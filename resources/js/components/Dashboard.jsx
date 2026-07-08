import React, { useState, useEffect } from 'react';
import Header from './Header';
import axios from 'axios';
import '../../css/Dashboard.css';
import '../../css/Dashboard-Extras.css';
import KpiStrip from './KpiStrip';
import Estadisticas from './Estadisticas';
import MapaUbicaciones from './MapaUbicaciones';
import DistribucionMunicipios from './DistribucionMunicipios';
import ResumenFase from './ResumenFase';
import ProximosEventos from './ProximosEventos';

const Dashboard = ({ user, onLogout }) => {
    const [activeTab, setActiveTab] = useState(''); // Inicialmente vacío
    const [municipioResaltado, setMunicipioResaltado] = useState(null);
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

    // Función para filtrar proyectos por nombre de fase
    const getProyectosPorNombreFase = (nombreFase) => {
        return proyectos.filter(proyecto => proyecto.descripcion_fase === nombreFase);
    };

    const handleTabChange = (tab) => {
        setActiveTab(tab);
        setMunicipioResaltado(null); // Reset al cambiar de pestaña
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

    const faseActiva = fasesDashboard.find(fase => fase.nombre === activeTab);
    const proyectosDeLaFaseActiva = faseActiva ? getProyectosPorNombreFase(faseActiva.nombre) : [];

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
                            {faseActiva && (
                                <div className="dashboard-fase-grid">
                                    <div className="dashboard-fase-main">
                                        <div className="fase-vista-header">
                                            <h2>Proyectos en {faseActiva.nombre}</h2>
                                            <p className="fase-vista-subtitulo">Ubicación geográfica de los proyectos</p>
                                        </div>
                                        <MapaUbicaciones
                                            proyectos={proyectosDeLaFaseActiva}
                                            onProyectoClick={handleOpenModalProyecto}
                                            municipioResaltado={municipioResaltado}
                                        />
                                        <DistribucionMunicipios
                                            proyectos={proyectosDeLaFaseActiva}
                                            onMunicipioClick={setMunicipioResaltado}
                                            municipioSeleccionado={municipioResaltado}
                                            onVolver={() => setMunicipioResaltado(null)}
                                            onProyectoClick={handleOpenModalProyecto}
                                        />
                                    </div>
                                    <div className="dashboard-fase-lateral">
                                        <ResumenFase
                                            proyectos={proyectosDeLaFaseActiva}
                                            nombreFase={faseActiva.nombre}
                                        />
                                        <ProximosEventos />
                                    </div>
                                </div>
                            )}
                            {activeTab === 'estadisticas' && <Estadisticas proyectos={proyectos} />}
                        </div>
                    </div>
                </section>
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
