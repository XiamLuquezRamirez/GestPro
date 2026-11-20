import { describe, it, expect } from 'vitest';
import { contratoVacio, datosContrato, datosSecop, formularioContrato } from './contratos';
import {
    CAMPOS_DESDE_SECOP,
    aplicarDiferencias,
    avisosSecop,
    camposCambiados,
    camposMarcados,
    desvincularSecop,
    diferenciasSecop,
    filaResultadoSecop,
    formularioTieneDatos,
    importarSecop,
    mensajeSecop,
    referenciaDesdeSecop,
    referenciaSecop,
    valoresDesdeSecop,
    vincularSecop,
} from './secop';

// Un elemento de `resultados` de GET /secop/contratos, como lo arma el servidor.
const resultado = {
    contrato: {
        n_contrato: 'CO-045-2026',
        objeto: 'Construcción del puente',
        contratante: 'Alcaldía de Becerril',
        contratante_nit: '800123456',
        contratista: 'Constructora S.A.S.',
        contratista_identificacion: '900555111',
        valor_inicial: '40000000.00',
        fecha_suscripcion: '2026-01-05',
        fecha_inicio: '2026-01-10',
        fecha_fin_inicial: '2026-06-30',
        tipo_contrato_id: 2,
        estado: 'en_ejecucion',
        secop_id_contrato: 'CO1.PCCNTR.123456',
        secop_proceso: 'CO1.BDOS.987',
        url_secop: 'https://community.secop.gov.co/Public/Tendering/ContractNoticePhases/View?PPI=CO1.PPI.1',
    },
    secop: {
        tipo_contrato_secop: 'Obra', modalidad_secop: 'Licitación pública', estado_secop: 'En ejecución',
        dias_adicionados: 0, valor_pagado: '0.00', valor_facturado: null,
    },
    registrado: null,
};

const tipos = [{ id: 2, nombre: 'Obra', activo: true }, { id: 3, nombre: 'Suministro', activo: true }];

// El contrato abierto (presentar), con la referencia de SECOP II guardada.
const guardado = {
    id: 7,
    proyecto: 3,
    n_contrato: 'CO-045-2026',
    objeto: 'Construcción del puente',
    contratante: 'Alcaldía de Becerril',
    contratante_nit: '800123456',
    contratista: 'Constructora S.A.S.',
    contratista_identificacion: '900555111',
    tipo_contrato_id: 2,
    tipo_contrato: { id: 2, nombre: 'Obra', activo: true },
    valor_inicial: '40000000.00',
    valor: '40000000.00',
    fecha_suscripcion: '2026-01-05',
    fecha_inicio: '2026-01-10',
    fecha_fin_inicial: '2026-06-30',
    fecha_fin: '2026-06-30',
    estado: 'en_ejecucion',
    anticipo: false,
    secop_id_contrato: 'CO1.PCCNTR.123456',
    secop_proceso: 'CO1.BDOS.987',
    url_secop: resultado.contrato.url_secop,
    secop_consultado_at: '2026-11-01 10:00:00',
    updated_at: '2026-11-02T10:00:00.000000Z',
};

// Intl separa el símbolo con un espacio de no separación.
const plano = (texto) => texto.replace(/ /g, ' ');
const planos = (diferencias) => diferencias.map((d) => ({ ...d, actual: plano(d.actual), secop: plano(d.secop) }));

const conContrato = (cambios) => ({ ...resultado, contrato: { ...resultado.contrato, ...cambios } });

