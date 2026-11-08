import { describe, it, expect } from 'vitest';
import {
    AVISO_SIN_SECTOR,
    alternarPlantilla,
    avisoIncompatibles,
    avisosPlantilla,
    detallePlantilla,
    elegirPrincipal,
    hayCompatibleSgr,
    incluyeSgr,
    opcionPlantilla,
    seleccionParaNuevo,
} from './plantillasExpediente';

/** Plantilla como la devuelve GET /proyectos/{id}/plantillas-compatibles. */
const plantilla = (id, extra = {}) => ({
    id,
    nombre: `Plantilla ${id}`,
    descripcion: null,
    motor: 'simple',
    es_sgr: false,
    activa: true,
    compatible: true,
    fuentes: [],
    sectores: [],
    todas_fuentes: true,
    todos_sectores: true,
    requisitos: 3,
    version: { id: id * 10, numero: 1 },
    en_expediente: null,
    ...extra,
});

const SGR = plantilla(1, { nombre: 'SGR', motor: 'sgr', es_sgr: true, requisitos: 206 });
const COPIA_SGR = plantilla(2, { nombre: 'SGR copia', motor: 'sgr' });
const AGUA = plantilla(5, { nombre: 'Obras de agua' });
const VIAS = plantilla(6, { nombre: 'Vías' });

const base = { fase_sgr: 3, sector_ids: [4], tipo_ids: [], componente_ids: [], tipologia_ids: [] };

describe('incluyeSgr', () => {
    it('mira el motor de las plantillas elegidas', () => {
        expect(incluyeSgr([5, 1], [SGR, AGUA])).toBe(true);
        expect(incluyeSgr([5], [SGR, AGUA])).toBe(false);
        expect(incluyeSgr([], [SGR])).toBe(false);
    });

    it('una copia de la SGR con motor sgr también cuenta', () => {
        expect(incluyeSgr([2], [COPIA_SGR])).toBe(true);
    });

    it('acepta las plantillas del expediente, que traen plantilla_id', () => {
        expect(incluyeSgr([1], [{ plantilla_id: 1, motor: 'sgr' }])).toBe(true);
    });

    it('una plantilla que no está en la lista no cuenta', () => {
        expect(incluyeSgr([9], [SGR])).toBe(false);
        expect(incluyeSgr([1], null)).toBe(false);
    });
});

describe('hayCompatibleSgr', () => {
    it('solo cuenta una plantilla sgr que hoy se ofrece', () => {
        expect(hayCompatibleSgr([AGUA, SGR])).toBe(true);
        expect(hayCompatibleSgr([AGUA, { ...SGR, compatible: false }])).toBe(false);
        expect(hayCompatibleSgr(null)).toBe(false);
    });
});

describe('alternarPlantilla', () => {
    it('la primera que se marca queda como principal', () => {
        expect(alternarPlantilla({ ...base, plantilla_ids: [], principal_id: null }, 5))
            .toEqual({ ...base, plantilla_ids: [5], principal_id: 5 });
    });

    it('marcar otra no cambia la principal', () => {
        expect(alternarPlantilla({ ...base, plantilla_ids: [1], principal_id: 1 }, 5))
            .toMatchObject({ plantilla_ids: [1, 5], principal_id: 1 });
    });

    it('quitar la principal pasa la principal a la primera que queda', () => {
        expect(alternarPlantilla({ ...base, plantilla_ids: [1, 5, 6], principal_id: 1 }, 1))
            .toMatchObject({ plantilla_ids: [5, 6], principal_id: 5 });
    });

    it('quitar la última deja la selección sin principal', () => {
        expect(alternarPlantilla({ ...base, plantilla_ids: [5], principal_id: 5 }, 5))
            .toMatchObject({ plantilla_ids: [], principal_id: null });
    });

    it('no toca la configuración SGR', () => {
        expect(alternarPlantilla({ ...base, plantilla_ids: [1], principal_id: 1 }, 1)).toMatchObject(base);
    });
});

describe('elegirPrincipal', () => {
    it('elige una de las marcadas', () => {
        expect(elegirPrincipal({ ...base, plantilla_ids: [1, 5], principal_id: 1 }, 5)).toMatchObject({ principal_id: 5 });
    });

    it('una que no está marcada no puede ser principal', () => {
        const seleccion = { ...base, plantilla_ids: [1], principal_id: 1 };
        expect(elegirPrincipal(seleccion, 5)).toBe(seleccion);
    });
});

