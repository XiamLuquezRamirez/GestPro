import React, { useState } from 'react';
import {
    ResponsiveContainer, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
    PieChart, Pie, Cell,
} from 'recharts';
import { calcularCadenaPresupuestal } from '../utils/cadenaPresupuestal';

const formatearPresupuesto = (valor) => '$' + Math.round((valor || 0) / 1_000_000).toLocaleString('es-CO') + ' M';

const PALETA_MUNICIPIOS = ['#1976d2', '#43a047', '#fb8c00', '#8e24aa', '#00acc1', '#c62828', '#6d4c41', '#546e7a'];
const MESES_CORTOS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

const FILTRO_INICIAL = {
    municipio: '',
    fase: '',
    estado: '',
    fechaDesde: '',
    fechaHasta: '',
};

const Estadisticas = ({ proyectos }) => {
    const [filtro, setFiltro] = useState(FILTRO_INICIAL);

    const municipiosUnicos = [...new Set(proyectos.map(p => p.descripcion_municipio).filter(Boolean))].sort();
    const fasesUnicas = [...new Set(proyectos.map(p => p.descripcion_fase).filter(Boolean))].sort();
    const estadosUnicos = [...new Set(proyectos.map(p => p.descripcion_estado).filter(Boolean))].sort();

    const proyectosFiltrados = proyectos.filter(p => {
        if (filtro.municipio && p.descripcion_municipio !== filtro.municipio) return false;
        if (filtro.fase && p.descripcion_fase !== filtro.fase) return false;
        if (filtro.estado && p.descripcion_estado !== filtro.estado) return false;
        if (filtro.fechaDesde && (!p.fecha_inicio || p.fecha_inicio < filtro.fechaDesde)) return false;
        if (filtro.fechaHasta && (!p.fecha_inicio || p.fecha_inicio > filtro.fechaHasta)) return false;
        return true;
    });

    const cadena = calcularCadenaPresupuestal(proyectosFiltrados);
    // La barra más larga define la escala visual; las otras dos se leen en proporción.
    const maxCadena = Math.max(cadena.planeado, cadena.contratado, cadena.ejecutado);
    const anchoBarra = (valor) => (maxCadena > 0 ? `${(valor / maxCadena) * 100}%` : '0%');
    const hayFiltroActivo = Boolean(filtro.municipio || filtro.fase || filtro.estado || filtro.fechaDesde || filtro.fechaHasta);

    const colorPorMunicipio = {};
    municipiosUnicos.forEach((m, i) => {
        colorPorMunicipio[m] = PALETA_MUNICIPIOS[i % PALETA_MUNICIPIOS.length];
    });

    const dataPorEstado = Object.values(
        proyectosFiltrados.reduce((acc, p) => {
            const estado = p.descripcion_estado || 'Sin estado';
            if (!acc[estado]) {
                acc[estado] = { estado, cantidad: 0, color: p.color_estado || '#9e9e9e' };
            }
            acc[estado].cantidad++;
            return acc;
        }, {})
    );

    const dataPorFase = Object.values(
        proyectosFiltrados.reduce((acc, p) => {
            const fase = p.descripcion_fase || 'Sin fase';
            if (!acc[fase]) {
                acc[fase] = { fase, cantidad: 0, color: p.color_fase || '#9e9e9e' };
            }
            acc[fase].cantidad++;
            return acc;
        }, {})
    );

    const dataPorMunicipio = Object.values(
        proyectosFiltrados.reduce((acc, p) => {
            const municipio = p.descripcion_municipio || 'Sin municipio';
            if (!acc[municipio]) {
                acc[municipio] = { municipio, cantidad: 0 };
            }
            acc[municipio].cantidad++;
            return acc;
        }, {})
    ).sort((a, b) => b.cantidad - a.cantidad);

    const dataPresupuestoPorMunicipio = Object.values(
        proyectosFiltrados.reduce((acc, p) => {
            const municipio = p.descripcion_municipio || 'Sin municipio';
            if (!acc[municipio]) {
                acc[municipio] = { municipio, presupuesto: 0 };
            }
            acc[municipio].presupuesto += parseFloat(p.presupuesto) || 0;
            return acc;
        }, {})
    ).sort((a, b) => b.presupuesto - a.presupuesto);

    const dataAvancePorFase = Object.values(
        proyectosFiltrados.reduce((acc, p) => {
            const fase = p.descripcion_fase || 'Sin fase';
            if (!acc[fase]) {
                acc[fase] = { fase, sumaAvance: 0, cantidad: 0, color: p.color_fase || '#9e9e9e' };
            }
            acc[fase].sumaAvance += parseInt(p.progreso, 10) || 0;
            acc[fase].cantidad++;
            return acc;
        }, {})
    ).map(item => ({
        fase: item.fase,
        color: item.color,
        avancePromedio: Math.round(item.sumaAvance / item.cantidad),
    }));

   
    const dataPorMes = Object.values(
        proyectosFiltrados
            .filter(p => p.fecha_inicio)
            .reduce((acc, p) => {
                const [anio, mes] = p.fecha_inicio.split('-');
                const clave = `${anio}-${mes}`;
                if (!acc[clave]) {
                    acc[clave] = {
                        clave,
                        etiqueta: `${MESES_CORTOS[parseInt(mes, 10) - 1]} ${anio}`,
                        cantidad: 0,
                    };
                }
                acc[clave].cantidad++;
                return acc;
            }, {})
    ).sort((a, b) => a.clave.localeCompare(b.clave));

    const handleFiltroChange = (campo) => (e) => {
        setFiltro(prev => ({ ...prev, [campo]: e.target.value }));
    };

    const limpiarFiltros = () => setFiltro(FILTRO_INICIAL);

    return (
        <div className="estadisticas-content">
            <div className="filtros-barra">
                <div className="filtro-campo">
                    <label>Municipio</label>
                    <select value={filtro.municipio} onChange={handleFiltroChange('municipio')}>
                        <option value="">Todos</option>
                        {municipiosUnicos.map(m => <option key={m} value={m}>{m}</option>)}
                    </select>
                </div>
                <div className="filtro-campo">
                    <label>Fase</label>
                    <select value={filtro.fase} onChange={handleFiltroChange('fase')}>
                        <option value="">Todas</option>
                        {fasesUnicas.map(f => <option key={f} value={f}>{f}</option>)}
                    </select>
                </div>
                <div className="filtro-campo">
                    <label>Estado</label>
                    <select value={filtro.estado} onChange={handleFiltroChange('estado')}>
                        <option value="">Todos</option>
                        {estadosUnicos.map(e => <option key={e} value={e}>{e}</option>)}
                    </select>
                </div>
                <div className="filtro-campo">
                    <label>Desde</label>
                    <input type="date" value={filtro.fechaDesde} onChange={handleFiltroChange('fechaDesde')} />
                </div>
                <div className="filtro-campo">
                    <label>Hasta</label>
                    <input type="date" value={filtro.fechaHasta} onChange={handleFiltroChange('fechaHasta')} />
                </div>
                <button type="button" className="filtros-limpiar-btn" onClick={limpiarFiltros}>
                    Limpiar filtros
                </button>
            </div>

            <div className="cadena-presupuestal-card" style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 16, marginBottom: 16 }}>
                <h3 style={{ fontSize: 15, margin: '0 0 14px' }}>
                    💰 Cadena presupuestal
                    {hayFiltroActivo && (
                        <span style={{ fontWeight: 400, color: '#6b7280', fontSize: 12 }}>
                            {' '}— {proyectosFiltrados.length} proyecto(s) según los filtros
                        </span>
                    )}
                </h3>

                {proyectosFiltrados.length === 0 ? (
                    <p style={{ fontSize: 13, color: '#6b7280', margin: 0 }}>
                        Sin proyectos que coincidan con los filtros.
                    </p>
                ) : (
                    <>
                        <div style={{ marginBottom: 12 }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, marginBottom: 4 }}>
                                <span style={{ color: '#374151', fontWeight: 600 }}>📋 Planeado</span>
                                <span style={{ fontWeight: 700 }}>{formatearPresupuesto(cadena.planeado)}</span>
                            </div>
                            <div style={{ background: '#e5e7eb', borderRadius: 4, height: 22 }}>
                                <div style={{ background: '#64748b', width: anchoBarra(cadena.planeado), height: '100%', borderRadius: 4 }} />
                            </div>
                        </div>

                        <div style={{ marginBottom: 12 }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, marginBottom: 4 }}>
                                <span style={{ color: '#374151', fontWeight: 600 }}>📝 Contratado</span>
                                <span style={{ fontWeight: 700, color: '#2563eb' }}>
                                    {formatearPresupuesto(cadena.contratado)}
                                    {cadena.pctContratado !== null && (
                                        <span style={{ color: '#6b7280', fontSize: 11, fontWeight: 400 }}>
                                            {' '}({cadena.pctContratado}% de lo planeado)
                                        </span>
                                    )}
                                </span>
                            </div>
                            <div style={{ background: '#e5e7eb', borderRadius: 4, height: 22 }}>
                                <div style={{ background: '#2563eb', width: anchoBarra(cadena.contratado), height: '100%', borderRadius: 4 }} />
                            </div>
                        </div>

                        <div>
                            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, marginBottom: 4 }}>
                                <span style={{ color: '#374151', fontWeight: 600 }}>💵 Ejecutado (facturado)</span>
                                <span style={{ fontWeight: 700, color: '#16a34a' }}>
                                    {formatearPresupuesto(cadena.ejecutado)}
                                    {cadena.pctEjecutado !== null && (
                                        <span style={{ color: '#6b7280', fontSize: 11, fontWeight: 400 }}>
                                            {' '}({cadena.pctEjecutado}% de lo contratado)
                                        </span>
                                    )}
                                </span>
                            </div>
                            <div style={{ background: '#e5e7eb', borderRadius: 4, height: 22 }}>
                                <div style={{ background: '#16a34a', width: anchoBarra(cadena.ejecutado), height: '100%', borderRadius: 4 }} />
                            </div>
                        </div>

                        {cadena.excedente > 0 && (
                            <div style={{ background: '#fffbeb', borderLeft: '3px solid #f59e0b', padding: '9px 11px', marginTop: 14, fontSize: 12, color: '#78350f' }}>
                                ⚠️ Lo contratado supera lo planeado en <strong>{formatearPresupuesto(cadena.excedente)}</strong>.
                            </div>
                        )}
                    </>
                )}
            </div>

            <div className="graficas-grid">
                <div className="grafica-card">
                    <h3>Proyectos por Estado</h3>
                    {dataPorEstado.length === 0 ? (
                        <p className="estadisticas-sin-datos">Sin datos para el filtro actual.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={Math.max(200, dataPorEstado.length * 45)}>
                            <BarChart data={dataPorEstado} layout="vertical" margin={{ left: 20 }}>
                                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                <XAxis type="number" allowDecimals={false} />
                                <YAxis type="category" dataKey="estado" width={140} />
                                <Tooltip formatter={(value) => [`${value} proyectos (${Math.round((value / proyectosFiltrados.length) * 100)}%)`, '']} />
                                <Bar dataKey="cantidad" radius={[0, 4, 4, 0]}>
                                    {dataPorEstado.map(entry => (
                                        <Cell key={entry.estado} fill={entry.color} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </div>

                <div className="grafica-card">
                    <h3>Proyectos por Fase</h3>
                    {dataPorFase.length === 0 ? (
                        <p className="estadisticas-sin-datos">Sin datos para el filtro actual.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={260}>
                            <PieChart>
                                <Pie
                                    data={dataPorFase}
                                    dataKey="cantidad"
                                    nameKey="fase"
                                    innerRadius={60}
                                    outerRadius={90}
                                    paddingAngle={2}
                                >
                                    {dataPorFase.map(entry => (
                                        <Cell key={entry.fase} fill={entry.color} />
                                    ))}
                                </Pie>
                                <Tooltip formatter={(value, name) => [`${value} proyectos (${Math.round((value / proyectosFiltrados.length) * 100)}%)`, name]} />
                                <Legend formatter={(value) => {
                                    const entry = dataPorFase.find(d => d.fase === value);
                                    const pct = Math.round((entry.cantidad / proyectosFiltrados.length) * 100);
                                    return `${value}: ${entry.cantidad} (${pct}%)`;
                                }} />
                            </PieChart>
                        </ResponsiveContainer>
                    )}
                </div>

                <div className="grafica-card">
                    <h3>Proyectos por Municipio</h3>
                    {dataPorMunicipio.length === 0 ? (
                        <p className="estadisticas-sin-datos">Sin datos para el filtro actual.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={Math.max(200, dataPorMunicipio.length * 45)}>
                            <BarChart data={dataPorMunicipio} layout="vertical" margin={{ left: 20 }}>
                                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                <XAxis type="number" allowDecimals={false} />
                                <YAxis type="category" dataKey="municipio" width={120} />
                                <Tooltip formatter={(value) => [`${value} proyectos`, '']} />
                                <Bar dataKey="cantidad" radius={[0, 4, 4, 0]}>
                                    {dataPorMunicipio.map(entry => (
                                        <Cell key={entry.municipio} fill={colorPorMunicipio[entry.municipio] || '#9e9e9e'} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </div>

                <div className="grafica-card">
                    <h3>Presupuesto por Municipio</h3>
                    {dataPresupuestoPorMunicipio.length === 0 ? (
                        <p className="estadisticas-sin-datos">Sin datos para el filtro actual.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={Math.max(200, dataPresupuestoPorMunicipio.length * 45)}>
                            <BarChart data={dataPresupuestoPorMunicipio} layout="vertical" margin={{ left: 20 }}>
                                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                <XAxis type="number" tickFormatter={(v) => formatearPresupuesto(v)} />
                                <YAxis type="category" dataKey="municipio" width={120} />
                                <Tooltip formatter={(value) => [formatearPresupuesto(value), 'Presupuesto']} />
                                <Bar dataKey="presupuesto" radius={[0, 4, 4, 0]}>
                                    {dataPresupuestoPorMunicipio.map(entry => (
                                        <Cell key={entry.municipio} fill={colorPorMunicipio[entry.municipio] || '#9e9e9e'} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </div>

                <div className="grafica-card">
                    <h3>Avance promedio por Fase</h3>
                    {dataAvancePorFase.length === 0 ? (
                        <p className="estadisticas-sin-datos">Sin datos para el filtro actual.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={220}>
                            <BarChart data={dataAvancePorFase}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                <XAxis dataKey="fase" />
                                <YAxis domain={[0, 100]} tickFormatter={(v) => `${v}%`} />
                                <Tooltip formatter={(value) => [`${value}%`, 'Avance promedio']} />
                                <Bar dataKey="avancePromedio" radius={[4, 4, 0, 0]}>
                                    {dataAvancePorFase.map(entry => (
                                        <Cell key={entry.fase} fill={entry.color} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </div>

                <div className="grafica-card">
                    <h3>Proyectos iniciados por mes</h3>
                    {dataPorMes.length === 0 ? (
                        <p className="estadisticas-sin-datos">Sin datos para el filtro actual.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={220}>
                            <BarChart data={dataPorMes}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                <XAxis dataKey="etiqueta" />
                                <YAxis allowDecimals={false} />
                                <Tooltip formatter={(value) => [`${value} proyectos`, '']} />
                                <Bar dataKey="cantidad" fill="#1976d2" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </div>
            </div>

            <div className="estadisticas-tabla-section">
                <h3>Proyectos filtrados ({proyectosFiltrados.length})</h3>
                {proyectosFiltrados.length === 0 ? (
                    <p className="estadisticas-sin-datos">No hay proyectos que coincidan con los filtros seleccionados.</p>
                ) : (
                    <table className="estadisticas-tabla">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Municipio</th>
                                <th>Fase</th>
                                <th>Estado</th>
                                <th>Presupuesto</th>
                                <th>Avance</th>
                            </tr>
                        </thead>
                        <tbody>
                            {proyectosFiltrados.map(p => (
                                <tr key={p.id}>
                                    <td>{p.nombre}</td>
                                    <td>{p.descripcion_municipio}</td>
                                    <td>{p.descripcion_fase}</td>
                                    <td>
                                        <span className="tabla-estado-pastilla" style={{ backgroundColor: p.color_estado || '#9e9e9e' }}>
                                            {p.descripcion_estado}
                                        </span>
                                    </td>
                                    <td>{formatearPresupuesto(parseFloat(p.presupuesto))}</td>
                                    <td>{parseInt(p.progreso, 10) || 0}%</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
};

export default Estadisticas;
