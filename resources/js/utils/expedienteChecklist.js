// Lógica de presentación del expediente y su checklist.
// Funciones puras, probadas en expedienteChecklist.test.js. Qué requisitos aplican
// lo decide siempre el backend (sincronizar y EvaluadorRequisitos); aquí solo se organizan.

/** Categorías de un requisito, en el orden en que se agrupan. La administración de plantillas usa la misma lista. */
export const CATEGORIAS = [
    ['tecnico', 'Técnico'],
    ['juridico', 'Jurídico'],
    ['financiero', 'Financiero'],
    ['ambiental', 'Ambiental'],
    ['social', 'Social'],
    ['administrativo', 'Administrativo'],
    ['otro', 'Otro'],
];

const ETIQUETA_CATEGORIA = Object.fromEntries(CATEGORIAS);

const ETIQUETA_CARACTER = { obligatorio: 'Obligatorio', opcional: 'Opcional', condicionado: 'Condicionado' };

export const MOTOR_SGR = 'sgr';

export const TITULO_PROPIOS = 'Requisitos propios del proyecto';

export const SECCIONES = {
    A: { num: 'S.1', titulo: 'Requisitos generales', cita: 'Acuerdo 12/2024 · Subsección 1' },
    B: { num: 'S.2', titulo: 'Requisitos adicionales', cita: 'Acuerdo 12/2024 · Subsección 2' },
    D: { num: 'ESP', titulo: 'Recuperación tras desastre', cita: 'Acuerdo 12/2024 · art. 4.1.2.2.9' },
    C: { num: 'S.3', titulo: 'Requisitos sectoriales', cita: 'según el sector seleccionado' },
};

export const ORDEN_SECCIONES = ['A', 'B', 'D', 'C'];

export const FASES = [
    { valor: 1, romano: 'I', corta: 'Perfil', larga: 'Perfil' },
    { valor: 2, romano: 'II', corta: 'Prefactib.', larga: 'Prefactibilidad' },
    { valor: 3, romano: 'III', corta: 'Factib.', larga: 'Factibilidad' },
];

export const ETIQUETA_FUENTE = { a12: 'A12', ot: 'OT' };

const ETIQUETA_ESTADO = { borrador: 'Borrador', radicado: 'Radicado' };

/** Estado de un requisito del checklist. Lo calcula el backend. */
export const ETIQUETA_ESTADO_REQUISITO = {
    por_definir: 'Por definir',
    pendiente: 'Pendiente',
    cumplido: 'Cumplido',
    no_aplica_pendiente: 'No aplica, por aprobar',
    no_aplica: 'No aplica',
};

/** Solo se cumple lo que aplica; un "no aplica" se retira primero. */
export const puedeMarcarRequisito = (fila) => ['pendiente', 'cumplido'].includes(fila.estado);

export const puedeMarcarNoAplica = (fila) => ['pendiente', 'por_definir'].includes(fila.estado);

/** Solo una fila por definir se decide como "aplica". */
export const puedeDecidirAplica = (fila) => fila.estado === 'por_definir';

/**
 * La exigencia de aprobación del "no aplica" es de la fila, que reúne todos sus orígenes.
 * No se lee de `fila.requisito`: es solo el requisito mostrado, y en un propio es null.
 */
export const requiereAprobacionNoAplica = (fila) => Boolean(fila?.requiere_aprobacion_no_aplica);

/**
 * Igual que el backend: revisa otra persona, salvo un Administrador, que puede revisar
 * lo que él mismo marcó.
 * No mira el rol ni el estado del expediente: la pantalla solo la usa cuando el expediente es editable.
 */
export const puedeRevisarNoAplica = (fila, usuario) =>
    Boolean(usuario)
    && fila.estado === 'no_aplica_pendiente'
    && (usuario.rol === 'Administrador' || fila.no_aplica?.usuario_id !== usuario.id);

/**
 * Acciones de aplicabilidad de una fila según su estado, en el orden en que se muestran:
 * 'aplica', 'no_aplica', 'retirar_no_aplica', 'aprobar' y 'rechazar'.
 * Como puedeRevisarNoAplica, no mira el rol ni el estado del expediente.
 */
