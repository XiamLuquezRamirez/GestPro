# KPIs Ejecutivos y Tarjeta de Municipio Ampliada — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a global 6-metric executive KPI strip above GestPro's Dashboard tabs, and expand the existing `MunicipioCard` with budget, average progress, a risk label, and an explicit "Ver detalle" button — all computed client-side from data `GET /proyectos` already returns.

**Architecture:** One new presentational component (`KpiStrip.jsx`) rendered once, outside the per-fase tab loop, so it never changes when switching tabs. `MunicipioCard.jsx` (already exists from a prior task) gains derived calculations from the same `proyectos` prop it already receives — no new props, no new API calls, no backend changes.

**Tech Stack:** React 18 (no TypeScript, no JSX test runner configured in this project), Laravel Mix/webpack for the build.

---

**Context for whoever executes this plan:** Full spec: `docs/superpowers/specs/2026-07-07-dashboard-kpis-ejecutivos-design.md`. There is **no automated frontend test suite** in this repo (no Jest/Vitest in `package.json`) — verification is (a) a successful `npm run dev` build and (b) manual visual confirmation in the browser. `resources/js/components/Dashboard.jsx` is ~774 lines; this plan only adds one new `<KpiStrip />` line plus its import — nothing else in that file changes. `resources/js/components/MunicipioCard.jsx` (52 lines) gets extended, not rewritten.

The local dev database already has demo data seeded (`DemoDataSeeder`: 30 proyectos across 5 municipios × 6 fase/estado combinations, with real `presupuesto` and `progreso` values, plus 4 contratos) — log in as `admin@gestpro.local` / `Admin123!` and open the Dashboard to see real numbers immediately.

**Confirmed before writing this plan:** `ProyectoController::proyectos()` (`app/Http/Controllers/ProyectoController.php:71`) attaches `$proyecto->contratos = $contratos;` to every project in the `GET /proyectos` response — so `proyecto.contratos.length` is always a valid array length, never undefined, for every project returned by that endpoint.

---

## Task 1: Create the `KpiStrip` component

**Files:**
- Create: `resources/js/components/KpiStrip.jsx`

- [ ] **Step 1: Write the component**

```jsx
import React from 'react';

const formatearPresupuesto = (valor) => '$' + Math.round(valor / 1_000_000).toLocaleString('es-CO') + ' M';

const KpiStrip = ({ proyectos }) => {
    const total = proyectos.length;

    const presupuestoTotal = proyectos.reduce((sum, p) => sum + (parseFloat(p.presupuesto) || 0), 0);
    const contratosActivos = proyectos.reduce((sum, p) => sum + (p.contratos ? p.contratos.length : 0), 0);
    const enRiesgo = proyectos.filter(p => p.descripcion_estado === 'Con retraso').length;
    const avancePromedio = total > 0
        ? Math.round(proyectos.reduce((sum, p) => sum + (parseInt(p.progreso, 10) || 0), 0) / total)
        : 0;
    const finalizados = 0;

    return (
        <div className="kpi-strip">
            <div className="kpi-tile">
                <span className="kpi-icono">📊</span>
                <div>
                    <div className="kpi-valor">{total}</div>
                    <div className="kpi-etiqueta">Total proyectos</div>
                </div>
            </div>
            <div className="kpi-tile">
                <span className="kpi-icono">💰</span>
                <div>
                    <div className="kpi-valor">{formatearPresupuesto(presupuestoTotal)}</div>
                    <div className="kpi-etiqueta">Presupuesto total</div>
                </div>
            </div>
            <div className="kpi-tile">
                <span className="kpi-icono">📄</span>
                <div>
                    <div className="kpi-valor">{contratosActivos}</div>
                    <div className="kpi-etiqueta">Contratos activos</div>
                </div>
            </div>
            <div className="kpi-tile kpi-tile-riesgo">
                <span className="kpi-icono">⚠️</span>
                <div>
                    <div className="kpi-valor">{enRiesgo}</div>
                    <div className="kpi-etiqueta">En riesgo</div>
                </div>
            </div>
            <div className="kpi-tile">
                <span className="kpi-icono">📈</span>
                <div>
                    <div className="kpi-valor">{avancePromedio}%</div>
                    <div className="kpi-etiqueta">Avance promedio</div>
                </div>
            </div>
            <div className="kpi-tile" title="Aún no existe un estado de tipo Finalizado en el catálogo">
                <span className="kpi-icono">✅</span>
                <div>
                    <div className="kpi-valor">{finalizados}</div>
                    <div className="kpi-etiqueta">Finalizados</div>
                </div>
            </div>
        </div>
    );
};

export default KpiStrip;
```

