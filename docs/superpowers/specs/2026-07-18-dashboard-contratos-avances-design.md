# Diseño: Pestaña de Contratos con Avance Físico vs Financiero

**Fecha:** 2026-07-18
**Estado:** Aprobado
**Autor:** Xiamir Luquez (con Claude)

## Contexto

Hoy los contratos no tienen presencia en la vista principal del dashboard. Solo
se llegan a ver navegando: proyecto → modal → pestaña "Contratos", donde se
muestra una tabla básica (Número / Objeto / Valor). El modal de contrato
individual muestra un campo genérico `avance` que ni siquiera corresponde al
modelo real (que tiene `avance_fisico` y `avance_financiero` separados).

La pestaña "Estadísticas y Filtros" tiene gráficas de avance, pero agregadas
**por fase de proyecto**, no por contrato, y no comparan físico contra
financiero.

En contratación pública, la brecha entre avance financiero y físico es el
indicador de supervisión más accionable: si se facturó el 78% pero solo hay 35%
ejecutado, hay un riesgo que exige actuar sobre el contratista o la
interventoría.

## Objetivo

Dar visibilidad de primer nivel a los contratos mediante una pestaña dedicada en
el dashboard, que permita (a) comparar todos los contratos entre sí para
detectar los desfasados, y (b) entrar al detalle de uno para ver **cuándo** se
abrió su brecha.

## Datos disponibles (verificado)

El endpoint `GET /GestPro/proyectos` (`ProyectoController::proyectos`) ya
entrega, por cada proyecto, sus contratos con:

- `avance_financiero`, `avance_fisico` (valores vigentes, 0-100)
- `valor`, `n_contrato`, `objeto`, `estado`, fechas
- `avancesFinancieros[]`: actas con `fecha_acta` y `porcentaje_ejecutado`
- `actividades[]`: con `peso`, `ultimo_avance`, `fecha_ultimo_avance`

Recharts ya está instalado y en uso en `Estadisticas.jsx`.

**Fórmulas existentes** (tomadas de `Parametros.jsx`, cálculos ya implementados):

```
avance_fisico     = Σ ( peso_actividad / 100 × último_porcentaje_ejecucion )
avance_financiero = Σ ( valor_facturado de todas las actas ) / valor_vigente × 100
```

Nótese que el avance financiero es **acumulativo sobre `valor_facturado`**, no el
`porcentaje_ejecutado` de un acta individual. El `valor_vigente` es el `valor`
actual del contrato (ya recalculado si tiene adiciones).

## Decisiones de diseño

| Decisión | Elección | Razón |
|---|---|---|
| Ubicación | Pestaña "Contratos" propia en el dashboard | Los contratos son entidad de peso propio; hoy están a 3 clics. No recarga la vista de fases ni convierte Estadísticas en cajón de sastre. |
| Vista general | Scatter (todos los contratos) + tabla con semáforo | El scatter compara y detecta outliers; la tabla da el detalle accionable ordenado por severidad. |
| Detalle de un contrato | Gráfica de evolución temporal (2 líneas) | Responde *cuándo* se abrió la brecha, no solo que existe. Las alternativas repetían lo que la tabla ya dice. |
| Umbrales de desfase | Verde ≤10, ámbar 11-25, rojo >25 (puntos porcentuales) | Hasta 10 puntos es desfase natural entre ejecución y facturación; >25 indica pago muy por encima de lo ejecutado. |
| Contratos sin datos de avance | Excluir del scatter, listar aparte en la tabla | Puntos en (0,0) distorsionan la lectura; pero no deben perderse de vista. |

## Ajuste de backend requerido

El endpoint actual solo entrega el **último** avance de cada actividad, lo que
impide reconstruir la serie temporal física. Se requiere:

**En `ProyectoController::proyectos`** (y en `listarContratos` para consistencia),
por cada actividad, además de `ultimo_avance` / `fecha_ultimo_avance`, adjuntar
el histórico completo:

```php
$actividad->avances = DB::table('actividad_avances')
    ->select('id', 'fecha', 'porcentaje_ejecucion')
    ->where('actividad_id', $actividad->id)
    ->orderBy('fecha', 'asc')
    ->orderBy('id', 'asc')
    ->get();
```

Esto es aditivo: no altera ningún campo existente ni rompe consumidores actuales.

### Serie temporal física (cálculo en el frontend)

Con el histórico disponible, la serie se construye así:

1. Recolectar todas las fechas de corte del contrato: las `fecha` de todos los
   `actividad_avances` más las `fecha_acta` de las actas, ordenadas ascendente y
   deduplicadas.
2. Para cada fecha de corte `F`, calcular el avance físico aplicando la fórmula
   ponderada, tomando para cada actividad su **último avance con fecha ≤ F**
   (si una actividad no tiene avances hasta `F`, cuenta como 0).
3. Para cada fecha de corte `F`, el avance financiero es acumulativo:
   `Σ(valor_facturado de las actas con fecha_acta ≤ F) / valor_vigente × 100`.
   Si el contrato no tiene `valor` vigente (0 o null), la serie financiera no se
   calcula y se trata como contrato sin datos.

