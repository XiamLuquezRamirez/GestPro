# Contratación, fase 5: revisión por etapa. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** que cada etapa de un expediente de contratación se pueda **aprobar** o **devolver** con una observación, que la revisión quede en el historial del expediente y que se vea su estado en la página del expediente, en la lista de procesos y en la ficha del proyecto. La revisión **solo avisa**: nunca bloquea marcar requisitos, cambiar fechas ni cerrar. Los avisos de la 4B pasan a seguir la aprobación: una etapa deja de estar vencida cuando se aprueba, los hitos cuentan etapas aprobadas y el cierre avisa también de las etapas sin aprobar. La viabilización no cambia.

**Arquitectura:**
- **Migración 23:** `2026_11_19_100022_add_revision_a_expediente_etapas` agrega a `expediente_etapas` `estado_revision` (string(20), `sin_revisar` por defecto: `sin_revisar`, `aprobada` o `devuelta`), `usuario_reviso_id` (null al eliminar el usuario), `fecha_revision` (timestamp), `observacion_revision` (text) y `pendientes_al_aprobar` (unsignedSmallInteger), todas nullable salvo el estado. Cada columna y la clave foránea con su guarda. Las filas que existan quedan sin revisar. El `down()` se detiene si alguna etapa está aprobada o devuelta.
- **Revisión:** las rutas `POST /expedientes/{expediente}/etapas/{etapa}/aprobar` y `.../devolver` (`RevisionEtapasController` y el servicio `App\Services\Contratacion\RevisionEtapas`), con `AprobacionConPendientesException` para el 422 de confirmación. El modelo `ExpedienteEtapa` gana las columnas y el usuario que revisó.
- **Estado visible:** en `ExpedienteService`, cada elemento de `avance_por_etapa` del expediente de contratación gana `revision` (estado visible calculado, quién, cuándo, observación, pendientes al aprobar, `devuelta_at`) y `aviso_etapa_anterior`. Los cambios después de la revisión se detectan por el id de `checklist_historial`, en lote y con un número constante de consultas.
- **Avisos:** `AvisosContratacion` agrega la `alerta` `aprobada`, mantiene vencidas y próximas las etapas completas sin aprobar, cuenta los hitos por etapas aprobadas y suma `etapas_por_revisar` y `etapas_devueltas`. El cierre (`CierreConPendientesException`) avisa también de las etapas sin aprobar.
- **Pantallas:** en `ExpedienteContratacion.jsx`, el estado de revisión de cada etapa con quién y cuándo, la observación, los botones "Aprobar etapa" / "Aprobar de nuevo" y "Devolver", el aviso "La etapa anterior aún no está aprobada" y el diálogo de cierre con las etapas sin aprobar; la franja y los chips (`ChipsAvisosProceso.jsx`) con "por revisar" y "devueltas"; el historial (`ExpedienteChecklist.jsx`) con los eventos de revisión. Las etiquetas salen de utilidades puras (`utils/expedienteContratacion.js`, `utils/avisosContratacion.js`, `utils/expedienteChecklist.js`) con pruebas Vitest.

**Tecnologías:** Laravel 12, PHP 8.2, MariaDB 10.4, React 18 con Laravel Mix y react-router, PHPUnit sobre SQLite en memoria, Vitest.

**Fuente:**
- La propuesta `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, aprobada:
  - fase 5: "Revisión por etapa. Aprobar y devolver, solo avisa"; lista cuando la revisión por etapa queda registrada en el historial, sin bloqueos;
  - regla R12: un expediente cerrado queda congelado;
  - regla R15: los cambios de estado, fechas y revisión dejan registro en `checklist_historial`;
  - sección 6: las columnas de revisión de `expediente_etapas`; los hitos cuentan las etapas aprobadas cuando exista la revisión.
- El diseño `docs/superpowers/specs/2026-11-17-contratacion-revision-design.md`, secciones "Revisión por etapa (solo avisa)" y "Cronograma" (vencida es fecha programada pasada sin aprobación; la fecha real es la de aprobación por defecto, corregible).
- Decisiones del usuario (2026-11-19), abajo.

**Estado de partida:** rama `feature/contratacion`, igual a `main` (`cc0629f`). La fase 4 (etapas 4A y 4B) está validada y aplicada en `gest_pro`. Pasan 1262 pruebas PHP y 772 JS.

**Datos reales comprobados hoy en `gest_pro`:**
- **Expedientes:** 11, todos de viabilización, con 326 filas de requisitos.
- **Plantillas:** 5. De contratación, "Licitación pública – SECOP II", **en borrador**, y "nueva plantilla", publicada.
- **Requisitos de catálogo:** 238.
- **Procesos:** 2. El proceso 2 está en preparación.
- **Contratos:** 7.
- **`expediente_etapas`:** vacía.

No hay ningún expediente de contratación, así que **las columnas nuevas nacen sobre una tabla vacía** y ninguna respuesta existente cambia de valor: solo ganan claves nuevas.

---

## Reglas de ejecución

- **Nada de esto se aplica a `gest_pro` sin autorización del usuario.** Allí nunca se usa `migrate:rollback`: si hay que volver atrás, se restaura el respaldo. La tarea 8 aplica la migración 23, y solo con un sí explícito.
- **La viabilización no cambia ni una respuesta ni una cifra.** Las pruebas existentes de `tests/Feature/Viabilizacion/**`, los fixtures de paridad (`tests/Fixtures/paridad_*`) y `ExpedientesContratacionAisladosTest` no se modifican y deben pasar. Las únicas pruebas existentes que cambian son de contratación:
  - ganan claves nuevas: `AvancePonderadoApiTest`, `CronogramaEtapasApiTest` y `ExpedienteContratacionApiTest` (`revision` y `aviso_etapa_anterior` en `avance_por_etapa`), `AvisosProcesosApiTest` y `ProcesosContratacionApiTest` (`etapas_por_revisar` y `etapas_devueltas` en `avisos`) y `ExpedienteEtapasTest` (las columnas de la migración 23);
  - cambian de valor solo por las decisiones del usuario: en `CronogramaEtapasApiTest` y `AvisosProcesosApiTest`, una etapa completa sin aprobar con la fecha pasada sigue `vencida` y los hitos cuentan 0 aprobadas (decisión 1); en `ExpedienteContratacionApiTest`, el aviso de cierre suma las etapas sin aprobar y, para cerrar sin pendientes, las etapas se aprueban antes (decisión 3).
  En JS cambian `avisosContratacion.test.js`, `expedienteChecklist.test.js` y `expedienteContratacion.test.js`, que ganan casos.
- Commits autorizados solo en `feature/contratacion`. Sin push. Mensajes en inglés, terminados con la línea `Co-Authored-By` del modelo que comitea.
- Nomenclatura en español sin tildes para tablas, columnas, clases y métodos nuevos. Los textos para el usuario van en español con tuteo. Las migraciones, que son herramientas del operador, conservan el registro de usted.
- **Fechas en días calendario y en la zona `config('contratacion.zona')` (`America/Bogota`)**, como en la 4B. Los momentos de la revisión (`fecha`, `devuelta_at`) se responden en ISO 8601 UTC, como el `fecha` del historial.
- Pruebas: `php artisan test` sin definir `DB_DATABASE`, para que use SQLite en memoria, y `npm test`. Si Vitest crea `resources/js/node_modules`, se borra.
- **Aplicar los bloques exactos.** Cada reemplazo aparece una sola vez en su archivo. Si no lo encuentras tal cual, detente y avisa. Se recomienda aplicarlos con un script que los lea del plan y exija una coincidencia por reemplazo.
- **No usar `git stash`:** el usuario tiene stashes propios.
- El usuario suele tener `npm run watch` corriendo, que reescribe `public/`. Solo la tarea 5 comitea `public/`, después de `npm run production`.
- No tocar ni comitear `public/images/marca-completa.png` ni `public/images/marca-completa1.png`: son cambios del usuario.
- `.env` está versionado: nunca se agrega a un commit. Comitear siempre con rutas explícitas.
- `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio. Hay que definirlo en cada comando, porque el estado de la terminal no se conserva.
- **MariaDB no hace transaccional el DDL.** Por eso cada columna de la migración 23 y su clave foránea llevan su guarda (`hasColumn` y la clave foránea por separado) y se puede reintentar.
- **El prefijo de la API (`/GestPro`) es también la base del SPA.** `/contratacion/:procesoId` y `/proyectos/:id` no son rutas GET de la API, y la fase no agrega rutas nuevas al front.

## Decisiones de este plan

**Decisiones del usuario (2026-11-19)**
1. **Vencida hasta aprobar:** una etapa deja de estar vencida o próxima cuando se **aprueba**, no al completarse. Una etapa completa sin aprobar con la fecha programada pasada sigue vencida. Los **hitos** cuentan etapas **aprobadas**.
2. **Fecha real al aprobar:** si está vacía o es la sugerida, pasa a ser la fecha de la aprobación (deja de ser sugerida); si la escribió una persona, se respeta. Sigue siendo corregible con el `PUT` de fechas.
3. **Cierre:** el aviso de cierre suma "N etapas sin aprobar" a los requisitos pendientes; se puede cerrar confirmando (solo avisa).

**Datos**
- Las columnas de revisión de la sección 6, en la tabla de la 4B. La fila de una etapa se crea si no existe (por expediente y etapa, como en la 4B), bajo el bloqueo del expediente.
- El `down()` se detiene con "No se puede revertir: hay etapas aprobadas o devueltas en los expedientes de contratación, cuya revisión se perdería. Restaure un respaldo." si alguna fila no está `sin_revisar`. Si no, quita la clave foránea y las columnas, cada una con su guarda.

**Aprobar (`POST /expedientes/{expediente}/etapas/{etapa}/aprobar`)**
- Permiso `can:editar-datos` (Administrador o Gestor; Consulta recibe 403). El cargo o la persona de revisión de la plantilla solo orienta: no restringe.
- Cuerpo `{confirmar?, observacion?, pendientes_vistos?}`. Si faltan requisitos que cuentan y no llega `confirmar: true`, responde 422 `{"mensaje": "Faltan N requisitos de esta etapa. Confirma para aprobarla de todos modos.", "pendientes": N}` (en singular, "Falta 1 requisito de esta etapa. …") y no guarda nada.
- **`pendientes_vistos`:** con `confirmar`, si ahora faltan más requisitos que los que vio la confirmación (alguien desmarcó algo mientras tanto), responde el mismo 422 con el número nuevo en vez de aprobar.
- Aprueba: estado `aprobada`, quién, `fecha_revision = now()`, la observación (opcional) y `pendientes_al_aprobar`. La fecha real, según la decisión 2.
- **Reaprobar sin devolver:** una etapa con estado visible `aprobada_con_cambios` (calculado bajo el bloqueo) se aprueba otra vez directamente: actualiza quién, cuándo y los pendientes (con la misma confirmación), y la observación solo si llega una. No toca la fecha real. Una `aprobada` sin cambios responde 422 "La etapa ya está aprobada.".
- Historial `etapa_aprobada`, con `con_pendientes` si faltaban requisitos, las claves de fecha solo si la aprobación puso la fecha real y **`reaprobacion: true`** en una reaprobación.

**Devolver (`POST /expedientes/{expediente}/etapas/{etapa}/devolver`)**
- Mismo permiso. `observacion` obligatoria, máximo 2000: vacía o solo espacios responde 422 con "Escribe por qué devuelves la etapa.".
- Estado `devuelta`, quién, cuándo, la observación y `pendientes_al_aprobar = null`. Se puede devolver una aprobada y una ya devuelta (queda la nueva observación).
- **Devolver restaura la sugerida previa:** si la fecha real la puso la aprobación (se mira la aprobación que la fijó, no las reaprobaciones) y nadie la cambió después, vuelve a la regla de la 4B. Si la etapa está completa, la sugerida que tenía antes de aprobarse o, si no tenía fecha, la de hoy como sugerida; si no está completa, vacía. Una fecha escrita por una persona se conserva.
- Historial `etapa_devuelta`, con la observación.

**Errores comunes (422)**
- "El expediente no es de contratación.", "El expediente está cerrado." (R12), "La etapa no tiene requisitos en este expediente." y, para una etapa sin requisitos que cuenten, "La etapa no tiene requisitos que cuenten.".

**Estado visible (`revision` en cada etapa de `avance_por_etapa`)**
- `estado`: `sin_requisitos` (nada de la etapa cuenta; neutro y primero que todo, sin botones), `pendiente` (le faltan requisitos que cuentan), `lista` (no le falta nada), `aprobada`, `aprobada_con_cambios` (aprobada y después hubo un evento del checklist en un requisito de la etapa o se aplicó una versión nueva de una plantilla del expediente; la aprobación no se pierde) o `devuelta` (devuelta y sin eventos de la etapa después; si los hay, pasa a `pendiente` o `lista`, pero conserva la observación y `devuelta_at`).
- **Cambios detectados por el id del historial:** "después" es un id de `checklist_historial` mayor que el del último `etapa_aprobada` o `etapa_devuelta` de esa etapa, no la hora: un cambio en el mismo segundo de la revisión cuenta. En lote, acotado por expediente y leyendo solo id, tipo y etapa del historial.
- **Una versión nueva marca las aprobadas como con cambios:** aplicar una versión nueva de una plantilla avisa en todas las etapas aprobadas del expediente.
- También `usuario`, `fecha`, `observacion`, `pendientes_al_aprobar` y `devuelta_at` (null salvo en una etapa devuelta). El grupo sin etapa lleva `revision: null`.
- `aviso_etapa_anterior`: true si alguna etapa anterior con requisitos que cuentan no está aprobada y esta tiene algún requisito cumplido.

**Avisos (ajuste de la 4B por la decisión 1)**
- `alerta` gana el valor `aprobada` (aprobada o con cambios: no vence ni es próxima). Sin aprobar, en borrador, una etapa completa con la fecha programada pasada es `vencida` y con la fecha a 3 días o menos, `proxima`; si no, `completa` (lista para revisar). Cerrado, como en la 4B.
- `avisos` gana `etapas_por_revisar` (estado `lista`) y `etapas_devueltas` (estado `devuelta`), en 0 sin expediente o con el expediente cerrado. `hitos.completadas` cuenta las etapas aprobadas.
- `aviso_proceso` de los contratos conserva sus claves: como las vencidas ahora incluyen las completas sin aprobar, un contrato avisa hasta que se apruebe la etapa.

**Cierre (decisión 3, `POST /expedientes/{expediente}/cerrar`)**
- Sin `confirmar`, responde 422 si faltan requisitos **o** hay etapas sin aprobar. El mensaje dice las dos cosas ("Faltan N requisitos por cumplir y M etapas sin aprobar.", "Faltan N requisitos por cumplir.", "Hay M etapas sin aprobar.", con sus singulares), seguido de " Confirma para cerrar el expediente de todos modos.", y `pendientes` agrega `etapas_sin_aprobar: [{etapa, estado}]`. Con todo cumplido y todo aprobado, cierra sin confirmar.

**Pantallas**
- **Página del expediente:** en cada etapa, la etiqueta "Pendiente", "Lista para revisar", "Aprobada", "Aprobada con cambios", "Devuelta" o "Sin requisitos"; quién y cuándo ("Aprobada por … el …", "con N requisitos pendientes"); la observación; las notas "Algún requisito de la etapa cambió después de aprobarla." y "Tuvo cambios después de la devolución.".
- **Botones (solo editores, expediente en borrador):** "Aprobar etapa", o "Aprobar de nuevo" en una aprobada con cambios, con observación opcional y, si faltan requisitos, la confirmación "Falta 1 requisito de esta etapa…" / "Faltan N requisitos…" con "Aprobar de todos modos"; "Devolver", con la observación obligatoria ("Escribe por qué devuelves la etapa."). Sin botones en `sin_requisitos`.
- En la sección de cada etapa del checklist, "La etapa anterior aún no está aprobada" cuando corresponde.
- **Franja y chips:** "1 etapa lista para revisar" / "N etapas listas para revisar" y "1 etapa devuelta" / "N etapas devueltas"; en procesos y ficha, "N por revisar" y "N devueltas"; "Hitos C/T" cuenta las aprobadas.
- **Historial:** "Aprobó la etapa …", "Aprobó de nuevo la etapa …", con "con N requisitos pendientes" si los había, y "Devolvió la etapa …: observación".
- **Cierre:** el diálogo de confirmación lista los requisitos pendientes por etapa y "Hay N etapas sin aprobar" con el estado de cada una.
- **Solo lectura** (expediente cerrado o rol de consulta): sin botones de revisión; la observación sigue visible.

## Fuera de esta etapa

- **Fase 6:** el tablero de Contratación.
- Plazos orientativos por etapa en la plantilla.
- Días hábiles: todo se cuenta en días calendario.

## Lo que ya se ensayó

El plan se ensayó completo en un clon descartable fuera del repositorio, y este documento se generó con el código de ese ensayo. Un script comprobó que aplicar sus bloques sobre la rama reproduce el ensayo exactamente.

- **Pruebas:** con el código de las tareas 2 a 4 pasan 1295 pruebas PHP y 821 JS. Cada tarea intermedia también pasa sus suites completas. Ninguna prueba existente de viabilización ni fixture de paridad se modificó.
- **En una copia de `gest_pro` con los datos reales,** las fotos de la tarea 1 no mostraron ninguna diferencia al migrar, al revertir la migración 23 (comparando solo tablas) ni al volver a migrar. La tabla `expediente_etapas` quedó vacía.
- **En el navegador, sobre esa copia,** con un Administrador y un usuario Consulta de prueba creados solo en la copia, todo lo de la tarea 6 se vio así el 2026-11-19:
  - **Expediente:** con la versión borrador de la plantilla de licitación publicada solo en la copia, se creó el expediente del proceso 2 con esa plantilla y, por API, la etapa 4 recibió una fecha programada pasada.
  - **Aprobar en `/contratacion/2`:** al marcar el único requisito de la etapa 4 siguió "Vencida" con "Lista para revisar". "Aprobar etapa" con una observación la dejó "Aprobada", con la fecha real de hoy sin "sugerida" y la observación visible. Al desmarcar el requisito pasó a "Aprobada con cambios" con "Aprobar de nuevo"; al reaprobar, pidió la confirmación "Falta 1 requisito…" y quedó "con 1 requisito pendiente".
  - **Devolver:** "Devolver" la etapa 1 sin observación dijo "Escribe por qué devuelves la etapa."; con "Falta el CDP firmado" quedó "Devuelta" y la franja dijo "1 etapa devuelta". Al marcar un requisito de la etapa 1 mostró "Tuvo cambios después de la devolución." y salió de las devueltas.
  - **Historial:** "Aprobó la etapa…", "Aprobó de nuevo la etapa… con 1 requisito pendiente" y "Devolvió la etapa…: Falta el CDP firmado".
  - **Cierre:** "Cerrar expediente" listó los requisitos pendientes y las etapas sin aprobar; se canceló.
  - **Ficha `/proyectos/1`:** "Hitos 1/11", sin vencidas.
  - **Consulta:** sin botones de revisión, con la observación visible; `POST .../aprobar` con su token respondió 403.
  - **Revertir con revisiones:** en MariaDB, `migrate:rollback --step=1` se detuvo con "No se puede revertir: hay etapas aprobadas o devueltas…".
  - **Consola:** solo 401 de sesiones previas o del cambio de usuario, los 422 provocados y el 403 del lector.
- **Una revisión independiente** no encontró hallazgos altos. Encontró 2 medios y 6 bajos, ya corregidos en las tareas correspondientes salvo un bajo aceptado:
  - medio: reaprobar una etapa aprobada con cambios exigía devolverla antes; ahora se aprueba de nuevo directamente;
  - medio: un cambio en el mismo segundo de la revisión no se detectaba; ahora los cambios se detectan por el id del historial;
  - bajo aceptado: una respuesta atrasada puede dejar la franja de avisos desactualizada hasta la siguiente carga.

---

### Tarea 1: Punto de partida y fotos con el código actual

Las fotos se toman **antes de cambiar código**. La copia `gestpro_verif_tmp` se conserva hasta la tarea 6.

- [ ] **Paso 1: Comprobar la rama, el estado y las pruebas**

Run: `git branch --show-current`
Expected: `feature/contratacion`

Run: `git status --short`
Expected: como mucho, `public/` (si corre `npm run watch`) y las dos imágenes del usuario.

Run: `php artisan test`
Expected: pasan todas, 1262. Si alguna falla, detenerse y avisar.

Run: `npm test`
Expected: pasan todas, 772.

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
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c9_antes_$f.json" gestpro_verif_tmp; DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c9_tablas_antes_$f.json" gestpro_verif_tmp --solo-tablas; done
```

Expected: `Expedientes: 11, filas: 326, plantillas: 5, requisitos: 238` y `Contratos: 7, procesos: 2, actas: 2, expedientes: 11`, con las cifras actuales de `gest_pro`.

---

### Tarea 2: Migración 23: aprobar y devolver etapas, y su estado de revisión

Las columnas de revisión de `expediente_etapas` (migración 23), el modelo `ExpedienteEtapa` con quién revisó, las rutas `POST /expedientes/{expediente}/etapas/{etapa}/aprobar` y `.../devolver` (`RevisionEtapasController` y el servicio `RevisionEtapas`, con `AprobacionConPendientesException` para la confirmación) y, en `ExpedienteService`, el estado visible `revision` y `aviso_etapa_anterior` de cada etapa del avance, calculados en lote. La aprobación pone la fecha real según la decisión 2 y todo queda en el historial. La viabilización no cambia.

**Files:**
- Create: `app/Exceptions/AprobacionConPendientesException.php`
- Create: `app/Http/Controllers/RevisionEtapasController.php`
- Create: `app/Services/Contratacion/RevisionEtapas.php`
- Create: `database/migrations/2026_11_19_100022_add_revision_a_expediente_etapas.php`
- Test (create): `tests/Feature/Contratacion/RevisionEtapasApiTest.php`
- Test (create): `tests/Feature/Schema/RevisionEtapasTest.php`
- Modify: `app/Models/ExpedienteEtapa.php`
- Modify: `app/Services/Contratacion/AvisosContratacion.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `routes/viabilizacion.php`
- Test: `tests/Feature/Contratacion/AvancePonderadoApiTest.php`
- Test: `tests/Feature/Contratacion/CronogramaEtapasApiTest.php`
- Test: `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`
- Test: `tests/Feature/Schema/ExpedienteEtapasTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/RevisionEtapasApiTest.php`:

```php
<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\ExpedienteEtapa;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Revisión por etapa de un expediente de contratación (fase 5, R15, solo avisa): aprobar y
 * devolver una etapa, la fecha real al aprobar, el historial y el estado visible de la revisión
 * en avance_por_etapa. Hoy es el 19 de noviembre de 2026 salvo que la prueba diga otra cosa.
 */
class RevisionEtapasApiTest extends TestCase
{
    use RefreshDatabase;

    private const HOY = '2026-11-19';

    private Proyecto $proyecto;
    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(self::HOY . ' 10:00:00');
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $sector = DB::table('sectores')->insertGetId(['nombre' => 'Agua Potable', 'activo' => true]);
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal', 'sector' => $sector]);
        $this->gestor = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Gestora']);
    }

    private function como(Rol $rol = Rol::Gestor): static
    {
        $usuario = $rol === Rol::Gestor ? $this->gestor : User::factory()->create(['rol' => $rol]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function etapa(string $clave): EtapaContratacion
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail();
    }

    /** Plantilla publicada: dos requisitos de planeación, uno del pliego definitivo y uno del cierre. */
    private function plantilla(): PlantillaChecklist
    {
        $admin = $this->como(Rol::Administrador);
        $id = $admin->postJson('/GestPro/plantillas', [
            'nombre' => 'Licitación',
            'uso' => PlantillaChecklist::USO_CONTRATACION,
            'todas_fuentes' => true,
            'todos_sectores' => true,
            'modalidad_ids' => [Modalidad::where('clave', 'licitacion_publica')->value('id')],
        ])->assertCreated()->json('id');
        $plantilla = PlantillaChecklist::findOrFail($id);
        $borrador = $plantilla->versiones()->where('estado', CatalogoVersion::BORRADOR)->firstOrFail();
        foreach ([
            ['Estudios previos', 'planeacion'], ['CDP', 'planeacion'],
            ['Pliego de condiciones', 'pliego_definitivo'], ['Acta de cierre', 'cierre'],
        ] as [$texto, $etapa]) {
            $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos", [
                'texto' => $texto, 'etapa_id' => $this->etapa($etapa)->id,
            ])->assertCreated();
        }
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();

        return $plantilla->fresh();
    }

    private function expediente(?PlantillaChecklist $plantilla = null): ProyectoExpediente
    {
        $plantilla ??= $this->plantilla();
        $proceso = ProcesoLicitacion::create([
            'proyecto' => $this->proyecto->id,
            'objeto' => 'Construcción del acueducto',
            'modalidad' => Modalidad::where('clave', 'licitacion_publica')->value('id'),
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-' . (ProcesoLicitacion::count() + 1),
        ]);
        $id = $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => [$plantilla->id]])
            ->assertCreated()->json('id');

        return ProyectoExpediente::findOrFail($id);
    }

    private function leer(ProyectoExpediente $expediente)
    {
        return $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$expediente->id}")->assertOk();
    }

    private function fila(ProyectoExpediente $expediente, string $texto): ProyectoRequisito
    {
        return $expediente->requisitos()->whereHas('requisito', fn ($q) => $q->where('texto', $texto))->firstOrFail();
    }

    private function marcar(ProyectoExpediente $expediente, string $texto, bool $cumplido = true)
    {
        return $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, $texto)->id}", ['cumplido' => $cumplido])->assertOk();
    }

    private function aprobar(ProyectoExpediente $expediente, string $etapa, array $datos = [])
    {
        return $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa($etapa)->id}/aprobar", $datos);
    }

    private function devolver(ProyectoExpediente $expediente, string $etapa, array $datos = [])
    {
        return $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa($etapa)->id}/devolver", $datos);
    }

    private function fechas(ProyectoExpediente $expediente, string $etapa, array $datos)
    {
        return $this->como()->putJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa($etapa)->id}", $datos)->assertOk();
    }

    /** El elemento de avance_por_etapa de la etapa dada. */
    private function deEtapa(array $avance, string $clave): array
    {
        return collect($avance)->firstWhere('etapa.id', $this->etapa($clave)->id);
    }

    private function revisionDe(ProyectoExpediente $expediente, string $clave): array
    {
        return $this->deEtapa($this->leer($expediente)->json('avance_por_etapa'), $clave)['revision'];
    }

    private function estados(ProyectoExpediente $expediente): array
    {
        return array_map(fn (array $a) => $a['revision']['estado'] ?? null, $this->leer($expediente)->json('avance_por_etapa'));
    }

    /** Entradas del historial con ese evento: [antes, despues]. */
    private function historial(string $evento): array
    {
        return DB::table('checklist_historial')->where('evento', $evento)->orderBy('id')->get()
            ->map(fn ($h) => [json_decode((string) $h->antes, true), json_decode((string) $h->despues, true)])
            ->all();
    }

    private function viabilizacion(): ProyectoExpediente
    {
        $this->proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        $servicio = app(ExpedienteService::class);

        return $servicio->confirmarConfiguracion($servicio->crear($this->proyecto->fresh(), ['fase_sgr' => 3], $this->gestor), ['fase_sgr' => 3], $this->gestor);
    }

    // ------------------------------------------------------------ aprobar

    public function test_aprueba_una_etapa_completa_con_su_historial(): void
    {
        $expediente = $this->expediente();
        $planeacion = $this->etapa('planeacion');
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP')->assertJsonPath('avance_por_etapa.0.revision.estado', 'lista');

        $this->travelTo(self::HOY . ' 11:30:00');
        $r = $this->aprobar($expediente, 'planeacion', ['observacion' => '  Revisado con la oficina jurídica.  '])->assertOk();
        $this->assertSame(['etapa', 'avisos'], array_keys($r->json()));
        $r->assertJsonPath('etapa.etapa.id', $planeacion->id)
            ->assertJsonPath('etapa.revision', [
                'estado' => 'aprobada',
                'usuario' => 'Gestora',
                'fecha' => now()->toIso8601String(),
                'observacion' => 'Revisado con la oficina jurídica.',
                'pendientes_al_aprobar' => 0,
                'devuelta_at' => null,
            ])
            // La sugerida pasa a ser la de la aprobación, ya no sugerida.
            ->assertJsonPath('etapa.fecha_real', self::HOY)
            ->assertJsonPath('etapa.fecha_real_sugerida', false);

        $fila = ExpedienteEtapa::where('expediente_id', $expediente->id)->sole();
        $this->assertSame(
            [ExpedienteEtapa::APROBADA, $this->gestor->id, 0, 'Revisado con la oficina jurídica.', false],
            [$fila->estado_revision, $fila->usuario_reviso_id, $fila->pendientes_al_aprobar, $fila->observacion_revision, $fila->fecha_real_sugerida]
        );
        $base = ['etapa_id' => $planeacion->id, 'etapa' => $planeacion->nombre];
        $this->assertSame([[
            [...$base, 'estado_revision' => 'sin_revisar', 'fecha_real' => self::HOY, 'fecha_real_sugerida' => true],
            [...$base, 'estado_revision' => 'aprobada', 'observacion' => 'Revisado con la oficina jurídica.', 'pendientes_al_aprobar' => 0, 'fecha_real' => self::HOY, 'fecha_real_sugerida' => false],
        ]], $this->historial('etapa_aprobada'));
        $this->assertSame('expediente', DB::table('checklist_historial')->where('evento', 'etapa_aprobada')->value('entidad_tipo'));

        $this->assertSame('aprobada', $this->revisionDe($expediente, 'planeacion')['estado']);
        // Ya aprobada: no se aprueba otra vez.
        $this->aprobar($expediente, 'planeacion')->assertStatus(422)->assertExactJson(['mensaje' => 'La etapa ya está aprobada.']);
        $this->assertCount(1, $this->historial('etapa_aprobada'));
    }

    public function test_aprobar_con_pendientes_pide_confirmacion_y_la_registra(): void
    {
        $expediente = $this->expediente();

        $this->aprobar($expediente, 'planeacion')->assertStatus(422)->assertExactJson([
            'mensaje' => 'Faltan 2 requisitos de esta etapa. Confirma para aprobarla de todos modos.',
            'pendientes' => 2,
        ]);
        $this->marcar($expediente, 'Estudios previos');
        $this->aprobar($expediente, 'planeacion', ['confirmar' => false])->assertStatus(422)->assertExactJson([
            'mensaje' => 'Falta 1 requisito de esta etapa. Confirma para aprobarla de todos modos.',
            'pendientes' => 1,
        ]);
        $this->assertSame(0, ExpedienteEtapa::count());
        $this->assertSame([], $this->historial('etapa_aprobada'));

        $this->aprobar($expediente, 'planeacion', ['confirmar' => 'si'])->assertStatus(422)->assertJsonValidationErrors('confirmar');
        $r = $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertOk()
            ->assertJsonPath('etapa.revision.estado', 'aprobada')
            ->assertJsonPath('etapa.revision.pendientes_al_aprobar', 1)
            ->assertJsonPath('etapa.revision.observacion', null)
            ->assertJsonPath('etapa.completa', false)
            // Vacía: pasa a la fecha de la aprobación.
            ->assertJsonPath('etapa.fecha_real', self::HOY)
            ->assertJsonPath('etapa.fecha_real_sugerida', false);
        $this->assertSame(ExpedienteEtapa::APROBADA, ExpedienteEtapa::sole()->estado_revision);
        $despues = $this->historial('etapa_aprobada')[0][1];
        $this->assertSame([1, true, null], [$despues['pendientes_al_aprobar'], $despues['con_pendientes'], $despues['observacion'] ?? null]);

        // Al completarse después, la fecha de la aprobación no cambia (no es sugerida).
        $this->travelTo('2026-11-21 09:00:00');
        $this->marcar($expediente, 'CDP')
            ->assertJsonPath('avance_por_etapa.0.fecha_real', self::HOY)
            ->assertJsonPath('avance_por_etapa.0.fecha_real_sugerida', false);
        $this->assertSame(1, $r->json('etapa.revision.pendientes_al_aprobar'));
    }

    public function test_al_aprobar_se_respeta_la_fecha_real_que_puso_una_persona(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $this->fechas($expediente, 'planeacion', ['fecha_real' => '2026-11-10']);

        $this->travelTo('2026-11-20 09:00:00');
        $this->aprobar($expediente, 'planeacion')->assertOk()
            ->assertJsonPath('etapa.fecha_real', '2026-11-10')
            ->assertJsonPath('etapa.fecha_real_sugerida', false);
        $base = ['etapa_id' => $this->etapa('planeacion')->id, 'etapa' => $this->etapa('planeacion')->nombre];
        $this->assertSame([[
            [...$base, 'estado_revision' => 'sin_revisar'],
            [...$base, 'estado_revision' => 'aprobada', 'pendientes_al_aprobar' => 0],
        ]], $this->historial('etapa_aprobada'));

        // Sigue siendo corregible.
        $this->fechas($expediente, 'planeacion', ['fecha_real' => '2026-11-12'])->assertJsonPath('etapa.fecha_real', '2026-11-12');
    }

    public function test_la_fecha_de_aprobacion_es_la_de_hoy_en_la_zona_de_contratacion(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        // Las 20:00 del 19 en Bogotá son la 01:00 del 20 en UTC; y la sugerida, vaciada por una persona.
        $this->fechas($expediente, 'planeacion', ['fecha_real' => null]);
        $this->travelTo('2026-11-20 01:00:00');

        $this->aprobar($expediente, 'planeacion')->assertOk()->assertJsonPath('etapa.fecha_real', self::HOY);
    }

    // ------------------------------------------------------------ devolver

    public function test_devolver_exige_la_observacion_y_la_registra(): void
    {
        $expediente = $this->expediente();
        $planeacion = $this->etapa('planeacion');

        $this->devolver($expediente, 'planeacion')->assertStatus(422)
            ->assertJsonValidationErrors(['observacion' => 'Escribe por qué devuelves la etapa.']);
        $this->devolver($expediente, 'planeacion', ['observacion' => '   '])->assertStatus(422)->assertJsonValidationErrors('observacion');
        $this->devolver($expediente, 'planeacion', ['observacion' => str_repeat('a', 2001)])->assertStatus(422)
            ->assertJsonValidationErrors('observacion');

        $this->travelTo(self::HOY . ' 12:00:00');
        $this->devolver($expediente, 'planeacion', ['observacion' => 'Falta firmar los estudios previos.'])->assertOk()
            ->assertJsonPath('etapa.revision', [
                'estado' => 'devuelta',
                'usuario' => 'Gestora',
                'fecha' => now()->toIso8601String(),
                'observacion' => 'Falta firmar los estudios previos.',
                'pendientes_al_aprobar' => null,
                'devuelta_at' => now()->toIso8601String(),
            ]);

        // Devolver otra vez una devuelta se permite, con la nueva observación.
        $this->travelTo(self::HOY . ' 13:00:00');
        $this->devolver($expediente, 'planeacion', ['observacion' => 'Y el CDP.'])->assertOk()
            ->assertJsonPath('etapa.revision.observacion', 'Y el CDP.')
            ->assertJsonPath('etapa.revision.devuelta_at', now()->toIso8601String());

        $base = ['etapa_id' => $planeacion->id, 'etapa' => $planeacion->nombre];
        $this->assertSame([
            [[...$base, 'estado_revision' => 'sin_revisar'], [...$base, 'estado_revision' => 'devuelta', 'observacion' => 'Falta firmar los estudios previos.']],
            [[...$base, 'estado_revision' => 'devuelta', 'observacion' => 'Falta firmar los estudios previos.'], [...$base, 'estado_revision' => 'devuelta', 'observacion' => 'Y el CDP.']],
        ], $this->historial('etapa_devuelta'));
        $fila = ExpedienteEtapa::sole();
        $this->assertSame([ExpedienteEtapa::DEVUELTA, null, null], [$fila->estado_revision, $fila->pendientes_al_aprobar, $fila->fecha_real]);
    }

    public function test_devolver_una_aprobada_vuelve_la_fecha_de_aprobacion_a_la_regla_de_la_sugerida(): void
    {
        $expediente = $this->expediente();
        // Completa: la fecha de la aprobación vuelve a ser sugerida.
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $this->aprobar($expediente, 'planeacion')->assertOk()->assertJsonPath('etapa.fecha_real_sugerida', false);
        $this->travelTo('2026-11-20 09:00:00');
        $this->devolver($expediente, 'planeacion', ['observacion' => 'Revisar el CDP.'])->assertOk()
            ->assertJsonPath('etapa.fecha_real', self::HOY)
            ->assertJsonPath('etapa.fecha_real_sugerida', true)
            ->assertJsonPath('etapa.revision.estado', 'devuelta');
        $this->assertSame(
            ['fecha_real' => self::HOY, 'fecha_real_sugerida' => true],
            array_intersect_key($this->historial('etapa_devuelta')[0][1], ['fecha_real' => 0, 'fecha_real_sugerida' => 0])
        );
        // Y como sugerida, se borra si la etapa deja de estar completa.
        $this->marcar($expediente, 'CDP', false)->assertJsonPath('avance_por_etapa.0.fecha_real', null);

        // Sin completar: la fecha de la aprobación se vacía.
        $this->aprobar($expediente, 'pliego_definitivo', ['confirmar' => true])->assertOk()->assertJsonPath('etapa.fecha_real', '2026-11-20');
        $this->devolver($expediente, 'pliego_definitivo', ['observacion' => 'Falta el pliego.'])->assertOk()
            ->assertJsonPath('etapa.fecha_real', null)
            ->assertJsonPath('etapa.fecha_real_sugerida', false);
    }

    public function test_devolver_conserva_la_fecha_real_de_una_persona(): void
    {
        $expediente = $this->expediente();
        // Puesta antes de aprobar.
        $this->fechas($expediente, 'planeacion', ['fecha_real' => '2026-11-10']);
        $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertOk();
        $this->devolver($expediente, 'planeacion', ['observacion' => 'No.'])->assertOk()->assertJsonPath('etapa.fecha_real', '2026-11-10');

        // Corregida después de aprobar.
        $this->aprobar($expediente, 'cierre', ['confirmar' => true])->assertOk()->assertJsonPath('etapa.fecha_real', self::HOY);
        $this->fechas($expediente, 'cierre', ['fecha_real' => '2026-11-15']);
        $this->devolver($expediente, 'cierre', ['observacion' => 'No.'])->assertOk()->assertJsonPath('etapa.fecha_real', '2026-11-15');
    }

    // ------------------------------------------------------------ rechazos

    public function test_rechaza_lectores_viabilizacion_etapa_sin_requisitos_y_cerrado(): void
    {
        $expediente = $this->expediente();
        $viabilizacion = $this->viabilizacion();
        $planeacion = $this->etapa('planeacion')->id;
        $devolucion = ['observacion' => 'No.'];

        foreach (['aprobar' => ['confirmar' => true], 'devolver' => $devolucion] as $accion => $datos) {
            $this->como(Rol::Consulta)->postJson("/GestPro/expedientes/{$expediente->id}/etapas/{$planeacion}/{$accion}", $datos)->assertForbidden();
            $this->como()->postJson("/GestPro/expedientes/{$viabilizacion->id}/etapas/{$planeacion}/{$accion}", $datos)->assertStatus(422)
                ->assertExactJson(['mensaje' => 'El expediente no es de contratación.']);
            $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa('adendas')->id}/{$accion}", $datos)->assertStatus(422)
                ->assertExactJson(['mensaje' => 'La etapa no tiene requisitos en este expediente.']);
            $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/etapas/99999/{$accion}", $datos)->assertNotFound();
        }

        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertOk();
        $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertStatus(422)->assertExactJson(['mensaje' => 'El expediente está cerrado.']);
        $this->devolver($expediente, 'planeacion', $devolucion)->assertStatus(422)->assertExactJson(['mensaje' => 'El expediente está cerrado.']);

        $this->assertSame(0, ExpedienteEtapa::count());
        $this->assertSame(0, DB::table('checklist_historial')->whereIn('evento', ['etapa_aprobada', 'etapa_devuelta'])->count());
    }

    // ------------------------------------------------------------ estado visible

    public function test_el_estado_visible_pendiente_lista_y_aprobada(): void
    {
        $expediente = $this->expediente();
        $avance = $this->leer($expediente)->json('avance_por_etapa');
        $this->assertSame(['pendiente', 'pendiente', 'pendiente'], array_column(array_column($avance, 'revision'), 'estado'));
        $this->assertSame(
            ['estado' => 'pendiente', 'usuario' => null, 'fecha' => null, 'observacion' => null, 'pendientes_al_aprobar' => null, 'devuelta_at' => null],
            $avance[0]['revision']
        );

        $this->marcar($expediente, 'Acta de cierre');
        $this->assertSame(['pendiente', 'pendiente', 'lista'], $this->estados($expediente));
        $this->aprobar($expediente, 'cierre')->assertOk();
        $this->aprobar($expediente, 'pliego_definitivo', ['confirmar' => true])->assertOk();
        $this->assertSame(['pendiente', 'aprobada', 'aprobada'], $this->estados($expediente));
    }

    public function test_una_aprobada_con_un_cambio_en_su_checklist_pasa_a_aprobada_con_cambios(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $this->aprobar($expediente, 'planeacion')->assertOk();
        $this->aprobar($expediente, 'pliego_definitivo', ['confirmar' => true])->assertOk();

        // Un evento de otra etapa no la cambia; uno de la suya, sí, pero la aprobación no se pierde.
        $this->travelTo(self::HOY . ' 10:05:00');
        $this->marcar($expediente, 'Acta de cierre');
        $this->assertSame(['aprobada', 'aprobada', 'lista'], $this->estados($expediente));
        $this->travelTo(self::HOY . ' 10:10:00');
        $r = $this->marcar($expediente, 'CDP', false);
        $this->assertSame('aprobada_con_cambios', $r->json('avance_por_etapa.0.revision.estado'));
        $this->assertSame('aprobada', $r->json('avance_por_etapa.1.revision.estado'));
        $this->assertSame(ExpedienteEtapa::APROBADA, ExpedienteEtapa::where('etapa_id', $this->etapa('planeacion')->id)->value('estado_revision'));
        // Ya está aprobada: no se aprueba otra vez.
        $this->aprobar($expediente, 'planeacion')->assertStatus(422);

        // Una observación en un requisito de la etapa también es un cambio.
        $this->travelTo(self::HOY . ' 10:15:00');
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, 'Pliego de condiciones')->id}", ['observacion' => 'Ver anexo.'])->assertOk();
        $this->assertSame('aprobada_con_cambios', $this->revisionDe($expediente, 'pliego_definitivo')['estado']);
    }

    public function test_los_propios_de_la_etapa_y_una_version_nueva_cambian_la_aprobada(): void
    {
        $plantilla = $this->plantilla();
        $expediente = $this->expediente($plantilla);
        $base = "/GestPro/expedientes/{$expediente->id}";
        $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertOk();
        $this->aprobar($expediente, 'pliego_definitivo', ['confirmar' => true])->assertOk();
        $this->aprobar($expediente, 'cierre', ['confirmar' => true])->assertOk();

        // Un propio que se agrega a otra etapa y se elimina: su registro sobrevive a la fila.
        $this->travelTo(self::HOY . ' 10:05:00');
        $propio = $this->como()->postJson("{$base}/requisitos-propios", ['titulo' => 'Póliza', 'etapa_propia_id' => $this->etapa('pliego_definitivo')->id])
            ->assertCreated()->json('requisito.id');
        $this->como()->deleteJson("{$base}/requisitos-propios/{$propio}")->assertOk();
        $this->assertSame(['aprobada', 'aprobada_con_cambios', 'aprobada'], $this->estados($expediente));

        // Una versión nueva de la plantilla cambia los requisitos: todas las aprobadas avisan.
        $borrador = app(CatalogoService::class)->crearBorrador('Sin CDP', $plantilla);
        $admin = $this->como(Rol::Administrador);
        $admin->deleteJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos/{$borrador->requisitos()->where('texto', 'CDP')->sole()->id}")->assertOk();
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();
        $this->travelTo(self::HOY . ' 10:10:00');
        $this->como()->postJson("{$base}/plantillas/{$plantilla->id}/version-nueva", ['version_id' => $borrador->id])->assertOk()
            ->assertJsonPath('avance_por_etapa.0.revision.estado', 'aprobada_con_cambios')
            ->assertJsonPath('avance_por_etapa.2.revision.estado', 'aprobada_con_cambios');
    }

    public function test_una_devuelta_con_un_cambio_en_su_checklist_muestra_su_estado_y_la_observacion(): void
    {
        $expediente = $this->expediente();
        $this->devolver($expediente, 'planeacion', ['observacion' => 'Faltan los estudios previos.'])->assertOk();
        $devuelta = now()->toIso8601String();
        $this->assertSame(['devuelta', 'pendiente', 'pendiente'], $this->estados($expediente));

        $this->travelTo(self::HOY . ' 10:05:00');
        $this->marcar($expediente, 'Pliego de condiciones');
        $this->assertSame('devuelta', $this->revisionDe($expediente, 'planeacion')['estado']);

        $this->travelTo(self::HOY . ' 10:10:00');
        $this->marcar($expediente, 'Estudios previos')->assertJsonPath('avance_por_etapa.0.revision', [
            'estado' => 'pendiente',
            'usuario' => 'Gestora',
            'fecha' => $devuelta,
            'observacion' => 'Faltan los estudios previos.',
            'pendientes_al_aprobar' => null,
            'devuelta_at' => $devuelta,
        ]);
        $this->assertSame(ExpedienteEtapa::DEVUELTA, ExpedienteEtapa::where('etapa_id', $this->etapa('planeacion')->id)->value('estado_revision'));
        $this->marcar($expediente, 'CDP');
        $this->assertSame('lista', $this->revisionDe($expediente, 'planeacion')['estado']);

        // Y se puede aprobar.
        $this->aprobar($expediente, 'planeacion')->assertOk()->assertJsonPath('etapa.revision.estado', 'aprobada')
            ->assertJsonPath('etapa.revision.devuelta_at', null);
    }

    public function test_aviso_de_la_etapa_anterior_sin_aprobar(): void
    {
        $expediente = $this->expediente();
        $avisos = fn () => array_column($this->leer($expediente)->json('avance_por_etapa'), 'aviso_etapa_anterior');
        $this->assertSame([false, false, false], $avisos());

        // El cierre tiene algo cumplido y hay etapas anteriores sin aprobar.
        $this->marcar($expediente, 'Acta de cierre');
        $this->marcar($expediente, 'Estudios previos');
        $this->assertSame([false, false, true], $avisos());

        $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertOk();
        $this->assertSame([false, false, true], $avisos());
        $this->aprobar($expediente, 'pliego_definitivo', ['confirmar' => true])->assertOk()->assertJsonPath('etapa.aviso_etapa_anterior', false);
        $this->assertSame([false, false, false], $avisos());
    }

    public function test_el_grupo_sin_etapa_no_tiene_revision(): void
    {
        $expediente = $this->expediente();
        // Un requisito que quedó sin etapa (datos anteriores) va al final.
        RequisitoCatalogo::where('texto', 'Acta de cierre')->update(['etapa_id' => null]);
        $this->marcar($expediente, 'Acta de cierre')
            ->assertJsonPath('avance_por_etapa.2.etapa', null)
            ->assertJsonPath('avance_por_etapa.2.revision', null)
            ->assertJsonPath('avance_por_etapa.2.aviso_etapa_anterior', false);
    }

    // ------------------------------------------------------------ consultas en lote

    private function contarConsultas(string $url): int
    {
        $peticion = $this->como(Rol::Consulta);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $peticion->getJson($url)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }

    public function test_el_estado_de_la_revision_no_hace_una_consulta_por_etapa(): void
    {
        $expediente = $this->expediente();
        $url = "/GestPro/expedientes/{$expediente->id}";
        $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertOk();
        $this->travelTo(self::HOY . ' 10:05:00');
        $this->marcar($expediente, 'CDP');
        $conUna = $this->contarConsultas($url);

        $this->aprobar($expediente, 'pliego_definitivo', ['confirmar' => true])->assertOk();
        $this->devolver($expediente, 'cierre', ['observacion' => 'No.'])->assertOk();
        $this->travelTo(self::HOY . ' 10:10:00');
        $this->marcar($expediente, 'Pliego de condiciones');
        $this->marcar($expediente, 'Acta de cierre');
        $this->marcar($expediente, 'Estudios previos');

        $this->assertSame($conUna, $this->contarConsultas($url));
        $this->assertSame(['aprobada_con_cambios', 'aprobada_con_cambios', 'lista'], $this->estados($expediente));
    }

    public function test_la_lista_de_procesos_no_hace_una_consulta_por_proceso_con_revisiones(): void
    {
        $plantilla = $this->plantilla();
        $url = "/GestPro/proyectos/{$this->proyecto->id}/procesos";
        $revisado = function () use ($plantilla) {
            $expediente = $this->expediente($plantilla);
            $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertOk();
            $this->devolver($expediente, 'cierre', ['observacion' => 'No.'])->assertOk();
            $this->travel(1)->minutes();
            $this->marcar($expediente, 'CDP');
            $this->marcar($expediente, 'Acta de cierre');
        };
        $revisado();
        $conUno = $this->contarConsultas($url);

        foreach (range(1, 3) as $i) {
            $revisado();
        }

        $this->assertSame($conUno, $this->contarConsultas($url));
    }

    public function test_la_viabilizacion_no_cambia(): void
    {
        $r = $this->leer($this->viabilizacion());
        $this->assertArrayNotHasKey('avance_por_etapa', $r->json());
        $this->assertSame(0, ExpedienteEtapa::count());
    }
}
```

Crear `tests/Feature/Schema/RevisionEtapasTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\EtapaContratacion;
use App\Models\ExpedienteEtapa;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Migración 23: revisión de cada etapa de un expediente de contratación (expediente_etapas):
 * estado, quién y cuándo, la observación y los requisitos que faltaban al aprobarla.
 */
class RevisionEtapasTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNAS = ['estado_revision', 'usuario_reviso_id', 'fecha_revision', 'observacion_revision', 'pendientes_al_aprobar'];

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_19_100022_add_revision_a_expediente_etapas.php');
    }

    private function fila(array $datos = []): ExpedienteEtapa
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
        $expediente = ProyectoExpediente::create(['proyecto_id' => $proyecto->id, 'estado' => ProyectoExpediente::BORRADOR]);

        return ExpedienteEtapa::create([
            'expediente_id' => $expediente->id,
            'etapa_id' => EtapaContratacion::where('clave', 'planeacion')->value('id'),
            ...$datos,
        ]);
    }

    public function test_agrega_las_columnas_de_la_revision(): void
    {
        $columnas = collect(Schema::getColumns('expediente_etapas'))->keyBy('name');

        foreach (self::COLUMNAS as $columna) {
            $this->assertTrue($columnas->has($columna), "Falta {$columna}.");
        }
        $this->assertFalse($columnas['estado_revision']['nullable']);
        foreach (['usuario_reviso_id', 'fecha_revision', 'observacion_revision', 'pendientes_al_aprobar'] as $columna) {
            $this->assertTrue($columnas[$columna]['nullable'], "{$columna} debía admitir null.");
        }
        $this->assertNotNull(collect(Schema::getForeignKeys('expediente_etapas'))
            ->first(fn ($f) => $f['columns'] === ['usuario_reviso_id'] && $f['foreign_table'] === 'users'));
    }

    public function test_una_fila_nace_sin_revisar_y_el_revisor_eliminado_queda_en_null(): void
    {
        $this->assertSame(ExpedienteEtapa::SIN_REVISAR, $this->fila()->fresh()->estado_revision);

        $revisor = User::factory()->create();
        $fila = $this->fila(['estado_revision' => ExpedienteEtapa::APROBADA, 'usuario_reviso_id' => $revisor->id, 'fecha_revision' => now(), 'pendientes_al_aprobar' => 2]);
        $this->assertSame([$revisor->id, 2], [$fila->fresh()->usuario_reviso_id, $fila->fresh()->pendientes_al_aprobar]);
        $this->assertSame($revisor->id, $fila->usuarioReviso->id);

        $revisor->delete();
        $this->assertNull($fila->fresh()->usuario_reviso_id);
    }

    public function test_el_rollback_se_detiene_si_hay_revisiones_y_ambos_sentidos_se_repiten(): void
    {
        $migracion = $this->migracion();
        $migracion->up(); // ya migrada: no falla
        $fila = $this->fila(['estado_revision' => ExpedienteEtapa::DEVUELTA, 'observacion_revision' => 'Falta el CDP.']);

        try {
            $migracion->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('expediente_etapas', 'estado_revision'));

        // Sin revisiones (una fila con solo fechas no se pierde) se revierte, y se repite sin fallar.
        $fila->update(['estado_revision' => ExpedienteEtapa::SIN_REVISAR, 'observacion_revision' => null]);
        $migracion->down();
        $migracion->down();
        foreach (self::COLUMNAS as $columna) {
            $this->assertFalse(Schema::hasColumn('expediente_etapas', $columna), "{$columna} debía caer.");
        }
        $this->assertSame(1, ExpedienteEtapa::query()->count());
        $migracion->up();
        $migracion->up();
        $this->assertTrue(Schema::hasColumn('expediente_etapas', 'pendientes_al_aprobar'));
        $this->assertSame(ExpedienteEtapa::SIN_REVISAR, ExpedienteEtapa::query()->value('estado_revision'));
    }
}
```

En `tests/Feature/Contratacion/AvancePonderadoApiTest.php`, reemplazar (1 de 3):

```php
        return ['total' => $total, 'cumplidos' => $cumplidos, 'porcentaje' => $porcentaje, 'peso_total' => $pesoTotal, 'peso_cumplido' => $pesoCumplido];
    }

    // ------------------------------------------------------------ criterio 9

    public function test_criterio_9_cumplir_el_requisito_de_peso_3_de_cinco_da_43_por_ciento(): void
```

por:

```php
        return ['total' => $total, 'cumplidos' => $cumplidos, 'porcentaje' => $porcentaje, 'peso_total' => $pesoTotal, 'peso_cumplido' => $pesoCumplido];
    }

    /** Las claves de la revisión (fase 5) de una etapa sin revisar: su estado visible y el aviso de la etapa anterior. */
    private function sinRevisar(string $estado, bool $avisoAnterior = false): array
    {
        return [
            'revision' => ['estado' => $estado, 'usuario' => null, 'fecha' => null, 'observacion' => null, 'pendientes_al_aprobar' => null, 'devuelta_at' => null],
            'aviso_etapa_anterior' => $avisoAnterior,
        ];
    }

    // ------------------------------------------------------------ criterio 9

    public function test_criterio_9_cumplir_el_requisito_de_peso_3_de_cinco_da_43_por_ciento(): void
```

En `tests/Feature/Contratacion/AvancePonderadoApiTest.php`, reemplazar (2 de 3):

```php

        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$r->json('requisito.id')}", ['cumplido' => true])->assertOk()
            ->assertJsonPath('progreso', $this->progreso(2, 1, 25, 4, 1))
            ->assertJsonPath('avance_por_etapa.1', ['etapa' => $this->etapaJson('adjudicacion'), ...$this->progreso(1, 1, 100, 1, 1), 'fecha_programada' => null, 'fecha_real' => now()->toDateString(), 'fecha_real_sugerida' => true, 'completa' => true, 'alerta' => 'completa']);
    }

    public function test_el_avance_por_etapa_tambien_se_pondera(): void
```

por:

```php

        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$r->json('requisito.id')}", ['cumplido' => true])->assertOk()
            ->assertJsonPath('progreso', $this->progreso(2, 1, 25, 4, 1))
            ->assertJsonPath('avance_por_etapa.1', ['etapa' => $this->etapaJson('adjudicacion'), ...$this->progreso(1, 1, 100, 1, 1), 'fecha_programada' => null, 'fecha_real' => now()->toDateString(), 'fecha_real_sugerida' => true, 'completa' => true, 'alerta' => 'completa', ...$this->sinRevisar('lista', true)]);
    }

    public function test_el_avance_por_etapa_tambien_se_pondera(): void
```

En `tests/Feature/Contratacion/AvancePonderadoApiTest.php`, reemplazar (3 de 3):

```php
            ['etapa' => $this->etapaJson('pliego_definitivo'), ...$this->progreso(1, 0, 0, 1, 0)],
            ['etapa' => $this->etapaJson('cierre'), ...$this->progreso(1, 0, 0, 1, 0)],
        ];
        // La API agrega a cada etapa sus fechas y su semáforo.
        $conFechas = array_map(fn (array $a) => [...$a, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha'], $avance);
        $this->assertSame($conFechas, $this->cumplir($expediente, 'Estudios previos')->json('avance_por_etapa'));
        $this->assertSame($conFechas, $this->leer($expediente)->json('avance_por_etapa'));
        $this->assertSame($avance, app(ExpedienteService::class)->avancePorEtapaDe($expediente));
```

por:

```php
            ['etapa' => $this->etapaJson('pliego_definitivo'), ...$this->progreso(1, 0, 0, 1, 0)],
            ['etapa' => $this->etapaJson('cierre'), ...$this->progreso(1, 0, 0, 1, 0)],
        ];
        // La API agrega a cada etapa sus fechas, su semáforo y su revisión.
        $conFechas = array_map(fn (array $a) => [...$a, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha', ...$this->sinRevisar('pendiente')], $avance);
        $this->assertSame($conFechas, $this->cumplir($expediente, 'Estudios previos')->json('avance_por_etapa'));
        $this->assertSame($conFechas, $this->leer($expediente)->json('avance_por_etapa'));
        $this->assertSame($avance, app(ExpedienteService::class)->avancePorEtapaDe($expediente));
```

En `tests/Feature/Contratacion/CronogramaEtapasApiTest.php`, reemplazar (1 de 4):

```php
        return collect($avance)->firstWhere('etapa.id', $this->etapa($clave)->id);
    }

    private function viabilizacion(): ProyectoExpediente
    {
        $this->proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
```

por:

```php
        return collect($avance)->firstWhere('etapa.id', $this->etapa($clave)->id);
    }

    /** Las claves de la revisión (fase 5) de una etapa sin revisar: su estado visible y el aviso de la etapa anterior. */
    private function sinRevisar(string $estado, bool $avisoAnterior = false): array
    {
        return [
            'revision' => ['estado' => $estado, 'usuario' => null, 'fecha' => null, 'observacion' => null, 'pendientes_al_aprobar' => null, 'devuelta_at' => null],
            'aviso_etapa_anterior' => $avisoAnterior,
        ];
    }

    private function viabilizacion(): ProyectoExpediente
    {
        $this->proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
```

En `tests/Feature/Contratacion/CronogramaEtapasApiTest.php`, reemplazar (2 de 4):

```php
            'etapa' => ['id' => $planeacion->id, 'nombre' => $planeacion->nombre, 'orden' => $planeacion->orden],
            'total' => 2, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 2, 'peso_cumplido' => 0,
            'fecha_programada' => '2026-11-25', 'fecha_real' => null, 'fecha_real_sugerida' => false,
            'completa' => false, 'alerta' => 'al_dia',
        ]);
        $this->assertSame(['etapa', 'avisos'], array_keys($r->json()));
```

por:

```php
            'etapa' => ['id' => $planeacion->id, 'nombre' => $planeacion->nombre, 'orden' => $planeacion->orden],
            'total' => 2, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 2, 'peso_cumplido' => 0,
            'fecha_programada' => '2026-11-25', 'fecha_real' => null, 'fecha_real_sugerida' => false,
            'completa' => false, 'alerta' => 'al_dia', ...$this->sinRevisar('pendiente'),
        ]);
        $this->assertSame(['etapa', 'avisos'], array_keys($r->json()));
```

En `tests/Feature/Contratacion/CronogramaEtapasApiTest.php`, reemplazar (3 de 4):

```php
        $this->assertSame(['vencida', 'proxima', 'al_dia'], array_column($avance, 'alerta'));
        $this->assertSame([
            'etapa', 'total', 'cumplidos', 'porcentaje', 'peso_total', 'peso_cumplido',
            'fecha_programada', 'fecha_real', 'fecha_real_sugerida', 'completa', 'alerta',
        ], array_keys($avance[0]));
        $r->assertJsonPath('avisos.etapas_vencidas', 1)
            ->assertJsonPath('avisos.etapas_proximas', 1)
```

por:

```php
        $this->assertSame(['vencida', 'proxima', 'al_dia'], array_column($avance, 'alerta'));
        $this->assertSame([
            'etapa', 'total', 'cumplidos', 'porcentaje', 'peso_total', 'peso_cumplido',
            'fecha_programada', 'fecha_real', 'fecha_real_sugerida', 'completa', 'alerta', 'revision', 'aviso_etapa_anterior',
        ], array_keys($avance[0]));
        $r->assertJsonPath('avisos.etapas_vencidas', 1)
            ->assertJsonPath('avisos.etapas_proximas', 1)
```

En `tests/Feature/Contratacion/CronogramaEtapasApiTest.php`, reemplazar (4 de 4):

```php
        $r = $this->marcar($expediente, 'CDP');
        $this->assertSame([
            'fecha_programada' => null, 'fecha_real' => self::HOY, 'fecha_real_sugerida' => true, 'completa' => true, 'alerta' => 'completa',
        ], array_slice($r->json('avance_por_etapa.0'), 6));
        $this->assertSame(0, $r->json('avisos.etapas_vencidas'));
        $fila = ExpedienteEtapa::sole();
```

por:

```php
        $r = $this->marcar($expediente, 'CDP');
        $this->assertSame([
            'fecha_programada' => null, 'fecha_real' => self::HOY, 'fecha_real_sugerida' => true, 'completa' => true, 'alerta' => 'completa',
            ...$this->sinRevisar('lista'),
        ], array_slice($r->json('avance_por_etapa.0'), 6));
        $this->assertSame(0, $r->json('avisos.etapas_vencidas'));
        $fila = ExpedienteEtapa::sole();
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (1 de 5):

```php
        return ['id' => $etapa->id, 'nombre' => $etapa->nombre, 'orden' => $etapa->orden];
    }

    /**
     * Plantilla de contratación publicada, por la API: licitación pública, todas las fuentes y
     * todos los sectores. Por defecto, tres requisitos: dos de planeación (uno opcional) y uno
```

por:

```php
        return ['id' => $etapa->id, 'nombre' => $etapa->nombre, 'orden' => $etapa->orden];
    }

    /** Las claves de la revisión (fase 5) de una etapa sin revisar: su estado visible y el aviso de la etapa anterior. */
    private function sinRevisar(string $estado, bool $avisoAnterior = false): array
    {
        return [
            'revision' => ['estado' => $estado, 'usuario' => null, 'fecha' => null, 'observacion' => null, 'pendientes_al_aprobar' => null, 'devuelta_at' => null],
            'aviso_etapa_anterior' => $avisoAnterior,
        ];
    }

    /**
     * Plantilla de contratación publicada, por la API: licitación pública, todas las fuentes y
     * todos los sectores. Por defecto, tres requisitos: dos de planeación (uno opcional) y uno
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (2 de 5):

```php
        ], $etapas);
        // En el orden del catálogo de etapas, no en el de los requisitos.
        $this->assertSame([
            ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha'],
            ['etapa' => $this->etapaJson('pliego_definitivo'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha'],
        ], $r->json('avance_por_etapa'));
    }
```

por:

```php
        ], $etapas);
        // En el orden del catálogo de etapas, no en el de los requisitos.
        $this->assertSame([
            ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha', ...$this->sinRevisar('pendiente')],
            ['etapa' => $this->etapaJson('pliego_definitivo'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha', ...$this->sinRevisar('pendiente')],
        ], $r->json('avance_por_etapa'));
    }
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (3 de 5):

```php
        $r = $this->leer($expediente);

        $this->assertSame([
            ['etapa' => $this->etapaJson('planeacion'), 'total' => 2, 'cumplidos' => 1, 'porcentaje' => 50, 'peso_total' => 2, 'peso_cumplido' => 1, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha'],
            ['etapa' => $this->etapaJson('pliego_definitivo'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha'],
            ['etapa' => null, 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha'],
        ], $r->json('avance_por_etapa'));
        $r->assertJsonPath('progreso', ['total' => 4, 'cumplidos' => 1, 'porcentaje' => 25, 'peso_total' => 4, 'peso_cumplido' => 1]);
        $this->assertSame(
```

por:

```php
        $r = $this->leer($expediente);

        $this->assertSame([
            ['etapa' => $this->etapaJson('planeacion'), 'total' => 2, 'cumplidos' => 1, 'porcentaje' => 50, 'peso_total' => 2, 'peso_cumplido' => 1, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha', ...$this->sinRevisar('pendiente')],
            ['etapa' => $this->etapaJson('pliego_definitivo'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha', ...$this->sinRevisar('pendiente')],
            ['etapa' => null, 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha', 'revision' => null, 'aviso_etapa_anterior' => false],
        ], $r->json('avance_por_etapa'));
        $r->assertJsonPath('progreso', ['total' => 4, 'cumplidos' => 1, 'porcentaje' => 25, 'peso_total' => 4, 'peso_cumplido' => 1]);
        $this->assertSame(
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (4 de 5):

```php
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'))
            ->assertJsonPath('requisito.etapa', $this->etapaJson('planeacion'))
            ->assertJsonPath('progreso', ['total' => 2, 'cumplidos' => 1, 'porcentaje' => 50, 'peso_total' => 2, 'peso_cumplido' => 1])
            ->assertJsonPath('avance_por_etapa.0', ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 1, 'porcentaje' => 100, 'peso_total' => 1, 'peso_cumplido' => 1, 'fecha_programada' => null, 'fecha_real' => now()->toDateString(), 'fecha_real_sugerida' => true, 'completa' => true, 'alerta' => 'completa']);
    }

    public function test_la_api_de_viabilizacion_no_trae_las_claves_de_contratacion(): void
```

por:

```php
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'))
            ->assertJsonPath('requisito.etapa', $this->etapaJson('planeacion'))
            ->assertJsonPath('progreso', ['total' => 2, 'cumplidos' => 1, 'porcentaje' => 50, 'peso_total' => 2, 'peso_cumplido' => 1])
            ->assertJsonPath('avance_por_etapa.0', ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 1, 'porcentaje' => 100, 'peso_total' => 1, 'peso_cumplido' => 1, 'fecha_programada' => null, 'fecha_real' => now()->toDateString(), 'fecha_real_sugerida' => true, 'completa' => true, 'alerta' => 'completa', ...$this->sinRevisar('lista')]);
    }

    public function test_la_api_de_viabilizacion_no_trae_las_claves_de_contratacion(): void
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (5 de 5):

```php
        $this->como()->postJson($url, ['titulo' => 'Póliza', 'etapa_propia_id' => $this->etapa('adjudicacion')->id])
            ->assertCreated()
            ->assertJsonPath('requisito.etapa', $this->etapaJson('adjudicacion'))
            ->assertJsonPath('avance_por_etapa.2', ['etapa' => $this->etapaJson('adjudicacion'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha']);

        $propio = $expediente->requisitos()->whereNull('requisito_catalogo_id')->sole();
        $this->assertSame($this->etapa('adjudicacion')->id, $propio->etapa_propia_id);
```

por:

```php
        $this->como()->postJson($url, ['titulo' => 'Póliza', 'etapa_propia_id' => $this->etapa('adjudicacion')->id])
            ->assertCreated()
            ->assertJsonPath('requisito.etapa', $this->etapaJson('adjudicacion'))
            ->assertJsonPath('avance_por_etapa.2', ['etapa' => $this->etapaJson('adjudicacion'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha', ...$this->sinRevisar('pendiente')]);

        $propio = $expediente->requisitos()->whereNull('requisito_catalogo_id')->sole();
        $this->assertSame($this->etapa('adjudicacion')->id, $propio->etapa_propia_id);
```

En `tests/Feature/Schema/ExpedienteEtapasTest.php`, reemplazar (1 de 1):

```php
        $columnas = collect(Schema::getColumns('expediente_etapas'))->keyBy('name');

        $this->assertSame(
            ['id', 'expediente_id', 'etapa_id', 'fecha_programada', 'fecha_real', 'fecha_real_sugerida', 'usuario_actualizo_id', 'created_at', 'updated_at'],
            $columnas->keys()->all()
        );
        $this->assertTrue($columnas['fecha_programada']['nullable']);
```

por:

```php
        $columnas = collect(Schema::getColumns('expediente_etapas'))->keyBy('name');

        $this->assertSame(
            ['id', 'expediente_id', 'etapa_id', 'fecha_programada', 'fecha_real', 'fecha_real_sugerida', 'usuario_actualizo_id', 'created_at', 'updated_at',
                // Migración 23: la revisión de la etapa (en SQLite, al final).
                'estado_revision', 'usuario_reviso_id', 'fecha_revision', 'observacion_revision', 'pendientes_al_aprobar'],
            $columnas->keys()->all()
        );
        $this->assertTrue($columnas['fecha_programada']['nullable']);
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/RevisionEtapasApiTest.php tests/Feature/Schema/RevisionEtapasTest.php tests/Feature/Contratacion/AvancePonderadoApiTest.php tests/Feature/Contratacion/CronogramaEtapasApiTest.php tests/Feature/Contratacion/ExpedienteContratacionApiTest.php tests/Feature/Schema/ExpedienteEtapasTest.php`
Expected: FALLAN 29 y pasan 52: en `RevisionEtapasApiTest` fallan 16 de 17, porque las rutas `POST .../etapas/{etapa}/aprobar` y `.../devolver` no existen ("Failed asserting that 405 is identical to 200.") ni hay `revision` en el avance ("Failed asserting that null is identical to 'lista'."); en `RevisionEtapasTest` fallan las 3, porque no existen las columnas de revisión; en `ExpedienteEtapasTest` falla 1, la que espera esas columnas en la tabla; en `AvancePonderadoApiTest` fallan 2, en `CronogramaEtapasApiTest` 3 y en `ExpedienteContratacionApiTest` 4, los que esperan en `avance_por_etapa` las claves `revision` y `aviso_etapa_anterior`.

- [ ] **Paso 3: Implementar**

Crear `app/Exceptions/AprobacionConPendientesException.php`:

```php
<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

/**
 * Aprobar una etapa de un expediente de contratación con requisitos pendientes, sin confirmarlo.
 * No es un bloqueo: la pantalla muestra cuántos faltan y se aprueba confirmando. Responde 422 con
 * el mensaje y `pendientes` (cuántos requisitos que cuentan faltan en la etapa).
 */
class AprobacionConPendientesException extends ReglaNegocioException
{
    public function __construct(string $mensaje, private readonly int $pendientes)
    {
        parent::__construct($mensaje);
    }

    public function pendientes(): int
    {
        return $this->pendientes;
    }

    public function render(): JsonResponse
    {
        return response()->json(['mensaje' => $this->getMessage(), 'pendientes' => $this->pendientes], 422);
    }
}
```

Crear `app/Http/Controllers/RevisionEtapasController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\EtapaContratacion;
use App\Models\ProyectoExpediente;
use App\Services\Contratacion\RevisionEtapas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Revisión de una etapa de un expediente de contratación: aprobarla y devolverla. Rutas con
 * can:editar-datos. Ambas responden la etapa como en avance_por_etapa y los avisos del expediente.
 */
class RevisionEtapasController extends Controller
{
    private const MAX_OBSERVACION = 2000;

    public function __construct(private readonly RevisionEtapas $revision)
    {
    }

    /** Con requisitos pendientes en la etapa, sin `confirmar: true` responde 422 con cuántos faltan. */
    public function aprobar(Request $request, ProyectoExpediente $expediente, EtapaContratacion $etapa): JsonResponse
    {
        $datos = $request->validate([
            'confirmar' => ['sometimes', 'boolean'],
            'observacion' => ['sometimes', 'nullable', 'string', 'max:' . self::MAX_OBSERVACION],
        ], [
            'confirmar.boolean' => 'Indica si confirmas la aprobación con verdadero o falso.',
            'observacion.string' => 'Escribe la observación como texto.',
            'observacion.max' => 'La observación no puede tener más de ' . self::MAX_OBSERVACION . ' caracteres.',
        ]);

        return response()->json($this->revision->aprobar(
            $expediente,
            $etapa,
            (bool) ($datos['confirmar'] ?? false),
            $datos['observacion'] ?? null,
            $request->user()
        ));
    }

    public function devolver(Request $request, ProyectoExpediente $expediente, EtapaContratacion $etapa): JsonResponse
    {
        $datos = $request->validate([
            'observacion' => ['required', 'string', 'max:' . self::MAX_OBSERVACION],
        ], [
            'observacion.required' => 'Escribe por qué devuelves la etapa.',
            'observacion.string' => 'Escribe la observación como texto.',
            'observacion.max' => 'La observación no puede tener más de ' . self::MAX_OBSERVACION . ' caracteres.',
        ]);

        return response()->json($this->revision->devolver($expediente, $etapa, $datos['observacion'], $request->user()));
    }
}
```

Crear `app/Services/Contratacion/RevisionEtapas.php`:

