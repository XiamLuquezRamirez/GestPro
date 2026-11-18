<?php

namespace App\Services\Contratacion;

use App\Exceptions\SecopNoDisponibleException;
use App\Models\Contrato;
use App\Models\TipoProceso;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Consulta de contratos en SECOP II (datos abiertos de datos.gov.co, conjunto jbjy-vk9h, API
 * Socrata). Solo lee: lo encontrado se muestra y la pantalla decide qué copiar al contrato.
 *
 * - Los parámetros viajan como query de la petición (Http los codifica): nunca se pegan a la URL.
 * - Los valores escritos por el usuario que van dentro de SoQL ($where) se escapan duplicando las
 *   comillas simples; los ID (CO1.PCCNTR.…, CO1.BDOS.…) y los NIT ya llegan reducidos a su forma.
 * - Cada resultado es FilaSecop::mapear() más `registrado`: {id, n_contrato} del contrato de
 *   GestPro con ese secop_id_contrato, o null.
 * - Sin conexión, tiempo agotado, HTTP distinto de 200 o una respuesta que no es una lista:
 *   SecopNoDisponibleException (502).
 */
final class SecopII
{
    public const LIMITE_BUSQUEDA = 25;

    public const LIMITE_EMPRESA = 50;

    public const MAX_NUMERO = 100;

    public const AVISO_NTC = 'Ese enlace es del aviso del proceso. Pega el enlace o el ID del contrato (CO1.PCCNTR…) o búscalo por su número.';

    public const ENLACE_SIN_ID = 'Ese enlace no trae el ID del contrato. Pega el ID (CO1.PCCNTR…) o busca el contrato por su número.';

    public const NUMERO_LARGO = 'El número del contrato admite hasta 100 caracteres.';

    public const NIT_INVALIDO = 'Escribe el NIT solo con números (puede llevar puntos y el dígito de verificación con guion).';

    /** El NIT como se escribe: cifras con puntos o espacios y, opcional, -dígito de verificación. */
    public const FORMA_NIT = '/^[0-9][0-9. ]*(-[0-9])?$/';

    public const FORMA_ID_CONTRATO = '/^CO1\.PCCNTR\.\d+$/';

    /**
     * Interpreta lo que pegó el usuario: un ID o un enlace del contrato (CO1.PCCNTR.…), uno del
     * proceso de compra (CO1.BDOS.… o CO1.REQ.…) o el número del contrato, este último junto con
     * el NIT de la entidad si viene. El enlace del aviso (CO1.NTC.…) u otro enlace sin ID se
     * rechazan con un mensaje para `q`.
     *
     * @return list<array<string, mixed>>
     */
    public function buscar(string $texto, ?string $nitEntidad = null): array
    {
        $texto = trim($texto);

        if (preg_match('/CO1\.PCCNTR\.\d+/i', $texto, $m) === 1) {
            $consulta = ['id_contrato' => strtoupper($m[0])];
        } elseif (preg_match('/CO1\.(BDOS|REQ)\.\d+/i', $texto, $m) === 1) {
            $consulta = ['proceso_de_compra' => strtoupper($m[0])];
        } elseif (preg_match('/CO1\.NTC\.\d+/i', $texto) === 1) {
            throw ValidationException::withMessages(['q' => self::AVISO_NTC]);
        } elseif (preg_match('#^https?://|secop\.gov\.co#i', $texto) === 1) {
            throw ValidationException::withMessages(['q' => self::ENLACE_SIN_ID]);
        } else {
            if (mb_strlen($texto) > self::MAX_NUMERO) {
                throw ValidationException::withMessages(['q' => self::NUMERO_LARGO]);
            }
            $where = 'upper(referencia_del_contrato) = ' . self::literal(mb_strtoupper($texto));
            $nit = $nitEntidad !== null && trim($nitEntidad) !== '' ? self::nit($nitEntidad, 'nit') : null;
            if ($nit !== null) {
                $where .= ' AND nit_entidad = ' . self::literal($nit);
            }
            $consulta = ['$where' => $where, '$order' => 'fecha_de_firma DESC'];
        }

        return $this->presentar($this->consultar([...$consulta, '$limit' => self::LIMITE_BUSQUEDA]));
    }

