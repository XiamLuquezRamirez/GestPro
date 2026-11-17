// Lógica de presentación del módulo de contratación: catálogos de modalidades y etapas, y
// procesos de contratación de un proyecto.
// Funciones puras, probadas en contratacion.test.js. Las reglas las valida el backend; aquí se
// adelantan los mismos mensajes para no esperar un 422.

import { normalizar } from './navegacion';
import { compararEtapas, mover, nombreEtapa } from './plantillasChecklist';

/** Modalidades y etapas se administran con gestionar-catalogos: solo un Administrador. */
export const puedeAdministrarCatalogos = (usuario) => usuario?.rol === 'Administrador';

/** Las reglas del formulario de cada catálogo, con los límites y mensajes del controlador. */
export const REGLAS_MODALIDAD = {
    maximoNombre: 199,
    faltaNombre: 'Escribe el nombre de la modalidad.',
    repetido: 'Ya existe una modalidad con ese nombre.',
};

export const REGLAS_ETAPA = {
    maximoNombre: 120,
    maximoDescripcion: 2000,
    faltaNombre: 'Escribe el nombre de la etapa.',
    repetido: 'Ya existe una etapa con ese nombre.',
};

export const CATALOGO_VACIO = { nombre: '', descripcion: '' };

export const formularioCatalogo = (item) => ({
    nombre: item?.nombre ?? '',
    descripcion: item?.descripcion ?? '',
});

/**
 * Errores del formulario por campo; {} si se puede enviar. El nombre no se repite sin contar
 * mayúsculas ni tildes, como en el servidor. `idActual` es el del registro que se edita.
 */
export const validarCatalogo = (formulario, items, idActual, reglas) => {
    const errores = {};
    const nombre = String(formulario.nombre ?? '').trim();
    if (!nombre) {
        errores.nombre = reglas.faltaNombre;
    } else if (nombre.length > reglas.maximoNombre) {
        errores.nombre = `El nombre admite hasta ${reglas.maximoNombre} caracteres.`;
    } else if ((items ?? []).some((i) => i.id !== idActual && normalizar(i.nombre) === normalizar(nombre))) {
        errores.nombre = reglas.repetido;
    }
    const descripcion = String(formulario.descripcion ?? '').trim();
    if (reglas.maximoDescripcion && descripcion.length > reglas.maximoDescripcion) {
        errores.descripcion = `La descripción admite hasta ${reglas.maximoDescripcion} caracteres.`;
    }
    return errores;
};

/** Lo que esperan /guardarModalidad y /guardarEtapaContratacion. `conDescripcion` solo en etapas. */
export const datosCatalogo = (formulario, item, conDescripcion = false) => ({
    accion: item ? 'Editar' : 'Agregar',
    ...(item ? { id: item.id } : {}),
    nombre: String(formulario.nombre ?? '').trim(),
    ...(conDescripcion ? { descripcion: String(formulario.descripcion ?? '').trim() || null } : {}),
});

/** Por orden y, a igual orden, por nombre (como las modalidades del servidor) y por id. */
export const ordenarCatalogo = (items) => [...(items ?? [])].sort((a, b) => (
    (a.orden ?? 0) - (b.orden ?? 0)
    || String(a.nombre ?? '').localeCompare(String(b.nombre ?? ''), 'es')
    || a.id - b.id
));

/** Los ids de la lista con el elemento `indice` movido una posición: lo que espera el PUT de orden. */
export const idsTrasMover = (items, indice, delta) => mover((items ?? []).map((i) => i.id), indice, delta);

/**
 * Los errores de validación de un 422 ({ errors }) repartidos: el primer mensaje de cada campo
 * del formulario y, aparte, los de otros campos. null si el error no es de validación.
 */
export const erroresPorCampo = (error, campos) => {
    const errores = error?.response?.status === 422 ? error.response.data?.errors : null;
    if (!errores || typeof errores !== 'object') return null;
    const enCampos = {};
    const otros = [];
    Object.entries(errores).forEach(([campo, mensajes]) => {
        const lista = [].concat(mensajes ?? []).map(String);
        if (campos.includes(campo)) {
            if (lista.length) enCampos[campo] = lista[0];
        } else {
            otros.push(...lista);
        }
    });
    return { campos: enCampos, otros };
};

/** Modalidades y etapas son femeninas: "Activa" o "Inactiva". */
export const textoEstadoCatalogo = (activo) => (activo ? 'Activa' : 'Inactiva');

// ---------- Procesos de contratación ----------

/** Los procesos se escriben con editar-datos: Administrador o Gestor. */
export const puedeEditarProcesos = (usuario) => ['Administrador', 'Gestor'].includes(usuario?.rol);

