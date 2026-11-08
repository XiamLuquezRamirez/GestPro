import { describe, it, expect } from 'vitest';
import {
    ESTADO,
    avanceRequisitos,
    construirFilas,
    contarPorEstado,
    esFormulacion,
    filtrarFilas,
    rutaExpediente,
    textoExpediente,
    textoAvanceProyecto,
} from './expedientesResumen';

const proyecto = (id, nombre, municipio = 'MEDELLÍN', fase = 'Formulación') => ({
    id,
    nombre,
    descripcion_municipio: municipio,
    descripcion_fase: fase,
});

const resumen = (proyectoId, estado, total, cumplidos, extra = {}) => ({
    proyecto_id: proyectoId,
    cantidad_expedientes: 1,
    expediente_id: proyectoId * 10,
    estado,
    fase_sgr: 3,
    plantillas: ['SGR'],
    tiene_sgr: true,
    configurado: true,
    fecha_radicado: null,
    actualizado: '2026-11-04T10:00:00-05:00',
    progreso: { total, cumplidos, porcentaje: total ? Math.round((cumplidos / total) * 100) : 0 },
    ...extra,
});

describe('construirFilas', () => {
    it('incluye los proyectos sin expediente', () => {
        const [fila] = construirFilas([proyecto(1, 'Acueducto')], []);

        expect(fila).toMatchObject({
            proyectoId: 1,
            nombre: 'Acueducto',
            municipio: 'MEDELLÍN',
            faseProyecto: 'Formulación',
            estado: ESTADO.SIN_EXPEDIENTE,
            expedienteId: null,
            pendientes: 0,
            porcentaje: 0,
        });
    });

    it('combina el proyecto con su expediente y calcula los pendientes', () => {
        const [fila] = construirFilas([proyecto(1, 'Acueducto')], [resumen(1, 'borrador', 8, 3, { cantidad_expedientes: 2 })]);

        expect(fila).toMatchObject({
            estado: ESTADO.BORRADOR,
            expedienteId: 10,
            cantidad: 2,
            faseSgr: 3,
            total: 8,
            cumplidos: 3,
            pendientes: 5,
            porcentaje: 38,
        });
    });

    it('ordena borradores con más pendientes, luego sin expediente y al final radicados', () => {
        const filas = construirFilas(
            [
                proyecto(1, 'Radicado'),
                proyecto(2, 'Zeta sin expediente'),
                proyecto(3, 'Ávila sin expediente'),
                proyecto(4, 'Borrador casi listo'),
                proyecto(5, 'Borrador atrasado'),
            ],
            [resumen(1, 'radicado', 8, 8), resumen(4, 'borrador', 8, 7), resumen(5, 'borrador', 8, 1)],
        );

        expect(filas.map((f) => f.proyectoId)).toEqual([5, 4, 3, 2, 1]);
    });

    it('ignora resúmenes de proyectos que no están en la lista', () => {
        expect(construirFilas([proyecto(1, 'Acueducto')], [resumen(99, 'borrador', 8, 1)])).toHaveLength(1);
    });

    it('tolera listas vacías o un nombre ausente', () => {
        expect(construirFilas([], [])).toEqual([]);
        expect(construirFilas([{ id: 1 }, proyecto(2, 'B')], [])).toHaveLength(2);
    });
});

describe('contarPorEstado', () => {
    it('cuenta proyectos por estado y suma los pendientes solo de los borradores', () => {
        const filas = construirFilas(
            [proyecto(1, 'A'), proyecto(2, 'B'), proyecto(3, 'C'), proyecto(4, 'D')],
            [resumen(1, 'borrador', 8, 3), resumen(2, 'borrador', 4, 1), resumen(3, 'radicado', 8, 8)],
        );

        expect(contarPorEstado(filas)).toEqual({
            todos: 4,
            [ESTADO.SIN_CONFIGURAR]: 0,
            [ESTADO.SIN_EXPEDIENTE]: 1,
            [ESTADO.BORRADOR]: 2,
            [ESTADO.RADICADO]: 1,
            pendientesEnBorrador: 8,
        });
    });
});

