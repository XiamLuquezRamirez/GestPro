import { describe, it, expect } from 'vitest';
import { calcularCadenaPresupuestal } from './cadenaPresupuestal';

describe('calcularCadenaPresupuestal', () => {
    // Los valores llegan de la API como strings decimales.
    const proyectos = [
        {
            id: 1,
            presupuesto: '1000000000.00',
            contratos: [
                {
                    id: 10,
                    valor: '600000000.00',
                    avancesFinancieros: [
                        { valor_facturado: '200000000.00' },
                        { valor_facturado: '100000000.00' },
                    ],
                },
                {
                    id: 11,
                    valor: '300000000.00',
                    avancesFinancieros: [],
                },
            ],
        },
        {
            id: 2,
            presupuesto: '500000000.00',
            contratos: [],
        },
    ];

    it('suma el presupuesto planeado de todos los proyectos', () => {
        expect(calcularCadenaPresupuestal(proyectos).planeado).toBe(1500000000);
    });

    it('suma el valor de todos los contratos', () => {
        expect(calcularCadenaPresupuestal(proyectos).contratado).toBe(900000000);
    });

    it('suma lo facturado de todas las actas', () => {
        expect(calcularCadenaPresupuestal(proyectos).ejecutado).toBe(300000000);
    });

    it('un proyecto sin contratos aporta a planeado pero no a contratado ni ejecutado', () => {
        const soloPlaneado = [{ id: 3, presupuesto: '800000000.00', contratos: [] }];
        const r = calcularCadenaPresupuestal(soloPlaneado);
        expect(r.planeado).toBe(800000000);
        expect(r.contratado).toBe(0);
        expect(r.ejecutado).toBe(0);
    });

    it('un contrato sin actas aporta a contratado pero no a ejecutado', () => {
        const sinActas = [{
            id: 4, presupuesto: '100000000.00',
            contratos: [{ id: 40, valor: '90000000.00', avancesFinancieros: [] }],
        }];
        const r = calcularCadenaPresupuestal(sinActas);
        expect(r.contratado).toBe(90000000);
        expect(r.ejecutado).toBe(0);
    });

    it('calcula el porcentaje contratado sobre lo planeado, redondeado', () => {
        // 900M / 1500M = 60%
        expect(calcularCadenaPresupuestal(proyectos).pctContratado).toBe(60);
    });

    it('calcula el porcentaje ejecutado sobre lo contratado, redondeado', () => {
        // 300M / 900M = 33.33 -> 33
        expect(calcularCadenaPresupuestal(proyectos).pctEjecutado).toBe(33);
    });

    it('pctContratado es null cuando no hay planeado (no divide por cero)', () => {
        const sinPlaneado = [{
            id: 5, presupuesto: '0.00',
            contratos: [{ id: 50, valor: '100000000.00', avancesFinancieros: [] }],
        }];
        expect(calcularCadenaPresupuestal(sinPlaneado).pctContratado).toBeNull();
    });

    it('pctEjecutado es null cuando no hay contratado', () => {
        const sinContratos = [{ id: 6, presupuesto: '100000000.00', contratos: [] }];
        expect(calcularCadenaPresupuestal(sinContratos).pctEjecutado).toBeNull();
    });

    it('excedente es la diferencia cuando lo contratado supera lo planeado', () => {
        const sobreContratado = [{
            id: 7, presupuesto: '100000000.00',
            contratos: [{ id: 70, valor: '150000000.00', avancesFinancieros: [] }],
        }];
        expect(calcularCadenaPresupuestal(sobreContratado).excedente).toBe(50000000);
    });

    it('excedente es 0 cuando lo contratado no supera lo planeado', () => {
        expect(calcularCadenaPresupuestal(proyectos).excedente).toBe(0);
    });

    it('una lista vacía devuelve ceros y porcentajes nulos', () => {
        const r = calcularCadenaPresupuestal([]);
        expect(r.planeado).toBe(0);
        expect(r.contratado).toBe(0);
        expect(r.ejecutado).toBe(0);
        expect(r.pctContratado).toBeNull();
        expect(r.pctEjecutado).toBeNull();
        expect(r.excedente).toBe(0);
    });

    it('tolera entradas nulas o mal formadas sin romperse', () => {
        expect(calcularCadenaPresupuestal(null).planeado).toBe(0);
        expect(calcularCadenaPresupuestal(undefined).planeado).toBe(0);
        const raros = [{ id: 8, presupuesto: null, contratos: null }];
        expect(calcularCadenaPresupuestal(raros).planeado).toBe(0);
    });
});
