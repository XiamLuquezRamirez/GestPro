# Contratación, etapa 4B: fechas por etapa y avisos. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** que cada etapa de un expediente de contratación tenga una fecha programada y una fecha real, y que el sistema avise de las etapas vencidas o próximas a vencer, del cierre del proceso y de los procesos sin actualización. Los avisos se ven en la página del expediente, en la lista de procesos (diálogo de procesos del proyecto y bloque Procesos de la ficha) y en el módulo Contratos. La viabilización no cambia.

**Arquitectura:**
- **Migración 22:** `2026_11_19_100021_create_expediente_etapas_table` crea `expediente_etapas`: `expediente_id` (cae con el expediente), `etapa_id` (restrict), `fecha_programada` y `fecha_real` (date, nullable), `fecha_real_sugerida` (boolean, false por defecto), `usuario_actualizo_id` (null al eliminar el usuario), timestamps y un único `(expediente_id, etapa_id)`. Nace vacía. El `down()` se detiene si la tabla tiene registros.
- **Configuración:** `config/contratacion.php` con `zona` (`America/Bogota`), `dias_proxima` (3) y `dias_sin_actualizacion` (15), cada una con su variable de entorno.
- **Cronograma:** el modelo `ExpedienteEtapa`, la ruta `PUT /expedientes/{expediente}/etapas/{etapa}` (`CronogramaExpedienteController` y el servicio `CronogramaExpediente`) y, en `ExpedienteService`, el punto único que ajusta la fecha real sugerida y agrega fechas y semáforo a `avance_por_etapa`, más `avisos` al expediente.
- **Avisos:** el servicio de lectura `App\Services\Contratacion\AvisosContratacion` calcula el semáforo de cada etapa y el resumen de cada proceso en lote. Lo usan el expediente, los procesos (`ProcesosContratacion`, `ExpedientesContratacion`), la ficha del proyecto (`ProyectoController`) y los contratos (`ContratosService`, clave `aviso_proceso`).
- **Pantallas:** en `ExpedienteContratacion.jsx`, la franja de avisos y, por etapa, las fechas editables y la etiqueta del semáforo; los chips de avisos (`ChipsAvisosProceso.jsx`) en `ProcesosProyecto.jsx` y `FichaProyecto.jsx`; el aviso del proceso en `ContratosModulo.jsx`. Las etiquetas salen de utilidades puras (`utils/avisosContratacion.js` y `utils/expedienteContratacion.js`) con pruebas Vitest.

**Tecnologías:** Laravel 12, PHP 8.2, MariaDB 10.4, React 18 con Laravel Mix y react-router, PHPUnit sobre SQLite en memoria, Vitest.

**Fuente:**
- La propuesta `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, aprobada:
  - fase 4, segunda mitad: la etapa **4B** (esta); la 4A (peso y avance ponderado) ya está aplicada;
  - regla R14: etapa vencida con `fecha_programada` pasada y sin completar; próxima con 3 días o menos (días calendario); sin actualización sin eventos ni cambios en 15 días;
  - criterio 11 de la sección 9 (alertas);
  - sección 6: la tabla `expediente_etapas`; el umbral de días va en configuración, sin tabla nueva.
- El diseño `docs/superpowers/specs/2026-11-17-contratacion-revision-design.md`, sección "Cronograma".
- Decisiones del usuario (2026-11-19): la fecha real es **sugerida y corregible**; los avisos van en la página del expediente, en la lista de procesos y en el módulo Contratos. El tablero de Contratación sigue en la fase 6.

**Estado de partida:** rama `feature/contratacion`, igual a `main` (`7afbde2`). La etapa 4A está validada y aplicada en `gest_pro`. Pasan 1229 pruebas PHP y 722 JS.

**Datos reales comprobados hoy en `gest_pro`:**
- **Expedientes:** 11, todos de viabilización, con 326 filas de requisitos.
- **Plantillas:** 5. De contratación, "Licitación pública – SECOP II", **en borrador**, y "nueva plantilla", publicada.
- **Requisitos de catálogo:** 238.
- **Procesos:** 2. El proceso 2 está en preparación, con fecha de cierre 2026-11-20.
- **Contratos:** 7.

No hay ningún expediente de contratación, así que **la tabla nueva queda vacía al aplicar** y ninguna respuesta existente cambia de valor: solo ganan claves nuevas.

---

## Reglas de ejecución

- **Nada de esto se aplica a `gest_pro` sin autorización del usuario.** Allí nunca se usa `migrate:rollback`: si hay que volver atrás, se restaura el respaldo. La tarea 8 aplica la migración 22, y solo con un sí explícito.
- **La viabilización no cambia ni una respuesta ni una cifra.** Las pruebas existentes de `tests/Feature/Viabilizacion/**`, los fixtures de paridad (`tests/Fixtures/paridad_*`) y `ExpedientesContratacionAisladosTest` no se modifican y deben pasar. Las únicas pruebas existentes que cambian son de contratación y solo ganan claves nuevas: `AvancePonderadoApiTest` y `ExpedienteContratacionApiTest` (fechas y semáforo en `avance_por_etapa`, `avisos` en el expediente), `ContratosBusquedaApiTest` (`aviso_proceso`) y `ProcesosContratacionApiTest` (`avisos` en el proceso). En JS cambia `expedienteContratacion.test.js`, que gana casos.
- Commits autorizados solo en `feature/contratacion`. Sin push. Mensajes en inglés, terminados con la línea `Co-Authored-By` del modelo que comitea.
- Nomenclatura en español sin tildes para tablas, columnas, clases y métodos nuevos. Los textos para el usuario van en español con tuteo. Las migraciones, que son herramientas del operador, conservan el registro de usted.
- **Fechas en días calendario y en la zona `config('contratacion.zona')` (`America/Bogota`).** `app.timezone` sigue en UTC: los `created_at`/`updated_at` que responde la API no cambian. "Hoy" sale de `now()` pasado a esa zona, para poder fijarlo en pruebas con `travelTo`. En JS las utilidades reciben `hoy` como argumento, calculado en la zona de Bogotá, nunca con `toISOString()`.
- Pruebas: `php artisan test` sin definir `DB_DATABASE`, para que use SQLite en memoria, y `npm test`. Si Vitest crea `resources/js/node_modules`, se borra.
- **Aplicar los bloques exactos.** Cada reemplazo aparece una sola vez en su archivo. Si no lo encuentras tal cual, detente y avisa. Se recomienda aplicarlos con un script que los lea del plan y exija una coincidencia por reemplazo.
- **No usar `git stash`:** el usuario tiene stashes propios.
- El usuario suele tener `npm run watch` corriendo, que reescribe `public/`. Solo la tarea 5 comitea `public/`, después de `npm run production`.
- No tocar ni comitear `public/images/marca-completa.png` ni `public/images/marca-completa1.png`: son cambios del usuario.
- `.env` está versionado: nunca se agrega a un commit. Comitear siempre con rutas explícitas.
- `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio. Hay que definirlo en cada comando, porque el estado de la terminal no se conserva.
- **MariaDB no hace transaccional el DDL.** Por eso cada paso de la migración 22 lleva su guarda (`hasTable`, el índice único y cada clave foránea por separado) y se puede reintentar.
- **El prefijo de la API (`/GestPro`) es también la base del SPA.** `/contratacion/:procesoId`, `/proyectos/:id` y `/contratos` no son rutas GET de la API, y la etapa no agrega rutas nuevas al front.

## Decisiones de este plan

**Datos**
- `expediente_etapas` según la sección 6, **sin** las columnas de revisión (`estado_revision` y las demás): son de la fase 5.
- Solo expedientes de contratación. La fila de una etapa nace al guardar su primera fecha o al sugerir su fecha real (una por expediente y etapa). `ProyectoExpediente::etapasCronograma()` es la relación.
- El `down()` se detiene con "No se puede revertir: hay fechas en el cronograma de los expedientes de contratación, que se perderían. Restaure un respaldo." si la tabla tiene registros. Vacía, la elimina.

**Fechas de una etapa (`PUT /expedientes/{expediente}/etapas/{etapa}`)**
- Permiso `can:editar-datos`. Cuerpo con `fecha_programada` y/o `fecha_real` (`Y-m-d` o `null`): lo que no llega no cambia y `null` borra.
- Responde 422 con "El expediente no es de contratación.", "El expediente está cerrado.", "La etapa no tiene requisitos en este expediente." o, para una fecha real futura, "La fecha real no puede ser posterior a hoy." (también en `errors.fecha_real`). La fecha programada puede estar en el pasado.
- Poner la fecha real a mano, aunque sea la misma sugerida, la deja como de una persona (`fecha_real_sugerida = false`).
- Lo que cambia queda en el historial del expediente como `etapa_fechas`, con la etapa y solo los campos que cambiaron. Sin cambios no se registra nada.
- Responde la etapa presentada y los `avisos` del expediente.

**Fecha real sugerida**
- Un solo punto del servicio la ajusta tras cualquier acción que cambie el avance: marcar o desmarcar, no aplica, aprobar o rechazar, propios, versión nueva. Cada etapa que **pasa a completa** (todo lo que cuenta cumplido, `total > 0`) y no tiene fecha real recibe la de hoy como sugerida. Una que **deja de estar completa** pierde la sugerida; la que puso una persona se conserva.
- **Marcador "descartada":** si una persona vacía la fecha real de una etapa completa, la fila queda con `fecha_real_sugerida = true` y `fecha_real = null`. Así no se vuelve a sugerir mientras la etapa siga completa; si deja de estarlo y se completa otra vez, sí. La API muestra `fecha_real_sugerida` solo con una fecha real.
- **También al sincronizar** el checklist (crear el expediente, versión nueva), con `DB::afterCommit`, cuando la transacción más externa se confirma.
- **Un fallo del ajuste no rompe la acción:** la acción ya guardada responde 200, sin la sugerida, y la siguiente lo reintenta. El error se reporta.

**Semáforo por etapa**
- Cada elemento de `avance_por_etapa` gana `fecha_programada`, `fecha_real`, `fecha_real_sugerida`, `completa` y `alerta`.
- `completa`: `total > 0` y `cumplidos === total`, por conteos, sin pesos.
- `alerta` en un expediente en borrador: `completa`; si no, `vencida` (programada antes de hoy), `proxima` (de hoy a hoy + 3), `al_dia` (más lejana) o `sin_fecha`.
- **Expediente cerrado:** las etapas que no se completaron quedan `sin_completar`, con o sin fecha programada; las completas siguen `completa`. No hay vencidas ni próximas.
- El grupo de filas sin etapa nunca tiene fechas: `completa` o `sin_fecha` (`sin_completar` si está cerrado).

**Avisos (`avisos`, el mismo objeto en el expediente y en el proceso)**
- `etapas_vencidas` y `etapas_proximas`: conteos. 0 sin expediente, con el expediente cerrado o con el proceso en un **estado final** (`celebrado`, `desierto`, `cancelado`, `revocado`); en ese último caso el semáforo de cada etapa no cambia, solo los conteos.
- `cierre_proximo` (fecha de cierre de hoy a hoy + 3) y `cierre_vencido` (antes de hoy), solo con el proceso en `preparacion` o `publicado`.
- `dias_para_cierre`: días de hoy a la fecha de cierre (negativo si pasó, `null` sin fecha), para el chip "Cierre en N días".
- `sin_actualizacion` y `dias_sin_actualizacion`: la última actividad es la más reciente entre el `updated_at` del proceso y el último evento del historial de su expediente, pasadas a la zona de contratación. 15 días o más avisa, salvo en un estado final o con el expediente cerrado.
- `hitos`: `{completadas, total}`, etapas completas sobre etapas con requisitos que cuentan.
- **Una sola clave `avisos` en el proceso**, siempre presente, también sin expediente (con 0 en etapas e hitos). Sale en la lista de procesos del proyecto, al crear, editar y cambiar de estado un proceso, y en la ficha. El bloque `expediente` del proceso no cambia.
- Todo en lote: la lista de procesos, la ficha y la búsqueda de contratos no hacen consultas por proceso ni por contrato. **La ficha calcula los avisos una sola vez**, y sus contratos reusan los de la lista de procesos.

**Contratos (`aviso_proceso`)**
- En la búsqueda de contratos, en el detalle y en `listarContratos`: `null`, o `{proceso_id, etapas_vencidas, cierre_vencido, sin_actualizacion, dias_sin_actualizacion}` si el proceso tiene etapas vencidas, el cierre vencido o está sin actualización.

**Pantallas**
- **Página del expediente:** arriba, una franja de avisos con frases ("1 etapa vencida", "1 etapa próxima a vencer", "El proceso cierra en N días", "Sin actualización hace N días"). En cada etapa, la fecha programada y la real con un input de fecha, la marca "sugerida" y la etiqueta del semáforo: "Vencida", "Próxima", "Al día", "Completa" o "Sin completar".
- **Guardar:** al salir del campo o con Enter, no a cada tecla. Los errores del servidor se muestran bajo el campo.
- El `max` de la fecha real es hoy en la zona de Bogotá.
- **Solo lectura** (expediente cerrado o rol de consulta): las fechas se muestran como texto `dd/mm/aaaa`.
- **Fusión de respuestas:** una respuesta que llega tarde no pisa una fecha que ya se guardó después.
- **Procesos y ficha:** chips "N vencidas", "N próximas", "Cierre en N días" o "Cierre vencido", "Sin actualización hace N días" y "Hitos C/T".
- **Contratos:** en la lista, bajo el proceso, el aviso "El proceso tiene N etapas vencidas" (o cierre vencido, o sin actualización) con el enlace "Checklist" a `/contratacion/:procesoId`, sin encimarse con la columna Estado; en el contrato abierto, el mismo aviso en un bloque.

## Fuera de esta etapa

- **Fase 5:** la revisión por etapa. Entonces la fecha real pasará a ser la de aprobación.
- **Fase 6:** el tablero de Contratación.
- Plazos orientativos por etapa en la plantilla.
- Días hábiles: todo se cuenta en días calendario.

## Lo que ya se ensayó

El plan se ensayó completo en un clon descartable fuera del repositorio, y este documento se generó con el código de ese ensayo. Un script comprobó que aplicar sus bloques sobre la rama reproduce el ensayo exactamente.

- **Pruebas:** con el código de las tareas 2 a 4 pasan 1262 pruebas PHP y 772 JS. Cada tarea intermedia también pasa sus suites completas. Ninguna prueba existente de viabilización ni fixture de paridad se modificó.
- **En una copia de `gest_pro` con los datos reales,** las fotos de la tarea 1 no mostraron ninguna diferencia al migrar, al revertir la migración 22 (comparando solo tablas) ni al volver a migrar. La tabla `expediente_etapas` quedó vacía.
- **En el navegador, sobre esa copia:**
  - **Expediente:** con la versión borrador de la plantilla de licitación publicada solo en la copia, se creó el expediente del proceso 2 con esa plantilla. Por API, la etapa 1 recibió una fecha programada pasada y la etapa 2 una a 2 días; una fecha real futura respondió 422.
  - **Página `/contratacion/2`:** la franja dijo "1 etapa vencida · 1 etapa próxima a vencer · El proceso cierra en 1 día" (el 2026-11-19), con las etiquetas "Vencida" y "Próxima". Al editar en la pantalla la fecha programada de la etapa 1, se guardó al salir del campo y pasó a "Al día". Al marcar el único requisito de la etapa 4, quedó "Completa" con la fecha real de hoy "sugerida".
  - **Ficha `/proyectos/1`:** la columna Avisos del bloque Procesos mostró los chips del proceso 2.
  - **Contratos:** con el contrato 1 vinculado al proceso 2 en la copia, `/contratos` mostró el aviso "El proceso tiene 1 etapa vencida" con su enlace, y `/contratos/1` el bloque del aviso. En esta verificación se encontró que el aviso de la lista se encimaba con la columna Estado, y se corrigió.
  - **Cierre:** al cerrar el expediente con `confirmar`, las etapas sin completar dijeron "Sin completar", las fechas quedaron como texto, los avisos dejaron de contar vencidas y un `PUT` de fechas respondió 422 "El expediente está cerrado.".
  - **Consola:** solo un 401 de una sesión previa y los 422 provocados.
- **Una revisión independiente** no encontró hallazgos altos. Encontró 2 medios y 8 bajos, ya corregidos en las tareas correspondientes salvo dos bajos aceptados:
  - medio: "hoy" se calculaba en UTC; ahora se calcula en la zona `America/Bogota`;
  - medio: una fecha real vaciada a mano se volvía a sugerir; ahora queda descartada mientras la etapa siga completa;
  - bajo aceptado: si una versión nueva de la plantilla saca una etapa entera, su fecha sugerida queda huérfana en la tabla;
  - bajo aceptado: el costo del aviso en la búsqueda de contratos, que se calcula en lote, sin consultas por contrato.

---

### Tarea 1: Punto de partida y fotos con el código actual

Las fotos se toman **antes de cambiar código**. La copia `gestpro_verif_tmp` se conserva hasta la tarea 6.

- [ ] **Paso 1: Comprobar la rama, el estado y las pruebas**

Run: `git branch --show-current`
Expected: `feature/contratacion`

Run: `git status --short`
Expected: como mucho, `public/` (si corre `npm run watch`) y las dos imágenes del usuario.

Run: `php artisan test`
Expected: pasan todas, 1229. Si alguna falla, detenerse y avisar.

Run: `npm test`
Expected: pasan todas, 722.

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
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c8_antes_$f.json" gestpro_verif_tmp; DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c8_tablas_antes_$f.json" gestpro_verif_tmp --solo-tablas; done
```

Expected: `Expedientes: 11, filas: 326, plantillas: 5, requisitos: 238` y `Contratos: 7, procesos: 2, actas: 2, expedientes: 11`, con las cifras actuales de `gest_pro`.

---

### Tarea 2: Migración 22: fechas por etapa, fecha real sugerida y avisos del expediente

La tabla `expediente_etapas`, `config/contratacion.php`, el modelo `ExpedienteEtapa` y la ruta `PUT /expedientes/{expediente}/etapas/{etapa}`. El servicio ajusta la fecha real sugerida en un solo punto, agrega fechas y semáforo a cada etapa del avance y `avisos` al expediente (`AvisosContratacion`). La viabilización no cambia.

**Files:**
- Create: `app/Http/Controllers/CronogramaExpedienteController.php`
- Create: `app/Models/ExpedienteEtapa.php`
- Create: `app/Services/Contratacion/AvisosContratacion.php`
- Create: `app/Services/Contratacion/CronogramaExpediente.php`
- Create: `config/contratacion.php`
- Create: `database/migrations/2026_11_19_100021_create_expediente_etapas_table.php`
- Test (create): `tests/Feature/Contratacion/CronogramaEtapasApiTest.php`
- Test (create): `tests/Feature/Schema/ExpedienteEtapasTest.php`
- Modify: `app/Models/ProyectoExpediente.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `routes/viabilizacion.php`
- Test: `tests/Feature/Contratacion/AvancePonderadoApiTest.php`
- Test: `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/CronogramaEtapasApiTest.php`:

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
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cronograma de un expediente de contratación (R14, criterio 11): fecha programada y real de cada
 * etapa, la fecha real sugerida al completarse una etapa, el semáforo por etapa y los avisos del
 * expediente. Hoy es el 19 de noviembre de 2026 salvo que la prueba diga otra cosa.
 */
