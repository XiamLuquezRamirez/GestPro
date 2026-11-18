import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import {
    CAMPOS_CONTRATO,
    CONTRATO_VACIO,
    erroresGuardarContrato,
    primerCampoConError,
    ESTADOS_CONTRATO,
    aplicarPrecarga,
    contratoVacio,
    datosContrato,
    datosDelProceso,
    etiquetaEstadoContrato,
    formularioContrato,
    notaProceso,
    opcionesProceso,
    opcionesTipoContrato,
    procesoElegido,
    validarContrato,
    valorVigente,
    vigentesDistintos,
} from './contratos';

// Un contrato como lo devuelve presentar (POST /guardarContrato).
const presentado = {
    id: 7,
    proyecto: 3,
    n_contrato: 'CO-001',
    objeto: 'Construcción del puente',
    contratante: 'Alcaldía de Becerril',
    contratante_nit: '800.123.456-1',
    contratista: 'Constructora S.A.S.',
    contratista_identificacion: '900.555.111-2',
    tipo_contrato_id: 2,
    tipo_contrato: { id: 2, nombre: 'Obra', activo: true },
    valor_inicial: '1500000.50',
    valor: '1500000.50',
    fecha_inicio: '2026-01-10',
    fecha_fin_inicial: '2026-06-30',
    fecha_fin: '2026-06-30',
    fecha_suscripcion: '2026-01-05',
    numero_rp: 'RP-77',
    fecha_rp: '2026-01-06',
    fecha_acta_inicio: '2026-01-10',
    supervisor: 'Ana Pérez',
    interventoria: 'Interventora Ltda.',
    estado: 'en_ejecucion',
    estado_original: 'vigente',
    anticipo: true,
    porcentaje_anticipo: 30,
    avance_fisico: 40,
    avance_financiero: 25,
    proceso_id: 5,
    proceso: { id: 5, codigo_proceso: 'LP-01-2026', objeto: 'Puente', estado: 'celebrado', modalidad: { id: 1, nombre: 'Licitación pública' }, plataforma: 'secop_ii', url_proceso: 'https://community.secop.gov.co/p/1' },
    anexos: [],
    updated_at: '2026-11-18 09:00:00',
};

// El mismo contrato como lo devuelven GET /proyectos y GET /listarContratos: columnas crudas.
const listado = {
    ...presentado,
    tipo_contrato: undefined,
    proyecto: '3',
    proceso_licitacion: 5,
    tipo_contrato_id: '2',
    anticipo: 1,
    porcentaje_anticipo: '30',
    fecha_inicio: '2026-01-10 00:00:00',
};

describe('estados del contrato', () => {
    it('coinciden con Contrato::ESTADOS y en el mismo orden', () => {
        // Ruta relativa a este archivo, no al directorio desde donde se lance Vitest.
        const modelo = readFileSync(new URL('../../../app/Models/Contrato.php', import.meta.url), 'utf8');
        const valor = (nombre) => modelo.match(new RegExp(`const ${nombre} = '([a-z_]+)'`))[1];
        const lista = modelo.match(/const ESTADOS = \[([^\]]*)\]/)[1]
            .split(',').map((x) => x.trim()).filter(Boolean).map((x) => valor(x.replace('self::', '')));
        expect(ESTADOS_CONTRATO.map(([v]) => v)).toEqual(lista);
    });

    it('tienen las etiquetas acordadas', () => {
        expect(ESTADOS_CONTRATO.map(([, e]) => e)).toEqual(['Celebrado', 'En ejecución', 'Suspendido', 'Terminado', 'Liquidado', 'Anulado']);
    });

    it('la etiqueta de un estado conocido, el texto crudo de uno desconocido y una raya sin estado', () => {
        expect(etiquetaEstadoContrato('en_ejecucion')).toBe('En ejecución');
        expect(etiquetaEstadoContrato('anulado')).toBe('Anulado');
        expect(etiquetaEstadoContrato('Vigente')).toBe('Vigente');
        expect(etiquetaEstadoContrato('')).toBe('—');
        expect(etiquetaEstadoContrato(null)).toBe('—');
    });
});

