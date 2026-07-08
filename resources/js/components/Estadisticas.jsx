import React, { useState } from 'react';

const formatearPresupuesto = (valor) => '$' + Math.round((valor || 0) / 1_000_000).toLocaleString('es-CO') + ' M';

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