class CronogramaEtapasApiTest extends TestCase
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

    private function proceso(array $cambios = []): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => $this->proyecto->id,
            'objeto' => 'Construcción del acueducto',
            'modalidad' => Modalidad::where('clave', 'licitacion_publica')->value('id'),
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-001',
            ...$cambios,
        ]);
    }

    private function expediente(?PlantillaChecklist $plantilla = null, ?ProcesoLicitacion $proceso = null): ProyectoExpediente
    {
        $plantilla ??= $this->plantilla();
        $proceso ??= $this->proceso();
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

    private function fechas(ProyectoExpediente $expediente, string $etapa, array $datos)
    {
        return $this->como()->putJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa($etapa)->id}", $datos);
    }

    /** El elemento de avance_por_etapa de la etapa dada. */
    private function deEtapa(array $avance, string $clave): array
    {
        return collect($avance)->firstWhere('etapa.id', $this->etapa($clave)->id);
    }

    private function viabilizacion(): ProyectoExpediente
    {
        $this->proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        $servicio = app(ExpedienteService::class);

        return $servicio->confirmarConfiguracion($servicio->crear($this->proyecto->fresh(), ['fase_sgr' => 3], $this->gestor), ['fase_sgr' => 3], $this->gestor);
    }

    // ------------------------------------------------------------ guardar fechas

    public function test_guarda_la_fecha_programada_y_la_real_con_su_historial(): void
    {
        $expediente = $this->expediente();
        $planeacion = $this->etapa('planeacion');

        $r = $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-25'])->assertOk();
        $r->assertJsonPath('etapa', [
            'etapa' => ['id' => $planeacion->id, 'nombre' => $planeacion->nombre, 'orden' => $planeacion->orden],
            'total' => 2, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 2, 'peso_cumplido' => 0,
            'fecha_programada' => '2026-11-25', 'fecha_real' => null, 'fecha_real_sugerida' => false,
            'completa' => false, 'alerta' => 'al_dia',
        ]);
        $this->assertSame(['etapa', 'avisos'], array_keys($r->json()));

        // Lo que no llega no cambia.
        $this->fechas($expediente, 'planeacion', ['fecha_real' => '2026-11-18'])->assertOk()
            ->assertJsonPath('etapa.fecha_programada', '2026-11-25')
            ->assertJsonPath('etapa.fecha_real', '2026-11-18');
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => null])->assertOk()
            ->assertJsonPath('etapa.fecha_programada', null)
            ->assertJsonPath('etapa.fecha_real', '2026-11-18')
            ->assertJsonPath('etapa.alerta', 'sin_fecha');

        $fila = ExpedienteEtapa::where('expediente_id', $expediente->id)->sole();
        $this->assertSame($planeacion->id, $fila->etapa_id);
        $this->assertSame($this->gestor->id, $fila->usuario_actualizo_id);
        $this->assertNull($fila->fecha_programada);
        $this->assertSame('2026-11-18', $fila->fecha_real->format('Y-m-d'));

        $historial = DB::table('checklist_historial')->where('evento', 'etapa_fechas')->orderBy('id')->get()
            ->map(fn ($h) => [$h->entidad_tipo, (int) $h->expediente_id, json_decode((string) $h->antes, true), json_decode((string) $h->despues, true)])
            ->all();
        $base = ['etapa_id' => $planeacion->id, 'etapa' => $planeacion->nombre];
        $this->assertSame([
            ['expediente', $expediente->id, [...$base, 'fecha_programada' => null], [...$base, 'fecha_programada' => '2026-11-25']],
            ['expediente', $expediente->id, [...$base, 'fecha_real' => null], [...$base, 'fecha_real' => '2026-11-18']],
            ['expediente', $expediente->id, [...$base, 'fecha_programada' => '2026-11-25'], [...$base, 'fecha_programada' => null]],
        ], $historial);

        // Sin cambios, no hay registro nuevo.
        $this->fechas($expediente, 'planeacion', ['fecha_real' => '2026-11-18'])->assertOk();
        $this->assertSame(3, DB::table('checklist_historial')->where('evento', 'etapa_fechas')->count());

        $this->assertSame('2026-11-18', $this->deEtapa($this->leer($expediente)->json('avance_por_etapa'), 'planeacion')['fecha_real']);
    }

    public function test_valida_las_fechas_y_rechaza_una_fecha_real_futura(): void
    {
        $expediente = $this->expediente();

        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '25/11/2026'])->assertStatus(422)
            ->assertJsonValidationErrors('fecha_programada');
        $this->fechas($expediente, 'planeacion', ['fecha_real' => 20261118])->assertStatus(422)
            ->assertJsonValidationErrors('fecha_real');
        $this->fechas($expediente, 'planeacion', ['fecha_real' => '2026-11-20'])->assertStatus(422)
            ->assertJsonPath('mensaje', 'La fecha real no puede ser posterior a hoy.')
            ->assertJsonPath('errors.fecha_real.0', 'La fecha real no puede ser posterior a hoy.');
        $this->fechas($expediente, 'planeacion', ['fecha_real' => self::HOY])->assertOk();
        // Una fecha programada en el pasado sí se admite.
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-01-01'])->assertOk();

        $this->assertSame(self::HOY, ExpedienteEtapa::sole()->fecha_real->format('Y-m-d'));
    }

    public function test_rechaza_viabilizacion_cerrado_etapa_sin_requisitos_y_lectores(): void
    {
        $expediente = $this->expediente();
        $viabilizacion = $this->viabilizacion();
        $datos = ['fecha_programada' => '2026-11-25'];

        $this->como(Rol::Consulta)->putJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa('planeacion')->id}", $datos)->assertForbidden();
        $this->fechas($viabilizacion, 'planeacion', $datos)->assertStatus(422)
            ->assertExactJson(['mensaje' => 'El expediente no es de contratación.']);
        $this->fechas($expediente, 'adendas', $datos)->assertStatus(422)
            ->assertExactJson(['mensaje' => 'La etapa no tiene requisitos en este expediente.']);
        $this->como()->putJson("/GestPro/expedientes/{$expediente->id}/etapas/99999", $datos)->assertNotFound();

        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertOk();
        $this->fechas($expediente, 'planeacion', $datos)->assertStatus(422)
            ->assertExactJson(['mensaje' => 'El expediente está cerrado.']);

        $this->assertSame(0, ExpedienteEtapa::count());
        $this->assertSame(0, DB::table('checklist_historial')->where('evento', 'etapa_fechas')->count());
    }

    // ------------------------------------------------------------ semáforo (R14, criterio 11)

    public function test_criterio_11_vencida_proxima_al_dia_completa_y_sin_fecha(): void
    {
        $expediente = $this->expediente();
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-18'])->assertOk()
            ->assertJsonPath('etapa.alerta', 'vencida');
        // Faltan 3 días: próxima; 4, al día.
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_programada' => '2026-11-22'])->assertOk()
            ->assertJsonPath('etapa.alerta', 'proxima');
        $this->fechas($expediente, 'cierre', ['fecha_programada' => '2026-11-23'])->assertOk()
            ->assertJsonPath('etapa.alerta', 'al_dia');

        $r = $this->leer($expediente);
        $avance = $r->json('avance_por_etapa');
        $this->assertSame(['vencida', 'proxima', 'al_dia'], array_column($avance, 'alerta'));
        $this->assertSame([
            'etapa', 'total', 'cumplidos', 'porcentaje', 'peso_total', 'peso_cumplido',
            'fecha_programada', 'fecha_real', 'fecha_real_sugerida', 'completa', 'alerta',
        ], array_keys($avance[0]));
        $r->assertJsonPath('avisos.etapas_vencidas', 1)
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
    {
        $expediente = $this->expediente();
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-01'])->assertOk();
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_programada' => '2026-11-20'])->assertOk();
        $this->marcar($expediente, 'Acta de cierre');
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertOk();

        // Las etapas sin completar quedan sin completar, con o sin fecha; la completa, completa.
        $this->travelTo('2026-12-31 08:00:00');
        $r = $this->leer($expediente);
        $this->assertSame(['sin_completar', 'sin_completar', 'completa'], array_column($r->json('avance_por_etapa'), 'alerta'));
        $r->assertJsonPath('avisos.etapas_vencidas', 0)
            ->assertJsonPath('avisos.etapas_proximas', 0)
            ->assertJsonPath('avisos.sin_actualizacion', false);
    }

    // ------------------------------------------------------------ fecha real sugerida

    public function test_al_completarse_una_etapa_se_sugiere_hoy_y_al_dejar_de_estarlo_se_borra(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos')->assertJsonPath('avance_por_etapa.0.fecha_real', null);

        $r = $this->marcar($expediente, 'CDP');
        $this->assertSame([
            'fecha_programada' => null, 'fecha_real' => self::HOY, 'fecha_real_sugerida' => true, 'completa' => true, 'alerta' => 'completa',
        ], array_slice($r->json('avance_por_etapa.0'), 6));
        $this->assertSame(0, $r->json('avisos.etapas_vencidas'));
        $fila = ExpedienteEtapa::sole();
        $this->assertTrue($fila->fecha_real_sugerida);
        $this->assertNull($fila->usuario_actualizo_id);
        $this->assertSame(self::HOY, $this->deEtapa($this->leer($expediente)->json('avance_por_etapa'), 'planeacion')['fecha_real']);

        // Al día siguiente, otra acción no cambia la sugerida.
        $this->travelTo('2026-11-20 09:00:00');
        $this->marcar($expediente, 'Acta de cierre')->assertJsonPath('avance_por_etapa.0.fecha_real', self::HOY);

        $this->marcar($expediente, 'CDP', false)
            ->assertJsonPath('avance_por_etapa.0.fecha_real', null)
            ->assertJsonPath('avance_por_etapa.0.fecha_real_sugerida', false)
            ->assertJsonPath('avance_por_etapa.0.completa', false);
        $this->assertNull(ExpedienteEtapa::where('etapa_id', $this->etapa('planeacion')->id)->sole()->fecha_real);
        // La de cierre se completó y tiene la suya, de hoy.
        $this->assertSame('2026-11-20', ExpedienteEtapa::where('etapa_id', $this->etapa('cierre')->id)->sole()->fecha_real->format('Y-m-d'));
        // La sugerencia no se registra como un cambio de fechas: la acción ya tiene el suyo.
        $this->assertSame(0, DB::table('checklist_historial')->where('evento', 'etapa_fechas')->count());
    }

    public function test_una_fecha_real_puesta_por_una_persona_se_conserva(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');

        // Corregida: deja de ser sugerida y sobrevive a que la etapa deje de estar completa.
        $this->fechas($expediente, 'planeacion', ['fecha_real' => '2026-11-17'])->assertOk()
            ->assertJsonPath('etapa.fecha_real', '2026-11-17')
            ->assertJsonPath('etapa.fecha_real_sugerida', false);
        $this->assertSame([
            'etapa_id' => $this->etapa('planeacion')->id, 'etapa' => $this->etapa('planeacion')->nombre,
            'fecha_real' => self::HOY, 'fecha_real_sugerida' => true,
        ], json_decode((string) DB::table('checklist_historial')->where('evento', 'etapa_fechas')->sole()->antes, true));
        $this->marcar($expediente, 'CDP', false)
            ->assertJsonPath('avance_por_etapa.0.fecha_real', '2026-11-17')
            ->assertJsonPath('avance_por_etapa.0.completa', false);

        // Confirmar la sugerida tal cual también la vuelve de una persona.
        $this->marcar($expediente, 'Pliego de condiciones');
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_real' => self::HOY])->assertOk()
            ->assertJsonPath('etapa.fecha_real_sugerida', false);
        $this->marcar($expediente, 'Pliego de condiciones', false)->assertJsonPath('avance_por_etapa.1.fecha_real', self::HOY);
    }

    public function test_la_sugerencia_sigue_al_no_aplica_y_a_los_propios(): void
    {
        $expediente = $this->expediente();
        $base = "/GestPro/expedientes/{$expediente->id}";
        $this->marcar($expediente, 'Estudios previos');

        $this->como()->postJson("{$base}/requisitos/{$this->fila($expediente, 'CDP')->id}/no-aplica", ['justificacion' => 'Ya se expidió.'])
            ->assertOk()->assertJsonPath('avance_por_etapa.0.fecha_real', self::HOY)->assertJsonPath('avance_por_etapa.0.completa', true);

        $propio = $this->como()->postJson("{$base}/requisitos-propios", ['titulo' => 'Póliza', 'etapa_propia_id' => $this->etapa('planeacion')->id])
            ->assertCreated()->assertJsonPath('avance_por_etapa.0.fecha_real', null)->json('requisito.id');

        $this->como()->deleteJson("{$base}/requisitos-propios/{$propio}")->assertOk()
            ->assertJsonPath('avance_por_etapa.0.fecha_real', self::HOY)
            ->assertJsonPath('avance_por_etapa.0.fecha_real_sugerida', true);
    }

    public function test_la_sugerencia_sigue_a_una_version_nueva(): void
    {
        $plantilla = $this->plantilla();
        $expediente = $this->expediente($plantilla);
        $this->marcar($expediente, 'Estudios previos');

        $borrador = app(CatalogoService::class)->crearBorrador('Sin CDP', $plantilla);
        $cdp = $borrador->requisitos()->where('texto', 'CDP')->sole();
        $admin = $this->como(Rol::Administrador);
        $admin->deleteJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos/{$cdp->id}")->assertOk();
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();

        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/plantillas/{$plantilla->id}/version-nueva", ['version_id' => $borrador->id])
            ->assertOk()
            ->assertJsonPath('avance_por_etapa.0.completa', true)
            ->assertJsonPath('avance_por_etapa.0.fecha_real', self::HOY)
            ->assertJsonPath('avance_por_etapa.0.fecha_real_sugerida', true);
    }

    // ------------------------------------------------------------ avisos del expediente

    public function test_los_avisos_del_expediente_con_cierre_y_sin_actualizacion(): void
    {
        $this->travelTo('2026-11-01 10:00:00');
        $proceso = $this->proceso(['fecha_cierre' => '2026-11-21']);
        $expediente = $this->expediente(null, $proceso);

        // 14 días sin actividad: todavía no; 15: sí.
        $this->travelTo('2026-11-15 23:00:00');
        $this->leer($expediente)->assertJsonPath('avisos', [
            'etapas_vencidas' => 0,
            'etapas_proximas' => 0,
            'cierre_proximo' => false,
            'cierre_vencido' => false,
            'dias_para_cierre' => 6,
            'sin_actualizacion' => false,
            'dias_sin_actualizacion' => 14,
            'hitos' => ['completadas' => 0, 'total' => 3],
        ]);
        $this->travelTo('2026-11-16 05:30:00');
        $this->leer($expediente)->assertJsonPath('avisos.sin_actualizacion', true)->assertJsonPath('avisos.dias_sin_actualizacion', 15);

        // Una acción en el checklist es actividad.
        $this->travelTo(self::HOY . ' 10:00:00');
        $this->marcar($expediente, 'Estudios previos')
            ->assertJsonPath('avisos.sin_actualizacion', false)
            ->assertJsonPath('avisos.dias_sin_actualizacion', 0)
            ->assertJsonPath('avisos.cierre_proximo', true)
            ->assertJsonPath('avisos.dias_para_cierre', 2);

        $this->travelTo('2026-11-22 10:00:00');
        $this->leer($expediente)->assertJsonPath('avisos.cierre_proximo', false)->assertJsonPath('avisos.cierre_vencido', true)
            ->assertJsonPath('avisos.dias_para_cierre', -1);
        // En evaluación, el cierre ya pasó: no avisa.
        $proceso->update(['estado' => ProcesoLicitacion::EVALUACION]);
        $this->leer($expediente)->assertJsonPath('avisos.cierre_vencido', false);
    }

    // ------------------------------------------------------------ zona horaria (M1)

    public function test_hoy_es_el_de_la_zona_de_contratacion_y_no_el_utc(): void
    {
        $this->assertSame('America/Bogota', config('contratacion.zona'));
        $expediente = $this->expediente();
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => self::HOY])->assertOk();

        // Las 20:00 del 19 en Bogotá son la 01:00 del 20 en UTC: la etapa de hoy no está vencida.
        $this->travelTo('2026-11-20 01:00:00');
        $this->assertSame('proxima', $this->deEtapa($this->leer($expediente)->json('avance_por_etapa'), 'planeacion')['alerta']);
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP')
            ->assertJsonPath('avance_por_etapa.0.fecha_real', self::HOY)
            ->assertJsonPath('avance_por_etapa.0.fecha_real_sugerida', true);
        // El 20 todavía no llega en Bogotá.
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_real' => '2026-11-20'])->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_real' => 'La fecha real no puede ser posterior a hoy.']);
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_programada' => self::HOY])->assertOk()->assertJsonPath('etapa.alerta', 'proxima');

        // Con la zona UTC, el mismo instante ya es el 20.
        config(['contratacion.zona' => 'UTC']);
        $this->assertSame('vencida', $this->deEtapa($this->leer($expediente)->json('avance_por_etapa'), 'pliego_definitivo')['alerta']);
    }

    public function test_los_dias_sin_actualizacion_cuentan_en_la_zona_de_contratacion(): void
    {
        // Las 21:00 del 1 de noviembre en Bogotá (02:00 del 2 en UTC): la actividad es del 1.
        $this->travelTo('2026-11-02 02:00:00');
        $expediente = $this->expediente();

        $this->travelTo('2026-11-16 12:00:00');
        $this->leer($expediente)
            ->assertJsonPath('avisos.dias_sin_actualizacion', 15)
            ->assertJsonPath('avisos.sin_actualizacion', true);
    }

    // ------------------------------------------------------------ sugerencia solo al completarse (M2)

    public function test_una_fecha_real_vaciada_por_una_persona_no_se_vuelve_a_sugerir_mientras_siga_completa(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP')->assertJsonPath('avance_por_etapa.0.fecha_real', self::HOY);

        $this->fechas($expediente, 'planeacion', ['fecha_real' => null])->assertOk()
            ->assertJsonPath('etapa.fecha_real', null)
            ->assertJsonPath('etapa.fecha_real_sugerida', false)
            ->assertJsonPath('etapa.completa', true);

        // Otras acciones no la vuelven a sugerir, ni cambiar solo la programada.
        $this->marcar($expediente, 'Acta de cierre')->assertJsonPath('avance_por_etapa.0.fecha_real', null);
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-30'])->assertOk()->assertJsonPath('etapa.fecha_real', null);
        $this->marcar($expediente, 'Estudios previos')->assertJsonPath('avance_por_etapa.0.fecha_real', null);
        $planeacion = $this->deEtapa($this->leer($expediente)->json('avance_por_etapa'), 'planeacion');
        $this->assertSame([null, false], [$planeacion['fecha_real'], $planeacion['fecha_real_sugerida']]);

        // Deja de estar completa y se vuelve a completar: se sugiere otra vez.
        $this->marcar($expediente, 'CDP', false)->assertJsonPath('avance_por_etapa.0.fecha_real', null);
        $this->travelTo('2026-11-21 15:00:00');
        $this->marcar($expediente, 'CDP')
            ->assertJsonPath('avance_por_etapa.0.fecha_real', '2026-11-21')
            ->assertJsonPath('avance_por_etapa.0.fecha_real_sugerida', true);
    }

    public function test_una_etapa_con_fila_previa_recibe_la_sugerida_al_completarse(): void
    {
        $expediente = $this->expediente();
        // La fila nace con la fecha programada, y una persona vacía la real mientras no está completa.
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_programada' => '2026-11-25', 'fecha_real' => null])->assertOk();

        $this->marcar($expediente, 'Pliego de condiciones')
            ->assertJsonPath('avance_por_etapa.1.fecha_real', self::HOY)
            ->assertJsonPath('avance_por_etapa.1.fecha_real_sugerida', true)
            ->assertJsonPath('avance_por_etapa.1.fecha_programada', '2026-11-25');
    }

    // ------------------------------------------------------------ estados finales (B1)

    public function test_un_proceso_en_estado_final_no_cuenta_vencidas_ni_proximas(): void
    {
        $proceso = $this->proceso();
        $expediente = $this->expediente(null, $proceso);
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-18'])->assertOk();
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_programada' => '2026-11-20'])->assertOk()
            ->assertJsonPath('avisos.etapas_vencidas', 1)->assertJsonPath('avisos.etapas_proximas', 1);

        foreach ([ProcesoLicitacion::CELEBRADO, ProcesoLicitacion::DESIERTO, ProcesoLicitacion::CANCELADO, ProcesoLicitacion::REVOCADO] as $estado) {
            $proceso->update(['estado' => $estado]);
            $this->leer($expediente)
                ->assertJsonPath('avisos.etapas_vencidas', 0)
                ->assertJsonPath('avisos.etapas_proximas', 0);
        }
    }

    // ------------------------------------------------------------ caminos sin avanceDe (B4)

    public function test_sincronizar_tambien_ajusta_la_fecha_sugerida(): void
    {
        $expediente = $this->expediente();
        // Cumplidos sin pasar por una acción del checklist: solo sincronizar los ve.
        $expediente->requisitos()->whereHas('requisito', fn ($q) => $q->where('etapa_id', $this->etapa('planeacion')->id))
            ->update(['cumplido' => true]);

        app(ExpedienteService::class)->sincronizar($expediente->fresh());

        $fila = ExpedienteEtapa::where('expediente_id', $expediente->id)->sole();
        $this->assertSame(
            [$this->etapa('planeacion')->id, self::HOY, true],
            [$fila->etapa_id, $fila->fecha_real->format('Y-m-d'), $fila->fecha_real_sugerida]
        );
    }

    public function test_la_vista_previa_de_una_version_nueva_no_sugiere_y_aplicarla_si(): void
    {
        $plantilla = $this->plantilla();
        $expediente = $this->expediente($plantilla);
        $this->marcar($expediente, 'Estudios previos');
        $borrador = app(CatalogoService::class)->crearBorrador('Sin CDP', $plantilla);
        $admin = $this->como(Rol::Administrador);
        $admin->deleteJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos/{$borrador->requisitos()->where('texto', 'CDP')->sole()->id}")->assertOk();
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();
        $ruta = "/GestPro/expedientes/{$expediente->id}/plantillas/{$plantilla->id}/version-nueva";

        $this->como()->getJson($ruta)->assertOk();
        $this->assertSame(0, ExpedienteEtapa::count());

        $this->como()->postJson($ruta, ['version_id' => $borrador->id])->assertOk();
        $this->assertTrue(ExpedienteEtapa::sole()->sugerida());
    }

    // ------------------------------------------------------------ fallos al ajustar la sugerida (B5)

    /** Hace fallar, una vez, la primera consulta que cumpla `$cumple` mientras `activo` sea true. */
    private function fallarUnaVez(\Closure $cumple): \stdClass
    {
        $estado = new \stdClass();
        $estado->activo = false;
        $estado->fallo = false;
        DB::connection()->beforeExecuting(function (string $sql) use ($estado, $cumple) {
            if ($estado->activo && ! $estado->fallo && $cumple($sql)) {
                $estado->fallo = true;
                throw new \RuntimeException('SQLSTATE[40001]: Deadlock found when trying to get lock');
            }
        });

        return $estado;
    }

    public function test_un_interbloqueo_al_bloquear_el_expediente_no_hace_fallar_la_accion(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos');
        $cdp = $this->fila($expediente, 'CDP');
        // La acción ya quedó guardada; falla el bloqueo del expediente, la primera consulta del
        // ajuste de la sugerida que acompaña su respuesta.
        $cdp->update(['cumplido' => true]);
        $estado = $this->fallarUnaVez(fn (string $sql) => str_starts_with($sql, 'select') && str_contains($sql, '"proyectos_expedientes"'));
        $fresco = $expediente->fresh();
        $estado->activo = true;

        $avance = app(ExpedienteService::class)->avanceDe($fresco);

        $this->assertTrue($estado->fallo);
        $this->assertSame([true, null], [$avance['avance_por_etapa'][0]['completa'], $avance['avance_por_etapa'][0]['fecha_real']]);
        $this->assertSame(0, ExpedienteEtapa::count());
    }

    public function test_un_fallo_al_guardar_la_sugerida_no_hace_fallar_la_accion(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos');
        $estado = $this->fallarUnaVez(fn (string $sql) => str_starts_with($sql, 'insert into "expediente_etapas"'));
        $estado->activo = true;

        $this->marcar($expediente, 'CDP')
            ->assertJsonPath('avance_por_etapa.0.completa', true)
            ->assertJsonPath('avance_por_etapa.0.fecha_real', null);
        $this->assertTrue($estado->fallo);
        $this->assertTrue($this->fila($expediente, 'CDP')->cumplido);
        $this->assertSame(0, ExpedienteEtapa::count());
    }

    public function test_la_viabilizacion_no_cambia(): void
    {
        $viabilizacion = $this->viabilizacion();
        $fila = $viabilizacion->requisitos()->where('aplica', true)->orderBy('orden')->firstOrFail();

        $r = $this->leer($viabilizacion);
        $this->assertArrayNotHasKey('avisos', $r->json());
        $this->assertArrayNotHasKey('avance_por_etapa', $r->json());
        $parcial = $this->como()->patchJson("/GestPro/expedientes/{$viabilizacion->id}/requisitos/{$fila->id}", ['cumplido' => true])->assertOk();
        $this->assertSame(['requisito', 'progreso'], array_keys($parcial->json()));
        $this->assertSame(0, ExpedienteEtapa::count());
    }
}
```

Crear `tests/Feature/Schema/ExpedienteEtapasTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\EtapaContratacion;
use App\Models\ExpedienteEtapa;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Migración 22: cronograma de las etapas de un expediente de contratación (expediente_etapas),
 * con la fecha programada y la real de cada etapa.
 */
class ExpedienteEtapasTest extends TestCase
{
    use RefreshDatabase;

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_19_100021_create_expediente_etapas_table.php');
    }

    private function expediente(): ProyectoExpediente
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);

        return ProyectoExpediente::create(['proyecto_id' => $proyecto->id, 'estado' => ProyectoExpediente::BORRADOR]);
    }

    private function etapa(string $clave = 'planeacion'): EtapaContratacion
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail();
    }

    public function test_la_tabla_tiene_las_columnas_del_cronograma(): void
    {
        $columnas = collect(Schema::getColumns('expediente_etapas'))->keyBy('name');

        $this->assertSame(
            ['id', 'expediente_id', 'etapa_id', 'fecha_programada', 'fecha_real', 'fecha_real_sugerida', 'usuario_actualizo_id', 'created_at', 'updated_at'],
            $columnas->keys()->all()
        );
        $this->assertTrue($columnas['fecha_programada']['nullable']);
        $this->assertTrue($columnas['fecha_real']['nullable']);
        $this->assertTrue($columnas['usuario_actualizo_id']['nullable']);
        $this->assertFalse($columnas['fecha_real_sugerida']['nullable']);
        $this->assertNotNull(collect(Schema::getIndexes('expediente_etapas'))
            ->first(fn ($i) => $i['unique'] && $i['columns'] === ['expediente_id', 'etapa_id']));
    }

    public function test_una_fila_por_expediente_y_etapa_y_cae_con_el_expediente(): void
    {
        $expediente = $this->expediente();
        $fila = ExpedienteEtapa::create(['expediente_id' => $expediente->id, 'etapa_id' => $this->etapa()->id, 'fecha_programada' => '2026-11-20']);

        $this->assertFalse($fila->fresh()->fecha_real_sugerida);
        $this->assertSame('2026-11-20', $fila->fresh()->fecha_programada->format('Y-m-d'));
        $this->assertSame([$fila->id], $expediente->etapasCronograma()->pluck('id')->all());
        $this->assertSame($expediente->id, $fila->expediente->id);
        $this->assertSame('planeacion', $fila->etapa->clave);

        try {
            ExpedienteEtapa::create(['expediente_id' => $expediente->id, 'etapa_id' => $this->etapa()->id]);
            $this->fail('La etapa no debía repetirse en el expediente.');
        } catch (QueryException) {
        }

        $expediente->delete();
        $this->assertSame(0, DB::table('expediente_etapas')->count());
    }

    public function test_una_etapa_con_cronograma_no_se_elimina(): void
    {
        $etapa = $this->etapa('adendas');
        ExpedienteEtapa::create(['expediente_id' => $this->expediente()->id, 'etapa_id' => $etapa->id]);

        $this->expectException(QueryException::class);
        $etapa->delete();
    }

    public function test_el_rollback_se_detiene_si_hay_registros_y_ambos_sentidos_se_repiten(): void
    {
        $migracion = $this->migracion();
        $migracion->up(); // ya migrada: no falla
        $fila = ExpedienteEtapa::create(['expediente_id' => $this->expediente()->id, 'etapa_id' => $this->etapa()->id]);

        try {
            $migracion->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }
        $this->assertTrue(Schema::hasTable('expediente_etapas'));

        $fila->delete();
        $migracion->down();
        $migracion->down();
        $this->assertFalse(Schema::hasTable('expediente_etapas'));
        $migracion->up();
        $migracion->up();
        $this->assertTrue(Schema::hasTable('expediente_etapas'));
    }
}
```

En `tests/Feature/Contratacion/AvancePonderadoApiTest.php`, reemplazar (1 de 2):

```php

        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$r->json('requisito.id')}", ['cumplido' => true])->assertOk()
            ->assertJsonPath('progreso', $this->progreso(2, 1, 25, 4, 1))
            ->assertJsonPath('avance_por_etapa.1', ['etapa' => $this->etapaJson('adjudicacion'), ...$this->progreso(1, 1, 100, 1, 1)]);
    }

    public function test_el_avance_por_etapa_tambien_se_pondera(): void
```

por:

```php

        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$r->json('requisito.id')}", ['cumplido' => true])->assertOk()
            ->assertJsonPath('progreso', $this->progreso(2, 1, 25, 4, 1))
            ->assertJsonPath('avance_por_etapa.1', ['etapa' => $this->etapaJson('adjudicacion'), ...$this->progreso(1, 1, 100, 1, 1), 'fecha_programada' => null, 'fecha_real' => now()->toDateString(), 'fecha_real_sugerida' => true, 'completa' => true, 'alerta' => 'completa']);
    }

    public function test_el_avance_por_etapa_tambien_se_pondera(): void
```

En `tests/Feature/Contratacion/AvancePonderadoApiTest.php`, reemplazar (2 de 2):

```php
            ['etapa' => $this->etapaJson('pliego_definitivo'), ...$this->progreso(1, 0, 0, 1, 0)],
            ['etapa' => $this->etapaJson('cierre'), ...$this->progreso(1, 0, 0, 1, 0)],
        ];
        $this->assertSame($avance, $this->cumplir($expediente, 'Estudios previos')->json('avance_por_etapa'));
        $this->assertSame($avance, $this->leer($expediente)->json('avance_por_etapa'));
        $this->assertSame($avance, app(ExpedienteService::class)->avancePorEtapaDe($expediente));
    }
```

por:

```php
            ['etapa' => $this->etapaJson('pliego_definitivo'), ...$this->progreso(1, 0, 0, 1, 0)],
            ['etapa' => $this->etapaJson('cierre'), ...$this->progreso(1, 0, 0, 1, 0)],
        ];
        // La API agrega a cada etapa sus fechas y su semáforo.
        $conFechas = array_map(fn (array $a) => [...$a, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha'], $avance);
        $this->assertSame($conFechas, $this->cumplir($expediente, 'Estudios previos')->json('avance_por_etapa'));
        $this->assertSame($conFechas, $this->leer($expediente)->json('avance_por_etapa'));
        $this->assertSame($avance, app(ExpedienteService::class)->avancePorEtapaDe($expediente));
    }
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (1 de 5):

```php
        $this->assertSame([
            'id', 'proyecto', 'plantillas', 'tiene_sgr', 'configurado', 'estado', 'usuario_creo', 'creado', 'actualizado',
            'tipo', 'proceso', 'fecha_cierre', 'usuario_cierre',
            'progreso', 'avance_por_etapa', 'requisitos', 'avisos_duplicados', 'avisos_duplicados_total', 'retenidos',
        ], array_keys($r->json()));
        $r->assertJsonPath('tipo', ProyectoExpediente::TIPO_CONTRATACION)
            ->assertJsonPath('proceso', [
```

por:

```php
        $this->assertSame([
            'id', 'proyecto', 'plantillas', 'tiene_sgr', 'configurado', 'estado', 'usuario_creo', 'creado', 'actualizado',
            'tipo', 'proceso', 'fecha_cierre', 'usuario_cierre',
            'progreso', 'avance_por_etapa', 'avisos', 'requisitos', 'avisos_duplicados', 'avisos_duplicados_total', 'retenidos',
        ], array_keys($r->json()));
        $r->assertJsonPath('tipo', ProyectoExpediente::TIPO_CONTRATACION)
            ->assertJsonPath('proceso', [
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (2 de 5):

```php
        ], $etapas);
        // En el orden del catálogo de etapas, no en el de los requisitos.
        $this->assertSame([
            ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0],
            ['etapa' => $this->etapaJson('pliego_definitivo'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0],
        ], $r->json('avance_por_etapa'));
    }
