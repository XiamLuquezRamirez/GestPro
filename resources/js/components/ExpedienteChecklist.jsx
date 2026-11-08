import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
    faPaperclip, faDownload, faTrash, faCommentDots, faBan, faRotateLeft, faCheck, faXmark, faClockRotateLeft, faCircleCheck,
    faPen, faPlus,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    CARACTERES_PROPIO,
    CATEGORIAS,
    ETIQUETA_ESTADO_REQUISITO,
    TITULO_PROPIOS,
    accionesAplicabilidad,
    agruparChecklist,
    contenidoFila,
    formularioPropio,
    puedeMarcarRequisito,
    responsablesDisponibles,
    textoOrigenes,
    textoPorDefinir,
} from '../utils/expedienteChecklist';
import ExpedienteHistorialRequisito from './ExpedienteHistorialRequisito';

const TIPOS_SOPORTE = '.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx';

const fechaCorta = (iso) => (iso ? new Date(iso).toLocaleDateString('es-CO') : '');

/** Nombre corto de una fila para las etiquetas accesibles: su código o el comienzo de su texto. */
const nombreFila = (contenido) => contenido.codigo ?? (contenido.texto.length > 60 ? `${contenido.texto.slice(0, 60)}…` : contenido.texto);

/** Textos parecidos entre plantillas distintas. Solo avisan: el sistema nunca los fusiona. */
export const AvisosDuplicados = ({ avisos }) => {
    if (!avisos?.length) return null;
    return (
        <div className="exv-aviso exv-duplicados exv-no-imprimir" role="status">
            <b>{avisos.length === 1 ? 'Un requisito podría estar repetido' : `${avisos.length} requisitos podrían estar repetidos`}</b>{' '}
            entre plantillas: sus textos se parecen, pero no comparten código común. Revísalos; el sistema no los une.
            <ul>
                {avisos.map((aviso) => (
                    <li key={aviso.filas.join('-')}>
                        «{aviso.textos[0]}» ({aviso.plantillas[0]}) y «{aviso.textos[1]}» ({aviso.plantillas[1]})
                        <span className="exv-duplicados-similitud"> · {Math.round(aviso.similitud * 100)} % de palabras en común</span>
                    </li>
                ))}
            </ul>
        </div>
    );
};

/** Formulario de un requisito propio, para agregarlo o editarlo. */
const FormularioPropio = ({ fila, ocupado, onGuardar, onCancelar }) => {
    const [formulario, setFormulario] = useState(() => formularioPropio(fila));
    const titulo = useRef(null);
    const prefijo = `exv-propio-${fila?.id ?? 'nuevo'}`;
    const cambiar = (campo, valor) => setFormulario((f) => ({ ...f, [campo]: valor }));

    useEffect(() => { titulo.current?.focus(); }, []);

    return (
        <form
            className="exv-propio-form"
            aria-label={fila ? 'Editar requisito propio' : 'Agregar requisito propio'}
            onSubmit={(e) => { e.preventDefault(); onGuardar(formulario); }}
        >
            <label className="exv-campo" htmlFor={`${prefijo}-titulo`}>
                <span>Título</span>
                <textarea
                    id={`${prefijo}-titulo`}
                    ref={titulo}
                    rows={2}
                    maxLength={500}
                    required
                    value={formulario.titulo}
                    onChange={(e) => cambiar('titulo', e.target.value)}
                />
            </label>
            <label className="exv-campo" htmlFor={`${prefijo}-descripcion`}>
                <span>Descripción <small>(opcional)</small></span>
                <textarea
                    id={`${prefijo}-descripcion`}
                    rows={3}
                    maxLength={2000}
                    value={formulario.descripcion}
                    onChange={(e) => cambiar('descripcion', e.target.value)}
                />
            </label>
            <label className="exv-campo" htmlFor={`${prefijo}-categoria`}>
                <span>Categoría</span>
                <select id={`${prefijo}-categoria`} value={formulario.categoria} onChange={(e) => cambiar('categoria', e.target.value)}>
                    <option value="">Sin categoría</option>
                    {CATEGORIAS.map(([valor, texto]) => <option key={valor} value={valor}>{texto}</option>)}
                </select>
            </label>
            <fieldset className="exv-campo-grupo" aria-describedby={`${prefijo}-ayuda`}>
                <legend>Carácter</legend>
                {CARACTERES_PROPIO.map(([valor, texto]) => (
                    <label key={valor}>
                        <input
                            type="radio"
                            name={`${prefijo}-caracter`}
                            value={valor}
                            checked={formulario.caracter === valor}
                            onChange={() => cambiar('caracter', valor)}
                        />
                        {texto}
                    </label>
                ))}
                <p id={`${prefijo}-ayuda`} className="exv-campo-ayuda">
                    Un requisito opcional se puede marcar, pero no cuenta en el avance ni bloquea la radicación.
                </p>
            </fieldset>
            <div className="exv-propio-acciones">
                <button type="button" className="exv-btn" onClick={onCancelar} disabled={ocupado}>Cancelar</button>
                <button type="submit" className="exv-btn exv-btn-primario" disabled={ocupado || !formulario.titulo.trim()}>
                    {fila ? 'Guardar cambios' : 'Agregar requisito'}
                </button>
            </div>
        </form>
    );
};

