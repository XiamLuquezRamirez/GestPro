import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowRightArrowLeft, faArrowUpRightFromSquare, faClipboardCheck, faFileSignature, faGavel, faPen, faPlus, faTrash, faXmark } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { Link } from 'react-router-dom';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
import { rutaExpedienteContratacion } from '../utils/navegacion';
import { mensajeApi } from '../utils/plantillasChecklist';
import {
    avanceExpediente, CAMPOS_PROCESO, ESTADOS_PROCESO, MAXIMO_OBSERVACION, MAXIMO_OBSERVACIONES_PROCESO, PLATAFORMAS, avisoCambioEstado, datosEstado, datosProceso,
    erroresPorCampo, esEdicionDesactualizada, etiquetaEstadoProceso, etiquetaPlataforma, formularioProceso,
    modoTrasGuardarContrato, numeroProceso, opcionesConActual, opcionesFuenteProceso, ordenarCatalogo, puedeEditarProcesos, puedeRegistrarContrato,
    textoContratos, textoFecha, textoValor, tonoEstado, validarProceso, valorEnEdicion,
} from '../utils/contratacion';
import ContratoEditor from './contratos/ContratoEditor';
import useDialogoModal from './useDialogoModal';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';

// Catálogos del formulario: su ruta y cómo se guarda cada respuesta.
const comoLista = (datos) => (Array.isArray(datos) ? datos : []);
const CATALOGOS = [
    { clave: 'modalidades', ruta: '/modalidades', guardar: (d, { setModalidades }) => setModalidades(ordenarCatalogo(d)) },
    { clave: 'entidades', ruta: '/entidades', guardar: (d, { setEntidades }) => setEntidades(comoLista(d)) },
    { clave: 'fuentes', ruta: '/fuentesFinanciacion', guardar: (d, { setFuentes }) => setFuentes(comoLista(d)) },
    { clave: 'responsables', ruta: '/responsables', guardar: (d, { setResponsables }) => setResponsables(comoLista(d)) },
];

// Un contrato nuevo no tiene pestañas que recargar: el editor lo cierra quien lo abrió al guardar.
const sinRecargar = async () => null;

/**
 * Diálogo modal accesible: Escape llama a `onEscape`, un clic en el fondo a `onFondo` y, al
 * cerrar, el foco vuelve a donde estaba (el botón "Procesos" de la fila).
 */
const Dialogo = ({ titulo, subtitulo, onCerrar, onEscape, onFondo, children }) => {
    const caja = useRef(null);
    const idTitulo = useId();
    const alPulsar = useDialogoModal(caja, onEscape);

    return (
        <div className="ct-dialogo-fondo" onMouseDown={(e) => { if (e.target === e.currentTarget) onFondo(); }}>
            <div ref={caja} className="ct-dialogo" role="dialog" aria-modal="true" aria-labelledby={idTitulo} tabIndex={-1} onKeyDown={alPulsar}>
                <header className="ct-dialogo-cabecera">
                    <div>
                        {subtitulo && <span className="gp-etiqueta">{subtitulo}</span>}
                        <h2 id={idTitulo}>{titulo}</h2>
                    </div>
                    <button type="button" className="gp-icono" onClick={onCerrar} aria-label="Cerrar">
                        <FontAwesomeIcon icon={faXmark} />
                    </button>
                </header>
                <div className="ct-dialogo-cuerpo">{children}</div>
            </div>
        </div>
    );
};

/**
 * El editor de un contrato nuevo del proyecto, con ese proceso ya elegido (y su precarga), en un
 * diálogo propio sobre el de procesos. Los controles de cerrar son los del editor (Cancelar) y la
 * "×"; Escape cancela y un clic en el fondo no descarta lo escrito. Mientras el editor guarda,
 * ni Escape ni la "×" lo cierran.
 */
