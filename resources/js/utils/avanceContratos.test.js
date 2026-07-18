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
                // Historial que rinde 65% físico y 90% financiero (desfase +25).
                {
                    id: 10, n_contrato: 'C-001', valor: '1000000000.00',
                    actividades: [{ id: 1, peso: 100, avances: [{ fecha: '2026-03-31', porcentaje_ejecucion: 65 }] }],
                    avancesFinancieros: [{ fecha_acta: '2026-03-31', valor_facturado: '900000000.00' }],
                },
                // Sin historial: no comparable.
                { id: 11, n_contrato: 'C-002', valor: '1240000000.00', actividades: [], avancesFinancieros: [] },
            ],
        },
        {
            id: 2,
            nombre: 'Parque Central',
            contratos: [
                {
                    id: 12, n_contrato: 'C-005', valor: '620000000.00',
                    actividades: [{ id: 2, peso: 100, avances: [{ fecha: '2026-03-31', porcentaje_ejecucion: 42 }] }],
                    avancesFinancieros: [{ fecha_acta: '2026-03-31', valor_facturado: '310000000.00' }],
                },
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

    it('grafica un contrato con historial de un solo lado, sin inventar el otro', () => {
        // Con actividades pero sin actas, el financiero es genuinamente 0%: se
        // facturó nada de lo ejecutado. Eso es información real (desfase negativo),
        // no ausencia de datos, así que sí entra al scatter.
        const soloFisico = [{
            id: 3,
            nombre: 'Proyecto Solo Físico',
            contratos: [{
                id: 20, n_contrato: 'C-020', valor: '500000000.00',
                actividades: [{ id: 1, peso: 100, avances: [{ fecha: '2026-03-31', porcentaje_ejecucion: 80 }] }],
                avancesFinancieros: [],
            }],
        }];

        const fila = aplanarContratos(soloFisico)[0];
        expect(fila.sinDatos).toBe(false);
        expect(fila.avanceFisico).toBe(80);
        expect(fila.avanceFinanciero).toBe(0);
        expect(fila.desfase).toBe(-80);
        expect(fila.severidad).toBe('sano');
    });

    it('devuelve arreglo vacío si no hay proyectos', () => {
        expect(aplanarContratos([])).toEqual([]);
        expect(aplanarContratos(null)).toEqual([]);
    });

    it('deriva los avances del historial, no de los campos guardados', () => {
        // Los campos avance_fisico/avance_financiero del contrato son un snapshot
        // entero que solo se refresca al guardar el formulario. El historial es la
        // fuente de verdad: scatter y gráfica de evolución deben coincidir.
        const desactualizados = [{
            id: 4,
            nombre: 'Proyecto Desactualizado',
            contratos: [{
                id: 30,
                n_contrato: 'C-030',
                valor: '1000000000.00',
                avance_fisico: 10,        // snapshot viejo
                avance_financiero: 15,    // snapshot viejo
                actividades: [
                    { id: 1, peso: 60, avances: [{ fecha: '2026-03-31', porcentaje_ejecucion: 50 }] },
                    { id: 2, peso: 40, avances: [{ fecha: '2026-03-31', porcentaje_ejecucion: 25 }] },
                ],
                avancesFinancieros: [
                    { fecha_acta: '2026-02-28', valor_facturado: '200000000.00' },
                    { fecha_acta: '2026-03-31', valor_facturado: '300000000.00' },
                ],
            }],
        }];

        const fila = aplanarContratos(desactualizados)[0];
        // Físico real: 50%×60 + 25%×40 = 40 (no el 10 guardado)
        expect(fila.avanceFisico).toBe(40);
        // Financiero real: (200M+300M)/1000M = 50 (no el 15 guardado)
        expect(fila.avanceFinanciero).toBe(50);
        expect(fila.desfase).toBe(10);
        expect(fila.severidad).toBe('sano');
        expect(fila.sinDatos).toBe(false);
    });

    it('marca sinDatos cuando el historial está vacío, ignorando los campos guardados', () => {
        const soloSnapshot = [{
            id: 5,
            nombre: 'Proyecto Sin Historial',
            contratos: [{
                id: 31, n_contrato: 'C-031', valor: '500000000.00',
                avance_fisico: 40, avance_financiero: 55,
                actividades: [], avancesFinancieros: [],
            }],
        }];

        expect(aplanarContratos(soloSnapshot)[0].sinDatos).toBe(true);
    });

    it('un contrato sin valor vigente no es comparable', () => {
        const sinValor = [{
            id: 6,
            nombre: 'Proyecto Sin Valor',
            contratos: [{
                id: 32, n_contrato: 'C-032', valor: null,
                avance_fisico: 30, avance_financiero: 40,
                actividades: [{ id: 1, peso: 100, avances: [{ fecha: '2026-03-31', porcentaje_ejecucion: 30 }] }],
                avancesFinancieros: [{ fecha_acta: '2026-03-31', valor_facturado: '100000000.00' }],
            }],
        }];

        expect(aplanarContratos(sinValor)[0].sinDatos).toBe(true);
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
