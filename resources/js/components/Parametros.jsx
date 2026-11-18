import React, { useState, useEffect } from 'react';
import { Navigate, useNavigate, useParams } from 'react-router-dom';
import axios from '../axios';
import { faPlus, faTrash, faSave, faTimes, faFile, faEdit, faClipboardCheck, faGavel } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
import EmojiPicker from 'emoji-picker-react';
import { MapContainer, TileLayer, Marker, useMapEvents } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import '../leafletIconFix';
import '../../css/Parametros.css';
import { seccionDesdeSlug } from '../utils/navegacion';
import { alternarFuente, datosFinanciacionDe, financiacionCompleta, fuentesDisponibles, mensajeDeError } from '../utils/fuentesProyecto';
import { formatoMoneda, limpiarMoneda, monedaEnEdicion, numeroDeMoneda } from '../utils/moneda';
import CatalogoModalidades from './CatalogoModalidades';
import CatalogoEtapasContratacion from './CatalogoEtapasContratacion';
import CatalogoTiposContrato from './CatalogoTiposContrato';
import ProcesosProyecto from './ProcesosProyecto';
import ContratosProyecto from './contratos/ContratosProyecto';

// Componente auxiliar: escucha clics en el mapa y reporta la coordenada al padre.
// Debe vivir dentro de <MapContainer> — el hook useMapEvents solo funciona ahí.
const CapturadorClicMapa = ({ onAgregarPunto }) => {
    useMapEvents({
        click(e) {
            onAgregarPunto({ lat: e.latlng.lat, lng: e.latlng.lng });
        },
    });
    return null;
};

// Secciones con componente propio (`propia` en navegacion.js), por su `tab`.
const SECCIONES_PROPIAS = {
    modalidades: CatalogoModalidades,
    'etapas-contratacion': CatalogoEtapasContratacion,
    'tipos-contrato': CatalogoTiposContrato,
};

const CabeceraSeccion = ({ seccion }) => (
    <header className="gp-cabecera">
        <span className="gp-etiqueta">{seccion.grupo}</span>
        <h1>{seccion.titulo}</h1>
        <p>{seccion.descripcion}</p>
    </header>
);

