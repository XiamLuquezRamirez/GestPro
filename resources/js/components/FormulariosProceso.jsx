import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowUpRightFromSquare, faFileSignature } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
import { mensajeApi } from '../utils/plantillasChecklist';
import {
    CAMPOS_PROCESO, ESTADOS_PROCESO, MAXIMO_OBSERVACION, MAXIMO_OBSERVACIONES_PROCESO, PLATAFORMAS, avisoCambioEstado, datosEstado, datosProceso,
    erroresPorCampo, esEdicionDesactualizada, etiquetaEstadoProceso, formularioProceso, numeroProceso, opcionesConActual, opcionesFuenteProceso,
    ordenarCatalogo, validarProceso, valorEnEdicion,
} from '../utils/contratacion';
import ContratoEditor from './contratos/ContratoEditor';
import useDialogoModal from './useDialogoModal';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';

// Lo que comparten el diálogo de procesos del proyecto (ProcesosProyecto) y la página del proceso
// (ExpedienteContratacion): el formulario del proceso, el cambio de estado, el registro de un
// contrato con la precarga del proceso, los catálogos del formulario y el guardado.

// Catálogos del formulario: su ruta y cómo se guarda cada respuesta.
const comoLista = (datos) => (Array.isArray(datos) ? datos : []);
export const CATALOGOS = [
    { clave: 'modalidades', ruta: '/modalidades', guardar: (d, { setModalidades }) => setModalidades(ordenarCatalogo(d)) },
    { clave: 'entidades', ruta: '/entidades', guardar: (d, { setEntidades }) => setEntidades(comoLista(d)) },
    { clave: 'fuentes', ruta: '/fuentesFinanciacion', guardar: (d, { setFuentes }) => setFuentes(comoLista(d)) },
    { clave: 'responsables', ruta: '/responsables', guardar: (d, { setResponsables }) => setResponsables(comoLista(d)) },
];

/**
 * Los catálogos del formulario del proceso, pedidos al montar. `cargarCatalogos(pendientes)` pide
 * otra vez los indicados (por defecto, todos); cada uno queda en null mientras no carga. Sin
 * modalidades no se crea ni edita; sin los demás el formulario conserva lo que el proceso tiene.
 */
export const useCatalogosProceso = () => {
    const [modalidades, setModalidades] = useState(null);
    const [entidades, setEntidades] = useState(null);
    const [fuentes, setFuentes] = useState(null);
    const [responsables, setResponsables] = useState(null);
    const [errorCatalogos, setErrorCatalogos] = useState(null);
    const turnoCatalogos = useRef(0);

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

    const catalogos = { modalidades, entidades, fuentes, responsables };
    // Las claves de los que aún no cargaron: lo que se pide otra vez con "Reintentar".
    const pendientes = CATALOGOS.map((c) => c.clave).filter((clave) => catalogos[clave] === null);

    return { ...catalogos, errorCatalogos, cargarCatalogos, pendientes };
};

/** Aviso de que otra persona cambió el proceso mientras se editaba: no se guardó el cambio. */
export const avisarProcesoDesactualizado = (e) => Swal.fire({ icon: 'warning', titleText: 'No se guardó tu cambio', text: mensajeApi(e) });

/**
 * Crea (sin `proceso`, en el proyecto `proyectoId`) o edita un proceso. Tras guardar llama a
 * `onGuardado`; si otra persona lo cambió entretanto, a `onDesactualizado(error)`. Devuelve los
 * errores por campo del servidor (para el formulario) o null; los demás errores se avisan.
 */
export const guardarProceso = async ({ proceso, proyectoId, formulario, onGuardado, onDesactualizado }) => {
    try {
        if (proceso) await axios.put(`/procesos/${proceso.id}`, datosProceso(formulario, proceso));
        else await axios.post(`/proyectos/${proyectoId}/procesos`, datosProceso(formulario));
        await onGuardado();
        return null;
    } catch (e) {
        if (esEdicionDesactualizada(e)) {
            await onDesactualizado(e);
            return null;
        }
        const errores = erroresPorCampo(e, CAMPOS_PROCESO);
        if (!errores || errores.otros.length || !Object.keys(errores.campos).length) avisarError('No se pudo guardar el proceso', e);
        return errores?.campos ?? null;
    }
};

/**
 * Cambia el estado de un proceso. Si el cambio es inusual (sale de un cierre o vuelve atrás en el
 * flujo) pide confirmación antes; lo demás, como `guardarProceso`.
 */
export const cambiarEstadoProceso = async ({ proceso, estado, observacion, onGuardado, onDesactualizado }) => {
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
        await onGuardado();
        return null;
    } catch (e) {
        if (esEdicionDesactualizada(e)) {
            await onDesactualizado(e);
            return null;
        }
        const errores = erroresPorCampo(e, ['estado', 'observacion']);
        if (!errores || errores.otros.length || !Object.keys(errores.campos).length) avisarError('No se pudo cambiar el estado', e);
        return errores?.campos ?? null;
    }
};

// Un contrato nuevo no tiene pestañas que recargar: el editor lo cierra quien lo abrió al guardar.
const sinRecargar = async () => null;

/**
 * El editor de un contrato nuevo del proyecto, con ese proceso ya elegido (y su precarga), en un
 * diálogo propio. Los controles de cerrar son los del editor (Cancelar) y la "×"; Escape cancela
 * y un clic en el fondo no descarta lo escrito. Mientras el editor guarda, ni Escape ni la "×" lo
 * cierran.
 */
export const DialogoContrato = ({ proyecto, proceso, onGuardado, onCancelar }) => {
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

export const Campo = ({ etiqueta, error, idError, ancho, children }) => (
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
export const FormularioProceso = ({ proceso, fuenteIds, modalidades, entidades, fuentes, responsables, guardando, onGuardar, onCancelar }) => {
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
export const FormularioEstado = ({ proceso, guardando, onGuardar, onCancelar }) => {
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

/** El número del proceso; con enlace web, abre el proceso (SECOP) en otra pestaña. */
export const NumeroProceso = ({ proceso }) => {
    const { texto, enlace } = numeroProceso(proceso);
    if (!enlace) return texto;
    return (
        <a href={enlace} target="_blank" rel="noopener noreferrer" className="ct-enlace">
            {texto} <FontAwesomeIcon icon={faArrowUpRightFromSquare} aria-hidden="true" />
            <span className="ct-oculto"> (se abre en otra pestaña)</span>
        </a>
    );
};
