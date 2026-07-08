# Dashboard: Layout en Grid + Panel de Alertas — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Dashboard's "Próximos Eventos" carousel with a compact `AlertasPanel` (proyectos en riesgo + eventos de alta prioridad) placed in a new sidebar column next to the fase-tab content, so the fase tabs (Formulación/Licitación/Ejecución) fit without scrolling on large screens — fixing the pre-existing `eventos.estado` backend bug and seeding demo data for eventos/prioridades/tipos/responsables along the way, since the new panel has nothing to show without them.

**Architecture:** A 2-column CSS grid (`.dashboard-fase-grid`) wraps the existing fase-tab content (unchanged internally) alongside a new `AlertasPanel.jsx` sidebar component. The grid only applies to fase tabs — Estadísticas keeps its current full-width, independently-scrolling layout. `Eventos.jsx` is deleted; its data-fetch and icon-mapping logic move into `AlertasPanel.jsx`, filtered down to just the "alta prioridad" subset.

**Tech Stack:** Laravel 12 (PHP), React 18, Laravel Mix/webpack. One backend change (column alias in an existing query), one seeder extension, one new React component, layout/CSS changes.

---

**Context for whoever executes this plan:** Full spec: `docs/superpowers/specs/2026-07-07-dashboard-layout-alertas-design.md`. No frontend test suite exists (no Jest/Vitest) — verification is `npm run dev` + manual browser checks. Backend has PHPUnit (`php artisan test`, 36 tests currently passing) — no test covers the `eventos()` endpoint specifically, but the full suite must stay green.

**Confirmed before writing this plan:**
- `app/Http/Controllers/ProyectoController.php`'s `eventos()` method selects `'eventos.estado'`, but the migration (`2026_07_06_160021_create_eventos_table.php`) defines the column as `estado_evento`. This makes `GET /eventos` fail with a 500 today. The `App\Models\Evento` model already expects `estado_evento` (see its `$fillable`), confirming the migration is right and the controller is wrong.
- Tables `prioridades`, `tipo_eventos`, `responsable`, and `eventos` are completely empty — no seeder populates them. Models `Evento`, `Prioridad`, `TipoEvento`, `Responsable` already exist in `app/Models/` with the relations/casts needed; no model changes required.
- `resources/js/components/eventos.jsx` is imported **only** by `resources/js/components/Dashboard.jsx` (grepped across all of `resources/js`) — safe to delete once that import is removed.
- **CSS reuse warning:** `resources/js/components/GestionarEventos.jsx` (a separate, unrelated admin page with no CSS import of its own — it relies on `Dashboard.css`/`Dashboard-Extras.css` being globally bundled by webpack) uses the classes `.eventos-grid`, `.evento-card` (and its `::before`/`::after`/`:hover` variants), `.evento-icon`, `.evento-content`, `.evento-descripcion`, and `.prioridad-badge` (base rule + `:hover`). **None of these are touched by this plan.** Only the classes verified (via grep) to be exclusive to `eventos.jsx` are removed in Task 5: `.eventos-header`, `.slider-controls`, `.slider-btn`, `.slider-indicator`, `.eventos-slider` (+ `::before`), `.evento-fecha`, `.fecha-icon`, `.evento-responsable`, `.responsable-icon`, `.evento-prioridad`, the 3 `.evento-card[style*='...']` attribute-selector hacks (these only ever matched `eventos.jsx`'s inline `borderLeftColor` style — `GestionarEventos.jsx` never sets that inline style), `.auto-rotation-indicator`, `.rotation-dot`.
- `Dashboard.jsx` declares `const [currentEventIndex, setCurrentEventIndex] = useState(0);` (line 13) which is **never read anywhere in that file** (grepped) — it's dead state left over from an earlier version, removed alongside the `Eventos` wiring in Task 4.

---

## Task 1: Fix the `eventos.estado` backend bug

**Files:**
- Modify: `app/Http/Controllers/ProyectoController.php`

- [ ] **Step 1: Alias the column**

Find this exact block in the `eventos()` method:

```php
                'eventos.responsable',
                'eventos.estado',
                'tipo_eventos.icono',
```

Replace with:

```php
                'eventos.responsable',
                DB::raw('eventos.estado_evento as estado'),
                'tipo_eventos.icono',
```

- [ ] **Step 2: Verify the full test suite still passes**

Run: `php artisan test`
Expected: `Tests: 36 passed`.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/ProyectoController.php
git commit -m "fix: alias eventos.estado_evento as estado in eventos() query"
```

---

## Task 2: Seed demo data for prioridades, tipos de evento, responsables, and eventos

**Files:**
- Modify: `database/seeders/DemoDataSeeder.php`

- [ ] **Step 1: Add the new model imports**

Find this exact block:

```php
use App\Models\Contrato;
use App\Models\Entidad;
use App\Models\Estado;
use App\Models\Fase;
use App\Models\PresupuestoProyecto;
use App\Models\Proyecto;
use App\Models\Sector;
use Illuminate\Database\Seeder;
```

Replace with:

```php
use App\Models\Contrato;
use App\Models\Entidad;
use App\Models\Estado;
use App\Models\Evento;
use App\Models\Fase;
use App\Models\PresupuestoProyecto;
use App\Models\Prioridad;
use App\Models\Proyecto;
use App\Models\Responsable;
use App\Models\Sector;
use App\Models\TipoEvento;
use Illuminate\Database\Seeder;
```

- [ ] **Step 2: Call the new seeding method from `run()`**

Find this exact block:

```php
        $proyectos = $this->seedProyectos($fases, $estados, $sectores, $entidades, $municipios);
        $this->seedContratos($proyectos);
    }