const Parametros = ({ user }) => {
    const navigate = useNavigate();
    // La sección vive en la URL: se puede enlazar, recargar y volver con el navegador.
    const { seccion: slugSeccion } = useParams();
    const seccion = seccionDesdeSlug(slugSeccion);
    const activeTab = seccion?.tab;
    const [showModal, setShowModal] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [modalType, setModalType] = useState('');
    // Proyecto cuyos procesos de contratación se ven en el diálogo, o null.
    const [proyectoProcesos, setProyectoProcesos] = useState(null);
    // Proyecto cuyos contratos se ven en el diálogo, o null.
    const [proyectoContratos, setProyectoContratos] = useState(null);
    const [modalActiveTab, setModalActiveTab] = useState('datos');
    const [proyectos, setProyectos] = useState([]);
    const [municipios, setMunicipios] = useState([]);
    const [estados, setEstados] = useState([]);
    const [fases, setFases] = useState([]);
    const [entidades, setEntidades] = useState([]);
    const [sectores, setSectores] = useState([]);
    const [fuentes, setFuentes] = useState([]);
    const [eventos, setEventos] = useState([]);
    const [tipos, setTiposEventos] = useState([]);
    const [prioridades, setPrioridades] = useState([]);
    const [responsables, setResponsables] = useState([]);
    const [departamentos, setDepartamentos] = useState([]);
    const [detallesPresupuesto, setDetallesPresupuesto] = useState([]);
    const [puntosUbicacion, setPuntosUbicacion] = useState([]);
    // Estado para búsqueda
    const [searchText, setSearchText] = useState('');
    // Estado para el modal de emoji
    const [showEmojiModal, setShowEmojiModal] = useState(false);
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
            { id: 1, nombre: 'Formulación', color: '#FF9800', activo: true, dashboard: true },
            { id: 2, nombre: 'Licitación', color: '#2196F3', activo: true, dashboard: true },
            { id: 3, nombre: 'Ejecución', color: '#4CAF50', activo: true, dashboard: true }
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
        ],
        entidades: [
            { id: 1, nombre: 'Empresa 1', activo: true },
            { id: 2, nombre: 'Empresa 2', activo: true },
            { id: 3, nombre: 'Empresa 3', activo: true },
            { id: 4, nombre: 'Empresa 4', activo: false }
        ],
        fuentes: [],
        sectores: []
    });

    // Datos de proyectos y eventos

    useEffect(() => {
        listProyectos();
        listMunicipios();
        listEstados();
        listFases();
        listEntidades();
        listSectores();
        listFuentes();
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

    const listSectores = async () => {
        const response = await axios.get('/sectores');
        setSectores(response.data);
    }

    const listFuentes = async () => {
        const response = await axios.get('/fuentesFinanciacion');
        setFuentes(response.data);
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
        icono: '🗓️',
        email: '',
        cargo: '',
        // Campos para proyectos
        presupuesto: '',
        fechaInicio: '',
        estado: 'En revisión',
        fase: 'formulacion',
        municipio: '',
        descripcion: '',
        sector: '',
        entidadPresenta: '',
        entidadFinancia: '',
        fuenteIds: [],
            financiacionPorDefinir: false,
        // Campos para eventos
        titulo: '',
        fecha: '',
        tipo: '',
        prioridad: '',
        proyecto: '',
        responsable: '',

        departamento: '',
        dashboard: false,

        // Campos para detalles de presupuesto
        proyectoId: '',
        descripcionComponente: '',
        valor: '',
        accion: 'Agregar'
    });

    // Contratos del proyecto que se edita: van con él a /guardarProyecto
    const [contratos, setContratos] = useState([]);

    const handleEmojiClick = (emojiData) => {
        setFormData(prev => ({
            ...prev,
            icono: emojiData.emoji
        }));
    };

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: value
        }));
    };

    // Fuentes y sectores: el backend rechaza nombres repetidos con un 422.
    const guardarCatalogo = async (ruta, listar, titulo) => {
        try {
            await axios.post(ruta, { ...formData, id: editingItem ? editingItem.id : undefined });
            listar();
            handleCloseModal();
            Swal.fire({ title: titulo, icon: 'success' });
        } catch (error) {
            Swal.fire({
                title: 'No se pudo guardar',
                text: mensajeDeError(error),
                icon: 'error'
            });
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (modalType === 'proyectos') {
            if (!financiacionCompleta(formData)) {
                setModalActiveTab('fuentes');
                Swal.fire({
                    title: 'Falta la fuente de financiación',
                    text: 'Seleccione al menos una fuente o marque que está por definir.',
                    icon: 'warning'
                });
                return;
            }

            // Calcular el presupuesto total de los componentes
            const presupuestoTotal = detallesPresupuesto.reduce((sum, comp) => sum + comp.valor, 0);

            const newProyecto = {
                ...formData,
                id: editingItem ? editingItem.id : Date.now(),
                presupuesto: presupuestoTotal,
                componentesPresupuesto: detallesPresupuesto,
                contratos: contratos,
                puntosUbicacion: puntosUbicacion,
            };
            let response;
            try {
                response = await axios.post('/guardarProyecto', newProyecto);
            } catch (error) {
                Swal.fire({
                    title: 'No se pudo guardar el proyecto',
                    text: mensajeDeError(error),
                    icon: 'error'
                });
                return;
            }
            if (response.status === 200) {
                listProyectos();
                handleCloseModal();
                Swal.fire({
                    title: 'Proyecto guardado correctamente',
                    icon: response.data?.advertencia ? 'warning' : 'success',
                    text: response.data?.advertencia
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
        } else if (modalType === 'tiposEventos') {
            const newTipoEvento = {
                ...formData,
                id: editingItem ? editingItem.id : Date.now(),
                activo: true
            };
            const response = await axios.post('/guardarTipoEvento', newTipoEvento);
            if (response.status === 200) {
                listTiposEventos();
                handleCloseModal();
                Swal.fire({
                    title: 'Tipo de evento guardado correctamente',
                    icon: 'success'
                }).then(() => {
                    listTiposEventos();
                    handleCloseModal();
                });
            }
        } else if (modalType === 'prioridades') {
            const newPrioridad = {
                ...formData,
                id: editingItem ? editingItem.id : Date.now(),
                activo: true
            };
            const response = await axios.post('/guardarPrioridad', newPrioridad);
            if (response.status === 200) {
                listPrioridades();
                handleCloseModal();
                Swal.fire({
                    title: 'Prioridad guardada correctamente',
                    icon: 'success'
                }).then(() => {
                    listPrioridades();
                    handleCloseModal();
                });
            }
        } else if (modalType === 'responsables') {
            const newResponsable = {
                ...formData,
                id: editingItem ? editingItem.id : Date.now(),
                activo: true
            };
            const response = await axios.post('/guardarResponsable', newResponsable);
            if (response.status === 200) {
                listResponsables();
                handleCloseModal();
                Swal.fire({
                    title: 'Responsable guardado correctamente',
                    icon: 'success'
                }).then(() => {
                    listResponsables();
                    handleCloseModal();
                });
            }
        } else if (modalType === 'fuentes') {
            await guardarCatalogo('/guardarFuenteFinanciacion', listFuentes, 'Fuente de financiación guardada correctamente');
            return;
        } else if (modalType === 'sectores') {
            await guardarCatalogo('/guardarSector', listSectores, 'Sector guardado correctamente');
            return;
        } else if (modalType === 'entidades') {
            const newEntidad = {
                ...formData,
                id: editingItem ? editingItem.id : Date.now(),
                activo: true
            };
            const response = await axios.post('/guardarEntidad', newEntidad);
            if (response.status === 200) {
                listEntidades();
                handleCloseModal();
                Swal.fire({
                    title: 'Entidad guardada correctamente',
                    icon: 'success'
                }).then(() => {
                    listEntidades();
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
            icono: item.icono || '🗓️',
            email: item.email || '',
            cargo: item.cargo || '',
            // Campos para proyectos
            municipio: item.municipio || '',
            fechaInicio: item.fecha_inicio || '',
            estado: item.estado || '',
            fase: item.fase || '',
            municipio: item.municipio || '',
            descripcion: item.descripcion || '',
            sector: item.sector || '',
            entidadPresenta: item.entidad_presenta || '',
            entidadFinancia: item.entidad_financia || '',
            ...datosFinanciacionDe(item),
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
            departamento: item.codigo_departamento || '',
            dashboard: item.dashboard || false
        });


        // Cargar componentes de presupuesto si es un proyecto
        if (type === 'proyectos' && item.componentesPresupuesto) {
            console.log(item.componentesPresupuesto);
            setDetallesPresupuesto(
                item.componentesPresupuesto.map(componente => ({
                    id: componente.id,
                    descripcionComponente: componente.componente,
                    valor: parseFloat(componente.valor)
                }))
            );
        } else {
            setDetallesPresupuesto([]);
        }
        // Cargar contratos si es un proyecto
        if (type === 'proyectos' && item.contratos) {
            setContratos(item.contratos);
        } else {
            setContratos([]);
        }

        // Cargar puntos de ubicación si es un proyecto
        if (type === 'proyectos' && item.puntosUbicacion) {
            setPuntosUbicacion(
                item.puntosUbicacion.map(punto => ({
                    lat: parseFloat(punto.lat),
                    lng: parseFloat(punto.lng)
                }))
            );
        } else {
            setPuntosUbicacion([]);
        }

        setModalActiveTab('datos');
        setShowModal(true);
    };

    const handleDelete = async (id, type) => {
        Swal.fire({
            title: '¿Está seguro de que desea eliminar este elemento?',
            ...(type === 'proyectos' ? { text: 'Sus contratos no se borran: quedan sin proyecto.' } : {}),
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
                } else if (type === 'tiposEventos') {
                    const response = await axios.post('/eliminarTipoEvento', { id: id });
                    if (response.status === 200) {
                        listTiposEventos();
                        Swal.fire({
                            title: 'Tipo de evento eliminado correctamente',
                            icon: 'success'
                        });
                    }
                } else if (type === 'prioridades') {
                    const response = await axios.post('/eliminarPrioridad', { id: id });
                    if (response.status === 200) {
                        listPrioridades();
                        Swal.fire({
                            title: 'Prioridad eliminada correctamente',
                            icon: 'success'
                        });
                    }
                } else if (type === 'responsables') {
                    const response = await axios.post('/eliminarResponsable', { id: id });
                    if (response.status === 200) {
                        listResponsables();
                        Swal.fire({
                            title: 'Responsable eliminado correctamente',
                            icon: 'success'
                        });
                    }
                } else if (type === 'entidades') {
                    const response = await axios.post('/eliminarEntidad', { id: id });
                    if (response.status === 200) {
                        listEntidades();
                        Swal.fire({
                            title: 'Entidad eliminada correctamente',
                            icon: 'success'
                        });
                    }
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
            icono: '🗓️',
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
            sector: '',
            entidadPresenta: '',
            entidadFinancia: '',
            fuenteIds: [],
            financiacionPorDefinir: false,
            accion: 'Agregar'
        });
        setDetallesPresupuesto([]);
        setContratos([]); // Resetear contratos al agregar nuevo
        setPuntosUbicacion([]);
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
            icono: '🗓️',
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
            sector: '',
            entidadPresenta: '',
            entidadFinancia: '',
            fuenteIds: [],
            financiacionPorDefinir: false,
            accion: 'Agregar'
        });
        setDetallesPresupuesto([]);
        setContratos([]); // Resetear contratos al cerrar modal
        setPuntosUbicacion([]);
        setModalActiveTab('datos');
    };

    const toggleActiveDashboard = async (id, type, dashboard) => {
        console.log(id, type, dashboard);
        setParametros(prev => ({
            ...prev,
            [type]: prev[type].map(item => item.id === id ? { ...item, dashboard: !item.dashboard } : item)
        }));
        const response = await axios.post('/activarFaseDashboard', { id: id, dashboard: dashboard });
        if (response.status === 200) {
            listFases();
            Swal.fire({
                title: 'Fase activada en Dashboard correctamente',
                icon: 'success'
            });
        }
    };

    const toggleActive = async (id, type, activo) => {
        console.log(id, type, activo);
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
        } else if (type === 'tiposEventos') {
            const response = await axios.post('/activarTipoEvento', { id: id, activo: activo });
            if (response.status === 200) {
                listTiposEventos();
            }
        } else if (type === 'prioridades') {
            const response = await axios.post('/activarPrioridad', { id: id, activo: activo });
            if (response.status === 200) {
                listPrioridades();
            }
        } else if (type === 'responsables') {
            const response = await axios.post('/activarResponsable', { id: id, activo: activo });
            if (response.status === 200) {
                listResponsables();
            }
        } else if (type === 'entidades') {
            const response = await axios.post('/activarEntidad', { id: id, activo: activo });
            if (response.status === 200) {
                listEntidades();
            }
        } else if (type === 'fuentes') {
            try {
                await axios.post('/activarFuenteFinanciacion', { id: id });
                listFuentes();
            } catch (error) {
                Swal.fire({ title: 'No se pudo actualizar', text: mensajeDeError(error), icon: 'error' });
            }
        } else if (type === 'sectores') {
            try {
                await axios.post('/activarSector', { id: id });
                listSectores();
            } catch (error) {
                Swal.fire({ title: 'No se pudo actualizar', text: mensajeDeError(error), icon: 'error' });
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
            entidades: 'Entidad',
            fuentes: 'Fuente de financiación',
            sectores: 'Sector',
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

    const handleValorChange = (e) => {
        const sanitized = limpiarMoneda(e.target.value);
        setFormData(prev => ({
            ...prev,
            valor: sanitized ? monedaEnEdicion(sanitized) : ''
        }));
    };

    const handleAddComponente = () => {
        const valorNumerico = numeroDeMoneda(formData.valor);
        if (formData.descripcionComponente && valorNumerico > 0) {
            const nuevoComponente = {
                id: Date.now(),
                descripcionComponente: formData.descripcionComponente,
                valor: valorNumerico
            };
            setDetallesPresupuesto(prev => [...prev, nuevoComponente]);
            setFormData(prev => ({
                ...prev,
                descripcionComponente: '',
                valor: ''
            }));
        }
    };

    const handleDeleteComponente = (id) => {

        setDetallesPresupuesto(prev => prev.filter(comp => comp.id !== id));
    };

    const calcularTotalPresupuesto = () => {

        return detallesPresupuesto.reduce((sum, comp) => sum + comp.valor, 0);
    };

    const renderTable = (data, type) => {
        // Paginación
        const [currentPage, setCurrentPage] = useState(1);
        const itemsPerPage = 10;
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
                    <span className="tb-contador">{filteredData.length} {filteredData.length === 1 ? 'registro' : 'registros'}</span>
                    <div className="table-acciones">
                        <input
                            type="text"
                            className="search-input"
                            placeholder={`Buscar en ${seccion.titulo.toLowerCase()}…`}
                            aria-label={`Buscar en ${seccion.titulo.toLowerCase()}`}
                            value={searchText}
                            onChange={e => setSearchText(e.target.value)}
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
                                    <td data-etiqueta="Nombre">{item.nombre || item.titulo}</td>
                                    {type === 'municipios' && <td data-etiqueta="Departamento">{item.departamento}</td>}
                                    {type === 'responsables' && <td data-etiqueta="Email">{item.email}</td>}
                                    {type === 'responsables' && <td data-etiqueta="Cargo">{item.cargo}</td>}
                                    {(type === 'estados' || type === 'fases' || type === 'prioridades') && (
                                        <td data-etiqueta="Color">
                                            <div className="color-preview" style={{ backgroundColor: item.color }}></div>
                                        </td>
                                    )}
                                    {type === 'tiposEventos' && <td data-etiqueta="Icono">{item.icono}</td>}
                                    {type === 'proyectos' && <td data-etiqueta="Municipio">{item.descripcion_municipio}</td>}
                                    {type === 'proyectos' && <td data-etiqueta="Presupuesto" className="numero">{formatoMoneda(item.presupuesto)}</td>}
                                    {type === 'proyectos' && (
                                        <td data-etiqueta="Estado">
                                            <span className="status-badge" style={{ backgroundColor: item.color_estado, color: 'white' }}>
                                                {item.descripcion_estado}
                                            </span>
                                        </td>
                                    )}
                                    {type === 'proyectos' && (
                                        <td data-etiqueta="Fase">
                                            <span className="status-badge" style={{ backgroundColor: item.color_fase, color: 'white' }}>
                                                {item.descripcion_fase}
                                            </span>
                                        </td>
                                    )}
                                    {type === 'eventos' && <td data-etiqueta="Fecha">{formatDate(item.fecha)}</td>}
                                    {type === 'eventos' && <td data-etiqueta="Tipo">{item.descripcion_tipo_evento}</td>}
                                    {type === 'eventos' && (
                                        <td data-etiqueta="Prioridad">
                                            <span className="status-badge" style={{ backgroundColor: getPrioridadColor(item.descripcion_prioridad), color: 'white' }}>
                                                {item.descripcion_prioridad.split(' ')[1].toUpperCase()}
                                            </span>
                                        </td>
                                    )}
                                    {type === 'eventos' && <td data-etiqueta="Proyecto">{item.descripcion_proyecto}</td>}
                                    {type === 'eventos' && <td data-etiqueta="Responsable">{item.descripcion_responsable}</td>}

                                    <td className="celda-acciones">
                                        <div className="action-buttons">
                                            {type !== 'proyectos' && type !== 'eventos' && (
                                                <button
                                                    onClick={() => toggleActive(item.id, type, item.activo)}
                                                    className={`btn-toggle ${item.activo ? 'deactivate' : 'activate'}`}
                                                >
                                                    {item.activo ? 'Desactivar' : 'Activar'}
                                                </button>
                                            )}
                                            {type === 'fases' && (
                                                <button
                                                    onClick={() => toggleActiveDashboard(item.id, type, item.dashboard)}
                                                    className={`btn-toggle ${item.dashboard ? 'deactivate' : 'activate'}`}
                                                >
                                                    {item.dashboard ? 'Desactivar en Dashboard' : 'Activar en Dashboard'}
                                                </button>
                                            )}

                                            {type === 'proyectos' && (
                                                <button
                                                    onClick={() => setProyectoContratos(item)}
                                                    className="btn-add-contrato-list"
                                                    title="Agregar Contrato"
                                                >
                                                    <FontAwesomeIcon icon={faFile} /> Contrato
                                                </button>
                                            )}

                                            {type === 'proyectos' && (
                                                <button
                                                    onClick={() => navigate(`/viabilizacion/${item.id}`)}
                                                    className="btn-add-contrato-list btn-expediente"
                                                    title="Expediente"
                                                >
                                                    <FontAwesomeIcon icon={faClipboardCheck} /> Expediente
                                                </button>
                                            )}

                                            {type === 'proyectos' && (
                                                <button
                                                    onClick={() => setProyectoProcesos(item)}
                                                    className="btn-add-contrato-list btn-procesos"
                                                    title="Procesos de contratación"
                                                >
                                                    <FontAwesomeIcon icon={faGavel} /> Procesos
                                                </button>
                                            )}

                                            <button
                                                onClick={() => handleEdit(item, type)}
                                                className="btn-edit"
                                                title="Editar"
                                            >
                                                <FontAwesomeIcon icon={faEdit} /> Editar
                                            </button>
                                            {/* Fuentes y sectores se desactivan: los proyectos los referencian. */}
                                            {type !== 'fuentes' && type !== 'sectores' && (
                                                <button
                                                    onClick={() => handleDelete(item.id, type)}
                                                    className="btn-delete"
                                                    title="Eliminar"
                                                >
                                                    <FontAwesomeIcon icon={faTrash} /> Eliminar
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {/* Controles de paginación */}
                    {totalPages > 1 && (
                        <div className="pagination-controls">
                            <button onClick={() => setCurrentPage(p => Math.max(1, p - 1))} disabled={currentPage === 1} aria-label="Página anterior">&lt;</button>
                            <span>Página {currentPage} de {totalPages}</span>
                            <button onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))} disabled={currentPage === totalPages} aria-label="Página siguiente">&gt;</button>
                        </div>
                    )}
                </div>
            </div>
        );
    };

    const openEmojiModal = () => setShowEmojiModal(true);
    const closeEmojiModal = () => setShowEmojiModal(false);

    if (!seccion) return <Navigate to="/parametros/proyectos" replace />;

    return (
        <div className="parametros-container">
            <CabeceraSeccion seccion={seccion} />

            {activeTab === 'proyectos' && renderTable(proyectos, 'proyectos')}
                        {activeTab === 'eventos-calendario' && renderTable(eventos, 'eventos')}
                        {activeTab === 'municipios' && renderTable(municipios, 'municipios')}
                        {activeTab === 'estados' && renderTable(estados, 'estados')}
                        {activeTab === 'fases' && renderTable(fases, 'fases')}
                        {activeTab === 'eventos' && renderTable(tipos, 'tiposEventos')}
                        {activeTab === 'prioridades' && renderTable(prioridades, 'prioridades')}
                        {activeTab === 'responsables' && renderTable(responsables, 'responsables')}
                        {activeTab === 'entidades' && renderTable(entidades, 'entidades')}
                        {activeTab === 'fuentes-financiacion' && renderTable(fuentes, 'fuentes')}
                        {activeTab === 'sectores' && renderTable(sectores, 'sectores')}

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
                                        Datos del Proyecto
                                    </button>

                                    <button
                                        type="button"
                                        className={`modal-tab ${modalActiveTab === 'presupuesto' ? 'active' : ''}`}
                                        onClick={() => setModalActiveTab('presupuesto')}
                                    >
                                        Presupuesto
                                    </button>
                                    <button
                                        type="button"
                                        className={`modal-tab ${modalActiveTab === 'fuentes' ? 'active' : ''}`}
                                        onClick={() => setModalActiveTab('fuentes')}
                                    >
                                        Financiación
                                    </button>
                                    <button
                                        type="button"
                                        className={`modal-tab ${modalActiveTab === 'ubicacion' ? 'active' : ''}`}
                                        onClick={() => setModalActiveTab('ubicacion')}
                                    >
                                        Ubicación
                                    </button>

                                </div>
                            )}

                            {/* Campos comunes */}
                            {(modalType === 'municipios' || modalType === 'estados' || modalType === 'fases' || modalType === 'tiposEventos' || modalType === 'prioridades' || modalType === 'responsables' || modalType === 'entidades' || modalType === 'fuentes' || modalType === 'sectores') && (
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

                            {modalType === 'fuentes' && (
                                <div className="form-group">
                                    <label htmlFor="descripcion">Descripción</label>
                                    <textarea
                                        id="descripcion"
                                        name="descripcion"
                                        value={formData.descripcion}
                                        onChange={handleInputChange}
                                        rows="3"
                                        placeholder="Opcional"
                                    />
                                </div>
                            )}

                            {/* Campos específicos para municipios */}
                            {modalType === 'municipios' && (
                                <div className="form-row">
                                    <div className="form-group">
                                        <label htmlFor="codigo">Código *</label>
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
                                <>
                                    <div className="form-group">
                                        <label htmlFor="icono">Icono *</label>
                                        <div className="icono-selector">
                                            <span className="icono-actual">{formData.icono}</span>
                                            <button
                                                type="button"
                                                className="btn-select-emoji"
                                                onClick={openEmojiModal}
                                            >
                                                Seleccionar icono
                                            </button>
                                        </div>
                                    </div>
                                </>
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
                                                        type="text"
                                                        id="valor"
                                                        name="valor"
                                                        value={formData.valor}
                                                        onChange={handleValorChange}
                                                        inputMode="decimal"
                                                        placeholder="$ 0"
                                                    />
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={handleAddComponente}
                                                className="btn-add-componente"
                                                disabled={!formData.descripcionComponente || numeroDeMoneda(formData.valor) <= 0}
                                            >
                                                <FontAwesomeIcon icon={faPlus} /> Agregar Componente
                                            </button>
                                        </div>

                                        <div className="componentes-list">
                                            <h4>Componentes Agregados</h4>
                                            {detallesPresupuesto.length === 0 ? (
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
                                                            {detallesPresupuesto.map(componente => (
                                                                <tr key={componente.id}>
                                                                    <td>{componente.descripcionComponente}</td>
                                                                    <td>{formatoMoneda(componente.valor)}</td>
                                                                    <td>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleDeleteComponente(componente.id)}
                                                                            className="btn-delete-componente"
                                                                        >
                                                                            <FontAwesomeIcon icon={faTrash} />
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
                                                <span className="total-amount">{formatoMoneda(calcularTotalPresupuesto())}</span>
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
                                            <fieldset className="form-group fuentes-seleccion">
                                                <legend>Fuentes de financiación *</legend>
                                                <div className="fuentes-seleccion-lista">
                                                    {fuentesDisponibles(fuentes, formData.fuenteIds).map(fuente => (
                                                        <label key={fuente.id} className="checkbox-label">
                                                            <input
                                                                type="checkbox"
                                                                checked={formData.fuenteIds.some(id => String(id) === String(fuente.id))}
                                                                onChange={() => setFormData(prev => ({ ...prev, ...alternarFuente(prev, fuente.id) }))}
                                                            />
                                                            <span>{fuente.nombre}</span>
                                                        </label>
                                                    ))}
                                                </div>
                                                <label className="checkbox-label fuentes-seleccion-pendiente">
                                                    <input
                                                        type="checkbox"
                                                        checked={formData.financiacionPorDefinir}
                                                        onChange={(e) => setFormData(prev => ({
                                                            ...prev,
                                                            financiacionPorDefinir: e.target.checked,
                                                            fuenteIds: e.target.checked ? [] : prev.fuenteIds
                                                        }))}
                                                    />
                                                    <span>Por definir (etapa de estructuración)</span>
                                                </label>
                                            </fieldset>
                                        </div>
                                    </div>
                                </>
                            )}

                            {modalType === 'proyectos' && modalActiveTab === 'ubicacion' && (
                                <>
                                    <p className="mapa-ubicacion-instrucciones">
                                        Haz clic en el mapa para agregar un punto. Haz clic en un punto existente para quitarlo.
                                    </p>
                                    <div className="mapa-ubicacion-container">
                                        <MapContainer center={[6.2442, -75.5812]} zoom={9} style={{ height: '400px', width: '100%' }}>
                                            <TileLayer
                                                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                                                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                                            />
                                            <CapturadorClicMapa onAgregarPunto={(punto) => setPuntosUbicacion(prev => [...prev, punto])} />
                                            {puntosUbicacion.map((punto, index) => (
                                                <Marker
                                                    key={index}
                                                    position={[punto.lat, punto.lng]}
                                                    eventHandlers={{
                                                        click: () => setPuntosUbicacion(prev => prev.filter((_, i) => i !== index)),
                                                    }}
                                                />
                                            ))}
                                        </MapContainer>
                                    </div>
                                    <div className="mapa-ubicacion-info">
                                        <span>{puntosUbicacion.length} punto(s) seleccionado(s)</span>
                                        <button type="button" className="btn-limpiar-puntos" onClick={() => setPuntosUbicacion([])}>
                                            Limpiar todos los puntos
                                        </button>
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
                                                {municipios.filter(municipio => municipio.activo === 1).map(municipio => (
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
                                        <label htmlFor="sector">Sector</label>
                                        <select
                                            id="sector"
                                            name="sector"
                                            value={formData.sector}
                                            onChange={handleInputChange}>
                                            <option value="">Seleccione un sector</option>
                                            {/* Uno ya desactivado se conserva si el proyecto lo tiene. */}
                                            {sectores
                                                .filter(s => s.activo || String(s.id) === String(formData.sector))
                                                .map(s => (
                                                    <option key={s.id} value={s.id}>
                                                        {s.nombre}
                                                    </option>
                                                ))}
                                        </select>
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

            {/* Modal para gestión de contratos */}
            {proyectoContratos && (
                <ContratosProyecto
                    key={proyectoContratos.id}
                    proyecto={proyectoContratos}
                    onCerrar={() => setProyectoContratos(null)}
                    onCambio={listProyectos}
                />
            )}

            {proyectoProcesos && (
                <ProcesosProyecto
                    key={proyectoProcesos.id}
                    proyecto={proyectoProcesos}
                    user={user}
                    onCerrar={() => setProyectoProcesos(null)}
                />
            )}

            {/* Modal para seleccionar emoji (global) */}
            {showEmojiModal && (
                <div className="emoji-fondo" onClick={closeEmojiModal}>
                    <div className="emoji-ventana" role="dialog" aria-modal="true" aria-label="Selecciona un icono" onClick={e => e.stopPropagation()}>
                        <div className="modal-header">
                            <h2>Selecciona un icono <span className="icono-actual">{formData.icono}</span></h2>
                            <button type="button" onClick={closeEmojiModal} className="btn-close" aria-label="Cerrar">×</button>
                        </div>
                        <EmojiPicker
                            onEmojiClick={(emojiData) => {
                                handleEmojiClick(emojiData);
                                closeEmojiModal();
                            }}
                            theme="dark"
                            disableSkinTones
                            disableSearchBar
                            disableAutoFocus
                            disablePreview
                            width="100%"
                        />
                    </div>
                </div>
            )}
        </div>
    );
};

// Una sección propia se pinta sin montar Parametros: no pide sus catálogos ni llama los hooks de
// renderTable. Parametros solo se monta para una sección de tablas, que llama renderTable una vez,
// así que siempre ve el mismo número de hooks (navegacion.test.js lo comprueba).
const ParametrosPorSeccion = ({ user }) => {
    const seccion = seccionDesdeSlug(useParams().seccion);
    if (!seccion) return <Navigate to="/parametros/proyectos" replace />;
    const Propia = seccion?.propia ? SECCIONES_PROPIAS[seccion.tab] : null;
    if (!Propia) return <Parametros user={user} />;

    return (
        <div className="parametros-container">
            <CabeceraSeccion seccion={seccion} />
            <Propia user={user} />
        </div>
    );
};

export default ParametrosPorSeccion;