const DialogoContrato = ({ proyecto, proceso, onGuardado, onCancelar }) => {
    const caja = useRef(null);
    const idTitulo = useId();
    const [guardando, setGuardando] = useState(false);
    const cancelar = () => { if (!guardando) onCancelar(); };
    const alPulsar = useDialogoModal(caja, cancelar);

    return (
        <div className="modal-overlay">
            <div ref={caja} className="modal-content contratos-modal" role="dialog" aria-modal="true" aria-labelledby={idTitulo} tabIndex={-1} onKeyDown={alPulsar}>
                <div className="modal-header">
                    <h2 id={idTitulo}><FontAwesomeIcon icon={faFileSignature} aria-hidden="true" /> Nuevo contrato - {proyecto.nombre}</h2>
                    <button type="button" onClick={cancelar} className="btn-close" aria-label="Cerrar" disabled={guardando}>×</button>
                </div>
                <ContratoEditor
                    proyecto={proyecto}
                    procesoInicial={proceso.id}
                    recargar={sinRecargar}
                    onGuardado={onGuardado}
                    onCancelar={cancelar}
                    onOcupado={setGuardando}
                />
            </div>
        </div>
    );
};

const Campo = ({ etiqueta, error, idError, ancho, children }) => (
    <label className={`pl-campo${ancho ? ' pl-campo-ancho' : ''}`}>
        <span>{etiqueta}</span>
        {children}
        {error && <small id={idError} className="ct-error-campo">{error}</small>}
    </label>
);

/**
 * Formulario de un proceso. Valida antes de enviar con las reglas del servidor; `onGuardar`
 * devuelve los errores por campo del servidor, o null. Escape cancela sin cerrar el diálogo.
 */
