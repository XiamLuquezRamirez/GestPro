import React from 'react';
import { FASES, alternarId, alternarSector } from '../utils/expedienteChecklist';

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

const ExpedienteConfiguracion = ({ catalogo, expediente, seleccion, deshabilitada, recalculando, onCambiar }) => {
    const { configuracion, progreso } = expediente;
    const sectores = conSeleccionadas(catalogo.sectores, configuracion.sectores);
    const componentes = conSeleccionadas(catalogo.componentes, configuracion.componentes);
    const tipologias = conSeleccionadas(catalogo.tipologias, configuracion.tipologias);
    const sectoresMarcados = sectores.filter((s) => seleccion.sector_ids.includes(s.id));

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
        </aside>
    );
};

export default ExpedienteConfiguracion;
