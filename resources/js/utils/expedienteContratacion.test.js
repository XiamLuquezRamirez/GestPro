import { describe, it, expect, vi } from 'vitest';
import {
    accionesRevision,
    aprobarConConfirmacion,
    agruparPorEtapa,
    cierreSinAvisos,
    datosAprobarEtapa,
    dialogoAprobarEtapa,
    etapasConAvisoAnterior,
    fechaMomento,
    hayRevisionAtrasada,
    pendientesAlAprobar,
    recalcularAvisoAnterior,
    revisionEtapa,
    tituloCierrePendientes,
    avisoIncompatiblesProceso,
    alternarPlantillaNueva,
    avanceEtapas,
    conAvance,
    conEtapaActualizada,
    datosFechaEtapa,
    datosPropioContratacion,
    errorFechaEtapa,
    etapasConFechas,
    fechaCompleta,
    fechaHoyBogota,
    fechaPorGuardar,
    fechaLegible,
    fusionarAvance,
    fechasEtapa,
    errorDeProceso,
    esIdProceso,
    formularioPropioContratacion,
    htmlPendientesCierre,
    NOTA_PONDERADO,
    opcionesEtapaPropio,
    pondera,
    propioCompleto,
    seleccionInicial,
    textoEstadoExpediente,
    textoPendientesCierre,
    textoSinPlantillas,
} from './expedienteContratacion';
import {
    abrirFormularioDetalle, enlaceProceso, filaContratoProceso, fuentesDelDetalle, procesoDelFormulario, puedeRegistrarDesdeDetalle,
    textoContratosProceso,
} from './expedienteContratacion';
import { agruparChecklist, describirEvento } from './expedienteChecklist';

const etapa = (id, orden, nombre = `Etapa ${id}`) => ({ id, nombre, orden });

const fila = (id, et, cambios = {}) => ({
    id,
    etapa: et,
    estado: 'pendiente',
    cumplido: false,
    cuenta: true,
    caracter: 'obligatorio',
    requisito: { codigo: `R${id}`, texto: `Requisito ${id}` },
    plantilla: { id: 1, nombre: 'Licitación', motor: 'simple' },
    propio: null,
    ...cambios,
});

describe('esIdProceso', () => {
    it('acepta un entero positivo y rechaza lo demás', () => {
        expect(esIdProceso('12')).toBe(true);
        expect(esIdProceso('0')).toBe(false);
        expect(esIdProceso('abc')).toBe(false);
        expect(esIdProceso('1.5')).toBe(false);
        expect(esIdProceso(undefined)).toBe(false);
    });
});

describe('errorDeProceso', () => {
    it('un 404 no se reintenta', () => {
        expect(errorDeProceso({ response: { status: 404 } })).toEqual({ mensaje: 'El proceso no existe.', reintentable: false });
    });

    it('otro error se reintenta, con el mensaje de la API', () => {
        expect(errorDeProceso({ response: { status: 500 } })).toEqual({ mensaje: 'Error del servidor. Inténtalo de nuevo.', reintentable: true });
    });
});

describe('agruparPorEtapa', () => {
    it('agrupa por etapa en el orden del catálogo, con "Sin etapa" al final', () => {
        const a = etapa(5, 2, 'Selección');
        const b = etapa(3, 1, 'Planeación');
        const grupos = agruparPorEtapa([
            fila(1, null),
            fila(2, a),
            fila(3, b),
            fila(4, a, { estado: 'cumplido', cumplido: true }),
            fila(5, b, { propio: { titulo: 'Propio' }, requisito: null, plantilla: null }),
        ]);

        expect(grupos.map((g) => g.clave)).toEqual(['etapa-3', 'etapa-5', 'sin-etapa']);
        expect(grupos.map((g) => g.titulo)).toEqual(['1. Planeación', '2. Selección', 'Sin etapa']);
        // Dentro de cada etapa, en el orden en que llegan (los propios, que el backend pone al final, también).
        expect(grupos[0].filas.map((f) => f.id)).toEqual([3, 5]);
        expect(grupos[1].filas.map((f) => f.id)).toEqual([2, 4]);
        expect(grupos[2].filas.map((f) => f.id)).toEqual([1]);
    });

    it('a igual orden, desempata por id', () => {
        const grupos = agruparPorEtapa([fila(1, etapa(9, 1)), fila(2, etapa(4, 1))]);
        expect(grupos.map((g) => g.etapa.id)).toEqual([4, 9]);
    });

    it('cuenta solo lo que cuenta, como el backend', () => {
        const a = etapa(1, 1);
        const [grupo] = agruparPorEtapa([
            fila(1, a, { estado: 'cumplido', cumplido: true }),
            fila(2, a),
            fila(3, a, { cuenta: false, caracter: 'opcional', estado: 'cumplido', cumplido: true }),
            fila(4, a, { cuenta: false, estado: 'no_aplica' }),
        ]);
        expect(grupo.total).toBe(2);
        expect(grupo.cumplidos).toBe(1);
    });

    it('sin filas, sin grupos', () => {
        expect(agruparPorEtapa([])).toEqual([]);
        expect(agruparPorEtapa(undefined)).toEqual([]);
    });

    it('no cambia la agrupación de viabilización', () => {
        const filas = [fila(1, etapa(1, 1)), fila(2, null)];
        const bloques = agruparChecklist(filas);
        expect(bloques).toHaveLength(1);
        expect(bloques[0].tipo).toBe('simple');
    });
});

describe('avanceEtapas', () => {
    it('presenta el avance por etapa de la API, con título y clave', () => {
        expect(avanceEtapas([
            { etapa: etapa(3, 1, 'Planeación'), total: 4, cumplidos: 1, porcentaje: 25 },
            { etapa: null, total: 2, cumplidos: 2, porcentaje: 100 },
        ])).toEqual([
            { clave: 'etapa-3', titulo: '1. Planeación', total: 4, cumplidos: 1, porcentaje: 25 },
            { clave: 'sin-etapa', titulo: 'Sin etapa', total: 2, cumplidos: 2, porcentaje: 100 },
        ]);
    });

    it('sin avance, lista vacía', () => {
        expect(avanceEtapas(undefined)).toEqual([]);
    });
});

describe('conAvance', () => {
    const expediente = { id: 1, progreso: { total: 2, cumplidos: 0, porcentaje: 0 }, avance_por_etapa: [{ etapa: null, total: 2, cumplidos: 0, porcentaje: 0 }] };

    it('toma el progreso y el avance por etapa de una respuesta parcial', () => {
        const datos = { progreso: { total: 2, cumplidos: 1, porcentaje: 50 }, avance_por_etapa: [{ etapa: null, total: 2, cumplidos: 1, porcentaje: 50 }] };
        expect(conAvance(expediente, datos)).toEqual({ ...expediente, ...datos });
    });

    it('lo que la respuesta no trae queda como estaba', () => {
        expect(conAvance(expediente, {})).toEqual(expediente);
        expect(conAvance(expediente, undefined)).toEqual(expediente);
    });

    it('también toma los avisos, si la respuesta los trae', () => {
        const conAvisos = { ...expediente, avisos: { etapas_vencidas: 0 } };
        expect(conAvance(conAvisos, { avisos: { etapas_vencidas: 1 } }).avisos).toEqual({ etapas_vencidas: 1 });
        expect(conAvance(conAvisos, {}).avisos).toEqual({ etapas_vencidas: 0 });
    });
});

