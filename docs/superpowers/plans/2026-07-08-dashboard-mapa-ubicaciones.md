# Dashboard: mapa de ubicaciones + resumen por fase — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reemplazar el contenido de las pestañas de fase del Dashboard (grilla de tarjetas por municipio + drill-down + panel de alertas) por un mapa de ubicaciones con clustering de puntos reales, un panel de resumen de la fase, una tabla de distribución por municipio, y un panel de próximos eventos.

**Architecture:** Todo el trabajo es de frontend (4 componentes React nuevos + reestructuración de `Dashboard.jsx` + CSS) salvo una extensión mínima y aditiva de `ProyectoController::eventos()` para incluir el municipio del proyecto. Ningún endpoint nuevo: todo se deriva en el cliente de `/proyectos` (ya trae `puntosUbicacion`, `municipio`, `progreso`, `descripcion_estado`) y `/eventos`.

**Tech Stack:** Laravel 12 (PHP), React 18 (sin TypeScript), `react-leaflet@4.2.1` + `leaflet@1.9.4` (ya instalados), nueva dependencia `react-leaflet-cluster@3.1.1` (verificado compatible con React 18 / react-leaflet 4 — las versiones 4.x de ese paquete requieren React 19 y NO deben usarse).

**Spec:** `docs/superpowers/specs/2026-07-08-dashboard-mapa-ubicaciones-design.md`

---

### Task 1: Módulo compartido para el fix del ícono de Leaflet

Hoy el fix del ícono de marcador de Leaflet para webpack vive duplicado inline en `Parametros.jsx`. Este plan agrega un segundo mapa (`MapaUbicaciones.jsx`), así que antes de duplicarlo de nuevo, se extrae a un módulo compartido.

**Files:**
- Create: `resources/js/leafletIconFix.js`
- Modify: `resources/js/components/Parametros.jsx:8-22`

- [ ] **Step 1: Crear el módulo compartido**

Crear `resources/js/leafletIconFix.js`:

```js
import L from 'leaflet';
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
```

- [ ] **Step 2: Reemplazar el bloque inline en Parametros.jsx por el import**

En `resources/js/components/Parametros.jsx`, las líneas 8-22 hoy son:

```jsx
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
```

Reemplazar por:

```jsx
import { MapContainer, TileLayer, Marker, useMapEvents } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import '../leafletIconFix';
```

(El resto del archivo, incluido `CapturadorClicMapa` justo debajo, no cambia.)

- [ ] **Step 3: Verificar que compila**

Run: `npm run build`
Expected: compila sin errores (sin referencias rotas a `L`, `markerIcon2x`, etc. — verifica que ningún otro lugar de `Parametros.jsx` usa `L` directamente; si lo usa, no se puede quitar el import de `leaflet`, pero hoy no lo usa fuera de ese bloque).

- [ ] **Step 4: Commit**

```bash
git add resources/js/leafletIconFix.js resources/js/components/Parametros.jsx
git commit -m "refactor: extract Leaflet marker icon fix to a shared module"
```

---

### Task 2: Backend — incluir el municipio del proyecto en `/eventos`

**Files:**
- Modify: `app/Http/Controllers/ProyectoController.php:82-109`

- [ ] **Step 1: Extender el select y agregar el join**

En `app/Http/Controllers/ProyectoController.php`, el método `eventos()` hoy es:

```php
    public function eventos()
    {
        $eventos = DB::table('eventos')
            ->leftJoin('proyectos', 'eventos.proyecto', 'proyectos.id')
            ->leftJoin('tipo_eventos', 'eventos.tipo_eventos', 'tipo_eventos.id')
            ->leftJoin('prioridades', 'eventos.prioridad', 'prioridades.id')
            ->leftJoin('responsable', 'eventos.responsable', 'responsable.id')
            ->select(
                'eventos.id',
                'eventos.titulo',
                'eventos.descripcion',
                'eventos.fecha',
                'eventos.tipo_eventos',
                'eventos.prioridad',
                'eventos.responsable',
                DB::raw('eventos.estado_evento as estado'),
                'tipo_eventos.icono',
                'prioridades.color',
                'prioridades.nombre',
                DB::raw('proyectos.nombre as descripcion_proyecto'),
                DB::raw('tipo_eventos.nombre as descripcion_tipo_evento'),
                DB::raw('CONCAT(prioridades.color, " ", prioridades.nombre) as descripcion_prioridad'),
                DB::raw('responsable.nombre as descripcion_responsable')
            )
            ->orderBy('eventos.fecha', 'desc')
            ->get();
        return response()->json($eventos);
    }
```

Reemplazar por:

```php
    public function eventos()
    {
        $eventos = DB::table('eventos')
            ->leftJoin('proyectos', 'eventos.proyecto', 'proyectos.id')
            ->leftJoin('tipo_eventos', 'eventos.tipo_eventos', 'tipo_eventos.id')
            ->leftJoin('prioridades', 'eventos.prioridad', 'prioridades.id')
            ->leftJoin('responsable', 'eventos.responsable', 'responsable.id')
            ->leftJoin('municipios', 'proyectos.municipio', 'municipios.codigo')
            ->select(
                'eventos.id',
                'eventos.titulo',
                'eventos.descripcion',
                'eventos.fecha',
                'eventos.proyecto',
                'eventos.tipo_eventos',
                'eventos.prioridad',
                'eventos.responsable',
                DB::raw('eventos.estado_evento as estado'),
                'tipo_eventos.icono',
                'prioridades.color',
                'prioridades.nombre',
                DB::raw('proyectos.nombre as descripcion_proyecto'),
                DB::raw('municipios.nombre as descripcion_municipio'),
                DB::raw('tipo_eventos.nombre as descripcion_tipo_evento'),
                DB::raw('CONCAT(prioridades.color, " ", prioridades.nombre) as descripcion_prioridad'),
                DB::raw('responsable.nombre as descripcion_responsable')
            )
            ->orderBy('eventos.fecha', 'desc')
            ->get();
        return response()->json($eventos);
    }
```

- [ ] **Step 2: Verificar que la suite de tests sigue en verde**

Run: `php artisan test`
Expected: `Tests: 36 passed`

- [ ] **Step 3: Verificar manualmente el nuevo campo**

Run (con el servidor de pruebas ya usado en este proyecto, ej. `php -S 127.0.0.1:8017 -t public` en otra terminal, y sesión autenticada, o vía `php artisan tinker`):

```bash
php artisan tinker --execute="echo json_encode(DB::table('eventos')->leftJoin('proyectos', 'eventos.proyecto', 'proyectos.id')->leftJoin('municipios', 'proyectos.municipio', 'municipios.codigo')->select('eventos.id', DB::raw('municipios.nombre as descripcion_municipio'))->first());"
```

Expected: un JSON con `descripcion_municipio` no nulo si el evento de prueba tiene un proyecto con municipio asignado.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/ProyectoController.php
git commit -m "feat: include project municipio in the /eventos endpoint"
```

---

### Task 3: Instalar react-leaflet-cluster y crear `MapaUbicaciones.jsx`

**Files:**
- Modify: `package.json`, `package-lock.json` (vía npm install)
- Create: `resources/js/components/MapaUbicaciones.jsx`

- [ ] **Step 1: Instalar la dependencia (versión exacta, no la última)**

Run: `npm install react-leaflet-cluster@3.1.1`

Expected: se agrega `react-leaflet-cluster` (y su dependencia interna `leaflet.markercluster`) a `package.json`/`package-lock.json`. **No instalar la versión más reciente (4.x)** — esa requiere React 19 y `react-leaflet` 5, incompatible con las versiones ya instaladas en este proyecto (`react@18.2.0`, `react-leaflet@4.2.1`).

- [ ] **Step 2: Crear el componente del mapa**

Crear `resources/js/components/MapaUbicaciones.jsx`:

```jsx
import React, { useMemo, useRef, useEffect } from 'react';
import { MapContainer, TileLayer, Marker, useMap } from 'react-leaflet';
import MarkerClusterGroup from 'react-leaflet-cluster';
import 'leaflet/dist/leaflet.css';
import 'react-leaflet-cluster/dist/assets/MarkerCluster.css';
import 'react-leaflet-cluster/dist/assets/MarkerCluster.Default.css';
import '../leafletIconFix';

const CENTRO_POR_DEFECTO = [6.2442, -75.5812];
const ZOOM_POR_DEFECTO = 9;

// Encuadra el mapa a todos los puntos existentes la primera vez que llegan datos.
// No se repite en renders posteriores para no pelear con la navegación del usuario.
const AjustarVistaInicial = ({ puntos }) => {
    const map = useMap();
    const ajustadoRef = useRef(false);

    useEffect(() => {
        if (!ajustadoRef.current && puntos.length > 0) {
            map.fitBounds(puntos.map(p => [p.lat, p.lng]), { maxZoom: 13, padding: [40, 40] });
            ajustadoRef.current = true;
        }
    }, [puntos]);

    return null;
};

// Encuadra el mapa en los puntos de un municipio específico cuando se selecciona
// una fila en la tabla "Distribución por municipio".
const CentradorMunicipio = ({ municipio, puntos }) => {
    const map = useMap();

    useEffect(() => {
        if (!municipio) return;
        const puntosDelMunicipio = puntos.filter(p => p.proyecto.descripcion_municipio === municipio);
        if (puntosDelMunicipio.length > 0) {
            map.fitBounds(puntosDelMunicipio.map(p => [p.lat, p.lng]), { maxZoom: 14, padding: [40, 40] });
        }
    }, [municipio]);

    return null;
};

const MapaUbicaciones = ({ proyectos, onProyectoClick, municipioResaltado }) => {
    const puntos = useMemo(() => (
        proyectos.flatMap(proyecto =>
            (proyecto.puntosUbicacion || []).map(punto => ({
                lat: parseFloat(punto.lat),
                lng: parseFloat(punto.lng),
                proyecto,
            }))
        )
    ), [proyectos]);

    return (
        <div className="dashboard-mapa-container">
            <MapContainer center={CENTRO_POR_DEFECTO} zoom={ZOOM_POR_DEFECTO} style={{ height: '420px', width: '100%' }}>
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                <AjustarVistaInicial puntos={puntos} />
                <CentradorMunicipio municipio={municipioResaltado} puntos={puntos} />
                <MarkerClusterGroup chunkedLoading>
                    {puntos.map((punto, index) => (
                        <Marker
                            key={index}
                            position={[punto.lat, punto.lng]}
                            eventHandlers={{ click: () => onProyectoClick(punto.proyecto) }}
                        />
                    ))}
                </MarkerClusterGroup>
            </MapContainer>
        </div>
    );
};

