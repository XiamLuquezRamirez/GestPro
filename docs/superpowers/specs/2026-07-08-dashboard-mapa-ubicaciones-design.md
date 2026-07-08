# Dashboard: mapa de ubicaciones + resumen por fase

**Fecha:** 2026-07-08
**Estado:** Aprobado, pendiente de plan de implementación

## Contexto

El usuario compartió una maqueta visual de una nueva vista para las pestañas de fase del Dashboard (Formulación / Licitación / Ejecución): tira de KPIs, un mapa con la ubicación geográfica de los proyectos, un panel de resumen de la fase activa, una tabla de distribución por municipio, y un panel de próximos eventos.

Esto conecta con la feature recién completada en `feature/ubicacion-proyectos-mapa`: cada proyecto ya puede tener varios puntos lat/lng capturados en la pestaña "Ubicación" del modal de Gestión de Proyectos, persistidos en `proyecto_ubicaciones` y devueltos embebidos en cada proyecto (`puntosUbicacion`) por `GET /proyectos`. Este documento cubre cómo usar esos datos para reemplazar el contenido actual de las pestañas de fase del Dashboard.

Esta es la segunda de dos sub-iteraciones anticipadas en la spec `2026-07-08-ubicacion-proyectos-mapa-design.md` (la primera fue la captura de puntos; el usuario pidió explícitamente no tocar el Dashboard hasta ahora).

## Alcance

Incluye:
- Reemplazar, dentro de cada pestaña de fase (Formulación/Licitación/Ejecución) de `Dashboard.jsx`, el contenido actual (grilla `MunicipioCard` + vista de detalle por municipio + `AlertasPanel`) por: mapa de ubicaciones + panel "Resumen de [Fase]" + tabla "Distribución por municipio" + panel "Próximos eventos".
- Nuevo componente de mapa con clustering de puntos (Leaflet + plugin de clustering).
- Nuevo componente `ResumenFase` (o similar) con el panel de resumen y la tabla de distribución por municipio.
- Nuevo componente `ProximosEventos`, reemplazando el uso de `AlertasPanel` en esta vista.
- Pequeña extensión de `ProyectoController::eventos()` para incluir el municipio del proyecto de cada evento (necesario para el chip de municipio en "Próximos eventos").
- Retiro de `MunicipioCard.jsx` y `AlertasPanel.jsx` del árbol de componentes si, tras el cambio, no se usan en ningún otro lugar del proyecto (se confirma con una búsqueda antes de eliminar los archivos).

No incluye:
- La pestaña "Estadísticas y Filtros": queda exactamente como está.
- Ningún cambio a `KpiStrip.jsx` ni a la tira de KPIs superior.
- Ningún mapa coroplético con polígonos de municipios (se descartó esa opción — ver Decisiones registradas).
- Ningún endpoint nuevo para agregaciones de mapa/resumen: todo se calcula en el cliente a partir de `/proyectos` (que ya incluye `puntosUbicacion`, `municipio`, `progreso`, `descripcion_estado`) y `/eventos`.
- El indicador de paginación "3/3" de la maqueta: no es UI real, no se implementa.

## Diseño

### 1. Mapa de ubicaciones

Nuevo componente (ej. `MapaUbicaciones.jsx`), usado dentro de cada pestaña de fase:
- Se basa en `react-leaflet` (ya instalado) + un plugin de clustering (`react-leaflet-cluster`, nueva dependencia npm).
- Recibe **todos los proyectos de todas las fases** (no solo los de la pestaña activa — decisión explícita del usuario, para dar contexto geográfico completo siempre).
- Aplana todos los `punto` de `proyecto.puntosUbicacion` de todos los proyectos en una lista de marcadores, cada uno con referencia a su proyecto.
- El plugin de clustering agrupa visualmente los marcadores cercanos en un círculo con contador (coincide con el look de la maqueta), usando su esquema de color propio por tamaño de grupo — no hay rangos de color fijos definidos por producto, y no hay "regiones grises" para municipios sin proyectos (esto solo sería posible con un mapa coroplético real con polígonos, que se descartó).
- Encuadre inicial: `fitBounds` a todos los puntos existentes; si no hay ningún punto capturado en todo el sistema, se usa el centro/zoom por defecto ya establecido en `Parametros.jsx` (`[6.2442, -75.5812]`, zoom 9).
- Clic en un marcador individual (no en un cluster) → invoca `onProyectoClick(proyecto)`, reutilizando el modal de detalle de proyecto que ya vive en `Dashboard.jsx` (mismo patrón que `AlertasPanel` usa hoy).
- Proyectos sin ningún punto capturado simplemente no tienen marcador — es un estado esperado mientras se van cargando ubicaciones para proyectos existentes.

### 2. Panel "Resumen de [Fase]" + tabla "Distribución por municipio"

Nuevo componente (ej. `ResumenFase.jsx`), recibe los proyectos **ya filtrados por la fase de la pestaña activa** (a diferencia del mapa) y el nombre de la fase para el título.

- **Panel de resumen** (columna lateral derecha):
  - Cantidad de proyectos en la fase.
  - Presupuesto total de la fase (`sum(presupuesto)`).
  - Avance promedio (`avg(progreso)`, mismo campo y cálculo que usa `KpiStrip`).
  - Cantidad en riesgo (`filter(descripcion_estado === 'Con retraso').length`, mismo criterio que `KpiStrip`/`AlertasPanel` hoy).
- **Tabla "Distribución por municipio"**: se agrupan los proyectos de la fase por `descripcion_municipio` (no por puntos del mapa). Una fila por municipio con:
  - Municipio (nombre).
  - Proyectos (conteo).
  - Presupuesto (suma).
  - Avance promedio (%).
  - Estado general: "Buen ritmo" si ningún proyecto del municipio está en riesgo; si al menos uno lo está, una etiqueta de alerta (ej. "Con retraso", reutilizando el color/icono ya asociado a ese estado).
