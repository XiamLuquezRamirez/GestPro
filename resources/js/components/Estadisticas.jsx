import React, { useState } from 'react';
import {
    ResponsiveContainer, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
    PieChart, Pie, Cell,
} from 'recharts';
import { calcularCadenaPresupuestal } from '../utils/cadenaPresupuestal';
import { calcularAvanceGrupo } from '../utils/kpisDashboard';
import { formatearMillones } from '../utils/tablero';
import { COLORES_GRAFICA, EJE, LEYENDA, PALETA_CATEGORIAS, REJILLA, TOOLTIP, anchoEjeCategorias } from '../utils/temaGraficas';

const MESES_CORTOS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

const FILTRO_INICIAL = {
    municipio: '',
    fase: '',
    estado: '',
    fechaDesde: '',
    fechaHasta: '',
};

const SinDatos = ({ children = 'Sin datos para el filtro actual.' }) => <p className="gp-vacio">{children}</p>;

const BarraCadena = ({ titulo, valor, detalle, ancho, clase }) => (
    <div className={`tb-cadena-fila ${clase}`}>
        <div className="tb-cadena-texto">
            <span>{titulo}</span>
            <strong>{formatearMillones(valor)}{detalle && <small> {detalle}</small>}</strong>
        </div>
        <div className="tb-cadena-pista"><span style={{ width: ancho }} /></div>
    </div>
);