const avanceApi = (id, cambios = {}) => ({
    etapa: id === null ? null : etapa(id, id, `Etapa ${id}`),
    total: 2,
    cumplidos: 1,
    porcentaje: 50,
    fecha_programada: null,
    fecha_real: null,
    fecha_real_sugerida: false,
    completa: false,
    alerta: 'sin_fecha',
    ...cambios,
});

describe('fechasEtapa', () => {
    it('las fechas de una etapa como valores del input date, con su semáforo', () => {
        expect(fechasEtapa(avanceApi(3, { fecha_programada: '2026-11-18', alerta: 'vencida' }))).toEqual({
            etapaId: 3,
            conFechas: true,
            programada: '2026-11-18',
            real: '',
            sugerida: false,
            alerta: { texto: 'Vencida', tono: 'coral' },
        });
    });

    it('"sugerida" solo con una fecha real que puso el sistema', () => {
        expect(fechasEtapa(avanceApi(1, { fecha_real: '2026-11-19', fecha_real_sugerida: true, completa: true, alerta: 'completa' })))
            .toMatchObject({ real: '2026-11-19', sugerida: true, alerta: { texto: 'Completa', tono: 'verde' } });
        expect(fechasEtapa(avanceApi(1, { fecha_real: null, fecha_real_sugerida: true })).sugerida).toBe(false);
        expect(fechasEtapa(avanceApi(1, { fecha_real: '2026-11-19', fecha_real_sugerida: false })).sugerida).toBe(false);
    });

    it('sin fecha, sin etiqueta', () => {
        expect(fechasEtapa(avanceApi(1)).alerta).toBeNull();
    });

    it('el grupo sin etapa no tiene fechas', () => {
        expect(fechasEtapa(avanceApi(null, { alerta: 'completa' }))).toEqual({
            etapaId: null, conFechas: false, programada: '', real: '', sugerida: false, alerta: { texto: 'Completa', tono: 'verde' },
        });
    });

    it('una respuesta sin las claves nuevas no rompe', () => {
        expect(fechasEtapa({ etapa: etapa(2, 2), total: 1, cumplidos: 0, porcentaje: 0 })).toEqual({
            etapaId: 2, conFechas: true, programada: '', real: '', sugerida: false, alerta: null,
        });
    });
});

describe('etapasConFechas', () => {
    it('el avance de cada etapa con sus fechas', () => {
        const lista = etapasConFechas([avanceApi(1, { fecha_programada: '2026-11-21', alerta: 'proxima' }), avanceApi(null)]);
        expect(lista).toHaveLength(2);
        expect(lista[0]).toMatchObject({ clave: 'etapa-1', titulo: '1. Etapa 1', total: 2, cumplidos: 1, porcentaje: 50, etapaId: 1, programada: '2026-11-21' });
        expect(lista[0].alerta).toEqual({ texto: 'Próxima', tono: 'ambar' });
        expect(lista[1]).toMatchObject({ clave: 'sin-etapa', conFechas: false });
        expect(etapasConFechas(undefined)).toEqual([]);
    });
});

describe('fechaCompleta', () => {
    it('acepta vacío (borrar) y una fecha año-mes-día con año de cuatro cifras desde 1900', () => {
        expect(fechaCompleta('')).toBe(true);
        expect(fechaCompleta('2026-11-25')).toBe(true);
    });

    it('rechaza lo que el input da mientras se escribe el año', () => {
        expect(fechaCompleta('0002-11-25')).toBe(false);
        expect(fechaCompleta('0202-11-25')).toBe(false);
        expect(fechaCompleta('2026-13-01')).toBe(false);
        expect(fechaCompleta('25/11/2026')).toBe(false);
        expect(fechaCompleta(undefined)).toBe(false);
    });
});

describe('datosFechaEtapa', () => {
    it('solo el campo que cambió; vacío borra la fecha', () => {
        expect(datosFechaEtapa('fecha_programada', '2026-11-25')).toEqual({ fecha_programada: '2026-11-25' });
        expect(datosFechaEtapa('fecha_real', '')).toEqual({ fecha_real: null });
    });

    it('un campo desconocido no se envía', () => {
        expect(datosFechaEtapa('otra', '2026-11-25')).toEqual({});
    });
});

describe('conEtapaActualizada', () => {
    const expediente = {
        id: 5,
        avance_por_etapa: [avanceApi(1), avanceApi(2), avanceApi(null)],
        avisos: { etapas_vencidas: 0 },
    };

    it('reemplaza la etapa que respondió el PUT y pone los avisos nuevos', () => {
        const etapaNueva = avanceApi(2, { fecha_programada: '2026-11-10', alerta: 'vencida' });
        const nuevo = conEtapaActualizada(expediente, { etapa: etapaNueva, avisos: { etapas_vencidas: 1 } });
        expect(nuevo.avance_por_etapa).toEqual([avanceApi(1), etapaNueva, avanceApi(null)]);
        expect(nuevo.avisos).toEqual({ etapas_vencidas: 1 });
        expect(expediente.avance_por_etapa[1].alerta).toBe('sin_fecha');
    });

    it('sin la etapa en el avance, solo los avisos', () => {
        const nuevo = conEtapaActualizada(expediente, { etapa: avanceApi(9), avisos: { etapas_vencidas: 2 } });
        expect(nuevo.avance_por_etapa).toEqual(expediente.avance_por_etapa);
        expect(nuevo.avisos).toEqual({ etapas_vencidas: 2 });
    });

    it('sin expediente o sin respuesta, como estaba', () => {
        expect(conEtapaActualizada(null, { etapa: avanceApi(1) })).toBeNull();
        expect(conEtapaActualizada(expediente, undefined)).toEqual(expediente);
    });
});

describe('errorFechaEtapa', () => {
    it('el error del campo que envió la validación', () => {
        const error = { response: { status: 422, data: { message: 'x', errors: { fecha_real: ['Escribe la fecha real como año-mes-día.'] } } } };
        expect(errorFechaEtapa(error, 'fecha_real')).toBe('Escribe la fecha real como año-mes-día.');
    });

    it('la regla de negocio con el campo, sin repetirla', () => {
        const error = { response: { status: 422, data: {
            mensaje: 'La fecha real no puede ser posterior a hoy.',
            errores: ['La fecha real no puede ser posterior a hoy.'],
            errors: { fecha_real: ['La fecha real no puede ser posterior a hoy.'] },
        } } };
        expect(errorFechaEtapa(error, 'fecha_real')).toBe('La fecha real no puede ser posterior a hoy.');
    });

    it('un rechazo sin campo (expediente cerrado) o de otro tipo, con el mensaje de la API', () => {
        expect(errorFechaEtapa({ response: { status: 422, data: { mensaje: 'El expediente está cerrado.' } } }, 'fecha_programada'))
            .toBe('El expediente está cerrado.');
        expect(errorFechaEtapa({ response: { status: 403 } }, 'fecha_real')).toBe('No tienes permiso para esta acción.');
    });
});

