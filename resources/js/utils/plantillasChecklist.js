// Lógica de presentación de la administración de plantillas de checklist.
// Funciones puras, probadas en plantillasChecklist.test.js. Las reglas las valida el backend.

import { CATEGORIAS, ORDEN_SECCIONES, SECCIONES, mensajeError, textoResponsable } from './expedienteChecklist';
import { RUTA_PLANTILLAS } from './navegacion';

// La lista vive en expedienteChecklist.js porque el checklist del expediente también agrupa por categoría.
export { CATEGORIAS };

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

// Los valores son los de PlantillaChecklist::USOS (plantillasChecklist.test.js lo comprueba).
export const USO_VIABILIZACION = 'viabilizacion';
export const USO_CONTRATACION = 'contratacion';

/** Usos de una plantilla, en el orden del selector de la lista. El uso se elige al crear y no cambia. */
export const USOS = [
    [USO_VIABILIZACION, 'Viabilización'],
    [USO_CONTRATACION, 'Contratación'],
];

const etiqueta = (lista, valor) => lista.find(([v]) => v === valor)?.[1] ?? '';

export const etiquetaCategoria = (valor) => etiqueta(CATEGORIAS, valor);
export const etiquetaTipo = (valor) => etiqueta(TIPOS_REQUISITO, valor);
export const etiquetaCaracter = (valor) => etiqueta(CARACTERES, valor);
export const etiquetaAlcance = (valor) => etiqueta(ALCANCES, valor);
export const etiquetaFuenteNormativa = (valor) => etiqueta(FUENTES_NORMATIVAS, valor);
export const etiquetaUso = (valor) => etiqueta(USOS, valor);

/** Una plantilla sin uso es de viabilización: así quedaron todas las anteriores a la contratación. */
export const esContratacion = (plantilla) => plantilla?.uso === USO_CONTRATACION;
export const usoDePlantilla = (plantilla) => (esContratacion(plantilla) ? USO_CONTRATACION : USO_VIABILIZACION);

/** El uso que pide la URL de la lista (`?uso=`); sin él o con un valor desconocido, viabilización. */
export const usoDeConsulta = (valor) => (USOS.some(([v]) => v === valor) ? valor : USO_VIABILIZACION);

/** La ruta de la lista en la pestaña de un uso: la de viabilización es la de siempre, sin parámetro. */
export const rutaListaPlantillas = (uso) => (uso === USO_CONTRATACION ? `${RUTA_PLANTILLAS}?uso=${USO_CONTRATACION}` : RUTA_PLANTILLAS);

/**
 * Lo que se envía al crear una plantilla desde la lista: el uso de la pestaña activa, para todas
 * las fuentes y sectores. Una de contratación nace sin modalidades: se eligen en el detalle y
 * publicar las exige, para que no se ofrezca a todas sin que nadie lo haya decidido.
 */
export const datosNuevaPlantilla = (nombre, uso) => ({
    nombre: String(nombre ?? '').trim(),
    uso,
    todas_fuentes: true,
    todos_sectores: true,
    ...(uso === USO_CONTRATACION ? { todas_modalidades: false, modalidad_ids: [] } : {}),
});

/** "Todas las fuentes" o los nombres separados por coma. */
export const textoAsociaciones = (todas, items, textoTodas) =>
    todas ? textoTodas : (items.length ? items.map((i) => i.nombre).join(', ') : '—');

/** Las modalidades de una plantilla de contratación en la lista; las inactivas, marcadas. */
export const textoModalidades = (plantilla) => {
    if (plantilla?.todas_modalidades) return 'Todas las modalidades';
    const modalidades = plantilla?.modalidades ?? [];
    if (!modalidades.length) return 'Sin modalidades';
    return modalidades.map((m) => (m.activo === false ? `${m.nombre} (inactiva)` : m.nombre)).join(', ');
};

export const PLANTILLA_VACIA = {
    nombre: '',
    descripcion: '',
    alcance: '',
    todas_fuentes: false,
    todos_sectores: true,
    fuente_ids: [],
    sector_ids: [],
    todas_modalidades: false,
    modalidad_ids: [],
};