// Los valores son las constantes de ProcesoLicitacion (contratacion.test.js lo comprueba).
export const PLATAFORMAS = [
    ['secop_ii', 'SECOP II'],
    ['secop_i', 'SECOP I'],
    ['tvec', 'Tienda Virtual'],
    ['ninguna', 'Sin plataforma'],
];

export const ESTADOS_PROCESO = [
    ['preparacion', 'En preparación'],
    ['publicado', 'Publicado'],
    ['evaluacion', 'En evaluación'],
    ['adjudicado', 'Adjudicado'],
    ['desierto', 'Desierto'],
    ['celebrado', 'Celebrado'],
    ['cancelado', 'Cancelado'],
];

/** Estados que cierran un proceso: salir de ellos es inusual. */
export const ESTADOS_CIERRE = ['desierto', 'celebrado', 'cancelado'];

// Paso de cada estado en el flujo preparación → publicado → evaluación → adjudicado o desierto →
// celebrado. Cancelado no tiene paso: se puede cancelar desde cualquier estado.
const PASO_FLUJO = { preparacion: 0, publicado: 1, evaluacion: 2, adjudicado: 3, desierto: 3, celebrado: 4 };

const TONOS_ESTADO = {
    preparacion: 'neutro',
    publicado: 'azul',
    evaluacion: 'ambar',
    adjudicado: 'verde',
    celebrado: 'verde-pleno',
    desierto: 'coral',
    cancelado: 'coral',
};

export const MAXIMO_OBSERVACION = 2000;

const etiqueta = (lista, valor) => lista.find(([v]) => v === valor)?.[1];

export const etiquetaPlataforma = (valor) => etiqueta(PLATAFORMAS, valor) ?? '—';
export const etiquetaEstadoProceso = (valor) => etiqueta(ESTADOS_PROCESO, valor) ?? (valor || '—');
/** Tono de la insignia de estado; la clase CSS es ct-estado-<tono>. */
export const tonoEstado = (valor) => TONOS_ESTADO[valor] ?? 'neutro';

/**
 * El cambio de estado nunca se bloquea (el checklist solo avisa), pero salir de un estado de
 * cierre o volver atrás en el flujo pide confirmación. Devuelve { titulo, texto } del aviso, o
 * null si el cambio es el esperado.
 */
export const avisoCambioEstado = (anterior, nuevo) => {
    if (!anterior || !nuevo || anterior === nuevo) return null;
    const de = etiquetaEstadoProceso(anterior);
    const a = etiquetaEstadoProceso(nuevo);
    if (ESTADOS_CIERRE.includes(anterior)) {
        return {
            titulo: 'El proceso ya está cerrado',
            texto: `"${de}" es un estado de cierre. ¿Seguro que quieres pasar el proceso a "${a}"? El cambio queda en el historial.`,
        };
    }
    if (anterior in PASO_FLUJO && nuevo in PASO_FLUJO && PASO_FLUJO[nuevo] < PASO_FLUJO[anterior]) {
        return {
            titulo: 'El proceso vuelve atrás',
            texto: `"${a}" es un paso anterior a "${de}" en el flujo del proceso. ¿Seguro que quieres cambiarlo? El cambio queda en el historial.`,
        };
    }
    return null;
};

// Valor en pesos. La columna es decimal(15, 2): 13 cifras enteras y 2 decimales. En el formulario
// se escribe como en Colombia, con punto de miles y coma decimal; a la API va "1234567.5".
const CIFRAS_ENTERAS = 13;

/**
 * Sin coma, un punto seguido de 1 o 2 cifras al final es el decimal: "1234567.89" o "150.5", como
 * llega un valor pegado de una hoja de cálculo. Con 3 cifras ("1.500") sigue siendo de miles.
 */
const conComaDecimal = (texto) => (texto.includes(',') ? texto : texto.replace(/\.(\d{1,2})$/, ',$1'));

/** `texto` es `anterior` con un tramo seguido borrado (retroceso, suprimir o cortar). */
const esBorrado = (texto, anterior) => {
    if (texto.length >= anterior.length) return false;
    let inicio = 0;
    while (inicio < texto.length && texto[inicio] === anterior[inicio]) inicio += 1;
    let fin = 0;
    while (fin < texto.length - inicio && texto[texto.length - 1 - fin] === anterior[anterior.length - 1 - fin]) fin += 1;
    return inicio + fin === texto.length;
};

/**
 * Lo que el usuario escribe en el valor, con los miles agrupados: "1234567,891" → "1.234.567,89".
 * Un punto que se teclea es de miles (se escribe "1.500.000" sin romper nada): el decimal se
 * teclea con coma. `anterior` es lo que mostraba el campo: al borrar de "1.234" queda "1.23", que
 * no es un decimal.
 */