describe('fechaHoyBogota', () => {
    it('el día de Bogotá, no el de UTC: a las 20:00 de Bogotá (01:00 UTC) sigue siendo el mismo día', () => {
        expect(fechaHoyBogota(new Date('2026-11-20T01:00:00Z'))).toBe('2026-11-19');
        expect(fechaHoyBogota(new Date('2026-11-20T04:59:00Z'))).toBe('2026-11-19');
    });

    it('a las 00:00 de Bogotá (05:00 UTC) cambia el día', () => {
        expect(fechaHoyBogota(new Date('2026-11-20T05:00:00Z'))).toBe('2026-11-20');
        expect(fechaHoyBogota(new Date('2026-01-05T12:00:00Z'))).toBe('2026-01-05');
    });
});

describe('fechaLegible', () => {
    it('año-mes-día como día/mes/año, sin pasar por zonas horarias', () => {
        expect(fechaLegible('2026-11-19')).toBe('19/11/2026');
        expect(fechaLegible('2026-01-05')).toBe('05/01/2026');
    });

    it('sin fecha, "Sin fecha"', () => {
        expect(fechaLegible('')).toBe('Sin fecha');
        expect(fechaLegible(null)).toBe('Sin fecha');
        expect(fechaLegible(undefined)).toBe('Sin fecha');
    });
});

describe('fusionarAvance (respuesta del checklist que llega después de guardar una fecha)', () => {
    const actual = [
        avanceApi(1, { fecha_programada: '2026-12-01', fecha_real: '2026-11-18', alerta: 'al_dia' }),
        avanceApi(2, { fecha_programada: '2026-12-05', alerta: 'al_dia' }),
    ];
    const vieja = [
        avanceApi(1, { cumplidos: 2, porcentaje: 100, fecha_programada: '2026-11-01', fecha_real: null, alerta: 'vencida' }),
        avanceApi(2, { cumplidos: 2, porcentaje: 100, fecha_programada: '2026-11-02', alerta: 'vencida' }),
    ];

    it('sin etapas protegidas, lo que llega', () => {
        expect(fusionarAvance(actual, vieja, new Set())).toEqual(vieja);
        expect(fusionarAvance(actual, vieja)).toEqual(vieja);
    });

    it('en una etapa protegida (fecha guardada hace poco) conserva las fechas y el semáforo, y toma el avance nuevo', () => {
        const r = fusionarAvance(actual, vieja, new Set([1]));
        expect(r[0]).toMatchObject({ cumplidos: 2, porcentaje: 100, fecha_programada: '2026-12-01', fecha_real: '2026-11-18', alerta: 'al_dia' });
        expect(r[1]).toEqual(vieja[1]);
    });

    it('si la etapa se completó o dejó de estarlo, la fecha real (sugerida o borrada) es la que llega', () => {
        const completada = [avanceApi(1, { completa: true, fecha_programada: '2026-11-01', fecha_real: '2026-11-19', fecha_real_sugerida: true, alerta: 'completa' })];
        const r = fusionarAvance([actual[0]], completada, new Set([1]));
        expect(r[0]).toMatchObject({ fecha_programada: '2026-12-01', fecha_real: '2026-11-19', fecha_real_sugerida: true, alerta: 'completa' });
    });

    it('el grupo sin etapa y las etapas nuevas pasan tal cual', () => {
        const r = fusionarAvance(actual, [avanceApi(null), avanceApi(9)], new Set([1]));
        expect(r).toEqual([avanceApi(null), avanceApi(9)]);
    });

    it('sin lista que llegue, la actual', () => {
        expect(fusionarAvance(actual, undefined, new Set([1]))).toBe(actual);
    });
});

describe('conAvance con etapas protegidas', () => {
    it('aplica fusionarAvance al avance por etapa', () => {
        const expediente = { avance_por_etapa: [avanceApi(1, { fecha_programada: '2026-12-01' })], progreso: { cumplidos: 0 } };
        const datos = { avance_por_etapa: [avanceApi(1, { fecha_programada: '2026-11-01' })], progreso: { cumplidos: 1 } };
        expect(conAvance(expediente, datos, new Set([1])).avance_por_etapa[0].fecha_programada).toBe('2026-12-01');
        expect(conAvance(expediente, datos).avance_por_etapa[0].fecha_programada).toBe('2026-11-01');
    });
});

describe('fechaPorGuardar', () => {
    it('nada si el borrador es lo ya guardado', () => {
        expect(fechaPorGuardar('2026-11-19', '2026-11-19')).toBe('nada');
    });

    it('error si es una fecha a medio escribir', () => {
        expect(fechaPorGuardar('0002-11-19', '2026-11-19')).toBe('incompleta');
    });

    it('guardar si cambió y está completa (vacía borra)', () => {
        expect(fechaPorGuardar('2026-11-20', '2026-11-19')).toBe('guardar');
        expect(fechaPorGuardar('', '2026-11-19')).toBe('guardar');
    });
});

describe('opcionesEtapaPropio', () => {
    const etapas = [
        { id: 2, nombre: 'Selección', orden: 2, activo: true },
        { id: 1, nombre: 'Planeación', orden: 1, activo: true },
        { id: 3, nombre: 'Vieja', orden: 3, activo: false },
    ];

    it('las activas en el orden del catálogo, numeradas', () => {
        expect(opcionesEtapaPropio(etapas, null)).toEqual([
            { valor: '1', etiqueta: '1. Planeación' },
            { valor: '2', etiqueta: '2. Selección' },
        ]);
    });

    it('más la que ya tiene el propio, aunque esté inactiva', () => {
        expect(opcionesEtapaPropio(etapas, { etapa: { id: 3, nombre: 'Vieja', orden: 3 } }).at(-1))
            .toEqual({ valor: '3', etiqueta: '3. Vieja (inactiva)' });
    });

    it('sin catálogo, solo la actual', () => {
        expect(opcionesEtapaPropio(null, { etapa: { id: 3, nombre: 'Vieja', orden: 3 } })).toEqual([{ valor: '3', etiqueta: '3. Vieja' }]);
        expect(opcionesEtapaPropio(null, null)).toEqual([]);
    });
});

