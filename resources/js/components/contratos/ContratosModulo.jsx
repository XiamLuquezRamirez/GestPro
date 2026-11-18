import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, Navigate, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { faArrowLeft, faPlus } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../../axios';
import {
    consultaDeFiltros, esIdContrato, filaContrato, filtrosDeConsulta, opcionesFiltroEstado, opcionesFiltroProyecto, parametrosBusqueda,
    puedeEditarContratos, rutaContrato, rutaListaDesde, textoListaVacia,
} from '../../utils/contratosModulo';
import { RUTA_CONTRATOS } from '../../utils/navegacion';
import { mensajeApi } from '../../utils/plantillasChecklist';
import ContratoEditor from './ContratoEditor';
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

const AvisoProyectos = ({ proyectos }) => (proyectos.error ? (
    <p className="pl-error" role="alert">
        No se pudieron cargar los proyectos: {proyectos.error}{' '}
        <button type="button" className="gp-boton" onClick={proyectos.reintentar}>Reintentar</button>
    </p>
) : null);

const Volver = ({ a }) => (
    <Link to={a} className="pl-volver"><FontAwesomeIcon icon={faArrowLeft} /> Contratos</Link>
);

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

    const filas = useMemo(() => (contratos ?? []).map(filaContrato), [contratos]);
    // Al abrir un contrato se lleva la lista con sus filtros, para volver a ella.
    const estadoNavegacion = { lista: search };

    return (
        <div className="pl-pagina">
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Trabajo diario</span>
                <h1>Contratos</h1>
                <p>Todos los contratos, con o sin proyecto: su estado, su valor vigente y su avance.</p>
            </header>

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
                                    <td data-etiqueta="Proceso" className="numero">{f.proceso}</td>
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
        </div>
    );
};

/** Un contrato nuevo, con proyecto opcional. Al guardarlo se abre, para seguir con sus pestañas. */
const NuevoContrato = ({ proyectos }) => {
    const { state } = useLocation();
    const navigate = useNavigate();
    const volver = rutaListaDesde(state);

    return (
        <div className="pl-pagina">
            <Volver a={volver} />
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Contratos</span>
                <h1>Nuevo contrato</h1>
            </header>
            <AvisoProyectos proyectos={proyectos} />
            <div className="gp-panel ct-editor">
                <ContratoEditor
                    proyectos={proyectos.lista}
                    recargar={recargarDetalle}
                    onGuardado={(guardado) => navigate(guardado?.id ? rutaContrato(guardado.id) : volver, { replace: true, state })}
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
                <Volver a={volver} />
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
            <Volver a={volver} />
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Contrato · {contrato.proyecto_nombre || 'Sin proyecto'}</span>
                <h1>{contrato.n_contrato || 'Sin número'}</h1>
                {!edita && <p>Solo lectura: tu rol puede consultar el contrato, pero no modificarlo.</p>}
            </header>
            {edita && <AvisoProyectos proyectos={proyectos} />}
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
 * El módulo Contratos: la lista (`/contratos`), un contrato nuevo (`/contratos/nuevo`, solo quien
 * puede editar) y uno abierto (`/contratos/:contratoId`).
 */
const ContratosModulo = ({ user, nuevo = false }) => {
    const { contratoId } = useParams();
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
    return <ListaContratos edita={edita} proyectos={proyectos} />;
};

export default ContratosModulo;
