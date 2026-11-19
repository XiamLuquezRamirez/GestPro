// Vista "Contratación" del tablero (GET /tablero/contratacion): los filtros (en la URL), las
// tarjetas por semáforo y por estado, los procesos por etapa actual y las filas de la tabla. Todo
// sale de lo que calcula el backend (semáforo, etapa actual, avisos): aquí no se mira la fecha de
// hoy ni se suma ni promedia ningún avance (R13). Funciones puras, probadas en contratacionPanel.test.js.

import { etiquetaRevision } from './avisosContratacion';
import { ESTADOS_PROCESO, PLATAFORMAS, etiquetaEstadoProceso, etiquetaPlataforma, numeroProceso, textoFecha, textoValor, tonoEstado } from './contratacion';
import { rutaExpedienteContratacion, rutaFichaProyecto } from './navegacion';

// Los valores son los del backend (TableroContratacion), en su orden de prioridad.
export const SEMAFOROS = [
    ['vencida', 'Vencida', 'coral'],
    ['proxima', 'Próxima', 'ambar'],
    ['al_dia', 'Al día', 'verde'],
    ['sin_expediente', 'Sin expediente', 'neutro'],
    ['terminado', 'Terminado', 'neutro'],
];

/** La etiqueta y el tono (`ct-estado-<tono>`) del semáforo de un proceso; null si no se conoce. */
export const etiquetaSemaforo = (valor) => {
    const encontrado = SEMAFOROS.find(([v]) => v === valor);
    return encontrado ? { valor: encontrado[0], texto: encontrado[1], tono: encontrado[2] } : null;
};

// ---------- Filtros ----------

export const FILTROS_VACIOS = {
    modalidad_id: '',
    plataforma: '',
    fuente_id: '',
    responsable_id: '',
    estado: '',
    semaforo: '',
};

const CLAVES_FILTRO = Object.keys(FILTROS_VACIOS);
const esId = (valor) => /^[1-9]\d*$/.test(valor);
const VALIDOS = {
    modalidad_id: esId,
    plataforma: (v) => PLATAFORMAS.some(([p]) => p === v),
    fuente_id: esId,
    responsable_id: esId,
    estado: (v) => ESTADOS_PROCESO.some(([e]) => e === v),
    semaforo: (v) => SEMAFOROS.some(([s]) => s === v),
};

/**
 * Los filtros de la URL (`?vista=contratacion&estado=publicado…`). Un valor que la API rechazaría
 * (un id que no es número, un estado que no existe) se descarta: así una URL vieja o mal escrita
 * no deja el tablero en error.
 */
export const filtrosDeConsulta = (consulta) => Object.fromEntries(CLAVES_FILTRO.map((clave) => {
    const valor = String(consulta?.get?.(clave) ?? '').trim();
    return [clave, VALIDOS[clave](valor) ? valor : ''];
}));

/** Los parámetros de GET /tablero/contratacion: solo los filtros con valor. */
export const parametrosApi = (filtros) => Object.fromEntries(CLAVES_FILTRO.filter((c) => filtros?.[c]).map((c) => [c, filtros[c]]));

export const hayFiltros = (filtros) => Object.keys(parametrosApi(filtros)).length > 0;

/** La consulta de la URL con estos filtros: conserva lo demás (la vista) y quita los vacíos. */
export const consultaConFiltros = (previos, filtros) => {
    const siguientes = new URLSearchParams(previos);
    CLAVES_FILTRO.forEach((clave) => {
        if (filtros?.[clave]) siguientes.set(clave, filtros[clave]);
        else siguientes.delete(clave);
    });
    return siguientes;
};

/** El filtro de una tarjeta: elegirla filtra por su valor; elegir la activa lo quita. */
export const alternarFiltro = (filtros, clave, valor) => ({
    ...filtros,
    [clave]: !valor || filtros[clave] === valor ? '' : valor,
});

const OPCIONES_DE = {
    modalidad_id: (o) => (o?.modalidades ?? []).map((m) => String(m.id)),
    plataforma: (o) => o?.plataformas ?? [],
    fuente_id: (o) => (o?.fuentes ?? []).map((f) => String(f.id)),
    responsable_id: (o) => (o?.responsables ?? []).map((r) => String(r.id)),
};

/**
 * Los filtros cuyo valor ya no tiene ningún proceso (las opciones del servidor no dependen de los
 * filtros): hay que quitarlos para no dejar un filtro oculto que vacía la tabla. Sin opciones
 * (aún no cargan), ninguno.
 */
export const filtrosFueraDeOpciones = (filtros, opciones) => {
    if (!opciones) return [];
    return Object.keys(OPCIONES_DE).filter((clave) => filtros?.[clave] && !OPCIONES_DE[clave](opciones).includes(filtros[clave]));
};

const deCatalogo = (items) => (items ?? []).map((i) => ({ valor: String(i.id), etiqueta: i.nombre }));

