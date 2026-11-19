import { describe, it, expect } from 'vitest';
import {
    FILTROS_VACIOS,
    SEMAFOROS,
    alternarFiltro,
    consultaConFiltros,
    etapasActuales,
    etiquetaSemaforo,
    filaProceso,
    filtrosDeConsulta,
    filtrosFueraDeOpciones,
    hayFiltros,
    opcionesFiltros,
    parametrosApi,
    tarjetasEstado,
    tarjetasSemaforo,
    textoListaVacia,
} from './contratacionPanel';
// El monto sale con el formato de moneda de los procesos (textoValor).
import { textoValor as textoMonto } from './contratacion';

const consulta = (texto) => new URLSearchParams(texto);

const resumen = {
    total: 3,
    por_estado: { preparacion: 2, publicado: 1, evaluacion: 0, adjudicado: 0, desierto: 0, celebrado: 0, cancelado: 0, revocado: 0 },
    por_semaforo: { vencida: 1, proxima: 0, al_dia: 1, sin_expediente: 1, terminado: 0 },
    por_etapa: [
        { id: 1, nombre: 'Planeación', orden: 1, procesos: 1 },
        { id: 2, nombre: 'Selección', orden: 2, procesos: 1 },
    ],
};

const opciones = {
    modalidades: [{ id: 1, nombre: 'Licitación pública' }, { id: 5, nombre: 'Mínima cuantía' }],
    plataformas: ['secop_ii', 'ninguna'],
    fuentes: [{ id: 3, nombre: 'SGR' }],
    responsables: [{ id: 2, nombre: 'Ana Pérez' }],
};

const proceso = {
    id: 7,
    codigo_proceso: 'LP-001-2026',
    url_proceso: null,
    objeto: 'Construcción del acueducto',
    modalidad: { id: 1, nombre: 'Licitación pública' },
    plataforma: 'secop_ii',
    fuente: { id: 3, nombre: 'SGR' },
    responsable: { id: 2, nombre: 'Ana Pérez' },
    monto: '1500000.00',
    estado: 'preparacion',
    fecha_cierre: '2026-11-21',
    proyecto: { id: 4, nombre: 'Acueducto veredal' },
    expediente: { id: 12, estado: 'borrador', progreso: { total: 4, cumplidos: 1, porcentaje: 25 }, aviso_plantilla: null },
    avisos: { etapas_vencidas: 1 },
    etapa_actual: { id: 1, nombre: 'Planeación', orden: 1, estado_revision: 'devuelta' },
    semaforo: 'vencida',
};

describe('semáforo', () => {
    it('tiene los cinco valores de la API, en su orden', () => {
        expect(SEMAFOROS.map(([v]) => v)).toEqual(['vencida', 'proxima', 'al_dia', 'sin_expediente', 'terminado']);
    });

    it('da la etiqueta y el tono; un valor desconocido no inventa uno', () => {
        expect(etiquetaSemaforo('vencida')).toEqual({ valor: 'vencida', texto: 'Vencida', tono: 'coral' });
        expect(etiquetaSemaforo('al_dia')).toMatchObject({ texto: 'Al día', tono: 'verde' });
        expect(etiquetaSemaforo('otro')).toBeNull();
    });
});

