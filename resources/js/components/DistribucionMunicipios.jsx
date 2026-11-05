import React, { useMemo, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faArrowLeft, faArrowRight, faChartColumn } from '@fortawesome/free-solid-svg-icons';
import { calcularAvanceGrupo } from '../utils/kpisDashboard';
import { filasEnSala, formatearMillones } from '../utils/tablero';

const FILAS_INICIALES = 5;

const DistribucionMunicipios = ({ proyectos, onMunicipioClick, municipioSeleccionado, onVolver, onProyectoClick, compacto = false }) => {
    const [verTodos, setVerTodos] = useState(false);

    const filas = useMemo(() => {
        const porMunicipio = proyectos.reduce((acc, proyecto) => {
            const municipio = proyecto.descripcion_municipio || 'Sin municipio';
            if (!acc[municipio]) {
                acc[municipio] = [];
            }
            acc[municipio].push(proyecto);
            return acc;
        }, {});

        return Object.entries(porMunicipio)
            .map(([municipio, proyectosDelMunicipio]) => {
                const total = proyectosDelMunicipio.length;
                const presupuestoTotal = proyectosDelMunicipio.reduce((sum, p) => sum + (parseFloat(p.presupuesto) || 0), 0);
                // Ejecución financiera real; null cuando el municipio no tiene contratos.
                const avance = calcularAvanceGrupo(proyectosDelMunicipio);
                const enRiesgo = proyectosDelMunicipio.filter((p) => p.descripcion_estado === 'Con retraso').length;

                return { municipio, total, presupuestoTotal, avance, enRiesgo };
            })
            // Los que concentran más proyectos primero: son los que importan si no caben todos.
            .sort((a, b) => b.total - a.total || b.presupuestoTotal - a.presupuestoTotal);
    }, [proyectos]);

    const proyectosDelMunicipioSeleccionado = useMemo(() => {
        if (!municipioSeleccionado) return null;
        return proyectos.filter((p) => (p.descripcion_municipio || 'Sin municipio') === municipioSeleccionado);
    }, [proyectos, municipioSeleccionado]);

    if (municipioSeleccionado && proyectosDelMunicipioSeleccionado) {
        return (
            <section className="tb-tarjeta tb-distribucion gp-panel">
                <div className="tb-municipio-cabecera">
                    <button type="button" className="gp-boton" onClick={onVolver}>
                        <FontAwesomeIcon icon={faArrowLeft} /> Volver
                    </button>
                    <h3>{municipioSeleccionado}</h3>
                    <span className="gp-etiqueta">{proyectosDelMunicipioSeleccionado.length} proyectos</span>
                </div>
                {proyectosDelMunicipioSeleccionado.length === 0 ? (
                    <p className="gp-vacio">No hay proyectos en este municipio.</p>
                ) : (
                    <ul className="tb-lista-simple clicable">
                        {proyectosDelMunicipioSeleccionado.map((proyecto) => (
                            <li key={proyecto.id}>
                                <button type="button" onClick={() => onProyectoClick(proyecto)}>
                                    <span className="tb-lista-texto"><b>{proyecto.nombre}</b><span>{proyecto.descripcion_estado}</span></span>
                                    <strong>{formatearMillones(parseFloat(proyecto.presupuesto))}</strong>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        );
    }

    const limite = compacto ? filasEnSala(window.innerHeight, window.innerWidth) : FILAS_INICIALES;
    const visibles = verTodos ? filas : filas.slice(0, limite);
    const ocultas = filas.length - visibles.length;

    return (
        <section className="tb-tarjeta tb-distribucion gp-panel">
            <h3 className="tb-titulo-icono">
                <FontAwesomeIcon icon={faChartColumn} /> Distribución por municipio
                {compacto && ocultas > 0 && <span className="tb-tenue tb-titulo-nota"> · {visibles.length} de {filas.length}</span>}
            </h3>
            {filas.length === 0 ? (
                <p className="gp-vacio">No hay proyectos en esta fase todavía.</p>
            ) : (
                <>
                    <table className="tb-tabla tb-tabla-cartas">
                        <thead>
                            <tr>
                                <th>Municipio</th>
                                <th className="numero"><span className="tb-largo">Proyectos</span><span className="tb-corto">Proy.</span></th>
                                <th className="numero"><span className="tb-largo">Presupuesto</span><span className="tb-corto">Presup.</span></th>
                                <th><span className="tb-largo">Ejecución financiera</span><span className="tb-corto">Ejecución</span></th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {visibles.map((fila) => {
                                const esClicable = fila.municipio !== 'Sin municipio';
                                return (
                                    <tr
                                        key={fila.municipio}
                                        className={esClicable ? 'clicable' : ''}
                                        onClick={esClicable ? () => onMunicipioClick(fila.municipio) : undefined}
                                    >
                                        <td data-etiqueta="Municipio">
                                            {esClicable ? <button type="button" className="tb-fila-enlace">{fila.municipio}</button> : fila.municipio}
                                        </td>
                                        <td data-etiqueta="Proyectos" className="numero">{fila.total}</td>
                                        <td data-etiqueta="Presupuesto" className="numero">{formatearMillones(fila.presupuestoTotal)}</td>
                                        <td data-etiqueta="Ejecución">
                                            {fila.avance.tieneContratos ? (
                                                <span className="tb-avance">
                                                    <span className="tb-pista"><span style={{ width: `${Math.min(fila.avance.pct, 100)}%` }} /></span>
                                                    <b>{fila.avance.pct}%</b>
                                                </span>
                                            ) : (
                                                <span className="tb-tenue">Sin contratos</span>
                                            )}
                                        </td>
                                        <td data-etiqueta="Estado">
                                            <span className={`tb-pastilla ${fila.enRiesgo > 0 ? 'riesgo' : 'bien'}`}>
                                                {fila.enRiesgo > 0 ? `${fila.enRiesgo} en riesgo` : 'Sin alertas'}
                                            </span>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                    {!compacto && filas.length > FILAS_INICIALES && (
                        <button type="button" className="tb-ver-mas" onClick={() => setVerTodos((v) => !v)}>
                            {verTodos ? 'Ver menos' : `Ver todos los municipios (${filas.length})`} <FontAwesomeIcon icon={faArrowRight} />
                        </button>
                    )}
                </>
            )}
        </section>
    );
};

export default DistribucionMunicipios;
