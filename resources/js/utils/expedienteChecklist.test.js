import { describe, it, expect } from 'vitest';
import {
    agruparRequisitos,
    alternarId,
    alternarSector,
    construirHojasExcel,
    describirEvento,
    mensajeError,
    nombreArchivoExcel,
    puedeMarcarNoAplica,
    puedeMarcarRequisito,
    puedeRadicar,
    puedeRevisarNoAplica,
    responsablesDisponibles,
    sectoresSinTipo,
    seleccionDesde,
    sectoresConFuenteA12,
} from './expedienteChecklist';

const fila = (id, seccion, extra = {}) => ({
    id,
    estado: 'pendiente',
    cuenta: true,
    no_aplica: null,
    responsable: null,
    cumplido: false,
    fecha_cumplido: null,
    usuario_cumplido: null,
    observacion: null,
    soporte: null,
    ...extra.fila,
    requisito: {
        id: id * 10,
        clave: `r${id}`,
        seccion,
        fuente: 'a12',
        sector: null,
        tipo: null,
        subtitulo: null,
        codigo: `c${id}`,
        texto: `Texto ${id}`,
        nota: null,
        ...extra.requisito,
    },
});

const salud = { id: 1, clave: 'salud', nombre: 'Salud', fuente: null };
const justicia = { id: 2, clave: 'justicia', nombre: 'Justicia', fuente: 'Anexo 08 del Acuerdo 12/2024' };

describe('agruparRequisitos', () => {
    it('ordena las secciones como el prototipo: A, B, D, C', () => {
        const secciones = agruparRequisitos([fila(1, 'C', { requisito: { sector: salud } }), fila(2, 'D'), fila(3, 'B'), fila(4, 'A')]);
        expect(secciones.map((s) => s.clave)).toEqual(['A', 'B', 'D', 'C']);
    });

    it('omite las secciones sin requisitos', () => {
        expect(agruparRequisitos([fila(1, 'A')]).map((s) => s.clave)).toEqual(['A']);
        expect(agruparRequisitos([])).toEqual([]);
    });

    it('cuenta total y cumplidos por sección', () => {
        const [a] = agruparRequisitos([fila(1, 'A', { fila: { cumplido: true, estado: 'cumplido' } }), fila(2, 'A')]);
        expect(a.total).toBe(2);
        expect(a.cumplidos).toBe(1);
    });

    it('lo que no cuenta no suma al total ni a los cumplidos de la sección', () => {
        const [a] = agruparRequisitos([
            fila(1, 'A', { fila: { estado: 'no_aplica', cuenta: false } }),
            fila(2, 'A', { fila: { estado: 'cumplido', cumplido: true, cuenta: false } }), // opcional
            fila(3, 'A', { fila: { estado: 'no_aplica_pendiente', cumplido: true } }),
            fila(4, 'A'),
        ]);
        expect(a.total).toBe(2);
        expect(a.cumplidos).toBe(0);
    });

    it('agrupa la sección C por sector y dentro por subtítulo, sin reordenar', () => {
        const [c] = agruparRequisitos([
            fila(1, 'C', { requisito: { sector: justicia, subtitulo: '8.1 Casas' } }),
            fila(2, 'C', { requisito: { sector: justicia, subtitulo: '8.1 Casas' } }),
            fila(3, 'C', { requisito: { sector: justicia, subtitulo: '8.2 Cárceles' } }),
            fila(4, 'C', { requisito: { sector: salud, subtitulo: '7.1 Infra' } }),
        ]);

        expect(c.grupos.map((g) => g.sector.clave)).toEqual(['justicia', 'salud']);
        expect(c.grupos[0].subgrupos.map((s) => s.subtitulo)).toEqual(['8.1 Casas', '8.2 Cárceles']);
        expect(c.grupos[0].subgrupos[0].filas.map((f) => f.id)).toEqual([1, 2]);
    });

    it('las secciones sin sector forman un único grupo', () => {
        const [b] = agruparRequisitos([
            fila(1, 'B', { requisito: { subtitulo: 'Predios' } }),
            fila(2, 'B', { requisito: { subtitulo: 'Licencias' } }),
        ]);
        expect(b.grupos).toHaveLength(1);
        expect(b.grupos[0].sector).toBeNull();
        expect(b.grupos[0].subgrupos.map((s) => s.subtitulo)).toEqual(['Predios', 'Licencias']);
    });
});

