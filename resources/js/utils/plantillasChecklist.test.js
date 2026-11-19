import { readFileSync } from 'node:fs';
import { beforeAll, describe, it, expect } from 'vitest';
import {
    ALCANCES,
    FUENTES_NORMATIVAS,
    CARACTERES,
    CATEGORIAS,
    PLANTILLA_VACIA,
    TIPOS_REQUISITO,
    agruparVistaPrevia,
    escaparHtml,
    OPCION_SIN_ASIGNAR,
    OPCION_SOLO_CARGO,
    opcionesResponsable,
    htmlErrores,
    mensajeApi,
    porRedactar,
    datosGuardarPlantilla,
    datosPlantilla,
    errorConLista,
    etiquetaFuenteNormativa,
    pestanaPorTecla,
    textoRequisitosVersion,
    datosRequisito,
    datosVistaPrevia,
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
    USOS,
    USO_CONTRATACION,
    USO_VIABILIZACION,
    catalogoConPropios,
    datosNuevaPlantilla,
    esContratacion,
    etiquetaUso,
    rutaListaPlantillas,
    textoModalidades,
    usoDeConsulta,
    usoDePlantilla,
    SIN_ETAPA,
    agruparPorEtapa,
    compararEtapas,
    erroresDeEtapa,
    erroresDelRequisito,
    etiquetaPeso,
    PESO_MAXIMO,
    PESO_MINIMO,
    mismaEtapa,
    nombreEtapa,
    ordenTrasMoverEnEtapa,
    sinEtapa,
} from './plantillasChecklist';
import { RUTA_PLANTILLAS } from './navegacion';