describe('formulario vacío', () => {
    it('tiene todos los campos de la API vacíos y sin anticipo', () => {
        expect(CAMPOS_CONTRATO).toEqual(Object.keys(CONTRATO_VACIO));
        const vacio = contratoVacio();
        CAMPOS_CONTRATO.filter((c) => c !== 'anticipo').forEach((c) => expect(vacio[c]).toBe(''));
        expect(vacio.anticipo).toBe(false);
        expect(vacio.id).toBeUndefined();
    });

    it('lleva el proyecto como texto, para el select', () => {
        expect(contratoVacio(3).proyecto).toBe('3');
        expect(contratoVacio(null).proyecto).toBe('');
    });

    it('cada llamada devuelve una copia', () => {
        const a = contratoVacio();
        a.objeto = 'x';
        expect(contratoVacio().objeto).toBe('');
        expect(CONTRATO_VACIO.objeto).toBe('');
    });
});

describe('formularioContrato', () => {
    it('sin contrato es el formulario vacío', () => {
        expect(formularioContrato(null)).toEqual(contratoVacio());
    });

    it('desde presentar trae todos los campos, con el valor inicial agrupado', () => {
        expect(formularioContrato(presentado)).toEqual({
            id: 7,
            proyecto: '3',
            proceso_id: '5',
            tipo_contrato_id: '2',
            n_contrato: 'CO-001',
            objeto: 'Construcción del puente',
            contratante: 'Alcaldía de Becerril',
            contratante_nit: '800.123.456-1',
            contratista: 'Constructora S.A.S.',
            contratista_identificacion: '900.555.111-2',
            supervisor: 'Ana Pérez',
            interventoria: 'Interventora Ltda.',
            numero_rp: 'RP-77',
            valor_inicial: '1.500.000,50',
            fecha_inicio: '2026-01-10',
            fecha_fin_inicial: '2026-06-30',
            fecha_suscripcion: '2026-01-05',
            fecha_rp: '2026-01-06',
            fecha_acta_inicio: '2026-01-10',
            estado: 'en_ejecucion',
            anticipo: true,
            porcentaje_anticipo: '30',
            avance_fisico: 40,
            avance_financiero: 25,
        });
    });

    it('desde el listado da lo mismo que desde presentar', () => {
        expect(formularioContrato(listado)).toEqual(formularioContrato(presentado));
    });

    it('un contrato sin valor ni fecha fin iniciales toma los vigentes', () => {
        const f = formularioContrato({ ...presentado, valor_inicial: null, valor: '2000000.00', fecha_fin_inicial: null, fecha_fin: '2026-07-31' });
        expect(f.valor_inicial).toBe('2.000.000');
        expect(f.fecha_fin_inicial).toBe('2026-07-31');
    });

    it('conserva los avances en cero y deja vacíos los nulos', () => {
        const f = formularioContrato({ ...presentado, avance_fisico: 0, avance_financiero: null, porcentaje_anticipo: null, anticipo: 0 });
        expect(f.avance_fisico).toBe(0);
        expect(f.avance_financiero).toBe('');
        expect(f.porcentaje_anticipo).toBe('');
        expect(f.anticipo).toBe(false);
    });

    it('toma el proceso y el tipo de los objetos si faltan los ids', () => {
        const f = formularioContrato({ id: 1, proceso: { id: 9 }, tipo_contrato: { id: 4, nombre: 'Suministro' } });
        expect(f.proceso_id).toBe('9');
        expect(f.tipo_contrato_id).toBe('4');
    });

    it('no pierde ningún campo: formulario y vuelta dan lo que tenía el contrato', () => {
        const datos = datosContrato(formularioContrato(presentado), presentado);
        CAMPOS_CONTRATO.forEach((campo) => expect(datos[campo], campo).toEqual(presentado[campo]));
    });
});