```

Replace with:

```php
        $proyectos = $this->seedProyectos($fases, $estados, $sectores, $entidades, $municipios);
        $this->seedContratos($proyectos);
        $this->seedEventos($proyectos);
    }
```

- [ ] **Step 3: Add the `seedEventos` method**

Find this exact block (the end of `seedContratos` and the closing brace of the class):

```php
            PresupuestoProyecto::updateOrCreate(
                ['proyecto' => $proyecto->id, 'componente' => 'Interventoría'],
                ['valor' => round(((float) $proyecto->presupuesto) * 0.08, 2)]
            );
        }
    }
}
```

Replace with:

```php
            PresupuestoProyecto::updateOrCreate(
                ['proyecto' => $proyecto->id, 'componente' => 'Interventoría'],
                ['valor' => round(((float) $proyecto->presupuesto) * 0.08, 2)]
            );
        }
    }

    private function seedEventos(array $proyectos): void
    {
        $prioridades = [
            'Alta' => Prioridad::updateOrCreate(['nombre' => 'Alta'], ['color' => '#e53935', 'activo' => true]),
            'Media' => Prioridad::updateOrCreate(['nombre' => 'Media'], ['color' => '#fbc02d', 'activo' => true]),
            'Baja' => Prioridad::updateOrCreate(['nombre' => 'Baja'], ['color' => '#43a047', 'activo' => true]),
        ];

        $tipos = [
            'Revisión' => TipoEvento::updateOrCreate(['nombre' => 'Revisión'], ['icono' => 'revision', 'activo' => true]),
            'Contrato' => TipoEvento::updateOrCreate(['nombre' => 'Contrato'], ['icono' => 'contrato', 'activo' => true]),
            'Inspección' => TipoEvento::updateOrCreate(['nombre' => 'Inspección'], ['icono' => 'inspeccion', 'activo' => true]),
            'Documentación' => TipoEvento::updateOrCreate(['nombre' => 'Documentación'], ['icono' => 'documentacion', 'activo' => true]),
        ];

        $responsables = [
            'Ana Gómez' => Responsable::updateOrCreate(['nombre' => 'Ana Gómez'], ['cargo' => 'Supervisora de obra', 'activo' => true]),
            'Carlos Ruiz' => Responsable::updateOrCreate(['nombre' => 'Carlos Ruiz'], ['cargo' => 'Interventor', 'activo' => true]),
            'Laura Pérez' => Responsable::updateOrCreate(['nombre' => 'Laura Pérez'], ['cargo' => 'Coordinadora jurídica', 'activo' => true]),
        ];

        // [índice en $proyectos, título, tipo, prioridad, días desde hoy (negativo = pasado), responsable, estado_evento]
        $definiciones = [
            [0, 'Revisión de avance físico', 'Revisión', 'Alta', 3, 'Ana Gómez', 'pendiente'],
            [2, 'Publicación de pliego de condiciones', 'Documentación', 'Alta', 5, 'Laura Pérez', 'pendiente'],
            [4, 'Inspección de obra en sitio', 'Inspección', 'Alta', 7, 'Carlos Ruiz', 'pendiente'],
            [6, 'Firma de contrato de interventoría', 'Contrato', 'Media', 10, 'Carlos Ruiz', 'pendiente'],
            [8, 'Revisión de informe mensual', 'Revisión', 'Media', 14, 'Ana Gómez', 'pendiente'],
            [10, 'Entrega de documentación técnica', 'Documentación', 'Baja', 20, 'Laura Pérez', 'pendiente'],
            [12, 'Auditoría de cierre', 'Inspección', 'Alta', -5, 'Carlos Ruiz', 'completado'],
            [1, 'Seguimiento a hallazgos', 'Revisión', 'Alta', 2, 'Ana Gómez', 'cancelado'],
        ];

        foreach ($definiciones as [$index, $titulo, $tipoNombre, $prioridadNombre, $dias, $responsableNombre, $estadoEvento]) {
            $proyecto = $proyectos[$index]['proyecto'];

            Evento::updateOrCreate(
                ['titulo' => $titulo, 'proyecto' => $proyecto->id],
                [
                    'descripcion' => 'Evento de demostración para ' . $proyecto->nombre . '.',
                    'fecha' => now()->addDays($dias)->format('Y-m-d'),
                    'tipo_eventos' => $tipos[$tipoNombre]->id,
                    'prioridad' => $prioridades[$prioridadNombre]->id,
                    'estado_evento' => $estadoEvento,
                    'responsable' => $responsables[$responsableNombre]->id,
                ]
            );
        }
    }
}
```

Note: `$proyectos` here is the array `seedProyectos()` returns — a list of `['proyecto' => Proyecto, 'fase' => string]`, in a fixed, deterministic order (5 municipios × 6 fase/estado combinations = 30 entries, always built in the same order). Indices `0, 1, 2, 4, 6, 8, 10, 12` are all within that range and stable across reseeds.

- [ ] **Step 4: Run the seeder**

Run: `php artisan db:seed --class=DemoDataSeeder`
Expected: completes with no errors.

- [ ] **Step 5: Verify the data landed**

Run: `php artisan tinker --execute="echo DB::table('eventos')->count() . ' eventos, ' . DB::table('prioridades')->count() . ' prioridades, ' . DB::table('eventos')->where('estado_evento', 'pendiente')->count() . ' pendientes';"`
Expected output: `8 eventos, 3 prioridades, 6 pendientes`

- [ ] **Step 6: Commit**

```bash
git add database/seeders/DemoDataSeeder.php
git commit -m "feat: seed demo prioridades, tipos de evento, responsables, and eventos"
```

---

## Task 3: Create `AlertasPanel.jsx`

**Files:**
- Create: `resources/js/components/AlertasPanel.jsx`

- [ ] **Step 1: Write the component**

```jsx
import React, { useState, useEffect } from 'react';
import axios from 'axios';

