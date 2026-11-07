// Lógica de presentación del expediente de viabilización SGR.
// Funciones puras, probadas en expedienteChecklist.test.js. Qué requisitos aplican
// lo decide siempre el backend (EvaluadorRequisitos); aquí solo se organizan.

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

/**
 * Igual que el backend: revisa otra persona, salvo un Administrador, que puede revisar
 * lo que él mismo marcó.
 * No mira el rol ni el estado del expediente: la pantalla solo la usa cuando el expediente es editable.
 */
export const puedeRevisarNoAplica = (fila, usuario) =>
    Boolean(usuario)
    && fila.estado === 'no_aplica_pendiente'
    && (usuario.rol === 'Administrador' || fila.no_aplica?.usuario_id !== usuario.id);

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
};

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

/** Configuración del expediente en el formato que espera la API. */
export const seleccionDesde = (expediente) => ({
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

/** Filas de las dos hojas del Excel: resumen del expediente y checklist. */
export const construirHojasExcel = (expediente) => {
    const { configuracion: c, progreso: p } = expediente;

    const resumen = [
        ['Proyecto', expediente.proyecto.nombre],
        ['Expediente', `#${expediente.id}`],
        ['Estado', ETIQUETA_ESTADO[expediente.estado] ?? expediente.estado],
        ['Fecha de radicación', soloFecha(expediente.fecha_radicado) || '—'],
        ['Versión del catálogo', `v${expediente.catalogo_version.numero}`],
        ['Fase SGR', faseLegible(expediente.fase_sgr)],
        ['Sectores', lista(c.sectores)],
        ['Tipos de proyecto', lista(c.tipos)],
        ['Componentes', lista(c.componentes)],
        ['Tipologías', lista(c.tipologias)],
        ['Avance', `${p.cumplidos} de ${p.total} (${p.porcentaje}%)`],
    ].map(([Campo, Valor]) => ({ Campo, Valor }));

    const checklist = expediente.requisitos.map(({ requisito: r, ...f }) => ({
        'Sección': `${SECCIONES[r.seccion].num} ${SECCIONES[r.seccion].titulo}`,
        'Sector': r.sector?.nombre ?? '',
        'Tipo de proyecto': r.tipo?.nombre ?? '',
        'Subtítulo': r.subtitulo ?? '',
        'Código': r.codigo,
        'Fuente': ETIQUETA_FUENTE[r.fuente] ?? r.fuente,
        'Requisito': r.texto,
        'Nota': r.nota ?? '',
        'Estado': ETIQUETA_ESTADO_REQUISITO[f.estado] ?? f.estado,
        'Cumplido': f.cumplido ? 'Sí' : 'No',
        'Fecha de cumplimiento': soloFecha(f.fecha_cumplido),
        'Marcado por': f.usuario_cumplido ?? '',
        'Responsable': f.responsable?.nombre ?? '',
        'Justificación de no aplica': f.no_aplica?.justificacion ?? '',
        'Aprobado por': f.no_aplica?.aprobado_por ?? '',
        'Observación': f.observacion ?? '',
        'Soporte': f.soporte?.nombre ?? '',
    }));

    return { resumen, checklist };
};

export const nombreArchivoExcel = (expediente) => {
    const proyecto = expediente.proyecto.nombre
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^A-Za-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 60);
    return `expediente-${expediente.id}-${proyecto}.xlsx`;
};
