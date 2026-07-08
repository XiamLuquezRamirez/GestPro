# Ubicación de Proyectos: Captura de Puntos en Mapa — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the WIP "Ubicación" tab in the project create/edit modal (`Parametros.jsx`) with an interactive Leaflet map where the user can click to add multiple geographic points per project, remove them by clicking, and persist them to a new `proyecto_ubicaciones` table via the existing `/guardarProyecto` endpoint.

**Architecture:** A new table + model mirror the existing `presupuesto_proyecto`/`PresupuestoProyecto` pattern exactly (delete-and-reinsert on save, attached to each project on read). The frontend adds one new piece of component state (`puntosUbicacion`, a sibling of the existing `detallesPresupuesto`/`contratos` state — not nested inside `formData`, following the same established pattern), a small map-click-listener helper component, and the actual Leaflet map JSX inside the already-existing (but empty) "ubicacion" tab conditional block.

**Tech Stack:** Laravel 12 (PHP) for the backend, React 18 for the frontend, `leaflet` + `react-leaflet` (new dependencies) for the interactive map with OpenStreetMap tiles. Laravel Mix/webpack build.

---

**Context for whoever executes this plan:** Full spec: `docs/superpowers/specs/2026-07-08-ubicacion-proyectos-mapa-design.md`. No frontend test suite exists — verification is `npm run dev` + manual browser checks. Backend has PHPUnit (`php artisan test`, 36 tests currently passing) — this plan's changes are purely additive (a new table nobody else references yet), so the full suite must stay green with no changes needed to existing tests.