describe('seleccion', () => {
    it('seleccionDesde extrae los ids de la configuración del expediente', () => {
        const expediente = {
            fase_sgr: 2,
            configuracion: {
                sectores: [{ id: 1 }],
                tipos: [{ id: 5, sector_id: 1 }],
                componentes: [{ id: 7 }],
                tipologias: [{ id: 9 }],
            },
        };
        expect(seleccionDesde(expediente)).toEqual({
            fase_sgr: 2, sector_ids: [1], tipo_ids: [5], componente_ids: [7], tipologia_ids: [9],
        });
    });

    it('alternarId agrega y quita', () => {
        expect(alternarId([1, 2], 3)).toEqual([1, 2, 3]);
        expect(alternarId([1, 2], 1)).toEqual([2]);
    });

    it('quitar un sector quita también sus tipos marcados y deja los de otros sectores', () => {
        // Sin esto el backend rechazaría la configuración (tipo de sector no seleccionado).
        const catalogo = { sectores: [{ id: 1, tipos: [{ id: 10 }, { id: 11 }] }, { id: 2, tipos: [{ id: 20 }] }] };
        const seleccion = { fase_sgr: 3, sector_ids: [1, 2], tipo_ids: [10, 20], componente_ids: [], tipologia_ids: [] };

        const resultado = alternarSector(seleccion, 1, catalogo);

        expect(resultado.sector_ids).toEqual([2]);
        expect(resultado.tipo_ids).toEqual([20]);
    });

    it('agregar un sector no toca los tipos', () => {
        const catalogo = { sectores: [{ id: 1, tipos: [{ id: 10 }] }, { id: 2, tipos: [] }] };
        const seleccion = { fase_sgr: 3, sector_ids: [1], tipo_ids: [10], componente_ids: [], tipologia_ids: [] };

        const resultado = alternarSector(seleccion, 2, catalogo);

        expect(resultado.sector_ids).toEqual([1, 2]);
        expect(resultado.tipo_ids).toEqual([10]);
    });

    it('sectoresConFuenteA12 lista solo los seleccionados que se rigen por el Acuerdo 12', () => {
        expect(sectoresConFuenteA12([salud, justicia])).toEqual(['Justicia']);
    });
});

describe('mensajeError', () => {
    it('usa el mensaje de una regla de negocio y sus detalles', () => {
        const e = { response: { data: { mensaje: 'Radicado', errores: ['uno', 'dos'] } } };
        expect(mensajeError(e)).toBe('Radicado uno dos');
    });

    it('usa los errores de validación de Laravel', () => {
        const e = { response: { data: { message: 'Inválido', errors: { archivo: ['Tipo no permitido'] } } } };
        expect(mensajeError(e)).toBe('Tipo no permitido');
    });

    it('cae en un mensaje genérico', () => {
        expect(mensajeError(new Error('Network Error'))).toBe('Network Error');
        expect(mensajeError({})).toBe('Ocurrió un error inesperado.');
    });
});