/** El formulario de General. Las modalidades solo se usan en una plantilla de contratación. */
export const formularioPlantilla = (plantilla) => ({
    nombre: plantilla.nombre ?? '',
    descripcion: plantilla.descripcion ?? '',
    alcance: plantilla.alcance ?? '',
    todas_fuentes: Boolean(plantilla.todas_fuentes),
    todos_sectores: Boolean(plantilla.todos_sectores),
    fuente_ids: (plantilla.fuentes ?? []).map((f) => f.id),
    sector_ids: (plantilla.sectores ?? []).map((s) => s.id),
    todas_modalidades: Boolean(plantilla.todas_modalidades),
    modalidad_ids: (plantilla.modalidades ?? []).map((m) => m.id),
});

/**
 * Lo que espera la API: con "todas", la lista se envía vacía. Las modalidades solo viajan en una
 * plantilla de contratación (`uso`): la de viabilización las rechaza. El uso no se envía: no cambia.
 */
export const datosPlantilla = (formulario, uso = USO_VIABILIZACION) => ({
    nombre: formulario.nombre.trim(),
    descripcion: formulario.descripcion.trim() || null,
    alcance: formulario.alcance || null,
    todas_fuentes: formulario.todas_fuentes,
    todos_sectores: formulario.todos_sectores,
    fuente_ids: formulario.todas_fuentes ? [] : formulario.fuente_ids,
    sector_ids: formulario.todos_sectores ? [] : formulario.sector_ids,
    ...(uso === USO_CONTRATACION ? {
        todas_modalidades: Boolean(formulario.todas_modalidades),
        modalidad_ids: formulario.todas_modalidades ? [] : (formulario.modalidad_ids ?? []),
    } : {}),
});

/**
 * Lo que se envía al guardar. La plantilla SGR siempre aplica a todos los sectores y conserva la
 * fuente SGR (salvo que ya sea para todas las fuentes): un dato inconsistente no debe llegar como 422.
 */
export const datosGuardarPlantilla = (formulario, plantilla, fuentes = []) => {
    if (!plantilla?.es_sgr) return datosPlantilla(formulario, plantilla?.uso);
    const idSgr = fuentes.find((f) => f.clave === 'sgr')?.id;
    const conSgr = idSgr === undefined || formulario.todas_fuentes || formulario.fuente_ids.includes(idSgr)
        ? formulario.fuente_ids
        : [...formulario.fuente_ids, idSgr];
    return datosPlantilla({ ...formulario, todos_sectores: true, fuente_ids: conSgr }, plantilla.uso);
};

/** Opciones de un catálogo: las activas y las que la plantilla ya tiene. */
export const opcionesCatalogo = (items, elegidos) => items.filter((i) => i.activo || elegidos.includes(i.id));

/**
 * El catálogo más lo que la plantilla ya tiene y el catálogo no trae (por ejemplo, porque no se
 * pudo cargar): así no desaparece una casilla marcada. Lo que falta va al final, como llega.
 */
export const catalogoConPropios = (items, propios) => {
    const lista = items ?? [];
    const faltan = (propios ?? []).filter((p) => !lista.some((i) => i.id === p.id));
    return [...lista, ...faltan];
};

const CAMPOS_TEXTO = [
    'texto', 'descripcion', 'categoria', 'tipo_requisito', 'cargo_cumplimiento', 'cargo_revision',
    'soporte_requerido', 'referencia_normativa', 'nota', 'condicion_descripcion', 'codigo_comun',
    'clave', 'codigo', 'subtitulo', 'seccion', 'fuente', 'condicion',
];

/** Valores del select de una persona del requisito que no son el id de un responsable. */
export const OPCION_SIN_ASIGNAR = '';
export const OPCION_SOLO_CARGO = 'solo-cargo';

