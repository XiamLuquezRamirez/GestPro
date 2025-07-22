import React, { useState, useEffect } from 'react';
import Header from './Header';
import '../../css/Parametros.css';
import axios from '../axios';
import { faPlus, faTrash, faSave, faTimes } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';

const Parametros = ({ user, onLogout }) => {
    const [activeTab, setActiveTab] = useState('proyectos');
    const [showModal, setShowModal] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [modalType, setModalType] = useState('');
    const [proyectos, setProyectos] = useState([]);
    const [municipios, setMunicipios] = useState([]);
    const [estados, setEstados] = useState([]);
    const [fases, setFases] = useState([]);
    const [entidades, setEntidades] = useState([]);
    const [eventos, setEventos] = useState([]);
    const [tipos, setTiposEventos] = useState([]);
    const [prioridades, setPrioridades] = useState([]);
    const [responsables, setResponsables] = useState([]);
    const [departamentos, setDepartamentos] = useState([]);
    const [detallesPresupuesto, setDetallesPresupuesto] = useState([]);
    // Datos de ejemplo para los parámetros
    const [parametros, setParametros] = useState({
        municipios: [
            { id: 1, nombre: 'Valledupar', codigo: 'VAL', activo: true },
            { id: 2, nombre: 'Aguachica', codigo: 'AGU', activo: true },
            { id: 3, nombre: 'Codazzi', codigo: 'COD', activo: true },
            { id: 4, nombre: 'La Paz', codigo: 'LAP', activo: false }
        ],
        estados: [
            { id: 1, nombre: 'En revisión', color: '#FF9800', activo: true },
            { id: 2, nombre: 'Aprobado', color: '#4CAF50', activo: true },
            { id: 3, nombre: 'Rechazado', color: '#f44336', activo: true },
            { id: 4, nombre: 'En ejecución', color: '#2196F3', activo: true },
            { id: 5, nombre: 'Completado', color: '#9C27B0', activo: true }
        ],
        fases: [
            { id: 1, nombre: 'Formulación', color: '#FF9800', activo: true },
            { id: 2, nombre: 'Licitación', color: '#2196F3', activo: true },
            { id: 3, nombre: 'Ejecución', color: '#4CAF50', activo: true }
        ],
        tiposEventos: [
            { id: 1, nombre: 'Pliego de Condiciones', icono: '📋', activo: true },
            { id: 2, nombre: 'Propuesta Técnica', icono: '📄', activo: true },
            { id: 3, nombre: 'Audiencia Pública', icono: '👥', activo: true },
            { id: 4, nombre: 'Documentación', icono: '📁', activo: true },
            { id: 5, nombre: 'Revisión', icono: '🔍', activo: true },
            { id: 6, nombre: 'Contrato', icono: '✍️', activo: true },
            { id: 7, nombre: 'Inicio de Obras', icono: '🚀', activo: true },
            { id: 8, nombre: 'Inspección', icono: '🔧', activo: true }
        ],
        prioridades: [
            { id: 1, nombre: 'Baja', color: '#4CAF50', activo: true },
            { id: 2, nombre: 'Media', color: '#FF9800', activo: true },
            { id: 3, nombre: 'Alta', color: '#f44336', activo: true },
            { id: 4, nombre: 'Urgente', color: '#9C27B0', activo: true }
        ],
        responsables: [
            { id: 1, nombre: 'Juan Pérez', email: 'juan.perez@empresa.com', cargo: 'Gerente de Proyectos', activo: true },
            { id: 2, nombre: 'María García', email: 'maria.garcia@empresa.com', cargo: 'Coordinadora', activo: true },
            { id: 3, nombre: 'Carlos López', email: 'carlos.lopez@empresa.com', cargo: 'Supervisor', activo: true },
            { id: 4, nombre: 'Ana Rodríguez', email: 'ana.rodriguez@empresa.com', cargo: 'Analista', activo: false }
        ]
    });

    // Datos de proyectos y eventos


    useEffect(() => {
        listProyectos();
        listMunicipios();
        listEstados();
        listFases();
        listEntidades();
        ///
        listEventos();
        listTiposEventos();
        listPrioridades();
        listResponsables();

        listDepartamentos();

    }, []);


    const listProyectos = async () => {
        const response = await axios.get('/proyectos');
        setProyectos(response.data);
    }

    const listEventos = async () => {
        const response = await axios.get('/eventos');
        setEventos(response.data);
    }

    const listMunicipios = async () => {
        const response = await axios.get('/municipios');
        setMunicipios(response.data);
    }

    const listEstados = async () => {
        const response = await axios.get('/estados');
        setEstados(response.data);
    }

    const listFases = async () => {
        const response = await axios.get('/fases');
        setFases(response.data);
    }

    const listEntidades = async () => {
        const response = await axios.get('/entidades');
        setEntidades(response.data);
    }

    const listTiposEventos = async () => {
        const response = await axios.get('/tiposEventos');
        setTiposEventos(response.data);
    }

    const listPrioridades = async () => {
        const response = await axios.get('/prioridades');
        setPrioridades(response.data);
    }

    const listResponsables = async () => {
        const response = await axios.get('/responsables');
        setResponsables(response.data);
    }

    const listDepartamentos = async () => {
        const response = await axios.get('/departamentos');
        setDepartamentos(response.data);
    }
   
    const estadosEventos = [
        { value: 'pendiente', label: 'Pendiente' },
        { value: 'en_proceso', label: 'En Proceso' },
        { value: 'completado', label: 'Completado' },
        { value: 'cancelado', label: 'Cancelado' }
    ];


    const [formData, setFormData] = useState({
        id: '',
        nombre: '',
        codigo: '',
        color: '#1976d2',
        icono: '📋',
        email: '',
        cargo: '',
        // Campos para proyectos
        presupuesto: '',
        fechaInicio: '',
        estado: 'En revisión',
        fase: 'formulacion',
        municipio: '',
        descripcion: '',
        entidadPresenta: '',
        entidadFinancia: '',
        fuenteFinanciamiento: '',
        // Campos para eventos
        titulo: '',
        fecha: '',
        tipo: '',
        prioridad: '',
        proyecto: '',
        responsable: '',

        departamento: '',       

        // Campos para detalles de presupuesto
        proyectoId: '',
        descripcionComponente: '',
        valor: '',
        accion: 'Agregar'
    });

    const [modalActiveTab, setModalActiveTab] = useState('datos');
    const [componentesPresupuesto, setComponentesPresupuesto] = useState([]);
    // Estado para búsqueda
    const [searchText, setSearchText] = useState("");

    const tabs = [
        { id: 'proyectos', label: 'Gestionar Proyectos', icon: '📋' },
        { id: 'eventos-calendario', label: 'Gestionar Eventos', icon: '📅' },
        { id: 'municipios', label: 'Municipios', icon: '🏘️' },
        { id: 'estados', label: 'Estados', icon: '📊' },
        { id: 'fases', label: 'Fases', icon: '🔄' },
        { id: 'eventos', label: 'Tipos de Eventos', icon: '📅' },
        { id: 'prioridades', label: 'Prioridades', icon: '🎯' },
        { id: 'responsables', label: 'Responsables', icon: '👥' },

    ];


    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: value
        }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (modalType === 'proyectos') {
            // Calcular el presupuesto total de los componentes
            const presupuestoTotal = componentesPresupuesto.reduce((sum, comp) => sum + comp.valor, 0);
            
            const newProyecto = {
                ...formData,
                id: editingItem ? editingItem.id : Date.now(),
                presupuesto: formatCurrency(presupuestoTotal),
                componentesPresupuesto: componentesPresupuesto,

            };
            const response = await axios.post('/guardarProyecto', newProyecto);
            if (response.status === 200) {
                listProyectos();
                handleCloseModal();
                Swal.fire({
                    title: 'Proyecto guardado correctamente',
                    icon: 'success'
                }).then(() => {
                    listProyectos();
                    handleCloseModal();
                });
            }

        } else if (modalType === 'eventos') {
            const newEvento = {
                ...formData,
                id: editingItem ? editingItem.id : Date.now()
            };
            const response = await axios.post('/guardarEvento', newEvento);
            if (response.status === 200) {
                listEventos();
                handleCloseModal();
                Swal.fire({
                    title: 'Evento guardado correctamente',
                    icon: 'success'
                }).then(() => {
                    listEventos();
                    handleCloseModal();
                });
            }

        } else if (modalType === 'municipios') {
            const newMunicipio = {
                ...formData,
                id: editingItem ? editingItem.id : Date.now(),
                activo: true
            };
            const response = await axios.post('/guardarMunicipio', newMunicipio);
            if (response.status === 200) {
                listMunicipios();
                handleCloseModal();
                Swal.fire({
                    title: 'Municipio guardado correctamente',
                    icon: 'success'
                }).then(() => {
                    listMunicipios();
                    handleCloseModal();
                });
            }

        } else if (modalType === 'estados') {
            const newEstado = {
                ...formData,
                id: editingItem ? editingItem.id : Date.now(),
                activo: true
            };
            const response = await axios.post('/guardarEstado', newEstado);
            if (response.status === 200) {
                listEstados();
                handleCloseModal();
                Swal.fire({
                    title: 'Estado guardado correctamente',
                    icon: 'success'
                }).then(() => {
                    listEstados();
                    handleCloseModal();
                });
            }

        } else if (modalType === 'fases') {
            const newFase = {
                ...formData,
                id: editingItem ? editingItem.id : Date.now(),
                activo: true
            };
            const response = await axios.post('/guardarFase', newFase);
            if (response.status === 200) {
                listFases();
                handleCloseModal();
                Swal.fire({
                    title: 'Fase guardada correctamente',
                    icon: 'success'
                }).then(() => {
                    listFases();
                    handleCloseModal();
                });
            }

        } else {
            const newItem = {
                ...formData,
                id: editingItem ? editingItem.id : Date.now(),
                activo: true
            };

            setParametros(prev => ({
                ...prev,
                [modalType]: editingItem
                    ? prev[modalType].map(item => item.id === editingItem.id ? newItem : item)
                    : [...prev[modalType], newItem]
            }));
        }

        handleCloseModal();
    };

    

    const handleEdit = (item, type) => {
        setEditingItem(item);
        setModalType(type);
        setFormData({
            id: item.id || '',
            nombre: item.nombre || '',
            codigo: item.codigo || '',
            color: item.color || '#1976d2',
            icono: item.icono || '📋',
            email: item.email || '',
            cargo: item.cargo || '',
            // Campos para proyectos
            municipio: item.municipio || '',
            fechaInicio: item.fecha_inicio || '',
            estado: item.estado || '',
            fase: item.fase || '',
            municipio: item.municipio || '',
            descripcion: item.descripcion || '',
            entidadPresenta: item.entidad_presenta || '',
            entidadFinancia: item.entidad_financia || '',
            fuenteFinanciamiento: item.fuente_financiacion || '',
            // Campos para eventos
            titulo: item.titulo || '',
            fecha: item.fecha || '',
            tipo: item.tipo_eventos || '',
            prioridad: item.prioridad || '',
            proyecto: item.proyecto || '',
            responsable: item.responsable || '',
            // Campos para detalles de presupuesto
            proyectoId: item.proyectoId || '',
            descripcionComponente: item.descripcionComponente || '',
            valor: item.valor || '',
            accion: 'Editar',
            departamento: item.codigo_departamento || ''
        });


        // Cargar componentes de presupuesto si es un proyecto
        if (type === 'proyectos' && item.componentesPresupuesto) {
            console.log(item.componentesPresupuesto);
            setComponentesPresupuesto(
                item.componentesPresupuesto.map(componente => ({
                    id: componente.id,
                    descripcionComponente: componente.componente,
                    valor: parseFloat(componente.valor)
                }))
            );
        } else {
            setComponentesPresupuesto([]);
        }

        setModalActiveTab('datos');
        setShowModal(true);
    };

    const handleDelete = async (id, type) => {
        Swal.fire({
            title: '¿Está seguro de que desea eliminar este elemento?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                if (type === 'proyectos') {
                    const response = await axios.post('/eliminarProyecto', { id: id });
                    if (response.status === 200) {
                        listProyectos();
                        Swal.fire({
                            title: 'Proyecto eliminado correctamente',
                            icon: 'success'
                        });
                    }
                } else if (type === 'eventos') {
                    const response = await axios.post('/eliminarEvento', { id: id });
                    if (response.status === 200) {
                        listEventos();
                        Swal.fire({
                            title: 'Evento eliminado correctamente',
                            icon: 'success'
                        });
                    }
                } else if (type === 'municipios') {
                    const response = await axios.post('/eliminarMunicipio', { id: id });
                    if (response.status === 200) {
                        listMunicipios();
                        Swal.fire({
                            title: 'Municipio eliminado correctamente',
                            icon: 'success'
                        });
                    }
                } else if (type === 'estados') {
                    const response = await axios.post('/eliminarEstado', { id: id });
                    if (response.status === 200) {
                        listEstados();
                        Swal.fire({
                            title: 'Estado eliminado correctamente',
                            icon: 'success'
                        });
                    }
                } else if (type === 'fases') {
                    const response = await axios.post('/eliminarFase', { id: id });
                    if (response.status === 200) {
                        listFases();
                        Swal.fire({
                            title: 'Fase eliminada correctamente',
                            icon: 'success'
                        });
                    }
                } else {
                    setParametros(prev => ({
                        ...prev,
                        [type]: prev[type].filter(item => item.id !== id)
                    }));
                }
                Swal.fire({
                    title: 'Elemento eliminado correctamente',
                    icon: 'success'
                });
            }
        });

        
    };

    const handleAddNew = (type) => {
        setEditingItem(null);
        setModalType(type);
        setFormData({
            id: '',
            nombre: '',
            codigo: '',
            color: '#1976d2',
            icono: '📋',
            email: '',
            cargo: '',
            presupuesto: '',
            fechaInicio: '',
            estado: 'En revisión',
            fase: 'formulacion',
            municipio: '',
            descripcion: '',
            titulo: '',
            fecha: '',
            tipo: 'pliego',
            prioridad: 'media',
            proyecto: '',
            responsable: '',
            proyectoId: '',
            valor: '',
            descripcionComponente: '',
            entidadPresenta: '',
            entidadFinancia: '',
            fuenteFinanciamiento: '',
            accion: 'Agregar'
        });
        setComponentesPresupuesto([]);
        setModalActiveTab('datos');
        setShowModal(true);
    };

    const handleCloseModal = () => {
        setShowModal(false);
        setEditingItem(null);
        setModalType('');
        setFormData({
            id: '',
            nombre: '',
            codigo: '',
            color: '#1976d2',
            icono: '📋',
            email: '',
            cargo: '',
            presupuesto: '',
            fechaInicio: '',
            estado: 'En revisión',
            fase: 'formulacion',
            municipio: '',
            descripcion: '',
            titulo: '',
            fecha: '',
            tipo: 'pliego',
            prioridad: 'media',
            proyecto: '',
            responsable: '',
            proyectoId: '',
            valor: '',
            descripcionComponente: '',
            entidadPresenta: '',
            entidadFinancia: '',
            fuenteFinanciamiento: '',
            accion: 'Agregar'
        });
        setComponentesPresupuesto([]);
        setModalActiveTab('datos');
    };

    const toggleActive = async (id, type, activo) => {
        setParametros(prev => ({
            ...prev,
            [type]: prev[type].map(item =>
                item.id === id ? { ...item, activo: !item.activo } : item
            )
        }));

        if (type === 'municipios') {
            const response = await axios.post('/activarMunicipio', { id: id, activo: activo });
            if (response.status === 200) {
                listMunicipios();
            }
        } else if (type === 'estados') {
            const response = await axios.post('/activarEstado', { id: id, activo: activo });
            if (response.status === 200) {
                listEstados();
            }
        } else if (type === 'fases') {
            const response = await axios.post('/activarFase', { id: id, activo: activo });
            if (response.status === 200) {
                listFases();
            }
        }
    };

    const getModalTitle = () => {
        const titles = {
            municipios: 'Municipio',
            estados: 'Estado',
            fases: 'Fase',
            tiposEventos: 'Tipo de Evento',
            prioridades: 'Prioridad',
            responsables: 'Responsable',
            proyectos: 'Proyecto',
            eventos: 'Evento'
        };
        return editingItem ? `Editar ${titles[modalType]}` : `Agregar ${titles[modalType]}`;
    };

    const getPrioridadColor = (prioridad) => {

        return prioridad.split(' ')[0] || '#757575';
    };

    const formatDate = (dateString) => {
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return new Date(dateString).toLocaleDateString('es-ES', options);
    };

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);
    };

    const handleAddComponente = () => {
        if (formData.descripcionComponente && formData.valor) {
            const nuevoComponente = {
                id: Date.now(),
                descripcionComponente: formData.descripcionComponente,
                valor: parseFloat(formData.valor)
            };
            setComponentesPresupuesto(prev => [...prev, nuevoComponente]);
            setFormData(prev => ({
                ...prev,
                descripcionComponente: '',
                valor: ''
            }));
        }
    };

    const handleDeleteComponente = (id) => {
    
        setComponentesPresupuesto(prev => prev.filter(comp => comp.id !== id));
    };

    const calcularTotalPresupuesto = () => {
        
        return componentesPresupuesto.reduce((sum, comp) => sum + comp.valor, 0);
    };

    const renderTable = (data, type) => {
        // Paginación
        const [currentPage, setCurrentPage] = useState(1);
        const itemsPerPage = 5;
        // Filtro de búsqueda
        const filteredData = data.filter(item => {
            const value = (item.nombre || item.titulo || "").toLowerCase();
            return value.includes(searchText.toLowerCase());
        });
        const totalPages = Math.ceil(filteredData.length / itemsPerPage);
        const paginatedData = filteredData.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);

        useEffect(() => {
            setCurrentPage(1); // Reset page when data/type/search cambia
        }, [data, type, searchText]);

        return (
            <div className="table-container">
                <div className="table-header">
                    <h3>{tabs.find(tab => tab.id === type)?.label}</h3>
                    <div style={{ display: 'flex', gap: '1rem', alignItems: 'center' }}>
                        <input
                            type="text"
                            className="search-input"
                            placeholder="Buscar..."
                            value={searchText}
                            onChange={e => setSearchText(e.target.value)}
                            style={{ padding: '0.5rem 1rem', borderRadius: '6px', border: '1px solid #e0e6ed', fontSize: '1rem' }}
                        />
                        <button onClick={() => handleAddNew(type)} className="btn-add">
                            <span>+</span> Agregar
                        </button>
                    </div>
                </div>
                <div className="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                {type === 'municipios' && <th>Departamento</th>}
                                {type === 'responsables' && <th>Email</th>}
                                {type === 'responsables' && <th>Cargo</th>}
                                {(type === 'estados' || type === 'fases' || type === 'prioridades') && <th>Color</th>}
                                {type === 'tiposEventos' && <th>Icono</th>}
                                {type === 'proyectos' && <th>Municipio</th>}
                                {type === 'proyectos' && <th>Presupuesto</th>}
                                {type === 'proyectos' && <th>Estado</th>}
                                {type === 'proyectos' && <th>Fase</th>}
                                {type === 'eventos' && <th>Fecha</th>}
                                {type === 'eventos' && <th>Tipo</th>}
                                {type === 'eventos' && <th>Prioridad</th>}
                                {type === 'eventos' && <th>Proyecto</th>}
                                {type === 'eventos' && <th>Responsable</th>}
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {paginatedData.map(item => (
                                <tr key={item.id}>
                                    <td>{item.nombre || item.titulo}</td>
                                    {type === 'municipios' && <td>{item.departamento}</td>}
                                    {type === 'responsables' && <td>{item.email}</td>}
                                    {type === 'responsables' && <td>{item.cargo}</td>}
                                    {(type === 'estados' || type === 'fases' || type === 'prioridades') && (
                                        <td>
                                            <div className="color-preview" style={{ backgroundColor: item.color }}></div>
                                        </td>
                                    )}
                                    {type === 'tiposEventos' && <td>{item.icono}</td>}
                                    {type === 'proyectos' && <td>{item.descripcion_municipio}</td>}
                                    {type === 'proyectos' && <td>{item.presupuesto}</td>}
                                    {type === 'proyectos' && (
                                        <td>
                                            <span className="status-badge" style={{ backgroundColor: item.color_estado, color: 'white' }}>
                                                {item.descripcion_estado}
                                            </span>
                                        </td>
                                    )}
                                    {type === 'proyectos' && (
                                        <td>
                                            <span className="status-badge" style={{ backgroundColor: item.color_fase, color: 'white' }}>
                                                {item.descripcion_fase}
                                            </span>
                                        </td>
                                    )}
                                    {type === 'eventos' && <td>{formatDate(item.fecha)}</td>}
                                    {type === 'eventos' && <td>{item.descripcion_tipo_evento}</td>}
                                    {type === 'eventos' && (
                                        <td>
                                            <span className="status-badge" style={{ backgroundColor: getPrioridadColor(item.descripcion_prioridad), color: 'white' }}>
                                                {item.descripcion_prioridad.split(' ')[1].toUpperCase()}
                                            </span>
                                        </td>
                                    )}
                                    {type === 'eventos' && <td>{item.descripcion_proyecto}</td>}
                                    {type === 'eventos' && <td>{item.descripcion_responsable}</td>}
                                  
                                    <td>
                                        <div className="action-buttons">
                                            {type !== 'proyectos' && type !== 'eventos' && (
                                                <button
                                                    onClick={() => toggleActive(item.id, type, item.activo)}
                                                    className={`btn-toggle ${item.activo ? 'deactivate' : 'activate'}`}
                                                >
                                                    {item.activo ? 'Desactivar' : 'Activar'}
                                                </button>
                                            )}
                                            <button
                                                onClick={() => handleEdit(item, type)}
                                                className="btn-edit"
                                            >
                                                ✏️
                                            </button>
                                            <button
                                                onClick={() => handleDelete(item.id, type)}
                                                className="btn-delete"
                                            >
                                                🗑️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {/* Controles de paginación */}
                    {totalPages > 1 && (
                        <div className="pagination-controls">
                            <button onClick={() => setCurrentPage(p => Math.max(1, p - 1))} disabled={currentPage === 1}>&lt;</button>
                            <span>Página {currentPage} de {totalPages}</span>
                            <button onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))} disabled={currentPage === totalPages}>&gt;</button>
                        </div>
                    )}
                </div>
            </div>
        );
    };

    return (
        <div className="parametros-container">
            <Header user={user} onLogout={onLogout} />

            <div className="content-wrapper">
                <div className="page-header">
                    <h1>Gestión de Parámetros</h1>
                    <p>Configure los parámetros del sistema</p>
                </div>

                <div className="tabs-container">
                    <div className="tabs-header">
                        {tabs.map(tab => (
                            <button
                                key={tab.id}
                                className={`tab-button ${activeTab === tab.id ? 'active' : ''}`}
                                onClick={() => setActiveTab(tab.id)}
                            >
                                <span className="tab-icon">{tab.icon}</span>
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    <div className="tab-content">
                        {activeTab === 'general' && (
                            <div className="general-config">
                                <h2>Configuración General del Sistema</h2>
                                <div className="config-grid">
                                    <div className="config-card">
                                        <h3>Información del Sistema</h3>
                                        <p><strong>Versión:</strong> 1.0.0</p>
                                        <p><strong>Última actualización:</strong> {new Date().toLocaleDateString()}</p>
                                        <p><strong>Total de parámetros:</strong> {
                                            Object.values(parametros).reduce((total, array) => total + array.length, 0)
                                        }</p>
                                    </div>
                                    <div className="config-card">
                                        <h3>Estadísticas</h3>
                                        <p><strong>Municipios activos:</strong> {municipios.filter(m => m.activo).length}</p>
                                        <p><strong>Estados configurados:</strong> {estados.filter(e => e.activo).length}</p>
                                        <p><strong>Responsables activos:</strong> {responsables.filter(r => r.activo).length}</p>
                                        <p><strong>Proyectos activos:</strong> {proyectos.length}</p>
                                        <p><strong>Eventos pendientes:</strong> {eventos.filter(e => e.estado === 'pendiente').length}</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'proyectos' && renderTable(proyectos, 'proyectos')}
                        {activeTab === 'eventos-calendario' && renderTable(eventos, 'eventos')}
                        {activeTab === 'municipios' && renderTable(municipios, 'municipios')}
                        {activeTab === 'estados' && renderTable(estados, 'estados')}
                        {activeTab === 'fases' && renderTable(fases, 'fases')}
                        {activeTab === 'eventos' && renderTable(parametros.tiposEventos, 'tiposEventos')}
                        {activeTab === 'prioridades' && renderTable(parametros.prioridades, 'prioridades')}
                        {activeTab === 'responsables' && renderTable(responsables, 'responsables')}

                    </div>
                </div>
            </div>

            {/* Modal para agregar/editar */}
            {showModal && (
                <div className="modal-overlay">
                    <div className="modal-content" onClick={e => e.stopPropagation()}>
                        <div className="modal-header">
                            <h2>{getModalTitle()}</h2>
                            <button onClick={handleCloseModal} className="btn-close">×</button>
                        </div>

                        <form onSubmit={handleSubmit} className="modal-form">
                            {/* Pestañas para proyectos */}
                            {modalType === 'proyectos' && (
                                <div className="modal-tabs">
                                    <button
                                        type="button"
                                        className={`modal-tab ${modalActiveTab === 'datos' ? 'active' : ''}`}
                                        onClick={() => setModalActiveTab('datos')}
                                    >
                                        📋 Datos del Proyecto
                                    </button>

                                    <button
                                        type="button"
                                        className={`modal-tab ${modalActiveTab === 'presupuesto' ? 'active' : ''}`}
                                        onClick={() => setModalActiveTab('presupuesto')}
                                    >
                                        💰 Presupuesto
                                    </button>
                                    <button
                                        type="button"
                                        className={`modal-tab ${modalActiveTab === 'fuentes' ? 'active' : ''}`}
                                        onClick={() => setModalActiveTab('fuentes')}
                                    >
                                        💰 Financiación
                                    </button>
                                </div>
                            )}

                            {/* Campos comunes */}
                            {(modalType === 'municipios' || modalType === 'estados' || modalType === 'fases' || modalType === 'tiposEventos' || modalType === 'prioridades' || modalType === 'responsables') && (
                                <div className="form-group">
                                    <label htmlFor="nombre">Nombre *</label>
                                    <input
                                        type="text"
                                        id="nombre"
                                        name="nombre"
                                        value={formData.nombre}
                                        onChange={handleInputChange}
                                        required
                                        placeholder="Ingrese el nombre"
                                    />
                                </div>
                            )}

                            {/* Campos específicos para municipios */}
                            {modalType === 'municipios' && (
                            <div className="form-row">
                                <div className="form-group">
                                    <label htmlFor="nombre">Código *</label>
                                    <input
                                        type="text"
                                        id="codigo"
                                        name="codigo"
                                        value={formData.codigo}
                                        onChange={handleInputChange}
                                        required
                                        placeholder="Ingrese el código"
                                    />
                                </div>

                                <div className="form-group">
                                    <label htmlFor="codigo">Departamento *</label>
                                    <select
                                        id="departamento"
                                        name="departamento"
                                        value={formData.departamento}
                                        onChange={handleInputChange}
                                        required
                                    >
                                    <option value="">Seleccione un departamento</option>
                                    {departamentos.map(departamento => (
                                        <option key={departamento.codigo} value={departamento.codigo}>
                                            {departamento.nombre}
                                        </option>
                                    ))}
                                </select>
                                </div>
                                </div>
                            )}

                            {/* Campos específicos para estados, fases y prioridades */}
                            {(modalType === 'estados' || modalType === 'fases' || modalType === 'prioridades') && (
                                <div className="form-group">
                                    <label htmlFor="color">Color *</label>
                                    <input
                                        type="color"
                                        id="color"
                                        name="color"
                                        value={formData.color}
                                        onChange={handleInputChange}
                                        required
                                    />
                                </div>
                            )}

                            {/* Campos específicos para tipos de eventos */}
                            {modalType === 'tiposEventos' && (
                                <div className="form-group">
                                    <label htmlFor="icono">Icono *</label>
                                    <input
                                        type="text"
                                        id="icono"
                                        name="icono"
                                        value={formData.icono}
                                        onChange={handleInputChange}
                                        required
                                        placeholder="Ej: 📋"
                                    />
                                </div>
                            )}

                            {/* Campos específicos para responsables */}
                            {modalType === 'responsables' && (
                                <>
                                    <div className="form-group">
                                        <label htmlFor="email">Email *</label>
                                        <input
                                            type="email"
                                            id="email"
                                            name="email"
                                            value={formData.email}
                                            onChange={handleInputChange}
                                            required
                                            placeholder="ejemplo@empresa.com"
                                        />
                                    </div>
                                    <div className="form-group">
                                        <label htmlFor="cargo">Cargo *</label>
                                        <input
                                            type="text"
                                            id="cargo"
                                            name="cargo"
                                            value={formData.cargo}
                                            onChange={handleInputChange}
                                            required
                                            placeholder="Ej: Gerente de Proyectos"
                                        />
                                    </div>
                                </>
                            )}

                            {/* Pestaña de Presupuesto para proyectos */}
                            {modalType === 'proyectos' && modalActiveTab === 'presupuesto' && (
                                <>
                                    <div className="presupuesto-modal-section">
                                        <h3>Componentes del Presupuesto</h3>
                                        <p className="presupuesto-info">
                                            Agregue los componentes que conforman el presupuesto total del proyecto.
                                        </p>

                                        <div className="componente-form">
                                            <div className="form-row">
                                                <div className="form-group">
                                                    <label htmlFor="descripcion">Descripción del Componente *</label>
                                                    <input
                                                        type="text"
                                                        id="descripcionComponente"
                                                        name="descripcionComponente"
                                                        value={formData.descripcionComponente}
                                                        onChange={handleInputChange}
                                                    />
                                                </div>
                                                <div className="form-group">
                                                    <label htmlFor="valor">Valor *</label>
                                                    <input
                                                        type="number"
                                                        id="valor"
                                                        name="valor"
                                                        value={formData.valor}
                                                        onChange={handleInputChange}
                                                        min="0"
                                                        step="1000"
                                                    />
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={handleAddComponente}
                                                className="btn-add-componente"
                                                disabled={!formData.descripcionComponente || !formData.valor}
                                            >
                                            <FontAwesomeIcon icon={faPlus} /> Agregar Componente
                                            </button>
                                        </div>

                                        <div className="componentes-list">
                                            <h4>Componentes Agregados</h4>
                                            {componentesPresupuesto.length === 0 ? (
                                                <p className="no-componentes">No hay componentes agregados</p>
                                            ) : (
                                                <div className="componentes-table">
                                                    <table>
                                                        <thead>
                                                            <tr>
                                                                <th>Descripción</th>
                                                                <th>Valor</th>
                                                                <th>Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {componentesPresupuesto.map(componente => (
                                                                <tr key={componente.id}>
                                                                    <td>{componente.descripcionComponente}</td>
                                                                    <td>{formatCurrency(componente.valor)}</td>
                                                                    <td>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleDeleteComponente(componente.id)}
                                                                            className="btn-delete-componente"
                                                                        >
                                                                            🗑️
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            )}
                                        </div>

                                        <div className="presupuesto-total">
                                            <div className="total-card">
                                                <span className="total-label">Presupuesto Total:</span>
                                                <span className="total-amount">{formatCurrency(calcularTotalPresupuesto())}</span>
                                            </div>
                                        </div>
                                    </div>
                                </>
                            )}

                            {/* Pestaña de Fuentes de Financiamiento para proyectos */}
                            {modalType === 'proyectos' && modalActiveTab === 'fuentes' && (
                                <>
                                    <div className="fuentes-modal-section">
                                        <h3>Financiación</h3>

                                        <div className="fuentes-form">
                                            <div className="form-row">
                                                <div className="form-group">
                                                    <label htmlFor="fuente">Entidad presenta el proyecto *</label>
                                                    <select
                                                        id="entidadPresenta"
                                                        name="entidadPresenta"
                                                        value={formData.entidadPresenta}
                                                        onChange={handleInputChange}>
                                                        <option value="">Seleccione una entidad</option>
                                                        {entidades.map(entidad => (
                                                            <option key={entidad.id} value={entidad.id}>
                                                                {entidad.nombre}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>

                                                <div className="form-group">
                                                    <label htmlFor="fuente">Entidad Financia el proyecto *</label>
                                                    <select
                                                        id="entidadFinancia"
                                                        name="entidadFinancia"
                                                        value={formData.entidadFinancia}
                                                        onChange={handleInputChange}
                                                        
                                                    >
                                                        <option value="">Seleccione una entidad</option>
                                                        {entidades.map(entidad => (
                                                            <option key={entidad.id} value={entidad.id}>
                                                                {entidad.nombre}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>
                                            </div>
                                            <div className="form-group">
                                                    <label htmlFor="fuente">Fuente de Financiamiento *</label>
                                                    <textarea
                                                        type="text"
                                                        id="fuenteFinanciamiento"
                                                        name="fuenteFinanciamiento"
                                                        value={formData.fuenteFinanciamiento}
                                                        onChange={handleInputChange}
                                                        rows="4"
                                                        placeholder="Ingrese la fuente de financiamiento"
                                                    />
                                                </div>
                                        </div>
                                    </div>
                                </>
                            )}

                            {/* Campos específicos para proyectos - Pestaña Datos */}
                            {modalType === 'proyectos' && modalActiveTab === 'datos' && (
                                <>
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
                                    <div className="form-row">
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
                                                    <option key={municipio.codigo} value={municipio.codigo}>
                                                        {municipio.nombre}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="form-group">
                                            <label htmlFor="fechaInicio">Fecha de Inicio *</label>
                                            <input
                                                type="date"
                                                id="fechaInicio"
                                                name="fechaInicio"
                                                value={formData.fechaInicio}
                                                onChange={handleInputChange}
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
                                                <option value="">Seleccione un estado</option>
                                                {estados.map(estado => (
                                                    <option key={estado.id} value={estado.id}>
                                                        {estado.nombre}
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
                                                required>
                                                <option value="">Seleccione una fase</option>
                                                {fases.map(fase => (
                                                    <option key={fase.id} value={fase.id}>
                                                        {fase.nombre}
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
                                </>
                            )}

                            {/* Campos específicos para eventos */}
                            {modalType === 'eventos' && (
                                <>
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
                                                <option value="">Seleccione un tipo de evento</option>
                                                {tipos.map(tipo => (
                                                    <option key={tipo.id} value={tipo.id}>
                                                        {tipo.nombre}
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
                                                    <option key={prioridad.id} value={prioridad.id}>
                                                        {prioridad.nombre}
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
                                                {estadosEventos.map(estado => (
                                                    <option key={estado.value} value={estado.value}>
                                                        {estado.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    </div>
                                    <div className="form-row">
                                        <div className="form-group">
                                            <label htmlFor="proyecto">Proyecto</label>
                                            <select
                                                id="proyecto"
                                                name="proyecto"
                                                value={formData.proyecto}
                                                onChange={handleInputChange}
                                            >
                                                <option value="">Seleccione un proyecto</option>
                                                {proyectos.map(proyecto => (
                                                    <option key={proyecto.id} value={proyecto.id}>
                                                        {proyecto.nombre}
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
                                                    <option key={responsable.id} value={responsable.id}>
                                                        {responsable.nombre}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    </div>
                                </>
                            )}

                            <div className="modal-actions">
                                <button type="button" onClick={handleCloseModal} className="btn-cancel">
                                    <FontAwesomeIcon icon={faTimes} /> Cancelar
                                </button>
                                <button type="submit" className="btn-save">
                                    <FontAwesomeIcon icon={faSave} />  {editingItem ? 'Actualizar' : 'Guardar'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Parametros;