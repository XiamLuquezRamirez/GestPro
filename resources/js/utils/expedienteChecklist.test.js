import { describe, it, expect } from 'vitest';
import {
    ANCHOS_HOJA_CHECKLIST,
    accionesAplicabilidad,
    actualizarExpedienteEnLista,
    agruparChecklist,
    agruparRequisitos,
    alternarId,
    alternarSector,
    autorHistorial,
    construirHojasExcel,
    contenidoFila,
    datosPropio,
    describirEvento,
    fechaHoraHistorial,
    formularioPropio,
    formularioVigente,
    hayMasHistorial,
    mensajeError,
    motivoNoEliminarPropio,
    nombreArchivoExcel,
    plantillasConVersion,
    puedeMarcarNoAplica,
    puedeMarcarRequisito,
    contarOpcionales,
    pistaRadicar,
    puedeRadicar,
    puedeRevisarNoAplica,
    textoOpcionales,
    requiereAprobacionNoAplica,
    responsablesDisponibles,
    sectoresSinTipo,
    seleccionDesde,
    sectoresConFuenteA12,
    textoAvisosDuplicados,
    textoOrigenes,
    textoPlantillas,
    textoPorDefinir,
    textoRequisitoHistorial,
    unirPaginaHistorial,
} from './expedienteChecklist';

