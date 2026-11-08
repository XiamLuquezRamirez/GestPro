// Lógica del tablero y del modo sala, separada de la vista para poder probarla.
import { avanceRequisitos, esFormulacion } from './expedientesResumen';
import { calcularAvanceGrupo } from './kpisDashboard';
import { normalizar } from './navegacion';

export const VISTA = {
    ESTADISTICAS: 'estadisticas',
    CONTRATOS: 'contratos',
    EXPEDIENTES: 'expedientes',
};

const VISTAS_ANALITICAS = Object.values(VISTA);

// Por encima de este ancho, la sala escala todo en proporción: así un televisor 4K
// se lee igual que uno Full HD, en lugar de mostrar la misma interfaz diminuta.
const ANCHO_BASE_SALA = 1920;
const ALTO_MINIMO_SALA_COMPLETA = 900;

export const slugFase = (nombre) => normalizar(nombre).replace(/\s+/g, '-');

export const resolverVista = (pedida, fases) => {
    if (VISTAS_ANALITICAS.includes(pedida)) return pedida;
    const slugs = fases.map((f) => slugFase(f.nombre));
    if (slugs.includes(pedida)) return pedida;
    return slugs[0] ?? null;
};

const metricaDeFase = (fase, delGrupo, resumenes) => {
    if (esFormulacion(fase.nombre)) {
        const { pct, medidos, total } = avanceRequisitos(delGrupo, resumenes);
        return { pct, etiqueta: 'Requisitos', detalle: `${medidos} de ${total} configurados` };
    }

    const avance = calcularAvanceGrupo(delGrupo);
    return {
        pct: avance.tieneContratos ? avance.pct : null,
        etiqueta: 'Ejecución financiera',
        detalle: avance.tieneContratos ? 'Facturado sobre contratado' : 'Sin contratos',
    };
};

export const resumirFases = (fases, proyectos, resumenes) =>
    fases.map((fase) => {
        const delGrupo = proyectos.filter((p) => p.descripcion_fase === fase.nombre);

        return {
            id: fase.id,
            nombre: fase.nombre,
            slug: slugFase(fase.nombre),
            color: fase.color,
            cantidad: delGrupo.length,
            presupuesto: delGrupo.reduce((suma, p) => suma + (parseFloat(p.presupuesto) || 0), 0),
            enRiesgo: delGrupo.filter((p) => p.descripcion_estado === 'Con retraso').length,
            proporcion: proyectos.length ? Math.round((delGrupo.length / proyectos.length) * 100) : 0,
            metrica: metricaDeFase(fase, delGrupo, resumenes),
        };
    });

export const secuenciaSala = (fases) => [...fases.map((f) => slugFase(f.nombre)), VISTA.CONTRATOS, VISTA.EXPEDIENTES];

export const siguienteVista = (secuencia, actual) => {
    if (secuencia.length === 0) return null;
    const indice = secuencia.indexOf(actual);
    return secuencia[(indice + 1) % secuencia.length];
};

export const escalaSala = (anchoVentana) => Math.max(1, anchoVentana / ANCHO_BASE_SALA);

/** En la sala no se puede desplazar: las filas se ajustan al alto real, ya sin la escala. */
export const filasEnSala = (altoVentana, anchoVentana) =>
    (altoVentana / escalaSala(anchoVentana) < ALTO_MINIMO_SALA_COMPLETA ? 6 : 8);

export const formatearMillones =(valor) => `$${Math.round((valor || 0) / 1_000_000).toLocaleString('es-CO')} M`;