describe('Excel', () => {
    const expediente = {
        id: 7,
        proyecto: { id: 1, nombre: 'Acueducto: veredal/norte' },
        catalogo_version: { numero: 1 },
        fase_sgr: 3,
        estado: 'borrador',
        fecha_radicado: null,
        configuracion: {
            sectores: [salud],
            tipos: [{ id: 5, sector_id: 1, nombre: 'Infraestructura en salud' }],
            componentes: [{ id: 7, nombre: 'Predios' }],
            tipologias: [],
        },
        progreso: { total: 2, cumplidos: 1, porcentaje: 50 },
        requisitos: [
            fila(1, 'A', {
                fila: {
                    estado: 'cumplido',
                    responsable: { id: 3, nombre: 'Luis Gómez', cargo: null },
                    cumplido: true,
                    fecha_cumplido: '2026-11-03T15:00:00-05:00',
                    usuario_cumplido: 'Ana',
                    observacion: 'Ok',
                    soporte: { nombre: 'a.pdf' },
                },
                requisito: { nota: 'Una nota' },
            }),
            fila(2, 'C', {
                fila: { estado: 'no_aplica', cuenta: false, no_aplica: { justificacion: 'Sin predios' } },
                requisito: { fuente: 'ot', sector: salud, tipo: { nombre: 'Infraestructura en salud' }, subtitulo: '7.1' },
            }),
        ],
    };

    it('la hoja de checklist trae una fila por requisito con su cumplimiento', () => {
        const { checklist } = construirHojasExcel(expediente);

        expect(checklist).toHaveLength(2);
        expect(checklist[0]).toMatchObject({
            'Sección': 'S.1 Requisitos generales',
            'Código': 'c1',
            'Fuente': 'A12',
            'Requisito': 'Texto 1',
            'Nota': 'Una nota',
            'Estado': 'Cumplido',
            'Cumplido': 'Sí',
            'Fecha de cumplimiento': '2026-11-03',
            'Marcado por': 'Ana',
            'Responsable': 'Luis Gómez',
            'Justificación de no aplica': '',
            'Aprobado por': '',
            'Observación': 'Ok',
            'Soporte': 'a.pdf',
        });
        expect(checklist[1]).toMatchObject({
            'Sección': 'S.3 Requisitos sectoriales',
            'Sector': 'Salud',
            'Tipo de proyecto': 'Infraestructura en salud',
            'Fuente': 'OT',
            'Estado': 'No aplica',
            'Cumplido': 'No',
            'Fecha de cumplimiento': '',
            'Marcado por': '',
            'Responsable': '',
            'Justificación de no aplica': 'Sin predios',
        });
    });

    it('la hoja de resumen recoge la configuración y el avance', () => {
        const { resumen } = construirHojasExcel(expediente);
        const valor = (campo) => resumen.find((r) => r.Campo === campo)?.Valor;

        expect(valor('Proyecto')).toBe('Acueducto: veredal/norte');
        expect(valor('Fase SGR')).toBe('III — Factibilidad');
        expect(valor('Estado')).toBe('Borrador');
        expect(valor('Sectores')).toBe('Salud');
        expect(valor('Componentes')).toBe('Predios');
        expect(valor('Tipologías')).toBe('—');
        expect(valor('Avance')).toBe('1 de 2 (50%)');
    });

    it('el nombre del archivo no lleva caracteres inválidos', () => {
        expect(nombreArchivoExcel(expediente)).toBe('expediente-7-Acueducto-veredal-norte.xlsx');
    });
});

describe('acciones sobre un requisito', () => {
    it('solo se cumple lo pendiente o lo ya cumplido', () => {
        expect(puedeMarcarRequisito({ estado: 'pendiente' })).toBe(true);
        expect(puedeMarcarRequisito({ estado: 'cumplido' })).toBe(true);
        expect(puedeMarcarRequisito({ estado: 'no_aplica' })).toBe(false);
        expect(puedeMarcarRequisito({ estado: 'por_definir' })).toBe(false);
    });

    it('se marca no aplica lo pendiente o por definir, nunca lo cumplido', () => {
        expect(puedeMarcarNoAplica({ estado: 'pendiente' })).toBe(true);
        expect(puedeMarcarNoAplica({ estado: 'por_definir' })).toBe(true);
        expect(puedeMarcarNoAplica({ estado: 'cumplido' })).toBe(false);
        expect(puedeMarcarNoAplica({ estado: 'no_aplica_pendiente' })).toBe(false);
    });

    it('revisa otra persona, o un Administrador aunque lo haya marcado', () => {
        const porAprobar = { estado: 'no_aplica_pendiente', no_aplica: { usuario_id: 5 } };
        expect(puedeRevisarNoAplica(porAprobar, { id: 5, rol: 'Gestor' })).toBe(false);
        expect(puedeRevisarNoAplica(porAprobar, { id: 6, rol: 'Gestor' })).toBe(true);
        expect(puedeRevisarNoAplica(porAprobar, { id: 5, rol: 'Administrador' })).toBe(true);
        expect(puedeRevisarNoAplica({ ...porAprobar, estado: 'no_aplica' }, { id: 6, rol: 'Gestor' })).toBe(false);
        expect(puedeRevisarNoAplica(porAprobar, null)).toBe(false);
        expect(puedeRevisarNoAplica({ ...porAprobar, no_aplica: { usuario_id: null } }, { id: 6, rol: 'Gestor' })).toBe(true);
    });

    it('ofrece los responsables activos y conserva el actual aunque esté inactivo', () => {
        const lista = [{ id: 1, activo: true }, { id: 2, activo: false }, { id: 3, activo: false }];
        expect(responsablesDisponibles(lista, 2).map((r) => r.id)).toEqual([1, 2]);
        expect(responsablesDisponibles(lista, null).map((r) => r.id)).toEqual([1]);
        expect(responsablesDisponibles(undefined, null)).toEqual([]);
    });
});

