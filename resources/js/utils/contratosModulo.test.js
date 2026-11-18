import { describe, it, expect } from 'vitest';
import {
    SIN_PROYECTO, consultaDeFiltros, esIdContrato, filaContrato, filtrosDeConsulta, hayFiltros, opcionesFiltroEstado, opcionesFiltroProyecto,
    opcionesProyecto, parametrosBusqueda, puedeEditarContratos, rutaContrato, rutaListaDesde, textoAvance, textoListaVacia,
    tonoEstadoContrato, CLAVE_NIT_EMPRESA, TEXTO_SECOP_VACIO, VISTA_LISTA, VISTA_SECOP, consultaVistaSecop, esNitValido,
    errorNit, estadoImportarSecop, estadoSinSecop, guardarNitEmpresa, leerNitEmpresa, nitDeConsulta, nitParaMostrar, normalizarNit,
    resultadoSecopDeEstado, rutaVistaSecop, vistaDeConsulta,
} from './contratosModulo';
import { ESTADOS_CONTRATO } from './contratos';
import { RUTA_CONTRATOS } from './navegacion';

const consulta = (texto) => new URLSearchParams(texto);
// Intl separa el símbolo con un espacio duro: se compara con espacios normales.
const normal = (texto) => texto.replace(/\s/g, ' ');

describe('filtrosDeConsulta', () => {
    it('lee la búsqueda, el estado y el proyecto de la URL', () => {
        expect(filtrosDeConsulta(consulta('q=puente&estado=en_ejecucion&proyecto=7')))
            .toEqual({ q: 'puente', estado: 'en_ejecucion', proyecto: '7' });
        expect(filtrosDeConsulta(consulta('proyecto=sin'))).toEqual({ q: '', estado: '', proyecto: SIN_PROYECTO });
    });

    it('sin parámetros, sin filtros', () => {
        expect(filtrosDeConsulta(consulta(''))).toEqual({ q: '', estado: '', proyecto: '' });
        expect(filtrosDeConsulta(undefined)).toEqual({ q: '', estado: '', proyecto: '' });
    });

    it('descarta lo que el servidor rechazaría', () => {
        expect(filtrosDeConsulta(consulta('estado=borrador&proyecto=abc'))).toEqual({ q: '', estado: '', proyecto: '' });
        expect(filtrosDeConsulta(consulta('proyecto=0')).proyecto).toBe('');
        expect(filtrosDeConsulta(consulta('proyecto=-3')).proyecto).toBe('');
    });

    it('recorta la búsqueda a 100 caracteres sin espacios sobrantes', () => {
        expect(filtrosDeConsulta(consulta('q=%20%20obra%20%20')).q).toBe('obra');
        expect(filtrosDeConsulta(consulta(`q=${'a'.repeat(150)}`)).q).toHaveLength(100);
    });
});

describe('consultaDeFiltros', () => {
    it('escribe solo los filtros con valor', () => {
        expect(consultaDeFiltros({ q: 'vía', estado: '', proyecto: 'sin' }).toString()).toBe('q=v%C3%ADa&proyecto=sin');
        expect(consultaDeFiltros({ q: '', estado: '', proyecto: '' }).toString()).toBe('');
    });

    it('ida y vuelta con filtrosDeConsulta', () => {
        const filtros = { q: 'acueducto', estado: 'suspendido', proyecto: '12' };
        expect(filtrosDeConsulta(consultaDeFiltros(filtros))).toEqual(filtros);
    });

    it('recorta la búsqueda antes de escribirla', () => {
        expect(consultaDeFiltros({ q: '  obra ', estado: '', proyecto: '' }).get('q')).toBe('obra');
        expect(consultaDeFiltros({ q: '   ', estado: '', proyecto: '' }).has('q')).toBe(false);
    });
});

describe('parametrosBusqueda', () => {
    it('lo que se envía a GET /contratos/buscar: sin los filtros vacíos', () => {
        expect(parametrosBusqueda({ q: 'obra', estado: '', proyecto: 'sin' })).toEqual({ q: 'obra', proyecto: 'sin' });
        expect(parametrosBusqueda({ q: '', estado: '', proyecto: '' })).toEqual({});
    });
});

describe('hayFiltros y textoListaVacia', () => {
    it('distingue una lista vacía de una búsqueda sin resultados', () => {
        expect(hayFiltros({ q: '', estado: '', proyecto: '' })).toBe(false);
        expect(hayFiltros({ q: '', estado: 'anulado', proyecto: '' })).toBe(true);
        expect(textoListaVacia({ q: '', estado: '', proyecto: '' })).toBe('Aún no hay contratos registrados.');
        expect(textoListaVacia({ q: 'x', estado: '', proyecto: '' })).toBe('No hay contratos que coincidan con los filtros.');
    });
});

