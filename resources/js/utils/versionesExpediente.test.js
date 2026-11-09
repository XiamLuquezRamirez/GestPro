import { describe, it, expect } from 'vitest';
import {
    TOPE_VISTA_PREVIA,
    etiquetaAplicarVersion,
    htmlVistaPrevia,
    plantillasConVersionNueva,
    textoVersionNueva,
} from './versionesExpediente';

/** Plantilla como la trae la cabecera del expediente. */
const plantilla = (id, extra = {}) => ({
    plantilla_id: id,
    nombre: `Plantilla ${id}`,
    motor: 'simple',
    es_principal: id === 1,
    compatible: true,
    version: { id: id * 10, numero: 2 },
    version_nueva: null,
    ...extra,
});

/** Fila resumida de la vista previa (VersionesExpediente::resumen). */
const fila = (id, extra = {}) => ({ id, codigo: `R-${id}`, texto: `Requisito ${id}`, caracter: 'obligatorio', ...extra });

const propio = (id, titulo, extra = {}) => ({ id, codigo: null, texto: titulo, caracter: 'obligatorio', titulo, ...extra });

const vista = (extra = {}) => ({
    plantilla: { id: 1, nombre: 'Obras de agua' },
    version_actual: { id: 10, numero: 2 },
    version_nueva: { id: 11, numero: 3, descripcion: null, fecha_publicacion: '2026-11-05T12:00:00-05:00' },
    nuevos: [],
    retirados: [],
    cambian: [],
    siguen: 0,
    progreso_antes: { total: 30, cumplidos: 12, porcentaje: 40 },
    progreso_despues: { total: 32, cumplidos: 12, porcentaje: 38 },
    ...extra,
});

// Sin DOM en las pruebas: el texto se saca quitando etiquetas. Vale porque todo dato va escapado
// (ningún "<" de los datos llega como tal) y solo se comparan textos sin entidades.
const textoDe = (html) => html.replace(/<[^>]+>/g, '');

/** El texto de cada <li> de la sección cuyo título empieza con `titulo`, o null si no está. */
const itemsDe = (html, titulo) => {
    const seccion = [...html.matchAll(/<section><h3>(.*?)<\/h3>(.*?)<\/section>/g)].find(([, h3]) => h3.startsWith(titulo));
    return seccion ? [...seccion[2].matchAll(/<li[^>]*>(.*?)<\/li>/g)].map(([, li]) => textoDe(li)) : null;
};

describe('plantillasConVersionNueva', () => {
    it('devuelve solo las que traen version_nueva, en su orden', () => {
        const lista = [
            plantilla(1, { version_nueva: { id: 11, numero: 3 } }),
            plantilla(2),
            plantilla(3, { version_nueva: { id: 31, numero: 5 } }),
        ];
        expect(plantillasConVersionNueva(lista).map((p) => p.plantilla_id)).toEqual([1, 3]);
    });

    it('tolera una lista vacía, nula o con elementos inesperados', () => {
        expect(plantillasConVersionNueva([])).toEqual([]);
        expect(plantillasConVersionNueva(null)).toEqual([]);
        expect(plantillasConVersionNueva(undefined)).toEqual([]);
        expect(plantillasConVersionNueva([null, 'SGR', plantilla(2)])).toEqual([]);
    });
});

describe('textoVersionNueva', () => {
    it('dice la versión disponible, sin repetir la que usa el expediente', () => {
        expect(textoVersionNueva(plantilla(1, { version_nueva: { id: 11, numero: 3 } }))).toBe('versión 3 disponible');
    });

    it('sin versión nueva devuelve null', () => {
        expect(textoVersionNueva(plantilla(1))).toBeNull();
        expect(textoVersionNueva(null)).toBeNull();
    });
});

describe('etiquetaAplicarVersion', () => {
    it('nombra la versión y la plantilla, para distinguir un botón de otro', () => {
        const agua = plantilla(2, { nombre: 'Obras de agua', version_nueva: { id: 21, numero: 3 } });
        expect(etiquetaAplicarVersion(agua)).toBe('Aplicar la versión 3 de Obras de agua');
    });

    it('sin versión nueva devuelve null', () => {
        expect(etiquetaAplicarVersion(plantilla(1))).toBeNull();
        expect(etiquetaAplicarVersion(undefined)).toBeNull();
    });
});

