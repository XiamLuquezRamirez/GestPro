import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faBackwardStep, faExpand, faForwardStep, faPause, faPlay, faRightFromBracket,
} from '@fortawesome/free-solid-svg-icons';
import FlujoFases from './FlujoFases';
import KpiStrip from './KpiStrip';
import { getImageUrl } from '../utils/assetHelper';
import { VISTA, escalaSala, secuenciaSala, siguienteVista } from '../utils/tablero';

const SEGUNDOS_POR_VISTA = 30;
const MINUTOS_ENTRE_RECARGAS = 5;
// La sesión dura 60 minutos: se renueva antes para que la pantalla no se quede sin datos.
const MINUTOS_ENTRE_RENOVACIONES = 30;
const SEGUNDOS_CONTROLES_VISIBLES = 4;

const TITULOS_ANALISIS = { [VISTA.CONTRATOS]: 'Contratos', [VISTA.EXPEDIENTES]: 'Expedientes' };

const horaCorta = (fecha) => fecha.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });

/**
 * Pantalla completa para TV o sala de reuniones: recorre las vistas sola, recarga los
 * datos y escala en proporción hasta 4K. Abrir /sala basta para dejarla funcionando.
 */
const ModoSala = ({ resumenFases, proyectos, ultimaCarga, errorCarga, recargar, renderVista }) => {
    const navigate = useNavigate();
    const secuencia = useMemo(() => secuenciaSala(resumenFases), [resumenFases]);
    const [vista, setVista] = useState(null);
    const [pausada, setPausada] = useState(false);
    const [ahora, setAhora] = useState(() => new Date());
    const [escala, setEscala] = useState(() => escalaSala(window.innerWidth));
    const [controlesVisibles, setControlesVisibles] = useState(true);

    useEffect(() => {
        if (secuencia.length > 0 && !secuencia.includes(vista)) setVista(secuencia[0]);
    }, [secuencia, vista]);

    const avanzar = useCallback((pasos = 1) => {
        setVista((actual) => {
            if (secuencia.length === 0) return actual;
            const indice = Math.max(secuencia.indexOf(actual), 0);
            return secuencia[(indice + pasos + secuencia.length) % secuencia.length];
        });
    }, [secuencia]);

    useEffect(() => {
        if (pausada || !vista) return undefined;
        const temporizador = setTimeout(() => setVista(siguienteVista(secuencia, vista)), SEGUNDOS_POR_VISTA * 1000);
        return () => clearTimeout(temporizador);
    }, [vista, pausada, secuencia]);

    useEffect(() => {
        const reloj = setInterval(() => setAhora(new Date()), 15000);
        const recarga = setInterval(recargar, MINUTOS_ENTRE_RECARGAS * 60000);
        const renovacion = setInterval(async () => {
            try {
                const { data } = await axios.post('/refrescar-token');
                localStorage.setItem('token', data.token);
            } catch {
                // Si falla, la siguiente recarga mostrará el aviso de sesión vencida.
            }
        }, MINUTOS_ENTRE_RENOVACIONES * 60000);

        return () => {
            clearInterval(reloj);
            clearInterval(recarga);
            clearInterval(renovacion);
        };
    }, [recargar]);

    // Fija el tamaño base del texto: aquí el crecimiento lo da el zoom. Si no, se escalaría dos veces.
    useEffect(() => {
        document.documentElement.classList.add('en-sala');
        return () => document.documentElement.classList.remove('en-sala');
    }, []);

    useEffect(() => {
        const alRedimensionar = () => setEscala(escalaSala(window.innerWidth));
        window.addEventListener('resize', alRedimensionar);
        return () => window.removeEventListener('resize', alRedimensionar);
    }, []);

    const pantallaCompleta = () => {
        if (document.fullscreenElement) document.exitFullscreen();
        else document.documentElement.requestFullscreen?.();
    };

    const salir = useCallback(() => {
        if (document.fullscreenElement) document.exitFullscreen();
        navigate('/dashboard');
    }, [navigate]);

    useEffect(() => {
        const alPulsar = (e) => {
            if (e.key === 'Escape') salir();
            if (e.key === 'ArrowRight') avanzar(1);
            if (e.key === 'ArrowLeft') avanzar(-1);
            if (e.key === ' ') {
                e.preventDefault();
                setPausada((p) => !p);
            }
            if (e.key.toLowerCase() === 'f') pantallaCompleta();
        };
        window.addEventListener('keydown', alPulsar);
        return () => window.removeEventListener('keydown', alPulsar);
    }, [avanzar, salir]);

    // Los controles y el cursor desaparecen solos: en una pared no aportan nada.
    useEffect(() => {
        let temporizador = setTimeout(() => setControlesVisibles(false), SEGUNDOS_CONTROLES_VISIBLES * 1000);
        const alMover = () => {
            setControlesVisibles(true);
            clearTimeout(temporizador);
            temporizador = setTimeout(() => setControlesVisibles(false), SEGUNDOS_CONTROLES_VISIBLES * 1000);
        };
        window.addEventListener('mousemove', alMover);
        window.addEventListener('touchstart', alMover);
        return () => {
            clearTimeout(temporizador);
            window.removeEventListener('mousemove', alMover);
            window.removeEventListener('touchstart', alMover);
        };
    }, []);

    const faseActual = resumenFases.find((f) => f.slug === vista);
    const titulo = faseActual ? faseActual.nombre : TITULOS_ANALISIS[vista] ?? '';

    return (
        <div
            className={`sala${controlesVisibles ? '' : ' sin-controles'}`}
            style={{ '--escala': escala, zoom: escala }}
        >
            <header className="sala-cabecera">
                <div className="sala-marca">
                    <img src={getImageUrl('images/marca-hexagono.png')} alt="" />
                    <div>
                        <span className="gp-etiqueta">GestPro · Sala de situación</span>
                        <h1>{titulo}</h1>
                    </div>
                </div>

                <ol className="sala-puntos" aria-label="Vistas de la rotación">
                    {secuencia.map((id) => <li key={id} className={id === vista ? 'activo' : ''} />)}
                </ol>

                <div className="sala-controles" aria-label="Controles">
                    <button type="button" onClick={() => avanzar(-1)} aria-label="Vista anterior"><FontAwesomeIcon icon={faBackwardStep} /></button>
                    <button type="button" onClick={() => setPausada((p) => !p)} aria-label={pausada ? 'Reanudar' : 'Pausar'}><FontAwesomeIcon icon={pausada ? faPlay : faPause} /></button>
                    <button type="button" onClick={() => avanzar(1)} aria-label="Vista siguiente"><FontAwesomeIcon icon={faForwardStep} /></button>
                    <button type="button" onClick={pantallaCompleta} aria-label="Pantalla completa"><FontAwesomeIcon icon={faExpand} /></button>
                    <button type="button" onClick={salir} aria-label="Salir del modo sala"><FontAwesomeIcon icon={faRightFromBracket} /></button>
                </div>

                <div className="sala-reloj">
                    <strong>{horaCorta(ahora)}</strong>
                    <span className={errorCarga ? 'sala-sin-datos' : ''}>
                        {errorCarga
                            ? (errorCarga.sesionVencida ? 'Sesión vencida: inicia sesión de nuevo' : `Sin conexión · datos de ${ultimaCarga ? horaCorta(ultimaCarga) : '—'}`)
                            : `Datos de ${ultimaCarga ? horaCorta(ultimaCarga) : '—'}`}
                    </span>
                </div>
            </header>

            <div className="sala-progreso" aria-hidden="true">
                <span
                    key={vista}
                    className={pausada ? 'pausada' : ''}
                    style={{ animationDuration: `${SEGUNDOS_POR_VISTA}s` }}
                />
            </div>

            <KpiStrip proyectos={proyectos} />
            <FlujoFases fases={resumenFases} activa={vista} onElegir={setVista} />

            <div className="sala-vista">{vista && renderVista(vista, true)}</div>
        </div>
    );
};

export default ModoSala;