**Confirmed before writing this plan:**
- The "Ubicación" tab button already exists in `Parametros.jsx` (added by the user before this plan was written); its content block is currently an empty placeholder: `{modalType === 'proyectos' && modalActiveTab === 'ubicacion' && (<><div className="form-group"></div></>)}`. **This file is a live file the user may keep editing between when this plan was written and when it's executed** — before Task 5's Step 2 (replacing that placeholder), re-read the file and confirm the placeholder still looks like this; if it's changed, stop and report rather than guessing how to adapt.
- `detallesPresupuesto` and `contratos` (the two existing "nested collection" fields for a project) are **not** stored inside `formData` — they're their own separate `useState([])` calls, populated in `handleEdit`, reset in `handleAddNew`/`handleCloseModal`, and merged into the `newProyecto` object only at submit time in `handleSubmit`. The new `puntosUbicacion` field follows this exact same pattern, not `formData.puntosUbicacion`.
- `resources/css/Parametros.css` has no JS import anywhere (`Parametros.jsx` doesn't import any CSS file directly) — it's pulled into the build via a plain CSS `@import './Parametros.css';` inside `resources/css/app.css`, which Laravel Mix compiles as a separate entry point (`webpack.mix.js`'s `mix.postCss('resources/css/app.css', 'css', ...)`). New CSS for this feature goes in `Parametros.css`, not in a new file, and needs no new import statement anywhere.
- `app/Models/Evento.php` and `app/Models/PresupuestoProyecto.php` both already use the project's established `xxxRel()` naming convention for a `belongsTo` whose FK column is a single word matching what the method would naturally be called (e.g. `proyectoRel()`, not `proyecto()`, because the column is also literally named `proyecto`). The new `ProyectoUbicacion` model follows this too.
- `Proyecto::presupuestoComponentes()`/`contratos()`/`eventos()` are existing Eloquent `hasMany` relations that the controller doesn't actually use (it queries via raw `DB::table` throughout) but that exist for consistency/future use. The new `puntosUbicacion()` relation on `Proyecto` follows this same existing pattern.

---

## Task 1: Migration and model for `proyecto_ubicaciones`

**Files:**
- Create: `database/migrations/2026_07_08_100000_create_proyecto_ubicaciones_table.php`
- Create: `app/Models/ProyectoUbicacion.php`
- Modify: `app/Models/Proyecto.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto')->constrained('proyectos')->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_ubicaciones');
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `2026_07_08_100000_create_proyecto_ubicaciones_table ... DONE`

- [ ] **Step 3: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyectoUbicacion extends Model
{
    public $timestamps = false;

    protected $fillable = ['proyecto', 'lat', 'lng'];
    protected $casts = ['lat' => 'decimal:7', 'lng' => 'decimal:7'];

    // proyectoRel() not proyecto(): the FK column is also called `proyecto` (see
    // PresupuestoProyecto::proyectoRel() for the same pattern in this project).
    public function proyectoRel(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto');
    }
}
```

- [ ] **Step 4: Add the inverse relation on `Proyecto`**

Find this exact block in `app/Models/Proyecto.php`:

```php
    public function presupuestoComponentes(): HasMany
    {
        return $this->hasMany(PresupuestoProyecto::class, 'proyecto');
    }
```

Replace with:

```php
    public function presupuestoComponentes(): HasMany
    {
        return $this->hasMany(PresupuestoProyecto::class, 'proyecto');
    }

    public function puntosUbicacion(): HasMany
    {
        return $this->hasMany(ProyectoUbicacion::class, 'proyecto');
    }
```

- [ ] **Step 5: Verify with tinker**

Run: `php artisan tinker --execute="echo Schema::hasTable('proyecto_ubicaciones') ? 'table exists' : 'MISSING';"`
Expected: `table exists`

- [ ] **Step 6: Verify the full test suite still passes**

Run: `php artisan test`
Expected: `Tests: 36 passed`.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_08_100000_create_proyecto_ubicaciones_table.php app/Models/ProyectoUbicacion.php app/Models/Proyecto.php
git commit -m "feat: add proyecto_ubicaciones table and ProyectoUbicacion model"
```

---

## Task 2: Backend — persist and return location points

**Files:**
- Modify: `app/Http/Controllers/ProyectoController.php`

- [ ] **Step 1: Insert points when creating a project**

Find this exact block:

```php
                if (isset($proyecto['componentesPresupuesto']) && count($proyecto['componentesPresupuesto']) > 0) {
                    foreach ($proyecto['componentesPresupuesto'] as $presupuesto) {
                        DB::table('presupuesto_proyecto')->insert([
                            'proyecto' => $proyectoId,
                            'componente' => $presupuesto['descripcionComponente'],
                            'valor' => $presupuesto['valor']
                        ]);
                    }
                }

            } else {
```

Replace with:

```php
                if (isset($proyecto['componentesPresupuesto']) && count($proyecto['componentesPresupuesto']) > 0) {
                    foreach ($proyecto['componentesPresupuesto'] as $presupuesto) {
                        DB::table('presupuesto_proyecto')->insert([
                            'proyecto' => $proyectoId,
                            'componente' => $presupuesto['descripcionComponente'],
                            'valor' => $presupuesto['valor']
                        ]);
                    }
                }

                if (isset($proyecto['puntosUbicacion']) && count($proyecto['puntosUbicacion']) > 0) {
                    foreach ($proyecto['puntosUbicacion'] as $punto) {
                        DB::table('proyecto_ubicaciones')->insert([
                            'proyecto' => $proyectoId,
                            'lat' => $punto['lat'],
                            'lng' => $punto['lng']
                        ]);
                    }
                }

            } else {
```

- [ ] **Step 2: Delete-and-reinsert points when editing a project**

Find this exact block:

```php
                DB::table('presupuesto_proyecto')->where('proyecto', $proyecto['id'])->delete();
                if (isset($proyecto['componentesPresupuesto']) && count($proyecto['componentesPresupuesto']) > 0) {
                    foreach ($proyecto['componentesPresupuesto'] as $presupuesto) {
                        DB::table('presupuesto_proyecto')->insert([
                            'proyecto' => $proyecto['id'],
                            'componente' => $presupuesto['descripcionComponente'],
                            'valor' => $presupuesto['valor']
                        ]);
                    }
                }

            }
```

Replace with:

```php
                DB::table('presupuesto_proyecto')->where('proyecto', $proyecto['id'])->delete();
                if (isset($proyecto['componentesPresupuesto']) && count($proyecto['componentesPresupuesto']) > 0) {
                    foreach ($proyecto['componentesPresupuesto'] as $presupuesto) {
                        DB::table('presupuesto_proyecto')->insert([
                            'proyecto' => $proyecto['id'],
                            'componente' => $presupuesto['descripcionComponente'],
                            'valor' => $presupuesto['valor']
                        ]);
                    }
                }

                DB::table('proyecto_ubicaciones')->where('proyecto', $proyecto['id'])->delete();
                if (isset($proyecto['puntosUbicacion']) && count($proyecto['puntosUbicacion']) > 0) {
                    foreach ($proyecto['puntosUbicacion'] as $punto) {
                        DB::table('proyecto_ubicaciones')->insert([
                            'proyecto' => $proyecto['id'],
                            'lat' => $punto['lat'],
                            'lng' => $punto['lng']
                        ]);
                    }
                }

            }
