# Dashboard: reorganización de layout + panel de alertas

**Fecha:** 2026-07-07
**Estado:** Aprobado, pendiente de plan de implementación

## Contexto

El usuario siente que el Dashboard "le hace falta información" y quiere que todo sea visible en una sola pantalla, sin scroll, en pantallas grandes (1920x1080 o similar). Hoy el `<main>` del Dashboard es una pila vertical: `KpiStrip` → pestañas de fase (con las tarjetas de municipio) o Estadísticas → sección "Próximos Eventos" (carrusel de 3 tarjetas) al final. En una pantalla estándar, llegar a "Próximos Eventos" ya requiere scroll.

Se identificaron dos mejoras independientes en la sesión de brainstorming:
1. **Reorganizar el layout** en un grid de 2 columnas (este documento) para acercarse a cero scroll y agregar un panel de alertas/riesgos.
2. **Mapa esquemático de municipios** (spec y plan separados, se aborda después).

Este documento cubre solo la primera.

Durante la exploración se encontraron dos problemas reales, no relacionados directamente con el pedido del usuario pero que bloquean la nueva funcionalidad:
- `ProyectoController::eventos()` selecciona `'eventos.estado'`, pero la migración `2026_07_06_160021_create_eventos_table.php` define la columna como `estado_evento`. Esto causa que `GET /eventos` devuelva un error 500 siempre (visto repetidamente en verificaciones de esta sesión y hasta ahora fuera de alcance).
- Las tablas `prioridades`, `tipo_eventos`, `responsable` y `eventos` están completamente vacías — ningún seeder las puebla. El nuevo panel de alertas necesita datos reales para mostrar algo.

Ambos se corrigen como parte de este trabajo, ya que el panel de alertas depende de ellos.

## Alcance

Incluye:
- Corregir `ProyectoController::eventos()` (alias de columna).
- Extender `DemoDataSeeder` con prioridades, tipos de evento, responsables y eventos de demostración.
- Nuevo componente `AlertasPanel.jsx`: combina proyectos en riesgo (`Con retraso`) y eventos de alta prioridad próximos.
- Reestructurar el layout de `Dashboard.jsx`/CSS a un grid de 2 columnas (contenido principal + panel lateral) para las pestañas de fase.
- Retirar `Eventos.jsx` del árbol de componentes (su lógica de eventos-de-alta-prioridad se reimplementa, más simple, dentro de `AlertasPanel.jsx`).

No incluye:
- El mapa esquemático de municipios (spec/plan separado, próxima iteración).
- Ningún cambio a `KpiStrip.jsx`, `MunicipioCard.jsx`, `Estadisticas.jsx` — se siguen usando tal cual.
- Cero scroll en la pestaña Estadísticas — esa vista sigue teniendo su propio scroll interno dado el volumen real de sus datos (6 gráficas + tabla de hasta 30 filas); prometer cero scroll ahí sería deshonesto con la densidad real de esa información.
- Ningún cambio al modal de detalle de proyecto/contrato.

## Diseño

### 1. Layout: grid de 2 columnas

`Dashboard.jsx` reestructura el contenido de las pestañas de fase (Formulación/Licitación/Ejecución) en un contenedor con `display: grid; grid-template-columns: 1fr 340px; gap: 2rem;`:
- **Columna principal:** el `municipios-grid`/`municipio-proyectos-view` que ya existe hoy, sin cambios funcionales.
- **Columna lateral (nueva):** `<AlertasPanel proyectos={proyectos} />`.

Cuando `activeTab === 'estadisticas'`, el grid se colapsa a una sola columna (`<Estadisticas proyectos={proyectos} />` ocupa el ancho completo, como hoy) — el panel de alertas es específico de las pestañas de fase, no de la vista analítica.

`<Eventos />` se elimina de `Dashboard.jsx` (import y uso). El archivo `resources/js/components/eventos.jsx` se elimina del repositorio — su única razón de ser (mostrar eventos) queda cubierta, de forma más enfocada, por `AlertasPanel`.

### 2. Fix de backend

En `app/Http/Controllers/ProyectoController.php`, dentro de `eventos()`, se reemplaza:
```php
'eventos.estado',
```
por:
```php
DB::raw('eventos.estado_evento as estado'),
```
Esto hace que `GET /eventos` deje de fallar con 500, sin tocar el frontend (que ya lee `evento.estado`, el alias mantiene ese contrato).

### 3. Datos de demostración

