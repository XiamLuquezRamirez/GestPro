// Lógica de presentación del expediente de contratación de un proceso (/contratacion/:procesoId):
// el checklist por etapa, el avance por etapa, los requisitos propios con su etapa, el aviso de
// pendientes al cerrar y la elección de plantillas para crearlo. La agrupación de viabilización
// (agruparChecklist) no cambia. Funciones puras, probadas en expedienteContratacion.test.js.

import { etiquetaAlerta, etiquetaRevision } from './avisosContratacion';
import { esIdContrato } from './contratosModulo';
import { opcionesEtapa } from './contratacion';
import { datosPropio, formularioPropio } from './expedienteChecklist';
import { compararEtapas, escaparHtml, mensajeApi, nombreEtapa, SIN_ETAPA } from './plantillasChecklist';

/** Si el `:procesoId` de la ruta es un id: la misma regla que el de un contrato (lo que acepta la API). */
export const esIdProceso = (valor) => esIdContrato(valor);

/** El error al cargar el proceso: un proceso que no existe no se reintenta. */
export const errorDeProceso = (error) => (error?.response?.status === 404
    ? { mensaje: 'El proceso no existe.', reintentable: false }
    : { mensaje: mensajeApi(error), reintentable: true });

export const TITULO_SIN_ETAPA = 'Sin etapa';

export const textoSinPlantillas = 'No hay plantillas de contratación publicadas que correspondan a la modalidad, la fuente y el sector de este proceso.';

const claveEtapa = (etapa) => (etapa ? `etapa-${etapa.id}` : SIN_ETAPA);
const tituloEtapa = (etapa) => (etapa ? nombreEtapa(etapa) : TITULO_SIN_ETAPA);

/** Lo que cuenta lo decide el backend: lo no aplicable y lo opcional no suman. */
const conteo = (filas) => ({
    total: filas.filter((f) => f.cuenta).length,
    cumplidos: filas.filter((f) => f.cuenta && f.estado === 'cumplido').length,
});

/**
 * Filas del checklist por etapa (`fila.etapa`, la del requisito o la del propio), en el orden del
 * catálogo (orden y, a igual orden, id, como el backend) y "Sin etapa" al final. Dentro de cada
 * etapa van en el orden en que llegan: el del backend, con los propios después de las plantillas.
 */
export const agruparPorEtapa = (filas) => {
    const grupos = new Map();
    (filas ?? []).forEach((f) => {
        const clave = claveEtapa(f.etapa);
        if (!grupos.has(clave)) grupos.set(clave, { clave, etapa: f.etapa ?? null, titulo: tituloEtapa(f.etapa), filas: [] });
        grupos.get(clave).filas.push(f);
    });
    const lista = [...grupos.values()];
    const conEtapa = lista.filter((g) => g.etapa).sort((a, b) => compararEtapas(a.etapa, b.etapa));
    const sinEtapa = lista.filter((g) => !g.etapa);
    return [...conEtapa, ...sinEtapa].map((g) => ({ ...g, ...conteo(g.filas) }));
};

export const NOTA_PONDERADO = 'El porcentaje pondera cada requisito por su peso.';

/**
 * Si el porcentaje del expediente está ponderado de verdad: algún requisito que cuenta pesa
 * distinto de 1. Con todos en 1 es el mismo que el conteo, y en viabilización las filas no traen peso.
 */
export const pondera = (filas) => (filas ?? []).some((f) => f.cuenta && f.peso !== undefined && f.peso !== null && Number(f.peso) !== 1);

/** El avance por etapa de la API (`avance_por_etapa`, ya en el orden del catálogo), para sus barras. */
export const avanceEtapas = (avance) => (avance ?? []).map((a) => ({
    clave: claveEtapa(a.etapa),
    titulo: tituloEtapa(a.etapa),
    total: a.total,
    cumplidos: a.cumplidos,
    porcentaje: a.porcentaje,
}));

// ---------- Revisión por etapa (fase 5) ----------

const APROBADAS = ['aprobada', 'aprobada_con_cambios'];

