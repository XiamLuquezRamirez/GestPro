// Lógica de presentación de la administración de plantillas de checklist.
// Funciones puras, probadas en plantillasChecklist.test.js. Las reglas las valida el backend.

import { ORDEN_SECCIONES, SECCIONES, mensajeError } from './expedienteChecklist';

export const CATEGORIAS = [
    ['tecnico', 'Técnico'],
    ['juridico', 'Jurídico'],
    ['financiero', 'Financiero'],
    ['ambiental', 'Ambiental'],
    ['social', 'Social'],
    ['administrativo', 'Administrativo'],
    ['otro', 'Otro'],
];

export const TIPOS_REQUISITO = [
    ['documental', 'Documental'],
    ['validacion', 'Validación'],
    ['condicion', 'Condición'],
];

export const CONDICIONADO = 'condicionado';

export const CARACTERES = [
    ['obligatorio', 'Obligatorio'],
    ['opcional', 'Opcional'],
    [CONDICIONADO, 'Condicionado'],
];

/** Norma de la que sale un requisito del evaluador SGR (el valor `fuente` que guarda la API). */
export const FUENTES_NORMATIVAS = [
    ['a12', 'Acuerdo 12/2024'],
    ['ot', 'Orientaciones Transitorias'],
];

export const ALCANCES = [
    ['general', 'General'],
    ['especifica', 'Específica'],
];

const etiqueta = (lista, valor) => lista.find(([v]) => v === valor)?.[1] ?? '';

export const etiquetaCategoria = (valor) => etiqueta(CATEGORIAS, valor);
export const etiquetaTipo = (valor) => etiqueta(TIPOS_REQUISITO, valor);
export const etiquetaCaracter = (valor) => etiqueta(CARACTERES, valor);
export const etiquetaAlcance = (valor) => etiqueta(ALCANCES, valor);
export const etiquetaFuenteNormativa = (valor) => etiqueta(FUENTES_NORMATIVAS, valor);

/** "Todas las fuentes" o los nombres separados por coma. */
export const textoAsociaciones = (todas, items, textoTodas) =>
    todas ? textoTodas : (items.length ? items.map((i) => i.nombre).join(', ') : '—');

export const PLANTILLA_VACIA = {
    nombre: '',
    descripcion: '',
    alcance: '',
    todas_fuentes: false,
    todos_sectores: true,
    fuente_ids: [],
    sector_ids: [],
};

export const formularioPlantilla = (plantilla) => ({
    nombre: plantilla.nombre ?? '',
    descripcion: plantilla.descripcion ?? '',
    alcance: plantilla.alcance ?? '',
    todas_fuentes: Boolean(plantilla.todas_fuentes),
    todos_sectores: Boolean(plantilla.todos_sectores),
    fuente_ids: (plantilla.fuentes ?? []).map((f) => f.id),
    sector_ids: (plantilla.sectores ?? []).map((s) => s.id),
});

/** Lo que espera la API: con "todas", la lista se envía vacía. */
export const datosPlantilla = (formulario) => ({
    nombre: formulario.nombre.trim(),
    descripcion: formulario.descripcion.trim() || null,
    alcance: formulario.alcance || null,
    todas_fuentes: formulario.todas_fuentes,
    todos_sectores: formulario.todos_sectores,
    fuente_ids: formulario.todas_fuentes ? [] : formulario.fuente_ids,
    sector_ids: formulario.todos_sectores ? [] : formulario.sector_ids,
});

/**
 * Lo que se envía al guardar. La plantilla SGR siempre aplica a todos los sectores y conserva la
 * fuente SGR (salvo que ya sea para todas las fuentes): un dato inconsistente no debe llegar como 422.
 */
export const datosGuardarPlantilla = (formulario, plantilla, fuentes = []) => {
    if (!plantilla?.es_sgr) return datosPlantilla(formulario);
    const idSgr = fuentes.find((f) => f.clave === 'sgr')?.id;
    const conSgr = idSgr === undefined || formulario.todas_fuentes || formulario.fuente_ids.includes(idSgr)
        ? formulario.fuente_ids
        : [...formulario.fuente_ids, idSgr];
    return datosPlantilla({ ...formulario, todos_sectores: true, fuente_ids: conSgr });
};

/** Opciones de un catálogo: las activas y las que la plantilla ya tiene. */
export const opcionesCatalogo = (items, elegidos) => items.filter((i) => i.activo || elegidos.includes(i.id));

