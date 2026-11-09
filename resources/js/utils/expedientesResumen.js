// Lógica del seguimiento de expedientes en el Dashboard: une los proyectos con el
// resumen de GET /expedientes, que solo trae los proyectos que tienen expediente.

import { faseLegible } from './expedienteChecklist';
import { datosFinanciacionDe } from './fuentesProyecto';

/** Valores de los selectores que no son un id. */
export const TODAS_LAS_FUENTES = 'todas';
export const FUENTE_POR_DEFINIR = 'por_definir';
export const TODOS_LOS_RESPONSABLES = 'todos';

export const ESTADO = {
    SIN_CONFIGURAR: 'sin_configurar',
    SIN_EXPEDIENTE: 'sin_expediente',
    BORRADOR: 'borrador',
    RADICADO: 'radicado',
};

// Primero lo que requiere acción; lo radicado ya está cerrado.
const PRIORIDAD = {
    [ESTADO.SIN_CONFIGURAR]: 0,
    [ESTADO.BORRADOR]: 1,
    [ESTADO.SIN_EXPEDIENTE]: 2,
    [ESTADO.RADICADO]: 3,
};

const normalizar = (texto) =>
    String(texto ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim();

export const esFormulacion = (nombreFase) => normalizar(nombreFase) === 'formulacion';

const estadoDe = (resumen) => {
    if (!resumen) return ESTADO.SIN_EXPEDIENTE;
    return resumen.estado === ESTADO.BORRADOR && !resumen.configurado ? ESTADO.SIN_CONFIGURAR : resumen.estado;
};

const construirFila = (proyecto, resumen) => {
    const estado = estadoDe(resumen);
    // Sin configurar no hay avance que medir: la lista de requisitos aún no es la real.
    const progreso = estado === ESTADO.SIN_CONFIGURAR ? { porcentaje: null } : resumen?.progreso ?? {};
    const { total = 0, cumplidos = 0, porcentaje = 0 } = progreso;
    const { fuenteIds, financiacionPorDefinir } = datosFinanciacionDe(proyecto);

    return {
        proyectoId: proyecto.id,
        nombre: proyecto.nombre ?? '',
        municipio: proyecto.descripcion_municipio ?? '',
        faseProyecto: proyecto.descripcion_fase ?? '',
        estado,
        expedienteId: resumen?.expediente_id ?? null,
        cantidad: resumen?.cantidad_expedientes ?? 0,
        faseSgr: resumen?.fase_sgr ?? null,
        // La fase solo es de regalías: se muestra si el expediente tiene una plantilla con motor sgr.
        tieneSgr: Boolean(resumen?.tiene_sgr),
        plantillas: resumen?.plantillas ?? [],
        fechaRadicado: resumen?.fecha_radicado ?? null,
        actualizado: resumen?.actualizado ?? null,
        total,
        cumplidos,
        pendientes: total - cumplidos,
        porcentaje,
        fuenteIds,
        financiacionPorDefinir,
        // Los cuenta el servicio; sin configurar, como el avance, no se muestran.
        responsables: estado === ESTADO.SIN_CONFIGURAR ? [] : resumen?.responsables ?? [],
    };
};

const compararFilas = (a, b) =>
    PRIORIDAD[a.estado] - PRIORIDAD[b.estado] ||
    b.pendientes - a.pendientes ||
    a.nombre.localeCompare(b.nombre, 'es');

export const construirFilas = (proyectos, resumenes) => {
    const porProyecto = new Map(resumenes.map((r) => [r.proyecto_id, r]));

    return proyectos.map((p) => construirFila(p, porProyecto.get(p.id))).sort(compararFilas);
};

const mismoId = (a, b) => String(a) === String(b);

/**
 * Pendientes que muestra la fila: los del expediente o, con un responsable elegido, los
 * suyos, tal como los contó el servicio.
 */
export const pendientesDe = (fila, responsable = TODOS_LOS_RESPONSABLES) =>
    responsable === TODOS_LOS_RESPONSABLES
        ? fila.pendientes
        : fila.responsables.find((r) => mismoId(r.id, responsable))?.pendientes ?? 0;

/** Recibe las filas ya filtradas por fuente, responsable y texto, para contar lo que se ve. */
export const contarPorEstado = (filas, responsable = TODOS_LOS_RESPONSABLES) => {
    const cuantos = (estado) => filas.filter((f) => f.estado === estado).length;

    return {
        todos: filas.length,
        [ESTADO.SIN_CONFIGURAR]: cuantos(ESTADO.SIN_CONFIGURAR),
        [ESTADO.SIN_EXPEDIENTE]: cuantos(ESTADO.SIN_EXPEDIENTE),
        [ESTADO.BORRADOR]: cuantos(ESTADO.BORRADOR),
        [ESTADO.RADICADO]: cuantos(ESTADO.RADICADO),
        pendientesEnBorrador: filas
            .filter((f) => f.estado === ESTADO.BORRADOR)
            .reduce((suma, f) => suma + pendientesDe(f, responsable), 0),
    };
};

const tieneFuente = (fila, fuente) =>
    fuente === TODAS_LAS_FUENTES ||
    (fuente === FUENTE_POR_DEFINIR ? fila.financiacionPorDefinir : fila.fuenteIds.some((id) => mismoId(id, fuente)));

// Solo quedan los proyectos en los que esa persona tiene algo pendiente.
const tieneResponsable = (fila, responsable) =>
    responsable === TODOS_LOS_RESPONSABLES || fila.responsables.some((r) => mismoId(r.id, responsable));

export const filtrarFilas = (
    filas,
    { estado = 'todos', texto = '', fuente = TODAS_LAS_FUENTES, responsable = TODOS_LOS_RESPONSABLES },
) => {
    const buscado = normalizar(texto);

    return filas.filter(
        (f) =>
            (estado === 'todos' || f.estado === estado) &&
            tieneFuente(f, fuente) &&
            tieneResponsable(f, responsable) &&
            (buscado === '' || normalizar(`${f.nombre} ${f.municipio}`).includes(buscado)),
    );
};

/**
 * Opciones del filtro por fuente: las del catálogo (en su orden) que tiene algún proyecto de
 * la lista, también las inactivas, y "Por definir" si algún proyecto lo está. Sin catálogo
 * no hay filtro.
 */
export const opcionesFuentes = (fuentes, filas) => {
    if (!fuentes?.length) return [];

    const usadas = new Set(filas.flatMap((f) => f.fuenteIds.map(String)));
    const opciones = fuentes
        .filter((fuente) => usadas.has(String(fuente.id)))
        .map((fuente) => ({ valor: String(fuente.id), etiqueta: fuente.nombre }));

    return filas.some((f) => f.financiacionPorDefinir)
        ? [...opciones, { valor: FUENTE_POR_DEFINIR, etiqueta: 'Por definir' }]
        : opciones;
};

/** Opciones del filtro por responsable: quienes tienen algo pendiente, sin repetir y por nombre. */
export const opcionesResponsables = (filas) => {
    const porId = new Map();
    filas.forEach((f) => f.responsables.forEach((r) => porId.set(String(r.id), r.nombre)));

    return [...porId]
        .map(([valor, etiqueta]) => ({ valor, etiqueta }))
        .sort((a, b) => a.etiqueta.localeCompare(b.etiqueta, 'es'));
};

/**
 * Columna "Expediente" del seguimiento: "#12 · Fase SGR III — Factibilidad" o "#12".
 * La fase SGR solo se muestra en un expediente medible con plantilla de motor sgr.
 */
export const textoExpediente = (fila) => {
    if (fila.expedienteId === null) return '—';
    const medible = fila.estado === ESTADO.BORRADOR || fila.estado === ESTADO.RADICADO;
    return medible && fila.tieneSgr ? `#${fila.expedienteId} · Fase SGR ${faseLegible(fila.faseSgr)}` : `#${fila.expedienteId}`;
};

export const rutaExpediente = (fila) =>
    fila.expedienteId
        ? `/viabilizacion/${fila.proyectoId}?expediente=${fila.expedienteId}`
        : `/viabilizacion/${fila.proyectoId}`;

/** Promedio del porcentaje de requisitos cumplidos entre los proyectos que se pueden medir. */
export const avanceRequisitos = (proyectos, resumenes) => {
    const ids = new Set(proyectos.map((p) => p.id));
    const medidos = (resumenes ?? []).filter((r) => ids.has(r.proyecto_id) && r.configurado);
    const pct = medidos.length
        ? Math.round(medidos.reduce((suma, r) => suma + r.progreso.porcentaje, 0) / medidos.length)
        : null;

    return { pct, medidos: medidos.length, total: proyectos.length };
};

export const textoAvanceProyecto = (proyectoId, resumenes) => {
    if (!resumenes) return '—';

    const resumen = resumenes.find((r) => r.proyecto_id === proyectoId);
    if (!resumen) return 'Sin expediente';
    if (estadoDe(resumen) === ESTADO.SIN_CONFIGURAR) return 'Sin configurar';

    const { porcentaje, cumplidos, total } = resumen.progreso;
    const texto = `${porcentaje}% (${cumplidos} de ${total})`;

    return resumen.estado === ESTADO.RADICADO ? `${texto} · Radicado` : texto;
};
