// Avisos de contratación (fase 4B): el semáforo de cada etapa del expediente, la franja de avisos
// de la página del expediente, los chips de cada proceso (diálogo de procesos y ficha del
// proyecto) y el aviso del proceso en el módulo Contratos. Todo sale de lo que calcula el backend
// (`alerta`, `avisos`, `aviso_proceso`): aquí no se mira la fecha de hoy. Funciones puras,
// probadas en avisosContratacion.test.js.

import { rutaExpedienteContratacion } from './navegacion';

const ALERTAS = {
    vencida: { texto: 'Vencida', tono: 'coral' },
    proxima: { texto: 'Próxima', tono: 'ambar' },
    al_dia: { texto: 'Al día', tono: 'azul' },
    completa: { texto: 'Completa', tono: 'verde' },
    sin_completar: { texto: 'Sin completar', tono: 'neutro' },
};

/** La etiqueta del semáforo de una etapa (`alerta` de la API); sin fecha, ninguna. */
export const etiquetaAlerta = (alerta) => (ALERTAS[alerta] ? { ...ALERTAS[alerta] } : null);

const numero = (valor) => Number(valor) || 0;
const dias = (n) => (n === 1 ? '1 día' : `${n} días`);

/** "Cierre en N días", "Cierre hoy" o "Cierre vencido"; null si el cierre no está cerca ni pasó. */
export const textoCierre = (avisos) => {
    if (avisos?.cierre_vencido) return 'Cierre vencido';
    if (!avisos?.cierre_proximo) return null;
    const n = numero(avisos.dias_para_cierre);
    return n <= 0 ? 'Cierre hoy' : `Cierre en ${dias(n)}`;
};

/** "Sin actualización hace N días" (sin los días, solo "Sin actualización"). */
export const textoSinActualizacion = (n) => (n === null || n === undefined ? 'Sin actualización' : `Sin actualización hace ${dias(n)}`);

/**
 * Los chips de un proceso desde su `avisos`: vencidas, próximas, cierre, sin actualización e
 * hitos (si hay etapas que contar), cada uno con su tono de `ct-estado-*` y un título que explica
 * la cifra. Lista vacía si no hay nada que mostrar.
 */
export const chipsAvisosProceso = (avisos) => {
    if (!avisos) return [];
    const chips = [];
    const vencidas = numero(avisos.etapas_vencidas);
    if (vencidas > 0) {
        chips.push({
            clave: 'vencidas',
            texto: vencidas === 1 ? '1 vencida' : `${vencidas} vencidas`,
            tono: 'coral',
            titulo: vencidas === 1 ? '1 etapa del expediente vencida' : `${vencidas} etapas del expediente vencidas`,
        });
    }
    const proximas = numero(avisos.etapas_proximas);
    if (proximas > 0) {
        chips.push({
            clave: 'proximas',
            texto: proximas === 1 ? '1 próxima' : `${proximas} próximas`,
            tono: 'ambar',
            titulo: proximas === 1 ? '1 etapa del expediente próxima a vencer' : `${proximas} etapas del expediente próximas a vencer`,
        });
    }
    const cierre = textoCierre(avisos);
    if (cierre) {
        chips.push({
            clave: 'cierre',
            texto: cierre,
            tono: avisos.cierre_vencido ? 'coral' : 'ambar',
            titulo: avisos.cierre_vencido ? 'La fecha de cierre del proceso ya pasó' : 'La fecha de cierre del proceso está cerca',
        });
    }
    if (avisos.sin_actualizacion) {
        chips.push({
            clave: 'sin-actualizacion',
            texto: textoSinActualizacion(avisos.dias_sin_actualizacion),
            tono: 'neutro',
            titulo: 'Sin cambios en el proceso ni en su expediente',
        });
    }
    const completadas = numero(avisos.hitos?.completadas);
    const total = numero(avisos.hitos?.total);
    if (total > 0) {
        chips.push({
            clave: 'hitos',
            texto: `Hitos ${completadas}/${total}`,
            tono: completadas >= total ? 'verde' : 'neutro',
            titulo: `${completadas} de ${total} etapas del expediente completas`,
        });
    }
    return chips;
};

/**
 * La franja de avisos de la página del expediente (`avisos` del expediente), en frases
 * completas. Los hitos no son un aviso: no van. Lista vacía si no hay nada que avisar.
 */
export const franjaAvisosExpediente = (avisos) => {
    if (!avisos) return [];
    const franja = [];
    const vencidas = numero(avisos.etapas_vencidas);
    if (vencidas > 0) {
        franja.push({ clave: 'vencidas', texto: vencidas === 1 ? '1 etapa vencida' : `${vencidas} etapas vencidas`, tono: 'coral' });
    }
    const proximas = numero(avisos.etapas_proximas);
    if (proximas > 0) {
        franja.push({
            clave: 'proximas',
            texto: proximas === 1 ? '1 etapa próxima a vencer' : `${proximas} etapas próximas a vencer`,
            tono: 'ambar',
        });
    }
    if (avisos.cierre_vencido) {
        franja.push({ clave: 'cierre', texto: 'La fecha de cierre del proceso ya pasó', tono: 'coral' });
    } else if (avisos.cierre_proximo) {
        const n = numero(avisos.dias_para_cierre);
        franja.push({ clave: 'cierre', texto: n <= 0 ? 'El proceso cierra hoy' : `El proceso cierra en ${dias(n)}`, tono: 'ambar' });
    }
    if (avisos.sin_actualizacion) {
        franja.push({ clave: 'sin-actualizacion', texto: textoSinActualizacion(avisos.dias_sin_actualizacion), tono: 'neutro' });
    }
    return franja;
};

/**
 * El aviso de un contrato cuyo proceso tiene etapas vencidas, el cierre vencido o está sin
 * actualización (`aviso_proceso` de la API), con el enlace al checklist del proceso. Null si no
 * hay nada que avisar.
 */
export const avisoProcesoContrato = (aviso) => {
    if (!aviso || aviso.proceso_id === null || aviso.proceso_id === undefined) return null;
    const textos = [];
    const vencidas = numero(aviso.etapas_vencidas);
    if (vencidas > 0) textos.push(`El proceso tiene ${vencidas === 1 ? '1 etapa vencida' : `${vencidas} etapas vencidas`}`);
    if (aviso.cierre_vencido) textos.push('Cierre del proceso vencido');
    if (aviso.sin_actualizacion) {
        const n = aviso.dias_sin_actualizacion;
        textos.push(n === null || n === undefined ? 'Proceso sin actualización' : `Proceso sin actualización hace ${dias(n)}`);
    }
    if (textos.length === 0) return null;
    return {
        procesoId: aviso.proceso_id,
        ruta: rutaExpedienteContratacion(aviso.proceso_id),
        textos,
        texto: textos.join(' · '),
    };
};
