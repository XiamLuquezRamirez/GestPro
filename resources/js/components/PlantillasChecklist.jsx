import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import Swal from 'sweetalert2';
import { faCopy, faPlus, faPowerOff } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
import {
    USOS, USO_CONTRATACION, USO_VIABILIZACION, datosNuevaPlantilla, esContratacion, etiquetaUso, filtrarPlantillas, mensajeApi,
    resumenVersiones, textoAsociaciones, textoModalidades, usoDeConsulta,
} from '../utils/plantillasChecklist';
import { RUTA_PLANTILLAS } from '../utils/navegacion';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';

const DESCRIPCION_USO = {
    [USO_VIABILIZACION]: 'Conjuntos de requisitos que se aplican a los expedientes según su fuente de financiación y su sector.',
    [USO_CONTRATACION]: 'Conjuntos de requisitos de un proceso de contratación, por etapa, según su modalidad, su fuente de financiación y su sector.',
};

// Al crear se dice el uso: no cambia después.
const AYUDA_CREAR = {
    [USO_VIABILIZACION]: 'Plantilla de viabilización (el uso no cambia después). Nace para todas las fuentes y sectores, con su primera versión en borrador. Ajústalo en el detalle.',
    [USO_CONTRATACION]: 'Plantilla de contratación (el uso no cambia después). Nace para todas las fuentes y sectores, sin modalidades y con su primera versión en borrador. Elige sus modalidades en el detalle antes de publicar.',
};

/**
 * Lista de plantillas de checklist (solo Administrador), por uso: viabilización o contratación.
 * El uso elegido vive en la URL (`?uso=contratacion`) para que un enlace o el volver lo conserven.
 */
