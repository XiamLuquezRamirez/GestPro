import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import axios from 'axios';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCalendarDays, faChartColumn, faClipboardCheck, faFileSignature, faLocationDot, faTv } from '@fortawesome/free-solid-svg-icons';
import '../../css/Tablero.css';
import ContratosPanel from './ContratosPanel';
import DistribucionMunicipios from './DistribucionMunicipios';
import Estadisticas from './Estadisticas';
import ExpedientesSgrPanel from './ExpedientesSgrPanel';
import FlujoFases from './FlujoFases';
import KpiStrip from './KpiStrip';
import MapaUbicaciones from './MapaUbicaciones';
import ModalProyecto from './ModalProyecto';
import ModoSala from './ModoSala';
import ProximosEventos from './ProximosEventos';
import RiesgosFase from './RiesgosFase';
import { mensajeError } from '../utils/expedienteChecklist';
import { VISTA, filasEnSala, resolverVista, resumirFases, slugFase } from '../utils/tablero';

const VISTAS_ANALISIS = [
    { id: VISTA.ESTADISTICAS, titulo: 'Estadísticas', icono: faChartColumn },
    { id: VISTA.CONTRATOS, titulo: 'Contratos', icono: faFileSignature },
    { id: VISTA.EXPEDIENTES, titulo: 'Expedientes', icono: faClipboardCheck },
];

const hoyEnTexto = () => new Date().toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