export default MapaUbicaciones;
```

- [ ] **Step 3: Verificar que compila**

Run: `npm run build`
Expected: compila sin errores.

- [ ] **Step 4: Commit**

```bash
git add package.json package-lock.json resources/js/components/MapaUbicaciones.jsx
git commit -m "feat: add MapaUbicaciones component with point clustering"
```

---

### Task 4: Componente `DistribucionMunicipios.jsx`

**Files:**
- Create: `resources/js/components/DistribucionMunicipios.jsx`

- [ ] **Step 1: Crear el componente**

Crear `resources/js/components/DistribucionMunicipios.jsx`:

```jsx
import React, { useMemo } from 'react';

const formatearPresupuesto = (valor) => '$' + Math.round(valor / 1_000_000).toLocaleString('es-CO') + ' M';

const DistribucionMunicipios = ({ proyectos, onMunicipioClick }) => {
    const filas = useMemo(() => {
        const porMunicipio = proyectos.reduce((acc, proyecto) => {
            const municipio = proyecto.descripcion_municipio || 'Sin municipio';
            if (!acc[municipio]) {
                acc[municipio] = [];
            }
            acc[municipio].push(proyecto);
            return acc;
        }, {});

        return Object.entries(porMunicipio).map(([municipio, proyectosDelMunicipio]) => {
            const total = proyectosDelMunicipio.length;
            const presupuestoTotal = proyectosDelMunicipio.reduce((sum, p) => sum + (parseFloat(p.presupuesto) || 0), 0);
            const avancePromedio = total > 0
                ? Math.round(proyectosDelMunicipio.reduce((sum, p) => sum + (parseInt(p.progreso, 10) || 0), 0) / total)
                : 0;
            const enRiesgo = proyectosDelMunicipio.filter(p => p.descripcion_estado === 'Con retraso').length;

            return { municipio, total, presupuestoTotal, avancePromedio, enRiesgo };
        });
    }, [proyectos]);

    return (
        <div className="estadisticas-tabla-section">
            <h3>Distribución por municipio</h3>
            {filas.length === 0 ? (
                <p className="tabla-sin-datos">No hay proyectos en esta fase todavía.</p>
            ) : (
                <table className="estadisticas-tabla">
                    <thead>
                        <tr>
                            <th>Municipio</th>
                            <th>Proyectos</th>
                            <th>Presupuesto</th>
                            <th>Avance promedio</th>
                            <th>Estado general</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filas.map(fila => (
                            <tr key={fila.municipio} onClick={() => onMunicipioClick(fila.municipio)} style={{ cursor: 'pointer' }}>
                                <td>{fila.municipio}</td>
                                <td>{fila.total}</td>
                                <td>{formatearPresupuesto(fila.presupuestoTotal)}</td>
                                <td>{fila.avancePromedio}%</td>
                                <td>
                                    <span
                                        className="tabla-estado-pastilla"
                                        style={{ backgroundColor: fila.enRiesgo > 0 ? '#e53935' : '#43a047' }}
                                    >
                                        {fila.enRiesgo > 0 ? `▲ ${fila.enRiesgo} en riesgo` : '● Buen ritmo'}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
};

export default DistribucionMunicipios;
```

- [ ] **Step 2: Verificar que compila**

Run: `npm run build`
Expected: compila sin errores (el componente aún no se usa desde ningún lado — eso es normal hasta la Tarea 7 — pero debe transpilar sin errores de sintaxis).

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/DistribucionMunicipios.jsx
git commit -m "feat: add DistribucionMunicipios table component"
```

---

### Task 5: Componente `ResumenFase.jsx`

**Files:**
- Create: `resources/js/components/ResumenFase.jsx`

- [ ] **Step 1: Crear el componente**

Crear `resources/js/components/ResumenFase.jsx`:

```jsx
import React, { useMemo } from 'react';

const formatearPresupuesto = (valor) => '$' + Math.round(valor / 1_000_000).toLocaleString('es-CO') + ' M';

const ResumenFase = ({ proyectos, nombreFase }) => {
    const resumen = useMemo(() => {
        const total = proyectos.length;
        const presupuestoTotal = proyectos.reduce((sum, p) => sum + (parseFloat(p.presupuesto) || 0), 0);
        const avancePromedio = total > 0
            ? Math.round(proyectos.reduce((sum, p) => sum + (parseInt(p.progreso, 10) || 0), 0) / total)
            : 0;
        const enRiesgo = proyectos.filter(p => p.descripcion_estado === 'Con retraso').length;

        return { total, presupuestoTotal, avancePromedio, enRiesgo };
    }, [proyectos]);

    return (
        <aside className="resumen-fase-panel">
            <h3>Resumen de {nombreFase}</h3>
            <div className="resumen-fase-stat">
                <span className="resumen-fase-valor">{resumen.total}</span>
                <span className="resumen-fase-etiqueta">Proyectos en {nombreFase.toLowerCase()}</span>
            </div>
            <div className="resumen-fase-stat">
                <span className="resumen-fase-valor">{formatearPresupuesto(resumen.presupuestoTotal)}</span>
                <span className="resumen-fase-etiqueta">Presupuesto total</span>
            </div>
            <div className="resumen-fase-stat">
                <span className="resumen-fase-valor">{resumen.avancePromedio}%</span>
                <span className="resumen-fase-etiqueta">Avance promedio</span>
            </div>
            <div className="resumen-fase-stat">
                <span className={`resumen-fase-valor${resumen.enRiesgo > 0 ? ' en-riesgo' : ''}`}>{resumen.enRiesgo}</span>
                <span className="resumen-fase-etiqueta">En riesgo</span>
            </div>
        </aside>
    );
};

export default ResumenFase;
```

- [ ] **Step 2: Verificar que compila**

Run: `npm run build`
Expected: compila sin errores.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/ResumenFase.jsx
git commit -m "feat: add ResumenFase summary panel component"
```

---

### Task 6: Componente `ProximosEventos.jsx`

**Files:**
- Create: `resources/js/components/ProximosEventos.jsx`

- [ ] **Step 1: Crear el componente**

Crear `resources/js/components/ProximosEventos.jsx`:

```jsx
import React, { useState, useEffect, useMemo } from 'react';
import axios from 'axios';

const MESES_CORTOS = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

const formatearFechaCorta = (fechaStr) => {
    const fecha = new Date(fechaStr);
    return {
        dia: String(fecha.getDate()).padStart(2, '0'),
        mes: MESES_CORTOS[fecha.getMonth()],
    };
};

const ProximosEventos = () => {
    const [eventos, setEventos] = useState([]);

    useEffect(() => {
        axios.get('/eventos').then(response => setEventos(response.data));
    }, []);

    const proximosEventos = useMemo(() => {
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);

        return eventos
            .filter(e => new Date(e.fecha) >= hoy)
            .sort((a, b) => new Date(a.fecha) - new Date(b.fecha))
            .slice(0, 5);
    }, [eventos]);

    return (
        <aside className="proximos-eventos-panel">
            <h3>Próximos eventos</h3>
            {proximosEventos.length === 0 ? (
                <p className="tabla-sin-datos">No hay eventos próximos.</p>
            ) : (
                <ul className="proximos-eventos-lista">
                    {proximosEventos.map(evento => {
                        const { dia, mes } = formatearFechaCorta(evento.fecha);
                        return (
                            <li key={evento.id} className="proximo-evento-item">
                                <div className="proximo-evento-fecha">
                                    <span className="proximo-evento-dia">{dia}</span>
                                    <span className="proximo-evento-mes">{mes}</span>
                                </div>
                                <div className="proximo-evento-info">
                                    <span className="proximo-evento-titulo">{evento.titulo}</span>
                                    <span className="proximo-evento-proyecto">{evento.descripcion_proyecto}</span>
                                </div>
                                {evento.descripcion_municipio && (
                                    <span className="proximo-evento-municipio">{evento.descripcion_municipio}</span>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </aside>
    );
};

export default ProximosEventos;
```

- [ ] **Step 2: Verificar que compila**

Run: `npm run build`
Expected: compila sin errores.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/ProximosEventos.jsx
git commit -m "feat: add ProximosEventos chronological events panel"
```

---

### Task 7: Wiring — reemplazar el contenido de las pestañas de fase en `Dashboard.jsx`

Este es el archivo completo y final de `resources/js/components/Dashboard.jsx`. Reemplaza el archivo entero (409 líneas hoy) por este contenido — es más confiable que una serie de parches fragmentados dado cuánto cambia la sección central del archivo. El `Header`, los dos modales (`modalProyecto`/`modalContrato`) y sus pestañas internas **no cambian ni una línea** respecto al archivo actual; el resto sí.

**Files:**
- Modify: `resources/js/components/Dashboard.jsx` (reemplazo completo)

- [ ] **Step 1: Reemplazar el archivo completo**

```jsx
import React, { useState, useEffect } from 'react';
import Header from './Header';
import axios from 'axios';
import '../../css/Dashboard.css';
import '../../css/Dashboard-Extras.css';
import KpiStrip from './KpiStrip';
import Estadisticas from './Estadisticas';
import MapaUbicaciones from './MapaUbicaciones';
import DistribucionMunicipios from './DistribucionMunicipios';
import ResumenFase from './ResumenFase';
import ProximosEventos from './ProximosEventos';

const Dashboard = ({ user, onLogout }) => {
    const [activeTab, setActiveTab] = useState(''); // Inicialmente vacío
    const [municipioResaltado, setMunicipioResaltado] = useState(null);
    const [fases, setFases] = useState([]);
    const [proyectos, setProyectos] = useState([]);
    const [modalProyecto, setModalProyecto] = useState(null);

    const [isLargeScreen, setIsLargeScreen] = useState(false);

    // Detectar tamaño de pantalla
    useEffect(() => {
        const checkScreenSize = () => {
            setIsLargeScreen(window.innerWidth >= 1200);
        };

        checkScreenSize();
        window.addEventListener('resize', checkScreenSize);

        return () => window.removeEventListener('resize', checkScreenSize);
    }, []);

    // Función para filtrar proyectos por nombre de fase
    const getProyectosPorNombreFase = (nombreFase) => {
        return proyectos.filter(proyecto => proyecto.descripcion_fase === nombreFase);
    };

    const handleTabChange = (tab) => {
        setActiveTab(tab);
        setMunicipioResaltado(null); // Reset al cambiar de pestaña
    };

    const handleOpenModalProyecto = (proyecto) => setModalProyecto(proyecto);
    const handleCloseModalProyecto = () => setModalProyecto(null);

    // Estado para la pestaña activa del modal de proyecto
    const [modalProyectoTab, setModalProyectoTab] = useState('datos');

    // Estado para el contrato seleccionado en el modal
    const [modalContrato, setModalContrato] = useState(null);

    useEffect(() => {
        listFases();
        listProyectos();
    }, []);

    // Cuando se abre un nuevo modal, resetear la pestaña activa
    useEffect(() => {
        if (modalProyecto) setModalProyectoTab('datos');
    }, [modalProyecto]);

    useEffect(() => {
        if (fases.length > 0) {
            setActiveTab(fases[0].nombre);
        }
    }, [fases]);

    const listFases = async () => {
        const response = await axios.get('/fases');
        setFases(response.data);
    };

    const listProyectos = async () => {
        const response = await axios.get('/proyectos');
        setProyectos(response.data);
        console.log(response.data);
    };

    // Mostrar solo las fases que tienen dashboard_fase === 1
    const fasesDashboard = fases.filter(fase => fase.dashboard === 1);
    console.log(fasesDashboard);

    return (
        <div className="dashboard-container">
            {/* Header */}
            <Header user={user} onLogout={onLogout} />

            {/* Contenido principal */}
            <main className="dashboard-main ocultar-scroll">
                {/* KPIs ejecutivos — globales, no cambian por pestaña */}
                <KpiStrip proyectos={proyectos} />

                {/* Pestañas de Proyectos */}
                <section className="proyectos-tabs-section">
                    <div className="tabs-container">
                        <div className="tabs-header">
                            {fasesDashboard.map(fase => (
                                <button
                                    key={fase.id}
                                    className={`tab-button ${activeTab === fase.nombre ? 'active' : ''}`}
                                    onClick={() => handleTabChange(fase.nombre)}
                                >
                                    {fase.nombre}
                                </button>
                            ))}
                            <button
                                className={`tab-button ${activeTab === 'estadisticas' ? 'active' : ''}`}
                                onClick={() => handleTabChange('estadisticas')}
                            >
                                Estadísticas y Filtros
                            </button>
                        </div>

                        <div className="tab-content">
                            {activeTab !== 'estadisticas' && fasesDashboard.map(fase => (
                                activeTab === fase.nombre && (
                                    <div key={fase.id} className="dashboard-fase-grid">
                                        <div className="dashboard-fase-main">
                                            <div className="fase-vista-header">
                                                <h2>Proyectos en {fase.nombre}</h2>
                                                <p className="fase-vista-subtitulo">Ubicación geográfica de los proyectos</p>
                                            </div>
                                            <MapaUbicaciones
                                                proyectos={proyectos}
                                                onProyectoClick={handleOpenModalProyecto}
                                                municipioResaltado={municipioResaltado}
                                            />
                                            <DistribucionMunicipios
                                                proyectos={getProyectosPorNombreFase(fase.nombre)}
                                                onMunicipioClick={setMunicipioResaltado}
                                            />
                                        </div>
                                        <div className="dashboard-fase-lateral">
                                            <ResumenFase
                                                proyectos={getProyectosPorNombreFase(fase.nombre)}
                                                nombreFase={fase.nombre}
                                            />
                                            <ProximosEventos />
                                        </div>
                                    </div>
                                )
                            ))}
                            {activeTab === 'estadisticas' && <Estadisticas proyectos={proyectos} />}
                        </div>
                    </div>
                </section>
            </main>
            {modalProyecto && (
                <div className="modal-proyecto-overlay" onClick={handleCloseModalProyecto}>
                    <div className="modal-proyecto" onClick={e => e.stopPropagation()}>
                        <div className="modal-proyecto-header">
                            <h2><span className="icono">📁</span>{modalProyecto.nombre}</h2>
                            <button className="modal-close-btn" onClick={handleCloseModalProyecto}>×</button>
                        </div>
                        {/* Pestañas del modal */}
                        <div className="modal-tabs">
                            <button
                                className={`modal-tab${modalProyectoTab === 'datos' ? ' active' : ''}`}
                                onClick={() => setModalProyectoTab('datos')}
                            >
                                Datos Generales
                            </button>
                            <button
                                className={`modal-tab${modalProyectoTab === 'componentes' ? ' active' : ''}`}
                                onClick={() => setModalProyectoTab('componentes')}
                            >
                                Componentes
                            </button>
                            {modalProyecto.contratos && modalProyecto.contratos.length > 0 && (
                                <button
                                    className={`modal-tab${modalProyectoTab === 'contratos' ? ' active' : ''}`}
                                    onClick={() => setModalProyectoTab('contratos')}
                                >
                                    Contratos
                                </button>
                            )}
                        </div>
                        <div className="modal-proyecto-content">
                            {modalProyectoTab === 'datos' && (
                                <div className="modal-tab-content datos-generales">
                                    <p><span className="icono">📝</span><strong>Descripción:</strong> {modalProyecto.descripcion}</p>
                                    <p><span className="icono">📍</span><strong>Municipio:</strong> {modalProyecto.descripcion_municipio}</p>
                                    <p><span className="icono">🔄</span><strong>Fase:</strong> {modalProyecto.descripcion_fase}</p>
                                    <p><span className="icono">📊</span><strong>Estado:</strong> {modalProyecto.descripcion_estado}</p>
                                    <p><span className="icono">💰</span><strong>Presupuesto Total:</strong> $ {modalProyecto.totalPresupuesto?.toLocaleString()}</p>
                                    <p><span className="icono">🏢</span><strong>Entidad Presenta:</strong> {modalProyecto.descripcion_entidad_presenta}</p>
                                    <p><span className="icono">🏦</span><strong>Entidad Financia:</strong> {modalProyecto.descripcion_entidad_financia}</p>
                                    <p><span className="icono">💡</span><strong>Fuente de Financiación:</strong> {modalProyecto.fuente_financiacion}</p>
                                </div>
                            )}
                            {modalProyectoTab === 'componentes' && (
                                <div className="modal-tab-content componentes">
                                    <h3>Componentes del Presupuesto</h3>
                                    <ul>
                                        {modalProyecto.componentesPresupuesto && modalProyecto.componentesPresupuesto.length > 0 ? (
                                            modalProyecto.componentesPresupuesto.map(comp => (
                                                <li key={comp.id}>
                                                    <span className="badge-componente">{comp.componente}</span>
                                                    <span className="icono">💵</span>$ {parseInt(comp.valor).toLocaleString()}
                                                </li>
                                            ))
                                        ) : (
                                            <li>No hay componentes registrados.</li>
                                        )}
                                    </ul>
                                </div>
                            )}
                            {modalProyectoTab === 'contratos' && modalProyecto.contratos && (
                                <div className="modal-tab-content contratos">
                                    <h3>Contratos</h3>
                                    {modalProyecto.contratos.length > 0 ? (
                                        <table className="contratos-table">
                                            <thead>
                                                <tr>
                                                    <th>Número</th>
                                                    <th>Objeto</th>
                                                    <th>Valor</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {modalProyecto.contratos.map(contrato => (
                                                    <tr key={contrato.id} className="contrato-row" style={{ cursor: 'pointer' }} onClick={() => setModalContrato(contrato)}>
                                                        <td>{contrato.n_contrato}</td>
                                                        <td>{contrato.objeto}</td>
                                                        <td>$ {contrato.valor ? parseInt(contrato.valor).toLocaleString() : ''}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    ) : (
                                        <div>No hay contratos registrados.</div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
            {modalContrato && (
                <div className="modal-proyecto-overlay" onClick={() => setModalContrato(null)}>
                    <div className="modal-proyecto" onClick={e => e.stopPropagation()}>
                        <div className="modal-proyecto-header">
                            <h2><span className="icono">📄</span>Contrato {modalContrato.n_contrato || modalContrato.numero || modalContrato.nombre}</h2>
                            <button className="modal-close-btn" onClick={() => setModalContrato(null)}>×</button>
                        </div>
                        <div className="modal-proyecto-content">
                            <p><span className="icono">🔢</span><strong>Número de Contrato:</strong> {modalContrato.n_contrato || modalContrato.numero || modalContrato.nombre}</p>
                            <p><span className="icono">📝</span><strong>Objeto:</strong> {modalContrato.objeto || modalContrato.descripcion}</p>
                            <p><span className="icono">🏢</span><strong>Contratante:</strong> {modalContrato.contratante}</p>
                            <p><span className="icono">👷</span><strong>Contratista:</strong> {modalContrato.contratista}</p>
                            <p><span className="icono">💵</span><strong>Monto:</strong> $ {modalContrato.monto ? parseInt(modalContrato.monto).toLocaleString() : (modalContrato.valor ? parseInt(modalContrato.valor).toLocaleString() : '')}</p>
                            <p><span className="icono">📅</span><strong>Fecha de Inicio:</strong> {modalContrato.fecha_inicio || modalContrato.fecha}</p>
                            <p><span className="icono">📅</span><strong>Fecha de Fin:</strong> {modalContrato.fecha_fin}</p>
                            <p><span className="icono">🕵️‍♂️</span><strong>Interventoría:</strong> {modalContrato.interventoria || modalContrato.interventor}</p>
                            <p><span className="icono">📈</span><strong>Avance:</strong> {modalContrato.avance ? `${modalContrato.avance}%` : ''}</p>
                            <p><span className="icono">🔄</span><strong>Estado:</strong> {modalContrato.estado}</p>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Dashboard;
```

Nota sobre lo que se quitó respecto al archivo original: el estado `selectedMunicipio` y sus handlers `handleMunicipioClick`/`handleBackToMunicipios` (reemplazados por `municipioResaltado`/`setMunicipioResaltado`, ya que ya no hay drill-down, solo resaltado en el mapa); las funciones `getProyectosPorFase`, `getProyectosPorMunicipioYFase`, `getProyectosPorMunicipioFaseYEstado`, `getProyectosPorMunicipioYNombreFase` y las variables `proyectosFormulacion`/`proyectosLicitacion`/`proyectosEjecucion` y su `console.log` (todas quedaban sin otro uso tras quitar la grilla de `MunicipioCard`); el array derivado `municipios` y los tres `reduce` de `proyectosPorMunicipio`/`proyectosLicitacionPorMunicipio`/`proyectosEjecucionPorMunicipio` (mismo motivo); `formatDate` (solo se usaba en la vista de detalle por municipio eliminada); y los imports de `MunicipioCard`/`AlertasPanel`.

- [ ] **Step 2: Verificar que compila**

Run: `npm run build`
Expected: compila sin errores. Si aparece un error de "'X' is not defined", revisar que no quedó ninguna referencia a las funciones/variables eliminadas en el Step 1.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/Dashboard.jsx
git commit -m "feat: replace phase-tab content with map, summary, table and events panel"
```

---

### Task 8: CSS — estilos nuevos y limpieza de los obsoletos

**Files:**
- Modify: `resources/css/Dashboard.css`

- [ ] **Step 1: Eliminar los bloques de CSS que quedan sin uso**

En `resources/css/Dashboard.css`, eliminar los siguientes rangos de líneas (numeración del archivo **antes** de este cambio; bórralos de abajo hacia arriba para que no se corran los números mientras editas):

1. Líneas 944-954 — comentario + regla `.dashboard-fase-main .municipios-grid, .dashboard-fase-main .proyectos-grid` (esas clases ya no existen en el nuevo markup).
2. Líneas 835-933 — desde el comentario `/* Panel de alertas */` (`.alertas-panel`) hasta el final de `.alerta-fecha` (todo el bloque de estilos de `AlertasPanel`).
3. Dentro del `@media (max-width: 1200px)` que sigue (líneas 934-942 en el archivo original), eliminar solo la sub-regla `.alertas-panel { position: static; }`, dejando `.dashboard-fase-grid { grid-template-columns: 1fr; }` intacto. El bloque queda así:
   ```css
   @media (max-width: 1200px) {
       .dashboard-fase-grid {
           grid-template-columns: 1fr;
       }
   }
   ```
4. Líneas 759-821 — desde el comentario `/* Ampliación de la tarjeta de municipio: presupuesto, avance, riesgo, botón */` (`.municipio-presupuesto`) hasta `.municipio-btn-detalle:hover` (todos los estilos que solo usaba `MunicipioCard`). **No borrar** `.dashboard-fase-grid`/`.dashboard-fase-main` (líneas 823-833), esos se mantienen tal cual — se siguen usando en el nuevo layout.
5. Líneas 128-451 — desde el comentario `/* Cards de Municipios Modernas */` (`.municipios-grid`) hasta el final del bloque `.estado-badge.en-evaluación` (justo antes de `.eventos-grid`). **No tocar** `.eventos-grid`/`.evento-card` y lo que sigue después (esas pertenecen a `GestionarEventos.jsx`, un componente no relacionado con este trabajo).

- [ ] **Step 2: Verificar que nada más referencia esas clases**

Run:
```bash
grep -rn "municipio-card-modern\|municipio-proyectos-view\|proyecto-card\b\|alertas-panel\|alerta-item\|municipio-presupuesto\|municipio-avance\|municipio-estado-general\|municipio-btn-detalle" resources/js/
```
Expected: sin resultados (ya no queda ningún componente JSX usando esas clases, tras la Tarea 7).

- [ ] **Step 3: Agregar los estilos nuevos**

Al final de `resources/css/Dashboard.css`, agregar:

```css
/* Encabezado sobre el mapa de ubicaciones, dentro de cada pestaña de fase */
.fase-vista-header {
    margin-bottom: 1.2rem;
}

.fase-vista-header h2 {
    color: #1a237e;
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 0.3rem;
}

.fase-vista-subtitulo {
    color: #6c757d;
    font-size: 0.9rem;
}

/* Columna lateral de cada pestaña de fase: Resumen + Próximos eventos apilados */
.dashboard-fase-lateral {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Contenedor del mapa de ubicaciones */
.dashboard-mapa-container {
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
}

/* Mensaje reutilizado por la tabla de distribución y por próximos eventos cuando no hay datos */
.tabla-sin-datos {
    color: #6c757d;
    font-size: 0.9rem;
    text-align: center;
    padding: 1.5rem 0;
}

/* Panel "Resumen de [Fase]" */
.resumen-fase-panel {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.resumen-fase-panel h3 {
    color: #1a237e;
    font-size: 1.2rem;
    margin-bottom: 1.2rem;
    font-weight: 700;
}

.resumen-fase-stat {
    display: flex;
    flex-direction: column;
    margin-bottom: 1.2rem;
}

.resumen-fase-stat:last-child {
    margin-bottom: 0;
}

.resumen-fase-valor {
    color: #1a237e;
    font-size: 1.6rem;
    font-weight: 800;
}

.resumen-fase-valor.en-riesgo {
    color: #e53935;
}

.resumen-fase-etiqueta {
    color: #6c757d;
    font-size: 0.8rem;
}

/* Panel "Próximos eventos" */
.proximos-eventos-panel {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.proximos-eventos-panel h3 {
    color: #1a237e;
    font-size: 1.2rem;
    margin-bottom: 1.2rem;
    font-weight: 700;
}

.proximos-eventos-lista {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}

.proximo-evento-item {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 0.6rem 0.7rem;
    background: rgba(248, 249, 250, 0.8);
    border-radius: 10px;
}

.proximo-evento-fecha {
    display: flex;
    flex-direction: column;
    align-items: center;
    line-height: 1.1;
    flex-shrink: 0;
    min-width: 2.6rem;
}

.proximo-evento-dia {
    color: #1976d2;
    font-size: 1.1rem;
    font-weight: 800;
}

.proximo-evento-mes {
    color: #6c757d;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.04em;
}

.proximo-evento-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1;
    min-width: 0;
}

.proximo-evento-titulo {
    color: #1a237e;
    font-weight: 600;
    font-size: 0.85rem;
}

.proximo-evento-proyecto {
    color: #6c757d;
    font-size: 0.75rem;
}

.proximo-evento-municipio {
    background: rgba(25, 118, 210, 0.1);
    color: #1976d2;
    padding: 0.25rem 0.6rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    white-space: nowrap;
}
```

- [ ] **Step 4: Verificar que compila**

Run: `npm run build`
Expected: compila sin errores.

- [ ] **Step 5: Commit**

```bash
git add resources/css/Dashboard.css
git commit -m "style: add map/summary/table/events panel styles, remove obsolete rules"
```

---

### Task 9: Eliminar `MunicipioCard.jsx` y `AlertasPanel.jsx`

**Files:**
- Delete: `resources/js/components/MunicipioCard.jsx`
- Delete: `resources/js/components/AlertasPanel.jsx`

- [ ] **Step 1: Confirmar que ya no se usan en ningún lado**

Run:
```bash
grep -rn "MunicipioCard\|AlertasPanel" resources/js/
```
Expected: sin resultados (tras la Tarea 7, `Dashboard.jsx` ya no los importa, y estos son los dos únicos componentes que los usaban).

- [ ] **Step 2: Eliminar los archivos (git rm los borra y los deja en stage)**

```bash
git rm resources/js/components/MunicipioCard.jsx resources/js/components/AlertasPanel.jsx
```

- [ ] **Step 3: Verificar que compila**

Run: `npm run build`
Expected: compila sin errores.

- [ ] **Step 4: Commit**

```bash
git commit -m "chore: remove MunicipioCard and AlertasPanel, superseded by the new dashboard view"
```

---

### Task 10: Verificación manual end-to-end

- [ ] **Step 1: Levantar el backend y compilar el frontend**

```bash
php -S 127.0.0.1:8017 -t public
npm run build
```

- [ ] **Step 2: Verificar el mapa**

Iniciar sesión, ir al Dashboard, confirmar que la pestaña de la primera fase (ej. Formulación) muestra el mapa con al menos un cluster/marcador visible si existen proyectos con `puntosUbicacion` guardados (los 2 puntos de prueba de la feature `ubicacion-proyectos-mapa` deberían aparecer). Hacer clic en un marcador individual y confirmar que abre el modal de detalle del proyecto correcto.

- [ ] **Step 3: Verificar resumen y tabla por pestaña**

Cambiar entre las pestañas de fase (Formulación/Licitación/Ejecución) y confirmar que "Resumen de [Fase]" y "Distribución por municipio" cambian sus números según la fase activa, y que sus cifras (cantidad, presupuesto, avance) son coherentes con lo que muestra la tira de KPIs superior para ese subconjunto de proyectos.

- [ ] **Step 4: Verificar clic en fila de municipio**

En la tabla "Distribución por municipio", hacer clic en una fila y confirmar que el mapa se reencuadra sobre los puntos de ese municipio (si tiene puntos capturados) sin abrir ningún modal.

- [ ] **Step 5: Verificar "Próximos eventos"**

Confirmar que el panel muestra hasta 5 eventos futuros ordenados por fecha, con su municipio correcto en el chip, y que **no cambia** al cambiar de pestaña de fase (es global, según lo decidido en el spec).

- [ ] **Step 6: Verificar que la pestaña Estadísticas sigue intacta**

Cambiar a la pestaña "Estadísticas y Filtros" y confirmar que se ve y funciona exactamente igual que antes de este cambio.

- [ ] **Step 7: Correr la suite completa de tests una vez más**

Run: `php artisan test`
Expected: `Tests: 36 passed`

- [ ] **Step 8: Detener el servidor de prueba**

Detener el proceso de `php -S 127.0.0.1:8017` iniciado en el Step 1.