describe('opcionesFiltroEstado', () => {
    it('"Todos los estados" y luego cada estado de contrato', () => {
        const opciones = opcionesFiltroEstado();
        expect(opciones[0]).toEqual({ valor: '', etiqueta: 'Todos los estados' });
        expect(opciones.slice(1).map((o) => o.valor)).toEqual(ESTADOS_CONTRATO.map(([v]) => v));
        expect(opciones.find((o) => o.valor === 'en_ejecucion').etiqueta).toBe('En ejecución');
    });
});

const proyectos = [
    { id: 3, nombre: 'Vía terciaria' },
    { id: 1, nombre: 'Acueducto veredal' },
    { id: 2, nombre: 'Escuela rural' },
];

describe('opcionesFiltroProyecto', () => {
    it('"Todos", "Sin proyecto" y los proyectos por nombre', () => {
        expect(opcionesFiltroProyecto(proyectos, '')).toEqual([
            { valor: '', etiqueta: 'Todos los proyectos' },
            { valor: 'sin', etiqueta: 'Sin proyecto' },
            { valor: '1', etiqueta: 'Acueducto veredal' },
            { valor: '2', etiqueta: 'Escuela rural' },
            { valor: '3', etiqueta: 'Vía terciaria' },
        ]);
    });

    it('conserva el proyecto de la URL aunque la lista no lo traiga (o no haya cargado)', () => {
        expect(opcionesFiltroProyecto(null, '9').map((o) => o.valor)).toEqual(['', 'sin', '9']);
        expect(opcionesFiltroProyecto(null, '9')[2].etiqueta).toBe('Proyecto 9');
        expect(opcionesFiltroProyecto(proyectos, '2').filter((o) => o.valor === '2')).toHaveLength(1);
    });
});

describe('opcionesProyecto (selector del editor)', () => {
    it('un contrato nuevo puede quedar sin proyecto', () => {
        expect(opcionesProyecto(proyectos, null).map((o) => o.valor)).toEqual(['', '1', '2', '3']);
        expect(opcionesProyecto(proyectos, null)[0]).toEqual({ valor: '', etiqueta: 'Sin proyecto' });
    });

    it('uno sin proyecto puede recibir uno', () => {
        expect(opcionesProyecto(proyectos, { id: 5, proyecto: null }).map((o) => o.valor)).toEqual(['', '1', '2', '3']);
    });

    it('uno con proyecto puede cambiarlo, pero no quitarlo', () => {
        expect(opcionesProyecto(proyectos, { id: 5, proyecto: 2 }).map((o) => o.valor)).toEqual(['1', '2', '3']);
    });

    it('ofrece el proyecto del contrato aunque la lista no lo traiga', () => {
        const contrato = { id: 5, proyecto: 8, proyecto_nombre: 'Puente colgante' };
        expect(opcionesProyecto(null, contrato)).toEqual([{ valor: '8', etiqueta: 'Puente colgante' }]);
        expect(opcionesProyecto([], { id: 5, proyecto: 8 })).toEqual([{ valor: '8', etiqueta: 'Proyecto 8' }]);
    });
});

describe('filaContrato', () => {
    const contrato = {
        id: 4,
        n_contrato: 'CO-001-2026',
        objeto: 'Construcción de placa huella',
        contratista: 'Consorcio Vías',
        estado: 'en_ejecucion',
        valor: '1500000.50',
        valor_inicial: '1000000.00',
        avance_fisico: 45,
        avance_financiero: 0,
        proyecto: { id: 2, nombre: 'Escuela rural' },
        proceso: { id: 9, codigo_proceso: 'LP-003-2026' },
    };

    it('lo que muestra la lista de un contrato', () => {
        const fila = filaContrato(contrato);
        expect({ ...fila, valor: normal(fila.valor) }).toEqual({
            id: 4,
            numero: 'CO-001-2026',
            objeto: 'Construcción de placa huella',
            contratista: 'Consorcio Vías',
            proyecto: 'Escuela rural',
            sinProyecto: false,
            proceso: 'LP-003-2026',
            estado: 'En ejecución',
            tono: 'verde',
            valor: '$ 1.500.000,50',
            avanceFisico: '45%',
            avanceFinanciero: '0%',
        });
    });

    it('sin proyecto, sin proceso, sin avances ni datos opcionales', () => {
        const fila = filaContrato({ id: 5, n_contrato: '', objeto: 'Suministro', contratista: null, estado: 'celebrado', valor: null, avance_fisico: null, proyecto: null, proceso: null });
        expect(fila).toMatchObject({
            numero: 'Sin número', contratista: '—', proyecto: 'Sin proyecto', sinProyecto: true, proceso: '—',
            valor: '—', avanceFisico: '—', avanceFinanciero: '—',
        });
    });

    it('un proceso sin código se nombra', () => {
        expect(filaContrato({ ...contrato, proceso: { id: 9, codigo_proceso: '  ' } }).proceso).toBe('Sin número');
    });

    // El valor de la fila es el vigente (con adiciones), no el inicial.
    it('muestra el valor vigente', () => {
        expect(normal(filaContrato({ ...contrato, valor: '2000000.00' }).valor)).toBe('$ 2.000.000,00');
    });
});

