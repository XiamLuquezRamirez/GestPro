import { describe, it, expect } from 'vitest';
import {
    datosGenerales, errorDeFicha, esIdProyecto, filaProceso, resumenFormulacion, textoFuentes, textoSector,
} from './fichaProyecto';

// Intl separa el símbolo con un espacio duro: se compara con espacios normales.
const normal = (texto) => texto.replace(/\s/g, ' ');

const proyecto = {
    id: 7,
    nombre: 'Acueducto veredal',
    descripcion: 'Construcción del acueducto',
    descripcion_municipio: 'BECERRIL',
    descripcion_fase: 'Ejecución',
    descripcion_estado: 'Activo',
    descripcion_entidad_presenta: 'Alcaldía',
    descripcion_entidad_financia: null,
    fecha_inicio: '2026-01-15',
    presupuesto: 5000000,
    totalPresupuesto: 3500000,
    sector: 2,
    fuente_ids: [1, 3],
    financiacion_por_definir: 0,
    fuente_financiacion: 'SGR y propios',
};
const fuentes = [{ id: 1, nombre: 'SGR' }, { id: 2, nombre: 'Crédito' }, { id: 3, nombre: 'Recursos propios' }];
const sectores = [{ id: 2, nombre: 'Agua potable' }];

describe('esIdProyecto', () => {
    it('acepta un entero positivo de hasta 18 cifras', () => {
        expect(esIdProyecto('7')).toBe(true);
        expect(esIdProyecto('123456789012345')).toBe(true);
    });

    it('rechaza lo que no es un id, sin llamar a la API', () => {
        ['', '0', '07', 'abc', '7a', '-1', '1.5', '99999999999999999999', undefined, null].forEach((valor) => {
            expect(esIdProyecto(valor)).toBe(false);
        });
    });
});

describe('errorDeFicha', () => {
    it('un 404 es un proyecto que no existe y no se reintenta', () => {
        expect(errorDeFicha({ response: { status: 404 } })).toEqual({ mensaje: 'El proyecto no existe.', reintentable: false });
    });

    it('los demás errores se pueden reintentar', () => {
        expect(errorDeFicha({ response: { status: 500 } })).toEqual({ mensaje: 'Error del servidor. Inténtalo de nuevo.', reintentable: true });
        expect(errorDeFicha({ request: {} })).toEqual({ mensaje: 'No hay conexión con el servidor.', reintentable: true });
    });
});

describe('textoFuentes', () => {
    it('nombra las fuentes del proyecto con el catálogo', () => {
        expect(textoFuentes(proyecto, fuentes)).toBe('SGR, Recursos propios');
    });

    it('por definir, sin fuentes o sin catálogo', () => {
        expect(textoFuentes({ ...proyecto, financiacion_por_definir: 1, fuente_ids: [] }, fuentes)).toBe('Por definir');
        expect(textoFuentes({ ...proyecto, fuente_ids: [] }, fuentes)).toBe('—');
        // Sin el catálogo (cargando o falló) se usa el texto que el proyecto ya tiene.
        expect(textoFuentes(proyecto, null)).toBe('SGR y propios');
        expect(textoFuentes({ ...proyecto, fuente_financiacion: '' }, null)).toBe('2 fuentes');
        expect(textoFuentes({ ...proyecto, fuente_ids: [1], fuente_financiacion: null }, null)).toBe('1 fuente');
    });

    it('una fuente que el catálogo no trae no se inventa', () => {
        expect(textoFuentes({ ...proyecto, fuente_ids: [1, 9] }, fuentes)).toBe('SGR, Fuente 9');
    });
});

describe('textoSector', () => {
    it('el nombre del sector del catálogo', () => {
        expect(textoSector(proyecto, sectores)).toBe('Agua potable');
        expect(textoSector({ ...proyecto, sector: '2' }, sectores)).toBe('Agua potable');
    });

    it('sin sector, sin catálogo o fuera de él, una raya', () => {
        expect(textoSector({ ...proyecto, sector: null }, sectores)).toBe('—');
        expect(textoSector(proyecto, null)).toBe('—');
        expect(textoSector({ ...proyecto, sector: 9 }, sectores)).toBe('—');
    });
});