describe('htmlVistaPrevia', () => {
    it('resume las versiones y el avance antes y después', () => {
        const texto = textoDe(htmlVistaPrevia(vista({ nuevos: [fila(1)] })));
        expect(texto).toContain('Versión 2 → 3');
        expect(texto).toContain('Avance: 12 de 30 (40%) → 12 de 32 (38%)');
    });

    it('muestra la descripción de la versión solo si la tiene', () => {
        const con = vista({ version_nueva: { id: 11, numero: 3, descripcion: '  Ajusta la norma de 2026.  ', fecha_publicacion: null } });
        expect(htmlVistaPrevia(con)).toContain('<p class="exv-vista-descripcion">Ajusta la norma de 2026.</p>');
        expect(htmlVistaPrevia(vista())).not.toContain('exv-vista-descripcion');
        const enBlanco = vista({ version_nueva: { id: 11, numero: 3, descripcion: '   ', fecha_publicacion: null } });
        expect(htmlVistaPrevia(enBlanco)).not.toContain('exv-vista-descripcion');
    });

    it('si el avance no cambia, lo dice una sola vez', () => {
        const igual = { total: 30, cumplidos: 12, porcentaje: 40 };
        const texto = textoDe(htmlVistaPrevia(vista({ progreso_antes: igual, progreso_despues: igual })));
        expect(texto).toContain('Avance: 12 de 30 (40%), no cambia');
        expect(texto).not.toContain('→ 12 de 30');
    });

    it('sin cambios lo dice, con los que siguen igual', () => {
        const igual = { total: 8, cumplidos: 2, porcentaje: 25 };
        const html = htmlVistaPrevia(vista({ siguen: 8, progreso_antes: igual, progreso_despues: igual }));
        const texto = textoDe(html);
        expect(texto).toContain('La versión nueva no cambia el checklist de este expediente.');
        expect(texto).toContain('8 requisitos siguen igual.');
        expect(html).not.toContain('<section>');
    });

    it('con cambios no dice que no cambia nada', () => {
        expect(textoDe(htmlVistaPrevia(vista({ cambian: [{ antes: fila(1), despues: fila(1, { texto: 'Otro' }) }] }))))
            .not.toContain('no cambia el checklist');
    });

    it('no dice cuántos siguen igual si no sigue ninguno', () => {
        expect(textoDe(htmlVistaPrevia(vista({ nuevos: [fila(1)] })))).not.toContain('igual');
    });

    it('usa singular y plural en los títulos y en los que siguen', () => {
        const una = textoDe(htmlVistaPrevia(vista({
            nuevos: [fila(1)], retirados: [fila(2)], cambian: [{ antes: fila(3), despues: fila(3, { texto: 'Nuevo' }) }], siguen: 1,
        })));
        expect(una).toContain('Se agrega 1 requisito');
        expect(una).toContain('Deja de aplicar 1 requisito');
        expect(una).toContain('Cambia 1 requisito');
        expect(una).toContain('1 requisito sigue igual.');

        const varias = textoDe(htmlVistaPrevia(vista({
            nuevos: [fila(1), fila(2)],
            retirados: [fila(3), fila(4), fila(5)],
            cambian: [{ antes: fila(6), despues: fila(6, { texto: 'A' }) }, { antes: fila(7), despues: fila(7, { texto: 'B' }) }],
            siguen: 4,
        })));
        expect(varias).toContain('Se agregan 2 requisitos');
        expect(varias).toContain('Dejan de aplicar 3 requisitos');
        expect(varias).toContain('Cambian 2 requisitos');
        expect(varias).toContain('4 requisitos siguen igual.');
    });

    it('muestra cada fila con su código y su texto', () => {
        const html = htmlVistaPrevia(vista({ retirados: [fila(1), fila(2, { codigo: null, texto: 'Sin código' })] }));
        expect(html).toContain('<li><b>R-1</b> Requisito 1</li>');
        expect(itemsDe(html, 'Dejan de aplicar')).toEqual(['R-1 Requisito 1', 'Sin código']);
    });

    it('una fila propia se muestra por su título y sin código', () => {
        const html = htmlVistaPrevia(vista({ retirados: [propio(9, 'Permiso de la CAR', { codigo: 'X-1', texto: 'otro', con_trabajo: false })] }));
        expect(itemsDe(html, 'Deja de aplicar')).toEqual(['Permiso de la CAR propio']);
        expect(html).not.toContain('X-1');
    });

    it('marca los retirados que tienen trabajo guardado', () => {
        const html = htmlVistaPrevia(vista({
            retirados: [fila(1, { con_trabajo: true }), fila(2, { con_trabajo: false })],
        }));
        expect(itemsDe(html, 'Dejan de aplicar')).toEqual(['R-1 Requisito 1 conserva su trabajo', 'R-2 Requisito 2']);
    });

    it('marca los nuevos que no son obligatorios y los que vuelven a aplicar', () => {
        const html = htmlVistaPrevia(vista({
            nuevos: [fila(null, { codigo: 'N-1', texto: 'Nace' }), fila(null, { codigo: 'N-2', texto: 'Cond', caracter: 'condicionado' }), fila(7)],
        }));
        expect(itemsDe(html, 'Se agregan')).toEqual(['N-1 Nace', 'N-2 Cond condicionado', 'R-7 Requisito 7 vuelve a aplicar']);
    });

    it('en los que cambian dice el texto nuevo y el cambio de carácter', () => {
        const html = htmlVistaPrevia(vista({
            cambian: [
                { antes: fila(1), despues: fila(1, { codigo: 'R-1b', texto: 'Texto nuevo' }) },
                { antes: fila(2), despues: fila(2, { caracter: 'opcional' }) },
            ],
        }));
        expect(itemsDe(html, 'Cambian')).toEqual([
            'R-1 Requisito 1Ahora: R-1b Texto nuevo',
            'R-2 Requisito 2Pasa de obligatorio a opcional.',
        ]);
    });

    it('limita cada lista a TOPE_VISTA_PREVIA y cuenta el resto', () => {
        const muchos = Array.from({ length: TOPE_VISTA_PREVIA + 3 }, (_, i) => fila(i + 1));
        const items = itemsDe(htmlVistaPrevia(vista({ retirados: muchos })), 'Dejan de aplicar');
        expect(items).toHaveLength(TOPE_VISTA_PREVIA + 1);
        expect(items.at(-2)).toBe(`R-${TOPE_VISTA_PREVIA} Requisito ${TOPE_VISTA_PREVIA}`);
        expect(items.at(-1)).toBe('y 3 más');
    });

    it('con justo TOPE_VISTA_PREVIA filas no agrega "y N más"', () => {
        const justas = Array.from({ length: TOPE_VISTA_PREVIA }, (_, i) => fila(i + 1));
        const items = itemsDe(htmlVistaPrevia(vista({ nuevos: justas })), 'Se agregan');
        expect(items).toHaveLength(TOPE_VISTA_PREVIA);
        expect(items.some((t) => t.endsWith('más'))).toBe(false);
    });

    it('escapa todo texto que viene de los datos', () => {
        const malo = '<img src=x onerror=alert(1)>';
        const html = htmlVistaPrevia(vista({
            version_actual: { id: 10, numero: '<b>2</b>' },
            version_nueva: { id: 11, numero: 3, descripcion: `Notas ${malo}`, fecha_publicacion: null },
            nuevos: [fila(1, { codigo: '"R&1"', texto: malo })],
            retirados: [propio(2, malo, { con_trabajo: true })],
            cambian: [{ antes: fila(3, { texto: malo }), despues: fila(3, { texto: `${malo}!`, caracter: '<i>raro</i>' }) }],
        }));
        expect(html).not.toContain('<img');
        expect(html).not.toContain('<b>2</b>');
        expect(html).not.toContain('<i>raro</i>');
        expect(html).toContain('&lt;img src=x onerror=alert(1)&gt;');
        expect(html).toContain('<b>&quot;R&amp;1&quot;</b>');
        expect(html).toContain('Versión &lt;b&gt;2&lt;/b&gt; → 3');
    });
});
