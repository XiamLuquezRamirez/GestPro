# MunicipioCard Dashboard Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extract the municipio card in GestPro's Dashboard into its own `MunicipioCard` component and add a per-estado segmented status bar using the real estado color already returned by the API, replacing the meaningless positional rainbow border-left.

**Architecture:** A new presentational component (`resources/js/components/MunicipioCard.jsx`) receives the same data `Dashboard.jsx` already computes (municipio, its filtered proyectos for the current fase, the fase name, and the existing click handler) and renders the same markup as today plus a new segmented bar. `Dashboard.jsx` swaps its inline card JSX for `<MunicipioCard />`. No state, no data-fetching, no routes, no backend changes.

**Tech Stack:** React 18 (no TypeScript, no JSX test runner configured in this project), Laravel Mix/webpack for the build.

---

**Context for whoever executes this plan:** This is a small, self-contained frontend-only change. Full spec: `docs/superpowers/specs/2026-07-07-dashboard-municipio-card-design.md`. There is **no automated frontend test suite** in this repo (no Jest/Vitest in `package.json`) — verification is (a) a successful `npm run dev` build (Laravel Mix/webpack will fail loudly on a JSX/syntax error) and (b) manual visual confirmation in the browser. `resources/js/components/Dashboard.jsx` is 774 lines; only the exact block shown in Task 2 changes — do not touch anything else in that file (not the "Estadísticas y Filtros" tab, not the modals, not the drill-in project list, not the Eventos widget).

The local dev database already has demo data seeded (`DemoDataSeeder`, 30 proyectos across 5 municipios × 3 fases × 6 estados with real colors) so the visual result is checkable immediately — log in as `admin@gestpro.local` / `Admin123!` (or whatever the current local admin credentials are) and open the Dashboard.

---

## Task 1: Create the `MunicipioCard` component

**Files:**
- Create: `resources/js/components/MunicipioCard.jsx`

- [ ] **Step 1: Write the component**

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

