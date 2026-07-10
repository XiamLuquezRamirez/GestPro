# Gestión de Avance Financiero y Avance Físico en Contratos

**Fecha:** 2026-07-10
**Estado:** Aprobado, pendiente de plan de implementación

## Contexto

El usuario reportó: "los Avance Financiero y Avance Físicos no se pueden gestionar en los contratos". Se investigó siguiendo el proceso de depuración sistemática antes de proponer cualquier fix.

**Causa raíz confirmada:** en `resources/js/components/Parametros.jsx`, dentro del formulario de contrato (pestaña "Información del Contrato"), los inputs `avance_financiero` y `avance_fisico` tienen el atributo `disabled` fijo en el JSX, y el botón "Calcular" junto a cada uno no tiene ningún `onClick` — es decorativo, sin lógica, desde el commit inicial que introdujo el formulario de contratos (no es una regresión de esta sesión). El backend (columnas en `contratos`, modelo, `guardarContrato()`) sí soporta persistir estos valores perfectamente.

Además, ya existen en la base de datos dos tablas satélite completas — `avance_financiero` (con `descripcion, fecha_acta, valor_facturado, amortizacion_50, valor_presente_acta, porcentaje_ejecutado, anexo`) y `avance_fisico` (con `descripcion_avance_fisico, fecha_avance_fisico, valor_avance_fisico`) — con sus modelos Eloquent (`AvanceFinanciero`, `AvanceFisico`) y relaciones ya definidas en `Contrato.php`, pero **sin ningún controlador, ruta ni interfaz que las use**. Incluso hay una ruta `POST /subirActa` ya declarada en `routes/api.php` sin método correspondiente en el controlador — un scaffolding claramente dejado a medias.

Al discutir el diseño con el usuario, aclaró la lógica de negocio real detrás de ambos indicadores (ver Decisiones registradas), lo cual determina que el modelo de datos correcto para "avance físico" es distinto al de la tabla `avance_fisico` ya creada (esta última no sirve para el diseño aprobado — ver más abajo).

## Alcance

**Incluye:**
- Habilitar la gestión real de avance financiero y avance físico por contrato, cada uno con su propia lógica de cálculo (ver Diseño).
- Nuevas tablas para actividades ponderadas de avance físico, con historial de actualizaciones por fecha (para poder graficar una curva S en una iteración futura — **esta iteración NO construye ningún gráfico**, solo guarda los datos).
- Reutiliza la tabla `avance_financiero` ya existente para las actas de avance financiero (con subida de archivo opcional, completando la ruta `/subirActa` ya declarada).
- Dos pestañas nuevas en el modal de contrato: "Avance Financiero" y "Avance Físico", junto a "Información del Contrato" y "Anexos".
- Los botones "Calcular" de la pestaña "Información" quedan funcionales.

**No incluye:**
- Ninguna gráfica de curva S (planeado vs. real en el tiempo) — se guardan los datos con fecha para eso, pero la visualización queda para después.
- Ninguna fórmula automática entre `valor_facturado`, `amortizacion_50` y `valor_presente_acta` en el acta financiera — son campos de entrada manual independientes, tal como ya existen en la tabla.
- Ningún cambio a la pestaña "Anexos" ni a la tabla `anexos_contratos` — se usa como referencia de patrón, no se modifica.
- La tabla `avance_fisico` (ya creada, nunca usada) no se elimina en este trabajo, pero queda huérfana — no encaja con el modelo de actividades ponderadas aprobado. Se puede limpiar en una tarea futura.

## Diseño

### 1. Avance Financiero

Fórmula acordada: **Avance financiero (%) = (Σ valor_presente_acta de todas las actas del contrato) / valor total del contrato × 100** — `valor_presente_acta` ya representa el valor "neto" (facturado menos amortización de anticipo), coincidiendo con la definición de "causado/amortizado neto" que dio el usuario.

