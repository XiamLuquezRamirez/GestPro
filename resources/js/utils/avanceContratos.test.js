import { describe, it, expect } from 'vitest';
import { calcularDesfase, severidadDesfase, aplanarContratos, construirSerieTemporal } from './avanceContratos';

describe('calcularDesfase', () => {
    it('resta financiero menos físico', () => {
        expect(calcularDesfase({ avance_fisico: 35, avance_financiero: 78 })).toBe(43);
    });

    it('puede ser negativo cuando lo ejecutado supera lo facturado', () => {
        expect(calcularDesfase({ avance_fisico: 70, avance_financiero: 55 })).toBe(-15);
    });

    it('trata null como 0', () => {
        expect(calcularDesfase({ avance_fisico: null, avance_financiero: 40 })).toBe(40);
    });
});

describe('severidadDesfase', () => {
    it('clasifica 10 como sano (borde inferior)', () => {
        expect(severidadDesfase(10)).toBe('sano');
    });

    it('clasifica 11 como atencion', () => {
        expect(severidadDesfase(11)).toBe('atencion');
    });

    it('clasifica 25 como atencion (borde superior)', () => {
        expect(severidadDesfase(25)).toBe('atencion');
    });

    it('clasifica 26 como critico', () => {
        expect(severidadDesfase(26)).toBe('critico');
    });

    it('un desfase negativo es sano', () => {
        expect(severidadDesfase(-15)).toBe('sano');
    });
});

describe('aplanarContratos', () => {
    const proyectos = [
        {
            id: 1,
            nombre: 'Acueducto Rural',
            contratos: [
                { id: 10, n_contrato: 'C-001', valor: '890000000.00', avance_fisico: 65, avance_financiero: 90, actividades: [], avancesFinancieros: [] },
                { id: 11, n_contrato: 'C-002', valor: '1240000000.00', avance_fisico: null, avance_financiero: null, actividades: [], avancesFinancieros: [] },
            ],
        },
        {
            id: 2,
            nombre: 'Parque Central',
            contratos: [
                { id: 12, n_contrato: 'C-005', valor: '620000000.00', avance_fisico: 42, avance_financiero: 58, actividades: [], avancesFinancieros: [] },
            ],
        },
    ];

    it('extrae todos los contratos de todos los proyectos', () => {
        expect(aplanarContratos(proyectos)).toHaveLength(3);
    });

    it('conserva el nombre del proyecto en cada contrato', () => {
        const filas = aplanarContratos(proyectos);
        expect(filas.find(c => c.id === 12).nombreProyecto).toBe('Parque Central');
    });

    it('calcula desfase y severidad de los contratos con datos', () => {
        const fila = aplanarContratos(proyectos).find(c => c.id === 10);
        expect(fila.desfase).toBe(25);
        expect(fila.severidad).toBe('atencion');
        expect(fila.sinDatos).toBe(false);
    });

    it('marca como sinDatos los contratos sin avances registrados', () => {
        const fila = aplanarContratos(proyectos).find(c => c.id === 11);
        expect(fila.sinDatos).toBe(true);
    });

    it('devuelve arreglo vacío si no hay proyectos', () => {
        expect(aplanarContratos([])).toEqual([]);
        expect(aplanarContratos(null)).toEqual([]);
    });
});

describe('construirSerieTemporal', () => {
    const contrato = {
        valor: '1000000000.00',
        actividades: [
            {
                id: 1, nombre: 'Excavación', peso: 60,
                avances: [
                    { fecha: '2026-01-31', porcentaje_ejecucion: 50 },
                    { fecha: '2026-03-31', porcentaje_ejecucion: 100 },
                ],
            },
            {
                id: 2, nombre: 'Estructura', peso: 40,
                avances: [
                    { fecha: '2026-03-31', porcentaje_ejecucion: 25 },
                ],
            },
        ],
        avancesFinancieros: [
            { fecha_acta: '2026-02-28', valor_facturado: '200000000.00' },
            { fecha_acta: '2026-03-31', valor_facturado: '300000000.00' },
        ],
    };

    it('genera un punto por cada fecha de corte, ordenadas', () => {
        const serie = construirSerieTemporal(contrato);
        expect(serie.map(p => p.fecha)).toEqual(['2026-01-31', '2026-02-28', '2026-03-31']);
    });

    it('calcula el físico ponderado usando el último avance hasta cada fecha', () => {
        const serie = construirSerieTemporal(contrato);
        // 31-ene: Excavación 50% × peso 60 = 30; Estructura sin avances = 0
        expect(serie[0].fisico).toBe(30);
        // 28-feb: sin avances nuevos, se mantiene 30
        expect(serie[1].fisico).toBe(30);
        // 31-mar: Excavación 100% × 60 = 60; Estructura 25% × 40 = 10 → 70
        expect(serie[2].fisico).toBe(70);
    });

    it('acumula el financiero sobre valor_facturado, no lo reemplaza', () => {
        const serie = construirSerieTemporal(contrato);
        // 31-ene: sin actas todavía
        expect(serie[0].financiero).toBe(0);
        // 28-feb: 200M / 1000M = 20%
        expect(serie[1].financiero).toBe(20);
        // 31-mar: (200M + 300M) / 1000M = 50% (no 30%)
        expect(serie[2].financiero).toBe(50);
    });

    it('incluye la brecha en cada punto', () => {
        const serie = construirSerieTemporal(contrato);
        expect(serie[2].brecha).toBe(-20); // 50 financiero − 70 físico
    });

    it('devuelve arreglo vacío si el contrato no tiene valor vigente', () => {
        expect(construirSerieTemporal({ ...contrato, valor: 0 })).toEqual([]);
        expect(construirSerieTemporal({ ...contrato, valor: null })).toEqual([]);
    });

    it('devuelve arreglo vacío si no hay ninguna fecha de corte', () => {
        expect(construirSerieTemporal({ valor: '1000000000.00', actividades: [], avancesFinancieros: [] })).toEqual([]);
    });
});
