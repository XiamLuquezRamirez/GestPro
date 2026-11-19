import { describe, it, expect } from 'vitest';
import {
    avisoProcesoContrato, chipsAvisosProceso, etiquetaAlerta, franjaAvisosExpediente, textoCierre, textoSinActualizacion,
} from './avisosContratacion';

const avisos = (cambios = {}) => ({
    etapas_vencidas: 0,
    etapas_proximas: 0,
    cierre_proximo: false,
    cierre_vencido: false,
    dias_para_cierre: null,
    sin_actualizacion: false,
    dias_sin_actualizacion: 0,
    hitos: { completadas: 0, total: 0 },
    ...cambios,
});

const textos = (chips) => chips.map((c) => c.texto);

describe('etiquetaAlerta', () => {
    it('el semáforo de cada etapa, con su tono', () => {
        expect(etiquetaAlerta('vencida')).toEqual({ texto: 'Vencida', tono: 'coral' });
        expect(etiquetaAlerta('proxima')).toEqual({ texto: 'Próxima', tono: 'ambar' });
        expect(etiquetaAlerta('al_dia')).toEqual({ texto: 'Al día', tono: 'azul' });
        expect(etiquetaAlerta('completa')).toEqual({ texto: 'Completa', tono: 'verde' });
    });

    it('una etapa que no se completó en un expediente cerrado: "Sin completar", en neutro', () => {
        expect(etiquetaAlerta('sin_completar')).toEqual({ texto: 'Sin completar', tono: 'neutro' });
    });

    it('sin fecha (o un valor desconocido), sin etiqueta', () => {
        expect(etiquetaAlerta('sin_fecha')).toBeNull();
        expect(etiquetaAlerta(undefined)).toBeNull();
        expect(etiquetaAlerta('otra')).toBeNull();
    });
});

describe('textoCierre', () => {
    it('cierre próximo: en N días, en 1 día u hoy', () => {
        expect(textoCierre(avisos({ cierre_proximo: true, dias_para_cierre: 3 }))).toBe('Cierre en 3 días');
        expect(textoCierre(avisos({ cierre_proximo: true, dias_para_cierre: 1 }))).toBe('Cierre en 1 día');
        expect(textoCierre(avisos({ cierre_proximo: true, dias_para_cierre: 0 }))).toBe('Cierre hoy');
    });

    it('cierre vencido', () => {
        expect(textoCierre(avisos({ cierre_vencido: true, dias_para_cierre: -2 }))).toBe('Cierre vencido');
    });

    it('sin aviso de cierre, null (aunque tenga fecha lejana)', () => {
        expect(textoCierre(avisos({ dias_para_cierre: 20 }))).toBeNull();
        expect(textoCierre(undefined)).toBeNull();
    });
});

describe('textoSinActualizacion', () => {
    it('en singular y en plural', () => {
        expect(textoSinActualizacion(1)).toBe('Sin actualización hace 1 día');
        expect(textoSinActualizacion(20)).toBe('Sin actualización hace 20 días');
    });

    it('sin días conocidos, solo "Sin actualización"', () => {
        expect(textoSinActualizacion(null)).toBe('Sin actualización');
    });
});

describe('chipsAvisosProceso', () => {
    it('vencidas, próximas, cierre, sin actualización e hitos, en ese orden', () => {
        const chips = chipsAvisosProceso(avisos({
            etapas_vencidas: 2,
            etapas_proximas: 1,
            cierre_proximo: true,
            dias_para_cierre: 2,
            sin_actualizacion: true,
            dias_sin_actualizacion: 16,
            hitos: { completadas: 1, total: 4 },
        }));
        expect(textos(chips)).toEqual(['2 vencidas', '1 próxima', 'Cierre en 2 días', 'Sin actualización hace 16 días', 'Hitos 1/4']);
        expect(chips.map((c) => c.tono)).toEqual(['coral', 'ambar', 'ambar', 'neutro', 'neutro']);
        expect(chips.map((c) => c.clave)).toEqual(['vencidas', 'proximas', 'cierre', 'sin-actualizacion', 'hitos']);
    });

    it('cada chip explica su cifra con un título', () => {
        const chips = chipsAvisosProceso(avisos({ etapas_vencidas: 1, etapas_proximas: 3, hitos: { completadas: 2, total: 3 } }));
        expect(chips.map((c) => c.titulo)).toEqual([
            '1 etapa del expediente vencida',
            '3 etapas del expediente próximas a vencer',
            '2 de 3 etapas del expediente completas',
        ]);
    });

    it('singular y plural de vencidas y próximas', () => {
        expect(textos(chipsAvisosProceso(avisos({ etapas_vencidas: 1, etapas_proximas: 2 })))).toEqual(['1 vencida', '2 próximas']);
    });

    it('cierre vencido en coral y cierre hoy', () => {
        expect(chipsAvisosProceso(avisos({ cierre_vencido: true, dias_para_cierre: -1 }))).toEqual([
            { clave: 'cierre', texto: 'Cierre vencido', tono: 'coral', titulo: 'La fecha de cierre del proceso ya pasó' },
        ]);
        expect(textos(chipsAvisosProceso(avisos({ cierre_proximo: true, dias_para_cierre: 0 })))).toEqual(['Cierre hoy']);
    });

    it('con todas las etapas completas, los hitos van en verde', () => {
        expect(chipsAvisosProceso(avisos({ hitos: { completadas: 3, total: 3 } }))).toEqual([
            { clave: 'hitos', texto: 'Hitos 3/3', tono: 'verde', titulo: '3 de 3 etapas del expediente completas' },
        ]);
    });

    it('sin nada que avisar (ni hitos que contar), lista vacía', () => {
        expect(chipsAvisosProceso(avisos())).toEqual([]);
        expect(chipsAvisosProceso(avisos({ dias_sin_actualizacion: 30 }))).toEqual([]);
        expect(chipsAvisosProceso(undefined)).toEqual([]);
        expect(chipsAvisosProceso(null)).toEqual([]);
    });
});