describe('textoAvance', () => {
    it('un porcentaje o una raya', () => {
        expect(textoAvance(30)).toBe('30%');
        expect(textoAvance(0)).toBe('0%');
        expect(textoAvance(null)).toBe('—');
        expect(textoAvance(undefined)).toBe('—');
    });
});

describe('tonoEstadoContrato', () => {
    it('cada estado tiene tono; uno desconocido es neutro', () => {
        ESTADOS_CONTRATO.forEach(([v]) => expect(tonoEstadoContrato(v)).toMatch(/^(neutro|azul|ambar|verde|verde-pleno|coral)$/));
        expect(tonoEstadoContrato('suspendido')).toBe('ambar');
        expect(tonoEstadoContrato('anulado')).toBe('coral');
        expect(tonoEstadoContrato('otro')).toBe('neutro');
    });
});

describe('rutas', () => {
    it('la de un contrato y la de volver a la lista con sus filtros', () => {
        expect(RUTA_CONTRATOS).toBe('/contratos');
        expect(rutaContrato(4)).toBe('/contratos/4');
        expect(rutaListaDesde({ lista: '?estado=anulado' })).toBe('/contratos?estado=anulado');
        expect(rutaListaDesde({ lista: 'https://otro' })).toBe('/contratos');
        expect(rutaListaDesde(null)).toBe('/contratos');
    });
});

describe('esIdContrato', () => {
    it('solo un entero positivo es el id de un contrato', () => {
        expect(esIdContrato('12')).toBe(true);
        expect(esIdContrato(3)).toBe(true);
        ['0', '-1', '1.5', 'abc', '', undefined, null, '12x', 'nuevo'].forEach((v) => expect(esIdContrato(v)).toBe(false));
    });

    it('rechaza un id de más de 18 cifras o mayor que el entero seguro, sin llamar a la API', () => {
        expect(esIdContrato(String(Number.MAX_SAFE_INTEGER))).toBe(true);
        expect(esIdContrato(String(Number.MAX_SAFE_INTEGER + 1))).toBe(false);
        expect(esIdContrato('99999999999999999999')).toBe(false);
        expect(esIdContrato('1234567890123456789')).toBe(false);
    });
});

describe('puedeEditarContratos', () => {
    it('Administrador o Gestor, como editar-datos', () => {
        expect(puedeEditarContratos({ rol: 'Administrador' })).toBe(true);
        expect(puedeEditarContratos({ rol: 'Gestor' })).toBe(true);
        expect(puedeEditarContratos({ rol: 'Consulta' })).toBe(false);
        expect(puedeEditarContratos(null)).toBe(false);
    });
});

// ---------- Contratos de la empresa en SECOP II ----------

describe('vistaDeConsulta', () => {
    it('?vista=secop abre los contratos de la empresa en SECOP II para quien puede editar', () => {
        expect(vistaDeConsulta(consulta('vista=secop'), true)).toBe(VISTA_SECOP);
        expect(vistaDeConsulta(consulta('vista=secop&nit=900123456'), true)).toBe(VISTA_SECOP);
    });

    it('para un lector, o sin vista o con otra, es la lista', () => {
        expect(vistaDeConsulta(consulta('vista=secop'), false)).toBe(VISTA_LISTA);
        expect(vistaDeConsulta(consulta(''), true)).toBe(VISTA_LISTA);
        expect(vistaDeConsulta(consulta('vista=otra'), true)).toBe(VISTA_LISTA);
        expect(vistaDeConsulta(undefined, true)).toBe(VISTA_LISTA);
    });

    it('la consulta de cada vista', () => {
        expect(consultaVistaSecop('').toString()).toBe('vista=secop');
        expect(consultaVistaSecop('900.123.456-7').toString()).toBe('vista=secop&nit=900123456-7');
        expect(rutaVistaSecop()).toBe('/contratos?vista=secop');
    });
});

