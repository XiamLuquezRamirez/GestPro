import { describe, it, expect, vi } from 'vitest';
import { AVISO_SESION_EXPIRADA, crearRenovador, debeRenovar, tomarAvisoSesion } from './sesion';

const error401 = (config = {}) => ({ response: { status: 401 }, config });

describe('debeRenovar', () => {
    it('renueva un 401 de una petición normal cuando hay sesión', () => {
        expect(debeRenovar(error401(), true)).toBe(true);
    });

    it('no renueva sin sesión, con otro código, ni dos veces la misma petición', () => {
        expect(debeRenovar(error401(), false)).toBe(false);
        expect(debeRenovar({ response: { status: 403 }, config: {} }, true)).toBe(false);
        expect(debeRenovar({ config: {} }, true)).toBe(false);
        expect(debeRenovar(error401({ _renovada: true }), true)).toBe(false);
    });

    it('no renueva la propia renovación ni el inicio de sesión', () => {
        expect(debeRenovar(error401({ _sinRenovar: true }), true)).toBe(false);
    });
});

describe('crearRenovador', () => {
    it('hace una sola renovación aunque la pidan varias peticiones a la vez', async () => {
        const refrescar = vi.fn().mockResolvedValue('nuevo');
        const renovar = crearRenovador(refrescar);

        const resultados = await Promise.all([renovar(), renovar(), renovar()]);

        expect(refrescar).toHaveBeenCalledTimes(1);
        expect(resultados).toEqual(['nuevo', 'nuevo', 'nuevo']);
    });

    it('terminada una renovación, la siguiente vuelve a pedir', async () => {
        const refrescar = vi.fn().mockResolvedValueOnce('uno').mockResolvedValueOnce('dos');
        const renovar = crearRenovador(refrescar);

        expect(await renovar()).toBe('uno');
        expect(await renovar()).toBe('dos');
    });

    it('si falla, todas las que esperaban reciben el fallo y se puede reintentar', async () => {
        const refrescar = vi.fn().mockRejectedValueOnce(new Error('vencida')).mockResolvedValueOnce('nuevo');
        const renovar = crearRenovador(refrescar);

        await expect(Promise.all([renovar(), renovar()])).rejects.toThrow('vencida');
        expect(refrescar).toHaveBeenCalledTimes(1);
        expect(await renovar()).toBe('nuevo');
    });
});

describe('tomarAvisoSesion', () => {
    const almacen = (inicial = {}) => {
        const datos = { ...inicial };
        return {
            getItem: (k) => (k in datos ? datos[k] : null),
            setItem: (k, v) => { datos[k] = v; },
            removeItem: (k) => { delete datos[k]; },
        };
    };

    it('devuelve el aviso una sola vez', () => {
        const a = almacen({ 'gestpro:aviso-sesion': 'expirada' });
        expect(tomarAvisoSesion(a)).toBe(AVISO_SESION_EXPIRADA);
        expect(tomarAvisoSesion(a)).toBeNull();
    });

    it('sin aviso, o si el almacenamiento falla, no dice nada', () => {
        expect(tomarAvisoSesion(almacen())).toBeNull();
        expect(tomarAvisoSesion({ getItem: () => { throw new Error('bloqueado'); } })).toBeNull();
    });
});
