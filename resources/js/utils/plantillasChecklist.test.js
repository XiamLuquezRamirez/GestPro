import { readFileSync } from 'node:fs';
import { beforeAll, describe, it, expect } from 'vitest';
import {
    ALCANCES,
    CARACTERES,
    CATEGORIAS,
    PLANTILLA_VACIA,
    TIPOS_REQUISITO,
    agruparVistaPrevia,
    escaparHtml,
    htmlErrores,
    mensajeApi,
    porRedactar,
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

describe('refuerzo: payload completo del requisito', () => {
    it('motor sgr: envía el objeto entero', () => {
        const formulario = {
            ...requisitoVacio('sgr'),
            texto: ' Texto ', descripcion: ' Desc ', categoria: 'tecnico', tipo_requisito: 'documental',
            cargo_cumplimiento: 'Gestor', cargo_revision: 'Revisor', soporte_requerido: 'PDF',
            referencia_normativa: 'Art. 1', nota: 'N', codigo_comun: ' CC ', clave: ' k1 ', seccion: 'C',
            fuente: 'ot', sector_id: '3', tipo_id: '9', subtitulo: ' Sub ', codigo: ' c1 ', condicion: '  fase=3  ',
        };
        expect(datosRequisito(formulario, 'sgr')).toEqual({
            texto: 'Texto', descripcion: 'Desc', categoria: 'tecnico', tipo_requisito: 'documental',
            cargo_cumplimiento: 'Gestor', cargo_revision: 'Revisor', soporte_requerido: 'PDF',
            referencia_normativa: 'Art. 1', nota: 'N', requiere_aprobacion_no_aplica: true, activo: true,
            codigo_comun: 'CC', clave: 'k1', seccion: 'C', fuente: 'ot', sector_id: 3, tipo_id: 9,
            subtitulo: 'Sub', codigo: 'c1', condicion: 'fase=3',
        });
    });

    it('motor simple: envía el objeto entero y respeta el carácter', () => {
        const formulario = {
            ...requisitoVacio('simple'),
            texto: ' Texto ', descripcion: 'Desc', categoria: 'juridico', tipo_requisito: 'validacion',
            cargo_cumplimiento: 'A', cargo_revision: 'B', soporte_requerido: 'C', referencia_normativa: 'D',
            nota: 'E', codigo_comun: 'CC', clave: ' k2 ', codigo: ' c2 ', caracter: 'opcional',
            condicion_descripcion: 'ignorada', activo: false,
        };
        expect(datosRequisito(formulario, 'simple')).toEqual({
            texto: 'Texto', descripcion: 'Desc', categoria: 'juridico', tipo_requisito: 'validacion',
            cargo_cumplimiento: 'A', cargo_revision: 'B', soporte_requerido: 'C', referencia_normativa: 'D',
            nota: 'E', requiere_aprobacion_no_aplica: false, activo: false, codigo_comun: 'CC', clave: 'k2',
            caracter: 'opcional', condicion_descripcion: null, codigo: 'c2',
        });
    });
});

describe('refuerzo: constantes contra PHP', () => {
    // Rutas relativas a este archivo, no al directorio desde donde se lance Vitest.
    const leer = (ruta) => readFileSync(new URL(ruta, import.meta.url), 'utf8');
    const lista = (codigo, nombre) => {
        const m = codigo.match(new RegExp(`const ${nombre} = \\[([^\\]]*)\\]`));
        return [...m[1].matchAll(/'([a-z_]+)'/g)].map((x) => x[1]);
    };
    let modelo;
    let servicio;
    beforeAll(() => {
        modelo = leer('../../../app/Models/RequisitoCatalogo.php');
        servicio = leer('../../../app/Services/Viabilizacion/PlantillaService.php');
    });

    it('categorías, tipos y alcances coinciden con PHP', () => {
        expect(CATEGORIAS.map(([v]) => v)).toEqual(lista(modelo, 'CATEGORIAS'));
        expect(TIPOS_REQUISITO.map(([v]) => v)).toEqual(lista(modelo, 'TIPOS'));
        expect(ALCANCES.map(([v]) => v)).toEqual(lista(servicio, 'ALCANCES'));
    });

    it('los caracteres coinciden con PHP', () => {
        const valor = (n) => modelo.match(new RegExp(`const ${n} = '([a-z_]+)'`))[1];
        expect(CARACTERES.map(([v]) => v)).toEqual([valor('OBLIGATORIO'), valor('OPCIONAL'), valor('CONDICIONADO')]);
    });

    it('las etiquetas están fijadas', () => {
        expect(CATEGORIAS.map(([, t]) => t)).toEqual(['Técnico', 'Jurídico', 'Financiero', 'Ambiental', 'Social', 'Administrativo', 'Otro']);
        expect(TIPOS_REQUISITO.map(([, t]) => t)).toEqual(['Documental', 'Validación', 'Condición']);
        expect(CARACTERES.map(([, t]) => t)).toEqual(['Obligatorio', 'Opcional', 'Condicionado']);
        expect(ALCANCES.map(([, t]) => t)).toEqual(['General', 'Específica']);
    });
});

describe('refuerzo: formularioRequisito', () => {
    it('conserva aprobación y activo de un requisito existente', () => {
        expect(formularioRequisito({ requiere_aprobacion_no_aplica: false }, 'sgr').requiere_aprobacion_no_aplica).toBe(false);
        expect(formularioRequisito({ requiere_aprobacion_no_aplica: true }, 'simple').requiere_aprobacion_no_aplica).toBe(true);
        expect(formularioRequisito({ activo: false }, 'simple').activo).toBe(false);
        expect(formularioRequisito({}, 'simple').activo).toBe(true);
        expect(formularioRequisito({}, 'simple').caracter).toBe('obligatorio');
        expect(formularioRequisito({ caracter: 'opcional' }, 'simple').caracter).toBe('opcional');
    });

    it('un campo ausente no pisa los valores por defecto', () => {
        const f = formularioRequisito({ texto: 'x', fuente: undefined, requiere_aprobacion_no_aplica: undefined }, 'sgr');
        expect(f).toMatchObject({ texto: 'x', seccion: 'A', fuente: 'a12', condicion: 'siempre', requiere_aprobacion_no_aplica: true });
    });

    it('un null del servidor en un texto es un dato real: pasa a vacío', () => {
        const f = formularioRequisito({ texto: 'x', seccion: null, condicion: null, sector_id: null }, 'sgr');
        expect(f).toMatchObject({ seccion: '', condicion: '', sector_id: '' });
    });

    it('un requisito de la sección C con condicion null no se reescribe como "siempre"', () => {
        const r = { texto: 'Requisito sectorial', seccion: 'C', fuente: 'a12', condicion: null, sector_id: 3, tipo_id: 9, requiere_aprobacion_no_aplica: true, activo: true };
        const datos = datosRequisito(formularioRequisito(r, 'sgr'), 'sgr');
        expect(datos.condicion).toBeNull();
        expect(datos).toMatchObject({ seccion: 'C', sector_id: 3, tipo_id: 9 });
    });
});

describe('refuerzo: vista previa', () => {
    const r = (id, orden, seccion, extra) => ({ id, orden, seccion, activo: true, texto: `R${id}`, ...extra });

    it('sgr: orden A, B, D, C, por orden dentro del grupo y con títulos', () => {
        const grupos = agruparVistaPrevia([
            r(1, 9, 'C'), r(2, 8, 'D'), r(3, 7, 'B'), r(4, 6, 'A'), r(5, 2, 'A'), r(6, 1, 'C'),
        ], 'sgr');
        expect(grupos.map((g) => g.clave)).toEqual(['A', 'B', 'D', 'C']);
        expect(grupos.map((g) => g.titulo)).toEqual([
            'S.1 Requisitos generales', 'S.2 Requisitos adicionales', 'ESP Recuperación tras desastre', 'S.3 Requisitos sectoriales',
        ]);
        expect(grupos[0].requisitos.map((x) => x.id)).toEqual([5, 4]);
        expect(grupos[3].requisitos.map((x) => x.id)).toEqual([6, 1]);
    });

    it('simple: una categoría vacía o desconocida cae en "Sin categoría"', () => {
        const grupos = agruparVistaPrevia([
            r(1, 1, undefined, { categoria: '' }), r(2, 2, undefined, { categoria: 'inventada' }), r(3, 3, undefined, { categoria: 'otro' }),
        ], 'simple');
        expect(grupos.map((g) => g.titulo)).toEqual(['Otro', 'Sin categoría']);
        expect(grupos[1].requisitos.map((x) => x.id)).toEqual([1, 2]);
    });
});

describe('refuerzo: resto', () => {
    it('plantilla vacía, versiones, filtro y mover', () => {
        expect(PLANTILLA_VACIA.todos_sectores).toBe(true);
        expect(PLANTILLA_VACIA.todas_fuentes).toBe(false);
        expect(etiquetaVersion({ numero: 2, estado: 'publicada', vigente: false })).toBe('v2');
        expect(filtrarPlantillas([{ activo: true }, { activo: false }], { soloActivas: false })).toHaveLength(2);
        expect(mover([1, 2, 3], 0, -1)).toEqual([1, 2, 3]);
        expect(mover([1, 2, 3], -1, 1)).toEqual([1, 2, 3]);
        expect(mover([1, 2, 3], 3, -1)).toEqual([1, 2, 3]);
        const original = [1, 2, 3];
        expect(mover(original, 5, 0)).not.toBe(original);
    });
});

describe('porRedactar, mensajeApi y htmlErrores', () => {
    it('detecta los condicionados con la condición original', () => {
        expect(porRedactar({ caracter: 'condicionado', condicion_descripcion: 'Condición original: fase=3' })).toBe(true);
        expect(porRedactar({ caracter: 'condicionado', condicion_descripcion: 'Si hay predios' })).toBe(false);
        expect(porRedactar({ caracter: 'obligatorio', condicion_descripcion: 'Condición original: x' })).toBe(false);
        expect(porRedactar({ caracter: 'condicionado', condicion_descripcion: null })).toBe(false);
        expect(porRedactar(null)).toBe(false);
    });

    it('traduce los errores de la API', () => {
        const con = (status, data) => ({ response: { status, data } });
        expect(mensajeApi(con(401))).toBe('Tu sesión expiró. Vuelve a iniciar sesión.');
        expect(mensajeApi(con(403))).toBe('No tienes permiso para esta acción.');
        expect(mensajeApi(con(404))).toBe('El registro ya no existe. Recarga la página.');
        expect(mensajeApi(con(500))).toBe('Error del servidor. Inténtalo de nuevo.');
        expect(mensajeApi(con(503))).toBe('Error del servidor. Inténtalo de nuevo.');
        expect(mensajeApi({ isAxiosError: true, request: {}, message: 'Network Error' })).toBe('No hay conexión con el servidor.');
        expect(mensajeApi(con(422, { mensaje: 'No se puede', errores: ['a'] }))).toBe('No se puede a');
        expect(mensajeApi(con(422, { errors: { nombre: ['Falta el nombre.'] } }))).toBe('Falta el nombre.');
        expect(mensajeApi(new Error('fallo'))).toBe('fallo');
    });

    it('escapa y limita la lista de errores', () => {
        expect(escaparHtml('<b>"a" & \'b\'</b>')).toBe('&lt;b&gt;&quot;a&quot; &amp; &#39;b&#39;&lt;/b&gt;');
        const html = htmlErrores('Falla <x>', ['<img>', 'dos']);
        expect(html).toContain('<p>Falla &lt;x&gt;</p>');
        expect(html).toContain('<li>&lt;img&gt;</li>');
        expect(html).not.toContain('<img>');
        expect(htmlErrores('Solo mensaje')).not.toContain('<ul');
        const muchos = htmlErrores('m', Array.from({ length: 25 }, (_, i) => `e${i}`));
        expect(muchos.match(/<li>/g)).toHaveLength(21);
        expect(muchos).toContain('<li>y 5 más</li>');
    });
});
