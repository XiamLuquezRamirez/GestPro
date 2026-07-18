import React, { useState, useMemo } from 'react';
import {
    ResponsiveContainer, ScatterChart, Scatter, XAxis, YAxis, ZAxis,
    CartesianGrid, Tooltip, Legend, ReferenceLine,
    LineChart, Line,
} from 'recharts';
import { aplanarContratos, construirSerieTemporal, COLOR_SEVERIDAD } from '../utils/avanceContratos';

const formatearMoneda = (valor) => '$' + Math.round((valor || 0) / 1_000_000).toLocaleString('es-CO') + ' M';

const ETIQUETA_SEVERIDAD = {
    sano: 'Sano (≤10)',
    atencion: 'Atención (11-25)',
    critico: 'Crítico (>25)',
};

const TooltipScatter = ({ active, payload }) => {
    if (!active || !payload || !payload.length) return null;
    const c = payload[0].payload;
    return (
        <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 6, padding: '8px 10px', fontSize: 12 }}>
            <strong>{c.n_contrato}</strong><br />
            <span style={{ color: '#6b7280' }}>{c.nombreProyecto}</span><br />
            Físico: {c.avanceFisico}%<br />
            Financiero: {c.avanceFinanciero}%<br />
            <span style={{ color: COLOR_SEVERIDAD[c.severidad], fontWeight: 700 }}>
                Desfase: {c.desfase > 0 ? '+' : ''}{c.desfase}
            </span>
        </div>
    );
};

