import React, { useEffect, useState } from 'react';
import { faDownload } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { autorHistorial, describirEvento, fechaHoraHistorial, mensajeError } from '../utils/expedienteChecklist';

/** Historial de un requisito. Se vuelve a pedir cada vez que la fila cambia. */
const ExpedienteHistorialRequisito = ({ fila, onCargar, onDescargar }) => {
    const [entradas, setEntradas] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        let vigente = true;
        setError(null);
        onCargar(fila)
            .then((datos) => { if (vigente) setEntradas(datos); })
            .catch((e) => { if (vigente) setError(mensajeError(e)); });
        return () => { vigente = false; };
    }, [fila, onCargar]);

    if (error) return <p className="exv-historial-vacio">{error}</p>;
    if (entradas === null) return <p className="exv-historial-vacio">Cargando historial…</p>;
    if (entradas.length === 0) return <p className="exv-historial-vacio">Sin cambios registrados todavía.</p>;

    return (
        <ol className="exv-historial">
            {entradas.map((entrada) => (
                <li key={entrada.id}>
                    <span className="exv-historial-fecha">{fechaHoraHistorial(entrada.fecha)}</span>
                    <span><b>{autorHistorial(entrada)}</b> · {describirEvento(entrada)}</span>
                    {entrada.soporte && (
                        <button type="button" className="exv-mini" onClick={() => onDescargar(entrada)}>
                            <FontAwesomeIcon icon={faDownload} /> {entrada.soporte.nombre}
                        </button>
                    )}
                </li>
            ))}
        </ol>
    );
};

export default ExpedienteHistorialRequisito;
