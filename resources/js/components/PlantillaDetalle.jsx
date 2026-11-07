import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { faArrowLeft, faFloppyDisk } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
import axios from '../axios';
import { alternarId } from '../utils/expedienteChecklist';
import { avisarError } from '../utils/avisosPlantillas';
import { ALCANCES, datosPlantilla, formularioPlantilla, mensajeApi, opcionesCatalogo, resumenVersiones, versionDeTrabajo } from '../utils/plantillasChecklist';
import PlantillaRequisitos from './PlantillaRequisitos';
import PlantillaVistaPrevia from './PlantillaVistaPrevia';
import PlantillaVersiones from './PlantillaVersiones';
import { RUTA_PLANTILLAS } from '../utils/navegacion';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';

const PESTANAS = [
    ['general', 'General y asociaciones'],
    ['requisitos', 'Requisitos'],
    ['vista', 'Vista previa'],
    ['versiones', 'Versiones'],
];

const NOTA_SGR = 'La plantilla SGR aplica a todos los sectores y siempre incluye la fuente SGR.';

const ListaChequeo = ({ titulo, todas, textoTodas, onTodas, bloquearTodas, items, elegidos, onAlternar, sufijoInactivo, fijos = [], nota }) => (
    <fieldset className="pl-asociacion">
        <legend>{titulo}</legend>
        <label className="pl-check">
            <input type="checkbox" checked={todas} disabled={bloquearTodas} onChange={(e) => onTodas(e.target.checked)} /> {textoTodas}
        </label>
        {nota && <p className="pl-ayuda">{nota}</p>}
        {!todas && (
            <div className="pl-opciones">
                {opcionesCatalogo(items, elegidos).map((item) => (
                    <label key={item.id} className="pl-check">
                        <input type="checkbox" checked={elegidos.includes(item.id)} disabled={fijos.includes(item.id)} onChange={() => onAlternar(item.id)} />
                        {item.nombre}{!item.activo && ` (${sufijoInactivo})`}
                    </label>
                ))}
            </div>
        )}
    </fieldset>
);

