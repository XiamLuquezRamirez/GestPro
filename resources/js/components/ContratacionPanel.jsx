import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faClipboardCheck } from '@fortawesome/free-solid-svg-icons';
import axios from '../axios';
import '../../css/ExpedientesSgrPanel.css';
import '../../css/Contratacion.css';
import ChipsAvisosProceso from './ChipsAvisosProceso';
import { NumeroProceso } from './FormulariosProceso';
import { mensajeError } from '../utils/expedienteChecklist';
import {
    alternarFiltro,
    consultaConFiltros,
    etapasActuales,
    filaProceso,
    filtrosDeConsulta,
    filtrosFueraDeOpciones,
    FILTROS_VACIOS,
    hayFiltros,
    opcionesFiltros,
    parametrosApi,
    tarjetasEstado,
    tarjetasSemaforo,
    textoListaVacia,
} from '../utils/contratacionPanel';

// Los selects de filtro, en el orden en que se muestran: clave, texto de "todos" y nombre accesible.
const SELECTS = [
    ['modalidad_id', 'Todas las modalidades', 'Filtrar por modalidad'],
    ['plataforma', 'Todas las plataformas', 'Filtrar por plataforma'],
    ['fuente_id', 'Todas las fuentes', 'Filtrar por fuente de financiación'],
    ['responsable_id', 'Todos los responsables', 'Filtrar por responsable'],
    ['estado', 'Todos los estados', 'Filtrar por estado del proceso'],
    ['semaforo', 'Todos los semáforos', 'Filtrar por semáforo'],
];

const Semaforo = ({ semaforo }) => (semaforo ? (
    <span className={`ct-estado ct-estado-${semaforo.tono} ctp-semaforo ctp-semaforo-${semaforo.valor}`}>
        <span className="ctp-punto" aria-hidden="true" />{semaforo.texto}
    </span>
) : '—');

const FilaProceso = ({ proceso }) => {
    const fila = filaProceso(proceso);
    return (
        <tr>
            <td data-etiqueta="Número"><NumeroProceso proceso={proceso} /></td>
            <td data-etiqueta="Objeto"><div className="ctp-objeto" title={fila.objeto}>{fila.objeto}</div></td>
            <td data-etiqueta="Proyecto">
                {fila.proyecto ? <Link to={fila.proyecto.ruta} className="ct-enlace">{fila.proyecto.nombre}</Link> : 'Sin proyecto'}
            </td>
            <td data-etiqueta="Modalidad">{fila.modalidad}</td>
            <td data-etiqueta="Monto" className="numero">{fila.monto}</td>
            <td data-etiqueta="Estado"><span className={`ct-estado ct-estado-${fila.estado.tono}`}>{fila.estado.texto}</span></td>
            <td data-etiqueta="Etapa actual">
                <div>{fila.etapa.texto}</div>
                {fila.etapa.revision && <span className={`ct-estado ct-estado-${fila.etapa.revision.tono}`}>{fila.etapa.revision.texto}</span>}
            </td>
            <td data-etiqueta="Avance" className="exs-col-avance">
                {fila.avance === null ? '—' : (
                    <>
                        <div className="exs-avance-texto"><span>Expediente</span><strong>{fila.avance}%</strong></div>
                        <span
                            className="tb-pista"
                            role="progressbar"
                            aria-valuenow={fila.avance}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-label={`Avance del expediente de ${fila.numero.texto === '—' ? fila.objeto : fila.numero.texto}`}
                        >
                            <span style={{ width: `${fila.avance}%`, background: fila.avance === 100 ? 'var(--gp-verde)' : undefined }} />
                        </span>
                    </>
                )}
            </td>
            <td data-etiqueta="Cierre" className="numero">{fila.cierre}</td>
            <td data-etiqueta="Avisos" className="ctp-col-avisos"><ChipsAvisosProceso avisos={fila.avisos} /></td>
            <td data-etiqueta="Semáforo"><Semaforo semaforo={fila.semaforo} /></td>
            <td className="exs-col-accion">
                <Link className="exs-abrir" to={fila.rutaChecklist}>
                    <FontAwesomeIcon icon={faClipboardCheck} aria-hidden="true" /> Checklist
                </Link>
            </td>
        </tr>
    );
};