describe('NIT de la empresa', () => {
    it('acepta lo que acepta el servidor: dígitos con puntos o espacios y el dígito de verificación', () => {
        ['900123456', '900.123.456', '900 123 456', '900.123.456-7', '9'].forEach((v) => expect(esNitValido(v)).toBe(true));
        expect(esNitValido(' 900123456 ')).toBe(true);
    });

    it('rechaza lo demás', () => {
        ['', '  ', 'abc', '-900', '900-12', '900123456-', '.900', '900,123', '1'.repeat(21), null, undefined].forEach((v) => expect(esNitValido(v)).toBe(false));
    });

    it('normalizarNit quita puntos y espacios y conserva el dígito de verificación', () => {
        expect(normalizarNit(' 900.123 456-7 ')).toBe('900123456-7');
        expect(normalizarNit('900123456')).toBe('900123456');
        expect(normalizarNit('abc')).toBe('');
    });

    it('nitParaMostrar agrupa los miles con puntos', () => {
        expect(nitParaMostrar('900123456-7')).toBe('900.123.456-7');
        expect(nitParaMostrar('900 123 456')).toBe('900.123.456');
        expect(nitParaMostrar('12345')).toBe('12.345');
        expect(nitParaMostrar('123')).toBe('123');
        expect(nitParaMostrar('')).toBe('');
        expect(nitParaMostrar('abc')).toBe('');
    });

    it('errorNit: el mensaje del campo antes de consultar, como los del servidor', () => {
        expect(errorNit('  ')).toBe('Escribe el NIT de la empresa.');
        expect(errorNit('abc')).toBe('Escribe el NIT solo con números (puede llevar puntos y el dígito de verificación con guion).');
        expect(errorNit('900.123.456-7')).toBeNull();
    });

    it('nitDeConsulta toma el NIT de la URL solo si es válido, normalizado', () => {
        expect(nitDeConsulta(consulta('vista=secop&nit=900.123.456-7'))).toBe('900123456-7');
        expect(nitDeConsulta(consulta('vista=secop&nit=abc'))).toBe('');
        expect(nitDeConsulta(consulta('vista=secop'))).toBe('');
        expect(nitDeConsulta(undefined)).toBe('');
    });
});

describe('NIT recordado', () => {
    const almacen = (inicial = {}) => {
        const datos = { ...inicial };
        return {
            datos,
            getItem: (clave) => (clave in datos ? datos[clave] : null),
            setItem: (clave, valor) => { datos[clave] = String(valor); },
        };
    };
    const roto = { getItem: () => { throw new Error('bloqueado'); }, setItem: () => { throw new Error('bloqueado'); } };

    it('se guarda normalizado con una clave propia y se lee igual', () => {
        const a = almacen();
        guardarNitEmpresa('900.123.456-7', a);
        expect(a.datos).toEqual({ [CLAVE_NIT_EMPRESA]: '900123456-7' });
        expect(CLAVE_NIT_EMPRESA).toBe('gestpro.secop.nitEmpresa');
        expect(leerNitEmpresa(a)).toBe('900123456-7');
    });

    it('un NIT no válido no se guarda; uno guardado que no es válido se lee vacío', () => {
        const a = almacen();
        guardarNitEmpresa('abc', a);
        expect(a.datos).toEqual({});
        expect(leerNitEmpresa(almacen({ [CLAVE_NIT_EMPRESA]: '<script>' }))).toBe('');
        expect(leerNitEmpresa(almacen())).toBe('');
    });

    it('sin almacenamiento (bloqueado o ausente) no falla', () => {
        expect(leerNitEmpresa(roto)).toBe('');
        expect(() => guardarNitEmpresa('900123456', roto)).not.toThrow();
        expect(leerNitEmpresa(null)).toBe('');
        expect(() => guardarNitEmpresa('900123456', null)).not.toThrow();
    });
});

describe('importar de SECOP II', () => {
    const resultado = { contrato: { n_contrato: 'C-1', secop_id_contrato: 'CO1.PCCNTR.1' }, secop: {}, registrado: null };

    it('el estado de navegación lleva la lista de la que se viene y el resultado', () => {
        expect(estadoImportarSecop(resultado, '?vista=secop&nit=900123456'))
            .toEqual({ lista: '?vista=secop&nit=900123456', secop: resultado });
    });

    it('resultadoSecopDeEstado devuelve el resultado solo si tiene la forma de uno', () => {
        expect(resultadoSecopDeEstado({ lista: '?vista=secop', secop: resultado })).toBe(resultado);
        [null, undefined, {}, { secop: null }, { secop: 'x' }, { secop: {} }, { secop: { contrato: 'x' } }, { secop: { contrato: [] } }]
            .forEach((estado) => expect(resultadoSecopDeEstado(estado)).toBeNull());
    });

    it('al guardar se conserva solo la lista, no el resultado', () => {
        expect(estadoSinSecop({ lista: '?vista=secop', secop: resultado })).toEqual({ lista: '?vista=secop' });
        expect(estadoSinSecop(null)).toEqual({});
    });

    it('el texto sin resultados', () => {
        expect(TEXTO_SECOP_VACIO).toBe('SECOP II no tiene contratos con ese NIT como proveedor.');
    });
});
