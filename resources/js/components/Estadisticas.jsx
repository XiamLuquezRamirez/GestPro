import React, { useState } from 'react';
import {
    ResponsiveContainer, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
    PieChart, Pie, Cell,
} from 'recharts';

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
                                <Tooltip formatter={(value) => [`${value} proyectos`, '']} />
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
                                <Tooltip formatter={(value, name) => [`${value} proyectos`, name]} />
                                <Legend />
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
