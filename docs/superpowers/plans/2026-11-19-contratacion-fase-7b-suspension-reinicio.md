# Contratación, fase 7B: suspensión y reinicio del contrato. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** que un contrato en ejecución se pueda **suspender** y **reiniciar** desde botones propios del contrato.
- **Datos que se guardan:** fecha de suspensión, reinicio previsto opcional, acta y motivo.
- **Al reiniciar:** los días calendario suspendidos se suman a la fecha fin vigente, igual que una prórroga.
- **Mientras está suspendido:** la fecha fin no cambia y el contrato muestra "Suspendido desde X (N días)". Si pasa el reinicio previsto, aparece el aviso "Reinicio vencido".
- **Lo que no cambia:** el valor, los valores iniciales, los avances y las adiciones y prórrogas existentes.

Los informes de supervisión (7C) quedan para un plan aparte.

**Arquitectura:**
- **Migración 25:** `2026_11_19_100024_add_suspension_a_modificaciones_contrato` agrega a `modificaciones_contrato` `fecha_reinicio` y `fecha_reinicio_prevista` (date, nullable), cada una con su guarda. El `down()` se detiene si hay suspensiones.
- **La suspensión es una fila de `modificaciones_contrato`** con `tipo = 'suspension'`. Se guarda así:
  - `fecha_modificacion`: la fecha de suspensión;
  - `numero_otrosi`: el número del acta;
  - `justificacion`: el motivo;
  - `dias_prorroga`: null mientras está abierta. Al reiniciar se llena con los días suspendidos, así que `ContratosService::recalcular()` corre la fecha fin sin cambios.
- **Suspensión (API):**
  - el servicio `App\Services\Contratacion\SuspensionContratos` (`suspender()`, `reiniciar()`, `eliminar()`, `abiertasDe()` y `aviso()`), que devuelve un `SuspensionGuardada`;
  - las rutas `POST /contratos/{contrato}/suspender` y `/reiniciar`, en `ContratoController`, bajo `can:editar-datos`;
  - control de versión con `actualizado` (409), bloqueo de la fila e historial `contrato_suspendido` / `contrato_reiniciado`.
- **Pestaña Modificaciones (endpoints existentes):**
  - `guardarModificacionContrato` rechaza el tipo `suspension`;
  - eliminar una suspensión pasa por `SuspensionContratos::eliminar()`: solo la última, con versión, e historial `suspension_eliminada`;
  - las adiciones y prórrogas bloquean la fila del contrato antes de recalcular.
- **Reglas en lo existente:**
  - editar no pasa a `suspendido` ni sale de `suspendido` con una suspensión abierta;
  - terminar y liquidar (7A) se rechazan con una suspensión abierta.
- **Aviso:** `aviso_suspension` va en `presentar()`, la búsqueda, el detalle, `listarContratos` y GET /proyectos, con una sola consulta por listado.
- **Pantallas:**
  - en `ContratoEditor.jsx`, los botones "Suspender" y "Reiniciar", con los diálogos de `SuspensionContrato.jsx`;
  - la franja "Suspendido desde…", las filas "Suspensión" en Modificaciones (papelera solo en la última) y el select de estado con «Suspendido» deshabilitado;
  - "Actualizar desde SECOP II" sin aplicar ese estado;
  - el chip "Reinicio vencido" en el módulo Contratos, la lista de Parámetros y la ficha del proyecto;
  - las utilidades puras, en `utils/contratos.js` y `utils/secop.js`, con pruebas Vitest.

**Tecnologías:** Laravel 12, PHP 8.2, MariaDB 10.4, React 18 con Laravel Mix y react-router, PHPUnit sobre SQLite en memoria, Vitest.

**Fuente:**
- La propuesta `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, aprobada:
  - fase 7: suspensión y reinicio sobre `modificaciones_contrato`. Cada pieza se entrega por separado y no altera el avance físico ni el financiero;
  - sección 3: "Suspensiones y reinicios: después, reutilizando"; antes de crear una tabla se evalúa un tipo nuevo en `modificaciones_contrato`, porque una suspensión corre la fecha de fin en los días suspendidos, igual que una prórroga;
  - el flujo de la sección 5 (suspensión → contrato suspendido → vuelve a ejecución) y las reglas R6, R13, R15 y R16.
- La fase 7A (terminación y liquidación), aplicada, cuyos patrones sigue: botones con diálogo, versión y 409, historial, avisos en las listas.
- Decisiones del usuario (2026-11-19), abajo.

**Estado de partida:** rama `feature/contratacion`, igual a `main` (`0d1cc59`). La 7A está validada y aplicada en `gest_pro`. Pasan 1346 pruebas PHP y 918 JS.

**Datos reales comprobados hoy en `gest_pro`:** 7 contratos (6 en ejecución y 1 liquidado), **ninguna fila en `modificaciones_contrato`** y ningún contrato suspendido. Las dos columnas nacen sin filas y ninguna respuesta existente cambia de valor: solo ganan claves nuevas.

---

## Reglas de ejecución

- **Nada de esto se aplica a `gest_pro` sin autorización del usuario.** Allí nunca se usa `migrate:rollback`: si hay que volver atrás, se restaura el respaldo. La tarea 7 aplica la migración 25, y solo con un sí explícito.
- **La viabilización no cambia ni una respuesta ni una cifra.** Las pruebas existentes de `tests/Feature/Viabilizacion/**`, los fixtures de paridad (`tests/Fixtures/paridad_*`) y `ExpedientesContratacionAisladosTest` no se modifican y deben pasar.
- **Pruebas existentes que cambian:**
  - **PHP (todas de contratación):**
    - `ContratosApiTest` y `ContratosBusquedaApiTest` ganan la clave `aviso_suspension`;
    - en `ContratosApiTest`, una prueba de editar cambia su estado de ejemplo de `suspendido` a `celebrado`, porque ya no se pasa a suspendido por el formulario;
    - `AvancePonderadoApiTest` y `ExpedienteContratacionApiTest` comparan la fecha real sugerida con `now(config('contratacion.zona'))`, como el servicio. Con `now()` fallaban cada día entre las 7 p. m. y la medianoche de Bogotá.
  - **JS:** `contratos.test.js` y `secop.test.js` ganan casos, y cambian las de `opcionesEstadoContrato` («Suspendido» deshabilitado) y una de `suspensionAbierta`.
- Commits autorizados solo en `feature/contratacion`. Sin push. Mensajes en inglés, terminados con la línea `Co-Authored-By` del modelo que comitea.
- Nomenclatura en español sin tildes para columnas, clases y métodos nuevos. Los textos para el usuario van en español con tuteo. Las migraciones, que son herramientas del operador, conservan el registro de usted.
- **"Hoy" en la zona `config('contratacion.zona')` (`America/Bogota`)**, con `AvisosContratacion::hoy()` en PHP y `fechaHoyBogota()` en JS. Los días se cuentan en días calendario con fechas `Y-m-d` en UTC, sin horas.
- Pruebas: `php artisan test` sin definir `DB_DATABASE`, para que use SQLite en memoria, y `npm test`. Si Vitest crea `resources/js/node_modules`, se borra.
- **Aplicar los bloques exactos.** Cada reemplazo aparece una sola vez en su archivo. Si no lo encuentras tal cual, detente y avisa. Se recomienda aplicarlos con un script que los lea del plan y exija una coincidencia por reemplazo.
- **No usar `git stash`, `git clean` ni `git checkout -- .`:** el usuario tiene stashes y cambios propios.
- El usuario suele tener `npm run watch` corriendo, que reescribe `public/`. Solo la tarea 4 comitea `public/`, después de `npm run production`.
- No tocar ni comitear `public/images/marca-completa.png` ni `public/images/marca-completa1.png`: son cambios del usuario.
- `.env` está versionado: nunca se agrega a un commit. Comitear siempre con rutas explícitas.
- `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio. Hay que definirlo en cada comando, porque el estado de la terminal no se conserva.
- **MariaDB no hace transaccional el DDL.** Por eso cada columna de la migración 25 lleva su guarda `hasColumn` y se puede reintentar.
- **El prefijo de la API (`/GestPro`) es también la base del SPA.** Las rutas nuevas son POST y no coinciden con ninguna ruta del SPA.

## Decisiones de este plan

**Decisiones del usuario (2026-11-19)**
1. **Botones propios:** "Suspender" y "Reiniciar" junto a Terminar y Liquidar, cada uno con su diálogo, como en la 7A. Cambian el estado (suspendido / en ejecución) y quedan en el historial. La suspensión aparece también en la pestaña Modificaciones.
2. **La fecha fin se corre al reiniciar:**
   - mientras está suspendido, la fecha fin no cambia;
   - al reiniciar se suman los días calendario reales suspendidos (reinicio − suspensión), igual que una prórroga.
3. **Fecha prevista de reinicio opcional, con aviso:** si pasa y el contrato sigue suspendido, sale "Reinicio vencido" en el contrato y en las listas.

**Datos**
- El `down()` se detiene si hay alguna suspensión, con este mensaje: "No se puede revertir: hay suspensiones de contratos registradas, que se perderían. Restaure un respaldo.". Si no hay ninguna, quita las dos columnas, cada una con su guarda.

**Suspender (`POST /contratos/{contrato}/suspender`)**
- **Permiso:** `can:editar-datos`. Consulta recibe 403; sin token, 401. Un contrato que no existe responde 404.
- **Cuerpo:** `{fecha_suspension, fecha_reinicio_prevista?, numero_acta? (hasta 50), motivo? (hasta 2000), actualizado}`.
- **Orden de las comprobaciones:** primero la versión (409), luego si ya hay una suspensión abierta (422 "El contrato ya tiene una suspensión abierta.") y luego el estado. Solo se suspende un contrato `en_ejecucion`; si no, 422 "Solo se suspende un contrato en ejecución.".
- **Fechas:**
  - la de suspensión no es posterior a hoy, ni anterior a `fecha_inicio`, ni anterior al último reinicio ("La fecha de suspensión no puede ser anterior al último reinicio.");
  - la prevista no es anterior a la de suspensión.
- **Qué guarda:**
  - crea la fila, deja el contrato en estado `suspendido` y adelanta `updated_at`;
  - registra `contrato_suspendido`, con `antes` `{estado}` y `despues` `{estado, fecha_suspension, fecha_reinicio_prevista, numero_acta, motivo, modificacion_id}`.
- **Responde** `{success, contrato: presentar(), avisos: []}`.

**Reiniciar (`POST /contratos/{contrato}/reiniciar`)**
- **Cuerpo:** `{fecha_reinicio, fecha_suspension?, observacion?, actualizado}`. Solo se reinicia un contrato `suspendido`; si no, 422 "El contrato no está suspendido.".
- **Con una suspensión abierta,** la cierra.
- **Sin una abierta** (un suspendido antiguo o importado así):
  - exige `fecha_suspension` (422 "Escribe la fecha de suspensión.");
  - esa fecha cumple las mismas reglas que al suspender;
  - crea la suspensión ya cerrada.
- **Fecha de reinicio:** no posterior a hoy ni anterior a la de suspensión. Días = reinicio − suspensión (0 si es el mismo día).
- **Qué guarda:**
  - `fecha_reinicio` y `dias_prorroga`;
  - el estado `en_ejecucion`;
  - llama a `recalcular()` y adelanta `updated_at`;
  - registra `contrato_reiniciado`, con la fecha fin de antes y la de después, las dos fechas de la suspensión, los días y la observación.
- **Responde** como suspender, más el `resumen` de `recalcular()`.

**Pestaña Modificaciones**
- **Registrar:** `guardarModificacionContrato` con `tipo: suspension` responde 422 en `tipo`: "Las suspensiones se registran con «Suspender».".
- **Eliminar una suspensión:**
  - exige `actualizado` (falta o inválido: 422) y la versión (409), antes de las demás reglas;
  - solo se elimina la más reciente, por fecha y luego por id. Si no es la más reciente: 422 "Solo se puede eliminar la última suspensión.". Si ya no existe: 404 "La suspensión ya no existe.";
  - si estaba abierta, el contrato vuelve a `en_ejecucion`;
  - recalcula, adelanta `updated_at` y registra `suspension_eliminada` con los datos de la fila.
- **Eliminar una adición o prórroga:** igual que antes, salvo el bloqueo de la fila del contrato.

**Editar y crear**
- **Editar:**
  - pasar a `suspendido` desde otro estado responde 422 en `estado`: "Para suspender el contrato usa «Suspender».";
  - salir de `suspendido` con una suspensión abierta responde 422 en `estado`: "Para reanudar el contrato usa «Reiniciar».";
  - sin una suspensión abierta, salir de `suspendido` se permite.
- **Crear:** sin cambios.

**7A**
- Terminar o liquidar con una suspensión abierta responde 422 "Reinicia el contrato antes de terminarlo o liquidarlo.", después de la versión.

**`aviso_suspension`**
- Vale null, o `{desde, dias (hoy − desde), reinicio_previsto, vencido (previsto < hoy)}` si el contrato está `suspendido` con una suspensión abierta.
- Va en `presentar()`, la búsqueda, el detalle, `listarContratos` y GET /proyectos, con una sola consulta por listado (ninguna si no hay suspendidos).

**Pantallas**
- **Botones** (solo para quien puede editar):
  - "Suspender" en un contrato en ejecución sin suspensión abierta, y "Reiniciar" en uno suspendido;
  - con cambios sin guardar quedan con `aria-disabled` y la nota "Guarda o descarta tus cambios primero";
  - con una suspensión abierta no se ofrecen Terminar ni Liquidar, y aparece la nota "Reinicia el contrato antes de terminarlo o liquidarlo.".
- **Diálogo Suspender:** fecha de suspensión (hoy por defecto), prevista opcional, número del acta y motivo.
- **Diálogo Reiniciar:**
  - fecha de reinicio (hoy por defecto) y observación;
  - la fecha de suspensión solo si no hay una abierta;
  - una vista previa: "Se suman N días: la fecha fin pasa de A a B.".
- **Al guardar:**
  - sale "Contrato suspendido" o "Contrato reiniciado" con la fecha fin nueva;
  - un 409 recarga y avisa;
  - si la recarga falla, el editor corrige el estado en local y avisa que la lista no se actualizó.
- **Franja de la información:**
  - "Suspendido desde X (N días)", con "Reinicio previsto el Y" y ": vencido" con el chip "Reinicio vencido";
  - la nota de que la fecha fin no cambia mientras esté suspendido.
- **Pestaña Modificaciones:**
  - el formulario no ofrece "Suspensión";
  - las suspensiones se listan con su detalle ("Del A al B: N días" o "Desde el A: abierta…");
  - la papelera aparece solo en la última suspensión, con una confirmación que explica qué pasa.
- **Select de estado:** "Suspendido (usa «Suspender»)" deshabilitado. Con una suspensión abierta, solo queda Suspendido, con "En ejecución (usa «Reiniciar»)" y "… (reinicia primero)".
- **SECOP II:**
  - no aplica «Suspendido» a un contrato guardado que no lo está ("SECOP II dice «Suspendido»: usa «Suspender»…");
  - no saca un contrato de una suspensión abierta ("…usa «Reiniciar»…").
- **Chip "Reinicio vencido"** en el módulo Contratos, la lista de Parámetros y la ficha del proyecto.

## Fuera de esta etapa

- **7C:** informes de supervisión.
- Pagos y obligaciones, solo si se confirman.
- **Eliminar una suspensión cerrada de un contrato terminado o liquidado:** se permite y recalcula su fecha fin, igual que eliminar una prórroga.

## Lo que ya se ensayó

El plan se ensayó completo en un clon descartable fuera del repositorio, y este documento se generó con el código de ese ensayo. Un script comprobó que aplicar sus bloques sobre la rama reproduce el ensayo exactamente.

- **Pruebas:** con el código de las tareas 2 y 3 pasan 1391 pruebas PHP y 969 JS. Cada tarea también pasa sus suites completas.
  - **Pruebas PHP existentes que cambiaron, todas de contratación:**
    - dos ganan la clave nueva;
    - una cambia el estado de ejemplo de `suspendido` a `celebrado`, por la regla de editar;
    - dos comparan la fecha real sugerida en la zona de contratación, como lo hace el servicio. Esas dos fallaban con el código de `main` cada día entre las 7 p. m. y la medianoche de Bogotá.
  - No cambió ninguna prueba de viabilización ni ningún fixture de paridad.
- **En una copia de `gest_pro` con los datos reales:** migrar, revertir y volver a migrar dio `SIN DIFERENCIAS` en las seis comparaciones. Con suspensiones registradas, revertir se detuvo con su mensaje.
- **En el navegador, sobre esa copia,** con un Administrador y un usuario Consulta de prueba creados solo en la copia, el 2026-11-19:
  - **Suspender C-TEST-001** (05/11/2026, previsto 10/11/2026, con acta):
    - mostró "Suspendido desde 05/11/2026 (14 días)", "Reinicio previsto el 10/11/2026: vencido" y el chip "Reinicio vencido";
    - solo quedó "Reiniciar", con la nota "Reinicia el contrato antes de terminarlo o liquidarlo.";
    - la lista de `/contratos` mostró "Suspendido" con "Reinicio vencido", y la fecha fin siguió en 31/12/2026.
  - **Terminar por API** respondió 422 "Reinicia el contrato antes de terminarlo o liquidarlo.".
  - **Reiniciar con la fecha de hoy:**
    - la vista previa decía "Se suman 14 días: la fecha fin pasa de 31/12/2026 a 14/01/2027.";
    - al guardar salió "Contrato reiniciado" con "La fecha fin vigente es el 14/01/2027.";
    - en Modificaciones apareció "Suspensión. Del 05/11/2026 al 19/11/2026: 14 días".
  - **Eliminar esa suspensión** pidió confirmación y devolvió la fecha fin a 31/12/2026. Una suspensión abierta eliminada dejó el contrato "En ejecución".
  - **Rechazos por API:**
    - un reinicio con una versión vieja respondió 409;
    - `guardarModificacionContrato` con tipo `suspension` respondió 422.
  - **Varias suspensiones (C-DECIMAL-TEST):** con dos suspensiones, solo la última mostró la papelera. Se reinició por API mientras la pantalla seguía abierta, y eliminar desde la pantalla respondió "No se eliminó la suspensión" (409). El reinicio de la otra persona se conservó y la pestaña se recargó con la suspensión cerrada.
  - **Consulta:** no vio botones de suspensión, y la API le respondió 403.
  - **Base:** valor, valor inicial, fecha fin inicial y avances quedaron iguales. El historial registró `contrato_suspendido`, `contrato_reiniciado` y `suspension_eliminada`.
- **Una revisión independiente** encontró 3 hallazgos medios y 5 bajos, sin ninguno alto. Todos se corrigieron en las tareas correspondientes, salvo dos bajos aceptados.
  - **Medios:**
    - eliminar una suspensión no controlaba la versión y podía deshacer el reinicio de otra persona; ahora exige `actualizado` y responde 409 (verificado en el navegador);
    - si la recarga fallaba tras reiniciar o eliminar, la pantalla quedaba desincronizada; ahora se corrige en local y avisa;
    - "Actualizar desde SECOP II" podía copiar el estado «Suspendido» o sacarlo de él; ahora no lo aplica y avisa.
  - **Bajos corregidos:**
    - las adiciones y prórrogas bloquean la fila del contrato para no pisar un reinicio concurrente;
    - una suspensión que ya no existe responde 404;
    - la papelera aparece solo en la última suspensión;
    - el select dice "(reinicia primero)" en Terminado y Liquidado.
  - **Bajos aceptados:**
    - se puede eliminar una suspensión cerrada de un contrato terminado o liquidado, igual que una prórroga;
    - `recalcular()` sumaría los días dos veces si a un contrato le faltara la fecha fin inicial; es un defecto anterior a esta fase y en `gest_pro` no hay ningún contrato así.

---

### Tarea 1: Punto de partida y fotos con el código actual

Las fotos se toman **antes de cambiar código**. La copia `gestpro_verif_tmp` se conserva hasta la tarea 5.

- [ ] **Paso 1: Comprobar la rama, el estado y las pruebas**

Run: `git branch --show-current`
Expected: `feature/contratacion`

Run: `git status --short`
Expected: como mucho, `public/` (si corre `npm run watch`) y las dos imágenes del usuario.

Run: `php artisan test`
Expected: pasan todas, 1346. Entre las 7 p. m. y la medianoche de Bogotá pueden fallar 2, "un propio pesa 1" (`AvancePonderadoApiTest`) y "marcar responde tambien el avance por etapa" (`ExpedienteContratacionApiTest`), porque comparan la fecha en UTC; la tarea 2 las corrige. Si falla cualquier otra, detenerse y avisar.

Run: `npm test`
Expected: pasan todas, 918.

- [ ] **Paso 2: Crear la copia descartable**

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE IF EXISTS gestpro_verif_tmp; CREATE DATABASE gestpro_verif_tmp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro | /c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp
```

Si MySQL no responde (`ERROR 2002`), está apagado. Hay que pedirle al usuario que lo arranque desde XAMPP.

- [ ] **Paso 3: Crear los scripts de comprobación**

Si siguen en `$TMP_DIR` desde etapas anteriores, se reutilizan tal cual. Si no, se crean con este contenido.

`$TMP_DIR/gestpro_foto_contratacion2.php` toma los expedientes, con la API de viabilización, y las plantillas y requisitos del catálogo:

```php
<?php
// Foto para comparar antes y después de migrar: los expedientes, que esta etapa no debe tocar,
// y las plantillas y requisitos del catálogo, que las migraciones 16 y 17 amplían.
// Uso, desde la raíz del repositorio:
//   php gestpro_foto_contratacion2.php <archivo.json> <base esperada> [--solo-tablas]
// --solo-tablas lee las tablas sin pasar por el servicio de expedientes.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProyectoExpediente;
use App\Services\Viabilizacion\ExpedienteService;
use Illuminate\Support\Facades\DB;

if (DB::getDatabaseName() !== $argv[2]) {
    exit('Abortado: la base es ' . DB::getDatabaseName() . ", se esperaba {$argv[2]}\n");
}

$columnasExpediente = ['id', 'proyecto_id', 'fase_sgr', 'fecha_configuracion', 'estado', 'fecha_radicado',
    'usuario_radicado_id', 'created_at', 'usuario_creo_id'];
$columnasFila = ['id', 'expediente_id', 'requisito_catalogo_id', 'aplica', 'cumplido', 'fecha_cumplido',
    'usuario_cumplido_id', 'observacion', 'soporte_archivo', 'soporte_nombre', 'orden', 'estado_aplicabilidad',
    'caracter', 'responsable_id', 'titulo_propio'];
// Solo las columnas que existían antes de esta etapa.
$columnasProceso = ['id', 'proyecto', 'codigo_proceso', 'tipo_proceso', 'modalidad', 'entidad_contratante',
    'tipo_proponente', 'entidad_proponente', 'monto'];

$foto = [
    'tablas' => [
        'expedientes' => DB::table('proyectos_expedientes')->orderBy('id')->get($columnasExpediente),
        'expediente_plantillas' => DB::table('expediente_plantillas')->orderBy('expediente_id')->orderBy('catalogo_version_id')->get(),
        'filas' => DB::table('proyectos_requisitos')->orderBy('id')->get($columnasFila),
        'origenes' => DB::table('proyecto_requisito_origenes')->orderBy('proyecto_requisito_id')->orderBy('requisito_catalogo_id')->get(),
        'modalidades' => DB::table('modalidades')->orderBy('id')->get(['id', 'nombre', 'activo']),
        'procesos' => DB::table('procesos_licitacion')->orderBy('id')->get($columnasProceso),
        'contratos_proceso' => DB::table('contratos')->orderBy('id')->pluck('proceso_licitacion', 'id'),
        'plantillas' => DB::table('plantillas_checklist')->orderBy('id')->get(['id', 'clave', 'nombre', 'motor', 'activo', 'todas_fuentes', 'todos_sectores']),
        'requisitos' => DB::table('requisitos_catalogo')->orderBy('id')->get(['id', 'catalogo_version_id', 'clave', 'texto', 'orden', 'activo', 'caracter', 'codigo_comun']),
    ],
];

if (! in_array('--solo-tablas', $argv, true)) {
    $servicio = app(ExpedienteService::class);
    $foto['api'] = [
        'resumen' => $servicio->resumenPorProyecto(),
        'expedientes' => ProyectoExpediente::query()->orderBy('id')->get()
            ->map(fn (ProyectoExpediente $e) => $servicio->presentar($e))->all(),
    ];
}

file_put_contents($argv[1], json_encode($foto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo 'Expedientes: ' . count($foto['tablas']['expedientes']) . ', filas: ' . count($foto['tablas']['filas'])
    . ', plantillas: ' . count($foto['tablas']['plantillas']) . ', requisitos: ' . count($foto['tablas']['requisitos'])
    . (isset($foto['api']) ? ', con API' : ', solo tablas') . "\n";
```

`$TMP_DIR/gestpro_foto_contratacion3.php` toma los contratos con su ejecución, los procesos y los listados de la API:

```php
<?php
// Foto para comparar antes y después de migrar: contratos (con su ejecución), procesos y
// expedientes de viabilización, que esta etapa no debe alterar.
// Uso, desde la raíz del repositorio:
//   php gestpro_foto_contratacion3.php <archivo.json> <base esperada> [--solo-tablas]
// --solo-tablas lee las tablas sin pasar por controladores ni servicios.
// El estado del contrato cambia a propósito (texto libre → valor controlado): se compara el
// texto original, que la migración guarda en estado_original.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ProyectoController;
use App\Models\ProyectoExpediente;
use App\Services\Viabilizacion\ExpedienteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (DB::getDatabaseName() !== $argv[2]) {
    exit('Abortado: la base es ' . DB::getDatabaseName() . ", se esperaba {$argv[2]}\n");
}

// Solo las columnas que existían antes de esta etapa (sin estado, que se compara aparte).
$columnasContrato = ['id', 'proyecto', 'n_contrato', 'objeto', 'contratante', 'contratista', 'valor', 'valor_inicial',
    'fecha_inicio', 'fecha_fin', 'fecha_fin_inicial', 'interventoria', 'avance_fisico', 'avance_financiero',
    'anticipo', 'porcentaje_anticipo', 'proceso_licitacion'];
$columnasProceso = ['id', 'proyecto', 'codigo_proceso', 'tipo_proceso', 'modalidad', 'entidad_contratante',
    'tipo_proponente', 'entidad_proponente', 'monto', 'objeto', 'plataforma', 'url_proceso', 'estado',
    'usuario_creo_id', 'created_at', 'updated_at'];
$estadoTexto = Schema::hasColumn('contratos', 'estado_original') ? 'COALESCE(estado_original, estado)' : 'estado';

$foto = [
    'tablas' => [
        'contratos' => DB::table('contratos')->orderBy('id')->get($columnasContrato),
        'estado_texto' => DB::table('contratos')->orderBy('id')->pluck(DB::raw($estadoTexto), 'id'),
        'procesos' => DB::table('procesos_licitacion')->orderBy('id')->get($columnasProceso),
        'actas' => DB::table('avance_financiero')->orderBy('id')->get(),
        'actividades' => DB::table('actividades_contrato')->orderBy('id')->get(),
        'avances_actividad' => DB::table('actividad_avances')->orderBy('id')->get(),
        'modificaciones' => DB::table('modificaciones_contrato')->orderBy('id')->get(),
        'anexos' => DB::table('anexos_contratos')->orderBy('id')->get(),
        'expedientes' => DB::table('proyectos_expedientes')->orderBy('id')->get(['id', 'proyecto_id', 'estado']),
        'filas' => DB::table('proyectos_requisitos')->orderBy('id')->get(['id', 'expediente_id', 'cumplido', 'estado_aplicabilidad']),
    ],
];

if (! in_array('--solo-tablas', $argv, true)) {
    $controlador = app(ProyectoController::class);
    $sinEstado = function (array $contrato): array {
        unset($contrato['estado']);

        return $contrato;
    };
    $porProyecto = [];
    foreach (DB::table('contratos')->whereNotNull('proyecto')->distinct()->orderBy('proyecto')->pluck('proyecto') as $proyecto) {
        $respuesta = $controlador->listarContratos(new Request(['proyecto' => $proyecto]));
        $porProyecto[$proyecto] = array_map($sinEstado, json_decode($respuesta->getContent(), true));
    }
    $proyectos = json_decode($controlador->proyectos()->getContent(), true);
    $contratosDeProyectos = [];
    foreach ($proyectos as $proyecto) {
        foreach ($proyecto['contratos'] ?? [] as $contrato) {
            $contratosDeProyectos[$contrato['id']] = $sinEstado($contrato);
        }
    }
    ksort($contratosDeProyectos);
    $servicio = app(ExpedienteService::class);
    $foto['api'] = [
        'listar_contratos' => $porProyecto,
        'contratos_en_proyectos' => $contratosDeProyectos,
        'resumen_expedientes' => $servicio->resumenPorProyecto(),
        'expedientes' => ProyectoExpediente::query()->orderBy('id')->get()
            ->map(fn (ProyectoExpediente $e) => $servicio->presentar($e))->all(),
    ];
}

file_put_contents($argv[1], json_encode($foto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo 'Contratos: ' . count($foto['tablas']['contratos']) . ', procesos: ' . count($foto['tablas']['procesos'])
    . ', actas: ' . count($foto['tablas']['actas']) . ', expedientes: ' . count($foto['tablas']['expedientes'])
    . (isset($foto['api']) ? ', con API' : ', solo tablas') . "\n";
```

`$TMP_DIR/gestpro_comparar_contratacion3.php` es el mismo comparador para las dos fotos:

```php
<?php
// Comprueba que todo lo que había antes sigue igual después. Lo nuevo (campos que la etapa
// agrega) se permite; un valor distinto o algo que falta, no.
// Uso: php gestpro_comparar_contratacion3.php <antes.json> <despues.json>

function comparar(mixed $antes, mixed $despues, string $ruta, array &$diferencias): void
{
    if (is_array($antes)) {
        if (! is_array($despues)) {
            $diferencias[] = "{$ruta}: ya no es una lista u objeto";

            return;
        }
        foreach ($antes as $clave => $valor) {
            if (! array_key_exists($clave, $despues)) {
                $diferencias[] = "{$ruta}.{$clave}: falta";
                continue;
            }
            comparar($valor, $despues[$clave], "{$ruta}.{$clave}", $diferencias);
        }

        return;
    }
    if ($antes !== $despues) {
        $diferencias[] = "{$ruta}: " . json_encode($antes, JSON_UNESCAPED_UNICODE) . ' → ' . json_encode($despues, JSON_UNESCAPED_UNICODE);
    }
}

$antes = json_decode(file_get_contents($argv[1]), true);
$despues = json_decode(file_get_contents($argv[2]), true);
$diferencias = [];
comparar($antes, $despues, '', $diferencias);

if ($diferencias) {
    echo 'DIFERENCIAS: ' . count($diferencias) . "\n" . implode("\n", array_slice($diferencias, 0, 40)) . "\n";
    exit(1);
}
echo "SIN DIFERENCIAS\n";
```

- [ ] **Paso 4: Tomar las fotos de antes, con el código actual**

Desde la raíz del repositorio, por cada foto (`2` y `3`), una completa y una solo de tablas:

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c12_antes_$f.json" gestpro_verif_tmp; DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c12_tablas_antes_$f.json" gestpro_verif_tmp --solo-tablas; done
```

Expected: `Expedientes: 14, filas: 331, plantillas: 5, requisitos: 241` y `Contratos: 7, procesos: 3, actas: 2, expedientes: 14`, con las cifras actuales de `gest_pro`.

---

### Tarea 2: API para suspender y reiniciar un contrato (migración 25)

La migración 25 con las dos fechas de reinicio en `modificaciones_contrato`; el servicio `SuspensionContratos` (con `SuspensionGuardada`) y las rutas `POST /contratos/{contrato}/suspender` y `/reiniciar` en `ContratoController`, con versión, bloqueo e historial; en la pestaña Modificaciones, el rechazo del tipo `suspension` al registrar, la eliminación de la última suspensión con versión y el bloqueo de la fila del contrato en adiciones y prórrogas; la regla de editar, el rechazo de terminar o liquidar con una suspensión abierta y `aviso_suspension` en `presentar()`, la búsqueda, el detalle, `listarContratos` y GET /proyectos. También corrige dos pruebas existentes que comparaban la fecha real sugerida en UTC. Valor y avances no cambian.

**Files:**
- Create: `app/Services/Contratacion/SuspensionContratos.php`
- Create: `app/Services/Contratacion/SuspensionGuardada.php`
- Create: `database/migrations/2026_11_19_100024_add_suspension_a_modificaciones_contrato.php`
- Test (create): `tests/Feature/Contratacion/SuspensionContratosApiTest.php`
- Modify: `app/Http/Controllers/ContratoController.php`
- Modify: `app/Http/Controllers/ProyectoController.php`
- Modify: `app/Models/ModificacionContrato.php`
- Modify: `app/Services/Contratacion/CierreContratos.php`
- Modify: `app/Services/Contratacion/ContratosService.php`
- Modify: `routes/contratacion.php`
- Test: `tests/Feature/Contratacion/AvancePonderadoApiTest.php`
- Test: `tests/Feature/Contratacion/ContratosApiTest.php`
- Test: `tests/Feature/Contratacion/ContratosBusquedaApiTest.php`
- Test: `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/SuspensionContratosApiTest.php`:

```php
<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\Contrato;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Contratacion\ContratosService;
use App\Services\Contratacion\SuspensionContratos;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Fase 7B: suspender y reiniciar un contrato (POST /contratos/{id}/suspender y /reiniciar), las
 * suspensiones en la pestaña Modificaciones (guardar y eliminar), las reglas de editar, el ajuste de
 * terminar y liquidar, el aviso de suspensión en los listados y la migración 25. Hoy es el 19 de
 * noviembre de 2026 (en Bogotá) salvo que la prueba diga otra cosa.
 */
class SuspensionContratosApiTest extends TestCase
{
    use RefreshDatabase;

    private const HOY = '2026-11-19';

