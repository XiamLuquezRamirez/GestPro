# Diseño: Adiciones y Prórrogas de Contrato

**Fecha:** 2026-07-16
**Estado:** Aprobado
**Autor:** Xiamir Luquez (con Claude)

## Contexto

GestPro gestiona contratos de proyectos de inversión pública en Colombia. Cada
contrato (`contratos`) tiene un `valor`, un plazo (`fecha_inicio`, `fecha_fin`),
anexos, actas de avance financiero/físico y actividades.

En contratación pública, los contratos se modifican mediante **otrosíes**:

- **Adición:** aumenta el valor del contrato. Legalmente limitada al 50% del
  valor inicial (Ley 80), con excepciones (obra pública).
- **Prórroga:** extiende el plazo (mueve la `fecha_fin`).
- Un mismo otrosí puede adicionar valor **y** prorrogar plazo simultáneamente.

Hoy no existe forma de registrar estas modificaciones ni de conservar su
historial. Editar `valor`/`fecha_fin` a mano pierde trazabilidad y rompe el
cálculo del límite legal.

## Objetivo

Registrar adiciones y prórrogas como un **historial de modificaciones** con
trazabilidad completa (número de otrosí, tipo, monto, días, fecha,
justificación), recalculando automáticamente el valor y la fecha fin vigentes
del contrato a partir de ese historial.

## Decisiones de diseño

| Decisión | Elección | Razón |
|---|---|---|
| Registro | Historial en tabla propia | Trazabilidad y auditoría requeridas en contratación pública. |
| Tipos | Adición, prórroga o ambas por registro | Un otrosí real suele hacer las dos cosas a la vez. |
| Límite 50% | Advertir pero permitir | Existen excepciones legales; bloquear estorbaría. |
| Recálculo | Automático desde el historial | Consistencia; sin edición manual del valor/fecha vigentes. |

## Modelo de datos

### Nueva tabla `modificaciones_contrato`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | `id` | |
| `contrato_id` | `foreignId` → `contratos`, `cascadeOnDelete` | |
| `numero_otrosi` | `string(50)`, nullable | Ej. "Otrosí No. 2" |
| `tipo` | `string(20)` — `adicion` \| `prorroga` \| `adicion_prorroga` | |
| `valor_adicion` | `decimal(15,2)`, nullable | Monto adicionado (0/null si solo prórroga) |
| `dias_prorroga` | `integer`, nullable | Días que extiende (0/null si solo adición) |
| `fecha_modificacion` | `date` | Fecha del otrosí |
| `justificacion` | `text`, nullable | Motivo legal/técnico |

`public $timestamps = false;` (consistente con el resto de modelos del proyecto).

### Campos nuevos en `contratos` (base inmutable)

- `valor_inicial` `decimal(15,2)`, nullable — se copia de `valor` al crear el
  contrato. Base para el cálculo del límite del 50%.
- `fecha_fin_inicial` `date`, nullable — se copia de `fecha_fin` al crear. Base
  para calcular las prórrogas.

Tras esta funcionalidad, `valor` y `fecha_fin` del contrato representan los
**valores vigentes recalculados** desde el historial.

### Backfill de datos existentes

La migración que agrega `valor_inicial`/`fecha_fin_inicial` debe copiar, para
los contratos ya existentes:

```
valor_inicial      ← valor
fecha_fin_inicial  ← fecha_fin
```

Así los contratos preexistentes quedan con base consistente y su % adicionado
arranca en 0.

## Lógica de recálculo (backend)

Al agregar o eliminar una modificación, dentro de una transacción (mismo patrón
que `guardarContrato`), se recalcula:

```
valor_vigente     = valor_inicial + SUMA(valor_adicion de todas las modificaciones del contrato)
fecha_fin_vigente = fecha_fin_inicial + SUMA(dias_prorroga de todas las modificaciones del contrato)
```

- **Al crear un contrato nuevo:** `guardarContrato` copia `valor → valor_inicial`
  y `fecha_fin → fecha_fin_inicial`.
- **Validación 50%:** el backend calcula
  `porcentaje_adicionado = SUMA(adiciones) / valor_inicial * 100`. Si supera 50%,
  incluye un flag de advertencia en la respuesta, **pero persiste el registro**
  (advertir, no bloquear).

### Endpoints nuevos en `ProyectoController`

Siguiendo el estilo de `subirActa` / `eliminarActaFinanciera` (uso de
`DB::table`, transacciones, respuestas JSON):

- `guardarModificacionContrato(Request $request)` — inserta el otrosí y recalcula
  el contrato. Devuelve el resumen recalculado y el flag de límite 50%.
- `eliminarModificacionContrato(Request $request)` — borra el otrosí y recalcula.

Las modificaciones se cargan junto al contrato en el listado existente vía
relación `hasMany` (`Contrato::modificaciones()`).

## Interfaz (frontend, `Parametros.jsx`)

Nueva sección **"Modificaciones (Adiciones y Prórrogas)"** dentro de la edición
de contrato, junto a la sección de anexos (mismo patrón visual).

### Resumen recalculado (solo lectura)

- Valor inicial: `$X` → **Valor vigente: `$Y`**
- Fecha fin inicial: `dd/mm/aaaa` → **Fecha fin vigente: `dd/mm/aaaa`**
- **% adicionado: `NN%`** — en rojo con alerta "⚠ Supera el 50% legal" cuando
  `porcentaje_adicionado > 50`.

### Tabla de historial

Columnas: N° Otrosí · Tipo · Adición · Días · Fecha · Justificación · (eliminar).

### Formulario de nuevo otrosí

- Selector de tipo: Adición / Prórroga / Ambas → muestra u oculta `valor_adicion`
  y `dias_prorroga` según corresponda.
- `valor_adicion`: input de moneda colombiana reutilizando el input decimal
  existente (formato `$ 1.487.342.187,33`).
- `dias_prorroga`: numérico.
- `fecha_modificacion`: fecha.
- `justificacion`: texto.

## Pruebas (Feature, patrón `AvancesTest` / `HijasDeProyectoTest`)

1. **Esquema:** `modificaciones_contrato` existe con sus columnas y FK con
   cascade; `contratos` tiene `valor_inicial` y `fecha_fin_inicial`.
2. **Recálculo de valor:** contrato con `valor_inicial = 100.000.000`, adición de
   `30.000.000` → `valor` vigente = `130.000.000`.
3. **Recálculo de fecha:** prórroga de `60` días → `fecha_fin` = `fecha_fin_inicial + 60`.
4. **Tipo "ambas":** un otrosí `adicion_prorroga` afecta ambos totales.
5. **Eliminar modificación:** borrar un otrosí revierte el recálculo.
6. **Límite 50%:** adición que supera el 50% del `valor_inicial` devuelve el flag
   de advertencia **pero el registro persiste** (no bloquea).
7. **Backfill:** un contrato preexistente sin `valor_inicial` recibe el `valor`
   actual tras la migración.

## Fuera de alcance (YAGNI)

- Adjuntar el PDF del otrosí (se puede seguir usando la sección de anexos).
- Flujo de aprobación/estados del otrosí.
- Reducciones de valor o suspensiones (solo adición y prórroga por ahora).
