import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import {
    CAMPOS_PROCESO,
    CATALOGO_VACIO,
    ESTADOS_CIERRE,
    ESTADOS_PROCESO,
    PLATAFORMAS,
    PROCESO_VACIO,
    REGLAS_ETAPA,
    REGLAS_MODALIDAD,
    REGLAS_TIPO_CONTRATO,
    datosCatalogo,
    erroresPorCampo,
    formularioCatalogo,
    idsTrasMover,
    ordenarCatalogo,
    puedeAdministrarCatalogos,
    textoEstadoCatalogo,
    textoFecha,
    validarCatalogo,
    avisoCambioEstado,
    datosEstado,
    datosProceso,
    esEdicionDesactualizada,
    esEnlaceWeb,
    etiquetaEstadoProceso,
    etiquetaPlataforma,
    formularioProceso,
    indiceFocoAtrapado,
    numeroProceso,
    opcionesConActual,
    opcionesEtapa,
    opcionesFuenteProceso,
    puedeEditarProcesos,
    textoContratos,
    textoValor,
    tonoEstado,
    validarProceso,
    valorDesdeApi,
    valorEnEdicion,
    valorParaApi,
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

    it('los tipos de contrato usan los mensajes y el largo del servidor', () => {
        const tipos = [{ id: 1, nombre: 'Interventoría' }];
        expect(validarCatalogo({ nombre: ' ' }, tipos, undefined, REGLAS_TIPO_CONTRATO)).toEqual({ nombre: 'Escribe el nombre del tipo de contrato.' });
        expect(validarCatalogo({ nombre: 'INTERVENTORIA' }, tipos, undefined, REGLAS_TIPO_CONTRATO)).toEqual({ nombre: 'Ya existe un tipo de contrato con ese nombre.' });
        expect(validarCatalogo({ nombre: 'Interventoría' }, tipos, 1, REGLAS_TIPO_CONTRATO)).toEqual({});
        expect(validarCatalogo({ nombre: 'x'.repeat(121) }, [], undefined, REGLAS_TIPO_CONTRATO)).toEqual({ nombre: 'El nombre admite hasta 120 caracteres.' });
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

// ---------- Procesos de contratación ----------

/** El texto con espacios normales: Intl separa el signo de pesos con un espacio duro. */
const plano = (texto) => texto.replace(/\s/g, ' ');

describe('constantes contra PHP', () => {
    // Ruta relativa a este archivo, no al directorio desde donde se lance Vitest.
    const modelo = readFileSync(new URL('../../../app/Models/ProcesoLicitacion.php', import.meta.url), 'utf8');
    const valor = (nombre) => modelo.match(new RegExp(`const ${nombre} = '([a-z_]+)'`))[1];
    const lista = (nombre) => modelo.match(new RegExp(`const ${nombre} = \\[([^\\]]*)\\]`))[1]
        .split(',').map((x) => x.trim()).filter(Boolean).map((x) => valor(x.replace('self::', '')));

    it('las plataformas y los estados coinciden con ProcesoLicitacion', () => {
        expect(PLATAFORMAS.map(([v]) => v)).toEqual(lista('PLATAFORMAS'));
        expect(ESTADOS_PROCESO.map(([v]) => v)).toEqual(lista('ESTADOS'));
        expect(ESTADOS_CIERRE.every((e) => lista('ESTADOS').includes(e))).toBe(true);
    });
});

describe('puedeEditarProcesos', () => {
    it('Administrador o Gestor, como editar-datos', () => {
        expect(puedeEditarProcesos({ rol: 'Administrador' })).toBe(true);
        expect(puedeEditarProcesos({ rol: 'Gestor' })).toBe(true);
        expect(puedeEditarProcesos({ rol: 'Consulta' })).toBe(false);
        expect(puedeEditarProcesos(undefined)).toBe(false);
    });
});

describe('etiquetas de plataforma y estado', () => {
    it('dan el nombre legible', () => {
        expect(etiquetaPlataforma('secop_ii')).toBe('SECOP II');
        expect(etiquetaPlataforma('secop_i')).toBe('SECOP I');
        expect(etiquetaPlataforma('tvec')).toBe('Tienda Virtual');
        expect(etiquetaPlataforma('ninguna')).toBe('Sin plataforma');
        expect(etiquetaEstadoProceso('preparacion')).toBe('En preparación');
        expect(etiquetaEstadoProceso('evaluacion')).toBe('En evaluación');
    });

    it('un valor desconocido o vacío no rompe la pantalla', () => {
        expect(etiquetaPlataforma(null)).toBe('—');
        expect(etiquetaEstadoProceso('otro')).toBe('otro');
        expect(etiquetaEstadoProceso(null)).toBe('—');
    });

    it('cada estado tiene un tono para su insignia', () => {
        expect(ESTADOS_PROCESO.map(([v]) => tonoEstado(v))).toEqual(['neutro', 'azul', 'ambar', 'verde', 'coral', 'verde-pleno', 'coral', 'coral']);
        expect(tonoEstado('otro')).toBe('neutro');
    });
});

describe('avisoCambioEstado', () => {
    it('el avance por el flujo no avisa', () => {
        [
            ['preparacion', 'publicado'], ['publicado', 'evaluacion'], ['evaluacion', 'adjudicado'],
            ['evaluacion', 'desierto'], ['adjudicado', 'celebrado'], ['adjudicado', 'desierto'],
            ['preparacion', 'celebrado'],
        ].forEach(([de, a]) => expect(avisoCambioEstado(de, a)).toBeNull());
    });

    it('cancelar desde un estado abierto no avisa', () => {
        ['preparacion', 'publicado', 'evaluacion', 'adjudicado'].forEach((de) => expect(avisoCambioEstado(de, 'cancelado')).toBeNull());
    });

    it('volver atrás en el flujo avisa', () => {
        expect(avisoCambioEstado('evaluacion', 'publicado')).toEqual({
            titulo: 'El proceso vuelve atrás',
            texto: '"Publicado" es un paso anterior a "En evaluación" en el flujo del proceso. ¿Seguro que quieres cambiarlo? El cambio queda en el historial.',
        });
        expect(avisoCambioEstado('adjudicado', 'preparacion')?.titulo).toBe('El proceso vuelve atrás');
        expect(avisoCambioEstado('publicado', 'preparacion')?.titulo).toBe('El proceso vuelve atrás');
    });

    it('salir de un estado de cierre avisa, también hacia otro cierre o hacia adelante', () => {
        expect(avisoCambioEstado('celebrado', 'evaluacion')).toEqual({
            titulo: 'El proceso ya está cerrado',
            texto: '"Celebrado" es un estado de cierre. ¿Seguro que quieres pasar el proceso a "En evaluación"? El cambio queda en el historial.',
        });
        expect(avisoCambioEstado('desierto', 'celebrado')?.titulo).toBe('El proceso ya está cerrado');
        expect(avisoCambioEstado('cancelado', 'preparacion')?.titulo).toBe('El proceso ya está cerrado');
        expect(avisoCambioEstado('desierto', 'cancelado')?.titulo).toBe('El proceso ya está cerrado');
    });

    it('sin cambio o sin datos no hay aviso', () => {
        expect(avisoCambioEstado('celebrado', 'celebrado')).toBeNull();
        expect(avisoCambioEstado(null, 'publicado')).toBeNull();
        expect(avisoCambioEstado('publicado', '')).toBeNull();
    });
});

describe('valor en pesos', () => {
    it('agrupa los miles mientras se escribe y deja hasta 2 decimales', () => {
        expect(valorEnEdicion('1234567')).toBe('1.234.567');
        expect(valorEnEdicion('1234567,891')).toBe('1.234.567,89');
        expect(valorEnEdicion('$ 1.234.5678')).toBe('12.345.678');
        expect(valorEnEdicion('0012')).toBe('12');
        expect(valorEnEdicion(',5')).toBe('0,5');
        expect(valorEnEdicion('12,')).toBe('12,');
        expect(valorEnEdicion('1,2,3')).toBe('1,23');
        expect(valorEnEdicion('abc')).toBe('');
        expect(valorEnEdicion('')).toBe('');
    });

    it('sin coma, un punto con 1 o 2 cifras al final es el decimal; con 3, de miles', () => {
        expect(valorEnEdicion('1234567.89')).toBe('1.234.567,89');
        expect(valorEnEdicion('150.5')).toBe('150,5');
        expect(valorEnEdicion('$ 1,234.5')).toBe('1,23');
        expect(valorEnEdicion('1.500')).toBe('1.500');
        expect(valorEnEdicion('1.234.567.89')).toBe('1.234.567,89');
        expect(valorEnEdicion('150.')).toBe('150');
    });

    it('al borrar de un valor agrupado el punto sigue siendo de miles', () => {
        expect(valorEnEdicion('1.23', '1.234')).toBe('123');
        expect(valorEnEdicion('12.34', '12.345')).toBe('1.234');
        expect(valorEnEdicion('1.234.56', '1.234.567')).toBe('123.456');
        expect(valorEnEdicion('1.34', '1.234')).toBe('134');
        // Pegar sobre lo que había no es borrar.
        expect(valorEnEdicion('150.5', '1.234.567')).toBe('150,5');
        expect(valorEnEdicion('150.5', '')).toBe('150,5');
    });

    it('lo envía a la API sin separadores de miles y con punto decimal', () => {
        expect(valorParaApi('1.234.567,5')).toBe('1234567.5');
        expect(valorParaApi('1.234.567')).toBe('1234567');
        expect(valorParaApi('0,5')).toBe('0.5');
        expect(valorParaApi('12,')).toBe('12');
        expect(valorParaApi('0')).toBe('0');
        expect(valorParaApi('')).toBeNull();
        expect(valorParaApi(',')).toBeNull();
        expect(valorParaApi(null)).toBeNull();
    });

    it('a la API, con la misma regla del punto decimal', () => {
        expect(valorParaApi('1234567.89')).toBe('1234567.89');
        expect(valorParaApi('150.5')).toBe('150.5');
        expect(valorParaApi('1.500')).toBe('1500');
    });

    it('lo trae de la API al formulario sin centavos en cero', () => {
        expect(valorDesdeApi('1234567.50')).toBe('1.234.567,50');
        expect(valorDesdeApi('1000.00')).toBe('1.000');
        expect(valorDesdeApi('0.00')).toBe('0');
        expect(valorDesdeApi(null)).toBe('');
        expect(valorParaApi(valorDesdeApi('9999999999999.99'))).toBe('9999999999999.99');
    });

    it('lo muestra en pesos colombianos', () => {
        expect(plano(textoValor('1234567.00'))).toBe('$ 1.234.567');
        expect(plano(textoValor('1234567.50'))).toBe('$ 1.234.567,50');
        expect(plano(textoValor(0))).toBe('$ 0');
        expect(textoValor(null)).toBe('—');
        expect(textoValor('')).toBe('—');
        expect(textoValor('abc')).toBe('—');
    });
});

describe('textoFecha', () => {
    it('año-mes-día como día/mes/año, sin desfase de zona horaria', () => {
        expect(textoFecha('2026-03-20')).toBe('20/03/2026');
        expect(textoFecha('2026-01-01')).toBe('01/01/2026');
    });

    it('sin fecha o con otro formato, un guion', () => {
        expect(textoFecha(null)).toBe('—');
        expect(textoFecha('')).toBe('—');
        expect(textoFecha('mañana')).toBe('—');
    });
});

describe('textoContratos', () => {
    it('cuenta los contratos vinculados', () => {
        expect(textoContratos(0)).toBe('Sin contratos');
        expect(textoContratos(undefined)).toBe('Sin contratos');
        expect(textoContratos(1)).toBe('1 contrato');
        expect(textoContratos(3)).toBe('3 contratos');
    });
});

describe('enlace y número del proceso', () => {
    it('solo un enlace http o https completo es enlace', () => {
        expect(esEnlaceWeb('https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.1')).toBe(true);
        expect(esEnlaceWeb(' http://ejemplo.gov.co ')).toBe(true);
        expect(esEnlaceWeb('javascript:alert(1)')).toBe(false);
        expect(esEnlaceWeb('ftp://ejemplo.gov.co')).toBe(false);
        expect(esEnlaceWeb('www.secop.gov.co')).toBe(false);
        expect(esEnlaceWeb('https://')).toBe(false);
        expect(esEnlaceWeb(null)).toBe(false);
    });

    it('el número enlaza al proceso si tiene enlace', () => {
        expect(numeroProceso({ codigo_proceso: 'LP-001-2026', url_proceso: 'https://secop.gov.co/x' })).toEqual({ texto: 'LP-001-2026', enlace: 'https://secop.gov.co/x' });
        expect(numeroProceso({ codigo_proceso: null, url_proceso: 'https://secop.gov.co/x' })).toEqual({ texto: 'Ver proceso', enlace: 'https://secop.gov.co/x' });
        expect(numeroProceso({ codigo_proceso: 'LP-001-2026', url_proceso: null })).toEqual({ texto: 'LP-001-2026', enlace: null });
        expect(numeroProceso({ codigo_proceso: '', url_proceso: 'javascript:alert(1)' })).toEqual({ texto: '—', enlace: null });
    });
});

describe('opcionesConActual', () => {
    const entidades = [
        { id: 1, nombre: 'Alcaldía', activo: 1 },
        { id: 2, nombre: 'Gobernación', activo: 0 },
        { id: 3, nombre: 'Ministerio', activo: true },
    ];

    it('ofrece las activas, aunque `activo` venga como 1/0', () => {
        expect(opcionesConActual(entidades, null)).toEqual([
            { valor: '1', etiqueta: 'Alcaldía' },
            { valor: '3', etiqueta: 'Ministerio' },
        ]);
    });

    it('conserva la actual inactiva, marcada', () => {
        expect(opcionesConActual(entidades, { id: 2, nombre: 'Gobernación' }).at(-1)).toEqual({ valor: '2', etiqueta: 'Gobernación (inactiva)' });
    });

    it('conserva la actual que el catálogo ya no trae, marcada', () => {
        expect(opcionesConActual(entidades, { id: 7, nombre: 'Ministerio de Minas' }).at(-1)).toEqual({ valor: '7', etiqueta: 'Ministerio de Minas (inactiva)' });
    });

    it('sin el catálogo cargado conserva la actual sin marcarla: no se sabe si está inactiva', () => {
        expect(opcionesConActual(null, { id: 7, nombre: 'Gobernación' })).toEqual([{ valor: '7', etiqueta: 'Gobernación' }]);
        expect(opcionesConActual(undefined, { id: 7, nombre: 'Gobernación' })).toEqual([{ valor: '7', etiqueta: 'Gobernación' }]);
        expect(opcionesConActual(null, null)).toEqual([]);
    });

    it('la actual activa no se repite', () => {
        expect(opcionesConActual(entidades, { id: 1, nombre: 'Alcaldía' })).toHaveLength(2);
    });
});

describe('formularioProceso', () => {
    const proceso = {
        id: 5,
        objeto: 'Construcción de la sede',
        modalidad: { id: 1, nombre: 'Licitación pública', activo: true },
        plataforma: 'secop_ii',
        codigo_proceso: 'LP-001',
        url_proceso: null,
        entidad_contratante: { id: 3, nombre: 'Alcaldía' },
        monto: '1500000.00',
        fuente: { id: 7, nombre: 'Regalías' },
        responsable: { id: 2, nombre: 'Ana Pérez' },
        fecha_apertura: '2026-03-01',
        fecha_cierre: '2026-03-20',
        observaciones: 'Pliegos publicados',
        estado: 'publicado',
    };

    it('nuevo: vacío', () => {
        expect(formularioProceso(null)).toEqual(PROCESO_VACIO);
        expect(CAMPOS_PROCESO).toEqual(['objeto', 'modalidad_id', 'plataforma', 'codigo_proceso', 'url_proceso', 'entidad_contratante_id', 'monto', 'fuente_id', 'responsable_id', 'fecha_apertura', 'fecha_cierre', 'observaciones']);
    });

    it('editar: con los ids como texto para los selects y el valor formateado', () => {
        expect(formularioProceso(proceso)).toEqual({
            objeto: 'Construcción de la sede',
            modalidad_id: '1',
            plataforma: 'secop_ii',
            codigo_proceso: 'LP-001',
            url_proceso: '',
            entidad_contratante_id: '3',
            monto: '1.500.000',
            fuente_id: '7',
            responsable_id: '2',
            fecha_apertura: '2026-03-01',
            fecha_cierre: '2026-03-20',
            observaciones: 'Pliegos publicados',
        });
    });

    it('un proceso anterior sin plataforma, entidad ni valor la elige al editar', () => {
        expect(formularioProceso({ ...proceso, plataforma: null, entidad_contratante: null, monto: null })).toMatchObject({
            plataforma: '', entidad_contratante_id: '', monto: '',
        });
    });

    it('un proceso sin fuente, responsable, fechas ni observaciones los deja vacíos', () => {
        expect(formularioProceso({
            ...proceso, fuente: null, responsable: null, fecha_apertura: null, fecha_cierre: null, observaciones: null,
        })).toMatchObject({
            fuente_id: '', responsable_id: '', fecha_apertura: '', fecha_cierre: '', observaciones: '',
        });
    });
});

describe('validarProceso', () => {
    const valido = {
        ...PROCESO_VACIO, objeto: 'Interventoría', modalidad_id: '4', plataforma: 'secop_ii',
    };

    it('acepta lo mínimo: objeto, modalidad y plataforma', () => {
        expect(validarProceso(valido)).toEqual({});
    });

    it('exige objeto, modalidad y plataforma con los mensajes del servidor', () => {
        expect(validarProceso({ ...PROCESO_VACIO, objeto: '  ' })).toEqual({
            objeto: 'Escribe el objeto del proceso.',
            modalidad_id: 'Elige la modalidad del proceso.',
            plataforma: 'Elige la plataforma del proceso.',
        });
        expect(validarProceso({ ...valido, plataforma: 'otra' })).toEqual({ plataforma: 'Elige una plataforma de la lista.' });
    });

    it('revisa el enlace y los largos', () => {
        expect(validarProceso({ ...valido, url_proceso: 'secop.gov.co/x' })).toEqual({ url_proceso: 'Escribe un enlace completo que empiece por http:// o https://.' });
        expect(validarProceso({ ...valido, url_proceso: `https://secop.gov.co/${'x'.repeat(480)}` })).toEqual({ url_proceso: 'El enlace admite hasta 500 caracteres.' });
        expect(validarProceso({ ...valido, codigo_proceso: 'x'.repeat(101) })).toEqual({ codigo_proceso: 'El número del proceso admite hasta 100 caracteres.' });
        expect(validarProceso({ ...valido, objeto: 'x'.repeat(5001) })).toEqual({ objeto: 'El objeto admite hasta 5000 caracteres.' });
    });

    it('el valor admite hasta 13 cifras enteras', () => {
        expect(validarProceso({ ...valido, monto: '9.999.999.999.999,99' })).toEqual({});
        expect(validarProceso({ ...valido, monto: '10.000.000.000.000' })).toEqual({ monto: 'El valor admite hasta 13 cifras enteras y 2 decimales.' });
    });

    it('las fechas son opcionales y el cierre no es anterior a la apertura', () => {
        expect(validarProceso({ ...valido, fecha_apertura: '2026-03-01', fecha_cierre: '2026-03-01' })).toEqual({});
        expect(validarProceso({ ...valido, fecha_cierre: '2026-03-01' })).toEqual({});
        expect(validarProceso({ ...valido, fecha_apertura: '2026-03-10', fecha_cierre: '2026-03-01' })).toEqual({
            fecha_cierre: 'La fecha de cierre no puede ser anterior a la de apertura.',
        });
    });

    it('rechaza una fecha que no sea año-mes-día y observaciones demasiado largas', () => {
        expect(validarProceso({ ...valido, fecha_apertura: '01/03/2026' })).toEqual({ fecha_apertura: 'Escribe la fecha de apertura como año-mes-día.' });
        expect(validarProceso({ ...valido, fecha_cierre: '2026-02-30' })).toEqual({ fecha_cierre: 'Escribe la fecha de cierre como año-mes-día.' });
        expect(validarProceso({ ...valido, observaciones: 'x'.repeat(5000) })).toEqual({});
        expect(validarProceso({ ...valido, observaciones: 'x'.repeat(5001) })).toEqual({ observaciones: 'Las observaciones admiten hasta 5000 caracteres.' });
    });
});

describe('opcionesFuenteProceso', () => {
    const fuentes = [
        { id: 1, nombre: 'Propios', activo: true },
        { id: 2, nombre: 'Regalías', activo: true },
        { id: 3, nombre: 'Antigua', activo: false },
        { id: 4, nombre: 'Cofinanciación', activo: true },
    ];
    const nombres = (lista) => lista.map((o) => o.etiqueta);

    it('sin fuentes en el proyecto, una sola lista con las activas', () => {
        expect(opcionesFuenteProceso(fuentes, null, [])).toEqual({
            delProyecto: [],
            otras: [
                { valor: '1', etiqueta: 'Propios' },
                { valor: '2', etiqueta: 'Regalías' },
                { valor: '4', etiqueta: 'Cofinanciación' },
            ],
        });
    });

    it('las del proyecto primero, aparte de las demás', () => {
        const opciones = opcionesFuenteProceso(fuentes, null, [4, '2']);
        expect(nombres(opciones.delProyecto)).toEqual(['Regalías', 'Cofinanciación']);
        expect(nombres(opciones.otras)).toEqual(['Propios']);
    });

    it('una fuente inactiva del proyecto no se ofrece si el proceso no la tiene', () => {
        const opciones = opcionesFuenteProceso(fuentes, null, [3, 1]);
        expect(nombres(opciones.delProyecto)).toEqual(['Propios']);
        expect(nombres(opciones.otras)).toEqual(['Regalías', 'Cofinanciación']);
    });

    it('la actual inactiva se conserva marcada, en el grupo que le toca', () => {
        expect(nombres(opcionesFuenteProceso(fuentes, { id: 3, nombre: 'Antigua' }, []).otras)).toEqual(['Propios', 'Regalías', 'Cofinanciación', 'Antigua (inactiva)']);
        expect(nombres(opcionesFuenteProceso(fuentes, { id: 3, nombre: 'Antigua' }, [3]).delProyecto)).toEqual(['Antigua (inactiva)']);
    });

    it('con el catálogo sin cargar ofrece la actual, y sin ella, nada', () => {
        expect(opcionesFuenteProceso(null, { id: 3, nombre: 'Antigua' }, [1])).toEqual({ delProyecto: [], otras: [{ valor: '3', etiqueta: 'Antigua' }] });
        expect(opcionesFuenteProceso(null, null, [1])).toEqual({ delProyecto: [], otras: [] });
    });
});

describe('datosProceso', () => {
    it('envía todos los campos; los opcionales vacíos como null', () => {
        expect(datosProceso({
            objeto: '  Obra ', modalidad_id: '2', plataforma: 'ninguna', codigo_proceso: ' ', url_proceso: '', entidad_contratante_id: '', monto: '',
        })).toEqual({
            objeto: 'Obra',
            modalidad_id: 2,
            plataforma: 'ninguna',
            codigo_proceso: null,
            url_proceso: null,
            entidad_contratante_id: null,
            monto: null,
            fuente_id: null,
            responsable_id: null,
            fecha_apertura: null,
            fecha_cierre: null,
            observaciones: null,
        });
    });

    it('los ids como números y el valor como texto decimal', () => {
        expect(datosProceso({
            objeto: 'Obra', modalidad_id: '2', plataforma: 'secop_i', codigo_proceso: 'SA-3', url_proceso: ' https://secop.gov.co/3 ', entidad_contratante_id: '9', monto: '1.234,5',
            fuente_id: '7', responsable_id: '2', fecha_apertura: '2026-03-01', fecha_cierre: ' 2026-03-20 ', observaciones: ' Con anexos ',
        })).toEqual({
            objeto: 'Obra',
            modalidad_id: 2,
            plataforma: 'secop_i',
            codigo_proceso: 'SA-3',
            url_proceso: 'https://secop.gov.co/3',
            entidad_contratante_id: 9,
            monto: '1234.5',
            fuente_id: 7,
            responsable_id: 2,
            fecha_apertura: '2026-03-01',
            fecha_cierre: '2026-03-20',
            observaciones: 'Con anexos',
        });
    });

    it('al editar envía el `actualizado` del proceso que se abrió; al crear, no', () => {
        const formulario = { ...PROCESO_VACIO, objeto: 'Obra', modalidad_id: '2', plataforma: 'ninguna' };
        expect(datosProceso(formulario, { id: 4, actualizado: '2026-11-17T10:00:00-05:00' })).toMatchObject({ actualizado: '2026-11-17T10:00:00-05:00' });
        expect(datosProceso(formulario, { id: 4, actualizado: null })).toMatchObject({ actualizado: null });
        expect(datosProceso(formulario)).not.toHaveProperty('actualizado');
    });
});

describe('datosEstado', () => {
    it('envía el estado, la observación sin espacios o null, y el `actualizado` del proceso', () => {
        expect(datosEstado({ id: 4, actualizado: '2026-11-17T10:00:00-05:00' }, 'publicado', '  En SECOP II ')).toEqual({
            estado: 'publicado', observacion: 'En SECOP II', actualizado: '2026-11-17T10:00:00-05:00',
        });
        expect(datosEstado({ id: 4, actualizado: null }, 'cancelado', '   ')).toEqual({ estado: 'cancelado', observacion: null, actualizado: null });
    });
});

describe('esEdicionDesactualizada', () => {
    it('solo el 409 del servidor', () => {
        expect(esEdicionDesactualizada({ response: { status: 409, data: { mensaje: 'Otra persona cambió este proceso…' } } })).toBe(true);
        expect(esEdicionDesactualizada({ response: { status: 422, data: {} } })).toBe(false);
        expect(esEdicionDesactualizada(new Error('sin red'))).toBe(false);
        expect(esEdicionDesactualizada(null)).toBe(false);
    });
});

describe('indiceFocoAtrapado', () => {
    it('Tab en el último vuelve al primero; Mayús+Tab en el primero va al último', () => {
        expect(indiceFocoAtrapado(4, 5, false)).toBe(0);
        expect(indiceFocoAtrapado(0, 5, true)).toBe(4);
    });

    it('en medio el navegador sigue solo', () => {
        expect(indiceFocoAtrapado(2, 5, false)).toBeNull();
        expect(indiceFocoAtrapado(2, 5, true)).toBeNull();
    });

    it('desde el propio diálogo entra por el primero o el último', () => {
        expect(indiceFocoAtrapado(-1, 5, false)).toBe(0);
        expect(indiceFocoAtrapado(-1, 5, true)).toBe(4);
    });

    it('sin controles, el foco se queda en el diálogo', () => {
        expect(indiceFocoAtrapado(-1, 0, false)).toBe(-1);
    });
});

describe('opcionesEtapa', () => {
    const etapas = [
        { id: 12, nombre: 'Selección', orden: 3, activo: true },
        { id: 10, nombre: 'Planeación', orden: 1, activo: true },
        { id: 11, nombre: 'Precontractual', orden: 2, activo: false },
    ];

    it('ofrece las activas en el orden del catálogo, numeradas', () => {
        expect(opcionesEtapa(etapas, null)).toEqual([
            { valor: '10', etiqueta: '1. Planeación' },
            { valor: '12', etiqueta: '3. Selección' },
        ]);
    });

    it('conserva la etapa inactiva que el requisito ya tiene, marcada', () => {
        expect(opcionesEtapa(etapas, { id: 11, nombre: 'Precontractual', orden: 2, activo: false }).at(-1))
            .toEqual({ valor: '11', etiqueta: '2. Precontractual (inactiva)' });
        expect(opcionesEtapa(etapas, { id: 10, nombre: 'Planeación', orden: 1 })).toHaveLength(2);
    });

    it('a igual orden decide el id, como el servidor y la lista agrupada, no el nombre', () => {
        const empatadas = [
            { id: 21, nombre: 'Adendas', orden: 6, activo: true },
            { id: 20, nombre: 'Cierre', orden: 6, activo: true },
        ];
        expect(opcionesEtapa(empatadas, null).map((o) => o.valor)).toEqual(['20', '21']);
    });

    it('sin el catálogo cargado solo ofrece la actual, sin marca', () => {
        expect(opcionesEtapa(null, { id: 11, nombre: 'Precontractual', orden: 2 })).toEqual([{ valor: '11', etiqueta: '2. Precontractual' }]);
        expect(opcionesEtapa(undefined, null)).toEqual([]);
    });
});