describe('datosContrato', () => {
    it('al crear: sin id ni actualizado; vacíos como null; ids como número', () => {
        const datos = datosContrato({ ...contratoVacio(3), n_contrato: ' CO-9 ', objeto: 'Obra', valor_inicial: '1.000', estado: 'celebrado' });
        expect(datos).toEqual({
            proyecto: 3,
            proceso_id: null,
            tipo_contrato_id: null,
            n_contrato: 'CO-9',
            objeto: 'Obra',
            contratante: null,
            contratante_nit: null,
            contratista: null,
            contratista_identificacion: null,
            supervisor: null,
            interventoria: null,
            numero_rp: null,
            valor_inicial: '1000',
            fecha_inicio: null,
            fecha_fin_inicial: null,
            fecha_suscripcion: null,
            fecha_rp: null,
            fecha_acta_inicio: null,
            estado: 'celebrado',
            anticipo: false,
            porcentaje_anticipo: null,
            avance_fisico: null,
            avance_financiero: null,
        });
    });

    it('al editar: el id y el updated_at del contrato que se abrió', () => {
        const datos = datosContrato(formularioContrato(presentado), presentado);
        expect(datos.id).toBe(7);
        expect(datos.actualizado).toBe('2026-11-18 09:00:00');
    });

    it('un contrato antiguo, sin updated_at, envía actualizado nulo', () => {
        const antiguo = { ...presentado, updated_at: null };
        expect(datosContrato(formularioContrato(antiguo), antiguo).actualizado).toBeNull();
    });

    it('limita los avances a 0..100', () => {
        const f = { ...contratoVacio(), avance_fisico: 120.4, avance_financiero: -3 };
        expect(datosContrato(f).avance_fisico).toBe(100);
        expect(datosContrato(f).avance_financiero).toBe(0);
        expect(datosContrato({ ...f, avance_fisico: '45.67' }).avance_fisico).toBe(45.67);
        expect(datosContrato({ ...f, avance_fisico: 'abc' }).avance_fisico).toBeNull();
    });

    it('sin anticipo, el porcentaje va nulo', () => {
        expect(datosContrato({ ...contratoVacio(), anticipo: false, porcentaje_anticipo: '20' }).porcentaje_anticipo).toBeNull();
        expect(datosContrato({ ...contratoVacio(), anticipo: true, porcentaje_anticipo: '20' }).porcentaje_anticipo).toBe(20);
    });

    it('envía el proceso elegido como número y "sin proceso" como null', () => {
        expect(datosContrato({ ...contratoVacio(), proceso_id: '5' }).proceso_id).toBe(5);
        expect(datosContrato({ ...contratoVacio(), proceso_id: '' }).proceso_id).toBeNull();
    });

    it('no envía el valor ni la fecha fin vigentes', () => {
        const datos = datosContrato({ ...formularioContrato(presentado), valor: '1', fecha_fin: '2030-01-01' }, presentado);
        expect(datos).not.toHaveProperty('valor');
        expect(datos).not.toHaveProperty('fecha_fin');
    });
});

describe('validarContrato', () => {
    const valido = { ...contratoVacio(3), n_contrato: 'CO-1', objeto: 'Obra', valor_inicial: '1.000', estado: 'celebrado' };

    it('un formulario con lo obligatorio no tiene errores', () => {
        expect(validarContrato(valido)).toEqual({});
    });

    it('pide número, objeto, valor inicial y estado con los mensajes del servidor', () => {
        expect(validarContrato(contratoVacio())).toEqual({
            n_contrato: 'Escribe el número del contrato.',
            objeto: 'Escribe el objeto del contrato.',
            valor_inicial: 'Escribe el valor inicial del contrato.',
            estado: 'Elige el estado del contrato.',
        });
    });

    it('acepta un valor inicial de cero', () => {
        expect(validarContrato({ ...valido, valor_inicial: '0' })).toEqual({});
    });

    it('rechaza un valor de más de 13 cifras enteras', () => {
        expect(validarContrato({ ...valido, valor_inicial: '12345678901234' }).valor_inicial)
            .toBe('El valor inicial admite hasta 13 cifras enteras y 2 decimales.');
    });

    it('rechaza una fecha fin inicial anterior a la de inicio', () => {
        expect(validarContrato({ ...valido, fecha_inicio: '2026-02-01', fecha_fin_inicial: '2026-01-31' }).fecha_fin_inicial)
            .toBe('La fecha de fin no puede ser anterior a la de inicio.');
        expect(validarContrato({ ...valido, fecha_inicio: '2026-02-01', fecha_fin_inicial: '2026-02-01' })).toEqual({});
    });

    it('respeta los largos máximos', () => {
        const errores = validarContrato({ ...valido, n_contrato: 'x'.repeat(101), numero_rp: 'x'.repeat(41), contratista_identificacion: 'x'.repeat(31) });
        expect(Object.keys(errores)).toEqual(['n_contrato', 'contratista_identificacion', 'numero_rp']);
    });
});