```

- [ ] **Step 3: Attach points to each project in `proyectos()`**

Find this exact block:

```php
            // Cargar anexos para cada contrato
            foreach ($contratos as $contrato) {
                $anexos = DB::table('anexos_contratos')
                    ->select('id', 'descripcion', 'nombre_archivo', 'ruta_archivo', 'fecha')
                    ->where('contrato_id', $contrato->id)
                    ->get();
                $contrato->anexos = $anexos;
            }

        
            
            $proyecto->contratos = $contratos;
        }
```

Replace with:

```php
            // Cargar anexos para cada contrato
            foreach ($contratos as $contrato) {
                $anexos = DB::table('anexos_contratos')
                    ->select('id', 'descripcion', 'nombre_archivo', 'ruta_archivo', 'fecha')
                    ->where('contrato_id', $contrato->id)
                    ->get();
                $contrato->anexos = $anexos;
            }

        
            
            $proyecto->contratos = $contratos;

            $proyecto->puntosUbicacion = DB::table('proyecto_ubicaciones')
                ->select('id', 'lat', 'lng')
                ->where('proyecto', $proyecto->id)
                ->get();
        }
```

- [ ] **Step 4: Verify the full test suite still passes**

Run: `php artisan test`
Expected: `Tests: 36 passed`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ProyectoController.php
git commit -m "feat: persist and return proyecto_ubicaciones in guardarProyecto/proyectos"
```

---

## Task 3: Install Leaflet and fix its default marker icons for webpack

**Files:**
- Modify: `package.json`, `package-lock.json` (via `npm install`)
- Modify: `resources/js/components/Parametros.jsx` (imports and icon fix only — no UI yet)

- [ ] **Step 1: Install the dependencies**

Run: `npm install leaflet@^1.9 react-leaflet@^4 --save`
Expected: both packages appear under `"dependencies"` in `package.json`.

- [ ] **Step 2: Add the imports and the marker-icon fix**

Find this exact block at the top of `resources/js/components/Parametros.jsx`:

```jsx
import React, { useState, useEffect } from 'react';
import Header from './Header';
import axios from '../axios';
import { faPlus, faTrash, faSave, faTimes, faCalculator } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
import EmojiPicker from 'emoji-picker-react';

const Parametros = ({ user, onLogout }) => {
```

Replace with:

```jsx
import React, { useState, useEffect } from 'react';
import Header from './Header';
import axios from '../axios';
import { faPlus, faTrash, faSave, faTimes, faCalculator } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
import EmojiPicker from 'emoji-picker-react';
import { MapContainer, TileLayer, Marker, useMapEvents } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Leaflet's default marker icon references image URLs that bundlers (webpack/Mix
// included) don't resolve automatically. This re-points them at the actual
// bundled asset URLs — a standard, documented fix for using Leaflet with webpack.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

// Componente auxiliar: escucha clics en el mapa y reporta la coordenada al padre.
// Debe vivir dentro de <MapContainer> — el hook useMapEvents solo funciona ahí.
const CapturadorClicMapa = ({ onAgregarPunto }) => {
    useMapEvents({
        click(e) {
            onAgregarPunto({ lat: e.latlng.lat, lng: e.latlng.lng });
        },
    });
    return null;
};

const Parametros = ({ user, onLogout }) => {
```

- [ ] **Step 3: Verify the build compiles**