describe('formulario de la plantilla', () => {
    const plantilla = {
        nombre: 'Educación', descripcion: null, alcance: 'especifica', todas_fuentes: false, todos_sectores: false,
        fuentes: [{ id: 4, nombre: 'Recursos propios' }], sectores: [{ id: 2, nombre: 'Educación' }],
    };

    it('lee la plantilla y la devuelve en el formato de la API', () => {
        const formulario = formularioPlantilla(plantilla);
        expect(formulario).toEqual({
            nombre: 'Educación', descripcion: '', alcance: 'especifica', todas_fuentes: false, todos_sectores: false,
            fuente_ids: [4], sector_ids: [2], todas_modalidades: false, modalidad_ids: [],
        });
        // En viabilización las modalidades no viajan: el servidor las rechaza.
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
    });

    it('reconoce la marca sin importar mayúsculas ni tildes, como el servidor', () => {
        const cond = (texto) => porRedactar({ caracter: 'condicionado', condicion_descripcion: texto });
        expect(cond('condicion original: x')).toBe(true);
        expect(cond('CONDICIÓN ORIGINAL: x')).toBe(true);
        expect(cond('(condición original: x)')).toBe(true);
        expect(cond('Condición original x')).toBe(false);
    });

    it('sigue exigiendo condicionado y tolera valores vacíos', () => {
        expect(porRedactar(null)).toBe(false);
    });

    it('traduce los errores de la API', () => {
        const con = (status, data) => ({ response: { status, data } });
        expect(mensajeApi(con(401))).toBe('Tu sesión expiró. Vuelve a iniciar sesión.');
        expect(mensajeApi(con(403))).toBe('No tienes permiso para esta acción.');
        expect(mensajeApi(con(404))).toBe('El registro ya no existe.');
        expect(mensajeApi(con(429))).toBe('Demasiadas solicitudes. Espera un momento e inténtalo de nuevo.');
        expect(mensajeApi(con(500))).toBe('Error del servidor. Inténtalo de nuevo.');
        expect(mensajeApi(con(503))).toBe('Error del servidor. Inténtalo de nuevo.');
        expect(mensajeApi({ isAxiosError: true, request: {}, message: 'Network Error' })).toBe('No hay conexión con el servidor.');
        expect(mensajeApi(con(422, { mensaje: 'No se puede', errores: ['a'] }))).toBe('No se puede a');
        expect(mensajeApi(con(422, { errors: { nombre: ['Falta el nombre.'] } }))).toBe('Falta el nombre.');
        expect(mensajeApi(new Error('Cannot read properties of undefined'))).toBe('Ocurrió un error inesperado.');
        expect(mensajeApi(undefined)).toBe('Ocurrió un error inesperado.');
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

describe('fuentes normativas', () => {
    it('lista las dos normas con su etiqueta completa', () => {
        expect(FUENTES_NORMATIVAS).toEqual([['a12', 'Acuerdo 12/2024'], ['ot', 'Orientaciones Transitorias']]);
        expect(etiquetaFuenteNormativa('ot')).toBe('Orientaciones Transitorias');
        expect(etiquetaFuenteNormativa('xx')).toBe('');
    });

    it('el formulario nuevo del SGR parte de una fuente que existe en la lista', () => {
        expect(FUENTES_NORMATIVAS.map(([v]) => v)).toContain(requisitoVacio('sgr').fuente);
    });
});

describe('datosGuardarPlantilla', () => {
    const fuentes = [{ id: 1, clave: 'propios' }, { id: 2, clave: 'sgr' }];
    const base = { nombre: 'P', descripcion: '', alcance: '', todas_fuentes: false, todos_sectores: false, fuente_ids: [1], sector_ids: [5] };

    it('no cambia nada en una plantilla que no es SGR', () => {
        expect(datosGuardarPlantilla(base, { es_sgr: false }, fuentes)).toEqual(datosPlantilla(base));
    });

    it('en la SGR fuerza todos los sectores y conserva la fuente SGR', () => {
        const datos = datosGuardarPlantilla(base, { es_sgr: true }, fuentes);
        expect(datos.todos_sectores).toBe(true);
        expect(datos.sector_ids).toEqual([]);
        expect(datos.fuente_ids).toEqual([1, 2]);
        expect(base.fuente_ids).toEqual([1]);
    });

    it('en la SGR no duplica la fuente ni la agrega si es para todas las fuentes', () => {
        expect(datosGuardarPlantilla({ ...base, fuente_ids: [2] }, { es_sgr: true }, fuentes).fuente_ids).toEqual([2]);
        const todas = datosGuardarPlantilla({ ...base, todas_fuentes: true, fuente_ids: [] }, { es_sgr: true }, fuentes);
        expect(todas.todas_fuentes).toBe(true);
        expect(todas.fuente_ids).toEqual([]);
    });

    it('en la SGR, sin el catálogo de fuentes cargado, no inventa ids', () => {
        expect(datosGuardarPlantilla(base, { es_sgr: true }, []).fuente_ids).toEqual([1]);
    });
});

describe('pestanaPorTecla', () => {
    const ids = ['a', 'b', 'c'];

    it('las flechas avanzan y retroceden con vuelta', () => {
        expect(pestanaPorTecla(ids, 'a', 'ArrowRight')).toBe('b');
        expect(pestanaPorTecla(ids, 'c', 'ArrowRight')).toBe('a');
        expect(pestanaPorTecla(ids, 'b', 'ArrowLeft')).toBe('a');
        expect(pestanaPorTecla(ids, 'a', 'ArrowLeft')).toBe('c');
    });

    it('Inicio y Fin van a los extremos; otra tecla o pestaña desconocida no navega', () => {
        expect(pestanaPorTecla(ids, 'b', 'Home')).toBe('a');
        expect(pestanaPorTecla(ids, 'b', 'End')).toBe('c');
        expect(pestanaPorTecla(ids, 'b', 'Enter')).toBeNull();
        expect(pestanaPorTecla(ids, 'z', 'Home')).toBeNull();
        expect(pestanaPorTecla([], 'a', 'Home')).toBeNull();
    });
});

describe('errorConLista y textoRequisitosVersion', () => {
    it('reconoce solo el 422 con mensaje y lista', () => {
        const con = (status, data) => ({ response: { status, data } });
        expect(errorConLista(con(422, { mensaje: 'No', errores: ['a', 'b'] }))).toEqual({ mensaje: 'No', errores: ['a', 'b'] });
        expect(errorConLista(con(422, { mensaje: 'No', errores: [] }))).toBeNull();
        expect(errorConLista(con(422, { mensaje: 'No' }))).toBeNull();
        expect(errorConLista(con(422, { errors: { x: ['y'] } }))).toBeNull();
        expect(errorConLista(con(500, { mensaje: 'No', errores: ['a'] }))).toBeNull();
        expect(errorConLista(new Error('x'))).toBeNull();
    });

    it('cuenta los requisitos activos y avisa de los inactivos', () => {
        expect(textoRequisitosVersion({ requisitos: 10, requisitos_activos: 10 })).toBe('10');
        expect(textoRequisitosVersion({ requisitos: 207, requisitos_activos: 206 })).toBe('206 · 1 inactivo');
        expect(textoRequisitosVersion({ requisitos: 10, requisitos_activos: 7 })).toBe('7 · 3 inactivos');
        expect(textoRequisitosVersion({ requisitos: 4 })).toBe('4');
    });
});

describe('opcionesResponsable', () => {
    const responsables = [
        { id: 1, nombre: 'Laura', cargo: 'Interventora', activo: true },
        { id: 2, nombre: 'Carlos', cargo: 'Interventor', activo: true },
        { id: 3, nombre: 'Ángela', cargo: 'Interventor', activo: true },
        { id: 4, nombre: 'Pedro', cargo: 'Jefe de planeación', activo: false },
        { id: 5, nombre: 'Beto', cargo: null, activo: true },
        { id: 6, nombre: 'Daniel', cargo: '  ', activo: true },
    ];

    it('ofrece "Sin asignar" y las personas activas por nombre, con su cargo si lo tienen', () => {
        expect(opcionesResponsable(responsables, { persona: null, cargo: '' })).toEqual([
            { valor: OPCION_SIN_ASIGNAR, etiqueta: 'Sin asignar' },
            { valor: '3', etiqueta: 'Ángela (Interventor)' },
            { valor: '5', etiqueta: 'Beto' },
            { valor: '2', etiqueta: 'Carlos (Interventor)' },
            { valor: '6', etiqueta: 'Daniel' },
            { valor: '1', etiqueta: 'Laura (Interventora)' },
        ]);
    });

    it('una persona activa ya elegida no se repite', () => {
        const opciones = opcionesResponsable(responsables, { persona: { id: 2, nombre: 'Carlos', cargo: 'Interventor' }, cargo: 'Interventor' });
        expect(opciones.filter((o) => o.valor === '2')).toHaveLength(1);
        expect(opciones).toHaveLength(6);
    });

    it('conserva la persona inactiva del requisito y la marca', () => {
        const opciones = opcionesResponsable(responsables, { persona: { id: 4, nombre: 'Pedro', cargo: 'Jefe de planeación' }, cargo: 'Jefe de planeación' });
        expect(opciones.at(-1)).toEqual({ valor: '4', etiqueta: 'Pedro (Jefe de planeación) (inactivo)' });
    });

    it('conserva la persona del requisito aunque la lista no la traiga', () => {
        expect(opcionesResponsable(responsables, { persona: { id: 9, nombre: 'Rosa', cargo: null, activo: false }, cargo: null }).at(-1))
            .toEqual({ valor: '9', etiqueta: 'Rosa (inactivo)' });
        expect(opcionesResponsable(responsables, { persona: { id: 9, nombre: 'Rosa', cargo: 'Tesorera' }, cargo: 'Tesorera' }).at(-1))
            .toEqual({ valor: '9', etiqueta: 'Rosa (Tesorera)' });
    });

    it('un cargo sin persona (dato anterior) se conserva como "Solo cargo"', () => {
        expect(opcionesResponsable(responsables, { persona: null, cargo: ' Interventor ' }).at(-1))
            .toEqual({ valor: OPCION_SOLO_CARGO, etiqueta: 'Solo cargo: Interventor' });
        // Con persona, el cargo es el suyo: no hay "Solo cargo".
        expect(opcionesResponsable(responsables, { persona: { id: 1, nombre: 'Laura', cargo: 'Interventora' }, cargo: 'Interventora' })
            .some((o) => o.valor === OPCION_SOLO_CARGO)).toBe(false);
    });

    it('sin responsables solo ofrece "Sin asignar"', () => {
        expect(opcionesResponsable(null, { persona: null, cargo: '' })).toEqual([{ valor: OPCION_SIN_ASIGNAR, etiqueta: 'Sin asignar' }]);
        expect(opcionesResponsable([], {})).toEqual([{ valor: OPCION_SIN_ASIGNAR, etiqueta: 'Sin asignar' }]);
    });
});

describe('personas del requisito en el formulario', () => {
    const ana = { id: 1, nombre: 'Ana', cargo: 'Interventora', activo: true };

    it('un requisito nuevo nace sin personas', () => {
        expect(requisitoVacio('simple')).toMatchObject({
            responsable_cumplimiento_id: OPCION_SIN_ASIGNAR, responsable_revision_id: OPCION_SIN_ASIGNAR,
            persona_cumplimiento: null, persona_revision: null,
        });
    });

    it('lee la persona elegida, un cargo sin persona como "Solo cargo" y la ausencia de ambos', () => {
        const f = formularioRequisito({
            responsable_cumplimiento_id: 1, responsable_cumplimiento: ana, cargo_cumplimiento: 'Interventora',
            responsable_revision_id: null, responsable_revision: null, cargo_revision: 'Supervisor',
        }, 'simple');
        expect(f).toMatchObject({
            responsable_cumplimiento_id: '1', persona_cumplimiento: ana, cargo_cumplimiento: 'Interventora',
            responsable_revision_id: OPCION_SOLO_CARGO, persona_revision: null, cargo_revision: 'Supervisor',
        });
        expect(formularioRequisito({ cargo_revision: null }, 'simple').responsable_revision_id).toBe(OPCION_SIN_ASIGNAR);
    });

    it('con el catálogo de responsables envía el id y el cargo según la elección', () => {
        const base = { ...requisitoVacio('simple'), texto: 'X', cargo_cumplimiento: 'Interventor', cargo_revision: 'Supervisor' };
        const datos = (cumplimiento, revision) => datosRequisito(
            { ...base, responsable_cumplimiento_id: cumplimiento, responsable_revision_id: revision }, 'simple', true,
        );

        expect(datos('2', OPCION_SOLO_CARGO)).toMatchObject({
            responsable_cumplimiento_id: 2, cargo_cumplimiento: 'Interventor',
            responsable_revision_id: null, cargo_revision: 'Supervisor',
        });
        // "Sin asignar" vacía también el cargo.
        expect(datos(OPCION_SIN_ASIGNAR, OPCION_SIN_ASIGNAR)).toMatchObject({
            responsable_cumplimiento_id: null, cargo_cumplimiento: null,
            responsable_revision_id: null, cargo_revision: null,
        });
    });

    it('sin el catálogo de responsables solo envía el cargo escrito y no toca las personas', () => {
        const f = { ...formularioRequisito({ responsable_cumplimiento_id: 1, responsable_cumplimiento: ana, cargo_cumplimiento: 'Interventora' }, 'sgr'), texto: 'X' };
        const datos = datosRequisito(f, 'sgr');
        expect(datos.cargo_cumplimiento).toBe('Interventora');
        expect(datos).not.toHaveProperty('responsable_cumplimiento_id');
        expect(datos).not.toHaveProperty('responsable_revision_id');
    });
});

describe('datosVistaPrevia', () => {
    const base = { caracter: 'opcional', tipo_requisito: 'documental', soporte_requerido: 'PDF' };

    it('dice quién cumple: la persona con su cargo o, sin ella, el cargo', () => {
        expect(datosVistaPrevia({
            ...base, responsable_cumplimiento: { id: 1, nombre: 'Ana', cargo: 'Interventora' }, cargo_cumplimiento: 'Interventora',
        }, 'simple')).toEqual(['Opcional', 'Documental', 'Soporte: PDF', 'Cumple: Ana (Interventora)']);
        expect(datosVistaPrevia({ ...base, responsable_cumplimiento: null, cargo_cumplimiento: 'Supervisor' }, 'sgr'))
            .toEqual(['Documental', 'Soporte: PDF', 'Cumple: Supervisor']);
        expect(datosVistaPrevia({ caracter: 'obligatorio' }, 'sgr')).toEqual([]);
    });

    it('en un condicionado dice cuándo aplica, salvo que esté por redactar', () => {
        expect(datosVistaPrevia({ caracter: 'condicionado', condicion_descripcion: 'Si compra predios' }, 'simple'))
            .toEqual(['Condicionado', 'Aplica si: Si compra predios']);
        expect(datosVistaPrevia({ caracter: 'condicionado', condicion_descripcion: 'Condición original: comp=predios' }, 'simple'))
            .toEqual(['Condicionado']);
    });
});

describe('uso de la plantilla', () => {
    it('los usos coinciden con PlantillaChecklist::USOS', () => {
        const modelo = readFileSync(new URL('../../../app/Models/PlantillaChecklist.php', import.meta.url), 'utf8');
        const valor = (n) => modelo.match(new RegExp(`const ${n} = '([a-z_]+)'`))[1];
        expect(USOS.map(([v]) => v)).toEqual([valor('USO_VIABILIZACION'), valor('USO_CONTRATACION')]);
        expect(modelo).toMatch(/const USOS = \[self::USO_VIABILIZACION, self::USO_CONTRATACION\]/);
        expect(USOS.map(([, t]) => t)).toEqual(['Viabilización', 'Contratación']);
        expect(etiquetaUso(USO_CONTRATACION)).toBe('Contratación');
    });

    it('una plantilla sin uso es de viabilización', () => {
        expect(esContratacion({ uso: 'contratacion' })).toBe(true);
        expect(esContratacion({ uso: 'viabilizacion' })).toBe(false);
        expect(esContratacion({})).toBe(false);
        expect(esContratacion(null)).toBe(false);
        expect(usoDePlantilla({ uso: 'contratacion' })).toBe(USO_CONTRATACION);
        expect(usoDePlantilla(undefined)).toBe(USO_VIABILIZACION);
    });

    it('lee el uso de la URL y vuelve a la lista en esa pestaña', () => {
        expect(usoDeConsulta('contratacion')).toBe(USO_CONTRATACION);
        expect(usoDeConsulta('viabilizacion')).toBe(USO_VIABILIZACION);
        expect(usoDeConsulta(null)).toBe(USO_VIABILIZACION);
        expect(usoDeConsulta('otro')).toBe(USO_VIABILIZACION);
        expect(rutaListaPlantillas(USO_CONTRATACION)).toBe(`${RUTA_PLANTILLAS}?uso=contratacion`);
        expect(rutaListaPlantillas(USO_VIABILIZACION)).toBe(RUTA_PLANTILLAS);
        expect(usoDeConsulta(new URL(`http://x${rutaListaPlantillas(USO_CONTRATACION)}`).searchParams.get('uso'))).toBe(USO_CONTRATACION);
    });

    it('crea con el uso de la pestaña; la de contratación, sin modalidades', () => {
        expect(datosNuevaPlantilla('  Mínima cuantía ', USO_CONTRATACION)).toEqual({
            nombre: 'Mínima cuantía', uso: 'contratacion', todas_fuentes: true, todos_sectores: true,
            todas_modalidades: false, modalidad_ids: [],
        });
        expect(datosNuevaPlantilla('PGN', USO_VIABILIZACION)).toEqual({
            nombre: 'PGN', uso: 'viabilizacion', todas_fuentes: true, todos_sectores: true,
        });
    });
});

describe('modalidades de la plantilla', () => {
    const contratacion = {
        uso: 'contratacion', nombre: 'Licitación', descripcion: null, alcance: null, todas_fuentes: true, todos_sectores: true,
        fuentes: [], sectores: [], todas_modalidades: false,
        modalidades: [{ id: 3, nombre: 'Licitación pública', activo: true }, { id: 7, nombre: 'Concurso de méritos', activo: false }],
    };

    it('lee las modalidades y las envía solo en contratación', () => {
        const formulario = formularioPlantilla(contratacion);
        expect(formulario).toMatchObject({ todas_modalidades: false, modalidad_ids: [3, 7] });
        expect(datosPlantilla(formulario, USO_CONTRATACION)).toMatchObject({ todas_modalidades: false, modalidad_ids: [3, 7] });
        expect(datosPlantilla(formulario, USO_VIABILIZACION)).not.toHaveProperty('modalidad_ids');
        expect(datosPlantilla(formulario)).not.toHaveProperty('todas_modalidades');
    });

    it('con "Todas las modalidades" envía la lista vacía', () => {
        const formulario = { ...formularioPlantilla(contratacion), todas_modalidades: true };
        expect(datosPlantilla(formulario, USO_CONTRATACION)).toMatchObject({ todas_modalidades: true, modalidad_ids: [] });
    });

    it('al guardar sigue el uso de la plantilla y no lo envía: no cambia', () => {
        const formulario = formularioPlantilla(contratacion);
        const datos = datosGuardarPlantilla(formulario, contratacion, []);
        expect(datos).toMatchObject({ modalidad_ids: [3, 7] });
        expect(datos).not.toHaveProperty('uso');
        expect(datosGuardarPlantilla(formulario, { uso: 'viabilizacion', es_sgr: false }, [])).not.toHaveProperty('modalidad_ids');
        expect(datosGuardarPlantilla(formulario, { uso: 'viabilizacion', es_sgr: true }, [])).not.toHaveProperty('modalidad_ids');
        expect(PLANTILLA_VACIA).toMatchObject({ todas_modalidades: false, modalidad_ids: [] });
    });

    it('resume las modalidades en la lista y marca las inactivas', () => {
        expect(textoModalidades(contratacion)).toBe('Licitación pública, Concurso de méritos (inactiva)');
        expect(textoModalidades({ ...contratacion, todas_modalidades: true })).toBe('Todas las modalidades');
        expect(textoModalidades({ ...contratacion, modalidades: [] })).toBe('Sin modalidades');
        expect(textoModalidades({})).toBe('Sin modalidades');
    });

    it('conserva en el catálogo lo que la plantilla tiene y el catálogo no trae', () => {
        const catalogo = [{ id: 3, nombre: 'Licitación pública', activo: true }, { id: 4, nombre: 'Selección abreviada', activo: true }];
        expect(catalogoConPropios(catalogo, contratacion.modalidades).map((m) => m.id)).toEqual([3, 4, 7]);
        expect(catalogoConPropios(null, contratacion.modalidades).map((m) => m.id)).toEqual([3, 7]);
        expect(catalogoConPropios(catalogo, undefined)).toEqual(catalogo);
        // La inactiva que la plantilla ya tiene sigue entre las opciones; otra inactiva, no.
        const opciones = opcionesCatalogo([...catalogo, { id: 9, nombre: 'Otra', activo: false }, contratacion.modalidades[1]], [3, 7]);
        expect(opciones.map((m) => m.id)).toEqual([3, 4, 7]);
    });
});

describe('etapa del requisito de contratación', () => {
    const etapa = { id: 11, clave: 'seleccion', nombre: 'Selección', orden: 3, activo: false };

    it('un requisito nuevo de contratación nace sin etapa; uno de viabilización no la tiene', () => {
        expect(requisitoVacio('simple', USO_CONTRATACION)).toMatchObject({ etapa_id: '', etapa_actual: null });
        expect(requisitoVacio('simple')).not.toHaveProperty('etapa_id');
        expect(requisitoVacio('sgr', USO_VIABILIZACION)).not.toHaveProperty('etapa_actual');
    });

    it('lee la etapa del requisito y conserva la actual para su opción', () => {
        expect(formularioRequisito({ texto: 'x', etapa_id: 11, etapa }, 'simple', USO_CONTRATACION))
            .toMatchObject({ etapa_id: '11', etapa_actual: etapa });
        expect(formularioRequisito({ texto: 'x', etapa_id: null, etapa: null }, 'simple', USO_CONTRATACION))
            .toMatchObject({ etapa_id: '', etapa_actual: null });
        // Sin el objeto de la API, el id basta para no perder la opción.
        expect(formularioRequisito({ texto: 'x', etapa_id: 4 }, 'simple', USO_CONTRATACION).etapa_actual).toEqual({ id: 4, nombre: 'Etapa 4' });
        expect(formularioRequisito({ texto: 'x', etapa_id: 11, etapa }, 'simple')).not.toHaveProperty('etapa_id');
    });

    it('envía la etapa como número solo en contratación', () => {
        const formulario = formularioRequisito({ texto: 'Estudios previos', etapa_id: 11, etapa }, 'simple', USO_CONTRATACION);
        expect(datosRequisito(formulario, 'simple', false, USO_CONTRATACION).etapa_id).toBe(11);
        expect(datosRequisito({ ...formulario, etapa_id: '' }, 'simple', false, USO_CONTRATACION).etapa_id).toBeNull();
        expect(datosRequisito(formulario, 'simple')).not.toHaveProperty('etapa_id');
        expect(datosRequisito(formulario, 'simple', true, USO_VIABILIZACION)).not.toHaveProperty('etapa_id');
    });
});

describe('peso del requisito de contratación', () => {
    it('los límites coinciden con CatalogoRequisitosController (1 a 100)', () => {
        expect([PESO_MINIMO, PESO_MAXIMO]).toEqual([1, 100]);
    });

    it('un requisito nuevo de contratación pesa 1; uno de viabilización no lleva peso', () => {
        expect(requisitoVacio('simple', USO_CONTRATACION)).toMatchObject({ peso: '1' });
        expect(requisitoVacio('simple')).not.toHaveProperty('peso');
        expect(requisitoVacio('sgr', USO_VIABILIZACION)).not.toHaveProperty('peso');
    });

    it('lee el peso del requisito; sin él, 1', () => {
        expect(formularioRequisito({ texto: 'x', etapa_id: 11, peso: 3 }, 'simple', USO_CONTRATACION)).toMatchObject({ peso: '3' });
        expect(formularioRequisito({ texto: 'x', etapa_id: 11 }, 'simple', USO_CONTRATACION)).toMatchObject({ peso: '1' });
        expect(formularioRequisito({ texto: 'x', etapa_id: 11, peso: null }, 'simple', USO_CONTRATACION)).toMatchObject({ peso: '1' });
        expect(formularioRequisito({ texto: 'x', peso: 3 }, 'simple')).not.toHaveProperty('peso');
    });

    it('envía el peso como entero solo en contratación', () => {
        const formulario = formularioRequisito({ texto: 'Estudios previos', etapa_id: 11, peso: 3 }, 'simple', USO_CONTRATACION);
        expect(datosRequisito(formulario, 'simple', false, USO_CONTRATACION).peso).toBe(3);
        expect(datosRequisito({ ...formulario, peso: ' 7 ' }, 'simple', false, USO_CONTRATACION).peso).toBe(7);
        expect(datosRequisito({ ...formulario, peso: 12 }, 'sgr', true, USO_CONTRATACION).peso).toBe(12);
        // Vacío vuelve al valor por defecto.
        expect(datosRequisito({ ...formulario, peso: '' }, 'simple', false, USO_CONTRATACION).peso).toBe(1);
        expect(datosRequisito(formulario, 'simple')).not.toHaveProperty('peso');
        expect(datosRequisito(formulario, 'sgr', true, USO_VIABILIZACION)).not.toHaveProperty('peso');
    });

    it('no redondea un peso que no es entero: lo envía para que el servidor lo objete', () => {
        const formulario = requisitoVacio('simple', USO_CONTRATACION);
        expect(datosRequisito({ ...formulario, peso: '2.5' }, 'simple', false, USO_CONTRATACION).peso).toBe(2.5);
        expect(datosRequisito({ ...formulario, peso: 'abc' }, 'simple', false, USO_CONTRATACION).peso).toBe('abc');
    });

    it('la lista dice "Peso N" solo si N no es 1', () => {
        expect(etiquetaPeso(3)).toBe('Peso 3');
        expect(etiquetaPeso('100')).toBe('Peso 100');
        expect(etiquetaPeso(1)).toBeNull();
        expect(etiquetaPeso('1')).toBeNull();
        expect(etiquetaPeso(undefined)).toBeNull();
        expect(etiquetaPeso(null)).toBeNull();
    });
});

describe('requisitos por etapa', () => {
    const planeacion = { id: 10, nombre: 'Planeación', orden: 1, activo: true };
    const seleccion = { id: 12, nombre: 'Selección', orden: 3, activo: false };
    const r = (id, orden, etapa, extra) => ({ id, orden, activo: true, texto: `R${id}`, etapa_id: etapa?.id ?? null, etapa: etapa ?? null, ...extra });

    it('agrupa en el orden del catálogo, numera las etapas y deja "Sin etapa" al final', () => {
        const grupos = agruparPorEtapa([r(1, 1, seleccion), r(2, 2, null), r(3, 3, planeacion), r(4, 4, seleccion)]);
        expect(grupos.map((g) => g.titulo)).toEqual(['1. Planeación', '3. Selección', 'Sin etapa']);
        expect(grupos.map((g) => g.clave)).toEqual(['etapa-10', 'etapa-12', SIN_ETAPA]);
        expect(grupos.map((g) => g.inactiva)).toEqual([false, true, false]);
        expect(grupos[1].requisitos.map((x) => x.id)).toEqual([1, 4]);
        expect(grupos[2].requisitos.map((x) => x.id)).toEqual([2]);
    });

    it('a igual orden decide el id; sin el objeto de la etapa usa el catálogo', () => {
        const otra = { id: 9, nombre: 'Otra', orden: 3, activo: true };
        const sinObjeto = { ...r(5, 1, otra), etapa: undefined };
        const grupos = agruparPorEtapa([r(1, 2, seleccion), sinObjeto], [otra, seleccion]);
        expect(grupos.map((g) => g.titulo)).toEqual(['3. Otra', '3. Selección']);
        expect(agruparPorEtapa([{ ...sinObjeto }], null)[0].titulo).toBe('Etapa 9');
        expect(agruparPorEtapa([], null)).toEqual([]);
        expect(nombreEtapa({ nombre: 'Planeación', orden: 1 })).toBe('1. Planeación');
        expect(nombreEtapa({ nombre: 'Planeación' })).toBe('Planeación');
        expect(nombreEtapa(null)).toBe('');
    });

    it('sin "Sin etapa" si todos la tienen', () => {
        expect(agruparPorEtapa([r(1, 1, planeacion)]).map((g) => g.clave)).toEqual(['etapa-10']);
        expect(sinEtapa({ etapa_id: null })).toBe(true);
        expect(sinEtapa({})).toBe(true);
        expect(sinEtapa({ etapa_id: 3 })).toBe(false);
    });

    it('la vista previa de contratación agrupa por etapa los activos, por orden', () => {
        const grupos = agruparVistaPrevia([
            r(1, 5, planeacion), r(2, 1, planeacion), r(3, 2, null, { activo: false }), r(4, 3, seleccion), r(5, 4, null),
        ], 'simple', USO_CONTRATACION);
        expect(grupos.map((g) => g.clave)).toEqual(['etapa-10', 'etapa-12', SIN_ETAPA]);
        expect(grupos[0].requisitos.map((x) => x.id)).toEqual([2, 1]);
        expect(grupos[2].requisitos.map((x) => x.id)).toEqual([5]);
        // Una plantilla simple de viabilización sigue por categoría.
        expect(agruparVistaPrevia([r(1, 1, planeacion, { categoria: 'juridico' })], 'simple').map((g) => g.titulo)).toEqual(['Jurídico']);
    });
});

describe('ordenTrasMoverEnEtapa', () => {
    const a = { id: 'a', orden: 1 };
    const b = { id: 'b', orden: 2 };
    // Orden de la versión: a1, b1, a2, b2, sin1, a3. Se ve: a1, a2, a3 | b1, b2 | sin1.
    const lista = [
        { id: 1, etapa_id: 'a', etapa: a }, { id: 2, etapa_id: 'b', etapa: b }, { id: 3, etapa_id: 'a', etapa: a },
        { id: 4, etapa_id: 'b', etapa: b }, { id: 5, etapa_id: null }, { id: 6, etapa_id: 'a', etapa: a },
    ];
    const ids = (l) => l.map((x) => x.id);

    it('envía el orden que se ve, por etapas, con el requisito cambiado por su vecino de etapa', () => {
        expect(ids(ordenTrasMoverEnEtapa(lista, 3, -1))).toEqual([3, 1, 6, 2, 4, 5]);
        expect(ids(ordenTrasMoverEnEtapa(lista, 3, 1))).toEqual([1, 6, 3, 2, 4, 5]);
        expect(ids(ordenTrasMoverEnEtapa(lista, 2, 1))).toEqual([1, 3, 6, 4, 2, 5]);
    });

    it('el orden enviado coincide con la lista agrupada tras recargar', () => {
        const nueva = ordenTrasMoverEnEtapa(lista, 4, -1).map((r, i) => ({ ...r, orden: i + 1 }));
        expect(agruparPorEtapa(nueva).flatMap((g) => g.requisitos)).toEqual(nueva);
    });

    it('sin vecino en su etapa hacia ese lado, o con un movimiento inválido, no hay orden nuevo', () => {
        expect(ordenTrasMoverEnEtapa(lista, 1, -1)).toBeNull();
        expect(ordenTrasMoverEnEtapa(lista, 6, 1)).toBeNull();
        expect(ordenTrasMoverEnEtapa(lista, 2, -1)).toBeNull();
        expect(ordenTrasMoverEnEtapa(lista, 5, -1)).toBeNull();
        expect(ordenTrasMoverEnEtapa(lista, 99, 1)).toBeNull();
        expect(ordenTrasMoverEnEtapa(lista, 3, 0)).toBeNull();
        expect(ordenTrasMoverEnEtapa(lista, 3, 2)).toBeNull();
    });

    it('sin etapa también es un grupo, y las etapas sin objeto se ordenan con el catálogo', () => {
        expect(mismaEtapa({ etapa_id: null }, {})).toBe(true);
        expect(mismaEtapa({ etapa_id: 1 }, { etapa_id: 2 })).toBe(false);
        const sinObjeto = [{ id: 1 }, { id: 2, etapa_id: 20 }, { id: 3, etapa_id: null }, { id: 4, etapa_id: 10 }];
        expect(ids(ordenTrasMoverEnEtapa(sinObjeto, 3, -1, [{ id: 10, orden: 1 }, { id: 20, orden: 2 }]))).toEqual([4, 2, 3, 1]);
    });
});

describe('compararEtapas', () => {
    it('por orden y, a igual orden, por id; sin orden, al final', () => {
        const etapas = [{ id: 7, orden: 2 }, { id: 3 }, { id: 9, orden: 1 }, { id: 5, orden: 2 }];
        expect([...etapas].sort(compararEtapas).map((e) => e.id)).toEqual([9, 5, 7, 3]);
    });
});

describe('errores de la etapa al guardar un requisito', () => {
    const respuesta = (status, data) => ({ response: { status, data } });

    it('de una regla de negocio toma los de errors.etapa_id y deja el resto de la lista para el aviso', () => {
        const error = respuesta(422, {
            mensaje: 'El requisito no es válido.',
            errores: ['La etapa "Selección" está inactiva.', 'Ya existe un requisito con la clave "x" en esta versión.'],
            errors: { etapa_id: ['La etapa "Selección" está inactiva.'] },
        });
        expect(erroresDeEtapa(error)).toEqual({
            etapa: ['La etapa "Selección" está inactiva.'],
            mensaje: 'El requisito no es válido.',
            otros: ['Ya existe un requisito con la clave "x" en esta versión.'],
        });
    });

    it('no adivina por el texto: sin errors.etapa_id, todo queda para el aviso', () => {
        const error = respuesta(422, { mensaje: 'El requisito no es válido.', errores: ['La etapa de algo que no es el campo.'] });
        expect(erroresDeEtapa(error)).toEqual({ etapa: [], mensaje: 'El requisito no es válido.', otros: ['La etapa de algo que no es el campo.'] });
    });

    it('solo con el error de la etapa no queda nada para el aviso', () => {
        const mensaje = 'Elige la etapa de contratación del requisito.';
        expect(erroresDeEtapa(respuesta(422, { mensaje: 'El requisito no es válido.', errores: [mensaje], errors: { etapa_id: [mensaje] } })))
            .toEqual({ etapa: [mensaje], mensaje: 'El requisito no es válido.', otros: [] });
    });

    it('separa los de la etapa de la validación de Laravel', () => {
        const error = respuesta(422, {
            message: 'Revisa la etapa: debe ser un número entero.',
            errors: { etapa_id: ['Revisa la etapa: debe ser un número entero.'], texto: ['Escribe el nombre del requisito.'] },
        });
        expect(erroresDeEtapa(error)).toEqual({
            etapa: ['Revisa la etapa: debe ser un número entero.'],
            mensaje: 'El requisito no es válido.',
            otros: ['Escribe el nombre del requisito.'],
        });
    });

    it('sin errores de la etapa, todo queda para el aviso; otro estado no se reparte', () => {
        expect(erroresDeEtapa(respuesta(422, { mensaje: 'El requisito no pertenece a esta versión.' })))
            .toEqual({ etapa: [], mensaje: 'El requisito no pertenece a esta versión.', otros: [] });
        expect(erroresDeEtapa(respuesta(500, {}))).toBeNull();
        expect(erroresDeEtapa(new Error('x'))).toBeNull();
    });
});

describe('errores de la etapa y del peso al guardar un requisito', () => {
    const respuesta = (status, data) => ({ response: { status, data } });
    const sinPeso = 'Los requisitos de una plantilla de viabilización no llevan peso.';

    it('de una regla de negocio reparte etapa y peso, cada uno en su campo, y deja el resto para el aviso', () => {
        const error = respuesta(422, {
            mensaje: 'El requisito no es válido.',
            errores: ['Elige la etapa de contratación del requisito.', sinPeso, 'Un requisito condicionado necesita describir cuándo aplica.'],
            errors: { etapa_id: ['Elige la etapa de contratación del requisito.'], peso: [sinPeso] },
        });
        expect(erroresDelRequisito(error)).toEqual({
            etapa: ['Elige la etapa de contratación del requisito.'],
            peso: [sinPeso],
            mensaje: 'El requisito no es válido.',
            otros: ['Un requisito condicionado necesita describir cuándo aplica.'],
        });
    });

    it('separa el peso de la validación de Laravel', () => {
        const error = respuesta(422, {
            message: 'Revisa el peso: el máximo es 100.',
            errors: { peso: ['Revisa el peso: el máximo es 100.'], texto: ['Escribe el nombre del requisito.'] },
        });
        expect(erroresDelRequisito(error)).toEqual({
            etapa: [],
            peso: ['Revisa el peso: el máximo es 100.'],
            mensaje: 'El requisito no es válido.',
            otros: ['Escribe el nombre del requisito.'],
        });
    });

    it('sin errores de campo todo queda para el aviso; otro estado no se reparte', () => {
        expect(erroresDelRequisito(respuesta(422, { mensaje: 'El requisito no pertenece a esta versión.' })))
            .toEqual({ etapa: [], peso: [], mensaje: 'El requisito no pertenece a esta versión.', otros: [] });
        expect(erroresDelRequisito(respuesta(500, {}))).toBeNull();
        expect(erroresDelRequisito(new Error('x'))).toBeNull();
    });

    it('erroresDeEtapa no cambia: los del peso quedan para el aviso', () => {
        const error = respuesta(422, {
            message: 'Revisa el peso: el mínimo es 1.',
            errors: { peso: ['Revisa el peso: el mínimo es 1.'] },
        });
        expect(erroresDeEtapa(error)).toEqual({ etapa: [], mensaje: 'El requisito no es válido.', otros: ['Revisa el peso: el mínimo es 1.'] });
    });
});

describe('errores al publicar una plantilla de contratación', () => {
    it('los requisitos sin etapa llegan como lista; la falta de modalidades, como mensaje', () => {
        const sinEtapa = {
            response: {
                status: 422,
                data: {
                    mensaje: 'La versión tiene requisitos sin etapa de contratación y no puede publicarse.',
                    errores: ['Estudios previos: elige su etapa de contratación.'],
                },
            },
        };
        expect(errorConLista(sinEtapa)).toEqual({
            mensaje: 'La versión tiene requisitos sin etapa de contratación y no puede publicarse.',
            errores: ['Estudios previos: elige su etapa de contratación.'],
        });
        const sinModalidades = {
            response: { status: 422, data: { mensaje: 'Asocia la plantilla de contratación a sus modalidades, o marca "Todas las modalidades", antes de publicar.' } },
        };
        expect(errorConLista(sinModalidades)).toBeNull();
        expect(mensajeApi(sinModalidades)).toBe('Asocia la plantilla de contratación a sus modalidades, o marca "Todas las modalidades", antes de publicar.');
    });
});
