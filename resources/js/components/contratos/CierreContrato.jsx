import React, { useEffect, useRef, useState } from 'react';
import axios from '../../axios';
import { MAXIMO_OBSERVACION, esEdicionDesactualizada, textoFecha, textoValor, valorEnEdicion, valorParaApi } from '../../utils/contratacion';
import {
    CAMPOS_LIQUIDAR, CAMPOS_TERMINAR, datosLiquidar, datosTerminar, erroresCierre, erroresLiquidar, erroresTerminar, formularioLiquidar,
    formularioTerminar, saldoLiquidacion, textoAvisoCierre,
} from '../../utils/contratos';
import { fechaHoyBogota } from '../../utils/expedienteContratacion';
import { mensajeApi } from '../../utils/plantillasChecklist';
import { DialogoSecop } from './SecopContrato';
import '../../../css/Plantillas.css';
import '../../../css/Contratacion.css';

// Lo que cambia entre los dos diálogos: la ruta, los campos y sus textos.
const TIPOS = {
    terminar: {
        ruta: 'terminar',
        campos: CAMPOS_TERMINAR,
        formulario: formularioTerminar,
        datos: datosTerminar,
        errores: erroresTerminar,
    },
    liquidar: {
        ruta: 'liquidar',
        campos: CAMPOS_LIQUIDAR,
        formulario: formularioLiquidar,
        datos: datosLiquidar,
        errores: erroresLiquidar,
    },
};

const textos = (tipo, contrato) => {
    if (tipo === 'terminar') {
        return contrato?.estado === 'terminado'
            ? { titulo: 'Corregir la terminación', boton: 'Guardar corrección', explicacion: 'Corrige la fecha en que el contrato terminó. El cambio queda en el historial del contrato.' }
            : {
                titulo: 'Terminar el contrato',
                boton: 'Terminar',
                explicacion: 'El contrato pasa a «Terminado» con la fecha en que terminó. El valor, la fecha fin y los avances no cambian. El cambio queda en el historial del contrato.',
            };
    }
    return contrato?.estado === 'liquidado'
        ? { titulo: 'Corregir la liquidación', boton: 'Guardar liquidación', explicacion: 'Completa o corrige los datos de la liquidación. El cambio queda en el historial del contrato.' }
        : {
            titulo: 'Liquidar el contrato',
            boton: 'Liquidar',
            explicacion: 'El contrato pasa a «Liquidado» con su fecha de terminación, la de liquidación y el valor liquidado. El valor vigente, la fecha fin y los avances no cambian. El cambio queda en el historial del contrato.',
        };
};

/**
 * El diálogo de «Terminar» o «Liquidar» (`tipo`) de un contrato guardado: sus fechas, el valor
 * liquidado y una observación opcional. Valida como el servidor (con hoy en Bogotá) y muestra
 * junto a cada campo los errores de un 422.
 *
 * - `onGuardado(data)`: la respuesta ({ contrato, avisos }); quien lo pinta cierra el diálogo.
 * - `onDesactualizado(error)`: un 409 (otra persona cambió el contrato); quien lo pinta avisa.
 * - `onCerrar()`: Cancelar, la "×", Escape o un clic en el fondo (no mientras se guarda).
 */
