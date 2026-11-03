<?php

namespace App\Services\Viabilizacion;

/**
 * Decide qué requisitos del catálogo aplican a una configuración de expediente.
 * Es la fuente de verdad: el frontend solo refleja lo que devuelve el backend.
 *
 * Porta `compileCond` y el filtro de `render()` del prototipo
 * checklist-viabilizacion-sgr.html con su misma semántica, verificada contra la
 * lógica original en tests/Fixtures/paridad_evaluador_sgr.json.
 *
 * DSL de condiciones (secciones A, B y D):
 *   siempre · fase=3 · fase=1|2 · fase!=1 · comp=<clave> · esp=<clave>
 *   combinables con "&" (todas deben cumplirse) y "(comp=a|comp=b)" (alguna).
 *
 * Semántica heredada a tener presente: un átomo que el evaluador no reconoce se
 * considera verdadero, y dentro del paréntesis solo se reconoce comp=. Por eso
 * validarCondicion() existe y debe usarse antes de guardar cualquier condición.
 */
final class EvaluadorRequisitos
{
    private const CLAVE = '[A-Za-z0-9_]+';

    /** @var array<string, \Closure> condiciones ya compiladas */
    private array $compiladas = [];

    public function cumpleCondicion(?string $dsl, ConfiguracionExpediente $cfg): bool
    {
        $dsl ??= '';

        return ($this->compiladas[$dsl] ??= $this->compilar($dsl))($cfg);
    }

    /**
     * Requisitos aplicables, en el orden recibido.
     *
     * @param iterable<array{clave:string, seccion:string, condicion:?string, sector:?string, tipo:?string}> $requisitos
     * @return list<string> claves de los requisitos que aplican
     */
    public function aplicables(iterable $requisitos, ConfiguracionExpediente $cfg): array
    {
        $claves = [];
        foreach ($requisitos as $requisito) {
            if ($this->aplica($requisito, $cfg)) {
                $claves[] = $requisito['clave'];
            }
        }

        return $claves;
    }

    public function aplica(array $requisito, ConfiguracionExpediente $cfg): bool
    {
        $seccion = $requisito['seccion'];

        // Régimen especial de desastre (art. 4.1.2.2.9): reemplaza los generales.
        if ($cfg->esDesastre()) {
            if ($seccion === 'A') {
                return false;
            }
        } elseif ($seccion === 'D') {
            return false;
        }

        if ($seccion === 'C') {
            $sector = $requisito['sector'] ?? null;
            if ($sector === null || ! $cfg->tieneSector($sector)) {
                return false;
            }
            $marcados = $cfg->tiposDe($sector);
            if ($marcados === []) {
                return true; // sin tipos marcados: el sector completo
            }
            $tipo = $requisito['tipo'] ?? null;

            return $tipo === null || isset($marcados[$tipo]); // null = todo el sector
        }

        return $this->cumpleCondicion($requisito['condicion'] ?? null, $cfg);
    }

    /**
     * Errores de una condición antes de guardarla. Vacío = válida.
     *
     * @param list<string> $componentes claves válidas para comp=
     * @param list<string> $tipologias claves válidas para esp=
     * @return list<string>
     */
    public function validarCondicion(?string $dsl, array $componentes, array $tipologias): array
    {
        $dsl = trim((string) $dsl);
        if ($dsl === '') {
            return ['La condición no puede estar vacía; use "siempre" si aplica a todo.'];
        }

        $errores = [];
        foreach (explode('&', $dsl) as $parte) {
            $atomo = trim($parte);
            if ($atomo === '') {
                $errores[] = 'Hay un "&" sin condición a uno de sus lados.';
                continue;
            }
            if (in_array($atomo, ['siempre', 'fase=3', 'fase=1|2', 'fase!=1'], true)) {
                continue;
            }
            if (preg_match('/^\((.+)\)$/', $atomo, $m)) {
                foreach (array_map('trim', explode('|', $m[1])) as $alternativa) {
                    if (! preg_match('/^comp=(' . self::CLAVE . ')$/', $alternativa, $mc)) {
                        $errores[] = "Dentro de ( | ) solo se admite comp=<clave>; se encontró \"{$alternativa}\".";
                    } elseif (! in_array($mc[1], $componentes, true)) {
                        $errores[] = "El componente \"{$mc[1]}\" no existe en el catálogo.";
                    }
                }
                continue;
            }
            if (preg_match('/^comp=(' . self::CLAVE . ')$/', $atomo, $m)) {
                if (! in_array($m[1], $componentes, true)) {
                    $errores[] = "El componente \"{$m[1]}\" no existe en el catálogo.";
                }
                continue;
            }
            if (preg_match('/^esp=(' . self::CLAVE . ')$/', $atomo, $m)) {
                if (! in_array($m[1], $tipologias, true)) {
                    $errores[] = "La tipología \"{$m[1]}\" no existe en el catálogo.";
                }
                continue;
            }
            $errores[] = "Condición no reconocida: \"{$atomo}\". Se admiten: siempre, fase=3, fase=1|2, fase!=1, comp=<clave>, esp=<clave> y (comp=a|comp=b).";
        }

        return $errores;
    }

    /** Traducción literal de compileCond(). */
    private function compilar(string $dsl): \Closure
    {
        if (trim($dsl) === '') {
            return fn () => true;
        }

        $partes = array_values(array_filter(array_map('trim', explode('&', $dsl)), fn ($p) => $p !== ''));

        $funciones = array_map(function (string $p): \Closure {
            if ($p === 'siempre') {
                return fn () => true;
            }
            if ($p === 'fase=3') {
                return fn (ConfiguracionExpediente $c) => $c->fase === 3;
            }
            if ($p === 'fase=1|2') {
                return fn (ConfiguracionExpediente $c) => $c->fase === 1 || $c->fase === 2;
            }
            if ($p === 'fase!=1') {
                return fn (ConfiguracionExpediente $c) => $c->fase !== 1;
            }
            if (preg_match('/^\((.+)\)$/', $p, $m)) {
                $alternativas = array_map('trim', explode('|', $m[1]));

                return function (ConfiguracionExpediente $c) use ($alternativas) {
                    foreach ($alternativas as $a) {
                        // Igual que el original: búsqueda no anclada de comp=<clave>.
                        if (preg_match('/comp=(' . self::CLAVE . ')/', $a, $mc) && $c->tieneComponente($mc[1])) {
                            return true;
                        }
                    }

                    return false;
                };
            }
            if (preg_match('/^comp=(' . self::CLAVE . ')$/', $p, $m)) {
                return fn (ConfiguracionExpediente $c) => $c->tieneComponente($m[1]);
            }
            if (preg_match('/^esp=(' . self::CLAVE . ')$/', $p, $m)) {
                return fn (ConfiguracionExpediente $c) => $c->tieneTipologia($m[1]);
            }

            return fn () => true; // heredado del prototipo; ver validarCondicion()
        }, $partes);

        return function (ConfiguracionExpediente $c) use ($funciones) {
            foreach ($funciones as $f) {
                if (! $f($c)) {
                    return false;
                }
            }

            return true;
        };
    }
}
