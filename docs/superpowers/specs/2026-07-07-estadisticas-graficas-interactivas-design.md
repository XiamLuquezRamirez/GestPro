# Estadísticas: filtros y gráficas profesionales interactivas

**Fecha:** 2026-07-07
**Estado:** Aprobado, pendiente de plan de implementación

## Contexto

La pestaña "Estadísticas y Filtros" del Dashboard (dentro de `Dashboard.jsx`) usa hoy gráficas hechas a mano con `<div>`s (barras con `width: %`, un pie chart con `conic-gradient`), varias con datos incorrectos o inventados:

- `getMunicipioColor()` tiene hardcodeados 4 municipios (`Valledupar`, `Aguachica`, `Codazzi`, `La Paz`) que **no existen** en los datos reales (los municipios reales son Medellín, Bello, Itagüí, Envigado, Rionegro) — todas las barras por municipio caen hoy al color gris de respaldo.
- Existe una gráfica "Presupuesto Mensual por Municipio" oculta (`style={{ display: 'none' }}`) que genera sus valores con `Math.random()`.
- La función `getDatosEvolucionTemporal()` genera un calendario de 12 meses también con `Math.random()` y no se usa en ningún lado (código muerto).
- `getEstadoColor()` y `getFaseColor()` hardcodean colores que ya existen y vienen del backend (`proyecto.color_estado`, `proyecto.color_fase`), duplicando una fuente de verdad.
- Las "tarjetas de resumen" (Total Proyectos, 3 estados, Presupuesto Total) dentro de esta pestaña repiten lo que el `KpiStrip` global (ya implementado arriba de las pestañas) muestra siempre.
- No existe ningún filtro real e interactivo — la sección "Filtros por Estado" es una lista estática agrupada por estado, sin controles.

El usuario pidió: reemplazar las gráficas actuales por unas profesionales e interactivas, agregar filtros, agregar gráficas nuevas con datos reales que aporten información, y usar una librería adecuada para esto.

## Alcance

Incluye:
- Extraer toda la lógica y el marcado de la pestaña Estadísticas de `Dashboard.jsx` a un componente nuevo `Estadisticas.jsx`, que recibe `proyectos` (el arreglo completo, sin filtrar por fase) como única prop.
- Instalar `recharts` como librería de gráficas.
- Una barra de filtros global (municipio, fase, estado, rango de fechas por `fecha_inicio`) que afecta únicamente las gráficas y la tabla de esta pestaña.
- 6 gráficas (ver diseño abajo), todas reactivas a los filtros.
- Una tabla de resultados filtrados que reemplaza la sección estática "Filtros por Estado".
- Eliminar código muerto/deshonesto: la gráfica oculta de presupuesto mensual, `getDatosEvolucionTemporal()`, y los mapas de color hardcodeados (`getEstadoColor`, `getFaseColor`, `getMunicipioColor`).
- Eliminar las tarjetas de resumen duplicadas de esta pestaña.
- CSS nuevo, reemplazando las reglas que queden sin uso en `Dashboard-Extras.css`.

No incluye:
- Ningún cambio de backend, rutas o base de datos — todo se deriva de los campos que `GET /proyectos` ya devuelve.
- Cambios al `KpiStrip` global ni a las demás pestañas (Formulación/Licitación/Ejecución) — los filtros de Estadísticas no afectan nada fuera de esta pestaña.
- Gráficas de tendencia/comparación temporal que requerirían un histórico de snapshots que no existe (se usa `fecha_inicio`, que sí es un dato real, para la única gráfica con eje temporal).

## Diseño

### 1. Librería: Recharts

Se eligió Recharts sobre ApexCharts: es una librería nativa de React (componentes declarativos, no una API imperativa envuelta), más liviana, con tooltips/leyendas/responsive-container incorporados, y se integra bien con el estilo azul/blanco ya establecido del dashboard.

### 2. Nuevo componente `Estadisticas.jsx`

`Dashboard.jsx` deja de renderizar el bloque `activeTab === 'estadisticas'` inline y en su lugar renderiza `<Estadisticas proyectos={proyectos} />`. Esto reduce el tamaño de `Dashboard.jsx` (actualmente 778 líneas) y aísla toda la lógica de filtrado/gráficas en un archivo dedicado, siguiendo el mismo patrón ya usado para `KpiStrip.jsx` y `MunicipioCard.jsx`.

Dentro de `Estadisticas.jsx`, el estado de filtros (`municipio`, `fase`, `estado`, `fechaDesde`, `fechaHasta`) vive en ese componente. Se deriva `proyectosFiltrados` con un `.filter()` sobre la prop `proyectos`, y ese arreglo filtrado (no el original) alimenta las 6 gráficas y la tabla de resultados.

### 3. Barra de filtros

Cuatro controles en una fila (usando `<select>` nativo, igual que el resto de la app, por ejemplo `Parametros.jsx`):
- **Municipio:** "Todos" + lista de `descripcion_municipio` únicos presentes en `proyectos`.
- **Fase:** "Todos" + lista de `descripcion_fase` únicos.
- **Estado:** "Todos" + lista de `descripcion_estado` únicos.
- **Fecha:** dos `<input type="date">` (Desde/Hasta) que filtran por `proyecto.fecha_inicio`.

Un botón "Limpiar filtros" resetea los 5 valores. Todo vive en `useState` local a `Estadisticas.jsx`.

### 4. Las 6 gráficas

