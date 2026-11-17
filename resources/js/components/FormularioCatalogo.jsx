import React, { useId, useRef, useState } from 'react';
import { formularioCatalogo, validarCatalogo } from '../utils/contratacion';

/**
 * Formulario de un elemento de catálogo (modalidad o etapa): nombre y, si `conDescripcion`,
 * descripción. Valida antes de enviar con las reglas del servidor; `onGuardar` devuelve los
 * errores por campo del servidor, o null. Escape cancela.
 */
const FormularioCatalogo = ({ titulo, item, items, reglas, conDescripcion = false, guardando, onGuardar, onCancelar }) => {
    const [f, setF] = useState(() => formularioCatalogo(item));
    const [errores, setErrores] = useState({});
    const formulario = useRef(null);
    const id = useId();

    const enfocarPrimerError = (lista) => {
        const campo = ['nombre', 'descripcion'].find((c) => lista[c]);
        if (campo) formulario.current?.querySelector(`[name="${campo}"]`)?.focus();
    };

    const enviar = async (e) => {
        e.preventDefault();
        const locales = validarCatalogo(f, items, item?.id, reglas);
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
        if (e.key === 'Escape' && !guardando) {
            e.stopPropagation();
            onCancelar();
        }
    };

    const ayuda = (campo) => (errores[campo] ? `${id}-${campo}` : undefined);

    return (
        <form ref={formulario} className="gp-panel pl-formulario" onSubmit={enviar} onKeyDown={alPulsar} noValidate aria-labelledby={`${id}-titulo`}>
            <h2 id={`${id}-titulo`} className="ct-formulario-titulo">{titulo}</h2>
            <label className="pl-campo pl-campo-ancho">
                <span>Nombre</span>
                <input
                    name="nombre"
                    autoFocus
                    value={f.nombre}
                    maxLength={reglas.maximoNombre}
                    aria-invalid={Boolean(errores.nombre)}
                    aria-describedby={ayuda('nombre')}
                    onChange={(e) => cambiar('nombre', e.target.value)}
                />
                {errores.nombre && <small id={ayuda('nombre')} className="ct-error-campo">{errores.nombre}</small>}
            </label>
            {conDescripcion && (
                <label className="pl-campo pl-campo-ancho">
                    <span>Descripción (opcional)</span>
                    <textarea
                        name="descripcion"
                        rows={3}
                        value={f.descripcion}
                        maxLength={reglas.maximoDescripcion}
                        aria-invalid={Boolean(errores.descripcion)}
                        aria-describedby={ayuda('descripcion')}
                        onChange={(e) => cambiar('descripcion', e.target.value)}
                    />
                    {errores.descripcion && <small id={ayuda('descripcion')} className="ct-error-campo">{errores.descripcion}</small>}
                </label>
            )}
            <div className="pl-formulario-acciones">
                <button type="button" className="gp-boton" onClick={onCancelar} disabled={guardando}>Cancelar</button>
                <button type="submit" className="gp-boton gp-boton-primario" disabled={guardando}>Guardar</button>
            </div>
        </form>
    );
};

export default FormularioCatalogo;