export const valorEnEdicion = (texto, anterior = '') => {
    const crudo = String(texto ?? '').replace(/[^\d.,]/g, '');
    const limpio = (esBorrado(crudo, String(anterior ?? '')) ? crudo : conComaDecimal(crudo)).replace(/[^\d,]/g, '');
    if (!limpio) return '';
    const coma = limpio.indexOf(',');
    const enteros = (coma === -1 ? limpio : limpio.slice(0, coma)).replace(/^0+(?=\d)/, '');
    const agrupados = (enteros || '0').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    if (coma === -1) return agrupados;
    return `${agrupados},${limpio.slice(coma + 1).replace(/,/g, '').slice(0, 2)}`;
};

/** El valor del formulario como lo espera la API: "1.234.567,5" → "1234567.5"; vacío → null. */
export const valorParaApi = (texto) => {
    const limpio = conComaDecimal(String(texto ?? '').replace(/[^\d.,]/g, '')).replace(/[^\d,]/g, '');
    if (!limpio.replace(/,/g, '')) return null;
    const [enteros, decimales = ''] = limpio.split(',');
    const entero = enteros.replace(/^0+(?=\d)/, '') || '0';
    return decimales ? `${entero}.${decimales}` : entero;
};

/** El valor de la API para el formulario: "1234567.50" → "1.234.567,50"; "1000.00" → "1.000". */
export const valorDesdeApi = (monto) => {
    if (monto === null || monto === undefined || monto === '') return '';
    const [enteros, decimales = ''] = String(monto).split('.');
    return valorEnEdicion(/^0*$/.test(decimales) ? enteros : `${enteros},${decimales}`);
};

const PESOS = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 });
const PESOS_CON_CENTAVOS = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** "$ 1.234.567" o, con centavos, "$ 1.234.567,50"; sin valor, "—". */
export const textoValor = (monto) => {
    if (monto === null || monto === undefined || monto === '') return '—';
    const numero = Number(monto);
    if (!Number.isFinite(numero)) return '—';
    return (Number.isInteger(numero) ? PESOS : PESOS_CON_CENTAVOS).format(numero);
};

export const textoContratos = (cantidad) => {
    const n = Number(cantidad) || 0;
    if (n === 0) return 'Sin contratos';
    return n === 1 ? '1 contrato' : `${n} contratos`;
};

