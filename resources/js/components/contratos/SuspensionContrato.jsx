import React, { useEffect, useRef, useState } from 'react';
import axios from '../../axios';
import { MAXIMO_OBSERVACION, esEdicionDesactualizada, textoFecha } from '../../utils/contratacion';
import {
    CAMPOS_REINICIAR, CAMPOS_SUSPENDER, chipAvisoSuspension, datosReiniciar, datosSuspender, erroresCierre, erroresReiniciar, erroresSuspender,
    formularioReiniciar, formularioSuspender, suspensionAbierta, textoAvisoSuspension, textoReinicioPrevisto, textoVistaPreviaReinicio,
    vistaPreviaReinicio,
} from '../../utils/contratos';
import { fechaHoyBogota } from '../../utils/expedienteContratacion';
import { mensajeApi } from '../../utils/plantillasChecklist';
import { DialogoSecop } from './SecopContrato';
import '../../../css/Plantillas.css';
import '../../../css/Contratacion.css';

// Lo que cambia entre los dos diálogos: la ruta, los campos, sus reglas y sus textos.
const TIPOS = {
    suspender: {
        ruta: 'suspender',
        campos: CAMPOS_SUSPENDER,
        formulario: formularioSuspender,
        datos: datosSuspender,
        errores: erroresSuspender,
        titulo: 'Suspender el contrato',
        boton: 'Suspender',
        explicacion: 'El contrato pasa a «Suspendido» desde la fecha de suspensión. Mientras esté suspendido, la fecha fin no cambia: al reiniciarlo se le suman los días suspendidos. El valor y los avances no cambian. El cambio queda en el historial del contrato y en la pestaña Modificaciones.',
    },
    reiniciar: {
        ruta: 'reiniciar',
        campos: CAMPOS_REINICIAR,
        formulario: formularioReiniciar,
        datos: datosReiniciar,
        errores: erroresReiniciar,
        titulo: 'Reiniciar el contrato',
        boton: 'Reiniciar',
        explicacion: 'El contrato vuelve a «En ejecución» y a su fecha fin se le suman los días calendario que estuvo suspendido. El valor y los avances no cambian. El cambio queda en el historial del contrato.',
    },
};

/**
 * El diálogo de «Suspender» o «Reiniciar» (`tipo`) de un contrato guardado. Suspender: la fecha de
 * suspensión, la prevista de reinicio, el número del acta y el motivo (opcionales). Reiniciar: la
 * fecha de reinicio (la de suspensión solo si el contrato no tiene una abierta), una observación
 * opcional y la vista previa de los días que se suman a la fecha fin. Valida como el servidor (con
 * hoy en Bogotá) y muestra junto a cada campo los errores de un 422.
 *
 * - `onGuardado(data, enviado)`: la respuesta ({ contrato, avisos, resumen? }) y lo que se envió (al
 *   reiniciar, su `fecha_reinicio`); quien lo pinta cierra el diálogo.
 * - `onDesactualizado(error)`: un 409 (otra persona cambió el contrato); quien lo pinta avisa.
 * - `onCerrar()`: Cancelar, la "×", Escape o un clic en el fondo (no mientras se guarda).
 */
