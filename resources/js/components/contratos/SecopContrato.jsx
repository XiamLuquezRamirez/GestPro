import React, { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Link } from 'react-router-dom';
import { faArrowUpRightFromSquare, faMagnifyingGlass, faXmark } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../../axios';
import { camposMarcados, filaResultadoSecop, mensajeSecop } from '../../utils/secop';
import useDialogoModal from '../useDialogoModal';
import '../../../css/Plantillas.css';
import '../../../css/Contratacion.css';

/**
 * Diálogo modal de SECOP II, en `document.body`: así queda encima del editor aunque este viva en
 * otro diálogo. Escape, la "×" y un clic en el fondo llaman a `onCerrar`. Escape y Tab no llegan al
 * diálogo de afuera (que cerraría el editor o movería el foco fuera de este). Lo usan también los
 * diálogos de cierre del contrato: `etiqueta` es el rótulo sobre el título y `estrecho`, para un
 * formulario corto.
 */
export const DialogoSecop = ({ titulo, etiqueta = 'SECOP II', estrecho = false, onCerrar, children }) => {
    const caja = useRef(null);
    const idTitulo = useId();
    const alPulsar = useDialogoModal(caja, onCerrar);
    const pulsar = (e) => {
        alPulsar(e);
        if (e.key === 'Escape' || e.key === 'Tab') e.stopPropagation();
    };

    return createPortal(
        <div className="ct-dialogo-fondo" onMouseDown={(e) => { if (e.target === e.currentTarget) onCerrar(); }}>
            <div ref={caja} className={`ct-dialogo${estrecho ? ' ct-dialogo-estrecho' : ''}`} role="dialog" aria-modal="true" aria-labelledby={idTitulo} tabIndex={-1} onKeyDown={pulsar}>
                <header className="ct-dialogo-cabecera">
                    <div>
                        <span className="gp-etiqueta">{etiqueta}</span>
                        <h2 id={idTitulo}>{titulo}</h2>
                    </div>
                    <button type="button" className="gp-icono" onClick={onCerrar} aria-label="Cerrar">
                        <FontAwesomeIcon icon={faXmark} />
                    </button>
                </header>
                <div className="ct-dialogo-cuerpo">{children}</div>
            </div>
        </div>,
        document.body,
    );
};

/**
 * Resultados de SECOP II (elementos de `resultados`): número, entidad, contratista, valor, fechas
 * y estado en SECOP II. Uno ya registrado enlaza a su contrato (con `estadoEnlace` como estado de
 * navegación, si viene; con `enlaceEnOtraPestana`, en otra pestaña, para no dejar el formulario
 * que se está llenando); los demás llevan el botón `textoAccion`, que llama a `onElegir(resultado)`.
 */
export const ResultadosSecop = ({ resultados, textoAccion = 'Usar este', onElegir, estadoEnlace, enlaceEnOtraPestana = false }) => (
    <ul className="ct-secop-resultados">
        {resultados.map((resultado) => {
            const fila = filaResultadoSecop(resultado);
            return (
                <li key={fila.clave} className="ct-secop-resultado">
                    <div className="ct-secop-resultado-cabecera">
                        <div>
                            <strong>{fila.numero}</strong> <span className="ct-clave">{fila.idSecop}</span>
                        </div>
                        <span className="ct-estado ct-estado-neutro" title="Estado en SECOP II">{fila.estado}</span>
                    </div>
                    <dl className="ct-secop-datos">
                        <div><dt>Entidad</dt><dd>{fila.entidad}</dd></div>
                        <div><dt>Contratista</dt><dd>{fila.contratista}</dd></div>
                        <div><dt>Valor</dt><dd className="ct-valor">{fila.valor}</dd></div>
                        <div><dt>Suscripción</dt><dd>{fila.suscripcion}</dd></div>
                        <div><dt>Inicio</dt><dd>{fila.inicio}</dd></div>
                        <div><dt>Fin</dt><dd>{fila.fin}</dd></div>
                    </dl>
                    <div className="ct-secop-resultado-accion">
                        {fila.registrado ? (
                            <span>
                                Ya registrado:{' '}
                                {enlaceEnOtraPestana ? (
                                    <Link to={fila.registrado.ruta} target="_blank" rel="noopener noreferrer" className="ct-enlace">
                                        contrato {fila.registrado.numero} <FontAwesomeIcon icon={faArrowUpRightFromSquare} aria-hidden="true" />
                                        <span className="ct-oculto"> (se abre en otra pestaña)</span>
                                    </Link>
                                ) : (
                                    <Link to={fila.registrado.ruta} state={estadoEnlace} className="ct-enlace">contrato {fila.registrado.numero}</Link>
                                )}
                            </span>
                        ) : (
                            <button type="button" className="gp-boton gp-boton-primario" onClick={() => onElegir(resultado)} aria-label={`${textoAccion}: contrato ${fila.numero}`}>
                                {textoAccion}
                            </button>
                        )}
                    </div>
                </li>
            );
        })}
    </ul>
);

const SIN_TEXTO = 'Escribe el número, el ID o el enlace del contrato.';

/**
 * "Buscar en SECOP II": el número, el ID (CO1.PCCNTR…) o el enlace del contrato y, opcional, el
 * NIT de la entidad (GET /secop/contratos). Solo la última búsqueda llega a la lista; al cerrar,
 * una respuesta atrasada se descarta. "Usar este" llama a `onElegir(resultado)`.
 */
