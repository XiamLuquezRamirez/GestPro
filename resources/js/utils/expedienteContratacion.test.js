import { describe, it, expect } from 'vitest';
import {
    agruparPorEtapa,
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
