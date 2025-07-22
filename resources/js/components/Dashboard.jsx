import React, { useState } from 'react';
import '../../css/Dashboard.css';
import Header from './Header';

const Dashboard = ({ user, onLogout }) => {
    const [activeTab, setActiveTab] = useState('formulacion');
    const [currentEventIndex, setCurrentEventIndex] = useState(0);
    const [selectedMunicipio, setSelectedMunicipio] = useState(null);

    // Datos de ejemplo - en un proyecto real vendrían de una API
    const [municipios] = useState([
        {
            id: 1,
            nombre: 'Valledupar',
            proyectosFormulacion: 8,
            color: '#4CAF50',
            icon: '🗺️'
        },
        {
            id: 2,
            nombre: 'Aguachica',
            proyectosFormulacion: 5,
            color: '#2196F3',
            icon: ' 🗺️'
        },
        {
            id: 3,
            nombre: 'Codazzi',
            proyectosFormulacion: 3,
            color: '#FF9800',
            icon: '🗺️'
        },
        {
            id: 4,
            nombre: 'La Paz',
            proyectosFormulacion: 6,
            color: '#9C27B0',
            icon: ' 🗺️ '
        }
    ]);

    // Array consolidado con todos los proyectos
    const [todosLosProyectos] = useState([
        // Proyectos en Formulación
        {
            id: 1,
            nombre: 'Centro Deportivo Valledupar',
            municipio: 'Valledupar',
            presupuesto: '$2,500,000',
            fechaInicio: '2024-08-01',
            estado: 'En revisión',
            fase: 'formulacion'
        },
        {
            id: 2,
            nombre: 'Parque Ecológico Aguachica',
            municipio: 'Aguachica',
            presupuesto: '$1,800,000',
            fechaInicio: '2024-08-15',
            estado: 'Aprobado',
            fase: 'formulacion'
        },
        {
            id: 3,
            nombre: 'Mejora Hospital Codazzi',
            municipio: 'Codazzi',
            presupuesto: '$3,200,000',
            fechaInicio: '2024-09-01',
            estado: 'En revisión',
            fase: 'formulacion'
        },
        {
            id: 4,
            nombre: 'Sistema de Agua Potable Valledupar',
            municipio: 'Valledupar',
            presupuesto: '$4,100,000',
            fechaInicio: '2024-08-10',
            estado: 'Aprobado',
            fase: 'formulacion'
        },
        {
            id: 5,
            nombre: 'Centro Comercial La Paz',
            municipio: 'La Paz',
            presupuesto: '$5,200,000',
            fechaInicio: '2024-09-15',
            estado: 'En revisión',
            fase: 'formulacion'
        },
        {
            id: 6,
            nombre: 'Mejora Vías Rurales Aguachica',
            municipio: 'Aguachica',
            presupuesto: '$2,800,000',
            fechaInicio: '2024-08-20',
            estado: 'Aprobado',
            fase: 'formulacion'
        },
        // Proyectos en Licitación
        {
            id: 7,
            nombre: 'Sistema de Alcantarillado Valledupar',
            municipio: 'Valledupar',
            presupuesto: '$4,500,000',
            fechaLicitacion: '2024-07-20',
            estado: 'Abierta',
            fase: 'licitacion'
        },
        {
            id: 8,
            nombre: 'Construcción Escuela Rural La Paz',
            municipio: 'La Paz',
            presupuesto: '$1,200,000',
            fechaLicitacion: '2024-07-25',
            estado: 'En evaluación',
            fase: 'licitacion'
        },
        {
            id: 9,
            nombre: 'Mejora Vías Principales Aguachica',
            municipio: 'Aguachica',
            presupuesto: '$3,800,000',
            fechaLicitacion: '2024-08-01',
            estado: 'Abierta',
            fase: 'licitacion'
        },
        {
            id: 10,
            nombre: 'Centro de Salud Valledupar',
            municipio: 'Valledupar',
            presupuesto: '$2,100,000',
            fechaLicitacion: '2024-08-05',
            estado: 'En evaluación',
            fase: 'licitacion'
        },
        // Proyectos en Ejecución
        {
            id: 11,
            nombre: 'Construcción Centro Comercial Valledupar',
            municipio: 'Valledupar',
            progreso: 75,
            fechaInicio: '2024-01-15',
            fechaFin: '2024-12-30',
            estado: 'En ejecución',
            fase: 'ejecucion'
        },
        {
            id: 12,
            nombre: 'Mejora Vías Principales Aguachica',
            municipio: 'Aguachica',
            progreso: 45,
            fechaInicio: '2024-03-01',
            fechaFin: '2025-02-28',
            estado: 'En ejecución',
            fase: 'ejecucion'
        },
        {
            id: 13,
            nombre: 'Sistema de Alcantarillado Codazzi',
            municipio: 'Codazzi',
            progreso: 90,
            fechaInicio: '2023-11-01',
            fechaFin: '2024-10-31',
            estado: 'En ejecución',
            fase: 'ejecucion'
        },
        {
            id: 14,
            nombre: 'Parque Recreacional La Paz',
            municipio: 'La Paz',
            progreso: 60,
            fechaInicio: '2024-02-01',
            fechaFin: '2024-11-30',
            estado: 'En ejecución',
            fase: 'ejecucion'
        }
    ]);

    // Función para filtrar proyectos por fase
    const getProyectosPorFase = (fase) => {
        return todosLosProyectos.filter(proyecto => proyecto.fase === fase);
    };

    // Función para filtrar proyectos por estado
    const getProyectosPorEstado = (estado) => {
        return todosLosProyectos.filter(proyecto => proyecto.estado === estado);
    };

    // Función para filtrar proyectos por municipio y fase
    const getProyectosPorMunicipioYFase = (municipio, fase) => {
        return todosLosProyectos.filter(proyecto => 
            proyecto.municipio === municipio && proyecto.fase === fase
        );
    };

    // Función para filtrar proyectos por municipio, fase y estado
    const getProyectosPorMunicipioFaseYEstado = (municipio, fase, estado) => {
        return todosLosProyectos.filter(proyecto => 
            proyecto.municipio === municipio && 
            proyecto.fase === fase && 
            proyecto.estado === estado
        );
    };

    // Función para obtener estadísticas de proyectos por estado
    const getEstadisticasPorEstado = () => {
        const estadisticas = {};
        todosLosProyectos.forEach(proyecto => {
            if (!estadisticas[proyecto.estado]) {
                estadisticas[proyecto.estado] = 0;
            }
            estadisticas[proyecto.estado]++;
        });
        return estadisticas;
    };

    // Función para obtener estadísticas por fase
    const getEstadisticasPorFase = () => {
        const estadisticas = {};
        todosLosProyectos.forEach(proyecto => {
            if (!estadisticas[proyecto.fase]) {
                estadisticas[proyecto.fase] = 0;
            }
            estadisticas[proyecto.fase]++;
        });
        return estadisticas;
    };

    // Función para obtener estadísticas por municipio
    const getEstadisticasPorMunicipio = () => {
        const estadisticas = {};
        todosLosProyectos.forEach(proyecto => {
            if (!estadisticas[proyecto.municipio]) {
                estadisticas[proyecto.municipio] = 0;
            }
            estadisticas[proyecto.municipio]++;
        });
        return estadisticas;
    };

    // Función para obtener datos de progreso de proyectos en ejecución
    const getDatosProgreso = () => {
        return proyectosEjecucion.map(proyecto => ({
            nombre: proyecto.nombre,
            progreso: proyecto.progreso,
            municipio: proyecto.municipio
        }));
    };

    // Función para obtener presupuesto total por municipio
    const getPresupuestoPorMunicipio = () => {
        const presupuestos = {};
        todosLosProyectos.forEach(proyecto => {
            if (proyecto.presupuesto) {
                const valor = parseInt(proyecto.presupuesto.replace(/[$,]/g, ''));
                if (!presupuestos[proyecto.municipio]) {
                    presupuestos[proyecto.municipio] = 0;
                }
                presupuestos[proyecto.municipio] += valor;
            }
        });
        return presupuestos;
    };

    // Función para obtener datos de evolución temporal
    const getDatosEvolucionTemporal = () => {
        const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        const datos = {};
        
        // Simular datos de evolución por mes
        meses.forEach((mes, index) => {
            datos[mes] = {
                formulacion: Math.floor(Math.random() * 10) + 5,
                licitacion: Math.floor(Math.random() * 8) + 3,
                ejecucion: Math.floor(Math.random() * 6) + 2
            };
        });
        
        return { meses, datos };
    };

    // Función para obtener datos de presupuesto por mes
    const getDatosPresupuestoMensual = () => {
        const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        const presupuestos = meses.map(() => Math.floor(Math.random() * 5000000) + 2000000);
        return { meses, presupuestos };
    };

    // Funciones para colores de gráficas
    const getEstadoColor = (estado) => {
        const colores = {
            'Aprobado': '#4CAF50',
            'En revisión': '#FF9800',
            'Abierta': '#2196F3',
            'En evaluación': '#9C27B0',
            'En ejecución': '#1976D2'
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

    const getMunicipioColor = (municipio) => {
        const colores = {
            'Valledupar': '#1976D2',
            'Aguachica': '#43A047',
            'Codazzi': '#FF9800',
            'La Paz': '#9C27B0'
        };
        return colores[municipio] || '#757575';
    };

    // Obtener proyectos por fase para usar en el componente
    const proyectosFormulacion = getProyectosPorFase('formulacion');
    const proyectosLicitacion = getProyectosPorFase('licitacion');
    const proyectosEjecucion = getProyectosPorFase('ejecucion');

    // Ejemplos de uso de filtrado por estado
    const proyectosAprobados = getProyectosPorEstado('Aprobado');
    const proyectosEnRevision = getProyectosPorEstado('En revisión');
    const proyectosAbiertos = getProyectosPorEstado('Abierta');
    const proyectosEnEvaluacion = getProyectosPorEstado('En evaluación');
    const proyectosEnEjecucion = getProyectosPorEstado('En ejecución');

    const [proximosEventos] = useState([
        {
            id: 1,
            titulo: 'Fecha límite para pasar pliego',
            descripcion: 'Proyecto: Centro Deportivo Valledupar',
            fecha: '2024-07-15',
            tipo: 'pliego',
            prioridad: 'alta'
        },
        {
            id: 2,
            titulo: 'Presentación de propuesta técnica',
            descripcion: 'Proyecto: Parque Ecológico Aguachica',
            fecha: '2024-07-20',
            tipo: 'propuesta',
            prioridad: 'media'
        },
        {
            id: 3,
            titulo: 'Audiencia pública',
            descripcion: 'Proyecto: Mejora Hospital Codazzi',
            fecha: '2024-07-25',
            tipo: 'audiencia',
            prioridad: 'alta'
        },
        {
            id: 4,
            titulo: 'Entrega de documentación',
            descripcion: 'Proyecto: Escuela Rural La Paz',
            fecha: '2024-07-30',
            tipo: 'documentacion',
            prioridad: 'baja'
        },
        {
            id: 5,
            titulo: 'Revisión de propuestas',
            descripcion: 'Proyecto: Sistema Alcantarillado',
            fecha: '2024-08-05',
            tipo: 'revision',
            prioridad: 'alta'
        },
        {
            id: 6,
            titulo: 'Firma de contratos',
            descripcion: 'Proyecto: Mejora Vías Aguachica',
            fecha: '2024-08-10',
            tipo: 'contrato',
            prioridad: 'media'
        },
        {
            id: 7,
            titulo: 'Inicio de obras',
            descripcion: 'Proyecto: Centro Comercial',
            fecha: '2024-08-15',
            tipo: 'inicio',
            prioridad: 'alta'
        },
        {
            id: 8,
            titulo: 'Inspección de avance',
            descripcion: 'Proyecto: Hospital Codazzi',
            fecha: '2024-08-20',
            tipo: 'inspeccion',
            prioridad: 'baja'
        }
    ]);

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

    const getPriorityColor = (prioridad) => {
        const colors = {
            alta: '#f44336',
            media: '#ff9800',
            baja: '#4caf50'
        };
        return colors[prioridad] || '#757575';
    };

    const nextEvents = () => {
        setCurrentEventIndex(prev => 
            prev + 4 >= proximosEventos.length ? 0 : prev + 4
        );
    };

    const prevEvents = () => {
        setCurrentEventIndex(prev => 
            prev - 4 < 0 ? Math.max(0, proximosEventos.length - 4) : prev - 4
        );
    };

    const visibleEvents = proximosEventos.slice(currentEventIndex, currentEventIndex + 4);

    // Agrupar proyectos por municipio usando las nuevas funciones de filtrado
    const proyectosPorMunicipio = municipios.reduce((acc, municipio) => {
        acc[municipio.nombre] = getProyectosPorMunicipioYFase(municipio.nombre, 'formulacion');
        return acc;
    }, {});

    const proyectosLicitacionPorMunicipio = municipios.reduce((acc, municipio) => {
        acc[municipio.nombre] = getProyectosPorMunicipioYFase(municipio.nombre, 'licitacion');
        return acc;
    }, {});

    const proyectosEjecucionPorMunicipio = municipios.reduce((acc, municipio) => {
        acc[municipio.nombre] = getProyectosPorMunicipioYFase(municipio.nombre, 'ejecucion');
        return acc;
    }, {});

    const handleMunicipioClick = (municipio) => {
        setSelectedMunicipio(selectedMunicipio === municipio ? null : municipio);
    };

    const handleBackToMunicipios = () => {
        setSelectedMunicipio(null);
    };

    const handleTabChange = (tab) => {
        setActiveTab(tab);
        setSelectedMunicipio(null); // Reset municipio selection when changing tabs
    };

    return (
        <div className="dashboard-container">
            {/* Header */}
            <Header user={user} onLogout={onLogout} />

            {/* Contenido principal */}
            <main className="dashboard-main ocultar-scroll">
                {/* Pestañas de Proyectos */}
                <section className="proyectos-tabs-section">
                    <div className="tabs-container">
                        <div className="tabs-header">
                            <button 
                                className={`tab-button ${activeTab === 'formulacion' ? 'active' : ''}`}
                                onClick={() => handleTabChange('formulacion')}
                            >
                                Proyectos en Formulación
                            </button>
                            <button 
                                className={`tab-button ${activeTab === 'licitacion' ? 'active' : ''}`}
                                onClick={() => handleTabChange('licitacion')}
                            >
                                Proyectos en Licitación
                            </button>
                            <button 
                                className={`tab-button ${activeTab === 'ejecucion' ? 'active' : ''}`}
                                onClick={() => handleTabChange('ejecucion')}
                            >
                                Proyectos en Ejecución
                            </button>
                            <button 
                                className={`tab-button ${activeTab === 'estadisticas' ? 'active' : ''}`}
                                onClick={() => handleTabChange('estadisticas')}
                            >
                                Estadísticas y Filtros
                            </button>
                        </div>
                        
                        <div className="tab-content">
                            {activeTab === 'formulacion' && (
                                <div className="formulacion-content">
                                    {!selectedMunicipio ? (
                                        // Vista de municipios
                                        <div className="municipios-grid">
                                            {municipios.map(municipio => {
                                                const proyectosDelMunicipio = proyectosPorMunicipio[municipio.nombre] || [];
                                                return (
                                                    <div 
                                                        key={municipio.id} 
                                                        className="municipio-card-modern"
                                                        style={{ borderLeftColor: municipio.color }}
                                                        onClick={() => handleMunicipioClick(municipio.nombre)}
                                                    >
                                                        <div className="municipio-card-content">
                                                            <div className="municipio-icon-modern">
                                                                {municipio.icon}
                                                            </div>
                                                            <div className="municipio-info-modern">
                                                                <h3>{municipio.nombre}</h3>
                                                                <div className="proyectos-count-modern">
                                                                    <span className="count-number-modern">{proyectosDelMunicipio.length}</span>
                                                                    <span className="count-label-modern">proyectos en formulación</span>
                                                                </div>
                                                            </div>
                                                            <div className="municipio-arrow">
                                                                →
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    ) : (
                                        // Vista de proyectos del municipio seleccionado
                                        <div className="municipio-proyectos-view">
                                            <div className="municipio-header-back">
                                                <button onClick={handleBackToMunicipios} className="back-btn">
                                                    ← Volver a municipios
                                                </button>
                                                <div className="municipio-info-header">
                                                    <span className="municipio-icon-header">
                                                        {municipios.find(m => m.nombre === selectedMunicipio)?.icon || '🏙️'}
                                                    </span>
                                                    <h3 className="municipio-nombre">{selectedMunicipio}</h3>
                                                    <span className="proyectos-count-header">
                                                        {proyectosPorMunicipio[selectedMunicipio]?.length || 0} proyectos
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="proyectos-grid">
                                                {proyectosPorMunicipio[selectedMunicipio]?.map(proyecto => (
                                                    <div key={proyecto.id} className="proyecto-card">
                                                        <div className="proyecto-header">
                                                            <h3>{proyecto.nombre}</h3>
                                                            <span className="municipio-tag">{proyecto.municipio}</span>
                                                        </div>
                                                        <div className="proyecto-info">
                                                            <div className="info-item">
                                                                <span className="info-label">Presupuesto:</span>
                                                                <span className="info-value">{proyecto.presupuesto}</span>
                                                            </div>
                                                            <div className="info-item">
                                                                <span className="info-label">Fecha Inicio:</span>
                                                                <span className="info-value">{formatDate(proyecto.fechaInicio)}</span>
                                                            </div>
                                                            <div className="info-item">
                                                                <span className="info-label">Estado:</span>
                                                                <span className={`estado-badge ${proyecto.estado.toLowerCase().replace(' ', '-')}`}>
                                                                    {proyecto.estado}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                )) || (
                                                    <div className="no-proyectos">
                                                        <p>No hay proyectos en formulación para este municipio.</p>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                            
                            {activeTab === 'licitacion' && (
                                <div className="formulacion-content">
                                    {!selectedMunicipio ? (
                                        // Vista de municipios para licitación
                                        <div className="municipios-grid">
                                            {municipios.map(municipio => {
                                                const proyectosDelMunicipio = proyectosLicitacionPorMunicipio[municipio.nombre] || [];
                                                return (
                                                    <div 
                                                        key={municipio.id} 
                                                        className="municipio-card-modern"
                                                        style={{ borderLeftColor: municipio.color }}
                                                        onClick={() => handleMunicipioClick(municipio.nombre)}
                                                    >
                                                        <div className="municipio-card-content">
                                                            <div className="municipio-icon-modern">
                                                                {municipio.icon}
                                                            </div>
                                                            <div className="municipio-info-modern">
                                                                <h3>{municipio.nombre}</h3>
                                                                <div className="proyectos-count-modern">
                                                                    <span className="count-number-modern">{proyectosDelMunicipio.length}</span>
                                                                    <span className="count-label-modern">proyectos en licitación</span>
                                                                </div>
                                                            </div>
                                                            <div className="municipio-arrow">
                                                                →
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    ) : (
                                        // Vista de proyectos de licitación del municipio seleccionado
                                        <div className="municipio-proyectos-view">
                                            <div className="municipio-header-back">
                                                <button onClick={handleBackToMunicipios} className="back-btn">
                                                    ← Volver a municipios
                                                </button>
                                                <div className="municipio-info-header">
                                                    <span className="municipio-icon-header">
                                                        {municipios.find(m => m.nombre === selectedMunicipio)?.icon || '🏙️'}
                                                    </span>
                                                    <h3 className="municipio-nombre">{selectedMunicipio}</h3>
                                                    <span className="proyectos-count-header">
                                                        {proyectosLicitacionPorMunicipio[selectedMunicipio]?.length || 0} proyectos
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="proyectos-grid">
                                                {proyectosLicitacionPorMunicipio[selectedMunicipio]?.map(proyecto => (
                                                    <div key={proyecto.id} className="proyecto-card">
                                                        <div className="proyecto-header">
                                                            <h3>{proyecto.nombre}</h3>
                                                            <span className="municipio-tag">{proyecto.municipio}</span>
                                                        </div>
                                                        <div className="proyecto-info">
                                                            <div className="info-item">
                                                                <span className="info-label">Presupuesto:</span>
                                                                <span className="info-value">{proyecto.presupuesto}</span>
                                                            </div>
                                                            <div className="info-item">
                                                                <span className="info-label">Fecha Licitación:</span>
                                                                <span className="info-value">{formatDate(proyecto.fechaLicitacion)}</span>
                                                            </div>
                                                            <div className="info-item">
                                                                <span className="info-label">Estado:</span>
                                                                <span className={`estado-badge ${proyecto.estado.toLowerCase().replace(' ', '-')}`}>
                                                                    {proyecto.estado}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                )) || (
                                                    <div className="no-proyectos">
                                                        <p>No hay proyectos en licitación para este municipio.</p>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                            
                            {activeTab === 'ejecucion' && (
                                <div className="formulacion-content">
                                    {!selectedMunicipio ? (
                                        // Vista de municipios para ejecución
                                        <div className="municipios-grid">
                                            {municipios.map(municipio => {
                                                const proyectosDelMunicipio = proyectosEjecucionPorMunicipio[municipio.nombre] || [];
                                                return (
                                                    <div 
                                                        key={municipio.id} 
                                                        className="municipio-card-modern"
                                                        style={{ borderLeftColor: municipio.color }}
                                                        onClick={() => handleMunicipioClick(municipio.nombre)}
                                                    >
                                                        <div className="municipio-card-content">
                                                            <div className="municipio-icon-modern">
                                                                {municipio.icon}
                                                            </div>
                                                            <div className="municipio-info-modern">
                                                                <h3>{municipio.nombre}</h3>
                                                                <div className="proyectos-count-modern">
                                                                    <span className="count-number-modern">{proyectosDelMunicipio.length}</span>
                                                                    <span className="count-label-modern">proyectos en ejecución</span>
                                                                </div>
                                                            </div>
                                                            <div className="municipio-arrow">
                                                                →
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    ) : (
                                        // Vista de proyectos de ejecución del municipio seleccionado
                                        <div className="municipio-proyectos-view">
                                            <div className="municipio-header-back">
                                                <button onClick={handleBackToMunicipios} className="back-btn">
                                                    ← Volver a municipios
                                                </button>
                                                <div className="municipio-info-header">
                                                    <span className="municipio-icon-header">
                                                        {municipios.find(m => m.nombre === selectedMunicipio)?.icon || '🏙️'}
                                                    </span>
                                                    <h3 className="municipio-nombre">{selectedMunicipio}</h3>
                                                    <span className="proyectos-count-header">
                                                        {proyectosEjecucionPorMunicipio[selectedMunicipio]?.length || 0} proyectos
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="proyectos-grid">
                                                {proyectosEjecucionPorMunicipio[selectedMunicipio]?.map(proyecto => (
                                                    <div key={proyecto.id} className="proyecto-card">
                                                        <div className="proyecto-header">
                                                            <h3>{proyecto.nombre}</h3>
                                                            <span className="municipio-tag">{proyecto.municipio}</span>
                                                        </div>
                                                        <div className="proyecto-progreso">
                                                            <div className="progreso-info">
                                                                <span>Progreso: {proyecto.progreso}%</span>
                                                                <div className="progreso-bar">
                                                                    <div 
                                                                        className="progreso-fill"
                                                                        style={{ width: `${proyecto.progreso}%` }}
                                                                    ></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div className="proyecto-fechas">
                                                            <div className="fecha-item">
                                                                <span className="fecha-label">Inicio:</span>
                                                                <span className="fecha-value">{formatDate(proyecto.fechaInicio)}</span>
                                                            </div>
                                                            <div className="fecha-item">
                                                                <span className="fecha-label">Fin:</span>
                                                                <span className="fecha-value">{formatDate(proyecto.fechaFin)}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                )) || (
                                                    <div className="no-proyectos">
                                                        <p>No hay proyectos en ejecución para este municipio.</p>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                            
                            {activeTab === 'estadisticas' && (
                                <div className="estadisticas-content">
                                    <div className="estadisticas-grid">
                                        {/* Tarjetas de resumen */}
                                        <div className="resumen-cards">
                                            <div className="resumen-card">
                                                <div className="resumen-icon">📊</div>
                                                <div className="resumen-info">
                                                    <h3>Total Proyectos</h3>
                                                    <span className="resumen-valor">{todosLosProyectos.length}</span>
                                                </div>
                                            </div>
                                            <div className="resumen-card">
                                                <div className="resumen-icon">✅</div>
                                                <div className="resumen-info">
                                                    <h3>Proyectos Aprobados</h3>
                                                    <span className="resumen-valor">{proyectosAprobados.length}</span>
                                                </div>
                                            </div>
                                            <div className="resumen-card">
                                                <div className="resumen-icon">🚀</div>
                                                <div className="resumen-info">
                                                    <h3>En Ejecución</h3>
                                                    <span className="resumen-valor">{proyectosEnEjecucion.length}</span>
                                                </div>
                                            </div>
                                            <div className="resumen-card">
                                                <div className="resumen-icon">💰</div>
                                                <div className="resumen-info">
                                                    <h3>Presupuesto Total</h3>
                                                    <span className="resumen-valor">
                                                        ${Object.values(getPresupuestoPorMunicipio()).reduce((a, b) => a + b, 0).toLocaleString()}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Gráficas */}
                                        <div className="graficas-section">
                                            <div className="grafica-card">
                                                <h3>Proyectos por Estado</h3>
                                                <div className="grafica-barras">
                                                    {Object.entries(getEstadisticasPorEstado()).map(([estado, cantidad]) => (
                                                        <div key={estado} className="barra-item">
                                                            <div className="barra-label">{estado}</div>
                                                            <div className="barra-container">
                                                                <div 
                                                                    className="barra-fill"
                                                                    style={{ 
                                                                        width: `${(cantidad / todosLosProyectos.length) * 100}%`,
                                                                        backgroundColor: getEstadoColor(estado)
                                                                    }}
                                                                ></div>
                                                                <span className="barra-valor">{cantidad}</span>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>

                                            <div className="grafica-card">
                                                <h3>Proyectos por Fase</h3>
                                                <div className="grafica-pie-real">
                                                    <div className="pie-chart">
                                                        {Object.entries(getEstadisticasPorFase()).map(([fase, cantidad], index) => {
                                                            const total = Object.values(getEstadisticasPorFase()).reduce((a, b) => a + b, 0);
                                                            const porcentaje = (cantidad / total) * 100;
                                                            const angulo = (porcentaje / 100) * 360;
                                                            const anguloAcumulado = Object.entries(getEstadisticasPorFase())
                                                                .slice(0, index)
                                                                .reduce((acc, [_, val]) => acc + (val / total) * 360, 0);
                                                            
                                                            return (
                                                                <div 
                                                                    key={fase}
                                                                    className="pie-slice"
                                                                    style={{
                                                                        background: `conic-gradient(from ${anguloAcumulado}deg, ${getFaseColor(fase)} 0deg, ${getFaseColor(fase)} ${angulo}deg, transparent ${angulo}deg)`
                                                                    }}
                                                                ></div>
                                                            );
                                                        })}
                                                    </div>
                                                    <div className="pie-legend">
                                                        {Object.entries(getEstadisticasPorFase()).map(([fase, cantidad]) => (
                                                            <div key={fase} className="legend-item">
                                                                <div 
                                                                    className="legend-color"
                                                                    style={{ backgroundColor: getFaseColor(fase) }}
                                                                ></div>
                                                                <div className="legend-info">
                                                                    <span className="legend-label">{fase}</span>
                                                                    <span className="legend-value">{cantidad} ({((cantidad / Object.values(getEstadisticasPorFase()).reduce((a, b) => a + b, 0)) * 100).toFixed(1)}%)</span>
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="grafica-card">
                                                <h3>Proyectos por Municipio</h3>
                                                <div className="grafica-barras">
                                                    {Object.entries(getEstadisticasPorMunicipio()).map(([municipio, cantidad]) => (
                                                        <div key={municipio} className="barra-item">
                                                            <div className="barra-label">{municipio}</div>
                                                            <div className="barra-container">
                                                                <div 
                                                                    className="barra-fill"
                                                                    style={{ 
                                                                        width: `${(cantidad / todosLosProyectos.length) * 100}%`,
                                                                        backgroundColor: getMunicipioColor(municipio)
                                                                    }}
                                                                ></div>
                                                                <span className="barra-valor">{cantidad}</span>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>

                                            <div className="grafica-card">
                                                <h3>Progreso de Proyectos en Ejecución</h3>
                                                <div className="progreso-grafica">
                                                    {getDatosProgreso().map((proyecto, index) => (
                                                        <div key={`${proyecto.nombre}-${proyecto.municipio}-${index}`} className="progreso-item">
                                                            <div className="progreso-info">
                                                                <span className="proyecto-nombre">{proyecto.nombre}</span>
                                                                <span className="proyecto-municipio">{proyecto.municipio}</span>
                                                            </div>
                                                            <div className="progreso-bar-container">
                                                                <div 
                                                                    className="progreso-bar-fill"
                                                                    style={{ width: `${proyecto.progreso}%` }}
                                                                ></div>
                                                                <span className="progreso-porcentaje">{proyecto.progreso}%</span>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>

                                            {/* Nueva gráfica de líneas - Evolución Temporal */}
                                            <div className="grafica-card">
                                                <h3>Evolución de Proyectos por Mes</h3>
                                                <div className="linea-grafica">
                                                    <div className="linea-chart">
                                                        {(() => {
                                                            const { meses, datos } = getDatosEvolucionTemporal();
                                                            const maxValue = Math.max(...meses.map(mes => 
                                                                Math.max(datos[mes].formulacion, datos[mes].licitacion, datos[mes].ejecucion)
                                                            ));
                                                            
                                                            return (
                                                                <svg width="100%" height="200" viewBox="0 0 400 200">
                                                                    {/* Líneas de fondo */}
                                                                    {[0, 25, 50, 75, 100].map(y => (
                                                                        <line
                                                                            key={y}
                                                                            x1="0"
                                                                            y1={y * 2}
                                                                            x2="400"
                                                                            y2={y * 2}
                                                                            stroke="#e0e6ed"
                                                                            strokeWidth="1"
                                                                        />
                                                                    ))}
                                                                    
                                                                    {/* Línea de Formulación */}
                                                                    <polyline
                                                                        points={meses.map((mes, index) => 
                                                                            `${(index * 400 / 11) + 20},${200 - (datos[mes].formulacion / maxValue) * 180}`
                                                                        ).join(' ')}
                                                                        fill="none"
                                                                        stroke={getFaseColor('formulacion')}
                                                                        strokeWidth="3"
                                                                        strokeLinecap="round"
                                                                        strokeLinejoin="round"
                                                                    />
                                                                    
                                                                    {/* Puntos de Formulación */}
                                                                    {meses.map((mes, index) => (
                                                                        <circle
                                                                            key={`form-${mes}-${index}`}
                                                                            cx={(index * 400 / 11) + 20}
                                                                            cy={200 - (datos[mes].formulacion / maxValue) * 180}
                                                                            r="4"
                                                                            fill={getFaseColor('formulacion')}
                                                                        />
                                                                    ))}
                                                                    
                                                                    {/* Línea de Licitación */}
                                                                    <polyline
                                                                        points={meses.map((mes, index) => 
                                                                            `${(index * 400 / 11) + 20},${200 - (datos[mes].licitacion / maxValue) * 180}`
                                                                        ).join(' ')}
                                                                        fill="none"
                                                                        stroke={getFaseColor('licitacion')}
                                                                        strokeWidth="3"
                                                                        strokeLinecap="round"
                                                                        strokeLinejoin="round"
                                                                    />
                                                                    
                                                                    {/* Puntos de Licitación */}
                                                                    {meses.map((mes, index) => (
                                                                        <circle
                                                                            key={`lic-${mes}-${index}`}
                                                                            cx={(index * 400 / 11) + 20}
                                                                            cy={200 - (datos[mes].licitacion / maxValue) * 180}
                                                                            r="4"
                                                                            fill={getFaseColor('licitacion')}
                                                                        />
                                                                    ))}
                                                                    
                                                                    {/* Línea de Ejecución */}
                                                                    <polyline
                                                                        points={meses.map((mes, index) => 
                                                                            `${(index * 400 / 11) + 20},${200 - (datos[mes].ejecucion / maxValue) * 180}`
                                                                        ).join(' ')}
                                                                        fill="none"
                                                                        stroke={getFaseColor('ejecucion')}
                                                                        strokeWidth="3"
                                                                        strokeLinecap="round"
                                                                        strokeLinejoin="round"
                                                                    />
                                                                    
                                                                    {/* Puntos de Ejecución */}
                                                                    {meses.map((mes, index) => (
                                                                        <circle
                                                                            key={`ejec-${mes}-${index}`}
                                                                            cx={(index * 400 / 11) + 20}
                                                                            cy={200 - (datos[mes].ejecucion / maxValue) * 180}
                                                                            r="4"
                                                                            fill={getFaseColor('ejecucion')}
                                                                        />
                                                                    ))}
                                                                    
                                                                    {/* Etiquetas de meses */}
                                                                    {meses.map((mes, index) => (
                                                                        <text
                                                                            key={`label-${mes}-${index}`}
                                                                            x={(index * 400 / 11) + 20}
                                                                            y="220"
                                                                            textAnchor="middle"
                                                                            fontSize="10"
                                                                            fill="#8f9bb3"
                                                                        >
                                                                            {mes}
                                                                        </text>
                                                                    ))}
                                                                </svg>
                                                            );
                                                        })()}
                                                    </div>
                                                    <div className="linea-legend">
                                                        <div className="legend-item">
                                                            <div className="legend-color" style={{ backgroundColor: getFaseColor('formulacion') }}></div>
                                                            <span>Formulación</span>
                                                        </div>
                                                        <div className="legend-item">
                                                            <div className="legend-color" style={{ backgroundColor: getFaseColor('licitacion') }}></div>
                                                            <span>Licitación</span>
                                                        </div>
                                                        <div className="legend-item">
                                                            <div className="legend-color" style={{ backgroundColor: getFaseColor('ejecucion') }}></div>
                                                            <span>Ejecución</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Nueva gráfica de barras apiladas */}
                                            <div className="grafica-card">
                                                <h3>Presupuesto Mensual por Municipio</h3>
                                                <div className="barras-apiladas">
                                                    {(() => {
                                                        const { meses, presupuestos } = getDatosPresupuestoMensual();
                                                        const maxPresupuesto = Math.max(...presupuestos);
                                                        const municipios = ['Valledupar', 'Aguachica', 'Codazzi', 'La Paz'];
                                                        
                                                        return (
                                                            <>
                                                                <div className="barras-container">
                                                                    {meses.map((mes, index) => {
                                                                        const presupuestoTotal = presupuestos[index];
                                                                        const alturas = municipios.map((municipio, mIndex) => {
                                                                            const porcentaje = (Math.random() * 0.4) + 0.2; // 20-60% del total
                                                                            return {
                                                                                municipio,
                                                                                altura: (presupuestoTotal * porcentaje / maxPresupuesto) * 150,
                                                                                color: getMunicipioColor(municipio)
                                                                            };
                                                                        });
                                                                        
                                                                        return (
                                                                            <div key={mes} className="barra-apilada">
                                                                                <div className="barra-secciones">
                                                                                    {alturas.map((seccion, sIndex) => (
                                                                                        <div
                                                                                            key={`${mes}-${seccion.municipio}`}
                                                                                            className="barra-seccion"
                                                                                            style={{
                                                                                                height: `${seccion.altura}px`,
                                                                                                backgroundColor: seccion.color
                                                                                            }}
                                                                                            title={`${seccion.municipio}: $${(presupuestoTotal * ((Math.random() * 0.4) + 0.2)).toLocaleString()}`}
                                                                                        ></div>
                                                                                    ))}
                                                                                </div>
                                                                                <div className="barra-label">{mes}</div>
                                                                                <div className="barra-total">${presupuestoTotal.toLocaleString()}</div>
                                                                            </div>
                                                                        );
                                                                    })}
                                                                </div>
                                                                <div className="barras-legend">
                                                                    {municipios.map(municipio => (
                                                                        <div key={municipio} className="legend-item">
                                                                            <div className="legend-color" style={{ backgroundColor: getMunicipioColor(municipio) }}></div>
                                                                            <span>{municipio}</span>
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            </>
                                                        );
                                                    })()}
                                                </div>
                                            </div>
                                        </div>

                                        {/* Filtros por Estado */}
                                        <div className="filtros-section">
                                            <h3>Filtros por Estado</h3>
                                            <div className="filtros-grid">
                                                <div className="filtro-card">
                                                    <h4>Proyectos Aprobados ({proyectosAprobados.length})</h4>
                                                    <div className="proyectos-lista">
                                                        {proyectosAprobados.map(proyecto => (
                                                            <div key={proyecto.id} className="proyecto-item">
                                                                <span className="proyecto-nombre">{proyecto.nombre}</span>
                                                                <span className="proyecto-fase">{proyecto.fase}</span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>

                                                <div className="filtro-card">
                                                    <h4>Proyectos En Revisión ({proyectosEnRevision.length})</h4>
                                                    <div className="proyectos-lista">
                                                        {proyectosEnRevision.map(proyecto => (
                                                            <div key={proyecto.id} className="proyecto-item">
                                                                <span className="proyecto-nombre">{proyecto.nombre}</span>
                                                                <span className="proyecto-fase">{proyecto.fase}</span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>

                                                <div className="filtro-card">
                                                    <h4>Licitaciones Abiertas ({proyectosAbiertos.length})</h4>
                                                    <div className="proyectos-lista">
                                                        {proyectosAbiertos.map(proyecto => (
                                                            <div key={proyecto.id} className="proyecto-item">
                                                                <span className="proyecto-nombre">{proyecto.nombre}</span>
                                                                <span className="proyecto-fase">{proyecto.fase}</span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>

                                                <div className="filtro-card">
                                                    <h4>En Evaluación ({proyectosEnEvaluacion.length})</h4>
                                                    <div className="proyectos-lista">
                                                        {proyectosEnEvaluacion.map(proyecto => (
                                                            <div key={proyecto.id} className="proyecto-item">
                                                                <span className="proyecto-nombre">{proyecto.nombre}</span>
                                                                <span className="proyecto-fase">{proyecto.fase}</span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>

                                                <div className="filtro-card">
                                                    <h4>En Ejecución ({proyectosEnEjecucion.length})</h4>
                                                    <div className="proyectos-lista">
                                                        {proyectosEnEjecucion.map(proyecto => (
                                                            <div key={proyecto.id} className="proyecto-item">
                                                                <span className="proyecto-nombre">{proyecto.nombre}</span>
                                                                <span className="proyecto-fase">{proyecto.fase}</span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                {/* Sección de Eventos con Slider */}
                <section className="eventos-section">
                    <div className="eventos-header">
                        <h2>Próximos Eventos</h2>
                        <div className="slider-controls">
                            <button onClick={prevEvents} className="slider-btn">
                                ‹
                            </button>
                            <span className="slider-indicator">
                                {Math.floor(currentEventIndex / 4) + 1} / {Math.ceil(proximosEventos.length / 4)}
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
                                    style={{ borderLeftColor: getPriorityColor(evento.prioridad) }}
                                >
                                    <div className="evento-icon">
                                        {getEventIcon(evento.tipo)}
                                    </div>
                                    <div className="evento-content">
                                        <h3>{evento.titulo}</h3>
                                        <p className="evento-descripcion">{evento.descripcion}</p>
                                        <div className="evento-fecha">
                                            <span className="fecha-icon">📅</span>
                                            <span>{formatDate(evento.fecha)}</span>
                                        </div>
                                    </div>
                                    <div className="evento-prioridad">
                                        <span 
                                            className="prioridad-badge"
                                            style={{ backgroundColor: getPriorityColor(evento.prioridad) }}
                                        >
                                            {evento.prioridad.toUpperCase()}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            </main>
        </div>
    );
};

export default Dashboard;