const PlantillasChecklist = () => {
    const navigate = useNavigate();
    const [consulta, setConsulta] = useSearchParams();
    const uso = usoDeConsulta(consulta.get('uso'));
    const [plantillas, setPlantillas] = useState(null);
    const [buscar, setBuscar] = useState('');
    const [soloActivas, setSoloActivas] = useState(false);
    const [error, setError] = useState(null);
    const [ocupado, ejecutar] = useOcupado();
    const peticion = useRef(0);
    // Lo que la lista muestra ahora (uso y búsqueda). Una recarga tras una acción pide esto y no
    // lo que había al empezarla: si entretanto se cambió de pestaña, no la pisa con la anterior.
    const vigente = useRef(null);

    // Solo vale la respuesta de la última petición: las anteriores llegan desordenadas y se descartan.
    const cargar = useCallback(async (texto, usoLista) => {
        const numero = ++peticion.current;
        try {
            const { data } = await axios.get('/plantillas', { params: { uso: usoLista, ...(texto ? { buscar: texto } : {}) } });
            if (numero !== peticion.current) return;
            setPlantillas(data);
            setError(null);
        } catch (e) {
            if (numero === peticion.current) setError(mensajeApi(e));
        }
    }, []);

    // La primera carga y el cambio de uso son inmediatos; la búsqueda espera a que se deje de
    // escribir. Al cambiar de uso no se muestran, mientras tanto, las plantillas del otro.
    useEffect(() => {
        const cambioUso = vigente.current?.uso !== uso;
        if (cambioUso && vigente.current !== null) {
            setPlantillas(null);
            setError(null);
        }
        vigente.current = { uso, buscar: buscar.trim() };
        const temporizador = setTimeout(() => cargar(buscar.trim(), uso), cambioUso ? 0 : 300);
        return () => clearTimeout(temporizador);
    }, [buscar, uso, cargar]);

    const recargar = () => cargar(vigente.current.buscar, vigente.current.uso);

    // Reemplaza la entrada del historial: cambiar de pestaña no es una página nueva.
    const elegirUso = (nuevo) => setConsulta((actual) => {
        const siguiente = new URLSearchParams(actual);
        if (nuevo === USO_VIABILIZACION) siguiente.delete('uso');
        else siguiente.set('uso', nuevo);
        return siguiente;
    }, { replace: true });

    const visibles = useMemo(() => filtrarPlantillas(plantillas ?? [], { soloActivas }), [plantillas, soloActivas]);

    const pedirNombre = (titulo, texto) => Swal.fire({
        titleText: titulo,
        text: texto,
        input: 'text',
        inputLabel: 'Nombre',
        inputAttributes: { maxlength: '199' },
        inputValidator: (nombre) => (!nombre?.trim() ? 'Escribe el nombre de la plantilla.' : undefined),
        showCancelButton: true,
        confirmButtonText: 'Crear',
        cancelButtonText: 'Cancelar',
    });

    const crear = () => ejecutar(async () => {
        const { isConfirmed, value } = await pedirNombre(`Nueva plantilla de ${etiquetaUso(uso).toLowerCase()}`, AYUDA_CREAR[uso]);
        if (!isConfirmed) return;
        try {
            const { data } = await axios.post('/plantillas', datosNuevaPlantilla(value, uso));
            navigate(`${RUTA_PLANTILLAS}/${data.id}`);
        } catch (e) {
            avisarError('No se pudo crear la plantilla', e);
        }
    });

    const duplicar = (plantilla) => ejecutar(async () => {
        const { isConfirmed, value } = await Swal.fire({
            titleText: `Duplicar "${plantilla.nombre}"`,
            html: plantilla.motor === 'sgr'
                ? 'Copia los requisitos activos de la versión vigente en una plantilla nueva, en borrador. Puedes quitar los que no sirvan antes de publicar.'
                : 'Copia los requisitos activos de la versión vigente en una plantilla nueva, en borrador.',
            input: 'text',
            inputValue: `Copia de ${plantilla.nombre}`,
            inputLabel: 'Nombre de la copia',
            inputAttributes: { maxlength: '199' },
            inputValidator: (nombre) => (!nombre?.trim() ? 'Escribe el nombre de la plantilla.' : undefined),
            showCancelButton: true,
            confirmButtonText: 'Duplicar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;

        let motor = plantilla.motor;
        if (plantilla.motor === 'sgr') {
            const eleccion = await Swal.fire({
                titleText: '¿Cómo deciden si aplica cada requisito?',
                html: 'Simple: una persona decide los condicionados. SGR: el evaluador automático de regalías.'
                    + '<br><br><b>Con Simple</b>, los requisitos que dependían de una condición del evaluador quedarán como condicionados por redactar: describe en palabras cuándo aplican antes de publicar. Los que dependían solo del sector quedan condicionados y se pueden publicar.',
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
    });

    const alternarActiva = (plantilla) => ejecutar(async () => {
        if (plantilla.activo) {
            const { isConfirmed } = await Swal.fire({
                icon: 'warning',
                titleText: `¿Desactivar "${plantilla.nombre}"?`,
                text: 'No se ofrecerá en expedientes nuevos. Los que ya la usan no cambian.',
                showCancelButton: true,
                confirmButtonText: 'Sí, desactivar',
                cancelButtonText: 'Cancelar',
            });
            if (!isConfirmed) return;
        }
        try {
            await axios.post(`/plantillas/${plantilla.id}/activar`, { activo: !plantilla.activo });
            await recargar();
        } catch (e) {
            avisarError('No se pudo cambiar el estado', e);
        }
    });

    return (
        <div className="pl-pagina">
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Catálogos</span>
                <h1>Plantillas de checklist</h1>
                <p>{DESCRIPCION_USO[uso]}</p>
            </header>

            <div className="pl-segmento" role="group" aria-label="Uso de las plantillas">
                {USOS.map(([valor, texto]) => (
                    <button key={valor} type="button" aria-pressed={uso === valor}
                        className={`pl-segmento-opcion${uso === valor ? ' activa' : ''}`} onClick={() => elegirUso(valor)}>
                        {texto}
                    </button>
                ))}
            </div>

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
                <button type="button" className="gp-boton gp-boton-primario" onClick={crear} disabled={ocupado}>
                    <FontAwesomeIcon icon={faPlus} /> Nueva plantilla
                </button>
            </div>

            {error && <p className="pl-error" role="alert">{error}</p>}
            {plantillas === null && !error && <p className="gp-vacio">Cargando plantillas…</p>}
            {plantillas !== null && visibles.length === 0 && (
                <p className="gp-vacio">No hay plantillas de {etiquetaUso(uso).toLowerCase()} que coincidan.</p>
            )}

            <ul className="pl-lista">
                {visibles.map((p) => (
                    <li key={p.id} className={`gp-panel pl-tarjeta${p.activo ? '' : ' inactiva'}`}>
                        <div className="pl-tarjeta-cuerpo">
                            <div className="pl-tarjeta-titulo">
                                <Link to={`${RUTA_PLANTILLAS}/${p.id}`}>{p.nombre}</Link>
                                {p.es_sgr && <span className="pl-insignia">SGR</span>}
                                {!esContratacion(p) && <span className="pl-insignia pl-insignia-suave">{p.motor === 'sgr' ? 'Evaluador SGR' : 'Simple'}</span>}
                                {!p.activo && <span className="pl-insignia pl-insignia-alerta">Inactiva</span>}
                            </div>
                            {p.descripcion && <p className="pl-tarjeta-descripcion">{p.descripcion}</p>}
                            <dl className="pl-datos">
                                <div><dt>Versión</dt><dd>{resumenVersiones(p)}</dd></div>
                                <div><dt>Requisitos</dt><dd>{p.vigente?.requisitos ?? p.borrador?.requisitos ?? 0}</dd></div>
                                <div><dt>Fuentes</dt><dd>{textoAsociaciones(p.todas_fuentes, p.fuentes, 'Todas')}</dd></div>
                                <div><dt>Sectores</dt><dd>{textoAsociaciones(p.todos_sectores, p.sectores, 'Todos')}</dd></div>
                                {esContratacion(p) && <div><dt>Modalidades</dt><dd>{textoModalidades(p)}</dd></div>}
                            </dl>
                        </div>
                        <div className="pl-tarjeta-acciones">
                            <button type="button" className="gp-boton" onClick={() => duplicar(p)} disabled={!p.vigente || ocupado}
                                title={p.vigente ? undefined : 'Publica una versión antes de duplicar'}>
                                <FontAwesomeIcon icon={faCopy} /> Duplicar
                            </button>
                            {!p.es_sgr && (
                                <button type="button" className="gp-boton" onClick={() => alternarActiva(p)} disabled={ocupado}>
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