describe('formulario de un propio de contratación', () => {
    it('vacío para uno nuevo, sin etapa', () => {
        expect(formularioPropioContratacion(null)).toEqual({ titulo: '', descripcion: '', categoria: '', caracter: 'obligatorio', etapa_propia_id: '' });
    });

    it('con la etapa de la fila', () => {
        const propio = fila(1, etapa(4, 1), { propio: { titulo: 'T', descripcion: null, categoria: 'tecnico' }, caracter: 'opcional' });
        expect(formularioPropioContratacion(propio)).toEqual({ titulo: 'T', descripcion: '', categoria: 'tecnico', caracter: 'opcional', etapa_propia_id: '4' });
    });

    it('lo que espera la API: la etapa como número', () => {
        expect(datosPropioContratacion({ titulo: ' T ', descripcion: '', categoria: '', caracter: 'obligatorio', etapa_propia_id: '4' }))
            .toEqual({ titulo: 'T', descripcion: null, categoria: null, caracter: 'obligatorio', etapa_propia_id: 4 });
        expect(datosPropioContratacion({ titulo: 'T', descripcion: '', categoria: '', caracter: 'obligatorio', etapa_propia_id: '' }).etapa_propia_id).toBeNull();
    });

    it('se puede guardar con título y etapa', () => {
        expect(propioCompleto({ titulo: 'T', etapa_propia_id: '4' })).toBe(true);
        expect(propioCompleto({ titulo: '  ', etapa_propia_id: '4' })).toBe(false);
        expect(propioCompleto({ titulo: 'T', etapa_propia_id: '' })).toBe(false);
    });
});

describe('pendientes al cerrar', () => {
    const pendientes = {
        total: 4,
        por_etapa: [
            { etapa: etapa(3, 1, 'Planeación'), pendientes: 3 },
            { etapa: null, pendientes: 1 },
        ],
    };

    it('cuántos faltan y en qué etapas', () => {
        expect(textoPendientesCierre(pendientes)).toEqual({
            titulo: 'Faltan 4 requisitos por cumplir',
            etapas: ['1. Planeación: 3 pendientes', 'Sin etapa: 1 pendiente'],
        });
    });

    it('en singular', () => {
        expect(textoPendientesCierre({ total: 1, por_etapa: [{ etapa: etapa(3, 1, 'Planeación'), pendientes: 1 }] }).titulo)
            .toBe('Falta 1 requisito por cumplir');
    });

    it('sin pendientes, null', () => {
        expect(textoPendientesCierre({ total: 0, por_etapa: [] })).toBeNull();
        expect(textoPendientesCierre(undefined)).toBeNull();
    });

    it('el html escapa los nombres de las etapas', () => {
        const html = htmlPendientesCierre({ total: 1, por_etapa: [{ etapa: etapa(3, 1, '<b>X</b>'), pendientes: 1 }] });
        expect(html).toContain('&lt;b&gt;X&lt;/b&gt;');
        expect(html).not.toContain('<b>X</b>');
        expect(html).toContain('Puedes cerrarlo de todos modos');
    });
});

describe('textoEstadoExpediente', () => {
    it('borrador', () => {
        expect(textoEstadoExpediente({ estado: 'borrador' })).toBe('Borrador');
    });

    it('cerrado, con fecha y usuario si los hay', () => {
        const texto = textoEstadoExpediente({ estado: 'cerrado', fecha_cierre: '2026-11-18T15:00:00Z', usuario_cierre: 'Ana' });
        expect(texto).toMatch(/^Cerrado el .+ por Ana$/);
        expect(textoEstadoExpediente({ estado: 'cerrado', fecha_cierre: null, usuario_cierre: null })).toBe('Cerrado');
    });
});

describe('selección de plantillas para el expediente nuevo', () => {
    it('con una sola plantilla compatible, ya va elegida; con varias, ninguna', () => {
        expect(seleccionInicial([{ id: 7 }])).toEqual([7]);
        expect(seleccionInicial([{ id: 7 }, { id: 8 }])).toEqual([]);
        expect(seleccionInicial(undefined)).toEqual([]);
    });

    it('la primera elegida es la principal: se conserva el orden en que se eligen', () => {
        let seleccion = [];
        seleccion = alternarPlantillaNueva(seleccion, 8);
        seleccion = alternarPlantillaNueva(seleccion, 7);
        expect(seleccion).toEqual([8, 7]);
        seleccion = alternarPlantillaNueva(seleccion, 8);
        expect(seleccion).toEqual([7]);
    });
});

describe('textoSinPlantillas', () => {
    it('dice por qué no hay plantillas', () => {
        expect(textoSinPlantillas).toBe('No hay plantillas de contratación publicadas que correspondan a la modalidad, la fuente y el sector de este proceso.');
    });
});

describe('historial', () => {
    it('describe el cierre del expediente', () => {
        expect(describirEvento({ evento: 'expediente_cerrado', antes: {}, despues: {} })).toBe('Cerró el expediente');
    });
});

describe('avisoIncompatiblesProceso', () => {
    it('nombra las plantillas que ya no corresponden al proceso', () => {
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: false }, { nombre: 'B', compatible: true }])).toEqual({
            titulo: 'Una plantilla ya no corresponde a la modalidad, la fuente o el sector del proceso',
            detalle: 'A. El expediente la conserva tal como está.',
        });
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: false }, { nombre: 'B', compatible: false }]).detalle)
            .toBe('A, B. El expediente las conserva tal como están.');
    });

    it('una inactiva que sigue cubriendo el proceso se avisa como inactiva', () => {
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: false, corresponde: true, activa: false, publicada: true }])).toEqual({
            titulo: 'Una plantilla del expediente está inactiva',
            detalle: 'A. El expediente la conserva tal como está.',
        });
        expect(avisoIncompatiblesProceso([
            { nombre: 'A', compatible: false, corresponde: true, activa: false, publicada: true },
            { nombre: 'B', compatible: false, corresponde: true, activa: false, publicada: false },
        ])?.titulo).toBe('Algunas plantillas del expediente están inactivas');
    });

    it('una sin versión publicada se avisa como tal', () => {
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: false, corresponde: true, activa: true, publicada: false }])?.titulo)
            .toBe('Una plantilla ya no tiene una versión publicada');
    });

    it('si no cubre el proceso, eso es lo que se avisa aunque además esté inactiva', () => {
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: false, corresponde: false, activa: false, publicada: true }])?.titulo)
            .toBe('Una plantilla ya no corresponde a la modalidad, la fuente o el sector del proceso');
    });

    it('con razones distintas, da la de cada una', () => {
        expect(avisoIncompatiblesProceso([
            { nombre: 'A', compatible: false, corresponde: false, activa: true, publicada: true },
            { nombre: 'B', compatible: false, corresponde: true, activa: false, publicada: true },
            { nombre: 'C', compatible: true, corresponde: true, activa: true, publicada: true },
        ])).toEqual({
            titulo: 'Algunas plantillas ya no sirven al proceso',
            detalle: 'A (ya no corresponde a la modalidad, la fuente o el sector), B (inactiva). El expediente las conserva tal como están.',
        });
    });

    it('sin incompatibles, null', () => {
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: true }])).toBeNull();
        expect(avisoIncompatiblesProceso(undefined)).toBeNull();
    });
});

describe('pondera', () => {
    it('el texto de la nota bajo el medidor general', () => {
        expect(NOTA_PONDERADO).toBe('El porcentaje pondera cada requisito por su peso.');
    });

    it('sí si algún requisito que cuenta pesa distinto de 1', () => {
        expect(pondera([fila(1, null, { peso: 1 }), fila(2, null, { peso: 3 })])).toBe(true);
    });

    it('no si todos los que cuentan pesan 1, aunque uno que no cuenta pese más', () => {
        expect(pondera([fila(1, null, { peso: 1 }), fila(2, null, { peso: 3, cuenta: false })])).toBe(false);
    });

    it('no en viabilización (sus filas no traen peso) ni sin filas', () => {
        expect(pondera([fila(1, null), fila(2, null)])).toBe(false);
        expect(pondera([])).toBe(false);
        expect(pondera(undefined)).toBe(false);
    });
});