const CAMPOS_TEXTO = [
    'texto', 'descripcion', 'categoria', 'tipo_requisito', 'cargo_cumplimiento', 'cargo_revision',
    'soporte_requerido', 'referencia_normativa', 'nota', 'condicion_descripcion', 'codigo_comun',
    'clave', 'codigo', 'subtitulo', 'seccion', 'fuente', 'condicion',
];

export const requisitoVacio = (motor) => ({
    ...Object.fromEntries(CAMPOS_TEXTO.map((c) => [c, ''])),
    caracter: 'obligatorio',
    requiere_aprobacion_no_aplica: motor === 'sgr',
    activo: true,
    sector_id: '',
    tipo_id: '',
    ...(motor === 'sgr' ? { seccion: 'A', fuente: 'a12', condicion: 'siempre' } : {}),
});

export const formularioRequisito = (requisito, motor) => {
    const base = requisitoVacio(motor);
    // El respaldo solo cubre un campo que no viene (undefined); un null del servidor es un dato real.
    const texto = (campo) => (requisito[campo] === undefined ? base[campo] : requisito[campo]) ?? '';
    const conRespaldo = (campo) => requisito[campo] ?? base[campo];
    return {
        ...base,
        ...Object.fromEntries(CAMPOS_TEXTO.map((c) => [c, texto(c)])),
        caracter: conRespaldo('caracter'),
        requiere_aprobacion_no_aplica: Boolean(conRespaldo('requiere_aprobacion_no_aplica')),
        activo: conRespaldo('activo'),
        sector_id: texto('sector_id'),
        tipo_id: texto('tipo_id'),
    };
};

/** Lo que espera la API según el motor. Los textos vacíos viajan como null. */
export const datosRequisito = (formulario, motor) => {
    const texto = (campo) => (String(formulario[campo] ?? '').trim() || null);
    const comunes = {
        texto: texto('texto'),
        descripcion: texto('descripcion'),
        categoria: texto('categoria'),
        tipo_requisito: texto('tipo_requisito'),
        cargo_cumplimiento: texto('cargo_cumplimiento'),
        cargo_revision: texto('cargo_revision'),
        soporte_requerido: texto('soporte_requerido'),
        referencia_normativa: texto('referencia_normativa'),
        nota: texto('nota'),
        requiere_aprobacion_no_aplica: formulario.requiere_aprobacion_no_aplica,
        activo: formulario.activo,
        codigo_comun: texto('codigo_comun'),
    };

    if (motor === 'sgr') {
        return {
            ...comunes,
            clave: texto('clave'),
            seccion: formulario.seccion,
            fuente: formulario.fuente,
            sector_id: formulario.seccion === 'C' ? (Number(formulario.sector_id) || null) : null,
            tipo_id: formulario.seccion === 'C' ? (Number(formulario.tipo_id) || null) : null,
            subtitulo: texto('subtitulo'),
            codigo: texto('codigo'),
            condicion: texto('condicion'),
        };
    }

    return {
        ...comunes,
        clave: texto('clave'),
        caracter: formulario.caracter,
        condicion_descripcion: formulario.caracter === CONDICIONADO ? texto('condicion_descripcion') : null,
        codigo: texto('codigo'),
    };
};

/**
 * Vista previa: los requisitos activos como los verá el usuario. El SGR por sección,
 * en el orden del expediente; una plantilla simple por categoría.
 */
export const agruparVistaPrevia = (requisitos, motor) => {
    const activos = requisitos.filter((r) => r.activo).sort((a, b) => a.orden - b.orden);

    if (motor === 'sgr') {
        return ORDEN_SECCIONES
            .map((clave) => ({
                clave,
                titulo: `${SECCIONES[clave].num} ${SECCIONES[clave].titulo}`,
                requisitos: activos.filter((r) => r.seccion === clave),
            }))
            .filter((g) => g.requisitos.length);
    }

    const grupos = [...CATEGORIAS, [null, 'Sin categoría']]
        .map(([clave, titulo]) => ({ clave: clave ?? 'sin', titulo, requisitos: activos.filter((r) => (CATEGORIAS.some(([c]) => c === r.categoria) ? r.categoria : null) === clave) }));
    return grupos.filter((g) => g.requisitos.length);
};

/** La pestaña a la que llevan las flechas, Inicio y Fin desde `actual`; null si la tecla no navega. */
export const pestanaPorTecla = (ids, actual, tecla) => {
    const indice = ids.indexOf(actual);
    if (indice < 0 || ids.length === 0) return null;
    if (tecla === 'ArrowRight') return ids[(indice + 1) % ids.length];
    if (tecla === 'ArrowLeft') return ids[(indice - 1 + ids.length) % ids.length];
    if (tecla === 'Home') return ids[0];
    if (tecla === 'End') return ids[ids.length - 1];
    return null;
};

