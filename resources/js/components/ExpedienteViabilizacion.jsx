import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import { faArrowLeft, faCheck, faFileExcel, faPlus, faPrint, faSliders, faStamp, faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import Header from './Header';
import ExpedienteChecklist from './ExpedienteChecklist';
import ExpedienteConfiguracion from './ExpedienteConfiguracion';
import {
    construirHojasExcel,
    faseLegible,
    mensajeError,
    nombreArchivoExcel,
    puedeRadicar,
    sectoresConFuenteA12,
    sectoresSinTipo,
    seleccionDesde,
} from '../utils/expedienteChecklist';
import '../../css/ExpedienteViabilizacion.css';

const ROLES_EDICION = ['Administrador', 'Gestor'];
const ESPERA_RECALCULO_MS = 350;

const avisarError = (titulo, error) => Swal.fire({ icon: 'error', title: titulo, text: mensajeError(error) });

const ExpedienteViabilizacion = ({ user, onLogout }) => {
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

    const puedeEditar = ROLES_EDICION.includes(user?.rol);
    const editable = puedeEditar && expediente?.estado === 'borrador';
    const idSolicitado = Number(searchParams.get('expediente')) || null;
    const idActual = idSolicitado ?? datosProyecto?.expedientes[0]?.id ?? null;

    // Envíos de configuración en serie: si el usuario sigue marcando mientras hay uno
    // en curso, solo se manda el último estado al terminar.
    const envio = useRef({ temporizador: null, enCurso: false, pendiente: null });

    const cargarProyecto = useCallback(async () => {
        const { data } = await axios.get(`/proyectos/${proyectoId}/expedientes`);
        setDatosProyecto(data);
        return data;
    }, [proyectoId]);

    const cargarExpediente = useCallback(async (id) => {
        const { data } = await axios.get(`/expedientes/${id}`);
        setExpediente(data);
        setSeleccion(seleccionDesde(data));
    }, []);

    useEffect(() => {
        let vigente = true;
        (async () => {
            try {
                const [, respuestaCatalogo] = await Promise.all([cargarProyecto(), axios.get('/catalogo-requisitos/vigente')]);
                if (vigente) setCatalogo(respuestaCatalogo.data);
            } catch (error) {
                if (vigente) {
                    setErrorCarga(error?.response?.status === 404 ? 'El proyecto no existe.' : mensajeError(error));
                }
            }
        })();
        return () => { vigente = false; };
    }, [cargarProyecto]);

    useEffect(() => {
        clearTimeout(envio.current.temporizador);
        envio.current.pendiente = null;
        setConfigAbierta(false);

        if (!idActual) {
            setExpediente(null);
            setSeleccion(null);
            return undefined;
        }

        let vigente = true;
        cargarExpediente(idActual).catch((error) => {
            if (vigente) setErrorCarga(error?.response?.status === 404 ? 'El expediente no existe.' : mensajeError(error));
        });
        return () => { vigente = false; };
    }, [idActual, cargarExpediente]);

    useEffect(() => () => clearTimeout(envio.current.temporizador), []);

    const enviarConfiguracion = useCallback(async (id, datos) => {
        if (envio.current.enCurso) {
            envio.current.pendiente = datos;
            return;
        }
        envio.current.enCurso = true;
        setRecalculando(true);
        try {
            const { data } = await axios.put(`/expedientes/${id}/configuracion`, datos);
            if (!envio.current.pendiente) setExpediente(data);
        } catch (error) {
            envio.current.pendiente = null;
            avisarError('No se pudo actualizar la configuración', error);
            cargarExpediente(id).catch(() => {});
        } finally {
            envio.current.enCurso = false;
            const siguiente = envio.current.pendiente;
            envio.current.pendiente = null;
            if (siguiente) enviarConfiguracion(id, siguiente);
            else setRecalculando(false);
        }
    }, [cargarExpediente]);

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
            setExpediente(data);
            setSeleccion(seleccionDesde(data));
            setConfigAbierta(false);
            await cargarProyecto();
        } catch (error) {
            avisarError('No se pudo confirmar la configuración', error);
        } finally {
            setConfirmando(false);
        }
    };

    const reemplazarFila = (fila, progreso) => setExpediente((prev) => ({
        ...prev,
        progreso: progreso ?? prev.progreso,
        requisitos: prev.requisitos.map((f) => (f.id === fila.id ? fila : f)),
    }));

    const urlFila = (fila) => `/expedientes/${expediente.id}/requisitos/${fila.id}`;

    const marcar = async (fila, cumplido) => {
        reemplazarFila({ ...fila, cumplido });
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

    const descargarSoporte = async (fila) => {
        try {
            // Con axios, para enviar el JWT: un enlace directo no lleva la cabecera.
            const { data } = await axios.get(`${urlFila(fila)}/soporte`, { responseType: 'blob' });
            const url = URL.createObjectURL(data);
            const enlace = document.createElement('a');
            enlace.href = url;
            enlace.download = fila.soporte.nombre;
            enlace.click();
            URL.revokeObjectURL(url);
        } catch (error) {
            avisarError('No se pudo descargar el soporte', error);
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

    const crearExpediente = async () => {
        // Uno nuevo parte de la configuración del abierto (caso re-radicación); sin
        // ninguno, de la fase III y el sector sugerido por el proyecto. El cumplimiento
        // no se copia: cada expediente se evalúa desde cero.
        const sugerido = datosProyecto.sector_sugerido_id;
        const datos = seleccion ?? { fase_sgr: 3, sector_ids: sugerido ? [sugerido] : [] };
        try {
            const { data } = await axios.post(`/proyectos/${proyectoId}/expedientes`, datos);
            await cargarProyecto();
            abrirExpediente(data.id);
        } catch (error) {
            avisarError('No se pudo crear el expediente', error);
        }
    };

    const radicar = async () => {
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
            const { data } = await axios.post(`/expedientes/${expediente.id}/radicar`);
            setExpediente(data);
            await cargarProyecto();
        } catch (error) {
            avisarError('No se pudo radicar el expediente', error);
        }
    };

    const eliminar = async () => {
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
    };

    const exportarExcel = () => {
        const { resumen, checklist } = construirHojasExcel(expediente);
        const libro = XLSX.utils.book_new();
        const hojaResumen = XLSX.utils.json_to_sheet(resumen);
        hojaResumen['!cols'] = [{ wch: 24 }, { wch: 80 }];
        const hojaChecklist = XLSX.utils.json_to_sheet(checklist);
        hojaChecklist['!cols'] = [28, 26, 30, 30, 14, 8, 80, 40, 10, 14, 22, 40, 26].map((wch) => ({ wch }));
        XLSX.utils.book_append_sheet(libro, hojaResumen, 'Resumen');
        XLSX.utils.book_append_sheet(libro, hojaChecklist, 'Checklist');
        XLSX.writeFile(libro, nombreArchivoExcel(expediente));
    };

    const volver = () => navigate(-1);

    if (errorCarga) {
        return (
            <div className="exv-pagina">
                <Header user={user} onLogout={onLogout} />
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
    const a12 = expediente ? sectoresConFuenteA12(expediente.configuracion.sectores) : [];
    const esDesastre = expediente?.configuracion.tipologias.some((t) => t.clave === 'desastre');
    const { cumplidos = 0, total = 0, porcentaje = 0 } = expediente?.progreso ?? {};
    const sinConfigurar = Boolean(expediente) && !expediente.configurado;
    const panelVisible = sinConfigurar ? editable : configAbierta;
    const hayBorrador = datosProyecto?.expedientes.some((e) => e.estado === 'borrador');
    const sinTipo = sinConfigurar && seleccion ? sectoresSinTipo(seleccion, catalogo) : [];

    return (
        <div className="exv-pagina">
            <Header user={user} onLogout={onLogout} />

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
                                <span className="exv-eyebrow">Viabilización de proyectos · Sistema General de Regalías</span>
                                <h2>{datosProyecto.proyecto.nombre}</h2>
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
                                                #{e.id} · {e.estado === 'radicado' ? 'Radicado' : 'Borrador'} · catálogo v{e.catalogo_version.numero}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                {puedeEditar && !hayBorrador && (
                                    <button type="button" className="exv-btn" onClick={crearExpediente}>
                                        <FontAwesomeIcon icon={faPlus} /> Nuevo expediente
                                    </button>
                                )}
                            </div>
                        </div>

                        {!expediente ? (
                            <div className="exv-vacio">
                                <b>Este proyecto aún no tiene expediente de viabilización</b>
                                <p>
                                    {puedeEditar
                                        ? 'Crea uno para armar su checklist de requisitos del SGR.'
                                        : 'Un Administrador o Gestor debe crearlo.'}
                                </p>
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
                                    />
                                )}

                                {sinConfigurar ? (
                                    <main className="exv-tablero">
                                        <div className="exv-confirmar">
                                            {editable ? (
                                                <>
                                                    <h3>Configura el expediente para empezar a chequear</h3>
                                                    <p>
                                                        Se hace una sola vez. Revisa en el panel la fase SGR, el sector, el tipo de proyecto y
                                                        los componentes: definen qué requisitos aplican y, con eso, el porcentaje de avance.
                                                    </p>
                                                    {seleccion.sector_ids.length === 0 && (
                                                        <div className="exv-aviso">
                                                            <b>Sin sector.</b> Solo se cargarán los requisitos generales.
                                                        </div>
                                                    )}
                                                    {sinTipo.length > 0 && (
                                                        <div className="exv-aviso">
                                                            <b>Sin tipo de proyecto: {sinTipo.join(', ')}.</b> Se cargarán todos los requisitos de ese sector, incluidos los que no le apliquen al proyecto.
                                                        </div>
                                                    )}
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
                                                            disabled={recalculando || confirmando}
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
                                                    <button type="button" className="exv-btn exv-btn-peligro" onClick={eliminar}>
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
                                                        disabled={recalculando || !puedeRadicar(expediente.progreso)}
                                                    >
                                                        <FontAwesomeIcon icon={faStamp} /> Radicar
                                                    </button>
                                                </>
                                            )}
                                        </div>
                                    </div>

                                    <div className="exv-solo-impresion exv-resumen-impresion">
                                        <h2>Expediente de viabilización SGR #{expediente.id}</h2>
                                        <div>
                                            <span>Fase {faseLegible(expediente.fase_sgr)}</span>
                                            <span>{cumplidos} de {total} requisitos ({porcentaje}%)</span>
                                            <span>Catálogo v{expediente.catalogo_version.numero}</span>
                                            <span>{expediente.estado === 'radicado' ? `Radicado ${new Date(expediente.fecha_radicado).toLocaleDateString('es-CO')}` : 'Borrador'}</span>
                                        </div>
                                        <div>Sectores: {expediente.configuracion.sectores.map((s) => s.nombre).join('; ') || 'Sin sector (solo generales)'}</div>
                                        {expediente.configuracion.tipos.length > 0 && <div>Tipos de proyecto: {expediente.configuracion.tipos.map((t) => t.nombre).join('; ')}</div>}
                                        {expediente.configuracion.componentes.length > 0 && <div>Componentes: {expediente.configuracion.componentes.map((c) => c.nombre).join('; ')}</div>}
                                        {expediente.configuracion.tipologias.length > 0 && <div>Tipologías: {expediente.configuracion.tipologias.map((t) => t.nombre).join('; ')}</div>}
                                        <div className="exv-resumen-clave">A12 = Acuerdo 12/2024 · OT = Orientaciones Transitorias</div>
                                    </div>

                                    {expediente.estado === 'radicado' && (
                                        <div className="exv-nota exv-no-imprimir">
                                            <b>Expediente radicado{expediente.fecha_radicado && ` el ${new Date(expediente.fecha_radicado).toLocaleDateString('es-CO')}`}{expediente.usuario_radico && ` por ${expediente.usuario_radico}`}.</b>{' '}
                                            Su checklist está congelado. Para corregirlo, crea un expediente nuevo.
                                        </div>
                                    )}
                                    {!configAbierta && (
                                        <div className="exv-config-resumen exv-no-imprimir">
                                            <span><b>Fase</b> {faseLegible(expediente.fase_sgr)}</span>
                                            <span><b>Sectores</b> {expediente.configuracion.sectores.map((s) => s.nombre).join('; ') || 'Solo generales'}</span>
                                            {expediente.configuracion.tipos.length > 0 && <span><b>Tipos</b> {expediente.configuracion.tipos.map((t) => t.nombre).join('; ')}</span>}
                                            {expediente.configuracion.componentes.length > 0 && <span><b>Componentes</b> {expediente.configuracion.componentes.map((c) => c.nombre).join('; ')}</span>}
                                        </div>
                                    )}
                                    {configAbierta && expediente.configuracion.sectores.length > 0 && (
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
                                        onMarcar={marcar}
                                        onObservacion={guardarObservacion}
                                        onSubirSoporte={subirSoporte}
                                        onDescargarSoporte={descargarSoporte}
                                        onEliminarSoporte={eliminarSoporte}
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
