import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { faArrowRightArrowLeft, faArrowUpRightFromSquare, faFileSignature, faPen } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    etiquetaPlataforma, modoTrasGuardarContrato, puedeEditarProcesos, textoFecha, textoValor,
} from '../utils/contratacion';
import {
    abrirFormularioDetalle, enlaceProceso, filaContratoProceso, fuentesDelDetalle, procesoDelFormulario, puedeRegistrarDesdeDetalle, textoContratosProceso,
} from '../utils/expedienteContratacion';
import {
    DialogoContrato, FormularioEstado, FormularioProceso, avisarProcesoDesactualizado, cambiarEstadoProceso,
    guardarProceso, useCatalogosProceso,
} from './FormulariosProceso';
import useOcupado from './useOcupado';

/**
 * El formulario del proceso con sus catálogos, que se piden al abrirlo. Sin modalidades no se
 * edita: se muestra el error con "Reintentar". `fuenteIds`: las del proyecto, para agrupar "Fuentes del proyecto".
 */
const EditorProceso = ({ proceso, fuenteIds, guardando, onGuardar, onCancelar }) => {
    const { modalidades, entidades, fuentes, responsables, errorCatalogos, cargarCatalogos, pendientes } = useCatalogosProceso();

    if (modalidades === null) {
        return errorCatalogos ? (
            <div className="pl-error" role="alert">
                {errorCatalogos}{' '}
                <button type="button" className="gp-boton" onClick={() => cargarCatalogos(pendientes)}>Reintentar</button>{' '}
                <button type="button" className="gp-boton" onClick={onCancelar}>Cancelar</button>
            </div>
        ) : <p className="gp-vacio" role="status">Cargando el formulario…</p>;
    }

    return (
        <>
            {errorCatalogos && (
                <p className="pl-aviso-suave" role="status">
                    {errorCatalogos} <button type="button" className="gp-boton" onClick={() => cargarCatalogos(pendientes)}>Reintentar</button>
                </p>
            )}
            <FormularioProceso
                proceso={proceso}
                fuenteIds={fuenteIds}
                modalidades={modalidades}
                entidades={entidades}
                fuentes={fuentes}
                responsables={responsables}
                guardando={guardando}
                onGuardar={onGuardar}
                onCancelar={onCancelar}
            />
        </>
    );
};

const Dato = ({ titulo, children }) => <div><dt>{titulo}</dt><dd>{children}</dd></div>;

/**
 * Los bloques "Proceso" y "Contratos" de la página del proceso (GET /procesos/{id}/detalle), tenga
 * o no expediente. Quien edita procesos puede editarlo, cambiar su estado y, si el estado lo
 * admite, registrar un contrato con la precarga del proceso; tras guardar, `onCambio` recarga la
 * página (detalle y expediente). Usa los mismos formularios que el diálogo de procesos del proyecto.
 */
