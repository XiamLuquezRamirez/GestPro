import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import { DESTINOS, GRUPO, RUTA_CONTRATOS, RUTA_PLANTILLAS, SECCIONES_PARAMETROS, SUBGRUPOS_CATALOGOS, buscarEnPaleta, catalogosPorSubgrupo, destinoActivo, destinosPara, puedeVer, rutaExpedienteContratacion, rutaFichaProyecto, seccionDesdeSlug, subgrupoDe, tituloDeRuta } from './navegacion';
import { ICONOS } from './iconosNavegacion';

const proyectos = [
    { id: 1, nombre: 'Construcción sede educativa', descripcion_municipio: 'MEDELLÍN' },
    { id: 2, nombre: 'Acueducto veredal', descripcion_municipio: 'BELLO' },
];

describe('seccionDesdeSlug', () => {
    it('traduce la URL a la sección interna de Parámetros', () => {
        expect(seccionDesdeSlug('proyectos').tab).toBe('proyectos');
        expect(seccionDesdeSlug('eventos').tab).toBe('eventos-calendario');
        expect(seccionDesdeSlug('tipos-evento').tab).toBe('eventos');
    });

    it('devuelve null si la sección no existe', () => {
        expect(seccionDesdeSlug('nada')).toBeNull();
        expect(seccionDesdeSlug(undefined)).toBeNull();
    });

    it('modalidades, etapas y tipos de contrato son catálogos con componente propio', () => {
        expect(seccionDesdeSlug('modalidades')).toMatchObject({ tab: 'modalidades', titulo: 'Modalidades', grupo: GRUPO.CATALOGOS, propia: true });
        expect(seccionDesdeSlug('etapas-contratacion')).toMatchObject({ tab: 'etapas-contratacion', titulo: 'Etapas de contratación', grupo: GRUPO.CATALOGOS, propia: true });
        expect(SECCIONES_PARAMETROS.filter((s) => s.propia).map((s) => s.slug)).toEqual(['modalidades', 'etapas-contratacion', 'tipos-contrato']);
        expect(seccionDesdeSlug('tipos-contrato')).toMatchObject({ tab: 'tipos-contrato', titulo: 'Tipos de contrato', grupo: GRUPO.CATALOGOS, propia: true });
    });

    it('cada sección tiene un icono registrado', () => {
        SECCIONES_PARAMETROS.forEach((s) => expect(ICONOS[s.icono]).toBeDefined());
    });
});

describe('secciones en Parametros.jsx', () => {
    // Ruta relativa a este archivo, no al directorio desde donde se lance Vitest.
    const fuente = readFileSync(new URL('../components/Parametros.jsx', import.meta.url), 'utf8');

    // Parametros llama hooks dentro de renderTable: cada sección que lo monta debe pintar una tabla,
    // y cada sección propia, que se pinta sin Parametros, debe tener su componente.
    it('cada sección de tablas llama renderTable y cada sección propia tiene componente', () => {
        const propias = fuente.match(/const SECCIONES_PROPIAS = \{([^}]*)\}/)[1];
        SECCIONES_PARAMETROS.forEach((s) => {
            if (s.propia) expect(propias).toMatch(new RegExp(`^\\s*'?${s.tab}'?:`, 'm'));
            else expect(fuente).toContain(`activeTab === '${s.tab}' && renderTable(`);
        });
    });
});