describe('describirEvento', () => {
    it('explica cada cambio con sus datos', () => {
        expect(describirEvento({ evento: 'cumplido_marcado' })).toBe('Marcó como cumplido');
        expect(describirEvento({ evento: 'soporte_reemplazado', antes: { soporte_nombre: 'v1.pdf' }, despues: { soporte_nombre: 'v2.pdf' } }))
            .toBe('Reemplazó el soporte: v1.pdf por v2.pdf');
        expect(describirEvento({ evento: 'no_aplica_marcado', despues: { justificacion: 'Sin predios' } }))
            .toBe('Indicó que no aplica: Sin predios');
        expect(describirEvento({ evento: 'no_aplica_aprobado', despues: { autoaprobado: true } }))
            .toBe('Aprobó el "no aplica" que él mismo marcó');
        expect(describirEvento({ evento: 'responsable_cambiado', antes: { responsable: 'Ana' }, despues: { responsable: null } }))
            .toBe('Cambió el responsable: lo quitó (era Ana)');
        expect(describirEvento({ evento: 'otro_evento' })).toBe('otro_evento');
        expect(describirEvento({ evento: 'no_aplica_rechazado', despues: { motivo: 'Sí hay predios' } })).toBe('Rechazó el "no aplica": Sí hay predios');
        expect(describirEvento({ evento: 'no_aplica_rechazado', despues: { motivo: null } })).toBe('Rechazó el "no aplica"');
        expect(describirEvento({ evento: 'no_aplica_retirado', antes: null, despues: null })).toBe('Retiró el "no aplica"');
        expect(describirEvento({ evento: 'soporte_subido', despues: { soporte_nombre: 'acta.pdf' } })).toBe('Adjuntó un soporte: acta.pdf');
        expect(describirEvento({ evento: 'soporte_eliminado', antes: { soporte_nombre: null } })).toBe('Eliminó el soporte: soporte');
        expect(describirEvento({ evento: 'observacion_cambiada', despues: { observacion: 'Falta firma' } })).toBe('Cambió la observación: "Falta firma"');
        expect(describirEvento({ evento: 'observacion_cambiada', despues: { observacion: null } })).toBe('Cambió la observación: la quitó');
    });
});

describe('puedeRadicar', () => {
    it('solo con todos los requisitos cumplidos', () => {
        expect(puedeRadicar({ total: 8, cumplidos: 8 })).toBe(true);
        expect(puedeRadicar({ total: 8, cumplidos: 7 })).toBe(false);
    });

    it('un expediente sin requisitos no se radica', () => {
        expect(puedeRadicar({ total: 0, cumplidos: 0 })).toBe(false);
    });
});

describe('sectoresSinTipo', () => {
    const catalogo = {
        sectores: [
            { id: 1, nombre: 'Vivienda', tipos: [{ id: 10 }, { id: 11 }] },
            { id: 2, nombre: 'Salud', tipos: [{ id: 20 }] },
            { id: 3, nombre: 'Ambiente', tipos: [] },
        ],
    };

    it('lista los sectores marcados con tipos disponibles y ninguno elegido', () => {
        expect(sectoresSinTipo({ sector_ids: [1, 2], tipo_ids: [20] }, catalogo)).toEqual(['Vivienda']);
    });

    it('ignora sectores sin tipos o no marcados', () => {
        expect(sectoresSinTipo({ sector_ids: [3], tipo_ids: [] }, catalogo)).toEqual([]);
        expect(sectoresSinTipo({ sector_ids: [], tipo_ids: [] }, catalogo)).toEqual([]);
    });
});