export const BuscarSecop = ({ onElegir, onCerrar }) => {
    const [texto, setTexto] = useState('');
    const [nit, setNit] = useState('');
    const [resultados, setResultados] = useState(null);
    const [buscando, setBuscando] = useState(false);
    const [error, setError] = useState(null);
    const turno = useRef(0);
    const idAyuda = useId();
    const idError = useId();

    // Al cerrar, cualquier respuesta pendiente queda atrasada.
    useEffect(() => () => { turno.current += 1; }, []);

    const buscar = async (e) => {
        e.preventDefault();
        const q = texto.trim();
        if (!q) {
            setError(SIN_TEXTO);
            return;
        }
        const numero = ++turno.current;
        setBuscando(true);
        setError(null);
        try {
            const { data } = await axios.get('/secop/contratos', { params: { q, ...(nit.trim() ? { nit: nit.trim() } : {}) } });
            if (numero !== turno.current) return;
            setResultados(Array.isArray(data?.resultados) ? data.resultados : []);
        } catch (falla) {
            if (numero !== turno.current) return;
            setResultados(null);
            setError(mensajeSecop(falla));
        } finally {
            if (numero === turno.current) setBuscando(false);
        }
    };

    return (
        <DialogoSecop titulo="Buscar en SECOP II" onCerrar={onCerrar}>
            <form className="pl-formulario ct-secop-busqueda" onSubmit={buscar} noValidate aria-label="Buscar el contrato en SECOP II">
                <label className="pl-campo pl-campo-ancho">
                    <span>Número, ID o enlace del contrato</span>
                    <input
                        type="text"
                        value={texto}
                        onChange={(e) => { setTexto(e.target.value); setError(null); }}
                        maxLength={500}
                        placeholder="CO1.PCCNTR.1234567 o el número del contrato"
                        aria-describedby={error ? `${idAyuda} ${idError}` : idAyuda}
                        aria-invalid={error === SIN_TEXTO}
                    />
                    <small id={idAyuda} className="contrato-nota">Pega el ID o el enlace del contrato en SECOP II, o escribe su número.</small>
                </label>
                <label className="pl-campo">
                    <span>NIT de la entidad (opcional)</span>
                    <input type="text" value={nit} onChange={(e) => setNit(e.target.value)} maxLength={30} inputMode="numeric" />
                </label>
                <div className="pl-formulario-acciones">
                    <button type="submit" className="gp-boton gp-boton-primario" disabled={buscando}>
                        <FontAwesomeIcon icon={faMagnifyingGlass} /> {buscando ? 'Buscando…' : 'Buscar'}
                    </button>
                </div>
            </form>
            {error && <p id={idError} className="pl-error" role="alert">{error}</p>}
            <div aria-live="polite">
                {resultados?.length === 0 && <p className="gp-vacio">SECOP II no tiene contratos con esos datos. Si no está en SECOP II, llena el formulario a mano.</p>}
                {resultados?.length > 0 && <ResultadosSecop resultados={resultados} onElegir={onElegir} enlaceEnOtraPestana />}
            </div>
        </DialogoSecop>
    );
};

/**
 * Las diferencias entre el formulario y SECOP II (diferenciasSecop), con una casilla por campo
 * (marcadas las de `marcado`) y los `avisos` del resultado. "Copiar al formulario" llama a
 * `onCopiar(campos elegidos)`.
 */
export const DiferenciasSecop = ({ titulo, diferencias, avisos = [], onCopiar, onCerrar }) => {
    const [elegidos, setElegidos] = useState(() => camposMarcados(diferencias));
    const alternar = (campo) => setElegidos((prev) => (prev.includes(campo) ? prev.filter((c) => c !== campo) : [...prev, campo]));

    return (
        <DialogoSecop titulo={titulo} onCerrar={onCerrar}>
            <p className="contrato-nota">Marca lo que quieres copiar al formulario. Nada se guarda hasta que guardes el contrato.</p>
            {avisos.length > 0 && (
                <ul className="ct-secop-avisos">
                    {avisos.map((aviso) => <li key={aviso}>{aviso}</li>)}
                </ul>
            )}
            <div className="ct-secop-tabla">
                <table>
                    <thead>
                        <tr>
                            <th scope="col"><span className="ct-oculto">Copiar</span></th>
                            <th scope="col">Campo</th>
                            <th scope="col">En GestPro</th>
                            <th scope="col">En SECOP II</th>
                        </tr>
                    </thead>
                    <tbody>
                        {diferencias.map((d) => (
                            <tr key={d.campo}>
                                <td>
                                    <input
                                        type="checkbox"
                                        checked={elegidos.includes(d.campo)}
                                        onChange={() => alternar(d.campo)}
                                        aria-label={`Copiar ${d.etiqueta}`}
                                        aria-describedby={d.nota ? `secop-nota-${d.campo}` : undefined}
                                    />
                                </td>
                                <th scope="row">
                                    {d.etiqueta}
                                    {d.nota && <small id={`secop-nota-${d.campo}`} className="ct-secop-nota">{d.nota}</small>}
                                </th>
                                <td>{d.actual}</td>
                                <td>{d.secop}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="pl-formulario-acciones ct-secop-acciones">
                <button type="button" className="gp-boton" onClick={onCerrar}>Cancelar</button>
                <button type="button" className="gp-boton gp-boton-primario" onClick={() => onCopiar(elegidos)}>Copiar al formulario</button>
            </div>
        </DialogoSecop>
    );
};
