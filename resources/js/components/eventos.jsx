import React, { useState, useEffect } from 'react';
import axios from 'axios';

const Eventos = () => {
    const [proximosEventos, setProximosEventos] = useState([]);
    const [currentEventIndex, setCurrentEventIndex] = useState(0);
    const [autoRotationEnabled, setAutoRotationEnabled] = useState(true);
    const [currentRotationIndex, setCurrentRotationIndex] = useState(0);

    useEffect(() => {
        listEventos();
    }, []);

    const listEventos = async () => {
        const response = await axios.get('/eventos');
        setProximosEventos(response.data);
        console.log(response.data);
    };


    // Rotación automática de eventos cada 10 segundos
    useEffect(() => {
        if (!autoRotationEnabled || proximosEventos.length <= 3) return;

        const interval = setInterval(() => {
            setCurrentEventIndex(prev => {
                const nextIndex = prev + 3 >= proximosEventos.length ? 0 : prev + 3;
                setCurrentRotationIndex(Math.floor(nextIndex / 3));
                return nextIndex;
            });
        }, 10000); // 10 segundos

        return () => clearInterval(interval);
    }, [proximosEventos.length, autoRotationEnabled]);

    const formatDate = (dateString) => {
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return new Date(dateString).toLocaleDateString('es-ES', options);
    };

    const getEventIcon = (tipo) => {
        const icons = {
            pliego: '📋',
            propuesta: '📄',
            audiencia: '👥',
            documentacion: '📁',
            revision: '🔍',
            contrato: '✍️',
            inicio: '🚀',
            inspeccion: '🔧'
        };
        return icons[tipo] || '📅';
    };

     const nextEvents = () => {
        pauseAutoRotation();
        setCurrentEventIndex(prev => {
            const nextIndex = prev + 3 >= proximosEventos.length ? 0 : prev + 3;
            setCurrentRotationIndex(Math.floor(nextIndex / 3));
            return nextIndex;
        });
    };

    const prevEvents = () => {
        pauseAutoRotation();
        setCurrentEventIndex(prev => {
            const newIndex = prev - 3 < 0 ? Math.max(0, proximosEventos.length - 3) : prev - 3;
            setCurrentRotationIndex(Math.floor(newIndex / 3));
            return newIndex;
        });
    };

    const visibleEvents = proximosEventos.slice(currentEventIndex, currentEventIndex + 3);

    // Pausar rotación automática cuando el usuario interactúa
    const pauseAutoRotation = () => {
        setAutoRotationEnabled(false);
        setTimeout(() => setAutoRotationEnabled(true), 30000); // Reanudar después de 30 segundos
    };

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
                    {visibleEvents.filter(evento => evento.estado !== 'cancelado' && evento.estado !== 'completado').map(evento => (
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
                                    style={{ backgroundColor: evento.color }}
                                >
                                    {evento.nombre}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Indicador de rotación automática */}
                {proximosEventos.length > 3 && (
                    <div className="auto-rotation-indicator">
                        {Array.from({ length: Math.ceil(proximosEventos.filter(evento => evento.estado !== 'cancelado' || evento.estado !== 'completado').length / 3) }, (_, index) => (
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