export const accionesAplicabilidad = (fila, usuario) => {
    const acciones = [];
    if (puedeDecidirAplica(fila)) acciones.push('aplica');
    if (puedeMarcarNoAplica(fila)) acciones.push('no_aplica');
    if (['no_aplica_pendiente', 'no_aplica'].includes(fila.estado)) acciones.push('retirar_no_aplica');
    if (puedeRevisarNoAplica(fila, usuario)) acciones.push('aprobar', 'rechazar');
    return acciones;
};

/** Responsables que se pueden elegir: los activos y el que la fila ya tiene. */
export const responsablesDisponibles = (responsables, actualId) =>
    (responsables ?? []).filter((r) => r.activo || r.id === actualId);

const EVENTOS = {
    cumplido_marcado: 'Marcó como cumplido',
    cumplido_desmarcado: 'Desmarcó el cumplimiento',
    observacion_cambiada: 'Cambió la observación',
    responsable_cambiado: 'Cambió el responsable',
    soporte_subido: 'Adjuntó un soporte',
    soporte_reemplazado: 'Reemplazó el soporte',
    soporte_eliminado: 'Eliminó el soporte',
    no_aplica_marcado: 'Indicó que no aplica',
    no_aplica_retirado: 'Retiró el "no aplica"',
    no_aplica_aprobado: 'Aprobó el "no aplica"',
    no_aplica_rechazado: 'Rechazó el "no aplica"',
    aplicabilidad_decidida: 'Indicó que aplica',
    requisito_propio_agregado: 'Agregó el requisito propio',
    requisito_propio_editado: 'Editó el requisito propio',
    requisito_propio_eliminado: 'Eliminó el requisito propio',
};

const CAMPOS_PROPIO = { titulo: 'título', descripcion: 'descripción', categoria: 'categoría', caracter: 'carácter' };

/** "título", "título y carácter", "título, categoría y carácter". */
const enumerar = (items) => (items.length <= 1 ? items.join('') : `${items.slice(0, -1).join(', ')} y ${items[items.length - 1]}`);

/** Texto de una entrada del historial de un requisito. */
export const describirEvento = (entrada) => {
    const base = EVENTOS[entrada.evento] ?? entrada.evento;
    const d = entrada.despues ?? {};
    const a = entrada.antes ?? {};
    switch (entrada.evento) {
        case 'observacion_cambiada':
            return d.observacion ? `${base}: "${d.observacion}"` : `${base}: la quitó`;
        case 'responsable_cambiado':
            return d.responsable ? `${base}: ${d.responsable}` : `${base}: lo quitó (era ${a.responsable ?? '—'})`;
        case 'soporte_subido':
            return `${base}: ${d.soporte_nombre ?? 'soporte'}`;
        case 'soporte_reemplazado':
            return `${base}: ${a.soporte_nombre ?? 'soporte'} por ${d.soporte_nombre ?? 'soporte'}`;
        case 'soporte_eliminado':
            return `${base}: ${a.soporte_nombre ?? 'soporte'}`;
        case 'no_aplica_marcado':
            return `${base}: ${d.justificacion ?? '—'}`;
        case 'no_aplica_aprobado':
            return d.autoaprobado ? `${base} que él mismo marcó` : base;
        case 'no_aplica_rechazado':
            return d.motivo ? `${base}: ${d.motivo}` : base;
        case 'requisito_propio_agregado':
            return d.titulo ? `${base}: ${d.titulo}` : base;
        case 'requisito_propio_editado': {
            const campos = Object.keys(d).filter((c) => CAMPOS_PROPIO[c]).map((c) => CAMPOS_PROPIO[c]);
            return campos.length ? `${base}: ${enumerar(campos)}` : base;
        }
        case 'requisito_propio_eliminado':
            return a.titulo ? `${base}: ${a.titulo}` : base;
        default:
            return base;
    }
};

/** Agrupa consecutivamente: el backend ya entrega las filas en el orden del catálogo. */
const agruparConsecutivos = (items, claveDe, crear) => {
    const grupos = [];
    let actual = null;
    let claveActual;
    items.forEach((item) => {
        const clave = claveDe(item);
        if (!actual || clave !== claveActual) {
            actual = crear(item);
            claveActual = clave;
            grupos.push(actual);
        }
        actual.items.push(item);
    });
    return grupos;
};

