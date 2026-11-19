// Lógica de presentación del expediente de contratación de un proceso (/contratacion/:procesoId):
// el checklist por etapa, el avance por etapa, los requisitos propios con su etapa, el aviso de
// pendientes al cerrar y la elección de plantillas para crearlo. La agrupación de viabilización
// (agruparChecklist) no cambia. Funciones puras, probadas en expedienteContratacion.test.js.

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

/** El expediente con el progreso y el avance por etapa que trae una respuesta parcial, si los trae. */
export const conAvance = (expediente, datos) => ({
    ...expediente,
    progreso: datos?.progreso ?? expediente.progreso,
    avance_por_etapa: datos?.avance_por_etapa ?? expediente.avance_por_etapa,
});

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

const plural = (n, singular, varios) => (n === 1 ? `${n} ${singular}` : `${n} ${varios}`);

/**
 * Los pendientes con que el servidor rechazó cerrar sin confirmar (`pendientes` del 422):
 * "Faltan 4 requisitos por cumplir" y una línea por etapa. Null si no hay.
 */
export const textoPendientesCierre = (pendientes) => {
    if (!pendientes?.total) return null;
    return {
        titulo: `${pendientes.total === 1 ? 'Falta' : 'Faltan'} ${plural(pendientes.total, 'requisito', 'requisitos')} por cumplir`,
        etapas: (pendientes.por_etapa ?? []).map((e) => `${tituloEtapa(e.etapa)}: ${plural(e.pendientes, 'pendiente', 'pendientes')}`),
    };
};

/** El cuerpo del aviso antes de confirmar el cierre, con los nombres escapados. */
export const htmlPendientesCierre = (pendientes) => {
    const texto = textoPendientesCierre(pendientes);
    if (!texto) return '';
    const etapas = texto.etapas.map((e) => `<li>${escaparHtml(e)}</li>`).join('');
    return `<p>${escaparHtml(texto.titulo)}.</p>${etapas ? `<ul style="text-align:left">${etapas}</ul>` : ''}`
        + '<p>Puedes cerrarlo de todos modos: la revisión solo avisa. Cerrado, el checklist queda congelado.</p>';
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
