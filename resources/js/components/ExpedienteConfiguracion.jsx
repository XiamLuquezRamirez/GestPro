import React from 'react';
import { FASES, alternarId, alternarSector } from '../utils/expedienteChecklist';
import {
    AVISO_SIN_SECTOR, alternarPlantilla, avisosPlantilla, detallePlantilla, elegirPrincipal, incluyeSgr, opcionPlantilla,
} from '../utils/plantillasExpediente';

// Opciones del catálogo vigente más lo ya elegido que se haya desactivado después:
// un expediente debe poder seguir mostrando (y soltar) lo que ya tenía.
const conSeleccionadas = (opciones, seleccionadas) => [
    ...opciones,
    ...seleccionadas.filter((s) => !opciones.some((o) => o.id === s.id)),
];

const Casilla = ({ marcada, deshabilitada, onCambiar, children }) => (
    <label className="exv-opt">
        <input type="checkbox" checked={marcada} disabled={deshabilitada} onChange={onCambiar} />
        <span>{children}</span>
    </label>
);

/** Una plantilla disponible: casilla, opción de principal, datos y avisos. */
const OpcionPlantilla = ({ plantilla, plantillasExpediente, opcion, esPrincipal, varias, deshabilitada, onAlternar, onPrincipal }) => {
    const detalle = detallePlantilla(plantilla, plantillasExpediente);
    const avisos = avisosPlantilla(plantilla);
    const idDetalle = `exv-plantilla-${plantilla.id}-detalle`;

    return (
        <div className={`exv-plantilla${opcion.marcada ? ' marcada' : ''}`}>
            <label className="exv-opt">
                <input
                    type="checkbox"
                    checked={opcion.marcada}
                    disabled={deshabilitada || opcion.deshabilitada}
                    aria-describedby={idDetalle}
                    onChange={onAlternar}
                />
                <span>
                    <b className="exv-plantilla-nombre">{plantilla.nombre}</b>
                    {avisos.map((aviso) => <span key={aviso} className="exv-plantilla-aviso">{aviso}</span>)}
                </span>
            </label>
            <div id={idDetalle} className="exv-plantilla-detalle">
                {plantilla.descripcion && <p>{plantilla.descripcion}</p>}
                <dl>
                    <div><dt>Fuentes</dt><dd>{detalle.fuentes}</dd></div>
                    <div><dt>Sectores</dt><dd>{detalle.sectores}</dd></div>
                    <div><dt>Requisitos</dt><dd>{detalle.requisitos} · {detalle.version}</dd></div>
                </dl>
                {opcion.motivo && <p className="exv-plantilla-motivo">{opcion.motivo}</p>}
            </div>
            {opcion.marcada && (varias ? (
                <label className="exv-plantilla-principal">
                    <input
                        type="radio"
                        name="exv-plantilla-principal"
                        checked={esPrincipal}
                        disabled={deshabilitada}
                        aria-label={`Usar ${plantilla.nombre} como plantilla principal`}
                        onChange={onPrincipal}
                    />
                    Principal
                </label>
            ) : <span className="exv-plantilla-principal">Principal</span>)}
        </div>
    );
};

/** Primer bloque del panel: las plantillas que se ofrecen al proyecto y las que ya tiene el expediente. */
const BloquePlantillas = ({ disponibles, errorPlantillas, onReintentar, expediente, seleccion, deshabilitada, onCambiar }) => {
    if (errorPlantillas) {
        return (
            <fieldset className="exv-plantillas">
                <legend>Plantillas</legend>
                <p className="exv-config-aviso" role="alert">No se pudieron cargar las plantillas. {errorPlantillas}</p>
                {onReintentar && <button type="button" className="exv-mini" onClick={onReintentar}>Reintentar</button>}
            </fieldset>
        );
    }
    if (!disponibles) {
        return (
            <fieldset className="exv-plantillas">
                <legend>Plantillas</legend>
                <p className="exv-config-nota">Cargando plantillas…</p>
            </fieldset>
        );
    }

    const lista = disponibles.plantillas;
    const varias = seleccion.plantilla_ids.length > 1;

    return (
        <fieldset className="exv-plantillas">
            <legend>Plantillas <small>(definen el checklist)</small></legend>
            {disponibles.sin_sector && <p className="exv-config-aviso">{AVISO_SIN_SECTOR}.</p>}
            {lista.length === 0 ? (
                <p className="exv-config-nota">
                    Ninguna plantilla se ofrece a este proyecto. Revisa sus fuentes de financiación y su sector, o pide a un Administrador que asocie una plantilla.
                </p>
            ) : lista.map((p) => (
                <OpcionPlantilla
                    key={p.id}
                    plantilla={p}
                    plantillasExpediente={expediente.plantillas}
                    opcion={opcionPlantilla(p, seleccion, expediente.plantillas, lista, { configurado: expediente.configurado })}
                    esPrincipal={seleccion.principal_id === p.id}
                    varias={varias}
                    deshabilitada={deshabilitada}
                    onAlternar={() => onCambiar(alternarPlantilla(seleccion, p.id))}
                    onPrincipal={() => onCambiar(elegirPrincipal(seleccion, p.id))}
                />
            ))}
        </fieldset>
    );
};