/** Si la etapa está aprobada (con o sin cambios después). */
const estaAprobada = (avance) => APROBADAS.includes(avance?.revision?.estado);

const momento = (iso) => {
    const ms = iso ? Date.parse(iso) : NaN;
    return Number.isNaN(ms) ? null : ms;
};

// Si la etapa que llega trae una revisión más vieja que la de pantalla (o ninguna): la petición
// salió antes de aprobarla o devolverla.
const revisionAtrasada = (previa, nueva) => {
    const antes = momento(previa?.revision?.fecha);
    if (antes === null) return false;
    const ahora = momento(nueva?.revision?.fecha);
    return ahora === null || ahora < antes;
};

/** Si la respuesta de una acción del checklist salió antes de una aprobación o devolución ya pintada. */
export const hayRevisionAtrasada = (actual, entrante) => {
    const previas = new Map((actual ?? []).filter((a) => a.etapa).map((a) => [a.etapa.id, a]));
    return (entrante ?? []).some((n) => n.etapa && revisionAtrasada(previas.get(n.etapa.id), n));
};

/**
 * `aviso_etapa_anterior` con la regla de la API: alguna etapa anterior (en el orden en que llegan,
 * el del catálogo) con requisitos que cuentan no está aprobada, y esta tiene algo cumplido. Hace
 * falta al aprobar o devolver una etapa: la respuesta trae solo esa, y el aviso de las siguientes
 * cambia. Solo toca las etapas que ya traen la clave.
 */
export const recalcularAvisoAnterior = (avance) => {
    let anteriorSinAprobar = false;
    return (avance ?? []).map((a) => {
        if (!a.etapa || !('aviso_etapa_anterior' in a)) return a;
        const aviso = anteriorSinAprobar && Number(a.cumplidos) > 0;
        if (Number(a.total) > 0 && !estaAprobada(a)) anteriorSinAprobar = true;
        return a.aviso_etapa_anterior === aviso ? a : { ...a, aviso_etapa_anterior: aviso };
    });
};

/** Los ids de las etapas cuya etapa anterior aún no está aprobada (para su sección del checklist). */
export const etapasConAvisoAnterior = (avance) => new Set(
    (avance ?? []).filter((a) => a.etapa && a.aviso_etapa_anterior).map((a) => a.etapa.id)
);

/**
 * El avance por etapa que llega de una acción del checklist, sin reponer fechas viejas: esa
 * petición pudo salir antes de que se guardara una fecha. En las etapas `protegidas` (ids cuya
 * fecha se guardó en esta pantalla) se conservan las fechas y el semáforo actuales; solo si la
 * etapa se completó o dejó de estarlo se toma la fecha real que llega (la sugerida o su borrado).
 * El avance (conteos y porcentajes) siempre es el que llega.
 *
 * Lo mismo con la revisión: si la petición salió antes de aprobar o devolver una etapa (su
 * revisión es más vieja que la de pantalla), se conservan la revisión, el semáforo y la fecha real
 * de esa etapa (la aprobación pudo poner la fecha real) y se recalcula el aviso de la etapa
 * anterior. Con la misma revisión o una más nueva vale la que llega: puede traer "aprobada con
 * cambios" o la devuelta que se volvió a trabajar.
 */