/**
 * Sección → sector → subtítulo. La sección C es la única con sector; las demás
 * forman un único grupo con sector null.
 */
export const agruparRequisitos = (filas) =>
    ORDEN_SECCIONES.flatMap((clave) => {
        const deSeccion = filas.filter((f) => f.requisito.seccion === clave);
        if (deSeccion.length === 0) return [];

        const grupos = agruparConsecutivos(
            deSeccion,
            (f) => f.requisito.sector?.id ?? null,
            (f) => ({ sector: f.requisito.sector ?? null, items: [] })
        ).map(({ sector, items }) => ({
            sector,
            subgrupos: agruparConsecutivos(
                items,
                (f) => f.requisito.subtitulo ?? null,
                (f) => ({ subtitulo: f.requisito.subtitulo ?? null, items: [] })
            ).map(({ subtitulo, items: filasSub }) => ({ subtitulo, filas: filasSub })),
        }));

        return [{
            clave,
            ...SECCIONES[clave],
            // Lo que cuenta lo decide el backend: lo no aplicable y lo opcional no suman.
            total: deSeccion.filter((f) => f.cuenta).length,
            cumplidos: deSeccion.filter((f) => f.cuenta && f.estado === 'cumplido').length,
            grupos,
        }];
    });

/** Lo que cuenta lo decide el backend: lo no aplicable y lo opcional no suman. */
const conteo = (filas) => ({
    total: filas.filter((f) => f.cuenta).length,
    cumplidos: filas.filter((f) => f.cuenta && f.estado === 'cumplido').length,
});

export const esPropio = (fila) => Boolean(fila.propio);

/** Clave de una categoría conocida, o null: lo desconocido va a "Sin categoría". */
const categoriaDe = (fila) => {
    const valor = fila.categoria ?? fila.propio?.categoria ?? fila.requisito?.categoria ?? null;
    return ETIQUETA_CATEGORIA[valor] ? valor : null;
};

export const etiquetaCategoriaFila = (fila) => ETIQUETA_CATEGORIA[categoriaDe(fila)] ?? null;

/** Filas de una plantilla simple por categoría, en el orden de CATEGORIAS y "Sin categoría" al final. */
const agruparPorCategoria = (filas) =>
    [...CATEGORIAS, [null, 'Sin categoría']]
        .map(([clave, titulo]) => {
            const deCategoria = filas.filter((f) => categoriaDe(f) === clave);
            return { clave: clave ?? 'sin', titulo, ...conteo(deCategoria), filas: deCategoria };
        })
        .filter((c) => c.filas.length > 0);

/**
 * Bloques del checklist, en el orden de las filas (el del backend: la principal primero):
 * - `sgr`: una plantilla con motor sgr, por sección como siempre (agruparRequisitos);
 * - `simple`: otra plantilla, por categoría;
 * - `propios`: los requisitos propios, siempre al final.
 * La plantilla de una fila es la de su primer origen, la que muestra el backend.
 */
export const agruparChecklist = (filas) => {
    const porPlantilla = new Map();
    filas.filter((f) => !esPropio(f)).forEach((f) => {
        const clave = f.plantilla?.id ?? 'sin-plantilla';
        if (!porPlantilla.has(clave)) porPlantilla.set(clave, { plantilla: f.plantilla ?? null, filas: [] });
        porPlantilla.get(clave).filas.push(f);
    });

    const bloques = [...porPlantilla.entries()].map(([clave, { plantilla, filas: dePlantilla }]) => (
        plantilla?.motor === MOTOR_SGR
            ? { tipo: 'sgr', clave: `plantilla-${clave}`, plantilla, ...conteo(dePlantilla), secciones: agruparRequisitos(dePlantilla) }
            : { tipo: 'simple', clave: `plantilla-${clave}`, plantilla, ...conteo(dePlantilla), categorias: agruparPorCategoria(dePlantilla) }
    ));

    const propios = filas.filter(esPropio);
    if (propios.length > 0) {
        bloques.push({ tipo: 'propios', clave: 'propios', titulo: TITULO_PROPIOS, ...conteo(propios), filas: propios });
    }
    return bloques;
};

