import { describe, it, expect } from 'vitest';
import { alternarFuente, datosFinanciacionDe, financiacionCompleta, fuentesDisponibles } from './fuentesProyecto';

const fuentes = [
    { id: 1, nombre: 'SGR', activo: true },
    { id: 2, nombre: 'PGN', activo: false },
    { id: 3, nombre: 'Propios', activo: true },
];

describe('fuentesDisponibles', () => {
    it('ofrece las activas', () => {
        expect(fuentesDisponibles(fuentes, []).map((f) => f.id)).toEqual([1, 3]);
    });

    it('conserva una inactiva si el proyecto ya la tiene', () => {
        expect(fuentesDisponibles(fuentes, [2]).map((f) => f.id)).toEqual([1, 2, 3]);
    });
});

describe('alternarFuente', () => {
    it('agrega una fuente y quita el por definir', () => {
        expect(alternarFuente({ fuenteIds: [1], financiacionPorDefinir: true }, 3))
            .toEqual({ fuenteIds: [1, 3], financiacionPorDefinir: false });
    });

    it('quita una fuente ya marcada', () => {
        expect(alternarFuente({ fuenteIds: [1, 3], financiacionPorDefinir: false }, 1))
            .toEqual({ fuenteIds: [3], financiacionPorDefinir: false });
    });
});

describe('financiacionCompleta', () => {
    it('exige una fuente o el por definir', () => {
        expect(financiacionCompleta({ fuenteIds: [], financiacionPorDefinir: false })).toBe(false);
        expect(financiacionCompleta({ fuenteIds: [1], financiacionPorDefinir: false })).toBe(true);
        expect(financiacionCompleta({ fuenteIds: [], financiacionPorDefinir: true })).toBe(true);
    });
});

describe('datosFinanciacionDe', () => {
    it('traduce el proyecto del listado al formato del formulario', () => {
        expect(datosFinanciacionDe({ fuente_ids: [1, 3], financiacion_por_definir: 0 }))
            .toEqual({ fuenteIds: [1, 3], financiacionPorDefinir: false });
        expect(datosFinanciacionDe({ fuente_ids: [], financiacion_por_definir: 1 }))
            .toEqual({ fuenteIds: [], financiacionPorDefinir: true });
    });

    it('tolera un elemento sin esos campos', () => {
        expect(datosFinanciacionDe({})).toEqual({ fuenteIds: [], financiacionPorDefinir: false });
    });
});