This renders exactly the same DOM structure `Dashboard.jsx` renders today for a municipio card (`municipio-card-content` / `municipio-icon-modern` / `municipio-info-modern` / `proyectos-count-modern` / `municipio-arrow`), plus one new `municipio-estado-bar` block. `total` is guaranteed `> 0` because the parent only ever renders a card for municipios that already have at least one proyecto in the current fase (see the `.filter(...)` in Task 2's code) — so `seg.cantidad / total` never divides by zero.

- [ ] **Step 2: Verify the build compiles**

Run: `npm run dev` (from `c:\xampp\htdocs\GestPro`)
Expected: Mix/webpack build succeeds with no errors (the new file isn't imported by anything yet, so this just confirms the JSX itself is syntactically valid and the file resolves).

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/MunicipioCard.jsx
git commit -m "feat: add MunicipioCard component with per-estado status bar"
```

---

## Task 2: Wire `MunicipioCard` into `Dashboard.jsx`

**Files:**
- Modify: `resources/js/components/Dashboard.jsx:6` (add import)
- Modify: `resources/js/components/Dashboard.jsx:342-370` (replace inline card markup)

- [ ] **Step 1: Add the import**

In `resources/js/components/Dashboard.jsx`, find line 6:

```javascript
import Eventos from './eventos';
```

Add a new import line directly after it:

```javascript
import Eventos from './eventos';
import MunicipioCard from './MunicipioCard';
```

- [ ] **Step 2: Replace the inline card markup**

Find this exact block in `resources/js/components/Dashboard.jsx` (currently lines 342-370):

```jsx
                                        {!selectedMunicipio ? (
                                            <div className="municipios-grid">
                                                {municipios
                                                    .filter(municipio => getProyectosPorMunicipioYNombreFase(municipio.nombre, fase.nombre).length > 0)
                                                    .map(municipio => {
                                                        const proyectosDelMunicipio = getProyectosPorMunicipioYNombreFase(municipio.nombre, fase.nombre) || [];
                                                        return (
                                                            <div
                                                                key={municipio.nombre}
                                                                className="municipio-card-modern"
                                                                style={{ borderLeftColor: municipio.color }}
                                                                onClick={() => handleMunicipioClick(municipio.nombre)}
                                                            >
                                                                <div className="municipio-card-content">
                                                                    <div className="municipio-icon-modern">
                                                                        {municipio.icon}
                                                                    </div>
                                                                    <div className="municipio-info-modern">
                                                                        <h3>{municipio.nombre}</h3>
                                                                        <div className="proyectos-count-modern">
                                                                            <span className="count-number-modern">{proyectosDelMunicipio.length}</span>
                                                                            <span className="count-label-modern">proyectos en {fase.nombre.toLowerCase()}</span>
                                                                        </div>
                                                                    </div>
                                                                    <div className="municipio-arrow">→</div>
                                                                </div>
                                                            </div>
                                                        );
                                                    })}
                                            </div>
                                        ) : (
```

Replace it with:

```jsx
                                        {!selectedMunicipio ? (
                                            <div className="municipios-grid">
                                                {municipios
                                                    .filter(municipio => getProyectosPorMunicipioYNombreFase(municipio.nombre, fase.nombre).length > 0)
                                                    .map(municipio => {
                                                        const proyectosDelMunicipio = getProyectosPorMunicipioYNombreFase(municipio.nombre, fase.nombre) || [];
                                                        return (
                                                            <MunicipioCard
                                                                key={municipio.nombre}
                                                                municipio={municipio}
                                                                proyectos={proyectosDelMunicipio}
                                                                faseNombre={fase.nombre}
                                                                onClick={() => handleMunicipioClick(municipio.nombre)}
                                                            />
                                                        );
                                                    })}
                                            </div>
                                        ) : (
```

Nothing else in the file changes: `handleMunicipioClick`, the `!selectedMunicipio` drill-in branch (the `else` side of this same ternary, rendering individual `proyecto-card`s), the `estadisticas` tab, both modals, and the `<Eventos />` widget are all untouched.

- [ ] **Step 2: Verify the build compiles**

Run: `npm run dev`
Expected: Mix/webpack build succeeds with no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/Dashboard.jsx
git commit -m "feat: use MunicipioCard in the fase tabs municipio grid"
```

---

## Task 3: Update `Dashboard.css` — new status-bar styles, remove the meaningless rainbow border

**Files:**
- Modify: `resources/css/Dashboard.css:166-169`

- [ ] **Step 1: Replace the positional border-left rules with the new segmented-bar classes**

Find this exact block in `resources/css/Dashboard.css` (currently lines 166-169):

```css
.municipio-card-modern:nth-child(1) { border-left: 5px solid #1976d2; }
.municipio-card-modern:nth-child(2) { border-left: 5px solid #43a047; }
.municipio-card-modern:nth-child(3) { border-left: 5px solid #fbc02d; }
.municipio-card-modern:nth-child(4) { border-left: 5px solid #e53935; }
```

Replace it with:

```css
.municipio-estado-bar {
    display: flex;
    height: 8px;
    border-radius: 6px;
    overflow: hidden;
    margin-top: 0.8rem;
    background: rgba(0, 0, 0, 0.06);
}

.municipio-estado-segmento {
    display: block;
    height: 100%;
}
```

These 4 `nth-child` rules were the only place `.municipio-card-modern` got a `border-left` color, and it was purely positional (card #1 in the grid is always blue, #2 always green, etc. — unrelated to any real data about that municipio). Removing them is safe: nothing else in `Dashboard.css` or `Dashboard.jsx` references `border-left` on this class, and the base `.municipio-card-modern` rule (line 137) doesn't set one either, so the card keeps its existing `border: 1px solid rgba(255, 255, 255, 0.3)` from the base style with no dangling override.

- [ ] **Step 2: Verify the build compiles**

Run: `npm run dev`
Expected: Mix/webpack build succeeds with no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/css/Dashboard.css
git commit -m "style: add municipio estado segment bar, drop positional rainbow border"
```

---

## Task 4: Manual end-to-end verification

**Files:** none (verification only)

- [ ] **Step 1: Build the frontend assets**

Run: `npm run dev`
Expected: succeeds (this is the production-equivalent build the app actually serves; `npm run watch`/`npm run hot` are for live development and aren't needed just to verify).

- [ ] **Step 2: Start the app if it isn't already running**

If Apache/XAMPP is already serving the app at `http://localhost/GestPro`, use that. Otherwise run `php artisan serve` from `c:\xampp\htdocs\GestPro` and use the printed URL (e.g. `http://127.0.0.1:8000`).

- [ ] **Step 3: Log in and open the Dashboard**

Log in with `admin@gestpro.local` / `Admin123!` (the seeded local admin — if the password was since changed, use whatever is current). Open the Dashboard.

- [ ] **Step 4: Confirm the visual result on each fase tab**

For each of the three tabs (Formulación, Licitación, Ejecución):
- Each municipio card shows a thin segmented bar under the proyectos count, with real colors (amber `#fbc02d` / green `#43a047` for Formulación estados, blue `#1976d2` / purple `#8e24aa` for Licitación, blue `#1976d2` / red `#e53935` for Ejecución — per `DemoDataSeeder`'s estado colors) instead of the old flat single-color left border.
- No card shows the old positional rainbow border-left (blue/green/amber/red repeating by position).
- Clicking a card still navigates into that municipio's project list for the current fase, exactly as before.

- [ ] **Step 5: Confirm the untouched areas are unaffected**

Open the "Estadísticas y Filtros" tab and confirm it looks exactly as it did before this change (KPI cards, the 4 hand-rolled charts, "Filtros por Estado" — nothing here should differ). Open a project's detail modal from the drill-in list and confirm it still opens normally.

- [ ] **Step 6: Report the result**

If everything in Steps 4-5 checks out, this task (and the plan) is complete — no commit needed for this task, it's verification-only. If something looks wrong, stop and report exactly what — do not proceed to closing out the plan with an unresolved visual bug.