```

por:

```php
        ], $etapas);
        // En el orden del catálogo de etapas, no en el de los requisitos.
        $this->assertSame([
            ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha'],
            ['etapa' => $this->etapaJson('pliego_definitivo'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha'],
        ], $r->json('avance_por_etapa'));
    }
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (3 de 5):

```php
        $r = $this->leer($expediente);

        $this->assertSame([
            ['etapa' => $this->etapaJson('planeacion'), 'total' => 2, 'cumplidos' => 1, 'porcentaje' => 50, 'peso_total' => 2, 'peso_cumplido' => 1],
            ['etapa' => $this->etapaJson('pliego_definitivo'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0],
            ['etapa' => null, 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0],
        ], $r->json('avance_por_etapa'));
        $r->assertJsonPath('progreso', ['total' => 4, 'cumplidos' => 1, 'porcentaje' => 25, 'peso_total' => 4, 'peso_cumplido' => 1]);
        $this->assertSame(
```

por:

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

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (4 de 5):

```php
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'))
            ->assertJsonPath('requisito.etapa', $this->etapaJson('planeacion'))
            ->assertJsonPath('progreso', ['total' => 2, 'cumplidos' => 1, 'porcentaje' => 50, 'peso_total' => 2, 'peso_cumplido' => 1])
            ->assertJsonPath('avance_por_etapa.0', ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 1, 'porcentaje' => 100, 'peso_total' => 1, 'peso_cumplido' => 1]);
    }

    public function test_la_api_de_viabilizacion_no_trae_las_claves_de_contratacion(): void
```

por:

```php
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'))
            ->assertJsonPath('requisito.etapa', $this->etapaJson('planeacion'))
            ->assertJsonPath('progreso', ['total' => 2, 'cumplidos' => 1, 'porcentaje' => 50, 'peso_total' => 2, 'peso_cumplido' => 1])
            ->assertJsonPath('avance_por_etapa.0', ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 1, 'porcentaje' => 100, 'peso_total' => 1, 'peso_cumplido' => 1, 'fecha_programada' => null, 'fecha_real' => now()->toDateString(), 'fecha_real_sugerida' => true, 'completa' => true, 'alerta' => 'completa']);
    }

    public function test_la_api_de_viabilizacion_no_trae_las_claves_de_contratacion(): void
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (5 de 5):

```php
        $this->como()->postJson($url, ['titulo' => 'Póliza', 'etapa_propia_id' => $this->etapa('adjudicacion')->id])
            ->assertCreated()
            ->assertJsonPath('requisito.etapa', $this->etapaJson('adjudicacion'))
            ->assertJsonPath('avance_por_etapa.2', ['etapa' => $this->etapaJson('adjudicacion'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0]);

        $propio = $expediente->requisitos()->whereNull('requisito_catalogo_id')->sole();
        $this->assertSame($this->etapa('adjudicacion')->id, $propio->etapa_propia_id);
```

por:

```php
        $this->como()->postJson($url, ['titulo' => 'Póliza', 'etapa_propia_id' => $this->etapa('adjudicacion')->id])
            ->assertCreated()
            ->assertJsonPath('requisito.etapa', $this->etapaJson('adjudicacion'))
            ->assertJsonPath('avance_por_etapa.2', ['etapa' => $this->etapaJson('adjudicacion'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha']);

        $propio = $expediente->requisitos()->whereNull('requisito_catalogo_id')->sole();
        $this->assertSame($this->etapa('adjudicacion')->id, $propio->etapa_propia_id);
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/CronogramaEtapasApiTest.php tests/Feature/Schema/ExpedienteEtapasTest.php tests/Feature/Contratacion/AvancePonderadoApiTest.php tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`
Expected: FALLAN 30 y pasan 31: en `CronogramaEtapasApiTest` fallan las 20, porque la ruta `PUT /expedientes/{expediente}/etapas/{etapa}` no existe ("Failed asserting that 405 is identical to 200.") ni hay fecha real sugerida, semáforo ni avisos; en `ExpedienteEtapasTest` fallan las 4, porque no existe la tabla `expediente_etapas`; en `AvancePonderadoApiTest` fallan 2 y en `ExpedienteContratacionApiTest` 4, los que esperan en `avance_por_etapa` las fechas y el semáforo (`fecha_programada`, `fecha_real`, `fecha_real_sugerida`, `completa`, `alerta`) y la clave `avisos` del expediente.

- [ ] **Paso 3: Implementar**

Crear `app/Http/Controllers/CronogramaExpedienteController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\EtapaContratacion;
use App\Models\ProyectoExpediente;
use App\Services\Contratacion\CronogramaExpediente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Fechas programada y real de una etapa de un expediente de contratación. Ruta con can:editar-datos. */
class CronogramaExpedienteController extends Controller
{
    private const MENSAJES = [
        'string' => 'Escribe :attribute como año-mes-día.',
        'date_format' => 'Escribe :attribute como año-mes-día.',
    ];

    private const ATRIBUTOS = [
        'fecha_programada' => 'la fecha programada',
        'fecha_real' => 'la fecha real',
    ];

    public function __construct(private readonly CronogramaExpediente $cronograma)
    {
    }

    /**
     * Cada fecha es opcional: la que no llega no cambia y con null se borra. Responde la etapa
     * como en avance_por_etapa y los avisos del expediente.
     */
    public function update(Request $request, ProyectoExpediente $expediente, EtapaContratacion $etapa): JsonResponse
    {
        $datos = $request->validate([
            'fecha_programada' => ['sometimes', 'nullable', 'bail', 'string', 'date_format:Y-m-d'],
            'fecha_real' => ['sometimes', 'nullable', 'bail', 'string', 'date_format:Y-m-d'],
        ], self::MENSAJES, self::ATRIBUTOS);

        return response()->json($this->cronograma->guardar($expediente, $etapa, $datos, $request->user()));
    }
}
```

Crear `app/Models/ExpedienteEtapa.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cronograma de una etapa en un expediente de contratación: su fecha programada y su fecha real.
 * La fila nace al guardar la primera fecha de la etapa o al sugerir la real.
 *
 * `fecha_real_sugerida` dice que el sistema ya atendió la fecha real de la vez actual en que la
 * etapa está completa:
 * - con fecha real: la puso el sistema al completarse la etapa y nadie la ha tocado (sugerida());
 * - sin fecha real: una persona la vació estando la etapa completa (descartada()), y no se vuelve a
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
        'fecha_programada' => 'date',
        'fecha_real' => 'date',
        'fecha_real_sugerida' => 'boolean',
        'usuario_actualizo_id' => 'integer',
    ];

    /** La fecha real la puso el sistema y nadie la ha tocado. */
    public function sugerida(): bool
    {
        return $this->fecha_real_sugerida && $this->fecha_real !== null;
    }

    /** Una persona vació la fecha real estando la etapa completa: no se vuelve a sugerir mientras lo siga. */
    public function descartada(): bool
    {
        return $this->fecha_real_sugerida && $this->fecha_real === null;
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(ProyectoExpediente::class, 'expediente_id');
    }

    public function etapa(): BelongsTo
    {
        return $this->belongsTo(EtapaContratacion::class, 'etapa_id');
    }
}
```

Crear `app/Services/Contratacion/AvisosContratacion.php`:

```php
<?php

namespace App\Services\Contratacion;

use App\Models\ProcesoLicitacion;
use App\Models\ProyectoExpediente;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Avisos de contratación (R14): el semáforo de cada etapa de un expediente y el resumen de un
 * proceso. Solo lee: no guarda nada.
 *
 * Reglas:
 * - Días calendario en la zona `contratacion.zona` (America/Bogota por defecto), no en la de la
 *   aplicación (UTC): "hoy" es now() en esa zona, y los momentos (updated_at, el último evento del
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
 */
final class AvisosContratacion
{
    public const VENCIDA = 'vencida';
    public const PROXIMA = 'proxima';
    public const AL_DIA = 'al_dia';
    public const COMPLETA = 'completa';
    public const SIN_FECHA = 'sin_fecha';
    /** Etapa que no se completó en un expediente ya cerrado. */
    public const SIN_COMPLETAR = 'sin_completar';

    /** Estados en los que el proceso ya terminó: no avisa etapas vencidas ni próximas ni que está sin actualización. */
    public const ESTADOS_FINALES = [
        ProcesoLicitacion::CELEBRADO, ProcesoLicitacion::DESIERTO, ProcesoLicitacion::CANCELADO, ProcesoLicitacion::REVOCADO,
    ];

    /** Estados en los que el cierre del proceso todavía no ha ocurrido. */
    public const ANTES_DEL_CIERRE = [ProcesoLicitacion::PREPARACION, ProcesoLicitacion::PUBLICADO];

    /** Zona horaria de los días de contratación. */
    public function zona(): string
    {
        return (string) config('contratacion.zona', 'America/Bogota');
    }

    /** El comienzo de hoy en la zona de contratación. */
    public function hoy(): Carbon
    {
        return Carbon::now($this->zona())->startOfDay();
    }

    /** La fecha (año-mes-día) de un momento en la zona de contratación. */
    public function fechaDe(CarbonInterface $momento): string
    {
        return Carbon::instance($momento)->setTimezone($this->zona())->toDateString();
    }

    public function diasProxima(): int
    {
        return (int) config('contratacion.dias_proxima', 3);
    }

    public function diasSinActualizacion(): int
    {
        return (int) config('contratacion.dias_sin_actualizacion', 15);
    }

    /** @param array{total: int, cumplidos: int} $avance el de una etapa en avance_por_etapa */
    public function completa(array $avance): bool
    {
        return $avance['total'] > 0 && $avance['cumplidos'] === $avance['total'];
    }

    /**
     * Semáforo de una etapa.
     *
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
        $hoy = $this->hoy();
        $etapas = array_values(array_filter($avancePorEtapa, fn (array $a) => $a['etapa'] !== null));
        $alertas = array_count_values(array_column($etapas, 'alerta'));

        $diasCierre = $proceso->fecha_cierre ? $this->dias($hoy, $proceso->fecha_cierre) : null;
        $antesDelCierre = in_array($proceso->estado, self::ANTES_DEL_CIERRE, true);

        $ultima = collect([$proceso->updated_at, $ultimoEvento])->filter()->max();
        $diasSin = $ultima ? max(0, $this->dias(Carbon::parse($this->fechaDe($ultima)), $hoy)) : null;
        $terminado = in_array($proceso->estado, self::ESTADOS_FINALES, true);
        $vigente = ! $terminado && $expediente?->estado !== ProyectoExpediente::CERRADO;

        return [
            'etapas_vencidas' => $terminado ? 0 : $alertas[self::VENCIDA] ?? 0,
            'etapas_proximas' => $terminado ? 0 : $alertas[self::PROXIMA] ?? 0,
            'cierre_proximo' => $antesDelCierre && $diasCierre !== null && $diasCierre >= 0 && $diasCierre <= $this->diasProxima(),
            'cierre_vencido' => $antesDelCierre && $diasCierre !== null && $diasCierre < 0,
            'dias_para_cierre' => $diasCierre,
            'sin_actualizacion' => $vigente && $diasSin !== null && $diasSin >= $this->diasSinActualizacion(),
            'dias_sin_actualizacion' => $diasSin,
            'hitos' => [
                'completadas' => count(array_filter($etapas, fn (array $a) => $a['completa'])),
                'total' => count(array_filter($etapas, fn (array $a) => $a['total'] > 0)),
            ],
        ];
    }

    /**
     * El último evento del historial de cada expediente, en una sola consulta.
     *
     * @param iterable<int> $expedienteIds
     * @return array<int, Carbon> expediente_id => fecha; sin eventos, no aparece
     */
    public function ultimosEventos(iterable $expedienteIds): array
    {
        $ids = collect($expedienteIds)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('checklist_historial')
            ->whereIn('expediente_id', $ids)
            ->groupBy('expediente_id')
            ->get(['expediente_id', DB::raw('MAX(created_at) AS ultimo')])
            ->filter(fn (object $fila) => $fila->ultimo !== null)
            ->mapWithKeys(fn (object $fila) => [(int) $fila->expediente_id => Carbon::parse($fila->ultimo)])
            ->all();
    }

    /** Días calendario de `$desde` a `$hasta`: negativos si `$hasta` es anterior. */
    private function dias(CarbonInterface $desde, CarbonInterface $hasta): int
    {
        return (int) round(Carbon::parse($desde->toDateString())->diffInDays(Carbon::parse($hasta->toDateString()), false));
    }
}
```

Crear `app/Services/Contratacion/CronogramaExpediente.php`:

```php
<?php

namespace App\Services\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\EtapaContratacion;
use App\Models\ExpedienteEtapa;
use App\Models\ProyectoExpediente;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\HistorialChecklist;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Fechas programada y real de una etapa de un expediente de contratación, puestas por una persona.
 *
 * Reglas:
 * - Solo en un expediente de contratación en borrador, y en una etapa que tiene requisitos en él
 *   (una de su avance por etapa).
 * - Lo que no llega en el pedido no cambia; null borra la fecha. La fecha real no puede ser
 *   posterior a hoy; la programada puede ser cualquiera.
 * - Poner la fecha real a mano, aunque sea la misma que sugirió el sistema, la deja como de una
 *   persona (`fecha_real_sugerida` = false): ya no se borra si la etapa deja de estar completa.
 * - Vaciarla con la etapa completa la deja descartada (ExpedienteEtapa::descartada()): no se
 *   vuelve a sugerir hasta que la etapa deje de estar completa y se complete otra vez. La
 *   sugerencia nace solo cuando una etapa pasa a estar completa.
 * - La fila de la etapa nace con la primera fecha. Lo que cambia queda en el historial del
 *   expediente (`etapa_fechas`, con la etapa y solo los campos que cambiaron).
 * - Sobre el expediente bloqueado, como las acciones del checklist.
 */
final class CronogramaExpediente
{
    public const NO_ES_CONTRATACION = 'El expediente no es de contratación.';
    public const SIN_REQUISITOS = 'La etapa no tiene requisitos en este expediente.';
    public const REAL_FUTURA = 'La fecha real no puede ser posterior a hoy.';

    public function __construct(
        private readonly ExpedienteService $expedientes,
        private readonly HistorialChecklist $historial,
        private readonly AvisosContratacion $avisos,
    ) {
    }

    /**
     * @param array{fecha_programada?: ?string, fecha_real?: ?string} $datos fechas año-mes-día
     * @return array{etapa: array, avisos: array} la etapa como en avance_por_etapa y los avisos del expediente
     */
    public function guardar(ProyectoExpediente $expediente, EtapaContratacion $etapa, array $datos, ?User $usuario): array
    {
        if (! $expediente->esContratacion()) {
            throw new ReglaNegocioException(self::NO_ES_CONTRATACION);
        }
        $real = $datos['fecha_real'] ?? null;
        if ($real !== null && $real > $this->avisos->hoy()->toDateString()) {
            throw new ReglaNegocioException(self::REAL_FUTURA, [self::REAL_FUTURA], ['fecha_real' => [self::REAL_FUTURA]]);
        }

        DB::transaction(function () use ($expediente, $etapa, $datos, $usuario) {
            $this->expedientes->bloquear($expediente);
            $this->expedientes->exigirBorrador($expediente);
            $avance = collect($this->expedientes->avancePorEtapaDe($expediente))
                ->first(fn (array $a) => ($a['etapa']['id'] ?? null) === $etapa->id);
            if ($avance === null) {
                throw new ReglaNegocioException(self::SIN_REQUISITOS);
            }

            $fila = ExpedienteEtapa::query()
                ->where('expediente_id', $expediente->id)
                ->where('etapa_id', $etapa->id)
                ->first() ?? new ExpedienteEtapa(['expediente_id' => $expediente->id, 'etapa_id' => $etapa->id]);
            $antes = $this->fechas($fila);
            if (array_key_exists('fecha_programada', $datos)) {
                $fila->fecha_programada = $datos['fecha_programada'];
            }
            if (array_key_exists('fecha_real', $datos)) {
                $fila->fecha_real = $datos['fecha_real'];
                $fila->fecha_real_sugerida = $datos['fecha_real'] === null && $this->avisos->completa($avance);
            }
            $despues = $this->fechas($fila);
            $cambios = array_filter($despues, fn ($valor, string $campo) => $valor !== $antes[$campo], ARRAY_FILTER_USE_BOTH);
            if ($cambios === []) {
                return;
            }

            $fila->usuario_actualizo_id = $usuario?->id;
            $fila->save();
            $cual = ['etapa_id' => $etapa->id, 'etapa' => $etapa->nombre];
            $this->historial->registrarExpediente($expediente, 'etapa_fechas', [...$cual, ...array_intersect_key($antes, $cambios)], [...$cual, ...$cambios], $usuario);
            $expediente->touch();
        });

        $cronograma = $this->expedientes->cronogramaDeContratacion($expediente);

        return [
            'etapa' => collect($cronograma['avance_por_etapa'])->first(fn (array $a) => ($a['etapa']['id'] ?? null) === $etapa->id),
            'avisos' => $cronograma['avisos'],
        ];
    }

    /** @return array{fecha_programada: ?string, fecha_real: ?string, fecha_real_sugerida: bool} */
    private function fechas(ExpedienteEtapa $fila): array
    {
        $fecha = fn (?DateTimeInterface $valor) => $valor?->format('Y-m-d');

        return [
            'fecha_programada' => $fecha($fila->fecha_programada),
            'fecha_real' => $fecha($fila->fecha_real),
            'fecha_real_sugerida' => $fila->sugerida(),
        ];
    }
}
```

Crear `config/contratacion.php`:

```php
<?php

/*
 * Avisos de contratación (AvisosContratacion). Los días son calendario, en la zona `zona` (la
 * aplicación guarda y responde en UTC); "hoy" es now() en esa zona.
 */
return [

    // Zona horaria de los días de contratación: "hoy", los días sin actualización, la fecha real
    // sugerida y el tope de la fecha real.
    'zona' => env('CONTRATACION_ZONA', 'America/Bogota'),

    // Una etapa o un cierre está próximo si faltan estos días o menos.
    'dias_proxima' => (int) env('CONTRATACION_DIAS_PROXIMA', 3),

    // Un proceso está sin actualización si pasaron estos días o más desde su última actividad.
    'dias_sin_actualizacion' => (int) env('CONTRATACION_DIAS_SIN_ACTUALIZACION', 15),

];
```

Crear `database/migrations/2026_11_19_100021_create_expediente_etapas_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Cronograma de un expediente de contratación: por cada etapa, la fecha programada y la fecha
 * real. `fecha_real_sugerida` es verdadero cuando la fecha real la puso el sistema al completarse
 * la etapa y nadie la ha tocado, o, sin fecha real, cuando una persona la vació con la etapa
 * completa (ExpedienteEtapa::descartada()). Una fila por expediente y etapa; cae con el expediente, una
 * etapa con cronograma no se elimina (las etapas se desactivan) y el usuario que la actualizó
 * queda en null si se elimina. Nace vacía.
 */
return new class extends Migration
{
    private const TABLA = 'expediente_etapas';
    private const UNICO = 'expediente_etapas_expediente_etapa_unique';

    /** Columna => [tabla, acción al eliminar]. */
    private const FORANEAS = [
        'expediente_id' => ['proyectos_expedientes', 'cascade'],
        'etapa_id' => ['etapas_contratacion', 'restrict'],
        'usuario_actualizo_id' => ['users', 'set null'],
    ];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional y Laravel agrega el índice y las claves foráneas
        // con sentencias aparte: cada paso lleva su guarda, para poder reintentar sin dejar nada
        // a medias. El índice único va antes de las claves foráneas, que así lo usan.
        if (! Schema::hasTable(self::TABLA)) {
            Schema::create(self::TABLA, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('expediente_id');
                $table->unsignedBigInteger('etapa_id');
                $table->date('fecha_programada')->nullable();
                $table->date('fecha_real')->nullable();
                $table->boolean('fecha_real_sugerida')->default(false);
                $table->unsignedBigInteger('usuario_actualizo_id')->nullable();
                $table->timestamps();
            });
        }
        if (! $this->unico()) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->unique(['expediente_id', 'etapa_id'], self::UNICO);
            });
        }
        foreach (self::FORANEAS as $columna => [$tabla, $accion]) {
            if (! $this->foranea($columna)) {
                Schema::table(self::TABLA, function (Blueprint $table) use ($columna, $tabla, $accion) {
                    $table->foreign($columna)->references('id')->on($tabla)->onDelete($accion);
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLA)) {
            return;
        }
        if (DB::table(self::TABLA)->exists()) {
            throw new RuntimeException('No se puede revertir: hay fechas en el cronograma de los expedientes de contratación, que se perderían. Restaure un respaldo.');
        }

        // La tabla cae con su índice y sus claves foráneas.
        Schema::drop(self::TABLA);
    }

    private function unico(): ?array
    {
        return collect(Schema::getIndexes(self::TABLA))
            ->first(fn ($i) => $i['unique'] && ! $i['primary'] && $i['columns'] === ['expediente_id', 'etapa_id']);
    }

    private function foranea(string $columna): ?array
    {
        return collect(Schema::getForeignKeys(self::TABLA))->first(fn ($f) => $f['columns'] === [$columna]);
    }
};
```

En `app/Models/ProyectoExpediente.php`, reemplazar (1 de 1):

```php
        return $this->hasMany(ProyectoRequisito::class, 'expediente_id');
    }

    public function usuarioRadico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_radicado_id');
```

por:

```php
        return $this->hasMany(ProyectoRequisito::class, 'expediente_id');
    }

    /** Fechas programada y real de sus etapas; solo en los de contratación. */
    public function etapasCronograma(): HasMany
    {
        return $this->hasMany(ExpedienteEtapa::class, 'expediente_id');
    }

    public function usuarioRadico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_radicado_id');
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 7):

```php
use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\Fase;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
```

por:

```php
use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\ExpedienteEtapa;
use App\Models\Fase;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 7):

```php
use App\Models\RequisitoTipologia;
use App\Models\Responsable;
use App\Models\User;
use App\Services\Contratacion\PlantillasContratacion;
use Closure;
use Illuminate\Http\UploadedFile;
```

por:

```php
use App\Models\RequisitoTipologia;
use App\Models\Responsable;
use App\Models\User;
use App\Services\Contratacion\AvisosContratacion;
use App\Services\Contratacion\PlantillasContratacion;
use Closure;
use Illuminate\Http\UploadedFile;
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (3 de 7):

```php
 *   `cumplidos` siguen siendo conteos. En viabilización el peso no se usa. Cerrado, queda congelado como uno
 *   radicado. La fecha y el usuario del cierre se guardan en fecha_radicado y
 *   usuario_radicado_id: los del acto que termina el trámite del expediente.
 */
final class ExpedienteService
{
```

por:

```php
 *   `cumplidos` siguen siendo conteos. En viabilización el peso no se usa. Cerrado, queda congelado como uno
 *   radicado. La fecha y el usuario del cierre se guardan en fecha_radicado y
 *   usuario_radicado_id: los del acto que termina el trámite del expediente.
 * - Cronograma (solo contratación): cada etapa puede tener fecha programada y fecha real
 *   (expediente_etapas). Tras cada acción que cambia el avance, una etapa que acaba de
 *   completarse recibe la de hoy como sugerida, y una que dejó de estarlo pierde la suya si era
 *   sugerida; la que puso una persona se conserva, y la que una persona vació estando completa no
 *   se vuelve a sugerir mientras lo siga (fechasTrasElAvance, el único lugar). El avance
 *   por etapa presentado trae sus fechas y su semáforo, y el expediente sus avisos
 *   (AvisosContratacion).
 */
final class ExpedienteService
{
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (4 de 7):

```php
        private readonly HistorialChecklist $historial,
        private readonly PlantillasExpediente $plantillas,
        private readonly PlantillasContratacion $contratacion,
    ) {
    }
```

por:

```php
        private readonly HistorialChecklist $historial,
        private readonly PlantillasExpediente $plantillas,
        private readonly PlantillasContratacion $contratacion,
        private readonly AvisosContratacion $avisos,
    ) {
    }
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (5 de 7):

```php
     * - Los requisitos propios no se tocan.
     * - Corre en su propia transacción (un savepoint si ya hay una) y con la fila del
     *   expediente bloqueada: dos recálculos simultáneos no eligen anclas distintas para una llave.
     *
     * @return array{aplican:int, retenidos:int}
     */
    public function sincronizar(ProyectoExpediente $expediente): array
    {
        return DB::transaction(function () use ($expediente) {
            $this->bloquear($expediente);

            return $this->recalcular($expediente);
        });
    }

    /** @return array{aplican:int, retenidos:int} */
```

