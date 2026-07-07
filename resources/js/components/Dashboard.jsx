import React, { useState, useEffect } from 'react';
import Header from './Header';
import axios from 'axios';
import '../../css/Dashboard.css';
import '../../css/Dashboard-Extras.css';
import Eventos from './eventos';
import MunicipioCard from './MunicipioCard';
import KpiStrip from './KpiStrip';

const Dashboard = ({ user, onLogout }) => {
    const [activeTab, setActiveTab] = useState(''); // Inicialmente vacío
    const [currentEventIndex, setCurrentEventIndex] = useState(0);
    const [selectedMunicipio, setSelectedMunicipio] = useState(null);
    const [fases, setFases] = useState([]);
    const [proyectos, setProyectos] = useState([]);
    const [modalProyecto, setModalProyecto] = useState(null);

    const [isLargeScreen, setIsLargeScreen] = useState(false);

    // Detectar tamaño de pantalla
    useEffect(() => {
        const checkScreenSize = () => {
            setIsLargeScreen(window.innerWidth >= 1200);
        };

        checkScreenSize();
        window.addEventListener('resize', checkScreenSize);

        return () => window.removeEventListener('resize', checkScreenSize);
    }, []);

    // Función para filtrar proyectos por fase (usando descripcion_fase)
    const getProyectosPorFase = (fase) => {
        return proyectos.filter(proyecto => proyecto.descripcion_fase?.toLowerCase() === fase.toLowerCase());
    };

    // Función para filtrar proyectos por estado (usando descripcion_estado)
    const getProyectosPorEstado = (estado) => {
        return proyectos.filter(proyecto => proyecto.descripcion_estado === estado);
    };

    // Función para filtrar proyectos por municipio y fase
    const getProyectosPorMunicipioYFase = (municipio, fase) => {
        return proyectos.filter(proyecto =>
            proyecto.descripcion_municipio === municipio && proyecto.descripcion_fase?.toLowerCase() === fase.toLowerCase()
        );
    };

    // Función para filtrar proyectos por municipio, fase y estado
    const getProyectosPorMunicipioFaseYEstado = (municipio, fase, estado) => {
        return proyectos.filter(proyecto =>
            proyecto.descripcion_municipio === municipio &&
            proyecto.descripcion_fase?.toLowerCase() === fase.toLowerCase() &&
            proyecto.descripcion_estado?.toLowerCase() === estado.toLowerCase()
        );
    };

    // Función para obtener estadísticas de proyectos por estado
    const getEstadisticasPorEstado = () => {
        const estadisticas = {};
        proyectos.forEach(proyecto => {
            const estado = proyecto.descripcion_estado;
            if (!estadisticas[estado]) {
                estadisticas[estado] = 0;
            }
            estadisticas[estado]++;
        });
        console.log("Estadísticas por estado:", estadisticas);
        return estadisticas;
    };

    // Función para obtener estadísticas por fase
    const getEstadisticasPorFase = () => {
        const estadisticas = {};
        proyectos.forEach(proyecto => {
            const fase = proyecto.descripcion_fase;
            if (!estadisticas[fase]) {
                estadisticas[fase] = 0;
            }
            estadisticas[fase]++;
        });
        console.log("Estadísticas por fase:", estadisticas);
        return estadisticas;
    };

    // Función para obtener estadísticas por municipio
    const getEstadisticasPorMunicipio = () => {
        const estadisticas = {};
        proyectos.forEach(proyecto => {
            const municipio = proyecto.descripcion_municipio;
            if (!estadisticas[municipio]) {
                estadisticas[municipio] = 0;
            }
            estadisticas[municipio]++;
        });
        console.log("Estadísticas por municipio:", estadisticas);
        return estadisticas;
    };

    // Función para obtener datos de progreso de proyectos en ejecución
    const getDatosProgreso = () => {
        const proyectosEnEjecucion = getProyectosPorFase('Ejecución');
        console.log("Proyectos en ejecución:", proyectosEnEjecucion);
        return proyectosEnEjecucion.map(proyecto => ({
            nombre: proyecto.nombre,
            progreso: proyecto.progreso || 0,
            municipio: proyecto.descripcion_municipio
        }));
    };

    // Función para obtener presupuesto total por municipio
    const getPresupuestoPorMunicipio = () => {
        const presupuestos = {};
        proyectos.forEach(proyecto => {
            if (proyecto.totalPresupuesto) {
                const valor = parseInt(proyecto.totalPresupuesto);
                if (!presupuestos[proyecto.descripcion_municipio]) {
                    presupuestos[proyecto.descripcion_municipio] = 0;
                }
                presupuestos[proyecto.descripcion_municipio] += valor;
            }
        });
        console.log("Presupuesto por municipio:", presupuestos);
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
            'Formulación': '#FF9800',
            'Licitación': '#2196F3',
            'Ejecución': '#4CAF50'
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
    const proyectosFormulacion = getProyectosPorFase('Formulación');
    const proyectosLicitacion = getProyectosPorFase('Licitación');
    const proyectosEjecucion = getProyectosPorFase('Ejecución');

    console.log("Proyectos por fase:", {
        formulacion: proyectosFormulacion.length,
        licitacion: proyectosLicitacion.length,
        ejecucion: proyectosEjecucion.length
    });

    // Generar dinámicamente los arrays de proyectos por estado
    const estadosExistentes = Object.keys(getEstadisticasPorEstado());
    const proyectosPorEstado = {};
    estadosExistentes.forEach(estado => {
        proyectosPorEstado[estado] = getProyectosPorEstado(estado);
    });




    const formatDate = (dateString) => {
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return new Date(dateString).toLocaleDateString('es-ES', options);
    };

    

    // Eliminar el array de municipios de ejemplo
    // const [municipios] = useState([...]);

    // Generar municipios únicos a partir de los proyectos
    const municipios = Array.from(
        new Map(
            proyectos.map(p => [p.descripcion_municipio, {
                nombre: p.descripcion_municipio,
                color: '#4CAF50', // Puedes personalizar el color si tienes un campo en el backend
                icon: '🏙️' // Puedes personalizar el icono si tienes un campo en el backend
            }])
        ).values()
    );

    // Agrupar proyectos por municipio usando las nuevas funciones de filtrado
    const proyectosPorMunicipio = municipios.reduce((acc, municipio) => {
        acc[municipio.nombre] = getProyectosPorMunicipioYFase(municipio.nombre, 'Formulación');
        return acc;
    }, {});

    const proyectosLicitacionPorMunicipio = municipios.reduce((acc, municipio) => {
        acc[municipio.nombre] = getProyectosPorMunicipioYFase(municipio.nombre, 'Licitación');
        return acc;
    }, {});

    const proyectosEjecucionPorMunicipio = municipios.reduce((acc, municipio) => {
        acc[municipio.nombre] = getProyectosPorMunicipioYFase(municipio.nombre, 'Ejecución');
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

    const handleOpenModalProyecto = (proyecto) => setModalProyecto(proyecto);
    const handleCloseModalProyecto = () => setModalProyecto(null);

    // Estado para la pestaña activa del modal de proyecto
    const [modalProyectoTab, setModalProyectoTab] = useState('datos');

    // Estado para el contrato seleccionado en el modal
    const [modalContrato, setModalContrato] = useState(null);
  

    useEffect(() => {
        listFases();
        listProyectos();
    }, []);

    

   
    // Cuando se abre un nuevo modal, resetear la pestaña activa
    useEffect(() => {
        if (modalProyecto) setModalProyectoTab('datos');
    }, [modalProyecto]);

    useEffect(() => {
        if (fases.length > 0) {
            setActiveTab(fases[0].nombre);
        }
    }, [fases]);

    const listFases = async () => {
        const response = await axios.get('/fases');
        setFases(response.data);
    };

    const listProyectos = async () => {
        const response = await axios.get('/proyectos');
        setProyectos(response.data);
        console.log(response.data);
    };

 

    // Mostrar solo las fases que tienen dashboard_fase === 1
    const fasesDashboard = fases.filter(fase => fase.dashboard === 1);
    console.log(fasesDashboard);

    // Función para filtrar proyectos por nombre de fase
    const getProyectosPorNombreFase = (nombreFase) => {
        return proyectos.filter(proyecto => proyecto.descripcion_fase === nombreFase);
    };

    // Agrupar proyectos por municipio y nombre de fase
    const getProyectosPorMunicipioYNombreFase = (municipio, nombreFase) => {
        return proyectos.filter(proyecto =>
            proyecto.descripcion_municipio === municipio && proyecto.descripcion_fase === nombreFase
        );
    };

    return (
        <div className="dashboard-container">
            {/* Header */}
            <Header user={user} onLogout={onLogout} />

            {/* Contenido principal */}
            <main className="dashboard-main ocultar-scroll">
                {/* KPIs ejecutivos — globales, no cambian por pestaña */}
                <KpiStrip proyectos={proyectos} />

                {/* Pestañas de Proyectos */}
                <section className="proyectos-tabs-section">
                    <div className="tabs-container">
                        <div className="tabs-header">
                            {fasesDashboard.map(fase => (
                                <button
                                    key={fase.id}
                                    className={`tab-button ${activeTab === fase.nombre ? 'active' : ''}`}
                                    onClick={() => handleTabChange(fase.nombre)}
                                >
                                    {fase.nombre}
                                </button>
                            ))}
                            <button
                                className={`tab-button ${activeTab === 'estadisticas' ? 'active' : ''}`}
                                onClick={() => handleTabChange('estadisticas')}
                            >
                                Estadísticas y Filtros
                            </button>
                        </div>

                        <div className="tab-content">
                            {fasesDashboard.map(fase => (
                                activeTab === fase.nombre && (
                                    <div key={fase.id} className="formulacion-content">
                                        {!selectedMunicipio ? (
                                            <div className="municipios-grid">
                                                {municipios
                                                    .filter(municipio => getProyectosPorMunicipioYNombreFase(municipio.nombre, fase.nombre).length > 0)
                                                    .map(municipio => {
                                                        const proyectosDelMunicipio = getProyectosPorMunicipioYNombreFase(municipio.nombre, fase.nombre) || [];
                                                        return (
                                                            <MunicipioCard
                                                                key={municipio.nombre}
                                                                municipio={municipio}
                                                                proyectos={proyectosDelMunicipio}
                                                                faseNombre={fase.nombre}
                                                                onClick={() => handleMunicipioClick(municipio.nombre)}
                                                            />
                                                        );
                                                    })}
                                            </div>
                                        ) : (
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
                                                            {getProyectosPorMunicipioYNombreFase(selectedMunicipio, fase.nombre)?.length || 0} proyectos
                                                        </span>
                                                    </div>
                                                </div>
                                                <div className="proyectos-grid">
                                                    {getProyectosPorMunicipioYNombreFase(selectedMunicipio, fase.nombre)?.length > 0 ? (
                                                        getProyectosPorMunicipioYNombreFase(selectedMunicipio, fase.nombre).map(proyecto => (
                                                            <div key={proyecto.id} className="proyecto-card" onClick={() => handleOpenModalProyecto(proyecto)}>
                                                                <div className="proyecto-header">
                                                                    <h3>{proyecto.nombre}</h3>
                                                                    <span className="municipio-tag">{proyecto.descripcion_municipio}</span>
                                                                </div>
                                                                <div className="proyecto-info">
                                                                    <div className="info-item">
                                                                        <span className="info-label">Presupuesto:</span>
                                                                        <span className="info-value">$ {proyecto.totalPresupuesto?.toLocaleString()}</span>
                                                                    </div>
                                                                    <div className="info-item">
                                                                        <span className="info-label">Fecha Inicio:</span>
                                                                        <span className="info-value">{formatDate(proyecto.fecha_inicio)}</span>
                                                                    </div>
                                                                    <div className="info-item">
                                                                        <span className="info-label">Estado:</span>
                                                                        <span className={`estado-badge ${proyecto.descripcion_estado?.toLowerCase().replace(' ', '-')}`}>
                                                                            {proyecto.descripcion_estado}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        ))
                                                    ) : (
                                                        <div className="no-proyectos">
                                                            <p>No hay proyectos en {fase.nombre.toLowerCase()} para este municipio.</p>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )
                            ))}
                            {activeTab === 'estadisticas' && (
                                <div className="estadisticas-content">
                                    {/* Tarjetas de resumen */}
                                    <div className="resumen-cards">
                                        <div className="resumen-card">
                                            <div className="resumen-icon">📊</div>
                                            <div className="resumen-info">
                                                <h3>Total Proyectos</h3>
                                                <span className="resumen-valor">{proyectos.length}</span>
                                            </div>
                                        </div>
                                        {estadosExistentes.slice(0, 3).map((estado, index) => (
                                            <div key={estado} className="resumen-card">
                                                <div className="resumen-icon">
                                                    {index === 0 ? '✅' : index === 1 ? '🚀' : '💰'}
                                                </div>
                                                <div className="resumen-info">
                                                    <h3>{estado}</h3>
                                                    <span className="resumen-valor">{proyectosPorEstado[estado].length}</span>
                                                </div>
                                            </div>
                                        ))}
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
                                                                    width: `${(cantidad / proyectos.length) * 100}%`,
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
                                                                    width: `${(cantidad / proyectos.length) * 100}%`,
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

                                    </div>

                                    <div className="graficas-section2">

                                        {/* Nueva gráfica de barras apiladas */}
                                        <div className="grafica-card" style={{ display: 'none' }}>
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

                                    {/* Filtros por Estado - Dinámicos */}
                                    <div className="filtros-section">
                                        <h3>Filtros por Estado</h3>
                                        <div className="filtros-grid">
                                            {Object.entries(getEstadisticasPorEstado()).map(([estado, cantidad]) => {
                                                const proyectosDelEstado = getProyectosPorEstado(estado);
                                                return (
                                                    <div key={estado} className="filtro-card">
                                                        <h4>{estado} ({cantidad})</h4>
                                                        <div className="proyectos-lista">
                                                            {proyectosDelEstado.map(proyecto => (
                                                                <div key={proyecto.id} className="proyecto-item">
                                                                    <span className="proyecto-nombre">{proyecto.nombre}</span>
                                                                    <span className="proyecto-fase">{proyecto.descripcion_fase}</span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                {/* Sección de Eventos con Slider */}
                <Eventos />
            </main>
            {modalProyecto && (
                <div className="modal-proyecto-overlay" onClick={handleCloseModalProyecto}>
                    <div className="modal-proyecto" onClick={e => e.stopPropagation()}>
                        <div className="modal-proyecto-header">
                            <h2><span className="icono">📁</span>{modalProyecto.nombre}</h2>
                            <button className="modal-close-btn" onClick={handleCloseModalProyecto}>×</button>
                        </div>
                        {/* Pestañas del modal */}
                        <div className="modal-tabs">
                            <button
                                className={`modal-tab${modalProyectoTab === 'datos' ? ' active' : ''}`}
                                onClick={() => setModalProyectoTab('datos')}
                            >
                                Datos Generales
                            </button>
                            <button
                                className={`modal-tab${modalProyectoTab === 'componentes' ? ' active' : ''}`}
                                onClick={() => setModalProyectoTab('componentes')}
                            >
                                Componentes
                            </button>
                            {modalProyecto.contratos && modalProyecto.contratos.length > 0 && (
                                <button
                                    className={`modal-tab${modalProyectoTab === 'contratos' ? ' active' : ''}`}
                                    onClick={() => setModalProyectoTab('contratos')}
                                >
                                    Contratos
                                </button>
                            )}
                        </div>
                        <div className="modal-proyecto-content">
                            {modalProyectoTab === 'datos' && (
                                <div className="modal-tab-content datos-generales">
                                    <p><span className="icono">📝</span><strong>Descripción:</strong> {modalProyecto.descripcion}</p>
                                    <p><span className="icono">📍</span><strong>Municipio:</strong> {modalProyecto.descripcion_municipio}</p>
                                    <p><span className="icono">🔄</span><strong>Fase:</strong> {modalProyecto.descripcion_fase}</p>
                                    <p><span className="icono">📊</span><strong>Estado:</strong> {modalProyecto.descripcion_estado}</p>
                                    <p><span className="icono">💰</span><strong>Presupuesto Total:</strong> $ {modalProyecto.totalPresupuesto?.toLocaleString()}</p>
                                    <p><span className="icono">🏢</span><strong>Entidad Presenta:</strong> {modalProyecto.descripcion_entidad_presenta}</p>
                                    <p><span className="icono">🏦</span><strong>Entidad Financia:</strong> {modalProyecto.descripcion_entidad_financia}</p>
                                    <p><span className="icono">💡</span><strong>Fuente de Financiación:</strong> {modalProyecto.fuente_financiacion}</p>
                                </div>
                            )}
                            {modalProyectoTab === 'componentes' && (
                                <div className="modal-tab-content componentes">
                                    <h3>Componentes del Presupuesto</h3>
                                    <ul>
                                        {modalProyecto.componentesPresupuesto && modalProyecto.componentesPresupuesto.length > 0 ? (
                                            modalProyecto.componentesPresupuesto.map(comp => (
                                                <li key={comp.id}>
                                                    <span className="badge-componente">{comp.componente}</span>
                                                    <span className="icono">💵</span>$ {parseInt(comp.valor).toLocaleString()}
                                                </li>
                                            ))
                                        ) : (
                                            <li>No hay componentes registrados.</li>
                                        )}
                                    </ul>
                                </div>
                            )}
                            {modalProyectoTab === 'contratos' && modalProyecto.contratos && (
                                <div className="modal-tab-content contratos">
                                    <h3>Contratos</h3>
                                    {modalProyecto.contratos.length > 0 ? (
                                        <table className="contratos-table">
                                            <thead>
                                                <tr>
                                                    <th>Número</th>
                                                    <th>Objeto</th>
                                                    <th>Valor</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {modalProyecto.contratos.map(contrato => (
                                                    <tr key={contrato.id} className="contrato-row" style={{ cursor: 'pointer' }} onClick={() => setModalContrato(contrato)}>
                                                        <td>{contrato.n_contrato}</td>
                                                        <td>{contrato.objeto}</td>
                                                        <td>$ {contrato.valor ? parseInt(contrato.valor).toLocaleString() : ''}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    ) : (
                                        <div>No hay contratos registrados.</div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
            {modalContrato && (
                <div className="modal-proyecto-overlay" onClick={() => setModalContrato(null)}>
                    <div className="modal-proyecto" onClick={e => e.stopPropagation()}>
                        <div className="modal-proyecto-header">
                            <h2><span className="icono">📄</span>Contrato {modalContrato.n_contrato || modalContrato.numero || modalContrato.nombre}</h2>
                            <button className="modal-close-btn" onClick={() => setModalContrato(null)}>×</button>
                        </div>
                        <div className="modal-proyecto-content">
                            <p><span className="icono">🔢</span><strong>Número de Contrato:</strong> {modalContrato.n_contrato || modalContrato.numero || modalContrato.nombre}</p>
                            <p><span className="icono">📝</span><strong>Objeto:</strong> {modalContrato.objeto || modalContrato.descripcion}</p>
                            <p><span className="icono">🏢</span><strong>Contratante:</strong> {modalContrato.contratante}</p>
                            <p><span className="icono">👷</span><strong>Contratista:</strong> {modalContrato.contratista}</p>
                            <p><span className="icono">💵</span><strong>Monto:</strong> $ {modalContrato.monto ? parseInt(modalContrato.monto).toLocaleString() : (modalContrato.valor ? parseInt(modalContrato.valor).toLocaleString() : '')}</p>
                            <p><span className="icono">📅</span><strong>Fecha de Inicio:</strong> {modalContrato.fecha_inicio || modalContrato.fecha}</p>
                            <p><span className="icono">📅</span><strong>Fecha de Fin:</strong> {modalContrato.fecha_fin}</p>
                            <p><span className="icono">🕵️‍♂️</span><strong>Interventoría:</strong> {modalContrato.interventoria || modalContrato.interventor}</p>
                            <p><span className="icono">📈</span><strong>Avance:</strong> {modalContrato.avance ? `${modalContrato.avance}%` : ''}</p>
                            <p><span className="icono">🔄</span><strong>Estado:</strong> {modalContrato.estado}</p>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Dashboard;