/** Solo un enlace http(s) completo se muestra como enlace: nunca javascript: ni rutas relativas. */
export const esEnlaceWeb = (texto) => {
    const valor = String(texto ?? '').trim();
    if (!/^https?:\/\//i.test(valor)) return false;
    try {
        return Boolean(new URL(valor).hostname);
    } catch {
        return false;
    }
};

/** El número del proceso y su enlace: sin número pero con enlace, "Ver proceso". */
export const numeroProceso = (proceso) => {
    const enlace = esEnlaceWeb(proceso?.url_proceso) ? String(proceso.url_proceso).trim() : null;
    const codigo = String(proceso?.codigo_proceso ?? '').trim();
    return { texto: codigo || (enlace ? 'Ver proceso' : '—'), enlace };
};

// La API de entidades devuelve `activo` como 1/0 (consulta sin modelo); la de modalidades, booleano.
const esActivo = (valor) => valor === true || valor === 1 || valor === '1';

/**
 * Opciones de un select de catálogo: las activas y, si `actual` (lo que el proceso ya tiene) está
 * inactiva o ya no viene en la lista, también ella, marcada "(inactiva)". Con la lista sin cargar
 * (null) no se sabe: se ofrece la actual, sin marca, para no perderla.
 */
export const opcionesConActual = (items, actual) => {
    const lista = items ?? [];
    const opciones = lista.filter((i) => esActivo(i.activo)).map((i) => ({ valor: String(i.id), etiqueta: i.nombre }));
    if (actual?.id !== undefined && actual?.id !== null && !opciones.some((o) => o.valor === String(actual.id))) {
        const enLista = lista.find((i) => String(i.id) === String(actual.id));
        const nombre = enLista?.nombre ?? actual.nombre;
        opciones.push({ valor: String(actual.id), etiqueta: items === null || items === undefined ? nombre : `${nombre} (inactiva)` });
    }
    return opciones;
};

/**
 * Opciones del select de etapa de un requisito de contratación: las activas en el orden del
 * catálogo (por orden y, a igual orden, por id, como el servidor y la lista agrupada), numeradas,
 * y la que el requisito ya tiene (`actual`) si está inactiva, marcada. Con el catálogo sin cargar
 * (null) solo se ofrece la actual.
 */
export const opcionesEtapa = (etapas, actual) => {
    const numerada = (etapa) => ({ ...etapa, nombre: nombreEtapa(etapa) });
    const lista = etapas === null || etapas === undefined ? null : [...etapas].sort(compararEtapas).map(numerada);
    return opcionesConActual(lista, actual ? numerada(actual) : null);
};

export const PROCESO_VACIO = {
    objeto: '',
    modalidad_id: '',
    plataforma: '',
    codigo_proceso: '',
    url_proceso: '',
    entidad_contratante_id: '',
    monto: '',
};

/** Los campos del formulario, en su orden: también son los nombres de la API. */
export const CAMPOS_PROCESO = Object.keys(PROCESO_VACIO);

export const formularioProceso = (proceso) => {
    if (!proceso) return { ...PROCESO_VACIO };
    const id = (valor) => (valor?.id === undefined || valor?.id === null ? '' : String(valor.id));
    return {
        objeto: proceso.objeto ?? '',
        modalidad_id: id(proceso.modalidad),
        // Un proceso anterior a la plataforma obligatoria la elige al editarse.
        plataforma: PLATAFORMAS.some(([v]) => v === proceso.plataforma) ? proceso.plataforma : '',
        codigo_proceso: proceso.codigo_proceso ?? '',
        url_proceso: proceso.url_proceso ?? '',
        entidad_contratante_id: id(proceso.entidad_contratante),
        monto: valorDesdeApi(proceso.monto),
    };
};

/** Errores del formulario por campo, con los mensajes del servidor; {} si se puede enviar. */
export const validarProceso = (formulario) => {
    const errores = {};
    const texto = (campo) => String(formulario[campo] ?? '').trim();
    if (!texto('objeto')) errores.objeto = 'Escribe el objeto del proceso.';
    else if (texto('objeto').length > 5000) errores.objeto = 'El objeto admite hasta 5000 caracteres.';
    if (!texto('modalidad_id')) errores.modalidad_id = 'Elige la modalidad del proceso.';
    if (!texto('plataforma')) errores.plataforma = 'Elige la plataforma del proceso.';
    else if (!PLATAFORMAS.some(([v]) => v === texto('plataforma'))) errores.plataforma = 'Elige una plataforma de la lista.';
    if (texto('codigo_proceso').length > 100) errores.codigo_proceso = 'El número del proceso admite hasta 100 caracteres.';
    if (texto('url_proceso')) {
        if (!esEnlaceWeb(texto('url_proceso'))) errores.url_proceso = 'Escribe un enlace completo que empiece por http:// o https://.';
        else if (texto('url_proceso').length > 500) errores.url_proceso = 'El enlace admite hasta 500 caracteres.';
    }
    const monto = valorParaApi(formulario.monto);
    if (monto !== null && monto.split('.')[0].length > CIFRAS_ENTERAS) {
        errores.monto = `El valor admite hasta ${CIFRAS_ENTERAS} cifras enteras y 2 decimales.`;
    }
    return errores;
};

/**
 * Lo que esperan el POST y el PUT de procesos. Se envían todos los campos: un opcional vacío va
 * como null y así se borra al editar. El estado no se envía: tiene su propia ruta. Al editar
 * `proceso` es el que se abrió: va su `actualizado`, para que el servidor rechace el cambio si
 * otra persona lo modificó entretanto.
 */
export const datosProceso = (formulario, proceso = null) => {
    const texto = (campo) => String(formulario[campo] ?? '').trim() || null;
    const id = (campo) => (texto(campo) === null ? null : Number(texto(campo)));
    return {
        objeto: texto('objeto'),
        modalidad_id: id('modalidad_id'),
        plataforma: texto('plataforma'),
        codigo_proceso: texto('codigo_proceso'),
        url_proceso: texto('url_proceso'),
        entidad_contratante_id: id('entidad_contratante_id'),
        monto: valorParaApi(formulario.monto),
        ...(proceso ? { actualizado: proceso.actualizado ?? null } : {}),
    };
};

/** Lo que espera el cambio de estado: con el `actualizado` del proceso que se abrió. */
export const datosEstado = (proceso, estado, observacion) => ({
    estado,
    observacion: String(observacion ?? '').trim() || null,
    actualizado: proceso?.actualizado ?? null,
});

/** El servidor rechazó el cambio (409) porque otra persona modificó el proceso entretanto. */
export const esEdicionDesactualizada = (error) => error?.response?.status === 409;

/**
 * Foco atrapado en un diálogo: el índice al que debe saltar Tab (o Mayús+Tab) desde `indice` entre
 * `total` controles, o null si el navegador puede seguir solo. -1 es un foco fuera de la lista
 * (el propio diálogo); sin controles, -1 deja el foco en el diálogo.
 */
export const indiceFocoAtrapado = (indice, total, haciaAtras) => {
    if (total <= 0) return -1;
    if (haciaAtras) return indice <= 0 ? total - 1 : null;
    return indice === -1 || indice >= total - 1 ? 0 : null;
};
