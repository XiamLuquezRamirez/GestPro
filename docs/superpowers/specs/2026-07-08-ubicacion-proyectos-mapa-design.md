# Ubicación de proyectos: selección de puntos en mapa

**Fecha:** 2026-07-08
**Estado:** Aprobado, pendiente de plan de implementación

## Contexto

Dentro del módulo "Gestión de Proyectos" (`resources/js/components/Parametros.jsx`), el modal de crear/editar proyecto ya tiene pestañas (`Datos del Proyecto`, `Presupuesto`, `Financiación`) y el usuario había empezado a construir manualmente una cuarta pestaña, "Ubicación" (botón ya agregado, contenido vacío — un placeholder `<div className="form-group"></div>` sin terminar). El pedido es completar esa pestaña: un mapa donde el usuario pueda seleccionar varios puntos geográficos (un proyecto puede tener más de uno), que se guarden en la base de datos.

Se identificaron dos mejoras relacionadas en la sesión de brainstorming, y se decidió construir solo la primera por ahora:
1. **Captura de puntos de ubicación** (este documento): mapa interactivo en el modal de proyecto para agregar/quitar puntos, persistidos en la base de datos.
2. **Mapa de calor en el Dashboard** (explícitamente diferido — no se toca nada del Dashboard en este trabajo; se retoma en una conversación futura).

No existen coordenadas geográficas en ninguna tabla del sistema hoy (ni en `municipios` ni en `proyectos`) — esta es la primera vez que se introduce este tipo de dato.

## Alcance

Incluye:
- Nueva tabla `proyecto_ubicaciones` (lat/lng por punto, ligada a un proyecto).
- Nuevo modelo `ProyectoUbicacion`.
- Extender `ProyectoController::guardarProyecto()` para persistir los puntos (patrón borrar-y-reinsertar, igual que ya hace con `componentesPresupuesto`).
- Extender `ProyectoController::proyectos()` para devolver los puntos de cada proyecto (igual que ya hace con `componentesPresupuesto`/`contratos`), para poder recargarlos al editar.
- Completar la pestaña "Ubicación" del modal de proyecto en `Parametros.jsx`: mapa interactivo (Leaflet + OpenStreetMap) donde click agrega un punto y click sobre un punto existente lo quita, más un contador y un botón "Limpiar todos".
- Instalar `leaflet` y `react-leaflet` como dependencias nuevas.

No incluye:
- Ningún cambio al Dashboard (`Dashboard.jsx`, `AlertasPanel.jsx`, `Estadisticas.jsx`, etc.) — se difiere explícitamente a una conversación futura.
- Ningún mapa de calor ni visualización agregada de los puntos — este documento cubre solo la captura y persistencia.
- Auto-centrar el mapa según el municipio seleccionado en la pestaña "Datos" — requeriría agregar coordenadas a la tabla `municipios`, fuera de alcance aquí. El mapa se centra en un punto fijo (Medellín/Antioquia) por defecto.
- Validación de que los puntos caigan dentro de los límites del municipio seleccionado — no se valida geográficamente, se confía en que el usuario haga clic en el lugar correcto.
- Un límite máximo de puntos por proyecto — el usuario puede agregar tantos como quiera.

## Diseño

### 1. Esquema de base de datos

Nueva migración, tabla `proyecto_ubicaciones`:
```php
Schema::create('proyecto_ubicaciones', function (Blueprint $table) {
    $table->id();
    $table->foreignId('proyecto')->constrained('proyectos')->cascadeOnDelete();
    $table->decimal('lat', 10, 7);
    $table->decimal('lng', 10, 7);
});
```
`cascadeOnDelete()` porque estos puntos son propiedad exclusiva de su proyecto — mismo criterio ya aplicado a `presupuesto_proyecto`, `contratos`, etc. (ver convención documentada en specs anteriores de este mismo proyecto).

Nuevo modelo `app/Models/ProyectoUbicacion.php`:
```php
class ProyectoUbicacion extends Model
{
    public $timestamps = false;
    protected $fillable = ['proyecto', 'lat', 'lng'];

    public function proyectoRel(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto');
    }
}
```
(Se sufija `proyectoRel()` en vez de `proyecto()` porque la columna FK ya se llama `proyecto` — mismo motivo documentado ya en `Evento::proyectoRel()`/`Contrato` y otros modelos de este proyecto.)

