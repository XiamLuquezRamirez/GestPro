import { describe, it, expect } from 'vitest';
import {
    CATALOGO_VACIO,
    REGLAS_ETAPA,
    REGLAS_MODALIDAD,
    datosCatalogo,
    erroresPorCampo,
    formularioCatalogo,
    idsTrasMover,
    ordenarCatalogo,
    puedeAdministrarCatalogos,
    textoEstadoCatalogo,
    validarCatalogo,
} from './contratacion';

const modalidades = [
    { id: 1, nombre: 'Licitación pública', clave: 'licitacion_publica', orden: 1, activo: true },
    { id: 2, nombre: 'Mínima cuantía', clave: 'minima_cuantia', orden: 5, activo: true },
];

describe('puedeAdministrarCatalogos', () => {
    it('solo un Administrador modifica modalidades y etapas', () => {
        expect(puedeAdministrarCatalogos({ rol: 'Administrador' })).toBe(true);
        expect(puedeAdministrarCatalogos({ rol: 'Gestor' })).toBe(false);
        expect(puedeAdministrarCatalogos({ rol: 'Consulta' })).toBe(false);
        expect(puedeAdministrarCatalogos(null)).toBe(false);
    });
});

describe('formularioCatalogo', () => {
    it('parte vacío o de lo que ya tiene el elemento, sin null en los campos', () => {
        expect(formularioCatalogo(null)).toEqual(CATALOGO_VACIO);
        expect(formularioCatalogo({ id: 3, nombre: 'Adendas', descripcion: null })).toEqual({ nombre: 'Adendas', descripcion: '' });
    });
});

describe('validarCatalogo', () => {
    it('exige el nombre', () => {
        expect(validarCatalogo({ nombre: '   ' }, modalidades, undefined, REGLAS_MODALIDAD)).toEqual({ nombre: 'Escribe el nombre de la modalidad.' });
        expect(validarCatalogo({ nombre: '' }, [], undefined, REGLAS_ETAPA)).toEqual({ nombre: 'Escribe el nombre de la etapa.' });
    });

    it('no repite un nombre sin contar mayúsculas, tildes ni espacios', () => {
        expect(validarCatalogo({ nombre: ' minima CUANTIA ' }, modalidades, undefined, REGLAS_MODALIDAD))
            .toEqual({ nombre: 'Ya existe una modalidad con ese nombre.' });
    });

    it('al editar, el nombre propio no cuenta como repetido', () => {
        expect(validarCatalogo({ nombre: 'Mínima cuantía' }, modalidades, 2, REGLAS_MODALIDAD)).toEqual({});
        expect(validarCatalogo({ nombre: 'Mínima cuantía' }, modalidades, 1, REGLAS_MODALIDAD)).toEqual({ nombre: 'Ya existe una modalidad con ese nombre.' });
    });

    it('aplica los largos del servidor a nombre y descripción', () => {
        expect(validarCatalogo({ nombre: 'x'.repeat(200) }, [], undefined, REGLAS_MODALIDAD)).toEqual({ nombre: 'El nombre admite hasta 199 caracteres.' });
        expect(validarCatalogo({ nombre: 'x'.repeat(199) }, [], undefined, REGLAS_MODALIDAD)).toEqual({});
        expect(validarCatalogo({ nombre: 'Etapa', descripcion: 'd'.repeat(2001) }, [], undefined, REGLAS_ETAPA))
            .toEqual({ descripcion: 'La descripción admite hasta 2000 caracteres.' });
        expect(validarCatalogo({ nombre: 'x'.repeat(121), descripcion: '' }, [], undefined, REGLAS_ETAPA)).toEqual({ nombre: 'El nombre admite hasta 120 caracteres.' });
    });

    it('las modalidades no tienen descripción que validar', () => {
        expect(validarCatalogo({ nombre: 'Otra', descripcion: 'd'.repeat(5000) }, [], undefined, REGLAS_MODALIDAD)).toEqual({});
    });
});