export const fusionarAvance = (actual, entrante, protegidas) => {
    if (!entrante) return actual;
    const conProtegidas = Boolean(protegidas && protegidas.size > 0);
    const atrasada = hayRevisionAtrasada(actual, entrante);
    if (!conProtegidas && !atrasada) return entrante;
    const previas = new Map((actual ?? []).filter((a) => a.etapa).map((a) => [a.etapa.id, a]));
    const protegerFechas = (nueva, previa) => {
        if (!conProtegidas || !protegidas.has(nueva.etapa.id)) return nueva;
        if (Boolean(previa.completa) !== Boolean(nueva.completa)) return { ...nueva, fecha_programada: previa.fecha_programada };
        return {
            ...nueva,
            fecha_programada: previa.fecha_programada,
            fecha_real: previa.fecha_real,
            fecha_real_sugerida: previa.fecha_real_sugerida,
            alerta: previa.alerta,
        };
    };
    const fusionado = entrante.map((nueva) => {
        const previa = nueva.etapa ? previas.get(nueva.etapa.id) : null;
        if (!previa) return nueva;
        const conFechas = protegerFechas(nueva, previa);
        if (!revisionAtrasada(previa, nueva)) return conFechas;
        return {
            ...conFechas,
            revision: previa.revision,
            alerta: previa.alerta,
            fecha_real: previa.fecha_real,
            fecha_real_sugerida: previa.fecha_real_sugerida,
        };
    });
    return atrasada ? recalcularAvisoAnterior(fusionado) : fusionado;
};

/**
 * El expediente con el progreso y el avance por etapa que trae una respuesta parcial, si los trae.
 * Si la respuesta salió antes de una aprobación o devolución ya pintada, sus avisos (por revisar,
 * devueltas, hitos) son viejos: quedan los de pantalla.
 */
export const conAvance = (expediente, datos, protegidas) => ({
    ...expediente,
    progreso: datos?.progreso ?? expediente.progreso,
    avance_por_etapa: fusionarAvance(expediente.avance_por_etapa, datos?.avance_por_etapa, protegidas),
    avisos: (hayRevisionAtrasada(expediente.avance_por_etapa, datos?.avance_por_etapa) ? null : datos?.avisos) ?? expediente.avisos,
});

// ---------- Cronograma (fechas por etapa) ----------

export const CAMPOS_FECHA_ETAPA = ['fecha_programada', 'fecha_real'];

/**
 * Las fechas de una etapa de `avance_por_etapa` como valores del input date ('' sin fecha), si
 * la real la sugirió el sistema y la etiqueta de su semáforo. El grupo sin etapa no tiene fechas.
 */
export const fechasEtapa = (avance) => {
    const real = avance?.fecha_real ?? '';
    return {
        etapaId: avance?.etapa?.id ?? null,
        conFechas: Boolean(avance?.etapa),
        programada: avance?.fecha_programada ?? '',
        real,
        sugerida: Boolean(real && avance?.fecha_real_sugerida),
        alerta: etiquetaAlerta(avance?.alerta),
    };
};

const plural = (n, singular, varios) => (n === 1 ? `${n} ${singular}` : `${n} ${varios}`);

/** Un momento ISO (UTC) como día/mes/año en la zona de contratación; null si no hay o no es válido. */
export const fechaMomento = (iso) => {
    const ms = momento(iso);
    return ms === null ? null : fechaLegible(fechaHoyBogota(new Date(ms)));
};

const quienCuando = (accion, revision) => {
    const cuando = fechaMomento(revision.fecha);
    return `${accion}${revision.usuario ? ` por ${revision.usuario}` : ''}${cuando ? ` el ${cuando}` : ''}`;
};

/**
 * La revisión de una etapa para mostrarla (`revision` de `avance_por_etapa`): la etiqueta de su
 * estado, quién y cuándo la aprobó o la devolvió, su observación y una nota si cambió después.
 * Una devuelta que se volvió a trabajar se ve pendiente o lista, pero conserva la devolución.
 * Null en el grupo sin etapa o si la respuesta no trae la revisión.
 */
export const revisionEtapa = (avance) => {
    const revision = avance?.etapa ? avance.revision : null;
    if (!revision) return null;
    const { estado } = revision;
    let detalle = null;
    let nota = null;
    if (APROBADAS.includes(estado)) {
        const n = Number(revision.pendientes_al_aprobar) || 0;
        detalle = `${quienCuando('Aprobada', revision)}${n > 0 ? ` con ${plural(n, 'requisito pendiente', 'requisitos pendientes')}` : ''}`;
        if (estado === 'aprobada_con_cambios') nota = 'Algún requisito de la etapa cambió después de aprobarla.';
    } else if (revision.devuelta_at) {
        detalle = quienCuando('Devuelta', revision);
        if (estado !== 'devuelta') nota = 'Tuvo cambios después de la devolución.';
    }
    return { estado, etiqueta: etiquetaRevision(estado), detalle, observacion: revision.observacion || null, nota };
};

