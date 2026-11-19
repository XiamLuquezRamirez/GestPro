import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import Swal from 'sweetalert2';
import { faArrowLeft, faArrowsRotate, faClockRotateLeft, faLock, faPlus } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { ChecklistPorEtapa } from './ExpedienteChecklist';
import ExpedienteHistorial from './ExpedienteHistorial';
import useOcupado from './useOcupado';
import {
    contarOpcionales, motivoNoEliminarPropio, plantillasConVersion, requiereAprobacionNoAplica, textoOpcionales,
} from '../utils/expedienteChecklist';
import {
    alternarPlantillaNueva, avanceEtapas, avisoIncompatiblesProceso, conAvance, datosPropioContratacion, errorDeProceso,
    esIdProceso, htmlPendientesCierre, seleccionInicial, textoEstadoExpediente, textoSinPlantillas,
} from '../utils/expedienteContratacion';
import { etiquetaEstadoProceso, numeroProceso, tonoEstado } from '../utils/contratacion';
import { RUTA_PLANTILLAS, rutaFichaProyecto } from '../utils/navegacion';
import { avisarError } from '../utils/avisosPlantillas';
import {
    etiquetaAplicarVersion, htmlVistaPrevia, plantillasConVersionNueva, textoVersionNueva,
} from '../utils/versionesExpediente';
import '../../css/ExpedienteViabilizacion.css';
import '../../css/Contratacion.css';
import '../../css/ExpedienteContratacion.css';

const ROLES_EDICION = ['Administrador', 'Gestor'];
const ID_HISTORIAL = 'exc-historial-expediente';

/** Barra de avance con sus cifras: "3 / 8 requisitos cumplidos" y el porcentaje, por separado. */
const Medidor = ({ titulo, cumplidos, total, porcentaje, nota }) => (
    <div className="exv-medidor">
        <div className="exv-medidor-top">
            <b>
                {titulo && <>{titulo} · </>}
                <span>{cumplidos}</span> / {total} {titulo ? 'cumplidos' : 'requisitos cumplidos'}
                {nota && <small className="exv-medidor-opcionales"> · {nota}</small>}
            </b>
            <em>{porcentaje}%</em>
        </div>
        <div
            className="exv-pista"
            role="progressbar"
            aria-label={titulo ? `Avance de ${titulo}` : 'Avance del expediente'}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={porcentaje}
        >
            <div className={`exv-relleno${porcentaje === 100 && total > 0 ? ' completo' : ''}`} style={{ width: `${porcentaje}%` }} />
        </div>
    </div>
);

/** Página con el botón de volver: la usan la carga, los errores y el expediente. */
const Pagina = ({ children }) => {
    const navigate = useNavigate();
    return (
        <div className="exv-pagina">
            <div className="exv-contenido">
                <button type="button" className="exv-btn exv-no-imprimir exc-volver" onClick={() => navigate(-1)}>
                    <FontAwesomeIcon icon={faArrowLeft} /> Volver
                </button>
                {children}
            </div>
        </div>
    );
};

/** Sin expediente: las plantillas compatibles para elegir (la primera elegida es la principal) y crearlo. */
const SinExpediente = ({ plantillas, puedeEditar, esAdministrador, seleccion, onCambiar, ocupado, onCrear }) => {
    if (plantillas.length === 0) {
        return (
            <div className="exv-vacio">
                <b>Este proceso aún no tiene expediente.</b>
                <p>{textoSinPlantillas}</p>
                {esAdministrador && (
                    <p><Link to={RUTA_PLANTILLAS} className="ct-enlace">Ir a Plantillas de checklist</Link></p>
                )}
            </div>
        );
    }
    return (
        <div className="exv-vacio exc-crear">
            <b>Este proceso aún no tiene expediente.</b>
            <p>
                {puedeEditar
                    ? 'Elige las plantillas de su checklist. La primera que elijas es la principal.'
                    : 'Un Administrador o Gestor debe crearlo. Estas son las plantillas que le corresponden:'}
            </p>
            <fieldset className="exc-plantillas" disabled={!puedeEditar || ocupado}>
                <legend>Plantillas compatibles</legend>
                {plantillas.map((p) => {
                    const posicion = seleccion.indexOf(p.id);
                    return (
                        <label key={p.id}>
                            <input type="checkbox" checked={posicion !== -1} onChange={() => onCambiar(alternarPlantillaNueva(seleccion, p.id))} />
                            <span>
                                <b>{p.nombre}</b> (v{p.version.numero}) · {p.requisitos === 1 ? '1 requisito' : `${p.requisitos} requisitos`}
                                {posicion === 0 && seleccion.length > 1 && <span className="exv-estado exc-principal">Principal</span>}
                                {p.descripcion && <small>{p.descripcion}</small>}
                            </span>
                        </label>
                    );
                })}
            </fieldset>
            {puedeEditar && (
                <button type="button" className="exv-btn exv-btn-primario" onClick={onCrear} disabled={ocupado || seleccion.length === 0}>
                    <FontAwesomeIcon icon={faPlus} /> Crear expediente
                </button>
            )}
        </div>
    );
};