describe('procesos del contrato', () => {
    const procesos = [
        { id: 1, codigo_proceso: 'SA-01', objeto: 'Estudios', estado: 'evaluacion' },
        { id: 2, codigo_proceso: 'LP-02', objeto: 'Puente', estado: 'adjudicado' },
        { id: 3, codigo_proceso: '', objeto: 'Suministro de tubería', estado: 'celebrado' },
        { id: 4, codigo_proceso: 'CD-04', objeto: 'Viejo', estado: 'cancelado' },
    ];

    it('opciones: sin proceso y los adjudicados o celebrados', () => {
        expect(opcionesProceso(procesos, null)).toEqual([
            { valor: '', etiqueta: 'Sin proceso' },
            { valor: '2', etiqueta: 'LP-02 · Puente (Adjudicado)' },
            { valor: '3', etiqueta: 'Sin número · Suministro de tubería (Celebrado)' },
        ]);
    });

    it('opciones: también el actual aunque ya no admita contratos', () => {
        expect(opcionesProceso(procesos, { id: 4 }).map((o) => o.valor)).toEqual(['', '2', '3', '4']);
    });

    it('opciones: el actual con la lista sin cargar, desde su resumen', () => {
        expect(opcionesProceso(null, { id: 9, codigo_proceso: 'X-9', objeto: 'Otro', estado: 'celebrado' })).toEqual([
            { valor: '', etiqueta: 'Sin proceso' },
            { valor: '9', etiqueta: 'X-9 · Otro (Celebrado)' },
        ]);
    });

    it('opciones: recorta un objeto largo', () => {
        const [, opcion] = opcionesProceso([{ id: 1, codigo_proceso: 'A', objeto: 'x'.repeat(100), estado: 'adjudicado' }], null);
        expect(opcion.etiqueta).toBe(`A · ${'x'.repeat(60)}… (Adjudicado)`);
    });

    it('el proceso elegido sale de la lista y, si no está, del contrato', () => {
        expect(procesoElegido(procesos, null, '2')).toBe(procesos[1]);
        const actual = { id: 9, codigo_proceso: 'X-9' };
        expect(procesoElegido(procesos, actual, '9')).toBe(actual);
        expect(procesoElegido(procesos, actual, '')).toBeNull();
        expect(procesoElegido(null, null, '7')).toBeNull();
    });

    it('datos de solo lectura del proceso: número, modalidad, plataforma y enlace', () => {
        expect(datosDelProceso(presentado.proceso)).toEqual({
            numero: 'LP-01-2026',
            enlace: 'https://community.secop.gov.co/p/1',
            modalidad: 'Licitación pública',
            plataforma: 'SECOP II',
        });
        expect(datosDelProceso({ id: 1, url_proceso: 'javascript:alert(1)' })).toEqual({ numero: '—', enlace: null, modalidad: '—', plataforma: '—' });
    });

    it('nota R4: si el proyecto tiene procesos y no se elige ninguno', () => {
        expect(notaProceso(procesos, '')).toBe('Este proyecto tiene procesos de contratación: si el contrato salió de uno, elígelo.');
        expect(notaProceso(procesos, '2')).toBeNull();
        expect(notaProceso([], '')).toBeNull();
        expect(notaProceso(null, '')).toBeNull();
    });

    it('nota R4: si ningún proceso admite contratos, lo dice', () => {
        expect(notaProceso([procesos[0]], '')).toBe('Este proyecto tiene procesos de contratación, pero ninguno adjudicado o celebrado: cuando lo esté, podrás vincularle el contrato.');
    });
});

describe('aplicarPrecarga', () => {
    const precarga = { objeto: 'Puente vehicular', contratante: 'Gobernación del Cesar', valor_inicial: '2500000.00' };

    it('llena los campos vacíos', () => {
        const f = aplicarPrecarga(contratoVacio(3), precarga);
        expect(f.objeto).toBe('Puente vehicular');
        expect(f.contratante).toBe('Gobernación del Cesar');
        expect(f.valor_inicial).toBe('2.500.000');
        expect(f.proyecto).toBe('3');
    });

    it('no toca lo que ya se escribió (R8)', () => {
        const f = aplicarPrecarga({ ...contratoVacio(), objeto: 'Mío', contratante: ' Yo ', valor_inicial: '10' }, precarga);
        expect(f.objeto).toBe('Mío');
        expect(f.contratante).toBe(' Yo ');
        expect(f.valor_inicial).toBe('10');
    });

    it('un campo con solo espacios cuenta como vacío', () => {
        expect(aplicarPrecarga({ ...contratoVacio(), objeto: '   ' }, precarga).objeto).toBe('Puente vehicular');
    });

    it('no sugiere un valor nulo ni cero, ni textos vacíos', () => {
        const f = aplicarPrecarga(contratoVacio(), { objeto: null, contratante: '', valor_inicial: '0.00' });
        expect(f).toEqual(contratoVacio());
        expect(aplicarPrecarga(contratoVacio(), { valor_inicial: null }).valor_inicial).toBe('');
    });

    it('sin precarga devuelve el formulario igual', () => {
        const f = contratoVacio();
        expect(aplicarPrecarga(f, null)).toEqual(f);
    });
});