describe('valoresDesdeSecop', () => {
    it('pasa cada campo al formato del formulario', () => {
        expect(valoresDesdeSecop(resultado.contrato)).toEqual({
            tipo_contrato_id: '2',
            n_contrato: 'CO-045-2026',
            estado: 'en_ejecucion',
            objeto: 'Construcción del puente',
            contratante: 'Alcaldía de Becerril',
            contratante_nit: '800123456',
            contratista: 'Constructora S.A.S.',
            contratista_identificacion: '900555111',
            valor_inicial: '40.000.000',
            fecha_suscripcion: '2026-01-05',
            fecha_inicio: '2026-01-10',
            fecha_fin_inicial: '2026-06-30',
        });
    });

    it('da los mismos valores que formularioContrato con los mismos datos', () => {
        const formulario = formularioContrato({ ...resultado.contrato, valor_inicial: '1500000.50' });
        const valores = valoresDesdeSecop({ ...resultado.contrato, valor_inicial: '1500000.50' });
        Object.entries(valores).forEach(([campo, valor]) => expect(valor, campo).toBe(formulario[campo]));
    });

    it('omite los nulos, los vacíos y un estado que no es de la lista', () => {
        const valores = valoresDesdeSecop({ ...resultado.contrato, estado: null, objeto: '  ', tipo_contrato_id: null, fecha_inicio: null });
        expect(valores).not.toHaveProperty('estado');
        expect(valores).not.toHaveProperty('objeto');
        expect(valores).not.toHaveProperty('tipo_contrato_id');
        expect(valores).not.toHaveProperty('fecha_inicio');
        expect(valoresDesdeSecop({ estado: 'vigente' })).toEqual({});
        expect(valoresDesdeSecop(null)).toEqual({});
    });

    it('deja solo el día de una fecha con hora y quita espacios', () => {
        expect(valoresDesdeSecop({ fecha_inicio: '2026-01-10T00:00:00.000', n_contrato: ' CO-1 ' })).toEqual({ fecha_inicio: '2026-01-10', n_contrato: 'CO-1' });
    });
});

describe('referenciaDesdeSecop', () => {
    it('trae los tres campos y marca la consulta', () => {
        expect(referenciaDesdeSecop(resultado.contrato)).toEqual({
            secop_id_contrato: 'CO1.PCCNTR.123456',
            secop_proceso: 'CO1.BDOS.987',
            url_secop: resultado.contrato.url_secop,
            secop_consultado: true,
        });
    });

    it('deja vacíos los que no vienen y descarta un enlace que no es web', () => {
        expect(referenciaDesdeSecop({ secop_id_contrato: 'CO1.PCCNTR.1', secop_proceso: null, url_secop: 'javascript:alert(1)' })).toEqual({
            secop_id_contrato: 'CO1.PCCNTR.1', secop_proceso: '', url_secop: '', secop_consultado: true,
        });
    });
});

