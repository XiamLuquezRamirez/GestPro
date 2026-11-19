import React from 'react';
import { chipsAvisosProceso } from '../utils/avisosContratacion';

/**
 * Los chips de avisos de un proceso (`avisos` de la API): etapas vencidas y próximas, devueltas y
 * por revisar, cierre, sin actualización e hitos (etapas aprobadas). Los usan el diálogo de procesos del proyecto y la ficha. Sin nada que
 * mostrar, no pinta nada.
 */
const ChipsAvisosProceso = ({ avisos }) => {
    const chips = chipsAvisosProceso(avisos);
    if (chips.length === 0) return null;
    return (
        <ul className="ct-avisos" aria-label="Avisos del proceso">
            {chips.map((c) => (
                <li key={c.clave} className={`ct-estado ct-estado-${c.tono}`} title={c.titulo}>
                    {c.texto}<span className="ct-oculto"> ({c.titulo})</span>
                </li>
            ))}
        </ul>
    );
};

export default ChipsAvisosProceso;
