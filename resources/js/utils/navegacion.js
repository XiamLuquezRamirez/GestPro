// Mapa único de la aplicación: de aquí salen el riel lateral, la barra móvil y el
// buscador Ctrl+K, para que los tres ofrezcan siempre los mismos destinos.

export const GRUPO = {
    TRABAJO: 'Trabajo diario',
    ANALISIS: 'Análisis',
    CATALOGOS: 'Catálogos',
};

// `tab` es el identificador interno que ya usa Parametros.jsx.
export const SECCIONES_PARAMETROS = [
    { slug: 'proyectos', tab: 'proyectos', titulo: 'Proyectos', descripcion: 'Registro, presupuesto, ubicación y contratos de cada proyecto.', grupo: GRUPO.TRABAJO, icono: 'faFolderOpen', palabras: 'obras contratos presupuesto' },
    { slug: 'eventos', tab: 'eventos-calendario', titulo: 'Eventos', descripcion: 'Agenda de hitos y compromisos por proyecto.', grupo: GRUPO.TRABAJO, icono: 'faCalendarDays', palabras: 'agenda calendario hitos' },
    { slug: 'municipios', tab: 'municipios', titulo: 'Municipios', descripcion: 'Territorios donde se ubican los proyectos.', grupo: GRUPO.CATALOGOS, icono: 'faMapLocationDot', palabras: 'territorio departamento catalogo' },
    { slug: 'estados', tab: 'estados', titulo: 'Estados', descripcion: 'Situación de un proyecto, con su color.', grupo: GRUPO.CATALOGOS, icono: 'faCircleHalfStroke', palabras: 'situacion catalogo' },
    { slug: 'fases', tab: 'fases', titulo: 'Fases', descripcion: 'Etapas del ciclo y cuáles aparecen en el tablero.', grupo: GRUPO.CATALOGOS, icono: 'faDiagramProject', palabras: 'etapas ciclo catalogo' },
    { slug: 'tipos-evento', tab: 'eventos', titulo: 'Tipos de evento', descripcion: 'Clases de evento disponibles en la agenda.', grupo: GRUPO.CATALOGOS, icono: 'faTags', palabras: 'categorias catalogo' },
    { slug: 'prioridades', tab: 'prioridades', titulo: 'Prioridades', descripcion: 'Niveles de urgencia de los eventos.', grupo: GRUPO.CATALOGOS, icono: 'faFlag', palabras: 'urgencia catalogo' },
    { slug: 'responsables', tab: 'responsables', titulo: 'Responsables', descripcion: 'Personas a cargo de los eventos.', grupo: GRUPO.CATALOGOS, icono: 'faUsers', palabras: 'personas equipo catalogo' },
    { slug: 'entidades', tab: 'entidades', titulo: 'Entidades', descripcion: 'Quienes presentan y financian los proyectos.', grupo: GRUPO.CATALOGOS, icono: 'faLandmark', palabras: 'alcaldia gobernacion ministerio catalogo' },
    { slug: 'fuentes-financiacion', tab: 'fuentes-financiacion', titulo: 'Fuentes de financiación', descripcion: 'De dónde provienen los recursos de los proyectos.', grupo: GRUPO.CATALOGOS, icono: 'faCoins', palabras: 'regalias sgr recursos propios cofinanciacion catalogo' },
    { slug: 'sectores', tab: 'sectores', titulo: 'Sectores', descripcion: 'Sectores de inversión de los proyectos.', grupo: GRUPO.CATALOGOS, icono: 'faLayerGroup', palabras: 'educacion salud transporte vivienda catalogo' },
];

// No usar /plantillas: bajo /GestPro chocaría con la API del mismo nombre.
export const RUTA_PLANTILLAS = '/plantillas-checklist';