describe('importarSecop y vincularSecop', () => {
    it('un contrato nuevo toma todo lo de SECOP II, vacía lo que no trae y conserva lo demás', () => {
        const formulario = { ...contratoVacio(3), supervisor: 'Ana', estado: 'celebrado', objeto: 'Escrito a mano' };
        const importado = importarSecop(formulario, conContrato({ estado: null, objeto: null }));
        expect(importado.proyecto).toBe('3');
        expect(importado.supervisor).toBe('Ana');
        // SECOP II no tradujo el estado ni trae el objeto: quedan vacíos, para elegirlos.
        expect(importado.estado).toBe('');
        expect(importado.objeto).toBe('');
        expect(importado.n_contrato).toBe('CO-045-2026');
        expect(importado.valor_inicial).toBe('40.000.000');
        expect(importado.secop_id_contrato).toBe('CO1.PCCNTR.123456');
        expect(importado.secop_consultado).toBe(true);
    });

    it('otro resultado reemplaza la referencia del anterior', () => {
        const primero = importarSecop(contratoVacio(), resultado);
        const segundo = importarSecop(primero, conContrato({ secop_id_contrato: 'CO1.PCCNTR.9', secop_proceso: null, url_secop: null }));
        expect(segundo.secop_id_contrato).toBe('CO1.PCCNTR.9');
        expect(segundo.secop_proceso).toBe('');
        expect(segundo.url_secop).toBe('');
    });

    it('otro resultado no mezcla los datos de los dos contratos', () => {
        const primero = importarSecop({ ...contratoVacio(3), supervisor: 'Ana' }, resultado);
        const otro = {
            ...resultado,
            contrato: {
                n_contrato: 'CO-099-2026', objeto: 'Suministro de tubería', secop_id_contrato: 'CO1.PCCNTR.9',
                contratante: null, contratante_nit: null, contratista: null, contratista_identificacion: null, valor_inicial: null,
                fecha_suscripcion: null, fecha_inicio: null, fecha_fin_inicial: null, tipo_contrato_id: null, estado: 'vigente',
            },
        };
        const segundo = importarSecop(primero, otro);
        expect(segundo.n_contrato).toBe('CO-099-2026');
        expect(segundo.objeto).toBe('Suministro de tubería');
        CAMPOS_DESDE_SECOP.filter(([campo]) => !['n_contrato', 'objeto'].includes(campo))
            .forEach(([campo]) => expect(segundo[campo], campo).toBe(''));
        expect(segundo.supervisor).toBe('Ana');
        expect(segundo.proyecto).toBe('3');
    });

    it('un contrato guardado que no venía de SECOP II conserva lo que SECOP II no trae', () => {
        const formulario = formularioContrato({ ...guardado, secop_id_contrato: null, estado: 'liquidado' });
        const importado = importarSecop(formulario, conContrato({ estado: null, objeto: null }));
        expect(importado.estado).toBe('liquidado');
        expect(importado.objeto).toBe('Construcción del puente');
        // Si ya venía de SECOP II, el nuevo resultado lo reemplaza entero.
        const deSecop = importarSecop({ ...formulario, secop_consultado: true }, conContrato({ estado: null, objeto: null }));
        expect(deSecop.estado).toBe('');
        expect(deSecop.objeto).toBe('');
    });

    it('vincular solo copia la referencia', () => {
        const formulario = formularioContrato({ ...guardado, secop_id_contrato: null, objeto: 'Otro objeto' });
        const vinculado = vincularSecop(formulario, resultado);
        expect(vinculado.objeto).toBe('Otro objeto');
        expect(vinculado.secop_id_contrato).toBe('CO1.PCCNTR.123456');
        expect(vinculado.secop_consultado).toBe(true);
    });
});

describe('formularioTieneDatos', () => {
    it('solo cuentan los campos que trae SECOP II', () => {
        expect(formularioTieneDatos(contratoVacio(3))).toBe(false);
        expect(formularioTieneDatos({ ...contratoVacio(), supervisor: 'Ana', anticipo: true })).toBe(false);
        expect(formularioTieneDatos({ ...contratoVacio(), objeto: 'Obra' })).toBe(true);
        expect(formularioTieneDatos({ ...contratoVacio(), valor_inicial: '1.000' })).toBe(true);
    });

    it('la lista de campos son claves del formulario', () => {
        const vacio = contratoVacio();
        CAMPOS_DESDE_SECOP.forEach(([campo]) => expect(vacio, campo).toHaveProperty(campo));
    });
});

describe('referenciaSecop', () => {
    it('sin consulta, la guardada del contrato', () => {
        expect(referenciaSecop(formularioContrato(guardado), guardado)).toEqual({
            id: 'CO1.PCCNTR.123456',
            proceso: 'CO1.BDOS.987',
            enlace: guardado.url_secop,
            consultado: '2026-11-01 10:00:00',
            pendiente: false,
            desvinculado: false,
        });
    });

    it('tras usar SECOP II, la del formulario, pendiente de guardar', () => {
        const formulario = vincularSecop(formularioContrato({ ...guardado, secop_id_contrato: null }), resultado);
        expect(referenciaSecop(formulario, { ...guardado, secop_id_contrato: null })).toMatchObject({ id: 'CO1.PCCNTR.123456', pendiente: true });
    });

    it('un enlace guardado que no es web no se ofrece', () => {
        expect(referenciaSecop(contratoVacio(), { url_secop: 'ftp://x' }).enlace).toBeNull();
        expect(referenciaSecop(contratoVacio(), null)).toEqual({ id: '', proceso: '', enlace: null, consultado: null, pendiente: false, desvinculado: false });
    });
});