/** Las opciones de cada select de filtro: las que da el servidor y, para estado y semáforo, todas. */
export const opcionesFiltros = (opciones) => ({
    modalidad_id: deCatalogo(opciones?.modalidades),
    plataforma: (opciones?.plataformas ?? []).map((p) => ({ valor: p, etiqueta: etiquetaPlataforma(p) })),
    fuente_id: deCatalogo(opciones?.fuentes),
    responsable_id: deCatalogo(opciones?.responsables),
    estado: ESTADOS_PROCESO.map(([valor, etiqueta]) => ({ valor, etiqueta })),
    semaforo: SEMAFOROS.map(([valor, etiqueta]) => ({ valor, etiqueta })),
});

// ---------- Resumen ----------

const cuenta = (conteos, clave) => Number(conteos?.[clave]) || 0;

/** Las cinco tarjetas del semáforo, con lo que cuenta el servidor (ya filtrado). */
export const tarjetasSemaforo = (resumen, activo) => SEMAFOROS.map(([valor, etiqueta, tono]) => ({
    valor,
    etiqueta,
    tono,
    cantidad: cuenta(resumen?.por_semaforo, valor),
    activa: activo === valor,
}));

/** Las tarjetas por estado del proceso: las que tienen procesos y, aunque quede en cero, la filtrada. */
export const tarjetasEstado = (resumen, activo) => ESTADOS_PROCESO
    .map(([valor, etiqueta]) => ({ valor, etiqueta, tono: tonoEstado(valor), cantidad: cuenta(resumen?.por_estado, valor), activa: activo === valor }))
    .filter((t) => t.cantidad > 0 || t.activa);

/**
 * Cuántos procesos tiene cada etapa actual (la primera sin aprobar), en el orden del catálogo, y
 * qué parte del total son (para el ancho de la barra: es una proporción de procesos, no un avance).
 * `sinEtapa`: los que no tienen etapa actual (sin expediente, cerrado o con todo aprobado).
 */
export const etapasActuales = (resumen) => {
    const total = Number(resumen?.total) || 0;
    const etapas = (resumen?.por_etapa ?? []).map((e) => ({
        id: e.id,
        nombre: e.nombre,
        orden: e.orden,
        procesos: Number(e.procesos) || 0,
        porcentaje: total ? Math.round(((Number(e.procesos) || 0) / total) * 100) : 0,
    }));
    const enEtapa = etapas.reduce((suma, e) => suma + e.procesos, 0);
    return { etapas, sinEtapa: Math.max(0, total - enEtapa) };
};

// ---------- Tabla ----------

// La etapa actual sin revisión que anunciar: "pendiente" es lo normal de la etapa en curso.
const revisionVisible = (estado) => (estado && estado !== 'pendiente' ? etiquetaRevision(estado) : null);

const textoSinEtapa = (proceso) => {
    if (!proceso.expediente) return 'Sin expediente';
    if (proceso.expediente.estado === 'cerrado') return 'Expediente cerrado';
    // Sin requisitos que cuenten no hay etapas que aprobar: "Todas aprobadas" sería engañoso.
    if (!(Number(proceso.expediente.progreso?.total) > 0)) return 'Sin requisitos';
    return 'Todas aprobadas';
};

const etapaDe = (proceso) => (proceso.etapa_actual
    ? { texto: proceso.etapa_actual.nombre, revision: revisionVisible(proceso.etapa_actual.estado_revision) }
    : { texto: textoSinEtapa(proceso), revision: null });

const textoODefecto = (valor, siVacio) => String(valor ?? '').trim() || siVacio;

/** Un proceso de GET /tablero/contratacion tal como lo muestra la tabla. */
export const filaProceso = (proceso) => ({
    id: proceso.id,
    numero: numeroProceso(proceso),
    objeto: textoODefecto(proceso.objeto, 'Sin objeto'),
    proyecto: proceso.proyecto
        ? { id: proceso.proyecto.id, nombre: textoODefecto(proceso.proyecto.nombre, `Proyecto ${proceso.proyecto.id}`), ruta: rutaFichaProyecto(proceso.proyecto.id) }
        : null,
    modalidad: proceso.modalidad?.nombre ?? '—',
    monto: textoValor(proceso.monto),
    estado: { texto: etiquetaEstadoProceso(proceso.estado), tono: tonoEstado(proceso.estado) },
    etapa: etapaDe(proceso),
    avance: proceso.expediente ? Number(proceso.expediente.progreso?.porcentaje) || 0 : null,
    cierre: textoFecha(proceso.fecha_cierre),
    semaforo: etiquetaSemaforo(proceso.semaforo),
    avisos: proceso.avisos ?? null,
    rutaChecklist: rutaExpedienteContratacion(proceso.id),
});

export const textoListaVacia = (conFiltros) => (conFiltros
    ? 'Ningún proceso coincide con los filtros elegidos.'
    : 'Aún no hay procesos de contratación registrados.');