const ItemRequisito = ({
    fila, editable, usuario, responsables, ocupado, onMarcar, onObservacion, onSubirSoporte, onDescargarSoporte, onEliminarSoporte,
    onAplica, onNoAplica, onRetirarNoAplica, onAprobarNoAplica, onRechazarNoAplica, onResponsable, onCargarHistorial, onDescargarHistorial,
    onEditarPropio, onEliminarPropio,
}) => {
    const contenido = contenidoFila(fila);
    const [viendoObservacion, setViendoObservacion] = useState(Boolean(fila.observacion));
    const [viendoHistorial, setViendoHistorial] = useState(false);
    const acciones = editable ? accionesAplicabilidad(fila, usuario) : [];
    const origenes = textoOrigenes(fila);
    const disponibles = responsablesDisponibles(responsables, fila.responsable?.id ?? null);
    // Si la lista falla o aún no llega, el asignado se muestra igual.
    const opciones = fila.responsable && !disponibles.some((p) => p.id === fila.responsable.id)
        ? [fila.responsable, ...disponibles]
        : disponibles;

    return (
        <article className={`exv-item${fila.estado === 'cumplido' ? ' cumplido' : ''}${fila.cuenta ? '' : ' no-cuenta'}`}>
            <label className="exv-item-principal">
                <input
                    type="checkbox"
                    checked={fila.cumplido}
                    disabled={!editable || !puedeMarcarRequisito(fila)}
                    onChange={(e) => onMarcar(fila, e.target.checked)}
                />
                <div className="exv-item-cuerpo">
                    <div className="exv-item-tags">
                        {contenido.codigo && <span className="exv-codigo">{contenido.codigo}</span>}
                        {contenido.fuente && <span className={`exv-fuente exv-fuente-${fila.requisito.fuente}`}>{contenido.fuente}</span>}
                        {contenido.propio && <span className="exv-estado exv-estado-propio">Propio</span>}
                        {contenido.propio && contenido.categoria && <span className="exv-estado">{contenido.categoria}</span>}
                        {fila.estado !== 'pendiente' && fila.estado !== 'cumplido' && (
                            <span className={`exv-estado exv-estado-${fila.estado}`}>{ETIQUETA_ESTADO_REQUISITO[fila.estado]}</span>
                        )}
                        {fila.caracter !== 'obligatorio' && <span className="exv-estado">{fila.caracter === 'opcional' ? 'Opcional' : 'Condicionado'}</span>}
                    </div>
                    <div className="exv-item-texto">
                        {contenido.texto}
                        {contenido.nota && (
                            <span className={contenido.propio ? 'exv-item-descripcion' : 'exv-item-nota'}>
                                {contenido.propio ? contenido.nota : `↳ ${contenido.nota}`}
                            </span>
                        )}
                        {contenido.descripcion && <span className="exv-item-descripcion">{contenido.descripcion}</span>}
                    </div>
                    {/* Por definir, la condición va en su recuadro; decidida, queda a la vista bajo el texto. */}
                    {contenido.aplicaSi && fila.estado !== 'por_definir' && (
                        <div className="exv-item-condicion"><b>Aplica si:</b> {contenido.aplicaSi}</div>
                    )}
                    {origenes && <div className="exv-item-origenes">{origenes}</div>}
                </div>
            </label>

            {fila.cumplido && fila.fecha_cumplido && (
                <div className="exv-item-meta">
                    Cumplido el {fechaCorta(fila.fecha_cumplido)}
                    {fila.usuario_cumplido && ` por ${fila.usuario_cumplido}`}
                </div>
            )}

            {fila.estado === 'por_definir' && (
                <div className="exv-no-aplica exv-por-definir">
                    <b>{ETIQUETA_ESTADO_REQUISITO.por_definir}:</b> {textoPorDefinir(fila)}
                </div>
            )}

            {fila.no_aplica && fila.estado !== 'por_definir' && (
                <div className="exv-no-aplica">
                    <b>{ETIQUETA_ESTADO_REQUISITO[fila.estado]}:</b> {fila.no_aplica.justificacion}
                    <span className="exv-no-aplica-quien">
                        {fila.no_aplica.usuario && ` Marcado por ${fila.no_aplica.usuario}.`}
                        {fila.no_aplica.aprobado_por && ` Aprobado por ${fila.no_aplica.aprobado_por} el ${fechaCorta(fila.no_aplica.fecha_aprobacion)}.`}
                        {fila.estado === 'no_aplica_pendiente' && contenido.cargoRevision && ` Debería revisarlo: ${contenido.cargoRevision}.`}
                    </span>
                </div>
            )}

            <div className="exv-responsable">
                <span>Responsable:</span>
                {editable ? (
                    <select
                        value={fila.responsable?.id ?? ''}
                        aria-label={`Responsable del requisito ${nombreFila(contenido)}`}
                        onChange={(e) => onResponsable(fila, e.target.value ? Number(e.target.value) : null)}
                    >
                        <option value="">Sin asignar</option>
                        {opciones.map((p) => (
                            <option key={p.id} value={p.id}>{p.nombre}{p.cargo ? ` (${p.cargo})` : ''}</option>
                        ))}
                    </select>
                ) : (
                    <b>{fila.responsable ? fila.responsable.nombre : 'Sin asignar'}</b>
                )}
                {contenido.cargoCumplimiento && <span className="exv-responsable-cargo">Cargo indicado: {contenido.cargoCumplimiento}</span>}
                {contenido.soporteRequerido && <span className="exv-responsable-cargo">Soporte requerido: {contenido.soporteRequerido}</span>}
            </div>

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
                {acciones.includes('aplica') && (
                    <button type="button" className="exv-mini exv-mini-exito" disabled={ocupado} onClick={() => onAplica(fila)}>
                        <FontAwesomeIcon icon={faCircleCheck} /> Aplica
                    </button>
                )}
                {acciones.includes('no_aplica') && (
                    <button type="button" className="exv-mini" onClick={() => onNoAplica(fila)}>
                        <FontAwesomeIcon icon={faBan} /> No aplica
                    </button>
                )}
                {acciones.includes('retirar_no_aplica') && (
                    <button type="button" className="exv-mini" onClick={() => onRetirarNoAplica(fila)}>
                        <FontAwesomeIcon icon={faRotateLeft} /> Retirar "no aplica"
                    </button>
                )}
                {acciones.includes('aprobar') && (
                    <>
                        <button type="button" className="exv-mini exv-mini-exito" onClick={() => onAprobarNoAplica(fila)}>
                            <FontAwesomeIcon icon={faCheck} /> Aprobar
                        </button>
                        <button type="button" className="exv-mini exv-mini-peligro" onClick={() => onRechazarNoAplica(fila)}>
                            <FontAwesomeIcon icon={faXmark} /> Rechazar
                        </button>
                    </>
                )}
                {editable && contenido.propio && (
                    <>
                        <button type="button" className="exv-mini" disabled={ocupado} data-foco={`editar-propio-${fila.id}`} onClick={() => onEditarPropio(fila)}>
                            <FontAwesomeIcon icon={faPen} /> Editar
                        </button>
                        <button type="button" className="exv-mini exv-mini-peligro" disabled={ocupado} onClick={() => onEliminarPropio(fila)}>
                            <FontAwesomeIcon icon={faTrash} /> Eliminar
                        </button>
                    </>
                )}
                <button type="button" className="exv-mini" aria-expanded={viendoHistorial} onClick={() => setViendoHistorial((v) => !v)}>
                    <FontAwesomeIcon icon={faClockRotateLeft} /> Historial
                </button>
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
                        aria-label={`Observación del requisito ${nombreFila(contenido)}`}
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

            {viendoHistorial && (
                <ExpedienteHistorialRequisito fila={fila} onCargar={onCargarHistorial} onDescargar={onDescargarHistorial} />
            )}

            {/* Solo impresión: los controles anteriores no salen en el PDF. */}
            {(fila.observacion || fila.soporte || fila.responsable) && (
                <div className="exv-solo-impresion exv-item-impresion">
                    {fila.responsable && <div><b>Responsable:</b> {fila.responsable.nombre}</div>}
                    {fila.observacion && <div><b>Observación:</b> {fila.observacion}</div>}
                    {fila.soporte && <div><b>Soporte:</b> {fila.soporte.nombre}</div>}
                </div>
            )}
        </article>
    );
};