`DemoDataSeeder` se extiende (siguiendo el mismo patrón `updateOrCreate` que ya usa para fases/estados/sectores) para sembrar:
- **Prioridades:** Alta (`#e53935`), Media (`#fbc02d`), Baja (`#43a047`).
- **Tipos de evento:** 4-5 tipos reutilizando los íconos ya mapeados en el frontend (`revision`→🔍, `contrato`→✍️, `inspeccion`→🔧, `documentacion`→📁).
- **Responsables:** 3 nombres de ejemplo.
- **Eventos:** ~8 registros, cada uno ligado a un `proyecto` real ya sembrado (usando sus IDs), con `fecha` variada (algunas próximas, algunas pasadas), `prioridad` variada (para que "Alta" tenga al menos 3-4 eventos de muestra), y `estado_evento` en un valor que no sea `cancelado` ni `completado` para la mayoría (para que se vean en el panel), con 1-2 en `completado` (para probar que el filtro de exclusión funciona).

### 4. `AlertasPanel.jsx` (nuevo componente)

Recibe una sola prop, `proyectos` (el arreglo completo sin filtrar, igual que `KpiStrip`). Internamente:
- Hace su propio `useEffect` + `axios.get('/eventos')` al montar (mismo patrón que tenía `Eventos.jsx`).
- **Bloque "Proyectos en riesgo":** `proyectos.filter(p => p.descripcion_estado === 'Con retraso')`. Cada fila es clickeable; el click se comunica al padre vía una prop `onProyectoClick` (para reutilizar el modal de detalle que ya vive en `Dashboard.jsx`, sin duplicar esa lógica).
- **Bloque "Eventos de alta prioridad":** de los eventos obtenidos, `filter(e => e.nombre === 'Alta' && e.estado !== 'cancelado' && e.estado !== 'completado')`, ordenados por `fecha` ascendente, mostrando título, fecha e ícono (reutilizando `getEventIcon` — se copia esa pequeña función a `AlertasPanel.jsx`, ya que `Eventos.jsx` desaparece).
- Si ambos arreglos están vacíos, un mensaje "Sin alertas activas por ahora." en vez de dejar la columna en blanco.
- Sin paginación ni carrusel — es una lista compacta pensada para caber en una columna angosta sin necesitar interacción adicional.

### 5. CSS

Se agrega el grid de 2 columnas y los estilos del panel nuevo (tarjeta compacta reutilizando visualmente el lenguaje ya establecido: fondo translúcido con blur, bordes redondeados, acento de color por prioridad/riesgo). Se eliminan las reglas CSS que solo servían a `Eventos.jsx` (`.eventos-section`, `.eventos-header`, `.slider-controls`, `.slider-btn`, `.slider-indicator`, `.eventos-slider`, `.eventos-grid`, `.evento-card` y sus sub-reglas, `.auto-rotation-indicator`, `.rotation-dot`), verificando primero que ninguna se use en otro lugar del código.

### 6. Verificación

Sin suite de tests de frontend. Verificación: `php artisan test` (confirma que el fix de backend no rompe nada — no hay test actual para el endpoint de eventos, pero se confirma que el resto de la suite sigue en verde), `npm run dev`, y navegación manual: `GET /eventos` ya no da 500, el panel de alertas muestra proyectos en riesgo reales y eventos de alta prioridad reales, el layout de 2 columnas se ve correctamente en un viewport de 1920x1080 sin necesitar scroll en las pestañas de fase (Estadísticas puede seguir con su scroll interno), y los demás flujos (modal de proyecto, tarjetas de municipio, KPIs) siguen funcionando sin cambios.

## Decisiones registradas

- El panel de alertas reemplaza por completo la sección "Próximos Eventos"; `Eventos.jsx` se elimina, no se mantiene en paralelo.
- "Con retraso" sigue siendo el único criterio de riesgo (consistente con `KpiStrip`/`MunicipioCard`) — no se inventa una nueva categoría.
- Se corrige el bug de `eventos.estado`/`estado_evento` y se siembran datos de demo para eventos/prioridades/tipos/responsables, porque el panel de alertas no tiene sentido sin datos reales que mostrar — esto es un cambio de backend mínimo y justificado, no scope creep general.
- Cero scroll aplica a las pestañas de fase; Estadísticas mantiene su propio scroll interno, dado el volumen real de sus datos.
- El mapa esquemático de municipios queda fuera de este documento, como una iteración siguiente.