describe('DESTINOS', () => {
    it('lleva a cada catálogo con una sola ruta directa', () => {
        const rutas = DESTINOS.map((d) => d.ruta);

        ['municipios', 'estados', 'fases', 'tipos-evento', 'prioridades', 'responsables', 'entidades', 'fuentes-financiacion', 'sectores', 'modalidades', 'etapas-contratacion', 'tipos-contrato'].forEach((slug) => {
            expect(rutas).toContain(`/parametros/${slug}`);
        });
    });

    it('Contratos es trabajo diario y su avance es análisis, con otro nombre', () => {
        expect(DESTINOS.find((d) => d.id === 'contratos')).toMatchObject({ titulo: 'Contratos', ruta: '/contratos', grupo: GRUPO.TRABAJO });
        expect(DESTINOS.find((d) => d.id === 'avance-contratos')).toMatchObject({ titulo: 'Avance de contratos', ruta: '/dashboard?vista=contratos', grupo: GRUPO.ANALISIS });
        expect(DESTINOS.filter((d) => d.titulo === 'Contratos')).toHaveLength(1);
    });

    it('Contratos va después de Expedientes, antes del análisis', () => {
        const ids = DESTINOS.map((d) => d.id);
        expect(ids.indexOf('contratos')).toBe(ids.indexOf('expedientes') + 1);
        expect(DESTINOS[ids.indexOf('contratos') + 1].grupo).toBe(GRUPO.ANALISIS);
    });

    it('cada destino tiene un icono registrado', () => {
        DESTINOS.forEach((d) => expect(ICONOS[d.icono]).toBeDefined());
    });

    it('el avance de contratos no repite el icono de Contratos', () => {
        const icono = (id) => DESTINOS.find((d) => d.id === id).icono;
        expect(icono('avance-contratos')).not.toBe(icono('contratos'));
    });

    it('no repite identificadores ni rutas', () => {
        expect(new Set(DESTINOS.map((d) => d.id)).size).toBe(DESTINOS.length);
        expect(new Set(DESTINOS.map((d) => d.ruta)).size).toBe(DESTINOS.length);
    });
});

describe('destinosPara', () => {
    const sinPlantillas = DESTINOS.filter((d) => d.id !== 'plantillas');

    it('las plantillas de checklist solo se muestran a un Administrador', () => {
        expect(destinosPara({ rol: 'Administrador' })).toEqual(DESTINOS);
        expect(destinosPara({ rol: 'Gestor' })).toEqual(sinPlantillas);
        expect(destinosPara({ rol: 'Consulta' })).toEqual(sinPlantillas);
        expect(destinosPara(null)).toEqual(sinPlantillas);
    });

    it('la ruta de plantillas y su detalle marcan el destino', () => {
        expect(RUTA_PLANTILLAS).toBe('/plantillas-checklist');
        expect(destinoActivo(RUTA_PLANTILLAS, '')).toBe('plantillas');
        expect(destinoActivo(`${RUTA_PLANTILLAS}/7`, '')).toBe('plantillas');
        expect(tituloDeRuta(`${RUTA_PLANTILLAS}/7`, '')).toBe('Plantillas de checklist');
    });

    it('una ruta que solo empieza igual no queda activa', () => {
        expect(destinoActivo(`${RUTA_PLANTILLAS}x`, '')).toBeNull();
        expect(destinoActivo('/plantillas', '')).toBeNull();
    });
});

describe('puedeVer', () => {
    it('aplica la misma regla de rol que destinosPara', () => {
        expect(puedeVer({ rol: 'Administrador' }, 'plantillas')).toBe(true);
        expect(puedeVer({ rol: 'Gestor' }, 'plantillas')).toBe(false);
        expect(puedeVer({ rol: 'Consulta' }, 'plantillas')).toBe(false);
        expect(puedeVer(null, 'plantillas')).toBe(false);
        expect(puedeVer({ rol: 'Gestor' }, 'tablero')).toBe(true);
        expect(puedeVer({ rol: 'Administrador' }, 'inexistente')).toBe(false);
    });
});