describe('opcionPlantilla', () => {
    const enExpediente = [{ plantilla_id: 1, motor: 'sgr' }];

    it('una plantilla compatible se puede marcar', () => {
        expect(opcionPlantilla(AGUA, { plantilla_ids: [] }, [], [AGUA])).toEqual({ marcada: false, deshabilitada: false, motivo: null });
    });

    it('una que ya no se ofrece solo se conserva: no se puede volver a marcar', () => {
        const vieja = { ...AGUA, compatible: false };
        expect(opcionPlantilla(vieja, { plantilla_ids: [] }, [], [vieja]))
            .toEqual({ marcada: false, deshabilitada: true, motivo: 'Ya no se ofrece a este proyecto: solo la conserva un expediente que ya la tenía.' });
        // Mientras el expediente aún la tenga guardada, se puede volver a marcar.
        expect(opcionPlantilla(vieja, { plantilla_ids: [] }, [{ plantilla_id: 5, motor: 'simple' }], [vieja]).deshabilitada).toBe(false);
        // Marcada, siempre se puede quitar.
        expect(opcionPlantilla(vieja, { plantilla_ids: [5] }, [], [vieja])).toMatchObject({ marcada: true, deshabilitada: false });
    });

    it('con una plantilla sgr elegida, otra sgr no se puede marcar', () => {
        expect(opcionPlantilla(COPIA_SGR, { plantilla_ids: [1] }, enExpediente, [SGR, COPIA_SGR]))
            .toEqual({ marcada: false, deshabilitada: true, motivo: 'El expediente ya tiene una plantilla con motor SGR: solo admite una.' });
        expect(opcionPlantilla(SGR, { plantilla_ids: [1] }, enExpediente, [SGR, COPIA_SGR]).deshabilitada).toBe(false);
    });

    it('la otra plantilla sgr se reconoce solo en las disponibles o solo en el expediente', () => {
        const esperado = { marcada: false, deshabilitada: true, motivo: 'El expediente ya tiene una plantilla con motor SGR: solo admite una.' };
        // Solo en disponibles: el expediente no la trae.
        expect(opcionPlantilla(COPIA_SGR, { plantilla_ids: [1] }, [], [SGR, COPIA_SGR])).toEqual(esperado);
        // Solo en el expediente: ya no está entre las disponibles.
        expect(opcionPlantilla(COPIA_SGR, { plantilla_ids: [1] }, [{ plantilla_id: 1, motor: 'sgr' }], [COPIA_SGR])).toEqual(esperado);
    });

    it('en un expediente configurado, la última plantilla no se puede quitar', () => {
        const enUno = { plantilla_ids: [5] };
        expect(opcionPlantilla(AGUA, enUno, [], [AGUA], { configurado: true }))
            .toEqual({ marcada: true, deshabilitada: true, motivo: 'Un expediente configurado necesita al menos una plantilla.' });
        expect(opcionPlantilla(AGUA, enUno, [], [AGUA], { configurado: false }).deshabilitada).toBe(false);
        expect(opcionPlantilla(AGUA, { plantilla_ids: [5, 6] }, [], [AGUA, VIAS], { configurado: true }).deshabilitada).toBe(false);
    });

    it('sin selección cargada, todo queda deshabilitado', () => {
        expect(opcionPlantilla(AGUA, null, [], [AGUA])).toMatchObject({ marcada: false, deshabilitada: true });
    });
});

describe('avisosPlantilla', () => {
    it('una compatible y activa no tiene avisos', () => {
        expect(avisosPlantilla(AGUA)).toEqual([]);
    });

    it('avisa si ya no es compatible o si está inactiva', () => {
        expect(avisosPlantilla({ ...AGUA, compatible: false })).toEqual(['Ya no es compatible con el proyecto']);
        expect(avisosPlantilla({ ...AGUA, compatible: false, activa: false })).toEqual(['Inactiva']);
    });

    it('el aviso del proyecto sin sector es el del diseño', () => {
        expect(AVISO_SIN_SECTOR).toBe('El proyecto no tiene sector: solo se ofrecen las plantillas para todos los sectores');
    });
});