```php
<?php

namespace App\Services\Contratacion;

use App\Exceptions\AprobacionConPendientesException;
use App\Exceptions\ReglaNegocioException;
use App\Models\ChecklistHistorial;
use App\Models\EtapaContratacion;
use App\Models\ExpedienteEtapa;
use App\Models\ProyectoExpediente;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\HistorialChecklist;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Revisión por etapa de un expediente de contratación (R15): aprobar y devolver una etapa. Solo
 * avisa: la revisión nunca bloquea marcar requisitos, cambiar fechas ni cerrar.
 *
 * Reglas:
 * - Solo en un expediente de contratación en borrador, y en una etapa que tiene requisitos en él
 *   (una de su avance por etapa). La fila de la etapa nace si no existe, como con las fechas.
 * - Aprobar: con requisitos que cuentan sin cumplir, sin `confirmar` responde cuántos faltan
 *   (AprobacionConPendientesException) y no aprueba; confirmada, aprueba y guarda cuántos
 *   faltaban. Una etapa ya aprobada no se aprueba otra vez. La fecha real, si está vacía o es la
 *   sugerida, pasa a ser la de hoy (la de la aprobación) y deja de ser sugerida; la que escribió
 *   una persona se respeta. Sigue siendo corregible.
 * - Devolver: con una observación (obligatoria); una devuelta se puede devolver otra vez. Si la
 *   etapa estaba aprobada y su fecha real es la que puso la aprobación (nadie la cambió
 *   después), vuelve a la regla de la sugerida: sugerida si la etapa está completa, vacía si no.
 * - Queda en el historial del expediente (`etapa_aprobada`, `etapa_devuelta`) con la etapa, el
 *   estado de revisión de antes y de después, la observación y la fecha real si cambió.
 * - Sobre el expediente bloqueado, como las acciones del checklist y las fechas de una etapa.
 */
final class RevisionEtapas
{
    public const YA_APROBADA = 'La etapa ya está aprobada.';

    public function __construct(
        private readonly ExpedienteService $expedientes,
        private readonly HistorialChecklist $historial,
        private readonly AvisosContratacion $avisos,
    ) {
    }

    /** @return array{etapa: array, avisos: array} la etapa como en avance_por_etapa y los avisos del expediente */
    public function aprobar(ProyectoExpediente $expediente, EtapaContratacion $etapa, bool $confirmar, ?string $observacion, ?User $usuario): array
    {
        return $this->revisar($expediente, $etapa, $usuario, function (ExpedienteEtapa $fila, array $avance) use ($confirmar, $observacion, $usuario) {
            if ($fila->aprobada()) {
                throw new ReglaNegocioException(self::YA_APROBADA);
            }
            $pendientes = $avance['total'] - $avance['cumplidos'];
            if ($pendientes > 0 && ! $confirmar) {
                $falta = $pendientes === 1 ? 'Falta 1 requisito' : "Faltan {$pendientes} requisitos";
                throw new AprobacionConPendientesException("{$falta} de esta etapa. Confirma para aprobarla de todos modos.", $pendientes);
            }

            $antes = $this->revision($fila);
            $fechaAntes = $this->fecha($fila);
            $fijaFecha = $fila->fecha_real === null || $fila->sugerida();
            if ($fijaFecha) {
                $fila->fecha_real = $this->avisos->hoy()->toDateString();
                $fila->fecha_real_sugerida = false;
            }
            $this->guardarRevision($fila, ExpedienteEtapa::APROBADA, $observacion, $pendientes, $usuario);

            return ['etapa_aprobada', [...$antes, ...($fijaFecha ? $fechaAntes : [])], [
                'estado_revision' => ExpedienteEtapa::APROBADA,
                ...($observacion !== null ? ['observacion' => $observacion] : []),
                'pendientes_al_aprobar' => $pendientes,
                ...($pendientes > 0 ? ['con_pendientes' => true] : []),
                ...($fijaFecha ? $this->fecha($fila) : []),
            ]];
        });
    }

    /** @return array{etapa: array, avisos: array} la etapa como en avance_por_etapa y los avisos del expediente */
    public function devolver(ProyectoExpediente $expediente, EtapaContratacion $etapa, string $observacion, ?User $usuario): array
    {
        return $this->revisar($expediente, $etapa, $usuario, function (ExpedienteEtapa $fila, array $avance, ProyectoExpediente $expediente) use ($etapa, $observacion, $usuario) {
            $antes = $this->revision($fila);
            $fechaAntes = $this->fecha($fila);
            $vuelve = $fila->aprobada() && $this->fechaDeLaAprobacion($expediente, $etapa, $fila);
            if ($vuelve) {
                $completa = $this->avisos->completa($avance);
                $fila->fecha_real = $completa ? $fila->fecha_real : null;
                $fila->fecha_real_sugerida = $completa;
            }
            $this->guardarRevision($fila, ExpedienteEtapa::DEVUELTA, $observacion, null, $usuario);

            return ['etapa_devuelta', [...$antes, ...($vuelve ? $fechaAntes : [])], [
                'estado_revision' => ExpedienteEtapa::DEVUELTA,
                'observacion' => $observacion,
                ...($vuelve ? $this->fecha($fila) : []),
            ]];
        });
    }

    /**
     * Lo común de aprobar y devolver: las reglas del expediente y la etapa, la fila de la etapa
     * (nace si no existe), el historial y la respuesta.
     *
     * @param \Closure(ExpedienteEtapa, array, ProyectoExpediente): array{0: string, 1: array, 2: array} $accion
     *        cambia la fila (sin guardar lo que no sea la revisión) y da el evento, el antes y el después
     */
    private function revisar(ProyectoExpediente $expediente, EtapaContratacion $etapa, ?User $usuario, \Closure $accion): array
    {
        if (! $expediente->esContratacion()) {
            throw new ReglaNegocioException(CronogramaExpediente::NO_ES_CONTRATACION);
        }

        DB::transaction(function () use ($expediente, $etapa, $usuario, $accion) {
            $this->expedientes->bloquear($expediente);
            $this->expedientes->exigirBorrador($expediente);
            $avance = collect($this->expedientes->avancePorEtapaDe($expediente))
                ->first(fn (array $a) => ($a['etapa']['id'] ?? null) === $etapa->id);
            if ($avance === null) {
                throw new ReglaNegocioException(CronogramaExpediente::SIN_REQUISITOS);
            }

            $fila = ExpedienteEtapa::query()
                ->where('expediente_id', $expediente->id)
                ->where('etapa_id', $etapa->id)
                ->first() ?? new ExpedienteEtapa(['expediente_id' => $expediente->id, 'etapa_id' => $etapa->id]);
            [$evento, $antes, $despues] = $accion($fila, $avance, $expediente);

            $cual = ['etapa_id' => $etapa->id, 'etapa' => $etapa->nombre];
            $this->historial->registrarExpediente($expediente, $evento, [...$cual, ...$antes], [...$cual, ...$despues], $usuario);
            $expediente->touch();
        });

        $cronograma = $this->expedientes->cronogramaDeContratacion($expediente);

        return [
            'etapa' => collect($cronograma['avance_por_etapa'])->first(fn (array $a) => ($a['etapa']['id'] ?? null) === $etapa->id),
            'avisos' => $cronograma['avisos'],
        ];
    }

    private function guardarRevision(ExpedienteEtapa $fila, string $estado, ?string $observacion, ?int $pendientes, ?User $usuario): void
    {
        $fila->fill([
            'estado_revision' => $estado,
            'usuario_reviso_id' => $usuario?->id,
            'fecha_revision' => now(),
            'observacion_revision' => $observacion,
            'pendientes_al_aprobar' => $pendientes,
        ])->save();
        $fila->setRelation('usuarioReviso', $usuario);
    }

    /**
     * La fecha real de la etapa es la que puso su aprobación vigente: el último `etapa_aprobada`
     * de la etapa la fijó, es la misma de ahora y ningún `etapa_fechas` posterior la cambió.
     */
    private function fechaDeLaAprobacion(ProyectoExpediente $expediente, EtapaContratacion $etapa, ExpedienteEtapa $fila): bool
    {
        $eventos = ChecklistHistorial::query()
            ->where('expediente_id', $expediente->id)
            ->where('entidad_tipo', HistorialChecklist::EXPEDIENTE)
            ->whereIn('evento', ['etapa_aprobada', 'etapa_fechas'])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (ChecklistHistorial $h) => ($h->despues['etapa_id'] ?? null) === $etapa->id);
        foreach ($eventos as $evento) {
            if ($evento->evento === 'etapa_fechas') {
                if (array_key_exists('fecha_real', $evento->despues)) {
                    return false;
                }
                continue;
            }

            return array_key_exists('fecha_real', $evento->despues)
                && $evento->despues['fecha_real'] === $fila->fecha_real?->format('Y-m-d');
        }

        return false;
    }

    /** @return array{estado_revision: string, observacion?: string} */
    private function revision(ExpedienteEtapa $fila): array
    {
        return [
            'estado_revision' => $fila->estado_revision,
            ...($fila->observacion_revision !== null ? ['observacion' => $fila->observacion_revision] : []),
        ];
    }

    /** @return array{fecha_real: ?string, fecha_real_sugerida: bool} */
    private function fecha(ExpedienteEtapa $fila): array
    {
        return [
            'fecha_real' => $fila->fecha_real instanceof DateTimeInterface ? $fila->fecha_real->format('Y-m-d') : $fila->fecha_real,
            'fecha_real_sugerida' => $fila->sugerida(),
        ];
    }
}
```

Crear `database/migrations/2026_11_19_100022_add_revision_a_expediente_etapas.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Revisión de cada etapa de un expediente de contratación (R15): `estado_revision` (sin_revisar,
 * aprobada o devuelta), quién la revisó y cuándo, la observación de la revisión y cuántos
 * requisitos de la etapa faltaban al aprobarla. Las filas que ya existen quedan sin revisar. El
 * usuario que revisó queda en null si se elimina. Solo avisa: nada se bloquea por la revisión.
 */
return new class extends Migration
{
    private const TABLA = 'expediente_etapas';

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada columna y la clave foránea llevan su
        // guarda, para poder reintentar sin dejar nada a medias.
        $columnas = [
            'estado_revision' => fn (Blueprint $t) => $t->string('estado_revision', 20)->default('sin_revisar')->after('fecha_real_sugerida'),
            'usuario_reviso_id' => fn (Blueprint $t) => $t->unsignedBigInteger('usuario_reviso_id')->nullable()->after('estado_revision'),
            'fecha_revision' => fn (Blueprint $t) => $t->timestamp('fecha_revision')->nullable()->after('usuario_reviso_id'),
            'observacion_revision' => fn (Blueprint $t) => $t->text('observacion_revision')->nullable()->after('fecha_revision'),
            'pendientes_al_aprobar' => fn (Blueprint $t) => $t->unsignedSmallInteger('pendientes_al_aprobar')->nullable()->after('observacion_revision'),
        ];
        foreach ($columnas as $columna => $definir) {
            if (! Schema::hasColumn(self::TABLA, $columna)) {
                Schema::table(self::TABLA, $definir);
            }
        }
        if (! $this->foranea()) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->foreign('usuario_reviso_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn(self::TABLA, 'estado_revision')) {
            return;
        }
        if (DB::table(self::TABLA)->where('estado_revision', '!=', 'sin_revisar')->exists()) {
            throw new RuntimeException('No se puede revertir: hay etapas aprobadas o devueltas en los expedientes de contratación, cuya revisión se perdería. Restaure un respaldo.');
        }

        // La clave foránea cae antes que su columna; cada paso con su guarda.
        if ($this->foranea()) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->dropForeign(['usuario_reviso_id']);
            });
        }
        foreach (['pendientes_al_aprobar', 'observacion_revision', 'fecha_revision', 'usuario_reviso_id', 'estado_revision'] as $columna) {
            if (Schema::hasColumn(self::TABLA, $columna)) {
                Schema::table(self::TABLA, function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }
    }

    private function foranea(): ?array
    {
        return collect(Schema::getForeignKeys(self::TABLA))->first(fn ($f) => $f['columns'] === ['usuario_reviso_id']);
    }
};
```

En `app/Models/ExpedienteEtapa.php`, reemplazar (1 de 4):

```php
 *   sugerir hasta que la etapa deje de estar completa y se complete otra vez.
 * Falso con una fecha real: la puso una persona. Falso sin ella: nadie la ha atendido; si la etapa
 * se completa, se sugiere. La API muestra `fecha_real_sugerida` solo en el primer caso (sugerida()).
 */
class ExpedienteEtapa extends Model
{
    protected $table = 'expediente_etapas';
    protected $fillable = ['expediente_id', 'etapa_id', 'fecha_programada', 'fecha_real', 'fecha_real_sugerida', 'usuario_actualizo_id'];
    protected $attributes = ['fecha_real_sugerida' => false];
    protected $casts = [
        'expediente_id' => 'integer',
        'etapa_id' => 'integer',
```

por:

```php
 *   sugerir hasta que la etapa deje de estar completa y se complete otra vez.
 * Falso con una fecha real: la puso una persona. Falso sin ella: nadie la ha atendido; si la etapa
 * se completa, se sugiere. La API muestra `fecha_real_sugerida` solo en el primer caso (sugerida()).
 *
 * Revisión (R15, solo avisa): `estado_revision` es sin_revisar, aprobada o devuelta, con quién la
 * revisó (`usuario_reviso_id`), cuándo (`fecha_revision`), la observación y, al aprobarla, cuántos
 * requisitos de la etapa faltaban (`pendientes_al_aprobar`). El estado que ve la pantalla
 * (pendiente, lista, aprobada, aprobada con cambios, devuelta) se calcula con el checklist:
 * AvisosContratacion::revision().
 */
class ExpedienteEtapa extends Model
{
    protected $table = 'expediente_etapas';
    public const SIN_REVISAR = 'sin_revisar';
    public const APROBADA = 'aprobada';
    public const DEVUELTA = 'devuelta';

    protected $fillable = [
        'expediente_id', 'etapa_id', 'fecha_programada', 'fecha_real', 'fecha_real_sugerida', 'usuario_actualizo_id',
        'estado_revision', 'usuario_reviso_id', 'fecha_revision', 'observacion_revision', 'pendientes_al_aprobar',
    ];
    protected $attributes = ['fecha_real_sugerida' => false, 'estado_revision' => self::SIN_REVISAR];
    protected $casts = [
        'expediente_id' => 'integer',
        'etapa_id' => 'integer',
```

En `app/Models/ExpedienteEtapa.php`, reemplazar (2 de 4):

```php
        'fecha_real' => 'date',
        'fecha_real_sugerida' => 'boolean',
        'usuario_actualizo_id' => 'integer',
    ];

    /** La fecha real la puso el sistema y nadie la ha tocado. */
```

por:

```php
        'fecha_real' => 'date',
        'fecha_real_sugerida' => 'boolean',
        'usuario_actualizo_id' => 'integer',
        'usuario_reviso_id' => 'integer',
        'fecha_revision' => 'datetime',
        'pendientes_al_aprobar' => 'integer',
    ];

    /** La fecha real la puso el sistema y nadie la ha tocado. */
```

En `app/Models/ExpedienteEtapa.php`, reemplazar (3 de 4):

```php
        return $this->fecha_real_sugerida && $this->fecha_real === null;
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(ProyectoExpediente::class, 'expediente_id');
```

por:

```php
        return $this->fecha_real_sugerida && $this->fecha_real === null;
    }

    public function aprobada(): bool
    {
        return $this->estado_revision === self::APROBADA;
    }

    public function devuelta(): bool
    {
        return $this->estado_revision === self::DEVUELTA;
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(ProyectoExpediente::class, 'expediente_id');
```

En `app/Models/ExpedienteEtapa.php`, reemplazar (4 de 4):

```php
    {
        return $this->belongsTo(EtapaContratacion::class, 'etapa_id');
    }
}
```

por:

```php
    {
        return $this->belongsTo(EtapaContratacion::class, 'etapa_id');
    }

    public function usuarioReviso(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_reviso_id');
    }
}
```

En `app/Services/Contratacion/AvisosContratacion.php`, reemplazar (1 de 4):

```php

namespace App\Services\Contratacion;

use App\Models\ProcesoLicitacion;
use App\Models\ProyectoExpediente;
use Carbon\CarbonInterface;
```

por:

```php

namespace App\Services\Contratacion;

use App\Models\ExpedienteEtapa;
use App\Models\ProcesoLicitacion;
use App\Models\ProyectoExpediente;
use Carbon\CarbonInterface;
```

En `app/Services/Contratacion/AvisosContratacion.php`, reemplazar (2 de 4):

```php
 *   expediente cerrado.
 * - Hitos: etapas completas de las que tienen requisitos que cuentan. Las filas sin etapa no son
 *   una etapa: no cuentan como hito ni avisan.
 */
final class AvisosContratacion
{
```

por:

```php
 *   expediente cerrado.
 * - Hitos: etapas completas de las que tienen requisitos que cuentan. Las filas sin etapa no son
 *   una etapa: no cuentan como hito ni avisan.
 * - Revisión de una etapa (R15, solo avisa), el estado que ve la pantalla: aprobada, o aprobada
 *   con cambios si después de aprobarla hubo un evento del checklist en un requisito de la etapa
 *   o un cambio de los requisitos del expediente (una versión nueva); devuelta, mientras no haya
 *   un evento del checklist de la etapa después de devolverla; si no, pendiente (le faltan
 *   requisitos que cuentan) o lista (no le falta nada). Ni la aprobación ni la devolución se
 *   pierden por los cambios: solo cambia lo que se muestra.
 */
final class AvisosContratacion
{
```

En `app/Services/Contratacion/AvisosContratacion.php`, reemplazar (3 de 4):

```php
    /** Etapa que no se completó en un expediente ya cerrado. */
    public const SIN_COMPLETAR = 'sin_completar';

    /** Estados en los que el proceso ya terminó: no avisa etapas vencidas ni próximas ni que está sin actualización. */
    public const ESTADOS_FINALES = [
        ProcesoLicitacion::CELEBRADO, ProcesoLicitacion::DESIERTO, ProcesoLicitacion::CANCELADO, ProcesoLicitacion::REVOCADO,
```

por:

```php
    /** Etapa que no se completó en un expediente ya cerrado. */
    public const SIN_COMPLETAR = 'sin_completar';

    /** Estados visibles de la revisión de una etapa. */
    public const REVISION_PENDIENTE = 'pendiente';
    public const REVISION_LISTA = 'lista';
    public const REVISION_APROBADA = 'aprobada';
    public const REVISION_CON_CAMBIOS = 'aprobada_con_cambios';
    public const REVISION_DEVUELTA = 'devuelta';

    /** Estados en los que el proceso ya terminó: no avisa etapas vencidas ni próximas ni que está sin actualización. */
    public const ESTADOS_FINALES = [
        ProcesoLicitacion::CELEBRADO, ProcesoLicitacion::DESIERTO, ProcesoLicitacion::CANCELADO, ProcesoLicitacion::REVOCADO,
```

En `app/Services/Contratacion/AvisosContratacion.php`, reemplazar (4 de 4):

```php
        return $programada <= $hoy->copy()->addDays($this->diasProxima())->toDateString() ? self::PROXIMA : self::AL_DIA;
    }

    /**
     * Resumen de avisos de un proceso.
     *
```

por:

```php
        return $programada <= $hoy->copy()->addDays($this->diasProxima())->toDateString() ? self::PROXIMA : self::AL_DIA;
    }

    /**
     * La revisión de una etapa, con su estado visible. Espera cargado `usuarioReviso` en la fila.
     *
     * @param array{total: int, cumplidos: int} $avance el de la etapa en avance_por_etapa
     * @param ?ExpedienteEtapa $fila la de la etapa en el cronograma, o null si no tiene
     * @param ?CarbonInterface $cambioEtapa el último evento del checklist en un requisito de la etapa, después de su revisión
     * @param ?CarbonInterface $cambioRequisitos el último cambio de los requisitos del expediente (versión nueva), después de su revisión
     * @return array{estado: string, usuario: ?string, fecha: ?string, observacion: ?string, pendientes_al_aprobar: ?int, devuelta_at: ?string}
     */
    public function revision(array $avance, ?ExpedienteEtapa $fila, ?CarbonInterface $cambioEtapa = null, ?CarbonInterface $cambioRequisitos = null): array
    {
        $fecha = $fila?->fecha_revision;
        $despues = fn (?CarbonInterface $cambio) => $cambio !== null && $fecha !== null && $cambio->greaterThan($fecha);
        if ($fila?->aprobada()) {
            $estado = $despues($cambioEtapa) || $despues($cambioRequisitos) ? self::REVISION_CON_CAMBIOS : self::REVISION_APROBADA;
        } elseif ($fila?->devuelta() && ! $despues($cambioEtapa)) {
            $estado = self::REVISION_DEVUELTA;
        } else {
            $estado = $avance['total'] > $avance['cumplidos'] ? self::REVISION_PENDIENTE : self::REVISION_LISTA;
        }

        return [
            'estado' => $estado,
            'usuario' => $fila?->usuarioReviso?->name,
            'fecha' => $fecha?->toIso8601String(),
            'observacion' => $fila?->observacion_revision,
            'pendientes_al_aprobar' => $fila?->pendientes_al_aprobar,
            'devuelta_at' => $fila?->devuelta() ? $fecha?->toIso8601String() : null,
        ];
    }

    /** La revisión dice que la etapa está aprobada (con o sin cambios después). */
    public function aprobada(?array $revision): bool
    {
        return in_array($revision['estado'] ?? null, [self::REVISION_APROBADA, self::REVISION_CON_CAMBIOS], true);
    }

    /**
     * Resumen de avisos de un proceso.
     *
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 8):

```php
use App\Models\User;
use App\Services\Contratacion\AvisosContratacion;
use App\Services\Contratacion\PlantillasContratacion;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
```

por:

```php
use App\Models\User;
use App\Services\Contratacion\AvisosContratacion;
use App\Services\Contratacion\PlantillasContratacion;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 8):

```php
 *   se vuelve a sugerir mientras lo siga (fechasTrasElAvance, el único lugar). El avance
 *   por etapa presentado trae sus fechas y su semáforo, y el expediente sus avisos
 *   (AvisosContratacion).
 */
final class ExpedienteService
{
```

por:

```php
 *   se vuelve a sugerir mientras lo siga (fechasTrasElAvance, el único lugar). El avance
 *   por etapa presentado trae sus fechas y su semáforo, y el expediente sus avisos
 *   (AvisosContratacion).
 * - Revisión por etapa (solo contratación, RevisionEtapas): el avance por etapa presentado trae
 *   la revisión de cada etapa con su estado visible, calculado en lote con los eventos del
 *   checklist posteriores a la revisión (cambiosTrasLaRevision), y el aviso de la etapa anterior
 *   sin aprobar. Nada de la revisión bloquea las acciones del checklist ni el cierre.
 */
final class ExpedienteService
{
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (3 de 8):

```php
                'tipologias' => $this->opciones($expediente->tipologias),
            ]]),
            'progreso' => $contratacion ? $this->progresoPonderado($filas, $posiciones) : $this->progresoDe($expediente),
            ...($contratacion ? $this->cronogramaYAvisos($expediente, $this->avancePorEtapa($filas, $posiciones), $this->cronogramaDe($expediente)) : []),
            'requisitos' => $filas->map(fn (ProyectoRequisito $f) => $this->presentarFila($f, $posiciones))->values(),
            // Los más parecidos, hasta MAX_AVISOS; el total dice cuántos hay en realidad.
            'avisos_duplicados' => $avisos['avisos'],
```

por:

```php
                'tipologias' => $this->opciones($expediente->tipologias),
            ]]),
            'progreso' => $contratacion ? $this->progresoPonderado($filas, $posiciones) : $this->progresoDe($expediente),
            ...($contratacion ? $this->cronogramaYAvisos($expediente, $this->avancePorEtapa($filas, $posiciones), $this->cronogramaDe($expediente), $posiciones) : []),
            'requisitos' => $filas->map(fn (ProyectoRequisito $f) => $this->presentarFila($f, $posiciones))->values(),
            // Los más parecidos, hasta MAX_AVISOS; el total dice cuántos hay en realidad.
            'avisos_duplicados' => $avisos['avisos'],
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (4 de 8):

```php

        return [
            'progreso' => $this->progresoPonderado($filas, $posiciones),
            ...$this->cronogramaYAvisos($expediente, $avance, $cronograma),
        ];
    }
```

por:

```php

        return [
            'progreso' => $this->progresoPonderado($filas, $posiciones),
            ...$this->cronogramaYAvisos($expediente, $avance, $cronograma, $posiciones),
        ];
    }
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (5 de 8):

```php
     */
    public function cronogramaDeContratacion(ProyectoExpediente $expediente): array
    {
        return $this->cronogramaYAvisos($expediente, $this->avancePorEtapaDe($expediente), $this->cronogramaDe($expediente));
    }

    /** @return Collection<int, ExpedienteEtapa> etapa_id => fechas de la etapa */
    private function cronogramaDe(ProyectoExpediente $expediente): Collection
    {
        return $expediente->etapasCronograma()->get()->keyBy('etapa_id');
    }

    /**
```

por:

```php
     */
    public function cronogramaDeContratacion(ProyectoExpediente $expediente): array
    {
        $posiciones = $this->posiciones($this->plantillas->versionesDe($expediente));

        return $this->cronogramaYAvisos(
            $expediente,
            $this->avancePorEtapa($this->filasDelAvance($expediente), $posiciones),
            $this->cronogramaDe($expediente),
            $posiciones
        );
    }

    /** @return Collection<int, ExpedienteEtapa> etapa_id => fechas y revisión de la etapa, con quién la revisó */
    private function cronogramaDe(ProyectoExpediente $expediente): Collection
    {
        return $expediente->etapasCronograma()->with('usuarioReviso:id,name')->get()->keyBy('etapa_id');
    }

    /**
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (6 de 8):

```php
    }

    /**
     * El avance por etapa con las fechas y el semáforo de cada etapa, y los avisos del expediente.
     *
     * @param list<array> $avance el de avancePorEtapa()
     * @param Collection<int, ExpedienteEtapa> $cronograma el de cronogramaDe()
     * @return array{avance_por_etapa: list<array>, avisos: array}
     */
    private function cronogramaYAvisos(ProyectoExpediente $expediente, array $avance, Collection $cronograma): array
    {
        $presentado = $this->presentarCronograma($avance, $cronograma, $expediente->esBorrador());
        $expediente->loadMissing('proceso');

        return [
```

por:

```php
    }

    /**
     * El avance por etapa con las fechas, el semáforo y la revisión de cada etapa, y los avisos
     * del expediente.
     *
     * @param list<array> $avance el de avancePorEtapa()
     * @param Collection<int, ExpedienteEtapa> $cronograma el de cronogramaDe()
     * @param array<int, int> $posiciones las del expediente, para la etapa de las filas con cambios
     * @return array{avance_por_etapa: list<array>, avisos: array}
     */
    private function cronogramaYAvisos(ProyectoExpediente $expediente, array $avance, Collection $cronograma, array $posiciones): array
    {
        $cambios = $this->cambiosTrasLaRevision([$expediente->id => ['posiciones' => $posiciones, 'cronograma' => $cronograma]]);
        $presentado = $this->presentarCronograma($avance, $cronograma, $expediente->esBorrador(), $cambios[$expediente->id] ?? []);
        $expediente->loadMissing('proceso');

        return [
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (7 de 8):

```php

    /**
     * Cada etapa del avance con `fecha_programada`, `fecha_real`, `fecha_real_sugerida`,
     * `completa` y `alerta` (AvisosContratacion::alerta). Las filas sin etapa no tienen fechas.
     *
     * @param list<array> $avance el de avancePorEtapa()
     * @param Collection<int, ExpedienteEtapa> $cronograma etapa_id => fechas
     * @param bool $vigila el expediente está en borrador: avisa vencimientos
     * @return list<array>
     */
    public function presentarCronograma(array $avance, Collection $cronograma, bool $vigila): array
    {
        return array_map(function (array $etapa) use ($cronograma, $vigila) {
            $fila = $etapa['etapa'] !== null ? $cronograma->get($etapa['etapa']['id']) : null;
            $programada = $fila?->fecha_programada?->format('Y-m-d');

            return [
                ...$etapa,
                'fecha_programada' => $programada,
                'fecha_real' => $fila?->fecha_real?->format('Y-m-d'),
                'fecha_real_sugerida' => (bool) $fila?->sugerida(),
                'completa' => $this->avisos->completa($etapa),
                'alerta' => $this->avisos->alerta($etapa, $programada, $vigila),
            ];
        }, $avance);
    }

    /** @return list<array{etapa: ?array, total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}> el de avancePorEtapa() */
```

por:

```php

    /**
     * Cada etapa del avance con `fecha_programada`, `fecha_real`, `fecha_real_sugerida`,
     * `completa`, `alerta` (AvisosContratacion::alerta), `revision` (AvisosContratacion::revision)
     * y `aviso_etapa_anterior`: alguna etapa anterior con requisitos que cuentan no está aprobada
     * y esta ya tiene algún requisito cumplido. Las filas sin etapa no tienen fechas ni revisión
     * (`revision` null) ni avisan de la etapa anterior.
     *
     * @param list<array> $avance el de avancePorEtapa()
     * @param Collection<int, ExpedienteEtapa> $cronograma etapa_id => fechas y revisión
     * @param bool $vigila el expediente está en borrador: avisa vencimientos
     * @param array{etapas?: array<int, CarbonInterface>, requisitos?: ?CarbonInterface} $cambios los de cambiosTrasLaRevision()
     * @return list<array>
     */
    public function presentarCronograma(array $avance, Collection $cronograma, bool $vigila, array $cambios = []): array
    {
        $presentado = [];
        $anteriorSinAprobar = false;
        foreach ($avance as $etapa) {
            $fila = $etapa['etapa'] !== null ? $cronograma->get($etapa['etapa']['id']) : null;
            $programada = $fila?->fecha_programada?->format('Y-m-d');
            $revision = $etapa['etapa'] !== null
                ? $this->avisos->revision($etapa, $fila, $cambios['etapas'][$etapa['etapa']['id']] ?? null, $cambios['requisitos'] ?? null)
                : null;

            $presentado[] = [
                ...$etapa,
                'fecha_programada' => $programada,
                'fecha_real' => $fila?->fecha_real?->format('Y-m-d'),
                'fecha_real_sugerida' => (bool) $fila?->sugerida(),
                'completa' => $this->avisos->completa($etapa),
                'alerta' => $this->avisos->alerta($etapa, $programada, $vigila),
                'revision' => $revision,
                'aviso_etapa_anterior' => $revision !== null && $anteriorSinAprobar && $etapa['cumplidos'] > 0,
            ];
            if ($revision !== null && $etapa['total'] > 0 && ! $this->avisos->aprobada($revision)) {
                $anteriorSinAprobar = true;
            }
        }

        return $presentado;
    }

    /**
     * Lo que cambió en el checklist de cada expediente después de revisar sus etapas, en lote
     * (las consultas no crecen con los expedientes ni con las etapas): por etapa, el último
     * evento del historial en un requisito cuya etapa mostrada es esa (también los de filas ya
     * eliminadas o que cambiaron de etapa, por la etapa propia que guarda el registro), y el
     * último cambio de los requisitos de todo el expediente (una versión nueva aplicada). Solo
     * mira los expedientes con alguna etapa revisada y los eventos posteriores a la primera
     * revisión entre todos ellos.
     *
     * @param array<int, array{posiciones: array<int, int>, cronograma: Collection<int, ExpedienteEtapa>}> $expedientes expediente_id => datos
     * @return array<int, array{etapas: array<int, CarbonInterface>, requisitos: ?CarbonInterface}> expediente_id => cambios; sin revisiones, no aparece
     */
    private function cambiosTrasLaRevision(array $expedientes): array
    {
        $desde = [];
        foreach ($expedientes as $id => $datos) {
            $primera = $datos['cronograma']
                ->filter(fn (ExpedienteEtapa $f) => $f->estado_revision !== ExpedienteEtapa::SIN_REVISAR && $f->fecha_revision !== null)
                ->min('fecha_revision');
            if ($primera !== null) {
                $desde[$id] = $primera;
            }
        }
        if ($desde === []) {
            return [];
        }

        $eventos = DB::table('checklist_historial')
            ->whereIn('expediente_id', array_keys($desde))
            ->where('created_at', '>', min($desde))
            ->where(fn ($q) => $q->where('entidad_tipo', HistorialChecklist::REQUISITO)
                ->orWhere(fn ($q) => $q->where('entidad_tipo', HistorialChecklist::EXPEDIENTE)->where('evento', 'version_aplicada')))
            ->get(['expediente_id', 'entidad_tipo', 'entidad_id', 'antes', 'despues', 'created_at']);
        $idsFilas = $eventos->where('entidad_tipo', HistorialChecklist::REQUISITO)->pluck('entidad_id')->unique()->values();
        $filas = $idsFilas->isEmpty() ? collect() : ProyectoRequisito::query()
            ->whereIn('id', $idsFilas)
            ->with(['requisito', 'origenes.version:id,plantilla_id', ...self::RELACIONES_ETAPA])
            ->get()
            ->keyBy('id');

        $cambios = [];
        $mas = fn (?CarbonInterface $a, CarbonInterface $b) => $a === null || $b->greaterThan($a) ? $b : $a;
        foreach ($eventos as $evento) {
            $id = (int) $evento->expediente_id;
            $momento = Carbon::parse($evento->created_at);
            $cambios[$id] ??= ['etapas' => [], 'requisitos' => null];
            if ($evento->entidad_tipo === HistorialChecklist::EXPEDIENTE) {
                $cambios[$id]['requisitos'] = $mas($cambios[$id]['requisitos'], $momento);
                continue;
            }
            $etapas = [];
            $fila = $filas->get((int) $evento->entidad_id);
            if ($fila) {
                $etapas[] = $this->etapaDe($fila, $expedientes[$id]['posiciones'])?->id;
            }
            foreach ([$evento->antes, $evento->despues] as $datos) {
                $etapas[] = json_decode((string) $datos, true)['etapa_propia_id'] ?? null;
            }
            foreach (array_unique(array_filter($etapas)) as $etapaId) {
                $cambios[$id]['etapas'][(int) $etapaId] = $mas($cambios[$id]['etapas'][(int) $etapaId] ?? null, $momento);
            }
        }

        return $cambios;
    }

    /** @return list<array{etapa: ?array, total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}> el de avancePorEtapa() */
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (8 de 8):

```php
            ->with(['requisito', 'origenes.version:id,plantilla_id', ...self::RELACIONES_ETAPA])
            ->get()
            ->groupBy('expediente_id');
        $cronogramas = ExpedienteEtapa::query()->whereIn('expediente_id', $ids)->get()->groupBy('expediente_id');

        return $expedientes->mapWithKeys(function (ProyectoExpediente $e) use ($filas, $cronogramas) {
            $deEste = $filas->get($e->id, collect());
            $posiciones = $this->posiciones($this->plantillas->ordenar($e->versionesPlantilla));

            return [$e->id => [
                'progreso' => $this->progresoPonderado($deEste, $posiciones),
                'avance_por_etapa' => $this->presentarCronograma(
                    $this->avancePorEtapa($deEste, $posiciones),
                    $cronogramas->get($e->id, collect())->keyBy('etapa_id'),
                    $e->esBorrador()
                ),
            ]];
        })->all();
```

por:

```php
            ->with(['requisito', 'origenes.version:id,plantilla_id', ...self::RELACIONES_ETAPA])
            ->get()
            ->groupBy('expediente_id');
        $cronogramas = ExpedienteEtapa::query()->whereIn('expediente_id', $ids)->with('usuarioReviso:id,name')->get()->groupBy('expediente_id');
        $datos = $expedientes->mapWithKeys(fn (ProyectoExpediente $e) => [$e->id => [
            'posiciones' => $this->posiciones($this->plantillas->ordenar($e->versionesPlantilla)),
            'cronograma' => $cronogramas->get($e->id, collect())->keyBy('etapa_id'),
        ]])->all();
        $cambios = $this->cambiosTrasLaRevision($datos);

        return $expedientes->mapWithKeys(function (ProyectoExpediente $e) use ($filas, $datos, $cambios) {
            $deEste = $filas->get($e->id, collect());
            $posiciones = $datos[$e->id]['posiciones'];

            return [$e->id => [
                'progreso' => $this->progresoPonderado($deEste, $posiciones),
                'avance_por_etapa' => $this->presentarCronograma(
                    $this->avancePorEtapa($deEste, $posiciones),
                    $datos[$e->id]['cronograma'],
                    $e->esBorrador(),
                    $cambios[$e->id] ?? []
                ),
            ]];
        })->all();
```

En `routes/viabilizacion.php`, reemplazar (1 de 2):

```php
use App\Http\Controllers\PlantillaChecklistController;
use App\Http\Controllers\PlantillasExpedienteController;
use App\Http\Controllers\RequisitoPropioController;
use Illuminate\Support\Facades\Route;

// Lectura: cualquier usuario autenticado.
```

por:

```php
use App\Http\Controllers\PlantillaChecklistController;
use App\Http\Controllers\PlantillasExpedienteController;
use App\Http\Controllers\RequisitoPropioController;
use App\Http\Controllers\RevisionEtapasController;
use Illuminate\Support\Facades\Route;

// Lectura: cualquier usuario autenticado.
```

En `routes/viabilizacion.php`, reemplazar (2 de 2):

```php
    // Solo los de contratación: fechas programada y real de una etapa.
    Route::put('/expedientes/{expediente}/etapas/{etapa}', [CronogramaExpedienteController::class, 'update'])
        ->whereNumber('etapa')->name('expedientes.etapas.update');
    Route::delete('/expedientes/{expediente}', [ExpedienteController::class, 'destroy'])
        ->name('expedientes.destroy');
```

por:

```php
    // Solo los de contratación: fechas programada y real de una etapa.
    Route::put('/expedientes/{expediente}/etapas/{etapa}', [CronogramaExpedienteController::class, 'update'])
        ->whereNumber('etapa')->name('expedientes.etapas.update');
    // Solo los de contratación: revisión de una etapa (solo avisa).
    Route::post('/expedientes/{expediente}/etapas/{etapa}/aprobar', [RevisionEtapasController::class, 'aprobar'])
        ->whereNumber('etapa')->name('expedientes.etapas.aprobar');
    Route::post('/expedientes/{expediente}/etapas/{etapa}/devolver', [RevisionEtapasController::class, 'devolver'])
        ->whereNumber('etapa')->name('expedientes.etapas.devolver');
    Route::delete('/expedientes/{expediente}', [ExpedienteController::class, 'destroy'])
        ->name('expedientes.destroy');
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/RevisionEtapasApiTest.php tests/Feature/Schema/RevisionEtapasTest.php tests/Feature/Contratacion/AvancePonderadoApiTest.php tests/Feature/Contratacion/CronogramaEtapasApiTest.php tests/Feature/Contratacion/ExpedienteContratacionApiTest.php tests/Feature/Schema/ExpedienteEtapasTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1282 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Exceptions/AprobacionConPendientesException.php app/Http/Controllers/RevisionEtapasController.php app/Services/Contratacion/RevisionEtapas.php database/migrations/2026_11_19_100022_add_revision_a_expediente_etapas.php tests/Feature/Contratacion/RevisionEtapasApiTest.php tests/Feature/Schema/RevisionEtapasTest.php app/Models/ExpedienteEtapa.php app/Services/Contratacion/AvisosContratacion.php app/Services/Viabilizacion/ExpedienteService.php routes/viabilizacion.php tests/Feature/Contratacion/AvancePonderadoApiTest.php tests/Feature/Contratacion/CronogramaEtapasApiTest.php tests/Feature/Contratacion/ExpedienteContratacionApiTest.php tests/Feature/Schema/ExpedienteEtapasTest.php
git commit -m "feat: stage review for contracting expedients (migration 23)"
```

---

### Tarea 3: Avisos y cierre según la aprobación; reaprobar y correcciones de la revisión

Los avisos siguen la aprobación (decisión 1): la `alerta` `aprobada`, las completas sin aprobar siguen vencidas o próximas, los hitos cuentan las aprobadas y `avisos` gana `etapas_por_revisar` y `etapas_devueltas`. El cierre avisa también de las etapas sin aprobar (decisión 3, `CierreConPendientesException`). Además, las correcciones de la revisión independiente: reaprobar una etapa `aprobada_con_cambios` sin devolverla, detectar los cambios por el id del historial (no por la hora), restaurar la fecha sugerida previa al devolver, el estado `sin_requisitos` y `pendientes_vistos` en la confirmación.

**Files:**
- Modify: `app/Exceptions/CierreConPendientesException.php`
- Modify: `app/Http/Controllers/RevisionEtapasController.php`
- Modify: `app/Services/Contratacion/AvisosContratacion.php`
- Modify: `app/Services/Contratacion/RevisionEtapas.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Test: `tests/Feature/Contratacion/AvisosProcesosApiTest.php`
- Test: `tests/Feature/Contratacion/CronogramaEtapasApiTest.php`
- Test: `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`
- Test: `tests/Feature/Contratacion/ProcesosContratacionApiTest.php`
- Test: `tests/Feature/Contratacion/RevisionEtapasApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `tests/Feature/Contratacion/AvisosProcesosApiTest.php`, reemplazar (1 de 2):

```php
        'sin_actualizacion' => false,
        'dias_sin_actualizacion' => 0,
        'hitos' => ['completadas' => 0, 'total' => 0],
    ];

    private Proyecto $proyecto;
```

por:

```php
        'sin_actualizacion' => false,
        'dias_sin_actualizacion' => 0,
        'hitos' => ['completadas' => 0, 'total' => 0],
        'etapas_por_revisar' => 0,
        'etapas_devueltas' => 0,
    ];

    private Proyecto $proyecto;
```

En `tests/Feature/Contratacion/AvisosProcesosApiTest.php`, reemplazar (2 de 2):

```php
            'dias_para_cierre' => 2,
            'sin_actualizacion' => false,
            'dias_sin_actualizacion' => 0,
            'hitos' => ['completadas' => 1, 'total' => 3],
        ], $procesos[0]['avisos']);
        // El bloque del expediente no cambia.
        $this->assertSame(['id', 'estado', 'progreso', 'aviso_plantilla'], array_keys($procesos[0]['expediente']));
