import React, { useCallback, useEffect, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faEdit, faPlus } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
import { mensajeApi } from '../utils/plantillasChecklist';
import {
    REGLAS_MODALIDAD, datosCatalogo, erroresPorCampo, ordenarCatalogo, puedeAdministrarCatalogos, textoEstadoCatalogo,
} from '../utils/contratacion';
import FormularioCatalogo from './FormularioCatalogo';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';

/**
 * Parámetros → Modalidades. Las modalidades no se eliminan: se desactivan, y un proceso conserva
 * la suya aunque se desactive. La clave la fija la siembra. Solo un Administrador modifica.
 */
const CatalogoModalidades = ({ user }) => {
    const [modalidades, setModalidades] = useState(null);
    const [error, setError] = useState(null);
    const [editando, setEditando] = useState(null); // null, 'nueva' o la modalidad
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    // data-foco del control que debe recuperar el foco cuando la lista vuelva a pintarse: el botón
    // que abrió el formulario o el de activar o desactivar que se usó.
    const retorno = useRef(null);
    const administra = puedeAdministrarCatalogos(user);

    const cargar = useCallback(async () => {
        try {
            const { data } = await axios.get('/modalidades');
            setModalidades(ordenarCatalogo(data));
            setError(null);
        } catch (e) {
            setError(mensajeApi(e));
        }
    }, []);

    useEffect(() => { cargar(); }, [cargar]);

    // Cuando se cierra el formulario o termina el cambio de estado, el foco vuelve a su control. Se
    // espera a que nada esté ocupado: un botón deshabilitado no recibe el foco.
    useEffect(() => {
        if (editando || ocupado || !retorno.current) return;
        contenedor.current?.querySelector(`[data-foco="${retorno.current}"]:not(:disabled)`)?.focus();
        retorno.current = null;
    }, [editando, ocupado, modalidades]);

    const abrir = (modalidad) => {
        retorno.current = modalidad === 'nueva' ? 'nueva' : `editar:${modalidad.id}`;
        setEditando(modalidad);
    };

    const guardar = (formulario) => ejecutar(async () => {
        const item = editando === 'nueva' ? null : editando;
        try {
            await axios.post('/guardarModalidad', datosCatalogo(formulario, item));
            setEditando(null);
            await cargar();
            return null;
        } catch (e) {
            const errores = erroresPorCampo(e, ['nombre']);
            if (!errores || errores.otros.length || !Object.keys(errores.campos).length) avisarError('No se pudo guardar la modalidad', e);
            return errores?.campos ?? null;
        }
    });

    const alternarActiva = (modalidad) => ejecutar(async () => {
        retorno.current = `activar:${modalidad.id}`;
        if (modalidad.activo) {
            const { isConfirmed } = await Swal.fire({
                icon: 'warning',
                titleText: `¿Desactivar "${modalidad.nombre}"?`,
                text: 'No se ofrecerá en procesos nuevos. Los procesos que ya la tienen la conservan.',
                showCancelButton: true,
                confirmButtonText: 'Sí, desactivar',
                cancelButtonText: 'Cancelar',
            });
            if (!isConfirmed) return;
        }
        try {
            await axios.post('/activarModalidad', { id: modalidad.id });
            await cargar();
        } catch (e) {
            avisarError('No se pudo cambiar el estado', e);
        }
    });

    if (editando) {
        return (
            <FormularioCatalogo
                titulo={editando === 'nueva' ? 'Nueva modalidad' : 'Editar modalidad'}
                item={editando === 'nueva' ? null : editando}
                items={modalidades}
                reglas={REGLAS_MODALIDAD}
                guardando={ocupado}
                onGuardar={guardar}
                onCancelar={() => setEditando(null)}
            />
        );
    }

    return (
        <div className="ct-seccion" ref={contenedor}>
            <div className="ct-barra">
                <p className="pl-ayuda">
                    {administra
                        ? 'Las modalidades no se eliminan: desactiva las que ya no uses. Los procesos conservan la suya.'
                        : 'Solo un Administrador puede modificar las modalidades.'}
                </p>
                {administra && (
                    <button type="button" className="gp-boton gp-boton-primario" data-foco="nueva" onClick={() => abrir('nueva')} disabled={ocupado || modalidades === null}>
                        <FontAwesomeIcon icon={faPlus} /> Nueva modalidad
                    </button>
                )}
            </div>

            {error && (
                <p className="pl-error" role="alert">
                    {error} <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>
                </p>
            )}
            {modalidades === null && !error && <p className="gp-vacio" role="status">Cargando modalidades…</p>}
            {modalidades !== null && modalidades.length === 0 && <p className="gp-vacio">Aún no hay modalidades.</p>}

            {modalidades !== null && modalidades.length > 0 && (
                <div className="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Clave</th>
                                <th>Estado</th>
                                {administra && <th>Acciones</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {modalidades.map((m) => (
                                <tr key={m.id} className={m.activo ? undefined : 'ct-inactiva'}>
                                    <td data-etiqueta="Nombre">{m.nombre}</td>
                                    <td data-etiqueta="Clave">{m.clave ? <span className="ct-clave">{m.clave}</span> : '—'}</td>
                                    <td data-etiqueta="Estado">
                                        <span className={`status-badge ${m.activo ? 'active' : 'inactive'}`}>{textoEstadoCatalogo(m.activo)}</span>
                                    </td>
                                    {administra && (
                                        <td className="celda-acciones">
                                            <div className="action-buttons">
                                                <button type="button" className="btn-edit" data-foco={`editar:${m.id}`} onClick={() => abrir(m)} disabled={ocupado} aria-label={`Editar ${m.nombre}`}>
                                                    <FontAwesomeIcon icon={faEdit} /> Editar
                                                </button>
                                                <button
                                                    type="button"
                                                    className={`btn-toggle ${m.activo ? 'deactivate' : 'activate'}`}
                                                    data-foco={`activar:${m.id}`}
                                                    onClick={() => alternarActiva(m)}
                                                    disabled={ocupado}
                                                    aria-label={`${m.activo ? 'Desactivar' : 'Activar'} ${m.nombre}`}
                                                >
                                                    {m.activo ? 'Desactivar' : 'Activar'}
                                                </button>
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
};

export default CatalogoModalidades;