/** Papel de cada persona del requisito: campo del id, de su cargo y del objeto que trae la API. */
const PERSONAS = [
    ['responsable_cumplimiento_id', 'cargo_cumplimiento', 'responsable_cumplimiento'],
    ['responsable_revision_id', 'cargo_revision', 'responsable_revision'],
];
// En el formulario, la persona que el requisito ya tenía: conserva su opción aunque esté inactiva.
const personaDelFormulario = (campoObjeto) => campoObjeto.replace('responsable_', 'persona_');

/**
 * Un requisito nuevo. En una plantilla de contratación (`uso`) lleva además su etapa: el id del
 * select y, en `etapa_actual`, la que el requisito ya tiene (conserva su opción aunque esté inactiva).
 */
export const requisitoVacio = (motor, uso = USO_VIABILIZACION) => ({
    ...Object.fromEntries(CAMPOS_TEXTO.map((c) => [c, ''])),
    ...Object.fromEntries(PERSONAS.flatMap(([campoId, , campoObjeto]) => [
        [campoId, OPCION_SIN_ASIGNAR], [personaDelFormulario(campoObjeto), null],
    ])),
    caracter: 'obligatorio',
    requiere_aprobacion_no_aplica: motor === 'sgr',
    activo: true,
    sector_id: '',
    tipo_id: '',
    ...(motor === 'sgr' ? { seccion: 'A', fuente: 'a12', condicion: 'siempre' } : {}),
    ...(uso === USO_CONTRATACION ? { etapa_id: '', etapa_actual: null } : {}),
});

/** La etapa que el requisito ya tiene, para el formulario; sin el objeto de la API, solo su id. */
const etapaActual = (requisito) => {
    if (requisito.etapa_id === null || requisito.etapa_id === undefined) return null;
    return requisito.etapa ?? { id: requisito.etapa_id, nombre: `Etapa ${requisito.etapa_id}` };
};

export const formularioRequisito = (requisito, motor, uso = USO_VIABILIZACION) => {
    const base = requisitoVacio(motor, uso);
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
        // El select de cada persona: su id, "Solo cargo" si el requisito tiene un cargo sin
        // persona (dato anterior) o "Sin asignar".
        ...Object.fromEntries(PERSONAS.flatMap(([campoId, campoCargo, campoObjeto]) => {
            const id = requisito[campoId];
            const eleccion = id ? String(id) : (String(requisito[campoCargo] ?? '').trim() ? OPCION_SOLO_CARGO : OPCION_SIN_ASIGNAR);
            return [[campoId, eleccion], [personaDelFormulario(campoObjeto), id ? (requisito[campoObjeto] ?? null) : null]];
        })),
        ...(uso === USO_CONTRATACION ? {
            etapa_id: requisito.etapa_id === null || requisito.etapa_id === undefined ? '' : String(requisito.etapa_id),
            etapa_actual: etapaActual(requisito),
        } : {}),
    };
};

/**
 * Opciones del select de una persona del requisito: "Sin asignar" y los responsables activos por
 * nombre, como "Nombre (Cargo)". `actual` es lo que el requisito ya tiene: su persona, si está
 * inactiva, se conserva como opción marcada; un cargo sin persona, como "Solo cargo".
 */
export const opcionesResponsable = (responsables, actual = {}) => {
    const lista = responsables ?? [];
    const etiqueta = (p) => textoResponsable(p, null);
    const opciones = lista
        .filter((p) => p.activo)
        .sort((a, b) => String(a.nombre ?? '').localeCompare(String(b.nombre ?? ''), 'es'))
        .map((p) => ({ valor: String(p.id), etiqueta: etiqueta(p) }));

    const persona = actual.persona ?? null;
    if (persona && !opciones.some((o) => o.valor === String(persona.id))) {
        const enLista = lista.find((p) => String(p.id) === String(persona.id));
        const activo = enLista ? enLista.activo : persona.activo !== false;
        opciones.push({ valor: String(persona.id), etiqueta: `${etiqueta(enLista ?? persona)}${activo ? '' : ' (inactivo)'}` });
    }
    const cargo = String(actual.cargo ?? '').trim();
    if (!persona && cargo) {
        opciones.push({ valor: OPCION_SOLO_CARGO, etiqueta: `Solo cargo: ${cargo}` });
    }
    return [{ valor: OPCION_SIN_ASIGNAR, etiqueta: 'Sin asignar' }, ...opciones];
};