const ContratosPanel = ({ proyectos }) => {
    const [soloCriticos, setSoloCriticos] = useState(false);
    const [contratoSeleccionado, setContratoSeleccionado] = useState(null);

    const contratos = useMemo(() => aplanarContratos(proyectos), [proyectos]);

    const conDatos = contratos.filter(c => !c.sinDatos);
    const sinDatos = contratos.filter(c => c.sinDatos);

    const totalValor = contratos.reduce((s, c) => s + c.valorNumerico, 0);
    const promedioFisico = conDatos.length
        ? Math.round(conDatos.reduce((s, c) => s + c.avanceFisico, 0) / conDatos.length)
        : 0;
    const criticos = conDatos.filter(c => c.severidad === 'critico');

    const porSeveridad = ['sano', 'atencion', 'critico'].map(sev => ({
        severidad: sev,
        datos: conDatos.filter(c => c.severidad === sev),
    })).filter(g => g.datos.length > 0);

    const filasTabla = [...(soloCriticos ? criticos : conDatos)].sort((a, b) => b.desfase - a.desfase);

    const serie = contratoSeleccionado ? construirSerieTemporal(contratoSeleccionado) : [];

    if (contratos.length === 0) {
        return <div className="contratos-panel"><p>No hay contratos registrados.</p></div>;
    }

    return (
        <div className="contratos-panel">

            <div className="contratos-kpis" style={{ display: 'flex', gap: 12, marginBottom: 16, flexWrap: 'wrap' }}>
                <div style={{ flex: 1, minWidth: 140, background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 12 }}>
                    <div style={{ fontSize: 11, color: '#6b7280', textTransform: 'uppercase' }}>Contratos</div>
                    <div style={{ fontSize: 22, fontWeight: 700 }}>{contratos.length}</div>
                </div>
                <div style={{ flex: 1, minWidth: 140, background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 12 }}>
                    <div style={{ fontSize: 11, color: '#6b7280', textTransform: 'uppercase' }}>Valor total</div>
                    <div style={{ fontSize: 22, fontWeight: 700 }}>{formatearMoneda(totalValor)}</div>
                </div>
                <div style={{ flex: 1, minWidth: 140, background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 12 }}>
                    <div style={{ fontSize: 11, color: '#6b7280', textTransform: 'uppercase' }}>Avance físico prom.</div>
                    <div style={{ fontSize: 22, fontWeight: 700, color: '#2563eb' }}>{promedioFisico}%</div>
                </div>
                <div style={{ flex: 1, minWidth: 140, background: '#fff', border: `1px solid ${criticos.length ? '#fca5a5' : '#e5e7eb'}`, borderRadius: 8, padding: 12 }}>
                    <div style={{ fontSize: 11, color: '#6b7280', textTransform: 'uppercase' }}>Con desfase alto</div>
                    <div style={{ fontSize: 22, fontWeight: 700, color: criticos.length ? '#dc2626' : '#111827' }}>{criticos.length}</div>
                </div>
            </div>

            <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 14, marginBottom: 16 }}>
                <h3 style={{ fontSize: 14, margin: '0 0 8px' }}>Avance Físico vs Financiero</h3>
                {conDatos.length === 0 ? (
                    <p style={{ fontSize: 13, color: '#6b7280' }}>Ningún contrato tiene avances registrados todavía.</p>
                ) : (
                    <ResponsiveContainer width="100%" height={340}>
                        <ScatterChart margin={{ top: 10, right: 20, bottom: 20, left: 10 }}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis type="number" dataKey="avanceFisico" name="Físico" domain={[0, 100]}
                                label={{ value: 'Avance Físico %', position: 'insideBottom', offset: -10, fontSize: 12 }} />
                            <YAxis type="number" dataKey="avanceFinanciero" name="Financiero" domain={[0, 100]}
                                label={{ value: 'Avance Financiero %', angle: -90, position: 'insideLeft', fontSize: 12 }} />
                            <ZAxis range={[90, 90]} />
                            <ReferenceLine segment={[{ x: 0, y: 0 }, { x: 100, y: 100 }]} stroke="#94a3b8" strokeDasharray="5 5" />
                            <Tooltip content={<TooltipScatter />} />
                            <Legend />
                            {porSeveridad.map(({ severidad, datos }) => (
                                <Scatter
                                    key={severidad}
                                    name={ETIQUETA_SEVERIDAD[severidad]}
                                    data={datos}
                                    fill={COLOR_SEVERIDAD[severidad]}
                                    onClick={(punto) => setContratoSeleccionado(punto)}
                                    cursor="pointer"
                                />
                            ))}
                        </ScatterChart>
                    </ResponsiveContainer>
                )}
            </div>

            <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 14 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
                    <h3 style={{ fontSize: 14, margin: 0 }}>Detalle de contratos</h3>
                    <div style={{ display: 'flex', gap: 6 }}>
                        <button type="button" onClick={() => setSoloCriticos(false)}
                            style={{ fontSize: 11, padding: '4px 11px', borderRadius: 11, border: 'none', cursor: 'pointer', background: soloCriticos ? '#f3f4f6' : '#2563eb', color: soloCriticos ? '#6b7280' : '#fff' }}>
                            Todos
                        </button>
                        <button type="button" onClick={() => setSoloCriticos(true)}
                            style={{ fontSize: 11, padding: '4px 11px', borderRadius: 11, border: 'none', cursor: 'pointer', background: soloCriticos ? '#dc2626' : '#f3f4f6', color: soloCriticos ? '#fff' : '#6b7280' }}>
                            Solo críticos ({criticos.length})
                        </button>
                    </div>
                </div>

                <table style={{ width: '100%', fontSize: 12, borderCollapse: 'collapse' }}>
                    <thead>
                        <tr style={{ borderBottom: '2px solid #e5e7eb', color: '#6b7280', fontSize: 11, textTransform: 'uppercase' }}>
                            <th style={{ textAlign: 'left', padding: '6px 4px' }}>Contrato</th>
                            <th style={{ textAlign: 'left', padding: '6px 4px' }}>Proyecto</th>
                            <th style={{ padding: '6px 4px' }}>Valor</th>
                            <th style={{ padding: '6px 4px' }}>Físico</th>
                            <th style={{ padding: '6px 4px' }}>Financiero</th>
                            <th style={{ padding: '6px 4px' }}>Desfase</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filasTabla.map(c => (
                            <tr key={c.id}
                                onClick={() => setContratoSeleccionado(c)}
                                style={{
                                    borderBottom: '1px solid #f3f4f6', cursor: 'pointer',
                                    background: contratoSeleccionado?.id === c.id ? '#eff6ff' : (c.severidad === 'critico' ? '#fef2f2' : 'transparent'),
                                }}>
                                <td style={{ padding: '7px 4px', fontWeight: 600 }}>{c.n_contrato}</td>
                                <td style={{ padding: '7px 4px', color: '#6b7280' }}>{c.nombreProyecto}</td>
                                <td align="center">{formatearMoneda(c.valorNumerico)}</td>
                                <td align="center">{c.avanceFisico}%</td>
                                <td align="center">{c.avanceFinanciero}%</td>
                                <td align="center">
                                    <span style={{
                                        background: c.severidad === 'critico' ? '#fecaca' : c.severidad === 'atencion' ? '#fed7aa' : '#dcfce7',
                                        color: c.severidad === 'critico' ? '#991b1b' : c.severidad === 'atencion' ? '#9a3412' : '#166534',
                                        padding: '2px 9px', borderRadius: 10, fontWeight: 700,
                                    }}>
                                        {c.desfase > 0 ? '+' : ''}{c.desfase}
                                    </span>
                                </td>
                            </tr>
                        ))}
                        {sinDatos.map(c => (
                            <tr key={c.id} style={{ borderBottom: '1px solid #f3f4f6', color: '#9ca3af' }}>
                                <td style={{ padding: '7px 4px', fontWeight: 600 }}>{c.n_contrato}</td>
                                <td style={{ padding: '7px 4px' }}>{c.nombreProyecto}</td>
                                <td align="center">{formatearMoneda(c.valorNumerico)}</td>
                                <td align="center" colSpan="3" style={{ fontStyle: 'italic' }}>Sin datos de avance</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {contratoSeleccionado && (
                <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 14, marginTop: 16 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                        <h3 style={{ fontSize: 14, margin: 0 }}>
                            Evolución de {contratoSeleccionado.n_contrato}
                            <span style={{ color: '#6b7280', fontWeight: 400 }}> — {contratoSeleccionado.nombreProyecto}</span>
                        </h3>
                        <button type="button" onClick={() => setContratoSeleccionado(null)}
                            style={{ border: 'none', background: 'transparent', cursor: 'pointer', fontSize: 18, color: '#6b7280' }}>×</button>
                    </div>
                    {serie.length < 2 ? (
                        <p style={{ fontSize: 13, color: '#6b7280' }}>Datos insuficientes para graficar la evolución.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={280}>
                            <LineChart data={serie} margin={{ top: 10, right: 20, bottom: 10, left: 0 }}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="fecha" fontSize={11} />
                                <YAxis domain={[0, 100]} fontSize={11} />
                                <Tooltip formatter={(valor, nombre) => [`${valor}%`, nombre]} />
                                <Legend />
                                <Line type="monotone" dataKey="fisico" name="Físico" stroke="#2563eb" strokeWidth={2.5} dot={{ r: 3 }} />
                                <Line type="monotone" dataKey="financiero" name="Financiero" stroke="#16a34a" strokeWidth={2.5} dot={{ r: 3 }} />
                            </LineChart>
                        </ResponsiveContainer>
                    )}
                </div>
            )}

        </div>
    );
};

export default ContratosPanel;