describe('filtros en la URL', () => {
    it('sin parámetros, ningún filtro', () => {
        expect(filtrosDeConsulta(consulta(''))).toEqual(FILTROS_VACIOS);
        expect(hayFiltros(FILTROS_VACIOS)).toBe(false);
    });

    it('lee los válidos y descarta los que la API rechazaría', () => {
        const filtros = filtrosDeConsulta(consulta('vista=contratacion&modalidad_id=5&plataforma=secop_ii&fuente_id=abc&responsable_id=0&estado=publicado&semaforo=roja'));
        expect(filtros).toEqual({ ...FILTROS_VACIOS, modalidad_id: '5', plataforma: 'secop_ii', estado: 'publicado' });
        expect(hayFiltros(filtros)).toBe(true);
    });

    it('a la API solo van los filtros con valor', () => {
        expect(parametrosApi({ ...FILTROS_VACIOS, semaforo: 'vencida', fuente_id: '3' })).toEqual({ fuente_id: '3', semaforo: 'vencida' });
        expect(parametrosApi(FILTROS_VACIOS)).toEqual({});
    });

    it('al escribirlos en la URL conserva la vista y quita los vacíos', () => {
        const siguiente = consultaConFiltros(consulta('vista=contratacion&estado=publicado'), { ...FILTROS_VACIOS, semaforo: 'proxima' });
        expect(siguiente.toString()).toBe('vista=contratacion&semaforo=proxima');
    });

    it('alterna el filtro de una tarjeta: elegirla filtra, volver a elegirla lo quita', () => {
        const con = alternarFiltro(FILTROS_VACIOS, 'semaforo', 'vencida');
        expect(con.semaforo).toBe('vencida');
        expect(alternarFiltro(con, 'semaforo', 'vencida').semaforo).toBe('');
        expect(alternarFiltro(con, 'semaforo', '').semaforo).toBe('');
    });

    it('señala los filtros cuyo valor ya no está entre las opciones del servidor', () => {
        const filtros = { ...FILTROS_VACIOS, modalidad_id: '99', plataforma: 'tvec', fuente_id: '3', responsable_id: '2', estado: 'adjudicado' };
        expect(filtrosFueraDeOpciones(filtros, opciones)).toEqual(['modalidad_id', 'plataforma']);
        expect(filtrosFueraDeOpciones(FILTROS_VACIOS, opciones)).toEqual([]);
        expect(filtrosFueraDeOpciones(filtros, null)).toEqual([]);
    });
});

describe('opcionesFiltros', () => {
    it('arma las opciones de cada select desde las del servidor', () => {
        const o = opcionesFiltros(opciones);
        expect(o.modalidad_id).toEqual([{ valor: '1', etiqueta: 'Licitación pública' }, { valor: '5', etiqueta: 'Mínima cuantía' }]);
        expect(o.plataforma).toEqual([{ valor: 'secop_ii', etiqueta: 'SECOP II' }, { valor: 'ninguna', etiqueta: 'Sin plataforma' }]);
        expect(o.fuente_id).toEqual([{ valor: '3', etiqueta: 'SGR' }]);
        expect(o.responsable_id).toEqual([{ valor: '2', etiqueta: 'Ana Pérez' }]);
        expect(o.estado).toHaveLength(8);
        expect(o.estado[0]).toEqual({ valor: 'preparacion', etiqueta: 'En preparación' });
        expect(o.semaforo.map((s) => s.valor)).toEqual(['vencida', 'proxima', 'al_dia', 'sin_expediente', 'terminado']);
    });

    it('sin opciones, listas vacías (y los estados y semáforos de siempre)', () => {
        const o = opcionesFiltros(null);
        expect(o.modalidad_id).toEqual([]);
        expect(o.plataforma).toEqual([]);
        expect(o.estado).toHaveLength(8);
    });
});

describe('tarjetas', () => {
    it('por semáforo: las cinco, con su cantidad y la activa marcada', () => {
        const tarjetas = tarjetasSemaforo(resumen, 'vencida');
        expect(tarjetas.map((t) => [t.valor, t.cantidad, t.activa])).toEqual([
            ['vencida', 1, true], ['proxima', 0, false], ['al_dia', 1, false], ['sin_expediente', 1, false], ['terminado', 0, false],
        ]);
        expect(tarjetas[0]).toMatchObject({ etiqueta: 'Vencida', tono: 'coral' });
    });

    it('por estado: solo los que tienen procesos, y el filtrado aunque quede en cero', () => {
        expect(tarjetasEstado(resumen, '').map((t) => [t.valor, t.cantidad])).toEqual([['preparacion', 2], ['publicado', 1]]);
        expect(tarjetasEstado(resumen, 'celebrado').map((t) => t.valor)).toEqual(['preparacion', 'publicado', 'celebrado']);
        expect(tarjetasEstado(resumen, 'publicado')[1]).toMatchObject({ etiqueta: 'Publicado', activa: true });
    });

    it('sin resumen, todo en cero', () => {
        expect(tarjetasSemaforo(null, '').every((t) => t.cantidad === 0)).toBe(true);
        expect(tarjetasEstado(null, '')).toEqual([]);
    });
});