const ExpedienteConfiguracion = ({
    catalogo, expediente, seleccion, deshabilitada, recalculando, onCambiar, disponibles, errorPlantillas, onReintentarPlantillas,
}) => {
    const { configuracion, progreso } = expediente;
    const sectores = conSeleccionadas(catalogo.sectores, configuracion.sectores);
    const componentes = conSeleccionadas(catalogo.componentes, configuracion.componentes);
    const tipologias = conSeleccionadas(catalogo.tipologias, configuracion.tipologias);
    const sectoresMarcados = sectores.filter((s) => seleccion.sector_ids.includes(s.id));
    // Con la lista y con las del expediente: si la lista no cargó, el panel SGR igual sigue a la selección.
    const conSgr = incluyeSgr(seleccion.plantilla_ids, [...(disponibles?.plantillas ?? []), ...expediente.plantillas]);

    const tiposDe = (sector) => conSeleccionadas(
        sector.tipos ?? [],
        configuracion.tipos.filter((t) => t.sector_id === sector.id)
    );

    return (
        <aside className="exv-config" aria-label="Configuración del expediente">
            <div className="exv-config-head">
                <h2>Configuración</h2>
                <span>{recalculando ? 'Recalculando…' : `${progreso.total} ${progreso.total === 1 ? 'requisito' : 'requisitos'}`}</span>
            </div>

            <BloquePlantillas
                disponibles={disponibles}
                errorPlantillas={errorPlantillas}
                onReintentar={onReintentarPlantillas}
                expediente={expediente}
                seleccion={seleccion}
                deshabilitada={deshabilitada}
                onCambiar={onCambiar}
            />

            {conSgr && (
                <div className="exv-panel-sgr" role="group" aria-labelledby="exv-panel-sgr-titulo">
                    <h3 id="exv-panel-sgr-titulo" className="exv-panel-sgr-titulo">Configuración SGR</h3>
                    <fieldset>
                        <legend>Fase del proyecto</legend>
                        <div className="exv-seg">
                            {FASES.map((fase) => (
                                <label key={fase.valor} className={seleccion.fase_sgr === fase.valor ? 'activa' : ''}>
                                    <input
                                        type="radio"
                                        name="fase_sgr"
                                        value={fase.valor}
                                        checked={seleccion.fase_sgr === fase.valor}
                                        disabled={deshabilitada}
                                        onChange={() => onCambiar({ ...seleccion, fase_sgr: fase.valor })}
                                    />
                                    {fase.romano}
                                    <span className="exv-seg-tag">{fase.corta}</span>
                                </label>
                            ))}
                        </div>
                    </fieldset>
        
                    <fieldset>
                        <legend>Componentes del proyecto</legend>
                        {componentes.map((c) => (
                            <Casilla
                                key={c.id}
                                marcada={seleccion.componente_ids.includes(c.id)}
                                deshabilitada={deshabilitada}
                                onCambiar={() => onCambiar({ ...seleccion, componente_ids: alternarId(seleccion.componente_ids, c.id) })}
                            >
                                {c.nombre}
                            </Casilla>
                        ))}
                    </fieldset>
        
                    <fieldset>
                        <legend>Sector de inversión <small>(uno o varios)</small></legend>
                        {sectores.map((s) => (
                            <Casilla
                                key={s.id}
                                marcada={seleccion.sector_ids.includes(s.id)}
                                deshabilitada={deshabilitada}
                                onCambiar={() => onCambiar(alternarSector(seleccion, s.id, catalogo))}
                            >
                                {s.nombre}
                                {s.fuente && <span className="exv-opt-sub">Acuerdo 12/2024</span>}
                            </Casilla>
                        ))}
                    </fieldset>
        
                    {sectoresMarcados.length > 0 && (
                        <fieldset className="exv-tipos">
                            <legend>Tipo de proyecto <small>(marca los que apliquen)</small></legend>
                            {sectoresMarcados.map((sector) => (
                                <div key={sector.id} className="exv-tipo-grupo">
                                    <div className="exv-tipo-titulo">{sector.nombre}</div>
                                    {tiposDe(sector).map((t) => (
                                        <Casilla
                                            key={t.id}
                                            marcada={seleccion.tipo_ids.includes(t.id)}
                                            deshabilitada={deshabilitada}
                                            onCambiar={() => onCambiar({ ...seleccion, tipo_ids: alternarId(seleccion.tipo_ids, t.id) })}
                                        >
                                            {t.nombre}
                                        </Casilla>
                                    ))}
                                </div>
                            ))}
                        </fieldset>
                    )}
        
                    <fieldset>
                        <legend>Tipologías y fuentes especiales</legend>
                        {tipologias.map((t) => (
                            <Casilla
                                key={t.id}
                                marcada={seleccion.tipologia_ids.includes(t.id)}
                                deshabilitada={deshabilitada}
                                onCambiar={() => onCambiar({ ...seleccion, tipologia_ids: alternarId(seleccion.tipologia_ids, t.id) })}
                            >
                                {t.nombre}
                            </Casilla>
                        ))}
                    </fieldset>
                </div>
            )}
        </aside>
    );
};

export default ExpedienteConfiguracion;
