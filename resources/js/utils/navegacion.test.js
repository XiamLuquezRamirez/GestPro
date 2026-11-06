import { describe, it, expect } from 'vitest';
import { DESTINOS, buscarEnPaleta, destinoActivo, seccionDesdeSlug, tituloDeRuta } from './navegacion';

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
});

describe('DESTINOS', () => {
    it('lleva a cada catálogo con una sola ruta directa', () => {
        const rutas = DESTINOS.map((d) => d.ruta);

        ['municipios', 'estados', 'fases', 'tipos-evento', 'prioridades', 'responsables', 'entidades', 'fuentes-financiacion', 'sectores'].forEach((slug) => {
            expect(rutas).toContain(`/parametros/${slug}`);
        });
    });

    it('no repite identificadores ni rutas', () => {
        expect(new Set(DESTINOS.map((d) => d.id)).size).toBe(DESTINOS.length);
        expect(new Set(DESTINOS.map((d) => d.ruta)).size).toBe(DESTINOS.length);
    });
});

describe('buscarEnPaleta', () => {
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

    it('un expediente pertenece al seguimiento SGR', () => {
        expect(destinoActivo('/viabilizacion/4', '')).toBe('expedientes');
    });
});

describe('tituloDeRuta', () => {
    it('da el título de la barra móvil', () => {
        expect(tituloDeRuta('/parametros/tipos-evento', '')).toBe('Tipos de evento');
        expect(tituloDeRuta('/dashboard', '')).toBe('Tablero');
        expect(tituloDeRuta('/viabilizacion/4', '')).toBe('Expedientes SGR');
        expect(tituloDeRuta('/otra', '')).toBe('GestPro');
    });
});
