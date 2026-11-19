import { describe, it, expect } from 'vitest';
import {
    agruparPorEtapa,
    avisoIncompatiblesProceso,
    alternarPlantillaNueva,
    avanceEtapas,
    conAvance,
    datosPropioContratacion,
    errorDeProceso,
    esIdProceso,
    formularioPropioContratacion,
    htmlPendientesCierre,
    opcionesEtapaPropio,
    propioCompleto,
    seleccionInicial,
    textoEstadoExpediente,
    textoPendientesCierre,
    textoSinPlantillas,
} from './expedienteContratacion';
import { agruparChecklist, describirEvento } from './expedienteChecklist';

const etapa = (id, orden, nombre = `Etapa ${id}`) => ({ id, nombre, orden });

const fila = (id, et, cambios = {}) => ({
    id,
    etapa: et,
    estado: 'pendiente',
    cumplido: false,
    cuenta: true,
    caracter: 'obligatorio',
    requisito: { codigo: `R${id}`, texto: `Requisito ${id}` },
    plantilla: { id: 1, nombre: 'Licitación', motor: 'simple' },
    propio: null,
    ...cambios,
});

describe('esIdProceso', () => {
    it('acepta un entero positivo y rechaza lo demás', () => {
        expect(esIdProceso('12')).toBe(true);
        expect(esIdProceso('0')).toBe(false);
        expect(esIdProceso('abc')).toBe(false);
        expect(esIdProceso('1.5')).toBe(false);
        expect(esIdProceso(undefined)).toBe(false);
    });
});

describe('errorDeProceso', () => {
    it('un 404 no se reintenta', () => {
        expect(errorDeProceso({ response: { status: 404 } })).toEqual({ mensaje: 'El proceso no existe.', reintentable: false });
    });

    it('otro error se reintenta, con el mensaje de la API', () => {
        expect(errorDeProceso({ response: { status: 500 } })).toEqual({ mensaje: 'Error del servidor. Inténtalo de nuevo.', reintentable: true });
    });
});

describe('agruparPorEtapa', () => {
    it('agrupa por etapa en el orden del catálogo, con "Sin etapa" al final', () => {
        const a = etapa(5, 2, 'Selección');
        const b = etapa(3, 1, 'Planeación');
        const grupos = agruparPorEtapa([
            fila(1, null),
            fila(2, a),
            fila(3, b),
            fila(4, a, { estado: 'cumplido', cumplido: true }),
            fila(5, b, { propio: { titulo: 'Propio' }, requisito: null, plantilla: null }),
        ]);

        expect(grupos.map((g) => g.clave)).toEqual(['etapa-3', 'etapa-5', 'sin-etapa']);
        expect(grupos.map((g) => g.titulo)).toEqual(['1. Planeación', '2. Selección', 'Sin etapa']);
        // Dentro de cada etapa, en el orden en que llegan (los propios, que el backend pone al final, también).
        expect(grupos[0].filas.map((f) => f.id)).toEqual([3, 5]);
        expect(grupos[1].filas.map((f) => f.id)).toEqual([2, 4]);
        expect(grupos[2].filas.map((f) => f.id)).toEqual([1]);
    });

    it('a igual orden, desempata por id', () => {
        const grupos = agruparPorEtapa([fila(1, etapa(9, 1)), fila(2, etapa(4, 1))]);
        expect(grupos.map((g) => g.etapa.id)).toEqual([4, 9]);
    });

    it('cuenta solo lo que cuenta, como el backend', () => {
        const a = etapa(1, 1);
        const [grupo] = agruparPorEtapa([
            fila(1, a, { estado: 'cumplido', cumplido: true }),
            fila(2, a),
            fila(3, a, { cuenta: false, caracter: 'opcional', estado: 'cumplido', cumplido: true }),
            fila(4, a, { cuenta: false, estado: 'no_aplica' }),
        ]);
        expect(grupo.total).toBe(2);
        expect(grupo.cumplidos).toBe(1);
    });

    it('sin filas, sin grupos', () => {
        expect(agruparPorEtapa([])).toEqual([]);
        expect(agruparPorEtapa(undefined)).toEqual([]);
    });

    it('no cambia la agrupación de viabilización', () => {
        const filas = [fila(1, etapa(1, 1)), fila(2, null)];
        const bloques = agruparChecklist(filas);
        expect(bloques).toHaveLength(1);
        expect(bloques[0].tipo).toBe('simple');
    });
});

describe('avanceEtapas', () => {
    it('presenta el avance por etapa de la API, con título y clave', () => {
        expect(avanceEtapas([
            { etapa: etapa(3, 1, 'Planeación'), total: 4, cumplidos: 1, porcentaje: 25 },
            { etapa: null, total: 2, cumplidos: 2, porcentaje: 100 },
        ])).toEqual([
            { clave: 'etapa-3', titulo: '1. Planeación', total: 4, cumplidos: 1, porcentaje: 25 },
            { clave: 'sin-etapa', titulo: 'Sin etapa', total: 2, cumplidos: 2, porcentaje: 100 },
        ]);
    });

    it('sin avance, lista vacía', () => {
        expect(avanceEtapas(undefined)).toEqual([]);
    });
});