    private Proyecto $proyecto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(self::HOY . ' 10:00:00');
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
    }

    private function como(Rol|User $quien = Rol::Gestor): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function contrato(array $mas = []): Contrato
    {
        return Contrato::create([
            'proyecto' => $this->proyecto->id,
            'n_contrato' => 'C-001-2026',
            'objeto' => 'Construcción del acueducto veredal',
            'valor_inicial' => 1000000,
            'valor' => 1000000,
            'fecha_inicio' => '2026-02-01',
            'fecha_fin_inicial' => '2026-12-31',
            'fecha_fin' => '2026-12-31',
            'avance_fisico' => 80,
            'avance_financiero' => 60,
            'estado' => Contrato::EN_EJECUCION,
            ...$mas,
        ]);
    }

    private function version(Contrato $contrato): ?string
    {
        return $contrato->fresh()->updated_at?->format('Y-m-d H:i:s');
    }

    private function suspender(Contrato $contrato, array $datos = [], Rol|User $quien = Rol::Gestor)
    {
        return $this->como($quien)->postJson("/GestPro/contratos/{$contrato->id}/suspender", [
            'fecha_suspension' => '2026-11-01',
            'actualizado' => $this->version($contrato),
            ...$datos,
        ]);
    }

    private function reiniciar(Contrato $contrato, array $datos = [], Rol|User $quien = Rol::Gestor)
    {
        return $this->como($quien)->postJson("/GestPro/contratos/{$contrato->id}/reiniciar", [
            'fecha_reinicio' => '2026-11-15',
            'actualizado' => $this->version($contrato),
            ...$datos,
        ]);
    }

    /** @return list<array{0: string, 1: ?array, 2: ?array}> */
    private function eventos(Contrato $contrato): array
    {
        return DB::table('checklist_historial')
            ->where('entidad_tipo', 'contrato')
            ->where('entidad_id', $contrato->id)
            ->orderBy('id')->get()
            ->map(fn ($h) => [$h->evento, json_decode((string) $h->antes, true), json_decode((string) $h->despues, true)])
            ->all();
    }

    /** Las suspensiones del contrato, en orden, con sus fechas como año-mes-día. */
    private function suspensiones(Contrato $contrato): array
    {
        $dia = fn ($v) => $v === null ? null : substr((string) $v, 0, 10);

        return DB::table('modificaciones_contrato')->where('contrato_id', $contrato->id)->where('tipo', 'suspension')
            ->orderBy('id')->get()
            ->map(fn ($f) => [
                'fecha_modificacion' => $dia($f->fecha_modificacion),
                'fecha_reinicio' => $dia($f->fecha_reinicio),
                'fecha_reinicio_prevista' => $dia($f->fecha_reinicio_prevista),
                'dias_prorroga' => $f->dias_prorroga === null ? null : (int) $f->dias_prorroga,
                'valor_adicion' => $f->valor_adicion,
                'numero_otrosi' => $f->numero_otrosi,
                'justificacion' => $f->justificacion,
            ])->all();
    }

    /** Formulario de edición completo del contrato (como lo manda la pantalla). */
    private function editar(Contrato $contrato, array $mas = [])
    {
        $c = $contrato->fresh();

        return $this->como()->postJson('/GestPro/guardarContrato', ['formContrato' => [
            'id' => $c->id,
            'actualizado' => $this->version($c),
            'n_contrato' => $c->n_contrato,
            'objeto' => $c->objeto,
            'valor_inicial' => $c->valor_inicial,
            'estado' => $c->estado,
            'proyecto' => $c->proyecto,
            ...$mas,
        ], 'anexos' => null]);
    }

    private function idSuspension(Contrato $contrato, int $orden = 0): int
    {
        return (int) DB::table('modificaciones_contrato')->where('contrato_id', $contrato->id)->where('tipo', 'suspension')
            ->orderBy('id')->skip($orden)->value('id');
    }

    // ------------------------------------------------------------------ permisos

    public function test_sin_token_responde_401_y_consulta_403(): void
    {
        $c = $this->contrato();
        $s = $this->contrato(['n_contrato' => 'S', 'estado' => Contrato::SUSPENDIDO]);

        $this->postJson("/GestPro/contratos/{$c->id}/suspender", ['fecha_suspension' => '2026-11-01', 'actualizado' => null])->assertUnauthorized();
        $this->postJson("/GestPro/contratos/{$s->id}/reiniciar", ['fecha_reinicio' => '2026-11-15', 'actualizado' => null])->assertUnauthorized();
        $this->suspender($c, [], Rol::Consulta)->assertForbidden();
        $this->reiniciar($s, ['fecha_suspension' => '2026-11-01'], Rol::Consulta)->assertForbidden();

        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);
        $this->assertSame(Contrato::SUSPENDIDO, $s->fresh()->estado);
        $this->assertSame(0, DB::table('modificaciones_contrato')->count());
        $this->assertSame([], $this->eventos($c));
    }

    public function test_un_contrato_que_no_existe_responde_404(): void
    {
        $this->como()->postJson('/GestPro/contratos/9999/suspender', ['fecha_suspension' => '2026-11-01', 'actualizado' => null])
            ->assertNotFound()->assertExactJson(['mensaje' => 'El contrato no existe.']);
        $this->como()->postJson('/GestPro/contratos/9999/reiniciar', ['fecha_reinicio' => '2026-11-15', 'actualizado' => null])
            ->assertNotFound()->assertExactJson(['mensaje' => 'El contrato no existe.']);
    }

    // ------------------------------------------------------------------ suspender

    public function test_suspende_un_contrato_en_ejecucion(): void
    {
        $c = $this->contrato();
        $version = $this->version($c);
        $this->travel(5)->seconds();

        $this->suspender($c, [
            'fecha_suspension' => '2026-11-05',
            'fecha_reinicio_prevista' => '2026-12-01',
            'numero_acta' => '  Acta 3  ',
            'motivo' => 'Ola invernal',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('avisos', [])
            ->assertJsonPath('contrato.estado', Contrato::SUSPENDIDO)
            ->assertJsonPath('contrato.fecha_fin', '2026-12-31')
            ->assertJsonPath('contrato.aviso_suspension', [
                'desde' => '2026-11-05', 'dias' => 14, 'reinicio_previsto' => '2026-12-01', 'vencido' => false,
            ]);

        $this->assertSame([[
            'fecha_modificacion' => '2026-11-05',
            'fecha_reinicio' => null,
            'fecha_reinicio_prevista' => '2026-12-01',
            'dias_prorroga' => null,
            'valor_adicion' => null,
            'numero_otrosi' => 'Acta 3',
            'justificacion' => 'Ola invernal',
        ]], $this->suspensiones($c));
        $fresco = $c->fresh();
        $this->assertNotSame($version, $this->version($fresco));
        $this->assertSame('2026-12-31', $fresco->fecha_fin->format('Y-m-d'));
        $this->assertSame('1000000.00', $fresco->valor);
    }

    public static function estadosQueNoSeSuspenden(): array
    {
        return [
            'celebrado' => [Contrato::CELEBRADO],
            'suspendido' => [Contrato::SUSPENDIDO],
            'terminado' => [Contrato::TERMINADO],
            'liquidado' => [Contrato::LIQUIDADO],
            'anulado' => [Contrato::ANULADO],
        ];
    }

    #[DataProvider('estadosQueNoSeSuspenden')]
    public function test_solo_se_suspende_un_contrato_en_ejecucion(string $estado): void
    {
        $c = $this->contrato(['estado' => $estado]);

        $this->suspender($c)->assertUnprocessable()->assertJsonPath('mensaje', 'Solo se suspende un contrato en ejecución.');
        $this->assertSame($estado, $c->fresh()->estado);
        $this->assertSame([], $this->suspensiones($c));
        $this->assertSame([], $this->eventos($c));
    }

    public function test_no_se_suspende_con_una_suspension_abierta(): void
    {
        $c = $this->contrato();
        $this->suspender($c)->assertOk();

        $this->suspender($c, ['fecha_suspension' => '2026-11-10'])->assertUnprocessable()
            ->assertJsonPath('mensaje', 'El contrato ya tiene una suspensión abierta.');

        // Una abierta en un contrato en ejecución (datos inconsistentes) también lo impide.
        DB::table('contratos')->where('id', $c->id)->update(['estado' => Contrato::EN_EJECUCION]);
        $this->suspender($c, ['fecha_suspension' => '2026-11-10'])->assertUnprocessable()
            ->assertJsonPath('mensaje', 'El contrato ya tiene una suspensión abierta.');
        $this->assertCount(1, $this->suspensiones($c));
    }

    public function test_la_fecha_de_suspension_es_obligatoria_y_con_formato(): void
    {
        $c = $this->contrato();

        $this->suspender($c, ['fecha_suspension' => null])->assertUnprocessable()->assertJsonValidationErrors('fecha_suspension');
        $this->suspender($c, ['fecha_suspension' => '01/11/2026'])->assertUnprocessable()->assertJsonValidationErrors('fecha_suspension');
        $this->suspender($c, ['fecha_reinicio_prevista' => 'mañana'])->assertUnprocessable()->assertJsonValidationErrors('fecha_reinicio_prevista');
        $this->suspender($c, ['numero_acta' => str_repeat('a', 51)])->assertUnprocessable()->assertJsonValidationErrors('numero_acta');
        $this->suspender($c, ['motivo' => str_repeat('a', 2001)])->assertUnprocessable()->assertJsonValidationErrors('motivo');
        $this->como()->postJson("/GestPro/contratos/{$c->id}/suspender", ['fecha_suspension' => '2026-11-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('actualizado');
        $this->assertSame([], $this->suspensiones($c));
    }

    public function test_la_fecha_de_suspension_no_es_posterior_a_hoy_en_bogota(): void
    {
        // 03:00 del 20 en UTC son las 22:00 del 19 en Bogotá: hoy es el 19.
        $this->travelTo('2026-11-20 03:00:00');
        $c = $this->contrato();

        $this->suspender($c, ['fecha_suspension' => '2026-11-20'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_suspension.0', 'La fecha de suspensión no puede ser posterior a hoy.');
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);

        $this->suspender($c, ['fecha_suspension' => '2026-11-19'])->assertOk()
            ->assertJsonPath('contrato.aviso_suspension.dias', 0);
    }

    public function test_la_fecha_de_suspension_no_es_anterior_a_la_de_inicio(): void
    {
        $c = $this->contrato(['fecha_inicio' => '2026-02-01']);

        $this->suspender($c, ['fecha_suspension' => '2026-01-31'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_suspension.0', 'La fecha de suspensión no puede ser anterior a la de inicio.');
        $this->suspender($c, ['fecha_suspension' => '2026-02-01'])->assertOk();

        // Sin fecha de inicio, no hay tope.
        $sinInicio = $this->contrato(['n_contrato' => 'B', 'fecha_inicio' => null]);
        $this->suspender($sinInicio, ['fecha_suspension' => '2025-06-01'])->assertOk();
    }

    public function test_la_fecha_de_suspension_no_es_anterior_al_ultimo_reinicio(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-10-01'])->assertOk();
        $this->reiniciar($c, ['fecha_reinicio' => '2026-10-20'])->assertOk();

        $this->suspender($c, ['fecha_suspension' => '2026-10-19'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_suspension.0', 'La fecha de suspensión no puede ser anterior al último reinicio.');
        // El mismo día del reinicio sí.
        $this->suspender($c, ['fecha_suspension' => '2026-10-20'])->assertOk();
    }

    public function test_la_fecha_prevista_no_es_anterior_a_la_de_suspension(): void
    {
        $c = $this->contrato();

        $this->suspender($c, ['fecha_suspension' => '2026-11-05', 'fecha_reinicio_prevista' => '2026-11-04'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_reinicio_prevista.0', 'La fecha prevista de reinicio no puede ser anterior a la de suspensión.');
        // Puede ser pasada (queda vencida) o el mismo día.
        $this->suspender($c, ['fecha_suspension' => '2026-11-05', 'fecha_reinicio_prevista' => '2026-11-05'])->assertOk()
            ->assertJsonPath('contrato.aviso_suspension.vencido', true);
    }

    public function test_suspender_con_una_version_vieja_responde_409(): void
    {
        $c = $this->contrato();
        $vieja = $this->version($c);
        $this->travel(5)->seconds();
        $c->fresh()->touch();

        $this->suspender($c, ['actualizado' => $vieja])->assertStatus(409)
            ->assertExactJson(['mensaje' => ContratosService::DESACTUALIZADO]);
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);
        $this->assertSame([], $this->suspensiones($c));
    }

    public function test_suspender_deja_historial_con_antes_y_despues(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-05', 'numero_acta' => 'Acta 3', 'motivo' => 'Lluvias'])->assertOk();

        $this->assertSame([[
            'contrato_suspendido',
            ['estado' => Contrato::EN_EJECUCION],
            [
                'estado' => Contrato::SUSPENDIDO,
                'fecha_suspension' => '2026-11-05',
                'fecha_reinicio_prevista' => null,
                'numero_acta' => 'Acta 3',
                'motivo' => 'Lluvias',
                'modificacion_id' => $this->idSuspension($c),
            ],
        ]], $this->eventos($c));
    }

    // ------------------------------------------------------------------ reiniciar

    public function test_reinicia_y_corre_la_fecha_fin_como_una_prorroga(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-01'])->assertOk();
        $version = $this->version($c);
        $this->travel(5)->seconds();

        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-15', 'observacion' => 'Cesaron las lluvias'])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('avisos', [])
            ->assertJsonPath('contrato.estado', Contrato::EN_EJECUCION)
            ->assertJsonPath('contrato.fecha_fin', '2027-01-14')
            ->assertJsonPath('contrato.aviso_suspension', null)
            ->assertJsonPath('resumen.fecha_fin_inicial', '2026-12-31')
            ->assertJsonPath('resumen.fecha_fin_vigente', '2027-01-14');

        $this->assertSame([[
            'fecha_modificacion' => '2026-11-01',
            'fecha_reinicio' => '2026-11-15',
            'fecha_reinicio_prevista' => null,
            'dias_prorroga' => 14,
            'valor_adicion' => null,
            'numero_otrosi' => null,
            'justificacion' => null,
        ]], $this->suspensiones($c));

        // Igual que una prórroga de los mismos días en un contrato igual.
        $otro = $this->contrato(['n_contrato' => 'Gemelo']);
        $this->como()->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $otro->id, 'tipo' => 'prorroga', 'dias_prorroga' => 14, 'fecha_modificacion' => '2026-11-15',
        ])->assertOk();
        $this->assertSame($otro->fresh()->fecha_fin->format('Y-m-d'), $c->fresh()->fecha_fin->format('Y-m-d'));

        // Solo cambian el estado y la fecha fin vigente.
        $fresco = $c->fresh();
        $this->assertNotSame($version, $this->version($fresco));
        $this->assertSame('1000000.00', $fresco->valor);
        $this->assertSame('1000000.00', $fresco->valor_inicial);
        $this->assertSame('2026-12-31', $fresco->fecha_fin_inicial->format('Y-m-d'));
        $this->assertSame(80, (int) $fresco->avance_fisico);
        $this->assertSame(60, (int) $fresco->avance_financiero);

        [$evento, $antes, $despues] = $this->eventos($c)[1];
        $this->assertSame('contrato_reiniciado', $evento);
        $this->assertSame(['estado' => Contrato::SUSPENDIDO, 'fecha_fin' => '2026-12-31'], $antes);
        $this->assertSame([
            'estado' => Contrato::EN_EJECUCION,
            'fecha_fin' => '2027-01-14',
            'fecha_suspension' => '2026-11-01',
            'fecha_reinicio' => '2026-11-15',
            'dias_suspendido' => 14,
            'observacion' => 'Cesaron las lluvias',
            'modificacion_id' => $this->idSuspension($c),
        ], $despues);
    }

    public function test_reiniciar_el_mismo_dia_suma_cero_dias(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-10'])->assertOk();

        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-10'])->assertOk()
            ->assertJsonPath('contrato.fecha_fin', '2026-12-31');
        $this->assertSame(0, $this->suspensiones($c)[0]['dias_prorroga']);
    }

    public function test_reiniciar_sin_suspension_abierta_pide_la_fecha_y_la_crea_cerrada(): void
    {
        // Un contrato que llegó suspendido (de SECOP II o de antes de la fase 7B).
        $c = $this->contrato(['estado' => Contrato::SUSPENDIDO]);

        $this->reiniciar($c)->assertUnprocessable()
            ->assertJsonPath('errors.fecha_suspension.0', 'Escribe la fecha de suspensión.');
        $this->assertSame([], $this->suspensiones($c));

        $this->reiniciar($c, ['fecha_suspension' => '2026-11-11', 'fecha_reinicio' => '2026-11-10'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_reinicio.0', 'La fecha de reinicio no puede ser anterior a la de suspensión.');
        $this->reiniciar($c, ['fecha_suspension' => '2026-01-31'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_suspension.0', 'La fecha de suspensión no puede ser anterior a la de inicio.');

        $this->reiniciar($c, ['fecha_suspension' => '2026-11-05', 'fecha_reinicio' => '2026-11-15'])->assertOk()
            ->assertJsonPath('contrato.estado', Contrato::EN_EJECUCION)
            ->assertJsonPath('contrato.fecha_fin', '2027-01-10');
        $this->assertSame([[
            'fecha_modificacion' => '2026-11-05',
            'fecha_reinicio' => '2026-11-15',
            'fecha_reinicio_prevista' => null,
            'dias_prorroga' => 10,
            'valor_adicion' => null,
            'numero_otrosi' => null,
            'justificacion' => null,
        ]], $this->suspensiones($c));
        $this->assertSame('contrato_reiniciado', $this->eventos($c)[0][0]);
    }

    public function test_con_suspension_abierta_la_fecha_de_suspension_del_pedido_no_cuenta(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-01'])->assertOk();

        $this->reiniciar($c, ['fecha_suspension' => '2026-11-10', 'fecha_reinicio' => '2026-11-15'])->assertOk();
        $this->assertSame(['2026-11-01', 14], [$this->suspensiones($c)[0]['fecha_modificacion'], $this->suspensiones($c)[0]['dias_prorroga']]);
    }

    public static function estadosQueNoSeReinician(): array
    {
        return [
            'celebrado' => [Contrato::CELEBRADO],
            'en ejecución' => [Contrato::EN_EJECUCION],
            'terminado' => [Contrato::TERMINADO],
            'liquidado' => [Contrato::LIQUIDADO],
            'anulado' => [Contrato::ANULADO],
        ];
    }

    #[DataProvider('estadosQueNoSeReinician')]
    public function test_solo_se_reinicia_un_contrato_suspendido(string $estado): void
    {
        $c = $this->contrato(['estado' => $estado]);

        $this->reiniciar($c, ['fecha_suspension' => '2026-11-01'])->assertUnprocessable()
            ->assertJsonPath('mensaje', 'El contrato no está suspendido.');
        $this->assertSame($estado, $c->fresh()->estado);
        $this->assertSame([], $this->suspensiones($c));
    }

    public function test_las_fechas_de_reiniciar(): void
    {
        $this->travelTo('2026-11-20 03:00:00');
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-10'])->assertOk();

        $this->reiniciar($c, ['fecha_reinicio' => null])->assertUnprocessable()->assertJsonValidationErrors('fecha_reinicio');
        $this->reiniciar($c, ['fecha_reinicio' => '15/11/2026'])->assertUnprocessable()->assertJsonValidationErrors('fecha_reinicio');
        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-15', 'observacion' => str_repeat('a', 2001)])->assertUnprocessable()->assertJsonValidationErrors('observacion');
        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-20'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_reinicio.0', 'La fecha de reinicio no puede ser posterior a hoy.');
        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-09'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_reinicio.0', 'La fecha de reinicio no puede ser anterior a la de suspensión.');
        $this->assertSame(Contrato::SUSPENDIDO, $c->fresh()->estado);
        $this->assertNull($this->suspensiones($c)[0]['fecha_reinicio']);

        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-19'])->assertOk();
    }

    public function test_reiniciar_con_una_version_vieja_responde_409(): void
    {
        $c = $this->contrato();
        $this->suspender($c)->assertOk();
        $vieja = $this->version($c);
        $this->travel(5)->seconds();
        $c->fresh()->touch();

        $this->reiniciar($c, ['actualizado' => $vieja])->assertStatus(409)
            ->assertExactJson(['mensaje' => ContratosService::DESACTUALIZADO]);
        $this->assertSame(Contrato::SUSPENDIDO, $c->fresh()->estado);
    }

    public function test_dos_suspensiones_suman_sus_dias(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-10-01'])->assertOk();
        $this->reiniciar($c, ['fecha_reinicio' => '2026-10-11'])->assertOk()->assertJsonPath('contrato.fecha_fin', '2027-01-10');
        $this->suspender($c, ['fecha_suspension' => '2026-11-01'])->assertOk()->assertJsonPath('contrato.fecha_fin', '2027-01-10');
        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-06'])->assertOk()
            ->assertJsonPath('contrato.fecha_fin', '2027-01-15')
            ->assertJsonPath('resumen.fecha_fin_vigente', '2027-01-15');

        // Con una prórroga, también suma.
        $this->como()->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $c->id, 'tipo' => 'prorroga', 'dias_prorroga' => 5, 'fecha_modificacion' => '2026-11-10',
        ])->assertOk()->assertJsonPath('resumen.fecha_fin_vigente', '2027-01-20');
    }

    // ------------------------------------------------------------------ pestaña Modificaciones

    public function test_guardar_modificacion_no_registra_suspensiones_y_sigue_aceptando_prorrogas(): void
    {
        $c = $this->contrato();

        $this->como()->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $c->id, 'tipo' => 'suspension', 'dias_prorroga' => 10, 'fecha_modificacion' => '2026-11-01',
        ])->assertUnprocessable()->assertJsonPath('errors.tipo.0', 'Las suspensiones se registran con «Suspender».');
        $this->assertSame(0, DB::table('modificaciones_contrato')->count());

        $this->como()->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $c->id, 'tipo' => 'prorroga', 'dias_prorroga' => 10, 'fecha_modificacion' => '2026-11-01',
        ])->assertOk()->assertJsonPath('resumen.fecha_fin_vigente', '2027-01-10');
    }

    public function test_eliminar_la_suspension_abierta_devuelve_el_contrato_a_ejecucion(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-05', 'numero_acta' => 'Acta 3'])->assertOk();
        $id = $this->idSuspension($c);
        $version = $this->version($c);
        $this->travel(5)->seconds();

        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $id, 'actualizado' => $version])->assertOk()
            ->assertJsonPath('resumen.fecha_fin_vigente', '2026-12-31');

        $fresco = $c->fresh();
        $this->assertSame(Contrato::EN_EJECUCION, $fresco->estado);
        $this->assertSame('2026-12-31', $fresco->fecha_fin->format('Y-m-d'));
        $this->assertNotSame($version, $this->version($fresco));
        $this->assertSame([], $this->suspensiones($c));

        [$evento, $antes, $despues] = $this->eventos($c)[1];
        $this->assertSame('suspension_eliminada', $evento);
        $this->assertSame($id, $antes['modificacion_id']);
        $this->assertSame('2026-11-05', $antes['fecha_suspension']);
        $this->assertNull($antes['fecha_reinicio']);
        $this->assertSame('Acta 3', $antes['numero_acta']);
        $this->assertSame(Contrato::SUSPENDIDO, $antes['estado']);
        $this->assertSame(['estado' => Contrato::EN_EJECUCION, 'fecha_fin' => '2026-12-31'], $despues);
    }

    public function test_eliminar_la_suspension_cerrada_devuelve_la_fecha_fin(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-01'])->assertOk();
        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-15'])->assertOk()->assertJsonPath('contrato.fecha_fin', '2027-01-14');

        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $this->idSuspension($c), 'actualizado' => $this->version($c)])->assertOk()
            ->assertJsonPath('resumen.fecha_fin_vigente', '2026-12-31');

        $fresco = $c->fresh();
        $this->assertSame(Contrato::EN_EJECUCION, $fresco->estado);
        $this->assertSame('2026-12-31', $fresco->fecha_fin->format('Y-m-d'));
        [$evento, $antes, $despues] = $this->eventos($c)[2];
        $this->assertSame('suspension_eliminada', $evento);
        $this->assertSame(14, $antes['dias_suspendido']);
        $this->assertSame('2027-01-14', $antes['fecha_fin']);
        $this->assertSame(['estado' => Contrato::EN_EJECUCION, 'fecha_fin' => '2026-12-31'], $despues);
    }

    public function test_solo_se_elimina_la_ultima_suspension(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-10-01'])->assertOk();
        $this->reiniciar($c, ['fecha_reinicio' => '2026-10-11'])->assertOk();
        $this->suspender($c, ['fecha_suspension' => '2026-11-01'])->assertOk();

        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $this->idSuspension($c, 0), 'actualizado' => $this->version($c)])->assertUnprocessable()
            ->assertJsonPath('mensaje', 'Solo se puede eliminar la última suspensión.');
        $this->assertCount(2, $this->suspensiones($c));
        $this->assertSame(Contrato::SUSPENDIDO, $c->fresh()->estado);

        // Eliminar una prórroga sigue como antes, aunque haya suspensiones.
        $this->como()->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $c->id, 'tipo' => 'prorroga', 'dias_prorroga' => 5, 'fecha_modificacion' => '2026-11-10',
        ])->assertOk();
        $prorroga = DB::table('modificaciones_contrato')->where('tipo', 'prorroga')->value('id');
        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $prorroga])->assertOk()
            ->assertJsonPath('resumen.fecha_fin_vigente', '2027-01-10');
        $this->assertSame(Contrato::SUSPENDIDO, $c->fresh()->estado);
    }

    public function test_eliminar_una_suspension_sin_version_responde_422(): void
    {
        $c = $this->contrato();
        $this->suspender($c)->assertOk();

        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $this->idSuspension($c)])->assertUnprocessable()
            ->assertJsonPath('errors.actualizado.0', 'Falta la versión del contrato que estabas editando. Ciérralo y vuelve a abrirlo.');
        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $this->idSuspension($c), 'actualizado' => 'ayer'])->assertUnprocessable()
            ->assertJsonPath('errors.actualizado.0', 'La versión del contrato no es válida. Ciérralo y vuelve a abrirlo.');
        $this->assertCount(1, $this->suspensiones($c));
        $this->assertSame(Contrato::SUSPENDIDO, $c->fresh()->estado);
    }

    public function test_eliminar_una_suspension_con_una_version_vieja_responde_409_y_no_deshace_el_reinicio(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-01'])->assertOk();
        // La pestaña Modificaciones se abrió con esta versión; otra persona reinicia entretanto.
        $vieja = $this->version($c);
        $this->travel(5)->seconds();
        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-15'])->assertOk()->assertJsonPath('contrato.fecha_fin', '2027-01-14');

        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $this->idSuspension($c), 'actualizado' => $vieja])
            ->assertStatus(409)->assertExactJson(['mensaje' => ContratosService::DESACTUALIZADO]);

        $fresco = $c->fresh();
        $this->assertSame(Contrato::EN_EJECUCION, $fresco->estado);
        $this->assertSame('2027-01-14', $fresco->fecha_fin->format('Y-m-d'));
        $this->assertSame([['2026-11-01', '2026-11-15', 14]], array_map(
            fn ($f) => [$f['fecha_modificacion'], $f['fecha_reinicio'], $f['dias_prorroga']],
            $this->suspensiones($c)
        ));
        $this->assertSame(['contrato_suspendido', 'contrato_reiniciado'], array_column($this->eventos($c), 0));
    }

    public function test_la_version_se_exige_antes_que_la_regla_de_la_ultima(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-10-01'])->assertOk();
        $vieja = $this->version($c);
        $this->travel(5)->seconds();
        $this->reiniciar($c, ['fecha_reinicio' => '2026-10-11'])->assertOk();
        $this->suspender($c, ['fecha_suspension' => '2026-11-01'])->assertOk();

        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $this->idSuspension($c, 0), 'actualizado' => $vieja])
            ->assertStatus(409)->assertExactJson(['mensaje' => ContratosService::DESACTUALIZADO]);
        $this->assertCount(2, $this->suspensiones($c));
    }

    public function test_eliminar_una_suspension_que_ya_no_existe_responde_404(): void
    {
        $c = $this->contrato();
        $this->suspender($c)->assertOk();
        $id = $this->idSuspension($c);
        // Otra persona la eliminó entre la lectura del controlador y el bloqueo del contrato.
        DB::table('modificaciones_contrato')->where('id', $id)->delete();

        try {
            app(SuspensionContratos::class)->eliminar($id, $c->id, $this->version($c), null);
            $this->fail('Debió responder 404.');
        } catch (HttpResponseException $e) {
            $this->assertSame(404, $e->getResponse()->getStatusCode());
            $this->assertSame(['mensaje' => 'La suspensión ya no existe.'], $e->getResponse()->getData(true));
        }
        $this->assertSame(Contrato::SUSPENDIDO, $c->fresh()->estado);
        $this->assertSame(['contrato_suspendido'], array_column($this->eventos($c), 0));
    }

    /**
     * Registrar o eliminar una adición o prórroga bloquea primero la fila del contrato, para no pisar
     * la fecha fin de un reinicio que corre a la vez. SQLite no escribe FOR UPDATE: se comprueba que
     * el contrato se lee (con el bloqueo, en MySQL) antes de escribir en modificaciones_contrato.
     */
    public function test_guardar_y_eliminar_una_prorroga_bloquean_el_contrato_antes_de_escribir(): void
    {
        $c = $this->contrato();
        $consultas = [];
        DB::listen(function ($q) use (&$consultas) {
            $consultas[] = $q->sql;
        });
        $primera = function (array $consultas, string $patron): int {
            foreach ($consultas as $i => $sql) {
                if (preg_match($patron, $sql)) {
                    return $i;
                }
            }

            return PHP_INT_MAX;
        };

        $this->como()->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $c->id, 'tipo' => 'prorroga', 'dias_prorroga' => 10, 'fecha_modificacion' => '2026-11-01',
        ])->assertOk()->assertExactJson([
            'success' => 'Modificación registrada correctamente',
            'resumen' => [
                'valor_inicial' => 1000000, 'valor_vigente' => 1000000, 'fecha_fin_inicial' => '2026-12-31',
                'fecha_fin_vigente' => '2027-01-10', 'porcentaje_adicionado' => 0, 'supera_limite' => false,
            ],
        ]);
        $this->assertLessThan(
            $primera($consultas, '/^insert into "modificaciones_contrato"/'),
            $primera($consultas, '/^select .* from "contratos" where "contratos"\."id" = \?/')
        );

        $consultas = [];
        $prorroga = DB::table('modificaciones_contrato')->where('tipo', 'prorroga')->value('id');
        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $prorroga])->assertOk()
            ->assertJsonPath('success', 'Modificación eliminada correctamente')
            ->assertJsonPath('resumen.fecha_fin_vigente', '2026-12-31');
        $this->assertLessThan(
            $primera($consultas, '/^delete from "modificaciones_contrato"/'),
            $primera($consultas, '/^select .* from "contratos" where "contratos"\."id" = \?/')
        );

        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => 99999])->assertNotFound()
            ->assertExactJson(['error' => 'Modificación no encontrada']);
    }

    public function test_las_modificaciones_del_contrato_traen_las_fechas_de_reinicio(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-01', 'fecha_reinicio_prevista' => '2026-11-20'])->assertOk();
        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-15'])->assertOk();

        $mod = $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$c->id}/detalle")->assertOk()->json('modificaciones.0');
        $this->assertSame('suspension', $mod['tipo']);
        $this->assertSame('2026-11-15', substr($mod['fecha_reinicio'], 0, 10));
        $this->assertSame('2026-11-20', substr($mod['fecha_reinicio_prevista'], 0, 10));
        $this->assertSame(14, (int) $mod['dias_prorroga']);

        $listado = $this->como(Rol::Consulta)->getJson('/GestPro/listarContratos?proyecto=' . $this->proyecto->id)->assertOk()->json('0.modificaciones.0');
        $this->assertSame('2026-11-15', substr($listado['fecha_reinicio'], 0, 10));
    }

    // ------------------------------------------------------------------ editar, terminar y liquidar

    public function test_editar_no_pasa_a_suspendido(): void
    {
        $c = $this->contrato();

        $this->editar($c, ['estado' => Contrato::SUSPENDIDO])->assertUnprocessable()
            ->assertJsonPath('errors.estado.0', 'Para suspender el contrato usa «Suspender».');
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);

        // Uno ya suspendido (sin abierta) puede seguir así al editar otros campos.
        $s = $this->contrato(['n_contrato' => 'S', 'estado' => Contrato::SUSPENDIDO]);
        $this->editar($s, ['objeto' => 'Otro objeto'])->assertOk();
    }

    public function test_editar_no_sale_de_suspendido_con_una_suspension_abierta(): void
    {
        $c = $this->contrato();
        $this->suspender($c)->assertOk();

        $this->editar($c, ['estado' => Contrato::EN_EJECUCION])->assertUnprocessable()
            ->assertJsonPath('errors.estado.0', 'Para reanudar el contrato usa «Reiniciar».');
        $this->editar($c, ['estado' => Contrato::ANULADO])->assertUnprocessable()
            ->assertJsonPath('errors.estado.0', 'Para reanudar el contrato usa «Reiniciar».');
        $this->assertSame(Contrato::SUSPENDIDO, $c->fresh()->estado);
        // Seguir suspendido y cambiar otro campo sí.
        $this->editar($c, ['objeto' => 'Otro objeto'])->assertOk();

        // Sin suspensión abierta, se sale de suspendido.
        $s = $this->contrato(['n_contrato' => 'S', 'estado' => Contrato::SUSPENDIDO]);
        $this->editar($s, ['estado' => Contrato::EN_EJECUCION])->assertOk()->assertJsonPath('contrato.estado', Contrato::EN_EJECUCION);
    }

    public function test_crear_un_contrato_suspendido_se_permite(): void
    {
        $this->como()->postJson('/GestPro/guardarContrato', ['formContrato' => [
            'n_contrato' => 'N-1', 'objeto' => 'Obra', 'valor_inicial' => 10, 'estado' => Contrato::SUSPENDIDO,
            'proyecto' => $this->proyecto->id,
        ]])->assertOk()
            ->assertJsonPath('contrato.estado', Contrato::SUSPENDIDO)
            ->assertJsonPath('contrato.aviso_suspension', null);
    }

    public function test_terminar_o_liquidar_con_una_suspension_abierta_se_rechaza(): void
    {
        $c = $this->contrato();
        $this->suspender($c)->assertOk();

        $this->como()->postJson("/GestPro/contratos/{$c->id}/terminar", [
            'fecha_terminacion_real' => '2026-11-15', 'actualizado' => $this->version($c),
        ])->assertUnprocessable()->assertJsonPath('mensaje', 'Reinicia el contrato antes de terminarlo o liquidarlo.');
        $this->como()->postJson("/GestPro/contratos/{$c->id}/liquidar", [
            'fecha_terminacion_real' => '2026-11-15', 'fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => 1, 'actualizado' => $this->version($c),
        ])->assertUnprocessable()->assertJsonPath('mensaje', 'Reinicia el contrato antes de terminarlo o liquidarlo.');
        $this->assertSame(Contrato::SUSPENDIDO, $c->fresh()->estado);

        // Un suspendido sin suspensión abierta se termina como en la fase 7A.
        $s = $this->contrato(['n_contrato' => 'S', 'estado' => Contrato::SUSPENDIDO]);
        $this->como()->postJson("/GestPro/contratos/{$s->id}/terminar", [
            'fecha_terminacion_real' => '2026-11-15', 'actualizado' => $this->version($s),
        ])->assertOk()->assertJsonPath('contrato.estado', Contrato::TERMINADO);
    }

    // ------------------------------------------------------------------ aviso de suspensión

    public function test_el_aviso_de_suspension_en_la_busqueda_el_detalle_y_los_listados(): void
    {
        $vencido = $this->contrato(['n_contrato' => 'A']);
        $this->suspender($vencido, ['fecha_suspension' => '2026-11-01', 'fecha_reinicio_prevista' => '2026-11-18'])->assertOk();
        $alDia = $this->contrato(['n_contrato' => 'B']);
        $this->suspender($alDia, ['fecha_suspension' => '2026-11-05', 'fecha_reinicio_prevista' => '2026-11-19'])->assertOk();
        $sinPrevista = $this->contrato(['n_contrato' => 'C']);
        $this->suspender($sinPrevista, ['fecha_suspension' => '2026-11-19'])->assertOk();
        $suspendidoSinFila = $this->contrato(['n_contrato' => 'D', 'estado' => Contrato::SUSPENDIDO]);
        $enEjecucion = $this->contrato(['n_contrato' => 'E']);
        $reiniciado = $this->contrato(['n_contrato' => 'F']);
        $this->suspender($reiniciado, ['fecha_suspension' => '2026-11-01'])->assertOk();
        $this->reiniciar($reiniciado, ['fecha_reinicio' => '2026-11-02'])->assertOk();

        $esperado = [
            $vencido->id => ['desde' => '2026-11-01', 'dias' => 18, 'reinicio_previsto' => '2026-11-18', 'vencido' => true],
            $alDia->id => ['desde' => '2026-11-05', 'dias' => 14, 'reinicio_previsto' => '2026-11-19', 'vencido' => false],
            $sinPrevista->id => ['desde' => '2026-11-19', 'dias' => 0, 'reinicio_previsto' => null, 'vencido' => false],
            $suspendidoSinFila->id => null,
            $enEjecucion->id => null,
            $reiniciado->id => null,
        ];

        DB::enableQueryLog();
        $busqueda = collect($this->como(Rol::Consulta)->getJson('/GestPro/contratos/buscar')->assertOk()->json())->keyBy('id');
        $consultasBusqueda = count(DB::getQueryLog());
        DB::flushQueryLog();
        $listado = collect($this->como(Rol::Consulta)->getJson('/GestPro/listarContratos?proyecto=' . $this->proyecto->id)->assertOk()->json())->keyBy('id');
        DB::flushQueryLog();
        $proyectos = collect(collect($this->como(Rol::Consulta)->getJson('/GestPro/proyectos')->assertOk()->json())
            ->firstWhere('id', $this->proyecto->id)['contratos'])->keyBy('id');
        $consultasProyectos = count(DB::getQueryLog());

        foreach ($esperado as $id => $aviso) {
            $this->assertSame($aviso, $busqueda[$id]['aviso_suspension'], "búsqueda #{$id}");
            $this->assertSame($aviso, $listado[$id]['aviso_suspension'], "listado #{$id}");
            $this->assertSame($aviso, $proyectos[$id]['aviso_suspension'], "proyectos #{$id}");
            $this->assertSame($aviso, $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$id}/detalle")->assertOk()->json('aviso_suspension'), "detalle #{$id}");
        }
        // Y en la ficha del proyecto (sus contratos son los de la búsqueda).
        $ficha = collect($this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/ficha")->assertOk()->json('contratos'))->keyBy('id');
        $this->assertSame($esperado[$vencido->id], $ficha[$vencido->id]['aviso_suspension']);

        // Sin consultas por fila: con más contratos suspendidos, las mismas consultas.
        $mas = $this->contrato(['n_contrato' => 'G']);
        $this->suspender($mas, ['fecha_suspension' => '2026-11-10', 'fecha_reinicio_prevista' => '2026-11-12'])->assertOk();
        $this->contrato(['n_contrato' => 'H', 'estado' => Contrato::SUSPENDIDO]);
        DB::flushQueryLog();
        $this->como(Rol::Consulta)->getJson('/GestPro/contratos/buscar')->assertOk();
        $this->assertSame($consultasBusqueda, count(DB::getQueryLog()));
        DB::flushQueryLog();
        $this->como(Rol::Consulta)->getJson('/GestPro/proyectos')->assertOk();
        // GET /proyectos consulta los anexos, actas y actividades de cada contrato (ya era así):
        // dos contratos más son seis consultas más, ninguna de suspensiones.
        $this->assertSame($consultasProyectos + 2 * 3, count(DB::getQueryLog()));
    }

    public function test_el_aviso_se_vence_con_los_dias(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-10', 'fecha_reinicio_prevista' => '2026-11-19'])->assertOk()
            ->assertJsonPath('contrato.aviso_suspension.vencido', false);

        $this->travelTo('2026-11-21 03:00:00'); // el 20 en Bogotá
        $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$c->id}/detalle")->assertOk()
            ->assertJsonPath('aviso_suspension', ['desde' => '2026-11-10', 'dias' => 10, 'reinicio_previsto' => '2026-11-19', 'vencido' => true]);
    }

    // ------------------------------------------------------------------ migración

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_19_100024_add_suspension_a_modificaciones_contrato.php');
    }

    public function test_la_migracion_agrega_las_columnas_nulas_y_su_rollback_se_detiene_con_suspensiones(): void
    {
        $columnas = collect(Schema::getColumns('modificaciones_contrato'))->keyBy('name');
        foreach (['fecha_reinicio', 'fecha_reinicio_prevista'] as $columna) {
            $this->assertTrue($columnas->has($columna), $columna);
            $this->assertTrue($columnas[$columna]['nullable'], $columna);
        }

        $migracion = $this->migracion();
        $migracion->up(); // ya migrada: no falla
        $c = $this->contrato();
        $this->suspender($c)->assertOk();

        try {
            $migracion->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertSame('No se puede revertir: hay suspensiones de contratos registradas, que se perderían. Restaure un respaldo.', $e->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('modificaciones_contrato', 'fecha_reinicio'));

        // Con solo adiciones y prórrogas, sí se revierte (y dos veces no falla).
        DB::table('modificaciones_contrato')->delete();
        DB::table('modificaciones_contrato')->insert(['contrato_id' => $c->id, 'tipo' => 'prorroga', 'dias_prorroga' => 5, 'fecha_modificacion' => '2026-11-01']);
        $migracion->down();
        $migracion->down();
        foreach (['fecha_reinicio', 'fecha_reinicio_prevista'] as $columna) {
            $this->assertFalse(Schema::hasColumn('modificaciones_contrato', $columna), $columna);
        }
        $this->assertSame(5, (int) DB::table('modificaciones_contrato')->value('dias_prorroga'));
        $migracion->up();
        $migracion->up();
        $this->assertTrue(Schema::hasColumn('modificaciones_contrato', 'fecha_reinicio_prevista'));
    }
}
```

En `tests/Feature/Contratacion/AvancePonderadoApiTest.php`, reemplazar (1 de 1):

```php

        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$r->json('requisito.id')}", ['cumplido' => true])->assertOk()
            ->assertJsonPath('progreso', $this->progreso(2, 1, 25, 4, 1))
            ->assertJsonPath('avance_por_etapa.1', ['etapa' => $this->etapaJson('adjudicacion'), ...$this->progreso(1, 1, 100, 1, 1), 'fecha_programada' => null, 'fecha_real' => now()->toDateString(), 'fecha_real_sugerida' => true, 'completa' => true, 'alerta' => 'completa', ...$this->sinRevisar('lista', true)]);
    }

    public function test_el_avance_por_etapa_tambien_se_pondera(): void
