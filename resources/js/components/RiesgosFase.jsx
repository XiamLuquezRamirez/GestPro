import React from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faTriangleExclamation } from '@fortawesome/free-solid-svg-icons';
import { formatearMillones } from '../utils/tablero';

/** Lo que pide atención en la fase. Sin retrasos no ocupa espacio: no hay nada que decir. */
const RiesgosFase = ({ proyectos, onProyectoClick, limite = null }) => {
    const enRiesgo = proyectos.filter((p) => p.descripcion_estado === 'Con retraso');

    if (enRiesgo.length === 0) return null;

    // En la sala no se puede desplazar: el contador sigue mostrando el total real.
    const visibles = limite ? enRiesgo.slice(0, limite) : enRiesgo;

    return (
        <aside className="tb-tarjeta tb-riesgo-tarjeta gp-panel">
            <h3 className="tb-titulo-icono">
                <FontAwesomeIcon icon={faTriangleExclamation} /> Requieren atención <span className="tb-contador">{enRiesgo.length}</span>
            </h3>
            <ul className="tb-riesgos">
                {visibles.map((p) => (
                    <li key={p.id}>
                        <button type="button" onClick={() => onProyectoClick(p)}>
                            <span className="tb-riesgo-nombre">{p.nombre}</span>
                            <span className="tb-riesgo-detalle">{p.descripcion_municipio} · {formatearMillones(parseFloat(p.presupuesto))}</span>
                        </button>
                    </li>
                ))}
            </ul>
            {visibles.length < enRiesgo.length && <p className="tb-nota">y {enRiesgo.length - visibles.length} más</p>}
        </aside>
    );
};

export default RiesgosFase;