describe('franjaAvisosExpediente', () => {
    it('los conteos en frases completas', () => {
        expect(textos(franjaAvisosExpediente(avisos({
            etapas_vencidas: 2,
            etapas_proximas: 1,
            cierre_proximo: true,
            dias_para_cierre: 3,
            sin_actualizacion: true,
            dias_sin_actualizacion: 15,
            hitos: { completadas: 1, total: 4 },
        })))).toEqual([
            '2 etapas vencidas',
            '1 etapa próxima a vencer',
            'El proceso cierra en 3 días',
            'Sin actualización hace 15 días',
        ]);
    });

    it('singular, cierre hoy y cierre vencido', () => {
        expect(textos(franjaAvisosExpediente(avisos({ etapas_vencidas: 1, etapas_proximas: 2, cierre_proximo: true, dias_para_cierre: 0 }))))
            .toEqual(['1 etapa vencida', '2 etapas próximas a vencer', 'El proceso cierra hoy']);
        expect(franjaAvisosExpediente(avisos({ cierre_vencido: true, dias_para_cierre: -4 }))).toEqual([
            { clave: 'cierre', texto: 'La fecha de cierre del proceso ya pasó', tono: 'coral' },
        ]);
        expect(textos(franjaAvisosExpediente(avisos({ cierre_proximo: true, dias_para_cierre: 1 })))).toEqual(['El proceso cierra en 1 día']);
    });

    it('los hitos no son un aviso: sin lo demás, lista vacía', () => {
        expect(franjaAvisosExpediente(avisos({ hitos: { completadas: 1, total: 3 } }))).toEqual([]);
        expect(franjaAvisosExpediente(undefined)).toEqual([]);
    });
});

describe('avisoProcesoContrato', () => {
    const aviso = (cambios = {}) => ({ proceso_id: 7, etapas_vencidas: 0, cierre_vencido: false, sin_actualizacion: false, dias_sin_actualizacion: 0, ...cambios });

    it('etapas vencidas, con el enlace al checklist del proceso', () => {
        expect(avisoProcesoContrato(aviso({ etapas_vencidas: 2 }))).toEqual({
            procesoId: 7,
            ruta: '/contratacion/7',
            textos: ['El proceso tiene 2 etapas vencidas'],
            texto: 'El proceso tiene 2 etapas vencidas',
        });
        expect(avisoProcesoContrato(aviso({ etapas_vencidas: 1 })).texto).toBe('El proceso tiene 1 etapa vencida');
    });

    it('cierre vencido y sin actualización, juntos si los hay', () => {
        expect(avisoProcesoContrato(aviso({ cierre_vencido: true })).texto).toBe('Cierre del proceso vencido');
        expect(avisoProcesoContrato(aviso({ sin_actualizacion: true, dias_sin_actualizacion: 1 })).texto).toBe('Proceso sin actualización hace 1 día');
        expect(avisoProcesoContrato(aviso({ sin_actualizacion: true, dias_sin_actualizacion: null })).texto).toBe('Proceso sin actualización');
        expect(avisoProcesoContrato(aviso({ etapas_vencidas: 1, cierre_vencido: true, sin_actualizacion: true, dias_sin_actualizacion: 20 })).textos).toEqual([
            'El proceso tiene 1 etapa vencida',
            'Cierre del proceso vencido',
            'Proceso sin actualización hace 20 días',
        ]);
        expect(avisoProcesoContrato(aviso({ etapas_vencidas: 1, cierre_vencido: true })).texto)
            .toBe('El proceso tiene 1 etapa vencida · Cierre del proceso vencido');
    });

    it('sin aviso, sin proceso o sin nada que avisar, null', () => {
        expect(avisoProcesoContrato(null)).toBeNull();
        expect(avisoProcesoContrato(undefined)).toBeNull();
        expect(avisoProcesoContrato(aviso())).toBeNull();
        expect(avisoProcesoContrato(aviso({ proceso_id: null, etapas_vencidas: 2 }))).toBeNull();
    });
});