const Dashboard = ({ sala = false }) => {
    const [searchParams, setSearchParams] = useSearchParams();
    const [fases, setFases] = useState([]);
    const [proyectos, setProyectos] = useState([]);
    const [resumenExpedientes, setResumenExpedientes] = useState(null);
    const [errorExpedientes, setErrorExpedientes] = useState(null);
    const [errorCarga, setErrorCarga] = useState(null);
    const [cargando, setCargando] = useState(true);
    const [ultimaCarga, setUltimaCarga] = useState(null);
    const [municipioResaltado, setMunicipioResaltado] = useState(null);
    const [modalProyecto, setModalProyecto] = useState(null);

    const listExpedientes = useCallback(async () => {
        setErrorExpedientes(null);
        try {
            const { data } = await axios.get('/expedientes');
            setResumenExpedientes(data);
        } catch (error) {
            // El resto del tablero sigue funcionando: solo el seguimiento lo indica.
            setErrorExpedientes(mensajeError(error));
        }
    }, []);

    const cargar = useCallback(async () => {
        setErrorCarga(null);
        try {
            const [respuestaFases, respuestaProyectos] = await Promise.all([axios.get('/fases'), axios.get('/proyectos')]);
            setFases(respuestaFases.data);
            setProyectos(respuestaProyectos.data);
            setUltimaCarga(new Date());
        } catch (error) {
            // Se conservan los datos anteriores: en una sala es mejor verlos con aviso que vacíos.
            setErrorCarga({ mensaje: mensajeError(error), sesionVencida: error?.response?.status === 401 });
        } finally {
            setCargando(false);
        }
        await listExpedientes();
    }, [listExpedientes]);

    useEffect(() => {
        cargar();
    }, [cargar]);

    const fasesTablero = useMemo(() => fases.filter((f) => f.dashboard === 1), [fases]);
    const resumenFases = useMemo(
        () => resumirFases(fasesTablero, proyectos, resumenExpedientes),
        [fasesTablero, proyectos, resumenExpedientes],
    );

    // Un resultado del buscador Ctrl+K llega como ?proyecto=ID.
    const proyectoPedido = searchParams.get('proyecto');
    useEffect(() => {
        if (!proyectoPedido || proyectos.length === 0) return;
        const encontrado = proyectos.find((p) => String(p.id) === proyectoPedido);
        if (encontrado) setModalProyecto(encontrado);
        setSearchParams((previos) => {
            const siguientes = new URLSearchParams(previos);
            siguientes.delete('proyecto');
            return siguientes;
        }, { replace: true });
    }, [proyectoPedido, proyectos, setSearchParams]);

    const renderVista = (vista, compacto = false) => {
        if (vista === VISTA.ESTADISTICAS) return <Estadisticas proyectos={proyectos} />;
        if (vista === VISTA.CONTRATOS) return <ContratosPanel proyectos={proyectos} compacto={compacto} />;
        if (vista === VISTA.EXPEDIENTES) {
            return (
                <ExpedientesSgrPanel
                    proyectos={proyectos}
                    resumenes={resumenExpedientes}
                    error={errorExpedientes}
                    onReintentar={listExpedientes}
                    compacto={compacto}
                />
            );
        }

        const fase = fasesTablero.find((f) => slugFase(f.nombre) === vista);
        if (!fase) return null;
        const delGrupo = proyectos.filter((p) => p.descripcion_fase === fase.nombre);
        // En la sala, riesgos y eventos comparten una columna sin desplazamiento: se reparten el alto.
        const hayRiesgos = delGrupo.some((p) => p.descripcion_estado === 'Con retraso');
        const filasCompartidas = filasEnSala(window.innerHeight, window.innerWidth) === 8 ? 3 : 2;
        const limiteLateral = compacto && hayRiesgos ? filasCompartidas : undefined;

        return (
            <div className={`tb-fase${compacto ? ' compacto' : ''}`} key={fase.id}>
                <section className="tb-mapa gp-panel" aria-label={`Mapa de proyectos en ${fase.nombre}`}>
                    <header className="tb-mapa-cabecera">
                        <FontAwesomeIcon icon={faLocationDot} />
                        <div className="tb-mapa-titulo">
                            <h2>Territorio</h2>
                            <span>Proyectos en {fase.nombre}</span>
                        </div>
                        {!compacto && (
                            <select
                                className="tb-mapa-filtro"
                                aria-label="Filtrar por municipio"
                                value={municipioResaltado ?? ''}
                                onChange={(e) => setMunicipioResaltado(e.target.value || null)}
                            >
                                <option value="">Todos los municipios</option>
                                {[...new Set(delGrupo.map((p) => p.descripcion_municipio).filter(Boolean))].sort().map((m) => (
                                    <option key={m} value={m}>{m}</option>
                                ))}
                            </select>
                        )}
                    </header>
                    <MapaUbicaciones
                        proyectos={delGrupo}
                        onProyectoClick={setModalProyecto}
                        municipioResaltado={municipioResaltado}
                    />
                </section>
                <DistribucionMunicipios
                    proyectos={delGrupo}
                    onMunicipioClick={setMunicipioResaltado}
                    municipioSeleccionado={municipioResaltado}
                    onVolver={() => setMunicipioResaltado(null)}
                    onProyectoClick={setModalProyecto}
                    compacto={compacto}
                />
                <div className="tb-fase-lateral">
                    <RiesgosFase proyectos={delGrupo} onProyectoClick={setModalProyecto} limite={limiteLateral} />
                    <ProximosEventos limite={limiteLateral} />
                </div>
            </div>
        );
    };

    if (sala) {
        return (
            <ModoSala
                resumenFases={resumenFases}
                proyectos={proyectos}
                ultimaCarga={ultimaCarga}
                errorCarga={errorCarga}
                recargar={cargar}
                renderVista={renderVista}
            />
        );
    }

    const vista = resolverVista(searchParams.get('vista'), fasesTablero);

    const cambiarVista = (siguiente) => {
        setMunicipioResaltado(null);
        setSearchParams({ vista: siguiente }, { replace: true });
    };

    return (
        <div className="tb">
            <header className="tb-cabecera">
                <div className="tb-titulo">
                    <span className="tb-fecha"><FontAwesomeIcon icon={faCalendarDays} /> {hoyEnTexto()}</span>
                    <h1>Tablero</h1>
                </div>
                <div className="tb-cabecera-acciones">
                    <div className="tb-analisis" role="group" aria-label="Vistas de análisis">
                        {VISTAS_ANALISIS.map((v) => (
                            <button
                                key={v.id}
                                type="button"
                                className={vista === v.id ? 'activo' : ''}
                                aria-pressed={vista === v.id}
                                onClick={() => cambiarVista(v.id)}
                            >
                                <FontAwesomeIcon icon={v.icono} /> {v.titulo}
                            </button>
                        ))}
                    </div>
                    <Link to="/sala" className="gp-boton tb-boton-sala" title="Pantalla completa para TV o sala de reuniones">
                        <FontAwesomeIcon icon={faTv} /> Modo sala
                    </Link>
                </div>
            </header>

            {errorCarga && (
                <div className="tb-aviso" role="alert">
                    <span>No se pudieron cargar los datos del tablero. {errorCarga.mensaje}</span>
                    <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>
                </div>
            )}

            <KpiStrip proyectos={proyectos} />
            <FlujoFases fases={resumenFases} activa={vista} onElegir={cambiarVista} />

            {cargando ? <p className="gp-vacio" role="status">Cargando tablero…</p> : <div className="tb-vista">{renderVista(vista)}</div>}

            {modalProyecto && (
                <ModalProyecto
                    proyecto={modalProyecto}
                    resumenExpedientes={resumenExpedientes}
                    onCerrar={() => setModalProyecto(null)}
                />
            )}
        </div>
    );
};

export default Dashboard;
