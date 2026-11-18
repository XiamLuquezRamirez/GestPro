import { describe, it, expect } from 'vitest';
import { formatoMoneda, limpiarMoneda, monedaEnEdicion, numeroDeMoneda } from './moneda';

// Intl separa el símbolo con un espacio duro: se compara con espacios normales.
const normal = (texto) => texto.replace(/\s/g, ' ');

describe('formatoMoneda', () => {
    it('pesos colombianos con dos decimales', () => {
        expect(normal(formatoMoneda(1234567.5))).toBe('$ 1.234.567,50');
        expect(normal(formatoMoneda(0))).toBe('$ 0,00');
    });
});

describe('numeroDeMoneda', () => {
    it('lee lo escrito con miles y coma decimal', () => {
        expect(numeroDeMoneda('$ 1.487.342,33')).toBe(1487342.33);
        expect(numeroDeMoneda('1500000')).toBe(1500000);
        expect(numeroDeMoneda(2500)).toBe(2500);
    });

    it('vacío o sin cifras es cero', () => {
        expect(numeroDeMoneda('')).toBe(0);
        expect(numeroDeMoneda(null)).toBe(0);
        expect(numeroDeMoneda(undefined)).toBe(0);
        expect(numeroDeMoneda('$ ')).toBe(0);
    });
});

describe('limpiarMoneda', () => {
    it('deja cifras y una coma con hasta dos decimales', () => {
        expect(limpiarMoneda('$ 1.487.342')).toBe('1487342');
        expect(limpiarMoneda('1487342,339')).toBe('1487342,33');
        expect(limpiarMoneda('12,3,4')).toBe('12,34');
        expect(limpiarMoneda('abc')).toBe('');
    });
});

describe('monedaEnEdicion', () => {
    it('agrupa los miles y conserva la parte decimal escrita', () => {
        expect(monedaEnEdicion('1487342')).toBe('$ 1.487.342');
        expect(monedaEnEdicion('1487342,')).toBe('$ 1.487.342,');
        expect(monedaEnEdicion('1487342,3')).toBe('$ 1.487.342,3');
        expect(monedaEnEdicion(',5')).toBe('$ 0,5');
    });
});
