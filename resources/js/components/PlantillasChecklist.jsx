import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Swal from 'sweetalert2';
import { faCopy, faPlus, faPowerOff } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { mensajeError } from '../utils/expedienteChecklist';
import { filtrarPlantillas, resumenVersiones, textoAsociaciones } from '../utils/plantillasChecklist';
import { RUTA_PLANTILLAS } from '../utils/navegacion';
import '../../css/Plantillas.css';

const avisarError = (titulo, error) => Swal.fire({ icon: 'error', title: titulo, text: mensajeError(error) });

/** Lista de plantillas de checklist (solo Administrador). */
const PlantillasChecklist = () => {
    const navigate = useNavigate();
    const [plantillas, setPlantillas] = useState(null);
    const [buscar, setBuscar] = useState('');
    const [soloActivas, setSoloActivas] = useState(false);
    const [error, setError] = useState(null);

    const cargar = useCallback(async (texto) => {
        try {
            const { data } = await axios.get('/plantillas', { params: texto ? { buscar: texto } : {} });
            setPlantillas(data);
            setError(null);
        } catch (e) {
            setError(mensajeError(e));
        }
    }, []);

    // La búsqueda espera a que se deje de escribir.
    useEffect(() => {
        const temporizador = setTimeout(() => cargar(buscar.trim()), 300);
        return () => clearTimeout(temporizador);
    }, [buscar, cargar]);

    const visibles = useMemo(() => filtrarPlantillas(plantillas ?? [], { soloActivas }), [plantillas, soloActivas]);

    const pedirNombre = (titulo, texto, valor = '') => Swal.fire({
        title: titulo,
        text: texto,
        input: 'text',
        inputValue: valor,
        inputLabel: 'Nombre',
        inputAttributes: { maxlength: '199' },
        inputValidator: (nombre) => (!nombre?.trim() ? 'Escribe el nombre de la plantilla.' : undefined),
        showCancelButton: true,
        confirmButtonText: 'Crear',
        cancelButtonText: 'Cancelar',
    });

    const crear = async () => {
        const { isConfirmed, value } = await pedirNombre(
            'Nueva plantilla',
            'Nace para todas las fuentes y sectores, con su primera versión en borrador. Ajústalo en el detalle.'
        );
        if (!isConfirmed) return;
        try {
            const { data } = await axios.post('/plantillas', { nombre: value.trim(), todas_fuentes: true, todos_sectores: true });
            navigate(`${RUTA_PLANTILLAS}/${data.id}`);
        } catch (e) {
            avisarError('No se pudo crear la plantilla', e);
        }
    };

    const duplicar = async (plantilla) => {
        const { isConfirmed, value } = await Swal.fire({
            title: `Duplicar "${plantilla.nombre}"`,
            html: plantilla.motor === 'sgr'
                ? 'Copia los requisitos activos de la versión vigente en una plantilla nueva, en borrador. Puedes quitar los que no sirvan antes de publicar.'
                : 'Copia los requisitos activos de la versión vigente en una plantilla nueva, en borrador.',
            input: 'text',
            inputValue: `Copia de ${plantilla.nombre}`,
            inputLabel: 'Nombre de la copia',
            inputValidator: (nombre) => (!nombre?.trim() ? 'Escribe el nombre de la plantilla.' : undefined),
            showCancelButton: true,
            confirmButtonText: 'Duplicar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;

        let motor = plantilla.motor;
        if (plantilla.motor === 'sgr') {
            const eleccion = await Swal.fire({
                title: '¿Cómo deciden si aplica cada requisito?',
                text: 'Simple: una persona decide los condicionados. SGR: el evaluador automático de regalías.',
                input: 'select',
                inputOptions: { simple: 'Simple (recomendado para otras fuentes)', sgr: 'SGR (evaluador automático)' },
                inputValue: 'simple',
                showCancelButton: true,
                confirmButtonText: 'Duplicar',
                cancelButtonText: 'Cancelar',
            });
            if (!eleccion.isConfirmed) return;
            motor = eleccion.value;
        }

        try {
            const { data } = await axios.post(`/plantillas/${plantilla.id}/duplicar`, { nombre: value.trim(), motor });
            navigate(`${RUTA_PLANTILLAS}/${data.id}`);
        } catch (e) {
            avisarError('No se pudo duplicar la plantilla', e);
        }
    };

    const alternarActiva = async (plantilla) => {
        if (plantilla.activo) {
            const { isConfirmed } = await Swal.fire({
                icon: 'warning',
                title: `¿Desactivar "${plantilla.nombre}"?`,
                text: 'No se ofrecerá en expedientes nuevos. Los que ya la usan no cambian.',
                showCancelButton: true,
                confirmButtonText: 'Sí, desactivar',
                cancelButtonText: 'Cancelar',
            });
            if (!isConfirmed) return;
        }
        try {
            await axios.post(`/plantillas/${plantilla.id}/activar`, { activo: !plantilla.activo });
            await cargar(buscar.trim());
        } catch (e) {
            avisarError('No se pudo cambiar el estado', e);
        }
    };

    return (
        <div className="pl-pagina">
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Catálogos</span>
                <h1>Plantillas de checklist</h1>
                <p>Conjuntos de requisitos que se aplican a los expedientes según su fuente de financiación y su sector.</p>
            </header>

            <div className="pl-barra">
                <input
                    type="search"
                    className="pl-buscar"
                    placeholder="Buscar por nombre o descripción"
                    aria-label="Buscar plantillas"
                    value={buscar}
                    onChange={(e) => setBuscar(e.target.value)}
                />
                <label className="pl-check">
                    <input type="checkbox" checked={soloActivas} onChange={(e) => setSoloActivas(e.target.checked)} /> Solo activas
                </label>
                <button type="button" className="gp-boton gp-boton-primario" onClick={crear}>
                    <FontAwesomeIcon icon={faPlus} /> Nueva plantilla
                </button>
            </div>

            {error && <p className="pl-error" role="alert">{error}</p>}
            {plantillas === null && !error && <p className="gp-vacio">Cargando plantillas…</p>}
            {plantillas !== null && visibles.length === 0 && <p className="gp-vacio">No hay plantillas que coincidan.</p>}

            <ul className="pl-lista">
                {visibles.map((p) => (
                    <li key={p.id} className={`gp-panel pl-tarjeta${p.activo ? '' : ' inactiva'}`}>
                        <div className="pl-tarjeta-cuerpo">
                            <div className="pl-tarjeta-titulo">
                                <Link to={`${RUTA_PLANTILLAS}/${p.id}`}>{p.nombre}</Link>
                                {p.es_sgr && <span className="pl-insignia">SGR</span>}
                                <span className="pl-insignia pl-insignia-suave">{p.motor === 'sgr' ? 'Evaluador SGR' : 'Simple'}</span>
                                {!p.activo && <span className="pl-insignia pl-insignia-alerta">Inactiva</span>}
                            </div>
                            {p.descripcion && <p className="pl-tarjeta-descripcion">{p.descripcion}</p>}
                            <dl className="pl-datos">
                                <div><dt>Versión</dt><dd>{resumenVersiones(p)}</dd></div>
                                <div><dt>Requisitos</dt><dd>{p.vigente?.requisitos ?? p.borrador?.requisitos ?? 0}</dd></div>
                                <div><dt>Fuentes</dt><dd>{textoAsociaciones(p.todas_fuentes, p.fuentes, 'Todas')}</dd></div>
                                <div><dt>Sectores</dt><dd>{textoAsociaciones(p.todos_sectores, p.sectores, 'Todos')}</dd></div>
                            </dl>
                        </div>
                        <div className="pl-tarjeta-acciones">
                            <button type="button" className="gp-boton" onClick={() => duplicar(p)} disabled={!p.vigente}
                                title={p.vigente ? undefined : 'Publica una versión antes de duplicar'}>
                                <FontAwesomeIcon icon={faCopy} /> Duplicar
                            </button>
                            {!p.es_sgr && (
                                <button type="button" className="gp-boton" onClick={() => alternarActiva(p)}>
                                    <FontAwesomeIcon icon={faPowerOff} /> {p.activo ? 'Desactivar' : 'Activar'}
                                </button>
                            )}
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
};

export default PlantillasChecklist;