**Backend** — se reutiliza la tabla `avance_financiero` ya migrada (`id, contrato_id, descripcion, fecha_acta, valor_facturado, amortizacion_50, valor_presente_acta, porcentaje_ejecutado, anexo`), sin cambios de esquema:
- `POST /guardarActaFinanciera`: inserta UNA acta nueva (no borra-y-reinserta como los anexos — cada acta es un registro histórico independiente que se agrega uno a la vez, nunca se edita).
- `POST /eliminarActaFinanciera`: elimina una acta por `id` (y su archivo físico si tiene `anexo`, mismo patrón que `eliminarAnexo`).
- `POST /subirActa`: completa la ruta ya declarada sin implementar. Sube el archivo a `public/actas_avance/` y devuelve la ruta relativa — mismo patrón de dos fases que `subirAnexo` (subir → obtener ruta → incluirla al guardar la acta). Opcional: una acta puede guardarse sin archivo.
- `proyectos()` y `listarContratos()` en `ProyectoController.php` se extienden para adjuntar `avancesFinancieros` a cada contrato (mismo patrón que ya hace con `anexos`), ordenadas por `fecha_acta` descendente.

**Frontend** — pestaña "Avance Financiero" en el modal de contrato (solo visible/habilitada si el contrato ya fue guardado, es decir `editingContrato` no es null — una acta necesita un `contrato_id` real):
- Formulario: Descripción, Fecha del acta, Valor facturado, Amortización 50%, Valor presente del acta, % Ejecutado (opcional, informativo, no se usa en el cálculo), Archivo (opcional).
- Tabla de actas ya guardadas: fecha, valor facturado, valor presente, % ejecutado, enlace al archivo si tiene, botón eliminar.
- Título de pestaña con conteo: "Avance Financiero (3)".

**Botón "Calcular Avance Financiero"** (pestaña "Información"): suma `valor_presente_acta` de todas las actas ya cargadas en memoria, divide entre el valor del contrato (`formContrato.valor`, parseado a número), redondea a entero, y actualiza el campo `avance_financiero` (que sigue siendo de solo lectura, solo se actualiza por este botón). Si no hay actas registradas, o si el valor del contrato es 0/vacío, muestra un aviso en vez de calcular.

### 2. Avance Físico — actividades ponderadas

Fórmula acordada: **Avance físico (%) = Σ (Peso_i × % Ejecución_i)** de cada actividad del contrato, donde los pesos deben sumar 100%.

La tabla `avance_fisico` ya existente (`descripcion_avance_fisico, fecha_avance_fisico, valor_avance_fisico`) es un registro plano por contrato sin concepto de "actividad" — no encaja con este modelo ponderado. Se crean tablas nuevas en su lugar; `avance_fisico` queda sin usar (ver Alcance).

**Backend — nuevas tablas:**
```php
Schema::create('actividades_contrato', function (Blueprint $table) {
    $table->id();
    $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
    $table->string('nombre');
    $table->unsignedTinyInteger('peso'); // % de 0 a 100
});

Schema::create('actividad_avances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('actividad_id')->constrained('actividades_contrato')->cascadeOnDelete();
    $table->date('fecha');
    $table->unsignedTinyInteger('porcentaje_ejecucion'); // % de 0 a 100
});
```
`actividad_avances` es un historial append-only: cada actualización del % de ejecución de una actividad agrega una fila nueva con su fecha, nunca sobreescribe una existente — así queda la base para graficar la curva S en el futuro (planeado vs. real en el tiempo), aunque esta iteración no construye esa gráfica.

**Nuevos modelos:**
- `ActividadContrato` (tabla `actividades_contrato`, `$timestamps = false`, fillable `contrato_id, nombre, peso`; `belongsTo(Contrato::class, 'contrato_id')`, `hasMany(ActividadAvance::class, 'actividad_id')`).
- `ActividadAvance` (tabla `actividad_avances`, `$timestamps = false`, fillable `actividad_id, fecha, porcentaje_ejecucion`, cast `fecha => date`; `belongsTo(ActividadContrato::class, 'actividad_id')`).
- `Contrato::actividades(): HasMany` → `ActividadContrato::class, 'contrato_id'`.

