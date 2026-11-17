import React, { useCallback, useEffect, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowDown, faArrowUp, faPen, faPlus, faPowerOff } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
import { mensajeApi } from '../utils/plantillasChecklist';
import {
    REGLAS_ETAPA, datosCatalogo, erroresPorCampo, idsTrasMover, ordenarCatalogo, puedeAdministrarCatalogos, textoEstadoCatalogo,
} from '../utils/contratacion';
import FormularioCatalogo from './FormularioCatalogo';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';

/**
 * Parámetros → Etapas de contratación: las etapas de un proceso en su orden. No se eliminan: se
 * desactivan. La clave se genera del nombre al crearla y no cambia. Solo un Administrador modifica.
 */
const CatalogoEtapasContratacion = ({ user }) => {
    const [etapas, setEtapas] = useState(null);
    const [error, setError] = useState(null);
    const [editando, setEditando] = useState(null); // null, 'nueva' o la etapa
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    // data-foco del control que debe recuperar el foco cuando la lista vuelva a pintarse: el botón
    // que abrió el formulario, la flecha de la etapa que se movió o el de activar o desactivar.
    const retorno = useRef(null);
    const administra = puedeAdministrarCatalogos(user);

    const cargar = useCallback(async () => {
        try {
            const { data } = await axios.get('/etapasContratacion');
            setEtapas(ordenarCatalogo(data));
            setError(null);
        } catch (e) {
            setError(mensajeApi(e));
        }
    }, []);

    useEffect(() => { cargar(); }, [cargar]);

    // Cuando se cierra el formulario, llega el orden nuevo o termina el cambio de estado, el foco
    // vuelve a su control. Una flecha que quedó en el borde está deshabilitada: el foco pasa a la
    // otra flecha de la etapa.
    useEffect(() => {
        if (editando || ocupado || !retorno.current) return;
        const buscar = (clave) => contenedor.current?.querySelector(`[data-foco="${clave}"]:not(:disabled)`);
        const [accion, id] = retorno.current.split(':');
        const otra = { subir: 'bajar', bajar: 'subir' }[accion];
        (buscar(retorno.current) ?? (otra ? buscar(`${otra}:${id}`) : null))?.focus();
        retorno.current = null;
    }, [editando, ocupado, etapas]);

    const abrir = (etapa) => {
        retorno.current = etapa === 'nueva' ? 'nueva' : `editar:${etapa.id}`;
        setEditando(etapa);
    };

    const guardar = (formulario) => ejecutar(async () => {
        const item = editando === 'nueva' ? null : editando;
        try {
            await axios.post('/guardarEtapaContratacion', datosCatalogo(formulario, item, true));
            setEditando(null);
            await cargar();
            return null;
        } catch (e) {
            const errores = erroresPorCampo(e, ['nombre', 'descripcion']);
            if (!errores || errores.otros.length || !Object.keys(errores.campos).length) avisarError('No se pudo guardar la etapa', e);
            return errores?.campos ?? null;
        }
    });

    const alternarActiva = (etapa) => ejecutar(async () => {
        retorno.current = `activar:${etapa.id}`;
        if (etapa.activo) {
            const { isConfirmed } = await Swal.fire({
                icon: 'warning',
                titleText: `¿Desactivar "${etapa.nombre}"?`,
                text: 'No se ofrecerá en requisitos nuevos. Lo que ya la usa la conserva.',
                showCancelButton: true,
                confirmButtonText: 'Sí, desactivar',
                cancelButtonText: 'Cancelar',
            });
            if (!isConfirmed) return;
        }
        try {
            await axios.post('/activarEtapaContratacion', { id: etapa.id });
            await cargar();
        } catch (e) {
            avisarError('No se pudo cambiar el estado', e);
        }
    });

    // Se envía la lista completa; los botones quedan deshabilitados hasta que llega el orden nuevo.
    const moverEtapa = (indice, delta) => ejecutar(async () => {
        retorno.current = `${delta < 0 ? 'subir' : 'bajar'}:${etapas[indice].id}`;
        try {
            await axios.put('/etapasContratacion/orden', { ids: idsTrasMover(etapas, indice, delta) });
        } catch (e) {
            avisarError('No se pudo cambiar el orden', e);
        }
        // También tras un error: otra persona pudo cambiar el catálogo entretanto.
        await cargar();
    });

    if (editando) {
        return (
            <FormularioCatalogo
                titulo={editando === 'nueva' ? 'Nueva etapa' : 'Editar etapa'}
                item={editando === 'nueva' ? null : editando}
                items={etapas}
                reglas={REGLAS_ETAPA}
                conDescripcion
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
                        ? 'El orden es el de un proceso: ordena con las flechas. Las etapas no se eliminan: desactiva las que no uses.'
                        : 'Solo un Administrador puede modificar las etapas.'}
                </p>
                {administra && (
                    <button type="button" className="gp-boton gp-boton-primario" data-foco="nueva" onClick={() => abrir('nueva')} disabled={ocupado || etapas === null}>
                        <FontAwesomeIcon icon={faPlus} /> Nueva etapa
                    </button>
                )}
            </div>

            {error && (
                <p className="pl-error" role="alert">
                    {error} <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>
                </p>
            )}
            {etapas === null && !error && <p className="gp-vacio" role="status">Cargando etapas…</p>}
            {etapas !== null && etapas.length === 0 && <p className="gp-vacio">Aún no hay etapas.</p>}

            {etapas !== null && etapas.length > 0 && (
                <ol className="pl-tabla ct-etapas" aria-label="Etapas de contratación en orden">
                    {etapas.map((etapa, indice) => (
                        <li key={etapa.id} className={etapa.activo ? undefined : 'ct-inactiva'}>
                            <div className="ct-etapa">
                                <span className="ct-etapa-orden" aria-hidden="true">{indice + 1}</span>
                                <div className="ct-etapa-texto">
                                    <strong>{etapa.nombre}</strong>
                                    {etapa.descripcion && <small>{etapa.descripcion}</small>}
                                </div>
                                {!etapa.activo && <span className="pl-insignia pl-insignia-alerta">{textoEstadoCatalogo(false)}</span>}
                            </div>
                            {administra && (
                                <div className="pl-fila-acciones">
                                    <button type="button" className="gp-icono" data-foco={`subir:${etapa.id}`} aria-label={`Subir ${etapa.nombre}`} disabled={ocupado || indice === 0} onClick={() => moverEtapa(indice, -1)}>
                                        <FontAwesomeIcon icon={faArrowUp} />
                                    </button>
                                    <button type="button" className="gp-icono" data-foco={`bajar:${etapa.id}`} aria-label={`Bajar ${etapa.nombre}`} disabled={ocupado || indice === etapas.length - 1} onClick={() => moverEtapa(indice, 1)}>
                                        <FontAwesomeIcon icon={faArrowDown} />
                                    </button>
                                    <button type="button" className="gp-icono" data-foco={`editar:${etapa.id}`} aria-label={`Editar ${etapa.nombre}`} disabled={ocupado} onClick={() => abrir(etapa)}>
                                        <FontAwesomeIcon icon={faPen} />
                                    </button>
                                    <button
                                        type="button"
                                        className="gp-icono"
                                        data-foco={`activar:${etapa.id}`}
                                        aria-label={`${etapa.activo ? 'Desactivar' : 'Activar'} ${etapa.nombre}`}
                                        title={etapa.activo ? 'Desactivar' : 'Activar'}
                                        disabled={ocupado}
                                        onClick={() => alternarActiva(etapa)}
                                    >
                                        <FontAwesomeIcon icon={faPowerOff} />
                                    </button>
                                </div>
                            )}
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
};

export default CatalogoEtapasContratacion;