describe('desvincularSecop', () => {
    it('deja la referencia vacía en el formulario, marcada para enviarse, sin consulta', () => {
        const formulario = formularioContrato(guardado);
        const desvinculado = desvincularSecop(formulario);
        expect(desvinculado).toEqual({
            ...formulario, secop_id_contrato: '', secop_proceso: '', url_secop: '', secop_consultado: false, secop_desvincular: true,
        });
    });

    it('al guardar, los tres campos van como null y sin secop_consultado', () => {
        const datos = datosContrato(desvincularSecop(formularioContrato(guardado)), guardado);
        expect(datos).toMatchObject({ secop_id_contrato: null, secop_proceso: null, url_secop: null });
        expect(datos).not.toHaveProperty('secop_consultado');
        expect(datos.objeto).toBe('Construcción del puente');
    });

    it('lo que tomó de SECOP II antes de desvincular también se descarta', () => {
        const aplicado = aplicarDiferencias({ ...formularioContrato(guardado), objeto: 'X' }, resultado, ['objeto'], guardado);
        expect(datosSecop(desvincularSecop(aplicado))).toEqual({ secop_id_contrato: null, secop_proceso: null, url_secop: null });
    });

    it('la referencia que muestra el editor queda vacía y pendiente de guardar', () => {
        expect(referenciaSecop(desvincularSecop(formularioContrato(guardado)), guardado)).toEqual({
            id: '', proceso: '', enlace: null, consultado: '2026-11-01 10:00:00', pendiente: true, desvinculado: true,
        });
    });

    it('volver a tomar un resultado de SECOP II envía la nueva referencia', () => {
        const otra = vincularSecop(desvincularSecop(formularioContrato(guardado)), conContrato({ secop_id_contrato: 'CO1.PCCNTR.9' }));
        expect(datosSecop(otra)).toMatchObject({ secop_id_contrato: 'CO1.PCCNTR.9', secop_consultado: true });
        expect(referenciaSecop(otra, guardado)).toMatchObject({ id: 'CO1.PCCNTR.9', desvinculado: false });
    });
});

describe('avisosSecop', () => {
    it('sin estado ni días adicionados, ninguno', () => {
        expect(avisosSecop(resultado)).toEqual([]);
        expect(avisosSecop({ ...resultado, secop: { ...resultado.secop, dias_adicionados: null } })).toEqual([]);
    });

    it('pide elegir el estado con el texto de SECOP II', () => {
        expect(avisosSecop({ ...conContrato({ estado: null }), secop: { ...resultado.secop, estado_secop: 'En aprobación' } }))
            .toEqual(['Elige el estado: SECOP II dice «En aprobación».']);
        expect(avisosSecop({ ...conContrato({ estado: null }), secop: { ...resultado.secop, estado_secop: null } }))
            .toEqual(['Elige el estado: SECOP II no lo informa.']);
    });

    it('con el formulario resultante, pide el estado solo si quedó sin elegir', () => {
        const sinEstado = { ...conContrato({ estado: null }), secop: { ...resultado.secop, estado_secop: 'En aprobación' } };
        expect(avisosSecop(sinEstado, { ...contratoVacio(), estado: 'celebrado' })).toEqual([]);
        expect(avisosSecop(sinEstado, importarSecop(contratoVacio(), sinEstado))).toEqual(['Elige el estado: SECOP II dice «En aprobación».']);
        // El estado de SECOP II no se copió (no se marcó) y el formulario no tiene: también se pide.
        expect(avisosSecop(resultado, contratoVacio())).toEqual(['Elige el estado: SECOP II dice «En ejecución».']);
    });

    it('recuerda registrar los días adicionados como prórroga', () => {
        expect(avisosSecop({ ...resultado, secop: { ...resultado.secop, dias_adicionados: 30 } }))
            .toEqual(['SECOP II reporta 30 días adicionados: regístralos como prórroga en Modificaciones.']);
        expect(avisosSecop({ ...resultado, secop: { ...resultado.secop, dias_adicionados: 1 } }))
            .toEqual(['SECOP II reporta 1 día adicionado: regístralos como prórroga en Modificaciones.']);
    });
});

