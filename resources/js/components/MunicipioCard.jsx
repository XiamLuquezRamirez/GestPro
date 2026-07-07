import React from 'react';

const COLOR_ESTADO_RESPALDO = '#9e9e9e';

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
                </div>
                <div className="municipio-arrow">→</div>
            </div>
        </div>
    );
};

export default MunicipioCard;