const getEventIcon = (tipo) => {
    const icons = {
        pliego: '📋',
        propuesta: '📄',
        audiencia: '👥',
        documentacion: '📁',
        revision: '🔍',
        contrato: '✍️',
        inicio: '🚀',
        inspeccion: '🔧'
    };
    return icons[tipo] || '📅';
};

const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('es-ES', options);
};

const AlertasPanel = ({ proyectos, onProyectoClick }) => {
    const [eventos, setEventos] = useState([]);

    useEffect(() => {
        axios.get('/eventos').then(response => setEventos(response.data));
    }, []);

    const proyectosEnRiesgo = proyectos.filter(p => p.descripcion_estado === 'Con retraso');

    const eventosAltaPrioridad = eventos
        .filter(e => e.nombre === 'Alta' && e.estado !== 'cancelado' && e.estado !== 'completado')
        .sort((a, b) => new Date(a.fecha) - new Date(b.fecha));

    const sinAlertas = proyectosEnRiesgo.length === 0 && eventosAltaPrioridad.length === 0;

    return (
        <aside className="alertas-panel">
            <h3>Alertas y Prioridades</h3>
            {sinAlertas ? (
                <p className="alertas-sin-datos">Sin alertas activas por ahora.</p>
            ) : (
                <>
                    {proyectosEnRiesgo.length > 0 && (
                        <div className="alertas-bloque">
                            <h4>⚠️ Proyectos en riesgo</h4>
                            <ul className="alertas-lista">
                                {proyectosEnRiesgo.map(proyecto => (
                                    <li
                                        key={proyecto.id}
                                        className="alerta-item alerta-item-riesgo"
                                        onClick={() => onProyectoClick(proyecto)}
                                    >
                                        <span className="alerta-titulo">{proyecto.nombre}</span>
                                        <span className="alerta-municipio">{proyecto.descripcion_municipio}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {eventosAltaPrioridad.length > 0 && (
                        <div className="alertas-bloque">
                            <h4>🔴 Eventos de alta prioridad</h4>
                            <ul className="alertas-lista">
                                {eventosAltaPrioridad.map(evento => (
                                    <li key={evento.id} className="alerta-item" style={{ borderLeftColor: evento.color }}>
                                        <span className="alerta-icono">{getEventIcon(evento.icono)}</span>
                                        <div className="alerta-info">
                                            <span className="alerta-titulo">{evento.titulo}</span>
                                            <span className="alerta-fecha">{formatDate(evento.fecha)}</span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </>
            )}
        </aside>
    );
};

export default AlertasPanel;
```

- [ ] **Step 2: Verify the build compiles**

Run: `npm run dev`
Expected: succeeds. (Not wired into `Dashboard.jsx` yet — this only confirms the new file is syntactically valid.)

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/AlertasPanel.jsx
git commit -m "feat: add AlertasPanel component (proyectos en riesgo + eventos de alta prioridad)"
```

---

## Task 4: Wire `AlertasPanel` into `Dashboard.jsx`, remove `Eventos`

**Files:**
- Modify: `resources/js/components/Dashboard.jsx`
- Delete: `resources/js/components/eventos.jsx`

- [ ] **Step 1: Swap the imports**

Find this exact block:

```jsx
import Eventos from './eventos';
import MunicipioCard from './MunicipioCard';
import KpiStrip from './KpiStrip';
import Estadisticas from './Estadisticas';
```

Replace with:

```jsx
import MunicipioCard from './MunicipioCard';
import KpiStrip from './KpiStrip';
import Estadisticas from './Estadisticas';
import AlertasPanel from './AlertasPanel';
```

- [ ] **Step 2: Remove the dead `currentEventIndex` state**

Find this exact block:

```jsx
    const [currentEventIndex, setCurrentEventIndex] = useState(0);
    const [selectedMunicipio, setSelectedMunicipio] = useState(null);
```

Replace with:

```jsx
    const [selectedMunicipio, setSelectedMunicipio] = useState(null);
```

- [ ] **Step 3: Wrap the fase-tab content in the new grid, add the sidebar**

Find this exact block:

```jsx
                        <div className="tab-content">
                            {fasesDashboard.map(fase => (
                                activeTab === fase.nombre && (
```

Replace with:

```jsx
                        <div className="tab-content">
                            {activeTab !== 'estadisticas' && (
                            <div className="dashboard-fase-grid">
                            <div className="dashboard-fase-main">
                            {fasesDashboard.map(fase => (
                                activeTab === fase.nombre && (
```

Then find this exact block (the closing of that same `.map()`, right before the Estadísticas line):

```jsx
                                )
                            ))}
                            {activeTab === 'estadisticas' && <Estadisticas proyectos={proyectos} />}
                        </div>
```

Replace with:

```jsx
                                )
                            ))}
                            </div>
                            <AlertasPanel proyectos={proyectos} onProyectoClick={handleOpenModalProyecto} />
                            </div>
                            )}
                            {activeTab === 'estadisticas' && <Estadisticas proyectos={proyectos} />}
                        </div>
```

Nothing between these two found blocks (the ~70 lines rendering `municipios-grid`/`municipio-proyectos-view`) changes — this only adds the grid wrapper and the sidebar around the existing content.

- [ ] **Step 4: Remove the `<Eventos />` section**

Find this exact block:

```jsx
                </section>

                {/* Sección de Eventos con Slider */}
                <Eventos />
            </main>
```

Replace with:

```jsx
                </section>
            </main>
```

- [ ] **Step 5: Delete the now-unused `eventos.jsx` file**

```bash
git rm resources/js/components/eventos.jsx
```

- [ ] **Step 6: Verify the build compiles**

Run: `npm run dev`
Expected: succeeds, no "Eventos is not defined" or missing-module errors.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/Dashboard.jsx
git commit -m "feat: replace Eventos carousel with AlertasPanel in a 2-column fase-tab layout"
```

(The `git rm` from Step 5 stages the deletion; it'll be included in this same commit since it's already staged.)

---

## Task 5: CSS — remove eventos-carousel-only rules, add the grid + alerts panel styles

**Files:**
- Modify: `resources/css/Dashboard.css`

**Do not remove** `.eventos-grid`, `.evento-card` (and its `::before`/`::after`/`:hover` variants), `.evento-icon`, `.evento-content` (+ `h3` sub-rule), `.evento-descripcion`, `.prioridad-badge` (+ `:hover`), `@keyframes slideInFromRight`, or the two later duplicate `.evento-card` rules (animation, hover depth) — `resources/js/components/GestionarEventos.jsx`, a separate page, depends on all of these and is **not** part of this plan's scope.

- [ ] **Step 1: Un-share `.eventos-section` from the tab-section rules**

Find this exact block:

```css
/* Pestañas de Proyectos */
.proyectos-tabs-section,
.eventos-section {
    margin-bottom: 2rem;
}

.proyectos-tabs-section h2,
.eventos-section h2 {
    color: #fff;
    font-size: 1.4rem;
    margin-bottom: 1.5rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    position: static;
}
```

Replace with:

```css
/* Pestañas de Proyectos */
.proyectos-tabs-section {
    margin-bottom: 2rem;
}

.proyectos-tabs-section h2 {
    color: #fff;
    font-size: 1.4rem;
    margin-bottom: 1.5rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    position: static;
}
```

- [ ] **Step 2: Remove the slider/carousel chrome (header, controls, wrapper)**

Find this exact block:

```css
/* Eventos con Slider */
.eventos-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.slider-controls {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.slider-btn {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
    border: 2px solid #1976d2;
    color: #1976d2;
    width: 45px;
    height: 45px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.5rem;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    backdrop-filter: blur(10px);
    box-shadow: 0 8px 25px rgba(25, 118, 210, 0.2);
}

.slider-btn:hover {
    background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
    color: #fff;
    border-color: #1976d2;
    transform: translateY(-2px) scale(1.1);
    box-shadow: 0 12px 35px rgba(25, 118, 210, 0.4);
}

.slider-indicator {
    color: #fff;
    font-size: 1rem;
    font-weight: 700;
    min-width: 60px;
    text-align: center;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.eventos-slider {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
    position: relative;
    overflow: hidden;
}

.eventos-slider::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.5) 50%, transparent 100%);
    z-index: 1;
}
```

Delete it entirely (replace with nothing). Immediately after this deletion, `.eventos-grid { ... }` (kept) should be the next rule in the file.

- [ ] **Step 3: Remove the fecha/responsable/prioridad-wrapper rules exclusive to the carousel card**

Find this exact block (starts right after `.evento-card:hover .evento-descripcion`'s closing `}`, ends right before `.prioridad-badge`'s opening rule):

```css
.evento-fecha {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    color: #495057;
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.evento-card:hover .evento-fecha {
    transform: translateX(3px);
}

.fecha-icon {
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.evento-card:hover .fecha-icon {
    transform: scale(1.2);
}

.evento-responsable {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    color: #495057;
    font-size: 0.9rem;
    font-weight: 600;
    margin-top: 0.5rem;
    transition: all 0.3s ease;
}

.evento-card:hover .evento-responsable {
    transform: translateX(3px);
}

.responsable-icon {
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.evento-card:hover .responsable-icon {
    transform: scale(1.2);
}

.evento-prioridad {
    flex-shrink: 0;
    position: relative;
    z-index: 2;
}
```

Delete it entirely (replace with nothing). Immediately after this deletion, `.prioridad-badge { ... }` (kept, used by `GestionarEventos.jsx`) should be the next rule.

- [ ] **Step 4: Remove the inline-style-color attribute hacks**

**Note before editing:** these 3 lines have a trailing space before the opening `{` and before the `!important;` line ending, in the current file (confirmed with `cat -A`). If your edit tool requires an exact string match, copy the literal current bytes (`git show HEAD:resources/css/Dashboard.css | sed -n '715,723p'` or read the file directly) rather than retyping from scratch.

Find this exact block (starts right after `.evento-card:hover .prioridad-badge`'s closing `}`, ends right before `/* Indicador de rotación automática */`):

```css
.evento-card[style*='f44336'] .prioridad-badge { 
    background: linear-gradient(135deg, #e53935 0%, #c62828 100%) !important; 
}
.evento-card[style*='ff9800'] .prioridad-badge { 
    background: linear-gradient(135deg, #fbc02d 0%, #f57f17 100%) !important; 
}
.evento-card[style*='4caf50'] .prioridad-badge { 
    background: linear-gradient(135deg, #43a047 0%, #388e3c 100%) !important; 
}
```

Delete it entirely (replace with nothing). These only ever matched `eventos.jsx`'s inline `style={{ borderLeftColor: ... }}` on `.evento-card` (a hex-substring selector hack) — `GestionarEventos.jsx` never sets that inline style on its own `.evento-card` elements, so this is safe.

- [ ] **Step 5: Remove the rotation indicator rules**

Find this exact block:

```css
/* Indicador de rotación automática */
.auto-rotation-indicator {
    position: absolute;
    bottom: 1rem;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 0.5rem;
    z-index: 3;
}

.rotation-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.5);
    transition: all 0.3s ease;
}

.rotation-dot.active {
    background: #1976d2;
    transform: scale(1.3);
    box-shadow: 0 2px 8px rgba(25, 118, 210, 0.4);
}
```

Delete it entirely (replace with nothing). Immediately after this deletion, `/* Animación de entrada para las cards */` (kept — the `@keyframes` and its two `.evento-card` rules below it are shared with `GestionarEventos.jsx`) should be the next content.

- [ ] **Step 6: Append the new grid + alerts panel CSS**

Add this block to the end of `resources/css/Dashboard.css`:

```css

/* Layout de pestañas de fase: contenido principal + panel de alertas */
.dashboard-fase-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 2rem;
    align-items: start;
}

.dashboard-fase-main {
    min-width: 0;
}

/* Panel de alertas */
.alertas-panel {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
    position: sticky;
    top: 0;
}

.alertas-panel h3 {
    color: #1a237e;
    font-size: 1.2rem;
    margin-bottom: 1.2rem;
    font-weight: 700;
}

.alertas-sin-datos {
    color: #6c757d;
    font-size: 0.9rem;
    text-align: center;
    padding: 1.5rem 0;
}

.alertas-bloque {
    margin-bottom: 1.5rem;
}

.alertas-bloque:last-child {
    margin-bottom: 0;
}

.alertas-bloque h4 {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6c757d;
    font-weight: 700;
    margin-bottom: 0.8rem;
}

.alertas-lista {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.alerta-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem 0.7rem;
    background: rgba(248, 249, 250, 0.8);
    border-radius: 10px;
    border-left: 3px solid transparent;
    font-size: 0.82rem;
    transition: all 0.3s ease;
}

.alerta-item-riesgo {
    border-left-color: #e53935;
    cursor: pointer;
}

.alerta-item-riesgo:hover {
    background: rgba(227, 242, 253, 0.8);
}

.alerta-titulo {
    color: #1a237e;
    font-weight: 600;
    flex: 1;
}

.alerta-municipio {
    color: #6c757d;
    font-size: 0.75rem;
    white-space: nowrap;
}

.alerta-icono {
    font-size: 1.2rem;
    flex-shrink: 0;
}

.alerta-info {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.alerta-fecha {
    color: #6c757d;
    font-size: 0.72rem;
}

@media (max-width: 1200px) {
    .dashboard-fase-grid {
        grid-template-columns: 1fr;
    }

    .alertas-panel {
        position: static;
    }
}

/* .municipios-grid/.proyectos-grid ya tienen reglas responsive que ensanchan su
   minmax a partir de 1200px (pensadas para cuando ocupaban el ancho completo).
   Ahora viven dentro de la columna angosta .dashboard-fase-main junto al panel
   de alertas, así que ese ensanche deja entrar menos tarjetas por fila de las
   que cabrían, forzando scroll. Esta regla (más específica, gana sin !important)
   las mantiene en un minmax más chico solo cuando están dentro de ese contexto. */
.dashboard-fase-main .municipios-grid,
.dashboard-fase-main .proyectos-grid {
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.5rem;
}
```

(This last rule — `.dashboard-fase-main .municipios-grid`/`.proyectos-grid` — wasn't part of the original Step 6 block; it was added during Task 6's manual verification after measuring real scroll overflow at 1920x1080. See Task 6 Step 5 for the measurements.)

- [ ] **Step 7: Verify the build compiles**

Run: `npm run dev`
Expected: Mix/webpack build succeeds.

- [ ] **Step 8: Verify nothing GestionarEventos.jsx needs was removed**

Run: `grep -n "eventos-grid\|evento-card\|evento-icon\|evento-content\|evento-descripcion\|prioridad-badge" resources/css/Dashboard.css`
Expected: still finds these selectors (they must NOT have been deleted).

Run: `grep -n "eventos-header\|slider-controls\|slider-btn\|slider-indicator\|eventos-slider\|evento-fecha\|fecha-icon\|evento-responsable\|responsable-icon\|evento-prioridad\|auto-rotation-indicator\|rotation-dot" resources/css/Dashboard.css`
Expected: no output (empty) — these should all be gone.

- [ ] **Step 9: Commit**

```bash
git add resources/css/Dashboard.css
git commit -m "style: remove eventos-carousel-only CSS, add fase-grid and alerts panel styles"
```

---

## Task 6: Manual end-to-end verification

**Files:** none (verification only)

- [ ] **Step 1: Build and start the app**

Run: `npm run dev`, then `php -S 127.0.0.1:8015 -t public` from the repo root (NOT `php artisan serve` — this repo has a known, unrelated issue where its router script is sometimes missing on disk). Open `http://127.0.0.1:8015/`.

- [ ] **Step 2: Confirm the `/eventos` bug is fixed**

Log in with `admin@gestpro.local` / `Admin123!`. Confirm the Dashboard loads with no console error mentioning `eventos` or a 500 on `GET /GestPro/eventos` (check the browser Network tab or console).

- [ ] **Step 3: Confirm the AlertasPanel shows real data**

On the "Formulación" tab (or whichever fase tab is active by default), confirm a sidebar labeled "Alertas y Prioridades" appears to the right of the municipio cards, showing:
- A "⚠️ Proyectos en riesgo" block listing 5 projects (one per municipio, matching the KPI strip's "En riesgo: 5").
- A "🔴 Eventos de alta prioridad" block listing 3 events (from the 8 seeded, the ones with prioridad Alta and not cancelado/completado — 5 are Alta, but 1 of those is `cancelado` and 1 is `completado`, so 3 should show), sorted with the soonest date first.

- [ ] **Step 4: Confirm clicking a risk project opens its modal**

Click one of the rows under "Proyectos en riesgo". Confirm the same project-detail modal used elsewhere in the app opens, showing that project's data.

- [ ] **Step 5: Confirm the layout fits without scrolling on a large screen**

Resize the browser (or use dev tools device toolbar) to 1920x1080. Confirm the Formulación/Licitación/Ejecución tabs show the municipio cards and the alerts panel side-by-side without needing to scroll the page to see all the municipio cards for that fase (5 municipios should fit in the narrower grid column — if any wrapping causes a slight scroll, that's an acceptable trade-off of the `auto-fit` grid, not a regression to chase further in this task).

**Found and fixed during this step:** the pre-existing `@media (min-width: 1200px)` rule widened `.municipios-grid`'s `minmax` to 500px (tuned for when the grid spanned the full dashboard width). Nested inside the new, narrower `.dashboard-fase-main` column, that wider minmax let only 2 cards fit per row instead of 3, forcing an extra row and ~565px of scroll overflow at 1920x1080 — directly working against this task's own goal. Fixed with a more specific override (added to the end of Task 5's CSS, see that section) that keeps `.municipios-grid`/`.proyectos-grid` at their narrower ~320px minmax specifically when nested inside `.dashboard-fase-main`, cutting the overflow to ~175px. That residual ~175px is the "slight scroll" trade-off this step already anticipated — not chased further, since doing so would mean shrinking the already-approved `MunicipioCard` padding/font sizes, out of scope here.

- [ ] **Step 6: Confirm Estadísticas is unaffected**

Click "Estadísticas y Filtros". Confirm it still renders full-width (no sidebar), with its filters, 6 charts, and table all present and working as before.

- [ ] **Step 7: Confirm other fase tabs also show the grid**

Click "Licitación" and "Ejecución". Confirm each shows the same 2-column grid (municipio cards + AlertasPanel), and that the AlertasPanel's content stays the same regardless of which fase tab is active (it's driven by the full `proyectos` array, not the fase filter).

- [ ] **Step 8: Confirm `GestionarEventos.jsx`'s CSS dependency is intact**

`GestionarEventos.jsx` is not currently registered in `resources/js/app.jsx`'s router (`Routes`/`Route`) — it's unreachable from the UI today, so there's no live page to open and eyeball. The safety net here is Task 5 Step 8's grep check (already run): confirm again it passes, so if this component is ever wired up in the future, its `.eventos-grid`/`.evento-card`/`.evento-icon`/`.evento-content`/`.evento-descripcion`/`.prioridad-badge` styling is still there waiting for it.

Run: `grep -n "eventos-grid\|evento-card\|evento-icon\|evento-content\|evento-descripcion\|prioridad-badge" resources/css/Dashboard.css`
Expected: still finds these selectors.

- [ ] **Step 9: Run the full backend test suite one more time**

Run: `php artisan test`
Expected: `Tests: 36 passed`.
