import React from 'react';

const Eventos = () => {
    return (
        <section className="eventos-section">
        <div className="eventos-header">
            <h2>Próximos Eventos</h2>
            <div className="slider-controls">
                <button onClick={prevEvents} className="slider-btn">
                    ‹
                </button>
                <span className="slider-indicator">
                    {Math.floor(currentEventIndex / 3) + 1} / {Math.ceil(proximosEventos.length / 3)}
                </span>
                <button onClick={nextEvents} className="slider-btn">
                    ›
                </button>
            </div>
        </div>
        <div className="eventos-slider">
            <div className="eventos-grid">
                {visibleEvents.map(evento => (
                    <div
                        key={evento.id}
                        className="evento-card"
                        style={{ borderLeftColor: evento.descripcion_prioridad.split(' ')[0].toUpperCase() }}
                    >
                        <div className="evento-icon">
                            {evento.icono}
                        </div>
                        <div className="evento-content">
                            <h3>{evento.titulo}</h3>
                            <p className="evento-descripcion">{evento.descripcion}</p>
                            <div className="evento-fecha">
                                <span className="fecha-icon">📅</span>
                                <span>{formatDate(evento.fecha)}</span>
                            </div>
                            {evento.descripcion_responsable && (
                                <div className="evento-responsable">
                                    <span className="responsable-icon">👤</span>
                                    <span>{evento.descripcion_responsable}</span>
                                </div>
                            )}
                        </div>

                        <div className="evento-prioridad">
                            <span
                                className="prioridad-badge"
                                style={{ backgroundColor: evento.descripcion_prioridad.split(' ')[0].toUpperCase() }}
                            >
                                {evento.descripcion_prioridad.split(' ')[1].toUpperCase()}
                            </span>
                        </div>
                    </div>
                ))}
            </div>

            {/* Indicador de rotación automática */}
            {proximosEventos.length > 3 && (
                <div className="auto-rotation-indicator">
                    {Array.from({ length: Math.ceil(proximosEventos.length / 3) }, (_, index) => (
                        <div
                            key={index}
                            className={`rotation-dot ${currentRotationIndex === index ? 'active' : ''}`}
                            onClick={() => {
                                pauseAutoRotation();
                                setCurrentEventIndex(index * 3);
                                setCurrentRotationIndex(index);
                            }}
                        />
                    ))}
                </div>
            )}
        </div>
    </section>
    );
};

export default Eventos;