const FormularioProceso = ({ proceso, fuenteIds, modalidades, entidades, fuentes, responsables, guardando, onGuardar, onCancelar }) => {
    const [f, setF] = useState(() => formularioProceso(proceso));
    const [errores, setErrores] = useState({});
    const formulario = useRef(null);
    const id = useId();

    const enfocarPrimerError = (lista) => {
        const campo = CAMPOS_PROCESO.find((c) => lista[c]);
        if (campo) formulario.current?.querySelector(`[name="${campo}"]`)?.focus();
    };

    const enviar = async (e) => {
        e.preventDefault();
        const locales = validarProceso(f);
        setErrores(locales);
        if (Object.keys(locales).length) {
            enfocarPrimerError(locales);
            return;
        }
        const delServidor = await onGuardar(f);
        if (delServidor && Object.keys(delServidor).length) {
            setErrores(delServidor);
            enfocarPrimerError(delServidor);
        }
    };

    const cambiar = (campo, valor) => {
        setF((actual) => ({ ...actual, [campo]: valor }));
        setErrores((actual) => (actual[campo] ? { ...actual, [campo]: undefined } : actual));
    };

    const alPulsar = (e) => {
        if (e.key !== 'Escape') return;
        e.stopPropagation();
        if (!guardando) onCancelar();
    };

    const fuentesProceso = opcionesFuenteProceso(fuentes, proceso?.fuente, fuenteIds);

    // Propiedades comunes de cada control: nombre, valor, aviso de error y su descripción.
    const control = (campo) => ({
        name: campo,
        value: f[campo],
        'aria-invalid': Boolean(errores[campo]),
        'aria-describedby': errores[campo] ? `${id}-${campo}` : undefined,
        onChange: (e) => cambiar(campo, e.target.value),
    });
    const campo = (nombre, etiqueta, hijo, ancho = false) => (
        <Campo etiqueta={etiqueta} error={errores[nombre]} idError={`${id}-${nombre}`} ancho={ancho}>{hijo}</Campo>
    );

    return (
        <form ref={formulario} className="gp-panel pl-formulario" onSubmit={enviar} onKeyDown={alPulsar} noValidate aria-labelledby={`${id}-titulo`}>
            <h3 id={`${id}-titulo`} className="ct-formulario-titulo">{proceso ? 'Editar proceso' : 'Nuevo proceso'}</h3>
            {campo('objeto', 'Objeto', <textarea rows={3} maxLength={5000} autoFocus {...control('objeto')} />, true)}
            {campo('modalidad_id', 'Modalidad', (
                <select {...control('modalidad_id')}>
                    <option value="">Elige la modalidad</option>
                    {opcionesConActual(modalidades, proceso?.modalidad).map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                </select>
            ))}
            {campo('plataforma', 'Plataforma', (
                <select {...control('plataforma')}>
                    <option value="">Elige la plataforma</option>
                    {PLATAFORMAS.map(([valor, texto]) => <option key={valor} value={valor}>{texto}</option>)}
                </select>
            ))}
            {campo('codigo_proceso', 'Número del proceso (opcional)', <input maxLength={100} {...control('codigo_proceso')} />)}
            {campo('url_proceso', 'Enlace al proceso (opcional)', (
                <input type="url" inputMode="url" maxLength={500} placeholder="https://" {...control('url_proceso')} />
            ))}
            {campo('entidad_contratante_id', 'Entidad contratante (opcional)', (
                <select {...control('entidad_contratante_id')}>
                    <option value="">Sin entidad</option>
                    {opcionesConActual(entidades, proceso?.entidad_contratante).map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                </select>
            ))}
            {campo('monto', 'Valor en pesos (opcional)', (
                <input inputMode="decimal" placeholder="0" {...control('monto')} onChange={(e) => cambiar('monto', valorEnEdicion(e.target.value, f.monto))} />
            ))}
            {campo('fuente_id', 'Fuente de financiación (opcional)', (
                <select {...control('fuente_id')}>
                    <option value="">Sin fuente</option>
                    {fuentesProceso.delProyecto.length > 0 && (
                        <optgroup label="Fuentes del proyecto">
                            {fuentesProceso.delProyecto.map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                        </optgroup>
                    )}
                    {fuentesProceso.delProyecto.length > 0 && fuentesProceso.otras.length > 0 ? (
                        <optgroup label="Otras fuentes">
                            {fuentesProceso.otras.map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                        </optgroup>
                    ) : fuentesProceso.otras.map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                </select>
            ))}
            {campo('responsable_id', 'Responsable (opcional)', (
                <select {...control('responsable_id')}>
                    <option value="">Sin responsable</option>
                    {opcionesConActual(responsables, proceso?.responsable).map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                </select>
            ))}
            {campo('fecha_apertura', 'Fecha de apertura (opcional)', <input type="date" {...control('fecha_apertura')} />)}
            {campo('fecha_cierre', 'Fecha de cierre (opcional)', <input type="date" {...control('fecha_cierre')} />)}
            {campo('observaciones', 'Observaciones (opcional)', (
                <textarea rows={3} maxLength={MAXIMO_OBSERVACIONES_PROCESO} {...control('observaciones')} />
            ), true)}
            <div className="pl-formulario-acciones">
                <button type="button" className="gp-boton" onClick={onCancelar} disabled={guardando}>Cancelar</button>
                <button type="submit" className="gp-boton gp-boton-primario" disabled={guardando}>Guardar proceso</button>
            </div>
        </form>
    );
};

/**
 * Cambio de estado de un proceso, con observación opcional. Nunca se bloquea: si el cambio es
 * inusual (sale de un cierre o vuelve atrás en el flujo) se pide confirmación antes de enviarlo.
 */
const FormularioEstado = ({ proceso, guardando, onGuardar, onCancelar }) => {
    const [estado, setEstado] = useState('');
    const [observacion, setObservacion] = useState('');
    const [errores, setErrores] = useState({});
    const id = useId();

    const enviar = async (e) => {
        e.preventDefault();
        if (!estado) {
            setErrores({ estado: 'Elige el estado del proceso.' });
            return;
        }
        const delServidor = await onGuardar(estado, observacion);
        if (delServidor && Object.keys(delServidor).length) setErrores(delServidor);
    };

    const alPulsar = (e) => {
        if (e.key !== 'Escape') return;
        e.stopPropagation();
        if (!guardando) onCancelar();
    };

    return (
        <form className="pl-formulario ct-cambio-estado" onSubmit={enviar} onKeyDown={alPulsar} noValidate aria-label="Cambiar estado del proceso">
            <Campo etiqueta={`Nuevo estado (ahora: ${etiquetaEstadoProceso(proceso.estado)})`} error={errores.estado} idError={`${id}-estado`}>
                <select
                    autoFocus
                    value={estado}
                    aria-invalid={Boolean(errores.estado)}
                    aria-describedby={errores.estado ? `${id}-estado` : undefined}
                    onChange={(e) => { setEstado(e.target.value); setErrores({}); }}
                >
                    <option value="">Elige el estado</option>
                    {ESTADOS_PROCESO.filter(([valor]) => valor !== proceso.estado).map(([valor, texto]) => <option key={valor} value={valor}>{texto}</option>)}
                </select>
            </Campo>
            <Campo etiqueta="Observación (opcional)" error={errores.observacion} idError={`${id}-observacion`} ancho>
                <textarea
                    rows={2}
                    maxLength={MAXIMO_OBSERVACION}
                    value={observacion}
                    aria-invalid={Boolean(errores.observacion)}
                    aria-describedby={errores.observacion ? `${id}-observacion` : undefined}
                    onChange={(e) => setObservacion(e.target.value)}
                />
            </Campo>
            <div className="pl-formulario-acciones">
                <button type="button" className="gp-boton" onClick={onCancelar} disabled={guardando}>Cancelar</button>
                <button type="submit" className="gp-boton gp-boton-primario" disabled={guardando}>Cambiar estado</button>
            </div>
        </form>
    );
};

const NumeroProceso = ({ proceso }) => {
    const { texto, enlace } = numeroProceso(proceso);
    if (!enlace) return texto;
    return (
        <a href={enlace} target="_blank" rel="noopener noreferrer" className="ct-enlace">
            {texto} <FontAwesomeIcon icon={faArrowUpRightFromSquare} aria-hidden="true" />
            <span className="ct-oculto"> (se abre en otra pestaña)</span>
        </a>
    );
};

/**
 * El avance del expediente del proceso (su propio conteo de requisitos), su estado si está
 * cerrado, el aviso de plantilla y el enlace al checklist. Al navegar, la página que abrió el
 * diálogo se desmonta y con ella el diálogo, que devuelve el foco y se cierra limpio.
 */
const ExpedienteProceso = ({ proceso }) => {
    const avance = avanceExpediente(proceso.expediente);
    return (
        <>
            <div>
                <dt>Expediente</dt>
                <dd>
                    {avance.texto}{avance.cerrado ? ' · Cerrado' : ''}
                    {' '}
                    <Link to={rutaExpedienteContratacion(proceso.id)} className="ct-enlace">
                        <FontAwesomeIcon icon={faClipboardCheck} aria-hidden="true" /> Checklist
                    </Link>
                </dd>
            </div>
            {avance.aviso && <div><dt>Plantilla</dt><dd>{avance.aviso}</dd></div>}
        </>
    );
};

/**
 * Procesos de contratación de un proyecto (la obra, la interventoría…), en un diálogo que abre
 * Parámetros → Proyectos. Un Administrador o Gestor los crea, edita, cambia de estado y elimina;
 * los demás solo los consultan. El backend aplica los mismos permisos y reglas.
 */
const ProcesosProyecto = ({ proyecto, user, onCerrar, onCambio }) => {
    const [procesos, setProcesos] = useState(null);
    const [error, setError] = useState(null);
    const [modalidades, setModalidades] = useState(null);
    const [entidades, setEntidades] = useState(null);
    const [fuentes, setFuentes] = useState(null);
    const [responsables, setResponsables] = useState(null);
    const [errorCatalogos, setErrorCatalogos] = useState(null);
    // null; { tipo: 'proceso', proceso: null | proceso }, { tipo: 'estado', proceso } o { tipo: 'contrato', proceso }.
    const [modo, setModo] = useState(null);
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    const retorno = useRef(null); // data-foco del control que recupera el foco al volver a la lista
    const turno = useRef(0);
    const turnoCatalogos = useRef(0);
    const edita = puedeEditarProcesos(user);

    // Solo vale la última respuesta, y solo si es de este proyecto: las tardías se descartan.
    const cargar = useCallback(async () => {
        const numero = ++turno.current;
        try {
            const { data } = await axios.get(`/proyectos/${proyecto.id}/procesos`);
            if (numero !== turno.current || String(data?.proyecto?.id) !== String(proyecto.id)) return;
            setProcesos(data.procesos ?? []);
            setError(null);
        } catch (e) {
            if (numero === turno.current) setError(mensajeApi(e));
        }
    }, [proyecto.id]);

    useEffect(() => {
        setProcesos(null);
        setError(null);
        setModo(null);
        cargar();
        return () => { turno.current += 1; };
    }, [cargar]);

    // Los catálogos se cargan al abrir y, con "Reintentar", se piden otra vez los que fallaron. Sin
    // modalidades no se crea ni edita; sin los demás el formulario conserva lo que el proceso tiene.
    const cargarCatalogos = useCallback(async (pendientes = CATALOGOS.map((c) => c.clave)) => {
        const numero = ++turnoCatalogos.current;
        setErrorCatalogos(null);
        const pedidos = CATALOGOS.filter((c) => pendientes.includes(c.clave));
        const respuestas = await Promise.allSettled(pedidos.map((c) => axios.get(c.ruta)));
        if (numero !== turnoCatalogos.current) return;
        pedidos.forEach((c, i) => {
            if (respuestas[i].status === 'fulfilled') c.guardar(respuestas[i].value.data, { setModalidades, setEntidades, setFuentes, setResponsables });
        });
        const fallo = respuestas.find((r) => r.status === 'rejected');
        if (fallo) setErrorCatalogos(`No se pudieron cargar las modalidades, entidades, fuentes o responsables: ${mensajeApi(fallo.reason)}`);
    }, []);

    useEffect(() => {
        cargarCatalogos();
        return () => { turnoCatalogos.current += 1; };
    }, [cargarCatalogos]);

    // El botón desaparece mientras se reintenta: el foco pasa al diálogo para no perderse.
    const reintentarCatalogos = () => {
        contenedor.current?.closest('[role="dialog"]')?.focus();
        const cargados = { modalidades, entidades, fuentes, responsables };
        cargarCatalogos(CATALOGOS.map((c) => c.clave).filter((clave) => cargados[clave] === null));
    };

    // Al volver a la lista, el foco regresa al botón que abrió el formulario (o al diálogo).
    useEffect(() => {
        if (modo || ocupado || !retorno.current) return;
        const destino = contenedor.current?.querySelector(`[data-foco="${retorno.current}"]:not(:disabled)`)
            ?? contenedor.current?.closest('[role="dialog"]');
        destino?.focus();
        retorno.current = null;
    }, [modo, ocupado, procesos]);

    const abrir = (nuevoModo, foco) => {
        retorno.current = foco;
        setModo(nuevoModo);
    };

    // Escape cierra primero el formulario abierto y, si no hay, el diálogo. Un clic en el fondo
    // no descarta un formulario a medio llenar.
    const alEscape = () => {
        if (ocupado) return;
        if (modo) setModo(null);
        else onCerrar();
    };
    const alFondo = () => {
        if (!ocupado && !modo) onCerrar();
    };

    // Otra persona cambió el proceso mientras estaba abierto: se avisa y se recarga la lista. El
    // formulario sigue abierto para copiar lo escrito; al reabrirlo trae lo último.
    const avisarDesactualizado = (e) => Promise.all([
        cargar(),
        Swal.fire({ icon: 'warning', titleText: 'No se guardó tu cambio', text: mensajeApi(e) }),
    ]);

    const guardar = (formulario) => ejecutar(async () => {
        const proceso = modo?.proceso ?? null;
        try {
            if (proceso) await axios.put(`/procesos/${proceso.id}`, datosProceso(formulario, proceso));
            else await axios.post(`/proyectos/${proyecto.id}/procesos`, datosProceso(formulario));
            setModo(null);
            await cargar();
            return null;
        } catch (e) {
            if (esEdicionDesactualizada(e)) {
                await avisarDesactualizado(e);
                return null;
            }
            const errores = erroresPorCampo(e, CAMPOS_PROCESO);
            if (!errores || errores.otros.length || !Object.keys(errores.campos).length) avisarError('No se pudo guardar el proceso', e);
            return errores?.campos ?? null;
        }
    });

    const cambiarEstado = (estado, observacion) => ejecutar(async () => {
        const { proceso } = modo;
        const aviso = avisoCambioEstado(proceso.estado, estado);
        if (aviso) {
            const { isConfirmed } = await Swal.fire({
                icon: 'warning',
                titleText: aviso.titulo,
                text: aviso.texto,
                showCancelButton: true,
                confirmButtonText: 'Sí, cambiar el estado',
                cancelButtonText: 'Cancelar',
            });
            if (!isConfirmed) return null;
        }
        try {
            await axios.post(`/procesos/${proceso.id}/estado`, datosEstado(proceso, estado, observacion));
            setModo(null);
            await cargar();
            return null;
        } catch (e) {
            if (esEdicionDesactualizada(e)) {
                await avisarDesactualizado(e);
                return null;
            }
            const errores = erroresPorCampo(e, ['estado', 'observacion']);
            if (!errores || errores.otros.length || !Object.keys(errores.campos).length) avisarError('No se pudo cambiar el estado', e);
            return errores?.campos ?? null;
        }
    });

    // El contrato se guardó (el editor ya mostró sus avisos): se cierra (si sigue abierto) y se
    // recarga la lista, que trae la cuenta de contratos y el estado, que pudo pasar a celebrado con
    // la propuesta del editor. `onCambio` avisa a quien abrió el diálogo: sus contratos cambiaron.
    const alGuardarContrato = (procesoId) => {
        setModo((actual) => modoTrasGuardarContrato(actual, procesoId));
        cargar();
        onCambio?.();
    };

    const eliminar = (proceso) => ejecutar(async () => {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            titleText: '¿Eliminar este proceso?',
            text: proceso.objeto,
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        retorno.current = 'nuevo';
        try {
            await axios.delete(`/procesos/${proceso.id}`);
        } catch (e) {
            // Con contratos vinculados el servidor lo rechaza y explica por qué.
            avisarError('No se pudo eliminar el proceso', e);
            retorno.current = `eliminar:${proceso.id}`;
        }
        await cargar();
    });

    const editandoProceso = modo?.tipo === 'proceso';

    return (
        <>
            <Dialogo titulo={proyecto.nombre} subtitulo="Procesos de contratación" onCerrar={() => { if (!ocupado) onCerrar(); }} onEscape={alEscape} onFondo={alFondo}>
                <div className="ct-seccion" ref={contenedor}>
                    {errorCatalogos && (
                        <p className="pl-aviso-suave" role="status">
                            {errorCatalogos} <button type="button" className="gp-boton" onClick={reintentarCatalogos}>Reintentar</button>
                        </p>
                    )}

                    {editandoProceso ? (
                        <FormularioProceso
                            proceso={modo.proceso}
                            fuenteIds={proyecto.fuente_ids ?? []}
                            modalidades={modalidades}
                            entidades={entidades}
                            fuentes={fuentes}
                            responsables={responsables}
                            guardando={ocupado}
                            onGuardar={guardar}
                            onCancelar={() => setModo(null)}
                        />
                    ) : (
                        <>
                            <div className="ct-barra">
                                <p className="pl-ayuda">
                                    {edita
                                        ? 'Un proyecto puede tener varios procesos, por ejemplo la obra y la interventoría.'
                                        : 'Solo un Administrador o Gestor puede registrar o cambiar procesos.'}
                                </p>
                                {edita && (
                                    <button
                                        type="button"
                                        className="gp-boton gp-boton-primario"
                                        data-foco="nuevo"
                                        disabled={ocupado || procesos === null || modalidades === null}
                                        onClick={() => abrir({ tipo: 'proceso', proceso: null }, 'nuevo')}
                                    >
                                        <FontAwesomeIcon icon={faPlus} /> Nuevo proceso
                                    </button>
                                )}
                            </div>

                            {error && (
                                <p className="pl-error" role="alert">
                                    {error} <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>
                                </p>
                            )}
                            {procesos === null && !error && <p className="gp-vacio" role="status">Cargando procesos…</p>}
                            {procesos !== null && procesos.length === 0 && (
                                <p className="gp-vacio">
                                    <FontAwesomeIcon icon={faGavel} aria-hidden="true" /> Este proyecto aún no tiene procesos de contratación.
                                </p>
                            )}

                            {procesos !== null && procesos.length > 0 && (
                                <ul className="pl-lista" aria-label="Procesos del proyecto">
                                    {procesos.map((p) => (
                                        <li key={p.id} className="gp-panel ct-proceso">
                                            <div className="ct-proceso-cabecera">
                                                <p className="ct-proceso-objeto">{p.objeto || 'Sin objeto'}</p>
                                                <span className={`ct-estado ct-estado-${tonoEstado(p.estado)}`}>{etiquetaEstadoProceso(p.estado)}</span>
                                            </div>
                                            <dl className="pl-datos">
                                                <div>
                                                    <dt>Modalidad</dt>
                                                    <dd>{p.modalidad ? `${p.modalidad.nombre}${p.modalidad.activo ? '' : ' (inactiva)'}` : '—'}</dd>
                                                </div>
                                                <div><dt>Plataforma</dt><dd>{etiquetaPlataforma(p.plataforma)}</dd></div>
                                                <div><dt>Número</dt><dd><NumeroProceso proceso={p} /></dd></div>
                                                <div><dt>Entidad contratante</dt><dd>{p.entidad_contratante?.nombre ?? '—'}</dd></div>
                                                <div><dt>Valor</dt><dd className="ct-valor">{textoValor(p.monto)}</dd></div>
                                                <div><dt>Fuente de financiación</dt><dd>{p.fuente?.nombre ?? '—'}</dd></div>
                                                <div><dt>Responsable</dt><dd>{p.responsable?.nombre ?? '—'}</dd></div>
                                                <div><dt>Apertura</dt><dd>{textoFecha(p.fecha_apertura)}</dd></div>
                                                <div><dt>Cierre</dt><dd>{textoFecha(p.fecha_cierre)}</dd></div>
                                                <div><dt>Contratos vinculados</dt><dd>{textoContratos(p.contratos)}</dd></div>
                                                <ExpedienteProceso proceso={p} />
                                            </dl>
                                            {p.observaciones && <p className="pl-ayuda">{p.observaciones}</p>}
                                            {modo?.tipo === 'estado' && modo.proceso.id === p.id ? (
                                                <FormularioEstado proceso={p} guardando={ocupado} onGuardar={cambiarEstado} onCancelar={() => setModo(null)} />
                                            ) : edita && (
                                                <div className="pl-tarjeta-acciones">
                                                    <button type="button" className="gp-boton" data-foco={`editar:${p.id}`} disabled={ocupado || modo !== null || modalidades === null}
                                                        onClick={() => abrir({ tipo: 'proceso', proceso: p }, `editar:${p.id}`)}>
                                                        <FontAwesomeIcon icon={faPen} /> Editar
                                                    </button>
                                                    <button type="button" className="gp-boton" data-foco={`estado:${p.id}`} disabled={ocupado || modo !== null}
                                                        onClick={() => abrir({ tipo: 'estado', proceso: p }, `estado:${p.id}`)}>
                                                        <FontAwesomeIcon icon={faArrowRightArrowLeft} /> Cambiar estado
                                                    </button>
                                                    {puedeRegistrarContrato(user, p) && (
                                                        <button type="button" className="gp-boton" data-foco={`contrato:${p.id}`} disabled={ocupado || modo !== null}
                                                            onClick={() => abrir({ tipo: 'contrato', proceso: p }, `contrato:${p.id}`)}>
                                                            <FontAwesomeIcon icon={faFileSignature} /> Registrar contrato
                                                        </button>
                                                    )}
                                                    <button type="button" className="gp-boton" data-foco={`eliminar:${p.id}`} disabled={ocupado || modo !== null} onClick={() => eliminar(p)}>
                                                        <FontAwesomeIcon icon={faTrash} /> Eliminar
                                                    </button>
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </>
                    )}
                </div>
            </Dialogo>
            {/* Hermano y posterior al diálogo de procesos: queda por encima y su foco no lo atrapa aquel. */}
            {modo?.tipo === 'contrato' && (
                <DialogoContrato
                    proyecto={proyecto}
                    proceso={modo.proceso}
                    onGuardado={() => alGuardarContrato(modo.proceso.id)}
                    onCancelar={() => setModo(null)}
                />
            )}
        </>
    );
};

export default ProcesosProyecto;