describe('filtrarFilas', () => {
    const filas = construirFilas(
        [proyecto(1, 'Construcción sede', 'MEDELLÍN'), proyecto(2, 'Acueducto', 'BELLO'), proyecto(3, 'Vía rural', 'ITAGÜÍ')],
        [resumen(1, 'borrador', 8, 3)],
    );
    const ids = (lista) => lista.map((f) => f.proyectoId);

    it('sin filtros devuelve todo', () => {
        expect(filtrarFilas(filas, { estado: 'todos', texto: '' })).toHaveLength(3);
    });

    it('filtra por estado', () => {
        expect(ids(filtrarFilas(filas, { estado: ESTADO.BORRADOR, texto: '' }))).toEqual([1]);
    });

    it('busca por nombre o municipio sin distinguir tildes ni mayúsculas', () => {
        expect(ids(filtrarFilas(filas, { estado: 'todos', texto: 'construccion' }))).toEqual([1]);
        expect(ids(filtrarFilas(filas, { estado: 'todos', texto: 'itagui' }))).toEqual([3]);
        expect(ids(filtrarFilas(filas, { estado: 'todos', texto: '  BELLO ' }))).toEqual([2]);
    });

    it('combina estado y texto', () => {
        expect(filtrarFilas(filas, { estado: ESTADO.BORRADOR, texto: 'bello' })).toEqual([]);
    });
});

describe('rutaExpediente', () => {
    it('abre el expediente concreto o, si no hay, la página del proyecto', () => {
        const [conExpediente, sinExpediente] = construirFilas(
            [proyecto(1, 'A'), proyecto(2, 'B')],
            [resumen(1, 'borrador', 8, 3)],
        );

        expect(rutaExpediente(conExpediente)).toBe('/viabilizacion/1?expediente=10');
        expect(rutaExpediente(sinExpediente)).toBe('/viabilizacion/2');
    });
});

describe('expedientes sin configurar', () => {
    const sinConfigurar = resumen(1, 'borrador', 8, 0, { configurado: false });

    it('tienen estado propio y no muestran avance ni pendientes', () => {
        const [fila] = construirFilas([proyecto(1, 'A')], [sinConfigurar]);

        expect(fila).toMatchObject({ estado: ESTADO.SIN_CONFIGURAR, expedienteId: 10, porcentaje: null, pendientes: 0 });
    });

    it('van primero, porque son lo que requiere acción', () => {
        const filas = construirFilas(
            [proyecto(1, 'A'), proyecto(2, 'B'), proyecto(3, 'C')],
            [resumen(2, 'borrador', 8, 1, { configurado: true }), sinConfigurar],
        );

        expect(filas.map((f) => f.proyectoId)).toEqual([1, 2, 3]);
    });

    it('se cuentan aparte y sus requisitos no suman como pendientes', () => {
        const conteos = contarPorEstado(construirFilas([proyecto(1, 'A')], [sinConfigurar]));

        expect(conteos[ESTADO.SIN_CONFIGURAR]).toBe(1);
        expect(conteos[ESTADO.BORRADOR]).toBe(0);
        expect(conteos.pendientesEnBorrador).toBe(0);
    });
});

describe('esFormulacion', () => {
    it('reconoce la fase sin importar tildes ni mayúsculas', () => {
        expect(esFormulacion('Formulación')).toBe(true);
        expect(esFormulacion(' FORMULACION ')).toBe(true);
        expect(esFormulacion('Licitación')).toBe(false);
        expect(esFormulacion(undefined)).toBe(false);
    });
});