```

por:

```php
            'dias_para_cierre' => 2,
            'sin_actualizacion' => false,
            'dias_sin_actualizacion' => 0,
            // Hitos = etapas aprobadas (fase 5, decisión 1): el cierre está completo pero sin aprobar.
            'hitos' => ['completadas' => 0, 'total' => 3],
            'etapas_por_revisar' => 1,
            'etapas_devueltas' => 0,
        ], $procesos[0]['avisos']);
        // El bloque del expediente no cambia.
        $this->assertSame(['id', 'estado', 'progreso', 'aviso_plantilla'], array_keys($procesos[0]['expediente']));
```

En `tests/Feature/Contratacion/CronogramaEtapasApiTest.php`, reemplazar (1 de 2):

```php
            ->assertJsonPath('avisos.etapas_proximas', 1)
            ->assertJsonPath('avisos.hitos', ['completadas' => 0, 'total' => 3]);

        // Hoy mismo también es próxima; una completa con la fecha pasada ya no avisa.
        $this->fechas($expediente, 'cierre', ['fecha_programada' => self::HOY])->assertOk()->assertJsonPath('etapa.alerta', 'proxima');
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $r = $this->leer($expediente);
        $this->assertSame(['completa', 'proxima', 'proxima'], array_column($r->json('avance_por_etapa'), 'alerta'));
        $r->assertJsonPath('avisos.etapas_vencidas', 0)
            ->assertJsonPath('avisos.etapas_proximas', 2)
            ->assertJsonPath('avisos.hitos', ['completadas' => 1, 'total' => 3]);

        // Al pasar los días, la próxima se vence.
        $this->travelTo('2026-11-23 08:00:00');
        $this->assertSame(['completa', 'vencida', 'vencida'], array_column($this->leer($expediente)->json('avance_por_etapa'), 'alerta'));
    }

    public function test_un_expediente_cerrado_no_avisa_vencimientos(): void
```

por:

```php
            ->assertJsonPath('avisos.etapas_proximas', 1)
            ->assertJsonPath('avisos.hitos', ['completadas' => 0, 'total' => 3]);

        // Hoy mismo también es próxima; una completa con la fecha pasada sigue vencida mientras no
        // se apruebe, y no cuenta como hito (fase 5, decisión 1).
        $this->fechas($expediente, 'cierre', ['fecha_programada' => self::HOY])->assertOk()->assertJsonPath('etapa.alerta', 'proxima');
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $r = $this->leer($expediente);
        $this->assertSame(['vencida', 'proxima', 'proxima'], array_column($r->json('avance_por_etapa'), 'alerta'));
        $r->assertJsonPath('avisos.etapas_vencidas', 1)
            ->assertJsonPath('avisos.etapas_proximas', 2)
            ->assertJsonPath('avisos.hitos', ['completadas' => 0, 'total' => 3]);

        // Al pasar los días, la próxima se vence.
        $this->travelTo('2026-11-23 08:00:00');
        $this->assertSame(['vencida', 'vencida', 'vencida'], array_column($this->leer($expediente)->json('avance_por_etapa'), 'alerta'));
    }

    public function test_un_expediente_cerrado_no_avisa_vencimientos(): void
```

En `tests/Feature/Contratacion/CronogramaEtapasApiTest.php`, reemplazar (2 de 2):

```php
            'sin_actualizacion' => false,
            'dias_sin_actualizacion' => 14,
            'hitos' => ['completadas' => 0, 'total' => 3],
        ]);
        $this->travelTo('2026-11-16 05:30:00');
        $this->leer($expediente)->assertJsonPath('avisos.sin_actualizacion', true)->assertJsonPath('avisos.dias_sin_actualizacion', 15);
```

por:

```php
            'sin_actualizacion' => false,
            'dias_sin_actualizacion' => 14,
            'hitos' => ['completadas' => 0, 'total' => 3],
            'etapas_por_revisar' => 0,
            'etapas_devueltas' => 0,
        ]);
        $this->travelTo('2026-11-16 05:30:00');
        $this->leer($expediente)->assertJsonPath('avisos.sin_actualizacion', true)->assertJsonPath('avisos.dias_sin_actualizacion', 15);
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (1 de 3):

```php
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar")
            ->assertStatus(422)
            ->assertExactJson([
                'mensaje' => 'Faltan 2 requisitos por cumplir. Confirma para cerrar el expediente de todos modos.',
                'pendientes' => [
                    'total' => 2,
                    'por_etapa' => [
                        ['etapa' => $this->etapaJson('planeacion'), 'pendientes' => 1],
                        ['etapa' => $this->etapaJson('pliego_definitivo'), 'pendientes' => 1],
                    ],
                ],
            ]);
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'));
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => false])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'Falta 1 requisito por cumplir. Confirma para cerrar el expediente de todos modos.')
            ->assertJsonPath('pendientes.por_etapa', [['etapa' => $this->etapaJson('pliego_definitivo'), 'pendientes' => 1]]);

        $this->assertSame(ProyectoExpediente::BORRADOR, $expediente->fresh()->estado);
```

por:

```php
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar")
            ->assertStatus(422)
            ->assertExactJson([
                // Fase 5, decisión 3: también avisa las etapas sin aprobar.
                'mensaje' => 'Faltan 2 requisitos por cumplir y 2 etapas sin aprobar. Confirma para cerrar el expediente de todos modos.',
                'pendientes' => [
                    'total' => 2,
                    'por_etapa' => [
                        ['etapa' => $this->etapaJson('planeacion'), 'pendientes' => 1],
                        ['etapa' => $this->etapaJson('pliego_definitivo'), 'pendientes' => 1],
                    ],
                    'etapas_sin_aprobar' => [
                        ['etapa' => $this->etapaJson('planeacion'), 'estado' => 'pendiente'],
                        ['etapa' => $this->etapaJson('pliego_definitivo'), 'estado' => 'pendiente'],
                    ],
                ],
            ]);
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'));
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => false])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'Falta 1 requisito por cumplir y 2 etapas sin aprobar. Confirma para cerrar el expediente de todos modos.')
            ->assertJsonPath('pendientes.por_etapa', [['etapa' => $this->etapaJson('pliego_definitivo'), 'pendientes' => 1]]);

        $this->assertSame(ProyectoExpediente::BORRADOR, $expediente->fresh()->estado);
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (2 de 3):

```php
                    ['etapa' => $this->etapaJson('planeacion'), 'pendientes' => 1],
                    ['etapa' => $this->etapaJson('pliego_definitivo'), 'pendientes' => 1],
                ],
            ],
        ], json_decode($entrada->despues, true));
    }
```

por:

```php
                    ['etapa' => $this->etapaJson('planeacion'), 'pendientes' => 1],
                    ['etapa' => $this->etapaJson('pliego_definitivo'), 'pendientes' => 1],
                ],
                // Fase 5, decisión 3: también las etapas sin aprobar.
                'etapas_sin_aprobar' => [
                    ['etapa' => $this->etapaJson('planeacion'), 'estado' => 'pendiente'],
                    ['etapa' => $this->etapaJson('pliego_definitivo'), 'estado' => 'pendiente'],
                ],
            ],
        ], json_decode($entrada->despues, true));
    }
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (3 de 3):

```php
        $expediente = $this->expediente();
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'));
        $this->cumplir($expediente, $this->fila($expediente, 'Pliego de condiciones'));

        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar")->assertOk()
            ->assertJsonPath('estado', ProyectoExpediente::CERRADO)
            ->assertJsonPath('progreso.porcentaje', 100);
        $this->assertSame(
            ['requisitos' => 2, 'cumplidos' => 2, 'pendientes' => ['total' => 0, 'por_etapa' => []]],
            json_decode(DB::table('checklist_historial')->where('evento', 'expediente_cerrado')->value('despues'), true)
        );
    }
```

por:

```php
        $expediente = $this->expediente();
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'));
        $this->cumplir($expediente, $this->fila($expediente, 'Pliego de condiciones'));
        // Fase 5, decisión 3: sin pendientes es también con las etapas aprobadas.
        foreach (['planeacion', 'pliego_definitivo'] as $etapa) {
            $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa($etapa)->id}/aprobar")->assertOk();
        }

        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar")->assertOk()
            ->assertJsonPath('estado', ProyectoExpediente::CERRADO)
            ->assertJsonPath('progreso.porcentaje', 100);
        $this->assertSame(
            ['requisitos' => 2, 'cumplidos' => 2, 'pendientes' => ['total' => 0, 'por_etapa' => [], 'etapas_sin_aprobar' => []]],
            json_decode(DB::table('checklist_historial')->where('evento', 'expediente_cerrado')->value('despues'), true)
        );
    }
```

En `tests/Feature/Contratacion/ProcesosContratacionApiTest.php`, reemplazar (1 de 1):

```php
                'etapas_vencidas' => 0, 'etapas_proximas' => 0, 'cierre_proximo' => false, 'cierre_vencido' => false,
                'dias_para_cierre' => null, 'sin_actualizacion' => false, 'dias_sin_actualizacion' => 0,
                'hitos' => ['completadas' => 0, 'total' => 0],
            ],
            'creado' => $r->json('creado'),
            'actualizado' => $r->json('actualizado'),
```

por:

```php
                'etapas_vencidas' => 0, 'etapas_proximas' => 0, 'cierre_proximo' => false, 'cierre_vencido' => false,
                'dias_para_cierre' => null, 'sin_actualizacion' => false, 'dias_sin_actualizacion' => 0,
                'hitos' => ['completadas' => 0, 'total' => 0],
                'etapas_por_revisar' => 0, 'etapas_devueltas' => 0,
            ],
            'creado' => $r->json('creado'),
            'actualizado' => $r->json('actualizado'),
```

En `tests/Feature/Contratacion/RevisionEtapasApiTest.php`, reemplazar (1 de 3):

```php

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\ExpedienteEtapa;
use App\Models\FuenteFinanciacion;
```

por:

```php

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\Contrato;
use App\Models\EtapaContratacion;
use App\Models\ExpedienteEtapa;
use App\Models\FuenteFinanciacion;
```

En `tests/Feature/Contratacion/RevisionEtapasApiTest.php`, reemplazar (2 de 3):

```php
        $this->assertSame('aprobada_con_cambios', $r->json('avance_por_etapa.0.revision.estado'));
        $this->assertSame('aprobada', $r->json('avance_por_etapa.1.revision.estado'));
        $this->assertSame(ExpedienteEtapa::APROBADA, ExpedienteEtapa::where('etapa_id', $this->etapa('planeacion')->id)->value('estado_revision'));
        // Ya está aprobada: no se aprueba otra vez.
        $this->aprobar($expediente, 'planeacion')->assertStatus(422);

        // Una observación en un requisito de la etapa también es un cambio.
        $this->travelTo(self::HOY . ' 10:15:00');
```

por:

```php
        $this->assertSame('aprobada_con_cambios', $r->json('avance_por_etapa.0.revision.estado'));
        $this->assertSame('aprobada', $r->json('avance_por_etapa.1.revision.estado'));
        $this->assertSame(ExpedienteEtapa::APROBADA, ExpedienteEtapa::where('etapa_id', $this->etapa('planeacion')->id)->value('estado_revision'));
        // Con cambios, se puede volver a aprobar (ver test_una_aprobada_con_cambios_se_reaprueba_sin_tocar_la_fecha_real).
        $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertOk()->assertJsonPath('etapa.revision.estado', 'aprobada');

        // Una observación en un requisito de la etapa también es un cambio.
        $this->travelTo(self::HOY . ' 10:15:00');
```

En `tests/Feature/Contratacion/RevisionEtapasApiTest.php`, reemplazar (3 de 3):

```php
        $this->assertSame($conUno, $this->contarConsultas($url));
    }

    public function test_la_viabilizacion_no_cambia(): void
    {
        $r = $this->leer($this->viabilizacion());
```

por:

```php
        $this->assertSame($conUno, $this->contarConsultas($url));
    }

    // ------------------------------------------------------------ avisos (decisión 1)

    private function alertas(ProyectoExpediente $expediente): array
    {
        return array_column($this->leer($expediente)->json('avance_por_etapa'), 'alerta');
    }

    public function test_una_etapa_completa_sin_aprobar_sigue_vencida_o_proxima_hasta_aprobarla(): void
    {
        $expediente = $this->expediente();
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-18']);
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_programada' => '2026-11-22']);
        $this->fechas($expediente, 'cierre', ['fecha_programada' => '2026-12-31']);
        foreach (['Estudios previos', 'CDP', 'Pliego de condiciones', 'Acta de cierre'] as $texto) {
            $this->marcar($expediente, $texto);
        }

        // Completas sin aprobar: la vencida y la próxima siguen avisando; la de fecha lejana, completa.
        $r = $this->leer($expediente);
        $this->assertSame(['vencida', 'proxima', 'completa'], array_column($r->json('avance_por_etapa'), 'alerta'));
        $this->assertSame([true, true, true], array_column($r->json('avance_por_etapa'), 'completa'));
        $r->assertJsonPath('avisos.etapas_vencidas', 1)
            ->assertJsonPath('avisos.etapas_proximas', 1)
            ->assertJsonPath('avisos.etapas_por_revisar', 3)
            ->assertJsonPath('avisos.etapas_devueltas', 0)
            ->assertJsonPath('avisos.hitos', ['completadas' => 0, 'total' => 3]);

        // Aprobada, con o sin pendientes: deja de avisar y cuenta como hito.
        $this->aprobar($expediente, 'planeacion')->assertOk()
            ->assertJsonPath('etapa.alerta', 'aprobada')
            ->assertJsonPath('avisos.etapas_vencidas', 0)
            ->assertJsonPath('avisos.etapas_por_revisar', 2)
            ->assertJsonPath('avisos.hitos', ['completadas' => 1, 'total' => 3]);
        $this->marcar($expediente, 'Pliego de condiciones', false);
        $this->aprobar($expediente, 'pliego_definitivo', ['confirmar' => true])->assertOk()
            ->assertJsonPath('etapa.alerta', 'aprobada')
            ->assertJsonPath('avisos.etapas_proximas', 0)
            ->assertJsonPath('avisos.hitos', ['completadas' => 2, 'total' => 3]);

        // Con cambios después de aprobarla, sigue aprobada para los avisos.
        $this->travelTo(self::HOY . ' 10:05:00');
        $this->marcar($expediente, 'CDP', false);
        $this->assertSame('aprobada_con_cambios', $this->revisionDe($expediente, 'planeacion')['estado']);
        $this->assertSame(['aprobada', 'aprobada', 'completa'], $this->alertas($expediente));
        $this->leer($expediente)->assertJsonPath('avisos.hitos', ['completadas' => 2, 'total' => 3])->assertJsonPath('avisos.etapas_vencidas', 0);

        // Al pasar los días, la que no se aprobó se vence aunque esté completa.
        $this->travelTo('2027-01-01 08:00:00');
        $this->assertSame(['aprobada', 'aprobada', 'vencida'], $this->alertas($expediente));
    }

    public function test_una_completa_sin_fecha_queda_completa_y_las_devueltas_se_cuentan(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Acta de cierre');
        $this->assertSame(['sin_fecha', 'sin_fecha', 'completa'], $this->alertas($expediente));

        $this->devolver($expediente, 'cierre', ['observacion' => 'Falta la firma.'])->assertOk()
            ->assertJsonPath('etapa.alerta', 'completa')
            ->assertJsonPath('avisos.etapas_devueltas', 1)
            ->assertJsonPath('avisos.etapas_por_revisar', 0);
        $this->devolver($expediente, 'planeacion', ['observacion' => 'Faltan los estudios.'])->assertOk()
            ->assertJsonPath('avisos.etapas_devueltas', 2);

        // Con un cambio en su checklist, la devuelta vuelve a estar por revisar si está completa.
        $this->travelTo(self::HOY . ' 10:05:00');
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, 'Acta de cierre')->id}", ['observacion' => 'Firmada.'])->assertOk()
            ->assertJsonPath('avisos.etapas_devueltas', 1)
            ->assertJsonPath('avisos.etapas_por_revisar', 1);
    }

    public function test_un_expediente_cerrado_muestra_las_aprobadas_y_no_cuenta_revisiones(): void
    {
        $expediente = $this->expediente();
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-01']);
        $this->marcar($expediente, 'Pliego de condiciones');
        $this->marcar($expediente, 'Acta de cierre');
        $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertOk();
        $this->devolver($expediente, 'pliego_definitivo', ['observacion' => 'No.'])->assertOk();
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertOk();

        $this->travelTo('2026-12-31 08:00:00');
        $r = $this->leer($expediente);
        $this->assertSame(['aprobada', 'completa', 'completa'], array_column($r->json('avance_por_etapa'), 'alerta'));
        $r->assertJsonPath('avisos.etapas_vencidas', 0)
            ->assertJsonPath('avisos.etapas_por_revisar', 0)
            ->assertJsonPath('avisos.etapas_devueltas', 0)
            ->assertJsonPath('avisos.hitos', ['completadas' => 1, 'total' => 3]);
    }

    public function test_los_procesos_la_ficha_y_los_contratos_avisan_con_la_nueva_regla(): void
    {
        $expediente = $this->expediente();
        $proceso = $expediente->proceso;
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-18']);
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $this->devolver($expediente, 'cierre', ['observacion' => 'No.'])->assertOk();
        $contrato = Contrato::create([
            'proyecto' => $this->proyecto->id, 'proceso_licitacion' => $proceso->id, 'n_contrato' => 'C-001',
            'objeto' => 'Construcción del acueducto', 'valor_inicial' => 1000000, 'valor' => 1000000, 'estado' => 'en_ejecucion',
        ]);

        $avisos = $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk()->json('procesos.0.avisos');
        $this->assertSame([
            'etapas_vencidas' => 1,
            'etapas_proximas' => 0,
            'cierre_proximo' => false,
            'cierre_vencido' => false,
            'dias_para_cierre' => null,
            'sin_actualizacion' => false,
            'dias_sin_actualizacion' => 0,
            'hitos' => ['completadas' => 0, 'total' => 3],
            'etapas_por_revisar' => 1,
            'etapas_devueltas' => 1,
        ], $avisos);
        $ficha = $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/ficha")->assertOk();
        $this->assertSame($avisos, $ficha->json('procesos.0.avisos'));
        // La completa vencida sin aprobar también avisa en el contrato.
        $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$contrato->id}/detalle")->assertOk()
            ->assertJsonPath('aviso_proceso.etapas_vencidas', 1);

        $this->aprobar($expediente, 'planeacion')->assertOk();
        $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$contrato->id}/detalle")->assertOk()->assertJsonPath('aviso_proceso', null);
        $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk()
            ->assertJsonPath('procesos.0.avisos.hitos', ['completadas' => 1, 'total' => 3])
            ->assertJsonPath('procesos.0.avisos.etapas_por_revisar', 0);
    }

    // ------------------------------------------------------------ cierre (decisión 3)

    public function test_el_cierre_avisa_tambien_las_etapas_sin_aprobar(): void
    {
        $expediente = $this->expediente();
        $url = "/GestPro/expedientes/{$expediente->id}/cerrar";
        $etapa = fn (string $clave) => ['id' => $this->etapa($clave)->id, 'nombre' => $this->etapa($clave)->nombre, 'orden' => $this->etapa($clave)->orden];
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $this->devolver($expediente, 'cierre', ['observacion' => 'No.'])->assertOk();

        $this->como()->postJson($url)->assertStatus(422)->assertExactJson([
            'mensaje' => 'Faltan 2 requisitos por cumplir y 3 etapas sin aprobar. Confirma para cerrar el expediente de todos modos.',
            'pendientes' => [
                'total' => 2,
                'por_etapa' => [
                    ['etapa' => $etapa('pliego_definitivo'), 'pendientes' => 1],
                    ['etapa' => $etapa('cierre'), 'pendientes' => 1],
                ],
                'etapas_sin_aprobar' => [
                    ['etapa' => $etapa('planeacion'), 'estado' => 'lista'],
                    ['etapa' => $etapa('pliego_definitivo'), 'estado' => 'pendiente'],
                    ['etapa' => $etapa('cierre'), 'estado' => 'devuelta'],
                ],
            ],
        ]);

        // Sin requisitos pendientes, avisa solo las etapas sin aprobar.
        $this->marcar($expediente, 'Pliego de condiciones');
        $this->aprobar($expediente, 'planeacion')->assertOk();
        $this->aprobar($expediente, 'pliego_definitivo')->assertOk();
        $this->marcar($expediente, 'Acta de cierre');
        $this->como()->postJson($url)->assertStatus(422)
            ->assertJsonPath('mensaje', 'Hay 1 etapa sin aprobar. Confirma para cerrar el expediente de todos modos.')
            ->assertJsonPath('pendientes.total', 0)
            // Devuelta y con un cambio después (marcar el acta, aunque sea en el mismo segundo): lista.
            ->assertJsonPath('pendientes.etapas_sin_aprobar', [['etapa' => $etapa('cierre'), 'estado' => 'lista']]);
        $this->assertSame(ProyectoExpediente::BORRADOR, $expediente->fresh()->estado);

        // Confirmando, cierra y el historial guarda lo que faltaba.
        $this->como()->postJson($url, ['confirmar' => true])->assertOk()->assertJsonPath('estado', ProyectoExpediente::CERRADO);
        $despues = json_decode((string) DB::table('checklist_historial')->where('evento', 'expediente_cerrado')->value('despues'), true);
        $this->assertSame([['etapa' => $etapa('cierre'), 'estado' => 'lista']], $despues['pendientes']['etapas_sin_aprobar']);
    }

    public function test_con_todo_aprobado_el_cierre_no_pide_confirmacion(): void
    {
        $expediente = $this->expediente();
        foreach (['Estudios previos', 'CDP', 'Pliego de condiciones'] as $texto) {
            $this->marcar($expediente, $texto);
        }
        $this->aprobar($expediente, 'planeacion')->assertOk();
        $this->aprobar($expediente, 'pliego_definitivo')->assertOk();
        // Aprobada con pendientes: los requisitos sí se avisan, la etapa no.
        $this->aprobar($expediente, 'cierre', ['confirmar' => true])->assertOk();
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar")->assertStatus(422)
            ->assertJsonPath('mensaje', 'Falta 1 requisito por cumplir. Confirma para cerrar el expediente de todos modos.')
            ->assertJsonPath('pendientes.etapas_sin_aprobar', []);

        $this->marcar($expediente, 'Acta de cierre');
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar")->assertOk()->assertJsonPath('estado', ProyectoExpediente::CERRADO);
    }

    // ------------------------------------------------------------ ajustes de la revisión (M1, M2, B1-B4)

    public function test_un_cambio_en_el_mismo_segundo_de_la_revision_cuenta(): void
    {
        $expediente = $this->expediente();
        // Sin mover el reloj: el orden lo da el historial, no la hora al segundo.
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $this->aprobar($expediente, 'planeacion')->assertOk()->assertJsonPath('etapa.revision.estado', 'aprobada');
        $this->marcar($expediente, 'CDP', false)->assertJsonPath('avance_por_etapa.0.revision.estado', 'aprobada_con_cambios');

        $this->devolver($expediente, 'pliego_definitivo', ['observacion' => 'Falta el pliego.'])->assertOk();
        // Un cambio de otra etapa en el mismo segundo no la mueve.
        $this->marcar($expediente, 'Acta de cierre');
        $this->assertSame('devuelta', $this->revisionDe($expediente, 'pliego_definitivo')['estado']);
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, 'Pliego de condiciones')->id}", ['observacion' => 'Ver anexo.'])->assertOk();
        $revision = $this->revisionDe($expediente, 'pliego_definitivo');
        $this->assertSame(['pendiente', 'Falta el pliego.'], [$revision['estado'], $revision['observacion']]);
        $this->marcar($expediente, 'Pliego de condiciones');
        $this->assertSame('lista', $this->revisionDe($expediente, 'pliego_definitivo')['estado']);
    }

    public function test_el_estado_de_la_revision_lee_solo_el_historial_que_necesita(): void
    {
        $expediente = $this->expediente();
        $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertOk();
        $this->marcar($expediente, 'CDP');
        $this->marcar($expediente, 'Acta de cierre');

        $consultas = [];
        DB::listen(function ($q) use (&$consultas) {
            if (str_contains($q->sql, 'checklist_historial')) {
                $consultas[] = $q->sql;
            }
        });
        $this->leer($expediente)->assertJsonPath('avance_por_etapa.0.revision.estado', 'aprobada_con_cambios');

        $this->assertNotEmpty($consultas);
        foreach ($consultas as $sql) {
            // Ni por la hora (al segundo) ni con los antes/después completos: por id, y solo la etapa.
            $this->assertStringNotContainsString('created_at" >', $sql);
            $this->assertDoesNotMatchRegularExpression('/(select|,)\s*"(antes|despues)"/i', $sql);
            $this->assertStringNotContainsString('select *', $sql);
        }
    }

    public function test_una_aprobada_con_cambios_se_reaprueba_sin_tocar_la_fecha_real(): void
    {
        $expediente = $this->expediente();
        $planeacion = $this->etapa('planeacion');
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $this->aprobar($expediente, 'planeacion', ['observacion' => 'Primera revisión.'])->assertOk();

        $this->travelTo('2026-11-20 09:00:00');
        $this->marcar($expediente, 'CDP', false)->assertJsonPath('avance_por_etapa.0.revision.estado', 'aprobada_con_cambios');
        // La misma confirmación que al aprobar.
        $this->aprobar($expediente, 'planeacion')->assertStatus(422)->assertExactJson([
            'mensaje' => 'Falta 1 requisito de esta etapa. Confirma para aprobarla de todos modos.',
            'pendientes' => 1,
        ]);

        $directora = User::factory()->create(['rol' => Rol::Administrador, 'name' => 'Directora']);
        $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($directora))
            ->postJson("/GestPro/expedientes/{$expediente->id}/etapas/{$planeacion->id}/aprobar", ['confirmar' => true, 'observacion' => 'Segunda revisión.'])
            ->assertOk()
            ->assertJsonPath('etapa.revision', [
                'estado' => 'aprobada',
                'usuario' => 'Directora',
                'fecha' => now()->toIso8601String(),
                'observacion' => 'Segunda revisión.',
                'pendientes_al_aprobar' => 1,
                'devuelta_at' => null,
            ])
            // La fecha real sigue siendo la de la primera aprobación.
            ->assertJsonPath('etapa.fecha_real', self::HOY)
            ->assertJsonPath('etapa.fecha_real_sugerida', false);
        $fila = ExpedienteEtapa::sole();
        $this->assertSame([ExpedienteEtapa::APROBADA, $directora->id, 1], [$fila->estado_revision, $fila->usuario_reviso_id, $fila->pendientes_al_aprobar]);

        $base = ['etapa_id' => $planeacion->id, 'etapa' => $planeacion->nombre];
        $this->assertSame([
            [...$base, 'estado_revision' => 'aprobada', 'observacion' => 'Primera revisión.'],
            [...$base, 'estado_revision' => 'aprobada', 'observacion' => 'Segunda revisión.', 'pendientes_al_aprobar' => 1, 'con_pendientes' => true, 'reaprobacion' => true],
        ], $this->historial('etapa_aprobada')[1]);

        // Sin cambios después, sigue sin poder aprobarse otra vez.
        $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertStatus(422)->assertExactJson(['mensaje' => 'La etapa ya está aprobada.']);

        // Sin observación nueva, se conserva la anterior.
        $this->travelTo('2026-11-21 09:00:00');
        $this->marcar($expediente, 'CDP');
        $this->aprobar($expediente, 'planeacion')->assertOk()
            ->assertJsonPath('etapa.revision.observacion', 'Segunda revisión.')
            ->assertJsonPath('etapa.revision.pendientes_al_aprobar', 0)
            ->assertJsonPath('etapa.fecha_real', self::HOY);
        $this->assertCount(3, $this->historial('etapa_aprobada'));
    }

    public function test_devolver_restaura_la_fecha_sugerida_de_antes_de_aprobar(): void
    {
        $expediente = $this->expediente();
        // Completa el 19: sugerida el 19; aprobada el 21: la fecha pasa al 21.
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP')->assertJsonPath('avance_por_etapa.0.fecha_real', self::HOY);
        $this->travelTo('2026-11-21 09:00:00');
        $this->aprobar($expediente, 'planeacion')->assertOk()->assertJsonPath('etapa.fecha_real', '2026-11-21');
        // Devuelta el 23: vuelve la sugerida que tenía antes de aprobar (la del día en que se completó).
        $this->travelTo('2026-11-23 09:00:00');
        $this->devolver($expediente, 'planeacion', ['observacion' => 'Revisar.'])->assertOk()
            ->assertJsonPath('etapa.fecha_real', self::HOY)
            ->assertJsonPath('etapa.fecha_real_sugerida', true);
        $this->assertSame(
            ['fecha_real' => self::HOY, 'fecha_real_sugerida' => true],
            array_intersect_key($this->historial('etapa_devuelta')[0][1], ['fecha_real' => 0, 'fecha_real_sugerida' => 0])
        );

        // Aprobada sin completar (sin fecha antes) y completada después: al devolverla, la regla de
        // la 4B, sugerida = hoy.
        $this->aprobar($expediente, 'pliego_definitivo', ['confirmar' => true])->assertOk()->assertJsonPath('etapa.fecha_real', '2026-11-23');
        $this->travelTo('2026-11-24 09:00:00');
        $this->marcar($expediente, 'Pliego de condiciones');
        $this->travelTo('2026-11-25 09:00:00');
        $this->devolver($expediente, 'pliego_definitivo', ['observacion' => 'Revisar.'])->assertOk()
            ->assertJsonPath('etapa.fecha_real', '2026-11-25')
            ->assertJsonPath('etapa.fecha_real_sugerida', true);

        // Vacía antes de aprobar porque una persona la vació estando completa: también sin fecha
        // antes, así que al devolverla completa, sugerida = hoy.
        $this->marcar($expediente, 'Acta de cierre');
        $this->fechas($expediente, 'cierre', ['fecha_real' => null]);
        $this->aprobar($expediente, 'cierre')->assertOk()->assertJsonPath('etapa.fecha_real', '2026-11-25');
        $this->travelTo('2026-11-26 09:00:00');
        $this->devolver($expediente, 'cierre', ['observacion' => 'Revisar.'])->assertOk()
            ->assertJsonPath('etapa.fecha_real', '2026-11-26')
            ->assertJsonPath('etapa.fecha_real_sugerida', true);
    }

    public function test_devolver_tras_una_reaprobacion_mira_la_fecha_de_la_primera_aprobacion(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $this->travelTo('2026-11-21 09:00:00');
        $this->aprobar($expediente, 'planeacion')->assertOk()->assertJsonPath('etapa.fecha_real', '2026-11-21');
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, 'CDP')->id}", ['observacion' => 'Ver.'])->assertOk();
        $this->aprobar($expediente, 'planeacion')->assertOk()->assertJsonPath('etapa.revision.estado', 'aprobada');

        // La fecha sigue siendo la que puso la primera aprobación: vuelve la sugerida de antes.
        $this->devolver($expediente, 'planeacion', ['observacion' => 'Revisar.'])->assertOk()
            ->assertJsonPath('etapa.fecha_real', self::HOY)
            ->assertJsonPath('etapa.fecha_real_sugerida', true);
    }

    public function test_una_etapa_sin_requisitos_que_cuenten_no_se_revisa(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Acta de cierre');
        $this->aprobar($expediente, 'cierre')->assertOk();
        // El único requisito del cierre pasa a opcional: la etapa sigue en el avance, con total 0.
        $this->fila($expediente, 'Acta de cierre')->update(['caracter' => RequisitoCatalogo::OPCIONAL]);

        $r = $this->leer($expediente);
        $cierre = $this->deEtapa($r->json('avance_por_etapa'), 'cierre');
        $this->assertSame([0, 'sin_requisitos'], [$cierre['total'], $cierre['revision']['estado']]);
        $r->assertJsonPath('avisos.etapas_por_revisar', 0)->assertJsonPath('avisos.hitos', ['completadas' => 0, 'total' => 2]);

        $sinRequisitos = ['mensaje' => 'La etapa no tiene requisitos que cuenten.'];
        $this->aprobar($expediente, 'cierre', ['confirmar' => true])->assertStatus(422)->assertExactJson($sinRequisitos);
        $this->devolver($expediente, 'cierre', ['observacion' => 'No.'])->assertStatus(422)->assertExactJson($sinRequisitos);
        $this->assertCount(1, $this->historial('etapa_aprobada'));
        $this->assertSame([], $this->historial('etapa_devuelta'));

        // Ni pide aprobarla para cerrar.
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $this->marcar($expediente, 'Pliego de condiciones');
        $this->aprobar($expediente, 'planeacion')->assertOk();
        $this->aprobar($expediente, 'pliego_definitivo')->assertOk();
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar")->assertOk();
    }

    public function test_confirmar_con_menos_pendientes_vistos_que_los_actuales_vuelve_a_pedir_confirmacion(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos');
        // La pantalla vio 1 pendiente; mientras tanto alguien desmarcó otro.
        $this->aprobar($expediente, 'planeacion')->assertStatus(422)->assertJsonPath('pendientes', 1);
        $this->marcar($expediente, 'Estudios previos', false);

        $this->aprobar($expediente, 'planeacion', ['confirmar' => true, 'pendientes_vistos' => 1])->assertStatus(422)->assertExactJson([
            'mensaje' => 'Faltan 2 requisitos de esta etapa. Confirma para aprobarla de todos modos.',
            'pendientes' => 2,
        ]);
        $this->assertSame(0, ExpedienteEtapa::count());

        $this->aprobar($expediente, 'planeacion', ['confirmar' => true, 'pendientes_vistos' => 'dos'])->assertStatus(422)
            ->assertJsonValidationErrors('pendientes_vistos');
        $this->aprobar($expediente, 'planeacion', ['confirmar' => true, 'pendientes_vistos' => -1])->assertStatus(422)
            ->assertJsonValidationErrors('pendientes_vistos');
        // Con los que hay (o con menos de los que vio), aprueba.
        $this->aprobar($expediente, 'planeacion', ['confirmar' => true, 'pendientes_vistos' => 2])->assertOk()
            ->assertJsonPath('etapa.revision.pendientes_al_aprobar', 2);
        $this->aprobar($expediente, 'pliego_definitivo', ['confirmar' => true, 'pendientes_vistos' => 5])->assertOk()
            ->assertJsonPath('etapa.revision.pendientes_al_aprobar', 1);
        // Sin pendientes_vistos, confirmar basta, como antes.
        $this->aprobar($expediente, 'cierre', ['confirmar' => true])->assertOk();
    }

    public function test_la_viabilizacion_no_cambia(): void
    {
        $r = $this->leer($this->viabilizacion());
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/AvisosProcesosApiTest.php tests/Feature/Contratacion/CronogramaEtapasApiTest.php tests/Feature/Contratacion/ExpedienteContratacionApiTest.php tests/Feature/Contratacion/ProcesosContratacionApiTest.php tests/Feature/Contratacion/RevisionEtapasApiTest.php`
Expected: FALLAN 22 y pasan 170: en `RevisionEtapasApiTest` fallan 14 de 30, los de los avisos según la aprobación (vencida hasta aprobar, hitos, por revisar y devueltas), el cierre con etapas sin aprobar y las correcciones (reaprobar, un cambio en el mismo segundo, el historial que se lee, la fecha al devolver, `sin_requisitos` y `pendientes_vistos`); en `AvisosProcesosApiTest` fallan 2 y en `ProcesosContratacionApiTest` 1, que esperan `etapas_por_revisar` y `etapas_devueltas` en `avisos`; en `CronogramaEtapasApiTest` fallan 2, que esperan la completa sin aprobar todavía `vencida` y 0 hitos ("Failed asserting that two arrays are identical."); en `ExpedienteContratacionApiTest` fallan 3, los del cierre, que esperan las etapas sin aprobar en el mensaje y en `pendientes` ("Failed asserting that two strings are equal.").

