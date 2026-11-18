<?php

namespace App\Services\Contratacion;

use App\Models\Contrato;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Str;

/**
 * Una fila de SECOP II (datos abiertos, jbjy-vk9h) llevada a los campos del contrato. Función
 * pura: los tipos de contrato activos llegan como argumento.
 *
 * - `contrato`: lo que se puede copiar al formulario, con los nombres de POST /guardarContrato.
 *   Lo que falta o no se entiende queda en null (la pantalla lo pide).
 * - `secop`: lo que SECOP II dice y GestPro no guarda; sirve para avisar (el estado o el tipo sin
 *   equivalente, los días adicionados que hay que registrar como prórroga).
 */
final class FilaSecop
{
    /**
     * Estado de SECOP II (sin mayúsculas ni tildes) => estado del contrato. Otro (p. ej. "En
     * aprobación", "enviado Proveedor", "Borrador"): null, y la pantalla pide elegirlo.
     */
    private const ESTADOS = [
        'en ejecucion' => Contrato::EN_EJECUCION,
        'activo' => Contrato::EN_EJECUCION,
        'modificado' => Contrato::EN_EJECUCION,
        'prorrogado' => Contrato::EN_EJECUCION,
        'cedido' => Contrato::EN_EJECUCION,
        'celebrado' => Contrato::CELEBRADO,
        'firmado' => Contrato::CELEBRADO,
        'aprobado' => Contrato::CELEBRADO,
        'suspendido' => Contrato::SUSPENDIDO,
        'terminado' => Contrato::TERMINADO,
        'cerrado' => Contrato::TERMINADO,
        'liquidado' => Contrato::LIQUIDADO,
        'cancelado' => Contrato::ANULADO,
        'anulado' => Contrato::ANULADO,
    ];

    /**
     * Tipo de SECOP II (normalizado) => nombre del tipo sembrado en el catálogo, para los que no
     * coinciden por nombre. Si ese tipo no existe o está inactivo, no se propone ninguno.
     */
    private const TIPOS_EQUIVALENTES = [
        'suministros' => 'Suministro',
        'acuerdo marco de precios' => 'Orden de compra',
    ];

    /**
     * @param array<string, mixed> $fila
     * @param array<string, int> $tiposActivos nombre del tipo de contrato => id
     * @return array{contrato: array<string, mixed>, secop: array<string, mixed>}
     */
    public static function mapear(array $fila, array $tiposActivos): array
    {
        $tipoSecop = self::texto($fila, 'tipo_de_contrato');
        $estadoSecop = self::texto($fila, 'estado_contrato');

        return [
            'contrato' => [
                'n_contrato' => self::texto($fila, 'referencia_del_contrato'),
                'objeto' => self::texto($fila, 'objeto_del_contrato') ?? self::texto($fila, 'descripcion_del_proceso'),
                'contratante' => self::texto($fila, 'nombre_entidad'),
                'contratante_nit' => self::texto($fila, 'nit_entidad'),
                'contratista' => self::texto($fila, 'proveedor_adjudicado'),
                'contratista_identificacion' => self::texto($fila, 'documento_proveedor'),
                'valor_inicial' => self::valor($fila, 'valor_del_contrato'),
                'fecha_suscripcion' => self::fecha($fila, 'fecha_de_firma'),
                'fecha_inicio' => self::fecha($fila, 'fecha_de_inicio_del_contrato'),
                'fecha_fin_inicial' => self::fecha($fila, 'fecha_de_fin_del_contrato'),
                'tipo_contrato_id' => self::tipo($tipoSecop, $tiposActivos),
                'estado' => $estadoSecop === null ? null : (self::ESTADOS[self::normalizar($estadoSecop)] ?? null),
                'secop_id_contrato' => self::texto($fila, 'id_contrato'),
                'secop_proceso' => self::texto($fila, 'proceso_de_compra'),
                'url_secop' => self::enlace($fila['urlproceso'] ?? null),
            ],
            'secop' => [
                'tipo_contrato_secop' => $tipoSecop,
                'modalidad_secop' => self::texto($fila, 'modalidad_de_contratacion'),
                'estado_secop' => $estadoSecop,
                'dias_adicionados' => self::entero($fila, 'dias_adicionados'),
                'valor_pagado' => self::valor($fila, 'valor_pagado'),
                'valor_facturado' => self::valor($fila, 'valor_facturado'),
            ],
        ];
    }

    /** Sin mayúsculas, tildes ni espacios sobrantes. */
    public static function normalizar(string $texto): string
    {
        return preg_replace('/\s+/', ' ', Str::lower(Str::ascii(trim($texto))));
    }

    private static function texto(array $fila, string $campo): ?string
    {
        $valor = $fila[$campo] ?? null;
        if (! is_string($valor) && ! is_int($valor) && ! is_float($valor)) {
            return null;
        }
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    /** Con 2 decimales, como las columnas decimal(15, 2). */
    private static function valor(array $fila, string $campo): ?string
    {
        $texto = self::texto($fila, $campo);
        if ($texto === null) {
            return null;
        }
        try {
            return (string) BigDecimal::of($texto)->toScale(2, RoundingMode::HALF_UP);
        } catch (MathException) {
            return null;
        }
    }

    /** SECOP II da fecha y hora local (2022-09-30T14:21:47.161): solo se toma la fecha. */
    private static function fecha(array $fila, string $campo): ?string
    {
        $texto = self::texto($fila, $campo);
        if ($texto === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $texto, $m) !== 1) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "{$m[1]}-{$m[2]}-{$m[3]}" : null;
    }

    private static function entero(array $fila, string $campo): ?int
    {
        $texto = self::texto($fila, $campo);

        return $texto !== null && preg_match('/^-?\d+$/', $texto) === 1 ? (int) $texto : null;
    }

    /** @param array<string, int> $tiposActivos */
    private static function tipo(?string $tipoSecop, array $tiposActivos): ?int
    {
        if ($tipoSecop === null) {
            return null;
        }
        $buscado = self::normalizar($tipoSecop);
        $equivalente = self::TIPOS_EQUIVALENTES[$buscado] ?? null;
        foreach ([$buscado, ...($equivalente !== null ? [self::normalizar($equivalente)] : [])] as $nombreBuscado) {
            foreach ($tiposActivos as $nombre => $id) {
                if (self::normalizar((string) $nombre) === $nombreBuscado) {
                    return $id;
                }
            }
        }

        return null;
    }

    /** `urlproceso` viene como {url: …}; se acepta también un texto. Solo http o https. */
    private static function enlace(mixed $urlproceso): ?string
    {
        $url = is_array($urlproceso) ? ($urlproceso['url'] ?? null) : $urlproceso;
        if (! is_string($url)) {
            return null;
        }
        $url = trim($url);

        return strlen($url) <= 500 && preg_match('#^https?://#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false
            ? $url
            : null;
    }
}
