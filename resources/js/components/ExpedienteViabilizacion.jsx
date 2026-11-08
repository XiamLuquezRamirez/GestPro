import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import { faArrowLeft, faCheck, faFileExcel, faPlus, faPrint, faSliders, faStamp, faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import ExpedienteChecklist, { AvisosDuplicados } from './ExpedienteChecklist';
import ExpedienteConfiguracion from './ExpedienteConfiguracion';
import useOcupado from './useOcupado';
import {
    ANCHOS_HOJA_CHECKLIST,
    actualizarExpedienteEnLista,
    construirHojasExcel,
    datosPropio,
    faseLegible,
    motivoNoEliminarPropio,
    nombreArchivoExcel,
    plantillasConVersion,
    puedeRadicar,
    requiereAprobacionNoAplica,
    sectoresConFuenteA12,
    sectoresSinTipo,
    seleccionDesde,
    textoPlantillas,
} from '../utils/expedienteChecklist';
import {
    AVISO_SIN_SECTOR, avisoIncompatibles, hayCompatibleSgr, seleccionParaNuevo, textoPlantillasNuevo,
} from '../utils/plantillasExpediente';
import { mensajeApi } from '../utils/plantillasChecklist';
import { avisarError } from '../utils/avisosPlantillas';
import '../../css/ExpedienteViabilizacion.css';

const ROLES_EDICION = ['Administrador', 'Gestor'];
const ESPERA_RECALCULO_MS = 350;

const ExpedienteViabilizacion = ({ user }) => {
    const { proyectoId } = useParams();
    const [searchParams, setSearchParams] = useSearchParams();
    const navigate = useNavigate();

    const [datosProyecto, setDatosProyecto] = useState(null);
    const [catalogo, setCatalogo] = useState(null);
    const [expediente, setExpediente] = useState(null);
    const [seleccion, setSeleccion] = useState(null);
    const [recalculando, setRecalculando] = useState(false);
    const [confirmando, setConfirmando] = useState(false);
    const [configAbierta, setConfigAbierta] = useState(false);
    const [errorCarga, setErrorCarga] = useState(null);
    const [responsables, setResponsables] = useState([]);
    // Plantillas que se ofrecen al proyecto (y las que ya tiene el expediente abierto).
    const [disponibles, setDisponibles] = useState(null);
    const [errorPlantillas, setErrorPlantillas] = useState(null);
    const [ocupado, ejecutar] = useOcupado();

    const puedeEditar = ROLES_EDICION.includes(user?.rol);
    const editable = puedeEditar && expediente?.estado === 'borrador';
    const idSolicitado = Number(searchParams.get('expediente')) || null;
    const idActual = idSolicitado ?? datosProyecto?.expedientes[0]?.id ?? null;

    // Envíos de configuración en serie: si el usuario sigue marcando mientras hay uno
    // en curso, solo se manda el último estado al terminar. `pendiente` lleva su expediente
    // ({ id, datos }): si entretanto se abrió otro, no se le manda la selección del anterior.
    const envio = useRef({ temporizador: null, enCurso: false, pendiente: null });

    // Expediente que se muestra (o se está abriendo). Una respuesta de otro llega tarde y se descarta.
    const idVigente = useRef(null);
    const esVigente = (id) => id === idVigente.current;

    const cargarProyecto = useCallback(async () => {
        const { data } = await axios.get(`/proyectos/${proyectoId}/expedientes`);
        setDatosProyecto(data);
        return data;
    }, [proyectoId]);

    // El selector de expedientes sale de la lista del proyecto: se pone al día con cada respuesta del expediente.
    const actualizarEnLista = useCallback((data) => setDatosProyecto((prev) => (
        prev ? { ...prev, expedientes: actualizarExpedienteEnLista(prev.expedientes, data) } : prev
    )), []);

    // Como cargarPlantillas: solo vale la última petición, y solo si su expediente sigue abierto.
    // Una respuesta o un error que llegan tarde se descartan en silencio.
    const peticionExpediente = useRef(0);
    const cargarExpediente = useCallback(async (id) => {
        if (id !== idVigente.current) return;
        const numero = ++peticionExpediente.current;
        const alDia = () => numero === peticionExpediente.current && id === idVigente.current;
        let data;
        try {
            ({ data } = await axios.get(`/expedientes/${id}`));
        } catch (error) {
            if (alDia()) throw error;
            return;
        }
        if (!alDia()) return;
        setExpediente(data);
        setSeleccion(seleccionDesde(data));
        actualizarEnLista(data);
    }, [actualizarEnLista]);

    // Los avisos de duplicados cambian cuando una fila deja de aplicar o vuelve a aplicar, y la
    // respuesta de una acción por fila no los trae: se piden aparte, sin tocar nada más de la pantalla.
    const peticionAvisos = useRef(0);
    const refrescarAvisos = useCallback(async (id) => {
        const numero = ++peticionAvisos.current;
        try {
            const { data } = await axios.get(`/expedientes/${id}`);
            if (numero !== peticionAvisos.current) return;
            setExpediente((prev) => (prev?.id === id
                ? { ...prev, avisos_duplicados: data.avisos_duplicados, avisos_duplicados_total: data.avisos_duplicados_total }
                : prev));
        } catch {
            // En silencio: los avisos quedan como estaban hasta la próxima carga.
        }
    }, []);

    // Solo vale la última petición: el expediente abierto puede cambiar mientras llega.
    const peticionPlantillas = useRef(0);
    const cargarPlantillas = useCallback(async (expedienteId) => {
        const numero = ++peticionPlantillas.current;
        setErrorPlantillas(null);
        try {
            const { data } = await axios.get(`/proyectos/${proyectoId}/plantillas-compatibles`, {
                params: expedienteId ? { expediente: expedienteId } : {},
            });
            if (numero === peticionPlantillas.current) setDisponibles(data);
        } catch (error) {
            if (numero === peticionPlantillas.current) {
                setDisponibles(null);
                setErrorPlantillas(mensajeApi(error));
            }
        }
    }, [proyectoId]);

    useEffect(() => {
        let vigente = true;
        (async () => {
            try {
                const [, respuestaCatalogo] = await Promise.all([cargarProyecto(), axios.get('/catalogo-requisitos/vigente')]);
                if (vigente) setCatalogo(respuestaCatalogo.data);
            } catch (error) {
                if (vigente) {
                    setErrorCarga(error?.response?.status === 404 ? 'El proyecto no existe.' : mensajeApi(error));
                }
            }
        })();
        return () => { vigente = false; };
    }, [cargarProyecto]);

    useEffect(() => {
        idVigente.current = idActual;
        clearTimeout(envio.current.temporizador);
        envio.current.pendiente = null;
        // Un cambio que esperaba el temporizador ya no se manda; si hay un envío en curso, él lo apaga al terminar.
        if (!envio.current.enCurso) setRecalculando(false);
        setConfigAbierta(false);

        if (!idActual) {
            setExpediente(null);
            setSeleccion(null);
            return;
        }

        // cargarExpediente ya descarta lo que llega tarde: si lanza, el error es del expediente abierto.
        cargarExpediente(idActual).catch((error) => {
            setErrorCarga(error?.response?.status === 404 ? 'El expediente no existe.' : mensajeApi(error));
        });
    }, [idActual, cargarExpediente]);

    // También sin expediente: dice qué plantillas tendría uno nuevo. Espera al proyecto para
    // saber cuál es el expediente abierto y no pedir la lista dos veces.
    const proyectoCargado = Boolean(datosProyecto);
    useEffect(() => {
        if (!proyectoCargado) return;
        setDisponibles(null);
        cargarPlantillas(idActual);
    }, [proyectoCargado, idActual, cargarPlantillas]);

    useEffect(() => () => clearTimeout(envio.current.temporizador), []);

    // Sin la lista, el checklist igual funciona: solo no se puede elegir responsable.
    useEffect(() => {
        let vigente = true;
        axios.get('/responsables')
            .then(({ data }) => { if (vigente) setResponsables(data); })
            .catch(() => { if (vigente) setResponsables([]); });
        return () => { vigente = false; };
    }, []);

    const enviarConfiguracion = useCallback(async (id, datos) => {
        if (envio.current.enCurso) {
            envio.current.pendiente = { id, datos };
            return;
        }
        envio.current.enCurso = true;
        setRecalculando(true);
        try {
            const { data } = await axios.put(`/expedientes/${id}/configuracion`, datos);
            // La lista del selector se indexa por id: ponerla al día vale aunque ya se muestre otro.
            actualizarEnLista(data);
            if (id === idVigente.current && !envio.current.pendiente) setExpediente(data);
        } catch (error) {
            // Si ya se muestra otro expediente, el error de este no se avisa ni se recarga.
            if (id === idVigente.current) {
                envio.current.pendiente = null;
                avisarError('No se pudo actualizar la configuración', error);
                cargarExpediente(id).catch(() => {});
            }
        } finally {
            envio.current.enCurso = false;
            const siguiente = envio.current.pendiente;
            envio.current.pendiente = null;
            if (siguiente) enviarConfiguracion(siguiente.id, siguiente.datos);
            else setRecalculando(false);
        }
    }, [cargarExpediente, actualizarEnLista]);

    const cambiarSeleccion = (nueva) => {
        setSeleccion(nueva);
        setRecalculando(true);
        clearTimeout(envio.current.temporizador);
        envio.current.temporizador = setTimeout(() => enviarConfiguracion(expediente.id, nueva), ESPERA_RECALCULO_MS);
    };

    const confirmarConfiguracion = async () => {
        setConfirmando(true);
        try {
            const { data } = await axios.post(`/expedientes/${expediente.id}/configuracion/confirmar`, seleccion);
            if (!esVigente(data.id)) return;
            setExpediente(data);
            setSeleccion(seleccionDesde(data));
            setConfigAbierta(false);
            // Las plantillas nuevas ya son del expediente: la lista lo refleja.
            cargarPlantillas(data.id);
            await cargarProyecto();
        } catch (error) {
            avisarError('No se pudo confirmar la configuración', error);
        } finally {
            setConfirmando(false);
        }
    };

    // Si la fila ya no está (se abrió otro expediente mientras llegaba la respuesta), nada cambia:
    // ni la fila ni el progreso, que es del expediente de la fila.
    const reemplazarFila = (fila, progreso) => setExpediente((prev) => (
        prev?.requisitos.some((f) => f.id === fila.id)
            ? { ...prev, progreso: progreso ?? prev.progreso, requisitos: prev.requisitos.map((f) => (f.id === fila.id ? fila : f)) }
            : prev
    ));

    // Un propio nuevo va al final: el backend ordena los propios después de toda plantilla.
    const agregarFila = (expedienteId, fila, progreso) => setExpediente((prev) => (
        prev?.id === expedienteId
            ? { ...prev, progreso: progreso ?? prev.progreso, requisitos: [...prev.requisitos, fila] }
            : prev
    ));

    const quitarFila = (fila, progreso) => setExpediente((prev) => (
        prev?.requisitos.some((f) => f.id === fila.id)
            ? { ...prev, progreso: progreso ?? prev.progreso, requisitos: prev.requisitos.filter((f) => f.id !== fila.id) }
            : prev
    ));

    const urlFila = (fila) => `/expedientes/${expediente.id}/requisitos/${fila.id}`;

    const marcar = async (fila, cumplido) => {
        reemplazarFila({ ...fila, cumplido, estado: cumplido ? 'cumplido' : 'pendiente' });
        try {
            const { data } = await axios.patch(urlFila(fila), { cumplido });
            reemplazarFila(data.requisito, data.progreso);
        } catch (error) {
            reemplazarFila(fila);
            avisarError('No se pudo actualizar el requisito', error);
        }
    };

    const guardarObservacion = async (fila, observacion) => {
        try {
            const { data } = await axios.patch(urlFila(fila), { observacion });
            reemplazarFila(data.requisito, data.progreso);
        } catch (error) {
            avisarError('No se pudo guardar la observación', error);
        }
    };

    const subirSoporte = async (fila, archivo) => {
        const formulario = new FormData();
        formulario.append('archivo', archivo);
        try {
            const { data } = await axios.post(`${urlFila(fila)}/soporte`, formulario);
            reemplazarFila(data.requisito);
        } catch (error) {
            avisarError('No se pudo adjuntar el soporte', error);
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
        } catch (error) {
            avisarError('No se pudo descargar el soporte', error);
        }
    };

    const descargarSoporte = (fila) => descargar(`${urlFila(fila)}/soporte`, fila.soporte.nombre);

    const descargarSoporteHistorial = (entrada) =>
        descargar(`/expedientes/${expediente.id}/historial/${entrada.id}/soporte`, entrada.soporte.nombre);

    const cargarHistorial = useCallback(
        async (fila) => (await axios.get(`/expedientes/${expediente.id}/requisitos/${fila.id}/historial`)).data,
        [expediente?.id]
    );

    // Los propios no entran en los avisos de duplicados (ExpedienteService::avisosDuplicados), y con
    // una sola plantilla no hay avisos: solo hace falta pedirlos tras una acción de aplicabilidad.
    const accionFila = async (peticion, titulo) => {
        const { id, plantillas } = expediente;
        try {
            const { data } = await peticion();
            reemplazarFila(data.requisito, data.progreso);
            if (plantillas.length > 1) refrescarAvisos(id);
        } catch (error) {
            avisarError(titulo, error);
            // La fila pudo cambiar en el servidor: se recarga para no mostrar acciones viejas.
            cargarExpediente(expediente.id).catch(() => {});
        }
    };

    const marcarNoAplica = async (fila) => {
        const { isConfirmed, value } = await Swal.fire({
            title: 'Este requisito no aplica',
            text: requiereAprobacionNoAplica(fila) ? 'Otra persona deberá aprobarlo.' : undefined,
            input: 'textarea',
            inputLabel: 'Justificación',
            inputPlaceholder: 'Explica por qué no aplica a este proyecto',
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
            titleText: '¿Este requisito aplica al proyecto?',
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
        const datos = datosPropio(formulario);
        try {
            if (fila) {
                const { data } = await axios.put(`${urlPropios()}/${fila.id}`, datos);
                reemplazarFila(data.requisito, data.progreso);
            } else {
                const { data } = await axios.post(urlPropios(), datos);
                agregarFila(expediente.id, data.requisito, data.progreso);
            }
            return true;
        } catch (error) {
            avisarError(fila ? 'No se pudo guardar el requisito propio' : 'No se pudo agregar el requisito propio', error);
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
            quitarFila(fila, data.progreso);
        } catch (error) {
            avisarError('No se pudo eliminar el requisito propio', error);
            // Pudo cambiar en el servidor (otra persona lo marcó o le adjuntó un soporte).
            cargarExpediente(expediente.id).catch(() => {});
        }
    });

    const asignarResponsable = async (fila, responsableId) => {
        reemplazarFila({ ...fila, responsable: responsables.find((r) => r.id === responsableId) ?? null });
        try {
            const { data } = await axios.patch(urlFila(fila), { responsable_id: responsableId });
            reemplazarFila(data.requisito, data.progreso);
        } catch (error) {
            reemplazarFila(fila);
            avisarError('No se pudo asignar el responsable', error);
        }
    };

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
            reemplazarFila(data.requisito);
        } catch (error) {
            avisarError('No se pudo eliminar el soporte', error);
        }
    };

    const abrirExpediente = (id) => setSearchParams(id ? { expediente: String(id) } : {});

    // El expediente del selector ya llegó: hasta entonces, `seleccion` y `disponibles` son del anterior.
    const expedienteAlDia = !idActual || expediente?.id === idActual;
    // Sin la lista no se sabe qué plantillas pasarían; si falló, se crea igual y decide el servidor.
    const plantillasListas = disponibles !== null || Boolean(errorPlantillas);

    const crearExpediente = () => ejecutar(async () => {
        if (!expedienteAlDia || !plantillasListas) return;
        // Uno nuevo parte de la configuración del abierto (caso re-radicación), solo con las
        // plantillas que hoy se ofrecen al proyecto. Sin ninguno, el servidor preselecciona la
        // plantilla SGR compatible, si la hay; con ella van la fase III y el sector sugerido.
        // El cumplimiento no se copia: cada expediente se evalúa desde cero.
        let datos;
        let quitadas = [];
        if (seleccion) {
            ({ datos, quitadas } = seleccionParaNuevo(seleccion, disponibles?.plantillas ?? null, expediente?.plantillas ?? []));
        } else {
            const sugerido = datosProyecto.sector_sugerido_id;
            // Sin la lista no se sabe si habrá SGR: se envía el sector, que sin ella no tiene efecto.
            const conSgr = disponibles ? hayCompatibleSgr(disponibles.plantillas) : true;
            datos = { fase_sgr: 3, sector_ids: sugerido && conSgr ? [sugerido] : [] };
        }
        try {
            const { data } = await axios.post(`/proyectos/${proyectoId}/expedientes`, datos);
            await cargarProyecto();
            abrirExpediente(data.id);
        } catch (error) {
            avisarError('No se pudo crear el expediente', error);
            return;
        }
        if (quitadas.length > 0) {
            Swal.fire({
                icon: 'warning',
                titleText: quitadas.length === 1 ? 'Una plantilla no pasó al expediente nuevo' : 'Algunas plantillas no pasaron al expediente nuevo',
                text: `${quitadas.length === 1 ? 'Ya no se ofrece' : 'Ya no se ofrecen'} a este proyecto: ${quitadas.join(', ')}. Revisa la configuración antes de confirmarla.`,
            });
        }
    });

    const radicar = () => ejecutar(async () => {
        const { id } = expediente;
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            title: '¿Radicar el expediente?',
            text: 'El checklist quedará congelado y no admitirá más cambios.',
            showCancelButton: true,
            confirmButtonText: 'Sí, radicar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        try {
            const { data } = await axios.post(`/expedientes/${id}/radicar`);
            if (esVigente(id)) setExpediente(data);
            await cargarProyecto();
        } catch (error) {
            avisarError('No se pudo radicar el expediente', error);
        }
    });

    const eliminar = () => ejecutar(async () => {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            title: `¿Eliminar el expediente #${expediente.id}?`,
            text: 'Se perderán su checklist, observaciones y soportes.',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        try {
            await axios.delete(`/expedientes/${expediente.id}`);
            await cargarProyecto();
            abrirExpediente(null);
        } catch (error) {
            avisarError('No se pudo eliminar el expediente', error);
        }
    });

    const exportarExcel = () => {
        const { resumen, checklist } = construirHojasExcel(expediente);
        const libro = XLSX.utils.book_new();
        const hojaResumen = XLSX.utils.json_to_sheet(resumen);
        hojaResumen['!cols'] = [{ wch: 24 }, { wch: 80 }];
        const hojaChecklist = XLSX.utils.json_to_sheet(checklist);
        // Un ancho por columna, en el orden de construirHojasExcel (20 columnas).
        hojaChecklist['!cols'] = ANCHOS_HOJA_CHECKLIST.map((wch) => ({ wch }));
        XLSX.utils.book_append_sheet(libro, hojaResumen, 'Resumen');
        XLSX.utils.book_append_sheet(libro, hojaChecklist, 'Checklist');
        XLSX.writeFile(libro, nombreArchivoExcel(expediente));
    };

    const volver = () => navigate(-1);

    if (errorCarga) {
        return (
            <div className="exv-pagina">
                <div className="exv-contenido">
                    <div className="exv-vacio">
                        <b>No se pudo abrir el expediente</b>
                        <p>{errorCarga}</p>
                        <button type="button" className="exv-btn" onClick={volver}>Volver</button>
                    </div>
                </div>
            </div>
        );
    }

    const cargado = datosProyecto && catalogo && (!idActual || (expediente && seleccion));
    // Lo propio de regalías solo se muestra si el expediente tiene una plantilla con motor sgr.
    const conSgr = Boolean(expediente?.tiene_sgr);
    const a12 = expediente && conSgr ? sectoresConFuenteA12(expediente.configuracion.sectores) : [];
    const esDesastre = conSgr && expediente.configuracion.tipologias.some((t) => t.clave === 'desastre');
    const { cumplidos = 0, total = 0, porcentaje = 0 } = expediente?.progreso ?? {};
    const sinConfigurar = Boolean(expediente) && !expediente.configurado;
    const panelVisible = sinConfigurar ? editable : configAbierta;
    const hayBorrador = datosProyecto?.expedientes.some((e) => e.estado === 'borrador');
    const sinTipo = sinConfigurar && conSgr && seleccion ? sectoresSinTipo(seleccion, catalogo) : [];
    const sinPlantillasElegidas = Boolean(seleccion) && seleccion.plantilla_ids.length === 0;
    const incompatibles = avisoIncompatibles(expediente?.plantillas);

    return (
        <div className="exv-pagina">
            <div className="exv-contenido">
                {!cargado ? (
                    <div className="exv-vacio">Cargando expediente…</div>
                ) : (
                    <>
                        <div className="exv-titulo">
                            <button type="button" className="exv-btn exv-no-imprimir" onClick={volver}>
                                <FontAwesomeIcon icon={faArrowLeft} /> Volver
                            </button>
                            <div className="exv-titulo-texto">
                                <span className="exv-eyebrow">Seguimiento de proyectos</span>
                                <h2>{datosProyecto.proyecto.nombre}</h2>
                                {expediente && (
                                    <p className="exv-subtitulo">
                                        <b>Expediente #{expediente.id}</b>
                                        <span>{textoPlantillas(expediente.plantillas)}</span>
                                    </p>
                                )}
                            </div>
                            <div className="exv-titulo-acciones exv-no-imprimir">
                                {datosProyecto.expedientes.length > 0 && (
                                    <select
                                        aria-label="Expediente"
                                        value={idActual ?? ''}
                                        onChange={(e) => abrirExpediente(Number(e.target.value))}
                                    >
                                        {datosProyecto.expedientes.map((e) => (
                                            <option key={e.id} value={e.id}>
                                                #{e.id} · {e.estado === 'radicado' ? 'Radicado' : 'Borrador'} · {e.plantillas.length ? e.plantillas.map((p) => p.nombre).join(', ') : 'Sin plantillas'}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                {puedeEditar && !hayBorrador && (
                                    <button
                                        type="button"
                                        className="exv-btn"
                                        onClick={crearExpediente}
                                        disabled={ocupado || !expedienteAlDia || !plantillasListas}
                                    >
                                        <FontAwesomeIcon icon={faPlus} /> Nuevo expediente
                                    </button>
                                )}
                            </div>
                        </div>

                        {!expediente ? (
                            <div className="exv-vacio">
                                <b>Este proyecto aún no tiene expediente</b>
                                <p>
                                    {puedeEditar
                                        ? 'Crea uno para armar su checklist de requisitos.'
                                        : 'Un Administrador o Gestor debe crearlo.'}
                                </p>
                                {puedeEditar && disponibles && (
                                    <p className="exv-vacio-plantillas">
                                        {textoPlantillasNuevo(disponibles.plantillas)}
                                        {disponibles.sin_sector && ` ${AVISO_SIN_SECTOR}.`}
                                    </p>
                                )}
                            </div>
                        ) : (
                            <div className={`exv-shell${panelVisible ? '' : ' plegada'}`}>
                                {panelVisible && (
                                    <ExpedienteConfiguracion
                                        catalogo={catalogo}
                                        expediente={expediente}
                                        seleccion={seleccion}
                                        deshabilitada={!editable}
                                        recalculando={recalculando}
                                        onCambiar={cambiarSeleccion}
                                        disponibles={disponibles}
                                        errorPlantillas={errorPlantillas}
                                        onReintentarPlantillas={() => cargarPlantillas(expediente.id)}
                                    />
                                )}

                                {sinConfigurar ? (
                                    <main className="exv-tablero">
                                        <div className="exv-confirmar">
                                            {editable ? (
                                                <>
                                                    <h3>Configura el expediente para empezar a chequear</h3>
                                                    <p>
                                                        {conSgr
                                                            ? 'Se hace una sola vez. Elige en el panel las plantillas y revisa la fase SGR, el sector, el tipo de proyecto y los componentes: definen qué requisitos aplican y, con eso, el porcentaje de avance.'
                                                            : 'Se hace una sola vez. Elige en el panel las plantillas del expediente: definen qué requisitos aplican y, con eso, el porcentaje de avance.'}
                                                    </p>
                                                    {sinPlantillasElegidas && (
                                                        <div className="exv-aviso">
                                                            <b>Sin plantillas.</b> Elige al menos una plantilla para armar el checklist.
                                                        </div>
                                                    )}
                                                    {conSgr && seleccion.sector_ids.length === 0 && (
                                                        <div className="exv-aviso">
                                                            <b>Sin sector.</b> Solo se cargarán los requisitos generales del SGR.
                                                        </div>
                                                    )}
                                                    {sinTipo.length > 0 && (
                                                        <div className="exv-aviso">
                                                            <b>Sin tipo de proyecto: {sinTipo.join(', ')}.</b> Se cargarán todos los requisitos de ese sector, incluidos los que no le apliquen al proyecto.
                                                        </div>
                                                    )}
                                                    <AvisosDuplicados avisos={expediente.avisos_duplicados} total={expediente.avisos_duplicados_total} />
                                                    <div className="exv-confirmar-pie">
                                                        <span>
                                                            {recalculando
                                                                ? 'Recalculando…'
                                                                : `Con esta configuración ${total === 1 ? 'aplica 1 requisito' : `aplican ${total} requisitos`}.`}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            className="exv-btn exv-btn-primario"
                                                            onClick={confirmarConfiguracion}
                                                            disabled={recalculando || confirmando || sinPlantillasElegidas}
                                                        >
                                                            <FontAwesomeIcon icon={faCheck} /> Confirmar configuración
                                                        </button>
                                                    </div>
                                                </>
                                            ) : (
                                                <>
                                                    <h3>Este expediente aún no está configurado</h3>
                                                    <p>Un Administrador o Gestor debe confirmar su configuración para que se pueda hacer seguimiento.</p>
                                                </>
                                            )}
                                        </div>
                                    </main>
                                ) : (
                                <main className="exv-tablero">
                                    <div className="exv-barra">
                                        <div className="exv-medidor">
                                            <div className="exv-medidor-top">
                                                <b><span>{cumplidos}</span> / {total} requisitos cumplidos</b>
                                                <em>{porcentaje}%</em>
                                            </div>
                                            <div className="exv-pista">
                                                <div
                                                    className={`exv-relleno${porcentaje === 100 && total > 0 ? ' completo' : ''}`}
                                                    style={{ width: `${porcentaje}%` }}
                                                />
                                            </div>
                                        </div>
                                        <div className="exv-barra-acciones">
                                            <button
                                                type="button"
                                                className="exv-btn"
                                                aria-expanded={configAbierta}
                                                onClick={() => setConfigAbierta((abierta) => !abierta)}
                                            >
                                                <FontAwesomeIcon icon={faSliders} />{' '}
                                                {configAbierta ? 'Ocultar configuración' : editable ? 'Editar configuración' : 'Ver configuración'}
                                            </button>
                                            <button type="button" className="exv-btn" onClick={exportarExcel}>
                                                <FontAwesomeIcon icon={faFileExcel} /> Excel
                                            </button>
                                            <button type="button" className="exv-btn" onClick={() => window.print()}>
                                                <FontAwesomeIcon icon={faPrint} /> Imprimir / PDF
                                            </button>
                                            {editable && (
                                                <>
                                                    <button type="button" className="exv-btn exv-btn-peligro" onClick={eliminar} disabled={ocupado}>
                                                        <FontAwesomeIcon icon={faTrash} /> Eliminar
                                                    </button>
                                                    {!puedeRadicar(expediente.progreso) && (
                                                        <span className="exv-radicar-pista">
                                                            {total === 0 && 'Sin requisitos para radicar'}
                                                            {total > 0 && `${total - cumplidos === 1 ? 'Falta' : 'Faltan'} ${total - cumplidos} para radicar`}
                                                        </span>
                                                    )}
                                                    <button
                                                        type="button"
                                                        className="exv-btn exv-btn-primario"
                                                        onClick={radicar}
                                                        disabled={ocupado || recalculando || !puedeRadicar(expediente.progreso)}
                                                    >
                                                        <FontAwesomeIcon icon={faStamp} /> Radicar
                                                    </button>
                                                </>
                                            )}
                                        </div>
                                    </div>

                                    <div className="exv-solo-impresion exv-resumen-impresion">
                                        <h2>Expediente #{expediente.id} <small>{plantillasConVersion(expediente.plantillas)}</small></h2>
                                        <div>
                                            {conSgr && <span>Fase SGR {faseLegible(expediente.fase_sgr)}</span>}
                                            <span>{cumplidos} de {total} requisitos ({porcentaje}%)</span>
                                            <span>{expediente.estado === 'radicado' ? `Radicado ${new Date(expediente.fecha_radicado).toLocaleDateString('es-CO')}` : 'Borrador'}</span>
                                        </div>
                                        {conSgr && (
                                            <>
                                                <div>Sectores: {expediente.configuracion.sectores.map((s) => s.nombre).join('; ') || 'Sin sector (solo generales)'}</div>
                                                {expediente.configuracion.tipos.length > 0 && <div>Tipos de proyecto: {expediente.configuracion.tipos.map((t) => t.nombre).join('; ')}</div>}
                                                {expediente.configuracion.componentes.length > 0 && <div>Componentes: {expediente.configuracion.componentes.map((c) => c.nombre).join('; ')}</div>}
                                                {expediente.configuracion.tipologias.length > 0 && <div>Tipologías: {expediente.configuracion.tipologias.map((t) => t.nombre).join('; ')}</div>}
                                                <div className="exv-resumen-clave">A12 = Acuerdo 12/2024 · OT = Orientaciones Transitorias</div>
                                            </>
                                        )}
                                    </div>

                                    {expediente.estado === 'radicado' && (
                                        <div className="exv-nota exv-no-imprimir">
                                            <b>Expediente radicado{expediente.fecha_radicado && ` el ${new Date(expediente.fecha_radicado).toLocaleDateString('es-CO')}`}{expediente.usuario_radico && ` por ${expediente.usuario_radico}`}.</b>{' '}
                                            Su checklist está congelado. Para corregirlo, crea un expediente nuevo.
                                        </div>
                                    )}
                                    {incompatibles && (
                                        <div className="exv-aviso exv-no-imprimir">
                                            <b>{incompatibles.titulo}:</b> {incompatibles.detalle}
                                        </div>
                                    )}
                                    {!configAbierta && (
                                        <div className="exv-config-resumen exv-no-imprimir">
                                            <span><b>Plantillas</b> {plantillasConVersion(expediente.plantillas)}</span>
                                            {conSgr && (
                                                <>
                                                    <span><b>Fase SGR</b> {faseLegible(expediente.fase_sgr)}</span>
                                                    <span><b>Sectores</b> {expediente.configuracion.sectores.map((s) => s.nombre).join('; ') || 'Solo generales'}</span>
                                                    {expediente.configuracion.tipos.length > 0 && <span><b>Tipos</b> {expediente.configuracion.tipos.map((t) => t.nombre).join('; ')}</span>}
                                                    {expediente.configuracion.componentes.length > 0 && <span><b>Componentes</b> {expediente.configuracion.componentes.map((c) => c.nombre).join('; ')}</span>}
                                                </>
                                            )}
                                        </div>
                                    )}
                                    {configAbierta && conSgr && expediente.configuracion.sectores.length > 0 && (
                                        <div className="exv-nota exv-no-imprimir">
                                            <b>Sectores seleccionados:</b> se suman los requisitos generales más los de cada sector marcado. Marca los <b>tipos de proyecto</b> para acotar cada sector (si no marcas ninguno de un sector, se muestra completo).
                                            {a12.length > 0 && <small>{a12.join(', ')}: se rige(n) por el Acuerdo 12/2024 (vigente), que reemplazó las Orientaciones Transitorias.</small>}
                                        </div>
                                    )}
                                    {esDesastre && (
                                        <div className="exv-aviso exv-no-imprimir">
                                            <b>Régimen especial de desastre.</b> Bajo el artículo 4.1.2.2.9, estos proyectos (Fase III) cumplen <b>únicamente</b> el listado especial; reemplaza los requisitos generales. Los requisitos sectoriales siguen aplicando si eliges un sector.
                                        </div>
                                    )}
                                    {expediente.retenidos > 0 && (
                                        <div className="exv-nota exv-no-imprimir">
                                            {expediente.retenidos === 1
                                                ? '1 requisito con trabajo guardado no aplica con esta configuración.'
                                                : `${expediente.retenidos} requisitos con trabajo guardado no aplican con esta configuración.`}{' '}
                                            Se recuperan tal cual si vuelven a aplicar.
                                        </div>
                                    )}

                                    <ExpedienteChecklist
                                        expediente={expediente}
                                        editable={editable}
                                        ocupado={ocupado}
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

                                    <p className="exv-pie">
                                        Herramienta de apoyo para conformar el expediente. Cada ítem es un resumen del requisito; el texto exacto y su aplicabilidad los determina la norma y la instancia competente.
                                    </p>
                                </main>
                                )}
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    );
};

export default ExpedienteViabilizacion;