const Estadisticas = ({ proyectos }) => {
    const [filtro, setFiltro] = useState(FILTRO_INICIAL);

    const municipiosUnicos = [...new Set(proyectos.map((p) => p.descripcion_municipio).filter(Boolean))].sort();
    const fasesUnicas = [...new Set(proyectos.map((p) => p.descripcion_fase).filter(Boolean))].sort();
    const estadosUnicos = [...new Set(proyectos.map((p) => p.descripcion_estado).filter(Boolean))].sort();

    const proyectosFiltrados = proyectos.filter((p) => {
        if (filtro.municipio && p.descripcion_municipio !== filtro.municipio) return false;
        if (filtro.fase && p.descripcion_fase !== filtro.fase) return false;
        if (filtro.estado && p.descripcion_estado !== filtro.estado) return false;
        if (filtro.fechaDesde && (!p.fecha_inicio || p.fecha_inicio < filtro.fechaDesde)) return false;
        if (filtro.fechaHasta && (!p.fecha_inicio || p.fecha_inicio > filtro.fechaHasta)) return false;
        return true;
    });

    const cadena = calcularCadenaPresupuestal(proyectosFiltrados);
    // La barra más larga define la escala visual; las otras dos se leen en proporción.
    const maxCadena = Math.max(cadena.planeado, cadena.contratado, cadena.ejecutado);
    const anchoBarra = (valor) => (maxCadena > 0 ? `${(valor / maxCadena) * 100}%` : '0%');
    const hayFiltroActivo = Boolean(filtro.municipio || filtro.fase || filtro.estado || filtro.fechaDesde || filtro.fechaHasta);

    const colorPorMunicipio = {};
    municipiosUnicos.forEach((m, i) => {
        colorPorMunicipio[m] = PALETA_CATEGORIAS[i % PALETA_CATEGORIAS.length];
    });

    const dataPorEstado = Object.values(
        proyectosFiltrados.reduce((acc, p) => {
            const estado = p.descripcion_estado || 'Sin estado';
            if (!acc[estado]) {
                acc[estado] = { estado, cantidad: 0, color: p.color_estado || COLORES_GRAFICA.tenue };
            }
            acc[estado].cantidad++;
            return acc;
        }, {})
    );

    const dataPorFase = Object.values(
        proyectosFiltrados.reduce((acc, p) => {
            const fase = p.descripcion_fase || 'Sin fase';
            if (!acc[fase]) {
                acc[fase] = { fase, cantidad: 0, color: p.color_fase || COLORES_GRAFICA.tenue };
            }
            acc[fase].cantidad++;
            return acc;
        }, {})
    );

    const dataPorMunicipio = Object.values(
        proyectosFiltrados.reduce((acc, p) => {
            const municipio = p.descripcion_municipio || 'Sin municipio';
            if (!acc[municipio]) {
                acc[municipio] = { municipio, cantidad: 0 };
            }
            acc[municipio].cantidad++;
            return acc;
        }, {})
    ).sort((a, b) => b.cantidad - a.cantidad);

    const dataPresupuestoPorMunicipio = Object.values(
        proyectosFiltrados.reduce((acc, p) => {
            const municipio = p.descripcion_municipio || 'Sin municipio';
            if (!acc[municipio]) {
                acc[municipio] = { municipio, presupuesto: 0 };
            }
            acc[municipio].presupuesto += parseFloat(p.presupuesto) || 0;
            return acc;
        }, {})
    ).sort((a, b) => b.presupuesto - a.presupuesto);

    // Ejecución financiera por fase. Las fases sin contratos no se grafican:
    // una barra en 0 se leería como "no avanza" cuando en realidad todavía no
    // hay nada que medir (Formulación y Licitación son previas a la contratación).
    const proyectosPorFase = proyectosFiltrados.reduce((acc, p) => {
        const fase = p.descripcion_fase || 'Sin fase';
        if (!acc[fase]) {
            acc[fase] = { fase, color: p.color_fase || COLORES_GRAFICA.tenue, proyectos: [] };
        }
        acc[fase].proyectos.push(p);
        return acc;
    }, {});

    const fasesConAvance = Object.values(proyectosPorFase).map((item) => ({
        ...item,
        avance: calcularAvanceGrupo(item.proyectos),
    }));

    const dataAvancePorFase = fasesConAvance
        .filter((item) => item.avance.tieneContratos)
        .map((item) => ({ fase: item.fase, color: item.color, ejecucion: item.avance.pct }));

    const fasesSinContratos = fasesConAvance
        .filter((item) => !item.avance.tieneContratos)
        .map((item) => item.fase);

    const dataPorMes = Object.values(
        proyectosFiltrados
            .filter((p) => p.fecha_inicio)
            .reduce((acc, p) => {
                const [anio, mes] = p.fecha_inicio.split('-');
                const clave = `${anio}-${mes}`;
                if (!acc[clave]) {
                    acc[clave] = {
                        clave,
                        etiqueta: `${MESES_CORTOS[parseInt(mes, 10) - 1]} ${anio}`,
                        cantidad: 0,
                    };
                }
                acc[clave].cantidad++;
                return acc;
            }, {})
    ).sort((a, b) => a.clave.localeCompare(b.clave));

    const handleFiltroChange = (campo) => (e) => {
        setFiltro((prev) => ({ ...prev, [campo]: e.target.value }));
    };

    const limpiarFiltros = () => setFiltro(FILTRO_INICIAL);

    return (
        <div className="tb-estadisticas">
            <div className="tb-filtros gp-panel">
                <label>
                    <span className="gp-etiqueta">Municipio</span>
                    <select value={filtro.municipio} onChange={handleFiltroChange('municipio')}>
                        <option value="">Todos</option>
                        {municipiosUnicos.map((m) => <option key={m} value={m}>{m}</option>)}
                    </select>
                </label>
                <label>
                    <span className="gp-etiqueta">Fase</span>
                    <select value={filtro.fase} onChange={handleFiltroChange('fase')}>
                        <option value="">Todas</option>
                        {fasesUnicas.map((f) => <option key={f} value={f}>{f}</option>)}
                    </select>
                </label>
                <label>
                    <span className="gp-etiqueta">Estado</span>
                    <select value={filtro.estado} onChange={handleFiltroChange('estado')}>
                        <option value="">Todos</option>
                        {estadosUnicos.map((e) => <option key={e} value={e}>{e}</option>)}
                    </select>
                </label>
                <label>
                    <span className="gp-etiqueta">Desde</span>
                    <input type="date" value={filtro.fechaDesde} onChange={handleFiltroChange('fechaDesde')} />
                </label>
                <label>
                    <span className="gp-etiqueta">Hasta</span>
                    <input type="date" value={filtro.fechaHasta} onChange={handleFiltroChange('fechaHasta')} />
                </label>
                <button type="button" className="gp-boton" onClick={limpiarFiltros} disabled={!hayFiltroActivo}>Limpiar filtros</button>
            </div>

            <section className="tb-tarjeta gp-panel">
                <h3>
                    Cadena presupuestal
                    {hayFiltroActivo && <span className="tb-tenue"> · {proyectosFiltrados.length} proyecto(s) según los filtros</span>}
                </h3>

                {proyectosFiltrados.length === 0 ? (
                    <SinDatos>Sin proyectos que coincidan con los filtros.</SinDatos>
                ) : (
                    <>
                        <BarraCadena titulo="Planeado" valor={cadena.planeado} ancho={anchoBarra(cadena.planeado)} clase="planeado" />
                        <BarraCadena
                            titulo="Contratado"
                            valor={cadena.contratado}
                            detalle={cadena.pctContratado !== null ? `(${cadena.pctContratado}% de lo planeado)` : null}
                            ancho={anchoBarra(cadena.contratado)}
                            clase="contratado"
                        />
                        <BarraCadena
                            titulo="Ejecutado (facturado)"
                            valor={cadena.ejecutado}
                            detalle={cadena.pctEjecutado !== null ? `(${cadena.pctEjecutado}% de lo contratado)` : null}
                            ancho={anchoBarra(cadena.ejecutado)}
                            clase="ejecutado"
                        />
                        {cadena.excedente > 0 && (
                            <p className="tb-aviso-linea">
                                Lo contratado supera lo planeado en <strong>{formatearMillones(cadena.excedente)}</strong>.
                            </p>
                        )}
                    </>
                )}
            </section>

            <div className="tb-graficas">
                <section className="tb-tarjeta gp-panel">
                    <h3>Proyectos por estado</h3>
                    {dataPorEstado.length === 0 ? <SinDatos /> : (
                        <ResponsiveContainer width="100%" height={Math.max(200, dataPorEstado.length * 45)}>
                            <BarChart data={dataPorEstado} layout="vertical" margin={{ left: 8, right: 16 }}>
                                <CartesianGrid {...REJILLA} horizontal={false} />
                                <XAxis {...EJE} type="number" allowDecimals={false} />
                                <YAxis {...EJE} type="category" dataKey="estado" width={anchoEjeCategorias(140)} />
                                <Tooltip {...TOOLTIP} formatter={(value) => [`${value} proyectos (${Math.round((value / proyectosFiltrados.length) * 100)}%)`, '']} />
                                <Bar dataKey="cantidad" radius={[0, 4, 4, 0]}>
                                    {dataPorEstado.map((entry) => <Cell key={entry.estado} fill={entry.color} />)}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </section>

                <section className="tb-tarjeta gp-panel">
                    <h3>Proyectos por fase</h3>
                    {dataPorFase.length === 0 ? <SinDatos /> : (
                        <ResponsiveContainer width="100%" height={260}>
                            <PieChart>
                                <Pie data={dataPorFase} dataKey="cantidad" nameKey="fase" innerRadius={60} outerRadius={90} paddingAngle={2} stroke="none">
                                    {dataPorFase.map((entry) => <Cell key={entry.fase} fill={entry.color} />)}
                                </Pie>
                                <Tooltip {...TOOLTIP} formatter={(value, name) => [`${value} proyectos (${Math.round((value / proyectosFiltrados.length) * 100)}%)`, name]} />
                                <Legend
                                    {...LEYENDA}
                                    formatter={(value) => {
                                        const entry = dataPorFase.find((d) => d.fase === value);
                                        const pct = Math.round((entry.cantidad / proyectosFiltrados.length) * 100);
                                        return `${value}: ${entry.cantidad} (${pct}%)`;
                                    }}
                                />
                            </PieChart>
                        </ResponsiveContainer>
                    )}
                </section>

                <section className="tb-tarjeta gp-panel">
                    <h3>Proyectos por municipio</h3>
                    {dataPorMunicipio.length === 0 ? <SinDatos /> : (
                        <ResponsiveContainer width="100%" height={Math.max(200, dataPorMunicipio.length * 45)}>
                            <BarChart data={dataPorMunicipio} layout="vertical" margin={{ left: 8, right: 16 }}>
                                <CartesianGrid {...REJILLA} horizontal={false} />
                                <XAxis {...EJE} type="number" allowDecimals={false} />
                                <YAxis {...EJE} type="category" dataKey="municipio" width={anchoEjeCategorias(120)} />
                                <Tooltip {...TOOLTIP} formatter={(value) => [`${value} proyectos`, '']} />
                                <Bar dataKey="cantidad" radius={[0, 4, 4, 0]}>
                                    {dataPorMunicipio.map((entry) => <Cell key={entry.municipio} fill={colorPorMunicipio[entry.municipio] || COLORES_GRAFICA.tenue} />)}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </section>

                <section className="tb-tarjeta gp-panel">
                    <h3>Presupuesto por municipio</h3>
                    {dataPresupuestoPorMunicipio.length === 0 ? <SinDatos /> : (
                        <ResponsiveContainer width="100%" height={Math.max(200, dataPresupuestoPorMunicipio.length * 45)}>
                            <BarChart data={dataPresupuestoPorMunicipio} layout="vertical" margin={{ left: 8, right: 16 }}>
                                <CartesianGrid {...REJILLA} horizontal={false} />
                                <XAxis {...EJE} type="number" tickFormatter={(v) => formatearMillones(v)} />
                                <YAxis {...EJE} type="category" dataKey="municipio" width={anchoEjeCategorias(120)} />
                                <Tooltip {...TOOLTIP} formatter={(value) => [formatearMillones(value), 'Presupuesto']} />
                                <Bar dataKey="presupuesto" radius={[0, 4, 4, 0]}>
                                    {dataPresupuestoPorMunicipio.map((entry) => <Cell key={entry.municipio} fill={colorPorMunicipio[entry.municipio] || COLORES_GRAFICA.tenue} />)}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </section>

                <section className="tb-tarjeta gp-panel">
                    <h3>Ejecución financiera por fase</h3>
                    {dataAvancePorFase.length === 0 ? (
                        <SinDatos>Ninguna fase tiene contratos registrados para el filtro actual.</SinDatos>
                    ) : (
                        <ResponsiveContainer width="100%" height={220}>
                            <BarChart data={dataAvancePorFase}>
                                <CartesianGrid {...REJILLA} vertical={false} />
                                <XAxis {...EJE} dataKey="fase" />
                                <YAxis {...EJE} domain={[0, 100]} tickFormatter={(v) => `${v}%`} />
                                <Tooltip {...TOOLTIP} formatter={(value) => [`${value}%`, 'Ejecución financiera']} />
                                <Bar dataKey="ejecucion" radius={[4, 4, 0, 0]}>
                                    {dataAvancePorFase.map((entry) => <Cell key={entry.fase} fill={entry.color} />)}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                    {fasesSinContratos.length > 0 && (
                        <p className="tb-nota">Sin contratos registrados: {fasesSinContratos.join(', ')}</p>
                    )}
                </section>

                <section className="tb-tarjeta gp-panel">
                    <h3>Proyectos iniciados por mes</h3>
                    {dataPorMes.length === 0 ? <SinDatos /> : (
                        <ResponsiveContainer width="100%" height={220}>
                            <BarChart data={dataPorMes}>
                                <CartesianGrid {...REJILLA} vertical={false} />
                                <XAxis {...EJE} dataKey="etiqueta" />
                                <YAxis {...EJE} allowDecimals={false} />
                                <Tooltip {...TOOLTIP} formatter={(value) => [`${value} proyectos`, '']} />
                                <Bar dataKey="cantidad" fill={COLORES_GRAFICA.azul} radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </section>
            </div>

            <section className="tb-tarjeta gp-panel">
                <h3>Proyectos filtrados <span className="tb-contador">{proyectosFiltrados.length}</span></h3>
                {proyectosFiltrados.length === 0 ? (
                    <SinDatos>No hay proyectos que coincidan con los filtros seleccionados.</SinDatos>
                ) : (
                    <table className="tb-tabla tb-tabla-cartas">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Municipio</th>
                                <th>Fase</th>
                                <th>Estado</th>
                                <th className="numero">Presupuesto</th>
                                <th className="numero">Ejecución</th>
                            </tr>
                        </thead>
                        <tbody>
                            {proyectosFiltrados.map((p) => {
                                const avance = calcularAvanceGrupo([p]);
                                return (
                                    <tr key={p.id}>
                                        <td data-etiqueta="Nombre">{p.nombre}</td>
                                        <td data-etiqueta="Municipio">{p.descripcion_municipio}</td>
                                        <td data-etiqueta="Fase">{p.descripcion_fase}</td>
                                        <td data-etiqueta="Estado">
                                            <span className="tb-color-texto" style={{ '--color': p.color_estado || COLORES_GRAFICA.tenue }}>{p.descripcion_estado}</span>
                                        </td>
                                        <td data-etiqueta="Presupuesto" className="numero">{formatearMillones(parseFloat(p.presupuesto))}</td>
                                        <td data-etiqueta="Ejecución" className="numero">{avance.tieneContratos ? `${avance.pct}%` : <span className="tb-tenue">—</span>}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </section>
        </div>
    );
};

export default Estadisticas;
