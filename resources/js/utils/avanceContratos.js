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

// Convierte la estructura anidada proyectos[].contratos[] en una lista plana de
// filas listas para graficar y tabular.
//
// Los avances se derivan del HISTORIAL (último punto de la serie temporal), no de
// las columnas avance_fisico/avance_financiero del contrato. Esas columnas son un
// snapshot entero que solo se refresca cuando alguien guarda el formulario en
// Parámetros, así que pueden estar desactualizadas respecto a las actas y avances
// ya registrados. Derivar del historial garantiza que el scatter y la gráfica de
// evolución muestren exactamente los mismos números.
export const aplanarContratos = (proyectos) => {
    if (!Array.isArray(proyectos)) return [];

    return proyectos.flatMap((proyecto) =>
        (proyecto.contratos || []).map((contrato) => {
            const serie = construirSerieTemporal(contrato);
            // Sin historial (o sin valor vigente) no hay nada que comparar: el
            // contrato queda fuera del scatter y se lista aparte.
            const sinDatos = serie.length === 0;
            const ultimo = sinDatos ? null : serie[serie.length - 1];

            const avanceFisico = ultimo ? ultimo.fisico : 0;
            const avanceFinanciero = ultimo ? ultimo.financiero : 0;
            const desfase = ultimo ? ultimo.brecha : 0;

            return {
                ...contrato,
                nombreProyecto: proyecto.nombre,
                proyectoId: proyecto.id,
                avanceFisico,
                avanceFinanciero,
                valorNumerico: numero(contrato.valor),
                desfase,
                severidad: severidadDesfase(desfase),
                sinDatos,
                serie,
            };
        })
    );
};

