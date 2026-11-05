import React, { useMemo } from 'react';

const PUNTOS_POR_CURVA = 72;

// Cerros del terreno, uno por cada color del logo: centro, radio mayor, curvas y desfase de la forma.
const CERROS = [
    { x: 300, y: 340, radio: 300, curvas: 9, fase: .6, color: 'var(--gp-azul-texto)' },
    { x: 780, y: 720, radio: 260, curvas: 8, fase: 2.1, color: 'var(--gp-verde-texto)' },
    { x: 860, y: 140, radio: 170, curvas: 5, fase: 4, color: 'var(--gp-ambar)' },
];

const trazar = ({ x, y, fase }, radio) => {
    const puntos = Array.from({ length: PUNTOS_POR_CURVA }, (_, i) => {
        const angulo = (i / PUNTOS_POR_CURVA) * Math.PI * 2;
        const deformacion = 1 + .16 * Math.sin(angulo * 3 + fase) + .08 * Math.sin(angulo * 5 + fase * 1.7) + .04 * Math.cos(angulo * 8 + fase);
        return `${(x + Math.cos(angulo) * radio * deformacion).toFixed(1)} ${(y + Math.sin(angulo) * radio * deformacion).toFixed(1)}`;
    });
    return `M${puntos.join('L')}Z`;
};

/** Curvas de nivel de un plano topográfico: la firma visual de GestPro. */
const CurvasNivel = ({ className = '' }) => {
    const curvas = useMemo(() => CERROS.flatMap((cerro, c) => (
        Array.from({ length: cerro.curvas }, (_, i) => ({
            clave: `${c}-${i}`,
            d: trazar(cerro, cerro.radio * (1 - i / cerro.curvas)),
            // La cota más alta de cada cerro se resalta, como en los mapas reales.
            cima: i === cerro.curvas - 1,
            retraso: i * .09 + c * .25,
            color: cerro.color,
        }))
    )), []);

    return (
        <svg className={`gp-curvas ${className}`} viewBox="0 0 1000 1000" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            {curvas.map((curva) => (
                <path
                    key={curva.clave}
                    d={curva.d}
                    pathLength="1"
                    className={curva.cima ? 'cima' : ''}
                    style={{ animationDelay: `${curva.retraso}s`, '--curva': curva.color }}
                />
            ))}
        </svg>
    );
};

export default CurvasNivel;