/** Detalle de una plantilla: datos, asociaciones, requisitos, vista previa y versiones. */
const PlantillaDetalle = () => {
    const { plantillaId } = useParams();
    const [plantilla, setPlantilla] = useState(null);
    const [formulario, setFormulario] = useState(null);
    const [fuentes, setFuentes] = useState([]);
    const [sectores, setSectores] = useState([]);
    const [requisitos, setRequisitos] = useState([]);
    const [pestana, setPestana] = useState('general');
    const [guardando, guardarUnaVez] = useOcupado();
    const [error, setError] = useState(null);
    const peticion = useRef(0);
    const cargada = useRef(false);

    const version = versionDeTrabajo(plantilla);

    // `reiniciar` vuelve a llenar el formulario de General; las recargas por requisitos o versiones lo respetan.
    // Solo vale la última petición, y solo el fallo de la carga inicial reemplaza la página.
    const cargar = useCallback(async ({ reiniciar = false } = {}) => {
        const numero = ++peticion.current;
        const fallo = (e, mensaje) => {
            if (numero !== peticion.current) return;
            if (cargada.current) avisarError('No se pudo recargar la plantilla', e);
            else setError(mensaje);
        };

        let datos;
        try {
            datos = (await axios.get(`/plantillas/${plantillaId}`)).data;
        } catch (e) {
            fallo(e, e?.response?.status === 404 ? 'La plantilla no existe.' : mensajeApi(e));
            return;
        }

        let lista = [];
        const trabajo = versionDeTrabajo(datos);
        try {
            if (trabajo) lista = (await axios.get(`/catalogo-requisitos/versiones/${trabajo.id}/requisitos`)).data;
        } catch (e) {
            fallo(e, mensajeApi(e));
            return;
        }

        if (numero !== peticion.current) return;
        cargada.current = true;
        setPlantilla(datos);
        setRequisitos(lista);
        if (reiniciar) setFormulario(formularioPlantilla(datos));
        setError(null);
    }, [plantillaId]);

    useEffect(() => {
        cargada.current = false;
        setPlantilla(null);
        setFormulario(null);
        setRequisitos([]);
        setError(null);
        setPestana('general');
        cargar({ reiniciar: true });
    }, [cargar]);

    useEffect(() => {
        let vigente = true;
        Promise.all([axios.get('/fuentesFinanciacion'), axios.get('/sectores')])
            .then(([f, s]) => { if (vigente) { setFuentes(f.data); setSectores(s.data); } })
            .catch((e) => { if (vigente) avisarError('No se pudieron cargar las fuentes y los sectores', e); });
        return () => { vigente = false; };
    }, []);

    const cambiar = (campo, valor) => setFormulario((f) => ({ ...f, [campo]: valor }));

    const guardar = (e) => {
        e.preventDefault();
        return guardarUnaVez(async () => {
            try {
                await axios.put(`/plantillas/${plantilla.id}`, datosPlantilla(formulario));
            } catch (err) {
                avisarError('No se pudo guardar la plantilla', err);
                return;
            }
            await cargar({ reiniciar: true });
            Swal.fire({ icon: 'success', titleText: 'Plantilla guardada', timer: 1400, showConfirmButton: false });
        });
    };

    if (error) {
        return (
            <div className="pl-pagina">
                <p className="pl-error" role="alert">{error}</p>
                <Link to={RUTA_PLANTILLAS} className="gp-boton"><FontAwesomeIcon icon={faArrowLeft} /> Volver a plantillas</Link>
            </div>
        );
    }
    if (!plantilla || !formulario) return <p className="gp-vacio">Cargando plantilla…</p>;

    return (
        <div className="pl-pagina">
            <Link to={RUTA_PLANTILLAS} className="pl-volver"><FontAwesomeIcon icon={faArrowLeft} /> Plantillas</Link>
            <header className="gp-cabecera">
                <span className="gp-etiqueta">
                    {plantilla.motor === 'sgr' ? 'Evaluador SGR' : 'Plantilla simple'} · {resumenVersiones(plantilla)}
                </span>
                <h1>{plantilla.nombre}</h1>
                {!plantilla.activo && <p>Inactiva: no se ofrece en expedientes nuevos.</p>}
            </header>

            <div className="pl-pestanas" role="tablist">
                {PESTANAS.map(([id, titulo]) => (
                    <button key={id} type="button" role="tab" aria-selected={pestana === id}
                        className={`pl-pestana${pestana === id ? ' activa' : ''}`} onClick={() => setPestana(id)}>
                        {titulo}
                    </button>
                ))}
            </div>

            {pestana === 'general' && (
                <form className="gp-panel pl-formulario" onSubmit={guardar}>
                    <label className="pl-campo">
                        <span>Nombre</span>
                        <input value={formulario.nombre} maxLength={199} required onChange={(e) => cambiar('nombre', e.target.value)} />
                    </label>
                    <label className="pl-campo">
                        <span>Descripción</span>
                        <textarea rows={3} value={formulario.descripcion} onChange={(e) => cambiar('descripcion', e.target.value)} />
                    </label>
                    <label className="pl-campo">
                        <span>Alcance</span>
                        <select value={formulario.alcance} onChange={(e) => cambiar('alcance', e.target.value)}>
                            <option value="">Sin definir</option>
                            {ALCANCES.map(([valor, texto]) => <option key={valor} value={valor}>{texto}</option>)}
                        </select>
                    </label>
                    <ListaChequeo
                        titulo="Fuentes de financiación"
                        sufijoInactivo="inactiva"
                        fijos={plantilla.es_sgr ? fuentes.filter((f) => f.clave === 'sgr').map((f) => f.id) : []}
                        todas={formulario.todas_fuentes}
                        textoTodas="Todas las fuentes"
                        onTodas={(v) => cambiar('todas_fuentes', v)}
                        items={fuentes}
                        elegidos={formulario.fuente_ids}
                        onAlternar={(id) => cambiar('fuente_ids', alternarId(formulario.fuente_ids, id))}
                    />
                    <ListaChequeo
                        titulo="Sectores"
                        sufijoInactivo="inactivo"
                        nota={plantilla.es_sgr ? NOTA_SGR : undefined}
                        bloquearTodas={plantilla.es_sgr}
                        todas={plantilla.es_sgr || formulario.todos_sectores}
                        textoTodas="Todos los sectores"
                        onTodas={(v) => cambiar('todos_sectores', v)}
                        items={sectores}
                        elegidos={formulario.sector_ids}
                        onAlternar={(id) => cambiar('sector_ids', alternarId(formulario.sector_ids, id))}
                    />
                    <div className="pl-formulario-acciones">
                        <button type="submit" className="gp-boton gp-boton-primario" disabled={guardando}>
                            <FontAwesomeIcon icon={faFloppyDisk} /> Guardar
                        </button>
                    </div>
                </form>
            )}

            {pestana === 'requisitos' && (
                <PlantillaRequisitos plantilla={plantilla} version={version} requisitos={requisitos} onCambio={() => cargar()} />
            )}

            {pestana === 'vista' && <PlantillaVistaPrevia plantilla={plantilla} version={version} requisitos={requisitos} />}

            {pestana === 'versiones' && <PlantillaVersiones plantilla={plantilla} onCambio={() => cargar()} />}
        </div>
    );
};

export default PlantillaDetalle;