/**
 * El avance por etapa para sus barras (avanceEtapas) con las fechas y el semáforo de cada una, su
 * revisión, cuántos requisitos que cuentan le faltan y si su etapa anterior aún no está aprobada.
 */
export const etapasConFechas = (avance) => {
    const lista = avance ?? [];
    return avanceEtapas(lista).map((e, i) => ({
        ...e,
        ...fechasEtapa(lista[i]),
        revision: revisionEtapa(lista[i]),
        faltan: Math.max(0, (Number(e.total) || 0) - (Number(e.cumplidos) || 0)),
        avisoEtapaAnterior: Boolean(lista[i]?.etapa && lista[i]?.aviso_etapa_anterior),
    }));
};

/**
 * Los botones de revisión de una etapa (de etapasConFechas): solo para quien edita un expediente
 * en borrador, en una etapa del catálogo con revisión. "Aprobar etapa" si no está aprobada,
 * "Aprobar de nuevo" si está aprobada con cambios (reaprueba sin devolverla) y "Devolver" siempre;
 * una etapa sin requisitos que cuenten no tiene botones.
 */
export const accionesRevision = (etapa, editable) => {
    const estado = etapa?.revision?.estado;
    if (!editable || !estado || estado === 'sin_requisitos') return { aprobar: false, reaprobar: false, devolver: false };
    return { aprobar: !APROBADAS.includes(estado), reaprobar: estado === 'aprobada_con_cambios', devolver: true };
};

/** El diálogo de aprobar una etapa; si le faltan requisitos, dice cuántos (solo avisa). */
export const dialogoAprobarEtapa = (titulo, faltan, otraVez = false) => {
    const n = Number(faltan) || 0;
    if (n <= 0) {
        return {
            icono: 'question',
            titulo: otraVez ? `¿Aprobar de nuevo la etapa «${titulo}»?` : `¿Aprobar la etapa «${titulo}»?`,
            texto: 'La aprobación queda en el historial del expediente con tu nombre y la fecha.',
            boton: otraVez ? 'Aprobar de nuevo' : 'Aprobar etapa',
        };
    }
    return {
        icono: 'warning',
        titulo: otraVez ? `¿Aprobar de nuevo la etapa «${titulo}» con requisitos pendientes?` : `¿Aprobar la etapa «${titulo}» con requisitos pendientes?`,
        texto: `${n === 1 ? 'Falta' : 'Faltan'} ${plural(n, 'requisito', 'requisitos')} de esta etapa. Puedes aprobarla de todos modos: la revisión solo avisa.`,
        boton: 'Aprobar de todos modos',
    };
};

/**
 * El cuerpo del POST de aprobar: la observación sin espacios (o null) y, si se confirmó, `confirmar`
 * con `pendientes_vistos` (los que mostró la confirmación, para que el servidor avise si hay más).
 */
export const datosAprobarEtapa = (observacion, confirmar, pendientesVistos) => ({
    observacion: String(observacion ?? '').trim() || null,
    ...(confirmar ? { confirmar: true } : {}),
    ...(confirmar && Number.isInteger(pendientesVistos) && pendientesVistos >= 0 ? { pendientes_vistos: pendientesVistos } : {}),
});

/** Cuántos requisitos faltan si el servidor pide confirmar la aprobación (422 con `pendientes`); null si es otro error. */
export const pendientesAlAprobar = (error) => {
    const pendientes = error?.response?.status === 422 ? error.response.data?.pendientes : null;
    return typeof pendientes === 'number' ? pendientes : null;
};

