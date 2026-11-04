import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import axios from 'axios';
import '../../css/ExpedientesSgrPanel.css';
import { faseLegible, mensajeError } from '../utils/expedienteChecklist';
import { ESTADO, construirFilas, contarPorEstado, filtrarFilas, rutaExpediente } from '../utils/expedientesResumen';

const ETIQUETA_ESTADO = {
    [ESTADO.SIN_EXPEDIENTE]: 'Sin expediente',
    [ESTADO.BORRADOR]: 'En borrador',
    [ESTADO.RADICADO]: 'Radicado',
};

const fecha = (iso) => (iso ? new Date(iso).toLocaleDateString('es-CO') : '—');

const FilaExpediente = ({ fila }) => {
    const tieneExpediente = fila.expedienteId !== null;

    return (
        <tr>
            <td>
                <div className="exs-proyecto">{fila.nombre}</div>
                <div className="exs-secundario">{fila.municipio} · {fila.faseProyecto}</div>
            </td>
            <td>
                <span className={`exs-estado exs-estado-${fila.estado}`}>{ETIQUETA_ESTADO[fila.estado]}</span>
            </td>
            <td>
                {tieneExpediente ? (
                    <>
                        <div>#{fila.expedienteId} · Fase {faseLegible(fila.faseSgr)}</div>
                        {fila.cantidad > 1 && <div className="exs-secundario">{fila.cantidad} expedientes en total</div>}
                    </>
                ) : '—'}
            </td>
            <td className="exs-col-avance">
                {tieneExpediente ? (
                    <>
                        <div className="exs-avance-texto">
                            <span>{fila.cumplidos} de {fila.total}</span>
                            <strong>{fila.porcentaje}%</strong>
                        </div>
                        <div
                            className="exs-pista"
                            role="progressbar"
                            aria-valuenow={fila.porcentaje}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-label={`Avance de ${fila.nombre}`}
                        >
                            <div
                                className={`exs-relleno${fila.porcentaje === 100 ? ' completo' : ''}`}
                                style={{ width: `${fila.porcentaje}%` }}
                            />
                        </div>
                    </>
                ) : '—'}
            </td>
            <td className="exs-col-numero">{tieneExpediente ? fila.pendientes : '—'}</td>
            <td>{fila.estado === ESTADO.RADICADO ? fecha(fila.fechaRadicado) : fecha(fila.actualizado)}</td>
            <td className="exs-col-accion">
                <Link className="exs-abrir" to={rutaExpediente(fila)}>Abrir</Link>
            </td>
        </tr>
    );
};

const ExpedientesSgrPanel = ({ proyectos }) => {
    const [resumenes, setResumenes] = useState(null);
    const [error, setError] = useState(null);
    const [estado, setEstado] = useState('todos');
    const [texto, setTexto] = useState('');

    const cargar = useCallback(async () => {
        setError(null);
        try {
            const { data } = await axios.get('/expedientes');
            setResumenes(data);
        } catch (e) {
            setError(mensajeError(e));
        }
    }, []);

    useEffect(() => {
        cargar();
    }, [cargar]);

    const filas = useMemo(() => construirFilas(proyectos, resumenes ?? []), [proyectos, resumenes]);
    const conteos = useMemo(() => contarPorEstado(filas), [filas]);
    const visibles = useMemo(() => filtrarFilas(filas, { estado, texto }), [filas, estado, texto]);

    if (error) {
        return (
            <div className="exs-panel exs-mensaje" role="alert">
                <p>No se pudo cargar el seguimiento de expedientes. {error}</p>
                <button type="button" className="exs-reintentar" onClick={cargar}>Reintentar</button>
            </div>
        );
    }

    if (resumenes === null) {
        return <div className="exs-panel exs-mensaje"><p>Cargando expedientes…</p></div>;
    }

    if (filas.length === 0) {
        return <div className="exs-panel exs-mensaje"><p>No hay proyectos registrados.</p></div>;
    }

    const opciones = [
        { valor: 'todos', etiqueta: 'Proyectos', cantidad: conteos.todos },
        { valor: ESTADO.SIN_EXPEDIENTE, etiqueta: 'Sin expediente', cantidad: conteos[ESTADO.SIN_EXPEDIENTE] },
        {
            valor: ESTADO.BORRADOR,
            etiqueta: 'En borrador',
            cantidad: conteos[ESTADO.BORRADOR],
            detalle: `${conteos.pendientesEnBorrador} ${conteos.pendientesEnBorrador === 1 ? 'requisito pendiente' : 'requisitos pendientes'}`,
        },
        { valor: ESTADO.RADICADO, etiqueta: 'Radicados', cantidad: conteos[ESTADO.RADICADO] },
    ];

    return (
        <div className="exs-panel">
            <div className="exs-resumen">
                {opciones.map((o) => (
                    <button
                        key={o.valor}
                        type="button"
                        className={`exs-tarjeta${estado === o.valor ? ' activa' : ''}`}
                        aria-pressed={estado === o.valor}
                        onClick={() => setEstado(o.valor)}
                    >
                        <span className="exs-tarjeta-etiqueta">{o.etiqueta}</span>
                        <span className="exs-tarjeta-valor">{o.cantidad}</span>
                        {o.detalle && <span className="exs-secundario">{o.detalle}</span>}
                    </button>
                ))}
            </div>

            <div className="exs-tabla-marco">
                <div className="exs-tabla-encabezado">
                    <h3>Seguimiento por proyecto</h3>
                    <input
                        type="search"
                        className="exs-buscar"
                        placeholder="Buscar proyecto o municipio"
                        aria-label="Buscar proyecto o municipio"
                        value={texto}
                        onChange={(e) => setTexto(e.target.value)}
                    />
                </div>

                {visibles.length === 0 ? (
                    <p className="exs-sin-resultados">Ningún proyecto coincide con el filtro.</p>
                ) : (
                    <div className="exs-tabla-scroll">
                        <table className="exs-tabla">
                            <thead>
                                <tr>
                                    <th>Proyecto</th>
                                    <th>Estado</th>
                                    <th>Expediente</th>
                                    <th>Avance</th>
                                    <th className="exs-col-numero">Pendientes</th>
                                    <th>Actualizado</th>
                                    <th aria-label="Acciones" />
                                </tr>
                            </thead>
                            <tbody>
                                {visibles.map((fila) => <FilaExpediente key={fila.proyectoId} fila={fila} />)}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
};

export default ExpedientesSgrPanel;
