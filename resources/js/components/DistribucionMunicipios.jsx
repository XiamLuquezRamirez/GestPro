import React, { useMemo } from 'react';

const formatearPresupuesto = (valor) => '$' + Math.round(valor / 1_000_000).toLocaleString('es-CO') + ' M';

const DistribucionMunicipios = ({ proyectos, onMunicipioClick, municipioSeleccionado, onVolver, onProyectoClick }) => {
    const filas = useMemo(() => {
        const porMunicipio = proyectos.reduce((acc, proyecto) => {
            const municipio = proyecto.descripcion_municipio || 'Sin municipio';
            if (!acc[municipio]) {
                acc[municipio] = [];
            }
            acc[municipio].push(proyecto);
            return acc;
        }, {});

        return Object.entries(porMunicipio).map(([municipio, proyectosDelMunicipio]) => {
            const total = proyectosDelMunicipio.length;
            const presupuestoTotal = proyectosDelMunicipio.reduce((sum, p) => sum + (parseFloat(p.presupuesto) || 0), 0);
            const avancePromedio = total > 0
                ? Math.round(proyectosDelMunicipio.reduce((sum, p) => sum + (parseInt(p.progreso, 10) || 0), 0) / total)
                : 0;
            const enRiesgo = proyectosDelMunicipio.filter(p => p.descripcion_estado === 'Con retraso').length;

            return { municipio, total, presupuestoTotal, avancePromedio, enRiesgo };
        });
    }, [proyectos]);

    const proyectosDelMunicipioSeleccionado = useMemo(() => {
        if (!municipioSeleccionado) return null;
        return proyectos.filter(p => (p.descripcion_municipio || 'Sin municipio') === municipioSeleccionado);
    }, [proyectos, municipioSeleccionado]);

    if (municipioSeleccionado && proyectosDelMunicipioSeleccionado) {
        return (
            <div className="estadisticas-tabla-section">
                <div className="municipio-drilldown-header">
                    <button type="button" className="municipio-drilldown-volver" onClick={onVolver}>
                        ← Volver
                    </button>
                    <h3>{municipioSeleccionado}</h3>
                    <span className="municipio-drilldown-conteo">{proyectosDelMunicipioSeleccionado.length} proyecto(s)</span>
                </div>
                {proyectosDelMunicipioSeleccionado.length === 0 ? (
                    <p className="tabla-sin-datos">No hay proyectos en este municipio.</p>
                ) : (
                    <ul className="municipio-drilldown-lista">
                        {proyectosDelMunicipioSeleccionado.map(proyecto => (
                            <li
                                key={proyecto.id}
                                className="municipio-drilldown-item"
                                onClick={() => onProyectoClick(proyecto)}
                            >
                                <span className="municipio-drilldown-nombre">{proyecto.nombre}</span>
                                <span className="municipio-drilldown-presupuesto">
                                    {formatearPresupuesto(parseFloat(proyecto.presupuesto) || 0)}
                                </span>
                                <span
                                    className="tabla-estado-pastilla"
                                    style={{ backgroundColor: proyecto.color_estado || '#9e9e9e' }}
                                >
                                    {proyecto.descripcion_estado}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        );
    }

    return (
        <div className="estadisticas-tabla-section">
            <h3>Distribución por municipio</h3>
            {filas.length === 0 ? (
                <p className="tabla-sin-datos">No hay proyectos en esta fase todavía.</p>
            ) : (
                <table className="estadisticas-tabla">
                    <thead>
                        <tr>
                            <th>Municipio</th>
                            <th>Proyectos</th>
                            <th>Presupuesto</th>
                            <th>Avance promedio</th>
                            <th>Estado general</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filas.map(fila => {
                            const esClicable = fila.municipio !== 'Sin municipio';
                            return (
                                <tr
                                    key={fila.municipio}
                                    onClick={esClicable ? () => onMunicipioClick(fila.municipio) : undefined}
                                    style={esClicable ? { cursor: 'pointer' } : undefined}
                                >
                                    <td>{fila.municipio}</td>
                                    <td>{fila.total}</td>
                                    <td>{formatearPresupuesto(fila.presupuestoTotal)}</td>
                                    <td>{fila.avancePromedio}%</td>
                                    <td>
                                        <span
                                            className="tabla-estado-pastilla"
                                            style={{ backgroundColor: fila.enRiesgo > 0 ? '#e53935' : '#43a047' }}
                                        >
                                            {fila.enRiesgo > 0 ? `▲ ${fila.enRiesgo} en riesgo` : '● Buen ritmo'}
                                        </span>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            )}
        </div>
    );
};

export default DistribucionMunicipios;