describe('buscarEnPaleta', () => {
    it('"SGR" sigue llevando a Expedientes aunque el título ya no lo diga', () => {
        const expedientes = DESTINOS.find((d) => d.id === 'expedientes');
        expect(expedientes.titulo).toBe('Expedientes');
        expect(buscarEnPaleta('SGR', DESTINOS, []).map((r) => r.clave)).toContain('expedientes');
        expect(buscarEnPaleta('viabilización', DESTINOS, []).map((r) => r.clave)).toContain('expedientes');
    });

    it('sin texto muestra solo los destinos', () => {
        const resultados = buscarEnPaleta('', DESTINOS, proyectos);

        expect(resultados).toHaveLength(DESTINOS.length);
        expect(resultados.every((r) => r.tipo === 'destino')).toBe(true);
    });

    it('encuentra destinos por título o sinónimo, sin importar tildes', () => {
        const titulos = (q) => buscarEnPaleta(q, DESTINOS, []).map((r) => r.titulo);

        expect(titulos('estadisticas')).toContain('Estadísticas');
        expect(titulos('alcaldia')).toContain('Entidades');
        expect(titulos('catalogo')).toContain('Municipios');
        expect(titulos('secop')).toEqual(expect.arrayContaining(['Modalidades', 'Etapas de contratación']));
        expect(titulos('licitación')).toEqual(expect.arrayContaining(['Modalidades', 'Etapas de contratación']));
        expect(titulos('minima cuantia')).toContain('Modalidades');
        expect(titulos('interventoria')).toContain('Tipos de contrato');
    });

    it('"contratos" lleva al módulo y al avance; "contratista" o "avance", a cada uno', () => {
        const claves = (q) => buscarEnPaleta(q, DESTINOS, []).map((r) => r.clave);

        expect(claves('contratos')).toEqual(expect.arrayContaining(['contratos', 'avance-contratos']));
        expect(claves('contratista')).toContain('contratos');
        expect(claves('otrosí')).toContain('contratos');
        expect(claves('desfase')).toEqual(['avance-contratos']);
        expect(buscarEnPaleta('contratos', DESTINOS, []).find((r) => r.clave === 'contratos')).toMatchObject({ titulo: 'Contratos', ruta: '/contratos', detalle: GRUPO.TRABAJO });
    });

    it('encuentra proyectos por nombre o municipio y los deja después de los destinos', () => {
        const porNombre = buscarEnPaleta('construccion', DESTINOS, proyectos);
        expect(porNombre.at(-1)).toMatchObject({ tipo: 'proyecto', titulo: 'Construcción sede educativa', ruta: '/proyectos/1' });

        expect(buscarEnPaleta('bello', DESTINOS, proyectos).map((r) => r.titulo)).toEqual(['Acueducto veredal']);
    });

    it('limita los proyectos para que la lista siga siendo escaneable', () => {
        const muchos = Array.from({ length: 30 }, (_, i) => ({ id: i, nombre: `Vía ${i}` }));

        expect(buscarEnPaleta('via', [], muchos)).toHaveLength(8);
    });

    it('tolera proyectos sin nombre', () => {
        expect(buscarEnPaleta('x', [], [{ id: 1 }])).toEqual([]);
    });
});

describe('destinoActivo', () => {
    it('marca la sección de Parámetros de la URL', () => {
        expect(destinoActivo('/parametros/fases', '')).toBe('fases');
    });

    it('distingue las vistas analíticas del tablero', () => {
        expect(destinoActivo('/dashboard', '')).toBe('tablero');
        expect(destinoActivo('/dashboard', '?vista=formulacion')).toBe('tablero');
        expect(destinoActivo('/dashboard', '?vista=contratos')).toBe('avance-contratos');
        expect(destinoActivo('/dashboard', '?vista=expedientes')).toBe('expedientes');
    });

    it('la lista de contratos, uno nuevo y uno abierto marcan Contratos', () => {
        expect(RUTA_CONTRATOS).toBe('/contratos');
        expect(destinoActivo('/contratos', '')).toBe('contratos');
        expect(destinoActivo('/contratos', '?estado=anulado')).toBe('contratos');
        expect(destinoActivo('/contratos/nuevo', '')).toBe('contratos');
        expect(destinoActivo('/contratos/12', '')).toBe('contratos');
        expect(destinoActivo('/contratosx', '')).toBeNull();
    });

    it('un expediente pertenece a Expedientes', () => {
        expect(destinoActivo('/viabilizacion/4', '')).toBe('expedientes');
    });

    it('la ficha de un proyecto pertenece a Proyectos', () => {
        expect(destinoActivo('/proyectos/4', '')).toBe('proyectos');
        expect(tituloDeRuta('/proyectos/4', '')).toBe('Proyectos');
        // GET /proyectos es de la API: el SPA no tiene /proyectos sin id.
        expect(destinoActivo('/proyectos', '')).toBeNull();
        expect(destinoActivo('/proyectosx/4', '')).toBeNull();
    });

    it('el expediente de contratación de un proceso pertenece a Proyectos', () => {
        expect(destinoActivo('/contratacion/9', '')).toBe('proyectos');
        expect(tituloDeRuta('/contratacion/9', '')).toBe('Proyectos');
        expect(destinoActivo('/contratacion', '')).toBeNull();
        expect(destinoActivo('/contratacionx/9', '')).toBeNull();
    });
});

