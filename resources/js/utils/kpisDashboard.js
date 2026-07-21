// Cálculo de los KPIs globales del dashboard.
// Función pura, probada en kpisDashboard.test.js.
//
// Nota de diseño: todos los indicadores se derivan de datos verificables
// (contratos, actas, estado y fase). Deliberadamente NO se usa el campo
// proyectos.progreso: nunca se escribe desde la aplicación y sus valores
// provienen del seeder de demostración.

import { calcularCadenaPresupuestal } from './cadenaPresupuestal';
import { aplanarContratos } from './avanceContratos';

const SIN_FASE = 'Sin fase';

/**
 * Ejecución financiera de un grupo de proyectos (una fase, un municipio, etc.).
 *
 * Distingue dos situaciones que no deben confundirse:
 *  - Sin contratos  → pct null. No hay base para medir; la interfaz muestra "—".
 *  - Con contratos y sin actas → pct 0. El 0% es real: se contrató y no se ha
 *    facturado nada todavía.
 */
export const calcularAvanceGrupo = (proyectos) => {
    const lista = Array.isArray(proyectos) ? proyectos : [];
    const tieneContratos = lista.some(p => (p?.contratos?.length || 0) > 0);

    if (!tieneContratos) {
        return { pct: null, tieneContratos: false };
    }

    const { pctEjecutado } = calcularCadenaPresupuestal(lista);
    return { pct: pctEjecutado ?? 0, tieneContratos: true };
};

/**
 * Calcula los seis KPIs de la tira superior del dashboard.
 *
 * - total:          cantidad de proyectos
 * - totalContratos: suma de contratos de todos los proyectos
 * - enRiesgo:       proyectos en estado "Con retraso"
 * - pctEjecucion:   facturado / contratado × 100 (null si no hay contratado)
 * - contratados:    proyectos con al menos un contrato
 * - porFase:        [{ fase, cantidad }] ordenado alfabéticamente
 */
export const calcularKpis = (proyectos) => {
    const lista = Array.isArray(proyectos) ? proyectos : [];

    const total = lista.length;
    const totalContratos = lista.reduce((suma, p) => suma + (p?.contratos?.length || 0), 0);
    const enRiesgo = lista.filter(p => p?.descripcion_estado === 'Con retraso').length;
    const contratados = lista.filter(p => (p?.contratos?.length || 0) > 0).length;

    // La ejecución financiera es la misma que muestra la cadena presupuestal:
    // reutilizarla evita que el KPI y el bloque cuenten historias distintas.
    const { pctEjecutado } = calcularCadenaPresupuestal(lista);

    // Contratos donde lo facturado supera en más de 25 puntos a lo ejecutado
    // físicamente. aplanarContratos ya deriva la severidad del historial y marca
    // como sinDatos los que no son medibles, así que esos no se cuentan.
    const desfaseCritico = aplanarContratos(lista)
        .filter(c => !c.sinDatos && c.severidad === 'critico')
        .length;

    const conteoFases = lista.reduce((acc, p) => {
        const fase = p?.descripcion_fase || SIN_FASE;
        acc[fase] = (acc[fase] || 0) + 1;
        return acc;
    }, {});

    const porFase = Object.entries(conteoFases)
        .map(([fase, cantidad]) => ({ fase, cantidad }))
        .sort((a, b) => a.fase.localeCompare(b.fase));

    return {
        total,
        totalContratos,
        enRiesgo,
        pctEjecucion: pctEjecutado,
        contratados,
        desfaseCritico,
        porFase,
    };
};
