// Plantillas de un expediente: cuáles se ofrecen, cómo se eligen y qué se envía.
// Funciones puras, probadas en plantillasExpediente.test.js. Las reglas las valida el
// backend (PlantillasExpediente::validar); aquí solo se evita pedir lo que va a rechazar.

import { MOTOR_SGR } from './expedienteChecklist';
import { textoAsociaciones } from './plantillasChecklist';

export const AVISO_SIN_SECTOR = 'El proyecto no tiene sector: solo se ofrecen las plantillas para todos los sectores';

const MOTIVO_NO_OFRECIDA = 'Ya no se ofrece a este proyecto: solo la conserva un expediente que ya la tenía.';
const MOTIVO_OTRA_SGR = 'El expediente ya tiene una plantilla con motor SGR: solo admite una.';
const MOTIVO_ULTIMA = 'Un expediente configurado necesita al menos una plantilla.';

/** Igual que el backend (PlantillasExpediente::MAX_PLANTILLAS). */
export const MAX_PLANTILLAS = 20;
const MOTIVO_MAXIMO = `Un expediente admite como máximo ${MAX_PLANTILLAS} plantillas.`;

/** Id de una plantilla de GET plantillas-compatibles (`id`) o de las de un expediente (`plantilla_id`). */
const idDe = (plantilla) => plantilla.id ?? plantilla.plantilla_id;

const buscar = (lista, id) => (lista ?? []).find((p) => idDe(p) === id);

const esSgr = (plantilla) => plantilla?.motor === MOTOR_SGR;

/** Hoy se puede elegir para un expediente nuevo: compatible (activa y con vigente) y con versión. */
const seOfrece = (plantilla) => Boolean(plantilla?.compatible && plantilla.activa !== false && plantilla.version);

/** Si alguna de las plantillas elegidas tiene motor sgr. Sirve la lista de disponibles o la del expediente. */
export const incluyeSgr = (plantillaIds, plantillas) => (plantillaIds ?? []).some((id) => esSgr(buscar(plantillas, id)));

/**
 * Si la selección en curso incluye una plantilla sgr. Se busca en la lista de disponibles y en
 * las del expediente: si la lista no cargó, igual se sabe.
 */
export const seleccionConSgr = (seleccion, disponibles, plantillasExpediente) =>
    incluyeSgr(seleccion?.plantilla_ids, [...(disponibles?.plantillas ?? []), ...(plantillasExpediente ?? [])]);

/**
 * Si la pantalla necesita el catálogo SGR (sectores, tipos, componentes y tipologías): solo
 * cuando el expediente abierto tiene una plantilla sgr o la selección en curso incluye una.
 */
export const necesitaCatalogoSgr = (expediente, seleccion, disponibles) =>
    Boolean(expediente) && (Boolean(expediente.tiene_sgr) || seleccionConSgr(seleccion, disponibles, expediente.plantillas));

/** Si entre las disponibles hay una plantilla sgr que hoy se ofrece al proyecto. */
export const hayCompatibleSgr = (disponibles) => (disponibles ?? []).some((p) => esSgr(p) && seOfrece(p));

/**
 * Texto que se muestra antes de crear el expediente: entre qué plantillas se podrá elegir y,
 * si la hay, que se preselecciona la de motor sgr (la única que el servidor preselecciona).
 */
export const textoPlantillasNuevo = (disponibles) => {
    const lista = disponibles ?? [];
    if (lista.length === 0) return 'Hoy no se le ofrece ninguna plantilla: el expediente nacerá sin plantillas.';
    const texto = `Al crearlo podrás elegir entre: ${lista.map((p) => p.nombre).join(', ')}.`;
    const sgr = lista.find((p) => esSgr(p) && seOfrece(p));
    return sgr ? `${texto} Solo se preselecciona ${sgr.nombre}.` : texto;
};

/** Marca o quita una plantilla. La primera que se marca es la principal; si se quita la principal, pasa a la primera que queda. */
export const alternarPlantilla = (seleccion, id) => {
    const marcada = seleccion.plantilla_ids.includes(id);
    const plantilla_ids = marcada ? seleccion.plantilla_ids.filter((x) => x !== id) : [...seleccion.plantilla_ids, id];
    const principalSigue = seleccion.principal_id !== null && plantilla_ids.includes(seleccion.principal_id);
    return { ...seleccion, plantilla_ids, principal_id: principalSigue ? seleccion.principal_id : (plantilla_ids[0] ?? null) };
};

/** Solo una plantilla marcada puede ser la principal. */
export const elegirPrincipal = (seleccion, id) =>
    (seleccion.plantilla_ids.includes(id) ? { ...seleccion, principal_id: id } : seleccion);

/**
 * Estado de la casilla de una plantilla disponible.
 * - Marcada, siempre se puede quitar.
 * - Una que ya no se ofrece solo se puede volver a marcar mientras el expediente la tenga
 *   guardada (`plantillasExpediente`, las de la última respuesta del servidor): una plantilla
 *   nueva en el expediente debe ser compatible.
 * - Con una plantilla sgr elegida, otra sgr no se puede marcar.
 * - Un expediente ya configurado no se queda sin plantillas: la última no se puede quitar.
 * - Con MAX_PLANTILLAS marcadas, no se puede marcar otra.
 */
