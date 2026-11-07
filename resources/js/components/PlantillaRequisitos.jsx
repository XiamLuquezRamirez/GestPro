import React, { useEffect, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowDown, faArrowUp, faPen, faPlus, faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { ORDEN_SECCIONES, SECCIONES } from '../utils/expedienteChecklist';
import { avisarError } from '../utils/avisosPlantillas';
import {
    CARACTERES, CATEGORIAS, CONDICIONADO, FUENTES_NORMATIVAS, TIPOS_REQUISITO, datosRequisito, etiquetaCaracter, etiquetaCategoria, formularioRequisito,
    mensajeApi, mover, porRedactar, requisitoVacio,
} from '../utils/plantillasChecklist';
import useOcupado from './useOcupado';

const Campo = ({ etiqueta, children, ancho }) => (
    <label className={`pl-campo${ancho ? ' pl-campo-ancho' : ''}`}>
        <span>{etiqueta}</span>
        {children}
    </label>
);

/** Formulario de un requisito. En el motor sgr agrega sección, sector, tipo y condición. */
const FormularioRequisito = ({ motor, esSgr, inicial, catalogoSgr, guardando, onGuardar, onCancelar }) => {
    const [f, setF] = useState(inicial);
    const [revisionCondicion, setRevisionCondicion] = useState(null);
    const cambiar = (campo, valor) => setF((actual) => ({ ...actual, [campo]: valor }));
    const texto = (campo, props = {}) => <input value={f[campo]} onChange={(e) => cambiar(campo, e.target.value)} {...props} />;

    const sector = catalogoSgr?.sectores.find((s) => s.id === Number(f.sector_id));

    const validarCondicion = async () => {
        try {
            const { data } = await axios.post('/catalogo-requisitos/validar-condicion', { condicion: f.condicion });
            setRevisionCondicion(data.valida ? 'La condición es válida.' : data.errores.join(' '));
        } catch (e) {
            setRevisionCondicion(mensajeApi(e));
        }
    };

    return (
        <form className="gp-panel pl-formulario pl-formulario-requisito" onSubmit={(e) => { e.preventDefault(); onGuardar(f); }}>
            <Campo etiqueta="Nombre del requisito" ancho>
                <textarea rows={2} required autoFocus value={f.texto} onChange={(e) => cambiar('texto', e.target.value)} />
            </Campo>
            <Campo etiqueta="Descripción" ancho>
                <textarea rows={2} value={f.descripcion} onChange={(e) => cambiar('descripcion', e.target.value)} />
            </Campo>

            {motor === 'sgr' && (
                <>
                    <Campo etiqueta="Clave">{texto('clave', { required: true, maxLength: 40, pattern: '[a-z0-9_]+', title: 'Solo minúsculas, números y guion bajo.' })}</Campo>
                    <Campo etiqueta="Código">{texto('codigo', { required: true, maxLength: 60 })}</Campo>
                    <Campo etiqueta="Sección">
                        <select value={f.seccion} onChange={(e) => cambiar('seccion', e.target.value)}>
                            {ORDEN_SECCIONES.map((s) => <option key={s} value={s}>{SECCIONES[s].num} {SECCIONES[s].titulo}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta="Fuente normativa">
                        <select value={f.fuente} onChange={(e) => cambiar('fuente', e.target.value)}>
                            {FUENTES_NORMATIVAS.map(([v, t]) => <option key={v} value={v}>{t}</option>)}
                        </select>
                    </Campo>
                    {f.seccion === 'C' && (
                        <>
                            <Campo etiqueta="Sector SGR">
                                <select value={f.sector_id} onChange={(e) => setF((a) => ({ ...a, sector_id: e.target.value, tipo_id: '' }))}>
                                    <option value="">Elige un sector</option>
                                    {(catalogoSgr?.sectores ?? []).map((s) => <option key={s.id} value={s.id}>{s.nombre}{!s.activo && ' (inactivo)'}</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta="Tipo de proyecto">
                                <select value={f.tipo_id} onChange={(e) => cambiar('tipo_id', e.target.value)}>
                                    <option value="">Todo el sector</option>
                                    {(sector?.tipos ?? []).map((t) => <option key={t.id} value={t.id}>{t.nombre}{!t.activo && ' (inactivo)'}</option>)}
                                </select>
                            </Campo>
                        </>
                    )}
                    <Campo etiqueta="Subtítulo">{texto('subtitulo', { maxLength: 255 })}</Campo>
                    <div className="pl-campo">
                        <span id="pl-condicion-etiqueta">Condición (evaluador SGR)</span>
                        <span className="pl-en-linea">
                            <input
                                aria-labelledby="pl-condicion-etiqueta"
                                value={f.condicion}
                                maxLength={255}
                                placeholder="siempre, fase=3, comp=predios…"
                                onChange={(e) => { cambiar('condicion', e.target.value); setRevisionCondicion(null); }}
                            />
                            <button type="button" className="gp-boton" onClick={validarCondicion}>Validar</button>
                        </span>
                        {revisionCondicion && <small className="pl-ayuda" role="status">{revisionCondicion}</small>}
                    </div>
                </>
            )}

            <Campo etiqueta="Categoría">
                <select value={f.categoria} onChange={(e) => cambiar('categoria', e.target.value)}>
                    <option value="">Sin categoría</option>
                    {CATEGORIAS.map(([v, t]) => <option key={v} value={v}>{t}</option>)}
                </select>
            </Campo>
            <Campo etiqueta="Tipo de requisito">
                <select value={f.tipo_requisito} onChange={(e) => cambiar('tipo_requisito', e.target.value)}>
                    <option value="">Sin definir</option>
                    {TIPOS_REQUISITO.map(([v, t]) => <option key={v} value={v}>{t}</option>)}
                </select>
            </Campo>

            {motor === 'simple' && (
                <>
                    <Campo etiqueta="Carácter">
                        <select value={f.caracter} onChange={(e) => cambiar('caracter', e.target.value)}>
                            {CARACTERES.map(([v, t]) => <option key={v} value={v}>{t}</option>)}
                        </select>
                    </Campo>
                    {f.caracter === CONDICIONADO && (
                        <Campo etiqueta="¿Cuándo aplica?" ancho>
                            <textarea rows={2} required value={f.condicion_descripcion} onChange={(e) => cambiar('condicion_descripcion', e.target.value)} />
                            {porRedactar(f) && (
                                <small className="pl-ayuda">Reemplaza la "Condición original" por una descripción en palabras de cuándo aplica este requisito.</small>
                            )}
                        </Campo>
                    )}
                </>
            )}

            <Campo etiqueta="Responsable de cumplimiento (cargo)">{texto('cargo_cumplimiento', { maxLength: 150 })}</Campo>
            <Campo etiqueta="Responsable de revisión (cargo)">{texto('cargo_revision', { maxLength: 150 })}</Campo>
            <Campo etiqueta="Documento o soporte requerido">{texto('soporte_requerido', { maxLength: 255 })}</Campo>
            <Campo etiqueta="Referencia normativa">{texto('referencia_normativa', { maxLength: 255 })}</Campo>
            {!esSgr && <Campo etiqueta="Código común (para no repetir entre plantillas)">{texto('codigo_comun', { maxLength: 40 })}</Campo>}
            <Campo etiqueta="Observaciones e instrucciones" ancho>
                <textarea rows={2} value={f.nota} onChange={(e) => cambiar('nota', e.target.value)} />
            </Campo>
            <label className="pl-check">
                <input type="checkbox" checked={f.activo} onChange={(e) => cambiar('activo', e.target.checked)} /> Activo
            </label>
            {esSgr ? (
                <p className="pl-ayuda">En la plantilla SGR todo "no aplica" necesita aprobación.</p>
            ) : (
                <label className="pl-check">
                    <input type="checkbox" checked={f.requiere_aprobacion_no_aplica} onChange={(e) => cambiar('requiere_aprobacion_no_aplica', e.target.checked)} />
                    El "no aplica" necesita aprobación
                </label>
            )}

            <div className="pl-formulario-acciones">
                <button type="button" className="gp-boton" onClick={onCancelar} disabled={guardando}>Cancelar</button>
                <button type="submit" className="gp-boton gp-boton-primario" disabled={guardando}>Guardar requisito</button>
            </div>
        </form>
    );
};

/** Requisitos de la versión de trabajo: se editan en borrador; una versión publicada solo se lee. */
const PlantillaRequisitos = ({ plantilla, version, requisitos, onCambio }) => {
    const [editando, setEditando] = useState(null); // null, 'nuevo' o el requisito
    const [catalogoSgr, setCatalogoSgr] = useState(null);
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    const retorno = useRef(null); // data-foco del botón que abrió el formulario
    const editable = version?.estado === 'borrador';
    const esSgr = plantilla.es_sgr;
    const url = version ? `/catalogo-requisitos/versiones/${version.id}` : null;

    useEffect(() => {
        if (plantilla.motor !== 'sgr') return undefined;
        let vigente = true;
        axios.get('/catalogo-requisitos/vigente', { params: { incluir_inactivos: 1 } })
            .then(({ data }) => { if (vigente) setCatalogoSgr(data); })
            .catch((e) => { if (vigente) avisarError('No se pudo cargar el catálogo SGR (sectores y tipos)', e); });
        return () => { vigente = false; };
    }, [plantilla.motor]);

    // Al cerrar el formulario, el foco vuelve al botón que lo abrió.
    useEffect(() => {
        if (editando || !retorno.current) return;
        contenedor.current?.querySelector(`[data-foco="${retorno.current}"]`)?.focus();
        retorno.current = null;
    }, [editando]);

    const abrir = (requisito) => {
        retorno.current = requisito === 'nuevo' ? 'nuevo' : `editar-${requisito.id}`;
        setEditando(requisito);
    };

    const crearBorrador = () => ejecutar(async () => {
        try {
            await axios.post(`/plantillas/${plantilla.id}/versiones`, { descripcion: null });
            await onCambio();
        } catch (e) {
            avisarError('No se pudo crear el borrador', e);
        }
    });

    const guardar = (formulario) => ejecutar(async () => {
        const datos = datosRequisito(formulario, plantilla.motor);
        try {
            if (editando === 'nuevo') {
                await axios.post(`${url}/requisitos`, datos);
            } else {
                await axios.put(`${url}/requisitos/${editando.id}`, datos);
            }
            setEditando(null);
            await onCambio();
        } catch (e) {
            avisarError('No se pudo guardar el requisito', e);
        }
    });

    const eliminar = (requisito) => ejecutar(async () => {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            titleText: '¿Eliminar este requisito del borrador?',
            text: requisito.texto,
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        try {
            await axios.delete(`${url}/requisitos/${requisito.id}`);
            await onCambio();
        } catch (e) {
            avisarError('No se pudo eliminar el requisito', e);
        }
    });

    // Los botones quedan deshabilitados hasta que llega la recarga con el orden nuevo.
    const moverRequisito = (indice, delta) => ejecutar(async () => {
        const ids = mover(requisitos.map((r) => r.id), indice, delta);
        try {
            await axios.put(`${url}/requisitos-orden`, { ids });
            await onCambio();
        } catch (e) {
            avisarError('No se pudo cambiar el orden', e);
        }
    });

    const porRedactarTotal = requisitos.filter((r) => r.activo && porRedactar(r)).length;

    if (!version) {
        return (
            <div className="gp-panel pl-aviso">
                <p>La plantilla no tiene versiones.</p>
                <button type="button" className="gp-boton gp-boton-primario" onClick={crearBorrador} disabled={ocupado}>Crear borrador</button>
            </div>
        );
    }

    if (editando) {
        return (
            <FormularioRequisito
                motor={plantilla.motor}
                esSgr={esSgr}
                catalogoSgr={catalogoSgr}
                guardando={ocupado}
                inicial={editando === 'nuevo' ? requisitoVacio(plantilla.motor) : formularioRequisito(editando, plantilla.motor)}
                onGuardar={guardar}
                onCancelar={() => setEditando(null)}
            />
        );
    }

    return (
        <div className="pl-requisitos" ref={contenedor}>
            <div className="pl-barra">
                <p className="pl-ayuda">
                    {editable
                        ? `Editando el borrador v${version.numero}. Los cambios se ven en los expedientes nuevos cuando se publique.`
                        : `La versión v${version.numero} está publicada y no se modifica.`}
                </p>
                {editable ? (
                    <button type="button" className="gp-boton gp-boton-primario" data-foco="nuevo" onClick={() => abrir('nuevo')}>
                        <FontAwesomeIcon icon={faPlus} /> Agregar requisito
                    </button>
                ) : (
                    <button type="button" className="gp-boton gp-boton-primario" onClick={crearBorrador} disabled={ocupado}>Crear borrador para editar</button>
                )}
            </div>

            {porRedactarTotal > 0 && (
                <p className="pl-aviso-suave" role="status">
                    {porRedactarTotal} {porRedactarTotal === 1 ? 'condicionado por redactar' : 'condicionados por redactar'}: describe en palabras cuándo aplica cada uno antes de publicar.
                </p>
            )}

            {requisitos.length === 0 && <p className="gp-vacio">Esta versión aún no tiene requisitos.</p>}

            <ol className="pl-tabla">
                {requisitos.map((r, indice) => (
                    <li key={r.id} className={r.activo ? '' : 'inactivo'}>
                        <div className="pl-fila-texto">
                            <span className="pl-codigo">{r.codigo || r.clave}</span>
                            <span>{r.texto}</span>
                            <span className="pl-fila-etiquetas">
                                {r.categoria && <span className="pl-insignia pl-insignia-suave">{etiquetaCategoria(r.categoria)}</span>}
                                {plantilla.motor === 'simple' && <span className="pl-insignia pl-insignia-suave">{etiquetaCaracter(r.caracter)}</span>}
                                {porRedactar(r) && <span className="pl-insignia pl-insignia-alerta">Por redactar</span>}
                                {!r.activo && <span className="pl-insignia pl-insignia-alerta">Inactivo</span>}
                            </span>
                        </div>
                        {editable && (
                            <div className="pl-fila-acciones">
                                <button type="button" className="gp-icono" aria-label={`Subir ${r.texto}`} disabled={ocupado || indice === 0} onClick={() => moverRequisito(indice, -1)}>
                                    <FontAwesomeIcon icon={faArrowUp} />
                                </button>
                                <button type="button" className="gp-icono" aria-label={`Bajar ${r.texto}`} disabled={ocupado || indice === requisitos.length - 1} onClick={() => moverRequisito(indice, 1)}>
                                    <FontAwesomeIcon icon={faArrowDown} />
                                </button>
                                <button type="button" className="gp-icono" aria-label={`Editar ${r.texto}`} disabled={ocupado} data-foco={`editar-${r.id}`} onClick={() => abrir(r)}>
                                    <FontAwesomeIcon icon={faPen} />
                                </button>
                                <button type="button" className="gp-icono" aria-label={`Eliminar ${r.texto}`} disabled={ocupado} onClick={() => eliminar(r)}>
                                    <FontAwesomeIcon icon={faTrash} />
                                </button>
                            </div>
                        )}
                    </li>
                ))}
            </ol>
        </div>
    );
};

export default PlantillaRequisitos;
