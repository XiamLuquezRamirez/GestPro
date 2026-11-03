// Lógica de presentación del expediente de viabilización SGR.
// Funciones puras, probadas en expedienteChecklist.test.js. Qué requisitos aplican
// lo decide siempre el backend (EvaluadorRequisitos); aquí solo se organizan.

export const SECCIONES = {
    A: { num: 'S.1', titulo: 'Requisitos generales', cita: 'Acuerdo 12/2024 · Subsección 1' },
    B: { num: 'S.2', titulo: 'Requisitos adicionales', cita: 'Acuerdo 12/2024 · Subsección 2' },
    D: { num: 'ESP', titulo: 'Recuperación tras desastre', cita: 'Acuerdo 12/2024 · art. 4.1.2.2.9' },
    C: { num: 'S.3', titulo: 'Requisitos sectoriales', cita: 'según el sector seleccionado' },
};

const ORDEN_SECCIONES = ['A', 'B', 'D', 'C'];

export const FASES = [
    { valor: 1, romano: 'I', corta: 'Perfil', larga: 'Perfil' },
    { valor: 2, romano: 'II', corta: 'Prefactib.', larga: 'Prefactibilidad' },
    { valor: 3, romano: 'III', corta: 'Factib.', larga: 'Factibilidad' },
];

export const ETIQUETA_FUENTE = { a12: 'A12', ot: 'OT' };

const ETIQUETA_ESTADO = { borrador: 'Borrador', radicado: 'Radicado' };

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
            total: deSeccion.length,
            cumplidos: deSeccion.filter((f) => f.cumplido).length,
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
        'Cumplido': f.cumplido ? 'Sí' : 'No',
        'Fecha de cumplimiento': soloFecha(f.fecha_cumplido),
        'Responsable': f.usuario_cumplido ?? '',
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