/** Mueve un elemento una posición arriba (-1) o abajo (+1). Fuera de rango, no cambia nada. */
export const mover = (lista, indice, delta) => {
    const destino = indice + delta;
    const copia = [...lista];
    if (indice < 0 || indice >= lista.length || destino < 0 || destino >= lista.length) return copia;
    [copia[indice], copia[destino]] = [copia[destino], copia[indice]];
    return copia;
};

/** Requisitos activos de una versión y, si los hay, cuántos inactivos tiene: "206" o "206 · 1 inactivo". */
export const textoRequisitosVersion = (version) => {
    const activos = version.requisitos_activos ?? version.requisitos;
    const inactivos = Math.max((version.requisitos ?? activos) - activos, 0);
    if (inactivos === 0) return String(activos);
    return `${activos} · ${inactivos} ${inactivos === 1 ? 'inactivo' : 'inactivos'}`;
};

export const etiquetaVersion = (version) => {
    if (!version) return '—';
    if (version.estado === 'borrador') return `v${version.numero} · borrador`;
    return version.vigente ? `v${version.numero} · vigente` : `v${version.numero}`;
};

/** "v1 vigente · v2 en borrador" o "Sin versión publicada · v1 en borrador". */
export const resumenVersiones = (plantilla) => [
    plantilla.vigente ? `v${plantilla.vigente.numero} vigente` : 'Sin versión publicada',
    plantilla.borrador ? `v${plantilla.borrador.numero} en borrador` : null,
].filter(Boolean).join(' · ');

/** La versión que se edita: el borrador si existe; si no, la vigente en solo lectura. */
export const versionDeTrabajo = (plantilla) => {
    const versiones = plantilla?.versiones ?? [];
    return versiones.find((v) => v.estado === 'borrador') ?? versiones.find((v) => v.vigente) ?? null;
};

export const filtrarPlantillas = (plantillas, { soloActivas }) =>
    plantillas.filter((p) => !soloActivas || p.activo);

/** Texto que deja el duplicado del SGR cuando una condición del evaluador no se puede traducir. */
export const MARCA_CONDICION_ORIGINAL = 'Condición original:';

/** Minúsculas y sin tildes, como Str::lower(Str::ascii()) del servidor. */
const normalizar = (texto) => String(texto ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const MARCA_NORMALIZADA = normalizar(MARCA_CONDICION_ORIGINAL);

/** Condicionado cuya descripción aún es la condición del evaluador SGR: falta redactarla en palabras. */
export const porRedactar = (requisito) =>
    requisito?.caracter === CONDICIONADO
    && normalizar(requisito.condicion_descripcion).includes(MARCA_NORMALIZADA);

/** Mensaje en español de un error de la API. Reglas de negocio y validación (422) van a mensajeError. */
export const mensajeApi = (error) => {
    const estado = error?.response?.status;
    if (estado === 401) return 'Tu sesión expiró. Vuelve a iniciar sesión.';
    if (estado === 403) return 'No tienes permiso para esta acción.';
    if (estado === 404) return 'El registro ya no existe.';
    if (estado === 429) return 'Demasiadas solicitudes. Espera un momento e inténtalo de nuevo.';
    if (estado >= 500) return 'Error del servidor. Inténtalo de nuevo.';
    if (!error?.response) {
        if (error?.request || error?.isAxiosError) return 'No hay conexión con el servidor.';
        return 'Ocurrió un error inesperado.';
    }
    return mensajeError(error);
};

/** El mensaje y la lista de un 422 de reglas de negocio ({ mensaje, errores }); null si el error es de otra clase. */
export const errorConLista = (error) => {
    const datos = error?.response?.data;
    if (error?.response?.status !== 422 || !datos?.mensaje || !Array.isArray(datos.errores) || datos.errores.length === 0) return null;
    return { mensaje: datos.mensaje, errores: datos.errores };
};

export const escaparHtml = (texto) => String(texto ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

/** HTML para Swal: el mensaje y, si hay, la lista de errores (escapada y limitada a `maximo`). */
export const htmlErrores = (mensaje, errores = [], maximo = 20) => {
    const visibles = errores.slice(0, maximo).map((e) => `<li>${escaparHtml(e)}</li>`);
    if (errores.length > maximo) visibles.push(`<li>y ${errores.length - maximo} más</li>`);
    return `<p>${escaparHtml(mensaje)}</p>${visibles.length ? `<ul style="text-align:left">${visibles.join('')}</ul>` : ''}`;
};
