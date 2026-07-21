# Diseño: Avance Real en los Componentes del Dashboard

**Fecha:** 2026-07-21
**Estado:** Aprobado
**Autor:** Xiamir Luquez (con Claude)

## Contexto

Los KPIs globales ya migraron a datos verificables
(`2026-07-21-kpis-confiables-design.md`), pero el campo `proyectos.progreso`
—que nunca se escribe desde la aplicación y cuyos valores provienen de
`DemoDataSeeder` con `rand()`— sigue alimentando tres puntos del dashboard:

1. `ResumenFase.jsx:9-11` — stat "Avance promedio" del panel lateral, con barra.
2. `DistribucionMunicipios.jsx:19-21` — columna "Avance promedio" de la tabla por
   municipio, con barra.
3. `Estadisticas.jsx:98-105` — gráfica de barras "Avance promedio por Fase".

Hoy muestran cifras que parecen plausibles (41-46% según el municipio) pero son
inventadas. Un directivo puede estar decidiendo sobre la base de que Rionegro va
al 44% cuando ese municipio no tiene un solo contrato registrado.

## Cobertura real de datos

| Agrupación | Con contratos | Sin contratos |
|---|---|---|
| Municipios | MEDELLÍN (5), BELLO (2) | ENVIGADO, RIONEGRO, ITAGÜÍ, DISTRACCIÓN |
| Fases | Ejecución (5 de 11 proyectos) | Formulación (0/10), Licitación (0/10) |

Formulación y Licitación no tienen contratos **por definición de su etapa**: son
fases previas a la contratación. No es un vacío de carga, es la naturaleza del
proceso.

## Objetivo

Sustituir el avance basado en `progreso` por avance financiero real derivado de
contratos, mostrando explícitamente "sin contratos" donde no hay base para medir,
en lugar de un 0% o un porcentaje inventado.

## Decisiones de diseño

| Decisión | Elección | Razón |
|---|---|---|
| Fuente del avance | Ejecución financiera (facturado / contratado) | Único avance verificable hoy. Consistente con los KPIs y la cadena presupuestal. |
| Grupos sin contratos | Mostrar `—` con leyenda "Sin contratos" | Un 0% se leería como "no avanza"; el guion comunica "no hay base para medir". |
| Barra de progreso | Se oculta cuando no hay dato | Una barra vacía al 0% transmite la idea equivocada de avance nulo. |
| Reutilización | Una sola función pura para los tres | La fórmula es idéntica; solo cambia la agrupación. |

## Especificación

### Función `calcularAvanceGrupo(proyectos)`

Devuelve `{ pct, tieneContratos }`:

- `tieneContratos`: `true` si algún proyecto del grupo tiene al menos un contrato.
- `pct`: ejecución financiera del grupo — `Σ valor_facturado / Σ contratos.valor × 100`,
  redondeado a entero. Es `null` cuando `tieneContratos` es `false`.

Se apoya en `calcularCadenaPresupuestal` (ya probada, en
`resources/js/utils/cadenaPresupuestal.js`), tomando su `pctEjecutado`.

**Caso borde relevante:** un grupo puede tener contratos pero ninguna acta
(`tieneContratos: true`, `pct: 0`). Ahí el 0% **sí es correcto**: hay contratos
firmados y nada facturado todavía. Se muestra `0%` con barra, no guion.

### Presentación por componente

Los tres muestran lo mismo bajo la etiqueta **"Ejecución financiera"** (ya no
"Avance promedio", que era ambiguo):

1. **`ResumenFase`** — el stat muestra `{pct}%` con barra cuando hay contratos;
   `—` sin barra y con la leyenda "Sin contratos en esta fase" cuando no.

2. **`DistribucionMunicipios`** — encabezado de columna pasa a "Ejecución
   financiera". La celda muestra barra + porcentaje, o `—` con el texto
   "Sin contratos" en gris cuando el municipio no tiene ninguno.

3. **`Estadisticas`** — la gráfica pasa a titularse "Ejecución financiera por
   Fase". Las fases sin contratos **no se grafican** (se excluyen del dataset) y
   se listan debajo como nota: "Sin contratos registrados: Formulación,
   Licitación". Graficarlas en 0 daría una barra visualmente idéntica a "cero
   avance real".

### Textos exactos

- Guion: `—` (raya em, no guion simple).
- `ResumenFase`: etiqueta "Ejecución financiera"; leyenda "Sin contratos en esta fase".
- `DistribucionMunicipios`: columna "Ejecución financiera"; celda "Sin contratos".
- `Estadisticas`: título "Ejecución financiera por Fase"; nota "Sin contratos
  registrados: {lista}".

## Estructura de archivos

- **Modificar:** `resources/js/utils/kpisDashboard.js` — agregar y exportar
  `calcularAvanceGrupo`. Va aquí porque ya importa `calcularCadenaPresupuestal`
  y es el módulo de indicadores del dashboard.
- **Modificar:** `resources/js/utils/kpisDashboard.test.js` — tests de la nueva
  función.
- **Modificar:** `resources/js/components/ResumenFase.jsx`
- **Modificar:** `resources/js/components/DistribucionMunicipios.jsx`
- **Modificar:** `resources/js/components/Estadisticas.jsx`

Sin cambios en backend: los tres componentes ya reciben proyectos con contratos
y actas anidados.

## Pruebas (Vitest)

1. Un grupo con contratos y actas devuelve el porcentaje correcto y
   `tieneContratos: true`.
2. Un grupo sin ningún contrato devuelve `pct: null` y `tieneContratos: false`.
3. Un grupo con contratos pero sin actas devuelve `pct: 0` y
   `tieneContratos: true` (el 0% es real, no ausencia de datos).
4. Un grupo vacío devuelve `pct: null` y `tieneContratos: false`.
5. Tolera entradas nulas, proyectos sin `contratos` y contratos sin
   `avancesFinancieros`.
6. El porcentaje se redondea a entero.

## Verificación manual

Tras implementar, en el dashboard debe verse:

- Fase **Ejecución** → ResumenFase muestra un porcentaje real con barra.
- Fases **Formulación** y **Licitación** → ResumenFase muestra `—` y
  "Sin contratos en esta fase".
- Tabla de municipios → MEDELLÍN y BELLO con porcentaje; ENVIGADO, RIONEGRO,
  ITAGÜÍ y DISTRACCIÓN con `—` y "Sin contratos".
- Estadísticas → la gráfica solo muestra la barra de Ejecución, con la nota
  "Sin contratos registrados: Formulación, Licitación".

## Fuera de alcance (YAGNI)

- Eliminar la columna `progreso` de la base de datos o del modelo. Queda
  huérfana pero inofensiva; borrarla es una migración destructiva que merece su
  propia decisión.
- Construir un formulario que alimente `progreso` (la opción C que se evaluó).
- Avance físico por grupo: solo 2 contratos tienen actividades registradas.
- Ponderar la ejecución por presupuesto del proyecto.

## Limitación conocida

Tras este cambio, buena parte del dashboard mostrará `—`: dos de tres fases y
cuatro de seis municipios. Es el reflejo fiel del estado de los datos —solo 5 de
31 proyectos tienen contratos y hay 2 actas en total—, y es preferible a los
41-46% inventados que se muestran hoy. La riqueza visual volverá conforme se
registren contratos y actas reales.