describe('vigentes', () => {
    it('iguales a los iniciales: nada que mostrar', () => {
        expect(vigentesDistintos(presentado)).toEqual({ valor: false, fechaFin: false });
    });

    it('con adiciones o prórrogas, distintos', () => {
        expect(vigentesDistintos({ ...presentado, valor: '2000000.50', fecha_fin: '2026-07-30' })).toEqual({ valor: true, fechaFin: true });
    });

    it('compara valores como números y fechas por día', () => {
        expect(vigentesDistintos({ ...presentado, valor_inicial: '1500000.5', fecha_fin: '2026-06-30 00:00:00' })).toEqual({ valor: false, fechaFin: false });
    });

    it('sin contrato o sin iniciales, nada que mostrar', () => {
        expect(vigentesDistintos(null)).toEqual({ valor: false, fechaFin: false });
        expect(vigentesDistintos({ valor: '10', valor_inicial: null, fecha_fin: '2026-01-01', fecha_fin_inicial: null })).toEqual({ valor: false, fechaFin: false });
    });

    it('valor vigente: el inicial del formulario más las adiciones del contrato', () => {
        const conAdicion = { ...presentado, valor_inicial: '1000.00', valor: '1500.00' };
        expect(valorVigente({ valor_inicial: '1.000' }, conAdicion)).toBe(1500);
        expect(valorVigente({ valor_inicial: '2.000,25' }, conAdicion)).toBe(2500.25);
    });

    it('valor vigente de un contrato nuevo es su valor inicial; vacío, cero', () => {
        expect(valorVigente({ valor_inicial: '1.234,5' }, null)).toBe(1234.5);
        expect(valorVigente({ valor_inicial: '' }, null)).toBe(0);
    });
});

describe('opcionesTipoContrato', () => {
    const tipos = [
        { id: 1, nombre: 'Obra', activo: true },
        { id: 2, nombre: 'Consultoría', activo: 0 },
        { id: 3, nombre: 'Suministro', activo: 1 },
    ];

    it('los activos', () => {
        expect(opcionesTipoContrato(tipos, null)).toEqual([
            { valor: '1', etiqueta: 'Obra' },
            { valor: '3', etiqueta: 'Suministro' },
        ]);
    });

    it('y el actual si está inactivo, marcado', () => {
        expect(opcionesTipoContrato(tipos, { tipo_contrato_id: '2' })).toEqual([
            { valor: '1', etiqueta: 'Obra' },
            { valor: '3', etiqueta: 'Suministro' },
            { valor: '2', etiqueta: 'Consultoría (inactivo)' },
        ]);
    });

    it('con la lista sin cargar, el actual con su nombre', () => {
        expect(opcionesTipoContrato(null, presentado)).toEqual([{ valor: '2', etiqueta: 'Obra' }]);
        expect(opcionesTipoContrato(null, { tipo_contrato_id: 8 })).toEqual([{ valor: '8', etiqueta: 'Tipo actual' }]);
    });

    it('el actual activo no se repite', () => {
        expect(opcionesTipoContrato(tipos, { tipo_contrato_id: 1 })).toHaveLength(2);
    });
});

describe('erroresGuardarContrato', () => {
    const error422 = (errors) => ({ response: { status: 422, data: { message: 'x', errors } } });

    it('los errores de campos con control quedan por campo; los demás, para avisar', () => {
        const r = erroresGuardarContrato(error422({
            objeto: ['Escribe el objeto del contrato.'],
            proyecto: ['El contrato debe seguir en un proyecto.'],
            'anexos.0.fecha': ['Falta la fecha del anexo.'],
        }));
        expect(r.campos).toEqual({ objeto: 'Escribe el objeto del contrato.' });
        expect(r.otros).toEqual(['El contrato debe seguir en un proyecto.', 'Falta la fecha del anexo.']);
    });

    it('un error que no es 422 da null', () => {
        expect(erroresGuardarContrato({ response: { status: 500 } })).toBeNull();
    });
});

describe('primerCampoConError', () => {
    it('el primero en el orden del formulario, sin contar los vacíos', () => {
        expect(primerCampoConError({ estado: 'a', n_contrato: 'b', objeto: undefined })).toBe('n_contrato');
        expect(primerCampoConError({ objeto: undefined })).toBeNull();
        expect(primerCampoConError({})).toBeNull();
    });
});
