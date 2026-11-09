<?php

namespace App\Services\Viabilizacion;

use App\Models\ChecklistHistorial;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\User;

/**
 * Registro de lo que pasa en el checklist: quién cambió qué y cuándo.
 *
 * Los soportes reemplazados o retirados no se borran: su ruta queda en `antes` y se
 * descargan por el id del registro. La ruta nunca sale en la API.
 */
final class HistorialChecklist
{
    public const REQUISITO = 'requisito';
    public const EXPEDIENTE = 'expediente';

    /** Entradas por página del historial del expediente. */
    public const POR_PAGINA = 50;

    public function registrarFila(ProyectoRequisito $fila, string $evento, array $antes, array $despues, ?User $usuario): void
    {
        $this->registrar(self::REQUISITO, $fila->id, $fila->expediente_id, $evento, $antes, $despues, $usuario);
    }

    public function registrarExpediente(ProyectoExpediente $expediente, string $evento, array $antes, array $despues, ?User $usuario): void
    {
        $this->registrar(self::EXPEDIENTE, $expediente->id, $expediente->id, $evento, $antes, $despues, $usuario);
    }

    /** Historial de una fila, del más reciente al más antiguo. */
    public function deFila(ProyectoRequisito $fila): array
    {
        return ChecklistHistorial::query()
            ->where('entidad_tipo', self::REQUISITO)
            ->where('entidad_id', $fila->id)
            ->with('usuario:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ChecklistHistorial $h) => $this->presentar($h))
            ->all();
    }

    /**
     * Una página del historial completo del expediente, del más reciente al más antiguo: sus
     * propios eventos y los de todas sus filas, también los de las ya eliminadas. Se pagina
     * desde 1; una página fuera de rango llega vacía, con el mismo total.
     *
     * Cada entrada es la de presentar() más `entidad` y `entidad_id`, para que quien arma la
     * respuesta encuentre la fila de las entradas de requisito.
     *
     * @return array{entradas: list<array>, por_pagina: int, total: int}
     */
    public function paginaDeExpediente(ProyectoExpediente $expediente, int $pagina): array
    {
        $consulta = ChecklistHistorial::query()->where('expediente_id', $expediente->id);
        $total = (clone $consulta)->count();
        // Fuera de rango no se consulta: así tampoco se calcula un desplazamiento enorme.
        $entradas = $pagina > intdiv($total + self::POR_PAGINA - 1, self::POR_PAGINA)
            ? collect()
            : $consulta->with('usuario:id,name')->orderByDesc('id')->forPage($pagina, self::POR_PAGINA)->get();

        return [
            'entradas' => $entradas->map(fn (ChecklistHistorial $h) => [
                ...$this->presentar($h),
                'entidad' => $h->entidad_tipo,
                'entidad_id' => $h->entidad_id,
            ])->all(),
            'por_pagina' => self::POR_PAGINA,
            'total' => $total,
        ];
    }

    /**
     * Estado de aplicabilidad que tenía la fila cuando se marcó su último "no aplica", o null
     * si no hay registro.
     */
    public function estadoAlMarcarNoAplica(ProyectoRequisito $fila): ?string
    {
        $entrada = ChecklistHistorial::query()
            ->where('entidad_tipo', self::REQUISITO)
            ->where('entidad_id', $fila->id)
            ->where('evento', 'no_aplica_marcado')
            ->orderByDesc('id')
            ->first();

        return $entrada?->antes['estado'] ?? null;
    }

    /** @return list<string> rutas de los soportes conservados en el historial del expediente */
    public function archivosDe(ProyectoExpediente $expediente): array
    {
        return ChecklistHistorial::query()
            ->where('expediente_id', $expediente->id)
            ->get()
            ->map(fn (ChecklistHistorial $h) => $h->antes['soporte_archivo'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * `usuario` es el nombre de quien registró la entrada, o null. `sistema` dice por qué falta:
     * true si nadie la registró (un comando, como expedientes:crear-faltantes); false con un
     * usuario_id cuyo usuario ya no existe (la tabla no tiene clave foránea a users).
     */
    public function presentar(ChecklistHistorial $entrada): array
    {
        return [
            'id' => $entrada->id,
            'evento' => $entrada->evento,
            'antes' => $this->sinRuta($entrada->antes),
            'despues' => $this->sinRuta($entrada->despues),
            'usuario' => $entrada->usuario?->name,
            'sistema' => $entrada->usuario_id === null,
            'fecha' => $entrada->created_at?->toIso8601String(),
            'soporte' => isset($entrada->antes['soporte_archivo'])
                ? ['nombre' => $entrada->antes['soporte_nombre'] ?? 'soporte']
                : null,
        ];
    }

    private function registrar(string $tipo, int $id, ?int $expedienteId, string $evento, array $antes, array $despues, ?User $usuario): void
    {
        ChecklistHistorial::create([
            'entidad_tipo' => $tipo,
            'entidad_id' => $id,
            'expediente_id' => $expedienteId,
            'evento' => $evento,
            'antes' => $antes ?: null,
            'despues' => $despues ?: null,
            'usuario_id' => $usuario?->id,
        ]);
    }

    private function sinRuta(?array $datos): ?array
    {
        if ($datos === null) {
            return null;
        }
        unset($datos['soporte_archivo']);

        return $datos;
    }
}