```

por:

```php

        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$r->json('requisito.id')}", ['cumplido' => true])->assertOk()
            ->assertJsonPath('progreso', $this->progreso(2, 1, 25, 4, 1))
            ->assertJsonPath('avance_por_etapa.1', ['etapa' => $this->etapaJson('adjudicacion'), ...$this->progreso(1, 1, 100, 1, 1), 'fecha_programada' => null, 'fecha_real' => now(config('contratacion.zona'))->toDateString(), 'fecha_real_sugerida' => true, 'completa' => true, 'alerta' => 'completa', ...$this->sinRevisar('lista', true)]);
    }

    public function test_el_avance_por_etapa_tambien_se_pondera(): void
```

En `tests/Feature/Contratacion/ContratosApiTest.php`, reemplazar (1 de 2):

```php
                'fecha_liquidacion' => null,
                'valor_liquidado' => null,
                'aviso_cierre' => null,
                'anticipo' => true,
                'porcentaje_anticipo' => 30,
                'avance_fisico' => 10,
```

por:

```php
                'fecha_liquidacion' => null,
                'valor_liquidado' => null,
                'aviso_cierre' => null,
                // Fase 7B.
                'aviso_suspension' => null,
                'anticipo' => true,
                'porcentaje_anticipo' => 30,
                'avance_fisico' => 10,
```

En `tests/Feature/Contratacion/ContratosApiTest.php`, reemplazar (2 de 2):

```php
        $form = [
            'id' => $contrato->id, 'actualizado' => $this->version($contrato),
            'n_contrato' => 'C-001-2026', 'objeto' => 'Construcción del acueducto veredal',
            'valor_inicial' => '1000000.00', 'estado' => 'suspendido', 'contratista' => 'Consorcio Agua 2',
        ];
        $this->como($gestor)->postJson(self::GUARDAR, ['formContrato' => $form, 'anexos' => []])->assertOk()
            ->assertJsonPath('contrato.supervisor', 'Ing. Pérez');

        $eventos = $this->eventos($contrato);
        $this->assertCount(2, $eventos);
        $this->assertSame(['contrato_editado', ['contratista' => 'Consorcio Agua', 'estado' => 'en_ejecucion'], ['contratista' => 'Consorcio Agua 2', 'estado' => 'suspendido']], $eventos[1]);
        $this->assertSame($gestor->id, (int) DB::table('checklist_historial')->orderByDesc('id')->value('usuario_id'));
    }
```

por:

```php
        $form = [
            'id' => $contrato->id, 'actualizado' => $this->version($contrato),
            'n_contrato' => 'C-001-2026', 'objeto' => 'Construcción del acueducto veredal',
            // Fase 7B: a suspendido ya no se pasa por el formulario (usa «Suspender»): otro estado.
            'valor_inicial' => '1000000.00', 'estado' => 'celebrado', 'contratista' => 'Consorcio Agua 2',
        ];
        $this->como($gestor)->postJson(self::GUARDAR, ['formContrato' => $form, 'anexos' => []])->assertOk()
            ->assertJsonPath('contrato.supervisor', 'Ing. Pérez');

        $eventos = $this->eventos($contrato);
        $this->assertCount(2, $eventos);
        $this->assertSame(['contrato_editado', ['contratista' => 'Consorcio Agua', 'estado' => 'en_ejecucion'], ['contratista' => 'Consorcio Agua 2', 'estado' => 'celebrado']], $eventos[1]);
        $this->assertSame($gestor->id, (int) DB::table('checklist_historial')->orderByDesc('id')->value('usuario_id'));
    }
```

En `tests/Feature/Contratacion/ContratosBusquedaApiTest.php`, reemplazar (1 de 1):

```php
            ['id', 'n_contrato', 'objeto', 'contratista', 'contratante', 'estado', 'valor', 'valor_inicial', 'fecha_inicio', 'fecha_fin',
                // Fase 7A: datos de cierre y su aviso.
                'fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado',
                'avance_fisico', 'avance_financiero', 'tipo_contrato', 'proyecto', 'proceso', 'aviso_proceso', 'aviso_cierre', 'secop_id_contrato', 'updated_at'],
            array_keys($fila),
        );
        $this->assertSame($c->id, $fila['id']);
```

por:

```php
            ['id', 'n_contrato', 'objeto', 'contratista', 'contratante', 'estado', 'valor', 'valor_inicial', 'fecha_inicio', 'fecha_fin',
                // Fase 7A: datos de cierre y su aviso.
                'fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado',
                'avance_fisico', 'avance_financiero', 'tipo_contrato', 'proyecto', 'proceso', 'aviso_proceso', 'aviso_cierre',
                // Fase 7B: aviso de suspensión.
                'aviso_suspension', 'secop_id_contrato', 'updated_at'],
            array_keys($fila),
        );
        $this->assertSame($c->id, $fila['id']);
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (1 de 1):

```php
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'))
            ->assertJsonPath('requisito.etapa', $this->etapaJson('planeacion'))
            ->assertJsonPath('progreso', ['total' => 2, 'cumplidos' => 1, 'porcentaje' => 50, 'peso_total' => 2, 'peso_cumplido' => 1])
            ->assertJsonPath('avance_por_etapa.0', ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 1, 'porcentaje' => 100, 'peso_total' => 1, 'peso_cumplido' => 1, 'fecha_programada' => null, 'fecha_real' => now()->toDateString(), 'fecha_real_sugerida' => true, 'completa' => true, 'alerta' => 'completa', ...$this->sinRevisar('lista')]);
    }

    public function test_la_api_de_viabilizacion_no_trae_las_claves_de_contratacion(): void
```

por:

```php
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'))
            ->assertJsonPath('requisito.etapa', $this->etapaJson('planeacion'))
            ->assertJsonPath('progreso', ['total' => 2, 'cumplidos' => 1, 'porcentaje' => 50, 'peso_total' => 2, 'peso_cumplido' => 1])
            ->assertJsonPath('avance_por_etapa.0', ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 1, 'porcentaje' => 100, 'peso_total' => 1, 'peso_cumplido' => 1, 'fecha_programada' => null, 'fecha_real' => now(config('contratacion.zona'))->toDateString(), 'fecha_real_sugerida' => true, 'completa' => true, 'alerta' => 'completa', ...$this->sinRevisar('lista')]);
    }

    public function test_la_api_de_viabilizacion_no_trae_las_claves_de_contratacion(): void
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/SuspensionContratosApiTest.php tests/Feature/Contratacion/AvancePonderadoApiTest.php tests/Feature/Contratacion/ContratosApiTest.php tests/Feature/Contratacion/ContratosBusquedaApiTest.php tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`
Expected: FALLAN 46 y pasan 134. En `SuspensionContratosApiTest` fallan 44 y pasa 1: las rutas `POST /contratos/{contrato}/suspender` y `/reiniciar` aún no existen y la ruta comodín del SPA, que solo atiende GET, responde 405 ("Failed asserting that 405 is identical to 200.", "... to 401.", "... to 404.", "... to 422."); las de la pestaña Modificaciones, la regla de editar, la 7A, los listados y la migración fallan porque las columnas, los bloqueos y `aviso_suspension` aún no existen. En `ContratosApiTest` y `ContratosBusquedaApiTest` falla 1 en cada una, la que compara la forma exacta de la respuesta, porque falta `aviso_suspension`. `AvancePonderadoApiTest` y `ExpedienteContratacionApiTest` pasan (su cambio solo compara la fecha en la zona de contratación).

- [ ] **Paso 3: Implementar**

Crear `app/Services/Contratacion/SuspensionContratos.php`:

```php
<?php

namespace App\Services\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\Contrato;
use App\Models\ModificacionContrato;
use App\Models\User;
use App\Services\Viabilizacion\HistorialChecklist;
use DateTimeInterface;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Suspensión y reinicio de un contrato (fase 7B), cada uno con su acción.
 *
 * Reglas:
 * - Una suspensión es una fila de modificaciones_contrato con tipo = 'suspension':
 *   `fecha_modificacion` es la fecha de suspensión, `numero_otrosi` el número del acta,
 *   `justificacion` el motivo y `valor_adicion` null. Está abierta mientras no tiene
 *   `fecha_reinicio`; entonces `dias_prorroga` es null. Al reiniciar, `dias_prorroga` son los días
 *   calendario entre la suspensión y el reinicio: ContratosService::recalcular() corre la fecha fin
 *   igual que con una prórroga. Mientras está suspendido, la fecha fin no cambia.
 * - Suspender: solo un contrato en ejecución y sin otra suspensión abierta. La fecha de suspensión
 *   no es posterior a hoy (en config('contratacion.zona')), ni anterior a la de inicio, si la hay,
 *   ni al reinicio de la última suspensión cerrada. La fecha prevista de reinicio es opcional y no
 *   es anterior a la de suspensión. El contrato pasa a suspendido.
 * - Reiniciar: solo un contrato suspendido. Cierra la suspensión abierta; si no tiene (un contrato
 *   que llegó suspendido, p. ej. de SECOP II), pide la fecha de suspensión (con las reglas de
 *   suspender) y crea la suspensión ya cerrada. La fecha de reinicio no es posterior a hoy ni
 *   anterior a la de suspensión (el mismo día suma 0 días). El contrato vuelve a en ejecución y se
 *   recalcula.
 * - Solo cambian el estado y, al reiniciar, la fecha fin vigente: el valor, los iniciales y los
 *   avances físico y financiero no se tocan (R13).
 * - Traen `actualizado`, como editar: si ya no es el de la fila, se rechaza (409). Cada cambio
 *   adelanta updated_at del contrato (la tabla de modificaciones no tiene fechas) y queda en
 *   checklist_historial (entidad contrato): `contrato_suspendido` y `contrato_reiniciado`, con
 *   antes y después (R15).
 * - Eliminar una suspensión (pestaña Modificaciones) también trae `actualizado` (409 si es viejo,
 *   antes que las demás reglas; 404 si la fila ya no existe) y solo la más reciente del contrato. Si estaba
 *   abierta y el contrato suspendido, vuelve a en ejecución. Recalcula, adelanta updated_at y deja
 *   `suspension_eliminada` en el historial con los datos de la fila.
 * - aviso() dice desde cuándo está suspendido un contrato y si su reinicio previsto ya pasó, sin
 *   consultas; avisosDe() lo calcula para muchos contratos con una sola consulta.
 */
final class SuspensionContratos
{
    use ControlDeVersion;

    public const NO_EN_EJECUCION = 'Solo se suspende un contrato en ejecución.';

    public const YA_ABIERTA = 'El contrato ya tiene una suspensión abierta.';

    public const NO_SUSPENDIDO = 'El contrato no está suspendido.';

    public const SIN_SUSPENSION = 'Escribe la fecha de suspensión.';

    public const SUSPENSION_FUTURA = 'La fecha de suspensión no puede ser posterior a hoy.';

    public const SUSPENSION_ANTES_DE_INICIO = 'La fecha de suspensión no puede ser anterior a la de inicio.';

    public const SUSPENSION_ANTES_DE_REINICIO = 'La fecha de suspensión no puede ser anterior al último reinicio.';

    public const PREVISTA_ANTERIOR = 'La fecha prevista de reinicio no puede ser anterior a la de suspensión.';

    public const REINICIO_FUTURO = 'La fecha de reinicio no puede ser posterior a hoy.';

    public const REINICIO_ANTERIOR = 'La fecha de reinicio no puede ser anterior a la de suspensión.';

    public const SOLO_LA_ULTIMA = 'Solo se puede eliminar la última suspensión.';

    public const YA_NO_EXISTE = 'La suspensión ya no existe.';

    public const POR_FORMULARIO = 'Las suspensiones se registran con «Suspender».';

    public function __construct(
        private readonly HistorialChecklist $historial,
        private readonly AvisosContratacion $avisos,
        private readonly ContratosService $contratos,
    ) {
    }

    /**
     * @param string $fecha año-mes-día
     * @param ?string $prevista año-mes-día
     */
    public function suspender(Contrato $contrato, string $fecha, ?string $prevista, ?string $acta, ?string $motivo, ?string $actualizado, ?User $usuario): SuspensionGuardada
    {
        $acta = $this->texto($acta);
        $motivo = $this->texto($motivo);

        return DB::transaction(function () use ($contrato, $fecha, $prevista, $acta, $motivo, $actualizado, $usuario) {
            // Primero la versión: con un formulario viejo, el estado que se ve engañaría.
            $actual = $this->bloquear($contrato);
            $this->exigirVersion($actual, $actualizado, ContratosService::DESACTUALIZADO);
            if (self::abierta($actual->id) !== null) {
                throw new ReglaNegocioException(self::YA_ABIERTA);
            }
            if ($actual->estado !== Contrato::EN_EJECUCION) {
                throw new ReglaNegocioException(self::NO_EN_EJECUCION);
            }
            $this->exigirSuspension($actual, $fecha);
            if ($prevista !== null && $prevista < $fecha) {
                throw ValidationException::withMessages(['fecha_reinicio_prevista' => self::PREVISTA_ANTERIOR]);
            }

            $id = DB::table('modificaciones_contrato')->insertGetId([
                'contrato_id' => $actual->id,
                'numero_otrosi' => $acta,
                'tipo' => ModificacionContrato::SUSPENSION,
                'valor_adicion' => null,
                'dias_prorroga' => null,
                'fecha_modificacion' => $fecha,
                'fecha_reinicio' => null,
                'fecha_reinicio_prevista' => $prevista,
                'justificacion' => $motivo,
            ]);
            $antes = ['estado' => $actual->estado];
            $this->adelantarActualizado($actual);
            $actual->fill(['estado' => Contrato::SUSPENDIDO])->save();
            $this->historial->registrarContrato($actual, 'contrato_suspendido', $antes, [
                'estado' => Contrato::SUSPENDIDO,
                'fecha_suspension' => $fecha,
                'fecha_reinicio_prevista' => $prevista,
                'numero_acta' => $acta,
                'motivo' => $motivo,
                'modificacion_id' => $id,
            ], $usuario);

            return new SuspensionGuardada($actual->refresh(), []);
        });
    }

    /**
     * @param string $reinicio año-mes-día
     * @param ?string $suspension año-mes-día; solo cuenta si el contrato no tiene suspensión abierta
     */
    public function reiniciar(Contrato $contrato, string $reinicio, ?string $suspension, ?string $observacion, ?string $actualizado, ?User $usuario): SuspensionGuardada
    {
        $observacion = $this->texto($observacion);

        return DB::transaction(function () use ($contrato, $reinicio, $suspension, $observacion, $actualizado, $usuario) {
            $actual = $this->bloquear($contrato);
            $this->exigirVersion($actual, $actualizado, ContratosService::DESACTUALIZADO);
            if ($actual->estado !== Contrato::SUSPENDIDO) {
                throw new ReglaNegocioException(self::NO_SUSPENDIDO);
            }
            $abierta = self::abierta($actual->id);
            if ($abierta !== null) {
                $suspension = $this->dia($abierta->fecha_modificacion);
            } else {
                if ($suspension === null) {
                    throw ValidationException::withMessages(['fecha_suspension' => self::SIN_SUSPENSION]);
                }
                $this->exigirSuspension($actual, $suspension);
            }
            if ($reinicio > $this->hoy()) {
                throw ValidationException::withMessages(['fecha_reinicio' => self::REINICIO_FUTURO]);
            }
            if ($reinicio < $suspension) {
                throw ValidationException::withMessages(['fecha_reinicio' => self::REINICIO_ANTERIOR]);
            }
            $dias = self::dias($suspension, $reinicio);

            if ($abierta !== null) {
                $id = (int) $abierta->id;
                DB::table('modificaciones_contrato')->where('id', $id)->update(['fecha_reinicio' => $reinicio, 'dias_prorroga' => $dias]);
            } else {
                $id = DB::table('modificaciones_contrato')->insertGetId([
                    'contrato_id' => $actual->id,
                    'numero_otrosi' => null,
                    'tipo' => ModificacionContrato::SUSPENSION,
                    'valor_adicion' => null,
                    'dias_prorroga' => $dias,
                    'fecha_modificacion' => $suspension,
                    'fecha_reinicio' => $reinicio,
                    'fecha_reinicio_prevista' => null,
                    'justificacion' => null,
                ]);
            }
            $antes = ['estado' => $actual->estado, 'fecha_fin' => $this->fecha($actual->fecha_fin)];
            $this->adelantarActualizado($actual);
            $actual->fill(['estado' => Contrato::EN_EJECUCION])->save();
            $resumen = $this->contratos->recalcular($actual);
            $actual->refresh();
            $this->historial->registrarContrato($actual, 'contrato_reiniciado', $antes, [
                'estado' => Contrato::EN_EJECUCION,
                'fecha_fin' => $this->fecha($actual->fecha_fin),
                'fecha_suspension' => $suspension,
                'fecha_reinicio' => $reinicio,
                'dias_suspendido' => $dias,
                'observacion' => $observacion,
                'modificacion_id' => $id,
            ], $usuario);

            return new SuspensionGuardada($actual, [], $resumen);
        });
    }

    /**
     * Elimina una suspensión desde la pestaña Modificaciones: solo la más reciente del contrato.
     * Devuelve el resumen de ContratosService::recalcular().
     *
     * @return array{valor_inicial: float, valor_vigente: float, fecha_fin_inicial: ?string, fecha_fin_vigente: ?string, porcentaje_adicionado: float, supera_limite: bool}
     */
    public function eliminar(int $modificacionId, int $contratoId, ?string $actualizado, ?User $usuario): array
    {
        return DB::transaction(function () use ($modificacionId, $contratoId, $actualizado, $usuario) {
            // Primero la versión, con el contrato bloqueado: con una lista vieja se desharía en
            // silencio un reinicio u otra suspensión que alguien guardó entretanto.
            $actual = Contrato::query()->whereKey($contratoId)->lockForUpdate()->firstOrFail();
            $this->exigirVersion($actual, $actualizado, ContratosService::DESACTUALIZADO);
            $fila = DB::table('modificaciones_contrato')
                ->where('id', $modificacionId)
                ->where('contrato_id', $actual->id)
                ->where('tipo', ModificacionContrato::SUSPENSION)
                ->first();
            if ($fila === null) {
                throw new HttpResponseException(response()->json(['mensaje' => self::YA_NO_EXISTE], 404));
            }
            $ultima = DB::table('modificaciones_contrato')
                ->where('contrato_id', $actual->id)
                ->where('tipo', ModificacionContrato::SUSPENSION)
                ->orderByDesc('fecha_modificacion')
                ->orderByDesc('id')
                ->first();
            if ($ultima === null || (int) $ultima->id !== (int) $fila->id) {
                throw new ReglaNegocioException(self::SOLO_LA_ULTIMA);
            }

            $abierta = $fila->fecha_reinicio === null;
            $antes = [
                'modificacion_id' => (int) $fila->id,
                'fecha_suspension' => $this->dia($fila->fecha_modificacion),
                'fecha_reinicio' => $this->dia($fila->fecha_reinicio),
                'fecha_reinicio_prevista' => $this->dia($fila->fecha_reinicio_prevista),
                'dias_suspendido' => $fila->dias_prorroga === null ? null : (int) $fila->dias_prorroga,
                'numero_acta' => $fila->numero_otrosi,
                'motivo' => $fila->justificacion,
                'estado' => $actual->estado,
                'fecha_fin' => $this->fecha($actual->fecha_fin),
            ];
            DB::table('modificaciones_contrato')->where('id', $fila->id)->delete();
            $this->adelantarActualizado($actual);
            if ($abierta && $actual->estado === Contrato::SUSPENDIDO) {
                $actual->estado = Contrato::EN_EJECUCION;
            }
            $actual->save();
            $resumen = $this->contratos->recalcular($actual);
            $actual->refresh();
            $this->historial->registrarContrato($actual, 'suspension_eliminada', $antes, [
                'estado' => $actual->estado,
                'fecha_fin' => $this->fecha($actual->fecha_fin),
            ], $usuario);

            return $resumen;
        });
    }

    /** La suspensión abierta del contrato (la más reciente, si por error hubiera más), o null. */
    public static function abierta(int $contratoId): ?object
    {
        return DB::table('modificaciones_contrato')
            ->where('contrato_id', $contratoId)
            ->where('tipo', ModificacionContrato::SUSPENSION)
            ->whereNull('fecha_reinicio')
            ->orderByDesc('fecha_modificacion')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Las suspensiones abiertas de los contratos suspendidos, en una sola consulta.
     *
     * @param array<int, ?string> $estados contrato_id => estado
     * @return array<int, object> contrato_id => suspensión abierta (la más reciente)
     */
    public static function abiertasDe(array $estados): array
    {
        $ids = array_keys(array_filter($estados, fn (?string $estado) => $estado === Contrato::SUSPENDIDO));
        if ($ids === []) {
            return [];
        }
        $abiertas = [];
        DB::table('modificaciones_contrato')
            ->whereIn('contrato_id', $ids)
            ->where('tipo', ModificacionContrato::SUSPENSION)
            ->whereNull('fecha_reinicio')
            ->orderBy('fecha_modificacion')
            ->orderBy('id')
            ->get()
            ->each(function ($fila) use (&$abiertas) {
                $abiertas[(int) $fila->contrato_id] = $fila;
            });

        return $abiertas;
    }

    /**
     * Desde cuándo está suspendido un contrato y si su reinicio previsto ya pasó: null si no está
     * suspendido o no tiene suspensión abierta.
     *
     * @param ?object $abierta la fila de su suspensión abierta
     * @param string $hoy año-mes-día
     * @return ?array{desde: string, dias: int, reinicio_previsto: ?string, vencido: bool}
     */
    public static function aviso(?string $estado, ?object $abierta, string $hoy): ?array
    {
        if ($estado !== Contrato::SUSPENDIDO || $abierta === null) {
            return null;
        }
        $desde = substr((string) $abierta->fecha_modificacion, 0, 10);
        $prevista = $abierta->fecha_reinicio_prevista !== null ? substr((string) $abierta->fecha_reinicio_prevista, 0, 10) : null;

        return [
            'desde' => $desde,
            'dias' => self::dias($desde, $hoy),
            'reinicio_previsto' => $prevista,
            'vencido' => $prevista !== null && $prevista < $hoy,
        ];
    }

    /** Días calendario entre dos fechas año-mes-día (negativo si la segunda es anterior). */
    private static function dias(string $desde, string $hasta): int
    {
        return (int) round(Carbon::parse($desde, 'UTC')->diffInDays(Carbon::parse($hasta, 'UTC'), false));
    }

    /**
     * La fecha de suspensión no es posterior a hoy, ni anterior a la de inicio, si la hay, ni al
     * reinicio de la última suspensión cerrada.
     */
    private function exigirSuspension(Contrato $contrato, string $fecha): void
    {
        if ($fecha > $this->hoy()) {
            throw ValidationException::withMessages(['fecha_suspension' => self::SUSPENSION_FUTURA]);
        }
        $inicio = $this->fecha($contrato->fecha_inicio);
        if ($inicio !== null && $fecha < $inicio) {
            throw ValidationException::withMessages(['fecha_suspension' => self::SUSPENSION_ANTES_DE_INICIO]);
        }
        $ultimoReinicio = $this->dia(DB::table('modificaciones_contrato')
            ->where('contrato_id', $contrato->id)
            ->where('tipo', ModificacionContrato::SUSPENSION)
            ->max('fecha_reinicio'));
        if ($ultimoReinicio !== null && $fecha < $ultimoReinicio) {
            throw ValidationException::withMessages(['fecha_suspension' => self::SUSPENSION_ANTES_DE_REINICIO]);
        }
    }

    private function hoy(): string
    {
        return $this->avisos->hoy()->toDateString();
    }

    private function bloquear(Contrato $contrato): Contrato
    {
        return Contrato::query()->whereKey($contrato->id)->lockForUpdate()->firstOrFail();
    }

    private function fecha(?DateTimeInterface $valor): ?string
    {
        return $valor?->format('Y-m-d');
    }

    /** Una fecha leída con el constructor de consultas (año-mes-día, con o sin hora) como año-mes-día. */
    private function dia(mixed $valor): ?string
    {
        return $valor === null ? null : substr((string) $valor, 0, 10);
    }

    private function texto(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : null;
    }
}
```

Crear `app/Services/Contratacion/SuspensionGuardada.php`:

```php
<?php

namespace App\Services\Contratacion;

use App\Models\Contrato;

/**
 * Lo que deja suspender o reiniciar un contrato: el contrato como quedó, los avisos que no impiden
 * guardar y, al reiniciar, el resumen de ContratosService::recalcular().
 */
final class SuspensionGuardada
{
    /**
     * @param list<string> $avisos
     * @param ?array{valor_inicial: float, valor_vigente: float, fecha_fin_inicial: ?string, fecha_fin_vigente: ?string, porcentaje_adicionado: float, supera_limite: bool} $resumen
     */
    public function __construct(
        public readonly Contrato $contrato,
        public readonly array $avisos,
        public readonly ?array $resumen = null,
    ) {
    }
}
```