describe('datosCatalogo', () => {
    it('agrega o edita con el nombre recortado', () => {
        expect(datosCatalogo({ nombre: '  Régimen especial ' }, null)).toEqual({ accion: 'Agregar', nombre: 'Régimen especial' });
        expect(datosCatalogo({ nombre: 'Régimen especial' }, { id: 6 })).toEqual({ accion: 'Editar', id: 6, nombre: 'Régimen especial' });
    });

    it('en etapas envía la descripción, vacía como null', () => {
        expect(datosCatalogo({ nombre: 'Adendas', descripcion: '  ' }, { id: 6 }, true)).toEqual({ accion: 'Editar', id: 6, nombre: 'Adendas', descripcion: null });
        expect(datosCatalogo({ nombre: 'Adendas', descripcion: ' Capítulo 9 ' }, null, true)).toEqual({ accion: 'Agregar', nombre: 'Adendas', descripcion: 'Capítulo 9' });
    });
});

describe('ordenarCatalogo', () => {
    it('ordena por orden, luego por nombre y luego por id, sin cambiar la lista recibida', () => {
        const lista = [
            { id: 4, nombre: 'Zeta', orden: 2 },
            { id: 3, nombre: 'Beta', orden: 2 },
            { id: 9, nombre: 'Alfa', orden: 1 },
            { id: 1, nombre: 'Beta', orden: 2 },
        ];
        expect(ordenarCatalogo(lista).map((i) => i.id)).toEqual([9, 1, 3, 4]);
        expect(lista[0].id).toBe(4);
        expect(ordenarCatalogo(null)).toEqual([]);
    });
});

describe('idsTrasMover', () => {
    const etapas = [{ id: 10 }, { id: 20 }, { id: 30 }];

    it('devuelve la lista completa de ids con la etapa movida una posición', () => {
        expect(idsTrasMover(etapas, 1, -1)).toEqual([20, 10, 30]);
        expect(idsTrasMover(etapas, 1, 1)).toEqual([10, 30, 20]);
    });

    it('en los bordes no cambia el orden', () => {
        expect(idsTrasMover(etapas, 0, -1)).toEqual([10, 20, 30]);
        expect(idsTrasMover(etapas, 2, 1)).toEqual([10, 20, 30]);
    });
});

describe('erroresPorCampo', () => {
    const error = (status, data) => ({ response: { status, data } });

    it('reparte el primer mensaje de cada campo del formulario y aparta los demás', () => {
        const respuesta = error(422, { message: 'x', errors: { nombre: ['Ya existe una etapa con ese nombre.', 'Otro'], ids: ['El orden debe incluir todas las etapas.'] } });
        expect(erroresPorCampo(respuesta, ['nombre', 'descripcion'])).toEqual({
            campos: { nombre: 'Ya existe una etapa con ese nombre.' },
            otros: ['El orden debe incluir todas las etapas.'],
        });
    });

    it('acepta un mensaje suelto en lugar de lista', () => {
        expect(erroresPorCampo(error(422, { errors: { nombre: 'Escribe el nombre.' } }), ['nombre'])).toEqual({ campos: { nombre: 'Escribe el nombre.' }, otros: [] });
    });

    it('null si no es un error de validación por campos', () => {
        expect(erroresPorCampo(error(422, { mensaje: 'El proceso ya está en ese estado.' }), ['nombre'])).toBeNull();
        expect(erroresPorCampo(error(403, { message: 'This action is unauthorized.' }), ['nombre'])).toBeNull();
        expect(erroresPorCampo(new Error('red'), ['nombre'])).toBeNull();
    });
});

describe('textoEstadoCatalogo', () => {
    it('modalidades y etapas son femeninas', () => {
        expect(textoEstadoCatalogo(true)).toBe('Activa');
        expect(textoEstadoCatalogo(false)).toBe('Inactiva');
    });
});