describe('filaResultadoSecop', () => {
    it('lo que se lista de un resultado', () => {
        const fila = filaResultadoSecop(resultado);
        expect({ ...fila, valor: plano(fila.valor) }).toEqual({
            clave: 'CO1.PCCNTR.123456',
            idSecop: 'CO1.PCCNTR.123456',
            numero: 'CO-045-2026',
            entidad: 'Alcaldía de Becerril',
            contratista: 'Constructora S.A.S.',
            valor: '$ 40.000.000',
            suscripcion: '05/01/2026',
            inicio: '10/01/2026',
            fin: '30/06/2026',
            estado: 'En ejecución',
            registrado: null,
        });
    });

    it('uno ya registrado enlaza a su contrato', () => {
        const fila = filaResultadoSecop({ ...resultado, registrado: { id: 12, n_contrato: 'CO-045' } });
        expect(fila.registrado).toEqual({ ruta: '/contratos/12', numero: 'CO-045' });
    });

    it('los datos que faltan', () => {
        const fila = filaResultadoSecop({ contrato: {}, secop: null, registrado: null });
        expect(fila).toMatchObject({ numero: 'Sin número', entidad: '—', contratista: '—', valor: '—', inicio: '—', estado: '—' });
    });
});

describe('diferenciasSecop', () => {
    it('sin diferencias, la lista vacía (el valor se compara como número)', () => {
        expect(diferenciasSecop(formularioContrato(guardado), resultado, { tipos, contrato: guardado })).toEqual([]);
        const conCentavos = formularioContrato({ ...guardado, valor_inicial: '40000000' });
        expect(diferenciasSecop(conCentavos, resultado, { tipos, contrato: guardado })).toEqual([]);
    });

    it('solo los campos que difieren, legibles, en el orden del formulario', () => {
        const formulario = { ...formularioContrato(guardado), objeto: 'Puente viejo', valor_inicial: '35.000.000', tipo_contrato_id: '3', estado: 'suspendido' };
        expect(planos(diferenciasSecop(formulario, resultado, { tipos, contrato: guardado }))).toEqual([
            { campo: 'tipo_contrato_id', etiqueta: 'Tipo de contrato', actual: 'Suministro', secop: 'Obra', marcado: true, nota: null },
            {
                campo: 'estado', etiqueta: 'Estado', actual: 'Suspendido', secop: 'En ejecución', marcado: false,
                nota: 'Revisa antes de copiar: el estado de SECOP II puede ir detrás del de GestPro.',
            },
            { campo: 'objeto', etiqueta: 'Objeto', actual: 'Puente viejo', secop: 'Construcción del puente', marcado: true, nota: null },
            {
                campo: 'valor_inicial', etiqueta: 'Valor inicial', actual: '$ 35.000.000', secop: '$ 40.000.000', marcado: false,
                nota: 'Es la base del valor vigente: al guardar, el vigente se recalcula con las adiciones de Modificaciones.',
            },
        ]);
    });

    it('la fecha fin inicial no va marcada y lleva su nota; las fechas se leen día/mes/año', () => {
        const formulario = { ...formularioContrato(guardado), fecha_fin_inicial: '' };
        const [fin] = diferenciasSecop(formulario, resultado, { tipos, contrato: guardado });
        expect(fin).toMatchObject({ campo: 'fecha_fin_inicial', actual: '—', secop: '30/06/2026', marcado: false });
        expect(fin.nota).toMatch(/prórrogas/);
    });

    it('lo que SECOP II no trae no se compara', () => {
        const formulario = { ...formularioContrato(guardado), objeto: 'Otro', estado: 'liquidado' };
        expect(diferenciasSecop(formulario, conContrato({ objeto: null, estado: null }), { tipos, contrato: guardado })).toEqual([]);
    });

    it('compara el proceso y el enlace con los guardados', () => {
        const otro = conContrato({ secop_proceso: 'CO1.BDOS.111', url_secop: 'https://community.secop.gov.co/otro' });
        const diferencias = diferenciasSecop(formularioContrato(guardado), otro, { tipos, contrato: guardado });
        expect(diferencias.map((d) => d.campo)).toEqual(['secop_proceso', 'url_secop']);
        expect(diferencias[0]).toMatchObject({ etiqueta: 'Proceso en SECOP II', actual: 'CO1.BDOS.987', secop: 'CO1.BDOS.111', marcado: true });
    });

    it('un tipo que no está en el catálogo se nombra con el del contrato o con su id', () => {
        const formulario = { ...formularioContrato(guardado), tipo_contrato_id: '9' };
        expect(diferenciasSecop(formulario, resultado, { tipos: null, contrato: guardado })[0]).toMatchObject({ actual: 'Tipo 9', secop: 'Obra' });
    });

    it('camposMarcados da los marcados por defecto', () => {
        const formulario = { ...formularioContrato(guardado), objeto: 'X', valor_inicial: '1', fecha_fin_inicial: '2026-05-01', estado: 'terminado' };
        expect(camposMarcados(diferenciasSecop(formulario, resultado, { tipos, contrato: guardado }))).toEqual(['objeto']);
        expect(camposMarcados(null)).toEqual([]);
    });
});

