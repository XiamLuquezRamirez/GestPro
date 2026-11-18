# Contratación, etapa 3A: proceso y contrato, datos y reglas. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** que el proceso y el contrato sean dos registros vinculados sin repetir datos. El proceso gana fuente, fechas de apertura y cierre, responsable, observaciones y el estado `revocado`. El contrato gana estados controlados, tipo, datos de suscripción, RP, acta de inicio y supervisor. Además, un contrato puede no tener proyecto. Se guarda con validación, se vincula a un proceso adjudicado con precarga y no se borra si ya tiene ejecución.

**Arquitectura:**
- **Migración 18:** amplía `procesos_licitacion` (`fuente_id`, `fecha_apertura`, `fecha_cierre`, `responsable_id`, `observaciones`).
- **Migración 19:** amplía `contratos`. Convierte el estado de texto libre a valores controlados, guarda el texto anterior en `estado_original` y vuelve opcional el proyecto. Agrega las columnas para la referencia de SECOP II, que usará la etapa 3B, y siembra los tipos de contrato.
- **Backend:**
  - `App\Services\Contratacion\ContratosService` valida, guarda, recalcula y elimina contratos. `ContratoController` atiende las rutas de siempre, `/guardarContrato` y `/eliminarContrato`.
  - `TipoContratoController` administra `tipos_procesos`.
  - `ProcesosContratacion` incorpora los campos nuevos y la precarga del contrato.
- **Pantallas:** el formulario actual de contratos en Parámetros → Proyectos (estados, campos nuevos, proceso con precarga, valores iniciales y vigentes, borrado protegido), el diálogo de procesos y el catálogo "Tipos de contrato".

**Tecnologías:** Laravel 12, PHP 8.2, MariaDB 10.4, React 18 con Laravel Mix, PHPUnit sobre SQLite en memoria, Vitest.

**Fuente:** `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, aprobada el 2026-11-18, fase 2. El 2026-11-18 el usuario decidió:
- Dividir la fase 2 en dos etapas. Esta es la **3A**. La **3B** incluye el módulo "Contratos" del menú, la búsqueda en SECOP II, "Registrar contrato" desde el proceso y la ficha del proyecto.
- El formulario edita el **valor inicial** y la **fecha fin inicial**. Los vigentes se calculan con las adiciones y prórrogas.
- **Un contrato puede no tener proyecto**, porque la empresa muchas veces solo ejecuta contratos como contratista. Si se borra un proyecto, sus contratos quedan sin proyecto en vez de borrarse.

**Estado de partida:** rama `feature/contratacion`, igual a `main`. Las etapas 1 y 2 están aplicadas en `gest_pro`. Pasan 848 pruebas PHP y 461 JS.

**Datos reales comprobados:**
- **Contratos:** 7, con estados "En ejecución" (4), "vigente" (2) y "liquidado" (1). Ninguno tiene proceso ni modificaciones. En todos, el valor coincide con el inicial y la fecha fin con la inicial. Ninguno tiene un anticipo mayor que 50 ni campos obligatorios vacíos.
- **Procesos:** 1, sin contratos.
- **`tipos_procesos`:** vacío.

---

## Reglas de ejecución

- **Nada de esto se aplica a `gest_pro` sin autorización del usuario.** Allí nunca se usa `migrate:rollback`: si hay que volver atrás, se restaura el respaldo. La tarea 11 aplica las migraciones, y solo con un sí explícito.
- Commits autorizados solo en `feature/contratacion`. Sin push. Mensajes en inglés, terminados con la línea `Co-Authored-By` del modelo que comitea.
- Nomenclatura en español sin tildes para tablas, columnas, clases y métodos nuevos. Los textos para el usuario van en español con tuteo. Las migraciones, que son herramientas del operador, conservan el registro de usted.
- Pruebas: `php artisan test` sin definir `DB_DATABASE`, para que use SQLite en memoria, y `npm test`. Si Vitest crea `resources/js/node_modules`, se borra.
- **Editar con la herramienta Edit, no con `sed`.** Cada reemplazo de este plan aparece una sola vez en su archivo. Si no lo encuentras tal cual, detente y avisa. Si Edit falla solo por finales de línea CRLF, adapta solo eso.
- **Copiar cada bloque completo, carácter por carácter.** En etapas anteriores se perdió el espacio después de un `=` al recortar un reemplazo. Antes de comitear, revisa en `git diff` que no aparezca `=(`, `===X` ni algo parecido que el plan no tenga.
- **No usar `git stash`:** el usuario tiene stashes propios.
- El usuario suele tener `npm run watch` corriendo, que reescribe `public/`. Solo la tarea 8 comitea `public/`, después de `npm run production`.
- No tocar ni comitear `public/images/marca-completa.png` ni `public/images/marca-completa1.png`: son cambios del usuario.
- `.env` está versionado: nunca se agrega a un commit. Comitear siempre con rutas explícitas.
- `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio. Hay que definirlo en cada comando, porque el estado de la terminal no se conserva.
- **SQLite:** agregar columnas con clave foránea reconstruye la tabla y, dentro de `RefreshDatabase`, borra en cascada las filas hijas. Las pruebas de las migraciones no crean filas hijas.
- **Estado intermedio:** entre las tareas 5 y 6, guardar un contrato desde la pantalla da 422, porque el servicio exige `valor_inicial` y los estados nuevos, y la pantalla todavía no los envía. Se resuelve en la tarea 6, antes del compilado.
- **Backend y compilado van juntos.** El código de esta etapa exige los campos y estados nuevos, y el JavaScript anterior no los envía. Por eso no se despliega el backend sin el compilado de la tarea 8, ni el compilado sin las migraciones de la tarea 11.

## Decisiones de este plan

**Proceso**
- Fuente y responsable: activos, o los que el proceso ya tiene.
- Fechas en formato `Y-m-d`. El cierre no puede ser anterior a la apertura, comparando con lo que quedaría guardado.
- Observaciones de hasta 5000 caracteres.
- `revocado` es un estado más, y también un estado de cierre.
- `GET /procesos/{proceso}/precarga-contrato` (lectura) devuelve:
  - el proceso resumido;
  - si admite contratos, es decir, si está `adjudicado` o `celebrado`;
  - la precarga: objeto, entidad contratante y valor sugerido. El valor sugerido es el monto menos lo ya contratado sin contar los anulados, nunca negativo, y nulo si el proceso no tiene monto.

**Contrato: datos y estados**
- Estados: `celebrado`, `en_ejecucion`, `suspendido`, `terminado`, `liquidado` y `anulado`.
- Conversión de los estados existentes, sin distinguir mayúsculas, tildes ni espacios:
  - "en ejecución" y "vigente" pasan a `en_ejecucion`;
  - "ejecutado" y "terminado" pasan a `terminado`;
  - lo vacío o no reconocido pasa a `en_ejecucion`, y lo no reconocido se anota en el log.
- El texto anterior queda en `estado_original`.
- El `down()` de la migración 19 restaura los estados y se detiene si se perderían datos: columnas nuevas con valor, contratos creados después, estados cambiados o contratos sin proyecto.
- Tipos de contrato sembrados solo si la tabla está vacía: Obra, Interventoría, Consultoría, Suministro, Prestación de servicios y Orden de compra.

**Contrato: guardar**
- `POST /guardarContrato` conserva su URI, su permiso y la forma del pedido (`formContrato` y `anexos`). El pedido trae `valor_inicial` y `fecha_fin_inicial`. El valor y la fecha fin vigentes se recalculan con las modificaciones.
- Responde `{success, contrato, avisos, proponer_celebrado}`. Los errores de validación llegan con 422 por campo, y una edición sobre una versión vieja da 409.
- Edición parcial: un campo ausente no cambia y un nulo lo borra.
- Anexos: si la clave no viene o vale null, se conservan. Si viene, se reemplazan, y si cambian, la versión avanza.
- Un contrato puede crearse sin proyecto. Al editar, uno que ya tiene proyecto no puede quedarse sin él: eso queda para el módulo Contratos de la etapa 3B.

**Contrato: proceso**
- El proceso debe ser del mismo proyecto.
- Vincular un proceso nuevo exige que esté `adjudicado` o `celebrado`. El que ya tiene se conserva.
- Un contrato sin proyecto toma el del proceso.

**Contrato: avisos, historial y borrado**
- Si los contratos de un proceso suman más que su monto, se avisa sin bloquear.
- Al crear el primer contrato de un proceso `adjudicado`, la pantalla propone pasarlo a `celebrado` y pide confirmación.
- El historial va en `checklist_historial`, con la entidad `contrato`.
- `POST /eliminarContrato` se rechaza si el contrato tiene actas, actividades, avances físicos o modificaciones: hay que anularlo. Si no, se borran sus anexos, pero no un archivo que use otro anexo.

**Contrato: indicadores y lectura**
- Los contratos `anulado` no cuentan en la cadena presupuestal, en el panel de contratos ni en los desfases. Hoy no hay ninguno.
- `GET /proyectos` y `GET /listarContratos` traen las columnas nuevas y el proceso resumido. Así, editar desde cualquiera de los dos no borra datos.

**Catálogo de tipos de contrato**
- Usa la tabla `tipos_procesos`. Las rutas existentes `/tiposProcesos`, `/guardarTipoProceso` y `/activarTipoProceso`, que hoy dan 500, pasan a `TipoContratoController`. `/eliminarTipoProceso` se deja como está.
- La escritura es solo del Administrador.

## Fuera de esta etapa

- Etapa 3B: el módulo "Contratos" (todos los contratos, con o sin proyecto), la búsqueda e importación desde SECOP II, "Registrar contrato" desde el proceso, la ficha del proyecto con Procesos y Contratos, y la extracción de los contratos de `Parametros.jsx`.
- `procesos_licitacion.tipo_proceso` y la ruta `/eliminarTipoProceso`.
- El conteo `totalContratos` del tablero, que sigue contando todos los contratos.

## Lo que ya se ensayó

El plan se ensayó completo en un clon descartable fuera del repositorio, y este documento se generó con el código de ese ensayo. Un script comprobó que aplicar sus bloques sobre la rama reproduce el ensayo exactamente.

- **Pruebas:** con el código de las tareas 2 a 7 pasan 1008 pruebas PHP y 526 JS.
- **En una copia de `gest_pro` con los datos reales,** la foto de la tarea 1 no mostró ninguna diferencia:
  - al migrar;
  - al revertir las dos migraciones (comparando solo tablas);
  - al volver a migrar.

  La foto cubre los 7 contratos con sus actas, actividades y modificaciones, los listados de contratos de la API, el proceso y los 10 expedientes de viabilización.
- **Migración 19 en MariaDB:**
  - convirtió los 7 estados ("En ejecución" y "vigente" a `en_ejecucion`, "liquidado" a `liquidado`) y guardó el texto anterior;
  - sembró los 6 tipos de contrato;
  - dejó `proyecto` nulable con `ON DELETE SET NULL`.

  Al revertir, restauró los textos, la columna obligatoria con `ON DELETE CASCADE` y la tabla de tipos vacía.
- **En el navegador, sobre esa copia:**
  - la lista de contratos mostró las etiquetas de estado;
  - editar un contrato existente cambió solo el tipo, el supervisor y el avance financiero que la pantalla calcula, igual que hoy, y quedó en el historial;
  - eliminar ese contrato, que tiene un acta, se rechazó con el mensaje esperado;
  - con un proceso adjudicado, un contrato nuevo recibió la precarga del objeto y del valor, mostró los datos del proceso de solo lectura, y la propuesta de pasar el proceso a celebrado lo dejó `celebrado`;
  - el diálogo de procesos mostró los campos nuevos y cuántos contratos tiene cada proceso;
  - "Tipos de contrato" mostró los 6 tipos;
  - la consola no mostró errores nuevos.
- **Una revisión independiente** del ensayo no encontró hallazgos graves. Los de gravedad media y baja ya están corregidos en las tareas correspondientes:
  - una edición que solo cambia anexos no avanzaba la versión;
  - se podía quitar el proyecto por la API;
  - `anexos: null` borraba los anexos;
  - al eliminar un contrato se podía borrar el archivo de otro;
  - había carreras y doble envío en la pantalla, y los errores no recibían el foco;
  - los anulados no quedaban fuera de todos los indicadores.

---

### Tarea 1: Punto de partida y foto con el código actual

La foto se toma **antes de cambiar código**. La copia `gestpro_verif_tmp` se conserva hasta la tarea 9.

- [ ] **Paso 1: Comprobar la rama, el estado y las pruebas**

Run: `git branch --show-current`
Expected: `feature/contratacion`

Run: `git status --short`
Expected: como mucho, `public/` (si corre `npm run watch`) y las dos imágenes del usuario.

Run: `php artisan test`
Expected: pasan todas, 848. Si alguna falla, detenerse y avisar.

Run: `npm test`
Expected: pasan todas, 461.

- [ ] **Paso 2: Crear la copia descartable**

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE IF EXISTS gestpro_verif_tmp; CREATE DATABASE gestpro_verif_tmp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro | /c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp
```

Si MySQL no responde (`ERROR 2002`), está apagado. Hay que pedirle al usuario que lo arranque desde XAMPP, o arrancarlo en segundo plano con `/c/xampp/mysql/bin/mysqld --defaults-file=/c/xampp/mysql/bin/my.ini --standalone`.

- [ ] **Paso 3: Crear los dos scripts de comprobación**

Crear `$TMP_DIR/gestpro_foto_contratacion3.php`. La foto toma:
- los contratos con su ejecución (actas, actividades, avances, modificaciones y anexos);
- los procesos y los expedientes de viabilización;
- los listados de contratos de la API.

La etapa no debe alterar ninguno de ellos. El estado del contrato cambia a propósito, así que se compara el texto original. El script se detiene si la base no es la esperada:

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

Crear `$TMP_DIR/gestpro_comparar_contratacion3.php`. Lo nuevo se permite; un valor distinto o algo que falta, no:

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

- [ ] **Paso 4: Tomar la foto de antes, con el código actual**

Desde la raíz del repositorio:

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion3.php" "$TMP_DIR/c3_antes.json" gestpro_verif_tmp
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion3.php" "$TMP_DIR/c3_tablas_antes.json" gestpro_verif_tmp --solo-tablas
```

La segunda foto, solo de tablas, sirve para comparar después de revertir.

Expected: `Contratos: 7, procesos: 1, actas: 2, expedientes: 10, con API`, con las cifras actuales de `gest_pro`. La segunda línea termina en `solo tablas`.

---

### Tarea 2: Migraciones 18 y 19: proceso y contrato ampliados

Campos nuevos del proceso y del contrato, estados controlados del contrato, proyecto opcional, columnas de SECOP y tipos de contrato sembrados.

**Files:**
- Create: `database/migrations/2026_11_18_100017_amplia_procesos_licitacion_contratos.php`
- Create: `database/migrations/2026_11_18_100018_amplia_contratos.php`
- Test (create): `tests/Feature/Schema/ProcesosContratosAmpliadosTest.php`
- Modify: `app/Models/Contrato.php`
- Modify: `app/Models/ProcesoLicitacion.php`
- Test: `tests/Feature/Schema/ContratacionCatalogosTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Schema/ProcesosContratosAmpliadosTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\Contrato;
use App\Models\FuenteFinanciacion;
use App\Models\ProcesoLicitacion;
use App\Models\Responsable;
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Migraciones 18 y 19: campos nuevos de procesos_licitacion y de contratos, estados controlados
 * del contrato y tipos de contrato sembrados. Las pruebas no crean filas hijas (actas, anexos,
 * eventos): en SQLite, poner o quitar una clave foránea reconstruye la tabla y, dentro de la
 * transacción de RefreshDatabase, eso borraría en cascada las filas hijas. En MariaDB no pasa.
 */
class ProcesosContratosAmpliadosTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNAS_PROCESO = ['fuente_id', 'fecha_apertura', 'fecha_cierre', 'responsable_id', 'observaciones'];

    private const COLUMNAS_CONTRATO = [
        'tipo_contrato_id', 'contratista_identificacion', 'fecha_suscripcion', 'supervisor', 'numero_rp',
        'fecha_rp', 'fecha_acta_inicio', 'estado_original', 'usuario_creo_id', 'created_at', 'updated_at',
        'contratante_nit', 'secop_id_contrato', 'secop_proceso', 'url_secop', 'secop_consultado_at',
    ];

    private const TIPOS = ['Obra', 'Interventoría', 'Consultoría', 'Suministro', 'Prestación de servicios', 'Orden de compra'];

    private function migracionProcesos(): object
    {
        return require database_path('migrations/2026_11_18_100017_amplia_procesos_licitacion_contratos.php');
    }

    private function migracionContratos(): object
    {
        return require database_path('migrations/2026_11_18_100018_amplia_contratos.php');
    }

    private function columna(string $tabla, string $columna): ?array
    {
        return collect(Schema::getColumns($tabla))->firstWhere('name', $columna);
    }

    private function foranea(string $tabla, string $columna): ?array
    {
        return collect(Schema::getForeignKeys($tabla))->first(fn ($f) => $f['columns'] === [$columna]);
    }

    private function proyecto(): int
    {
        return DB::table('proyectos')->insertGetId(['nombre' => 'Acueducto']);
    }

    private function proceso(array $cambios = []): int
    {
        return DB::table('procesos_licitacion')->insertGetId([
            'proyecto' => $this->proyecto(), 'codigo_proceso' => 'LP-001', ...$cambios,
        ]);
    }

    private function contrato(array $cambios = []): int
    {
        return DB::table('contratos')->insertGetId([
            'proyecto' => $this->proyecto(), 'n_contrato' => 'C-001', ...$cambios,
        ]);
    }

    private function usuario(): int
    {
        return User::factory()->create()->id;
    }

    /** Deja el esquema como estaba antes de la migración 19, con la tabla de tipos vacía. */
    private function contratosSinMigrar(): object
    {
        $migracion = $this->migracionContratos();
        $migracion->down();
        DB::table('tipos_procesos')->delete();

        return $migracion;
    }

    // ------------------------------------------------------------- procesos (18)

    private function asertarProcesosMigrados(): void
    {
        foreach (self::COLUMNAS_PROCESO as $columna) {
            $definicion = $this->columna('procesos_licitacion', $columna);
            $this->assertNotNull($definicion, $columna);
            $this->assertTrue($definicion['nullable'], $columna);
        }

        $fuente = $this->foranea('procesos_licitacion', 'fuente_id');
        $this->assertSame('fuentes_financiacion', $fuente['foreign_table']);
        $this->assertSame('restrict', strtolower($fuente['on_delete']));

        $responsable = $this->foranea('procesos_licitacion', 'responsable_id');
        $this->assertSame('responsable', $responsable['foreign_table']);
        $this->assertSame('set null', strtolower($responsable['on_delete']));
    }

    private function asertarProcesosRevertidos(): void
    {
        foreach (self::COLUMNAS_PROCESO as $columna) {
            $this->assertFalse(Schema::hasColumn('procesos_licitacion', $columna), $columna);
        }
        $this->assertNull($this->foranea('procesos_licitacion', 'fuente_id'));
        $this->assertNull($this->foranea('procesos_licitacion', 'responsable_id'));
    }

    public function test_el_proceso_tiene_fuente_fechas_responsable_y_observaciones_opcionales(): void
    {
        $this->asertarProcesosMigrados();

        $id = $this->proceso();
        $fila = DB::table('procesos_licitacion')->where('id', $id)->first();
        foreach (self::COLUMNAS_PROCESO as $columna) {
            $this->assertNull($fila->{$columna}, $columna);
        }
    }

    public function test_una_fuente_asignada_a_un_proceso_no_se_borra_y_un_responsable_si_deja_el_campo_nulo(): void
    {
        $fuente = FuenteFinanciacion::first();
        $responsable = Responsable::create(['nombre' => 'Ana', 'activo' => true]);
        $id = $this->proceso(['fuente_id' => $fuente->id, 'responsable_id' => $responsable->id]);

        $responsable->delete();
        $this->assertNull(DB::table('procesos_licitacion')->where('id', $id)->value('responsable_id'));

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('fuentes_financiacion')->where('id', $fuente->id)->delete();
    }

    public function test_el_rollback_de_procesos_se_detiene_si_alguna_columna_tiene_datos(): void
    {
        $fuente = FuenteFinanciacion::first()->id;
        $responsable = Responsable::create(['nombre' => 'Ana', 'activo' => true])->id;
        $id = $this->proceso();

        foreach ([
            'fuente_id' => $fuente, 'fecha_apertura' => '2026-03-01', 'fecha_cierre' => '2026-03-20',
            'responsable_id' => $responsable, 'observaciones' => 'Nota',
        ] as $columna => $valor) {
            DB::table('procesos_licitacion')->where('id', $id)->update([$columna => $valor]);

            try {
                $this->migracionProcesos()->down();
                $this->fail("El rollback debía detenerse con {$columna}.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('procesos de contratación', $e->getMessage(), $columna);
                $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage(), $columna);
            }

            $this->asertarProcesosMigrados();
            DB::table('procesos_licitacion')->where('id', $id)->update([$columna => null]);
        }

        $this->migracionProcesos()->down();
        $this->asertarProcesosRevertidos();
        $this->assertTrue(DB::table('procesos_licitacion')->where('id', $id)->exists());
    }

    public function test_los_procesos_en_ambos_sentidos_se_pueden_repetir_y_conservan_las_filas(): void
    {
        $migracion = $this->migracionProcesos();
        $id = $this->proceso(['codigo_proceso' => 'LP-777']);

        $migracion->up(); // ya migrada: no falla
        $this->asertarProcesosMigrados();

        $migracion->down();
        $migracion->down();
        $this->asertarProcesosRevertidos();

        $migracion->up();
        $migracion->up();
        $this->asertarProcesosMigrados();
        $this->assertSame('LP-777', DB::table('procesos_licitacion')->where('id', $id)->value('codigo_proceso'));
    }

    public function test_reintentar_procesos_tras_un_fallo_a_medias_completa_la_migracion(): void
    {
        $migracion = $this->migracionProcesos();
        $migracion->down();

        // Lo que dejaría un fallo de la subida: una columna con su clave foránea, otra sin ella
        // y otra de datos.
        Schema::table('procesos_licitacion', function (Blueprint $table) {
            $table->foreignId('fuente_id')->nullable()->constrained('fuentes_financiacion')->restrictOnDelete();
        });
        Schema::table('procesos_licitacion', function (Blueprint $table) {
            $table->unsignedBigInteger('responsable_id')->nullable();
            $table->date('fecha_apertura')->nullable();
        });
        $this->assertNull($this->foranea('procesos_licitacion', 'responsable_id'));

        $migracion->up();
        $this->asertarProcesosMigrados();

        // Lo que dejaría un fallo de la bajada: una columna ya sin clave foránea.
        Schema::table('procesos_licitacion', function (Blueprint $table) {
            $table->dropForeign(['responsable_id']);
        });
        $migracion->down();
        $this->asertarProcesosRevertidos();
    }

    // ------------------------------------------------------------- contratos (19): esquema

    private function asertarContratosMigrados(): void
    {
        foreach (self::COLUMNAS_CONTRATO as $columna) {
            $definicion = $this->columna('contratos', $columna);
            $this->assertNotNull($definicion, $columna);
            $this->assertTrue($definicion['nullable'], $columna);
        }

        $tipo = $this->foranea('contratos', 'tipo_contrato_id');
        $this->assertSame('tipos_procesos', $tipo['foreign_table']);
        $this->assertSame('restrict', strtolower($tipo['on_delete']));

        $usuario = $this->foranea('contratos', 'usuario_creo_id');
        $this->assertSame('users', $usuario['foreign_table']);
        $this->assertSame('set null', strtolower($usuario['on_delete']));

        // El proyecto es opcional: la columna admite nulo y borrar el proyecto deja el contrato.
        $this->assertTrue($this->columna('contratos', 'proyecto')['nullable']);
        $proyecto = $this->foranea('contratos', 'proyecto');
        $this->assertSame('proyectos', $proyecto['foreign_table']);
        $this->assertSame('set null', strtolower($proyecto['on_delete']));

        $unico = collect(Schema::getIndexes('contratos'))->first(fn ($i) => $i['unique'] && $i['columns'] === ['secop_id_contrato']);
        $this->assertNotNull($unico);
    }

    private function asertarContratosRevertidos(): void
    {
        foreach (self::COLUMNAS_CONTRATO as $columna) {
            $this->assertFalse(Schema::hasColumn('contratos', $columna), $columna);
        }
        $this->assertNull($this->foranea('contratos', 'tipo_contrato_id'));
        $this->assertNull($this->foranea('contratos', 'usuario_creo_id'));

        $this->assertFalse($this->columna('contratos', 'proyecto')['nullable']);
        $this->assertSame('cascade', strtolower($this->foranea('contratos', 'proyecto')['on_delete']));
    }

    public function test_el_contrato_tiene_las_columnas_nuevas_nulables_con_sus_claves_foraneas(): void
    {
        $this->asertarContratosMigrados();

        $id = $this->contrato();
        $fila = DB::table('contratos')->where('id', $id)->first();
        foreach (self::COLUMNAS_CONTRATO as $columna) {
            $this->assertNull($fila->{$columna}, $columna);
        }
    }

    public function test_un_tipo_asignado_a_un_contrato_no_se_borra_y_un_usuario_si_deja_el_campo_nulo(): void
    {
        $tipo = TipoProceso::first()->id;
        $usuario = $this->usuario();
        $id = $this->contrato(['tipo_contrato_id' => $tipo, 'usuario_creo_id' => $usuario]);

        DB::table('users')->where('id', $usuario)->delete();
        $this->assertNull(DB::table('contratos')->where('id', $id)->value('usuario_creo_id'));

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('tipos_procesos')->where('id', $tipo)->delete();
    }

    // ------------------------------------------------------------- contratos (19): estados

    public function test_convierte_los_estados_reales_y_conserva_el_texto_original(): void
    {
        $migracion = $this->contratosSinMigrar();

        $casos = [
            'En ejecución' => 'en_ejecucion',
            'vigente' => 'en_ejecucion',
            'liquidado' => 'liquidado',
            'ejecutado' => 'terminado',
            'Suspendido ' => 'suspendido',
            '  ANULADO' => 'anulado',
            'Terminado' => 'terminado',
            'celebrado' => 'celebrado',
            'En   Ejecucion' => 'en_ejecucion',
            '' => 'en_ejecucion',
            'Cualquier cosa' => 'en_ejecucion',
        ];
        $ids = [];
        foreach (array_keys($casos) as $original) {
            $ids[$original] = $this->contrato(['estado' => $original]);
        }
        $nulo = $this->contrato(['estado' => null]);

        $migracion->up();

        foreach ($casos as $original => $esperado) {
            $fila = DB::table('contratos')->where('id', $ids[$original])->first();
            $this->assertSame($esperado, $fila->estado, "'{$original}'");
            $this->assertSame($original, $fila->estado_original, "'{$original}'");
        }
        $fila = DB::table('contratos')->where('id', $nulo)->first();
        $this->assertSame('en_ejecucion', $fila->estado);
        $this->assertNull($fila->estado_original);
    }

    public function test_los_contratos_antiguos_quedan_sin_fechas_de_registro_ni_datos_nuevos(): void
    {
        $migracion = $this->contratosSinMigrar();
        $id = $this->contrato(['estado' => 'vigente', 'valor' => 1000]);

        $migracion->up();

        $fila = DB::table('contratos')->where('id', $id)->first();
        $this->assertNull($fila->created_at);
        $this->assertNull($fila->updated_at);
        $this->assertNull($fila->tipo_contrato_id);
        $this->assertNull($fila->usuario_creo_id);
        $this->assertEquals(1000, $fila->valor);
    }

    public function test_avisa_de_los_estados_no_reconocidos_con_su_id_y_su_texto(): void
    {
        $migracion = $this->contratosSinMigrar();
        $raro = $this->contrato(['estado' => 'Quien sabe']);
        $this->contrato(['estado' => 'vigente']);
        Log::spy();

        $migracion->up();

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $mensaje) => str_contains($mensaje, "#{$raro}") && str_contains($mensaje, 'Quien sabe')
        );
    }

    public function test_no_avisa_si_todos_los_estados_se_reconocen(): void
    {
        $migracion = $this->contratosSinMigrar();
        $this->contrato(['estado' => 'En ejecución']);
        $this->contrato(['estado' => null]);
        Log::spy();

        $migracion->up();

        Log::shouldNotHaveReceived('warning');
    }

    // ------------------------------------------------------------- contratos (19): tipos

    public function test_siembra_los_seis_tipos_de_contrato_solo_si_la_tabla_esta_vacia(): void
    {
        $this->assertSame(self::TIPOS, TipoProceso::orderBy('id')->pluck('nombre')->all());
        $this->assertTrue(TipoProceso::all()->every->activo);

        $migracion = $this->contratosSinMigrar();
        TipoProceso::create(['nombre' => 'Otro', 'activo' => true]);
        $migracion->up();
        $this->assertSame(['Otro'], TipoProceso::pluck('nombre')->all());

        $migracion->down();
        TipoProceso::query()->delete();
        $migracion->up();
        $migracion->up(); // repetida: la tabla ya no está vacía
        $this->assertSame(self::TIPOS, TipoProceso::orderBy('id')->pluck('nombre')->all());
    }

    // ------------------------------------------------------------- contratos (19): modelos

    public function test_el_modelo_de_contrato_conoce_estados_campos_relaciones_y_marcas_de_tiempo(): void
    {
        $this->assertSame(
            ['celebrado', 'en_ejecucion', 'suspendido', 'terminado', 'liquidado', 'anulado'],
            Contrato::ESTADOS
        );
        $this->assertTrue((new Contrato())->usesTimestamps());

        $tipo = TipoProceso::first();
        $usuario = $this->usuario();
        $proyecto = $this->proyecto();
        $contrato = Contrato::create([
            'proyecto' => $proyecto, 'n_contrato' => 'C-9', 'estado' => 'celebrado',
            'tipo_contrato_id' => $tipo->id, 'contratista_identificacion' => '900123456-1',
            'fecha_suscripcion' => '2026-02-10', 'supervisor' => 'Luis Pérez', 'numero_rp' => 'RP-55',
            'fecha_rp' => '2026-02-12', 'fecha_acta_inicio' => '2026-02-20', 'usuario_creo_id' => $usuario,
        ])->fresh();

        $this->assertSame('2026-02-10', $contrato->fecha_suscripcion->toDateString());
        $this->assertSame('2026-02-12', $contrato->fecha_rp->toDateString());
        $this->assertSame('2026-02-20', $contrato->fecha_acta_inicio->toDateString());
        $this->assertSame('900123456-1', $contrato->contratista_identificacion);
        $this->assertSame($tipo->nombre, $contrato->tipoContrato->nombre);
        $this->assertSame($usuario, $contrato->usuario_creo_id);
        $this->assertNotNull($contrato->created_at);
        $this->assertNotNull($contrato->updated_at);
    }

    public function test_el_modelo_de_proceso_acepta_revocado_y_conoce_los_campos_nuevos(): void
    {
        $this->assertContains('revocado', ProcesoLicitacion::ESTADOS);
        $this->assertSame('revocado', ProcesoLicitacion::REVOCADO);

        $fuente = FuenteFinanciacion::first();
        $responsable = Responsable::create(['nombre' => 'Ana', 'activo' => true]);
        $proceso = ProcesoLicitacion::create([
            'proyecto' => $this->proyecto(), 'codigo_proceso' => 'LP-5', 'estado' => 'revocado',
            'fuente_id' => $fuente->id, 'responsable_id' => $responsable->id,
            'fecha_apertura' => '2026-03-01', 'fecha_cierre' => '2026-03-20', 'observaciones' => 'Nota',
        ])->fresh();

        $this->assertSame('revocado', $proceso->estado);
        $this->assertSame('2026-03-01', $proceso->fecha_apertura->toDateString());
        $this->assertSame('2026-03-20', $proceso->fecha_cierre->toDateString());
        $this->assertSame($fuente->id, $proceso->fuente->id);
        $this->assertSame('Ana', $proceso->responsable->nombre);
        $this->assertSame('Nota', $proceso->observaciones);
    }

    // ------------------------------------------------------------- contratos (19): rollback

    public function test_el_rollback_restaura_los_estados_borra_los_tipos_sembrados_y_quita_las_columnas(): void
    {
        $migracion = $this->contratosSinMigrar();
        $a = $this->contrato(['estado' => 'En ejecución']);
        $b = $this->contrato(['estado' => 'vigente']);
        $c = $this->contrato(['estado' => 'Suspendido ']);
        $d = $this->contrato(['estado' => null]);
        $e = $this->contrato(['estado' => 'Cualquier cosa']);
        $migracion->up();
        $this->assertSame(6, TipoProceso::count());

        $migracion->down();

        $this->asertarContratosRevertidos();
        $this->assertSame(
            ['En ejecución', 'vigente', 'Suspendido ', null, 'Cualquier cosa'],
            collect([$a, $b, $c, $d, $e])->map(fn ($id) => DB::table('contratos')->where('id', $id)->value('estado'))->all()
        );
        $this->assertSame(0, TipoProceso::count());
    }

    public function test_el_rollback_conserva_los_tipos_que_alguien_usa_y_los_que_no_son_sembrados(): void
    {
        $migracion = $this->migracionContratos();
        $obra = TipoProceso::where('nombre', 'Obra')->value('id');
        $otro = TipoProceso::create(['nombre' => 'Convenio', 'activo' => true])->id;
        $enProceso = TipoProceso::where('nombre', 'Suministro')->value('id');
        $this->proceso(['tipo_proceso' => $enProceso]);

        $migracion->down();

        $this->assertSame(['Convenio', 'Suministro'], TipoProceso::orderBy('nombre')->pluck('nombre')->all());
        $this->assertNull(TipoProceso::find($obra));
        $this->assertNotNull(TipoProceso::find($otro));
    }

    public function test_el_rollback_se_detiene_si_algun_contrato_tiene_datos_nuevos(): void
    {
        $id = $this->contrato(['estado' => 'en_ejecucion']);
        $tipo = TipoProceso::first()->id;
        $usuario = $this->usuario();

        foreach ([
            'tipo_contrato_id' => $tipo, 'contratista_identificacion' => '900', 'fecha_suscripcion' => '2026-01-01',
            'supervisor' => 'Luis', 'numero_rp' => 'RP-1', 'fecha_rp' => '2026-01-02',
            'fecha_acta_inicio' => '2026-01-03', 'usuario_creo_id' => $usuario,
            'contratante_nit' => '800.1', 'secop_id_contrato' => 'CO1.PCCNTR.1', 'secop_proceso' => 'CO1.REQ.1',
            'url_secop' => 'https://example.test/c', 'secop_consultado_at' => '2026-01-04 08:00:00',
        ] as $columna => $valor) {
            DB::table('contratos')->where('id', $id)->update([$columna => $valor]);

            try {
                $this->migracionContratos()->down();
                $this->fail("El rollback debía detenerse con {$columna}.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('contratos', $e->getMessage(), $columna);
                $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage(), $columna);
            }

            $this->asertarContratosMigrados();
            DB::table('contratos')->where('id', $id)->update([$columna => null]);
        }

        $this->migracionContratos()->down();
        $this->asertarContratosRevertidos();
    }

    public function test_el_rollback_se_detiene_si_algun_contrato_se_creo_despues(): void
    {
        $this->contrato(['estado' => 'en_ejecucion', 'created_at' => '2026-05-01 10:00:00']);

        try {
            $this->migracionContratos()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('después', $e->getMessage());
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }

        $this->asertarContratosMigrados();
    }

    public function test_el_rollback_se_detiene_si_alguien_cambio_el_estado_de_un_contrato(): void
    {
        $migracion = $this->contratosSinMigrar();
        $id = $this->contrato(['estado' => 'vigente']);
        $migracion->up();
        DB::table('contratos')->where('id', $id)->update(['estado' => 'liquidado']);

        try {
            $migracion->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('estado', $e->getMessage());
            $this->assertStringContainsString("#{$id}", $e->getMessage());
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }

        $this->asertarContratosMigrados();
        $this->assertSame('vigente', DB::table('contratos')->where('id', $id)->value('estado_original'));
    }

    public function test_los_contratos_en_ambos_sentidos_se_pueden_repetir_sin_perder_estados(): void
    {
        $migracion = $this->contratosSinMigrar();
        $a = $this->contrato(['estado' => 'vigente']);
        $b = $this->contrato(['estado' => null]);
        $c = $this->contrato(['estado' => 'liquidado']);

        $migracion->up();
        $migracion->up(); // repetida: no vuelve a convertir
        $this->asertarContratosMigrados();
        $fila = fn (int $id) => DB::table('contratos')->where('id', $id)->first();
        $this->assertSame(['vigente', null, 'liquidado'], [$fila($a)->estado_original, $fila($b)->estado_original, $fila($c)->estado_original]);
        $this->assertSame(['en_ejecucion', 'en_ejecucion', 'liquidado'], [$fila($a)->estado, $fila($b)->estado, $fila($c)->estado]);

        $migracion->down();
        $migracion->down();
        $this->asertarContratosRevertidos();
        $this->assertSame(['vigente', null, 'liquidado'], [$fila($a)->estado, $fila($b)->estado, $fila($c)->estado]);

        $migracion->up();
        $this->asertarContratosMigrados();
        $this->assertSame(['en_ejecucion', 'en_ejecucion', 'liquidado'], [$fila($a)->estado, $fila($b)->estado, $fila($c)->estado]);
    }

    public function test_reintentar_contratos_tras_un_fallo_a_medias_en_la_subida_completa_la_migracion(): void
    {
        $migracion = $this->contratosSinMigrar();
        $a = $this->contrato(['estado' => 'vigente']);

        // Lo que dejaría un fallo de la subida: columnas agregadas, una clave foránea puesta, la
        // otra no, y los estados sin convertir.
        Schema::table('contratos', function (Blueprint $table) {
            $table->foreignId('tipo_contrato_id')->nullable()->constrained('tipos_procesos')->restrictOnDelete();
        });
        Schema::table('contratos', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_creo_id')->nullable();
            $table->string('estado_original', 30)->nullable();
            $table->string('supervisor', 255)->nullable();
        });
        $this->assertNull($this->foranea('contratos', 'usuario_creo_id'));

        $migracion->up();

        $this->asertarContratosMigrados();
        $fila = DB::table('contratos')->where('id', $a)->first();
        $this->assertSame('en_ejecucion', $fila->estado);
        $this->assertSame('vigente', $fila->estado_original);
        $this->assertSame(self::TIPOS, TipoProceso::orderBy('id')->pluck('nombre')->all());
    }

    public function test_reintentar_contratos_tras_un_fallo_a_medias_en_la_bajada_completa_la_reversion(): void
    {
        $migracion = $this->contratosSinMigrar();
        $a = $this->contrato(['estado' => 'vigente']);
        $migracion->up();

        // Lo que dejaría un fallo de la bajada: estados ya restaurados, tipos borrados, una
        // clave foránea ya quitada y una columna ya borrada.
        DB::table('contratos')->where('id', $a)->update(['estado' => 'vigente']);
        DB::table('tipos_procesos')->delete();
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropForeign(['usuario_creo_id']);
        });
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('usuario_creo_id');
        });

        $migracion->down();

        $this->asertarContratosRevertidos();
        $this->assertSame('vigente', DB::table('contratos')->where('id', $a)->value('estado'));
    }

    // ------------------------------------------------------------- contratos (19): proyecto y SECOP

    public function test_borrar_un_proyecto_deja_sus_contratos_sin_proyecto(): void
    {
        // Regla nueva: un contrato no necesita proyecto, así que ya no se borra con él.
        $proyecto = $this->proyecto();
        $conProyecto = $this->contrato(['proyecto' => $proyecto]);
        $sinProyecto = $this->contrato(['proyecto' => null, 'n_contrato' => 'C-2']);

        DB::table('proyectos')->where('id', $proyecto)->delete();

        $this->assertNull(DB::table('contratos')->where('id', $conProyecto)->value('proyecto'));
        $this->assertTrue(DB::table('contratos')->where('id', $sinProyecto)->exists());
        $this->assertNull(Contrato::find($conProyecto)->proyecto);
        $this->assertNull(Contrato::find($conProyecto)->proyectoRel);
    }

    public function test_el_id_de_secop_del_contrato_no_se_repite_pero_puede_faltar(): void
    {
        $this->contrato(['secop_id_contrato' => null]);
        $this->contrato(['secop_id_contrato' => null]);
        $this->contrato(['secop_id_contrato' => 'CO1.PCCNTR.100']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->contrato(['secop_id_contrato' => 'CO1.PCCNTR.100']);
    }

    public function test_el_modelo_guarda_la_referencia_de_secop_y_el_nit(): void
    {
        $contrato = Contrato::create([
            'proyecto' => null, 'n_contrato' => 'C-7', 'contratante_nit' => '890.980.040-8',
            'secop_id_contrato' => 'CO1.PCCNTR.7', 'secop_proceso' => 'CO1.REQ.7',
            'url_secop' => 'https://community.secop.gov.co/x', 'secop_consultado_at' => '2026-11-18 09:30:00',
        ])->fresh();

        $this->assertNull($contrato->proyecto);
        $this->assertSame('890.980.040-8', $contrato->contratante_nit);
        $this->assertSame('CO1.PCCNTR.7', $contrato->secop_id_contrato);
        $this->assertSame('CO1.REQ.7', $contrato->secop_proceso);
        $this->assertSame('2026-11-18 09:30:00', $contrato->secop_consultado_at->format('Y-m-d H:i:s'));
    }

    public function test_el_rollback_se_detiene_si_algun_contrato_no_tiene_proyecto_y_despues_restaura_la_cascada(): void
    {
        $id = $this->contrato(['proyecto' => null]);

        try {
            $this->migracionContratos()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sin proyecto', $e->getMessage());
            $this->assertStringContainsString('restaure un respaldo.', $e->getMessage());
        }
        $this->asertarContratosMigrados();

        $proyecto = $this->proyecto();
        DB::table('contratos')->where('id', $id)->update(['proyecto' => $proyecto]);
        $this->migracionContratos()->down();

        $this->asertarContratosRevertidos();
        DB::table('proyectos')->where('id', $proyecto)->delete();
        $this->assertFalse(DB::table('contratos')->where('id', $id)->exists());
    }

    public function test_reintentar_el_proyecto_opcional_tras_un_fallo_a_medias(): void
    {
        $migracion = $this->contratosSinMigrar();

        // Subida: la clave foránea en cascada ya se quitó y la columna ya admite nulo.
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropForeign(['proyecto']);
        });
        Schema::table('contratos', function (Blueprint $table) {
            $table->unsignedBigInteger('proyecto')->nullable()->change();
        });
        $migracion->up();
        $this->asertarContratosMigrados();

        // Bajada: la clave foránea ya se quitó y la columna sigue admitiendo nulo.
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropForeign(['proyecto']);
        });
        $migracion->down();
        $this->asertarContratosRevertidos();
    }
}
```

En `tests/Feature/Schema/ContratacionCatalogosTest.php`, reemplazar (1 de 1):

```php
        $this->assertTrue($proceso->usuarioCreo->is($usuario));
        $this->assertSame(['secop_ii', 'secop_i', 'tvec', 'ninguna'], ProcesoLicitacion::PLATAFORMAS);
        $this->assertSame(
            ['preparacion', 'publicado', 'evaluacion', 'adjudicado', 'desierto', 'celebrado', 'cancelado'],
            ProcesoLicitacion::ESTADOS
        );
    }
```

por:

```php
        $this->assertTrue($proceso->usuarioCreo->is($usuario));
        $this->assertSame(['secop_ii', 'secop_i', 'tvec', 'ninguna'], ProcesoLicitacion::PLATAFORMAS);
        $this->assertSame(
            ['preparacion', 'publicado', 'evaluacion', 'adjudicado', 'desierto', 'celebrado', 'cancelado', 'revocado'],
            ProcesoLicitacion::ESTADOS
        );
    }
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Schema/ProcesosContratosAmpliadosTest.php tests/Feature/Schema/ContratacionCatalogosTest.php`
Expected: FALLAN 28 y pasan 20: no existen las columnas nuevas de `procesos_licitacion` ni de `contratos`, la conversión de estados, la siembra de tipos ni el proyecto opcional, y `ProcesoLicitacion::ESTADOS` todavía no tiene `revocado`.

- [ ] **Paso 3: Implementar**

Crear `database/migrations/2026_11_18_100017_amplia_procesos_licitacion_contratos.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Un proceso de contratación registra además la fuente de financiación, las fechas de apertura y
 * cierre, un responsable y observaciones. Todo es opcional: los procesos que ya existen quedan
 * sin esos datos. Los estados válidos los controla la aplicación (ProcesoLicitacion::ESTADOS,
 * que ahora incluye revocado); la columna `estado` no cambia.
 */
return new class extends Migration
{
    private const COLUMNAS = ['fuente_id', 'fecha_apertura', 'fecha_cierre', 'responsable_id', 'observaciones'];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. Cada clave foránea va después de su columna.
        $this->agregar('fuente_id', fn (Blueprint $t) => $t->unsignedBigInteger('fuente_id')->nullable());
        if (! $this->foranea('fuente_id')) {
            Schema::table('procesos_licitacion', function (Blueprint $table) {
                $table->foreign('fuente_id')->references('id')->on('fuentes_financiacion')->restrictOnDelete();
            });
        }
        $this->agregar('fecha_apertura', fn (Blueprint $t) => $t->date('fecha_apertura')->nullable());
        $this->agregar('fecha_cierre', fn (Blueprint $t) => $t->date('fecha_cierre')->nullable());
        $this->agregar('responsable_id', fn (Blueprint $t) => $t->unsignedBigInteger('responsable_id')->nullable());
        if (! $this->foranea('responsable_id')) {
            Schema::table('procesos_licitacion', function (Blueprint $table) {
                $table->foreign('responsable_id')->references('id')->on('responsable')->nullOnDelete();
            });
        }
        $this->agregar('observaciones', fn (Blueprint $t) => $t->text('observaciones')->nullable());
    }

    public function down(): void
    {
        $existentes = array_values(array_filter(self::COLUMNAS, fn (string $c) => Schema::hasColumn('procesos_licitacion', $c)));

        $conDatos = $existentes && DB::table('procesos_licitacion')
            ->where(function ($q) use ($existentes) {
                foreach ($existentes as $columna) {
                    $q->orWhereNotNull($columna);
                }
            })
            ->exists();
        if ($conDatos) {
            throw new RuntimeException('No se puede revertir: hay procesos de contratación con fuente de financiación, fechas de apertura o cierre, responsable u observaciones registrados, que se perderían. Restaure un respaldo.');
        }

        // Primero las claves foráneas, después las columnas; cada paso con su guarda. Se quitan
        // por el nombre que entrega el esquema; SQLite no da nombre y ahí se deja la deducción de Laravel.
        foreach (['fuente_id', 'responsable_id'] as $columna) {
            if ($foranea = $this->foranea($columna)) {
                $nombre = $foranea['name'] ?? [$columna];
                Schema::table('procesos_licitacion', function (Blueprint $table) use ($nombre) {
                    $table->dropForeign($nombre);
                });
            }
        }
        foreach ($existentes as $columna) {
            Schema::table('procesos_licitacion', function (Blueprint $table) use ($columna) {
                $table->dropColumn($columna);
            });
        }
    }

    private function agregar(string $columna, Closure $definicion): void
    {
        if (! Schema::hasColumn('procesos_licitacion', $columna)) {
            Schema::table('procesos_licitacion', fn (Blueprint $table) => $definicion($table));
        }
    }

    private function foranea(string $columna): ?array
    {
        return collect(Schema::getForeignKeys('procesos_licitacion'))->first(fn ($f) => $f['columns'] === [$columna]);
    }
};
```

Crear `database/migrations/2026_11_18_100018_amplia_contratos.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * Un contrato registra además su tipo, la identificación del contratista, la fecha de
 * suscripción, el supervisor, el registro presupuestal (número y fecha), la fecha del acta de
 * inicio, el usuario que lo creó y sus marcas de tiempo (nulas en los contratos antiguos).
 *
 * El estado pasa de texto libre a un valor controlado (Contrato::ESTADOS: celebrado, en_ejecucion,
 * suspendido, terminado, liquidado, anulado). El texto que tenía cada contrato se conserva en
 * `estado_original`, que sirve para revertir y para auditar la conversión.
 *
 * El proyecto del contrato pasa a ser opcional (la empresa también ejecuta contratos de proyectos
 * que no formuló): `proyecto` admite nulo y, al borrar un proyecto, sus contratos quedan sin
 * proyecto en vez de borrarse. Se agregan además la referencia de SECOP II y el NIT del contratante.
 *
 * Los tipos de contrato son el catálogo `tipos_procesos`: se siembran solo si está vacío.
 */
return new class extends Migration
{
    private const NUEVAS = [
        'tipo_contrato_id', 'contratista_identificacion', 'fecha_suscripcion', 'supervisor',
        'numero_rp', 'fecha_rp', 'fecha_acta_inicio', 'usuario_creo_id',
        'contratante_nit', 'secop_id_contrato', 'secop_proceso', 'url_secop', 'secop_consultado_at',
    ];

    private const COLUMNAS = [...self::NUEVAS, 'estado_original', 'created_at', 'updated_at'];

    // Estado de hoy (sin mayúsculas, tildes ni espacios sobrantes) => estado controlado.
    private const ESTADOS = [
        'en ejecucion' => 'en_ejecucion',
        'vigente' => 'en_ejecucion',
        'liquidado' => 'liquidado',
        'suspendido' => 'suspendido',
        'anulado' => 'anulado',
        'ejecutado' => 'terminado',
        'terminado' => 'terminado',
        'celebrado' => 'celebrado',
    ];

    private const TIPOS = ['Obra', 'Interventoría', 'Consultoría', 'Suministro', 'Prestación de servicios', 'Orden de compra'];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. Cada clave foránea va después de su columna.
        $estadoOriginalNuevo = ! Schema::hasColumn('contratos', 'estado_original');

        $this->agregar('tipo_contrato_id', fn (Blueprint $t) => $t->unsignedBigInteger('tipo_contrato_id')->nullable());
        if (! $this->foranea('tipo_contrato_id')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->foreign('tipo_contrato_id')->references('id')->on('tipos_procesos')->restrictOnDelete();
            });
        }
        $this->agregar('contratista_identificacion', fn (Blueprint $t) => $t->string('contratista_identificacion', 30)->nullable());
        $this->agregar('fecha_suscripcion', fn (Blueprint $t) => $t->date('fecha_suscripcion')->nullable());
        $this->agregar('supervisor', fn (Blueprint $t) => $t->string('supervisor', 255)->nullable());
        $this->agregar('numero_rp', fn (Blueprint $t) => $t->string('numero_rp', 40)->nullable());
        $this->agregar('fecha_rp', fn (Blueprint $t) => $t->date('fecha_rp')->nullable());
        $this->agregar('fecha_acta_inicio', fn (Blueprint $t) => $t->date('fecha_acta_inicio')->nullable());
        $this->agregar('estado_original', fn (Blueprint $t) => $t->string('estado_original', 30)->nullable());
        $this->agregar('usuario_creo_id', fn (Blueprint $t) => $t->unsignedBigInteger('usuario_creo_id')->nullable());
        if (! $this->foranea('usuario_creo_id')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->foreign('usuario_creo_id')->references('id')->on('users')->nullOnDelete();
            });
        }
        $this->agregar('created_at', fn (Blueprint $t) => $t->timestamp('created_at')->nullable());
        $this->agregar('updated_at', fn (Blueprint $t) => $t->timestamp('updated_at')->nullable());

        $this->agregar('contratante_nit', fn (Blueprint $t) => $t->string('contratante_nit', 30)->nullable());
        $this->agregar('secop_id_contrato', fn (Blueprint $t) => $t->string('secop_id_contrato', 40)->nullable());
        if (! $this->indiceSecop()) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->unique('secop_id_contrato');
            });
        }
        $this->agregar('secop_proceso', fn (Blueprint $t) => $t->string('secop_proceso', 40)->nullable());
        $this->agregar('url_secop', fn (Blueprint $t) => $t->string('url_secop', 500)->nullable());
        $this->agregar('secop_consultado_at', fn (Blueprint $t) => $t->timestamp('secop_consultado_at')->nullable());

        // Proyecto opcional: primero se quita la clave foránea en cascada, después la columna
        // admite nulo y al final se pone la clave foránea que deja el contrato sin proyecto.
        if (($foranea = $this->foranea('proyecto')) && strtolower($foranea['on_delete']) !== 'set null') {
            $this->quitarForanea($foranea, 'proyecto');
        }
        if (! $this->columnaProyecto()['nullable']) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->unsignedBigInteger('proyecto')->nullable()->change();
            });
        }
        if (! $this->foranea('proyecto')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->foreign('proyecto')->references('id')->on('proyectos')->nullOnDelete();
            });
        }

        // La conversión de estados es un solo paso atómico: copia el texto y lo convierte. Se
        // omite si ya se hizo (algún contrato conserva su estado original); así repetir la
        // migración, o reintentarla después de haber convertido, no pisa el estado original.
        // Solo no se distingue el caso en que todos los contratos tenían el estado nulo.
        if ($estadoOriginalNuevo || ! DB::table('contratos')->whereNotNull('estado_original')->exists()) {
            $this->convertirEstados();
        }

        if (! DB::table('tipos_procesos')->exists()) {
            DB::table('tipos_procesos')->insert(array_map(fn (string $nombre) => ['nombre' => $nombre, 'activo' => true], self::TIPOS));
        }
    }

    public function down(): void
    {
        $existentes = array_values(array_filter(self::COLUMNAS, fn (string $c) => Schema::hasColumn('contratos', $c)));

        $datos = array_values(array_filter(self::NUEVAS, fn (string $c) => in_array($c, $existentes, true)));
        if ($datos && DB::table('contratos')->where(function ($q) use ($datos) {
            foreach ($datos as $columna) {
                $q->orWhereNotNull($columna);
            }
        })->exists()) {
            throw new RuntimeException('No se puede revertir: hay contratos con tipo de contrato, identificación del contratista, fechas de suscripción, registro presupuestal o acta de inicio, supervisor o usuario que los creó registrados, que se perderían. Restaure un respaldo.');
        }

        if ($this->columnaProyecto()['nullable'] && DB::table('contratos')->whereNull('proyecto')->exists()) {
            throw new RuntimeException('No se puede revertir: hay contratos sin proyecto, que no podrían existir en el esquema anterior. Asígneles un proyecto o restaure un respaldo.');
        }

        if (in_array('created_at', $existentes, true) && DB::table('contratos')->whereNotNull('created_at')->exists()) {
            throw new RuntimeException('No se puede revertir: hay contratos creados después de la migración, que se perderían. Restaure un respaldo.');
        }

        if (in_array('estado_original', $existentes, true)) {
            // Vale el estado que dejó la conversión o el original (si un intento anterior ya lo
            // restauró); cualquier otro lo cambió alguien después y se perdería.
            $cambiados = DB::table('contratos')->get(['id', 'estado', 'estado_original'])
                ->filter(fn ($c) => $c->estado !== $this->convertir($c->estado_original) && $c->estado !== $c->estado_original)
                ->map(fn ($c) => '#' . $c->id)
                ->all();
            if ($cambiados) {
                throw new RuntimeException('No se puede revertir: el estado de algunos contratos (' . implode(', ', $cambiados) . ') se cambió después de la migración y ese cambio se perdería. Restaure un respaldo.');
            }

            DB::table('contratos')->update(['estado' => DB::raw('estado_original')]);
        }

        // Los tipos sembrados que nadie usa; los que usa un contrato o un proceso se conservan.
        $usados = DB::table('procesos_licitacion')->whereNotNull('tipo_proceso')->pluck('tipo_proceso');
        if (in_array('tipo_contrato_id', $existentes, true)) {
            $usados = $usados->merge(DB::table('contratos')->whereNotNull('tipo_contrato_id')->pluck('tipo_contrato_id'));
        }
        DB::table('tipos_procesos')->whereIn('nombre', self::TIPOS)->whereNotIn('id', $usados->all())->delete();

        // Proyecto obligatorio otra vez, con la cascada de antes: primero se cambia la clave
        // foránea, después la columna y al final se pone la clave en cascada.
        if (($foranea = $this->foranea('proyecto')) && strtolower($foranea['on_delete']) !== 'cascade') {
            $this->quitarForanea($foranea, 'proyecto');
        }
        if ($this->columnaProyecto()['nullable']) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->unsignedBigInteger('proyecto')->nullable(false)->change();
            });
        }
        if (! $this->foranea('proyecto')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->foreign('proyecto')->references('id')->on('proyectos')->cascadeOnDelete();
            });
        }

        // Primero el índice único y las claves foráneas, después las columnas; cada paso con su
        // guarda. Se quitan por el nombre que entrega el esquema; SQLite no da nombre y ahí se
        // deja la deducción de Laravel.
        if ($indice = $this->indiceSecop()) {
            Schema::table('contratos', function (Blueprint $table) use ($indice) {
                $table->dropUnique($indice['name']);
            });
        }
        foreach (['tipo_contrato_id', 'usuario_creo_id'] as $columna) {
            if ($foranea = $this->foranea($columna)) {
                $this->quitarForanea($foranea, $columna);
            }
        }
        foreach ($existentes as $columna) {
            Schema::table('contratos', function (Blueprint $table) use ($columna) {
                $table->dropColumn($columna);
            });
        }
    }

    private function convertirEstados(): void
    {
        $noReconocidos = [];

        DB::transaction(function () use (&$noReconocidos) {
            foreach (DB::table('contratos')->get(['id', 'estado']) as $contrato) {
                $texto = trim((string) $contrato->estado);
                if ($texto !== '' && ! isset(self::ESTADOS[$this->clave($texto)])) {
                    $noReconocidos[] = "#{$contrato->id} '{$contrato->estado}'";
                }

                DB::table('contratos')->where('id', $contrato->id)->update([
                    'estado_original' => $contrato->estado,
                    'estado' => $this->convertir($contrato->estado),
                ]);
            }
        });

        if ($noReconocidos) {
            $mensaje = 'Contratos con un estado no reconocido, que pasaron a en_ejecucion (su texto original queda en estado_original): ' . implode('; ', $noReconocidos) . '.';
            Log::warning($mensaje);
            if (defined('STDERR') && ! app()->runningUnitTests()) {
                fwrite(STDERR, "  AVISO: {$mensaje}\n");
            }
        }
    }

    private function convertir(?string $estado): string
    {
        return self::ESTADOS[$this->clave((string) $estado)] ?? 'en_ejecucion';
    }

    private function clave(string $estado): string
    {
        return Str::ascii(mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $estado))));
    }

    private function agregar(string $columna, Closure $definicion): void
    {
        if (! Schema::hasColumn('contratos', $columna)) {
            Schema::table('contratos', fn (Blueprint $table) => $definicion($table));
        }
    }

    private function quitarForanea(array $foranea, string $columna): void
    {
        $nombre = $foranea['name'] ?? [$columna];
        Schema::table('contratos', function (Blueprint $table) use ($nombre) {
            $table->dropForeign($nombre);
        });
    }

    private function columnaProyecto(): array
    {
        return collect(Schema::getColumns('contratos'))->firstWhere('name', 'proyecto');
    }

    private function indiceSecop(): ?array
    {
        return collect(Schema::getIndexes('contratos'))->first(fn ($i) => $i['unique'] && $i['columns'] === ['secop_id_contrato']);
    }

    private function foranea(string $columna): ?array
    {
        return collect(Schema::getForeignKeys('contratos'))->first(fn ($f) => $f['columns'] === [$columna]);
    }
};
```

En `app/Models/Contrato.php`, reemplazar (1 de 3):

```php

class Contrato extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'proyecto', 'n_contrato', 'objeto', 'contratante', 'contratista', 'valor',
        'fecha_inicio', 'fecha_fin', 'interventoria', 'avance_fisico', 'avance_financiero',
        'estado', 'anticipo', 'porcentaje_anticipo', 'proceso_licitacion',
        'valor_inicial', 'fecha_fin_inicial',
    ];

    protected $casts = [
```

por:

```php

class Contrato extends Model
{
    public const CELEBRADO = 'celebrado';
    public const EN_EJECUCION = 'en_ejecucion';
    public const SUSPENDIDO = 'suspendido';
    public const TERMINADO = 'terminado';
    public const LIQUIDADO = 'liquidado';
    public const ANULADO = 'anulado';
    public const ESTADOS = [
        self::CELEBRADO, self::EN_EJECUCION, self::SUSPENDIDO, self::TERMINADO, self::LIQUIDADO, self::ANULADO,
    ];

    // Los contratos antiguos tienen las marcas de tiempo nulas. El resto del código los escribe
    // con DB::table, que no las llena; solo el modelo y el servicio de contratos las mantienen.
    public $timestamps = true;

    protected $fillable = [
        'proyecto', 'n_contrato', 'objeto', 'contratante', 'contratista', 'valor',
        'fecha_inicio', 'fecha_fin', 'interventoria', 'avance_fisico', 'avance_financiero',
        'estado', 'anticipo', 'porcentaje_anticipo', 'proceso_licitacion',
        'valor_inicial', 'fecha_fin_inicial',
        'tipo_contrato_id', 'contratista_identificacion', 'fecha_suscripcion', 'supervisor',
        'numero_rp', 'fecha_rp', 'fecha_acta_inicio', 'usuario_creo_id',
        'contratante_nit', 'secop_id_contrato', 'secop_proceso', 'url_secop', 'secop_consultado_at',
    ];

    protected $casts = [
```

En `app/Models/Contrato.php`, reemplazar (2 de 3):

```php
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_fin_inicial' => 'date',
        'anticipo' => 'boolean',
    ];

    // proyectoRel() not proyecto(): the FK column is also called `proyecto` (see Task 4's note
```

por:

```php
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_fin_inicial' => 'date',
        'fecha_suscripcion' => 'date',
        'fecha_rp' => 'date',
        'fecha_acta_inicio' => 'date',
        'secop_consultado_at' => 'datetime',
        'anticipo' => 'boolean',
        'tipo_contrato_id' => 'integer',
        'usuario_creo_id' => 'integer',
    ];

    // proyectoRel() not proyecto(): the FK column is also called `proyecto` (see Task 4's note
```

En `app/Models/Contrato.php`, reemplazar (3 de 3):

```php
        return $this->belongsTo(ProcesoLicitacion::class, 'proceso_licitacion');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(AnexoContrato::class, 'contrato_id');
```

por:

```php
        return $this->belongsTo(ProcesoLicitacion::class, 'proceso_licitacion');
    }

    public function tipoContrato(): BelongsTo
    {
        return $this->belongsTo(TipoProceso::class, 'tipo_contrato_id');
    }

    public function usuarioCreo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_creo_id');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(AnexoContrato::class, 'contrato_id');
```

En `app/Models/ProcesoLicitacion.php`, reemplazar (1 de 3):

```php
    public const DESIERTO = 'desierto';
    public const CELEBRADO = 'celebrado';
    public const CANCELADO = 'cancelado';
    public const ESTADOS = [
        self::PREPARACION, self::PUBLICADO, self::EVALUACION, self::ADJUDICADO,
        self::DESIERTO, self::CELEBRADO, self::CANCELADO,
    ];

    protected $table = 'procesos_licitacion';
    protected $fillable = [
        'proyecto', 'objeto', 'codigo_proceso', 'tipo_proceso', 'modalidad', 'plataforma', 'url_proceso',
        'estado', 'entidad_contratante', 'tipo_proponente', 'entidad_proponente', 'monto', 'usuario_creo_id',
    ];
    protected $attributes = ['estado' => self::PREPARACION];
    protected $casts = [
```

por:

```php
    public const DESIERTO = 'desierto';
    public const CELEBRADO = 'celebrado';
    public const CANCELADO = 'cancelado';
    public const REVOCADO = 'revocado';
    public const ESTADOS = [
        self::PREPARACION, self::PUBLICADO, self::EVALUACION, self::ADJUDICADO,
        self::DESIERTO, self::CELEBRADO, self::CANCELADO, self::REVOCADO,
    ];

    protected $table = 'procesos_licitacion';
    protected $fillable = [
        'proyecto', 'objeto', 'codigo_proceso', 'tipo_proceso', 'modalidad', 'plataforma', 'url_proceso',
        'estado', 'entidad_contratante', 'tipo_proponente', 'entidad_proponente', 'monto', 'usuario_creo_id',
        'fuente_id', 'fecha_apertura', 'fecha_cierre', 'responsable_id', 'observaciones',
    ];
    protected $attributes = ['estado' => self::PREPARACION];
    protected $casts = [
```

En `app/Models/ProcesoLicitacion.php`, reemplazar (2 de 3):

```php
        'entidad_contratante' => 'integer',
        'monto' => 'decimal:2',
        'usuario_creo_id' => 'integer',
    ];

    // proyectoRel()/modalidadRel(): both FK columns (proyecto, modalidad) are single words that
```

por:

```php
        'entidad_contratante' => 'integer',
        'monto' => 'decimal:2',
        'usuario_creo_id' => 'integer',
        'fuente_id' => 'integer',
        'responsable_id' => 'integer',
        'fecha_apertura' => 'date',
        'fecha_cierre' => 'date',
    ];

    // proyectoRel()/modalidadRel(): both FK columns (proyecto, modalidad) are single words that
```

En `app/Models/ProcesoLicitacion.php`, reemplazar (3 de 3):

```php
        return $this->belongsTo(User::class, 'usuario_creo_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoLicitacion::class, 'proceso');
```

por:

```php
        return $this->belongsTo(User::class, 'usuario_creo_id');
    }

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteFinanciacion::class, 'fuente_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Responsable::class, 'responsable_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoLicitacion::class, 'proceso');
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Schema/ProcesosContratosAmpliadosTest.php tests/Feature/Schema/ContratacionCatalogosTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 875 passed.

- [ ] **Paso 5: Commit**

```bash
git add database/migrations/2026_11_18_100017_amplia_procesos_licitacion_contratos.php database/migrations/2026_11_18_100018_amplia_contratos.php tests/Feature/Schema/ProcesosContratosAmpliadosTest.php app/Models/Contrato.php app/Models/ProcesoLicitacion.php tests/Feature/Schema/ContratacionCatalogosTest.php
git commit -m "feat: process and contract columns, contract states (migrations 18-19)"
```

---

### Tarea 3: Tipos de contrato: API

Las rutas existentes de `tipos_procesos`, que hoy dan 500, pasan a un controlador nuevo en el patrón de las modalidades.

**Files:**
- Create: `app/Http/Controllers/TipoContratoController.php`
- Test (create): `tests/Feature/Contratacion/TiposContratoApiTest.php`
- Modify: `routes/api.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/TiposContratoApiTest.php`:

```php
<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TiposContratoApiTest extends TestCase
{
    use RefreshDatabase;

    private const LISTAR = '/GestPro/tiposProcesos';
    private const GUARDAR = '/GestPro/guardarTipoProceso';
    private const ACTIVAR = '/GestPro/activarTipoProceso';

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function tipo(string $nombre): TipoProceso
    {
        return TipoProceso::where('nombre', $nombre)->firstOrFail();
    }

    public function test_cualquier_usuario_autenticado_lista_los_tipos_ordenados_por_nombre_con_inactivos(): void
    {
        $this->tipo('Obra')->update(['activo' => false]);

        $respuesta = $this->como(Rol::Consulta)->getJson(self::LISTAR)
            ->assertOk()
            ->assertJsonCount(6)
            ->assertJsonStructure([['id', 'nombre', 'activo']]);

        $nombres = array_column($respuesta->json(), 'nombre');
        $this->assertSame(
            ['Consultoría', 'Interventoría', 'Obra', 'Orden de compra', 'Prestación de servicios', 'Suministro'],
            $nombres
        );
        $this->assertFalse(collect($respuesta->json())->firstWhere('nombre', 'Obra')['activo']);
    }

    public function test_el_administrador_agrega_un_tipo_activo(): void
    {
        $this->como(Rol::Administrador)->postJson(self::GUARDAR, [
            'accion' => 'Agregar',
            'nombre' => '  Concesión ',
            'activo' => false,
        ])->assertOk()->assertJsonPath('success', 'Tipo de contrato guardado correctamente');

        $tipo = TipoProceso::where('nombre', 'Concesión')->firstOrFail();
        $this->assertTrue($tipo->activo);
        $this->assertDatabaseCount('tipos_procesos', 7);
    }

    public function test_editar_cambia_el_nombre_y_conserva_el_estado(): void
    {
        $tipo = $this->tipo('Suministro');
        $tipo->update(['activo' => false]);

        $this->como(Rol::Administrador)->postJson(self::GUARDAR, [
            'accion' => 'Editar', 'id' => $tipo->id, 'nombre' => 'Suministro de bienes', 'activo' => true,
        ])->assertOk();

        $tipo->refresh();
        $this->assertSame('Suministro de bienes', $tipo->nombre);
        $this->assertFalse($tipo->activo);
    }

    public function test_rechaza_nombres_vacios_o_largos_con_mensajes_en_espanol(): void
    {
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => ''])
            ->assertStatus(422)->assertJsonPath('errors.nombre.0', 'Escribe el nombre del tipo de contrato.');
        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => str_repeat('a', 121)])
            ->assertStatus(422)->assertJsonPath('errors.nombre.0', 'El nombre admite hasta 120 caracteres.');
        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => str_repeat('a', 120)])->assertOk();
    }

    public function test_el_nombre_no_se_repite_sin_distinguir_mayusculas_ni_tildes(): void
    {
        $admin = $this->como(Rol::Administrador);

        foreach (['Interventoría', 'INTERVENTORÍA', 'interventoria', ' interventoria '] as $nombre) {
            $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => $nombre])
                ->assertStatus(422)
                ->assertJsonPath('errors.nombre.0', 'Ya existe un tipo de contrato con ese nombre.');
        }

        $this->assertDatabaseCount('tipos_procesos', 6);
    }

    public function test_editar_sin_cambiar_el_nombre_o_solo_sus_mayusculas_no_choca_consigo_mismo(): void
    {
        $tipo = $this->tipo('Consultoría');
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $tipo->id, 'nombre' => 'Consultoría'])->assertOk();
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $tipo->id, 'nombre' => 'CONSULTORIA'])->assertOk();

        $this->assertSame('CONSULTORIA', $tipo->fresh()->nombre);
    }

    public function test_no_se_puede_renombrar_con_el_nombre_de_otro_tipo(): void
    {
        $tipo = $this->tipo('Consultoría');

        $this->como(Rol::Administrador)->postJson(self::GUARDAR, [
            'accion' => 'Editar', 'id' => $tipo->id, 'nombre' => 'orden de compra',
        ])->assertStatus(422)->assertJsonValidationErrors('nombre');

        $this->assertSame('Consultoría', $tipo->fresh()->nombre);
    }

    public function test_activar_alterna_el_estado_segun_la_base_de_datos(): void
    {
        $tipo = $this->tipo('Obra');
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::ACTIVAR, ['id' => $tipo->id])
            ->assertOk()->assertJsonPath('success', 'Tipo de contrato actualizado correctamente');
        $this->assertFalse($tipo->fresh()->activo);

        $admin->postJson(self::ACTIVAR, ['id' => $tipo->id])->assertOk();
        $this->assertTrue($tipo->fresh()->activo);
    }

    public function test_solo_el_administrador_modifica_el_catalogo(): void
    {
        $tipo = $this->tipo('Obra');

        foreach ([Rol::Gestor, Rol::Consulta] as $rol) {
            $usuario = $this->como($rol);

            $usuario->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Nuevo'])->assertForbidden();
            $usuario->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $tipo->id, 'nombre' => 'Otro'])->assertForbidden();
            $usuario->postJson(self::ACTIVAR, ['id' => $tipo->id])->assertForbidden();
        }

        $this->assertDatabaseCount('tipos_procesos', 6);
        $this->assertSame('Obra', $tipo->fresh()->nombre);
        $this->assertTrue($tipo->fresh()->activo);
    }

    public function test_rechaza_acciones_e_ids_invalidos(): void
    {
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['nombre' => 'Nuevo'])->assertStatus(422)->assertJsonValidationErrors('accion');
        $admin->postJson(self::GUARDAR, ['accion' => 'Borrar', 'nombre' => 'Nuevo'])->assertStatus(422);
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'nombre' => 'Nuevo'])->assertStatus(422)->assertJsonValidationErrors('id');
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => [1], 'nombre' => 'Nuevo'])->assertStatus(422);
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => 999999, 'nombre' => 'Nuevo'])->assertNotFound();
        $admin->postJson(self::ACTIVAR, [])->assertStatus(422);
        $admin->postJson(self::ACTIVAR, ['id' => [1]])->assertStatus(422);
        $admin->postJson(self::ACTIVAR, ['id' => 999999])->assertNotFound();

        $this->assertDatabaseCount('tipos_procesos', 6);
    }

    public function test_el_id_debe_ser_un_entero_de_verdad(): void
    {
        $primero = TipoProceso::orderBy('id')->firstOrFail();
        $nombre = $primero->nombre;
        $admin = $this->como(Rol::Administrador);
        $mensaje = 'El id del tipo de contrato debe ser un número entero.';

        foreach ([true, '1', '01'] as $raro) {
            $admin->postJson(self::ACTIVAR, ['id' => $raro])
                ->assertStatus(422)->assertJsonValidationErrors(['id' => $mensaje]);
            $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $raro, 'nombre' => 'Otro'])
                ->assertStatus(422)->assertJsonValidationErrors(['id' => $mensaje]);
        }

        $primero->refresh();
        $this->assertTrue($primero->activo);
        $this->assertSame($nombre, $primero->nombre);
    }

    public function test_el_alta_lee_los_nombres_dentro_de_su_transaccion(): void
    {
        // La unicidad se valida en código (nombre es TEXT, sin índice único): la lectura de los
        // nombres debe estar dentro de la transacción del bloqueo.
        $admin = $this->como(Rol::Administrador);
        $fuera = DB::transactionLevel();
        $niveles = [];
        DB::listen(function (QueryExecuted $consulta) use (&$niveles) {
            if (preg_match('/^select .* from "tipos_procesos"/i', ltrim($consulta->sql))) {
                $niveles[] = DB::transactionLevel();
            }
        });

        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Concesión'])->assertOk();

        $this->assertNotEmpty($niveles);
        $this->assertGreaterThan($fuera, $niveles[0]);
    }

    public function test_las_tres_rutas_apuntan_al_controlador_de_tipos_de_contrato(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())->keyBy(fn ($r) => $r->methods()[0] . ' ' . $r->uri());

        $this->assertStringContainsString('TipoContratoController@index', $rutas['GET GestPro/tiposProcesos']->getActionName());
        $this->assertStringContainsString('TipoContratoController@guardar', $rutas['POST GestPro/guardarTipoProceso']->getActionName());
        $this->assertStringContainsString('TipoContratoController@activar', $rutas['POST GestPro/activarTipoProceso']->getActionName());
        // Fuera de alcance: eliminar sigue donde estaba.
        $this->assertStringContainsString('ProyectoController@eliminarTipoProceso', $rutas['POST GestPro/eliminarTipoProceso']->getActionName());
    }

    public function test_exige_autenticacion(): void
    {
        $this->getJson(self::LISTAR)->assertUnauthorized();
        $this->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Nuevo'])->assertUnauthorized();
        $this->postJson(self::ACTIVAR, ['id' => 1])->assertUnauthorized();
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/TiposContratoApiTest.php`
Expected: FALLAN 12 y pasan 2: las rutas de `tipos_procesos` apuntan a métodos que no existen en `ProyectoController` (500).

- [ ] **Paso 3: Implementar**

Crear `app/Http/Controllers/TipoContratoController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\TipoProceso;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo de tipos de contrato (tabla `tipos_procesos`, modelo `TipoProceso`). Los tipos no se
 * eliminan por aquí: se desactivan, y uno desactivado se conserva en los contratos que ya lo tienen.
 */
class TipoContratoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(TipoProceso::query()->orderBy('nombre')->get());
    }

    public function guardar(Request $request): JsonResponse
    {
        $request->validate([
            'accion' => ['required', Rule::in(['Agregar', 'Editar'])],
            'id' => ['bail', 'required_if:accion,Editar', $this->entero()],
        ]);

        $tipo = $request->input('accion') === 'Editar'
            ? TipoProceso::findOrFail($request->input('id'))
            : new TipoProceso(['activo' => true]);

        $datos = $request->validate([
            'nombre' => ['bail', 'required', 'string', 'max:120'],
        ], [
            'nombre.required' => 'Escribe el nombre del tipo de contrato.',
            'nombre.string' => 'Escribe el nombre del tipo de contrato.',
            'nombre.max' => 'El nombre admite hasta 120 caracteres.',
        ]);
        $datos['nombre'] = trim($datos['nombre']);

        // Con todos los tipos bloqueados, dos altas o renombres a la vez no eligen el mismo nombre:
        // el segundo espera y ve lo que guardó el primero. `nombre` es TEXT y no tiene índice único
        // que lo respalde.
        DB::transaction(function () use ($tipo, $datos) {
            $todos = TipoProceso::query()->lockForUpdate()->get(['id', 'nombre']);
            $this->exigirNombreLibre($tipo, $datos['nombre'], $todos);
            $tipo->fill($datos)->save();
        });

        return response()->json(['success' => 'Tipo de contrato guardado correctamente']);
    }

    public function activar(Request $request): JsonResponse
    {
        $request->validate(['id' => ['bail', 'required', $this->entero()]]);

        $tipo = TipoProceso::findOrFail($request->input('id'));
        $tipo->update(['activo' => ! $tipo->activo]);

        return response()->json(['success' => 'Tipo de contrato actualizado correctamente']);
    }

    /** Enteros de verdad: `integer` admitiría true como 1 o "1". */
    private function entero(): Closure
    {
        return fn (string $atributo, mixed $valor, Closure $falla) => is_int($valor)
            || $falla('El id del tipo de contrato debe ser un número entero.');
    }

    /**
     * El nombre no se repite sin contar mayúsculas ni tildes. Se compara en PHP para que MariaDB y
     * SQLite lo hagan igual; el catálogo es pequeño.
     *
     * @param Collection<int, TipoProceso> $todos
     */
    private function exigirNombreLibre(TipoProceso $tipo, string $nombre, Collection $todos): void
    {
        $normalizar = fn (?string $texto) => Str::lower(Str::ascii(trim((string) $texto)));
        $repetido = $todos->contains(fn (TipoProceso $otro) => $otro->id !== $tipo->id
            && $normalizar($otro->nombre) === $normalizar($nombre));

        if ($repetido) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un tipo de contrato con ese nombre.']);
        }
    }
}
```

En `routes/api.php`, reemplazar (1 de 3):

```php
use App\Http\Controllers\ModalidadController;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\AuthController;

// 🔓 Rutas públicas
```

por:

```php
use App\Http\Controllers\ModalidadController;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\TipoContratoController;
use App\Http\Controllers\AuthController;

// 🔓 Rutas públicas
```

En `routes/api.php`, reemplazar (2 de 3):

```php
    Route::get('/listarContratos', [ProyectoController::class, 'listarContratos']);
    Route::get('/sectores', [ProyectoController::class, 'sectores']);
    Route::get('/fuentesFinanciacion', [FuenteFinanciacionController::class, 'index']);
    Route::get('/tiposProcesos', [ProyectoController::class, 'tiposProcesos']);
    Route::get('/modalidades', [ModalidadController::class, 'index']);
    Route::get('/etapasContratacion', [EtapaContratacionController::class, 'index']);
```

por:

```php
    Route::get('/listarContratos', [ProyectoController::class, 'listarContratos']);
    Route::get('/sectores', [ProyectoController::class, 'sectores']);
    Route::get('/fuentesFinanciacion', [FuenteFinanciacionController::class, 'index']);
    Route::get('/tiposProcesos', [TipoContratoController::class, 'index']);
    Route::get('/modalidades', [ModalidadController::class, 'index']);
    Route::get('/etapasContratacion', [EtapaContratacionController::class, 'index']);
```

En `routes/api.php`, reemplazar (3 de 3):

```php
        Route::post('/activarFuenteFinanciacion', [FuenteFinanciacionController::class, 'activar']);
        Route::post('/guardarSector', [SectorController::class, 'guardar']);
        Route::post('/activarSector', [SectorController::class, 'activar']);
        Route::post('/guardarTipoProceso', [ProyectoController::class, 'guardarTipoProceso']);
        Route::post('/eliminarTipoProceso', [ProyectoController::class, 'eliminarTipoProceso']);
        Route::post('/activarTipoProceso', [ProyectoController::class, 'activarTipoProceso']);
        Route::post('/guardarModalidad', [ModalidadController::class, 'guardar']);
        Route::post('/activarModalidad', [ModalidadController::class, 'activar']);
        Route::post('/guardarEtapaContratacion', [EtapaContratacionController::class, 'guardar']);
```

por:

```php
        Route::post('/activarFuenteFinanciacion', [FuenteFinanciacionController::class, 'activar']);
        Route::post('/guardarSector', [SectorController::class, 'guardar']);
        Route::post('/activarSector', [SectorController::class, 'activar']);
        Route::post('/guardarTipoProceso', [TipoContratoController::class, 'guardar']);
        Route::post('/eliminarTipoProceso', [ProyectoController::class, 'eliminarTipoProceso']);
        Route::post('/activarTipoProceso', [TipoContratoController::class, 'activar']);
        Route::post('/guardarModalidad', [ModalidadController::class, 'guardar']);
        Route::post('/activarModalidad', [ModalidadController::class, 'activar']);
        Route::post('/guardarEtapaContratacion', [EtapaContratacionController::class, 'guardar']);
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/TiposContratoApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 889 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Http/Controllers/TipoContratoController.php tests/Feature/Contratacion/TiposContratoApiTest.php routes/api.php
git commit -m "feat: contract types catalog API"
```

---

### Tarea 4: Procesos: fuente, fechas, responsable, revocado y precarga

Campos nuevos del proceso en la API, el estado `revocado` y la precarga del contrato desde un proceso.

**Files:**
- Modify: `app/Http/Controllers/ProcesoContratacionController.php`
- Modify: `app/Models/ProcesoLicitacion.php`
- Modify: `app/Services/Contratacion/ProcesosContratacion.php`
- Modify: `resources/js/utils/contratacion.js`
- Test: `resources/js/utils/contratacion.test.js`
- Modify: `routes/contratacion.php`
- Test: `tests/Feature/Contratacion/ProcesosContratacionApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/contratacion.test.js`, reemplazar (1 de 1):

```js
    });

    it('cada estado tiene un tono para su insignia', () => {
        expect(ESTADOS_PROCESO.map(([v]) => tonoEstado(v))).toEqual(['neutro', 'azul', 'ambar', 'verde', 'coral', 'verde-pleno', 'coral']);
        expect(tonoEstado('otro')).toBe('neutro');
    });
});
```

por:

```js
    });

    it('cada estado tiene un tono para su insignia', () => {
        expect(ESTADOS_PROCESO.map(([v]) => tonoEstado(v))).toEqual(['neutro', 'azul', 'ambar', 'verde', 'coral', 'verde-pleno', 'coral', 'coral']);
        expect(tonoEstado('otro')).toBe('neutro');
    });
});
```

En `tests/Feature/Contratacion/ProcesosContratacionApiTest.php`, reemplazar (1 de 6):

```php
use App\Models\Modalidad;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
```

por:

```php
use App\Models\Modalidad;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\Responsable;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
```

En `tests/Feature/Contratacion/ProcesosContratacionApiTest.php`, reemplazar (2 de 6):

```php
            'url_proceso' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.1',
            'entidad_contratante' => ['id' => $this->entidad->id, 'nombre' => 'Gobernación del Cesar'],
            'monto' => '1500000.50',
            'estado' => 'preparacion',
            'contratos' => 0,
            'creado' => $r->json('creado'),
```

por:

```php
            'url_proceso' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.1',
            'entidad_contratante' => ['id' => $this->entidad->id, 'nombre' => 'Gobernación del Cesar'],
            'monto' => '1500000.50',
            'fuente' => null,
            'responsable' => null,
            'fecha_apertura' => null,
            'fecha_cierre' => null,
            'observaciones' => null,
            'estado' => 'preparacion',
            'contratos' => 0,
            'creado' => $r->json('creado'),
```

En `tests/Feature/Contratacion/ProcesosContratacionApiTest.php`, reemplazar (3 de 6):

```php
            'url_proceso' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.1',
            'entidad_contratante_id' => $this->entidad->id,
            'monto' => '1500000.50',
            'estado' => 'preparacion',
        ]]], $this->eventos($proceso));
        $this->assertNull(DB::table('checklist_historial')->where('entidad_tipo', 'proceso')->value('expediente_id'));
```

por:

```php
            'url_proceso' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.1',
            'entidad_contratante_id' => $this->entidad->id,
            'monto' => '1500000.50',
            'fuente_id' => null,
            'responsable_id' => null,
            'fecha_apertura' => null,
            'fecha_cierre' => null,
            'observaciones' => null,
            'estado' => 'preparacion',
        ]]], $this->eventos($proceso));
        $this->assertNull(DB::table('checklist_historial')->where('entidad_tipo', 'proceso')->value('expediente_id'));
```

En `tests/Feature/Contratacion/ProcesosContratacionApiTest.php`, reemplazar (4 de 6):

```php
            'enlace largo' => [['url_proceso' => 'https://secop.gov.co/' . str_repeat('a', 480)], 'url_proceso', 'El enlace admite hasta 500 caracteres.'],
            'entidad texto' => [['entidad_contratante_id' => '1'], 'entidad_contratante_id', 'Revisa la entidad contratante: debe ser un número entero.'],
            'monto no numérico' => [['monto' => 'mil'], 'monto', 'Escribe el valor como un número.'],
            'monto negativo' => [['monto' => -1], 'monto', 'El valor no puede ser negativo.'],
            'monto con tres decimales' => [['monto' => '10.555'], 'monto', 'El valor admite hasta 2 decimales.'],
            'monto enorme' => [['monto' => '10000000000000'], 'monto', 'El valor admite hasta 13 cifras enteras y 2 decimales.'],
```

por:

```php
            'enlace largo' => [['url_proceso' => 'https://secop.gov.co/' . str_repeat('a', 480)], 'url_proceso', 'El enlace admite hasta 500 caracteres.'],
            'entidad texto' => [['entidad_contratante_id' => '1'], 'entidad_contratante_id', 'Revisa la entidad contratante: debe ser un número entero.'],
            'monto no numérico' => [['monto' => 'mil'], 'monto', 'Escribe el valor como un número.'],
            'fuente texto' => [['fuente_id' => '1'], 'fuente_id', 'Revisa la fuente de financiación: debe ser un número entero.'],
            'responsable texto' => [['responsable_id' => '1'], 'responsable_id', 'Revisa el responsable: debe ser un número entero.'],
            'apertura sin formato' => [['fecha_apertura' => '15/03/2026'], 'fecha_apertura', 'Escribe la fecha de apertura como año-mes-día.'],
            'apertura imposible' => [['fecha_apertura' => '2026-02-30'], 'fecha_apertura', 'Escribe la fecha de apertura como año-mes-día.'],
            'cierre sin formato' => [['fecha_cierre' => 'mañana'], 'fecha_cierre', 'Escribe la fecha de cierre como año-mes-día.'],
            'observaciones no texto' => [['observaciones' => ['a']], 'observaciones', 'Revisa las observaciones: debe ser un texto.'],
            'observaciones largas' => [['observaciones' => str_repeat('a', 5001)], 'observaciones', 'Las observaciones admiten hasta 5000 caracteres.'],
            'cierre antes de la apertura' => [['fecha_apertura' => '2026-03-10', 'fecha_cierre' => '2026-03-09'], 'fecha_cierre', 'La fecha de cierre no puede ser anterior a la de apertura.'],
            'monto negativo' => [['monto' => -1], 'monto', 'El valor no puede ser negativo.'],
            'monto con tres decimales' => [['monto' => '10.555'], 'monto', 'El valor admite hasta 2 decimales.'],
            'monto enorme' => [['monto' => '10000000000000'], 'monto', 'El valor admite hasta 13 cifras enteras y 2 decimales.'],
```

En `tests/Feature/Contratacion/ProcesosContratacionApiTest.php`, reemplazar (5 de 6):

```php
            'entidad_contratante_id' => $this->entidad->id,
            'entidad_contratante' => 'Gobernación del Cesar',
            'monto' => '800.00',
            'estado' => 'cancelado',
        ], null], $this->eventos($proceso)[2]);
    }
```

por:

```php
            'entidad_contratante_id' => $this->entidad->id,
            'entidad_contratante' => 'Gobernación del Cesar',
            'monto' => '800.00',
            'fuente_id' => null,
            'fuente' => null,
            'responsable_id' => null,
            'responsable' => null,
            'fecha_apertura' => null,
            'fecha_cierre' => null,
            'observaciones' => null,
            'estado' => 'cancelado',
        ], null], $this->eventos($proceso)[2]);
    }
```

En `tests/Feature/Contratacion/ProcesosContratacionApiTest.php`, reemplazar (6 de 6):

```php

        $this->assertSame(0, DB::table('checklist_historial')->count());
    }
}
```

por:

```php

        $this->assertSame(0, DB::table('checklist_historial')->count());
    }

    // ----------------------------------------- fuente, responsable, fechas, observaciones

    private function fuente(string $nombre = 'Fuente de prueba', bool $activo = true): FuenteFinanciacion
    {
        return FuenteFinanciacion::create(['clave' => 'f_' . uniqid(), 'nombre' => $nombre, 'orden' => 99, 'activo' => $activo]);
    }

    private function responsable(string $nombre = 'Marta Ríos', bool $activo = true): Responsable
    {
        return Responsable::create(['nombre' => $nombre, 'activo' => $activo]);
    }

    public function test_crea_con_fuente_responsable_fechas_y_observaciones(): void
    {
        $fuente = $this->fuente();
        $responsable = $this->responsable();

        $r = $this->crear([
            'fuente_id' => $fuente->id,
            'responsable_id' => $responsable->id,
            'fecha_apertura' => '2026-03-10',
            'fecha_cierre' => '2026-03-24',
            'observaciones' => '  Pliegos publicados el 9 de marzo.  ',
        ])->assertCreated()
            ->assertJsonPath('fuente', ['id' => $fuente->id, 'nombre' => 'Fuente de prueba'])
            ->assertJsonPath('responsable', ['id' => $responsable->id, 'nombre' => 'Marta Ríos'])
            ->assertJsonPath('fecha_apertura', '2026-03-10')
            ->assertJsonPath('fecha_cierre', '2026-03-24')
            ->assertJsonPath('observaciones', 'Pliegos publicados el 9 de marzo.');

        $proceso = ProcesoLicitacion::findOrFail($r->json('id'));
        $this->assertSame($fuente->id, $proceso->fuente_id);
        $this->assertSame($responsable->id, $proceso->responsable_id);
        $despues = $this->eventos($proceso)[0][2];
        $this->assertSame($fuente->id, $despues['fuente_id']);
        $this->assertSame($responsable->id, $despues['responsable_id']);
        $this->assertSame('2026-03-10', $despues['fecha_apertura']);
        $this->assertSame('2026-03-24', $despues['fecha_cierre']);
        $this->assertSame('Pliegos publicados el 9 de marzo.', $despues['observaciones']);
    }

    public function test_el_listado_trae_fuente_y_responsable_sin_consultas_por_proceso(): void
    {
        $this->proceso(['fuente_id' => $this->fuente()->id, 'responsable_id' => $this->responsable()->id]);
        $consultas = function (): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson($this->urlProyecto())->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $conUno = $consultas();
        $this->proceso(['fuente_id' => $this->fuente('Otra fuente')->id, 'responsable_id' => $this->responsable('Luis Paz')->id]);

        $this->assertSame($conUno, $consultas());
        $this->como(Rol::Consulta)->getJson($this->urlProyecto())
            ->assertJsonPath('procesos.0.fuente.nombre', 'Fuente de prueba')
            ->assertJsonPath('procesos.1.responsable.nombre', 'Luis Paz');
    }

    public function test_edita_los_campos_nuevos_y_registra_solo_lo_que_cambio(): void
    {
        $fuente = $this->fuente();
        $otraFuente = $this->fuente('Otra fuente');
        $responsable = $this->responsable();
        $proceso = $this->proceso(['fuente_id' => $fuente->id, 'fecha_apertura' => '2026-03-10', 'observaciones' => 'Uno']);

        $this->editar($proceso, $this->datos([
            'fuente_id' => $otraFuente->id,
            'responsable_id' => $responsable->id,
            'fecha_apertura' => '2026-03-10',
            'fecha_cierre' => '2026-03-20',
            'observaciones' => 'Uno',
        ]))->assertOk()
            ->assertJsonPath('fuente.id', $otraFuente->id)
            ->assertJsonPath('responsable.id', $responsable->id)
            ->assertJsonPath('fecha_cierre', '2026-03-20');

        $this->assertSame(['proceso_editado', [
            'fuente_id' => $fuente->id,
            'responsable_id' => null,
            'fecha_cierre' => null,
        ], [
            'fuente_id' => $otraFuente->id,
            'responsable_id' => $responsable->id,
            'fecha_cierre' => '2026-03-20',
        ]], $this->eventos($proceso)[1]);
    }

    public function test_los_campos_nuevos_que_no_vienen_no_cambian_y_con_null_se_borran(): void
    {
        $proceso = $this->proceso([
            'fuente_id' => $this->fuente()->id, 'responsable_id' => $this->responsable()->id,
            'fecha_apertura' => '2026-03-10', 'fecha_cierre' => '2026-03-20', 'observaciones' => 'Nota',
        ]);

        $this->editar($proceso, $this->datos(['fuente_id' => null, 'fecha_cierre' => null, 'observaciones' => '   ']))
            ->assertOk()
            ->assertJsonPath('fuente', null)
            ->assertJsonPath('fecha_cierre', null)
            ->assertJsonPath('observaciones', null)
            ->assertJsonPath('fecha_apertura', '2026-03-10')
            ->assertJsonPath('responsable.nombre', 'Marta Ríos');

        $this->assertSame(['fuente_id', 'fecha_cierre', 'observaciones'], array_keys($this->eventos($proceso)[1][2]));
    }

    public function test_editar_los_campos_nuevos_sin_cambios_no_registra_nada(): void
    {
        $proceso = $this->proceso(['fecha_apertura' => '2026-03-10', 'fuente_id' => $this->fuente()->id]);

        $this->editar($proceso, $this->datos(['fecha_apertura' => '2026-03-10', 'fuente_id' => $proceso->fuente_id]))->assertOk();

        $this->assertCount(1, $this->eventos($proceso));
    }

    public function test_al_crear_la_fuente_y_el_responsable_deben_estar_activos(): void
    {
        $this->crear([
            'fuente_id' => $this->fuente('Inactiva', false)->id,
            'responsable_id' => $this->responsable('Inactivo', false)->id,
        ])->assertStatus(422)->assertJsonValidationErrors([
            'fuente_id' => 'La fuente elegida está inactiva: elige otra.',
            'responsable_id' => 'El responsable elegido está inactivo: elige otro.',
        ]);

        $this->assertSame(0, ProcesoLicitacion::count());
    }

    public function test_fuente_o_responsable_inexistente(): void
    {
        $this->crear(['fuente_id' => 9999, 'responsable_id' => 9999])->assertStatus(422)
            ->assertJsonValidationErrors([
                'fuente_id' => 'Elige una fuente de la lista.',
                'responsable_id' => 'Elige un responsable de la lista.',
            ]);
    }

    public function test_al_editar_se_conservan_la_fuente_y_el_responsable_inactivos_que_ya_tiene(): void
    {
        $fuente = $this->fuente();
        $responsable = $this->responsable();
        $proceso = $this->proceso(['fuente_id' => $fuente->id, 'responsable_id' => $responsable->id]);
        $fuente->update(['activo' => false]);
        $responsable->update(['activo' => false]);

        $this->editar($proceso, $this->datos([
            'objeto' => 'Otro objeto', 'fuente_id' => $fuente->id, 'responsable_id' => $responsable->id,
        ]))->assertOk()->assertJsonPath('fuente.id', $fuente->id)->assertJsonPath('responsable.id', $responsable->id);
    }

    public function test_al_editar_no_se_cambia_a_una_fuente_o_responsable_inactivo(): void
    {
        $proceso = $this->proceso();

        $this->editar($proceso, $this->datos([
            'fuente_id' => $this->fuente('Inactiva', false)->id,
            'responsable_id' => $this->responsable('Inactivo', false)->id,
        ]))->assertStatus(422)->assertJsonValidationErrors([
            'fuente_id' => 'La fuente elegida está inactiva: elige otra.',
            'responsable_id' => 'El responsable elegido está inactivo: elige otro.',
        ]);

        $this->assertNull($proceso->refresh()->fuente_id);
        $this->assertCount(1, $this->eventos($proceso));
    }

    public function test_el_cierre_puede_ser_el_mismo_dia_de_la_apertura_o_faltar_la_otra_fecha(): void
    {
        $this->crear(['fecha_apertura' => '2026-03-10', 'fecha_cierre' => '2026-03-10'])->assertCreated();
        $this->crear(['fecha_cierre' => '2026-03-01'])->assertCreated();
        $this->crear(['fecha_apertura' => '2026-03-10'])->assertCreated();
    }

    public function test_al_editar_el_cierre_se_compara_con_la_apertura_guardada(): void
    {
        $proceso = $this->proceso(['fecha_apertura' => '2026-03-10']);

        $this->editar($proceso, $this->datos(['fecha_cierre' => '2026-03-01']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_cierre' => 'La fecha de cierre no puede ser anterior a la de apertura.']);
        $this->editar($proceso, $this->datos(['fecha_cierre' => '2026-03-10']))->assertOk();

        // Adelantar la apertura más allá del cierre guardado también se rechaza.
        $this->editar($proceso, $this->datos(['fecha_apertura' => '2026-04-01']))
            ->assertStatus(422)->assertJsonValidationErrors(['fecha_cierre']);
        $this->assertSame('2026-03-10', $proceso->refresh()->fecha_apertura->toDateString());
    }

    public function test_el_estado_revocado_se_admite(): void
    {
        $proceso = $this->proceso();

        $this->cambiarEstado($proceso, ['estado' => 'revocado', 'observacion' => 'Se revocó el acto'])
            ->assertOk()->assertJsonPath('estado', 'revocado');

        $this->assertSame('revocado', $proceso->refresh()->estado);
    }

    // ------------------------------------------------------ precarga de un contrato

    private function urlPrecarga(ProcesoLicitacion|int $proceso): string
    {
        return $this->urlProceso($proceso, '/precarga-contrato');
    }

    private function pasarA(ProcesoLicitacion $proceso, string $estado): void
    {
        $this->cambiarEstado($proceso, ['estado' => $estado])->assertOk();
    }

    public function test_la_precarga_resume_el_proceso_y_sugiere_el_valor(): void
    {
        $proceso = $this->proceso([
            'codigo_proceso' => 'LP-001-2026',
            'url_proceso' => 'https://secop.gov.co/p/1',
            'entidad_contratante_id' => $this->entidad->id,
            'monto' => 1000000,
        ]);
        $this->pasarA($proceso, 'adjudicado');

        $this->como(Rol::Consulta)->getJson($this->urlPrecarga($proceso))->assertOk()->assertExactJson([
            'proceso' => [
                'id' => $proceso->id,
                'codigo_proceso' => 'LP-001-2026',
                'objeto' => 'Construcción del acueducto veredal',
                'estado' => 'adjudicado',
                'modalidad' => ['id' => $this->modalidad('licitacion_publica')->id, 'nombre' => 'Licitación pública'],
                'plataforma' => 'secop_ii',
                'url_proceso' => 'https://secop.gov.co/p/1',
                'monto' => '1000000.00',
            ],
            'admite_contratos' => true,
            'precarga' => [
                'objeto' => 'Construcción del acueducto veredal',
                'contratante' => 'Gobernación del Cesar',
                'valor_inicial' => '1000000.00',
            ],
        ]);
    }

    public function test_el_valor_sugerido_descuenta_los_contratos_no_anulados(): void
    {
        $proceso = $this->proceso(['monto' => 1000]);
        $this->pasarA($proceso, 'adjudicado');
        $nuevo = fn (array $mas) => Contrato::create(['proyecto' => $this->proyecto->id, 'proceso_licitacion' => $proceso->id, ...$mas]);
        $nuevo(['valor_inicial' => 300.25, 'estado' => 'en_ejecucion']);
        $nuevo(['valor_inicial' => 200, 'estado' => 'anulado']);
        $nuevo(['valor_inicial' => null]);
        $nuevo(['valor_inicial' => 100.10, 'estado' => 'liquidado']);
        // De otro proceso: no cuenta.
        Contrato::create(['proyecto' => $this->proyecto->id, 'valor_inicial' => 999]);

        $this->como()->getJson($this->urlPrecarga($proceso))->assertOk()
            ->assertJsonPath('precarga.valor_inicial', '599.65');
    }

    public function test_el_valor_sugerido_nunca_es_negativo_ni_existe_sin_monto(): void
    {
        $proceso = $this->proceso(['monto' => 100]);
        Contrato::create(['proyecto' => $this->proyecto->id, 'proceso_licitacion' => $proceso->id, 'valor_inicial' => 150, 'estado' => 'celebrado']);
        $this->como()->getJson($this->urlPrecarga($proceso))->assertOk()->assertJsonPath('precarga.valor_inicial', '0.00');

        $sinMonto = $this->proceso();
        $this->como()->getJson($this->urlPrecarga($sinMonto))->assertOk()
            ->assertJsonPath('proceso.monto', null)
            ->assertJsonPath('precarga.valor_inicial', null)
            ->assertJsonPath('precarga.contratante', null);
    }

    public static function admiten(): array
    {
        return [
            'preparación' => ['preparacion', false],
            'publicado' => ['publicado', false],
            'evaluación' => ['evaluacion', false],
            'adjudicado' => ['adjudicado', true],
            'celebrado' => ['celebrado', true],
            'desierto' => ['desierto', false],
            'cancelado' => ['cancelado', false],
            'revocado' => ['revocado', false],
        ];
    }

    #[DataProvider('admiten')]
    public function test_solo_adjudicado_o_celebrado_admiten_contratos(string $estado, bool $admite): void
    {
        $proceso = $this->proceso();
        if ($estado !== 'preparacion') {
            $this->pasarA($proceso, $estado);
        }

        $this->como(Rol::Consulta)->getJson($this->urlPrecarga($proceso))->assertOk()
            ->assertJsonPath('admite_contratos', $admite)
            ->assertJsonPath('proceso.estado', $estado);
    }

    public function test_la_precarga_sin_sesion_no_tiene_acceso(): void
    {
        $proceso = ProcesoLicitacion::create(['proyecto' => $this->proyecto->id, 'objeto' => 'Obra']);

        $this->getJson($this->urlPrecarga($proceso))->assertUnauthorized();
    }

    public function test_la_precarga_es_de_lectura(): void
    {
        $proceso = $this->proceso();

        $this->como(Rol::Consulta)->getJson($this->urlPrecarga($proceso))->assertOk();
        $this->como()->getJson($this->urlPrecarga(999999))->assertNotFound();
        $this->assertSame(
            '/GestPro/procesos/' . $proceso->id . '/precarga-contrato',
            parse_url(route('procesos.precarga-contrato', $proceso), PHP_URL_PATH)
        );
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/ProcesosContratacionApiTest.php`
Expected: FALLAN 40 y pasan 69: el proceso todavía no acepta ni presenta fuente, fechas, responsable y observaciones, y no existe la precarga del contrato.

Run: `npx vitest run resources/js/utils/contratacion.test.js`
Expected: FALLAN 2 y pasan 59: `ESTADOS_PROCESO` y los tonos todavía no tienen `revocado`.

- [ ] **Paso 3: Implementar**

En `app/Http/Controllers/ProcesoContratacionController.php`, reemplazar (1 de 4):

```php
        'monto.numeric' => 'Escribe el valor como un número.',
        'monto.min' => 'El valor no puede ser negativo.',
        'monto.decimal' => 'El valor admite hasta 2 decimales.',
        'estado.required' => 'Elige el estado del proceso.',
        'estado.in' => 'Elige un estado de la lista.',
        'observacion.max' => 'La observación admite hasta 2000 caracteres.',
```

por:

```php
        'monto.numeric' => 'Escribe el valor como un número.',
        'monto.min' => 'El valor no puede ser negativo.',
        'monto.decimal' => 'El valor admite hasta 2 decimales.',
        'fecha_apertura.date_format' => 'Escribe :attribute como año-mes-día.',
        'fecha_cierre.date_format' => 'Escribe :attribute como año-mes-día.',
        'observaciones.max' => 'Las observaciones admiten hasta 5000 caracteres.',
        'estado.required' => 'Elige el estado del proceso.',
        'estado.in' => 'Elige un estado de la lista.',
        'observacion.max' => 'La observación admite hasta 2000 caracteres.',
```

En `app/Http/Controllers/ProcesoContratacionController.php`, reemplazar (2 de 4):

```php
        'url_proceso' => 'el enlace',
        'entidad_contratante_id' => 'la entidad contratante',
        'monto' => 'el valor',
        'estado' => 'el estado',
        'observacion' => 'la observación',
        'actualizado' => 'la versión del proceso',
```

por:

```php
        'url_proceso' => 'el enlace',
        'entidad_contratante_id' => 'la entidad contratante',
        'monto' => 'el valor',
        'fuente_id' => 'la fuente de financiación',
        'responsable_id' => 'el responsable',
        'fecha_apertura' => 'la fecha de apertura',
        'fecha_cierre' => 'la fecha de cierre',
        'observaciones' => 'las observaciones',
        'estado' => 'el estado',
        'observacion' => 'la observación',
        'actualizado' => 'la versión del proceso',
```

En `app/Http/Controllers/ProcesoContratacionController.php`, reemplazar (3 de 4):

```php
        ]);
    }

    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $proceso = $this->procesos->crear($proyecto, $this->validar($request), $request->user());
```

por:

```php
        ]);
    }

    /** Lo que precarga el formulario de un contrato nuevo del proceso. */
    public function precargaContrato(ProcesoLicitacion $proceso): JsonResponse
    {
        return response()->json($this->procesos->precargaContrato($proceso));
    }

    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $proceso = $this->procesos->crear($proyecto, $this->validar($request), $request->user());
```

En `app/Http/Controllers/ProcesoContratacionController.php`, reemplazar (4 de 4):

```php
            'url_proceso' => ['sometimes', 'nullable', 'bail', 'string', 'url:http,https', 'max:500'],
            'entidad_contratante_id' => ['sometimes', 'nullable', 'bail', 'integer', $entero],
            'monto' => ['sometimes', 'nullable', 'bail', 'numeric', 'min:0', 'decimal:0,2', $cifras],
            ...$mas,
        ], self::MENSAJES, self::ATRIBUTOS);
    }
```

por:

```php
            'url_proceso' => ['sometimes', 'nullable', 'bail', 'string', 'url:http,https', 'max:500'],
            'entidad_contratante_id' => ['sometimes', 'nullable', 'bail', 'integer', $entero],
            'monto' => ['sometimes', 'nullable', 'bail', 'numeric', 'min:0', 'decimal:0,2', $cifras],
            'fuente_id' => ['sometimes', 'nullable', 'bail', 'integer', $entero],
            'responsable_id' => ['sometimes', 'nullable', 'bail', 'integer', $entero],
            'fecha_apertura' => ['sometimes', 'nullable', 'bail', 'string', 'date_format:Y-m-d'],
            'fecha_cierre' => ['sometimes', 'nullable', 'bail', 'string', 'date_format:Y-m-d'],
            'observaciones' => ['sometimes', 'nullable', 'bail', 'string', 'max:5000'],
            ...$mas,
        ], self::MENSAJES, self::ATRIBUTOS);
    }
```

En `app/Models/ProcesoLicitacion.php`, reemplazar (1 de 1):

```php
        self::DESIERTO, self::CELEBRADO, self::CANCELADO, self::REVOCADO,
    ];

    protected $table = 'procesos_licitacion';
    protected $fillable = [
        'proyecto', 'objeto', 'codigo_proceso', 'tipo_proceso', 'modalidad', 'plataforma', 'url_proceso',
```

por:

```php
        self::DESIERTO, self::CELEBRADO, self::CANCELADO, self::REVOCADO,
    ];

    /** Estados en los que se puede registrar un contrato del proceso. */
    public const ADMITEN_CONTRATOS = [self::ADJUDICADO, self::CELEBRADO];

    protected $table = 'procesos_licitacion';
    protected $fillable = [
        'proyecto', 'objeto', 'codigo_proceso', 'tipo_proceso', 'modalidad', 'plataforma', 'url_proceso',
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (1 de 12):

```php

use App\Exceptions\EdicionDesactualizadaException;
use App\Exceptions\ReglaNegocioException;
use App\Models\Entidad;
use App\Models\Modalidad;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Viabilizacion\HistorialChecklist;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
```

por:

```php

use App\Exceptions\EdicionDesactualizadaException;
use App\Exceptions\ReglaNegocioException;
use App\Models\Contrato;
use App\Models\Entidad;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\Responsable;
use App\Models\User;
use App\Services\Viabilizacion\HistorialChecklist;
use Brick\Math\BigDecimal;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (2 de 12):

```php
 * Reglas:
 * - El objeto, la modalidad y la plataforma son obligatorios; el número, el enlace, la entidad
 *   contratante y el valor, opcionales.
 * - La modalidad y la entidad deben existir y estar activas. Al editar se acepta la que el
 *   proceso ya tiene aunque se haya desactivado: los catálogos se desactivan, no se eliminan.
 * - Un proceso nuevo empieza en preparación. El estado solo cambia con cambiarEstado(): lo
 *   cambia una persona, sin transiciones prohibidas (el checklist solo avisa), pero no al mismo
 *   estado.
```

por:

```php
 * Reglas:
 * - El objeto, la modalidad y la plataforma son obligatorios; el número, el enlace, la entidad
 *   contratante y el valor, opcionales.
 * - Opcionales: la fuente de financiación, el responsable, las fechas de apertura y de cierre y
 *   las observaciones. El cierre no puede ser anterior a la apertura (se compara con lo que
 *   quedaría guardado, no solo con lo que trae el pedido).
 * - La modalidad, la entidad, la fuente y el responsable deben existir y estar activos. Al editar
 *   se acepta el que el proceso ya tiene aunque se haya desactivado: los catálogos se desactivan,
 *   no se eliminan.
 * - Un proceso nuevo empieza en preparación. El estado solo cambia con cambiarEstado(): lo
 *   cambia una persona, sin transiciones prohibidas (el checklist solo avisa), pero no al mismo
 *   estado.
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (3 de 12):

```php
        'url_proceso' => 'url_proceso',
        'entidad_contratante_id' => 'entidad_contratante',
        'monto' => 'monto',
    ];

    private const TEXTOS = ['objeto', 'codigo_proceso', 'url_proceso'];

    private const OBLIGATORIOS = [
        'objeto' => 'Escribe el objeto del proceso.',
```

por:

```php
        'url_proceso' => 'url_proceso',
        'entidad_contratante_id' => 'entidad_contratante',
        'monto' => 'monto',
        'fuente_id' => 'fuente_id',
        'responsable_id' => 'responsable_id',
        'fecha_apertura' => 'fecha_apertura',
        'fecha_cierre' => 'fecha_cierre',
        'observaciones' => 'observaciones',
    ];

    private const TEXTOS = ['objeto', 'codigo_proceso', 'url_proceso', 'observaciones'];

    private const OBLIGATORIOS = [
        'objeto' => 'Escribe el objeto del proceso.',
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (4 de 12):

```php
        'plataforma' => 'Elige la plataforma del proceso.',
    ];

    public const DESACTUALIZADO = 'Otra persona cambió este proceso mientras lo editabas. Ciérralo y vuelve a abrirlo para ver lo último.';

    private const RELACIONES = ['modalidadRel:id,nombre,clave,activo', 'entidadContratante:id,nombre', 'usuarioCreo:id,name'];

    public function __construct(private readonly HistorialChecklist $historial)
    {
```

por:

```php
        'plataforma' => 'Elige la plataforma del proceso.',
    ];

    public const CIERRE_ANTERIOR = 'La fecha de cierre no puede ser anterior a la de apertura.';

    public const DESACTUALIZADO = 'Otra persona cambió este proceso mientras lo editabas. Ciérralo y vuelve a abrirlo para ver lo último.';

    private const RELACIONES = ['modalidadRel:id,nombre,clave,activo', 'entidadContratante:id,nombre', 'fuente:id,nombre', 'responsable:id,nombre', 'usuarioCreo:id,name'];

    public function __construct(private readonly HistorialChecklist $historial)
    {
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (5 de 12):

```php
        return $this->forma($proceso->load(self::RELACIONES)->loadCount('contratos'));
    }

    /** @param array{objeto: string, modalidad_id: int, plataforma: string, codigo_proceso?: ?string, url_proceso?: ?string, entidad_contratante_id?: ?int, monto?: int|float|string|null} $datos */
    public function crear(Proyecto $proyecto, array $datos, ?User $usuario): ProcesoLicitacion
    {
        $valores = $this->valores([...array_fill_keys(array_keys(self::CAMPOS), null), ...$datos]);

        return DB::transaction(function () use ($proyecto, $valores, $usuario) {
            $this->exigirCatalogos($valores, null);
            $proceso = ProcesoLicitacion::create([
                ...$this->columnas($valores),
                'proyecto' => $proyecto->id,
```

por:

```php
        return $this->forma($proceso->load(self::RELACIONES)->loadCount('contratos'));
    }

    /** @param array{objeto: string, modalidad_id: int, plataforma: string, codigo_proceso?: ?string, url_proceso?: ?string, entidad_contratante_id?: ?int, monto?: int|float|string|null, fuente_id?: ?int, responsable_id?: ?int, fecha_apertura?: ?string, fecha_cierre?: ?string, observaciones?: ?string} $datos */
    public function crear(Proyecto $proyecto, array $datos, ?User $usuario): ProcesoLicitacion
    {
        $valores = $this->valores([...array_fill_keys(array_keys(self::CAMPOS), null), ...$datos]);

        return DB::transaction(function () use ($proyecto, $valores, $usuario) {
            $this->exigirCatalogos($valores, null);
            $this->exigirFechas($valores);
            $proceso = ProcesoLicitacion::create([
                ...$this->columnas($valores),
                'proyecto' => $proyecto->id,
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (6 de 12):

```php
     * Cambia los campos que trae el pedido; los demás no cambian. El estado no se edita aquí.
     * `$actualizado` es el `actualizado` del proceso que tenía el formulario.
     *
     * @param array{objeto?: string, modalidad_id?: int, plataforma?: string, codigo_proceso?: ?string, url_proceso?: ?string, entidad_contratante_id?: ?int, monto?: int|float|string|null} $datos
     */
    public function editar(ProcesoLicitacion $proceso, array $datos, ?string $actualizado, ?User $usuario): ProcesoLicitacion
    {
        $valores = $this->valores($datos);

        return DB::transaction(function () use ($proceso, $valores, $actualizado, $usuario) {
            // Compara con la fila bloqueada: así también se decide sobre la modalidad y la
            // entidad que el proceso tiene ahora, no las de cuando llegó el pedido.
            $actual = $this->bloquearVigente($proceso, $actualizado);
            $this->exigirCatalogos($valores, $actual);
            $antes = $this->valoresDe($actual);
            $cambios = array_filter($valores, fn ($valor, string $campo) => $valor !== $antes[$campo], ARRAY_FILTER_USE_BOTH);
            if ($cambios !== []) {
                $this->adelantarActualizado($actual);
```

por:

```php
     * Cambia los campos que trae el pedido; los demás no cambian. El estado no se edita aquí.
     * `$actualizado` es el `actualizado` del proceso que tenía el formulario.
     *
     * @param array{objeto?: string, modalidad_id?: int, plataforma?: string, codigo_proceso?: ?string, url_proceso?: ?string, entidad_contratante_id?: ?int, monto?: int|float|string|null, fuente_id?: ?int, responsable_id?: ?int, fecha_apertura?: ?string, fecha_cierre?: ?string, observaciones?: ?string} $datos
     */
    public function editar(ProcesoLicitacion $proceso, array $datos, ?string $actualizado, ?User $usuario): ProcesoLicitacion
    {
        $valores = $this->valores($datos);

        return DB::transaction(function () use ($proceso, $valores, $actualizado, $usuario) {
            // Compara con la fila bloqueada: así también se decide sobre los catálogos y las
            // fechas que el proceso tiene ahora, no los de cuando llegó el pedido.
            $actual = $this->bloquearVigente($proceso, $actualizado);
            $this->exigirCatalogos($valores, $actual);
            $antes = $this->valoresDe($actual);
            $this->exigirFechas([...$antes, ...$valores]);
            $cambios = array_filter($valores, fn ($valor, string $campo) => $valor !== $antes[$campo], ARRAY_FILTER_USE_BOTH);
            if ($cambios !== []) {
                $this->adelantarActualizado($actual);
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (7 de 12):

```php
            }

            // El registro sobrevive al proceso: con el proyecto y los nombres de los catálogos.
            $actual->load(['modalidadRel:id,nombre', 'entidadContratante:id,nombre']);
            $datos = $this->valoresDe($actual);
            $this->historial->registrarProceso($actual, 'proceso_eliminado', [
                'proyecto_id' => $actual->proyecto,
```

por:

```php
            }

            // El registro sobrevive al proceso: con el proyecto y los nombres de los catálogos.
            $actual->load(['modalidadRel:id,nombre', 'entidadContratante:id,nombre', 'fuente:id,nombre', 'responsable:id,nombre']);
            $datos = $this->valoresDe($actual);
            $this->historial->registrarProceso($actual, 'proceso_eliminado', [
                'proyecto_id' => $actual->proyecto,
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (8 de 12):

```php
                'entidad_contratante_id' => $datos['entidad_contratante_id'],
                'entidad_contratante' => $actual->entidadContratante?->nombre,
                'monto' => $datos['monto'],
                'estado' => $actual->estado,
            ], [], $usuario);
            $actual->delete();
        });
    }

    private function bloquear(ProcesoLicitacion $proceso): ProcesoLicitacion
    {
        return ProcesoLicitacion::query()->whereKey($proceso->id)->lockForUpdate()->firstOrFail();
```

por:

```php
                'entidad_contratante_id' => $datos['entidad_contratante_id'],
                'entidad_contratante' => $actual->entidadContratante?->nombre,
                'monto' => $datos['monto'],
                'fuente_id' => $datos['fuente_id'],
                'fuente' => $actual->fuente?->nombre,
                'responsable_id' => $datos['responsable_id'],
                'responsable' => $actual->responsable?->nombre,
                'fecha_apertura' => $datos['fecha_apertura'],
                'fecha_cierre' => $datos['fecha_cierre'],
                'observaciones' => $datos['observaciones'],
                'estado' => $actual->estado,
            ], [], $usuario);
            $actual->delete();
        });
    }

    /**
     * Lo que se sabe de un proceso para precargar el formulario de un contrato nuevo: el proceso
     * resumido, si admite contratos y los datos sugeridos. El valor sugerido es el monto menos lo
     * que ya suman los contratos vigentes del proceso (los anulados no cuentan), nunca negativo;
     * sin monto no hay sugerencia.
     *
     * @return array{proceso: array, admite_contratos: bool, precarga: array}
     */
    public function precargaContrato(ProcesoLicitacion $proceso): array
    {
        $proceso->load(['modalidadRel:id,nombre', 'entidadContratante:id,nombre']);
        $modalidad = $proceso->modalidadRel;

        return [
            'proceso' => [
                'id' => $proceso->id,
                'codigo_proceso' => $proceso->codigo_proceso,
                'objeto' => $proceso->objeto,
                'estado' => $proceso->estado,
                'modalidad' => $modalidad ? ['id' => $modalidad->id, 'nombre' => $modalidad->nombre] : null,
                'plataforma' => $proceso->plataforma,
                'url_proceso' => $proceso->url_proceso,
                'monto' => $proceso->monto,
            ],
            'admite_contratos' => in_array($proceso->estado, ProcesoLicitacion::ADMITEN_CONTRATOS, true),
            'precarga' => [
                'objeto' => $proceso->objeto,
                'contratante' => $proceso->entidadContratante?->nombre,
                'valor_inicial' => $this->valorSugerido($proceso),
            ],
        ];
    }

    private function valorSugerido(ProcesoLicitacion $proceso): ?string
    {
        if ($proceso->monto === null) {
            return null;
        }
        $restante = BigDecimal::of($proceso->monto);
        $proceso->contratos()
            ->where(fn ($q) => $q->whereNull('estado')->orWhere('estado', '<>', Contrato::ANULADO))
            ->pluck('valor_inicial')
            ->each(function ($valor) use (&$restante) {
                $restante = $restante->minus(BigDecimal::of((string) ($valor ?? 0)));
            });

        return (string) ($restante->isNegative() ? BigDecimal::zero() : $restante)->toScale(2);
    }

    private function bloquear(ProcesoLicitacion $proceso): ProcesoLicitacion
    {
        return ProcesoLicitacion::query()->whereKey($proceso->id)->lockForUpdate()->firstOrFail();
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (9 de 12):

```php
        return $valores;
    }

    /** La modalidad y la entidad del pedido existen y están activas, o son las que el proceso ya tiene. */
    private function exigirCatalogos(array $valores, ?ProcesoLicitacion $actual): void
    {
        $errores = array_filter([
            'modalidad_id' => $this->errorCatalogo(Modalidad::class, $valores['modalidad_id'] ?? null, $actual?->modalidad, 'modalidad'),
            'entidad_contratante_id' => $this->errorCatalogo(Entidad::class, $valores['entidad_contratante_id'] ?? null, $actual?->entidad_contratante, 'entidad'),
        ]);
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
```

por:

```php
        return $valores;
    }

    /** Los catálogos del pedido existen y están activos, o son los que el proceso ya tiene. */
    private function exigirCatalogos(array $valores, ?ProcesoLicitacion $actual): void
    {
        $errores = array_filter([
            'modalidad_id' => $this->errorCatalogo(Modalidad::class, $valores['modalidad_id'] ?? null, $actual?->modalidad, 'modalidad'),
            'entidad_contratante_id' => $this->errorCatalogo(Entidad::class, $valores['entidad_contratante_id'] ?? null, $actual?->entidad_contratante, 'entidad'),
            'fuente_id' => $this->errorCatalogo(FuenteFinanciacion::class, $valores['fuente_id'] ?? null, $actual?->fuente_id, 'fuente'),
            'responsable_id' => $this->errorCatalogo(Responsable::class, $valores['responsable_id'] ?? null, $actual?->responsable_id, 'responsable', false),
        ]);
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (10 de 12):

```php
    }

    /** @param class-string<Model> $modelo */
    private function errorCatalogo(string $modelo, ?int $id, ?int $actual, string $nombre): ?string
    {
        if ($id === null) {
            return null;
        }
        $fila = $modelo::query()->find($id, ['id', 'activo']);
        if ($fila === null) {
            return "Elige una {$nombre} de la lista.";
        }

        return $fila->activo || $id === $actual ? null : "La {$nombre} elegida está inactiva: elige otra.";
    }

    /** @return array<string, mixed> los campos de la API con los valores guardados */
    private function valoresDe(ProcesoLicitacion $proceso): array
    {
        return array_map(fn (string $columna) => $proceso->{$columna}, self::CAMPOS);
    }

    private function columnas(array $valores): array
```

por:

```php
    }

    /** @param class-string<Model> $modelo */
    private function errorCatalogo(string $modelo, ?int $id, ?int $actual, string $nombre, bool $femenino = true): ?string
    {
        if ($id === null) {
            return null;
        }
        $fila = $modelo::query()->find($id, ['id', 'activo']);
        if ($fila === null) {
            return ($femenino ? 'Elige una' : 'Elige un') . " {$nombre} de la lista.";
        }
        if ($fila->activo || $id === $actual) {
            return null;
        }

        return $femenino ? "La {$nombre} elegida está inactiva: elige otra." : "El {$nombre} elegido está inactivo: elige otro.";
    }

    /** El cierre no es anterior a la apertura; `$valores` trae las dos fechas, como quedarían. */
    private function exigirFechas(array $valores): void
    {
        $apertura = $valores['fecha_apertura'] ?? null;
        $cierre = $valores['fecha_cierre'] ?? null;
        if ($apertura !== null && $cierre !== null && $cierre < $apertura) {
            throw new ReglaNegocioException(self::CIERRE_ANTERIOR, [self::CIERRE_ANTERIOR], ['fecha_cierre' => [self::CIERRE_ANTERIOR]]);
        }
    }

    /** @return array<string, mixed> los campos de la API con los valores guardados (las fechas como año-mes-día) */
    private function valoresDe(ProcesoLicitacion $proceso): array
    {
        return array_map(function (string $columna) use ($proceso) {
            $valor = $proceso->{$columna};

            return $valor instanceof DateTimeInterface ? $valor->format('Y-m-d') : $valor;
        }, self::CAMPOS);
    }

    private function columnas(array $valores): array
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (11 de 12):

```php
    {
        $modalidad = $proceso->modalidadRel;
        $entidad = $proceso->entidadContratante;

        return [
            'id' => $proceso->id,
```

por:

```php
    {
        $modalidad = $proceso->modalidadRel;
        $entidad = $proceso->entidadContratante;
        $fuente = $proceso->fuente;
        $responsable = $proceso->responsable;

        return [
            'id' => $proceso->id,
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (12 de 12):

```php
            'url_proceso' => $proceso->url_proceso,
            'entidad_contratante' => $entidad ? ['id' => $entidad->id, 'nombre' => $entidad->nombre] : null,
            'monto' => $proceso->monto,
            'estado' => $proceso->estado,
            'contratos' => (int) $proceso->contratos_count,
            'creado' => $proceso->created_at?->toIso8601String(),
```

por:

```php
            'url_proceso' => $proceso->url_proceso,
            'entidad_contratante' => $entidad ? ['id' => $entidad->id, 'nombre' => $entidad->nombre] : null,
            'monto' => $proceso->monto,
            'fuente' => $fuente ? ['id' => $fuente->id, 'nombre' => $fuente->nombre] : null,
            'responsable' => $responsable ? ['id' => $responsable->id, 'nombre' => $responsable->nombre] : null,
            'fecha_apertura' => $proceso->fecha_apertura?->format('Y-m-d'),
            'fecha_cierre' => $proceso->fecha_cierre?->format('Y-m-d'),
            'observaciones' => $proceso->observaciones,
            'estado' => $proceso->estado,
            'contratos' => (int) $proceso->contratos_count,
            'creado' => $proceso->created_at?->toIso8601String(),
```

En `resources/js/utils/contratacion.js`, reemplazar (1 de 2):

```js
    ['desierto', 'Desierto'],
    ['celebrado', 'Celebrado'],
    ['cancelado', 'Cancelado'],
];

/** Estados que cierran un proceso: salir de ellos es inusual. */
export const ESTADOS_CIERRE = ['desierto', 'celebrado', 'cancelado'];

// Paso de cada estado en el flujo preparación → publicado → evaluación → adjudicado o desierto →
// celebrado. Cancelado no tiene paso: se puede cancelar desde cualquier estado.
```

por:

```js
    ['desierto', 'Desierto'],
    ['celebrado', 'Celebrado'],
    ['cancelado', 'Cancelado'],
    ['revocado', 'Revocado'],
];

/** Estados que cierran un proceso: salir de ellos es inusual. */
export const ESTADOS_CIERRE = ['desierto', 'celebrado', 'cancelado', 'revocado'];

// Paso de cada estado en el flujo preparación → publicado → evaluación → adjudicado o desierto →
// celebrado. Cancelado no tiene paso: se puede cancelar desde cualquier estado.
```

En `resources/js/utils/contratacion.js`, reemplazar (2 de 2):

```js
    celebrado: 'verde-pleno',
    desierto: 'coral',
    cancelado: 'coral',
};

export const MAXIMO_OBSERVACION = 2000;
```

por:

```js
    celebrado: 'verde-pleno',
    desierto: 'coral',
    cancelado: 'coral',
    revocado: 'coral',
};

export const MAXIMO_OBSERVACION = 2000;
```

En `routes/contratacion.php`, reemplazar (1 de 1):

```php
// Lectura: cualquier usuario autenticado.
Route::get('/proyectos/{proyecto}/procesos', [ProcesoContratacionController::class, 'index'])
    ->name('procesos.index');

// Procesos de contratación: Administrador o Gestor.
Route::middleware('can:editar-datos')->group(function () {
```

por:

```php
// Lectura: cualquier usuario autenticado.
Route::get('/proyectos/{proyecto}/procesos', [ProcesoContratacionController::class, 'index'])
    ->name('procesos.index');
Route::get('/procesos/{proceso}/precarga-contrato', [ProcesoContratacionController::class, 'precargaContrato'])
    ->name('procesos.precarga-contrato');

// Procesos de contratación: Administrador o Gestor.
Route::middleware('can:editar-datos')->group(function () {
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/ProcesosContratacionApiTest.php`
Expected: pasan todas.

Run: `npx vitest run resources/js/utils/contratacion.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 930 passed.

Run: `npm test`
Expected: 461 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Http/Controllers/ProcesoContratacionController.php app/Models/ProcesoLicitacion.php app/Services/Contratacion/ProcesosContratacion.php resources/js/utils/contratacion.js resources/js/utils/contratacion.test.js routes/contratacion.php tests/Feature/Contratacion/ProcesosContratacionApiTest.php
git commit -m "feat: process funding, dates, owner, revoked state and contract preload"
```

---

### Tarea 5: Servicio de contratos

Guardar con validación, proyecto opcional, vínculo con el proceso, valores iniciales con recálculo, avisos, historial y borrado protegido.

**Files:**
- Create: `app/Http/Controllers/ContratoController.php`
- Create: `app/Services/Contratacion/ContratoGuardado.php`
- Create: `app/Services/Contratacion/ContratosService.php`
- Create: `app/Services/Contratacion/ControlDeVersion.php`
- Test (create): `tests/Feature/Contratacion/ContratosApiTest.php`
- Modify: `app/Http/Controllers/ProyectoController.php`
- Modify: `app/Services/Contratacion/ProcesosContratacion.php`
- Modify: `app/Services/Viabilizacion/HistorialChecklist.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Schema/ModificacionesContratoTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/ContratosApiTest.php`:

```php
<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\ActividadContrato;
use App\Models\AnexoContrato;
use App\Models\AvanceFinanciero;
use App\Models\AvanceFisico;
use App\Models\Contrato;
use App\Models\Modalidad;
use App\Models\ModificacionContrato;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Contratos: guardar (crear y editar), eliminar, recalcular y lectura en los listados. */
class ContratosApiTest extends TestCase
{
    use RefreshDatabase;

    private const GUARDAR = '/GestPro/guardarContrato';
    private const ELIMINAR = '/GestPro/eliminarContrato';

    private Proyecto $proyecto;
    private string $publico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
        // Los anexos viven en public/anexos_contratos: en las pruebas, en una carpeta temporal.
        $this->publico = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gestpro-publico-' . uniqid();
        File::ensureDirectoryExists($this->publico . DIRECTORY_SEPARATOR . 'anexos_contratos');
        $this->app->usePublicPath($this->publico);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publico);
        parent::tearDown();
    }

    private function como(Rol|User $quien = Rol::Gestor): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function datos(array $mas = []): array
    {
        return [
            'n_contrato' => 'C-001-2026',
            'objeto' => 'Construcción del acueducto veredal',
            'valor_inicial' => 1000000,
            'estado' => 'en_ejecucion',
            'proyecto' => $this->proyecto->id,
            ...$mas,
        ];
    }

    private function guardar(array $formContrato, array $anexos = [], Rol|User $quien = Rol::Gestor)
    {
        return $this->como($quien)->postJson(self::GUARDAR, ['formContrato' => $formContrato, 'anexos' => $anexos]);
    }

    private function crear(array $mas = []): Contrato
    {
        return Contrato::findOrFail($this->guardar($this->datos($mas))->assertOk()->json('contrato.id'));
    }

    /** El `actualizado` que tendría ahora el formulario del contrato: su updated_at. */
    private function version(Contrato $contrato): ?string
    {
        return $contrato->fresh()->updated_at?->format('Y-m-d H:i:s');
    }

    private function editar(Contrato $contrato, array $mas = [], ?array $anexos = [])
    {
        $form = [...$this->datos(), 'id' => $contrato->id, 'actualizado' => $this->version($contrato), ...$mas];

        return $this->como()->postJson(self::GUARDAR, array_filter(['formContrato' => $form, 'anexos' => $anexos], fn ($v) => $v !== null));
    }

    private function proceso(string $estado = 'adjudicado', array $mas = []): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => $this->proyecto->id,
            'objeto' => 'Obra del acueducto',
            'modalidad' => Modalidad::where('clave', 'licitacion_publica')->firstOrFail()->id,
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-001-2026',
            'url_proceso' => 'https://secop.gov.co/p/1',
            'estado' => $estado,
            ...$mas,
        ]);
    }

    /** Un contrato antiguo: escrito con DB::table, sin marcas de tiempo. */
    private function antiguo(array $mas = []): Contrato
    {
        $id = DB::table('contratos')->insertGetId([
            'proyecto' => $this->proyecto->id,
            'n_contrato' => 'C-VIEJO',
            'objeto' => 'Contrato antiguo',
            'valor' => 100000000,
            'valor_inicial' => 100000000,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'fecha_fin_inicial' => '2026-12-31',
            'estado' => 'en_ejecucion',
            ...$mas,
        ]);

        return Contrato::findOrFail($id);
    }

    /** @return list<array{0: string, 1: ?array, 2: ?array}> */
    private function eventos(Contrato|int $contrato): array
    {
        return DB::table('checklist_historial')
            ->where('entidad_tipo', 'contrato')
            ->where('entidad_id', $contrato instanceof Contrato ? $contrato->id : $contrato)
            ->orderBy('id')->get()
            ->map(fn ($h) => [$h->evento, json_decode((string) $h->antes, true), json_decode((string) $h->despues, true)])
            ->all();
    }

    private function archivo(string $nombre): string
    {
        $ruta = 'anexos_contratos/' . $nombre;
        file_put_contents($this->publico . DIRECTORY_SEPARATOR . $ruta, 'pdf');

        return $ruta;
    }

    // ------------------------------------------------------------------ crear

    public function test_un_gestor_crea_un_contrato_con_todos_sus_campos(): void
    {
        $gestor = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Ana Gestora']);
        $obra = TipoProceso::where('nombre', 'Obra')->firstOrFail();
        Carbon::setTestNow('2026-11-18 10:00:00');

        $r = $this->guardar($this->datos([
            'n_contrato' => '  C-001-2026 ',
            'contratante' => 'Gobernación del Cesar',
            'contratante_nit' => '892399999-1',
            'contratista' => 'Consorcio Agua',
            'contratista_identificacion' => '901234567-8',
            'tipo_contrato_id' => $obra->id,
            'valor_inicial' => '1500000.5',
            'fecha_inicio' => '2026-02-01',
            'fecha_fin_inicial' => '2026-12-31',
            'fecha_suscripcion' => '2026-01-20',
            'numero_rp' => 'RP-77',
            'fecha_rp' => '2026-01-25',
            'fecha_acta_inicio' => '2026-02-01',
            'supervisor' => 'Ing. Pérez',
            'interventoria' => 'Interventoría Andina',
            'estado' => 'celebrado',
            'anticipo' => true,
            'porcentaje_anticipo' => 30,
            'avance_fisico' => 10,
            'avance_financiero' => 5,
            // Lo que la pantalla manda de más y no se escribe desde aquí.
            'valor' => 999,
            'fecha_fin' => '2030-01-01',
            'secop_id_contrato' => 'CO1.PCCNTR.1',
            'actividades' => [],
        ]), [], $gestor)->assertOk();

        $id = $r->json('contrato.id');
        $r->assertExactJson([
            'success' => true,
            'contrato' => [
                'id' => $id,
                'proyecto' => $this->proyecto->id,
                'n_contrato' => 'C-001-2026',
                'objeto' => 'Construcción del acueducto veredal',
                'contratante' => 'Gobernación del Cesar',
                'contratante_nit' => '892399999-1',
                'contratista' => 'Consorcio Agua',
                'contratista_identificacion' => '901234567-8',
                'tipo_contrato_id' => $obra->id,
                'tipo_contrato' => ['id' => $obra->id, 'nombre' => 'Obra', 'activo' => true],
                'valor_inicial' => '1500000.50',
                'valor' => '1500000.50',
                'fecha_inicio' => '2026-02-01',
                'fecha_fin_inicial' => '2026-12-31',
                'fecha_fin' => '2026-12-31',
                'fecha_suscripcion' => '2026-01-20',
                'numero_rp' => 'RP-77',
                'fecha_rp' => '2026-01-25',
                'fecha_acta_inicio' => '2026-02-01',
                'supervisor' => 'Ing. Pérez',
                'interventoria' => 'Interventoría Andina',
                'estado' => 'celebrado',
                'estado_original' => null,
                'anticipo' => true,
                'porcentaje_anticipo' => 30,
                'avance_fisico' => 10,
                'avance_financiero' => 5,
                'proceso_id' => null,
                'proceso' => null,
                'secop_id_contrato' => null,
                'secop_proceso' => null,
                'url_secop' => null,
                'secop_consultado_at' => null,
                'anexos' => [],
                'usuario_creo' => 'Ana Gestora',
                'created_at' => '2026-11-18 10:00:00',
                'updated_at' => '2026-11-18 10:00:00',
            ],
            'avisos' => [],
            'proponer_celebrado' => false,
        ]);

        $contrato = Contrato::findOrFail($id);
        $this->assertSame($gestor->id, $contrato->usuario_creo_id);
        $this->assertNull($contrato->secop_id_contrato);
        Carbon::setTestNow();
    }

    public function test_al_crear_el_valor_y_la_fecha_fin_vigentes_son_los_iniciales(): void
    {
        $contrato = $this->crear(['valor_inicial' => 200000000, 'fecha_fin_inicial' => '2027-06-30']);

        $this->assertSame('200000000.00', $contrato->valor_inicial);
        $this->assertSame('200000000.00', $contrato->valor);
        $this->assertSame('2027-06-30', $contrato->fecha_fin_inicial->format('Y-m-d'));
        $this->assertSame('2027-06-30', $contrato->fecha_fin->format('Y-m-d'));
        $this->assertFalse($contrato->anticipo);
    }

    public function test_se_crea_un_contrato_sin_proyecto(): void
    {
        $r = $this->guardar($this->datos(['proyecto' => null]))->assertOk();

        $this->assertNull($r->json('contrato.proyecto'));
        $this->assertNull(Contrato::findOrFail($r->json('contrato.id'))->proyecto);
    }

    public function test_un_proyecto_que_no_existe_se_rechaza(): void
    {
        $this->guardar($this->datos(['proyecto' => 9999]))->assertUnprocessable()
            ->assertJsonPath('errors.proyecto.0', 'Elige un proyecto de la lista.');
        $this->assertDatabaseCount('contratos', 0);
    }

    public function test_los_anexos_se_guardan_con_el_contrato(): void
    {
        $r = $this->guardar($this->datos(), [
            ['descripcion' => 'Minuta', 'nombreArchivo' => 'minuta.pdf', 'rutaArchivo' => 'anexos_contratos/1_a.pdf', 'fecha' => '2026-01-20'],
        ])->assertOk();

        $r->assertJsonPath('contrato.anexos.0.descripcion', 'Minuta')
            ->assertJsonPath('contrato.anexos.0.nombre_archivo', 'minuta.pdf')
            ->assertJsonPath('contrato.anexos.0.ruta_archivo', 'anexos_contratos/1_a.pdf')
            ->assertJsonPath('contrato.anexos.0.fecha', '2026-01-20');
        $this->assertDatabaseCount('anexos_contratos', 1);
    }

    // ------------------------------------------------------------------ validación

    public static function pedidosInvalidos(): array
    {
        return [
            'sin número' => [['n_contrato' => null], 'n_contrato', 'Escribe el número del contrato.'],
            'número largo' => [['n_contrato' => str_repeat('x', 101)], 'n_contrato', 'El número del contrato admite hasta 100 caracteres.'],
            'sin objeto' => [['objeto' => '   '], 'objeto', 'Escribe el objeto del contrato.'],
            'objeto largo' => [['objeto' => str_repeat('x', 5001)], 'objeto', 'El objeto admite hasta 5000 caracteres.'],
            'contratante largo' => [['contratante' => str_repeat('x', 5001)], 'contratante', 'En el contratante caben hasta 5000 caracteres.'],
            'nit largo' => [['contratante_nit' => str_repeat('1', 31)], 'contratante_nit', 'En el NIT del contratante caben hasta 30 caracteres.'],
            'identificación larga' => [['contratista_identificacion' => str_repeat('1', 31)], 'contratista_identificacion', 'En la identificación del contratista caben hasta 30 caracteres.'],
            'supervisor largo' => [['supervisor' => str_repeat('x', 256)], 'supervisor', 'En el supervisor caben hasta 255 caracteres.'],
            'rp largo' => [['numero_rp' => str_repeat('1', 41)], 'numero_rp', 'En el número del RP caben hasta 40 caracteres.'],
            'sin valor' => [['valor_inicial' => null], 'valor_inicial', 'Escribe el valor inicial del contrato.'],
            'valor no numérico' => [['valor_inicial' => 'mil'], 'valor_inicial', 'Escribe el valor inicial como un número.'],
            'valor negativo' => [['valor_inicial' => -1], 'valor_inicial', 'El valor inicial no puede ser negativo.'],
            'valor con tres decimales' => [['valor_inicial' => '10.555'], 'valor_inicial', 'El valor inicial admite hasta 2 decimales.'],
            'valor enorme' => [['valor_inicial' => '10000000000000'], 'valor_inicial', 'El valor inicial admite hasta 13 cifras enteras y 2 decimales.'],
            'fecha mal escrita' => [['fecha_inicio' => '01/02/2026'], 'fecha_inicio', 'Escribe la fecha de inicio como año-mes-día.'],
            'fecha rp mal escrita' => [['fecha_rp' => 'ayer'], 'fecha_rp', 'Escribe la fecha del RP como año-mes-día.'],
            'sin estado' => [['estado' => null], 'estado', 'Elige el estado del contrato.'],
            'estado de texto libre' => [['estado' => 'En ejecución'], 'estado', 'Elige un estado de la lista.'],
            'anticipo no booleano' => [['anticipo' => 'quizá'], 'anticipo', 'Revisa el anticipo: debe ser sí o no.'],
            'porcentaje de anticipo alto' => [['porcentaje_anticipo' => 51], 'porcentaje_anticipo', 'El porcentaje de anticipo va de 0 a 50.'],
            'porcentaje de anticipo decimal' => [['porcentaje_anticipo' => 10.5], 'porcentaje_anticipo', 'Revisa el porcentaje de anticipo: debe ser un número entero.'],
            'avance físico alto' => [['avance_fisico' => 101], 'avance_fisico', 'El avance físico va de 0 a 100.'],
            'avance financiero negativo' => [['avance_financiero' => -1], 'avance_financiero', 'El avance financiero va de 0 a 100.'],
            'tipo no entero' => [['tipo_contrato_id' => 'obra'], 'tipo_contrato_id', 'Revisa el tipo de contrato: debe ser un número entero.'],
            'tipo booleano' => [['tipo_contrato_id' => true], 'tipo_contrato_id', 'Revisa el tipo de contrato: debe ser un número entero.'],
            'proyecto no entero' => [['proyecto' => 'uno'], 'proyecto', 'Revisa el proyecto: debe ser un número entero.'],
            'proceso no entero' => [['proceso_id' => 'uno'], 'proceso_id', 'Revisa el proceso: debe ser un número entero.'],
        ];
    }

    #[DataProvider('pedidosInvalidos')]
    public function test_un_pedido_invalido_se_rechaza_por_campo(array $cambios, string $campo, string $mensaje): void
    {
        $this->guardar($this->datos($cambios))->assertUnprocessable()
            ->assertJsonPath("errors.{$campo}.0", $mensaje);
        $this->assertDatabaseCount('contratos', 0);
    }

    public function test_los_avances_que_calcula_la_pantalla_se_redondean_a_enteros(): void
    {
        $r = $this->guardar($this->datos(['avance_financiero' => 33.335, 'avance_fisico' => '66.5']))->assertOk();

        $r->assertJsonPath('contrato.avance_financiero', 33)->assertJsonPath('contrato.avance_fisico', 67);
    }

    public function test_los_seis_estados_son_validos(): void
    {
        foreach (Contrato::ESTADOS as $i => $estado) {
            $this->guardar($this->datos(['n_contrato' => "C-{$i}", 'estado' => $estado]))->assertOk()
                ->assertJsonPath('contrato.estado', $estado);
        }
        $this->assertDatabaseCount('contratos', 6);
    }

    public function test_la_fecha_fin_inicial_no_puede_ser_anterior_al_inicio(): void
    {
        $this->guardar($this->datos(['fecha_inicio' => '2026-05-01', 'fecha_fin_inicial' => '2026-04-30']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.fecha_fin_inicial.0', 'La fecha de fin no puede ser anterior a la de inicio.');

        $this->guardar($this->datos(['fecha_inicio' => '2026-05-01', 'fecha_fin_inicial' => '2026-05-01']))->assertOk();
    }

    public function test_al_editar_las_fechas_se_comparan_con_lo_que_quedaria_guardado(): void
    {
        $contrato = $this->crear(['fecha_inicio' => '2026-05-01', 'fecha_fin_inicial' => '2026-12-31']);
        $form = ['id' => $contrato->id, 'actualizado' => $this->version($contrato), 'n_contrato' => 'C-1', 'objeto' => 'X', 'valor_inicial' => 1, 'estado' => 'en_ejecucion', 'fecha_fin_inicial' => '2026-04-01'];

        $this->como()->postJson(self::GUARDAR, ['formContrato' => $form])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_fin_inicial.0', 'La fecha de fin no puede ser anterior a la de inicio.');
    }

    public function test_el_tipo_de_contrato_debe_existir_y_estar_activo_salvo_el_que_ya_tiene(): void
    {
        $obra = TipoProceso::where('nombre', 'Obra')->firstOrFail();
        $suministro = TipoProceso::where('nombre', 'Suministro')->firstOrFail();

        $this->guardar($this->datos(['tipo_contrato_id' => 9999]))->assertUnprocessable()
            ->assertJsonPath('errors.tipo_contrato_id.0', 'Elige un tipo de contrato de la lista.');

        $contrato = $this->crear(['tipo_contrato_id' => $obra->id]);
        $obra->update(['activo' => false]);
        $suministro->update(['activo' => false]);

        $this->guardar($this->datos(['tipo_contrato_id' => $obra->id]))->assertUnprocessable()
            ->assertJsonPath('errors.tipo_contrato_id.0', 'El tipo de contrato elegido está inactivo: elige otro.');
        $this->editar($contrato, ['tipo_contrato_id' => $obra->id, 'objeto' => 'Otro objeto'])->assertOk();
        $this->editar($contrato->fresh(), ['tipo_contrato_id' => $suministro->id])->assertUnprocessable()
            ->assertJsonPath('errors.tipo_contrato_id.0', 'El tipo de contrato elegido está inactivo: elige otro.');
    }

    public function test_los_anexos_se_validan_y_solo_se_aceptan_rutas_de_la_carpeta_de_anexos(): void
    {
        $this->guardar($this->datos(), [['descripcion' => '', 'nombreArchivo' => 'a.pdf', 'rutaArchivo' => 'anexos_contratos/a.pdf', 'fecha' => '2026-01-01']])
            ->assertUnprocessable()->assertJsonPath('errors', ['anexos.0.descripcion' => ['Escribe la descripción del anexo.']]);

        foreach (['../.env', 'anexos_contratos/../../.env', 'otra/a.pdf', 'anexos_contratos/sub/a.pdf', 'anexos_contratos\\..\\a.pdf'] as $ruta) {
            $this->guardar($this->datos(), [['descripcion' => 'X', 'nombreArchivo' => 'a.pdf', 'rutaArchivo' => $ruta, 'fecha' => '2026-01-01']])
                ->assertUnprocessable()
                ->assertJsonPath('errors', ['anexos.0.rutaArchivo' => ['El anexo no es válido: vuelve a subirlo.']]);
        }
        $this->assertDatabaseCount('contratos', 0);
    }

    // ------------------------------------------------------------------ proceso

    public function test_un_contrato_se_vincula_a_un_proceso_adjudicado_o_celebrado_del_mismo_proyecto(): void
    {
        foreach (ProcesoLicitacion::ADMITEN_CONTRATOS as $estado) {
            $proceso = $this->proceso($estado);
            $r = $this->guardar($this->datos(['proceso_id' => $proceso->id]))->assertOk();

            $r->assertJsonPath('contrato.proceso_id', $proceso->id)
                ->assertJsonPath('contrato.proceso', [
                    'id' => $proceso->id,
                    'codigo_proceso' => 'LP-001-2026',
                    'objeto' => 'Obra del acueducto',
                    'estado' => $estado,
                    'modalidad' => ['id' => $proceso->modalidad, 'nombre' => 'Licitación pública'],
                    'plataforma' => 'secop_ii',
                    'url_proceso' => 'https://secop.gov.co/p/1',
                ]);
            $this->assertSame($proceso->id, Contrato::findOrFail($r->json('contrato.id'))->proceso_licitacion);
        }
    }

    public static function estadosQueNoAdmiten(): array
    {
        return array_map(fn (string $e) => [$e], array_values(array_diff(ProcesoLicitacion::ESTADOS, ProcesoLicitacion::ADMITEN_CONTRATOS)));
    }

    #[DataProvider('estadosQueNoAdmiten')]
    public function test_no_se_vincula_a_un_proceso_que_no_esta_adjudicado_ni_celebrado(string $estado): void
    {
        $proceso = $this->proceso($estado);

        $this->guardar($this->datos(['proceso_id' => $proceso->id]))->assertUnprocessable()
            ->assertJsonPath('errors.proceso_id.0', 'Solo se puede vincular un contrato a un proceso adjudicado o celebrado.');
        $this->assertDatabaseCount('contratos', 0);
    }

    public function test_el_proceso_que_ya_tiene_se_conserva_aunque_cambie_de_estado_y_se_puede_desvincular(): void
    {
        $proceso = $this->proceso();
        $contrato = $this->crear(['proceso_id' => $proceso->id]);
        $proceso->update(['estado' => 'cancelado']);

        $this->editar($contrato, ['proceso_id' => $proceso->id, 'objeto' => 'Nuevo objeto'])->assertOk()
            ->assertJsonPath('contrato.proceso_id', $proceso->id);
        // Si no viene en el pedido, tampoco cambia.
        $this->editar($contrato->fresh(), ['objeto' => 'Otro'])->assertOk()->assertJsonPath('contrato.proceso_id', $proceso->id);

        $this->editar($contrato->fresh(), ['proceso_id' => null])->assertOk()->assertJsonPath('contrato.proceso', null);
        $this->assertNull($contrato->fresh()->proceso_licitacion);

        // Una vez desvinculado, volver a vincularlo es un vínculo nuevo: ya no se admite.
        $this->editar($contrato->fresh(), ['proceso_id' => $proceso->id])->assertUnprocessable()
            ->assertJsonPath('errors.proceso_id.0', 'Solo se puede vincular un contrato a un proceso adjudicado o celebrado.');
    }

    public function test_el_proceso_debe_ser_del_mismo_proyecto(): void
    {
        $otro = Proyecto::create(['nombre' => 'Puente']);
        $proceso = $this->proceso('adjudicado', ['proyecto' => $otro->id]);

        $this->guardar($this->datos(['proceso_id' => $proceso->id]))->assertUnprocessable()
            ->assertJsonPath('errors.proceso_id.0', 'El proceso elegido es de otro proyecto.');

        // Tampoco al cambiar el proyecto de un contrato que conserva su proceso.
        $propio = $this->proceso();
        $contrato = $this->crear(['proceso_id' => $propio->id]);
        $this->editar($contrato, ['proyecto' => $otro->id])->assertUnprocessable()
            ->assertJsonPath('errors.proceso_id.0', 'El proceso elegido es de otro proyecto.');
    }

    public function test_un_proceso_que_no_existe_se_rechaza(): void
    {
        $this->guardar($this->datos(['proceso_id' => 9999]))->assertUnprocessable()
            ->assertJsonPath('errors.proceso_id.0', 'Elige un proceso de la lista.');
    }

    public function test_un_contrato_sin_proyecto_toma_el_del_proceso(): void
    {
        $proceso = $this->proceso();

        $r = $this->guardar($this->datos(['proyecto' => null, 'proceso_id' => $proceso->id]))->assertOk();
        $this->assertSame($this->proyecto->id, $r->json('contrato.proyecto'));

        $sinProyecto = $this->crear(['proyecto' => null, 'n_contrato' => 'C-2']);
        $this->editar($sinProyecto, ['proyecto' => null, 'proceso_id' => $proceso->id])->assertOk()
            ->assertJsonPath('contrato.proyecto', $this->proyecto->id);
    }

    public function test_al_editar_se_asigna_o_cambia_el_proyecto(): void
    {
        $otro = Proyecto::create(['nombre' => 'Puente']);
        $contrato = $this->crear(['proyecto' => null]);

        $this->editar($contrato, ['proyecto' => $this->proyecto->id])->assertOk()->assertJsonPath('contrato.proyecto', $this->proyecto->id);
        $this->editar($contrato->fresh(), ['proyecto' => $otro->id])->assertOk()->assertJsonPath('contrato.proyecto', $otro->id);
        $this->editar($contrato->fresh(), ['proyecto' => 9999])->assertUnprocessable()
            ->assertJsonPath('errors.proyecto.0', 'Elige un proyecto de la lista.');
    }

    // ------------------------------------------------------------------ avisos y celebrado

    public function test_el_primer_contrato_de_un_proceso_adjudicado_propone_pasarlo_a_celebrado(): void
    {
        $proceso = $this->proceso();

        $this->guardar($this->datos(['proceso_id' => $proceso->id]))->assertOk()->assertJsonPath('proponer_celebrado', true);
        $this->guardar($this->datos(['proceso_id' => $proceso->id, 'n_contrato' => 'C-2']))->assertOk()->assertJsonPath('proponer_celebrado', false);

        $celebrado = $this->proceso('celebrado');
        $this->guardar($this->datos(['proceso_id' => $celebrado->id]))->assertOk()->assertJsonPath('proponer_celebrado', false);
        $this->guardar($this->datos())->assertOk()->assertJsonPath('proponer_celebrado', false);
        // El estado del proceso no cambia solo: lo cambia la persona si confirma.
        $this->assertSame('adjudicado', $proceso->fresh()->estado);
    }

    public function test_editar_no_propone_celebrado(): void
    {
        $proceso = $this->proceso();
        $contrato = $this->crear();

        $this->editar($contrato, ['proceso_id' => $proceso->id])->assertOk()->assertJsonPath('proponer_celebrado', false);
    }

    public function test_avisa_si_los_contratos_del_proceso_superan_su_monto_sin_contar_los_anulados(): void
    {
        $proceso = $this->proceso('adjudicado', ['monto' => 1000000]);

        $this->guardar($this->datos(['proceso_id' => $proceso->id, 'valor_inicial' => 600000]))->assertOk()->assertJsonPath('avisos', []);
        $anulado = $this->crear(['proceso_id' => $proceso->id, 'valor_inicial' => 900000, 'estado' => 'anulado', 'n_contrato' => 'C-2']);
        $this->assertNotNull($anulado);

        $r = $this->guardar($this->datos(['proceso_id' => $proceso->id, 'valor_inicial' => 400000.01, 'n_contrato' => 'C-3']))->assertOk();
        $r->assertJsonPath('avisos', ['Los contratos del proceso suman $1.000.000,01, más que su valor de $1.000.000,00.']);

        // Sin monto no hay aviso.
        $sinMonto = $this->proceso('adjudicado');
        $this->guardar($this->datos(['proceso_id' => $sinMonto->id, 'valor_inicial' => 5]))->assertOk()->assertJsonPath('avisos', []);
    }

    public function test_el_aviso_tambien_sale_al_editar(): void
    {
        $proceso = $this->proceso('adjudicado', ['monto' => 100]);
        $contrato = $this->crear(['proceso_id' => $proceso->id, 'valor_inicial' => 100]);

        $this->editar($contrato, ['proceso_id' => $proceso->id, 'valor_inicial' => 150])->assertOk()
            ->assertJsonPath('avisos', ['Los contratos del proceso suman $150,00, más que su valor de $100,00.']);
    }

    // ------------------------------------------------------------------ editar

    public function test_al_editar_se_guardan_los_iniciales_y_se_recalcula_con_las_modificaciones(): void
    {
        $contrato = $this->antiguo();
        ModificacionContrato::create(['contrato_id' => $contrato->id, 'tipo' => 'adicion_prorroga', 'valor_adicion' => 30000000, 'dias_prorroga' => 60, 'fecha_modificacion' => '2026-03-01']);
        DB::table('contratos')->where('id', $contrato->id)->update(['valor' => 130000000, 'fecha_fin' => '2027-03-01']);

        // Sin cambiar los iniciales, el valor vigente (con la adición) no se pisa.
        $this->editar($contrato, ['valor_inicial' => 100000000, 'fecha_fin_inicial' => '2026-12-31', 'objeto' => 'Otro objeto'])->assertOk()
            ->assertJsonPath('contrato.valor', '130000000.00')
            ->assertJsonPath('contrato.fecha_fin', '2027-03-01');

        // Con otros iniciales, se recalcula: inicial + adiciones, fin inicial + prórrogas.
        $this->editar($contrato->fresh(), ['valor_inicial' => 120000000, 'fecha_fin_inicial' => '2026-11-30'])->assertOk()
            ->assertJsonPath('contrato.valor_inicial', '120000000.00')
            ->assertJsonPath('contrato.valor', '150000000.00')
            ->assertJsonPath('contrato.fecha_fin_inicial', '2026-11-30')
            ->assertJsonPath('contrato.fecha_fin', '2027-01-29');
    }

    public function test_editar_cambia_solo_los_campos_que_vienen_y_registra_solo_lo_que_cambio(): void
    {
        $contrato = $this->crear(['supervisor' => 'Ing. Pérez', 'contratista' => 'Consorcio Agua']);
        $gestor = User::factory()->create(['rol' => Rol::Gestor]);

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

    public function test_editar_sin_cambios_no_registra_ni_cambia_la_version(): void
    {
        $contrato = $this->crear();
        $version = $this->version($contrato);
        $this->travel(5)->seconds();

        $this->editar($contrato)->assertOk()->assertJsonPath('contrato.updated_at', $version);
        $this->assertCount(1, $this->eventos($contrato));
    }

    public function test_al_editar_los_anexos_se_reemplazan_y_sin_anexos_en_el_pedido_se_conservan(): void
    {
        $contrato = $this->crear();
        AnexoContrato::create(['contrato_id' => $contrato->id, 'descripcion' => 'Viejo', 'nombre_archivo' => 'v.pdf', 'ruta_archivo' => 'anexos_contratos/v.pdf', 'fecha' => '2026-01-01']);

        $this->editar($contrato, [], null)->assertOk()->assertJsonCount(1, 'contrato.anexos');

        $this->editar($contrato->fresh(), [], [
            ['descripcion' => 'Nuevo', 'nombreArchivo' => 'n.pdf', 'rutaArchivo' => 'anexos_contratos/n.pdf', 'fecha' => '2026-02-01'],
        ])->assertOk()->assertJsonPath('contrato.anexos.0.descripcion', 'Nuevo')->assertJsonCount(1, 'contrato.anexos');

        $this->editar($contrato->fresh(), [], [])->assertOk()->assertJsonCount(0, 'contrato.anexos');
    }

    public function test_editar_un_contrato_antiguo_sin_version_funciona_y_le_pone_fecha(): void
    {
        $contrato = $this->antiguo();
        $this->assertNull($contrato->updated_at);

        $r = $this->editar($contrato, ['objeto' => 'Renovado'])->assertOk();
        $this->assertNotNull($r->json('contrato.updated_at'));
        $this->assertNull($r->json('contrato.created_at'));
    }

    public function test_editar_con_una_version_vieja_responde_409(): void
    {
        $contrato = $this->crear();
        $vieja = $this->version($contrato);
        $this->editar($contrato, ['objeto' => 'Primero'])->assertOk();

        $this->editar($contrato->fresh(), ['objeto' => 'Segundo', 'actualizado' => $vieja])->assertStatus(409)
            ->assertJsonPath('mensaje', 'Otra persona cambió este contrato mientras lo editabas. Ciérralo y vuelve a abrirlo para ver lo último.');
        $this->assertSame('Primero', $contrato->fresh()->objeto);

        // Un formulario de un contrato antiguo (sin versión) tampoco pisa uno que ya la tiene.
        $this->editar($contrato->fresh(), ['objeto' => 'Tercero', 'actualizado' => null])->assertStatus(409);
    }

    public function test_cambiar_solo_los_anexos_cambia_la_version_y_queda_en_el_historial(): void
    {
        $contrato = $this->crear();
        $vieja = $this->version($contrato);
        $anexo = ['descripcion' => 'Minuta', 'nombreArchivo' => 'm.pdf', 'rutaArchivo' => 'anexos_contratos/m.pdf', 'fecha' => '2026-02-01'];

        // B cambia solo los anexos.
        $this->editar($contrato, [], [$anexo])->assertOk();
        $this->assertNotSame($vieja, $this->version($contrato));
        [$evento, $antes, $despues] = collect($this->eventos($contrato))->last();
        $this->assertSame('contrato_editado', $evento);
        $this->assertSame(['anexos' => []], $antes);
        $this->assertSame(['anexos' => [
            ['descripcion' => 'Minuta', 'nombre_archivo' => 'm.pdf', 'ruta_archivo' => 'anexos_contratos/m.pdf', 'fecha' => '2026-02-01'],
        ]], $despues);

        // A guarda con la versión que tenía: no borra en silencio el anexo de B.
        $this->editar($contrato->fresh(), ['objeto' => 'De A', 'actualizado' => $vieja], [])->assertStatus(409);
        $this->assertSame(1, $contrato->anexos()->count());

        // Los mismos anexos no cambian la versión ni registran nada.
        $version = $this->version($contrato);
        $eventos = count($this->eventos($contrato));
        $this->travel(5)->seconds();
        $this->editar($contrato->fresh(), [], [$anexo])->assertOk()->assertJsonPath('contrato.updated_at', $version);
        $this->assertCount($eventos, $this->eventos($contrato));
    }

    public function test_anexos_null_conserva_los_anexos(): void
    {
        $contrato = $this->crear();
        AnexoContrato::create(['contrato_id' => $contrato->id, 'descripcion' => 'Viejo', 'nombre_archivo' => 'v.pdf', 'ruta_archivo' => 'anexos_contratos/v.pdf', 'fecha' => '2026-01-01']);
        $form = [...$this->datos(), 'id' => $contrato->id, 'actualizado' => $this->version($contrato)];

        $this->como()->postJson(self::GUARDAR, ['formContrato' => $form, 'anexos' => null])->assertOk()
            ->assertJsonCount(1, 'contrato.anexos');
    }

    public function test_un_contrato_con_proyecto_no_se_queda_sin_proyecto_al_editar(): void
    {
        $contrato = $this->crear();

        $this->editar($contrato, ['proyecto' => null])->assertUnprocessable()
            ->assertJsonPath('errors.proyecto.0', 'El contrato debe seguir en un proyecto.');
        $this->assertSame($this->proyecto->id, (int) $contrato->fresh()->proyecto);
    }

    public function test_al_editar_la_version_debe_venir(): void
    {
        $contrato = $this->crear();
        $form = [...$this->datos(), 'id' => $contrato->id];

        $this->como()->postJson(self::GUARDAR, ['formContrato' => $form])->assertUnprocessable()
            ->assertJsonPath('errors.actualizado.0', 'Falta la versión del contrato que estabas editando. Ciérralo y vuelve a abrirlo.');
    }

    public function test_editar_un_contrato_que_no_existe_responde_404(): void
    {
        $this->como()->postJson(self::GUARDAR, ['formContrato' => [...$this->datos(), 'id' => 9999, 'actualizado' => null]])
            ->assertNotFound()->assertJsonPath('mensaje', 'El contrato no existe.');
    }

    public function test_crear_y_editar_quedan_en_el_historial(): void
    {
        $proceso = $this->proceso();
        $contrato = $this->crear(['proceso_id' => $proceso->id, 'fecha_inicio' => '2026-02-01', 'supervisor' => ' Ing. Pérez ']);

        [$evento, $antes, $despues] = $this->eventos($contrato)[0];
        $this->assertSame('contrato_creado', $evento);
        $this->assertNull($antes);
        $this->assertSame([
            'proyecto' => $this->proyecto->id,
            'proceso_id' => $proceso->id,
            'tipo_contrato_id' => null,
            'n_contrato' => 'C-001-2026',
            'objeto' => 'Construcción del acueducto veredal',
            'contratante' => null,
            'contratante_nit' => null,
            'contratista' => null,
            'contratista_identificacion' => null,
            'supervisor' => 'Ing. Pérez',
            'interventoria' => null,
            'numero_rp' => null,
            'valor_inicial' => '1000000.00',
            'fecha_inicio' => '2026-02-01',
            'fecha_fin_inicial' => null,
            'fecha_suscripcion' => null,
            'fecha_rp' => null,
            'fecha_acta_inicio' => null,
            'estado' => 'en_ejecucion',
            'anticipo' => false,
            'porcentaje_anticipo' => null,
            'avance_fisico' => null,
            'avance_financiero' => null,
        ], $despues);
    }

    // ------------------------------------------------------------------ eliminar

    public function test_eliminar_un_contrato_sin_actas_borra_sus_anexos_y_archivos(): void
    {
        $contrato = $this->crear();
        $ruta = $this->archivo('1_minuta.pdf');
        AnexoContrato::create(['contrato_id' => $contrato->id, 'descripcion' => 'Minuta', 'nombre_archivo' => 'minuta.pdf', 'ruta_archivo' => $ruta, 'fecha' => '2026-01-01']);
        // Un anexo cuyo archivo ya no está no impide eliminar.
        AnexoContrato::create(['contrato_id' => $contrato->id, 'descripcion' => 'Perdido', 'nombre_archivo' => 'p.pdf', 'ruta_archivo' => 'anexos_contratos/perdido.pdf', 'fecha' => '2026-01-01']);
        $otro = $this->crear(['n_contrato' => 'C-2']);
        $ajeno = $this->archivo('2_otro.pdf');
        AnexoContrato::create(['contrato_id' => $otro->id, 'descripcion' => 'Otro', 'nombre_archivo' => 'o.pdf', 'ruta_archivo' => $ajeno, 'fecha' => '2026-01-01']);

        $this->como()->postJson(self::ELIMINAR, ['id' => $contrato->id])->assertOk()
            ->assertJson(['success' => true, 'mensaje' => 'Contrato eliminado.']);

        $this->assertNull(Contrato::find($contrato->id));
        $this->assertSame(1, AnexoContrato::count());
        $this->assertFileDoesNotExist($this->publico . '/' . $ruta);
        $this->assertFileExists($this->publico . '/' . $ajeno);

        [$evento, $antes, $despues] = collect($this->eventos($contrato))->last();
        $this->assertSame('contrato_eliminado', $evento);
        $this->assertSame('C-001-2026', $antes['n_contrato']);
        $this->assertSame($this->proyecto->id, $antes['proyecto']);
        $this->assertSame(['Minuta', 'Perdido'], array_column($antes['anexos'], 'descripcion'));
        $this->assertNull($despues);
    }

    public function test_eliminar_no_toca_archivos_fuera_de_la_carpeta_de_anexos(): void
    {
        $contrato = $this->crear();
        file_put_contents($this->publico . '/secreto.txt', 'x');
        // Una ruta guardada antes de validar los anexos.
        DB::table('anexos_contratos')->insert(['contrato_id' => $contrato->id, 'descripcion' => 'Raro', 'nombre_archivo' => 's', 'ruta_archivo' => 'anexos_contratos/../secreto.txt', 'fecha' => '2026-01-01']);

        $this->como()->postJson(self::ELIMINAR, ['id' => $contrato->id])->assertOk();

        $this->assertFileExists($this->publico . '/secreto.txt');
    }

    public function test_eliminar_no_borra_un_archivo_que_usa_otro_anexo(): void
    {
        $contrato = $this->crear();
        $otro = $this->crear(['n_contrato' => 'C-2']);
        $ruta = $this->archivo('compartido.pdf');
        foreach ([$contrato, $otro] as $c) {
            AnexoContrato::create(['contrato_id' => $c->id, 'descripcion' => 'Minuta', 'nombre_archivo' => 'm.pdf', 'ruta_archivo' => $ruta, 'fecha' => '2026-01-01']);
        }

        $this->como()->postJson(self::ELIMINAR, ['id' => $contrato->id])->assertOk();
        $this->assertFileExists($this->publico . '/' . $ruta);

        $this->como()->postJson(self::ELIMINAR, ['id' => $otro->id])->assertOk();
        $this->assertFileDoesNotExist($this->publico . '/' . $ruta);
    }

    public static function dependencias(): array
    {
        return [
            'acta financiera' => [fn (Contrato $c) => AvanceFinanciero::create(['contrato_id' => $c->id, 'descripcion' => 'Acta 1', 'fecha_acta' => '2026-02-01', 'valor_facturado' => 10])],
            'acta física' => [fn (Contrato $c) => AvanceFisico::create(['contrato_id' => $c->id, 'descripcion_avance_fisico' => 'Acta 1', 'fecha_avance_fisico' => '2026-02-01'])],
            'actividad' => [fn (Contrato $c) => ActividadContrato::create(['contrato_id' => $c->id, 'nombre' => 'Excavación', 'peso' => 50])],
            'modificación' => [fn (Contrato $c) => ModificacionContrato::create(['contrato_id' => $c->id, 'tipo' => 'prorroga', 'dias_prorroga' => 10, 'fecha_modificacion' => '2026-03-01'])],
        ];
    }

    #[DataProvider('dependencias')]
    public function test_no_se_elimina_un_contrato_con_actas_actividades_o_modificaciones(\Closure $crearDependencia): void
    {
        $contrato = $this->crear();
        $ruta = $this->archivo('1_minuta.pdf');
        AnexoContrato::create(['contrato_id' => $contrato->id, 'descripcion' => 'Minuta', 'nombre_archivo' => 'minuta.pdf', 'ruta_archivo' => $ruta, 'fecha' => '2026-01-01']);
        $crearDependencia($contrato);

        $this->como()->postJson(self::ELIMINAR, ['id' => $contrato->id])->assertUnprocessable()
            ->assertJsonPath('mensaje', 'Este contrato tiene actas, actividades o modificaciones: anúlalo en vez de eliminarlo.');

        $this->assertNotNull(Contrato::find($contrato->id));
        $this->assertSame(1, AnexoContrato::count());
        $this->assertFileExists($this->publico . '/' . $ruta);
    }

    public function test_eliminar_un_contrato_que_no_existe_o_sin_id(): void
    {
        $this->como()->postJson(self::ELIMINAR, ['id' => 9999])->assertNotFound()->assertJsonPath('mensaje', 'El contrato no existe.');
        $this->como()->postJson(self::ELIMINAR, [])->assertUnprocessable()->assertJsonPath('errors.id.0', 'Falta el contrato que quieres eliminar.');
    }

    // ------------------------------------------------------------------ recalcular

    public function test_las_modificaciones_siguen_recalculando_desde_el_servicio_sin_cambiar_la_version(): void
    {
        $contrato = $this->crear(['valor_inicial' => 100000000, 'fecha_fin_inicial' => '2026-12-31']);
        $version = $this->version($contrato);
        $this->travel(5)->seconds();

        $this->como()->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $contrato->id, 'tipo' => 'adicion_prorroga', 'valor_adicion' => 60000000,
            'dias_prorroga' => 31, 'fecha_modificacion' => '2026-03-01',
        ])->assertOk()->assertJsonPath('resumen', [
            // JSON no distingue 60 de 60.0: llegan como enteros.
            'valor_inicial' => 100000000,
            'valor_vigente' => 160000000,
            'fecha_fin_inicial' => '2026-12-31',
            'fecha_fin_vigente' => '2027-01-31',
            'porcentaje_adicionado' => 60,
            'supera_limite' => true,
        ]);

        $fresco = $contrato->fresh();
        $this->assertSame('160000000.00', $fresco->valor);
        // Registrar una modificación no deja viejo el formulario del contrato.
        $this->assertSame($version, $this->version($fresco));
        $this->editar($fresco, ['valor_inicial' => 100000000, 'actualizado' => $version])->assertOk()
            ->assertJsonPath('contrato.valor', '160000000.00');
    }

    // ------------------------------------------------------------------ lectura

    public function test_los_listados_traen_las_columnas_nuevas_y_el_proceso_resumido(): void
    {
        $proceso = $this->proceso();
        $obra = TipoProceso::where('nombre', 'Obra')->firstOrFail();
        $con = $this->crear(['proceso_id' => $proceso->id, 'tipo_contrato_id' => $obra->id, 'supervisor' => 'Ing. Pérez', 'fecha_fin_inicial' => '2026-12-31', 'numero_rp' => 'RP-1']);
        $sin = $this->antiguo();
        // Un contrato sin proyecto no sale en los listados por proyecto ni los rompe.
        $this->crear(['proyecto' => null, 'n_contrato' => 'C-SUELTO']);

        $proyectos = $this->como(Rol::Consulta)->getJson('/GestPro/proyectos')->assertOk()->json();
        $listado = $this->como(Rol::Consulta)->getJson('/GestPro/listarContratos?proyecto=' . $this->proyecto->id)->assertOk()->json();
        $deProyectos = collect($proyectos)->firstWhere('id', $this->proyecto->id)['contratos'];

        foreach (['proyectos' => $deProyectos, 'listarContratos' => $listado] as $ruta => $contratos) {
            $this->assertCount(2, $contratos, $ruta);
            $uno = collect($contratos)->firstWhere('id', $con->id);
            foreach ([
                'tipo_contrato_id' => $obra->id, 'supervisor' => 'Ing. Pérez', 'numero_rp' => 'RP-1',
                'proceso_id' => $proceso->id, 'proyecto' => $this->proyecto->id, 'estado' => 'en_ejecucion',
                'usuario_creo_id' => $con->usuario_creo_id, 'updated_at' => $this->version($con),
            ] as $campo => $valor) {
                $this->assertEquals($valor, $uno[$campo], "{$ruta}: {$campo}");
            }
            $this->assertSame('2026-12-31', substr((string) $uno['fecha_fin_inicial'], 0, 10), $ruta);
            $this->assertEquals(1000000, $uno['valor_inicial'], $ruta);
            $this->assertSame(['id', 'codigo_proceso', 'objeto', 'estado', 'modalidad', 'plataforma', 'url_proceso'], array_keys($uno['proceso']), $ruta);
            $this->assertSame('LP-001-2026', $uno['proceso']['codigo_proceso'], $ruta);
            foreach ([
                'contratista_identificacion', 'fecha_suscripcion', 'fecha_rp', 'fecha_acta_inicio', 'estado_original',
                'created_at', 'contratante_nit', 'secop_id_contrato', 'secop_proceso', 'url_secop', 'secop_consultado_at',
                // Lo que ya devolvían.
                'n_contrato', 'objeto', 'valor', 'contratante', 'contratista', 'fecha_inicio', 'fecha_fin',
                'interventoria', 'avance_financiero', 'avance_fisico', 'anticipo', 'porcentaje_anticipo',
                'anexos', 'avancesFinancieros', 'actividades',
            ] as $campo) {
                $this->assertArrayHasKey($campo, $uno, "{$ruta}: {$campo}");
            }

            $viejo = collect($contratos)->firstWhere('id', $sin->id);
            $this->assertNull($viejo['proceso'], $ruta);
            $this->assertNull($viejo['proceso_id'], $ruta);
            $this->assertNull($viejo['updated_at'], $ruta);
        }
        $this->assertArrayHasKey('modificaciones', $listado[0]);
    }

    // ------------------------------------------------------------------ permisos

    public function test_un_lector_no_guarda_ni_elimina_contratos(): void
    {
        $contrato = $this->crear();

        $this->guardar($this->datos(), [], Rol::Consulta)->assertForbidden();
        $this->como(Rol::Consulta)->postJson(self::ELIMINAR, ['id' => $contrato->id])->assertForbidden();
        $this->assertDatabaseCount('contratos', 1);
    }

    public function test_sin_sesion_no_se_guarda(): void
    {
        $this->postJson(self::GUARDAR, ['formContrato' => $this->datos()])->assertUnauthorized();
    }
}
```

En `tests/Feature/Schema/ModificacionesContratoTest.php`, reemplazar (1 de 1):

```php
                'formContrato' => [
                    'n_contrato' => 'C-100',
                    'objeto' => 'Construcción',
                    'valor' => 200000000,
                    'fecha_fin' => '2027-06-30',
                    'estado' => 'En ejecución',
                    'proyecto' => $proyecto->id,
                ],
                'anexos' => [],
```

por:

```php
                'formContrato' => [
                    'n_contrato' => 'C-100',
                    'objeto' => 'Construcción',
                    // El pedido trae los iniciales (etapa 3A): el valor y la fecha fin vigentes se derivan.
                    'valor_inicial' => 200000000,
                    'fecha_fin_inicial' => '2027-06-30',
                    'estado' => 'en_ejecucion',
                    'proyecto' => $proyecto->id,
                ],
                'anexos' => [],
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/ContratosApiTest.php tests/Feature/Schema/ModificacionesContratoTest.php`
Expected: FALLAN 78 y pasan 13: `guardarContrato` todavía no valida, exige `valor` y no conoce los campos, los estados, el proceso ni el proyecto opcional, y `eliminarContrato` no existe.

- [ ] **Paso 3: Implementar**

Crear `app/Http/Controllers/ContratoController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Services\Contratacion\ContratosService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Guardar y eliminar contratos (can:editar-datos). La lectura sigue en ProyectoController
 * (GET /proyectos y GET /listarContratos).
 */
class ContratoController extends Controller
{
    private const MENSAJES = [
        'required' => 'Completa :attribute.',
        'string' => 'Revisa :attribute: debe ser un texto.',
        'integer' => 'Revisa :attribute: debe ser un número entero.',
        'numeric' => 'Revisa :attribute: debe ser un número.',
        'boolean' => 'Revisa :attribute: debe ser sí o no.',
        'array' => 'Revisa :attribute: no es una lista válida.',
        'max' => 'En :attribute caben hasta :max caracteres.',
        'date_format' => 'Escribe :attribute como año-mes-día.',
        'n_contrato.required' => 'Escribe el número del contrato.',
        'n_contrato.max' => 'El número del contrato admite hasta 100 caracteres.',
        'objeto.required' => 'Escribe el objeto del contrato.',
        'objeto.max' => 'El objeto admite hasta 5000 caracteres.',
        'valor_inicial.required' => 'Escribe el valor inicial del contrato.',
        'valor_inicial.numeric' => 'Escribe el valor inicial como un número.',
        'valor_inicial.min' => 'El valor inicial no puede ser negativo.',
        'valor_inicial.decimal' => 'El valor inicial admite hasta 2 decimales.',
        'estado.required' => 'Elige el estado del contrato.',
        'estado.in' => 'Elige un estado de la lista.',
        'porcentaje_anticipo.min' => 'El porcentaje de anticipo va de 0 a 50.',
        'porcentaje_anticipo.max' => 'El porcentaje de anticipo va de 0 a 50.',
        'avance_fisico.min' => 'El avance físico va de 0 a 100.',
        'avance_fisico.max' => 'El avance físico va de 0 a 100.',
        'avance_financiero.min' => 'El avance financiero va de 0 a 100.',
        'avance_financiero.max' => 'El avance financiero va de 0 a 100.',
        'actualizado.present' => 'Falta la versión del contrato que estabas editando. Ciérralo y vuelve a abrirlo.',
        'actualizado.date' => 'La versión del contrato no es válida. Ciérralo y vuelve a abrirlo.',
        'anexos.*.descripcion.required' => 'Escribe la descripción del anexo.',
        'anexos.*.descripcion.max' => 'La descripción del anexo admite hasta 255 caracteres.',
        'anexos.*.nombreArchivo.required' => 'Falta el nombre del archivo del anexo: vuelve a subirlo.',
        'anexos.*.nombreArchivo.max' => 'El nombre del archivo del anexo admite hasta 255 caracteres.',
        'anexos.*.rutaArchivo.required' => 'El anexo no es válido: vuelve a subirlo.',
        'anexos.*.fecha.required' => 'Falta la fecha del anexo.',
        'id.required' => 'Falta el contrato que quieres eliminar.',
    ];

    private const ATRIBUTOS = [
        'id' => 'el contrato',
        'proyecto' => 'el proyecto',
        'proceso_id' => 'el proceso',
        'tipo_contrato_id' => 'el tipo de contrato',
        'n_contrato' => 'el número del contrato',
        'objeto' => 'el objeto',
        'contratante' => 'el contratante',
        'contratante_nit' => 'el NIT del contratante',
        'contratista' => 'el contratista',
        'contratista_identificacion' => 'la identificación del contratista',
        'supervisor' => 'el supervisor',
        'interventoria' => 'la interventoría',
        'numero_rp' => 'el número del RP',
        'valor_inicial' => 'el valor inicial',
        'fecha_inicio' => 'la fecha de inicio',
        'fecha_fin_inicial' => 'la fecha de fin',
        'fecha_suscripcion' => 'la fecha de suscripción',
        'fecha_rp' => 'la fecha del RP',
        'fecha_acta_inicio' => 'la fecha del acta de inicio',
        'estado' => 'el estado',
        'anticipo' => 'el anticipo',
        'porcentaje_anticipo' => 'el porcentaje de anticipo',
        'avance_fisico' => 'el avance físico',
        'avance_financiero' => 'el avance financiero',
        'actualizado' => 'la versión del contrato',
        'anexos' => 'los anexos',
        'anexos.*.descripcion' => 'la descripción del anexo',
        'anexos.*.nombreArchivo' => 'el nombre del archivo del anexo',
        'anexos.*.rutaArchivo' => 'el archivo del anexo',
        'anexos.*.fecha' => 'la fecha del anexo',
    ];

    // Las columnas de valor son decimal(15, 2): 13 cifras enteras y 2 decimales.
    private const CIFRAS_ENTERAS = 13;

    // Lo que sube subirAnexo: un archivo directo en public/anexos_contratos. Otra ruta se rechaza:
    // al eliminar el contrato se borra el archivo.
    private const RUTA_ANEXO = '#^anexos_contratos/[^/\\\\]+$#';

    public function __construct(private readonly ContratosService $contratos)
    {
    }

    /**
     * Crea (sin `formContrato.id`) o edita un contrato. El pedido es `formContrato` con los campos
     * y `anexos`; al editar, los anexos que trae reemplazan a los que había. Los campos que no se
     * escriben desde aquí (el valor y la fecha fin vigentes, la referencia de SECOP, lo que la
     * pantalla manda de más) se ignoran.
     */
    public function guardar(Request $request): JsonResponse
    {
        $form = $request->input('formContrato');
        $form = is_array($form) ? $form : [];
        $editar = ! empty($form['id']);

        $validador = Validator::make($form, $this->reglas($editar), self::MENSAJES, self::ATRIBUTOS);
        $validadorAnexos = Validator::make($request->only('anexos'), $this->reglasAnexos(), self::MENSAJES, self::ATRIBUTOS);
        // Los dos a la vez: así la pantalla muestra todos los errores de una vez.
        $fallaForm = $validador->fails();
        $fallanAnexos = $validadorAnexos->fails();
        if ($fallaForm || $fallanAnexos) {
            throw ValidationException::withMessages([...$validador->errors()->toArray(), ...$validadorAnexos->errors()->toArray()]);
        }
        $datos = $validador->validated();
        // `anexos: null` es como no enviarlos: se conservan.
        $anexos = $request->input('anexos') !== null ? $validadorAnexos->validated()['anexos'] : null;
        unset($datos['id'], $datos['actualizado']);

        if ($editar) {
            $contrato = Contrato::find($form['id']);
            if ($contrato === null) {
                return response()->json(['mensaje' => 'El contrato no existe.'], 404);
            }
            $guardado = $this->contratos->editar($contrato, $datos, $anexos, $form['actualizado'], $request->user());
        } else {
            $guardado = $this->contratos->crear($datos, $anexos ?? [], $request->user());
        }

        return response()->json([
            'success' => true,
            'contrato' => $this->contratos->presentar($guardado->contrato),
            'avisos' => $guardado->avisos,
            'proponer_celebrado' => $guardado->proponerCelebrado,
        ]);
    }

    public function eliminar(Request $request): JsonResponse
    {
        $datos = $request->validate(['id' => ['bail', 'required', $this->entero()]], self::MENSAJES, self::ATRIBUTOS);
        $contrato = Contrato::find($datos['id']);
        if ($contrato === null) {
            return response()->json(['mensaje' => 'El contrato no existe.'], 404);
        }
        $this->contratos->eliminar($contrato, $request->user());

        return response()->json(['success' => true, 'mensaje' => 'Contrato eliminado.']);
    }

    /**
     * Al editar, los opcionales que no vienen en el pedido no cambian y con null se borran; los
     * obligatorios siempre se envían, y también `actualizado` (el updated_at del contrato que
     * tenía el formulario; null para un contrato antiguo, sin fechas).
     */
    private function reglas(bool $editar): array
    {
        $cifras = fn (string $atributo, mixed $valor, Closure $falla) => strlen(ltrim(explode('.', ltrim((string) $valor, '+-'))[0], '0')) <= self::CIFRAS_ENTERAS
            || $falla('El valor inicial admite hasta ' . self::CIFRAS_ENTERAS . ' cifras enteras y 2 decimales.');
        $opcional = fn (mixed ...$reglas) => ['sometimes', 'nullable', 'bail', ...$reglas];
        $fecha = $opcional('string', 'date_format:Y-m-d');

        return [
            'id' => $opcional($this->entero()),
            'proyecto' => $opcional($this->entero()),
            'proceso_id' => $opcional($this->entero()),
            'tipo_contrato_id' => $opcional($this->entero()),
            'n_contrato' => ['bail', 'required', 'string', 'max:100'],
            'objeto' => ['bail', 'required', 'string', 'max:5000'],
            'contratante' => $opcional('string', 'max:5000'),
            'contratante_nit' => $opcional('string', 'max:30'),
            'contratista' => $opcional('string', 'max:5000'),
            'contratista_identificacion' => $opcional('string', 'max:30'),
            'supervisor' => $opcional('string', 'max:255'),
            'interventoria' => $opcional('string', 'max:5000'),
            'numero_rp' => $opcional('string', 'max:40'),
            'valor_inicial' => ['bail', 'required', 'numeric', 'min:0', 'decimal:0,2', $cifras],
            'fecha_inicio' => $fecha,
            'fecha_fin_inicial' => $fecha,
            'fecha_suscripcion' => $fecha,
            'fecha_rp' => $fecha,
            'fecha_acta_inicio' => $fecha,
            'estado' => ['bail', 'required', 'string', Rule::in(Contrato::ESTADOS)],
            'anticipo' => $opcional('boolean'),
            'porcentaje_anticipo' => $opcional('integer', 'min:0', 'max:50'),
            // Los calcula la pantalla y pueden traer decimales: el servicio los redondea.
            'avance_fisico' => $opcional('numeric', 'min:0', 'max:100'),
            'avance_financiero' => $opcional('numeric', 'min:0', 'max:100'),
            ...($editar ? ['actualizado' => ['present', 'nullable', 'bail', 'string', 'date']] : []),
        ];
    }

    private function reglasAnexos(): array
    {
        $ruta = fn (string $atributo, mixed $valor, Closure $falla) => (preg_match(self::RUTA_ANEXO, (string) $valor) === 1
                && ! in_array(basename((string) $valor), ['.', '..'], true))
            || $falla('El anexo no es válido: vuelve a subirlo.');

        return [
            'anexos' => ['sometimes', 'nullable', 'array'],
            'anexos.*' => ['array'],
            'anexos.*.descripcion' => ['bail', 'required', 'string', 'max:255'],
            'anexos.*.nombreArchivo' => ['bail', 'required', 'string', 'max:255'],
            'anexos.*.rutaArchivo' => ['bail', 'required', 'string', $ruta],
            'anexos.*.fecha' => ['bail', 'required', 'string', 'date_format:Y-m-d'],
        ];
    }

    /** Enteros de verdad: un número o un texto de solo cifras (los select envían textos), no true. */
    private function entero(): Closure
    {
        return fn (string $atributo, mixed $valor, Closure $falla) => is_int($valor)
            || (is_string($valor) && preg_match('/^\d+$/', $valor) === 1)
            || $falla('Revisa :attribute: debe ser un número entero.');
    }
}
```

Crear `app/Services/Contratacion/ContratoGuardado.php`:

```php
<?php

namespace App\Services\Contratacion;

use App\Models\Contrato;

/**
 * Lo que deja guardar un contrato: el contrato como quedó, los avisos que no impiden guardar y si
 * conviene proponer pasar su proceso a celebrado (la pantalla pregunta; el estado no cambia solo).
 */
final class ContratoGuardado
{
    /** @param list<string> $avisos */
    public function __construct(
        public readonly Contrato $contrato,
        public readonly array $avisos,
        public readonly bool $proponerCelebrado,
    ) {
    }
}
```

Crear `app/Services/Contratacion/ContratosService.php`:

```php
<?php

namespace App\Services\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\AnexoContrato;
use App\Models\AvanceFinanciero;
use App\Models\AvanceFisico;
use App\Models\Contrato;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\TipoProceso;
use App\Models\User;
use App\Services\Viabilizacion\HistorialChecklist;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Contratos: crear, editar, eliminar, recalcular sus valores vigentes y presentarlos.
 *
 * Reglas:
 * - El número, el objeto, el valor inicial y el estado (uno de Contrato::ESTADOS) son
 *   obligatorios; lo demás, opcional.
 * - El proyecto es opcional: la empresa también ejecuta contratos de proyectos que no formuló. Al
 *   editar se puede asignar o cambiar; si viene, debe existir. Un contrato que ya tiene proyecto no
 *   se queda sin él al editar.
 * - El proceso (columna proceso_licitacion) debe ser del mismo proyecto que el contrato (R3). Un
 *   contrato sin proyecto toma el del proceso. Vincular un proceso nuevo, al crear o al cambiarlo,
 *   exige que esté adjudicado o celebrado; el que ya tiene se conserva aunque haya cambiado de
 *   estado. Desvincular se permite.
 * - El tipo de contrato debe existir y estar activo, o ser el que el contrato ya tiene.
 * - La fecha fin inicial no es anterior a la de inicio (con lo que quedaría guardado).
 * - El pedido escribe el valor inicial y la fecha fin inicial. El valor y la fecha fin vigentes
 *   los deriva recalcular(): inicial más adiciones y prórrogas. Al crear son los iniciales.
 * - Avisos que no impiden guardar: los contratos del proceso que no están anulados suman más que
 *   su monto (R9). Al crear el primer contrato de un proceso adjudicado se propone pasarlo a
 *   celebrado.
 * - Editar trae `actualizado`, el updated_at que tenía el formulario: si ya no es el de la fila,
 *   se rechaza (409). Cambiar los anexos también cambia la versión. Registrar o quitar una modificación recalcula sin cambiar la versión: no
 *   toca lo que edita el formulario.
 * - No se elimina un contrato con actas (financieras o físicas), actividades o modificaciones: se
 *   anula. Al eliminar se borran sus anexos, las filas y los archivos.
 * - Todo queda en checklist_historial (entidad_tipo = contrato, sin expediente): al editar, solo
 *   lo que cambió; al eliminar, los datos para saber qué era.
 */
final class ContratosService
{
    use ControlDeVersion;

    /** Campo de la API => columna del contrato, en el orden del historial. */
    private const CAMPOS = [
        'proyecto' => 'proyecto',
        'proceso_id' => 'proceso_licitacion',
        'tipo_contrato_id' => 'tipo_contrato_id',
        'n_contrato' => 'n_contrato',
        'objeto' => 'objeto',
        'contratante' => 'contratante',
        'contratante_nit' => 'contratante_nit',
        'contratista' => 'contratista',
        'contratista_identificacion' => 'contratista_identificacion',
        'supervisor' => 'supervisor',
        'interventoria' => 'interventoria',
        'numero_rp' => 'numero_rp',
        'valor_inicial' => 'valor_inicial',
        'fecha_inicio' => 'fecha_inicio',
        'fecha_fin_inicial' => 'fecha_fin_inicial',
        'fecha_suscripcion' => 'fecha_suscripcion',
        'fecha_rp' => 'fecha_rp',
        'fecha_acta_inicio' => 'fecha_acta_inicio',
        'estado' => 'estado',
        'anticipo' => 'anticipo',
        'porcentaje_anticipo' => 'porcentaje_anticipo',
        'avance_fisico' => 'avance_fisico',
        'avance_financiero' => 'avance_financiero',
    ];

    private const TEXTOS = [
        'n_contrato', 'objeto', 'contratante', 'contratante_nit', 'contratista',
        'contratista_identificacion', 'supervisor', 'interventoria', 'numero_rp',
    ];

    private const ENTEROS = ['proyecto', 'proceso_id', 'tipo_contrato_id', 'porcentaje_anticipo'];

    // Los avances los calcula la pantalla y pueden traer decimales: se guardan redondeados.
    private const AVANCES = ['avance_fisico', 'avance_financiero'];

    private const OBLIGATORIOS = [
        'n_contrato' => 'Escribe el número del contrato.',
        'objeto' => 'Escribe el objeto del contrato.',
        'valor_inicial' => 'Escribe el valor inicial del contrato.',
        'estado' => 'Elige el estado del contrato.',
    ];

    public const DESACTUALIZADO = 'Otra persona cambió este contrato mientras lo editabas. Ciérralo y vuelve a abrirlo para ver lo último.';

    public const CON_REGISTROS = 'Este contrato tiene actas, actividades o modificaciones: anúlalo en vez de eliminarlo.';

    public const FIN_ANTERIOR = 'La fecha de fin no puede ser anterior a la de inicio.';

    public const SIN_PROYECTO = 'El contrato debe seguir en un proyecto.';

    private const CARPETA_ANEXOS = 'anexos_contratos';

    public function __construct(private readonly HistorialChecklist $historial)
    {
    }

    /**
     * Crea el contrato con sus anexos. Los campos que no vienen quedan vacíos; el anticipo, en no.
     *
     * @param array<string, mixed> $datos campos de CAMPOS
     * @param list<array{descripcion: string, nombreArchivo: string, rutaArchivo: string, fecha: string}> $anexos
     */
    public function crear(array $datos, array $anexos, ?User $usuario): ContratoGuardado
    {
        $valores = $this->valores([...array_fill_keys(array_keys(self::CAMPOS), null), 'anticipo' => false, ...$datos]);

        return DB::transaction(function () use ($valores, $anexos, $usuario) {
            $this->exigirTipo($valores, null);
            [$valores, $proceso] = $this->exigirVinculos($valores, null);
            $this->exigirFechas($valores);

            $contrato = Contrato::create([
                ...$this->columnas($valores),
                'valor' => $valores['valor_inicial'],
                'fecha_fin' => $valores['fecha_fin_inicial'],
                'usuario_creo_id' => $usuario?->id,
            ]);
            $this->guardarAnexos($contrato, $anexos);
            $this->historial->registrarContrato($contrato, 'contrato_creado', [], $this->valoresDe($contrato), $usuario);

            // El primero: el proceso no tenía otros contratos, ni siquiera anulados.
            $proponer = $proceso?->estado === ProcesoLicitacion::ADJUDICADO
                && $proceso->contratos()->count() === 1;

            return new ContratoGuardado($contrato, $this->avisos($contrato), $proponer);
        });
    }

    /**
     * Cambia los campos que trae el pedido; los demás no cambian. Con `$anexos` (aunque sea una
     * lista vacía) los anexos se reemplazan; con null se conservan. `$actualizado` es el
     * updated_at que tenía el formulario. Si cambia algún campo o los anexos, cambia la versión y
     * queda en el historial. Después recalcula el valor y la fecha fin vigentes.
     *
     * @param array<string, mixed> $datos campos de CAMPOS
     * @param ?list<array{descripcion: string, nombreArchivo: string, rutaArchivo: string, fecha: string}> $anexos
     */
    public function editar(Contrato $contrato, array $datos, ?array $anexos, ?string $actualizado, ?User $usuario): ContratoGuardado
    {
        $valores = $this->valores($datos);

        return DB::transaction(function () use ($contrato, $valores, $anexos, $actualizado, $usuario) {
            // Se decide con la fila bloqueada: el tipo, el proceso y las fechas que tiene ahora.
            $actual = $this->bloquear($contrato);
            $this->exigirVersion($actual, $actualizado, self::DESACTUALIZADO);
            $this->exigirTipo($valores, $actual);
            [$valores] = $this->exigirVinculos($valores, $actual);
            // Sin proceso que lo dé, un proyecto null quitaría el que tiene.
            if ($actual->proyecto !== null && array_key_exists('proyecto', $valores) && $valores['proyecto'] === null) {
                throw ValidationException::withMessages(['proyecto' => self::SIN_PROYECTO]);
            }
            $antes = $this->valoresDe($actual);
            $this->exigirFechas([...$antes, ...$valores]);

            $cambios = array_filter($valores, fn ($valor, string $campo) => $valor !== $antes[$campo], ARRAY_FILTER_USE_BOTH);
            $anexosAntes = $this->anexosDe($actual);
            $anexosDespues = $anexos === null ? $anexosAntes : array_map(fn (array $a) => [
                'descripcion' => $a['descripcion'],
                'nombre_archivo' => $a['nombreArchivo'],
                'ruta_archivo' => $a['rutaArchivo'],
                'fecha' => $a['fecha'],
            ], array_values($anexos));
            $cambianAnexos = $anexosDespues !== $anexosAntes;

            if ($cambios !== [] || $cambianAnexos) {
                $this->adelantarActualizado($actual);
                $actual->fill($this->columnas($cambios))->save();
                if ($cambianAnexos) {
                    $actual->anexos()->delete();
                    $this->guardarAnexos($actual, $anexos);
                }
                $this->historial->registrarContrato(
                    $actual,
                    'contrato_editado',
                    [...array_intersect_key($antes, $cambios), ...($cambianAnexos ? ['anexos' => $anexosAntes] : [])],
                    [...$cambios, ...($cambianAnexos ? ['anexos' => $anexosDespues] : [])],
                    $usuario
                );
            }
            $this->recalcular($actual);
            $actual->refresh();

            return new ContratoGuardado($actual, $this->avisos($actual), false);
        });
    }

    public function eliminar(Contrato $contrato, ?User $usuario): void
    {
        $rutas = DB::transaction(function () use ($contrato, $usuario) {
            // Con la fila bloqueada, un acta o una modificación que se registre a la vez espera y
            // luego no encuentra el contrato, o termina antes y aquí se cuenta.
            $actual = $this->bloquear($contrato);
            $conRegistros = AvanceFinanciero::query()->where('contrato_id', $actual->id)->exists()
                || AvanceFisico::query()->where('contrato_id', $actual->id)->exists()
                || $actual->actividades()->exists()
                || $actual->modificaciones()->exists();
            if ($conRegistros) {
                throw new ReglaNegocioException(self::CON_REGISTROS);
            }

            $anexos = $this->anexosDe($actual);
            // El registro sobrevive al contrato: con los vigentes y los anexos que tenía.
            $this->historial->registrarContrato($actual, 'contrato_eliminado', [
                ...$this->valoresDe($actual),
                'valor' => $actual->valor,
                'fecha_fin' => $actual->fecha_fin?->format('Y-m-d'),
                'anexos' => $anexos,
            ], [], $usuario);
            $actual->anexos()->delete();
            $actual->delete();

            return array_unique(array_column($anexos, 'ruta_archivo'));
        });

        // Los archivos, solo cuando las filas ya no están: si algo fallara antes, no se pierden. Un
        // archivo que otro anexo (de cualquier contrato) también usa se conserva.
        foreach ($rutas as $ruta) {
            if (! AnexoContrato::query()->where('ruta_archivo', $ruta)->exists()) {
                $this->borrarArchivo($ruta);
            }
        }
    }

    /**
     * Deriva el valor y la fecha fin vigentes desde el valor inicial y la fecha fin inicial más
     * todas las modificaciones. Devuelve el resumen: vigentes, % adicionado y si supera el 50 %.
     * No cambia la versión del contrato (updated_at): no toca lo que edita el formulario.
     *
     * @return array{valor_inicial: float, valor_vigente: float, fecha_fin_inicial: ?string, fecha_fin_vigente: ?string, porcentaje_adicionado: float, supera_limite: bool}
     */
    public function recalcular(Contrato $contrato): array
    {
        $fila = DB::table('contratos')->where('id', $contrato->id)->first();

        $valorInicial = (float) ($fila->valor_inicial ?? $fila->valor ?? 0);
        $fechaFinInicial = $fila->fecha_fin_inicial ?? $fila->fecha_fin;
        $fechaFinInicial = $fechaFinInicial !== null ? Carbon::parse($fechaFinInicial)->toDateString() : null;

        $modificaciones = DB::table('modificaciones_contrato')->where('contrato_id', $contrato->id);
        $sumaAdiciones = (float) (clone $modificaciones)->sum('valor_adicion');
        $sumaDias = (int) (clone $modificaciones)->sum('dias_prorroga');

        $valorVigente = $valorInicial + $sumaAdiciones;
        $fechaFinVigente = $fechaFinInicial !== null
            ? Carbon::parse($fechaFinInicial)->addDays($sumaDias)->toDateString()
            : null;

        // Con el constructor de consultas, no con el modelo: así no cambia updated_at.
        DB::table('contratos')->where('id', $contrato->id)->update([
            'valor' => $valorVigente,
            'fecha_fin' => $fechaFinVigente,
        ]);

        $porcentaje = $valorInicial > 0 ? ($sumaAdiciones / $valorInicial) * 100 : 0.0;

        return [
            'valor_inicial' => $valorInicial,
            'valor_vigente' => $valorVigente,
            'fecha_fin_inicial' => $fechaFinInicial,
            'fecha_fin_vigente' => $fechaFinVigente,
            'porcentaje_adicionado' => round($porcentaje, 2),
            'supera_limite' => $porcentaje > 50,
        ];
    }

    public function presentar(Contrato $contrato): array
    {
        $contrato->load(['tipoContrato', 'procesoLicitacion.modalidadRel:id,nombre', 'usuarioCreo:id,name', 'anexos' => fn ($q) => $q->orderBy('id')]);
        $tipo = $contrato->tipoContrato;
        $fecha = fn (?DateTimeInterface $valor) => $valor?->format('Y-m-d');
        $momento = fn (?DateTimeInterface $valor) => $valor?->format('Y-m-d H:i:s');

        return [
            'id' => $contrato->id,
            'proyecto' => $this->entero($contrato->proyecto),
            'n_contrato' => $contrato->n_contrato,
            'objeto' => $contrato->objeto,
            'contratante' => $contrato->contratante,
            'contratante_nit' => $contrato->contratante_nit,
            'contratista' => $contrato->contratista,
            'contratista_identificacion' => $contrato->contratista_identificacion,
            'tipo_contrato_id' => $contrato->tipo_contrato_id,
            'tipo_contrato' => $tipo ? ['id' => $tipo->id, 'nombre' => $tipo->nombre, 'activo' => $tipo->activo] : null,
            'valor_inicial' => $contrato->valor_inicial,
            'valor' => $contrato->valor,
            'fecha_inicio' => $fecha($contrato->fecha_inicio),
            'fecha_fin_inicial' => $fecha($contrato->fecha_fin_inicial),
            'fecha_fin' => $fecha($contrato->fecha_fin),
            'fecha_suscripcion' => $fecha($contrato->fecha_suscripcion),
            'numero_rp' => $contrato->numero_rp,
            'fecha_rp' => $fecha($contrato->fecha_rp),
            'fecha_acta_inicio' => $fecha($contrato->fecha_acta_inicio),
            'supervisor' => $contrato->supervisor,
            'interventoria' => $contrato->interventoria,
            'estado' => $contrato->estado,
            'estado_original' => $contrato->estado_original,
            'anticipo' => (bool) $contrato->anticipo,
            'porcentaje_anticipo' => $this->entero($contrato->porcentaje_anticipo),
            'avance_fisico' => $this->entero($contrato->avance_fisico),
            'avance_financiero' => $this->entero($contrato->avance_financiero),
            'proceso_id' => $this->entero($contrato->proceso_licitacion),
            'proceso' => $contrato->procesoLicitacion ? $this->resumenProceso($contrato->procesoLicitacion) : null,
            'secop_id_contrato' => $contrato->secop_id_contrato,
            'secop_proceso' => $contrato->secop_proceso,
            'url_secop' => $contrato->url_secop,
            'secop_consultado_at' => $momento($contrato->secop_consultado_at),
            'anexos' => $contrato->anexos->map(fn (AnexoContrato $a) => [
                'id' => $a->id,
                'descripcion' => $a->descripcion,
                'nombre_archivo' => $a->nombre_archivo,
                'ruta_archivo' => $a->ruta_archivo,
                'fecha' => $fecha($a->fecha),
            ])->all(),
            'usuario_creo' => $contrato->usuarioCreo?->name,
            'created_at' => $momento($contrato->created_at),
            'updated_at' => $momento($contrato->updated_at),
        ];
    }

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
            ->whereIn('id', $filas->pluck('proceso_licitacion')->filter()->unique()->values())
            ->with('modalidadRel:id,nombre')
            ->get()
            ->keyBy('id');

        foreach ($filas as $fila) {
            $proceso = $fila->proceso_licitacion !== null ? $procesos->get($fila->proceso_licitacion) : null;
            $fila->proceso_id = $this->entero($fila->proceso_licitacion);
            $fila->proceso = $proceso ? $this->resumenProceso($proceso) : null;
        }
    }

    /** @return array{id: int, codigo_proceso: ?string, objeto: ?string, estado: string, modalidad: ?array, plataforma: ?string, url_proceso: ?string} */
    private function resumenProceso(ProcesoLicitacion $proceso): array
    {
        $modalidad = $proceso->modalidadRel;

        return [
            'id' => $proceso->id,
            'codigo_proceso' => $proceso->codigo_proceso,
            'objeto' => $proceso->objeto,
            'estado' => $proceso->estado,
            'modalidad' => $modalidad ? ['id' => $modalidad->id, 'nombre' => $modalidad->nombre] : null,
            'plataforma' => $proceso->plataforma,
            'url_proceso' => $proceso->url_proceso,
        ];
    }

    /** @return list<string> */
    private function avisos(Contrato $contrato): array
    {
        $proceso = $contrato->proceso_licitacion !== null ? ProcesoLicitacion::find($contrato->proceso_licitacion) : null;
        if ($proceso?->monto === null) {
            return [];
        }

        $suma = BigDecimal::zero();
        $proceso->contratos()
            ->where(fn ($q) => $q->whereNull('estado')->orWhere('estado', '<>', Contrato::ANULADO))
            ->pluck('valor_inicial')
            ->each(function ($valor) use (&$suma) {
                $suma = $suma->plus(BigDecimal::of((string) ($valor ?? 0)));
            });
        $monto = BigDecimal::of($proceso->monto);

        return $suma->isGreaterThan($monto)
            ? ["Los contratos del proceso suman {$this->pesos($suma)}, más que su valor de {$this->pesos($monto)}."]
            : [];
    }

    /** $1.234.567,89 */
    private function pesos(BigDecimal $valor): string
    {
        [$entero, $decimales] = explode('.', (string) $valor->toScale(2, RoundingMode::HALF_UP));

        return '$' . number_format((int) $entero, 0, ',', '.') . ',' . $decimales;
    }

    private function bloquear(Contrato $contrato): Contrato
    {
        return Contrato::query()->whereKey($contrato->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Valores del pedido en el orden de CAMPOS, como quedarían guardados: textos recortados y
     * vacíos como null, enteros como enteros, avances redondeados, el valor con dos decimales.
     */
    private function valores(array $datos): array
    {
        $valores = array_intersect_key(array_replace(self::CAMPOS, $datos), $datos, self::CAMPOS);
        if (array_key_exists('anticipo', $valores)) {
            $valores['anticipo'] = filter_var($valores['anticipo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        foreach ($valores as $campo => $valor) {
            if ($valor === null) {
                continue;
            }
            $texto = trim((string) $valor);
            $valores[$campo] = match (true) {
                in_array($campo, self::TEXTOS, true) => $texto !== '' ? $texto : null,
                in_array($campo, self::ENTEROS, true) => (int) $valor,
                in_array($campo, self::AVANCES, true) => (int) round((float) $valor),
                $campo === 'valor_inicial' => (string) BigDecimal::of((string) $valor)->toScale(2),
                default => $valor,
            };
        }

        $faltan = array_filter(
            self::OBLIGATORIOS,
            fn (string $campo) => array_key_exists($campo, $valores) && $valores[$campo] === null,
            ARRAY_FILTER_USE_KEY
        );
        if ($faltan !== []) {
            throw ValidationException::withMessages($faltan);
        }

        return $valores;
    }

    /** El tipo del pedido existe y está activo, o es el que el contrato ya tiene. */
    private function exigirTipo(array $valores, ?Contrato $actual): void
    {
        $id = $valores['tipo_contrato_id'] ?? null;
        if ($id === null) {
            return;
        }
        $tipo = TipoProceso::query()->find($id, ['id', 'activo']);
        if ($tipo === null) {
            throw ValidationException::withMessages(['tipo_contrato_id' => 'Elige un tipo de contrato de la lista.']);
        }
        if (! $tipo->activo && $id !== $actual?->tipo_contrato_id) {
            throw ValidationException::withMessages(['tipo_contrato_id' => 'El tipo de contrato elegido está inactivo: elige otro.']);
        }
    }

    /**
     * El proyecto existe y el proceso es suyo (R3) y admite contratos si es un vínculo nuevo. Sin
     * proyecto, el contrato toma el del proceso. El proceso queda bloqueado: así no se elimina ni
     * se pierde de vista mientras se le vincula el contrato.
     *
     * @return array{0: array, 1: ?ProcesoLicitacion} los valores (con el proyecto que corresponda) y el proceso
     */
    private function exigirVinculos(array $valores, ?Contrato $actual): array
    {
        $proyectoId = array_key_exists('proyecto', $valores) ? $valores['proyecto'] : $this->entero($actual?->proyecto);
        $procesoActual = $this->entero($actual?->proceso_licitacion);
        $procesoId = array_key_exists('proceso_id', $valores) ? $valores['proceso_id'] : $procesoActual;

        if ($proyectoId !== null && ! Proyecto::query()->whereKey($proyectoId)->exists()) {
            throw ValidationException::withMessages(['proyecto' => 'Elige un proyecto de la lista.']);
        }
        if ($procesoId === null) {
            return [$valores, null];
        }

        $proceso = ProcesoLicitacion::query()->whereKey($procesoId)->lockForUpdate()->first();
        if ($proceso === null) {
            throw ValidationException::withMessages(['proceso_id' => 'Elige un proceso de la lista.']);
        }
        if ($procesoId !== $procesoActual && ! in_array($proceso->estado, ProcesoLicitacion::ADMITEN_CONTRATOS, true)) {
            throw ValidationException::withMessages(['proceso_id' => 'Solo se puede vincular un contrato a un proceso adjudicado o celebrado.']);
        }
        if ($proyectoId === null) {
            $valores['proyecto'] = $proceso->proyecto;
        } elseif ($proyectoId !== $proceso->proyecto) {
            throw ValidationException::withMessages(['proceso_id' => 'El proceso elegido es de otro proyecto.']);
        }

        return [$valores, $proceso];
    }

    /** La fecha fin inicial no es anterior a la de inicio; `$valores` trae las dos, como quedarían. */
    private function exigirFechas(array $valores): void
    {
        $inicio = $valores['fecha_inicio'] ?? null;
        $fin = $valores['fecha_fin_inicial'] ?? null;
        if ($inicio !== null && $fin !== null && $fin < $inicio) {
            throw ValidationException::withMessages(['fecha_fin_inicial' => self::FIN_ANTERIOR]);
        }
    }

    /** @param list<array{descripcion: string, nombreArchivo: string, rutaArchivo: string, fecha: string}> $anexos */
    private function guardarAnexos(Contrato $contrato, array $anexos): void
    {
        foreach ($anexos as $anexo) {
            $contrato->anexos()->create([
                'descripcion' => $anexo['descripcion'],
                'nombre_archivo' => $anexo['nombreArchivo'],
                'ruta_archivo' => $anexo['rutaArchivo'],
                'fecha' => $anexo['fecha'],
            ]);
        }
    }

    /** @return list<array{descripcion: ?string, nombre_archivo: ?string, ruta_archivo: ?string, fecha: ?string}> los anexos guardados, en orden */
    private function anexosDe(Contrato $contrato): array
    {
        return $contrato->anexos()->orderBy('id')->get()->map(fn (AnexoContrato $a) => [
            'descripcion' => $a->descripcion,
            'nombre_archivo' => $a->nombre_archivo,
            'ruta_archivo' => $a->ruta_archivo,
            'fecha' => $a->fecha?->format('Y-m-d'),
        ])->all();
    }

    /** Borra el archivo de un anexo si existe y está dentro de public/anexos_contratos. */
    private function borrarArchivo(?string $ruta): void
    {
        $carpeta = realpath(public_path(self::CARPETA_ANEXOS));
        $archivo = $ruta !== null ? realpath(public_path($ruta)) : false;
        if ($carpeta !== false && $archivo !== false && is_file($archivo)
            && str_starts_with($archivo, $carpeta . DIRECTORY_SEPARATOR)) {
            unlink($archivo);
        }
    }

    /** @return array<string, mixed> los campos de la API con los valores guardados, normalizados como valores() */
    private function valoresDe(Contrato $contrato): array
    {
        $valores = [];
        foreach (self::CAMPOS as $campo => $columna) {
            $valor = $contrato->{$columna};
            $valores[$campo] = match (true) {
                $valor === null => null,
                $valor instanceof DateTimeInterface => $valor->format('Y-m-d'),
                in_array($campo, self::ENTEROS, true), in_array($campo, self::AVANCES, true) => (int) $valor,
                $campo === 'valor_inicial' => (string) BigDecimal::of((string) $valor)->toScale(2),
                $campo === 'anticipo' => (bool) $valor,
                default => $valor,
            };
        }

        return $valores;
    }

    private function columnas(array $valores): array
    {
        $columnas = [];
        foreach ($valores as $campo => $valor) {
            $columnas[self::CAMPOS[$campo]] = $valor;
        }

        return $columnas;
    }

    private function entero(mixed $valor): ?int
    {
        return $valor === null ? null : (int) $valor;
    }
}
```

Crear `app/Services/Contratacion/ControlDeVersion.php`:

```php
<?php

namespace App\Services\Contratacion;

use App\Exceptions\EdicionDesactualizadaException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Control de versión de una fila editada desde un formulario: el pedido trae `actualizado`, el
 * updated_at que tenía la fila cuando se abrió el formulario. Si ya no es el de la fila, otra
 * persona la cambió entretanto y se rechaza (409) en vez de deshacer en silencio lo suyo.
 */
trait ControlDeVersion
{
    /**
     * `$actualizado` cae en el mismo segundo que updated_at, o ambos son null (una fila anterior
     * a las fechas). Se llama con la fila ya bloqueada.
     */
    private function exigirVersion(Model $fila, ?string $actualizado, string $mensaje): void
    {
        $esperado = $actualizado === null ? null : Carbon::parse($actualizado)->getTimestamp();
        if ($esperado !== $fila->updated_at?->getTimestamp()) {
            throw new EdicionDesactualizadaException($mensaje);
        }
    }

    /**
     * updated_at se guarda sin fracciones de segundo: si un cambio cae en el mismo segundo que el
     * anterior, se adelanta uno para que `actualizado` cambie siempre y el control de versión no
     * deje pasar un formulario viejo.
     */
    private function adelantarActualizado(Model $fila): void
    {
        $ahora = Carbon::now();
        $siguiente = $fila->updated_at?->copy()->startOfSecond()->addSecond();
        $fila->updated_at = $siguiente !== null && $siguiente->greaterThan($ahora) ? $siguiente : $ahora;
    }
}
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (1 de 5):

```php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Services\Financiacion\FuentesProyecto;
use App\Services\Viabilizacion\ExpedienteService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
```

por:

```php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Proyecto;
use App\Services\Contratacion\ContratosService;
use App\Services\Financiacion\FuentesProyecto;
use App\Services\Viabilizacion\ExpedienteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (2 de 5):

```php
            $proyecto->totalPresupuesto = $totalPresupuesto;

            $contratos = DB::table('contratos')
                ->select('id', 'n_contrato', 'objeto', 'valor', 'estado', 'contratante', 'contratista', 'fecha_inicio', 'fecha_fin', 'interventoria', 'avance_financiero', 'avance_fisico', 'anticipo', 'porcentaje_anticipo')
                ->where('proyecto', $proyecto->id)
                ->get();
                
```

por:

```php
            $proyecto->totalPresupuesto = $totalPresupuesto;

            $contratos = DB::table('contratos')
                ->select(
                    'id', 'n_contrato', 'objeto', 'valor', 'estado', 'contratante', 'contratista', 'fecha_inicio', 'fecha_fin', 'interventoria', 'avance_financiero', 'avance_fisico', 'anticipo', 'porcentaje_anticipo',
                    // Para que editar desde aquí no borre datos: los iniciales, la versión y lo nuevo de la etapa 3A.
                    'proyecto', 'proceso_licitacion', 'valor_inicial', 'fecha_fin_inicial',
                    'tipo_contrato_id', 'contratista_identificacion', 'fecha_suscripcion', 'supervisor', 'numero_rp', 'fecha_rp',
                    'fecha_acta_inicio', 'estado_original', 'usuario_creo_id', 'created_at', 'updated_at',
                    'contratante_nit', 'secop_id_contrato', 'secop_proceso', 'url_secop', 'secop_consultado_at'
                )
                ->where('proyecto', $proyecto->id)
                ->get();
                
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (3 de 5):

```php
                ->get();
        }

        return response()->json($proyectos);
    }
```

por:

```php
                ->get();
        }

        // El proceso de cada contrato, resumido: una sola consulta para todos los proyectos.
        app(ContratosService::class)->agregarProcesos($proyectos->flatMap(fn ($proyecto) => $proyecto->contratos));

        return response()->json($proyectos);
    }
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (4 de 5):

```php
        return response()->json($respuesta);
    }

    public function guardarContrato(Request $request)
    {
        $data = $request->all();
        $formContrato = $data['formContrato'] ?? $data;
        $anexos = $data['anexos'] ?? [];

        $nullableDate = fn ($value) => ($value === '' || $value === null) ? null : $value;
        $nullableInt = fn ($value) => ($value === '' || $value === null) ? null : (int) $value;
        $nullableString = fn ($value) => ($value === '' || $value === null) ? null : $value;

        $contratoPayload = [
            'n_contrato' => $formContrato['n_contrato'],
            'objeto' => $formContrato['objeto'],
            'contratante' => $nullableString($formContrato['contratante'] ?? null),
            'contratista' => $nullableString($formContrato['contratista'] ?? null),
            'valor' => $formContrato['valor'],
            'fecha_inicio' => $nullableDate($formContrato['fecha_inicio'] ?? null),
            'fecha_fin' => $nullableDate($formContrato['fecha_fin'] ?? null),
            'interventoria' => $nullableString($formContrato['interventoria'] ?? null),
            'avance_financiero' => $nullableInt($formContrato['avance_financiero'] ?? null),
            'avance_fisico' => $nullableInt($formContrato['avance_fisico'] ?? null),
            'estado' => $formContrato['estado'],
            'anticipo' => (bool) ($formContrato['anticipo'] ?? false),
            'porcentaje_anticipo' => $nullableInt($formContrato['porcentaje_anticipo'] ?? null),
        ];
        
        DB::beginTransaction();
        try {
            $contratoId = null;
            
            if (isset($formContrato['id']) && $formContrato['id']) {
                // Actualizar contrato existente
                DB::table('contratos')->where('id', $formContrato['id'])->update($contratoPayload);
                $contratoId = $formContrato['id'];
                
                // Eliminar anexos existentes del contrato
                DB::table('anexos_contratos')->where('contrato_id', $contratoId)->delete();
            } else {
                // Insertar nuevo contrato: fijar la base inmutable para adiciones/prórrogas.
                $contratoId = DB::table('contratos')->insertGetId(array_merge($contratoPayload, [
                    'proyecto' => $formContrato['proyecto'] ?? null,
                    'valor_inicial' => $contratoPayload['valor'],
                    'fecha_fin_inicial' => $contratoPayload['fecha_fin'],
                ]));
            }
            
            // Guardar anexos
            if (!empty($anexos) && $contratoId) {
                foreach ($anexos as $anexo) {
                    DB::table('anexos_contratos')->insert([
                        'contrato_id' => $contratoId,
                        'descripcion' => $anexo['descripcion'],
                        'nombre_archivo' => $anexo['nombreArchivo'],
                        'ruta_archivo' => $anexo['rutaArchivo'],
                        'fecha' => $anexo['fecha']
                    ]);
                }
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
        DB::commit();
        return response()->json(['success' => 'Contrato guardado correctamente']);
    }

    public function eliminarProyecto(Request $request)
    {
        $proyecto = $request->all();
```

por:

```php
        return response()->json($respuesta);
    }

    public function eliminarProyecto(Request $request)
    {
        $proyecto = $request->all();
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (5 de 5):

```php
        ]);
    }

    /**
     * Recalcula valor y fecha_fin vigentes del contrato desde valor_inicial /
     * fecha_fin_inicial + suma del historial de modificaciones. Devuelve el
     * resumen (valores vigentes, % adicionado y bandera de límite 50%).
     */
    private function recalcularContrato($contratoId): array
    {
        $contrato = DB::table('contratos')->where('id', $contratoId)->first();

        $valorInicial = (float) ($contrato->valor_inicial ?? $contrato->valor ?? 0);
        $fechaFinInicial = $contrato->fecha_fin_inicial ?? $contrato->fecha_fin;

        $sumaAdiciones = (float) DB::table('modificaciones_contrato')
            ->where('contrato_id', $contratoId)
            ->sum('valor_adicion');

        $sumaDias = (int) DB::table('modificaciones_contrato')
            ->where('contrato_id', $contratoId)
            ->sum('dias_prorroga');

        $valorVigente = $valorInicial + $sumaAdiciones;
        $fechaFinVigente = $fechaFinInicial
            ? Carbon::parse($fechaFinInicial)->addDays($sumaDias)->toDateString()
            : null;

        DB::table('contratos')->where('id', $contratoId)->update([
            'valor' => $valorVigente,
            'fecha_fin' => $fechaFinVigente,
        ]);

        $porcentaje = $valorInicial > 0 ? ($sumaAdiciones / $valorInicial) * 100 : 0.0;

        return [
            'valor_inicial' => $valorInicial,
            'valor_vigente' => $valorVigente,
            'fecha_fin_inicial' => $fechaFinInicial,
            'fecha_fin_vigente' => $fechaFinVigente,
            'porcentaje_adicionado' => round($porcentaje, 2),
            'supera_limite' => $porcentaje > 50,
        ];
    }

    public function listarContratos(Request $request)
    {
        $proyecto = $request->all();
        $contratos = DB::table('contratos')->where('proyecto', $proyecto['proyecto'])->get();

        foreach ($contratos as $contrato) {
            $anexos = DB::table('anexos_contratos')->where('contrato_id', $contrato->id)->get();
```

por:

```php
        ]);
    }

    /** Recalcula el valor y la fecha fin vigentes del contrato: ContratosService::recalcular(). */
    private function recalcularContrato($contratoId): array
    {
        return app(ContratosService::class)->recalcular(Contrato::findOrFail($contratoId));
    }

    public function listarContratos(Request $request)
    {
        $proyecto = $request->all();
        $contratos = DB::table('contratos')->where('proyecto', $proyecto['proyecto'])->get();
        app(ContratosService::class)->agregarProcesos($contratos);

        foreach ($contratos as $contrato) {
            $anexos = DB::table('anexos_contratos')->where('contrato_id', $contrato->id)->get();
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (1 de 4):

```php

namespace App\Services\Contratacion;

use App\Exceptions\EdicionDesactualizadaException;
use App\Exceptions\ReglaNegocioException;
use App\Models\Contrato;
use App\Models\Entidad;
```

por:

```php

namespace App\Services\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\Contrato;
use App\Models\Entidad;
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (2 de 4):

```php
use Brick\Math\BigDecimal;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
```

por:

```php
use Brick\Math\BigDecimal;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (3 de 4):

```php
 */
final class ProcesosContratacion
{
    /** Campo de la API => columna del proceso. */
    private const CAMPOS = [
        'objeto' => 'objeto',
```

por:

```php
 */
final class ProcesosContratacion
{
    use ControlDeVersion;

    /** Campo de la API => columna del proceso. */
    private const CAMPOS = [
        'objeto' => 'objeto',
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (4 de 4):

```php
        return ProcesoLicitacion::query()->whereKey($proceso->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * La fila bloqueada, si sigue siendo la versión que tenía el formulario: `$actualizado` cae en
     * el mismo segundo que updated_at, o ambos son null (un proceso anterior a las fechas).
     */
    private function bloquearVigente(ProcesoLicitacion $proceso, ?string $actualizado): ProcesoLicitacion
    {
        $actual = $this->bloquear($proceso);
        $esperado = $actualizado === null ? null : Carbon::parse($actualizado)->getTimestamp();
        if ($esperado !== $actual->updated_at?->getTimestamp()) {
            throw new EdicionDesactualizadaException(self::DESACTUALIZADO);
        }

        return $actual;
    }

    /**
     * updated_at se guarda sin fracciones de segundo: si un cambio cae en el mismo segundo que el
     * anterior, se adelanta uno para que `actualizado` cambie siempre y el control de versión no
     * deje pasar un formulario viejo.
     */
    private function adelantarActualizado(ProcesoLicitacion $proceso): void
    {
        $ahora = Carbon::now();
        $siguiente = $proceso->updated_at?->copy()->startOfSecond()->addSecond();
        $proceso->updated_at = $siguiente !== null && $siguiente->greaterThan($ahora) ? $siguiente : $ahora;
    }

    /**
     * Valores del pedido en el orden de CAMPOS: textos recortados y vacíos como null, y el valor
     * con dos decimales, como lo devuelve el modelo, para comparar con lo guardado.
```

por:

```php
        return ProcesoLicitacion::query()->whereKey($proceso->id)->lockForUpdate()->firstOrFail();
    }

    /** La fila bloqueada, si sigue siendo la versión que tenía el formulario (ControlDeVersion). */
    private function bloquearVigente(ProcesoLicitacion $proceso, ?string $actualizado): ProcesoLicitacion
    {
        $actual = $this->bloquear($proceso);
        $this->exigirVersion($actual, $actualizado, self::DESACTUALIZADO);

        return $actual;
    }

    /**
     * Valores del pedido en el orden de CAMPOS: textos recortados y vacíos como null, y el valor
     * con dos decimales, como lo devuelve el modelo, para comparar con lo guardado.
```

En `app/Services/Viabilizacion/HistorialChecklist.php`, reemplazar (1 de 3):

```php
namespace App\Services\Viabilizacion;

use App\Models\ChecklistHistorial;
use App\Models\ProcesoLicitacion;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
```

por:

```php
namespace App\Services\Viabilizacion;

use App\Models\ChecklistHistorial;
use App\Models\Contrato;
use App\Models\ProcesoLicitacion;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
```

En `app/Services/Viabilizacion/HistorialChecklist.php`, reemplazar (2 de 3):

```php
    public const REQUISITO = 'requisito';
    public const EXPEDIENTE = 'expediente';
    public const PROCESO = 'proceso';

    /** Entradas por página del historial del expediente. */
    public const POR_PAGINA = 50;
```

por:

```php
    public const REQUISITO = 'requisito';
    public const EXPEDIENTE = 'expediente';
    public const PROCESO = 'proceso';
    public const CONTRATO = 'contrato';

    /** Entradas por página del historial del expediente. */
    public const POR_PAGINA = 50;
```

En `app/Services/Viabilizacion/HistorialChecklist.php`, reemplazar (3 de 3):

```php
        $this->registrar(self::PROCESO, $proceso->id, null, $evento, $antes, $despues, $usuario);
    }

    /** Historial de una fila, del más reciente al más antiguo. */
    public function deFila(ProyectoRequisito $fila): array
    {
```

por:

```php
        $this->registrar(self::PROCESO, $proceso->id, null, $evento, $antes, $despues, $usuario);
    }

    /** Un contrato tampoco pertenece a un expediente: su entrada va sin expediente_id. */
    public function registrarContrato(Contrato $contrato, string $evento, array $antes, array $despues, ?User $usuario): void
    {
        $this->registrar(self::CONTRATO, $contrato->id, null, $evento, $antes, $despues, $usuario);
    }

    /** Historial de una fila, del más reciente al más antiguo. */
    public function deFila(ProyectoRequisito $fila): array
    {
```

En `routes/api.php`, reemplazar (1 de 2):

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EtapaContratacionController;
use App\Http\Controllers\FuenteFinanciacionController;
use App\Http\Controllers\ModalidadController;
```

por:

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\EtapaContratacionController;
use App\Http\Controllers\FuenteFinanciacionController;
use App\Http\Controllers\ModalidadController;
```

En `routes/api.php`, reemplazar (2 de 2):

```php
        Route::post('/eliminarProyecto', [ProyectoController::class, 'eliminarProyecto']);
        Route::post('/guardarEvento', [ProyectoController::class, 'guardarEvento']);
        Route::post('/eliminarEvento', [ProyectoController::class, 'eliminarEvento']);
        Route::post('/guardarContrato', [ProyectoController::class, 'guardarContrato']);
        Route::post('/subirAnexo', [ProyectoController::class, 'subirAnexo']);
        Route::post('/subirActa', [ProyectoController::class, 'subirActa']);
        Route::post('/eliminarAnexo', [ProyectoController::class, 'eliminarAnexo']);
        Route::post('/eliminarContrato', [ProyectoController::class, 'eliminarContrato']);
        Route::post('/guardarActaFinanciera', [ProyectoController::class, 'guardarActaFinanciera']);
        Route::post('/eliminarActaFinanciera', [ProyectoController::class, 'eliminarActaFinanciera']);
        Route::post('/guardarActividad', [ProyectoController::class, 'guardarActividad']);
```

por:

```php
        Route::post('/eliminarProyecto', [ProyectoController::class, 'eliminarProyecto']);
        Route::post('/guardarEvento', [ProyectoController::class, 'guardarEvento']);
        Route::post('/eliminarEvento', [ProyectoController::class, 'eliminarEvento']);
        Route::post('/guardarContrato', [ContratoController::class, 'guardar']);
        Route::post('/subirAnexo', [ProyectoController::class, 'subirAnexo']);
        Route::post('/subirActa', [ProyectoController::class, 'subirActa']);
        Route::post('/eliminarAnexo', [ProyectoController::class, 'eliminarAnexo']);
        Route::post('/eliminarContrato', [ContratoController::class, 'eliminar']);
        Route::post('/guardarActaFinanciera', [ProyectoController::class, 'guardarActaFinanciera']);
        Route::post('/eliminarActaFinanciera', [ProyectoController::class, 'eliminarActaFinanciera']);
        Route::post('/guardarActividad', [ProyectoController::class, 'guardarActividad']);
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/ContratosApiTest.php tests/Feature/Schema/ModificacionesContratoTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1008 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Http/Controllers/ContratoController.php app/Services/Contratacion/ContratoGuardado.php app/Services/Contratacion/ContratosService.php app/Services/Contratacion/ControlDeVersion.php tests/Feature/Contratacion/ContratosApiTest.php app/Http/Controllers/ProyectoController.php app/Services/Contratacion/ProcesosContratacion.php app/Services/Viabilizacion/HistorialChecklist.php routes/api.php tests/Feature/Schema/ModificacionesContratoTest.php
git commit -m "feat: contracts service with validation, optional project and protected delete"
```

---

### Tarea 6: Formulario de contratos

Estados, campos nuevos, proceso con precarga, valores iniciales y vigentes, errores por campo y borrado protegido en la pantalla actual; los anulados fuera de los indicadores.

**Files:**
- Create: `resources/js/utils/contratos.js`
- Test (create): `resources/js/utils/contratos.test.js`
- Modify: `resources/css/Parametros.css`
- Modify: `resources/js/components/ContratosPanel.jsx`
- Modify: `resources/js/components/ModalProyecto.jsx`
- Modify: `resources/js/components/Parametros.jsx`
- Modify: `resources/js/utils/avanceContratos.js`
- Test: `resources/js/utils/avanceContratos.test.js`
- Modify: `resources/js/utils/cadenaPresupuestal.js`
- Test: `resources/js/utils/cadenaPresupuestal.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `resources/js/utils/contratos.test.js`:

```js
import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import {
    CAMPOS_CONTRATO,
    CONTRATO_VACIO,
    erroresGuardarContrato,
    primerCampoConError,
    ESTADOS_CONTRATO,
    aplicarPrecarga,
    contratoVacio,
    datosContrato,
    datosDelProceso,
    etiquetaEstadoContrato,
    formularioContrato,
    notaProceso,
    opcionesProceso,
    opcionesTipoContrato,
    procesoElegido,
    validarContrato,
    valorVigente,
    vigentesDistintos,
} from './contratos';

// Un contrato como lo devuelve presentar (POST /guardarContrato).
const presentado = {
    id: 7,
    proyecto: 3,
    n_contrato: 'CO-001',
    objeto: 'Construcción del puente',
    contratante: 'Alcaldía de Becerril',
    contratante_nit: '800.123.456-1',
    contratista: 'Constructora S.A.S.',
    contratista_identificacion: '900.555.111-2',
    tipo_contrato_id: 2,
    tipo_contrato: { id: 2, nombre: 'Obra', activo: true },
    valor_inicial: '1500000.50',
    valor: '1500000.50',
    fecha_inicio: '2026-01-10',
    fecha_fin_inicial: '2026-06-30',
    fecha_fin: '2026-06-30',
    fecha_suscripcion: '2026-01-05',
    numero_rp: 'RP-77',
    fecha_rp: '2026-01-06',
    fecha_acta_inicio: '2026-01-10',
    supervisor: 'Ana Pérez',
    interventoria: 'Interventora Ltda.',
    estado: 'en_ejecucion',
    estado_original: 'vigente',
    anticipo: true,
    porcentaje_anticipo: 30,
    avance_fisico: 40,
    avance_financiero: 25,
    proceso_id: 5,
    proceso: { id: 5, codigo_proceso: 'LP-01-2026', objeto: 'Puente', estado: 'celebrado', modalidad: { id: 1, nombre: 'Licitación pública' }, plataforma: 'secop_ii', url_proceso: 'https://community.secop.gov.co/p/1' },
    anexos: [],
    updated_at: '2026-11-18 09:00:00',
};

// El mismo contrato como lo devuelven GET /proyectos y GET /listarContratos: columnas crudas.
const listado = {
    ...presentado,
    tipo_contrato: undefined,
    proyecto: '3',
    proceso_licitacion: 5,
    tipo_contrato_id: '2',
    anticipo: 1,
    porcentaje_anticipo: '30',
    fecha_inicio: '2026-01-10 00:00:00',
};

describe('estados del contrato', () => {
    it('coinciden con Contrato::ESTADOS y en el mismo orden', () => {
        // Ruta relativa a este archivo, no al directorio desde donde se lance Vitest.
        const modelo = readFileSync(new URL('../../../app/Models/Contrato.php', import.meta.url), 'utf8');
        const valor = (nombre) => modelo.match(new RegExp(`const ${nombre} = '([a-z_]+)'`))[1];
        const lista = modelo.match(/const ESTADOS = \[([^\]]*)\]/)[1]
            .split(',').map((x) => x.trim()).filter(Boolean).map((x) => valor(x.replace('self::', '')));
        expect(ESTADOS_CONTRATO.map(([v]) => v)).toEqual(lista);
    });

    it('tienen las etiquetas acordadas', () => {
        expect(ESTADOS_CONTRATO.map(([, e]) => e)).toEqual(['Celebrado', 'En ejecución', 'Suspendido', 'Terminado', 'Liquidado', 'Anulado']);
    });

    it('la etiqueta de un estado conocido, el texto crudo de uno desconocido y una raya sin estado', () => {
        expect(etiquetaEstadoContrato('en_ejecucion')).toBe('En ejecución');
        expect(etiquetaEstadoContrato('anulado')).toBe('Anulado');
        expect(etiquetaEstadoContrato('Vigente')).toBe('Vigente');
        expect(etiquetaEstadoContrato('')).toBe('—');
        expect(etiquetaEstadoContrato(null)).toBe('—');
    });
});

describe('formulario vacío', () => {
    it('tiene todos los campos de la API vacíos y sin anticipo', () => {
        expect(CAMPOS_CONTRATO).toEqual(Object.keys(CONTRATO_VACIO));
        const vacio = contratoVacio();
        CAMPOS_CONTRATO.filter((c) => c !== 'anticipo').forEach((c) => expect(vacio[c]).toBe(''));
        expect(vacio.anticipo).toBe(false);
        expect(vacio.id).toBeUndefined();
    });

    it('lleva el proyecto como texto, para el select', () => {
        expect(contratoVacio(3).proyecto).toBe('3');
        expect(contratoVacio(null).proyecto).toBe('');
    });

    it('cada llamada devuelve una copia', () => {
        const a = contratoVacio();
        a.objeto = 'x';
        expect(contratoVacio().objeto).toBe('');
        expect(CONTRATO_VACIO.objeto).toBe('');
    });
});

describe('formularioContrato', () => {
    it('sin contrato es el formulario vacío', () => {
        expect(formularioContrato(null)).toEqual(contratoVacio());
    });

    it('desde presentar trae todos los campos, con el valor inicial agrupado', () => {
        expect(formularioContrato(presentado)).toEqual({
            id: 7,
            proyecto: '3',
            proceso_id: '5',
            tipo_contrato_id: '2',
            n_contrato: 'CO-001',
            objeto: 'Construcción del puente',
            contratante: 'Alcaldía de Becerril',
            contratante_nit: '800.123.456-1',
            contratista: 'Constructora S.A.S.',
            contratista_identificacion: '900.555.111-2',
            supervisor: 'Ana Pérez',
            interventoria: 'Interventora Ltda.',
            numero_rp: 'RP-77',
            valor_inicial: '1.500.000,50',
            fecha_inicio: '2026-01-10',
            fecha_fin_inicial: '2026-06-30',
            fecha_suscripcion: '2026-01-05',
            fecha_rp: '2026-01-06',
            fecha_acta_inicio: '2026-01-10',
            estado: 'en_ejecucion',
            anticipo: true,
            porcentaje_anticipo: '30',
            avance_fisico: 40,
            avance_financiero: 25,
        });
    });

    it('desde el listado da lo mismo que desde presentar', () => {
        expect(formularioContrato(listado)).toEqual(formularioContrato(presentado));
    });

    it('un contrato sin valor ni fecha fin iniciales toma los vigentes', () => {
        const f = formularioContrato({ ...presentado, valor_inicial: null, valor: '2000000.00', fecha_fin_inicial: null, fecha_fin: '2026-07-31' });
        expect(f.valor_inicial).toBe('2.000.000');
        expect(f.fecha_fin_inicial).toBe('2026-07-31');
    });

    it('conserva los avances en cero y deja vacíos los nulos', () => {
        const f = formularioContrato({ ...presentado, avance_fisico: 0, avance_financiero: null, porcentaje_anticipo: null, anticipo: 0 });
        expect(f.avance_fisico).toBe(0);
        expect(f.avance_financiero).toBe('');
        expect(f.porcentaje_anticipo).toBe('');
        expect(f.anticipo).toBe(false);
    });

    it('toma el proceso y el tipo de los objetos si faltan los ids', () => {
        const f = formularioContrato({ id: 1, proceso: { id: 9 }, tipo_contrato: { id: 4, nombre: 'Suministro' } });
        expect(f.proceso_id).toBe('9');
        expect(f.tipo_contrato_id).toBe('4');
    });

    it('no pierde ningún campo: formulario y vuelta dan lo que tenía el contrato', () => {
        const datos = datosContrato(formularioContrato(presentado), presentado);
        CAMPOS_CONTRATO.forEach((campo) => expect(datos[campo], campo).toEqual(presentado[campo]));
    });
});

describe('datosContrato', () => {
    it('al crear: sin id ni actualizado; vacíos como null; ids como número', () => {
        const datos = datosContrato({ ...contratoVacio(3), n_contrato: ' CO-9 ', objeto: 'Obra', valor_inicial: '1.000', estado: 'celebrado' });
        expect(datos).toEqual({
            proyecto: 3,
            proceso_id: null,
            tipo_contrato_id: null,
            n_contrato: 'CO-9',
            objeto: 'Obra',
            contratante: null,
            contratante_nit: null,
            contratista: null,
            contratista_identificacion: null,
            supervisor: null,
            interventoria: null,
            numero_rp: null,
            valor_inicial: '1000',
            fecha_inicio: null,
            fecha_fin_inicial: null,
            fecha_suscripcion: null,
            fecha_rp: null,
            fecha_acta_inicio: null,
            estado: 'celebrado',
            anticipo: false,
            porcentaje_anticipo: null,
            avance_fisico: null,
            avance_financiero: null,
        });
    });

    it('al editar: el id y el updated_at del contrato que se abrió', () => {
        const datos = datosContrato(formularioContrato(presentado), presentado);
        expect(datos.id).toBe(7);
        expect(datos.actualizado).toBe('2026-11-18 09:00:00');
    });

    it('un contrato antiguo, sin updated_at, envía actualizado nulo', () => {
        const antiguo = { ...presentado, updated_at: null };
        expect(datosContrato(formularioContrato(antiguo), antiguo).actualizado).toBeNull();
    });

    it('limita los avances a 0..100', () => {
        const f = { ...contratoVacio(), avance_fisico: 120.4, avance_financiero: -3 };
        expect(datosContrato(f).avance_fisico).toBe(100);
        expect(datosContrato(f).avance_financiero).toBe(0);
        expect(datosContrato({ ...f, avance_fisico: '45.67' }).avance_fisico).toBe(45.67);
        expect(datosContrato({ ...f, avance_fisico: 'abc' }).avance_fisico).toBeNull();
    });

    it('sin anticipo, el porcentaje va nulo', () => {
        expect(datosContrato({ ...contratoVacio(), anticipo: false, porcentaje_anticipo: '20' }).porcentaje_anticipo).toBeNull();
        expect(datosContrato({ ...contratoVacio(), anticipo: true, porcentaje_anticipo: '20' }).porcentaje_anticipo).toBe(20);
    });

    it('envía el proceso elegido como número y "sin proceso" como null', () => {
        expect(datosContrato({ ...contratoVacio(), proceso_id: '5' }).proceso_id).toBe(5);
        expect(datosContrato({ ...contratoVacio(), proceso_id: '' }).proceso_id).toBeNull();
    });

    it('no envía el valor ni la fecha fin vigentes', () => {
        const datos = datosContrato({ ...formularioContrato(presentado), valor: '1', fecha_fin: '2030-01-01' }, presentado);
        expect(datos).not.toHaveProperty('valor');
        expect(datos).not.toHaveProperty('fecha_fin');
    });
});

describe('validarContrato', () => {
    const valido = { ...contratoVacio(3), n_contrato: 'CO-1', objeto: 'Obra', valor_inicial: '1.000', estado: 'celebrado' };

    it('un formulario con lo obligatorio no tiene errores', () => {
        expect(validarContrato(valido)).toEqual({});
    });

    it('pide número, objeto, valor inicial y estado con los mensajes del servidor', () => {
        expect(validarContrato(contratoVacio())).toEqual({
            n_contrato: 'Escribe el número del contrato.',
            objeto: 'Escribe el objeto del contrato.',
            valor_inicial: 'Escribe el valor inicial del contrato.',
            estado: 'Elige el estado del contrato.',
        });
    });

    it('acepta un valor inicial de cero', () => {
        expect(validarContrato({ ...valido, valor_inicial: '0' })).toEqual({});
    });

    it('rechaza un valor de más de 13 cifras enteras', () => {
        expect(validarContrato({ ...valido, valor_inicial: '12345678901234' }).valor_inicial)
            .toBe('El valor inicial admite hasta 13 cifras enteras y 2 decimales.');
    });

    it('rechaza una fecha fin inicial anterior a la de inicio', () => {
        expect(validarContrato({ ...valido, fecha_inicio: '2026-02-01', fecha_fin_inicial: '2026-01-31' }).fecha_fin_inicial)
            .toBe('La fecha de fin no puede ser anterior a la de inicio.');
        expect(validarContrato({ ...valido, fecha_inicio: '2026-02-01', fecha_fin_inicial: '2026-02-01' })).toEqual({});
    });

    it('respeta los largos máximos', () => {
        const errores = validarContrato({ ...valido, n_contrato: 'x'.repeat(101), numero_rp: 'x'.repeat(41), contratista_identificacion: 'x'.repeat(31) });
        expect(Object.keys(errores)).toEqual(['n_contrato', 'contratista_identificacion', 'numero_rp']);
    });
});

describe('procesos del contrato', () => {
    const procesos = [
        { id: 1, codigo_proceso: 'SA-01', objeto: 'Estudios', estado: 'evaluacion' },
        { id: 2, codigo_proceso: 'LP-02', objeto: 'Puente', estado: 'adjudicado' },
        { id: 3, codigo_proceso: '', objeto: 'Suministro de tubería', estado: 'celebrado' },
        { id: 4, codigo_proceso: 'CD-04', objeto: 'Viejo', estado: 'cancelado' },
    ];

    it('opciones: sin proceso y los adjudicados o celebrados', () => {
        expect(opcionesProceso(procesos, null)).toEqual([
            { valor: '', etiqueta: 'Sin proceso' },
            { valor: '2', etiqueta: 'LP-02 · Puente (Adjudicado)' },
            { valor: '3', etiqueta: 'Sin número · Suministro de tubería (Celebrado)' },
        ]);
    });

    it('opciones: también el actual aunque ya no admita contratos', () => {
        expect(opcionesProceso(procesos, { id: 4 }).map((o) => o.valor)).toEqual(['', '2', '3', '4']);
    });

    it('opciones: el actual con la lista sin cargar, desde su resumen', () => {
        expect(opcionesProceso(null, { id: 9, codigo_proceso: 'X-9', objeto: 'Otro', estado: 'celebrado' })).toEqual([
            { valor: '', etiqueta: 'Sin proceso' },
            { valor: '9', etiqueta: 'X-9 · Otro (Celebrado)' },
        ]);
    });

    it('opciones: recorta un objeto largo', () => {
        const [, opcion] = opcionesProceso([{ id: 1, codigo_proceso: 'A', objeto: 'x'.repeat(100), estado: 'adjudicado' }], null);
        expect(opcion.etiqueta).toBe(`A · ${'x'.repeat(60)}… (Adjudicado)`);
    });

    it('el proceso elegido sale de la lista y, si no está, del contrato', () => {
        expect(procesoElegido(procesos, null, '2')).toBe(procesos[1]);
        const actual = { id: 9, codigo_proceso: 'X-9' };
        expect(procesoElegido(procesos, actual, '9')).toBe(actual);
        expect(procesoElegido(procesos, actual, '')).toBeNull();
        expect(procesoElegido(null, null, '7')).toBeNull();
    });

    it('datos de solo lectura del proceso: número, modalidad, plataforma y enlace', () => {
        expect(datosDelProceso(presentado.proceso)).toEqual({
            numero: 'LP-01-2026',
            enlace: 'https://community.secop.gov.co/p/1',
            modalidad: 'Licitación pública',
            plataforma: 'SECOP II',
        });
        expect(datosDelProceso({ id: 1, url_proceso: 'javascript:alert(1)' })).toEqual({ numero: '—', enlace: null, modalidad: '—', plataforma: '—' });
    });

    it('nota R4: si el proyecto tiene procesos y no se elige ninguno', () => {
        expect(notaProceso(procesos, '')).toBe('Este proyecto tiene procesos de contratación: si el contrato salió de uno, elígelo.');
        expect(notaProceso(procesos, '2')).toBeNull();
        expect(notaProceso([], '')).toBeNull();
        expect(notaProceso(null, '')).toBeNull();
    });

    it('nota R4: si ningún proceso admite contratos, lo dice', () => {
        expect(notaProceso([procesos[0]], '')).toBe('Este proyecto tiene procesos de contratación, pero ninguno adjudicado o celebrado: cuando lo esté, podrás vincularle el contrato.');
    });
});

describe('aplicarPrecarga', () => {
    const precarga = { objeto: 'Puente vehicular', contratante: 'Gobernación del Cesar', valor_inicial: '2500000.00' };

    it('llena los campos vacíos', () => {
        const f = aplicarPrecarga(contratoVacio(3), precarga);
        expect(f.objeto).toBe('Puente vehicular');
        expect(f.contratante).toBe('Gobernación del Cesar');
        expect(f.valor_inicial).toBe('2.500.000');
        expect(f.proyecto).toBe('3');
    });

    it('no toca lo que ya se escribió (R8)', () => {
        const f = aplicarPrecarga({ ...contratoVacio(), objeto: 'Mío', contratante: ' Yo ', valor_inicial: '10' }, precarga);
        expect(f.objeto).toBe('Mío');
        expect(f.contratante).toBe(' Yo ');
        expect(f.valor_inicial).toBe('10');
    });

    it('un campo con solo espacios cuenta como vacío', () => {
        expect(aplicarPrecarga({ ...contratoVacio(), objeto: '   ' }, precarga).objeto).toBe('Puente vehicular');
    });

    it('no sugiere un valor nulo ni cero, ni textos vacíos', () => {
        const f = aplicarPrecarga(contratoVacio(), { objeto: null, contratante: '', valor_inicial: '0.00' });
        expect(f).toEqual(contratoVacio());
        expect(aplicarPrecarga(contratoVacio(), { valor_inicial: null }).valor_inicial).toBe('');
    });

    it('sin precarga devuelve el formulario igual', () => {
        const f = contratoVacio();
        expect(aplicarPrecarga(f, null)).toEqual(f);
    });
});

describe('vigentes', () => {
    it('iguales a los iniciales: nada que mostrar', () => {
        expect(vigentesDistintos(presentado)).toEqual({ valor: false, fechaFin: false });
    });

    it('con adiciones o prórrogas, distintos', () => {
        expect(vigentesDistintos({ ...presentado, valor: '2000000.50', fecha_fin: '2026-07-30' })).toEqual({ valor: true, fechaFin: true });
    });

    it('compara valores como números y fechas por día', () => {
        expect(vigentesDistintos({ ...presentado, valor_inicial: '1500000.5', fecha_fin: '2026-06-30 00:00:00' })).toEqual({ valor: false, fechaFin: false });
    });

    it('sin contrato o sin iniciales, nada que mostrar', () => {
        expect(vigentesDistintos(null)).toEqual({ valor: false, fechaFin: false });
        expect(vigentesDistintos({ valor: '10', valor_inicial: null, fecha_fin: '2026-01-01', fecha_fin_inicial: null })).toEqual({ valor: false, fechaFin: false });
    });

    it('valor vigente: el inicial del formulario más las adiciones del contrato', () => {
        const conAdicion = { ...presentado, valor_inicial: '1000.00', valor: '1500.00' };
        expect(valorVigente({ valor_inicial: '1.000' }, conAdicion)).toBe(1500);
        expect(valorVigente({ valor_inicial: '2.000,25' }, conAdicion)).toBe(2500.25);
    });

    it('valor vigente de un contrato nuevo es su valor inicial; vacío, cero', () => {
        expect(valorVigente({ valor_inicial: '1.234,5' }, null)).toBe(1234.5);
        expect(valorVigente({ valor_inicial: '' }, null)).toBe(0);
    });
});

describe('opcionesTipoContrato', () => {
    const tipos = [
        { id: 1, nombre: 'Obra', activo: true },
        { id: 2, nombre: 'Consultoría', activo: 0 },
        { id: 3, nombre: 'Suministro', activo: 1 },
    ];

    it('los activos', () => {
        expect(opcionesTipoContrato(tipos, null)).toEqual([
            { valor: '1', etiqueta: 'Obra' },
            { valor: '3', etiqueta: 'Suministro' },
        ]);
    });

    it('y el actual si está inactivo, marcado', () => {
        expect(opcionesTipoContrato(tipos, { tipo_contrato_id: '2' })).toEqual([
            { valor: '1', etiqueta: 'Obra' },
            { valor: '3', etiqueta: 'Suministro' },
            { valor: '2', etiqueta: 'Consultoría (inactivo)' },
        ]);
    });

    it('con la lista sin cargar, el actual con su nombre', () => {
        expect(opcionesTipoContrato(null, presentado)).toEqual([{ valor: '2', etiqueta: 'Obra' }]);
        expect(opcionesTipoContrato(null, { tipo_contrato_id: 8 })).toEqual([{ valor: '8', etiqueta: 'Tipo actual' }]);
    });

    it('el actual activo no se repite', () => {
        expect(opcionesTipoContrato(tipos, { tipo_contrato_id: 1 })).toHaveLength(2);
    });
});

describe('erroresGuardarContrato', () => {
    const error422 = (errors) => ({ response: { status: 422, data: { message: 'x', errors } } });

    it('los errores de campos con control quedan por campo; los demás, para avisar', () => {
        const r = erroresGuardarContrato(error422({
            objeto: ['Escribe el objeto del contrato.'],
            proyecto: ['El contrato debe seguir en un proyecto.'],
            'anexos.0.fecha': ['Falta la fecha del anexo.'],
        }));
        expect(r.campos).toEqual({ objeto: 'Escribe el objeto del contrato.' });
        expect(r.otros).toEqual(['El contrato debe seguir en un proyecto.', 'Falta la fecha del anexo.']);
    });

    it('un error que no es 422 da null', () => {
        expect(erroresGuardarContrato({ response: { status: 500 } })).toBeNull();
    });
});

describe('primerCampoConError', () => {
    it('el primero en el orden del formulario, sin contar los vacíos', () => {
        expect(primerCampoConError({ estado: 'a', n_contrato: 'b', objeto: undefined })).toBe('n_contrato');
        expect(primerCampoConError({ objeto: undefined })).toBeNull();
        expect(primerCampoConError({})).toBeNull();
    });
});
```

En `resources/js/utils/avanceContratos.test.js`, reemplazar (1 de 1):

```js
        expect(construirSerieTemporal({ valor: '1000000000.00', actividades: [], avancesFinancieros: [] })).toEqual([]);
    });
});
```

por:

```js
        expect(construirSerieTemporal({ valor: '1000000000.00', actividades: [], avancesFinancieros: [] })).toEqual([]);
    });
});

describe('aplanarContratos y los anulados', () => {
    it('deja fuera los contratos anulados (R16), como la cadena presupuestal', () => {
        const proyectos = [{
            id: 1,
            nombre: 'Acueducto',
            contratos: [
                { id: 1, estado: 'en_ejecucion', valor: '100.00', actividades: [], avancesFinancieros: [] },
                { id: 2, estado: 'anulado', valor: '900.00', actividades: [], avancesFinancieros: [] },
                { id: 3, valor: '50.00', actividades: [], avancesFinancieros: [] },
            ],
        }];
        const filas = aplanarContratos(proyectos);
        expect(filas.map((c) => c.id)).toEqual([1, 3]);
        expect(filas.reduce((s, c) => s + c.valorNumerico, 0)).toBe(150);
    });
});
```

En `resources/js/utils/cadenaPresupuestal.test.js`, reemplazar (1 de 1):

```js
        expect(r.ejecutado).toBe(0);
    });

    it('calcula el porcentaje contratado sobre lo planeado, redondeado', () => {
        // 900M / 1500M = 60%
        expect(calcularCadenaPresupuestal(proyectos).pctContratado).toBe(60);
```

por:

```js
        expect(r.ejecutado).toBe(0);
    });

    it('un contrato anulado no aporta a contratado ni a ejecutado (R16)', () => {
        const conAnulado = [{
            id: 9, presupuesto: '100000000.00',
            contratos: [
                { id: 90, estado: 'en_ejecucion', valor: '60000000.00', avancesFinancieros: [{ valor_facturado: '10000000.00' }] },
                { id: 91, estado: 'anulado', valor: '30000000.00', avancesFinancieros: [{ valor_facturado: '5000000.00' }] },
            ],
        }];
        const r = calcularCadenaPresupuestal(conAnulado);
        expect(r.contratado).toBe(60000000);
        expect(r.ejecutado).toBe(10000000);
    });

    it('calcula el porcentaje contratado sobre lo planeado, redondeado', () => {
        // 900M / 1500M = 60%
        expect(calcularCadenaPresupuestal(proyectos).pctContratado).toBe(60);
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/contratos.test.js resources/js/utils/avanceContratos.test.js resources/js/utils/cadenaPresupuestal.test.js`
Expected: FALLAN los 3 archivos (2 pruebas y la carga de `contratos.test.js`): no existe `resources/js/utils/contratos.js`, y la cadena presupuestal y `aplanarContratos` todavía cuentan los contratos anulados.

- [ ] **Paso 3: Implementar**

Crear `resources/js/utils/contratos.js`:

```js
// Lógica de presentación del formulario de contratos: estados, formulario, lo que se envía a
// /guardarContrato, el proceso del contrato y su precarga, y los valores vigentes.
// Funciones puras, probadas en contratos.test.js. Las reglas las valida el backend
// (ContratoController y ContratosService); aquí se adelantan los mismos mensajes.

import { erroresPorCampo, etiquetaEstadoProceso, etiquetaPlataforma, numeroProceso, valorDesdeApi, valorParaApi } from './contratacion';

// Los valores son las constantes de Contrato (contratos.test.js lo comprueba).
export const ESTADOS_CONTRATO = [
    ['celebrado', 'Celebrado'],
    ['en_ejecucion', 'En ejecución'],
    ['suspendido', 'Suspendido'],
    ['terminado', 'Terminado'],
    ['liquidado', 'Liquidado'],
    ['anulado', 'Anulado'],
];

/** La etiqueta del estado; un texto que no es de la lista se muestra tal cual. */
export const etiquetaEstadoContrato = (valor) => ESTADOS_CONTRATO.find(([v]) => v === valor)?.[1] ?? (valor || '—');

// Estados de un proceso al que se puede vincular un contrato nuevo (ProcesoLicitacion::ADMITEN_CONTRATOS).
const ADMITEN_CONTRATOS = ['adjudicado', 'celebrado'];

/** El formulario vacío. Sus claves son los campos de la API, en el orden del formulario. */
export const CONTRATO_VACIO = {
    proyecto: '',
    proceso_id: '',
    tipo_contrato_id: '',
    n_contrato: '',
    objeto: '',
    contratante: '',
    contratante_nit: '',
    contratista: '',
    contratista_identificacion: '',
    supervisor: '',
    interventoria: '',
    numero_rp: '',
    valor_inicial: '',
    fecha_inicio: '',
    fecha_fin_inicial: '',
    fecha_suscripcion: '',
    fecha_rp: '',
    fecha_acta_inicio: '',
    estado: '',
    anticipo: false,
    porcentaje_anticipo: '',
    avance_fisico: '',
    avance_financiero: '',
};

export const CAMPOS_CONTRATO = Object.keys(CONTRATO_VACIO);

const CAMPOS_TEXTO = [
    'n_contrato', 'objeto', 'contratante', 'contratante_nit', 'contratista',
    'contratista_identificacion', 'supervisor', 'interventoria', 'numero_rp',
];
const CAMPOS_ID = ['proyecto', 'proceso_id', 'tipo_contrato_id'];
const CAMPOS_FECHA = ['fecha_inicio', 'fecha_fin_inicial', 'fecha_suscripcion', 'fecha_rp', 'fecha_acta_inicio'];

// Largos máximos de ContratoController::reglas().
const MAXIMOS = {
    n_contrato: 100,
    objeto: 5000,
    contratante: 5000,
    contratante_nit: 30,
    contratista: 5000,
    contratista_identificacion: 30,
    supervisor: 255,
    interventoria: 5000,
    numero_rp: 40,
};

// La columna es decimal(15, 2): 13 cifras enteras y 2 decimales.
const CIFRAS_ENTERAS = 13;

const vacio = (valor) => valor === null || valor === undefined || String(valor).trim() === '';
const comoTexto = (valor) => (valor === null || valor === undefined ? '' : String(valor));
// Las fechas del listado pueden traer la hora ("2026-01-10 00:00:00"): el input date quiere el día.
const comoFecha = (valor) => (vacio(valor) ? '' : String(valor).slice(0, 10));
const esActivo = (valor) => valor === true || valor === 1 || valor === '1';

export const contratoVacio = (proyectoId = null) => ({ ...CONTRATO_VACIO, proyecto: comoTexto(proyectoId) });

/**
 * El formulario de un contrato, del listado (columnas crudas: `proceso_licitacion`, `anticipo`
 * 1/0) o de presentar. Un contrato sin valor ni fecha fin iniciales toma los vigentes.
 */
export const formularioContrato = (contrato) => {
    if (!contrato) return contratoVacio();
    const formulario = { id: contrato.id };
    CAMPOS_TEXTO.forEach((campo) => { formulario[campo] = comoTexto(contrato[campo]); });
    CAMPOS_FECHA.forEach((campo) => { formulario[campo] = comoFecha(contrato[campo]); });
    return {
        ...formulario,
        proyecto: comoTexto(contrato.proyecto),
        proceso_id: comoTexto(contrato.proceso_id ?? contrato.proceso_licitacion ?? contrato.proceso?.id),
        tipo_contrato_id: comoTexto(contrato.tipo_contrato_id ?? contrato.tipo_contrato?.id),
        valor_inicial: valorDesdeApi(contrato.valor_inicial ?? contrato.valor),
        fecha_fin_inicial: comoFecha(contrato.fecha_fin_inicial ?? contrato.fecha_fin),
        estado: comoTexto(contrato.estado),
        anticipo: esActivo(contrato.anticipo),
        porcentaje_anticipo: comoTexto(contrato.porcentaje_anticipo),
        avance_fisico: contrato.avance_fisico ?? '',
        avance_financiero: contrato.avance_financiero ?? '',
    };
};

const avance = (valor) => {
    if (vacio(valor)) return null;
    const numero = Number(String(valor).replace(',', '.'));
    return Number.isFinite(numero) ? Math.min(100, Math.max(0, numero)) : null;
};

/**
 * Lo que espera `formContrato` de /guardarContrato. Se envían todos los campos: un opcional vacío
 * va como null y así se borra al editar. El valor y la fecha fin vigentes no se envían: los
 * calcula el servidor. Al editar, `contrato` es el que se abrió: van su id y su `updated_at`,
 * para que el servidor rechace el cambio si otra persona lo modificó entretanto.
 */
export const datosContrato = (formulario, contrato = null) => {
    const texto = (campo) => (vacio(formulario[campo]) ? null : String(formulario[campo]).trim());
    const datos = {};
    CAMPOS_CONTRATO.forEach((campo) => {
        if (CAMPOS_ID.includes(campo)) datos[campo] = texto(campo) === null ? null : Number(texto(campo));
        else datos[campo] = texto(campo);
    });
    const anticipo = Boolean(formulario.anticipo);
    return {
        ...(contrato ? { id: contrato.id } : {}),
        ...datos,
        valor_inicial: valorParaApi(formulario.valor_inicial),
        anticipo,
        porcentaje_anticipo: anticipo && texto('porcentaje_anticipo') !== null ? Number(texto('porcentaje_anticipo')) : null,
        avance_fisico: avance(formulario.avance_fisico),
        avance_financiero: avance(formulario.avance_financiero),
        ...(contrato ? { actualizado: contrato.updated_at ?? null } : {}),
    };
};

/** Errores del formulario por campo, con los mensajes del servidor; {} si se puede enviar. */
export const validarContrato = (formulario) => {
    const errores = {};
    const texto = (campo) => String(formulario[campo] ?? '').trim();
    if (!texto('n_contrato')) errores.n_contrato = 'Escribe el número del contrato.';
    if (!texto('objeto')) errores.objeto = 'Escribe el objeto del contrato.';
    CAMPOS_TEXTO.forEach((campo) => {
        if (!errores[campo] && texto(campo).length > MAXIMOS[campo]) {
            errores[campo] = `Admite hasta ${MAXIMOS[campo]} caracteres.`;
        }
    });
    const valor = valorParaApi(formulario.valor_inicial);
    if (valor === null) errores.valor_inicial = 'Escribe el valor inicial del contrato.';
    else if (valor.split('.')[0].length > CIFRAS_ENTERAS) {
        errores.valor_inicial = `El valor inicial admite hasta ${CIFRAS_ENTERAS} cifras enteras y 2 decimales.`;
    }
    const inicio = texto('fecha_inicio');
    const fin = texto('fecha_fin_inicial');
    if (inicio && fin && fin < inicio) errores.fecha_fin_inicial = 'La fecha de fin no puede ser anterior a la de inicio.';
    if (!texto('estado')) errores.estado = 'Elige el estado del contrato.';
    // En el orden del formulario: así se enfoca el primero.
    return Object.fromEntries(CAMPOS_CONTRATO.filter((c) => errores[c]).map((c) => [c, errores[c]]));
};

// Campos con un control en el formulario: el proyecto no lo tiene (es el de la lista abierta).
const CAMPOS_CON_CONTROL = CAMPOS_CONTRATO.filter((campo) => campo !== 'proyecto');

/**
 * Los errores de un 422 de /guardarContrato: `campos`, los que se muestran junto a su control, y
 * `otros`, los mensajes que no tienen dónde mostrarse (el proyecto, los anexos) y se avisan.
 * null si no es un 422 con errores.
 */
export const erroresGuardarContrato = (error) => erroresPorCampo(error, CAMPOS_CON_CONTROL);

/** El primer campo con error, en el orden del formulario; null si no hay. */
export const primerCampoConError = (errores) => CAMPOS_CON_CONTROL.find((campo) => errores?.[campo]) ?? null;

// ---------- Proceso del contrato ----------

const MAXIMO_OBJETO_OPCION = 60;

const etiquetaProceso = (proceso) => {
    const codigo = String(proceso.codigo_proceso ?? '').trim() || 'Sin número';
    const objeto = String(proceso.objeto ?? '').trim();
    const corto = objeto.length > MAXIMO_OBJETO_OPCION ? `${objeto.slice(0, MAXIMO_OBJETO_OPCION)}…` : objeto;
    return `${codigo}${corto ? ` · ${corto}` : ''} (${etiquetaEstadoProceso(proceso.estado)})`;
};

/**
 * Opciones del select de proceso: "Sin proceso", los del proyecto que admiten contratos
 * (adjudicados o celebrados) y el que el contrato ya tiene (`actual`, su resumen), aunque haya
 * cambiado de estado o la lista no haya cargado.
 */
export const opcionesProceso = (procesos, actual) => {
    const idActual = actual?.id === undefined || actual?.id === null ? null : String(actual.id);
    const opciones = (procesos ?? [])
        .filter((p) => ADMITEN_CONTRATOS.includes(p.estado) || String(p.id) === idActual)
        .map((p) => ({ valor: String(p.id), etiqueta: etiquetaProceso(p) }));
    if (idActual !== null && !opciones.some((o) => o.valor === idActual)) {
        opciones.push({ valor: idActual, etiqueta: etiquetaProceso(actual) });
    }
    return [{ valor: '', etiqueta: 'Sin proceso' }, ...opciones];
};

/** El proceso elegido en el select: de la lista del proyecto o, si no está, el del contrato. */
export const procesoElegido = (procesos, actual, procesoId) => {
    if (vacio(procesoId)) return null;
    const id = String(procesoId);
    return (procesos ?? []).find((p) => String(p.id) === id)
        ?? (actual && String(actual.id) === id ? actual : null);
};

/** Lo que se muestra de solo lectura del proceso elegido. */
export const datosDelProceso = (proceso) => {
    // numeroProceso ya descarta los enlaces que no son http(s).
    const { enlace } = numeroProceso(proceso);
    return {
        numero: String(proceso?.codigo_proceso ?? '').trim() || '—',
        enlace,
        modalidad: proceso?.modalidad?.nombre || '—',
        plataforma: etiquetaPlataforma(proceso?.plataforma),
    };
};

/** R4: si el proyecto tiene procesos y no se eligió ninguno, una nota lo recuerda; si no, null. */
export const notaProceso = (procesos, procesoId) => {
    if (!vacio(procesoId) || !(procesos ?? []).length) return null;
    return procesos.some((p) => ADMITEN_CONTRATOS.includes(p.estado))
        ? 'Este proyecto tiene procesos de contratación: si el contrato salió de uno, elígelo.'
        : 'Este proyecto tiene procesos de contratación, pero ninguno adjudicado o celebrado: cuando lo esté, podrás vincularle el contrato.';
};

/**
 * R8: la precarga del proceso (GET /procesos/{id}/precarga-contrato) llena solo los campos
 * vacíos: objeto, contratante y valor inicial. Un valor sugerido nulo o cero no se escribe.
 */
export const aplicarPrecarga = (formulario, precarga) => {
    if (!precarga) return formulario;
    const resultado = { ...formulario };
    ['objeto', 'contratante'].forEach((campo) => {
        if (vacio(resultado[campo]) && !vacio(precarga[campo])) resultado[campo] = String(precarga[campo]);
    });
    if (vacio(resultado.valor_inicial) && Number(precarga.valor_inicial) > 0) {
        resultado.valor_inicial = valorDesdeApi(precarga.valor_inicial);
    }
    return resultado;
};

// ---------- Valores vigentes ----------

/**
 * Si el valor o la fecha fin vigentes (inicial más adiciones y prórrogas) difieren de los
 * iniciales: solo entonces se muestran, de solo lectura.
 */
export const vigentesDistintos = (contrato) => {
    const distinto = (vigente, inicial, comparar) => !vacio(vigente) && !vacio(inicial) && comparar(vigente) !== comparar(inicial);
    return {
        valor: distinto(contrato?.valor, contrato?.valor_inicial, Number),
        fechaFin: distinto(contrato?.fecha_fin, contrato?.fecha_fin_inicial, comoFecha),
    };
};

/**
 * El valor vigente con el valor inicial que tiene el formulario: el inicial más las adiciones
 * que ya tiene el contrato (vigente menos inicial guardados). Es la base del avance financiero y
 * del porcentaje ejecutado de las actas.
 */
export const valorVigente = (formulario, contrato) => {
    const inicial = Number(valorParaApi(formulario?.valor_inicial) ?? 0);
    const adiciones = !vacio(contrato?.valor) && !vacio(contrato?.valor_inicial)
        ? Number(contrato.valor) - Number(contrato.valor_inicial)
        : 0;
    return Math.round((inicial + adiciones) * 100) / 100;
};

// ---------- Tipo de contrato ----------

/**
 * Opciones del select de tipo de contrato: los activos y el que el contrato ya tiene si está
 * inactivo, marcado. Con la lista sin cargar (null) se ofrece el actual para no perderlo.
 */
export const opcionesTipoContrato = (tipos, contrato) => {
    const opciones = (tipos ?? []).filter((t) => esActivo(t.activo)).map((t) => ({ valor: String(t.id), etiqueta: t.nombre }));
    const id = contrato?.tipo_contrato_id ?? contrato?.tipo_contrato?.id;
    if (!vacio(id) && !opciones.some((o) => o.valor === String(id))) {
        const enLista = (tipos ?? []).find((t) => String(t.id) === String(id));
        const nombre = enLista?.nombre ?? contrato?.tipo_contrato?.nombre ?? 'Tipo actual';
        opciones.push({ valor: String(id), etiqueta: enLista ? `${nombre} (inactivo)` : nombre });
    }
    return opciones;
};
```

En `resources/css/Parametros.css`, reemplazar (1 de 1):

```css

.contratos-list-header, .contrato-form-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .6rem; padding-bottom: .75rem; margin-bottom: 1rem; border-bottom: 1px solid var(--gp-linea); }

.presupuesto-total { margin-top: 1rem; }
.total-card { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .4rem 1rem; padding: .9rem 1.1rem; background: var(--gp-azul-suave); border: 1px solid rgba(1, 142, 178, .6); border-radius: var(--gp-radio); }
.total-label { font-size: .9rem; font-weight: 600; color: var(--gp-texto-2); }
```

por:

```css

.contratos-list-header, .contrato-form-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .6rem; padding-bottom: .75rem; margin-bottom: 1rem; border-bottom: 1px solid var(--gp-linea); }

/* Formulario de contrato: errores por campo, notas y datos del proceso de solo lectura */
.form-group .ct-error-campo { display: block; margin-top: .25rem; }
.form-group [aria-invalid="true"] { border-color: var(--gp-coral); }
.contrato-nota { margin: -.4rem 0 1rem; font-size: .82rem; color: var(--gp-texto-3); }
.contrato-proceso-datos { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .6rem 1rem; padding: .75rem 1rem; margin: 0 0 1rem; background: var(--gp-superficie-2); border: 1px solid var(--gp-linea); border-radius: var(--gp-radio); }
.contrato-proceso-datos dt { font-size: .74rem; font-weight: 600; color: var(--gp-texto-3); }
.contrato-proceso-datos dd { margin: .15rem 0 0; font-size: .88rem; color: var(--gp-texto); overflow-wrap: anywhere; }

.presupuesto-total { margin-top: 1rem; }
.total-card { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .4rem 1rem; padding: .9rem 1.1rem; background: var(--gp-azul-suave); border: 1px solid rgba(1, 142, 178, .6); border-radius: var(--gp-radio); }
.total-label { font-size: .9rem; font-weight: 600; color: var(--gp-texto-2); }
```

En `resources/js/components/ContratosPanel.jsx`, reemplazar (1 de 1):

```jsx
    const conDatos = contratos.filter((c) => !c.sinDatos);
    const sinDatos = contratos.filter((c) => c.sinDatos);

    const totalValor = contratos.reduce((s, c) => s + c.valorNumerico, 0);

    const promedioFisico = conDatos.length
```

por:

```jsx
    const conDatos = contratos.filter((c) => !c.sinDatos);
    const sinDatos = contratos.filter((c) => c.sinDatos);

    // aplanarContratos ya deja fuera los anulados: no cuentan en ningún total.
    const totalValor = contratos.reduce((s, c) => s + c.valorNumerico, 0);

    const promedioFisico = conDatos.length
```

En `resources/js/components/ModalProyecto.jsx`, reemplazar (1 de 2):

```jsx
import { faClipboardCheck, faXmark } from '@fortawesome/free-solid-svg-icons';
import { COLOR_SEVERIDAD, construirSerieTemporal, severidadDesfase } from '../utils/avanceContratos';
import { textoAvanceProyecto } from '../utils/expedientesResumen';

const moneda = (valor) => (valor || valor === 0 ? `$ ${Math.round(Number(valor)).toLocaleString('es-CO')}` : '—');
```

por:

```jsx
import { faClipboardCheck, faXmark } from '@fortawesome/free-solid-svg-icons';
import { COLOR_SEVERIDAD, construirSerieTemporal, severidadDesfase } from '../utils/avanceContratos';
import { textoAvanceProyecto } from '../utils/expedientesResumen';
import { etiquetaEstadoContrato } from '../utils/contratos';

const moneda = (valor) => (valor || valor === 0 ? `$ ${Math.round(Number(valor)).toLocaleString('es-CO')}` : '—');
```

En `resources/js/components/ModalProyecto.jsx`, reemplazar (2 de 2):

```jsx
    const ultimo = serie[serie.length - 1];

    return (
        <Ventana titulo={`Contrato ${contrato.n_contrato || contrato.numero || contrato.nombre}`} subtitulo={contrato.estado} onCerrar={onCerrar}>
            <div className="tb-modal-cuerpo">
                <dl className="tb-datos">
                    <div className="tb-dato ancho"><dt className="gp-etiqueta">Objeto</dt><dd>{contrato.objeto || contrato.descripcion || '—'}</dd></div>
```

por:

```jsx
    const ultimo = serie[serie.length - 1];

    return (
        <Ventana titulo={`Contrato ${contrato.n_contrato || contrato.numero || contrato.nombre}`} subtitulo={contrato.estado ? etiquetaEstadoContrato(contrato.estado) : null}onCerrar={onCerrar}>
            <div className="tb-modal-cuerpo">
                <dl className="tb-datos">
                    <div className="tb-dato ancho"><dt className="gp-etiqueta">Objeto</dt><dd>{contrato.objeto || contrato.descripcion || '—'}</dd></div>
```

En `resources/js/components/Parametros.jsx`, reemplazar (1 de 22):

```jsx
import React, { useState, useEffect } from 'react';
import { Navigate, useNavigate, useParams } from 'react-router-dom';
import axios from '../axios';
import { faPlus, faTrash, faSave, faTimes, faCalculator, faFile, faInfo, faEdit, faClipboardCheck, faEye, faGavel } from '@fortawesome/free-solid-svg-icons';
```

por:

```jsx
import React, { useState, useEffect, useRef } from 'react';
import { Navigate, useNavigate, useParams } from 'react-router-dom';
import axios from '../axios';
import { faPlus, faTrash, faSave, faTimes, faCalculator, faFile, faInfo, faEdit, faClipboardCheck, faEye, faGavel } from '@fortawesome/free-solid-svg-icons';
```

En `resources/js/components/Parametros.jsx`, reemplazar (2 de 22):

```jsx
import '../../css/Parametros.css';
import { seccionDesdeSlug } from '../utils/navegacion';
import { alternarFuente, datosFinanciacionDe, financiacionCompleta, fuentesDisponibles, mensajeDeError } from '../utils/fuentesProyecto';
import CatalogoModalidades from './CatalogoModalidades';
import CatalogoEtapasContratacion from './CatalogoEtapasContratacion';
import ProcesosProyecto from './ProcesosProyecto';
```

por:

```jsx
import '../../css/Parametros.css';
import { seccionDesdeSlug } from '../utils/navegacion';
import { alternarFuente, datosFinanciacionDe, financiacionCompleta, fuentesDisponibles, mensajeDeError } from '../utils/fuentesProyecto';
import { datosEstado, esEdicionDesactualizada, textoValor, valorEnEdicion } from '../utils/contratacion';
import {
    ESTADOS_CONTRATO, aplicarPrecarga, contratoVacio, datosContrato, datosDelProceso, erroresGuardarContrato, etiquetaEstadoContrato,
    formularioContrato, notaProceso, opcionesProceso, opcionesTipoContrato, primerCampoConError, procesoElegido, validarContrato,
    valorVigente, vigentesDistintos,
} from '../utils/contratos';
import { avisarError } from '../utils/avisosPlantillas';
import { htmlErrores, mensajeApi } from '../utils/plantillasChecklist';
import CatalogoModalidades from './CatalogoModalidades';
import CatalogoEtapasContratacion from './CatalogoEtapasContratacion';
import ProcesosProyecto from './ProcesosProyecto';
```

En `resources/js/components/Parametros.jsx`, reemplazar (3 de 22):

```jsx
        accion: 'Agregar'
    });

    // Estados posibles para los contratos
    const estadosContratos = [
        { value: 'vigente', label: 'Vigente' },
        { value: 'ejecutado', label: 'Ejecutado' },
        { value: 'liquidado', label: 'Liquidado' },
        { value: 'suspendido', label: 'Suspendido' },
        { value: 'anulado', label: 'Anulado' },
    ];

    // Estado para la lista de contratos
    const [contratos, setContratos] = useState([]);

    // Campos de formulario para contratos
    const [formContrato, setFormContrato] = useState({
        n_contrato: '',
        objeto: '',
        contratante: '',
        contratista: '',
        valor: '',
        fecha_inicio: '',
        fecha_fin: '',
        interventoria: '',
        avance: '',
        avance_financiero: '',
        avance_fisico: '',
        estado: '',
        proyecto: '',
        anticipo: false,
        porcentaje_anticipo: ''
    });

    const [actasFinancieras, setActasFinancieras] = useState([]);
    const [formActaFinanciera, setFormActaFinanciera] = useState({
```

por:

```jsx
        accion: 'Agregar'
    });

    // Estado para la lista de contratos
    const [contratos, setContratos] = useState([]);

    // Campos de formulario para contratos (los de la API: utils/contratos.js)
    const [formContrato, setFormContrato] = useState(() => contratoVacio());
    // Errores del formulario por campo, de la validación local o de un 422.
    const [erroresContrato, setErroresContrato] = useState({});
    const [guardandoContrato, setGuardandoContrato] = useState(false);
    // Evita un segundo envío mientras el primero no termina (el estado tarda un render en verse).
    const enviandoContrato = useRef(false);
    // Cada error de validación sube el contador: tras pintar, se enfoca el primer campo con error.
    const [focoErrorContrato, setFocoErrorContrato] = useState(0);
    const formularioContratoRef = useRef(null);
    // Turno de la carga de procesos: una respuesta atrasada (de otro contrato) se descarta.
    const turnoProcesosContrato = useRef(0);
    // Catálogo de tipos de contrato y procesos del proyecto; null mientras no cargan.
    const [tiposContrato, setTiposContrato] = useState(null);
    const [procesosContrato, setProcesosContrato] = useState(null);

    const [actasFinancieras, setActasFinancieras] = useState([]);
    const [formActaFinanciera, setFormActaFinanciera] = useState({
```

En `resources/js/components/Parametros.jsx`, reemplazar (4 de 22):

```jsx
    // Manejar cambios en el formulario de contratos
    const handleContratoChange = (e) => {
        const { name, value, type, checked } = e.target;
        if (name === 'anticipo' && !checked) {
            setFormContrato(prev => ({ ...prev, anticipo: false, porcentaje_anticipo: '' }));
            return;
```

por:

```jsx
    // Manejar cambios en el formulario de contratos
    const handleContratoChange = (e) => {
        const { name, value, type, checked } = e.target;
        setErroresContrato(prev => (prev[name] ? { ...prev, [name]: undefined } : prev));
        if (name === 'proceso_id') {
            setFormContrato(prev => ({ ...prev, proceso_id: value }));
            // R8: en un contrato nuevo, el proceso elegido llena los campos que siguen vacíos.
            if (value && !editingContrato) precargarDesdeProceso(value);
            return;
        }
        if (name === 'anticipo' && !checked) {
            setFormContrato(prev => ({ ...prev, anticipo: false, porcentaje_anticipo: '' }));
            return;
```

En `resources/js/components/Parametros.jsx`, reemplazar (5 de 22):

```jsx
        setFormContrato(prev => ({ ...prev, [name]: type === 'checkbox' ? checked : value }));
    };

    // Manejar cambios en el formulario de anexos
    const handleAnexoChange = (e) => {
        const { name, value, files } = e.target;
```

por:

```jsx
        setFormContrato(prev => ({ ...prev, [name]: type === 'checkbox' ? checked : value }));
    };

    const precargarDesdeProceso = async (procesoId) => {
        try {
            const { data } = await axios.get(`/procesos/${procesoId}/precarga-contrato`);
            // Si entretanto se eligió otro proceso, esta precarga ya no aplica.
            setFormContrato(prev => (prev.proceso_id === procesoId ? aplicarPrecarga(prev, data?.precarga) : prev));
        } catch (error) {
            avisarError('No se pudieron traer los datos del proceso', error);
        }
    };

    // Tipos de contrato (GET /tiposProcesos) y procesos del proyecto para el formulario.
    const cargarTiposContrato = async () => {
        try {
            const { data } = await axios.get('/tiposProcesos');
            setTiposContrato(Array.isArray(data) ? data : []);
        } catch (error) {
            // Sin catálogo, el select ofrece solo el tipo que el contrato ya tiene.
            setTiposContrato(null);
        }
    };

    // Devuelve la lista pedida (o null si falló); solo la última carga llega al formulario.
    const cargarProcesosContrato = async (proyectoId) => {
        const numero = ++turnoProcesosContrato.current;
        if (!proyectoId) {
            setProcesosContrato([]);
            return [];
        }
        try {
            const { data } = await axios.get(`/proyectos/${proyectoId}/procesos`);
            const lista = data?.procesos ?? [];
            if (numero === turnoProcesosContrato.current) setProcesosContrato(lista);
            return lista;
        } catch (error) {
            if (numero === turnoProcesosContrato.current) setProcesosContrato(null);
            return null;
        }
    };

    useEffect(() => {
        if (!focoErrorContrato) return;
        const campo = primerCampoConError(erroresContrato);
        if (campo) formularioContratoRef.current?.querySelector(`[name="${campo}"]`)?.focus();
    }, [focoErrorContrato]);

    // Manejar cambios en el formulario de anexos
    const handleAnexoChange = (e) => {
        const { name, value, files } = e.target;
```

En `resources/js/components/Parametros.jsx`, reemplazar (6 de 22):

```jsx
        const porcentajeAnticipo = parseInt(formContrato.porcentaje_anticipo, 10) || 0;
        const amortizacion = formContrato.anticipo ? Math.round(valorFacturado * (porcentajeAnticipo / 100) * 100) / 100 : 0;
        const valorPresente = Math.round((valorFacturado - amortizacion) * 100) / 100;
        const valorVigente = parseCurrencyValue(formContrato.valor);
        const porcentajeEjecutado = valorVigente ? Math.round((valorFacturado / valorVigente) * 100 * 100) / 100 : 0;
        return { amortizacion, valorPresente, porcentajeEjecutado };
    };
```

por:

```jsx
        const porcentajeAnticipo = parseInt(formContrato.porcentaje_anticipo, 10) || 0;
        const amortizacion = formContrato.anticipo ? Math.round(valorFacturado * (porcentajeAnticipo / 100) * 100) / 100 : 0;
        const valorPresente = Math.round((valorFacturado - amortizacion) * 100) / 100;
        const vigente = valorVigente(formContrato, editingContrato);
        const porcentajeEjecutado = vigente ? Math.round((valorFacturado / vigente) * 100 * 100) / 100 : 0;
        return { amortizacion, valorPresente, porcentajeEjecutado };
    };
```

En `resources/js/components/Parametros.jsx`, reemplazar (7 de 22):

```jsx
            const { amortizacion, valorPresente, porcentajeEjecutado } = calcularAmortizacionYValorPresente(valorFacturado);
            return { ...prev, amortizacion_50: amortizacion, valor_presente_acta: valorPresente, porcentaje_ejecutado: porcentajeEjecutado };
        });
    }, [formContrato.anticipo, formContrato.porcentaje_anticipo, formContrato.valor]);

    // Manejar cambios en el formulario de acta financiera
    const handleActaFinancieraChange = (e) => {
```

por:

```jsx
            const { amortizacion, valorPresente, porcentajeEjecutado } = calcularAmortizacionYValorPresente(valorFacturado);
            return { ...prev, amortizacion_50: amortizacion, valor_presente_acta: valorPresente, porcentaje_ejecutado: porcentajeEjecutado };
        });
    }, [formContrato.anticipo, formContrato.porcentaje_anticipo, formContrato.valor_inicial, editingContrato]);

    // Manejar cambios en el formulario de acta financiera
    const handleActaFinancieraChange = (e) => {
```

En `resources/js/components/Parametros.jsx`, reemplazar (8 de 22):

```jsx

    // Calcular avance financiero automáticamente: suma de valor_facturado de todas las actas / valor vigente del contrato
    useEffect(() => {
        const valorVigente = parseCurrencyValue(formContrato.valor);
        if (actasFinancieras.length === 0 || !valorVigente) {
            setFormContrato(prev => (prev.avance_financiero === '' ? prev : { ...prev, avance_financiero: '' }));
            return;
        }
        const totalFacturado = actasFinancieras.reduce((sum, acta) => sum + (parseFloat(acta.valor_facturado) || 0), 0);
        const porcentaje = Math.round((totalFacturado / valorVigente) * 100 * 100) / 100;
        setFormContrato(prev => (prev.avance_financiero === porcentaje ? prev : { ...prev, avance_financiero: porcentaje }));
    }, [actasFinancieras, formContrato.valor]);

    // Calcular avance físico: suma ponderada de (peso x último % de ejecución) de cada actividad
    const handleCalcularAvanceFisico = () => {
```

por:

```jsx

    // Calcular avance financiero automáticamente: suma de valor_facturado de todas las actas / valor vigente del contrato
    useEffect(() => {
        const vigente = valorVigente(formContrato, editingContrato);
        if (actasFinancieras.length === 0 || !vigente) {
            setFormContrato(prev => (prev.avance_financiero === '' ? prev : { ...prev, avance_financiero: '' }));
            return;
        }
        const totalFacturado = actasFinancieras.reduce((sum, acta) => sum + (parseFloat(acta.valor_facturado) || 0), 0);
        const porcentaje = Math.round((totalFacturado / vigente) * 100 * 100) / 100;
        setFormContrato(prev => (prev.avance_financiero === porcentaje ? prev : { ...prev, avance_financiero: porcentaje }));
    }, [actasFinancieras, formContrato.valor_inicial, editingContrato]);

    // Calcular avance físico: suma ponderada de (peso x último % de ejecución) de cada actividad
    const handleCalcularAvanceFisico = () => {
```

En `resources/js/components/Parametros.jsx`, reemplazar (9 de 22):

```jsx
        setFormContrato(prev => ({ ...prev, avance_fisico: Math.round(total) }));
    };

    // Agregar contrato a la lista
    const handleAddContrato = () => {
        const valorNumerico = parseCurrencyValue(formContrato.valor);
        if (!formContrato.n_contrato || !formContrato.objeto || valorNumerico <= 0 || !formContrato.estado) {
            Swal.fire({ icon: 'warning', title: 'Los campos N° Contrato, Objeto, Valor y Estado son obligatorios' });
            return;
        }
        setContratos(prev => [
            ...prev,
            { ...formContrato, id: Date.now(), valor: valorNumerico }
        ]);
        setFormContrato({
            n_contrato: '', objeto: '',
            contratante: '', contratista: '',
            valor: '', fecha_inicio: '',
            fecha_fin: '', interventoria: '',
            avance_financiero: '',
            avance_fisico: '', estado: '',
            proyecto: proyectoContratos.id || '',
            anticipo: false, porcentaje_anticipo: ''
        });
    };

    // Eliminar contrato de la lista
    const handleDeleteContrato = (id) => {
        setContratos(prev => prev.filter(c => c.id !== id));
    };

    // Editar contrato
    const handleEditContrato = (contrato) => {
        setEditingContrato(contrato);
        setFormContrato({
            id: contrato.id || '',
            n_contrato: contrato.n_contrato || '',
            objeto: contrato.objeto || '',
            contratante: contrato.contratante || '',
            contratista: contrato.contratista || '',
            valor: contrato.valor ? formatCurrency(Number(contrato.valor)) : '',
            fecha_inicio: contrato.fecha_inicio || '',
            fecha_fin: contrato.fecha_fin || '',
            interventoria: contrato.interventoria || '',
            avance_financiero: contrato.avance_financiero || '',
            avance_fisico: contrato.avance_fisico || '',
            estado: contrato.estado || '',
            proyecto: contrato.proyecto || '',
            anticipo: !!contrato.anticipo,
            porcentaje_anticipo: contrato.porcentaje_anticipo ?? ''
        });
        // Cargar anexos del contrato si existen
        if (contrato.anexos && contrato.anexos.length > 0) {
            const anexosFormateados = contrato.anexos.map(anexo => ({
```

por:

```jsx
        setFormContrato(prev => ({ ...prev, avance_fisico: Math.round(total) }));
    };

    // Eliminar contrato. El servidor lo rechaza si tiene actas, actividades o modificaciones, y
    // explica por qué: en ese caso se anula.
    const handleDeleteContrato = async (contrato) => {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            titleText: `¿Eliminar el contrato ${contrato.n_contrato}?`,
            text: 'También se borran sus anexos. No se puede deshacer.',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        try {
            await axios.post('/eliminarContrato', { id: contrato.id });
            await listContratos();
            listProyectos();
            Swal.fire({ icon: 'success', title: 'Contrato eliminado' });
        } catch (error) {
            avisarError('No se pudo eliminar el contrato', error);
        }
    };

    // Editar contrato
    const handleEditContrato = (contrato) => {
        setEditingContrato(contrato);
        setFormContrato(formularioContrato(contrato));
        setErroresContrato({});
        // Cargar anexos del contrato si existen
        if (contrato.anexos && contrato.anexos.length > 0) {
            const anexosFormateados = contrato.anexos.map(anexo => ({
```

En `resources/js/components/Parametros.jsx`, reemplazar (10 de 22):

```jsx
        setModificaciones(contrato.modificaciones || []);
        setResumenModificacion(null);
        setShowContratoForm(true);
    };

    // Guardar contrato (agregar o actualizar)
    const handleSaveContrato = async () => {
        const valorNumerico = parseCurrencyValue(formContrato.valor);
        if (!formContrato.n_contrato || !formContrato.objeto || valorNumerico <= 0 || !formContrato.estado) {
            Swal.fire({ icon: 'warning', title: 'Los campos N° Contrato, Objeto, Valor y Estado son obligatorios' });
            return;
        }

        const contratoData = { ...formContrato, valor: valorNumerico };

        if (editingContrato) {
            // Actualizar contrato existente
            setContratos(prev => prev.map(c =>
                c.id === editingContrato.id ? { ...contratoData, id: c.id, anexos: anexos } : c
            ));
            setEditingContrato(null);
        } else {
            // Agregar nuevo contrato
            setContratos(prev => [
                ...prev,
                { ...contratoData, id: Date.now(), anexos: anexos }
            ]);
        }

        console.log(contratoData);
        console.log(anexos);
        // Actualizar el proyecto con los nuevos contratos con axios
        const response = await axios.post('/guardarContrato', {
            formContrato: contratoData,
            anexos: anexos.map(anexo => ({
                descripcion: anexo.descripcion,
                nombreArchivo: anexo.nombreArchivo,
                rutaArchivo: anexo.rutaArchivo,
                fecha: anexo.fecha
            }))
        });
        if (response.status === 200) {
            Swal.fire({
                title: 'Contrato guardado correctamente',
                icon: 'success'
            }).then(() => {
                listContratos();
                handleCloseModal();
            });
        }

        // Limpiar formulario
        setFormContrato({
            n_contrato: '', objeto: '', contratante: '',
            contratista: '', valor: '',
            fecha_inicio: '', fecha_fin: '',
            interventoria: '',
            avance_financiero: '',
            avance_fisico: '', estado: '',
            proyecto: proyectoContratos.id || '',
            anticipo: false, porcentaje_anticipo: ''
        });
        setShowContratoForm(false);
    };

    // Cancelar edición/agregado de contrato
    const handleCancelContratoForm = () => {
        setShowContratoForm(false);
        setEditingContrato(null);
        setFormContrato({
            n_contrato: '', objeto: '', contratante: '',
            contratista: '', valor: '', fecha_inicio: '',
            fecha_fin: '', interventoria: '',
            avance_financiero: '',
            avance_fisico: '',
            estado: '',
            proyecto: proyectoContratos.id || '',
            anticipo: false, porcentaje_anticipo: ''
        });
        setAnexos([]);
        setFormAnexo({ descripcion: '', archivo: null });
        setActasFinancieras([]);
```

por:

```jsx
        setModificaciones(contrato.modificaciones || []);
        setResumenModificacion(null);
        setShowContratoForm(true);
        // Los estados de los procesos pueden haber cambiado desde que se abrió la lista.
        cargarProcesosContrato(contrato.proyecto ?? proyectoContratos?.id);
    };

    // Primer contrato de un proceso adjudicado: se propone pasar el proceso a celebrado.
    const proponerProcesoCelebrado = async (contrato) => {
        const { isConfirmed } = await Swal.fire({
            icon: 'question',
            titleText: '¿Pasar el proceso a celebrado?',
            text: 'Es el primer contrato de este proceso adjudicado. El cambio queda en el historial del proceso.',
            showCancelButton: true,
            confirmButtonText: 'Sí, pasar a celebrado',
            cancelButtonText: 'Ahora no',
        });
        if (!isConfirmed) return;
        // Recién cargado, el proceso trae su `actualizado` vigente.
        const lista = await cargarProcesosContrato(contrato.proyecto);
        const proceso = (lista ?? []).find(p => p.id === contrato.proceso_id);
        if (!proceso) {
            Swal.fire({ icon: 'error', titleText: 'No se pudo cambiar el estado', text: 'No se pudo cargar el proceso: cambia su estado desde Procesos.' });
            return;
        }
        try {
            await axios.post(`/procesos/${proceso.id}/estado`, datosEstado(proceso, 'celebrado', null));
            await cargarProcesosContrato(contrato.proyecto);
            await Swal.fire({ icon: 'success', title: 'El proceso quedó celebrado' });
        } catch (error) {
            if (esEdicionDesactualizada(error)) {
                await Swal.fire({ icon: 'warning', titleText: 'No se cambió el estado del proceso', text: mensajeApi(error) });
                return;
            }
            avisarError('No se pudo cambiar el estado del proceso', error);
        }
    };

    // Guardar contrato (agregar o actualizar), una vez a la vez.
    const handleSaveContrato = async () => {
        if (enviandoContrato.current) return;
        enviandoContrato.current = true;
        try {
            await enviarContrato();
        } finally {
            enviandoContrato.current = false;
        }
    };

    const enviarContrato = async () => {
        const locales = validarContrato(formContrato);
        setErroresContrato(locales);
        if (Object.keys(locales).length) {
            setContratoActiveTab('informacion');
            setFocoErrorContrato(n => n + 1);
            return;
        }

        setGuardandoContrato(true);
        let respuesta;
        try {
            respuesta = await axios.post('/guardarContrato', {
                formContrato: datosContrato(formContrato, editingContrato),
                anexos: anexos.map(anexo => ({
                    descripcion: anexo.descripcion,
                    nombreArchivo: anexo.nombreArchivo,
                    rutaArchivo: anexo.rutaArchivo,
                    fecha: anexo.fecha
                }))
            });
        } catch (error) {
            if (esEdicionDesactualizada(error)) {
                // Otra persona cambió el contrato: se avisa y se recarga la lista. El formulario
                // sigue abierto para copiar lo escrito; al reabrirlo trae lo último.
                await Promise.all([
                    listContratos(),
                    Swal.fire({ icon: 'warning', titleText: 'No se guardó tu cambio', text: mensajeApi(error) }),
                ]);
                return;
            }
            const errores = erroresGuardarContrato(error);
            if (!errores) {
                avisarError('No se pudo guardar el contrato', error);
                return;
            }
            // Los de campos con control, junto a su control; los demás (el proyecto, los anexos), en un aviso.
            if (Object.keys(errores.campos).length) {
                setErroresContrato(errores.campos);
                setContratoActiveTab('informacion');
                setFocoErrorContrato(n => n + 1);
            }
            if (errores.otros.length) {
                Swal.fire({ icon: 'error', titleText: 'No se pudo guardar el contrato', html: htmlErrores('Revisa lo siguiente:', errores.otros) });
            } else if (!Object.keys(errores.campos).length) {
                avisarError('No se pudo guardar el contrato', error);
            }
            return;
        } finally {
            setGuardandoContrato(false);
        }

        const { contrato, avisos = [], proponer_celebrado: proponerCelebrado } = respuesta.data ?? {};
        if (avisos.length) {
            await Swal.fire({ icon: 'warning', titleText: 'Contrato guardado, con avisos', html: htmlErrores('Revisa lo siguiente:', avisos) });
        } else {
            await Swal.fire({ icon: 'success', title: 'Contrato guardado correctamente' });
        }
        if (proponerCelebrado && contrato?.proceso_id) await proponerProcesoCelebrado(contrato);

        listContratos();
        listProyectos();
        handleCancelContratoForm();
    };

    // Cancelar edición/agregado de contrato
    const handleCancelContratoForm = () => {
        setShowContratoForm(false);
        setEditingContrato(null);
        setFormContrato(contratoVacio(proyectoContratos?.id));
        setErroresContrato({});
        setAnexos([]);
        setFormAnexo({ descripcion: '', archivo: null });
        setActasFinancieras([]);
```

En `resources/js/components/Parametros.jsx`, reemplazar (11 de 22):

```jsx
    // Abrir formulario para agregar contrato
    const handleAddNewContrato = () => {
        setEditingContrato(null);
        setFormContrato({
            n_contrato: '', objeto: '', contratante: '',
            contratista: '', valor: '', fecha_inicio: '',
            fecha_fin: '', interventoria: '',
            avance_financiero: '',
            avance_fisico: '', estado: '',
            proyecto: proyectoContratos.id || '',
            anticipo: false, porcentaje_anticipo: ''
        });
        setAnexos([]);
        setFormAnexo({ descripcion: '', archivo: null });
        setActasFinancieras([]);
```

por:

```jsx
    // Abrir formulario para agregar contrato
    const handleAddNewContrato = () => {
        setEditingContrato(null);
        setFormContrato(contratoVacio(proyectoContratos?.id));
        setErroresContrato({});
        setAnexos([]);
        setFormAnexo({ descripcion: '', archivo: null });
        setActasFinancieras([]);
```

En `resources/js/components/Parametros.jsx`, reemplazar (12 de 22):

```jsx
        setResumenModificacion(null);
        setContratoActiveTab('informacion');
        setShowContratoForm(true);
    };

    const listContratos = async () => {
```

por:

```jsx
        setResumenModificacion(null);
        setContratoActiveTab('informacion');
        setShowContratoForm(true);
        cargarProcesosContrato(proyectoContratos?.id);
    };

    const listContratos = async () => {
```

En `resources/js/components/Parametros.jsx`, reemplazar (13 de 22):

```jsx
            const actual = lista.find(c => c.id === formContrato.id);
            if (actual) {
                setModificaciones(actual.modificaciones || []);
                setFormContrato(prev => ({
                    ...prev,
                    valor: actual.valor ? formatCurrency(Number(actual.valor)) : '',
                    fecha_fin: actual.fecha_fin || '',
                }));
            }
        }
    };
```

por:

```jsx
            const actual = lista.find(c => c.id === formContrato.id);
            if (actual) {
                setModificaciones(actual.modificaciones || []);
                // Los vigentes recalculados salen del contrato recargado; lo escrito en el
                // formulario se conserva. Registrar una modificación no cambia la versión
                // (updated_at), y se guarda la que tenía al abrirse: así un cambio de otra
                // persona entretanto sigue dando 409.
                setEditingContrato(prev => ({ ...actual, updated_at: prev?.updated_at ?? actual.updated_at }));
            }
        }
    };
```

En `resources/js/components/Parametros.jsx`, reemplazar (14 de 22):

```jsx
    const handleDelete = async (id, type) => {
        Swal.fire({
            title: '¿Está seguro de que desea eliminar este elemento?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
```

por:

```jsx
    const handleDelete = async (id, type) => {
        Swal.fire({
            title: '¿Está seguro de que desea eliminar este elemento?',
            ...(type === 'proyectos' ? { text: 'Sus contratos no se borran: quedan sin proyecto.' } : {}),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
```

En `resources/js/components/Parametros.jsx`, reemplazar (15 de 22):

```jsx
        }));
    };

    const handleContratoValorChange = (e) => {
        const sanitized = sanitizeCurrencyInput(e.target.value);
        setFormContrato(prev => ({
            ...prev,
            valor: sanitized ? formatCurrencyInput(sanitized) : ''
        }));
    };

    const handleAddComponente = () => {
```

por:

```jsx
        }));
    };

    // Valor inicial con los miles agrupados ("1.234.567,89"), como el valor de los procesos.
    const handleContratoValorChange = (e) => {
        const { value } = e.target;
        setErroresContrato(prev => (prev.valor_inicial ? { ...prev, valor_inicial: undefined } : prev));
        setFormContrato(prev => ({ ...prev, valor_inicial: valorEnEdicion(value, prev.valor_inicial) }));
    };

    const handleAddComponente = () => {
```

En `resources/js/components/Parametros.jsx`, reemplazar (16 de 22):

```jsx
        // Resetear estado del formulario
        setShowContratoForm(false);
        setEditingContrato(null);
        setFormContrato({
            n_contrato: '', objeto: '',
            contratante: '', contratista: '',
            valor: '', fecha_inicio: '',
            fecha_fin: '', interventoria: '',
            avance_financiero: '',
            avance_fisico: '', estado: '',
            proyecto: item.id || '',
            anticipo: false, porcentaje_anticipo: ''
        });
        setShowContratosModal(true);
    };
```

por:

```jsx
        // Resetear estado del formulario
        setShowContratoForm(false);
        setEditingContrato(null);
        setFormContrato(contratoVacio(item.id));
        setErroresContrato({});
        setProcesosContrato(null);
        cargarTiposContrato();
        cargarProcesosContrato(item.id);
        setShowContratosModal(true);
    };
```

En `resources/js/components/Parametros.jsx`, reemplazar (17 de 22):

```jsx

    if (!seccion) return <Navigate to="/parametros/proyectos" replace />;

    return (
        <div className="parametros-container">
            <CabeceraSeccion seccion={seccion} />
```

por:

```jsx

    if (!seccion) return <Navigate to="/parametros/proyectos" replace />;

    // Formulario de contrato: proceso elegido, nota R4 y vigentes que difieren de los iniciales.
    const procesoDelContrato = procesoElegido(procesosContrato, editingContrato?.proceso ?? null, formContrato.proceso_id);
    const datosProcesoContrato = procesoDelContrato ? datosDelProceso(procesoDelContrato) : null;
    const notaProcesoContrato = notaProceso(procesosContrato, formContrato.proceso_id);
    const vigentesContrato = vigentesDistintos(editingContrato);

    // Propiedades comunes de un control del formulario de contrato: valor, cambio y aviso de error.
    const controlContrato = (campo) => ({
        id: campo,
        name: campo,
        value: formContrato[campo] ?? '',
        onChange: handleContratoChange,
        'aria-invalid': Boolean(erroresContrato[campo]),
        'aria-describedby': erroresContrato[campo] ? `${campo}-error` : undefined,
    });
    const campoContrato = (campo, etiqueta, control) => (
        <div className="form-group">
            <label htmlFor={campo}>{etiqueta}</label>
            {control}
            {erroresContrato[campo] && <small id={`${campo}-error`} className="ct-error-campo" role="alert">{erroresContrato[campo]}</small>}
        </div>
    );

    return (
        <div className="parametros-container">
            <CabeceraSeccion seccion={seccion} />
```

En `resources/js/components/Parametros.jsx`, reemplazar (18 de 22):

```jsx
                                                                <td>{contrato.n_contrato}</td>
                                                                <td>{contrato.objeto}</td>
                                                                <td>{formatCurrency(Number(contrato.valor))}</td>
                                                                <td>{estadosContratos.find(e => e.value === contrato.estado)?.label || contrato.estado}</td>
                                                                <td>
                                                                    <span className="anexos-count">
                                                                        {contrato.anexos ? contrato.anexos.length : 0} anexos
```

por:

```jsx
                                                                <td>{contrato.n_contrato}</td>
                                                                <td>{contrato.objeto}</td>
                                                                <td>{formatCurrency(Number(contrato.valor))}</td>
                                                                <td>{etiquetaEstadoContrato(contrato.estado)}</td>
                                                                <td>
                                                                    <span className="anexos-count">
                                                                        {contrato.anexos ? contrato.anexos.length : 0} anexos
```

En `resources/js/components/Parametros.jsx`, reemplazar (19 de 22):

```jsx
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleDeleteContrato(contrato.id)}
                                                                            className="btn-delete-contrato"
                                                                            title="Eliminar contrato"
                                                                        >
```

por:

```jsx
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleDeleteContrato(contrato)}
                                                                            className="btn-delete-contrato"
                                                                            title="Eliminar contrato"
                                                                        >
```

En `resources/js/components/Parametros.jsx`, reemplazar (20 de 22):

```jsx
                                    {contratoActiveTab === 'informacion' && (
                                        <div className="contratos-modal-section">
                                            <p className="contratos-info">
                                                Complete los datos del contrato. Los campos marcados con * son obligatorios.
                                            </p>
                                            <div className="contrato-form">
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="n_contrato">N° Contrato *</label>
                                                        <input type="text" id="n_contrato" name="n_contrato" value={formContrato.n_contrato} onChange={handleContratoChange} />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="valor">Valor *</label>
                                                        <input
                                                            type="text"
                                                            id="valor"
                                                            name="valor"
                                                            value={formContrato.valor}
                                                            onChange={handleContratoValorChange}
                                                            inputMode="decimal"
                                                            placeholder="$ 0"
                                                        />
                                                    </div>
                                                </div>
                                                <div className="form-group">
                                                    <label htmlFor="objeto">Objeto *</label>
                                                    <textarea
                                                        id="objeto"
                                                        name="objeto"
                                                        value={formContrato.objeto}
                                                        onChange={handleContratoChange}
                                                        rows="4"
                                                        placeholder="Ingrese el objeto del contrato"
                                                    />
                                                </div>
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="contratante">Contratante *</label>
                                                        <input type="text" id="contratante" name="contratante" value={formContrato.contratante} onChange={handleContratoChange} />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="contratista">Contratista *</label>
                                                        <input type="text" id="contratista" name="contratista" value={formContrato.contratista} onChange={handleContratoChange} />
                                                    </div>
                                                </div>
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="fecha_inicio">Fecha Inicio *</label>
                                                        <input type="date" id="fecha_inicio" name="fecha_inicio" value={formContrato.fecha_inicio} onChange={handleContratoChange} />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="fecha_fin">Fecha Fin *</label>
                                                        <input type="date" id="fecha_fin" name="fecha_fin" value={formContrato.fecha_fin} onChange={handleContratoChange} />
                                                    </div>
                                                </div>
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="interventoria">Interventoría *</label>
                                                        <input type="text" id="interventoria" name="interventoria" value={formContrato.interventoria} onChange={handleContratoChange} />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="estado">Estado *</label>
                                                        <select id="estado" name="estado" value={formContrato.estado} onChange={handleContratoChange}>
                                                            <option value="">Seleccione un estado</option>
                                                            {estadosContratos.map(e => (
                                                                <option key={e.value} value={e.value}>{e.label}</option>
                                                            ))}
                                                        </select>
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="avance_financiero">Avance Financiero</label>
                                                        <input type="text" disabled id="avance_financiero" name="avance_financiero" value={formContrato.avance_financiero} onChange={handleContratoChange} />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="avance_fisico">Avance Físico</label>
                                                        <div className="avance-fisico-container">
                                                            <input type="text" id="avance_fisico" disabled name="avance_fisico" value={formContrato.avance_fisico} onChange={handleContratoChange} />
                                                            <button type="button" className="btn-calcular-avance" title='Calcular Avance Físico' onClick={handleCalcularAvanceFisico}><FontAwesomeIcon icon={faCalculator} /></button>
                                                        </div>
                                                    </div>

                                                </div>
                                                <div className="form-row">
                                                    <div className="form-group anticipo-group">
```

por:

```jsx
                                    {contratoActiveTab === 'informacion' && (
                                        <div className="contratos-modal-section">
                                            <p className="contratos-info">
                                                Completa los datos del contrato. Los campos marcados con * son obligatorios.
                                            </p>
                                            <div className="contrato-form" ref={formularioContratoRef}>
                                                <div className="form-row">
                                                    {campoContrato('proceso_id', 'Proceso de contratación', (
                                                        <select {...controlContrato('proceso_id')}>
                                                            {opcionesProceso(procesosContrato, editingContrato?.proceso ?? null).map(o => (
                                                                <option key={o.valor} value={o.valor}>{o.etiqueta}</option>
                                                            ))}
                                                        </select>
                                                    ))}
                                                    {campoContrato('tipo_contrato_id', 'Tipo de contrato', (
                                                        <select {...controlContrato('tipo_contrato_id')}>
                                                            <option value="">Sin tipo</option>
                                                            {opcionesTipoContrato(tiposContrato, editingContrato).map(o => (
                                                                <option key={o.valor} value={o.valor}>{o.etiqueta}</option>
                                                            ))}
                                                        </select>
                                                    ))}
                                                </div>
                                                {notaProcesoContrato && <p className="contrato-nota">{notaProcesoContrato}</p>}
                                                {datosProcesoContrato && (
                                                    <dl className="contrato-proceso-datos" aria-label="Datos del proceso">
                                                        <div><dt>Número del proceso</dt><dd>{datosProcesoContrato.numero}</dd></div>
                                                        <div><dt>Modalidad</dt><dd>{datosProcesoContrato.modalidad}</dd></div>
                                                        <div><dt>Plataforma</dt><dd>{datosProcesoContrato.plataforma}</dd></div>
                                                        <div>
                                                            <dt>Enlace</dt>
                                                            <dd>
                                                                {datosProcesoContrato.enlace
                                                                    ? <a className="ct-enlace" href={datosProcesoContrato.enlace} target="_blank" rel="noopener noreferrer">Ver el proceso</a>
                                                                    : '—'}
                                                            </dd>
                                                        </div>
                                                    </dl>
                                                )}
                                                <div className="form-row">
                                                    {campoContrato('n_contrato', 'N° Contrato *', <input type="text" maxLength={100} {...controlContrato('n_contrato')} />)}
                                                    {campoContrato('estado', 'Estado *', (
                                                        <select {...controlContrato('estado')}>
                                                            <option value="">Elige el estado</option>
                                                            {ESTADOS_CONTRATO.map(([valor, etiqueta]) => (
                                                                <option key={valor} value={valor}>{etiqueta}</option>
                                                            ))}
                                                        </select>
                                                    ))}
                                                </div>
                                                {campoContrato('objeto', 'Objeto *', (
                                                    <textarea rows="4" maxLength={5000} placeholder="Escribe el objeto del contrato" {...controlContrato('objeto')} />
                                                ))}
                                                <div className="form-row">
                                                    {campoContrato('contratante', 'Contratante', <input type="text" maxLength={5000} {...controlContrato('contratante')} />)}
                                                    {campoContrato('contratante_nit', 'NIT de la entidad contratante', <input type="text" maxLength={30} {...controlContrato('contratante_nit')} />)}
                                                </div>
                                                <div className="form-row">
                                                    {campoContrato('contratista', 'Contratista', <input type="text" maxLength={5000} {...controlContrato('contratista')} />)}
                                                    {campoContrato('contratista_identificacion', 'Identificación del contratista', <input type="text" maxLength={30} {...controlContrato('contratista_identificacion')} />)}
                                                </div>
                                                <div className="form-row">
                                                    {campoContrato('valor_inicial', 'Valor inicial *', (
                                                        <input type="text" inputMode="decimal" placeholder="0" {...controlContrato('valor_inicial')} onChange={handleContratoValorChange} />
                                                    ))}
                                                    {vigentesContrato.valor && (
                                                        <div className="form-group">
                                                            <label htmlFor="valor_vigente">Valor vigente</label>
                                                            <input type="text" id="valor_vigente" disabled value={textoValor(valorVigente(formContrato, editingContrato))} aria-describedby="vigentes-nota" />
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="form-row">
                                                    {campoContrato('fecha_suscripcion', 'Fecha de suscripción', <input type="date" {...controlContrato('fecha_suscripcion')} />)}
                                                    {campoContrato('fecha_acta_inicio', 'Fecha del acta de inicio', <input type="date" {...controlContrato('fecha_acta_inicio')} />)}
                                                </div>
                                                <div className="form-row">
                                                    {campoContrato('fecha_inicio', 'Fecha de inicio', <input type="date" {...controlContrato('fecha_inicio')} />)}
                                                    {campoContrato('fecha_fin_inicial', 'Fecha fin inicial', <input type="date" {...controlContrato('fecha_fin_inicial')} />)}
                                                    {vigentesContrato.fechaFin && (
                                                        <div className="form-group">
                                                            <label htmlFor="fecha_fin_vigente">Fecha fin vigente</label>
                                                            <input type="date" id="fecha_fin_vigente" disabled value={String(editingContrato.fecha_fin).slice(0, 10)} aria-describedby="vigentes-nota" />
                                                        </div>
                                                    )}
                                                </div>
                                                {(vigentesContrato.valor || vigentesContrato.fechaFin) && (
                                                    <p id="vigentes-nota" className="contrato-nota">
                                                        Los vigentes suman al valor inicial y a la fecha fin inicial las adiciones y prórrogas de la pestaña Modificaciones; se recalculan al guardar.
                                                    </p>
                                                )}
                                                <div className="form-row">
                                                    {campoContrato('numero_rp', 'Número del RP', <input type="text" maxLength={40} {...controlContrato('numero_rp')} />)}
                                                    {campoContrato('fecha_rp', 'Fecha del RP', <input type="date" {...controlContrato('fecha_rp')} />)}
                                                </div>
                                                <div className="form-row">
                                                    {campoContrato('supervisor', 'Supervisor', <input type="text" maxLength={255} {...controlContrato('supervisor')} />)}
                                                    {campoContrato('interventoria', 'Interventoría', <input type="text" maxLength={5000} {...controlContrato('interventoria')} />)}
                                                </div>
                                                <div className="form-row">
                                                    {campoContrato('avance_financiero', 'Avance Financiero', <input type="text" disabled {...controlContrato('avance_financiero')} />)}
                                                    {campoContrato('avance_fisico', 'Avance Físico', (
                                                        <div className="avance-fisico-container">
                                                            <input type="text" disabled {...controlContrato('avance_fisico')} />
                                                            <button type="button" className="btn-calcular-avance" title='Calcular Avance Físico' onClick={handleCalcularAvanceFisico}><FontAwesomeIcon icon={faCalculator} /></button>
                                                        </div>
                                                    ))}
                                                </div>
                                                <div className="form-row">
                                                    <div className="form-group anticipo-group">
```

En `resources/js/components/Parametros.jsx`, reemplazar (21 de 22):

```jsx
                                                                value={formContrato.porcentaje_anticipo}
                                                                onChange={handleContratoChange}
                                                                min="0"
                                                                max="100"
                                                                disabled={!formContrato.anticipo}
                                                            />
                                                        </div>

                                                    </div>
```

por:

```jsx
                                                                value={formContrato.porcentaje_anticipo}
                                                                onChange={handleContratoChange}
                                                                min="0"
                                                                max="50"
                                                                disabled={!formContrato.anticipo}
                                                                aria-invalid={Boolean(erroresContrato.porcentaje_anticipo)}
                                                                aria-describedby={erroresContrato.porcentaje_anticipo ? 'porcentaje_anticipo-error' : undefined}
                                                            />
                                                            {erroresContrato.porcentaje_anticipo && <small id="porcentaje_anticipo-error" className="ct-error-campo" role="alert">{erroresContrato.porcentaje_anticipo}</small>}
                                                        </div>

                                                    </div>
```

En `resources/js/components/Parametros.jsx`, reemplazar (22 de 22):

```jsx
                                        type="button"
                                        onClick={handleSaveContrato}
                                        className="btn-save"
                                        disabled={!formContrato.n_contrato || !formContrato.objeto || parseCurrencyValue(formContrato.valor) <= 0 || !formContrato.estado}
                                    >
                                        <FontAwesomeIcon icon={faSave} /> {editingContrato ? 'Actualizar' : 'Guardar'} Contrato
                                    </button>
                                </>
                            )}
```

por:

```jsx
                                        type="button"
                                        onClick={handleSaveContrato}
                                        className="btn-save"
                                        disabled={guardandoContrato}
                                    >
                                        <FontAwesomeIcon icon={faSave} /> {guardandoContrato ? 'Guardando…' : `${editingContrato ? 'Actualizar' : 'Guardar'} Contrato`}
                                    </button>
                                </>
                            )}
```

En `resources/js/utils/avanceContratos.js`, reemplazar (1 de 1):

```js
// Parámetros, así que pueden estar desactualizadas respecto a las actas y avances
// ya registrados. Derivar del historial garantiza que el scatter y la gráfica de
// evolución muestren exactamente los mismos números.
export const aplanarContratos = (proyectos) => {
    if (!Array.isArray(proyectos)) return [];

    return proyectos.flatMap((proyecto) =>
        (proyecto.contratos || []).map((contrato) => {
            const serie = construirSerieTemporal(contrato);
            // Sin historial (o sin valor vigente) no hay nada que comparar: el
            // contrato queda fuera del scatter y se lista aparte.
```

por:

```js
// Parámetros, así que pueden estar desactualizadas respecto a las actas y avances
// ya registrados. Derivar del historial garantiza que el scatter y la gráfica de
// evolución muestren exactamente los mismos números.
//
// R16: los contratos anulados quedan fuera, como en la cadena presupuestal: no cuentan en los
// totales ni en los avances.
export const aplanarContratos = (proyectos) => {
    if (!Array.isArray(proyectos)) return [];

    return proyectos.flatMap((proyecto) =>
        (proyecto.contratos || []).filter((contrato) => contrato?.estado !== 'anulado').map((contrato) => {
            const serie = construirSerieTemporal(contrato);
            // Sin historial (o sin valor vigente) no hay nada que comparar: el
            // contrato queda fuera del scatter y se lista aparte.
```

En `resources/js/utils/cadenaPresupuestal.js`, reemplazar (1 de 2):

```js
 * Suma los tres niveles presupuestales de una lista de proyectos.
 *
 * - planeado:   suma de proyectos.presupuesto
 * - contratado: suma de contratos.valor (valor vigente, ya incluye adiciones)
 * - ejecutado:  suma de avancesFinancieros.valor_facturado
 *
 * Devuelve además los porcentajes de avance de la cadena y el excedente de
 * contratación sobre lo planeado (0 si no hay excedente).
```

por:

```js
 * Suma los tres niveles presupuestales de una lista de proyectos.
 *
 * - planeado:   suma de proyectos.presupuesto
 * - contratado: suma de contratos.valor (valor vigente, ya incluye adiciones), sin los anulados
 * - ejecutado:  suma de avancesFinancieros.valor_facturado de esos mismos contratos
 *
 * Devuelve además los porcentajes de avance de la cadena y el excedente de
 * contratación sobre lo planeado (0 si no hay excedente).
```

En `resources/js/utils/cadenaPresupuestal.js`, reemplazar (2 de 2):

```js
        planeado += numero(proyecto?.presupuesto);

        for (const contrato of (proyecto?.contratos || [])) {
            contratado += numero(contrato?.valor);

            for (const acta of (contrato?.avancesFinancieros || [])) {
```

por:

```js
        planeado += numero(proyecto?.presupuesto);

        for (const contrato of (proyecto?.contratos || [])) {
            // R16: un contrato anulado no cuenta, ni su valor ni lo facturado.
            if (contrato?.estado === 'anulado') continue;
            contratado += numero(contrato?.valor);

            for (const acta of (contrato?.avancesFinancieros || [])) {
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/contratos.test.js resources/js/utils/avanceContratos.test.js resources/js/utils/cadenaPresupuestal.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1008 passed.

Run: `npm test`
Expected: 515 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/js/utils/contratos.js resources/js/utils/contratos.test.js resources/css/Parametros.css resources/js/components/ContratosPanel.jsx resources/js/components/ModalProyecto.jsx resources/js/components/Parametros.jsx resources/js/utils/avanceContratos.js resources/js/utils/avanceContratos.test.js resources/js/utils/cadenaPresupuestal.js resources/js/utils/cadenaPresupuestal.test.js
git commit -m "feat: contract form with states, new fields, process link and preload"
```

---

### Tarea 7: Pantallas de procesos y tipos de contrato

Campos nuevos en el diálogo de procesos y el catálogo "Tipos de contrato" en Parámetros.

**Files:**
- Create: `resources/js/components/CatalogoTiposContrato.jsx`
- Modify: `resources/js/components/Parametros.jsx`
- Modify: `resources/js/components/ProcesosProyecto.jsx`
- Modify: `resources/js/utils/contratacion.js`
- Test: `resources/js/utils/contratacion.test.js`
- Modify: `resources/js/utils/navegacion.js`
- Test: `resources/js/utils/navegacion.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/contratacion.test.js`, reemplazar (1 de 10):

```js
    PROCESO_VACIO,
    REGLAS_ETAPA,
    REGLAS_MODALIDAD,
    datosCatalogo,
    erroresPorCampo,
    formularioCatalogo,
```

por:

```js
    PROCESO_VACIO,
    REGLAS_ETAPA,
    REGLAS_MODALIDAD,
    REGLAS_TIPO_CONTRATO,
    datosCatalogo,
    erroresPorCampo,
    formularioCatalogo,
```

En `resources/js/utils/contratacion.test.js`, reemplazar (2 de 10):

```js
    ordenarCatalogo,
    puedeAdministrarCatalogos,
    textoEstadoCatalogo,
    validarCatalogo,
    avisoCambioEstado,
    datosEstado,
```

por:

```js
    ordenarCatalogo,
    puedeAdministrarCatalogos,
    textoEstadoCatalogo,
    textoFecha,
    validarCatalogo,
    avisoCambioEstado,
    datosEstado,
```

En `resources/js/utils/contratacion.test.js`, reemplazar (3 de 10):

```js
    numeroProceso,
    opcionesConActual,
    opcionesEtapa,
    puedeEditarProcesos,
    textoContratos,
    textoValor,
```

por:

```js
    numeroProceso,
    opcionesConActual,
    opcionesEtapa,
    opcionesFuenteProceso,
    puedeEditarProcesos,
    textoContratos,
    textoValor,
```

En `resources/js/utils/contratacion.test.js`, reemplazar (4 de 10):

```js
        expect(validarCatalogo({ nombre: 'x'.repeat(121), descripcion: '' }, [], undefined, REGLAS_ETAPA)).toEqual({ nombre: 'El nombre admite hasta 120 caracteres.' });
    });

    it('las modalidades no tienen descripción que validar', () => {
        expect(validarCatalogo({ nombre: 'Otra', descripcion: 'd'.repeat(5000) }, [], undefined, REGLAS_MODALIDAD)).toEqual({});
    });
```

por:

```js
        expect(validarCatalogo({ nombre: 'x'.repeat(121), descripcion: '' }, [], undefined, REGLAS_ETAPA)).toEqual({ nombre: 'El nombre admite hasta 120 caracteres.' });
    });

    it('los tipos de contrato usan los mensajes y el largo del servidor', () => {
        const tipos = [{ id: 1, nombre: 'Interventoría' }];
        expect(validarCatalogo({ nombre: ' ' }, tipos, undefined, REGLAS_TIPO_CONTRATO)).toEqual({ nombre: 'Escribe el nombre del tipo de contrato.' });
        expect(validarCatalogo({ nombre: 'INTERVENTORIA' }, tipos, undefined, REGLAS_TIPO_CONTRATO)).toEqual({ nombre: 'Ya existe un tipo de contrato con ese nombre.' });
        expect(validarCatalogo({ nombre: 'Interventoría' }, tipos, 1, REGLAS_TIPO_CONTRATO)).toEqual({});
        expect(validarCatalogo({ nombre: 'x'.repeat(121) }, [], undefined, REGLAS_TIPO_CONTRATO)).toEqual({ nombre: 'El nombre admite hasta 120 caracteres.' });
    });

    it('las modalidades no tienen descripción que validar', () => {
        expect(validarCatalogo({ nombre: 'Otra', descripcion: 'd'.repeat(5000) }, [], undefined, REGLAS_MODALIDAD)).toEqual({});
    });
```

En `resources/js/utils/contratacion.test.js`, reemplazar (5 de 10):

```js
    });
});

describe('textoContratos', () => {
    it('cuenta los contratos vinculados', () => {
        expect(textoContratos(0)).toBe('Sin contratos');
```

por:

```js
    });
});

describe('textoFecha', () => {
    it('año-mes-día como día/mes/año, sin desfase de zona horaria', () => {
        expect(textoFecha('2026-03-20')).toBe('20/03/2026');
        expect(textoFecha('2026-01-01')).toBe('01/01/2026');
    });

    it('sin fecha o con otro formato, un guion', () => {
        expect(textoFecha(null)).toBe('—');
        expect(textoFecha('')).toBe('—');
        expect(textoFecha('mañana')).toBe('—');
    });
});

describe('textoContratos', () => {
    it('cuenta los contratos vinculados', () => {
        expect(textoContratos(0)).toBe('Sin contratos');
```

En `resources/js/utils/contratacion.test.js`, reemplazar (6 de 10):

```js
        url_proceso: null,
        entidad_contratante: { id: 3, nombre: 'Alcaldía' },
        monto: '1500000.00',
        estado: 'publicado',
    };

    it('nuevo: vacío', () => {
        expect(formularioProceso(null)).toEqual(PROCESO_VACIO);
        expect(CAMPOS_PROCESO).toEqual(['objeto', 'modalidad_id', 'plataforma', 'codigo_proceso', 'url_proceso', 'entidad_contratante_id', 'monto']);
    });

    it('editar: con los ids como texto para los selects y el valor formateado', () => {
```

por:

```js
        url_proceso: null,
        entidad_contratante: { id: 3, nombre: 'Alcaldía' },
        monto: '1500000.00',
        fuente: { id: 7, nombre: 'Regalías' },
        responsable: { id: 2, nombre: 'Ana Pérez' },
        fecha_apertura: '2026-03-01',
        fecha_cierre: '2026-03-20',
        observaciones: 'Pliegos publicados',
        estado: 'publicado',
    };

    it('nuevo: vacío', () => {
        expect(formularioProceso(null)).toEqual(PROCESO_VACIO);
        expect(CAMPOS_PROCESO).toEqual(['objeto', 'modalidad_id', 'plataforma', 'codigo_proceso', 'url_proceso', 'entidad_contratante_id', 'monto', 'fuente_id', 'responsable_id', 'fecha_apertura', 'fecha_cierre', 'observaciones']);
    });

    it('editar: con los ids como texto para los selects y el valor formateado', () => {
```

En `resources/js/utils/contratacion.test.js`, reemplazar (7 de 10):

```js
            url_proceso: '',
            entidad_contratante_id: '3',
            monto: '1.500.000',
        });
    });
```

por:

```js
            url_proceso: '',
            entidad_contratante_id: '3',
            monto: '1.500.000',
            fuente_id: '7',
            responsable_id: '2',
            fecha_apertura: '2026-03-01',
            fecha_cierre: '2026-03-20',
            observaciones: 'Pliegos publicados',
        });
    });
```

En `resources/js/utils/contratacion.test.js`, reemplazar (8 de 10):

```js
            plataforma: '', entidad_contratante_id: '', monto: '',
        });
    });
});

describe('validarProceso', () => {
```

por:

```js
            plataforma: '', entidad_contratante_id: '', monto: '',
        });
    });

    it('un proceso sin fuente, responsable, fechas ni observaciones los deja vacíos', () => {
        expect(formularioProceso({
            ...proceso, fuente: null, responsable: null, fecha_apertura: null, fecha_cierre: null, observaciones: null,
        })).toMatchObject({
            fuente_id: '', responsable_id: '', fecha_apertura: '', fecha_cierre: '', observaciones: '',
        });
    });
});

describe('validarProceso', () => {
```

En `resources/js/utils/contratacion.test.js`, reemplazar (9 de 10):

```js
        expect(validarProceso({ ...valido, monto: '9.999.999.999.999,99' })).toEqual({});
        expect(validarProceso({ ...valido, monto: '10.000.000.000.000' })).toEqual({ monto: 'El valor admite hasta 13 cifras enteras y 2 decimales.' });
    });
});

describe('datosProceso', () => {
```

por:

```js
        expect(validarProceso({ ...valido, monto: '9.999.999.999.999,99' })).toEqual({});
        expect(validarProceso({ ...valido, monto: '10.000.000.000.000' })).toEqual({ monto: 'El valor admite hasta 13 cifras enteras y 2 decimales.' });
    });

    it('las fechas son opcionales y el cierre no es anterior a la apertura', () => {
        expect(validarProceso({ ...valido, fecha_apertura: '2026-03-01', fecha_cierre: '2026-03-01' })).toEqual({});
        expect(validarProceso({ ...valido, fecha_cierre: '2026-03-01' })).toEqual({});
        expect(validarProceso({ ...valido, fecha_apertura: '2026-03-10', fecha_cierre: '2026-03-01' })).toEqual({
            fecha_cierre: 'La fecha de cierre no puede ser anterior a la de apertura.',
        });
    });

    it('rechaza una fecha que no sea año-mes-día y observaciones demasiado largas', () => {
        expect(validarProceso({ ...valido, fecha_apertura: '01/03/2026' })).toEqual({ fecha_apertura: 'Escribe la fecha de apertura como año-mes-día.' });
        expect(validarProceso({ ...valido, fecha_cierre: '2026-02-30' })).toEqual({ fecha_cierre: 'Escribe la fecha de cierre como año-mes-día.' });
        expect(validarProceso({ ...valido, observaciones: 'x'.repeat(5000) })).toEqual({});
        expect(validarProceso({ ...valido, observaciones: 'x'.repeat(5001) })).toEqual({ observaciones: 'Las observaciones admiten hasta 5000 caracteres.' });
    });
});

describe('opcionesFuenteProceso', () => {
    const fuentes = [
        { id: 1, nombre: 'Propios', activo: true },
        { id: 2, nombre: 'Regalías', activo: true },
        { id: 3, nombre: 'Antigua', activo: false },
        { id: 4, nombre: 'Cofinanciación', activo: true },
    ];
    const nombres = (lista) => lista.map((o) => o.etiqueta);

    it('sin fuentes en el proyecto, una sola lista con las activas', () => {
        expect(opcionesFuenteProceso(fuentes, null, [])).toEqual({
            delProyecto: [],
            otras: [
                { valor: '1', etiqueta: 'Propios' },
                { valor: '2', etiqueta: 'Regalías' },
                { valor: '4', etiqueta: 'Cofinanciación' },
            ],
        });
    });

    it('las del proyecto primero, aparte de las demás', () => {
        const opciones = opcionesFuenteProceso(fuentes, null, [4, '2']);
        expect(nombres(opciones.delProyecto)).toEqual(['Regalías', 'Cofinanciación']);
        expect(nombres(opciones.otras)).toEqual(['Propios']);
    });

    it('una fuente inactiva del proyecto no se ofrece si el proceso no la tiene', () => {
        const opciones = opcionesFuenteProceso(fuentes, null, [3, 1]);
        expect(nombres(opciones.delProyecto)).toEqual(['Propios']);
        expect(nombres(opciones.otras)).toEqual(['Regalías', 'Cofinanciación']);
    });

    it('la actual inactiva se conserva marcada, en el grupo que le toca', () => {
        expect(nombres(opcionesFuenteProceso(fuentes, { id: 3, nombre: 'Antigua' }, []).otras)).toEqual(['Propios', 'Regalías', 'Cofinanciación', 'Antigua (inactiva)']);
        expect(nombres(opcionesFuenteProceso(fuentes, { id: 3, nombre: 'Antigua' }, [3]).delProyecto)).toEqual(['Antigua (inactiva)']);
    });

    it('con el catálogo sin cargar ofrece la actual, y sin ella, nada', () => {
        expect(opcionesFuenteProceso(null, { id: 3, nombre: 'Antigua' }, [1])).toEqual({ delProyecto: [], otras: [{ valor: '3', etiqueta: 'Antigua' }] });
        expect(opcionesFuenteProceso(null, null, [1])).toEqual({ delProyecto: [], otras: [] });
    });
});

describe('datosProceso', () => {
```

En `resources/js/utils/contratacion.test.js`, reemplazar (10 de 10):

```js
        expect(datosProceso({
            objeto: '  Obra ', modalidad_id: '2', plataforma: 'ninguna', codigo_proceso: ' ', url_proceso: '', entidad_contratante_id: '', monto: '',
        })).toEqual({
            objeto: 'Obra', modalidad_id: 2, plataforma: 'ninguna', codigo_proceso: null, url_proceso: null, entidad_contratante_id: null, monto: null,
        });
    });

    it('los ids como números y el valor como texto decimal', () => {
        expect(datosProceso({
            objeto: 'Obra', modalidad_id: '2', plataforma: 'secop_i', codigo_proceso: 'SA-3', url_proceso: ' https://secop.gov.co/3 ', entidad_contratante_id: '9', monto: '1.234,5',
        })).toEqual({
            objeto: 'Obra', modalidad_id: 2, plataforma: 'secop_i', codigo_proceso: 'SA-3', url_proceso: 'https://secop.gov.co/3', entidad_contratante_id: 9, monto: '1234.5',
        });
    });
```

por:

```js
        expect(datosProceso({
            objeto: '  Obra ', modalidad_id: '2', plataforma: 'ninguna', codigo_proceso: ' ', url_proceso: '', entidad_contratante_id: '', monto: '',
        })).toEqual({
            objeto: 'Obra',
            modalidad_id: 2,
            plataforma: 'ninguna',
            codigo_proceso: null,
            url_proceso: null,
            entidad_contratante_id: null,
            monto: null,
            fuente_id: null,
            responsable_id: null,
            fecha_apertura: null,
            fecha_cierre: null,
            observaciones: null,
        });
    });

    it('los ids como números y el valor como texto decimal', () => {
        expect(datosProceso({
            objeto: 'Obra', modalidad_id: '2', plataforma: 'secop_i', codigo_proceso: 'SA-3', url_proceso: ' https://secop.gov.co/3 ', entidad_contratante_id: '9', monto: '1.234,5',
            fuente_id: '7', responsable_id: '2', fecha_apertura: '2026-03-01', fecha_cierre: ' 2026-03-20 ', observaciones: ' Con anexos ',
        })).toEqual({
            objeto: 'Obra',
            modalidad_id: 2,
            plataforma: 'secop_i',
            codigo_proceso: 'SA-3',
            url_proceso: 'https://secop.gov.co/3',
            entidad_contratante_id: 9,
            monto: '1234.5',
            fuente_id: 7,
            responsable_id: 2,
            fecha_apertura: '2026-03-01',
            fecha_cierre: '2026-03-20',
            observaciones: 'Con anexos',
        });
    });
```

En `resources/js/utils/navegacion.test.js`, reemplazar (1 de 3):

```js
        expect(seccionDesdeSlug(undefined)).toBeNull();
    });

    it('modalidades y etapas de contratación son catálogos con componente propio', () => {
        expect(seccionDesdeSlug('modalidades')).toMatchObject({ tab: 'modalidades', titulo: 'Modalidades', grupo: GRUPO.CATALOGOS, propia: true });
        expect(seccionDesdeSlug('etapas-contratacion')).toMatchObject({ tab: 'etapas-contratacion', titulo: 'Etapas de contratación', grupo: GRUPO.CATALOGOS, propia: true });
        expect(SECCIONES_PARAMETROS.filter((s) => s.propia).map((s) => s.slug)).toEqual(['modalidades', 'etapas-contratacion']);
    });

    it('cada sección tiene un icono registrado', () => {
```

por:

```js
        expect(seccionDesdeSlug(undefined)).toBeNull();
    });

    it('modalidades, etapas y tipos de contrato son catálogos con componente propio', () => {
        expect(seccionDesdeSlug('modalidades')).toMatchObject({ tab: 'modalidades', titulo: 'Modalidades', grupo: GRUPO.CATALOGOS, propia: true });
        expect(seccionDesdeSlug('etapas-contratacion')).toMatchObject({ tab: 'etapas-contratacion', titulo: 'Etapas de contratación', grupo: GRUPO.CATALOGOS, propia: true });
        expect(SECCIONES_PARAMETROS.filter((s) => s.propia).map((s) => s.slug)).toEqual(['modalidades', 'etapas-contratacion', 'tipos-contrato']);
        expect(seccionDesdeSlug('tipos-contrato')).toMatchObject({ tab: 'tipos-contrato', titulo: 'Tipos de contrato', grupo: GRUPO.CATALOGOS, propia: true });
    });

    it('cada sección tiene un icono registrado', () => {
```

En `resources/js/utils/navegacion.test.js`, reemplazar (2 de 3):

```js
    it('lleva a cada catálogo con una sola ruta directa', () => {
        const rutas = DESTINOS.map((d) => d.ruta);

        ['municipios', 'estados', 'fases', 'tipos-evento', 'prioridades', 'responsables', 'entidades', 'fuentes-financiacion', 'sectores', 'modalidades', 'etapas-contratacion'].forEach((slug) => {
            expect(rutas).toContain(`/parametros/${slug}`);
        });
    });
```

por:

```js
    it('lleva a cada catálogo con una sola ruta directa', () => {
        const rutas = DESTINOS.map((d) => d.ruta);

        ['municipios', 'estados', 'fases', 'tipos-evento', 'prioridades', 'responsables', 'entidades', 'fuentes-financiacion', 'sectores', 'modalidades', 'etapas-contratacion', 'tipos-contrato'].forEach((slug) => {
            expect(rutas).toContain(`/parametros/${slug}`);
        });
    });
```

En `resources/js/utils/navegacion.test.js`, reemplazar (3 de 3):

```js
        expect(titulos('secop')).toEqual(expect.arrayContaining(['Modalidades', 'Etapas de contratación']));
        expect(titulos('licitación')).toEqual(expect.arrayContaining(['Modalidades', 'Etapas de contratación']));
        expect(titulos('minima cuantia')).toContain('Modalidades');
    });

    it('encuentra proyectos por nombre o municipio y los deja después de los destinos', () => {
```

por:

```js
        expect(titulos('secop')).toEqual(expect.arrayContaining(['Modalidades', 'Etapas de contratación']));
        expect(titulos('licitación')).toEqual(expect.arrayContaining(['Modalidades', 'Etapas de contratación']));
        expect(titulos('minima cuantia')).toContain('Modalidades');
        expect(titulos('interventoria')).toContain('Tipos de contrato');
    });

    it('encuentra proyectos por nombre o municipio y los deja después de los destinos', () => {
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/contratacion.test.js resources/js/utils/navegacion.test.js`
Expected: FALLAN 18 y pasan 75: el formulario del proceso todavía no maneja fuente, responsable, fechas ni observaciones, no existen `textoFecha` ni las reglas de los tipos de contrato, y la navegación no tiene la sección "Tipos de contrato".

- [ ] **Paso 3: Implementar**

Crear `resources/js/components/CatalogoTiposContrato.jsx`:

```jsx
import React, { useCallback, useEffect, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faEdit, faPlus } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
import { mensajeApi } from '../utils/plantillasChecklist';
import {
    REGLAS_TIPO_CONTRATO, datosCatalogo, erroresPorCampo, ordenarCatalogo, puedeAdministrarCatalogos, textoEstadoCatalogo,
} from '../utils/contratacion';
import FormularioCatalogo from './FormularioCatalogo';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';

/**
 * Parámetros → Tipos de contrato. Los tipos no se eliminan: se desactivan, y un contrato conserva
 * el suyo aunque se desactive. Solo un Administrador modifica.
 */
const CatalogoTiposContrato = ({ user }) => {
    const [tipos, setTipos] = useState(null);
    const [error, setError] = useState(null);
    const [editando, setEditando] = useState(null); // null, 'nueva' o el tipo
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    // data-foco del control que debe recuperar el foco cuando la lista vuelva a pintarse: el botón
    // que abrió el formulario o el de activar o desactivar que se usó.
    const retorno = useRef(null);
    const administra = puedeAdministrarCatalogos(user);

    const cargar = useCallback(async () => {
        try {
            const { data } = await axios.get('/tiposProcesos');
            setTipos(ordenarCatalogo(data));
            setError(null);
        } catch (e) {
            setError(mensajeApi(e));
        }
    }, []);

    useEffect(() => { cargar(); }, [cargar]);

    // Cuando se cierra el formulario o termina el cambio de estado, el foco vuelve a su control. Se
    // espera a que nada esté ocupado: un botón deshabilitado no recibe el foco.
    useEffect(() => {
        if (editando || ocupado || !retorno.current) return;
        contenedor.current?.querySelector(`[data-foco="${retorno.current}"]:not(:disabled)`)?.focus();
        retorno.current = null;
    }, [editando, ocupado, tipos]);

    const abrir = (tipo) => {
        retorno.current = tipo === 'nueva' ? 'nueva' : `editar:${tipo.id}`;
        setEditando(tipo);
    };

    const guardar = (formulario) => ejecutar(async () => {
        const item = editando === 'nueva' ? null : editando;
        try {
            await axios.post('/guardarTipoProceso', datosCatalogo(formulario, item));
            setEditando(null);
            await cargar();
            return null;
        } catch (e) {
            const errores = erroresPorCampo(e, ['nombre']);
            if (!errores || errores.otros.length || !Object.keys(errores.campos).length) avisarError('No se pudo guardar el tipo de contrato', e);
            return errores?.campos ?? null;
        }
    });

    const alternarActiva = (tipo) => ejecutar(async () => {
        retorno.current = `activar:${tipo.id}`;
        if (tipo.activo) {
            const { isConfirmed } = await Swal.fire({
                icon: 'warning',
                titleText: `¿Desactivar "${tipo.nombre}"?`,
                text: 'No se ofrecerá en contratos nuevos. Los contratos que ya lo tienen lo conservan.',
                showCancelButton: true,
                confirmButtonText: 'Sí, desactivar',
                cancelButtonText: 'Cancelar',
            });
            if (!isConfirmed) return;
        }
        try {
            await axios.post('/activarTipoProceso', { id: tipo.id });
            await cargar();
        } catch (e) {
            avisarError('No se pudo cambiar el estado', e);
        }
    });

    if (editando) {
        return (
            <FormularioCatalogo
                titulo={editando === 'nueva' ? 'Nuevo tipo de contrato' : 'Editar tipo de contrato'}
                item={editando === 'nueva' ? null : editando}
                items={tipos}
                reglas={REGLAS_TIPO_CONTRATO}
                guardando={ocupado}
                onGuardar={guardar}
                onCancelar={() => setEditando(null)}
            />
        );
    }

    return (
        <div className="ct-seccion" ref={contenedor}>
            <div className="ct-barra">
                <p className="pl-ayuda">
                    {administra
                        ? 'Los tipos de contrato no se eliminan: desactiva los que ya no uses. Los contratos conservan el suyo.'
                        : 'Solo un Administrador puede modificar los tipos de contrato.'}
                </p>
                {administra && (
                    <button type="button" className="gp-boton gp-boton-primario" data-foco="nueva" onClick={() => abrir('nueva')} disabled={ocupado || tipos === null}>
                        <FontAwesomeIcon icon={faPlus} /> Nuevo tipo de contrato
                    </button>
                )}
            </div>

            {error && (
                <p className="pl-error" role="alert">
                    {error} <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>
                </p>
            )}
            {tipos === null && !error && <p className="gp-vacio" role="status">Cargando tipos de contrato…</p>}
            {tipos !== null && tipos.length === 0 && <p className="gp-vacio">Aún no hay tipos de contrato.</p>}

            {tipos !== null && tipos.length > 0 && (
                <div className="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Estado</th>
                                {administra && <th>Acciones</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {tipos.map((t) => (
                                <tr key={t.id} className={t.activo ? undefined : 'ct-inactiva'}>
                                    <td data-etiqueta="Nombre">{t.nombre}</td>
                                    <td data-etiqueta="Estado">
                                        <span className={`status-badge ${t.activo ? 'active' : 'inactive'}`}>{textoEstadoCatalogo(t.activo, true)}</span>
                                    </td>
                                    {administra && (
                                        <td className="celda-acciones">
                                            <div className="action-buttons">
                                                <button type="button" className="btn-edit" data-foco={`editar:${t.id}`} onClick={() => abrir(t)} disabled={ocupado} aria-label={`Editar ${t.nombre}`}>
                                                    <FontAwesomeIcon icon={faEdit} /> Editar
                                                </button>
                                                <button
                                                    type="button"
                                                    className={`btn-toggle ${t.activo ? 'deactivate' : 'activate'}`}
                                                    data-foco={`activar:${t.id}`}
                                                    onClick={() => alternarActiva(t)}
                                                    disabled={ocupado}
                                                    aria-label={`${t.activo ? 'Desactivar' : 'Activar'} ${t.nombre}`}
                                                >
                                                    {t.activo ? 'Desactivar' : 'Activar'}
                                                </button>
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
};

export default CatalogoTiposContrato;
```

En `resources/js/components/Parametros.jsx`, reemplazar (1 de 2):

```jsx
import { htmlErrores, mensajeApi } from '../utils/plantillasChecklist';
import CatalogoModalidades from './CatalogoModalidades';
import CatalogoEtapasContratacion from './CatalogoEtapasContratacion';
import ProcesosProyecto from './ProcesosProyecto';

// Componente auxiliar: escucha clics en el mapa y reporta la coordenada al padre.
```

por:

```jsx
import { htmlErrores, mensajeApi } from '../utils/plantillasChecklist';
import CatalogoModalidades from './CatalogoModalidades';
import CatalogoEtapasContratacion from './CatalogoEtapasContratacion';
import CatalogoTiposContrato from './CatalogoTiposContrato';
import ProcesosProyecto from './ProcesosProyecto';

// Componente auxiliar: escucha clics en el mapa y reporta la coordenada al padre.
```

En `resources/js/components/Parametros.jsx`, reemplazar (2 de 2):

```jsx
const SECCIONES_PROPIAS = {
    modalidades: CatalogoModalidades,
    'etapas-contratacion': CatalogoEtapasContratacion,
};

const CabeceraSeccion = ({ seccion }) => (
```

por:

```jsx
const SECCIONES_PROPIAS = {
    modalidades: CatalogoModalidades,
    'etapas-contratacion': CatalogoEtapasContratacion,
    'tipos-contrato': CatalogoTiposContrato,
};

const CabeceraSeccion = ({ seccion }) => (
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (1 de 9):

```jsx
import { avisarError } from '../utils/avisosPlantillas';
import { mensajeApi } from '../utils/plantillasChecklist';
import {
    CAMPOS_PROCESO, ESTADOS_PROCESO, MAXIMO_OBSERVACION, PLATAFORMAS, avisoCambioEstado, datosEstado, datosProceso,
    erroresPorCampo, esEdicionDesactualizada, etiquetaEstadoProceso, etiquetaPlataforma, formularioProceso,
    indiceFocoAtrapado, numeroProceso, opcionesConActual, ordenarCatalogo, puedeEditarProcesos, textoContratos, textoValor,
    tonoEstado, validarProceso, valorEnEdicion,
} from '../utils/contratacion';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';

const ENFOCABLES = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
```

por:

```jsx
import { avisarError } from '../utils/avisosPlantillas';
import { mensajeApi } from '../utils/plantillasChecklist';
import {
    CAMPOS_PROCESO, ESTADOS_PROCESO, MAXIMO_OBSERVACION, MAXIMO_OBSERVACIONES_PROCESO, PLATAFORMAS, avisoCambioEstado, datosEstado, datosProceso,
    erroresPorCampo, esEdicionDesactualizada, etiquetaEstadoProceso, etiquetaPlataforma, formularioProceso,
    indiceFocoAtrapado, numeroProceso, opcionesConActual, opcionesFuenteProceso, ordenarCatalogo, puedeEditarProcesos,
    textoContratos, textoFecha, textoValor, tonoEstado, validarProceso, valorEnEdicion,
} from '../utils/contratacion';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';

// Catálogos del formulario: su ruta y cómo se guarda cada respuesta.
const comoLista = (datos) => (Array.isArray(datos) ? datos : []);
const CATALOGOS = [
    { clave: 'modalidades', ruta: '/modalidades', guardar: (d, { setModalidades }) => setModalidades(ordenarCatalogo(d)) },
    { clave: 'entidades', ruta: '/entidades', guardar: (d, { setEntidades }) => setEntidades(comoLista(d)) },
    { clave: 'fuentes', ruta: '/fuentesFinanciacion', guardar: (d, { setFuentes }) => setFuentes(comoLista(d)) },
    { clave: 'responsables', ruta: '/responsables', guardar: (d, { setResponsables }) => setResponsables(comoLista(d)) },
];

const ENFOCABLES = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (2 de 9):

```jsx
 * Formulario de un proceso. Valida antes de enviar con las reglas del servidor; `onGuardar`
 * devuelve los errores por campo del servidor, o null. Escape cancela sin cerrar el diálogo.
 */
const FormularioProceso = ({ proceso, modalidades, entidades, guardando, onGuardar, onCancelar }) => {
    const [f, setF] = useState(() => formularioProceso(proceso));
    const [errores, setErrores] = useState({});
    const formulario = useRef(null);
```

por:

```jsx
 * Formulario de un proceso. Valida antes de enviar con las reglas del servidor; `onGuardar`
 * devuelve los errores por campo del servidor, o null. Escape cancela sin cerrar el diálogo.
 */
const FormularioProceso = ({ proceso, fuenteIds, modalidades, entidades, fuentes, responsables, guardando, onGuardar, onCancelar }) => {
    const [f, setF] = useState(() => formularioProceso(proceso));
    const [errores, setErrores] = useState({});
    const formulario = useRef(null);
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (3 de 9):

```jsx
        if (!guardando) onCancelar();
    };

    // Propiedades comunes de cada control: nombre, valor, aviso de error y su descripción.
    const control = (campo) => ({
        name: campo,
```

por:

```jsx
        if (!guardando) onCancelar();
    };

    const fuentesProceso = opcionesFuenteProceso(fuentes, proceso?.fuente, fuenteIds);

    // Propiedades comunes de cada control: nombre, valor, aviso de error y su descripción.
    const control = (campo) => ({
        name: campo,
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (4 de 9):

```jsx
            {campo('monto', 'Valor en pesos (opcional)', (
                <input inputMode="decimal" placeholder="0" {...control('monto')} onChange={(e) => cambiar('monto', valorEnEdicion(e.target.value, f.monto))} />
            ))}
            <div className="pl-formulario-acciones">
                <button type="button" className="gp-boton" onClick={onCancelar} disabled={guardando}>Cancelar</button>
                <button type="submit" className="gp-boton gp-boton-primario" disabled={guardando}>Guardar proceso</button>
```

por:

```jsx
            {campo('monto', 'Valor en pesos (opcional)', (
                <input inputMode="decimal" placeholder="0" {...control('monto')} onChange={(e) => cambiar('monto', valorEnEdicion(e.target.value, f.monto))} />
            ))}
            {campo('fuente_id', 'Fuente de financiación (opcional)', (
                <select {...control('fuente_id')}>
                    <option value="">Sin fuente</option>
                    {fuentesProceso.delProyecto.length > 0 && (
                        <optgroup label="Fuentes del proyecto">
                            {fuentesProceso.delProyecto.map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                        </optgroup>
                    )}
                    {fuentesProceso.delProyecto.length > 0 && fuentesProceso.otras.length > 0 ? (
                        <optgroup label="Otras fuentes">
                            {fuentesProceso.otras.map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                        </optgroup>
                    ) : fuentesProceso.otras.map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                </select>
            ))}
            {campo('responsable_id', 'Responsable (opcional)', (
                <select {...control('responsable_id')}>
                    <option value="">Sin responsable</option>
                    {opcionesConActual(responsables, proceso?.responsable).map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                </select>
            ))}
            {campo('fecha_apertura', 'Fecha de apertura (opcional)', <input type="date" {...control('fecha_apertura')} />)}
            {campo('fecha_cierre', 'Fecha de cierre (opcional)', <input type="date" {...control('fecha_cierre')} />)}
            {campo('observaciones', 'Observaciones (opcional)', (
                <textarea rows={3} maxLength={MAXIMO_OBSERVACIONES_PROCESO} {...control('observaciones')} />
            ), true)}
            <div className="pl-formulario-acciones">
                <button type="button" className="gp-boton" onClick={onCancelar} disabled={guardando}>Cancelar</button>
                <button type="submit" className="gp-boton gp-boton-primario" disabled={guardando}>Guardar proceso</button>
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (5 de 9):

```jsx
    const [error, setError] = useState(null);
    const [modalidades, setModalidades] = useState(null);
    const [entidades, setEntidades] = useState(null);
    const [errorCatalogos, setErrorCatalogos] = useState(null);
    // null; { tipo: 'proceso', proceso: null | proceso } o { tipo: 'estado', proceso }.
    const [modo, setModo] = useState(null);
```

por:

```jsx
    const [error, setError] = useState(null);
    const [modalidades, setModalidades] = useState(null);
    const [entidades, setEntidades] = useState(null);
    const [fuentes, setFuentes] = useState(null);
    const [responsables, setResponsables] = useState(null);
    const [errorCatalogos, setErrorCatalogos] = useState(null);
    // null; { tipo: 'proceso', proceso: null | proceso } o { tipo: 'estado', proceso }.
    const [modo, setModo] = useState(null);
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (6 de 9):

```jsx
    }, [cargar]);

    // Los catálogos se cargan al abrir y, con "Reintentar", se piden otra vez los que fallaron. Sin
    // modalidades no se crea ni edita; sin entidades el formulario conserva la que el proceso tiene.
    const cargarCatalogos = useCallback(async ({ conModalidades = true, conEntidades = true } = {}) => {
        const numero = ++turnoCatalogos.current;
        setErrorCatalogos(null);
        const [m, e] = await Promise.allSettled([
            conModalidades ? axios.get('/modalidades') : null,
            conEntidades ? axios.get('/entidades') : null,
        ]);
        if (numero !== turnoCatalogos.current) return;
        if (conModalidades && m.status === 'fulfilled') setModalidades(ordenarCatalogo(m.value.data));
        if (conEntidades && e.status === 'fulfilled') setEntidades(Array.isArray(e.value.data) ? e.value.data : []);
        const fallo = [m, e].find((r) => r.status === 'rejected');
        if (fallo) setErrorCatalogos(`No se pudieron cargar las modalidades o las entidades: ${mensajeApi(fallo.reason)}`);
    }, []);

    useEffect(() => {
```

por:

```jsx
    }, [cargar]);

    // Los catálogos se cargan al abrir y, con "Reintentar", se piden otra vez los que fallaron. Sin
    // modalidades no se crea ni edita; sin los demás el formulario conserva lo que el proceso tiene.
    const cargarCatalogos = useCallback(async (pendientes = CATALOGOS.map((c) => c.clave)) => {
        const numero = ++turnoCatalogos.current;
        setErrorCatalogos(null);
        const pedidos = CATALOGOS.filter((c) => pendientes.includes(c.clave));
        const respuestas = await Promise.allSettled(pedidos.map((c) => axios.get(c.ruta)));
        if (numero !== turnoCatalogos.current) return;
        pedidos.forEach((c, i) => {
            if (respuestas[i].status === 'fulfilled') c.guardar(respuestas[i].value.data, { setModalidades, setEntidades, setFuentes, setResponsables });
        });
        const fallo = respuestas.find((r) => r.status === 'rejected');
        if (fallo) setErrorCatalogos(`No se pudieron cargar las modalidades, entidades, fuentes o responsables: ${mensajeApi(fallo.reason)}`);
    }, []);

    useEffect(() => {
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (7 de 9):

```jsx
    // El botón desaparece mientras se reintenta: el foco pasa al diálogo para no perderse.
    const reintentarCatalogos = () => {
        contenedor.current?.closest('[role="dialog"]')?.focus();
        cargarCatalogos({ conModalidades: modalidades === null, conEntidades: entidades === null });
    };

    // Al volver a la lista, el foco regresa al botón que abrió el formulario (o al diálogo).
```

por:

```jsx
    // El botón desaparece mientras se reintenta: el foco pasa al diálogo para no perderse.
    const reintentarCatalogos = () => {
        contenedor.current?.closest('[role="dialog"]')?.focus();
        const cargados = { modalidades, entidades, fuentes, responsables };
        cargarCatalogos(CATALOGOS.map((c) => c.clave).filter((clave) => cargados[clave] === null));
    };

    // Al volver a la lista, el foco regresa al botón que abrió el formulario (o al diálogo).
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (8 de 9):

```jsx
                {editandoProceso ? (
                    <FormularioProceso
                        proceso={modo.proceso}
                        modalidades={modalidades}
                        entidades={entidades}
                        guardando={ocupado}
                        onGuardar={guardar}
                        onCancelar={() => setModo(null)}
```

por:

```jsx
                {editandoProceso ? (
                    <FormularioProceso
                        proceso={modo.proceso}
                        fuenteIds={proyecto.fuente_ids ?? []}
                        modalidades={modalidades}
                        entidades={entidades}
                        fuentes={fuentes}
                        responsables={responsables}
                        guardando={ocupado}
                        onGuardar={guardar}
                        onCancelar={() => setModo(null)}
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (9 de 9):

```jsx
                                            <div><dt>Número</dt><dd><NumeroProceso proceso={p} /></dd></div>
                                            <div><dt>Entidad contratante</dt><dd>{p.entidad_contratante?.nombre ?? '—'}</dd></div>
                                            <div><dt>Valor</dt><dd className="ct-valor">{textoValor(p.monto)}</dd></div>
                                            <div><dt>Contratos vinculados</dt><dd>{textoContratos(p.contratos)}</dd></div>
                                        </dl>
                                        {modo?.tipo === 'estado' && modo.proceso.id === p.id ? (
                                            <FormularioEstado proceso={p} guardando={ocupado} onGuardar={cambiarEstado} onCancelar={() => setModo(null)} />
                                        ) : edita && (
```

por:

```jsx
                                            <div><dt>Número</dt><dd><NumeroProceso proceso={p} /></dd></div>
                                            <div><dt>Entidad contratante</dt><dd>{p.entidad_contratante?.nombre ?? '—'}</dd></div>
                                            <div><dt>Valor</dt><dd className="ct-valor">{textoValor(p.monto)}</dd></div>
                                            <div><dt>Fuente de financiación</dt><dd>{p.fuente?.nombre ?? '—'}</dd></div>
                                            <div><dt>Responsable</dt><dd>{p.responsable?.nombre ?? '—'}</dd></div>
                                            <div><dt>Apertura</dt><dd>{textoFecha(p.fecha_apertura)}</dd></div>
                                            <div><dt>Cierre</dt><dd>{textoFecha(p.fecha_cierre)}</dd></div>
                                            <div><dt>Contratos vinculados</dt><dd>{textoContratos(p.contratos)}</dd></div>
                                        </dl>
                                        {p.observaciones && <p className="pl-ayuda">{p.observaciones}</p>}
                                        {modo?.tipo === 'estado' && modo.proceso.id === p.id ? (
                                            <FormularioEstado proceso={p} guardando={ocupado} onGuardar={cambiarEstado} onCancelar={() => setModo(null)} />
                                        ) : edita && (
```

En `resources/js/utils/contratacion.js`, reemplazar (1 de 7):

```js
    repetido: 'Ya existe una etapa con ese nombre.',
};

export const CATALOGO_VACIO = { nombre: '', descripcion: '' };

export const formularioCatalogo = (item) => ({
```

por:

```js
    repetido: 'Ya existe una etapa con ese nombre.',
};

export const REGLAS_TIPO_CONTRATO = {
    maximoNombre: 120,
    faltaNombre: 'Escribe el nombre del tipo de contrato.',
    repetido: 'Ya existe un tipo de contrato con ese nombre.',
};

export const CATALOGO_VACIO = { nombre: '', descripcion: '' };

export const formularioCatalogo = (item) => ({
```

En `resources/js/utils/contratacion.js`, reemplazar (2 de 7):

```js
    return { campos: enCampos, otros };
};

/** Modalidades y etapas son femeninas: "Activa" o "Inactiva". */
export const textoEstadoCatalogo = (activo) => (activo ? 'Activa' : 'Inactiva');

// ---------- Procesos de contratación ----------
```

por:

```js
    return { campos: enCampos, otros };
};

/** Modalidades y etapas son femeninas: "Activa" o "Inactiva"; los tipos de contrato, masculinos. */
export const textoEstadoCatalogo = (activo, masculino = false) => {
    if (masculino) return activo ? 'Activo' : 'Inactivo';
    return activo ? 'Activa' : 'Inactiva';
};

// ---------- Procesos de contratación ----------
```

En `resources/js/utils/contratacion.js`, reemplazar (3 de 7):

```js
    return (Number.isInteger(numero) ? PESOS : PESOS_CON_CENTAVOS).format(numero);
};

export const textoContratos = (cantidad) => {
    const n = Number(cantidad) || 0;
    if (n === 0) return 'Sin contratos';
```

por:

```js
    return (Number.isInteger(numero) ? PESOS : PESOS_CON_CENTAVOS).format(numero);
};

/** Una fecha año-mes-día de la API como día/mes/año; sin fecha, "—". */
export const textoFecha = (fecha) => {
    const partes = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(fecha ?? ''));
    return partes ? `${partes[3]}/${partes[2]}/${partes[1]}` : '—';
};

export const textoContratos = (cantidad) => {
    const n = Number(cantidad) || 0;
    if (n === 0) return 'Sin contratos';
```

En `resources/js/utils/contratacion.js`, reemplazar (4 de 7):

```js
    url_proceso: '',
    entidad_contratante_id: '',
    monto: '',
};

/** Los campos del formulario, en su orden: también son los nombres de la API. */
export const CAMPOS_PROCESO = Object.keys(PROCESO_VACIO);
```

por:

```js
    url_proceso: '',
    entidad_contratante_id: '',
    monto: '',
    fuente_id: '',
    responsable_id: '',
    fecha_apertura: '',
    fecha_cierre: '',
    observaciones: '',
};

export const MAXIMO_OBSERVACIONES_PROCESO = 5000;

/** Los campos del formulario, en su orden: también son los nombres de la API. */
export const CAMPOS_PROCESO = Object.keys(PROCESO_VACIO);
```

En `resources/js/utils/contratacion.js`, reemplazar (5 de 7):

```js
        url_proceso: proceso.url_proceso ?? '',
        entidad_contratante_id: id(proceso.entidad_contratante),
        monto: valorDesdeApi(proceso.monto),
    };
};

/** Errores del formulario por campo, con los mensajes del servidor; {} si se puede enviar. */
export const validarProceso = (formulario) => {
    const errores = {};
```

por:

```js
        url_proceso: proceso.url_proceso ?? '',
        entidad_contratante_id: id(proceso.entidad_contratante),
        monto: valorDesdeApi(proceso.monto),
        fuente_id: id(proceso.fuente),
        responsable_id: id(proceso.responsable),
        fecha_apertura: proceso.fecha_apertura ?? '',
        fecha_cierre: proceso.fecha_cierre ?? '',
        observaciones: proceso.observaciones ?? '',
    };
};

/** Año-mes-día de un calendario real: "2026-02-30" no existe. */
const esFechaValida = (texto) => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(texto)) return false;
    const fecha = new Date(`${texto}T00:00:00Z`);
    return !Number.isNaN(fecha.getTime()) && fecha.toISOString().slice(0, 10) === texto;
};

/**
 * Opciones del select de fuente de un proceso, en dos grupos: las fuentes del proyecto
 * (`fuenteIds`) y las demás. Se ofrecen las activas y la que el proceso ya tiene (`actual`), marcada
 * si está inactiva; una fuente inactiva del proyecto que el proceso no tiene no se ofrece.
 */
export const opcionesFuenteProceso = (fuentes, actual, fuenteIds = []) => {
    const delProyecto = [];
    const otras = [];
    opcionesConActual(fuentes, actual).forEach((o) => {
        (fuenteIds.some((id) => String(id) === o.valor) ? delProyecto : otras).push(o);
    });
    return { delProyecto, otras };
};

/** Errores del formulario por campo, con los mensajes del servidor; {} si se puede enviar. */
export const validarProceso = (formulario) => {
    const errores = {};
```

En `resources/js/utils/contratacion.js`, reemplazar (6 de 7):

```js
    if (monto !== null && monto.split('.')[0].length > CIFRAS_ENTERAS) {
        errores.monto = `El valor admite hasta ${CIFRAS_ENTERAS} cifras enteras y 2 decimales.`;
    }
    return errores;
};
```

por:

```js
    if (monto !== null && monto.split('.')[0].length > CIFRAS_ENTERAS) {
        errores.monto = `El valor admite hasta ${CIFRAS_ENTERAS} cifras enteras y 2 decimales.`;
    }
    const apertura = texto('fecha_apertura');
    const cierre = texto('fecha_cierre');
    if (apertura && !esFechaValida(apertura)) errores.fecha_apertura = 'Escribe la fecha de apertura como año-mes-día.';
    if (cierre && !esFechaValida(cierre)) errores.fecha_cierre = 'Escribe la fecha de cierre como año-mes-día.';
    else if (apertura && cierre && !errores.fecha_apertura && cierre < apertura) {
        errores.fecha_cierre = 'La fecha de cierre no puede ser anterior a la de apertura.';
    }
    if (texto('observaciones').length > MAXIMO_OBSERVACIONES_PROCESO) {
        errores.observaciones = `Las observaciones admiten hasta ${MAXIMO_OBSERVACIONES_PROCESO} caracteres.`;
    }
    return errores;
};
```

En `resources/js/utils/contratacion.js`, reemplazar (7 de 7):

```js
        url_proceso: texto('url_proceso'),
        entidad_contratante_id: id('entidad_contratante_id'),
        monto: valorParaApi(formulario.monto),
        ...(proceso ? { actualizado: proceso.actualizado ?? null } : {}),
    };
};
```

por:

```js
        url_proceso: texto('url_proceso'),
        entidad_contratante_id: id('entidad_contratante_id'),
        monto: valorParaApi(formulario.monto),
        fuente_id: id('fuente_id'),
        responsable_id: id('responsable_id'),
        fecha_apertura: texto('fecha_apertura'),
        fecha_cierre: texto('fecha_cierre'),
        observaciones: texto('observaciones'),
        ...(proceso ? { actualizado: proceso.actualizado ?? null } : {}),
    };
};
```

En `resources/js/utils/navegacion.js`, reemplazar (1 de 1):

```js
    // `propia`: la sección es un componente aparte que se pinta sin Parametros, no una de sus tablas.
    { slug: 'modalidades', tab: 'modalidades', titulo: 'Modalidades', descripcion: 'Formas de contratación: licitación pública, mínima cuantía…', grupo: GRUPO.CATALOGOS, icono: 'faGavel', palabras: 'contratacion licitacion seleccion abreviada concurso meritos directa minima cuantia secop catalogo', propia: true },
    { slug: 'etapas-contratacion', tab: 'etapas-contratacion', titulo: 'Etapas de contratación', descripcion: 'Etapas de un proceso y su orden.', grupo: GRUPO.CATALOGOS, icono: 'faListOl', palabras: 'contratacion licitacion proceso pliego adjudicacion cronograma secop catalogo', propia: true },
];

// No usar /plantillas: bajo /GestPro chocaría con la API del mismo nombre.
```

por:

```js
    // `propia`: la sección es un componente aparte que se pinta sin Parametros, no una de sus tablas.
    { slug: 'modalidades', tab: 'modalidades', titulo: 'Modalidades', descripcion: 'Formas de contratación: licitación pública, mínima cuantía…', grupo: GRUPO.CATALOGOS, icono: 'faGavel', palabras: 'contratacion licitacion seleccion abreviada concurso meritos directa minima cuantia secop catalogo', propia: true },
    { slug: 'etapas-contratacion', tab: 'etapas-contratacion', titulo: 'Etapas de contratación', descripcion: 'Etapas de un proceso y su orden.', grupo: GRUPO.CATALOGOS, icono: 'faListOl', palabras: 'contratacion licitacion proceso pliego adjudicacion cronograma secop catalogo', propia: true },
    { slug: 'tipos-contrato', tab: 'tipos-contrato', titulo: 'Tipos de contrato', descripcion: 'Clases de contrato: obra, interventoría, consultoría, suministro…', grupo: GRUPO.CATALOGOS, icono: 'faFileSignature', palabras: 'contratacion contratos obra interventoria consultoria suministro prestacion servicios orden compra catalogo', propia: true },
];

// No usar /plantillas: bajo /GestPro chocaría con la API del mismo nombre.
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/contratacion.test.js resources/js/utils/navegacion.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1008 passed.

Run: `npm test`
Expected: 526 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/js/components/CatalogoTiposContrato.jsx resources/js/components/Parametros.jsx resources/js/components/ProcesosProyecto.jsx resources/js/utils/contratacion.js resources/js/utils/contratacion.test.js resources/js/utils/navegacion.js resources/js/utils/navegacion.test.js
git commit -m "feat: process funding, dates and owner in the screen; contract types catalog"
```

---

### Tarea 8: Compilar para producción

- [ ] **Paso 1: Compilar**

Run: `npm run production`
Expected: `Compiled Successfully`, sin errores.

Justo antes de comitear, comprobar que `public/js/app.js` es el de producción: minificado y sin comentarios `/***/` al inicio. Si `npm run watch` lo reescribió, compilar otra vez.

- [ ] **Paso 2: Commit**

```bash
git add public/js/app.js public/js/app.js.map public/mix-manifest.json
git commit -m "build: production bundle for procurement stage 3A"
```

Los archivos que no cambiaron se omiten.

---

### Tarea 9: Verificación en la copia descartable

Usa la copia y la foto de la tarea 1. Nunca `gest_pro`.

- [ ] **Paso 1: Migrar la copia y comparar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

Expected: se aplican `2026_11_18_100017_amplia_procesos_licitacion_contratos` y `2026_11_18_100018_amplia_contratos`.

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion3.php" "$TMP_DIR/c3_despues.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c3_antes.json" "$TMP_DIR/c3_despues.json"
```

Expected: `SIN DIFERENCIAS`. Cualquier diferencia detiene la etapa.

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp --default-character-set=utf8mb4 -e "SELECT id, estado, estado_original FROM contratos; SELECT nombre FROM tipos_procesos; SHOW CREATE TABLE contratos\G" | grep -E "^[0-9]|^[A-Z]|proyecto"
```

Expected:
- **Estados:** los 7 contratos en `en_ejecucion` (6) y `liquidado` (1), con su texto anterior en `estado_original`.
- **Tipos de contrato:** los 6 sembrados.
- **Proyecto:** `proyecto` nulable, con `ON DELETE SET NULL`.

- [ ] **Paso 2: Revertir, comparar y volver a migrar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=2 --force
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion3.php" "$TMP_DIR/c3_rollback.json" gestpro_verif_tmp --solo-tablas
```

```bash
php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c3_tablas_antes.json" "$TMP_DIR/c3_rollback.json"
```

Expected: `SIN DIFERENCIAS`. Con las migraciones revertidas, el código nuevo no puede listar contratos (le faltan columnas), por eso se compara solo con tablas. Además, `proyecto` vuelve a ser obligatorio con `ON DELETE CASCADE`, y `tipos_procesos` queda vacía.

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion3.php" "$TMP_DIR/c3_remigrado.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c3_antes.json" "$TMP_DIR/c3_remigrado.json"
```

Expected: `SIN DIFERENCIAS`.

- [ ] **Paso 3: Probar en el navegador sobre la copia**

Compilar en desarrollo (`npx mix`) si `public/` no tiene el código de esta etapa. Arrancar el servidor desde `public/`, en un puerto libre: `artisan serve` recargaría `gest_pro` desde `.env`, y en el 8765 escucha otra aplicación. Comprobar con `netstat -ano | grep 8796` que nadie más escucha en ese puerto.

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8796 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

En Windows, ese servidor a veces corta `app.js` y la página queda en blanco: basta recargar.

Crear un Administrador de prueba solo en la copia; nunca usar sesiones de usuarios reales de `gest_pro`:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'verif@gestpro.test', 'rol' => App\Enums\Rol::Administrador]);"
```

La contraseña es la predeterminada de `UserFactory`. Entrar por `http://127.0.0.1:8796/GestPro/`. Si el navegador guarda una sesión anterior, borrar `localStorage` antes de entrar.

1. **Lista de contratos de un proyecto** (Parámetros → Proyectos → "Agregar Contrato"): los estados se ven como "En ejecución" y "Liquidado".
2. **Editar un contrato existente:**
   - cambiar el tipo de contrato y el supervisor, y guardar;
   - en la copia, solo cambian esos campos, `updated_at` y, como hoy, el avance financiero que calcula la pantalla;
   - `checklist_historial` registra `contrato_editado` con el usuario de prueba;
   - sus actas y actividades siguen ahí.
3. **Eliminar ese contrato, que tiene un acta:** se rechaza con "Este contrato tiene actas, actividades o modificaciones: anúlalo en vez de eliminarlo."
4. **Precarga desde un proceso:**
   - poner el proceso de la copia en `adjudicado` y con monto (por la pantalla de procesos o por la API);
   - en su proyecto, "Agregar Contrato" ofrece ese proceso y muestra la nota sobre los procesos;
   - al elegirlo, se precargan el objeto y el valor, y aparecen de solo lectura su número, modalidad, plataforma y enlace;
   - al guardar, la pantalla propone pasar el proceso a celebrado y, si se confirma, el proceso queda `celebrado`.
5. **Diálogo de procesos:** el formulario muestra fuente, responsable, fechas de apertura y cierre, y observaciones. La lista muestra cuántos contratos tiene cada proceso.
6. **Parámetros → Tipos de contrato:** se ven los 6 tipos sembrados.

La consola del navegador no muestra errores nuevos. Los 401 de una sesión vieja y los 422 de las validaciones probadas son esperados.

- [ ] **Paso 4: Limpiar**

Detener el servidor, eliminar la copia y devolver `public/` a lo comiteado si se compiló en desarrollo:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 5: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas, 1008.

Run: `npm test`
Expected: pasan todas, 526.

---

### Tarea 10: Informar y pedir validación

- [ ] **Paso 1: Actualizar el estado del diseño**

En `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, reemplazar:

```markdown
**Estado:** aprobada por el usuario el 2026-11-18, con las 6 recomendaciones de la sección 12.
```

por:

```markdown
**Estado:** aprobada por el usuario el 2026-11-18, con las 6 recomendaciones de la sección 12. La fase 2 se divide en las etapas 3A (datos y reglas) y 3B (módulo Contratos, SECOP II, "Registrar contrato" y ficha del proyecto). Además, un contrato puede no tener proyecto. La etapa 3A está implementada y verificada en copia, pendiente de validar y aplicar en `gest_pro`.
```

```bash
git add docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md
git commit -m "docs: mark procurement stage 3A as implemented"
```

- [ ] **Paso 2: Informar**

Informar:
- los totales de pruebas;
- las comparaciones (migrar, revertir y volver a migrar);
- lo revisado en el navegador.

Pedir **dos** decisiones por separado:
1. si valida la etapa;
2. si autoriza aplicar las migraciones 18 y 19 en `gest_pro`.

---

### Tarea 11: Aplicar en `gest_pro` (solo con autorización)

No ejecutar esta tarea sin un sí explícito del usuario a este paso. Si la comparación muestra diferencias, restaurar el respaldo: no usar `migrate:rollback`.

- [ ] **Paso 1: Respaldar**

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro > "$TMP_DIR/gest_pro_antes_contratacion_3a.sql"
```

Expected: el archivo existe y termina en `-- Dump completed`.

- [ ] **Paso 2: Tomar la foto antes de migrar**

Con el código de esta etapa, el listado de contratos ya espera las columnas nuevas, así que antes de migrar solo se toman las tablas:

```bash
php "$TMP_DIR/gestpro_foto_contratacion3.php" "$TMP_DIR/c3_real_antes.json" gest_pro --solo-tablas
```

- [ ] **Paso 3: Migrar hacia adelante**

Run: `php artisan migrate --force`
Expected: se aplican las dos migraciones `2026_11_18_10001[78]_*`.

- [ ] **Paso 4: Comparar**

```bash
php "$TMP_DIR/gestpro_foto_contratacion3.php" "$TMP_DIR/c3_real_despues.json" gest_pro
```

```bash
php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c3_real_antes.json" "$TMP_DIR/c3_real_despues.json"
```

Expected: `SIN DIFERENCIAS`.

- [ ] **Paso 5: Informar**

Dar el resultado y la ruta completa del respaldo, y avisar que está en una carpeta temporal.

---

## Criterio de la etapa

La etapa está lista cuando:

- los 7 contratos siguen con las mismas cifras, actas, actividades y modificaciones, y con su estado convertido sin perder el texto anterior;
- un contrato se guarda con validación, se vincula a un proceso adjudicado de su proyecto con precarga y puede no tener proyecto;
- un contrato con ejecución no se elimina;
- el proceso tiene fuente, fechas, responsable, observaciones y el estado `revocado`;
- los tipos de contrato se administran desde Parámetros;
- ningún expediente cambia al migrar ni al revertir.