- [ ] **Paso 3: Implementar**

En `app/Exceptions/CierreConPendientesException.php`, reemplazar (1 de 1):

```php
use Illuminate\Http\JsonResponse;

/**
 * Cerrar un expediente de contratación con requisitos pendientes, sin confirmarlo. No es un
 * bloqueo: la pantalla muestra cuántos faltan y en qué etapas, y se cierra confirmando.
 * Responde 422 con el mensaje y `pendientes` ({total, por_etapa: [{etapa, pendientes}]}).
 */
class CierreConPendientesException extends ReglaNegocioException
{
    /** @param array{total: int, por_etapa: list<array{etapa: ?array, pendientes: int}>} $pendientes */
    public function __construct(string $mensaje, private readonly array $pendientes)
    {
        parent::__construct($mensaje);
    }

    /** @return array{total: int, por_etapa: list<array{etapa: ?array, pendientes: int}>} */
    public function pendientes(): array
    {
        return $this->pendientes;
```

por:

```php
use Illuminate\Http\JsonResponse;

/**
 * Cerrar un expediente de contratación con requisitos pendientes o etapas sin aprobar, sin
 * confirmarlo. No es un bloqueo: la pantalla muestra cuántos faltan y en qué etapas, y las etapas
 * sin aprobar, y se cierra confirmando. Responde 422 con el mensaje y `pendientes`
 * ({total, por_etapa: [{etapa, pendientes}], etapas_sin_aprobar: [{etapa, estado}]}).
 */
class CierreConPendientesException extends ReglaNegocioException
{
    /** @param array{total: int, por_etapa: list<array{etapa: ?array, pendientes: int}>, etapas_sin_aprobar: list<array{etapa: array, estado: string}>} $pendientes */
    public function __construct(string $mensaje, private readonly array $pendientes)
    {
        parent::__construct($mensaje);
    }

    /** @return array{total: int, por_etapa: list<array{etapa: ?array, pendientes: int}>, etapas_sin_aprobar: list<array{etapa: array, estado: string}>} */
    public function pendientes(): array
    {
        return $this->pendientes;
```

En `app/Http/Controllers/RevisionEtapasController.php`, reemplazar (1 de 2):

```php
    {
    }

    /** Con requisitos pendientes en la etapa, sin `confirmar: true` responde 422 con cuántos faltan. */
    public function aprobar(Request $request, ProyectoExpediente $expediente, EtapaContratacion $etapa): JsonResponse
    {
        $datos = $request->validate([
            'confirmar' => ['sometimes', 'boolean'],
            'observacion' => ['sometimes', 'nullable', 'string', 'max:' . self::MAX_OBSERVACION],
        ], [
            'confirmar.boolean' => 'Indica si confirmas la aprobación con verdadero o falso.',
            'observacion.string' => 'Escribe la observación como texto.',
            'observacion.max' => 'La observación no puede tener más de ' . self::MAX_OBSERVACION . ' caracteres.',
        ]);
```

por:

```php
    {
    }

    /**
     * Con requisitos pendientes en la etapa, sin `confirmar: true` responde 422 con cuántos faltan;
     * también con `confirmar` si llega `pendientes_vistos` y ahora faltan más de los que vio.
     */
    public function aprobar(Request $request, ProyectoExpediente $expediente, EtapaContratacion $etapa): JsonResponse
    {
        $datos = $request->validate([
            'confirmar' => ['sometimes', 'boolean'],
            'observacion' => ['sometimes', 'nullable', 'string', 'max:' . self::MAX_OBSERVACION],
            'pendientes_vistos' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ], [
            'confirmar.boolean' => 'Indica si confirmas la aprobación con verdadero o falso.',
            'pendientes_vistos.integer' => 'Indica cuántos pendientes viste con un número entero.',
            'pendientes_vistos.min' => 'Los pendientes vistos no pueden ser negativos.',
            'observacion.string' => 'Escribe la observación como texto.',
            'observacion.max' => 'La observación no puede tener más de ' . self::MAX_OBSERVACION . ' caracteres.',
        ]);
```

En `app/Http/Controllers/RevisionEtapasController.php`, reemplazar (2 de 2):

```php
            $etapa,
            (bool) ($datos['confirmar'] ?? false),
            $datos['observacion'] ?? null,
            $request->user()
        ));
    }
```

por:

```php
            $etapa,
            (bool) ($datos['confirmar'] ?? false),
            $datos['observacion'] ?? null,
            $request->user(),
            isset($datos['pendientes_vistos']) ? (int) $datos['pendientes_vistos'] : null
        ));
    }
```

En `app/Services/Contratacion/AvisosContratacion.php`, reemplazar (1 de 8):

```php
 *   historial) se pasan a esa zona antes de tomar su fecha. Los umbrales están en
 *   config/contratacion.php: `dias_proxima` (3) y `dias_sin_actualizacion` (15).
 * - Una etapa está completa si tiene requisitos que cuentan y todos están cumplidos (la misma
 *   definición del avance, sin pesos). Si no lo está, en un expediente en borrador: vencida si su
 *   fecha programada ya pasó; próxima si cae entre hoy y hoy + dias_proxima; al día si es más
 *   lejana; sin fecha si no tiene. Un expediente cerrado no avisa vencimientos: sus etapas sin
 *   completar quedan sin completar (con o sin fecha) y las completas, completas.
 * - Del proceso: en un estado final (celebrado, desierto, cancelado, revocado) no cuenta etapas
 *   vencidas ni próximas: ya terminó. El cierre es próximo si su fecha de cierre cae entre hoy y hoy + dias_proxima, y
 *   vencido si ya pasó, mientras siga en preparación o publicado. Está sin actualización si desde
 *   su última actividad (el máximo entre su updated_at y el último evento del historial de su
 *   expediente) pasaron dias_sin_actualizacion días o más, salvo en un estado final o con el
 *   expediente cerrado.
 * - Hitos: etapas completas de las que tienen requisitos que cuentan. Las filas sin etapa no son
 *   una etapa: no cuentan como hito ni avisan.
 * - Revisión de una etapa (R15, solo avisa), el estado que ve la pantalla: aprobada, o aprobada
 *   con cambios si después de aprobarla hubo un evento del checklist en un requisito de la etapa
 *   o un cambio de los requisitos del expediente (una versión nueva); devuelta, mientras no haya
 *   un evento del checklist de la etapa después de devolverla; si no, pendiente (le faltan
 *   requisitos que cuentan) o lista (no le falta nada). Ni la aprobación ni la devolución se
 *   pierden por los cambios: solo cambia lo que se muestra.
 */
final class AvisosContratacion
{
```

por:

```php
 *   historial) se pasan a esa zona antes de tomar su fecha. Los umbrales están en
 *   config/contratacion.php: `dias_proxima` (3) y `dias_sin_actualizacion` (15).
 * - Una etapa está completa si tiene requisitos que cuentan y todos están cumplidos (la misma
 *   definición del avance, sin pesos). Una etapa aprobada (también con cambios después) avisa
 *   `aprobada` y ya no vence: lo que deja de vencer es la aprobación, no completarla. Si no está
 *   aprobada, en un expediente en borrador: vencida si su fecha programada ya pasó (aunque esté
 *   completa); próxima si cae entre hoy y hoy + dias_proxima; si no, completa (lista para
 *   revisar) si lo está, o al día con una fecha más lejana y sin fecha si no tiene. Un expediente
 *   cerrado no avisa vencimientos: sus etapas sin aprobar quedan completas o sin completar.
 * - Del proceso: en un estado final (celebrado, desierto, cancelado, revocado) no cuenta etapas
 *   vencidas ni próximas: ya terminó. El cierre es próximo si su fecha de cierre cae entre hoy y hoy + dias_proxima, y
 *   vencido si ya pasó, mientras siga en preparación o publicado. Está sin actualización si desde
 *   su última actividad (el máximo entre su updated_at y el último evento del historial de su
 *   expediente) pasaron dias_sin_actualizacion días o más, salvo en un estado final o con el
 *   expediente cerrado.
 * - Hitos: etapas aprobadas de las que tienen requisitos que cuentan. Por revisar: las que tienen
 *   requisitos que cuentan y están listas (completas, sin aprobar ni devueltas); devueltas: las
 *   devueltas sin cambios después. Con el expediente cerrado no hay nada por revisar ni devuelto:
 *   ya no se revisa. Las filas sin etapa no son una etapa: no cuentan como hito ni avisan.
 * - Revisión de una etapa (R15, solo avisa), el estado que ve la pantalla: aprobada, o aprobada
 *   con cambios si después de aprobarla hubo un evento del checklist en un requisito de la etapa
 *   o un cambio de los requisitos del expediente (una versión nueva); devuelta, mientras no haya
 *   un evento del checklist de la etapa después de devolverla; si no, pendiente (le faltan
 *   requisitos que cuentan) o lista (no le falta nada). Ni la aprobación ni la devolución se
 *   pierden por los cambios: solo cambia lo que se muestra. "Después" se decide por el id del
 *   historial, no por la hora. Una etapa sin requisitos que cuenten (total 0: todos opcionales o
 *   no aplica) muestra sin_requisitos, neutro: ni lista ni aprobada, no se revisa ni cuenta.
 */
final class AvisosContratacion
{
```

En `app/Services/Contratacion/AvisosContratacion.php`, reemplazar (2 de 8):

```php
    public const SIN_FECHA = 'sin_fecha';
    /** Etapa que no se completó en un expediente ya cerrado. */
    public const SIN_COMPLETAR = 'sin_completar';

    /** Estados visibles de la revisión de una etapa. */
    public const REVISION_PENDIENTE = 'pendiente';
```

por:

```php
    public const SIN_FECHA = 'sin_fecha';
    /** Etapa que no se completó en un expediente ya cerrado. */
    public const SIN_COMPLETAR = 'sin_completar';
    /** Etapa aprobada en su revisión: ya no avisa. */
    public const APROBADA = 'aprobada';

    /** Estados visibles de la revisión de una etapa. */
    public const REVISION_PENDIENTE = 'pendiente';
```

En `app/Services/Contratacion/AvisosContratacion.php`, reemplazar (3 de 8):

```php
    public const REVISION_APROBADA = 'aprobada';
    public const REVISION_CON_CAMBIOS = 'aprobada_con_cambios';
    public const REVISION_DEVUELTA = 'devuelta';

    /** Estados en los que el proceso ya terminó: no avisa etapas vencidas ni próximas ni que está sin actualización. */
    public const ESTADOS_FINALES = [
```

por:

```php
    public const REVISION_APROBADA = 'aprobada';
    public const REVISION_CON_CAMBIOS = 'aprobada_con_cambios';
    public const REVISION_DEVUELTA = 'devuelta';
    /** La etapa está en el avance pero nada de ella cuenta (total 0): no hay nada que revisar. */
    public const REVISION_SIN_REQUISITOS = 'sin_requisitos';

    /** Estados en los que el proceso ya terminó: no avisa etapas vencidas ni próximas ni que está sin actualización. */
    public const ESTADOS_FINALES = [
```

En `app/Services/Contratacion/AvisosContratacion.php`, reemplazar (4 de 8):

```php
     * @param array{total: int, cumplidos: int} $avance el de la etapa en avance_por_etapa
     * @param ?string $programada su fecha programada, año-mes-día
     * @param bool $vigila el expediente está en borrador: avisa vencimientos
     */
    public function alerta(array $avance, ?string $programada, bool $vigila): string
    {
        if ($this->completa($avance)) {
            return self::COMPLETA;
        }
        if (! $vigila) {
            return self::SIN_COMPLETAR;
        }
        if ($programada === null) {
            return self::SIN_FECHA;
        }
        $hoy = $this->hoy();
        if ($programada < $hoy->toDateString()) {
            return self::VENCIDA;
        }

        return $programada <= $hoy->copy()->addDays($this->diasProxima())->toDateString() ? self::PROXIMA : self::AL_DIA;
    }

    /**
```

por:

```php
     * @param array{total: int, cumplidos: int} $avance el de la etapa en avance_por_etapa
     * @param ?string $programada su fecha programada, año-mes-día
     * @param bool $vigila el expediente está en borrador: avisa vencimientos
     * @param bool $aprobada la revisión de la etapa la aprobó (con o sin cambios después)
     */
    public function alerta(array $avance, ?string $programada, bool $vigila, bool $aprobada = false): string
    {
        if ($aprobada) {
            return self::APROBADA;
        }
        $completa = $this->completa($avance);
        if (! $vigila) {
            return $completa ? self::COMPLETA : self::SIN_COMPLETAR;
        }
        if ($programada === null) {
            return $completa ? self::COMPLETA : self::SIN_FECHA;
        }
        $hoy = $this->hoy();
        if ($programada < $hoy->toDateString()) {
            return self::VENCIDA;
        }
        if ($programada <= $hoy->copy()->addDays($this->diasProxima())->toDateString()) {
            return self::PROXIMA;
        }

        return $completa ? self::COMPLETA : self::AL_DIA;
    }

    /**
```

En `app/Services/Contratacion/AvisosContratacion.php`, reemplazar (5 de 8):

```php
     *
     * @param array{total: int, cumplidos: int} $avance el de la etapa en avance_por_etapa
     * @param ?ExpedienteEtapa $fila la de la etapa en el cronograma, o null si no tiene
     * @param ?CarbonInterface $cambioEtapa el último evento del checklist en un requisito de la etapa, después de su revisión
     * @param ?CarbonInterface $cambioRequisitos el último cambio de los requisitos del expediente (versión nueva), después de su revisión
     * @return array{estado: string, usuario: ?string, fecha: ?string, observacion: ?string, pendientes_al_aprobar: ?int, devuelta_at: ?string}
     */
    public function revision(array $avance, ?ExpedienteEtapa $fila, ?CarbonInterface $cambioEtapa = null, ?CarbonInterface $cambioRequisitos = null): array
    {
        $fecha = $fila?->fecha_revision;
        $despues = fn (?CarbonInterface $cambio) => $cambio !== null && $fecha !== null && $cambio->greaterThan($fecha);
        if ($fila?->aprobada()) {
            $estado = $despues($cambioEtapa) || $despues($cambioRequisitos) ? self::REVISION_CON_CAMBIOS : self::REVISION_APROBADA;
        } elseif ($fila?->devuelta() && ! $despues($cambioEtapa)) {
            $estado = self::REVISION_DEVUELTA;
        } else {
            $estado = $avance['total'] > $avance['cumplidos'] ? self::REVISION_PENDIENTE : self::REVISION_LISTA;
```

por:

```php
     *
     * @param array{total: int, cumplidos: int} $avance el de la etapa en avance_por_etapa
     * @param ?ExpedienteEtapa $fila la de la etapa en el cronograma, o null si no tiene
     * @param bool $cambioEtapa hubo un evento del checklist en un requisito de la etapa después de su revisión vigente
     * @param bool $cambioRequisitos cambiaron los requisitos del expediente (versión nueva) después de su revisión vigente
     * @return array{estado: string, usuario: ?string, fecha: ?string, observacion: ?string, pendientes_al_aprobar: ?int, devuelta_at: ?string}
     */
    public function revision(array $avance, ?ExpedienteEtapa $fila, bool $cambioEtapa = false, bool $cambioRequisitos = false): array
    {
        $fecha = $fila?->fecha_revision;
        if ($avance['total'] === 0) {
            $estado = self::REVISION_SIN_REQUISITOS;
        } elseif ($fila?->aprobada()) {
            $estado = $cambioEtapa || $cambioRequisitos ? self::REVISION_CON_CAMBIOS : self::REVISION_APROBADA;
        } elseif ($fila?->devuelta() && ! $cambioEtapa) {
            $estado = self::REVISION_DEVUELTA;
        } else {
            $estado = $avance['total'] > $avance['cumplidos'] ? self::REVISION_PENDIENTE : self::REVISION_LISTA;
```

En `app/Services/Contratacion/AvisosContratacion.php`, reemplazar (6 de 8):

```php
     * Resumen de avisos de un proceso.
     *
     * @param ?ProyectoExpediente $expediente el de contratación del proceso, o null
     * @param list<array> $avancePorEtapa el de su expediente, ya presentado (con `alerta` y `completa`); [] sin expediente
     * @param ?CarbonInterface $ultimoEvento el último evento del historial de su expediente, o null
     * @return array{etapas_vencidas: int, etapas_proximas: int, cierre_proximo: bool, cierre_vencido: bool, dias_para_cierre: ?int,
     *               sin_actualizacion: bool, dias_sin_actualizacion: ?int, hitos: array{completadas: int, total: int}}
     */
    public function resumen(ProcesoLicitacion $proceso, ?ProyectoExpediente $expediente, array $avancePorEtapa, ?CarbonInterface $ultimoEvento): array
    {
```

por:

```php
     * Resumen de avisos de un proceso.
     *
     * @param ?ProyectoExpediente $expediente el de contratación del proceso, o null
     * @param list<array> $avancePorEtapa el de su expediente, ya presentado (con `alerta`, `completa` y `revision`); [] sin expediente
     * @param ?CarbonInterface $ultimoEvento el último evento del historial de su expediente, o null
     * @return array{etapas_vencidas: int, etapas_proximas: int, cierre_proximo: bool, cierre_vencido: bool, dias_para_cierre: ?int,
     *               sin_actualizacion: bool, dias_sin_actualizacion: ?int, hitos: array{completadas: int, total: int},
     *               etapas_por_revisar: int, etapas_devueltas: int}
     */
    public function resumen(ProcesoLicitacion $proceso, ?ProyectoExpediente $expediente, array $avancePorEtapa, ?CarbonInterface $ultimoEvento): array
    {
```

En `app/Services/Contratacion/AvisosContratacion.php`, reemplazar (7 de 8):

```php
        $ultima = collect([$proceso->updated_at, $ultimoEvento])->filter()->max();
        $diasSin = $ultima ? max(0, $this->dias(Carbon::parse($this->fechaDe($ultima)), $hoy)) : null;
        $terminado = in_array($proceso->estado, self::ESTADOS_FINALES, true);
        $vigente = ! $terminado && $expediente?->estado !== ProyectoExpediente::CERRADO;

        return [
            'etapas_vencidas' => $terminado ? 0 : $alertas[self::VENCIDA] ?? 0,
```

por:

```php
        $ultima = collect([$proceso->updated_at, $ultimoEvento])->filter()->max();
        $diasSin = $ultima ? max(0, $this->dias(Carbon::parse($this->fechaDe($ultima)), $hoy)) : null;
        $terminado = in_array($proceso->estado, self::ESTADOS_FINALES, true);
        $cerrado = $expediente?->estado === ProyectoExpediente::CERRADO;
        $vigente = ! $terminado && ! $cerrado;
        $conRequisitos = array_values(array_filter($etapas, fn (array $a) => $a['total'] > 0));
        $revisiones = array_count_values(array_map(fn (array $a) => $a['revision']['estado'] ?? '', $conRequisitos));

        return [
            'etapas_vencidas' => $terminado ? 0 : $alertas[self::VENCIDA] ?? 0,
```

En `app/Services/Contratacion/AvisosContratacion.php`, reemplazar (8 de 8):

```php
            'sin_actualizacion' => $vigente && $diasSin !== null && $diasSin >= $this->diasSinActualizacion(),
            'dias_sin_actualizacion' => $diasSin,
            'hitos' => [
                'completadas' => count(array_filter($etapas, fn (array $a) => $a['completa'])),
                'total' => count(array_filter($etapas, fn (array $a) => $a['total'] > 0)),
            ],
        ];
    }
```

por:

```php
            'sin_actualizacion' => $vigente && $diasSin !== null && $diasSin >= $this->diasSinActualizacion(),
            'dias_sin_actualizacion' => $diasSin,
            'hitos' => [
                'completadas' => count(array_filter($conRequisitos, fn (array $a) => $this->aprobada($a['revision'] ?? null))),
                'total' => count($conRequisitos),
            ],
            'etapas_por_revisar' => $cerrado ? 0 : $revisiones[self::REVISION_LISTA] ?? 0,
            'etapas_devueltas' => $cerrado ? 0 : $revisiones[self::REVISION_DEVUELTA] ?? 0,
        ];
    }
```

En `app/Services/Contratacion/RevisionEtapas.php`, reemplazar (1 de 7):

```php
 *
 * Reglas:
 * - Solo en un expediente de contratación en borrador, y en una etapa que tiene requisitos en él
 *   (una de su avance por etapa). La fila de la etapa nace si no existe, como con las fechas.
 * - Aprobar: con requisitos que cuentan sin cumplir, sin `confirmar` responde cuántos faltan
 *   (AprobacionConPendientesException) y no aprueba; confirmada, aprueba y guarda cuántos
 *   faltaban. Una etapa ya aprobada no se aprueba otra vez. La fecha real, si está vacía o es la
 *   sugerida, pasa a ser la de hoy (la de la aprobación) y deja de ser sugerida; la que escribió
 *   una persona se respeta. Sigue siendo corregible.
 * - Devolver: con una observación (obligatoria); una devuelta se puede devolver otra vez. Si la
 *   etapa estaba aprobada y su fecha real es la que puso la aprobación (nadie la cambió
 *   después), vuelve a la regla de la sugerida: sugerida si la etapa está completa, vacía si no.
 * - Queda en el historial del expediente (`etapa_aprobada`, `etapa_devuelta`) con la etapa, el
 *   estado de revisión de antes y de después, la observación y la fecha real si cambió.
 * - Sobre el expediente bloqueado, como las acciones del checklist y las fechas de una etapa.
```

por:

```php
 *
 * Reglas:
 * - Solo en un expediente de contratación en borrador, y en una etapa que tiene requisitos en él
 *   (una de su avance por etapa) y alguno que cuente (total > 0). La fila de la etapa nace si no
 *   existe, como con las fechas.
 * - Aprobar: con requisitos que cuentan sin cumplir, sin `confirmar` responde cuántos faltan
 *   (AprobacionConPendientesException) y no aprueba; tampoco si confirma habiendo visto menos
 *   pendientes de los que hay ahora (`pendientes_vistos`); confirmada, aprueba y guarda cuántos
 *   faltaban. Una etapa aprobada sin cambios después no se aprueba otra vez; una aprobada con
 *   cambios (estado visible calculado bajo el bloqueo) se reaprueba: quién, cuándo, la observación
 *   si llega otra y los pendientes, con la misma confirmación, sin tocar la fecha real, y el
 *   historial lo marca (`reaprobacion`). La fecha real, si está vacía o es la
 *   sugerida, pasa a ser la de hoy (la de la aprobación) y deja de ser sugerida; la que escribió
 *   una persona se respeta. Sigue siendo corregible.
 * - Devolver: con una observación (obligatoria); una devuelta se puede devolver otra vez. Si la
 *   etapa estaba aprobada y su fecha real es la que puso la aprobación (nadie la cambió
 *   después), vuelve a la regla de la sugerida: si la etapa está completa, la sugerida que tenía
 *   antes de aprobarse (está en el `antes` del `etapa_aprobada`) o, si no tenía fecha, la de hoy;
 *   vacía si no está completa.
 * - Queda en el historial del expediente (`etapa_aprobada`, `etapa_devuelta`) con la etapa, el
 *   estado de revisión de antes y de después, la observación y la fecha real si cambió.
 * - Sobre el expediente bloqueado, como las acciones del checklist y las fechas de una etapa.
```

En `app/Services/Contratacion/RevisionEtapas.php`, reemplazar (2 de 7):

```php
final class RevisionEtapas
{
    public const YA_APROBADA = 'La etapa ya está aprobada.';

    public function __construct(
        private readonly ExpedienteService $expedientes,
```

por:

```php
final class RevisionEtapas
{
    public const YA_APROBADA = 'La etapa ya está aprobada.';
    public const NADA_QUE_REVISAR = 'La etapa no tiene requisitos que cuenten.';

    public function __construct(
        private readonly ExpedienteService $expedientes,
```

En `app/Services/Contratacion/RevisionEtapas.php`, reemplazar (3 de 7):

```php
    ) {
    }

    /** @return array{etapa: array, avisos: array} la etapa como en avance_por_etapa y los avisos del expediente */
    public function aprobar(ProyectoExpediente $expediente, EtapaContratacion $etapa, bool $confirmar, ?string $observacion, ?User $usuario): array
    {
        return $this->revisar($expediente, $etapa, $usuario, function (ExpedienteEtapa $fila, array $avance) use ($confirmar, $observacion, $usuario) {
            if ($fila->aprobada()) {
                throw new ReglaNegocioException(self::YA_APROBADA);
            }
            $pendientes = $avance['total'] - $avance['cumplidos'];
            if ($pendientes > 0 && ! $confirmar) {
                $falta = $pendientes === 1 ? 'Falta 1 requisito' : "Faltan {$pendientes} requisitos";
                throw new AprobacionConPendientesException("{$falta} de esta etapa. Confirma para aprobarla de todos modos.", $pendientes);
            }

            $antes = $this->revision($fila);
            $fechaAntes = $this->fecha($fila);
            $fijaFecha = $fila->fecha_real === null || $fila->sugerida();
            if ($fijaFecha) {
                $fila->fecha_real = $this->avisos->hoy()->toDateString();
                $fila->fecha_real_sugerida = false;
            }
            $this->guardarRevision($fila, ExpedienteEtapa::APROBADA, $observacion, $pendientes, $usuario);

            return ['etapa_aprobada', [...$antes, ...($fijaFecha ? $fechaAntes : [])], [
```

por:

```php
    ) {
    }

    /**
     * @param ?int $pendientesVistos los pendientes que vio quien confirma: si ahora faltan más, la
     *        confirmación no vale y se vuelve a pedir con el número nuevo
     * @return array{etapa: array, avisos: array} la etapa como en avance_por_etapa y los avisos del expediente
     */
    public function aprobar(ProyectoExpediente $expediente, EtapaContratacion $etapa, bool $confirmar, ?string $observacion, ?User $usuario, ?int $pendientesVistos = null): array
    {
        return $this->revisar($expediente, $etapa, $usuario, function (ExpedienteEtapa $fila, array $avance) use ($confirmar, $observacion, $usuario, $pendientesVistos) {
            // Aprobada con cambios después: se vuelve a aprobar (reaprobación); sin cambios, no.
            $reaprobacion = $fila->aprobada() && ($avance['revision']['estado'] ?? null) === AvisosContratacion::REVISION_CON_CAMBIOS;
            if ($fila->aprobada() && ! $reaprobacion) {
                throw new ReglaNegocioException(self::YA_APROBADA);
            }
            $pendientes = $avance['total'] - $avance['cumplidos'];
            $confirmada = $confirmar && ($pendientesVistos === null || $pendientes <= $pendientesVistos);
            if ($pendientes > 0 && ! $confirmada) {
                $falta = $pendientes === 1 ? 'Falta 1 requisito' : "Faltan {$pendientes} requisitos";
                throw new AprobacionConPendientesException("{$falta} de esta etapa. Confirma para aprobarla de todos modos.", $pendientes);
            }

            $antes = $this->revision($fila);
            $fechaAntes = $this->fecha($fila);
            // La reaprobación no toca la fecha real: la puso (o la respetó) la primera aprobación.
            $fijaFecha = ! $reaprobacion && ($fila->fecha_real === null || $fila->sugerida());
            if ($fijaFecha) {
                $fila->fecha_real = $this->avisos->hoy()->toDateString();
                $fila->fecha_real_sugerida = false;
            }
            // Al reaprobar sin observación nueva, se conserva la anterior.
            $observacion = $reaprobacion ? $observacion ?? $fila->observacion_revision : $observacion;
            $this->guardarRevision($fila, ExpedienteEtapa::APROBADA, $observacion, $pendientes, $usuario);

            return ['etapa_aprobada', [...$antes, ...($fijaFecha ? $fechaAntes : [])], [
```

En `app/Services/Contratacion/RevisionEtapas.php`, reemplazar (4 de 7):

```php
                ...($observacion !== null ? ['observacion' => $observacion] : []),
                'pendientes_al_aprobar' => $pendientes,
                ...($pendientes > 0 ? ['con_pendientes' => true] : []),
                ...($fijaFecha ? $this->fecha($fila) : []),
            ]];
        });
```

por:

```php
                ...($observacion !== null ? ['observacion' => $observacion] : []),
                'pendientes_al_aprobar' => $pendientes,
                ...($pendientes > 0 ? ['con_pendientes' => true] : []),
                ...($reaprobacion ? ['reaprobacion' => true] : []),
                ...($fijaFecha ? $this->fecha($fila) : []),
            ]];
        });
```

En `app/Services/Contratacion/RevisionEtapas.php`, reemplazar (5 de 7):

```php
        return $this->revisar($expediente, $etapa, $usuario, function (ExpedienteEtapa $fila, array $avance, ProyectoExpediente $expediente) use ($etapa, $observacion, $usuario) {
            $antes = $this->revision($fila);
            $fechaAntes = $this->fecha($fila);
            $vuelve = $fila->aprobada() && $this->fechaDeLaAprobacion($expediente, $etapa, $fila);
            if ($vuelve) {
                $completa = $this->avisos->completa($avance);
                $fila->fecha_real = $completa ? $fila->fecha_real : null;
                $fila->fecha_real_sugerida = $completa;
            }
            $this->guardarRevision($fila, ExpedienteEtapa::DEVUELTA, $observacion, null, $usuario);
```

por:

```php
        return $this->revisar($expediente, $etapa, $usuario, function (ExpedienteEtapa $fila, array $avance, ProyectoExpediente $expediente) use ($etapa, $observacion, $usuario) {
            $antes = $this->revision($fila);
            $fechaAntes = $this->fecha($fila);
            $aprobacion = $fila->aprobada() ? $this->aprobacionQueFijoLaFecha($expediente, $etapa, $fila) : null;
            $vuelve = $aprobacion !== null;
            if ($vuelve) {
                // La de antes de aprobar, si era sugerida; sin fecha antes, la de hoy (como al
                // completarse); sin completar, vacía.
                $previa = $aprobacion->antes ?? [];
                $completa = $this->avisos->completa($avance);
                $sugeridaPrevia = ($previa['fecha_real_sugerida'] ?? false) && ($previa['fecha_real'] ?? null) !== null;
                $fila->fecha_real = ! $completa ? null : ($sugeridaPrevia ? $previa['fecha_real'] : $this->avisos->hoy()->toDateString());
                $fila->fecha_real_sugerida = $completa;
            }
            $this->guardarRevision($fila, ExpedienteEtapa::DEVUELTA, $observacion, null, $usuario);
```

En `app/Services/Contratacion/RevisionEtapas.php`, reemplazar (6 de 7):

```php
        DB::transaction(function () use ($expediente, $etapa, $usuario, $accion) {
            $this->expedientes->bloquear($expediente);
            $this->expedientes->exigirBorrador($expediente);
            $avance = collect($this->expedientes->avancePorEtapaDe($expediente))
                ->first(fn (array $a) => ($a['etapa']['id'] ?? null) === $etapa->id);
            if ($avance === null) {
                throw new ReglaNegocioException(CronogramaExpediente::SIN_REQUISITOS);
            }

            $fila = ExpedienteEtapa::query()
                ->where('expediente_id', $expediente->id)
```

por:

```php
        DB::transaction(function () use ($expediente, $etapa, $usuario, $accion) {
            $this->expedientes->bloquear($expediente);
            $this->expedientes->exigirBorrador($expediente);
            // El estado visible de la revisión, calculado bajo el bloqueo.
            $avance = collect($this->expedientes->avancePresentadoDe($expediente))
                ->first(fn (array $a) => ($a['etapa']['id'] ?? null) === $etapa->id);
            if ($avance === null) {
                throw new ReglaNegocioException(CronogramaExpediente::SIN_REQUISITOS);
            }
            // Todo lo de la etapa es opcional o no aplica: no hay nada que aprobar ni devolver.
            if ($avance['total'] === 0) {
                throw new ReglaNegocioException(self::NADA_QUE_REVISAR);
            }

            $fila = ExpedienteEtapa::query()
                ->where('expediente_id', $expediente->id)
```

En `app/Services/Contratacion/RevisionEtapas.php`, reemplazar (7 de 7):

```php
    }

    /**
     * La fecha real de la etapa es la que puso su aprobación vigente: el último `etapa_aprobada`
     * de la etapa la fijó, es la misma de ahora y ningún `etapa_fechas` posterior la cambió.
     */
    private function fechaDeLaAprobacion(ProyectoExpediente $expediente, EtapaContratacion $etapa, ExpedienteEtapa $fila): bool
    {
        $eventos = ChecklistHistorial::query()
            ->where('expediente_id', $expediente->id)
            ->where('entidad_tipo', HistorialChecklist::EXPEDIENTE)
            ->whereIn('evento', ['etapa_aprobada', 'etapa_fechas'])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (ChecklistHistorial $h) => ($h->despues['etapa_id'] ?? null) === $etapa->id);
        foreach ($eventos as $evento) {
            if ($evento->evento === 'etapa_fechas') {
                if (array_key_exists('fecha_real', $evento->despues)) {
                    return false;
                }
                continue;
            }

            return array_key_exists('fecha_real', $evento->despues)
                && $evento->despues['fecha_real'] === $fila->fecha_real?->format('Y-m-d');
        }

        return false;
    }

    /** @return array{estado_revision: string, observacion?: string} */
```

por:

```php
    }

    /**
     * El `etapa_aprobada` de la aprobación vigente, si la fecha real de la etapa es la que puso:
     * esa aprobación la fijó, es la misma de ahora y ningún `etapa_fechas` posterior la cambió.
     * Las reaprobaciones no tocan la fecha: se mira la aprobación anterior a ellas. Su `antes`
     * guarda la fecha real que tenía la etapa antes de aprobarse.
     */
    private function aprobacionQueFijoLaFecha(ProyectoExpediente $expediente, EtapaContratacion $etapa, ExpedienteEtapa $fila): ?ChecklistHistorial
    {
        $eventos = ChecklistHistorial::query()
            ->where('expediente_id', $expediente->id)
            ->where('entidad_tipo', HistorialChecklist::EXPEDIENTE)
            ->whereIn('evento', ['etapa_aprobada', 'etapa_fechas'])
            ->where('despues->etapa_id', $etapa->id)
            ->orderByDesc('id')
            ->get();
        foreach ($eventos as $evento) {
            if ($evento->evento === 'etapa_fechas') {
                if (array_key_exists('fecha_real', $evento->despues)) {
                    return null;
                }
                continue;
            }
            if ($evento->despues['reaprobacion'] ?? false) {
                continue;
            }

            return array_key_exists('fecha_real', $evento->despues)
                && $evento->despues['fecha_real'] === $fila->fecha_real?->format('Y-m-d') ? $evento : null;
        }

        return null;
    }

    /** @return array{estado_revision: string, observacion?: string} */
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 10):

```php
 *   contratación (de un proceso) nace con crearDeProceso(), ya configurado: comparte el
 *   checklist, pero no se configura ni se radica. Sus filas llevan la etapa de contratación
 *   (la del requisito mostrado, o la elegida en un propio), con el avance por etapa, y se
 *   cierra con cerrar(): solo avisa de los pendientes. Su porcentaje de avance es ponderado:
 *   cada fila que cuenta pesa lo del requisito que muestra (un propio pesa 1); `total` y
 *   `cumplidos` siguen siendo conteos. En viabilización el peso no se usa. Cerrado, queda congelado como uno
 *   radicado. La fecha y el usuario del cierre se guardan en fecha_radicado y
```

por:

```php
 *   contratación (de un proceso) nace con crearDeProceso(), ya configurado: comparte el
 *   checklist, pero no se configura ni se radica. Sus filas llevan la etapa de contratación
 *   (la del requisito mostrado, o la elegida en un propio), con el avance por etapa, y se
 *   cierra con cerrar(): solo avisa de los pendientes y de las etapas sin aprobar. Su porcentaje de avance es ponderado:
 *   cada fila que cuenta pesa lo del requisito que muestra (un propio pesa 1); `total` y
 *   `cumplidos` siguen siendo conteos. En viabilización el peso no se usa. Cerrado, queda congelado como uno
 *   radicado. La fecha y el usuario del cierre se guardan en fecha_radicado y
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 10):

```php
 *   (AvisosContratacion).
 * - Revisión por etapa (solo contratación, RevisionEtapas): el avance por etapa presentado trae
 *   la revisión de cada etapa con su estado visible, calculado en lote con los eventos del
 *   checklist posteriores a la revisión (cambiosTrasLaRevision), y el aviso de la etapa anterior
 *   sin aprobar. Nada de la revisión bloquea las acciones del checklist ni el cierre.
 */
final class ExpedienteService
```

por:

```php
 *   (AvisosContratacion).
 * - Revisión por etapa (solo contratación, RevisionEtapas): el avance por etapa presentado trae
 *   la revisión de cada etapa con su estado visible, calculado en lote con los eventos del
 *   checklist posteriores a la revisión, por id del historial (cambiosTrasLaRevision), y el aviso de la etapa anterior
 *   sin aprobar. Nada de la revisión bloquea las acciones del checklist ni el cierre.
 */
final class ExpedienteService
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (3 de 10):

```php
    }

    /**
     * Cierra un expediente de contratación. Solo avisa: con requisitos que cuentan sin cumplir,
     * sin `$confirmar` responde cuántos faltan y en qué etapas (CierreConPendientesException) y
     * no cierra; confirmado, cierra igual y el historial guarda lo que faltaba.
     */
    public function cerrar(ProyectoExpediente $expediente, bool $confirmar, ?User $usuario): ProyectoExpediente
    {
```

por:

```php
    }

    /**
     * Cierra un expediente de contratación. Solo avisa: con requisitos que cuentan sin cumplir o
     * con etapas sin aprobar, sin `$confirmar` responde cuántos requisitos faltan y en qué etapas,
     * y qué etapas no están aprobadas (CierreConPendientesException), y no cierra; confirmado,
     * cierra igual y el historial guarda lo que faltaba.
     */
    public function cerrar(ProyectoExpediente $expediente, bool $confirmar, ?User $usuario): ProyectoExpediente
    {
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (4 de 10):

```php
            $this->bloquear($expediente);
            $this->exigirBorrador($expediente);

            $avance = $this->avancePorEtapaDe($expediente);
            $pendientes = $this->pendientes($avance);
            if ($pendientes['total'] > 0 && ! $confirmar) {
                $falta = $pendientes['total'] === 1 ? 'Falta 1 requisito' : "Faltan {$pendientes['total']} requisitos";
                throw new CierreConPendientesException("{$falta} por cumplir. Confirma para cerrar el expediente de todos modos.", $pendientes);
            }

            $expediente->update([
```

por:

```php
            $this->bloquear($expediente);
            $this->exigirBorrador($expediente);

            $avance = $this->cronogramaDeContratacion($expediente)['avance_por_etapa'];
            $pendientes = $this->pendientes($avance);
            $sinAprobar = count($pendientes['etapas_sin_aprobar']);
            if (($pendientes['total'] > 0 || $sinAprobar > 0) && ! $confirmar) {
                throw new CierreConPendientesException($this->mensajeDeCierre($pendientes['total'], $sinAprobar), $pendientes);
            }

            $expediente->update([
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (5 de 10):

```php
    }

    /**
     * Lo que falta en cada etapa del avance, solo las etapas con algo pendiente.
     *
     * @param list<array{etapa: ?array, total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}> $avance el de avancePorEtapa()
     * @return array{total: int, por_etapa: list<array{etapa: ?array, pendientes: int}>}
     */
    private function pendientes(array $avance): array
    {
        $porEtapa = [];
        foreach ($avance as $etapa) {
            if ($etapa['total'] > $etapa['cumplidos']) {
                $porEtapa[] = ['etapa' => $etapa['etapa'], 'pendientes' => $etapa['total'] - $etapa['cumplidos']];
            }
        }

        return ['total' => array_sum(array_column($porEtapa, 'pendientes')), 'por_etapa' => $porEtapa];
    }

    public function eliminar(ProyectoExpediente $expediente, ?User $usuario = null): void
```

por:

```php
    }

    /**
     * Lo que falta en cada etapa del avance, solo las etapas con algo pendiente, y las etapas con
     * requisitos que cuentan que no están aprobadas, con su estado de revisión visible.
     *
     * @param list<array> $avance el presentado, con `revision` (presentarCronograma())
     * @return array{total: int, por_etapa: list<array{etapa: ?array, pendientes: int}>, etapas_sin_aprobar: list<array{etapa: array, estado: string}>}
     */
    private function pendientes(array $avance): array
    {
        $porEtapa = [];
        $sinAprobar = [];
        foreach ($avance as $etapa) {
            if ($etapa['total'] > $etapa['cumplidos']) {
                $porEtapa[] = ['etapa' => $etapa['etapa'], 'pendientes' => $etapa['total'] - $etapa['cumplidos']];
            }
            if ($etapa['revision'] !== null && $etapa['total'] > 0 && ! $this->avisos->aprobada($etapa['revision'])) {
                $sinAprobar[] = ['etapa' => $etapa['etapa'], 'estado' => $etapa['revision']['estado']];
            }
        }

        return ['total' => array_sum(array_column($porEtapa, 'pendientes')), 'por_etapa' => $porEtapa, 'etapas_sin_aprobar' => $sinAprobar];
    }

    /** El aviso del cierre: los requisitos por cumplir, las etapas sin aprobar o ambos. */
    private function mensajeDeCierre(int $requisitos, int $etapas): string
    {
        $confirma = 'Confirma para cerrar el expediente de todos modos.';
        $deEtapas = $etapas === 1 ? '1 etapa sin aprobar' : "{$etapas} etapas sin aprobar";
        if ($requisitos === 0) {
            return "Hay {$deEtapas}. {$confirma}";
        }
        $falta = $requisitos === 1 ? 'Falta 1 requisito por cumplir' : "Faltan {$requisitos} requisitos por cumplir";

        return $etapas === 0 ? "{$falta}. {$confirma}" : "{$falta} y {$deEtapas}. {$confirma}";
    }

    public function eliminar(ProyectoExpediente $expediente, ?User $usuario = null): void
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (6 de 10):

```php
     * @param list<array> $avance el de avancePorEtapa()
     * @param Collection<int, ExpedienteEtapa> $cronograma etapa_id => fechas y revisión
     * @param bool $vigila el expediente está en borrador: avisa vencimientos
     * @param array{etapas?: array<int, CarbonInterface>, requisitos?: ?CarbonInterface} $cambios los de cambiosTrasLaRevision()
     * @return list<array>
     */
    public function presentarCronograma(array $avance, Collection $cronograma, bool $vigila, array $cambios = []): array
```

por:

```php
     * @param list<array> $avance el de avancePorEtapa()
     * @param Collection<int, ExpedienteEtapa> $cronograma etapa_id => fechas y revisión
     * @param bool $vigila el expediente está en borrador: avisa vencimientos
     * @param array<int, array{checklist: bool, requisitos: bool}> $cambios etapa_id => cambios tras su revisión, los de cambiosTrasLaRevision()
     * @return list<array>
     */
    public function presentarCronograma(array $avance, Collection $cronograma, bool $vigila, array $cambios = []): array
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (7 de 10):

```php
            $fila = $etapa['etapa'] !== null ? $cronograma->get($etapa['etapa']['id']) : null;
            $programada = $fila?->fecha_programada?->format('Y-m-d');
            $revision = $etapa['etapa'] !== null
                ? $this->avisos->revision($etapa, $fila, $cambios['etapas'][$etapa['etapa']['id']] ?? null, $cambios['requisitos'] ?? null)
                : null;

            $presentado[] = [
```

por:

```php
            $fila = $etapa['etapa'] !== null ? $cronograma->get($etapa['etapa']['id']) : null;
            $programada = $fila?->fecha_programada?->format('Y-m-d');
            $revision = $etapa['etapa'] !== null
                ? $this->avisos->revision($etapa, $fila, $cambios[$etapa['etapa']['id']]['checklist'] ?? false, $cambios[$etapa['etapa']['id']]['requisitos'] ?? false)
                : null;

            $presentado[] = [
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (8 de 10):

```php
                'fecha_real' => $fila?->fecha_real?->format('Y-m-d'),
                'fecha_real_sugerida' => (bool) $fila?->sugerida(),
                'completa' => $this->avisos->completa($etapa),
                'alerta' => $this->avisos->alerta($etapa, $programada, $vigila),
                'revision' => $revision,
                'aviso_etapa_anterior' => $revision !== null && $anteriorSinAprobar && $etapa['cumplidos'] > 0,
            ];
```

por:

```php
                'fecha_real' => $fila?->fecha_real?->format('Y-m-d'),
                'fecha_real_sugerida' => (bool) $fila?->sugerida(),
                'completa' => $this->avisos->completa($etapa),
                'alerta' => $this->avisos->alerta($etapa, $programada, $vigila, $this->avisos->aprobada($revision)),
                'revision' => $revision,
                'aviso_etapa_anterior' => $revision !== null && $anteriorSinAprobar && $etapa['cumplidos'] > 0,
            ];
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (9 de 10):

```php

    /**
     * Lo que cambió en el checklist de cada expediente después de revisar sus etapas, en lote
     * (las consultas no crecen con los expedientes ni con las etapas): por etapa, el último
     * evento del historial en un requisito cuya etapa mostrada es esa (también los de filas ya
     * eliminadas o que cambiaron de etapa, por la etapa propia que guarda el registro), y el
     * último cambio de los requisitos de todo el expediente (una versión nueva aplicada). Solo
     * mira los expedientes con alguna etapa revisada y los eventos posteriores a la primera
     * revisión entre todos ellos.
     *
     * @param array<int, array{posiciones: array<int, int>, cronograma: Collection<int, ExpedienteEtapa>}> $expedientes expediente_id => datos
     * @return array<int, array{etapas: array<int, CarbonInterface>, requisitos: ?CarbonInterface}> expediente_id => cambios; sin revisiones, no aparece
     */
    private function cambiosTrasLaRevision(array $expedientes): array
    {
        $desde = [];
        foreach ($expedientes as $id => $datos) {
            $primera = $datos['cronograma']
                ->filter(fn (ExpedienteEtapa $f) => $f->estado_revision !== ExpedienteEtapa::SIN_REVISAR && $f->fecha_revision !== null)
                ->min('fecha_revision');
            if ($primera !== null) {
                $desde[$id] = $primera;
            }
        }
        if ($desde === []) {
            return [];
        }

        $eventos = DB::table('checklist_historial')
            ->whereIn('expediente_id', array_keys($desde))
            ->where('created_at', '>', min($desde))
            ->where(fn ($q) => $q->where('entidad_tipo', HistorialChecklist::REQUISITO)
                ->orWhere(fn ($q) => $q->where('entidad_tipo', HistorialChecklist::EXPEDIENTE)->where('evento', 'version_aplicada')))
            ->get(['expediente_id', 'entidad_tipo', 'entidad_id', 'antes', 'despues', 'created_at']);
        $idsFilas = $eventos->where('entidad_tipo', HistorialChecklist::REQUISITO)->pluck('entidad_id')->unique()->values();
        $filas = $idsFilas->isEmpty() ? collect() : ProyectoRequisito::query()
            ->whereIn('id', $idsFilas)
```

por:

```php

    /**
     * Lo que cambió en el checklist de cada expediente después de revisar sus etapas, en lote
     * (las consultas no crecen con los expedientes ni con las etapas). El orden lo da el id del
     * historial (monótono y asignado bajo el bloqueo del expediente), no la hora al segundo: la
     * revisión vigente de una etapa es su último `etapa_aprobada`/`etapa_devuelta`, y cuentan los
     * eventos de requisito de esa etapa con un id mayor (también los de filas ya eliminadas o que
     * cambiaron de etapa, por la etapa propia que guarda el registro) y, para las aprobadas, una
     * versión nueva aplicada después. Solo mira los expedientes con alguna etapa revisada, y de
     * cada uno, el historial posterior a la más antigua de sus revisiones vigentes. Del historial
     * lee solo los ids, el tipo y la etapa (no los antes/después completos).
     *
     * @param array<int, array{posiciones: array<int, int>, cronograma: Collection<int, ExpedienteEtapa>}> $expedientes expediente_id => datos
     * @return array<int, array<int, array{checklist: bool, requisitos: bool}>> expediente_id => etapa_id => cambios
     *         después de su revisión: `checklist` en un requisito de la etapa, `requisitos` una versión nueva; sin revisiones, no aparece
     */
    private function cambiosTrasLaRevision(array $expedientes): array
    {
        $revisadas = [];
        foreach ($expedientes as $id => $datos) {
            $etapas = $datos['cronograma']
                ->filter(fn (ExpedienteEtapa $f) => $f->estado_revision !== ExpedienteEtapa::SIN_REVISAR)
                ->keys()
                ->all();
            if ($etapas !== []) {
                $revisadas[$id] = array_fill_keys($etapas, true);
            }
        }
        if ($revisadas === []) {
            return [];
        }

        // La revisión vigente de cada etapa revisada: el id de su último evento de revisión.
        $revisiones = [];
        $eventosDeRevision = DB::table('checklist_historial')
            ->whereIn('expediente_id', array_keys($revisadas))
            ->where('entidad_tipo', HistorialChecklist::EXPEDIENTE)
            ->whereIn('evento', ['etapa_aprobada', 'etapa_devuelta'])
            ->get(['id', 'expediente_id', 'despues->etapa_id as etapa_id']);
        foreach ($eventosDeRevision as $evento) {
            $id = (int) $evento->expediente_id;
            $etapaId = (int) $evento->etapa_id;
            if (isset($revisadas[$id][$etapaId])) {
                $revisiones[$id][$etapaId] = max($revisiones[$id][$etapaId] ?? 0, (int) $evento->id);
            }
        }
        if ($revisiones === []) {
            return [];
        }

        $eventos = DB::table('checklist_historial')
            ->where(function ($q) use ($revisiones) {
                foreach ($revisiones as $id => $porEtapa) {
                    $q->orWhere(fn ($q) => $q->where('expediente_id', $id)->where('id', '>', min($porEtapa)));
                }
            })
            ->where(fn ($q) => $q->where('entidad_tipo', HistorialChecklist::REQUISITO)
                ->orWhere(fn ($q) => $q->where('entidad_tipo', HistorialChecklist::EXPEDIENTE)->where('evento', 'version_aplicada')))
            ->get(['id', 'expediente_id', 'entidad_tipo', 'entidad_id', 'antes->etapa_propia_id as antes_etapa', 'despues->etapa_propia_id as despues_etapa']);
        $idsFilas = $eventos->where('entidad_tipo', HistorialChecklist::REQUISITO)->pluck('entidad_id')->unique()->values();
        $filas = $idsFilas->isEmpty() ? collect() : ProyectoRequisito::query()
            ->whereIn('id', $idsFilas)
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (10 de 10):

```php
            ->get()
            ->keyBy('id');

        $cambios = [];
        $mas = fn (?CarbonInterface $a, CarbonInterface $b) => $a === null || $b->greaterThan($a) ? $b : $a;
        foreach ($eventos as $evento) {
            $id = (int) $evento->expediente_id;
            $momento = Carbon::parse($evento->created_at);
            $cambios[$id] ??= ['etapas' => [], 'requisitos' => null];
            if ($evento->entidad_tipo === HistorialChecklist::EXPEDIENTE) {
                $cambios[$id]['requisitos'] = $mas($cambios[$id]['requisitos'], $momento);
                continue;
            }
            $etapas = [];
            $fila = $filas->get((int) $evento->entidad_id);
            if ($fila) {
                $etapas[] = $this->etapaDe($fila, $expedientes[$id]['posiciones'])?->id;
            }
            foreach ([$evento->antes, $evento->despues] as $datos) {
                $etapas[] = json_decode((string) $datos, true)['etapa_propia_id'] ?? null;
            }
            foreach (array_unique(array_filter($etapas)) as $etapaId) {
                $cambios[$id]['etapas'][(int) $etapaId] = $mas($cambios[$id]['etapas'][(int) $etapaId] ?? null, $momento);
            }
        }

        return $cambios;
    }

    /** @return list<array{etapa: ?array, total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}> el de avancePorEtapa() */
    public function avancePorEtapaDe(ProyectoExpediente $expediente): array
    {
```

por:

```php
            ->get()
            ->keyBy('id');

        // El último evento de cada etapa y la última versión aplicada de cada expediente, por id.
        $ultimoDeEtapa = [];
        $ultimaVersion = [];
        foreach ($eventos as $evento) {
            $id = (int) $evento->expediente_id;
            if ($evento->entidad_tipo === HistorialChecklist::EXPEDIENTE) {
                $ultimaVersion[$id] = max($ultimaVersion[$id] ?? 0, (int) $evento->id);
                continue;
            }
            $fila = $filas->get((int) $evento->entidad_id);
            $etapas = [$fila ? $this->etapaDe($fila, $expedientes[$id]['posiciones'])?->id : null, $evento->antes_etapa, $evento->despues_etapa];
            foreach (array_unique(array_map('intval', array_filter($etapas))) as $etapaId) {
                $ultimoDeEtapa[$id][$etapaId] = max($ultimoDeEtapa[$id][$etapaId] ?? 0, (int) $evento->id);
            }
        }

        $cambios = [];
        foreach ($revisiones as $id => $porEtapa) {
            foreach ($porEtapa as $etapaId => $revision) {
                $cambios[$id][$etapaId] = [
                    'checklist' => ($ultimoDeEtapa[$id][$etapaId] ?? 0) > $revision,
                    'requisitos' => ($ultimaVersion[$id] ?? 0) > $revision,
                ];
            }
        }

        return $cambios;
    }

    /**
     * El avance por etapa de un expediente de contratación ya presentado (con fechas, semáforo y
     * el estado visible de la revisión, presentarCronograma()), sin los avisos. Para decidir una
     * revisión bajo el bloqueo del expediente.
     *
     * @return list<array>
     */
    public function avancePresentadoDe(ProyectoExpediente $expediente): array
    {
        $posiciones = $this->posiciones($this->plantillas->versionesDe($expediente));
        $cronograma = $this->cronogramaDe($expediente);
        $cambios = $this->cambiosTrasLaRevision([$expediente->id => ['posiciones' => $posiciones, 'cronograma' => $cronograma]]);

        return $this->presentarCronograma(
            $this->avancePorEtapa($this->filasDelAvance($expediente), $posiciones),
            $cronograma,
            $expediente->esBorrador(),
            $cambios[$expediente->id] ?? []
        );
    }

    /** @return list<array{etapa: ?array, total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}> el de avancePorEtapa() */
    public function avancePorEtapaDe(ProyectoExpediente $expediente): array
    {
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/AvisosProcesosApiTest.php tests/Feature/Contratacion/CronogramaEtapasApiTest.php tests/Feature/Contratacion/ExpedienteContratacionApiTest.php tests/Feature/Contratacion/ProcesosContratacionApiTest.php tests/Feature/Contratacion/RevisionEtapasApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1295 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Exceptions/CierreConPendientesException.php app/Http/Controllers/RevisionEtapasController.php app/Services/Contratacion/AvisosContratacion.php app/Services/Contratacion/RevisionEtapas.php app/Services/Viabilizacion/ExpedienteService.php tests/Feature/Contratacion/AvisosProcesosApiTest.php tests/Feature/Contratacion/CronogramaEtapasApiTest.php tests/Feature/Contratacion/ExpedienteContratacionApiTest.php tests/Feature/Contratacion/ProcesosContratacionApiTest.php tests/Feature/Contratacion/RevisionEtapasApiTest.php
git commit -m "feat: alerts and closing follow stage approval; re-approval and review fixes"
```

---

### Tarea 4: Revisión por etapa en las pantallas de contratación

En la página del expediente, el estado de revisión de cada etapa con quién y cuándo, la observación y los botones "Aprobar etapa" / "Aprobar de nuevo" y "Devolver" (con sus confirmaciones), el aviso "La etapa anterior aún no está aprobada" y el diálogo de cierre con las etapas sin aprobar. La franja y los chips de procesos y ficha con "por revisar" y "devueltas", hitos por etapas aprobadas, y los eventos de revisión en el historial.

**Files:**
- Modify: `resources/css/ExpedienteContratacion.css`
- Modify: `resources/js/components/ChipsAvisosProceso.jsx`
- Modify: `resources/js/components/ExpedienteChecklist.jsx`
- Modify: `resources/js/components/ExpedienteContratacion.jsx`
- Modify: `resources/js/utils/avisosContratacion.js`
- Test: `resources/js/utils/avisosContratacion.test.js`
- Modify: `resources/js/utils/expedienteChecklist.js`
- Test: `resources/js/utils/expedienteChecklist.test.js`
- Modify: `resources/js/utils/expedienteContratacion.js`
- Test: `resources/js/utils/expedienteContratacion.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/avisosContratacion.test.js`, reemplazar (1 de 4):

```js
import { describe, it, expect } from 'vitest';
import {
    avisoProcesoContrato, chipsAvisosProceso, etiquetaAlerta, franjaAvisosExpediente, textoCierre, textoSinActualizacion,
} from './avisosContratacion';

const avisos = (cambios = {}) => ({
```

por:

```js
import { describe, it, expect } from 'vitest';
import {
    avisoProcesoContrato, chipsAvisosProceso, etiquetaAlerta, etiquetaRevision, franjaAvisosExpediente, textoCierre, textoSinActualizacion,
} from './avisosContratacion';

const avisos = (cambios = {}) => ({
```

En `resources/js/utils/avisosContratacion.test.js`, reemplazar (2 de 4):

```js
        expect(chips.map((c) => c.titulo)).toEqual([
            '1 etapa del expediente vencida',
            '3 etapas del expediente próximas a vencer',
            '2 de 3 etapas del expediente completas',
        ]);
    });
```

por:

```js
        expect(chips.map((c) => c.titulo)).toEqual([
            '1 etapa del expediente vencida',
            '3 etapas del expediente próximas a vencer',
            '2 de 3 etapas del expediente aprobadas',
        ]);
    });
```

En `resources/js/utils/avisosContratacion.test.js`, reemplazar (3 de 4):

```js
        expect(textos(chipsAvisosProceso(avisos({ cierre_proximo: true, dias_para_cierre: 0 })))).toEqual(['Cierre hoy']);
    });

    it('con todas las etapas completas, los hitos van en verde', () => {
        expect(chipsAvisosProceso(avisos({ hitos: { completadas: 3, total: 3 } }))).toEqual([
            { clave: 'hitos', texto: 'Hitos 3/3', tono: 'verde', titulo: '3 de 3 etapas del expediente completas' },
        ]);
    });
```

por:

```js
        expect(textos(chipsAvisosProceso(avisos({ cierre_proximo: true, dias_para_cierre: 0 })))).toEqual(['Cierre hoy']);
    });

    it('con todas las etapas aprobadas, los hitos van en verde', () => {
        expect(chipsAvisosProceso(avisos({ hitos: { completadas: 3, total: 3 } }))).toEqual([
            { clave: 'hitos', texto: 'Hitos 3/3', tono: 'verde', titulo: '3 de 3 etapas del expediente aprobadas' },
        ]);
    });
```

En `resources/js/utils/avisosContratacion.test.js`, reemplazar (4 de 4):

```js
        expect(avisoProcesoContrato(aviso({ proceso_id: null, etapas_vencidas: 2 }))).toBeNull();
    });
});
```

por:

```js
        expect(avisoProcesoContrato(aviso({ proceso_id: null, etapas_vencidas: 2 }))).toBeNull();
    });
});

describe('revisión por etapa en los avisos (fase 5)', () => {
    it('la alerta "aprobada" se muestra como "Aprobada", en verde', () => {
        expect(etiquetaAlerta('aprobada')).toEqual({ texto: 'Aprobada', tono: 'verde' });
    });

    it('la etiqueta de cada estado de revisión, con su tono', () => {
        expect(etiquetaRevision('pendiente')).toEqual({ texto: 'Pendiente', tono: 'neutro' });
        expect(etiquetaRevision('lista')).toEqual({ texto: 'Lista para revisar', tono: 'azul' });
        expect(etiquetaRevision('aprobada')).toEqual({ texto: 'Aprobada', tono: 'verde' });
        expect(etiquetaRevision('aprobada_con_cambios')).toEqual({ texto: 'Aprobada con cambios', tono: 'ambar' });
        expect(etiquetaRevision('devuelta')).toEqual({ texto: 'Devuelta', tono: 'coral' });
        expect(etiquetaRevision('sin_requisitos')).toEqual({ texto: 'Sin requisitos', tono: 'neutro' });
        expect(etiquetaRevision('otro')).toBeNull();
        expect(etiquetaRevision(undefined)).toBeNull();
    });

    it('chips: devueltas y por revisar después de las próximas, antes del cierre', () => {
        const chips = chipsAvisosProceso(avisos({
            etapas_vencidas: 1,
            etapas_proximas: 1,
            etapas_devueltas: 2,
            etapas_por_revisar: 3,
            cierre_vencido: true,
            hitos: { completadas: 1, total: 4 },
        }));
        expect(textos(chips)).toEqual(['1 vencida', '1 próxima', '2 devueltas', '3 por revisar', 'Cierre vencido', 'Hitos 1/4']);
        expect(chips.find((c) => c.clave === 'devueltas')).toEqual({
            clave: 'devueltas', texto: '2 devueltas', tono: 'coral', titulo: '2 etapas del expediente devueltas en la revisión',
        });
        expect(chips.find((c) => c.clave === 'por-revisar')).toEqual({
            clave: 'por-revisar', texto: '3 por revisar', tono: 'azul', titulo: '3 etapas del expediente listas para revisar',
        });
    });

    it('chips en singular', () => {
        const chips = chipsAvisosProceso(avisos({ etapas_devueltas: 1, etapas_por_revisar: 1 }));
        expect(textos(chips)).toEqual(['1 devuelta', '1 por revisar']);
        expect(chips.map((c) => c.titulo)).toEqual(['1 etapa del expediente devuelta en la revisión', '1 etapa del expediente lista para revisar']);
    });

    it('los hitos cuentan las etapas aprobadas', () => {
        expect(chipsAvisosProceso(avisos({ hitos: { completadas: 1, total: 3 } }))[0].titulo).toBe('1 de 3 etapas del expediente aprobadas');
    });

    it('sin las claves nuevas (o en 0), sin esos chips', () => {
        expect(chipsAvisosProceso(avisos({ etapas_devueltas: 0, etapas_por_revisar: 0 }))).toEqual([]);
    });

    it('franja: devueltas y por revisar en frases completas', () => {
        expect(franjaAvisosExpediente(avisos({ etapas_vencidas: 1, etapas_devueltas: 2, etapas_por_revisar: 1, sin_actualizacion: true, dias_sin_actualizacion: 15 })))
            .toEqual([
                { clave: 'vencidas', texto: '1 etapa vencida', tono: 'coral' },
                { clave: 'devueltas', texto: '2 etapas devueltas', tono: 'coral' },
                { clave: 'por-revisar', texto: '1 etapa lista para revisar', tono: 'azul' },
                { clave: 'sin-actualizacion', texto: 'Sin actualización hace 15 días', tono: 'neutro' },
            ]);
        expect(textos(franjaAvisosExpediente(avisos({ etapas_devueltas: 1, etapas_por_revisar: 3 }))))
            .toEqual(['1 etapa devuelta', '3 etapas listas para revisar']);
    });
});
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (1 de 1):

```js
});

describe('describirEvento', () => {
    it('explica cada cambio con sus datos', () => {
        expect(describirEvento({ evento: 'cumplido_marcado' })).toBe('Marcó como cumplido');
        expect(describirEvento({ evento: 'soporte_reemplazado', antes: { soporte_nombre: 'v1.pdf' }, despues: { soporte_nombre: 'v2.pdf' } }))
```

por:

```js
});

describe('describirEvento', () => {
    it('la revisión por etapa: aprobar, aprobar de nuevo y devolver', () => {
        const despues = { etapa: '1. Planeación' };
        expect(describirEvento({ evento: 'etapa_aprobada', antes: {}, despues })).toBe('Aprobó la etapa 1. Planeación');
        expect(describirEvento({ evento: 'etapa_aprobada', antes: {}, despues: { ...despues, reaprobacion: true } })).toBe('Aprobó de nuevo la etapa 1. Planeación');
        expect(describirEvento({ evento: 'etapa_aprobada', antes: {}, despues: { ...despues, pendientes_al_aprobar: 2 } })).toBe('Aprobó la etapa 1. Planeación con 2 requisitos pendientes');
        expect(describirEvento({ evento: 'etapa_aprobada', antes: {}, despues: { ...despues, reaprobacion: true, pendientes_al_aprobar: 1 } })).toBe('Aprobó de nuevo la etapa 1. Planeación con 1 requisito pendiente');
        expect(describirEvento({ evento: 'etapa_devuelta', antes: {}, despues: { ...despues, observacion: 'Falta el CDP.' } })).toBe('Devolvió la etapa 1. Planeación: Falta el CDP.');
    });

    it('explica cada cambio con sus datos', () => {
        expect(describirEvento({ evento: 'cumplido_marcado' })).toBe('Marcó como cumplido');
        expect(describirEvento({ evento: 'soporte_reemplazado', antes: { soporte_nombre: 'v1.pdf' }, despues: { soporte_nombre: 'v2.pdf' } }))
```

En `resources/js/utils/expedienteContratacion.test.js`, reemplazar (1 de 2):

```js
import { describe, it, expect } from 'vitest';
import {
    agruparPorEtapa,
    avisoIncompatiblesProceso,
    alternarPlantillaNueva,
    avanceEtapas,
```

por:

```js
import { describe, it, expect, vi } from 'vitest';
import {
    accionesRevision,
    aprobarConConfirmacion,
    agruparPorEtapa,
    cierreSinAvisos,
    datosAprobarEtapa,
    dialogoAprobarEtapa,
    etapasConAvisoAnterior,
    fechaMomento,
    hayRevisionAtrasada,
    pendientesAlAprobar,
    recalcularAvisoAnterior,
    revisionEtapa,
    tituloCierrePendientes,
    avisoIncompatiblesProceso,
    alternarPlantillaNueva,
    avanceEtapas,
```

En `resources/js/utils/expedienteContratacion.test.js`, reemplazar (2 de 2):

```js
        expect(pondera(undefined)).toBe(false);
    });
});
```

por:

```js
        expect(pondera(undefined)).toBe(false);
    });
});

// ---------- Revisión por etapa (fase 5) ----------

const revisionApi = (cambios = {}) => ({
    estado: 'pendiente', usuario: null, fecha: null, observacion: null, pendientes_al_aprobar: null, devuelta_at: null, ...cambios,
});

const avanceRevision = (id, cambios = {}) => avanceApi(id, { revision: id === null ? null : revisionApi(), aviso_etapa_anterior: false, ...cambios });

describe('fechaMomento', () => {
    it('un momento ISO en UTC como día/mes/año de Bogotá', () => {
        expect(fechaMomento('2026-11-19T15:30:00+00:00')).toBe('19/11/2026');
        expect(fechaMomento('2026-11-20T02:00:00+00:00')).toBe('19/11/2026');
    });

    it('sin momento o uno inválido, null', () => {
        expect(fechaMomento(null)).toBeNull();
        expect(fechaMomento('no es fecha')).toBeNull();
    });
});

describe('revisionEtapa', () => {
    it('aprobada: etiqueta, quién y cuándo, y la observación', () => {
        const r = revisionEtapa(avanceRevision(1, {
            revision: revisionApi({ estado: 'aprobada', usuario: 'Gestora', fecha: '2026-11-19T15:30:00+00:00', observacion: 'Revisado con jurídica.', pendientes_al_aprobar: 0 }),
        }));
        expect(r).toEqual({
            estado: 'aprobada',
            etiqueta: { texto: 'Aprobada', tono: 'verde' },
            detalle: 'Aprobada por Gestora el 19/11/2026',
            observacion: 'Revisado con jurídica.',
            nota: null,
        });
    });

    it('aprobada con requisitos pendientes: lo dice el detalle', () => {
        const r = revisionEtapa(avanceRevision(1, {
            revision: revisionApi({ estado: 'aprobada', usuario: 'Ana', fecha: '2026-11-19T15:30:00+00:00', pendientes_al_aprobar: 2 }),
        }));
        expect(r.detalle).toBe('Aprobada por Ana el 19/11/2026 con 2 requisitos pendientes');
        const una = revisionEtapa(avanceRevision(1, { revision: revisionApi({ estado: 'aprobada', fecha: '2026-11-19T15:30:00+00:00', pendientes_al_aprobar: 1 }) }));
        expect(una.detalle).toBe('Aprobada el 19/11/2026 con 1 requisito pendiente');
    });

    it('aprobada con cambios: avisa que algo cambió después', () => {
        const r = revisionEtapa(avanceRevision(1, { revision: revisionApi({ estado: 'aprobada_con_cambios', usuario: 'Ana', fecha: '2026-11-19T15:30:00+00:00', pendientes_al_aprobar: 0 }) }));
        expect(r.etiqueta).toEqual({ texto: 'Aprobada con cambios', tono: 'ambar' });
        expect(r.detalle).toBe('Aprobada por Ana el 19/11/2026');
        expect(r.nota).toBe('Algún requisito de la etapa cambió después de aprobarla.');
    });

    it('devuelta vigente: quién, cuándo y por qué', () => {
        const r = revisionEtapa(avanceRevision(1, {
            revision: revisionApi({ estado: 'devuelta', usuario: 'Ana', fecha: '2026-11-19T15:30:00+00:00', devuelta_at: '2026-11-19T15:30:00+00:00', observacion: 'Falta el CDP.' }),
        }));
        expect(r).toEqual({
            estado: 'devuelta', etiqueta: { texto: 'Devuelta', tono: 'coral' }, detalle: 'Devuelta por Ana el 19/11/2026', observacion: 'Falta el CDP.', nota: null,
        });
    });

    it('devuelta y trabajada después: pendiente o lista, conservando la devolución', () => {
        const r = revisionEtapa(avanceRevision(1, {
            revision: revisionApi({ estado: 'lista', usuario: 'Ana', fecha: '2026-11-19T15:30:00+00:00', devuelta_at: '2026-11-19T15:30:00+00:00', observacion: 'Falta el CDP.' }),
        }));
        expect(r.etiqueta).toEqual({ texto: 'Lista para revisar', tono: 'azul' });
        expect(r.detalle).toBe('Devuelta por Ana el 19/11/2026');
        expect(r.observacion).toBe('Falta el CDP.');
        expect(r.nota).toBe('Tuvo cambios después de la devolución.');
    });

    it('sin revisión: solo la etiqueta', () => {
        expect(revisionEtapa(avanceRevision(1))).toEqual({
            estado: 'pendiente', etiqueta: { texto: 'Pendiente', tono: 'neutro' }, detalle: null, observacion: null, nota: null,
        });
    });

    it('el grupo sin etapa o una respuesta sin revisión: null', () => {
        expect(revisionEtapa(avanceRevision(null))).toBeNull();
        expect(revisionEtapa(avanceApi(1))).toBeNull();
        expect(revisionEtapa(undefined)).toBeNull();
    });
});

describe('etapasConFechas con la revisión', () => {
    it('cada etapa trae su revisión, lo que le falta y el aviso de la etapa anterior', () => {
        const lista = etapasConFechas([
            avanceRevision(1, { total: 3, cumplidos: 1 }),
            avanceRevision(2, { aviso_etapa_anterior: true }),
            avanceRevision(null),
        ]);
        expect(lista[0]).toMatchObject({ faltan: 2, avisoEtapaAnterior: false, revision: { estado: 'pendiente' } });
        expect(lista[1].avisoEtapaAnterior).toBe(true);
        expect(lista[2]).toMatchObject({ faltan: 1, revision: null, avisoEtapaAnterior: false });
    });
});

describe('accionesRevision', () => {
    const conEstado = (estado) => etapasConFechas([avanceRevision(1, { revision: revisionApi({ estado }) })])[0];

    it('sin aprobar: aprobar y devolver', () => {
        ['pendiente', 'lista', 'devuelta'].forEach((estado) => {
            expect(accionesRevision(conEstado(estado), true)).toEqual({ aprobar: true, reaprobar: false, devolver: true });
        });
    });

    it('aprobada sin cambios: solo devolver', () => {
        expect(accionesRevision(conEstado('aprobada'), true)).toEqual({ aprobar: false, reaprobar: false, devolver: true });
    });

    it('aprobada con cambios: aprobar de nuevo y devolver', () => {
        expect(accionesRevision(conEstado('aprobada_con_cambios'), true)).toEqual({ aprobar: false, reaprobar: true, devolver: true });
    });

    it('sin requisitos que cuenten: ningún botón', () => {
        expect(accionesRevision(conEstado('sin_requisitos'), true)).toEqual({ aprobar: false, reaprobar: false, devolver: false });
    });

    it('solo lectura o grupo sin etapa: ninguna', () => {
        expect(accionesRevision(conEstado('lista'), false)).toEqual({ aprobar: false, reaprobar: false, devolver: false });
        expect(accionesRevision(etapasConFechas([avanceRevision(null)])[0], true)).toEqual({ aprobar: false, reaprobar: false, devolver: false });
        expect(accionesRevision(undefined, true)).toEqual({ aprobar: false, reaprobar: false, devolver: false });
    });
});

describe('aprobar una etapa', () => {
    it('el diálogo sin pendientes', () => {
        expect(dialogoAprobarEtapa('1. Planeación', 0)).toEqual({
            icono: 'question',
            titulo: '¿Aprobar la etapa «1. Planeación»?',
            texto: 'La aprobación queda en el historial del expediente con tu nombre y la fecha.',
            boton: 'Aprobar etapa',
        });
    });

    it('el diálogo con pendientes dice cuántos', () => {
        expect(dialogoAprobarEtapa('1. Planeación', 2)).toEqual({
            icono: 'warning',
            titulo: '¿Aprobar la etapa «1. Planeación» con requisitos pendientes?',
            texto: 'Faltan 2 requisitos de esta etapa. Puedes aprobarla de todos modos: la revisión solo avisa.',
            boton: 'Aprobar de todos modos',
        });
        expect(dialogoAprobarEtapa('X', 1).texto).toBe('Falta 1 requisito de esta etapa. Puedes aprobarla de todos modos: la revisión solo avisa.');
    });

    it('el cuerpo: observación sin espacios (o null) y confirmar solo si se confirmó', () => {
        expect(datosAprobarEtapa('  Revisado.  ', false)).toEqual({ observacion: 'Revisado.' });
        expect(datosAprobarEtapa('', true)).toEqual({ observacion: null, confirmar: true });
        expect(datosAprobarEtapa(undefined, false)).toEqual({ observacion: null });
    });

    it('el cuerpo con confirmación envía los pendientes que se mostraron', () => {
        expect(datosAprobarEtapa('Ok', true, 2)).toEqual({ observacion: 'Ok', confirmar: true, pendientes_vistos: 2 });
        expect(datosAprobarEtapa('', true, 0)).toEqual({ observacion: null, confirmar: true, pendientes_vistos: 0 });
        expect(datosAprobarEtapa('', false, 2)).toEqual({ observacion: null });
        expect(datosAprobarEtapa('', true)).toEqual({ observacion: null, confirmar: true });
    });

    it('el diálogo de aprobar de nuevo', () => {
        expect(dialogoAprobarEtapa('1. Planeación', 0, true)).toMatchObject({
            titulo: '¿Aprobar de nuevo la etapa «1. Planeación»?', boton: 'Aprobar de nuevo',
        });
        expect(dialogoAprobarEtapa('1. Planeación', 2, true).boton).toBe('Aprobar de todos modos');
    });

    it('los pendientes del 422 que pide confirmar; cualquier otro error, null', () => {
        const error = (status, data) => ({ response: { status, data } });
        expect(pendientesAlAprobar(error(422, { mensaje: 'Faltan 2 requisitos...', pendientes: 2 }))).toBe(2);
        expect(pendientesAlAprobar(error(422, { mensaje: 'La etapa ya está aprobada.' }))).toBeNull();
        expect(pendientesAlAprobar(error(422, { pendientes: { total: 1 } }))).toBeNull();
        expect(pendientesAlAprobar(error(500, { pendientes: 2 }))).toBeNull();
        expect(pendientesAlAprobar(undefined)).toBeNull();
    });
});

describe('aviso de la etapa anterior', () => {
    const aprobada = revisionApi({ estado: 'aprobada', fecha: '2026-11-19T15:30:00+00:00' });

    it('los ids de las etapas con el aviso', () => {
        const avance = [avanceRevision(1), avanceRevision(2, { aviso_etapa_anterior: true }), avanceRevision(null)];
        expect([...etapasConAvisoAnterior(avance)]).toEqual([2]);
        expect(etapasConAvisoAnterior(undefined).size).toBe(0);
    });

    it('recalcularAvisoAnterior: con la regla de la API (anterior con requisitos sin aprobar y esta con algo cumplido)', () => {
        const avance = [
            avanceRevision(1, { revision: aprobada }),
            avanceRevision(2, { cumplidos: 1, aviso_etapa_anterior: true }),
            avanceRevision(3, { total: 0, cumplidos: 0 }),
            avanceRevision(4, { cumplidos: 1 }),
            avanceRevision(5, { cumplidos: 0, aviso_etapa_anterior: true }),
            avanceRevision(null, { cumplidos: 1 }),
        ];
        expect(recalcularAvisoAnterior(avance).map((a) => a.aviso_etapa_anterior)).toEqual([false, false, false, true, false, false]);
    });

    it('una etapa vacía o aprobada con cambios no cuenta como sin aprobar', () => {
        const avance = [
            avanceRevision(1, { revision: revisionApi({ estado: 'aprobada_con_cambios' }) }),
            avanceRevision(2, { total: 0, cumplidos: 0 }),
            avanceRevision(3, { cumplidos: 1, aviso_etapa_anterior: true }),
        ];
        expect(recalcularAvisoAnterior(avance)[2].aviso_etapa_anterior).toBe(false);
    });

    it('sin las claves de la revisión, no toca nada', () => {
        const avance = [avanceApi(1), avanceApi(2)];
        expect(recalcularAvisoAnterior(avance)).toEqual(avance);
    });

    it('conEtapaActualizada lo recalcula al aprobar o devolver', () => {
        const expediente = { id: 5, avance_por_etapa: [avanceRevision(1), avanceRevision(2, { aviso_etapa_anterior: true })], avisos: {} };
        const nuevo = conEtapaActualizada(expediente, { etapa: avanceRevision(1, { revision: aprobada, alerta: 'aprobada' }), avisos: { etapas_por_revisar: 0 } });
        expect(nuevo.avance_por_etapa[1].aviso_etapa_anterior).toBe(false);
        const devuelto = conEtapaActualizada(nuevo, { etapa: avanceRevision(1, { revision: revisionApi({ estado: 'devuelta', fecha: '2026-11-19T16:00:00+00:00' }) }) });
        expect(devuelto.avance_por_etapa[1].aviso_etapa_anterior).toBe(true);
    });
});

describe('fusionarAvance con una revisión más nueva en pantalla', () => {
    const aprobada = revisionApi({ estado: 'aprobada', usuario: 'Ana', fecha: '2026-11-19T15:30:00+00:00', pendientes_al_aprobar: 0 });
    const actual = [
        avanceRevision(1, { cumplidos: 2, completa: true, fecha_real: '2026-11-19', fecha_real_sugerida: false, alerta: 'aprobada', revision: aprobada }),
        avanceRevision(2, { cumplidos: 1, aviso_etapa_anterior: false }),
    ];

    it('una respuesta que salió antes de aprobar no repone la revisión, el semáforo ni la fecha real', () => {
        const vieja = [
            avanceRevision(1, { cumplidos: 2, completa: true, fecha_real: '2026-11-18', fecha_real_sugerida: true, alerta: 'vencida', revision: revisionApi({ estado: 'lista' }) }),
            avanceRevision(2, { cumplidos: 2, aviso_etapa_anterior: true }),
        ];
        const r = fusionarAvance(actual, vieja);
        expect(r[0]).toMatchObject({ cumplidos: 2, fecha_real: '2026-11-19', fecha_real_sugerida: false, alerta: 'aprobada', revision: aprobada });
        expect(r[1]).toMatchObject({ cumplidos: 2, aviso_etapa_anterior: false });
        expect(hayRevisionAtrasada(actual, vieja)).toBe(true);
    });

    it('una respuesta con la misma revisión (o más nueva) pasa tal cual: puede traer "aprobada con cambios"', () => {
        const nueva = [
            avanceRevision(1, { cumplidos: 1, alerta: 'aprobada', revision: { ...aprobada, estado: 'aprobada_con_cambios' } }),
            avanceRevision(2),
        ];
        expect(fusionarAvance(actual, nueva)).toBe(nueva);
        expect(hayRevisionAtrasada(actual, nueva)).toBe(false);
    });

    it('conAvance conserva los avisos si la respuesta salió antes de la revisión', () => {
        const expediente = { avance_por_etapa: actual, progreso: {}, avisos: { etapas_por_revisar: 0 } };
        const vieja = [avanceRevision(1, { revision: revisionApi({ estado: 'lista' }) }), avanceRevision(2)];
        expect(conAvance(expediente, { avance_por_etapa: vieja, avisos: { etapas_por_revisar: 1 } }).avisos).toEqual({ etapas_por_revisar: 0 });
        expect(conAvance(expediente, { avance_por_etapa: actual, avisos: { etapas_por_revisar: 2 } }).avisos).toEqual({ etapas_por_revisar: 2 });
    });
});

describe('cierre con etapas sin aprobar', () => {
    const sinAprobar = [
        { etapa: etapa(1, 1, 'Planeación'), estado: 'lista' },
        { etapa: etapa(2, 2, 'Selección'), estado: 'devuelta' },
    ];

    it('requisitos y etapas sin aprobar', () => {
        expect(textoPendientesCierre({ total: 1, por_etapa: [{ etapa: etapa(2, 2, 'Selección'), pendientes: 1 }], etapas_sin_aprobar: sinAprobar })).toEqual({
            titulo: 'Falta 1 requisito por cumplir',
            etapas: ['2. Selección: 1 pendiente'],
            sinAprobar: { titulo: 'Hay 2 etapas sin aprobar', etapas: ['1. Planeación: lista para revisar', '2. Selección: devuelta'] },
        });
    });

    it('solo etapas sin aprobar (todo cumplido)', () => {
        expect(textoPendientesCierre({ total: 0, por_etapa: [], etapas_sin_aprobar: [sinAprobar[0]] })).toEqual({
            titulo: null,
            etapas: [],
            sinAprobar: { titulo: 'Hay 1 etapa sin aprobar', etapas: ['1. Planeación: lista para revisar'] },
        });
    });

    it('el html trae las dos listas, escapadas', () => {
        const html = htmlPendientesCierre({ total: 0, por_etapa: [], etapas_sin_aprobar: [{ etapa: etapa(1, 1, '<i>P</i>'), estado: 'pendiente' }] });
        expect(html).toContain('Hay 1 etapa sin aprobar.');
        expect(html).toContain('&lt;i&gt;P&lt;/i&gt;: pendiente');
        expect(html).not.toContain('<i>P</i>');
        expect(html).not.toContain('por cumplir');
        expect(html).toContain('Puedes cerrarlo de todos modos');
    });

    it('el título del diálogo según lo que falte', () => {
        expect(tituloCierrePendientes({ total: 2, etapas_sin_aprobar: sinAprobar })).toBe('¿Cerrar con pendientes?');
        expect(tituloCierrePendientes({ total: 2, etapas_sin_aprobar: [] })).toBe('¿Cerrar con requisitos pendientes?');
        expect(tituloCierrePendientes({ total: 2 })).toBe('¿Cerrar con requisitos pendientes?');
        expect(tituloCierrePendientes({ total: 0, etapas_sin_aprobar: sinAprobar })).toBe('¿Cerrar con etapas sin aprobar?');
    });

    it('cierreSinAvisos: todo cumplido y todas las etapas con requisitos aprobadas', () => {
        const aprobada = revisionApi({ estado: 'aprobada' });
        const exp = (avance, cumplidos = 2) => ({ progreso: { total: 2, cumplidos }, avance_por_etapa: avance });
        expect(cierreSinAvisos(exp([avanceRevision(1, { revision: aprobada }), avanceRevision(2, { total: 0, cumplidos: 0 }), avanceRevision(null)]))).toBe(true);
        expect(cierreSinAvisos(exp([avanceRevision(1, { revision: revisionApi({ estado: 'aprobada_con_cambios' }) })]))).toBe(true);
        expect(cierreSinAvisos(exp([avanceRevision(1, { revision: revisionApi({ estado: 'lista' }) })]))).toBe(false);
        expect(cierreSinAvisos(exp([avanceRevision(1, { revision: aprobada })], 1))).toBe(false);
        expect(cierreSinAvisos(exp([avanceApi(1)]))).toBe(true);
    });
});


describe('sin requisitos que cuenten', () => {
    it('la revisión es neutra', () => {
        const etapa = etapasConFechas([avanceRevision(1, { total: 0, cumplidos: 0, revision: revisionApi({ estado: 'sin_requisitos' }) })])[0];
        expect(etapa.revision.etiqueta).toEqual({ texto: 'Sin requisitos', tono: 'neutro' });
        expect(etapa.revision.nota).toBeNull();
    });
});

describe('aprobarConConfirmacion', () => {
    const error422 = (pendientes) => ({ response: { status: 422, data: { mensaje: 'Faltan', pendientes } } });

    it('sin pendientes: envía sin confirmar y aprueba', async () => {
        const enviar = vi.fn().mockResolvedValue({ etapa: 1 });
        const confirmar = vi.fn();
        expect(await aprobarConConfirmacion({ faltan: 0, enviar, confirmar })).toEqual({ aprobada: true, data: { etapa: 1 } });
        expect(enviar).toHaveBeenCalledWith(false, 0);
        expect(confirmar).not.toHaveBeenCalled();
    });

    it('con pendientes mostrados: confirma y envía los pendientes vistos', async () => {
        const enviar = vi.fn().mockResolvedValue({ ok: true });
        await aprobarConConfirmacion({ faltan: 2, enviar, confirmar: vi.fn() });
        expect(enviar).toHaveBeenCalledWith(true, 2);
    });

    it('si el servidor cuenta más pendientes, vuelve a pedir confirmación con el número nuevo', async () => {
        const enviar = vi.fn()
            .mockRejectedValueOnce(error422(3))
            .mockResolvedValueOnce({ ok: true });
        const confirmar = vi.fn().mockResolvedValue(true);
        const r = await aprobarConConfirmacion({ faltan: 2, enviar, confirmar });
        expect(confirmar).toHaveBeenCalledWith(3);
        expect(enviar).toHaveBeenNthCalledWith(1, true, 2);
        expect(enviar).toHaveBeenNthCalledWith(2, true, 3);
        expect(r).toEqual({ aprobada: true, data: { ok: true } });
    });

    it('puede pedir confirmación más de una vez', async () => {
        const enviar = vi.fn()
            .mockRejectedValueOnce(error422(3))
            .mockRejectedValueOnce(error422(4))
            .mockResolvedValueOnce({ ok: true });
        const confirmar = vi.fn().mockResolvedValue(true);
        await aprobarConConfirmacion({ faltan: 2, enviar, confirmar });
        expect(confirmar.mock.calls.map((c) => c[0])).toEqual([3, 4]);
        expect(enviar).toHaveBeenLastCalledWith(true, 4);
    });

    it('si no confirma el número nuevo, no aprueba', async () => {
        const enviar = vi.fn().mockRejectedValueOnce(error422(1));
        const r = await aprobarConConfirmacion({ faltan: 0, enviar, confirmar: vi.fn().mockResolvedValue(false) });
        expect(r).toEqual({ aprobada: false, data: null });
        expect(enviar).toHaveBeenCalledTimes(1);
    });

    it('cualquier otro error se propaga', async () => {
        const fallo = { response: { status: 500, data: {} } };
        await expect(aprobarConConfirmacion({ faltan: 0, enviar: vi.fn().mockRejectedValue(fallo), confirmar: vi.fn() })).rejects.toBe(fallo);
    });
});
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/avisosContratacion.test.js resources/js/utils/expedienteChecklist.test.js resources/js/utils/expedienteContratacion.test.js`
Expected: FALLAN los 3 archivos, 50 pruebas, y pasan 186: en `expedienteContratacion.test.js` fallan 41, las que usan lo nuevo, como `revisionEtapa` ("revisionEtapa is not a function"), `accionesRevision`, `aprobarConConfirmacion` o `etiquetaRevision`; en `avisosContratacion.test.js` fallan 8, porque los chips y la franja aún no tienen "por revisar" ni "devueltas" y los hitos dicen "completas" en vez de "aprobadas"; en `expedienteChecklist.test.js` falla 1, porque el historial aún no tiene la etiqueta "Aprobó la etapa …".

- [ ] **Paso 3: Implementar**

En `resources/css/ExpedienteContratacion.css`, reemplazar (1 de 1):

```css
    font-size: .88rem;
}
.exc-avisos ul { display: flex; flex-wrap: wrap; gap: .4rem; margin: 0; padding: 0; list-style: none; }
```

por:

```css
    font-size: .88rem;
}
.exc-avisos ul { display: flex; flex-wrap: wrap; gap: .4rem; margin: 0; padding: 0; list-style: none; }

