import { describe, it, expect } from 'vitest';
import { calcularKpis } from './kpisDashboard';

describe('calcularKpis', () => {
    const proyectos = [
        {
            id: 1,
            descripcion_estado: 'En ejecución',
            descripcion_fase: 'Ejecución',
            contratos: [
                {
                    id: 10,
                    valor: '600000000.00',
                    avancesFinancieros: [{ valor_facturado: '200000000.00' }],
                },
                {
                    id: 11,
                    valor: '400000000.00',
                    avancesFinancieros: [],
                },
            ],
        },
        {
            id: 2,
            descripcion_estado: 'Con retraso',
            descripcion_fase: 'Formulación',
            contratos: [],
        },
        {
            id: 3,
            descripcion_estado: 'Con retraso',
            descripcion_fase: 'Formulación',
            contratos: [],
        },
    ];

    it('cuenta el total de proyectos', () => {
        expect(calcularKpis(proyectos).total).toBe(3);
    });

    it('cuenta el total de contratos sumando los de cada proyecto', () => {
        expect(calcularKpis(proyectos).totalContratos).toBe(2);
    });

    it('cuenta los proyectos con retraso', () => {
        expect(calcularKpis(proyectos).enRiesgo).toBe(2);
    });

    it('calcula el porcentaje de ejecución financiera', () => {
        // 200M facturado / 1000M contratado = 20%
        expect(calcularKpis(proyectos).pctEjecucion).toBe(20);
    });

    it('pctEjecucion es null cuando no hay contratos (para mostrar guion)', () => {
        const sinContratos = [{ id: 4, descripcion_fase: 'Formulación', contratos: [] }];
        expect(calcularKpis(sinContratos).pctEjecucion).toBeNull();
    });

    it('cuenta proyectos contratados, no contratos totales', () => {
        // El proyecto 1 tiene 2 contratos pero cuenta como 1 proyecto contratado.
        expect(calcularKpis(proyectos).contratados).toBe(1);
    });

    it('un proyecto con arreglo de contratos vacío no cuenta como contratado', () => {
        const vacios = [
            { id: 5, descripcion_fase: 'Licitación', contratos: [] },
            { id: 6, descripcion_fase: 'Licitación', contratos: [{ id: 60, valor: '100.00', avancesFinancieros: [] }] },
        ];
        expect(calcularKpis(vacios).contratados).toBe(1);
    });

    it('agrupa por fase y ordena alfabéticamente', () => {
        expect(calcularKpis(proyectos).porFase).toEqual([
            { fase: 'Ejecución', cantidad: 1 },
            { fase: 'Formulación', cantidad: 2 },
        ]);
    });

    it('agrupa como "Sin fase" los proyectos sin fase asignada', () => {
        const sinFase = [{ id: 7, contratos: [] }];
        expect(calcularKpis(sinFase).porFase).toEqual([{ fase: 'Sin fase', cantidad: 1 }]);
    });

    it('una lista vacía devuelve ceros, pctEjecucion nulo y porFase vacío', () => {
        const r = calcularKpis([]);
        expect(r.total).toBe(0);
        expect(r.totalContratos).toBe(0);
        expect(r.enRiesgo).toBe(0);
        expect(r.contratados).toBe(0);
        expect(r.pctEjecucion).toBeNull();
        expect(r.porFase).toEqual([]);
    });

    it('tolera entradas nulas o mal formadas', () => {
        expect(calcularKpis(null).total).toBe(0);
        expect(calcularKpis(undefined).total).toBe(0);
        const raros = [{ id: 8, contratos: null }];
        expect(calcularKpis(raros).totalContratos).toBe(0);
        expect(calcularKpis(raros).contratados).toBe(0);
    });

    it('tolera contratos sin actas financieras', () => {
        const sinActas = [{
            id: 9, descripcion_fase: 'Ejecución',
            contratos: [{ id: 90, valor: '500000000.00' }],
        }];
        const r = calcularKpis(sinActas);
        expect(r.pctEjecucion).toBe(0);
        expect(r.contratados).toBe(1);
    });
});
