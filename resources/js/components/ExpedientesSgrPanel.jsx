import React, { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import '../../css/ExpedientesSgrPanel.css';
import { faseLegible } from '../utils/expedienteChecklist';
import { ESTADO, construirFilas, contarPorEstado, filtrarFilas, rutaExpediente } from '../utils/expedientesResumen';
import { filasEnSala } from '../utils/tablero';

const ETIQUETA_ESTADO = {
    [ESTADO.SIN_CONFIGURAR]: 'Sin configurar',
    [ESTADO.SIN_EXPEDIENTE]: 'Sin expediente',
    [ESTADO.BORRADOR]: 'En borrador',
    [ESTADO.RADICADO]: 'Radicado',
};

const fecha = (iso) => (iso ? new Date(iso).toLocaleDateString('es-CO') : '—');

const FilaExpediente = ({ fila }) => {
    const tieneExpediente = fila.expedienteId !== null;
    const medible = fila.estado === ESTADO.BORRADOR || fila.estado === ESTADO.RADICADO;

    return (
        <tr>
            <td data-etiqueta="Proyecto">
                <div className="exs-proyecto">{fila.nombre}</div>
                <div className="exs-secundario">{fila.municipio} · {fila.faseProyecto}</div>
            </td>
            <td data-etiqueta="Estado">
                <span className={`exs-estado exs-estado-${fila.estado}`}>{ETIQUETA_ESTADO[fila.estado]}</span>
            </td>
            <td data-etiqueta="Expediente">
                {tieneExpediente ? (
                    <>
                        <div>#{fila.expedienteId}{medible && ` · Fase ${faseLegible(fila.faseSgr)}`}</div>
                        {fila.cantidad > 1 && <div className="exs-secundario">{fila.cantidad} expedientes en total</div>}
                    </>
                ) : '—'}
            </td>
            <td data-etiqueta="Cumplidos" className="exs-col-avance">
                {medible ? (
                    <>
                        <div className="exs-avance-texto">
                            <span>{fila.cumplidos} de {fila.total}</span>
                            <strong>{fila.porcentaje}%</strong>
                        </div>
                        <span
                            className="tb-pista"
                            role="progressbar"
                            aria-valuenow={fila.porcentaje}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-label={`Requisitos cumplidos de ${fila.nombre}`}
                        >
                            <span style={{ width: `${fila.porcentaje}%`, background: fila.porcentaje === 100 ? 'var(--gp-verde)' : undefined }} />
                        </span>
                    </>
                ) : '—'}
            </td>
            <td data-etiqueta="Pendientes" className="numero">{medible ? fila.pendientes : '—'}</td>
            <td data-etiqueta="Actualizado">{fila.estado === ESTADO.RADICADO ? fecha(fila.fechaRadicado) : fecha(fila.actualizado)}</td>
            <td className="exs-col-accion">
                <Link className="exs-abrir" to={rutaExpediente(fila)}>Abrir</Link>
            </td>
        </tr>
    );
};

const ExpedientesSgrPanel = ({ proyectos, resumenes, error, onReintentar, compacto = false }) => {
    const [estado, setEstado] = useState('todos');
    const [texto, setTexto] = useState('');

    const filas = useMemo(() => construirFilas(proyectos, resumenes ?? []), [proyectos, resumenes]);
    const conteos = useMemo(() => contarPorEstado(filas), [filas]);
    const visibles = useMemo(() => filtrarFilas(filas, { estado, texto }), [filas, estado, texto]);

    if (error) {
        return (
            <div className="exs-panel exs-mensaje" role="alert">
                <p>No se pudo cargar el seguimiento de expedientes. {error}</p>
                <button type="button" className="gp-boton exs-reintentar" onClick={onReintentar}>Reintentar</button>
            </div>
        );
    }

    if (resumenes === null) {
        return <div className="exs-panel exs-mensaje"><p>Cargando expedientes…</p></div>;
    }

    if (filas.length === 0) {
        return <div className="exs-panel exs-mensaje"><p>No hay proyectos registrados.</p></div>;
    }

    // En la sala importan los proyectos en seguimiento, no los que aún no tienen expediente.
    const enSeguimiento = compacto ? visibles.filter((f) => f.expedienteId !== null) : visibles;
    // Se muestran las filas que caben, y el título dice cuántas quedan fuera.
    const filasTabla = compacto ? enSeguimiento.slice(0, filasEnSala(window.innerHeight, window.innerWidth)) : visibles;
    const filasOcultas = enSeguimiento.length - filasTabla.length;

    const opciones = [
        { valor: 'todos', etiqueta: 'Proyectos', cantidad: conteos.todos },
        { valor: ESTADO.SIN_CONFIGURAR, etiqueta: 'Sin configurar', cantidad: conteos[ESTADO.SIN_CONFIGURAR] },
        {
            valor: ESTADO.BORRADOR,
            etiqueta: 'En borrador',
            cantidad: conteos[ESTADO.BORRADOR],
            detalle: `${conteos.pendientesEnBorrador} ${conteos.pendientesEnBorrador === 1 ? 'requisito pendiente' : 'requisitos pendientes'}`,
        },
        { valor: ESTADO.RADICADO, etiqueta: 'Radicados', cantidad: conteos[ESTADO.RADICADO] },
        { valor: ESTADO.SIN_EXPEDIENTE, etiqueta: 'Sin expediente', cantidad: conteos[ESTADO.SIN_EXPEDIENTE] },
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
                        disabled={compacto}
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
                    <h3>
                        Seguimiento por proyecto
                        {filasOcultas > 0 && <span className="exs-secundario"> · mostrando {filasTabla.length} de {enSeguimiento.length}</span>}
                    </h3>
                    {!compacto && <input
                        type="search"
                        className="exs-buscar"
                        placeholder="Buscar proyecto o municipio"
                        aria-label="Buscar proyecto o municipio"
                        value={texto}
                        onChange={(e) => setTexto(e.target.value)}
                    />}
                </div>

                {filasTabla.length === 0 ? (
                    <p className="exs-sin-resultados">
                        {compacto ? 'Aún no hay proyectos con expediente en seguimiento.' : 'Ningún proyecto coincide con el filtro.'}
                    </p>
                ) : (
                    <>
                        <table className="tb-tabla tb-tabla-cartas exs-tabla">
                            <thead>
                                <tr>
                                    <th>Proyecto</th>
                                    <th>Estado</th>
                                    <th>Expediente</th>
                                    <th>Requisitos cumplidos</th>
                                    <th className="numero">Pendientes</th>
                                    <th>Actualizado</th>
                                    <th aria-label="Acciones" />
                                </tr>
                            </thead>
                            <tbody>
                                {filasTabla.map((fila) => <FilaExpediente key={fila.proyectoId} fila={fila} />)}
                            </tbody>
                        </table>
                    </>
                )}
            </div>
        </div>
    );
};

export default ExpedientesSgrPanel;
