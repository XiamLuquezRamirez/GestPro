import React, { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import { Link, Navigate, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { faArrowLeft, faMagnifyingGlass, faPlus } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../../axios';
import {
    TEXTO_SECOP_VACIO, VISTA_LISTA, VISTA_SECOP, consultaDeFiltros, consultaVistaSecop, errorNit, esIdContrato, estadoImportarSecop,
    estadoSinSecop, filaContrato, filtrosDeConsulta, guardarNitEmpresa, leerNitEmpresa, nitDeConsulta, nitParaMostrar, normalizarNit,
    opcionesFiltroEstado, opcionesFiltroProyecto, parametrosBusqueda, proyectoDeEstado, puedeEditarContratos, resultadoSecopDeEstado, rutaContrato,
    rutaListaDesde, rutaVistaSecop, textoListaVacia, textoVolverDesde, vistaDeConsulta,
} from '../../utils/contratosModulo';
import { avisoProcesoContrato } from '../../utils/avisosContratacion';
import { chipAvisoSuspension, textoAvisoCierre, textoAvisoSuspension } from '../../utils/contratos';
import { RUTA_CONTRATOS } from '../../utils/navegacion';
import { mensajeApi } from '../../utils/plantillasChecklist';
import { mensajeSecop } from '../../utils/secop';
import ContratoEditor from './ContratoEditor';
import { ResultadosSecop } from './SecopContrato';
import '../../../css/Plantillas.css';
import '../../../css/Parametros.css';
import '../../../css/Contratacion.css';

const RUTA_NUEVO = `${RUTA_CONTRATOS}/nuevo`;

/** El contrato completo (GET /contratos/{id}/detalle), con la forma que espera ContratoEditor. */
const pedirDetalle = async (id) => (await axios.get(`/contratos/${id}/detalle`)).data;

/** El `recargar` del editor: el contrato `id` recargado, o null (sin id o si falla). */
const recargarDetalle = async (id) => {
    if (!id) return null;
    try {
        return await pedirDetalle(id);
    } catch (e) {
        return null;
    }
};

/**
 * Los proyectos para el filtro y el selector de proyecto (GET /proyectos, solo id y nombre):
 * `lista` es null mientras cargan; si fallan, `error` y `reintentar`.
 */
const useProyectos = () => {
    const [lista, setLista] = useState(null);
    const [error, setError] = useState(null);
    const cargar = useCallback(async () => {
        setError(null);
        try {
            const { data } = await axios.get('/proyectos');
            setLista((Array.isArray(data) ? data : []).map(({ id, nombre }) => ({ id, nombre })));
        } catch (e) {
            setError(mensajeApi(e));
        }
    }, []);
    useEffect(() => { cargar(); }, [cargar]);
    return { lista, error, reintentar: cargar };
};

/**
 * El aviso de un contrato cuyo proceso tiene etapas vencidas, el cierre vencido o está sin
 * actualización (`aviso_proceso`), con el enlace al checklist del proceso. Sin aviso, nada.
 */
const AvisoProceso = ({ aviso, bloque = false }) => {
    const datos = avisoProcesoContrato(aviso);
    if (!datos) return null;
    const enlace = <Link to={datos.ruta} className="ct-enlace">Abrir el checklist del proceso</Link>;
    if (bloque) {
        return <p className="pl-aviso-suave"><b>{datos.texto}.</b> {enlace}</p>;
    }
    return (
        <span className="ct-aviso-proceso">
            <span className="ct-estado ct-estado-coral">{datos.texto}</span>
            <Link to={datos.ruta} className="ct-enlace">Checklist<span className="ct-oculto"> del proceso</span></Link>
        </span>
    );
};

const AvisoProyectos = ({ proyectos }) => (proyectos.error ? (
    <p className="pl-error" role="alert">
        No se pudieron cargar los proyectos: {proyectos.error}{' '}
        <button type="button" className="gp-boton" onClick={proyectos.reintentar}>Reintentar</button>
    </p>
) : null);

const Volver = ({ a, texto = 'Contratos' }) => (
    <Link to={a} className="pl-volver"><FontAwesomeIcon icon={faArrowLeft} /> {texto}</Link>
);

/** Las vistas de /contratos para quien puede editar: la lista y los contratos de la empresa en SECOP II. */
const PestanasVista = ({ vista }) => {
    const pestana = (propia) => ({
        className: `pl-pestana${vista === propia ? ' activa' : ''}`,
        'aria-current': vista === propia ? 'page' : undefined,
    });
    return (
        <nav className="pl-pestanas" aria-label="Vistas de contratos">
            <Link to={RUTA_CONTRATOS} {...pestana(VISTA_LISTA)}>En GestPro</Link>
            <Link to={rutaVistaSecop()} {...pestana(VISTA_SECOP)}>En SECOP II</Link>
        </nav>
    );
};

/** Lista de todos los contratos, con o sin proyecto. Los filtros viven en la URL. */
const ListaContratos = ({ edita, proyectos }) => {
    const { search } = useLocation();
    const [consulta, setConsulta] = useSearchParams();
    const filtros = useMemo(() => filtrosDeConsulta(consulta), [consulta]);
    const clave = consultaDeFiltros(filtros).toString();
    const [texto, setTexto] = useState(filtros.q);
    const [contratos, setContratos] = useState(null);
    const [error, setError] = useState(null);
    const peticion = useRef(0);

    // Reemplaza la entrada del historial: filtrar no es una página nueva.
    const filtrar = useCallback((cambios) => {
        setConsulta(consultaDeFiltros({ ...filtrosDeConsulta(consulta), ...cambios }), { replace: true });
    }, [consulta, setConsulta]);

    // La búsqueda llega a la URL cuando se deja de escribir; si la URL cambia (al volver), el campo la sigue.
    useEffect(() => {
        if (texto.trim() === filtros.q) return undefined;
        const temporizador = setTimeout(() => filtrar({ q: texto }), 300);
        return () => clearTimeout(temporizador);
    }, [texto, filtros.q, filtrar]);
    useEffect(() => {
        setTexto((actual) => (actual.trim() === filtros.q ? actual : filtros.q));
    }, [filtros.q]);

    // Solo vale la respuesta de la última petición.
    const cargar = useCallback(async () => {
        const numero = ++peticion.current;
        setError(null);
        try {
            const params = parametrosBusqueda(filtrosDeConsulta(new URLSearchParams(clave)));
            const { data } = await axios.get('/contratos/buscar', { params });
            if (numero === peticion.current) setContratos(Array.isArray(data) ? data : []);
        } catch (e) {
            if (numero === peticion.current) setError(mensajeApi(e));
        }
    }, [clave]);
    useEffect(() => { cargar(); }, [cargar]);

    const filas = useMemo(() => (contratos ?? []).map((c) => ({
        ...filaContrato(c), avisoProceso: c.aviso_proceso, avisoCierre: textoAvisoCierre(c.aviso_cierre),
        // Fase 7B: desde cuándo está suspendido (como título) y el chip si su reinicio previsto venció.
        avisoSuspension: textoAvisoSuspension(c.aviso_suspension), chipSuspension: chipAvisoSuspension(c.aviso_suspension),
    })), [contratos]);
    // Al abrir un contrato se lleva la lista con sus filtros, para volver a ella.
    const estadoNavegacion = { lista: search };

    return (
        <div className="pl-pagina">
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Trabajo diario</span>
                <h1>Contratos</h1>
                <p>Todos los contratos, con o sin proyecto: su estado, su valor vigente y su avance.</p>
            </header>

            {edita && <PestanasVista vista={VISTA_LISTA} />}
            <AvisoProyectos proyectos={proyectos} />

            <div className="pl-barra">
                <input
                    type="search"
                    className="pl-buscar"
                    placeholder="Buscar por número, objeto, contratista, contratante o NIT"
                    aria-label="Buscar contratos"
                    maxLength={100}
                    value={texto}
                    onChange={(e) => setTexto(e.target.value)}
                />
                <select className="pl-buscar ct-filtro" aria-label="Estado" value={filtros.estado} onChange={(e) => filtrar({ estado: e.target.value })}>
                    {opcionesFiltroEstado().map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                </select>
                <select className="pl-buscar ct-filtro" aria-label="Proyecto" value={filtros.proyecto} onChange={(e) => filtrar({ proyecto: e.target.value })}>
                    {opcionesFiltroProyecto(proyectos.lista, filtros.proyecto).map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                </select>
                {edita && (
                    <Link to={RUTA_NUEVO} state={estadoNavegacion} className="gp-boton gp-boton-primario">
                        <FontAwesomeIcon icon={faPlus} /> Nuevo contrato
                    </Link>
                )}
            </div>

            {error && (
                <p className="pl-error" role="alert">
                    {error} <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>
                </p>
            )}
            {contratos === null && !error && <p className="gp-vacio" role="status">Cargando contratos…</p>}
            {contratos !== null && !error && filas.length === 0 && <p className="gp-vacio">{textoListaVacia(filtros)}</p>}

            {/* Con un error no se muestra la tabla del filtro anterior: no es lo que se buscó. */}
            {!error && filas.length > 0 && (
                <div className="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Objeto</th>
                                <th>Contratista</th>
                                <th>Proyecto</th>
                                <th>Proceso</th>
                                <th>Estado</th>
                                <th>Valor vigente</th>
                                <th>Físico</th>
                                <th>Financiero</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filas.map((f) => (
                                <tr key={f.id}>
                                    <td data-etiqueta="Número">
                                        <Link to={rutaContrato(f.id)} state={estadoNavegacion} className="ct-enlace">{f.numero}</Link>
                                    </td>
                                    <td data-etiqueta="Objeto"><span className="ct-objeto" title={f.objeto}>{f.objeto}</span></td>
                                    <td data-etiqueta="Contratista">{f.contratista}</td>
                                    <td data-etiqueta="Proyecto" className={f.sinProyecto ? 'ct-sin-dato' : undefined}>{f.proyecto}</td>
                                    <td data-etiqueta="Proceso" className="numero">
                                        {f.proceso}
                                        <AvisoProceso aviso={f.avisoProceso} />
                                    </td>
                                    <td data-etiqueta="Estado">
                                        <span className={`ct-estado ct-estado-${f.tono}`} title={f.avisoSuspension ?? undefined}>{f.estado}</span>
                                        {f.avisoCierre && <span className="ct-aviso-cierre ct-estado ct-estado-ambar">{f.avisoCierre}</span>}
                                        {f.chipSuspension && <span className="ct-aviso-cierre ct-estado ct-estado-coral" title={f.avisoSuspension}>{f.chipSuspension}</span>}
                                    </td>
                                    <td data-etiqueta="Valor vigente" className="numero">{f.valor}</td>
                                    <td data-etiqueta="Físico" className="numero">{f.avanceFisico}</td>
                                    <td data-etiqueta="Financiero" className="numero">{f.avanceFinanciero}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
};

/**
 * Los contratos de la empresa en SECOP II (`?vista=secop`): aquellos en que la empresa es el
 * proveedor (GET /secop/contratos-empresa). El NIT se recuerda en el navegador y el consultado va
 * en la URL, así que al volver de un contrato o de importar se consulta de nuevo. Solo vale la
 * última respuesta. "Importar" abre un contrato nuevo lleno con los datos del resultado.
 */
const ContratosSecop = () => {
    const { search } = useLocation();
    const navigate = useNavigate();
    const [consulta, setConsulta] = useSearchParams();
    const nitConsultado = nitDeConsulta(consulta);
    const [nit, setNit] = useState(() => nitConsultado || leerNitEmpresa());
    const [errorCampo, setErrorCampo] = useState(null);
    // Los resultados del NIT consultado: null sin consulta o mientras carga.
    const [consultado, setConsultado] = useState({ nit: '', resultados: null });
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const turno = useRef(0);
    const idAyuda = useId();
    const idError = useId();

    // Al salir de la vista, cualquier respuesta pendiente queda atrasada.
    useEffect(() => () => { turno.current += 1; }, []);

    const cargar = useCallback(async (nitBuscado) => {
        const numero = ++turno.current;
        setCargando(true);
        setError(null);
        setConsultado({ nit: nitBuscado, resultados: null });
        try {
            const { data } = await axios.get('/secop/contratos-empresa', { params: { nit: nitBuscado } });
            if (numero !== turno.current) return;
            setConsultado({ nit: nitBuscado, resultados: Array.isArray(data?.resultados) ? data.resultados : [] });
        } catch (falla) {
            if (numero !== turno.current) return;
            setError(mensajeSecop(falla));
        } finally {
            if (numero === turno.current) setCargando(false);
        }
    }, []);

    // El NIT de la URL manda: se consulta al llegar o al volver; sin NIT no hay resultados.
    useEffect(() => {
        if (nitConsultado) {
            setNit((actual) => (normalizarNit(actual) === nitConsultado ? actual : nitConsultado));
            setErrorCampo(null);
            cargar(nitConsultado);
            return;
        }
        turno.current += 1;
        setCargando(false);
        setError(null);
        setConsultado({ nit: '', resultados: null });
    }, [nitConsultado, cargar]);

    const consultar = (e) => {
        e.preventDefault();
        const falla = errorNit(nit);
        setErrorCampo(falla);
        if (falla) return;
        const normalizado = normalizarNit(nit);
        guardarNitEmpresa(normalizado);
        // Consultar no es una página nueva; el mismo NIT se consulta otra vez.
        if (normalizado === nitConsultado) cargar(normalizado);
        else setConsulta(consultaVistaSecop(normalizado), { replace: true });
    };

    // La vista (con su NIT) va en el estado: "Volver" y "Cancelar" regresan a ella.
    const importar = (resultado) => navigate(RUTA_NUEVO, { state: estadoImportarSecop(resultado, search) });
    const { resultados } = consultado;
    const listos = !cargando && !error && resultados !== null;

    return (
        <div className="pl-pagina">
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Trabajo diario</span>
                <h1>Contratos</h1>
                <p>Todos los contratos, con o sin proyecto: su estado, su valor vigente y su avance.</p>
            </header>

            <PestanasVista vista={VISTA_SECOP} />

            <section aria-labelledby="secop-empresa-titulo">
                <h2 id="secop-empresa-titulo" className="ct-secop-titulo">Contratos de la empresa en SECOP II</h2>
                <p className="ct-secop-nota">
                    Los contratos en que la empresa es el proveedor, según los datos abiertos de SECOP II. Importa los que aún no están en GestPro.
                </p>

                <form className="pl-barra ct-secop-empresa" onSubmit={consultar} noValidate aria-label="Consultar los contratos de la empresa en SECOP II">
                    <label className="pl-campo">
                        <span>NIT de la empresa</span>
                        <input
                            type="text"
                            value={nit}
                            onChange={(e) => { setNit(e.target.value); setErrorCampo(null); }}
                            maxLength={20}
                            inputMode="numeric"
                            placeholder="900.123.456-7"
                            aria-describedby={errorCampo ? `${idAyuda} ${idError}` : idAyuda}
                            aria-invalid={Boolean(errorCampo)}
                        />
                        <small id={idAyuda} className="contrato-nota">Se recuerda en este navegador.</small>
                    </label>
                    <button type="submit" className="gp-boton gp-boton-primario" disabled={cargando}>
                        <FontAwesomeIcon icon={faMagnifyingGlass} /> {cargando ? 'Consultando…' : 'Consultar'}
                    </button>
                </form>
                {errorCampo && <p id={idError} className="pl-error" role="alert">{errorCampo}</p>}

                {error && (
                    <p className="pl-error" role="alert">
                        {error} <button type="button" className="gp-boton" onClick={() => cargar(consultado.nit)}>Reintentar</button>
                    </p>
                )}
                <div aria-live="polite">
                    {cargando && <p className="gp-vacio" role="status">Consultando SECOP II…</p>}
                    {listos && resultados.length === 0 && <p className="gp-vacio">{TEXTO_SECOP_VACIO}</p>}
                    {listos && resultados.length > 0 && (
                        <>
                            <p className="ct-secop-nota ct-secop-conteo">
                                {resultados.length === 1 ? '1 contrato' : `${resultados.length} contratos`} con el NIT {nitParaMostrar(consultado.nit)} como proveedor.
                            </p>
                            <ResultadosSecop resultados={resultados} textoAccion="Importar" onElegir={importar} estadoEnlace={{ lista: search }} />
                        </>
                    )}
                </div>
            </section>
        </div>
    );
};

/**
 * Un contrato nuevo, con proyecto opcional. Si llega de "Importar" (con el resultado de SECOP II
 * en el estado de navegación), el formulario empieza lleno; si llega de la ficha del proyecto,
 * con ese proyecto ya elegido; sin estado (al abrir la dirección a mano), vacío. Al guardarlo se
 * abre, para seguir con sus pestañas.
 */
const NuevoContrato = ({ proyectos }) => {
    const { state, key } = useLocation();
    const navigate = useNavigate();
    const volver = rutaListaDesde(state);
    const importacion = resultadoSecopDeEstado(state);
    const proyecto = proyectoDeEstado(state);

    return (
        <div className="pl-pagina">
            <Volver a={volver} texto={textoVolverDesde(state)} />
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Contratos</span>
                <h1>Nuevo contrato</h1>
            </header>
            <AvisoProyectos proyectos={proyectos} />
            <div className="gp-panel ct-editor">
                <ContratoEditor
                    key={key}
                    proyecto={proyecto}
                    proyectos={proyectos.lista}
                    importacionSecop={importacion}
                    recargar={recargarDetalle}
                    onGuardado={(guardado) => navigate(guardado?.id ? rutaContrato(guardado.id) : volver, { replace: true, state: estadoSinSecop(state) })}
                    onCancelar={() => navigate(volver)}
                />
            </div>
        </div>
    );
};

/** Un contrato existente: el editor completo. Sin permiso, se ve sin controles para modificarlo. */
const ContratoAbierto = ({ contratoId, edita, proyectos }) => {
    const { state } = useLocation();
    const navigate = useNavigate();
    const volver = rutaListaDesde(state);
    const [contrato, setContrato] = useState(null);
    // { mensaje, reintentable }: un contrato que no existe no se reintenta.
    const [error, setError] = useState(null);
    const peticion = useRef(0);

    const cargar = useCallback(async () => {
        const numero = ++peticion.current;
        setError(null);
        try {
            const datos = await pedirDetalle(contratoId);
            if (numero === peticion.current) setContrato(datos);
        } catch (e) {
            if (numero !== peticion.current) return;
            const noExiste = e?.response?.status === 404;
            setError({ mensaje: noExiste ? 'El contrato no existe.' : mensajeApi(e), reintentable: !noExiste });
        }
    }, [contratoId]);
    useEffect(() => { cargar(); }, [cargar]);

    // El editor recarga el contrato tras un cambio en sus pestañas; la cabecera también se actualiza.
    const recargar = useCallback(async (id) => {
        const datos = await recargarDetalle(id);
        if (datos && String(datos.id) === String(contratoId)) setContrato(datos);
        return datos;
    }, [contratoId]);

    if (error) {
        return (
            <div className="pl-pagina">
                <Volver a={volver} texto={textoVolverDesde(state)} />
                <p className="pl-error" role="alert">
                    {error.mensaje}{' '}
                    {error.reintentable && <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>}
                </p>
            </div>
        );
    }
    if (!contrato) return <p className="gp-vacio" role="status">Cargando contrato…</p>;

    return (
        <div className="pl-pagina">
            <Volver a={volver} texto={textoVolverDesde(state)} />
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Contrato · {contrato.proyecto_nombre || 'Sin proyecto'}</span>
                <h1>{contrato.n_contrato || 'Sin número'}</h1>
                {!edita && <p>Solo lectura: tu rol puede consultar el contrato, pero no modificarlo.</p>}
            </header>
            {edita && <AvisoProyectos proyectos={proyectos} />}
            <AvisoProceso aviso={contrato.aviso_proceso} bloque />
            <div className="gp-panel ct-editor">
                <ContratoEditor
                    key={contrato.id}
                    contrato={contrato}
                    proyectos={proyectos.lista}
                    puedeEditar={edita}
                    recargar={recargar}
                    onGuardado={() => navigate(volver)}
                    onCancelar={() => navigate(volver)}
                />
            </div>
        </div>
    );
};

/**
 * El módulo Contratos: la lista (`/contratos`), los contratos de la empresa en SECOP II
 * (`/contratos?vista=secop`), un contrato nuevo (`/contratos/nuevo`; estos dos, solo quien puede
 * editar) y uno abierto (`/contratos/:contratoId`).
 */
const ContratosModulo = ({ user, nuevo = false }) => {
    const { contratoId } = useParams();
    const [consulta] = useSearchParams();
    const edita = puedeEditarContratos(user);
    const proyectos = useProyectos();

    if (nuevo) {
        if (!edita) return <Navigate to={RUTA_CONTRATOS} replace />;
        return <NuevoContrato proyectos={proyectos} />;
    }
    if (contratoId !== undefined) {
        if (!esIdContrato(contratoId)) {
            return (
                <div className="pl-pagina">
                    <Volver a={RUTA_CONTRATOS} />
                    <p className="pl-error" role="alert">El contrato no existe.</p>
                </div>
            );
        }
        return <ContratoAbierto key={contratoId} contratoId={contratoId} edita={edita} proyectos={proyectos} />;
    }
    if (vistaDeConsulta(consulta, edita) === VISTA_SECOP) return <ContratosSecop />;
    return <ListaContratos edita={edita} proyectos={proyectos} />;
};

export default ContratosModulo;
