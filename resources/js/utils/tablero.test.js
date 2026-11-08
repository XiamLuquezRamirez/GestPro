import { describe, it, expect } from 'vitest';
import {
    VISTA,
    escalaSala,
    filasEnSala,
    formatearMillones,
    resolverVista,
    resumirFases,
    secuenciaSala,
    siguienteVista,
    slugFase,
} from './tablero';

const fases = [
    { id: 1, nombre: 'Formulación', color: '#f00', dashboard: 1 },
    { id: 2, nombre: 'Licitación', color: '#0f0', dashboard: 1 },
    { id: 3, nombre: 'Ejecución', color: '#00f', dashboard: 1 },
];

describe('slugFase', () => {
    it('quita tildes y mayúsculas', () => {
        expect(slugFase('Formulación')).toBe('formulacion');
        expect(slugFase('Pre Inversión')).toBe('pre-inversion');
    });
});

describe('resolverVista', () => {
    it('sin vista, abre la primera fase', () => {
        expect(resolverVista(null, fases)).toBe('formulacion');
    });

    it('acepta una fase o una vista analítica', () => {
        expect(resolverVista('ejecucion', fases)).toBe('ejecucion');
        expect(resolverVista(VISTA.CONTRATOS, fases)).toBe(VISTA.CONTRATOS);
    });

    it('una vista desconocida cae en la primera fase', () => {
        expect(resolverVista('nada', fases)).toBe('formulacion');
    });

    it('con la vista pedida respeta la analítica aunque aún no carguen las fases', () => {
        expect(resolverVista(VISTA.EXPEDIENTES, [])).toBe(VISTA.EXPEDIENTES);
        expect(resolverVista(null, [])).toBeNull();
    });
});

describe('resumirFases', () => {
    const proyectos = [
        { id: 1, descripcion_fase: 'Formulación', presupuesto: '1000000', descripcion_estado: 'Con retraso' },
        { id: 2, descripcion_fase: 'Formulación', presupuesto: '2000000', descripcion_estado: 'Activo' },
        { id: 3, descripcion_fase: 'Ejecución', presupuesto: '4000000', contratos: [] },
    ];

    it('resume cantidad, presupuesto, riesgo y proporción de cada fase', () => {
        const [formulacion, licitacion] = resumirFases(fases, proyectos, []);

        expect(formulacion).toMatchObject({ slug: 'formulacion', cantidad: 2, presupuesto: 3000000, enRiesgo: 1, proporcion: 67 });
        expect(licitacion).toMatchObject({ cantidad: 0, presupuesto: 0, proporcion: 0 });
    });

    it('en Formulación mide requisitos, no contratos', () => {
        const resumenes = [{
            proyecto_id: 1,
            estado: 'borrador',
            configurado: true,
            progreso: { total: 10, cumplidos: 5, porcentaje: 50 },
        }];

        expect(resumirFases(fases, proyectos, resumenes)[0].metrica).toEqual({ pct: 50, etiqueta: 'Requisitos', detalle: '1 de 2 configurados' });
    });

    it('sin datos no inventa un 0 %', () => {
        expect(resumirFases(fases, proyectos, null)[0].metrica.pct).toBeNull();
        expect(resumirFases(fases, proyectos, [])[2].metrica).toMatchObject({ pct: null, etiqueta: 'Ejecución financiera' });
    });

    it('sin proyectos no divide por cero', () => {
        expect(resumirFases(fases, [], [])[0].proporcion).toBe(0);
    });
});

describe('modo sala', () => {
    it('recorre las fases y luego contratos y expedientes', () => {
        expect(secuenciaSala(fases)).toEqual(['formulacion', 'licitacion', 'ejecucion', VISTA.CONTRATOS, VISTA.EXPEDIENTES]);
    });

    it('avanza en círculo', () => {
        const secuencia = ['a', 'b', 'c'];

        expect(siguienteVista(secuencia, 'a')).toBe('b');
        expect(siguienteVista(secuencia, 'c')).toBe('a');
        expect(siguienteVista(secuencia, 'x')).toBe('a');
        expect(siguienteVista([], 'x')).toBeNull();
    });

    it('muestra menos filas en pantallas bajas, medidas ya descontando la escala', () => {
        expect(filasEnSala(1080, 1920)).toBe(8);
        expect(filasEnSala(768, 1366)).toBe(6);
        expect(filasEnSala(720, 1280)).toBe(6);
        expect(filasEnSala(2160, 3840)).toBe(8); // 4K escala x2: equivale a 1080
        expect(filasEnSala(1080, 2560)).toBe(6); // ultrapanorámica: escala x1,33 deja 810 de alto
    });

    it('escala proporcionalmente por encima de 1920 y nunca reduce', () => {
        expect(escalaSala(1366)).toBe(1);
        expect(escalaSala(1920)).toBe(1);
        expect(escalaSala(3840)).toBe(2);
    });
});

describe('formatearMillones', () => {
    it('resume en millones', () => {
        expect(formatearMillones(1234567890).replace(/\s/g, ' ')).toBe('$1.235 M');
        expect(formatearMillones(null)).toBe('$0 M');
    });
});