Crear `database/migrations/2026_11_19_100024_add_suspension_a_modificaciones_contrato.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Suspensión y reinicio del contrato (fase 7B): una suspensión es una fila de
 * modificaciones_contrato con tipo = 'suspension'. Se agregan la fecha de reinicio (null mientras
 * la suspensión está abierta) y la fecha prevista de reinicio (opcional). Ambas admiten nulo: las
 * adiciones y prórrogas que ya existen quedan sin ellas.
 */
return new class extends Migration
{
    private const TABLA = 'modificaciones_contrato';

    private const COLUMNAS = ['fecha_reinicio', 'fecha_reinicio_prevista'];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada columna lleva su guarda, para poder
        // reintentar sin dejar nada a medias.
        $columnas = [
            'fecha_reinicio' => fn (Blueprint $t) => $t->date('fecha_reinicio')->nullable()->after('fecha_modificacion'),
            'fecha_reinicio_prevista' => fn (Blueprint $t) => $t->date('fecha_reinicio_prevista')->nullable()->after('fecha_reinicio'),
        ];
        foreach ($columnas as $columna => $definir) {
            if (! Schema::hasColumn(self::TABLA, $columna)) {
                Schema::table(self::TABLA, $definir);
            }
        }
    }

    public function down(): void
    {
        if (DB::table(self::TABLA)->where('tipo', 'suspension')->exists()) {
            throw new RuntimeException('No se puede revertir: hay suspensiones de contratos registradas, que se perderían. Restaure un respaldo.');
        }

        // Cada columna con su guarda.
        foreach (array_reverse(self::COLUMNAS) as $columna) {
            if (Schema::hasColumn(self::TABLA, $columna)) {
                Schema::table(self::TABLA, function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }
    }
};
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (1 de 8):

```php
use App\Services\Contratacion\CierreContratos;
use App\Services\Contratacion\CierreGuardado;
use App\Services\Contratacion\ContratosService;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
```

por:

```php
use App\Services\Contratacion\CierreContratos;
use App\Services\Contratacion\CierreGuardado;
use App\Services\Contratacion\ContratosService;
use App\Services\Contratacion\SuspensionContratos;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (2 de 8):

```php
use Illuminate\Validation\ValidationException;

/**
 * Guardar, eliminar, terminar y liquidar contratos (can:editar-datos). Lectura: buscar y detalle (cualquier usuario
 * autenticado); los listados por proyecto siguen en ProyectoController (GET /proyectos y
 * GET /listarContratos).
 */
```

por:

```php
use Illuminate\Validation\ValidationException;

/**
 * Guardar, eliminar, terminar, liquidar, suspender y reiniciar contratos (can:editar-datos). Lectura: buscar y detalle (cualquier usuario
 * autenticado); los listados por proyecto siguen en ProyectoController (GET /proyectos y
 * GET /listarContratos).
 */
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (3 de 8):

```php
        'secop_proceso.max' => 'El proceso de SECOP II admite hasta 40 caracteres.',
        'url_secop.regex' => 'El enlace de SECOP II debe empezar por http:// o https://.',
        'url_secop.max' => 'El enlace de SECOP II admite hasta 500 caracteres.',
        'actualizado.present' => 'Falta la versión del contrato que estabas editando. Ciérralo y vuelve a abrirlo.',
        'actualizado.date' => 'La versión del contrato no es válida. Ciérralo y vuelve a abrirlo.',
        'anexos.*.descripcion.required' => 'Escribe la descripción del anexo.',
        'anexos.*.descripcion.max' => 'La descripción del anexo admite hasta 255 caracteres.',
        'anexos.*.nombreArchivo.required' => 'Falta el nombre del archivo del anexo: vuelve a subirlo.',
```

por:

```php
        'secop_proceso.max' => 'El proceso de SECOP II admite hasta 40 caracteres.',
        'url_secop.regex' => 'El enlace de SECOP II debe empezar por http:// o https://.',
        'url_secop.max' => 'El enlace de SECOP II admite hasta 500 caracteres.',
        ...self::MENSAJES_VERSION,
        'anexos.*.descripcion.required' => 'Escribe la descripción del anexo.',
        'anexos.*.descripcion.max' => 'La descripción del anexo admite hasta 255 caracteres.',
        'anexos.*.nombreArchivo.required' => 'Falta el nombre del archivo del anexo: vuelve a subirlo.',
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (4 de 8):

```php
        'valor_liquidado.min' => 'El valor liquidado no puede ser negativo.',
        'valor_liquidado.decimal' => 'El valor liquidado admite hasta 2 decimales.',
        'observacion.max' => 'La observación admite hasta 2000 caracteres.',
    ];

    private const ATRIBUTOS = [
```

por:

```php
        'valor_liquidado.min' => 'El valor liquidado no puede ser negativo.',
        'valor_liquidado.decimal' => 'El valor liquidado admite hasta 2 decimales.',
        'observacion.max' => 'La observación admite hasta 2000 caracteres.',
        'fecha_suspension.required' => 'Escribe la fecha de suspensión.',
        'fecha_reinicio.required' => 'Escribe la fecha de reinicio.',
        'numero_acta.max' => 'El número del acta admite hasta 50 caracteres.',
        'motivo.max' => 'El motivo admite hasta 2000 caracteres.',
    ];

    private const ATRIBUTOS = [
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (5 de 8):

```php
        'fecha_liquidacion' => 'la fecha de liquidación',
        'valor_liquidado' => 'el valor liquidado',
        'observacion' => 'la observación',
    ];

    // Las columnas de valor son decimal(15, 2): 13 cifras enteras y 2 decimales.
```

por:

```php
        'fecha_liquidacion' => 'la fecha de liquidación',
        'valor_liquidado' => 'el valor liquidado',
        'observacion' => 'la observación',
        'fecha_suspension' => 'la fecha de suspensión',
        'fecha_reinicio_prevista' => 'la fecha prevista de reinicio',
        'fecha_reinicio' => 'la fecha de reinicio',
        'numero_acta' => 'el número del acta',
        'motivo' => 'el motivo',
    ];

    // Las columnas de valor son decimal(15, 2): 13 cifras enteras y 2 decimales.
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (6 de 8):

```php

    // El `actualizado` del contrato que tenía la pantalla: siempre presente; null solo para un
    // contrato antiguo, sin fechas. Con otro valor, el servicio responde 409.
    private const VERSION = ['present', 'nullable', 'bail', 'string', 'date'];

    // El ID de un contrato en SECOP II (sin distinguir mayúsculas) y su enlace.
    private const FORMATO_SECOP = '/^CO1\.PCCNTR\.\d+$/i';
```

por:

```php

    // El `actualizado` del contrato que tenía la pantalla: siempre presente; null solo para un
    // contrato antiguo, sin fechas. Con otro valor, el servicio responde 409.
    // También la usa eliminarModificacionContrato al eliminar una suspensión.
    public const VERSION = ['present', 'nullable', 'bail', 'string', 'date'];

    public const MENSAJES_VERSION = [
        'actualizado.present' => 'Falta la versión del contrato que estabas editando. Ciérralo y vuelve a abrirlo.',
        'actualizado.date' => 'La versión del contrato no es válida. Ciérralo y vuelve a abrirlo.',
    ];

    // El ID de un contrato en SECOP II (sin distinguir mayúsculas) y su enlace.
    private const FORMATO_SECOP = '/^CO1\.PCCNTR\.\d+$/i';
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (7 de 8):

```php
    public function __construct(
        private readonly ContratosService $contratos,
        private readonly CierreContratos $cierre,
    ) {
    }
```

por:

```php
    public function __construct(
        private readonly ContratosService $contratos,
        private readonly CierreContratos $cierre,
        private readonly SuspensionContratos $suspension,
    ) {
    }
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (8 de 8):

```php
        ));
    }

    private function cerrado(CierreGuardado $guardado): JsonResponse
    {
        return response()->json([
```

por:

```php
        ));
    }

    /**
     * Suspende un contrato en ejecución: `fecha_suspension`, `fecha_reinicio_prevista`,
     * `numero_acta` y `motivo` opcionales y `actualizado`. Responde el contrato y los avisos.
     */
    public function suspender(Request $request, string $contrato): JsonResponse
    {
        $datos = $request->validate([
            'fecha_suspension' => ['bail', 'required', 'string', 'date_format:Y-m-d'],
            'fecha_reinicio_prevista' => ['sometimes', 'nullable', 'bail', 'string', 'date_format:Y-m-d'],
            'numero_acta' => ['sometimes', 'nullable', 'bail', 'string', 'max:50'],
            'motivo' => ['sometimes', 'nullable', 'bail', 'string', 'max:2000'],
            'actualizado' => self::VERSION,
        ], self::MENSAJES, self::ATRIBUTOS);
        $fila = Contrato::find($contrato);
        if ($fila === null) {
            return response()->json(['mensaje' => 'El contrato no existe.'], 404);
        }

        $guardado = $this->suspension->suspender(
            $fila,
            $datos['fecha_suspension'],
            $datos['fecha_reinicio_prevista'] ?? null,
            $datos['numero_acta'] ?? null,
            $datos['motivo'] ?? null,
            $datos['actualizado'],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'contrato' => $this->contratos->presentar($guardado->contrato),
            'avisos' => $guardado->avisos,
        ]);
    }

    /**
     * Reinicia un contrato suspendido: `fecha_reinicio`, `fecha_suspension` (solo si no tiene una
     * suspensión abierta), `observacion` opcional y `actualizado`. Responde el contrato, los avisos
     * y el resumen del recálculo.
     */
    public function reiniciar(Request $request, string $contrato): JsonResponse
    {
        $datos = $request->validate([
            'fecha_reinicio' => ['bail', 'required', 'string', 'date_format:Y-m-d'],
            'fecha_suspension' => ['sometimes', 'nullable', 'bail', 'string', 'date_format:Y-m-d'],
            'observacion' => ['sometimes', 'nullable', 'bail', 'string', 'max:2000'],
            'actualizado' => self::VERSION,
        ], self::MENSAJES, self::ATRIBUTOS);
        $fila = Contrato::find($contrato);
        if ($fila === null) {
            return response()->json(['mensaje' => 'El contrato no existe.'], 404);
        }

        $guardado = $this->suspension->reiniciar(
            $fila,
            $datos['fecha_reinicio'],
            $datos['fecha_suspension'] ?? null,
            $datos['observacion'] ?? null,
            $datos['actualizado'],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'contrato' => $this->contratos->presentar($guardado->contrato),
            'avisos' => $guardado->avisos,
            'resumen' => $guardado->resumen,
        ]);
    }

    private function cerrado(CierreGuardado $guardado): JsonResponse
    {
        return response()->json([
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (1 de 6):

```php
namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Proyecto;
use App\Services\Contratacion\ContratosService;
use App\Services\Contratacion\ExpedientesContratacion;
use App\Services\Contratacion\ProcesosContratacion;
use App\Services\Financiacion\FuentesProyecto;
use App\Services\Viabilizacion\ExpedienteService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProyectoController extends Controller
{
```

por:

```php
namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\ModificacionContrato;
use App\Models\Proyecto;
use App\Services\Contratacion\ContratosService;
use App\Services\Contratacion\ExpedientesContratacion;
use App\Services\Contratacion\ProcesosContratacion;
use App\Services\Contratacion\SuspensionContratos;
use App\Services\Financiacion\FuentesProyecto;
use App\Services\Viabilizacion\ExpedienteService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProyectoController extends Controller
{
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (2 de 6):

```php
        app(ContratosService::class)->agregarProcesos($todos);
        // Los datos de cierre que faltan, como en el detalle y en /listarContratos (sin consultas).
        app(ContratosService::class)->agregarAvisoCierre($todos);

        return response()->json($proyectos);
    }
```

por:

```php
        app(ContratosService::class)->agregarProcesos($todos);
        // Los datos de cierre que faltan, como en el detalle y en /listarContratos (sin consultas).
        app(ContratosService::class)->agregarAvisoCierre($todos);
        // Fase 7B: desde cuándo está suspendido y si su reinicio previsto venció (una consulta).
        app(ContratosService::class)->agregarAvisoSuspension($todos);

        return response()->json($proyectos);
    }
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (3 de 6):

```php
    /**
     * Registra una modificación (otrosí) y recalcula el valor y la fecha fin
     * vigentes del contrato desde su base inmutable + historial completo.
     */
    public function guardarModificacionContrato(Request $request)
    {
        $data = $request->all();
        $contratoId = $data['contrato_id'];

        $nullableDecimal = fn ($v) => ($v === '' || $v === null) ? null : $v;
        $nullableInt = fn ($v) => ($v === '' || $v === null) ? null : (int) $v;

        DB::beginTransaction();
        try {
            DB::table('modificaciones_contrato')->insert([
                'contrato_id' => $contratoId,
                'numero_otrosi' => $data['numero_otrosi'] ?? null,
```

por:

```php
    /**
     * Registra una modificación (otrosí) y recalcula el valor y la fecha fin
     * vigentes del contrato desde su base inmutable + historial completo.
     * Las suspensiones no se registran aquí (fase 7B): las registra «Suspender».
     */
    public function guardarModificacionContrato(Request $request)
    {
        $data = $request->all();
        $contratoId = $data['contrato_id'];

        if (($data['tipo'] ?? null) === ModificacionContrato::SUSPENSION) {
            throw ValidationException::withMessages(['tipo' => SuspensionContratos::POR_FORMULARIO]);
        }

        $nullableDecimal = fn ($v) => ($v === '' || $v === null) ? null : $v;
        $nullableInt = fn ($v) => ($v === '' || $v === null) ? null : (int) $v;

        DB::beginTransaction();
        try {
            // Con el contrato bloqueado, un reinicio que corre a la vez espera (o termina antes) y
            // el recálculo de aquí no pisa la fecha fin que dejó.
            Contrato::query()->whereKey($contratoId)->lockForUpdate()->firstOrFail();
            DB::table('modificaciones_contrato')->insert([
                'contrato_id' => $contratoId,
                'numero_otrosi' => $data['numero_otrosi'] ?? null,
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (4 de 6):

```php
    }

    /**
     * Elimina una modificación (otrosí) y recalcula el contrato afectado.
     */
    public function eliminarModificacionContrato(Request $request)
    {
        $id = $request->input('id');

        DB::beginTransaction();
        try {
            $mod = DB::table('modificaciones_contrato')->where('id', $id)->first();
```

por:

```php
    }

    /**
     * Elimina una modificación (otrosí) y recalcula el contrato afectado. Una suspensión la
     * elimina SuspensionContratos (con `actualizado`, solo la última, y devuelve el estado si
     * estaba abierta).
     */
    public function eliminarModificacionContrato(Request $request, SuspensionContratos $suspensiones)
    {
        $id = $request->input('id');

        $suspension = DB::table('modificaciones_contrato')->where('id', $id)->where('tipo', ModificacionContrato::SUSPENSION)->first();
        if ($suspension !== null) {
            // Una suspensión cambia el estado y la fecha fin: trae la versión del contrato (409).
            $datos = $request->validate(['actualizado' => ContratoController::VERSION], ContratoController::MENSAJES_VERSION);
            $resumen = $suspensiones->eliminar((int) $suspension->id, (int) $suspension->contrato_id, $datos['actualizado'], $request->user());

            return response()->json([
                'success' => 'Modificación eliminada correctamente',
                'resumen' => $resumen,
            ]);
        }

        DB::beginTransaction();
        try {
            $mod = DB::table('modificaciones_contrato')->where('id', $id)->first();
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (5 de 6):

```php
            }

            $contratoId = $mod->contrato_id;
            DB::table('modificaciones_contrato')->where('id', $id)->delete();

            $resumen = $this->recalcularContrato($contratoId);
```

por:

```php
            }

            $contratoId = $mod->contrato_id;
            // Como al registrar: el contrato bloqueado antes de borrar y recalcular.
            Contrato::query()->whereKey($contratoId)->lockForUpdate()->firstOrFail();
            DB::table('modificaciones_contrato')->where('id', $id)->delete();

            $resumen = $this->recalcularContrato($contratoId);
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (6 de 6):

```php
        // Con lo que avisa el proceso de cada contrato y los datos de cierre que faltan, como en el detalle.
        app(ContratosService::class)->agregarProcesos($contratos, true);
        app(ContratosService::class)->agregarAvisoCierre($contratos);
        app(ContratosService::class)->agregarDetalle($contratos);

        return response()->json($contratos);
```

por:

```php
        // Con lo que avisa el proceso de cada contrato y los datos de cierre que faltan, como en el detalle.
        app(ContratosService::class)->agregarProcesos($contratos, true);
        app(ContratosService::class)->agregarAvisoCierre($contratos);
        app(ContratosService::class)->agregarAvisoSuspension($contratos);
        app(ContratosService::class)->agregarDetalle($contratos);

        return response()->json($contratos);
```

En `app/Models/ModificacionContrato.php`, reemplazar (1 de 2):

```php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModificacionContrato extends Model
{
    public $timestamps = false;

    protected $table = 'modificaciones_contrato';
```

por:

```php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModificacionContrato extends Model
{
    /** Tipo de las suspensiones (fase 7B): las registra «Suspender», no el formulario de modificaciones. */
    public const SUSPENSION = 'suspension';

    public $timestamps = false;

    protected $table = 'modificaciones_contrato';
```

En `app/Models/ModificacionContrato.php`, reemplazar (2 de 2):

```php
    protected $fillable = [
        'contrato_id', 'numero_otrosi', 'tipo', 'valor_adicion',
        'dias_prorroga', 'fecha_modificacion', 'justificacion',
    ];

    protected $casts = [
        'valor_adicion' => 'decimal:2',
        'dias_prorroga' => 'integer',
        'fecha_modificacion' => 'date',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }
}
```

por:

```php
    protected $fillable = [
        'contrato_id', 'numero_otrosi', 'tipo', 'valor_adicion',
        'dias_prorroga', 'fecha_modificacion', 'justificacion',
        'fecha_reinicio', 'fecha_reinicio_prevista',
    ];

    protected $casts = [
        'valor_adicion' => 'decimal:2',
        'dias_prorroga' => 'integer',
        'fecha_modificacion' => 'date',
        'fecha_reinicio' => 'date',
        'fecha_reinicio_prevista' => 'date',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    /** Suspensiones abiertas: sin fecha de reinicio. */
    public function scopeSuspensionesAbiertas(Builder $consulta): Builder
    {
        return $consulta->where('tipo', self::SUSPENSION)->whereNull('fecha_reinicio');
    }
}
```

En `app/Services/Contratacion/CierreContratos.php`, reemplazar (1 de 6):

```php

use App\Exceptions\ReglaNegocioException;
use App\Models\Contrato;
use App\Models\User;
use App\Services\Viabilizacion\HistorialChecklist;
use Brick\Math\BigDecimal;
```

por:

```php

use App\Exceptions\ReglaNegocioException;
use App\Models\Contrato;
use App\Models\ModificacionContrato;
use App\Models\User;
use App\Services\Viabilizacion\HistorialChecklist;
use Brick\Math\BigDecimal;
```

En `app/Services/Contratacion/CierreContratos.php`, reemplazar (2 de 6):

```php
 *   `contrato_liquidado`, con antes y después (R15).
 * - Sin ningún cambio (mismo estado y mismos datos) y sin observación no se guarda nada: ni versión
 *   nueva ni historial; responde el contrato tal cual y sus avisos. Con observación sí se registra.
 * - aviso() dice qué datos de cierre le faltan a un contrato según su estado, sin consultas.
 */
final class CierreContratos
```

por:

```php
 *   `contrato_liquidado`, con antes y después (R15).
 * - Sin ningún cambio (mismo estado y mismos datos) y sin observación no se guarda nada: ni versión
 *   nueva ni historial; responde el contrato tal cual y sus avisos. Con observación sí se registra.
 * - Fase 7B: un contrato con una suspensión abierta no se termina ni se liquida: primero se
 *   reinicia (SuspensionContratos). Uno suspendido sin suspensión abierta sigue estas reglas.
 * - aviso() dice qué datos de cierre le faltan a un contrato según su estado, sin consultas.
 */
final class CierreContratos
```

En `app/Services/Contratacion/CierreContratos.php`, reemplazar (3 de 6):

```php

    public const LIQUIDACION_ANTERIOR = 'La fecha de liquidación no puede ser anterior a la de terminación.';

    public const SUPERA_VIGENTE = 'El valor liquidado supera el valor vigente del contrato.';

    // Estados desde los que se termina un contrato.
```

por:

```php

    public const LIQUIDACION_ANTERIOR = 'La fecha de liquidación no puede ser anterior a la de terminación.';

    public const SUSPENSION_ABIERTA = 'Reinicia el contrato antes de terminarlo o liquidarlo.';

    public const SUPERA_VIGENTE = 'El valor liquidado supera el valor vigente del contrato.';

    // Estados desde los que se termina un contrato.
```

En `app/Services/Contratacion/CierreContratos.php`, reemplazar (4 de 6):

```php
            // Primero la versión: con un formulario viejo, el estado que se ve engañaría.
            $actual = $this->bloquear($contrato);
            $this->exigirVersion($actual, $actualizado, ContratosService::DESACTUALIZADO);
            if ($actual->estado === Contrato::LIQUIDADO) {
                throw new ReglaNegocioException(self::TERMINAR_LIQUIDADO);
            }
```

por:

```php
            // Primero la versión: con un formulario viejo, el estado que se ve engañaría.
            $actual = $this->bloquear($contrato);
            $this->exigirVersion($actual, $actualizado, ContratosService::DESACTUALIZADO);
            $this->exigirSinSuspensionAbierta($actual);
            if ($actual->estado === Contrato::LIQUIDADO) {
                throw new ReglaNegocioException(self::TERMINAR_LIQUIDADO);
            }
```

En `app/Services/Contratacion/CierreContratos.php`, reemplazar (5 de 6):

```php
        return DB::transaction(function () use ($contrato, $terminacion, $liquidacion, $valor, $observacion, $actualizado, $usuario) {
            $actual = $this->bloquear($contrato);
            $this->exigirVersion($actual, $actualizado, ContratosService::DESACTUALIZADO);
            if ($actual->estado === Contrato::ANULADO) {
                throw new ReglaNegocioException(self::ANULADO);
            }
```

por:

```php
        return DB::transaction(function () use ($contrato, $terminacion, $liquidacion, $valor, $observacion, $actualizado, $usuario) {
            $actual = $this->bloquear($contrato);
            $this->exigirVersion($actual, $actualizado, ContratosService::DESACTUALIZADO);
            $this->exigirSinSuspensionAbierta($actual);
            if ($actual->estado === Contrato::ANULADO) {
                throw new ReglaNegocioException(self::ANULADO);
            }
```

En `app/Services/Contratacion/CierreContratos.php`, reemplazar (6 de 6):

```php
        }
    }

    private function hoy(): string
    {
        return $this->avisos->hoy()->toDateString();
```

por:

```php
        }
    }

    private function exigirSinSuspensionAbierta(Contrato $contrato): void
    {
        if (ModificacionContrato::query()->where('contrato_id', $contrato->id)->suspensionesAbiertas()->exists()) {
            throw new ReglaNegocioException(self::SUSPENSION_ABIERTA);
        }
    }

    private function hoy(): string
    {
        return $this->avisos->hoy()->toDateString();
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (1 de 10):

```php
use App\Models\AvanceFinanciero;
use App\Models\AvanceFisico;
use App\Models\Contrato;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\TipoProceso;
```

por:

```php
use App\Models\AvanceFinanciero;
use App\Models\AvanceFisico;
use App\Models\Contrato;
use App\Models\ModificacionContrato;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\TipoProceso;
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (2 de 10):

```php
 *   y valor liquidado): se conservan.
 * - presentar(), la búsqueda, el detalle y el listado por proyecto traen los datos de cierre y
 *   `aviso_cierre` (CierreContratos::aviso), calculado con la fila, sin consultas.
 */
final class ContratosService
{
```

por:

```php
 *   y valor liquidado): se conservan.
 * - presentar(), la búsqueda, el detalle y el listado por proyecto traen los datos de cierre y
 *   `aviso_cierre` (CierreContratos::aviso), calculado con la fila, sin consultas.
 * - Fase 7B: al editar, el estado no pasa a suspendido desde otro (eso lo hace «Suspender»,
 *   SuspensionContratos) ni sale de suspendido mientras el contrato tiene una suspensión abierta
 *   (eso lo hace «Reiniciar»); sin suspensión abierta sí sale. Al crear, cualquier estado.
 * - presentar(), la búsqueda, el detalle y los listados por proyecto traen `aviso_suspension`
 *   (SuspensionContratos::aviso): en los listados, con una sola consulta para todas las filas.
 */
final class ContratosService
{
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (3 de 10):

```php

    public const CIERRE_POR_FORMULARIO = 'Para terminar o liquidar el contrato usa «Terminar» o «Liquidar».';

    // Estados a los que solo se llega con su acción (CierreContratos), no con el formulario.
    private const ESTADOS_DE_CIERRE = [Contrato::TERMINADO, Contrato::LIQUIDADO];
```

por:

```php

    public const CIERRE_POR_FORMULARIO = 'Para terminar o liquidar el contrato usa «Terminar» o «Liquidar».';

    public const SUSPENDER_POR_FORMULARIO = 'Para suspender el contrato usa «Suspender».';

    public const REINICIAR_POR_FORMULARIO = 'Para reanudar el contrato usa «Reiniciar».';

    // Estados a los que solo se llega con su acción (CierreContratos), no con el formulario.
    private const ESTADOS_DE_CIERRE = [Contrato::TERMINADO, Contrato::LIQUIDADO];
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (4 de 10):

```php
    public function __construct(
        private readonly HistorialChecklist $historial,
        private readonly ExpedientesContratacion $expedientes,
    ) {
    }
```

por:

```php
    public function __construct(
        private readonly HistorialChecklist $historial,
        private readonly ExpedientesContratacion $expedientes,
        private readonly AvisosContratacion $avisosContratacion,
    ) {
    }
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (5 de 10):

```php
            if ($nuevoEstado !== $actual->estado && in_array($nuevoEstado, self::ESTADOS_DE_CIERRE, true)) {
                throw ValidationException::withMessages(['estado' => self::CIERRE_POR_FORMULARIO]);
            }
            $this->exigirTipo($valores, $actual);
            [$valores] = $this->exigirVinculos($valores, $actual);
            // Sin proceso que lo dé, un proyecto null quitaría el que tiene.
```

por:

```php
            if ($nuevoEstado !== $actual->estado && in_array($nuevoEstado, self::ESTADOS_DE_CIERRE, true)) {
                throw ValidationException::withMessages(['estado' => self::CIERRE_POR_FORMULARIO]);
            }
            // Fase 7B: suspender y reanudar (con una suspensión abierta) tienen su acción.
            if ($nuevoEstado === Contrato::SUSPENDIDO && $actual->estado !== Contrato::SUSPENDIDO) {
                throw ValidationException::withMessages(['estado' => self::SUSPENDER_POR_FORMULARIO]);
            }
            if ($actual->estado === Contrato::SUSPENDIDO && $nuevoEstado !== Contrato::SUSPENDIDO
                && ModificacionContrato::query()->where('contrato_id', $actual->id)->suspensionesAbiertas()->exists()) {
                throw ValidationException::withMessages(['estado' => self::REINICIAR_POR_FORMULARIO]);
            }
            $this->exigirTipo($valores, $actual);
            [$valores] = $this->exigirVinculos($valores, $actual);
            // Sin proceso que lo dé, un proyecto null quitaría el que tiene.
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (6 de 10):

```php
            'aviso_cierre' => CierreContratos::aviso(
                $contrato->estado, $contrato->fecha_terminacion_real, $contrato->fecha_liquidacion, $contrato->valor_liquidado
            ),
            'anticipo' => (bool) $contrato->anticipo,
            'porcentaje_anticipo' => $this->entero($contrato->porcentaje_anticipo),
            'avance_fisico' => $this->entero($contrato->avance_fisico),
```

por:

```php
            'aviso_cierre' => CierreContratos::aviso(
                $contrato->estado, $contrato->fecha_terminacion_real, $contrato->fecha_liquidacion, $contrato->valor_liquidado
            ),
            'aviso_suspension' => SuspensionContratos::aviso(
                $contrato->estado,
                $contrato->estado === Contrato::SUSPENDIDO ? SuspensionContratos::abierta($contrato->id) : null,
                $this->avisosContratacion->hoy()->toDateString()
            ),
            'anticipo' => (bool) $contrato->anticipo,
            'porcentaje_anticipo' => $this->entero($contrato->porcentaje_anticipo),
            'avance_fisico' => $this->entero($contrato->avance_fisico),
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (7 de 10):

```php
        }
    }

    /**
     * Lo que avisa el proceso de un contrato en el módulo Contratos: null si no tiene etapas
     * vencidas, ni el cierre vencido, ni está sin actualización.
```

por:

```php
        }
    }

    /**
     * Completa las filas de contratos de los listados (consultas con DB::table que traen el id y el
     * estado) con `aviso_suspension` (SuspensionContratos::aviso): una sola consulta para las
     * suspensiones abiertas de todas las filas suspendidas, ninguna si no hay suspendidas.
     *
     * @param iterable<object> $filas
     */
    public function agregarAvisoSuspension(iterable $filas): void
    {
        $filas = Collection::make($filas);
        $abiertas = SuspensionContratos::abiertasDe($filas->mapWithKeys(fn ($f) => [(int) $f->id => $f->estado])->all());
        $hoy = $this->avisosContratacion->hoy()->toDateString();
        foreach ($filas as $fila) {
            $fila->aviso_suspension = SuspensionContratos::aviso($fila->estado, $abiertas[(int) $fila->id] ?? null, $hoy);
        }
    }

    /**
     * Lo que avisa el proceso de un contrato en el módulo Contratos: null si no tiene etapas
     * vencidas, ni el cierre vencido, ni está sin actualización.
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (8 de 10):

```php
        $filas = $consulta->get();
        $this->agregarProcesos($filas, true, $resumenes);
        $this->agregarAvisoCierre($filas);

        return $filas->map(fn ($f) => [
            'id' => (int) $f->id,
```

por:

```php
        $filas = $consulta->get();
        $this->agregarProcesos($filas, true, $resumenes);
        $this->agregarAvisoCierre($filas);
        $this->agregarAvisoSuspension($filas);

        return $filas->map(fn ($f) => [
            'id' => (int) $f->id,
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (9 de 10):

```php
            'proceso' => $f->proceso,
            'aviso_proceso' => $f->aviso_proceso,
            'aviso_cierre' => $f->aviso_cierre,
            'secop_id_contrato' => $f->secop_id_contrato,
            'updated_at' => $f->updated_at,
        ])->all();
    }

    /**
     * El contrato con la misma forma que un elemento de GET /listarContratos (con `aviso_proceso` y `aviso_cierre`),
     * más `proyecto_nombre`, o null si no existe.
     */
    public function detalle(int $id): ?object
```

por:

```php
            'proceso' => $f->proceso,
            'aviso_proceso' => $f->aviso_proceso,
            'aviso_cierre' => $f->aviso_cierre,
            'aviso_suspension' => $f->aviso_suspension,
            'secop_id_contrato' => $f->secop_id_contrato,
            'updated_at' => $f->updated_at,
        ])->all();
    }

    /**
     * El contrato con la misma forma que un elemento de GET /listarContratos (con `aviso_proceso`, `aviso_cierre` y `aviso_suspension`),
     * más `proyecto_nombre`, o null si no existe.
     */
    public function detalle(int $id): ?object
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (10 de 10):

```php
        $filas = [$fila];
        $this->agregarProcesos($filas, true);
        $this->agregarAvisoCierre($filas);
        $this->agregarDetalle($filas);

        return $fila;
```

por:

```php
        $filas = [$fila];
        $this->agregarProcesos($filas, true);
        $this->agregarAvisoCierre($filas);
        $this->agregarAvisoSuspension($filas);
        $this->agregarDetalle($filas);

        return $fila;
```

En `routes/contratacion.php`, reemplazar (1 de 1):

```php
    Route::post('/contratos/{contrato}/liquidar', [ContratoController::class, 'liquidar'])
        ->whereNumber('contrato')
        ->name('contratos.liquidar');
});

// SECOP II (datos abiertos): consultar para importar contratos. Administrador o Gestor, con
```

por:

```php
    Route::post('/contratos/{contrato}/liquidar', [ContratoController::class, 'liquidar'])
        ->whereNumber('contrato')
        ->name('contratos.liquidar');

    // Suspensión y reinicio del contrato (fase 7B): cambian el estado y registran la suspensión.
    Route::post('/contratos/{contrato}/suspender', [ContratoController::class, 'suspender'])
        ->whereNumber('contrato')
        ->name('contratos.suspender');
    Route::post('/contratos/{contrato}/reiniciar', [ContratoController::class, 'reiniciar'])
        ->whereNumber('contrato')
        ->name('contratos.reiniciar');
});

// SECOP II (datos abiertos): consultar para importar contratos. Administrador o Gestor, con
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/SuspensionContratosApiTest.php tests/Feature/Contratacion/AvancePonderadoApiTest.php tests/Feature/Contratacion/ContratosApiTest.php tests/Feature/Contratacion/ContratosBusquedaApiTest.php tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1391 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Services/Contratacion/SuspensionContratos.php app/Services/Contratacion/SuspensionGuardada.php database/migrations/2026_11_19_100024_add_suspension_a_modificaciones_contrato.php tests/Feature/Contratacion/SuspensionContratosApiTest.php app/Http/Controllers/ContratoController.php app/Http/Controllers/ProyectoController.php app/Models/ModificacionContrato.php app/Services/Contratacion/CierreContratos.php app/Services/Contratacion/ContratosService.php routes/contratacion.php tests/Feature/Contratacion/AvancePonderadoApiTest.php tests/Feature/Contratacion/ContratosApiTest.php tests/Feature/Contratacion/ContratosBusquedaApiTest.php tests/Feature/Contratacion/ExpedienteContratacionApiTest.php
git commit -m "feat: contract suspension and restart API (migration 25)"
```

---

### Tarea 3: Suspender y reiniciar en el editor del contrato

Los botones y diálogos de suspensión (`SuspensionContrato.jsx`) en `ContratoEditor.jsx`, con la vista previa de la fecha fin; la franja "Suspendido desde…"; las suspensiones en la pestaña Modificaciones, con la papelera solo en la última y la versión al eliminar; el select de estado y "Actualizar desde SECOP II" sin aplicar «Suspendido»; el chip "Reinicio vencido" en el módulo Contratos, la lista de Parámetros y la ficha del proyecto. Las utilidades puras, en `utils/contratos.js` y `utils/secop.js`, con pruebas Vitest.

**Files:**
- Create: `resources/js/components/contratos/SuspensionContrato.jsx`
- Modify: `resources/css/Contratacion.css`
- Modify: `resources/js/components/FichaProyecto.jsx`
- Modify: `resources/js/components/contratos/ContratoEditor.jsx`
- Modify: `resources/js/components/contratos/ContratosModulo.jsx`
- Modify: `resources/js/components/contratos/ContratosProyecto.jsx`
- Modify: `resources/js/utils/contratos.js`
- Test: `resources/js/utils/contratos.test.js`
- Modify: `resources/js/utils/secop.js`
- Test: `resources/js/utils/secop.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/contratos.test.js`, reemplazar (1 de 4):

```js
    saldoLiquidacion,
    textoAvisoCierre,
} from './contratos';

// Un contrato como lo devuelve presentar (POST /guardarContrato).
const presentado = {
```

por:

```js
    saldoLiquidacion,
    textoAvisoCierre,
} from './contratos';
import {
    CAMPOS_REINICIAR,
    CAMPOS_SUSPENDER,
    TIPOS_MODIFICACION,
    accionesSuspension,
    chipAvisoSuspension,
    confirmacionEliminarModificacion,
    contratoTrasSuspension,
    datosEliminarModificacion,
    datosReiniciar,
    datosSuspender,
    detalleSuspension,
    diasEntre,
    erroresReiniciar,
    erroresSuspender,
    formularioReiniciar,
    formularioSuspender,
    notaSuspensionCierre,
    puedeEliminarModificacion,
    sumarDias,
    suspensionAbierta,
    textoAvisoSuspension,
    textoDiasModificacion,
    textoReinicioPrevisto,
    textoVistaPreviaReinicio,
    vistaPreviaReinicio,
} from './contratos';

// Un contrato como lo devuelve presentar (POST /guardarContrato).
const presentado = {
```

En `resources/js/utils/contratos.test.js`, reemplazar (2 de 4):

```js
        expect(opcionesEstadoContrato('en_ejecucion', false).map((o) => o.valor)).toEqual(ESTADOS_CONTRATO.map(([v]) => v));
    });

    it('al editar, terminado y liquidado se deshabilitan y dicen qué acción usar', () => {
        const opciones = opcionesEstadoContrato('en_ejecucion', false);
        expect(deshabilitados(opciones)).toEqual(['terminado', 'liquidado']);
        expect(opciones.find((o) => o.valor === 'terminado').etiqueta).toBe('Terminado (usa «Terminar»)');
        expect(opciones.find((o) => o.valor === 'liquidado').etiqueta).toBe('Liquidado (usa «Liquidar»)');
        expect(opciones.find((o) => o.valor === 'en_ejecucion')).toEqual({ valor: 'en_ejecucion', etiqueta: 'En ejecución', deshabilitado: false });
    });

    it('el estado guardado sigue habilitado: un terminado puede quedarse así, pero no pasar a liquidado', () => {
        expect(deshabilitados(opcionesEstadoContrato('terminado', false))).toEqual(['liquidado']);
        expect(deshabilitados(opcionesEstadoContrato('liquidado', false))).toEqual(['terminado']);
        expect(opcionesEstadoContrato('liquidado', false).find((o) => o.valor === 'liquidado').etiqueta).toBe('Liquidado');
    });
```

por:

```js
        expect(opcionesEstadoContrato('en_ejecucion', false).map((o) => o.valor)).toEqual(ESTADOS_CONTRATO.map(([v]) => v));
    });

    it('al editar, suspendido, terminado y liquidado se deshabilitan y dicen qué acción usar', () => {
        const opciones = opcionesEstadoContrato('en_ejecucion', false);
        expect(deshabilitados(opciones)).toEqual(['suspendido', 'terminado', 'liquidado']);
        expect(opciones.find((o) => o.valor === 'suspendido').etiqueta).toBe('Suspendido (usa «Suspender»)');
        expect(opciones.find((o) => o.valor === 'terminado').etiqueta).toBe('Terminado (usa «Terminar»)');
        expect(opciones.find((o) => o.valor === 'liquidado').etiqueta).toBe('Liquidado (usa «Liquidar»)');
        expect(opciones.find((o) => o.valor === 'en_ejecucion')).toEqual({ valor: 'en_ejecucion', etiqueta: 'En ejecución', deshabilitado: false });
    });

    it('el estado guardado sigue habilitado: un terminado puede quedarse así, pero no pasar a liquidado', () => {
        expect(deshabilitados(opcionesEstadoContrato('terminado', false))).toEqual(['suspendido', 'liquidado']);
        expect(deshabilitados(opcionesEstadoContrato('liquidado', false))).toEqual(['suspendido', 'terminado']);
        expect(deshabilitados(opcionesEstadoContrato('suspendido', false))).toEqual(['terminado', 'liquidado']);
        expect(opcionesEstadoContrato('suspendido', false).find((o) => o.valor === 'suspendido').etiqueta).toBe('Suspendido');
        expect(opcionesEstadoContrato('liquidado', false).find((o) => o.valor === 'liquidado').etiqueta).toBe('Liquidado');
    });
```

En `resources/js/utils/contratos.test.js`, reemplazar (3 de 4):

```js
    });
});

describe('estadoCierreBloqueado', () => {
    it('SECOP II no cambia el estado de un contrato guardado a terminado ni a liquidado', () => {
        expect(estadoCierreBloqueado('liquidado', 'en_ejecucion')).toBe(true);
```

por:

```js
    });
});

describe('estado suspendido desde SECOP II', () => {
    it('no se aplica «Suspendido» a un contrato guardado que no lo está: se usa «Suspender»', () => {
        expect(estadoCierreBloqueado('suspendido', 'en_ejecucion')).toBe(true);
        expect(estadoCierreBloqueado('suspendido', 'celebrado', false)).toBe(true);
        expect(estadoCierreBloqueado('suspendido', null, false)).toBe(true);
        expect(avisoEstadoCierreSecop('suspendido', 'en_ejecucion'))
            .toBe('SECOP II dice «Suspendido»: usa «Suspender» para registrarlo con su fecha.');
        expect(avisoEstadoCierreSecop('suspendido', null))
            .toBe('SECOP II dice «Suspendido»: usa «Suspender» para registrarlo con su fecha.');
    });

    it('a un contrato terminado o liquidado SECOP II lo ve suspendido: va detrás, se bloquea sin aviso', () => {
        expect(estadoCierreBloqueado('suspendido', 'liquidado')).toBe(true);
        expect(avisoEstadoCierreSecop('suspendido', 'liquidado')).toBeNull();
        expect(avisoEstadoCierreSecop('suspendido', 'terminado')).toBeNull();
    });

    it('con una suspensión abierta, SECOP II no lo saca de suspendido: se usa «Reiniciar»', () => {
        expect(estadoCierreBloqueado('en_ejecucion', 'suspendido', false, true)).toBe(true);
        expect(estadoCierreBloqueado('celebrado', 'suspendido', false, true)).toBe(true);
        expect(estadoCierreBloqueado('terminado', 'suspendido', false, true)).toBe(true);
        expect(avisoEstadoCierreSecop('en_ejecucion', 'suspendido', false, true))
            .toBe('SECOP II dice «En ejecución»: usa «Reiniciar» para registrar el reinicio.');
        expect(avisoEstadoCierreSecop('liquidado', 'suspendido', false, true))
            .toBe('SECOP II dice «Liquidado»: usa «Reiniciar» para registrar el reinicio.');
    });

    it('sin suspensión abierta, un suspendido sí toma otro estado (salvo terminado y liquidado); el mismo estado, nada', () => {
        expect(estadoCierreBloqueado('en_ejecucion', 'suspendido')).toBe(false);
        expect(avisoEstadoCierreSecop('en_ejecucion', 'suspendido')).toBeNull();
        expect(estadoCierreBloqueado('suspendido', 'suspendido', false, true)).toBe(false);
        expect(avisoEstadoCierreSecop('suspendido', 'suspendido', false, true)).toBeNull();
        expect(estadoCierreBloqueado(undefined, 'suspendido', false, true)).toBe(false);
        expect(avisoEstadoCierreSecop(undefined, 'suspendido', false, true)).toBeNull();
    });

    it('un contrato nuevo toma «Suspendido» de SECOP II', () => {
        expect(estadoCierreBloqueado('suspendido', '', true)).toBe(false);
        expect(avisoEstadoCierreSecop('suspendido', '', true)).toBeNull();
    });
});

describe('estadoCierreBloqueado', () => {
    it('SECOP II no cambia el estado de un contrato guardado a terminado ni a liquidado', () => {
        expect(estadoCierreBloqueado('liquidado', 'en_ejecucion')).toBe(true);
```

En `resources/js/utils/contratos.test.js`, reemplazar (4 de 4):

```js
        expect(muestraBloqueCierre(null)).toBe(false);
    });
});
```

por:

```js
        expect(muestraBloqueCierre(null)).toBe(false);
    });
});


// ---------- Suspensión y reinicio del contrato (fase 7B) ----------

// Un contrato suspendido con su suspensión abierta, como lo devuelven presentar y los listados.
const avisoAbierto = { desde: '2026-11-05', dias: 14, reinicio_previsto: null, vencido: false };
const suspendido = { ...presentado, estado: 'suspendido', aviso_suspension: avisoAbierto };
// Filas de la pestaña Modificaciones (detalle y GET /listarContratos).
const filaSuspension = (datos = {}) => ({
    id: 30, contrato_id: 7, numero_otrosi: 'Acta 2', tipo: 'suspension', valor_adicion: null, dias_prorroga: null,
    fecha_modificacion: '2026-11-05', fecha_reinicio: null, fecha_reinicio_prevista: null, justificacion: 'Lluvias', ...datos,
});

describe('suspensionAbierta', () => {
    it('la del aviso de la API: desde cuándo y el reinicio previsto', () => {
        expect(suspensionAbierta(suspendido)).toEqual({ desde: '2026-11-05', reinicio_previsto: null });
        expect(suspensionAbierta({ ...suspendido, aviso_suspension: { ...avisoAbierto, reinicio_previsto: '2026-11-10' } }))
            .toEqual({ desde: '2026-11-05', reinicio_previsto: '2026-11-10' });
    });

    it('sin la clave del aviso, la fila abierta de sus modificaciones (con las fechas sin hora)', () => {
        const contrato = {
            ...presentado, estado: 'suspendido',
            modificaciones: [{ id: 1, tipo: 'prorroga', dias_prorroga: 10 }, filaSuspension({ fecha_modificacion: '2026-11-05 00:00:00', fecha_reinicio_prevista: '2026-12-01' })],
        };
        expect(suspensionAbierta(contrato)).toEqual({ desde: '2026-11-05', reinicio_previsto: '2026-12-01' });
    });

    it('sin suspensión abierta (cerradas o ninguna), null', () => {
        expect(suspensionAbierta(presentado)).toBeNull();
        expect(suspensionAbierta({ ...presentado, modificaciones: [filaSuspension({ fecha_reinicio: '2026-11-10', dias_prorroga: 5 })] })).toBeNull();
        expect(suspensionAbierta({ ...presentado, estado: 'suspendido', aviso_suspension: null })).toBeNull();
        expect(suspensionAbierta(null)).toBeNull();
    });

    it('con la clave del aviso (aunque sea null), manda el aviso y no las modificaciones, que pueden ir atrasadas', () => {
        // Tras reiniciar sin poder recargar: la fila sigue abierta en local, pero el aviso ya es null.
        expect(suspensionAbierta({ ...presentado, estado: 'en_ejecucion', aviso_suspension: null, modificaciones: [filaSuspension()] })).toBeNull();
        // Tras suspender sin poder recargar: la fila nueva aún no está, el aviso sí.
        expect(suspensionAbierta({ ...suspendido, modificaciones: [] })).toEqual({ desde: '2026-11-05', reinicio_previsto: null });
        // Una clave undefined cuenta como sin aviso: se miran las modificaciones.
        expect(suspensionAbierta({ ...presentado, aviso_suspension: undefined, modificaciones: [filaSuspension()] }))
            .toEqual({ desde: '2026-11-05', reinicio_previsto: null });
    });
});

describe('accionesSuspension', () => {
    it('un contrato guardado en ejecución se suspende; uno suspendido se reinicia', () => {
        expect(accionesSuspension(presentado)).toEqual({ suspender: true, reiniciar: false });
        expect(accionesSuspension(suspendido)).toEqual({ suspender: false, reiniciar: true });
    });

    it('un suspendido sin suspensión abierta (p. ej. de SECOP II) también se reinicia', () => {
        expect(accionesSuspension({ ...presentado, estado: 'suspendido' })).toEqual({ suspender: false, reiniciar: true });
    });

    it('en ejecución con una suspensión abierta no se suspende otra vez', () => {
        expect(accionesSuspension({ ...presentado, modificaciones: [filaSuspension()] })).toEqual({ suspender: false, reiniciar: false });
    });

    it('en otros estados, o sin contrato guardado, ninguna', () => {
        ['celebrado', 'terminado', 'liquidado', 'anulado', null, 'Vigente'].forEach((estado) => {
            expect(accionesSuspension({ ...presentado, estado })).toEqual({ suspender: false, reiniciar: false });
        });
        expect(accionesSuspension({ ...presentado, id: undefined })).toEqual({ suspender: false, reiniciar: false });
        expect(accionesSuspension(null)).toEqual({ suspender: false, reiniciar: false });
    });
});

describe('cierre con una suspensión abierta', () => {
    it('no se termina ni se liquida: primero se reinicia, con su nota', () => {
        expect(accionesCierre(suspendido)).toMatchObject({ terminar: false, liquidar: false });
        expect(notaSuspensionCierre(suspendido)).toBe('Reinicia el contrato antes de terminarlo o liquidarlo.');
    });

    it('un suspendido sin suspensión abierta sigue como en la fase 7A, sin nota', () => {
        expect(accionesCierre({ ...presentado, estado: 'suspendido' })).toMatchObject({ terminar: true, liquidar: true });
        expect(notaSuspensionCierre({ ...presentado, estado: 'suspendido' })).toBeNull();
        expect(notaSuspensionCierre(presentado)).toBeNull();
        expect(notaSuspensionCierre(null)).toBeNull();
    });
});

describe('opcionesEstadoContrato con una suspensión abierta', () => {
    const deshabilitados = (opciones) => opciones.filter((o) => o.deshabilitado).map((o) => o.valor);

    it('solo queda suspendido: para salir se usa «Reiniciar»', () => {
        const opciones = opcionesEstadoContrato('suspendido', false, true);
        expect(deshabilitados(opciones)).toEqual(['celebrado', 'en_ejecucion', 'terminado', 'liquidado', 'anulado']);
        expect(opciones.find((o) => o.valor === 'en_ejecucion').etiqueta).toBe('En ejecución (usa «Reiniciar»)');
        expect(opciones.find((o) => o.valor === 'celebrado').etiqueta).toBe('Celebrado (reinicia primero)');
        expect(opciones.find((o) => o.valor === 'anulado').etiqueta).toBe('Anulado (reinicia primero)');
        expect(opciones.find((o) => o.valor === 'terminado').etiqueta).toBe('Terminado (reinicia primero)');
        expect(opciones.find((o) => o.valor === 'liquidado').etiqueta).toBe('Liquidado (reinicia primero)');
        expect(opciones.find((o) => o.valor === 'suspendido')).toEqual({ valor: 'suspendido', etiqueta: 'Suspendido', deshabilitado: false });
    });

    it('un contrato nuevo no tiene suspensiones: cualquier estado', () => {
        expect(deshabilitados(opcionesEstadoContrato('', true, false))).toEqual([]);
    });
});

describe('textos del aviso de suspensión', () => {
    it('desde cuándo y cuántos días lleva suspendido', () => {
        expect(textoAvisoSuspension(avisoAbierto)).toBe('Suspendido desde 05/11/2026 (14 días)');
        expect(textoAvisoSuspension({ ...avisoAbierto, dias: 1 })).toBe('Suspendido desde 05/11/2026 (1 día)');
        expect(textoAvisoSuspension({ ...avisoAbierto, dias: 0 })).toBe('Suspendido desde 05/11/2026 (0 días)');
        expect(textoAvisoSuspension(null)).toBeNull();
        expect(textoAvisoSuspension(undefined)).toBeNull();
    });

    it('el chip solo si el reinicio previsto ya pasó', () => {
        expect(chipAvisoSuspension({ ...avisoAbierto, reinicio_previsto: '2026-11-10', vencido: true })).toBe('Reinicio vencido');
        expect(chipAvisoSuspension({ ...avisoAbierto, reinicio_previsto: '2026-11-30', vencido: false })).toBeNull();
        expect(chipAvisoSuspension(avisoAbierto)).toBeNull();
        expect(chipAvisoSuspension(null)).toBeNull();
    });

    it('el reinicio previsto, con «vencido» si pasó; sin previsto, null', () => {
        expect(textoReinicioPrevisto({ ...avisoAbierto, reinicio_previsto: '2026-11-10', vencido: true })).toBe('Reinicio previsto el 10/11/2026: vencido');
        expect(textoReinicioPrevisto({ ...avisoAbierto, reinicio_previsto: '2026-11-30', vencido: false })).toBe('Reinicio previsto el 30/11/2026');
        expect(textoReinicioPrevisto(avisoAbierto)).toBeNull();
        expect(textoReinicioPrevisto(null)).toBeNull();
    });
});

describe('días calendario', () => {
    it('diasEntre cuenta días exactos entre fechas año-mes-día, sin zona horaria', () => {
        expect(diasEntre('2026-11-05', '2026-11-19')).toBe(14);
        expect(diasEntre('2026-11-05', '2026-11-05')).toBe(0);
        // Cruza el fin de año, un 29 de febrero y meses de distinto largo.
        expect(diasEntre('2026-12-25', '2027-01-05')).toBe(11);
        expect(diasEntre('2028-02-28', '2028-03-01')).toBe(2);
        expect(diasEntre('2026-03-01', '2026-11-01')).toBe(245);
        expect(diasEntre('2026-11-19', '2026-11-05')).toBe(-14);
        expect(diasEntre('2026-11-05 00:00:00', '2026-11-06')).toBe(1);
    });

    it('diasEntre sin una fecha válida, null', () => {
        expect(diasEntre('', '2026-11-05')).toBeNull();
        expect(diasEntre('2026-11-05', null)).toBeNull();
        expect(diasEntre('05/11/2026', '2026-11-06')).toBeNull();
    });

    it('sumarDias corre la fecha los días dados', () => {
        expect(sumarDias('2026-06-30', 14)).toBe('2026-07-14');
        expect(sumarDias('2026-12-20', 15)).toBe('2027-01-04');
        expect(sumarDias('2028-02-28', 1)).toBe('2028-02-29');
        expect(sumarDias('2026-06-30', 0)).toBe('2026-06-30');
        expect(sumarDias('2026-06-30 00:00:00', 1)).toBe('2026-07-01');
        expect(sumarDias('', 3)).toBeNull();
    });
});

describe('formularios y datos de suspender y reiniciar', () => {
    const hoy = '2026-11-19';

    it('los campos de cada diálogo, con los nombres de la API', () => {
        expect(CAMPOS_SUSPENDER).toEqual(['fecha_suspension', 'fecha_reinicio_prevista', 'numero_acta', 'motivo']);
        expect(CAMPOS_REINICIAR).toEqual(['fecha_suspension', 'fecha_reinicio', 'observacion']);
    });

    it('los formularios empiezan con la fecha de hoy', () => {
        expect(formularioSuspender(presentado, hoy)).toEqual({ fecha_suspension: hoy, fecha_reinicio_prevista: '', numero_acta: '', motivo: '' });
        expect(formularioReiniciar(suspendido, hoy)).toEqual({ fecha_suspension: '', fecha_reinicio: hoy, observacion: '' });
    });

    it('lo que espera POST /contratos/{id}/suspender: vacíos como null, textos recortados y la versión', () => {
        expect(datosSuspender({ fecha_suspension: '2026-11-05', fecha_reinicio_prevista: '', numero_acta: '  Acta 2 ', motivo: ' ' }, presentado))
            .toEqual({ fecha_suspension: '2026-11-05', fecha_reinicio_prevista: null, numero_acta: 'Acta 2', motivo: null, actualizado: '2026-11-18 09:00:00' });
        expect(datosSuspender({ fecha_suspension: '2026-11-05', fecha_reinicio_prevista: '2026-12-01', numero_acta: '', motivo: 'Lluvias' }, { ...presentado, updated_at: null }))
            .toEqual({ fecha_suspension: '2026-11-05', fecha_reinicio_prevista: '2026-12-01', numero_acta: null, motivo: 'Lluvias', actualizado: null });
    });

    it('lo que espera POST /contratos/{id}/reiniciar: la fecha de suspensión solo si no hay una abierta', () => {
        const formulario = { fecha_suspension: '2026-10-01', fecha_reinicio: '2026-11-19', observacion: ' Acta de reinicio ' };
        expect(datosReiniciar(formulario, suspendido))
            .toEqual({ fecha_reinicio: '2026-11-19', observacion: 'Acta de reinicio', actualizado: '2026-11-18 09:00:00' });
        expect(datosReiniciar({ ...formulario, observacion: '' }, { ...suspendido, aviso_suspension: null }))
            .toEqual({ fecha_reinicio: '2026-11-19', fecha_suspension: '2026-10-01', observacion: null, actualizado: '2026-11-18 09:00:00' });
    });
});

describe('erroresSuspender', () => {
    const hoy = '2026-11-19';
    const formulario = { fecha_suspension: '2026-11-05', fecha_reinicio_prevista: '', numero_acta: '', motivo: '' };

    it('sin errores con una fecha entre el inicio y hoy', () => {
        expect(erroresSuspender(formulario, presentado, hoy)).toEqual({});
        expect(erroresSuspender({ ...formulario, fecha_suspension: hoy, fecha_reinicio_prevista: hoy }, presentado, hoy)).toEqual({});
    });

    it('la fecha de suspensión: obligatoria, no futura, no anterior al inicio ni al último reinicio', () => {
        expect(erroresSuspender({ ...formulario, fecha_suspension: '' }, presentado, hoy)).toEqual({ fecha_suspension: 'Escribe la fecha de suspensión.' });
        expect(erroresSuspender({ ...formulario, fecha_suspension: '2026-11-20' }, presentado, hoy))
            .toEqual({ fecha_suspension: 'La fecha de suspensión no puede ser posterior a hoy.' });
        expect(erroresSuspender({ ...formulario, fecha_suspension: '2026-01-09' }, presentado, hoy))
            .toEqual({ fecha_suspension: 'La fecha de suspensión no puede ser anterior a la de inicio.' });
        const conCerrada = { ...presentado, modificaciones: [filaSuspension({ fecha_modificacion: '2026-10-01', fecha_reinicio: '2026-11-10 00:00:00', dias_prorroga: 40 })] };
        expect(erroresSuspender(formulario, conCerrada, hoy))
            .toEqual({ fecha_suspension: 'La fecha de suspensión no puede ser anterior al último reinicio.' });
        expect(erroresSuspender({ ...formulario, fecha_suspension: '2026-11-10' }, conCerrada, hoy)).toEqual({});
    });

    it('la prevista no es anterior a la de suspensión; el acta y el motivo tienen largo máximo', () => {
        expect(erroresSuspender({ ...formulario, fecha_reinicio_prevista: '2026-11-04' }, presentado, hoy))
            .toEqual({ fecha_reinicio_prevista: 'La fecha prevista de reinicio no puede ser anterior a la de suspensión.' });
        // La prevista puede ser futura.
        expect(erroresSuspender({ ...formulario, fecha_reinicio_prevista: '2027-01-15' }, presentado, hoy)).toEqual({});
        expect(erroresSuspender({ ...formulario, numero_acta: 'x'.repeat(51), motivo: 'x'.repeat(2001) }, presentado, hoy)).toEqual({
            numero_acta: 'El número del acta admite hasta 50 caracteres.',
            motivo: 'El motivo admite hasta 2000 caracteres.',
        });
        expect(erroresSuspender({ ...formulario, numero_acta: 'x'.repeat(50) }, presentado, hoy)).toEqual({});
    });
});

describe('erroresReiniciar', () => {
    const hoy = '2026-11-19';
    const formulario = { fecha_suspension: '', fecha_reinicio: '2026-11-19', observacion: '' };

    it('con la suspensión abierta, solo la fecha de reinicio: obligatoria, no futura, no anterior a la suspensión', () => {
        expect(erroresReiniciar(formulario, suspendido, hoy)).toEqual({});
        expect(erroresReiniciar({ ...formulario, fecha_reinicio: '2026-11-05' }, suspendido, hoy)).toEqual({});
        expect(erroresReiniciar({ ...formulario, fecha_reinicio: '' }, suspendido, hoy)).toEqual({ fecha_reinicio: 'Escribe la fecha de reinicio.' });
        expect(erroresReiniciar({ ...formulario, fecha_reinicio: '2026-11-20' }, suspendido, hoy))
            .toEqual({ fecha_reinicio: 'La fecha de reinicio no puede ser posterior a hoy.' });
        expect(erroresReiniciar({ ...formulario, fecha_reinicio: '2026-11-04' }, suspendido, hoy))
            .toEqual({ fecha_reinicio: 'La fecha de reinicio no puede ser anterior a la de suspensión.' });
    });

    it('sin suspensión abierta pide la fecha de suspensión, con las reglas de suspender', () => {
        const sinAbierta = { ...presentado, estado: 'suspendido', aviso_suspension: null };
        expect(erroresReiniciar(formulario, sinAbierta, hoy)).toEqual({ fecha_suspension: 'Escribe la fecha de suspensión.' });
        expect(erroresReiniciar({ ...formulario, fecha_suspension: '2026-01-01' }, sinAbierta, hoy))
            .toEqual({ fecha_suspension: 'La fecha de suspensión no puede ser anterior a la de inicio.' });
        expect(erroresReiniciar({ ...formulario, fecha_suspension: '2026-10-01', fecha_reinicio: '2026-09-30' }, sinAbierta, hoy))
            .toEqual({ fecha_reinicio: 'La fecha de reinicio no puede ser anterior a la de suspensión.' });
        expect(erroresReiniciar({ ...formulario, fecha_suspension: '2026-10-01' }, sinAbierta, hoy)).toEqual({});
    });

    it('con la suspensión abierta, la fecha de suspensión escrita no cuenta', () => {
        expect(erroresReiniciar({ ...formulario, fecha_suspension: '2030-01-01' }, suspendido, hoy)).toEqual({});
    });

    it('la observación tiene largo máximo', () => {
        expect(erroresReiniciar({ ...formulario, observacion: 'x'.repeat(2001) }, suspendido, hoy))
            .toEqual({ observacion: 'La observación admite hasta 2000 caracteres.' });
    });
});

describe('vista previa del reinicio', () => {
    it('los días suspendidos y la fecha fin corrida', () => {
        const vista = vistaPreviaReinicio({ fecha_reinicio: '2026-11-19' }, suspendido);
        expect(vista).toEqual({ dias: 14, fechaFinActual: '2026-06-30', fechaFinNueva: '2026-07-14' });
        expect(textoVistaPreviaReinicio(vista)).toBe('Se suman 14 días: la fecha fin pasa de 30/06/2026 a 14/07/2026.');
    });

    it('sin suspensión abierta usa la fecha de suspensión escrita', () => {
        const sinAbierta = { ...presentado, estado: 'suspendido', aviso_suspension: null, fecha_fin: '2026-12-31 00:00:00' };
        expect(vistaPreviaReinicio({ fecha_suspension: '2026-11-18', fecha_reinicio: '2026-11-19' }, sinAbierta))
            .toEqual({ dias: 1, fechaFinActual: '2026-12-31', fechaFinNueva: '2027-01-01' });
        expect(textoVistaPreviaReinicio({ dias: 1, fechaFinActual: '2026-12-31', fechaFinNueva: '2027-01-01' }))
            .toBe('Se suma 1 día: la fecha fin pasa de 31/12/2026 a 01/01/2027.');
    });

    it('el mismo día no suma días', () => {
        const vista = vistaPreviaReinicio({ fecha_reinicio: '2026-11-05' }, suspendido);
        expect(vista).toEqual({ dias: 0, fechaFinActual: '2026-06-30', fechaFinNueva: '2026-06-30' });
        expect(textoVistaPreviaReinicio(vista)).toBe('No se suman días: la fecha fin no cambia.');
    });

    it('sin fecha fin, solo los días', () => {
        const vista = vistaPreviaReinicio({ fecha_reinicio: '2026-11-19' }, { ...suspendido, fecha_fin: null });
        expect(vista).toEqual({ dias: 14, fechaFinActual: null, fechaFinNueva: null });
        expect(textoVistaPreviaReinicio(vista)).toBe('Se suman 14 días a la fecha fin del contrato.');
    });

    it('sin fechas completas o con el reinicio antes de la suspensión, null', () => {
        expect(vistaPreviaReinicio({ fecha_reinicio: '' }, suspendido)).toBeNull();
        expect(vistaPreviaReinicio({ fecha_reinicio: '2026-11-04' }, suspendido)).toBeNull();
        expect(vistaPreviaReinicio({ fecha_suspension: '', fecha_reinicio: '2026-11-19' }, { ...suspendido, aviso_suspension: null })).toBeNull();
        expect(textoVistaPreviaReinicio(null)).toBeNull();
    });
});

describe('contratoTrasSuspension', () => {
    it('toma del contrato presentado el estado, la fecha fin, los avisos y la versión; lo demás se conserva', () => {
        const abierto = { ...listado, anexos: [{ id: 1 }], modificaciones: [{ id: 2 }] };
        const respuesta = {
            ...presentado, estado: 'en_ejecucion', fecha_fin: '2026-07-14', aviso_suspension: null, aviso_cierre: null,
            updated_at: '2026-11-19T15:00:00-05:00', objeto: 'No se toma',
        };
        expect(contratoTrasSuspension(abierto, respuesta)).toEqual({
            ...abierto, estado: 'en_ejecucion', fecha_fin: '2026-07-14', aviso_suspension: null, aviso_cierre: null, updated_at: '2026-11-19T15:00:00-05:00',
        });
        expect(contratoTrasSuspension(abierto, { ...respuesta, aviso_suspension: undefined, aviso_cierre: undefined }))
            .toMatchObject({ aviso_suspension: null, aviso_cierre: null });
    });

    it('al reiniciar, cierra en local la suspensión abierta con la fecha de reinicio y sus días', () => {
        const abierto = { ...suspendido, modificaciones: [{ id: 2, tipo: 'prorroga', dias_prorroga: 10 }, filaSuspension({ fecha_modificacion: '2026-11-05 00:00:00' })] };
        const respuesta = { ...presentado, estado: 'en_ejecucion', fecha_fin: '2026-07-14', aviso_suspension: null, updated_at: 'v2' };
        const tras = contratoTrasSuspension(abierto, respuesta, { fecha_reinicio: '2026-11-19' });
        expect(tras.modificaciones).toEqual([
            { id: 2, tipo: 'prorroga', dias_prorroga: 10 },
            filaSuspension({ fecha_modificacion: '2026-11-05 00:00:00', fecha_reinicio: '2026-11-19', dias_prorroga: 14 }),
        ]);
        expect(suspensionAbierta(tras)).toBeNull();
        expect(suspensionAbierta({ ...tras, aviso_suspension: undefined })).toBeNull();
    });

    it('al suspender (o sin fecha de reinicio), las modificaciones no cambian y el aviso dice que está abierta', () => {
        const abierto = { ...presentado, modificaciones: [{ id: 2, tipo: 'prorroga' }] };
        const tras = contratoTrasSuspension(abierto, { ...suspendido, updated_at: 'v2' });
        expect(tras.modificaciones).toEqual([{ id: 2, tipo: 'prorroga' }]);
        expect(suspensionAbierta(tras)).toEqual({ desde: '2026-11-05', reinicio_previsto: null });
        // Sin modificaciones en el contrato abierto, no se agregan.
        expect(contratoTrasSuspension(listado, { ...presentado, estado: 'en_ejecucion' }, { fecha_reinicio: '2026-11-19' })).not.toHaveProperty('modificaciones');
    });
});

describe('suspensiones en la pestaña Modificaciones', () => {
    it('el formulario no ofrece el tipo suspensión; la tabla sí lo nombra', () => {
        expect(TIPOS_MODIFICACION.map(([valor]) => valor)).toEqual(['adicion', 'prorroga', 'adicion_prorroga']);
        expect(etiquetaTipoModificacion('suspension')).toBe('Suspensión');
    });

    it('el detalle: sus fechas y días, o «abierta» con el reinicio previsto', () => {
        expect(detalleSuspension(filaSuspension())).toBe('Desde el 05/11/2026: abierta');
        expect(detalleSuspension(filaSuspension({ fecha_reinicio_prevista: '2026-12-01' })))
            .toBe('Desde el 05/11/2026: abierta, reinicio previsto el 01/12/2026');
        expect(detalleSuspension(filaSuspension({ fecha_reinicio: '2026-11-19', dias_prorroga: 14 }))).toBe('Del 05/11/2026 al 19/11/2026: 14 días');
        expect(detalleSuspension(filaSuspension({ fecha_reinicio: '2026-11-06 00:00:00', dias_prorroga: 1 }))).toBe('Del 05/11/2026 al 06/11/2026: 1 día');
        expect(detalleSuspension({ tipo: 'prorroga', dias_prorroga: 10 })).toBeNull();
    });

    it('la columna de días: «Abierta», los días de la suspensión (también 0) o los de la prórroga', () => {
        expect(textoDiasModificacion(filaSuspension())).toBe('Abierta');
        expect(textoDiasModificacion(filaSuspension({ fecha_reinicio: '2026-11-05', dias_prorroga: 0 }))).toBe('0');
        expect(textoDiasModificacion(filaSuspension({ fecha_reinicio: '2026-11-19', dias_prorroga: 14 }))).toBe('14');
        expect(textoDiasModificacion({ tipo: 'prorroga', dias_prorroga: 30 })).toBe('30');
        expect(textoDiasModificacion({ tipo: 'adicion', dias_prorroga: null })).toBe('-');
    });

    it('eliminar una suspensión manda la versión del contrato; una adición o prórroga, solo el id', () => {
        expect(datosEliminarModificacion(filaSuspension(), { ...suspendido, updated_at: '2026-11-19 10:00:00' }))
            .toEqual({ id: 30, actualizado: '2026-11-19 10:00:00' });
        expect(datosEliminarModificacion(filaSuspension(), { ...suspendido, updated_at: undefined })).toEqual({ id: 30, actualizado: null });
        expect(datosEliminarModificacion({ id: 4, tipo: 'prorroga' }, { ...presentado, updated_at: 'v' })).toEqual({ id: 4 });
    });

    it('solo la última suspensión (por fecha y luego por id, como el servidor) se puede eliminar; las demás modificaciones, siempre', () => {
        const primera = filaSuspension({ id: 30, fecha_modificacion: '2026-10-01', fecha_reinicio: '2026-10-11', dias_prorroga: 10 });
        const ultima = filaSuspension({ id: 31, fecha_modificacion: '2026-11-01 00:00:00' });
        const prorroga = { id: 32, tipo: 'prorroga', fecha_modificacion: '2026-11-10' };
        const lista = [ultima, prorroga, primera];
        expect(puedeEliminarModificacion(primera, lista)).toBe(false);
        expect(puedeEliminarModificacion(ultima, lista)).toBe(true);
        expect(puedeEliminarModificacion(prorroga, lista)).toBe(true);
        // El mismo día, la de id mayor.
        const otra = filaSuspension({ id: 33, fecha_modificacion: '2026-11-01' });
        expect(puedeEliminarModificacion(ultima, [...lista, otra])).toBe(false);
        expect(puedeEliminarModificacion(otra, [...lista, otra])).toBe(true);
        expect(puedeEliminarModificacion(ultima, null)).toBe(true);
    });

    it('al eliminar, la confirmación explica la regla de la última y qué pasa con el contrato', () => {
        expect(confirmacionEliminarModificacion(filaSuspension())).toEqual({
            titulo: '¿Eliminar esta suspensión?',
            texto: 'Solo se puede eliminar la última suspensión. Como está abierta, el contrato vuelve a «En ejecución». El cambio queda en el historial del contrato.',
        });
        expect(confirmacionEliminarModificacion(filaSuspension({ fecha_reinicio: '2026-11-19', dias_prorroga: 14 }))).toEqual({
            titulo: '¿Eliminar esta suspensión?',
            texto: 'Solo se puede eliminar la última suspensión. La fecha fin se recalcula sin sus días. El cambio queda en el historial del contrato.',
        });
        expect(confirmacionEliminarModificacion({ tipo: 'adicion' })).toEqual({
            titulo: '¿Eliminar esta modificación?', texto: 'Se recalculará el valor y la fecha fin del contrato.',
        });
    });
});
```

En `resources/js/utils/secop.test.js`, reemplazar (1 de 1):

```js
    });
});

describe('aplicarDiferencias', () => {
    const formulario = { ...formularioContrato(guardado), objeto: 'X', valor_inicial: '1', estado: 'suspendido' };
    const otro = conContrato({ secop_proceso: 'CO1.BDOS.111', url_secop: 'https://community.secop.gov.co/otro' });
```

por:

```js
    });
});

describe('el estado suspendido con SECOP II', () => {
    const conEstado = (estado) => conContrato({ estado });
    const suspendidoAbierto = {
        ...guardado, estado: 'suspendido', aviso_suspension: { desde: '2026-11-05', dias: 14, reinicio_previsto: null, vencido: false },
    };

    it('a un contrato guardado en ejecución no le ofrece ni le copia «Suspendido», y avisa usar «Suspender»', () => {
        const formulario = formularioContrato(guardado);
        expect(diferenciasSecop(formulario, conEstado('suspendido'), { tipos, contrato: guardado })).toEqual([]);
        expect(aplicarDiferencias(formulario, conEstado('suspendido'), ['estado'], guardado).estado).toBe('en_ejecucion');
        expect(avisosSecop(conEstado('suspendido'), formulario, guardado))
            .toEqual(['SECOP II dice «Suspendido»: usa «Suspender» para registrarlo con su fecha.']);
    });

    it('con una suspensión abierta no lo saca de suspendido, y avisa usar «Reiniciar»', () => {
        const formulario = formularioContrato(suspendidoAbierto);
        expect(diferenciasSecop(formulario, resultado, { tipos, contrato: suspendidoAbierto })).toEqual([]);
        expect(aplicarDiferencias(formulario, resultado, ['estado'], suspendidoAbierto).estado).toBe('suspendido');
        expect(avisosSecop(resultado, formulario, suspendidoAbierto))
            .toEqual(['SECOP II dice «En ejecución»: usa «Reiniciar» para registrar el reinicio.']);
    });

    it('un suspendido sin suspensión abierta sí toma el estado de SECOP II', () => {
        const sinAbierta = { ...guardado, estado: 'suspendido', aviso_suspension: null };
        const formulario = formularioContrato(sinAbierta);
        expect(diferenciasSecop(formulario, resultado, { tipos, contrato: sinAbierta }).map((d) => d.campo)).toEqual(['estado']);
        expect(aplicarDiferencias(formulario, resultado, ['estado'], sinAbierta).estado).toBe('en_ejecucion');
        expect(avisosSecop(resultado, formulario, sinAbierta)).toEqual([]);
    });
});

describe('aplicarDiferencias', () => {
    const formulario = { ...formularioContrato(guardado), objeto: 'X', valor_inicial: '1', estado: 'suspendido' };
    const otro = conContrato({ secop_proceso: 'CO1.BDOS.111', url_secop: 'https://community.secop.gov.co/otro' });
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/contratos.test.js resources/js/utils/secop.test.js`
Expected: FALLAN 49 y pasan 170, en los 2 archivos: en `contratos.test.js`, las nuevas de `accionesSuspension`, `suspensionAbierta`, `textoAvisoSuspension`, `diasEntre`, `sumarDias`, los formularios, datos y errores de los diálogos, la vista previa del reinicio, las filas de suspensión de la pestaña Modificaciones y las de `opcionesEstadoContrato` con «Suspendido» ("expected [ 'terminado', 'liquidado' ] to deeply equal [ 'suspendido', 'terminado', … ]" y "... is not a function"); en `secop.test.js`, las del estado «Suspendido» que SECOP II no debe aplicar.

- [ ] **Paso 3: Implementar**

Crear `resources/js/components/contratos/SuspensionContrato.jsx`:

```jsx
import React, { useEffect, useRef, useState } from 'react';
import axios from '../../axios';
import { MAXIMO_OBSERVACION, esEdicionDesactualizada, textoFecha } from '../../utils/contratacion';
import {
    CAMPOS_REINICIAR, CAMPOS_SUSPENDER, chipAvisoSuspension, datosReiniciar, datosSuspender, erroresCierre, erroresReiniciar, erroresSuspender,
    formularioReiniciar, formularioSuspender, suspensionAbierta, textoAvisoSuspension, textoReinicioPrevisto, textoVistaPreviaReinicio,
    vistaPreviaReinicio,
} from '../../utils/contratos';
import { fechaHoyBogota } from '../../utils/expedienteContratacion';
import { mensajeApi } from '../../utils/plantillasChecklist';
import { DialogoSecop } from './SecopContrato';
import '../../../css/Plantillas.css';
import '../../../css/Contratacion.css';

// Lo que cambia entre los dos diálogos: la ruta, los campos, sus reglas y sus textos.
const TIPOS = {
    suspender: {
        ruta: 'suspender',
        campos: CAMPOS_SUSPENDER,
        formulario: formularioSuspender,
        datos: datosSuspender,
        errores: erroresSuspender,
        titulo: 'Suspender el contrato',
        boton: 'Suspender',
        explicacion: 'El contrato pasa a «Suspendido» desde la fecha de suspensión. Mientras esté suspendido, la fecha fin no cambia: al reiniciarlo se le suman los días suspendidos. El valor y los avances no cambian. El cambio queda en el historial del contrato y en la pestaña Modificaciones.',
    },
    reiniciar: {
        ruta: 'reiniciar',
        campos: CAMPOS_REINICIAR,
        formulario: formularioReiniciar,
        datos: datosReiniciar,
        errores: erroresReiniciar,
        titulo: 'Reiniciar el contrato',
        boton: 'Reiniciar',
        explicacion: 'El contrato vuelve a «En ejecución» y a su fecha fin se le suman los días calendario que estuvo suspendido. El valor y los avances no cambian. El cambio queda en el historial del contrato.',
    },
};

/**
 * El diálogo de «Suspender» o «Reiniciar» (`tipo`) de un contrato guardado. Suspender: la fecha de
 * suspensión, la prevista de reinicio, el número del acta y el motivo (opcionales). Reiniciar: la
 * fecha de reinicio (la de suspensión solo si el contrato no tiene una abierta), una observación
 * opcional y la vista previa de los días que se suman a la fecha fin. Valida como el servidor (con
 * hoy en Bogotá) y muestra junto a cada campo los errores de un 422.
 *
 * - `onGuardado(data, enviado)`: la respuesta ({ contrato, avisos, resumen? }) y lo que se envió (al
 *   reiniciar, su `fecha_reinicio`); quien lo pinta cierra el diálogo.
 * - `onDesactualizado(error)`: un 409 (otra persona cambió el contrato); quien lo pinta avisa.
 * - `onCerrar()`: Cancelar, la "×", Escape o un clic en el fondo (no mientras se guarda).
 */
export const DialogoSuspension = ({ tipo, contrato, onGuardado, onDesactualizado, onCerrar }) => {
    const config = TIPOS[tipo];
    const hoy = fechaHoyBogota();
    const [formulario, setFormulario] = useState(() => config.formulario(contrato, hoy));
    const [errores, setErrores] = useState({});
    const [general, setGeneral] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [foco, setFoco] = useState(0);
    const cuerpo = useRef(null);
    const abierta = suspensionAbierta(contrato);

    // Tras un error, el foco va al primer campo con error.
    useEffect(() => {
        if (!foco) return;
        const campo = config.campos.find((c) => errores[c]);
        if (campo) cuerpo.current?.querySelector(`[name="${campo}"]`)?.focus();
    }, [foco]);

    const cambiar = (e) => {
        const { name, value } = e.target;
        setErrores((prev) => (prev[name] ? { ...prev, [name]: undefined } : prev));
        setFormulario((prev) => ({ ...prev, [name]: value }));
    };

    const enviar = async (e) => {
        e.preventDefault();
        if (enviando) return;
        const locales = config.errores(formulario, contrato, hoy);
        setErrores(locales);
        setGeneral(null);
        if (Object.keys(locales).length) {
            setFoco((n) => n + 1);
            return;
        }
        setEnviando(true);
        try {
            const enviado = config.datos(formulario, contrato);
            const { data } = await axios.post(`/contratos/${contrato.id}/${config.ruta}`, enviado);
            onGuardado(data, enviado);
        } catch (error) {
            setEnviando(false);
            if (esEdicionDesactualizada(error)) {
                onDesactualizado(error);
                return;
            }
            // Los 422 tienen la misma forma que los de terminar y liquidar: por campo o `mensaje`.
            const delServidor = erroresCierre(error, config.campos);
            if (delServidor) {
                setErrores(delServidor.campos);
                setGeneral(delServidor.general);
                if (Object.keys(delServidor.campos).length) setFoco((n) => n + 1);
                return;
            }
            setGeneral(mensajeApi(error));
        }
    };

    const cerrar = () => { if (!enviando) onCerrar(); };

    // Un campo con su etiqueta, su nota y su error.
    const campo = (nombre, etiqueta, control, { nota = null, ancho = false } = {}) => (
        <div className={`pl-campo${ancho ? ' ct-cierre-ancho' : ''}`}>
            <label htmlFor={`suspension-${nombre}`}>{etiqueta}</label>
            {React.cloneElement(control, {
                id: `suspension-${nombre}`,
                name: nombre,
                value: formulario[nombre] ?? '',
                onChange: cambiar,
                disabled: enviando,
                'aria-invalid': Boolean(errores[nombre]),
                'aria-describedby': [errores[nombre] ? `suspension-${nombre}-error` : null, nota ? `suspension-${nombre}-nota` : null].filter(Boolean).join(' ') || undefined,
            })}
            {nota && <small id={`suspension-${nombre}-nota`} className="ct-secop-nota">{nota}</small>}
            {errores[nombre] && <small id={`suspension-${nombre}-error`} className="ct-error-campo" role="alert">{errores[nombre]}</small>}
        </div>
    );

    const inicio = String(contrato?.fecha_inicio ?? '').slice(0, 10) || undefined;
    const vista = tipo === 'reiniciar' ? textoVistaPreviaReinicio(vistaPreviaReinicio(formulario, contrato)) : null;
    // Mínimo del reinicio: la suspensión abierta o la escrita.
    const minimoReinicio = abierta?.desde || formulario.fecha_suspension || undefined;

    return (
        <DialogoSecop titulo={config.titulo} etiqueta={`Contrato ${contrato?.n_contrato || 'sin número'}`} estrecho onCerrar={cerrar}>
            <form ref={cuerpo} className="ct-cierre-formulario" onSubmit={enviar} noValidate>
                <p className="contrato-nota ct-cierre-explicacion">{config.explicacion}</p>
                {general && <p className="pl-error ct-cierre-explicacion" role="alert">{general}</p>}
                {tipo === 'suspender' && (
                    <>
                        {campo('fecha_suspension', 'Fecha de suspensión *', <input type="date" min={inicio} max={hoy} />)}
                        {campo('fecha_reinicio_prevista', 'Fecha prevista de reinicio (opcional)', (
                            <input type="date" min={formulario.fecha_suspension || undefined} />
                        ), { nota: 'Si pasa y el contrato sigue suspendido, se avisa «Reinicio vencido».' })}
                        {campo('numero_acta', 'Número del acta (opcional)', <input type="text" maxLength={50} placeholder="Por ejemplo, Acta de suspensión No. 1" />, { ancho: true })}
                        {campo('motivo', 'Motivo (opcional)', <textarea rows="3" maxLength={2000} placeholder="Por qué se suspende el contrato" />, { ancho: true })}
                    </>
                )}
                {tipo === 'reiniciar' && (
                    <>
                        {abierta ? (
                            <p className="ct-cierre-saldo">Suspendido desde el {textoFecha(abierta.desde)}.</p>
                        ) : campo('fecha_suspension', 'Fecha de suspensión *', <input type="date" min={inicio} max={hoy} />, {
                            nota: 'El contrato no tiene una suspensión registrada: escribe desde cuándo está suspendido.',
                        })}
                        {campo('fecha_reinicio', 'Fecha de reinicio *', <input type="date" min={minimoReinicio} max={hoy} />)}
                        {vista && <p className="ct-cierre-saldo ct-suspension-vista" aria-live="polite">{vista}</p>}
                        {campo('observacion', 'Observación (opcional)', (
                            <textarea rows="3" maxLength={MAXIMO_OBSERVACION} placeholder="Por ejemplo, el acta de reinicio" />
                        ), { ancho: true })}
                    </>
                )}
                <div className="pl-formulario-acciones ct-cierre-ancho">
                    <button type="button" className="gp-boton" onClick={cerrar} disabled={enviando}>Cancelar</button>
                    <button type="submit" className="gp-boton gp-boton-primario" disabled={enviando}>{enviando ? 'Guardando…' : config.boton}</button>
                </div>
            </form>
        </DialogoSecop>
    );
};

/**
 * La franja de la pestaña de información de un contrato suspendido con su suspensión abierta
 * (`aviso_suspension`): "Suspendido desde X (N días)" y, si la hay, la fecha prevista de reinicio,
 * con el chip "Reinicio vencido" si ya pasó. Nada si no hay aviso.
 */
export const FranjaSuspension = ({ aviso }) => {
    const texto = textoAvisoSuspension(aviso);
    if (!texto) return null;
    const previsto = textoReinicioPrevisto(aviso);
    const chip = chipAvisoSuspension(aviso);
    return (
        <div className={`ct-suspension${chip ? ' ct-suspension-vencida' : ''}`} role="status">
            <strong>{texto}</strong>
            {previsto && <span>{previsto}</span>}
            {chip && <span className="ct-estado ct-estado-coral">{chip}</span>}
            <small>La fecha fin no cambia mientras esté suspendido: al reiniciarlo se le suman los días suspendidos.</small>
        </div>
    );
};
```

En `resources/css/Contratacion.css`, reemplazar (1 de 1):

```css
    .ct-cierre-acciones .gp-boton { flex: 1 1 auto; justify-content: center; }
    .ct-cierre-nota { text-align: left; }
}
```

por:

```css
    .ct-cierre-acciones .gp-boton { flex: 1 1 auto; justify-content: center; }
    .ct-cierre-nota { text-align: left; }
}

/* ---------- Suspensión y reinicio del contrato (fase 7B) ---------- */

/* Franja de la pestaña de información: desde cuándo está suspendido y el reinicio previsto. */
.ct-suspension { display: flex; flex-wrap: wrap; gap: .3rem .9rem; align-items: center; padding: .6rem .85rem; margin: 0 0 1rem; font-size: .88rem; color: var(--gp-texto); background: var(--gp-ambar-suave); border-left: 3px solid var(--gp-ambar); border-radius: var(--gp-radio); }
.ct-suspension small { flex-basis: 100%; font-size: .78rem; color: var(--gp-texto-2); }
.ct-suspension.ct-suspension-vencida { background: var(--gp-coral-suave); border-left-color: var(--gp-coral); }
/* Vista previa de los días que se suman al reiniciar. */
.ct-suspension-vista { padding: .45rem .7rem; color: var(--gp-texto); background: var(--gp-azul-suave); border-radius: var(--gp-radio); }
/* Fila de una suspensión en la pestaña Modificaciones, con sus fechas bajo el tipo. */
.ct-suspension-detalle { display: block; margin-top: .15rem; font-size: .76rem; color: var(--gp-texto-3); }

@media (max-width: 720px) {
    .ct-suspension { flex-direction: column; align-items: flex-start; }
}
```

En `resources/js/components/FichaProyecto.jsx`, reemplazar (1 de 3):

```jsx
import axios from '../axios';
import { datosGenerales, errorDeFicha, esIdProyecto, filaProceso, resumenFormulacion } from '../utils/fichaProyecto';
import { estadoDesdeFicha, estadoNuevoContratoDeProyecto, filaContrato, puedeEditarContratos, rutaContrato } from '../utils/contratosModulo';
import { textoAvisoCierre } from '../utils/contratos';
import { RUTA_CONTRATOS } from '../utils/navegacion';
import ChipsAvisosProceso from './ChipsAvisosProceso';
import ProcesosProyecto from './ProcesosProyecto';
```

por:

```jsx
import axios from '../axios';
import { datosGenerales, errorDeFicha, esIdProyecto, filaProceso, resumenFormulacion } from '../utils/fichaProyecto';
import { estadoDesdeFicha, estadoNuevoContratoDeProyecto, filaContrato, puedeEditarContratos, rutaContrato } from '../utils/contratosModulo';
import { chipAvisoSuspension, textoAvisoCierre, textoAvisoSuspension } from '../utils/contratos';
import { RUTA_CONTRATOS } from '../utils/navegacion';
import ChipsAvisosProceso from './ChipsAvisosProceso';
import ProcesosProyecto from './ProcesosProyecto';
```

En `resources/js/components/FichaProyecto.jsx`, reemplazar (2 de 3):

```jsx
};

const Contratos = ({ proyecto, contratos, edita }) => {
    // Como en la lista del módulo Contratos: con el aviso de datos de cierre que faltan.
    const filas = contratos.map((c) => ({ ...filaContrato(c), avisoCierre: textoAvisoCierre(c.aviso_cierre) }));
    return (
        <Bloque
            titulo="Contratos"
```

por:

```jsx
};

const Contratos = ({ proyecto, contratos, edita }) => {
    // Como en la lista del módulo Contratos: con el aviso de datos de cierre que faltan y el de suspensión.
    const filas = contratos.map((c) => ({
        ...filaContrato(c), avisoCierre: textoAvisoCierre(c.aviso_cierre),
        avisoSuspension: textoAvisoSuspension(c.aviso_suspension), chipSuspension: chipAvisoSuspension(c.aviso_suspension),
    }));
    return (
        <Bloque
            titulo="Contratos"
```

En `resources/js/components/FichaProyecto.jsx`, reemplazar (3 de 3):

```jsx
                                    <td data-etiqueta="Número"><Link to={rutaContrato(f.id)} state={estadoDesdeFicha(proyecto.id)} className="ct-enlace">{f.numero}</Link></td>
                                    <td data-etiqueta="Objeto"><span className="ct-objeto" title={f.objeto}>{f.objeto}</span></td>
                                    <td data-etiqueta="Estado">
                                        <span className={`ct-estado ct-estado-${f.tono}`}>{f.estado}</span>
                                        {f.avisoCierre && <span className="ct-aviso-cierre ct-estado ct-estado-ambar">{f.avisoCierre}</span>}
                                    </td>
                                    <td data-etiqueta="Valor vigente" className="numero">{f.valor}</td>
                                    <td data-etiqueta="Físico" className="numero">{f.avanceFisico}</td>
```

por:

```jsx
                                    <td data-etiqueta="Número"><Link to={rutaContrato(f.id)} state={estadoDesdeFicha(proyecto.id)} className="ct-enlace">{f.numero}</Link></td>
                                    <td data-etiqueta="Objeto"><span className="ct-objeto" title={f.objeto}>{f.objeto}</span></td>
                                    <td data-etiqueta="Estado">
                                        <span className={`ct-estado ct-estado-${f.tono}`} title={f.avisoSuspension ?? undefined}>{f.estado}</span>
                                        {f.avisoCierre && <span className="ct-aviso-cierre ct-estado ct-estado-ambar">{f.avisoCierre}</span>}
                                        {f.chipSuspension && <span className="ct-aviso-cierre ct-estado ct-estado-coral" title={f.avisoSuspension}>{f.chipSuspension}</span>}
                                    </td>
                                    <td data-etiqueta="Valor vigente" className="numero">{f.valor}</td>
                                    <td data-etiqueta="Físico" className="numero">{f.avanceFisico}</td>
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (1 de 17):

```jsx
import React, { useEffect, useId, useRef, useState } from 'react';
import {
    faArrowUpRightFromSquare, faCalculator, faEye, faFile, faFileSignature, faFlagCheckered, faInfo, faLinkSlash, faMagnifyingGlass, faPlus, faRotate,
    faSave, faTimes, faTrash,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
```

por:

```jsx
import React, { useEffect, useId, useRef, useState } from 'react';
import {
    faArrowUpRightFromSquare, faCalculator, faEye, faFile, faFileSignature, faFlagCheckered, faInfo, faLinkSlash, faMagnifyingGlass, faPause, faPlay, faPlus,
    faRotate, faSave, faTimes, faTrash,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (2 de 17):

```jsx
import { avisarError } from '../../utils/avisosPlantillas';
import { datosEstado, esEdicionDesactualizada, textoFecha, textoValor, valorEnEdicion } from '../../utils/contratacion';
import {
    ACTA_VACIA, ACTIVIDAD_VACIA, ANEXO_VACIO, AVANCE_VACIO, MODIFICACION_VACIA, accionesCierre, actividadConAvance, anexosDelContrato,
    anexosParaGuardar, aplicarPrecarga, avanceFinancieroDeActas, avanceFisicoDeActividades, calculoActa, cambiarProyecto, cambiosSinGuardar,
    contratoTrasCierre, contratoVacio, datosActa, datosContrato, datosDelProceso, datosModificacion, erroresGuardarContrato,
    etiquetaTipoModificacion, formularioContrato, muestraBloqueCierre, notaProceso, notaSinEstadoCierre, opcionesEstadoContrato, opcionesProceso,
```

por:

```jsx
import { avisarError } from '../../utils/avisosPlantillas';
import { datosEstado, esEdicionDesactualizada, textoFecha, textoValor, valorEnEdicion } from '../../utils/contratacion';
import {
    ACTA_VACIA, ACTIVIDAD_VACIA, ANEXO_VACIO, AVANCE_VACIO, MODIFICACION_VACIA, TIPOS_MODIFICACION, accionesCierre, accionesSuspension, actividadConAvance,
    anexosDelContrato, confirmacionEliminarModificacion, contratoTrasSuspension, datosEliminarModificacion, detalleSuspension, notaSuspensionCierre,
    puedeEliminarModificacion, suspensionAbierta,
    textoDiasModificacion,
    anexosParaGuardar, aplicarPrecarga, avanceFinancieroDeActas, avanceFisicoDeActividades, calculoActa, cambiarProyecto, cambiosSinGuardar,
    contratoTrasCierre, contratoVacio, datosActa, datosContrato, datosDelProceso, datosModificacion, erroresGuardarContrato,
    etiquetaTipoModificacion, formularioContrato, muestraBloqueCierre, notaProceso, notaSinEstadoCierre, opcionesEstadoContrato, opcionesProceso,
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (3 de 17):

```jsx
    mensajeSecop, referenciaSecop, vincularSecop,
} from '../../utils/secop';
import { BloqueCierre, DialogoCierre } from './CierreContrato';
import { BuscarSecop, DiferenciasSecop } from './SecopContrato';
import '../../../css/Parametros.css';
import '../../../css/Contratacion.css';
```

por:

```jsx
    mensajeSecop, referenciaSecop, vincularSecop,
} from '../../utils/secop';
import { BloqueCierre, DialogoCierre } from './CierreContrato';
import { DialogoSuspension, FranjaSuspension } from './SuspensionContrato';
import { BuscarSecop, DiferenciasSecop } from './SecopContrato';
import '../../../css/Parametros.css';
import '../../../css/Contratacion.css';
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (4 de 17):

```jsx
 *   elegido. Sin la prop, el proyecto es el de `proyecto` o el del contrato, sin selector.
 * - `puedeEditar`: sin permiso, el contrato se ve sin controles para modificarlo.
 *   Con permiso, un contrato guardado ofrece «Terminar» y «Liquidar» (o sus correcciones), cada uno
 *   con su diálogo; quedan deshabilitados mientras el formulario tenga cambios sin guardar.
 *   Con permiso, el formulario ofrece SECOP II: "Buscar en SECOP II" (contrato nuevo o sin ID de
 *   SECOP II) y "Actualizar desde SECOP II" (con ID). Lo que se copia de SECOP II se guarda con el contrato.
 * - `recargar(id)`: vuelve a traer los contratos tras un cambio en las pestañas; resuelve el contrato `id` recargado o null.
 * - `onCambio()` (opcional): tras terminar o liquidar el contrato, para que quien lo pinta (y quien
 *   pinta a este) recargue lo suyo: el contrato tiene una versión nueva.
 * - `onGuardado(contrato)`: tras guardar (con los avisos ya mostrados); quien lo pinta cierra el editor.
 * - `onCancelar()`: "Cancelar" o "Volver a la lista".
```

por:

```jsx
 *   elegido. Sin la prop, el proyecto es el de `proyecto` o el del contrato, sin selector.
 * - `puedeEditar`: sin permiso, el contrato se ve sin controles para modificarlo.
 *   Con permiso, un contrato guardado ofrece «Terminar» y «Liquidar» (o sus correcciones), cada uno
 *   con su diálogo; quedan deshabilitados mientras el formulario tenga cambios sin guardar. Igual
 *   «Suspender» (en ejecución) y «Reiniciar» (suspendido), de la fase 7B.
 *   Con permiso, el formulario ofrece SECOP II: "Buscar en SECOP II" (contrato nuevo o sin ID de
 *   SECOP II) y "Actualizar desde SECOP II" (con ID). Lo que se copia de SECOP II se guarda con el contrato.
 * - `recargar(id)`: vuelve a traer los contratos tras un cambio en las pestañas; resuelve el contrato `id` recargado o null.
 * - `onCambio()` (opcional): tras terminar, liquidar, suspender o reiniciar el contrato (o eliminar
 *   una suspensión), para que quien lo pinta (y quien
 *   pinta a este) recargue lo suyo: el contrato tiene una versión nueva.
 * - `onGuardado(contrato)`: tras guardar (con los avisos ya mostrados); quien lo pinta cierra el editor.
 * - `onCancelar()`: "Cancelar" o "Volver a la lista".
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (5 de 17):

```jsx
    const turnoSecop = useRef(0);
    // Cierre del contrato: el diálogo abierto ('terminar', 'liquidar' o null).
    const [dialogoCierre, setDialogoCierre] = useState(null);
    const idNotaCierre = useId();
    // El formulario del último render, para comparar con SECOP II cuando llega la respuesta.
    const formularioActual = useRef(formContrato);
```

por:

```jsx
    const turnoSecop = useRef(0);
    // Cierre del contrato: el diálogo abierto ('terminar', 'liquidar' o null).
    const [dialogoCierre, setDialogoCierre] = useState(null);
    // Suspensión y reinicio (fase 7B): el diálogo abierto ('suspender', 'reiniciar' o null).
    const [dialogoSuspension, setDialogoSuspension] = useState(null);
    const idNotaCierre = useId();
    // El formulario del último render, para comparar con SECOP II cuando llega la respuesta.
    const formularioActual = useRef(formContrato);
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (6 de 17):

```jsx
        ]);
    };

    // Refrescar los datos del contrato abierto tras registrar o eliminar una modificación
    const refrescarContratoAbierto = async () => {
        const actual = await recargar(formContrato.id);
        if (formContrato.id && actual) {
            setModificaciones(actual.modificaciones || []);
            // Los vigentes recalculados salen del contrato recargado; lo escrito en el
            // formulario se conserva. Registrar una modificación no cambia la versión
            // (updated_at), y se guarda la que tenía al abrirse: así un cambio de otra
            // persona entretanto sigue dando 409.
            setEditingContrato(prev => ({ ...actual, updated_at: prev?.updated_at ?? actual.updated_at }));
        }
    };

    // Manejar cambios en el formulario de modificación (otrosí)
```

por:

```jsx
        ]);
    };

    // ---------- Suspensión y reinicio del contrato (fase 7B) ----------

    // Tras suspender o reiniciar, como tras terminar o liquidar: el contrato abierto toma el estado,
    // la fecha fin (al reiniciar se corre), los avisos y la versión nueva; el select de estado lo
    // sigue; la lista se recarga y, con ella, la pestaña Modificaciones (la suspensión es una fila).
    // Al reiniciar, la suspensión abierta se cierra antes en local con la fecha enviada: si la
    // recarga falla, la pestaña y el contrato no la siguen mostrando abierta.
    const alSuspenderOReiniciar = async (tipo, data, enviado = null) => {
        const { contrato: presentado, avisos = [], resumen = null } = data ?? {};
        setDialogoSuspension(null);
        if (!presentado) return;
        const reinicio = tipo === 'reiniciar' ? enviado : null;
        setEditingContrato(prev => contratoTrasSuspension(prev, presentado, reinicio));
        if (reinicio) setModificaciones(prev => contratoTrasSuspension({ modificaciones: prev }, presentado, reinicio).modificaciones ?? prev);
        setFormContrato(prev => ({ ...prev, estado: presentado.estado ?? prev.estado }));
        if (resumen) setResumenModificacion(resumen);
        avisarCambio();
        let recargado = null;
        let sinRecargar = [];
        try {
            recargado = await recargar?.(editingContrato.id);
        } catch {
            sinRecargar = ['No se pudo actualizar la lista de contratos: ciérrala y vuelve a abrirla para ver lo último.'];
        }
        if (recargado) {
            setModificaciones(recargado.modificaciones || []);
            setEditingContrato(prev => ({ ...prev, modificaciones: recargado.modificaciones || [] }));
        }
        const titulo = tipo === 'suspender' ? 'Contrato suspendido' : 'Contrato reiniciado';
        const texto = tipo === 'reiniciar' && presentado.fecha_fin ? `La fecha fin vigente es el ${textoFecha(presentado.fecha_fin)}.` : undefined;
        const lista = [...avisos, ...sinRecargar];
        await (lista.length
            ? Swal.fire({ icon: 'warning', titleText: `${titulo}, con avisos`, html: htmlErrores('Revisa lo siguiente:', lista) })
            : Swal.fire({ icon: 'success', title: titulo, text: texto }));
    };

    // Otra persona cambió el contrato: se avisa y se recarga la lista. Al eliminar una suspensión
    // (`titulo` propio) también se refresca el contrato abierto y su pestaña Modificaciones: no
    // había cambios sin guardar, así que toma la versión nueva y se puede volver a intentar.
    const alSuspensionDesactualizada = async (error, titulo = null) => {
        const tipo = dialogoSuspension;
        setDialogoSuspension(null);
        const recarga = titulo
            ? refrescarContratoAbierto(true).catch(() => false)
            : recargarTrasCierre(editingContrato?.id);
        await Promise.all([
            recarga,
            Swal.fire({
                icon: 'warning',
                titleText: titulo ?? (tipo === 'reiniciar' ? 'No se guardó el reinicio' : 'No se guardó la suspensión'),
                text: mensajeApi(error),
            }),
        ]);
    };

    // Refrescar los datos del contrato abierto tras registrar o eliminar una modificación.
    // `versionNueva`: eliminar una suspensión sí cambia la versión y puede cambiar el estado.
    // Resuelve true si recargó el contrato abierto, false si `recargar` no lo dio (puede rechazar).
    const refrescarContratoAbierto = async (versionNueva = false) => {
        const actual = await recargar(formContrato.id);
        if (formContrato.id && actual) {
            aplicarContratoRefrescado(actual, versionNueva);
            return true;
        }
        return false;
    };

    // El contrato abierto toma uno recargado (de la lista o de su detalle).
    const aplicarContratoRefrescado = (actual, versionNueva) => {
        setModificaciones(actual.modificaciones || []);
        if (versionNueva) {
            // Se eliminó sin cambios sin guardar: el contrato y su estado son los recargados.
            setEditingContrato(actual);
            setFormContrato(prev => ({ ...prev, estado: actual.estado ?? prev.estado }));
            return;
        }
        // Los vigentes recalculados salen del contrato recargado; lo escrito en el
        // formulario se conserva. Registrar una modificación no cambia la versión
        // (updated_at), y se guarda la que tenía al abrirse: así un cambio de otra
        // persona entretanto sigue dando 409.
        setEditingContrato(prev => ({ ...actual, updated_at: prev?.updated_at ?? actual.updated_at }));
    };

    // Manejar cambios en el formulario de modificación (otrosí)
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (7 de 17):

```jsx
        }
    };

    // Eliminar una modificación (otrosí) y recalcular el contrato
    const eliminarModificacion = async (id) => {
        const result = await Swal.fire({
            icon: 'warning', title: '¿Eliminar esta modificación?',
            text: 'Se recalculará el valor y la fecha fin del contrato.',
            showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
        });
        if (!result.isConfirmed) return;
        try {
            const resp = await axios.post('/eliminarModificacionContrato', { id });
            setResumenModificacion(resp.data.resumen);
            await refrescarContratoAbierto();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error al eliminar la modificación', text: e.response?.data?.error || e.message });
        }
    };

    // Formulario de contrato: proceso elegido, nota R4 y vigentes que difieren de los iniciales.
```

por:

```jsx
        }
    };

    // Eliminar una modificación (otrosí) o una suspensión y recalcular el contrato. Eliminar una
    // suspensión cambia la versión del contrato (y, si estaba abierta, su estado): como los botones
    // de cierre, espera a que no haya cambios sin guardar. Solo se elimina la última (lo dice el 422).
    const eliminarModificacion = async (mod) => {
        const esSuspension = mod.tipo === 'suspension';
        if (esSuspension && cambiosSinGuardar(formContrato, editingContrato, anexos)) {
            Swal.fire({ icon: 'warning', title: NOTA_CAMBIOS_SIN_GUARDAR, text: 'Eliminar una suspensión cambia el contrato: guarda o descarta lo que escribiste antes.' });
            return;
        }
        const { titulo, texto } = confirmacionEliminarModificacion(mod);
        const result = await Swal.fire({
            icon: 'warning', title: titulo, text: texto,
            showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
        });
        if (!result.isConfirmed) return;
        let resp;
        try {
            // Una suspensión lleva la versión del contrato: con una lista vieja, 409.
            resp = await axios.post('/eliminarModificacionContrato', datosEliminarModificacion(mod, editingContrato));
        } catch (e) {
            if (esSuspension && esEdicionDesactualizada(e)) {
                await alSuspensionDesactualizada(e, 'No se eliminó la suspensión');
                return;
            }
            const titulo = esSuspension ? 'No se eliminó la suspensión' : 'Error al eliminar la modificación';
            Swal.fire({ icon: 'error', title: titulo, text: e.response?.data?.error || mensajeApi(e) });
            return;
        }
        // Se eliminó: lo que falle de aquí en adelante es la recarga, no la eliminación.
        setResumenModificacion(resp.data?.resumen);
        if (esSuspension) avisarCambio();
        let enLista = false;
        try {
            enLista = await refrescarContratoAbierto(esSuspension);
        } catch {
            enLista = false;
        }
        if (enLista) return;
        // La lista no lo dio: el contrato abierto (su versión, estado y modificaciones) se toma de
        // su detalle; si tampoco, al menos se quita la fila.
        let detalle = null;
        if (formContrato.id) {
            try {
                detalle = (await axios.get(`/contratos/${formContrato.id}/detalle`)).data ?? null;
            } catch {
                detalle = null;
            }
        }
        if (detalle) aplicarContratoRefrescado(detalle, esSuspension);
        else setModificaciones(prev => prev.filter(m => String(m.id) !== String(mod.id)));
        await Swal.fire({
            icon: 'warning',
            titleText: esSuspension ? 'Suspensión eliminada' : 'Modificación eliminada',
            text: 'No se pudo actualizar la lista de contratos: ciérrala y vuelve a abrirla para ver lo último.',
        });
    };

    // Formulario de contrato: proceso elegido, nota R4 y vigentes que difieren de los iniciales.
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (8 de 17):

