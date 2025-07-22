import React, { useState, useEffect } from 'react';
import Header from './Header';
import '../../css/GestionarProyectos.css';

const GestionarProyectos = ({ user, onLogout }) => {
    const [proyectos, setProyectos] = useState([
        {
            id: 1,
            nombre: 'Centro Deportivo Valledupar',
            municipio: 'Valledupar',
            presupuesto: '$2,500,000',
            fechaInicio: '2024-08-01',
            estado: 'En revisión',
            fase: 'formulacion',
            descripcion: 'Construcción de un centro deportivo moderno en Valledupar'
        },
        {
            id: 2,
            nombre: 'Parque Ecológico Aguachica',
            municipio: 'Aguachica',
            presupuesto: '$1,800,000',
            fechaInicio: '2024-08-15',
            estado: 'Aprobado',
            fase: 'formulacion',
            descripcion: 'Desarrollo de un parque ecológico sostenible'
        }
    ]);

    const [showModal, setShowModal] = useState(false);
    const [editingProyecto, setEditingProyecto] = useState(null);
    const [formData, setFormData] = useState({
        nombre: '',
        municipio: '',
        presupuesto: '',
        fechaInicio: '',
        estado: 'En revisión',
        fase: 'formulacion',
        descripcion: ''
    });

    const municipios = ['Valledupar', 'Aguachica', 'Codazzi', 'La Paz'];
    const estados = ['En revisión', 'Aprobado', 'Rechazado', 'En ejecución', 'Completado'];
    const fases = ['formulacion', 'licitacion', 'ejecucion'];

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: value
        }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        
        if (editingProyecto) {
            // Editar proyecto existente
            setProyectos(prev => prev.map(proyecto => 
                proyecto.id === editingProyecto.id 
                    ? { ...formData, id: proyecto.id }
                    : proyecto
            ));
        } else {
            // Agregar nuevo proyecto
            const newProyecto = {
                ...formData,
                id: Date.now()
            };
            setProyectos(prev => [...prev, newProyecto]);
        }
        
        handleCloseModal();
    };

    const handleEdit = (proyecto) => {
        setEditingProyecto(proyecto);
        setFormData(proyecto);
        setShowModal(true);
    };

    const handleDelete = (id) => {
        if (window.confirm('¿Está seguro de que desea eliminar este proyecto?')) {
            setProyectos(prev => prev.filter(proyecto => proyecto.id !== id));
        }
    };

    const handleAddNew = () => {
        setEditingProyecto(null);
        setFormData({
            nombre: '',
            municipio: '',
            presupuesto: '',
            fechaInicio: '',
            estado: 'En revisión',
            fase: 'formulacion',
            descripcion: ''
        });
        setShowModal(true);
    };

    const handleCloseModal = () => {
        setShowModal(false);
        setEditingProyecto(null);
        setFormData({
            nombre: '',
            municipio: '',
            presupuesto: '',
            fechaInicio: '',
            estado: 'En revisión',
            fase: 'formulacion',
            descripcion: ''
        });
    };

    const getEstadoColor = (estado) => {
        const colores = {
            'En revisión': '#FF9800',
            'Aprobado': '#4CAF50',
            'Rechazado': '#f44336',
            'En ejecución': '#2196F3',
            'Completado': '#9C27B0'
        };
        return colores[estado] || '#757575';
    };

    const getFaseColor = (fase) => {
        const colores = {
            'formulacion': '#FF9800',
            'licitacion': '#2196F3',
            'ejecucion': '#4CAF50'
        };
        return colores[fase] || '#757575';
    };

    return (
        <div className="gestionar-proyectos-container">
            <Header user={user} onLogout={onLogout} />
            
            <div className="content-wrapper">
                <div className="page-header">
                    <h1>Gestionar Proyectos</h1>
                    <button onClick={handleAddNew} className="btn-add">
                        <span>+</span> Agregar Proyectos
                    </button>
                </div>

                <div className="proyectos-grid">
                    {proyectos.map(proyecto => (
                        <div key={proyecto.id} className="proyecto-card">
                            <div className="proyecto-header">
                                <h3>{proyecto.nombre}</h3>
                                <div className="proyecto-badges">
                                    <span 
                                        className="estado-badge"
                                        style={{ backgroundColor: getEstadoColor(proyecto.estado) }}
                                    >
                                        {proyecto.estado}
                                    </span>
                                    <span 
                                        className="fase-badge"
                                        style={{ backgroundColor: getFaseColor(proyecto.fase) }}
                                    >
                                        {proyecto.fase}
                                    </span>
                                </div>
                            </div>
                            
                            <div className="proyecto-info">
                                <div className="info-item">
                                    <span className="label">Municipio:</span>
                                    <span className="value">{proyecto.municipio}</span>
                                </div>
                                <div className="info-item">
                                    <span className="label">Presupuesto:</span>
                                    <span className="value">{proyecto.presupuesto}</span>
                                </div>
                                <div className="info-item">
                                    <span className="label">Fecha Inicio:</span>
                                    <span className="value">{proyecto.fechaInicio}</span>
                                </div>
                                <div className="info-item">
                                    <span className="label">Descripción:</span>
                                    <span className="value description">{proyecto.descripcion}</span>
                                </div>
                            </div>

                            <div className="proyecto-actions">
                                <button 
                                    onClick={() => handleEdit(proyecto)}
                                    className="btn-edit"
                                >
                                    ✏️ Editar
                                </button>
                                <button 
                                    onClick={() => handleDelete(proyecto.id)}
                                    className="btn-delete"
                                >
                                    🗑️ Eliminar
                                </button>
                            </div>
                        </div>
                    ))}
                </div>

                {proyectos.length === 0 && (
                    <div className="empty-state">
                        <div className="empty-icon">📋</div>
                        <h3>No hay proyectos</h3>
                        <p>Comience agregando su primer proyecto</p>
                        <button onClick={handleAddNew} className="btn-add">
                            Agregar Proyecto
                        </button>
                    </div>
                )}
            </div>

            {/* Modal para agregar/editar proyecto */}
            {showModal && (
                <div className="modal-overlay" onClick={handleCloseModal}>
                    <div className="modal-content" onClick={e => e.stopPropagation()}>
                        <div className="modal-header">
                            <h2>{editingProyecto ? 'Editar Proyecto' : 'Agregar Proyecto'}</h2>
                            <button onClick={handleCloseModal} className="btn-close">×</button>
                        </div>
                        
                        <form onSubmit={handleSubmit} className="modal-form">
                            <div className="form-group">
                                <label htmlFor="nombre">Nombre del Proyecto *</label>
                                <input
                                    type="text"
                                    id="nombre"
                                    name="nombre"
                                    value={formData.nombre}
                                    onChange={handleInputChange}
                                    required
                                    placeholder="Ingrese el nombre del proyecto"
                                />
                            </div>

                            <div className="form-group">
                                <label htmlFor="municipio">Municipio *</label>
                                <select
                                    id="municipio"
                                    name="municipio"
                                    value={formData.municipio}
                                    onChange={handleInputChange}
                                    required
                                >
                                    <option value="">Seleccione un municipio</option>
                                    {municipios.map(municipio => (
                                        <option key={municipio} value={municipio}>
                                            {municipio}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="form-row">
                                <div className="form-group">
                                    <label htmlFor="presupuesto">Presupuesto *</label>
                                    <input
                                        type="text"
                                        id="presupuesto"
                                        name="presupuesto"
                                        value={formData.presupuesto}
                                        onChange={handleInputChange}
                                        required
                                        placeholder="Ej: $1,000,000"
                                    />
                                </div>

                                <div className="form-group">
                                    <label htmlFor="fechaInicio">Fecha de Inicio *</label>
                                    <input
                                        type="date"
                                        id="fechaInicio"
                                        name="fechaInicio"
                                        value={formData.fechaInicio}
                                        onChange={handleInputChange}
                                        required
                                    />
                                </div>
                            </div>

                            <div className="form-row">
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
                                            <option key={estado} value={estado}>
                                                {estado}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="form-group">
                                    <label htmlFor="fase">Fase *</label>
                                    <select
                                        id="fase"
                                        name="fase"
                                        value={formData.fase}
                                        onChange={handleInputChange}
                                        required
                                    >
                                        {fases.map(fase => (
                                            <option key={fase} value={fase}>
                                                {fase.charAt(0).toUpperCase() + fase.slice(1)}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="form-group">
                                <label htmlFor="descripcion">Descripción</label>
                                <textarea
                                    id="descripcion"
                                    name="descripcion"
                                    value={formData.descripcion}
                                    onChange={handleInputChange}
                                    rows="4"
                                    placeholder="Descripción detallada del proyecto"
                                />
                            </div>

                            <div className="modal-actions">
                                <button type="button" onClick={handleCloseModal} className="btn-cancel">
                                    Cancelar
                                </button>
                                <button type="submit" className="btn-save">
                                    {editingProyecto ? 'Actualizar' : 'Guardar'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default GestionarProyectos; 