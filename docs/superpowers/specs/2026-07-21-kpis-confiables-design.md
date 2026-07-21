# Diseño: KPIs Confiables en el Dashboard

**Fecha:** 2026-07-21
**Estado:** Aprobado
**Autor:** Xiamir Luquez (con Claude)

## Contexto

La tira de KPIs (`KpiStrip.jsx`) muestra seis indicadores. Un análisis de la
confiabilidad de sus fuentes de datos encontró tres problemas:

### 1. "Avance promedio: 43%" — dato no confiable

Se calcula como el promedio aritmético de `proyectos.progreso`
(`KpiStrip.jsx:11-13`). Ese campo:

- **Nunca se escribe desde la aplicación.** Una búsqueda en todo el código
  muestra que solo aparece en el `fillable` del modelo y en
  `DemoDataSeeder.php:147`, donde se genera con `rand(-5, 5)`.
- **Contradice los datos reales.** Hay proyectos con 59% de "progreso" y cero
  contratos asociados; sin contrato no hay obra ejecutándose.
- **No es ni avance físico ni financiero.** Esos viven en `contratos`
  (`avance_fisico` / `avance_financiero`) y se derivan de actas y actividades.
  `progreso` es un tercer indicador sin relación con ellos.

### 2. "Finalizados: 0" — cuadro muerto

Está literalmente hardcodeado (`KpiStrip.jsx:14`: `const finalizados = 0;`) y no
existe un estado "Finalizado" en el catálogo (los estados son: Aprobado, Con
retraso, En ejecución, En estructuración, En evaluación, Publicado). Nunca
mostrará otro valor.

### 3. "Presupuesto total" — redundante

La cadena presupuestal agregada en la pestaña "Estadísticas y Filtros" ya muestra
ese valor con contexto (Planeado → Contratado → Ejecutado).

## Confiabilidad de las fuentes (medida sobre los datos actuales)

| Fuente | Cobertura | Veredicto |
|---|---|---|
| `presupuesto`, `estado`, `fase` | 31/31 | Completo |
| Contratos (`valor`) | 5/31 proyectos, 7 contratos | Parcial pero real |
| Actas (`valor_facturado`) | 2 contratos | Escaso pero real |
| `progreso` | 30/31 | Generado por el seeder con `rand()` |

## Objetivo

Reemplazar los tres KPIs problemáticos por indicadores calculados desde datos
verificables, manteniendo la tira en seis cuadros.

## Decisiones de diseño

| KPI actual | Reemplazo | Razón |
|---|---|---|
| Avance promedio (43%) | **Ejecución financiera** (19%) | Sale de actas reales. Coincide con la cadena presupuestal, así que KPI y bloque cuentan la misma historia. |
| Finalizados (0) | **Proyectos contratados** (5 de 31) | Mide cuántos pasaron de planeación a contrato. Datos completos. |
| Presupuesto total | **Distribución por fase** (10·10·11) | Muestra dónde se concentra la cartera. El presupuesto ya lo cubre la cadena. |

Se conservan sin cambios: **Total proyectos**, **Total de contratos**, **En riesgo**.

## Especificación de los KPIs

### Conservados

1. **Total proyectos** — `proyectos.length`.
2. **Total de contratos** — suma de `contratos.length` de cada proyecto.
3. **En riesgo** — proyectos con `descripcion_estado === 'Con retraso'`.

### Nuevos

4. **Ejecución financiera**
   `Σ valor_facturado / Σ contratos.valor × 100`, redondeado a entero.
   Se reutiliza `calcularCadenaPresupuestal` (ya probada, en
   `resources/js/utils/cadenaPresupuestal.js`): el valor es su `pctEjecutado`.
   Si `pctEjecutado` es `null` (no hay contratado), se muestra `—`, no `0%`.
   Etiqueta: "Ejecución financiera". Icono 💵.

5. **Proyectos contratados**
   Cantidad de proyectos con al menos un contrato, sobre el total.
   Se muestra como `5 / 31`, con el porcentaje como subtítulo o title.
   Etiqueta: "Contratados". Icono 🤝.

6. **Distribución por fase**
   Conteo por `descripcion_fase`, mostrado como `10 · 10 · 11` con las
   iniciales de cada fase debajo (ej. "Form · Lic · Ejec").
   Las fases se toman dinámicamente de los datos (no hardcodeadas), ordenadas
   alfabéticamente para que el orden sea estable entre renders.
   Etiqueta: "Por fase". Icono 🔄.

### Casos borde

- **Sin proyectos** (`proyectos` vacío): todos los conteos en 0, ejecución
  financiera en `—`, y la distribución por fase muestra `—`.
- **Sin contratos**: ejecución financiera muestra `—` (no `0%`, que sugeriría
  que se midió y dio cero).
- **Proyecto sin fase asignada**: se agrupa bajo "Sin fase" en la distribución.

## Estructura de archivos

- **Crear:** `resources/js/utils/kpisDashboard.js` — función pura
  `calcularKpis(proyectos)` que devuelve
  `{ total, totalContratos, enRiesgo, pctEjecucion, contratados, porFase }`.
  `porFase` es un arreglo `[{ fase, cantidad }]` ordenado alfabéticamente.
- **Crear:** `resources/js/utils/kpisDashboard.test.js` — tests Vitest.
- **Modificar:** `resources/js/components/KpiStrip.jsx` — consume la función y
  renderiza los seis cuadros.

Sin cambios en backend: `KpiStrip` ya recibe el prop `proyectos` con los
contratos anidados y sus actas.

## Pruebas (Vitest, funciones puras)

1. `total`, `totalContratos` y `enRiesgo` se calculan como hoy (no regresión).
2. `pctEjecucion` = facturado/contratado × 100 redondeado.
3. `pctEjecucion` es `null` cuando no hay contratos (para mostrar `—`).
4. `contratados` cuenta proyectos con al menos un contrato, no contratos totales
   (un proyecto con 3 contratos cuenta como 1).
5. Un proyecto con `contratos: []` no cuenta como contratado.
6. `porFase` agrupa correctamente y ordena alfabéticamente.
7. Un proyecto sin `descripcion_fase` se agrupa como "Sin fase".
8. Lista vacía devuelve ceros, `pctEjecucion` en `null` y `porFase` vacío.
9. Tolera `proyectos` nulo o indefinido sin romperse.
10. Tolera contratos sin `avancesFinancieros` y proyectos sin `contratos`.

## Fuera de alcance (YAGNI)

- Decidir el destino del campo `progreso` (mantenerlo con un formulario que lo
  alimente, o eliminarlo del modelo). Queda como deuda señalada.
- Avance físico global: solo 2 contratos tienen actividades registradas;
  mostrarlo como cifra global sería engañoso.
- Cambios en la cadena presupuestal o en la pestaña Contratos.
- Ponderar la ejecución financiera por presupuesto.

## Limitación conocida

La ejecución financiera (19%) se apoya en **2 actas** de 7 contratos. Es un dato
real, pero de muestra pequeña: se moverá bastante conforme se registren más
actas. Es más confiable que el 43% aleatorio que reemplaza, pero no es todavía
una medición robusta.
