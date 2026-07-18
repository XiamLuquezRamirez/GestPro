import { describe, it, expect } from 'vitest';
import { calcularDesfase, severidadDesfase, aplanarContratos } from './avanceContratos';

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