// ---------- Revisión por etapa (fase 5) ----------

const revisionApi = (cambios = {}) => ({
    estado: 'pendiente', usuario: null, fecha: null, observacion: null, pendientes_al_aprobar: null, devuelta_at: null, ...cambios,
});

const avanceRevision = (id, cambios = {}) => avanceApi(id, { revision: id === null ? null : revisionApi(), aviso_etapa_anterior: false, ...cambios });

describe('fechaMomento', () => {
    it('un momento ISO en UTC como día/mes/año de Bogotá', () => {
        expect(fechaMomento('2026-11-19T15:30:00+00:00')).toBe('19/11/2026');
        expect(fechaMomento('2026-11-20T02:00:00+00:00')).toBe('19/11/2026');
    });

    it('sin momento o uno inválido, null', () => {
        expect(fechaMomento(null)).toBeNull();
        expect(fechaMomento('no es fecha')).toBeNull();
    });
});

describe('revisionEtapa', () => {
    it('aprobada: etiqueta, quién y cuándo, y la observación', () => {
        const r = revisionEtapa(avanceRevision(1, {
            revision: revisionApi({ estado: 'aprobada', usuario: 'Gestora', fecha: '2026-11-19T15:30:00+00:00', observacion: 'Revisado con jurídica.', pendientes_al_aprobar: 0 }),
        }));
        expect(r).toEqual({
            estado: 'aprobada',
            etiqueta: { texto: 'Aprobada', tono: 'verde' },
            detalle: 'Aprobada por Gestora el 19/11/2026',
            observacion: 'Revisado con jurídica.',
            nota: null,
        });
    });

    it('aprobada con requisitos pendientes: lo dice el detalle', () => {
        const r = revisionEtapa(avanceRevision(1, {
            revision: revisionApi({ estado: 'aprobada', usuario: 'Ana', fecha: '2026-11-19T15:30:00+00:00', pendientes_al_aprobar: 2 }),
        }));
        expect(r.detalle).toBe('Aprobada por Ana el 19/11/2026 con 2 requisitos pendientes');
        const una = revisionEtapa(avanceRevision(1, { revision: revisionApi({ estado: 'aprobada', fecha: '2026-11-19T15:30:00+00:00', pendientes_al_aprobar: 1 }) }));
        expect(una.detalle).toBe('Aprobada el 19/11/2026 con 1 requisito pendiente');
    });

    it('aprobada con cambios: avisa que algo cambió después', () => {
        const r = revisionEtapa(avanceRevision(1, { revision: revisionApi({ estado: 'aprobada_con_cambios', usuario: 'Ana', fecha: '2026-11-19T15:30:00+00:00', pendientes_al_aprobar: 0 }) }));
        expect(r.etiqueta).toEqual({ texto: 'Aprobada con cambios', tono: 'ambar' });
        expect(r.detalle).toBe('Aprobada por Ana el 19/11/2026');
        expect(r.nota).toBe('Algún requisito de la etapa cambió después de aprobarla.');
    });

    it('devuelta vigente: quién, cuándo y por qué', () => {
        const r = revisionEtapa(avanceRevision(1, {
            revision: revisionApi({ estado: 'devuelta', usuario: 'Ana', fecha: '2026-11-19T15:30:00+00:00', devuelta_at: '2026-11-19T15:30:00+00:00', observacion: 'Falta el CDP.' }),
        }));
        expect(r).toEqual({
            estado: 'devuelta', etiqueta: { texto: 'Devuelta', tono: 'coral' }, detalle: 'Devuelta por Ana el 19/11/2026', observacion: 'Falta el CDP.', nota: null,
        });
    });

    it('devuelta y trabajada después: pendiente o lista, conservando la devolución', () => {
        const r = revisionEtapa(avanceRevision(1, {
            revision: revisionApi({ estado: 'lista', usuario: 'Ana', fecha: '2026-11-19T15:30:00+00:00', devuelta_at: '2026-11-19T15:30:00+00:00', observacion: 'Falta el CDP.' }),
        }));
        expect(r.etiqueta).toEqual({ texto: 'Lista para revisar', tono: 'azul' });
        expect(r.detalle).toBe('Devuelta por Ana el 19/11/2026');
        expect(r.observacion).toBe('Falta el CDP.');
        expect(r.nota).toBe('Tuvo cambios después de la devolución.');
    });

    it('sin revisión: solo la etiqueta', () => {
        expect(revisionEtapa(avanceRevision(1))).toEqual({
            estado: 'pendiente', etiqueta: { texto: 'Pendiente', tono: 'neutro' }, detalle: null, observacion: null, nota: null,
        });
    });

    it('el grupo sin etapa o una respuesta sin revisión: null', () => {
        expect(revisionEtapa(avanceRevision(null))).toBeNull();
        expect(revisionEtapa(avanceApi(1))).toBeNull();
        expect(revisionEtapa(undefined)).toBeNull();
    });
});

describe('etapasConFechas con la revisión', () => {
    it('cada etapa trae su revisión, lo que le falta y el aviso de la etapa anterior', () => {
        const lista = etapasConFechas([
            avanceRevision(1, { total: 3, cumplidos: 1 }),
            avanceRevision(2, { aviso_etapa_anterior: true }),
            avanceRevision(null),
        ]);
        expect(lista[0]).toMatchObject({ faltan: 2, avisoEtapaAnterior: false, revision: { estado: 'pendiente' } });
        expect(lista[1].avisoEtapaAnterior).toBe(true);
        expect(lista[2]).toMatchObject({ faltan: 1, revision: null, avisoEtapaAnterior: false });
    });
});

describe('accionesRevision', () => {
    const conEstado = (estado) => etapasConFechas([avanceRevision(1, { revision: revisionApi({ estado }) })])[0];

    it('sin aprobar: aprobar y devolver', () => {
        ['pendiente', 'lista', 'devuelta'].forEach((estado) => {
            expect(accionesRevision(conEstado(estado), true)).toEqual({ aprobar: true, reaprobar: false, devolver: true });
        });
    });

    it('aprobada sin cambios: solo devolver', () => {
        expect(accionesRevision(conEstado('aprobada'), true)).toEqual({ aprobar: false, reaprobar: false, devolver: true });
    });

    it('aprobada con cambios: aprobar de nuevo y devolver', () => {
        expect(accionesRevision(conEstado('aprobada_con_cambios'), true)).toEqual({ aprobar: false, reaprobar: true, devolver: true });
    });

    it('sin requisitos que cuenten: ningún botón', () => {
        expect(accionesRevision(conEstado('sin_requisitos'), true)).toEqual({ aprobar: false, reaprobar: false, devolver: false });
    });

    it('solo lectura o grupo sin etapa: ninguna', () => {
        expect(accionesRevision(conEstado('lista'), false)).toEqual({ aprobar: false, reaprobar: false, devolver: false });
        expect(accionesRevision(etapasConFechas([avanceRevision(null)])[0], true)).toEqual({ aprobar: false, reaprobar: false, devolver: false });
        expect(accionesRevision(undefined, true)).toEqual({ aprobar: false, reaprobar: false, devolver: false });
    });
});