/**
 * Lo que espera la API según el motor. Los textos vacíos viajan como null. Con `conPersonas`
 * (el catálogo de responsables cargó y se eligieron en selects) van los ids de las personas; sin
 * él solo viaja el cargo escrito y las personas del requisito no cambian. La etapa solo viaja en
 * una plantilla de contratación (`uso`): la de viabilización la rechaza.
 */
export const datosRequisito = (formulario, motor, conPersonas = false, uso = USO_VIABILIZACION) => {
    const texto = (campo) => (String(formulario[campo] ?? '').trim() || null);
    // Con persona, el cargo lo copia el servidor; "Solo cargo" conserva el escrito; "Sin asignar" lo vacía.
    const personas = conPersonas ? Object.fromEntries(PERSONAS.flatMap(([campoId, campoCargo]) => {
        const eleccion = String(formulario[campoId] ?? OPCION_SIN_ASIGNAR);
        if (eleccion === OPCION_SIN_ASIGNAR) return [[campoId, null], [campoCargo, null]];
        if (eleccion === OPCION_SOLO_CARGO) return [[campoId, null], [campoCargo, texto(campoCargo)]];
        return [[campoId, Number(eleccion)], [campoCargo, texto(campoCargo)]];
    })) : {};
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
        ...personas,
        ...(uso === USO_CONTRATACION ? { etapa_id: Number(formulario.etapa_id) || null } : {}),
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

/** "3. Selección" con el número de la etapa en el catálogo (su orden), o solo el nombre si no lo trae. */
export const nombreEtapa = (etapa) => (etapa?.orden ? `${etapa.orden}. ${etapa.nombre}` : String(etapa?.nombre ?? ''));

/** Clave del grupo de los requisitos de contratación que aún no tienen etapa. */
export const SIN_ETAPA = 'sin-etapa';

export const sinEtapa = (requisito) => requisito.etapa_id === null || requisito.etapa_id === undefined;

/** Los dos requisitos están en la misma etapa, o los dos sin ella. */
export const mismaEtapa = (a, b) => (a.etapa_id ?? null) === (b.etapa_id ?? null);

/**
 * Orden del catálogo de etapas, el mismo del servidor: por `orden` y, a igual orden, por id. Una
 * etapa sin `orden` (solo se conoce su id) va después de las demás.
 */
export const compararEtapas = (a, b) => (a.orden ?? Number.MAX_SAFE_INTEGER) - (b.orden ?? Number.MAX_SAFE_INTEGER) || a.id - b.id;

/**
 * Requisitos de una plantilla de contratación por etapa, en el orden del catálogo (orden y, a igual
 * orden, id) y, dentro de cada etapa, en el orden en que llegan. El título lleva el número de la
 * etapa (su orden, el de Parámetros → Etapas de contratación) y su nombre. "Sin etapa" va al final:
 * son requisitos que hay que completar antes de publicar. La etapa es la que trae cada requisito
 * de la API o, si no la trae, la de `etapas` (el catálogo) con su id.
 */
export const agruparPorEtapa = (requisitos, etapas = null) => {
    const grupos = new Map();
    const sinAsignar = [];
    (requisitos ?? []).forEach((r) => {
        if (sinEtapa(r)) {
            sinAsignar.push(r);
            return;
        }
        if (!grupos.has(r.etapa_id)) {
            const etapa = r.etapa ?? (etapas ?? []).find((e) => e.id === r.etapa_id) ?? { id: r.etapa_id, nombre: `Etapa ${r.etapa_id}` };
            grupos.set(r.etapa_id, { etapa, requisitos: [] });
        }
        grupos.get(r.etapa_id).requisitos.push(r);
    });

    const conEtapa = [...grupos.values()]
        .sort((a, b) => compararEtapas(a.etapa, b.etapa))
        .map(({ etapa, requisitos: lista }) => ({
            clave: `etapa-${etapa.id}`,
            titulo: nombreEtapa(etapa),
            inactiva: etapa.activo === false,
            requisitos: lista,
        }));
    return sinAsignar.length
        ? [...conEtapa, { clave: SIN_ETAPA, titulo: 'Sin etapa', inactiva: false, requisitos: sinAsignar }]
        : conEtapa;
};

/**
 * Vista previa: los requisitos activos como los verá el usuario. El SGR por sección,
 * en el orden del expediente; una plantilla de contratación (`uso`) por etapa; otra plantilla
 * simple, por categoría.
 */
export const agruparVistaPrevia = (requisitos, motor, uso = USO_VIABILIZACION, etapas = null) => {
    const activos = requisitos.filter((r) => r.activo).sort((a, b) => a.orden - b.orden);

    if (uso === USO_CONTRATACION) return agruparPorEtapa(activos, etapas);

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

/**
 * Las partes de la línea de datos de un requisito en la vista previa, en el orden en que se
 * muestran. Quién cumple es "Nombre (Cargo)" si la plantilla indica una persona, o el cargo.
 */
export const datosVistaPrevia = (r, motor) => {
    const cumple = textoResponsable(r.responsable_cumplimiento, r.cargo_cumplimiento);
    return [
        motor === 'simple' ? etiquetaCaracter(r.caracter) : null,
        r.tipo_requisito ? etiquetaTipo(r.tipo_requisito) : null,
        r.soporte_requerido ? `Soporte: ${r.soporte_requerido}` : null,
        cumple ? `Cumple: ${cumple}` : null,
        r.caracter === CONDICIONADO && r.condicion_descripcion && !porRedactar(r) ? `Aplica si: ${r.condicion_descripcion}` : null,
    ].filter(Boolean);
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

/**
 * Los requisitos de una versión de contratación en el orden en que se ven tras mover el requisito
 * `id` un lugar arriba (-1) o abajo (+1) dentro de su etapa: los grupos de `agruparPorEtapa`, uno
 * tras otro, con el cambio aplicado. Es lo que se envía como orden de la versión, para que el
 * `orden` guardado coincida con la lista. null si no tiene vecino en su etapa hacia ese lado.
 */
export const ordenTrasMoverEnEtapa = (requisitos, id, delta, etapas = null) => {
    const visibles = agruparPorEtapa(requisitos, etapas).flatMap((g) => g.requisitos);
    const indice = visibles.findIndex((r) => r.id === id);
    const vecino = visibles[indice + delta];
    if (indice < 0 || (delta !== -1 && delta !== 1) || !vecino || !mismaEtapa(vecino, visibles[indice])) return null;
    return mover(visibles, indice, delta);
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

/**
 * Los errores de un 422 al guardar un requisito de contratación, repartidos: `etapa`, para mostrarlos
 * bajo su campo, y `otros`, con su `mensaje`, para el aviso. Los de la etapa llegan en
 * `errors.etapa_id`, tanto de la validación de Laravel ({ message, errors }) como de una regla de
 * negocio ({ mensaje, errores, errors }), que además los repite en su lista `errores`: ahí se
 * quitan, para no avisarlos dos veces. null si no es 422.
 */
export const erroresDeEtapa = (error) => {
    if (error?.response?.status !== 422) return null;
    const datos = error.response.data ?? {};
    const { etapa_id: deEtapa = [], ...resto } = datos.errors && typeof datos.errors === 'object' ? datos.errors : {};
    const etapa = [].concat(deEtapa ?? []).map(String);
    if (Array.isArray(datos.errores) || datos.mensaje) {
        const lista = Array.isArray(datos.errores) ? datos.errores.map(String) : [];
        return { etapa, mensaje: datos.mensaje ?? 'El requisito no es válido.', otros: lista.filter((e) => !etapa.includes(e)) };
    }
    return { etapa, mensaje: 'El requisito no es válido.', otros: Object.values(resto).flat().map(String) };
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
