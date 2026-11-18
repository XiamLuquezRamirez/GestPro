import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { faArrowLeft, faArrowUpRightFromSquare, faClipboardCheck, faGavel, faPlus } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { datosGenerales, errorDeFicha, esIdProyecto, filaProceso, resumenFormulacion } from '../utils/fichaProyecto';
import { estadoDesdeFicha, estadoNuevoContratoDeProyecto, filaContrato, puedeEditarContratos, rutaContrato } from '../utils/contratosModulo';
import { RUTA_CONTRATOS } from '../utils/navegacion';
import ProcesosProyecto from './ProcesosProyecto';
import '../../css/Plantillas.css';
import '../../css/Parametros.css';
import '../../css/Contratacion.css';
import '../../css/FichaProyecto.css';

const RUTA_PROYECTOS = '/parametros/proyectos';

const Volver = () => (
    <Link to={RUTA_PROYECTOS} className="pl-volver"><FontAwesomeIcon icon={faArrowLeft} /> Proyectos</Link>
);

/** Un catálogo para mostrar nombres (fuentes, sectores): null mientras carga o si falla. */
const useCatalogo = (ruta) => {
    const [lista, setLista] = useState(null);
    useEffect(() => {
        let vigente = true;
        axios.get(ruta)
            .then(({ data }) => { if (vigente) setLista(Array.isArray(data) ? data : []); })
            .catch(() => {});
        return () => { vigente = false; };
    }, [ruta]);
    return lista;
};

const Bloque = ({ titulo, acciones, children }) => (
    <section className="gp-panel fp-bloque" aria-label={titulo}>
        <header className="fp-bloque-cabecera">
            <h2>{titulo}</h2>
            {acciones && <div className="fp-acciones">{acciones}</div>}
        </header>
        {children}
    </section>
);

const General = ({ proyecto, fuentes, sectores }) => (
    <Bloque titulo="General">
        <dl className="fp-datos">
            {datosGenerales(proyecto, { fuentes, sectores }).map((d) => (
                <div key={d.etiqueta} className={`fp-dato${d.ancho ? ' ancho' : ''}`}>
                    <dt className="gp-etiqueta">{d.etiqueta}</dt>
                    <dd>{d.valor}</dd>
                </div>
            ))}
        </dl>
    </Bloque>
);

const Formulacion = ({ proyectoId, expediente }) => {
    const resumen = resumenFormulacion(expediente, proyectoId);
    if (!resumen.tiene) {
        return <Bloque titulo="Formulación"><p className="gp-vacio">{resumen.texto}</p></Bloque>;
    }
    return (
        <Bloque
            titulo="Formulación"
            acciones={(
                <Link to={resumen.ruta} className="gp-boton">
                    <FontAwesomeIcon icon={faClipboardCheck} /> Abrir expediente
                </Link>
            )}
        >
            <dl className="fp-datos">
                <div className="fp-dato"><dt className="gp-etiqueta">Estado</dt><dd>{resumen.estado}</dd></div>
                <div className="fp-dato">
                    <dt className="gp-etiqueta">Avance del expediente</dt>
                    <dd>
                        {resumen.porcentaje !== null && (
                            <span className="fp-progreso" aria-hidden="true"><span style={{ width: `${resumen.porcentaje}%` }} /></span>
                        )}
                        {resumen.avance}
                    </dd>
                </div>
                <div className="fp-dato"><dt className="gp-etiqueta">Plantillas</dt><dd>{resumen.plantillas}</dd></div>
                {resumen.faseSgr && <div className="fp-dato"><dt className="gp-etiqueta">Fase SGR</dt><dd>{resumen.faseSgr}</dd></div>}
                {resumen.radicado && <div className="fp-dato"><dt className="gp-etiqueta">Radicado</dt><dd>{resumen.radicado}</dd></div>}
            </dl>
            {resumen.otros && <p className="fp-nota">{resumen.otros}</p>}
        </Bloque>
    );
};