describe('aprobar una etapa', () => {
    it('el diálogo sin pendientes', () => {
        expect(dialogoAprobarEtapa('1. Planeación', 0)).toEqual({
            icono: 'question',
            titulo: '¿Aprobar la etapa «1. Planeación»?',
            texto: 'La aprobación queda en el historial del expediente con tu nombre y la fecha.',
            boton: 'Aprobar etapa',
        });
    });

    it('el diálogo con pendientes dice cuántos', () => {
        expect(dialogoAprobarEtapa('1. Planeación', 2)).toEqual({
            icono: 'warning',
            titulo: '¿Aprobar la etapa «1. Planeación» con requisitos pendientes?',
            texto: 'Faltan 2 requisitos de esta etapa. Puedes aprobarla de todos modos: la revisión solo avisa.',
            boton: 'Aprobar de todos modos',
        });
        expect(dialogoAprobarEtapa('X', 1).texto).toBe('Falta 1 requisito de esta etapa. Puedes aprobarla de todos modos: la revisión solo avisa.');
    });

    it('el cuerpo: observación sin espacios (o null) y confirmar solo si se confirmó', () => {
        expect(datosAprobarEtapa('  Revisado.  ', false)).toEqual({ observacion: 'Revisado.' });
        expect(datosAprobarEtapa('', true)).toEqual({ observacion: null, confirmar: true });
        expect(datosAprobarEtapa(undefined, false)).toEqual({ observacion: null });
    });

    it('el cuerpo con confirmación envía los pendientes que se mostraron', () => {
        expect(datosAprobarEtapa('Ok', true, 2)).toEqual({ observacion: 'Ok', confirmar: true, pendientes_vistos: 2 });
        expect(datosAprobarEtapa('', true, 0)).toEqual({ observacion: null, confirmar: true, pendientes_vistos: 0 });
        expect(datosAprobarEtapa('', false, 2)).toEqual({ observacion: null });
        expect(datosAprobarEtapa('', true)).toEqual({ observacion: null, confirmar: true });
    });

    it('el diálogo de aprobar de nuevo', () => {
        expect(dialogoAprobarEtapa('1. Planeación', 0, true)).toMatchObject({
            titulo: '¿Aprobar de nuevo la etapa «1. Planeación»?', boton: 'Aprobar de nuevo',
        });
        expect(dialogoAprobarEtapa('1. Planeación', 2, true).boton).toBe('Aprobar de todos modos');
    });

    it('los pendientes del 422 que pide confirmar; cualquier otro error, null', () => {
        const error = (status, data) => ({ response: { status, data } });
        expect(pendientesAlAprobar(error(422, { mensaje: 'Faltan 2 requisitos...', pendientes: 2 }))).toBe(2);
        expect(pendientesAlAprobar(error(422, { mensaje: 'La etapa ya está aprobada.' }))).toBeNull();
        expect(pendientesAlAprobar(error(422, { pendientes: { total: 1 } }))).toBeNull();
        expect(pendientesAlAprobar(error(500, { pendientes: 2 }))).toBeNull();
        expect(pendientesAlAprobar(undefined)).toBeNull();
    });
});

describe('aviso de la etapa anterior', () => {
    const aprobada = revisionApi({ estado: 'aprobada', fecha: '2026-11-19T15:30:00+00:00' });

    it('los ids de las etapas con el aviso', () => {
        const avance = [avanceRevision(1), avanceRevision(2, { aviso_etapa_anterior: true }), avanceRevision(null)];
        expect([...etapasConAvisoAnterior(avance)]).toEqual([2]);
        expect(etapasConAvisoAnterior(undefined).size).toBe(0);
    });

    it('recalcularAvisoAnterior: con la regla de la API (anterior con requisitos sin aprobar y esta con algo cumplido)', () => {
        const avance = [
            avanceRevision(1, { revision: aprobada }),
            avanceRevision(2, { cumplidos: 1, aviso_etapa_anterior: true }),
            avanceRevision(3, { total: 0, cumplidos: 0 }),
            avanceRevision(4, { cumplidos: 1 }),
            avanceRevision(5, { cumplidos: 0, aviso_etapa_anterior: true }),
            avanceRevision(null, { cumplidos: 1 }),
        ];
        expect(recalcularAvisoAnterior(avance).map((a) => a.aviso_etapa_anterior)).toEqual([false, false, false, true, false, false]);
    });

    it('una etapa vacía o aprobada con cambios no cuenta como sin aprobar', () => {
        const avance = [
            avanceRevision(1, { revision: revisionApi({ estado: 'aprobada_con_cambios' }) }),
            avanceRevision(2, { total: 0, cumplidos: 0 }),
            avanceRevision(3, { cumplidos: 1, aviso_etapa_anterior: true }),
        ];
        expect(recalcularAvisoAnterior(avance)[2].aviso_etapa_anterior).toBe(false);
    });

    it('sin las claves de la revisión, no toca nada', () => {
        const avance = [avanceApi(1), avanceApi(2)];
        expect(recalcularAvisoAnterior(avance)).toEqual(avance);
    });

    it('conEtapaActualizada lo recalcula al aprobar o devolver', () => {
        const expediente = { id: 5, avance_por_etapa: [avanceRevision(1), avanceRevision(2, { aviso_etapa_anterior: true })], avisos: {} };
        const nuevo = conEtapaActualizada(expediente, { etapa: avanceRevision(1, { revision: aprobada, alerta: 'aprobada' }), avisos: { etapas_por_revisar: 0 } });
        expect(nuevo.avance_por_etapa[1].aviso_etapa_anterior).toBe(false);
        const devuelto = conEtapaActualizada(nuevo, { etapa: avanceRevision(1, { revision: revisionApi({ estado: 'devuelta', fecha: '2026-11-19T16:00:00+00:00' }) }) });
        expect(devuelto.avance_por_etapa[1].aviso_etapa_anterior).toBe(true);
    });
});