describe('conAvance', () => {
    const expediente = { id: 1, progreso: { total: 2, cumplidos: 0, porcentaje: 0 }, avance_por_etapa: [{ etapa: null, total: 2, cumplidos: 0, porcentaje: 0 }] };

    it('toma el progreso y el avance por etapa de una respuesta parcial', () => {
        const datos = { progreso: { total: 2, cumplidos: 1, porcentaje: 50 }, avance_por_etapa: [{ etapa: null, total: 2, cumplidos: 1, porcentaje: 50 }] };
        expect(conAvance(expediente, datos)).toEqual({ ...expediente, ...datos });
    });

    it('lo que la respuesta no trae queda como estaba', () => {
        expect(conAvance(expediente, {})).toEqual(expediente);
        expect(conAvance(expediente, undefined)).toEqual(expediente);
    });
});

describe('opcionesEtapaPropio', () => {
    const etapas = [
        { id: 2, nombre: 'Selección', orden: 2, activo: true },
        { id: 1, nombre: 'Planeación', orden: 1, activo: true },
        { id: 3, nombre: 'Vieja', orden: 3, activo: false },
    ];

    it('las activas en el orden del catálogo, numeradas', () => {
        expect(opcionesEtapaPropio(etapas, null)).toEqual([
            { valor: '1', etiqueta: '1. Planeación' },
            { valor: '2', etiqueta: '2. Selección' },
        ]);
    });

    it('más la que ya tiene el propio, aunque esté inactiva', () => {
        expect(opcionesEtapaPropio(etapas, { etapa: { id: 3, nombre: 'Vieja', orden: 3 } }).at(-1))
            .toEqual({ valor: '3', etiqueta: '3. Vieja (inactiva)' });
    });

    it('sin catálogo, solo la actual', () => {
        expect(opcionesEtapaPropio(null, { etapa: { id: 3, nombre: 'Vieja', orden: 3 } })).toEqual([{ valor: '3', etiqueta: '3. Vieja' }]);
        expect(opcionesEtapaPropio(null, null)).toEqual([]);
    });
});

describe('formulario de un propio de contratación', () => {
    it('vacío para uno nuevo, sin etapa', () => {
        expect(formularioPropioContratacion(null)).toEqual({ titulo: '', descripcion: '', categoria: '', caracter: 'obligatorio', etapa_propia_id: '' });
    });

    it('con la etapa de la fila', () => {
        const propio = fila(1, etapa(4, 1), { propio: { titulo: 'T', descripcion: null, categoria: 'tecnico' }, caracter: 'opcional' });
        expect(formularioPropioContratacion(propio)).toEqual({ titulo: 'T', descripcion: '', categoria: 'tecnico', caracter: 'opcional', etapa_propia_id: '4' });
    });

    it('lo que espera la API: la etapa como número', () => {
        expect(datosPropioContratacion({ titulo: ' T ', descripcion: '', categoria: '', caracter: 'obligatorio', etapa_propia_id: '4' }))
            .toEqual({ titulo: 'T', descripcion: null, categoria: null, caracter: 'obligatorio', etapa_propia_id: 4 });
        expect(datosPropioContratacion({ titulo: 'T', descripcion: '', categoria: '', caracter: 'obligatorio', etapa_propia_id: '' }).etapa_propia_id).toBeNull();
    });

    it('se puede guardar con título y etapa', () => {
        expect(propioCompleto({ titulo: 'T', etapa_propia_id: '4' })).toBe(true);
        expect(propioCompleto({ titulo: '  ', etapa_propia_id: '4' })).toBe(false);
        expect(propioCompleto({ titulo: 'T', etapa_propia_id: '' })).toBe(false);
    });
});

describe('pendientes al cerrar', () => {
    const pendientes = {
        total: 4,
        por_etapa: [
            { etapa: etapa(3, 1, 'Planeación'), pendientes: 3 },
            { etapa: null, pendientes: 1 },
        ],
    };

    it('cuántos faltan y en qué etapas', () => {
        expect(textoPendientesCierre(pendientes)).toEqual({
            titulo: 'Faltan 4 requisitos por cumplir',
            etapas: ['1. Planeación: 3 pendientes', 'Sin etapa: 1 pendiente'],
        });
    });

    it('en singular', () => {
        expect(textoPendientesCierre({ total: 1, por_etapa: [{ etapa: etapa(3, 1, 'Planeación'), pendientes: 1 }] }).titulo)
            .toBe('Falta 1 requisito por cumplir');
    });

    it('sin pendientes, null', () => {
        expect(textoPendientesCierre({ total: 0, por_etapa: [] })).toBeNull();
        expect(textoPendientesCierre(undefined)).toBeNull();
    });

    it('el html escapa los nombres de las etapas', () => {
        const html = htmlPendientesCierre({ total: 1, por_etapa: [{ etapa: etapa(3, 1, '<b>X</b>'), pendientes: 1 }] });
        expect(html).toContain('&lt;b&gt;X&lt;/b&gt;');
        expect(html).not.toContain('<b>X</b>');
        expect(html).toContain('Puedes cerrarlo de todos modos');
    });
});

