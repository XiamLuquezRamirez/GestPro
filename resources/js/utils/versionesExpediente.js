// Versión nueva de una plantilla del expediente: qué plantillas la tienen y la vista previa que
// se muestra antes de aplicarla (GET /expedientes/{e}/plantillas/{p}/version-nueva, que arma
// VersionesExpediente::vistaPrevia). Funciones puras, probadas en versionesExpediente.test.js.

import { ETIQUETA_CARACTER } from './expedienteChecklist';
import { escaparHtml } from './plantillasChecklist';

/** Requisitos que se muestran en cada lista de la vista previa; los demás solo se cuentan. */
export const TOPE_VISTA_PREVIA = 15;

/** Las plantillas del expediente (las de su cabecera) que tienen una versión nueva para aplicar. */
export const plantillasConVersionNueva = (plantillas) =>
    (Array.isArray(plantillas) ? plantillas : []).filter((p) => p !== null && typeof p === 'object' && p.version_nueva);

/**
 * "versión 3 disponible", o null si la plantilla no tiene versión nueva. La versión que usa el
 * expediente ya se muestra al lado ("SGR (v2)"): no se repite.
 */
export const textoVersionNueva = (plantilla) => {
    const nueva = plantilla?.version_nueva;
    return nueva ? `versión ${nueva.numero} disponible` : null;
};

/**
 * Nombre accesible del botón que la aplica: "Aplicar la versión 3 de Obras de agua". El botón
 * solo dice "Aplicar versión 3" y puede haber uno por plantilla. Null sin versión nueva.
 */
export const etiquetaAplicarVersion = (plantilla) => {
    const nueva = plantilla?.version_nueva;
    return nueva ? `Aplicar la versión ${nueva.numero} de ${plantilla.nombre}` : null;
};

/** Avance como lo muestra la pantalla: "12 de 30 (40%)". */
const textoAvance = (progreso) => `${progreso?.cumplidos ?? 0} de ${progreso?.total ?? 0} (${progreso?.porcentaje ?? 0}%)`;

const mismoAvance = (a, b) => a?.cumplidos === b?.cumplidos && a?.total === b?.total;

/** Lo que se muestra de una fila resumida, como contenidoFila: código y texto; un propio, solo su título. */
const contenido = (fila) => {
    // El backend solo manda `titulo` en un propio.
    const propio = fila?.titulo !== undefined;
    return { propio, codigo: propio ? null : (fila?.codigo ?? null), texto: (propio ? fila.titulo : fila?.texto) ?? '' };
};

const etiqueta = (texto) => `<span class="exv-vista-etiqueta">${escaparHtml(texto)}</span>`;

/** Código y texto de la fila, escapados. Un propio lo dice: en el checklist va en su propio bloque. */
const htmlTexto = (fila) => {
    const { propio, codigo, texto } = contenido(fila);
    return `${codigo ? `<b>${escaparHtml(codigo)}</b> ` : ''}${escaparHtml(texto)}${propio ? ` ${etiqueta('propio')}` : ''}`;
};

const caracterLegible = (caracter) => (ETIQUETA_CARACTER[caracter] ?? String(caracter ?? '')).toLowerCase();

/** Como en el checklist, solo se marca lo que no es obligatorio. */
const htmlCaracter = (fila) => (fila?.caracter && fila.caracter !== 'obligatorio' ? ` ${etiqueta(caracterLegible(fila.caracter))}` : '');

// Una fila con id ya existía: es una retirada que vuelve a aplicar, con el trabajo que tenía.
const htmlNuevo = (fila) => `${htmlTexto(fila)}${htmlCaracter(fila)}${fila?.id != null ? ` ${etiqueta('vuelve a aplicar')}` : ''}`;

const htmlRetirado = (fila) => `${htmlTexto(fila)}${fila?.con_trabajo ? ` ${etiqueta('conserva su trabajo')}` : ''}`;

const htmlCambio = ({ antes, despues }) => {
    const a = contenido(antes);
    const d = contenido(despues);
    const partes = [`${htmlTexto(antes)}${htmlCaracter(antes)}`];
    if (a.codigo !== d.codigo || a.texto !== d.texto) {
        partes.push(`<span class="exv-vista-ahora">Ahora: ${htmlTexto(despues)}</span>`);
    }
    if (antes?.caracter !== despues?.caracter) {
        partes.push(`<span class="exv-vista-ahora">Pasa de ${escaparHtml(caracterLegible(antes?.caracter))} a ${escaparHtml(caracterLegible(despues?.caracter))}.</span>`);
    }
    return partes.join('');
};

/** Lista con tope: las primeras TOPE_VISTA_PREVIA y "y N más". */
const htmlLista = (filas, htmlFila) => {
    const items = filas.slice(0, TOPE_VISTA_PREVIA).map((f) => `<li>${htmlFila(f)}</li>`);
    if (filas.length > TOPE_VISTA_PREVIA) items.push(`<li class="exv-vista-mas">y ${filas.length - TOPE_VISTA_PREVIA} más</li>`);
    return `<ul>${items.join('')}</ul>`;
};

const htmlSeccion = (titulo, filas, htmlFila) => (
    filas.length ? `<section><h3>${escaparHtml(titulo)}</h3>${htmlLista(filas, htmlFila)}</section>` : ''
);

/** El texto en singular o, con `{n}` reemplazado, en plural. */
const contar = (n, singular, varios) => (n === 1 ? singular : varios.replace('{n}', n));

/**
 * HTML de la vista previa para Swal: versión actual y nueva, la descripción de la nueva, el avance
 * antes y después, los requisitos que se agregan, los que dejan de aplicar (y si conservan su
 * trabajo), los que cambian y cuántos siguen igual. Todo texto va escapado.
 */
export const htmlVistaPrevia = (vista) => {
    const nuevos = vista?.nuevos ?? [];
    const retirados = vista?.retirados ?? [];
    const cambian = vista?.cambian ?? [];
    const siguen = Number(vista?.siguen) || 0;
    const { progreso_antes: antes, progreso_despues: despues } = vista ?? {};
    const descripcion = vista?.version_nueva?.descripcion?.trim();

    const partes = [
        `<p class="exv-vista-resumen"><b>Versión ${escaparHtml(vista?.version_actual?.numero)} → ${escaparHtml(vista?.version_nueva?.numero)}</b></p>`,
        descripcion ? `<p class="exv-vista-descripcion">${escaparHtml(descripcion)}</p>` : '',
        `<p>Avance: ${escaparHtml(mismoAvance(antes, despues) ? `${textoAvance(antes)}, no cambia` : `${textoAvance(antes)} → ${textoAvance(despues)}`)}</p>`,
    ];

    if (nuevos.length + retirados.length + cambian.length === 0) {
        partes.push('<p class="exv-vista-igual">La versión nueva no cambia el checklist de este expediente.</p>');
    }
    partes.push(
        htmlSeccion(contar(nuevos.length, 'Se agrega 1 requisito', 'Se agregan {n} requisitos'), nuevos, htmlNuevo),
        htmlSeccion(contar(retirados.length, 'Deja de aplicar 1 requisito', 'Dejan de aplicar {n} requisitos'), retirados, htmlRetirado),
        htmlSeccion(contar(cambian.length, 'Cambia 1 requisito', 'Cambian {n} requisitos'), cambian, htmlCambio),
    );
    if (siguen > 0) {
        partes.push(`<p>${escaparHtml(contar(siguen, '1 requisito sigue igual.', '{n} requisitos siguen igual.'))}</p>`);
    }

    return `<div class="exv-vista-version">${partes.join('')}</div>`;
};