Run: `npm run dev`
Expected: succeeds. (The map isn't rendered anywhere yet — this only confirms the new imports and the icon-fix code are valid.)

- [ ] **Step 4: Commit**

```bash
git add package.json package-lock.json resources/js/components/Parametros.jsx
git commit -m "chore: add leaflet/react-leaflet and fix default marker icons for webpack"
```

---

## Task 4: Wire the `puntosUbicacion` state (declare, reset, load, submit)

**Files:**
- Modify: `resources/js/components/Parametros.jsx`

- [ ] **Step 1: Declare the state**

Find this exact block:

```jsx
    const [detallesPresupuesto, setDetallesPresupuesto] = useState([]);
```

Replace with:

```jsx
    const [detallesPresupuesto, setDetallesPresupuesto] = useState([]);
    const [puntosUbicacion, setPuntosUbicacion] = useState([]);
```

- [ ] **Step 2: Load existing points when editing a project**

Find this exact block:

```jsx
        // Cargar contratos si es un proyecto
        if (type === 'proyectos' && item.contratos) {
            setContratos(item.contratos);
        } else {
            setContratos([]);
        }

        setModalActiveTab('datos');
```

Replace with:

```jsx
        // Cargar contratos si es un proyecto
        if (type === 'proyectos' && item.contratos) {
            setContratos(item.contratos);
        } else {
            setContratos([]);
        }

        // Cargar puntos de ubicación si es un proyecto
        if (type === 'proyectos' && item.puntosUbicacion) {
            setPuntosUbicacion(
                item.puntosUbicacion.map(punto => ({
                    lat: parseFloat(punto.lat),
                    lng: parseFloat(punto.lng)
                }))
            );
        } else {
            setPuntosUbicacion([]);
        }

        setModalActiveTab('datos');
```

- [ ] **Step 3: Reset when opening the modal for a new project**

Find this exact block:

```jsx
        setDetallesPresupuesto([]);
        setContratos([]); // Resetear contratos al agregar nuevo
        setModalActiveTab('datos');
        setShowModal(true);
    };
```

Replace with:

```jsx
        setDetallesPresupuesto([]);
        setContratos([]); // Resetear contratos al agregar nuevo
        setPuntosUbicacion([]);
        setModalActiveTab('datos');
        setShowModal(true);
    };
```

- [ ] **Step 4: Reset when closing the modal**

Find this exact block:

```jsx
        setDetallesPresupuesto([]);
        setContratos([]); // Resetear contratos al cerrar modal
        setModalActiveTab('datos');
    };
```

Replace with:

```jsx
        setDetallesPresupuesto([]);
        setContratos([]); // Resetear contratos al cerrar modal
        setPuntosUbicacion([]);
        setModalActiveTab('datos');
    };
```

- [ ] **Step 5: Include the points when submitting a project**

Find this exact block:

```jsx
            const newProyecto = {
                ...formData,
                id: editingItem ? editingItem.id : Date.now(),
                presupuesto: formatCurrency(presupuestoTotal),
                componentesPresupuesto: detallesPresupuesto,
                contratos: contratos,
            };
```

Replace with:

```jsx
            const newProyecto = {
                ...formData,
                id: editingItem ? editingItem.id : Date.now(),
                presupuesto: formatCurrency(presupuestoTotal),
                componentesPresupuesto: detallesPresupuesto,
                contratos: contratos,
                puntosUbicacion: puntosUbicacion,
            };
```

- [ ] **Step 6: Verify the build compiles**

Run: `npm run dev`
Expected: succeeds. (No UI uses `puntosUbicacion` yet — this only confirms the state wiring itself is valid.)

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/Parametros.jsx
git commit -m "feat: wire puntosUbicacion state through load/reset/submit"
```

---

## Task 5: Build the map UI in the "Ubicación" tab

**Files:**
- Modify: `resources/js/components/Parametros.jsx`

- [ ] **Step 1: Re-confirm the placeholder hasn't changed**

Read the current content of the block starting with `{modalType === 'proyectos' && modalActiveTab === 'ubicacion' &&` in `resources/js/components/Parametros.jsx`. If it no longer looks like the block shown in Step 2 below (even in whitespace), STOP and report (BLOCKED or NEEDS_CONTEXT) rather than guessing how to adapt — this file was mid-edit by the user when this plan was written.

- [ ] **Step 2: Replace the placeholder with the map**

Find this exact block:

```jsx
                            {/* */}
                            {modalType === 'proyectos' && modalActiveTab === 'ubicacion' && (
                                <>
                                    <div className="form-group">
                                        
                                    </div>
                                    
                                </>
                            )}
```

Replace with:

```jsx
                            {modalType === 'proyectos' && modalActiveTab === 'ubicacion' && (
                                <>
                                    <p className="mapa-ubicacion-instrucciones">
                                        Haz clic en el mapa para agregar un punto. Haz clic en un punto existente para quitarlo.
                                    </p>
                                    <div className="mapa-ubicacion-container">
                                        <MapContainer center={[6.2442, -75.5812]} zoom={9} style={{ height: '400px', width: '100%' }}>
                                            <TileLayer
                                                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                                                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                                            />
                                            <CapturadorClicMapa onAgregarPunto={(punto) => setPuntosUbicacion(prev => [...prev, punto])} />
                                            {puntosUbicacion.map((punto, index) => (
                                                <Marker
                                                    key={index}
                                                    position={[punto.lat, punto.lng]}
                                                    eventHandlers={{
                                                        click: () => setPuntosUbicacion(prev => prev.filter((_, i) => i !== index)),
                                                    }}
                                                />
                                            ))}
                                        </MapContainer>
                                    </div>
                                    <div className="mapa-ubicacion-info">
                                        <span>{puntosUbicacion.length} punto(s) seleccionado(s)</span>
                                        <button type="button" className="btn-limpiar-puntos" onClick={() => setPuntosUbicacion([])}>
                                            Limpiar todos los puntos
                                        </button>
                                    </div>
                                </>
                            )}
```

- [ ] **Step 3: Verify the build compiles**

Run: `npm run dev`
Expected: succeeds with no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Parametros.jsx
git commit -m "feat: add interactive Leaflet map to the Ubicacion tab"
```

---

## Task 6: CSS for the map container

**Files:**
- Modify: `resources/css/Parametros.css`

- [ ] **Step 1: Append the new styles**

Add this block to the end of `resources/css/Parametros.css`:

```css

.mapa-ubicacion-instrucciones {
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 0.75rem;
}

.mapa-ubicacion-container {
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #ddd;
    margin-bottom: 0.75rem;
}

.mapa-ubicacion-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.btn-limpiar-puntos {
    padding: 0.5rem 1rem;
    border: 1px solid #e53935;
    background: #fff;
    color: #e53935;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 500;
}

.btn-limpiar-puntos:hover {
    background: #e53935;
    color: #fff;
}
```

- [ ] **Step 2: Verify the build compiles**

Run: `npm run dev`
Expected: succeeds.

- [ ] **Step 3: Commit**

```bash
git add resources/css/Parametros.css
git commit -m "style: add Ubicacion map container and clear-points button styles"
```

---

## Task 7: Manual end-to-end verification

**Files:** none (verification only)

- [ ] **Step 1: Build and start the app**

Run: `npm run dev`, then `php -S 127.0.0.1:8017 -t public` from the repo root (NOT `php artisan serve` — this repo has a known, unrelated issue where its router script is sometimes missing on disk). Open `http://127.0.0.1:8017/`.

- [ ] **Step 2: Log in and open the project modal**

Log in with `admin@gestpro.local` / `Admin123!`. Navigate to "Gestión de Proyectos" (`/parametros`), click "Agregar" (or edit any existing project). Click the "📍 Ubicación" tab. Confirm the map renders with visible tiles (requires internet access to `tile.openstreetmap.org` — if the execution environment has no internet egress, the map container/click behavior can still be verified even if tiles render blank; note this explicitly rather than treating it as a failure).

- [ ] **Step 3: Add and remove points**

Click 3 different spots on the map. Confirm a marker appears at each, and the counter below reads "3 punto(s) seleccionado(s)". Click one of the markers directly. Confirm it disappears and the counter updates to "2 punto(s) seleccionado(s)". Click "Limpiar todos los puntos". Confirm all markers disappear and the counter reads "0 punto(s) seleccionado(s)".

- [ ] **Step 4: Save and verify persistence**

Add 2 points, fill in the required fields on the "Datos del Proyecto" tab (nombre, municipio), and save. Confirm the success message appears. Run: `php artisan tinker --execute="echo DB::table('proyecto_ubicaciones')->count();"` — expected: at least 2 (more if other projects already had points from earlier steps in this same verification pass).

- [ ] **Step 5: Confirm points reload when editing**

Reopen the same project you just saved in Step 4, click "📍 Ubicación". Confirm the 2 markers you saved are shown on the map again, and the counter reads "2 punto(s) seleccionado(s)".

- [ ] **Step 6: Run the full backend test suite one more time**

Run: `php artisan test`
Expected: `Tests: 36 passed`.