describe('el estado suspendido con SECOP II', () => {
    const conEstado = (estado) => conContrato({ estado });
    const suspendidoAbierto = {
        ...guardado, estado: 'suspendido', aviso_suspension: { desde: '2026-11-05', dias: 14, reinicio_previsto: null, vencido: false },
    };

    it('a un contrato guardado en ejecución no le ofrece ni le copia «Suspendido», y avisa usar «Suspender»', () => {
        const formulario = formularioContrato(guardado);
        expect(diferenciasSecop(formulario, conEstado('suspendido'), { tipos, contrato: guardado })).toEqual([]);
        expect(aplicarDiferencias(formulario, conEstado('suspendido'), ['estado'], guardado).estado).toBe('en_ejecucion');
        expect(avisosSecop(conEstado('suspendido'), formulario, guardado))
            .toEqual(['SECOP II dice «Suspendido»: usa «Suspender» para registrarlo con su fecha.']);
    });

    it('con una suspensión abierta no lo saca de suspendido, y avisa usar «Reiniciar»', () => {
        const formulario = formularioContrato(suspendidoAbierto);
        expect(diferenciasSecop(formulario, resultado, { tipos, contrato: suspendidoAbierto })).toEqual([]);
        expect(aplicarDiferencias(formulario, resultado, ['estado'], suspendidoAbierto).estado).toBe('suspendido');
        expect(avisosSecop(resultado, formulario, suspendidoAbierto))
            .toEqual(['SECOP II dice «En ejecución»: usa «Reiniciar» para registrar el reinicio.']);
    });

    it('un suspendido sin suspensión abierta sí toma el estado de SECOP II', () => {
        const sinAbierta = { ...guardado, estado: 'suspendido', aviso_suspension: null };
        const formulario = formularioContrato(sinAbierta);
        expect(diferenciasSecop(formulario, resultado, { tipos, contrato: sinAbierta }).map((d) => d.campo)).toEqual(['estado']);
        expect(aplicarDiferencias(formulario, resultado, ['estado'], sinAbierta).estado).toBe('en_ejecucion');
        expect(avisosSecop(resultado, formulario, sinAbierta)).toEqual([]);
    });
});