describe('fusionarAvance con una revisión más nueva en pantalla', () => {
    const aprobada = revisionApi({ estado: 'aprobada', usuario: 'Ana', fecha: '2026-11-19T15:30:00+00:00', pendientes_al_aprobar: 0 });
    const actual = [
        avanceRevision(1, { cumplidos: 2, completa: true, fecha_real: '2026-11-19', fecha_real_sugerida: false, alerta: 'aprobada', revision: aprobada }),
        avanceRevision(2, { cumplidos: 1, aviso_etapa_anterior: false }),
    ];

    it('una respuesta que salió antes de aprobar no repone la revisión, el semáforo ni la fecha real', () => {
        const vieja = [
            avanceRevision(1, { cumplidos: 2, completa: true, fecha_real: '2026-11-18', fecha_real_sugerida: true, alerta: 'vencida', revision: revisionApi({ estado: 'lista' }) }),
            avanceRevision(2, { cumplidos: 2, aviso_etapa_anterior: true }),
        ];
        const r = fusionarAvance(actual, vieja);
        expect(r[0]).toMatchObject({ cumplidos: 2, fecha_real: '2026-11-19', fecha_real_sugerida: false, alerta: 'aprobada', revision: aprobada });
        expect(r[1]).toMatchObject({ cumplidos: 2, aviso_etapa_anterior: false });
        expect(hayRevisionAtrasada(actual, vieja)).toBe(true);
    });

    it('una respuesta con la misma revisión (o más nueva) pasa tal cual: puede traer "aprobada con cambios"', () => {
        const nueva = [
            avanceRevision(1, { cumplidos: 1, alerta: 'aprobada', revision: { ...aprobada, estado: 'aprobada_con_cambios' } }),
            avanceRevision(2),
        ];
        expect(fusionarAvance(actual, nueva)).toBe(nueva);
        expect(hayRevisionAtrasada(actual, nueva)).toBe(false);
    });

    it('conAvance conserva los avisos si la respuesta salió antes de la revisión', () => {
        const expediente = { avance_por_etapa: actual, progreso: {}, avisos: { etapas_por_revisar: 0 } };
        const vieja = [avanceRevision(1, { revision: revisionApi({ estado: 'lista' }) }), avanceRevision(2)];
        expect(conAvance(expediente, { avance_por_etapa: vieja, avisos: { etapas_por_revisar: 1 } }).avisos).toEqual({ etapas_por_revisar: 0 });
        expect(conAvance(expediente, { avance_por_etapa: actual, avisos: { etapas_por_revisar: 2 } }).avisos).toEqual({ etapas_por_revisar: 2 });
    });
});

describe('cierre con etapas sin aprobar', () => {
    const sinAprobar = [
        { etapa: etapa(1, 1, 'Planeación'), estado: 'lista' },
        { etapa: etapa(2, 2, 'Selección'), estado: 'devuelta' },
    ];

    it('requisitos y etapas sin aprobar', () => {
        expect(textoPendientesCierre({ total: 1, por_etapa: [{ etapa: etapa(2, 2, 'Selección'), pendientes: 1 }], etapas_sin_aprobar: sinAprobar })).toEqual({
            titulo: 'Falta 1 requisito por cumplir',
            etapas: ['2. Selección: 1 pendiente'],
            sinAprobar: { titulo: 'Hay 2 etapas sin aprobar', etapas: ['1. Planeación: lista para revisar', '2. Selección: devuelta'] },
        });
    });

    it('solo etapas sin aprobar (todo cumplido)', () => {
        expect(textoPendientesCierre({ total: 0, por_etapa: [], etapas_sin_aprobar: [sinAprobar[0]] })).toEqual({
            titulo: null,
            etapas: [],
            sinAprobar: { titulo: 'Hay 1 etapa sin aprobar', etapas: ['1. Planeación: lista para revisar'] },
        });
    });

    it('el html trae las dos listas, escapadas', () => {
        const html = htmlPendientesCierre({ total: 0, por_etapa: [], etapas_sin_aprobar: [{ etapa: etapa(1, 1, '<i>P</i>'), estado: 'pendiente' }] });
        expect(html).toContain('Hay 1 etapa sin aprobar.');
        expect(html).toContain('&lt;i&gt;P&lt;/i&gt;: pendiente');
        expect(html).not.toContain('<i>P</i>');
        expect(html).not.toContain('por cumplir');
        expect(html).toContain('Puedes cerrarlo de todos modos');
    });

    it('el título del diálogo según lo que falte', () => {
        expect(tituloCierrePendientes({ total: 2, etapas_sin_aprobar: sinAprobar })).toBe('¿Cerrar con pendientes?');
        expect(tituloCierrePendientes({ total: 2, etapas_sin_aprobar: [] })).toBe('¿Cerrar con requisitos pendientes?');
        expect(tituloCierrePendientes({ total: 2 })).toBe('¿Cerrar con requisitos pendientes?');
        expect(tituloCierrePendientes({ total: 0, etapas_sin_aprobar: sinAprobar })).toBe('¿Cerrar con etapas sin aprobar?');
    });

    it('cierreSinAvisos: todo cumplido y todas las etapas con requisitos aprobadas', () => {
        const aprobada = revisionApi({ estado: 'aprobada' });
        const exp = (avance, cumplidos = 2) => ({ progreso: { total: 2, cumplidos }, avance_por_etapa: avance });
        expect(cierreSinAvisos(exp([avanceRevision(1, { revision: aprobada }), avanceRevision(2, { total: 0, cumplidos: 0 }), avanceRevision(null)]))).toBe(true);
        expect(cierreSinAvisos(exp([avanceRevision(1, { revision: revisionApi({ estado: 'aprobada_con_cambios' }) })]))).toBe(true);
        expect(cierreSinAvisos(exp([avanceRevision(1, { revision: revisionApi({ estado: 'lista' }) })]))).toBe(false);
        expect(cierreSinAvisos(exp([avanceRevision(1, { revision: aprobada })], 1))).toBe(false);
        expect(cierreSinAvisos(exp([avanceApi(1)]))).toBe(true);
    });
});


describe('sin requisitos que cuenten', () => {
    it('la revisión es neutra', () => {
        const etapa = etapasConFechas([avanceRevision(1, { total: 0, cumplidos: 0, revision: revisionApi({ estado: 'sin_requisitos' }) })])[0];
        expect(etapa.revision.etiqueta).toEqual({ texto: 'Sin requisitos', tono: 'neutro' });
        expect(etapa.revision.nota).toBeNull();
    });
});

describe('aprobarConConfirmacion', () => {
    const error422 = (pendientes) => ({ response: { status: 422, data: { mensaje: 'Faltan', pendientes } } });

    it('sin pendientes: envía sin confirmar y aprueba', async () => {
        const enviar = vi.fn().mockResolvedValue({ etapa: 1 });
        const confirmar = vi.fn();
        expect(await aprobarConConfirmacion({ faltan: 0, enviar, confirmar })).toEqual({ aprobada: true, data: { etapa: 1 } });
        expect(enviar).toHaveBeenCalledWith(false, 0);
        expect(confirmar).not.toHaveBeenCalled();
    });

    it('con pendientes mostrados: confirma y envía los pendientes vistos', async () => {
        const enviar = vi.fn().mockResolvedValue({ ok: true });
        await aprobarConConfirmacion({ faltan: 2, enviar, confirmar: vi.fn() });
        expect(enviar).toHaveBeenCalledWith(true, 2);
    });

    it('si el servidor cuenta más pendientes, vuelve a pedir confirmación con el número nuevo', async () => {
        const enviar = vi.fn()
            .mockRejectedValueOnce(error422(3))
            .mockResolvedValueOnce({ ok: true });
        const confirmar = vi.fn().mockResolvedValue(true);
        const r = await aprobarConConfirmacion({ faltan: 2, enviar, confirmar });
        expect(confirmar).toHaveBeenCalledWith(3);
        expect(enviar).toHaveBeenNthCalledWith(1, true, 2);
        expect(enviar).toHaveBeenNthCalledWith(2, true, 3);
        expect(r).toEqual({ aprobada: true, data: { ok: true } });
    });

    it('puede pedir confirmación más de una vez', async () => {
        const enviar = vi.fn()
            .mockRejectedValueOnce(error422(3))
            .mockRejectedValueOnce(error422(4))
            .mockResolvedValueOnce({ ok: true });
        const confirmar = vi.fn().mockResolvedValue(true);
        await aprobarConConfirmacion({ faltan: 2, enviar, confirmar });
        expect(confirmar.mock.calls.map((c) => c[0])).toEqual([3, 4]);
        expect(enviar).toHaveBeenLastCalledWith(true, 4);
    });

    it('si no confirma el número nuevo, no aprueba', async () => {
        const enviar = vi.fn().mockRejectedValueOnce(error422(1));
        const r = await aprobarConConfirmacion({ faltan: 0, enviar, confirmar: vi.fn().mockResolvedValue(false) });
        expect(r).toEqual({ aprobada: false, data: null });
        expect(enviar).toHaveBeenCalledTimes(1);
    });

    it('cualquier otro error se propaga', async () => {
        const fallo = { response: { status: 500, data: {} } };
        await expect(aprobarConConfirmacion({ faltan: 0, enviar: vi.fn().mockRejectedValue(fallo), confirmar: vi.fn() })).rejects.toBe(fallo);
    });
});