/**
 * Aprueba una etapa con la confirmación de pendientes. `enviar(confirmar, vistos)` hace el POST y
 * devuelve la respuesta; `confirmar(n)` pregunta si se aprueba con n pendientes (true/false). Con
 * pendientes en pantalla va directo con `confirmar` y los vistos; si el servidor responde el 422 de
 * confirmación (hay pendientes que la pantalla no veía, o más que los vistos), pregunta con su
 * número y reintenta, tantas veces como haga falta. Otros errores se propagan.
 * Devuelve { aprobada, data }; `aprobada` es false si no se confirmó.
 */
export const aprobarConConfirmacion = async ({ faltan, enviar, confirmar }) => {
    let vistos = Math.max(0, Number(faltan) || 0);
    let confirmado = vistos > 0;
    for (;;) {
        try {
            return { aprobada: true, data: await enviar(confirmado, vistos) };
        } catch (e) {
            const n = pendientesAlAprobar(e);
            if (n === null) throw e;
            if (!(await confirmar(n))) return { aprobada: false, data: null };
            vistos = n;
            confirmado = true;
        }
    }
};

/**
 * Si el valor del input date se puede guardar: vacío (borra la fecha) o una fecha año-mes-día
 * real con año desde 1900. Mientras se escribe el año, el input da valores como 0002-11-25.
 */
export const fechaCompleta = (valor) => {
    if (valor === '') return true;
    const partes = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(valor ?? ''));
    if (!partes) return false;
    const [anio, mes, dia] = partes.slice(1).map(Number);
    if (anio < 1900) return false;
    const fecha = new Date(Date.UTC(anio, mes - 1, dia));
    return fecha.getUTCFullYear() === anio && fecha.getUTCMonth() === mes - 1 && fecha.getUTCDate() === dia;
};

/** El cuerpo del PUT de la etapa: solo el campo que cambió; vacío lo borra (null). */
export const datosFechaEtapa = (campo, valor) => (CAMPOS_FECHA_ETAPA.includes(campo) ? { [campo]: valor || null } : {});

/**
 * El expediente con la etapa que respondió el PUT de fechas, aprobar o devolver (`etapa`) en su
 * lugar y los `avisos` nuevos. El aviso de la etapa anterior se recalcula en las demás: aprobar o
 * devolver una etapa lo cambia en las siguientes.
 */
export const conEtapaActualizada = (expediente, datos) => {
    if (!expediente || !datos) return expediente;
    const id = datos.etapa?.etapa?.id;
    return {
        ...expediente,
        avance_por_etapa: recalcularAvisoAnterior((expediente.avance_por_etapa ?? []).map((a) => (id && a.etapa?.id === id ? datos.etapa : a))),
        avisos: datos.avisos ?? expediente.avisos,
    };
};

/** El error que va bajo el campo de fecha: el de ese campo si el 422 lo trae; si no, el de la API. */
export const errorFechaEtapa = (error, campo) => {
    const errores = error?.response?.status === 422 ? error.response.data?.errors : null;
    const propio = errores && [].concat(errores[campo] ?? [])[0];
    return propio ? String(propio) : mensajeApi(error);
};

const ZONA_CONTRATACION = 'America/Bogota';

/** El día de hoy en la zona de contratación (Bogotá) como año-mes-día, no el del equipo ni el de UTC: el `max` de la fecha real. */
export const fechaHoyBogota = (ahora = new Date()) => new Intl.DateTimeFormat('en-CA', {
    timeZone: ZONA_CONTRATACION, year: 'numeric', month: '2-digit', day: '2-digit',
}).format(ahora);

/** Una fecha año-mes-día como día/mes/año (sin pasar por Date: no cambia con la zona); "Sin fecha" si no hay. */
export const fechaLegible = (iso) => {
    const partes = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso ?? ''));
    return partes ? `${partes[3]}/${partes[2]}/${partes[1]}` : 'Sin fecha';
};

/** Qué hacer con el borrador de una fecha: 'nada' (ya guardado), 'incompleta' (a medio escribir) o 'guardar'. */
export const fechaPorGuardar = (borrador, guardado) => {
    if (borrador === guardado) return 'nada';
    return fechaCompleta(borrador) ? 'guardar' : 'incompleta';
};