const ProcesoDetalle = ({ detalle, error, user, onReintentar, onCambio }) => {
    // null o { tipo: 'editar' | 'estado' | 'contrato', proceso } (abrirFormularioDetalle; la forma de
    // modoTrasGuardarContrato): el proceso queda congelado al abrir, con su `actualizado`.
    const [modo, setModo] = useState(null);
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    const retorno = useRef(null); // data-foco del botón que recupera el foco al cerrar el formulario
    const edita = puedeEditarProcesos(user);

    useEffect(() => {
        if (modo || ocupado || !retorno.current) return;
        contenedor.current?.querySelector(`[data-foco="${retorno.current}"]:not(:disabled)`)?.focus();
        retorno.current = null;
    }, [modo, ocupado, detalle]);

    if (!detalle) {
        return (
            <section className="exc-proceso" aria-label="Datos del proceso">
                {error ? (
                    <div className="exv-aviso" role="alert">
                        No se pudieron cargar los datos y contratos del proceso. {error.mensaje}{' '}
                        {error.reintentable && <button type="button" className="exv-btn" onClick={onReintentar}>Reintentar</button>}
                    </div>
                ) : <p className="gp-vacio" role="status">Cargando los datos del proceso…</p>}
            </section>
        );
    }

    const { proceso, contratos, proyecto } = detalle;
    const enlace = enlaceProceso(proceso);
    const registra = puedeRegistrarDesdeDetalle(user, detalle);

    const abrir = (nuevoModo, foco) => {
        retorno.current = foco;
        setModo(nuevoModo);
    };

    const alGuardar = async () => {
        setModo(null);
        await onCambio();
    };
    // Otra persona cambió el proceso: se avisa y se recarga. El formulario sigue abierto para copiar
    // lo escrito, con el proceso que se congeló al abrirlo: otro "Guardar" vuelve a dar 409 en vez de
    // pisar el cambio ajeno; al cerrarlo y reabrirlo trae lo último.
    const alDesactualizar = (e) => Promise.all([onCambio(), avisarProcesoDesactualizado(e)]);

    const guardar = (formulario) => ejecutar(() => guardarProceso({
        proceso: procesoDelFormulario(modo, 'editar'), formulario, onGuardado: alGuardar, onDesactualizado: alDesactualizar,
    }));
    const cambiarEstado = (estado, observacion) => ejecutar(() => cambiarEstadoProceso({
        proceso: procesoDelFormulario(modo, 'estado'), estado, observacion, onGuardado: alGuardar, onDesactualizado: alDesactualizar,
    }));
    // El contrato se guardó (el editor ya avisó): se cierra y se recarga, porque el proceso pudo pasar a celebrado.
    const alGuardarContrato = () => {
        setModo((actual) => modoTrasGuardarContrato(actual, proceso.id));
        onCambio();
    };

    return (
        <div className="exc-detalle" ref={contenedor}>
            {error && (
                <div className="exv-aviso" role="alert">
                    No se pudieron actualizar los datos del proceso. {error.mensaje}{' '}
                    {error.reintentable && <button type="button" className="exv-btn" onClick={onReintentar}>Reintentar</button>}
                </div>
            )}

            <section className="exc-bloque" aria-labelledby="exc-proceso-titulo">
                <header className="exc-bloque-cabecera">
                    <h3 id="exc-proceso-titulo">Proceso</h3>
                    {edita && !modo && (
                        <div className="pl-tarjeta-acciones exv-no-imprimir">
                            <button type="button" className="gp-boton" data-foco="editar" disabled={ocupado} onClick={() => abrir(abrirFormularioDetalle('editar', proceso), 'editar')}>
                                <FontAwesomeIcon icon={faPen} aria-hidden="true" /> Editar proceso
                            </button>
                            <button type="button" className="gp-boton" data-foco="estado" disabled={ocupado} onClick={() => abrir(abrirFormularioDetalle('estado', proceso), 'estado')}>
                                <FontAwesomeIcon icon={faArrowRightArrowLeft} aria-hidden="true" /> Cambiar estado
                            </button>
                        </div>
                    )}
                </header>

                {modo?.tipo === 'editar' ? (
                    <EditorProceso proceso={modo.proceso} fuenteIds={fuentesDelDetalle(detalle)} guardando={ocupado} onGuardar={guardar} onCancelar={() => setModo(null)} />
                ) : (
                    <>
                        <dl className="pl-datos exc-datos">
                            <Dato titulo="Número">{String(proceso.codigo_proceso ?? '').trim() || '—'}</Dato>
                            <Dato titulo="Monto"><span className="ct-valor">{textoValor(proceso.monto)}</span></Dato>
                            <Dato titulo="Fuente de financiación">{proceso.fuente?.nombre ?? '—'}</Dato>
                            <Dato titulo="Responsable">{proceso.responsable?.nombre ?? '—'}</Dato>
                            <Dato titulo="Apertura">{textoFecha(proceso.fecha_apertura)}</Dato>
                            <Dato titulo="Cierre">{textoFecha(proceso.fecha_cierre)}</Dato>
                            <Dato titulo="Plataforma">
                                {etiquetaPlataforma(proceso.plataforma)}
                                {enlace && (
                                    <>
                                        {' · '}
                                        <a href={enlace.enlace} target="_blank" rel="noopener noreferrer" className="ct-enlace">
                                            {enlace.texto} <FontAwesomeIcon icon={faArrowUpRightFromSquare} aria-hidden="true" />
                                            <span className="ct-oculto"> (se abre en otra pestaña)</span>
                                        </a>
                                    </>
                                )}
                            </Dato>
                            <Dato titulo="Entidad contratante">{proceso.entidad_contratante?.nombre ?? '—'}</Dato>
                        </dl>
                        <div className="exc-observaciones">
                            <b>Observaciones</b>
                            <p>{proceso.observaciones || 'Sin observaciones.'}</p>
                        </div>
                        {modo?.tipo === 'estado' && (
                            <FormularioEstado proceso={modo.proceso} guardando={ocupado} onGuardar={cambiarEstado} onCancelar={() => setModo(null)} />
                        )}
                    </>
                )}
            </section>

            <section className="exc-bloque" aria-labelledby="exc-contratos-titulo">
                <header className="exc-bloque-cabecera">
                    <h3 id="exc-contratos-titulo">Contratos <span className="exc-cuenta">· {textoContratosProceso(contratos)}</span></h3>
                    {registra && (
                        <button
                            type="button"
                            className="gp-boton exv-no-imprimir"
                            data-foco="contrato"
                            disabled={ocupado || modo !== null}
                            onClick={() => abrir(abrirFormularioDetalle('contrato', proceso), 'contrato')}
                        >
                            <FontAwesomeIcon icon={faFileSignature} aria-hidden="true" /> Registrar contrato
                        </button>
                    )}
                </header>
                {contratos.length > 0 && (
                    <ul className="exc-contratos">
                        {contratos.map((c) => {
                            const fila = filaContratoProceso(c);
                            return (
                                <li key={fila.id} className={fila.anulado ? 'anulado' : undefined}>
                                    <Link to={fila.ruta} className="ct-enlace exc-contrato-numero">{fila.numero}</Link>
                                    <span className="exc-contrato-objeto">{fila.objeto}<small>{fila.contratista}</small></span>
                                    <span className={`ct-estado ct-estado-${fila.tono}`}>{fila.estado}</span>
                                    <span className="ct-valor exc-contrato-valor">{fila.valor}</span>
                                </li>
                            );
                        })}
                    </ul>
                )}
                {edita && !detalle.admite_contratos && (
                    <p className="pl-ayuda">Podrás registrar contratos cuando el proceso esté adjudicado o celebrado.</p>
                )}
            </section>

            {modo?.tipo === 'contrato' && proyecto && (
                <DialogoContrato
                    proyecto={proyecto}
                    proceso={modo.proceso}
                    onGuardado={alGuardarContrato}
                    onCancelar={() => setModo(null)}
                />
            )}
        </div>
    );
};

export default ProcesoDetalle;