describe('etapasActuales', () => {
    it('cuenta los procesos de cada etapa actual y los que no tienen etapa actual', () => {
        const { etapas, sinEtapa } = etapasActuales(resumen);
        expect(etapas).toEqual([
            { id: 1, nombre: 'Planeación', orden: 1, procesos: 1, porcentaje: 33 },
            { id: 2, nombre: 'Selección', orden: 2, procesos: 1, porcentaje: 33 },
        ]);
        expect(sinEtapa).toBe(1);
    });

    it('sin procesos no divide por cero', () => {
        expect(etapasActuales({ total: 0, por_etapa: [] })).toEqual({ etapas: [], sinEtapa: 0 });
        expect(etapasActuales(null)).toEqual({ etapas: [], sinEtapa: 0 });
    });
});

describe('filaProceso', () => {
    it('presenta un proceso de la API para la tabla', () => {
        expect(filaProceso(proceso)).toEqual({
            id: 7,
            numero: { texto: 'LP-001-2026', enlace: null },
            objeto: 'Construcción del acueducto',
            proyecto: { id: 4, nombre: 'Acueducto veredal', ruta: '/proyectos/4' },
            modalidad: 'Licitación pública',
            monto: textoMonto('1500000'),
            estado: { texto: 'En preparación', tono: 'neutro' },
            etapa: { texto: 'Planeación', revision: { texto: 'Devuelta', tono: 'coral' } },
            avance: 25,
            cierre: '21/11/2026',
            semaforo: { valor: 'vencida', texto: 'Vencida', tono: 'coral' },
            avisos: { etapas_vencidas: 1 },
            rutaChecklist: '/contratacion/7',
        });
    });

    it('sin etapa actual explica por qué', () => {
        expect(filaProceso({ ...proceso, expediente: null, etapa_actual: null }).etapa).toEqual({ texto: 'Sin expediente', revision: null });
        expect(filaProceso({ ...proceso, expediente: { ...proceso.expediente, estado: 'cerrado' }, etapa_actual: null }).etapa.texto).toBe('Expediente cerrado');
        expect(filaProceso({ ...proceso, etapa_actual: null }).etapa.texto).toBe('Todas aprobadas');
    });

    it('un expediente abierto sin requisitos que cuenten no tiene etapas aprobadas: "Sin requisitos"', () => {
        const vacio = { ...proceso.expediente, estado: 'borrador', progreso: { ...proceso.expediente.progreso, total: 0, cumplidos: 0, porcentaje: 0 } };
        expect(filaProceso({ ...proceso, expediente: vacio, etapa_actual: null }).etapa).toEqual({ texto: 'Sin requisitos', revision: null });
        expect(filaProceso({ ...proceso, expediente: { ...vacio, progreso: null }, etapa_actual: null }).etapa.texto).toBe('Sin requisitos');
        // Cerrado sigue diciendo que está cerrado, tenga o no requisitos.
        expect(filaProceso({ ...proceso, expediente: { ...vacio, estado: 'cerrado' }, etapa_actual: null }).etapa.texto).toBe('Expediente cerrado');
    });

    it('una revisión pendiente no se anuncia: es lo normal de la etapa actual', () => {
        expect(filaProceso({ ...proceso, etapa_actual: { ...proceso.etapa_actual, estado_revision: 'pendiente' } }).etapa.revision).toBeNull();
        expect(filaProceso({ ...proceso, etapa_actual: { ...proceso.etapa_actual, estado_revision: 'lista' } }).etapa.revision.texto).toBe('Lista para revisar');
    });

    it('sin expediente no hay avance, y sin proyecto ni datos usa guiones', () => {
        const fila = filaProceso({ ...proceso, expediente: null, proyecto: null, modalidad: null, monto: null, fecha_cierre: null, objeto: '', codigo_proceso: null });
        expect(fila).toMatchObject({ avance: null, proyecto: null, modalidad: '—', monto: '—', cierre: '—', objeto: 'Sin objeto', numero: { texto: '—' } });
    });
});

describe('textoListaVacia', () => {
    it('distingue no tener procesos de que ninguno coincida', () => {
        expect(textoListaVacia(false)).toBe('Aún no hay procesos de contratación registrados.');
        expect(textoListaVacia(true)).toBe('Ningún proceso coincide con los filtros elegidos.');
    });
});
