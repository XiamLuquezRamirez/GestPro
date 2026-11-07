import React, { useCallback, useEffect, useState } from 'react';
import { Link, Outlet, useLocation } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faAnglesLeft, faAnglesRight, faEllipsis, faMagnifyingGlass, faRightFromBracket, faTv, faXmark,
} from '@fortawesome/free-solid-svg-icons';
import PaletaComandos from './PaletaComandos';
import { getImageUrl } from '../utils/assetHelper';
import { ICONOS } from '../utils/iconosNavegacion';
import { DESTINOS, GRUPO, destinoActivo, destinosPara, tituloDeRuta } from '../utils/navegacion';
import '../../css/Shell.css';

const GRUPOS = [GRUPO.TRABAJO, GRUPO.ANALISIS, GRUPO.CATALOGOS];
const IDS_BARRA_INFERIOR = ['tablero', 'proyectos', 'eventos'];
const CLAVE_PLEGADO = 'gestpro.rielPlegado';

const leerPlegado = () => {
    try {
        return localStorage.getItem(CLAVE_PLEGADO) === '1';
    } catch {
        return false;
    }
};

const guardarPlegado = (valor) => {
    try {
        localStorage.setItem(CLAVE_PLEGADO, valor ? '1' : '0');
    } catch {
        // Solo es una preferencia: sin almacenamiento, el riel vuelve a su estado inicial.
    }
};

const Enlace = ({ destino, activo, plegado, onClick }) => (
    <Link
        to={destino.ruta}
        className={`gp-enlace${activo ? ' activo' : ''}`}
        aria-current={activo ? 'page' : undefined}
        title={plegado ? destino.titulo : undefined}
        onClick={onClick}
    >
        <FontAwesomeIcon icon={ICONOS[destino.icono]} fixedWidth />
        <span>{destino.titulo}</span>
    </Link>
);

const ListaDestinos = ({ destinos, activo, plegado, onNavegar }) => GRUPOS.map((grupo) => (
    <div className="gp-nav-grupo" key={grupo}>
        <div className="gp-nav-titulo gp-etiqueta">{grupo}</div>
        {destinos.filter((d) => d.grupo === grupo).map((destino) => (
            <Enlace key={destino.id} destino={destino} activo={activo === destino.id} plegado={plegado} onClick={onNavegar} />
        ))}
    </div>
));

const inicialesDe = (nombre) => String(nombre ?? '').split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0].toUpperCase()).join('');