export const opcionPlantilla = (plantilla, seleccion, plantillasExpediente, disponibles, { configurado = false } = {}) => {
    if (!seleccion) return { marcada: false, deshabilitada: true, motivo: null };
    const id = plantilla.id;
    const marcada = seleccion.plantilla_ids.includes(id);
    if (marcada && configurado && seleccion.plantilla_ids.length === 1) {
        return { marcada, deshabilitada: true, motivo: MOTIVO_ULTIMA };
    }
    if (marcada) return { marcada, deshabilitada: false, motivo: null };

    if (seleccion.plantilla_ids.length >= MAX_PLANTILLAS) {
        return { marcada, deshabilitada: true, motivo: MOTIVO_MAXIMO };
    }

    if (!seOfrece(plantilla) && !buscar(plantillasExpediente, id)) {
        return { marcada, deshabilitada: true, motivo: MOTIVO_NO_OFRECIDA };
    }
    const otraSgr = seleccion.plantilla_ids.some((otra) => otra !== id && esSgr(buscar(disponibles, otra) ?? buscar(plantillasExpediente, otra)));
    if (esSgr(plantilla) && otraSgr) {
        return { marcada, deshabilitada: true, motivo: MOTIVO_OTRA_SGR };
    }
    return { marcada, deshabilitada: false, motivo: null };
};

/**
 * Plantillas que muestra el panel. Editable, todas las disponibles; en solo lectura (un radicado,
 * o un usuario que no edita), solo las de la selección, en su orden (la principal primero).
 */
export const plantillasDelPanel = (disponibles, seleccion, deshabilitada) => {
    const lista = disponibles ?? [];
    if (!deshabilitada) return lista;
    const ids = seleccion?.plantilla_ids ?? [];
    return ids.map((id) => lista.find((p) => p.id === id)).filter(Boolean);
};

/**
 * Qué mostrar cuando falló la lista de plantillas compatibles. En solo lectura (deshabilitado)
 * no hace falta esa lista: basta con las plantillas que ya trae el expediente, con su versión
 * y cuál es la principal. Editable, o sin plantillas que mostrar, devuelve null y se muestra el error.
 */
export const plantillasSinLista = (plantillasExpediente, deshabilitada) => {
    if (!deshabilitada || !plantillasExpediente?.length) return null;
    return plantillasExpediente.map((p) => ({
        id: idDe(p),
        nombre: p.nombre,
        principal: Boolean(p.es_principal),
        version: p.version ? `v${p.version.numero}` : 'Sin versión',
    }));
};

/** Avisos de una plantilla disponible. Una inactiva tampoco es compatible: basta con decir que está inactiva. */
export const avisosPlantilla = (plantilla) => {
    if (plantilla.activa === false) return ['Inactiva'];
    if (!plantilla.compatible) return ['Ya no es compatible con el proyecto'];
    return [];
};

/**
 * Aviso de las plantillas del expediente que ya no son compatibles con el proyecto, con la
 * concordancia de una o varias; null si no hay ninguna.
 */
export const avisoIncompatibles = (plantillas) => {
    // Lo que no sea un objeto (una respuesta inesperada) se ignora.
    const nombres = (Array.isArray(plantillas) ? plantillas : [])
        .filter((p) => p !== null && typeof p === 'object' && !p.compatible)
        .map((p) => p.nombre);
    if (nombres.length === 0) return null;
    const una = nombres.length === 1;
    return {
        titulo: una ? 'Una plantilla ya no es compatible con el proyecto' : 'Algunas plantillas ya no son compatibles con el proyecto',
        detalle: una
            ? `${nombres[0]}. Este expediente la conserva tal como está; un expediente nuevo no la tendrá.`
            : `${nombres.join(', ')}. Este expediente las conserva tal como están; un expediente nuevo no las tendrá.`,
    };
};

const plural = (n, singular, varios) => `${n} ${n === 1 ? singular : varios}`;

/**
 * Textos de la tarjeta de una plantilla: fuentes, sectores, requisitos de la vigente y versión.
 * Con `plantillasExpediente` (las de la última respuesta del servidor), la versión del expediente
 * sale de ahí: `en_expediente` es de cuando se cargó la lista y no sigue los cambios del panel.
 */
export const detallePlantilla = (plantilla, plantillasExpediente = null) => {
    const propia = plantillasExpediente ? buscar(plantillasExpediente, plantilla.id)?.version : plantilla.en_expediente?.version;
    const vigente = plantilla.version;
    let version = vigente ? `v${vigente.numero}` : 'Sin versión publicada';
    if (propia && propia.id !== vigente?.id) {
        version = vigente ? `v${propia.numero} en el expediente · v${vigente.numero} vigente` : `v${propia.numero} en el expediente`;
    }
    return {
        fuentes: textoAsociaciones(plantilla.todas_fuentes, plantilla.fuentes ?? [], 'Todas las fuentes'),
        sectores: textoAsociaciones(plantilla.todos_sectores, plantilla.sectores ?? [], 'Todos los sectores'),
        requisitos: plural(plantilla.requisitos ?? 0, 'requisito', 'requisitos'),
        version,
    };
};

/**
 * Selección para un expediente nuevo a partir de la de otro (un radicado): solo las plantillas
 * que hoy se ofrecen al proyecto. Devuelve los datos para la API y los nombres de las que se
 * quitaron, para avisar. Sin la lista de disponibles, la selección va tal cual y decide el servidor.
 *
 * @param plantillasExpediente las del expediente de origen, solo para nombrar las que no estén en la lista
 */
export const seleccionParaNuevo = (seleccion, disponibles, plantillasExpediente = []) => {
    if (!disponibles) return { datos: seleccion, quitadas: [] };

    const quedan = seleccion.plantilla_ids.filter((id) => seOfrece(buscar(disponibles, id)));
    const quitadas = seleccion.plantilla_ids
        .filter((id) => !quedan.includes(id))
        .map((id) => (buscar(disponibles, id) ?? buscar(plantillasExpediente, id))?.nombre ?? `Plantilla #${id}`);
    const principal_id = quedan.includes(seleccion.principal_id) ? seleccion.principal_id : (quedan[0] ?? null);

    return { datos: { ...seleccion, plantilla_ids: quedan, principal_id }, quitadas };
};
