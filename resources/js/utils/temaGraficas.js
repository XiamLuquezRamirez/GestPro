// Recharts dibuja SVG con atributos propios: sus colores no se pueden fijar con CSS.
export const COLORES_GRAFICA = {
    azul: '#018EB2',
    verde: '#3EAB2B',
    ambar: '#ECA200',
    coral: '#FF7A5C',
    tenue: '#8BA2AC',
};

export const PALETA_CATEGORIAS = ['#018EB2', '#3EAB2B', '#ECA200', '#38BBDC', '#62C952', '#F5C75A', '#8BA2AC', '#7ED6EC'];

export const EJE = {
    tick: { fill: '#B4C6CE', fontSize: 12 },
    axisLine: { stroke: '#35616F' },
    tickLine: { stroke: '#35616F' },
};

export const REJILLA = { stroke: '#234858', strokeDasharray: '3 3' };

export const TOOLTIP = {
    contentStyle: {
        background: '#133241',
        border: '1px solid #35616F',
        borderRadius: 6,
        color: '#EEF5F7',
        fontSize: 13,
    },
    itemStyle: { color: '#EEF5F7' },
    labelStyle: { color: '#B4C6CE' },
    cursor: { fill: 'rgba(1, 142, 178, .12)' },
};

export const LEYENDA = { wrapperStyle: { color: '#B4C6CE', fontSize: 12 } };

/** En un celular, los nombres largos de las barras no pueden comerse la gráfica. */
export const anchoEjeCategorias = (ancho = 140) => (window.innerWidth < 600 ? Math.min(ancho, 92) : ancho);