export const DESTINOS = [
    { id: 'tablero', titulo: 'Tablero', ruta: '/dashboard', grupo: GRUPO.TRABAJO, icono: 'faGaugeHigh', palabras: 'inicio dashboard panel mapa' },
    ...SECCIONES_PARAMETROS.filter((s) => s.grupo === GRUPO.TRABAJO).map((s) => ({ ...s, id: s.slug, ruta: `/parametros/${s.slug}` })),
    { id: 'expedientes', titulo: 'Expedientes', ruta: '/dashboard?vista=expedientes', grupo: GRUPO.TRABAJO, icono: 'faClipboardCheck', palabras: 'sgr viabilizacion regalias requisitos checklist plantillas seguimiento' },
    { id: 'contratos', titulo: 'Contratos', ruta: '/dashboard?vista=contratos', grupo: GRUPO.ANALISIS, icono: 'faFileSignature', palabras: 'avance fisico financiero desfase' },
    { id: 'estadisticas', titulo: 'Estadísticas', ruta: '/dashboard?vista=estadisticas', grupo: GRUPO.ANALISIS, icono: 'faChartColumn', palabras: 'graficas filtros cadena presupuestal' },
    ...SECCIONES_PARAMETROS.filter((s) => s.grupo === GRUPO.CATALOGOS).map((s) => ({ ...s, id: s.slug, ruta: `/parametros/${s.slug}` })),
    { id: 'plantillas', titulo: 'Plantillas de checklist', ruta: RUTA_PLANTILLAS, grupo: GRUPO.CATALOGOS, icono: 'faListCheck', palabras: 'requisitos checklist fuentes sectores versiones catalogo', soloRol: 'Administrador' },
];

/** Los destinos que puede abrir el usuario: algunos son solo de un rol. */
const permitido = (usuario, destino) => !destino.soloRol || destino.soloRol === usuario?.rol;

export const destinosPara = (usuario) => DESTINOS.filter((d) => permitido(usuario, d));

/** Si el usuario puede abrir el destino `id`: la misma regla que usa el menú. */
export const puedeVer = (usuario, id) => destinosPara(usuario).some((d) => d.id === id);

const MAX_PROYECTOS = 8;

export const normalizar = (texto) =>
    String(texto ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim();

export const seccionDesdeSlug = (slug) => SECCIONES_PARAMETROS.find((s) => s.slug === slug) ?? null;

/** Resultados del buscador: primero a dónde ir, después los proyectos que coinciden. */
export const buscarEnPaleta = (consulta, destinos, proyectos) => {
    const buscado = normalizar(consulta);
    const coincide = (...campos) => campos.some((c) => normalizar(c).includes(buscado));

    const porDestino = destinos
        .filter((d) => !buscado || coincide(d.titulo, d.palabras, d.grupo))
        .map((d) => ({ tipo: 'destino', clave: d.id, titulo: d.titulo, detalle: d.grupo, ruta: d.ruta, icono: d.icono }));

    if (!buscado) return porDestino;

    const porProyecto = proyectos
        .filter((p) => p.nombre && coincide(p.nombre, p.descripcion_municipio))
        .slice(0, MAX_PROYECTOS)
        .map((p) => ({
            tipo: 'proyecto',
            clave: `proyecto-${p.id}`,
            titulo: p.nombre,
            detalle: p.descripcion_municipio ?? '',
            ruta: `/dashboard?proyecto=${p.id}`,
            icono: 'faFolderOpen',
        }));

    return [...porDestino, ...porProyecto];
};

export const destinoActivo = (pathname, search) => {
    if (pathname.startsWith('/viabilizacion/')) return 'expedientes';
    if (pathname === RUTA_PLANTILLAS || pathname.startsWith(`${RUTA_PLANTILLAS}/`)) return 'plantillas';
    if (pathname.startsWith('/parametros/')) return pathname.split('/')[2];
    if (pathname.startsWith('/dashboard')) {
        const vista = new URLSearchParams(search).get('vista');
        return ['contratos', 'expedientes', 'estadisticas'].includes(vista) ? vista : 'tablero';
    }
    return null;
};

export const tituloDeRuta = (pathname, search) =>
    DESTINOS.find((d) => d.id === destinoActivo(pathname, search))?.titulo ?? 'GestPro';
