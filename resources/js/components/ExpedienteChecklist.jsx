import React, { useMemo, useState } from 'react';
import { faPaperclip, faDownload, faTrash, faCommentDots } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { ETIQUETA_FUENTE, agruparRequisitos } from '../utils/expedienteChecklist';

const TIPOS_SOPORTE = '.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx';

const fechaCorta = (iso) => (iso ? new Date(iso).toLocaleDateString('es-CO') : '');

const ItemRequisito = ({ fila, editable, onMarcar, onObservacion, onSubirSoporte, onDescargarSoporte, onEliminarSoporte }) => {
    const { requisito: r } = fila;
    const [viendoObservacion, setViendoObservacion] = useState(Boolean(fila.observacion));

    return (
        <article className={`exv-item${fila.cumplido ? ' cumplido' : ''}`}>
            <label className="exv-item-principal">
                <input
                    type="checkbox"
                    checked={fila.cumplido}
                    disabled={!editable}
                    onChange={(e) => onMarcar(fila, e.target.checked)}
                />
                <div className="exv-item-cuerpo">
                    <div className="exv-item-tags">
                        <span className="exv-codigo">{r.codigo}</span>
                        <span className={`exv-fuente exv-fuente-${r.fuente}`}>{ETIQUETA_FUENTE[r.fuente]}</span>
                    </div>
                    <div className="exv-item-texto">
                        {r.texto}
                        {r.nota && <span className="exv-item-nota">↳ {r.nota}</span>}
                    </div>
                </div>
            </label>

            {fila.cumplido && fila.fecha_cumplido && (
                <div className="exv-item-meta">
                    Cumplido el {fechaCorta(fila.fecha_cumplido)}
                    {fila.usuario_cumplido && ` por ${fila.usuario_cumplido}`}
                </div>
            )}

            <div className="exv-item-acciones">
                {(editable || fila.observacion) && (
                    <button type="button" className="exv-mini" onClick={() => setViendoObservacion((v) => !v)}>
                        <FontAwesomeIcon icon={faCommentDots} /> Observación
                    </button>
                )}
                {editable && (
                    <label className="exv-mini">
                        <FontAwesomeIcon icon={faPaperclip} /> {fila.soporte ? 'Reemplazar soporte' : 'Adjuntar soporte'}
                        <input
                            type="file"
                            hidden
                            accept={TIPOS_SOPORTE}
                            onChange={(e) => {
                                const archivo = e.target.files[0];
                                e.target.value = ''; // permite volver a elegir el mismo archivo
                                if (archivo) onSubirSoporte(fila, archivo);
                            }}
                        />
                    </label>
                )}
                {fila.soporte && (
                    <>
                        <button type="button" className="exv-mini" onClick={() => onDescargarSoporte(fila)}>
                            <FontAwesomeIcon icon={faDownload} /> {fila.soporte.nombre}
                        </button>
                        {editable && (
                            <button
                                type="button"
                                className="exv-mini exv-mini-peligro"
                                aria-label={`Eliminar soporte ${fila.soporte.nombre}`}
                                onClick={() => onEliminarSoporte(fila)}
                            >
                                <FontAwesomeIcon icon={faTrash} />
                            </button>
                        )}
                    </>
                )}
            </div>

            {viendoObservacion && (
                editable ? (
                    <textarea
                        className="exv-observacion"
                        rows={2}
                        maxLength={2000}
                        placeholder="Observación sobre este requisito"
                        defaultValue={fila.observacion ?? ''}
                        onBlur={(e) => {
                            if (e.target.value.trim() !== (fila.observacion ?? '')) {
                                onObservacion(fila, e.target.value);
                            }
                        }}
                    />
                ) : (
                    <p className="exv-observacion-lectura">{fila.observacion}</p>
                )
            )}

            {/* Solo impresión: los controles anteriores no salen en el PDF. */}
            {(fila.observacion || fila.soporte) && (
                <div className="exv-solo-impresion exv-item-impresion">
                    {fila.observacion && <div><b>Observación:</b> {fila.observacion}</div>}
                    {fila.soporte && <div><b>Soporte:</b> {fila.soporte.nombre}</div>}
                </div>
            )}
        </article>
    );
};

const ExpedienteChecklist = ({ expediente, editable, ...acciones }) => {
    const secciones = useMemo(() => agruparRequisitos(expediente.requisitos), [expediente.requisitos]);
    const sectores = expediente.configuracion.sectores;

    if (secciones.length === 0) {
        return (
            <div className="exv-vacio">
                <b>Sin requisitos que mostrar</b>
                <p>Ajusta la configuración del proyecto para generar el checklist.</p>
            </div>
        );
    }

    return secciones.map((seccion) => {
        const cita = seccion.clave === 'C'
            ? (sectores.length === 1 ? sectores[0].nombre : `${sectores.length} sectores`)
            : seccion.cita;

        return (
            <section key={seccion.clave} className="exv-seccion">
                <div className="exv-seccion-head">
                    <span className="exv-num">{seccion.num}</span>
                    <h3>{seccion.titulo}</h3>
                    <span className="exv-cita">{cita} · {seccion.cumplidos}/{seccion.total}</span>
                </div>
                {seccion.grupos.map((grupo) => (
                    <div key={grupo.sector?.id ?? 'general'}>
                        {seccion.clave === 'C' && sectores.length > 1 && (
                            <div className="exv-subhead exv-subhead-sector">{grupo.sector.nombre}</div>
                        )}
                        {grupo.subgrupos.map((sub, i) => (
                            <div key={`${sub.subtitulo ?? 'sin'}-${i}`}>
                                {sub.subtitulo && <div className="exv-subhead">{sub.subtitulo}</div>}
                                {sub.filas.map((fila) => (
                                    <ItemRequisito key={fila.id} fila={fila} editable={editable} {...acciones} />
                                ))}
                            </div>
                        ))}
                    </div>
                ))}
            </section>
        );
    });
};

export default ExpedienteChecklist;