por:

```php
     * - Los requisitos propios no se tocan.
     * - Corre en su propia transacción (un savepoint si ya hay una) y con la fila del
     *   expediente bloqueada: dos recálculos simultáneos no eligen anclas distintas para una llave.
     * - En uno de contratación, cambiar los requisitos cambia el avance: cuando la transacción
     *   más externa se confirma, ajusta la fecha real sugerida con el mismo punto único que
     *   avanceDe() (avanceConFechaSugerida), sobre una instancia propia del expediente. Si la
     *   transacción se revierte (la vista previa de una versión nueva), no ajusta nada.
     *
     * @return array{aplican:int, retenidos:int}
     */
    public function sincronizar(ProyectoExpediente $expediente): array
    {
        $resultado = DB::transaction(function () use ($expediente) {
            $this->bloquear($expediente);

            return $this->recalcular($expediente);
        });
        if ($expediente->esContratacion()) {
            $id = $expediente->id;
            DB::afterCommit(function () use ($id) {
                $actual = ProyectoExpediente::query()->find($id);
                if ($actual) {
                    $this->avanceConFechaSugerida($actual);
                }
            });
        }

        return $resultado;
    }

    /** @return array{aplican:int, retenidos:int} */
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (6 de 7):

```php
                'tipologias' => $this->opciones($expediente->tipologias),
            ]]),
            'progreso' => $contratacion ? $this->progresoPonderado($filas, $posiciones) : $this->progresoDe($expediente),
            ...($contratacion ? ['avance_por_etapa' => $this->avancePorEtapa($filas, $posiciones)] : []),
            'requisitos' => $filas->map(fn (ProyectoRequisito $f) => $this->presentarFila($f, $posiciones))->values(),
            // Los más parecidos, hasta MAX_AVISOS; el total dice cuántos hay en realidad.
            'avisos_duplicados' => $avisos['avisos'],
```

por:

```php
                'tipologias' => $this->opciones($expediente->tipologias),
            ]]),
            'progreso' => $contratacion ? $this->progresoPonderado($filas, $posiciones) : $this->progresoDe($expediente),
            ...($contratacion ? $this->cronogramaYAvisos($expediente, $this->avancePorEtapa($filas, $posiciones), $this->cronogramaDe($expediente)) : []),
            'requisitos' => $filas->map(fn (ProyectoRequisito $f) => $this->presentarFila($f, $posiciones))->values(),
            // Los más parecidos, hasta MAX_AVISOS; el total dice cuántos hay en realidad.
            'avisos_duplicados' => $avisos['avisos'],
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (7 de 7):

```php

    /**
     * El avance que acompaña la respuesta de una acción sobre el checklist: el progreso y, en
     * uno de contratación, también el avance por etapa, ambos de las mismas filas.
     */
    public function avanceDe(ProyectoExpediente $expediente): array
    {
        if (! $expediente->esContratacion()) {
            return ['progreso' => $this->progresoDe($expediente)];
        }
        $filas = $this->filasDelAvance($expediente);
        $posiciones = $this->posiciones($this->plantillas->versionesDe($expediente));

        return [
            'progreso' => $this->progresoPonderado($filas, $posiciones),
            'avance_por_etapa' => $this->avancePorEtapa($filas, $posiciones),
        ];
    }

    /** @return list<array{etapa: ?array, total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}> el de avancePorEtapa() */
    public function avancePorEtapaDe(ProyectoExpediente $expediente): array
    {
```

por:

```php

    /**
     * El avance que acompaña la respuesta de una acción sobre el checklist: el progreso y, en
     * uno de contratación, también el avance por etapa (con sus fechas y su semáforo) y los
     * avisos, de las mismas filas. En uno de contratación se llama después de toda acción que
     * cambia el avance, ya guardada y fuera de su transacción: ajusta la fecha real sugerida de
     * las etapas (avanceConFechaSugerida).
     *
     * @return array{progreso: array, avance_por_etapa?: list<array>, avisos?: array}
     */
    public function avanceDe(ProyectoExpediente $expediente): array
    {
        if (! $expediente->esContratacion()) {
            return ['progreso' => $this->progresoDe($expediente)];
        }
        [$filas, $posiciones, $avance, $cronograma] = $this->avanceConFechaSugerida($expediente);

        return [
            'progreso' => $this->progresoPonderado($filas, $posiciones),
            ...$this->cronogramaYAvisos($expediente, $avance, $cronograma),
        ];
    }

    /**
     * El punto único que ajusta la fecha real sugerida de un expediente de contratación: lo usan
     * avanceDe() (tras cada acción del checklist) y sincronizar() (al nacer el expediente o con
     * una versión nueva). Con el expediente bloqueado (la misma fila que bloquean las acciones
     * del checklist y las fechas de una etapa), lee el avance y aplica fechasTrasElAvance(), todo
     * en una transacción. Se llama con la acción ya guardada y fuera de su transacción, así que
     * nada de esto la hace fallar: si el bloqueo, un interbloqueo o una escritura fallan, se
     * registra el error, se deshace solo el ajuste y se responde el avance sin él.
     *
     * @return array{0: Collection<int, ProyectoRequisito>, 1: array, 2: list<array>, 3: Collection<int, ExpedienteEtapa>}
     *         las filas del avance, sus posiciones, el avance por etapa y el cronograma
     */
    private function avanceConFechaSugerida(ProyectoExpediente $expediente): array
    {
        $leer = function () use ($expediente): array {
            $filas = $this->filasDelAvance($expediente);
            $posiciones = $this->posiciones($this->plantillas->versionesDe($expediente));

            return [$filas, $posiciones, $this->avancePorEtapa($filas, $posiciones)];
        };

        try {
            return DB::transaction(function () use ($expediente, $leer) {
                $this->bloquear($expediente);
                [$filas, $posiciones, $avance] = $leer();

                return [$filas, $posiciones, $avance, $this->fechasTrasElAvance($expediente, $avance)];
            });
        } catch (\Throwable $e) {
            report($e);
            [$filas, $posiciones, $avance] = $leer();

            return [$filas, $posiciones, $avance, $this->cronogramaDe($expediente)];
        }
    }

    /**
     * El avance por etapa de un expediente de contratación, con sus fechas y su semáforo, y sus
     * avisos, sin cambiar nada.
     *
     * @return array{avance_por_etapa: list<array>, avisos: array}
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
     * La fecha real sugerida, tras una acción que cambió el avance: el único lugar que la pone o
     * la quita. Solo sugiere cuando una etapa pasa de incompleta a completa, y lo sabe por su
     * fila, sin comparar el avance de antes con el de después: una etapa completa cuya fecha real
     * nadie atendió (sin fila, o sin fecha real y sin estar descartada,
     * ExpedienteEtapa::descartada()) acaba de completarse. Así sirve igual para toda acción y
     * para sincronizar, aunque varias peticiones se crucen. En borrador, esa etapa recibe la de
     * hoy como sugerida (su fila nace si no la tiene), y cada etapa que no está completa pierde
     * la sugerida o el descarte, para que al completarse otra vez se sugiera; la fecha que puso
     * una persona no se toca. Las escrituras van por lote, no por etapa. Va dentro de la
     * transacción de avanceConFechaSugerida(), que atrapa sus errores: un fallo deshace todo el
     * ajuste.
     *
     * @param list<array> $avance el de avancePorEtapa()
     * @return Collection<int, ExpedienteEtapa> el cronograma resultante, como cronogramaDe()
     */
    private function fechasTrasElAvance(ProyectoExpediente $expediente, array $avance): Collection
    {
        $cronograma = $this->cronogramaDe($expediente);
        if (! $expediente->esBorrador()) {
            return $cronograma;
        }

        $nuevas = [];
        $sugerir = [];
        $quitar = [];
        foreach ($avance as $etapa) {
            if ($etapa['etapa'] === null) {
                continue;
            }
            $fila = $cronograma->get($etapa['etapa']['id']);
            if ($this->avisos->completa($etapa)) {
                // Completa: si nadie atendió su fecha real (ni el sistema ni una persona que la
                // vació estando completa), acaba de completarse: se sugiere.
                if (! $fila) {
                    $nuevas[] = $etapa['etapa']['id'];
                } elseif ($fila->fecha_real === null && ! $fila->descartada()) {
                    $sugerir[] = $fila->id;
                }
            } elseif ($fila && $fila->fecha_real_sugerida) {
                // Ya no está completa: se quita la sugerida, o se olvida que la vaciaron.
                $quitar[] = $fila->id;
            }
        }
        if ($nuevas === [] && $sugerir === [] && $quitar === []) {
            return $cronograma;
        }

        $hoy = $this->avisos->hoy()->toDateString();
        $ahora = now();
        if ($nuevas !== []) {
            ExpedienteEtapa::query()->insert(array_map(fn (int $etapaId) => [
                'expediente_id' => $expediente->id,
                'etapa_id' => $etapaId,
                'fecha_real' => $hoy,
                'fecha_real_sugerida' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ], $nuevas));
        }
        if ($sugerir !== []) {
            ExpedienteEtapa::query()->whereIn('id', $sugerir)->update(['fecha_real' => $hoy, 'fecha_real_sugerida' => true]);
        }
        if ($quitar !== []) {
            ExpedienteEtapa::query()->whereIn('id', $quitar)->update(['fecha_real' => null, 'fecha_real_sugerida' => false]);
        }

        return $this->cronogramaDe($expediente);
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
            'avance_por_etapa' => $presentado,
            'avisos' => $this->avisos->resumen(
                $expediente->proceso,
                $expediente,
                $presentado,
                $this->avisos->ultimosEventos([$expediente->id])[$expediente->id] ?? null
            ),
        ];
    }

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
    public function avancePorEtapaDe(ProyectoExpediente $expediente): array
    {
```

En `routes/viabilizacion.php`, reemplazar (1 de 2):

```php
 */

use App\Http\Controllers\CatalogoRequisitosController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\NoAplicaRequisitoController;
use App\Http\Controllers\PlantillaChecklistController;
```

por:

```php
 */

use App\Http\Controllers\CatalogoRequisitosController;
use App\Http\Controllers\CronogramaExpedienteController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\NoAplicaRequisitoController;
use App\Http\Controllers\PlantillaChecklistController;
```

En `routes/viabilizacion.php`, reemplazar (2 de 2):

```php
    // Solo los de contratación: se cierran, con aviso de los pendientes.
    Route::post('/expedientes/{expediente}/cerrar', [ExpedienteController::class, 'cerrar'])
        ->name('expedientes.cerrar');
    Route::delete('/expedientes/{expediente}', [ExpedienteController::class, 'destroy'])
        ->name('expedientes.destroy');
```

por:

```php
    // Solo los de contratación: se cierran, con aviso de los pendientes.
    Route::post('/expedientes/{expediente}/cerrar', [ExpedienteController::class, 'cerrar'])
        ->name('expedientes.cerrar');
    // Solo los de contratación: fechas programada y real de una etapa.
    Route::put('/expedientes/{expediente}/etapas/{etapa}', [CronogramaExpedienteController::class, 'update'])
        ->whereNumber('etapa')->name('expedientes.etapas.update');
    Route::delete('/expedientes/{expediente}', [ExpedienteController::class, 'destroy'])
        ->name('expedientes.destroy');
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/CronogramaEtapasApiTest.php tests/Feature/Schema/ExpedienteEtapasTest.php tests/Feature/Contratacion/AvancePonderadoApiTest.php tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1253 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Http/Controllers/CronogramaExpedienteController.php app/Models/ExpedienteEtapa.php app/Services/Contratacion/AvisosContratacion.php app/Services/Contratacion/CronogramaExpediente.php config/contratacion.php database/migrations/2026_11_19_100021_create_expediente_etapas_table.php tests/Feature/Contratacion/CronogramaEtapasApiTest.php tests/Feature/Schema/ExpedienteEtapasTest.php app/Models/ProyectoExpediente.php app/Services/Viabilizacion/ExpedienteService.php routes/viabilizacion.php tests/Feature/Contratacion/AvancePonderadoApiTest.php tests/Feature/Contratacion/ExpedienteContratacionApiTest.php
git commit -m "feat: stage dates and alerts for contracting expedients (migration 22)"
```

---

### Tarea 3: Avisos del proceso en los procesos, la ficha y los contratos

Cada proceso trae una sola clave `avisos` en la lista de procesos del proyecto, al crear, editar y cambiar de estado, y en la ficha, que los calcula una vez. Los contratos con proceso traen `aviso_proceso` en la búsqueda, el detalle y `listarContratos`. Todo en lote, sin consultas por proceso ni por contrato.

**Files:**
- Test (create): `tests/Feature/Contratacion/AvisosProcesosApiTest.php`
- Modify: `app/Http/Controllers/ProyectoController.php`
- Modify: `app/Services/Contratacion/ContratosService.php`
- Modify: `app/Services/Contratacion/ExpedientesContratacion.php`
- Modify: `app/Services/Contratacion/ProcesosContratacion.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Test: `tests/Feature/Contratacion/ContratosBusquedaApiTest.php`
- Test: `tests/Feature/Contratacion/ProcesosContratacionApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/AvisosProcesosApiTest.php`:

```php
<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\Contrato;
use App\Models\EtapaContratacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\User;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Avisos de los procesos de contratación (R14, criterio 11) en la lista de procesos del proyecto,
 * sus respuestas y la ficha (`avisos` del proceso), y en los contratos del módulo Contratos
 * (`aviso_proceso`). Hoy es el 19 de noviembre de 2026 salvo que la prueba diga otra cosa.
 */
class AvisosProcesosApiTest extends TestCase
{
    use RefreshDatabase;

    private const HOY = '2026-11-19';

    private const SIN_AVISOS = [
        'etapas_vencidas' => 0,
        'etapas_proximas' => 0,
        'cierre_proximo' => false,
        'cierre_vencido' => false,
        'dias_para_cierre' => null,
        'sin_actualizacion' => false,
        'dias_sin_actualizacion' => 0,
        'hitos' => ['completadas' => 0, 'total' => 0],
    ];

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

    private function modalidad(): int
    {
        return Modalidad::where('clave', 'licitacion_publica')->value('id');
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
            'modalidad_ids' => [$this->modalidad()],
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

    private function proceso(array $cambios = []): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => $this->proyecto->id,
            'objeto' => 'Construcción del acueducto',
            'modalidad' => $this->modalidad(),
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-' . (ProcesoLicitacion::count() + 1),
            ...$cambios,
        ]);
    }

    private function expediente(PlantillaChecklist $plantilla, ProcesoLicitacion $proceso): ProyectoExpediente
    {
        $id = $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => [$plantilla->id]])
            ->assertCreated()->json('id');

        return ProyectoExpediente::findOrFail($id);
    }

    private function fechas(ProyectoExpediente $expediente, string $etapa, array $datos): void
    {
        $this->como()->putJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa($etapa)->id}", $datos)->assertOk();
    }

    /** Planeación vencida (ayer) y pliego definitivo próximo (en 3 días). */
    private function conVencidaYProxima(ProyectoExpediente $expediente): void
    {
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-18']);
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_programada' => '2026-11-22']);
    }

    private function fila(ProyectoExpediente $expediente, string $texto): ProyectoRequisito
    {
        return $expediente->requisitos()->whereHas('requisito', fn ($q) => $q->where('texto', $texto))->firstOrFail();
    }

    private function procesos(): array
    {
        return $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk()->json('procesos');
    }

    private function contrato(ProcesoLicitacion $proceso, string $numero = 'C-001'): Contrato
    {
        return Contrato::create([
            'proyecto' => $this->proyecto->id,
            'proceso_licitacion' => $proceso->id,
            'n_contrato' => $numero,
            'objeto' => 'Construcción del acueducto',
            'valor_inicial' => 1000000,
            'valor' => 1000000,
            'estado' => 'en_ejecucion',
        ]);
    }

    // ------------------------------------------------------------ procesos

    public function test_criterio_11_la_lista_de_procesos_trae_los_avisos_de_cada_uno(): void
    {
        $plantilla = $this->plantilla();
        $conExpediente = $this->proceso(['fecha_cierre' => '2026-11-21']);
        $expediente = $this->expediente($plantilla, $conExpediente);
        $this->conVencidaYProxima($expediente);
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, 'Acta de cierre')->id}", ['cumplido' => true])->assertOk();
        $sinExpediente = $this->proceso(['fecha_cierre' => '2026-11-10']);

        $procesos = $this->procesos();

        $this->assertSame([$conExpediente->id, $sinExpediente->id], array_column($procesos, 'id'));
        $this->assertSame([
            'etapas_vencidas' => 1,
            'etapas_proximas' => 1,
            'cierre_proximo' => true,
            'cierre_vencido' => false,
            'dias_para_cierre' => 2,
            'sin_actualizacion' => false,
            'dias_sin_actualizacion' => 0,
            'hitos' => ['completadas' => 1, 'total' => 3],
        ], $procesos[0]['avisos']);
        // El bloque del expediente no cambia.
        $this->assertSame(['id', 'estado', 'progreso', 'aviso_plantilla'], array_keys($procesos[0]['expediente']));
        $this->assertNull($procesos[1]['expediente']);
        $this->assertSame([...self::SIN_AVISOS, 'cierre_vencido' => true, 'dias_para_cierre' => -9], $procesos[1]['avisos']);

        // La ficha del proyecto trae los mismos.
        $ficha = $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/ficha")->assertOk()->json('procesos');
        $this->assertSame(array_column($procesos, 'avisos'), array_column($ficha, 'avisos'));
    }

    public function test_sin_actualizacion_a_los_15_dias_salvo_en_un_estado_final_o_con_el_expediente_cerrado(): void
    {
        $this->travelTo('2026-11-01 10:00:00');
        $plantilla = $this->plantilla();
        $sinExpediente = $this->proceso();
        $abierto = $this->proceso();
        $this->expediente($plantilla, $abierto);
        $cerrado = $this->proceso();
        $this->como()->postJson("/GestPro/expedientes/{$this->expediente($plantilla, $cerrado)->id}/cerrar", ['confirmar' => true])->assertOk();
        $celebrado = $this->proceso(['estado' => ProcesoLicitacion::CELEBRADO]);

        $this->travelTo('2026-11-15 10:00:00');
        $this->assertSame([false, false, false, false], array_column(array_column($this->procesos(), 'avisos'), 'sin_actualizacion'));

        $this->travelTo('2026-11-16 10:00:00');
        $avisos = array_column($this->procesos(), 'avisos', 'id');
        $this->assertSame(
            [$sinExpediente->id => true, $abierto->id => true, $cerrado->id => false, $celebrado->id => false],
            array_map(fn (array $a) => $a['sin_actualizacion'], $avisos)
        );
        $this->assertSame(15, $avisos[$celebrado->id]['dias_sin_actualizacion']);

        // Un evento en el historial del expediente es actividad; editar el proceso, también.
        $expediente = $abierto->expediente;
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, 'CDP')->id}", ['observacion' => 'Pedido'])->assertOk();
        $this->como()->putJson("/GestPro/procesos/{$sinExpediente->id}", [
            'objeto' => 'Construcción del acueducto veredal', 'modalidad_id' => $this->modalidad(), 'plataforma' => 'secop_ii',
            'actualizado' => $sinExpediente->fresh()->updated_at->toIso8601String(),
        ])->assertOk()->assertJsonPath('avisos.sin_actualizacion', false)->assertJsonPath('avisos.dias_sin_actualizacion', 0);
        $avisos = array_column($this->procesos(), 'avisos', 'id');
        $this->assertFalse($avisos[$abierto->id]['sin_actualizacion']);
        $this->assertSame(0, $avisos[$abierto->id]['dias_sin_actualizacion']);
    }

    public function test_un_expediente_cerrado_no_cuenta_vencidas(): void
    {
        $proceso = $this->proceso();
        $expediente = $this->expediente($this->plantilla(), $proceso);
        $this->conVencidaYProxima($expediente);
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertOk();

        $avisos = $this->procesos()[0]['avisos'];
        $this->assertSame(0, $avisos['etapas_vencidas']);
        $this->assertSame(0, $avisos['etapas_proximas']);
        $this->assertSame(['completadas' => 0, 'total' => 3], $avisos['hitos']);
    }

    public function test_crear_editar_y_cambiar_el_estado_responden_los_avisos(): void
    {
        $creado = $this->como()->postJson("/GestPro/proyectos/{$this->proyecto->id}/procesos", [
            'objeto' => 'Construcción del acueducto', 'modalidad_id' => $this->modalidad(), 'plataforma' => 'secop_ii',
            'fecha_cierre' => '2026-11-20',
        ])->assertCreated();
        $this->assertSame([...self::SIN_AVISOS, 'cierre_proximo' => true, 'dias_para_cierre' => 1], $creado->json('avisos'));
        $proceso = ProcesoLicitacion::findOrFail($creado->json('id'));

        $this->como()->putJson("/GestPro/procesos/{$proceso->id}", [
            'objeto' => 'Construcción del acueducto', 'modalidad_id' => $this->modalidad(), 'plataforma' => 'secop_ii',
            'fecha_cierre' => '2026-11-18', 'actualizado' => $creado->json('actualizado'),
        ])->assertOk()->assertJsonPath('avisos.cierre_vencido', true)->assertJsonPath('avisos.cierre_proximo', false);

        // Ya en evaluación, el cierre pasó: no avisa.
        $this->como()->postJson("/GestPro/procesos/{$proceso->id}/estado", [
            'estado' => ProcesoLicitacion::EVALUACION, 'actualizado' => $proceso->fresh()->updated_at->toIso8601String(),
        ])->assertOk()->assertJsonPath('avisos', [...self::SIN_AVISOS, 'dias_para_cierre' => -1]);
    }

    public function test_la_lista_de_procesos_con_avisos_no_hace_una_consulta_por_proceso(): void
    {
        $plantilla = $this->plantilla();
        $this->conVencidaYProxima($this->expediente($plantilla, $this->proceso(['fecha_cierre' => '2026-11-21'])));
        $consultas = function (): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $conUno = $consultas();

        foreach (range(1, 3) as $i) {
            $expediente = $this->expediente($plantilla, $this->proceso(['fecha_cierre' => '2026-11-0' . $i]));
            $this->conVencidaYProxima($expediente);
            $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, 'Acta de cierre')->id}", ['cumplido' => true])->assertOk();
        }
        $this->proceso();

        $this->assertSame($conUno, $consultas());
        $this->assertSame([1, 1, 1, 1, 0], array_column(array_column($this->procesos(), 'avisos'), 'etapas_vencidas'));
    }

    // ------------------------------------------------------------ contratos

    public function test_los_contratos_traen_el_aviso_de_su_proceso(): void
    {
        $plantilla = $this->plantilla();
        $vencido = $this->proceso(['estado' => ProcesoLicitacion::ADJUDICADO]);
        $this->conVencidaYProxima($this->expediente($plantilla, $vencido));
        $alDia = $this->proceso(['estado' => ProcesoLicitacion::ADJUDICADO]);
        $conVencidas = $this->contrato($vencido, 'C-001');
        $sinAviso = $this->contrato($alDia, 'C-002');
        $sinProceso = Contrato::create([
            'proyecto' => $this->proyecto->id, 'n_contrato' => 'C-003', 'objeto' => 'Suministro', 'valor_inicial' => 1, 'valor' => 1, 'estado' => 'en_ejecucion',
        ]);
        $esperado = ['proceso_id' => $vencido->id, 'etapas_vencidas' => 1, 'cierre_vencido' => false, 'sin_actualizacion' => false, 'dias_sin_actualizacion' => 0];

        $filas = collect($this->como(Rol::Consulta)->getJson('/GestPro/contratos/buscar')->assertOk()->json())->keyBy('id');
        $this->assertSame($esperado, $filas[$conVencidas->id]['aviso_proceso']);
        $this->assertNull($filas[$sinAviso->id]['aviso_proceso']);
        $this->assertNull($filas[$sinProceso->id]['aviso_proceso']);

        $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$conVencidas->id}/detalle")->assertOk()->assertJsonPath('aviso_proceso', $esperado);
        $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$sinProceso->id}/detalle")->assertOk()->assertJsonPath('aviso_proceso', null);
        $listado = collect($this->como(Rol::Consulta)->getJson("/GestPro/listarContratos?proyecto={$this->proyecto->id}")->assertOk()->json())->keyBy('id');
        $this->assertSame($esperado, $listado[$conVencidas->id]['aviso_proceso']);
        $this->assertNull($listado[$sinAviso->id]['aviso_proceso']);

        $ficha = collect($this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/ficha")->assertOk()->json('contratos'))->keyBy('id');
        $this->assertSame($esperado, $ficha[$conVencidas->id]['aviso_proceso']);

        // Sin actualización, o con el cierre vencido, también avisa.
        $alDia->update(['fecha_cierre' => '2026-11-01', 'estado' => ProcesoLicitacion::PUBLICADO]);
        $this->travelTo('2026-12-10 10:00:00');
        $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$sinAviso->id}/detalle")->assertOk()->assertJsonPath('aviso_proceso', [
            'proceso_id' => $alDia->id, 'etapas_vencidas' => 0, 'cierre_vencido' => true, 'sin_actualizacion' => true, 'dias_sin_actualizacion' => 21,
        ]);
    }

    public function test_un_proceso_en_estado_final_no_avisa_etapas_vencidas_ni_en_sus_contratos(): void
    {
        $proceso = $this->proceso(['estado' => ProcesoLicitacion::ADJUDICADO]);
        $this->conVencidaYProxima($this->expediente($this->plantilla(), $proceso));
        $contrato = $this->contrato($proceso);
        $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$contrato->id}/detalle")->assertOk()->assertJsonPath('aviso_proceso.etapas_vencidas', 1);

        $proceso->update(['estado' => ProcesoLicitacion::CELEBRADO]);
        $avisos = $this->procesos()[0]['avisos'];
        $this->assertSame([0, 0], [$avisos['etapas_vencidas'], $avisos['etapas_proximas']]);
        $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$contrato->id}/detalle")->assertOk()->assertJsonPath('aviso_proceso', null);
        $filas = collect($this->como(Rol::Consulta)->getJson('/GestPro/contratos/buscar')->assertOk()->json())->keyBy('id');
        $this->assertNull($filas[$contrato->id]['aviso_proceso']);
    }

    public function test_la_ficha_del_proyecto_calcula_los_avisos_de_los_procesos_una_sola_vez(): void
    {
        $plantilla = $this->plantilla();
        $proceso = $this->proceso(['estado' => ProcesoLicitacion::ADJUDICADO]);
        $this->conVencidaYProxima($this->expediente($plantilla, $proceso));
        $this->contrato($proceso);
        $this->expediente($plantilla, $this->proceso());

        $peticion = $this->como(Rol::Consulta);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $ficha = $peticion->getJson("/GestPro/proyectos/{$this->proyecto->id}/ficha")->assertOk();
        DB::disableQueryLog();

        // resumenDeProcesos lee los expedientes de los procesos con una consulta: una sola vez.
        $lecturas = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains($q['query'], 'from "proyectos_expedientes"')
            && str_contains($q['query'], '"proceso_id" in'));
        $this->assertCount(1, $lecturas);
        $this->assertSame(1, $ficha->json('contratos.0.aviso_proceso.etapas_vencidas'));
        $this->assertSame([1, 0], array_column(array_column($ficha->json('procesos'), 'avisos'), 'etapas_vencidas'));
    }

    public function test_la_busqueda_de_contratos_con_avisos_no_hace_una_consulta_por_contrato(): void
    {
        $plantilla = $this->plantilla();
        $proceso = $this->proceso(['estado' => ProcesoLicitacion::ADJUDICADO]);
        $this->conVencidaYProxima($this->expediente($plantilla, $proceso));
        $this->contrato($proceso, 'C-001');
        $consultas = function (): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson('/GestPro/contratos/buscar')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $conUno = $consultas();

        foreach (range(2, 4) as $i) {
            $otro = $this->proceso(['estado' => ProcesoLicitacion::ADJUDICADO]);
            $this->conVencidaYProxima($this->expediente($plantilla, $otro));
            $this->contrato($otro, "C-00{$i}");
        }

        $this->assertSame($conUno, $consultas());
    }
}
```

En `tests/Feature/Contratacion/ContratosBusquedaApiTest.php`, reemplazar (1 de 1):

```php
        $fila = $filas[0];
        $this->assertSame(
            ['id', 'n_contrato', 'objeto', 'contratista', 'contratante', 'estado', 'valor', 'valor_inicial', 'fecha_inicio', 'fecha_fin',
                'avance_fisico', 'avance_financiero', 'tipo_contrato', 'proyecto', 'proceso', 'secop_id_contrato', 'updated_at'],
            array_keys($fila),
        );
        $this->assertSame($c->id, $fila['id']);