/** Etapas que se ofrecen a un propio: las activas y la que ya tiene (aunque esté inactiva). */
export const opcionesEtapaPropio = (etapas, fila) => opcionesEtapa(etapas, fila?.etapa ?? null);

/** El formulario de un propio de contratación: el de siempre más su etapa (como texto, la del select). */
export const formularioPropioContratacion = (fila) => ({
    ...formularioPropio(fila),
    etapa_propia_id: fila?.etapa?.id ? String(fila.etapa.id) : '',
});

/** Lo que espera la API: lo de siempre más `etapa_propia_id`, obligatoria en contratación. */
export const datosPropioContratacion = (formulario) => ({
    ...datosPropio(formulario),
    etapa_propia_id: Number(formulario.etapa_propia_id) || null,
});

/** Un propio de contratación se guarda con título y etapa. */
export const propioCompleto = (formulario) => Boolean(formulario.titulo.trim()) && Boolean(formulario.etapa_propia_id);

const etapasSinAprobar = (pendientes) => (Array.isArray(pendientes?.etapas_sin_aprobar) ? pendientes.etapas_sin_aprobar : []);

/**
 * Los pendientes con que el servidor rechazó cerrar sin confirmar (`pendientes` del 422):
 * "Faltan 4 requisitos por cumplir" y una línea por etapa; y, si hay etapas sin aprobar
 * (`etapas_sin_aprobar`), "Hay 2 etapas sin aprobar" con el estado de cada una en `sinAprobar`.
 * Sin requisitos pendientes, `titulo` es null. Null si no hay nada.
 */
export const textoPendientesCierre = (pendientes) => {
    const total = Number(pendientes?.total) || 0;
    const lista = etapasSinAprobar(pendientes);
    if (total === 0 && lista.length === 0) return null;
    const sinAprobar = lista.length > 0 ? {
        titulo: `Hay ${plural(lista.length, 'etapa', 'etapas')} sin aprobar`,
        etapas: lista.map((e) => `${tituloEtapa(e.etapa)}: ${(etiquetaRevision(e.estado)?.texto ?? 'Sin aprobar').toLowerCase()}`),
    } : null;
    return {
        titulo: total > 0 ? `${total === 1 ? 'Falta' : 'Faltan'} ${plural(total, 'requisito', 'requisitos')} por cumplir` : null,
        etapas: total > 0 ? (pendientes.por_etapa ?? []).map((e) => `${tituloEtapa(e.etapa)}: ${plural(e.pendientes, 'pendiente', 'pendientes')}`) : [],
        ...(sinAprobar ? { sinAprobar } : {}),
    };
};

const htmlLista = (titulo, lineas) => {
    const items = lineas.map((e) => `<li>${escaparHtml(e)}</li>`).join('');
    return `<p>${escaparHtml(titulo)}.</p>${items ? `<ul style="text-align:left">${items}</ul>` : ''}`;
};

/** El cuerpo del aviso antes de confirmar el cierre, con los nombres escapados. */
export const htmlPendientesCierre = (pendientes) => {
    const texto = textoPendientesCierre(pendientes);
    if (!texto) return '';
    return (texto.titulo ? htmlLista(texto.titulo, texto.etapas) : '')
        + (texto.sinAprobar ? htmlLista(texto.sinAprobar.titulo, texto.sinAprobar.etapas) : '')
        + '<p>Puedes cerrarlo de todos modos: la revisión solo avisa. Cerrado, el checklist queda congelado.</p>';
};

/** El título del diálogo de cierre con pendientes, según falten requisitos, aprobaciones o ambos. */
export const tituloCierrePendientes = (pendientes) => {
    const requisitos = (Number(pendientes?.total) || 0) > 0;
    const etapas = etapasSinAprobar(pendientes).length > 0;
    if (requisitos && etapas) return '¿Cerrar con pendientes?';
    return etapas ? '¿Cerrar con etapas sin aprobar?' : '¿Cerrar con requisitos pendientes?';
};

