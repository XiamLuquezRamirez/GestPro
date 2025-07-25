import React, { useState } from 'react';
import Header from './Header';

const GestionarEventos = ({ user, onLogout }) => {
    const [eventos, setEventos] = useState([
        {
            id: 1,
            titulo: 'Fecha límite para pasar pliego',
            descripcion: 'Proyecto: Centro Deportivo Valledupar',
            fecha: '2024-07-15',
            tipo: 'pliego',
            prioridad: 'alta',
            proyecto: 'Centro Deportivo Valledupar',
            responsable: 'Juan Pérez',
            estado: 'pendiente'
        },
        {
            id: 2,
            titulo: 'Presentación de propuesta técnica',
            descripcion: 'Proyecto: Parque Ecológico Aguachica',
            fecha: '2024-07-20',
            tipo: 'propuesta',
            prioridad: 'media',
            proyecto: 'Parque Ecológico Aguachica',
            responsable: 'María García',
            estado: 'en_proceso'
        }
    ]);

    const [showModal, setShowModal] = useState(false);
    const [editingEvento, setEditingEvento] = useState(null);
    const [formData, setFormData] = useState({
        titulo: '',
        descripcion: '',
        fecha: '',
        tipo: 'pliego',
        prioridad: 'media',
        proyecto: '',
        responsable: '',
        estado: 'pendiente'
    });

    const tipos = [
        { value: 'pliego', label: 'Pliego de Condiciones' },
        { value: 'propuesta', label: 'Propuesta Técnica' },
        { value: 'audiencia', label: 'Audiencia Pública' },
        { value: 'documentacion', label: 'Documentación' },
        { value: 'revision', label: 'Revisión' },
        { value: 'contrato', label: 'Contrato' },
        { value: 'inicio', label: 'Inicio de Obras' },
        { value: 'inspeccion', label: 'Inspección' }
    ];

    const prioridades = [
        { value: 'baja', label: 'Baja' },
        { value: 'media', label: 'Media' },
        { value: 'alta', label: 'Alta' },
        { value: 'urgente', label: 'Urgente' }
    ];

    const estados = [
        { value: 'pendiente', label: 'Pendiente' },
        { value: 'en_proceso', label: 'En Proceso' },
        { value: 'completado', label: 'Completado' },
        { value: 'cancelado', label: 'Cancelado' }
    ];

    const proyectos = [
        'Centro Deportivo Valledupar',
        'Parque Ecológico Aguachica',
        'Mejora Hospital Codazzi',
        'Sistema de Agua Potable Valledupar',
        'Centro Comercial La Paz'
    ];

    const responsables = [
        'Juan Pérez',
        'María García',
        'Carlos López',
        'Ana Rodríguez',
        'Luis Martínez'
    ];

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: value
        }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        
        if (editingEvento) {
            // Editar evento existente
            setEventos(prev => prev.map(evento => 
                evento.id === editingEvento.id 
                    ? { ...formData, id: evento.id }
                    : evento
            ));
        } else {
            // Agregar nuevo evento
            const newEvento = {
                ...formData,
                id: Date.now()
            };
            setEventos(prev => [...prev, newEvento]);
        }
        
        handleCloseModal();
    };

    const handleEdit = (evento) => {
        setEditingEvento(evento);
        setFormData(evento);
        setShowModal(true);
    };

    const handleDelete = (id) => {
        if (window.confirm('¿Está seguro de que desea eliminar este evento?')) {
            setEventos(prev => prev.filter(evento => evento.id !== id));
        }
    };

    const handleAddNew = () => {
        setEditingEvento(null);
        setFormData({
            titulo: '',
            descripcion: '',
            fecha: '',
            tipo: 'pliego',
            prioridad: 'media',
            proyecto: '',
            responsable: '',
            estado: 'pendiente'
        });
        setShowModal(true);
    };

    const handleCloseModal = () => {
        setShowModal(false);
        setEditingEvento(null);
        setFormData({
            titulo: '',
            descripcion: '',
            fecha: '',
            tipo: 'pliego',
            prioridad: 'media',
            proyecto: '',
            responsable: '',
            estado: 'pendiente'
        });
    };

    const getPrioridadColor = (prioridad) => {
        const colores = {
            baja: '#4CAF50',
            media: '#FF9800',
            alta: '#f44336',
            urgente: '#9C27B0'
        };
        return colores[prioridad] || '#757575';
    };

    const getEstadoColor = (estado) => {
        const colores = {
            pendiente: '#FF9800',
            en_proceso: '#2196F3',
            completado: '#4CAF50',
            cancelado: '#f44336'
        };
        return colores[estado] || '#757575';
    };

    const getTipoIcon = (tipo) => {
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

    return (
        <div className="gestionar-eventos-container">
            <Header user={user} onLogout={onLogout} />
            
            <div className="content-wrapper">
                <div className="page-header">
                    <h1>Gestionar Eventos</h1>
                    <button onClick={handleAddNew} className="btn-add">
                        <span>+</span> Agregar Evento
                    </button>
                </div>

                <div className="eventos-grid">
                    {eventos.map(evento => (
                        <div key={evento.id} className="evento-card">
                            <div className="evento-header">
                                <div className="evento-icon">
                                    {getTipoIcon(evento.tipo)}
                                </div>
                                <div className="evento-badges">
                                    <span 
                                        className="prioridad-badge"
                                        style={{ backgroundColor: getPrioridadColor(evento.prioridad) }}
                                    >
                                        {evento.prioridad.toUpperCase()}
                                    </span>
                                    <span 
                                        className="estado-badge"
                                        style={{ backgroundColor: getEstadoColor(evento.estado) }}
                                    >
                                        {estados.find(e => e.value === evento.estado)?.label}
                                    </span>
                                </div>
                            </div>
                            
                            <div className="evento-content">
                                <h3>{evento.titulo}</h3>
                                <p className="evento-descripcion">{evento.descripcion}</p>
                                
                                <div className="evento-info">
                                    <div className="info-item">
                                        <span className="label">Fecha:</span>
                                        <span className="value">{formatDate(evento.fecha)}</span>
                                    </div>
                                    <div className="info-item">
                                        <span className="label">Proyecto:</span>
                                        <span className="value">{evento.proyecto}</span>
                                    </div>
                                    <div className="info-item">
                                        <span className="label">Responsable:</span>
                                        <span className="value">{evento.responsable}</span>
                                    </div>
                                    <div className="info-item">
                                        <span className="label">Tipo:</span>
                                        <span className="value">{tipos.find(t => t.value === evento.tipo)?.label}</span>
                                    </div>
                                </div>
                            </div>

                            <div className="evento-actions">
                                <button 
                                    onClick={() => handleEdit(evento)}
                                    className="btn-edit"
                                >
                                    ✏️ Editar
                                </button>
                                <button 
                                    onClick={() => handleDelete(evento.id)}
                                    className="btn-delete"
                                >
                                    🗑️ Eliminar
                                </button>
                            </div>
                        </div>
                    ))}
                </div>

                {eventos.length === 0 && (
                    <div className="empty-state">
                        <div className="empty-icon">📅</div>
                        <h3>No hay eventos</h3>
                        <p>Comience agregando su primer evento</p>
                        <button onClick={handleAddNew} className="btn-add">
                            Agregar Evento
                        </button>
                    </div>
                )}
            </div>

            {/* Modal para agregar/editar evento */}
            {showModal && (
                <div className="modal-overlay" onClick={handleCloseModal}>
                    <div className="modal-content" onClick={e => e.stopPropagation()}>
                        <div className="modal-header">
                            <h2>{editingEvento ? 'Editar Evento' : 'Agregar Evento'}</h2>
                            <button onClick={handleCloseModal} className="btn-close">×</button>
                        </div>
                        
                        <form onSubmit={handleSubmit} className="modal-form">
                            <div className="form-group">
                                <label htmlFor="titulo">Título del Evento *</label>
                                <input
                                    type="text"
                                    id="titulo"
                                    name="titulo"
                                    value={formData.titulo}
                                    onChange={handleInputChange}
                                    required
                                    placeholder="Ingrese el título del evento"
                                />
                            </div>

                            <div className="form-group">
                                <label htmlFor="descripcion">Descripción *</label>
                                <textarea
                                    id="descripcion"
                                    name="descripcion"
                                    value={formData.descripcion}
                                    onChange={handleInputChange}
                                    required
                                    rows="3"
                                    placeholder="Descripción del evento"
                                />
                            </div>

                            <div className="form-row">
                                <div className="form-group">
                                    <label htmlFor="fecha">Fecha *</label>
                                    <input
                                        type="date"
                                        id="fecha"
                                        name="fecha"
                                        value={formData.fecha}
                                        onChange={handleInputChange}
                                        required
                                    />
                                </div>

                                <div className="form-group">
                                    <label htmlFor="tipo">Tipo de Evento *</label>
                                    <select
                                        id="tipo"
                                        name="tipo"
                                        value={formData.tipo}
                                        onChange={handleInputChange}
                                        required
                                    >
                                        {tipos.map(tipo => (
                                            <option key={tipo.value} value={tipo.value}>
                                                {tipo.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="form-row">
                                <div className="form-group">
                                    <label htmlFor="prioridad">Prioridad *</label>
                                    <select
                                        id="prioridad"
                                        name="prioridad"
                                        value={formData.prioridad}
                                        onChange={handleInputChange}
                                        required
                                    >
                                        {prioridades.map(prioridad => (
                                            <option key={prioridad.value} value={prioridad.value}>
                                                {prioridad.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="form-group">
                                    <label htmlFor="estado">Estado *</label>
                                    <select
                                        id="estado"
                                        name="estado"
                                        value={formData.estado}
                                        onChange={handleInputChange}
                                        required
                                    >
                                        {estados.map(estado => (
                                            <option key={estado.value} value={estado.value}>
                                                {estado.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="form-row">
                                <div className="form-group">
                                    <label htmlFor="proyecto">Proyecto *</label>
                                    <select
                                        id="proyecto"
                                        name="proyecto"
                                        value={formData.proyecto}
                                        onChange={handleInputChange}
                                        required
                                    >
                                        <option value="">Seleccione un proyecto</option>
                                        {proyectos.map(proyecto => (
                                            <option key={proyecto} value={proyecto}>
                                                {proyecto}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="form-group">
                                    <label htmlFor="responsable">Responsable *</label>
                                    <select
                                        id="responsable"
                                        name="responsable"
                                        value={formData.responsable}
                                        onChange={handleInputChange}
                                        required
                                    >
                                        <option value="">Seleccione un responsable</option>
                                        {responsables.map(responsable => (
                                            <option key={responsable} value={responsable}>
                                                {responsable}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="modal-actions">
                                <button type="button" onClick={handleCloseModal} className="btn-cancel">
                                    Cancelar
                                </button>
                                <button type="submit" className="btn-save">
                                    {editingEvento ? 'Actualizar' : 'Guardar'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default GestionarEventos; 