```

por:

```php
        $fila = $filas[0];
        $this->assertSame(
            ['id', 'n_contrato', 'objeto', 'contratista', 'contratante', 'estado', 'valor', 'valor_inicial', 'fecha_inicio', 'fecha_fin',
                'avance_fisico', 'avance_financiero', 'tipo_contrato', 'proyecto', 'proceso', 'aviso_proceso', 'secop_id_contrato', 'updated_at'],
            array_keys($fila),
        );
        $this->assertSame($c->id, $fila['id']);
```

En `tests/Feature/Contratacion/ProcesosContratacionApiTest.php`, reemplazar (1 de 1):

```php
            'contratos' => 0,
            // Sin plantilla de contratación publicada, no nace expediente.
            'expediente' => null,
            'creado' => $r->json('creado'),
            'actualizado' => $r->json('actualizado'),
            'usuario_creo' => 'Ana Gestora',
```

por:

```php
            'contratos' => 0,
            // Sin plantilla de contratación publicada, no nace expediente.
            'expediente' => null,
            'avisos' => [
                'etapas_vencidas' => 0, 'etapas_proximas' => 0, 'cierre_proximo' => false, 'cierre_vencido' => false,
                'dias_para_cierre' => null, 'sin_actualizacion' => false, 'dias_sin_actualizacion' => 0,
                'hitos' => ['completadas' => 0, 'total' => 0],
            ],
            'creado' => $r->json('creado'),
            'actualizado' => $r->json('actualizado'),
            'usuario_creo' => 'Ana Gestora',
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/AvisosProcesosApiTest.php tests/Feature/Contratacion/ContratosBusquedaApiTest.php tests/Feature/Contratacion/ProcesosContratacionApiTest.php`
Expected: FALLAN 10 y pasan 128: en `AvisosProcesosApiTest` fallan 8 de 9, porque los procesos todavía no traen `avisos` ni los contratos `aviso_proceso`; en `ContratosBusquedaApiTest` falla `la fila trae los campos acordados` y en `ProcesosContratacionApiTest` `un gestor crea un proceso en preparacion`, que esperan esas claves nuevas.

- [ ] **Paso 3: Implementar**

En `app/Http/Controllers/ProyectoController.php`, reemplazar (1 de 2):

```php
        return response()->json([
            'proyecto' => $fila,
            'expediente' => $expedientes->resumenPorProyecto($modelo->id)[0] ?? null,
            'procesos' => $procesos->deProyecto($modelo),
            'contratos' => $contratos->buscar(['proyecto' => (string) $modelo->id]),
        ]);
    }
```

por:

```php
        return response()->json([
            'proyecto' => $fila,
            'expediente' => $expedientes->resumenPorProyecto($modelo->id)[0] ?? null,
            // Los avisos de los procesos se calculan una vez: los contratos reusan los de la lista.
            'procesos' => $procesos->deProyecto($modelo, $resumenes),
            'contratos' => $contratos->buscar(['proyecto' => (string) $modelo->id], $resumenes),
        ]);
    }
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (2 de 2):

```php
    {
        $proyecto = $request->all();
        $contratos = DB::table('contratos')->where('proyecto', $proyecto['proyecto'])->get();
        app(ContratosService::class)->agregarProcesos($contratos);
        app(ContratosService::class)->agregarDetalle($contratos);

        return response()->json($contratos);
```

por:

```php
    {
        $proyecto = $request->all();
        $contratos = DB::table('contratos')->where('proyecto', $proyecto['proyecto'])->get();
        // Con lo que avisa el proceso de cada contrato, como en el detalle.
        app(ContratosService::class)->agregarProcesos($contratos, true);
        app(ContratosService::class)->agregarDetalle($contratos);

        return response()->json($contratos);
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (1 de 8):

```php
 *   anula. Al eliminar se borran sus anexos, las filas y los archivos.
 * - Todo queda en checklist_historial (entidad_tipo = contrato, sin expediente): al editar, solo
 *   lo que cambió; al eliminar, los datos para saber qué era.
 */
final class ContratosService
{
```

por:

```php
 *   anula. Al eliminar se borran sus anexos, las filas y los archivos.
 * - Todo queda en checklist_historial (entidad_tipo = contrato, sin expediente): al editar, solo
 *   lo que cambió; al eliminar, los datos para saber qué era.
 * - La búsqueda, el detalle y el listado por proyecto traen `aviso_proceso`: lo que avisa el
 *   proceso del contrato (etapas vencidas, cierre vencido o sin actualización), o null si no
 *   tiene proceso o este no avisa nada (ExpedientesContratacion::resumenDeProcesos).
 */
final class ContratosService
{
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (2 de 8):

```php

    private const CARPETA_ANEXOS = 'anexos_contratos';

    public function __construct(private readonly HistorialChecklist $historial)
    {
    }

    /**
```

por:

```php

    private const CARPETA_ANEXOS = 'anexos_contratos';

    public function __construct(
        private readonly HistorialChecklist $historial,
        private readonly ExpedientesContratacion $expedientes,
    ) {
    }

    /**
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (3 de 8):

```php

    /**
     * Completa las filas de contratos de los listados (consultas con DB::table) con `proceso_id`
     * y `proceso` resumido, o null, en una sola consulta de procesos.
     *
     * @param iterable<object> $filas
     */
    public function agregarProcesos(iterable $filas): void
    {
        $filas = Collection::make($filas);
        $procesos = ProcesoLicitacion::query()
```

por:

```php

    /**
     * Completa las filas de contratos de los listados (consultas con DB::table) con `proceso_id`
     * y `proceso` resumido, o null, en una sola consulta de procesos. Con `$conAviso`, también con
     * `aviso_proceso` (avisoProceso()), con consultas que no crecen con los procesos.
     *
     * @param iterable<object> $filas
     * @param array<int, array{avisos: array}> $resumenes los de ExpedientesContratacion::resumenDeProcesos()
     *        que quien llama ya tiene (proceso_id => resumen): solo se calculan los que faltan
     */
    public function agregarProcesos(iterable $filas, bool $conAviso = false, array $resumenes = []): void
    {
        $filas = Collection::make($filas);
        $procesos = ProcesoLicitacion::query()
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (4 de 8):

```php
            ->with('modalidadRel:id,nombre')
            ->get()
            ->keyBy('id');

        foreach ($filas as $fila) {
            $proceso = $fila->proceso_licitacion !== null ? $procesos->get($fila->proceso_licitacion) : null;
            $fila->proceso_id = $this->entero($fila->proceso_licitacion);
            $fila->proceso = $proceso ? $this->resumenProceso($proceso) : null;
        }
    }

    /**
```

por:

```php
            ->with('modalidadRel:id,nombre')
            ->get()
            ->keyBy('id');
        if ($conAviso) {
            $faltan = $procesos->reject(fn (ProcesoLicitacion $p) => isset($resumenes[$p->id]))->values();
            $resumenes += $faltan->isEmpty() ? [] : $this->expedientes->resumenDeProcesos($faltan);
        }

        foreach ($filas as $fila) {
            $proceso = $fila->proceso_licitacion !== null ? $procesos->get($fila->proceso_licitacion) : null;
            $fila->proceso_id = $this->entero($fila->proceso_licitacion);
            $fila->proceso = $proceso ? $this->resumenProceso($proceso) : null;
            if ($conAviso) {
                $fila->aviso_proceso = $proceso ? $this->avisoProceso($proceso->id, $resumenes[$proceso->id]['avisos']) : null;
            }
        }
    }

    /**
     * Lo que avisa el proceso de un contrato en el módulo Contratos: null si no tiene etapas
     * vencidas, ni el cierre vencido, ni está sin actualización.
     *
     * @param array $avisos los de AvisosContratacion::resumen()
     * @return ?array{proceso_id: int, etapas_vencidas: int, cierre_vencido: bool, sin_actualizacion: bool, dias_sin_actualizacion: ?int}
     */
    private function avisoProceso(int $procesoId, array $avisos): ?array
    {
        if ($avisos['etapas_vencidas'] === 0 && ! $avisos['cierre_vencido'] && ! $avisos['sin_actualizacion']) {
            return null;
        }

        return [
            'proceso_id' => $procesoId,
            'etapas_vencidas' => $avisos['etapas_vencidas'],
            'cierre_vencido' => $avisos['cierre_vencido'],
            'sin_actualizacion' => $avisos['sin_actualizacion'],
            'dias_sin_actualizacion' => $avisos['dias_sin_actualizacion'],
        ];
    }

    /**
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (5 de 8):

```php
     * `proyecto` (id o 'sin') y `proceso` (id).
     *
     * @param array{q?: ?string, estado?: ?string, proyecto?: int|string|null, proceso?: int|string|null} $filtros
     * @return list<array<string, mixed>>
     */
    public function buscar(array $filtros): array
    {
        $consulta = DB::table('contratos')
            ->leftJoin('proyectos', 'proyectos.id', '=', 'contratos.proyecto')
```

por:

```php
     * `proyecto` (id o 'sin') y `proceso` (id).
     *
     * @param array{q?: ?string, estado?: ?string, proyecto?: int|string|null, proceso?: int|string|null} $filtros
     * @param array<int, array{avisos: array}> $resumenes los resúmenes de procesos que quien llama ya tiene (agregarProcesos())
     * @return list<array<string, mixed>>
     */
    public function buscar(array $filtros, array $resumenes = []): array
    {
        $consulta = DB::table('contratos')
            ->leftJoin('proyectos', 'proyectos.id', '=', 'contratos.proyecto')
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (6 de 8):

```php
        }

        $filas = $consulta->get();
        $this->agregarProcesos($filas);

        return $filas->map(fn ($f) => [
            'id' => (int) $f->id,
```

por:

```php
        }

        $filas = $consulta->get();
        $this->agregarProcesos($filas, true, $resumenes);

        return $filas->map(fn ($f) => [
            'id' => (int) $f->id,
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (7 de 8):

```php
            'tipo_contrato' => $f->tipo_contrato_id !== null ? ['id' => (int) $f->tipo_contrato_id, 'nombre' => $f->tipo_nombre] : null,
            'proyecto' => $f->proyecto_id !== null ? ['id' => (int) $f->proyecto_id, 'nombre' => $f->proyecto_nombre] : null,
            'proceso' => $f->proceso,
            'secop_id_contrato' => $f->secop_id_contrato,
            'updated_at' => $f->updated_at,
        ])->all();
    }

    /**
     * El contrato con la misma forma que un elemento de GET /listarContratos, más `proyecto_nombre`,
     * o null si no existe.
     */
    public function detalle(int $id): ?object
    {
```

por:

```php
            'tipo_contrato' => $f->tipo_contrato_id !== null ? ['id' => (int) $f->tipo_contrato_id, 'nombre' => $f->tipo_nombre] : null,
            'proyecto' => $f->proyecto_id !== null ? ['id' => (int) $f->proyecto_id, 'nombre' => $f->proyecto_nombre] : null,
            'proceso' => $f->proceso,
            'aviso_proceso' => $f->aviso_proceso,
            'secop_id_contrato' => $f->secop_id_contrato,
            'updated_at' => $f->updated_at,
        ])->all();
    }