    /**
     * Los contratos en que la empresa es el proveedor, del más reciente (por fecha de firma) al
     * más antiguo. El NIT puede traer puntos y el dígito de verificación: se quitan.
     *
     * @return list<array<string, mixed>>
     */
    public function deEmpresa(string $nit): array
    {
        return $this->presentar($this->consultar([
            'documento_proveedor' => self::nit($nit, 'nit'),
            '$order' => 'fecha_de_firma DESC',
            '$limit' => self::LIMITE_EMPRESA,
        ]));
    }

    /** Un contrato por su ID de SECOP II (CO1.PCCNTR.…), o null si SECOP II no lo tiene. */
    public function porId(string $idContrato): ?array
    {
        $id = strtoupper(trim($idContrato));
        if (preg_match(self::FORMA_ID_CONTRATO, $id) !== 1) {
            throw ValidationException::withMessages(['idContrato' => 'El ID del contrato de SECOP II tiene la forma CO1.PCCNTR.123456.']);
        }

        return $this->presentar($this->consultar(['id_contrato' => $id, '$limit' => 1]))[0] ?? null;
    }

    /** Solo las cifras del NIT, sin puntos, espacios ni el dígito de verificación. */
    public static function nit(string $nit, string $campo): string
    {
        $nit = trim($nit);
        if (preg_match(self::FORMA_NIT, $nit) !== 1) {
            throw ValidationException::withMessages([$campo => self::NIT_INVALIDO]);
        }

        return preg_replace('/[^0-9]/', '', preg_replace('/-[0-9]$/', '', $nit));
    }

    /** Un texto como literal de SoQL: entre comillas simples, con las internas duplicadas. */
    public static function literal(string $valor): string
    {
        return "'" . str_replace("'", "''", $valor) . "'";
    }

    /**
     * @param array<string, string|int> $consulta
     * @return list<array<string, mixed>>
     */
    private function consultar(array $consulta): array
    {
        $config = config('services.secop');
        $pedido = Http::acceptJson()->timeout((int) ($config['timeout'] ?? 15));
        if (! empty($config['token'])) {
            $pedido = $pedido->withHeaders(['X-App-Token' => $config['token']]);
        }
        // Paquete de certificados propio, para equipos cuyo PHP no reconoce la cadena (por
        // ejemplo, con un antivirus que inspecciona HTTPS). Nunca se desactiva la verificación.
        if (! empty($config['ca_bundle'])) {
            $pedido = $pedido->withOptions(['verify' => $config['ca_bundle']]);
        }

        try {
            /** @var Response $respuesta */
            $respuesta = $pedido->get($config['url'], $consulta);
        } catch (ConnectionException $e) {
            throw new SecopNoDisponibleException('SECOP II sin conexión: ' . $e->getMessage(), 0, $e);
        }
        if ($respuesta->status() !== 200) {
            throw new SecopNoDisponibleException('SECOP II respondió HTTP ' . $respuesta->status());
        }
        $filas = $respuesta->json();
        if (! is_array($filas) || ! array_is_list($filas)) {
            throw new SecopNoDisponibleException('SECOP II respondió algo que no es una lista.');
        }

        return array_values(array_filter($filas, 'is_array'));
    }

    /**
     * @param list<array<string, mixed>> $filas
     * @return list<array<string, mixed>>
     */
    private function presentar(array $filas): array
    {
        if ($filas === []) {
            return [];
        }
        $tipos = TipoProceso::where('activo', true)->orderBy('id')->pluck('id', 'nombre')->map(fn ($id) => (int) $id)->all();
        $mapeadas = array_map(fn (array $fila) => FilaSecop::mapear($fila, $tipos), $filas);

        $ids = array_values(array_unique(array_filter(array_map(fn (array $m) => $m['contrato']['secop_id_contrato'], $mapeadas))));
        $registrados = $ids === [] ? collect() : Contrato::whereIn('secop_id_contrato', $ids)
            ->get(['id', 'n_contrato', 'secop_id_contrato'])
            ->keyBy('secop_id_contrato');

        return array_map(function (array $m) use ($registrados) {
            $c = $registrados->get($m['contrato']['secop_id_contrato']);

            return [...$m, 'registrado' => $c ? ['id' => $c->id, 'n_contrato' => $c->n_contrato] : null];
        }, $mapeadas);
    }
}
