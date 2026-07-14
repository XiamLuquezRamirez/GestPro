# Anticipo del contrato y cálculo automático de amortización

**Fecha:** 2026-07-10
**Estado:** Aprobado, pendiente de plan de implementación

## Contexto

En la feature recién completada de gestión de avance financiero, el campo "Amortización" de cada acta se escribe a mano, sin relación con ningún dato del contrato. El usuario pidió: al crear/editar un contrato, poder indicar si tiene anticipo y qué porcentaje, para que la amortización de cada acta se calcule automáticamente a partir de ese porcentaje, en vez de escribirse a mano.

Se descubrió que la tabla `contratos` ya tiene una columna `anticipo` (booleano) desde su migración original, pero nunca se expuso en el formulario ni se guarda desde `guardarContrato()` — es terreno parcialmente preparado pero nunca conectado, igual que el bug de avance financiero/físico resuelto en la feature anterior.

## Alcance

**Incluye:**
- Nueva columna `porcentaje_anticipo` en `contratos` (0-100, nullable).
- Exponer y guardar `anticipo` (que ya existía en BD, nunca se usaba) y `porcentaje_anticipo` en el formulario "Información del Contrato".
- Los campos "Amortización" y "Valor Presente del Acta" del formulario "Agregar Nueva Acta" (pestaña Avance Financiero) pasan de edición manual a **cálculo automático de solo lectura**, a partir del "Valor Facturado" que se escribe y del `anticipo`/`porcentaje_anticipo` del contrato.

**No incluye:**
- Ningún cambio a la fórmula de "Calcular Avance Financiero" (sigue sumando `valor_presente_acta` de todas las actas / valor del contrato) — solo cambia CÓMO se llena `valor_presente_acta` de cada acta individual, no el cálculo agregado ya existente.
- Ningún cambio a avance físico ni a las actividades ponderadas.
- Ninguna migración de datos para actas ya existentes (sus valores de amortización/valor presente ya guardados no se recalculan retroactivamente).

## Diseño

### 1. Backend

**Nueva migración**: agrega `porcentaje_anticipo` (`unsignedTinyInteger`, `nullable()`) a `contratos`.

**`Contrato.php`**: agregar `porcentaje_anticipo` a `$fillable` (junto a `anticipo`, que ya está).

**`guardarContrato()`**: el `$contratoPayload` hoy NO incluye `anticipo` (se ignora silenciosamente aunque el modelo lo declare fillable). Se agrega:
```php
'anticipo' => (bool) ($formContrato['anticipo'] ?? false),
'porcentaje_anticipo' => $nullableInt($formContrato['porcentaje_anticipo'] ?? null),
```

**`proyectos()`**: el `select()` de contratos hoy no incluye `anticipo` ni `porcentaje_anticipo` — se agregan ambas columnas para que el frontend pueda leerlas al editar un contrato.

**`listarContratos()`**: no usa `select()` explícito (trae todas las columnas), así que no necesita cambios — la columna nueva ya viene incluida automáticamente en cuanto exista en la tabla.

### 2. Formulario "Información del Contrato"

Se agregan dos campos nuevos, junto a "Avance Financiero"/"Avance Físico" (que ya están ahí, de solo lectura):
- Checkbox **"¿Lleva anticipo?"** (campo `anticipo`, booleano).
- Input numérico **"% de Anticipo"** (campo `porcentaje_anticipo`, 0-100), deshabilitado cuando el checkbox no está marcado.

`handleContratoChange` debe manejar el caso de un input `type="checkbox"` (usar `e.target.checked`, no `e.target.value`, para ese campo específico) — hoy solo maneja inputs de texto/select.

`formContrato` inicial y sus 3 puntos de reseteo (`handleAddContrato`, `handleSaveContrato`, `handleCancelContratoForm`, `handleAddNewContrato`) incluyen `anticipo: false, porcentaje_anticipo: ''`. `handleEditContrato` carga ambos valores del contrato.

### 3. Cálculo automático en "Agregar Nueva Acta"

Cuando el usuario escribe el "Valor Facturado" de una acta nueva, se recalculan automáticamente (dentro de `handleActaFinancieraChange`, en la misma actualización de estado que procesa el cambio de `valor_facturado`):

```
amortizacion = contrato.anticipo ? valor_facturado × (contrato.porcentaje_anticipo / 100) : 0
valor_presente_acta = valor_facturado − amortizacion
```

Los inputs "Amortización" y "Valor Presente del Acta" del formulario de acta pasan a `disabled` (ya no se escriben a mano), mismo patrón visual que los campos "Avance Financiero"/"Avance Físico" del contrato. La etiqueta "Amortización 50%" se reemplaza por una que muestre el porcentaje real configurado (ej. "Amortización (30%)"), ya que ya no es necesariamente 50%.

## Decisiones registradas

- Fórmula de amortización por acta: `valor_facturado × (porcentaje_anticipo / 100)`, aplicada solo si el contrato tiene `anticipo = true`; si no, la amortización es 0.
- `valor_presente_acta` también pasa a calcularse automáticamente (`valor_facturado − amortización`), no solo la amortización.
- Ambos campos calculados quedan de solo lectura en el formulario de acta, igual que ya lo están "Avance Financiero"/"Avance Físico" en el contrato.
- No se toca la fórmula de "Calcular Avance Financiero" a nivel de contrato (sigue sumando `valor_presente_acta` de todas las actas) — este cambio solo afecta cómo se llena ese valor por acta individual.
- El campo `anticipo` ya existía en la base de datos sin usarse; se conecta ahora en vez de crear una columna nueva para lo mismo.
