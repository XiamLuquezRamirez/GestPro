// Lógica de presentación de la ficha del proyecto (/proyectos/:proyectoId, GET /proyectos/{id}/ficha):
// el bloque General, el resumen de la formulación y las filas de procesos. Las filas de contratos
// son las de la lista de Contratos (filaContrato). Los avances del expediente, de los procesos y de
// cada contrato se presentan por separado: nunca se suman ni se promedian. Funciones puras,
// probadas en fichaProyecto.test.js.

import { esIdContrato } from './contratosModulo';
import { etiquetaEstadoProceso, numeroProceso, textoContratos, textoFecha, textoValor, tonoEstado } from './contratacion';
import { faseLegible } from './expedienteChecklist';
import { mensajeApi } from './plantillasChecklist';

/** Si el `:proyectoId` de la ruta es un id: la misma regla que el de un contrato (lo que acepta la API). */
export const esIdProyecto = (valor) => esIdContrato(valor);

/** El error al cargar la ficha: un proyecto que no existe no se reintenta. */
export const errorDeFicha = (error) => (error?.response?.status === 404
    ? { mensaje: 'El proyecto no existe.', reintentable: false }
    : { mensaje: mensajeApi(error), reintentable: true });

const texto = (valor, siVacio = '—') => String(valor ?? '').trim() || siVacio;
const mismoId = (a, b) => String(a) === String(b);
const plural = (n, singular, varios) => (n === 1 ? `1 ${singular}` : `${n} ${varios}`);

// ---------- General ----------

/**
 * Las fuentes del proyecto por su nombre en el catálogo (GET /fuentesFinanciacion). Sin el
 * catálogo (cargando o falló), el texto de financiación que el proyecto ya tiene o cuántas son.
 */
export const textoFuentes = (proyecto, fuentes) => {
    const ids = proyecto?.fuente_ids ?? [];
    if (Boolean(Number(proyecto?.financiacion_por_definir)) && ids.length === 0) return 'Por definir';
    if (ids.length === 0) return '—';
    if (!Array.isArray(fuentes)) return texto(proyecto.fuente_financiacion, plural(ids.length, 'fuente', 'fuentes'));
    return ids.map((id) => fuentes.find((f) => mismoId(f.id, id))?.nombre ?? `Fuente ${id}`).join(', ');
};

/** El sector del proyecto por su nombre en el catálogo (GET /sectores), o una raya. */
export const textoSector = (proyecto, sectores) => {
    if (proyecto?.sector === null || proyecto?.sector === undefined || !Array.isArray(sectores)) return '—';
    return texto(sectores.find((s) => mismoId(s.id, proyecto.sector))?.nombre);
};

/**
 * Los datos del bloque General ({etiqueta, valor, ancho}). El presupuesto es la suma de los
 * componentes; sin componentes, el presupuesto del proyecto (como en el tablero).
 */
export const datosGenerales = (proyecto, { fuentes = null, sectores = null } = {}) => [
    { etiqueta: 'Municipio', valor: texto(proyecto.descripcion_municipio) },
    { etiqueta: 'Fase', valor: texto(proyecto.descripcion_fase) },
    { etiqueta: 'Estado', valor: texto(proyecto.descripcion_estado) },
    { etiqueta: 'Sector', valor: textoSector(proyecto, sectores) },
    { etiqueta: 'Fuentes de financiación', valor: textoFuentes(proyecto, fuentes) },
    { etiqueta: 'Presupuesto', valor: textoValor(Number(proyecto.totalPresupuesto) || proyecto.presupuesto) },
    { etiqueta: 'Fecha de inicio', valor: textoFecha(proyecto.fecha_inicio) },
    { etiqueta: 'Entidad que presenta', valor: texto(proyecto.descripcion_entidad_presenta) },
    { etiqueta: 'Entidad que financia', valor: texto(proyecto.descripcion_entidad_financia) },
    { etiqueta: 'Descripción', valor: texto(proyecto.descripcion), ancho: true },
];

// ---------- Formulación ----------

const ESTADO_EXPEDIENTE = { borrador: 'Borrador', radicado: 'Radicado' };

/**
 * El resumen del expediente más reciente (el de GET /expedientes) con su propio avance de
 * requisitos. Sin configurar, la lista de requisitos aún no es la real: no hay avance.
 */
export const resumenFormulacion = (expediente, proyectoId) => {
    if (!expediente) return { tiene: false, texto: 'Sin expediente' };

    const sinConfigurar = expediente.estado === 'borrador' && !expediente.configurado;
    const { total = 0, cumplidos = 0, porcentaje = 0 } = expediente.progreso ?? {};
    const cantidad = Number(expediente.cantidad_expedientes) || 0;

    return {
        tiene: true,
        estado: sinConfigurar ? 'Sin configurar' : ESTADO_EXPEDIENTE[expediente.estado] ?? texto(expediente.estado),
        porcentaje: sinConfigurar ? null : porcentaje,
        avance: sinConfigurar
            ? 'Sin configurar: aún no hay requisitos que medir'
            : `${porcentaje}% (${cumplidos} de ${total} requisitos cumplidos)`,
        plantillas: (expediente.plantillas ?? []).length ? expediente.plantillas.join(', ') : '—',
        // La fase solo es de regalías: se muestra si el expediente tiene una plantilla con motor sgr.
        faseSgr: expediente.tiene_sgr ? faseLegible(expediente.fase_sgr) : null,
        radicado: expediente.fecha_radicado ? textoFecha(expediente.fecha_radicado) : null,
        otros: cantidad > 1 ? `El proyecto tiene ${cantidad} expedientes: se muestra el más reciente.` : null,
        ruta: `/viabilizacion/${proyectoId}`,
    };
};

// ---------- Procesos ----------

/** Una fila de la lista de procesos (un elemento de GET /proyectos/{id}/procesos). */
export const filaProceso = (proceso) => {
    const numero = numeroProceso(proceso);
    return {
        id: proceso.id,
        numero: numero.texto,
        enlace: numero.enlace,
        objeto: texto(proceso.objeto),
        modalidad: texto(proceso.modalidad?.nombre),
        estado: etiquetaEstadoProceso(proceso.estado),
        tono: tonoEstado(proceso.estado),
        monto: textoValor(proceso.monto),
        contratos: textoContratos(proceso.contratos),
    };
};
