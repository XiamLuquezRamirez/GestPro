# Plantillas de checklist, etapa 6: versiones en expedientes y cierre. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** cerrar el trabajo de plantillas de checklist. Un expediente en borrador aplica la versión nueva de una plantilla con vista previa y sin perder el trabajo hecho. El expediente tiene una pantalla de historial y el tablero se filtra por fuente y responsable. Se retira la columna `proyectos_expedientes.catalogo_version_id` (migración 11), y se resuelven los pendientes que el usuario eligió.

**Arquitectura:**
- La versión de cada plantilla del expediente ya vive solo en `expediente_plantillas`. La migración 11 retira la columna duplicada y todo lo que la leía.
- `VersionesExpediente` aplica una versión nueva:
  - re-ancla las filas por `clave`, cambia la versión en `expediente_plantillas` y deja que `ExpedienteService::sincronizar`, el único motor, recalcule el checklist;
  - la vista previa ejecuta esa misma aplicación dentro de una transacción que siempre se revierte.
- El historial del expediente se lee de `checklist_historial`, paginado.
- El resumen del tablero agrega los pendientes por responsable.

**Tecnologías:** Laravel 12, PHP 8.2, MariaDB 10.4, React 18 con Laravel Mix, PHPUnit sobre SQLite en memoria, Vitest.

**Diseño:** `docs/superpowers/specs/2026-11-06-plantillas-checklist-design.md`:
- sección 2: "Versiones";
- sección 4: migración 11;
- sección 6: "Trabajo diario";
- notas de la sección 8 para la etapa 6.

**Estado de partida:** rama `feature/plantillas-checklist`, etapas 1 a 5 validadas y aplicadas en `gest_pro`; 589 pruebas PHP y 293 JS pasando.

---

## Reglas de ejecución

- **Nada de esto se aplica a `gest_pro` sin autorización del usuario.** Nunca se usa `migrate:rollback` allí: si hay que volver atrás, se restaura el respaldo. La tarea 14 aplica la migración, y solo con un sí explícito.
- Commits autorizados solo en `feature/plantillas-checklist`. Sin push. Mensajes en inglés, como el historial, terminados con la línea `Co-Authored-By` del modelo que comitea.
- Nomenclatura en español sin tildes para tablas, columnas, clases y métodos nuevos. Textos para el usuario en español con tuteo, como el resto del módulo; los mensajes de las migraciones conservan el registro de usted de la serie.
- Pruebas: `php artisan test` sin definir `DB_DATABASE` (usa SQLite en memoria) y `npm test`. Si Vitest crea `resources/js/node_modules`, se borra.
- **Editar con la herramienta Edit, no con `sed`.** Cada reemplazo de este plan aparece una sola vez en su archivo. Si no lo encuentras tal cual, detente y avisa.
- El usuario suele tener `npm run watch` corriendo, que reescribe `public/`. Solo la tarea 11 comitea `public/`, después de `npm run production`.
- No tocar ni comitear `public/images/marca-completa.png` ni `public/images/marca-completa1.png` si siguen modificados: son cambios del usuario.
- `.env` está versionado: nunca se agrega a un commit. Comitear siempre con rutas explícitas.
- `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio. Hay que definirlo en cada comando, porque el estado de la terminal no se conserva.
- **Cuidado con SQLite.** Quitar o agregar una columna con clave foránea reconstruye la tabla y, dentro de la transacción de `RefreshDatabase`, borra en cascada las filas hijas. Por eso las pruebas de la migración 11 no crean filas de checklist. En MariaDB no ocurre, y la tarea 12 lo comprueba con datos reales.

## Decisiones del usuario (2026-11-09)

- Al editar un proyecto y pasarlo a Formulación, se crea su expediente automático si le corresponde: fuente definida y ningún expediente.
- De los pendientes menores solo entran el tope del orden SGR y que la pantalla del expediente no dependa del catálogo SGR cuando no lo usa.
- Quedan fuera:
  - el endpoint liviano de avisos de duplicados;
  - los pendientes de la administración de plantillas (orden y duplicado en bloque, carrera de `claveLibre`, nombre único, mensajes repetidos, ruta antigua);
  - las flechas de orden de la SGR que cruzan secciones.

## Decisiones de este plan

**Migración 11**
- Retira `proyectos_expedientes.catalogo_version_id` con su clave foránea.
- Antes de tocar nada, se detiene si algún expediente tiene en esa columna una versión que no figura en `expediente_plantillas`, porque se perdería. Así se cubre una rama anterior que haya creado expedientes sobre la base ya migrada.
- `down()` restaura la columna desde la versión de la plantilla con motor `sgr`, o null, como la dejó la migración 10.
- `CatalogoVersion::expedientes()` pasa a contar por `expediente_plantillas`. **Cambio visible:** el historial de versiones de una plantilla ahora cuenta todos los expedientes que la usan, como principal o complementaria. Antes solo contaba los SGR, y una plantilla simple siempre mostraba 0.
- La cabecera del expediente deja de traer `catalogo_version`: la versión de cada plantilla ya viene en `plantillas`.

**Aplicar una versión nueva**
- Solo en borrador, configurado o no. La plantilla tiene que estar en el expediente, activa y con una versión vigente distinta de la que usa.
- El pedido trae `version_id`: si ya no es la vigente, se rechaza. Así nunca se aplica algo distinto de lo que mostró la vista previa.
- **Reconocimiento por `clave`:** las filas ancladas a requisitos de la versión anterior de esa plantilla, también las retiradas, se re-anclan al requisito de la versión nueva con la misma `clave`. Luego cambia la versión en `expediente_plantillas` y `sincronizar` recalcula orígenes, carácter, orden, filas nuevas y retiradas.
- Lo que sigue conserva cumplimiento, observación, soporte y responsable. Lo nuevo nace pendiente, o por definir si es condicionado de motor simple. Lo retirado queda con `aplica = false` y su trabajo.
- Si dos filas quedan con la misma llave, se queda la anclada en un origen, luego la que aplica, luego la que tiene trabajo, luego la de id menor.
- Queda en el historial como `version_aplicada`.
- **Vista previa:** la misma aplicación dentro de una transacción que siempre se revierte. Bloquea el expediente mientras dura. Lista lo que se agrega, lo que deja de aplicar (marcando lo que conserva trabajo), lo que cambia y cuántos siguen igual, con el avance antes y después.
- La vista previa protege la versión, no el resto del expediente: si otra persona cambia la configuración entre la vista previa y la confirmación, se aplica sobre lo nuevo.
- La cabecera trae `version_nueva` por plantilla. La pantalla lo muestra con su botón solo en un borrador editable; en un radicado no se muestra.

**Historial del expediente**
- `GET /expedientes/{expediente}/historial?pagina=N`, para cualquier usuario autenticado, de a 50, de lo más reciente a lo más antiguo, con `total`.
- Junta los eventos del expediente y los de sus filas, incluidas las propias ya eliminadas.
- Cada entrada trae un resumen del requisito, con el mismo criterio de texto que el checklist. Nunca trae la ruta de un archivo.
- Una entrada sin usuario se muestra como "Sistema", y una de un usuario que ya no existe, como "Usuario eliminado".
- En pantalla: botón "Historial" en la barra del expediente configurado, con "Cargar más". Se recarga desde la primera página cuando cambia el expediente mostrado, sin pedirlo en cada render.

**Tablero**
- `GET /expedientes` agrega por expediente los responsables con pendientes y cuántos tiene cada uno, con la misma definición de avance del servicio, en una sola consulta.
- Filtros por fuente (incluido "Por definir") y por responsable. Las tarjetas cuentan sobre lo filtrado. Con un responsable elegido, la columna muestra sus pendientes.
- En el modo sala no hay filtros.

**Formulación, orden y catálogo**
- Entrar a Formulación al editar el proyecto crea el expediente automático si corresponde. Seguir en Formulación no lo crea: así un borrador eliminado a propósito no vuelve a nacer.
- El orden de un requisito del catálogo admite hasta 99 999. Además, el servicio lo acota dentro de su sección (SGR) o de su plantilla, para que datos anteriores no invadan la siguiente. Con los datos actuales (orden máximo 207) nada cambia.
- La pantalla del expediente pide `/catalogo-requisitos/vigente` solo si el expediente o la selección tienen una plantilla SGR. Si falla, el panel SGR lo dice y ofrece reintentar, sin tumbar la pantalla.

## Fuera de esta etapa

- Las notas de la sección 8 que quedan abiertas, en la tarea 13.
- Un aviso al aplicar si otra persona cambió la configuración del expediente después de la vista previa.

## Lo que ya se ensayó

El plan se ensayó completo en un clon descartable fuera del repositorio, y este documento se generó con el código de ese ensayo: un script comprobó que aplicar sus bloques sobre la rama reproduce el ensayo exactamente. Una revisión independiente del ensayo encontró un defecto medio (la colisión de llave al re-anclar) y varios menores, ya corregidos en las tareas correspondientes.

- **Pruebas:** 643 PHP y 343 JS.
- **En una copia de `gest_pro` (MariaDB)**, lo que la API entrega de los 10 expedientes y sus 286 filas no cambió en ningún paso:
  - al migrar;
  - al revertir, que restaura la columna con la versión SGR y su clave foránea restrict;
  - al reintentar con la clave foránea quitada a mano;
  - al volver a migrar.

  Además, la migración se detuvo con un mensaje claro ante un expediente con una versión que no figuraba en sus plantillas.
- **En el navegador, sobre esa copia:**
  - se publicó la versión 2 de la SGR con un requisito con texto nuevo, uno quitado y uno agregado;
  - la vista previa en el expediente #2 listó exactamente esos tres cambios, con el avance de 6 de 24 a 5 de 24;
  - al aplicarla, el requisito editado siguió cumplido, el nuevo quedó pendiente y el quitado se retiró con su trabajo;
  - el historial mostró "Aplicó la versión 2 de SGR (antes 1)";
  - el tablero filtró por fuente y por responsable, con "Pendientes de Ana Gómez".

## Archivos

| Archivo | Responsabilidad |
|---|---|
| `database/migrations/2026_11_09_100010_drop_catalogo_version_de_expedientes.php` | Migración 11 |
| `app/Services/Viabilizacion/VersionesExpediente.php` | Aplicar una versión nueva y su vista previa |
| `app/Services/Viabilizacion/ExpedienteService.php` | Cabecera con `version_nueva`, desempate de filas, historial del expediente, responsables del resumen, Formulación y tope de orden |
| `app/Services/Viabilizacion/PlantillasExpediente.php` | Versiones nuevas de las plantillas de un expediente |
| `app/Services/Viabilizacion/HistorialChecklist.php` | Página del historial del expediente |
| `app/Http/Controllers/PlantillasExpedienteController.php`, `ExpedienteController.php`, `ProyectoController.php`, `CatalogoRequisitosController.php` | Rutas nuevas, Formulación y validación del orden |
| `resources/js/utils/versionesExpediente.js` | Textos de la versión nueva y de su vista previa |
| `resources/js/components/ExpedienteHistorial.jsx` | Historial del expediente |
| `resources/js/components/ExpedienteViabilizacion.jsx` | Botones de versión nueva e historial, y catálogo SGR bajo demanda |
| `resources/js/utils/expedientesResumen.js`, `ExpedientesSgrPanel.jsx`, `Dashboard.jsx` | Filtros del tablero |

---

### Tarea 1: Punto de partida y foto con el código actual

La foto de la API tiene que tomarse **antes de cambiar código**. La copia `gestpro_verif_tmp` se conserva hasta la tarea 12.

- [ ] **Paso 1: Comprobar la rama, el estado y las pruebas**

Run: `git branch --show-current`
Expected: `feature/plantillas-checklist`

Run: `git status --short`
Expected: como mucho, `public/` (si corre `npm run watch`) y las dos imágenes del usuario.

Run: `php artisan test`
Expected: pasan todas, 589. Si alguna falla, detenerse y avisar.

Run: `npm test`
Expected: pasan todas, 293.

- [ ] **Paso 2: Crear la copia descartable**

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE IF EXISTS gestpro_verif_tmp; CREATE DATABASE gestpro_verif_tmp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro | /c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp
```

Si MySQL no responde (`ERROR 2002`), está apagado. Hay que pedirle al usuario que lo arranque desde XAMPP o arrancarlo con `/c/xampp/mysql/bin/mysqld --defaults-file=/c/xampp/mysql/bin/my.ini --standalone` en segundo plano.

- [ ] **Paso 3: Crear los dos scripts de comprobación**

Crear `$TMP_DIR/gestpro_foto_etapa6.php`. Toma las tablas y, salvo con `--solo-tablas`, lo que la API entrega de cada expediente. No guarda `catalogo_version_id`, que esta etapa retira: guarda la versión SGR de cada expediente leída de `expediente_plantillas`. Se detiene si la base no es la esperada:

```php
<?php
// Foto de los expedientes para comparar antes y después de migrar.
// Uso, desde la raíz del repositorio:
//   php gestpro_foto_etapa6.php <archivo.json> <base esperada> [--solo-tablas]
// --solo-tablas lee las tablas sin pasar por el servicio: sirve cuando el código y el
// esquema no coinciden (antes de migrar con el código nuevo, o después de revertir).
//
// La migración 11 retira proyectos_expedientes.catalogo_version_id, así que la foto no
// guarda esa columna: guarda la versión sgr de cada expediente leída de
// expediente_plantillas, y la comprobación de la columna se hace aparte.
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

$foto = [
    'tablas' => [
        'expedientes' => DB::table('proyectos_expedientes')->orderBy('id')->get($columnasExpediente),
        'version_sgr' => DB::table('expediente_plantillas')
            ->join('catalogo_versiones', 'catalogo_versiones.id', '=', 'expediente_plantillas.catalogo_version_id')
            ->join('plantillas_checklist', 'plantillas_checklist.id', '=', 'catalogo_versiones.plantilla_id')
            ->where('plantillas_checklist.motor', 'sgr')
            ->orderBy('expediente_plantillas.expediente_id')
            ->pluck('expediente_plantillas.catalogo_version_id', 'expediente_plantillas.expediente_id'),
        'expediente_plantillas' => DB::table('expediente_plantillas')->orderBy('expediente_id')->orderBy('catalogo_version_id')->get(),
        'filas' => DB::table('proyectos_requisitos')->orderBy('id')->get($columnasFila),
        'origenes' => DB::table('proyecto_requisito_origenes')->orderBy('proyecto_requisito_id')->orderBy('requisito_catalogo_id')->get(),
    ],
];

if (! in_array('--solo-tablas', $argv, true)) {
    $servicio = app(ExpedienteService::class);
    // catalogo_version sale de la API en esta etapa: no se compara.
    $sinVersion = function (array $e) {
        unset($e['catalogo_version']);

        return $e;
    };
    $foto['api'] = [
        'resumen' => $servicio->resumenPorProyecto(),
        'expedientes' => ProyectoExpediente::query()->orderBy('id')->get()
            ->map(fn (ProyectoExpediente $e) => $sinVersion($servicio->presentar($e)))->all(),
    ];
}

file_put_contents($argv[1], json_encode($foto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo 'Expedientes: ' . count($foto['tablas']['expedientes']) . ', filas: ' . count($foto['tablas']['filas'])
    . ', con versión sgr: ' . count($foto['tablas']['version_sgr'])
    . (isset($foto['api']) ? ', con API' : ', solo tablas') . "\n";
```

Crear `$TMP_DIR/gestpro_comparar_etapa6.php`. Lo nuevo que agrega la etapa se permite; un valor distinto o algo que falta, no:

```php
<?php
// Comprueba que todo lo que había antes sigue igual después. Lo nuevo (campos que la etapa
// agrega) se permite; un valor distinto o algo que falta, no.
// Uso: php gestpro_comparar_etapa6.php <antes.json> <despues.json>

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

- [ ] **Paso 4: Tomar la foto de antes, con el código de la etapa 5**

Desde la raíz del repositorio:

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_etapa6.php" "$TMP_DIR/e6_antes.json" gestpro_verif_tmp
```

Expected: `Expedientes: 10, filas: 286, con versión sgr: 10, con API`, o las cifras actuales de `gest_pro`.

Run: `/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT MAX(orden) FROM requisitos_catalogo"`
Expected: un número menor que 100 000 (207 con los datos actuales). Si es mayor, avisar antes de seguir: la tarea 9 acota esos órdenes y la comparación de la tarea 12 mostraría diferencias en `orden`.

---

### Tarea 2: Migración 11: la versión del expediente solo en sus plantillas

Retira `proyectos_expedientes.catalogo_version_id` y todo lo que la leía o escribía. La versión de cada plantilla del expediente ya está en `expediente_plantillas`.

**Files:**
- Create: `database/migrations/2026_11_09_100010_drop_catalogo_version_de_expedientes.php`
- Test (create): `tests/Feature/Schema/RetiroVersionExpedienteTest.php`
- Modify: `app/Models/CatalogoVersion.php`
- Modify: `app/Models/ProyectoExpediente.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `app/Services/Viabilizacion/PlantillasExpediente.php`
- Test: `resources/js/utils/expedienteChecklist.test.js`
- Test: `tests/Feature/Schema/CatalogoRequisitosTest.php`
- Test: `tests/Feature/Schema/PlantillasChecklistTest.php`
- Test: `tests/Feature/Schema/RequisitosPropiosTest.php`
- Test: `tests/Feature/Schema/SeguimientoRequisitoTest.php`
- Test: `tests/Feature/Viabilizacion/CatalogoRequisitosApiTest.php`
- Test: `tests/Feature/Viabilizacion/ExpedienteApiTest.php`
- Test: `tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`
- Test: `tests/Feature/Viabilizacion/ExpedienteConfiguracionValidacionTest.php`
- Test: `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`
- Test: `tests/Feature/Viabilizacion/PlantillasAdminApiTest.php`
- Test: `tests/Feature/Viabilizacion/PlantillasExpedienteApiTest.php`
- Test: `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Schema/RetiroVersionExpedienteTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\PlantillaChecklist;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Estas pruebas no crean filas de checklist a propósito. En SQLite, quitar o poner la clave
 * foránea reconstruye proyectos_expedientes y, dentro de la transacción de RefreshDatabase, eso
 * borraría en cascada las filas hijas. En MariaDB no pasa: se comprueba en la copia descartable.
 */
class RetiroVersionExpedienteTest extends TestCase
{
    use RefreshDatabase;

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_09_100010_drop_catalogo_version_de_expedientes.php');
    }

    private function version(int $plantilla): int
    {
        return DB::table('catalogo_versiones')->insertGetId([
            'plantilla_id' => $plantilla, 'numero' => 1, 'estado' => 'publicada', 'vigente' => true,
        ]);
    }

    private function otraPlantilla(): int
    {
        return PlantillaChecklist::create(['nombre' => 'Agua', 'motor' => PlantillaChecklist::MOTOR_SIMPLE])->id;
    }

    /** Con versión, la escribe en la columna: solo existe con la migración bajada. */
    private function expediente(?int $version = null): int
    {
        return DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => DB::table('proyectos')->insertGetId(['nombre' => 'Prueba']),
            ...($version !== null ? ['catalogo_version_id' => $version] : []),
            'fase_sgr' => 3,
            'estado' => 'borrador',
        ]);
    }

    /** @param array<int, bool> $versiones catalogo_version_id => es_principal */
    private function plantillas(int $expediente, array $versiones): void
    {
        foreach ($versiones as $version => $principal) {
            DB::table('expediente_plantillas')->insert([
                'expediente_id' => $expediente, 'catalogo_version_id' => $version, 'es_principal' => $principal,
            ]);
        }
    }

    private function foranea(): ?array
    {
        return collect(Schema::getForeignKeys('proyectos_expedientes'))
            ->first(fn ($f) => $f['columns'] === ['catalogo_version_id']);
    }

    private function versionDe(int $expediente): ?int
    {
        $version = DB::table('proyectos_expedientes')->where('id', $expediente)->value('catalogo_version_id');

        return $version === null ? null : (int) $version;
    }

    private function migrarDetenido(string $mensaje): void
    {
        try {
            $this->migracion()->up();
            $this->fail('La migración debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($mensaje, $e->getMessage());
        }

        // Nada se quitó.
        $this->assertTrue(Schema::hasColumn('proyectos_expedientes', 'catalogo_version_id'));
        $this->assertNotNull($this->foranea());
    }

    // ---------------------------------------------------------------- subida

    public function test_tras_migrar_los_expedientes_no_tienen_version_propia(): void
    {
        $this->assertFalse(Schema::hasColumn('proyectos_expedientes', 'catalogo_version_id'));
        $this->assertNull($this->foranea());
        // La versión sigue en expediente_plantillas.
        $this->assertTrue(Schema::hasColumns('expediente_plantillas', ['expediente_id', 'catalogo_version_id', 'es_principal']));
    }

    public function test_migrar_se_detiene_si_una_version_no_figura_en_expediente_plantillas(): void
    {
        $this->migracion()->down();
        $sgr = $this->version(PlantillaChecklist::sgr()->id);
        $otra = $this->version($this->otraPlantilla());

        $this->plantillas($this->expediente($sgr), [$sgr => true]); // registrada: no detiene
        $this->expediente(); // sin versión: no detiene
        $sinFila = $this->expediente($sgr);
        $otraVersion = $this->expediente($sgr);
        $this->plantillas($otraVersion, [$otra => true]);

        $this->migrarDetenido("los expedientes {$sinFila}, {$otraVersion} tienen una versión de catálogo que no figura en expediente_plantillas");
        $this->migrarDetenido('Revíselos antes de migrar.');
    }

    public function test_el_mensaje_nombra_un_solo_expediente_en_singular(): void
    {
        $this->migracion()->down();
        $sinFila = $this->expediente($this->version(PlantillaChecklist::sgr()->id));

        $this->migrarDetenido("el expediente {$sinFila} tiene una versión de catálogo que no figura");
        $this->migrarDetenido('Revíselo antes de migrar.');
    }

    public function test_el_mensaje_nombra_como_mucho_diez_expedientes(): void
    {
        $this->migracion()->down();
        $sgr = $this->version(PlantillaChecklist::sgr()->id);
        $ids = array_map(fn () => $this->expediente($sgr), range(1, 12));

        $this->migrarDetenido('los expedientes ' . implode(', ', array_slice($ids, 0, 10)) . ' y 2 más tienen');
    }

    public function test_reintentar_la_migracion_no_falla(): void
    {
        $migracion = $this->migracion();

        // Ya migrada.
        $migracion->up();
        $this->assertFalse(Schema::hasColumn('proyectos_expedientes', 'catalogo_version_id'));

        // Lo que dejaría un fallo después de quitar la clave foránea y antes de la columna.
        $migracion->down();
        Schema::table('proyectos_expedientes', function (Blueprint $table) {
            $table->dropForeign(['catalogo_version_id']);
        });
        $this->assertNull($this->foranea());

        $migracion->up();

        $this->assertFalse(Schema::hasColumn('proyectos_expedientes', 'catalogo_version_id'));
        $this->assertNull($this->foranea());
    }

    // ---------------------------------------------------------------- bajada

    public function test_el_rollback_restaura_la_version_sgr_de_cada_expediente(): void
    {
        $sgr = $this->version(PlantillaChecklist::sgr()->id);
        $otra = $this->version($this->otraPlantilla());

        $soloSgr = $this->expediente();
        $this->plantillas($soloSgr, [$sgr => true]);
        // La SGR como complementaria: cuenta el motor, no la principal.
        $complementaria = $this->expediente();
        $this->plantillas($complementaria, [$otra => true, $sgr => false]);
        $sinSgr = $this->expediente();
        $this->plantillas($sinSgr, [$otra => true]);
        $sinPlantillas = $this->expediente();

        $this->migracion()->down();

        $this->assertSame($sgr, $this->versionDe($soloSgr));
        $this->assertSame($sgr, $this->versionDe($complementaria));
        $this->assertNull($this->versionDe($sinSgr));
        $this->assertNull($this->versionDe($sinPlantillas));
    }

    public function test_el_rollback_deja_la_columna_opcional_con_su_clave_foranea_y_ambos_sentidos_se_pueden_repetir(): void
    {
        $migracion = $this->migracion();

        $migracion->down();
        $migracion->down(); // repetirlo no falla

        $columna = collect(Schema::getColumns('proyectos_expedientes'))->firstWhere('name', 'catalogo_version_id');
        $this->assertNotNull($columna);
        $this->assertTrue($columna['nullable']);
        $foranea = $this->foranea();
        $this->assertSame('catalogo_versiones', $foranea['foreign_table']);
        $this->assertSame(['id'], $foranea['foreign_columns']);
        $this->assertSame('restrict', strtolower($foranea['on_delete']));

        $migracion->up();
        $migracion->up(); // repetirlo no falla

        $this->assertFalse(Schema::hasColumn('proyectos_expedientes', 'catalogo_version_id'));
        $this->assertNull($this->foranea());
    }

    public function test_tras_el_rollback_una_version_usada_por_un_expediente_no_se_puede_borrar(): void
    {
        $sgr = $this->version(PlantillaChecklist::sgr()->id);
        $this->migracion()->down();
        $this->expediente($sgr);

        // Sin filas en expediente_plantillas: lo impide la clave foránea restaurada.
        $this->assertSame(0, DB::table('expediente_plantillas')->count());
        $this->expectException(QueryException::class);
        DB::table('catalogo_versiones')->where('id', $sgr)->delete();
    }

    public function test_tras_el_rollback_la_migracion_10_tambien_se_puede_revertir(): void
    {
        $sgr = $this->version(PlantillaChecklist::sgr()->id);
        $expediente = $this->expediente();
        $this->plantillas($expediente, [$sgr => true]);

        $this->migracion()->down();
        (require database_path('migrations/2026_11_08_100009_add_requisitos_propios_table.php'))->down();

        $columna = collect(Schema::getColumns('proyectos_expedientes'))->firstWhere('name', 'catalogo_version_id');
        $this->assertFalse($columna['nullable']);
        $this->assertSame($sgr, $this->versionDe($expediente));
    }
}
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (1 de 3):

```js
    const expediente = {
        id: 7,
        proyecto: { id: 1, nombre: 'Acueducto: veredal/norte' },
        catalogo_version: { numero: 1 },
        plantillas: [{ plantilla_id: 1, nombre: 'SGR', motor: 'sgr', es_principal: true, version: { id: 1, numero: 1 } }],
        tiene_sgr: true,
        fase_sgr: 3,
```

por:

```js
    const expediente = {
        id: 7,
        proyecto: { id: 1, nombre: 'Acueducto: veredal/norte' },
        plantillas: [{ plantilla_id: 1, nombre: 'SGR', motor: 'sgr', es_principal: true, version: { id: 1, numero: 1 } }],
        tiene_sgr: true,
        fase_sgr: 3,
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (2 de 3):

```js
    it('sin plantilla SGR no lleva la fase ni la configuración SGR, y aguanta filas propias', () => {
        const simple = {
            ...expediente,
            catalogo_version: null,
            tiene_sgr: false,
            plantillas: [{ plantilla_id: 5, nombre: 'Obras de agua', motor: 'simple', es_principal: true, version: { id: 8, numero: 2 } }],
            requisitos: [
```

por:

```js
    it('sin plantilla SGR no lleva la fase ni la configuración SGR, y aguanta filas propias', () => {
        const simple = {
            ...expediente,
            tiene_sgr: false,
            plantillas: [{ plantilla_id: 5, nombre: 'Obras de agua', motor: 'simple', es_principal: true, version: { id: 8, numero: 2 } }],
            requisitos: [
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (3 de 3):

```js
    });

    it('sin plantillas, el resumen lo dice', () => {
        const { resumen } = construirHojasExcel({ ...expediente, plantillas: [], tiene_sgr: false, catalogo_version: null, requisitos: [] });
        expect(resumen.find((r) => r.Campo === 'Plantillas')?.Valor).toBe('Sin plantillas');
    });
```

por:

```js
    });

    it('sin plantillas, el resumen lo dice', () => {
        const { resumen } = construirHojasExcel({ ...expediente, plantillas: [], tiene_sgr: false, requisitos: [] });
        expect(resumen.find((r) => r.Campo === 'Plantillas')?.Valor).toBe('Sin plantillas');
    });
```

En `tests/Feature/Schema/CatalogoRequisitosTest.php`, reemplazar (1 de 2):

```php

    private function crearExpediente(int $proyectoId): int
    {
        return DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => $proyectoId,
            'catalogo_version_id' => DB::table('catalogo_versiones')->where('vigente', true)->value('id'),
            'fase_sgr' => 3,
            'estado' => 'borrador',
        ]);
    }

    public function test_tablas_del_modulo_existen_con_sus_columnas(): void
```

por:

```php

    private function crearExpediente(int $proyectoId): int
    {
        $expediente = DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => $proyectoId,
            'fase_sgr' => 3,
            'estado' => 'borrador',
        ]);
        DB::table('expediente_plantillas')->insert([
            'expediente_id' => $expediente,
            'catalogo_version_id' => DB::table('catalogo_versiones')->where('vigente', true)->value('id'),
            'es_principal' => true,
        ]);

        return $expediente;
    }

    public function test_tablas_del_modulo_existen_con_sus_columnas(): void
```

En `tests/Feature/Schema/CatalogoRequisitosTest.php`, reemplazar (2 de 2):

```php
            'requisitos_tipologias' => ['clave', 'nombre', 'activo'],
            'requisitos_catalogo' => ['catalogo_version_id', 'clave', 'seccion', 'fuente', 'sector_id', 'tipo_id',
                'subtitulo', 'codigo', 'texto', 'nota', 'condicion', 'orden', 'activo'],
            'proyectos_expedientes' => ['proyecto_id', 'catalogo_version_id', 'fase_sgr', 'estado',
                'fecha_radicado', 'usuario_radicado_id'],
            'expediente_sectores' => ['expediente_id', 'sector_id'],
            'expediente_tipos' => ['expediente_id', 'tipo_id'],
```

por:

```php
            'requisitos_tipologias' => ['clave', 'nombre', 'activo'],
            'requisitos_catalogo' => ['catalogo_version_id', 'clave', 'seccion', 'fuente', 'sector_id', 'tipo_id',
                'subtitulo', 'codigo', 'texto', 'nota', 'condicion', 'orden', 'activo'],
            'proyectos_expedientes' => ['proyecto_id', 'fase_sgr', 'estado',
                'fecha_radicado', 'usuario_radicado_id'],
            'expediente_sectores' => ['expediente_id', 'sector_id'],
            'expediente_tipos' => ['expediente_id', 'tipo_id'],
```

En `tests/Feature/Schema/PlantillasChecklistTest.php`, reemplazar (1 de 4):

```php
        return require database_path('migrations/2026_11_06_100005_create_expediente_plantillas_table.php');
    }

    private function expediente(int $version): int
    {
        return DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => DB::table('proyectos')->insertGetId(['nombre' => 'Prueba']),
            'catalogo_version_id' => $version,
            'fase_sgr' => 3,
            'estado' => 'borrador',
        ]);
```

por:

```php
        return require database_path('migrations/2026_11_06_100005_create_expediente_plantillas_table.php');
    }

    /**
     * La migración 6 llena expediente_plantillas desde proyectos_expedientes.catalogo_version_id,
     * que la migración 11 retira: las pruebas que la ejecutan bajan antes la 11.
     */
    private function bajarRetiroDeVersion(): void
    {
        (require database_path('migrations/2026_11_09_100010_drop_catalogo_version_de_expedientes.php'))->down();
    }

    /** Con versión, la escribe en la columna anterior a la migración 11: hay que bajarla antes. */
    private function expediente(?int $version = null): int
    {
        return DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => DB::table('proyectos')->insertGetId(['nombre' => 'Prueba']),
            ...($version !== null ? ['catalogo_version_id' => $version] : []),
            'fase_sgr' => 3,
            'estado' => 'borrador',
        ]);
```

En `tests/Feature/Schema/PlantillasChecklistTest.php`, reemplazar (2 de 4):

```php

    public function test_migrar_registra_la_version_de_cada_expediente_como_principal(): void
    {
        $version = $this->version(PlantillaChecklist::sgr()->id);
        $expediente = $this->expediente($version);
        $migracion = $this->migracionExpedientes();
```

por:

```php

    public function test_migrar_registra_la_version_de_cada_expediente_como_principal(): void
    {
        $this->bajarRetiroDeVersion();
        $version = $this->version(PlantillaChecklist::sgr()->id);
        $expediente = $this->expediente($version);
        $migracion = $this->migracionExpedientes();
```

En `tests/Feature/Schema/PlantillasChecklistTest.php`, reemplazar (3 de 4):

```php
    public function test_borrar_un_expediente_borra_su_registro_de_plantillas(): void
    {
        $version = $this->version(PlantillaChecklist::sgr()->id);
        $expediente = $this->expediente($version);
        DB::table('expediente_plantillas')->insert([
            'expediente_id' => $expediente, 'catalogo_version_id' => $version, 'es_principal' => true,
        ]);
```

por:

```php
    public function test_borrar_un_expediente_borra_su_registro_de_plantillas(): void
    {
        $version = $this->version(PlantillaChecklist::sgr()->id);
        $expediente = $this->expediente();
        DB::table('expediente_plantillas')->insert([
            'expediente_id' => $expediente, 'catalogo_version_id' => $version, 'es_principal' => true,
        ]);
```

En `tests/Feature/Schema/PlantillasChecklistTest.php`, reemplazar (4 de 4):

```php

    public function test_reintentar_la_migracion_de_expedientes_rehace_lo_que_quedo_a_medias(): void
    {
        $expediente = $this->expediente($this->version(PlantillaChecklist::sgr()->id));
        $migracion = $this->migracionExpedientes();
        $migracion->down();
```

por:

```php

    public function test_reintentar_la_migracion_de_expedientes_rehace_lo_que_quedo_a_medias(): void
    {
        $this->bajarRetiroDeVersion();
        $expediente = $this->expediente($this->version(PlantillaChecklist::sgr()->id));
        $migracion = $this->migracionExpedientes();
        $migracion->down();
```

En `tests/Feature/Schema/RequisitosPropiosTest.php`, reemplazar (1 de 1):

```php
{
    use RefreshDatabase;

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_08_100009_add_requisitos_propios_table.php');
```

por:

```php
{
    use RefreshDatabase;

    /*
     * Estas pruebas ensayan la migración 10 sobre el esquema que ella deja, con
     * proyectos_expedientes.catalogo_version_id. La migración 11 la retira: se baja antes de
     * crear filas, así su reconstrucción de la tabla en SQLite no borra nada en cascada.
     */
    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2026_11_09_100010_drop_catalogo_version_de_expedientes.php'))->down();
    }

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_08_100009_add_requisitos_propios_table.php');
```

En `tests/Feature/Schema/SeguimientoRequisitoTest.php`, reemplazar (1 de 1):

```php
        $requisito = DB::table('requisitos_catalogo')->value('id') ?? $this->requisito($version);
        $expediente = DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => DB::table('proyectos')->insertGetId(['nombre' => 'Prueba']),
            'catalogo_version_id' => $version, 'fase_sgr' => 3, 'estado' => 'borrador',
        ]);

        return DB::table('proyectos_requisitos')->insertGetId([
```

por:

```php
        $requisito = DB::table('requisitos_catalogo')->value('id') ?? $this->requisito($version);
        $expediente = DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => DB::table('proyectos')->insertGetId(['nombre' => 'Prueba']),
            'fase_sgr' => 3, 'estado' => 'borrador',
        ]);

        return DB::table('proyectos_requisitos')->insertGetId([
```

En `tests/Feature/Viabilizacion/CatalogoRequisitosApiTest.php`, reemplazar (1 de 1):

```php

        // El expediente viejo sigue en v1, con su texto y sus 8 requisitos, aun recalculando.
        $viejo = $this->admin()->putJson("/GestPro/expedientes/{$antes->json('id')}/configuracion", ['fase_sgr' => 3])->assertOk();
        $viejo->assertJsonPath('catalogo_version.numero', 1)->assertJsonPath('progreso.total', 8);
        $this->assertSame($textoOriginal, collect($viejo->json('requisitos'))->firstWhere('requisito.clave', 'a1')['requisito']['texto']);

        // Uno nuevo usa v2.
        $otroProyecto = $this->proyectoSgr(['municipio' => $municipio->codigo, 'nombre' => 'Vía secundaria']);
        $nuevo = $this->admin()->postJson("/GestPro/proyectos/{$otroProyecto->id}/expedientes", ['fase_sgr' => 3])->assertCreated();
        $nuevo->assertJsonPath('catalogo_version.numero', 2)->assertJsonPath('progreso.total', 7);
        $this->assertSame('Texto v2', collect($nuevo->json('requisitos'))->firstWhere('requisito.clave', 'a1')['requisito']['texto']);
    }
```

por:

```php

        // El expediente viejo sigue en v1, con su texto y sus 8 requisitos, aun recalculando.
        $viejo = $this->admin()->putJson("/GestPro/expedientes/{$antes->json('id')}/configuracion", ['fase_sgr' => 3])->assertOk();
        $viejo->assertJsonPath('plantillas.0.version.numero', 1)->assertJsonPath('progreso.total', 8);
        $this->assertSame($textoOriginal, collect($viejo->json('requisitos'))->firstWhere('requisito.clave', 'a1')['requisito']['texto']);

        // Uno nuevo usa v2.
        $otroProyecto = $this->proyectoSgr(['municipio' => $municipio->codigo, 'nombre' => 'Vía secundaria']);
        $nuevo = $this->admin()->postJson("/GestPro/proyectos/{$otroProyecto->id}/expedientes", ['fase_sgr' => 3])->assertCreated();
        $nuevo->assertJsonPath('plantillas.0.version.numero', 2)->assertJsonPath('progreso.total', 7);
        $this->assertSame('Texto v2', collect($nuevo->json('requisitos'))->firstWhere('requisito.clave', 'a1')['requisito']['texto']);
    }
```

En `tests/Feature/Viabilizacion/ExpedienteApiTest.php`, reemplazar (1 de 1):

```php
        $r->assertCreated()
            ->assertJsonPath('estado', 'borrador')
            ->assertJsonPath('fase_sgr', 3)
            ->assertJsonPath('catalogo_version.numero', 1)
            ->assertJsonPath('progreso.total', 8)
            ->assertJsonPath('progreso.cumplidos', 0);
        $this->assertSame(['a1', 'a2', 'a3', 'a4', 'a8', 'a9', 'a10', 'a11'], $this->claves($r));
```

por:

```php
        $r->assertCreated()
            ->assertJsonPath('estado', 'borrador')
            ->assertJsonPath('fase_sgr', 3)
            ->assertJsonPath('plantillas.0.version.numero', 1)
            ->assertJsonPath('progreso.total', 8)
            ->assertJsonPath('progreso.cumplidos', 0);
        $this->assertSame(['a1', 'a2', 'a3', 'a4', 'a8', 'a9', 'a10', 'a11'], $this->claves($r));
```

En `tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`, reemplazar (1 de 1):

```php

        $expediente = Proyecto::where('nombre', 'Recursos propios')->firstOrFail()->expedientes()->sole();
        $this->assertNull($expediente->fecha_configuracion);
        $this->assertNull($expediente->catalogo_version_id);
        $this->assertSame(0, $expediente->versionesPlantilla()->count());
        $this->assertSame(0, $expediente->sectores()->count());
        $this->assertSame(0, $expediente->requisitos()->count());
```

por:

```php

        $expediente = Proyecto::where('nombre', 'Recursos propios')->firstOrFail()->expedientes()->sole();
        $this->assertNull($expediente->fecha_configuracion);
        $this->assertSame(0, $expediente->versionesPlantilla()->count());
        $this->assertSame(0, $expediente->sectores()->count());
        $this->assertSame(0, $expediente->requisitos()->count());
```

En `tests/Feature/Viabilizacion/ExpedienteConfiguracionValidacionTest.php`, reemplazar (1 de 2):

```php
        $registro = DB::table('checklist_historial')->where('evento', 'expediente_creado')->where('expediente_id', $id)->sole();
        $despues = json_decode($registro->despues, true);

        $this->assertSame($version->id, $despues['catalogo_version_id']);
        $this->assertSame(
            [['plantilla_id' => $this->sgr->id, 'catalogo_version_id' => $version->id, 'es_principal' => true]],
            $despues['plantillas']
```

por:

```php
        $registro = DB::table('checklist_historial')->where('evento', 'expediente_creado')->where('expediente_id', $id)->sole();
        $despues = json_decode($registro->despues, true);

        // La versión va con su plantilla, no aparte.
        $this->assertArrayNotHasKey('catalogo_version_id', $despues);
        $this->assertSame(
            [['plantilla_id' => $this->sgr->id, 'catalogo_version_id' => $version->id, 'es_principal' => true]],
            $despues['plantillas']
```

En `tests/Feature/Viabilizacion/ExpedienteConfiguracionValidacionTest.php`, reemplazar (2 de 2):

```php

        $expediente = ProyectoExpediente::sole();
        $this->assertSame(0, $expediente->versionesPlantilla()->count());
        $this->assertNull($expediente->catalogo_version_id);
        $this->assertSame(0, app(ExpedienteService::class)->progresoDe($expediente)['total']);
    }
}
```

por:

```php

        $expediente = ProyectoExpediente::sole();
        $this->assertSame(0, $expediente->versionesPlantilla()->count());
        $this->assertSame(0, app(ExpedienteService::class)->progresoDe($expediente)['total']);
    }
}
```

En `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`, reemplazar (1 de 3):

```php
            'compatible' => true, 'version' => ['id' => $version->id, 'numero' => 1],
        ]])
            ->assertJsonPath('tiene_sgr', true)
            ->assertJsonPath('catalogo_version', ['id' => $version->id, 'numero' => 1])
            ->assertJsonPath('progreso.total', 8)
            ->assertJsonPath('avisos_duplicados', []);
    }

    public function test_sin_plantilla_sgr_compatible_nace_sin_plantillas_y_sin_pedir_la_fase(): void
    {
        $r = $this->crear($this->proyecto(['recursos_propios']), [])->assertCreated();

        $r->assertJsonPath('plantillas', [])
            ->assertJsonPath('tiene_sgr', false)
            ->assertJsonPath('catalogo_version', null)
            ->assertJsonPath('fase_sgr', 3)
            ->assertJsonPath('requisitos', [])
            ->assertJsonPath('progreso.total', 0);
        $this->assertNull(ProyectoExpediente::find($r->json('id'))->catalogo_version_id);
    }

    public function test_crea_con_las_plantillas_pedidas(): void
```

por:

```php
            'compatible' => true, 'version' => ['id' => $version->id, 'numero' => 1],
        ]])
            ->assertJsonPath('tiene_sgr', true)
            ->assertJsonPath('progreso.total', 8)
            ->assertJsonPath('avisos_duplicados', []);
    }

    public function test_la_version_sgr_solo_viene_en_las_plantillas_del_expediente(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $proyecto = $this->proyecto();
        $version = $this->sgr->versionVigente();
        $sgr = ['plantilla_id' => $this->sgr->id, 'version' => ['id' => $version->id, 'numero' => 1]];
        $soloSgr = fn (array $plantillas) => collect($plantillas)->where('motor', 'sgr')
            ->map(fn ($p) => ['plantilla_id' => $p['plantilla_id'], 'version' => $p['version']])->values()->all();

        $creado = $this->crear($proyecto, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $agua->id])
            ->assertCreated()->assertJsonMissingPath('catalogo_version');
        $this->assertSame([$sgr], $soloSgr($creado->json('plantillas')));

        $detalle = $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$creado->json('id')}")
            ->assertOk()->assertJsonMissingPath('catalogo_version');
        $this->assertSame([$sgr], $soloSgr($detalle->json('plantillas')));

        $listado = $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$proyecto->id}/expedientes")
            ->assertOk()->assertJsonMissingPath('expedientes.0.catalogo_version');
        $this->assertSame([$sgr], $soloSgr($listado->json('expedientes.0.plantillas')));
    }

    public function test_sin_plantilla_sgr_compatible_nace_sin_plantillas_y_sin_pedir_la_fase(): void
    {
        $r = $this->crear($this->proyecto(['recursos_propios']), [])->assertCreated();

        $r->assertJsonPath('plantillas', [])
            ->assertJsonPath('tiene_sgr', false)
            ->assertJsonPath('fase_sgr', 3)
            ->assertJsonPath('requisitos', [])
            ->assertJsonPath('progreso.total', 0);
    }

    public function test_crea_con_las_plantillas_pedidas(): void
```

En `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`, reemplazar (2 de 3):

```php
        $r->assertJsonPath('plantillas.0.plantilla_id', $agua->id)
            ->assertJsonPath('plantillas.0.es_principal', true)
            ->assertJsonPath('tiene_sgr', false)
            ->assertJsonPath('catalogo_version', null)
            ->assertJsonPath('progreso.total', 2);
        $this->assertSame(['Planos firmados', 'Licencia'], collect($r->json('requisitos'))->pluck('requisito.texto')->all());
    }
```

por:

```php
        $r->assertJsonPath('plantillas.0.plantilla_id', $agua->id)
            ->assertJsonPath('plantillas.0.es_principal', true)
            ->assertJsonPath('tiene_sgr', false)
            ->assertJsonPath('progreso.total', 2);
        $this->assertSame(['Planos firmados', 'Licencia'], collect($r->json('requisitos'))->pluck('requisito.texto')->all());
    }
```

En `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`, reemplazar (3 de 3):

```php

        // Sin la SGR no hace falta la fase, y sus filas quedan retiradas.
        $sin = $this->configurar($id, ['plantilla_ids' => [$agua->id]])->assertOk();
        $sin->assertJsonPath('tiene_sgr', false)->assertJsonPath('catalogo_version', null)
            ->assertJsonPath('progreso.total', 1)->assertJsonPath('fase_sgr', 3);
        $this->assertSame(8, ProyectoExpediente::find($id)->requisitos()->where('aplica', false)->count());
```

por:

```php

        // Sin la SGR no hace falta la fase, y sus filas quedan retiradas.
        $sin = $this->configurar($id, ['plantilla_ids' => [$agua->id]])->assertOk();
        $sin->assertJsonPath('tiene_sgr', false)
            ->assertJsonPath('progreso.total', 1)->assertJsonPath('fase_sgr', 3);
        $this->assertSame(8, ProyectoExpediente::find($id)->requisitos()->where('aplica', false)->count());
```

En `tests/Feature/Viabilizacion/PlantillasAdminApiTest.php`, reemplazar (1 de 1):

```php
        $this->como(Rol::Gestor)->postJson("/GestPro/plantillas/{$id}/versiones", [])->assertForbidden();
    }

    public function test_el_detalle_ordena_las_versiones_de_la_mas_nueva_a_la_mas_antigua(): void
    {
        $sgr = PlantillaChecklist::sgr();
```

por:

```php
        $this->como(Rol::Gestor)->postJson("/GestPro/plantillas/{$id}/versiones", [])->assertForbidden();
    }

    public function test_cada_version_cuenta_los_expedientes_que_la_usan_sea_principal_o_complementaria(): void
    {
        $sgr = PlantillaChecklist::sgr();
        $otra = $this->crear()->assertCreated()->json('id');
        $expediente = DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => DB::table('proyectos')->insertGetId(['nombre' => 'Prueba']), 'fase_sgr' => 3, 'estado' => 'borrador',
        ]);
        DB::table('expediente_plantillas')->insert([
            ['expediente_id' => $expediente, 'catalogo_version_id' => $sgr->versionVigente()->id, 'es_principal' => false],
            ['expediente_id' => $expediente, 'catalogo_version_id' => DB::table('catalogo_versiones')->where('plantilla_id', $otra)->value('id'), 'es_principal' => true],
        ]);

        $this->como(Rol::Administrador)->getJson("/GestPro/plantillas/{$sgr->id}")->assertOk()
            ->assertJsonPath('versiones.0.expedientes', 1);
        $this->como(Rol::Administrador)->getJson("/GestPro/plantillas/{$otra}")->assertOk()
            ->assertJsonPath('versiones.0.expedientes', 1);
        $this->como(Rol::Administrador)->getJson('/GestPro/catalogo-requisitos/versiones')->assertOk()
            ->assertJsonPath('0.expedientes', 1);
    }

    public function test_el_detalle_ordena_las_versiones_de_la_mas_nueva_a_la_mas_antigua(): void
    {
        $sgr = PlantillaChecklist::sgr();
```

En `tests/Feature/Viabilizacion/PlantillasExpedienteApiTest.php`, reemplazar (1 de 2):

```php
        $this->assertSame([], $ninguna['versiones']);
    }

    public function test_guardar_registra_las_versiones_la_principal_y_la_version_sgr(): void
    {
        $proyecto = $this->proyecto(['sgr']);
        $general = $this->plantilla('General');
```

por:

```php
        $this->assertSame([], $ninguna['versiones']);
    }

    public function test_guardar_registra_las_versiones_y_la_principal(): void
    {
        $proyecto = $this->proyecto(['sgr']);
        $general = $this->plantilla('General');
```

En `tests/Feature/Viabilizacion/PlantillasExpedienteApiTest.php`, reemplazar (2 de 2):

```php
            ['catalogo_version_id' => $general->versionVigente()->id, 'es_principal' => 1],
        ], DB::table('expediente_plantillas')->where('expediente_id', $expediente->id)->get(['catalogo_version_id', 'es_principal'])
            ->map(fn ($f) => ['catalogo_version_id' => (int) $f->catalogo_version_id, 'es_principal' => (int) $f->es_principal])->all());
        $this->assertSame($sgrVersion, $expediente->refresh()->catalogo_version_id);

        // Sin la SGR, el expediente ya no tiene versión SGR.
        $this->servicio()->guardar($expediente, $this->servicio()->validar(['plantilla_ids' => [$general->id]], $proyecto, $expediente));

        $this->assertSame([$general->versionVigente()->id], DB::table('expediente_plantillas')->where('expediente_id', $expediente->id)->pluck('catalogo_version_id')->map(fn ($id) => (int) $id)->all());
        $this->assertNull($expediente->refresh()->catalogo_version_id);
    }

    public function test_una_plantilla_que_ya_tiene_el_expediente_conserva_su_version_aunque_ya_no_se_ofrezca(): void
```

por:

```php
            ['catalogo_version_id' => $general->versionVigente()->id, 'es_principal' => 1],
        ], DB::table('expediente_plantillas')->where('expediente_id', $expediente->id)->get(['catalogo_version_id', 'es_principal'])
            ->map(fn ($f) => ['catalogo_version_id' => (int) $f->catalogo_version_id, 'es_principal' => (int) $f->es_principal])->all());

        // Sin la SGR, su versión sale del expediente.
        $this->servicio()->guardar($expediente, $this->servicio()->validar(['plantilla_ids' => [$general->id]], $proyecto, $expediente));

        $this->assertSame([$general->versionVigente()->id], DB::table('expediente_plantillas')->where('expediente_id', $expediente->id)->pluck('catalogo_version_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_una_plantilla_que_ya_tiene_el_expediente_conserva_su_version_aunque_ya_no_se_ofrezca(): void
```

En `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`, reemplazar (1 de 2):

```php
        $expediente = app(ExpedienteService::class)
            ->crear($this->proyectoSgr(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);

        $this->assertSame($sgr->id, $expediente->catalogo_version_id);
    }

    public function test_la_lista_de_versiones_solo_muestra_las_de_sgr(): void
```

por:

```php
        $expediente = app(ExpedienteService::class)
            ->crear($this->proyectoSgr(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);

        $this->assertSame([$sgr->id], $expediente->versionesPlantilla()->pluck('catalogo_versiones.id')->all());
    }

    public function test_la_lista_de_versiones_solo_muestra_las_de_sgr(): void
```

En `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`, reemplazar (2 de 2):

```php
        $expediente = $servicio->crear($this->proyectoSgr(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);

        $version = $expediente->versionesPlantilla()->sole();
        $this->assertSame($expediente->catalogo_version_id, $version->id);
        $this->assertEquals(1, $version->pivot->es_principal);

        $servicio->eliminar($expediente);
```

por:

```php
        $expediente = $servicio->crear($this->proyectoSgr(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);

        $version = $expediente->versionesPlantilla()->sole();
        $this->assertSame(PlantillaChecklist::sgr()->versionVigente()->id, $version->id);
        $this->assertEquals(1, $version->pivot->es_principal);

        $servicio->eliminar($expediente);
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Schema/RetiroVersionExpedienteTest.php tests/Feature/Schema/CatalogoRequisitosTest.php tests/Feature/Schema/PlantillasChecklistTest.php tests/Feature/Schema/RequisitosPropiosTest.php tests/Feature/Schema/SeguimientoRequisitoTest.php tests/Feature/Viabilizacion/CatalogoRequisitosApiTest.php tests/Feature/Viabilizacion/ExpedienteApiTest.php tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php tests/Feature/Viabilizacion/ExpedienteConfiguracionValidacionTest.php tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php tests/Feature/Viabilizacion/PlantillasAdminApiTest.php tests/Feature/Viabilizacion/PlantillasExpedienteApiTest.php tests/Feature/Viabilizacion/PlantillasVersionesTest.php`
Expected: FALLAN 31 y pasan 243. Fallan las de `RetiroVersionExpedienteTest`, porque la migración 11 no existe y la columna sigue ahí. También fallan las pruebas existentes que ya no escriben la columna o que esperan que la API no traiga `catalogo_version`.

Run: `npx vitest run resources/js/utils/expedienteChecklist.test.js`
Expected: pasan las 80: en esta prueba solo se quita `catalogo_version` de tres datos de ejemplo.

- [ ] **Paso 3: Implementar**

Crear `database/migrations/2026_11_09_100010_drop_catalogo_version_de_expedientes.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FORANEA = 'proyectos_expedientes_catalogo_version_id_foreign';

    /** Cuántos expedientes nombra, como mucho, el mensaje que detiene la migración. */
    private const MAX_LISTADOS = 10;

    public function up(): void
    {
        // La versión de cada plantilla del expediente vive en expediente_plantillas. Una rama
        // anterior que cree expedientes sobre la base ya migrada escribiría solo esta columna:
        // antes de quitarla se comprueba que no se pierde ninguna versión.
        if (Schema::hasColumn('proyectos_expedientes', 'catalogo_version_id')) {
            $this->exigirVersionesRegistradas();
        }

        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. Primero la clave foránea, después la columna.
        // Se quita por el nombre que entrega el esquema; SQLite no da nombre y ahí se deja la
        // deducción de Laravel.
        if ($foranea = $this->foranea()) {
            $nombre = $foranea['name'] ?? ['catalogo_version_id'];
            Schema::table('proyectos_expedientes', function (Blueprint $table) use ($nombre) {
                $table->dropForeign($nombre);
            });
        }
        if (Schema::hasColumn('proyectos_expedientes', 'catalogo_version_id')) {
            Schema::table('proyectos_expedientes', function (Blueprint $table) {
                $table->dropColumn('catalogo_version_id');
            });
        }
    }

    public function down(): void
    {
        // Se restaura como la dejó la migración 10: opcional, con la versión de la plantilla con
        // motor sgr del expediente o null si no la tiene. SQLite ignora la posición.
        if (! Schema::hasColumn('proyectos_expedientes', 'catalogo_version_id')) {
            Schema::table('proyectos_expedientes', function (Blueprint $table) {
                $table->unsignedBigInteger('catalogo_version_id')->nullable()->after('proyecto_id');
            });
        }

        // Se llena antes de la clave foránea: en SQLite agregarla reconstruye la tabla. El
        // servicio admite como máximo una plantilla sgr por expediente; volver a llenarla en
        // un reintento da el mismo resultado.
        $versionSgr = DB::table('expediente_plantillas')
            ->join('catalogo_versiones', 'catalogo_versiones.id', '=', 'expediente_plantillas.catalogo_version_id')
            ->join('plantillas_checklist', 'plantillas_checklist.id', '=', 'catalogo_versiones.plantilla_id')
            ->whereColumn('expediente_plantillas.expediente_id', 'proyectos_expedientes.id')
            ->where('plantillas_checklist.motor', 'sgr')
            ->select('expediente_plantillas.catalogo_version_id')
            ->limit(1);
        // Con una consulta como valor de update(), la gramática de SQLite de Laravel no pasa sus
        // parámetros: la sentencia se arma aquí, con la subconsulta correlacionada.
        $gramatica = DB::getQueryGrammar();
        DB::update(
            sprintf('update %s set %s = (%s)',
                $gramatica->wrapTable('proyectos_expedientes'), $gramatica->wrap('catalogo_version_id'), $versionSgr->toSql()),
            $versionSgr->getBindings()
        );

        if (! $this->foranea()) {
            Schema::table('proyectos_expedientes', function (Blueprint $table) {
                $table->foreign('catalogo_version_id', self::FORANEA)
                    ->references('id')->on('catalogo_versiones')->restrictOnDelete();
            });
        }
    }

    /** Se detiene si algún expediente tiene una versión que no figura en expediente_plantillas. */
    private function exigirVersionesRegistradas(): void
    {
        $sinRegistro = DB::table('proyectos_expedientes')
            ->whereNotNull('catalogo_version_id')
            ->whereNotExists(fn ($q) => $q->from('expediente_plantillas')
                ->whereColumn('expediente_plantillas.expediente_id', 'proyectos_expedientes.id')
                ->whereColumn('expediente_plantillas.catalogo_version_id', 'proyectos_expedientes.catalogo_version_id'));

        $total = (clone $sinRegistro)->count();
        if ($total === 0) {
            return;
        }

        $ids = $sinRegistro->orderBy('id')->limit(self::MAX_LISTADOS)->pluck('id')->implode(', ');
        $resto = $total > self::MAX_LISTADOS ? ' y ' . ($total - self::MAX_LISTADOS) . ' más' : '';
        throw new RuntimeException($total === 1
            ? "No se puede migrar: el expediente {$ids} tiene una versión de catálogo que no figura en expediente_plantillas y se perdería. Revíselo antes de migrar."
            : "No se puede migrar: los expedientes {$ids}{$resto} tienen una versión de catálogo que no figura en expediente_plantillas y se perdería. Revíselos antes de migrar."
        );
    }

    private function foranea(): ?array
    {
        return collect(Schema::getForeignKeys('proyectos_expedientes'))
            ->first(fn ($f) => $f['columns'] === ['catalogo_version_id']);
    }
};
```

En `app/Models/CatalogoVersion.php`, reemplazar (1 de 2):

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoVersion extends Model
```

por:

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoVersion extends Model
```

En `app/Models/CatalogoVersion.php`, reemplazar (2 de 2):

```php
        return $this->hasMany(RequisitoCatalogo::class, 'catalogo_version_id');
    }

    public function expedientes(): HasMany
    {
        return $this->hasMany(ProyectoExpediente::class, 'catalogo_version_id');
    }

    public function scopeVigente(Builder $query): Builder
```

por:

```php
        return $this->hasMany(RequisitoCatalogo::class, 'catalogo_version_id');
    }

    /** Expedientes que usan esta versión, como principal o complementaria. */
    public function expedientes(): BelongsToMany
    {
        return $this->belongsToMany(ProyectoExpediente::class, 'expediente_plantillas', 'catalogo_version_id', 'expediente_id')
            ->withPivot('es_principal');
    }

    public function scopeVigente(Builder $query): Builder
```

En `app/Models/ProyectoExpediente.php`, reemplazar (1 de 2):

```php

    protected $table = 'proyectos_expedientes';
    protected $fillable = [
        'proyecto_id', 'catalogo_version_id', 'fase_sgr', 'fecha_configuracion', 'estado',
        'fecha_radicado', 'usuario_radicado_id', 'usuario_creo_id',
    ];
    protected $casts = [
        'proyecto_id' => 'integer',
        'catalogo_version_id' => 'integer',
        'fase_sgr' => 'integer',
        'fecha_configuracion' => 'datetime',
        'fecha_radicado' => 'datetime',
```

por:

```php

    protected $table = 'proyectos_expedientes';
    protected $fillable = [
        'proyecto_id', 'fase_sgr', 'fecha_configuracion', 'estado',
        'fecha_radicado', 'usuario_radicado_id', 'usuario_creo_id',
    ];
    protected $casts = [
        'proyecto_id' => 'integer',
        'fase_sgr' => 'integer',
        'fecha_configuracion' => 'datetime',
        'fecha_radicado' => 'datetime',
```

En `app/Models/ProyectoExpediente.php`, reemplazar (2 de 2):

```php
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    /** Versión de la plantilla SGR del expediente; null si no tiene plantilla con motor sgr. */
    public function version(): BelongsTo
    {
        return $this->belongsTo(CatalogoVersion::class, 'catalogo_version_id');
    }

    /** Versiones de plantilla que usa el expediente. La etapa 5 permite más de una. */
    public function versionesPlantilla(): BelongsToMany
    {
```

por:

```php
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    /** Versiones de plantilla que usa el expediente. La etapa 5 permite más de una. */
    public function versionesPlantilla(): BelongsToMany
    {
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 4):

```php
            $this->guardarSeleccion($expediente, $seleccion);
            $this->sincronizar($expediente);
            $this->historial->registrarExpediente($expediente, 'expediente_creado', [], [
                'catalogo_version_id' => $expediente->catalogo_version_id,
                'plantillas' => $this->plantillasRegistrables($this->plantillas->versionesDe($expediente)),
            ], $usuario);
```

por:

```php
            $this->guardarSeleccion($expediente, $seleccion);
            $this->sincronizar($expediente);
            $this->historial->registrarExpediente($expediente, 'expediente_creado', [], [
                'plantillas' => $this->plantillasRegistrables($this->plantillas->versionesDe($expediente)),
            ], $usuario);
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 4):

```php
    public function presentar(ProyectoExpediente $expediente): array
    {
        $expediente->load([
            'proyecto:id,nombre,sector', 'version:id,numero', 'usuarioRadico:id,name', 'usuarioCreo:id,name',
            'sectores', 'tipos', 'componentes', 'tipologias',
        ]);
        $versiones = $this->plantillas->versionesDe($expediente);
```

por:

```php
    public function presentar(ProyectoExpediente $expediente): array
    {
        $expediente->load([
            'proyecto:id,nombre,sector', 'usuarioRadico:id,name', 'usuarioCreo:id,name',
            'sectores', 'tipos', 'componentes', 'tipologias',
        ]);
        $versiones = $this->plantillas->versionesDe($expediente);
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (3 de 4):

```php
    public function presentarDeProyecto(Proyecto $proyecto): array
    {
        $expedientes = $proyecto->expedientes()
            ->with(['versionesPlantilla.plantilla', 'version:id,numero', 'usuarioRadico:id,name', 'usuarioCreo:id,name'])
            ->withCount([
                'requisitos as requisitos_aplican' => fn ($q) => $q->cuenta(),
                'requisitos as requisitos_cumplidos' => fn ($q) => $q->cumplidaQueCuenta(),
```

por:

```php
    public function presentarDeProyecto(Proyecto $proyecto): array
    {
        $expedientes = $proyecto->expedientes()
            ->with(['versionesPlantilla.plantilla', 'usuarioRadico:id,name', 'usuarioCreo:id,name'])
            ->withCount([
                'requisitos as requisitos_aplican' => fn ($q) => $q->cuenta(),
                'requisitos as requisitos_cumplidos' => fn ($q) => $q->cumplidaQueCuenta(),
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (4 de 4):

```php
     */
    public function presentarCabecera(ProyectoExpediente $expediente, ?Collection $versiones = null, ?array $compatibles = null): array
    {
        $expediente->loadMissing(['proyecto:id,nombre,sector', 'version:id,numero', 'usuarioRadico:id,name', 'usuarioCreo:id,name']);
        $versiones ??= $this->plantillas->versionesDe($expediente);
        $compatibles ??= $versiones->isEmpty() ? [] : $this->plantillas->idsCompatibles($expediente->proyecto);

        return [
            'id' => $expediente->id,
            'proyecto' => ['id' => $expediente->proyecto->id, 'nombre' => $expediente->proyecto->nombre],
            // Solo con plantilla SGR: es la versión que siguen las pantallas y rutas del catálogo SGR.
            'catalogo_version' => $expediente->version ? ['id' => $expediente->version->id, 'numero' => $expediente->version->numero] : null,
            'plantillas' => $versiones->map(fn (CatalogoVersion $v) => [
                'plantilla_id' => $v->plantilla_id,
                'nombre' => $v->plantilla->nombre,
```

por:

```php
     */
    public function presentarCabecera(ProyectoExpediente $expediente, ?Collection $versiones = null, ?array $compatibles = null): array
    {
        $expediente->loadMissing(['proyecto:id,nombre,sector', 'usuarioRadico:id,name', 'usuarioCreo:id,name']);
        $versiones ??= $this->plantillas->versionesDe($expediente);
        $compatibles ??= $versiones->isEmpty() ? [] : $this->plantillas->idsCompatibles($expediente->proyecto);

        return [
            'id' => $expediente->id,
            'proyecto' => ['id' => $expediente->proyecto->id, 'nombre' => $expediente->proyecto->nombre],
            'plantillas' => $versiones->map(fn (CatalogoVersion $v) => [
                'plantilla_id' => $v->plantilla_id,
                'nombre' => $v->plantilla->nombre,
```

En `app/Services/Viabilizacion/PlantillasExpediente.php`, reemplazar (1 de 3):

```php
 * - Una plantilla nueva en el expediente entra con su versión vigente; la que ya tenía
 *   conserva su versión aunque haya dejado de ofrecerse (sin cambios retroactivos).
 * - Como máximo una plantilla con motor sgr y 20 en total; si hay plantillas, una es la principal.
 * - proyectos_expedientes.catalogo_version_id guarda la versión de la plantilla sgr, o null.
 */
final class PlantillasExpediente
{
```

por:

```php
 * - Una plantilla nueva en el expediente entra con su versión vigente; la que ya tenía
 *   conserva su versión aunque haya dejado de ofrecerse (sin cambios retroactivos).
 * - Como máximo una plantilla con motor sgr y 20 en total; si hay plantillas, una es la principal.
 * - expediente_plantillas guarda la versión de cada plantilla del expediente, también la sgr.
 */
final class PlantillasExpediente
{
```

En `app/Services/Viabilizacion/PlantillasExpediente.php`, reemplazar (2 de 3):

```php
    }

    /**
     * Deja el expediente con las plantillas elegidas, sus versiones y la versión SGR.
     *
     * Se llama dentro de la transacción que bloqueó el expediente y validó la selección
     * (ExpedienteService). Abre la suya, que entonces es un savepoint: el pivote y
     * catalogo_version_id nunca quedan a medias.
     */
    public function guardar(ProyectoExpediente $expediente, array $seleccion): void
    {
```

por:

```php
    }

    /**
     * Deja el expediente con las plantillas elegidas, sus versiones y la principal.
     *
     * Se llama dentro de la transacción que bloqueó el expediente y validó la selección
     * (ExpedienteService). sync() escribe varias sentencias: abre la suya, que entonces es un
     * savepoint, para que el pivote nunca quede a medias.
     */
    public function guardar(ProyectoExpediente $expediente, array $seleccion): void
    {
```

En `app/Services/Viabilizacion/PlantillasExpediente.php`, reemplazar (3 de 3):

```php
                    ->mapWithKeys(fn (int $version, int $plantilla) => [$version => ['es_principal' => $plantilla === $seleccion['principal_id']]])
                    ->all()
            );
            $expediente->update(['catalogo_version_id' => $seleccion['version_sgr_id']]);
        });
        $expediente->unsetRelation('versionesPlantilla')->unsetRelation('version');
    }
}
```

por:

```php
                    ->mapWithKeys(fn (int $version, int $plantilla) => [$version => ['es_principal' => $plantilla === $seleccion['principal_id']]])
                    ->all()
            );
        });
        $expediente->unsetRelation('versionesPlantilla');
    }
}
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Schema/RetiroVersionExpedienteTest.php tests/Feature/Schema/CatalogoRequisitosTest.php tests/Feature/Schema/PlantillasChecklistTest.php tests/Feature/Schema/RequisitosPropiosTest.php tests/Feature/Schema/SeguimientoRequisitoTest.php tests/Feature/Viabilizacion/CatalogoRequisitosApiTest.php tests/Feature/Viabilizacion/ExpedienteApiTest.php tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php tests/Feature/Viabilizacion/ExpedienteConfiguracionValidacionTest.php tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php tests/Feature/Viabilizacion/PlantillasAdminApiTest.php tests/Feature/Viabilizacion/PlantillasExpedienteApiTest.php tests/Feature/Viabilizacion/PlantillasVersionesTest.php`
Expected: pasan todas.

Run: `npx vitest run resources/js/utils/expedienteChecklist.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 600 passed.

Run: `npm test`
Expected: 293 passed.

- [ ] **Paso 5: Commit**

```bash
git add database/migrations/2026_11_09_100010_drop_catalogo_version_de_expedientes.php tests/Feature/Schema/RetiroVersionExpedienteTest.php app/Models/CatalogoVersion.php app/Models/ProyectoExpediente.php app/Services/Viabilizacion/ExpedienteService.php app/Services/Viabilizacion/PlantillasExpediente.php resources/js/utils/expedienteChecklist.test.js tests/Feature/Schema/CatalogoRequisitosTest.php tests/Feature/Schema/PlantillasChecklistTest.php tests/Feature/Schema/RequisitosPropiosTest.php tests/Feature/Schema/SeguimientoRequisitoTest.php tests/Feature/Viabilizacion/CatalogoRequisitosApiTest.php tests/Feature/Viabilizacion/ExpedienteApiTest.php tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php tests/Feature/Viabilizacion/ExpedienteConfiguracionValidacionTest.php tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php tests/Feature/Viabilizacion/PlantillasAdminApiTest.php tests/Feature/Viabilizacion/PlantillasExpedienteApiTest.php tests/Feature/Viabilizacion/PlantillasVersionesTest.php
git commit -m "feat: drop catalogo_version_id from expedientes (migration 11)"
```

---

### Tarea 3: Aplicar una versión nueva de una plantilla al expediente

Servicio `VersionesExpediente`, sus dos rutas y `version_nueva` en la cabecera. Incluye el desempate por trabajo en `recalcular`.

**Files:**
- Create: `app/Services/Viabilizacion/VersionesExpediente.php`
- Test (create): `tests/Feature/Viabilizacion/VersionesExpedienteTest.php`
- Modify: `app/Http/Controllers/PlantillasExpedienteController.php`
- Modify: `app/Models/ProyectoRequisito.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `app/Services/Viabilizacion/PlantillasExpediente.php`
- Modify: `routes/viabilizacion.php`
- Test: `tests/Feature/Viabilizacion/ComposicionExpedienteTest.php`
- Test: `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Viabilizacion/VersionesExpedienteTest.php`:

```php
<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\ChecklistHistorial;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\Responsable;
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\RequisitosPropios;
use App\Services\Viabilizacion\VersionesExpediente;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Aplicar a un expediente la versión nueva de una de sus plantillas, con vista previa. */
class VersionesExpedienteTest extends TestCase
{
    use RefreshDatabase;

    private ExpedienteService $servicio;
    private PlantillaChecklist $sgr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->servicio = app(ExpedienteService::class);
        $this->sgr = PlantillaChecklist::sgr();
    }

    private function como(Rol $rol = Rol::Gestor, ?User $usuario = null): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario ?? User::factory()->create(['rol' => $rol])));
    }

    /**
     * Plantilla simple con su versión 1 vigente.
     *
     * @param array<string, array{0: string, 1: ?string}> $requisitos clave => [texto, codigo_comun]
     */
    private function plantilla(string $nombre, array $requisitos): PlantillaChecklist
    {
        $plantilla = PlantillaChecklist::create([
            'nombre' => $nombre, 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'activo' => true,
            'todas_fuentes' => true, 'todos_sectores' => true,
        ]);
        $version = $plantilla->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        $orden = 0;
        foreach ($requisitos as $clave => [$texto, $codigoComun]) {
            RequisitoCatalogo::create([
                'catalogo_version_id' => $version->id, 'clave' => $clave, 'texto' => $texto, 'orden' => ++$orden,
                'codigo' => strtoupper($clave), 'codigo_comun' => $codigoComun, 'caracter' => RequisitoCatalogo::OBLIGATORIO,
            ]);
        }

        return $plantilla;
    }

    /** Publica una versión nueva de la plantilla, copiada de la vigente y editada con $editar. */
    private function publicarVersion(PlantillaChecklist $plantilla, ?callable $editar = null): CatalogoVersion
    {
        $catalogo = app(CatalogoService::class);
        $borrador = $catalogo->crearBorrador('Ajustes', $plantilla);
        if ($editar) {
            $editar(fn (string $clave) => $this->requisito($borrador, $clave), $borrador);
        }

        return $catalogo->publicar($borrador);
    }

    private function requisito(CatalogoVersion $version, string $clave): RequisitoCatalogo
    {
        return RequisitoCatalogo::where('catalogo_version_id', $version->id)->where('clave', $clave)->sole();
    }

    private function version(PlantillaChecklist $plantilla, int $numero): CatalogoVersion
    {
        return $plantilla->versiones()->where('numero', $numero)->sole();
    }

    /** Expediente SGR en fase III (8 requisitos generales), con las plantillas dadas además de la SGR. */
    private function expediente(array $otras = [], bool $confirmar = true, ?Proyecto $proyecto = null): ProyectoExpediente
    {
        if (! $proyecto) {
            $proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
            $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        }
        $datos = [
            'fase_sgr' => 3,
            'plantilla_ids' => [$this->sgr->id, ...array_map(fn (PlantillaChecklist $p) => $p->id, $otras)],
            'principal_id' => $this->sgr->id,
        ];
        $expediente = $this->servicio->crear($proyecto, $datos, null);
        if ($confirmar) {
            $this->servicio->confirmarConfiguracion($expediente, $datos);
        }

        return $expediente->refresh();
    }

    /** Fila anclada al requisito de esa clave en la versión dada. */
    private function fila(ProyectoExpediente $expediente, CatalogoVersion $version, string $clave): ProyectoRequisito
    {
        return $expediente->requisitos()->where('requisito_catalogo_id', $this->requisito($version, $clave)->id)->sole();
    }

    private function origenes(ProyectoRequisito $fila): array
    {
        return $fila->origenes()->orderBy('requisitos_catalogo.id')->pluck('requisitos_catalogo.id')->map(fn ($id) => (int) $id)->all();
    }

    private function versionDe(ProyectoExpediente $expediente, PlantillaChecklist $plantilla): array
    {
        return DB::table('expediente_plantillas')
            ->join('catalogo_versiones', 'catalogo_versiones.id', '=', 'expediente_plantillas.catalogo_version_id')
            ->where('expediente_id', $expediente->id)->where('catalogo_versiones.plantilla_id', $plantilla->id)
            ->get(['catalogo_version_id', 'es_principal'])
            ->map(fn ($p) => ['catalogo_version_id' => (int) $p->catalogo_version_id, 'es_principal' => (bool) $p->es_principal])
            ->all();
    }

    private function aplicar(ProyectoExpediente $expediente, PlantillaChecklist $plantilla, mixed $versionId, Rol $rol = Rol::Gestor)
    {
        return $this->como($rol)->postJson("/GestPro/expedientes/{$expediente->id}/plantillas/{$plantilla->id}/version-nueva", ['version_id' => $versionId]);
    }

    private function vistaPrevia(ProyectoExpediente $expediente, PlantillaChecklist $plantilla, Rol $rol = Rol::Gestor)
    {
        return $this->como($rol)->getJson("/GestPro/expedientes/{$expediente->id}/plantillas/{$plantilla->id}/version-nueva");
    }

    private function plantillaEn($respuesta, string $ruta, PlantillaChecklist $plantilla): array
    {
        return collect($respuesta->json($ruta))->firstWhere('plantilla_id', $plantilla->id);
    }

    /** Todo lo que aplicar podría cambiar del expediente. */
    private function estado(ProyectoExpediente $expediente): array
    {
        $filas = $expediente->requisitos()->select('id');

        return [
            'expediente' => (array) DB::table('proyectos_expedientes')->where('id', $expediente->id)->first(),
            'filas' => DB::table('proyectos_requisitos')->where('expediente_id', $expediente->id)->orderBy('id')->get()->map(fn ($f) => (array) $f)->all(),
            'origenes' => DB::table('proyecto_requisito_origenes')->whereIn('proyecto_requisito_id', $filas)
                ->orderBy('proyecto_requisito_id')->orderBy('requisito_catalogo_id')->get()->map(fn ($o) => (array) $o)->all(),
            'plantillas' => DB::table('expediente_plantillas')->where('expediente_id', $expediente->id)
                ->orderBy('catalogo_version_id')->get()->map(fn ($p) => (array) $p)->all(),
            'historial' => DB::table('checklist_historial')->where('expediente_id', $expediente->id)->orderBy('id')->get()->map(fn ($h) => (array) $h)->all(),
        ];
    }

    // --------------------------------------------------------------- cabecera

    public function test_la_cabecera_ofrece_la_version_nueva_de_una_plantilla_activa(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $detalle = fn () => $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$expediente->id}")->assertOk();
        $listado = fn () => $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$expediente->proyecto_id}/expedientes")->assertOk();

        // Misma versión: no hay nada que ofrecer.
        $this->assertNull($this->plantillaEn($detalle(), 'plantillas', $agua)['version_nueva']);
        $this->assertNull($this->plantillaEn($detalle(), 'plantillas', $this->sgr)['version_nueva']);

        $v2 = $this->publicarVersion($agua);
        $esperada = ['id' => $v2->id, 'numero' => 2];
        $this->assertSame($esperada, $this->plantillaEn($detalle(), 'plantillas', $agua)['version_nueva']);
        $this->assertSame(1, $this->plantillaEn($detalle(), 'plantillas', $agua)['version']['numero']);
        $this->assertNull($this->plantillaEn($detalle(), 'plantillas', $this->sgr)['version_nueva']);
        $this->assertSame($esperada, $this->plantillaEn($listado(), 'expedientes.0.plantillas', $agua)['version_nueva']);

        // Inactiva: no se ofrece.
        $agua->update(['activo' => false]);
        $this->assertNull($this->plantillaEn($detalle(), 'plantillas', $agua)['version_nueva']);
        $this->assertNull($this->plantillaEn($listado(), 'expedientes.0.plantillas', $agua)['version_nueva']);

        // Aplicada: el expediente ya usa la vigente.
        $agua->update(['activo' => true]);
        $aplicada = $this->aplicar($expediente, $agua, $v2->id)->assertOk();
        $this->assertSame(['id' => $v2->id, 'numero' => 2], $this->plantillaEn($aplicada, 'plantillas', $agua)['version']);
        $this->assertNull($this->plantillaEn($aplicada, 'plantillas', $agua)['version_nueva']);
        $this->assertNull($this->plantillaEn($listado(), 'expedientes.0.plantillas', $agua)['version_nueva']);
    }

    public function test_el_listado_del_proyecto_calcula_las_versiones_nuevas_sin_una_consulta_por_expediente(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $proyecto = $this->expediente([$agua])->proyecto;
        $this->publicarVersion($agua);
        $contar = function () use ($proyecto) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->servicio->presentarDeProyecto($proyecto->fresh());
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $conUno = $contar();

        foreach (range(1, 2) as $i) {
            ProyectoExpediente::where('proyecto_id', $proyecto->id)->update(['estado' => ProyectoExpediente::RADICADO]);
            $this->expediente([$agua], proyecto: $proyecto);
        }

        $this->assertSame($conUno, $contar());
    }

    // ---------------------------------------------------------------- aplicar

    public function test_aplicar_una_version_sgr_conserva_el_trabajo_de_lo_que_sigue(): void
    {
        $expediente = $this->expediente();
        $v1 = $this->version($this->sgr, 1);
        $responsable = Responsable::create(['nombre' => 'Juan Pérez', 'activo' => true]);
        $a2 = $this->fila($expediente, $v1, 'a2');
        $this->servicio->marcar($expediente, $this->fila($expediente, $v1, 'a1'), ['cumplido' => true], null);
        $this->servicio->marcar($expediente, $a2, ['cumplido' => true, 'observacion' => 'Revisado', 'responsable_id' => $responsable->id], null);
        $a2->refresh()->update(['soporte_archivo' => "expedientes/{$expediente->id}/a2.pdf", 'soporte_nombre' => 'a2.pdf']);
        $a8 = $this->fila($expediente, $v1, 'a8');
        $this->servicio->marcar($expediente, $a8, ['cumplido' => true, 'observacion' => 'Ya no aplica'], null);

        // v2: a1 cambia su texto, a8 sale de la fase III y a6 entra.
        $v2 = $this->publicarVersion($this->sgr, function ($req) {
            $req('a1')->update(['texto' => 'Texto v2']);
            $req('a8')->update(['condicion' => 'fase=1|2']);
            $req('a6')->update(['condicion' => 'fase=3']);
        });
        $usuario = User::factory()->create(['rol' => Rol::Gestor]);

        $r = $this->como(usuario: $usuario)->postJson("/GestPro/expedientes/{$expediente->id}/plantillas/{$this->sgr->id}/version-nueva", ['version_id' => $v2->id])
            ->assertOk()
            ->assertJsonPath('progreso', ['total' => 8, 'cumplidos' => 2, 'porcentaje' => 25])
            ->assertJsonPath('retenidos', 1);
        $this->assertSame('Texto v2', collect($r->json('requisitos'))->firstWhere('requisito.clave', 'a1')['requisito']['texto']);

        // a2 sigue: la misma fila, anclada en v2, con todo su trabajo.
        $a2 = $a2->fresh();
        $this->assertSame($this->requisito($v2, 'a2')->id, $a2->requisito_catalogo_id);
        $this->assertSame([$this->requisito($v2, 'a2')->id], $this->origenes($a2));
        $this->assertTrue($a2->aplica);
        $this->assertTrue($a2->cumplido);
        $this->assertSame('Revisado', $a2->observacion);
        $this->assertSame($responsable->id, $a2->responsable_id);
        $this->assertSame('a2.pdf', $a2->soporte_nombre);

        // a6 nace pendiente.
        $a6 = $this->fila($expediente, $v2, 'a6');
        $this->assertTrue($a6->aplica);
        $this->assertSame('pendiente', $a6->estadoVisible());

        // a8 queda retirada con su trabajo.
        $a8 = $a8->fresh();
        $this->assertFalse($a8->aplica);
        $this->assertTrue($a8->cumplido);
        $this->assertSame('Ya no aplica', $a8->observacion);
        $this->assertSame([], $this->origenes($a8));

        $this->assertSame([['catalogo_version_id' => $v2->id, 'es_principal' => true]], $this->versionDe($expediente, $this->sgr));
        $evento = ChecklistHistorial::where('expediente_id', $expediente->id)->where('evento', 'version_aplicada')->sole();
        $this->assertSame('expediente', $evento->entidad_tipo);
        $this->assertSame(['plantilla_id' => $this->sgr->id, 'plantilla' => 'SGR', 'version' => 1, 'catalogo_version_id' => $v1->id], $evento->antes);
        $this->assertSame(['plantilla_id' => $this->sgr->id, 'plantilla' => 'SGR', 'version' => 2, 'catalogo_version_id' => $v2->id], $evento->despues);
        $this->assertSame($usuario->id, $evento->usuario_id);
    }

    public function test_una_fila_sin_codigo_comun_se_reconoce_por_su_clave(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null], 'licencia' => ['Licencia', null]]);
        $expediente = $this->expediente([$agua]);
        $v1 = $this->version($agua, 1);
        $planos = $this->fila($expediente, $v1, 'planos');
        $this->servicio->marcar($expediente, $planos, ['cumplido' => true, 'observacion' => 'Firmados'], null);
        $filas = $expediente->requisitos()->count();

        $v2 = $this->publicarVersion($agua, fn ($req) => $req('licencia')->update(['texto' => 'Licencia de construcción']));
        app(VersionesExpediente::class)->aplicar($expediente, $agua, $v2->id, null);

        // Con otra llave (req:{id}), sincronizar sola habría creado otra fila y retirado esta.
        $planos = $planos->fresh();
        $this->assertSame($this->requisito($v2, 'planos')->id, $planos->requisito_catalogo_id);
        $this->assertSame([$this->requisito($v2, 'planos')->id], $this->origenes($planos));
        $this->assertTrue($planos->aplica);
        $this->assertTrue($planos->cumplido);
        $this->assertSame('Firmados', $planos->observacion);
        $this->assertSame($filas, $expediente->requisitos()->count());
        $this->assertSame(0, $expediente->requisitos()->where('aplica', false)->count());
        $this->assertSame([['catalogo_version_id' => $v2->id, 'es_principal' => false]], $this->versionDe($expediente, $agua));
    }

    public function test_una_fila_cuyo_codigo_comun_cambia_conserva_su_trabajo(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', 'planos']]);
        $expediente = $this->expediente([$agua]);
        $planos = $this->fila($expediente, $this->version($agua, 1), 'planos');
        $this->servicio->marcar($expediente, $planos, ['cumplido' => true], null);

        $v2 = $this->publicarVersion($agua, fn ($req) => $req('planos')->update(['codigo_comun' => 'planos-firmados']));
        app(VersionesExpediente::class)->aplicar($expediente, $agua, $v2->id, null);

        $planos = $planos->fresh();
        $this->assertSame($this->requisito($v2, 'planos')->id, $planos->requisito_catalogo_id);
        $this->assertTrue($planos->aplica);
        $this->assertTrue($planos->cumplido);
        $this->assertSame(0, $expediente->requisitos()->where('aplica', false)->count());
    }

    public function test_al_tomar_un_codigo_comun_ya_usado_conserva_la_llave_la_fila_con_trabajo(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $v1Sgr = $this->version($this->sgr, 1);
        $planos = $this->fila($expediente, $this->version($agua, 1), 'planos');
        $this->servicio->marcar($expediente, $planos, ['cumplido' => true, 'observacion' => 'Firmados'], null);
        // La fila SGR a1, sin trabajo, tiene un id menor que planos.
        $a1 = $this->fila($expediente, $v1Sgr, 'a1');
        $this->assertLessThan($planos->id, $a1->id);
        $progresoAntes = $this->servicio->progresoDe($expediente);

        // v2: planos toma el código común de a1. Al re-anclar, las dos filas quedan con la llave a1.
        $v2 = $this->publicarVersion($agua, fn ($req) => $req('planos')->update(['codigo_comun' => 'a1']));

        $vista = $this->vistaPrevia($expediente, $agua)->assertOk();
        // Deja de aplicar solo a1, sin trabajo: planos no aparece como retirada con trabajo.
        $this->assertSame(
            [['id' => $a1->id, 'con_trabajo' => false]],
            collect($vista->json('retirados'))->map(fn (array $f) => ['id' => $f['id'], 'con_trabajo' => $f['con_trabajo']])->all()
        );
        $this->assertSame(1, $vista->json('progreso_despues.cumplidos'));

        $this->aplicar($expediente, $agua, $v2->id)->assertOk()
            ->assertJsonPath('progreso', $vista->json('progreso_despues'))
            ->assertJsonPath('retenidos', 0);

        // planos conserva la llave, con su trabajo y los dos orígenes; a1, vacía, se retira.
        $planos = $planos->fresh();
        $this->assertTrue($planos->aplica);
        $this->assertTrue($planos->cumplido);
        $this->assertSame('Firmados', $planos->observacion);
        $this->assertSame($this->requisito($v2, 'planos')->id, $planos->requisito_catalogo_id);
        $this->assertSame([$this->requisito($v1Sgr, 'a1')->id, $this->requisito($v2, 'planos')->id], $this->origenes($planos));
        $a1 = $a1->fresh();
        $this->assertFalse($a1->aplica);
        $this->assertSame([], $this->origenes($a1));
        $progresoDespues = $this->servicio->progresoDe($expediente);
        $this->assertSame($progresoAntes['cumplidos'], $progresoDespues['cumplidos']);
        $this->assertGreaterThanOrEqual($progresoAntes['porcentaje'], $progresoDespues['porcentaje']);
    }

    public function test_una_fila_retirada_sin_codigo_comun_se_reancla_y_al_volver_recupera_su_trabajo(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null], 'licencia' => ['Licencia', null]]);
        $expediente = $this->expediente([$agua]);
        $licencia = $this->fila($expediente, $this->version($agua, 1), 'licencia');
        $this->servicio->marcar($expediente, $licencia, ['cumplido' => true, 'observacion' => 'Radicada'], null);
        $filas = $expediente->requisitos()->count();

        // v2 desactiva licencia: la fila sale, anclada en v2, con su trabajo.
        $v2 = $this->publicarVersion($agua, fn ($req) => $req('licencia')->update(['activo' => false]));
        app(VersionesExpediente::class)->aplicar($expediente, $agua, $v2->id, null);
        $licencia = $licencia->fresh();
        $this->assertFalse($licencia->aplica);
        $this->assertTrue($licencia->cumplido);
        $this->assertSame($this->requisito($v2, 'licencia')->id, $licencia->requisito_catalogo_id);

        // v3 la reactiva: la retirada se re-ancla por clave y vuelve con su trabajo, sin otra fila.
        $v3 = $this->publicarVersion($agua, fn ($req) => $req('licencia')->update(['activo' => true]));
        $this->vistaPrevia($expediente, $agua)->assertOk()
            ->assertJsonPath('nuevos.0.id', $licencia->id)
            ->assertJsonCount(1, 'nuevos');
        $this->aplicar($expediente, $agua, $v3->id)->assertOk()->assertJsonPath('retenidos', 0);

        $licencia = $licencia->fresh();
        $this->assertSame($this->requisito($v3, 'licencia')->id, $licencia->requisito_catalogo_id);
        $this->assertSame([$this->requisito($v3, 'licencia')->id], $this->origenes($licencia));
        $this->assertTrue($licencia->aplica);
        $this->assertTrue($licencia->cumplido);
        $this->assertSame('Radicada', $licencia->observacion);
        $this->assertSame($filas, $expediente->requisitos()->count());
    }

    public function test_un_requisito_propio_no_cambia(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $propio = app(RequisitosPropios::class)->crear($expediente, ['titulo' => 'Acta de socialización'], null);
        $this->servicio->marcar($expediente, $propio, ['cumplido' => true, 'observacion' => 'Firmada'], null);
        $antes = $propio->fresh()->getAttributes();

        $v2 = $this->publicarVersion($agua, fn ($req) => $req('planos')->update(['texto' => 'Planos v2']));
        app(VersionesExpediente::class)->aplicar($expediente, $agua, $v2->id, null);

        $this->assertSame($antes, $propio->fresh()->getAttributes());
        $this->assertSame([], $this->origenes($propio));
    }

    public function test_se_aplica_tambien_en_un_borrador_sin_configurar(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua], confirmar: false);
        $v2 = $this->publicarVersion($agua);

        $this->aplicar($expediente, $agua, $v2->id)->assertOk()->assertJsonPath('configurado', false);
        $this->assertSame([['catalogo_version_id' => $v2->id, 'es_principal' => false]], $this->versionDe($expediente, $agua));
    }

    // ----------------------------------------------------------- vista previa

    public function test_la_vista_previa_no_cambia_nada_y_lista_lo_que_cambiaria(): void
    {
        $expediente = $this->expediente();
        $v1 = $this->version($this->sgr, 1);
        // a5 (comp=suelo) entra y sale: queda retirada, sin trabajo.
        $suelo = (int) DB::table('requisitos_componentes')->where('clave', 'suelo')->value('id');
        $this->servicio->actualizarConfiguracion($expediente, ['fase_sgr' => 3, 'componente_ids' => [$suelo]]);
        $this->servicio->actualizarConfiguracion($expediente, ['fase_sgr' => 3]);
        $a5 = $this->fila($expediente, $v1, 'a5');
        $this->assertFalse($a5->aplica);
        $a1 = $this->fila($expediente, $v1, 'a1');
        $a8 = $this->fila($expediente, $v1, 'a8');
        $a9 = $this->fila($expediente, $v1, 'a9');
        $this->servicio->marcar($expediente, $a1, ['cumplido' => true], null);
        $this->servicio->marcar($expediente, $a8, ['cumplido' => true], null);

        // v2: a1 cambia su texto, a8 y a9 salen de la fase III, a5 y a6 entran.
        $v2 = $this->publicarVersion($this->sgr, function ($req) {
            $req('a1')->update(['texto' => 'Texto v2']);
            $req('a8')->update(['condicion' => 'fase=1|2']);
            $req('a9')->update(['condicion' => 'fase=1|2']);
            $req('a5')->update(['condicion' => 'siempre']);
            $req('a6')->update(['condicion' => 'fase=3']);
        });
        $antes = $this->estado($expediente);
        $resumen = fn (ProyectoRequisito $fila, RequisitoCatalogo $r) => [
            'id' => $fila->id, 'codigo' => $r->codigo, 'texto' => $r->texto, 'caracter' => 'obligatorio',
        ];

        $r = $this->vistaPrevia($expediente, $this->sgr)->assertOk();

        $this->assertSame($antes, $this->estado($expediente));
        $r->assertJsonPath('plantilla', ['id' => $this->sgr->id, 'nombre' => 'SGR'])
            ->assertJsonPath('version_actual', ['id' => $v1->id, 'numero' => 1])
            ->assertJsonPath('version_nueva.id', $v2->id)
            ->assertJsonPath('version_nueva.numero', 2)
            ->assertJsonPath('version_nueva.descripcion', 'Ajustes')
            ->assertJsonPath('version_nueva.fecha_publicacion', $v2->fecha_publicacion->toIso8601String())
            ->assertJsonPath('siguen', 5)
            ->assertJsonPath('progreso_antes', ['total' => 8, 'cumplidos' => 2, 'porcentaje' => 25])
            ->assertJsonPath('progreso_despues', ['total' => 8, 'cumplidos' => 1, 'porcentaje' => 13]);
        // En el orden del checklist. La retirada que vuelve conserva su id; la que nace no tiene.
        $this->assertSame([
            $resumen($a5, $this->requisito($v2, 'a5')),
            ['id' => null, 'codigo' => $this->requisito($v2, 'a6')->codigo, 'texto' => $this->requisito($v2, 'a6')->texto, 'caracter' => 'obligatorio'],
        ], $r->json('nuevos'));
        $this->assertSame([
            [...$resumen($a8, $this->requisito($v1, 'a8')), 'con_trabajo' => true],
            [...$resumen($a9, $this->requisito($v1, 'a9')), 'con_trabajo' => false],
        ], $r->json('retirados'));
        $this->assertSame([[
            'antes' => $resumen($a1, $this->requisito($v1, 'a1')),
            'despues' => $resumen($a1, $this->requisito($v2, 'a1')),
        ]], $r->json('cambian'));
        $this->assertSame('Texto v2', $r->json('cambian.0.despues.texto'));

        // Aplicar hace lo que mostró la vista previa.
        $this->aplicar($expediente, $this->sgr, $v2->id)->assertOk()->assertJsonPath('progreso', $r->json('progreso_despues'));
    }

    public function test_la_vista_previa_no_toca_el_modelo_de_quien_la_pide(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $this->publicarVersion($agua, fn ($req) => $req('planos')->update(['texto' => 'Planos v2']));
        $expediente->load('versionesPlantilla');
        $atributos = $expediente->getAttributes();

        $vista = app(VersionesExpediente::class)->vistaPrevia($expediente, $agua);

        $this->assertSame('Planos v2', $vista['cambian'][0]['despues']['texto']);
        $this->assertSame($atributos, $expediente->getAttributes());
        $this->assertTrue($expediente->relationLoaded('versionesPlantilla'));
        $this->assertSame(1, $expediente->versionesPlantilla->firstWhere('plantilla_id', $agua->id)->numero);
    }

    public function test_una_propia_aparece_en_la_vista_previa_con_su_titulo(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        app(RequisitosPropios::class)->crear($expediente, ['titulo' => 'Acta de socialización'], null);
        $this->publicarVersion($agua);

        // Ningún cambio de la plantilla: siguen las 8 SGR, planos y la propia.
        $this->vistaPrevia($expediente, $agua)->assertOk()
            ->assertJsonPath('nuevos', [])->assertJsonPath('retirados', [])->assertJsonPath('cambian', [])
            ->assertJsonPath('siguen', 10);
    }

    // ---------------------------------------------------------------- rechazos

    public function test_un_expediente_radicado_no_admite_otra_version(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $v2 = $this->publicarVersion($agua);
        $expediente->update(['estado' => ProyectoExpediente::RADICADO]);
        $mensaje = 'El expediente está radicado: su checklist está congelado y no admite cambios.';

        $this->vistaPrevia($expediente, $agua)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $this->aplicar($expediente, $agua, $v2->id)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $this->assertSame($this->version($agua, 1)->id, $this->versionDe($expediente, $agua)[0]['catalogo_version_id']);
    }

    public function test_la_plantilla_debe_estar_en_el_expediente(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente();
        $v2 = $this->publicarVersion($agua);
        $mensaje = 'La plantilla "Obras de agua" no está en este expediente.';

        $this->vistaPrevia($expediente, $agua)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $this->aplicar($expediente, $agua, $v2->id)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $this->assertSame([], $this->versionDe($expediente, $agua));
    }

    public function test_sin_version_nueva_no_hay_nada_que_aplicar(): void
    {
        $expediente = $this->expediente();
        $mensaje = 'El expediente ya usa la versión vigente de "SGR".';

        $this->vistaPrevia($expediente, $this->sgr)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $this->aplicar($expediente, $this->sgr, $this->version($this->sgr, 1)->id)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
    }

    public function test_una_plantilla_inactiva_no_admite_otra_version(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $v2 = $this->publicarVersion($agua);
        $agua->update(['activo' => false]);
        $mensaje = 'La plantilla "Obras de agua" está inactiva: no puedes aplicarle una versión nueva.';

        $this->vistaPrevia($expediente, $agua)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $this->aplicar($expediente, $agua, $v2->id)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
    }

    public function test_una_version_distinta_de_la_vigente_se_rechaza(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $v2 = $this->publicarVersion($agua);
        // Se publica otra mientras se revisaba la v2.
        $v3 = $this->publicarVersion($agua);
        $antes = $this->estado($expediente);

        $this->aplicar($expediente, $agua, $v2->id)->assertStatus(422)
            ->assertJsonPath('mensaje', 'La plantilla publicó otra versión mientras revisabas. Vuelve a abrir la vista previa.');
        $this->assertSame($antes, $this->estado($expediente));

        $this->vistaPrevia($expediente, $agua)->assertOk()->assertJsonPath('version_nueva.id', $v3->id);
    }

    public function test_la_version_debe_ser_un_numero_entero(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $v2 = $this->publicarVersion($agua);

        foreach ([(string) $v2->id, true, 2.5, [$v2->id]] as $valor) {
            $this->aplicar($expediente, $agua, $valor)->assertStatus(422)
                ->assertJsonPath('errors.version_id', ['Revisa la versión: debe ser un número entero.']);
        }
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/plantillas/{$agua->id}/version-nueva", [])
            ->assertStatus(422)->assertJsonPath('errors.version_id', ['Indica la versión que vas a aplicar.']);
        $this->assertSame($this->version($agua, 1)->id, $this->versionDe($expediente, $agua)[0]['catalogo_version_id']);
    }

    public function test_sin_permiso_de_edicion_no_se_previsualiza_ni_se_aplica(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $v2 = $this->publicarVersion($agua);

        $this->vistaPrevia($expediente, $agua, Rol::Consulta)->assertForbidden();
        $this->aplicar($expediente, $agua, $v2->id, Rol::Consulta)->assertForbidden();
        $this->assertSame($this->version($agua, 1)->id, $this->versionDe($expediente, $agua)[0]['catalogo_version_id']);
    }
}
```

En `tests/Feature/Viabilizacion/ComposicionExpedienteTest.php`, reemplazar (1 de 2):

```php
        $this->assertSame([], $this->origenes($sinAncla));
    }

    public function test_sin_ancla_en_los_origenes_gana_la_fila_que_aplica_y_luego_la_de_id_menor(): void
    {
        $expediente = $this->expediente();
        $a1 = $this->sgrA1();
```

por:

```php
        $this->assertSame([], $this->origenes($sinAncla));
    }

    public function test_sin_ancla_en_los_origenes_gana_la_fila_que_aplica_luego_la_que_tiene_trabajo_y_luego_la_de_id_menor(): void
    {
        $expediente = $this->expediente();
        $a1 = $this->sgrA1();
```

En `tests/Feature/Viabilizacion/ComposicionExpedienteTest.php`, reemplazar (2 de 2):

```php
        $this->assertSame([$a1->id], $this->origenes($activa));
        $this->assertFalse($retirada->refresh()->aplica);

        // Empatadas en todo, la de id menor.
        $activa->update(['aplica' => false]);
        $activa->origenes()->detach();
        $this->servicio->sincronizar($expediente->refresh());

        $this->assertTrue($retirada->refresh()->aplica);
        $this->assertFalse($activa->refresh()->aplica);
    }
```

por:

```php
        $this->assertSame([$a1->id], $this->origenes($activa));
        $this->assertFalse($retirada->refresh()->aplica);

        // Las dos retiradas: la que tiene trabajo, aunque su id sea mayor.
        $activa->update(['aplica' => false]);
        $activa->origenes()->detach();
        $this->servicio->sincronizar($expediente->refresh());

        $this->assertTrue($activa->refresh()->aplica);
        $this->assertTrue($activa->cumplido);
        $this->assertFalse($retirada->refresh()->aplica);

        // Empatadas en todo, la de id menor.
        $activa->update(['aplica' => false, 'cumplido' => false]);
        $activa->origenes()->detach();
        $this->servicio->sincronizar($expediente->refresh());

        $this->assertTrue($retirada->refresh()->aplica);
        $this->assertFalse($activa->refresh()->aplica);
    }
```

En `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`, reemplazar (1 de 1):

```php
        $version = $this->sgr->versionVigente();
        $r->assertJsonPath('plantillas', [[
            'plantilla_id' => $this->sgr->id, 'nombre' => 'SGR', 'motor' => 'sgr', 'es_principal' => true,
            'compatible' => true, 'version' => ['id' => $version->id, 'numero' => 1],
        ]])
            ->assertJsonPath('tiene_sgr', true)
            ->assertJsonPath('progreso.total', 8)
```

por:

```php
        $version = $this->sgr->versionVigente();
        $r->assertJsonPath('plantillas', [[
            'plantilla_id' => $this->sgr->id, 'nombre' => 'SGR', 'motor' => 'sgr', 'es_principal' => true,
            'compatible' => true, 'version' => ['id' => $version->id, 'numero' => 1], 'version_nueva' => null,
        ]])
            ->assertJsonPath('tiene_sgr', true)
            ->assertJsonPath('progreso.total', 8)
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Viabilizacion/VersionesExpedienteTest.php tests/Feature/Viabilizacion/ComposicionExpedienteTest.php tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`
Expected: FALLAN 20 y pasan 50. Las rutas `.../version-nueva` no existen (405 por el comodín web), `VersionesExpediente` no existe, la cabecera no trae `version_nueva` y el desempate todavía no mira el trabajo.

- [ ] **Paso 3: Implementar**

Crear `app/Services/Viabilizacion/VersionesExpediente.php`:

```php
<?php

namespace App\Services\Viabilizacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aplicar a un expediente la versión vigente de una de sus plantillas, con vista previa.
 *
 * Reglas:
 * - Solo en borrador, configurado o no. Radicado, el checklist está congelado.
 * - La plantilla debe estar en el expediente, activa y con una versión vigente distinta de la
 *   que usa el expediente. Se aplica la vigente, y solo si es la que se previsualizó.
 * - Los requisitos se reconocen por `clave`: las filas ancladas en la versión anterior de la
 *   plantilla, también las retiradas, pasan al requisito de la nueva con la misma clave, si
 *   existe. Así conservan su trabajo aunque no tengan código común o este haya cambiado.
 * - El trabajo sigue al ancla: si la fila también tenía un origen de otra plantilla por su
 *   código común y la versión nueva cambia ese código, la fila se va con su clave y para la
 *   otra plantilla nace una fila pendiente con el código anterior.
 * - Si al re-anclar dos filas quedan con la misma llave (un requisito sin código que toma uno
 *   que ya usa otra plantilla), decide la regla de sincronizar: conserva la llave la anclada en
 *   un origen, luego la que aplica, luego la que tiene trabajo (ProyectoRequisito::conTrabajo) y
 *   luego la de id menor; la otra queda retirada con su trabajo, si lo tiene. Así una fila
 *   vacía de otra plantilla no desplaza a la que ya tenía el trabajo y el avance no baja.
 * - Luego se recalcula como al configurar (ExpedienteService::sincronizar): las filas que
 *   siguen conservan cumplimiento, observación, soporte y responsable; las nuevas nacen
 *   pendientes (por definir si son condicionadas) y las que salen quedan con aplica = false y
 *   su trabajo. Los propios, las demás plantillas y la principal no cambian.
 * - Queda en el historial del expediente (version_aplicada).
 * - La vista previa ejecuta la misma aplicación en una transacción que siempre se revierte:
 *   muestra exactamente lo que haría aplicar. Por eso también bloquea el expediente (FOR
 *   UPDATE) mientras dura, aunque sea una lectura.
 */
final class VersionesExpediente
{
    private const OTRA_VERSION = 'La plantilla publicó otra versión mientras revisabas. Vuelve a abrir la vista previa.';

    // Filas por UPDATE del re-anclado: 3 parámetros por fila (WHEN, THEN e IN), 900 en total,
    // por debajo del límite de 999 de las versiones viejas de SQLite.
    private const LOTE_REANCLAR = 300;

    public function __construct(
        private readonly ExpedienteService $expedientes,
        private readonly PlantillasExpediente $plantillas,
        private readonly HistorialChecklist $historial,
    ) {
    }

    /**
     * Lo que cambiaría en el checklist al aplicar la versión vigente de la plantilla, sin cambiar nada.
     *
     * @return array{plantilla: array, version_actual: array, version_nueva: array, nuevos: list<array>,
     *               retirados: list<array>, cambian: list<array{antes: array, despues: array}>, siguen: int,
     *               progreso_antes: array, progreso_despues: array}
     */
    public function vistaPrevia(ProyectoExpediente $expediente, PlantillaChecklist $plantilla): array
    {
        // Una instancia propia: tras revertir, el modelo de quien llama no queda con un estado que no existe.
        $copia = ProyectoExpediente::query()->findOrFail($expediente->id);

        DB::beginTransaction();
        try {
            $this->expedientes->bloquear($copia);
            [$actual, $nueva] = $this->versionesAplicables($copia, $plantilla);
            $antes = $this->foto($copia);
            $progresoAntes = $this->expedientes->progresoDe($copia);

            $this->aplicarEnTransaccion($copia, $plantilla, $nueva->id, null);

            $despues = $this->foto($copia);
            $progresoDespues = $this->expedientes->progresoDe($copia);
            $retirados = $antes->filter(fn (array $f, int $id) => $f['aplica'] && ! $despues[$id]['aplica'])->keys();
            // Todas las retiradas con trabajo, sin una lista de ids que podría pasar el límite de parámetros.
            $conTrabajo = $copia->requisitos()->where('aplica', false)->conTrabajo()->pluck('id')->flip();
        } finally {
            DB::rollBack();
        }

        $nuevos = [];
        $cambian = [];
        $siguen = 0;
        foreach ($despues->filter(fn (array $f) => $f['aplica']) as $id => $fila) {
            $previa = $antes->get($id);
            if (! $previa || ! $previa['aplica']) {
                // Una fila creada al aplicar no existe todavía: no tiene id. Una retirada que vuelve, sí.
                $nuevos[] = [...$fila['fila'], 'id' => $previa ? $id : null];
            } elseif ($previa['fila'] !== $fila['fila']) {
                $cambian[] = ['antes' => $previa['fila'], 'despues' => $fila['fila']];
            } else {
                $siguen++;
            }
        }

        return [
            'plantilla' => ['id' => $actual->plantilla->id, 'nombre' => $actual->plantilla->nombre],
            'version_actual' => ['id' => $actual->id, 'numero' => $actual->numero],
            'version_nueva' => [
                'id' => $nueva->id,
                'numero' => $nueva->numero,
                'descripcion' => $nueva->descripcion,
                'fecha_publicacion' => $nueva->fecha_publicacion?->toIso8601String(),
            ],
            'nuevos' => $nuevos,
            'retirados' => $retirados
                ->map(fn (int $id) => [...$antes[$id]['fila'], 'con_trabajo' => $conTrabajo->has($id)])
                ->values()->all(),
            'cambian' => $cambian,
            'siguen' => $siguen,
            'progreso_antes' => $progresoAntes,
            'progreso_despues' => $progresoDespues,
        ];
    }

    /** @param int $versionId la versión que se previsualizó: debe seguir siendo la vigente */
    public function aplicar(ProyectoExpediente $expediente, PlantillaChecklist $plantilla, int $versionId, ?User $usuario): ProyectoExpediente
    {
        DB::transaction(fn () => $this->aplicarEnTransaccion($expediente, $plantilla, $versionId, $usuario));

        return $expediente;
    }

    /** Lo que hace aplicar(), dentro de una transacción ya abierta. */
    private function aplicarEnTransaccion(ProyectoExpediente $expediente, PlantillaChecklist $plantilla, int $versionId, ?User $usuario): void
    {
        // Todo se decide sobre la fila bloqueada, que es lo primero que se bloquea.
        $this->expedientes->bloquear($expediente);
        [$actual, $nueva] = $this->versionesAplicables($expediente, $plantilla);
        if ($nueva->id !== $versionId) {
            throw new ReglaNegocioException(self::OTRA_VERSION);
        }

        $this->reanclar($expediente, $actual, $nueva);
        // es_principal no cambia: solo la versión de esta plantilla.
        DB::table('expediente_plantillas')
            ->where('expediente_id', $expediente->id)
            ->where('catalogo_version_id', $actual->id)
            ->update(['catalogo_version_id' => $nueva->id]);
        $expediente->unsetRelation('versionesPlantilla');
        $this->expedientes->sincronizar($expediente);

        $this->historial->registrarExpediente(
            $expediente,
            'version_aplicada',
            $this->registrable($actual->plantilla, $actual),
            $this->registrable($actual->plantilla, $nueva),
            $usuario
        );
        $expediente->touch();
    }

    /**
     * La versión que usa el expediente para la plantilla y la que se le puede aplicar. Se llama
     * con el expediente bloqueado.
     *
     * @return array{CatalogoVersion, CatalogoVersion} la actual (con su plantilla) y la vigente
     */
    private function versionesAplicables(ProyectoExpediente $expediente, PlantillaChecklist $plantilla): array
    {
        $this->expedientes->exigirBorrador($expediente);

        $actual = $this->plantillas->versionesDe($expediente)->firstWhere('plantilla_id', $plantilla->id)
            ?? throw new ReglaNegocioException("La plantilla \"{$plantilla->nombre}\" no está en este expediente.");
        if (! $actual->plantilla->activo) {
            throw new ReglaNegocioException("La plantilla \"{$actual->plantilla->nombre}\" está inactiva: no puedes aplicarle una versión nueva.");
        }
        $nueva = $this->plantillas->versionesNuevas(collect([$actual]))[$actual->id]
            ?? throw new ReglaNegocioException("El expediente ya usa la versión vigente de \"{$actual->plantilla->nombre}\".");

        return [$actual, $nueva];
    }

    /**
     * Pasa las filas ancladas en la versión anterior al requisito de la nueva con la misma clave,
     * en una sentencia por lote. Las que no la encuentran conservan su ancla: el recálculo las
     * reconoce por su código común o las retira con su trabajo.
     *
     * No choca con el unique (expediente_id, requisito_catalogo_id): la clave es única en cada
     * versión, así que dos filas nunca van al mismo requisito, y los destinos son de otra versión
     * que los anclas actuales, así que un UPDATE que revisa fila a fila (MariaDB) tampoco ve un
     * duplicado de paso. Ninguna fila debería estar anclada ya en la versión nueva, porque la
     * versión de una plantilla en el expediente solo avanza (siempre entra la vigente, que es la
     * última publicada). Si datos anteriores lo hicieran, ese requisito no se usa como destino.
     */
    private function reanclar(ProyectoExpediente $expediente, CatalogoVersion $actual, CatalogoVersion $nueva): void
    {
        $porClave = RequisitoCatalogo::query()->where('catalogo_version_id', $nueva->id)->pluck('id', 'clave');
        $ocupados = $expediente->requisitos()
            ->whereIn('requisito_catalogo_id', RequisitoCatalogo::query()->select('id')->where('catalogo_version_id', $nueva->id))
            ->pluck('requisito_catalogo_id')->flip();
        $claves = $expediente->requisitos()
            ->join('requisitos_catalogo', 'requisitos_catalogo.id', '=', 'proyectos_requisitos.requisito_catalogo_id')
            ->where('requisitos_catalogo.catalogo_version_id', $actual->id)
            ->pluck('requisitos_catalogo.clave', 'proyectos_requisitos.id');

        $destinos = [];
        foreach ($claves as $fila => $clave) {
            $destino = $porClave->get($clave);
            if ($destino !== null && ! $ocupados->has($destino)) {
                $destinos[(int) $fila] = (int) $destino;
            }
        }

        $tabla = (new ProyectoRequisito())->getTable();
        foreach (array_chunk($destinos, self::LOTE_REANCLAR, true) as $lote) {
            $casos = implode(' ', array_fill(0, count($lote), 'WHEN ? THEN ?'));
            $ids = implode(', ', array_fill(0, count($lote), '?'));
            $pares = [];
            foreach ($lote as $fila => $destino) {
                array_push($pares, $fila, $destino);
            }
            DB::update(
                "UPDATE {$tabla} SET requisito_catalogo_id = CASE id {$casos} END WHERE id IN ({$ids})",
                [...$pares, ...array_keys($lote)]
            );
        }
    }

    /**
     * Cada fila del checklist, en su orden, con si aplica y lo que muestra.
     *
     * @return Collection<int, array{aplica: bool, fila: array}> id de la fila => datos
     */
    private function foto(ProyectoExpediente $expediente): Collection
    {
        $posiciones = $this->expedientes->posiciones($this->plantillas->versionesDe($expediente));

        return $expediente->requisitos()
            ->with(['requisito', 'origenes.version:id,plantilla_id'])
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (ProyectoRequisito $f) => [$f->id => ['aplica' => $f->aplica, 'fila' => $this->resumen($f, $posiciones)]]);
    }

    /** Lo que muestra la fila, como presentarFila(): el requisito del primer origen o, sin orígenes, el ancla. */
    private function resumen(ProyectoRequisito $fila, array $posiciones): array
    {
        if ($fila->esPropio()) {
            // Como en la pantalla, un propio se muestra por su título y no tiene código.
            return ['id' => $fila->id, 'codigo' => null, 'texto' => $fila->titulo_propio, 'caracter' => $fila->caracter, 'titulo' => $fila->titulo_propio];
        }
        $requisito = $this->expedientes->requisitoMostrado($fila, $posiciones);

        return ['id' => $fila->id, 'codigo' => $requisito->codigo, 'texto' => $requisito->texto, 'caracter' => $fila->caracter];
    }

    /** @return array{plantilla_id: int, plantilla: string, version: int, catalogo_version_id: int} */
    private function registrable(PlantillaChecklist $plantilla, CatalogoVersion $version): array
    {
        return [
            'plantilla_id' => $plantilla->id,
            'plantilla' => $plantilla->nombre,
            'version' => $version->numero,
            'catalogo_version_id' => $version->id,
        ];
    }
}
```

En `app/Http/Controllers/PlantillasExpedienteController.php`, reemplazar (1 de 2):

```php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Services\Viabilizacion\PlantillasExpediente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Plantillas que se ofrecen al configurar el expediente de un proyecto. Lectura: autenticado. */
class PlantillasExpedienteController extends Controller
{
    private const MENSAJES = [
        'integer' => 'Revisa :attribute: debe ser un número entero.',
    ];

    private const ATRIBUTOS = [
        'expediente' => 'el expediente',
    ];

    public function __construct(private readonly PlantillasExpediente $plantillas)
    {
    }

    /** Con ?expediente={id}, agrega las que ese expediente ya tiene, aunque ya no se ofrezcan. */
```

por:

```php

namespace App\Http\Controllers;

use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use App\Services\Viabilizacion\VersionesExpediente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plantillas de un expediente: las que se ofrecen al configurarlo (lectura: autenticado) y la
 * versión nueva de una de las suyas, con vista previa (can:editar-datos).
 */
class PlantillasExpedienteController extends Controller
{
    private const MENSAJES = [
        'integer' => 'Revisa :attribute: debe ser un número entero.',
        'version_id.required' => 'Indica la versión que vas a aplicar.',
    ];

    private const ATRIBUTOS = [
        'expediente' => 'el expediente',
        'version_id' => 'la versión',
    ];

    public function __construct(
        private readonly PlantillasExpediente $plantillas,
        private readonly VersionesExpediente $versiones,
        private readonly ExpedienteService $expedientes,
    ) {
    }

    /** Con ?expediente={id}, agrega las que ese expediente ya tiene, aunque ya no se ofrezcan. */
```

En `app/Http/Controllers/PlantillasExpedienteController.php`, reemplazar (2 de 2):

```php

        return response()->json($this->plantillas->disponibles($proyecto, $expediente));
    }
}
```

por:

```php

        return response()->json($this->plantillas->disponibles($proyecto, $expediente));
    }

    /** Lo que cambiaría en el checklist al aplicar la versión vigente de la plantilla. No cambia nada. */
    public function vistaPreviaVersion(ProyectoExpediente $expediente, PlantillaChecklist $plantilla): JsonResponse
    {
        return response()->json($this->versiones->vistaPrevia($expediente, $plantilla));
    }

    /** Aplica la versión previsualizada (version_id) y responde el expediente, como al configurarlo. */
    public function aplicarVersion(Request $request, ProyectoExpediente $expediente, PlantillaChecklist $plantilla): JsonResponse
    {
        // Enteros de verdad: `integer` admitiría true como 1 o "1".
        $entero = fn (string $atributo, mixed $valor, \Closure $falla) => is_int($valor)
            || $falla('Revisa :attribute: debe ser un número entero.');
        $datos = $request->validate(['version_id' => ['bail', 'required', 'integer', $entero]], self::MENSAJES, self::ATRIBUTOS);

        $this->versiones->aplicar($expediente, $plantilla, $datos['version_id'], $request->user());

        return response()->json($this->expedientes->presentar($expediente->refresh()));
    }
}
```

En `app/Models/ProyectoRequisito.php`, reemplazar (1 de 1):

```php
        return $query->cuenta()->where('estado_aplicabilidad', self::APLICA)->where('cumplido', true);
    }

    public function cuenta(): bool
    {
        return $this->aplica
```

por:

```php
        return $query->cuenta()->where('estado_aplicabilidad', self::APLICA)->where('cumplido', true);
    }

    /**
     * Filas con trabajo hecho: cumplidas, con observación, soporte o responsable, o con un
     * "no aplica" marcado. Es lo que conserva una fila retirada y la única definición: la
     * usan los retenidos del expediente, la vista previa de una versión nueva y el desempate
     * de filas con la misma llave al sincronizar.
     */
    public function scopeConTrabajo(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('cumplido', true)
            ->orWhereNotNull('observacion')
            ->orWhereNotNull('soporte_archivo')
            ->orWhereNotIn('estado_aplicabilidad', [self::APLICA, self::POR_DEFINIR])
            ->orWhereNotNull('responsable_id'));
    }

    public function cuenta(): bool
    {
        return $this->aplica
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 8):

```php
 *
 * Reglas:
 * - Lleva una o varias plantillas, cada una con la versión con que entró (la vigente
 *   de ese momento). Sin plantillas en el pedido, nace con la plantilla SGR si el
 *   proyecto la admite, o sin plantillas.
 * - La fase SGR solo se exige si el expediente tiene una plantilla con motor sgr.
 * - Cada proyecto tiene como máximo un expediente en borrador.
 * - Nace sin configurar: solo se chequea o radica tras confirmar la configuración,
```

por:

```php
 *
 * Reglas:
 * - Lleva una o varias plantillas, cada una con la versión con que entró (la vigente
 *   de ese momento) o la que se le aplicó después (VersionesExpediente). Sin plantillas
 *   en el pedido, nace con la plantilla SGR si el proyecto la admite, o sin plantillas.
 * - La fase SGR solo se exige si el expediente tiene una plantilla con motor sgr.
 * - Cada proyecto tiene como máximo un expediente en borrador.
 * - Nace sin configurar: solo se chequea o radica tras confirmar la configuración,
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 8):

```php
        $sinLlave = [];
        $filas = $expediente->requisitos()->whereNotNull('requisito_catalogo_id')
            ->with('requisito:id,codigo_comun')->orderBy('id')->get();
        foreach ($filas->groupBy(fn (ProyectoRequisito $f) => $this->llave($f->requisito)) as $llave => $grupo) {
            // Dos filas con la misma llave solo pueden venir de datos anteriores a esta regla.
            // Conserva la llave la anclada en un origen calculado; si no, la que aplica; si no,
            // la de id menor. Las demás quedan sin orígenes.
            $origenes = array_flip(array_map(fn (RequisitoCatalogo $r) => $r->id, $llaves[$llave]['origenes'] ?? []));
            $ordenadas = $grupo->sortBy(fn (ProyectoRequisito $f) => [
                isset($origenes[$f->requisito_catalogo_id]) ? 0 : 1,
                $f->aplica ? 0 : 1,
                $f->id,
            ])->values();
            $existentes[$llave] = $ordenadas->first();
```

por:

```php
        $sinLlave = [];
        $filas = $expediente->requisitos()->whereNotNull('requisito_catalogo_id')
            ->with('requisito:id,codigo_comun')->orderBy('id')->get();
        $grupos = $filas->groupBy(fn (ProyectoRequisito $f) => $this->llave($f->requisito));
        // Solo si hay empates: una consulta para todo el expediente, con el criterio de conTrabajo.
        $conTrabajo = $grupos->contains(fn (Collection $grupo) => $grupo->count() > 1)
            ? $expediente->requisitos()->whereNotNull('requisito_catalogo_id')->conTrabajo()->pluck('id')->flip()
            : collect();
        foreach ($grupos as $llave => $grupo) {
            // Dos filas con la misma llave vienen de datos anteriores a esta regla o de una
            // versión nueva que da a un requisito el código común que ya usa otra fila
            // (VersionesExpediente). Conserva la llave la anclada en un origen calculado; luego
            // la que aplica; luego la que tiene trabajo, para no retirarlo en favor de una fila
            // vacía; luego la de id menor. Las demás quedan sin orígenes.
            $origenes = array_flip(array_map(fn (RequisitoCatalogo $r) => $r->id, $llaves[$llave]['origenes'] ?? []));
            $ordenadas = $grupo->sortBy(fn (ProyectoRequisito $f) => [
                isset($origenes[$f->requisito_catalogo_id]) ? 0 : 1,
                $f->aplica ? 0 : 1,
                $conTrabajo->has($f->id) ? 0 : 1,
                $f->id,
            ])->values();
            $existentes[$llave] = $ordenadas->first();
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (3 de 8):

```php
            'avisos_duplicados' => $avisos['avisos'],
            'avisos_duplicados_total' => $avisos['total'],
            // Filas que dejaron de aplicar pero guardan trabajo hecho.
            'retenidos' => $expediente->requisitos()->where('aplica', false)
                ->where(fn ($q) => $q->where('cumplido', true)->orWhereNotNull('observacion')->orWhereNotNull('soporte_archivo')
                    ->orWhereNotIn('estado_aplicabilidad', [ProyectoRequisito::APLICA, ProyectoRequisito::POR_DEFINIR])->orWhereNotNull('responsable_id'))
                ->count(),
        ];
    }
```

por:

```php
            'avisos_duplicados' => $avisos['avisos'],
            'avisos_duplicados_total' => $avisos['total'],
            // Filas que dejaron de aplicar pero guardan trabajo hecho.
            'retenidos' => $expediente->requisitos()->where('aplica', false)->conTrabajo()->count(),
        ];
    }
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (4 de 8):

```php
            ])
            ->orderByDesc('id')
            ->get();
        $compatibles = $expedientes->contains(fn (ProyectoExpediente $e) => $e->versionesPlantilla->isNotEmpty())
            ? $this->plantillas->idsCompatibles($proyecto)
            : [];

        return $expedientes->map(fn (ProyectoExpediente $e) => [
            ...$this->presentarCabecera($e->setRelation('proyecto', $proyecto), $this->plantillas->ordenar($e->versionesPlantilla), $compatibles),
            'progreso' => $this->progreso((int) $e->requisitos_aplican, (int) $e->requisitos_cumplidos),
        ])->values()->all();
    }
```

por:

```php
            ])
            ->orderByDesc('id')
            ->get();
        $versiones = $expedientes->flatMap(fn (ProyectoExpediente $e) => $e->versionesPlantilla);
        $compatibles = $versiones->isNotEmpty() ? $this->plantillas->idsCompatibles($proyecto) : [];
        $nuevas = $this->plantillas->versionesNuevas($versiones);

        return $expedientes->map(fn (ProyectoExpediente $e) => [
            ...$this->presentarCabecera($e->setRelation('proyecto', $proyecto), $this->plantillas->ordenar($e->versionesPlantilla), $compatibles, $nuevas),
            'progreso' => $this->progreso((int) $e->requisitos_aplican, (int) $e->requisitos_cumplidos),
        ])->values()->all();
    }
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (5 de 8):

```php
    /**
     * @param ?Collection<int, CatalogoVersion> $versiones las de versionesDe(), si ya se leyeron
     * @param ?list<int> $compatibles las de idsCompatibles() del proyecto, si ya se calcularon
     */
    public function presentarCabecera(ProyectoExpediente $expediente, ?Collection $versiones = null, ?array $compatibles = null): array
    {
        $expediente->loadMissing(['proyecto:id,nombre,sector', 'usuarioRadico:id,name', 'usuarioCreo:id,name']);
        $versiones ??= $this->plantillas->versionesDe($expediente);
        $compatibles ??= $versiones->isEmpty() ? [] : $this->plantillas->idsCompatibles($expediente->proyecto);

        return [
            'id' => $expediente->id,
```

por:

```php
    /**
     * @param ?Collection<int, CatalogoVersion> $versiones las de versionesDe(), si ya se leyeron
     * @param ?list<int> $compatibles las de idsCompatibles() del proyecto, si ya se calcularon
     * @param ?array<int, CatalogoVersion> $nuevas las de PlantillasExpediente::versionesNuevas(), si ya se calcularon
     */
    public function presentarCabecera(ProyectoExpediente $expediente, ?Collection $versiones = null, ?array $compatibles = null, ?array $nuevas = null): array
    {
        $expediente->loadMissing(['proyecto:id,nombre,sector', 'usuarioRadico:id,name', 'usuarioCreo:id,name']);
        $versiones ??= $this->plantillas->versionesDe($expediente);
        $compatibles ??= $versiones->isEmpty() ? [] : $this->plantillas->idsCompatibles($expediente->proyecto);
        $nuevas ??= $this->plantillas->versionesNuevas($versiones);

        return [
            'id' => $expediente->id,
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (6 de 8):

```php
                // Cambiar las fuentes o el sector del proyecto no altera el expediente: solo se avisa.
                'compatible' => in_array($v->plantilla_id, $compatibles, true),
                'version' => ['id' => $v->id, 'numero' => $v->numero],
            ])->values()->all(),
            'tiene_sgr' => $this->plantillas->tieneSgr($versiones),
            'fase_sgr' => $expediente->fase_sgr,
```

por:

```php
                // Cambiar las fuentes o el sector del proyecto no altera el expediente: solo se avisa.
                'compatible' => in_array($v->plantilla_id, $compatibles, true),
                'version' => ['id' => $v->id, 'numero' => $v->numero],
                // Solo se ofrece: el expediente la aplica de forma explícita (VersionesExpediente).
                'version_nueva' => isset($nuevas[$v->id]) ? ['id' => $nuevas[$v->id]->id, 'numero' => $nuevas[$v->id]->numero] : null,
            ])->values()->all(),
            'tiene_sgr' => $this->plantillas->tieneSgr($versiones),
            'fase_sgr' => $expediente->fase_sgr,
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (7 de 8):

```php
        ];
    }

    /** @return array<int, int> plantilla_id => posición (0 es la principal) */
    private function posiciones(Collection $versiones): array
    {
        return $versiones->values()->pluck('plantilla_id')->flip()->map(fn ($posicion) => (int) $posicion)->all();
    }

    /** Orígenes de la fila en el orden de las plantillas del expediente, la principal primero. */
    private function origenesOrdenados(ProyectoRequisito $fila, array $posiciones): Collection
    {
```

por:

```php
        ];
    }

    /**
     * @param Collection<int, CatalogoVersion> $versiones las de versionesDe()
     * @return array<int, int> plantilla_id => posición (0 es la principal)
     */
    public function posiciones(Collection $versiones): array
    {
        return $versiones->values()->pluck('plantilla_id')->flip()->map(fn ($posicion) => (int) $posicion)->all();
    }

    /**
     * Requisito que muestra la fila, el mismo de presentarFila(): el del primer origen y, sin
     * orígenes, el ancla. Null en un propio. Espera cargados `requisito` y `origenes.version`.
     *
     * @param array<int, int> $posiciones las de posiciones()
     */
    public function requisitoMostrado(ProyectoRequisito $fila, array $posiciones): ?RequisitoCatalogo
    {
        return $this->origenesOrdenados($fila, $posiciones)->first() ?? $fila->requisito;
    }

    /** Orígenes de la fila en el orden de las plantillas del expediente, la principal primero. */
    private function origenesOrdenados(ProyectoRequisito $fila, array $posiciones): Collection
    {
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (8 de 8):

```php
        $this->exigirChecklistEditable($expediente);
    }

    private function exigirBorrador(ProyectoExpediente $expediente): void
    {
        if (! $expediente->esBorrador()) {
            throw new ReglaNegocioException('El expediente está radicado: su checklist está congelado y no admite cambios.');
```

por:

```php
        $this->exigirChecklistEditable($expediente);
    }

    public function exigirBorrador(ProyectoExpediente $expediente): void
    {
        if (! $expediente->esBorrador()) {
            throw new ReglaNegocioException('El expediente está radicado: su checklist está congelado y no admite cambios.');
```

En `app/Services/Viabilizacion/PlantillasExpediente.php`, reemplazar (1 de 2):

```php
 * - Se ofrecen las plantillas activas con versión vigente de alguna fuente del proyecto
 *   (o de todas) y de su sector (o de todos). Sin sector, solo las de todos los sectores.
 * - Una plantilla nueva en el expediente entra con su versión vigente; la que ya tenía
 *   conserva su versión aunque haya dejado de ofrecerse (sin cambios retroactivos).
 * - Como máximo una plantilla con motor sgr y 20 en total; si hay plantillas, una es la principal.
 * - expediente_plantillas guarda la versión de cada plantilla del expediente, también la sgr.
 */
```

por:

```php
 * - Se ofrecen las plantillas activas con versión vigente de alguna fuente del proyecto
 *   (o de todas) y de su sector (o de todos). Sin sector, solo las de todos los sectores.
 * - Una plantilla nueva en el expediente entra con su versión vigente; la que ya tenía
 *   conserva su versión aunque haya dejado de ofrecerse (sin cambios retroactivos), hasta
 *   que se le aplique otra de forma explícita (VersionesExpediente).
 * - Como máximo una plantilla con motor sgr y 20 en total; si hay plantillas, una es la principal.
 * - expediente_plantillas guarda la versión de cada plantilla del expediente, también la sgr.
 */
```

En `app/Services/Viabilizacion/PlantillasExpediente.php`, reemplazar (2 de 2):

```php
            ->values();
    }

    /**
     * Comprueba la selección pedida y decide la versión de cada plantilla.
     *
```

por:

```php
            ->values();
    }

    /**
     * Versión que se puede aplicar a cada una de las versiones dadas: la vigente de su
     * plantilla, si es otra y la plantilla está activa. Una sola consulta, aunque las versiones
     * vengan de varios expedientes: la respuesta depende solo de la versión.
     *
     * @param Collection<int, CatalogoVersion> $versiones con su plantilla cargada
     * @return array<int, CatalogoVersion> catalogo_version_id => vigente de su plantilla; sin
     *         entrada si no hay versión nueva
     */
    public function versionesNuevas(Collection $versiones): array
    {
        $activas = $versiones->filter(fn (CatalogoVersion $v) => $v->plantilla->activo);
        if ($activas->isEmpty()) {
            return [];
        }

        $vigentes = CatalogoVersion::query()->vigente()
            ->whereIn('plantilla_id', $activas->pluck('plantilla_id')->unique()->values())
            ->get()
            ->keyBy('plantilla_id');

        $nuevas = [];
        foreach ($activas as $version) {
            $vigente = $vigentes->get($version->plantilla_id);
            if ($vigente && $vigente->id !== $version->id) {
                $nuevas[$version->id] = $vigente;
            }
        }

        return $nuevas;
    }

    /**
     * Comprueba la selección pedida y decide la versión de cada plantilla.
     *
```

En `routes/viabilizacion.php`, reemplazar (1 de 1):

```php
    Route::delete('/expedientes/{expediente}', [ExpedienteController::class, 'destroy'])
        ->name('expedientes.destroy');

    Route::post('/expedientes/{expediente}/requisitos-propios', [RequisitoPropioController::class, 'store'])
        ->name('expedientes.requisitos-propios.store');
```

por:

```php
    Route::delete('/expedientes/{expediente}', [ExpedienteController::class, 'destroy'])
        ->name('expedientes.destroy');

    // Versión nueva de una plantilla del expediente: vista previa (no cambia nada) y aplicar.
    Route::get('/expedientes/{expediente}/plantillas/{plantilla}/version-nueva', [PlantillasExpedienteController::class, 'vistaPreviaVersion'])
        ->name('expedientes.plantillas.version-nueva');
    Route::post('/expedientes/{expediente}/plantillas/{plantilla}/version-nueva', [PlantillasExpedienteController::class, 'aplicarVersion'])
        ->name('expedientes.plantillas.version-nueva.aplicar');

    Route::post('/expedientes/{expediente}/requisitos-propios', [RequisitoPropioController::class, 'store'])
        ->name('expedientes.requisitos-propios.store');
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Viabilizacion/VersionesExpedienteTest.php tests/Feature/Viabilizacion/ComposicionExpedienteTest.php tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 619 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Services/Viabilizacion/VersionesExpediente.php tests/Feature/Viabilizacion/VersionesExpedienteTest.php app/Http/Controllers/PlantillasExpedienteController.php app/Models/ProyectoRequisito.php app/Services/Viabilizacion/ExpedienteService.php app/Services/Viabilizacion/PlantillasExpediente.php routes/viabilizacion.php tests/Feature/Viabilizacion/ComposicionExpedienteTest.php tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php
git commit -m "feat: apply a new template version to an expediente with preview"
```

---

### Tarea 4: Aplicar la versión nueva desde la pantalla

Aviso y botón por plantilla en un borrador editable, con la vista previa en un `Swal`.

**Files:**
- Create: `resources/js/utils/versionesExpediente.js`
- Test (create): `resources/js/utils/versionesExpediente.test.js`
- Modify: `resources/css/ExpedienteViabilizacion.css`
- Modify: `resources/js/components/ExpedienteViabilizacion.jsx`
- Modify: `resources/js/utils/expedienteChecklist.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `resources/js/utils/versionesExpediente.test.js`:

```js
import { describe, it, expect } from 'vitest';
import {
    TOPE_VISTA_PREVIA,
    etiquetaAplicarVersion,
    htmlVistaPrevia,
    plantillasConVersionNueva,
    textoVersionNueva,
} from './versionesExpediente';

/** Plantilla como la trae la cabecera del expediente. */
const plantilla = (id, extra = {}) => ({
    plantilla_id: id,
    nombre: `Plantilla ${id}`,
    motor: 'simple',
    es_principal: id === 1,
    compatible: true,
    version: { id: id * 10, numero: 2 },
    version_nueva: null,
    ...extra,
});

/** Fila resumida de la vista previa (VersionesExpediente::resumen). */
const fila = (id, extra = {}) => ({ id, codigo: `R-${id}`, texto: `Requisito ${id}`, caracter: 'obligatorio', ...extra });

const propio = (id, titulo, extra = {}) => ({ id, codigo: null, texto: titulo, caracter: 'obligatorio', titulo, ...extra });

const vista = (extra = {}) => ({
    plantilla: { id: 1, nombre: 'Obras de agua' },
    version_actual: { id: 10, numero: 2 },
    version_nueva: { id: 11, numero: 3, descripcion: null, fecha_publicacion: '2026-11-05T12:00:00-05:00' },
    nuevos: [],
    retirados: [],
    cambian: [],
    siguen: 0,
    progreso_antes: { total: 30, cumplidos: 12, porcentaje: 40 },
    progreso_despues: { total: 32, cumplidos: 12, porcentaje: 38 },
    ...extra,
});

// Sin DOM en las pruebas: el texto se saca quitando etiquetas. Vale porque todo dato va escapado
// (ningún "<" de los datos llega como tal) y solo se comparan textos sin entidades.
const textoDe = (html) => html.replace(/<[^>]+>/g, '');

/** El texto de cada <li> de la sección cuyo título empieza con `titulo`, o null si no está. */
const itemsDe = (html, titulo) => {
    const seccion = [...html.matchAll(/<section><h3>(.*?)<\/h3>(.*?)<\/section>/g)].find(([, h3]) => h3.startsWith(titulo));
    return seccion ? [...seccion[2].matchAll(/<li[^>]*>(.*?)<\/li>/g)].map(([, li]) => textoDe(li)) : null;
};

describe('plantillasConVersionNueva', () => {
    it('devuelve solo las que traen version_nueva, en su orden', () => {
        const lista = [
            plantilla(1, { version_nueva: { id: 11, numero: 3 } }),
            plantilla(2),
            plantilla(3, { version_nueva: { id: 31, numero: 5 } }),
        ];
        expect(plantillasConVersionNueva(lista).map((p) => p.plantilla_id)).toEqual([1, 3]);
    });

    it('tolera una lista vacía, nula o con elementos inesperados', () => {
        expect(plantillasConVersionNueva([])).toEqual([]);
        expect(plantillasConVersionNueva(null)).toEqual([]);
        expect(plantillasConVersionNueva(undefined)).toEqual([]);
        expect(plantillasConVersionNueva([null, 'SGR', plantilla(2)])).toEqual([]);
    });
});

describe('textoVersionNueva', () => {
    it('dice la versión disponible, sin repetir la que usa el expediente', () => {
        expect(textoVersionNueva(plantilla(1, { version_nueva: { id: 11, numero: 3 } }))).toBe('versión 3 disponible');
    });

    it('sin versión nueva devuelve null', () => {
        expect(textoVersionNueva(plantilla(1))).toBeNull();
        expect(textoVersionNueva(null)).toBeNull();
    });
});

describe('etiquetaAplicarVersion', () => {
    it('nombra la versión y la plantilla, para distinguir un botón de otro', () => {
        const agua = plantilla(2, { nombre: 'Obras de agua', version_nueva: { id: 21, numero: 3 } });
        expect(etiquetaAplicarVersion(agua)).toBe('Aplicar la versión 3 de Obras de agua');
    });

    it('sin versión nueva devuelve null', () => {
        expect(etiquetaAplicarVersion(plantilla(1))).toBeNull();
        expect(etiquetaAplicarVersion(undefined)).toBeNull();
    });
});

describe('htmlVistaPrevia', () => {
    it('resume las versiones y el avance antes y después', () => {
        const texto = textoDe(htmlVistaPrevia(vista({ nuevos: [fila(1)] })));
        expect(texto).toContain('Versión 2 → 3');
        expect(texto).toContain('Avance: 12 de 30 (40%) → 12 de 32 (38%)');
    });

    it('muestra la descripción de la versión solo si la tiene', () => {
        const con = vista({ version_nueva: { id: 11, numero: 3, descripcion: '  Ajusta la norma de 2026.  ', fecha_publicacion: null } });
        expect(htmlVistaPrevia(con)).toContain('<p class="exv-vista-descripcion">Ajusta la norma de 2026.</p>');
        expect(htmlVistaPrevia(vista())).not.toContain('exv-vista-descripcion');
        const enBlanco = vista({ version_nueva: { id: 11, numero: 3, descripcion: '   ', fecha_publicacion: null } });
        expect(htmlVistaPrevia(enBlanco)).not.toContain('exv-vista-descripcion');
    });

    it('si el avance no cambia, lo dice una sola vez', () => {
        const igual = { total: 30, cumplidos: 12, porcentaje: 40 };
        const texto = textoDe(htmlVistaPrevia(vista({ progreso_antes: igual, progreso_despues: igual })));
        expect(texto).toContain('Avance: 12 de 30 (40%), no cambia');
        expect(texto).not.toContain('→ 12 de 30');
    });

    it('sin cambios lo dice, con los que siguen igual', () => {
        const igual = { total: 8, cumplidos: 2, porcentaje: 25 };
        const html = htmlVistaPrevia(vista({ siguen: 8, progreso_antes: igual, progreso_despues: igual }));
        const texto = textoDe(html);
        expect(texto).toContain('La versión nueva no cambia el checklist de este expediente.');
        expect(texto).toContain('8 requisitos siguen igual.');
        expect(html).not.toContain('<section>');
    });

    it('con cambios no dice que no cambia nada', () => {
        expect(textoDe(htmlVistaPrevia(vista({ cambian: [{ antes: fila(1), despues: fila(1, { texto: 'Otro' }) }] }))))
            .not.toContain('no cambia el checklist');
    });

    it('no dice cuántos siguen igual si no sigue ninguno', () => {
        expect(textoDe(htmlVistaPrevia(vista({ nuevos: [fila(1)] })))).not.toContain('igual');
    });

    it('usa singular y plural en los títulos y en los que siguen', () => {
        const una = textoDe(htmlVistaPrevia(vista({
            nuevos: [fila(1)], retirados: [fila(2)], cambian: [{ antes: fila(3), despues: fila(3, { texto: 'Nuevo' }) }], siguen: 1,
        })));
        expect(una).toContain('Se agrega 1 requisito');
        expect(una).toContain('Deja de aplicar 1 requisito');
        expect(una).toContain('Cambia 1 requisito');
        expect(una).toContain('1 requisito sigue igual.');

        const varias = textoDe(htmlVistaPrevia(vista({
            nuevos: [fila(1), fila(2)],
            retirados: [fila(3), fila(4), fila(5)],
            cambian: [{ antes: fila(6), despues: fila(6, { texto: 'A' }) }, { antes: fila(7), despues: fila(7, { texto: 'B' }) }],
            siguen: 4,
        })));
        expect(varias).toContain('Se agregan 2 requisitos');
        expect(varias).toContain('Dejan de aplicar 3 requisitos');
        expect(varias).toContain('Cambian 2 requisitos');
        expect(varias).toContain('4 requisitos siguen igual.');
    });

    it('muestra cada fila con su código y su texto', () => {
        const html = htmlVistaPrevia(vista({ retirados: [fila(1), fila(2, { codigo: null, texto: 'Sin código' })] }));
        expect(html).toContain('<li><b>R-1</b> Requisito 1</li>');
        expect(itemsDe(html, 'Dejan de aplicar')).toEqual(['R-1 Requisito 1', 'Sin código']);
    });

    it('una fila propia se muestra por su título y sin código', () => {
        const html = htmlVistaPrevia(vista({ retirados: [propio(9, 'Permiso de la CAR', { codigo: 'X-1', texto: 'otro', con_trabajo: false })] }));
        expect(itemsDe(html, 'Deja de aplicar')).toEqual(['Permiso de la CAR propio']);
        expect(html).not.toContain('X-1');
    });

    it('marca los retirados que tienen trabajo guardado', () => {
        const html = htmlVistaPrevia(vista({
            retirados: [fila(1, { con_trabajo: true }), fila(2, { con_trabajo: false })],
        }));
        expect(itemsDe(html, 'Dejan de aplicar')).toEqual(['R-1 Requisito 1 conserva su trabajo', 'R-2 Requisito 2']);
    });

    it('marca los nuevos que no son obligatorios y los que vuelven a aplicar', () => {
        const html = htmlVistaPrevia(vista({
            nuevos: [fila(null, { codigo: 'N-1', texto: 'Nace' }), fila(null, { codigo: 'N-2', texto: 'Cond', caracter: 'condicionado' }), fila(7)],
        }));
        expect(itemsDe(html, 'Se agregan')).toEqual(['N-1 Nace', 'N-2 Cond condicionado', 'R-7 Requisito 7 vuelve a aplicar']);
    });

    it('en los que cambian dice el texto nuevo y el cambio de carácter', () => {
        const html = htmlVistaPrevia(vista({
            cambian: [
                { antes: fila(1), despues: fila(1, { codigo: 'R-1b', texto: 'Texto nuevo' }) },
                { antes: fila(2), despues: fila(2, { caracter: 'opcional' }) },
            ],
        }));
        expect(itemsDe(html, 'Cambian')).toEqual([
            'R-1 Requisito 1Ahora: R-1b Texto nuevo',
            'R-2 Requisito 2Pasa de obligatorio a opcional.',
        ]);
    });

    it('limita cada lista a TOPE_VISTA_PREVIA y cuenta el resto', () => {
        const muchos = Array.from({ length: TOPE_VISTA_PREVIA + 3 }, (_, i) => fila(i + 1));
        const items = itemsDe(htmlVistaPrevia(vista({ retirados: muchos })), 'Dejan de aplicar');
        expect(items).toHaveLength(TOPE_VISTA_PREVIA + 1);
        expect(items.at(-2)).toBe(`R-${TOPE_VISTA_PREVIA} Requisito ${TOPE_VISTA_PREVIA}`);
        expect(items.at(-1)).toBe('y 3 más');
    });

    it('con justo TOPE_VISTA_PREVIA filas no agrega "y N más"', () => {
        const justas = Array.from({ length: TOPE_VISTA_PREVIA }, (_, i) => fila(i + 1));
        const items = itemsDe(htmlVistaPrevia(vista({ nuevos: justas })), 'Se agregan');
        expect(items).toHaveLength(TOPE_VISTA_PREVIA);
        expect(items.some((t) => t.endsWith('más'))).toBe(false);
    });

    it('escapa todo texto que viene de los datos', () => {
        const malo = '<img src=x onerror=alert(1)>';
        const html = htmlVistaPrevia(vista({
            version_actual: { id: 10, numero: '<b>2</b>' },
            version_nueva: { id: 11, numero: 3, descripcion: `Notas ${malo}`, fecha_publicacion: null },
            nuevos: [fila(1, { codigo: '"R&1"', texto: malo })],
            retirados: [propio(2, malo, { con_trabajo: true })],
            cambian: [{ antes: fila(3, { texto: malo }), despues: fila(3, { texto: `${malo}!`, caracter: '<i>raro</i>' }) }],
        }));
        expect(html).not.toContain('<img');
        expect(html).not.toContain('<b>2</b>');
        expect(html).not.toContain('<i>raro</i>');
        expect(html).toContain('&lt;img src=x onerror=alert(1)&gt;');
        expect(html).toContain('<b>&quot;R&amp;1&quot;</b>');
        expect(html).toContain('Versión &lt;b&gt;2&lt;/b&gt; → 3');
    });
});
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/versionesExpediente.test.js`
Expected: FALLA el archivo: no existe `./versionesExpediente`.

- [ ] **Paso 3: Implementar**

Crear `resources/js/utils/versionesExpediente.js`:

```js
// Versión nueva de una plantilla del expediente: qué plantillas la tienen y la vista previa que
// se muestra antes de aplicarla (GET /expedientes/{e}/plantillas/{p}/version-nueva, que arma
// VersionesExpediente::vistaPrevia). Funciones puras, probadas en versionesExpediente.test.js.

import { ETIQUETA_CARACTER } from './expedienteChecklist';
import { escaparHtml } from './plantillasChecklist';

/** Requisitos que se muestran en cada lista de la vista previa; los demás solo se cuentan. */
export const TOPE_VISTA_PREVIA = 15;

/** Las plantillas del expediente (las de su cabecera) que tienen una versión nueva para aplicar. */
export const plantillasConVersionNueva = (plantillas) =>
    (Array.isArray(plantillas) ? plantillas : []).filter((p) => p !== null && typeof p === 'object' && p.version_nueva);

/**
 * "versión 3 disponible", o null si la plantilla no tiene versión nueva. La versión que usa el
 * expediente ya se muestra al lado ("SGR (v2)"): no se repite.
 */
export const textoVersionNueva = (plantilla) => {
    const nueva = plantilla?.version_nueva;
    return nueva ? `versión ${nueva.numero} disponible` : null;
};

/**
 * Nombre accesible del botón que la aplica: "Aplicar la versión 3 de Obras de agua". El botón
 * solo dice "Aplicar versión 3" y puede haber uno por plantilla. Null sin versión nueva.
 */
export const etiquetaAplicarVersion = (plantilla) => {
    const nueva = plantilla?.version_nueva;
    return nueva ? `Aplicar la versión ${nueva.numero} de ${plantilla.nombre}` : null;
};

/** Avance como lo muestra la pantalla: "12 de 30 (40%)". */
const textoAvance = (progreso) => `${progreso?.cumplidos ?? 0} de ${progreso?.total ?? 0} (${progreso?.porcentaje ?? 0}%)`;

const mismoAvance = (a, b) => a?.cumplidos === b?.cumplidos && a?.total === b?.total;

/** Lo que se muestra de una fila resumida, como contenidoFila: código y texto; un propio, solo su título. */
const contenido = (fila) => {
    // El backend solo manda `titulo` en un propio.
    const propio = fila?.titulo !== undefined;
    return { propio, codigo: propio ? null : (fila?.codigo ?? null), texto: (propio ? fila.titulo : fila?.texto) ?? '' };
};

const etiqueta = (texto) => `<span class="exv-vista-etiqueta">${escaparHtml(texto)}</span>`;

/** Código y texto de la fila, escapados. Un propio lo dice: en el checklist va en su propio bloque. */
const htmlTexto = (fila) => {
    const { propio, codigo, texto } = contenido(fila);
    return `${codigo ? `<b>${escaparHtml(codigo)}</b> ` : ''}${escaparHtml(texto)}${propio ? ` ${etiqueta('propio')}` : ''}`;
};

const caracterLegible = (caracter) => (ETIQUETA_CARACTER[caracter] ?? String(caracter ?? '')).toLowerCase();

/** Como en el checklist, solo se marca lo que no es obligatorio. */
const htmlCaracter = (fila) => (fila?.caracter && fila.caracter !== 'obligatorio' ? ` ${etiqueta(caracterLegible(fila.caracter))}` : '');

// Una fila con id ya existía: es una retirada que vuelve a aplicar, con el trabajo que tenía.
const htmlNuevo = (fila) => `${htmlTexto(fila)}${htmlCaracter(fila)}${fila?.id != null ? ` ${etiqueta('vuelve a aplicar')}` : ''}`;

const htmlRetirado = (fila) => `${htmlTexto(fila)}${fila?.con_trabajo ? ` ${etiqueta('conserva su trabajo')}` : ''}`;

const htmlCambio = ({ antes, despues }) => {
    const a = contenido(antes);
    const d = contenido(despues);
    const partes = [`${htmlTexto(antes)}${htmlCaracter(antes)}`];
    if (a.codigo !== d.codigo || a.texto !== d.texto) {
        partes.push(`<span class="exv-vista-ahora">Ahora: ${htmlTexto(despues)}</span>`);
    }
    if (antes?.caracter !== despues?.caracter) {
        partes.push(`<span class="exv-vista-ahora">Pasa de ${escaparHtml(caracterLegible(antes?.caracter))} a ${escaparHtml(caracterLegible(despues?.caracter))}.</span>`);
    }
    return partes.join('');
};

/** Lista con tope: las primeras TOPE_VISTA_PREVIA y "y N más". */
const htmlLista = (filas, htmlFila) => {
    const items = filas.slice(0, TOPE_VISTA_PREVIA).map((f) => `<li>${htmlFila(f)}</li>`);
    if (filas.length > TOPE_VISTA_PREVIA) items.push(`<li class="exv-vista-mas">y ${filas.length - TOPE_VISTA_PREVIA} más</li>`);
    return `<ul>${items.join('')}</ul>`;
};

const htmlSeccion = (titulo, filas, htmlFila) => (
    filas.length ? `<section><h3>${escaparHtml(titulo)}</h3>${htmlLista(filas, htmlFila)}</section>` : ''
);

/** El texto en singular o, con `{n}` reemplazado, en plural. */
const contar = (n, singular, varios) => (n === 1 ? singular : varios.replace('{n}', n));

/**
 * HTML de la vista previa para Swal: versión actual y nueva, la descripción de la nueva, el avance
 * antes y después, los requisitos que se agregan, los que dejan de aplicar (y si conservan su
 * trabajo), los que cambian y cuántos siguen igual. Todo texto va escapado.
 */
export const htmlVistaPrevia = (vista) => {
    const nuevos = vista?.nuevos ?? [];
    const retirados = vista?.retirados ?? [];
    const cambian = vista?.cambian ?? [];
    const siguen = Number(vista?.siguen) || 0;
    const { progreso_antes: antes, progreso_despues: despues } = vista ?? {};
    const descripcion = vista?.version_nueva?.descripcion?.trim();

    const partes = [
        `<p class="exv-vista-resumen"><b>Versión ${escaparHtml(vista?.version_actual?.numero)} → ${escaparHtml(vista?.version_nueva?.numero)}</b></p>`,
        descripcion ? `<p class="exv-vista-descripcion">${escaparHtml(descripcion)}</p>` : '',
        `<p>Avance: ${escaparHtml(mismoAvance(antes, despues) ? `${textoAvance(antes)}, no cambia` : `${textoAvance(antes)} → ${textoAvance(despues)}`)}</p>`,
    ];

    if (nuevos.length + retirados.length + cambian.length === 0) {
        partes.push('<p class="exv-vista-igual">La versión nueva no cambia el checklist de este expediente.</p>');
    }
    partes.push(
        htmlSeccion(contar(nuevos.length, 'Se agrega 1 requisito', 'Se agregan {n} requisitos'), nuevos, htmlNuevo),
        htmlSeccion(contar(retirados.length, 'Deja de aplicar 1 requisito', 'Dejan de aplicar {n} requisitos'), retirados, htmlRetirado),
        htmlSeccion(contar(cambian.length, 'Cambia 1 requisito', 'Cambian {n} requisitos'), cambian, htmlCambio),
    );
    if (siguen > 0) {
        partes.push(`<p>${escaparHtml(contar(siguen, '1 requisito sigue igual.', '{n} requisitos siguen igual.'))}</p>`);
    }

    return `<div class="exv-vista-version">${partes.join('')}</div>`;
};
```

En `resources/css/ExpedienteViabilizacion.css`, reemplazar (1 de 1):

```css
}
.exv-config-resumen b { margin-right: .25rem; font-weight: 700; color: var(--exv-tinta); }

/* Panel de configuración */
.exv-config {
    position: sticky;
```

por:

```css
}
.exv-config-resumen b { margin-right: .25rem; font-weight: 700; color: var(--exv-tinta); }

/* Plantilla con versión nueva: en la franja de configuración o al configurar */
.exv-version-nueva { display: inline-flex; flex-wrap: wrap; align-items: center; gap: .25rem .5rem; color: var(--exv-a12); }
.exv-version-nueva b { color: var(--exv-tinta); }
.exv-version-nueva .exv-btn { padding: .2rem .6rem; font-size: .76rem; }
.exv-versiones-nuevas { display: flex; flex-direction: column; align-items: flex-start; gap: .4rem; }

/* Vista previa de una versión nueva, dentro de Swal: fuera de .exv-pagina, con los tokens --gp-* */
.exv-vista-version { max-height: 60vh; overflow-y: auto; text-align: left; font-size: .88rem; line-height: 1.45; }
.exv-vista-version p { margin: 0 0 .6rem; }
.exv-vista-version b { color: var(--gp-texto); }
.exv-vista-descripcion { white-space: pre-line; color: var(--gp-texto-3); }
.exv-vista-igual { font-weight: 600; color: var(--gp-texto); }
.exv-vista-version section { margin: .9rem 0; }
.exv-vista-version h3 { margin: 0 0 .35rem; font-size: .92rem; font-weight: 700; color: var(--gp-texto); }
.exv-vista-version ul { margin: 0; padding-left: 1.2rem; }
.exv-vista-version li { margin-bottom: .3rem; }
.exv-vista-ahora { display: block; color: var(--gp-azul-texto); }
.exv-vista-etiqueta {
    display: inline-block;
    padding: 0 .4rem;
    font-size: .74rem;
    color: var(--gp-texto-2);
    background: var(--gp-superficie-3);
    border-radius: var(--gp-radio);
}
.exv-vista-mas { list-style: none; margin-left: -1.2rem; color: var(--gp-texto-3); }

/* Panel de configuración */
.exv-config {
    position: sticky;
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (1 de 7):

```jsx
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import { faArrowLeft, faCheck, faFileExcel, faPlus, faPrint, faSliders, faStamp, faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import ExpedienteChecklist, { AvisosDuplicados } from './ExpedienteChecklist';
```

por:

```jsx
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import {
    faArrowLeft, faArrowsRotate, faCheck, faFileExcel, faPlus, faPrint, faSliders, faStamp, faTrash,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import ExpedienteChecklist, { AvisosDuplicados } from './ExpedienteChecklist';
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (2 de 7):

```jsx
} from '../utils/plantillasExpediente';
import { mensajeApi } from '../utils/plantillasChecklist';
import { avisarError } from '../utils/avisosPlantillas';
import '../../css/ExpedienteViabilizacion.css';

const ROLES_EDICION = ['Administrador', 'Gestor'];
const ESPERA_RECALCULO_MS = 350;

const ExpedienteViabilizacion = ({ user }) => {
    const { proyectoId } = useParams();
    const [searchParams, setSearchParams] = useSearchParams();
```

por:

```jsx
} from '../utils/plantillasExpediente';
import { mensajeApi } from '../utils/plantillasChecklist';
import { avisarError } from '../utils/avisosPlantillas';
import {
    etiquetaAplicarVersion, htmlVistaPrevia, plantillasConVersionNueva, textoVersionNueva,
} from '../utils/versionesExpediente';
import '../../css/ExpedienteViabilizacion.css';

const ROLES_EDICION = ['Administrador', 'Gestor'];
const ESPERA_RECALCULO_MS = 350;

/** Una plantilla con versión nueva: "Obras de agua versión 3 disponible" y, si se puede aplicar, su botón. */
const VersionNueva = ({ plantilla, puedeAplicar, deshabilitada, onAplicar }) => (
    <span className="exv-version-nueva">
        <b>{plantilla.nombre}</b> {textoVersionNueva(plantilla)}
        {puedeAplicar && (
            <button
                type="button"
                className="exv-btn"
                onClick={() => onAplicar(plantilla)}
                disabled={deshabilitada}
                aria-label={etiquetaAplicarVersion(plantilla)}
            >
                <FontAwesomeIcon icon={faArrowsRotate} /> Aplicar versión {plantilla.version_nueva.numero}
            </button>
        )}
    </span>
);

const ExpedienteViabilizacion = ({ user }) => {
    const { proyectoId } = useParams();
    const [searchParams, setSearchParams] = useSearchParams();
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (3 de 7):

```jsx
        prev ? { ...prev, expedientes: actualizarExpedienteEnLista(prev.expedientes, data) } : prev
    )), []);

    // Contador de los GET de avisos (refrescarAvisos). Poner un expediente completo lo sube:
    // un GET de avisos que aún está en vuelo traería avisos más viejos que los recién puestos.
    const peticionAvisos = useRef(0);
    const ponerExpediente = useCallback((data) => {
        peticionAvisos.current += 1;
        setExpediente(data);
    }, []);

    // Como cargarPlantillas: solo vale la última petición, y solo si su expediente sigue abierto.
    // Una respuesta o un error que llegan tarde se descartan en silencio.
    const peticionExpediente = useRef(0);
    const cargarExpediente = useCallback(async (id) => {
        if (id !== idVigente.current) return;
        const numero = ++peticionExpediente.current;
```

por:

```jsx
        prev ? { ...prev, expedientes: actualizarExpedienteEnLista(prev.expedientes, data) } : prev
    )), []);

    // Contadores de los GET del expediente (cargarExpediente) y de sus avisos (refrescarAvisos).
    // Poner un expediente completo sube los dos: un GET que aún está en vuelo traería datos más
    // viejos que los recién puestos, por ejemplo la respuesta de una acción.
    const peticionAvisos = useRef(0);
    const peticionExpediente = useRef(0);
    const ponerExpediente = useCallback((data) => {
        peticionAvisos.current += 1;
        peticionExpediente.current += 1;
        setExpediente(data);
    }, []);

    // Como cargarPlantillas: solo vale la última petición, y solo si su expediente sigue abierto y
    // nada puso el expediente completo mientras llegaba. Una respuesta o un error que llegan tarde
    // se descartan en silencio. Lo que ella misma pone sube el contador después de comprobarlo.
    const cargarExpediente = useCallback(async (id) => {
        if (id !== idVigente.current) return;
        const numero = ++peticionExpediente.current;
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (4 de 7):

```jsx
        }
    });

    const exportarExcel = () => {
        const { resumen, checklist } = construirHojasExcel(expediente);
        const libro = XLSX.utils.book_new();
```

por:

```jsx
        }
    });

    // Primero la vista previa, que no cambia nada; se aplica la versión que se mostró, y el
    // servidor la rechaza si entretanto la plantilla publicó otra.
    const aplicarVersionNueva = (plantilla) => ejecutar(async () => {
        const { id } = expediente;
        const url = `/expedientes/${id}/plantillas/${plantilla.plantilla_id}/version-nueva`;
        let vista;
        try {
            ({ data: vista } = await axios.get(url));
        } catch (error) {
            // Si ya se muestra otro expediente, el error de este no se avisa ni se recarga.
            if (!esVigente(id)) return;
            avisarError('No se pudo preparar la vista previa', error);
            // Otra persona pudo aplicarla o publicar otra versión: la cabecera se pone al día.
            cargarExpediente(id).catch(() => {});
            return;
        }
        if (!esVigente(id)) return;

        const { isConfirmed } = await Swal.fire({
            // titleText, no title: el nombre de la plantilla es un dato y Swal lo escapa.
            titleText: `Aplicar la versión ${vista.version_nueva.numero} de «${vista.plantilla.nombre}»`,
            html: htmlVistaPrevia(vista),
            width: '46rem',
            showCancelButton: true,
            confirmButtonText: 'Aplicar versión',
            cancelButtonText: 'Cancelar',
        });
        // Si mientras se revisaba se abrió otro expediente, no se manda nada.
        if (!isConfirmed || !esVigente(id)) return;

        try {
            const { data } = await axios.post(url, { version_id: vista.version_nueva.id });
            // La lista del selector se indexa por id: ponerla al día vale aunque ya se muestre otro.
            actualizarEnLista(data);
            if (!esVigente(data.id)) return;
            ponerExpediente(data);
            setSeleccion(seleccionDesde(data));
            // La versión de la plantilla en el expediente cambió: la lista del panel lo refleja.
            cargarPlantillas(data.id);
        } catch (error) {
            if (!esVigente(id)) return;
            avisarError('No se pudo aplicar la versión nueva', error);
            cargarExpediente(id).catch(() => {});
        }
    });

    const exportarExcel = () => {
        const { resumen, checklist } = construirHojasExcel(expediente);
        const libro = XLSX.utils.book_new();
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (5 de 7):

```jsx
    const sinTipo = sinConfigurar && conSgr && seleccion ? sectoresSinTipo(seleccion, catalogo) : [];
    const sinPlantillasElegidas = Boolean(seleccion) && seleccion.plantilla_ids.length === 0;
    const incompatibles = avisoIncompatibles(expediente?.plantillas);

    return (
        <div className="exv-pagina">
```

por:

```jsx
    const sinTipo = sinConfigurar && conSgr && seleccion ? sectoresSinTipo(seleccion, catalogo) : [];
    const sinPlantillasElegidas = Boolean(seleccion) && seleccion.plantilla_ids.length === 0;
    const incompatibles = avisoIncompatibles(expediente?.plantillas);
    // Un radicado tiene el checklist congelado: no admite otra versión, y no se la ofrece.
    const versionesNuevas = expediente?.estado === 'borrador' ? plantillasConVersionNueva(expediente.plantillas) : [];
    const versionNueva = (p) => (
        <VersionNueva
            key={p.plantilla_id}
            plantilla={p}
            puedeAplicar={editable}
            deshabilitada={ocupado || recalculando || confirmando}
            onAplicar={aplicarVersionNueva}
        />
    );

    return (
        <div className="exv-pagina">
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (6 de 7):

```jsx
                                                            <b>Sin tipo de proyecto: {sinTipo.join(', ')}.</b> Se cargarán todos los requisitos de ese sector, incluidos los que no le apliquen al proyecto.
                                                        </div>
                                                    )}
                                                    <AvisosDuplicados avisos={expediente.avisos_duplicados} total={expediente.avisos_duplicados_total} />
                                                    <div className="exv-confirmar-pie">
                                                        <span>
```

por:

```jsx
                                                            <b>Sin tipo de proyecto: {sinTipo.join(', ')}.</b> Se cargarán todos los requisitos de ese sector, incluidos los que no le apliquen al proyecto.
                                                        </div>
                                                    )}
                                                    {versionesNuevas.length > 0 && (
                                                        <div className="exv-nota exv-versiones-nuevas exv-no-imprimir">
                                                            <b>{versionesNuevas.length === 1 ? 'Una plantilla tiene una versión nueva.' : 'Algunas plantillas tienen una versión nueva.'}</b>
                                                            {versionesNuevas.map(versionNueva)}
                                                        </div>
                                                    )}
                                                    <AvisosDuplicados avisos={expediente.avisos_duplicados} total={expediente.avisos_duplicados_total} />
                                                    <div className="exv-confirmar-pie">
                                                        <span>
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (7 de 7):

```jsx
                                    {!configAbierta && (
                                        <div className="exv-config-resumen exv-no-imprimir">
                                            <span><b>Plantillas</b> {plantillasConVersion(expediente.plantillas)}</span>
                                            {conSgr && (
                                                <>
                                                    <span><b>Fase SGR</b> {faseLegible(expediente.fase_sgr)}</span>
```

por:

```jsx
                                    {!configAbierta && (
                                        <div className="exv-config-resumen exv-no-imprimir">
                                            <span><b>Plantillas</b> {plantillasConVersion(expediente.plantillas)}</span>
                                            {versionesNuevas.map(versionNueva)}
                                            {conSgr && (
                                                <>
                                                    <span><b>Fase SGR</b> {faseLegible(expediente.fase_sgr)}</span>
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (1 de 1):

```js

const ETIQUETA_CATEGORIA = Object.fromEntries(CATEGORIAS);

const ETIQUETA_CARACTER = { obligatorio: 'Obligatorio', opcional: 'Opcional', condicionado: 'Condicionado' };

export const MOTOR_SGR = 'sgr';
```

por:

```js

const ETIQUETA_CATEGORIA = Object.fromEntries(CATEGORIAS);

export const ETIQUETA_CARACTER = { obligatorio: 'Obligatorio', opcional: 'Opcional', condicionado: 'Condicionado' };

export const MOTOR_SGR = 'sgr';
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/versionesExpediente.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 619 passed.

Run: `npm test`
Expected: 314 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/js/utils/versionesExpediente.js resources/js/utils/versionesExpediente.test.js resources/css/ExpedienteViabilizacion.css resources/js/components/ExpedienteViabilizacion.jsx resources/js/utils/expedienteChecklist.js
git commit -m "feat: apply a new template version from the expediente screen"
```

---

### Tarea 5: Historial del expediente: API

`GET /expedientes/{expediente}/historial`, paginado, con el resumen del requisito de cada entrada.

**Files:**
- Test (create): `tests/Feature/Viabilizacion/HistorialExpedienteApiTest.php`
- Modify: `app/Http/Controllers/ExpedienteController.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `app/Services/Viabilizacion/HistorialChecklist.php`
- Modify: `routes/viabilizacion.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Viabilizacion/HistorialExpedienteApiTest.php`:

```php
<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\ChecklistHistorial;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\HistorialChecklist;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Historial de todo el expediente: sus eventos y los de sus filas, por páginas. */
class HistorialExpedienteApiTest extends TestCase
{
    use RefreshDatabase;

    private ExpedienteService $servicio;
    private ProyectoExpediente $expediente;
    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        Storage::fake('local');

        $this->servicio = app(ExpedienteService::class);
        $this->gestor = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Gestora Uno']);
        $this->expediente = $this->expedienteSgr('Acueducto');
    }

    private function expedienteSgr(string $nombre): ProyectoExpediente
    {
        $proyecto = Proyecto::create(['nombre' => $nombre]);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        $expediente = $this->servicio->crear($proyecto, ['fase_sgr' => 3], $this->gestor);
        $this->servicio->confirmarConfiguracion($expediente, ['fase_sgr' => 3], $this->gestor);

        return $expediente;
    }

    private function como(Rol|User $quien): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function url(?ProyectoExpediente $expediente = null): string
    {
        return '/GestPro/expedientes/' . ($expediente ?? $this->expediente)->id;
    }

    private function historial(array $consulta = [], ?ProyectoExpediente $expediente = null): array
    {
        $url = $this->url($expediente) . '/historial' . ($consulta ? '?' . http_build_query($consulta) : '');

        return $this->como(Rol::Consulta)->getJson($url)->assertOk()->json();
    }

    /** @return list<ProyectoRequisito> filas que aplican, en su orden */
    private function filas(int $cuantas): array
    {
        return $this->expediente->requisitos()->where('aplica', true)->orderBy('orden')->take($cuantas)->get()->all();
    }

    private function agregarPropio(string $titulo): ProyectoRequisito
    {
        $id = $this->como($this->gestor)->postJson($this->url() . '/requisitos-propios', ['titulo' => $titulo])
            ->assertCreated()->json('requisito.id');

        return ProyectoRequisito::findOrFail($id);
    }

    /** Entradas de relleno en el historial de una fila. */
    private function registrar(ProyectoRequisito $fila, int $veces): void
    {
        $historial = app(HistorialChecklist::class);
        for ($i = 1; $i <= $veces; $i++) {
            $historial->registrarFila($fila, 'observacion_cambiada', ['observacion' => null], ['observacion' => "Nota {$i}"], $this->gestor);
        }
    }

    public function test_reune_los_eventos_del_expediente_y_de_sus_filas_del_mas_reciente_al_mas_antiguo(): void
    {
        [$primera, $segunda] = $this->filas(2);
        $this->como($this->gestor)->patchJson("{$this->url()}/requisitos/{$primera->id}", ['cumplido' => true])->assertOk();
        $this->como($this->gestor)->patchJson("{$this->url()}/requisitos/{$segunda->id}", ['observacion' => 'Falta firma'])->assertOk();
        DB::table('proyectos_requisitos')->where('expediente_id', $this->expediente->id)->update(['cumplido' => true]);
        $this->como($this->gestor)->postJson("{$this->url()}/radicar")->assertOk();

        $respuesta = $this->historial();
        $entradas = $respuesta['entradas'];

        $this->assertSame(
            ['expediente_radicado', 'observacion_cambiada', 'cumplido_marcado', 'configuracion_confirmada', 'expediente_creado'],
            array_column($entradas, 'evento')
        );
        $this->assertSame(['expediente', 'requisito', 'requisito', 'expediente', 'expediente'], array_column($entradas, 'entidad'));
        $this->assertSame(array_values(array_reverse(array_column($entradas, 'id'))), DB::table('checklist_historial')->orderBy('id')->pluck('id')->all());
        $this->assertSame([$segunda->id, $primera->id], [$entradas[1]['requisito']['id'], $entradas[2]['requisito']['id']]);
        $this->assertSame(['id', 'evento', 'antes', 'despues', 'usuario', 'sistema', 'fecha', 'soporte', 'entidad', 'requisito'], array_keys($entradas[1]));
        $this->assertNull($entradas[0]['requisito']);
        $this->assertSame('Gestora Uno', $entradas[1]['usuario']);
        $this->assertFalse($entradas[1]['sistema']);
        $this->assertSame(['observacion' => 'Falta firma'], $entradas[1]['despues']);
        $this->assertNotNull($entradas[1]['fecha']);
        $this->assertSame(['pagina' => 1, 'por_pagina' => 50, 'total' => 5], array_diff_key($respuesta, ['entradas' => true]));
    }

    public function test_pagina_de_a_cincuenta(): void
    {
        $this->registrar($this->filas(1)[0], 58); // con las 2 del expediente, 60
        $todas = DB::table('checklist_historial')->orderByDesc('id')->pluck('id')->all();

        $primera = $this->historial();
        $segunda = $this->historial(['pagina' => 2]);
        $fuera = $this->historial(['pagina' => 3]);

        $this->assertCount(50, $primera['entradas']);
        $this->assertSame([2, 50, 60], [$segunda['pagina'], $segunda['por_pagina'], $segunda['total']]);
        $this->assertSame($todas, [...array_column($primera['entradas'], 'id'), ...array_column($segunda['entradas'], 'id')]);
        $this->assertSame(['entradas' => [], 'pagina' => 3, 'por_pagina' => 50, 'total' => 60], $fuera);
        $this->assertSame([], $this->historial(['pagina' => 999999999])['entradas']);
    }

    public function test_distingue_lo_que_registro_el_sistema_de_lo_que_registro_un_usuario_eliminado(): void
    {
        // Sin usuario, como lo crea expedientes:crear-faltantes.
        $proyecto = Proyecto::create(['nombre' => 'Vía terciaria']);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        $expediente = $this->servicio->crear($proyecto, ['fase_sgr' => 3], null);
        $retirado = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Gestor Retirado']);
        $fila = $expediente->requisitos()->where('aplica', true)->orderBy('orden')->first();
        app(HistorialChecklist::class)->registrarFila($fila, 'observacion_cambiada', ['observacion' => null], ['observacion' => 'Revisado'], $retirado);
        $retirado->delete();

        $entradas = collect($this->historial([], $expediente)['entradas'])->keyBy('evento');

        $this->assertNull($entradas['expediente_creado']['usuario']);
        $this->assertTrue($entradas['expediente_creado']['sistema']);
        $this->assertNull($entradas['observacion_cambiada']['usuario']);
        $this->assertFalse($entradas['observacion_cambiada']['sistema']);
        // El historial de la fila se presenta igual.
        $deFila = $this->como(Rol::Consulta)->getJson("{$this->url($expediente)}/requisitos/{$fila->id}/historial")->assertOk()->json();
        $this->assertSame([null, false], [$deFila[0]['usuario'], $deFila[0]['sistema']]);
    }

    public function test_una_fila_de_plantilla_trae_su_codigo_y_su_texto(): void
    {
        $fila = $this->filas(1)[0];
        $this->registrar($fila, 1);
        $mostrada = collect($this->como(Rol::Consulta)->getJson($this->url())->json('requisitos'))->firstWhere('id', $fila->id);

        $this->assertSame([
            'id' => $fila->id,
            'codigo' => $mostrada['requisito']['codigo'],
            'texto' => $mostrada['requisito']['texto'],
            'titulo' => null,
        ], $this->historial()['entradas'][0]['requisito']);
        $this->assertNotNull($mostrada['requisito']['codigo']);
    }

    public function test_una_fila_compartida_muestra_el_requisito_de_la_plantilla_principal(): void
    {
        $agua = PlantillaChecklist::create([
            'nombre' => 'Obras de agua', 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'activo' => true,
            'todas_fuentes' => true, 'todos_sectores' => true,
        ]);
        $version = $agua->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        RequisitoCatalogo::create([
            'catalogo_version_id' => $version->id, 'clave' => 'r1', 'texto' => 'Presupuesto detallado de agua', 'orden' => 1,
            'codigo_comun' => 'a1', 'caracter' => RequisitoCatalogo::OBLIGATORIO, 'codigo' => 'AG-1',
        ]);
        $sgr = PlantillaChecklist::sgr();
        $this->servicio->actualizarConfiguracion($this->expediente, [
            'fase_sgr' => 3, 'plantilla_ids' => [$sgr->id, $agua->id], 'principal_id' => $agua->id,
        ], $this->gestor);
        $a1 = RequisitoCatalogo::where('catalogo_version_id', $sgr->versionVigente()->id)->where('clave', 'a1')->sole();
        $compartida = $this->expediente->requisitos()->where('requisito_catalogo_id', $a1->id)->sole();
        $this->registrar($compartida, 1);

        $entrada = collect($this->historial()['entradas'])->firstWhere('requisito.id', $compartida->id);

        $this->assertSame(['id' => $compartida->id, 'codigo' => 'AG-1', 'texto' => 'Presupuesto detallado de agua', 'titulo' => null], $entrada['requisito']);
    }

    public function test_un_propio_se_resume_por_su_titulo_y_uno_eliminado_llega_sin_requisito(): void
    {
        $vigente = $this->agregarPropio('Acta de socialización');
        $eliminado = $this->agregarPropio('Permiso de vertimientos');
        $this->como($this->gestor)->deleteJson("{$this->url()}/requisitos-propios/{$eliminado->id}")->assertOk();

        $entradas = collect($this->historial()['entradas']);

        $this->assertSame(
            ['requisito_propio_eliminado', 'requisito_propio_agregado', 'requisito_propio_agregado'],
            $entradas->take(3)->pluck('evento')->all()
        );
        [$baja, $altaEliminado, $altaVigente] = $entradas->take(3)->all();
        $this->assertSame(
            ['id' => $vigente->id, 'codigo' => null, 'texto' => null, 'titulo' => 'Acta de socialización'],
            $altaVigente['requisito']
        );
        $this->assertSame('requisito', $baja['entidad']);
        $this->assertNull($baja['requisito']);
        $this->assertSame('Permiso de vertimientos', $baja['antes']['titulo']);
        $this->assertNull($altaEliminado['requisito']);
        $this->assertSame('Permiso de vertimientos', $altaEliminado['despues']['titulo']);
    }

    public function test_no_expone_la_ruta_de_los_soportes(): void
    {
        $fila = $this->filas(1)[0];
        foreach (['v1.pdf', 'v2.pdf'] as $nombre) {
            $this->como($this->gestor)->post("{$this->url()}/requisitos/{$fila->id}/soporte", [
                'archivo' => UploadedFile::fake()->create($nombre, 20, 'application/pdf'),
            ])->assertOk();
        }
        $rutas = DB::table('checklist_historial')->where('evento', 'soporte_reemplazado')->pluck('antes')
            ->map(fn ($antes) => json_decode($antes, true)['soporte_archivo']);
        $this->assertCount(1, $rutas);

        $respuesta = $this->como(Rol::Consulta)->getJson("{$this->url()}/historial")->assertOk();

        $this->assertStringNotContainsString('soporte_archivo', $respuesta->getContent());
        $this->assertStringNotContainsString(basename($rutas[0]), $respuesta->getContent());
        $this->assertStringNotContainsString(basename($fila->fresh()->soporte_archivo), $respuesta->getContent());
        $reemplazo = collect($respuesta->json('entradas'))->firstWhere('evento', 'soporte_reemplazado');
        $this->assertSame(['nombre' => 'v1.pdf'], $reemplazo['soporte']);
        $this->como(Rol::Consulta)->get("{$this->url()}/historial/{$reemplazo['id']}/soporte")->assertOk()->assertDownload('v1.pdf');
    }

    public function test_lo_consulta_cualquier_usuario_autenticado(): void
    {
        $this->getJson("{$this->url()}/historial")->assertUnauthorized();
        $this->como(Rol::Consulta)->getJson("{$this->url()}/historial")->assertOk()->assertJsonPath('total', 2);
        $this->como(Rol::Consulta)->getJson('/GestPro/expedientes/999999/historial')->assertNotFound();
    }

    public function test_una_pagina_invalida_se_rechaza_en_espanol(): void
    {
        $casos = [
            'pagina=0' => 'Revisa la página: debe ser 1 o mayor.',
            'pagina=-1' => 'Revisa la página: debe ser 1 o mayor.',
            'pagina=dos' => 'Revisa la página: debe ser un número entero.',
            'pagina=1.5' => 'Revisa la página: debe ser un número entero.',
            'pagina=' => 'Revisa la página: debe ser un número entero.',
            'pagina[]=1' => 'Revisa la página: debe ser un número entero.',
            'pagina=99999999999999999999' => 'Revisa la página: debe ser un número entero.',
        ];
        foreach ($casos as $consulta => $mensaje) {
            $this->como(Rol::Consulta)->getJson("{$this->url()}/historial?{$consulta}")
                ->assertStatus(422)
                ->assertJsonValidationErrors(['pagina' => $mensaje]);
        }
    }

    public function test_no_mezcla_el_historial_de_otro_expediente(): void
    {
        $otro = $this->expedienteSgr('Vía terciaria');
        $filaOtro = $otro->requisitos()->where('aplica', true)->orderBy('orden')->first();
        $this->registrar($filaOtro, 3);

        $propias = DB::table('checklist_historial')->where('expediente_id', $this->expediente->id)->orderByDesc('id')->pluck('id')->all();
        $this->assertSame($propias, array_column($this->historial()['entradas'], 'id'));
        $this->assertSame(2, $this->historial()['total']);
        $this->assertSame(5, $this->historial([], $otro)['total']);
    }

    public function test_las_consultas_no_crecen_con_las_entradas_ni_con_las_filas(): void
    {
        $medir = function (): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson("{$this->url()}/historial")->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        [$primera, $segunda, $tercera, $cuarta] = $this->filas(4);
        $propio = $this->agregarPropio('Acta de socialización');
        $this->registrar($primera, 1);
        $conPocas = $medir();

        foreach ([$segunda, $tercera, $cuarta, $propio] as $fila) {
            $this->registrar($fila, 10);
        }
        $eliminado = $this->agregarPropio('Permiso de vertimientos');
        $this->como($this->gestor)->deleteJson("{$this->url()}/requisitos-propios/{$eliminado->id}")->assertOk();

        $this->assertSame($conPocas, $medir());
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Viabilizacion/HistorialExpedienteApiTest.php`
Expected: FALLAN 10 y pasa 1. La ruta `GET /expedientes/{expediente}/historial` no existe y el comodín web responde HTML.

- [ ] **Paso 3: Implementar**

En `app/Http/Controllers/ExpedienteController.php`, reemplazar (1 de 1):

```php
        return response()->json($this->historial->deFila($requisito));
    }

    /** Soporte reemplazado o retirado, conservado en el historial. */
    public function descargarSoporteHistorial(ProyectoExpediente $expediente, ChecklistHistorial $entrada): StreamedResponse|JsonResponse
    {
```

por:

```php
        return response()->json($this->historial->deFila($requisito));
    }

    /** Todo el historial del expediente, el suyo y el de sus filas, por páginas. */
    public function historialExpediente(Request $request, ProyectoExpediente $expediente): JsonResponse
    {
        // De la consulta llega texto: `integer` admite "2", pero no "2.5" ni "dos".
        $datos = $request->validate(['pagina' => ['sometimes', 'bail', 'integer', 'min:1']], [
            'pagina.integer' => 'Revisa la página: debe ser un número entero.',
            'pagina.min' => 'Revisa la página: debe ser 1 o mayor.',
        ]);

        return response()->json($this->servicio->historialDe($expediente, (int) ($datos['pagina'] ?? 1)));
    }

    /** Soporte reemplazado o retirado, conservado en el historial. */
    public function descargarSoporteHistorial(ProyectoExpediente $expediente, ChecklistHistorial $entrada): StreamedResponse|JsonResponse
    {
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 2):

```php
use App\Models\Responsable;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
```

por:

```php
use App\Models\Responsable;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 2):

```php
        ];
    }

    /**
     * @param Collection<int, CatalogoVersion> $versiones las de versionesDe()
     * @return array<int, int> plantilla_id => posición (0 es la principal)
```

por:

```php
        ];
    }

    /**
     * Una página del historial completo del expediente (ver HistorialChecklist::paginaDeExpediente()).
     * Cada entrada de una fila que todavía existe trae en `requisito` lo que muestra la fila; si
     * la fila ya no existe, null (un propio eliminado guarda su título en `antes`).
     *
     * @return array{entradas: list<array>, pagina: int, por_pagina: int, total: int}
     */
    public function historialDe(ProyectoExpediente $expediente, int $pagina): array
    {
        $historial = $this->historial->paginaDeExpediente($expediente, $pagina);
        $deFila = fn (array $entrada) => $entrada['entidad'] === HistorialChecklist::REQUISITO;
        $filas = $this->resumenFilasHistorial($expediente, collect($historial['entradas'])->filter($deFila)->pluck('entidad_id'));

        return [
            // La fila se identifica por `requisito`; el id de la entidad no sale.
            'entradas' => array_map(fn (array $entrada) => [
                ...Arr::except($entrada, 'entidad_id'),
                'requisito' => $deFila($entrada) ? ($filas[$entrada['entidad_id']] ?? null) : null,
            ], $historial['entradas']),
            'pagina' => $pagina,
            'por_pagina' => $historial['por_pagina'],
            'total' => $historial['total'],
        ];
    }

    /**
     * Resumen de las filas dadas que todavía existen, leídas juntas: el requisito que muestra la
     * fila, como presentarFila(), o el título de un propio, sin código ni texto.
     *
     * @param Collection<int, int> $ids
     * @return array<int, array{id: int, codigo: ?string, texto: ?string, titulo: ?string}> id de la fila => resumen
     */
    private function resumenFilasHistorial(ProyectoExpediente $expediente, Collection $ids): array
    {
        $filas = $ids->isEmpty() ? collect() : $expediente->requisitos()
            ->whereIn('id', $ids->unique()->values())
            ->with(['requisito', 'origenes.version:id,plantilla_id'])
            ->get();
        if ($filas->isEmpty()) {
            return [];
        }
        $posiciones = $this->posiciones($this->plantillas->versionesDe($expediente));

        return $filas->mapWithKeys(function (ProyectoRequisito $fila) use ($posiciones) {
            $mostrado = $this->requisitoMostrado($fila, $posiciones);

            return [$fila->id => [
                'id' => $fila->id,
                'codigo' => $mostrado?->codigo,
                'texto' => $mostrado?->texto,
                'titulo' => $fila->esPropio() ? $fila->titulo_propio : null,
            ]];
        })->all();
    }

    /**
     * @param Collection<int, CatalogoVersion> $versiones las de versionesDe()
     * @return array<int, int> plantilla_id => posición (0 es la principal)
```

En `app/Services/Viabilizacion/HistorialChecklist.php`, reemplazar (1 de 4):

```php
    public const REQUISITO = 'requisito';
    public const EXPEDIENTE = 'expediente';

    public function registrarFila(ProyectoRequisito $fila, string $evento, array $antes, array $despues, ?User $usuario): void
    {
        $this->registrar(self::REQUISITO, $fila->id, $fila->expediente_id, $evento, $antes, $despues, $usuario);
```

por:

```php
    public const REQUISITO = 'requisito';
    public const EXPEDIENTE = 'expediente';

    /** Entradas por página del historial del expediente. */
    public const POR_PAGINA = 50;

    public function registrarFila(ProyectoRequisito $fila, string $evento, array $antes, array $despues, ?User $usuario): void
    {
        $this->registrar(self::REQUISITO, $fila->id, $fila->expediente_id, $evento, $antes, $despues, $usuario);
```

En `app/Services/Viabilizacion/HistorialChecklist.php`, reemplazar (2 de 4):

```php
            ->all();
    }

    /**
     * Estado de aplicabilidad que tenía la fila cuando se marcó su último "no aplica", o null
     * si no hay registro.
```

por:

```php
            ->all();
    }

    /**
     * Una página del historial completo del expediente, del más reciente al más antiguo: sus
     * propios eventos y los de todas sus filas, también los de las ya eliminadas. Se pagina
     * desde 1; una página fuera de rango llega vacía, con el mismo total.
     *
     * Cada entrada es la de presentar() más `entidad` y `entidad_id`, para que quien arma la
     * respuesta encuentre la fila de las entradas de requisito.
     *
     * @return array{entradas: list<array>, por_pagina: int, total: int}
     */
    public function paginaDeExpediente(ProyectoExpediente $expediente, int $pagina): array
    {
        $consulta = ChecklistHistorial::query()->where('expediente_id', $expediente->id);
        $total = (clone $consulta)->count();
        // Fuera de rango no se consulta: así tampoco se calcula un desplazamiento enorme.
        $entradas = $pagina > intdiv($total + self::POR_PAGINA - 1, self::POR_PAGINA)
            ? collect()
            : $consulta->with('usuario:id,name')->orderByDesc('id')->forPage($pagina, self::POR_PAGINA)->get();

        return [
            'entradas' => $entradas->map(fn (ChecklistHistorial $h) => [
                ...$this->presentar($h),
                'entidad' => $h->entidad_tipo,
                'entidad_id' => $h->entidad_id,
            ])->all(),
            'por_pagina' => self::POR_PAGINA,
            'total' => $total,
        ];
    }

    /**
     * Estado de aplicabilidad que tenía la fila cuando se marcó su último "no aplica", o null
     * si no hay registro.
```

En `app/Services/Viabilizacion/HistorialChecklist.php`, reemplazar (3 de 4):

```php
            ->all();
    }

    public function presentar(ChecklistHistorial $entrada): array
    {
        return [
```

por:

```php
            ->all();
    }

    /**
     * `usuario` es el nombre de quien registró la entrada, o null. `sistema` dice por qué falta:
     * true si nadie la registró (un comando, como expedientes:crear-faltantes); false con un
     * usuario_id cuyo usuario ya no existe (la tabla no tiene clave foránea a users).
     */
    public function presentar(ChecklistHistorial $entrada): array
    {
        return [
```

En `app/Services/Viabilizacion/HistorialChecklist.php`, reemplazar (4 de 4):

```php
            'antes' => $this->sinRuta($entrada->antes),
            'despues' => $this->sinRuta($entrada->despues),
            'usuario' => $entrada->usuario?->name,
            'fecha' => $entrada->created_at?->toIso8601String(),
            'soporte' => isset($entrada->antes['soporte_archivo'])
                ? ['nombre' => $entrada->antes['soporte_nombre'] ?? 'soporte']
```

por:

```php
            'antes' => $this->sinRuta($entrada->antes),
            'despues' => $this->sinRuta($entrada->despues),
            'usuario' => $entrada->usuario?->name,
            'sistema' => $entrada->usuario_id === null,
            'fecha' => $entrada->created_at?->toIso8601String(),
            'soporte' => isset($entrada->antes['soporte_archivo'])
                ? ['nombre' => $entrada->antes['soporte_nombre'] ?? 'soporte']
```

En `routes/viabilizacion.php`, reemplazar (1 de 1):

```php
    ->scopeBindings()->name('expedientes.requisitos.soporte.descargar');
Route::get('/expedientes/{expediente}/requisitos/{requisito}/historial', [ExpedienteController::class, 'historialRequisito'])
    ->scopeBindings()->name('expedientes.requisitos.historial');
Route::get('/expedientes/{expediente}/historial/{entrada}/soporte', [ExpedienteController::class, 'descargarSoporteHistorial'])
    ->name('expedientes.historial.soporte');
```

por:

```php
    ->scopeBindings()->name('expedientes.requisitos.soporte.descargar');
Route::get('/expedientes/{expediente}/requisitos/{requisito}/historial', [ExpedienteController::class, 'historialRequisito'])
    ->scopeBindings()->name('expedientes.requisitos.historial');
Route::get('/expedientes/{expediente}/historial', [ExpedienteController::class, 'historialExpediente'])
    ->name('expedientes.historial');
Route::get('/expedientes/{expediente}/historial/{entrada}/soporte', [ExpedienteController::class, 'descargarSoporteHistorial'])
    ->name('expedientes.historial.soporte');
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Viabilizacion/HistorialExpedienteApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 630 passed.

- [ ] **Paso 5: Commit**

```bash
git add tests/Feature/Viabilizacion/HistorialExpedienteApiTest.php app/Http/Controllers/ExpedienteController.php app/Services/Viabilizacion/ExpedienteService.php app/Services/Viabilizacion/HistorialChecklist.php routes/viabilizacion.php
git commit -m "feat: expediente history endpoint"
```

---

### Tarea 6: Historial del expediente en pantalla

Botón "Historial" en la barra del expediente y componente `ExpedienteHistorial`.

**Files:**
- Create: `resources/js/components/ExpedienteHistorial.jsx`
- Modify: `resources/css/ExpedienteViabilizacion.css`
- Modify: `resources/js/components/ExpedienteHistorialRequisito.jsx`
- Modify: `resources/js/components/ExpedienteViabilizacion.jsx`
- Modify: `resources/js/utils/expedienteChecklist.js`
- Test: `resources/js/utils/expedienteChecklist.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (1 de 3):

```js
    agruparRequisitos,
    alternarId,
    alternarSector,
    construirHojasExcel,
    contenidoFila,
    datosPropio,
    describirEvento,
    formularioPropio,
    formularioVigente,
    mensajeError,
    motivoNoEliminarPropio,
    nombreArchivoExcel,
```

por:

```js
    agruparRequisitos,
    alternarId,
    alternarSector,
    autorHistorial,
    construirHojasExcel,
    contenidoFila,
    datosPropio,
    describirEvento,
    fechaHoraHistorial,
    formularioPropio,
    formularioVigente,
    hayMasHistorial,
    mensajeError,
    motivoNoEliminarPropio,
    nombreArchivoExcel,
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (2 de 3):

```js
    textoOrigenes,
    textoPlantillas,
    textoPorDefinir,
} from './expedienteChecklist';

const fila = (id, seccion, extra = {}) => ({
```

por:

```js
    textoOrigenes,
    textoPlantillas,
    textoPorDefinir,
    textoRequisitoHistorial,
    unirPaginaHistorial,
} from './expedienteChecklist';

const fila = (id, seccion, extra = {}) => ({
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (3 de 3):

```js
        expect(formularioVigente(4, undefined)).toBeNull();
    });
});
```

por:

```js
        expect(formularioVigente(4, undefined)).toBeNull();
    });
});

describe('historial del expediente', () => {
    const configuracion = (extra = {}) => ({
        plantillas: [
            { plantilla_id: 1, catalogo_version_id: 10, es_principal: true },
            { plantilla_id: 2, catalogo_version_id: 20, es_principal: false },
        ],
        fase_sgr: 3,
        sector_ids: [4],
        tipo_ids: [],
        componente_ids: [7],
        tipologia_ids: [],
        ...extra,
    });

    it('describe los eventos del propio expediente', () => {
        expect(describirEvento({ evento: 'expediente_creado', antes: [], despues: { plantillas: [] } })).toBe('Creó el expediente');
        expect(describirEvento({ evento: 'configuracion_confirmada', antes: [], despues: configuracion() })).toBe('Confirmó la configuración');
        expect(describirEvento({ evento: 'expediente_radicado', antes: [], despues: { requisitos: 12 } })).toBe('Radicó el expediente');
    });

    it('dice qué cambió en la configuración', () => {
        const cambio = (despues) => describirEvento({ evento: 'configuracion_cambiada', antes: configuracion(), despues });
        expect(cambio(configuracion({ sector_ids: [4, 5] }))).toBe('Cambió la configuración: sectores');
        expect(cambio(configuracion({ fase_sgr: 2, tipo_ids: [8], tipologia_ids: [9] })))
            .toBe('Cambió la configuración: fase SGR, tipos de proyecto y tipologías');
        expect(cambio(configuracion({ componente_ids: [] }))).toBe('Cambió la configuración: componentes');
        // Los ids se comparan como conjuntos: el orden no es un cambio.
        expect(cambio(configuracion({ sector_ids: [4], componente_ids: [7] }))).toBe('Cambió la configuración');
    });

    it('nombra las plantillas, o solo la principal si las plantillas son las mismas', () => {
        const antes = configuracion();
        const otraPrincipal = configuracion({
            plantillas: [
                { plantilla_id: 2, catalogo_version_id: 20, es_principal: true },
                { plantilla_id: 1, catalogo_version_id: 10, es_principal: false },
            ],
        });
        expect(describirEvento({ evento: 'configuracion_cambiada', antes, despues: otraPrincipal }))
            .toBe('Cambió la configuración: plantilla principal');

        // Al quitar la plantilla sgr también se va la fase: lo que cambió son las plantillas.
        const { fase_sgr: _fase, ...sinSgr } = configuracion({ plantillas: [{ plantilla_id: 2, catalogo_version_id: 20, es_principal: true }] });
        expect(describirEvento({ evento: 'configuracion_cambiada', antes, despues: sinSgr }))
            .toBe('Cambió la configuración: plantillas');
        expect(describirEvento({ evento: 'configuracion_cambiada', antes: null, despues: null })).toBe('Cambió la configuración');
    });

    it('describe la versión aplicada con la anterior', () => {
        expect(describirEvento({
            evento: 'version_aplicada',
            antes: { plantilla_id: 2, plantilla: 'Obras de agua', version: 2, catalogo_version_id: 20 },
            despues: { plantilla_id: 2, plantilla: 'Obras de agua', version: 3, catalogo_version_id: 21 },
        })).toBe('Aplicó la versión 3 de Obras de agua (antes 2)');
        expect(describirEvento({ evento: 'version_aplicada', antes: {}, despues: { plantilla: 'Vías', version: 4 } }))
            .toBe('Aplicó la versión 4 de Vías');
        expect(describirEvento({ evento: 'version_aplicada', antes: null, despues: null })).toBe('Aplicó una versión nueva de una plantilla');
    });

    it('da el requisito de cada entrada en corto', () => {
        const largo = 'Carta de presentación del proyecto firmada por el representante legal de la entidad, con la fecha y el valor total solicitado';
        const texto = textoRequisitoHistorial({ entidad: 'requisito', requisito: { id: 1, codigo: 'A.1.2', texto: largo, titulo: null } });
        expect(texto.startsWith('A.1.2 · Carta de presentación')).toBe(true);
        expect(texto.endsWith('…')).toBe(true);
        expect(texto.length).toBeLessThanOrEqual('A.1.2 · '.length + 90);

        expect(textoRequisitoHistorial({ entidad: 'requisito', requisito: { id: 1, codigo: 'B.3', texto: 'Acta corta', titulo: null } }))
            .toBe('B.3 · Acta corta');
        expect(textoRequisitoHistorial({ entidad: 'requisito', requisito: { id: 1, codigo: null, texto: 'Sin código', titulo: null } }))
            .toBe('Sin código');
        expect(textoRequisitoHistorial({ entidad: 'requisito', requisito: { id: 2, codigo: null, texto: null, titulo: 'Acta de vecinos' } }))
            .toBe('Acta de vecinos');
    });

    it('de una fila que ya no existe usa el título del propio eliminado; del expediente, nada', () => {
        expect(textoRequisitoHistorial({ entidad: 'requisito', requisito: null, antes: { titulo: 'Acta de vecinos' } })).toBe('Acta de vecinos');
        expect(textoRequisitoHistorial({ entidad: 'requisito', requisito: null, antes: {} })).toBe('Requisito que ya no está en el checklist');
        expect(textoRequisitoHistorial({ entidad: 'expediente', requisito: null, antes: [] })).toBeNull();
    });

    it('une las páginas sin repetir lo que se corrió de una a otra', () => {
        const primera = [{ id: 9 }, { id: 8 }, { id: 7 }];
        expect(unirPaginaHistorial(primera, [{ id: 7 }, { id: 6 }]).map((e) => e.id)).toEqual([9, 8, 7, 6]);
        expect(unirPaginaHistorial([], [{ id: 3 }])).toEqual([{ id: 3 }]);
    });

    it('sabe si quedan páginas', () => {
        expect(hayMasHistorial({ pagina: 1, por_pagina: 50, total: 51 })).toBe(true);
        expect(hayMasHistorial({ pagina: 1, por_pagina: 50, total: 50 })).toBe(false);
        expect(hayMasHistorial({ pagina: 2, por_pagina: 50, total: 120 })).toBe(true);
        expect(hayMasHistorial({ pagina: 1, por_pagina: 50, total: 0 })).toBe(false);
        expect(hayMasHistorial(null)).toBe(false);
    });

    it('formatea la fecha y hora, y nada sin fecha', () => {
        expect(fechaHoraHistorial(null)).toBe('');
        expect(fechaHoraHistorial('2026-11-09T10:18:15-05:00')).toMatch(/\d/);
    });

    it('nombra a quien registró la entrada, al sistema o a un usuario que ya no existe', () => {
        expect(autorHistorial({ usuario: 'Gestora Uno', sistema: false })).toBe('Gestora Uno');
        expect(autorHistorial({ usuario: null, sistema: true })).toBe('Sistema');
        expect(autorHistorial({ usuario: null, sistema: false })).toBe('Usuario eliminado');
    });
});
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/expedienteChecklist.test.js`
Expected: FALLAN 10 y pasan 80: `describirEvento` no conoce los eventos del expediente y no existen `textoRequisitoHistorial`, `fechaHoraHistorial`, `unirPaginaHistorial`, `hayMasHistorial` ni `autorHistorial`.

- [ ] **Paso 3: Implementar**

Crear `resources/js/components/ExpedienteHistorial.jsx`:

```jsx
import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import { faDownload, faRotateRight } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import {
    autorHistorial,
    describirEvento,
    fechaHoraHistorial,
    hayMasHistorial,
    mensajeError,
    textoRequisitoHistorial,
    unirPaginaHistorial,
} from '../utils/expedienteChecklist';

/**
 * Historial completo del expediente (sus movimientos y los de sus requisitos), del más reciente
 * al más antiguo. Se pide desde la página 1 al abrirse y cada vez que cambia `revision`, que el
 * padre sube con cada cambio que el servidor ya registró; "Cargar más" pide la página siguiente
 * cuando llegó la anterior.
 */
const ExpedienteHistorial = ({ id, expedienteId, revision, onDescargar }) => {
    // La última respuesta, con las entradas de todas las páginas cargadas, y de qué expediente es.
    const [historial, setHistorial] = useState(null);
    // Página que se está pidiendo, o null.
    const [cargando, setCargando] = useState(null);
    // { mensaje, pagina }: la página que falló, para reintentarla.
    const [error, setError] = useState(null);
    const idTitulo = useId();

    // Lo sube cada carga desde la página 1, y también desmontarse: lo que responda una carga
    // anterior, sea de la página que sea o de otro expediente, llega tarde y se descarta.
    const carga = useRef(0);

    const pedir = useCallback(async (pagina, numero) => {
        setCargando(pagina);
        setError(null);
        try {
            const { data } = await axios.get(`/expedientes/${expedienteId}/historial`, { params: { pagina } });
            if (numero !== carga.current) return;
            setHistorial((prev) => ({
                ...data,
                expedienteId,
                entradas: pagina === 1 ? data.entradas : unirPaginaHistorial(prev.entradas, data.entradas),
            }));
        } catch (e) {
            if (numero === carga.current) setError({ mensaje: mensajeError(e), pagina });
        } finally {
            if (numero === carga.current) setCargando(null);
        }
    }, [expedienteId]);

    const recargar = useCallback(() => pedir(1, ++carga.current), [pedir]);

    useEffect(() => {
        recargar();
        return () => { carga.current += 1; };
    }, [recargar, revision]);

    // Mientras llega el primero, lo cargado de otro expediente no se muestra.
    const datos = historial?.expedienteId === expedienteId ? historial : null;
    // Las páginas van en orden: la siguiente solo se pide con la anterior ya puesta.
    const cargarMas = () => pedir(datos.pagina + 1, carga.current);
    const reintentar = () => (error?.pagina === 1 ? recargar() : cargarMas());

    const botonReintentar = (
        <button type="button" className="exv-btn" onClick={reintentar} disabled={cargando !== null}>
            <FontAwesomeIcon icon={faRotateRight} /> Reintentar
        </button>
    );

    let cuerpo;
    if (!datos) {
        cuerpo = error ? (
            <div className="exv-hist-estado" role="alert">
                <p>No se pudo cargar el historial: {error.mensaje}</p>
                {botonReintentar}
            </div>
        ) : (
            <p className="exv-hist-estado" role="status">Cargando historial…</p>
        );
    } else if (datos.entradas.length === 0) {
        cuerpo = <p className="exv-hist-estado">Aún no hay movimientos en este expediente.</p>;
    } else {
        cuerpo = (
            <ol className="exv-hist-lista">
                {datos.entradas.map((entrada) => {
                    const requisito = textoRequisitoHistorial(entrada);
                    return (
                        <li key={entrada.id} className="exv-hist-entrada">
                            <time className="exv-hist-fecha" dateTime={entrada.fecha ?? undefined}>
                                {fechaHoraHistorial(entrada.fecha)}
                            </time>
                            <div className="exv-hist-cuerpo">
                                <p className="exv-hist-texto">
                                    <b>{autorHistorial(entrada)}</b> · {describirEvento(entrada)}
                                </p>
                                {requisito && <p className="exv-hist-requisito">{requisito}</p>}
                            </div>
                            {entrada.soporte && (
                                <button
                                    type="button"
                                    className="exv-mini"
                                    aria-label={`Descargar ${entrada.soporte.nombre}`}
                                    onClick={() => onDescargar(entrada)}
                                >
                                    <FontAwesomeIcon icon={faDownload} /> {entrada.soporte.nombre}
                                </button>
                            )}
                        </li>
                    );
                })}
            </ol>
        );
    }

    return (
        <section id={id} className="exv-hist exv-no-imprimir" aria-labelledby={idTitulo} aria-busy={cargando !== null}>
            <div className="exv-hist-cabeza">
                <h3 id={idTitulo}>Historial del expediente</h3>
                {datos && (
                    <span role="status">
                        {cargando === 1 ? 'Actualizando…' : `${datos.total} ${datos.total === 1 ? 'movimiento' : 'movimientos'}`}
                    </span>
                )}
            </div>
            {cuerpo}
            {datos && error && (
                <div className="exv-hist-pie" role="alert">
                    <span>
                        {error.pagina === 1 ? 'No se pudo poner al día el historial' : 'No se pudo cargar la página siguiente'}: {error.mensaje}
                    </span>
                    {botonReintentar}
                </div>
            )}
            {datos && !error && hayMasHistorial(datos) && (
                <div className="exv-hist-pie">
                    <button type="button" className="exv-btn" onClick={cargarMas} disabled={cargando !== null}>
                        {cargando !== null ? 'Cargando…' : 'Cargar más'}
                    </button>
                </div>
            )}
        </section>
    );
};

export default ExpedienteHistorial;
```

En `resources/css/ExpedienteViabilizacion.css`, reemplazar (1 de 1):

```css
.exv-campo-ayuda { margin: .3rem 0 0; font-size: .76rem; color: var(--exv-tinta-tenue); }
.exv-propio-acciones { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .5rem; }

@media (max-width: 920px) {
    .exv-shell { grid-template-columns: minmax(0, 1fr); gap: 1rem; }
    .exv-config { position: static; max-height: none; }
```

por:

```css
.exv-campo-ayuda { margin: .3rem 0 0; font-size: .76rem; color: var(--exv-tinta-tenue); }
.exv-propio-acciones { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .5rem; }

/* Historial del expediente: se abre desde la barra, sobre el checklist */
.exv-btn-historial[aria-expanded="true"] { border-color: var(--exv-bronce); color: var(--exv-bronce-oscuro); background: var(--exv-bronce-tinte); }
.exv-hist {
    margin-bottom: 1.1rem;
    background: var(--exv-tarjeta);
    border: 1px solid var(--exv-linea);
    border-radius: var(--exv-radio);
}
.exv-hist-cabeza {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    justify-content: space-between;
    gap: .25rem .75rem;
    padding: .75rem 1.1rem;
    border-bottom: 1px solid var(--exv-linea-suave);
}
.exv-hist-cabeza h3 { margin: 0; font-size: .82rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--exv-tinta); }
.exv-hist-cabeza span { font-size: .75rem; color: var(--exv-tinta-tenue); font-variant-numeric: tabular-nums; }
.exv-hist-lista { max-height: 60vh; margin: 0; padding: 0; overflow-y: auto; list-style: none; }
.exv-hist-entrada {
    display: grid;
    grid-template-columns: 9.5rem minmax(0, 1fr) auto;
    gap: .2rem .9rem;
    align-items: start;
    padding: .55rem 1.1rem;
    font-size: .82rem;
    border-top: 1px solid var(--exv-linea-suave);
}
.exv-hist-entrada:first-child { border-top: 0; }
.exv-hist-fecha { padding-top: .05rem; font-size: .76rem; color: var(--exv-tinta-tenue); font-variant-numeric: tabular-nums; }
.exv-hist-texto { margin: 0; line-height: 1.4; color: var(--exv-tinta-suave); overflow-wrap: anywhere; }
.exv-hist-texto b { font-weight: 600; color: var(--exv-tinta); }
.exv-hist-requisito { margin: .15rem 0 0; font-size: .76rem; line-height: 1.35; color: var(--exv-tinta-tenue); overflow-wrap: anywhere; }
.exv-hist-estado { margin: 0; padding: 1rem 1.1rem; font-size: .84rem; color: var(--exv-tinta-tenue); }
.exv-hist-estado p { margin: 0 0 .6rem; color: var(--exv-tinta); }
.exv-hist-pie {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: .5rem .75rem;
    padding: .6rem 1.1rem;
    font-size: .8rem;
    color: var(--exv-tinta);
    border-top: 1px solid var(--exv-linea-suave);
}

@media (max-width: 600px) {
    .exv-hist-cabeza, .exv-hist-estado, .exv-hist-pie { padding-left: .9rem; padding-right: .9rem; }
    .exv-hist-entrada { grid-template-columns: minmax(0, 1fr); padding: .55rem .9rem; }
    .exv-hist-entrada .exv-mini { justify-self: start; }
}

@media (max-width: 920px) {
    .exv-shell { grid-template-columns: minmax(0, 1fr); gap: 1rem; }
    .exv-config { position: static; max-height: none; }
```

En `resources/js/components/ExpedienteHistorialRequisito.jsx`, reemplazar (1 de 2):

```jsx
import React, { useEffect, useState } from 'react';
import { faDownload } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { describirEvento, mensajeError } from '../utils/expedienteChecklist';

const fechaHora = (iso) => (iso ? new Date(iso).toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'short' }) : '');

/** Historial de un requisito. Se vuelve a pedir cada vez que la fila cambia. */
const ExpedienteHistorialRequisito = ({ fila, onCargar, onDescargar }) => {
```

por:

```jsx
import React, { useEffect, useState } from 'react';
import { faDownload } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { autorHistorial, describirEvento, fechaHoraHistorial, mensajeError } from '../utils/expedienteChecklist';

/** Historial de un requisito. Se vuelve a pedir cada vez que la fila cambia. */
const ExpedienteHistorialRequisito = ({ fila, onCargar, onDescargar }) => {
```

En `resources/js/components/ExpedienteHistorialRequisito.jsx`, reemplazar (2 de 2):

```jsx
        <ol className="exv-historial">
            {entradas.map((entrada) => (
                <li key={entrada.id}>
                    <span className="exv-historial-fecha">{fechaHora(entrada.fecha)}</span>
                    <span><b>{entrada.usuario ?? 'Usuario eliminado'}</b> · {describirEvento(entrada)}</span>
                    {entrada.soporte && (
                        <button type="button" className="exv-mini" onClick={() => onDescargar(entrada)}>
                            <FontAwesomeIcon icon={faDownload} /> {entrada.soporte.nombre}
```

por:

```jsx
        <ol className="exv-historial">
            {entradas.map((entrada) => (
                <li key={entrada.id}>
                    <span className="exv-historial-fecha">{fechaHoraHistorial(entrada.fecha)}</span>
                    <span><b>{autorHistorial(entrada)}</b> · {describirEvento(entrada)}</span>
                    {entrada.soporte && (
                        <button type="button" className="exv-mini" onClick={() => onDescargar(entrada)}>
                            <FontAwesomeIcon icon={faDownload} /> {entrada.soporte.nombre}
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (1 de 9):

```jsx
import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import {
    faArrowLeft, faArrowsRotate, faCheck, faFileExcel, faPlus, faPrint, faSliders, faStamp, faTrash,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import ExpedienteChecklist, { AvisosDuplicados } from './ExpedienteChecklist';
import ExpedienteConfiguracion from './ExpedienteConfiguracion';
import useOcupado from './useOcupado';
import {
    ANCHOS_HOJA_CHECKLIST,
```

por:

```jsx
import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import {
    faArrowLeft, faArrowsRotate, faCheck, faClockRotateLeft, faFileExcel, faPlus, faPrint, faSliders, faStamp, faTrash,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import ExpedienteChecklist, { AvisosDuplicados } from './ExpedienteChecklist';
import ExpedienteConfiguracion from './ExpedienteConfiguracion';
import ExpedienteHistorial from './ExpedienteHistorial';
import useOcupado from './useOcupado';
import {
    ANCHOS_HOJA_CHECKLIST,
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (2 de 9):

```jsx

const ROLES_EDICION = ['Administrador', 'Gestor'];
const ESPERA_RECALCULO_MS = 350;

/** Una plantilla con versión nueva: "Obras de agua versión 3 disponible" y, si se puede aplicar, su botón. */
const VersionNueva = ({ plantilla, puedeAplicar, deshabilitada, onAplicar }) => (
```

por:

```jsx

const ROLES_EDICION = ['Administrador', 'Gestor'];
const ESPERA_RECALCULO_MS = 350;
const ID_HISTORIAL = 'exv-historial-expediente';

/** Una plantilla con versión nueva: "Obras de agua versión 3 disponible" y, si se puede aplicar, su botón. */
const VersionNueva = ({ plantilla, puedeAplicar, deshabilitada, onAplicar }) => (
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (3 de 9):

```jsx
    const [recalculando, setRecalculando] = useState(false);
    const [confirmando, setConfirmando] = useState(false);
    const [configAbierta, setConfigAbierta] = useState(false);
    const [errorCarga, setErrorCarga] = useState(null);
    const [responsables, setResponsables] = useState([]);
    // Plantillas que se ofrecen al proyecto (y las que ya tiene el expediente abierto).
```

por:

```jsx
    const [recalculando, setRecalculando] = useState(false);
    const [confirmando, setConfirmando] = useState(false);
    const [configAbierta, setConfigAbierta] = useState(false);
    const [historialAbierto, setHistorialAbierto] = useState(false);
    // Sube con cada cambio del expediente mostrado que el servidor ya registró: el historial
    // abierto se vuelve a pedir desde la página 1 con cada uno. Así no se pide en cada render,
    // ni por un cambio optimista que el servidor todavía no confirmó.
    const [revision, setRevision] = useState(0);
    const nuevaRevision = useCallback(() => setRevision((r) => r + 1), []);
    const [errorCarga, setErrorCarga] = useState(null);
    const [responsables, setResponsables] = useState([]);
    // Plantillas que se ofrecen al proyecto (y las que ya tiene el expediente abierto).
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (4 de 9):

```jsx
        peticionAvisos.current += 1;
        peticionExpediente.current += 1;
        setExpediente(data);
    }, []);

    // Como cargarPlantillas: solo vale la última petición, y solo si su expediente sigue abierto y
    // nada puso el expediente completo mientras llegaba. Una respuesta o un error que llegan tarde
```

por:

```jsx
        peticionAvisos.current += 1;
        peticionExpediente.current += 1;
        setExpediente(data);
        nuevaRevision();
    }, [nuevaRevision]);

    // Como cargarPlantillas: solo vale la última petición, y solo si su expediente sigue abierto y
    // nada puso el expediente completo mientras llegaba. Una respuesta o un error que llegan tarde
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (5 de 9):

```jsx
        // Un cambio que esperaba el temporizador ya no se manda; si hay un envío en curso, él lo apaga al terminar.
        if (!envio.current.enCurso) setRecalculando(false);
        setConfigAbierta(false);

        if (!idActual) {
            ponerExpediente(null);
```

por:

```jsx
        // Un cambio que esperaba el temporizador ya no se manda; si hay un envío en curso, él lo apaga al terminar.
        if (!envio.current.enCurso) setRecalculando(false);
        setConfigAbierta(false);
        // El historial es del expediente: se cierra con él, como la configuración.
        setHistorialAbierto(false);

        if (!idActual) {
            ponerExpediente(null);
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (6 de 9):

```jsx

    // Si la fila ya no está (se abrió otro expediente mientras llegaba la respuesta), nada cambia:
    // ni la fila ni el progreso, que es del expediente de la fila.
    const reemplazarFila = (fila, progreso) => setExpediente((prev) => (
        prev?.requisitos.some((f) => f.id === fila.id)
            ? { ...prev, progreso: progreso ?? prev.progreso, requisitos: prev.requisitos.map((f) => (f.id === fila.id ? fila : f)) }
            : prev
    ));

    // Un propio nuevo va al final: el backend ordena los propios después de toda plantilla.
    const agregarFila = (expedienteId, fila, progreso) => setExpediente((prev) => (
        prev?.id === expedienteId
            ? { ...prev, progreso: progreso ?? prev.progreso, requisitos: [...prev.requisitos, fila] }
            : prev
    ));

    const quitarFila = (fila, progreso) => setExpediente((prev) => (
        prev?.requisitos.some((f) => f.id === fila.id)
            ? { ...prev, progreso: progreso ?? prev.progreso, requisitos: prev.requisitos.filter((f) => f.id !== fila.id) }
            : prev
    ));

    const urlFila = (fila) => `/expedientes/${expediente.id}/requisitos/${fila.id}`;

    const marcar = async (fila, cumplido) => {
        reemplazarFila({ ...fila, cumplido, estado: cumplido ? 'cumplido' : 'pendiente' });
        try {
            const { data } = await axios.patch(urlFila(fila), { cumplido });
            reemplazarFila(data.requisito, data.progreso);
        } catch (error) {
            reemplazarFila(fila);
            avisarError('No se pudo actualizar el requisito', error);
        }
    };
```

por:

```jsx

    // Si la fila ya no está (se abrió otro expediente mientras llegaba la respuesta), nada cambia:
    // ni la fila ni el progreso, que es del expediente de la fila.
    // pintarFila solo la muestra: es el cambio optimista, antes de la respuesta, o su reversión.
    const pintarFila = (fila, progreso) => setExpediente((prev) => (
        prev?.requisitos.some((f) => f.id === fila.id)
            ? { ...prev, progreso: progreso ?? prev.progreso, requisitos: prev.requisitos.map((f) => (f.id === fila.id ? fila : f)) }
            : prev
    ));

    // reemplazarFila, agregarFila y quitarFila ponen lo que respondió el servidor: el cambio ya
    // quedó en el historial.
    const reemplazarFila = (fila, progreso) => {
        pintarFila(fila, progreso);
        nuevaRevision();
    };

    // Un propio nuevo va al final: el backend ordena los propios después de toda plantilla.
    const agregarFila = (expedienteId, fila, progreso) => {
        setExpediente((prev) => (
            prev?.id === expedienteId
                ? { ...prev, progreso: progreso ?? prev.progreso, requisitos: [...prev.requisitos, fila] }
                : prev
        ));
        nuevaRevision();
    };

    const quitarFila = (fila, progreso) => {
        setExpediente((prev) => (
            prev?.requisitos.some((f) => f.id === fila.id)
                ? { ...prev, progreso: progreso ?? prev.progreso, requisitos: prev.requisitos.filter((f) => f.id !== fila.id) }
                : prev
        ));
        nuevaRevision();
    };

    const urlFila = (fila) => `/expedientes/${expediente.id}/requisitos/${fila.id}`;

    const marcar = async (fila, cumplido) => {
        pintarFila({ ...fila, cumplido, estado: cumplido ? 'cumplido' : 'pendiente' });
        try {
            const { data } = await axios.patch(urlFila(fila), { cumplido });
            reemplazarFila(data.requisito, data.progreso);
        } catch (error) {
            pintarFila(fila);
            avisarError('No se pudo actualizar el requisito', error);
        }
    };
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (7 de 9):

```jsx
    });

    const asignarResponsable = async (fila, responsableId) => {
        reemplazarFila({ ...fila, responsable: responsables.find((r) => r.id === responsableId) ?? null });
        try {
            const { data } = await axios.patch(urlFila(fila), { responsable_id: responsableId });
            reemplazarFila(data.requisito, data.progreso);
        } catch (error) {
            reemplazarFila(fila);
            avisarError('No se pudo asignar el responsable', error);
        }
    };
```

por:

```jsx
    });

    const asignarResponsable = async (fila, responsableId) => {
        pintarFila({ ...fila, responsable: responsables.find((r) => r.id === responsableId) ?? null });
        try {
            const { data } = await axios.patch(urlFila(fila), { responsable_id: responsableId });
            reemplazarFila(data.requisito, data.progreso);
        } catch (error) {
            pintarFila(fila);
            avisarError('No se pudo asignar el responsable', error);
        }
    };
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (8 de 9):

```jsx
                                                <FontAwesomeIcon icon={faSliders} />{' '}
                                                {configAbierta ? 'Ocultar configuración' : editable ? 'Editar configuración' : 'Ver configuración'}
                                            </button>
                                            <button type="button" className="exv-btn" onClick={exportarExcel}>
                                                <FontAwesomeIcon icon={faFileExcel} /> Excel
                                            </button>
```

por:

```jsx
                                                <FontAwesomeIcon icon={faSliders} />{' '}
                                                {configAbierta ? 'Ocultar configuración' : editable ? 'Editar configuración' : 'Ver configuración'}
                                            </button>
                                            <button
                                                type="button"
                                                className="exv-btn exv-btn-historial"
                                                aria-expanded={historialAbierto}
                                                aria-controls={historialAbierto ? ID_HISTORIAL : undefined}
                                                onClick={() => setHistorialAbierto((abierto) => !abierto)}
                                            >
                                                <FontAwesomeIcon icon={faClockRotateLeft} /> Historial
                                            </button>
                                            <button type="button" className="exv-btn" onClick={exportarExcel}>
                                                <FontAwesomeIcon icon={faFileExcel} /> Excel
                                            </button>
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (9 de 9):

```jsx
                                        </div>
                                    )}

                                    <ExpedienteChecklist
                                        expediente={expediente}
                                        editable={editable}
```

por:

```jsx
                                        </div>
                                    )}

                                    {historialAbierto && (
                                        <ExpedienteHistorial
                                            key={expediente.id}
                                            id={ID_HISTORIAL}
                                            expedienteId={expediente.id}
                                            revision={revision}
                                            onDescargar={descargarSoporteHistorial}
                                        />
                                    )}

                                    <ExpedienteChecklist
                                        expediente={expediente}
                                        editable={editable}
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (1 de 3):

```js
    requisito_propio_agregado: 'Agregó el requisito propio',
    requisito_propio_editado: 'Editó el requisito propio',
    requisito_propio_eliminado: 'Eliminó el requisito propio',
};

const CAMPOS_PROPIO = { titulo: 'título', descripcion: 'descripción', categoria: 'categoría', caracter: 'carácter' };
```

por:

```js
    requisito_propio_agregado: 'Agregó el requisito propio',
    requisito_propio_editado: 'Editó el requisito propio',
    requisito_propio_eliminado: 'Eliminó el requisito propio',
    expediente_creado: 'Creó el expediente',
    configuracion_confirmada: 'Confirmó la configuración',
    configuracion_cambiada: 'Cambió la configuración',
    expediente_radicado: 'Radicó el expediente',
    version_aplicada: 'Aplicó una versión nueva de una plantilla',
};

const CAMPOS_PROPIO = { titulo: 'título', descripcion: 'descripción', categoria: 'categoría', caracter: 'carácter' };
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (2 de 3):

```js
/** "título", "título y carácter", "título, categoría y carácter". */
const enumerar = (items) => (items.length <= 1 ? items.join('') : `${items.slice(0, -1).join(', ')} y ${items[items.length - 1]}`);

/** Texto de una entrada del historial de un requisito. */
export const describirEvento = (entrada) => {
    const base = EVENTOS[entrada.evento] ?? entrada.evento;
    const d = entrada.despues ?? {};
```

por:

```js
/** "título", "título y carácter", "título, categoría y carácter". */
const enumerar = (items) => (items.length <= 1 ? items.join('') : `${items.slice(0, -1).join(', ')} y ${items[items.length - 1]}`);

const mismosIds = (a, b) => {
    const x = new Set(a ?? []);
    const y = new Set(b ?? []);
    return x.size === y.size && [...x].every((id) => y.has(id));
};

/** Selecciones de la configuración que registra el backend (configuracionRegistrable), con su nombre. */
const SELECCIONES_CONFIGURACION = [
    ['sector_ids', 'sectores'],
    ['tipo_ids', 'tipos de proyecto'],
    ['componente_ids', 'componentes'],
    ['tipologia_ids', 'tipologías'],
];

/**
 * Qué cambió entre dos configuraciones registradas: "plantillas", "fase SGR", "sectores"…
 * La fase solo se registra con una plantilla sgr: si aparece o desaparece, lo que cambió
 * son las plantillas, y no se nombra aparte.
 */
const cambiosConfiguracion = (antes, despues) => {
    const plantillaIds = (c) => (c.plantillas ?? []).map((p) => p.plantilla_id);
    const principal = (c) => (c.plantillas ?? []).find((p) => p.es_principal)?.plantilla_id ?? null;
    const cambios = [];
    if (!mismosIds(plantillaIds(antes), plantillaIds(despues))) cambios.push('plantillas');
    else if (principal(antes) !== principal(despues)) cambios.push('plantilla principal');
    if ('fase_sgr' in antes && 'fase_sgr' in despues && antes.fase_sgr !== despues.fase_sgr) cambios.push('fase SGR');
    SELECCIONES_CONFIGURACION.forEach(([campo, nombre]) => {
        if (!mismosIds(antes[campo], despues[campo])) cambios.push(nombre);
    });
    return cambios;
};

/** Texto de una entrada del historial, de un requisito o del propio expediente. */
export const describirEvento = (entrada) => {
    const base = EVENTOS[entrada.evento] ?? entrada.evento;
    const d = entrada.despues ?? {};
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (3 de 3):

```js
        }
        case 'requisito_propio_eliminado':
            return a.titulo ? `${base}: ${a.titulo}` : base;
        default:
            return base;
    }
};

/** Agrupa consecutivamente: el backend ya entrega las filas en el orden del catálogo. */
const agruparConsecutivos = (items, claveDe, crear) => {
    const grupos = [];
```

por:

```js
        }
        case 'requisito_propio_eliminado':
            return a.titulo ? `${base}: ${a.titulo}` : base;
        case 'configuracion_cambiada': {
            const cambios = cambiosConfiguracion(a, d);
            return cambios.length ? `${base}: ${enumerar(cambios)}` : base;
        }
        case 'version_aplicada':
            if (!d.plantilla || !d.version) return base;
            return `Aplicó la versión ${d.version} de ${d.plantilla}${a.version ? ` (antes ${a.version})` : ''}`;
        default:
            return base;
    }
};

const LARGO_REQUISITO_HISTORIAL = 90;

const truncar = (texto, largo) => (texto.length > largo ? `${texto.slice(0, largo - 1).trimEnd()}…` : texto);

/**
 * Requisito de una entrada del historial del expediente, en corto: "A.1.2 · Carta de
 * presentación…" (código y texto truncado) o el título de un propio. De una fila que ya no
 * existe solo se sabe algo si es un propio eliminado, que guarda su título en `antes`.
 * Null en las entradas del propio expediente.
 */
export const textoRequisitoHistorial = (entrada) => {
    if (entrada.entidad !== 'requisito') return null;
    const r = entrada.requisito;
    if (!r) return entrada.antes?.titulo ?? 'Requisito que ya no está en el checklist';
    if (r.titulo) return r.titulo;
    const texto = r.texto ? truncar(r.texto, LARGO_REQUISITO_HISTORIAL) : null;
    return [r.codigo, texto].filter(Boolean).join(' · ') || 'Requisito sin texto';
};

/** Fecha y hora de una entrada del historial, como la muestra la pantalla. */
export const fechaHoraHistorial = (iso) => (
    iso ? new Date(iso).toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'short' }) : ''
);

/**
 * Quién registró una entrada del historial: su nombre; "Sistema" si no la registró nadie (un
 * comando, como expedientes:crear-faltantes), o "Usuario eliminado" si su usuario ya no existe.
 */
export const autorHistorial = (entrada) => {
    if (entrada?.usuario) return entrada.usuario;
    return entrada?.sistema ? 'Sistema' : 'Usuario eliminado';
};

/**
 * Entradas ya mostradas más las de la página siguiente. Si entretanto se registraron
 * movimientos nuevos, las páginas se corren y la siguiente repite el final de la anterior:
 * lo repetido no se vuelve a poner.
 */
export const unirPaginaHistorial = (entradas, nuevas) => {
    const vistas = new Set(entradas.map((e) => e.id));
    return [...entradas, ...nuevas.filter((e) => !vistas.has(e.id))];
};

/** Si quedan páginas después de la última que llegó (GET /expedientes/{id}/historial). */
export const hayMasHistorial = (respuesta) =>
    Boolean(respuesta) && respuesta.pagina * respuesta.por_pagina < respuesta.total;

/** Agrupa consecutivamente: el backend ya entrega las filas en el orden del catálogo. */
const agruparConsecutivos = (items, claveDe, crear) => {
    const grupos = [];
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/expedienteChecklist.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 630 passed.

Run: `npm test`
Expected: 324 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/js/components/ExpedienteHistorial.jsx resources/css/ExpedienteViabilizacion.css resources/js/components/ExpedienteHistorialRequisito.jsx resources/js/components/ExpedienteViabilizacion.jsx resources/js/utils/expedienteChecklist.js resources/js/utils/expedienteChecklist.test.js
git commit -m "feat: expediente history on screen"
```

---

### Tarea 7: Tablero: filtros por fuente y responsable

El resumen de `GET /expedientes` trae los responsables con pendientes, y el panel filtra por fuente y responsable.

**Files:**
- Modify: `app/Models/ProyectoRequisito.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `resources/css/ExpedientesSgrPanel.css`
- Modify: `resources/js/components/Dashboard.jsx`
- Modify: `resources/js/components/ExpedientesSgrPanel.jsx`
- Modify: `resources/js/utils/expedientesResumen.js`
- Test: `resources/js/utils/expedientesResumen.test.js`
- Test: `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`
- Test: `tests/Feature/Viabilizacion/SeguimientoRequisitoApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/expedientesResumen.test.js`, reemplazar (1 de 2):

```js
import { describe, it, expect } from 'vitest';
import {
    ESTADO,
    avanceRequisitos,
    construirFilas,
    contarPorEstado,
    esFormulacion,
    filtrarFilas,
    rutaExpediente,
    textoExpediente,
    textoAvanceProyecto,
```

por:

```js
import { describe, it, expect } from 'vitest';
import {
    ESTADO,
    FUENTE_POR_DEFINIR,
    TODAS_LAS_FUENTES,
    TODOS_LOS_RESPONSABLES,
    avanceRequisitos,
    construirFilas,
    contarPorEstado,
    esFormulacion,
    filtrarFilas,
    opcionesFuentes,
    opcionesResponsables,
    pendientesDe,
    rutaExpediente,
    textoExpediente,
    textoAvanceProyecto,
```

En `resources/js/utils/expedientesResumen.test.js`, reemplazar (2 de 2):

```js
        expect(fila).toMatchObject({ plantillas: [], tieneSgr: false });
    });
});
```

por:

```js
        expect(fila).toMatchObject({ plantillas: [], tieneSgr: false });
    });
});

describe('filtros por fuente y responsable', () => {
    const ana = { id: 7, nombre: 'Ana', pendientes: 2 };
    const beto = { id: 3, nombre: 'Beto', pendientes: 1 };
    const conFuentes = (p, fuenteIds, porDefinir = false) => ({ ...p, fuente_ids: fuenteIds, financiacion_por_definir: porDefinir });
    const filas = construirFilas(
        [
            conFuentes(proyecto(1, 'Acueducto'), [1]),
            conFuentes(proyecto(2, 'Vía'), [1, 2]),
            conFuentes(proyecto(3, 'Escuela'), [], true),
            conFuentes(proyecto(4, 'Parque'), [4]),
        ],
        [
            resumen(1, 'borrador', 8, 3, { responsables: [ana, beto] }),
            resumen(2, 'borrador', 4, 1, { responsables: [{ ...ana, pendientes: 3 }] }),
            resumen(3, 'radicado', 8, 8),
        ],
    );
    const ids = (lista) => lista.map((f) => f.proyectoId).sort();
    const catalogo = [
        { id: 2, nombre: 'Propios', activo: true },
        { id: 1, nombre: 'SGR', activo: true },
        { id: 4, nombre: 'Crédito', activo: false },
        { id: 5, nombre: 'Sin usar', activo: true },
    ];

    it('la fila lleva sus fuentes, si están por definir y los responsables del servicio', () => {
        const porId = new Map(filas.map((f) => [f.proyectoId, f]));

        expect(porId.get(2)).toMatchObject({ fuenteIds: [1, 2], financiacionPorDefinir: false, responsables: [{ ...ana, pendientes: 3 }] });
        expect(porId.get(3)).toMatchObject({ fuenteIds: [], financiacionPorDefinir: true, responsables: [] });
        expect(porId.get(4)).toMatchObject({ fuenteIds: [4], responsables: [] });
    });

    it('un proyecto o un resumen sin esos datos no rompe la fila', () => {
        const [fila] = construirFilas([proyecto(1, 'A')], [resumen(1, 'borrador', 8, 3)]);

        expect(fila).toMatchObject({ fuenteIds: [], financiacionPorDefinir: false, responsables: [] });
    });

    it('sin configurar no muestra responsables, como tampoco muestra avance', () => {
        const [fila] = construirFilas([proyecto(1, 'A')], [resumen(1, 'borrador', 8, 0, { configurado: false, responsables: [ana] })]);

        expect(fila.responsables).toEqual([]);
    });

    it('filtra por fuente, por "por definir" o deja todas', () => {
        expect(ids(filtrarFilas(filas, { fuente: TODAS_LAS_FUENTES }))).toEqual([1, 2, 3, 4]);
        expect(ids(filtrarFilas(filas, { fuente: '1' }))).toEqual([1, 2]);
        expect(ids(filtrarFilas(filas, { fuente: 2 }))).toEqual([2]);
        expect(ids(filtrarFilas(filas, { fuente: FUENTE_POR_DEFINIR }))).toEqual([3]);
    });

    it('filtra por responsable: solo los proyectos donde tiene algo pendiente', () => {
        expect(ids(filtrarFilas(filas, { responsable: TODOS_LOS_RESPONSABLES }))).toEqual([1, 2, 3, 4]);
        expect(ids(filtrarFilas(filas, { responsable: '7' }))).toEqual([1, 2]);
        expect(ids(filtrarFilas(filas, { responsable: '3' }))).toEqual([1]);
        expect(filtrarFilas(filas, { responsable: '99' })).toEqual([]);
    });

    it('combina fuente, responsable, estado y texto', () => {
        expect(ids(filtrarFilas(filas, { fuente: '2', responsable: '7' }))).toEqual([2]);
        expect(filtrarFilas(filas, { fuente: '2', responsable: '3' })).toEqual([]);
        expect(ids(filtrarFilas(filas, { estado: ESTADO.BORRADOR, fuente: '1', texto: 'via' }))).toEqual([2]);
    });

    it('con un responsable elegido, los pendientes son los suyos', () => {
        const [acueducto] = filas.filter((f) => f.proyectoId === 1);

        expect(pendientesDe(acueducto)).toBe(5);
        expect(pendientesDe(acueducto, TODOS_LOS_RESPONSABLES)).toBe(5);
        expect(pendientesDe(acueducto, '7')).toBe(2);
        expect(pendientesDe(acueducto, 3)).toBe(1);
        expect(pendientesDe(acueducto, '99')).toBe(0);
    });

    it('las tarjetas cuentan las filas filtradas y los pendientes del responsable', () => {
        const deAna = filtrarFilas(filas, { responsable: '7' });

        expect(contarPorEstado(deAna, '7')).toMatchObject({ todos: 2, [ESTADO.BORRADOR]: 2, [ESTADO.RADICADO]: 0, pendientesEnBorrador: 5 });
        expect(contarPorEstado(deAna)).toMatchObject({ pendientesEnBorrador: 8 });
    });

    it('ofrece las fuentes del catálogo que tiene algún proyecto, en su orden, y "Por definir"', () => {
        expect(opcionesFuentes(catalogo, filas)).toEqual([
            { valor: '2', etiqueta: 'Propios' },
            { valor: '1', etiqueta: 'SGR' },
            { valor: '4', etiqueta: 'Crédito' },
            { valor: FUENTE_POR_DEFINIR, etiqueta: 'Por definir' },
        ]);
    });

    it('sin proyectos por definir no ofrece "Por definir"; sin catálogo no hay opciones', () => {
        const sinPorDefinir = filas.filter((f) => !f.financiacionPorDefinir);

        expect(opcionesFuentes(catalogo, sinPorDefinir).map((o) => o.valor)).toEqual(['2', '1', '4']);
        expect(opcionesFuentes(null, filas)).toEqual([]);
        expect(opcionesFuentes([], filas)).toEqual([]);
        expect(opcionesFuentes(catalogo, [])).toEqual([]);
    });

    it('ofrece los responsables de los resúmenes sin repetir y por nombre', () => {
        const otras = construirFilas(
            [proyecto(1, 'A'), proyecto(2, 'B')],
            [
                resumen(1, 'borrador', 8, 3, { responsables: [beto, { id: 9, nombre: 'Álvaro', pendientes: 1 }] }),
                resumen(2, 'borrador', 8, 3, { responsables: [beto, ana] }),
            ],
        );

        expect(opcionesResponsables(otras)).toEqual([
            { valor: '9', etiqueta: 'Álvaro' },
            { valor: '7', etiqueta: 'Ana' },
            { valor: '3', etiqueta: 'Beto' },
        ]);
        expect(opcionesResponsables([])).toEqual([]);
    });
});
```

En `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`, reemplazar (1 de 2):

```php
        $this->assertSame(['Vías', 'SGR', 'Obras de agua'], $resumen[$proyecto->id]['plantillas']);
    }

    public function test_el_resumen_hace_tres_consultas_sin_importar_cuantos_proyectos_haya(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $servicio = app(ExpedienteService::class);
```

por:

```php
        $this->assertSame(['Vías', 'SGR', 'Obras de agua'], $resumen[$proyecto->id]['plantillas']);
    }

    public function test_el_resumen_hace_cuatro_consultas_sin_importar_cuantos_proyectos_haya(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $servicio = app(ExpedienteService::class);
```

En `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`, reemplazar (2 de 2):

```php
        };

        $this->crear($this->proyecto(), ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])->assertCreated();
        $this->assertSame(3, $contar());

        foreach (range(1, 3) as $_) {
            $this->crear($this->proyecto(), ['fase_sgr' => 3])->assertCreated();
        }
        $this->assertSame(3, $contar());
    }
}
```

por:

```php
        };

        $this->crear($this->proyecto(), ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])->assertCreated();
        // Cantidades, plantillas, responsables y expedientes con su avance.
        $this->assertSame(4, $contar());

        foreach (range(1, 3) as $_) {
            $this->crear($this->proyecto(), ['fase_sgr' => 3])->assertCreated();
        }
        $this->assertSame(4, $contar());
    }
}
```

En `tests/Feature/Viabilizacion/SeguimientoRequisitoApiTest.php`, reemplazar (1 de 1):

```php
        $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$this->expediente->id}")
            ->assertOk()->assertJsonPath('retenidos', 1);
    }
}
```

por:

```php
        $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$this->expediente->id}")
            ->assertOk()->assertJsonPath('retenidos', 1);
    }

    // ------------------------------------------------- responsables en el tablero

    private function responsable(string $nombre): int
    {
        return DB::table('responsable')->insertGetId(['nombre' => $nombre, 'activo' => true]);
    }

    private function resumenDe(ProyectoExpediente $expediente): array
    {
        return collect($this->como(Rol::Consulta)->getJson('/GestPro/expedientes')->assertOk()->json())
            ->firstWhere('expediente_id', $expediente->id);
    }

    public function test_el_tablero_cuenta_lo_que_falta_a_cada_responsable(): void
    {
        $beto = $this->responsable('Beto');
        $ana = $this->responsable('Ana');
        $carla = $this->responsable('Carla');
        [$a, $b, $c, $d, $e, $f, $g, $h] = $this->filas();
        $a->update(['responsable_id' => $ana]);
        $b->update(['responsable_id' => $carla, 'cumplido' => true]);
        $c->update(['responsable_id' => $ana, 'estado_aplicabilidad' => ProyectoRequisito::NO_APLICA]);
        $d->update(['responsable_id' => $ana, 'aplica' => false]);
        $e->update(['responsable_id' => $ana, 'caracter' => 'opcional']);
        $f->update(['responsable_id' => $ana, 'estado_aplicabilidad' => ProyectoRequisito::NO_APLICA_PENDIENTE, 'cumplido' => true]);
        $g->update(['responsable_id' => $ana, 'estado_aplicabilidad' => ProyectoRequisito::POR_DEFINIR]);
        $h->update(['responsable_id' => $beto]);

        $resumen = $this->resumenDe($this->expediente);

        // Ana: la pendiente, el "no aplica" por aprobar y el "por definir". Carla solo tiene cumplidas.
        $this->assertSame([
            ['id' => $ana, 'nombre' => 'Ana', 'pendientes' => 3],
            ['id' => $beto, 'nombre' => 'Beto', 'pendientes' => 1],
        ], $resumen['responsables']);
        // Con todas las que cuentan asignadas, lo de los responsables es lo que falta en el avance.
        $this->assertSame(
            $resumen['progreso']['total'] - $resumen['progreso']['cumplidos'],
            array_sum(array_column($resumen['responsables'], 'pendientes'))
        );
    }

    public function test_sin_responsables_la_lista_del_tablero_esta_vacia(): void
    {
        $this->assertSame([], $this->resumenDe($this->expediente)['responsables']);
    }

    public function test_cada_expediente_del_tablero_trae_solo_sus_responsables(): void
    {
        $ana = $this->responsable('Ana');
        $servicio = app(ExpedienteService::class);
        $otro = $servicio->crear($this->proyectoSgr(['nombre' => 'Vía']), ['fase_sgr' => 3], null);
        $servicio->confirmarConfiguracion($otro, ['fase_sgr' => 3]);
        $this->filas()[0]->update(['responsable_id' => $ana]);

        $this->assertSame([['id' => $ana, 'nombre' => 'Ana', 'pendientes' => 1]], $this->resumenDe($this->expediente)['responsables']);
        $this->assertSame([], $this->resumenDe($otro)['responsables']);
    }

    public function test_los_responsables_no_suman_consultas_al_tablero(): void
    {
        $servicio = app(ExpedienteService::class);
        $asignar = function (ProyectoExpediente $expediente): void {
            $responsable = $this->responsable("Responsable {$expediente->id}");
            $expediente->requisitos()->where('aplica', true)->limit(2)->get()
                ->each(fn (ProyectoRequisito $fila) => $fila->update(['responsable_id' => $responsable]));
        };
        $medir = function (): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson('/GestPro/expedientes')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $asignar($this->expediente);
        $conUno = $medir();

        foreach (['Dos', 'Tres', 'Cuatro'] as $nombre) {
            $expediente = $servicio->crear($this->proyectoSgr(['nombre' => $nombre]), ['fase_sgr' => 3], null);
            $servicio->confirmarConfiguracion($expediente, ['fase_sgr' => 3]);
            $asignar($expediente);
        }

        $this->assertSame($conUno, $medir());
        $this->assertCount(4, collect($this->como(Rol::Consulta)->getJson('/GestPro/expedientes')->json())
            ->filter(fn (array $r) => count($r['responsables']) === 1 && $r['responsables'][0]['pendientes'] === 2));
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php tests/Feature/Viabilizacion/SeguimientoRequisitoApiTest.php`
Expected: FALLAN 5 y pasan 43: el resumen no trae `responsables` y todavía hace 3 consultas.

Run: `npx vitest run resources/js/utils/expedientesResumen.test.js`
Expected: FALLAN 11 y pasan 25: las filas no llevan fuentes ni responsables y no existen los filtros nuevos.

- [ ] **Paso 3: Implementar**

En `app/Models/ProyectoRequisito.php`, reemplazar (1 de 1):

```php
        return $query->cuenta()->where('estado_aplicabilidad', self::APLICA)->where('cumplido', true);
    }

    /**
     * Filas con trabajo hecho: cumplidas, con observación, soporte o responsable, o con un
     * "no aplica" marcado. Es lo que conserva una fila retirada y la única definición: la
```

por:

```php
        return $query->cuenta()->where('estado_aplicabilidad', self::APLICA)->where('cumplido', true);
    }

    /**
     * Entre las que cuentan, las que faltan: el complemento de cumplidaQueCuenta, así que el
     * total menos los cumplidos son estas. Un "no aplica" por aprobar o un "por definir" faltan.
     */
    public function scopePendienteQueCuenta(Builder $query): Builder
    {
        return $query->cuenta()->where(fn (Builder $q) => $q->where('estado_aplicabilidad', '!=', self::APLICA)
            ->orWhere('cumplido', false));
    }

    /**
     * Filas con trabajo hecho: cumplidas, con observación, soporte o responsable, o con un
     * "no aplica" marcado. Es lo que conserva una fila retirada y la única definición: la
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 3):

```php
    }

    /**
     * Expediente más reciente de cada proyecto que tiene alguno, con su avance y sus
     * plantillas. Son tres consultas sin importar cuántos proyectos haya: cantidades,
     * expedientes con su avance y plantillas.
     */
    public function resumenPorProyecto(): array
    {
```

por:

```php
    }

    /**
     * Expediente más reciente de cada proyecto que tiene alguno, con su avance, sus
     * plantillas y los responsables con requisitos pendientes. Son cuatro consultas sin
     * importar cuántos proyectos haya: cantidades, plantillas, responsables y expedientes
     * con su avance.
     */
    public function resumenPorProyecto(): array
    {
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 3):

```php
            ->get(['expediente_plantillas.expediente_id', 'plantillas_checklist.nombre', 'plantillas_checklist.motor'])
            ->groupBy('expediente_id');

        return ProyectoExpediente::query()
            ->whereIn('id', $cantidades->keys())
            ->withCount([
```

por:

```php
            ->get(['expediente_plantillas.expediente_id', 'plantillas_checklist.nombre', 'plantillas_checklist.motor'])
            ->groupBy('expediente_id');

        // Lo que falta a cada responsable, con la misma definición del avance: suma lo que el
        // expediente tiene pendiente con responsable asignado.
        $responsables = ProyectoRequisito::query()
            ->pendienteQueCuenta()
            ->join('responsable', 'responsable.id', '=', 'proyectos_requisitos.responsable_id')
            ->whereIn('proyectos_requisitos.expediente_id', $cantidades->keys())
            ->groupBy('proyectos_requisitos.expediente_id', 'responsable.id', 'responsable.nombre')
            ->orderBy('responsable.nombre')
            ->orderBy('responsable.id')
            ->toBase()
            ->get(['proyectos_requisitos.expediente_id', 'responsable.id', 'responsable.nombre', DB::raw('COUNT(*) AS pendientes')])
            ->groupBy('expediente_id');

        return ProyectoExpediente::query()
            ->whereIn('id', $cantidades->keys())
            ->withCount([
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (3 de 3):

```php
                'fecha_radicado' => $e->fecha_radicado?->toIso8601String(),
                'actualizado' => $e->updated_at?->toIso8601String(),
                'progreso' => $this->progreso((int) $e->requisitos_aplican, (int) $e->requisitos_cumplidos),
            ])
            ->all();
    }
```

por:

```php
                'fecha_radicado' => $e->fecha_radicado?->toIso8601String(),
                'actualizado' => $e->updated_at?->toIso8601String(),
                'progreso' => $this->progreso((int) $e->requisitos_aplican, (int) $e->requisitos_cumplidos),
                'responsables' => $responsables->get($e->id, collect())->map(fn (object $r) => [
                    'id' => (int) $r->id,
                    'nombre' => (string) $r->nombre,
                    'pendientes' => (int) $r->pendientes,
                ])->values()->all(),
            ])
            ->all();
    }
```

En `resources/css/ExpedientesSgrPanel.css`, reemplazar (1 de 2):

```css
.exs-tarjeta-valor { font-family: var(--gp-fuente-datos); font-size: 1.7rem; font-weight: 700; line-height: 1.15; letter-spacing: -.03em; }

.exs-sin-resultados { margin: 1rem 0; font-size: .85rem; text-align: center; color: var(--gp-texto-3); }
.exs-tabla-marco { min-width: 0; padding: 1rem 1.1rem; background: var(--gp-superficie); border: 1px solid var(--gp-linea); border-radius: var(--gp-radio); }
.exs-tabla-encabezado { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .6rem; margin-bottom: .6rem; }
.exs-tabla-encabezado h3 { font-size: 1.02rem; }
.exs-buscar { width: min(16rem, 100%); padding: .42rem .65rem; font-size: .86rem; background: var(--gp-fondo-2); border: 1px solid var(--gp-linea-fuerte); border-radius: 4px; }
.exs-buscar::placeholder { color: var(--gp-texto-3); }

.exs-proyecto { font-weight: 600; line-height: 1.3; }
.exs-col-avance { width: 12rem; }
```

por:

```css
.exs-tarjeta-valor { font-family: var(--gp-fuente-datos); font-size: 1.7rem; font-weight: 700; line-height: 1.15; letter-spacing: -.03em; }

.exs-sin-resultados { margin: 1rem 0; font-size: .85rem; text-align: center; color: var(--gp-texto-3); }
div.exs-sin-resultados { display: grid; justify-items: center; gap: .6rem; }
.exs-tabla-marco { min-width: 0; padding: 1rem 1.1rem; background: var(--gp-superficie); border: 1px solid var(--gp-linea); border-radius: var(--gp-radio); }
.exs-tabla-encabezado { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .6rem; margin-bottom: .6rem; }
.exs-tabla-encabezado h3 { font-size: 1.02rem; }
.exs-buscar { width: min(16rem, 100%); padding: .42rem .65rem; font-size: .86rem; background: var(--gp-fondo-2); border: 1px solid var(--gp-linea-fuerte); border-radius: 4px; }
.exs-buscar::placeholder { color: var(--gp-texto-3); }
.exs-filtros { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; min-width: 0; }
.exs-filtro { max-width: 14rem; padding: .42rem .5rem; font-size: .86rem; background: var(--gp-fondo-2); border: 1px solid var(--gp-linea-fuerte); border-radius: 4px; }

.exs-proyecto { font-weight: 600; line-height: 1.3; }
.exs-col-avance { width: 12rem; }
```

En `resources/css/ExpedientesSgrPanel.css`, reemplazar (2 de 2):

```css
}

@media (max-width: 720px) {
    .exs-resumen { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
    .exs-resumen .exs-tarjeta:first-child { grid-column: 1 / -1; }
    .exs-col-avance { width: auto; }
```

por:

```css
}

@media (max-width: 720px) {
    .exs-filtros, .exs-filtro, .exs-buscar { width: 100%; max-width: none; }
    .exs-resumen { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
    .exs-resumen .exs-tarjeta:first-child { grid-column: 1 / -1; }
    .exs-col-avance { width: auto; }
```

En `resources/js/components/Dashboard.jsx`, reemplazar (1 de 4):

```jsx
    const [proyectos, setProyectos] = useState([]);
    const [resumenExpedientes, setResumenExpedientes] = useState(null);
    const [errorExpedientes, setErrorExpedientes] = useState(null);
    const [errorCarga, setErrorCarga] = useState(null);
    const [cargando, setCargando] = useState(true);
    const [ultimaCarga, setUltimaCarga] = useState(null);
```

por:

```jsx
    const [proyectos, setProyectos] = useState([]);
    const [resumenExpedientes, setResumenExpedientes] = useState(null);
    const [errorExpedientes, setErrorExpedientes] = useState(null);
    const [fuentes, setFuentes] = useState(null);
    const [errorCarga, setErrorCarga] = useState(null);
    const [cargando, setCargando] = useState(true);
    const [ultimaCarga, setUltimaCarga] = useState(null);
```

En `resources/js/components/Dashboard.jsx`, reemplazar (2 de 4):

```jsx
        }
    }, []);

    const cargar = useCallback(async () => {
        setErrorCarga(null);
        try {
            const [respuestaFases, respuestaProyectos] = await Promise.all([axios.get('/fases'), axios.get('/proyectos')]);
            setFases(respuestaFases.data);
```

por:

```jsx
        }
    }, []);

    const listFuentes = useCallback(async () => {
        try {
            const { data } = await axios.get('/fuentesFinanciacion');
            setFuentes(data);
        } catch {
            // Solo sirve para filtrar el seguimiento: se conserva el anterior o, sin él, no hay filtro por fuente.
        }
    }, []);

    const cargar = useCallback(async () => {
        setErrorCarga(null);
        const fuentesCargadas = listFuentes();
        try {
            const [respuestaFases, respuestaProyectos] = await Promise.all([axios.get('/fases'), axios.get('/proyectos')]);
            setFases(respuestaFases.data);
```

En `resources/js/components/Dashboard.jsx`, reemplazar (3 de 4):

```jsx
        } finally {
            setCargando(false);
        }
        await listExpedientes();
    }, [listExpedientes]);

    useEffect(() => {
        cargar();
```

por:

```jsx
        } finally {
            setCargando(false);
        }
        await Promise.all([listExpedientes(), fuentesCargadas]);
    }, [listExpedientes, listFuentes]);

    useEffect(() => {
        cargar();
```

En `resources/js/components/Dashboard.jsx`, reemplazar (4 de 4):

```jsx
                <ExpedientesSgrPanel
                    proyectos={proyectos}
                    resumenes={resumenExpedientes}
                    error={errorExpedientes}
                    onReintentar={listExpedientes}
                    compacto={compacto}
```

por:

```jsx
                <ExpedientesSgrPanel
                    proyectos={proyectos}
                    resumenes={resumenExpedientes}
                    fuentes={fuentes}
                    error={errorExpedientes}
                    onReintentar={listExpedientes}
                    compacto={compacto}
```

En `resources/js/components/ExpedientesSgrPanel.jsx`, reemplazar (1 de 7):

```jsx
import { Link } from 'react-router-dom';
import '../../css/ExpedientesSgrPanel.css';
import { textoPlantillas } from '../utils/expedienteChecklist';
import { ESTADO, construirFilas, contarPorEstado, filtrarFilas, rutaExpediente, textoExpediente } from '../utils/expedientesResumen';
import { filasEnSala } from '../utils/tablero';

const ETIQUETA_ESTADO = {
```

por:

```jsx
import { Link } from 'react-router-dom';
import '../../css/ExpedientesSgrPanel.css';
import { textoPlantillas } from '../utils/expedienteChecklist';
import {
    ESTADO,
    TODAS_LAS_FUENTES,
    TODOS_LOS_RESPONSABLES,
    construirFilas,
    contarPorEstado,
    filtrarFilas,
    opcionesFuentes,
    opcionesResponsables,
    pendientesDe,
    rutaExpediente,
    textoExpediente,
} from '../utils/expedientesResumen';
import { filasEnSala } from '../utils/tablero';

const ETIQUETA_ESTADO = {
```

En `resources/js/components/ExpedientesSgrPanel.jsx`, reemplazar (2 de 7):

```jsx

const fecha = (iso) => (iso ? new Date(iso).toLocaleDateString('es-CO') : '—');

const FilaExpediente = ({ fila }) => {
    const tieneExpediente = fila.expedienteId !== null;
    const medible = fila.estado === ESTADO.BORRADOR || fila.estado === ESTADO.RADICADO;
```

por:

```jsx

const fecha = (iso) => (iso ? new Date(iso).toLocaleDateString('es-CO') : '—');

const FilaExpediente = ({ fila, pendientes, etiquetaPendientes }) => {
    const tieneExpediente = fila.expedienteId !== null;
    const medible = fila.estado === ESTADO.BORRADOR || fila.estado === ESTADO.RADICADO;
```

En `resources/js/components/ExpedientesSgrPanel.jsx`, reemplazar (3 de 7):

```jsx
                    </>
                ) : '—'}
            </td>
            <td data-etiqueta="Pendientes" className="numero">{medible ? fila.pendientes : '—'}</td>
            <td data-etiqueta="Actualizado">{fila.estado === ESTADO.RADICADO ? fecha(fila.fechaRadicado) : fecha(fila.actualizado)}</td>
            <td className="exs-col-accion">
                <Link className="exs-abrir" to={rutaExpediente(fila)}>Abrir</Link>
```

por:

```jsx
                    </>
                ) : '—'}
            </td>
            <td data-etiqueta={etiquetaPendientes} className="numero">{medible ? pendientes : '—'}</td>
            <td data-etiqueta="Actualizado">{fila.estado === ESTADO.RADICADO ? fecha(fila.fechaRadicado) : fecha(fila.actualizado)}</td>
            <td className="exs-col-accion">
                <Link className="exs-abrir" to={rutaExpediente(fila)}>Abrir</Link>
```

En `resources/js/components/ExpedientesSgrPanel.jsx`, reemplazar (4 de 7):

```jsx
    );
};

const ExpedientesSgrPanel = ({ proyectos, resumenes, error, onReintentar, compacto = false }) => {
    const [estado, setEstado] = useState('todos');
    const [texto, setTexto] = useState('');

    const filas = useMemo(() => construirFilas(proyectos, resumenes ?? []), [proyectos, resumenes]);
    const conteos = useMemo(() => contarPorEstado(filas), [filas]);
    const visibles = useMemo(() => filtrarFilas(filas, { estado, texto }), [filas, estado, texto]);

    if (error) {
        return (
```

por:

```jsx
    );
};

/** `fuentes` es el catálogo de fuentes de financiación; sin él (null) no se filtra por fuente. */
const ExpedientesSgrPanel = ({ proyectos, resumenes, fuentes = null, error, onReintentar, compacto = false }) => {
    const [estado, setEstado] = useState('todos');
    const [texto, setTexto] = useState('');
    const [fuente, setFuente] = useState(TODAS_LAS_FUENTES);
    const [responsable, setResponsable] = useState(TODOS_LOS_RESPONSABLES);

    const filas = useMemo(() => construirFilas(proyectos, resumenes ?? []), [proyectos, resumenes]);
    // La sala no tiene filtros.
    const opcionesFuente = useMemo(() => (compacto ? [] : opcionesFuentes(fuentes, filas)), [compacto, fuentes, filas]);
    const opcionesResponsable = useMemo(() => (compacto ? [] : opcionesResponsables(filas)), [compacto, filas]);
    // Si la opción elegida desaparece al recargar, deja de filtrar: no queda un filtro oculto.
    const fuenteActiva = opcionesFuente.some((o) => o.valor === fuente) ? fuente : TODAS_LAS_FUENTES;
    const elegido = opcionesResponsable.find((o) => o.valor === responsable);
    const responsableActivo = elegido ? responsable : TODOS_LOS_RESPONSABLES;

    // Las tarjetas cuentan lo que dejan los demás filtros, para que coincidan con la tabla.
    const filtradas = useMemo(
        () => filtrarFilas(filas, { texto, fuente: fuenteActiva, responsable: responsableActivo }),
        [filas, texto, fuenteActiva, responsableActivo],
    );
    const conteos = useMemo(() => contarPorEstado(filtradas, responsableActivo), [filtradas, responsableActivo]);
    const visibles = useMemo(() => filtrarFilas(filtradas, { estado }), [filtradas, estado]);

    if (error) {
        return (
```

En `resources/js/components/ExpedientesSgrPanel.jsx`, reemplazar (5 de 7):

```jsx
    // Se muestran las filas que caben, y el título dice cuántas quedan fuera.
    const filasTabla = compacto ? enSeguimiento.slice(0, filasEnSala(window.innerHeight, window.innerWidth)) : visibles;
    const filasOcultas = enSeguimiento.length - filasTabla.length;

    const opciones = [
        { valor: 'todos', etiqueta: 'Proyectos', cantidad: conteos.todos },
```

por:

```jsx
    // Se muestran las filas que caben, y el título dice cuántas quedan fuera.
    const filasTabla = compacto ? enSeguimiento.slice(0, filasEnSala(window.innerHeight, window.innerWidth)) : visibles;
    const filasOcultas = enSeguimiento.length - filasTabla.length;
    // Al filtrar por una persona importa lo que le falta a ella, no al expediente.
    const etiquetaPendientes = elegido ? `Pendientes de ${elegido.etiqueta}` : 'Pendientes';

    const quitarFiltros = () => {
        setEstado('todos');
        setTexto('');
        setFuente(TODAS_LAS_FUENTES);
        setResponsable(TODOS_LOS_RESPONSABLES);
    };

    const opciones = [
        { valor: 'todos', etiqueta: 'Proyectos', cantidad: conteos.todos },
```

En `resources/js/components/ExpedientesSgrPanel.jsx`, reemplazar (6 de 7):

```jsx
                        Seguimiento por proyecto
                        {filasOcultas > 0 && <span className="exs-secundario"> · mostrando {filasTabla.length} de {enSeguimiento.length}</span>}
                    </h3>
                    {!compacto && <input
                        type="search"
                        className="exs-buscar"
                        placeholder="Buscar proyecto o municipio"
                        aria-label="Buscar proyecto o municipio"
                        value={texto}
                        onChange={(e) => setTexto(e.target.value)}
                    />}
                </div>

                {filasTabla.length === 0 ? (
                    <p className="exs-sin-resultados">
                        {compacto ? 'Aún no hay proyectos con expediente en seguimiento.' : 'Ningún proyecto coincide con el filtro.'}
                    </p>
                ) : (
                    <>
                        <table className="tb-tabla tb-tabla-cartas exs-tabla">
```

por:

```jsx
                        Seguimiento por proyecto
                        {filasOcultas > 0 && <span className="exs-secundario"> · mostrando {filasTabla.length} de {enSeguimiento.length}</span>}
                    </h3>
                    {!compacto && (
                        <div className="exs-filtros">
                            {opcionesFuente.length > 0 && (
                                <select
                                    className="exs-filtro"
                                    aria-label="Filtrar por fuente de financiación"
                                    value={fuenteActiva}
                                    onChange={(e) => setFuente(e.target.value)}
                                >
                                    <option value={TODAS_LAS_FUENTES}>Todas las fuentes</option>
                                    {opcionesFuente.map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                                </select>
                            )}
                            {opcionesResponsable.length > 0 && (
                                <select
                                    className="exs-filtro"
                                    aria-label="Filtrar por responsable con requisitos pendientes"
                                    value={responsableActivo}
                                    onChange={(e) => setResponsable(e.target.value)}
                                >
                                    <option value={TODOS_LOS_RESPONSABLES}>Todos los responsables</option>
                                    {opcionesResponsable.map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                                </select>
                            )}
                            <input
                                type="search"
                                className="exs-buscar"
                                placeholder="Buscar proyecto o municipio"
                                aria-label="Buscar proyecto o municipio"
                                value={texto}
                                onChange={(e) => setTexto(e.target.value)}
                            />
                        </div>
                    )}
                </div>

                {filasTabla.length === 0 ? (
                    compacto ? (
                        <p className="exs-sin-resultados">Aún no hay proyectos con expediente en seguimiento.</p>
                    ) : (
                        <div className="exs-sin-resultados">
                            <p>Ningún proyecto coincide con los filtros elegidos.</p>
                            <button type="button" className="gp-boton" onClick={quitarFiltros}>Quitar filtros</button>
                        </div>
                    )
                ) : (
                    <>
                        <table className="tb-tabla tb-tabla-cartas exs-tabla">
```

En `resources/js/components/ExpedientesSgrPanel.jsx`, reemplazar (7 de 7):

```jsx
                                    <th>Estado</th>
                                    <th>Expediente</th>
                                    <th>Requisitos cumplidos</th>
                                    <th className="numero">Pendientes</th>
                                    <th>Actualizado</th>
                                    <th aria-label="Acciones" />
                                </tr>
                            </thead>
                            <tbody>
                                {filasTabla.map((fila) => <FilaExpediente key={fila.proyectoId} fila={fila} />)}
                            </tbody>
                        </table>
                    </>
```

por:

```jsx
                                    <th>Estado</th>
                                    <th>Expediente</th>
                                    <th>Requisitos cumplidos</th>
                                    <th className="numero">{etiquetaPendientes}</th>
                                    <th>Actualizado</th>
                                    <th aria-label="Acciones" />
                                </tr>
                            </thead>
                            <tbody>
                                {filasTabla.map((fila) => (
                                    <FilaExpediente
                                        key={fila.proyectoId}
                                        fila={fila}
                                        pendientes={pendientesDe(fila, responsableActivo)}
                                        etiquetaPendientes={etiquetaPendientes}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </>
```

En `resources/js/utils/expedientesResumen.js`, reemplazar (1 de 5):

```js
// resumen de GET /expedientes, que solo trae los proyectos que tienen expediente.

import { faseLegible } from './expedienteChecklist';

export const ESTADO = {
    SIN_CONFIGURAR: 'sin_configurar',
```

por:

```js
// resumen de GET /expedientes, que solo trae los proyectos que tienen expediente.

import { faseLegible } from './expedienteChecklist';
import { datosFinanciacionDe } from './fuentesProyecto';

/** Valores de los selectores que no son un id. */
export const TODAS_LAS_FUENTES = 'todas';
export const FUENTE_POR_DEFINIR = 'por_definir';
export const TODOS_LOS_RESPONSABLES = 'todos';

export const ESTADO = {
    SIN_CONFIGURAR: 'sin_configurar',
```

En `resources/js/utils/expedientesResumen.js`, reemplazar (2 de 5):

```js
    // Sin configurar no hay avance que medir: la lista de requisitos aún no es la real.
    const progreso = estado === ESTADO.SIN_CONFIGURAR ? { porcentaje: null } : resumen?.progreso ?? {};
    const { total = 0, cumplidos = 0, porcentaje = 0 } = progreso;

    return {
        proyectoId: proyecto.id,
```

por:

```js
    // Sin configurar no hay avance que medir: la lista de requisitos aún no es la real.
    const progreso = estado === ESTADO.SIN_CONFIGURAR ? { porcentaje: null } : resumen?.progreso ?? {};
    const { total = 0, cumplidos = 0, porcentaje = 0 } = progreso;
    const { fuenteIds, financiacionPorDefinir } = datosFinanciacionDe(proyecto);

    return {
        proyectoId: proyecto.id,
```

En `resources/js/utils/expedientesResumen.js`, reemplazar (3 de 5):

```js
        cumplidos,
        pendientes: total - cumplidos,
        porcentaje,
    };
};
```

por:

```js
        cumplidos,
        pendientes: total - cumplidos,
        porcentaje,
        fuenteIds,
        financiacionPorDefinir,
        // Los cuenta el servicio; sin configurar, como el avance, no se muestran.
        responsables: estado === ESTADO.SIN_CONFIGURAR ? [] : resumen?.responsables ?? [],
    };
};
```

En `resources/js/utils/expedientesResumen.js`, reemplazar (4 de 5):

```js
    return proyectos.map((p) => construirFila(p, porProyecto.get(p.id))).sort(compararFilas);
};

export const contarPorEstado = (filas) => {
    const cuantos = (estado) => filas.filter((f) => f.estado === estado).length;

    return {
```

por:

```js
    return proyectos.map((p) => construirFila(p, porProyecto.get(p.id))).sort(compararFilas);
};

const mismoId = (a, b) => String(a) === String(b);

/**
 * Pendientes que muestra la fila: los del expediente o, con un responsable elegido, los
 * suyos, tal como los contó el servicio.
 */
export const pendientesDe = (fila, responsable = TODOS_LOS_RESPONSABLES) =>
    responsable === TODOS_LOS_RESPONSABLES
        ? fila.pendientes
        : fila.responsables.find((r) => mismoId(r.id, responsable))?.pendientes ?? 0;

/** Recibe las filas ya filtradas por fuente, responsable y texto, para contar lo que se ve. */
export const contarPorEstado = (filas, responsable = TODOS_LOS_RESPONSABLES) => {
    const cuantos = (estado) => filas.filter((f) => f.estado === estado).length;

    return {
```

En `resources/js/utils/expedientesResumen.js`, reemplazar (5 de 5):

```js
        [ESTADO.RADICADO]: cuantos(ESTADO.RADICADO),
        pendientesEnBorrador: filas
            .filter((f) => f.estado === ESTADO.BORRADOR)
            .reduce((suma, f) => suma + f.pendientes, 0),
    };
};

export const filtrarFilas = (filas, { estado, texto }) => {
    const buscado = normalizar(texto);

    return filas.filter(
        (f) =>
            (estado === 'todos' || f.estado === estado) &&
            (buscado === '' || normalizar(`${f.nombre} ${f.municipio}`).includes(buscado)),
    );
};

/**
 * Columna "Expediente" del seguimiento: "#12 · Fase SGR III — Factibilidad" o "#12".
 * La fase SGR solo se muestra en un expediente medible con plantilla de motor sgr.
```

por:

```js
        [ESTADO.RADICADO]: cuantos(ESTADO.RADICADO),
        pendientesEnBorrador: filas
            .filter((f) => f.estado === ESTADO.BORRADOR)
            .reduce((suma, f) => suma + pendientesDe(f, responsable), 0),
    };
};

const tieneFuente = (fila, fuente) =>
    fuente === TODAS_LAS_FUENTES ||
    (fuente === FUENTE_POR_DEFINIR ? fila.financiacionPorDefinir : fila.fuenteIds.some((id) => mismoId(id, fuente)));

// Solo quedan los proyectos en los que esa persona tiene algo pendiente.
const tieneResponsable = (fila, responsable) =>
    responsable === TODOS_LOS_RESPONSABLES || fila.responsables.some((r) => mismoId(r.id, responsable));

export const filtrarFilas = (
    filas,
    { estado = 'todos', texto = '', fuente = TODAS_LAS_FUENTES, responsable = TODOS_LOS_RESPONSABLES },
) => {
    const buscado = normalizar(texto);

    return filas.filter(
        (f) =>
            (estado === 'todos' || f.estado === estado) &&
            tieneFuente(f, fuente) &&
            tieneResponsable(f, responsable) &&
            (buscado === '' || normalizar(`${f.nombre} ${f.municipio}`).includes(buscado)),
    );
};

/**
 * Opciones del filtro por fuente: las del catálogo (en su orden) que tiene algún proyecto de
 * la lista, también las inactivas, y "Por definir" si algún proyecto lo está. Sin catálogo
 * no hay filtro.
 */
export const opcionesFuentes = (fuentes, filas) => {
    if (!fuentes?.length) return [];

    const usadas = new Set(filas.flatMap((f) => f.fuenteIds.map(String)));
    const opciones = fuentes
        .filter((fuente) => usadas.has(String(fuente.id)))
        .map((fuente) => ({ valor: String(fuente.id), etiqueta: fuente.nombre }));

    return filas.some((f) => f.financiacionPorDefinir)
        ? [...opciones, { valor: FUENTE_POR_DEFINIR, etiqueta: 'Por definir' }]
        : opciones;
};

/** Opciones del filtro por responsable: quienes tienen algo pendiente, sin repetir y por nombre. */
export const opcionesResponsables = (filas) => {
    const porId = new Map();
    filas.forEach((f) => f.responsables.forEach((r) => porId.set(String(r.id), r.nombre)));

    return [...porId]
        .map(([valor, etiqueta]) => ({ valor, etiqueta }))
        .sort((a, b) => a.etiqueta.localeCompare(b.etiqueta, 'es'));
};

/**
 * Columna "Expediente" del seguimiento: "#12 · Fase SGR III — Factibilidad" o "#12".
 * La fase SGR solo se muestra en un expediente medible con plantilla de motor sgr.
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php tests/Feature/Viabilizacion/SeguimientoRequisitoApiTest.php`
Expected: pasan todas.

Run: `npx vitest run resources/js/utils/expedientesResumen.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 634 passed.

Run: `npm test`
Expected: 335 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Models/ProyectoRequisito.php app/Services/Viabilizacion/ExpedienteService.php resources/css/ExpedientesSgrPanel.css resources/js/components/Dashboard.jsx resources/js/components/ExpedientesSgrPanel.jsx resources/js/utils/expedientesResumen.js resources/js/utils/expedientesResumen.test.js tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php tests/Feature/Viabilizacion/SeguimientoRequisitoApiTest.php
git commit -m "feat: filter the expediente dashboard by source and responsible"
```

---

### Tarea 8: Expediente automático al entrar a Formulación

Decisión del usuario: pasar un proyecto a Formulación al editarlo crea su expediente automático si le corresponde.

**Files:**
- Modify: `app/Http/Controllers/ProyectoController.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Test: `tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`, reemplazar (1 de 2):

```php
use Tests\TestCase;

/**
 * El expediente como seguimiento de la formulación: nace solo al crear el proyecto,
 * se configura una vez y desde ahí solo se entra a chequear.
 */
class ExpedienteAutomaticoTest extends TestCase
{
```

por:

```php
use Tests\TestCase;

/**
 * El expediente como seguimiento de la formulación: nace al crear el proyecto, al definir
 * su financiación o al entrar a Formulación, se configura una vez y desde ahí solo se entra
 * a chequear.
 */
class ExpedienteAutomaticoTest extends TestCase
{
```

En `tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`, reemplazar (2 de 2):

```php
        $this->como(Rol::Consulta)->getJson($url)->assertJsonPath('0.configurado', true);
    }

    // -------------------------------------------- salida de la fase Formulación

    public function test_avisa_si_el_proyecto_sale_de_formulacion_sin_radicar(): void
```

por:

```php
        $this->como(Rol::Consulta)->getJson($url)->assertJsonPath('0.configurado', true);
    }

    // ------------------------------------------- entrada a la fase Formulación

    /** Lo que la creación automática decide por su cuenta, para comparar dos expedientes. */
    private function preseleccion(ProyectoExpediente $expediente): array
    {
        return [
            'estado' => $expediente->estado,
            'fecha_configuracion' => $expediente->fecha_configuracion,
            'fase_sgr' => $expediente->fase_sgr,
            'plantillas' => $expediente->versionesPlantilla()->orderBy('catalogo_versiones.id')->get()
                ->map(fn ($v) => [$v->id, (bool) $v->pivot->es_principal])->all(),
            'sectores' => $expediente->sectores()->pluck('requisitos_sectores.id')->all(),
            'aplican' => $expediente->requisitos()->where('aplica', true)->count(),
        ];
    }

    public function test_pasar_a_formulacion_crea_el_expediente_con_la_preseleccion_del_alta(): void
    {
        $sector = $this->sectorMapeado();
        $this->guardar(['nombre' => 'Alta', 'sector' => $sector])->assertOk();
        $this->guardar(['nombre' => 'Edicion', 'sector' => $sector, 'fase' => $this->licitacion])->assertOk();
        $editado = Proyecto::where('nombre', 'Edicion')->firstOrFail();
        $this->assertSame(0, $editado->expedientes()->count());

        $this->guardar(['accion' => 'Editar', 'id' => $editado->id, 'nombre' => 'Edicion', 'sector' => $sector])->assertOk();

        $alta = Proyecto::where('nombre', 'Alta')->firstOrFail()->expedientes()->sole();
        $expediente = $editado->expedientes()->sole();
        $this->assertNotNull($expediente->usuario_creo_id);
        $this->assertSame([PlantillaChecklist::sgr()->id], $expediente->versionesPlantilla()->pluck('plantilla_id')->all());
        $this->assertSame($this->preseleccion($alta), $this->preseleccion($expediente));
    }

    public function test_pasar_a_formulacion_por_definir_no_crea_expediente(): void
    {
        $porDefinir = $this->proyecto(['financiacion_por_definir' => true, 'fase' => $this->licitacion], []);
        $this->guardar(['accion' => 'Editar', 'id' => $porDefinir->id, 'fuenteIds' => [], 'financiacionPorDefinir' => true])->assertOk();

        // Sin enviar la financiación sigue "por definir", aunque le queden fuentes de antes.
        $sinEnviar = $this->proyecto(['financiacion_por_definir' => true, 'fase' => $this->licitacion], [$this->fuenteSgr]);
        $formulario = $this->formulario(['accion' => 'Editar', 'id' => $sinEnviar->id]);
        unset($formulario['fuenteIds']);
        $this->como(Rol::Gestor)->postJson('/GestPro/guardarProyecto', $formulario)->assertOk();

        foreach ([$porDefinir, $sinEnviar] as $proyecto) {
            $this->assertDatabaseHas('proyectos', ['id' => $proyecto->id, 'fase' => $this->formulacion, 'financiacion_por_definir' => true]);
        }
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_pasar_a_formulacion_con_un_expediente_no_crea_otro(): void
    {
        $borrador = $this->proyecto(['fase' => $this->licitacion]);
        $this->expedienteSinConfigurar($borrador);
        $radicado = $this->proyecto(['fase' => $this->licitacion]);
        $expediente = $this->expedienteSinConfigurar($radicado);
        DB::table('proyectos_expedientes')->where('id', $expediente->id)->update(['estado' => 'radicado']);

        $this->guardar(['accion' => 'Editar', 'id' => $borrador->id])->assertOk();
        $this->guardar(['accion' => 'Editar', 'id' => $radicado->id])->assertOk();

        $this->assertSame(1, $borrador->expedientes()->count());
        $this->assertSame(1, $radicado->expedientes()->count());
    }

    public function test_seguir_en_formulacion_no_crea_ni_duplica_el_expediente(): void
    {
        $conExpediente = $this->proyecto();
        $this->expedienteSinConfigurar($conExpediente);
        // Un borrador eliminado a propósito no vuelve a nacer mientras siga en Formulación.
        $eliminado = $this->proyecto();
        app(ExpedienteService::class)->eliminar($this->expedienteSinConfigurar($eliminado));

        foreach ([$conExpediente, $eliminado] as $proyecto) {
            $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id])->assertOk();
            $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id, 'fuenteIds' => [$this->fuentePropios, $this->fuenteSgr]])->assertOk();
        }

        $this->assertSame(1, $conExpediente->expedientes()->count());
        $this->assertSame(0, $eliminado->expedientes()->count());
    }

    public function test_salir_de_formulacion_no_crea_expediente(): void
    {
        $proyecto = $this->proyecto();

        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id, 'fase' => $this->licitacion])->assertOk();
        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id, 'fase' => null])->assertOk();

        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_si_falla_la_creacion_al_pasar_a_formulacion_el_proyecto_se_guarda_igual(): void
    {
        Exceptions::fake();
        $proyecto = $this->proyecto(['fase' => $this->licitacion, 'sector' => $this->sectorConEquivalenteInactivo()]);

        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id])->assertOk();

        $this->assertDatabaseHas('proyectos', ['id' => $proyecto->id, 'fase' => $this->formulacion]);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
        Exceptions::assertReported(ReglaNegocioException::class);
    }

    public function test_si_falla_saber_si_entra_a_formulacion_el_proyecto_se_guarda_igual(): void
    {
        Exceptions::fake();
        // Sin fase antes: la única consulta a fases después de guardar es la de entrar a Formulación.
        $proyecto = $this->proyecto(['fase' => null]);
        DB::listen(function ($consulta) {
            if (str_contains($consulta->sql, '"fases"')) {
                throw new \RuntimeException('Sin acceso a fases');
            }
        });

        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id])
            ->assertOk()
            ->assertExactJson(['success' => 'Proyecto guardado correctamente']);

        $this->assertDatabaseHas('proyectos', ['id' => $proyecto->id, 'fase' => $this->formulacion]);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'Sin acceso a fases');
    }

    // -------------------------------------------- salida de la fase Formulación

    public function test_avisa_si_el_proyecto_sale_de_formulacion_sin_radicar(): void
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`
Expected: FALLAN 3 y pasan 36: pasar a Formulación todavía no crea el expediente (`ModelNotFoundException` al buscarlo), y no existe `entraAFormulacion`.

- [ ] **Paso 3: Implementar**

En `app/Http/Controllers/ProyectoController.php`, reemplazar (1 de 2):

```php
        $financiacion = $esNuevo || array_key_exists('fuenteIds', $proyecto) || array_key_exists('financiacionPorDefinir', $proyecto)
            ? $fuentes->validar($proyecto, $esNuevo ? null : (int) $proyectoId)
            : null;
        // Antes de la transacción, como la fase: un proyecto "por definir" que pasa a tener
        // fuente crea su expediente. Solo entonces: en cada edición volvería a nacer un
        // borrador que alguien eliminó a propósito.
        $estabaPorDefinir = ! $esNuevo
            && (bool) DB::table('proyectos')->where('id', $proyectoId)->value('financiacion_por_definir');
```

por:

```php
        $financiacion = $esNuevo || array_key_exists('fuenteIds', $proyecto) || array_key_exists('financiacionPorDefinir', $proyecto)
            ? $fuentes->validar($proyecto, $esNuevo ? null : (int) $proyectoId)
            : null;
        // Antes de guardar, como la fase: un proyecto "por definir" que pasa a tener fuente
        // crea su expediente. Solo entonces: en cada edición volvería a nacer un borrador que
        // alguien eliminó a propósito.
        $estabaPorDefinir = ! $esNuevo
            && (bool) DB::table('proyectos')->where('id', $proyectoId)->value('financiacion_por_definir');
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (2 de 2):

```php
        // Después del commit: el proyecto queda guardado pase lo que pase con el expediente.
        $respuesta = ['success' => 'Proyecto guardado correctamente'];
        $guardado = Proyecto::find($proyectoId);
        // crearAutomatico decide si corresponde: Formulación, alguna fuente y sin expediente.
        if ($guardado && ($esNuevo || ($estabaPorDefinir && ! $guardado->financiacion_por_definir))) {
            $expedientes->crearAutomatico($guardado, $request->user());
        }
        if ($guardado && ! $esNuevo && ($advertencia = $expedientes->advertenciaAlSalirDeFormulacion($guardado, $faseAnterior))) {
```

por:

```php
        // Después del commit: el proyecto queda guardado pase lo que pase con el expediente.
        $respuesta = ['success' => 'Proyecto guardado correctamente'];
        $guardado = Proyecto::find($proyectoId);
        // Se intenta al darlo de alta, al definir su financiación o al entrar a Formulación;
        // crearAutomatico decide si corresponde: Formulación, alguna fuente y sin expediente.
        if ($guardado && (
            $esNuevo
            || ($estabaPorDefinir && ! $guardado->financiacion_por_definir)
            || $expedientes->entraAFormulacion($guardado, $faseAnterior)
        )) {
            $expedientes->crearAutomatico($guardado, $request->user());
        }
        if ($guardado && ! $esNuevo && ($advertencia = $expedientes->advertenciaAlSalirDeFormulacion($guardado, $faseAnterior))) {
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 2):

```php
    }

    /**
     * Expediente que nace con el proyecto, si le corresponde. Con plantilla SGR compatible,
     * la preselecciona con la fase III y el sector sugerido por el proyecto; sin ella, nace
     * sin plantillas. Nunca interrumpe a quien guarda el proyecto: un fallo se registra y el
     * expediente se puede crear a mano.
```

por:

```php
    }

    /**
     * Expediente que nace al guardar el proyecto, si le corresponde. Con plantilla SGR compatible,
     * la preselecciona con la fase III y el sector sugerido por el proyecto; sin ella, nace
     * sin plantillas. Nunca interrumpe a quien guarda el proyecto: un fallo se registra y el
     * expediente se puede crear a mano.
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 2):

```php
        });
    }

    /** Aviso, sin bloquear el cambio, si el proyecto deja Formulación con un borrador. */
    public function advertenciaAlSalirDeFormulacion(Proyecto $proyecto, mixed $faseAnterior): ?string
    {
```

por:

```php
        });
    }

    /**
     * El proyecto pasó a Formulación con este guardado. Seguir en ella no cuenta: en cada
     * edición volvería a nacer un borrador que alguien eliminó a propósito. Se pregunta con el
     * proyecto ya guardado: como crearAutomatico, nunca interrumpe a quien lo guardó. Un fallo
     * al consultarlo se registra y cuenta como que no entró; el expediente se puede crear a mano.
     */
    public function entraAFormulacion(Proyecto $proyecto, mixed $faseAnterior): bool
    {
        try {
            return ! $this->esFormulacion($faseAnterior) && $this->esFormulacion($proyecto->fase);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /** Aviso, sin bloquear el cambio, si el proyecto deja Formulación con un borrador. */
    public function advertenciaAlSalirDeFormulacion(Proyecto $proyecto, mixed $faseAnterior): ?string
    {
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 641 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Http/Controllers/ProyectoController.php app/Services/Viabilizacion/ExpedienteService.php tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php
git commit -m "feat: create the automatic expediente when a project enters Formulacion"
```

---

### Tarea 9: Tope del orden SGR

El orden del catálogo admite hasta 99 999 y el servicio lo acota dentro de su sección o plantilla.

**Files:**
- Modify: `app/Http/Controllers/CatalogoRequisitosController.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Test: `tests/Feature/Viabilizacion/CatalogoRequisitosApiTest.php`
- Test: `tests/Feature/Viabilizacion/ComposicionExpedienteTest.php`
- Test: `tests/Feature/Viabilizacion/PlantillasRequisitosApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `tests/Feature/Viabilizacion/CatalogoRequisitosApiTest.php`, reemplazar (1 de 1):

```php
        $this->assertSame('fase=3 & comp=suelo', $this->requisitoDe($borrador, 'a5')->condicion);
    }

    public function test_un_requisito_sectorial_exige_sector_y_tipo_coherente(): void
    {
        $borrador = $this->crearBorrador();
```

por:

```php
        $this->assertSame('fase=3 & comp=suelo', $this->requisitoDe($borrador, 'a5')->condicion);
    }

    public function test_el_orden_de_un_requisito_sgr_no_sale_de_su_seccion(): void
    {
        $borrador = $this->crearBorrador();
        $c = DB::table('requisitos_catalogo')->where('catalogo_version_id', $borrador)->where('seccion', 'C')->orderBy('id')->first();
        $url = "/GestPro/catalogo-requisitos/versiones/{$borrador}/requisitos/{$c->id}";

        $this->admin()->putJson($url, $this->datosDe($c, ['orden' => 100_000]))
            ->assertStatus(422)
            ->assertJsonPath('errors.orden.0', 'Revisa el orden: el máximo es 99999.');
        $this->admin()->putJson($url, $this->datosDe($c, ['orden' => 99_999]))->assertOk();
        $this->assertSame(99_999, (int) $this->requisitoDe($borrador, $c->clave)->orden);
    }

    public function test_un_requisito_sectorial_exige_sector_y_tipo_coherente(): void
    {
        $borrador = $this->crearBorrador();
```

En `tests/Feature/Viabilizacion/ComposicionExpedienteTest.php`, reemplazar (1 de 1):

```php
        $this->assertSame(100_002, $this->fila($expediente, $a2)->orden);
    }

    // --------------------------------------------------------------- propios

    public function test_sincronizar_no_toca_los_requisitos_propios(): void
```

por:

```php
        $this->assertSame(100_002, $this->fila($expediente, $a2)->orden);
    }

    public function test_un_orden_sgr_grande_de_datos_anteriores_no_sale_de_su_seccion(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos' => [null, 'obligatorio']]);
        $expediente = $this->expediente($this->configuracion(['fase_sgr' => 3, 'sectores' => ['justicia'], 'tipos' => [], 'componentes' => [], 'tipologias' => []]));
        $filasC = $expediente->requisitos()->whereHas('requisito', fn ($q) => $q->where('seccion', 'C'))
            ->with('requisito')->orderBy('orden')->get();
        $this->assertGreaterThanOrEqual(3, $filasC->count());
        [$primera, $segunda, $tercera] = $filasC->take(3)->map->requisito->all();
        // Ya guardados así antes del tope del catálogo: el servicio no los valida.
        RequisitoCatalogo::whereKey($primera->id)->update(['orden' => 150_000]);
        RequisitoCatalogo::whereKey($segunda->id)->update(['orden' => 9_999_999]);

        $this->componer($expediente, [$this->sgr, $agua], $this->sgr);

        $seccionC = 4 * 100_000;
        $this->assertSame($seccionC + 99_999, $this->fila($expediente, $primera)->orden);
        $this->assertSame($seccionC + 99_999, $this->fila($expediente, $segunda)->orden);
        $this->assertSame($seccionC + $tercera->orden, $this->fila($expediente, $tercera)->orden);
        $this->assertLessThan(5 * 100_000, $this->fila($expediente, $segunda)->orden);
        $this->assertLessThan($this->fila($expediente, $this->requisito($agua, 'Planos'))->orden, $this->fila($expediente, $segunda)->orden);
        $this->assertSame(10_000_000 + 1, $this->fila($expediente, $this->requisito($agua, 'Planos'))->orden);
    }

    // --------------------------------------------------------------- propios

    public function test_sincronizar_no_toca_los_requisitos_propios(): void
```

En `tests/Feature/Viabilizacion/PlantillasRequisitosApiTest.php`, reemplazar (1 de 1):

```php
            ->assertJsonPath('errors.orden.0', 'Revisa el orden: el mínimo es 0.');
    }

    public function test_el_orden_de_un_requisito_tiene_tope_para_no_invadir_la_plantilla_siguiente(): void
    {
        $this->crear(['orden' => 10_000_000])->assertStatus(422)
            ->assertJsonPath('errors.orden.0', 'Revisa el orden: el máximo es 9999999.');
        $this->crear(['orden' => 9_999_999])->assertCreated();
    }

    public function test_ordenar_con_un_id_que_no_es_entero_nombra_el_campo_en_espanol(): void
```

por:

```php
            ->assertJsonPath('errors.orden.0', 'Revisa el orden: el mínimo es 0.');
    }

    public function test_el_orden_de_un_requisito_tiene_tope_para_no_invadir_el_bloque_siguiente(): void
    {
        // El mismo tope en todos los motores: en la SGR, el bloque es la sección.
        $this->crear(['orden' => 100_000])->assertStatus(422)
            ->assertJsonPath('errors.orden.0', 'Revisa el orden: el máximo es 99999.');
        $this->crear(['orden' => 99_999])->assertCreated();
    }

    public function test_ordenar_con_un_id_que_no_es_entero_nombra_el_campo_en_espanol(): void
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Viabilizacion/CatalogoRequisitosApiTest.php tests/Feature/Viabilizacion/ComposicionExpedienteTest.php tests/Feature/Viabilizacion/PlantillasRequisitosApiTest.php`
Expected: FALLAN 3 y pasan 65: la API acepta órdenes de 100 000 o más (200 o 201 en vez de 422) y una fila SGR con orden 150 000 queda en 550 000, fuera de su sección.

- [ ] **Paso 3: Implementar**

En `app/Http/Controllers/CatalogoRequisitosController.php`, reemplazar (1 de 1):

```php
            'requiere_aprobacion_no_aplica' => ['sometimes', 'boolean'],
            'codigo_comun' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'nota' => ['nullable', 'string'],
            'orden' => ['sometimes', 'integer', 'min:0', 'max:9999999'],
            'activo' => ['sometimes', 'boolean'],
        ];
```

por:

```php
            'requiere_aprobacion_no_aplica' => ['sometimes', 'boolean'],
            'codigo_comun' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'nota' => ['nullable', 'string'],
            // Tope: el orden de una fila SGR no debe salir de su sección (ExpedienteService).
            'orden' => ['sometimes', 'integer', 'min:0', 'max:99999'],
            'activo' => ['sometimes', 'boolean'],
        ];
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 2):

```php
    // del requisito. La principal tiene posición 0: un expediente solo SGR conserva su orden.
    private const ORDEN_POR_PLANTILLA = 10_000_000;
    private const ORDEN_POR_SECCION = 100_000;

    /** Del más al menos exigente: una fila con varios orígenes toma el primero que aparezca. */
    private const CARACTER_POR_EXIGENCIA = [RequisitoCatalogo::OBLIGATORIO, RequisitoCatalogo::CONDICIONADO, RequisitoCatalogo::OPCIONAL];
```

por:

```php
    // del requisito. La principal tiene posición 0: un expediente solo SGR conserva su orden.
    private const ORDEN_POR_PLANTILLA = 10_000_000;
    private const ORDEN_POR_SECCION = 100_000;
    // Tope del orden del requisito dentro de su bloque: la sección en el motor sgr, la plantilla
    // en los demás. El catálogo ya no admite órdenes de 100 000 o más, pero datos anteriores
    // pueden tenerlos y no deben pasar a la sección ni a la plantilla siguientes.
    private const TOPE_ORDEN_EN_SECCION = self::ORDEN_POR_SECCION - 1;
    private const TOPE_ORDEN_EN_PLANTILLA = self::ORDEN_POR_PLANTILLA - 1;

    /** Del más al menos exigente: una fila con varios orígenes toma el primero que aparezca. */
    private const CARACTER_POR_EXIGENCIA = [RequisitoCatalogo::OBLIGATORIO, RequisitoCatalogo::CONDICIONADO, RequisitoCatalogo::OPCIONAL];
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 2):

```php
                        'caracter' => $requisito->caracter,
                        'orden' => $posicion * self::ORDEN_POR_PLANTILLA
                            + ($esSgr ? self::ORDEN_SECCION[$requisito->seccion] * self::ORDEN_POR_SECCION : 0)
                            + $requisito->orden,
                    ];
                }
                $llaves[$llave]['origenes'][] = $requisito;
```

por:

```php
                        'caracter' => $requisito->caracter,
                        'orden' => $posicion * self::ORDEN_POR_PLANTILLA
                            + ($esSgr ? self::ORDEN_SECCION[$requisito->seccion] * self::ORDEN_POR_SECCION : 0)
                            + min((int) $requisito->orden, $esSgr ? self::TOPE_ORDEN_EN_SECCION : self::TOPE_ORDEN_EN_PLANTILLA),
                    ];
                }
                $llaves[$llave]['origenes'][] = $requisito;
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Viabilizacion/CatalogoRequisitosApiTest.php tests/Feature/Viabilizacion/ComposicionExpedienteTest.php tests/Feature/Viabilizacion/PlantillasRequisitosApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 643 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Http/Controllers/CatalogoRequisitosController.php app/Services/Viabilizacion/ExpedienteService.php tests/Feature/Viabilizacion/CatalogoRequisitosApiTest.php tests/Feature/Viabilizacion/ComposicionExpedienteTest.php tests/Feature/Viabilizacion/PlantillasRequisitosApiTest.php
git commit -m "fix: keep SGR row order inside its section"
```

---

### Tarea 10: Catálogo SGR solo cuando hace falta

La pantalla del expediente pide `/catalogo-requisitos/vigente` solo con plantilla SGR y sobrevive a su fallo.

**Files:**
- Modify: `resources/js/components/ExpedienteConfiguracion.jsx`
- Modify: `resources/js/components/ExpedienteViabilizacion.jsx`
- Modify: `resources/js/utils/expedienteChecklist.js`
- Test: `resources/js/utils/expedienteChecklist.test.js`
- Modify: `resources/js/utils/plantillasExpediente.js`
- Test: `resources/js/utils/plantillasExpediente.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (1 de 1):

```js
        expect(sectoresSinTipo({ sector_ids: [3], tipo_ids: [] }, catalogo)).toEqual([]);
        expect(sectoresSinTipo({ sector_ids: [], tipo_ids: [] }, catalogo)).toEqual([]);
    });
});

describe('agruparChecklist', () => {
```

por:

```js
        expect(sectoresSinTipo({ sector_ids: [3], tipo_ids: [] }, catalogo)).toEqual([]);
        expect(sectoresSinTipo({ sector_ids: [], tipo_ids: [] }, catalogo)).toEqual([]);
    });

    it('sin catálogo no avisa de ninguno', () => {
        expect(sectoresSinTipo({ sector_ids: [1], tipo_ids: [] }, null)).toEqual([]);
    });
});

describe('agruparChecklist', () => {
```

En `resources/js/utils/plantillasExpediente.test.js`, reemplazar (1 de 2):

```js
    elegirPrincipal,
    hayCompatibleSgr,
    incluyeSgr,
    opcionPlantilla,
    plantillasDelPanel,
    plantillasSinLista,
    seleccionParaNuevo,
    textoPlantillasNuevo,
} from './plantillasExpediente';
```

por:

```js
    elegirPrincipal,
    hayCompatibleSgr,
    incluyeSgr,
    necesitaCatalogoSgr,
    opcionPlantilla,
    plantillasDelPanel,
    plantillasSinLista,
    seleccionConSgr,
    seleccionParaNuevo,
    textoPlantillasNuevo,
} from './plantillasExpediente';
```

En `resources/js/utils/plantillasExpediente.test.js`, reemplazar (2 de 2):

```js
    });
});

describe('hayCompatibleSgr', () => {
    it('solo cuenta una plantilla sgr que hoy se ofrece', () => {
        expect(hayCompatibleSgr([AGUA, SGR])).toBe(true);
```

por:

```js
    });
});

describe('seleccionConSgr', () => {
    const delExpediente = { plantilla_id: 1, nombre: 'SGR', motor: 'sgr' };

    it('busca la plantilla elegida en la lista de disponibles', () => {
        expect(seleccionConSgr({ ...base, plantilla_ids: [5, 1] }, { plantillas: [SGR, AGUA] }, [])).toBe(true);
        expect(seleccionConSgr({ ...base, plantilla_ids: [5] }, { plantillas: [SGR, AGUA] }, [])).toBe(false);
    });

    it('sin la lista, la busca en las del expediente', () => {
        expect(seleccionConSgr({ ...base, plantilla_ids: [1] }, null, [delExpediente])).toBe(true);
        expect(seleccionConSgr({ ...base, plantilla_ids: [1] }, null, null)).toBe(false);
    });

    it('sin selección no incluye ninguna', () => {
        expect(seleccionConSgr(null, { plantillas: [SGR] }, [delExpediente])).toBe(false);
    });
});

describe('necesitaCatalogoSgr', () => {
    const expediente = (extra = {}) => ({ id: 7, tiene_sgr: false, plantillas: [], ...extra });

    it('sin expediente abierto no hace falta', () => {
        expect(necesitaCatalogoSgr(null, null, { plantillas: [SGR] })).toBe(false);
    });

    it('hace falta si el expediente tiene una plantilla sgr, aunque la selección ya la haya quitado', () => {
        expect(necesitaCatalogoSgr(expediente({ tiene_sgr: true }), { ...base, plantilla_ids: [] }, null)).toBe(true);
    });

    it('hace falta si la selección en curso incluye una plantilla sgr', () => {
        expect(necesitaCatalogoSgr(expediente(), { ...base, plantilla_ids: [5, 2] }, { plantillas: [AGUA, COPIA_SGR] })).toBe(true);
    });

    it('un expediente sin plantillas sgr ni en la selección no lo necesita', () => {
        expect(necesitaCatalogoSgr(expediente(), { ...base, plantilla_ids: [5] }, { plantillas: [SGR, AGUA] })).toBe(false);
        expect(necesitaCatalogoSgr(expediente(), null, null)).toBe(false);
    });
});

describe('hayCompatibleSgr', () => {
    it('solo cuenta una plantilla sgr que hoy se ofrece', () => {
        expect(hayCompatibleSgr([AGUA, SGR])).toBe(true);
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/expedienteChecklist.test.js resources/js/utils/plantillasExpediente.test.js`
Expected: FALLAN 8 y pasan 138: `sectoresSinTipo` no tolera un catálogo `null` y no existen `seleccionConSgr` ni `necesitaCatalogoSgr`.

- [ ] **Paso 3: Implementar**

En `resources/js/components/ExpedienteConfiguracion.jsx`, reemplazar (1 de 3):

```jsx
import React from 'react';
import { FASES, alternarId, alternarSector } from '../utils/expedienteChecklist';
import {
    AVISO_SIN_SECTOR, alternarPlantilla, avisosPlantilla, detallePlantilla, elegirPrincipal, incluyeSgr, opcionPlantilla,
    plantillasDelPanel,
    plantillasSinLista,
} from '../utils/plantillasExpediente';

// Opciones del catálogo vigente más lo ya elegido que se haya desactivado después:
```

por:

```jsx
import React from 'react';
import { FASES, alternarId, alternarSector } from '../utils/expedienteChecklist';
import {
    AVISO_SIN_SECTOR, alternarPlantilla, avisosPlantilla, detallePlantilla, elegirPrincipal, opcionPlantilla,
    plantillasDelPanel,
    plantillasSinLista,
    seleccionConSgr,
} from '../utils/plantillasExpediente';

// Opciones del catálogo vigente más lo ya elegido que se haya desactivado después:
```

En `resources/js/components/ExpedienteConfiguracion.jsx`, reemplazar (2 de 3):

```jsx
    );
};

const ExpedienteConfiguracion = ({
    catalogo, expediente, seleccion, deshabilitada, recalculando, onCambiar, disponibles, errorPlantillas, onReintentarPlantillas,
}) => {
    const { configuracion, progreso } = expediente;
    const sectores = conSeleccionadas(catalogo.sectores, configuracion.sectores);
    const componentes = conSeleccionadas(catalogo.componentes, configuracion.componentes);
    const tipologias = conSeleccionadas(catalogo.tipologias, configuracion.tipologias);
    const sectoresMarcados = sectores.filter((s) => seleccion.sector_ids.includes(s.id));
    // Con la lista y con las del expediente: si la lista no cargó, el panel SGR igual sigue a la selección.
    const conSgr = incluyeSgr(seleccion.plantilla_ids, [...(disponibles?.plantillas ?? []), ...expediente.plantillas]);

    const tiposDe = (sector) => conSeleccionadas(
        sector.tipos ?? [],
        configuracion.tipos.filter((t) => t.sector_id === sector.id)
    );

    return (
        <aside className="exv-config" aria-label="Configuración del expediente">
            <div className="exv-config-head">
```

por:

```jsx
    );
};

/** Opciones del panel SGR: fase, componentes, sectores, tipos y tipologías, con el catálogo ya cargado. */
const OpcionesSgr = ({ catalogo, configuracion, seleccion, deshabilitada, onCambiar }) => {
    const sectores = conSeleccionadas(catalogo.sectores, configuracion.sectores);
    const componentes = conSeleccionadas(catalogo.componentes, configuracion.componentes);
    const tipologias = conSeleccionadas(catalogo.tipologias, configuracion.tipologias);
    const sectoresMarcados = sectores.filter((s) => seleccion.sector_ids.includes(s.id));

    const tiposDe = (sector) => conSeleccionadas(
        sector.tipos ?? [],
        configuracion.tipos.filter((t) => t.sector_id === sector.id)
    );

    return (
        <>
            <fieldset>
                <legend>Fase del proyecto</legend>
                <div className="exv-seg">
                    {FASES.map((fase) => (
                        <label key={fase.valor} className={seleccion.fase_sgr === fase.valor ? 'activa' : ''}>
                            <input
                                type="radio"
                                name="fase_sgr"
                                value={fase.valor}
                                checked={seleccion.fase_sgr === fase.valor}
                                disabled={deshabilitada}
                                onChange={() => onCambiar({ ...seleccion, fase_sgr: fase.valor })}
                            />
                            {fase.romano}
                            <span className="exv-seg-tag">{fase.corta}</span>
                        </label>
                    ))}
                </div>
            </fieldset>

            <fieldset>
                <legend>Componentes del proyecto</legend>
                {componentes.map((c) => (
                    <Casilla
                        key={c.id}
                        marcada={seleccion.componente_ids.includes(c.id)}
                        deshabilitada={deshabilitada}
                        onCambiar={() => onCambiar({ ...seleccion, componente_ids: alternarId(seleccion.componente_ids, c.id) })}
                    >
                        {c.nombre}
                    </Casilla>
                ))}
            </fieldset>

            <fieldset>
                <legend>Sector de inversión <small>(uno o varios)</small></legend>
                {sectores.map((s) => (
                    <Casilla
                        key={s.id}
                        marcada={seleccion.sector_ids.includes(s.id)}
                        deshabilitada={deshabilitada}
                        onCambiar={() => onCambiar(alternarSector(seleccion, s.id, catalogo))}
                    >
                        {s.nombre}
                        {s.fuente && <span className="exv-opt-sub">Acuerdo 12/2024</span>}
                    </Casilla>
                ))}
            </fieldset>

            {sectoresMarcados.length > 0 && (
                <fieldset className="exv-tipos">
                    <legend>Tipo de proyecto <small>(marca los que apliquen)</small></legend>
                    {sectoresMarcados.map((sector) => (
                        <div key={sector.id} className="exv-tipo-grupo">
                            <div className="exv-tipo-titulo">{sector.nombre}</div>
                            {tiposDe(sector).map((t) => (
                                <Casilla
                                    key={t.id}
                                    marcada={seleccion.tipo_ids.includes(t.id)}
                                    deshabilitada={deshabilitada}
                                    onCambiar={() => onCambiar({ ...seleccion, tipo_ids: alternarId(seleccion.tipo_ids, t.id) })}
                                >
                                    {t.nombre}
                                </Casilla>
                            ))}
                        </div>
                    ))}
                </fieldset>
            )}

            <fieldset>
                <legend>Tipologías y fuentes especiales</legend>
                {tipologias.map((t) => (
                    <Casilla
                        key={t.id}
                        marcada={seleccion.tipologia_ids.includes(t.id)}
                        deshabilitada={deshabilitada}
                        onCambiar={() => onCambiar({ ...seleccion, tipologia_ids: alternarId(seleccion.tipologia_ids, t.id) })}
                    >
                        {t.nombre}
                    </Casilla>
                ))}
            </fieldset>
        </>
    );
};

/**
 * Panel de configuración. El catálogo SGR (`catalogo`) llega solo cuando el expediente lo
 * necesita: mientras es null el panel SGR dice que carga y, si falló (`errorCatalogo`),
 * ofrece reintentar en vez de las opciones.
 */
const ExpedienteConfiguracion = ({
    catalogo, errorCatalogo, onReintentarCatalogo,
    expediente, seleccion, deshabilitada, recalculando, onCambiar, disponibles, errorPlantillas, onReintentarPlantillas,
}) => {
    const { configuracion, progreso } = expediente;
    // Con la lista y con las del expediente: si la lista no cargó, el panel SGR igual sigue a la selección.
    const conSgr = seleccionConSgr(seleccion, disponibles, expediente.plantillas);

    return (
        <aside className="exv-config" aria-label="Configuración del expediente">
            <div className="exv-config-head">
```

En `resources/js/components/ExpedienteConfiguracion.jsx`, reemplazar (3 de 3):

```jsx
            {conSgr && (
                <div className="exv-panel-sgr" role="group" aria-labelledby="exv-panel-sgr-titulo">
                    <h3 id="exv-panel-sgr-titulo" className="exv-panel-sgr-titulo">Configuración SGR</h3>
                    <fieldset>
                        <legend>Fase del proyecto</legend>
                        <div className="exv-seg">
                            {FASES.map((fase) => (
                                <label key={fase.valor} className={seleccion.fase_sgr === fase.valor ? 'activa' : ''}>
                                    <input
                                        type="radio"
                                        name="fase_sgr"
                                        value={fase.valor}
                                        checked={seleccion.fase_sgr === fase.valor}
                                        disabled={deshabilitada}
                                        onChange={() => onCambiar({ ...seleccion, fase_sgr: fase.valor })}
                                    />
                                    {fase.romano}
                                    <span className="exv-seg-tag">{fase.corta}</span>
                                </label>
                            ))}
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Componentes del proyecto</legend>
                        {componentes.map((c) => (
                            <Casilla
                                key={c.id}
                                marcada={seleccion.componente_ids.includes(c.id)}
                                deshabilitada={deshabilitada}
                                onCambiar={() => onCambiar({ ...seleccion, componente_ids: alternarId(seleccion.componente_ids, c.id) })}
                            >
                                {c.nombre}
                            </Casilla>
                        ))}
                    </fieldset>

                    <fieldset>
                        <legend>Sector de inversión <small>(uno o varios)</small></legend>
                        {sectores.map((s) => (
                            <Casilla
                                key={s.id}
                                marcada={seleccion.sector_ids.includes(s.id)}
                                deshabilitada={deshabilitada}
                                onCambiar={() => onCambiar(alternarSector(seleccion, s.id, catalogo))}
                            >
                                {s.nombre}
                                {s.fuente && <span className="exv-opt-sub">Acuerdo 12/2024</span>}
                            </Casilla>
                        ))}
                    </fieldset>

                    {sectoresMarcados.length > 0 && (
                        <fieldset className="exv-tipos">
                            <legend>Tipo de proyecto <small>(marca los que apliquen)</small></legend>
                            {sectoresMarcados.map((sector) => (
                                <div key={sector.id} className="exv-tipo-grupo">
                                    <div className="exv-tipo-titulo">{sector.nombre}</div>
                                    {tiposDe(sector).map((t) => (
                                        <Casilla
                                            key={t.id}
                                            marcada={seleccion.tipo_ids.includes(t.id)}
                                            deshabilitada={deshabilitada}
                                            onCambiar={() => onCambiar({ ...seleccion, tipo_ids: alternarId(seleccion.tipo_ids, t.id) })}
                                        >
                                            {t.nombre}
                                        </Casilla>
                                    ))}
                                </div>
                            ))}
                        </fieldset>
                    )}

                    <fieldset>
                        <legend>Tipologías y fuentes especiales</legend>
                        {tipologias.map((t) => (
                            <Casilla
                                key={t.id}
                                marcada={seleccion.tipologia_ids.includes(t.id)}
                                deshabilitada={deshabilitada}
                                onCambiar={() => onCambiar({ ...seleccion, tipologia_ids: alternarId(seleccion.tipologia_ids, t.id) })}
                            >
                                {t.nombre}
                            </Casilla>
                        ))}
                    </fieldset>
                </div>
            )}
        </aside>
```

por:

```jsx
            {conSgr && (
                <div className="exv-panel-sgr" role="group" aria-labelledby="exv-panel-sgr-titulo">
                    <h3 id="exv-panel-sgr-titulo" className="exv-panel-sgr-titulo">Configuración SGR</h3>
                    {catalogo ? (
                        <OpcionesSgr
                            catalogo={catalogo}
                            configuracion={configuracion}
                            seleccion={seleccion}
                            deshabilitada={deshabilitada}
                            onCambiar={onCambiar}
                        />
                    ) : errorCatalogo ? (
                        <>
                            <p className="exv-config-aviso" role="alert">No se pudo cargar el catálogo SGR.</p>
                            <button type="button" className="exv-mini" onClick={onReintentarCatalogo}>Reintentar</button>
                        </>
                    ) : (
                        <p className="exv-config-nota" role="status">Cargando el catálogo SGR…</p>
                    )}
                </div>
            )}
        </aside>
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (1 de 6):

```jsx
    textoPlantillas,
} from '../utils/expedienteChecklist';
import {
    AVISO_SIN_SECTOR, avisoIncompatibles, hayCompatibleSgr, seleccionParaNuevo, textoPlantillasNuevo,
} from '../utils/plantillasExpediente';
import { mensajeApi } from '../utils/plantillasChecklist';
import { avisarError } from '../utils/avisosPlantillas';
```

por:

```jsx
    textoPlantillas,
} from '../utils/expedienteChecklist';
import {
    AVISO_SIN_SECTOR, avisoIncompatibles, hayCompatibleSgr, necesitaCatalogoSgr, seleccionParaNuevo, textoPlantillasNuevo,
} from '../utils/plantillasExpediente';
import { mensajeApi } from '../utils/plantillasChecklist';
import { avisarError } from '../utils/avisosPlantillas';
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (2 de 6):

```jsx
    const navigate = useNavigate();

    const [datosProyecto, setDatosProyecto] = useState(null);
    const [catalogo, setCatalogo] = useState(null);
    const [expediente, setExpediente] = useState(null);
    const [seleccion, setSeleccion] = useState(null);
    const [recalculando, setRecalculando] = useState(false);
```

por:

```jsx
    const navigate = useNavigate();

    const [datosProyecto, setDatosProyecto] = useState(null);
    // Catálogo SGR (sectores, tipos, componentes y tipologías): solo lo usa lo propio de la SGR.
    const [catalogo, setCatalogo] = useState(null);
    const [errorCatalogo, setErrorCatalogo] = useState(false);
    const [expediente, setExpediente] = useState(null);
    const [seleccion, setSeleccion] = useState(null);
    const [recalculando, setRecalculando] = useState(false);
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (3 de 6):

```jsx
        let vigente = true;
        (async () => {
            try {
                const [, respuestaCatalogo] = await Promise.all([cargarProyecto(), axios.get('/catalogo-requisitos/vigente')]);
                if (vigente) setCatalogo(respuestaCatalogo.data);
            } catch (error) {
                if (vigente) {
                    setErrorCarga(error?.response?.status === 404 ? 'El proyecto no existe.' : mensajeApi(error));
```

por:

```jsx
        let vigente = true;
        (async () => {
            try {
                await cargarProyecto();
            } catch (error) {
                if (vigente) {
                    setErrorCarga(error?.response?.status === 404 ? 'El proyecto no existe.' : mensajeApi(error));
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (4 de 6):

```jsx

    useEffect(() => () => clearTimeout(envio.current.temporizador), []);

    // Sin la lista, el checklist igual funciona: solo no se puede elegir responsable.
    useEffect(() => {
        let vigente = true;
```

por:

```jsx

    useEffect(() => () => clearTimeout(envio.current.temporizador), []);

    // El catálogo SGR se pide una sola vez por apertura de la pantalla, y solo cuando hace falta.
    // Si falla, la pantalla sigue y el panel SGR ofrece reintentar. Solo vale la última petición,
    // y ninguna después de cerrar la pantalla.
    const peticionCatalogo = useRef(0);
    const catalogoPedido = useRef(false);
    const cargarCatalogo = useCallback(async () => {
        catalogoPedido.current = true;
        const numero = ++peticionCatalogo.current;
        setErrorCatalogo(false);
        try {
            const { data } = await axios.get('/catalogo-requisitos/vigente');
            if (numero === peticionCatalogo.current) setCatalogo(data);
        } catch {
            if (numero === peticionCatalogo.current) setErrorCatalogo(true);
        }
    }, []);
    const necesitaCatalogo = necesitaCatalogoSgr(expediente, seleccion, disponibles);
    useEffect(() => {
        if (necesitaCatalogo && !catalogoPedido.current) cargarCatalogo();
    }, [necesitaCatalogo, cargarCatalogo]);
    useEffect(() => () => { peticionCatalogo.current += 1; }, []);

    // Sin la lista, el checklist igual funciona: solo no se puede elegir responsable.
    useEffect(() => {
        let vigente = true;
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (5 de 6):

```jsx
        );
    }

    const cargado = datosProyecto && catalogo && (!idActual || (expediente && seleccion));
    // Lo propio de regalías solo se muestra si el expediente tiene una plantilla con motor sgr.
    const conSgr = Boolean(expediente?.tiene_sgr);
    const a12 = expediente && conSgr ? sectoresConFuenteA12(expediente.configuracion.sectores) : [];
```

por:

```jsx
        );
    }

    const cargado = datosProyecto && (!idActual || (expediente && seleccion));
    // Lo propio de regalías solo se muestra si el expediente tiene una plantilla con motor sgr.
    const conSgr = Boolean(expediente?.tiene_sgr);
    const a12 = expediente && conSgr ? sectoresConFuenteA12(expediente.configuracion.sectores) : [];
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (6 de 6):

```jsx
                                {panelVisible && (
                                    <ExpedienteConfiguracion
                                        catalogo={catalogo}
                                        expediente={expediente}
                                        seleccion={seleccion}
                                        deshabilitada={!editable}
```

por:

```jsx
                                {panelVisible && (
                                    <ExpedienteConfiguracion
                                        catalogo={catalogo}
                                        errorCatalogo={errorCatalogo}
                                        onReintentarCatalogo={cargarCatalogo}
                                        expediente={expediente}
                                        seleccion={seleccion}
                                        deshabilitada={!editable}
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (1 de 1):

```js
/**
 * Sectores marcados sin ningún tipo elegido, pudiendo acotarse: cargan todos sus
 * requisitos, incluidos los que no le apliquen al proyecto, y eso distorsiona el avance.
 */
export const sectoresSinTipo = (seleccion, catalogo) =>
    catalogo.sectores
        .filter((s) => seleccion.sector_ids.includes(s.id))
        .filter((s) => (s.tipos ?? []).length > 0 && !s.tipos.some((t) => seleccion.tipo_ids.includes(t.id)))
        .map((s) => s.nombre);
```

por:

```js
/**
 * Sectores marcados sin ningún tipo elegido, pudiendo acotarse: cargan todos sus
 * requisitos, incluidos los que no le apliquen al proyecto, y eso distorsiona el avance.
 * Sin catálogo (todavía cargando, o falló) no se sabe: no avisa de ninguno.
 */
export const sectoresSinTipo = (seleccion, catalogo) =>
    (catalogo?.sectores ?? [])
        .filter((s) => seleccion.sector_ids.includes(s.id))
        .filter((s) => (s.tipos ?? []).length > 0 && !s.tipos.some((t) => seleccion.tipo_ids.includes(t.id)))
        .map((s) => s.nombre);
```

En `resources/js/utils/plantillasExpediente.js`, reemplazar (1 de 1):

```js
/** Si alguna de las plantillas elegidas tiene motor sgr. Sirve la lista de disponibles o la del expediente. */
export const incluyeSgr = (plantillaIds, plantillas) => (plantillaIds ?? []).some((id) => esSgr(buscar(plantillas, id)));

/** Si entre las disponibles hay una plantilla sgr que hoy se ofrece al proyecto. */
export const hayCompatibleSgr = (disponibles) => (disponibles ?? []).some((p) => esSgr(p) && seOfrece(p));
```

por:

```js
/** Si alguna de las plantillas elegidas tiene motor sgr. Sirve la lista de disponibles o la del expediente. */
export const incluyeSgr = (plantillaIds, plantillas) => (plantillaIds ?? []).some((id) => esSgr(buscar(plantillas, id)));

/**
 * Si la selección en curso incluye una plantilla sgr. Se busca en la lista de disponibles y en
 * las del expediente: si la lista no cargó, igual se sabe.
 */
export const seleccionConSgr = (seleccion, disponibles, plantillasExpediente) =>
    incluyeSgr(seleccion?.plantilla_ids, [...(disponibles?.plantillas ?? []), ...(plantillasExpediente ?? [])]);

/**
 * Si la pantalla necesita el catálogo SGR (sectores, tipos, componentes y tipologías): solo
 * cuando el expediente abierto tiene una plantilla sgr o la selección en curso incluye una.
 */
export const necesitaCatalogoSgr = (expediente, seleccion, disponibles) =>
    Boolean(expediente) && (Boolean(expediente.tiene_sgr) || seleccionConSgr(seleccion, disponibles, expediente.plantillas));

/** Si entre las disponibles hay una plantilla sgr que hoy se ofrece al proyecto. */
export const hayCompatibleSgr = (disponibles) => (disponibles ?? []).some((p) => esSgr(p) && seOfrece(p));
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/expedienteChecklist.test.js resources/js/utils/plantillasExpediente.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 643 passed.

Run: `npm test`
Expected: 343 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/js/components/ExpedienteConfiguracion.jsx resources/js/components/ExpedienteViabilizacion.jsx resources/js/utils/expedienteChecklist.js resources/js/utils/expedienteChecklist.test.js resources/js/utils/plantillasExpediente.js resources/js/utils/plantillasExpediente.test.js
git commit -m "fix: load the SGR catalog only when the expediente needs it"
```

---

### Tarea 11: Compilar para producción

- [ ] **Paso 1: Compilar**

Run: `npm run production`
Expected: `Compiled Successfully`, sin errores ni avisos.

Justo antes de comitear, comprobar que `public/js/app.js` es el de producción: minificado y sin comentarios `/***/`. Si `npm run watch` lo reescribió, compilar otra vez.

- [ ] **Paso 2: Commit**

```bash
git add public/js/app.js public/js/app.js.map public/css/app.css public/mix-manifest.json
git commit -m "build: production bundle for stage 6"
```

Si `public/css/app.css` o `public/js/app.js.map` no cambiaron o no existen, se omiten de `git add`.

---

### Tarea 12: Verificación en la copia descartable

Usa la copia y la foto de la tarea 1. Nunca `gest_pro`.

- [ ] **Paso 1: Migrar la copia y comparar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

Expected: se aplica `2026_11_09_100010_drop_catalogo_version_de_expedientes`.

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_etapa6.php" "$TMP_DIR/e6_despues.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_etapa6.php" "$TMP_DIR/e6_antes.json" "$TMP_DIR/e6_despues.json"
```

Expected: `SIN DIFERENCIAS`. Cualquier diferencia detiene la etapa.

Run: `/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SHOW COLUMNS FROM proyectos_expedientes LIKE 'catalogo_version_id'"`
Expected: sin resultados.

- [ ] **Paso 2: Revertir y comparar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=1 --force
```

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT e.id, e.catalogo_version_id, ep.catalogo_version_id AS en_plantillas FROM proyectos_expedientes e LEFT JOIN expediente_plantillas ep ON ep.expediente_id = e.id; SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = 'gestpro_verif_tmp' AND TABLE_NAME = 'proyectos_expedientes';"
```

Expected: cada expediente con su versión SGR en las dos columnas, y `proyectos_expedientes_catalogo_version_id_foreign` con `RESTRICT`.

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_etapa6.php" "$TMP_DIR/e6_rollback.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_etapa6.php" "$TMP_DIR/e6_antes.json" "$TMP_DIR/e6_rollback.json"
```

Expected: `SIN DIFERENCIAS`. El código nuevo no lee la columna, así que la API funciona con ella o sin ella.

- [ ] **Paso 3: Comprobar que la migración se detiene si perdería una versión**

Con la columna restaurada, se escribe en un expediente una versión que no está en sus plantillas:

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "INSERT INTO catalogo_versiones (plantilla_id, numero, estado, vigente, created_at, updated_at) VALUES ((SELECT id FROM plantillas_checklist WHERE clave = 'sgr'), 99, 'borrador', 0, NOW(), NOW()); SET @v = LAST_INSERT_ID(); SET @e = (SELECT MIN(id) FROM proyectos_expedientes); UPDATE proyectos_expedientes SET catalogo_version_id = @v WHERE id = @e; SELECT @e AS expediente, @v AS version;"
```

Run: `DB_DATABASE=gestpro_verif_tmp php artisan migrate --force`
Expected: falla con "No se puede migrar: el expediente N tiene una versión de catálogo que no figura en expediente_plantillas y se perdería. Revíselo antes de migrar." La columna sigue ahí.

Deshacer la prueba (la versión SGR de los expedientes es la de `expediente_plantillas`):

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "UPDATE proyectos_expedientes e SET catalogo_version_id = (SELECT ep.catalogo_version_id FROM expediente_plantillas ep JOIN catalogo_versiones v ON v.id = ep.catalogo_version_id JOIN plantillas_checklist p ON p.id = v.plantilla_id WHERE ep.expediente_id = e.id AND p.motor = 'sgr' LIMIT 1); DELETE FROM catalogo_versiones WHERE numero = 99 AND estado = 'borrador';"
```

- [ ] **Paso 4: Reintentar con la clave foránea ya quitada y comparar**

Simula una migración que se cortó después de quitar la clave foránea:

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "ALTER TABLE proyectos_expedientes DROP FOREIGN KEY proyectos_expedientes_catalogo_version_id_foreign;"
```

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_etapa6.php" "$TMP_DIR/e6_remigrado.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_etapa6.php" "$TMP_DIR/e6_antes.json" "$TMP_DIR/e6_remigrado.json"
```

Expected: la migración termina y `SIN DIFERENCIAS`.

- [ ] **Paso 5: Simular el comando de expedientes faltantes**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan expedientes:crear-faltantes --simular
```

Expected: `Se crearían N expedientes.` (0 con los datos actuales). Anotarlo para el informe; sin `--simular` solo se ejecuta con autorización del usuario.

- [ ] **Paso 6: Publicar una versión 2 de la SGR en la copia**

Crear `$TMP_DIR/gestpro_version2_etapa6.php`. Solo funciona sobre la copia:

```php
<?php
// Solo para la copia gestpro_verif_tmp: publica la versión 2 de la plantilla SGR con tres cambios.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Services\Viabilizacion\CatalogoService;
use Illuminate\Support\Facades\DB;

if (DB::getDatabaseName() !== 'gestpro_verif_tmp') {
    exit("Abortado: la base es " . DB::getDatabaseName() . "\n");
}

$servicio = app(CatalogoService::class);
$sgr = PlantillaChecklist::where('clave', 'sgr')->firstOrFail();
$borrador = $servicio->crearBorrador('Ensayo etapa 6: a1 con texto nuevo, sin a2, con a99', $sgr);

$a1 = RequisitoCatalogo::where('catalogo_version_id', $borrador->id)->where('clave', 'a1')->firstOrFail();
$datos = $a1->only(['clave', 'seccion', 'fuente', 'sector_id', 'tipo_id', 'subtitulo', 'codigo', 'texto', 'nota', 'condicion', 'orden', 'activo',
    'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision', 'soporte_requerido',
    'referencia_normativa', 'condicion_descripcion', 'requiere_aprobacion_no_aplica']);
$servicio->guardarRequisito($borrador, [...$datos, 'texto' => $datos['texto'] . ' (texto actualizado en la versión 2)'], $a1);

$a2 = RequisitoCatalogo::where('catalogo_version_id', $borrador->id)->where('clave', 'a2')->firstOrFail();
$servicio->eliminarRequisito($borrador, $a2);

$servicio->guardarRequisito($borrador, [...$datos, 'clave' => 'a99', 'codigo' => '4.1.2.1.1 · 99',
    'texto' => 'Requisito nuevo de la versión 2 para el ensayo', 'orden' => $datos['orden'] + 1]);

$servicio->publicar($borrador);
echo "Publicada la versión {$borrador->numero} (id {$borrador->id})\n";
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_version2_etapa6.php"
```

Expected: `Publicada la versión 2 (id N)`.

- [ ] **Paso 7: Probar en el navegador sobre la copia**

Compilar en desarrollo (`npx mix`) si `public/` no tiene el código de esta etapa, y arrancar el servidor desde `public/`, en un puerto libre (`artisan serve` recargaría `gest_pro` desde `.env`; comprobar con `netstat -ano | grep 8796` que nadie más escucha en ese puerto):

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8796 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Crear usuarios de prueba solo en la copia; nunca usar sesiones de usuarios reales de `gest_pro`:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'verif@gestpro.test', 'rol' => App\Enums\Rol::Administrador]); App\Models\User::factory()->create(['email' => 'gestor@gestpro.test', 'rol' => App\Enums\Rol::Gestor]);"
```

La contraseña es la predeterminada de `UserFactory`. Entrar por `http://127.0.0.1:8796/GestPro/`. Como Administrador:

1. Un borrador con la SGR y algún requisito `a1` o `a2` cumplido (el #2 con los datos actuales) muestra "Plantillas SGR (v1)" y "SGR versión 2 disponible" con el botón "Aplicar versión 2".
2. La vista previa lista:
   - se agrega `a99`;
   - deja de aplicar `a2`, con "conserva su trabajo" si estaba cumplido;
   - cambia el texto de `a1`;
   - cuántos siguen igual y el avance antes y después.

   "Cancelar" no cambia nada.
3. "Aplicar versión":
   - "SGR (v2)";
   - `a1` conserva su estado con el texto nuevo;
   - `a99` aparece pendiente;
   - el aviso de requisitos con trabajo guardado cuenta `a2`.
4. "Historial" muestra "Aplicó la versión 2 de SGR (antes 1)" y las marcas recientes, con "Cargar más" si hay más de 50. Marcar un requisito con el historial abierto lo pone al día.
5. Un expediente radicado no muestra el aviso de versión nueva ni el botón.
6. Tablero → Expedientes:
   - el filtro de fuente lista las fuentes de los proyectos y "Por definir", y las tarjetas cuentan lo filtrado;
   - después de asignar un responsable a un requisito pendiente, el filtro de responsable lo ofrece y la columna dice "Pendientes de …".
7. Editar un proyecto que no está en Formulación, tiene fuente y no tiene expediente, y pasarlo a Formulación: nace su expediente sin configurar. Si no hay ninguno así en la copia, se omite y se anota.
8. La consola del navegador no muestra errores nuevos.

Como Gestor: los pasos 1 a 4 funcionan.

- [ ] **Paso 8: Limpiar**

Detener el servidor, eliminar la copia y devolver `public/` a lo comiteado si se compiló en desarrollo (`git checkout public/js public/css public/mix-manifest.json`):

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 9: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas, 643.

Run: `npm test`
Expected: pasan todas, 343.

---

### Tarea 13: Notas del diseño

**Files:**
- Modify: `docs/superpowers/specs/2026-11-06-plantillas-checklist-design.md`

- [ ] **Paso 1: Corregir la fila de la migración 11**

En `docs/superpowers/specs/2026-11-06-plantillas-checklist-design.md`, reemplazar:

```markdown
| Restaura la columna desde la plantilla principal |
```

por:

```markdown
| Se detiene si alguna versión de la columna no figura en `expediente_plantillas`. El rollback restaura la columna con la versión de la plantilla con motor `sgr`, o null |
```

- [ ] **Paso 2: Cerrar los pendientes de la etapa 5 que resolvió esta etapa**

Reemplazar:

```markdown
  - **Orden en la SGR:** el tope de `orden` (9 999 999) no cuenta la sección. Una fila SGR de la sección C con un orden muy alto puede quedar después de la plantilla siguiente; esto afecta al Excel y a la impresión.
```

por:

```markdown
  - **Orden en la SGR (resuelto en la etapa 6):** el orden admite hasta 99 999 y el servicio lo acota dentro de su sección o de su plantilla.
```

Reemplazar:

```markdown
  - **Entrar a Formulación:** si un proyecto pasa a Formulación al editarlo, no se crea el expediente automático. Solo se crea al darlo de alta o al salir de "por definir". Falta decidir si también debe crearse en ese caso.
```

por:

```markdown
  - **Entrar a Formulación (resuelto en la etapa 6):** por decisión del usuario, pasar un proyecto a Formulación al editarlo también crea el expediente automático, si le corresponde.
```

Reemplazar:

```markdown
- **Pantalla:** el expediente todavía carga `/catalogo-requisitos/vigente` al abrirse, aunque no tenga la SGR. Si algún día no hay versión SGR vigente, la pantalla fallaría.
```

por:

```markdown
- **Pantalla (resuelto en la etapa 6):** el expediente pide `/catalogo-requisitos/vigente` solo si tiene o elige una plantilla SGR, y sobrevive a su fallo.
```

- [ ] **Paso 3: Agregar las notas de la etapa 6**

Reemplazar:

```markdown
  - **Bloqueo de confirmar:** en confirmar la configuración, el `bloquear()` inicial es la única defensa frente a un PUT simultáneo que quite todas las plantillas. No hay que moverlo.
```

por:

```markdown
  - **Bloqueo de confirmar:** en confirmar la configuración, el `bloquear()` inicial es la única defensa frente a un PUT simultáneo que quite todas las plantillas. No hay que moverlo.

**Notas de la etapa 6 (cierre):**

- **Versión en el expediente:** desde la migración 11, la versión de cada plantilla vive solo en `expediente_plantillas`. `CatalogoVersion::expedientes()` cuenta por esa tabla, así que el historial de versiones de una plantilla cuenta también los expedientes donde es complementaria.
- **Aplicar una versión nueva:**
  - las filas se reconocen por `clave` y se re-anclan al requisito nuevo, también las retiradas;
  - si dos filas quedan con la misma llave, se conserva la que tiene trabajo;
  - la vista previa es la misma aplicación revertida y bloquea el expediente mientras dura.
- **Pendientes que el usuario dejó fuera, sin prisa:**
  - el endpoint liviano de avisos de duplicados;
  - los pendientes de la administración de plantillas de la etapa 4;
  - las flechas de orden de la SGR que cruzan secciones.
- **Pendientes que encontró la revisión, menores:**
  - **Configuración cambiada:** la vista previa protege la versión, no la configuración; si otra persona la cambia antes de confirmar, se aplica sobre lo nuevo sin aviso.
  - **Expediente sin configurar:** el historial no se ve todavía.
  - **Panel abierto:** el aviso de versión nueva no aparece con el panel de configuración abierto.
```

- [ ] **Paso 4: Commit**

```bash
git add docs/superpowers/specs/2026-11-06-plantillas-checklist-design.md
git commit -m "docs: close stage 6 notes in the checklist templates design"
```

- [ ] **Paso 5: Informar al usuario y pedir validación**

Informar:
- los totales de pruebas;
- el resultado de las comparaciones (migrar, revertir, reintento y volver a migrar);
- la guarda de la migración;
- el `--simular`;
- lo revisado en el navegador.

Pedir dos decisiones por separado: si valida la etapa y si autoriza aplicar la migración en `gest_pro`.

---

### Tarea 14: Aplicar en `gest_pro` (solo con autorización)

No ejecutar esta tarea sin un sí explícito del usuario a este paso. Si la comparación muestra diferencias, restaurar el respaldo: no usar `migrate:rollback`.

- [ ] **Paso 1: Respaldar**

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro > "$TMP_DIR/gest_pro_antes_etapa_6.sql"
```

Expected: el archivo existe y no está vacío.

- [ ] **Paso 2: Tomar la foto antes de migrar**

El código nuevo no lee la columna, así que la foto completa funciona antes de migrar:

```bash
php "$TMP_DIR/gestpro_foto_etapa6.php" "$TMP_DIR/e6_real_antes.json" gest_pro
```

- [ ] **Paso 3: Migrar hacia adelante**

Run: `php artisan migrate --force`
Expected: se aplica `2026_11_09_100010_drop_catalogo_version_de_expedientes`. Si se detiene con el mensaje de la guarda, no tocar nada y avisar al usuario con los expedientes que nombra.

- [ ] **Paso 4: Comparar**

```bash
php "$TMP_DIR/gestpro_foto_etapa6.php" "$TMP_DIR/e6_real_despues.json" gest_pro
```

```bash
php "$TMP_DIR/gestpro_comparar_etapa6.php" "$TMP_DIR/e6_real_antes.json" "$TMP_DIR/e6_real_despues.json"
```

Expected: `SIN DIFERENCIAS`.

- [ ] **Paso 5: Informar**

Dar el resultado y la ruta completa del respaldo, y avisar que está en una carpeta temporal. `expedientes:crear-faltantes` sin `--simular` se ejecuta solo si el usuario lo pide aparte.

---

## Criterio de la etapa

La etapa está lista cuando, según el diseño:

- un expediente en borrador aplica la versión nueva de una plantilla con vista previa, y lo que sigue conserva su trabajo;
- el expediente tiene una pantalla de historial;
- el tablero se filtra por fuente y responsable;
- la migración 11 retira la columna sin cambiar lo que entrega la API de ningún expediente;
- se cumplen las dos decisiones del usuario: Formulación y los pendientes elegidos.

Con esto se completan las seis etapas del diseño.
