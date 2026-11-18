import React, { useCallback, useEffect, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faEdit, faPlus } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
import { mensajeApi } from '../utils/plantillasChecklist';
import {
    REGLAS_TIPO_CONTRATO, datosCatalogo, erroresPorCampo, ordenarCatalogo, puedeAdministrarCatalogos, textoEstadoCatalogo,
} from '../utils/contratacion';
import FormularioCatalogo from './FormularioCatalogo';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';

/**
 * Parámetros → Tipos de contrato. Los tipos no se eliminan: se desactivan, y un contrato conserva
 * el suyo aunque se desactive. Solo un Administrador modifica.
 */
const CatalogoTiposContrato = ({ user }) => {
    const [tipos, setTipos] = useState(null);
    const [error, setError] = useState(null);
    const [editando, setEditando] = useState(null); // null, 'nueva' o el tipo
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    // data-foco del control que debe recuperar el foco cuando la lista vuelva a pintarse: el botón
    // que abrió el formulario o el de activar o desactivar que se usó.
    const retorno = useRef(null);
    const administra = puedeAdministrarCatalogos(user);

    const cargar = useCallback(async () => {
        try {
            const { data } = await axios.get('/tiposProcesos');
            setTipos(ordenarCatalogo(data));
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
    }, [editando, ocupado, tipos]);

    const abrir = (tipo) => {
        retorno.current = tipo === 'nueva' ? 'nueva' : `editar:${tipo.id}`;
        setEditando(tipo);
    };

    const guardar = (formulario) => ejecutar(async () => {
        const item = editando === 'nueva' ? null : editando;
        try {
            await axios.post('/guardarTipoProceso', datosCatalogo(formulario, item));
            setEditando(null);
            await cargar();
            return null;
        } catch (e) {
            const errores = erroresPorCampo(e, ['nombre']);
            if (!errores || errores.otros.length || !Object.keys(errores.campos).length) avisarError('No se pudo guardar el tipo de contrato', e);
            return errores?.campos ?? null;
        }
    });

    const alternarActiva = (tipo) => ejecutar(async () => {
        retorno.current = `activar:${tipo.id}`;
        if (tipo.activo) {
            const { isConfirmed } = await Swal.fire({
                icon: 'warning',
                titleText: `¿Desactivar "${tipo.nombre}"?`,
                text: 'No se ofrecerá en contratos nuevos. Los contratos que ya lo tienen lo conservan.',
                showCancelButton: true,
                confirmButtonText: 'Sí, desactivar',
                cancelButtonText: 'Cancelar',
            });
            if (!isConfirmed) return;
        }
        try {
            await axios.post('/activarTipoProceso', { id: tipo.id });
            await cargar();
        } catch (e) {
            avisarError('No se pudo cambiar el estado', e);
        }
    });

    if (editando) {
        return (
            <FormularioCatalogo
                titulo={editando === 'nueva' ? 'Nuevo tipo de contrato' : 'Editar tipo de contrato'}
                item={editando === 'nueva' ? null : editando}
                items={tipos}
                reglas={REGLAS_TIPO_CONTRATO}
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
                        ? 'Los tipos de contrato no se eliminan: desactiva los que ya no uses. Los contratos conservan el suyo.'
                        : 'Solo un Administrador puede modificar los tipos de contrato.'}
                </p>
                {administra && (
                    <button type="button" className="gp-boton gp-boton-primario" data-foco="nueva" onClick={() => abrir('nueva')} disabled={ocupado || tipos === null}>
                        <FontAwesomeIcon icon={faPlus} /> Nuevo tipo de contrato
                    </button>
                )}
            </div>

            {error && (
                <p className="pl-error" role="alert">
                    {error} <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>
                </p>
            )}
            {tipos === null && !error && <p className="gp-vacio" role="status">Cargando tipos de contrato…</p>}
            {tipos !== null && tipos.length === 0 && <p className="gp-vacio">Aún no hay tipos de contrato.</p>}

            {tipos !== null && tipos.length > 0 && (
                <div className="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Estado</th>
                                {administra && <th>Acciones</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {tipos.map((t) => (
                                <tr key={t.id} className={t.activo ? undefined : 'ct-inactiva'}>
                                    <td data-etiqueta="Nombre">{t.nombre}</td>
                                    <td data-etiqueta="Estado">
                                        <span className={`status-badge ${t.activo ? 'active' : 'inactive'}`}>{textoEstadoCatalogo(t.activo, true)}</span>
                                    </td>
                                    {administra && (
                                        <td className="celda-acciones">
                                            <div className="action-buttons">
                                                <button type="button" className="btn-edit" data-foco={`editar:${t.id}`} onClick={() => abrir(t)} disabled={ocupado} aria-label={`Editar ${t.nombre}`}>
                                                    <FontAwesomeIcon icon={faEdit} /> Editar
                                                </button>
                                                <button
                                                    type="button"
                                                    className={`btn-toggle ${t.activo ? 'deactivate' : 'activate'}`}
                                                    data-foco={`activar:${t.id}`}
                                                    onClick={() => alternarActiva(t)}
                                                    disabled={ocupado}
                                                    aria-label={`${t.activo ? 'Desactivar' : 'Activar'} ${t.nombre}`}
                                                >
                                                    {t.activo ? 'Desactivar' : 'Activar'}
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

export default CatalogoTiposContrato;