describe('rutaExpedienteContratacion', () => {
    it('el expediente del proceso, siempre con id', () => {
        expect(rutaExpedienteContratacion(9)).toBe('/contratacion/9');
    });
});

describe('rutaFichaProyecto', () => {
    it('la ficha del proyecto, nunca /proyectos sin id', () => {
        expect(rutaFichaProyecto(7)).toBe('/proyectos/7');
        expect(rutaFichaProyecto('12')).toBe('/proyectos/12');
    });
});

describe('tituloDeRuta', () => {
    it('da el título de la barra móvil', () => {
        expect(tituloDeRuta('/parametros/tipos-evento', '')).toBe('Tipos de evento');
        expect(tituloDeRuta('/parametros/etapas-contratacion', '')).toBe('Etapas de contratación');
        expect(tituloDeRuta('/dashboard', '')).toBe('Tablero');
        expect(tituloDeRuta('/viabilizacion/4', '')).toBe('Expedientes');
        expect(tituloDeRuta('/contratos/12', '')).toBe('Contratos');
        expect(tituloDeRuta('/dashboard', '?vista=contratos')).toBe('Avance de contratos');
        expect(tituloDeRuta('/otra', '')).toBe('GestPro');
    });
});

describe('catálogos por subgrupo', () => {
    const admin = { rol: 'Administrador' };
    const gestor = { rol: 'Gestor' };

    it('reparte los catálogos en cinco secciones, en el orden acordado', () => {
        const secciones = catalogosPorSubgrupo(destinosPara(admin));

        expect(secciones.map((s) => s.titulo)).toEqual(['Proyectos y territorio', 'Financiación y entidades', 'Agenda', 'Contratación', 'Checklists']);
        expect(secciones.map((s) => s.destinos.map((d) => d.id))).toEqual([
            ['estados', 'fases', 'sectores', 'municipios'],
            ['fuentes-financiacion', 'entidades'],
            ['tipos-evento', 'prioridades', 'responsables'],
            ['modalidades', 'etapas-contratacion', 'tipos-contrato'],
            ['plantillas'],
        ]);
    });

    it('cada catálogo del menú está en exactamente una sección', () => {
        const catalogos = DESTINOS.filter((d) => d.grupo === GRUPO.CATALOGOS).map((d) => d.id).sort();
        const enSecciones = SUBGRUPOS_CATALOGOS.flatMap((s) => s.ids).sort();

        expect(enSecciones).toEqual(catalogos);
    });

    it('omite las secciones que el usuario no puede ver', () => {
        const secciones = catalogosPorSubgrupo(destinosPara(gestor));

        expect(secciones.map((s) => s.titulo)).not.toContain('Checklists');
        expect(secciones.flatMap((s) => s.destinos.map((d) => d.id))).not.toContain('plantillas');
    });

    it('ignora los destinos que no son catálogos', () => {
        const ids = catalogosPorSubgrupo(DESTINOS).flatMap((s) => s.destinos.map((d) => d.id));

        expect(ids).not.toContain('tablero');
        expect(ids).not.toContain('contratos');
    });

    it('dice en qué sección está un catálogo, o null si no es un catálogo', () => {
        expect(subgrupoDe('tipos-contrato')).toBe('Contratación');
        expect(subgrupoDe('responsables')).toBe('Agenda');
        expect(subgrupoDe('tablero')).toBeNull();
        expect(subgrupoDe(null)).toBeNull();
    });
});