const fila = (id, seccion, extra = {}) => ({
    id,
    estado: 'pendiente',
    caracter: 'obligatorio',
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

const SGR = { id: 1, nombre: 'SGR', motor: 'sgr' };
const AGUA = { id: 5, nombre: 'Obras de agua', motor: 'simple' };
const VIAS = { id: 6, nombre: 'Vías', motor: 'simple' };

/** Fila de una plantilla simple: sin sección ni sector. */
const filaSimple = (id, extra = {}) => {
    const base = fila(id, null, { fila: extra.fila, requisito: { fuente: null, codigo: null, ...extra.requisito } });
    // Como presentarFila: la categoría va en la fila, no dentro del requisito.
    return { ...base, categoria: extra.categoria ?? null, plantilla: extra.plantilla ?? AGUA, origenes: extra.origenes ?? [{ plantilla_id: (extra.plantilla ?? AGUA).id, nombre: (extra.plantilla ?? AGUA).nombre }] };
};

/** Requisito propio: sin requisito de catálogo ni plantilla. */
const filaPropia = (id, extra = {}) => ({
    ...fila(id, null, { fila: extra.fila }),
    requisito: null,
    plantilla: null,
    origenes: [],
    requiere_aprobacion_no_aplica: false,
    propio: { titulo: `Propio ${id}`, descripcion: null, categoria: null, ...extra.propio },
    categoria: extra.propio?.categoria ?? null,
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
            plantillas: [
                { plantilla_id: 4, nombre: 'SGR', motor: 'sgr', es_principal: true },
                { plantilla_id: 9, nombre: 'Obras de agua', motor: 'simple', es_principal: false },
            ],
            configuracion: {
                sectores: [{ id: 1 }],
                tipos: [{ id: 5, sector_id: 1 }],
                componentes: [{ id: 7 }],
                tipologias: [{ id: 9 }],
            },
        };
        expect(seleccionDesde(expediente)).toEqual({
            plantilla_ids: [4, 9],
            principal_id: 4,
            fase_sgr: 2, sector_ids: [1], tipo_ids: [5], componente_ids: [7], tipologia_ids: [9],
        });
    });

    it('seleccionDesde de un expediente sin plantillas no tiene principal', () => {
        const expediente = {
            fase_sgr: 3,
            plantillas: [],
            configuracion: { sectores: [], tipos: [], componentes: [], tipologias: [] },
        };
        expect(seleccionDesde(expediente)).toMatchObject({ plantilla_ids: [], principal_id: null });
    });

    it('la principal es la marcada aunque no venga primera', () => {
        const expediente = {
            fase_sgr: 3,
            plantillas: [
                { plantilla_id: 9, nombre: 'Obras de agua', motor: 'simple', es_principal: false },
                { plantilla_id: 4, nombre: 'SGR', motor: 'sgr', es_principal: true },
            ],
            configuracion: { sectores: [], tipos: [], componentes: [], tipologias: [] },
        };
        expect(seleccionDesde(expediente)).toMatchObject({ plantilla_ids: [9, 4], principal_id: 4 });
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
        plantillas: [{ plantilla_id: 1, nombre: 'SGR', motor: 'sgr', es_principal: true, version: { id: 1, numero: 1 } }],
        tiene_sgr: true,
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

    it('la hoja de resumen nombra las plantillas con su versión', () => {
        const { resumen } = construirHojasExcel(expediente);
        expect(resumen.find((r) => r.Campo === 'Plantillas')?.Valor).toBe('SGR (v1)');
    });

    it('sin plantilla SGR no lleva la fase ni la configuración SGR, y aguanta filas propias', () => {
        const simple = {
            ...expediente,
            tiene_sgr: false,
            plantillas: [{ plantilla_id: 5, nombre: 'Obras de agua', motor: 'simple', es_principal: true, version: { id: 8, numero: 2 } }],
            requisitos: [
                filaSimple(3, { categoria: 'tecnico', fila: { caracter: 'opcional' } }),
                filaPropia(4, { propio: { titulo: 'Acta de vecinos', descripcion: 'Firmada', categoria: 'social' } }),
            ],
        };
        const { resumen, checklist } = construirHojasExcel(simple);
        const campos = resumen.map((r) => r.Campo);

        expect(campos).not.toContain('Fase SGR');
        expect(campos).not.toContain('Sectores');
        expect(campos).not.toContain('Tipologías');
        expect(resumen.find((r) => r.Campo === 'Plantillas')?.Valor).toBe('Obras de agua (v2)');
        expect(checklist[0]).toMatchObject({
            'Plantilla': 'Obras de agua', 'Sección': '', 'Categoría': 'Técnico', 'Código': '', 'Fuente': '', 'Requisito': 'Texto 3', 'Carácter': 'Opcional',
        });
        expect(checklist[1]).toMatchObject({
            'Plantilla': 'Propio del proyecto', 'Sección': '', 'Categoría': 'Social', 'Requisito': 'Acta de vecinos', 'Nota': 'Firmada', 'Carácter': 'Obligatorio',
        });
    });

    it('una fuente normativa que no es A12 ni OT sale tal cual', () => {
        const otra = { ...expediente, requisitos: [filaSimple(3, { requisito: { fuente: 'Resolución 0330 de 2017' } })] };
        expect(construirHojasExcel(otra).checklist[0]['Fuente']).toBe('Resolución 0330 de 2017');
    });

    it('una fila simple copiada de la SGR no muestra sección aunque traiga una', () => {
        const copiada = { ...expediente, requisitos: [filaSimple(3, { requisito: { seccion: 'A' } })] };
        expect(construirHojasExcel(copiada).checklist[0]['Sección']).toBe('');
    });

    it('hay un ancho por cada columna de la hoja de checklist', () => {
        const { checklist } = construirHojasExcel(expediente);
        expect(ANCHOS_HOJA_CHECKLIST).toHaveLength(Object.keys(checklist[0]).length);
    });

    it('sin plantillas, el resumen lo dice', () => {
        const { resumen } = construirHojasExcel({ ...expediente, plantillas: [], tiene_sgr: false, requisitos: [] });
        expect(resumen.find((r) => r.Campo === 'Plantillas')?.Valor).toBe('Sin plantillas');
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

    it('sin catálogo no avisa de ninguno', () => {
        expect(sectoresSinTipo({ sector_ids: [1], tipo_ids: [] }, null)).toEqual([]);
    });
});

describe('agruparChecklist', () => {
    const sgr = (id, seccion, extra = {}) => ({ ...fila(id, seccion, extra), plantilla: SGR, origenes: [{ plantilla_id: 1, nombre: 'SGR' }] });

    it('un expediente solo SGR queda en un bloque con sus secciones, como hoy', () => {
        const filas = [sgr(1, 'A'), sgr(2, 'B'), sgr(3, 'A', { fila: { estado: 'cumplido', cumplido: true } })];
        const bloques = agruparChecklist(filas);

        expect(bloques).toHaveLength(1);
        expect(bloques[0]).toMatchObject({ tipo: 'sgr', plantilla: SGR, total: 3, cumplidos: 1 });
        expect(bloques[0].secciones).toEqual(agruparRequisitos(filas));
    });

    it('las demás plantillas se agrupan por plantilla y luego por categoría, en el orden de la lista', () => {
        const bloques = agruparChecklist([
            sgr(1, 'A'),
            filaSimple(2, { categoria: 'juridico' }),
            filaSimple(3, { categoria: 'tecnico', fila: { estado: 'cumplido', cumplido: true } }),
            filaSimple(4, { categoria: null }),
            filaSimple(5, { categoria: 'juridico' }),
            filaSimple(6, { plantilla: VIAS, categoria: 'ambiental' }),
        ]);

        expect(bloques.map((b) => [b.tipo, b.plantilla?.nombre])).toEqual([['sgr', 'SGR'], ['simple', 'Obras de agua'], ['simple', 'Vías']]);
        const agua = bloques[1];
        expect(agua.categorias.map((c) => [c.clave, c.titulo, c.filas.map((f) => f.id)])).toEqual([
            ['tecnico', 'Técnico', [3]],
            ['juridico', 'Jurídico', [2, 5]],
            ['sin', 'Sin categoría', [4]],
        ]);
        expect(agua).toMatchObject({ total: 4, cumplidos: 1 });
        expect(agua.categorias[0]).toMatchObject({ total: 1, cumplidos: 1 });
    });

    it('una categoría desconocida cae en "Sin categoría"', () => {
        const [bloque] = agruparChecklist([filaSimple(1, { categoria: 'inventada' })]);
        expect(bloque.categorias.map((c) => c.clave)).toEqual(['sin']);
    });

    it('los propios van al final, aunque lleguen antes', () => {
        const bloques = agruparChecklist([filaPropia(9), sgr(1, 'A'), filaSimple(2), filaPropia(10, { fila: { cuenta: false } })]);

        expect(bloques.map((b) => b.tipo)).toEqual(['sgr', 'simple', 'propios']);
        expect(bloques[2]).toMatchObject({ titulo: 'Requisitos propios del proyecto', total: 1, cumplidos: 0 });
        expect(bloques[2].filas.map((f) => f.id)).toEqual([9, 10]);
    });

    it('lo que no cuenta no suma en la plantilla ni en la categoría', () => {
        const [bloque] = agruparChecklist([
            filaSimple(1, { fila: { cuenta: false, estado: 'cumplido', cumplido: true } }),
            filaSimple(2, { fila: { estado: 'no_aplica_pendiente', cumplido: true } }),
        ]);
        expect(bloque).toMatchObject({ total: 1, cumplidos: 0 });
    });

    it('un solo requisito propio conserva su bloque', () => {
        const bloques = agruparChecklist([filaPropia(9)]);
        expect(bloques).toHaveLength(1);
        expect(bloques[0]).toMatchObject({ tipo: 'propios', clave: 'propios', total: 1, cumplidos: 0 });
        expect(bloques[0].filas.map((f) => f.id)).toEqual([9]);
    });

    it('sin filas no hay bloques', () => {
        expect(agruparChecklist([])).toEqual([]);
    });
});

describe('accionesAplicabilidad', () => {
    const gestor = { id: 6, rol: 'Gestor' };

    it('por definir ofrece "Aplica" y "No aplica"', () => {
        expect(accionesAplicabilidad({ estado: 'por_definir', no_aplica: null }, gestor)).toEqual(['aplica', 'no_aplica']);
    });

    it('pendiente solo ofrece "No aplica"; cumplido, nada', () => {
        expect(accionesAplicabilidad({ estado: 'pendiente', no_aplica: null }, gestor)).toEqual(['no_aplica']);
        expect(accionesAplicabilidad({ estado: 'cumplido', no_aplica: null }, gestor)).toEqual([]);
    });

    it('un "no aplica" se retira y, si está por aprobar, lo revisa otra persona', () => {
        const porAprobar = { estado: 'no_aplica_pendiente', no_aplica: { usuario_id: 5 } };
        expect(accionesAplicabilidad(porAprobar, gestor)).toEqual(['retirar_no_aplica', 'aprobar', 'rechazar']);
        expect(accionesAplicabilidad(porAprobar, { id: 5, rol: 'Gestor' })).toEqual(['retirar_no_aplica']);
        expect(accionesAplicabilidad({ estado: 'no_aplica', no_aplica: { usuario_id: 5 } }, gestor)).toEqual(['retirar_no_aplica']);
    });
});

describe('requiereAprobacionNoAplica', () => {
    it('se lee de la fila, que reúne todos sus orígenes, y no del requisito mostrado', () => {
        expect(requiereAprobacionNoAplica({ requiere_aprobacion_no_aplica: true, requisito: { requiere_aprobacion_no_aplica: false } })).toBe(true);
        expect(requiereAprobacionNoAplica({ requiere_aprobacion_no_aplica: false, requisito: { requiere_aprobacion_no_aplica: true } })).toBe(false);
    });

    it('un requisito propio no tiene requisito de catálogo y no la exige', () => {
        expect(requiereAprobacionNoAplica(filaPropia(1))).toBe(false);
    });
});

describe('textoPlantillas', () => {
    it('nombra las plantillas en el orden recibido, la principal primero', () => {
        expect(textoPlantillas([{ nombre: 'SGR' }, { nombre: 'Obras de agua' }])).toBe('Plantillas: SGR, Obras de agua');
        expect(textoPlantillas(['SGR', 'Obras de agua'])).toBe('Plantillas: SGR, Obras de agua');
    });

    it('con una sola plantilla, en singular', () => {
        expect(textoPlantillas([{ nombre: 'Obras de agua' }])).toBe('Plantilla: Obras de agua');
    });

    it('sin plantillas lo dice', () => {
        expect(textoPlantillas([])).toBe('Sin plantillas');
        expect(textoPlantillas(undefined)).toBe('Sin plantillas');
    });
});

describe('textoOrigenes', () => {
    it('con más de un origen, nombra las demás plantillas', () => {
        const f = filaSimple(1, { origenes: [{ plantilla_id: 5, nombre: 'Obras de agua' }, { plantilla_id: 6, nombre: 'Vías' }, { plantilla_id: 7, nombre: 'Riego' }] });
        expect(textoOrigenes(f)).toBe('También en: Vías, Riego');
    });

    it('con un solo origen, o ninguno, no dice nada', () => {
        expect(textoOrigenes(filaSimple(1))).toBeNull();
        expect(textoOrigenes(filaPropia(2))).toBeNull();
    });
});

describe('contenidoFila', () => {
    it('una fila de plantilla muestra su requisito', () => {
        const f = fila(1, 'A', { requisito: { nota: 'Una nota' } });
        expect(contenidoFila(f)).toEqual({
            propio: false, codigo: 'c1', fuente: 'A12', texto: 'Texto 1', nota: 'Una nota', categoria: null, cargoRevision: null, cargoCumplimiento: null,
            descripcion: null, aplicaSi: null, soporteRequerido: null,
        });
    });

    it('una fila propia muestra sus textos, sin código ni fuente', () => {
        const f = filaPropia(2, { propio: { titulo: 'Acta', descripcion: 'Firmada', categoria: 'social' } });
        expect(contenidoFila(f)).toEqual({
            propio: true, codigo: null, fuente: null, texto: 'Acta', nota: 'Firmada', categoria: 'Social', cargoRevision: null, cargoCumplimiento: null,
            descripcion: null, aplicaSi: null, soporteRequerido: null,
        });
    });

    it('una fila simple sin código ni fuente no los inventa', () => {
        const f = filaSimple(3, { categoria: 'tecnico', requisito: { cargo_revision: 'Interventor' } });
        expect(contenidoFila(f)).toMatchObject({ codigo: null, fuente: null, categoria: 'Técnico', cargoRevision: 'Interventor' });
    });

    it('trae la descripción y el soporte requerido del requisito', () => {
        const f = filaSimple(4, { requisito: { descripcion: 'Lo expide la autoridad ambiental.', soporte_requerido: 'Resolución' } });
        expect(contenidoFila(f)).toMatchObject({ descripcion: 'Lo expide la autoridad ambiental.', soporteRequerido: 'Resolución', aplicaSi: null });
    });

    it('un condicionado dice cuándo aplica, esté por definir o ya decidido', () => {
        const condicion = { requisito: { condicion_descripcion: 'Si la obra descarga aguas residuales.' } };
        expect(contenidoFila(filaSimple(5, { ...condicion, fila: { caracter: 'condicionado', estado: 'por_definir' } })).aplicaSi)
            .toBe('Si la obra descarga aguas residuales.');
        expect(contenidoFila(filaSimple(6, { ...condicion, fila: { caracter: 'condicionado', estado: 'pendiente' } })).aplicaSi)
            .toBe('Si la obra descarga aguas residuales.');
        // Un obligatorio no muestra una condición que no lo rige.
        expect(contenidoFila(filaSimple(7, { ...condicion, fila: { caracter: 'obligatorio' } })).aplicaSi).toBeNull();
    });
});

describe('textoPorDefinir', () => {
    it('dice cuándo aplica, si la plantilla lo explica', () => {
        const f = filaSimple(1, { requisito: { condicion_descripcion: 'Si hay predios.' }, fila: { caracter: 'condicionado', estado: 'por_definir' } });
        expect(textoPorDefinir(f)).toBe('Aplica si: Si hay predios.');
    });

    it('sin explicación, dice que falta decidir', () => {
        const f = filaSimple(2, { fila: { caracter: 'condicionado', estado: 'por_definir' } });
        expect(textoPorDefinir(f)).toBe('Falta decidir si este requisito aplica al proyecto.');
    });

    it('un obligatorio por definir también dice cuándo aplica', () => {
        // Por definir basta para mostrar cuándo aplica, aunque el carácter sea obligatorio.
        const f = filaSimple(3, { requisito: { condicion_descripcion: 'Si hay predios.' }, fila: { caracter: 'obligatorio', estado: 'por_definir' } });
        expect(textoPorDefinir(f)).toBe('Aplica si: Si hay predios.');
        expect(contenidoFila(f).aplicaSi).toBe('Si hay predios.');
        const sin = filaSimple(4, { fila: { caracter: 'obligatorio', estado: 'por_definir' } });
        expect(textoPorDefinir(sin)).toBe('Falta decidir si este requisito aplica al proyecto.');
    });
});

describe('requisitos propios', () => {
    it('el formulario nuevo es obligatorio y sin categoría', () => {
        expect(formularioPropio(null)).toEqual({ titulo: '', descripcion: '', categoria: '', caracter: 'obligatorio' });
    });

    it('el formulario de edición parte de la fila', () => {
        const f = filaPropia(1, { propio: { titulo: 'Acta', descripcion: null, categoria: 'social' }, fila: { caracter: 'opcional' } });
        expect(formularioPropio(f)).toEqual({ titulo: 'Acta', descripcion: '', categoria: 'social', caracter: 'opcional' });
    });

    it('lo que se envía va recortado y lo vacío como null', () => {
        expect(datosPropio({ titulo: '  Acta  ', descripcion: '   ', categoria: '', caracter: 'opcional' }))
            .toEqual({ titulo: 'Acta', descripcion: null, categoria: null, caracter: 'opcional' });
    });

    it('solo se elimina un propio que no esté cumplido ni tenga soporte', () => {
        expect(motivoNoEliminarPropio(filaPropia(1))).toBeNull();
        expect(motivoNoEliminarPropio(filaPropia(1, { fila: { cumplido: true } }))).toBe('El requisito está cumplido: desmárcalo antes de eliminarlo.');
        expect(motivoNoEliminarPropio(filaPropia(1, { fila: { soporte: { nombre: 'a.pdf' } } }))).toBe('El requisito tiene un soporte: quítalo antes de eliminarlo.');
    });
});

describe('describirEvento de la etapa 5', () => {
    it('explica "aplica" y los cambios de un requisito propio', () => {
        expect(describirEvento({ evento: 'aplicabilidad_decidida' })).toBe('Indicó que aplica');
        expect(describirEvento({ evento: 'requisito_propio_agregado', despues: { titulo: 'Acta' } })).toBe('Agregó el requisito propio: Acta');
        expect(describirEvento({ evento: 'requisito_propio_editado', antes: { titulo: 'A', caracter: 'obligatorio' }, despues: { titulo: 'B', caracter: 'opcional' } }))
            .toBe('Editó el requisito propio: título y carácter');
        expect(describirEvento({ evento: 'requisito_propio_editado', despues: { categoria: 'social' } })).toBe('Editó el requisito propio: categoría');
        expect(describirEvento({ evento: 'requisito_propio_eliminado', antes: { titulo: 'Acta' } })).toBe('Eliminó el requisito propio: Acta');
    });
});

describe('actualizarExpedienteEnLista', () => {
    const lista = [
        { id: 3, estado: 'borrador', configurado: true, plantillas: [{ plantilla_id: 1, nombre: 'SGR' }], tiene_sgr: true, progreso: { total: 8 } },
        { id: 2, estado: 'radicado', configurado: true, plantillas: [{ plantilla_id: 1, nombre: 'SGR' }], tiene_sgr: true, progreso: { total: 8 } },
    ];
    const actualizado = {
        id: 3, estado: 'borrador', configurado: true, plantillas: [{ plantilla_id: 5, nombre: 'Obras de agua' }], tiene_sgr: false,
        progreso: { total: 4 }, requisitos: [{ id: 1 }], configuracion: {},
    };

    it('cambia ese expediente con sus plantillas actuales y deja los demás', () => {
        const nueva = actualizarExpedienteEnLista(lista, actualizado);
        expect(nueva[0]).toEqual({ id: 3, estado: 'borrador', configurado: true, plantillas: [{ plantilla_id: 5, nombre: 'Obras de agua' }], tiene_sgr: false, progreso: { total: 4 } });
        expect(nueva[1]).toBe(lista[1]);
    });

    it('solo toma las claves que la lista ya tiene, aunque la respuesta traiga otras', () => {
        const nueva = actualizarExpedienteEnLista(lista, { ...actualizado, clave_nueva: 'x' });
        expect(nueva[0]).not.toHaveProperty('clave_nueva');
        expect(nueva[0]).not.toHaveProperty('configuracion');
    });

    it('conserva la forma de la lista: no agrega el checklist', () => {
        expect(actualizarExpedienteEnLista(lista, actualizado)[0]).not.toHaveProperty('requisitos');
    });

    it('sin lista, o si el expediente no está, no cambia nada', () => {
        expect(actualizarExpedienteEnLista(undefined, actualizado)).toBeUndefined();
        expect(actualizarExpedienteEnLista(lista, { ...actualizado, id: 9 })).toEqual(lista);
    });
});

describe('textoAvisosDuplicados', () => {
    const aviso = (n) => ({ filas: [n, n + 100], textos: ['a', 'b'], plantillas: ['SGR', 'Agua'], similitud: 0.9 });
    const avisos = (n) => Array.from({ length: n }, (_, i) => aviso(i + 1));

    it('sin avisos no hay texto', () => {
        expect(textoAvisosDuplicados([], 0)).toBeNull();
        expect(textoAvisosDuplicados(undefined, undefined)).toBeNull();
    });

    it('un solo par va en singular', () => {
        expect(textoAvisosDuplicados(avisos(1), 1)).toEqual({ titulo: '1 par de requisitos se parece', resumen: 'Ver el par' });
    });

    it('varios pares, todos a la vista', () => {
        expect(textoAvisosDuplicados(avisos(3), 3)).toEqual({ titulo: '3 pares de requisitos se parecen', resumen: 'Ver los 3 pares' });
    });

    it('con tope dice el total real y cuántos se muestran', () => {
        expect(textoAvisosDuplicados(avisos(50), 73)).toEqual({
            titulo: '73 pares de requisitos se parecen; se muestran los 50 más parecidos',
            resumen: 'Ver los 50 pares',
        });
    });

    it('sin total, o con uno menor, cuenta los que llegaron', () => {
        expect(textoAvisosDuplicados(avisos(2)).titulo).toBe('2 pares de requisitos se parecen');
        expect(textoAvisosDuplicados(avisos(2), 1).titulo).toBe('2 pares de requisitos se parecen');
    });
});

describe('plantillasConVersion', () => {
    it('nombra cada plantilla con su versión, en el orden del expediente', () => {
        expect(plantillasConVersion([
            { plantilla_id: 1, nombre: 'SGR', version: { id: 1, numero: 1 } },
            { plantilla_id: 5, nombre: 'Obras de agua', version: { id: 8, numero: 2 } },
        ])).toBe('SGR (v1), Obras de agua (v2)');
    });

    it('sin versión, solo el nombre', () => {
        expect(plantillasConVersion([{ nombre: 'Vías', version: null }])).toBe('Vías');
    });

    it('omite las entradas sin nombre', () => {
        expect(plantillasConVersion([null, { nombre: '', version: { numero: 3 } }, { nombre: 'SGR', version: { numero: 1 } }, {}]))
            .toBe('SGR (v1)');
        expect(plantillasConVersion([{ version: { numero: 2 } }, null])).toBe('Sin plantillas');
    });

    it('vacío o ausente es "Sin plantillas"', () => {
        expect(plantillasConVersion([])).toBe('Sin plantillas');
        expect(plantillasConVersion(null)).toBe('Sin plantillas');
    });
});

describe('formularioVigente', () => {
    const filas = [filaPropia(4), fila(5, 'A')];

    it('cerrado o para uno nuevo, queda igual', () => {
        expect(formularioVigente(null, filas)).toBeNull();
        expect(formularioVigente('nuevo', filas)).toBe('nuevo');
        expect(formularioVigente('nuevo', [])).toBe('nuevo');
    });

    it('editando un propio que sigue, queda abierto', () => {
        expect(formularioVigente(4, filas)).toBe(4);
    });

    it('si el propio ya no está, o el id no es de un propio, se cierra', () => {
        expect(formularioVigente(9, filas)).toBeNull();
        expect(formularioVigente(5, filas)).toBeNull();
        expect(formularioVigente(4, undefined)).toBeNull();
    });
});

describe('historial del expediente', () => {
    const configuracion = (extra = {}) => ({
        plantillas: [
            { plantilla_id: 1, catalogo_version_id: 10, es_principal: true },
            { plantilla_id: 2, catalogo_version_id: 20, es_principal: false },
        ],
        fase_sgr: 3,
        sector_ids: [4],
        tipo_ids: [],
        componente_ids: [7],
        tipologia_ids: [],
        ...extra,
    });

    it('describe los eventos del propio expediente', () => {
        expect(describirEvento({ evento: 'expediente_creado', antes: [], despues: { plantillas: [] } })).toBe('Creó el expediente');
        expect(describirEvento({ evento: 'configuracion_confirmada', antes: [], despues: configuracion() })).toBe('Confirmó la configuración');
        expect(describirEvento({ evento: 'expediente_radicado', antes: [], despues: { requisitos: 12 } })).toBe('Radicó el expediente');
    });

    it('dice qué cambió en la configuración', () => {
        const cambio = (despues) => describirEvento({ evento: 'configuracion_cambiada', antes: configuracion(), despues });
        expect(cambio(configuracion({ sector_ids: [4, 5] }))).toBe('Cambió la configuración: sectores');
        expect(cambio(configuracion({ fase_sgr: 2, tipo_ids: [8], tipologia_ids: [9] })))
            .toBe('Cambió la configuración: fase SGR, tipos de proyecto y tipologías');
        expect(cambio(configuracion({ componente_ids: [] }))).toBe('Cambió la configuración: componentes');
        // Los ids se comparan como conjuntos: el orden no es un cambio.
        expect(cambio(configuracion({ sector_ids: [4], componente_ids: [7] }))).toBe('Cambió la configuración');
    });

    it('nombra las plantillas, o solo la principal si las plantillas son las mismas', () => {
        const antes = configuracion();
        const otraPrincipal = configuracion({
            plantillas: [
                { plantilla_id: 2, catalogo_version_id: 20, es_principal: true },
                { plantilla_id: 1, catalogo_version_id: 10, es_principal: false },
            ],
        });
        expect(describirEvento({ evento: 'configuracion_cambiada', antes, despues: otraPrincipal }))
            .toBe('Cambió la configuración: plantilla principal');

        // Al quitar la plantilla sgr también se va la fase: lo que cambió son las plantillas.
        const { fase_sgr: _fase, ...sinSgr } = configuracion({ plantillas: [{ plantilla_id: 2, catalogo_version_id: 20, es_principal: true }] });
        expect(describirEvento({ evento: 'configuracion_cambiada', antes, despues: sinSgr }))
            .toBe('Cambió la configuración: plantillas');
        expect(describirEvento({ evento: 'configuracion_cambiada', antes: null, despues: null })).toBe('Cambió la configuración');
    });

    it('describe la versión aplicada con la anterior', () => {
        expect(describirEvento({
            evento: 'version_aplicada',
            antes: { plantilla_id: 2, plantilla: 'Obras de agua', version: 2, catalogo_version_id: 20 },
            despues: { plantilla_id: 2, plantilla: 'Obras de agua', version: 3, catalogo_version_id: 21 },
        })).toBe('Aplicó la versión 3 de Obras de agua (antes 2)');
        expect(describirEvento({ evento: 'version_aplicada', antes: {}, despues: { plantilla: 'Vías', version: 4 } }))
            .toBe('Aplicó la versión 4 de Vías');
        expect(describirEvento({ evento: 'version_aplicada', antes: null, despues: null })).toBe('Aplicó una versión nueva de una plantilla');
    });

    it('da el requisito de cada entrada en corto', () => {
        const largo = 'Carta de presentación del proyecto firmada por el representante legal de la entidad, con la fecha y el valor total solicitado';
        const texto = textoRequisitoHistorial({ entidad: 'requisito', requisito: { id: 1, codigo: 'A.1.2', texto: largo, titulo: null } });
        expect(texto.startsWith('A.1.2 · Carta de presentación')).toBe(true);
        expect(texto.endsWith('…')).toBe(true);
        expect(texto.length).toBeLessThanOrEqual('A.1.2 · '.length + 90);

        expect(textoRequisitoHistorial({ entidad: 'requisito', requisito: { id: 1, codigo: 'B.3', texto: 'Acta corta', titulo: null } }))
            .toBe('B.3 · Acta corta');
        expect(textoRequisitoHistorial({ entidad: 'requisito', requisito: { id: 1, codigo: null, texto: 'Sin código', titulo: null } }))
            .toBe('Sin código');
        expect(textoRequisitoHistorial({ entidad: 'requisito', requisito: { id: 2, codigo: null, texto: null, titulo: 'Acta de vecinos' } }))
            .toBe('Acta de vecinos');
    });

    it('de una fila que ya no existe usa el título del propio eliminado; del expediente, nada', () => {
        expect(textoRequisitoHistorial({ entidad: 'requisito', requisito: null, antes: { titulo: 'Acta de vecinos' } })).toBe('Acta de vecinos');
        expect(textoRequisitoHistorial({ entidad: 'requisito', requisito: null, antes: {} })).toBe('Requisito que ya no está en el checklist');
        expect(textoRequisitoHistorial({ entidad: 'expediente', requisito: null, antes: [] })).toBeNull();
    });

    it('une las páginas sin repetir lo que se corrió de una a otra', () => {
        const primera = [{ id: 9 }, { id: 8 }, { id: 7 }];
        expect(unirPaginaHistorial(primera, [{ id: 7 }, { id: 6 }]).map((e) => e.id)).toEqual([9, 8, 7, 6]);
        expect(unirPaginaHistorial([], [{ id: 3 }])).toEqual([{ id: 3 }]);
    });

    it('sabe si quedan páginas', () => {
        expect(hayMasHistorial({ pagina: 1, por_pagina: 50, total: 51 })).toBe(true);
        expect(hayMasHistorial({ pagina: 1, por_pagina: 50, total: 50 })).toBe(false);
        expect(hayMasHistorial({ pagina: 2, por_pagina: 50, total: 120 })).toBe(true);
        expect(hayMasHistorial({ pagina: 1, por_pagina: 50, total: 0 })).toBe(false);
        expect(hayMasHistorial(null)).toBe(false);
    });

    it('formatea la fecha y hora, y nada sin fecha', () => {
        expect(fechaHoraHistorial(null)).toBe('');
        expect(fechaHoraHistorial('2026-11-09T10:18:15-05:00')).toMatch(/\d/);
    });

    it('nombra a quien registró la entrada, al sistema o a un usuario que ya no existe', () => {
        expect(autorHistorial({ usuario: 'Gestora Uno', sistema: false })).toBe('Gestora Uno');
        expect(autorHistorial({ usuario: null, sistema: true })).toBe('Sistema');
        expect(autorHistorial({ usuario: null, sistema: false })).toBe('Usuario eliminado');
    });
});

describe('opcionales en el avance', () => {
    const fila = (caracter, estado = 'pendiente') => ({ caracter, estado });

    it('cuenta los opcionales que se muestran, salvo los que no aplican', () => {
        expect(contarOpcionales([fila('opcional'), fila('obligatorio'), fila('opcional', 'cumplido'), fila('opcional', 'no_aplica')])).toBe(2);
        expect(contarOpcionales(undefined)).toBe(0);
    });

    it('explica que los opcionales no cuentan, en singular y plural', () => {
        expect(textoOpcionales(0)).toBeNull();
        expect(textoOpcionales(1)).toBe('1 opcional, no cuenta en el avance');
        expect(textoOpcionales(3)).toBe('3 opcionales, no cuentan en el avance');
    });

    it('dice por qué no se puede radicar todavía', () => {
        expect(pistaRadicar({ total: 4, cumplidos: 4 }, 0)).toBeNull();
        expect(pistaRadicar({ total: 4, cumplidos: 3 }, 0)).toBe('Falta 1 para radicar');
        expect(pistaRadicar({ total: 4, cumplidos: 1 }, 2)).toBe('Faltan 3 para radicar');
        expect(pistaRadicar({ total: 0, cumplidos: 0 }, 0)).toBe('Sin requisitos para radicar');
        expect(pistaRadicar({ total: 0, cumplidos: 0 }, 1))
            .toBe('Solo hay requisitos opcionales: para radicar hace falta al menos uno obligatorio');
    });
});
