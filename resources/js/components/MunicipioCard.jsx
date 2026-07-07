import React from 'react';

const COLOR_ESTADO_RESPALDO = '#9e9e9e';

const formatearPresupuesto = (valor) => '$' + Math.round(valor / 1_000_000).toLocaleString('es-CO') + ' M';

const MunicipioCard = ({ municipio, proyectos, faseNombre, onClick }) => {
    const total = proyectos.length;

    const segmentos = Object.values(
        proyectos.reduce((acc, proyecto) => {
            const estado = proyecto.descripcion_estado || 'Sin estado';
            if (!acc[estado]) {
                acc[estado] = {
                    estado,
                    color: proyecto.color_estado || COLOR_ESTADO_RESPALDO,
                    cantidad: 0,
                };
            }
            acc[estado].cantidad++;
            return acc;
        }, {})
    );

    const presupuestoTotal = proyectos.reduce((sum, p) => sum + (parseFloat(p.presupuesto) || 0), 0);
    const avancePromedio = total > 0
        ? Math.round(proyectos.reduce((sum, p) => sum + (parseInt(p.progreso, 10) || 0), 0) / total)
        : 0;
    const enRiesgo = proyectos.filter(p => p.descripcion_estado === 'Con retraso').length;

    const handleDetalleClick = (e) => {
        e.stopPropagation();
        onClick();
    };

    return (
        <div className="municipio-card-modern" onClick={onClick}>
            <div className="municipio-card-content">
                <div className="municipio-icon-modern">
                    {municipio.icon}
                </div>
                <div className="municipio-info-modern">
                    <h3>{municipio.nombre}</h3>
                    <div className="proyectos-count-modern">
                        <span className="count-number-modern">{total}</span>
                        <span className="count-label-modern">proyectos en {faseNombre.toLowerCase()}</span>
                    </div>
                    <div className="municipio-estado-bar">
                        {segmentos.map(seg => (
                            <span
                                key={seg.estado}
                                className="municipio-estado-segmento"
                                style={{ width: `${(seg.cantidad / total) * 100}%`, backgroundColor: seg.color }}
                                title={`${seg.estado}: ${seg.cantidad}`}
                            />
                        ))}
                    </div>
                    <div className="municipio-presupuesto">
                        <span>Presupuesto</span>
                        <b>{formatearPresupuesto(presupuestoTotal)}</b>
                    </div>
                    <div className="municipio-avance-bar">
                        <div className="municipio-avance-fill" style={{ width: `${avancePromedio}%` }}></div>
                    </div>
                    <div className="municipio-avance-valor">{avancePromedio}% de avance promedio</div>
                    <div className={`municipio-estado-general${enRiesgo > 0 ? ' en-riesgo' : ''}`}>
                        {enRiesgo > 0 ? `▲ ${enRiesgo} en riesgo` : '● Buen ritmo'}
                    </div>
                    <button type="button" className="municipio-btn-detalle" onClick={handleDetalleClick}>
                        Ver detalle
                    </button>
                </div>
                <div className="municipio-arrow">→</div>
            </div>
        </div>
    );
};

export default MunicipioCard;