/** El expediente de un proceso con id válido. */
const Expediente = ({ procesoId, user }) => {
    // GET /procesos/{p}/plantillas-compatibles: el proceso, su expediente_id y las plantillas que le corresponden.
    const [disponibles, setDisponibles] = useState(null);
    const [expediente, setExpediente] = useState(null);
    // { mensaje, reintentable }. Con la página ya pintada, se avisa encima sin quitarla.
    const [error, setError] = useState(null);
    const [seleccion, setSeleccion] = useState(null);
    const [etapas, setEtapas] = useState(null);
    const [responsables, setResponsables] = useState([]);
    const [historialAbierto, setHistorialAbierto] = useState(false);
    // Sube con cada cambio que el servidor ya registró: el historial abierto se vuelve a pedir.
    const [revision, setRevision] = useState(0);
    const nuevaRevision = useCallback(() => setRevision((r) => r + 1), []);
    const [ocupado, ejecutar] = useOcupado();

    const puedeEditar = ROLES_EDICION.includes(user?.rol);
    const editable = puedeEditar && expediente?.estado === 'borrador';

    // Turno de las cargas completas: solo vale la última, y ninguna que empezó antes de que una
    // acción pusiera el expediente completo. Desmontarse también lo sube.
    const turno = useRef(0);
    const montado = useRef(true);
    useEffect(() => {
        montado.current = true;
        return () => { montado.current = false; turno.current += 1; };
    }, []);

    const ponerExpediente = useCallback((data) => {
        turno.current += 1;
        setExpediente(data);
        nuevaRevision();
    }, [nuevaRevision]);

    const cargar = useCallback(async () => {
        const numero = ++turno.current;
        const alDia = () => numero === turno.current;
        setError(null);
        let datos;
        try {
            ({ data: datos } = await axios.get(`/procesos/${procesoId}/plantillas-compatibles`));
        } catch (e) {
            if (alDia()) setError(errorDeProceso(e));
            return;
        }
        if (!alDia()) return;
        let datosExpediente = null;
        if (datos.expediente_id) {
            try {
                ({ data: datosExpediente } = await axios.get(`/expedientes/${datos.expediente_id}`));
            } catch (e) {
                // Un 404 aquí es del expediente (otra persona lo eliminó), no del proceso: se reintenta.
                if (alDia()) setError(e?.response?.status === 404 ? { mensaje: 'El expediente del proceso ya no existe.', reintentable: true } : errorDeProceso(e));
                return;
            }
            if (!alDia()) return;
        }
        setDisponibles(datos);
        setExpediente(datosExpediente);
        // Lo elegido se conserva entre recargas; la primera vez, si hay una sola plantilla, va elegida.
        setSeleccion((prev) => prev ?? seleccionInicial(datos.plantillas));
        nuevaRevision();
    }, [procesoId, nuevaRevision]);

    useEffect(() => { cargar(); }, [cargar]);

    // Para el selector de etapa de los propios. Sin la lista, solo se ofrece la que el propio ya tiene.
    useEffect(() => {
        let vigente = true;
        axios.get('/etapasContratacion')
            .then(({ data }) => { if (vigente) setEtapas(Array.isArray(data) ? data : []); })
            .catch(() => {});
        return () => { vigente = false; };
    }, []);

    // Sin la lista, el checklist igual funciona: solo no se puede elegir responsable.
    useEffect(() => {
        let vigente = true;
        axios.get('/responsables')
            .then(({ data }) => { if (vigente) setResponsables(data); })
            .catch(() => { if (vigente) setResponsables([]); });
        return () => { vigente = false; };
    }, []);

    // Recarga tras un error de una acción: la fila o el expediente pudieron cambiar en el servidor.
    const recargar = () => { cargar(); };

    // Los avisos de duplicados cambian cuando una fila deja o vuelve a aplicar: se piden aparte.
    // Solo vale el último GET, y ninguno anterior a la última vez que se puso el expediente completo.
    const peticionAvisos = useRef(0);
    const refrescarAvisos = async (id) => {
        const numero = ++peticionAvisos.current;
        const base = turno.current;
        try {
            const { data } = await axios.get(`/expedientes/${id}`);
            if (numero !== peticionAvisos.current || base !== turno.current) return;
            setExpediente((prev) => (prev?.id === id
                ? { ...prev, avisos_duplicados: data.avisos_duplicados, avisos_duplicados_total: data.avisos_duplicados_total }
                : prev));
        } catch {
            // En silencio: los avisos quedan como estaban hasta la próxima carga.
        }
    };

    // pintarFila solo la muestra (cambio optimista o su reversión); las demás ponen lo que
    // respondió el servidor, que ya quedó en el historial. `datos` trae progreso y avance por etapa.
    const pintarFila = (fila, datos) => setExpediente((prev) => (
        prev?.requisitos.some((f) => f.id === fila.id)
            ? { ...conAvance(prev, datos), requisitos: prev.requisitos.map((f) => (f.id === fila.id ? fila : f)) }
            : prev
    ));

    const reemplazarFila = (fila, datos) => {
        pintarFila(fila, datos);
        nuevaRevision();
    };

    // Un propio nuevo va al final: el backend ordena los propios después de toda plantilla, y la
    // agrupación lo pone en su etapa.
    const agregarFila = (expedienteId, fila, datos) => {
        setExpediente((prev) => (prev?.id === expedienteId ? { ...conAvance(prev, datos), requisitos: [...prev.requisitos, fila] } : prev));
        nuevaRevision();
    };

    const quitarFila = (fila, datos) => {
        setExpediente((prev) => (
            prev?.requisitos.some((f) => f.id === fila.id)
                ? { ...conAvance(prev, datos), requisitos: prev.requisitos.filter((f) => f.id !== fila.id) }
                : prev
        ));
        nuevaRevision();
    };

    const urlFila = (fila) => `/expedientes/${expediente.id}/requisitos/${fila.id}`;

    const marcar = async (fila, cumplido) => {
        pintarFila({ ...fila, cumplido, estado: cumplido ? 'cumplido' : 'pendiente' });
        try {
            const { data } = await axios.patch(urlFila(fila), { cumplido });
            reemplazarFila(data.requisito, data);
        } catch (e) {
            pintarFila(fila);
            avisarError('No se pudo actualizar el requisito', e);
        }
    };

    const guardarObservacion = async (fila, observacion) => {
        try {
            const { data } = await axios.patch(urlFila(fila), { observacion });
            reemplazarFila(data.requisito, data);
        } catch (e) {
            avisarError('No se pudo guardar la observación', e);
        }
    };

    const asignarResponsable = async (fila, responsableId) => {
        pintarFila({ ...fila, responsable: responsables.find((r) => r.id === responsableId) ?? null });
        try {
            const { data } = await axios.patch(urlFila(fila), { responsable_id: responsableId });
            reemplazarFila(data.requisito, data);
        } catch (e) {
            pintarFila(fila);
            avisarError('No se pudo asignar el responsable', e);
        }
    };

    const subirSoporte = async (fila, archivo) => {
        const formulario = new FormData();
        formulario.append('archivo', archivo);
        try {
            const { data } = await axios.post(`${urlFila(fila)}/soporte`, formulario);
            reemplazarFila(data.requisito, data);
        } catch (e) {
            avisarError('No se pudo adjuntar el soporte', e);
        }
    };

    const descargar = async (ruta, nombre) => {
        try {
            // Con axios, para enviar el JWT: un enlace directo no lleva la cabecera.
            const { data } = await axios.get(ruta, { responseType: 'blob' });
            const url = URL.createObjectURL(data);
            const enlace = document.createElement('a');
            enlace.href = url;
            enlace.download = nombre;
            enlace.click();
            URL.revokeObjectURL(url);
        } catch (e) {
            avisarError('No se pudo descargar el soporte', e);
        }
    };

    const descargarSoporte = (fila) => descargar(`${urlFila(fila)}/soporte`, fila.soporte.nombre);

    const descargarSoporteHistorial = (entrada) =>
        descargar(`/expedientes/${expediente.id}/historial/${entrada.id}/soporte`, entrada.soporte.nombre);

    const cargarHistorial = useCallback(
        async (fila) => (await axios.get(`/expedientes/${expediente.id}/requisitos/${fila.id}/historial`)).data,
        [expediente?.id]
    );

    const eliminarSoporte = async (fila) => {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            title: '¿Eliminar este soporte?',
            text: fila.soporte.nombre,
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        try {
            const { data } = await axios.delete(`${urlFila(fila)}/soporte`);
            reemplazarFila(data.requisito, data);
        } catch (e) {
            avisarError('No se pudo eliminar el soporte', e);
        }
    };

    // Los propios no entran en los avisos de duplicados, y con una sola plantilla no hay avisos.
    const accionFila = async (peticion, titulo) => {
        const { id, plantillas } = expediente;
        try {
            const { data } = await peticion();
            reemplazarFila(data.requisito, data);
            if (plantillas.length > 1) refrescarAvisos(id);
        } catch (e) {
            avisarError(titulo, e);
            recargar();
        }
    };

    const marcarNoAplica = async (fila) => {
        const { isConfirmed, value } = await Swal.fire({
            title: 'Este requisito no aplica',
            text: requiereAprobacionNoAplica(fila) ? 'Otra persona deberá aprobarlo.' : undefined,
            input: 'textarea',
            inputLabel: 'Justificación',
            inputPlaceholder: 'Explica por qué no aplica a este proceso',
            inputAttributes: { maxlength: '2000' },
            inputValidator: (texto) => (!texto?.trim() ? 'Escribe la justificación.' : undefined),
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        await accionFila(() => axios.post(`${urlFila(fila)}/no-aplica`, { justificacion: value }), 'No se pudo marcar como no aplica');
    };

    const retirarNoAplica = async (fila) => {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            title: '¿Retirar el "no aplica"?',
            text: 'El requisito volverá al estado que tenía antes de marcarlo: pendiente o por definir. Si ya estaba aprobado, necesitará otra aprobación.',
            showCancelButton: true,
            confirmButtonText: 'Sí, retirar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        await accionFila(() => axios.delete(`${urlFila(fila)}/no-aplica`), 'No se pudo retirar el "no aplica"');
    };

    const aprobarNoAplica = (fila) => ejecutar(() =>
        accionFila(() => axios.post(`${urlFila(fila)}/no-aplica/aprobar`), 'No se pudo aprobar el "no aplica"'));

    const rechazarNoAplica = async (fila) => {
        const { isConfirmed, value } = await Swal.fire({
            title: '¿Rechazar el "no aplica"?',
            text: 'El requisito volverá al estado que tenía antes de marcarlo: pendiente o por definir.',
            input: 'textarea',
            inputLabel: 'Motivo (opcional)',
            inputAttributes: { maxlength: '2000' },
            showCancelButton: true,
            confirmButtonText: 'Rechazar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        await accionFila(() => axios.post(`${urlFila(fila)}/no-aplica/rechazar`, { motivo: value || null }), 'No se pudo rechazar el "no aplica"');
    };

    // Pasar de "por definir" a "aplica" no tiene vuelta atrás: después solo cabe un "no aplica" justificado.
    const decidirAplica = (fila) => ejecutar(async () => {
        const { isConfirmed } = await Swal.fire({
            icon: 'question',
            titleText: '¿Este requisito aplica al proceso?',
            text: 'Quedará pendiente de cumplir. Si luego no aplica, tendrás que marcarlo como "no aplica" con una justificación.',
            showCancelButton: true,
            confirmButtonText: 'Sí, aplica',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        await accionFila(() => axios.post(`${urlFila(fila)}/aplica`), 'No se pudo indicar que el requisito aplica');
    });

    const urlPropios = () => `/expedientes/${expediente.id}/requisitos-propios`;

    /** Devuelve true si se guardó, para que el formulario se cierre. */
    const guardarPropio = async (fila, formulario) => Boolean(await ejecutar(async () => {
        const datos = datosPropioContratacion(formulario);
        try {
            if (fila) {
                const { data } = await axios.put(`${urlPropios()}/${fila.id}`, datos);
                reemplazarFila(data.requisito, data);
            } else {
                const { data } = await axios.post(urlPropios(), datos);
                agregarFila(expediente.id, data.requisito, data);
            }
            return true;
        } catch (e) {
            avisarError(fila ? 'No se pudo guardar el requisito propio' : 'No se pudo agregar el requisito propio', e);
            return false;
        }
    }));

    const eliminarPropio = (fila) => ejecutar(async () => {
        const motivo = motivoNoEliminarPropio(fila);
        if (motivo) {
            await Swal.fire({ icon: 'info', titleText: 'Este requisito no se puede eliminar', text: motivo });
            return;
        }
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            titleText: '¿Eliminar este requisito propio?',
            text: fila.propio.titulo,
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        try {
            const { data } = await axios.delete(`${urlPropios()}/${fila.id}`);
            quitarFila(fila, data);
        } catch (e) {
            avisarError('No se pudo eliminar el requisito propio', e);
            recargar();
        }
    });

    // Primero la vista previa, que no cambia nada; se aplica la versión que se mostró.
    const aplicarVersionNueva = (plantilla) => ejecutar(async () => {
        const url = `/expedientes/${expediente.id}/plantillas/${plantilla.plantilla_id}/version-nueva`;
        let vista;
        try {
            ({ data: vista } = await axios.get(url));
        } catch (e) {
            if (!montado.current) return;
            avisarError('No se pudo preparar la vista previa', e);
            recargar();
            return;
        }
        if (!montado.current) return;
        const { isConfirmed } = await Swal.fire({
            // titleText, no title: el nombre de la plantilla es un dato y Swal lo escapa.
            titleText: `Aplicar la versión ${vista.version_nueva.numero} de «${vista.plantilla.nombre}»`,
            html: htmlVistaPrevia(vista),
            width: '46rem',
            showCancelButton: true,
            confirmButtonText: 'Aplicar versión',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed || !montado.current) return;
        try {
            const { data } = await axios.post(url, { version_id: vista.version_nueva.id });
            if (montado.current) ponerExpediente(data);
        } catch (e) {
            if (!montado.current) return;
            avisarError('No se pudo aplicar la versión nueva', e);
            recargar();
        }
    });

    const crearExpediente = () => ejecutar(async () => {
        const plantillas = seleccion.filter((id) => disponibles.plantillas.some((p) => p.id === id));
        if (plantillas.length === 0) return;
        try {
            const { data } = await axios.post(`/procesos/${procesoId}/expediente`, { plantillas });
            if (!montado.current) return;
            setDisponibles((prev) => ({ ...prev, expediente_id: data.id }));
            ponerExpediente(data);
        } catch (e) {
            if (!montado.current) return;
            avisarError('No se pudo crear el expediente', e);
            // Otra persona pudo crearlo, o cambiar las plantillas que le corresponden.
            recargar();
        }
    });

    // Primero sin confirmar: si faltan requisitos, el servidor dice cuántos y en qué etapas, y se
    // pide confirmación (la revisión solo avisa). Si todo está cumplido, se confirma el congelado.
    const cerrarExpediente = () => ejecutar(async () => {
        const { id, progreso } = expediente;
        if (progreso.cumplidos >= progreso.total) {
            const { isConfirmed } = await Swal.fire({
                icon: 'question',
                titleText: '¿Cerrar el expediente?',
                text: 'El checklist quedará congelado y no admitirá más cambios.',
                showCancelButton: true,
                confirmButtonText: 'Sí, cerrar',
                cancelButtonText: 'Cancelar',
            });
            if (!isConfirmed) return;
        }
        const enviar = async (datos) => {
            const { data } = await axios.post(`/expedientes/${id}/cerrar`, datos);
            if (montado.current) ponerExpediente(data);
        };
        try {
            await enviar({});
            return;
        } catch (e) {
            const pendientes = e?.response?.status === 422 ? e.response.data?.pendientes : null;
            if (!pendientes) {
                avisarError('No se pudo cerrar el expediente', e);
                recargar();
                return;
            }
            if (!montado.current) return;
            const { isConfirmed } = await Swal.fire({
                icon: 'warning',
                titleText: '¿Cerrar con requisitos pendientes?',
                html: htmlPendientesCierre(pendientes),
                showCancelButton: true,
                confirmButtonText: 'Cerrar de todos modos',
                cancelButtonText: 'Cancelar',
            });
            // Lo pendiente pudo cambiar: el avance se pone al día con lo que dijo el servidor.
            recargar();
            if (!isConfirmed || !montado.current) return;
        }
        try {
            await enviar({ confirmar: true });
        } catch (e) {
            avisarError('No se pudo cerrar el expediente', e);
            recargar();
        }
    });

    const reintentar = error?.reintentable && <button type="button" className="exv-btn" onClick={cargar}>Reintentar</button>;

    if (!disponibles) {
        return (
            <Pagina>
                {error ? (
                    <div className="exv-vacio" role="alert">
                        <b>No se pudo abrir el expediente</b>
                        <p>{error.mensaje}</p>
                        {reintentar}
                    </div>
                ) : (
                    <div className="exv-vacio" role="status">Cargando expediente…</div>
                )}
            </Pagina>
        );
    }

    // La cabecera del proceso: la del expediente si lo hay (la más reciente), si no la de las plantillas.
    const proceso = { ...disponibles.proceso, ...(expediente?.proceso ?? {}) };
    const proyecto = expediente?.proyecto ?? disponibles.proceso.proyecto;
    const numero = numeroProceso(proceso);
    const { cumplidos = 0, total = 0, porcentaje = 0 } = expediente?.progreso ?? {};
    const avisoOpcionales = textoOpcionales(contarOpcionales(expediente?.requisitos));
    const etapasAvance = avanceEtapas(expediente?.avance_por_etapa);
    const cerrado = expediente?.estado === 'cerrado';
    const incompatibles = avisoIncompatiblesProceso(expediente?.plantillas);
    // Uno cerrado tiene el checklist congelado: no admite otra versión, y no se la ofrece.
    const versionesNuevas = expediente && !cerrado ? plantillasConVersionNueva(expediente.plantillas) : [];

    return (
        <Pagina>
            <div className="exv-titulo">
                <div className="exv-titulo-texto">
                    <span className="exv-eyebrow">Expediente de contratación</span>
                    <h2>{proceso.objeto || 'Proceso sin objeto'}</h2>
                    <p className="exv-subtitulo">
                        <span>
                            <b>Proceso</b>{' '}
                            {numero.enlace
                                ? <a href={numero.enlace} target="_blank" rel="noopener noreferrer" className="ct-enlace">{numero.texto}</a>
                                : numero.texto}
                        </span>
                        <span><b>Modalidad</b> {proceso.modalidad?.nombre ?? 'Sin modalidad'}</span>
                        <span className={`ct-estado ct-estado-${tonoEstado(proceso.estado)}`}>{etiquetaEstadoProceso(proceso.estado)}</span>
                        {proyecto && (
                            <span><b>Proyecto</b> <Link to={rutaFichaProyecto(proyecto.id)} className="ct-enlace">{proyecto.nombre || 'Sin nombre'}</Link></span>
                        )}
                    </p>
                    {expediente && (
                        <p className="exv-subtitulo">
                            <b>Expediente #{expediente.id}</b>
                            <span>{plantillasConVersion(expediente.plantillas)}</span>
                            <span>{textoEstadoExpediente(expediente)}</span>
                        </p>
                    )}
                </div>
            </div>

            {error && <div className="exv-aviso" role="alert">{error.mensaje} {reintentar}</div>}

            {!expediente ? (
                <SinExpediente
                    plantillas={disponibles.plantillas}
                    puedeEditar={puedeEditar}
                    esAdministrador={user?.rol === 'Administrador'}
                    seleccion={seleccion ?? []}
                    onCambiar={setSeleccion}
                    ocupado={ocupado}
                    onCrear={crearExpediente}
                />
            ) : (
                <main className="exv-tablero">
                    <div className="exv-barra">
                        <Medidor cumplidos={cumplidos} total={total} porcentaje={porcentaje} nota={avisoOpcionales} />
                        <div className="exv-barra-acciones">
                            <button
                                type="button"
                                className="exv-btn exv-btn-historial"
                                aria-expanded={historialAbierto}
                                aria-controls={historialAbierto ? ID_HISTORIAL : undefined}
                                onClick={() => setHistorialAbierto((abierto) => !abierto)}
                            >
                                <FontAwesomeIcon icon={faClockRotateLeft} /> Historial
                            </button>
                            {editable && (
                                <button type="button" className="exv-btn exv-btn-primario" onClick={cerrarExpediente} disabled={ocupado}>
                                    <FontAwesomeIcon icon={faLock} /> Cerrar expediente
                                </button>
                            )}
                        </div>
                    </div>

                    {etapasAvance.length > 0 && (
                        <section className="exc-etapas" aria-label="Avance por etapa">
                            {etapasAvance.map((e) => (
                                <Medidor key={e.clave} titulo={e.titulo} cumplidos={e.cumplidos} total={e.total} porcentaje={e.porcentaje} />
                            ))}
                        </section>
                    )}

                    {cerrado && (
                        <div className="exv-nota">
                            <b>{textoEstadoExpediente(expediente)}.</b> Su checklist está congelado: solo se puede consultar.
                        </div>
                    )}
                    {incompatibles && (
                        <div className="exv-aviso exv-no-imprimir">
                            <b>{incompatibles.titulo}:</b> {incompatibles.detalle}
                        </div>
                    )}
                    {versionesNuevas.length > 0 && (
                        <div className="exv-config-resumen exv-no-imprimir">
                            {versionesNuevas.map((p) => (
                                <span key={p.plantilla_id} className="exv-version-nueva">
                                    <b>{p.nombre}</b> {textoVersionNueva(p)}
                                    {editable && (
                                        <button
                                            type="button"
                                            className="exv-btn"
                                            onClick={() => aplicarVersionNueva(p)}
                                            disabled={ocupado}
                                            aria-label={etiquetaAplicarVersion(p)}
                                        >
                                            <FontAwesomeIcon icon={faArrowsRotate} /> Aplicar versión {p.version_nueva.numero}
                                        </button>
                                    )}
                                </span>
                            ))}
                        </div>
                    )}

                    {historialAbierto && (
                        <ExpedienteHistorial
                            key={expediente.id}
                            id={ID_HISTORIAL}
                            expedienteId={expediente.id}
                            revision={revision}
                            onDescargar={descargarSoporteHistorial}
                        />
                    )}

                    <ChecklistPorEtapa
                        expediente={expediente}
                        editable={editable}
                        ocupado={ocupado}
                        etapas={etapas}
                        onAplica={decidirAplica}
                        onGuardarPropio={guardarPropio}
                        onEliminarPropio={eliminarPropio}
                        onMarcar={marcar}
                        onObservacion={guardarObservacion}
                        onSubirSoporte={subirSoporte}
                        onDescargarSoporte={descargarSoporte}
                        onEliminarSoporte={eliminarSoporte}
                        usuario={user}
                        responsables={responsables}
                        onNoAplica={marcarNoAplica}
                        onRetirarNoAplica={retirarNoAplica}
                        onAprobarNoAplica={aprobarNoAplica}
                        onRechazarNoAplica={rechazarNoAplica}
                        onResponsable={asignarResponsable}
                        onCargarHistorial={cargarHistorial}
                        onDescargarHistorial={descargarSoporteHistorial}
                    />
                </main>
            )}
        </Pagina>
    );
};

/**
 * El expediente de contratación de un proceso (`/contratacion/:procesoId`): su checklist por etapa,
 * el avance total y por etapa, y el cierre, que solo avisa de lo pendiente. Sin expediente, ofrece
 * crearlo con las plantillas que le corresponden. Un id que no es válido no llega a la API.
 */
const ExpedienteContratacion = ({ user }) => {
    const { procesoId } = useParams();
    if (!esIdProceso(procesoId)) {
        return (
            <Pagina>
                <div className="exv-vacio" role="alert"><b>No se pudo abrir el expediente</b><p>El proceso no existe.</p></div>
            </Pagina>
        );
    }
    return <Expediente key={procesoId} procesoId={procesoId} user={user} />;
};

export default ExpedienteContratacion;
