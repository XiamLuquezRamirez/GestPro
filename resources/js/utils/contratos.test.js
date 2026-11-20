import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import {
    ACTA_VACIA,
    MODIFICACION_VACIA,
    actividadConAvance,
    anexosDelContrato,
    anexosParaGuardar,
    avanceFinancieroDeActas,
    avanceFisicoDeActividades,
    calculoActa,
    datosActa,
    datosModificacion,
    etiquetaTipoModificacion,
    sumaPesos,
    CAMPOS_CONTRATO,
    CONTRATO_VACIO,
    erroresGuardarContrato,
    primerCampoConError,
    ESTADOS_CONTRATO,
    aplicarPrecarga,
    cambiarProyecto,
    contratoVacio,
    datosContrato,
    datosDelProceso,
    etiquetaEstadoContrato,
    formularioContrato,
    notaProceso,
    opcionesProceso,
    opcionesTipoContrato,
    procesoElegido,
    procesoDelContrato,
    validarContrato,
    valorVigente,
    vigentesDistintos,
} from './contratos';
import {
    CAMPOS_LIQUIDAR,
    CAMPOS_TERMINAR,
    accionesCierre,
    notaSinEstadoCierre,
    avisoEstadoCierreSecop,
    cambiosSinGuardar,
    contratoTrasCierre,
    datosLiquidar,
    datosTerminar,
    erroresCierre,
    erroresLiquidar,
    erroresTerminar,
    estadoCierreBloqueado,
    formularioLiquidar,
    formularioTerminar,
    muestraBloqueCierre,
    opcionesEstadoContrato,
    saldoLiquidacion,
    textoAvisoCierre,
} from './contratos';
import {
    CAMPOS_REINICIAR,
    CAMPOS_SUSPENDER,
    TIPOS_MODIFICACION,
    accionesSuspension,
    chipAvisoSuspension,
    confirmacionEliminarModificacion,
    contratoTrasSuspension,
    datosEliminarModificacion,
    datosReiniciar,
    datosSuspender,
    detalleSuspension,
    diasEntre,
    erroresReiniciar,
    erroresSuspender,
    formularioReiniciar,
    formularioSuspender,
    notaSuspensionCierre,
    puedeEliminarModificacion,
    sumarDias,
    suspensionAbierta,
    textoAvisoSuspension,
    textoDiasModificacion,
    textoReinicioPrevisto,
    textoVistaPreviaReinicio,
    vistaPreviaReinicio,
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

describe('proyecto del contrato', () => {
    it('cambiar de proyecto quita el proceso, que era del anterior', () => {
        const nuevo = { ...contratoVacio(), proceso_id: '4', objeto: 'Obra' };
        expect(cambiarProyecto(nuevo, '9', null)).toEqual({ ...nuevo, proyecto: '9', proceso_id: '' });
        expect(cambiarProyecto({ ...nuevo, proyecto: '9' }, '', null)).toMatchObject({ proyecto: '', proceso_id: '' });
    });

    it('volver al proyecto del contrato abierto le devuelve su proceso', () => {
        const formulario = { ...formularioContrato(presentado), proyecto: '8', proceso_id: '' };
        expect(cambiarProyecto(formulario, '3', presentado)).toMatchObject({ proyecto: '3', proceso_id: '5' });
        expect(cambiarProyecto(formulario, 3, listado)).toMatchObject({ proyecto: '3', proceso_id: '5' });
    });

    it('elegir el mismo proyecto no cambia nada', () => {
        const formulario = { ...formularioContrato(presentado), proceso_id: '6' };
        expect(cambiarProyecto(formulario, '3', presentado)).toBe(formulario);
    });

    it('el proceso que el contrato ya tiene solo cuenta mientras siga en su proyecto', () => {
        expect(procesoDelContrato(presentado, formularioContrato(presentado))).toBe(presentado.proceso);
        expect(procesoDelContrato(listado, formularioContrato(listado))).toBe(listado.proceso);
        expect(procesoDelContrato(presentado, { ...formularioContrato(presentado), proyecto: '8' })).toBeNull();
        expect(procesoDelContrato(null, contratoVacio(3))).toBeNull();
        expect(procesoDelContrato({ ...presentado, proceso: null }, formularioContrato(presentado))).toBeNull();
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

describe('pestañas del contrato', () => {
    it('los anexos del listado se leen para la pestaña y se envían sin id', () => {
        const contrato = { anexos: [{ id: 4, descripcion: 'Póliza', nombre_archivo: 'p.pdf', ruta_archivo: 'anexos_contratos/p.pdf', fecha: '2026-01-10' }] };
        const anexos = anexosDelContrato(contrato);
        expect(anexos).toEqual([{ id: 4, descripcion: 'Póliza', nombreArchivo: 'p.pdf', rutaArchivo: 'anexos_contratos/p.pdf', fecha: '2026-01-10' }]);
        expect(anexosParaGuardar(anexos)).toEqual([{ descripcion: 'Póliza', nombreArchivo: 'p.pdf', rutaArchivo: 'anexos_contratos/p.pdf', fecha: '2026-01-10' }]);
        expect(anexosDelContrato({ anexos: [] })).toEqual([]);
        expect(anexosDelContrato(null)).toEqual([]);
    });

    it('el acta amortiza el anticipo y calcula el porcentaje sobre el vigente', () => {
        const formulario = { valor_inicial: '1.000.000', anticipo: true, porcentaje_anticipo: '30' };
        expect(calculoActa(200000, formulario, null)).toEqual({ amortizacion: 60000, valorPresente: 140000, porcentajeEjecutado: 20 });
        // Con una adición de 1.000.000 el vigente es 2.000.000.
        expect(calculoActa(200000, { ...formulario, anticipo: false }, { valor: '2000000.00', valor_inicial: '1000000.00' }))
            .toEqual({ amortizacion: 0, valorPresente: 200000, porcentajeEjecutado: 10 });
        expect(calculoActa(5000, { valor_inicial: '', anticipo: false }, null).porcentajeEjecutado).toBe(0);
    });

    it('lo que se envía de un acta', () => {
        const formulario = {
            ...ACTA_VACIA, descripcion: 'Acta 1', fecha_acta: '2026-02-01', valor_facturado: '$ 1.500,50',
            amortizacion_50: 0, valor_presente_acta: 1500.5, porcentaje_ejecutado: 15,
        };
        expect(datosActa(7, formulario, 'actas/a.pdf')).toEqual({
            contrato_id: 7, descripcion: 'Acta 1', fecha_acta: '2026-02-01', valor_facturado: 1500.5,
            amortizacion_50: 0, valor_presente_acta: 1500.5, porcentaje_ejecutado: 15, anexo: 'actas/a.pdf',
        });
    });

    it('el avance financiero suma lo facturado sobre el vigente; sin actas o sin vigente, vacío', () => {
        expect(avanceFinancieroDeActas([{ valor_facturado: '250000.00' }, { valor_facturado: 83333 }], 1000000)).toBe(33.33);
        expect(avanceFinancieroDeActas([], 1000000)).toBe('');
        expect(avanceFinancieroDeActas([{ valor_facturado: 10 }], 0)).toBe('');
    });

    it('el avance físico pondera el último avance de cada actividad', () => {
        const actividades = [{ peso: 60, ultimo_avance: 50 }, { peso: 40, ultimo_avance: null }, { peso: 0 }];
        expect(sumaPesos(actividades)).toBe(100);
        expect(avanceFisicoDeActividades(actividades)).toBe(30);
        expect(avanceFisicoDeActividades([{ peso: 33, ultimo_avance: 50 }])).toBe(17);
        expect(avanceFisicoDeActividades([])).toBe(0);
    });

    it('un avance solo pasa a ser el último si es igual o más reciente', () => {
        const actividad = { id: 1, ultimo_avance: 40, fecha_ultimo_avance: '2026-03-10' };
        expect(actividadConAvance(actividad, '2026-03-10', '55')).toEqual({ id: 1, ultimo_avance: 55, fecha_ultimo_avance: '2026-03-10' });
        expect(actividadConAvance(actividad, '2026-03-01', '55')).toBe(actividad);
        expect(actividadConAvance({ id: 2, ultimo_avance: null, fecha_ultimo_avance: null }, '2026-01-01', '10').ultimo_avance).toBe(10);
    });

    it('una prórroga no envía valor ni una adición días', () => {
        const base = {
            ...MODIFICACION_VACIA, numero_otrosi: 'Otrosí 1', valor_adicion: '$ 500.000,25', dias_prorroga: '30',
            fecha_modificacion: '2026-04-01', justificacion: 'Lluvias',
        };
        expect(datosModificacion(7, base)).toEqual({
            contrato_id: 7, numero_otrosi: 'Otrosí 1', tipo: 'adicion', valor_adicion: 500000.25,
            dias_prorroga: null, fecha_modificacion: '2026-04-01', justificacion: 'Lluvias',
        });
        expect(datosModificacion(7, { ...base, tipo: 'prorroga' })).toMatchObject({ valor_adicion: null, dias_prorroga: '30' });
        expect(datosModificacion(7, { ...base, tipo: 'adicion_prorroga', valor_adicion: '', dias_prorroga: '' }))
            .toMatchObject({ valor_adicion: null, dias_prorroga: null });
    });

    it('la etiqueta del tipo de modificación', () => {
        expect(etiquetaTipoModificacion('adicion')).toBe('Adición');
        expect(etiquetaTipoModificacion('prorroga')).toBe('Prórroga');
        expect(etiquetaTipoModificacion('adicion_prorroga')).toBe('Adición y Prórroga');
    });
});

// ---------- Cierre del contrato (fase 7A) ----------

describe('accionesCierre', () => {
    const con = (estado) => accionesCierre({ ...presentado, estado });

    it('un contrato celebrado, en ejecución o suspendido se termina y se liquida', () => {
        ['celebrado', 'en_ejecucion', 'suspendido'].forEach((estado) => {
            expect(con(estado)).toEqual({ terminar: true, liquidar: true, etiquetaTerminar: 'Terminar', etiquetaLiquidar: 'Liquidar' });
        });
    });

    it('uno terminado corrige su terminación y se liquida', () => {
        expect(con('terminado')).toEqual({ terminar: true, liquidar: true, etiquetaTerminar: 'Corregir terminación', etiquetaLiquidar: 'Liquidar' });
    });

    it('uno liquidado no se termina: corrige su liquidación', () => {
        expect(con('liquidado')).toEqual({ terminar: false, liquidar: true, etiquetaTerminar: 'Terminar', etiquetaLiquidar: 'Corregir liquidación' });
    });

    it('uno anulado no ofrece ninguna', () => {
        expect(con('anulado')).toMatchObject({ terminar: false, liquidar: false });
    });

    it('un estado antiguo fuera de la lista se liquida pero no se termina, como en el servidor', () => {
        expect(con('Vigente')).toMatchObject({ terminar: false, liquidar: true });
    });

    it('un contrato nuevo (sin id) o ninguno no ofrece nada', () => {
        expect(accionesCierre({ ...presentado, id: undefined })).toMatchObject({ terminar: false, liquidar: false });
        expect(accionesCierre(null)).toMatchObject({ terminar: false, liquidar: false });
    });
});

describe('notaSinEstadoCierre', () => {
    const nota = 'Elige un estado para el contrato y guárdalo para poder terminarlo.';

    it('un contrato guardado sin estado o con uno fuera de la lista: elegir y guardar el estado', () => {
        expect(notaSinEstadoCierre({ ...presentado, estado: null })).toBe(nota);
        expect(notaSinEstadoCierre({ ...presentado, estado: '' })).toBe(nota);
        expect(notaSinEstadoCierre({ ...presentado, estado: 'Vigente' })).toBe(nota);
    });

    it('con un estado de la lista, o sin contrato guardado, no hay nota', () => {
        ESTADOS_CONTRATO.forEach(([estado]) => expect(notaSinEstadoCierre({ ...presentado, estado })).toBeNull());
        expect(notaSinEstadoCierre({ ...presentado, id: undefined, estado: null })).toBeNull();
        expect(notaSinEstadoCierre(null)).toBeNull();
    });

    it('esos contratos se liquidan (el servidor lo acepta) pero no se terminan', () => {
        expect(accionesCierre({ ...presentado, estado: null })).toMatchObject({ terminar: false, liquidar: true });
    });
});

describe('textoAvisoCierre', () => {
    it('sin aviso o sin faltantes, null', () => {
        expect(textoAvisoCierre(null)).toBeNull();
        expect(textoAvisoCierre(undefined)).toBeNull();
        expect(textoAvisoCierre({ faltan: [] })).toBeNull();
    });

    it('si falta algo de la liquidación', () => {
        expect(textoAvisoCierre({ faltan: ['fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado'] })).toBe('Faltan datos de liquidación');
        expect(textoAvisoCierre({ faltan: ['valor_liquidado'] })).toBe('Faltan datos de liquidación');
        expect(textoAvisoCierre({ faltan: ['fecha_liquidacion'] })).toBe('Faltan datos de liquidación');
    });

    it('si solo falta la fecha de terminación', () => {
        expect(textoAvisoCierre({ faltan: ['fecha_terminacion_real'] })).toBe('Falta la fecha de terminación');
    });
});

describe('saldoLiquidacion', () => {
    it('el valor vigente menos el liquidado, en centavos exactos', () => {
        expect(saldoLiquidacion({ valor: '1500000.50', valor_liquidado: '1250000.25' })).toBe('250000.25');
        // Con coma flotante, 0.3 - 0.1 daría 0.19999999999999998.
        expect(saldoLiquidacion({ valor: '0.30', valor_liquidado: '0.10' })).toBe('0.20');
        expect(saldoLiquidacion({ valor: '9999999999999.99', valor_liquidado: '0.01' })).toBe('9999999999999.98');
        expect(saldoLiquidacion({ valor: '1000', valor_liquidado: '1000.00' })).toBe('0.00');
    });

    it('negativo si lo liquidado supera lo vigente', () => {
        expect(saldoLiquidacion({ valor: '1000000.00', valor_liquidado: '1000000.50' })).toBe('-0.50');
        expect(saldoLiquidacion({ valor: '100', valor_liquidado: '250.5' })).toBe('-150.50');
    });

    it('acepta números y textos de la API con uno o dos decimales', () => {
        expect(saldoLiquidacion({ valor: 1500000.5, valor_liquidado: '500000.5' })).toBe('1000000.00');
    });

    it('sin valor vigente o sin valor liquidado (o con uno ilegible), null', () => {
        expect(saldoLiquidacion({ valor: '1000.00', valor_liquidado: null })).toBeNull();
        expect(saldoLiquidacion({ valor: null, valor_liquidado: '10.00' })).toBeNull();
        expect(saldoLiquidacion({ valor: '1000.00', valor_liquidado: '' })).toBeNull();
        expect(saldoLiquidacion({ valor: 'mil', valor_liquidado: '10.00' })).toBeNull();
        expect(saldoLiquidacion({ valor: '10.001', valor_liquidado: '10.00' })).toBeNull();
        expect(saldoLiquidacion(null)).toBeNull();
    });
});

describe('opcionesEstadoContrato', () => {
    const deshabilitados = (opciones) => opciones.filter((o) => o.deshabilitado).map((o) => o.valor);

    it('todos los estados, en el orden de la lista', () => {
        expect(opcionesEstadoContrato('en_ejecucion', false).map((o) => o.valor)).toEqual(ESTADOS_CONTRATO.map(([v]) => v));
    });

    it('al editar, suspendido, terminado y liquidado se deshabilitan y dicen qué acción usar', () => {
        const opciones = opcionesEstadoContrato('en_ejecucion', false);
        expect(deshabilitados(opciones)).toEqual(['suspendido', 'terminado', 'liquidado']);
        expect(opciones.find((o) => o.valor === 'suspendido').etiqueta).toBe('Suspendido (usa «Suspender»)');
        expect(opciones.find((o) => o.valor === 'terminado').etiqueta).toBe('Terminado (usa «Terminar»)');
        expect(opciones.find((o) => o.valor === 'liquidado').etiqueta).toBe('Liquidado (usa «Liquidar»)');
        expect(opciones.find((o) => o.valor === 'en_ejecucion')).toEqual({ valor: 'en_ejecucion', etiqueta: 'En ejecución', deshabilitado: false });
    });

    it('el estado guardado sigue habilitado: un terminado puede quedarse así, pero no pasar a liquidado', () => {
        expect(deshabilitados(opcionesEstadoContrato('terminado', false))).toEqual(['suspendido', 'liquidado']);
        expect(deshabilitados(opcionesEstadoContrato('liquidado', false))).toEqual(['suspendido', 'terminado']);
        expect(deshabilitados(opcionesEstadoContrato('suspendido', false))).toEqual(['terminado', 'liquidado']);
        expect(opcionesEstadoContrato('suspendido', false).find((o) => o.valor === 'suspendido').etiqueta).toBe('Suspendido');
        expect(opcionesEstadoContrato('liquidado', false).find((o) => o.valor === 'liquidado').etiqueta).toBe('Liquidado');
    });

    it('un contrato nuevo puede llegar en cualquier estado', () => {
        expect(deshabilitados(opcionesEstadoContrato('', true))).toEqual([]);
    });
});

describe('formularios y datos de terminar y liquidar', () => {
    const terminado = { ...presentado, estado: 'terminado', fecha_terminacion_real: '2026-10-31', fecha_liquidacion: null, valor_liquidado: null };

    it('el formulario de terminar empieza con la fecha guardada, si la hay', () => {
        expect(formularioTerminar(presentado)).toEqual({ fecha_terminacion_real: '', observacion: '' });
        expect(formularioTerminar(terminado)).toEqual({ fecha_terminacion_real: '2026-10-31', observacion: '' });
    });

    it('el formulario de liquidar empieza con los datos guardados y el valor con los miles agrupados', () => {
        expect(formularioLiquidar(terminado)).toEqual({ fecha_terminacion_real: '2026-10-31', fecha_liquidacion: '', valor_liquidado: '', observacion: '' });
        expect(formularioLiquidar({ ...terminado, estado: 'liquidado', fecha_liquidacion: '2026-11-15 00:00:00', valor_liquidado: '1250000.50' }))
            .toEqual({ fecha_terminacion_real: '2026-10-31', fecha_liquidacion: '2026-11-15', valor_liquidado: '1.250.000,50', observacion: '' });
    });

    it('lo que espera POST /contratos/{id}/terminar, con la versión del contrato', () => {
        expect(datosTerminar({ fecha_terminacion_real: '2026-11-19', observacion: '  Acta final  ' }, presentado))
            .toEqual({ fecha_terminacion_real: '2026-11-19', observacion: 'Acta final', actualizado: '2026-11-18 09:00:00' });
        expect(datosTerminar({ fecha_terminacion_real: '2026-11-19', observacion: ' ' }, { ...presentado, updated_at: null }))
            .toEqual({ fecha_terminacion_real: '2026-11-19', observacion: null, actualizado: null });
    });

    it('lo que espera POST /contratos/{id}/liquidar: el valor como número de la API', () => {
        expect(datosLiquidar({
            fecha_terminacion_real: '2026-10-31', fecha_liquidacion: '2026-11-15', valor_liquidado: '1.250.000,5', observacion: '',
        }, terminado)).toEqual({
            fecha_terminacion_real: '2026-10-31', fecha_liquidacion: '2026-11-15', valor_liquidado: '1250000.5', observacion: null, actualizado: '2026-11-18 09:00:00',
        });
    });
});

describe('erroresTerminar', () => {
    const hoy = '2026-11-19';
    const formulario = { fecha_terminacion_real: '2026-11-19', observacion: '' };

    it('sin errores con una fecha entre el inicio y hoy', () => {
        expect(erroresTerminar(formulario, presentado, hoy)).toEqual({});
        expect(erroresTerminar({ ...formulario, fecha_terminacion_real: '2026-01-10' }, presentado, hoy)).toEqual({});
    });

    it('pide la fecha', () => {
        expect(erroresTerminar({ ...formulario, fecha_terminacion_real: '' }, presentado, hoy))
            .toEqual({ fecha_terminacion_real: 'Escribe la fecha de terminación real.' });
    });

    it('la fecha no es posterior a hoy ni anterior a la de inicio', () => {
        expect(erroresTerminar({ ...formulario, fecha_terminacion_real: '2026-11-20' }, presentado, hoy))
            .toEqual({ fecha_terminacion_real: 'La fecha de terminación no puede ser posterior a hoy.' });
        expect(erroresTerminar({ ...formulario, fecha_terminacion_real: '2026-01-09' }, { ...presentado, fecha_inicio: '2026-01-10 00:00:00' }, hoy))
            .toEqual({ fecha_terminacion_real: 'La fecha de terminación no puede ser anterior a la de inicio.' });
        expect(erroresTerminar({ ...formulario, fecha_terminacion_real: '2020-01-01' }, { ...presentado, fecha_inicio: null }, hoy)).toEqual({});
    });

    it('la observación admite hasta 2000 caracteres', () => {
        expect(erroresTerminar({ ...formulario, observacion: 'a'.repeat(2000) }, presentado, hoy)).toEqual({});
        expect(erroresTerminar({ ...formulario, observacion: 'a'.repeat(2001) }, presentado, hoy))
            .toEqual({ observacion: 'La observación admite hasta 2000 caracteres.' });
    });
});

describe('erroresLiquidar', () => {
    const hoy = '2026-11-19';
    const formulario = { fecha_terminacion_real: '2026-10-31', fecha_liquidacion: '2026-11-15', valor_liquidado: '1.250.000,50', observacion: '' };

    it('sin errores con los tres datos en orden', () => {
        expect(erroresLiquidar(formulario, presentado, hoy)).toEqual({});
        expect(erroresLiquidar({ ...formulario, fecha_liquidacion: '2026-10-31', valor_liquidado: '0' }, presentado, hoy)).toEqual({});
    });

    it('pide los tres datos', () => {
        expect(erroresLiquidar({ fecha_terminacion_real: '', fecha_liquidacion: '', valor_liquidado: '', observacion: '' }, presentado, hoy)).toEqual({
            fecha_terminacion_real: 'Escribe la fecha de terminación real.',
            fecha_liquidacion: 'Escribe la fecha de liquidación.',
            valor_liquidado: 'Escribe el valor liquidado.',
        });
    });

    it('las fechas no son posteriores a hoy; la de terminación no es anterior al inicio ni la de liquidación a la de terminación', () => {
        expect(erroresLiquidar({ ...formulario, fecha_terminacion_real: '2026-11-20', fecha_liquidacion: '2026-11-20' }, presentado, hoy)).toEqual({
            fecha_terminacion_real: 'La fecha de terminación no puede ser posterior a hoy.',
            fecha_liquidacion: 'La fecha de liquidación no puede ser posterior a hoy.',
        });
        expect(erroresLiquidar({ ...formulario, fecha_terminacion_real: '2026-01-01' }, presentado, hoy))
            .toEqual({ fecha_terminacion_real: 'La fecha de terminación no puede ser anterior a la de inicio.' });
        expect(erroresLiquidar({ ...formulario, fecha_liquidacion: '2026-10-30' }, presentado, hoy))
            .toEqual({ fecha_liquidacion: 'La fecha de liquidación no puede ser anterior a la de terminación.' });
    });

    it('el valor admite hasta 13 cifras enteras', () => {
        expect(erroresLiquidar({ ...formulario, valor_liquidado: '9.999.999.999.999,99' }, presentado, hoy)).toEqual({});
        expect(erroresLiquidar({ ...formulario, valor_liquidado: '10.000.000.000.000' }, presentado, hoy))
            .toEqual({ valor_liquidado: 'El valor liquidado admite hasta 13 cifras enteras y 2 decimales.' });
    });

    it('la observación admite hasta 2000 caracteres', () => {
        expect(erroresLiquidar({ ...formulario, observacion: 'a'.repeat(2001) }, presentado, hoy))
            .toEqual({ observacion: 'La observación admite hasta 2000 caracteres.' });
    });
});

describe('erroresCierre', () => {
    const error = (status, data) => ({ response: { status, data } });

    it('un 422 por campo: los del diálogo junto a su campo y los demás aparte', () => {
        expect(erroresCierre(error(422, { errors: { fecha_liquidacion: ['La fecha de liquidación no puede ser posterior a hoy.'], actualizado: ['Falta la versión.'] } }), CAMPOS_LIQUIDAR))
            .toEqual({ campos: { fecha_liquidacion: 'La fecha de liquidación no puede ser posterior a hoy.' }, general: 'Falta la versión.' });
    });

    it('un 422 por estado trae `mensaje`: va como error general', () => {
        expect(erroresCierre(error(422, { mensaje: 'El contrato está anulado.' }), CAMPOS_TERMINAR))
            .toEqual({ campos: {}, general: 'El contrato está anulado.' });
    });

    it('los campos del diálogo de terminar no incluyen los de liquidar', () => {
        expect(erroresCierre(error(422, { errors: { valor_liquidado: ['x'] } }), CAMPOS_TERMINAR)).toEqual({ campos: {}, general: 'x' });
    });

    it('otro error no es de validación: null', () => {
        expect(erroresCierre(error(409, { mensaje: 'Otra persona…' }), CAMPOS_TERMINAR)).toBeNull();
        expect(erroresCierre(error(500, {}), CAMPOS_TERMINAR)).toBeNull();
    });
});

describe('cambiosSinGuardar', () => {
    const anexos = [{ id: 1, descripcion: 'Póliza', nombre_archivo: 'p.pdf', ruta_archivo: 'anexos_contratos/p.pdf', fecha: '2026-01-05' }];
    const contrato = { ...presentado, anexos };
    const delContrato = anexosDelContrato(contrato);

    it('el formulario recién abierto no tiene cambios, del listado o de presentar', () => {
        expect(cambiosSinGuardar(formularioContrato(contrato), contrato, delContrato)).toBe(false);
        expect(cambiosSinGuardar(formularioContrato({ ...listado, anexos }), { ...listado, anexos }, delContrato)).toBe(false);
    });

    it('un campo escrito es un cambio; volver a lo guardado, no', () => {
        const formulario = formularioContrato(contrato);
        expect(cambiosSinGuardar({ ...formulario, objeto: 'Otro objeto' }, contrato, delContrato)).toBe(true);
        expect(cambiosSinGuardar({ ...formulario, objeto: 'Construcción del puente ' }, contrato, delContrato)).toBe(false);
        expect(cambiosSinGuardar({ ...formulario, valor_inicial: '1.500.000,5' }, contrato, delContrato)).toBe(false);
        expect(cambiosSinGuardar({ ...formulario, estado: 'suspendido' }, contrato, delContrato)).toBe(true);
    });

    it('el avance financiero que se recalcula solo con las actas no cuenta como cambio', () => {
        expect(cambiosSinGuardar({ ...formularioContrato(contrato), avance_financiero: 31.5 }, contrato, delContrato)).toBe(false);
    });

    it('lo copiado de SECOP II o una desvinculación pendiente son cambios', () => {
        expect(cambiosSinGuardar({ ...formularioContrato(contrato), secop_consultado: true }, contrato, delContrato)).toBe(true);
        expect(cambiosSinGuardar({ ...formularioContrato(contrato), secop_desvincular: true }, contrato, delContrato)).toBe(true);
    });

    it('un anexo subido que el contrato no tiene es un cambio; uno eliminado ya se guardó', () => {
        const nuevo = { id: 1700000000000, descripcion: 'Acta', nombreArchivo: 'a.pdf', rutaArchivo: 'anexos_contratos/a.pdf', fecha: '2026-11-19' };
        expect(cambiosSinGuardar(formularioContrato(contrato), contrato, [...delContrato, nuevo])).toBe(true);
        expect(cambiosSinGuardar(formularioContrato(contrato), contrato, [])).toBe(false);
    });

    it('sin contrato guardado, false', () => {
        expect(cambiosSinGuardar(contratoVacio(), null, [])).toBe(false);
    });
});

describe('contratoTrasCierre', () => {
    it('toma del contrato presentado el estado, los datos de cierre, su aviso y la versión; lo demás se conserva', () => {
        const abierto = { ...listado, anexos: [{ id: 1 }], modificaciones: [{ id: 2 }] };
        const respuesta = {
            ...presentado, estado: 'liquidado', fecha_terminacion_real: '2026-10-31', fecha_liquidacion: '2026-11-15',
            valor_liquidado: '1250000.50', aviso_cierre: null, updated_at: '2026-11-19T15:00:00-05:00', objeto: 'No se toma',
        };
        expect(contratoTrasCierre(abierto, respuesta)).toEqual({
            ...abierto, estado: 'liquidado', fecha_terminacion_real: '2026-10-31', fecha_liquidacion: '2026-11-15',
            valor_liquidado: '1250000.50', aviso_cierre: null, updated_at: '2026-11-19T15:00:00-05:00',
        });
    });
});

describe('avisoEstadoCierreSecop', () => {
    it('SECOP II propone terminado o liquidado y el contrato guardado no lo está', () => {
        expect(avisoEstadoCierreSecop('liquidado', 'en_ejecucion'))
            .toBe('SECOP II dice «Liquidado»: usa «Liquidar» para registrarlo con sus fechas y valor.');
        expect(avisoEstadoCierreSecop('terminado', 'en_ejecucion'))
            .toBe('SECOP II dice «Terminado»: usa «Terminar» para registrarlo con su fecha de terminación.');
    });

    it('un contrato liquidado al que SECOP II ve terminado: SECOP II va detrás, sin aviso', () => {
        expect(avisoEstadoCierreSecop('terminado', 'liquidado')).toBeNull();
    });

    it('null si el contrato ya está en ese estado, si SECOP II propone otro o si el contrato es nuevo', () => {
        expect(avisoEstadoCierreSecop('liquidado', 'liquidado')).toBeNull();
        expect(avisoEstadoCierreSecop('en_ejecucion', 'liquidado')).toBeNull();
        expect(avisoEstadoCierreSecop(undefined, 'en_ejecucion')).toBeNull();
        expect(avisoEstadoCierreSecop('liquidado', null, true)).toBeNull();
        expect(avisoEstadoCierreSecop('terminado', '', true)).toBeNull();
    });

    it('un contrato guardado sin estado también recibe el aviso: lo decide «es nuevo», no el estado vacío', () => {
        expect(avisoEstadoCierreSecop('liquidado', null, false))
            .toBe('SECOP II dice «Liquidado»: usa «Liquidar» para registrarlo con sus fechas y valor.');
        expect(avisoEstadoCierreSecop('terminado', '', false))
            .toBe('SECOP II dice «Terminado»: usa «Terminar» para registrarlo con su fecha de terminación.');
        expect(avisoEstadoCierreSecop('liquidado', null))
            .toBe('SECOP II dice «Liquidado»: usa «Liquidar» para registrarlo con sus fechas y valor.');
    });
});

describe('estado suspendido desde SECOP II', () => {
    it('no se aplica «Suspendido» a un contrato guardado que no lo está: se usa «Suspender»', () => {
        expect(estadoCierreBloqueado('suspendido', 'en_ejecucion')).toBe(true);
        expect(estadoCierreBloqueado('suspendido', 'celebrado', false)).toBe(true);
        expect(estadoCierreBloqueado('suspendido', null, false)).toBe(true);
        expect(avisoEstadoCierreSecop('suspendido', 'en_ejecucion'))
            .toBe('SECOP II dice «Suspendido»: usa «Suspender» para registrarlo con su fecha.');
        expect(avisoEstadoCierreSecop('suspendido', null))
            .toBe('SECOP II dice «Suspendido»: usa «Suspender» para registrarlo con su fecha.');
    });

    it('a un contrato terminado o liquidado SECOP II lo ve suspendido: va detrás, se bloquea sin aviso', () => {
        expect(estadoCierreBloqueado('suspendido', 'liquidado')).toBe(true);
        expect(avisoEstadoCierreSecop('suspendido', 'liquidado')).toBeNull();
        expect(avisoEstadoCierreSecop('suspendido', 'terminado')).toBeNull();
    });

    it('con una suspensión abierta, SECOP II no lo saca de suspendido: se usa «Reiniciar»', () => {
        expect(estadoCierreBloqueado('en_ejecucion', 'suspendido', false, true)).toBe(true);
        expect(estadoCierreBloqueado('celebrado', 'suspendido', false, true)).toBe(true);
        expect(estadoCierreBloqueado('terminado', 'suspendido', false, true)).toBe(true);
        expect(avisoEstadoCierreSecop('en_ejecucion', 'suspendido', false, true))
            .toBe('SECOP II dice «En ejecución»: usa «Reiniciar» para registrar el reinicio.');
        expect(avisoEstadoCierreSecop('liquidado', 'suspendido', false, true))
            .toBe('SECOP II dice «Liquidado»: usa «Reiniciar» para registrar el reinicio.');
    });

    it('sin suspensión abierta, un suspendido sí toma otro estado (salvo terminado y liquidado); el mismo estado, nada', () => {
        expect(estadoCierreBloqueado('en_ejecucion', 'suspendido')).toBe(false);
        expect(avisoEstadoCierreSecop('en_ejecucion', 'suspendido')).toBeNull();
        expect(estadoCierreBloqueado('suspendido', 'suspendido', false, true)).toBe(false);
        expect(avisoEstadoCierreSecop('suspendido', 'suspendido', false, true)).toBeNull();
        expect(estadoCierreBloqueado(undefined, 'suspendido', false, true)).toBe(false);
        expect(avisoEstadoCierreSecop(undefined, 'suspendido', false, true)).toBeNull();
    });

    it('un contrato nuevo toma «Suspendido» de SECOP II', () => {
        expect(estadoCierreBloqueado('suspendido', '', true)).toBe(false);
        expect(avisoEstadoCierreSecop('suspendido', '', true)).toBeNull();
    });
});

describe('estadoCierreBloqueado', () => {
    it('SECOP II no cambia el estado de un contrato guardado a terminado ni a liquidado', () => {
        expect(estadoCierreBloqueado('liquidado', 'en_ejecucion')).toBe(true);
        expect(estadoCierreBloqueado('terminado', 'en_ejecucion')).toBe(true);
        expect(estadoCierreBloqueado('terminado', 'liquidado')).toBe(true);
        expect(estadoCierreBloqueado('liquidado', 'terminado')).toBe(true);
    });

    it('sí lo deja si ya está en ese estado, si es otro estado o si el contrato es nuevo', () => {
        expect(estadoCierreBloqueado('liquidado', 'liquidado')).toBe(false);
        expect(estadoCierreBloqueado('en_ejecucion', 'liquidado')).toBe(false);
        expect(estadoCierreBloqueado(undefined, 'en_ejecucion')).toBe(false);
        expect(estadoCierreBloqueado('liquidado', null, true)).toBe(false);
        expect(estadoCierreBloqueado('terminado', '', true)).toBe(false);
    });

    it('un contrato guardado con estado null o vacío tampoco lo toma de SECOP II', () => {
        expect(estadoCierreBloqueado('liquidado', null, false)).toBe(true);
        expect(estadoCierreBloqueado('terminado', '', false)).toBe(true);
        expect(estadoCierreBloqueado('liquidado', undefined)).toBe(true);
        expect(estadoCierreBloqueado('en_ejecucion', null, false)).toBe(false);
    });
});

describe('muestraBloqueCierre', () => {
    it('terminado o liquidado, aunque no tenga datos', () => {
        expect(muestraBloqueCierre({ ...presentado, estado: 'terminado' })).toBe(true);
        expect(muestraBloqueCierre({ ...presentado, estado: 'liquidado', fecha_terminacion_real: null })).toBe(true);
    });

    it('en otro estado, solo si conserva algún dato de cierre (salió de terminado por el formulario)', () => {
        expect(muestraBloqueCierre(presentado)).toBe(false);
        expect(muestraBloqueCierre({ ...presentado, fecha_terminacion_real: null, fecha_liquidacion: null, valor_liquidado: null })).toBe(false);
        expect(muestraBloqueCierre({ ...presentado, estado: 'en_ejecucion', fecha_terminacion_real: '2026-10-31' })).toBe(true);
        expect(muestraBloqueCierre({ ...presentado, valor_liquidado: '0.00' })).toBe(true);
    });

    it('sin contrato, no', () => {
        expect(muestraBloqueCierre(null)).toBe(false);
    });
});


// ---------- Suspensión y reinicio del contrato (fase 7B) ----------

// Un contrato suspendido con su suspensión abierta, como lo devuelven presentar y los listados.
const avisoAbierto = { desde: '2026-11-05', dias: 14, reinicio_previsto: null, vencido: false };
const suspendido = { ...presentado, estado: 'suspendido', aviso_suspension: avisoAbierto };
// Filas de la pestaña Modificaciones (detalle y GET /listarContratos).
const filaSuspension = (datos = {}) => ({
    id: 30, contrato_id: 7, numero_otrosi: 'Acta 2', tipo: 'suspension', valor_adicion: null, dias_prorroga: null,
    fecha_modificacion: '2026-11-05', fecha_reinicio: null, fecha_reinicio_prevista: null, justificacion: 'Lluvias', ...datos,
});

describe('suspensionAbierta', () => {
    it('la del aviso de la API: desde cuándo y el reinicio previsto', () => {
        expect(suspensionAbierta(suspendido)).toEqual({ desde: '2026-11-05', reinicio_previsto: null });
        expect(suspensionAbierta({ ...suspendido, aviso_suspension: { ...avisoAbierto, reinicio_previsto: '2026-11-10' } }))
            .toEqual({ desde: '2026-11-05', reinicio_previsto: '2026-11-10' });
    });

    it('sin la clave del aviso, la fila abierta de sus modificaciones (con las fechas sin hora)', () => {
        const contrato = {
            ...presentado, estado: 'suspendido',
            modificaciones: [{ id: 1, tipo: 'prorroga', dias_prorroga: 10 }, filaSuspension({ fecha_modificacion: '2026-11-05 00:00:00', fecha_reinicio_prevista: '2026-12-01' })],
        };
        expect(suspensionAbierta(contrato)).toEqual({ desde: '2026-11-05', reinicio_previsto: '2026-12-01' });
    });

    it('sin suspensión abierta (cerradas o ninguna), null', () => {
        expect(suspensionAbierta(presentado)).toBeNull();
        expect(suspensionAbierta({ ...presentado, modificaciones: [filaSuspension({ fecha_reinicio: '2026-11-10', dias_prorroga: 5 })] })).toBeNull();
        expect(suspensionAbierta({ ...presentado, estado: 'suspendido', aviso_suspension: null })).toBeNull();
        expect(suspensionAbierta(null)).toBeNull();
    });

    it('con la clave del aviso (aunque sea null), manda el aviso y no las modificaciones, que pueden ir atrasadas', () => {
        // Tras reiniciar sin poder recargar: la fila sigue abierta en local, pero el aviso ya es null.
        expect(suspensionAbierta({ ...presentado, estado: 'en_ejecucion', aviso_suspension: null, modificaciones: [filaSuspension()] })).toBeNull();
        // Tras suspender sin poder recargar: la fila nueva aún no está, el aviso sí.
        expect(suspensionAbierta({ ...suspendido, modificaciones: [] })).toEqual({ desde: '2026-11-05', reinicio_previsto: null });
        // Una clave undefined cuenta como sin aviso: se miran las modificaciones.
        expect(suspensionAbierta({ ...presentado, aviso_suspension: undefined, modificaciones: [filaSuspension()] }))
            .toEqual({ desde: '2026-11-05', reinicio_previsto: null });
    });
});

describe('accionesSuspension', () => {
    it('un contrato guardado en ejecución se suspende; uno suspendido se reinicia', () => {
        expect(accionesSuspension(presentado)).toEqual({ suspender: true, reiniciar: false });
        expect(accionesSuspension(suspendido)).toEqual({ suspender: false, reiniciar: true });
    });

    it('un suspendido sin suspensión abierta (p. ej. de SECOP II) también se reinicia', () => {
        expect(accionesSuspension({ ...presentado, estado: 'suspendido' })).toEqual({ suspender: false, reiniciar: true });
    });

    it('en ejecución con una suspensión abierta no se suspende otra vez', () => {
        expect(accionesSuspension({ ...presentado, modificaciones: [filaSuspension()] })).toEqual({ suspender: false, reiniciar: false });
    });

    it('en otros estados, o sin contrato guardado, ninguna', () => {
        ['celebrado', 'terminado', 'liquidado', 'anulado', null, 'Vigente'].forEach((estado) => {
            expect(accionesSuspension({ ...presentado, estado })).toEqual({ suspender: false, reiniciar: false });
        });
        expect(accionesSuspension({ ...presentado, id: undefined })).toEqual({ suspender: false, reiniciar: false });
        expect(accionesSuspension(null)).toEqual({ suspender: false, reiniciar: false });
    });
});

describe('cierre con una suspensión abierta', () => {
    it('no se termina ni se liquida: primero se reinicia, con su nota', () => {
        expect(accionesCierre(suspendido)).toMatchObject({ terminar: false, liquidar: false });
        expect(notaSuspensionCierre(suspendido)).toBe('Reinicia el contrato antes de terminarlo o liquidarlo.');
    });

    it('un suspendido sin suspensión abierta sigue como en la fase 7A, sin nota', () => {
        expect(accionesCierre({ ...presentado, estado: 'suspendido' })).toMatchObject({ terminar: true, liquidar: true });
        expect(notaSuspensionCierre({ ...presentado, estado: 'suspendido' })).toBeNull();
        expect(notaSuspensionCierre(presentado)).toBeNull();
        expect(notaSuspensionCierre(null)).toBeNull();
    });
});

describe('opcionesEstadoContrato con una suspensión abierta', () => {
    const deshabilitados = (opciones) => opciones.filter((o) => o.deshabilitado).map((o) => o.valor);

    it('solo queda suspendido: para salir se usa «Reiniciar»', () => {
        const opciones = opcionesEstadoContrato('suspendido', false, true);
        expect(deshabilitados(opciones)).toEqual(['celebrado', 'en_ejecucion', 'terminado', 'liquidado', 'anulado']);
        expect(opciones.find((o) => o.valor === 'en_ejecucion').etiqueta).toBe('En ejecución (usa «Reiniciar»)');
        expect(opciones.find((o) => o.valor === 'celebrado').etiqueta).toBe('Celebrado (reinicia primero)');
        expect(opciones.find((o) => o.valor === 'anulado').etiqueta).toBe('Anulado (reinicia primero)');
        expect(opciones.find((o) => o.valor === 'terminado').etiqueta).toBe('Terminado (reinicia primero)');
        expect(opciones.find((o) => o.valor === 'liquidado').etiqueta).toBe('Liquidado (reinicia primero)');
        expect(opciones.find((o) => o.valor === 'suspendido')).toEqual({ valor: 'suspendido', etiqueta: 'Suspendido', deshabilitado: false });
    });

    it('un contrato nuevo no tiene suspensiones: cualquier estado', () => {
        expect(deshabilitados(opcionesEstadoContrato('', true, false))).toEqual([]);
    });
});

describe('textos del aviso de suspensión', () => {
    it('desde cuándo y cuántos días lleva suspendido', () => {
        expect(textoAvisoSuspension(avisoAbierto)).toBe('Suspendido desde 05/11/2026 (14 días)');
        expect(textoAvisoSuspension({ ...avisoAbierto, dias: 1 })).toBe('Suspendido desde 05/11/2026 (1 día)');
        expect(textoAvisoSuspension({ ...avisoAbierto, dias: 0 })).toBe('Suspendido desde 05/11/2026 (0 días)');
        expect(textoAvisoSuspension(null)).toBeNull();
        expect(textoAvisoSuspension(undefined)).toBeNull();
    });

    it('el chip solo si el reinicio previsto ya pasó', () => {
        expect(chipAvisoSuspension({ ...avisoAbierto, reinicio_previsto: '2026-11-10', vencido: true })).toBe('Reinicio vencido');
        expect(chipAvisoSuspension({ ...avisoAbierto, reinicio_previsto: '2026-11-30', vencido: false })).toBeNull();
        expect(chipAvisoSuspension(avisoAbierto)).toBeNull();
        expect(chipAvisoSuspension(null)).toBeNull();
    });

    it('el reinicio previsto, con «vencido» si pasó; sin previsto, null', () => {
        expect(textoReinicioPrevisto({ ...avisoAbierto, reinicio_previsto: '2026-11-10', vencido: true })).toBe('Reinicio previsto el 10/11/2026: vencido');
        expect(textoReinicioPrevisto({ ...avisoAbierto, reinicio_previsto: '2026-11-30', vencido: false })).toBe('Reinicio previsto el 30/11/2026');
        expect(textoReinicioPrevisto(avisoAbierto)).toBeNull();
        expect(textoReinicioPrevisto(null)).toBeNull();
    });
});

describe('días calendario', () => {
    it('diasEntre cuenta días exactos entre fechas año-mes-día, sin zona horaria', () => {
        expect(diasEntre('2026-11-05', '2026-11-19')).toBe(14);
        expect(diasEntre('2026-11-05', '2026-11-05')).toBe(0);
        // Cruza el fin de año, un 29 de febrero y meses de distinto largo.
        expect(diasEntre('2026-12-25', '2027-01-05')).toBe(11);
        expect(diasEntre('2028-02-28', '2028-03-01')).toBe(2);
        expect(diasEntre('2026-03-01', '2026-11-01')).toBe(245);
        expect(diasEntre('2026-11-19', '2026-11-05')).toBe(-14);
        expect(diasEntre('2026-11-05 00:00:00', '2026-11-06')).toBe(1);
    });

    it('diasEntre sin una fecha válida, null', () => {
        expect(diasEntre('', '2026-11-05')).toBeNull();
        expect(diasEntre('2026-11-05', null)).toBeNull();
        expect(diasEntre('05/11/2026', '2026-11-06')).toBeNull();
    });

    it('sumarDias corre la fecha los días dados', () => {
        expect(sumarDias('2026-06-30', 14)).toBe('2026-07-14');
        expect(sumarDias('2026-12-20', 15)).toBe('2027-01-04');
        expect(sumarDias('2028-02-28', 1)).toBe('2028-02-29');
        expect(sumarDias('2026-06-30', 0)).toBe('2026-06-30');
        expect(sumarDias('2026-06-30 00:00:00', 1)).toBe('2026-07-01');
        expect(sumarDias('', 3)).toBeNull();
    });
});

describe('formularios y datos de suspender y reiniciar', () => {
    const hoy = '2026-11-19';

    it('los campos de cada diálogo, con los nombres de la API', () => {
        expect(CAMPOS_SUSPENDER).toEqual(['fecha_suspension', 'fecha_reinicio_prevista', 'numero_acta', 'motivo']);
        expect(CAMPOS_REINICIAR).toEqual(['fecha_suspension', 'fecha_reinicio', 'observacion']);
    });

    it('los formularios empiezan con la fecha de hoy', () => {
        expect(formularioSuspender(presentado, hoy)).toEqual({ fecha_suspension: hoy, fecha_reinicio_prevista: '', numero_acta: '', motivo: '' });
        expect(formularioReiniciar(suspendido, hoy)).toEqual({ fecha_suspension: '', fecha_reinicio: hoy, observacion: '' });
    });

    it('lo que espera POST /contratos/{id}/suspender: vacíos como null, textos recortados y la versión', () => {
        expect(datosSuspender({ fecha_suspension: '2026-11-05', fecha_reinicio_prevista: '', numero_acta: '  Acta 2 ', motivo: ' ' }, presentado))
            .toEqual({ fecha_suspension: '2026-11-05', fecha_reinicio_prevista: null, numero_acta: 'Acta 2', motivo: null, actualizado: '2026-11-18 09:00:00' });
        expect(datosSuspender({ fecha_suspension: '2026-11-05', fecha_reinicio_prevista: '2026-12-01', numero_acta: '', motivo: 'Lluvias' }, { ...presentado, updated_at: null }))
            .toEqual({ fecha_suspension: '2026-11-05', fecha_reinicio_prevista: '2026-12-01', numero_acta: null, motivo: 'Lluvias', actualizado: null });
    });

    it('lo que espera POST /contratos/{id}/reiniciar: la fecha de suspensión solo si no hay una abierta', () => {
        const formulario = { fecha_suspension: '2026-10-01', fecha_reinicio: '2026-11-19', observacion: ' Acta de reinicio ' };
        expect(datosReiniciar(formulario, suspendido))
            .toEqual({ fecha_reinicio: '2026-11-19', observacion: 'Acta de reinicio', actualizado: '2026-11-18 09:00:00' });
        expect(datosReiniciar({ ...formulario, observacion: '' }, { ...suspendido, aviso_suspension: null }))
            .toEqual({ fecha_reinicio: '2026-11-19', fecha_suspension: '2026-10-01', observacion: null, actualizado: '2026-11-18 09:00:00' });
    });
});

describe('erroresSuspender', () => {
    const hoy = '2026-11-19';
    const formulario = { fecha_suspension: '2026-11-05', fecha_reinicio_prevista: '', numero_acta: '', motivo: '' };

    it('sin errores con una fecha entre el inicio y hoy', () => {
        expect(erroresSuspender(formulario, presentado, hoy)).toEqual({});
        expect(erroresSuspender({ ...formulario, fecha_suspension: hoy, fecha_reinicio_prevista: hoy }, presentado, hoy)).toEqual({});
    });

    it('la fecha de suspensión: obligatoria, no futura, no anterior al inicio ni al último reinicio', () => {
        expect(erroresSuspender({ ...formulario, fecha_suspension: '' }, presentado, hoy)).toEqual({ fecha_suspension: 'Escribe la fecha de suspensión.' });
        expect(erroresSuspender({ ...formulario, fecha_suspension: '2026-11-20' }, presentado, hoy))
            .toEqual({ fecha_suspension: 'La fecha de suspensión no puede ser posterior a hoy.' });
        expect(erroresSuspender({ ...formulario, fecha_suspension: '2026-01-09' }, presentado, hoy))
            .toEqual({ fecha_suspension: 'La fecha de suspensión no puede ser anterior a la de inicio.' });
        const conCerrada = { ...presentado, modificaciones: [filaSuspension({ fecha_modificacion: '2026-10-01', fecha_reinicio: '2026-11-10 00:00:00', dias_prorroga: 40 })] };
        expect(erroresSuspender(formulario, conCerrada, hoy))
            .toEqual({ fecha_suspension: 'La fecha de suspensión no puede ser anterior al último reinicio.' });
        expect(erroresSuspender({ ...formulario, fecha_suspension: '2026-11-10' }, conCerrada, hoy)).toEqual({});
    });

    it('la prevista no es anterior a la de suspensión; el acta y el motivo tienen largo máximo', () => {
        expect(erroresSuspender({ ...formulario, fecha_reinicio_prevista: '2026-11-04' }, presentado, hoy))
            .toEqual({ fecha_reinicio_prevista: 'La fecha prevista de reinicio no puede ser anterior a la de suspensión.' });
        // La prevista puede ser futura.
        expect(erroresSuspender({ ...formulario, fecha_reinicio_prevista: '2027-01-15' }, presentado, hoy)).toEqual({});
        expect(erroresSuspender({ ...formulario, numero_acta: 'x'.repeat(51), motivo: 'x'.repeat(2001) }, presentado, hoy)).toEqual({
            numero_acta: 'El número del acta admite hasta 50 caracteres.',
            motivo: 'El motivo admite hasta 2000 caracteres.',
        });
        expect(erroresSuspender({ ...formulario, numero_acta: 'x'.repeat(50) }, presentado, hoy)).toEqual({});
    });
});

describe('erroresReiniciar', () => {
    const hoy = '2026-11-19';
    const formulario = { fecha_suspension: '', fecha_reinicio: '2026-11-19', observacion: '' };

    it('con la suspensión abierta, solo la fecha de reinicio: obligatoria, no futura, no anterior a la suspensión', () => {
        expect(erroresReiniciar(formulario, suspendido, hoy)).toEqual({});
        expect(erroresReiniciar({ ...formulario, fecha_reinicio: '2026-11-05' }, suspendido, hoy)).toEqual({});
        expect(erroresReiniciar({ ...formulario, fecha_reinicio: '' }, suspendido, hoy)).toEqual({ fecha_reinicio: 'Escribe la fecha de reinicio.' });
        expect(erroresReiniciar({ ...formulario, fecha_reinicio: '2026-11-20' }, suspendido, hoy))
            .toEqual({ fecha_reinicio: 'La fecha de reinicio no puede ser posterior a hoy.' });
        expect(erroresReiniciar({ ...formulario, fecha_reinicio: '2026-11-04' }, suspendido, hoy))
            .toEqual({ fecha_reinicio: 'La fecha de reinicio no puede ser anterior a la de suspensión.' });
    });

    it('sin suspensión abierta pide la fecha de suspensión, con las reglas de suspender', () => {
        const sinAbierta = { ...presentado, estado: 'suspendido', aviso_suspension: null };
        expect(erroresReiniciar(formulario, sinAbierta, hoy)).toEqual({ fecha_suspension: 'Escribe la fecha de suspensión.' });
        expect(erroresReiniciar({ ...formulario, fecha_suspension: '2026-01-01' }, sinAbierta, hoy))
            .toEqual({ fecha_suspension: 'La fecha de suspensión no puede ser anterior a la de inicio.' });
        expect(erroresReiniciar({ ...formulario, fecha_suspension: '2026-10-01', fecha_reinicio: '2026-09-30' }, sinAbierta, hoy))
            .toEqual({ fecha_reinicio: 'La fecha de reinicio no puede ser anterior a la de suspensión.' });
        expect(erroresReiniciar({ ...formulario, fecha_suspension: '2026-10-01' }, sinAbierta, hoy)).toEqual({});
    });

    it('con la suspensión abierta, la fecha de suspensión escrita no cuenta', () => {
        expect(erroresReiniciar({ ...formulario, fecha_suspension: '2030-01-01' }, suspendido, hoy)).toEqual({});
    });

    it('la observación tiene largo máximo', () => {
        expect(erroresReiniciar({ ...formulario, observacion: 'x'.repeat(2001) }, suspendido, hoy))
            .toEqual({ observacion: 'La observación admite hasta 2000 caracteres.' });
    });
});

describe('vista previa del reinicio', () => {
    it('los días suspendidos y la fecha fin corrida', () => {
        const vista = vistaPreviaReinicio({ fecha_reinicio: '2026-11-19' }, suspendido);
        expect(vista).toEqual({ dias: 14, fechaFinActual: '2026-06-30', fechaFinNueva: '2026-07-14' });
        expect(textoVistaPreviaReinicio(vista)).toBe('Se suman 14 días: la fecha fin pasa de 30/06/2026 a 14/07/2026.');
    });

    it('sin suspensión abierta usa la fecha de suspensión escrita', () => {
        const sinAbierta = { ...presentado, estado: 'suspendido', aviso_suspension: null, fecha_fin: '2026-12-31 00:00:00' };
        expect(vistaPreviaReinicio({ fecha_suspension: '2026-11-18', fecha_reinicio: '2026-11-19' }, sinAbierta))
            .toEqual({ dias: 1, fechaFinActual: '2026-12-31', fechaFinNueva: '2027-01-01' });
        expect(textoVistaPreviaReinicio({ dias: 1, fechaFinActual: '2026-12-31', fechaFinNueva: '2027-01-01' }))
            .toBe('Se suma 1 día: la fecha fin pasa de 31/12/2026 a 01/01/2027.');
    });

    it('el mismo día no suma días', () => {
        const vista = vistaPreviaReinicio({ fecha_reinicio: '2026-11-05' }, suspendido);
        expect(vista).toEqual({ dias: 0, fechaFinActual: '2026-06-30', fechaFinNueva: '2026-06-30' });
        expect(textoVistaPreviaReinicio(vista)).toBe('No se suman días: la fecha fin no cambia.');
    });

    it('sin fecha fin, solo los días', () => {
        const vista = vistaPreviaReinicio({ fecha_reinicio: '2026-11-19' }, { ...suspendido, fecha_fin: null });
        expect(vista).toEqual({ dias: 14, fechaFinActual: null, fechaFinNueva: null });
        expect(textoVistaPreviaReinicio(vista)).toBe('Se suman 14 días a la fecha fin del contrato.');
    });

    it('sin fechas completas o con el reinicio antes de la suspensión, null', () => {
        expect(vistaPreviaReinicio({ fecha_reinicio: '' }, suspendido)).toBeNull();
        expect(vistaPreviaReinicio({ fecha_reinicio: '2026-11-04' }, suspendido)).toBeNull();
        expect(vistaPreviaReinicio({ fecha_suspension: '', fecha_reinicio: '2026-11-19' }, { ...suspendido, aviso_suspension: null })).toBeNull();
        expect(textoVistaPreviaReinicio(null)).toBeNull();
    });
});

describe('contratoTrasSuspension', () => {
    it('toma del contrato presentado el estado, la fecha fin, los avisos y la versión; lo demás se conserva', () => {
        const abierto = { ...listado, anexos: [{ id: 1 }], modificaciones: [{ id: 2 }] };
        const respuesta = {
            ...presentado, estado: 'en_ejecucion', fecha_fin: '2026-07-14', aviso_suspension: null, aviso_cierre: null,
            updated_at: '2026-11-19T15:00:00-05:00', objeto: 'No se toma',
        };
        expect(contratoTrasSuspension(abierto, respuesta)).toEqual({
            ...abierto, estado: 'en_ejecucion', fecha_fin: '2026-07-14', aviso_suspension: null, aviso_cierre: null, updated_at: '2026-11-19T15:00:00-05:00',
        });
        expect(contratoTrasSuspension(abierto, { ...respuesta, aviso_suspension: undefined, aviso_cierre: undefined }))
            .toMatchObject({ aviso_suspension: null, aviso_cierre: null });
    });

    it('al reiniciar, cierra en local la suspensión abierta con la fecha de reinicio y sus días', () => {
        const abierto = { ...suspendido, modificaciones: [{ id: 2, tipo: 'prorroga', dias_prorroga: 10 }, filaSuspension({ fecha_modificacion: '2026-11-05 00:00:00' })] };
        const respuesta = { ...presentado, estado: 'en_ejecucion', fecha_fin: '2026-07-14', aviso_suspension: null, updated_at: 'v2' };
        const tras = contratoTrasSuspension(abierto, respuesta, { fecha_reinicio: '2026-11-19' });
        expect(tras.modificaciones).toEqual([
            { id: 2, tipo: 'prorroga', dias_prorroga: 10 },
            filaSuspension({ fecha_modificacion: '2026-11-05 00:00:00', fecha_reinicio: '2026-11-19', dias_prorroga: 14 }),
        ]);
        expect(suspensionAbierta(tras)).toBeNull();
        expect(suspensionAbierta({ ...tras, aviso_suspension: undefined })).toBeNull();
    });

    it('al suspender (o sin fecha de reinicio), las modificaciones no cambian y el aviso dice que está abierta', () => {
        const abierto = { ...presentado, modificaciones: [{ id: 2, tipo: 'prorroga' }] };
        const tras = contratoTrasSuspension(abierto, { ...suspendido, updated_at: 'v2' });
        expect(tras.modificaciones).toEqual([{ id: 2, tipo: 'prorroga' }]);
        expect(suspensionAbierta(tras)).toEqual({ desde: '2026-11-05', reinicio_previsto: null });
        // Sin modificaciones en el contrato abierto, no se agregan.
        expect(contratoTrasSuspension(listado, { ...presentado, estado: 'en_ejecucion' }, { fecha_reinicio: '2026-11-19' })).not.toHaveProperty('modificaciones');
    });
});

describe('suspensiones en la pestaña Modificaciones', () => {
    it('el formulario no ofrece el tipo suspensión; la tabla sí lo nombra', () => {
        expect(TIPOS_MODIFICACION.map(([valor]) => valor)).toEqual(['adicion', 'prorroga', 'adicion_prorroga']);
        expect(etiquetaTipoModificacion('suspension')).toBe('Suspensión');
    });

    it('el detalle: sus fechas y días, o «abierta» con el reinicio previsto', () => {
        expect(detalleSuspension(filaSuspension())).toBe('Desde el 05/11/2026: abierta');
        expect(detalleSuspension(filaSuspension({ fecha_reinicio_prevista: '2026-12-01' })))
            .toBe('Desde el 05/11/2026: abierta, reinicio previsto el 01/12/2026');
        expect(detalleSuspension(filaSuspension({ fecha_reinicio: '2026-11-19', dias_prorroga: 14 }))).toBe('Del 05/11/2026 al 19/11/2026: 14 días');
        expect(detalleSuspension(filaSuspension({ fecha_reinicio: '2026-11-06 00:00:00', dias_prorroga: 1 }))).toBe('Del 05/11/2026 al 06/11/2026: 1 día');
        expect(detalleSuspension({ tipo: 'prorroga', dias_prorroga: 10 })).toBeNull();
    });

    it('la columna de días: «Abierta», los días de la suspensión (también 0) o los de la prórroga', () => {
        expect(textoDiasModificacion(filaSuspension())).toBe('Abierta');
        expect(textoDiasModificacion(filaSuspension({ fecha_reinicio: '2026-11-05', dias_prorroga: 0 }))).toBe('0');
        expect(textoDiasModificacion(filaSuspension({ fecha_reinicio: '2026-11-19', dias_prorroga: 14 }))).toBe('14');
        expect(textoDiasModificacion({ tipo: 'prorroga', dias_prorroga: 30 })).toBe('30');
        expect(textoDiasModificacion({ tipo: 'adicion', dias_prorroga: null })).toBe('-');
    });

    it('eliminar una suspensión manda la versión del contrato; una adición o prórroga, solo el id', () => {
        expect(datosEliminarModificacion(filaSuspension(), { ...suspendido, updated_at: '2026-11-19 10:00:00' }))
            .toEqual({ id: 30, actualizado: '2026-11-19 10:00:00' });
        expect(datosEliminarModificacion(filaSuspension(), { ...suspendido, updated_at: undefined })).toEqual({ id: 30, actualizado: null });
        expect(datosEliminarModificacion({ id: 4, tipo: 'prorroga' }, { ...presentado, updated_at: 'v' })).toEqual({ id: 4 });
    });

    it('solo la última suspensión (por fecha y luego por id, como el servidor) se puede eliminar; las demás modificaciones, siempre', () => {
        const primera = filaSuspension({ id: 30, fecha_modificacion: '2026-10-01', fecha_reinicio: '2026-10-11', dias_prorroga: 10 });
        const ultima = filaSuspension({ id: 31, fecha_modificacion: '2026-11-01 00:00:00' });
        const prorroga = { id: 32, tipo: 'prorroga', fecha_modificacion: '2026-11-10' };
        const lista = [ultima, prorroga, primera];
        expect(puedeEliminarModificacion(primera, lista)).toBe(false);
        expect(puedeEliminarModificacion(ultima, lista)).toBe(true);
        expect(puedeEliminarModificacion(prorroga, lista)).toBe(true);
        // El mismo día, la de id mayor.
        const otra = filaSuspension({ id: 33, fecha_modificacion: '2026-11-01' });
        expect(puedeEliminarModificacion(ultima, [...lista, otra])).toBe(false);
        expect(puedeEliminarModificacion(otra, [...lista, otra])).toBe(true);
        expect(puedeEliminarModificacion(ultima, null)).toBe(true);
    });

    it('al eliminar, la confirmación explica la regla de la última y qué pasa con el contrato', () => {
        expect(confirmacionEliminarModificacion(filaSuspension())).toEqual({
            titulo: '¿Eliminar esta suspensión?',
            texto: 'Solo se puede eliminar la última suspensión. Como está abierta, el contrato vuelve a «En ejecución». El cambio queda en el historial del contrato.',
        });
        expect(confirmacionEliminarModificacion(filaSuspension({ fecha_reinicio: '2026-11-19', dias_prorroga: 14 }))).toEqual({
            titulo: '¿Eliminar esta suspensión?',
            texto: 'Solo se puede eliminar la última suspensión. La fecha fin se recalcula sin sus días. El cambio queda en el historial del contrato.',
        });
        expect(confirmacionEliminarModificacion({ tipo: 'adicion' })).toEqual({
            titulo: '¿Eliminar esta modificación?', texto: 'Se recalculará el valor y la fecha fin del contrato.',
        });
    });
});