/* Revisión por etapa (fase 5): estado, quién y cuándo, observación y botones bajo cada etapa. */
.exc-revision {
    display: grid;
    gap: .3rem;
    padding-top: .5rem;
    border-top: 1px dashed var(--exv-linea-suave);
}
.exc-revision-linea { display: flex; flex-wrap: wrap; gap: .3rem .5rem; align-items: center; }
.exc-revision-detalle, .exc-revision-nota { font-size: .78rem; color: var(--exv-tinta-suave); }
.exc-revision-nota { font-style: italic; }
.exc-revision-observacion {
    margin: 0;
    padding: .35rem .55rem;
    font-size: .85rem;
    color: var(--exv-tinta);
    background: var(--gp-superficie-3);
    border-radius: var(--exv-radio);
    overflow-wrap: anywhere;
    white-space: pre-line;
}
.exc-revision-rotulo { font-weight: 600; }
.exc-revision-acciones { display: flex; flex-wrap: wrap; gap: .4rem; }
.exc-revision-acciones .exv-btn { font-size: .82rem; }

/* En la sección de una etapa del checklist: su etapa anterior aún no está aprobada. */
.exc-aviso-anterior {
    margin: 0 0 .6rem;
    padding: .35rem .6rem;
    font-size: .85rem;
    color: var(--gp-ambar);
    background: var(--gp-ambar-suave);
    border-radius: var(--exv-radio);
}
```

En `resources/js/components/ChipsAvisosProceso.jsx`, reemplazar (1 de 1):

```jsx
import { chipsAvisosProceso } from '../utils/avisosContratacion';

/**
 * Los chips de avisos de un proceso (`avisos` de la API): etapas vencidas y próximas, cierre, sin
 * actualización e hitos. Los usan el diálogo de procesos del proyecto y la ficha. Sin nada que
 * mostrar, no pinta nada.
 */
const ChipsAvisosProceso = ({ avisos }) => {
```

por:

```jsx
import { chipsAvisosProceso } from '../utils/avisosContratacion';

/**
 * Los chips de avisos de un proceso (`avisos` de la API): etapas vencidas y próximas, devueltas y
 * por revisar, cierre, sin actualización e hitos (etapas aprobadas). Los usan el diálogo de procesos del proyecto y la ficha. Sin nada que
 * mostrar, no pinta nada.
 */
const ChipsAvisosProceso = ({ avisos }) => {
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (1 de 2):

```jsx
 * al final (agruparPorEtapa). Cada propio va en su etapa, que es obligatoria; al final, quien
 * edita agrega uno nuevo eligiendo la etapa entre `etapas` (GET /etapasContratacion).
 */
export const ChecklistPorEtapa = ({ expediente, editable, ocupado, etapas, onGuardarPropio, onEliminarPropio, ...acciones }) => {
    const grupos = useMemo(() => agruparPorEtapa(expediente.requisitos), [expediente.requisitos]);
    const propiosAbiertos = useFormularioPropios(expediente, onGuardarPropio);
    const { formulario, contenedor, abrir, guardar, cerrar } = propiosAbiertos;
```

por:

```jsx
 * al final (agruparPorEtapa). Cada propio va en su etapa, que es obligatoria; al final, quien
 * edita agrega uno nuevo eligiendo la etapa entre `etapas` (GET /etapasContratacion).
 */
export const ChecklistPorEtapa = ({
    expediente, editable, ocupado, etapas, etapasAvisoAnterior, onGuardarPropio, onEliminarPropio, ...acciones
}) => {
    const grupos = useMemo(() => agruparPorEtapa(expediente.requisitos), [expediente.requisitos]);
    const propiosAbiertos = useFormularioPropios(expediente, onGuardarPropio);
    const { formulario, contenedor, abrir, guardar, cerrar } = propiosAbiertos;
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (2 de 2):

```jsx
                        <h3 id={`exv-${grupo.clave}-titulo`}>{grupo.titulo}</h3>
                        <span className="exv-cita">{grupo.cumplidos}/{grupo.total}</span>
                    </div>
                    {grupo.filas.map(renderFila)}
                </section>
            ))}
```

por:

```jsx
                        <h3 id={`exv-${grupo.clave}-titulo`}>{grupo.titulo}</h3>
                        <span className="exv-cita">{grupo.cumplidos}/{grupo.total}</span>
                    </div>
                    {grupo.etapa && etapasAvisoAnterior?.has(grupo.etapa.id) && (
                        <p className="exc-aviso-anterior">La etapa anterior aún no está aprobada</p>
                    )}
                    {grupo.filas.map(renderFila)}
                </section>
            ))}
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (1 de 10):

```jsx
import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import Swal from 'sweetalert2';
import { faArrowLeft, faArrowsRotate, faClockRotateLeft, faLock, faPlus } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { ChecklistPorEtapa } from './ExpedienteChecklist';
```

por:

```jsx
import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import Swal from 'sweetalert2';
import {
    faArrowLeft, faArrowsRotate, faCheck, faClockRotateLeft, faLock, faPlus, faRotateLeft,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { ChecklistPorEtapa } from './ExpedienteChecklist';
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (2 de 10):

```jsx
    contarOpcionales, motivoNoEliminarPropio, plantillasConVersion, requiereAprobacionNoAplica, textoOpcionales,
} from '../utils/expedienteChecklist';
import {
    alternarPlantillaNueva, avisoIncompatiblesProceso, conAvance, conEtapaActualizada, datosFechaEtapa, datosPropioContratacion,
    errorDeProceso, errorFechaEtapa, esIdProceso, etapasConFechas, fechaHoyBogota, fechaLegible, fechaPorGuardar, htmlPendientesCierre, NOTA_PONDERADO,
    pondera, seleccionInicial, textoEstadoExpediente, textoSinPlantillas,
} from '../utils/expedienteContratacion';
import { franjaAvisosExpediente } from '../utils/avisosContratacion';
import { etiquetaEstadoProceso, numeroProceso, tonoEstado } from '../utils/contratacion';
```

por:

```jsx
    contarOpcionales, motivoNoEliminarPropio, plantillasConVersion, requiereAprobacionNoAplica, textoOpcionales,
} from '../utils/expedienteChecklist';
import {
    accionesRevision, alternarPlantillaNueva, aprobarConConfirmacion, avisoIncompatiblesProceso, cierreSinAvisos, conAvance, conEtapaActualizada, datosAprobarEtapa,
    datosFechaEtapa, datosPropioContratacion, dialogoAprobarEtapa, errorDeProceso, errorFechaEtapa, esIdProceso, etapasConAvisoAnterior,
    etapasConFechas, fechaHoyBogota, fechaLegible, fechaPorGuardar, htmlPendientesCierre, NOTA_PONDERADO, pondera,
    seleccionInicial, textoEstadoExpediente, textoSinPlantillas, tituloCierrePendientes,
} from '../utils/expedienteContratacion';
import { franjaAvisosExpediente } from '../utils/avisosContratacion';
import { etiquetaEstadoProceso, numeroProceso, tonoEstado } from '../utils/contratacion';
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (3 de 10):

```jsx
    );
};

/** El avance de una etapa con su semáforo y, si es una etapa del catálogo, sus fechas. */
const EtapaCronograma = ({ etapa, editable, hoy, onGuardarFecha }) => {
    const guardar = (campo, valor) => onGuardarFecha(etapa, campo, valor);
    return (
        <div className="exc-etapa">
```

por:

```jsx
    );
};

/**
 * El estado de revisión de una etapa: su etiqueta, quién y cuándo la aprobó o la devolvió, la
 * observación y, para quien edita un expediente en borrador, los botones de aprobar y devolver.
 */
const RevisionEtapa = ({ etapa, editable, ocupado, onAprobar, onDevolver }) => {
    const { revision } = etapa;
    if (!revision) return null;
    const acciones = accionesRevision(etapa, editable);
    return (
        <div className="exc-revision">
            <span className="exc-revision-linea">
                <span className={`ct-estado ct-estado-${revision.etiqueta?.tono ?? 'neutro'}`}>
                    <span className="ct-oculto">Revisión: </span>{revision.etiqueta?.texto ?? revision.estado}
                </span>
                {revision.detalle && <small className="exc-revision-detalle">{revision.detalle}</small>}
            </span>
            {revision.nota && <small className="exc-revision-nota">{revision.nota}</small>}
            {revision.observacion && (
                <p className="exc-revision-observacion"><span className="exc-revision-rotulo">Observación:</span> {revision.observacion}</p>
            )}
            {(acciones.aprobar || acciones.reaprobar || acciones.devolver) && (
                <div className="exc-revision-acciones exv-no-imprimir">
                    {acciones.aprobar && (
                        <button
                            type="button"
                            className="exv-btn exv-btn-primario"
                            onClick={() => onAprobar(etapa)}
                            disabled={ocupado}
                            aria-label={`Aprobar etapa ${etapa.titulo}`}
                        >
                            <FontAwesomeIcon icon={faCheck} /> Aprobar etapa
                        </button>
                    )}
                    {acciones.reaprobar && (
                        <button
                            type="button"
                            className="exv-btn exv-btn-primario"
                            onClick={() => onAprobar(etapa, true)}
                            disabled={ocupado}
                            aria-label={`Aprobar de nuevo la etapa ${etapa.titulo}`}
                        >
                            <FontAwesomeIcon icon={faCheck} /> Aprobar de nuevo
                        </button>
                    )}
                    {acciones.devolver && (
                        <button
                            type="button"
                            className="exv-btn"
                            onClick={() => onDevolver(etapa)}
                            disabled={ocupado}
                            aria-label={`Devolver la etapa ${etapa.titulo}`}
                        >
                            <FontAwesomeIcon icon={faRotateLeft} /> Devolver
                        </button>
                    )}
                </div>
            )}
        </div>
    );
};

/** El avance de una etapa con su semáforo, su revisión y, si es una etapa del catálogo, sus fechas. */
const EtapaCronograma = ({ etapa, editable, ocupado, hoy, onGuardarFecha, onAprobar, onDevolver }) => {
    const guardar = (campo, valor) => onGuardarFecha(etapa, campo, valor);
    return (
        <div className="exc-etapa">
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (4 de 10):

```jsx
                    )}
                </div>
            )}
        </div>
    );
};

/** La franja de avisos del expediente: vencidas, próximas, cierre y sin actualización. */
const FranjaAvisos = ({ avisos }) => {
    const items = franjaAvisosExpediente(avisos);
    if (items.length === 0) return null;
    return (
        <div className="exc-avisos exv-no-imprimir">
            <b>Avisos del cronograma</b>
            <ul>
                {items.map((a) => <li key={a.clave} className={`ct-estado ct-estado-${a.tono}`}>{a.texto}</li>)}
            </ul>
```

por:

```jsx
                    )}
                </div>
            )}
            <RevisionEtapa etapa={etapa} editable={editable} ocupado={ocupado} onAprobar={onAprobar} onDevolver={onDevolver} />
        </div>
    );
};

/** La franja de avisos del expediente: vencidas, próximas, devueltas, por revisar, cierre y sin actualización. */
const FranjaAvisos = ({ avisos }) => {
    const items = franjaAvisosExpediente(avisos);
    if (items.length === 0) return null;
    return (
        <div className="exc-avisos exv-no-imprimir">
            <b>Avisos del expediente</b>
            <ul>
                {items.map((a) => <li key={a.clave} className={`ct-estado ct-estado-${a.tono}`}>{a.texto}</li>)}
            </ul>
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (5 de 10):

```jsx
        try {
            const { data } = await axios.put(`/expedientes/${id}/etapas/${etapa.etapaId}`, datosFechaEtapa(campo, valor));
            if (!montado.current) return null;
            // Su fecha ya es la más nueva: una respuesta del checklist que salió antes no la repone.
            etapasProtegidas.current.add(etapa.etapaId);
            setExpediente((prev) => (prev?.id === id ? conEtapaActualizada(prev, data) : prev));
            nuevaRevision();
```

por:

```jsx
        try {
            const { data } = await axios.put(`/expedientes/${id}/etapas/${etapa.etapaId}`, datosFechaEtapa(campo, valor));
            if (!montado.current) return null;
            // Su fecha ya es la más nueva: una respuesta del checklist que salió antes no la repone,
            // ni una carga completa que empezó antes.
            turno.current += 1;
            etapasProtegidas.current.add(etapa.etapaId);
            setExpediente((prev) => (prev?.id === id ? conEtapaActualizada(prev, data) : prev));
            nuevaRevision();
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (6 de 10):

```jsx
        }
    };

    const urlPropios = () => `/expedientes/${expediente.id}/requisitos-propios`;

    /** Devuelve true si se guardó, para que el formulario se cierre. */
```

por:

```jsx
        }
    };

    // Aprobar o devolver responden la etapa y los avisos, como el PUT de fechas.
    // Como las cargas, sube el turno: una carga que empezó antes no tapa la revisión nueva.
    const ponerRevision = (id, data, texto) => {
        turno.current += 1;
        setExpediente((prev) => (prev?.id === id ? conEtapaActualizada(prev, data) : prev));
        nuevaRevision();
        setAnuncio(texto);
    };

    // Un solo diálogo: la observación opcional y, si en pantalla le faltan requisitos, cuántos; así
    // se envía con `confirmar`. Si el servidor cuenta pendientes que la pantalla no veía (otra
    // persona desmarcó algo), pide confirmar con su número y se reintenta con `confirmar: true`.
    const aprobarEtapa = (etapa, otraVez = false) => ejecutar(async () => {
        const { id } = expediente;
        const dialogo = dialogoAprobarEtapa(etapa.titulo, etapa.faltan, otraVez);
        const { isConfirmed, value } = await Swal.fire({
            icon: dialogo.icono,
            titleText: dialogo.titulo,
            text: dialogo.texto,
            input: 'textarea',
            inputLabel: 'Observación (opcional)',
            inputAttributes: { maxlength: '2000' },
            showCancelButton: true,
            confirmButtonText: dialogo.boton,
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed || !montado.current) return;
        const url = `/expedientes/${id}/etapas/${etapa.etapaId}/aprobar`;
        let resultado;
        try {
            resultado = await aprobarConConfirmacion({
                faltan: etapa.faltan,
                enviar: async (confirmar, vistos) => (await axios.post(url, datosAprobarEtapa(value, confirmar, vistos))).data,
                confirmar: async (faltan) => {
                    if (!montado.current) return false;
                    const otra = dialogoAprobarEtapa(etapa.titulo, faltan, otraVez);
                    const confirmacion = await Swal.fire({
                        icon: otra.icono,
                        titleText: otra.titulo,
                        text: otra.texto,
                        showCancelButton: true,
                        confirmButtonText: otra.boton,
                        cancelButtonText: 'Cancelar',
                    });
                    return confirmacion.isConfirmed && montado.current;
                },
            });
        } catch (e) {
            if (!montado.current) return;
            avisarError('No se pudo aprobar la etapa', e);
            recargar();
            return;
        }
        if (!montado.current) return;
        // Sin aprobar, el avance se pone al día con lo que cambió en el servidor. Aprobando, la
        // respuesta trae la etapa al día (una recarga en curso podría reponer la anterior).
        if (!resultado.aprobada) {
            recargar();
            return;
        }
        ponerRevision(id, resultado.data, `${otraVez ? 'Aprobada de nuevo' : 'Aprobada'}: ${etapa.titulo}.`);
    });

    const devolverEtapa = (etapa) => ejecutar(async () => {
        const { id } = expediente;
        const { isConfirmed, value } = await Swal.fire({
            icon: 'warning',
            titleText: `¿Devolver la etapa «${etapa.titulo}»?`,
            text: 'Tu observación queda en la etapa y en el historial. La revisión solo avisa: el checklist se sigue pudiendo trabajar.',
            input: 'textarea',
            inputLabel: 'Observación',
            inputPlaceholder: 'Explica qué hay que corregir',
            inputAttributes: { maxlength: '2000', 'aria-required': 'true' },
            inputValidator: (texto) => (!texto?.trim() ? 'Escribe por qué devuelves la etapa.' : undefined),
            showCancelButton: true,
            confirmButtonText: 'Devolver',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed || !montado.current) return;
        try {
            const { data } = await axios.post(`/expedientes/${id}/etapas/${etapa.etapaId}/devolver`, { observacion: value.trim() });
            if (montado.current) ponerRevision(id, data, `Devuelta: ${etapa.titulo}.`);
        } catch (e) {
            if (!montado.current) return;
            avisarError('No se pudo devolver la etapa', e);
            recargar();
        }
    });

    const urlPropios = () => `/expedientes/${expediente.id}/requisitos-propios`;

    /** Devuelve true si se guardó, para que el formulario se cierre. */
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (7 de 10):

```jsx
        }
    });

    // Primero sin confirmar: si faltan requisitos, el servidor dice cuántos y en qué etapas, y se
    // pide confirmación (la revisión solo avisa). Si todo está cumplido, se confirma el congelado.
    const cerrarExpediente = () => ejecutar(async () => {
        const { id, progreso } = expediente;
        if (progreso.cumplidos >= progreso.total) {
            const { isConfirmed } = await Swal.fire({
                icon: 'question',
                titleText: '¿Cerrar el expediente?',
```

por:

```jsx
        }
    });

    // Primero sin confirmar: si faltan requisitos o hay etapas sin aprobar, el servidor dice cuáles
    // y se pide confirmación (la revisión solo avisa). Sin nada que avisar, se confirma el congelado.
    const cerrarExpediente = () => ejecutar(async () => {
        const { id } = expediente;
        if (cierreSinAvisos(expediente)) {
            const { isConfirmed } = await Swal.fire({
                icon: 'question',
                titleText: '¿Cerrar el expediente?',
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (8 de 10):

```jsx
            if (!montado.current) return;
            const { isConfirmed } = await Swal.fire({
                icon: 'warning',
                titleText: '¿Cerrar con requisitos pendientes?',
                html: htmlPendientesCierre(pendientes),
                showCancelButton: true,
                confirmButtonText: 'Cerrar de todos modos',
```

por:

```jsx
            if (!montado.current) return;
            const { isConfirmed } = await Swal.fire({
                icon: 'warning',
                titleText: tituloCierrePendientes(pendientes),
                html: htmlPendientesCierre(pendientes),
                showCancelButton: true,
                confirmButtonText: 'Cerrar de todos modos',
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (9 de 10):

```jsx
                    {etapasAvance.length > 0 && (
                        <section className="exc-etapas" aria-label="Avance y cronograma por etapa">
                            {etapasAvance.map((e) => (
                                <EtapaCronograma key={e.clave} etapa={e} editable={editable} hoy={hoy} onGuardarFecha={guardarFecha} />
                            ))}
                        </section>
                    )}
```

por:

```jsx
                    {etapasAvance.length > 0 && (
                        <section className="exc-etapas" aria-label="Avance y cronograma por etapa">
                            {etapasAvance.map((e) => (
                                <EtapaCronograma
                                    key={e.clave}
                                    etapa={e}
                                    editable={editable}
                                    ocupado={ocupado}
                                    hoy={hoy}
                                    onGuardarFecha={guardarFecha}
                                    onAprobar={aprobarEtapa}
                                    onDevolver={devolverEtapa}
                                />
                            ))}
                        </section>
                    )}
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (10 de 10):

```jsx
                        editable={editable}
                        ocupado={ocupado}
                        etapas={etapas}
                        onAplica={decidirAplica}
                        onGuardarPropio={guardarPropio}
                        onEliminarPropio={eliminarPropio}
```

por:

```jsx
                        editable={editable}
                        ocupado={ocupado}
                        etapas={etapas}
                        etapasAvisoAnterior={etapasConAvisoAnterior(expediente.avance_por_etapa)}
                        onAplica={decidirAplica}
                        onGuardarPropio={guardarPropio}
                        onEliminarPropio={eliminarPropio}
```

En `resources/js/utils/avisosContratacion.js`, reemplazar (1 de 6):

```js
// Avisos de contratación (fase 4B): el semáforo de cada etapa del expediente, la franja de avisos
// de la página del expediente, los chips de cada proceso (diálogo de procesos y ficha del
// proyecto) y el aviso del proceso en el módulo Contratos. Todo sale de lo que calcula el backend
// (`alerta`, `avisos`, `aviso_proceso`): aquí no se mira la fecha de hoy. Funciones puras,
```

por:

```js
// Avisos de contratación (fases 4B y 5): el semáforo y el estado de revisión de cada etapa del expediente, la franja de avisos
// de la página del expediente, los chips de cada proceso (diálogo de procesos y ficha del
// proyecto) y el aviso del proceso en el módulo Contratos. Todo sale de lo que calcula el backend
// (`alerta`, `avisos`, `aviso_proceso`): aquí no se mira la fecha de hoy. Funciones puras,
```

En `resources/js/utils/avisosContratacion.js`, reemplazar (2 de 6):

```js
    al_dia: { texto: 'Al día', tono: 'azul' },
    completa: { texto: 'Completa', tono: 'verde' },
    sin_completar: { texto: 'Sin completar', tono: 'neutro' },
};

/** La etiqueta del semáforo de una etapa (`alerta` de la API); sin fecha, ninguna. */
export const etiquetaAlerta = (alerta) => (ALERTAS[alerta] ? { ...ALERTAS[alerta] } : null);

const numero = (valor) => Number(valor) || 0;
const dias = (n) => (n === 1 ? '1 día' : `${n} días`);
```

por:

```js
    al_dia: { texto: 'Al día', tono: 'azul' },
    completa: { texto: 'Completa', tono: 'verde' },
    sin_completar: { texto: 'Sin completar', tono: 'neutro' },
    aprobada: { texto: 'Aprobada', tono: 'verde' },
};

/** La etiqueta del semáforo de una etapa (`alerta` de la API); sin fecha, ninguna. */
export const etiquetaAlerta = (alerta) => (ALERTAS[alerta] ? { ...ALERTAS[alerta] } : null);

// El estado visible de la revisión de una etapa (`revision.estado`, fase 5).
const REVISIONES = {
    pendiente: { texto: 'Pendiente', tono: 'neutro' },
    lista: { texto: 'Lista para revisar', tono: 'azul' },
    aprobada: { texto: 'Aprobada', tono: 'verde' },
    aprobada_con_cambios: { texto: 'Aprobada con cambios', tono: 'ambar' },
    devuelta: { texto: 'Devuelta', tono: 'coral' },
    sin_requisitos: { texto: 'Sin requisitos', tono: 'neutro' },
};

/** La etiqueta del estado de revisión de una etapa; null si no se conoce. */
export const etiquetaRevision = (estado) => (REVISIONES[estado] ? { ...REVISIONES[estado] } : null);

const numero = (valor) => Number(valor) || 0;
const dias = (n) => (n === 1 ? '1 día' : `${n} días`);
```

En `resources/js/utils/avisosContratacion.js`, reemplazar (3 de 6):

```js
export const textoSinActualizacion = (n) => (n === null || n === undefined ? 'Sin actualización' : `Sin actualización hace ${dias(n)}`);

/**
 * Los chips de un proceso desde su `avisos`: vencidas, próximas, cierre, sin actualización e
 * hitos (si hay etapas que contar), cada uno con su tono de `ct-estado-*` y un título que explica
 * la cifra. Lista vacía si no hay nada que mostrar.
 */
export const chipsAvisosProceso = (avisos) => {
```

por:

```js
export const textoSinActualizacion = (n) => (n === null || n === undefined ? 'Sin actualización' : `Sin actualización hace ${dias(n)}`);

/**
 * Los chips de un proceso desde su `avisos`: vencidas, próximas, devueltas, por revisar, cierre,
 * sin actualización e hitos (etapas aprobadas, si hay etapas que contar), cada uno con su tono de `ct-estado-*` y un título que explica
 * la cifra. Lista vacía si no hay nada que mostrar.
 */
export const chipsAvisosProceso = (avisos) => {
```

En `resources/js/utils/avisosContratacion.js`, reemplazar (4 de 6):

```js
            titulo: proximas === 1 ? '1 etapa del expediente próxima a vencer' : `${proximas} etapas del expediente próximas a vencer`,
        });
    }
    const cierre = textoCierre(avisos);
    if (cierre) {
        chips.push({
```

por:

```js
            titulo: proximas === 1 ? '1 etapa del expediente próxima a vencer' : `${proximas} etapas del expediente próximas a vencer`,
        });
    }
    const devueltas = numero(avisos.etapas_devueltas);
    if (devueltas > 0) {
        chips.push({
            clave: 'devueltas',
            texto: devueltas === 1 ? '1 devuelta' : `${devueltas} devueltas`,
            tono: 'coral',
            titulo: devueltas === 1 ? '1 etapa del expediente devuelta en la revisión' : `${devueltas} etapas del expediente devueltas en la revisión`,
        });
    }
    const porRevisar = numero(avisos.etapas_por_revisar);
    if (porRevisar > 0) {
        chips.push({
            clave: 'por-revisar',
            texto: `${porRevisar} por revisar`,
            tono: 'azul',
            titulo: porRevisar === 1 ? '1 etapa del expediente lista para revisar' : `${porRevisar} etapas del expediente listas para revisar`,
        });
    }
    const cierre = textoCierre(avisos);
    if (cierre) {
        chips.push({
```

En `resources/js/utils/avisosContratacion.js`, reemplazar (5 de 6):

```js
            clave: 'hitos',
            texto: `Hitos ${completadas}/${total}`,
            tono: completadas >= total ? 'verde' : 'neutro',
            titulo: `${completadas} de ${total} etapas del expediente completas`,
        });
    }
    return chips;
```

por:

```js
            clave: 'hitos',
            texto: `Hitos ${completadas}/${total}`,
            tono: completadas >= total ? 'verde' : 'neutro',
            titulo: `${completadas} de ${total} etapas del expediente aprobadas`,
        });
    }
    return chips;