Ambas series se grafican sobre el mismo eje temporal.

## Interfaz

Nueva pestaña **"Contratos"** en el dashboard, junto a las pestañas de fases y
"Estadísticas y Filtros" (`Dashboard.jsx`, contenedor `tabs-header`).

### 1. Tira de KPIs

Cuatro tarjetas: total de contratos · valor total contratado · avance físico
promedio · número de contratos con desfase alto (>25, en rojo).

### 2. Scatter: Avance Físico vs Financiero

- Eje X: avance físico (0-100). Eje Y: avance financiero (0-100).
- Diagonal punteada de equilibrio (x = y).
- Cada punto es un contrato, coloreado por severidad del desfase (verde/ámbar/rojo
  según umbrales).
- Tooltip con número de contrato, proyecto, ambos avances y el desfase.
- Contratos sin datos de avance no se grafican.
- Clic en un punto → selecciona ese contrato (resalta el punto y su fila, y abre
  la gráfica de evolución).

### 3. Tabla de detalle

Columnas: Contrato · Proyecto · Valor · Físico · Financiero · Desfase (badge con
color del semáforo). Ordenada por desfase descendente (lo crítico primero).
Filtro rápido "Todos / Solo críticos". Los contratos sin datos de avance
aparecen al final con la etiqueta "Sin datos de avance" y sin badge.
Clic en fila → mismo efecto que clic en el punto del scatter.

### 4. Gráfica de evolución (contrato seleccionado)

Al seleccionar un contrato, debajo de la tabla (o en panel) se muestra una
gráfica de líneas con las dos series descritas arriba:

- Línea azul: avance físico acumulado.
- Línea verde: avance financiero acumulado.
- Eje X temporal por fechas de corte; eje Y 0-100%.
- Tooltip por fecha mostrando ambos valores y la brecha en ese punto.
- Si el contrato tiene menos de 2 fechas de corte, se muestra un mensaje
  ("Datos insuficientes para graficar la evolución") en vez de una línea vacía.

## Estructura de archivos

- **Crear:** `resources/js/components/ContratosPanel.jsx` — la pestaña completa
  (KPIs, scatter, tabla, y la gráfica de evolución del seleccionado). Recibe
  `proyectos` como prop, igual que `Estadisticas.jsx`.
- **Crear:** `resources/js/utils/avanceContratos.js` — funciones puras:
  aplanar contratos desde proyectos, calcular desfase y severidad, y construir
  las series temporales. Aisladas para poder probarlas sin renderizar React.
- **Modificar:** `resources/js/components/Dashboard.jsx` — botón de pestaña
  "Contratos" y render condicional de `ContratosPanel`.
- **Modificar:** `app/Http/Controllers/ProyectoController.php` — adjuntar el
  histórico `avances` por actividad en `proyectos` y `listarContratos`.
- **Modificar/crear tests:** cobertura del endpoint (que entregue el histórico) y
  de las funciones puras de cálculo.

## Pruebas

**Backend (Feature, patrón `ModificacionesContratoTest`):**

1. `GET /GestPro/proyectos` entrega, por cada actividad de contrato, el arreglo
   `avances` con sus registros históricos ordenados por fecha ascendente.
2. Una actividad sin avances devuelve `avances` como arreglo vacío (no null).
3. `listarContratos` entrega el mismo histórico (consistencia entre endpoints).

**Frontend (funciones puras en `avanceContratos.js`):**

4. `calcularDesfase`: financiero − físico; clasifica correctamente en los tres
   niveles, incluyendo los bordes exactos (10 → verde, 11 → ámbar, 25 → ámbar,
   26 → rojo).
5. `construirSerieTemporal`: con dos actividades de pesos 60/40 y avances en
   fechas distintas, produce el avance físico ponderado correcto en cada fecha de
   corte, tomando el último avance ≤ fecha.
6. Actividad sin avances hasta una fecha de corte cuenta como 0 en esa fecha.
7. La serie financiera acumula `valor_facturado` correctamente: con actas de
   200M y 300M sobre un contrato de 1.000M, arroja 20% en la primera fecha y 50%
   en la segunda (no 30%).
8. Un contrato con `valor` 0 o null se marca como sin datos, sin dividir por cero.
9. `aplanarContratos`: extrae los contratos de todos los proyectos conservando
   el nombre del proyecto, y marca los que no tienen datos de avance.

## Fuera de alcance (YAGNI)

- Editar contratos desde el dashboard (sigue siendo en Parámetros).
- Exportar a Excel/PDF.
- Filtros por fase/municipio/estado en la pestaña de contratos (por ahora solo
  "Todos / Solo críticos"); se pueden añadir después si hacen falta.
- Mostrar adiciones/prórrogas en esta vista (ya existen en Parámetros; se podría
  agregar como columna más adelante).
- Corregir el campo `avance` mal mapeado del modal de contrato del dashboard
  (deuda preexistente, no la introduce este cambio).