### 2. Backend: persistir y devolver los puntos

En `ProyectoController::guardarProyecto()`, tanto en la rama de "Agregar" como en la de edición, se agrega el mismo patrón borrar-y-reinsertar que ya existe para `componentesPresupuesto`:
```php
DB::table('proyecto_ubicaciones')->where('proyecto', $proyectoId)->delete(); // solo en edición
if (isset($proyecto['puntosUbicacion']) && count($proyecto['puntosUbicacion']) > 0) {
    foreach ($proyecto['puntosUbicacion'] as $punto) {
        DB::table('proyecto_ubicaciones')->insert([
            'proyecto' => $proyectoId,
            'lat' => $punto['lat'],
            'lng' => $punto['lng'],
        ]);
    }
}
```
(En la rama "Agregar" no hace falta el `delete()` porque el proyecto es nuevo.)

En `ProyectoController::proyectos()`, dentro del mismo `foreach` que ya adjunta `componentesPresupuesto` y `contratos` a cada `$proyecto`, se agrega:
```php
$proyecto->puntosUbicacion = DB::table('proyecto_ubicaciones')
    ->select('id', 'lat', 'lng')
    ->where('proyecto', $proyecto->id)
    ->get();
```

### 3. Frontend: pestaña "Ubicación"

Se agrega `puntosUbicacion: []` al `formData` inicial de `Parametros.jsx`. En `handleEdit`, cuando `type === 'proyectos'`, se carga `item.puntosUbicacion || []` en ese campo (igual que ya se hace para `componentesPresupuesto`/`contratos` al editar).

El contenido de la pestaña `{modalType === 'proyectos' && modalActiveTab === 'ubicacion' && (...)}` (hoy vacío) se completa con:
- Un texto de instrucción: "Haz clic en el mapa para agregar un punto. Haz clic en un punto existente para quitarlo."
- Un `<MapContainer>` de `react-leaflet` (altura fija, por ejemplo 400px), centrado en Medellín (`[6.2442, -75.5812]`), zoom inicial que muestre el departamento de Antioquia.
- Un componente interno que escucha el evento `click` del mapa (hook `useMapEvents` de `react-leaflet`) y agrega `{ lat, lng }` a `formData.puntosUbicacion`.
- Un `<Marker>` de `react-leaflet` por cada punto en `formData.puntosUbicacion`, con su propio `onClick` que lo quita del arreglo (usando su índice).
- Debajo del mapa: `"{n} punto(s) seleccionado(s)"` y un botón "Limpiar todos los puntos" que vacía el arreglo.

**Detalle técnico a resolver durante la implementación:** los íconos de marcador por defecto de Leaflet referencian rutas de imagen que los bundlers (Webpack/Mix incluido) no resuelven automáticamente — hay que importar explícitamente las imágenes del ícono y reasignar `L.Icon.Default.mergeOptions(...)`, un ajuste bien documentado y estándar al usar Leaflet con Webpack.

### 4. Verificación

Sin suite de tests de frontend. Verificación: `php artisan test` (confirma que la suite de backend sigue en verde), `npm run dev`, y navegación manual: abrir el modal de "Agregar proyecto", ir a la pestaña "Ubicación", hacer clic en el mapa varias veces y confirmar que aparecen marcadores, hacer clic en un marcador y confirmar que desaparece, guardar el proyecto y confirmar en la base de datos que los puntos quedaron en `proyecto_ubicaciones`, volver a abrir ese mismo proyecto para editar y confirmar que los puntos guardados se recargan en el mapa.

## Decisiones registradas

- Leaflet + OpenStreetMap (gratis, sin API key) en vez de Google Maps/Mapbox.
- El mapa de calor del Dashboard queda explícitamente fuera de este documento.
- Sin auto-centrado por municipio ni validación geográfica de los puntos — mantenerlo simple por ahora.
- Sin límite de puntos por proyecto.
- Persistencia con el mismo patrón borrar-y-reinsertar ya usado por `componentesPresupuesto`, reutilizando el endpoint `/guardarProyecto` existente en vez de crear rutas nuevas.
