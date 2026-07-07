# KPIs ejecutivos y tarjeta de municipio ampliada

**Fecha:** 2026-07-07
**Estado:** Aprobado, pendiente de plan de implementación

## Contexto

Tras el rediseño de la tarjeta de municipio (`MunicipioCard.jsx`, ver `docs/superpowers/specs/2026-07-07-dashboard-municipio-card-design.md`), se pidió una propuesta gráfica más amplia para el Dashboard, evaluada con estándar de dashboards ejecutivos (Power BI/Monday/Asana). Esa propuesta identificó varios subsistemas independientes (KPIs+alertas, librería de gráficas, mapa, filtros, exportación, modo oscuro/tiempo real). Este spec cubre el primer subsistema, elegido por ser el de mayor impacto con menor riesgo técnico: **KPIs ejecutivos globales + ampliar la tarjeta de municipio con presupuesto, avance y riesgo**.

Todo lo necesario ya existe en los datos que `GET /proyectos` devuelve hoy — cada proyecto incluye `presupuesto`, `progreso`, `descripcion_estado`, y un arreglo `contratos`. No se requiere ningún cambio de backend.

## Alcance

Incluye:
- Un componente `KpiStrip.jsx` con 6 indicadores globales, visible siempre (no cambia por pestaña de fase): Total de proyectos, Presupuesto total, Total de contratos, Proyectos en riesgo, % de avance promedio, Proyectos finalizados.
- Ampliar `MunicipioCard.jsx` (ya existente, ver spec anterior) para mostrar, además de lo que ya tiene: presupuesto total del municipio, barra de % de avance promedio, etiqueta de estado general, y un botón "Ver detalle" explícito.

No incluye (fuera de alcance, subsistemas futuros ya identificados en la propuesta gráfica):
- Indicadores de tendencia (↑/↓, % de variación) — requieren un histórico de snapshots que no existe; se decidió explícitamente omitirlos en esta iteración en vez de inventar datos.
- Panel de alertas, mapa de Antioquia, librería de gráficas real, filtros globales, exportación, modo oscuro, tiempo real — cada uno es un subsistema propio, con su propio spec futuro.
- Cualquier cambio a `ProyectoController.php`, a las rutas, o a la base de datos.
- Redefinir la lógica de avance por fase que ya existe en `Dashboard.jsx` (`calcularAvanceProyecto`) — se decidió usar la columna real `proyectos.progreso` en su lugar, sin tocar esa lógica existente que sigue usándose donde ya se usa hoy.

## Diseño

### 1. `KpiStrip.jsx` (nuevo componente)

Recibe una sola prop: `proyectos` (el arreglo completo sin filtrar por fase, el mismo que `Dashboard.jsx` ya obtiene de `GET /proyectos`). Calcula, sin estado ni efectos, puramente a partir de esa prop:

- **Total de proyectos:** `proyectos.length`.
- **Presupuesto total:** suma de `proyecto.presupuesto` (numérico, viene como string desde la API por el cast `decimal:2` de Eloquent — se convierte con `parseFloat` antes de sumar).
- **Total de contratos:** suma de `proyecto.contratos.length` de cada proyecto (el payload de `/proyectos` ya incluye `contratos` anidados por proyecto, usado hoy en el modal de detalle). No se filtra por `estado` del contrato porque esa columna es texto libre sin valores sembrados en los datos de demostración — filtrar por "activo" inventaría una categoría que los datos reales no sustentan hoy, así que se etiqueta y calcula honestamente como el total.
- **En riesgo:** cantidad de proyectos con `descripcion_estado === 'Con retraso'` — el mismo estado real del catálogo que ya existe, sin inventar una regla de riesgo nueva.
- **% de avance promedio:** promedio de `proyecto.progreso` (columna real, numérica 0-100) sobre todos los proyectos.
- **Finalizados:** fijo en `0`, con un atributo `title` (tooltip nativo) explicando "Aún no existe un estado de tipo Finalizado en el catálogo" — se muestra la tarjeta para no romper la expectativa de 6 KPIs, pero de forma honesta.

Sin flechas de tendencia. La tarjeta de "En riesgo" usa acento rojo (`--red: #e53935`); el resto usa el azul de marca ya existente (`--brand: #1976d2`). Se renderiza una sola vez en `Dashboard.jsx`, antes del bloque de pestañas, fuera del `.map()` de fases — por eso los números no cambian al cambiar de pestaña.

### 2. Ampliar `MunicipioCard.jsx`

No se agregan props nuevas: el componente ya recibe `proyectos` (los del municipio+fase actual) y `faseNombre`. Con esos mismos datos, además del cálculo de segmentos por estado que ya tiene, se calcula:

- **Presupuesto total:** suma de `proyecto.presupuesto` de los proyectos recibidos.
- **% de avance promedio:** promedio de `proyecto.progreso` de los proyectos recibidos.
- **Estado general:** si algún proyecto tiene `descripcion_estado === 'Con retraso'`, se muestra "▲ N en riesgo" (N = cantidad); si no hay ninguno, se muestra "● Buen ritmo".
- **Botón "Ver detalle":** un `<button>` visible al final de la tarjeta que llama al mismo `onClick` que ya recibe el componente. La tarjeta completa sigue siendo clickeable como hoy (se mantiene el comportamiento actual); el botón es un refuerzo visual de affordance, no un cambio de comportamiento.

### 3. Datos y cálculo

Todo se deriva en el cliente a partir del payload que `/proyectos` ya devuelve — no hay llamadas nuevas a la API, no hay cambios de backend. `presupuesto` y `progreso` llegan como los tipos que Eloquent ya castea (`decimal:2` como string, `integer` como número); `KpiStrip` y `MunicipioCard` normalizan `presupuesto` con `parseFloat` antes de sumar.

### 4. Verificación

No existe suite de tests de frontend en este proyecto. Verificación: `npm run dev` (build limpio) + navegación manual en navegador confirmando que los 6 KPIs muestran números coherentes con los datos reales sembrados, que no cambian al cambiar de pestaña de fase, y que cada tarjeta de municipio muestra presupuesto/avance/riesgo/botón correctos y el clic (tanto en la tarjeta como en el botón) sigue navegando al detalle igual que antes.

## Decisiones registradas

- KPIs son globales (agregan las 3 fases), siempre visibles, no cambian por pestaña.
- Sin indicadores de tendencia en esta iteración — se evita mostrar comparaciones inventadas sin un histórico real.
- "En riesgo" = estado real `Con retraso`; "Finalizados" = 0 honesto (no se inventa un estado que no existe).
- Fuente de avance: columna `proyectos.progreso`, no la lógica de cálculo por fase que ya existe en `Dashboard.jsx` (esa lógica no se toca, sigue usándose donde ya se usaba).
- La tarjeta de municipio sigue siendo completamente clickeable; el botón "Ver detalle" se suma sin quitar el comportamiento actual.
