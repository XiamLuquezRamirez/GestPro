// Montos en pesos de los formularios de Parámetros (componentes del presupuesto) y de contratos
// (actas y modificaciones): cómo se muestran, cómo se escriben y cómo se leen.
// Funciones puras, probadas en moneda.test.js.

const PESOS_CON_CENTAVOS = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/** Un monto con centavos: 1234567.5 → "$ 1.234.567,50". */
export const formatoMoneda = (monto) => PESOS_CON_CENTAVOS.format(monto);

/** El número de lo escrito en un campo de moneda ("$ 1.487.342,33" → 1487342.33); vacío o inválido, 0. */
export const numeroDeMoneda = (valor) => {
    if (valor === '' || valor === null || valor === undefined) return 0;
    const limpio = String(valor).replace(/[^\d,]/g, '');
    if (!limpio) return 0;
    const [enteros, ...decimalesPartes] = limpio.split(',');
    const decimales = decimalesPartes.join('');
    const normalizado = decimales ? `${enteros}.${decimales}` : enteros;
    const numero = parseFloat(normalizado);
    return isNaN(numero) ? 0 : numero;
};

/**
 * Lo que se escribe en un campo de moneda: solo dígitos y una coma decimal, con máximo 2 dígitos
 * después de la coma (formato "1487342187,33").
 */
export const limpiarMoneda = (valor) => {
    const soloCifras = valor.replace(/[^\d,]/g, '');
    const primeraComa = soloCifras.indexOf(',');
    if (primeraComa === -1) return soloCifras;
    const enteros = soloCifras.slice(0, primeraComa);
    const decimales = soloCifras.slice(primeraComa + 1).replace(/,/g, '').slice(0, 2);
    return `${enteros},${decimales}`;
};

/**
 * Un valor ya limpio, formateado en vivo: agrupa los miles de la parte entera y conserva la parte
 * decimal tal cual se está escribiendo (sin redondearla mientras se teclea).
 */
export const monedaEnEdicion = (limpio) => {
    const [enteros, decimales] = limpio.split(',');
    const agrupados = enteros ? String(parseInt(enteros, 10)).replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '0';
    return decimales !== undefined ? `$ ${agrupados},${decimales}` : `$ ${agrupados}`;
};