`total` guards the `avancePromedio` division (returns `0` instead of `NaN` when `proyectos` is empty — this can legitimately happen before the first `GET /proyectos` response arrives, since `Dashboard.jsx` initializes `proyectos` as `[]`). `presupuesto` and `progreso` are normalized with `parseFloat`/`parseInt` because Eloquent's `decimal:2` cast serializes to a numeric string over JSON, not a native number.

- [ ] **Step 2: Verify the build compiles**

Run: `npm run dev` (from `c:\xampp\htdocs\GestPro`)
Expected: Mix/webpack build succeeds with no errors (the component isn't imported anywhere yet, so this only confirms the JSX itself is valid).

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/KpiStrip.jsx
git commit -m "feat: add KpiStrip component with 6 global executive metrics"
```

---

## Task 2: Wire `KpiStrip` into `Dashboard.jsx`

**Files:**
- Modify: `resources/js/components/Dashboard.jsx:7` (add import)
- Modify: `resources/js/components/Dashboard.jsx:316-317` (insert component)

- [ ] **Step 1: Add the import**

Find line 7 of `resources/js/components/Dashboard.jsx`:

```javascript
import MunicipioCard from './MunicipioCard';
```

Add directly after it:

```javascript
import MunicipioCard from './MunicipioCard';
import KpiStrip from './KpiStrip';
```

- [ ] **Step 2: Insert the component above the tabs**

Find this exact block (currently lines 316-318):

```jsx
            {/* Contenido principal */}
            <main className="dashboard-main ocultar-scroll">
                {/* Pestañas de Proyectos */}
```

Replace it with:

```jsx
            {/* Contenido principal */}
            <main className="dashboard-main ocultar-scroll">
                {/* KPIs ejecutivos — globales, no cambian por pestaña */}
                <KpiStrip proyectos={proyectos} />

                {/* Pestañas de Proyectos */}
```

`proyectos` is the component's existing state (populated by the existing `listProyectos()` effect) — no new state, no new fetch. Because this sits above and outside the `fasesDashboard.map(...)` block that renders each tab's content, it renders exactly once per page load and never re-filters when `activeTab` changes.

- [ ] **Step 2 (verification): Verify the build compiles**

Run: `npm run dev`
Expected: Mix/webpack build succeeds with no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/Dashboard.jsx
git commit -m "feat: render KpiStrip above the dashboard tabs"
```

---

## Task 3: Add KPI strip styles

**Files:**
- Modify: `resources/css/Dashboard.css` (append at end of file)

- [ ] **Step 1: Append the new rules**

Add this block to the end of `resources/css/Dashboard.css`:

```css
/* KPIs ejecutivos */
.kpi-strip {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.kpi-tile {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 1.1rem 1.2rem;
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.12);
    border-left: 4px solid #1976d2;
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.kpi-tile-riesgo {
    border-left-color: #e53935;
}

.kpi-tile-riesgo .kpi-valor {
    color: #e53935;
}

.kpi-icono {
    font-size: 1.6rem;
}

.kpi-valor {
    font-size: 1.7rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    line-height: 1;
    color: #1976d2;
}

.kpi-etiqueta {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    margin-top: 0.3rem;
    font-weight: 600;
}

@media (max-width: 1200px) {
    .kpi-strip {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 640px) {
    .kpi-strip {
        grid-template-columns: repeat(2, 1fr);
    }
}
```

- [ ] **Step 2: Verify the build compiles**

Run: `npm run dev`
Expected: Mix/webpack build succeeds with no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/css/Dashboard.css
git commit -m "style: add executive KPI tile styles"
```

---

## Task 4: Expand `MunicipioCard.jsx` with budget, progress, and a detail button

**Files:**
- Modify: `resources/js/components/MunicipioCard.jsx`

- [ ] **Step 1: Read the current file to confirm it matches**

The current full content of `resources/js/components/MunicipioCard.jsx` (52 lines) is:

```jsx
import React from 'react';

const COLOR_ESTADO_RESPALDO = '#9e9e9e';

const MunicipioCard = ({ municipio, proyectos, faseNombre, onClick }) => {
    const total = proyectos.length;

    const segmentos = Object.values(
        proyectos.reduce((acc, proyecto) => {
            const estado = proyecto.descripcion_estado || 'Sin estado';
            if (!acc[estado]) {
                acc[estado] = {
                    estado,
                    color: proyecto.color_estado || COLOR_ESTADO_RESPALDO,
                    cantidad: 0,
                };
            }
            acc[estado].cantidad++;
            return acc;
        }, {})
    );

    return (
        <div className="municipio-card-modern" onClick={onClick}>
            <div className="municipio-card-content">
                <div className="municipio-icon-modern">
                    {municipio.icon}
                </div>
                <div className="municipio-info-modern">
                    <h3>{municipio.nombre}</h3>
                    <div className="proyectos-count-modern">
                        <span className="count-number-modern">{total}</span>
                        <span className="count-label-modern">proyectos en {faseNombre.toLowerCase()}</span>
                    </div>
                    <div className="municipio-estado-bar">
                        {segmentos.map(seg => (
                            <span
                                key={seg.estado}
                                className="municipio-estado-segmento"
                                style={{ width: `${(seg.cantidad / total) * 100}%`, backgroundColor: seg.color }}
                                title={`${seg.estado}: ${seg.cantidad}`}
                            />
                        ))}
                    </div>
                </div>
                <div className="municipio-arrow">→</div>
            </div>
        </div>
    );
};

export default MunicipioCard;
```

If what you find differs from this (even in whitespace/prop names), STOP and report back (BLOCKED or NEEDS_CONTEXT) rather than guessing how to adapt the next step.

- [ ] **Step 2: Replace the file with the expanded version**

```jsx
import React from 'react';

const COLOR_ESTADO_RESPALDO = '#9e9e9e';

const formatearPresupuesto = (valor) => '$' + Math.round(valor / 1_000_000).toLocaleString('es-CO') + ' M';

const MunicipioCard = ({ municipio, proyectos, faseNombre, onClick }) => {
    const total = proyectos.length;

    const segmentos = Object.values(
        proyectos.reduce((acc, proyecto) => {
            const estado = proyecto.descripcion_estado || 'Sin estado';
            if (!acc[estado]) {
                acc[estado] = {
                    estado,
                    color: proyecto.color_estado || COLOR_ESTADO_RESPALDO,
                    cantidad: 0,
                };
            }
            acc[estado].cantidad++;
            return acc;
        }, {})
    );

    const presupuestoTotal = proyectos.reduce((sum, p) => sum + (parseFloat(p.presupuesto) || 0), 0);
    const avancePromedio = total > 0
        ? Math.round(proyectos.reduce((sum, p) => sum + (parseInt(p.progreso, 10) || 0), 0) / total)
        : 0;
    const enRiesgo = proyectos.filter(p => p.descripcion_estado === 'Con retraso').length;

    const handleDetalleClick = (e) => {
        e.stopPropagation();
        onClick();
    };

    return (
        <div className="municipio-card-modern" onClick={onClick}>
            <div className="municipio-card-content">
                <div className="municipio-icon-modern">
                    {municipio.icon}
                </div>
                <div className="municipio-info-modern">
                    <h3>{municipio.nombre}</h3>
                    <div className="proyectos-count-modern">
                        <span className="count-number-modern">{total}</span>
                        <span className="count-label-modern">proyectos en {faseNombre.toLowerCase()}</span>
                    </div>
                    <div className="municipio-estado-bar">
                        {segmentos.map(seg => (
                            <span
                                key={seg.estado}
                                className="municipio-estado-segmento"
                                style={{ width: `${(seg.cantidad / total) * 100}%`, backgroundColor: seg.color }}
                                title={`${seg.estado}: ${seg.cantidad}`}
                            />
                        ))}
                    </div>
                    <div className="municipio-presupuesto">
                        <span>Presupuesto</span>
                        <b>{formatearPresupuesto(presupuestoTotal)}</b>
                    </div>
                    <div className="municipio-avance-bar">
                        <div className="municipio-avance-fill" style={{ width: `${avancePromedio}%` }}></div>
                    </div>
                    <div className="municipio-avance-valor">{avancePromedio}% de avance promedio</div>
                    <div className={`municipio-estado-general${enRiesgo > 0 ? ' en-riesgo' : ''}`}>
                        {enRiesgo > 0 ? `▲ ${enRiesgo} en riesgo` : '● Buen ritmo'}
                    </div>
                    <button type="button" className="municipio-btn-detalle" onClick={handleDetalleClick}>
                        Ver detalle
                    </button>
                </div>
                <div className="municipio-arrow">→</div>
            </div>
        </div>
    );
};

export default MunicipioCard;
```

Two things to notice: the outer `<div className="municipio-card-modern" onClick={onClick}>` keeps its `onClick` exactly as before (the whole card stays clickable, per the design decision). The new `<button>`'s own click handler calls `e.stopPropagation()` before calling `onClick()` — without that, a click on the button would bubble up to the card's own `onClick` too, firing the same `handleMunicipioClick` twice in one click, which toggles `selectedMunicipio` on and back off in the parent (`Dashboard.jsx`'s `handleMunicipioClick` does `setSelectedMunicipio(selectedMunicipio === municipio ? null : municipio)`), making the button look like it does nothing.

- [ ] **Step 3: Verify the build compiles**

Run: `npm run dev`
Expected: Mix/webpack build succeeds with no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/MunicipioCard.jsx
git commit -m "feat: add budget, average progress, risk label, and detail button to MunicipioCard"
```

---

## Task 5: Add municipio card styles

**Files:**
- Modify: `resources/css/Dashboard.css` (append at end of file)

- [ ] **Step 1: Append the new rules**

```css
/* Ampliación de la tarjeta de municipio: presupuesto, avance, riesgo, botón */
.municipio-presupuesto {
    display: flex;
    justify-content: space-between;
    margin-top: 0.7rem;
    font-size: 0.78rem;
    color: #64748b;
}

.municipio-presupuesto b {
    color: #2c3e50;
    font-variant-numeric: tabular-nums;
}

.municipio-avance-bar {
    height: 8px;
    border-radius: 6px;
    background: rgba(0, 0, 0, 0.06);
    margin-top: 0.5rem;
    overflow: hidden;
}

.municipio-avance-fill {
    height: 100%;
    background: #1976d2;
    border-radius: 6px;
}

.municipio-avance-valor {
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 0.3rem;
}

.municipio-estado-general {
    font-size: 0.78rem;
    font-weight: 600;
    color: #43a047;
    margin-top: 0.6rem;
}

.municipio-estado-general.en-riesgo {
    color: #e53935;
}

.municipio-btn-detalle {
    margin-top: 0.8rem;
    width: 100%;
    padding: 0.55rem;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
    color: #fff;
    font-family: inherit;
    font-weight: 600;
    font-size: 0.82rem;
    cursor: pointer;
}

.municipio-btn-detalle:hover {
    opacity: 0.92;
}
```

- [ ] **Step 2: Verify the build compiles**

Run: `npm run dev`
Expected: Mix/webpack build succeeds with no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/css/Dashboard.css
git commit -m "style: add budget, progress, risk, and detail button styles to municipio card"
```

---

## Task 6: Manual end-to-end verification

**Files:** none (verification only)

- [ ] **Step 1: Build the frontend assets**

Run: `npm run dev`
Expected: succeeds.

- [ ] **Step 2: Start the app**

If Apache/XAMPP is already serving the app at `http://localhost/GestPro`, use that. Otherwise start a PHP built-in server directly (NOT `php artisan serve` — this repo has a known, unrelated issue where `vendor/laravel/framework/.../Foundation/resources/server.php` is sometimes missing on disk, which breaks `artisan serve`'s router script): `php -S 127.0.0.1:8010 -t public` from `c:\xampp\htdocs\GestPro`, then open `http://127.0.0.1:8010/`.

- [ ] **Step 3: Log in and confirm the KPI strip**

Log in with `admin@gestpro.local` / `Admin123!`. On the Dashboard, confirm above the tabs:
- **Total proyectos: 30**
- **Presupuesto total: $17.021 M**
- **Contratos activos: 4**
- **En riesgo: 5**
- **Avance promedio:** some percentage between 0-100 (exact value depends on the random `progreso` values `DemoDataSeeder` generated — not a fixed number to check against, just confirm it's a plausible percentage, not `NaN%` or `0%`)
- **Finalizados: 0**, and hovering it shows the tooltip explaining why.

- [ ] **Step 4: Confirm the KPI strip doesn't change between tabs**

Click "Licitación", then "Ejecución", then back to "Formulación". Confirm the 6 KPI numbers stay exactly the same across all three — they should NOT recompute per tab.

- [ ] **Step 5: Confirm each municipio card**

On any fase tab, confirm each of the 5 municipio cards now shows, below the existing status bar: a "Presupuesto" line with a dollar value, a thin progress bar with a percentage below it, a status label (either "● Buen ritmo" or "▲ N en riesgo"), and a "Ver detalle" button.

- [ ] **Step 6: Confirm click behavior**

Click directly on the "Ver detalle" button of one card — confirm it navigates into that municipio's project list (same view as clicking the card itself). Go back, then click on the card body itself (not the button) — confirm it still navigates the same way.

- [ ] **Step 7: Confirm the Estadísticas tab and modals are unaffected**

Open "Estadísticas y Filtros" — confirm it looks exactly as before this change. Open a project detail modal from the drill-in list — confirm it still opens normally.

- [ ] **Step 8: Report the result**

If everything in Steps 3-7 checks out, this task (and the plan) is complete — no commit needed for this task, it's verification-only. If something looks wrong, stop and report exactly what — do not proceed to closing out the plan with an unresolved issue.
