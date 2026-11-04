// Lógica de la pestaña "Expedientes SGR" del Dashboard: une los proyectos con el
// resumen de GET /expedientes, que solo trae los proyectos que tienen expediente.

export const ESTADO = {
    SIN_EXPEDIENTE: 'sin_expediente',
    BORRADOR: 'borrador',
    RADICADO: 'radicado',
};

// Primero lo que requiere trabajo; lo radicado ya está cerrado.
const PRIORIDAD = {
    [ESTADO.BORRADOR]: 0,
    [ESTADO.SIN_EXPEDIENTE]: 1,
    [ESTADO.RADICADO]: 2,
};

const normalizar = (texto) =>
    String(texto ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim();

const construirFila = (proyecto, resumen) => {
    const { total = 0, cumplidos = 0, porcentaje = 0 } = resumen?.progreso ?? {};

    return {
        proyectoId: proyecto.id,
        nombre: proyecto.nombre ?? '',
        municipio: proyecto.descripcion_municipio ?? '',
        faseProyecto: proyecto.descripcion_fase ?? '',
        estado: resumen?.estado ?? ESTADO.SIN_EXPEDIENTE,
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