describe('datosGenerales', () => {
    it('los datos del bloque General, en orden', () => {
        const datos = datosGenerales(proyecto, { fuentes, sectores });
        expect(datos.map((d) => d.etiqueta)).toEqual([
            'Municipio', 'Fase', 'Estado', 'Sector', 'Fuentes de financiación', 'Presupuesto', 'Fecha de inicio',
            'Entidad que presenta', 'Entidad que financia', 'Descripción',
        ]);
        const valor = (etiqueta) => datos.find((d) => d.etiqueta === etiqueta).valor;
        expect(valor('Municipio')).toBe('BECERRIL');
        expect(valor('Sector')).toBe('Agua potable');
        expect(valor('Fuentes de financiación')).toBe('SGR, Recursos propios');
        expect(normal(valor('Presupuesto'))).toBe('$ 3.500.000');
        expect(valor('Fecha de inicio')).toBe('15/01/2026');
        expect(valor('Entidad que financia')).toBe('—');
        expect(datos.find((d) => d.etiqueta === 'Descripción').ancho).toBe(true);
    });

    it('sin componentes, el presupuesto es el del proyecto', () => {
        const datos = datosGenerales({ ...proyecto, totalPresupuesto: 0 }, { fuentes, sectores });
        expect(normal(datos.find((d) => d.etiqueta === 'Presupuesto').valor)).toBe('$ 5.000.000');
    });

    it('sin datos, rayas', () => {
        const datos = datosGenerales({ id: 1, nombre: 'Vacío', fuente_ids: [] }, {});
        expect(datos.filter((d) => d.etiqueta !== 'Descripción').every((d) => d.valor === '—')).toBe(true);
    });
});

describe('resumenFormulacion', () => {
    const expediente = {
        proyecto_id: 7,
        cantidad_expedientes: 1,
        expediente_id: 12,
        estado: 'borrador',
        fase_sgr: 3,
        configurado: true,
        plantillas: ['SGR', 'Obras de agua'],
        tiene_sgr: true,
        fecha_radicado: null,
        actualizado: '2026-11-10T10:00:00-05:00',
        progreso: { total: 20, cumplidos: 9, porcentaje: 45 },
        responsables: [],
    };

    it('sin expediente', () => {
        expect(resumenFormulacion(null, 7)).toEqual({ tiene: false, texto: 'Sin expediente' });
    });

    it('un expediente en borrador: su avance propio y el enlace', () => {
        expect(resumenFormulacion(expediente, 7)).toEqual({
            tiene: true,
            estado: 'Borrador',
            porcentaje: 45,
            avance: '45% (9 de 20 requisitos cumplidos)',
            plantillas: 'SGR, Obras de agua',
            faseSgr: 'III — Factibilidad',
            radicado: null,
            otros: null,
            ruta: '/viabilizacion/7',
        });
    });

    it('sin configurar no hay avance que medir', () => {
        const r = resumenFormulacion({ ...expediente, configurado: false }, 7);
        expect(r.estado).toBe('Sin configurar');
        expect(r.porcentaje).toBeNull();
        expect(r.avance).toBe('Sin configurar: aún no hay requisitos que medir');
    });

    it('radicado, sin plantilla SGR y con varios expedientes', () => {
        const r = resumenFormulacion({
            ...expediente, estado: 'radicado', fecha_radicado: '2026-10-01T09:00:00-05:00', tiene_sgr: false, plantillas: [], cantidad_expedientes: 3,
        }, 7);
        expect(r.estado).toBe('Radicado');
        expect(r.radicado).toBe('01/10/2026');
        expect(r.faseSgr).toBeNull();
        expect(r.plantillas).toBe('—');
        expect(r.otros).toBe('El proyecto tiene 3 expedientes: se muestra el más reciente.');
    });
});

describe('filaProceso', () => {
    const proceso = {
        id: 4,
        objeto: 'Obra del acueducto',
        modalidad: { id: 1, nombre: 'Licitación pública', clave: 'licitacion_publica', activo: true },
        codigo_proceso: 'LP-001-2026',
        url_proceso: 'https://community.secop.gov.co/x',
        monto: '1500000.00',
        estado: 'adjudicado',
        contratos: 1,
    };

    it('una fila de la lista de procesos', () => {
        const fila = filaProceso(proceso);
        expect(fila).toMatchObject({
            id: 4,
            numero: 'LP-001-2026',
            enlace: 'https://community.secop.gov.co/x',
            objeto: 'Obra del acueducto',
            modalidad: 'Licitación pública',
            estado: 'Adjudicado',
            tono: 'verde',
            contratos: '1 contrato',
        });
        expect(normal(fila.monto)).toBe('$ 1.500.000');
    });

    it('sin número, modalidad ni monto', () => {
        const fila = filaProceso({ id: 5, objeto: '', modalidad: null, codigo_proceso: null, url_proceso: null, monto: null, estado: 'preparacion', contratos: 0 });
        expect(fila).toMatchObject({ numero: '—', enlace: null, objeto: '—', modalidad: '—', monto: '—', estado: 'En preparación', contratos: 'Sin contratos' });
    });
});