- Clic en una fila de municipio → centra/resalta ese municipio en el mapa (vía una prop callback, ej. `onMunicipioClick`); no abre el modal de proyecto, ya que la fila representa un agregado de varios proyectos, no uno solo.

### 3. Panel "Próximos eventos" (nuevo componente, reemplaza `AlertasPanel` en esta vista)

Nuevo componente `ProximosEventos.jsx`:
- Hace su propio `axios.get('/eventos')` (mismo patrón que `AlertasPanel` hoy).
- Filtra a eventos con `fecha` futura (o de hoy en adelante), sin filtrar por prioridad ni por fase — **siempre todos los proyectos/todas las fases** (decisión explícita del usuario).
- Ordena ascendente por fecha y muestra los **próximos 5**.
- Cada fila: fecha (día + mes abreviado, como en la maqueta), título del evento, nombre del proyecto (`descripcion_proyecto`), y un chip con el municipio del proyecto.
- Si no hay eventos futuros, un mensaje simple ("No hay eventos próximos.").
- `AlertasPanel.jsx` deja de usarse en `Dashboard.jsx`. El KPI "En riesgo" de la tira superior sigue mostrando esa señal de todas formas, así que no se pierde visibilidad sobre proyectos en riesgo.

### 4. Backend: municipio en `/eventos`

`ProyectoController::eventos()` no incluye hoy ni el `proyecto` (FK) ni el municipio del proyecto en su `select`. Se agrega:
```php
'eventos.proyecto',
DB::raw('municipios.nombre as descripcion_municipio'),
```
con un join adicional:
```php
->leftJoin('municipios', 'proyectos.municipio', 'municipios.codigo')
```
Esto es necesario para que `ProximosEventos.jsx` pueda mostrar el chip de municipio sin tener que cruzar por nombre de proyecto (frágil). Cambio mínimo, aditivo, no rompe el contrato actual del endpoint (solo agrega columnas).

### 5. `Dashboard.jsx`

- Se retiran: import y uso de `MunicipioCard`, la lógica de `municipio-proyectos-view` (drill-down), y el uso de `AlertasPanel`.
- Las funciones de agrupación existentes (`getProyectosPorMunicipioYFase`, etc.) se revisan: las que ya no se usan tras el cambio se eliminan; la lógica de agrupación por municipio+fase que sí se necesita para `ResumenFase` se reutiliza o adapta.
- Estructura nueva del contenido de cada pestaña de fase:
  ```
  <div className="fase-vista-grid">
      <div className="fase-vista-principal">
          <MapaUbicaciones proyectos={proyectos} onProyectoClick={...} />
      </div>
      <ResumenFase
          proyectos={proyectosDeLaFase}
          nombreFase={fase.nombre}
          onMunicipioClick={...}
      />
      <ProximosEventos />
  </div>
  ```
  (El acomodo exacto de grid/columnas se define en el plan de implementación, siguiendo el lenguaje visual ya establecido en `2026-07-07-dashboard-layout-alertas-design.md`.)
- La pestaña "Estadísticas y Filtros" no cambia.

### 6. Limpieza

- Si tras el cambio `MunicipioCard.jsx` y/o `AlertasPanel.jsx` no tienen ningún otro uso en el proyecto (se verifica con una búsqueda de importaciones antes de tocar nada), se eliminan sus archivos y el CSS que les pertenecía en exclusiva.

### 7. Verificación

- `php artisan test` sigue en verde tras el cambio de backend en `eventos()`.
- `npm run build`/`npm run dev` compila sin errores con la nueva dependencia de clustering.
- Verificación manual: el mapa muestra clusters con los puntos reales ya guardados (los 2 puntos de prueba de la feature anterior deberían verse), clic en un marcador abre el modal correcto, cada pestaña de fase muestra su resumen y tabla con números coherentes con los KPIs globales, "Próximos eventos" lista eventos reales de cualquier fase con su municipio correcto, y la pestaña Estadísticas sigue funcionando sin cambios.

## Decisiones registradas

- Mapa por **clustering de puntos reales**, no coroplético con polígonos de municipios — no hay GeoJSON de municipios en el proyecto y conseguir/mantener uno no se justifica cuando el clustering ya da un resultado visualmente equivalente con los datos que ya existen.
- El indicador "3/3" de la maqueta no es UI real (era parte de un diseño anterior del panel de eventos, ya resuelto de otra forma) — se descarta.
- El mapa y "Próximos eventos" muestran **siempre todos los proyectos/fases**, mientras que el resumen y la tabla de distribución sí se filtran por la fase de la pestaña activa — asimetría explícita pedida por el usuario (el mapa da contexto geográfico completo siempre; los números del resumen sí cambian por pestaña, igual que hoy).
- La tabla "Distribución por municipio" y el resumen se calculan sobre el campo `municipio` que cada proyecto ya tiene, no sobre los puntos capturados — evita que proyectos sin puntos aún capturados desaparezcan de esas cifras.
- Clic en marcador de mapa → modal de proyecto; clic en fila de municipio en la tabla → centra el mapa en ese municipio (no abre modal, es un agregado).
- `AlertasPanel` se retira de esta vista (reemplazado por `ProximosEventos`); el KPI "En riesgo" ya existente cubre la señal de riesgo que antes daba ese panel.
- Próximos eventos: se muestran 5 (la maqueta mostraba 3; 5 se eligió como default razonable, ajustable en el plan si se prefiere otro número).
- Se extiende `ProyectoController::eventos()` para incluir el municipio del proyecto — cambio de backend mínimo y aditivo, justificado porque el chip de municipio en "Próximos eventos" lo requiere.