Todas reciben `proyectosFiltrados`, no la prop original.

1. **Proyectos por Estado** — `BarChart` horizontal (Recharts, `layout="vertical"`). Una barra por estado presente, coloreada con el `color_estado` real de sus proyectos (no un mapa hardcodeado). Tooltip muestra cantidad y % del total filtrado.
2. **Proyectos por Fase** — `PieChart` tipo dona (`innerRadius`/`outerRadius`). Coloreada con `color_fase` real. Leyenda con cantidad y %.
3. **Proyectos por Municipio** — `BarChart` horizontal, ordenado descendente por cantidad. Como no existe un color de municipio en la base de datos, se usa una paleta categórica fija de 8 colores (ciclando si hiciera falta), asignada en el mismo orden en que aparecen los municipios — la misma asignación municipio→color se reutiliza en la gráfica 4 para que ambas hablen el mismo idioma visual.
4. **Presupuesto por Municipio** — `BarChart` horizontal, ordenado descendente por `proyecto.presupuesto` sumado por municipio. Usa `parseFloat(proyecto.presupuesto) || 0` — el mismo campo que ya usa `KpiStrip.jsx`, no `totalPresupuesto` (una suma distinta, de componentes de presupuesto, que se usa en el modal de detalle de proyecto y queda fuera de este alcance). Mismos colores por municipio que la gráfica 3.
5. **Avance promedio por Fase** — `BarChart` (una barra por fase), con el promedio de `parseInt(proyecto.progreso, 10) || 0` de los proyectos filtrados de esa fase. Mismo `color_fase` real que la gráfica 2.
6. **Proyectos iniciados por mes** — `BarChart`, agrupando `proyecto.fecha_inicio` por mes-año real (por ejemplo "Ene 2026"), ordenado cronológicamente. Reemplaza honestamente la antigua gráfica de "evolución temporal" que generaba valores con `Math.random()` — esta usa una fecha real que ya existe en cada proyecto. Color único (azul de marca `#1976d2`), ya que es una sola serie.

Todas las gráficas se envuelven en `ResponsiveContainer` de Recharts y muestran un mensaje simple ("Sin datos para el filtro actual") en vez de una gráfica vacía cuando `proyectosFiltrados.length === 0`.

### 5. Tabla de resultados filtrados

Reemplaza la sección "Filtros por Estado". Una tabla HTML simple (`<table>`) debajo de las gráficas, con una fila por proyecto en `proyectosFiltrados`: Nombre, Municipio, Fase, Estado (con una pastilla de color usando `color_estado` real), Presupuesto (`proyecto.presupuesto` formateado), Avance (`proyecto.progreso`%). Si `proyectosFiltrados.length === 0`, muestra "No hay proyectos que coincidan con los filtros seleccionados."

### 6. Limpieza de código muerto/deshonesto

En `Dashboard.jsx`, al mover la lógica de Estadísticas a su propio componente, se eliminan (no se migran) estas funciones porque ya no se usan o nunca debieron generar datos falsos:
- `getDatosEvolucionTemporal()` — no usada en ningún lado, genera datos con `Math.random()`.
- `getDatosPresupuestoMensual()` — solo usada por la gráfica oculta que se elimina.
- `getEstadoColor()`, `getFaseColor()`, `getMunicipioColor()` — se reemplazan por el uso directo de `proyecto.color_estado`/`proyecto.color_fase` reales, y por la paleta categórica fija para municipios dentro de `Estadisticas.jsx`.

El bloque JSX completo de la gráfica oculta "Presupuesto Mensual por Municipio" (`style={{ display: 'none' }}`) se elimina junto con su función de datos.

### 7. CSS

Las reglas de `Dashboard-Extras.css` que quedan sin ningún uso tras este cambio (`.resumen-cards`, `.resumen-card*`, `.grafica-barras`, `.barra-item/label/container/fill/valor`, `.grafica-pie-real`, `.pie-chart`, `.pie-slice`, `.pie-legend`, `.barra-apilada*`, `.barra-seccion*`, `.filtros-section .filtro-card*`) se eliminan. Se agregan reglas nuevas para: la barra de filtros, el grid de tarjetas de gráfica (reutilizando el patrón visual `.grafica-card` ya existente para el contenedor, ya que Recharts se renderiza dentro), y la tabla de resultados.

### 8. Verificación

Sin suite de tests de frontend en este proyecto. Verificación: `npm run dev` (build limpio) + navegación manual confirmando que las 6 gráficas muestran datos reales y coherentes, que los filtros (cada select, el rango de fechas y "Limpiar filtros") efectivamente cambian lo que muestran las 6 gráficas y la tabla, que el `KpiStrip` global y las demás pestañas no cambian al filtrar aquí, y que no quedan referencias rotas a las funciones/clases eliminadas.

## Decisiones registradas

- Recharts, no ApexCharts (más liviano, más "nativo" de React).
- Filtros en una barra global única para esta pestaña, no un control por gráfica.
- Se elimina, no se conserva, el código de datos inventados (`Math.random()`) y los mapas de color hardcodeados y desactualizados.
- Se quitan las tarjetas de resumen duplicadas del `KpiStrip`.
- Presupuesto en las gráficas nuevas usa `proyecto.presupuesto` (consistente con `KpiStrip`), no `totalPresupuesto`.
- Sin gráfica de tendencia inventada; la única gráfica con eje temporal usa `fecha_inicio` real.
