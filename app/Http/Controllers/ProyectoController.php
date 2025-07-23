<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProyectoController extends Controller
{
    public function proyectos()
    {
        $proyectos = DB::table('proyectos')
            ->leftJoin('municipios', 'proyectos.municipio', 'municipios.codigo')
            ->leftJoin('entidades as entidad_presenta', 'proyectos.entidad_presenta', 'entidad_presenta.id')
            ->leftJoin('entidades as entidad_financia', 'proyectos.entidad_financia', 'entidad_financia.id')
            ->leftJoin('fases as fase', 'proyectos.fase', 'fase.id')
            ->leftJoin('estados as estado', 'proyectos.estado', 'estado.id')
            ->select(
                'proyectos.id',
                'proyectos.nombre',
                'proyectos.descripcion',
                'proyectos.municipio',
                'proyectos.fecha_inicio',
                'proyectos.estado',
                'proyectos.fase',
                'proyectos.presupuesto',
                'proyectos.entidad_presenta',
                'proyectos.entidad_financia',
                'proyectos.fuente_financiacion',
                'fase.color as color_fase',
                'estado.color as color_estado',
                DB::raw('municipios.nombre as descripcion_municipio'),
                DB::raw('entidad_presenta.nombre as descripcion_entidad_presenta'),
                DB::raw('entidad_financia.nombre as descripcion_entidad_financia'),
                DB::raw('fase.nombre as descripcion_fase'),
                DB::raw('estado.nombre as descripcion_estado')
            )
            ->get();

        foreach ($proyectos as $proyecto) {
            $componentesPresupuesto = DB::table('presupuesto_proyecto')
                ->select('componente', 'valor', 'id')
                ->where('proyecto', $proyecto->id)
                ->get();
            $proyecto->componentesPresupuesto = $componentesPresupuesto;
        }

        return response()->json($proyectos);
    }

    public function eventos()
    {
        $eventos = DB::table('eventos')
            ->leftJoin('proyectos', 'eventos.proyecto', 'proyectos.id')
            ->leftJoin('tipo_eventos', 'eventos.tipo_eventos', 'tipo_eventos.id')
            ->leftJoin('prioridades', 'eventos.prioridad', 'prioridades.id')
            ->leftJoin('responsable', 'eventos.responsable', 'responsable.id')
            ->select(
                'eventos.id',
                'eventos.titulo',
                'eventos.descripcion',
                'eventos.fecha',
                'eventos.tipo_eventos',
                'eventos.prioridad',
                'eventos.responsable',
                'eventos.estado',
                'tipo_eventos.icono',
                DB::raw('proyectos.nombre as descripcion_proyecto'),
                DB::raw('tipo_eventos.nombre as descripcion_tipo_evento'),
                DB::raw('CONCAT(prioridades.color, " ", prioridades.nombre) as descripcion_prioridad'),
                DB::raw('responsable.nombre as descripcion_responsable')
            )
            ->orderBy('eventos.fecha', 'desc')
            ->get();
        return response()->json($eventos);
    }

    public function municipios()
    {
        $municipios = DB::table('municipios')
            ->select(
                'municipios.nombre',
                'municipios.codigo',
                'departamentos.nombre as departamento',
                'municipios.activo',
                'municipios.id',
                'departamentos.codigo as codigo_departamento'
            )
            ->leftJoin('departamentos', 'municipios.departamento', 'departamentos.codigo')
            ->orderBy('municipios.nombre', 'asc')
            ->get();
        return response()->json($municipios);
    }

    public function estados()
    {
        $estados = DB::table('estados')
            ->select('nombre', 'id', 'color', 'activo')
            ->orderBy('nombre', 'asc')
            ->get();
        return response()->json($estados);
    }

    public function fases()
    {
        $fases = DB::table('fases')
            ->select('nombre', 'id', 'color', 'activo', 'dashboard')
            ->orderBy('nombre', 'asc')
            ->get();
        return response()->json($fases);
    }

    public function entidades()
    {
        $entidades = DB::table('entidades')
            ->select('nombre', 'id', 'activo')
            ->orderBy('nombre', 'asc')
            ->get();
        return response()->json($entidades);
    }

    public function departamentos()
    {
        $departamentos = DB::table('departamentos')
            ->select('nombre', 'codigo')
            ->orderBy('nombre', 'asc')
            ->get();
        return response()->json($departamentos);
    }

    public function tiposEventos()
    {
        $tiposEventos = DB::table('tipo_eventos')
            ->select('nombre', 'id', 'icono', 'activo')
            ->orderBy('nombre', 'asc')
            ->get();
        return response()->json($tiposEventos);
    }

    public function prioridades()
    {
        $prioridades = DB::table('prioridades')
            ->select('nombre', 'id', 'color', 'activo')
            ->orderBy('nombre', 'asc')
            ->get();
        return response()->json($prioridades);
    }

    public function responsables()
    {
        $responsables = DB::table('responsable')
            ->select('nombre', 'id', 'email', 'cargo', 'activo')
            ->orderBy('nombre', 'asc')
            ->get();
        return response()->json($responsables);
    }

    public function activarMunicipio(Request $request)
    {
        $municipio = $request->all();
        if ($municipio['activo']) {
            DB::table('municipios')->where('id', $municipio['id'])->update(['activo' => '0']);
        } else {
            DB::table('municipios')->where('id', $municipio['id'])->update(['activo' => '1']);
        }
        return response()->json(['success' => 'Municipio activado correctamente']);
    }

    public function activarEstado(Request $request)
    {
        $estado = $request->all();
        if ($estado['activo']) {
            DB::table('estados')->where('id', $estado['id'])->update(['activo' => '0']);
        } else {
            DB::table('estados')->where('id', $estado['id'])->update(['activo' => '1']);
        }
        return response()->json(['success' => 'Estado activado correctamente']);
    }

    public function activarFaseDashboard(Request $request)
    {
        $fase = $request->all();
        if ($fase['dashboard']) {
            DB::table('fases')->where('id', $fase['id'])->update(['dashboard' => '0']);
        } else {
            DB::table('fases')->where('id', $fase['id'])->update(['dashboard' => '1']);
        }
        return response()->json(['success' => 'Fase activada en Dashboard correctamente']);
    }

    public function activarFase(Request $request)
    {
        $fase = $request->all();
        if ($fase['activo']) {
            DB::table('fases')->where('id', $fase['id'])->update(['activo' => '0']);
        } else {
            DB::table('fases')->where('id', $fase['id'])->update(['activo' => '1']);
        }
        return response()->json(['success' => 'Fase activada correctamente']);
    }

    public function guardarProyecto(Request $request)
    {
        $proyecto = $request->all();
        DB::beginTransaction();
        try {
            if ($proyecto['accion'] == 'Agregar') {
                $proyectoId = DB::table('proyectos')->insertGetId([
                    'municipio' => $proyecto['municipio'],
                    'nombre' => $proyecto['nombre'],
                    'descripcion' => $proyecto['descripcion'],
                    'fecha_inicio' => $proyecto['fechaInicio'],
                    'estado' => $proyecto['estado'],
                    'fase' => $proyecto['fase'],
                    'presupuesto' => $proyecto['presupuesto'],
                    'entidad_presenta' => $proyecto['entidadPresenta'],
                    'entidad_financia' => $proyecto['entidadFinancia'],
                    'fuente_financiacion' => $proyecto['fuenteFinanciamiento']
                ]);

                if (isset($proyecto['componentesPresupuesto']) && count($proyecto['componentesPresupuesto']) > 0) {
                    foreach ($proyecto['componentesPresupuesto'] as $presupuesto) {
                        DB::table('presupuesto_proyecto')->insert([
                            'proyecto' => $proyectoId,
                            'componente' => $presupuesto['descripcionComponente'],
                            'valor' => $presupuesto['valor']
                        ]);
                    }
                }
            } else {

                DB::table('proyectos')->where('id', $proyecto['id'])->update([
                    'municipio' => $proyecto['municipio'],
                    'nombre' => $proyecto['nombre'],
                    'descripcion' => $proyecto['descripcion'],
                    'fecha_inicio' => $proyecto['fechaInicio'],
                    'estado' => $proyecto['estado'],
                    'fase' => $proyecto['fase'],
                    'presupuesto' => $proyecto['presupuesto'],
                    'entidad_presenta' => $proyecto['entidadPresenta'],
                    'entidad_financia' => $proyecto['entidadFinancia'],
                    'fuente_financiacion' => $proyecto['fuenteFinanciamiento']
                ]);

                DB::table('presupuesto_proyecto')->where('proyecto', $proyecto['id'])->delete();
                if (isset($proyecto['componentesPresupuesto']) && count($proyecto['componentesPresupuesto']) > 0) {
                    foreach ($proyecto['componentesPresupuesto'] as $presupuesto) {
                        DB::table('presupuesto_proyecto')->insert([
                            'proyecto' => $proyecto['id'],
                            'componente' => $presupuesto['descripcionComponente'],
                            'valor' => $presupuesto['valor']
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
        DB::commit();
        return response()->json(['success' => 'Proyecto guardado correctamente']);
    }

    public function eliminarProyecto(Request $request)
    {
        $proyecto = $request->all();
        DB::table('proyectos')->where('id', $proyecto['id'])->delete();
        return response()->json(['success' => 'Proyecto eliminado correctamente']);
    }

    public function eliminarEvento(Request $request)
    {
        $evento = $request->all();
        DB::table('eventos')->where('id', $evento['id'])->delete();
        return response()->json(['success' => 'Evento eliminado correctamente']);
    }

    public function eliminarMunicipio(Request $request)
    {
        $municipio = $request->all();
        DB::table('municipios')->where('id', $municipio['id'])->delete();
        return response()->json(['success' => 'Municipio eliminado correctamente']);
    }

    public function guardarEvento(Request $request)
    {
        $evento = $request->all();
        DB::beginTransaction();
        try {
            if ($evento['accion'] == 'Agregar') {
                DB::table('eventos')->insert([
                    'titulo' => $evento['titulo'],
                    'descripcion' => $evento['descripcion'],
                    'fecha' => $evento['fecha'],
                    'tipo_eventos' => $evento['tipo'],
                    'prioridad' => $evento['prioridad'],
                    'estado' => $evento['estado'],
                    'responsable' => $evento['responsable'],
                    'proyecto' => $evento['proyecto']
                ]);
            } else {
                DB::table('eventos')->where('id', $evento['id'])->update(
                    [
                        'titulo' => $evento['titulo'],
                        'descripcion' => $evento['descripcion'],
                        'fecha' => $evento['fecha'],
                        'tipo_eventos' => $evento['tipo'],
                        'prioridad' => $evento['prioridad'],
                        'estado' => $evento['estado'],
                        'responsable' => $evento['responsable'],
                        'proyecto' => $evento['proyecto']
                    ]
                );
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
        DB::commit();
        return response()->json(['success' => 'Evento guardado correctamente']);
    }


    public function guardarMunicipio(Request $request)
    {
        $municipio = $request->all();
        DB::beginTransaction();
        try {
            if ($municipio['accion'] == 'Agregar') {
                DB::table('municipios')->insert([
                    'nombre' => $municipio['nombre'],
                    'codigo' => $municipio['codigo'],
                    'departamento' => $municipio['departamento'],
                    'activo' => $municipio['activo']
                ]);
            } else {
                DB::table('municipios')->where('id', $municipio['id'])->update([
                    'nombre' => $municipio['nombre'],
                    'codigo' => $municipio['codigo'],
                    'departamento' => $municipio['departamento']
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
        DB::commit();
        return response()->json(['success' => 'Municipio guardado correctamente']);
    }

    public function guardarEstado(Request $request)
    {
        $estado = $request->all();
        DB::beginTransaction();
        try {
            if ($estado['accion'] == 'Agregar') {
                DB::table('estados')->insert([
                    'nombre' => $estado['nombre'],
                    'color' => $estado['color'],
                    'activo' => $estado['activo']
                ]);
            } else {
                DB::table('estados')->where('id', $estado['id'])->update([
                    'nombre' => $estado['nombre'],
                    'color' => $estado['color']
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
        DB::commit();
        return response()->json(['success' => 'Estado guardado correctamente']);
    }

    public function eliminarEstado(Request $request)
    {
        $estado = $request->all();
        DB::table('estados')->where('id', $estado['id'])->delete();
        return response()->json(['success' => 'Estado eliminado correctamente']);
    }

    public function eliminarFase(Request $request)
    {
        $fase = $request->all();
        DB::table('fases')->where('id', $fase['id'])->delete();
        return response()->json(['success' => 'Fase eliminada correctamente']);
    }

    public function guardarFase(Request $request)
    {
        $fase = $request->all();
        DB::beginTransaction();
        try {
            if ($fase['accion'] == 'Agregar') {
                DB::table('fases')->insert([
                    'nombre' => $fase['nombre'],
                    'color' => $fase['color'],
                    'activo' => $fase['activo']
                ]);
            } else {
                DB::table('fases')->where('id', $fase['id'])->update([
                    'nombre' => $fase['nombre'],
                    'color' => $fase['color']
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
        DB::commit();
        return response()->json(['success' => 'Fase guardada correctamente']);
    }

    public function guardarTipoEvento(Request $request)
    {
        $tipoEvento = $request->all();
        DB::beginTransaction();
        try {
            if ($tipoEvento['accion'] == 'Agregar') {
                DB::table('tipo_eventos')->insert([
                    'nombre' => $tipoEvento['nombre'],
                    'icono' => $tipoEvento['icono'],
                    'activo' => $tipoEvento['activo']
                ]);
            } else {
                DB::table('tipo_eventos')->where('id', $tipoEvento['id'])->update([
                    'nombre' => $tipoEvento['nombre'],
                    'icono' => $tipoEvento['icono']
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
        DB::commit();
        return response()->json(['success' => 'Tipo de evento guardado correctamente']);
    }

    function guardarPrioridad(Request $request)
    {
        $prioridad = $request->all();
        DB::beginTransaction();
        try {
            if ($prioridad['accion'] == 'Agregar') {
                DB::table('prioridades')->insert([
                    'nombre' => $prioridad['nombre'],
                    'color' => $prioridad['color'],
                    'activo' => $prioridad['activo']
                ]);
            } else {
                DB::table('prioridades')->where('id', $prioridad['id'])->update([
                    'nombre' => $prioridad['nombre'],
                    'color' => $prioridad['color']
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
        DB::commit();
        return response()->json(['success' => 'Prioridad guardada correctamente']);

    }

    public function guardarResponsable(Request $request)
    {
        $responsable = $request->all();
        DB::beginTransaction();
        try {
            if ($responsable['accion'] == 'Agregar') {
                DB::table('responsable')->insert([
                    'nombre' => $responsable['nombre'],
                    'email' => $responsable['email'],
                    'cargo' => $responsable['cargo'],
                    'activo' => $responsable['activo']
                ]);
            } else {
                DB::table('responsable')->where('id', $responsable['id'])->update([
                    'nombre' => $responsable['nombre'],
                    'email' => $responsable['email'],
                    'cargo' => $responsable['cargo']
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
        DB::commit();
        return response()->json(['success' => 'Responsable guardado correctamente']);
    }

    public function eliminarPrioridad(Request $request)
    {
        $prioridad = $request->all();
        DB::table('prioridades')->where('id', $prioridad['id'])->delete();
        return response()->json(['success' => 'Prioridad eliminada correctamente']);
    }

    public function activarTipoEvento(Request $request)
    {
        $tipoEvento = $request->all();
        if ($tipoEvento['activo']) {
            DB::table('tipo_eventos')->where('id', $tipoEvento['id'])->update(['activo' => '0']);
        } else {
            DB::table('tipo_eventos')->where('id', $tipoEvento['id'])->update(['activo' => '1']);
        }
        return response()->json(['success' => 'Tipo de evento activado correctamente']);
    }

    public function activarPrioridad(Request $request)
    {
        $prioridad = $request->all();
        if ($prioridad['activo']) {
            DB::table('prioridades')->where('id', $prioridad['id'])->update(['activo' => '0']);
        } else {
            DB::table('prioridades')->where('id', $prioridad['id'])->update(['activo' => '1']);
        }
        return response()->json(['success' => 'Prioridad activada correctamente']);
    }

    public function activarResponsable(Request $request)
    {
        $responsable = $request->all();
        if ($responsable['activo']) {
            DB::table('responsable')->where('id', $responsable['id'])->update(['activo' => '0']);
        } else {
            DB::table('responsable')->where('id', $responsable['id'])->update(['activo' => '1']);
        }
        return response()->json(['success' => 'Responsable activado correctamente']);
    }

    public function eliminarTipoEvento(Request $request)
    {
        $tipoEvento = $request->all();
        DB::table('tipo_eventos')->where('id', $tipoEvento['id'])->delete();
        return response()->json(['success' => 'Tipo de evento eliminado correctamente']);
    }

    public function eliminarResponsable(Request $request)
    {
        $responsable = $request->all();
        DB::table('responsable')->where('id', $responsable['id'])->delete();
        return response()->json(['success' => 'Responsable eliminado correctamente']);
    }

    public function guardarEntidad(Request $request)
    {
        $entidad = $request->all();
        DB::beginTransaction();
        try {
            if ($entidad['accion'] == 'Agregar') {
                DB::table('entidades')->insert([
                    'nombre' => $entidad['nombre'],
                    'activo' => $entidad['activo']
                ]);
            } else {
                DB::table('entidades')->where('id', $entidad['id'])->update([
                    'nombre' => $entidad['nombre']
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
        DB::commit();
        return response()->json(['success' => 'Entidad guardada correctamente']);
    }

    public function activarEntidad(Request $request)
    {
        $entidad = $request->all();
        if ($entidad['activo']) {
            DB::table('entidades')->where('id', $entidad['id'])->update(['activo' => '0']);
        } else {
            DB::table('entidades')->where('id', $entidad['id'])->update(['activo' => '1']);
        }
        return response()->json(['success' => 'Entidad activada correctamente']);
    }

    public function eliminarEntidad(Request $request)
    {
        $entidad = $request->all();
        DB::table('entidades')->where('id', $entidad['id'])->delete();
        return response()->json(['success' => 'Entidad eliminada correctamente']);
    }

}