    /**
     * El contrato con la misma forma que un elemento de GET /listarContratos (con `aviso_proceso`),
     * más `proyecto_nombre`, o null si no existe.
     */
    public function detalle(int $id): ?object
    {
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (8 de 8):

```php
            return null;
        }
        $filas = [$fila];
        $this->agregarProcesos($filas);
        $this->agregarDetalle($filas);

        return $fila;
```

por:

```php
            return null;
        }
        $filas = [$fila];
        $this->agregarProcesos($filas, true);
        $this->agregarDetalle($filas);

        return $fila;
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (1 de 3):

```php
 *   proyecto al que le cambia el sector. Nunca interrumpe a quien guarda el proceso o el proyecto.
 * - Cambiar la modalidad o la fuente de un proceso con expediente no lo toca: el proceso avisa
 *   cuando ninguna de sus plantillas le sirve ya, y por qué (resumenDeProcesos).
 */
final class ExpedientesContratacion
{
    public function __construct(
        private readonly ExpedienteService $expedientes,
        private readonly PlantillasContratacion $plantillas,
    ) {
    }
```

por:

```php
 *   proyecto al que le cambia el sector. Nunca interrumpe a quien guarda el proceso o el proyecto.
 * - Cambiar la modalidad o la fuente de un proceso con expediente no lo toca: el proceso avisa
 *   cuando ninguna de sus plantillas le sirve ya, y por qué (resumenDeProcesos).
 * - resumenDeProcesos también da los avisos de cada proceso (AvisosContratacion): etapas
 *   vencidas y próximas, cierre, días sin actualización e hitos.
 */
final class ExpedientesContratacion
{
    public function __construct(
        private readonly ExpedienteService $expedientes,
        private readonly PlantillasContratacion $plantillas,
        private readonly AvisosContratacion $avisos,
    ) {
    }
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (2 de 3):

```php
    }

    /**
     * El expediente de cada proceso, resumido para la API del proceso: su estado, su avance
     * ponderado (ExpedienteService::progresoPonderadoDe) y el aviso si ninguna de sus plantillas
     * le sirve ya (no corresponde a la modalidad, la fuente o el sector, está inactiva o no tiene
     * versión publicada). Las consultas no crecen con los procesos.
     *
     * @param Collection<int, ProcesoLicitacion> $procesos
     * @return array<int, ?array{id: int, estado: string, progreso: array{total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}, aviso_plantilla: ?string}> proceso_id => resumen; null sin expediente
     */
    public function resumenDeProcesos(Collection $procesos): array
    {
```

por:

```php
    }

    /**
     * Lo que la API del proceso muestra de su expediente y sus avisos. `expediente` es el
     * expediente resumido: su estado, su avance ponderado (ExpedienteService::avanceDeVarios) y
     * el aviso si ninguna de sus plantillas le sirve ya (no corresponde a la modalidad, la fuente
     * o el sector, está inactiva o no tiene versión publicada); null sin expediente. `avisos` es
     * el resumen de AvisosContratacion, también para un proceso sin expediente (solo con el
     * cierre y la actualización). Las consultas no crecen con los procesos.
     *
     * @param Collection<int, ProcesoLicitacion> $procesos
     * @return array<int, array{expediente: ?array{id: int, estado: string, progreso: array{total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}, aviso_plantilla: ?string}, avisos: array}> proceso_id => resumen
     */
    public function resumenDeProcesos(Collection $procesos): array
    {
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (3 de 3):

```php
            ->with('versionesPlantilla')
            ->get()
            ->keyBy('proceso_id');
        $progresos = $this->expedientes->progresoPonderadoDe($expedientes->values());
        $estados = $this->plantillas->estadoPorProceso(
            $procesos->filter(fn (ProcesoLicitacion $p) => $expedientes->has($p->id))->values(),
            $expedientes->flatMap(fn (ProyectoExpediente $e) => $e->versionesPlantilla->pluck('plantilla_id'))
                ->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        return $procesos->mapWithKeys(function (ProcesoLicitacion $proceso) use ($expedientes, $estados, $progresos) {
            $expediente = $expedientes->get($proceso->id);
            if (! $expediente) {
                return [$proceso->id => null];
            }
            $razones = $expediente->versionesPlantilla
                ->map(fn ($v) => PlantillasContratacion::razon($estados[$proceso->id][(int) $v->plantilla_id]))
                ->all();

            return [$proceso->id => [
                'id' => $expediente->id,
                'estado' => $expediente->estado,
                'progreso' => $progresos[$expediente->id],
                'aviso_plantilla' => $this->avisoPlantilla($razones),
            ]];
        })->all();
    }
```

por:

```php
            ->with('versionesPlantilla')
            ->get()
            ->keyBy('proceso_id');
        $avances = $this->expedientes->avanceDeVarios($expedientes->values());
        $eventos = $this->avisos->ultimosEventos($expedientes->pluck('id'));
        $estados = $this->plantillas->estadoPorProceso(
            $procesos->filter(fn (ProcesoLicitacion $p) => $expedientes->has($p->id))->values(),
            $expedientes->flatMap(fn (ProyectoExpediente $e) => $e->versionesPlantilla->pluck('plantilla_id'))
                ->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        return $procesos->mapWithKeys(function (ProcesoLicitacion $proceso) use ($expedientes, $estados, $avances, $eventos) {
            $expediente = $expedientes->get($proceso->id);
            $avisos = $this->avisos->resumen(
                $proceso,
                $expediente,
                $expediente ? $avances[$expediente->id]['avance_por_etapa'] : [],
                $expediente ? ($eventos[$expediente->id] ?? null) : null
            );
            if (! $expediente) {
                return [$proceso->id => ['expediente' => null, 'avisos' => $avisos]];
            }
            $razones = $expediente->versionesPlantilla
                ->map(fn ($v) => PlantillasContratacion::razon($estados[$proceso->id][(int) $v->plantilla_id]))
                ->all();

            return [$proceso->id => [
                'expediente' => [
                    'id' => $expediente->id,
                    'estado' => $expediente->estado,
                    'progreso' => $avances[$expediente->id]['progreso'],
                    'aviso_plantilla' => $this->avisoPlantilla($razones),
                ],
                'avisos' => $avisos,
            ]];
        })->all();
    }
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (1 de 5):

```php
 *   intenta crear su expediente (ExpedientesContratacion::crearAutomatico), ya guardado el
 *   proceso: nunca interrumpe el guardado.
 * - presentar() trae el expediente del proceso resumido (estado, avance y aviso si su plantilla
 *   ya no le corresponde), o null sin expediente (ExpedientesContratacion::resumenDeProcesos).
 * - Todo queda en checklist_historial (entidad_tipo = proceso, sin expediente): al editar, solo
 *   lo que cambió; al eliminar, los datos para saber qué era.
 * - Los cambios sobre un proceso bloquean su fila: dos ediciones no se pisan en el historial.
```

por:

```php
 *   intenta crear su expediente (ExpedientesContratacion::crearAutomatico), ya guardado el
 *   proceso: nunca interrumpe el guardado.
 * - presentar() trae el expediente del proceso resumido (estado, avance y aviso si su plantilla
 *   ya no le corresponde), o null sin expediente, y los avisos del proceso (etapas vencidas y
 *   próximas, cierre, días sin actualización e hitos), también sin expediente
 *   (ExpedientesContratacion::resumenDeProcesos).
 * - Todo queda en checklist_historial (entidad_tipo = proceso, sin expediente): al editar, solo
 *   lo que cambió; al eliminar, los datos para saber qué era.
 * - Los cambios sobre un proceso bloquean su fila: dos ediciones no se pisan en el historial.
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (2 de 5):

```php
    /**
     * Los procesos del proyecto en el orden en que se crearon, con la forma de presentar(). Las
     * consultas no crecen con los procesos, tampoco las de sus expedientes.
     */
    public function deProyecto(Proyecto $proyecto): array
    {
        $procesos = ProcesoLicitacion::query()
            ->where('proyecto', $proyecto->id)
```

por:

```php
    /**
     * Los procesos del proyecto en el orden en que se crearon, con la forma de presentar(). Las
     * consultas no crecen con los procesos, tampoco las de sus expedientes.
     *
     * @param ?array $resumenes recibe los de ExpedientesContratacion::resumenDeProcesos() de estos
     *        procesos (proceso_id => resumen), para que quien también presenta sus contratos (la
     *        ficha del proyecto) no los vuelva a calcular
     */
    public function deProyecto(Proyecto $proyecto, ?array &$resumenes = null): array
    {
        $procesos = ProcesoLicitacion::query()
            ->where('proyecto', $proyecto->id)
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (3 de 5):

```php
            ->withCount('contratos')
            ->orderBy('id')
            ->get();
        $expedientes = $this->expedientes->resumenDeProcesos($procesos);

        return $procesos->map(fn (ProcesoLicitacion $proceso) => $this->forma($proceso, $expedientes[$proceso->id]))->all();
    }

    public function presentar(ProcesoLicitacion $proceso): array
```

por:

```php
            ->withCount('contratos')
            ->orderBy('id')
            ->get();
        $resumenes = $this->expedientes->resumenDeProcesos($procesos);

        return $procesos->map(fn (ProcesoLicitacion $proceso) => $this->forma($proceso, $resumenes[$proceso->id]))->all();
    }

    public function presentar(ProcesoLicitacion $proceso): array
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (4 de 5):

```php
        return $columnas;
    }

    /** @param ?array $expediente el de ExpedientesContratacion::resumenDeProcesos(); null sin expediente */
    private function forma(ProcesoLicitacion $proceso, ?array $expediente): array
    {
        $modalidad = $proceso->modalidadRel;
        $entidad = $proceso->entidadContratante;
```

por:

```php
        return $columnas;
    }

    /** @param array{expediente: ?array, avisos: array} $resumen el de ExpedientesContratacion::resumenDeProcesos() */
    private function forma(ProcesoLicitacion $proceso, array $resumen): array
    {
        $modalidad = $proceso->modalidadRel;
        $entidad = $proceso->entidadContratante;
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (5 de 5):

```php
            'observaciones' => $proceso->observaciones,
            'estado' => $proceso->estado,
            'contratos' => (int) $proceso->contratos_count,
            'expediente' => $expediente,
            'creado' => $proceso->created_at?->toIso8601String(),
            'actualizado' => $proceso->updated_at?->toIso8601String(),
            'usuario_creo' => $proceso->usuarioCreo?->name,
```

por:

```php
            'observaciones' => $proceso->observaciones,
            'estado' => $proceso->estado,
            'contratos' => (int) $proceso->contratos_count,
            'expediente' => $resumen['expediente'],
            'avisos' => $resumen['avisos'],
            'creado' => $proceso->created_at?->toIso8601String(),
            'actualizado' => $proceso->updated_at?->toIso8601String(),
            'usuario_creo' => $proceso->usuarioCreo?->name,
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 1):

```php
    }

    /**
     * Progreso ponderado de varios expedientes de contratación, con consultas que no crecen con
     * los expedientes: todas sus filas que cuentan se leen juntas. Espera cargadas sus
     * `versionesPlantilla`, que deciden el requisito que muestra cada fila.
     *
     * @param Collection<int, ProyectoExpediente> $expedientes
     * @return array<int, array{total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}> expediente_id => progreso
     */
    public function progresoPonderadoDe(Collection $expedientes): array
    {
        $filas = $expedientes->isEmpty() ? collect() : ProyectoRequisito::query()
            ->cuenta()
            ->whereIn('expediente_id', $expedientes->pluck('id'))
            ->with(['requisito', 'origenes.version:id,plantilla_id'])
            ->get()
            ->groupBy('expediente_id');

        return $expedientes->mapWithKeys(fn (ProyectoExpediente $e) => [$e->id => $this->progresoPonderado(
            $filas->get($e->id, collect()),
            $this->posiciones($this->plantillas->ordenar($e->versionesPlantilla))
        )])->all();
    }

    /**
```

por:

```php
    }

    /**
     * Progreso ponderado y avance por etapa (con sus fechas y su semáforo, el de
     * presentarCronograma()) de varios expedientes de contratación, con consultas que no crecen
     * con los expedientes: todas sus filas que aplican, con su etapa, y sus cronogramas se leen
     * juntos. Espera cargadas sus `versionesPlantilla`, que deciden el requisito que muestra
     * cada fila. No cambia nada.
     *
     * @param Collection<int, ProyectoExpediente> $expedientes
     * @return array<int, array{progreso: array{total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}, avance_por_etapa: list<array>}> expediente_id => avance
     */
    public function avanceDeVarios(Collection $expedientes): array
    {
        if ($expedientes->isEmpty()) {
            return [];
        }
        $ids = $expedientes->pluck('id');
        $filas = ProyectoRequisito::query()
            ->where('aplica', true)
            ->whereIn('expediente_id', $ids)
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
    }

    /**
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/AvisosProcesosApiTest.php tests/Feature/Contratacion/ContratosBusquedaApiTest.php tests/Feature/Contratacion/ProcesosContratacionApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1262 passed.

- [ ] **Paso 5: Commit**

```bash
git add tests/Feature/Contratacion/AvisosProcesosApiTest.php app/Http/Controllers/ProyectoController.php app/Services/Contratacion/ContratosService.php app/Services/Contratacion/ExpedientesContratacion.php app/Services/Contratacion/ProcesosContratacion.php app/Services/Viabilizacion/ExpedienteService.php tests/Feature/Contratacion/ContratosBusquedaApiTest.php tests/Feature/Contratacion/ProcesosContratacionApiTest.php
git commit -m "feat: process alerts in processes, the project page and contracts"
```

---

### Tarea 4: Fechas por etapa y avisos en las pantallas de contratación

En la página del expediente, la franja de avisos y, por etapa, las fechas editables (se guardan al salir del campo o con Enter), la marca "sugerida" y la etiqueta del semáforo. Los chips de avisos en la lista de procesos y en la ficha, y el aviso del proceso en el módulo Contratos.

**Files:**
- Create: `resources/js/components/ChipsAvisosProceso.jsx`
- Create: `resources/js/utils/avisosContratacion.js`
- Test (create): `resources/js/utils/avisosContratacion.test.js`
- Modify: `resources/css/Contratacion.css`
- Modify: `resources/css/ExpedienteContratacion.css`
- Modify: `resources/js/components/ExpedienteContratacion.jsx`
- Modify: `resources/js/components/FichaProyecto.jsx`
- Modify: `resources/js/components/ProcesosProyecto.jsx`
- Modify: `resources/js/components/contratos/ContratosModulo.jsx`
- Modify: `resources/js/utils/expedienteContratacion.js`
- Test: `resources/js/utils/expedienteContratacion.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `resources/js/utils/avisosContratacion.test.js`:

```js
import { describe, it, expect } from 'vitest';
import {
    avisoProcesoContrato, chipsAvisosProceso, etiquetaAlerta, franjaAvisosExpediente, textoCierre, textoSinActualizacion,
} from './avisosContratacion';

const avisos = (cambios = {}) => ({
    etapas_vencidas: 0,
    etapas_proximas: 0,
    cierre_proximo: false,
    cierre_vencido: false,
    dias_para_cierre: null,
    sin_actualizacion: false,
    dias_sin_actualizacion: 0,
    hitos: { completadas: 0, total: 0 },
    ...cambios,
});

const textos = (chips) => chips.map((c) => c.texto);

describe('etiquetaAlerta', () => {
    it('el semáforo de cada etapa, con su tono', () => {
        expect(etiquetaAlerta('vencida')).toEqual({ texto: 'Vencida', tono: 'coral' });
        expect(etiquetaAlerta('proxima')).toEqual({ texto: 'Próxima', tono: 'ambar' });
        expect(etiquetaAlerta('al_dia')).toEqual({ texto: 'Al día', tono: 'azul' });
        expect(etiquetaAlerta('completa')).toEqual({ texto: 'Completa', tono: 'verde' });
    });

    it('una etapa que no se completó en un expediente cerrado: "Sin completar", en neutro', () => {
        expect(etiquetaAlerta('sin_completar')).toEqual({ texto: 'Sin completar', tono: 'neutro' });
    });

    it('sin fecha (o un valor desconocido), sin etiqueta', () => {
        expect(etiquetaAlerta('sin_fecha')).toBeNull();
        expect(etiquetaAlerta(undefined)).toBeNull();
        expect(etiquetaAlerta('otra')).toBeNull();
    });
});

describe('textoCierre', () => {
    it('cierre próximo: en N días, en 1 día u hoy', () => {
        expect(textoCierre(avisos({ cierre_proximo: true, dias_para_cierre: 3 }))).toBe('Cierre en 3 días');
        expect(textoCierre(avisos({ cierre_proximo: true, dias_para_cierre: 1 }))).toBe('Cierre en 1 día');
        expect(textoCierre(avisos({ cierre_proximo: true, dias_para_cierre: 0 }))).toBe('Cierre hoy');
    });

    it('cierre vencido', () => {
        expect(textoCierre(avisos({ cierre_vencido: true, dias_para_cierre: -2 }))).toBe('Cierre vencido');
    });

    it('sin aviso de cierre, null (aunque tenga fecha lejana)', () => {
        expect(textoCierre(avisos({ dias_para_cierre: 20 }))).toBeNull();
        expect(textoCierre(undefined)).toBeNull();
    });
});

describe('textoSinActualizacion', () => {
    it('en singular y en plural', () => {
        expect(textoSinActualizacion(1)).toBe('Sin actualización hace 1 día');
        expect(textoSinActualizacion(20)).toBe('Sin actualización hace 20 días');
    });

    it('sin días conocidos, solo "Sin actualización"', () => {
        expect(textoSinActualizacion(null)).toBe('Sin actualización');
    });
});

describe('chipsAvisosProceso', () => {
    it('vencidas, próximas, cierre, sin actualización e hitos, en ese orden', () => {
        const chips = chipsAvisosProceso(avisos({
            etapas_vencidas: 2,
            etapas_proximas: 1,
            cierre_proximo: true,
            dias_para_cierre: 2,
            sin_actualizacion: true,
            dias_sin_actualizacion: 16,
            hitos: { completadas: 1, total: 4 },
        }));
        expect(textos(chips)).toEqual(['2 vencidas', '1 próxima', 'Cierre en 2 días', 'Sin actualización hace 16 días', 'Hitos 1/4']);
        expect(chips.map((c) => c.tono)).toEqual(['coral', 'ambar', 'ambar', 'neutro', 'neutro']);
        expect(chips.map((c) => c.clave)).toEqual(['vencidas', 'proximas', 'cierre', 'sin-actualizacion', 'hitos']);
    });

    it('cada chip explica su cifra con un título', () => {
        const chips = chipsAvisosProceso(avisos({ etapas_vencidas: 1, etapas_proximas: 3, hitos: { completadas: 2, total: 3 } }));
        expect(chips.map((c) => c.titulo)).toEqual([
            '1 etapa del expediente vencida',
            '3 etapas del expediente próximas a vencer',
            '2 de 3 etapas del expediente completas',
        ]);
    });

    it('singular y plural de vencidas y próximas', () => {
        expect(textos(chipsAvisosProceso(avisos({ etapas_vencidas: 1, etapas_proximas: 2 })))).toEqual(['1 vencida', '2 próximas']);
    });

    it('cierre vencido en coral y cierre hoy', () => {
        expect(chipsAvisosProceso(avisos({ cierre_vencido: true, dias_para_cierre: -1 }))).toEqual([
            { clave: 'cierre', texto: 'Cierre vencido', tono: 'coral', titulo: 'La fecha de cierre del proceso ya pasó' },
        ]);
        expect(textos(chipsAvisosProceso(avisos({ cierre_proximo: true, dias_para_cierre: 0 })))).toEqual(['Cierre hoy']);
    });

    it('con todas las etapas completas, los hitos van en verde', () => {
        expect(chipsAvisosProceso(avisos({ hitos: { completadas: 3, total: 3 } }))).toEqual([
            { clave: 'hitos', texto: 'Hitos 3/3', tono: 'verde', titulo: '3 de 3 etapas del expediente completas' },
        ]);
    });

    it('sin nada que avisar (ni hitos que contar), lista vacía', () => {
        expect(chipsAvisosProceso(avisos())).toEqual([]);
        expect(chipsAvisosProceso(avisos({ dias_sin_actualizacion: 30 }))).toEqual([]);
        expect(chipsAvisosProceso(undefined)).toEqual([]);
        expect(chipsAvisosProceso(null)).toEqual([]);
    });
});

describe('franjaAvisosExpediente', () => {
    it('los conteos en frases completas', () => {
        expect(textos(franjaAvisosExpediente(avisos({
            etapas_vencidas: 2,
            etapas_proximas: 1,
            cierre_proximo: true,
            dias_para_cierre: 3,
            sin_actualizacion: true,
            dias_sin_actualizacion: 15,
            hitos: { completadas: 1, total: 4 },
        })))).toEqual([
            '2 etapas vencidas',
            '1 etapa próxima a vencer',
            'El proceso cierra en 3 días',
            'Sin actualización hace 15 días',
        ]);
    });

    it('singular, cierre hoy y cierre vencido', () => {
        expect(textos(franjaAvisosExpediente(avisos({ etapas_vencidas: 1, etapas_proximas: 2, cierre_proximo: true, dias_para_cierre: 0 }))))
            .toEqual(['1 etapa vencida', '2 etapas próximas a vencer', 'El proceso cierra hoy']);
        expect(franjaAvisosExpediente(avisos({ cierre_vencido: true, dias_para_cierre: -4 }))).toEqual([
            { clave: 'cierre', texto: 'La fecha de cierre del proceso ya pasó', tono: 'coral' },
        ]);
        expect(textos(franjaAvisosExpediente(avisos({ cierre_proximo: true, dias_para_cierre: 1 })))).toEqual(['El proceso cierra en 1 día']);
    });

    it('los hitos no son un aviso: sin lo demás, lista vacía', () => {
        expect(franjaAvisosExpediente(avisos({ hitos: { completadas: 1, total: 3 } }))).toEqual([]);
        expect(franjaAvisosExpediente(undefined)).toEqual([]);
    });
});

describe('avisoProcesoContrato', () => {
    const aviso = (cambios = {}) => ({ proceso_id: 7, etapas_vencidas: 0, cierre_vencido: false, sin_actualizacion: false, dias_sin_actualizacion: 0, ...cambios });

    it('etapas vencidas, con el enlace al checklist del proceso', () => {
        expect(avisoProcesoContrato(aviso({ etapas_vencidas: 2 }))).toEqual({
            procesoId: 7,
            ruta: '/contratacion/7',
            textos: ['El proceso tiene 2 etapas vencidas'],
            texto: 'El proceso tiene 2 etapas vencidas',
        });
        expect(avisoProcesoContrato(aviso({ etapas_vencidas: 1 })).texto).toBe('El proceso tiene 1 etapa vencida');
    });

    it('cierre vencido y sin actualización, juntos si los hay', () => {
        expect(avisoProcesoContrato(aviso({ cierre_vencido: true })).texto).toBe('Cierre del proceso vencido');
        expect(avisoProcesoContrato(aviso({ sin_actualizacion: true, dias_sin_actualizacion: 1 })).texto).toBe('Proceso sin actualización hace 1 día');
        expect(avisoProcesoContrato(aviso({ sin_actualizacion: true, dias_sin_actualizacion: null })).texto).toBe('Proceso sin actualización');
        expect(avisoProcesoContrato(aviso({ etapas_vencidas: 1, cierre_vencido: true, sin_actualizacion: true, dias_sin_actualizacion: 20 })).textos).toEqual([
            'El proceso tiene 1 etapa vencida',
            'Cierre del proceso vencido',
            'Proceso sin actualización hace 20 días',
        ]);
        expect(avisoProcesoContrato(aviso({ etapas_vencidas: 1, cierre_vencido: true })).texto)
            .toBe('El proceso tiene 1 etapa vencida · Cierre del proceso vencido');
    });

    it('sin aviso, sin proceso o sin nada que avisar, null', () => {
        expect(avisoProcesoContrato(null)).toBeNull();
        expect(avisoProcesoContrato(undefined)).toBeNull();
        expect(avisoProcesoContrato(aviso())).toBeNull();
        expect(avisoProcesoContrato(aviso({ proceso_id: null, etapas_vencidas: 2 }))).toBeNull();
    });
});
```

En `resources/js/utils/expedienteContratacion.test.js`, reemplazar (1 de 2):

```js
    alternarPlantillaNueva,
    avanceEtapas,
    conAvance,
    datosPropioContratacion,
    errorDeProceso,
    esIdProceso,
    formularioPropioContratacion,
```

por:

```js
    alternarPlantillaNueva,
    avanceEtapas,
    conAvance,
    conEtapaActualizada,
    datosFechaEtapa,
    datosPropioContratacion,
    errorFechaEtapa,
    etapasConFechas,
    fechaCompleta,
    fechaHoyBogota,
    fechaPorGuardar,
    fechaLegible,
    fusionarAvance,
    fechasEtapa,
    errorDeProceso,
    esIdProceso,
    formularioPropioContratacion,
```

En `resources/js/utils/expedienteContratacion.test.js`, reemplazar (2 de 2):

```js
        expect(conAvance(expediente, {})).toEqual(expediente);
        expect(conAvance(expediente, undefined)).toEqual(expediente);
    });
});

describe('opcionesEtapaPropio', () => {
```

por:

```js
        expect(conAvance(expediente, {})).toEqual(expediente);
        expect(conAvance(expediente, undefined)).toEqual(expediente);
    });

    it('también toma los avisos, si la respuesta los trae', () => {
        const conAvisos = { ...expediente, avisos: { etapas_vencidas: 0 } };
        expect(conAvance(conAvisos, { avisos: { etapas_vencidas: 1 } }).avisos).toEqual({ etapas_vencidas: 1 });
        expect(conAvance(conAvisos, {}).avisos).toEqual({ etapas_vencidas: 0 });
    });
});

const avanceApi = (id, cambios = {}) => ({
    etapa: id === null ? null : etapa(id, id, `Etapa ${id}`),
    total: 2,
    cumplidos: 1,
    porcentaje: 50,
    fecha_programada: null,
    fecha_real: null,
    fecha_real_sugerida: false,
    completa: false,
    alerta: 'sin_fecha',
    ...cambios,
});

describe('fechasEtapa', () => {
    it('las fechas de una etapa como valores del input date, con su semáforo', () => {
        expect(fechasEtapa(avanceApi(3, { fecha_programada: '2026-11-18', alerta: 'vencida' }))).toEqual({
            etapaId: 3,
            conFechas: true,
            programada: '2026-11-18',
            real: '',
            sugerida: false,
            alerta: { texto: 'Vencida', tono: 'coral' },
        });
    });

    it('"sugerida" solo con una fecha real que puso el sistema', () => {
        expect(fechasEtapa(avanceApi(1, { fecha_real: '2026-11-19', fecha_real_sugerida: true, completa: true, alerta: 'completa' })))
            .toMatchObject({ real: '2026-11-19', sugerida: true, alerta: { texto: 'Completa', tono: 'verde' } });
        expect(fechasEtapa(avanceApi(1, { fecha_real: null, fecha_real_sugerida: true })).sugerida).toBe(false);
        expect(fechasEtapa(avanceApi(1, { fecha_real: '2026-11-19', fecha_real_sugerida: false })).sugerida).toBe(false);
    });

    it('sin fecha, sin etiqueta', () => {
        expect(fechasEtapa(avanceApi(1)).alerta).toBeNull();
    });

    it('el grupo sin etapa no tiene fechas', () => {
        expect(fechasEtapa(avanceApi(null, { alerta: 'completa' }))).toEqual({
            etapaId: null, conFechas: false, programada: '', real: '', sugerida: false, alerta: { texto: 'Completa', tono: 'verde' },
        });
    });

    it('una respuesta sin las claves nuevas no rompe', () => {
        expect(fechasEtapa({ etapa: etapa(2, 2), total: 1, cumplidos: 0, porcentaje: 0 })).toEqual({
            etapaId: 2, conFechas: true, programada: '', real: '', sugerida: false, alerta: null,
        });
    });
});

describe('etapasConFechas', () => {
    it('el avance de cada etapa con sus fechas', () => {
        const lista = etapasConFechas([avanceApi(1, { fecha_programada: '2026-11-21', alerta: 'proxima' }), avanceApi(null)]);
        expect(lista).toHaveLength(2);
        expect(lista[0]).toMatchObject({ clave: 'etapa-1', titulo: '1. Etapa 1', total: 2, cumplidos: 1, porcentaje: 50, etapaId: 1, programada: '2026-11-21' });
        expect(lista[0].alerta).toEqual({ texto: 'Próxima', tono: 'ambar' });
        expect(lista[1]).toMatchObject({ clave: 'sin-etapa', conFechas: false });
        expect(etapasConFechas(undefined)).toEqual([]);
    });
});

describe('fechaCompleta', () => {
    it('acepta vacío (borrar) y una fecha año-mes-día con año de cuatro cifras desde 1900', () => {
        expect(fechaCompleta('')).toBe(true);
        expect(fechaCompleta('2026-11-25')).toBe(true);
    });

    it('rechaza lo que el input da mientras se escribe el año', () => {
        expect(fechaCompleta('0002-11-25')).toBe(false);
        expect(fechaCompleta('0202-11-25')).toBe(false);
        expect(fechaCompleta('2026-13-01')).toBe(false);
        expect(fechaCompleta('25/11/2026')).toBe(false);
        expect(fechaCompleta(undefined)).toBe(false);
    });
});

describe('datosFechaEtapa', () => {
    it('solo el campo que cambió; vacío borra la fecha', () => {
        expect(datosFechaEtapa('fecha_programada', '2026-11-25')).toEqual({ fecha_programada: '2026-11-25' });
        expect(datosFechaEtapa('fecha_real', '')).toEqual({ fecha_real: null });
    });

    it('un campo desconocido no se envía', () => {
        expect(datosFechaEtapa('otra', '2026-11-25')).toEqual({});
    });
});

describe('conEtapaActualizada', () => {
    const expediente = {
        id: 5,
        avance_por_etapa: [avanceApi(1), avanceApi(2), avanceApi(null)],
        avisos: { etapas_vencidas: 0 },
    };

    it('reemplaza la etapa que respondió el PUT y pone los avisos nuevos', () => {
        const etapaNueva = avanceApi(2, { fecha_programada: '2026-11-10', alerta: 'vencida' });
        const nuevo = conEtapaActualizada(expediente, { etapa: etapaNueva, avisos: { etapas_vencidas: 1 } });
        expect(nuevo.avance_por_etapa).toEqual([avanceApi(1), etapaNueva, avanceApi(null)]);
        expect(nuevo.avisos).toEqual({ etapas_vencidas: 1 });
        expect(expediente.avance_por_etapa[1].alerta).toBe('sin_fecha');
    });

    it('sin la etapa en el avance, solo los avisos', () => {
        const nuevo = conEtapaActualizada(expediente, { etapa: avanceApi(9), avisos: { etapas_vencidas: 2 } });
        expect(nuevo.avance_por_etapa).toEqual(expediente.avance_por_etapa);
        expect(nuevo.avisos).toEqual({ etapas_vencidas: 2 });
    });

    it('sin expediente o sin respuesta, como estaba', () => {
        expect(conEtapaActualizada(null, { etapa: avanceApi(1) })).toBeNull();
        expect(conEtapaActualizada(expediente, undefined)).toEqual(expediente);
    });
});

describe('errorFechaEtapa', () => {
    it('el error del campo que envió la validación', () => {
        const error = { response: { status: 422, data: { message: 'x', errors: { fecha_real: ['Escribe la fecha real como año-mes-día.'] } } } };
        expect(errorFechaEtapa(error, 'fecha_real')).toBe('Escribe la fecha real como año-mes-día.');
    });

    it('la regla de negocio con el campo, sin repetirla', () => {
        const error = { response: { status: 422, data: {
            mensaje: 'La fecha real no puede ser posterior a hoy.',
            errores: ['La fecha real no puede ser posterior a hoy.'],
            errors: { fecha_real: ['La fecha real no puede ser posterior a hoy.'] },
        } } };
        expect(errorFechaEtapa(error, 'fecha_real')).toBe('La fecha real no puede ser posterior a hoy.');
    });

    it('un rechazo sin campo (expediente cerrado) o de otro tipo, con el mensaje de la API', () => {
        expect(errorFechaEtapa({ response: { status: 422, data: { mensaje: 'El expediente está cerrado.' } } }, 'fecha_programada'))
            .toBe('El expediente está cerrado.');
        expect(errorFechaEtapa({ response: { status: 403 } }, 'fecha_real')).toBe('No tienes permiso para esta acción.');
    });
});

describe('fechaHoyBogota', () => {
    it('el día de Bogotá, no el de UTC: a las 20:00 de Bogotá (01:00 UTC) sigue siendo el mismo día', () => {
        expect(fechaHoyBogota(new Date('2026-11-20T01:00:00Z'))).toBe('2026-11-19');
        expect(fechaHoyBogota(new Date('2026-11-20T04:59:00Z'))).toBe('2026-11-19');
    });

    it('a las 00:00 de Bogotá (05:00 UTC) cambia el día', () => {
        expect(fechaHoyBogota(new Date('2026-11-20T05:00:00Z'))).toBe('2026-11-20');
        expect(fechaHoyBogota(new Date('2026-01-05T12:00:00Z'))).toBe('2026-01-05');
    });
});

describe('fechaLegible', () => {
    it('año-mes-día como día/mes/año, sin pasar por zonas horarias', () => {
        expect(fechaLegible('2026-11-19')).toBe('19/11/2026');
        expect(fechaLegible('2026-01-05')).toBe('05/01/2026');
    });

    it('sin fecha, "Sin fecha"', () => {
        expect(fechaLegible('')).toBe('Sin fecha');
        expect(fechaLegible(null)).toBe('Sin fecha');
        expect(fechaLegible(undefined)).toBe('Sin fecha');
    });
});

describe('fusionarAvance (respuesta del checklist que llega después de guardar una fecha)', () => {
    const actual = [
        avanceApi(1, { fecha_programada: '2026-12-01', fecha_real: '2026-11-18', alerta: 'al_dia' }),
        avanceApi(2, { fecha_programada: '2026-12-05', alerta: 'al_dia' }),
    ];
    const vieja = [
        avanceApi(1, { cumplidos: 2, porcentaje: 100, fecha_programada: '2026-11-01', fecha_real: null, alerta: 'vencida' }),
        avanceApi(2, { cumplidos: 2, porcentaje: 100, fecha_programada: '2026-11-02', alerta: 'vencida' }),
    ];

    it('sin etapas protegidas, lo que llega', () => {
        expect(fusionarAvance(actual, vieja, new Set())).toEqual(vieja);
        expect(fusionarAvance(actual, vieja)).toEqual(vieja);
    });

    it('en una etapa protegida (fecha guardada hace poco) conserva las fechas y el semáforo, y toma el avance nuevo', () => {
        const r = fusionarAvance(actual, vieja, new Set([1]));
        expect(r[0]).toMatchObject({ cumplidos: 2, porcentaje: 100, fecha_programada: '2026-12-01', fecha_real: '2026-11-18', alerta: 'al_dia' });
        expect(r[1]).toEqual(vieja[1]);
    });

    it('si la etapa se completó o dejó de estarlo, la fecha real (sugerida o borrada) es la que llega', () => {
        const completada = [avanceApi(1, { completa: true, fecha_programada: '2026-11-01', fecha_real: '2026-11-19', fecha_real_sugerida: true, alerta: 'completa' })];
        const r = fusionarAvance([actual[0]], completada, new Set([1]));
        expect(r[0]).toMatchObject({ fecha_programada: '2026-12-01', fecha_real: '2026-11-19', fecha_real_sugerida: true, alerta: 'completa' });
    });

    it('el grupo sin etapa y las etapas nuevas pasan tal cual', () => {
        const r = fusionarAvance(actual, [avanceApi(null), avanceApi(9)], new Set([1]));
        expect(r).toEqual([avanceApi(null), avanceApi(9)]);
    });

    it('sin lista que llegue, la actual', () => {
        expect(fusionarAvance(actual, undefined, new Set([1]))).toBe(actual);
    });
});

describe('conAvance con etapas protegidas', () => {
    it('aplica fusionarAvance al avance por etapa', () => {
        const expediente = { avance_por_etapa: [avanceApi(1, { fecha_programada: '2026-12-01' })], progreso: { cumplidos: 0 } };
        const datos = { avance_por_etapa: [avanceApi(1, { fecha_programada: '2026-11-01' })], progreso: { cumplidos: 1 } };
        expect(conAvance(expediente, datos, new Set([1])).avance_por_etapa[0].fecha_programada).toBe('2026-12-01');
        expect(conAvance(expediente, datos).avance_por_etapa[0].fecha_programada).toBe('2026-11-01');
    });
});

describe('fechaPorGuardar', () => {
    it('nada si el borrador es lo ya guardado', () => {
        expect(fechaPorGuardar('2026-11-19', '2026-11-19')).toBe('nada');
    });

    it('error si es una fecha a medio escribir', () => {
        expect(fechaPorGuardar('0002-11-19', '2026-11-19')).toBe('incompleta');
    });

    it('guardar si cambió y está completa (vacía borra)', () => {
        expect(fechaPorGuardar('2026-11-20', '2026-11-19')).toBe('guardar');
        expect(fechaPorGuardar('', '2026-11-19')).toBe('guardar');
    });
});

describe('opcionesEtapaPropio', () => {
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/avisosContratacion.test.js resources/js/utils/expedienteContratacion.test.js`
Expected: FALLAN los 2 archivos, 30 pruebas, y pasan 39: `avisosContratacion.test.js` no carga porque no existe `utils/avisosContratacion.js` ("Cannot find module './avisosContratacion'"), y en `expedienteContratacion.test.js` fallan las 30 que usan lo nuevo, como `fechasEtapa` ("fechasEtapa is not a function") y la toma de `avisos` de la respuesta.

- [ ] **Paso 3: Implementar**

Crear `resources/js/components/ChipsAvisosProceso.jsx`:

```jsx
import React from 'react';
import { chipsAvisosProceso } from '../utils/avisosContratacion';

/**
 * Los chips de avisos de un proceso (`avisos` de la API): etapas vencidas y próximas, cierre, sin
 * actualización e hitos. Los usan el diálogo de procesos del proyecto y la ficha. Sin nada que
 * mostrar, no pinta nada.
 */
const ChipsAvisosProceso = ({ avisos }) => {
    const chips = chipsAvisosProceso(avisos);
    if (chips.length === 0) return null;
    return (
        <ul className="ct-avisos" aria-label="Avisos del proceso">
            {chips.map((c) => (
                <li key={c.clave} className={`ct-estado ct-estado-${c.tono}`} title={c.titulo}>
                    {c.texto}<span className="ct-oculto"> ({c.titulo})</span>
                </li>
            ))}
        </ul>
    );
};

export default ChipsAvisosProceso;
```

Crear `resources/js/utils/avisosContratacion.js`:

```js
// Avisos de contratación (fase 4B): el semáforo de cada etapa del expediente, la franja de avisos
// de la página del expediente, los chips de cada proceso (diálogo de procesos y ficha del
// proyecto) y el aviso del proceso en el módulo Contratos. Todo sale de lo que calcula el backend
// (`alerta`, `avisos`, `aviso_proceso`): aquí no se mira la fecha de hoy. Funciones puras,
// probadas en avisosContratacion.test.js.

import { rutaExpedienteContratacion } from './navegacion';

const ALERTAS = {
    vencida: { texto: 'Vencida', tono: 'coral' },
    proxima: { texto: 'Próxima', tono: 'ambar' },
    al_dia: { texto: 'Al día', tono: 'azul' },
    completa: { texto: 'Completa', tono: 'verde' },
    sin_completar: { texto: 'Sin completar', tono: 'neutro' },
};

/** La etiqueta del semáforo de una etapa (`alerta` de la API); sin fecha, ninguna. */
export const etiquetaAlerta = (alerta) => (ALERTAS[alerta] ? { ...ALERTAS[alerta] } : null);

const numero = (valor) => Number(valor) || 0;
const dias = (n) => (n === 1 ? '1 día' : `${n} días`);

/** "Cierre en N días", "Cierre hoy" o "Cierre vencido"; null si el cierre no está cerca ni pasó. */
export const textoCierre = (avisos) => {
    if (avisos?.cierre_vencido) return 'Cierre vencido';
    if (!avisos?.cierre_proximo) return null;
    const n = numero(avisos.dias_para_cierre);
    return n <= 0 ? 'Cierre hoy' : `Cierre en ${dias(n)}`;
};

/** "Sin actualización hace N días" (sin los días, solo "Sin actualización"). */
export const textoSinActualizacion = (n) => (n === null || n === undefined ? 'Sin actualización' : `Sin actualización hace ${dias(n)}`);

/**
 * Los chips de un proceso desde su `avisos`: vencidas, próximas, cierre, sin actualización e
 * hitos (si hay etapas que contar), cada uno con su tono de `ct-estado-*` y un título que explica
 * la cifra. Lista vacía si no hay nada que mostrar.
 */
export const chipsAvisosProceso = (avisos) => {
    if (!avisos) return [];
    const chips = [];
    const vencidas = numero(avisos.etapas_vencidas);
    if (vencidas > 0) {
        chips.push({
            clave: 'vencidas',
            texto: vencidas === 1 ? '1 vencida' : `${vencidas} vencidas`,
            tono: 'coral',
            titulo: vencidas === 1 ? '1 etapa del expediente vencida' : `${vencidas} etapas del expediente vencidas`,
        });
    }
    const proximas = numero(avisos.etapas_proximas);
    if (proximas > 0) {
        chips.push({
            clave: 'proximas',
            texto: proximas === 1 ? '1 próxima' : `${proximas} próximas`,
            tono: 'ambar',
            titulo: proximas === 1 ? '1 etapa del expediente próxima a vencer' : `${proximas} etapas del expediente próximas a vencer`,
        });
    }
    const cierre = textoCierre(avisos);
    if (cierre) {
        chips.push({
            clave: 'cierre',
            texto: cierre,
            tono: avisos.cierre_vencido ? 'coral' : 'ambar',
            titulo: avisos.cierre_vencido ? 'La fecha de cierre del proceso ya pasó' : 'La fecha de cierre del proceso está cerca',
        });
    }
    if (avisos.sin_actualizacion) {
        chips.push({
            clave: 'sin-actualizacion',
            texto: textoSinActualizacion(avisos.dias_sin_actualizacion),
            tono: 'neutro',
            titulo: 'Sin cambios en el proceso ni en su expediente',
        });
    }
    const completadas = numero(avisos.hitos?.completadas);
    const total = numero(avisos.hitos?.total);
    if (total > 0) {
        chips.push({
            clave: 'hitos',
            texto: `Hitos ${completadas}/${total}`,
            tono: completadas >= total ? 'verde' : 'neutro',
            titulo: `${completadas} de ${total} etapas del expediente completas`,
        });
    }
    return chips;
};

/**
 * La franja de avisos de la página del expediente (`avisos` del expediente), en frases
 * completas. Los hitos no son un aviso: no van. Lista vacía si no hay nada que avisar.
 */
export const franjaAvisosExpediente = (avisos) => {
    if (!avisos) return [];
    const franja = [];
    const vencidas = numero(avisos.etapas_vencidas);
    if (vencidas > 0) {
        franja.push({ clave: 'vencidas', texto: vencidas === 1 ? '1 etapa vencida' : `${vencidas} etapas vencidas`, tono: 'coral' });
    }
    const proximas = numero(avisos.etapas_proximas);
    if (proximas > 0) {
        franja.push({
            clave: 'proximas',
            texto: proximas === 1 ? '1 etapa próxima a vencer' : `${proximas} etapas próximas a vencer`,
            tono: 'ambar',
        });
    }
    if (avisos.cierre_vencido) {
        franja.push({ clave: 'cierre', texto: 'La fecha de cierre del proceso ya pasó', tono: 'coral' });
    } else if (avisos.cierre_proximo) {
        const n = numero(avisos.dias_para_cierre);
        franja.push({ clave: 'cierre', texto: n <= 0 ? 'El proceso cierra hoy' : `El proceso cierra en ${dias(n)}`, tono: 'ambar' });
    }
    if (avisos.sin_actualizacion) {
        franja.push({ clave: 'sin-actualizacion', texto: textoSinActualizacion(avisos.dias_sin_actualizacion), tono: 'neutro' });
    }
    return franja;
};

/**
 * El aviso de un contrato cuyo proceso tiene etapas vencidas, el cierre vencido o está sin
 * actualización (`aviso_proceso` de la API), con el enlace al checklist del proceso. Null si no
 * hay nada que avisar.
 */
export const avisoProcesoContrato = (aviso) => {
    if (!aviso || aviso.proceso_id === null || aviso.proceso_id === undefined) return null;
    const textos = [];
    const vencidas = numero(aviso.etapas_vencidas);
    if (vencidas > 0) textos.push(`El proceso tiene ${vencidas === 1 ? '1 etapa vencida' : `${vencidas} etapas vencidas`}`);
    if (aviso.cierre_vencido) textos.push('Cierre del proceso vencido');
    if (aviso.sin_actualizacion) {
        const n = aviso.dias_sin_actualizacion;
        textos.push(n === null || n === undefined ? 'Proceso sin actualización' : `Proceso sin actualización hace ${dias(n)}`);
    }
    if (textos.length === 0) return null;
    return {
        procesoId: aviso.proceso_id,
        ruta: rutaExpedienteContratacion(aviso.proceso_id),
        textos,
        texto: textos.join(' · '),
    };
};
```

En `resources/css/Contratacion.css`, reemplazar (1 de 1):

```css
.ct-estado-verde-pleno { color: var(--gp-sobre-acento); background: var(--gp-verde-texto); }
.ct-estado-coral { color: var(--gp-coral); background: var(--gp-coral-suave); }

/* ---------- Módulo Contratos (/contratos) ---------- */

.ct-filtro { flex: 0 1 13rem; }
```

por:

```css
.ct-estado-verde-pleno { color: var(--gp-sobre-acento); background: var(--gp-verde-texto); }
.ct-estado-coral { color: var(--gp-coral); background: var(--gp-coral-suave); }

/* Avisos de un proceso (vencidas, próximas, cierre, sin actualización, hitos), como chips. */
.ct-avisos { display: flex; flex-wrap: wrap; gap: .35rem; margin: 0; padding: 0; list-style: none; }
/* En la lista va dentro de la celda del proceso, que no parte líneas: el aviso sí, en su propio renglón. */
.ct-aviso-proceso { display: flex; flex-wrap: wrap; gap: .3rem; align-items: baseline; min-width: 11rem; max-width: 14rem; margin-top: .25rem; font-family: var(--gp-fuente); font-size: .8rem; white-space: normal; }
.ct-aviso-proceso .ct-estado { flex-shrink: 1; min-width: 0; border-radius: .5rem; white-space: normal; text-align: left; }

/* ---------- Módulo Contratos (/contratos) ---------- */

.ct-filtro { flex: 0 1 13rem; }
```

En `resources/css/ExpedienteContratacion.css`, reemplazar (1 de 1):

```css
.exc-plantillas label { display: flex; gap: .6rem; align-items: flex-start; cursor: pointer; font-size: .9rem; }
.exc-plantillas label small { display: block; color: var(--exv-tinta-suave); }
.exc-principal { margin-left: .5rem; }
```

por:

```css
.exc-plantillas label { display: flex; gap: .6rem; align-items: flex-start; cursor: pointer; font-size: .9rem; }
.exc-plantillas label small { display: block; color: var(--exv-tinta-suave); }
.exc-principal { margin-left: .5rem; }

/* Cronograma por etapa (fase 4B): semáforo, fecha programada y fecha real bajo cada barra. */
.exc-etapa { display: grid; gap: .5rem; align-content: start; min-width: 0; }
.exc-etapa-fechas { display: flex; flex-wrap: wrap; gap: .5rem .9rem; align-items: flex-end; }
.exc-alerta { align-self: center; }
.exc-fecha { display: grid; gap: .2rem; min-width: 0; }
.exc-fecha label, .exc-fecha-etiqueta { font-size: .74rem; color: var(--exv-tinta-tenue); }
.exc-fecha-texto { font-size: .85rem; color: var(--exv-tinta); font-variant-numeric: tabular-nums; }
.exc-fecha-control { display: inline-flex; gap: .4rem; align-items: center; }
.exc-fecha input[type="date"] {
    max-width: 100%;
    padding: .25rem .4rem;
    font: inherit;
    font-size: .85rem;
    color: var(--exv-tinta);
    background: var(--exv-tarjeta);
    border: 1px solid var(--exv-linea);
    border-radius: var(--exv-radio);
}
.exc-fecha input[type="date"]:disabled { color: var(--exv-tinta-suave); background: transparent; border-style: dashed; cursor: default; }
.exc-fecha input[aria-invalid="true"] { border-color: var(--gp-coral); }
.exc-sugerida { font-size: .74rem; font-style: italic; color: var(--exv-tinta-suave); }

/* Franja de avisos del cronograma, arriba del avance por etapa. */
.exc-avisos {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem .75rem;
    align-items: center;
    margin: 0 0 1rem;
    padding: .65rem .9rem;
    background: var(--exv-tarjeta);
    border: 1px solid var(--exv-linea-suave);
    border-left: 3px solid var(--gp-ambar);
    border-radius: var(--exv-radio);
    font-size: .88rem;
}
.exc-avisos ul { display: flex; flex-wrap: wrap; gap: .4rem; margin: 0; padding: 0; list-style: none; }
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (1 de 10):

```jsx
    contarOpcionales, motivoNoEliminarPropio, plantillasConVersion, requiereAprobacionNoAplica, textoOpcionales,
} from '../utils/expedienteChecklist';
import {
    alternarPlantillaNueva, avanceEtapas, avisoIncompatiblesProceso, conAvance, datosPropioContratacion, errorDeProceso,
    esIdProceso, htmlPendientesCierre, NOTA_PONDERADO, pondera, seleccionInicial, textoEstadoExpediente, textoSinPlantillas,
} from '../utils/expedienteContratacion';
import { etiquetaEstadoProceso, numeroProceso, tonoEstado } from '../utils/contratacion';
import { RUTA_PLANTILLAS, rutaFichaProyecto } from '../utils/navegacion';
import { avisarError } from '../utils/avisosPlantillas';
```

por:

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
import { RUTA_PLANTILLAS, rutaFichaProyecto } from '../utils/navegacion';
import { avisarError } from '../utils/avisosPlantillas';
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (2 de 10):

```jsx
    );
};

/** Página con el botón de volver: la usan la carga, los errores y el expediente. */
const Pagina = ({ children }) => {
    const navigate = useNavigate();
```

por:

```jsx
    );
};

const ETIQUETAS_FECHA = { fecha_programada: 'Fecha programada', fecha_real: 'Fecha real' };

/**
 * Una fecha de la etapa (programada o real) en un input date. Se guarda al salir del campo o con
 * Enter, si cambió y es una fecha completa (mientras se escribe el año, el input da valores como
 * 0002-11-25). El error del servidor va bajo el campo; el estado del guardado, en la región viva.
 */
const CampoFecha = ({ campo, valor, max, deshabilitado, sugerida, onGuardar }) => {
    const id = useId();
    const [borrador, setBorrador] = useState(valor);
    const [error, setError] = useState(null);
    // Refs, no estados: Enter y luego salir del campo no deben enviar dos veces, y un segundo
    // cambio mientras se guarda el primero se guarda después en vez de perderse.
    const guardando = useRef(false);
    const pendiente = useRef(false);
    const ultimo = useRef(valor);
    const guardado = useRef(valor);
    // Lo que diga el servidor (esta respuesta u otra acción del checklist) reemplaza el borrador,
    // salvo mientras se guarda: ahí el borrador puede ser un cambio más nuevo.
    useEffect(() => {
        guardado.current = valor;
        if (!guardando.current) { ultimo.current = valor; setBorrador(valor); }
    }, [valor]);

    const cambiar = (nuevo) => { ultimo.current = nuevo; setBorrador(nuevo); setError(null); };

    const guardar = async () => {
        if (guardando.current) { pendiente.current = true; return; }
        guardando.current = true;
        try {
            let repetir = true;
            while (repetir) {
                pendiente.current = false;
                const quiere = ultimo.current;
                const accion = fechaPorGuardar(quiere, guardado.current);
                if (accion === 'incompleta') setError('Escribe una fecha completa (día, mes y año).');
                if (accion !== 'guardar') break;
                // eslint-disable-next-line no-await-in-loop
                const mensaje = await onGuardar(campo, quiere);
                if (mensaje) {
                    setError(mensaje);
                    ultimo.current = guardado.current;
                    setBorrador(guardado.current);
                    break;
                }
                guardado.current = quiere;
                repetir = pendiente.current;
            }
        } finally {
            guardando.current = false;
        }
    };

    const idError = `${id}-error`;
    const idSugerida = `${id}-sugerida`;
    const descripcion = [error && idError, sugerida && idSugerida].filter(Boolean).join(' ') || undefined;
    const marcaSugerida = sugerida && (
        <span id={idSugerida} className="exc-sugerida" title="La propuso el sistema al completarse la etapa: puedes corregirla.">
            sugerida
        </span>
    );

    // Solo lectura (expediente cerrado o sin permiso de edición): la fecha como texto, no un input apagado.
    if (deshabilitado) {
        return (
            <div className="exc-fecha">
                <span className="exc-fecha-etiqueta">{ETIQUETAS_FECHA[campo]}</span>
                <span className="exc-fecha-control">
                    <span className="exc-fecha-texto">{fechaLegible(valor)}</span>
                    {marcaSugerida}
                </span>
            </div>
        );
    }

    return (
        <div className="exc-fecha">
            <label htmlFor={id}>{ETIQUETAS_FECHA[campo]}</label>
            <span className="exc-fecha-control">
                <input
                    id={id}
                    type="date"
                    value={borrador}
                    max={max}
                    aria-invalid={Boolean(error)}
                    aria-describedby={descripcion}
                    onChange={(e) => cambiar(e.target.value)}
                    onBlur={guardar}
                    onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); guardar(); } }}
                />
                {marcaSugerida}
            </span>
            {error && <small id={idError} className="ct-error-campo">{error}</small>}
        </div>
    );
};

/** El avance de una etapa con su semáforo y, si es una etapa del catálogo, sus fechas. */
const EtapaCronograma = ({ etapa, editable, hoy, onGuardarFecha }) => {
    const guardar = (campo, valor) => onGuardarFecha(etapa, campo, valor);
    return (
        <div className="exc-etapa">
            <Medidor titulo={etapa.titulo} cumplidos={etapa.cumplidos} total={etapa.total} porcentaje={etapa.porcentaje} />
            {(etapa.alerta || etapa.conFechas) && (
                <div className="exc-etapa-fechas">
                    {etapa.alerta && (
                        <span className={`ct-estado ct-estado-${etapa.alerta.tono} exc-alerta`}>{etapa.alerta.texto}</span>
                    )}
                    {etapa.conFechas && (
                        <>
                            <CampoFecha campo="fecha_programada" valor={etapa.programada} deshabilitado={!editable} onGuardar={guardar} />
                            <CampoFecha
                                campo="fecha_real"
                                valor={etapa.real}
                                max={hoy}
                                deshabilitado={!editable}
                                sugerida={etapa.sugerida}
                                onGuardar={guardar}
                            />
                        </>
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
        </div>
    );
};

/** Página con el botón de volver: la usan la carga, los errores y el expediente. */
const Pagina = ({ children }) => {
    const navigate = useNavigate();
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (3 de 10):

```jsx
    const [historialAbierto, setHistorialAbierto] = useState(false);
    // Sube con cada cambio que el servidor ya registró: el historial abierto se vuelve a pedir.
    const [revision, setRevision] = useState(0);
    const nuevaRevision = useCallback(() => setRevision((r) => r + 1), []);
    const [ocupado, ejecutar] = useOcupado();
```

por:

```jsx
    const [historialAbierto, setHistorialAbierto] = useState(false);
    // Sube con cada cambio que el servidor ya registró: el historial abierto se vuelve a pedir.
    const [revision, setRevision] = useState(0);
    // El texto de la región viva: el resultado del último guardado de una fecha.
    const [anuncio, setAnuncio] = useState('');
    const nuevaRevision = useCallback(() => setRevision((r) => r + 1), []);
    const [ocupado, ejecutar] = useOcupado();
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (4 de 10):

```jsx
        return () => { montado.current = false; turno.current += 1; };
    }, []);

    const ponerExpediente = useCallback((data) => {
        turno.current += 1;
        setExpediente(data);
        nuevaRevision();
    }, [nuevaRevision]);
```

por:

```jsx
        return () => { montado.current = false; turno.current += 1; };
    }, []);

    // Etapas cuya fecha se guardó en esta pantalla: sus fechas no las repone una respuesta atrasada del checklist.
    const etapasProtegidas = useRef(new Set());

    const ponerExpediente = useCallback((data) => {
        turno.current += 1;
        etapasProtegidas.current.clear();
        setExpediente(data);
        nuevaRevision();
    }, [nuevaRevision]);
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (5 de 10):

```jsx
            if (!alDia()) return;
        }
        setDisponibles(datos);
        setExpediente(datosExpediente);
        // Lo elegido se conserva entre recargas; la primera vez, si hay una sola plantilla, va elegida.
        setSeleccion((prev) => prev ?? seleccionInicial(datos.plantillas));
```

por:

```jsx
            if (!alDia()) return;
        }
        setDisponibles(datos);
        etapasProtegidas.current.clear();
        setExpediente(datosExpediente);
        // Lo elegido se conserva entre recargas; la primera vez, si hay una sola plantilla, va elegida.
        setSeleccion((prev) => prev ?? seleccionInicial(datos.plantillas));
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (6 de 10):

```jsx
    // respondió el servidor, que ya quedó en el historial. `datos` trae progreso y avance por etapa.
    const pintarFila = (fila, datos) => setExpediente((prev) => (
        prev?.requisitos.some((f) => f.id === fila.id)
            ? { ...conAvance(prev, datos), requisitos: prev.requisitos.map((f) => (f.id === fila.id ? fila : f)) }
            : prev
    ));
```

por:

```jsx
    // respondió el servidor, que ya quedó en el historial. `datos` trae progreso y avance por etapa.
    const pintarFila = (fila, datos) => setExpediente((prev) => (
        prev?.requisitos.some((f) => f.id === fila.id)
            ? { ...conAvance(prev, datos, etapasProtegidas.current), requisitos: prev.requisitos.map((f) => (f.id === fila.id ? fila : f)) }
            : prev
    ));
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (7 de 10):

```jsx
    // Un propio nuevo va al final: el backend ordena los propios después de toda plantilla, y la
    // agrupación lo pone en su etapa.
    const agregarFila = (expedienteId, fila, datos) => {
        setExpediente((prev) => (prev?.id === expedienteId ? { ...conAvance(prev, datos), requisitos: [...prev.requisitos, fila] } : prev));
        nuevaRevision();
    };

    const quitarFila = (fila, datos) => {
        setExpediente((prev) => (
            prev?.requisitos.some((f) => f.id === fila.id)
                ? { ...conAvance(prev, datos), requisitos: prev.requisitos.filter((f) => f.id !== fila.id) }
                : prev
        ));
        nuevaRevision();
```

por:

```jsx
    // Un propio nuevo va al final: el backend ordena los propios después de toda plantilla, y la
    // agrupación lo pone en su etapa.
    const agregarFila = (expedienteId, fila, datos) => {
        setExpediente((prev) => (prev?.id === expedienteId ? { ...conAvance(prev, datos, etapasProtegidas.current), requisitos: [...prev.requisitos, fila] } : prev));
        nuevaRevision();
    };

    const quitarFila = (fila, datos) => {
        setExpediente((prev) => (
            prev?.requisitos.some((f) => f.id === fila.id)
                ? { ...conAvance(prev, datos, etapasProtegidas.current), requisitos: prev.requisitos.filter((f) => f.id !== fila.id) }
                : prev
        ));
        nuevaRevision();
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (8 de 10):

```jsx
        await accionFila(() => axios.post(`${urlFila(fila)}/aplica`), 'No se pudo indicar que el requisito aplica');
    });

    const urlPropios = () => `/expedientes/${expediente.id}/requisitos-propios`;

    /** Devuelve true si se guardó, para que el formulario se cierre. */
```

por:

```jsx
        await accionFila(() => axios.post(`${urlFila(fila)}/aplica`), 'No se pudo indicar que el requisito aplica');
    });

    /** Guarda una fecha de la etapa: devuelve null si se guardó, o el mensaje que va bajo el campo. */
    const guardarFecha = async (etapa, campo, valor) => {
        const id = expediente.id;
        const nombre = `${ETIQUETAS_FECHA[campo]} de ${etapa.titulo}`;
        setAnuncio(`Guardando: ${nombre}…`);
        try {
            const { data } = await axios.put(`/expedientes/${id}/etapas/${etapa.etapaId}`, datosFechaEtapa(campo, valor));
            if (!montado.current) return null;
            // Su fecha ya es la más nueva: una respuesta del checklist que salió antes no la repone.
            etapasProtegidas.current.add(etapa.etapaId);
            setExpediente((prev) => (prev?.id === id ? conEtapaActualizada(prev, data) : prev));
            nuevaRevision();
            setAnuncio(`Guardada: ${nombre}.`);
            return null;
        } catch (e) {
            const mensaje = errorFechaEtapa(e, campo);
            if (montado.current) setAnuncio(`No se pudo guardar: ${nombre}. ${mensaje}`);
            return mensaje;
        }
    };

    const urlPropios = () => `/expedientes/${expediente.id}/requisitos-propios`;

    /** Devuelve true si se guardó, para que el formulario se cierre. */
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (9 de 10):

```jsx
    const numero = numeroProceso(proceso);
    const { cumplidos = 0, total = 0, porcentaje = 0 } = expediente?.progreso ?? {};
    const avisoOpcionales = textoOpcionales(contarOpcionales(expediente?.requisitos));
    const etapasAvance = avanceEtapas(expediente?.avance_por_etapa);
    const cerrado = expediente?.estado === 'cerrado';
    const incompatibles = avisoIncompatiblesProceso(expediente?.plantillas);
    // Uno cerrado tiene el checklist congelado: no admite otra versión, y no se la ofrece.
```

por:

```jsx
    const numero = numeroProceso(proceso);
    const { cumplidos = 0, total = 0, porcentaje = 0 } = expediente?.progreso ?? {};
    const avisoOpcionales = textoOpcionales(contarOpcionales(expediente?.requisitos));
    const etapasAvance = etapasConFechas(expediente?.avance_por_etapa);
    const hoy = fechaHoyBogota(new Date());
    const cerrado = expediente?.estado === 'cerrado';
    const incompatibles = avisoIncompatiblesProceso(expediente?.plantillas);
    // Uno cerrado tiene el checklist congelado: no admite otra versión, y no se la ofrece.
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (10 de 10):

```jsx
                        </div>
                    </div>

                    {etapasAvance.length > 0 && (
                        <section className="exc-etapas" aria-label="Avance por etapa">
                            {etapasAvance.map((e) => (
                                <Medidor key={e.clave} titulo={e.titulo} cumplidos={e.cumplidos} total={e.total} porcentaje={e.porcentaje} />
                            ))}
                        </section>
                    )}

                    {cerrado && (
                        <div className="exv-nota">
```

por:

```jsx
                        </div>
                    </div>

                    <FranjaAvisos avisos={expediente.avisos} />

                    {etapasAvance.length > 0 && (
                        <section className="exc-etapas" aria-label="Avance y cronograma por etapa">
                            {etapasAvance.map((e) => (
                                <EtapaCronograma key={e.clave} etapa={e} editable={editable} hoy={hoy} onGuardarFecha={guardarFecha} />
                            ))}
                        </section>
                    )}
                    <p className="ct-oculto" aria-live="polite">{anuncio}</p>

                    {cerrado && (
                        <div className="exv-nota">
```

En `resources/js/components/FichaProyecto.jsx`, reemplazar (1 de 4):

```jsx
import { datosGenerales, errorDeFicha, esIdProyecto, filaProceso, resumenFormulacion } from '../utils/fichaProyecto';
import { estadoDesdeFicha, estadoNuevoContratoDeProyecto, filaContrato, puedeEditarContratos, rutaContrato } from '../utils/contratosModulo';
import { RUTA_CONTRATOS } from '../utils/navegacion';
import ProcesosProyecto from './ProcesosProyecto';
import '../../css/Plantillas.css';
import '../../css/Parametros.css';
```

por:

```jsx
import { datosGenerales, errorDeFicha, esIdProyecto, filaProceso, resumenFormulacion } from '../utils/fichaProyecto';
import { estadoDesdeFicha, estadoNuevoContratoDeProyecto, filaContrato, puedeEditarContratos, rutaContrato } from '../utils/contratosModulo';
import { RUTA_CONTRATOS } from '../utils/navegacion';
import ChipsAvisosProceso from './ChipsAvisosProceso';
import ProcesosProyecto from './ProcesosProyecto';
import '../../css/Plantillas.css';
import '../../css/Parametros.css';
```

En `resources/js/components/FichaProyecto.jsx`, reemplazar (2 de 4):

```jsx
};

const Procesos = ({ procesos, onAbrir }) => {
    const filas = procesos.map(filaProceso);
    return (
        <Bloque
            titulo="Procesos de contratación"
```

por:

```jsx
};

const Procesos = ({ procesos, onAbrir }) => {
    const filas = procesos.map((p) => ({ ...filaProceso(p), avisos: p.avisos }));
    return (
        <Bloque
            titulo="Procesos de contratación"
```

En `resources/js/components/FichaProyecto.jsx`, reemplazar (3 de 4):

```jsx
                                <th>Valor</th>
                                <th>Contratos</th>
                                <th>Expediente</th>
                            </tr>
                        </thead>
                        <tbody>
```

por:

```jsx
                                <th>Valor</th>
                                <th>Contratos</th>
                                <th>Expediente</th>
                                <th>Avisos</th>
                            </tr>
                        </thead>
                        <tbody>
```

En `resources/js/components/FichaProyecto.jsx`, reemplazar (4 de 4):

```jsx
                                        {' '}
                                        <Link to={f.rutaChecklist} className="ct-enlace">Checklist</Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
```

por:

```jsx
                                        {' '}
                                        <Link to={f.rutaChecklist} className="ct-enlace">Checklist</Link>
                                    </td>
                                    <td data-etiqueta="Avisos"><ChipsAvisosProceso avisos={f.avisos} /></td>
                                </tr>
                            ))}
                        </tbody>
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (1 de 2):

```jsx
    modoTrasGuardarContrato, numeroProceso, opcionesConActual, opcionesFuenteProceso, ordenarCatalogo, puedeEditarProcesos, puedeRegistrarContrato,
    textoContratos, textoFecha, textoValor, tonoEstado, validarProceso, valorEnEdicion,
} from '../utils/contratacion';
import ContratoEditor from './contratos/ContratoEditor';
import useDialogoModal from './useDialogoModal';
import useOcupado from './useOcupado';
```

por:

```jsx
    modoTrasGuardarContrato, numeroProceso, opcionesConActual, opcionesFuenteProceso, ordenarCatalogo, puedeEditarProcesos, puedeRegistrarContrato,
    textoContratos, textoFecha, textoValor, tonoEstado, validarProceso, valorEnEdicion,
} from '../utils/contratacion';
import ChipsAvisosProceso from './ChipsAvisosProceso';
import ContratoEditor from './contratos/ContratoEditor';
import useDialogoModal from './useDialogoModal';
import useOcupado from './useOcupado';
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (2 de 2):

```jsx
                                                <div><dt>Contratos vinculados</dt><dd>{textoContratos(p.contratos)}</dd></div>
                                                <ExpedienteProceso proceso={p} />
                                            </dl>
                                            {p.observaciones && <p className="pl-ayuda">{p.observaciones}</p>}
                                            {modo?.tipo === 'estado' && modo.proceso.id === p.id ? (
                                                <FormularioEstado proceso={p} guardando={ocupado} onGuardar={cambiarEstado} onCancelar={() => setModo(null)} />
```

por:

```jsx
                                                <div><dt>Contratos vinculados</dt><dd>{textoContratos(p.contratos)}</dd></div>
                                                <ExpedienteProceso proceso={p} />
                                            </dl>
                                            <ChipsAvisosProceso avisos={p.avisos} />
                                            {p.observaciones && <p className="pl-ayuda">{p.observaciones}</p>}
                                            {modo?.tipo === 'estado' && modo.proceso.id === p.id ? (
                                                <FormularioEstado proceso={p} guardando={ocupado} onGuardar={cambiarEstado} onCancelar={() => setModo(null)} />
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (1 de 5):

```jsx
    opcionesFiltroEstado, opcionesFiltroProyecto, parametrosBusqueda, proyectoDeEstado, puedeEditarContratos, resultadoSecopDeEstado, rutaContrato,
    rutaListaDesde, rutaVistaSecop, textoListaVacia, textoVolverDesde, vistaDeConsulta,
} from '../../utils/contratosModulo';
import { RUTA_CONTRATOS } from '../../utils/navegacion';
import { mensajeApi } from '../../utils/plantillasChecklist';
import { mensajeSecop } from '../../utils/secop';
```

por:

```jsx
    opcionesFiltroEstado, opcionesFiltroProyecto, parametrosBusqueda, proyectoDeEstado, puedeEditarContratos, resultadoSecopDeEstado, rutaContrato,
    rutaListaDesde, rutaVistaSecop, textoListaVacia, textoVolverDesde, vistaDeConsulta,
} from '../../utils/contratosModulo';
import { avisoProcesoContrato } from '../../utils/avisosContratacion';
import { RUTA_CONTRATOS } from '../../utils/navegacion';
import { mensajeApi } from '../../utils/plantillasChecklist';
import { mensajeSecop } from '../../utils/secop';
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (2 de 5):

```jsx
    return { lista, error, reintentar: cargar };
};

const AvisoProyectos = ({ proyectos }) => (proyectos.error ? (
    <p className="pl-error" role="alert">
        No se pudieron cargar los proyectos: {proyectos.error}{' '}
```

por:

```jsx
    return { lista, error, reintentar: cargar };
};

/**
 * El aviso de un contrato cuyo proceso tiene etapas vencidas, el cierre vencido o está sin
 * actualización (`aviso_proceso`), con el enlace al checklist del proceso. Sin aviso, nada.
 */
const AvisoProceso = ({ aviso, bloque = false }) => {
    const datos = avisoProcesoContrato(aviso);
    if (!datos) return null;
    const enlace = <Link to={datos.ruta} className="ct-enlace">Abrir el checklist del proceso</Link>;
    if (bloque) {
        return <p className="pl-aviso-suave"><b>{datos.texto}.</b> {enlace}</p>;
    }
    return (
        <span className="ct-aviso-proceso">
            <span className="ct-estado ct-estado-coral">{datos.texto}</span>
            <Link to={datos.ruta} className="ct-enlace">Checklist<span className="ct-oculto"> del proceso</span></Link>
        </span>
    );
};

const AvisoProyectos = ({ proyectos }) => (proyectos.error ? (
    <p className="pl-error" role="alert">
        No se pudieron cargar los proyectos: {proyectos.error}{' '}
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (3 de 5):

```jsx
    }, [clave]);
    useEffect(() => { cargar(); }, [cargar]);

    const filas = useMemo(() => (contratos ?? []).map(filaContrato), [contratos]);
    // Al abrir un contrato se lleva la lista con sus filtros, para volver a ella.
    const estadoNavegacion = { lista: search };
