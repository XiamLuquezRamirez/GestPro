// Lógica de presentación de la administración de plantillas de checklist.
// Funciones puras, probadas en plantillasChecklist.test.js. Las reglas las valida el backend.

import { ORDEN_SECCIONES, SECCIONES } from './expedienteChecklist';

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

export const CARACTERES = [
    ['obligatorio', 'Obligatorio'],
    ['opcional', 'Opcional'],
    ['condicionado', 'Condicionado'],
];

export const ALCANCES = [
    ['general', 'General'],
    ['especifica', 'Específica'],
];

const CONDICIONADO = CARACTERES.find(([v]) => v === 'condicionado')[0];

const etiqueta = (lista, valor) => lista.find(([v]) => v === valor)?.[1] ?? '';

export const etiquetaCategoria = (valor) => etiqueta(CATEGORIAS, valor);
export const etiquetaTipo = (valor) => etiqueta(TIPOS_REQUISITO, valor);
export const etiquetaCaracter = (valor) => etiqueta(CARACTERES, valor);
export const etiquetaAlcance = (valor) => etiqueta(ALCANCES, valor);

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
    const valor = (campo) => requisito[campo] ?? base[campo];
    return {
        ...base,
        ...Object.fromEntries(CAMPOS_TEXTO.map((c) => [c, valor(c)])),
        caracter: valor('caracter'),
        requiere_aprobacion_no_aplica: Boolean(valor('requiere_aprobacion_no_aplica')),
        activo: valor('activo'),
        sector_id: valor('sector_id'),
        tipo_id: valor('tipo_id'),
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

/** Mueve un elemento una posición arriba (-1) o abajo (+1). Fuera de rango, no cambia nada. */
export const mover = (lista, indice, delta) => {
    const destino = indice + delta;
    const copia = [...lista];
    if (indice < 0 || indice >= lista.length || destino < 0 || destino >= lista.length) return copia;
    [copia[indice], copia[destino]] = [copia[destino], copia[indice]];
    return copia;
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