describe('textoEstadoExpediente', () => {
    it('borrador', () => {
        expect(textoEstadoExpediente({ estado: 'borrador' })).toBe('Borrador');
    });

    it('cerrado, con fecha y usuario si los hay', () => {
        const texto = textoEstadoExpediente({ estado: 'cerrado', fecha_cierre: '2026-11-18T15:00:00Z', usuario_cierre: 'Ana' });
        expect(texto).toMatch(/^Cerrado el .+ por Ana$/);
        expect(textoEstadoExpediente({ estado: 'cerrado', fecha_cierre: null, usuario_cierre: null })).toBe('Cerrado');
    });
});

describe('selección de plantillas para el expediente nuevo', () => {
    it('con una sola plantilla compatible, ya va elegida; con varias, ninguna', () => {
        expect(seleccionInicial([{ id: 7 }])).toEqual([7]);
        expect(seleccionInicial([{ id: 7 }, { id: 8 }])).toEqual([]);
        expect(seleccionInicial(undefined)).toEqual([]);
    });

    it('la primera elegida es la principal: se conserva el orden en que se eligen', () => {
        let seleccion = [];
        seleccion = alternarPlantillaNueva(seleccion, 8);
        seleccion = alternarPlantillaNueva(seleccion, 7);
        expect(seleccion).toEqual([8, 7]);
        seleccion = alternarPlantillaNueva(seleccion, 8);
        expect(seleccion).toEqual([7]);
    });
});

describe('textoSinPlantillas', () => {
    it('dice por qué no hay plantillas', () => {
        expect(textoSinPlantillas).toBe('No hay plantillas de contratación publicadas que correspondan a la modalidad, la fuente y el sector de este proceso.');
    });
});

describe('historial', () => {
    it('describe el cierre del expediente', () => {
        expect(describirEvento({ evento: 'expediente_cerrado', antes: {}, despues: {} })).toBe('Cerró el expediente');
    });
});

describe('avisoIncompatiblesProceso', () => {
    it('nombra las plantillas que ya no corresponden al proceso', () => {
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: false }, { nombre: 'B', compatible: true }])).toEqual({
            titulo: 'Una plantilla ya no corresponde a la modalidad, la fuente o el sector del proceso',
            detalle: 'A. El expediente la conserva tal como está.',
        });
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: false }, { nombre: 'B', compatible: false }]).detalle)
            .toBe('A, B. El expediente las conserva tal como están.');
    });

    it('una inactiva que sigue cubriendo el proceso se avisa como inactiva', () => {
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: false, corresponde: true, activa: false, publicada: true }])).toEqual({
            titulo: 'Una plantilla del expediente está inactiva',
            detalle: 'A. El expediente la conserva tal como está.',
        });
        expect(avisoIncompatiblesProceso([
            { nombre: 'A', compatible: false, corresponde: true, activa: false, publicada: true },
            { nombre: 'B', compatible: false, corresponde: true, activa: false, publicada: false },
        ])?.titulo).toBe('Algunas plantillas del expediente están inactivas');
    });

    it('una sin versión publicada se avisa como tal', () => {
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: false, corresponde: true, activa: true, publicada: false }])?.titulo)
            .toBe('Una plantilla ya no tiene una versión publicada');
    });

    it('si no cubre el proceso, eso es lo que se avisa aunque además esté inactiva', () => {
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: false, corresponde: false, activa: false, publicada: true }])?.titulo)
            .toBe('Una plantilla ya no corresponde a la modalidad, la fuente o el sector del proceso');
    });

    it('con razones distintas, da la de cada una', () => {
        expect(avisoIncompatiblesProceso([
            { nombre: 'A', compatible: false, corresponde: false, activa: true, publicada: true },
            { nombre: 'B', compatible: false, corresponde: true, activa: false, publicada: true },
            { nombre: 'C', compatible: true, corresponde: true, activa: true, publicada: true },
        ])).toEqual({
            titulo: 'Algunas plantillas ya no sirven al proceso',
            detalle: 'A (ya no corresponde a la modalidad, la fuente o el sector), B (inactiva). El expediente las conserva tal como están.',
        });
    });

    it('sin incompatibles, null', () => {
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: true }])).toBeNull();
        expect(avisoIncompatiblesProceso(undefined)).toBeNull();
    });
});