const AppShell = ({ user, onLogout }) => {
    const { pathname, search } = useLocation();
    const [plegado, setPlegado] = useState(leerPlegado);
    const [paletaAbierta, setPaletaAbierta] = useState(false);
    const [masAbierto, setMasAbierto] = useState(false);

    const activo = destinoActivo(pathname, search);
    const abrirPaleta = useCallback(() => setPaletaAbierta(true), []);

    useEffect(() => {
        const alPulsar = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setPaletaAbierta((abierta) => !abierta);
            }
        };
        window.addEventListener('keydown', alPulsar);
        return () => window.removeEventListener('keydown', alPulsar);
    }, []);

    useEffect(() => {
        setMasAbierto(false);
        window.scrollTo(0, 0);
    }, [pathname]);

    const alternarRiel = () => {
        setPlegado((actual) => {
            guardarPlegado(!actual);
            return !actual;
        });
    };

    const enBarraInferior = IDS_BARRA_INFERIOR.includes(activo);
    const destinos = destinosPara(user);

    return (
        <div className={`gp-app${plegado ? ' riel-plegado' : ''}`}>
            {/* Escritorio y tableta: todo a un clic, siempre visible */}
            <aside className="gp-riel" aria-label="Navegación principal">
                <div className="gp-riel-marca">
                    <img src={getImageUrl('images/marca-hexagono.png')} alt="" width="40" height="40" />
                    <span className="gp-riel-nombre">
                        <b>GestPro</b>
                        <small>Gestión de Proyectos</small>
                    </span>
                </div>

                <button type="button" className="gp-buscar" onClick={abrirPaleta} title="Buscar (Ctrl+K)">
                    <FontAwesomeIcon icon={faMagnifyingGlass} fixedWidth />
                    <span>Buscar o ir a…</span>
                    <kbd>Ctrl K</kbd>
                </button>

                <nav className="gp-nav">
                    <ListaDestinos destinos={destinos} activo={activo} plegado={plegado} />
                </nav>

                <div className="gp-riel-pie">
                    <Link to="/sala" className="gp-enlace gp-enlace-sala" title="Modo sala: pantalla completa para TV">
                        <FontAwesomeIcon icon={faTv} fixedWidth />
                        <span>Modo sala</span>
                    </Link>
                    <div className="gp-usuario">
                        <span className="gp-avatar" aria-hidden="true">{inicialesDe(user.name)}</span>
                        <div className="gp-usuario-datos">
                            <strong>{user.name}</strong>
                            <span>{user.rol}</span>
                        </div>
                        <button type="button" className="gp-icono" onClick={onLogout} title="Cerrar sesión" aria-label="Cerrar sesión">
                            <FontAwesomeIcon icon={faRightFromBracket} />
                        </button>
                    </div>
                    <button
                        type="button"
                        className="gp-plegar"
                        onClick={alternarRiel}
                        aria-label={plegado ? 'Expandir menú' : 'Plegar menú'}
                        title={plegado ? 'Expandir menú' : 'Plegar menú'}
                    >
                        <FontAwesomeIcon icon={plegado ? faAnglesRight : faAnglesLeft} />
                    </button>
                </div>
            </aside>

            {/* Celular: barra superior con el título y el buscador */}
            <header className="gp-barra-superior">
                <img src={getImageUrl('images/marca-hexagono.png')} alt="" width="28" height="28" />
                <h1>{tituloDeRuta(pathname, search)}</h1>
                <button type="button" className="gp-icono" onClick={abrirPaleta} aria-label="Buscar">
                    <FontAwesomeIcon icon={faMagnifyingGlass} />
                </button>
            </header>

            <main className="gp-contenido">
                <Outlet />
            </main>

            {/* Celular: lo más usado al alcance del pulgar */}
            <nav className="gp-barra-inferior" aria-label="Navegación rápida">
                {IDS_BARRA_INFERIOR.map((id) => {
                    const destino = DESTINOS.find((d) => d.id === id);
                    return (
                        <Link key={id} to={destino.ruta} className={activo === id ? 'activo' : ''} aria-current={activo === id ? 'page' : undefined}>
                            <FontAwesomeIcon icon={ICONOS[destino.icono]} />
                            <span>{destino.titulo}</span>
                        </Link>
                    );
                })}
                <button type="button" className={!enBarraInferior ? 'activo' : ''} onClick={() => setMasAbierto(true)} aria-haspopup="dialog">
                    <FontAwesomeIcon icon={faEllipsis} />
                    <span>Más</span>
                </button>
            </nav>

            {masAbierto && (
                <div className="gp-hoja-fondo" onClick={() => setMasAbierto(false)}>
                    <div className="gp-hoja" role="dialog" aria-modal="true" aria-label="Todas las secciones" onClick={(e) => e.stopPropagation()}>
                        <div className="gp-hoja-cabecera">
                            <div className="gp-usuario-datos">
                                <strong>{user.name}</strong>
                                <span>{user.rol}</span>
                            </div>
                            <button type="button" className="gp-icono" onClick={() => setMasAbierto(false)} aria-label="Cerrar">
                                <FontAwesomeIcon icon={faXmark} />
                            </button>
                        </div>
                        <ListaDestinos destinos={destinos} activo={activo} onNavegar={() => setMasAbierto(false)} />
                        <div className="gp-hoja-pie">
                            <Link to="/sala" className="gp-boton"><FontAwesomeIcon icon={faTv} /> Modo sala</Link>
                            <button type="button" className="gp-boton" onClick={onLogout}><FontAwesomeIcon icon={faRightFromBracket} /> Cerrar sesión</button>
                        </div>
                    </div>
                </div>
            )}

            {paletaAbierta && <PaletaComandos destinos={destinos} onCerrar={() => setPaletaAbierta(false)} />}
        </div>
    );
};

export default AppShell;
