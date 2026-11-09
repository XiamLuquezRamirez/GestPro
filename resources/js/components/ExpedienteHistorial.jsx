import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import { faDownload, faRotateRight } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import {
    autorHistorial,
    describirEvento,
    fechaHoraHistorial,
    hayMasHistorial,
    mensajeError,
    textoRequisitoHistorial,
    unirPaginaHistorial,
} from '../utils/expedienteChecklist';

/**
 * Historial completo del expediente (sus movimientos y los de sus requisitos), del más reciente
 * al más antiguo. Se pide desde la página 1 al abrirse y cada vez que cambia `revision`, que el
 * padre sube con cada cambio que el servidor ya registró; "Cargar más" pide la página siguiente
 * cuando llegó la anterior.
 */
const ExpedienteHistorial = ({ id, expedienteId, revision, onDescargar }) => {
    // La última respuesta, con las entradas de todas las páginas cargadas, y de qué expediente es.
    const [historial, setHistorial] = useState(null);
    // Página que se está pidiendo, o null.
    const [cargando, setCargando] = useState(null);
    // { mensaje, pagina }: la página que falló, para reintentarla.
    const [error, setError] = useState(null);
    const idTitulo = useId();

    // Lo sube cada carga desde la página 1, y también desmontarse: lo que responda una carga
    // anterior, sea de la página que sea o de otro expediente, llega tarde y se descarta.
    const carga = useRef(0);

    const pedir = useCallback(async (pagina, numero) => {
        setCargando(pagina);
        setError(null);
        try {
            const { data } = await axios.get(`/expedientes/${expedienteId}/historial`, { params: { pagina } });
            if (numero !== carga.current) return;
            setHistorial((prev) => ({
                ...data,
                expedienteId,
                entradas: pagina === 1 ? data.entradas : unirPaginaHistorial(prev.entradas, data.entradas),
            }));
        } catch (e) {
            if (numero === carga.current) setError({ mensaje: mensajeError(e), pagina });
        } finally {
            if (numero === carga.current) setCargando(null);
        }
    }, [expedienteId]);

    const recargar = useCallback(() => pedir(1, ++carga.current), [pedir]);

    useEffect(() => {
        recargar();
        return () => { carga.current += 1; };
    }, [recargar, revision]);

    // Mientras llega el primero, lo cargado de otro expediente no se muestra.
    const datos = historial?.expedienteId === expedienteId ? historial : null;
    // Las páginas van en orden: la siguiente solo se pide con la anterior ya puesta.
    const cargarMas = () => pedir(datos.pagina + 1, carga.current);
    const reintentar = () => (error?.pagina === 1 ? recargar() : cargarMas());

    const botonReintentar = (
        <button type="button" className="exv-btn" onClick={reintentar} disabled={cargando !== null}>
            <FontAwesomeIcon icon={faRotateRight} /> Reintentar
        </button>
    );

    let cuerpo;
    if (!datos) {
        cuerpo = error ? (
            <div className="exv-hist-estado" role="alert">
                <p>No se pudo cargar el historial: {error.mensaje}</p>
                {botonReintentar}
            </div>
        ) : (
            <p className="exv-hist-estado" role="status">Cargando historial…</p>
        );
    } else if (datos.entradas.length === 0) {
        cuerpo = <p className="exv-hist-estado">Aún no hay movimientos en este expediente.</p>;
    } else {
        cuerpo = (
            <ol className="exv-hist-lista">
                {datos.entradas.map((entrada) => {
                    const requisito = textoRequisitoHistorial(entrada);
                    return (
                        <li key={entrada.id} className="exv-hist-entrada">
                            <time className="exv-hist-fecha" dateTime={entrada.fecha ?? undefined}>
                                {fechaHoraHistorial(entrada.fecha)}
                            </time>
                            <div className="exv-hist-cuerpo">
                                <p className="exv-hist-texto">
                                    <b>{autorHistorial(entrada)}</b> · {describirEvento(entrada)}
                                </p>
                                {requisito && <p className="exv-hist-requisito">{requisito}</p>}
                            </div>
                            {entrada.soporte && (
                                <button
                                    type="button"
                                    className="exv-mini"
                                    aria-label={`Descargar ${entrada.soporte.nombre}`}
                                    onClick={() => onDescargar(entrada)}
                                >
                                    <FontAwesomeIcon icon={faDownload} /> {entrada.soporte.nombre}
                                </button>
                            )}
                        </li>
                    );
                })}
            </ol>
        );
    }

    return (
        <section id={id} className="exv-hist exv-no-imprimir" aria-labelledby={idTitulo} aria-busy={cargando !== null}>
            <div className="exv-hist-cabeza">
                <h3 id={idTitulo}>Historial del expediente</h3>
                {datos && (
                    <span role="status">
                        {cargando === 1 ? 'Actualizando…' : `${datos.total} ${datos.total === 1 ? 'movimiento' : 'movimientos'}`}
                    </span>
                )}
            </div>
            {cuerpo}
            {datos && error && (
                <div className="exv-hist-pie" role="alert">
                    <span>
                        {error.pagina === 1 ? 'No se pudo poner al día el historial' : 'No se pudo cargar la página siguiente'}: {error.mensaje}
                    </span>
                    {botonReintentar}
                </div>
            )}
            {datos && !error && hayMasHistorial(datos) && (
                <div className="exv-hist-pie">
                    <button type="button" className="exv-btn" onClick={cargarMas} disabled={cargando !== null}>
                        {cargando !== null ? 'Cargando…' : 'Cargar más'}
                    </button>
                </div>
            )}
        </section>
    );
};

export default ExpedienteHistorial;
