# Estadísticas: Filtros y Gráficas Interactivas con Recharts — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hand-rolled, partly-fake charts in the Dashboard's "Estadísticas y Filtros" tab with a dedicated `Estadisticas.jsx` component that renders 6 interactive Recharts charts plus a global filter bar (municipio/fase/estado/rango de fechas) and a filtered results table, and delete the dead/dishonest code (`Math.random()`-based data, stale hardcoded color maps) it replaces.

**Architecture:** One new presentational component, `resources/js/components/Estadisticas.jsx`, receives `proyectos` (the full unfiltered array `Dashboard.jsx` already fetches) as its only prop. It owns its own filter state, derives `proyectosFiltrados`, and feeds that into 6 Recharts charts and a results table. `Dashboard.jsx` drops its old inline `activeTab === 'estadisticas'` block and renders `<Estadisticas proyectos={proyectos} />` instead — mirroring how `KpiStrip.jsx` and `MunicipioCard.jsx` were already extracted in prior work.

**Tech Stack:** React 18 (no TypeScript), `recharts` (new dependency), Laravel Mix/webpack build. No backend changes — everything is derived from fields `GET /proyectos` already returns (`descripcion_municipio`, `descripcion_fase`, `descripcion_estado`, `color_estado`, `color_fase`, `presupuesto`, `progreso`, `fecha_inicio`).

---

**Context for whoever executes this plan:** Full spec: `docs/superpowers/specs/2026-07-07-estadisticas-graficas-interactivas-design.md`. There is **no automated frontend test suite** in this repo (no Jest/Vitest in `package.json`) — verification is (a) a successful `npm run dev` build after each task and (b) manual visual confirmation in the browser at the end (Task 7).

**Confirmed before writing this plan:**
- `resources/js/components/Dashboard.jsx` fetches `proyectos` via `useState([])` + an effect that calls `GET /proyectos` — the full, unfiltered array. `Estadisticas.jsx` receives this same array as its `proyectos` prop, exactly like `KpiStrip` already does.
- Every CSS class being deleted in Task 6 (`resumen-*`, `grafica-barras`, `barra-*`, `pie-*`, `legend-*`, `progreso-*`, `barras-*`, `filtros-section` and its descendants, `proyectos-lista`, `proyecto-item`, `proyecto-nombre`, `proyecto-fase`) was grepped across all of `resources/js` and confirmed to have **zero** usages outside the exact JSX block being deleted in Task 5. `.grafica-card` (and its `::before`/`:hover::before`/`h3` sub-rules) and `.estadisticas-content` are **kept** — they're reused by the new component.
- `proyecto.fecha_inicio` is a MySQL `DATE` column (migration: `$table->date('fecha_inicio')->nullable();`), returned by the raw `DB::table` query as a plain `'YYYY-MM-DD'` string — safe to compare lexicographically (`a < b`) and to split on `'-'` for month grouping, no `Date` parsing needed.
- `proyecto.presupuesto` is the same field `KpiStrip.jsx` already uses (`parseFloat(proyecto.presupuesto) || 0`) — the new charts use this field too, **not** `proyecto.totalPresupuesto` (a different, narrower sum used only in the project-detail modal, out of scope here).

---

## Task 1: Install Recharts

**Files:**
- Modify: `package.json`, `package-lock.json` (via `npm install`)

- [ ] **Step 1: Install the dependency**

Run: `npm install recharts@^2 --save`
Expected: `recharts` appears under `"dependencies"` in `package.json`, install completes with no errors (npm audit warnings about unrelated packages are fine, ignore them).

- [ ] **Step 2: Verify the build still compiles**

Run: `npm run dev`
Expected: Mix/webpack build succeeds with no errors (recharts isn't imported anywhere yet, this just confirms the install didn't break anything).

- [ ] **Step 3: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore: add recharts dependency for Estadisticas charts"
```

---

## Task 2: Create `Estadisticas.jsx` — filters, filtered list, results table

**Files:**
- Create: `resources/js/components/Estadisticas.jsx`

No charts yet in this task — just the filter bar, the derived `proyectosFiltrados` list, and the results table. This proves the filtering pipeline works before adding chart complexity in Task 3.

- [ ] **Step 1: Write the component**

```jsx
import React, { useState } from 'react';

const formatearPresupuesto = (valor) => '$' + Math.round((valor || 0) / 1_000_000).toLocaleString('es-CO') + ' M';

const FILTRO_INICIAL = {
    municipio: '',
    fase: '',
    estado: '',
    fechaDesde: '',
    fechaHasta: '',
};

