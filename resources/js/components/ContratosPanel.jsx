import React, { useState, useMemo } from 'react';
import {
    ResponsiveContainer, ScatterChart, Scatter, XAxis, YAxis, ZAxis,
    CartesianGrid, Tooltip, Legend, ReferenceLine,
    LineChart, Line,
} from 'recharts';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faXmark } from '@fortawesome/free-solid-svg-icons';
import { aplanarContratos, COLOR_SEVERIDAD } from '../utils/avanceContratos';
import { COLORES_GRAFICA, EJE, LEYENDA, REJILLA, TOOLTIP } from '../utils/temaGraficas';
import { filasEnSala, formatearMillones } from '../utils/tablero';

const ETIQUETA_SEVERIDAD = {
    sano: 'Sano (≤10)',
    atencion: 'Atención (11-25)',
    critico: 'Crítico (>25)',
};

const TooltipScatter = ({ active, payload }) => {
    if (!active || !payload || !payload.length) return null;
    const c = payload[0].payload;
    return (
        <div className="tb-tooltip">
            <strong>{c.n_contrato}</strong>
            <span className="tb-tenue">{c.nombreProyecto}</span>
            <span>Físico: {c.avanceFisico}%</span>
            <span>Financiero: {c.avanceFinanciero}%</span>
            <span style={{ color: COLOR_SEVERIDAD[c.severidad], fontWeight: 700 }}>
                Desfase: {c.desfase > 0 ? '+' : ''}{c.desfase}
            </span>
        </div>
    );
};