```

por:

```jsx
    }, [clave]);
    useEffect(() => { cargar(); }, [cargar]);

    const filas = useMemo(() => (contratos ?? []).map((c) => ({ ...filaContrato(c), avisoProceso: c.aviso_proceso })), [contratos]);
    // Al abrir un contrato se lleva la lista con sus filtros, para volver a ella.
    const estadoNavegacion = { lista: search };
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (4 de 5):

```jsx
                                    <td data-etiqueta="Objeto"><span className="ct-objeto" title={f.objeto}>{f.objeto}</span></td>
                                    <td data-etiqueta="Contratista">{f.contratista}</td>
                                    <td data-etiqueta="Proyecto" className={f.sinProyecto ? 'ct-sin-dato' : undefined}>{f.proyecto}</td>
                                    <td data-etiqueta="Proceso" className="numero">{f.proceso}</td>
                                    <td data-etiqueta="Estado"><span className={`ct-estado ct-estado-${f.tono}`}>{f.estado}</span></td>
                                    <td data-etiqueta="Valor vigente" className="numero">{f.valor}</td>
                                    <td data-etiqueta="Físico" className="numero">{f.avanceFisico}</td>
```

por:

```jsx
                                    <td data-etiqueta="Objeto"><span className="ct-objeto" title={f.objeto}>{f.objeto}</span></td>
                                    <td data-etiqueta="Contratista">{f.contratista}</td>
                                    <td data-etiqueta="Proyecto" className={f.sinProyecto ? 'ct-sin-dato' : undefined}>{f.proyecto}</td>
                                    <td data-etiqueta="Proceso" className="numero">
                                        {f.proceso}
                                        <AvisoProceso aviso={f.avisoProceso} />
                                    </td>
                                    <td data-etiqueta="Estado"><span className={`ct-estado ct-estado-${f.tono}`}>{f.estado}</span></td>
                                    <td data-etiqueta="Valor vigente" className="numero">{f.valor}</td>
                                    <td data-etiqueta="Físico" className="numero">{f.avanceFisico}</td>
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (5 de 5):

```jsx
                {!edita && <p>Solo lectura: tu rol puede consultar el contrato, pero no modificarlo.</p>}
            </header>
            {edita && <AvisoProyectos proyectos={proyectos} />}
            <div className="gp-panel ct-editor">
                <ContratoEditor
                    key={contrato.id}