/**
 * Si el cierre no tiene nada que avisar: todo cumplido y todas las etapas con requisitos que
 * cuentan aprobadas. Entonces la pantalla confirma el congelado antes de enviar; si no, el
 * servidor responde con los pendientes y se confirma con ellos.
 */
export const cierreSinAvisos = (expediente) => {
    const { cumplidos = 0, total = 0 } = expediente?.progreso ?? {};
    if (cumplidos < total) return false;
    return (expediente?.avance_por_etapa ?? []).every((a) => !a.etapa || !a.revision || Number(a.total) === 0 || estaAprobada(a));
};

const fecha = (iso) => (iso ? new Date(iso).toLocaleDateString('es-CO') : null);

/** "Borrador", o "Cerrado el 18/11/2026 por Ana" (con lo que se sepa). */
export const textoEstadoExpediente = (expediente) => {
    if (expediente?.estado !== 'cerrado') return 'Borrador';
    const cuando = fecha(expediente.fecha_cierre);
    return `Cerrado${cuando ? ` el ${cuando}` : ''}${expediente.usuario_cierre ? ` por ${expediente.usuario_cierre}` : ''}`;
};

/** Plantillas elegidas al abrir: si solo hay una compatible, ya va elegida. */
export const seleccionInicial = (plantillas) => (plantillas?.length === 1 ? [plantillas[0].id] : []);

/** Marca o desmarca una plantilla conservando el orden en que se eligen: la primera es la principal. */
export const alternarPlantillaNueva = (seleccion, id) => (
    seleccion.includes(id) ? seleccion.filter((x) => x !== id) : [...seleccion, id]
);

// Por qué una plantilla del expediente ya no le sirve al proceso: título con una, con varias, y
// la razón corta de cada una cuando las razones son distintas.
const RAZONES = {
    no_corresponde: [
        'Una plantilla ya no corresponde a la modalidad, la fuente o el sector del proceso',
        'Algunas plantillas ya no corresponden a la modalidad, la fuente o el sector del proceso',
        'ya no corresponde a la modalidad, la fuente o el sector',
    ],
    inactiva: ['Una plantilla del expediente está inactiva', 'Algunas plantillas del expediente están inactivas', 'inactiva'],
    sin_publicada: ['Una plantilla ya no tiene una versión publicada', 'Algunas plantillas ya no tienen una versión publicada', 'sin versión publicada'],
};

// Primero la cobertura (`corresponde`, que no mira si está activa o publicada): es lo que cambia
// al editar el proceso o el proyecto. Sin los campos de la razón, se entiende que no corresponde.
const razonPlantilla = (p) => {
    if (p.corresponde === false) return 'no_corresponde';
    if (p.activa === false) return 'inactiva';
    if (p.publicada === false) return 'sin_publicada';
    return 'no_corresponde';
};

/**
 * Aviso de las plantillas del expediente que ya no le sirven al proceso (cambió su modalidad o su
 * fuente, o el sector del proyecto; o la plantilla está inactiva o sin versión publicada), con la
 * razón; null si no hay. El expediente no cambia: solo se avisa.
 */
export const avisoIncompatiblesProceso = (plantillas) => {
    const incompatibles = (Array.isArray(plantillas) ? plantillas : [])
        .filter((p) => p && !p.compatible)
        .map((p) => ({ nombre: p.nombre, razon: razonPlantilla(p) }));
    if (incompatibles.length === 0) return null;
    const una = incompatibles.length === 1;
    const conserva = una ? 'El expediente la conserva tal como está.' : 'El expediente las conserva tal como están.';
    const razones = [...new Set(incompatibles.map((p) => p.razon))];
    if (razones.length === 1) {
        return { titulo: RAZONES[razones[0]][una ? 0 : 1], detalle: `${incompatibles.map((p) => p.nombre).join(', ')}. ${conserva}` };
    }
    return {
        titulo: 'Algunas plantillas ya no sirven al proceso',
        detalle: `${incompatibles.map((p) => `${p.nombre} (${RAZONES[p.razon][2]})`).join(', ')}. ${conserva}`,
    };
};