describe('aplicarDiferencias', () => {
    const formulario = { ...formularioContrato(guardado), objeto: 'X', valor_inicial: '1', estado: 'suspendido' };
    const otro = conContrato({ secop_proceso: 'CO1.BDOS.111', url_secop: 'https://community.secop.gov.co/otro' });

    it('copia solo los elegidos y conserva la referencia que no se eligió', () => {
        const aplicado = aplicarDiferencias(formulario, otro, ['objeto', 'url_secop'], guardado);
        expect(aplicado.objeto).toBe('Construcción del puente');
        expect(aplicado.valor_inicial).toBe('1');
        expect(aplicado.estado).toBe('suspendido');
        expect(aplicado.secop_id_contrato).toBe('CO1.PCCNTR.123456');
        expect(aplicado.secop_proceso).toBe('CO1.BDOS.987');
        expect(aplicado.url_secop).toBe('https://community.secop.gov.co/otro');
        expect(aplicado.secop_consultado).toBe(true);
    });

    it('sin elegidos, solo marca la consulta', () => {
        const aplicado = aplicarDiferencias(formulario, otro, [], guardado);
        expect(aplicado.objeto).toBe('X');
        expect(datosSecop(aplicado)).toEqual({
            secop_id_contrato: 'CO1.PCCNTR.123456', secop_proceso: 'CO1.BDOS.987', url_secop: guardado.url_secop, secop_consultado: true,
        });
    });

    it('sin diferencias, el formulario queda igual y marcado como consultado (la consulta se registra al guardar)', () => {
        const formulario = formularioContrato(guardado);
        expect(diferenciasSecop(formulario, resultado, { tipos, contrato: guardado })).toEqual([]);
        const consultado = aplicarDiferencias(formulario, resultado, camposMarcados([]), guardado);
        expect(consultado).toEqual({
            ...formulario,
            secop_id_contrato: guardado.secop_id_contrato, secop_proceso: guardado.secop_proceso, url_secop: guardado.url_secop, secop_consultado: true,
        });
        expect(datosContrato(consultado, guardado)).toMatchObject({ secop_id_contrato: 'CO1.PCCNTR.123456', secop_consultado: true });
    });

    it('al vincular un contrato sin ID, aplica sobre la referencia recién tomada', () => {
        const sinId = { ...guardado, secop_id_contrato: null, secop_proceso: null, url_secop: null };
        const vinculado = vincularSecop({ ...formularioContrato(sinId), objeto: 'X' }, otro);
        const aplicado = aplicarDiferencias(vinculado, otro, ['objeto'], sinId);
        expect(aplicado.secop_proceso).toBe('CO1.BDOS.111');
        expect(aplicado.objeto).toBe('Construcción del puente');
    });

    it('camposCambiados da los campos que SECOP II cambió', () => {
        expect(camposCambiados(formulario, aplicarDiferencias(formulario, otro, ['objeto', 'estado'], guardado))).toEqual(['estado', 'objeto']);
    });
});

describe('datosSecop y datosContrato', () => {
    it('sin consulta no se envía la referencia: la guardada no cambia', () => {
        expect(datosSecop(formularioContrato(guardado))).toEqual({});
        const datos = datosContrato(formularioContrato(guardado), guardado);
        ['secop_id_contrato', 'secop_proceso', 'url_secop', 'secop_consultado'].forEach((campo) => expect(datos).not.toHaveProperty(campo));
    });

    it('tras usar SECOP II, van los tres campos (vacío como null) y la consulta', () => {
        const importado = importarSecop(contratoVacio(), conContrato({ secop_proceso: null }));
        expect(datosContrato(importado)).toMatchObject({
            n_contrato: 'CO-045-2026',
            valor_inicial: '40000000',
            tipo_contrato_id: 2,
            secop_id_contrato: 'CO1.PCCNTR.123456',
            secop_proceso: null,
            url_secop: resultado.contrato.url_secop,
            secop_consultado: true,
        });
    });
});

