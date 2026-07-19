# Diseño: Cadena Presupuestal en Estadísticas

**Fecha:** 2026-07-18
**Estado:** Aprobado
**Autor:** Xiamir Luquez (con Claude)

## Contexto

El dashboard muestra hoy el presupuesto como un número suelto en la tira de KPIs:
"Presupuesto total: $17.012 M". Ese valor es la suma de `proyectos.presupuesto`,
es decir **lo planeado**, y no permite responder las dos preguntas siguientes:

- ¿Cuánto de eso se llegó a contratar?
- ¿Cuánto se ha facturado realmente?

Los tres niveles existen en la base de datos, pero nunca se han puesto juntos.
Con las cifras actuales:

| Nivel | Fuente | Valor |
|---|---|---|
| Planeado | `Σ proyectos.presupuesto` | $17.012 M |
| Contratado | `Σ contratos.valor` | $18.072 M |
| Ejecutado | `Σ avance_financiero.valor_facturado` | $3.487 M |

Lo contratado supera lo planeado en ~$1.060 M. Puede ser legítimo (adiciones,
contratos que cubren varios proyectos) o una señal a revisar — pero hoy el
dashboard no permite verlo.

## Objetivo

Agregar un bloque de **cadena presupuestal** (Planeado → Contratado → Ejecutado)
en la pestaña "Estadísticas y Filtros", que responda a los filtros ya existentes
de esa vista.

## Decisiones de diseño

| Decisión | Elección | Razón |
|---|---|---|
| Ubicación | Pestaña "Estadísticas y Filtros" | Ya opera sobre los 31 proyectos y tiene filtros. En la vista de fase habría chocado: cifras globales junto a un mapa filtrado por fase. |
| Posición | Arriba, antes de las gráficas existentes | Responde la pregunta de encabezado ("¿cómo vamos en plata?"). Debajo de cuatro gráficas quedaría enterrada. |
| Reactividad | Responde a los filtros existentes | Todas las gráficas de la pestaña usan `proyectosFiltrados`; la cadena hace lo mismo. Permite ver la cadena por municipio, fase, estado o rango de fechas. |
| Aviso de sobre-contratación | Condicional | Solo se muestra cuando contratado > planeado. Si no hay nada que señalar, no aparece: evita ruido permanente. |
| Backend | Sin cambios | El prop `proyectos` ya trae los contratos anidados con `valor` y `avancesFinancieros`. |

## Fuente de datos

`Estadisticas` recibe el prop `proyectos` desde `Dashboard.jsx`, alimentado por
`GET /GestPro/proyectos`. Cada proyecto incluye:

- `presupuesto` — decimal, lo planeado.
- `contratos[]` — cada uno con `valor` (valor vigente, ya incluye adiciones) y
  `avancesFinancieros[]` con `valor_facturado`.

Los tres totales se calculan sobre `proyectosFiltrados` (la lista que ya produce
el componente tras aplicar los filtros), no sobre `proyectos`.

```
planeado   = Σ proyectosFiltrados.presupuesto
contratado = Σ contratos.valor              (de los proyectos filtrados)
ejecutado  = Σ avancesFinancieros.valor_facturado  (de esos contratos)
```

**Nota sobre `contratos.valor`:** es el valor *vigente*, ya recalculado para
incluir adiciones/otrosíes. Es el denominador correcto y es consistente con el
resto del sistema.

## Interfaz

Bloque nuevo en `Estadisticas.jsx`, ubicado **después de la sección de filtros y
antes de la primera gráfica**.

### Contenido

Título: **💰 Cadena presupuestal**, seguido del conteo de proyectos considerados
cuando hay algún filtro activo (ej. "— 7 proyectos en MEDELLÍN").

Tres barras horizontales apiladas verticalmente, cada una con su etiqueta a la
izquierda y su valor formateado a la derecha:

1. **📋 Planeado** — gris (`#64748b`).
2. **📝 Contratado** — azul (`#2563eb`). A la derecha del valor, el porcentaje
   respecto de lo planeado (ej. "106% de lo planeado").
3. **💵 Ejecutado (facturado)** — verde (`#16a34a`). A la derecha, el porcentaje
   respecto de lo contratado (ej. "19% de lo contratado").

El ancho de cada barra es proporcional al mayor de los tres valores, de modo que
la barra más grande ocupe el 100% y las demás se lean en escala comparativa.

Los importes usan el helper `formatearPresupuesto` ya existente en el archivo
(formato `$17.012 M`).

### Aviso condicional

Cuando `contratado > planeado`, debajo de las barras aparece una franja ámbar:

> ⚠️ Lo contratado supera lo planeado en **$X M**.

Si `contratado <= planeado`, no se renderiza nada.

### Casos borde

- **Sin proyectos filtrados** (el filtro no arroja resultados): el bloque muestra
  "Sin proyectos que coincidan con los filtros" en lugar de barras en cero.
- **Planeado = 0** (posible al filtrar): no se calcula el porcentaje de contratado
  sobre planeado; se omite ese texto en vez de dividir por cero.
- **Contratado = 0**: no se calcula el porcentaje de ejecutado; se omite el texto.
- Un proyecto sin contratos aporta 0 a contratado y ejecutado, pero sí aporta su
  presupuesto a lo planeado. Es correcto: refleja lo planeado aún sin contratar.

## Estructura de archivos

- **Crear:** `resources/js/utils/cadenaPresupuestal.js` — función pura
  `calcularCadenaPresupuestal(proyectos)` que devuelve
  `{ planeado, contratado, ejecutado, pctContratado, pctEjecutado, excedente }`.
  Aislada para poder probarla sin renderizar React.
- **Crear:** `resources/js/utils/cadenaPresupuestal.test.js` — tests Vitest.
- **Modificar:** `resources/js/components/Estadisticas.jsx` — el bloque visual,
  consumiendo la función anterior sobre `proyectosFiltrados`.

## Pruebas (Vitest, funciones puras)

1. Suma los tres niveles correctamente a partir de proyectos con contratos
   anidados y actas.
2. Un proyecto sin contratos aporta a `planeado` pero no a `contratado` ni
   `ejecutado`.
3. Un contrato sin actas aporta a `contratado` pero no a `ejecutado`.
4. `pctContratado` = contratado/planeado × 100, redondeado a entero.
5. `pctEjecutado` = ejecutado/contratado × 100, redondeado a entero.
6. Con `planeado = 0`, `pctContratado` es `null` (no Infinity ni NaN).
7. Con `contratado = 0`, `pctEjecutado` es `null`.
8. `excedente` = contratado − planeado cuando es positivo; `0` cuando contratado
   ≤ planeado.
9. Lista vacía devuelve los tres totales en 0 y ambos porcentajes en `null`.
10. Valores decimales en string (como los entrega la API, ej. `"890000000.00"`)
    se suman correctamente.

## Fuera de alcance (YAGNI)

- La distribución por sector (propuesta C) y la corrección del bug del
  presupuesto en el modal (propuesta D): el usuario decidió dejarlas fuera.
- Desglose de la cadena por sector o por municipio dentro del bloque.
- Gráfica de evolución temporal del gasto.
- Cambios en la tira de KPIs (`KpiStrip`), incluido el cuadro "Finalizados"
  hardcodeado en 0.
- Exportación de las cifras.

## Limitación conocida

Solo 5 de los 31 proyectos tienen contratos asociados, y hay 2 actas financieras
en total. Al filtrar por la mayoría de municipios, contratado y ejecutado
aparecerán en 0. El comportamiento es correcto — refleja el estado real de los
datos — pero el bloque solo será plenamente informativo a medida que se carguen
más contratos y actas.
