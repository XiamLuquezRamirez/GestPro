import { describe, it, expect } from 'vitest';
import {
    agruparVistaPrevia,
    datosPlantilla,
    datosRequisito,
    etiquetaCategoria,
    etiquetaVersion,
    filtrarPlantillas,
    formularioPlantilla,
    formularioRequisito,
    mover,
    opcionesCatalogo,
    requisitoVacio,
    resumenVersiones,
    textoAsociaciones,
    versionDeTrabajo,
} from './plantillasChecklist';

describe('formulario de la plantilla', () => {
    const plantilla = {
        nombre: 'Educación', descripcion: null, alcance: 'especifica', todas_fuentes: false, todos_sectores: false,
        fuentes: [{ id: 4, nombre: 'Recursos propios' }], sectores: [{ id: 2, nombre: 'Educación' }],
    };

    it('lee la plantilla y la devuelve en el formato de la API', () => {
        const formulario = formularioPlantilla(plantilla);
        expect(formulario).toEqual({
            nombre: 'Educación', descripcion: '', alcance: 'especifica', todas_fuentes: false, todos_sectores: false,
            fuente_ids: [4], sector_ids: [2],
        });
        expect(datosPlantilla({ ...formulario, nombre: '  Educación  ', descripcion: ' ' })).toEqual({
            nombre: 'Educación', descripcion: null, alcance: 'especifica', todas_fuentes: false, todos_sectores: false,
            fuente_ids: [4], sector_ids: [2],
        });
    });

    it('con "todas" envía la lista vacía', () => {
        const datos = datosPlantilla({ ...formularioPlantilla(plantilla), todas_fuentes: true, todos_sectores: true, alcance: '' });
        expect(datos.fuente_ids).toEqual([]);
        expect(datos.sector_ids).toEqual([]);
        expect(datos.alcance).toBeNull();
    });

    it('ofrece los activos y conserva los inactivos ya elegidos', () => {
        const items = [{ id: 1, activo: true }, { id: 2, activo: false }, { id: 3, activo: false }];
        expect(opcionesCatalogo(items, [2]).map((i) => i.id)).toEqual([1, 2]);
    });

    it('resume las asociaciones', () => {
        expect(textoAsociaciones(true, [], 'Todas las fuentes')).toBe('Todas las fuentes');
        expect(textoAsociaciones(false, [{ nombre: 'SGR' }, { nombre: 'PGN' }], 'x')).toBe('SGR, PGN');
        expect(textoAsociaciones(false, [], 'x')).toBe('—');
    });
});

describe('formulario del requisito', () => {
    it('un requisito nuevo del SGR nace con aprobación y sección A; uno simple, sin ella', () => {
        expect(requisitoVacio('sgr')).toMatchObject({ seccion: 'A', fuente: 'a12', condicion: 'siempre', requiere_aprobacion_no_aplica: true });
        expect(requisitoVacio('simple')).toMatchObject({ seccion: '', caracter: 'obligatorio', requiere_aprobacion_no_aplica: false });
    });

    it('en el motor simple solo envía la condición si es condicionado', () => {
        const formulario = { ...formularioRequisito({ texto: ' CDP ', categoria: 'financiero' }, 'simple'), condicion_descripcion: 'Si compra predios' };
        expect(datosRequisito(formulario, 'simple')).toMatchObject({ texto: 'CDP', categoria: 'financiero', condicion_descripcion: null, descripcion: null });
        expect(datosRequisito({ ...formulario, caracter: 'condicionado' }, 'simple').condicion_descripcion).toBe('Si compra predios');
    });

    it('en el motor sgr el sector y el tipo solo van en la sección C', () => {
        const base = formularioRequisito({ texto: 'x', seccion: 'A', sector_id: 3, tipo_id: 9, condicion: 'fase=3' }, 'sgr');
        expect(datosRequisito(base, 'sgr')).toMatchObject({ sector_id: null, tipo_id: null, condicion: 'fase=3' });
        expect(datosRequisito({ ...base, seccion: 'C' }, 'sgr')).toMatchObject({ sector_id: 3, tipo_id: 9 });
        expect(datosRequisito(base, 'sgr')).not.toHaveProperty('caracter');
    });
});

describe('vista previa', () => {
    const r = (id, extra) => ({ id, orden: id, activo: true, texto: `R${id}`, ...extra });

    it('agrupa el SGR por sección en el orden del expediente y omite los inactivos', () => {
        const grupos = agruparVistaPrevia([r(1, { seccion: 'C' }), r(2, { seccion: 'A' }), r(3, { seccion: 'A', activo: false })], 'sgr');
        expect(grupos.map((g) => g.clave)).toEqual(['A', 'C']);
        expect(grupos[0].requisitos.map((x) => x.id)).toEqual([2]);
    });

    it('agrupa una plantilla simple por categoría y deja al final lo que no tiene', () => {
        const grupos = agruparVistaPrevia([r(2, { categoria: null }), r(1, { categoria: 'juridico' }), r(3, { categoria: 'tecnico' })], 'simple');
        expect(grupos.map((g) => g.titulo)).toEqual(['Técnico', 'Jurídico', 'Sin categoría']);
    });
});

describe('utilidades', () => {
    it('mueve dentro de los límites', () => {
        expect(mover([1, 2, 3], 1, -1)).toEqual([2, 1, 3]);
        expect(mover([1, 2, 3], 2, 1)).toEqual([1, 2, 3]);
    });

    it('nombra las versiones y elige la de trabajo', () => {
        const vigente = { id: 1, numero: 1, estado: 'publicada', vigente: true };
        const borrador = { id: 2, numero: 2, estado: 'borrador', vigente: false };
        expect(etiquetaVersion(vigente)).toBe('v1 · vigente');
        expect(etiquetaVersion(borrador)).toBe('v2 · borrador');
        expect(etiquetaVersion(null)).toBe('—');
        expect(versionDeTrabajo({ versiones: [borrador, vigente] })).toBe(borrador);
        expect(versionDeTrabajo({ versiones: [vigente] })).toBe(vigente);
        expect(versionDeTrabajo({ versiones: [] })).toBeNull();
        expect(resumenVersiones({ vigente: { numero: 1 }, borrador: { numero: 2 } })).toBe('v1 vigente · v2 en borrador');
        expect(resumenVersiones({ vigente: null, borrador: { numero: 1 } })).toBe('Sin versión publicada · v1 en borrador');
        expect(resumenVersiones({ vigente: { numero: 3 }, borrador: null })).toBe('v3 vigente');
    });

    it('etiqueta categorías y filtra plantillas activas', () => {
        expect(etiquetaCategoria('juridico')).toBe('Jurídico');
        expect(etiquetaCategoria('nada')).toBe('');
        expect(filtrarPlantillas([{ activo: true }, { activo: false }], { soloActivas: true })).toHaveLength(1);
    });
});