const Procesos = ({ procesos, onAbrir }) => {
    const filas = procesos.map(filaProceso);
    return (
        <Bloque
            titulo="Procesos de contratación"
            acciones={<button type="button" className="gp-boton" onClick={onAbrir}><FontAwesomeIcon icon={faGavel} /> Procesos</button>}
        >
            {filas.length === 0 ? <p className="gp-vacio">El proyecto no tiene procesos de contratación.</p> : (
                <div className="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Objeto</th>
                                <th>Modalidad</th>
                                <th>Estado</th>
                                <th>Valor</th>
                                <th>Contratos</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filas.map((f) => (
                                <tr key={f.id}>
                                    <td data-etiqueta="Número">
                                        {f.enlace ? (
                                            <a href={f.enlace} target="_blank" rel="noopener noreferrer" className="ct-enlace">
                                                {f.numero} <FontAwesomeIcon icon={faArrowUpRightFromSquare} />
                                            </a>
                                        ) : f.numero}
                                    </td>
                                    <td data-etiqueta="Objeto"><span className="ct-objeto" title={f.objeto}>{f.objeto}</span></td>
                                    <td data-etiqueta="Modalidad">{f.modalidad}</td>
                                    <td data-etiqueta="Estado"><span className={`ct-estado ct-estado-${f.tono}`}>{f.estado}</span></td>
                                    <td data-etiqueta="Valor" className="numero">{f.monto}</td>
                                    <td data-etiqueta="Contratos">{f.contratos}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Bloque>
    );
};

const Contratos = ({ proyecto, contratos, edita }) => {
    const filas = contratos.map(filaContrato);
    return (
        <Bloque
            titulo="Contratos"
            acciones={edita && (
                <Link to={`${RUTA_CONTRATOS}/nuevo`} state={estadoNuevoContratoDeProyecto(proyecto)} className="gp-boton gp-boton-primario">
                    <FontAwesomeIcon icon={faPlus} /> Nuevo contrato
                </Link>
            )}
        >
            {filas.length === 0 ? <p className="gp-vacio">El proyecto no tiene contratos.</p> : (
                <div className="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Objeto</th>
                                <th>Estado</th>
                                <th>Valor vigente</th>
                                <th>Físico</th>
                                <th>Financiero</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filas.map((f) => (
                                <tr key={f.id}>
                                    <td data-etiqueta="Número"><Link to={rutaContrato(f.id)} state={estadoDesdeFicha(proyecto.id)} className="ct-enlace">{f.numero}</Link></td>
                                    <td data-etiqueta="Objeto"><span className="ct-objeto" title={f.objeto}>{f.objeto}</span></td>
                                    <td data-etiqueta="Estado"><span className={`ct-estado ct-estado-${f.tono}`}>{f.estado}</span></td>
                                    <td data-etiqueta="Valor vigente" className="numero">{f.valor}</td>
                                    <td data-etiqueta="Físico" className="numero">{f.avanceFisico}</td>
                                    <td data-etiqueta="Financiero" className="numero">{f.avanceFinanciero}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Bloque>
    );
};

/** La ficha de un proyecto con id válido. Solo vale la respuesta de la última petición. */
const Ficha = ({ proyectoId, user }) => {
    const [ficha, setFicha] = useState(null);
    // { mensaje, reintentable }: un proyecto que no existe no se reintenta.
    const [error, setError] = useState(null);
    const [procesosAbiertos, setProcesosAbiertos] = useState(false);
    const peticion = useRef(0);
    const fuentes = useCatalogo('/fuentesFinanciacion');
    const sectores = useCatalogo('/sectores');
    const edita = puedeEditarContratos(user);

    const cargar = useCallback(async () => {
        const numero = ++peticion.current;
        setError(null);
        try {
            const { data } = await axios.get(`/proyectos/${proyectoId}/ficha`);
            if (numero === peticion.current) setFicha(data);
        } catch (e) {
            if (numero === peticion.current) setError(errorDeFicha(e));
        }
    }, [proyectoId]);
    useEffect(() => {
        cargar();
        return () => { peticion.current += 1; };
    }, [cargar]);

    const reintentar = error?.reintentable && <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>;
    // Con la ficha ya pintada, un fallo al recargarla no la quita: se avisa encima.
    const aviso = error && <p className="pl-error" role="alert">{error.mensaje} {reintentar}</p>;
    const proyecto = ficha?.proyecto;
    const subtitulo = useMemo(() => (proyecto
        ? [proyecto.descripcion_municipio, proyecto.descripcion_fase].filter(Boolean).join(' · ')
        : ''), [proyecto]);

    if (!ficha) {
        return (
            <div className="pl-pagina">
                <Volver />
                {aviso || <p className="gp-vacio" role="status">Cargando proyecto…</p>}
            </div>
        );
    }

    return (
        <div className="pl-pagina">
            <Volver />
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Proyecto{subtitulo ? ` · ${subtitulo}` : ''}</span>
                <h1>{proyecto.nombre || 'Sin nombre'}</h1>
                <p>Cada avance se mide por separado: el del expediente cuenta requisitos; el de cada contrato, su ejecución física y financiera.</p>
            </header>
            {aviso}
            <div className="fp-bloques">
                <General proyecto={proyecto} fuentes={fuentes} sectores={sectores} />
                <Formulacion proyectoId={proyecto.id} expediente={ficha.expediente} />
                <Procesos procesos={ficha.procesos ?? []} onAbrir={() => setProcesosAbiertos(true)} />
                <Contratos proyecto={proyecto} contratos={ficha.contratos ?? []} edita={edita} />
            </div>

            {procesosAbiertos && (
                <ProcesosProyecto
                    key={proyecto.id}
                    proyecto={proyecto}
                    user={user}
                    onCerrar={() => { setProcesosAbiertos(false); cargar(); }}
                    onCambio={cargar}
                />
            )}
        </div>
    );
};

/**
 * La ficha del proyecto (`/proyectos/:proyectoId`): sus datos generales, su formulación, sus
 * procesos de contratación y sus contratos, cada bloque con enlace a lo que ya existe. Un id
 * que no es válido no llega a la API.
 */
const FichaProyecto = ({ user }) => {
    const { proyectoId } = useParams();
    if (!esIdProyecto(proyectoId)) {
        return (
            <div className="pl-pagina">
                <Volver />
                <p className="pl-error" role="alert">El proyecto no existe.</p>
            </div>
        );
    }
    return <Ficha key={proyectoId} proyectoId={proyectoId} user={user} />;
};

export default FichaProyecto;
