import React, { useEffect, useRef, useState } from 'react';
import { faCalculator, faEye, faFile, faInfo, faPlus, faSave, faTimes, faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
import axios from '../../axios';
import { avisarError } from '../../utils/avisosPlantillas';
import { datosEstado, esEdicionDesactualizada, textoValor, valorEnEdicion } from '../../utils/contratacion';
import {
    ACTA_VACIA, ACTIVIDAD_VACIA, ANEXO_VACIO, AVANCE_VACIO, ESTADOS_CONTRATO, MODIFICACION_VACIA, actividadConAvance, anexosDelContrato,
    anexosParaGuardar, aplicarPrecarga, avanceFinancieroDeActas, avanceFisicoDeActividades, calculoActa, cambiarProyecto, contratoVacio,
    datosActa, datosContrato, datosDelProceso, datosModificacion, erroresGuardarContrato, etiquetaTipoModificacion, formularioContrato,
    notaProceso, opcionesProceso, opcionesTipoContrato, primerCampoConError, procesoDelContrato, procesoElegido, sumaPesos, validarContrato,
    valorVigente, vigentesDistintos,
} from '../../utils/contratos';
import { opcionesProyecto } from '../../utils/contratosModulo';
import { formatoMoneda, limpiarMoneda, monedaEnEdicion, numeroDeMoneda } from '../../utils/moneda';
import { htmlErrores, mensajeApi } from '../../utils/plantillasChecklist';
import '../../../css/Parametros.css';
import '../../../css/Contratacion.css';

/** Catálogo de tipos de contrato (GET /tiposProcesos); null si no se pudo traer. */
export const pedirTiposContrato = async () => {
    try {
        const { data } = await axios.get('/tiposProcesos');
        return Array.isArray(data) ? data : [];
    } catch (error) {
        // Sin catálogo, el select ofrece solo el tipo que el contrato ya tiene.
        return null;
    }
};

/** Procesos de contratación del proyecto (GET /proyectos/{id}/procesos); null si fallan; [] sin proyecto. */
export const pedirProcesosProyecto = async (proyectoId) => {
    if (!proyectoId) return [];
    try {
        const { data } = await axios.get(`/proyectos/${proyectoId}/procesos`);
        return data?.procesos ?? [];
    } catch (error) {
        return null;
    }
};

/**
 * Un contrato, nuevo o existente: su formulario y las pestañas de anexos, actas, actividades y
 * modificaciones, con el pie de Cancelar y Guardar. Se pinta dentro de un `.contratos-modal`
 * (pone su `.contratos-modal-body` y su `.modal-actions`) y se monta de nuevo para cada contrato.
 *
 * - `proyecto`: el proyecto del contrato nuevo ({id, nombre}) o null; sus procesos llenan el select.
 * - `contrato`: el que se edita (un elemento de GET /listarContratos o de /contratos/{id}/detalle), o null para uno nuevo.
 * - `procesoInicial`: en un contrato nuevo, el id del proceso ya elegido; se aplica su precarga.
 * - `tiposContrato`: el catálogo si quien lo pinta ya lo trae (null mientras carga o si falló); sin la prop, lo pide el editor.
 * - `procesosIniciales`: los procesos del proyecto que ya se tienen, mientras llegan los recién pedidos.
 * - `proyectos`: con la prop (la lista de GET /proyectos, o null mientras carga), el formulario
 *   muestra un selector de proyecto: uno nuevo o sin proyecto puede quedar "Sin proyecto"; uno
 *   con proyecto puede cambiarlo, pero no quitarlo. Al cambiarlo se cargan los procesos del
 *   elegido. Sin la prop, el proyecto es el de `proyecto` o el del contrato, sin selector.
 * - `puedeEditar`: sin permiso, el contrato se ve sin controles para modificarlo.
 * - `recargar(id)`: vuelve a traer los contratos tras un cambio en las pestañas; resuelve el contrato `id` recargado o null.
 * - `onGuardado(contrato)`: tras guardar (con los avisos ya mostrados); quien lo pinta cierra el editor.
 * - `onCancelar()`: "Cancelar" o "Volver a la lista".
 */
const ContratoEditor = ({
    proyecto = null, contrato = null, procesoInicial = null, tiposContrato, procesosIniciales = null, proyectos, puedeEditar = true,
    recargar, onGuardado, onCancelar,
}) => {
    const [editingContrato, setEditingContrato] = useState(contrato);
    const [contratoActiveTab, setContratoActiveTab] = useState('informacion');
    // Campos de formulario para contratos (los de la API: utils/contratos.js)
    const [formContrato, setFormContrato] = useState(() => {
        if (contrato) return formularioContrato(contrato);
        const vacio = contratoVacio(proyecto?.id);
        return procesoInicial ? { ...vacio, proceso_id: String(procesoInicial) } : vacio;
    });
    // Errores del formulario por campo, de la validación local o de un 422.
    const [erroresContrato, setErroresContrato] = useState({});
    const [guardandoContrato, setGuardandoContrato] = useState(false);
    // Evita un segundo envío mientras el primero no termina (el estado tarda un render en verse).
    const enviandoContrato = useRef(false);
    // Cada error de validación sube el contador: tras pintar, se enfoca el primer campo con error.
    const [focoErrorContrato, setFocoErrorContrato] = useState(0);
    const formularioContratoRef = useRef(null);
    // Turno de la carga de procesos: una respuesta atrasada (de otra carga) se descarta.
    const turnoProcesosContrato = useRef(0);
    // Catálogo de tipos de contrato y procesos del proyecto; null mientras no cargan.
    const [tiposPropios, setTiposPropios] = useState(null);
    const tipos = tiposContrato === undefined ? tiposPropios : tiposContrato;
    const [procesosContrato, setProcesosContrato] = useState(procesosIniciales);

    const [anexos, setAnexos] = useState(() => anexosDelContrato(contrato));
    const [formAnexo, setFormAnexo] = useState(ANEXO_VACIO);

    const [actasFinancieras, setActasFinancieras] = useState(contrato?.avancesFinancieros || []);
    const [formActaFinanciera, setFormActaFinanciera] = useState(ACTA_VACIA);

    const [actividades, setActividades] = useState(contrato?.actividades || []);
    const [formActividad, setFormActividad] = useState(ACTIVIDAD_VACIA);
    const [actividadRegistrandoAvance, setActividadRegistrandoAvance] = useState(null);
    const [formNuevoAvance, setFormNuevoAvance] = useState(AVANCE_VACIO);

    const [modificaciones, setModificaciones] = useState(contrato?.modificaciones || []);
    const [formModificacion, setFormModificacion] = useState(MODIFICACION_VACIA);
    const [resumenModificacion, setResumenModificacion] = useState(null);

    useEffect(() => {
        // Los estados de los procesos pueden haber cambiado desde que se abrió la lista.
        cargarProcesosContrato(contrato?.proyecto ?? proyecto?.id);
        if (tiposContrato === undefined) pedirTiposContrato().then(setTiposPropios);
        if (!contrato && procesoInicial) precargarDesdeProceso(String(procesoInicial));
    }, []);

    // Manejar cambios en el formulario de contratos
    const handleContratoChange = (e) => {
        const { name, value, type, checked } = e.target;
        setErroresContrato(prev => (prev[name] ? { ...prev, [name]: undefined } : prev));
        if (name === 'proyecto') {
            // El proceso era del proyecto anterior: se quita y se cargan los del elegido.
            setErroresContrato(prev => (prev.proceso_id ? { ...prev, proceso_id: undefined } : prev));
            setFormContrato(prev => cambiarProyecto(prev, value, editingContrato));
            // Mientras llegan los del elegido no se ofrecen los del proyecto anterior.
            setProcesosContrato(null);
            cargarProcesosContrato(value);
            return;
        }
        if (name === 'proceso_id') {
            setFormContrato(prev => ({ ...prev, proceso_id: value }));
            // R8: en un contrato nuevo, el proceso elegido llena los campos que siguen vacíos.
            if (value && !editingContrato) precargarDesdeProceso(value);
            return;
        }
        if (name === 'anticipo' && !checked) {
            setFormContrato(prev => ({ ...prev, anticipo: false, porcentaje_anticipo: '' }));
            return;
        }

        if (name === 'porcentaje_anticipo') {
            if (value === '') {
                setFormContrato(prev => ({ ...prev, porcentaje_anticipo: '0' }));
                return;
            }
            if (value < 0 || value > 50) {
                Swal.fire({ icon: 'warning', title: 'El porcentaje de anticipo debe ser entre 0 y 50' });
                return;
            }
            setFormContrato(prev => ({ ...prev, porcentaje_anticipo: value }));
            return;
        }

        setFormContrato(prev => ({ ...prev, [name]: type === 'checkbox' ? checked : value }));
    };

    // Valor inicial con los miles agrupados ("1.234.567,89"), como el valor de los procesos.
    const handleContratoValorChange = (e) => {
        const { value } = e.target;
        setErroresContrato(prev => (prev.valor_inicial ? { ...prev, valor_inicial: undefined } : prev));
        setFormContrato(prev => ({ ...prev, valor_inicial: valorEnEdicion(value, prev.valor_inicial) }));
    };

    const precargarDesdeProceso = async (procesoId) => {
        try {
            const { data } = await axios.get(`/procesos/${procesoId}/precarga-contrato`);
            // Si entretanto se eligió otro proceso, esta precarga ya no aplica.
            setFormContrato(prev => (prev.proceso_id === procesoId ? aplicarPrecarga(prev, data?.precarga) : prev));
        } catch (error) {
            avisarError('No se pudieron traer los datos del proceso', error);
        }
    };

    // Devuelve la lista pedida (o null si falló); solo la última carga llega al formulario.
    const cargarProcesosContrato = async (proyectoId) => {
        const numero = ++turnoProcesosContrato.current;
        const lista = await pedirProcesosProyecto(proyectoId);
        if (numero === turnoProcesosContrato.current) setProcesosContrato(lista);
        return lista;
    };

    useEffect(() => {
        if (!focoErrorContrato) return;
        const campo = primerCampoConError(erroresContrato);
        if (campo) formularioContratoRef.current?.querySelector(`[name="${campo}"]`)?.focus();
    }, [focoErrorContrato]);

    // Manejar cambios en el formulario de anexos
    const handleAnexoChange = (e) => {
        const { name, value, files } = e.target;
        if (name === 'archivo') {
            setFormAnexo(prev => ({ ...prev, [name]: files[0] }));
        } else {
            setFormAnexo(prev => ({ ...prev, [name]: value }));
        }
    };

    // Agregar anexo
    const handleAddAnexo = async () => {
        if (!formAnexo.descripcion || !formAnexo.archivo) {
            Swal.fire({ icon: 'warning', title: 'La descripción y el archivo son obligatorios' });
            return;
        }

        try {
            // Crear FormData para enviar el archivo
            const formData = new FormData();
            formData.append('archivo', formAnexo.archivo);
            formData.append('descripcion', formAnexo.descripcion);

            // Subir archivo al servidor
            const response = await axios.post('/subirAnexo', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                }
            });

            if (response.data.success) {
                const nuevoAnexo = {
                    id: Date.now(),
                    descripcion: formAnexo.descripcion,
                    nombreArchivo: formAnexo.archivo.name,
                    rutaArchivo: response.data.ruta,
                    fecha: new Date().toISOString().split('T')[0]
                };

                setAnexos(prev => [...prev, nuevoAnexo]);
                setFormAnexo(ANEXO_VACIO);

                Swal.fire({
                    icon: 'success',
                    title: 'Anexo agregado correctamente',
                    text: response.data.mensaje
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al subir el archivo',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al subir anexo:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error al subir el archivo',
                text: error.response?.data?.mensaje || 'Error interno del servidor'
            });
        }
    };

    // Eliminar anexo
    const handleDeleteAnexo = async (id) => {
        try {
            const response = await axios.post('/eliminarAnexo', { id: id });

            if (response.data.success) {
                setAnexos(prev => prev.filter(anexo => anexo.id !== id));
                Swal.fire({
                    icon: 'success',
                    title: 'Anexo eliminado correctamente',
                    text: response.data.mensaje
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al eliminar el anexo',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al eliminar anexo:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error al eliminar el anexo',
                text: error.response?.data?.mensaje || 'Error interno del servidor'
            });
        }
    };

    const handleChangeValorFacturado = (e) => {
        const limpio = limpiarMoneda(e.target.value);
        const valorFacturado = numeroDeMoneda(limpio);
        const { amortizacion, valorPresente, porcentajeEjecutado } = calculoActa(valorFacturado, formContrato, editingContrato);
        setFormActaFinanciera(prev => ({
            ...prev,
            valor_facturado: limpio ? monedaEnEdicion(limpio) : '',
            amortizacion_50: limpio ? amortizacion : '',
            valor_presente_acta: limpio ? valorPresente : '',
            porcentaje_ejecutado: limpio ? porcentajeEjecutado : ''
        }));
    };

    // Si el usuario cambia el anticipo/porcentaje/valor del contrato después de haber escrito
    // el valor facturado del acta, recalcula amortización/valor presente/% ejecutado.
    useEffect(() => {
        setFormActaFinanciera(prev => {
            if (prev.valor_facturado === '') return prev;
            const valorFacturado = numeroDeMoneda(prev.valor_facturado);
            const { amortizacion, valorPresente, porcentajeEjecutado } = calculoActa(valorFacturado, formContrato, editingContrato);
            return { ...prev, amortizacion_50: amortizacion, valor_presente_acta: valorPresente, porcentaje_ejecutado: porcentajeEjecutado };
        });
    }, [formContrato.anticipo, formContrato.porcentaje_anticipo, formContrato.valor_inicial, editingContrato]);

    // Manejar cambios en el formulario de acta financiera
    const handleActaFinancieraChange = (e) => {
        const { name, value, files } = e.target;
        if (name === 'archivo') {
            setFormActaFinanciera(prev => ({ ...prev, archivo: files[0] }));
        } else {
            setFormActaFinanciera(prev => ({ ...prev, [name]: value }));
        }
    };

    // Agregar acta de avance financiero
    const handleAddActaFinanciera = async () => {
        if (!formActaFinanciera.fecha_acta || !formActaFinanciera.valor_facturado) {
            Swal.fire({ icon: 'warning', title: 'La fecha del acta y el valor facturado son obligatorios' });
            return;
        }

        try {
            let rutaAnexo = null;
            if (formActaFinanciera.archivo) {
                const formData = new FormData();
                formData.append('archivo', formActaFinanciera.archivo);
                const uploadResponse = await axios.post('/subirActa', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });
                if (!uploadResponse.data.success) {
                    Swal.fire({ icon: 'error', title: 'Error al subir el archivo', text: uploadResponse.data.mensaje });
                    return;
                }
                rutaAnexo = uploadResponse.data.ruta;
            }

            const response = await axios.post('/guardarActaFinanciera', datosActa(editingContrato.id, formActaFinanciera, rutaAnexo));

            if (response.data.success) {
                setActasFinancieras(prev => [
                    {
                        ...formActaFinanciera,
                        id: response.data.id,
                        anexo: rutaAnexo,
                        valor_facturado: numeroDeMoneda(formActaFinanciera.valor_facturado),
                    },
                    ...prev
                ]);
                setFormActaFinanciera(ACTA_VACIA);
                recargar(editingContrato.id);
                Swal.fire({ icon: 'success', title: 'Acta agregada correctamente' });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al guardar el acta',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al agregar acta financiera:', error);
            Swal.fire({ icon: 'error', title: 'Error al agregar el acta', text: error.response?.data?.mensaje || 'Error interno del servidor' });
        }
    };

    // Eliminar acta de avance financiero
    const handleDeleteActaFinanciera = async (id) => {
        try {
            const response = await axios.post('/eliminarActaFinanciera', { id });
            if (response.data.success) {
                setActasFinancieras(prev => prev.filter(acta => acta.id !== id));
                recargar(editingContrato.id);
                Swal.fire({ icon: 'success', title: 'Acta eliminada correctamente' });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al eliminar el acta',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al eliminar acta financiera:', error);
            Swal.fire({ icon: 'error', title: 'Error al eliminar el acta', text: error.response?.data?.mensaje || 'Error interno del servidor' });
        }
    };

    // Manejar cambios en el formulario de nueva actividad
    const handleActividadChange = (e) => {
        const { name, value } = e.target;
        setFormActividad(prev => ({ ...prev, [name]: value }));
    };

    // Agregar actividad ponderada
    const handleAddActividad = async () => {
        if (!formActividad.nombre || !formActividad.peso) {
            Swal.fire({ icon: 'warning', title: 'El nombre y el peso son obligatorios' });
            return;
        }
        try {
            const response = await axios.post('/guardarActividad', {
                contrato_id: editingContrato.id,
                nombre: formActividad.nombre,
                peso: formActividad.peso
            });
            if (response.data.success) {
                setActividades(prev => [
                    ...prev,
                    { id: response.data.id, nombre: formActividad.nombre, peso: parseInt(formActividad.peso, 10), ultimo_avance: null, fecha_ultimo_avance: null }
                ]);
                setFormActividad(ACTIVIDAD_VACIA);
                recargar(editingContrato.id);
                Swal.fire({ icon: 'success', title: 'Actividad agregada correctamente' });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al agregar la actividad',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al agregar actividad:', error);
            Swal.fire({ icon: 'error', title: 'Error al agregar la actividad', text: error.response?.data?.mensaje || 'Error interno del servidor' });
        }
    };

    // Eliminar actividad
    const handleDeleteActividad = async (id) => {
        try {
            const response = await axios.post('/eliminarActividad', { id });
            if (response.data.success) {
                setActividades(prev => prev.filter(act => act.id !== id));
                recargar(editingContrato.id);
                Swal.fire({ icon: 'success', title: 'Actividad eliminada correctamente' });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al eliminar la actividad',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al eliminar actividad:', error);
            Swal.fire({ icon: 'error', title: 'Error al eliminar la actividad', text: error.response?.data?.mensaje || 'Error interno del servidor' });
        }
    };

    // Manejar cambios en el mini-formulario de "registrar avance"
    const handleNuevoAvanceChange = (e) => {
        const { name, value } = e.target;
        setFormNuevoAvance(prev => ({ ...prev, [name]: value }));
    };

    // Registrar un nuevo avance (histórico, por fecha) para una actividad
    const handleRegistrarAvance = async (actividadId) => {
        if (!formNuevoAvance.fecha || formNuevoAvance.porcentaje_ejecucion === '') {
            Swal.fire({ icon: 'warning', title: 'La fecha y el porcentaje son obligatorios' });
            return;
        }
        try {
            const response = await axios.post('/registrarAvanceActividad', {
                actividad_id: actividadId,
                fecha: formNuevoAvance.fecha,
                porcentaje_ejecucion: formNuevoAvance.porcentaje_ejecucion
            });
            if (response.data.success) {
                setActividades(prev => prev.map(act => (
                    act.id === actividadId ? actividadConAvance(act, formNuevoAvance.fecha, formNuevoAvance.porcentaje_ejecucion) : act
                )));
                setActividadRegistrandoAvance(null);
                setFormNuevoAvance(AVANCE_VACIO);
                recargar(editingContrato.id);
                Swal.fire({ icon: 'success', title: 'Avance registrado correctamente' });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al registrar el avance',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al registrar avance:', error);
            Swal.fire({ icon: 'error', title: 'Error al registrar el avance', text: error.response?.data?.mensaje || 'Error interno del servidor' });
        }
    };

    // Calcular avance financiero automáticamente: suma de valor_facturado de todas las actas / valor vigente del contrato
    useEffect(() => {
        const porcentaje = avanceFinancieroDeActas(actasFinancieras, valorVigente(formContrato, editingContrato));
        setFormContrato(prev => (prev.avance_financiero === porcentaje ? prev : { ...prev, avance_financiero: porcentaje }));
    }, [actasFinancieras, formContrato.valor_inicial, editingContrato]);

    // Calcular avance físico: suma ponderada de (peso x último % de ejecución) de cada actividad
    const handleCalcularAvanceFisico = () => {
        if (actividades.length === 0) {
            Swal.fire({ icon: 'warning', title: 'No hay actividades de avance físico registradas' });
            return;
        }
        setFormContrato(prev => ({ ...prev, avance_fisico: avanceFisicoDeActividades(actividades) }));
    };

    // Primer contrato de un proceso adjudicado: se propone pasar el proceso a celebrado.
    const proponerProcesoCelebrado = async (guardado) => {
        const { isConfirmed } = await Swal.fire({
            icon: 'question',
            titleText: '¿Pasar el proceso a celebrado?',
            text: 'Es el primer contrato de este proceso adjudicado. El cambio queda en el historial del proceso.',
            showCancelButton: true,
            confirmButtonText: 'Sí, pasar a celebrado',
            cancelButtonText: 'Ahora no',
        });
        if (!isConfirmed) return;
        // Recién cargado, el proceso trae su `actualizado` vigente.
        const lista = await cargarProcesosContrato(guardado.proyecto);
        const proceso = (lista ?? []).find(p => p.id === guardado.proceso_id);
        if (!proceso) {
            Swal.fire({ icon: 'error', titleText: 'No se pudo cambiar el estado', text: 'No se pudo cargar el proceso: cambia su estado desde Procesos.' });
            return;
        }
        try {
            await axios.post(`/procesos/${proceso.id}/estado`, datosEstado(proceso, 'celebrado', null));
            await cargarProcesosContrato(guardado.proyecto);
            await Swal.fire({ icon: 'success', title: 'El proceso quedó celebrado' });
        } catch (error) {
            if (esEdicionDesactualizada(error)) {
                await Swal.fire({ icon: 'warning', titleText: 'No se cambió el estado del proceso', text: mensajeApi(error) });
                return;
            }
            avisarError('No se pudo cambiar el estado del proceso', error);
        }
    };

    // Guardar contrato (agregar o actualizar), una vez a la vez.
    const handleSaveContrato = async () => {
        if (enviandoContrato.current) return;
        enviandoContrato.current = true;
        try {
            await enviarContrato();
        } finally {
            enviandoContrato.current = false;
        }
    };

    const enviarContrato = async () => {
        const locales = validarContrato(formContrato);
        setErroresContrato(locales);
        if (Object.keys(locales).length) {
            setContratoActiveTab('informacion');
            setFocoErrorContrato(n => n + 1);
            return;
        }

        setGuardandoContrato(true);
        let respuesta;
        try {
            respuesta = await axios.post('/guardarContrato', {
                formContrato: datosContrato(formContrato, editingContrato),
                anexos: anexosParaGuardar(anexos),
            });
        } catch (error) {
            if (esEdicionDesactualizada(error)) {
                // Otra persona cambió el contrato: se avisa y se recarga la lista. El formulario
                // sigue abierto para copiar lo escrito; al reabrirlo trae lo último.
                await Promise.all([
                    recargar(editingContrato?.id),
                    Swal.fire({ icon: 'warning', titleText: 'No se guardó tu cambio', text: mensajeApi(error) }),
                ]);
                return;
            }
            const errores = erroresGuardarContrato(error);
            if (!errores) {
                avisarError('No se pudo guardar el contrato', error);
                return;
            }
            // Los de campos con control, junto a su control; los demás (el proyecto, los anexos), en un aviso.
            if (Object.keys(errores.campos).length) {
                setErroresContrato(errores.campos);
                setContratoActiveTab('informacion');
                setFocoErrorContrato(n => n + 1);
            }
            if (errores.otros.length) {
                Swal.fire({ icon: 'error', titleText: 'No se pudo guardar el contrato', html: htmlErrores('Revisa lo siguiente:', errores.otros) });
            } else if (!Object.keys(errores.campos).length) {
                avisarError('No se pudo guardar el contrato', error);
            }
            return;
        } finally {
            setGuardandoContrato(false);
        }

        const { contrato: guardado, avisos = [], proponer_celebrado: proponerCelebrado } = respuesta.data ?? {};
        if (avisos.length) {
            await Swal.fire({ icon: 'warning', titleText: 'Contrato guardado, con avisos', html: htmlErrores('Revisa lo siguiente:', avisos) });
        } else {
            await Swal.fire({ icon: 'success', title: 'Contrato guardado correctamente' });
        }
        if (proponerCelebrado && guardado?.proceso_id) await proponerProcesoCelebrado(guardado);

        onGuardado(guardado);
    };

    // Refrescar los datos del contrato abierto tras registrar o eliminar una modificación
    const refrescarContratoAbierto = async () => {
        const actual = await recargar(formContrato.id);
        if (formContrato.id && actual) {
            setModificaciones(actual.modificaciones || []);
            // Los vigentes recalculados salen del contrato recargado; lo escrito en el
            // formulario se conserva. Registrar una modificación no cambia la versión
            // (updated_at), y se guarda la que tenía al abrirse: así un cambio de otra
            // persona entretanto sigue dando 409.
            setEditingContrato(prev => ({ ...actual, updated_at: prev?.updated_at ?? actual.updated_at }));
        }
    };

    // Manejar cambios en el formulario de modificación (otrosí)
    const handleModificacionChange = (e) => {
        const { name, value } = e.target;
        if (name === 'valor_adicion') {
            // Preservar la coma decimal (centavos), igual que los demás inputs de moneda.
            const limpio = limpiarMoneda(value);
            setFormModificacion(prev => ({ ...prev, valor_adicion: limpio ? monedaEnEdicion(limpio) : '' }));
            return;
        }
        setFormModificacion(prev => ({ ...prev, [name]: value }));
    };

    // Guardar una modificación (otrosí) y recalcular el contrato
    const guardarModificacion = async () => {
        if (!formContrato.id) {
            Swal.fire({ icon: 'warning', title: 'Guarda primero el contrato antes de registrar modificaciones.' });
            return;
        }
        if (!formModificacion.fecha_modificacion) {
            Swal.fire({ icon: 'warning', title: 'La fecha de la modificación es obligatoria.' });
            return;
        }
        try {
            const resp = await axios.post('/guardarModificacionContrato', datosModificacion(formContrato.id, formModificacion));
            setResumenModificacion(resp.data.resumen);
            if (resp.data.resumen?.supera_limite) {
                Swal.fire({ icon: 'warning', title: 'Adición supera el 50% legal', text: 'Se registró de todas formas.' });
            }
            await refrescarContratoAbierto();
            setFormModificacion(MODIFICACION_VACIA);
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error al guardar la modificación', text: e.response?.data?.error || e.message });
        }
    };

    // Eliminar una modificación (otrosí) y recalcular el contrato
    const eliminarModificacion = async (id) => {
        const result = await Swal.fire({
            icon: 'warning', title: '¿Eliminar esta modificación?',
            text: 'Se recalculará el valor y la fecha fin del contrato.',
            showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
        });
        if (!result.isConfirmed) return;
        try {
            const resp = await axios.post('/eliminarModificacionContrato', { id });
            setResumenModificacion(resp.data.resumen);
            await refrescarContratoAbierto();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error al eliminar la modificación', text: e.response?.data?.error || e.message });
        }
    };

    // Formulario de contrato: proceso elegido, nota R4 y vigentes que difieren de los iniciales.
    // El proceso que el contrato ya tiene solo se ofrece mientras siga en su proyecto.
    const procesoActual = procesoDelContrato(editingContrato, formContrato);
    const procesoFormulario = procesoElegido(procesosContrato, procesoActual, formContrato.proceso_id);
    const datosProcesoContrato = procesoFormulario ? datosDelProceso(procesoFormulario) : null;
    const notaProcesoContrato = notaProceso(procesosContrato, formContrato.proceso_id);
    const vigentesContrato = vigentesDistintos(editingContrato);

    // Propiedades comunes de un control del formulario de contrato: valor, cambio y aviso de error.
    // Sin permiso para editar, el control queda deshabilitado.
    const controlContrato = (campo) => ({
        id: campo,
        name: campo,
        value: formContrato[campo] ?? '',
        onChange: handleContratoChange,
        'aria-invalid': Boolean(erroresContrato[campo]),
        'aria-describedby': erroresContrato[campo] ? `${campo}-error` : undefined,
        ...(puedeEditar ? {} : { disabled: true }),
    });
    const campoContrato = (campo, etiqueta, control) => (
        <div className="form-group">
            <label htmlFor={campo}>{etiqueta}</label>
            {control}
            {erroresContrato[campo] && <small id={`${campo}-error`} className="ct-error-campo" role="alert">{erroresContrato[campo]}</small>}
        </div>
    );

    return (
        <>
            <div className="contratos-modal-body">
                <div className="contrato-form-header">
                    <h3>{editingContrato ? 'Editar Contrato' : 'Agregar Nuevo Contrato'}</h3>
                    <button
                        onClick={onCancelar}
                        className="btn-back-to-list"
                    >
                        ← Volver a la lista
                    </button>
                </div>

                {/* Pestañas del formulario de contrato */}
                <div className="contrato-tabs">
                    <button
                        className={`contrato-tab ${contratoActiveTab === 'informacion' ? 'active' : ''}`}
                        onClick={() => setContratoActiveTab('informacion')}
                    >
                        <FontAwesomeIcon icon={faInfo} />   Información del Contrato
                    </button>
                    <button
                        className={`contrato-tab ${contratoActiveTab === 'anexos' ? 'active' : ''}`}
                        onClick={() => setContratoActiveTab('anexos')}
                    >
                        <FontAwesomeIcon icon={faFile} /> Anexos ({anexos.length})
                    </button>
                    {editingContrato && (
                        <button
                            className={`contrato-tab ${contratoActiveTab === 'avance_financiero' ? 'active' : ''}`}
                            onClick={() => setContratoActiveTab('avance_financiero')}
                        >
                            <FontAwesomeIcon icon={faCalculator} /> Avance Financiero ({actasFinancieras.length})
                        </button>
                    )}
                    {editingContrato && (
                        <button
                            className={`contrato-tab ${contratoActiveTab === 'avance_fisico' ? 'active' : ''}`}
                            onClick={() => setContratoActiveTab('avance_fisico')}
                        >
                            <FontAwesomeIcon icon={faCalculator} /> Avance Físico ({actividades.length})
                        </button>
                    )}
                    {editingContrato && (
                        <button
                            className={`contrato-tab ${contratoActiveTab === 'modificaciones' ? 'active' : ''}`}
                            onClick={() => setContratoActiveTab('modificaciones')}
                        >
                            <FontAwesomeIcon icon={faFile} /> Modificaciones ({modificaciones.length})
                        </button>
                    )}
                </div>

                {/* Contenido de la pestaña de información */}
                {contratoActiveTab === 'informacion' && (
                    <div className="contratos-modal-section">
                        <p className="contratos-info">
                            Completa los datos del contrato. Los campos marcados con * son obligatorios.
                        </p>
                        <div className="contrato-form" ref={formularioContratoRef}>
                            {proyectos !== undefined && campoContrato('proyecto', 'Proyecto', (
                                // Los errores del proyecto llegan en un aviso (erroresGuardarContrato): aquí, solo la nota.
                                <select {...controlContrato('proyecto')} aria-describedby={puedeEditar ? 'proyecto-nota' : undefined}>
                                    {opcionesProyecto(proyectos, editingContrato).map(o => (
                                        <option key={o.valor} value={o.valor}>{o.etiqueta}</option>
                                    ))}
                                </select>
                            ))}
                            {proyectos !== undefined && puedeEditar && (
                                <p id="proyecto-nota" className="contrato-nota">
                                    {editingContrato?.proyecto
                                        ? 'Puedes pasar el contrato a otro proyecto, pero no dejarlo sin proyecto.'
                                        : 'Opcional: un contrato puede no tener proyecto. Si lo eliges, puedes vincularlo a uno de sus procesos.'}
                                </p>
                            )}
                            <div className="form-row">
                                {campoContrato('proceso_id', 'Proceso de contratación', (
                                    <select {...controlContrato('proceso_id')}>
                                        {opcionesProceso(procesosContrato, procesoActual).map(o => (
                                            <option key={o.valor} value={o.valor}>{o.etiqueta}</option>
                                        ))}
                                    </select>
                                ))}
                                {campoContrato('tipo_contrato_id', 'Tipo de contrato', (
                                    <select {...controlContrato('tipo_contrato_id')}>
                                        <option value="">Sin tipo</option>
                                        {opcionesTipoContrato(tipos, editingContrato).map(o => (
                                            <option key={o.valor} value={o.valor}>{o.etiqueta}</option>
                                        ))}
                                    </select>
                                ))}
                            </div>
                            {notaProcesoContrato && <p className="contrato-nota">{notaProcesoContrato}</p>}
                            {datosProcesoContrato && (
                                <dl className="contrato-proceso-datos" aria-label="Datos del proceso">
                                    <div><dt>Número del proceso</dt><dd>{datosProcesoContrato.numero}</dd></div>
                                    <div><dt>Modalidad</dt><dd>{datosProcesoContrato.modalidad}</dd></div>
                                    <div><dt>Plataforma</dt><dd>{datosProcesoContrato.plataforma}</dd></div>
                                    <div>
                                        <dt>Enlace</dt>
                                        <dd>
                                            {datosProcesoContrato.enlace
                                                ? <a className="ct-enlace" href={datosProcesoContrato.enlace} target="_blank" rel="noopener noreferrer">Ver el proceso</a>
                                                : '—'}
                                        </dd>
                                    </div>
                                </dl>
                            )}
                            <div className="form-row">
                                {campoContrato('n_contrato', 'N° Contrato *', <input type="text" maxLength={100} {...controlContrato('n_contrato')} />)}
                                {campoContrato('estado', 'Estado *', (
                                    <select {...controlContrato('estado')}>
                                        <option value="">Elige el estado</option>
                                        {ESTADOS_CONTRATO.map(([valor, etiqueta]) => (
                                            <option key={valor} value={valor}>{etiqueta}</option>
                                        ))}
                                    </select>
                                ))}
                            </div>
                            {campoContrato('objeto', 'Objeto *', (
                                <textarea rows="4" maxLength={5000} placeholder="Escribe el objeto del contrato" {...controlContrato('objeto')} />
                            ))}
                            <div className="form-row">
                                {campoContrato('contratante', 'Contratante', <input type="text" maxLength={5000} {...controlContrato('contratante')} />)}
                                {campoContrato('contratante_nit', 'NIT de la entidad contratante', <input type="text" maxLength={30} {...controlContrato('contratante_nit')} />)}
                            </div>
                            <div className="form-row">
                                {campoContrato('contratista', 'Contratista', <input type="text" maxLength={5000} {...controlContrato('contratista')} />)}
                                {campoContrato('contratista_identificacion', 'Identificación del contratista', <input type="text" maxLength={30} {...controlContrato('contratista_identificacion')} />)}
                            </div>
                            <div className="form-row">
                                {campoContrato('valor_inicial', 'Valor inicial *', (
                                    <input type="text" inputMode="decimal" placeholder="0" {...controlContrato('valor_inicial')} onChange={handleContratoValorChange} />
                                ))}
                                {vigentesContrato.valor && (
                                    <div className="form-group">
                                        <label htmlFor="valor_vigente">Valor vigente</label>
                                        <input type="text" id="valor_vigente" disabled value={textoValor(valorVigente(formContrato, editingContrato))} aria-describedby="vigentes-nota" />
                                    </div>
                                )}
                            </div>
                            <div className="form-row">
                                {campoContrato('fecha_suscripcion', 'Fecha de suscripción', <input type="date" {...controlContrato('fecha_suscripcion')} />)}
                                {campoContrato('fecha_acta_inicio', 'Fecha del acta de inicio', <input type="date" {...controlContrato('fecha_acta_inicio')} />)}
                            </div>
                            <div className="form-row">
                                {campoContrato('fecha_inicio', 'Fecha de inicio', <input type="date" {...controlContrato('fecha_inicio')} />)}
                                {campoContrato('fecha_fin_inicial', 'Fecha fin inicial', <input type="date" {...controlContrato('fecha_fin_inicial')} />)}
                                {vigentesContrato.fechaFin && (
                                    <div className="form-group">
                                        <label htmlFor="fecha_fin_vigente">Fecha fin vigente</label>
                                        <input type="date" id="fecha_fin_vigente" disabled value={String(editingContrato.fecha_fin).slice(0, 10)} aria-describedby="vigentes-nota" />
                                    </div>
                                )}
                            </div>
                            {(vigentesContrato.valor || vigentesContrato.fechaFin) && (
                                <p id="vigentes-nota" className="contrato-nota">
                                    Los vigentes suman al valor inicial y a la fecha fin inicial las adiciones y prórrogas de la pestaña Modificaciones; se recalculan al guardar.
                                </p>
                            )}
                            <div className="form-row">
                                {campoContrato('numero_rp', 'Número del RP', <input type="text" maxLength={40} {...controlContrato('numero_rp')} />)}
                                {campoContrato('fecha_rp', 'Fecha del RP', <input type="date" {...controlContrato('fecha_rp')} />)}
                            </div>
                            <div className="form-row">
                                {campoContrato('supervisor', 'Supervisor', <input type="text" maxLength={255} {...controlContrato('supervisor')} />)}
                                {campoContrato('interventoria', 'Interventoría', <input type="text" maxLength={5000} {...controlContrato('interventoria')} />)}
                            </div>
                            <div className="form-row">
                                {campoContrato('avance_financiero', 'Avance Financiero', <input type="text" disabled {...controlContrato('avance_financiero')} />)}
                                {campoContrato('avance_fisico', 'Avance Físico', (
                                    <div className="avance-fisico-container">
                                        <input type="text" disabled {...controlContrato('avance_fisico')} />
                                        {puedeEditar && <button type="button" className="btn-calcular-avance" title='Calcular Avance Físico' onClick={handleCalcularAvanceFisico}><FontAwesomeIcon icon={faCalculator} /></button>}
                                    </div>
                                ))}
                            </div>
                            <div className="form-row">
                                <div className="form-group anticipo-group">

                                    <label htmlFor="anticipo" className="checkbox-label">
                                        <input
                                            type="checkbox"
                                            id="anticipo"
                                            name="anticipo"
                                            checked={formContrato.anticipo}
                                            onChange={handleContratoChange}
                                            disabled={!puedeEditar}
                                        />
                                        <span>¿Lleva anticipo?</span>
                                    </label>

                                    <div className="anticipo-porcentaje">
                                        <label htmlFor="porcentaje_anticipo">% de Anticipo</label>
                                        <input
                                            type="number"
                                            id="porcentaje_anticipo"
                                            name="porcentaje_anticipo"
                                            value={formContrato.porcentaje_anticipo}
                                            onChange={handleContratoChange}
                                            min="0"
                                            max="50"
                                            disabled={!formContrato.anticipo || !puedeEditar}
                                            aria-invalid={Boolean(erroresContrato.porcentaje_anticipo)}
                                            aria-describedby={erroresContrato.porcentaje_anticipo ? 'porcentaje_anticipo-error' : undefined}
                                        />
                                        {erroresContrato.porcentaje_anticipo && <small id="porcentaje_anticipo-error" className="ct-error-campo" role="alert">{erroresContrato.porcentaje_anticipo}</small>}
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Contenido de la pestaña de anexos */}
                {contratoActiveTab === 'anexos' && (
                    <div className="contratos-modal-section">
                        <p className="contratos-info">
                            Gestione los anexos del contrato. Puede agregar y eliminar documentos relacionados.
                        </p>

                        {/* Formulario para agregar anexo */}
                        {puedeEditar && (
                            <div className="anexo-form">
                                <h4>Agregar Nuevo Anexo</h4>
                                <div className="form-row">
                                    <div className="form-group">
                                        <label htmlFor="descripcion_anexo">Descripción *</label>
                                        <input
                                            type="text"
                                            id="descripcion_anexo"
                                            name="descripcion"
                                            value={formAnexo.descripcion}
                                            onChange={handleAnexoChange}
                                            placeholder="Descripción del anexo"
                                        />
                                    </div>
                                    <div className="form-group">
                                        <label htmlFor="archivo_anexo">Archivo *</label>
                                        <input
                                            type="file"
                                            id="archivo_anexo"
                                            name="archivo"
                                            onChange={handleAnexoChange}
                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                        />
                                    </div>
                                    <div className="form-group">
                                        <button
                                            type="button"
                                            onClick={handleAddAnexo}
                                            className="btn-add-anexo"
                                            disabled={!formAnexo.descripcion || !formAnexo.archivo}
                                        >
                                            <FontAwesomeIcon icon={faPlus} /> Agregar Anexo
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Lista de anexos */}
                        <div className="anexos-list">
                            <h4>Anexos del Contrato ({anexos.length})</h4>
                            {anexos.length === 0 ? (
                                <div className="no-anexos">
                                    <p>No hay anexos agregados para este contrato</p>
                                </div>
                            ) : (
                                <div className="anexos-table">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Descripción</th>
                                                <th>Archivo</th>
                                                <th>Fecha</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {anexos.map(anexo => (
                                                <tr key={anexo.id}>
                                                    <td>{anexo.descripcion}</td>
                                                    <td>{anexo.nombreArchivo}</td>
                                                    <td>{anexo.fecha}</td>
                                                    <td>
                                                        <div className="anexo-actions">
                                                            <button
                                                                type="button"
                                                                onClick={() => window.open('/' + anexo.rutaArchivo, '_blank')}
                                                                className="btn-view-anexo"
                                                                title="Ver anexo"
                                                            >
                                                                <FontAwesomeIcon icon={faEye} />
                                                            </button>
                                                            {puedeEditar && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => handleDeleteAnexo(anexo.id)}
                                                                    className="btn-delete-anexo"
                                                                    title="Eliminar anexo"
                                                                >
                                                                    <FontAwesomeIcon icon={faTrash} />
                                                                </button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* Contenido de la pestaña de avance financiero */}
                {contratoActiveTab === 'avance_financiero' && (
                    <div className="contratos-modal-section">
                        <p className="contratos-info">
                            Registre las actas de avance financiero del contrato. El avance financiero se calcula sumando el valor presente de todas las actas.
                        </p>

                        {puedeEditar && (
                            <div className="anexo-form">
                                <h4>Agregar Nueva Acta</h4>
                                <div className="form-row">
                                    <div className="form-group">
                                        <label htmlFor="descripcion_acta_financiera">Descripción</label>
                                        <input type="text" id="descripcion_acta_financiera" name="descripcion" value={formActaFinanciera.descripcion} onChange={handleActaFinancieraChange} placeholder="Descripción del acta" />
                                    </div>
                                    <div className="form-group">
                                        <label htmlFor="fecha_acta">Fecha del Acta *</label>
                                        <input type="date" id="fecha_acta" name="fecha_acta" value={formActaFinanciera.fecha_acta} onChange={handleActaFinancieraChange} />
                                    </div>
                                </div>
                                <div className="form-row">
                                    <div className="form-group">
                                        <label htmlFor="valor_facturado">Valor Facturado *</label>
                                        <input
                                            type="text"
                                            id="valor_facturado"
                                            name="valor_facturado"
                                            value={formActaFinanciera.valor_facturado}
                                            onChange={handleChangeValorFacturado}
                                            inputMode="decimal"
                                            placeholder="$ 0"
                                        />
                                    </div>
                                    <div className="form-group">
                                        <label htmlFor="amortizacion_50">
                                            Amortización ({formContrato.anticipo ? `${formContrato.porcentaje_anticipo || 0}%` : '0%'})
                                        </label>
                                        <input type="text"
                                            id="amortizacion_50"
                                            name="amortizacion_50"
                                            value={formActaFinanciera.amortizacion_50 !== '' ? formatoMoneda(formActaFinanciera.amortizacion_50) : ''}
                                            disabled
                                            placeholder="$ 0"
                                        />
                                    </div>
                                </div>
                                <div className="form-row">
                                    <div className="form-group">
                                        <label htmlFor="valor_presente_acta">Valor Presente del Acta</label>
                                        <input type="text"
                                            id="valor_presente_acta"
                                            name="valor_presente_acta"
                                            value={formActaFinanciera.valor_presente_acta !== '' ? formatoMoneda(formActaFinanciera.valor_presente_acta) : ''}
                                            disabled
                                            placeholder="$ 0"
                                        />
                                    </div>
                                    <div className="form-group">
                                        <label htmlFor="porcentaje_ejecutado">% Ejecutado</label>
                                        <input type="text" id="porcentaje_ejecutado" name="porcentaje_ejecutado" value={formActaFinanciera.porcentaje_ejecutado !== '' ? `${formActaFinanciera.porcentaje_ejecutado}%` : ''} disabled placeholder="0%" />
                                    </div>
                                </div>
                                <div className="form-row">
                                    <div className="form-group">
                                        <label htmlFor="archivo_acta_financiera">Archivo (opcional)</label>
                                        <input
                                            type="file"
                                            id="archivo_acta_financiera"
                                            name="archivo"
                                            onChange={handleActaFinancieraChange}
                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                        />
                                    </div>
                                    <div className="form-group">
                                        <button
                                            type="button"
                                            onClick={handleAddActaFinanciera}
                                            className="btn-add-anexo"
                                            disabled={!formActaFinanciera.fecha_acta || !formActaFinanciera.valor_facturado}
                                        >
                                            <FontAwesomeIcon icon={faPlus} /> Agregar Acta
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="anexos-list">
                            <h4>Actas Registradas ({actasFinancieras.length})</h4>
                            {actasFinancieras.length === 0 ? (
                                <div className="no-anexos">
                                    <p>No hay actas de avance financiero registradas</p>
                                </div>
                            ) : (
                                <div className="anexos-table">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Valor Facturado</th>
                                                <th>Valor Presente</th>
                                                <th>% Ejecutado</th>
                                                <th>Archivo</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {actasFinancieras.map(acta => (
                                                <tr key={acta.id}>
                                                    <td>{acta.fecha_acta}</td>
                                                    <td>{formatoMoneda(Number(acta.valor_facturado || 0))}</td>
                                                    <td>{formatoMoneda(Number(acta.valor_presente_acta || 0))}</td>
                                                    <td>{acta.porcentaje_ejecutado ? `${acta.porcentaje_ejecutado}%` : '-'}</td>
                                                    <td>
                                                        {acta.anexo ? (
                                                            <button type="button" onClick={() => window.open('/' + acta.anexo, '_blank')} className="btn-view-anexo" title="Ver archivo">
                                                                <FontAwesomeIcon icon={faEye} />
                                                            </button>
                                                        ) : '-'}
                                                    </td>
                                                    <td>
                                                        {puedeEditar && (
                                                            <button type="button" onClick={() => handleDeleteActaFinanciera(acta.id)} className="btn-delete-anexo" title="Eliminar acta">
                                                                <FontAwesomeIcon icon={faTrash} />
                                                            </button>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* Contenido de la pestaña de avance físico */}
                {contratoActiveTab === 'avance_fisico' && (
                    <div className="contratos-modal-section">
                        <p className="contratos-info">
                            Registre las actividades del contrato con su peso relativo (deben sumar 100%). El avance físico total es la suma de (peso × % de ejecución) de cada actividad.
                        </p>

                        {puedeEditar && (
                            <div className="anexo-form">
                                <h4>Agregar Nueva Actividad</h4>
                                <div className="form-row">
                                    <div className="form-group">
                                        <label htmlFor="nombre_actividad">Nombre *</label>
                                        <input type="text" id="nombre_actividad" name="nombre" value={formActividad.nombre} onChange={handleActividadChange} placeholder="Ej: Cimentación" />
                                    </div>
                                    <div className="form-group">
                                        <label htmlFor="peso_actividad">Peso (%) *</label>
                                        <input type="number" id="peso_actividad" name="peso" value={formActividad.peso} onChange={handleActividadChange} min="0" max="100" />
                                    </div>
                                    <div className="form-group">
                                        <button
                                            type="button"
                                            onClick={handleAddActividad}
                                            className="btn-add-anexo"
                                            disabled={!formActividad.nombre || !formActividad.peso}
                                        >
                                            <FontAwesomeIcon icon={faPlus} /> Agregar Actividad
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="anexos-list">
                            <h4>Actividades del Contrato ({actividades.length})</h4>
                            {(() => {
                                const pesos = sumaPesos(actividades);
                                return actividades.length === 0 ? (
                                    <div className="no-anexos">
                                        <p>No hay actividades registradas</p>
                                    </div>
                                ) : (
                                    <>
                                        <div className="anexos-table">
                                            <table>
                                                <thead>
                                                    <tr>
                                                        <th>Actividad</th>
                                                        <th>Peso</th>
                                                        <th>% Ejecución</th>
                                                        <th>Última actualización</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {actividades.map(actividad => (
                                                        <React.Fragment key={actividad.id}>
                                                            <tr>
                                                                <td>{actividad.nombre}</td>
                                                                <td>{actividad.peso}%</td>
                                                                <td>{actividad.ultimo_avance !== null && actividad.ultimo_avance !== undefined ? `${actividad.ultimo_avance}%` : 'Sin registrar'}</td>
                                                                <td>{actividad.fecha_ultimo_avance || '-'}</td>
                                                                <td>
                                                                    <div className="anexo-actions">
                                                                        {puedeEditar && (
                                                                            <>
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => {
                                                                                        setActividadRegistrandoAvance(actividadRegistrandoAvance === actividad.id ? null : actividad.id);
                                                                                        setFormNuevoAvance({ fecha: '', porcentaje_ejecucion: '' });
                                                                                    }}
                                                                                    className="btn-view-anexo"
                                                                                    title="Registrar avance"
                                                                                >
                                                                                    <FontAwesomeIcon icon={faCalculator} />
                                                                                </button>
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => handleDeleteActividad(actividad.id)}
                                                                                    className="btn-delete-anexo"
                                                                                    title="Eliminar actividad"
                                                                                >
                                                                                    <FontAwesomeIcon icon={faTrash} />
                                                                                </button>
                                                                            </>
                                                                        )}
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            {actividadRegistrandoAvance === actividad.id && (
                                                                <tr>
                                                                    <td colSpan="5">
                                                                        <div className="form-row">
                                                                            <div className="form-group">
                                                                                <label htmlFor="fecha_nuevo_avance">Fecha</label>
                                                                                <input type="date" id="fecha_nuevo_avance" name="fecha" value={formNuevoAvance.fecha} onChange={handleNuevoAvanceChange} />
                                                                            </div>
                                                                            <div className="form-group">
                                                                                <label htmlFor="porcentaje_nuevo_avance">% Ejecución</label>
                                                                                <input type="number" id="porcentaje_nuevo_avance" name="porcentaje_ejecucion" value={formNuevoAvance.porcentaje_ejecucion} onChange={handleNuevoAvanceChange} min="0" max="100" />
                                                                            </div>
                                                                            <div className="form-group">
                                                                                <button type="button" onClick={() => handleRegistrarAvance(actividad.id)} className="btn-add-anexo">
                                                                                    <FontAwesomeIcon icon={faSave} /> Guardar
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            )}
                                                        </React.Fragment>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                        <div className="avance-fisico-resumen">
                                            <span className={`avance-fisico-suma-pesos ${pesos !== 100 ? 'advertencia' : ''}`}>
                                                Suma de pesos: {pesos}%
                                            </span>
                                            <span className="avance-fisico-total">
                                                Avance físico total: {avanceFisicoDeActividades(actividades)}%
                                            </span>
                                        </div>
                                    </>
                                );
                            })()}
                        </div>
                    </div>
                )}

                {/* Contenido de la pestaña de modificaciones (otrosíes) */}
                {contratoActiveTab === 'modificaciones' && (
                    <div className="contratos-modal-section">
                        <p className="contratos-info">
                            Registre las adiciones y prórrogas (otrosíes) del contrato. El valor y la fecha fin vigentes se recalculan automáticamente desde la base inicial más el historial de modificaciones.
                        </p>

                        {resumenModificacion && (
                            <div className="anexo-form">
                                <h4>Resumen recalculado</h4>
                                <div className="form-row">
                                    <div className="form-group">
                                        <label>Valor inicial</label>
                                        <span>{formatoMoneda(Number(resumenModificacion.valor_inicial || 0))}</span>
                                    </div>
                                    <div className="form-group">
                                        <label>Valor vigente</label>
                                        <span>{formatoMoneda(Number(resumenModificacion.valor_vigente || 0))}</span>
                                    </div>
                                    <div className="form-group">
                                        <label>Fecha fin inicial</label>
                                        <span>{resumenModificacion.fecha_fin_inicial || '-'}</span>
                                    </div>
                                    <div className="form-group">
                                        <label>Fecha fin vigente</label>
                                        <span>{resumenModificacion.fecha_fin_vigente || '-'}</span>
                                    </div>
                                    <div className="form-group">
                                        <label>% Adicionado</label>
                                        <span className={resumenModificacion.supera_limite ? 'texto-alerta' : ''}>
                                            {resumenModificacion.porcentaje_adicionado}%
                                        </span>
                                    </div>
                                </div>
                                {resumenModificacion.supera_limite && (
                                    <p className="texto-alerta">
                                        La suma de adiciones supera el 50% legal del valor inicial.
                                    </p>
                                )}
                            </div>
                        )}

                        {puedeEditar && (
                            <div className="anexo-form">
                                <h4>Registrar Nueva Modificación</h4>
                                <div className="form-row">
                                    <div className="form-group">
                                        <label htmlFor="numero_otrosi">N° Otrosí</label>
                                        <input type="text" id="numero_otrosi" name="numero_otrosi" value={formModificacion.numero_otrosi} onChange={handleModificacionChange} placeholder="Ej: Otrosí No. 1" />
                                    </div>
                                    <div className="form-group">
                                        <label htmlFor="tipo_modificacion">Tipo *</label>
                                        <select id="tipo_modificacion" name="tipo" value={formModificacion.tipo} onChange={handleModificacionChange}>
                                            <option value="adicion">Adición</option>
                                            <option value="prorroga">Prórroga</option>
                                            <option value="adicion_prorroga">Adición y Prórroga</option>
                                        </select>
                                    </div>
                                    <div className="form-group">
                                        <label htmlFor="fecha_modificacion">Fecha *</label>
                                        <input type="date" id="fecha_modificacion" name="fecha_modificacion" value={formModificacion.fecha_modificacion} onChange={handleModificacionChange} />
                                    </div>
                                </div>
                                <div className="form-row">
                                    {formModificacion.tipo !== 'prorroga' && (
                                        <div className="form-group">
                                            <label htmlFor="valor_adicion">Valor de la adición</label>
                                            <input type="text" id="valor_adicion" name="valor_adicion" value={formModificacion.valor_adicion} onChange={handleModificacionChange} placeholder="$ 0,00" />
                                        </div>
                                    )}
                                    {formModificacion.tipo !== 'adicion' && (
                                        <div className="form-group">
                                            <label htmlFor="dias_prorroga">Días de prórroga</label>
                                            <input type="number" id="dias_prorroga" name="dias_prorroga" value={formModificacion.dias_prorroga} onChange={handleModificacionChange} min="0" />
                                        </div>
                                    )}
                                    <div className="form-group">
                                        <label htmlFor="justificacion_modificacion">Justificación</label>
                                        <input type="text" id="justificacion_modificacion" name="justificacion" value={formModificacion.justificacion} onChange={handleModificacionChange} placeholder="Motivo de la modificación" />
                                    </div>
                                </div>
                                <div className="form-row">
                                    <div className="form-group">
                                        <button
                                            type="button"
                                            onClick={guardarModificacion}
                                            className="btn-add-anexo"
                                            disabled={!formContrato.id || !formModificacion.fecha_modificacion}
                                        >
                                            <FontAwesomeIcon icon={faSave} /> Registrar Modificación
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="anexos-list">
                            <h4>Historial de Modificaciones ({modificaciones.length})</h4>
                            {modificaciones.length === 0 ? (
                                <div className="no-anexos">
                                    <p>No hay modificaciones registradas</p>
                                </div>
                            ) : (
                                <div className="anexos-table">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>N° Otrosí</th>
                                                <th>Tipo</th>
                                                <th>Valor adición</th>
                                                <th>Días prórroga</th>
                                                <th>Fecha</th>
                                                <th>Justificación</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {modificaciones.map(mod => (
                                                <tr key={mod.id}>
                                                    <td>{mod.numero_otrosi || '-'}</td>
                                                    <td>{etiquetaTipoModificacion(mod.tipo)}</td>
                                                    <td>{mod.valor_adicion ? formatoMoneda(Number(mod.valor_adicion)) : '-'}</td>
                                                    <td>{mod.dias_prorroga || '-'}</td>
                                                    <td>{mod.fecha_modificacion || '-'}</td>
                                                    <td>{mod.justificacion || '-'}</td>
                                                    <td>
                                                        <div className="anexo-actions">
                                                            {puedeEditar && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => eliminarModificacion(mod.id)}
                                                                    className="btn-delete-anexo"
                                                                    title="Eliminar modificación"
                                                                >
                                                                    <FontAwesomeIcon icon={faTrash} />
                                                                </button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <div className="modal-actions">
                <button type="button" onClick={onCancelar} className="btn-cancel">
                    <FontAwesomeIcon icon={faTimes} /> Cancelar
                </button>
                {puedeEditar && (
                    <button
                        type="button"
                        onClick={handleSaveContrato}
                        className="btn-save"
                        disabled={guardandoContrato}
                    >
                        <FontAwesomeIcon icon={faSave} /> {guardandoContrato ? 'Guardando…' : `${editingContrato ? 'Actualizar' : 'Guardar'} Contrato`}
                    </button>
                )}
            </div>
        </>
    );
};

export default ContratoEditor;
