import React, { useState, useEffect } from 'react';
import axios from 'axios';

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

const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('es-ES', options);
};

const AlertasPanel = ({ proyectos, onProyectoClick }) => {
    const [eventos, setEventos] = useState([]);

    useEffect(() => {
        axios.get('/eventos').then(response => setEventos(response.data));
    }, []);

    const proyectosEnRiesgo = proyectos.filter(p => p.descripcion_estado === 'Con retraso');

    const eventosAltaPrioridad = eventos
        .filter(e => e.nombre === 'Alta' && e.estado !== 'cancelado' && e.estado !== 'completado')
        .sort((a, b) => new Date(a.fecha) - new Date(b.fecha));

    const sinAlertas = proyectosEnRiesgo.length === 0 && eventosAltaPrioridad.length === 0;

    return (
        <aside className="alertas-panel">
            <h3>Alertas y Prioridades</h3>
            {sinAlertas ? (
                <p className="alertas-sin-datos">Sin alertas activas por ahora.</p>
            ) : (
                <>
                    {proyectosEnRiesgo.length > 0 && (
                        <div className="alertas-bloque">
                            <h4>⚠️ Proyectos en riesgo</h4>
                            <ul className="alertas-lista">
                                {proyectosEnRiesgo.map(proyecto => (
                                    <li
                                        key={proyecto.id}
                                        className="alerta-item alerta-item-riesgo"
                                        onClick={() => onProyectoClick(proyecto)}
                                    >
                                        <span className="alerta-titulo">{proyecto.nombre}</span>
                                        <span className="alerta-municipio">{proyecto.descripcion_municipio}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {eventosAltaPrioridad.length > 0 && (
                        <div className="alertas-bloque">
                            <h4>🔴 Eventos de alta prioridad</h4>
                            <ul className="alertas-lista">
                                {eventosAltaPrioridad.map(evento => (
                                    <li key={evento.id} className="alerta-item" style={{ borderLeftColor: evento.color }}>
                                        <span className="alerta-icono">{getEventIcon(evento.icono)}</span>
                                        <div className="alerta-info">
                                            <span className="alerta-titulo">{evento.titulo}</span>
                                            <span className="alerta-fecha">{formatDate(evento.fecha)}</span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </>
            )}
        </aside>
    );
};

export default AlertasPanel;