/** Lo que se muestra de una fila, sea de plantilla o propia. Un propio no tiene código ni fuente. */
export const contenidoFila = (fila) => {
    const r = fila.requisito;
    if (esPropio(fila) || !r) {
        return {
            propio: true,
            codigo: null,
            fuente: null,
            texto: fila.propio?.titulo ?? '',
            nota: fila.propio?.descripcion ?? null,
            categoria: etiquetaCategoriaFila(fila),
            cargoRevision: null,
            cargoCumplimiento: null,
            descripcion: null,
            aplicaSi: null,
            soporteRequerido: null,
        };
    }
    // Cuándo aplica, en palabras: solo rige a un condicionado (o a una fila aún por definir).
    const condicionado = fila.caracter === 'condicionado' || fila.estado === 'por_definir';
    return {
        propio: false,
        codigo: r.codigo ?? null,
        fuente: ETIQUETA_FUENTE[r.fuente] ?? null,
        texto: r.texto ?? '',
        nota: r.nota ?? null,
        categoria: etiquetaCategoriaFila(fila),
        cargoRevision: r.cargo_revision ?? null,
        cargoCumplimiento: r.cargo_cumplimiento ?? null,
        descripcion: r.descripcion ?? null,
        aplicaSi: condicionado ? (r.condicion_descripcion ?? null) : null,
        soporteRequerido: r.soporte_requerido ?? null,
    };
};

/** Texto del recuadro "Por definir": cuándo aplica o, si la plantilla no lo dice, que falta decidirlo. */
export const textoPorDefinir = (fila) => {
    const { aplicaSi } = contenidoFila(fila);
    return aplicaSi ? `Aplica si: ${aplicaSi}` : 'Falta decidir si este requisito aplica al proyecto.';
};

/**
 * Lista de expedientes del proyecto (GET /proyectos/{id}/expedientes) con uno de ellos al día:
 * toma de la respuesta del expediente solo los campos que la lista ya tiene, como sus plantillas.
 */
export const actualizarExpedienteEnLista = (lista, expediente) => lista?.map((e) => (
    e.id === expediente.id
        ? { ...e, ...Object.fromEntries(Object.keys(e).filter((k) => k in expediente).map((k) => [k, expediente[k]])) }
        : e
));

/** "Plantillas: SGR, Obras de agua", "Plantilla: SGR" o "Sin plantillas". Acepta nombres u objetos con `nombre`. */
export const textoPlantillas = (plantillas) => {
    const nombres = (plantillas ?? []).map((p) => (typeof p === 'string' ? p : p.nombre)).filter(Boolean);
    if (nombres.length === 0) return 'Sin plantillas';
    return `${nombres.length === 1 ? 'Plantilla' : 'Plantillas'}: ${nombres.join(', ')}`;
};

/**
 * Textos del aviso de requisitos parecidos entre plantillas. Cada aviso es un par de filas; el
 * backend manda los más parecidos, hasta un tope, y en `total` cuántos pares hay en realidad.
 * - `titulo`: "1 par de requisitos se parece", "3 pares de requisitos se parecen" o, con tope,
 *   "73 pares de requisitos se parecen; se muestran los 50 más parecidos".
 * - `resumen`: el texto que despliega la lista.
 * Sin avisos devuelve null.
 */
export const textoAvisosDuplicados = (avisos, total) => {
    const mostrados = avisos?.length ?? 0;
    if (mostrados === 0) return null;
    const pares = Math.max(Number(total) || 0, mostrados);
    const titulo = pares === 1 ? '1 par de requisitos se parece' : `${pares} pares de requisitos se parecen`;
    return {
        titulo: pares > mostrados ? `${titulo}; se muestran los ${mostrados} más parecidos` : titulo,
        resumen: mostrados === 1 ? 'Ver el par' : `Ver los ${mostrados} pares`,
    };
};

/** Con más de un origen, las demás plantillas de las que viene la fila: "También en: Vías, Riego". */
export const textoOrigenes = (fila) => {
    const origenes = fila.origenes ?? [];
    if (origenes.length < 2) return null;
    const mostrada = origenes[0].plantilla_id;
    const otras = [...new Set(origenes.filter((o) => o.plantilla_id !== mostrada).map((o) => o.nombre))];
    return otras.length ? `También en: ${otras.join(', ')}` : null;
};

