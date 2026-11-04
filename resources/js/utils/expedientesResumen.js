// Lógica del seguimiento de expedientes en el Dashboard: une los proyectos con el
// resumen de GET /expedientes, que solo trae los proyectos que tienen expediente.

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

    return {
        proyectoId: proyecto.id,
        nombre: proyecto.nombre ?? '',
        municipio: proyecto.descripcion_municipio ?? '',
        faseProyecto: proyecto.descripcion_fase ?? '',
        estado,
        expedienteId: resumen?.expediente_id ?? null,
        cantidad: resumen?.cantidad_expedientes ?? 0,
        faseSgr: resumen?.fase_sgr ?? null,
        fechaRadicado: resumen?.fecha_radicado ?? null,
        actualizado: resumen?.actualizado ?? null,
        total,
        cumplidos,
        pendientes: total - cumplidos,
        porcentaje,
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

export const contarPorEstado = (filas) => {
    const cuantos = (estado) => filas.filter((f) => f.estado === estado).length;

    return {
        todos: filas.length,
        [ESTADO.SIN_CONFIGURAR]: cuantos(ESTADO.SIN_CONFIGURAR),
        [ESTADO.SIN_EXPEDIENTE]: cuantos(ESTADO.SIN_EXPEDIENTE),
        [ESTADO.BORRADOR]: cuantos(ESTADO.BORRADOR),
        [ESTADO.RADICADO]: cuantos(ESTADO.RADICADO),
        pendientesEnBorrador: filas
            .filter((f) => f.estado === ESTADO.BORRADOR)
            .reduce((suma, f) => suma + f.pendientes, 0),
    };
};

export const filtrarFilas = (filas, { estado, texto }) => {
    const buscado = normalizar(texto);

    return filas.filter(
        (f) =>
            (estado === 'todos' || f.estado === estado) &&
            (buscado === '' || normalizar(`${f.nombre} ${f.municipio}`).includes(buscado)),
    );
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
