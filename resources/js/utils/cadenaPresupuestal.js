// Cálculo de la cadena presupuestal: Planeado → Contratado → Ejecutado.
// Función pura, probada en cadenaPresupuestal.test.js.

const numero = (valor) => {
    const n = parseFloat(valor);
    return Number.isFinite(n) ? n : 0;
};

// Porcentaje redondeado de parte sobre total. Devuelve null si el total es 0
// para no dividir por cero ni mostrar Infinity/NaN en la interfaz.
const porcentaje = (parte, total) => (total > 0 ? Math.round((parte / total) * 100) : null);

/**
 * Suma los tres niveles presupuestales de una lista de proyectos.
 *
 * - planeado:   suma de proyectos.presupuesto
 * - contratado: suma de contratos.valor (valor vigente, ya incluye adiciones)
 * - ejecutado:  suma de avancesFinancieros.valor_facturado
 *
 * Devuelve además los porcentajes de avance de la cadena y el excedente de
 * contratación sobre lo planeado (0 si no hay excedente).
 */
export const calcularCadenaPresupuestal = (proyectos) => {
    const lista = Array.isArray(proyectos) ? proyectos : [];

    let planeado = 0;
    let contratado = 0;
    let ejecutado = 0;

    for (const proyecto of lista) {
        planeado += numero(proyecto?.presupuesto);

        for (const contrato of (proyecto?.contratos || [])) {
            contratado += numero(contrato?.valor);

            for (const acta of (contrato?.avancesFinancieros || [])) {
                ejecutado += numero(acta?.valor_facturado);
            }
        }
    }

    return {
        planeado,
        contratado,
        ejecutado,
        pctContratado: porcentaje(contratado, planeado),
        pctEjecutado: porcentaje(ejecutado, contratado),
        excedente: Math.max(0, contratado - planeado),
    };
};