describe('avanceRequisitos', () => {
    const proyectos = [proyecto(1, 'A'), proyecto(2, 'B'), proyecto(3, 'C'), proyecto(4, 'D')];

    it('promedia solo los proyectos con expediente configurado de la lista', () => {
        const avance = avanceRequisitos(proyectos, [
            resumen(1, 'borrador', 10, 5, { configurado: true }),
            resumen(2, 'radicado', 8, 8, { configurado: true }),
            resumen(3, 'borrador', 8, 0, { configurado: false }),
            resumen(99, 'borrador', 8, 0, { configurado: true }),
        ]);

        expect(avance).toEqual({ pct: 75, medidos: 2, total: 4 });
    });

    it('sin nada que medir, no inventa un 0 %', () => {
        expect(avanceRequisitos(proyectos, [])).toEqual({ pct: null, medidos: 0, total: 4 });
        expect(avanceRequisitos(proyectos, null)).toEqual({ pct: null, medidos: 0, total: 4 });
    });
});

describe('textoAvanceProyecto', () => {
    it('describe cada situación del proyecto', () => {
        const resumenes = [
            resumen(1, 'borrador', 8, 3, { configurado: true }),
            resumen(2, 'borrador', 8, 0, { configurado: false }),
            resumen(3, 'radicado', 8, 8, { configurado: true }),
        ];

        expect(textoAvanceProyecto(1, resumenes)).toBe('38% (3 de 8)');
        expect(textoAvanceProyecto(2, resumenes)).toBe('Sin configurar');
        expect(textoAvanceProyecto(3, resumenes)).toBe('100% (8 de 8) · Radicado');
        expect(textoAvanceProyecto(4, resumenes)).toBe('Sin expediente');
        expect(textoAvanceProyecto(1, null)).toBe('—');
    });
});

describe('plantillas y fase SGR en el seguimiento', () => {
    it('la fila lleva las plantillas del expediente y si tiene SGR', () => {
        const [fila] = construirFilas([proyecto(1, 'Acueducto')], [resumen(1, 'borrador', 8, 3, { plantillas: ['SGR', 'Obras de agua'] })]);
        expect(fila).toMatchObject({ plantillas: ['SGR', 'Obras de agua'], tieneSgr: true, faseSgr: 3 });
    });

    it('sin expediente no hay plantillas ni SGR', () => {
        const [fila] = construirFilas([proyecto(1, 'Acueducto')], []);
        expect(fila).toMatchObject({ plantillas: [], tieneSgr: false });
    });

    it('un expediente con plantilla sgr muestra su fase SGR', () => {
        const [fila] = construirFilas([proyecto(1, 'Acueducto')], [resumen(1, 'borrador', 8, 3)]);
        expect(textoExpediente(fila)).toBe('#10 · Fase SGR III — Factibilidad');
    });

    it('un expediente sin plantilla sgr no muestra "Fase SGR"', () => {
        const [fila] = construirFilas([proyecto(1, 'Acueducto')], [resumen(1, 'borrador', 8, 3, { plantillas: ['Obras de agua'], tiene_sgr: false })]);
        expect(textoExpediente(fila)).toBe('#10');
        expect(textoExpediente(fila)).not.toContain('Fase SGR');
    });

    it('sin configurar o sin expediente, tampoco', () => {
        const [sinConfigurar] = construirFilas([proyecto(1, 'Acueducto')], [resumen(1, 'borrador', 0, 0, { configurado: false })]);
        expect(textoExpediente(sinConfigurar)).toBe('#10');
        const [sinExpediente] = construirFilas([proyecto(2, 'Vía')], []);
        expect(textoExpediente(sinExpediente)).toBe('—');
    });

    it('un resumen anterior a las plantillas no rompe la fila', () => {
        const viejo = resumen(1, 'borrador', 8, 3);
        delete viejo.plantillas;
        delete viejo.tiene_sgr;
        const [fila] = construirFilas([proyecto(1, 'Acueducto')], [viejo]);
        expect(fila).toMatchObject({ plantillas: [], tieneSgr: false });
    });
});