describe('detallePlantilla', () => {
    it('resume fuentes, sectores, requisitos y versión', () => {
        const p = plantilla(7, {
            todas_fuentes: false,
            fuentes: [{ id: 1, nombre: 'SGR' }, { id: 2, nombre: 'Recursos propios' }],
            todos_sectores: true,
            requisitos: 1,
            version: { id: 70, numero: 3 },
        });
        expect(detallePlantilla(p)).toEqual({
            fuentes: 'SGR, Recursos propios',
            sectores: 'Todos los sectores',
            requisitos: '1 requisito',
            version: 'v3',
        });
    });

    it('dice qué versión usa el expediente si no es la vigente', () => {
        const p = plantilla(7, { requisitos: 12, version: { id: 71, numero: 2 }, en_expediente: { version: { id: 70, numero: 1 }, es_principal: true } });
        expect(detallePlantilla(p)).toMatchObject({ requisitos: '12 requisitos', version: 'v1 en el expediente · v2 vigente' });
    });

    it('con las plantillas del expediente, la versión sale de ellas y no de la lista, que puede ser vieja', () => {
        const p = plantilla(7, { version: { id: 71, numero: 2 }, en_expediente: { version: { id: 70, numero: 1 }, es_principal: true } });
        // Ya se quitó del expediente: no hay versión propia que mostrar.
        expect(detallePlantilla(p, []).version).toBe('v2');
        expect(detallePlantilla(p, [{ plantilla_id: 7, version: { id: 70, numero: 1 } }]).version).toBe('v1 en el expediente · v2 vigente');
    });

    it('si la versión del expediente es la vigente, solo dice la vigente', () => {
        const p = plantilla(7, { version: { id: 71, numero: 2 }, en_expediente: { version: { id: 71, numero: 2 }, es_principal: true } });
        expect(detallePlantilla(p).version).toBe('v2');
        expect(detallePlantilla(p, [{ plantilla_id: 7, version: { id: 71, numero: 2 } }]).version).toBe('v2');
    });

    it('sin vigente pero con versión en el expediente, dice cuál tiene', () => {
        const p = plantilla(7, { version: null, en_expediente: { version: { id: 70, numero: 1 }, es_principal: true } });
        expect(detallePlantilla(p).version).toBe('v1 en el expediente');
        expect(detallePlantilla(p, [{ plantilla_id: 7, version: { id: 70, numero: 1 } }]).version).toBe('v1 en el expediente');
    });

    it('sin versión vigente lo dice', () => {
        expect(detallePlantilla(plantilla(8, { version: null, todas_fuentes: false, fuentes: [] })))
            .toMatchObject({ fuentes: '—', version: 'Sin versión publicada' });
    });
});

describe('seleccionParaNuevo', () => {
    const seleccion = { ...base, plantilla_ids: [1, 5, 6], principal_id: 1 };

    it('envía solo las plantillas que siguen disponibles y compatibles', () => {
        const disponibles = [SGR, AGUA, { ...VIAS, compatible: false }];
        const { datos, quitadas } = seleccionParaNuevo(seleccion, disponibles);

        expect(datos).toEqual({ ...base, plantilla_ids: [1, 5], principal_id: 1 });
        expect(quitadas).toEqual(['Vías']);
    });

    it('si se quita la principal, pasa a la primera que queda', () => {
        const { datos, quitadas } = seleccionParaNuevo(seleccion, [{ ...SGR, activa: false, compatible: false }, AGUA, VIAS]);
        expect(datos).toMatchObject({ plantilla_ids: [5, 6], principal_id: 5 });
        expect(quitadas).toEqual(['SGR']);
    });

    it('una plantilla sin versión publicada o fuera de la lista también se quita', () => {
        const { datos, quitadas } = seleccionParaNuevo(seleccion, [SGR, { ...AGUA, version: null }], [{ plantilla_id: 6, nombre: 'Vías' }]);
        expect(datos).toMatchObject({ plantilla_ids: [1], principal_id: 1 });
        expect(quitadas).toEqual(['Obras de agua', 'Vías']);
    });

    it('una principal que no va primera sigue siendo la principal', () => {
        const { datos } = seleccionParaNuevo({ ...base, plantilla_ids: [1, 5], principal_id: 5 }, [SGR, AGUA]);
        expect(datos).toMatchObject({ plantilla_ids: [1, 5], principal_id: 5 });
    });

    it('sin nombre conocido, la nombra por su número', () => {
        expect(seleccionParaNuevo({ ...base, plantilla_ids: [9], principal_id: 9 }, []).quitadas).toEqual(['Plantilla #9']);
    });

    it('si se quitan todas, el nuevo nace sin plantillas', () => {
        const { datos } = seleccionParaNuevo({ ...base, plantilla_ids: [5], principal_id: 5 }, []);
        expect(datos).toMatchObject({ plantilla_ids: [], principal_id: null });
    });

    it('sin la lista de disponibles, envía la selección tal cual y el servidor decide', () => {
        expect(seleccionParaNuevo(seleccion, null)).toEqual({ datos: seleccion, quitadas: [] });
    });
});

describe('avisoIncompatibles', () => {
    it('sin plantillas incompatibles no hay aviso', () => {
        expect(avisoIncompatibles([{ nombre: 'SGR', compatible: true }])).toBeNull();
        expect(avisoIncompatibles(undefined)).toBeNull();
    });

    it('con una sola, en singular', () => {
        expect(avisoIncompatibles([{ nombre: 'SGR', compatible: false }, { nombre: 'Obras de agua', compatible: true }])).toEqual({
            titulo: 'Una plantilla ya no es compatible con el proyecto',
            detalle: 'SGR. Este expediente la conserva tal como está; un expediente nuevo no la tendrá.',
        });
    });

    it('con varias, en plural', () => {
        expect(avisoIncompatibles([{ nombre: 'SGR', compatible: false }, { nombre: 'Obras de agua', compatible: false }])).toEqual({
            titulo: 'Algunas plantillas ya no son compatibles con el proyecto',
            detalle: 'SGR, Obras de agua. Este expediente las conserva tal como están; un expediente nuevo no las tendrá.',
        });
    });
});
