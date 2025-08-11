import React, { useState, useEffect } from 'react';
import Header from './Header';
import axios from '../axios';
import { faPlus, faTrash, faSave, faTimes, faCalculator } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
import EmojiPicker from 'emoji-picker-react';

const Parametros = ({ user, onLogout }) => {
    const [activeTab, setActiveTab] = useState('proyectos');
    const [showModal, setShowModal] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [modalType, setModalType] = useState('');
    // Estados para el modal de contratos
    const [showContratosModal, setShowContratosModal] = useState(false);
    const [proyectoContratos, setProyectoContratos] = useState(null);
    const [showContratoForm, setShowContratoForm] = useState(false);
    const [editingContrato, setEditingContrato] = useState(null);
    const [modalActiveTab, setModalActiveTab] = useState('datos');
    const [contratoActiveTab, setContratoActiveTab] = useState('informacion');
    const [anexos, setAnexos] = useState([]);
    const [formAnexo, setFormAnexo] = useState({
        descripcion: '',
        archivo: null
    });
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
        dashboard: false,

        // Campos para detalles de presupuesto
        proyectoId: '',
        descripcionComponente: '',
        valor: '',
        accion: 'Agregar'
    });

    // Estados posibles para los contratos
    const estadosContratos = [
        { value: 'vigente', label: 'Vigente' },
        { value: 'ejecutado', label: 'Ejecutado' },
        { value: 'liquidado', label: 'Liquidado' },
        { value: 'suspendido', label: 'Suspendido' },
        { value: 'anulado', label: 'Anulado' },
    ];

    // Estado para la lista de contratos
    const [contratos, setContratos] = useState([]);

    // Campos de formulario para contratos
    const [formContrato, setFormContrato] = useState({
        n_contrato: '',
        objeto: '',
        contratante: '',
        contratista: '',
        valor: '',
        fecha_inicio: '',
        fecha_fin: '',
        interventoria: '',
        avance: '',
        avance_financiero: '',
        avance_fisico: '',
        estado: '',
        proyecto: ''
    });

    // Manejar cambios en el formulario de contratos
    const handleContratoChange = (e) => {
        const { name, value } = e.target;
        setFormContrato(prev => ({ ...prev, [name]: value }));
    };

    // Manejar cambios en el formulario de anexos
    const handleAnexoChange = (e) => {
        const { name, value, files } = e.target;
        if (name === 'archivo') {
            setFormAnexo(prev => ({ ...prev, [name]: files[0] }));
        } else {
            setFormAnexo(prev => ({ ...prev, [name]: value }));
        }
    };

    // Agregar anexo
    const handleAddAnexo = async () => {
        if (!formAnexo.descripcion || !formAnexo.archivo) {
            Swal.fire({ icon: 'warning', title: 'La descripción y el archivo son obligatorios' });
            return;
        }

        try {
            // Crear FormData para enviar el archivo
            const formData = new FormData();
            formData.append('archivo', formAnexo.archivo);
            formData.append('descripcion', formAnexo.descripcion);

            // Subir archivo al servidor
            const response = await axios.post('/subirAnexo', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                }
            });

            if (response.data.success) {
                const nuevoAnexo = {
                    id: Date.now(),
                    descripcion: formAnexo.descripcion,
                    nombreArchivo: formAnexo.archivo.name,
                    rutaArchivo: response.data.ruta,
                    fecha: new Date().toISOString().split('T')[0]
                };

                setAnexos(prev => [...prev, nuevoAnexo]);
                setFormAnexo({ descripcion: '', archivo: null });

                Swal.fire({
                    icon: 'success',
                    title: 'Anexo agregado correctamente',
                    text: response.data.mensaje
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al subir el archivo',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al subir anexo:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error al subir el archivo',
                text: error.response?.data?.mensaje || 'Error interno del servidor'
            });
        }
    };

    // Eliminar anexo
    const handleDeleteAnexo = async (id) => {
        try {
            const response = await axios.post('/eliminarAnexo', { id: id });

            if (response.data.success) {
                setAnexos(prev => prev.filter(anexo => anexo.id !== id));
                Swal.fire({
                    icon: 'success',
                    title: 'Anexo eliminado correctamente',
                    text: response.data.mensaje
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al eliminar el anexo',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al eliminar anexo:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error al eliminar el anexo',
                text: error.response?.data?.mensaje || 'Error interno del servidor'
            });
        }
    };

    // Agregar contrato a la lista
    const handleAddContrato = () => {
        if (!formContrato.n_contrato || !formContrato.objeto || !formContrato.valor || !formContrato.estado) {
            Swal.fire({ icon: 'warning', title: 'Los campos N° Contrato, Objeto, Valor y Estado son obligatorios' });
            return;
        }
        setContratos(prev => [
            ...prev,
            { ...formContrato, id: Date.now() }
        ]);
        setFormContrato({
            n_contrato: '', objeto: '', 
            contratante: '', contratista: '', 
            valor: '', fecha_inicio: '', 
            fecha_fin: '', interventoria: '', 
            avance_financiero: '', 
            avance_fisico: '', estado: '',
            proyecto: proyectoContratos.id || ''
        });
    };

    // Eliminar contrato de la lista
    const handleDeleteContrato = (id) => {
        setContratos(prev => prev.filter(c => c.id !== id));
    };

    // Editar contrato
    const handleEditContrato = (contrato) => {
        setEditingContrato(contrato);
        setFormContrato({
            id: contrato.id || '',
            n_contrato: contrato.n_contrato || '',
            objeto: contrato.objeto || '',
            contratante: contrato.contratante || '',
            contratista: contrato.contratista || '',
            valor: contrato.valor || '',
            fecha_inicio: contrato.fecha_inicio || '',
            fecha_fin: contrato.fecha_fin || '',
            interventoria: contrato.interventoria || '',
            avance_financiero: contrato.avance_financiero || '',
            avance_fisico: contrato.avance_fisico || '',
            estado: contrato.estado || '',
            proyecto: contrato.proyecto || ''
        });
        // Cargar anexos del contrato si existen
        if (contrato.anexos && contrato.anexos.length > 0) {
            const anexosFormateados = contrato.anexos.map(anexo => ({
                id: anexo.id,
                descripcion: anexo.descripcion,
                nombreArchivo: anexo.nombre_archivo,
                rutaArchivo: anexo.ruta_archivo,
                fecha: anexo.fecha
            }));
            setAnexos(anexosFormateados);
        } else {
            setAnexos([]);
        }
        setShowContratoForm(true);
    };

    // Guardar contrato (agregar o actualizar)
    const handleSaveContrato = async () => {
        if (!formContrato.n_contrato || !formContrato.objeto || !formContrato.valor || !formContrato.estado) {
            Swal.fire({ icon: 'warning', title: 'Los campos N° Contrato, Objeto, Valor y Estado son obligatorios' });
            return;
        }

        if (editingContrato) {
            // Actualizar contrato existente
            setContratos(prev => prev.map(c =>
                c.id === editingContrato.id ? { ...formContrato, id: c.id, anexos: anexos } : c
            ));
            setEditingContrato(null);
        } else {
            // Agregar nuevo contrato
            setContratos(prev => [
                ...prev,
                { ...formContrato, id: Date.now(), anexos: anexos }
            ]);
        }

        console.log(formContrato);
        console.log(anexos);
        // Actualizar el proyecto con los nuevos contratos con axios
        const response = await axios.post('/guardarContrato', {
            formContrato: formContrato,
            anexos: anexos.map(anexo => ({
                descripcion: anexo.descripcion,
                nombreArchivo: anexo.nombreArchivo,
                rutaArchivo: anexo.rutaArchivo,
                fecha: anexo.fecha
            }))
        });
        if (response.status === 200) {
            Swal.fire({
                title: 'Contrato guardado correctamente',
                icon: 'success'
            }).then(() => {
                listContratos();
                handleCloseModal();
            });
        }

        // Limpiar formulario
        setFormContrato({
            n_contrato: '', objeto: '', contratante: '',
            contratista: '', valor: '',
            fecha_inicio: '', fecha_fin: '',
            interventoria: '',
            avance_financiero: '',
            avance_fisico: '', estado: '',
            proyecto: proyectoContratos.id || ''
        });
        setShowContratoForm(false);
    };

    // Cancelar edición/agregado de contrato
    const handleCancelContratoForm = () => {
        setShowContratoForm(false);
        setEditingContrato(null);
        setFormContrato({
            n_contrato: '', objeto: '', contratante: '',
            contratista: '', valor: '', fecha_inicio: '',
            fecha_fin: '', interventoria: '',
            avance_financiero: '',
            avance_fisico: '',
            estado: '',
            proyecto: proyectoContratos.id || ''
        });
        setAnexos([]);
        setFormAnexo({ descripcion: '', archivo: null });
        setContratoActiveTab('informacion');
    };

    // Abrir formulario para agregar contrato
    const handleAddNewContrato = () => {
        setEditingContrato(null);
        setFormContrato({
            n_contrato: '', objeto: '', contratante: '',
            contratista: '', valor: '', fecha_inicio: '',
            fecha_fin: '', interventoria: '',
            avance_financiero: '',
            avance_fisico: '', estado: '',
            proyecto: proyectoContratos.id || ''
        });
        setAnexos([]);
        setFormAnexo({ descripcion: '', archivo: null });
        setContratoActiveTab('informacion');
        setShowContratoForm(true);
    };

    const listContratos = async () => {
        const response = await axios.get('/listarContratos', {
            params: {
                proyecto: proyectoContratos.id
            }
        });
        setContratos(response.data);
    }

    const tabs = [
        { id: 'proyectos', label: 'Gestionar Proyectos', icon: '📋' },
        { id: 'eventos-calendario', label: 'Gestionar Eventos', icon: '📅' },
        { id: 'municipios', label: 'Municipios', icon: '🏘️' },
        { id: 'estados', label: 'Estados', icon: '📊' },
        { id: 'fases', label: 'Fases', icon: '🔄' },
        { id: 'eventos', label: 'Tipos de Eventos', icon: '📅' },
        { id: 'prioridades', label: 'Prioridades', icon: '🎯' },
        { id: 'responsables', label: 'Responsables', icon: '👥' },
        { id: 'entidades', label: 'Entidades', icon: '🏢' }
    ];

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

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (modalType === 'proyectos') {
            // Calcular el presupuesto total de los componentes
            const presupuestoTotal = detallesPresupuesto.reduce((sum, comp) => sum + comp.valor, 0);

            const newProyecto = {
                ...formData,
                id: editingItem ? editingItem.id : Date.now(),
                presupuesto: formatCurrency(presupuestoTotal),
                componentesPresupuesto: detallesPresupuesto,
                contratos: contratos,
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
        } else if (type === 'entidades') {
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
            entidadPresenta: '',
            entidadFinancia: '',
            fuenteFinanciamiento: '',
            accion: 'Agregar'
        });
        setDetallesPresupuesto([]);
        setContratos([]); // Resetear contratos al agregar nuevo
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
            entidadPresenta: '',
            entidadFinancia: '',
            fuenteFinanciamiento: '',
            accion: 'Agregar'
        });
        setDetallesPresupuesto([]);
        setContratos([]); // Resetear contratos al cerrar modal
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

    const handleAddContratoProyecto = (item, type) => {
        console.log(item);
        setProyectoContratos(item);
        // Cargar contratos existentes del proyecto
        if (item.contratos && item.contratos.length > 0) {
            setContratos(item.contratos);
        } else {
            setContratos([]);
        }
        // Resetear estado del formulario
        setShowContratoForm(false);
        setEditingContrato(null);
        setFormContrato({
            n_contrato: '', objeto: '',
             contratante: '', contratista: '', 
             valor: '', fecha_inicio: '', 
             fecha_fin: '', interventoria: '', 
             avance_financiero: '', 
             avance_fisico: '', estado: '',
             proyecto: item.id || ''
        });
        setShowContratosModal(true);
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
                                                    onClick={() => handleAddContratoProyecto(item, type)}
                                                    className="btn-add-contrato-list"
                                                >
                                                    📋
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

    const openEmojiModal = () => setShowEmojiModal(true);
    const closeEmojiModal = () => setShowEmojiModal(false);

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
                        {activeTab === 'eventos' && renderTable(tipos, 'tiposEventos')}
                        {activeTab === 'prioridades' && renderTable(prioridades, 'prioridades')}
                        {activeTab === 'responsables' && renderTable(responsables, 'responsables')}
                        {activeTab === 'entidades' && renderTable(entidades, 'entidades')}
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
                            {(modalType === 'municipios' || modalType === 'estados' || modalType === 'fases' || modalType === 'tiposEventos' || modalType === 'prioridades' || modalType === 'responsables' || modalType === 'entidades') && (
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
                                        <div style={{ fontSize: '2rem', marginBottom: '0.5rem', display: 'flex', alignItems: 'center', gap: '1rem' }}>
                                            <span style={{ fontSize: '2.5rem', border: '1px solid #ccc', borderRadius: '8px', padding: '0.2em 0.5em', background: '#fff' }}>{formData.icono}</span>
                                            <button
                                                type="button"
                                                className="btn-select-emoji"
                                                style={{
                                                    background: '#1976d2',
                                                    color: '#fff',
                                                    border: 'none',
                                                    borderRadius: '6px',
                                                    padding: '0.5em 1.2em',
                                                    fontSize: '1.1rem',
                                                    cursor: 'pointer',
                                                    fontWeight: 'bold',
                                                    boxShadow: '0 2px 8px rgba(0,0,0,0.08)'
                                                }}
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
            {showContratosModal && (
                <div className="modal-overlay">
                    <div className="modal-content contratos-modal" onClick={e => e.stopPropagation()}>
                        <div className="modal-header">
                            <h2>📄 Gestión de Contratos - {proyectoContratos?.nombre}</h2>
                            <button onClick={() => setShowContratosModal(false)} className="btn-close">×</button>
                        </div>

                        <div className="contratos-modal-body">
                            {!showContratoForm ? (
                                // Vista de lista de contratos
                                <>
                                    <div className="contratos-list-header">
                                        <h3>Contratos del Proyecto</h3>
                                        <button
                                            onClick={handleAddNewContrato}
                                            className="btn-add-contrato-header"
                                        >
                                            <FontAwesomeIcon icon={faPlus} /> Agregar Contrato
                                        </button>
                                    </div>

                                    <div className="contratos-list">
                                        {contratos.length === 0 ? (
                                            <div className="no-contratos-container">
                                                <p className="no-contratos">No hay contratos registrados para este proyecto</p>
                                                <button
                                                    onClick={handleAddNewContrato}
                                                    className="btn-add-first-contrato"
                                                >
                                                    <FontAwesomeIcon icon={faPlus} /> Agregar Primer Contrato
                                                </button>
                                            </div>
                                        ) : (
                                            <div className="contratos-table">
                                                <table>
                                                    <thead>
                                                        <tr>
                                                            <th>N° Contrato</th>
                                                            <th>Objeto</th>
                                                            <th>Valor</th>
                                                            <th>Estado</th>
                                                            <th>Anexos</th>
                                                            <th>Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {contratos.map(contrato => (
                                                            <tr key={contrato.id}>
                                                                <td>{contrato.n_contrato}</td>
                                                                <td>{contrato.objeto}</td>
                                                                <td>{formatCurrency(Number(contrato.valor))}</td>
                                                                <td>{estadosContratos.find(e => e.value === contrato.estado)?.label || contrato.estado}</td>
                                                                <td>
                                                                    <span className="anexos-count">
                                                                        {contrato.anexos ? contrato.anexos.length : 0} anexos
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <div className="contrato-actions">
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleEditContrato(contrato)}
                                                                            className="btn-edit-contrato"
                                                                            title="Editar contrato"
                                                                        >
                                                                            ✏️
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleDeleteContrato(contrato.id)}
                                                                            className="btn-delete-contrato"
                                                                            title="Eliminar contrato"
                                                                        >
                                                                            <FontAwesomeIcon icon={faTrash} />
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </div>
                                </>
                            ) : (
                                // Vista del formulario
                                <>
                                    <div className="contrato-form-header">
                                        <h3>{editingContrato ? 'Editar Contrato' : 'Agregar Nuevo Contrato'}</h3>
                                        <button
                                            onClick={handleCancelContratoForm}
                                            className="btn-back-to-list"
                                        >
                                            ← Volver a la lista
                                        </button>
                                    </div>

                                    {/* Pestañas del formulario de contrato */}
                                    <div className="contrato-tabs">
                                        <button
                                            className={`contrato-tab ${contratoActiveTab === 'informacion' ? 'active' : ''}`}
                                            onClick={() => setContratoActiveTab('informacion')}
                                        >
                                            📄 Información del Contrato
                                        </button>
                                        <button
                                            className={`contrato-tab ${contratoActiveTab === 'anexos' ? 'active' : ''}`}
                                            onClick={() => setContratoActiveTab('anexos')}
                                        >
                                            📎 Anexos ({anexos.length})
                                        </button>
                                    </div>

                                    {/* Contenido de la pestaña de información */}
                                    {contratoActiveTab === 'informacion' && (
                                        <div className="contratos-modal-section">
                                            <p className="contratos-info">
                                                Complete los datos del contrato. Los campos marcados con * son obligatorios.
                                            </p>
                                            <div className="contrato-form">
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="n_contrato">N° Contrato *</label>
                                                        <input type="text" id="n_contrato" name="n_contrato" value={formContrato.n_contrato} onChange={handleContratoChange} />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="valor">Valor *</label>
                                                        <input type="number" id="valor" name="valor" value={formContrato.valor} onChange={handleContratoChange} min="0" step="1000" />
                                                    </div>
                                                </div>
                                                <div className="form-group">
                                                    <label htmlFor="objeto">Objeto *</label>
                                                    <textarea
                                                        id="objeto"
                                                        name="objeto"
                                                        value={formContrato.objeto}
                                                        onChange={handleContratoChange}
                                                        rows="4"
                                                        placeholder="Ingrese el objeto del contrato"
                                                    />
                                                </div>
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="contratante">Contratante *</label>
                                                        <input type="text" id="contratante" name="contratante" value={formContrato.contratante} onChange={handleContratoChange} />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="contratista">Contratista *</label>
                                                        <input type="text" id="contratista" name="contratista" value={formContrato.contratista} onChange={handleContratoChange} />
                                                    </div>
                                                </div>
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="fecha_inicio">Fecha Inicio *</label>
                                                        <input type="date" id="fecha_inicio" name="fecha_inicio" value={formContrato.fecha_inicio} onChange={handleContratoChange} />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="fecha_fin">Fecha Fin *</label>
                                                        <input type="date" id="fecha_fin" name="fecha_fin" value={formContrato.fecha_fin} onChange={handleContratoChange} />
                                                    </div>
                                                </div>
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="interventoria">Interventoría *</label>
                                                        <input type="text" id="interventoria" name="interventoria" value={formContrato.interventoria} onChange={handleContratoChange} />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="estado">Estado *</label>
                                                        <select id="estado" name="estado" value={formContrato.estado} onChange={handleContratoChange}>
                                                            <option value="">Seleccione un estado</option>
                                                            {estadosContratos.map(e => (
                                                                <option key={e.value} value={e.value}>{e.label}</option>
                                                            ))}
                                                        </select>
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="avance_financiero">Avance Financiero</label>
                                                        <div className="avance-financiero-container">
                                                            <input type="text" disabled id="avance_financiero" name="avance_financiero" value={formContrato.avance_financiero} onChange={handleContratoChange} />
                                                            <button type="button" className="btn-calcular-avance" title='Calcular Avance Financiero'><FontAwesomeIcon icon={faCalculator} /></button>
                                                        </div>
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="avance_fisico">Avance Físico</label>
                                                        <div className="avance-fisico-container">
                                                            <input type="text" id="avance_fisico" disabled name="avance_fisico" value={formContrato.avance_fisico} onChange={handleContratoChange} />
                                                            <button type="button" className="btn-calcular-avance" title='Calcular Avance Físico'><FontAwesomeIcon icon={faCalculator} /></button>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Contenido de la pestaña de anexos */}
                                    {contratoActiveTab === 'anexos' && (
                                        <div className="contratos-modal-section">
                                            <p className="contratos-info">
                                                Gestione los anexos del contrato. Puede agregar y eliminar documentos relacionados.
                                            </p>

                                            {/* Formulario para agregar anexo */}
                                            <div className="anexo-form">
                                                <h4>Agregar Nuevo Anexo</h4>
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="descripcion_anexo">Descripción *</label>
                                                        <input
                                                            type="text"
                                                            id="descripcion_anexo"
                                                            name="descripcion"
                                                            value={formAnexo.descripcion}
                                                            onChange={handleAnexoChange}
                                                            placeholder="Descripción del anexo"
                                                        />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="archivo_anexo">Archivo *</label>
                                                        <input
                                                            type="file"
                                                            id="archivo_anexo"
                                                            name="archivo"
                                                            onChange={handleAnexoChange}
                                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                                        />
                                                    </div>
                                                    <div className="form-group">
                                                        <button
                                                            type="button"
                                                            onClick={handleAddAnexo}
                                                            className="btn-add-anexo"
                                                            disabled={!formAnexo.descripcion || !formAnexo.archivo}
                                                        >
                                                            <FontAwesomeIcon icon={faPlus} /> Agregar Anexo
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Lista de anexos */}
                                            <div className="anexos-list">
                                                <h4>Anexos del Contrato ({anexos.length})</h4>
                                                {anexos.length === 0 ? (
                                                    <div className="no-anexos">
                                                        <p>No hay anexos agregados para este contrato</p>
                                                    </div>
                                                ) : (
                                                    <div className="anexos-table">
                                                        <table>
                                                            <thead>
                                                                <tr>
                                                                    <th>Descripción</th>
                                                                    <th>Archivo</th>
                                                                    <th>Fecha</th>
                                                                    <th>Acciones</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {anexos.map(anexo => (
                                                                    <tr key={anexo.id}>
                                                                        <td>{anexo.descripcion}</td>
                                                                        <td>{anexo.nombreArchivo}</td>
                                                                        <td>{anexo.fecha}</td>
                                                                        <td>
                                                                            <div className="anexo-actions">
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => window.open('/' + anexo.rutaArchivo, '_blank')}
                                                                                    className="btn-view-anexo"
                                                                                    title="Ver anexo"
                                                                                >
                                                                                    👁️
                                                                                </button>
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => handleDeleteAnexo(anexo.id)}
                                                                                    className="btn-delete-anexo"
                                                                                    title="Eliminar anexo"
                                                                                >
                                                                                    <FontAwesomeIcon icon={faTrash} />
                                                                                </button>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                ))}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </>
                            )}
                        </div>

                        <div className="modal-actions">
                            {!showContratoForm && (
                                <button type="button" onClick={() => setShowContratosModal(false)} className="btn-cancel">
                                    <FontAwesomeIcon icon={faTimes} /> Cerrar
                                </button>
                            )}
                            {showContratoForm && (
                                <>
                                    <button type="button" onClick={handleCancelContratoForm} className="btn-cancel">
                                        <FontAwesomeIcon icon={faTimes} /> Cancelar
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleSaveContrato}
                                        className="btn-save"
                                        disabled={!formContrato.n_contrato || !formContrato.objeto || !formContrato.valor || !formContrato.estado}
                                    >
                                        <FontAwesomeIcon icon={faSave} /> {editingContrato ? 'Actualizar' : 'Guardar'} Contrato
                                    </button>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* Modal para seleccionar emoji (global) */}
            {showEmojiModal && (
                <div style={{
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    width: '100vw',
                    height: '100vh',
                    background: 'rgba(0,0,0,0.35)',
                    zIndex: 9999,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                }}
                    onClick={closeEmojiModal}
                >
                    <div
                        style={{
                            background: '#fff',
                            borderRadius: '12px',
                            boxShadow: '0 4px 24px rgba(0,0,0,0.18)',
                            maxWidth: 600,
                            width: '90vw',
                            padding: '2rem 1.5rem 1.5rem 1.5rem',
                            position: 'relative',
                            textAlign: 'center',
                        }}
                        onClick={e => e.stopPropagation()}
                    >
                        <button
                            type="button"
                            onClick={closeEmojiModal}
                            style={{
                                position: 'absolute',
                                top: 10,
                                right: 15,
                                background: 'transparent',
                                border: 'none',
                                fontSize: '2rem',
                                color: '#888',
                                cursor: 'pointer',
                            }}
                            aria-label="Cerrar"
                        >
                            ×
                        </button>
                        <h3 style={{ marginBottom: '1rem' }}>Selecciona un icono</h3>
                        <div style={{ fontSize: '3rem', marginBottom: '1rem', border: '1px solid #eee', borderRadius: '8px', display: 'inline-block', padding: '0.3em 0.7em', background: '#f9f9f9' }}>
                            {formData.icono}
                        </div>
                        <div style={{ margin: '0 auto', width: '100%', maxWidth: 600 }}>
                            <EmojiPicker
                                onEmojiClick={(emojiData) => {
                                    handleEmojiClick(emojiData);
                                    closeEmojiModal();
                                }}
                                disableSkinTones
                                disableSearchBar
                                disableAutoFocus
                                disablePreview
                                width={600}
                                style={{
                                    width: '100%',
                                    overflowY: 'auto',
                                    border: '1px solid #ccc',
                                    borderRadius: '5px',
                                    padding: '10px',
                                    background: '#fff',
                                }}
                            />
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Parametros;