describe('mensajeSecop', () => {
    const error = (status, data) => ({ response: { status, data } });

    it('usa el mensaje del servidor de un 502 o un 404', () => {
        expect(mensajeSecop(error(502, { mensaje: 'No se pudo consultar SECOP II. Intenta de nuevo en unos minutos.' })))
            .toBe('No se pudo consultar SECOP II. Intenta de nuevo en unos minutos.');
        expect(mensajeSecop(error(404, { mensaje: 'SECOP II no tiene ese contrato.' }))).toBe('SECOP II no tiene ese contrato.');
    });

    it('de un 422, el primer error', () => {
        expect(mensajeSecop(error(422, { message: 'x', errors: { q: ['Escribe el número, el ID o el enlace del contrato.'] } })))
            .toBe('Escribe el número, el ID o el enlace del contrato.');
    });

    it('sin mensaje, el genérico de la API', () => {
        expect(mensajeSecop(error(403, {}))).toBe('No tienes permiso para esta acción.');
        expect(mensajeSecop({ request: {} })).toBe('No hay conexión con el servidor.');
    });
});

describe('estado de cierre que propone SECOP II (fase 7A)', () => {
    const liquidadoEnSecop = { ...conContrato({ estado: 'liquidado' }), secop: { ...resultado.secop, estado_secop: 'Liquidado' } };
    const terminadoEnSecop = { ...conContrato({ estado: 'terminado' }), secop: { ...resultado.secop, estado_secop: 'Terminado' } };

    it('en un contrato guardado, el estado terminado o liquidado de SECOP II no se ofrece ni se copia', () => {
        const formulario = formularioContrato(guardado);
        expect(diferenciasSecop(formulario, liquidadoEnSecop, { tipos, contrato: guardado })).toEqual([]);
        expect(diferenciasSecop({ ...formulario, objeto: 'X' }, terminadoEnSecop, { tipos, contrato: guardado }).map((d) => d.campo)).toEqual(['objeto']);
        expect(aplicarDiferencias(formulario, liquidadoEnSecop, ['estado'], guardado).estado).toBe('en_ejecucion');
    });

    it('y avisa qué acción usar', () => {
        expect(avisosSecop(liquidadoEnSecop, formularioContrato(guardado), guardado))
            .toEqual(['SECOP II dice «Liquidado»: usa «Liquidar» para registrarlo con sus fechas y valor.']);
        expect(avisosSecop(terminadoEnSecop, formularioContrato(guardado), guardado))
            .toEqual(['SECOP II dice «Terminado»: usa «Terminar» para registrarlo con su fecha de terminación.']);
    });

    it('si el contrato guardado ya está en ese estado, nada cambia', () => {
        const liquidado = { ...guardado, estado: 'liquidado' };
        expect(diferenciasSecop(formularioContrato(liquidado), liquidadoEnSecop, { tipos, contrato: liquidado })).toEqual([]);
        expect(avisosSecop(liquidadoEnSecop, formularioContrato(liquidado), liquidado)).toEqual([]);
    });

    it('un contrato guardado sin estado tampoco toma el estado de cierre de SECOP II, y se avisa', () => {
        const sinEstado = { ...guardado, estado: null };
        const formulario = formularioContrato(sinEstado);
        expect(diferenciasSecop(formulario, liquidadoEnSecop, { tipos, contrato: sinEstado }).map((d) => d.campo)).not.toContain('estado');
        expect(aplicarDiferencias(formulario, liquidadoEnSecop, ['estado'], sinEstado).estado).toBe('');
        expect(avisosSecop(liquidadoEnSecop, formulario, sinEstado))
            .toContain('SECOP II dice «Liquidado»: usa «Liquidar» para registrarlo con sus fechas y valor.');
        expect(avisosSecop(terminadoEnSecop, formulario, sinEstado))
            .toContain('SECOP II dice «Terminado»: usa «Terminar» para registrarlo con su fecha de terminación.');
    });

    it('un contrato nuevo sí toma el estado de cierre de SECOP II, sin aviso', () => {
        const importado = importarSecop(contratoVacio(), liquidadoEnSecop);
        expect(importado.estado).toBe('liquidado');
        expect(avisosSecop(liquidadoEnSecop, importado)).toEqual([]);
        expect(diferenciasSecop({ ...importado, estado: 'en_ejecucion' }, liquidadoEnSecop, { tipos }).map((d) => d.campo)).toEqual(['estado']);
    });
});
