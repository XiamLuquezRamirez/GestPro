import React from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faChevronRight, faClipboardList, faDiagramProject, faGavel, faRocket, faTriangleExclamation,
} from '@fortawesome/free-solid-svg-icons';
import { formatearMillones } from '../utils/tablero';

// Las fases son un catálogo editable: una fase nueva recibe un emblema genérico.
const EMBLEMAS = { formulacion: faClipboardList, licitacion: faGavel, ejecucion: faRocket };

/** El ciclo de los proyectos: cada fase dice cómo va y, al pulsarla, abre su detalle. */
const FlujoFases = ({ fases, activa, onElegir }) => (
    <section className="tb-flujo" aria-label="Proyectos por fase">
        <ol className="tb-etapas">
            {fases.map((f, i) => (
                <li key={f.id}>
                    <button
                        type="button"
                        className={`tb-etapa${activa === f.slug ? ' activa' : ''}`}
                        style={{ '--color-fase': f.color }}
                        aria-pressed={activa === f.slug}
                        onClick={() => onElegir(f.slug)}
                    >
                        <span className="tb-etapa-cabeza">
                            <span className="tb-etapa-chip">Fase {i + 1}</span>
                            {f.enRiesgo > 0 && (
                                <span className="tb-etapa-riesgo">
                                    <FontAwesomeIcon icon={faTriangleExclamation} /> {f.enRiesgo} en riesgo
                                </span>
                            )}
                            <FontAwesomeIcon className="tb-etapa-flecha" icon={faChevronRight} />
                        </span>

                        <span className="tb-etapa-cuerpo">
                            <span className="tb-etapa-emblema"><FontAwesomeIcon icon={EMBLEMAS[f.slug] ?? faDiagramProject} /></span>
                            <span className="tb-etapa-texto">
                                <span className="tb-etapa-nombre">{f.nombre}</span>
                                <span className="tb-etapa-cifras">
                                    <b>{f.cantidad}</b>
                                    <span>{f.cantidad === 1 ? 'proyecto' : 'proyectos'}<br />{formatearMillones(f.presupuesto)}</span>
                                </span>
                            </span>
                            <span className={`tb-etapa-pct${f.metrica.pct === null ? ' vacio' : ''}`}>
                                {f.metrica.pct === null ? 'Sin avance' : `${f.metrica.pct}%`}
                            </span>
                        </span>

                        <span className="tb-pista"><span style={{ width: `${Math.min(f.metrica.pct ?? 0, 100)}%` }} /></span>
                        {/* La etiqueta aclara qué mide: en Formulación son requisitos, no avance técnico. */}
                        <span className="tb-etapa-detalle">{f.metrica.etiqueta} · {f.metrica.detalle}</span>
                    </button>
                </li>
            ))}
        </ol>
    </section>
);

export default FlujoFases;