/** Cabecera de una plantilla dentro del checklist. */
const CabeceraPlantilla = ({ nombre, cumplidos, total }) => (
    <div className="exv-plantilla-titulo">
        <h3>{nombre}</h3>
        <span className="exv-cita">{cumplidos}/{total}</span>
    </div>
);

/** Plantilla con motor sgr: por sección, sector y subtítulo, como siempre. */
const BloqueSgr = ({ bloque, conCabecera, sectores, renderFila }) => {
    const Titulo = conCabecera ? 'h4' : 'h3';
    return (
        <div className="exv-bloque">
            {conCabecera && <CabeceraPlantilla nombre={bloque.plantilla.nombre} cumplidos={bloque.cumplidos} total={bloque.total} />}
            {bloque.secciones.map((seccion) => {
                const cita = seccion.clave === 'C'
                    ? (sectores.length === 1 ? sectores[0].nombre : `${sectores.length} sectores`)
                    : seccion.cita;

                return (
                    <section key={seccion.clave} className="exv-seccion">
                        <div className="exv-seccion-head">
                            <span className="exv-num">{seccion.num}</span>
                            <Titulo>{seccion.titulo}</Titulo>
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
                                        {sub.filas.map(renderFila)}
                                    </div>
                                ))}
                            </div>
                        ))}
                    </section>
                );
            })}
        </div>
    );
};

