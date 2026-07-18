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

// Un contrato "sin datos" no tiene ni avance físico ni financiero registrados:
// graficarlo en (0,0) distorsiona la lectura del scatter.
const sinDatosDeAvance = (contrato) =>
    (contrato?.avance_fisico === null || contrato?.avance_fisico === undefined || contrato?.avance_fisico === '') &&
    (contrato?.avance_financiero === null || contrato?.avance_financiero === undefined || contrato?.avance_financiero === '');

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