export const DialogoSuspension = ({ tipo, contrato, onGuardado, onDesactualizado, onCerrar }) => {
    const config = TIPOS[tipo];
    const hoy = fechaHoyBogota();
    const [formulario, setFormulario] = useState(() => config.formulario(contrato, hoy));
    const [errores, setErrores] = useState({});
    const [general, setGeneral] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [foco, setFoco] = useState(0);
    const cuerpo = useRef(null);
    const abierta = suspensionAbierta(contrato);

    // Tras un error, el foco va al primer campo con error.
    useEffect(() => {
        if (!foco) return;
        const campo = config.campos.find((c) => errores[c]);
        if (campo) cuerpo.current?.querySelector(`[name="${campo}"]`)?.focus();
    }, [foco]);

    const cambiar = (e) => {
        const { name, value } = e.target;
        setErrores((prev) => (prev[name] ? { ...prev, [name]: undefined } : prev));
        setFormulario((prev) => ({ ...prev, [name]: value }));
    };

    const enviar = async (e) => {
        e.preventDefault();
        if (enviando) return;
        const locales = config.errores(formulario, contrato, hoy);
        setErrores(locales);
        setGeneral(null);
        if (Object.keys(locales).length) {
            setFoco((n) => n + 1);
            return;
        }
        setEnviando(true);
        try {
            const enviado = config.datos(formulario, contrato);
            const { data } = await axios.post(`/contratos/${contrato.id}/${config.ruta}`, enviado);
            onGuardado(data, enviado);
        } catch (error) {
            setEnviando(false);
            if (esEdicionDesactualizada(error)) {
                onDesactualizado(error);
                return;
            }
            // Los 422 tienen la misma forma que los de terminar y liquidar: por campo o `mensaje`.
            const delServidor = erroresCierre(error, config.campos);
            if (delServidor) {
                setErrores(delServidor.campos);
                setGeneral(delServidor.general);
                if (Object.keys(delServidor.campos).length) setFoco((n) => n + 1);
                return;
            }
            setGeneral(mensajeApi(error));
        }
    };

    const cerrar = () => { if (!enviando) onCerrar(); };

    // Un campo con su etiqueta, su nota y su error.
    const campo = (nombre, etiqueta, control, { nota = null, ancho = false } = {}) => (
        <div className={`pl-campo${ancho ? ' ct-cierre-ancho' : ''}`}>
            <label htmlFor={`suspension-${nombre}`}>{etiqueta}</label>
            {React.cloneElement(control, {
                id: `suspension-${nombre}`,
                name: nombre,
                value: formulario[nombre] ?? '',
                onChange: cambiar,
                disabled: enviando,
                'aria-invalid': Boolean(errores[nombre]),
                'aria-describedby': [errores[nombre] ? `suspension-${nombre}-error` : null, nota ? `suspension-${nombre}-nota` : null].filter(Boolean).join(' ') || undefined,
            })}
            {nota && <small id={`suspension-${nombre}-nota`} className="ct-secop-nota">{nota}</small>}
            {errores[nombre] && <small id={`suspension-${nombre}-error`} className="ct-error-campo" role="alert">{errores[nombre]}</small>}
        </div>
    );

    const inicio = String(contrato?.fecha_inicio ?? '').slice(0, 10) || undefined;
    const vista = tipo === 'reiniciar' ? textoVistaPreviaReinicio(vistaPreviaReinicio(formulario, contrato)) : null;
    // Mínimo del reinicio: la suspensión abierta o la escrita.
    const minimoReinicio = abierta?.desde || formulario.fecha_suspension || undefined;

    return (
        <DialogoSecop titulo={config.titulo} etiqueta={`Contrato ${contrato?.n_contrato || 'sin número'}`} estrecho onCerrar={cerrar}>
            <form ref={cuerpo} className="ct-cierre-formulario" onSubmit={enviar} noValidate>
                <p className="contrato-nota ct-cierre-explicacion">{config.explicacion}</p>
                {general && <p className="pl-error ct-cierre-explicacion" role="alert">{general}</p>}
                {tipo === 'suspender' && (
                    <>
                        {campo('fecha_suspension', 'Fecha de suspensión *', <input type="date" min={inicio} max={hoy} />)}
                        {campo('fecha_reinicio_prevista', 'Fecha prevista de reinicio (opcional)', (
                            <input type="date" min={formulario.fecha_suspension || undefined} />
                        ), { nota: 'Si pasa y el contrato sigue suspendido, se avisa «Reinicio vencido».' })}
                        {campo('numero_acta', 'Número del acta (opcional)', <input type="text" maxLength={50} placeholder="Por ejemplo, Acta de suspensión No. 1" />, { ancho: true })}
                        {campo('motivo', 'Motivo (opcional)', <textarea rows="3" maxLength={2000} placeholder="Por qué se suspende el contrato" />, { ancho: true })}
                    </>
                )}
                {tipo === 'reiniciar' && (
                    <>
                        {abierta ? (
                            <p className="ct-cierre-saldo">Suspendido desde el {textoFecha(abierta.desde)}.</p>
                        ) : campo('fecha_suspension', 'Fecha de suspensión *', <input type="date" min={inicio} max={hoy} />, {
                            nota: 'El contrato no tiene una suspensión registrada: escribe desde cuándo está suspendido.',
                        })}
                        {campo('fecha_reinicio', 'Fecha de reinicio *', <input type="date" min={minimoReinicio} max={hoy} />)}
                        {vista && <p className="ct-cierre-saldo ct-suspension-vista" aria-live="polite">{vista}</p>}
                        {campo('observacion', 'Observación (opcional)', (
                            <textarea rows="3" maxLength={MAXIMO_OBSERVACION} placeholder="Por ejemplo, el acta de reinicio" />
                        ), { ancho: true })}
                    </>
                )}
                <div className="pl-formulario-acciones ct-cierre-ancho">
                    <button type="button" className="gp-boton" onClick={cerrar} disabled={enviando}>Cancelar</button>
                    <button type="submit" className="gp-boton gp-boton-primario" disabled={enviando}>{enviando ? 'Guardando…' : config.boton}</button>
                </div>
            </form>
        </DialogoSecop>
    );
};

/**
 * La franja de la pestaña de información de un contrato suspendido con su suspensión abierta
 * (`aviso_suspension`): "Suspendido desde X (N días)" y, si la hay, la fecha prevista de reinicio,
 * con el chip "Reinicio vencido" si ya pasó. Nada si no hay aviso.
 */
export const FranjaSuspension = ({ aviso }) => {
    const texto = textoAvisoSuspension(aviso);
    if (!texto) return null;
    const previsto = textoReinicioPrevisto(aviso);
    const chip = chipAvisoSuspension(aviso);
    return (
        <div className={`ct-suspension${chip ? ' ct-suspension-vencida' : ''}`} role="status">
            <strong>{texto}</strong>
            {previsto && <span>{previsto}</span>}
            {chip && <span className="ct-estado ct-estado-coral">{chip}</span>}
            <small>La fecha fin no cambia mientras esté suspendido: al reiniciarlo se le suman los días suspendidos.</small>
        </div>
    );
};