/** Otra plantilla: por categoría. Si todo es "Sin categoría", no hace falta el subtítulo. */
const BloqueSimple = ({ bloque, renderFila }) => {
    const soloSinCategoria = bloque.categorias.length === 1 && bloque.categorias[0].clave === 'sin';
    return (
        <section className="exv-seccion exv-bloque">
            <CabeceraPlantilla nombre={bloque.plantilla?.nombre ?? 'Sin plantilla'} cumplidos={bloque.cumplidos} total={bloque.total} />
            {bloque.categorias.map((categoria) => (
                <div key={categoria.clave}>
                    {!soloSinCategoria && (
                        <div className="exv-subhead">{categoria.titulo} · {categoria.cumplidos}/{categoria.total}</div>
                    )}
                    {categoria.filas.map(renderFila)}
                </div>
            ))}
        </section>
    );
};

const ExpedienteChecklist = ({ expediente, editable, ocupado, onGuardarPropio, onEliminarPropio, ...acciones }) => {
    const bloques = useMemo(() => agruparChecklist(expediente.requisitos), [expediente.requisitos]);
    const [formulario, setFormulario] = useState(null); // null, 'nuevo' o el id de la fila propia que se edita
    const contenedor = useRef(null);
    const retorno = useRef(null); // data-foco del botón que abrió el formulario
    const sectores = expediente.configuracion.sectores;
    const deCatalogo = bloques.filter((b) => b.tipo !== 'propios');
    const propios = bloques.find((b) => b.tipo === 'propios');
    // Con una sola plantilla el checklist se ve como siempre; con varias, cada una lleva su nombre.
    const conCabecera = expediente.plantillas.length > 1 || deCatalogo.length > 1;

    // Al cerrar el formulario, el foco vuelve al botón que lo abrió (si la fila sigue ahí).
    useEffect(() => {
        if (formulario !== null || !retorno.current) return;
        contenedor.current?.querySelector(`[data-foco="${retorno.current}"]`)?.focus();
        retorno.current = null;
    }, [formulario]);

    const abrir = (fila) => {
        retorno.current = fila ? `editar-propio-${fila.id}` : 'agregar-propio';
        setFormulario(fila ? fila.id : 'nuevo');
    };

    const guardar = async (fila, datos) => {
        if (await onGuardarPropio(fila, datos)) setFormulario(null);
    };

    const renderFila = (fila) => (
        formulario === fila.id ? (
            <FormularioPropio
                key={fila.id}
                fila={fila}
                ocupado={ocupado}
                onGuardar={(datos) => guardar(fila, datos)}
                onCancelar={() => setFormulario(null)}
            />
        ) : (
            <ItemRequisito
                key={fila.id}
                fila={fila}
                editable={editable}
                ocupado={ocupado || formulario !== null}
                onEditarPropio={abrir}
                onEliminarPropio={onEliminarPropio}
                {...acciones}
            />
        )
    );

    return (
        <div ref={contenedor}>
            <AvisosDuplicados avisos={expediente.avisos_duplicados} />

            {deCatalogo.length === 0 && !propios && (
                <div className="exv-vacio">
                    <b>Sin requisitos que mostrar</b>
                    <p>
                        {expediente.plantillas.length === 0
                            ? 'Elige al menos una plantilla en la configuración para armar el checklist.'
                            : 'Ajusta la configuración del proyecto para generar el checklist.'}
                    </p>
                </div>
            )}

            {deCatalogo.map((bloque) => (bloque.tipo === 'sgr' ? (
                <BloqueSgr key={bloque.clave} bloque={bloque} conCabecera={conCabecera} sectores={sectores} renderFila={renderFila} />
            ) : (
                <BloqueSimple key={bloque.clave} bloque={bloque} renderFila={renderFila} />
            )))}

            {(propios || editable) && (
                <section className="exv-seccion exv-bloque exv-propios" aria-labelledby="exv-propios-titulo">
                    <div className="exv-plantilla-titulo">
                        <h3 id="exv-propios-titulo">{TITULO_PROPIOS}</h3>
                        {propios && <span className="exv-cita">{propios.cumplidos}/{propios.total}</span>}
                    </div>
                    {!propios && (
                        <p className="exv-propios-vacio exv-no-imprimir">
                            Agrega aquí lo que el proyecto debe cumplir y no trae ninguna plantilla. No cambia ninguna plantilla.
                        </p>
                    )}
                    {propios?.filas.map(renderFila)}
                    {editable && (formulario === 'nuevo' ? (
                        <FormularioPropio
                            ocupado={ocupado}
                            onGuardar={(datos) => guardar(null, datos)}
                            onCancelar={() => setFormulario(null)}
                        />
                    ) : (
                        <button
                            type="button"
                            className="exv-btn exv-no-imprimir"
                            data-foco="agregar-propio"
                            disabled={ocupado || formulario !== null}
                            onClick={() => abrir(null)}
                        >
                            <FontAwesomeIcon icon={faPlus} /> Agregar requisito propio
                        </button>
                    ))}
                </section>
            )}
        </div>
    );
};

export default ExpedienteChecklist;