export const CARACTERES_PROPIO = [['obligatorio', 'Obligatorio'], ['opcional', 'Opcional']];

/** Formulario de un requisito propio: vacío para uno nuevo, o con los datos de la fila. */
export const formularioPropio = (fila) => ({
    titulo: fila?.propio?.titulo ?? '',
    descripcion: fila?.propio?.descripcion ?? '',
    categoria: fila?.propio?.categoria ?? '',
    caracter: fila?.caracter === 'opcional' ? 'opcional' : 'obligatorio',
});

/** Lo que espera la API: textos recortados y lo vacío como null. */
export const datosPropio = (formulario) => ({
    titulo: formulario.titulo.trim(),
    descripcion: formulario.descripcion.trim() || null,
    categoria: formulario.categoria || null,
    caracter: formulario.caracter,
});

/** Igual que el backend: un propio cumplido o con soporte no se elimina. Null si se puede. */
export const motivoNoEliminarPropio = (fila) => {
    if (fila.cumplido) return 'El requisito está cumplido: desmárcalo antes de eliminarlo.';
    if (fila.soporte) return 'El requisito tiene un soporte: quítalo antes de eliminarlo.';
    return null;
};

/**
 * Configuración del expediente en el formato que espera la API. Lleva siempre las plantillas
 * (la principal primero, como las entrega el backend) y la configuración SGR, aunque no haya
 * plantilla sgr: así no se pierde si se quita y se vuelve a poner.
 */
export const seleccionDesde = (expediente) => ({
    plantilla_ids: (expediente.plantillas ?? []).map((p) => p.plantilla_id),
    principal_id: (expediente.plantillas ?? []).find((p) => p.es_principal)?.plantilla_id ?? null,
    fase_sgr: expediente.fase_sgr,
    sector_ids: expediente.configuracion.sectores.map((s) => s.id),
    tipo_ids: expediente.configuracion.tipos.map((t) => t.id),
    componente_ids: expediente.configuracion.componentes.map((c) => c.id),
    tipologia_ids: expediente.configuracion.tipologias.map((t) => t.id),
});

export const alternarId = (lista, id) => (lista.includes(id) ? lista.filter((x) => x !== id) : [...lista, id]);

/**
 * Marca o desmarca un sector. Al desmarcarlo se quitan sus tipos: el backend
 * rechaza un tipo cuyo sector no está seleccionado.
 */
export const alternarSector = (seleccion, sectorId, catalogo) => {
    const sector_ids = alternarId(seleccion.sector_ids, sectorId);
    if (sector_ids.includes(sectorId)) {
        return { ...seleccion, sector_ids };
    }
    const delSector = (catalogo.sectores.find((s) => s.id === sectorId)?.tipos ?? []).map((t) => t.id);
    return { ...seleccion, sector_ids, tipo_ids: seleccion.tipo_ids.filter((id) => !delSector.includes(id)) };
};

/**
 * Sectores marcados sin ningún tipo elegido, pudiendo acotarse: cargan todos sus
 * requisitos, incluidos los que no le apliquen al proyecto, y eso distorsiona el avance.
 */
export const sectoresSinTipo = (seleccion, catalogo) =>
    catalogo.sectores
        .filter((s) => seleccion.sector_ids.includes(s.id))
        .filter((s) => (s.tipos ?? []).length > 0 && !s.tipos.some((t) => seleccion.tipo_ids.includes(t.id)))
        .map((s) => s.nombre);

/** Igual que el backend: solo se radica con todo lo que aplica cumplido. */
export const puedeRadicar = ({ total, cumplidos }) => total > 0 && cumplidos === total;

export const sectoresConFuenteA12 =(sectores) => sectores.filter((s) => s.fuente).map((s) => s.nombre);

/** Mensaje legible de un error de la API (regla de negocio o validación). */
export const mensajeError = (error) => {
    const datos = error?.response?.data;
    if (datos?.mensaje) {
        return [datos.mensaje, ...(datos.errores ?? [])].join(' ');
    }
    if (datos?.errors) {
        return Object.values(datos.errors).flat().join(' ');
    }
    return datos?.message || error?.message || 'Ocurrió un error inesperado.';
};

