// Utilidades de cálculo para la vista de contratos del dashboard.
// Funciones puras: sin React, sin peticiones. Probadas en avanceContratos.test.js.

// Umbrales de desfase (financiero − físico) en puntos porcentuales.
export const UMBRAL_SANO = 10;
export const UMBRAL_ATENCION = 25;

export const COLOR_SEVERIDAD = {
    sano: '#16a34a',
    atencion: '#f59e0b',
    critico: '#dc2626',
};

const numero = (valor) => {
    const n = parseFloat(valor);
    return Number.isFinite(n) ? n : 0;
};

// Desfase = avance financiero − avance físico. Positivo significa que se ha
// facturado más de lo ejecutado.
export const calcularDesfase = (contrato) =>
    numero(contrato?.avance_financiero) - numero(contrato?.avance_fisico);

export const severidadDesfase = (desfase) => {
    if (desfase <= UMBRAL_SANO) return 'sano';
    if (desfase <= UMBRAL_ATENCION) return 'atencion';
    return 'critico';
};

const vacio = (valor) => valor === null || valor === undefined || valor === '';

// Un contrato es "sin datos" si le falta CUALQUIERA de los dos avances: el
// desfase solo tiene sentido comparando ambos. Con uno solo, graficar el otro
// en 0 lo haría pasar por "sano" cuando en realidad no se puede saber.
const sinDatosDeAvance = (contrato) =>
    vacio(contrato?.avance_fisico) || vacio(contrato?.avance_financiero);

// Convierte la estructura anidada proyectos[].contratos[] en una lista plana de
// filas listas para graficar y tabular.
export const aplanarContratos = (proyectos) => {
    if (!Array.isArray(proyectos)) return [];

    return proyectos.flatMap((proyecto) =>
        (proyecto.contratos || []).map((contrato) => {
            const sinDatos = sinDatosDeAvance(contrato);
            const desfase = calcularDesfase(contrato);

            return {
                ...contrato,
                nombreProyecto: proyecto.nombre,
                proyectoId: proyecto.id,
                avanceFisico: numero(contrato.avance_fisico),
                avanceFinanciero: numero(contrato.avance_financiero),
                valorNumerico: numero(contrato.valor),
                desfase,
                severidad: severidadDesfase(desfase),
                sinDatos,
            };
        })
    );
};

// Normaliza una fecha (que puede venir como '2026-01-31' o ISO completo) a 'YYYY-MM-DD'.
const soloFecha = (valor) => (valor ? String(valor).slice(0, 10) : null);

// Reconstruye la evolución de ambos avances a lo largo del contrato.
// Devuelve [{ fecha, fisico, financiero, brecha }] ordenado por fecha ascendente.
export const construirSerieTemporal = (contrato) => {
    const valorVigente = numero(contrato?.valor);
    if (!valorVigente) return [];

    const actividades = contrato.actividades || [];
    const actas = contrato.avancesFinancieros || [];

    // Fechas de corte: todas las fechas donde algo cambió, deduplicadas y ordenadas.
    const fechas = [
        ...actividades.flatMap((act) => (act.avances || []).map((a) => soloFecha(a.fecha))),
        ...actas.map((acta) => soloFecha(acta.fecha_acta)),
    ].filter(Boolean);

    const cortes = [...new Set(fechas)].sort();
    if (cortes.length === 0) return [];

    return cortes.map((corte) => {
        // Físico: suma ponderada del último avance de cada actividad hasta el corte.
        const fisico = actividades.reduce((suma, act) => {
            const avancesHasta = (act.avances || [])
                .filter((a) => soloFecha(a.fecha) <= corte)
                .sort((a, b) => soloFecha(a.fecha).localeCompare(soloFecha(b.fecha)));

            const ultimo = avancesHasta.length ? numero(avancesHasta[avancesHasta.length - 1].porcentaje_ejecucion) : 0;
            return suma + (numero(act.peso) / 100) * ultimo;
        }, 0);

        // Financiero: acumulado de lo facturado hasta el corte sobre el valor vigente.
        const facturadoAcumulado = actas
            .filter((acta) => soloFecha(acta.fecha_acta) <= corte)
            .reduce((suma, acta) => suma + numero(acta.valor_facturado), 0);

        const financiero = (facturadoAcumulado / valorVigente) * 100;

        const redondear = (n) => Math.round(n * 100) / 100;
        return {
            fecha: corte,
            fisico: redondear(fisico),
            financiero: redondear(financiero),
            brecha: redondear(financiero - fisico),
        };
    });
};