```

En `resources/js/utils/avisosContratacion.js`, reemplazar (6 de 6):

```js
            tono: 'ambar',
        });
    }
    if (avisos.cierre_vencido) {
        franja.push({ clave: 'cierre', texto: 'La fecha de cierre del proceso ya pasó', tono: 'coral' });
    } else if (avisos.cierre_proximo) {
```

por:

```js
            tono: 'ambar',
        });
    }
    const devueltas = numero(avisos.etapas_devueltas);
    if (devueltas > 0) {
        franja.push({ clave: 'devueltas', texto: devueltas === 1 ? '1 etapa devuelta' : `${devueltas} etapas devueltas`, tono: 'coral' });
    }
    const porRevisar = numero(avisos.etapas_por_revisar);
    if (porRevisar > 0) {
        franja.push({
            clave: 'por-revisar',
            texto: porRevisar === 1 ? '1 etapa lista para revisar' : `${porRevisar} etapas listas para revisar`,
            tono: 'azul',
        });
    }
    if (avisos.cierre_vencido) {
        franja.push({ clave: 'cierre', texto: 'La fecha de cierre del proceso ya pasó', tono: 'coral' });
    } else if (avisos.cierre_proximo) {
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (1 de 2):

```js
    expediente_radicado: 'Radicó el expediente',
    expediente_cerrado: 'Cerró el expediente',
    version_aplicada: 'Aplicó una versión nueva de una plantilla',
};

const CAMPOS_PROPIO = { titulo: 'título', descripcion: 'descripción', categoria: 'categoría', caracter: 'carácter' };
```

por:

```js
    expediente_radicado: 'Radicó el expediente',
    expediente_cerrado: 'Cerró el expediente',
    version_aplicada: 'Aplicó una versión nueva de una plantilla',
    etapa_aprobada: 'Aprobó la etapa',
    etapa_devuelta: 'Devolvió la etapa',
};

const CAMPOS_PROPIO = { titulo: 'título', descripcion: 'descripción', categoria: 'categoría', caracter: 'carácter' };
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (2 de 2):

```js
            const cambios = cambiosConfiguracion(a, d);
            return cambios.length ? `${base}: ${enumerar(cambios)}` : base;
        }
        case 'version_aplicada':
            if (!d.plantilla || !d.version) return base;
            return `Aplicó la versión ${d.version} de ${d.plantilla}${a.version ? ` (antes ${a.version})` : ''}`;
```

por:

```js
            const cambios = cambiosConfiguracion(a, d);
            return cambios.length ? `${base}: ${enumerar(cambios)}` : base;
        }
        case 'etapa_aprobada': {
            const n = Number(d.pendientes_al_aprobar) || 0;
            const accion = d.reaprobacion === true || a.estado_revision === 'aprobada' ? 'Aprobó de nuevo la etapa' : base;
            return `${accion}${d.etapa ? ` ${d.etapa}` : ''}${n > 0 ? ` con ${n} ${n === 1 ? 'requisito pendiente' : 'requisitos pendientes'}` : ''}`;
        }
        case 'etapa_devuelta':
            return `${base}${d.etapa ? ` ${d.etapa}` : ''}${d.observacion ? `: ${d.observacion}` : ''}`;
        case 'version_aplicada':
            if (!d.plantilla || !d.version) return base;
            return `Aplicó la versión ${d.version} de ${d.plantilla}${a.version ? ` (antes ${a.version})` : ''}`;
```

En `resources/js/utils/expedienteContratacion.js`, reemplazar (1 de 6):

```js
// pendientes al cerrar y la elección de plantillas para crearlo. La agrupación de viabilización
// (agruparChecklist) no cambia. Funciones puras, probadas en expedienteContratacion.test.js.

import { etiquetaAlerta } from './avisosContratacion';
import { esIdContrato } from './contratosModulo';
import { opcionesEtapa } from './contratacion';
import { datosPropio, formularioPropio } from './expedienteChecklist';
```

por:

```js
// pendientes al cerrar y la elección de plantillas para crearlo. La agrupación de viabilización
// (agruparChecklist) no cambia. Funciones puras, probadas en expedienteContratacion.test.js.

import { etiquetaAlerta, etiquetaRevision } from './avisosContratacion';
import { esIdContrato } from './contratosModulo';
import { opcionesEtapa } from './contratacion';
import { datosPropio, formularioPropio } from './expedienteChecklist';
```

En `resources/js/utils/expedienteContratacion.js`, reemplazar (2 de 6):

```js
    porcentaje: a.porcentaje,
}));

/**
 * El avance por etapa que llega de una acción del checklist, sin reponer fechas viejas: esa
 * petición pudo salir antes de que se guardara una fecha. En las etapas `protegidas` (ids cuya
 * fecha se guardó en esta pantalla) se conservan las fechas y el semáforo actuales; solo si la
 * etapa se completó o dejó de estarlo se toma la fecha real que llega (la sugerida o su borrado).
 * El avance (conteos y porcentajes) siempre es el que llega.
 */
export const fusionarAvance = (actual, entrante, protegidas) => {
    if (!entrante) return actual;
    if (!protegidas || protegidas.size === 0) return entrante;
    const previas = new Map((actual ?? []).filter((a) => a.etapa).map((a) => [a.etapa.id, a]));
    return entrante.map((nueva) => {
        const id = nueva.etapa?.id;
        const previa = previas.get(id);
        if (!id || !protegidas.has(id) || !previa) return nueva;
        if (Boolean(previa.completa) !== Boolean(nueva.completa)) return { ...nueva, fecha_programada: previa.fecha_programada };
        return {
            ...nueva,
```

por:

```js
    porcentaje: a.porcentaje,
}));

// ---------- Revisión por etapa (fase 5) ----------

const APROBADAS = ['aprobada', 'aprobada_con_cambios'];

/** Si la etapa está aprobada (con o sin cambios después). */
const estaAprobada = (avance) => APROBADAS.includes(avance?.revision?.estado);

const momento = (iso) => {
    const ms = iso ? Date.parse(iso) : NaN;
    return Number.isNaN(ms) ? null : ms;
};

// Si la etapa que llega trae una revisión más vieja que la de pantalla (o ninguna): la petición
// salió antes de aprobarla o devolverla.
const revisionAtrasada = (previa, nueva) => {
    const antes = momento(previa?.revision?.fecha);
    if (antes === null) return false;
    const ahora = momento(nueva?.revision?.fecha);
    return ahora === null || ahora < antes;
};

/** Si la respuesta de una acción del checklist salió antes de una aprobación o devolución ya pintada. */
export const hayRevisionAtrasada = (actual, entrante) => {
    const previas = new Map((actual ?? []).filter((a) => a.etapa).map((a) => [a.etapa.id, a]));
    return (entrante ?? []).some((n) => n.etapa && revisionAtrasada(previas.get(n.etapa.id), n));
};

/**
 * `aviso_etapa_anterior` con la regla de la API: alguna etapa anterior (en el orden en que llegan,
 * el del catálogo) con requisitos que cuentan no está aprobada, y esta tiene algo cumplido. Hace
 * falta al aprobar o devolver una etapa: la respuesta trae solo esa, y el aviso de las siguientes
 * cambia. Solo toca las etapas que ya traen la clave.
 */
export const recalcularAvisoAnterior = (avance) => {
    let anteriorSinAprobar = false;
    return (avance ?? []).map((a) => {
        if (!a.etapa || !('aviso_etapa_anterior' in a)) return a;
        const aviso = anteriorSinAprobar && Number(a.cumplidos) > 0;
        if (Number(a.total) > 0 && !estaAprobada(a)) anteriorSinAprobar = true;
        return a.aviso_etapa_anterior === aviso ? a : { ...a, aviso_etapa_anterior: aviso };
    });
};

/** Los ids de las etapas cuya etapa anterior aún no está aprobada (para su sección del checklist). */
export const etapasConAvisoAnterior = (avance) => new Set(
    (avance ?? []).filter((a) => a.etapa && a.aviso_etapa_anterior).map((a) => a.etapa.id)
);

/**
 * El avance por etapa que llega de una acción del checklist, sin reponer fechas viejas: esa
 * petición pudo salir antes de que se guardara una fecha. En las etapas `protegidas` (ids cuya
 * fecha se guardó en esta pantalla) se conservan las fechas y el semáforo actuales; solo si la
 * etapa se completó o dejó de estarlo se toma la fecha real que llega (la sugerida o su borrado).
 * El avance (conteos y porcentajes) siempre es el que llega.
 *
 * Lo mismo con la revisión: si la petición salió antes de aprobar o devolver una etapa (su
 * revisión es más vieja que la de pantalla), se conservan la revisión, el semáforo y la fecha real
 * de esa etapa (la aprobación pudo poner la fecha real) y se recalcula el aviso de la etapa
 * anterior. Con la misma revisión o una más nueva vale la que llega: puede traer "aprobada con
 * cambios" o la devuelta que se volvió a trabajar.
 */
export const fusionarAvance = (actual, entrante, protegidas) => {
    if (!entrante) return actual;
    const conProtegidas = Boolean(protegidas && protegidas.size > 0);
    const atrasada = hayRevisionAtrasada(actual, entrante);
    if (!conProtegidas && !atrasada) return entrante;
    const previas = new Map((actual ?? []).filter((a) => a.etapa).map((a) => [a.etapa.id, a]));
    const protegerFechas = (nueva, previa) => {
        if (!conProtegidas || !protegidas.has(nueva.etapa.id)) return nueva;
        if (Boolean(previa.completa) !== Boolean(nueva.completa)) return { ...nueva, fecha_programada: previa.fecha_programada };
        return {
            ...nueva,
```

En `resources/js/utils/expedienteContratacion.js`, reemplazar (3 de 6):

```js
            fecha_real_sugerida: previa.fecha_real_sugerida,
            alerta: previa.alerta,
        };
    });
};

/** El expediente con el progreso y el avance por etapa que trae una respuesta parcial, si los trae. */
export const conAvance = (expediente, datos, protegidas) => ({
    ...expediente,
    progreso: datos?.progreso ?? expediente.progreso,
    avance_por_etapa: fusionarAvance(expediente.avance_por_etapa, datos?.avance_por_etapa, protegidas),
    avisos: datos?.avisos ?? expediente.avisos,
});

// ---------- Cronograma (fechas por etapa) ----------
```

por:

```js
            fecha_real_sugerida: previa.fecha_real_sugerida,
            alerta: previa.alerta,
        };
    };
    const fusionado = entrante.map((nueva) => {
        const previa = nueva.etapa ? previas.get(nueva.etapa.id) : null;
        if (!previa) return nueva;
        const conFechas = protegerFechas(nueva, previa);
        if (!revisionAtrasada(previa, nueva)) return conFechas;
        return {
            ...conFechas,
            revision: previa.revision,
            alerta: previa.alerta,
            fecha_real: previa.fecha_real,
            fecha_real_sugerida: previa.fecha_real_sugerida,
        };
    });
    return atrasada ? recalcularAvisoAnterior(fusionado) : fusionado;
};

/**
 * El expediente con el progreso y el avance por etapa que trae una respuesta parcial, si los trae.
 * Si la respuesta salió antes de una aprobación o devolución ya pintada, sus avisos (por revisar,
 * devueltas, hitos) son viejos: quedan los de pantalla.
 */
export const conAvance = (expediente, datos, protegidas) => ({
    ...expediente,
    progreso: datos?.progreso ?? expediente.progreso,
    avance_por_etapa: fusionarAvance(expediente.avance_por_etapa, datos?.avance_por_etapa, protegidas),
    avisos: (hayRevisionAtrasada(expediente.avance_por_etapa, datos?.avance_por_etapa) ? null : datos?.avisos) ?? expediente.avisos,
});

// ---------- Cronograma (fechas por etapa) ----------
```

En `resources/js/utils/expedienteContratacion.js`, reemplazar (4 de 6):

```js
    };
};

/** El avance por etapa para sus barras (avanceEtapas) con las fechas y el semáforo de cada una. */
export const etapasConFechas = (avance) => {
    const lista = avance ?? [];
    return avanceEtapas(lista).map((e, i) => ({ ...e, ...fechasEtapa(lista[i]) }));
};

/**
```

por:

```js
    };
};

const plural = (n, singular, varios) => (n === 1 ? `${n} ${singular}` : `${n} ${varios}`);

/** Un momento ISO (UTC) como día/mes/año en la zona de contratación; null si no hay o no es válido. */
export const fechaMomento = (iso) => {
    const ms = momento(iso);
    return ms === null ? null : fechaLegible(fechaHoyBogota(new Date(ms)));
};

const quienCuando = (accion, revision) => {
    const cuando = fechaMomento(revision.fecha);
    return `${accion}${revision.usuario ? ` por ${revision.usuario}` : ''}${cuando ? ` el ${cuando}` : ''}`;
};

/**
 * La revisión de una etapa para mostrarla (`revision` de `avance_por_etapa`): la etiqueta de su
 * estado, quién y cuándo la aprobó o la devolvió, su observación y una nota si cambió después.
 * Una devuelta que se volvió a trabajar se ve pendiente o lista, pero conserva la devolución.
 * Null en el grupo sin etapa o si la respuesta no trae la revisión.
 */
export const revisionEtapa = (avance) => {
    const revision = avance?.etapa ? avance.revision : null;
    if (!revision) return null;
    const { estado } = revision;
    let detalle = null;
    let nota = null;
    if (APROBADAS.includes(estado)) {
        const n = Number(revision.pendientes_al_aprobar) || 0;
        detalle = `${quienCuando('Aprobada', revision)}${n > 0 ? ` con ${plural(n, 'requisito pendiente', 'requisitos pendientes')}` : ''}`;
        if (estado === 'aprobada_con_cambios') nota = 'Algún requisito de la etapa cambió después de aprobarla.';
    } else if (revision.devuelta_at) {
        detalle = quienCuando('Devuelta', revision);
        if (estado !== 'devuelta') nota = 'Tuvo cambios después de la devolución.';
    }
    return { estado, etiqueta: etiquetaRevision(estado), detalle, observacion: revision.observacion || null, nota };
};

/**
 * El avance por etapa para sus barras (avanceEtapas) con las fechas y el semáforo de cada una, su
 * revisión, cuántos requisitos que cuentan le faltan y si su etapa anterior aún no está aprobada.
 */
export const etapasConFechas = (avance) => {
    const lista = avance ?? [];
    return avanceEtapas(lista).map((e, i) => ({
        ...e,
        ...fechasEtapa(lista[i]),
        revision: revisionEtapa(lista[i]),
        faltan: Math.max(0, (Number(e.total) || 0) - (Number(e.cumplidos) || 0)),
        avisoEtapaAnterior: Boolean(lista[i]?.etapa && lista[i]?.aviso_etapa_anterior),
    }));
};

/**
 * Los botones de revisión de una etapa (de etapasConFechas): solo para quien edita un expediente
 * en borrador, en una etapa del catálogo con revisión. "Aprobar etapa" si no está aprobada,
 * "Aprobar de nuevo" si está aprobada con cambios (reaprueba sin devolverla) y "Devolver" siempre;
 * una etapa sin requisitos que cuenten no tiene botones.
 */
export const accionesRevision = (etapa, editable) => {
    const estado = etapa?.revision?.estado;
    if (!editable || !estado || estado === 'sin_requisitos') return { aprobar: false, reaprobar: false, devolver: false };
    return { aprobar: !APROBADAS.includes(estado), reaprobar: estado === 'aprobada_con_cambios', devolver: true };
};

/** El diálogo de aprobar una etapa; si le faltan requisitos, dice cuántos (solo avisa). */
export const dialogoAprobarEtapa = (titulo, faltan, otraVez = false) => {
    const n = Number(faltan) || 0;
    if (n <= 0) {
        return {
            icono: 'question',
            titulo: otraVez ? `¿Aprobar de nuevo la etapa «${titulo}»?` : `¿Aprobar la etapa «${titulo}»?`,
            texto: 'La aprobación queda en el historial del expediente con tu nombre y la fecha.',
            boton: otraVez ? 'Aprobar de nuevo' : 'Aprobar etapa',
        };
    }
    return {
        icono: 'warning',
        titulo: otraVez ? `¿Aprobar de nuevo la etapa «${titulo}» con requisitos pendientes?` : `¿Aprobar la etapa «${titulo}» con requisitos pendientes?`,
        texto: `${n === 1 ? 'Falta' : 'Faltan'} ${plural(n, 'requisito', 'requisitos')} de esta etapa. Puedes aprobarla de todos modos: la revisión solo avisa.`,
        boton: 'Aprobar de todos modos',
    };
};

/**
 * El cuerpo del POST de aprobar: la observación sin espacios (o null) y, si se confirmó, `confirmar`
 * con `pendientes_vistos` (los que mostró la confirmación, para que el servidor avise si hay más).
 */
export const datosAprobarEtapa = (observacion, confirmar, pendientesVistos) => ({
    observacion: String(observacion ?? '').trim() || null,
    ...(confirmar ? { confirmar: true } : {}),
    ...(confirmar && Number.isInteger(pendientesVistos) && pendientesVistos >= 0 ? { pendientes_vistos: pendientesVistos } : {}),
});

/** Cuántos requisitos faltan si el servidor pide confirmar la aprobación (422 con `pendientes`); null si es otro error. */
export const pendientesAlAprobar = (error) => {
    const pendientes = error?.response?.status === 422 ? error.response.data?.pendientes : null;
    return typeof pendientes === 'number' ? pendientes : null;
};

/**
 * Aprueba una etapa con la confirmación de pendientes. `enviar(confirmar, vistos)` hace el POST y
 * devuelve la respuesta; `confirmar(n)` pregunta si se aprueba con n pendientes (true/false). Con
 * pendientes en pantalla va directo con `confirmar` y los vistos; si el servidor responde el 422 de
 * confirmación (hay pendientes que la pantalla no veía, o más que los vistos), pregunta con su
 * número y reintenta, tantas veces como haga falta. Otros errores se propagan.
 * Devuelve { aprobada, data }; `aprobada` es false si no se confirmó.
 */
export const aprobarConConfirmacion = async ({ faltan, enviar, confirmar }) => {
    let vistos = Math.max(0, Number(faltan) || 0);
    let confirmado = vistos > 0;
    for (;;) {
        try {
            return { aprobada: true, data: await enviar(confirmado, vistos) };
        } catch (e) {
            const n = pendientesAlAprobar(e);
            if (n === null) throw e;
            if (!(await confirmar(n))) return { aprobada: false, data: null };
            vistos = n;
            confirmado = true;
        }
    }
};

/**
```

En `resources/js/utils/expedienteContratacion.js`, reemplazar (5 de 6):

```js
/** El cuerpo del PUT de la etapa: solo el campo que cambió; vacío lo borra (null). */
export const datosFechaEtapa = (campo, valor) => (CAMPOS_FECHA_ETAPA.includes(campo) ? { [campo]: valor || null } : {});

/** El expediente con la etapa que respondió el PUT (`etapa`) en su lugar y los `avisos` nuevos. */
export const conEtapaActualizada = (expediente, datos) => {
    if (!expediente || !datos) return expediente;
    const id = datos.etapa?.etapa?.id;
    return {
        ...expediente,
        avance_por_etapa: (expediente.avance_por_etapa ?? []).map((a) => (id && a.etapa?.id === id ? datos.etapa : a)),
        avisos: datos.avisos ?? expediente.avisos,
    };
};
```

por:

```js
/** El cuerpo del PUT de la etapa: solo el campo que cambió; vacío lo borra (null). */
export const datosFechaEtapa = (campo, valor) => (CAMPOS_FECHA_ETAPA.includes(campo) ? { [campo]: valor || null } : {});

/**
 * El expediente con la etapa que respondió el PUT de fechas, aprobar o devolver (`etapa`) en su
 * lugar y los `avisos` nuevos. El aviso de la etapa anterior se recalcula en las demás: aprobar o
 * devolver una etapa lo cambia en las siguientes.
 */
export const conEtapaActualizada = (expediente, datos) => {
    if (!expediente || !datos) return expediente;
    const id = datos.etapa?.etapa?.id;
    return {
        ...expediente,
        avance_por_etapa: recalcularAvisoAnterior((expediente.avance_por_etapa ?? []).map((a) => (id && a.etapa?.id === id ? datos.etapa : a))),
        avisos: datos.avisos ?? expediente.avisos,
    };
};
```

En `resources/js/utils/expedienteContratacion.js`, reemplazar (6 de 6):

```js
/** Un propio de contratación se guarda con título y etapa. */
export const propioCompleto = (formulario) => Boolean(formulario.titulo.trim()) && Boolean(formulario.etapa_propia_id);

const plural = (n, singular, varios) => (n === 1 ? `${n} ${singular}` : `${n} ${varios}`);

/**
 * Los pendientes con que el servidor rechazó cerrar sin confirmar (`pendientes` del 422):
 * "Faltan 4 requisitos por cumplir" y una línea por etapa. Null si no hay.
 */
export const textoPendientesCierre = (pendientes) => {
    if (!pendientes?.total) return null;
    return {
        titulo: `${pendientes.total === 1 ? 'Falta' : 'Faltan'} ${plural(pendientes.total, 'requisito', 'requisitos')} por cumplir`,
        etapas: (pendientes.por_etapa ?? []).map((e) => `${tituloEtapa(e.etapa)}: ${plural(e.pendientes, 'pendiente', 'pendientes')}`),
    };
};

/** El cuerpo del aviso antes de confirmar el cierre, con los nombres escapados. */
export const htmlPendientesCierre = (pendientes) => {
    const texto = textoPendientesCierre(pendientes);
    if (!texto) return '';
    const etapas = texto.etapas.map((e) => `<li>${escaparHtml(e)}</li>`).join('');
    return `<p>${escaparHtml(texto.titulo)}.</p>${etapas ? `<ul style="text-align:left">${etapas}</ul>` : ''}`
        + '<p>Puedes cerrarlo de todos modos: la revisión solo avisa. Cerrado, el checklist queda congelado.</p>';
};

const fecha = (iso) => (iso ? new Date(iso).toLocaleDateString('es-CO') : null);

/** "Borrador", o "Cerrado el 18/11/2026 por Ana" (con lo que se sepa). */
```

por:

```js
/** Un propio de contratación se guarda con título y etapa. */
export const propioCompleto = (formulario) => Boolean(formulario.titulo.trim()) && Boolean(formulario.etapa_propia_id);

const etapasSinAprobar = (pendientes) => (Array.isArray(pendientes?.etapas_sin_aprobar) ? pendientes.etapas_sin_aprobar : []);

/**
 * Los pendientes con que el servidor rechazó cerrar sin confirmar (`pendientes` del 422):
 * "Faltan 4 requisitos por cumplir" y una línea por etapa; y, si hay etapas sin aprobar
 * (`etapas_sin_aprobar`), "Hay 2 etapas sin aprobar" con el estado de cada una en `sinAprobar`.
 * Sin requisitos pendientes, `titulo` es null. Null si no hay nada.
 */
export const textoPendientesCierre = (pendientes) => {
    const total = Number(pendientes?.total) || 0;
    const lista = etapasSinAprobar(pendientes);
    if (total === 0 && lista.length === 0) return null;
    const sinAprobar = lista.length > 0 ? {
        titulo: `Hay ${plural(lista.length, 'etapa', 'etapas')} sin aprobar`,
        etapas: lista.map((e) => `${tituloEtapa(e.etapa)}: ${(etiquetaRevision(e.estado)?.texto ?? 'Sin aprobar').toLowerCase()}`),
    } : null;
    return {
        titulo: total > 0 ? `${total === 1 ? 'Falta' : 'Faltan'} ${plural(total, 'requisito', 'requisitos')} por cumplir` : null,
        etapas: total > 0 ? (pendientes.por_etapa ?? []).map((e) => `${tituloEtapa(e.etapa)}: ${plural(e.pendientes, 'pendiente', 'pendientes')}`) : [],
        ...(sinAprobar ? { sinAprobar } : {}),
    };
};

const htmlLista = (titulo, lineas) => {
    const items = lineas.map((e) => `<li>${escaparHtml(e)}</li>`).join('');
    return `<p>${escaparHtml(titulo)}.</p>${items ? `<ul style="text-align:left">${items}</ul>` : ''}`;
};

/** El cuerpo del aviso antes de confirmar el cierre, con los nombres escapados. */
export const htmlPendientesCierre = (pendientes) => {
    const texto = textoPendientesCierre(pendientes);
    if (!texto) return '';
    return (texto.titulo ? htmlLista(texto.titulo, texto.etapas) : '')
        + (texto.sinAprobar ? htmlLista(texto.sinAprobar.titulo, texto.sinAprobar.etapas) : '')
        + '<p>Puedes cerrarlo de todos modos: la revisión solo avisa. Cerrado, el checklist queda congelado.</p>';
};

/** El título del diálogo de cierre con pendientes, según falten requisitos, aprobaciones o ambos. */
export const tituloCierrePendientes = (pendientes) => {
    const requisitos = (Number(pendientes?.total) || 0) > 0;
    const etapas = etapasSinAprobar(pendientes).length > 0;
    if (requisitos && etapas) return '¿Cerrar con pendientes?';
    return etapas ? '¿Cerrar con etapas sin aprobar?' : '¿Cerrar con requisitos pendientes?';
};

/**
 * Si el cierre no tiene nada que avisar: todo cumplido y todas las etapas con requisitos que
 * cuentan aprobadas. Entonces la pantalla confirma el congelado antes de enviar; si no, el
 * servidor responde con los pendientes y se confirma con ellos.
 */
export const cierreSinAvisos = (expediente) => {
    const { cumplidos = 0, total = 0 } = expediente?.progreso ?? {};
    if (cumplidos < total) return false;
    return (expediente?.avance_por_etapa ?? []).every((a) => !a.etapa || !a.revision || Number(a.total) === 0 || estaAprobada(a));
};

const fecha = (iso) => (iso ? new Date(iso).toLocaleDateString('es-CO') : null);

/** "Borrador", o "Cerrado el 18/11/2026 por Ana" (con lo que se sepa). */
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/avisosContratacion.test.js resources/js/utils/expedienteChecklist.test.js resources/js/utils/expedienteContratacion.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1295 passed.

Run: `npm test`
Expected: 821 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/css/ExpedienteContratacion.css resources/js/components/ChipsAvisosProceso.jsx resources/js/components/ExpedienteChecklist.jsx resources/js/components/ExpedienteContratacion.jsx resources/js/utils/avisosContratacion.js resources/js/utils/avisosContratacion.test.js resources/js/utils/expedienteChecklist.js resources/js/utils/expedienteChecklist.test.js resources/js/utils/expedienteContratacion.js resources/js/utils/expedienteContratacion.test.js
git commit -m "feat: stage review in the contracting screens"
```

---

### Tarea 5: Compilar para producción

- [ ] **Paso 1: Compilar**

Run: `npm run production`
Expected: `Compiled Successfully`, sin errores.

Justo antes de comitear, comprobar que `public/js/app.js` es el de producción: minificado y sin comentarios `/***/` al inicio. Si `npm run watch` lo reescribió, compilar otra vez.

- [ ] **Paso 2: Commit**

```bash
git add public/js/app.js public/js/app.js.map public/mix-manifest.json
git commit -m "build: production bundle for procurement phase 5"
```

Los archivos que no cambiaron se omiten. Los estilos nuevos de `ExpedienteContratacion.css` se importan desde el componente y van dentro de `app.js`: `public/css/app.css` no se comitea.

---

### Tarea 6: Verificación en la copia descartable

Usa la copia y las fotos de la tarea 1. Nunca `gest_pro`.

- [ ] **Paso 1: Migrar, revertir y volver a migrar, comparando**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

Expected: se aplica `2026_11_19_100022_add_revision_a_expediente_etapas`.

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c9_despues_$f.json" gestpro_verif_tmp && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c9_antes_$f.json" "$TMP_DIR/c9_despues_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces. El comparador admite claves nuevas; un valor distinto o algo que falta, no.

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT COUNT(*) FROM expediente_etapas;"
```

Expected: `0`.

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=1 --force
```

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c9_rb_$f.json" gestpro_verif_tmp --solo-tablas && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c9_tablas_antes_$f.json" "$TMP_DIR/c9_rb_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c9_re_$f.json" gestpro_verif_tmp && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c9_antes_$f.json" "$TMP_DIR/c9_re_$f.json"; done
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

Entrar por `http://127.0.0.1:8796/GestPro/` como el Administrador de prueba, borrando `localStorage` si hay una sesión anterior. Las llamadas a la API van con el token de esa sesión. Todo lo que sigue se hace **solo en la copia**.

Comprobar:

1. **El expediente del proceso 2:**
   - publicar la versión borrador de "Licitación pública – SECOP II" con `POST /catalogo-requisitos/versiones/{id}/publicar`;
   - crear el expediente del proceso 2 con esa plantilla: `POST /procesos/2/expediente` con `{"plantillas": [id]}`, usando el id de la plantilla de licitación (hay 2 compatibles: elegir esa, no "nueva plantilla");
   - a la etapa 4, por API, una `fecha_programada` pasada (ayer, por ejemplo) con `PUT /expedientes/{expediente}/etapas/{etapa}`.
2. **Aprobar en `/contratacion/2`:**
   - marcar el único requisito de la etapa 4: la etapa sigue "Vencida" y su revisión dice "Lista para revisar" (decisión 1: completa sin aprobar con la fecha pasada sigue vencida);
   - "Aprobar etapa" con una observación: la etapa queda "Aprobada", con quién y cuándo, la fecha real de hoy **sin** la marca "sugerida" (decisión 2) y la observación visible; deja de estar vencida;
   - desmarcar el requisito: la etapa queda "Aprobada con cambios", con la nota "Algún requisito de la etapa cambió después de aprobarla." y el botón "Aprobar de nuevo";
   - "Aprobar de nuevo": pide la confirmación "Falta 1 requisito de esta etapa…" con "Aprobar de todos modos"; al confirmar queda "Aprobada" "con 1 requisito pendiente".
3. **Devolver:**
   - "Devolver" la etapa 1 sin escribir observación: el diálogo no deja seguir y dice "Escribe por qué devuelves la etapa.";
   - con "Falta el CDP firmado": la etapa queda "Devuelta" con la observación, y la franja dice "1 etapa devuelta";
   - marcar un requisito de la etapa 1: la revisión muestra "Tuvo cambios después de la devolución.", conserva la observación y la franja deja de contarla como devuelta.
4. **El historial del expediente** muestra "Aprobó la etapa …", "Aprobó de nuevo la etapa … con 1 requisito pendiente" y "Devolvió la etapa …: Falta el CDP firmado".
5. **"Cerrar expediente":** el diálogo de confirmación lista los requisitos pendientes por etapa y las etapas sin aprobar con su estado. Cancelar: el expediente sigue en borrador.
6. **La ficha `/proyectos/1`:** el bloque Procesos muestra para el proceso 2 "Hitos 1/11" y ningún chip de vencidas.
7. **El usuario Consulta de prueba** (cerrar la sesión del Administrador y entrar con él): en `/contratacion/2` no hay botones de revisión y la observación sigue visible; un `POST /expedientes/{expediente}/etapas/{etapa}/aprobar` con su token responde 403.
8. **La viabilización no cambia:** `GET /expedientes` sigue con los 11 expedientes y `/viabilizacion/<proyecto con expediente>` se ve como antes.

La consola del navegador no muestra errores nuevos. Los 401 de una sesión vieja o del cambio de usuario, los 422 de las validaciones probadas y el 403 del usuario Consulta son esperados.

- [ ] **Paso 3: Comprobar que revertir se detiene con revisiones**

Con las etapas aprobadas y devueltas del paso 2 todavía en la copia:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=1 --force
```

Expected: se detiene con "No se puede revertir: hay etapas aprobadas o devueltas en los expedientes de contratación, cuya revisión se perdería. Restaure un respaldo." y las columnas siguen ahí:

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT estado_revision, COUNT(*) FROM expediente_etapas GROUP BY estado_revision;"
```

- [ ] **Paso 4: Limpiar**

Detener el servidor, eliminar la copia y devolver `public/` a lo comiteado si se compiló en desarrollo:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 5: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas, 1295.

Run: `npm test`
Expected: pasan todas, 821.

---

### Tarea 7: Informar y pedir validación

- [ ] **Paso 1: Actualizar el estado de la propuesta**

En `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, reemplazar:

```markdown
La etapa 4B (fechas por etapa y avisos, migración 22) está validada y aplicada en `gest_pro` (2026-11-19). La fase 4 queda completa.
```

por:

```markdown
La etapa 4B (fechas por etapa y avisos, migración 22) está validada y aplicada en `gest_pro` (2026-11-19). La fase 4 queda completa. La fase 5 (revisión por etapa, migración 23) está implementada y verificada en copia, pendiente de validar y aplicar en `gest_pro`.
```

```bash
git add docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md
git commit -m "docs: mark procurement phase 5 as implemented"
```

- [ ] **Paso 2: Informar**

Informar:
- los totales de pruebas;
- las comparaciones (migrar, revertir y volver a migrar), la tabla `expediente_etapas` vacía y que revertir se detiene con revisiones;
- lo revisado en el navegador.

Pedir **dos** decisiones por separado:
1. si valida la fase 5;
2. si autoriza aplicar la migración 23 en `gest_pro`.

Recordar que, con los datos actuales, no hay expedientes de contratación: `expediente_etapas` está vacía, las columnas nuevas nacen sin filas y ninguna cifra cambia.

---

### Tarea 8: Aplicar en `gest_pro` (solo con autorización)

No ejecutar esta tarea sin un sí explícito del usuario a este paso. Si la comparación muestra diferencias, restaurar el respaldo: no usar `migrate:rollback`.

- [ ] **Paso 1: Respaldar**

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro > "$TMP_DIR/gest_pro_antes_contratacion_fase5.sql"
```

Expected: el archivo existe y termina en `-- Dump completed`.

- [ ] **Paso 2: Tomar las fotos antes de migrar (solo tablas)**

```bash
for f in 2 3; do php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c9_real_antes_$f.json" gest_pro --solo-tablas; done
```

- [ ] **Paso 3: Migrar hacia adelante**

Run: `php artisan migrate --force`
Expected: se aplica `2026_11_19_100022_add_revision_a_expediente_etapas`.

- [ ] **Paso 4: Comparar**

```bash
for f in 2 3; do php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c9_real_despues_$f.json" gest_pro && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c9_real_antes_$f.json" "$TMP_DIR/c9_real_despues_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

```bash
/c/xampp/mysql/bin/mysql -u root gest_pro -e "SELECT COUNT(*) FROM expediente_etapas;"
```

Expected: `0`.

- [ ] **Paso 5: Informar**

Dar el resultado y la ruta completa del respaldo, y avisar que está en una carpeta temporal.

---

## Criterio de la fase

La fase está lista cuando:

- cada etapa de un expediente de contratación se puede aprobar (también con requisitos pendientes, confirmando) y devolver con una observación, por API y en la página del expediente, y una aprobada con cambios se aprueba de nuevo sin devolverla;
- cada aprobación y devolución queda en el historial del expediente, con quién, cuándo, la observación y los pendientes;
- el estado de revisión de cada etapa se ve en la página del expediente, y las etapas por revisar y devueltas en la franja, la lista de procesos y la ficha del proyecto;
- una etapa deja de estar vencida al aprobarse, los hitos cuentan las etapas aprobadas y el cierre avisa de las etapas sin aprobar;
- la revisión nunca bloquea marcar requisitos, cambiar fechas ni cerrar; un expediente cerrado no deja revisar;
- la viabilización no cambia: mismas respuestas, mismas cifras, y sus pruebas y la paridad del evaluador pasan;
- ningún dato cambia al migrar ni al revertir, y revertir se detiene si hay etapas aprobadas o devueltas.