export const DialogoCierre = ({ tipo, contrato, onGuardado, onDesactualizado, onCerrar }) => {
    const config = TIPOS[tipo];
    const { titulo, boton, explicacion } = textos(tipo, contrato);
    const [formulario, setFormulario] = useState(() => config.formulario(contrato));
    const [errores, setErrores] = useState({});
    const [general, setGeneral] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [foco, setFoco] = useState(0);
    const cuerpo = useRef(null);
    const hoy = fechaHoyBogota();

    // Tras un error, el foco va al primer campo con error.
    useEffect(() => {
        if (!foco) return;
        const campo = config.campos.find((c) => errores[c]);
        if (campo) cuerpo.current?.querySelector(`[name="${campo}"]`)?.focus();
    }, [foco]);

    const cambiar = (e) => {
        const { name, value } = e.target;
        setErrores((prev) => (prev[name] ? { ...prev, [name]: undefined } : prev));
        setFormulario((prev) => ({ ...prev, [name]: name === 'valor_liquidado' ? valorEnEdicion(value, prev.valor_liquidado) : value }));
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
            const { data } = await axios.post(`/contratos/${contrato.id}/${config.ruta}`, config.datos(formulario, contrato));
            onGuardado(data);
        } catch (error) {
            setEnviando(false);
            if (esEdicionDesactualizada(error)) {
                onDesactualizado(error);
                return;
            }
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

    // Un campo con su etiqueta y su error.
    const campo = (nombre, etiqueta, control, nota = null) => (
        <div className="pl-campo">
            <label htmlFor={`cierre-${nombre}`}>{etiqueta}</label>
            {React.cloneElement(control, {
                id: `cierre-${nombre}`,
                name: nombre,
                value: formulario[nombre] ?? '',
                onChange: cambiar,
                disabled: enviando,
                'aria-invalid': Boolean(errores[nombre]),
                'aria-describedby': [errores[nombre] ? `cierre-${nombre}-error` : null, nota ? `cierre-${nombre}-nota` : null].filter(Boolean).join(' ') || undefined,
            })}
            {nota && <small id={`cierre-${nombre}-nota`} className="ct-secop-nota">{nota}</small>}
            {errores[nombre] && <small id={`cierre-${nombre}-error`} className="ct-error-campo" role="alert">{errores[nombre]}</small>}
        </div>
    );

    const inicio = String(contrato?.fecha_inicio ?? '').slice(0, 10) || undefined;
    // El saldo con lo que se está escribiendo: avisa (sin impedir) si lo liquidado supera lo vigente.
    const saldo = tipo === 'liquidar' ? saldoLiquidacion({ valor: contrato?.valor, valor_liquidado: valorParaApi(formulario.valor_liquidado) }) : null;

    return (
        <DialogoSecop titulo={titulo} etiqueta={`Contrato ${contrato?.n_contrato || 'sin número'}`} estrecho onCerrar={cerrar}>
            <form ref={cuerpo} className="ct-cierre-formulario" onSubmit={enviar} noValidate>
                <p className="contrato-nota ct-cierre-explicacion">{explicacion}</p>
                {general && <p className="pl-error ct-cierre-explicacion" role="alert">{general}</p>}
                {campo('fecha_terminacion_real', 'Fecha de terminación real *', <input type="date" min={inicio} max={hoy} />)}
                {tipo === 'liquidar' && campo('fecha_liquidacion', 'Fecha de liquidación *', (
                    <input type="date" min={formulario.fecha_terminacion_real || undefined} max={hoy} />
                ))}
                {tipo === 'liquidar' && campo('valor_liquidado', 'Valor liquidado *', <input type="text" inputMode="decimal" placeholder="0" />,
                    `Valor vigente: ${textoValor(contrato?.valor)}`)}
                {saldo !== null && (
                    <p className={`ct-cierre-saldo${saldo.startsWith('-') ? ' ct-cierre-supera' : ''}`} aria-live="polite">
                        Saldo (vigente − liquidado): <span className="ct-valor">{textoValor(saldo)}</span>
                        {saldo.startsWith('-') && <> · El valor liquidado supera el valor vigente del contrato: puedes guardarlo igual.</>}
                    </p>
                )}
                <div className="pl-campo ct-cierre-ancho">
                    <label htmlFor="cierre-observacion">Observación (opcional)</label>
                    <textarea
                        id="cierre-observacion"
                        name="observacion"
                        rows="3"
                        maxLength={MAXIMO_OBSERVACION}
                        placeholder="Por ejemplo, el acta de terminación o de liquidación"
                        value={formulario.observacion}
                        onChange={cambiar}
                        disabled={enviando}
                        aria-invalid={Boolean(errores.observacion)}
                        aria-describedby={errores.observacion ? 'cierre-observacion-error' : undefined}
                    />
                    {errores.observacion && <small id="cierre-observacion-error" className="ct-error-campo" role="alert">{errores.observacion}</small>}
                </div>
                <div className="pl-formulario-acciones ct-cierre-ancho">
                    <button type="button" className="gp-boton" onClick={cerrar} disabled={enviando}>Cancelar</button>
                    <button type="submit" className="gp-boton gp-boton-primario" disabled={enviando}>{enviando ? 'Guardando…' : boton}</button>
                </div>
            </form>
        </DialogoSecop>
    );
};

/**
 * El bloque "Cierre" de la pestaña de información (si muestraBloqueCierre), de solo lectura: las fechas de terminación y
 * de liquidación, el valor liquidado, el saldo (vigente − liquidado) y el aviso de datos que faltan.
 */
export const BloqueCierre = ({ contrato }) => {
    const aviso = textoAvisoCierre(contrato?.aviso_cierre);
    const saldo = saldoLiquidacion(contrato);
    return (
        <section className="ct-cierre" aria-labelledby="cierre-titulo">
            <div className="ct-cierre-cabecera">
                <h4 id="cierre-titulo">Cierre</h4>
                {aviso && <span className="ct-estado ct-estado-ambar">{aviso}</span>}
            </div>
            <dl className="contrato-proceso-datos ct-cierre-datos">
                <div><dt>Fecha de terminación real</dt><dd>{textoFecha(contrato?.fecha_terminacion_real)}</dd></div>
                <div><dt>Fecha de liquidación</dt><dd>{textoFecha(contrato?.fecha_liquidacion)}</dd></div>
                <div><dt>Valor liquidado</dt><dd className="ct-valor">{textoValor(contrato?.valor_liquidado)}</dd></div>
                <div><dt>Saldo (vigente − liquidado)</dt><dd className="ct-valor">{saldo === null ? '—' : textoValor(saldo)}</dd></div>
            </dl>
        </section>
    );
};