export const faseLegible = (valor) => {
    const fase = FASES.find((f) => f.valor === valor);
    return fase ? `${fase.romano} — ${fase.larga}` : '—';
};

const lista = (items) => (items.length ? items.map((i) => i.nombre).join('; ') : '—');
const soloFecha = (iso) => (iso ? String(iso).slice(0, 10) : '');

/** "SGR (v1), Obras de agua (v2)" o "Sin plantillas". */
const plantillasConVersion = (plantillas) => (
    (plantillas ?? []).length
        ? plantillas.map((pl) => (pl.version ? `${pl.nombre} (v${pl.version.numero})` : pl.nombre)).join(', ')
        : 'Sin plantillas'
);

/**
 * Filas de las dos hojas del Excel: resumen del expediente y checklist. La fase y la
 * configuración SGR solo van si el expediente tiene una plantilla con motor sgr.
 */
export const construirHojasExcel = (expediente) => {
    const { configuracion: c, progreso: p } = expediente;

    const resumen = [
        ['Proyecto', expediente.proyecto.nombre],
        ['Expediente', `#${expediente.id}`],
        ['Estado', ETIQUETA_ESTADO[expediente.estado] ?? expediente.estado],
        ['Fecha de radicación', soloFecha(expediente.fecha_radicado) || '—'],
        ['Plantillas', plantillasConVersion(expediente.plantillas)],
        ...(expediente.tiene_sgr ? [
            ['Fase SGR', faseLegible(expediente.fase_sgr)],
            ['Sectores', lista(c.sectores)],
            ['Tipos de proyecto', lista(c.tipos)],
            ['Componentes', lista(c.componentes)],
            ['Tipologías', lista(c.tipologias)],
        ] : []),
        ['Avance', `${p.cumplidos} de ${p.total} (${p.porcentaje}%)`],
    ].map(([Campo, Valor]) => ({ Campo, Valor }));

    const checklist = expediente.requisitos.map((f) => {
        const r = f.requisito;
        const contenido = contenidoFila(f);
        // La sección solo es del motor sgr; una plantilla simple copiada de la SGR puede conservarla.
        const seccion = !f.plantilla || f.plantilla.motor === MOTOR_SGR ? SECCIONES[r?.seccion] : null;
        return {
            'Plantilla': contenido.propio ? 'Propio del proyecto' : (f.plantilla?.nombre ?? ''),
            'Sección': seccion ? `${seccion.num} ${seccion.titulo}` : '',
            'Categoría': contenido.categoria ?? '',
            'Sector': r?.sector?.nombre ?? '',
            'Tipo de proyecto': r?.tipo?.nombre ?? '',
            'Subtítulo': r?.subtitulo ?? '',
            'Código': contenido.codigo ?? '',
            'Fuente': contenido.fuente ?? '',
            'Requisito': contenido.texto,
            'Nota': contenido.nota ?? '',
            'Carácter': ETIQUETA_CARACTER[f.caracter] ?? '',
            'Estado': ETIQUETA_ESTADO_REQUISITO[f.estado] ?? f.estado,
            'Cumplido': f.cumplido ? 'Sí' : 'No',
            'Fecha de cumplimiento': soloFecha(f.fecha_cumplido),
            'Marcado por': f.usuario_cumplido ?? '',
            'Responsable': f.responsable?.nombre ?? '',
            'Justificación de no aplica': f.no_aplica?.justificacion ?? '',
            'Aprobado por': f.no_aplica?.aprobado_por ?? '',
            'Observación': f.observacion ?? '',
            'Soporte': f.soporte?.nombre ?? '',
        };
    });

    return { resumen, checklist };
};

/** Ancho de cada columna de la hoja de checklist, en el orden de construirHojasExcel (20 columnas). */
export const ANCHOS_HOJA_CHECKLIST = [24, 28, 14, 26, 30, 30, 14, 8, 80, 40, 14, 18, 10, 14, 22, 22, 40, 22, 40, 26];

export const nombreArchivoExcel = (expediente) => {
    const proyecto = expediente.proyecto.nombre
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^A-Za-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 60);
    return `expediente-${expediente.id}-${proyecto}.xlsx`;
};