describe('detalle del proceso: contratos', () => {
    const contrato = { id: 31, n_contrato: 'C-001', objeto: 'Construcción', contratista: 'Consorcio Agua', estado: 'en_ejecucion', valor_inicial: '1000000.00', valor: '1200000.00' };

    it('presenta un contrato del proceso con su valor vigente y su enlace', () => {
        expect(filaContratoProceso(contrato)).toMatchObject({
            id: 31,
            numero: 'C-001',
            objeto: 'Construcción',
            contratista: 'Consorcio Agua',
            estado: 'En ejecución',
            tono: 'verde',
            ruta: '/contratos/31',
        });
        expect(filaContratoProceso(contrato).valor).toBe(filaContratoProceso({ ...contrato, valor: null, valor_inicial: '1200000.00' }).valor);
    });

    it('sin valor vigente usa el inicial, y sin ninguno un guion', () => {
        expect(filaContratoProceso({ ...contrato, valor: null }).valor).toMatch(/1\.000\.000/);
        expect(filaContratoProceso({ ...contrato, valor: null, valor_inicial: null }).valor).toBe('—');
        expect(filaContratoProceso({ ...contrato, n_contrato: '' }).numero).toBe('Sin número');
    });

    it('marca los anulados', () => {
        expect(filaContratoProceso(contrato).anulado).toBe(false);
        expect(filaContratoProceso({ ...contrato, estado: 'anulado' }).anulado).toBe(true);
    });

    it('cuenta los contratos', () => {
        expect(textoContratosProceso([])).toBe('Este proceso aún no tiene contratos.');
        expect(textoContratosProceso([contrato])).toBe('1 contrato');
        expect(textoContratosProceso([contrato, contrato])).toBe('2 contratos');
    });

    it('"Registrar contrato": quien edita, si el proceso los admite y tiene proyecto', () => {
        const detalle = { admite_contratos: true, proyecto: { id: 4, nombre: 'Acueducto' } };
        expect(puedeRegistrarDesdeDetalle({ rol: 'Gestor' }, detalle)).toBe(true);
        expect(puedeRegistrarDesdeDetalle({ rol: 'Administrador' }, detalle)).toBe(true);
        expect(puedeRegistrarDesdeDetalle({ rol: 'Consulta' }, detalle)).toBe(false);
        expect(puedeRegistrarDesdeDetalle({ rol: 'Gestor' }, { ...detalle, admite_contratos: false })).toBe(false);
        expect(puedeRegistrarDesdeDetalle({ rol: 'Gestor' }, { ...detalle, proyecto: null })).toBe(false);
        expect(puedeRegistrarDesdeDetalle({ rol: 'Gestor' }, null)).toBe(false);
    });
});

describe('detalle del proceso: enlace a la plataforma', () => {
    it('con un enlace web, lo ofrece con el nombre de la plataforma', () => {
        expect(enlaceProceso({ url_proceso: 'https://community.secop.gov.co/x', plataforma: 'secop_ii' }))
            .toEqual({ enlace: 'https://community.secop.gov.co/x', texto: 'Ver el proceso en SECOP II' });
        expect(enlaceProceso({ url_proceso: ' https://tienda.gov.co/y ', plataforma: 'tvec' }).texto).toBe('Ver el proceso en Tienda Virtual');
        expect(enlaceProceso({ url_proceso: 'https://x.gov.co', plataforma: 'ninguna' }).texto).toBe('Ver el proceso');
    });

    it('sin enlace, o con uno que no es web, ninguno', () => {
        expect(enlaceProceso({ url_proceso: null, plataforma: 'secop_ii' })).toBeNull();
        expect(enlaceProceso({ url_proceso: 'javascript:alert(1)', plataforma: 'secop_ii' })).toBeNull();
        expect(enlaceProceso(null)).toBeNull();
    });
});

describe('formularios del detalle del proceso', () => {
    const v1 = { id: 7, estado: 'preparacion', actualizado: '2026-11-19T10:00:00.000000Z' };
    const v2 = { ...v1, estado: 'publicado', actualizado: '2026-11-19T10:05:00.000000Z' };

    it('congela el proceso al abrir: tras un 409 el detalle se recarga, pero el siguiente intento sale con el actualizado de cuando se abrió', () => {
        const modo = abrirFormularioDetalle('editar', v1);
        // El detalle recargado (v2) no cambia lo que se guarda: otra vez 409 en lugar de pisar el cambio ajeno.
        const detalleRecargado = { proceso: v2 };
        expect(procesoDelFormulario(modo, 'editar')).toBe(v1);
        expect(procesoDelFormulario(modo, 'editar')).not.toBe(detalleRecargado.proceso);
        expect(procesoDelFormulario(abrirFormularioDetalle('estado', v1), 'estado').actualizado).toBe(v1.actualizado);
    });

    it('solo da el proceso del formulario que está abierto', () => {
        expect(procesoDelFormulario(abrirFormularioDetalle('estado', v1), 'editar')).toBeNull();
        expect(procesoDelFormulario(null, 'editar')).toBeNull();
        expect(abrirFormularioDetalle('contrato', v1)).toEqual({ tipo: 'contrato', proceso: v1 });
    });

    it('las fuentes del proyecto del detalle, para agrupar "Fuentes del proyecto"', () => {
        expect(fuentesDelDetalle({ proyecto: { id: 4, nombre: 'P', fuente_ids: [2, 5] } })).toEqual([2, 5]);
        expect(fuentesDelDetalle({ proyecto: { id: 4, nombre: 'P' } })).toEqual([]);
        expect(fuentesDelDetalle({ proyecto: null })).toEqual([]);
        expect(fuentesDelDetalle(null)).toEqual([]);
    });
});