**Nuevos endpoints:**
- `POST /guardarActividad`: crea o actualiza una actividad (`id` presente → actualiza `nombre`/`peso`; si no, inserta). Devuelve el `id` (necesario para poder registrar avances sobre una actividad recién creada).
- `POST /eliminarActividad`: elimina una actividad por `id` (el `cascadeOnDelete()` de `actividad_avances` se encarga de su historial).
- `POST /registrarAvanceActividad`: inserta una fila nueva en `actividad_avances` (`actividad_id, fecha, porcentaje_ejecucion`) — siempre inserta, nunca actualiza una fila existente.
- `proyectos()`/`listarContratos()` adjuntan a cada contrato su lista `actividades`, y cada actividad trae ya calculado (en el backend, para evitar N+1 en el frontend) su `ultimo_avance` (el `porcentaje_ejecucion` de su fila más reciente en `actividad_avances` por `fecha`, o `null` si no tiene ninguna) y `fecha_ultimo_avance`.

**Frontend** — pestaña "Avance Físico" en el modal de contrato (igual que Avance Financiero, solo visible si `editingContrato` no es null):
- Formulario para agregar actividad: Nombre, Peso (%).
- Tabla de actividades: nombre, peso, % de ejecución actual (el `ultimo_avance`), fecha de esa última actualización, botón "Registrar avance" por fila (despliega un mini-formulario en línea: fecha + nuevo %, al enviar hace `POST /registrarAvanceActividad` y refresca), botón eliminar actividad.
- Resumen debajo de la tabla: suma total de pesos (en rojo si ≠ 100%, sin bloquear nada — es solo una advertencia visual) y el avance físico total ya calculado (Σ peso × último avance de cada actividad).
- Título de pestaña con conteo: "Avance Físico (2)" (número de actividades).

**Botón "Calcular Avance Físico"** (pestaña "Información"): usa el mismo cálculo ya mostrado en el resumen de la pestaña (Σ peso/100 × último avance de cada actividad) y lo copia al campo `avance_fisico`. Si no hay actividades registradas, muestra un aviso en vez de calcular.

### 3. Gating por contrato ya guardado

Ambas pestañas nuevas requieren un `contrato_id` real (las actas/actividades se insertan de inmediato contra el backend, no se acumulan en memoria como los anexos). Se muestran/habilitan solo cuando `editingContrato` no es `null` — es decir, al crear un contrato nuevo, el usuario debe guardarlo primero (pestaña "Información") y luego editarlo para gestionar su avance financiero/físico. Esto es consistente con que estas pestañas no tienen sentido para un contrato que todavía no existe en la base de datos.

## Decisiones registradas

- **Avance financiero** = Σ `valor_presente_acta` de todas las actas / valor total del contrato × 100. `valor_facturado` y `amortizacion_50` quedan como campos informativos de cada acta, sin fórmula automática entre ellos (entrada manual, tal como ya existen las columnas).
- **Avance físico** = Σ (peso × % ejecución) por actividad ponderada — no un valor plano manual. La tabla `avance_fisico` ya creada no encaja con este modelo y queda sin usar.
- El historial de `actividad_avances` se guarda con fecha desde ya (append-only, nunca se edita una fila existente) para habilitar una curva S en el futuro, pero **esta iteración no construye ninguna gráfica** — es una decisión explícita para no ampliar el alcance ahora.
- Las actas financieras y las actualizaciones de actividades se insertan una por una, de inmediato (no con el patrón "borrar todo y reinsertar" que usan los anexos), porque son historiales, no listas reemplazables.
- Los campos `avance_financiero`/`avance_fisico` del contrato siguen sin ser editables a mano (`disabled` se mantiene) — solo los botones "Calcular" los actualizan, completando el diseño que ya estaba insinuado mas nunca conectado.
- Las pestañas nuevas requieren que el contrato ya tenga `id` (ya guardado) — no se puede agregar actas/actividades a un contrato todavía sin guardar.
- La subida de archivo de la acta financiera reutiliza la ruta ya declarada `/subirActa` (hoy sin implementación), completándola con el mismo patrón exacto de `subirAnexo`.