const Estadisticas = ({ proyectos }) => {
    const [filtro, setFiltro] = useState(FILTRO_INICIAL);

    const municipiosUnicos = [...new Set(proyectos.map(p => p.descripcion_municipio).filter(Boolean))].sort();
    const fasesUnicas = [...new Set(proyectos.map(p => p.descripcion_fase).filter(Boolean))].sort();
    const estadosUnicos = [...new Set(proyectos.map(p => p.descripcion_estado).filter(Boolean))].sort();

    const proyectosFiltrados = proyectos.filter(p => {
        if (filtro.municipio && p.descripcion_municipio !== filtro.municipio) return false;
        if (filtro.fase && p.descripcion_fase !== filtro.fase) return false;
        if (filtro.estado && p.descripcion_estado !== filtro.estado) return false;
        if (filtro.fechaDesde && (!p.fecha_inicio || p.fecha_inicio < filtro.fechaDesde)) return false;
        if (filtro.fechaHasta && (!p.fecha_inicio || p.fecha_inicio > filtro.fechaHasta)) return false;
        return true;
    });

    const handleFiltroChange = (campo) => (e) => {
        setFiltro(prev => ({ ...prev, [campo]: e.target.value }));
    };

    const limpiarFiltros = () => setFiltro(FILTRO_INICIAL);

    return (
        <div className="estadisticas-content">
            <div className="filtros-barra">
                <div className="filtro-campo">
                    <label>Municipio</label>
                    <select value={filtro.municipio} onChange={handleFiltroChange('municipio')}>
                        <option value="">Todos</option>
                        {municipiosUnicos.map(m => <option key={m} value={m}>{m}</option>)}
                    </select>
                </div>
                <div className="filtro-campo">
                    <label>Fase</label>
                    <select value={filtro.fase} onChange={handleFiltroChange('fase')}>
                        <option value="">Todas</option>
                        {fasesUnicas.map(f => <option key={f} value={f}>{f}</option>)}
                    </select>
                </div>
                <div className="filtro-campo">
                    <label>Estado</label>
                    <select value={filtro.estado} onChange={handleFiltroChange('estado')}>
                        <option value="">Todos</option>
                        {estadosUnicos.map(e => <option key={e} value={e}>{e}</option>)}
                    </select>
                </div>
                <div className="filtro-campo">
                    <label>Desde</label>
                    <input type="date" value={filtro.fechaDesde} onChange={handleFiltroChange('fechaDesde')} />
                </div>
                <div className="filtro-campo">
                    <label>Hasta</label>
                    <input type="date" value={filtro.fechaHasta} onChange={handleFiltroChange('fechaHasta')} />
                </div>
                <button type="button" className="filtros-limpiar-btn" onClick={limpiarFiltros}>
                    Limpiar filtros
                </button>
            </div>

            <div className="estadisticas-tabla-section">
                <h3>Proyectos filtrados ({proyectosFiltrados.length})</h3>
                {proyectosFiltrados.length === 0 ? (
                    <p className="estadisticas-sin-datos">No hay proyectos que coincidan con los filtros seleccionados.</p>
                ) : (
                    <table className="estadisticas-tabla">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Municipio</th>
                                <th>Fase</th>
                                <th>Estado</th>
                                <th>Presupuesto</th>
                                <th>Avance</th>
                            </tr>
                        </thead>
                        <tbody>
                            {proyectosFiltrados.map(p => (
                                <tr key={p.id}>
                                    <td>{p.nombre}</td>
                                    <td>{p.descripcion_municipio}</td>
                                    <td>{p.descripcion_fase}</td>
                                    <td>
                                        <span className="tabla-estado-pastilla" style={{ backgroundColor: p.color_estado || '#9e9e9e' }}>
                                            {p.descripcion_estado}
                                        </span>
                                    </td>
                                    <td>{formatearPresupuesto(parseFloat(p.presupuesto))}</td>
                                    <td>{parseInt(p.progreso, 10) || 0}%</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
};

export default Estadisticas;
```

- [ ] **Step 2: Verify the build compiles**

Run: `npm run dev`
Expected: succeeds. (The component isn't imported into `Dashboard.jsx` yet — this only confirms the new file itself is syntactically valid and Babel/webpack can process it.)

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/Estadisticas.jsx
git commit -m "feat: add Estadisticas component with filter bar and results table"
```

---

## Task 3: Add the 6 Recharts charts to `Estadisticas.jsx`

**Files:**
- Modify: `resources/js/components/Estadisticas.jsx`

- [ ] **Step 1: Add the Recharts import**

Find the top of `resources/js/components/Estadisticas.jsx`:

```jsx
import React, { useState } from 'react';
```

Replace with:

```jsx
import React, { useState } from 'react';
import {
    ResponsiveContainer, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
    PieChart, Pie, Cell,
} from 'recharts';
```

- [ ] **Step 2: Add the fixed categorical palette and month labels**

Find:

```jsx
const FILTRO_INICIAL = {
```

Insert directly before it:

```jsx
const PALETA_MUNICIPIOS = ['#1976d2', '#43a047', '#fb8c00', '#8e24aa', '#00acc1', '#c62828', '#6d4c41', '#546e7a'];
const MESES_CORTOS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

```

- [ ] **Step 3: Add the chart data derivations**

Find this line (already in the file from Task 2):

```jsx
    const handleFiltroChange = (campo) => (e) => {
```

Insert directly before it:

```jsx
    const colorPorMunicipio = {};
    municipiosUnicos.forEach((m, i) => {
        colorPorMunicipio[m] = PALETA_MUNICIPIOS[i % PALETA_MUNICIPIOS.length];
    });

    const dataPorEstado = Object.values(
        proyectosFiltrados.reduce((acc, p) => {
            const estado = p.descripcion_estado || 'Sin estado';
            if (!acc[estado]) {
                acc[estado] = { estado, cantidad: 0, color: p.color_estado || '#9e9e9e' };
            }
            acc[estado].cantidad++;
            return acc;
        }, {})
    );

    const dataPorFase = Object.values(
        proyectosFiltrados.reduce((acc, p) => {
            const fase = p.descripcion_fase || 'Sin fase';
            if (!acc[fase]) {
                acc[fase] = { fase, cantidad: 0, color: p.color_fase || '#9e9e9e' };
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

    const dataAvancePorFase = Object.values(
        proyectosFiltrados.reduce((acc, p) => {
            const fase = p.descripcion_fase || 'Sin fase';
            if (!acc[fase]) {
                acc[fase] = { fase, sumaAvance: 0, cantidad: 0, color: p.color_fase || '#9e9e9e' };
            }
            acc[fase].sumaAvance += parseInt(p.progreso, 10) || 0;
            acc[fase].cantidad++;
            return acc;
        }, {})
    ).map(item => ({
        fase: item.fase,
        color: item.color,
        avancePromedio: Math.round(item.sumaAvance / item.cantidad),
    }));

    const dataPorMes = Object.values(
        proyectosFiltrados
            .filter(p => p.fecha_inicio)
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

```

- [ ] **Step 4: Add the charts grid to the JSX**

Find the closing of the filter bar and the start of the table section:

```jsx
                <button type="button" className="filtros-limpiar-btn" onClick={limpiarFiltros}>
                    Limpiar filtros
                </button>
            </div>

            <div className="estadisticas-tabla-section">
```

Replace with:

```jsx
                <button type="button" className="filtros-limpiar-btn" onClick={limpiarFiltros}>
                    Limpiar filtros
                </button>
            </div>

            <div className="graficas-grid">
                <div className="grafica-card">
                    <h3>Proyectos por Estado</h3>
                    {dataPorEstado.length === 0 ? (
                        <p className="estadisticas-sin-datos">Sin datos para el filtro actual.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={Math.max(200, dataPorEstado.length * 45)}>
                            <BarChart data={dataPorEstado} layout="vertical" margin={{ left: 20 }}>
                                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                <XAxis type="number" allowDecimals={false} />
                                <YAxis type="category" dataKey="estado" width={140} />
                                <Tooltip formatter={(value) => [`${value} proyectos`, '']} />
                                <Bar dataKey="cantidad" radius={[0, 4, 4, 0]}>
                                    {dataPorEstado.map(entry => (
                                        <Cell key={entry.estado} fill={entry.color} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </div>

                <div className="grafica-card">
                    <h3>Proyectos por Fase</h3>
                    {dataPorFase.length === 0 ? (
                        <p className="estadisticas-sin-datos">Sin datos para el filtro actual.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={260}>
                            <PieChart>
                                <Pie
                                    data={dataPorFase}
                                    dataKey="cantidad"
                                    nameKey="fase"
                                    innerRadius={60}
                                    outerRadius={90}
                                    paddingAngle={2}
                                >
                                    {dataPorFase.map(entry => (
                                        <Cell key={entry.fase} fill={entry.color} />
                                    ))}
                                </Pie>
                                <Tooltip formatter={(value, name) => [`${value} proyectos`, name]} />
                                <Legend />
                            </PieChart>
                        </ResponsiveContainer>
                    )}
                </div>

                <div className="grafica-card">
                    <h3>Proyectos por Municipio</h3>
                    {dataPorMunicipio.length === 0 ? (
                        <p className="estadisticas-sin-datos">Sin datos para el filtro actual.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={Math.max(200, dataPorMunicipio.length * 45)}>
                            <BarChart data={dataPorMunicipio} layout="vertical" margin={{ left: 20 }}>
                                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                <XAxis type="number" allowDecimals={false} />
                                <YAxis type="category" dataKey="municipio" width={120} />
                                <Tooltip formatter={(value) => [`${value} proyectos`, '']} />
                                <Bar dataKey="cantidad" radius={[0, 4, 4, 0]}>
                                    {dataPorMunicipio.map(entry => (
                                        <Cell key={entry.municipio} fill={colorPorMunicipio[entry.municipio] || '#9e9e9e'} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </div>

                <div className="grafica-card">
                    <h3>Presupuesto por Municipio</h3>
                    {dataPresupuestoPorMunicipio.length === 0 ? (
                        <p className="estadisticas-sin-datos">Sin datos para el filtro actual.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={Math.max(200, dataPresupuestoPorMunicipio.length * 45)}>
                            <BarChart data={dataPresupuestoPorMunicipio} layout="vertical" margin={{ left: 20 }}>
                                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                <XAxis type="number" tickFormatter={(v) => formatearPresupuesto(v)} />
                                <YAxis type="category" dataKey="municipio" width={120} />
                                <Tooltip formatter={(value) => [formatearPresupuesto(value), 'Presupuesto']} />
                                <Bar dataKey="presupuesto" radius={[0, 4, 4, 0]}>
                                    {dataPresupuestoPorMunicipio.map(entry => (
                                        <Cell key={entry.municipio} fill={colorPorMunicipio[entry.municipio] || '#9e9e9e'} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </div>

                <div className="grafica-card">
                    <h3>Avance promedio por Fase</h3>
                    {dataAvancePorFase.length === 0 ? (
                        <p className="estadisticas-sin-datos">Sin datos para el filtro actual.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={220}>
                            <BarChart data={dataAvancePorFase}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                <XAxis dataKey="fase" />
                                <YAxis domain={[0, 100]} tickFormatter={(v) => `${v}%`} />
                                <Tooltip formatter={(value) => [`${value}%`, 'Avance promedio']} />
                                <Bar dataKey="avancePromedio" radius={[4, 4, 0, 0]}>
                                    {dataAvancePorFase.map(entry => (
                                        <Cell key={entry.fase} fill={entry.color} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </div>

                <div className="grafica-card">
                    <h3>Proyectos iniciados por mes</h3>
                    {dataPorMes.length === 0 ? (
                        <p className="estadisticas-sin-datos">Sin datos para el filtro actual.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={220}>
                            <BarChart data={dataPorMes}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                <XAxis dataKey="etiqueta" />
                                <YAxis allowDecimals={false} />
                                <Tooltip formatter={(value) => [`${value} proyectos`, '']} />
                                <Bar dataKey="cantidad" fill="#1976d2" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </div>
            </div>

            <div className="estadisticas-tabla-section">
```

- [ ] **Step 5: Verify the build compiles**

Run: `npm run dev`
Expected: succeeds with no errors (still not wired into `Dashboard.jsx`, this just confirms the Recharts JSX and new data derivations are valid).

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/Estadisticas.jsx
git commit -m "feat: add 6 Recharts charts to Estadisticas"
```

---

## Task 4: Wire `Estadisticas` into `Dashboard.jsx`

**Files:**
- Modify: `resources/js/components/Dashboard.jsx`

- [ ] **Step 1: Add the import**

Find line 8 of `resources/js/components/Dashboard.jsx`:

```javascript
import KpiStrip from './KpiStrip';
```

Add directly after it:

```javascript
import KpiStrip from './KpiStrip';
import Estadisticas from './Estadisticas';
```

- [ ] **Step 2: Replace the inline Estadísticas block**

Find this exact block (currently lines 416-641 — verify by searching for the opening `{activeTab === 'estadisticas' && (` line, since line numbers may have shifted slightly from earlier edits in this same file):

```jsx
                            {activeTab === 'estadisticas' && (
                                <div className="estadisticas-content">
                                    {/* Tarjetas de resumen */}
                                    <div className="resumen-cards">
                                        <div className="resumen-card">
                                            <div className="resumen-icon">📊</div>
                                            <div className="resumen-info">
                                                <h3>Total Proyectos</h3>
                                                <span className="resumen-valor">{proyectos.length}</span>
                                            </div>
                                        </div>
                                        {estadosExistentes.slice(0, 3).map((estado, index) => (
                                            <div key={estado} className="resumen-card">
                                                <div className="resumen-icon">
                                                    {index === 0 ? '✅' : index === 1 ? '🚀' : '💰'}
                                                </div>
                                                <div className="resumen-info">
                                                    <h3>{estado}</h3>
                                                    <span className="resumen-valor">{proyectosPorEstado[estado].length}</span>
                                                </div>
                                            </div>
                                        ))}
                                        <div className="resumen-card">
                                            <div className="resumen-icon">💰</div>
                                            <div className="resumen-info">
                                                <h3>Presupuesto Total</h3>
                                                <span className="resumen-valor">
                                                    ${Object.values(getPresupuestoPorMunicipio()).reduce((a, b) => a + b, 0).toLocaleString()}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Gráficas */}
                                    <div className="graficas-section">
                                        <div className="grafica-card">
                                            <h3>Proyectos por Estado</h3>
                                            <div className="grafica-barras">
                                                {Object.entries(getEstadisticasPorEstado()).map(([estado, cantidad]) => (
                                                    <div key={estado} className="barra-item">
                                                        <div className="barra-label">{estado}</div>
                                                        <div className="barra-container">
                                                            <div
                                                                className="barra-fill"
                                                                style={{
                                                                    width: `${(cantidad / proyectos.length) * 100}%`,
                                                                    backgroundColor: getEstadoColor(estado)
                                                                }}
                                                            ></div>
                                                            <span className="barra-valor">{cantidad}</span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>

                                        <div className="grafica-card">
                                            <h3>Proyectos por Fase</h3>
                                            <div className="grafica-pie-real">
                                                <div className="pie-chart">
                                                    {Object.entries(getEstadisticasPorFase()).map(([fase, cantidad], index) => {
                                                        const total = Object.values(getEstadisticasPorFase()).reduce((a, b) => a + b, 0);
                                                        const porcentaje = (cantidad / total) * 100;
                                                        const angulo = (porcentaje / 100) * 360;
                                                        const anguloAcumulado = Object.entries(getEstadisticasPorFase())
                                                            .slice(0, index)
                                                            .reduce((acc, [_, val]) => acc + (val / total) * 360, 0);

                                                        return (
                                                            <div
                                                                key={fase}
                                                                className="pie-slice"
                                                                style={{
                                                                    background: `conic-gradient(from ${anguloAcumulado}deg, ${getFaseColor(fase)} 0deg, ${getFaseColor(fase)} ${angulo}deg, transparent ${angulo}deg)`
                                                                }}
                                                            ></div>
                                                        );
                                                    })}
                                                </div>
                                                <div className="pie-legend">
                                                    {Object.entries(getEstadisticasPorFase()).map(([fase, cantidad]) => (
                                                        <div key={fase} className="legend-item">
                                                            <div
                                                                className="legend-color"
                                                                style={{ backgroundColor: getFaseColor(fase) }}
                                                            ></div>
                                                            <div className="legend-info">
                                                                <span className="legend-label">{fase}</span>
                                                                <span className="legend-value">{cantidad} ({((cantidad / Object.values(getEstadisticasPorFase()).reduce((a, b) => a + b, 0)) * 100).toFixed(1)}%)</span>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="grafica-card">
                                            <h3>Proyectos por Municipio</h3>
                                            <div className="grafica-barras">
                                                {Object.entries(getEstadisticasPorMunicipio()).map(([municipio, cantidad]) => (
                                                    <div key={municipio} className="barra-item">
                                                        <div className="barra-label">{municipio}</div>
                                                        <div className="barra-container">
                                                            <div
                                                                className="barra-fill"
                                                                style={{
                                                                    width: `${(cantidad / proyectos.length) * 100}%`,
                                                                    backgroundColor: getMunicipioColor(municipio)
                                                                }}
                                                            ></div>
                                                            <span className="barra-valor">{cantidad}</span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>


                                        <div className="grafica-card">
                                            <h3>Progreso de Proyectos en Ejecución</h3>
                                            <div className="progreso-grafica">
                                                {getDatosProgreso().map((proyecto, index) => (
                                                    <div key={`${proyecto.nombre}-${proyecto.municipio}-${index}`} className="progreso-item">
                                                        <div className="progreso-info">
                                                            <span className="proyecto-nombre">{proyecto.nombre}</span>
                                                            <span className="proyecto-municipio">{proyecto.municipio}</span>
                                                        </div>
                                                        <div className="progreso-bar-container">
                                                            <div
                                                                className="progreso-bar-fill"
                                                                style={{ width: `${proyecto.progreso}%` }}
                                                            ></div>
                                                            <span className="progreso-porcentaje">{proyecto.progreso}%</span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>

                                    </div>

                                    <div className="graficas-section2">

                                        {/* Nueva gráfica de barras apiladas */}
                                        <div className="grafica-card" style={{ display: 'none' }}>
                                            <h3>Presupuesto Mensual por Municipio</h3>
                                            <div className="barras-apiladas">
                                                {(() => {
                                                    const { meses, presupuestos } = getDatosPresupuestoMensual();
                                                    const maxPresupuesto = Math.max(...presupuestos);
                                                    const municipios = ['Valledupar', 'Aguachica', 'Codazzi', 'La Paz'];

                                                    return (
                                                        <>
                                                            <div className="barras-container">
                                                                {meses.map((mes, index) => {
                                                                    const presupuestoTotal = presupuestos[index];
                                                                    const alturas = municipios.map((municipio, mIndex) => {
                                                                        const porcentaje = (Math.random() * 0.4) + 0.2; // 20-60% del total
                                                                        return {
                                                                            municipio,
                                                                            altura: (presupuestoTotal * porcentaje / maxPresupuesto) * 150,
                                                                            color: getMunicipioColor(municipio)
                                                                        };
                                                                    });

                                                                    return (
                                                                        <div key={mes} className="barra-apilada">
                                                                            <div className="barra-secciones">
                                                                                {alturas.map((seccion, sIndex) => (
                                                                                    <div
                                                                                        key={`${mes}-${seccion.municipio}`}
                                                                                        className="barra-seccion"
                                                                                        style={{
                                                                                            height: `${seccion.altura}px`,
                                                                                            backgroundColor: seccion.color
                                                                                        }}
                                                                                        title={`${seccion.municipio}: $${(presupuestoTotal * ((Math.random() * 0.4) + 0.2)).toLocaleString()}`}
                                                                                    ></div>
                                                                                ))}
                                                                            </div>
                                                                            <div className="barra-label">{mes}</div>
                                                                            <div className="barra-total">${presupuestoTotal.toLocaleString()}</div>
                                                                        </div>
                                                                    );
                                                                })}
                                                            </div>
                                                            <div className="barras-legend">
                                                                {municipios.map(municipio => (
                                                                    <div key={municipio} className="legend-item">
                                                                        <div className="legend-color" style={{ backgroundColor: getMunicipioColor(municipio) }}></div>
                                                                        <span>{municipio}</span>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </>
                                                    );
                                                })()}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Filtros por Estado - Dinámicos */}
                                    <div className="filtros-section">
                                        <h3>Filtros por Estado</h3>
                                        <div className="filtros-grid">
                                            {Object.entries(getEstadisticasPorEstado()).map(([estado, cantidad]) => {
                                                const proyectosDelEstado = getProyectosPorEstado(estado);
                                                return (
                                                    <div key={estado} className="filtro-card">
                                                        <h4>{estado} ({cantidad})</h4>
                                                        <div className="proyectos-lista">
                                                            {proyectosDelEstado.map(proyecto => (
                                                                <div key={proyecto.id} className="proyecto-item">
                                                                    <span className="proyecto-nombre">{proyecto.nombre}</span>
                                                                    <span className="proyecto-fase">{proyecto.descripcion_fase}</span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>
                            )}
```

Replace with:

```jsx
                            {activeTab === 'estadisticas' && <Estadisticas proyectos={proyectos} />}
```

- [ ] **Step 3: Verify the build compiles**

Run: `npm run dev`
Expected: Mix/webpack build succeeds. Note: at this point `Dashboard.jsx` still contains the now-unused functions (`getEstadisticasPorEstado`, `getEstadoColor`, etc.) — that's fine, unused functions don't break a JS build. They're removed in Task 5.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Dashboard.jsx
git commit -m "feat: render Estadisticas component instead of inline charts"
```

---

## Task 5: Remove dead code from `Dashboard.jsx`

**Files:**
- Modify: `resources/js/components/Dashboard.jsx`

After Task 4, these functions and variables have zero remaining call sites in `Dashboard.jsx`. This task deletes them.

- [ ] **Step 1: Remove `getProyectosPorEstado`**

Find this exact block:

```jsx
    // Función para filtrar proyectos por estado (usando descripcion_estado)
    const getProyectosPorEstado = (estado) => {
        return proyectos.filter(proyecto => proyecto.descripcion_estado === estado);
    };

```

Delete it entirely (replace with nothing).

- [ ] **Step 2: Remove the estadísticas/color helper functions**

Find this exact block (it starts right after `getProyectosPorMunicipioFaseYEstado`'s closing `};` and ends right before the `// Obtener proyectos por fase para usar en el componente` comment):

```jsx
    // Función para obtener estadísticas de proyectos por estado
    const getEstadisticasPorEstado = () => {
        const estadisticas = {};
        proyectos.forEach(proyecto => {
            const estado = proyecto.descripcion_estado;
            if (!estadisticas[estado]) {
                estadisticas[estado] = 0;
            }
            estadisticas[estado]++;
        });
        console.log("Estadísticas por estado:", estadisticas);
        return estadisticas;
    };

    // Función para obtener estadísticas por fase
    const getEstadisticasPorFase = () => {
        const estadisticas = {};
        proyectos.forEach(proyecto => {
            const fase = proyecto.descripcion_fase;
            if (!estadisticas[fase]) {
                estadisticas[fase] = 0;
            }
            estadisticas[fase]++;
        });
        console.log("Estadísticas por fase:", estadisticas);
        return estadisticas;
    };

    // Función para obtener estadísticas por municipio
    const getEstadisticasPorMunicipio = () => {
        const estadisticas = {};
        proyectos.forEach(proyecto => {
            const municipio = proyecto.descripcion_municipio;
            if (!estadisticas[municipio]) {
                estadisticas[municipio] = 0;
            }
            estadisticas[municipio]++;
        });
        console.log("Estadísticas por municipio:", estadisticas);
        return estadisticas;
    };

    // Función para obtener datos de progreso de proyectos en ejecución
    const getDatosProgreso = () => {
        const proyectosEnEjecucion = getProyectosPorFase('Ejecución');
        console.log("Proyectos en ejecución:", proyectosEnEjecucion);
        return proyectosEnEjecucion.map(proyecto => ({
            nombre: proyecto.nombre,
            progreso: proyecto.progreso || 0,
            municipio: proyecto.descripcion_municipio
        }));
    };

    // Función para obtener presupuesto total por municipio
    const getPresupuestoPorMunicipio = () => {
        const presupuestos = {};
        proyectos.forEach(proyecto => {
            if (proyecto.totalPresupuesto) {
                const valor = parseInt(proyecto.totalPresupuesto);
                if (!presupuestos[proyecto.descripcion_municipio]) {
                    presupuestos[proyecto.descripcion_municipio] = 0;
                }
                presupuestos[proyecto.descripcion_municipio] += valor;
            }
        });
        console.log("Presupuesto por municipio:", presupuestos);
        return presupuestos;
    };

    // Función para obtener datos de evolución temporal
    const getDatosEvolucionTemporal = () => {
        const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        const datos = {};

        // Simular datos de evolución por mes
        meses.forEach((mes, index) => {
            datos[mes] = {
                formulacion: Math.floor(Math.random() * 10) + 5,
                licitacion: Math.floor(Math.random() * 8) + 3,
                ejecucion: Math.floor(Math.random() * 6) + 2
            };
        });

        return { meses, datos };
    };

    // Función para obtener datos de presupuesto por mes
    const getDatosPresupuestoMensual = () => {
        const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        const presupuestos = meses.map(() => Math.floor(Math.random() * 5000000) + 2000000);
        return { meses, presupuestos };
    };

    // Funciones para colores de gráficas
    const getEstadoColor = (estado) => {
        const colores = {
            'Aprobado': '#4CAF50',
            'En revisión': '#FF9800',
            'Abierta': '#2196F3',
            'En evaluación': '#9C27B0',
            'En ejecución': '#1976D2'
        };
        return colores[estado] || '#757575';
    };

    const getFaseColor = (fase) => {
        const colores = {
            'Formulación': '#FF9800',
            'Licitación': '#2196F3',
            'Ejecución': '#4CAF50'
        };
        return colores[fase] || '#757575';
    };

    const getMunicipioColor = (municipio) => {
        const colores = {
            'Valledupar': '#1976D2',
            'Aguachica': '#43A047',
            'Codazzi': '#FF9800',
            'La Paz': '#9C27B0'
        };
        return colores[municipio] || '#757575';
    };

```

Delete it entirely (replace with nothing).

- [ ] **Step 3: Remove `estadosExistentes`/`proyectosPorEstado`**

Find this exact block:

```jsx
    // Generar dinámicamente los arrays de proyectos por estado
    const estadosExistentes = Object.keys(getEstadisticasPorEstado());
    const proyectosPorEstado = {};
    estadosExistentes.forEach(estado => {
        proyectosPorEstado[estado] = getProyectosPorEstado(estado);
    });
```

Delete it entirely (replace with nothing).

- [ ] **Step 4: Verify the build compiles**

Run: `npm run dev`
Expected: succeeds with no errors and no "is not defined" warnings.

- [ ] **Step 5: Verify nothing else references the removed names**

Run: `grep -n "getEstadisticasPorEstado\|getEstadisticasPorFase\|getEstadisticasPorMunicipio\|getPresupuestoPorMunicipio\|getDatosProgreso\|getDatosEvolucionTemporal\|getDatosPresupuestoMensual\|getEstadoColor\|getFaseColor\|getMunicipioColor\|estadosExistentes\|proyectosPorEstado\|getProyectosPorEstado" resources/js/components/Dashboard.jsx`
Expected: no output (empty).

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/Dashboard.jsx
git commit -m "refactor: remove dead Estadisticas helper functions from Dashboard.jsx"
```

---

## Task 6: CSS — remove dead rules, add new ones

**Files:**
- Modify: `resources/css/Dashboard-Extras.css`

`.grafica-card` (and its `::before`/`:hover::before`/`h3` sub-rules) and `.estadisticas-content` are **not** touched — they're reused by the new component.

- [ ] **Step 1: Remove the dead "resumen" and old chart-container rules**

Find this exact block (starts right after `.estadisticas-grid`'s closing `}`, ends right before `.grafica-card`'s opening rule):

```css
/* Tarjetas de resumen */
.resumen-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
    margin-bottom: 4rem;
}

.resumen-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 1.5rem;
    border-left: 5px solid #1976d2;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.resumen-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.1) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.resumen-card:hover::before {
    opacity: 1;
}

.resumen-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
}

.resumen-icon {
    font-size: 3rem;
    flex-shrink: 0;
    filter: drop-shadow(0 4px 8px rgba(25, 118, 210, 0.3));
    position: relative;
    z-index: 1;
}

.resumen-info {
    position: relative;
    z-index: 1;
}

.resumen-info h3 {
    color: #6c757d;
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.resumen-valor {
    color: #1a237e;
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Sección de gráficas */
.graficas-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 2rem;
}

.graficas-section2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
    margin-top: 4rem;
}
```

Delete it entirely (replace with nothing).

- [ ] **Step 2: Remove the dead bar/pie/legend rules**

Find this exact block (starts right after `.grafica-card h3`'s closing `}`, ends right before `/* Gráfica de progreso */`):

```css
/* Gráfica de barras */
.grafica-barras {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    position: relative;
    z-index: 1;
}

.barra-item {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.barra-label {
    min-width: 150px;
    font-weight: 600;
    color: #1a237e;
    font-size: 1rem;
}

.barra-container {
    flex: 1;
    height: 40px;
    background: rgba(240, 242, 245, 0.8);
    border-radius: 20px;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    backdrop-filter: blur(10px);
}

.barra-fill {
    height: 100%;
    border-radius: 20px;
    transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.barra-valor {
    position: absolute;
    right: 15px;
    color: #1a237e;
    font-weight: 700;
    font-size: 1rem;
    z-index: 1;
}

/* Gráfica de pie real */
.grafica-pie-real {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2rem;
    position: relative;
    z-index: 1;
}

.pie-chart {
    width: 250px;
    height: 250px;
    border-radius: 50%;
    position: relative;
    background: rgba(240, 242, 245, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.pie-slice {
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    clip-path: circle(50% at 50% 50%);
}

.pie-legend {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    width: 100%;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: rgba(248, 249, 250, 0.8);
    border-radius: 12px;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.legend-item:hover {
    background: rgba(227, 242, 253, 0.8);
    transform: translateX(5px);
}

.legend-color {
    width: 25px;
    height: 25px;
    border-radius: 50%;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.legend-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex: 1;
}

.legend-label {
    font-weight: 600;
    color: #1a237e;
    text-transform: capitalize;
    font-size: 1rem;
}

.legend-value {
    background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3);
}
```

Delete it entirely (replace with nothing).

- [ ] **Step 3: Remove the dead progreso rules**

Find this exact block (starts right after the previous block's end, ends right before `/* Gráfica de barras apiladas */`):

```css
/* Gráfica de progreso */
.progreso-grafica {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    position: relative;
    z-index: 1;
}

.progreso-item {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    padding: 1rem;
    background: rgba(248, 249, 250, 0.8);
    border-radius: 12px;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.progreso-item:hover {
    background: rgba(227, 242, 253, 0.8);
    transform: translateY(-2px);
}

.progreso-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.proyecto-nombre {
    font-weight: 600;
    color: #1a237e;
    font-size: 1rem;
}

.proyecto-municipio {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    color: #1976d2;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(25, 118, 210, 0.2);
}

.progreso-bar-container {
    height: 25px;
    background: rgba(240, 242, 245, 0.8);
    border-radius: 12px;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(10px);
}

.progreso-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #4CAF50, #8BC34A);
    border-radius: 12px;
    transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
}

.progreso-porcentaje {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #1a237e;
    font-weight: 700;
    font-size: 0.9rem;
    z-index: 1;
}
```

Delete it entirely (replace with nothing).

- [ ] **Step 4: Remove the dead stacked-bar rules**

Find this exact block (starts right after the previous block's end, ends right before `/* Sección de filtros */`):

```css
/* Gráfica de barras apiladas */
.barras-apiladas {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    position: relative;
    z-index: 1;
}

.barras-container {
    display: flex;
    justify-content: space-around;
    align-items: flex-end;
    height: 250px;
    padding: 0 1rem;
    gap: 1rem;
}

.barra-apilada {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.8rem;
    flex: 1;
}

.barra-secciones {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 60px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.barra-seccion {
    width: 100%;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    min-height: 8px;
}

.barra-seccion:hover {
    opacity: 0.8;
    transform: scale(1.05);
}

.barra-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #6c757d;
    text-align: center;
}

.barra-total {
    font-size: 0.8rem;
    font-weight: 700;
    color: #1a237e;
    text-align: center;
}

.barras-legend {
    display: flex;
    justify-content: center;
    gap: 2rem;
    flex-wrap: wrap;
    margin-top: 1.5rem;
}

.barras-legend .legend-item {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 0.8rem 1.5rem;
    background: rgba(248, 249, 250, 0.8);
    border-radius: 25px;
    font-size: 1rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.barras-legend .legend-item:hover {
    background: rgba(227, 242, 253, 0.8);
    transform: translateY(-2px);
}

.barras-legend .legend-color {
    width: 15px;
    height: 15px;
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

```

Delete it entirely (replace with nothing).

- [ ] **Step 5: Remove the dead filtros-section rules**

Find this exact block (starts right after the previous block's end, ends right before `/* Modales */`):

```css
/* Sección de filtros */
.filtros-section {
    margin-top: 2rem;
}

.filtros-section h3 {
    color: #1a237e;
    font-size: 1.3rem;
    margin-bottom: 2rem;
    font-weight: 700;
    border-bottom: 2px solid rgba(255, 255, 255, 0.3);
    padding-bottom: 1rem;
}

.filtros-section .filtros-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 2rem;
}

.filtros-section .filtro-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border-left: 5px solid #1976d2;
    position: relative;
    overflow: hidden;
}

.filtros-section .filtro-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.1) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.filtros-section .filtro-card:hover::before {
    opacity: 1;
}

.filtros-section .filtro-card h4 {
    color: #1a237e;
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    position: relative;
    z-index: 1;
}

.filtros-section .filtro-card h4::before {
    content: '';
    width: 8px;
    height: 8px;
    background: #1976d2;
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(25, 118, 210, 0.3);
}

.proyectos-lista {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    position: relative;
    z-index: 1;
}

.proyecto-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: rgba(248, 249, 250, 0.8);
    border-radius: 12px;
    border-left: 4px solid rgba(227, 242, 253, 0.8);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    backdrop-filter: blur(10px);
}

.proyecto-item:hover {
    background: rgba(227, 242, 253, 0.8);
    border-left-color: #1976d2;
    transform: translateX(5px);
}

.proyecto-nombre {
    font-weight: 600;
    color: #1a237e;
    font-size: 1rem;
    flex: 1;
}

.proyecto-fase {
    background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
    color: white;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: capitalize;
    box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3);
}
```

Delete it entirely (replace with nothing).

- [ ] **Step 6: Clean up the responsive media queries**

**Note before editing:** the current file has trailing spaces on some otherwise-blank lines inside these `@media` blocks (confirmed with `cat -A`). If your edit tool requires an exact string match, `git show HEAD:resources/css/Dashboard-Extras.css | sed -n '1041,1133p'` (or open the file directly) to copy the literal current bytes rather than retyping the block below from scratch — the content is logically identical to what's shown here, but don't assume this document's whitespace is byte-perfect.

Find this exact block:

```css
@media (min-width: 1200px) {
    .resumen-cards {
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 2.5rem;
        margin-bottom: 2rem;
    }
    
    .graficas-section {
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 2.5rem;
    }
    
    .graficas-section2 {
        grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
        gap: 2.5rem;
    }
    
    .modal-proyecto {
        max-width: 800px;
    }
}

@media (min-width: 1600px) {
    .resumen-cards {
        grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
        gap: 3rem;
        margin-bottom: 2rem;
    }
    
    .graficas-section {
        grid-template-columns: repeat(auto-fit, minmax(550px, 1fr));
        gap: 3rem;
    }
    
    .graficas-section2 {
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 3rem;
    }
    
    .modal-proyecto {
        max-width: 900px;
    }
}

/* Responsive para pantallas pequeñas */
@media (max-width: 768px) {
    .resumen-cards {
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .graficas-section,
    .graficas-section2 {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .pie-chart {
        width: 200px;
        height: 200px;
    }
    
    .barras-container {
        height: 200px;
    }
    
    .barra-secciones {
        max-width: 40px;
    }
    
    .modal-proyecto {
        max-width: 95vw;
        margin: 1rem;
    }
    
    .modal-proyecto-header {
        padding: 1rem 1.5rem;
    }
```

Replace with:

```css
@media (min-width: 1200px) {
    .modal-proyecto {
        max-width: 800px;
    }
}

@media (min-width: 1600px) {
    .modal-proyecto {
        max-width: 900px;
    }
}

/* Responsive para pantallas pequeñas */
@media (max-width: 768px) {
    .graficas-grid {
        grid-template-columns: 1fr;
    }

    .filtros-barra {
        flex-direction: column;
        align-items: stretch;
    }

    .modal-proyecto {
        max-width: 95vw;
        margin: 1rem;
    }
    
    .modal-proyecto-header {
        padding: 1rem 1.5rem;
    }
```

- [ ] **Step 7: Append the new CSS for the filter bar, charts grid, and table**

Add this block to the end of `resources/css/Dashboard-Extras.css`:

```css

/* Barra de filtros de Estadísticas */
.filtros-barra {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 1.2rem;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 1.2rem 1.5rem;
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
    margin-bottom: 2rem;
}

.filtro-campo {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    min-width: 150px;
}

.filtro-campo label {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6c757d;
}

.filtro-campo select,
.filtro-campo input[type="date"] {
    padding: 0.55rem 0.7rem;
    border-radius: 8px;
    border: 1px solid rgba(0, 0, 0, 0.15);
    font-family: inherit;
    font-size: 0.9rem;
    color: #2c3e50;
    background: #fff;
}

.filtros-limpiar-btn {
    padding: 0.6rem 1.2rem;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
    color: #fff;
    font-family: inherit;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.filtros-limpiar-btn:hover {
    opacity: 0.92;
}

/* Grid de gráficas de Estadísticas */
.graficas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
    gap: 2rem;
    margin-bottom: 2rem;
}

.estadisticas-sin-datos {
    color: #6c757d;
    font-size: 0.9rem;
    text-align: center;
    padding: 2rem 0;
}

/* Tabla de resultados filtrados */
.estadisticas-tabla-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.estadisticas-tabla-section h3 {
    color: #1a237e;
    font-size: 1.3rem;
    margin-bottom: 1.5rem;
    font-weight: 700;
}

.estadisticas-tabla {
    width: 100%;
    border-collapse: collapse;
}

.estadisticas-tabla th {
    text-align: left;
    padding: 0.8rem 1rem;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6c757d;
    border-bottom: 2px solid rgba(0, 0, 0, 0.08);
}

.estadisticas-tabla td {
    padding: 0.8rem 1rem;
    font-size: 0.9rem;
    color: #2c3e50;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.tabla-estado-pastilla {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    color: #fff;
    font-size: 0.78rem;
    font-weight: 600;
}
```

- [ ] **Step 8: Verify the build compiles**

Run: `npm run dev`
Expected: Mix/webpack build succeeds.

- [ ] **Step 9: Commit**

```bash
git add resources/css/Dashboard-Extras.css
git commit -m "style: remove dead Estadisticas CSS, add filter bar/charts grid/table styles"
```

---

## Task 7: Manual end-to-end verification

**Files:** none (verification only)

- [ ] **Step 1: Build the frontend assets**

Run: `npm run dev`
Expected: succeeds.

- [ ] **Step 2: Start the app**

If Apache/XAMPP is already serving the app at `http://localhost/GestPro`, use that. Otherwise start a PHP built-in server directly (NOT `php artisan serve` — this repo has a known, unrelated issue where `vendor/laravel/framework/.../Foundation/resources/server.php` is sometimes missing on disk, which breaks `artisan serve`'s router script): `php -S 127.0.0.1:8012 -t public` from the repo root, then open `http://127.0.0.1:8012/`.

- [ ] **Step 3: Log in and open Estadísticas**

Log in with `admin@gestpro.local` / `Admin123!`. Click the "Estadísticas y Filtros" tab. Confirm:
- The filter bar renders with 3 selects (Municipio, Fase, Estado), 2 date inputs, and a "Limpiar filtros" button.
- 6 chart cards render below the filter bar: "Proyectos por Estado", "Proyectos por Fase", "Proyectos por Municipio", "Presupuesto por Municipio", "Avance promedio por Fase", "Proyectos iniciados por mes" — each showing real bars/donut slices, not empty boxes.
- A "Proyectos filtrados (30)" table renders below the charts, listing all 30 seeded projects.

- [ ] **Step 4: Confirm filters actually filter**

Select a municipio (e.g. "Medellín") from the Municipio dropdown. Confirm: the 6 charts update to reflect only Medellín's projects, and the table below shows only Medellín rows with the correct count in its heading. Click "Limpiar filtros". Confirm everything returns to the full 30-project view.

- [ ] **Step 5: Confirm municipio color consistency**

Note the bar color for one municipio in "Proyectos por Municipio". Confirm the same municipio's bar in "Presupuesto por Municipio" uses the identical color.

- [ ] **Step 6: Confirm other tabs and the global KpiStrip are unaffected**

With a filter still active on the Estadísticas tab (e.g. Municipio = "Bello"), click "Formulación". Confirm the municipio cards there are unaffected by the Estadísticas filter (still show all municipios with projects in that fase). Confirm the `KpiStrip` numbers at the very top of the page are unchanged. Click back to "Estadísticas y Filtros" and confirm the filter you set is still selected (component state persists across tab switches since `Dashboard.jsx` doesn't unmount it — if it resets, that's expected React behavior given the conditional render and not a bug to fix).

- [ ] **Step 7: Check the browser console**

Confirm no new console errors reference `getEstadisticasPorEstado`, `getFaseColor`, `getMunicipioColor`, or any other removed function/variable name.