```jsx
    // Cierre: qué acciones ofrece el contrato guardado y si el formulario tiene cambios sin guardar
    // (terminar o liquidar cambia la versión: lo escrito ya no se podría guardar).
    const cierre = accionesCierre(editingContrato);
    const ofreceCierre = puedeEditar && (cierre.terminar || cierre.liquidar);
    const conCambios = ofreceCierre && cambiosSinGuardar(formContrato, editingContrato, anexos);
    // Un contrato guardado sin estado válido no se termina hasta elegirlo y guardarlo.
    const notaSinEstado = ofreceCierre ? notaSinEstadoCierre(editingContrato) : null;
    // Con cambios sin guardar, los botones siguen enfocables (aria-disabled) y la nota explica por
    // qué no hacen nada; mientras se guarda, sí quedan deshabilitados.
    const abrirCierre = (tipo) => {
        if (!conCambios) setDialogoCierre(tipo);
    };

    // Propiedades comunes de un control del formulario de contrato: valor, cambio y aviso de error.
    // Sin permiso para editar, el control queda deshabilitado.
```

por:

```jsx
    // Cierre: qué acciones ofrece el contrato guardado y si el formulario tiene cambios sin guardar
    // (terminar o liquidar cambia la versión: lo escrito ya no se podría guardar).
    const cierre = accionesCierre(editingContrato);
    // Suspensión (fase 7B): suspender en ejecución, reiniciar suspendido. Con una suspensión
    // abierta no se termina ni se liquida (la nota lo dice) y el estado no sale de suspendido.
    const suspension = accionesSuspension(editingContrato);
    const conSuspensionAbierta = suspensionAbierta(editingContrato) !== null;
    const ofreceAcciones = puedeEditar && (cierre.terminar || cierre.liquidar || suspension.suspender || suspension.reiniciar);
    const conCambios = ofreceAcciones && cambiosSinGuardar(formContrato, editingContrato, anexos);
    // Un contrato guardado sin estado válido no se termina hasta elegirlo y guardarlo.
    const notaSinEstado = ofreceAcciones ? notaSinEstadoCierre(editingContrato) : null;
    const notaSuspension = puedeEditar ? notaSuspensionCierre(editingContrato) : null;
    // Con cambios sin guardar, los botones siguen enfocables (aria-disabled) y la nota explica por
    // qué no hacen nada; mientras se guarda, sí quedan deshabilitados.
    const abrirCierre = (tipo) => {
        if (!conCambios) setDialogoCierre(tipo);
    };
    const abrirSuspension = (tipo) => {
        if (!conCambios) setDialogoSuspension(tipo);
    };

    // Propiedades comunes de un control del formulario de contrato: valor, cambio y aviso de error.
    // Sin permiso para editar, el control queda deshabilitado.
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (9 de 17):

```jsx
            <div className="contratos-modal-body">
                <div className="contrato-form-header">
                    <h3>{editingContrato ? 'Editar Contrato' : 'Agregar Nuevo Contrato'}</h3>
                    {ofreceCierre && (
                        <div className="ct-cierre-acciones">
                            {cierre.terminar && (
                                <button
                                    type="button"
```

por:

```jsx
            <div className="contratos-modal-body">
                <div className="contrato-form-header">
                    <h3>{editingContrato ? 'Editar Contrato' : 'Agregar Nuevo Contrato'}</h3>
                    {ofreceAcciones && (
                        <div className="ct-cierre-acciones">
                            {suspension.suspender && (
                                <button
                                    type="button"
                                    className="gp-boton"
                                    onClick={() => abrirSuspension('suspender')}
                                    disabled={guardandoContrato}
                                    aria-disabled={conCambios ? 'true' : undefined}
                                    aria-describedby={conCambios ? idNotaCierre : undefined}
                                >
                                    <FontAwesomeIcon icon={faPause} /> Suspender
                                </button>
                            )}
                            {suspension.reiniciar && (
                                <button
                                    type="button"
                                    className="gp-boton"
                                    onClick={() => abrirSuspension('reiniciar')}
                                    disabled={guardandoContrato}
                                    aria-disabled={conCambios ? 'true' : undefined}
                                    aria-describedby={conCambios ? idNotaCierre : undefined}
                                >
                                    <FontAwesomeIcon icon={faPlay} /> Reiniciar
                                </button>
                            )}
                            {cierre.terminar && (
                                <button
                                    type="button"
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (10 de 17):

```jsx
                            )}
                            {conCambios && <small id={idNotaCierre} className="ct-cierre-nota">{NOTA_CAMBIOS_SIN_GUARDAR}</small>}
                            {notaSinEstado && <small className="ct-cierre-nota">{notaSinEstado}</small>}
                        </div>
                    )}
                    <button
```

por:

```jsx
                            )}
                            {conCambios && <small id={idNotaCierre} className="ct-cierre-nota">{NOTA_CAMBIOS_SIN_GUARDAR}</small>}
                            {notaSinEstado && <small className="ct-cierre-nota">{notaSinEstado}</small>}
                            {notaSuspension && <small className="ct-cierre-nota">{notaSuspension}</small>}
                        </div>
                    )}
                    <button
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (11 de 17):

```jsx
                        <p className="contratos-info">
                            Completa los datos del contrato. Los campos marcados con * son obligatorios.
                        </p>
                        {(puedeEditar || referenciaContratoSecop.id) && (
                            <section className="ct-secop" aria-labelledby="secop-titulo">
                                <div className="ct-secop-cabecera">
```

por:

```jsx
                        <p className="contratos-info">
                            Completa los datos del contrato. Los campos marcados con * son obligatorios.
                        </p>
                        {editingContrato && <FranjaSuspension aviso={editingContrato.aviso_suspension} />}
                        {(puedeEditar || referenciaContratoSecop.id) && (
                            <section className="ct-secop" aria-labelledby="secop-titulo">
                                <div className="ct-secop-cabecera">
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (12 de 17):

```jsx
                                {campoContrato('estado', 'Estado *', (
                                    <select {...controlContrato('estado')}>
                                        <option value="">Elige el estado</option>
                                        {opcionesEstadoContrato(editingContrato?.estado, !editingContrato).map(o => (
                                            <option key={o.valor} value={o.valor} disabled={o.deshabilitado}>{o.etiqueta}</option>
                                        ))}
                                    </select>
```

por:

```jsx
                                {campoContrato('estado', 'Estado *', (
                                    <select {...controlContrato('estado')}>
                                        <option value="">Elige el estado</option>
                                        {opcionesEstadoContrato(editingContrato?.estado, !editingContrato, conSuspensionAbierta).map(o => (
                                            <option key={o.valor} value={o.valor} disabled={o.deshabilitado}>{o.etiqueta}</option>
                                        ))}
                                    </select>
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (13 de 17):

```jsx
                {contratoActiveTab === 'modificaciones' && (
                    <div className="contratos-modal-section">
                        <p className="contratos-info">
                            Registre las adiciones y prórrogas (otrosíes) del contrato. El valor y la fecha fin vigentes se recalculan automáticamente desde la base inicial más el historial de modificaciones.
                        </p>

                        {resumenModificacion && (
```

por:

```jsx
                {contratoActiveTab === 'modificaciones' && (
                    <div className="contratos-modal-section">
                        <p className="contratos-info">
                            Registra las adiciones y prórrogas (otrosíes) del contrato. El valor y la fecha fin vigentes se recalculan automáticamente desde la base inicial más el historial de modificaciones.
                            Las suspensiones se registran con «Suspender» y «Reiniciar» y se listan aquí: al reiniciar, sus días se suman a la fecha fin.
                        </p>

                        {resumenModificacion && (
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (14 de 17):

```jsx
                                    <div className="form-group">
                                        <label htmlFor="tipo_modificacion">Tipo *</label>
                                        <select id="tipo_modificacion" name="tipo" value={formModificacion.tipo} onChange={handleModificacionChange}>
                                            <option value="adicion">Adición</option>
                                            <option value="prorroga">Prórroga</option>
                                            <option value="adicion_prorroga">Adición y Prórroga</option>
                                        </select>
                                    </div>
                                    <div className="form-group">
```

por:

```jsx
                                    <div className="form-group">
                                        <label htmlFor="tipo_modificacion">Tipo *</label>
                                        <select id="tipo_modificacion" name="tipo" value={formModificacion.tipo} onChange={handleModificacionChange}>
                                            {TIPOS_MODIFICACION.map(([valor, etiqueta]) => <option key={valor} value={valor}>{etiqueta}</option>)}
                                        </select>
                                    </div>
                                    <div className="form-group">
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (15 de 17):

```jsx
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>N° Otrosí</th>
                                                <th>Tipo</th>
                                                <th>Valor adición</th>
                                                <th>Días prórroga</th>
                                                <th>Fecha</th>
                                                <th>Justificación</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
```

por:

```jsx
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>N° Otrosí o acta</th>
                                                <th>Tipo</th>
                                                <th>Valor adición</th>
                                                <th>Días</th>
                                                <th>Fecha</th>
                                                <th>Justificación o motivo</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (16 de 17):

```jsx
                                            {modificaciones.map(mod => (
                                                <tr key={mod.id}>
                                                    <td>{mod.numero_otrosi || '-'}</td>
                                                    <td>{etiquetaTipoModificacion(mod.tipo)}</td>
                                                    <td>{mod.valor_adicion ? formatoMoneda(Number(mod.valor_adicion)) : '-'}</td>
                                                    <td>{mod.dias_prorroga || '-'}</td>
                                                    <td>{mod.fecha_modificacion || '-'}</td>
                                                    <td>{mod.justificacion || '-'}</td>
                                                    <td>
                                                        <div className="anexo-actions">
                                                            {puedeEditar && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => eliminarModificacion(mod.id)}
                                                                    className="btn-delete-anexo"
                                                                    title="Eliminar modificación"
                                                                >
                                                                    <FontAwesomeIcon icon={faTrash} />
                                                                </button>
```

por:

```jsx
                                            {modificaciones.map(mod => (
                                                <tr key={mod.id}>
                                                    <td>{mod.numero_otrosi || '-'}</td>
                                                    <td>
                                                        {etiquetaTipoModificacion(mod.tipo)}
                                                        {detalleSuspension(mod) && <small className="ct-suspension-detalle">{detalleSuspension(mod)}</small>}
                                                    </td>
                                                    <td>{mod.valor_adicion ? formatoMoneda(Number(mod.valor_adicion)) : '-'}</td>
                                                    <td>{textoDiasModificacion(mod)}</td>
                                                    <td>{mod.fecha_modificacion || '-'}</td>
                                                    <td>{mod.justificacion || '-'}</td>
                                                    <td>
                                                        <div className="anexo-actions">
                                                            {/* De las suspensiones, solo la última se elimina. */}
                                                            {puedeEditar && puedeEliminarModificacion(mod, modificaciones) && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => eliminarModificacion(mod)}
                                                                    className="btn-delete-anexo"
                                                                    title={mod.tipo === 'suspension' ? 'Eliminar suspensión' : 'Eliminar modificación'}
                                                                    aria-label={mod.tipo === 'suspension' ? 'Eliminar suspensión' : 'Eliminar modificación'}
                                                                >
                                                                    <FontAwesomeIcon icon={faTrash} />
                                                                </button>
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (17 de 17):

```jsx
                    onCerrar={() => setDialogoSecop(null)}
                />
            )}
            {dialogoCierre && editingContrato && (
                <DialogoCierre
                    tipo={dialogoCierre}
```

por:

```jsx
                    onCerrar={() => setDialogoSecop(null)}
                />
            )}
            {dialogoSuspension && editingContrato && (
                <DialogoSuspension
                    tipo={dialogoSuspension}
                    contrato={editingContrato}
                    onGuardado={(data, enviado) => alSuspenderOReiniciar(dialogoSuspension, data, enviado)}
                    onDesactualizado={alSuspensionDesactualizada}
                    onCerrar={() => setDialogoSuspension(null)}
                />
            )}
            {dialogoCierre && editingContrato && (
                <DialogoCierre
                    tipo={dialogoCierre}
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (1 de 3):

```jsx
    rutaListaDesde, rutaVistaSecop, textoListaVacia, textoVolverDesde, vistaDeConsulta,
} from '../../utils/contratosModulo';
import { avisoProcesoContrato } from '../../utils/avisosContratacion';
import { textoAvisoCierre } from '../../utils/contratos';
import { RUTA_CONTRATOS } from '../../utils/navegacion';
import { mensajeApi } from '../../utils/plantillasChecklist';
import { mensajeSecop } from '../../utils/secop';
```

por:

```jsx
    rutaListaDesde, rutaVistaSecop, textoListaVacia, textoVolverDesde, vistaDeConsulta,
} from '../../utils/contratosModulo';
import { avisoProcesoContrato } from '../../utils/avisosContratacion';
import { chipAvisoSuspension, textoAvisoCierre, textoAvisoSuspension } from '../../utils/contratos';
import { RUTA_CONTRATOS } from '../../utils/navegacion';
import { mensajeApi } from '../../utils/plantillasChecklist';
import { mensajeSecop } from '../../utils/secop';
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (2 de 3):

```jsx

    const filas = useMemo(() => (contratos ?? []).map((c) => ({
        ...filaContrato(c), avisoProceso: c.aviso_proceso, avisoCierre: textoAvisoCierre(c.aviso_cierre),
    })), [contratos]);
    // Al abrir un contrato se lleva la lista con sus filtros, para volver a ella.
    const estadoNavegacion = { lista: search };
```

por:

```jsx

    const filas = useMemo(() => (contratos ?? []).map((c) => ({
        ...filaContrato(c), avisoProceso: c.aviso_proceso, avisoCierre: textoAvisoCierre(c.aviso_cierre),
        // Fase 7B: desde cuándo está suspendido (como título) y el chip si su reinicio previsto venció.
        avisoSuspension: textoAvisoSuspension(c.aviso_suspension), chipSuspension: chipAvisoSuspension(c.aviso_suspension),
    })), [contratos]);
    // Al abrir un contrato se lleva la lista con sus filtros, para volver a ella.
    const estadoNavegacion = { lista: search };
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (3 de 3):

```jsx
                                        <AvisoProceso aviso={f.avisoProceso} />
                                    </td>
                                    <td data-etiqueta="Estado">
                                        <span className={`ct-estado ct-estado-${f.tono}`}>{f.estado}</span>
                                        {f.avisoCierre && <span className="ct-aviso-cierre ct-estado ct-estado-ambar">{f.avisoCierre}</span>}
                                    </td>
                                    <td data-etiqueta="Valor vigente" className="numero">{f.valor}</td>
                                    <td data-etiqueta="Físico" className="numero">{f.avanceFisico}</td>
```

por:

```jsx
                                        <AvisoProceso aviso={f.avisoProceso} />
                                    </td>
                                    <td data-etiqueta="Estado">
                                        <span className={`ct-estado ct-estado-${f.tono}`} title={f.avisoSuspension ?? undefined}>{f.estado}</span>
                                        {f.avisoCierre && <span className="ct-aviso-cierre ct-estado ct-estado-ambar">{f.avisoCierre}</span>}
                                        {f.chipSuspension && <span className="ct-aviso-cierre ct-estado ct-estado-coral" title={f.avisoSuspension}>{f.chipSuspension}</span>}
                                    </td>
                                    <td data-etiqueta="Valor vigente" className="numero">{f.valor}</td>
                                    <td data-etiqueta="Físico" className="numero">{f.avanceFisico}</td>
```

En `resources/js/components/contratos/ContratosProyecto.jsx`, reemplazar (1 de 2):

```jsx
import Swal from 'sweetalert2';
import axios from '../../axios';
import { avisarError } from '../../utils/avisosPlantillas';
import { etiquetaEstadoContrato, textoAvisoCierre } from '../../utils/contratos';
import { formatoMoneda } from '../../utils/moneda';
import ContratoEditor, { pedirProcesosProyecto, pedirTiposContrato } from './ContratoEditor';
import '../../../css/Parametros.css';
```

por:

```jsx
import Swal from 'sweetalert2';
import axios from '../../axios';
import { avisarError } from '../../utils/avisosPlantillas';
import { chipAvisoSuspension, etiquetaEstadoContrato, textoAvisoCierre, textoAvisoSuspension } from '../../utils/contratos';
import { formatoMoneda } from '../../utils/moneda';
import ContratoEditor, { pedirProcesosProyecto, pedirTiposContrato } from './ContratoEditor';
import '../../../css/Parametros.css';
```

En `resources/js/components/contratos/ContratosProyecto.jsx`, reemplazar (2 de 2):

```jsx
                                                        <td>{contrato.objeto}</td>
                                                        <td>{formatoMoneda(Number(contrato.valor))}</td>
                                                        <td>
                                                            {etiquetaEstadoContrato(contrato.estado)}
                                                            {textoAvisoCierre(contrato.aviso_cierre) && (
                                                                <span className="ct-aviso-cierre ct-estado ct-estado-ambar">{textoAvisoCierre(contrato.aviso_cierre)}</span>
                                                            )}
                                                        </td>
                                                        <td>
                                                            <span className="anexos-count">
```

por:

```jsx
                                                        <td>{contrato.objeto}</td>
                                                        <td>{formatoMoneda(Number(contrato.valor))}</td>
                                                        <td>
                                                            <span title={textoAvisoSuspension(contrato.aviso_suspension) ?? undefined}>{etiquetaEstadoContrato(contrato.estado)}</span>
                                                            {textoAvisoCierre(contrato.aviso_cierre) && (
                                                                <span className="ct-aviso-cierre ct-estado ct-estado-ambar">{textoAvisoCierre(contrato.aviso_cierre)}</span>
                                                            )}
                                                            {chipAvisoSuspension(contrato.aviso_suspension) && (
                                                                <span className="ct-aviso-cierre ct-estado ct-estado-coral" title={textoAvisoSuspension(contrato.aviso_suspension)}>
                                                                    {chipAvisoSuspension(contrato.aviso_suspension)}
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td>
                                                            <span className="anexos-count">
```

En `resources/js/utils/contratos.js`, reemplazar (1 de 6):

```js
// Funciones puras, probadas en contratos.test.js. Las reglas las valida el backend
// (ContratoController y ContratosService); aquí se adelantan los mismos mensajes.

import { MAXIMO_OBSERVACION, erroresPorCampo, etiquetaEstadoProceso, etiquetaPlataforma, numeroProceso, valorDesdeApi, valorParaApi } from './contratacion';
import { numeroDeMoneda } from './moneda';

// Los valores son las constantes de Contrato (contratos.test.js lo comprueba).
```

por:

```js
// Funciones puras, probadas en contratos.test.js. Las reglas las valida el backend
// (ContratoController y ContratosService); aquí se adelantan los mismos mensajes.

import {
    MAXIMO_OBSERVACION, erroresPorCampo, etiquetaEstadoProceso, etiquetaPlataforma, numeroProceso, textoFecha, valorDesdeApi, valorParaApi,
} from './contratacion';
import { numeroDeMoneda } from './moneda';

// Los valores son las constantes de Contrato (contratos.test.js lo comprueba).
```

En `resources/js/utils/contratos.js`, reemplazar (2 de 6):

```js
    justificacion: formulario.justificacion,
});

/** La etiqueta del tipo de modificación. */
export const etiquetaTipoModificacion = (tipo) => {
    if (tipo === 'adicion') return 'Adición';
    if (tipo === 'prorroga') return 'Prórroga';
    return 'Adición y Prórroga';
};
```

por:

```js
    justificacion: formulario.justificacion,
});

/**
 * Los tipos que ofrece el formulario de la pestaña Modificaciones. Las suspensiones no: las
 * registran «Suspender» y «Reiniciar» (el servidor las rechaza en /guardarModificacionContrato).
 */
export const TIPOS_MODIFICACION = [
    ['adicion', 'Adición'],
    ['prorroga', 'Prórroga'],
    ['adicion_prorroga', 'Adición y Prórroga'],
];

/** La etiqueta del tipo de modificación (también de una suspensión, en la tabla). */
export const etiquetaTipoModificacion = (tipo) => {
    if (tipo === 'adicion') return 'Adición';
    if (tipo === 'prorroga') return 'Prórroga';
    if (tipo === 'suspension') return 'Suspensión';
    return 'Adición y Prórroga';
};
```

En `resources/js/utils/contratos.js`, reemplazar (3 de 6):

```js
const ESTADOS_DE_CIERRE = ['terminado', 'liquidado'];
// Estados desde los que se termina un contrato (CierreContratos::TERMINABLES).
const TERMINABLES = ['celebrado', 'en_ejecucion', 'suspendido', 'terminado'];
const ACCION_DE_ESTADO = { terminado: 'Terminar', liquidado: 'Liquidar' };

/** Los campos de los diálogos de terminar y liquidar, con los nombres de la API. */
export const CAMPOS_TERMINAR = ['fecha_terminacion_real', 'observacion'];
```

por:

```js
const ESTADOS_DE_CIERRE = ['terminado', 'liquidado'];
// Estados desde los que se termina un contrato (CierreContratos::TERMINABLES).
const TERMINABLES = ['celebrado', 'en_ejecucion', 'suspendido', 'terminado'];
const ACCION_DE_ESTADO = { suspendido: 'Suspender', terminado: 'Terminar', liquidado: 'Liquidar' };
// Estados a los que, al editar, solo se llega con su acción (los de cierre y suspendido, fase 7B).
const ESTADOS_POR_ACCION = ['suspendido', ...ESTADOS_DE_CIERRE];

/** Los campos de los diálogos de terminar y liquidar, con los nombres de la API. */
export const CAMPOS_TERMINAR = ['fecha_terminacion_real', 'observacion'];
```

En `resources/js/utils/contratos.js`, reemplazar (4 de 6):

```js
/**
 * Qué acciones de cierre ofrece un contrato guardado: terminar (celebrado, en ejecución,
 * suspendido o terminado, para corregir la fecha) y liquidar (cualquier estado salvo anulado;
 * liquidado, para completar o corregir), con la etiqueta de cada botón.
 */
export const accionesCierre = (contrato) => {
    const existe = !vacio(contrato?.id);
    const estado = contrato?.estado ?? null;
    return {
        terminar: existe && TERMINABLES.includes(estado),
```

por:

```js
/**
 * Qué acciones de cierre ofrece un contrato guardado: terminar (celebrado, en ejecución,
 * suspendido o terminado, para corregir la fecha) y liquidar (cualquier estado salvo anulado;
 * liquidado, para completar o corregir), con la etiqueta de cada botón. Con una suspensión
 * abierta, ninguna: primero se reinicia (fase 7B; lo explica notaSuspensionCierre).
 */
export const accionesCierre = (contrato) => {
    const existe = !vacio(contrato?.id) && suspensionAbierta(contrato) === null;
    const estado = contrato?.estado ?? null;
    return {
        terminar: existe && TERMINABLES.includes(estado),
```

En `resources/js/utils/contratos.js`, reemplazar (5 de 6):

```js
);

/**
 * Las opciones del select de estado. Al editar, terminado y liquidado se deshabilitan (se llega a
 * ellos con «Terminar» y «Liquidar») salvo el estado guardado; un contrato nuevo puede llegar en
 * cualquiera (p. ej. uno de SECOP II ya liquidado).
 */
export const opcionesEstadoContrato = (estadoGuardado, esNuevo) => ESTADOS_CONTRATO.map(([valor, etiqueta]) => {
    const deshabilitado = !esNuevo && ESTADOS_DE_CIERRE.includes(valor) && valor !== estadoGuardado;
    return { valor, etiqueta: deshabilitado ? `${etiqueta} (usa «${ACCION_DE_ESTADO[valor]}»)` : etiqueta, deshabilitado };
});

/** El formulario del diálogo de terminar: la fecha guardada, si la hay. */
```

por:

```js
);

/**
 * Las opciones del select de estado. Al editar, suspendido, terminado y liquidado se deshabilitan
 * (se llega a ellos con «Suspender», «Terminar» y «Liquidar») salvo el estado guardado; un
 * contrato nuevo puede llegar en cualquiera (p. ej. uno de SECOP II ya liquidado o suspendido).
 * Con una suspensión abierta (`conSuspensionAbierta`), el estado no sale de suspendido: se usa
 * «Reiniciar» (y terminado y liquidado dicen "reinicia primero").
 */
export const opcionesEstadoContrato = (estadoGuardado, esNuevo, conSuspensionAbierta = false) => ESTADOS_CONTRATO.map(([valor, etiqueta]) => {
    if (esNuevo) return { valor, etiqueta, deshabilitado: false };
    // Con la suspensión abierta, primero se reinicia: también antes de terminar o liquidar.
    if (conSuspensionAbierta && valor !== 'suspendido') {
        return { valor, etiqueta: valor === 'en_ejecucion' ? `${etiqueta} (usa «Reiniciar»)` : `${etiqueta} (reinicia primero)`, deshabilitado: true };
    }
    if (ESTADOS_POR_ACCION.includes(valor) && valor !== estadoGuardado) {
        return { valor, etiqueta: `${etiqueta} (usa «${ACCION_DE_ESTADO[valor]}»)`, deshabilitado: true };
    }
    return { valor, etiqueta, deshabilitado: false };
});

/** El formulario del diálogo de terminar: la fecha guardada, si la hay. */
```

En `resources/js/utils/contratos.js`, reemplazar (6 de 6):

```js
    return (anexos ?? []).some((anexo) => !guardados.has(String(anexo.id)));
};

/**
 * Si SECOP II no puede cambiar el estado del contrato guardado: propone terminado o liquidado y
 * el contrato no está en ese estado (a esos estados se llega con «Terminar» y «Liquidar»). Lo
 * decide `esNuevo`, como en opcionesEstadoContrato: un contrato nuevo sí lo toma; uno guardado,
 * aunque su estado esté vacío, no.
 */
export const estadoCierreBloqueado = (estadoSecop, estadoGuardado, esNuevo = false) => (
    !esNuevo && ESTADOS_DE_CIERRE.includes(estadoSecop) && estadoSecop !== estadoGuardado
);

/**
 * El aviso de "Actualizar desde SECOP II" cuando propone terminado o liquidado y el contrato
 * guardado no lo está: qué acción usar. null si no aplica (contrato nuevo incluido), o si el
 * contrato ya está liquidado y SECOP II lo ve terminado (va detrás).
 */
export const avisoEstadoCierreSecop = (estadoSecop, estadoGuardado, esNuevo = false) => {
    if (!estadoCierreBloqueado(estadoSecop, estadoGuardado, esNuevo)) return null;
    if (estadoSecop === 'liquidado') return 'SECOP II dice «Liquidado»: usa «Liquidar» para registrarlo con sus fechas y valor.';
    if (estadoGuardado === 'liquidado') return null;
    return 'SECOP II dice «Terminado»: usa «Terminar» para registrarlo con su fecha de terminación.';
};
```

por:

```js
    return (anexos ?? []).some((anexo) => !guardados.has(String(anexo.id)));
};

// SECOP II quiere sacar de suspendido un contrato con una suspensión abierta (eso lo hace «Reiniciar»).
const saleDeSuspensionAbierta = (estadoSecop, estadoGuardado, conSuspensionAbierta) => (
    conSuspensionAbierta && estadoGuardado === 'suspendido' && !vacio(estadoSecop) && estadoSecop !== 'suspendido'
);

/**
 * Si SECOP II no puede cambiar el estado del contrato guardado: propone terminado, liquidado o
 * suspendido y el contrato no está en ese estado (a esos estados se llega con «Terminar»,
 * «Liquidar» y «Suspender»), o propone otro estado y el contrato tiene una suspensión abierta
 * (`conSuspensionAbierta`: se sale con «Reiniciar»). Lo decide `esNuevo`, como en
 * opcionesEstadoContrato: un contrato nuevo sí lo toma; uno guardado, aunque su estado esté vacío, no.
 */
export const estadoCierreBloqueado = (estadoSecop, estadoGuardado, esNuevo = false, conSuspensionAbierta = false) => (
    !esNuevo && (
        (ESTADOS_POR_ACCION.includes(estadoSecop) && estadoSecop !== estadoGuardado)
        || saleDeSuspensionAbierta(estadoSecop, estadoGuardado, conSuspensionAbierta)
    )
);

/**
 * El aviso de "Actualizar desde SECOP II" cuando su estado no se copia (estadoCierreBloqueado):
 * qué acción usar. null si no aplica (contrato nuevo incluido), o si SECOP II va detrás: un
 * contrato liquidado que SECOP II ve terminado, o uno terminado o liquidado que ve suspendido.
 */
export const avisoEstadoCierreSecop = (estadoSecop, estadoGuardado, esNuevo = false, conSuspensionAbierta = false) => {
    if (!estadoCierreBloqueado(estadoSecop, estadoGuardado, esNuevo, conSuspensionAbierta)) return null;
    if (saleDeSuspensionAbierta(estadoSecop, estadoGuardado, conSuspensionAbierta)) {
        return `SECOP II dice «${etiquetaEstadoContrato(estadoSecop)}»: usa «Reiniciar» para registrar el reinicio.`;
    }
    if (estadoSecop === 'suspendido') {
        return ESTADOS_DE_CIERRE.includes(estadoGuardado) ? null : 'SECOP II dice «Suspendido»: usa «Suspender» para registrarlo con su fecha.';
    }
    if (estadoSecop === 'liquidado') return 'SECOP II dice «Liquidado»: usa «Liquidar» para registrarlo con sus fechas y valor.';
    if (estadoGuardado === 'liquidado') return null;
    return 'SECOP II dice «Terminado»: usa «Terminar» para registrarlo con su fecha de terminación.';
};

// ---------- Suspensión y reinicio del contrato (fase 7B) ----------

/** Los campos de los diálogos de suspender y reiniciar, con los nombres de la API. */
export const CAMPOS_SUSPENDER = ['fecha_suspension', 'fecha_reinicio_prevista', 'numero_acta', 'motivo'];
export const CAMPOS_REINICIAR = ['fecha_suspension', 'fecha_reinicio', 'observacion'];

// Los mensajes de SuspensionContratos y ContratoController.
const MAXIMO_ACTA = 50;
const MAXIMO_MOTIVO = 2000;
const MENSAJES_SUSPENSION = {
    sinSuspension: 'Escribe la fecha de suspensión.',
    suspensionFutura: 'La fecha de suspensión no puede ser posterior a hoy.',
    suspensionAntesDeInicio: 'La fecha de suspensión no puede ser anterior a la de inicio.',
    suspensionAntesDeReinicio: 'La fecha de suspensión no puede ser anterior al último reinicio.',
    previstaAnterior: 'La fecha prevista de reinicio no puede ser anterior a la de suspensión.',
    sinReinicio: 'Escribe la fecha de reinicio.',
    reinicioFuturo: 'La fecha de reinicio no puede ser posterior a hoy.',
    reinicioAnterior: 'La fecha de reinicio no puede ser anterior a la de suspensión.',
    actaLarga: `El número del acta admite hasta ${MAXIMO_ACTA} caracteres.`,
    motivoLargo: `El motivo admite hasta ${MAXIMO_MOTIVO} caracteres.`,
    cierreConSuspension: 'Reinicia el contrato antes de terminarlo o liquidarlo.',
};

const esSuspension = (modificacion) => modificacion?.tipo === 'suspension';
const diasTexto = (dias) => `${dias} ${Number(dias) === 1 ? 'día' : 'días'}`;
const textoOpcional = (valor) => (vacio(valor) ? null : String(valor).trim());

// Una fecha año-mes-día (con o sin hora) como milisegundos UTC: así los días son exactos, sin
// cambios de hora. null si no es una fecha así.
const diaUtc = (fecha) => {
    const partes = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(fecha ?? ''));
    return partes ? Date.UTC(Number(partes[1]), Number(partes[2]) - 1, Number(partes[3])) : null;
};

const MS_POR_DIA = 24 * 60 * 60 * 1000;

/** Los días calendario de `desde` a `hasta` (año-mes-día; negativo si `hasta` es anterior); null si falta alguna. */
export const diasEntre = (desde, hasta) => {
    const inicio = diaUtc(desde);
    const fin = diaUtc(hasta);
    return inicio === null || fin === null ? null : Math.round((fin - inicio) / MS_POR_DIA);
};

/** La fecha (año-mes-día) `dias` días calendario después de `fecha`; null si no es una fecha. */
export const sumarDias = (fecha, dias) => {
    const inicio = diaUtc(fecha);
    return inicio === null ? null : new Date(inicio + Number(dias) * MS_POR_DIA).toISOString().slice(0, 10);
};

/**
 * La suspensión abierta del contrato ({ desde, reinicio_previsto }, año-mes-día): la de su
 * `aviso_suspension` cuando el contrato trae esa clave (la API la da siempre: null sin suspensión
 * abierta), aunque sus modificaciones locales digan otra cosa (pueden ir atrasadas si no se pudo
 * recargar). Solo sin la clave, la fila sin fecha de reinicio de sus modificaciones. null si no tiene.
 */
export const suspensionAbierta = (contrato) => {
    if (contrato && Object.prototype.hasOwnProperty.call(contrato, 'aviso_suspension') && contrato.aviso_suspension !== undefined) {
        const aviso = contrato.aviso_suspension;
        return aviso?.desde ? { desde: comoFecha(aviso.desde), reinicio_previsto: comoFecha(aviso.reinicio_previsto) || null } : null;
    }
    const fila = (contrato?.modificaciones ?? []).find((m) => esSuspension(m) && vacio(m.fecha_reinicio));
    return fila ? { desde: comoFecha(fila.fecha_modificacion), reinicio_previsto: comoFecha(fila.fecha_reinicio_prevista) || null } : null;
};

/**
 * Qué acciones de suspensión ofrece un contrato guardado: suspender (en ejecución y sin una
 * suspensión abierta) y reiniciar (suspendido, la tenga o no: sin ella, el diálogo pide la fecha de
 * suspensión).
 */
export const accionesSuspension = (contrato) => {
    const existe = !vacio(contrato?.id);
    return {
        suspender: existe && contrato.estado === 'en_ejecucion' && suspensionAbierta(contrato) === null,
        reiniciar: existe && contrato.estado === 'suspendido',
    };
};

/** La nota junto a los botones de un contrato con una suspensión abierta (no se termina ni se liquida); null si no la tiene. */
export const notaSuspensionCierre = (contrato) => (
    !vacio(contrato?.id) && suspensionAbierta(contrato) !== null ? MENSAJES_SUSPENSION.cierreConSuspension : null
);

/** El texto del aviso de suspensión (`aviso_suspension` de la API): "Suspendido desde 05/11/2026 (14 días)"; null sin aviso. */
export const textoAvisoSuspension = (aviso) => {
    if (!aviso?.desde) return null;
    const dias = !vacio(aviso.dias) && Number.isFinite(Number(aviso.dias)) ? ` (${diasTexto(Number(aviso.dias))})` : '';
    return `Suspendido desde ${textoFecha(aviso.desde)}${dias}`;
};

/** El chip de las listas y del contrato: "Reinicio vencido" si el reinicio previsto ya pasó; si no, null. */
export const chipAvisoSuspension = (aviso) => (aviso?.desde && aviso.vencido ? 'Reinicio vencido' : null);

/** "Reinicio previsto el 10/11/2026", con ": vencido" si ya pasó; null sin fecha prevista. */
export const textoReinicioPrevisto = (aviso) => {
    if (!aviso?.desde || vacio(aviso.reinicio_previsto)) return null;
    return `Reinicio previsto el ${textoFecha(aviso.reinicio_previsto)}${aviso.vencido ? ': vencido' : ''}`;
};

/** El formulario del diálogo de suspender: la fecha de suspensión empieza en `hoy`. */
export const formularioSuspender = (contrato, hoy) => ({
    fecha_suspension: comoFecha(hoy),
    fecha_reinicio_prevista: '',
    numero_acta: '',
    motivo: '',
});

/** El formulario del diálogo de reiniciar: la fecha de reinicio empieza en `hoy`. */
export const formularioReiniciar = (contrato, hoy) => ({
    fecha_suspension: '',
    fecha_reinicio: comoFecha(hoy),
    observacion: '',
});

/** Lo que espera POST /contratos/{id}/suspender, con la versión (`updated_at`) del contrato abierto. */
export const datosSuspender = (formulario, contrato) => ({
    fecha_suspension: comoFecha(formulario.fecha_suspension),
    fecha_reinicio_prevista: comoFecha(formulario.fecha_reinicio_prevista) || null,
    numero_acta: textoOpcional(formulario.numero_acta),
    motivo: textoOpcional(formulario.motivo),
    actualizado: contrato?.updated_at ?? null,
});

/**
 * Lo que espera POST /contratos/{id}/reiniciar. La fecha de suspensión va solo si el contrato no
 * tiene una suspensión abierta (entonces el servidor la crea ya cerrada).
 */
export const datosReiniciar = (formulario, contrato) => ({
    fecha_reinicio: comoFecha(formulario.fecha_reinicio),
    ...(suspensionAbierta(contrato) === null ? { fecha_suspension: comoFecha(formulario.fecha_suspension) || null } : {}),
    observacion: observacionParaApi(formulario),
    actualizado: contrato?.updated_at ?? null,
});

// El último reinicio de las suspensiones cerradas del contrato (año-mes-día), o null.
const ultimoReinicio = (contrato) => (contrato?.modificaciones ?? [])
    .filter((m) => esSuspension(m) && !vacio(m.fecha_reinicio))
    .map((m) => comoFecha(m.fecha_reinicio))
    .reduce((ultimo, fecha) => (ultimo === null || fecha > ultimo ? fecha : ultimo), null);

// La fecha de suspensión: obligatoria, no posterior a hoy, ni anterior a la de inicio ni al último reinicio.
const errorSuspension = (fecha, contrato, hoy) => {
    if (!fecha) return MENSAJES_SUSPENSION.sinSuspension;
    if (fecha > hoy) return MENSAJES_SUSPENSION.suspensionFutura;
    const inicio = comoFecha(contrato?.fecha_inicio);
    if (inicio && fecha < inicio) return MENSAJES_SUSPENSION.suspensionAntesDeInicio;
    const reinicio = ultimoReinicio(contrato);
    if (reinicio && fecha < reinicio) return MENSAJES_SUSPENSION.suspensionAntesDeReinicio;
    return null;
};

const largo = (valor) => String(valor ?? '').trim().length;

/**
 * Errores del diálogo de suspender por campo, con los mensajes del servidor; {} si se puede
 * enviar. `hoy` es el día (año-mes-día) en la zona de contratación. La prevista puede ser futura.
 */
export const erroresSuspender = (formulario, contrato, hoy) => {
    const suspension = comoFecha(formulario?.fecha_suspension);
    const prevista = comoFecha(formulario?.fecha_reinicio_prevista);
    return sinNulos({
        fecha_suspension: errorSuspension(suspension, contrato, hoy),
        fecha_reinicio_prevista: suspension && prevista && prevista < suspension ? MENSAJES_SUSPENSION.previstaAnterior : null,
        numero_acta: largo(formulario?.numero_acta) > MAXIMO_ACTA ? MENSAJES_SUSPENSION.actaLarga : null,
        motivo: largo(formulario?.motivo) > MAXIMO_MOTIVO ? MENSAJES_SUSPENSION.motivoLargo : null,
    });
};

// La fecha de suspensión con que se cuenta al reiniciar: la de la abierta o, sin ella, la escrita.
const suspensionDelReinicio = (formulario, contrato) => suspensionAbierta(contrato)?.desde ?? comoFecha(formulario?.fecha_suspension);

/**
 * Errores del diálogo de reiniciar por campo; {} si se puede enviar. Sin suspensión abierta, la
 * fecha de suspensión es obligatoria y sigue las reglas de suspender. La de reinicio no es
 * posterior a `hoy` ni anterior a la de suspensión (el mismo día vale: suma 0 días).
 */
export const erroresReiniciar = (formulario, contrato, hoy) => {
    const abierta = suspensionAbierta(contrato);
    const suspension = suspensionDelReinicio(formulario, contrato);
    const reinicio = comoFecha(formulario?.fecha_reinicio);
    let errorReinicio = null;
    if (!reinicio) errorReinicio = MENSAJES_SUSPENSION.sinReinicio;
    else if (reinicio > hoy) errorReinicio = MENSAJES_SUSPENSION.reinicioFuturo;
    else if (suspension && reinicio < suspension) errorReinicio = MENSAJES_SUSPENSION.reinicioAnterior;
    return sinNulos({
        fecha_suspension: abierta ? null : errorSuspension(suspension, contrato, hoy),
        fecha_reinicio: errorReinicio,
        observacion: errorObservacion(formulario),
    });
};

/**
 * La vista previa del reinicio: los días calendario suspendidos (reinicio − suspensión) y la fecha
 * fin vigente antes y después de sumarlos, como hará el servidor al recalcular. Sin fecha fin,
 * ambas son null. null si faltan fechas o el reinicio es anterior a la suspensión.
 */
export const vistaPreviaReinicio = (formulario, contrato) => {
    const dias = diasEntre(suspensionDelReinicio(formulario, contrato), formulario?.fecha_reinicio);
    if (dias === null || dias < 0) return null;
    const fechaFinActual = comoFecha(contrato?.fecha_fin) || null;
    return { dias, fechaFinActual, fechaFinNueva: fechaFinActual ? sumarDias(fechaFinActual, dias) : null };
};

/** "Se suman 14 días: la fecha fin pasa de 30/06/2026 a 14/07/2026."; null sin vista previa. */
export const textoVistaPreviaReinicio = (vista) => {
    if (!vista) return null;
    if (vista.dias === 0) return 'No se suman días: la fecha fin no cambia.';
    const suman = `${vista.dias === 1 ? 'Se suma' : 'Se suman'} ${diasTexto(vista.dias)}`;
    return vista.fechaFinActual
        ? `${suman}: la fecha fin pasa de ${textoFecha(vista.fechaFinActual)} a ${textoFecha(vista.fechaFinNueva)}.`
        : `${suman} a la fecha fin del contrato.`;
};

/**
 * El contrato abierto tras suspenderlo o reiniciarlo: del contrato presentado que responde la API
 * toma el estado, la fecha fin vigente (al reiniciar se corre), sus avisos y la versión nueva; lo
 * demás (anexos, actas…) se conserva hasta recargar. Al reiniciar (`reinicio.fecha_reinicio`, la
 * que se envió) y quedar fuera de suspendido, la suspensión abierta de sus modificaciones se cierra
 * en local con esa fecha y sus días (como los calcula el servidor): así, si la recarga falla, el
 * contrato no queda con una fila abierta que ya no lo está.
 */
export const contratoTrasSuspension = (abierto, presentado, reinicio = null) => {
    const tras = {
        ...abierto,
        estado: presentado.estado,
        fecha_fin: presentado.fecha_fin,
        aviso_suspension: presentado.aviso_suspension ?? null,
        aviso_cierre: presentado.aviso_cierre ?? null,
        updated_at: presentado.updated_at,
    };
    const fecha = comoFecha(reinicio?.fecha_reinicio);
    if (!fecha || presentado.estado === 'suspendido' || !Array.isArray(abierto?.modificaciones)) return tras;
    tras.modificaciones = abierto.modificaciones.map((m) => (esSuspension(m) && vacio(m.fecha_reinicio)
        ? { ...m, fecha_reinicio: fecha, dias_prorroga: diasEntre(m.fecha_modificacion, fecha) }
        : m));
    return tras;
};

/**
 * Lo que espera POST /eliminarModificacionContrato: el id y, para una suspensión (cambia el estado
 * y la fecha fin), la versión (`updated_at`) del contrato abierto, que el servidor exige (409).
 */
export const datosEliminarModificacion = (modificacion, contrato) => (esSuspension(modificacion)
    ? { id: modificacion.id, actualizado: contrato?.updated_at ?? null }
    : { id: modificacion.id });

// La suspensión más reciente de la lista, como la elige el servidor: por fecha y luego por id.
const ultimaSuspension = (modificaciones) => (modificaciones ?? []).filter(esSuspension).reduce((ultima, m) => {
    if (ultima === null) return m;
    const fecha = comoFecha(m.fecha_modificacion);
    const fechaUltima = comoFecha(ultima.fecha_modificacion);
    if (fecha !== fechaUltima) return fecha > fechaUltima ? m : ultima;
    return Number(m.id) > Number(ultima.id) ? m : ultima;
}, null);

/** Si la pestaña Modificaciones ofrece eliminar la fila: siempre, salvo una suspensión que no es la última. */
export const puedeEliminarModificacion = (modificacion, modificaciones) => {
    if (!esSuspension(modificacion)) return true;
    const ultima = ultimaSuspension(modificaciones);
    return ultima === null || String(ultima.id) === String(modificacion.id);
};

/**
 * El detalle de una suspensión en la tabla de modificaciones: "Del 05/11/2026 al 19/11/2026:
 * 14 días" o, abierta, "Desde el 05/11/2026: abierta" con su reinicio previsto. null si no es una suspensión.
 */
export const detalleSuspension = (modificacion) => {
    if (!esSuspension(modificacion)) return null;
    const desde = textoFecha(modificacion.fecha_modificacion);
    if (vacio(modificacion.fecha_reinicio)) {
        const prevista = vacio(modificacion.fecha_reinicio_prevista) ? '' : `, reinicio previsto el ${textoFecha(modificacion.fecha_reinicio_prevista)}`;
        return `Desde el ${desde}: abierta${prevista}`;
    }
    return `Del ${desde} al ${textoFecha(modificacion.fecha_reinicio)}: ${diasTexto(Number(modificacion.dias_prorroga) || 0)}`;
};

/** La columna de días de la tabla: "Abierta" o los días de una suspensión (también 0), los de una prórroga o "-". */
export const textoDiasModificacion = (modificacion) => {
    if (esSuspension(modificacion)) {
        return vacio(modificacion.fecha_reinicio) ? 'Abierta' : String(Number(modificacion.dias_prorroga) || 0);
    }
    // Como antes: sin días (o 0) en una adición o prórroga, "-".
    return Number(modificacion?.dias_prorroga) ? String(modificacion.dias_prorroga) : '-';
};

/** El título y el texto de la confirmación de eliminar una modificación o una suspensión. */
export const confirmacionEliminarModificacion = (modificacion) => {
    if (!esSuspension(modificacion)) {
        return { titulo: '¿Eliminar esta modificación?', texto: 'Se recalculará el valor y la fecha fin del contrato.' };
    }
    const efecto = vacio(modificacion.fecha_reinicio)
        ? 'Como está abierta, el contrato vuelve a «En ejecución».'
        : 'La fecha fin se recalcula sin sus días.';
    return {
        titulo: '¿Eliminar esta suspensión?',
        texto: `Solo se puede eliminar la última suspensión. ${efecto} El cambio queda en el historial del contrato.`,
    };
};
```

En `resources/js/utils/secop.js`, reemplazar (1 de 6):

```js
// formato del formulario, con las mismas conversiones que formularioContrato.

import { esEnlaceWeb, textoFecha, textoValor, valorDesdeApi, valorParaApi } from './contratacion';
import { ESTADOS_CONTRATO, avisoEstadoCierreSecop, estadoCierreBloqueado, etiquetaEstadoContrato } from './contratos';
import { rutaContrato } from './contratosModulo';
import { mensajeApi } from './plantillasChecklist';
```

por:

```js
// formato del formulario, con las mismas conversiones que formularioContrato.

import { esEnlaceWeb, textoFecha, textoValor, valorDesdeApi, valorParaApi } from './contratacion';
import { ESTADOS_CONTRATO, avisoEstadoCierreSecop, estadoCierreBloqueado, etiquetaEstadoContrato, suspensionAbierta } from './contratos';
import { rutaContrato } from './contratosModulo';
import { mensajeApi } from './plantillasChecklist';
```

En `resources/js/utils/secop.js`, reemplazar (2 de 6):

```js
const vacio = (valor) => valor === null || valor === undefined || String(valor).trim() === '';
// Sin contrato guardado (o sin id), el formulario es de un contrato nuevo.
const esNuevo = (contrato) => vacio(contrato?.id);
const texto = (valor) => (vacio(valor) ? '' : String(valor).trim());

/**
```

por:

```js
const vacio = (valor) => valor === null || valor === undefined || String(valor).trim() === '';
// Sin contrato guardado (o sin id), el formulario es de un contrato nuevo.
const esNuevo = (contrato) => vacio(contrato?.id);

// Si el estado de SECOP II no se copia al contrato guardado (estadoCierreBloqueado), con su
// suspensión abierta, si la tiene.
const estadoBloqueado = (estadoSecop, contrato) => estadoCierreBloqueado(
    estadoSecop, contrato?.estado, esNuevo(contrato), !esNuevo(contrato) && suspensionAbierta(contrato) !== null
);
const texto = (valor) => (vacio(valor) ? '' : String(valor).trim());

/**
```

En `resources/js/utils/secop.js`, reemplazar (3 de 6):

```js
 * Avisos de un resultado: el estado que falta elegir, el estado de cierre que no se copia y los
 * días adicionados en SECOP II. Con el `formulario` que resultó de copiar, el estado se pide si el
 * formulario quedó sin él; sin formulario, si SECOP II no lo trae (o no se pudo traducir). Con el
 * `contrato` guardado, si SECOP II lo ve terminado o liquidado y el contrato no lo está, se avisa
 * qué acción usar (avisoEstadoCierreSecop).
 */
export const avisosSecop = (resultado, formulario = null, contrato = null) => {
    const avisos = [];
```

por:

```js
 * Avisos de un resultado: el estado que falta elegir, el estado de cierre que no se copia y los
 * días adicionados en SECOP II. Con el `formulario` que resultó de copiar, el estado se pide si el
 * formulario quedó sin él; sin formulario, si SECOP II no lo trae (o no se pudo traducir). Con el
 * `contrato` guardado, si su estado de SECOP II no se copia (terminado, liquidado o suspendido, o
 * salir de una suspensión abierta), se avisa qué acción usar (avisoEstadoCierreSecop).
 */
export const avisosSecop = (resultado, formulario = null, contrato = null) => {
    const avisos = [];
```

En `resources/js/utils/secop.js`, reemplazar (4 de 6):

```js
        const estadoSecop = texto(resultado?.secop?.estado_secop);
        avisos.push(estadoSecop ? `Elige el estado: SECOP II dice «${estadoSecop}».` : 'Elige el estado: SECOP II no lo informa.');
    }
    const avisoCierre = avisoEstadoCierreSecop(valoresDesdeSecop(resultado?.contrato).estado, contrato?.estado, esNuevo(contrato));
    if (avisoCierre) avisos.push(avisoCierre);
    const dias = Number(resultado?.secop?.dias_adicionados);
    if (Number.isFinite(dias) && dias > 0) {
```

por:

```js
        const estadoSecop = texto(resultado?.secop?.estado_secop);
        avisos.push(estadoSecop ? `Elige el estado: SECOP II dice «${estadoSecop}».` : 'Elige el estado: SECOP II no lo informa.');
    }
    const avisoCierre = avisoEstadoCierreSecop(
        valoresDesdeSecop(resultado?.contrato).estado, contrato?.estado, esNuevo(contrato), !esNuevo(contrato) && suspensionAbierta(contrato) !== null
    );
    if (avisoCierre) avisos.push(avisoCierre);
    const dias = Number(resultado?.secop?.dias_adicionados);
    if (Number.isFinite(dias) && dias > 0) {
```

En `resources/js/utils/secop.js`, reemplazar (5 de 6):

```js
 * del formulario, con su etiqueta, los dos valores legibles (`actual`, el del formulario, y
 * `secop`), si va `marcado` por defecto y su `nota`. Un campo que SECOP II no trae no se compara.
 * El estado, el valor inicial y la fecha fin inicial no van marcados (NOTAS_SIN_MARCAR); en un
 * contrato guardado, un estado terminado o liquidado de SECOP II no se ofrece (estadoCierreBloqueado).
 */
export const diferenciasSecop = (formulario, resultado, { tipos = null, contrato = null } = {}) => {
    const deSecop = { ...valoresDesdeSecop(resultado?.contrato), ...referenciaDesdeSecop(resultado?.contrato) };
    // A terminado y liquidado se llega con «Terminar» y «Liquidar»: ese estado no se ofrece.
    if (estadoCierreBloqueado(deSecop.estado, contrato?.estado, esNuevo(contrato))) delete deSecop.estado;
    const referencia = referenciaSecop(formulario, contrato);
    const actuales = { ...formulario, secop_proceso: referencia.proceso, url_secop: referencia.enlace ?? '' };
    return [...CAMPOS_DESDE_SECOP, ...REFERENCIA_COMPARADA]
```

por:

```js
 * del formulario, con su etiqueta, los dos valores legibles (`actual`, el del formulario, y
 * `secop`), si va `marcado` por defecto y su `nota`. Un campo que SECOP II no trae no se compara.
 * El estado, el valor inicial y la fecha fin inicial no van marcados (NOTAS_SIN_MARCAR); en un
 * contrato guardado, un estado de SECOP II al que se llega con una acción no se ofrece (estadoCierreBloqueado).
 */
export const diferenciasSecop = (formulario, resultado, { tipos = null, contrato = null } = {}) => {
    const deSecop = { ...valoresDesdeSecop(resultado?.contrato), ...referenciaDesdeSecop(resultado?.contrato) };
    // A terminado, liquidado y suspendido se llega con «Terminar», «Liquidar» y «Suspender», y de
    // una suspensión abierta se sale con «Reiniciar»: ese estado no se ofrece.
    if (estadoBloqueado(deSecop.estado, contrato)) delete deSecop.estado;
    const referencia = referenciaSecop(formulario, contrato);
    const actuales = { ...formulario, secop_proceso: referencia.proceso, url_secop: referencia.enlace ?? '' };
    return [...CAMPOS_DESDE_SECOP, ...REFERENCIA_COMPARADA]
```

En `resources/js/utils/secop.js`, reemplazar (6 de 6):

```js
 */
export const aplicarDiferencias = (formulario, resultado, elegidos, contrato = null) => {
    const deSecop = valoresDesdeSecop(resultado?.contrato);
    if (estadoCierreBloqueado(deSecop.estado, contrato?.estado, esNuevo(contrato))) delete deSecop.estado;
    const nuevaReferencia = referenciaDesdeSecop(resultado?.contrato);
    const referencia = referenciaSecop(formulario, contrato);
    const lista = elegidos ?? [];
```

por:

```js
 */
export const aplicarDiferencias = (formulario, resultado, elegidos, contrato = null) => {
    const deSecop = valoresDesdeSecop(resultado?.contrato);
    if (estadoBloqueado(deSecop.estado, contrato)) delete deSecop.estado;
    const nuevaReferencia = referenciaDesdeSecop(resultado?.contrato);
    const referencia = referenciaSecop(formulario, contrato);
    const lista = elegidos ?? [];
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/contratos.test.js resources/js/utils/secop.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1391 passed.

Run: `npm test`
Expected: 969 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/js/components/contratos/SuspensionContrato.jsx resources/css/Contratacion.css resources/js/components/FichaProyecto.jsx resources/js/components/contratos/ContratoEditor.jsx resources/js/components/contratos/ContratosModulo.jsx resources/js/components/contratos/ContratosProyecto.jsx resources/js/utils/contratos.js resources/js/utils/contratos.test.js resources/js/utils/secop.js resources/js/utils/secop.test.js
git commit -m "feat: contract suspension and restart in the contract editor"
```

---

### Tarea 4: Compilar para producción

- [ ] **Paso 1: Compilar**

Run: `npm run production`
Expected: `Compiled Successfully`, sin errores.

Justo antes de comitear, comprobar que `public/js/app.js` es el de producción: minificado y sin comentarios `/***/` al inicio. Si `npm run watch` lo reescribió, compilar otra vez.

- [ ] **Paso 2: Commit**

```bash
git add public/js/app.js public/js/app.js.map public/mix-manifest.json
git commit -m "build: production bundle for procurement phase 7B"
```

Los archivos que no cambiaron se omiten. `public/css/app.css` no se comitea si no cambió.

---

### Tarea 5: Verificación en la copia descartable

Usa la copia y las fotos de la tarea 1. Nunca `gest_pro`.

- [ ] **Paso 1: Migrar, revertir y volver a migrar, comparando**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

Expected: se aplica `2026_11_19_100024_add_suspension_a_modificaciones_contrato`.

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c12_despues_$f.json" gestpro_verif_tmp && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c12_antes_$f.json" "$TMP_DIR/c12_despues_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces. El comparador admite claves nuevas; un valor distinto o algo que falta, no.

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT COUNT(*) FROM modificaciones_contrato WHERE fecha_reinicio IS NOT NULL OR fecha_reinicio_prevista IS NOT NULL;"
```

Expected: `0`.

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=1 --force
```

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c12_rb_$f.json" gestpro_verif_tmp --solo-tablas && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c12_tablas_antes_$f.json" "$TMP_DIR/c12_rb_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c12_re_$f.json" gestpro_verif_tmp && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c12_antes_$f.json" "$TMP_DIR/c12_re_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

- [ ] **Paso 2: Probar en el navegador sobre la copia**

Compilar en desarrollo (`npx mix`) si `public/` no tiene el código de esta fase. Arrancar el servidor desde `public/`, en un puerto libre (`netstat -ano | grep 8796`):

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8796 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Crear un Administrador de prueba y un usuario Consulta de prueba, solo en la copia. Nunca usar sesiones de usuarios reales de `gest_pro`:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'verif@gestpro.test', 'rol' => App\Enums\Rol::Administrador]); App\Models\User::factory()->create(['email' => 'consulta@gestpro.test', 'rol' => App\Enums\Rol::Consulta]);"
```

Entrar por `http://127.0.0.1:8796/GestPro/` como el Administrador de prueba, borrando `localStorage` si hay una sesión anterior. Todo lo que sigue se hace **solo en la copia**.

Comprobar, sobre `C-TEST-001` (id 6, en ejecución, fecha fin 31/12/2026):

1. **Suspender** con fecha de suspensión 05/11/2026, reinicio previsto 10/11/2026 y un número de acta: "Contrato suspendido".
   - La información muestra "Suspendido desde 05/11/2026 (N días)", "Reinicio previsto el 10/11/2026: vencido" y el chip "Reinicio vencido".
   - Solo queda el botón "Reiniciar", con la nota "Reinicia el contrato antes de terminarlo o liquidarlo.".
   - La fecha fin sigue en 31/12/2026.
2. **Listas:** en `/contratos`, en la lista de contratos del proyecto en Parámetros y en la ficha `/proyectos/1`, la fila muestra "Suspendido" y el chip "Reinicio vencido".
3. **Bloqueos:**
   - `POST /contratos/6/terminar` responde 422 "Reinicia el contrato antes de terminarlo o liquidarlo.".
   - En el select de estado, "En ejecución (usa «Reiniciar»)" está deshabilitado.
4. **Reiniciar** con la fecha de hoy:
   - el diálogo muestra "Se suman N días: la fecha fin pasa de 31/12/2026 a …";
   - al guardar sale "Contrato reiniciado" con la fecha fin nueva;
   - el estado vuelve a "En ejecución" y la pestaña Modificaciones muestra la fila "Suspensión" con sus fechas y días.
5. **409:** reiniciar por API con un `actualizado` viejo responde 409.
6. **Eliminar la suspensión** desde la pestaña Modificaciones:
   - pide confirmación y la fecha fin vuelve a 31/12/2026;
   - suspender otra vez por API y eliminar la suspensión abierta: el contrato vuelve a "En ejecución".
7. **Formulario de modificaciones:** `POST /guardarModificacionContrato` con `tipo: "suspension"` responde 422, y una prórroga sigue registrándose como antes.
8. **El usuario Consulta de prueba** no ve los botones de suspensión, y `POST /contratos/6/suspender` con su token responde 403.
9. **Las cifras no cambian:**

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT id, estado, valor, valor_inicial, fecha_fin, fecha_fin_inicial, avance_fisico, avance_financiero FROM contratos WHERE id = 6; SELECT evento FROM checklist_historial WHERE entidad_tipo='contrato' AND entidad_id = 6 ORDER BY id;"
```

Expected:
- valor, valor inicial, fecha fin inicial y avances como en la foto de antes;
- el historial con `contrato_suspendido`, `contrato_reiniciado` y `suspension_eliminada`.

En la consola del navegador no hay errores nuevos. Se esperan solo estos: los 401 de una sesión vieja, los 422 y 409 provocados y el 403 del usuario Consulta.

- [ ] **Paso 3: Comprobar que revertir se detiene con suspensiones**

Suspender por API un contrato de la copia y, con esa suspensión todavía ahí:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=1 --force
```

Expected: se detiene con "No se puede revertir: hay suspensiones de contratos registradas, que se perderían. Restaure un respaldo." y las columnas siguen ahí.

- [ ] **Paso 4: Limpiar**

Detener el servidor, eliminar la copia y devolver `public/` a lo comiteado si se compiló en desarrollo:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 5: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas, 1391.

Run: `npm test`
Expected: pasan todas, 969.

---

### Tarea 6: Informar y pedir validación

- [ ] **Paso 1: Actualizar el estado de la propuesta**

En `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, reemplazar:

```markdown
La fase 7A (terminación y liquidación del contrato, migración 24) está validada y aplicada en `gest_pro` (2026-11-19).
```

por:

```markdown
La fase 7A (terminación y liquidación del contrato, migración 24) está validada y aplicada en `gest_pro` (2026-11-19). La fase 7B (suspensión y reinicio del contrato, migración 25) está implementada y verificada en copia, pendiente de validar y aplicar en `gest_pro`.
```

```bash
git add docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md
git commit -m "docs: mark procurement phase 7B as implemented"
```

- [ ] **Paso 2: Informar**

Informar:
- los totales de pruebas;
- las comparaciones (migrar, revertir y volver a migrar) y que revertir se detiene con suspensiones;
- lo revisado en el navegador.

Pedir **dos** decisiones por separado:
1. si valida la fase 7B;
2. si autoriza aplicar la migración 25 en `gest_pro`.

Recordar que, con los datos actuales, `modificaciones_contrato` está vacía y ningún contrato está suspendido: las dos columnas nacen sin filas y ninguna cifra cambia.

---

### Tarea 7: Aplicar en `gest_pro` (solo con autorización)

No ejecutar esta tarea sin un sí explícito del usuario a este paso. Si la comparación muestra diferencias, restaurar el respaldo: no usar `migrate:rollback`.

- [ ] **Paso 1: Respaldar**

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro > "$TMP_DIR/gest_pro_antes_contratacion_fase7b.sql"
```

Expected: el archivo existe y termina en `-- Dump completed`.

- [ ] **Paso 2: Tomar las fotos antes de migrar (solo tablas)**

```bash
for f in 2 3; do php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c12_real_antes_$f.json" gest_pro --solo-tablas; done
```

- [ ] **Paso 3: Migrar hacia adelante**

Run: `php artisan migrate --force`
Expected: se aplica `2026_11_19_100024_add_suspension_a_modificaciones_contrato`.

- [ ] **Paso 4: Comparar**

```bash
for f in 2 3; do php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c12_real_despues_$f.json" gest_pro && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c12_real_antes_$f.json" "$TMP_DIR/c12_real_despues_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

- [ ] **Paso 5: Informar**

Dar el resultado y la ruta completa del respaldo, y avisar que está en una carpeta temporal.

---

## Criterio de la fase

La fase está lista cuando:

- un contrato en ejecución se suspende (fecha, reinicio previsto opcional, acta y motivo) y se reinicia, por API y con los botones "Suspender" y "Reiniciar" del contrato, y cada suspensión queda en la pestaña Modificaciones;
- al reiniciar, los días calendario suspendidos se suman a la fecha fin vigente como una prórroga; mientras está suspendido la fecha fin no cambia;
- un contrato suspendido muestra "Suspendido desde X (N días)" y, si pasó el reinicio previsto, el aviso "Reinicio vencido" en el contrato, el módulo Contratos, la lista de Parámetros y la ficha del proyecto;
- el formulario de edición no suspende ni reanuda un contrato con una suspensión abierta, el formulario de modificaciones no registra suspensiones y un contrato con una suspensión abierta no se termina ni se liquida;
- solo la última suspensión se elimina, con control de versión, y el contrato y su fecha fin quedan coherentes;
- cada suspensión, reinicio y eliminación queda en el historial del contrato;
- el valor, los valores iniciales, los avances y las adiciones y prórrogas existentes no cambian;
- la viabilización no cambia: mismas respuestas, mismas cifras, y sus pruebas y la paridad del evaluador pasan;
- ningún dato cambia al migrar ni al revertir, y revertir se detiene si hay suspensiones.
