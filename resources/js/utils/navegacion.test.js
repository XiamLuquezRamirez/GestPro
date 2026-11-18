import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import { DESTINOS, GRUPO, RUTA_PLANTILLAS, SECCIONES_PARAMETROS, buscarEnPaleta, destinoActivo, destinosPara, puedeVer, seccionDesdeSlug, tituloDeRuta } from './navegacion';
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

    it('encuentra proyectos por nombre o municipio y los deja después de los destinos', () => {
        const porNombre = buscarEnPaleta('construccion', DESTINOS, proyectos);
        expect(porNombre.at(-1)).toMatchObject({ tipo: 'proyecto', titulo: 'Construcción sede educativa', ruta: '/dashboard?proyecto=1' });

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
        expect(destinoActivo('/dashboard', '?vista=contratos')).toBe('contratos');
        expect(destinoActivo('/dashboard', '?vista=expedientes')).toBe('expedientes');
    });

    it('un expediente pertenece a Expedientes', () => {
        expect(destinoActivo('/viabilizacion/4', '')).toBe('expedientes');
    });
});

describe('tituloDeRuta', () => {
    it('da el título de la barra móvil', () => {
        expect(tituloDeRuta('/parametros/tipos-evento', '')).toBe('Tipos de evento');
        expect(tituloDeRuta('/parametros/etapas-contratacion', '')).toBe('Etapas de contratación');
        expect(tituloDeRuta('/dashboard', '')).toBe('Tablero');
        expect(tituloDeRuta('/viabilizacion/4', '')).toBe('Expedientes');
        expect(tituloDeRuta('/otra', '')).toBe('GestPro');
    });
});
