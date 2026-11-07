import React from 'react';
import Swal from 'sweetalert2';
import { faBullhorn, faPlus, faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
import { etiquetaVersion, textoRequisitosVersion } from '../utils/plantillasChecklist';
import useOcupado from './useOcupado';

const COLUMNAS = ['Versión', 'Estado', 'Publicada', 'Requisitos', 'Expedientes', 'Acciones'];

const fecha = (iso) => (iso ? new Date(iso).toLocaleDateString('es-CO') : '—');

/** Historial de versiones: quién publicó y cuándo; publicar, crear y eliminar borrador. */
const PlantillaVersiones = ({ plantilla, onCambio }) => {
    const [ocupado, ejecutar] = useOcupado();

    const confirmar = async (titulo, texto, boton) => (await Swal.fire({
        icon: 'warning', titleText: titulo, text: texto, showCancelButton: true, confirmButtonText: boton, cancelButtonText: 'Cancelar',
    })).isConfirmed;

    // Si el registro ya no existe (404) o cambió de estado (422), se recarga para mostrar la realidad.
    const accion = async (peticion, titulo) => {
        try {
            await peticion();
            await onCambio();
        } catch (e) {
            const estado = e?.response?.status;
            await avisarError(titulo, e);
            if (estado === 404 || estado === 422) await onCambio();
        }
    };

    const publicar = (version) => ejecutar(async () => {
        if (!(await confirmar(`¿Publicar la versión v${version.numero}?`, 'Pasa a ser la vigente: la usarán los expedientes nuevos. Los que ya existen conservan la suya. Una versión publicada no se modifica.', 'Sí, publicar'))) return;
        await accion(() => axios.post(`/catalogo-requisitos/versiones/${version.id}/publicar`), 'No se pudo publicar la versión');
    });

    const eliminar = (version) => ejecutar(async () => {
        if (!(await confirmar(`¿Eliminar el borrador v${version.numero}?`, 'Se pierden sus cambios. Las versiones publicadas no se tocan.', 'Sí, eliminar'))) return;
        await accion(() => axios.delete(`/catalogo-requisitos/versiones/${version.id}`), 'No se pudo eliminar el borrador');
    });

    const crear = () => ejecutar(async () => {
        const { isConfirmed, value } = await Swal.fire({
            titleText: 'Nuevo borrador',
            text: plantilla.vigente ? 'Copia la versión vigente para editarla.' : 'La plantilla aún no tiene versión publicada: el borrador nace vacío.',
            input: 'text',
            inputLabel: '¿Qué cambia? (opcional)',
            inputAttributes: { maxlength: '255' },
            showCancelButton: true,
            confirmButtonText: 'Crear',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        await accion(() => axios.post(`/plantillas/${plantilla.id}/versiones`, { descripcion: value || null }), 'No se pudo crear el borrador');
    });

    return (
        <div className="pl-versiones">
            {!plantilla.borrador && (
                <div className="pl-barra">
                    <p className="pl-ayuda">Para cambiar requisitos se crea un borrador, se edita y se publica.</p>
                    <button type="button" className="gp-boton gp-boton-primario" onClick={crear} disabled={ocupado}>
                        <FontAwesomeIcon icon={faPlus} /> Crear borrador
                    </button>
                </div>
            )}
            <div className="pl-tabla-versiones" role="table" aria-label="Versiones de la plantilla">
                <div role="row" className="pl-versiones-cabecera">
                    {COLUMNAS.map((c) => <span key={c} role="columnheader">{c}</span>)}
                </div>
                {plantilla.versiones.map((v) => (
                    <div role="row" key={v.id}>
                        <span role="cell" data-label={COLUMNAS[0]}><b>v{v.numero}</b>{v.descripcion && <small> · {v.descripcion}</small>}</span>
                        <span role="cell" data-label={COLUMNAS[1]}>{v.estado === 'borrador' ? 'Borrador' : (v.vigente ? 'Vigente' : 'Publicada')}</span>
                        <span role="cell" data-label={COLUMNAS[2]}>{v.estado === 'borrador' ? '—' : `${fecha(v.fecha_publicacion)}${v.publicada_por ? ` por ${v.publicada_por}` : ''}`}</span>
                        <span role="cell" data-label={COLUMNAS[3]}>{textoRequisitosVersion(v)}</span>
                        <span role="cell" data-label={COLUMNAS[4]}>{v.expedientes}</span>
                        <span role="cell" data-label={COLUMNAS[5]} className="pl-fila-acciones">
                            {v.estado === 'borrador' && (
                                <>
                                    <button type="button" className="gp-boton gp-boton-primario" onClick={() => publicar(v)} disabled={ocupado}>
                                        <FontAwesomeIcon icon={faBullhorn} /> Publicar
                                    </button>
                                    <button type="button" className="gp-icono" aria-label={`Eliminar ${etiquetaVersion(v)}`} onClick={() => eliminar(v)} disabled={ocupado}>
                                        <FontAwesomeIcon icon={faTrash} />
                                    </button>
                                </>
                            )}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
};

export default PlantillaVersiones;