```

por:

```jsx
                {!edita && <p>Solo lectura: tu rol puede consultar el contrato, pero no modificarlo.</p>}
            </header>
            {edita && <AvisoProyectos proyectos={proyectos} />}
            <AvisoProceso aviso={contrato.aviso_proceso} bloque />
            <div className="gp-panel ct-editor">
                <ContratoEditor
                    key={contrato.id}
```

En `resources/js/utils/expedienteContratacion.js`, reemplazar (1 de 2):

```js
// pendientes al cerrar y la elección de plantillas para crearlo. La agrupación de viabilización
// (agruparChecklist) no cambia. Funciones puras, probadas en expedienteContratacion.test.js.

import { esIdContrato } from './contratosModulo';
import { opcionesEtapa } from './contratacion';
import { datosPropio, formularioPropio } from './expedienteChecklist';
```

por:

```js
// pendientes al cerrar y la elección de plantillas para crearlo. La agrupación de viabilización
// (agruparChecklist) no cambia. Funciones puras, probadas en expedienteContratacion.test.js.

import { etiquetaAlerta } from './avisosContratacion';
import { esIdContrato } from './contratosModulo';
import { opcionesEtapa } from './contratacion';
import { datosPropio, formularioPropio } from './expedienteChecklist';
```

En `resources/js/utils/expedienteContratacion.js`, reemplazar (2 de 2):

```js
    porcentaje: a.porcentaje,
}));

/** El expediente con el progreso y el avance por etapa que trae una respuesta parcial, si los trae. */
export const conAvance = (expediente, datos) => ({
    ...expediente,
    progreso: datos?.progreso ?? expediente.progreso,
    avance_por_etapa: datos?.avance_por_etapa ?? expediente.avance_por_etapa,
});

/** Etapas que se ofrecen a un propio: las activas y la que ya tiene (aunque esté inactiva). */
export const opcionesEtapaPropio = (etapas, fila) => opcionesEtapa(etapas, fila?.etapa ?? null);
```

por:

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
            fecha_programada: previa.fecha_programada,
            fecha_real: previa.fecha_real,
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

export const CAMPOS_FECHA_ETAPA = ['fecha_programada', 'fecha_real'];

/**
 * Las fechas de una etapa de `avance_por_etapa` como valores del input date ('' sin fecha), si
 * la real la sugirió el sistema y la etiqueta de su semáforo. El grupo sin etapa no tiene fechas.
 */
export const fechasEtapa = (avance) => {
    const real = avance?.fecha_real ?? '';
    return {
        etapaId: avance?.etapa?.id ?? null,
        conFechas: Boolean(avance?.etapa),
        programada: avance?.fecha_programada ?? '',
        real,
        sugerida: Boolean(real && avance?.fecha_real_sugerida),
        alerta: etiquetaAlerta(avance?.alerta),
    };
};

/** El avance por etapa para sus barras (avanceEtapas) con las fechas y el semáforo de cada una. */
export const etapasConFechas = (avance) => {
    const lista = avance ?? [];
    return avanceEtapas(lista).map((e, i) => ({ ...e, ...fechasEtapa(lista[i]) }));
};

/**
 * Si el valor del input date se puede guardar: vacío (borra la fecha) o una fecha año-mes-día
 * real con año desde 1900. Mientras se escribe el año, el input da valores como 0002-11-25.
 */
export const fechaCompleta = (valor) => {
    if (valor === '') return true;
    const partes = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(valor ?? ''));
    if (!partes) return false;
    const [anio, mes, dia] = partes.slice(1).map(Number);
    if (anio < 1900) return false;
    const fecha = new Date(Date.UTC(anio, mes - 1, dia));
    return fecha.getUTCFullYear() === anio && fecha.getUTCMonth() === mes - 1 && fecha.getUTCDate() === dia;
};

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

/** El error que va bajo el campo de fecha: el de ese campo si el 422 lo trae; si no, el de la API. */
export const errorFechaEtapa = (error, campo) => {
    const errores = error?.response?.status === 422 ? error.response.data?.errors : null;
    const propio = errores && [].concat(errores[campo] ?? [])[0];
    return propio ? String(propio) : mensajeApi(error);
};

const ZONA_CONTRATACION = 'America/Bogota';

/** El día de hoy en la zona de contratación (Bogotá) como año-mes-día, no el del equipo ni el de UTC: el `max` de la fecha real. */
export const fechaHoyBogota = (ahora = new Date()) => new Intl.DateTimeFormat('en-CA', {
    timeZone: ZONA_CONTRATACION, year: 'numeric', month: '2-digit', day: '2-digit',
}).format(ahora);

/** Una fecha año-mes-día como día/mes/año (sin pasar por Date: no cambia con la zona); "Sin fecha" si no hay. */
export const fechaLegible = (iso) => {
    const partes = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso ?? ''));
    return partes ? `${partes[3]}/${partes[2]}/${partes[1]}` : 'Sin fecha';
};

/** Qué hacer con el borrador de una fecha: 'nada' (ya guardado), 'incompleta' (a medio escribir) o 'guardar'. */
export const fechaPorGuardar = (borrador, guardado) => {
    if (borrador === guardado) return 'nada';
    return fechaCompleta(borrador) ? 'guardar' : 'incompleta';
};

/** Etapas que se ofrecen a un propio: las activas y la que ya tiene (aunque esté inactiva). */
export const opcionesEtapaPropio = (etapas, fila) => opcionesEtapa(etapas, fila?.etapa ?? null);
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/avisosContratacion.test.js resources/js/utils/expedienteContratacion.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1262 passed.

Run: `npm test`
Expected: 772 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/js/components/ChipsAvisosProceso.jsx resources/js/utils/avisosContratacion.js resources/js/utils/avisosContratacion.test.js resources/css/Contratacion.css resources/css/ExpedienteContratacion.css resources/js/components/ExpedienteContratacion.jsx resources/js/components/FichaProyecto.jsx resources/js/components/ProcesosProyecto.jsx resources/js/components/contratos/ContratosModulo.jsx resources/js/utils/expedienteContratacion.js resources/js/utils/expedienteContratacion.test.js
git commit -m "feat: stage dates and alerts in the contracting screens"
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
git commit -m "build: production bundle for procurement stage 4B"
```

Los archivos que no cambiaron se omiten. Los estilos nuevos (`Contratacion.css`, `ExpedienteContratacion.css`) se importan desde los componentes y van dentro de `app.js`: `public/css/app.css` no se comitea.

---

### Tarea 6: Verificación en la copia descartable

Usa la copia y las fotos de la tarea 1. Nunca `gest_pro`.

- [ ] **Paso 1: Migrar, revertir y volver a migrar, comparando**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

Expected: se aplica `2026_11_19_100021_create_expediente_etapas_table`.

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c8_despues_$f.json" gestpro_verif_tmp && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c8_antes_$f.json" "$TMP_DIR/c8_despues_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces. El comparador admite las claves nuevas (`aviso_proceso` en los contratos); un valor distinto o algo que falta, no.

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT COUNT(*) FROM expediente_etapas;"
```

Expected: `0`.

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=1 --force
```

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c8_rb_$f.json" gestpro_verif_tmp --solo-tablas && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c8_tablas_antes_$f.json" "$TMP_DIR/c8_rb_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c8_re_$f.json" gestpro_verif_tmp && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c8_antes_$f.json" "$TMP_DIR/c8_re_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

- [ ] **Paso 2: Probar en el navegador sobre la copia**

Compilar en desarrollo (`npx mix`) si `public/` no tiene el código de esta etapa. Arrancar el servidor desde `public/`, en un puerto libre (`netstat -ano | grep 8796`):

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8796 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Crear un Administrador de prueba solo en la copia. Nunca usar sesiones de usuarios reales de `gest_pro`:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'verif@gestpro.test', 'rol' => App\Enums\Rol::Administrador]);"
```

Entrar por `http://127.0.0.1:8796/GestPro/`, borrando `localStorage` si hay una sesión anterior. Las llamadas a la API van con el token de esa sesión. Todo lo que sigue se hace **solo en la copia**.

Comprobar:

1. **El expediente del proceso 2:**
   - publicar la versión borrador de "Licitación pública – SECOP II" con `POST /catalogo-requisitos/versiones/{id}/publicar`;
   - crear el expediente del proceso 2 con esa plantilla: `POST /procesos/2/expediente` con `{"plantillas": [id]}`, usando el id de la plantilla de licitación (hay 2 compatibles: elegir esa, no "nueva plantilla").
2. **Las fechas por API** (`PUT /expedientes/{expediente}/etapas/{etapa}`):
   - a la etapa 1, una `fecha_programada` pasada (ayer, por ejemplo): responde 200 y la etapa con `alerta` `vencida`;
   - a la etapa 2, una `fecha_programada` a 2 días de hoy: `alerta` `proxima`;
   - una `fecha_real` futura: 422 con "La fecha real no puede ser posterior a hoy.".
3. **`/contratacion/2`:**
   - arriba, la franja "1 etapa vencida · 1 etapa próxima a vencer · El proceso cierra en N días". N depende del día de la verificación, porque el proceso cierra el 2026-11-20: el 2026-11-19 dice "El proceso cierra en 1 día"; el 2026-11-20, "El proceso cierra hoy"; después, "La fecha de cierre del proceso ya pasó";
   - las etapas 1 y 2 muestran las etiquetas "Vencida" y "Próxima";
   - editar en la pantalla la fecha programada de la etapa 1 a una fecha lejana: se guarda al salir del campo, la etiqueta pasa a "Al día" y la franja deja de contar la vencida;
   - marcar el único requisito de la etapa 4: la etapa queda "Completa" y su fecha real es la de hoy, con la marca "sugerida".
4. **La ficha `/proyectos/1`:** el bloque Procesos tiene la columna Avisos, con los chips del proceso 2 (próximas, cierre, "Hitos C/T").
5. **Contratos:** vincular en la copia un contrato al proceso 2 y devolver la etapa 1 a una fecha pasada por API:

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "UPDATE contratos SET proceso_licitacion = 2 WHERE id = 1;"
```

   - en `/contratos`, la fila del contrato 1 muestra bajo el proceso el aviso "El proceso tiene 1 etapa vencida" con el enlace "Checklist" a `/contratacion/2`, sin encimarse con la columna Estado;
   - en `/contratos/1`, el mismo aviso en un bloque, con "Abrir el checklist del proceso".
6. **Cerrar el expediente** con `POST /expedientes/{expediente}/cerrar` y `{"confirmar": true}`:
   - en `/contratacion/2`, las etapas no completas dicen "Sin completar", las fechas se ven como texto `dd/mm/aaaa` y la franja ya no cuenta vencidas ni próximas;
   - un `PUT` de fechas responde 422 con "El expediente está cerrado.".
7. **La viabilización no cambia:** `GET /expedientes` sigue con los 11 expedientes y `/viabilizacion/<proyecto con expediente>` se ve como antes.

La consola del navegador no muestra errores nuevos. Los 401 de una sesión vieja y los 422 de las validaciones probadas son esperados.

- [ ] **Paso 3: Limpiar**

Detener el servidor, eliminar la copia y devolver `public/` a lo comiteado si se compiló en desarrollo:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 4: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas, 1262.

Run: `npm test`
Expected: pasan todas, 772.

---

### Tarea 7: Informar y pedir validación

- [ ] **Paso 1: Actualizar el estado de la propuesta**

En `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, reemplazar:

```markdown
La etapa 4A (peso por requisito y avance ponderado, migración 21) está validada y aplicada en `gest_pro` (2026-11-19).
```

por:

```markdown
La etapa 4A (peso por requisito y avance ponderado, migración 21) está validada y aplicada en `gest_pro` (2026-11-19). La etapa 4B (fechas por etapa y avisos, migración 22) está implementada y verificada en copia, pendiente de validar y aplicar en `gest_pro`.
```

```bash
git add docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md
git commit -m "docs: mark procurement stage 4B as implemented"
```

- [ ] **Paso 2: Informar**

Informar:
- los totales de pruebas;
- las comparaciones (migrar, revertir y volver a migrar) y la tabla `expediente_etapas` vacía;
- lo revisado en el navegador.

Pedir **dos** decisiones por separado:
1. si valida la etapa 4B;
2. si autoriza aplicar la migración 22 en `gest_pro`.

Recordar que, con los datos actuales, no hay expedientes de contratación: la tabla nueva queda vacía y ninguna cifra cambia.

---

### Tarea 8: Aplicar en `gest_pro` (solo con autorización)

No ejecutar esta tarea sin un sí explícito del usuario a este paso. Si la comparación muestra diferencias, restaurar el respaldo: no usar `migrate:rollback`.

- [ ] **Paso 1: Respaldar**

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro > "$TMP_DIR/gest_pro_antes_contratacion_4b.sql"
```

Expected: el archivo existe y termina en `-- Dump completed`.

- [ ] **Paso 2: Tomar las fotos antes de migrar (solo tablas)**

```bash
for f in 2 3; do php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c8_real_antes_$f.json" gest_pro --solo-tablas; done
```

- [ ] **Paso 3: Migrar hacia adelante**

Run: `php artisan migrate --force`
Expected: se aplica `2026_11_19_100021_create_expediente_etapas_table`.

- [ ] **Paso 4: Comparar**

```bash
for f in 2 3; do php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c8_real_despues_$f.json" gest_pro && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c8_real_antes_$f.json" "$TMP_DIR/c8_real_despues_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

```bash
/c/xampp/mysql/bin/mysql -u root gest_pro -e "SELECT COUNT(*) FROM expediente_etapas;"
```

Expected: `0`.

- [ ] **Paso 5: Informar**

Dar el resultado y la ruta completa del respaldo, y avisar que está en una carpeta temporal.

---

## Criterio de la etapa

La etapa está lista cuando:

- cada etapa de un expediente de contratación tiene fecha programada y fecha real, que se editan en la página del expediente y por API; la fecha real se sugiere al completarse la etapa, se puede corregir y nunca es posterior a hoy;
- las etapas vencidas y próximas, el cierre próximo o vencido, los procesos sin actualización y los hitos se ven en la página del expediente, en la lista de procesos, en la ficha del proyecto y en el módulo Contratos, sin consultas que crezcan con los procesos ni con los contratos;
- un expediente cerrado no muestra vencimientos y no deja cambiar fechas;
- la viabilización no cambia: mismas respuestas, mismas cifras, y sus pruebas y la paridad del evaluador pasan;
- ningún dato cambia al migrar ni al revertir, y revertir se detiene si la tabla tiene fechas.