const ContratosPanel = ({ proyectos, compacto = false }) => {
    const [soloCriticos, setSoloCriticos] = useState(false);
    // Se guarda el id, no el objeto: así la selección sigue apuntando al dato
    // vigente cuando `proyectos` se refresca, y no se contamina con la geometría
    // que Recharts adjunta al payload del clic.
    const [idSeleccionado, setIdSeleccionado] = useState(null);

    const contratos = useMemo(() => aplanarContratos(proyectos), [proyectos]);

    const conDatos = contratos.filter((c) => !c.sinDatos);
    const sinDatos = contratos.filter((c) => c.sinDatos);

    const totalValor = contratos.reduce((s, c) => s + c.valorNumerico, 0);

    const promedioFisico = conDatos.length
        ? Math.round(conDatos.reduce((s, c) => s + c.avanceFisico, 0) / conDatos.length)
        : 0;

    const criticos = conDatos.filter((c) => c.severidad === 'critico');

    const porSeveridad = ['sano', 'atencion', 'critico'].map((sev) => ({
        severidad: sev,
        datos: conDatos.filter((c) => c.severidad === sev),
    })).filter((g) => g.datos.length > 0);

    const ordenadas = [...(soloCriticos ? criticos : conDatos)].sort((a, b) => b.desfase - a.desfase);
    const filasTabla = compacto ? ordenadas.slice(0, filasEnSala(window.innerHeight, window.innerWidth)) : ordenadas;

    // Se deriva de la lista vigente para no quedar con datos obsoletos.
    const contratoSeleccionado = idSeleccionado
        ? contratos.find((c) => c.id === idSeleccionado) || null
        : null;
    // La serie ya viene calculada por aplanarContratos: el scatter y esta gráfica
    // salen de la misma fuente, así que sus números siempre coinciden.
    const serie = contratoSeleccionado?.serie ?? [];

    if (contratos.length === 0) {
        return <div className="tb-tarjeta gp-panel"><p className="gp-vacio">No hay contratos registrados.</p></div>;
    }

    return (
        <div className={`tb-contratos${compacto ? ' compacto' : ''}`}>
            {!compacto && (
                <div className="tb-mini-kpis">
                    <div className="tb-kpi">
                        <span className="gp-etiqueta">Contratos</span>
                        <span className="tb-kpi-valor">{contratos.length}</span>
                    </div>
                    <div className="tb-kpi">
                        <span className="gp-etiqueta">Valor total</span>
                        <span className="tb-kpi-valor">{formatearMillones(totalValor)}</span>
                    </div>
                    <div className="tb-kpi">
                        <span className="gp-etiqueta">Avance físico promedio</span>
                        <span className="tb-kpi-valor">{promedioFisico}%</span>
                    </div>
                    <div className={`tb-kpi${criticos.length ? ' alerta' : ''}`}>
                        <span className="gp-etiqueta">Con desfase alto</span>
                        <span className="tb-kpi-valor">{criticos.length}</span>
                    </div>
                </div>
            )}

            <section className="tb-tarjeta tb-contratos-grafica gp-panel">
                <h3>Avance físico vs. financiero</h3>
                {conDatos.length === 0 ? (
                    <p className="gp-vacio">Ningún contrato tiene avances registrados todavía.</p>
                ) : (
                    <div className="tb-lienzo-grafica">
                        <ResponsiveContainer width="100%" height="100%">
                            {/* bottom deja sitio para la etiqueta del eje X y, debajo, la leyenda */}
                            <ScatterChart margin={{ top: 10, right: 20, bottom: 45, left: 10 }}>
                                <CartesianGrid {...REJILLA} />
                                <XAxis {...EJE} type="number" dataKey="avanceFisico" name="Físico" domain={[0, 100]}
                                    label={{ value: 'Avance físico %', position: 'insideBottom', offset: -18, fontSize: 12, fill: COLORES_GRAFICA.tenue }} />
                                <YAxis {...EJE} type="number" dataKey="avanceFinanciero" name="Financiero" domain={[0, 100]}
                                    label={{ value: 'Avance financiero %', angle: -90, position: 'insideLeft', fontSize: 12, fill: COLORES_GRAFICA.tenue }} />
                                <ZAxis range={[110, 110]} />
                                <ReferenceLine segment={[{ x: 0, y: 0 }, { x: 100, y: 100 }]} stroke={COLORES_GRAFICA.tenue} strokeDasharray="5 5" />
                                <Tooltip content={<TooltipScatter />} />
                                {/* bottom: 0 ancla la leyenda al pie del contenedor, por debajo
                                    de la etiqueta del eje X (que vive dentro del área del gráfico) */}
                                <Legend verticalAlign="bottom" wrapperStyle={{ ...LEYENDA.wrapperStyle, bottom: 0 }} />
                                {porSeveridad.map(({ severidad, datos }) => (
                                    <Scatter
                                        key={severidad}
                                        name={ETIQUETA_SEVERIDAD[severidad]}
                                        data={datos}
                                        fill={COLOR_SEVERIDAD[severidad]}
                                        onClick={(punto) => setIdSeleccionado((punto?.payload ?? punto)?.id ?? null)}
                                        cursor="pointer"
                                    />
                                ))}
                            </ScatterChart>
                        </ResponsiveContainer>
                    </div>
                )}
            </section>

            <section className="tb-tarjeta tb-contratos-detalle gp-panel">
                <div className="tb-tarjeta-cabecera">
                    <h3>{compacto ? 'Mayor desfase' : 'Detalle de contratos'}</h3>
                    {!compacto && (
                        <div className="tb-filtro-chips">
                            <button type="button" className={!soloCriticos ? 'activo' : ''} onClick={() => setSoloCriticos(false)}>Todos</button>
                            <button type="button" className={soloCriticos ? 'activo peligro' : ''} onClick={() => setSoloCriticos(true)}>
                                Solo críticos ({criticos.length})
                            </button>
                        </div>
                    )}
                </div>

                {filasTabla.length === 0 && (compacto || sinDatos.length === 0) ? (
                    <p className="gp-vacio">{soloCriticos ? 'Ningún contrato con desfase crítico.' : 'Ningún contrato tiene avances registrados todavía.'}</p>
                ) : (
                <table className="tb-tabla tb-tabla-cartas">
                    <thead>
                        <tr>
                            <th>Contrato</th>
                            <th>Proyecto</th>
                            <th className="numero">Valor</th>
                            <th className="numero">Físico</th>
                            <th className="numero">Financiero</th>
                            <th className="numero">Desfase</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filasTabla.map((c) => (
                            <tr
                                key={c.id}
                                onClick={() => setIdSeleccionado(c.id)}
                                className={`clicable${idSeleccionado === c.id ? ' seleccionada' : ''}${c.severidad === 'critico' ? ' critica' : ''}`}
                            >
                                <td data-etiqueta="Contrato"><button type="button" className="tb-fila-enlace">{c.n_contrato}</button></td>
                                <td data-etiqueta="Proyecto" className="tb-tenue">{c.nombreProyecto}</td>
                                <td data-etiqueta="Valor" className="numero">{formatearMillones(c.valorNumerico)}</td>
                                <td data-etiqueta="Físico" className="numero">{c.avanceFisico}%</td>
                                <td data-etiqueta="Financiero" className="numero">{c.avanceFinanciero}%</td>
                                <td data-etiqueta="Desfase" className="numero">
                                    <span className={`tb-pastilla sev-${c.severidad}`}>{c.desfase > 0 ? '+' : ''}{c.desfase}</span>
                                </td>
                            </tr>
                        ))}
                        {!compacto && sinDatos.map((c) => (
                            <tr key={c.id} className="sin-datos">
                                <td data-etiqueta="Contrato">{c.n_contrato}</td>
                                <td data-etiqueta="Proyecto">{c.nombreProyecto}</td>
                                <td data-etiqueta="Valor" className="numero">{formatearMillones(c.valorNumerico)}</td>
                                <td data-etiqueta="Avance" colSpan="3" className="numero">Sin datos de avance</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                )}
            </section>

            {!compacto && contratoSeleccionado && (
                <section className="tb-tarjeta gp-panel">
                    <div className="tb-tarjeta-cabecera">
                        <h3>
                            Evolución de {contratoSeleccionado.n_contrato}
                            <span className="tb-tenue"> · {contratoSeleccionado.nombreProyecto}</span>
                        </h3>
                        <button type="button" className="gp-icono" onClick={() => setIdSeleccionado(null)} aria-label="Cerrar evolución">
                            <FontAwesomeIcon icon={faXmark} />
                        </button>
                    </div>
                    {serie.length < 2 ? (
                        <p className="gp-vacio">Datos insuficientes para graficar la evolución.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={280}>
                            <LineChart data={serie} margin={{ top: 10, right: 20, bottom: 10, left: 0 }}>
                                <CartesianGrid {...REJILLA} />
                                <XAxis {...EJE} dataKey="fecha" />
                                <YAxis {...EJE} domain={[0, 100]} />
                                <Tooltip {...TOOLTIP} cursor={{ stroke: COLORES_GRAFICA.tenue }} formatter={(valor, nombre) => [`${valor}%`, nombre]} />
                                <Legend {...LEYENDA} />
                                <Line type="monotone" dataKey="fisico" name="Físico" stroke={COLORES_GRAFICA.azul} strokeWidth={2.5} dot={{ r: 3 }} />
                                <Line type="monotone" dataKey="financiero" name="Financiero" stroke={COLORES_GRAFICA.ambar} strokeWidth={2.5} dot={{ r: 3 }} />
                            </LineChart>
                        </ResponsiveContainer>
                    )}
                </section>
            )}
        </div>
    );
};

export default ContratosPanel;
