import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowRightArrowLeft, faClipboardCheck, faFileSignature, faGavel, faPen, faPlus, faTrash, faXmark } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { Link } from 'react-router-dom';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
import { rutaExpedienteContratacion } from '../utils/navegacion';
import { mensajeApi } from '../utils/plantillasChecklist';
import {
    avanceExpediente, etiquetaEstadoProceso, etiquetaPlataforma, modoTrasGuardarContrato, puedeEditarProcesos, puedeRegistrarContrato,
    textoContratos, textoFecha, textoValor, tonoEstado,
} from '../utils/contratacion';
import ChipsAvisosProceso from './ChipsAvisosProceso';
import {
    DialogoContrato, FormularioEstado, FormularioProceso, NumeroProceso, cambiarEstadoProceso, guardarProceso, useCatalogosProceso,
} from './FormulariosProceso';
import useDialogoModal from './useDialogoModal';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';

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
    const {
        modalidades, entidades, fuentes, responsables, errorCatalogos, cargarCatalogos, pendientes: catalogosPendientes,
    } = useCatalogosProceso();
    // null; { tipo: 'proceso', proceso: null | proceso }, { tipo: 'estado', proceso } o { tipo: 'contrato', proceso }.
    const [modo, setModo] = useState(null);
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    const retorno = useRef(null); // data-foco del control que recupera el foco al volver a la lista
    const turno = useRef(0);
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

    // Los catálogos se cargan al abrir (useCatalogosProceso) y, con "Reintentar", se piden otra vez
    // los que fallaron. El botón desaparece mientras se reintenta: el foco pasa al diálogo para no perderse.
    const reintentarCatalogos = () => {
        contenedor.current?.closest('[role="dialog"]')?.focus();
        cargarCatalogos(catalogosPendientes);
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

    const guardar = (formulario) => ejecutar(() => guardarProceso({
        proceso: modo?.proceso ?? null,
        proyectoId: proyecto.id,
        formulario,
        onGuardado: async () => {
            setModo(null);
            await cargar();
        },
        onDesactualizado: avisarDesactualizado,
    }));

    const cambiarEstado = (estado, observacion) => ejecutar(() => cambiarEstadoProceso({
        proceso: modo.proceso,
        estado,
        observacion,
        onGuardado: async () => {
            setModo(null);
            await cargar();
        },
        onDesactualizado: avisarDesactualizado,
    }));

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
                                            <ChipsAvisosProceso avisos={p.avisos} />
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
