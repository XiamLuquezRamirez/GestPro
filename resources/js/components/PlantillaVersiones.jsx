import React from 'react';
import Swal from 'sweetalert2';
import { faBullhorn, faPlus, faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { mensajeError } from '../utils/expedienteChecklist';

const avisarError = (titulo, error) => Swal.fire({ icon: 'error', title: titulo, text: mensajeError(error) });
const fecha = (iso) => (iso ? new Date(iso).toLocaleDateString('es-CO') : '—');

/** Historial de versiones: quién publicó y cuándo; publicar, crear y eliminar borrador. */
const PlantillaVersiones = ({ plantilla, onCambio }) => {
    const borrador = plantilla.versiones.find((v) => v.estado === 'borrador');

    const confirmar = async (titulo, texto, boton) => (await Swal.fire({
        icon: 'warning', title: titulo, text: texto, showCancelButton: true, confirmButtonText: boton, cancelButtonText: 'Cancelar',
    })).isConfirmed;

    const accion = async (peticion, error) => {
        try {
            await peticion();
            await onCambio();
        } catch (e) {
            avisarError(error, e);
        }
    };

    const publicar = async (version) => {
        if (!(await confirmar(`¿Publicar la versión v${version.numero}?`, 'Pasa a ser la vigente: la usarán los expedientes nuevos. Los que ya existen conservan la suya. Una versión publicada no se modifica.', 'Sí, publicar'))) return;
        await accion(() => axios.post(`/catalogo-requisitos/versiones/${version.id}/publicar`), 'No se pudo publicar la versión');
    };

    const eliminar = async (version) => {
        if (!(await confirmar(`¿Eliminar el borrador v${version.numero}?`, 'Se pierden sus cambios. Las versiones publicadas no se tocan.', 'Sí, eliminar'))) return;
        await accion(() => axios.delete(`/catalogo-requisitos/versiones/${version.id}`), 'No se pudo eliminar el borrador');
    };

    const crear = async () => {
        const { isConfirmed, value } = await Swal.fire({
            title: 'Nuevo borrador',
            text: plantilla.vigente ? 'Copia la versión vigente para editarla.' : 'La plantilla aún no tiene versión publicada: el borrador nace vacío.',
            input: 'text',
            inputLabel: 'Qué cambia (opcional)',
            inputAttributes: { maxlength: '255' },
            showCancelButton: true,
            confirmButtonText: 'Crear',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        await accion(() => axios.post(`/plantillas/${plantilla.id}/versiones`, { descripcion: value || null }), 'No se pudo crear el borrador');
    };

    return (
        <div className="pl-versiones">
            {!borrador && (
                <div className="pl-barra">
                    <p className="pl-ayuda">Para cambiar requisitos se crea un borrador, se edita y se publica.</p>
                    <button type="button" className="gp-boton gp-boton-primario" onClick={crear}>
                        <FontAwesomeIcon icon={faPlus} /> Crear borrador
                    </button>
                </div>
            )}
            <div className="pl-tabla-versiones" role="table" aria-label="Versiones de la plantilla">
                <div role="row" className="pl-versiones-cabecera">
                    <span role="columnheader">Versión</span>
                    <span role="columnheader">Estado</span>
                    <span role="columnheader">Publicada</span>
                    <span role="columnheader">Requisitos</span>
                    <span role="columnheader">Expedientes</span>
                    <span role="columnheader">Acciones</span>
                </div>
                {plantilla.versiones.map((v) => (
                    <div role="row" key={v.id}>
                        <span role="cell"><b>v{v.numero}</b>{v.descripcion && <small> · {v.descripcion}</small>}</span>
                        <span role="cell">{v.estado === 'borrador' ? 'Borrador' : (v.vigente ? 'Vigente' : 'Publicada')}</span>
                        <span role="cell">{v.estado === 'borrador' ? '—' : `${fecha(v.fecha_publicacion)}${v.publicada_por ? ` por ${v.publicada_por}` : ''}`}</span>
                        <span role="cell">{v.requisitos}</span>
                        <span role="cell">{v.expedientes}</span>
                        <span role="cell" className="pl-fila-acciones">
                            {v.estado === 'borrador' && (
                                <>
                                    <button type="button" className="gp-boton gp-boton-primario" onClick={() => publicar(v)}>
                                        <FontAwesomeIcon icon={faBullhorn} /> Publicar
                                    </button>
                                    <button type="button" className="gp-icono" aria-label={`Eliminar el borrador v${v.numero}`} onClick={() => eliminar(v)}>
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