/**
 * Vista "Contratación" del tablero: todos los procesos de contratación con su semáforo, su etapa
 * actual (la primera sin aprobar) y sus avisos, con tarjetas por semáforo y por estado y filtros
 * que se guardan en la URL junto a la vista. El servidor filtra y cuenta (GET /tablero/contratacion).
 */
const ContratacionPanel = () => {
    const [searchParams, setSearchParams] = useSearchParams();
    const [datos, setDatos] = useState(null);
    const [error, setError] = useState(null);
    const [cargando, setCargando] = useState(true);
    const turno = useRef(0);

    const consulta = searchParams.toString();
    const filtros = useMemo(() => filtrosDeConsulta(new URLSearchParams(consulta)), [consulta]);
    const claveFiltros = JSON.stringify(parametrosApi(filtros));

    const aplicar = useCallback((siguientes) => {
        setSearchParams((previos) => consultaConFiltros(previos, siguientes), { replace: true });
    }, [setSearchParams]);

    // Solo vale la última respuesta: si se cambia de filtro mientras carga, la anterior se descarta.
    const cargar = useCallback(async () => {
        const numero = ++turno.current;
        setCargando(true);
        setError(null);
        try {
            const { data } = await axios.get('/tablero/contratacion', { params: JSON.parse(claveFiltros) });
            if (numero !== turno.current) return;
            setDatos(data);
        } catch (e) {
            if (numero === turno.current) setError(mensajeError(e));
        } finally {
            if (numero === turno.current) setCargando(false);
        }
    }, [claveFiltros]);

    useEffect(() => {
        cargar();
        return () => { turno.current += 1; };
    }, [cargar]);

    // Un filtro cuyo valor ya no tiene ningún proceso (una URL vieja) se quita: no queda oculto.
    useEffect(() => {
        const fuera = filtrosFueraDeOpciones(filtros, datos?.opciones);
        if (fuera.length === 0) return;
        aplicar(Object.fromEntries(Object.entries(filtros).map(([clave, valor]) => [clave, fuera.includes(clave) ? '' : valor])));
    }, [datos, filtros, aplicar]);

    const opciones = useMemo(() => opcionesFiltros(datos?.opciones), [datos]);

    if (error && !datos) {
        return (
            <div className="exs-panel exs-mensaje" role="alert">
                <p>No se pudo cargar el seguimiento de contratación. {error}</p>
                <button type="button" className="gp-boton exs-reintentar" onClick={cargar}>Reintentar</button>
            </div>
        );
    }

    if (!datos) {
        return <div className="exs-panel exs-mensaje" role="status"><p>Cargando procesos de contratación…</p></div>;
    }

    const conFiltros = hayFiltros(filtros);
    if (datos.procesos.length === 0 && !conFiltros) {
        return <div className="exs-panel exs-mensaje"><p>{textoListaVacia(false)}</p></div>;
    }

    const { resumen } = datos;
    const semaforos = tarjetasSemaforo(resumen, filtros.semaforo);
    const estados = tarjetasEstado(resumen, filtros.estado);
    const { etapas, sinEtapa } = etapasActuales(resumen);

    return (
        <div className="exs-panel ctp-panel" aria-busy={cargando}>
            {error && (
                <div className="tb-aviso" role="alert">
                    <span>No se pudieron actualizar los procesos. {error}</span>
                    <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>
                </div>
            )}

            <div className="exs-resumen ctp-resumen" role="group" aria-label="Procesos por semáforo">
                <button
                    type="button"
                    className={`exs-tarjeta${filtros.semaforo === '' ? ' activa' : ''}`}
                    aria-pressed={filtros.semaforo === ''}
                    onClick={() => aplicar({ ...filtros, semaforo: '' })}
                >
                    <span className="exs-tarjeta-etiqueta">Procesos</span>
                    <span className="exs-tarjeta-valor">{resumen.total}</span>
                </button>
                {semaforos.map((t) => (
                    <button
                        key={t.valor}
                        type="button"
                        className={`exs-tarjeta ctp-tarjeta-${t.valor}${t.activa ? ' activa' : ''}`}
                        aria-pressed={t.activa}
                        onClick={() => aplicar(alternarFiltro(filtros, 'semaforo', t.valor))}
                    >
                        <span className="exs-tarjeta-etiqueta"><span className="ctp-punto" aria-hidden="true" /> {t.etiqueta}</span>
                        <span className="exs-tarjeta-valor">{t.cantidad}</span>
                    </button>
                ))}
            </div>

            <div className="ctp-fila">
                <section className="exs-tabla-marco" aria-labelledby="ctp-estados-titulo">
                    <h3 id="ctp-estados-titulo" className="ctp-subtitulo">Por estado del proceso</h3>
                    {estados.length === 0 ? (
                        <p className="exs-secundario">Ningún proceso con estos filtros.</p>
                    ) : (
                        <div className="ctp-estados" role="group" aria-label="Filtrar por estado del proceso">
                            {estados.map((t) => (
                                <button
                                    key={t.valor}
                                    type="button"
                                    className={`ctp-estado${t.activa ? ' activa' : ''}`}
                                    aria-pressed={t.activa}
                                    onClick={() => aplicar(alternarFiltro(filtros, 'estado', t.valor))}
                                >
                                    <span className={`ct-estado ct-estado-${t.tono}`}>{t.etiqueta}</span>
                                    <strong>{t.cantidad}</strong>
                                </button>
                            ))}
                        </div>
                    )}
                </section>

                <section className="exs-tabla-marco" aria-labelledby="ctp-etapas-titulo">
                    <h3 id="ctp-etapas-titulo" className="ctp-subtitulo">Procesos por etapa actual</h3>
                    {etapas.length === 0 ? (
                        <p className="exs-secundario">Ningún proceso tiene una etapa en curso.</p>
                    ) : (
                        <ul className="ctp-etapas">
                            {etapas.map((e) => (
                                <li key={e.id}>
                                    <span className="ctp-etapa-nombre">{e.nombre}</span>
                                    <span className="tb-pista" aria-hidden="true"><span style={{ width: `${e.porcentaje}%` }} /></span>
                                    <strong>{e.procesos === 1 ? '1 proceso' : `${e.procesos} procesos`}</strong>
                                </li>
                            ))}
                        </ul>
                    )}
                    {sinEtapa > 0 && (
                        <p className="exs-secundario ctp-sin-etapa">
                            {sinEtapa === 1 ? '1 proceso' : `${sinEtapa} procesos`} sin etapa en curso (sin expediente, cerrado o con todo aprobado).
                        </p>
                    )}
                </section>
            </div>

            <div className="exs-tabla-marco">
                <div className="exs-tabla-encabezado">
                    <h3>Procesos de contratación</h3>
                    <div className="exs-filtros">
                        {SELECTS.map(([clave, todos, nombre]) => (opciones[clave].length > 0 || filtros[clave]) && (
                            <select
                                key={clave}
                                className="exs-filtro"
                                aria-label={nombre}
                                value={filtros[clave]}
                                onChange={(e) => aplicar({ ...filtros, [clave]: e.target.value })}
                            >
                                <option value="">{todos}</option>
                                {opciones[clave].map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                            </select>
                        ))}
                        {conFiltros && <button type="button" className="gp-boton" onClick={() => aplicar(FILTROS_VACIOS)}>Quitar filtros</button>}
                    </div>
                </div>

                {datos.procesos.length === 0 ? (
                    <div className="exs-sin-resultados">
                        <p>{textoListaVacia(true)}</p>
                        <button type="button" className="gp-boton" onClick={() => aplicar(FILTROS_VACIOS)}>Quitar filtros</button>
                    </div>
                ) : (
                    <table className="tb-tabla tb-tabla-cartas exs-tabla ctp-tabla">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Objeto</th>
                                <th>Proyecto</th>
                                <th>Modalidad</th>
                                <th className="numero">Monto</th>
                                <th>Estado</th>
                                <th>Etapa actual</th>
                                <th>Avance</th>
                                <th className="numero">Cierre</th>
                                <th>Avisos</th>
                                <th>Semáforo</th>
                                <th aria-label="Acciones" />
                            </tr>
                        </thead>
                        <tbody>
                            {datos.procesos.map((p) => <FilaProceso key={p.id} proceso={p} />)}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
};

export default ContratacionPanel;
