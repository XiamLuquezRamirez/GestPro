# Plantillas de checklist, etapa 3: seguimiento del requisito. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** que cada requisito del checklist tenga estado, responsable, "no aplica" con justificación y aprobación, e historial, sin cambiar las cifras de los expedientes SGR existentes.

**Arquitectura:** tres migraciones agregan los campos de seguimiento al catálogo y al checklist, y la tabla `checklist_historial`. `ProyectoRequisito` concentra la única definición de qué cuenta en el avance, que usan el detalle, el tablero y la radicación. `HistorialChecklist` registra cada cambio y conserva los soportes reemplazados; `NoAplicaRequisito` lleva el flujo del "no aplica". La pantalla del expediente agrega estas acciones a cada requisito.

**Tecnologías:** Laravel 12, PHP 8.2, MariaDB 10.4, React 18 con Laravel Mix, PHPUnit sobre SQLite en memoria, Vitest.

**Diseño:** `docs/superpowers/specs/2026-11-06-plantillas-checklist-design.md`: migraciones 7 a 9 de la sección 4, "Estados del requisito" y "No aplica" de la sección 2, y la fila de `ExpedienteChecklist.jsx` de la sección 6.

**Estado de partida:** rama `feature/plantillas-checklist`, etapas 1 y 2 validadas y aplicadas en `gest_pro`, 280 pruebas PHP y 134 JS pasando.

---

## Reglas de ejecución

- **Nada de esto se aplica a `gest_pro` sin autorización del usuario.** Nunca se usa `migrate:rollback` allí: si hay que volver atrás, se restaura el respaldo.
- Commits autorizados solo en `feature/plantillas-checklist`. Sin push. Mensajes en inglés, como el historial, terminados con la línea `Co-Authored-By` del modelo que comitea.
- Nomenclatura en español sin tildes para tablas, columnas y métodos nuevos.
- Pruebas: `php artisan test` sin definir `DB_DATABASE` (usa SQLite en memoria) y `npm test`.
- **Editar con la herramienta Edit, no con `sed`:** `sed` eliminó barras invertidas de líneas `use` en la etapa 1. Cada reemplazo de este plan aparece una sola vez en su archivo; si no lo encuentras tal cual, detente y avisa.
- El usuario suele tener `npm run watch` corriendo, que reescribe `public/`. Solo la tarea 7 comitea `public/`, después de `npm run production`.
- `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio. Hay que definirlo en cada comando, porque el estado de la terminal no se conserva.

## Fuera de esta etapa

- Resolver un requisito "por definir": nace solo con motor `simple`, en la etapa 5. Aquí ya cuenta como pendiente y admite "no aplica".
- Administrar en pantalla los campos nuevos del catálogo (categoría, cargos, aprobación del "no aplica"): etapa 4. Mientras tanto, los 207 requisitos SGR quedan obligatorios y sin aprobación del "no aplica".
- La pantalla de historial del expediente y los filtros por responsable: etapa 6. Aquí el historial se ve por requisito.

## Lo que ya se ensayó

El plan se ensayó completo en un clon descartable fuera del repositorio, y este documento se generó con el código de ese ensayo: un script comprobó que aplicar sus bloques sobre la rama reproduce el ensayo exactamente.

- Pruebas: 325 PHP y 140 JS.
- En una copia de `gest_pro`: lo que la API entrega de los 10 expedientes y sus 286 filas no cambió al migrar, al revertir ni al volver a migrar. Las claves foráneas e índices quedaron como se esperaba.
- En el navegador, sobre esa copia: "no aplica" sin y con aprobación, aprobar, retirar, responsable e historial, sin errores de consola.

**Cuidado con SQLite.** Cambiar una columna reconstruye la tabla y, dentro de la transacción de `RefreshDatabase`, eso afecta a las filas hijas. Por eso las pruebas que bajan y suben la migración del catálogo no crean filas de checklist. En MariaDB no ocurre: la tarea 8 lo comprueba con datos reales.

## Decisiones de este plan

- **Soportes:** reemplazar o eliminar un soporte ya no borra el archivo. Queda en el historial y se descarga por el id del registro; la ruta nunca sale en la API. Eliminar un expediente en borrador sí borra todos sus archivos, como avisa la pantalla, y su historial queda.
- **"No aplica":** solo sobre un requisito no cumplido. Si el requisito pide aprobación, queda "por aprobar" y sigue contando como pendiente. Revisa otra persona, salvo un Administrador, que puede revisar lo que él mismo marcó, y queda registrado. Quien marcó puede retirarlo.
- **Responsable:** se elige del catálogo `responsable`. Uno inactivo solo se conserva si la fila ya lo tenía.
- **Rollback:** las tres migraciones se detienen con un mensaje claro si perderían datos, como pide la sección 8 del diseño.
- **Excel:** la columna "Responsable" pasa a ser la persona asignada; quien marcó el cumplimiento va en "Marcado por". Se agregan "Estado" y "Justificación de no aplica".

## Archivos

| Archivo | Responsabilidad |
|---|---|
| `database/migrations/2026_11_06_100006_add_seguimiento_to_requisitos_catalogo_table.php` | Campos de seguimiento del catálogo |
| `database/migrations/2026_11_06_100007_add_seguimiento_to_proyectos_requisitos_table.php` | Estado, "no aplica", carácter y responsable de cada fila |
| `database/migrations/2026_11_06_100008_create_checklist_historial_table.php` | Historial del checklist |
| `app/Models/ProyectoRequisito.php` | Estados y la única definición de qué cuenta |
| `app/Models/ChecklistHistorial.php` | Registro del historial |
| `app/Services/Viabilizacion/HistorialChecklist.php` | Registrar y presentar el historial |
| `app/Services/Viabilizacion/NoAplicaRequisito.php` | Flujo del "no aplica" |
| `app/Services/Viabilizacion/ExpedienteService.php` | Avance, responsable, soportes conservados y registros |
| `app/Http/Controllers/NoAplicaRequisitoController.php` | Rutas del "no aplica" |
| `app/Http/Controllers/ExpedienteController.php` | Historial por requisito y descarga de soportes conservados |
| `resources/js/utils/expedienteChecklist.js` | Estados, permisos de acción, historial y Excel |
| `resources/js/components/ExpedienteChecklist.jsx` | Acciones de cada requisito |
| `resources/js/components/ExpedienteHistorialRequisito.jsx` | Historial de un requisito |
| `resources/js/components/ExpedienteViabilizacion.jsx` | Llamadas a la API y diálogos |

---

### Tarea 1: Punto de partida y foto con el código actual

La foto de la API tiene que tomarse **antes de cambiar código**, porque después el servicio ya espera las columnas nuevas. La copia `gestpro_verif_tmp` se conserva hasta la tarea 8.

- [ ] **Paso 1: Comprobar la rama, el estado y las pruebas**

Run: `git branch --show-current`
Expected: `feature/plantillas-checklist`

Run: `git status --short`
Expected: sin salida. Si aparece solo `public/`, es `npm run watch`: no tocarlo.

Run: `php artisan test`
Expected: pasan todas, 280. Si alguna falla, detenerse y avisar.

Run: `npm test`
Expected: pasan todas, 134.

- [ ] **Paso 2: Crear la copia descartable**

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE IF EXISTS gestpro_verif_tmp; CREATE DATABASE gestpro_verif_tmp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro | /c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp
```

- [ ] **Paso 3: Crear los dos scripts de comprobación**

Crear `$TMP_DIR/gestpro_foto_etapa3.php`. Toma las tablas y, salvo con `--solo-tablas`, lo que la API entrega de cada expediente. Se detiene si la base no es la esperada:

```php
<?php
// Foto de los expedientes para comparar antes y después de migrar.
// Uso, desde la raíz del repositorio:
//   php gestpro_foto_etapa3.php <archivo.json> <base esperada> [--solo-tablas]
// --solo-tablas lee las tablas sin pasar por el servicio: sirve antes de migrar con el
// código nuevo, que ya espera las columnas de seguimiento.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProyectoExpediente;
use App\Services\Viabilizacion\ExpedienteService;
use Illuminate\Support\Facades\DB;

if (DB::getDatabaseName() !== $argv[2]) {
    exit('Abortado: la base es ' . DB::getDatabaseName() . ", se esperaba {$argv[2]}\n");
}

$columnasFila = ['id', 'expediente_id', 'requisito_catalogo_id', 'aplica', 'cumplido', 'fecha_cumplido',
    'usuario_cumplido_id', 'observacion', 'soporte_archivo', 'soporte_nombre', 'orden'];
$columnasRequisito = ['id', 'catalogo_version_id', 'clave', 'seccion', 'fuente', 'sector_id', 'tipo_id',
    'subtitulo', 'codigo', 'texto', 'nota', 'condicion', 'orden', 'activo'];

$foto = [
    'tablas' => [
        'expedientes' => DB::table('proyectos_expedientes')->orderBy('id')->get(),
        'filas' => DB::table('proyectos_requisitos')->orderBy('id')->get($columnasFila),
        'requisitos' => DB::table('requisitos_catalogo')->orderBy('id')->get($columnasRequisito),
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
    . (isset($foto['api']) ? ', con API' : ', solo tablas') . "\n";
```

Crear `$TMP_DIR/gestpro_comparar_etapa3.php`. Exige que todo lo de antes siga igual y permite campos nuevos:

```php
<?php
// Comprueba que todo lo que había antes sigue igual después. Lo nuevo (campos que la etapa
// agrega) se permite; un valor distinto o algo que falta, no.
// Uso: php gestpro_comparar_etapa3.php <antes.json> <despues.json>

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

- [ ] **Paso 4: Tomar las fotos de antes, con el código de la etapa 2**

Desde la raíz del repositorio:

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_etapa3.php" "$TMP_DIR/e3_antes.json" gestpro_verif_tmp
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_etapa3.php" "$TMP_DIR/e3_tablas_antes.json" gestpro_verif_tmp --solo-tablas
```

Expected: `Expedientes: 10, filas: 286, con API` y luego `solo tablas`, o las cifras actuales de `gest_pro`.

---

### Tarea 2: Campos de seguimiento en el catálogo

**Files:**
- Create: `database/migrations/2026_11_06_100006_add_seguimiento_to_requisitos_catalogo_table.php`
- Test (create): `tests/Feature/Schema/SeguimientoRequisitoTest.php`
- Modify: `app/Models/RequisitoCatalogo.php`
- Modify: `database/seeders/CatalogoRequisitosSgrSeeder.php`
- Modify: `app/Services/Viabilizacion/CatalogoService.php`
- Test: `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Schema/SeguimientoRequisitoTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class SeguimientoRequisitoTest extends TestCase
{
    use RefreshDatabase;

    private function migracionCatalogo(): object
    {
        return require database_path('migrations/2026_11_06_100006_add_seguimiento_to_requisitos_catalogo_table.php');
    }

    private function versionSgr(): int
    {
        return DB::table('catalogo_versiones')->insertGetId([
            'plantilla_id' => PlantillaChecklist::sgr()->id, 'numero' => 1, 'estado' => 'publicada', 'vigente' => true,
        ]);
    }

    private function requisito(int $version, array $cambios = []): int
    {
        return DB::table('requisitos_catalogo')->insertGetId([
            'catalogo_version_id' => $version, 'clave' => 'a1', 'seccion' => 'A', 'fuente' => 'a12',
            'codigo' => '1', 'texto' => 'Texto', ...$cambios,
        ]);
    }

    // ---------------------------------------------------------------- catálogo

    /*
     * Estas pruebas no crean filas de checklist a propósito. En SQLite, cambiar una columna
     * reconstruye la tabla y, dentro de la transacción de RefreshDatabase, eso afectaría a las
     * filas hijas. En MariaDB no pasa: se comprueba en la copia descartable.
     */
    public function test_migrar_da_los_valores_por_defecto_y_el_codigo_comun_a_los_requisitos(): void
    {
        $migracion = $this->migracionCatalogo();
        $migracion->down();
        $this->assertFalse(Schema::hasColumn('requisitos_catalogo', 'codigo_comun'));
        $id = $this->requisito($this->versionSgr());

        $migracion->up();

        $fila = RequisitoCatalogo::find($id);
        $this->assertSame('a1', $fila->codigo_comun);
        $this->assertSame(RequisitoCatalogo::OBLIGATORIO, $fila->caracter);
        $this->assertFalse($fila->requiere_aprobacion_no_aplica);
        $this->assertNull($fila->categoria);
    }

    public function test_la_migracion_del_catalogo_se_puede_repetir_sin_pisar_un_codigo_comun(): void
    {
        $id = $this->requisito($this->versionSgr(), ['codigo_comun' => 'propio']);

        $this->migracionCatalogo()->up();

        $this->assertSame('propio', DB::table('requisitos_catalogo')->where('id', $id)->value('codigo_comun'));
    }

    public function test_un_requisito_puede_no_tener_seccion_fuente_ni_codigo(): void
    {
        $id = $this->requisito($this->versionSgr(), ['seccion' => null, 'fuente' => null, 'codigo' => null]);

        $this->assertNull(DB::table('requisitos_catalogo')->where('id', $id)->value('seccion'));
    }

    public function test_el_rollback_se_detiene_si_hay_requisitos_sin_seccion(): void
    {
        $this->requisito($this->versionSgr(), ['seccion' => null]);

        try {
            $this->migracionCatalogo()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sin sección, fuente o código', $e->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('requisitos_catalogo', 'codigo_comun'));
    }

    public function test_el_rollback_se_detiene_si_se_perderian_datos_de_seguimiento(): void
    {
        foreach ([['categoria' => 'Técnico'], ['caracter' => 'opcional'], ['requiere_aprobacion_no_aplica' => true]] as $indice => $datos) {
            DB::table('requisitos_catalogo')->delete();
            $version = DB::table('catalogo_versiones')->value('id') ?? $this->versionSgr();
            $this->requisito($version, ['clave' => "r{$indice}", ...$datos]);

            try {
                $this->migracionCatalogo()->down();
                $this->fail('El rollback debía detenerse con ' . json_encode($datos));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('se perderían', $e->getMessage());
            }
        }
    }
}
```

En `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`, reemplazar (1 de 1):

```php
    public function test_la_version_sembrada_pertenece_a_la_plantilla_sgr(): void
```

por:

```php
    public function test_cada_requisito_sembrado_usa_su_clave_como_codigo_comun(): void
    {
        $this->assertSame(207, DB::table('requisitos_catalogo')->whereColumn('codigo_comun', 'clave')->count());
    }

    public function test_un_borrador_copia_los_datos_de_seguimiento(): void
    {
        $datos = [
            'descripcion' => 'Descripción', 'categoria' => 'Técnico', 'tipo_requisito' => 'Documental',
            'caracter' => 'opcional', 'cargo_cumplimiento' => 'Ingeniero', 'cargo_revision' => 'Jurídica',
            'soporte_requerido' => 'Acta firmada', 'referencia_normativa' => 'Art. 1',
            'condicion_descripcion' => 'Si hay predios', 'codigo_comun' => 'comun',
        ];
        DB::table('requisitos_catalogo')->where('clave', 'a1')->update([...$datos, 'requiere_aprobacion_no_aplica' => true]);

        $copia = app(CatalogoService::class)->crearBorrador(null)->requisitos()->where('clave', 'a1')->first();

        foreach ($datos as $columna => $valor) {
            $this->assertSame($valor, $copia->{$columna}, $columna);
        }
        $this->assertTrue($copia->requiere_aprobacion_no_aplica);
    }

    public function test_un_requisito_nuevo_del_sgr_usa_su_clave_como_codigo_comun(): void
    {
        $borrador = app(CatalogoService::class)->crearBorrador(null);

        $this->comoAdmin()->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos", [
            'clave' => 'nuevo', 'seccion' => 'A', 'fuente' => 'a12', 'codigo' => '9.9', 'texto' => 'Nuevo', 'condicion' => 'siempre',
        ])->assertCreated();

        $this->assertSame('nuevo', $borrador->requisitos()->where('clave', 'nuevo')->value('codigo_comun'));
    }

    public function test_la_version_sembrada_pertenece_a_la_plantilla_sgr(): void
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Schema/SeguimientoRequisitoTest.php tests/Feature/Viabilizacion/PlantillasVersionesTest.php`
Expected: FALLAN las 5 pruebas de esquema, porque no existen la migración ni `RequisitoCatalogo::OBLIGATORIO`, y las 3 nuevas de `PlantillasVersionesTest`, porque no hay `codigo_comun` ni el borrador copia los campos.

- [ ] **Paso 3: Implementar**

Crear `database/migrations/2026_11_06_100006_add_seguimiento_to_requisitos_catalogo_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TEXTOS = [
        'descripcion', 'categoria', 'tipo_requisito', 'cargo_cumplimiento', 'cargo_revision',
        'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
    ];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada columna es una sentencia y se puede repetir.
        $this->agregar('descripcion', fn (Blueprint $t) => $t->text('descripcion')->nullable());
        $this->agregar('categoria', fn (Blueprint $t) => $t->string('categoria', 100)->nullable());
        $this->agregar('tipo_requisito', fn (Blueprint $t) => $t->string('tipo_requisito', 100)->nullable());
        $this->agregar('caracter', fn (Blueprint $t) => $t->string('caracter', 20)->default('obligatorio'));
        $this->agregar('cargo_cumplimiento', fn (Blueprint $t) => $t->string('cargo_cumplimiento', 150)->nullable());
        $this->agregar('cargo_revision', fn (Blueprint $t) => $t->string('cargo_revision', 150)->nullable());
        $this->agregar('soporte_requerido', fn (Blueprint $t) => $t->string('soporte_requerido', 255)->nullable());
        $this->agregar('referencia_normativa', fn (Blueprint $t) => $t->string('referencia_normativa', 255)->nullable());
        $this->agregar('condicion_descripcion', fn (Blueprint $t) => $t->text('condicion_descripcion')->nullable());
        $this->agregar('requiere_aprobacion_no_aplica', fn (Blueprint $t) => $t->boolean('requiere_aprobacion_no_aplica')->default(false));
        $this->agregar('codigo_comun', fn (Blueprint $t) => $t->string('codigo_comun', 40)->nullable());

        // Hasta hoy todos los requisitos son del SGR, y allí el código común es la clave.
        DB::table('requisitos_catalogo')->whereNull('codigo_comun')->update(['codigo_comun' => DB::raw('clave')]);

        // Solo el SGR se organiza por sección, fuente y código.
        Schema::table('requisitos_catalogo', function (Blueprint $table) {
            $table->char('seccion', 1)->nullable()->change();
            $table->string('fuente', 5)->nullable()->change();
            $table->string('codigo', 60)->nullable()->change();
        });
    }

    public function down(): void
    {
        $sinSgr = DB::table('requisitos_catalogo')
            ->where(fn ($q) => $q->whereNull('seccion')->orWhereNull('fuente')->orWhereNull('codigo'))
            ->exists();
        if ($sinSgr) {
            throw new RuntimeException('No se puede revertir: hay requisitos sin sección, fuente o código. Elimínelos o restaure un respaldo.');
        }

        $conDatos = DB::table('requisitos_catalogo')
            ->where(function ($q) {
                foreach (self::TEXTOS as $columna) {
                    $q->orWhereNotNull($columna);
                }
                $q->orWhere('caracter', '!=', 'obligatorio')->orWhere('requiere_aprobacion_no_aplica', true);
            })
            ->exists();
        if ($conDatos) {
            throw new RuntimeException('No se puede revertir: hay requisitos con datos de seguimiento que se perderían. Restaure un respaldo.');
        }

        Schema::table('requisitos_catalogo', function (Blueprint $table) {
            $table->char('seccion', 1)->nullable(false)->change();
            $table->string('fuente', 5)->nullable(false)->change();
            $table->string('codigo', 60)->nullable(false)->change();
        });
        Schema::table('requisitos_catalogo', function (Blueprint $table) {
            $table->dropColumn([...self::TEXTOS, 'caracter', 'requiere_aprobacion_no_aplica', 'codigo_comun']);
        });
    }

    private function agregar(string $columna, callable $definicion): void
    {
        if (! Schema::hasColumn('requisitos_catalogo', $columna)) {
            Schema::table('requisitos_catalogo', function (Blueprint $table) use ($definicion) {
                $definicion($table);
            });
        }
    }
};
```

En `app/Models/RequisitoCatalogo.php`, reemplazar (1 de 3):

```php
class RequisitoCatalogo extends Model
{
    public $timestamps = false;
```

por:

```php
class RequisitoCatalogo extends Model
{
    public const OBLIGATORIO = 'obligatorio';
    public const OPCIONAL = 'opcional';
    public const CONDICIONADO = 'condicionado';

    public $timestamps = false;
```

En `app/Models/RequisitoCatalogo.php`, reemplazar (2 de 3):

```php
        'subtitulo', 'codigo', 'texto', 'nota', 'condicion', 'orden', 'activo',
    ];
```

por:

```php
        'subtitulo', 'codigo', 'texto', 'nota', 'condicion', 'orden', 'activo',
        'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
        'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
        'requiere_aprobacion_no_aplica', 'codigo_comun',
    ];
```

En `app/Models/RequisitoCatalogo.php`, reemplazar (3 de 3):

```php
        'activo' => 'boolean',
    ];
```

por:

```php
        'activo' => 'boolean',
        'requiere_aprobacion_no_aplica' => 'boolean',
    ];
```

En `database/seeders/CatalogoRequisitosSgrSeeder.php`, reemplazar (1 de 1):

```php
                    'orden' => $r['orden'],
                    'activo' => true,
                ];
```

por:

```php
                    'orden' => $r['orden'],
                    'activo' => true,
                    'codigo_comun' => $r['clave'],
                ];
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (1 de 2):

```php
            $columnas = ['clave', 'seccion', 'fuente', 'sector_id', 'tipo_id', 'subtitulo', 'codigo', 'texto', 'nota', 'condicion', 'orden', 'activo'];
```

por:

```php
            $columnas = [
                'clave', 'seccion', 'fuente', 'sector_id', 'tipo_id', 'subtitulo', 'codigo', 'texto', 'nota', 'condicion', 'orden', 'activo',
                'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
                'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
                'requiere_aprobacion_no_aplica', 'codigo_comun',
            ];
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (2 de 2):

```php
            'clave' => $datos['clave'],
            'seccion' => $seccion,
```

por:

```php
            'clave' => $datos['clave'],
            'codigo_comun' => $datos['clave'],
            'seccion' => $seccion,
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Schema/SeguimientoRequisitoTest.php tests/Feature/Viabilizacion/PlantillasVersionesTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: pasan todas, 288.

- [ ] **Paso 5: Comitear**

```bash
git add database/migrations/2026_11_06_100006_add_seguimiento_to_requisitos_catalogo_table.php tests/Feature/Schema/SeguimientoRequisitoTest.php app/Models/RequisitoCatalogo.php database/seeders/CatalogoRequisitosSgrSeeder.php app/Services/Viabilizacion/CatalogoService.php tests/Feature/Viabilizacion/PlantillasVersionesTest.php
```

```bash
git commit -m "feat: add tracking fields to checklist requirements"
```

---

### Tarea 3: Estado del requisito y una sola definición del avance

**Files:**
- Create: `database/migrations/2026_11_06_100007_add_seguimiento_to_proyectos_requisitos_table.php`
- Modify: `app/Models/ProyectoRequisito.php`
- Test (create): `tests/Feature/Viabilizacion/SeguimientoRequisitoApiTest.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Test: `tests/Feature/Schema/SeguimientoRequisitoTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Viabilizacion/SeguimientoRequisitoApiTest.php`:

```php
<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeguimientoRequisitoApiTest extends TestCase
{
    use RefreshDatabase;

    private ProyectoExpediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);

        // Fase III sin sectores: 8 requisitos generales.
        $servicio = app(ExpedienteService::class);
        $this->expediente = $servicio->crear(Proyecto::create(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);
        $servicio->confirmarConfiguracion($this->expediente, ['fase_sgr' => 3]);
    }

    private function como(Rol|User $quien): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    /** @return list<ProyectoRequisito> */
    private function filas(): array
    {
        return $this->expediente->requisitos()->where('aplica', true)->orderBy('orden')->get()->all();
    }

    private function url(ProyectoRequisito $fila, string $resto = ''): string
    {
        return "/GestPro/expedientes/{$this->expediente->id}/requisitos/{$fila->id}{$resto}";
    }

    private function progreso(): array
    {
        return $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$this->expediente->id}")->assertOk()->json('progreso');
    }

    private function cumplirTodas(): void
    {
        DB::table('proyectos_requisitos')->where('expediente_id', $this->expediente->id)->update(['cumplido' => true]);
    }

    // ---------------------------------------------------------------- avance

    public function test_un_expediente_sgr_conserva_sus_cifras(): void
    {
        $this->assertSame(['total' => 8, 'cumplidos' => 0, 'porcentaje' => 0], $this->progreso());
        $this->assertSame(8, $this->expediente->requisitos()->where('caracter', 'obligatorio')->count());
    }

    public function test_no_aplica_y_opcional_no_cuentan_y_lo_pendiente_si(): void
    {
        [$a, $b, $c, $d] = $this->filas();
        $a->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA]);
        $b->update(['caracter' => 'opcional', 'cumplido' => true]);
        $c->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA_PENDIENTE, 'cumplido' => true]);
        $d->update(['estado_aplicabilidad' => ProyectoRequisito::POR_DEFINIR]);

        // De 8 quedan 6: c y d cuentan como pendientes aunque c tenga cumplido = true.
        $this->assertSame(['total' => 6, 'cumplidos' => 0, 'porcentaje' => 0], $this->progreso());
    }

    public function test_el_tablero_y_el_detalle_usan_la_misma_definicion(): void
    {
        [$a, $b] = $this->filas();
        $a->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA]);
        $b->update(['cumplido' => true]);

        $resumen = collect($this->como(Rol::Consulta)->getJson('/GestPro/expedientes')->assertOk()->json())
            ->firstWhere('expediente_id', $this->expediente->id);

        $this->assertSame($this->progreso(), $resumen['progreso']);
        $this->assertSame(['total' => 7, 'cumplidos' => 1, 'porcentaje' => 14], $resumen['progreso']);
    }

    public function test_la_fila_nace_con_el_caracter_del_catalogo(): void
    {
        DB::table('requisitos_catalogo')->update(['caracter' => 'opcional']);
        $servicio = app(ExpedienteService::class);

        $otro = $servicio->crear(Proyecto::create(['nombre' => 'Vía']), ['fase_sgr' => 3], null);

        $this->assertSame(0, $otro->requisitos()->where('caracter', '!=', 'opcional')->count());
    }

    // ------------------------------------------------------------ radicación

    public function test_no_se_radica_con_un_no_aplica_por_aprobar(): void
    {
        $this->cumplirTodas();
        $this->filas()[0]->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA_PENDIENTE]);

        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$this->expediente->id}/radicar")
            ->assertStatus(422)->assertJsonPath('mensaje', 'No se puede radicar: falta 1 de 8 requisitos por cumplir.');
    }

    public function test_se_radica_con_lo_que_no_aplica_y_sin_cumplir_lo_opcional(): void
    {
        $this->cumplirTodas();
        [$a, $b] = $this->filas();
        $a->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA, 'cumplido' => false]);
        $b->update(['caracter' => 'opcional', 'cumplido' => false]);

        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$this->expediente->id}/radicar")
            ->assertOk()->assertJsonPath('estado', 'radicado');
    }

    public function test_no_se_radica_si_nada_cuenta(): void
    {
        DB::table('proyectos_requisitos')->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA]);

        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$this->expediente->id}/radicar")
            ->assertStatus(422)->assertJsonPath('mensaje', 'No se puede radicar: el expediente no tiene requisitos aplicables.');
    }

    // ---------------------------------------------------------------- filas

    public function test_no_se_cumple_un_requisito_que_no_aplica(): void
    {
        $fila = $this->filas()[0];
        $fila->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA]);

        $this->como(Rol::Gestor)->patchJson($this->url($fila), ['cumplido' => true])->assertStatus(422);

        $this->assertFalse($fila->fresh()->cumplido);
    }

    public function test_la_fila_presenta_su_estado(): void
    {
        $fila = $this->filas()[0];

        $respuesta = $this->como(Rol::Gestor)->patchJson($this->url($fila), ['cumplido' => true])->assertOk();

        $respuesta->assertJsonPath('requisito.estado', 'cumplido')
            ->assertJsonPath('requisito.caracter', 'obligatorio')
            ->assertJsonPath('requisito.cuenta', true)
            ->assertJsonPath('requisito.no_aplica', null)
            ->assertJsonPath('requisito.responsable', null)
            ->assertJsonPath('requisito.requisito.requiere_aprobacion_no_aplica', false)
            ->assertJsonPath('progreso.cumplidos', 1);
    }
}
```

En `tests/Feature/Schema/SeguimientoRequisitoTest.php`, reemplazar (1 de 3):

```php
    private function versionSgr(): int
```

por:

```php
    private function migracionFilas(): object
    {
        return require database_path('migrations/2026_11_06_100007_add_seguimiento_to_proyectos_requisitos_table.php');
    }

    private function fila(array $cambios = []): int
    {
        $version = DB::table('catalogo_versiones')->value('id') ?? $this->versionSgr();
        $requisito = DB::table('requisitos_catalogo')->value('id') ?? $this->requisito($version);
        $expediente = DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => DB::table('proyectos')->insertGetId(['nombre' => 'Prueba']),
            'catalogo_version_id' => $version, 'fase_sgr' => 3, 'estado' => 'borrador',
        ]);

        return DB::table('proyectos_requisitos')->insertGetId([
            'expediente_id' => $expediente, 'requisito_catalogo_id' => $requisito, ...$cambios,
        ]);
    }

    private function versionSgr(): int
```

En `tests/Feature/Schema/SeguimientoRequisitoTest.php`, reemplazar (2 de 3):

```php
            try {
                $this->migracionCatalogo()->down();
                $this->fail('El rollback debía detenerse con ' . json_encode($datos));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('se perderían', $e->getMessage());
            }
        }
    }
}
```

por:

```php
            try {
                $this->migracionCatalogo()->down();
                $this->fail('El rollback debía detenerse con ' . json_encode($datos));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('se perderían', $e->getMessage());
            }
        }
    }

    // ------------------------------------------------------------ checklist

    public function test_migrar_deja_las_filas_existentes_en_aplica_y_obligatorias(): void
    {
        $migracion = $this->migracionFilas();
        $migracion->down();
        $this->assertFalse(Schema::hasColumn('proyectos_requisitos', 'estado_aplicabilidad'));
        $id = $this->fila(['cumplido' => true]);

        $migracion->up();
        $migracion->up(); // repetirla no falla

        $fila = DB::table('proyectos_requisitos')->where('id', $id)->first();
        $this->assertSame('aplica', $fila->estado_aplicabilidad);
        $this->assertSame('obligatorio', $fila->caracter);
        $this->assertEquals(1, $fila->cumplido);
    }

    public function test_borrar_un_responsable_o_un_usuario_conserva_la_fila(): void
    {
        $responsable = DB::table('responsable')->insertGetId(['nombre' => 'Ana', 'activo' => true]);
        $usuario = User::factory()->create();
        $id = $this->fila(['responsable_id' => $responsable, 'usuario_no_aplica_id' => $usuario->id, 'usuario_aprobo_id' => $usuario->id]);

        DB::table('responsable')->where('id', $responsable)->delete();
        DB::table('users')->where('id', $usuario->id)->delete();

        $fila = DB::table('proyectos_requisitos')->where('id', $id)->first();
        $this->assertNull($fila->responsable_id);
        $this->assertNull($fila->usuario_no_aplica_id);
        $this->assertNull($fila->usuario_aprobo_id);
    }

    public function test_el_rollback_del_checklist_se_detiene_si_se_perderian_datos(): void
    {
        $responsable = DB::table('responsable')->insertGetId(['nombre' => 'Ana', 'activo' => true]);
        foreach ([['estado_aplicabilidad' => 'no_aplica'], ['caracter' => 'opcional'], ['responsable_id' => $responsable]] as $datos) {
            DB::table('proyectos_requisitos')->delete();
            $this->fila($datos);

            try {
                $this->migracionFilas()->down();
                $this->fail('El rollback debía detenerse con ' . json_encode($datos));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('se perderían', $e->getMessage());
            }
        }

        $this->assertTrue(Schema::hasColumn('proyectos_requisitos', 'estado_aplicabilidad'));
    }

    public function test_el_rollback_del_checklist_avanza_sin_datos_de_seguimiento(): void
    {
        $this->fila();

        $this->migracionFilas()->down();

        $this->assertFalse(Schema::hasColumn('proyectos_requisitos', 'responsable_id'));
    }
}
```

En `tests/Feature/Schema/SeguimientoRequisitoTest.php`, reemplazar (3 de 3):

```php
use App\Models\RequisitoCatalogo;
```

por:

```php
use App\Models\RequisitoCatalogo;
use App\Models\User;
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Schema/SeguimientoRequisitoTest.php tests/Feature/Viabilizacion/SeguimientoRequisitoApiTest.php`
Expected: FALLAN las pruebas nuevas, porque no existen la migración 100007 ni las columnas `estado_aplicabilidad`, `caracter` y `responsable_id`.

- [ ] **Paso 3: Implementar**

Crear `database/migrations/2026_11_06_100007_add_seguimiento_to_proyectos_requisitos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FORANEAS = [
        'usuario_no_aplica_id' => 'users',
        'usuario_aprobo_id' => 'users',
        'responsable_id' => 'responsable',
    ];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada columna y cada clave foránea es una
        // sentencia aparte con su guarda, para poder reintentar sin dejar nada a medias.
        $this->agregar('estado_aplicabilidad', fn (Blueprint $t) => $t->string('estado_aplicabilidad', 30)->default('aplica'));
        $this->agregar('justificacion_no_aplica', fn (Blueprint $t) => $t->text('justificacion_no_aplica')->nullable());
        $this->agregar('usuario_no_aplica_id', fn (Blueprint $t) => $t->unsignedBigInteger('usuario_no_aplica_id')->nullable());
        $this->agregar('usuario_aprobo_id', fn (Blueprint $t) => $t->unsignedBigInteger('usuario_aprobo_id')->nullable());
        $this->agregar('fecha_aprobacion', fn (Blueprint $t) => $t->dateTime('fecha_aprobacion')->nullable());
        $this->agregar('caracter', fn (Blueprint $t) => $t->string('caracter', 20)->default('obligatorio'));
        $this->agregar('responsable_id', fn (Blueprint $t) => $t->unsignedBigInteger('responsable_id')->nullable());

        foreach (self::FORANEAS as $columna => $tabla) {
            if (! $this->tieneForanea($columna)) {
                Schema::table('proyectos_requisitos', function (Blueprint $table) use ($columna, $tabla) {
                    $table->foreign($columna)->references('id')->on($tabla)->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        $conDatos = DB::table('proyectos_requisitos')
            ->where(fn ($q) => $q->where('estado_aplicabilidad', '!=', 'aplica')
                ->orWhere('caracter', '!=', 'obligatorio')
                ->orWhereNotNull('responsable_id'))
            ->exists();
        if ($conDatos) {
            throw new RuntimeException('No se puede revertir: hay requisitos con no aplica, carácter o responsable que se perderían. Restaure un respaldo.');
        }

        foreach (array_keys(self::FORANEAS) as $columna) {
            Schema::table('proyectos_requisitos', function (Blueprint $table) use ($columna) {
                $table->dropForeign([$columna]);
            });
        }
        Schema::table('proyectos_requisitos', function (Blueprint $table) {
            $table->dropColumn([
                'estado_aplicabilidad', 'justificacion_no_aplica', 'usuario_no_aplica_id', 'usuario_aprobo_id',
                'fecha_aprobacion', 'caracter', 'responsable_id',
            ]);
        });
    }

    private function agregar(string $columna, callable $definicion): void
    {
        if (! Schema::hasColumn('proyectos_requisitos', $columna)) {
            Schema::table('proyectos_requisitos', function (Blueprint $table) use ($definicion) {
                $definicion($table);
            });
        }
    }

    private function tieneForanea(string $columna): bool
    {
        return collect(Schema::getForeignKeys('proyectos_requisitos'))->contains(fn ($f) => $f['columns'] === [$columna]);
    }
};
```

Reemplazar el contenido completo de `app/Models/ProyectoRequisito.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyectoRequisito extends Model
{
    // Lo decide una persona. Es independiente de `aplica`, que decide el sistema al configurar.
    public const APLICA = 'aplica';
    public const POR_DEFINIR = 'por_definir';
    public const NO_APLICA_PENDIENTE = 'no_aplica_pendiente';
    public const NO_APLICA = 'no_aplica';

    public $timestamps = false;

    protected $table = 'proyectos_requisitos';
    protected $fillable = [
        'expediente_id', 'requisito_catalogo_id', 'aplica', 'cumplido', 'fecha_cumplido',
        'usuario_cumplido_id', 'observacion', 'soporte_archivo', 'soporte_nombre', 'orden',
        'estado_aplicabilidad', 'justificacion_no_aplica', 'usuario_no_aplica_id', 'usuario_aprobo_id',
        'fecha_aprobacion', 'caracter', 'responsable_id',
    ];
    protected $attributes = [
        'estado_aplicabilidad' => self::APLICA,
        'caracter' => RequisitoCatalogo::OBLIGATORIO,
    ];
    protected $casts = [
        'expediente_id' => 'integer',
        'requisito_catalogo_id' => 'integer',
        'aplica' => 'boolean',
        'cumplido' => 'boolean',
        'fecha_cumplido' => 'datetime',
        'orden' => 'integer',
        'usuario_no_aplica_id' => 'integer',
        'usuario_aprobo_id' => 'integer',
        'fecha_aprobacion' => 'datetime',
        'responsable_id' => 'integer',
    ];

    /**
     * Filas que cuentan en el avance y deciden la radicación. Es la única definición:
     * la usan el detalle, el resumen del tablero y la radicación.
     */
    public function scopeCuenta(Builder $query): Builder
    {
        return $query->where('aplica', true)
            ->where('caracter', '!=', RequisitoCatalogo::OPCIONAL)
            ->where('estado_aplicabilidad', '!=', self::NO_APLICA);
    }

    /** Entre las que cuentan, las cumplidas. Un "no aplica" por aprobar no está cumplido. */
    public function scopeCumplidaQueCuenta(Builder $query): Builder
    {
        return $query->cuenta()->where('estado_aplicabilidad', self::APLICA)->where('cumplido', true);
    }

    public function cuenta(): bool
    {
        return $this->aplica
            && $this->caracter !== RequisitoCatalogo::OPCIONAL
            && $this->estado_aplicabilidad !== self::NO_APLICA;
    }

    /** Estado que ve el usuario: por_definir, pendiente, cumplido, no_aplica_pendiente o no_aplica. */
    public function estadoVisible(): string
    {
        if ($this->estado_aplicabilidad !== self::APLICA) {
            return $this->estado_aplicabilidad;
        }

        return $this->cumplido ? 'cumplido' : 'pendiente';
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(ProyectoExpediente::class, 'expediente_id');
    }

    public function requisito(): BelongsTo
    {
        return $this->belongsTo(RequisitoCatalogo::class, 'requisito_catalogo_id');
    }

    public function usuarioCumplido(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_cumplido_id');
    }

    public function usuarioNoAplica(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_no_aplica_id');
    }

    public function usuarioAprobo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_aprobo_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Responsable::class, 'responsable_id');
    }
}
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 9):

```php
                $expediente->requisitos()->create([
                    'requisito_catalogo_id' => $requisito->id,
                    'aplica' => true,
                    'orden' => $orden,
                ]);
```

por:

```php
                $expediente->requisitos()->create([
                    'requisito_catalogo_id' => $requisito->id,
                    'aplica' => true,
                    'caracter' => $requisito->caracter,
                    'orden' => $orden,
                ]);
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 9):

```php
        if (array_key_exists('cumplido', $datos)) {
            $cumplido = (bool) $datos['cumplido'];
```

por:

```php
        if (array_key_exists('cumplido', $datos)) {
            $cumplido = (bool) $datos['cumplido'];
            if ($cumplido && $fila->estado_aplicabilidad !== ProyectoRequisito::APLICA) {
                throw new ReglaNegocioException('Este requisito está marcado como no aplica o por definir: resuelva eso antes de cumplirlo.');
            }
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (3 de 9):

```php
        $filas = $expediente->requisitos()
            ->where('aplica', true)
            ->with(['requisito.sector:id,clave,nombre,fuente', 'requisito.tipo:id,clave,nombre', 'usuarioCumplido:id,name'])
            ->orderBy('orden')
            ->get();

        $cumplidos = $filas->where('cumplido', true)->count();
```

por:

```php
        $filas = $expediente->requisitos()
            ->where('aplica', true)
            ->with(self::RELACIONES_FILA)
            ->orderBy('orden')
            ->get();
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (4 de 9):

```php
            'progreso' => $this->progreso($filas->count(), $cumplidos),
            'requisitos' => $filas
```

por:

```php
            'progreso' => $this->progresoDe($expediente),
            'requisitos' => $filas
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (5 de 9):

```php
                'requisitos as requisitos_aplican' => fn ($q) => $q->where('aplica', true),
                'requisitos as requisitos_cumplidos' => fn ($q) => $q->where('aplica', true)->where('cumplido', true),
```

por:

```php
                'requisitos as requisitos_aplican' => fn ($q) => $q->cuenta(),
                'requisitos as requisitos_cumplidos' => fn ($q) => $q->cumplidaQueCuenta(),
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (6 de 9):

```php
        $base = $expediente->requisitos()->where('aplica', true);

        return $this->progreso((clone $base)->count(), (clone $base)->where('cumplido', true)->count());
```

por:

```php
        return $this->progreso(
            $expediente->requisitos()->cuenta()->count(),
            $expediente->requisitos()->cumplidaQueCuenta()->count()
        );
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (7 de 9):

```php
        $fila->loadMissing(['requisito.sector:id,clave,nombre,fuente', 'requisito.tipo:id,clave,nombre', 'usuarioCumplido:id,name']);
        $r = $fila->requisito;

        return [
            'id' => $fila->id,
            'cumplido' => $fila->cumplido,
```

por:

```php
        $fila->loadMissing(self::RELACIONES_FILA);
        $r = $fila->requisito;

        return [
            'id' => $fila->id,
            'estado' => $fila->estadoVisible(),
            'caracter' => $fila->caracter,
            'cuenta' => $fila->cuenta(),
            'no_aplica' => $fila->estado_aplicabilidad === ProyectoRequisito::APLICA ? null : [
                'justificacion' => $fila->justificacion_no_aplica,
                'usuario_id' => $fila->usuario_no_aplica_id,
                'usuario' => $fila->usuarioNoAplica?->name,
                'aprobado_por' => $fila->usuarioAprobo?->name,
                'fecha_aprobacion' => $fila->fecha_aprobacion?->toIso8601String(),
            ],
            'responsable' => $fila->responsable
                ? ['id' => $fila->responsable->id, 'nombre' => $fila->responsable->nombre, 'cargo' => $fila->responsable->cargo]
                : null,
            'cumplido' => $fila->cumplido,
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (8 de 9):

```php
                'texto' => $r->texto,
                'nota' => $r->nota,
            ],
        ];
```

por:

```php
                'texto' => $r->texto,
                'nota' => $r->nota,
                'cargo_cumplimiento' => $r->cargo_cumplimiento,
                'cargo_revision' => $r->cargo_revision,
                'requiere_aprobacion_no_aplica' => $r->requiere_aprobacion_no_aplica,
            ],
        ];
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (9 de 9):

```php
    private const ORDEN_SECCION = ['A' => 1, 'B' => 2, 'D' => 3, 'C' => 4];
```

por:

```php
    private const ORDEN_SECCION = ['A' => 1, 'B' => 2, 'D' => 3, 'C' => 4];

    private const RELACIONES_FILA = [
        'requisito.sector:id,clave,nombre,fuente', 'requisito.tipo:id,clave,nombre', 'usuarioCumplido:id,name',
        'usuarioNoAplica:id,name', 'usuarioAprobo:id,name', 'responsable:id,nombre,cargo',
    ];
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Schema/SeguimientoRequisitoTest.php tests/Feature/Viabilizacion/SeguimientoRequisitoApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: pasan todas, 301. `ExpedienteApiTest` sigue pasando sin cambios: las cifras SGR no cambian.

- [ ] **Paso 5: Comitear**

```bash
git add database/migrations/2026_11_06_100007_add_seguimiento_to_proyectos_requisitos_table.php app/Models/ProyectoRequisito.php tests/Feature/Viabilizacion/SeguimientoRequisitoApiTest.php app/Services/Viabilizacion/ExpedienteService.php tests/Feature/Schema/SeguimientoRequisitoTest.php
```

```bash
git commit -m "feat: track requirement state with a single progress rule"
```

---

### Tarea 4: Historial del checklist y soportes conservados

Cambia a propósito una prueba existente de `ExpedienteApiTest`: reemplazar o eliminar un soporte ya no borra el archivo.

**Files:**
- Create: `database/migrations/2026_11_06_100008_create_checklist_historial_table.php`
- Create: `app/Models/ChecklistHistorial.php`
- Create: `app/Services/Viabilizacion/HistorialChecklist.php`
- Test (create): `tests/Feature/Viabilizacion/HistorialRequisitoApiTest.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `app/Http/Controllers/ExpedienteController.php`
- Modify: `routes/viabilizacion.php`
- Test: `tests/Feature/Viabilizacion/ExpedienteApiTest.php`
- Test: `tests/Feature/Schema/SeguimientoRequisitoTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Viabilizacion/HistorialRequisitoApiTest.php`:

```php
<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HistorialRequisitoApiTest extends TestCase
{
    use RefreshDatabase;

    private ProyectoExpediente $expediente;
    private ProyectoRequisito $fila;
    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        Storage::fake('local');

        $this->gestor = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Gestora Uno']);
        $servicio = app(ExpedienteService::class);
        $this->expediente = $servicio->crear(Proyecto::create(['nombre' => 'Acueducto']), ['fase_sgr' => 3], $this->gestor);
        $servicio->confirmarConfiguracion($this->expediente, ['fase_sgr' => 3], $this->gestor);
        $this->fila = $this->expediente->requisitos()->where('aplica', true)->orderBy('orden')->first();
    }

    private function como(Rol|User $quien): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function url(string $resto = ''): string
    {
        return "/GestPro/expedientes/{$this->expediente->id}/requisitos/{$this->fila->id}{$resto}";
    }

    private function historial(): array
    {
        return $this->como(Rol::Consulta)->getJson($this->url('/historial'))->assertOk()->json();
    }

    private function subir(string $nombre): void
    {
        $this->como($this->gestor)->post($this->url('/soporte'), [
            'archivo' => UploadedFile::fake()->create($nombre, 20, 'application/pdf'),
        ])->assertOk();
    }

    public function test_marcar_y_desmarcar_quedan_registrados_con_quien_lo_hizo(): void
    {
        $this->como($this->gestor)->patchJson($this->url(), ['cumplido' => true])->assertOk();
        $this->como($this->gestor)->patchJson($this->url(), ['cumplido' => true])->assertOk(); // sin cambio, sin registro
        $this->como($this->gestor)->patchJson($this->url(), ['cumplido' => false])->assertOk();

        $historial = $this->historial();

        $this->assertSame(['cumplido_desmarcado', 'cumplido_marcado'], array_column($historial, 'evento'));
        $this->assertSame('Gestora Uno', $historial[1]['usuario']);
        $this->assertSame(['cumplido' => false], $historial[1]['antes']);
        $this->assertSame(['cumplido' => true], $historial[1]['despues']);
    }

    public function test_cambiar_la_observacion_queda_registrado(): void
    {
        $this->como($this->gestor)->patchJson($this->url(), ['observacion' => 'Falta firma'])->assertOk();

        $this->assertSame([
            'evento' => 'observacion_cambiada',
            'antes' => ['observacion' => null],
            'despues' => ['observacion' => 'Falta firma'],
        ], array_intersect_key($this->historial()[0], array_flip(['evento', 'antes', 'despues'])));
    }

    public function test_un_soporte_reemplazado_se_conserva_y_se_descarga_desde_el_historial(): void
    {
        $this->subir('v1.pdf');
        $primera = $this->fila->fresh()->soporte_archivo;
        $this->subir('v2.pdf');

        Storage::disk('local')->assertExists($primera);
        $respuesta = $this->como(Rol::Consulta)->getJson($this->url('/historial'))->assertOk();
        $this->assertStringNotContainsString('expedientes/', $respuesta->getContent()); // la ruta nunca sale
        $reemplazo = $respuesta->json('0');
        $this->assertSame('soporte_reemplazado', $reemplazo['evento']);
        $this->assertSame(['nombre' => 'v1.pdf'], $reemplazo['soporte']);

        $this->como(Rol::Consulta)->get("/GestPro/expedientes/{$this->expediente->id}/historial/{$reemplazo['id']}/soporte")
            ->assertOk()->assertDownload('v1.pdf');
    }

    public function test_un_soporte_eliminado_se_conserva_en_el_historial(): void
    {
        $this->subir('acta.pdf');
        $ruta = $this->fila->fresh()->soporte_archivo;

        $this->como($this->gestor)->deleteJson($this->url('/soporte'))->assertOk();

        Storage::disk('local')->assertExists($ruta);
        $this->assertSame('soporte_eliminado', $this->historial()[0]['evento']);
    }

    public function test_no_se_descarga_un_soporte_de_otro_expediente(): void
    {
        $this->subir('v1.pdf');
        $this->subir('v2.pdf');
        $entrada = $this->historial()[0]['id'];
        $servicio = app(ExpedienteService::class);
        $otro = $servicio->crear(Proyecto::create(['nombre' => 'Vía']), ['fase_sgr' => 3], null);

        $this->como(Rol::Consulta)->get("/GestPro/expedientes/{$otro->id}/historial/{$entrada}/soporte")->assertNotFound();
    }

    public function test_el_expediente_registra_su_creacion_configuracion_y_radicacion(): void
    {
        DB::table('proyectos_requisitos')->where('expediente_id', $this->expediente->id)->update(['cumplido' => true]);

        $this->como($this->gestor)->postJson("/GestPro/expedientes/{$this->expediente->id}/radicar")->assertOk();

        $this->assertSame(
            ['expediente_creado', 'configuracion_confirmada', 'expediente_radicado'],
            DB::table('checklist_historial')->where('entidad_tipo', 'expediente')->orderBy('id')->pluck('evento')->all()
        );
        $this->assertSame([$this->gestor->id], DB::table('checklist_historial')->distinct()->pluck('usuario_id')->all());
    }

    public function test_eliminar_el_expediente_borra_tambien_los_soportes_conservados_y_deja_el_registro(): void
    {
        $this->subir('v1.pdf');
        $primera = $this->fila->fresh()->soporte_archivo;
        $this->subir('v2.pdf');
        $segunda = $this->fila->fresh()->soporte_archivo;

        $this->como($this->gestor)->deleteJson("/GestPro/expedientes/{$this->expediente->id}")->assertOk();

        Storage::disk('local')->assertMissing($primera);
        Storage::disk('local')->assertMissing($segunda);
        $this->assertDatabaseHas('checklist_historial', [
            'expediente_id' => $this->expediente->id, 'evento' => 'expediente_eliminado', 'usuario_id' => $this->gestor->id,
        ]);
    }
}
```

En `tests/Feature/Viabilizacion/ExpedienteApiTest.php`, reemplazar (1 de 1):

```php
        // Reemplazar borra el archivo anterior.
        $this->como(Rol::Gestor)->post($url, ['archivo' => UploadedFile::fake()->create('presupuesto_v2.pdf', 80, 'application/pdf')])->assertOk();
        Storage::disk('local')->assertMissing($primera);

        $actual = DB::table('proyectos_requisitos')->where('id', $a1)->value('soporte_archivo');
        $this->como(Rol::Gestor)->deleteJson($url)->assertOk()->assertJsonPath('requisito.soporte', null);
        Storage::disk('local')->assertMissing($actual);
```

por:

```php
        // Reemplazar conserva el archivo anterior: queda en el historial.
        $this->como(Rol::Gestor)->post($url, ['archivo' => UploadedFile::fake()->create('presupuesto_v2.pdf', 80, 'application/pdf')])->assertOk();
        Storage::disk('local')->assertExists($primera);

        $actual = DB::table('proyectos_requisitos')->where('id', $a1)->value('soporte_archivo');
        $this->como(Rol::Gestor)->deleteJson($url)->assertOk()->assertJsonPath('requisito.soporte', null);
        Storage::disk('local')->assertExists($actual);
```

En `tests/Feature/Schema/SeguimientoRequisitoTest.php`, reemplazar (1 de 2):

```php
    private function fila(array $cambios = []): int
```

por:

```php
    private function migracionHistorial(): object
    {
        return require database_path('migrations/2026_11_06_100008_create_checklist_historial_table.php');
    }

    private function fila(array $cambios = []): int
```

En `tests/Feature/Schema/SeguimientoRequisitoTest.php`, reemplazar (2 de 2):

```php
        $this->assertFalse(Schema::hasColumn('proyectos_requisitos', 'responsable_id'));
    }
}
```

por:

```php
        $this->assertFalse(Schema::hasColumn('proyectos_requisitos', 'responsable_id'));
    }

    // ------------------------------------------------------------- historial

    public function test_el_historial_sobrevive_a_la_eliminacion_de_lo_que_registra(): void
    {
        $id = $this->fila();
        DB::table('checklist_historial')->insert([
            'entidad_tipo' => 'requisito', 'entidad_id' => $id, 'expediente_id' => 1, 'evento' => 'cumplido_marcado',
        ]);

        DB::table('proyectos_expedientes')->delete();

        $this->assertDatabaseCount('checklist_historial', 1);
    }

    public function test_reintentar_la_migracion_del_historial_rehace_lo_que_quedo_a_medias(): void
    {
        $migracion = $this->migracionHistorial();
        $migracion->down();
        Schema::create('checklist_historial', function ($table) {
            $table->id();
        });

        $migracion->up();

        $this->assertTrue(Schema::hasColumns('checklist_historial', ['entidad_tipo', 'antes', 'despues', 'usuario_id']));
    }

    public function test_el_rollback_del_historial_se_detiene_si_tiene_registros(): void
    {
        DB::table('checklist_historial')->insert(['entidad_tipo' => 'expediente', 'entidad_id' => 1, 'evento' => 'expediente_creado']);

        try {
            $this->migracionHistorial()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('se perderían', $e->getMessage());
        }

        $this->assertTrue(Schema::hasTable('checklist_historial'));
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Schema/SeguimientoRequisitoTest.php tests/Feature/Viabilizacion/HistorialRequisitoApiTest.php tests/Feature/Viabilizacion/ExpedienteApiTest.php`
Expected: FALLAN las pruebas nuevas, porque no existen la migración 100008 ni la ruta del historial, y `test_soporte_se_guarda_en_disco_privado_se_descarga_y_se_reemplaza`, porque hoy el archivo anterior se borra.

- [ ] **Paso 3: Implementar**

Crear `database/migrations/2026_11_06_100008_create_checklist_historial_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // up() solo corre si la migración no quedó registrada: si la tabla existe, es un resto
        // de un intento fallido, quizá sin sus índices.
        Schema::dropIfExists('checklist_historial');

        // Sin claves foráneas a propósito: el historial debe sobrevivir a la eliminación de
        // la fila, del expediente o del usuario que registra.
        Schema::create('checklist_historial', function (Blueprint $table) {
            $table->id();
            $table->string('entidad_tipo', 30);
            $table->unsignedBigInteger('entidad_id');
            $table->unsignedBigInteger('expediente_id')->nullable();
            $table->string('evento', 50);
            $table->json('antes')->nullable();
            $table->json('despues')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['entidad_tipo', 'entidad_id'], 'checklist_historial_entidad_idx');
            $table->index('expediente_id', 'checklist_historial_expediente_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('checklist_historial') && DB::table('checklist_historial')->exists()) {
            throw new RuntimeException('No se puede revertir: el historial del checklist tiene registros que se perderían. Restaure un respaldo.');
        }

        Schema::dropIfExists('checklist_historial');
    }
};
```

Crear `app/Models/ChecklistHistorial.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistHistorial extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'checklist_historial';
    protected $fillable = ['entidad_tipo', 'entidad_id', 'expediente_id', 'evento', 'antes', 'despues', 'usuario_id'];
    protected $casts = [
        'entidad_id' => 'integer',
        'expediente_id' => 'integer',
        'antes' => 'array',
        'despues' => 'array',
        'usuario_id' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
```

Crear `app/Services/Viabilizacion/HistorialChecklist.php`:

```php
<?php

namespace App\Services\Viabilizacion;

use App\Models\ChecklistHistorial;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\User;

/**
 * Registro de lo que pasa en el checklist: quién cambió qué y cuándo.
 *
 * Los soportes reemplazados o retirados no se borran: su ruta queda en `antes` y se
 * descargan por el id del registro. La ruta nunca sale en la API.
 */
final class HistorialChecklist
{
    public const REQUISITO = 'requisito';
    public const EXPEDIENTE = 'expediente';

    public function registrarFila(ProyectoRequisito $fila, string $evento, array $antes, array $despues, ?User $usuario): void
    {
        $this->registrar(self::REQUISITO, $fila->id, $fila->expediente_id, $evento, $antes, $despues, $usuario);
    }

    public function registrarExpediente(ProyectoExpediente $expediente, string $evento, array $antes, array $despues, ?User $usuario): void
    {
        $this->registrar(self::EXPEDIENTE, $expediente->id, $expediente->id, $evento, $antes, $despues, $usuario);
    }

    /** Historial de una fila, del más reciente al más antiguo. */
    public function deFila(ProyectoRequisito $fila): array
    {
        return ChecklistHistorial::query()
            ->where('entidad_tipo', self::REQUISITO)
            ->where('entidad_id', $fila->id)
            ->with('usuario:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ChecklistHistorial $h) => $this->presentar($h))
            ->all();
    }

    /** @return list<string> rutas de los soportes conservados en el historial del expediente */
    public function archivosDe(ProyectoExpediente $expediente): array
    {
        return ChecklistHistorial::query()
            ->where('expediente_id', $expediente->id)
            ->get()
            ->map(fn (ChecklistHistorial $h) => $h->antes['soporte_archivo'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    public function presentar(ChecklistHistorial $entrada): array
    {
        return [
            'id' => $entrada->id,
            'evento' => $entrada->evento,
            'antes' => $this->sinRuta($entrada->antes),
            'despues' => $this->sinRuta($entrada->despues),
            'usuario' => $entrada->usuario?->name,
            'fecha' => $entrada->created_at?->toIso8601String(),
            'soporte' => isset($entrada->antes['soporte_archivo'])
                ? ['nombre' => $entrada->antes['soporte_nombre'] ?? 'soporte']
                : null,
        ];
    }

    private function registrar(string $tipo, int $id, ?int $expedienteId, string $evento, array $antes, array $despues, ?User $usuario): void
    {
        ChecklistHistorial::create([
            'entidad_tipo' => $tipo,
            'entidad_id' => $id,
            'expediente_id' => $expedienteId,
            'evento' => $evento,
            'antes' => $antes ?: null,
            'despues' => $despues ?: null,
            'usuario_id' => $usuario?->id,
        ]);
    }

    private function sinRuta(?array $datos): ?array
    {
        if ($datos === null) {
            return null;
        }
        unset($datos['soporte_archivo']);

        return $datos;
    }
}
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 7):

```php
    public function __construct(private readonly EvaluadorRequisitos $evaluador)
    {
    }
```

por:

```php
    public function __construct(
        private readonly EvaluadorRequisitos $evaluador,
        private readonly HistorialChecklist $historial,
    ) {
    }
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 7):

```php
            $expediente->versionesPlantilla()->attach($version->id, ['es_principal' => true]);
            $this->guardarSeleccion($expediente, $seleccion);
            $this->sincronizar($expediente);

            return $expediente;
```

por:

```php
            $expediente->versionesPlantilla()->attach($version->id, ['es_principal' => true]);
            $this->guardarSeleccion($expediente, $seleccion);
            $this->sincronizar($expediente);
            $this->historial->registrarExpediente($expediente, 'expediente_creado', [], ['catalogo_version_id' => $version->id], $usuario);

            return $expediente;
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (3 de 7):

```php
    public function confirmarConfiguracion(ProyectoExpediente $expediente, array $datos): ProyectoExpediente
    {
        return DB::transaction(function () use ($expediente, $datos) {
            $this->actualizarConfiguracion($expediente, $datos);
            if (! $expediente->estaConfigurado()) {
                $expediente->update(['fecha_configuracion' => now()]);
            }
```

por:

```php
    public function confirmarConfiguracion(ProyectoExpediente $expediente, array $datos, ?User $usuario = null): ProyectoExpediente
    {
        return DB::transaction(function () use ($expediente, $datos, $usuario) {
            $this->actualizarConfiguracion($expediente, $datos);
            if (! $expediente->estaConfigurado()) {
                $expediente->update(['fecha_configuracion' => now()]);
                $this->historial->registrarExpediente($expediente, 'configuracion_confirmada', [], ['fase_sgr' => $expediente->fase_sgr], $usuario);
            }
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (4 de 7):

```php
        $fila->save();
        $expediente->touch();

        return $fila;
    }
```

por:

```php
        DB::transaction(function () use ($expediente, $fila, $usuario) {
            $this->registrarCambios($fila, $usuario);
            $fila->save();
            $expediente->touch();
        });

        return $fila;
    }
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (5 de 7):

```php
    public function guardarSoporte(ProyectoExpediente $expediente, ProyectoRequisito $fila, UploadedFile $archivo): ProyectoRequisito
    {
        $this->exigirEditable($expediente, $fila);

        $anterior = $fila->soporte_archivo;
        // Extensión deducida del contenido, no la que declara quien sube el archivo.
        $ruta = $archivo->storeAs("expedientes/{$expediente->id}", Str::uuid() . '.' . $archivo->extension(), self::DISCO);

        $fila->update(['soporte_archivo' => $ruta, 'soporte_nombre' => $archivo->getClientOriginalName()]);

        if ($anterior) {
            Storage::disk(self::DISCO)->delete($anterior);
        }

        return $fila;
    }

    public function eliminarSoporte(ProyectoExpediente $expediente, ProyectoRequisito $fila): ProyectoRequisito
    {
        $this->exigirEditable($expediente, $fila);

        if ($fila->soporte_archivo) {
            Storage::disk(self::DISCO)->delete($fila->soporte_archivo);
        }
        $fila->update(['soporte_archivo' => null, 'soporte_nombre' => null]);

        return $fila;
    }
```

por:

```php
    public function guardarSoporte(ProyectoExpediente $expediente, ProyectoRequisito $fila, UploadedFile $archivo, ?User $usuario = null): ProyectoRequisito
    {
        $this->exigirEditable($expediente, $fila);

        $anterior = $fila->only(['soporte_archivo', 'soporte_nombre']);
        // Extensión deducida del contenido, no la que declara quien sube el archivo.
        $ruta = $archivo->storeAs("expedientes/{$expediente->id}", Str::uuid() . '.' . $archivo->extension(), self::DISCO);

        try {
            DB::transaction(function () use ($fila, $archivo, $ruta, $anterior, $usuario) {
                $fila->update(['soporte_archivo' => $ruta, 'soporte_nombre' => $archivo->getClientOriginalName()]);
                // El anterior no se borra: queda en el historial y se puede descargar.
                $this->historial->registrarFila(
                    $fila,
                    $anterior['soporte_archivo'] ? 'soporte_reemplazado' : 'soporte_subido',
                    $anterior['soporte_archivo'] ? $anterior : [],
                    ['soporte_nombre' => $fila->soporte_nombre],
                    $usuario
                );
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISCO)->delete($ruta);
            throw $e;
        }

        return $fila;
    }

    public function eliminarSoporte(ProyectoExpediente $expediente, ProyectoRequisito $fila, ?User $usuario = null): ProyectoRequisito
    {
        $this->exigirEditable($expediente, $fila);

        if (! $fila->soporte_archivo) {
            return $fila;
        }

        DB::transaction(function () use ($fila, $usuario) {
            $antes = $fila->only(['soporte_archivo', 'soporte_nombre']);
            $fila->update(['soporte_archivo' => null, 'soporte_nombre' => null]);
            // El archivo no se borra: queda en el historial y se puede descargar.
            $this->historial->registrarFila($fila, 'soporte_eliminado', $antes, [], $usuario);
        });

        return $fila;
    }
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (6 de 7):

```php
        $expediente->update([
            'estado' => ProyectoExpediente::RADICADO,
            'fecha_radicado' => now(),
            'usuario_radicado_id' => $usuario?->id,
        ]);

        return $expediente;
    }

    public function eliminar(ProyectoExpediente $expediente): void
    {
        $this->exigirBorrador($expediente);

        DB::transaction(function () use ($expediente) {
            $archivos = $expediente->requisitos()->whereNotNull('soporte_archivo')->pluck('soporte_archivo')->all();
            $expediente->delete(); // pivotes y checklist caen en cascada
            Storage::disk(self::DISCO)->delete($archivos);
        });
    }
```

por:

```php
        DB::transaction(function () use ($expediente, $usuario, $total) {
            $expediente->update([
                'estado' => ProyectoExpediente::RADICADO,
                'fecha_radicado' => now(),
                'usuario_radicado_id' => $usuario?->id,
            ]);
            $this->historial->registrarExpediente($expediente, 'expediente_radicado', [], ['requisitos' => $total], $usuario);
        });

        return $expediente;
    }

    public function eliminar(ProyectoExpediente $expediente, ?User $usuario = null): void
    {
        $this->exigirBorrador($expediente);

        DB::transaction(function () use ($expediente, $usuario) {
            $archivos = [
                ...$expediente->requisitos()->whereNotNull('soporte_archivo')->pluck('soporte_archivo')->all(),
                ...$this->historial->archivosDe($expediente),
            ];
            // El registro sobrevive al expediente; los archivos no, como avisa la pantalla.
            $this->historial->registrarExpediente($expediente, 'expediente_eliminado', ['proyecto_id' => $expediente->proyecto_id], [], $usuario);
            $expediente->delete(); // pivotes y checklist caen en cascada
            Storage::disk(self::DISCO)->delete($archivos);
        });
    }
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (7 de 7):

```php
    private function exigirEditable(ProyectoExpediente $expediente, ProyectoRequisito $fila): void
```

por:

```php
    /** Registra lo que cambió en la fila antes de guardarla. */
    private function registrarCambios(ProyectoRequisito $fila, ?User $usuario): void
    {
        if ($fila->isDirty('cumplido')) {
            $this->historial->registrarFila(
                $fila,
                $fila->cumplido ? 'cumplido_marcado' : 'cumplido_desmarcado',
                ['cumplido' => (bool) $fila->getOriginal('cumplido')],
                ['cumplido' => $fila->cumplido],
                $usuario
            );
        }
        if ($fila->isDirty('observacion')) {
            $this->historial->registrarFila(
                $fila,
                'observacion_cambiada',
                ['observacion' => $fila->getOriginal('observacion')],
                ['observacion' => $fila->observacion],
                $usuario
            );
        }
    }

    private function exigirEditable(ProyectoExpediente $expediente, ProyectoRequisito $fila): void
```

En `app/Http/Controllers/ExpedienteController.php`, reemplazar (1 de 7):

```php
use App\Models\Proyecto;
```

por:

```php
use App\Models\ChecklistHistorial;
use App\Models\Proyecto;
```

En `app/Http/Controllers/ExpedienteController.php`, reemplazar (2 de 7):

```php
use App\Services\Viabilizacion\ExpedienteService;
```

por:

```php
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\HistorialChecklist;
```

En `app/Http/Controllers/ExpedienteController.php`, reemplazar (3 de 7):

```php
    public function __construct(private readonly ExpedienteService $servicio)
    {
    }
```

por:

```php
    public function __construct(
        private readonly ExpedienteService $servicio,
        private readonly HistorialChecklist $historial,
    ) {
    }
```

En `app/Http/Controllers/ExpedienteController.php`, reemplazar (4 de 7):

```php
        $this->servicio->confirmarConfiguracion($expediente, $datos);
```

por:

```php
        $this->servicio->confirmarConfiguracion($expediente, $datos, $request->user());
```

En `app/Http/Controllers/ExpedienteController.php`, reemplazar (5 de 7):

```php
        $fila = $this->servicio->guardarSoporte($expediente, $requisito, $request->file('archivo'));
```

por:

```php
        $fila = $this->servicio->guardarSoporte($expediente, $requisito, $request->file('archivo'), $request->user());
```

En `app/Http/Controllers/ExpedienteController.php`, reemplazar (6 de 7):

```php
    public function eliminarSoporte(ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        $fila = $this->servicio->eliminarSoporte($expediente, $requisito);
```

por:

```php
    public function eliminarSoporte(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        $fila = $this->servicio->eliminarSoporte($expediente, $requisito, $request->user());
```

En `app/Http/Controllers/ExpedienteController.php`, reemplazar (7 de 7):

```php
    public function destroy(ProyectoExpediente $expediente): JsonResponse
    {
        $this->servicio->eliminar($expediente);
```

por:

```php
    public function historialRequisito(ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        return response()->json($this->historial->deFila($requisito));
    }

    /** Soporte reemplazado o retirado, conservado en el historial. */
    public function descargarSoporteHistorial(ProyectoExpediente $expediente, ChecklistHistorial $entrada): StreamedResponse|JsonResponse
    {
        $ruta = $entrada->expediente_id === $expediente->id ? ($entrada->antes['soporte_archivo'] ?? null) : null;
        $disco = Storage::disk(ExpedienteService::DISCO);
        if (! $ruta || ! $disco->exists($ruta)) {
            return response()->json(['mensaje' => 'Ese registro no tiene un soporte disponible.'], 404);
        }

        return $disco->download($ruta, $entrada->antes['soporte_nombre'] ?? 'soporte');
    }

    public function destroy(Request $request, ProyectoExpediente $expediente): JsonResponse
    {
        $this->servicio->eliminar($expediente, $request->user());
```

En `routes/viabilizacion.php`, reemplazar (1 de 1):

```php
Route::get('/expedientes/{expediente}/requisitos/{requisito}/soporte', [ExpedienteController::class, 'descargarSoporte'])
    ->scopeBindings()->name('expedientes.requisitos.soporte.descargar');
```

por:

```php
Route::get('/expedientes/{expediente}/requisitos/{requisito}/soporte', [ExpedienteController::class, 'descargarSoporte'])
    ->scopeBindings()->name('expedientes.requisitos.soporte.descargar');
Route::get('/expedientes/{expediente}/requisitos/{requisito}/historial', [ExpedienteController::class, 'historialRequisito'])
    ->scopeBindings()->name('expedientes.requisitos.historial');
Route::get('/expedientes/{expediente}/historial/{entrada}/soporte', [ExpedienteController::class, 'descargarSoporteHistorial'])
    ->name('expedientes.historial.soporte');
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Schema/SeguimientoRequisitoTest.php tests/Feature/Viabilizacion/HistorialRequisitoApiTest.php tests/Feature/Viabilizacion/ExpedienteApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: pasan todas, 311.

- [ ] **Paso 5: Comitear**

```bash
git add database/migrations/2026_11_06_100008_create_checklist_historial_table.php app/Models/ChecklistHistorial.php app/Services/Viabilizacion/HistorialChecklist.php tests/Feature/Viabilizacion/HistorialRequisitoApiTest.php app/Services/Viabilizacion/ExpedienteService.php app/Http/Controllers/ExpedienteController.php routes/viabilizacion.php tests/Feature/Viabilizacion/ExpedienteApiTest.php tests/Feature/Schema/SeguimientoRequisitoTest.php
```

```bash
git commit -m "feat: record checklist history and keep replaced supports"
```

---

### Tarea 5: "No aplica" con aprobación y responsable

**Files:**
- Create: `app/Services/Viabilizacion/NoAplicaRequisito.php`
- Create: `app/Http/Controllers/NoAplicaRequisitoController.php`
- Test (create): `tests/Feature/Viabilizacion/NoAplicaRequisitoApiTest.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `app/Http/Controllers/ExpedienteController.php`
- Modify: `routes/viabilizacion.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Viabilizacion/NoAplicaRequisitoApiTest.php`:

```php
<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NoAplicaRequisitoApiTest extends TestCase
{
    use RefreshDatabase;

    private ProyectoExpediente $expediente;
    private ProyectoRequisito $fila;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);

        // Fase III sin sectores: 8 requisitos generales.
        $servicio = app(ExpedienteService::class);
        $this->expediente = $servicio->crear(Proyecto::create(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);
        $servicio->confirmarConfiguracion($this->expediente, ['fase_sgr' => 3]);
        $this->fila = $this->expediente->requisitos()->where('aplica', true)->orderBy('orden')->first();
    }

    private function usuario(Rol $rol): User
    {
        return User::factory()->create(['rol' => $rol]);
    }

    private function como(Rol|User $quien): static
    {
        $usuario = $quien instanceof User ? $quien : $this->usuario($quien);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function url(string $resto = ''): string
    {
        return "/GestPro/expedientes/{$this->expediente->id}/requisitos/{$this->fila->id}{$resto}";
    }

    private function conAprobacion(): void
    {
        DB::table('requisitos_catalogo')->where('id', $this->fila->requisito_catalogo_id)->update(['requiere_aprobacion_no_aplica' => true]);
    }

    private function marcarComo(User $usuario)
    {
        return $this->como($usuario)->postJson($this->url('/no-aplica'), ['justificacion' => '  El proyecto no tiene predios.  ']);
    }

    private function eventos(): array
    {
        return DB::table('checklist_historial')->where('entidad_tipo', 'requisito')->where('entidad_id', $this->fila->id)->orderBy('id')->pluck('evento')->all();
    }

    // ------------------------------------------------------------ sin aprobación

    public function test_un_no_aplica_sin_aprobacion_deja_de_contar_de_inmediato(): void
    {
        $gestor = $this->usuario(Rol::Gestor);

        $this->marcarComo($gestor)->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica')
            ->assertJsonPath('requisito.cuenta', false)
            ->assertJsonPath('requisito.no_aplica.justificacion', 'El proyecto no tiene predios.')
            ->assertJsonPath('requisito.no_aplica.usuario_id', $gestor->id)
            ->assertJsonPath('progreso.total', 7);

        $this->assertSame(['no_aplica_marcado'], $this->eventos());
    }

    public function test_un_no_aplica_exige_justificacion(): void
    {
        $this->como(Rol::Gestor)->postJson($this->url('/no-aplica'), ['justificacion' => '   '])
            ->assertStatus(422)->assertJsonPath('message', 'Explique por qué el requisito no aplica.');

        $this->assertSame('aplica', $this->fila->fresh()->estado_aplicabilidad);
    }

    public function test_un_requisito_cumplido_no_se_marca_como_no_aplica(): void
    {
        $this->fila->update(['cumplido' => true]);

        $this->marcarComo($this->usuario(Rol::Gestor))->assertStatus(422)
            ->assertJsonPath('mensaje', 'El requisito está cumplido: desmárquelo antes de indicar que no aplica.');
    }

    public function test_retirar_un_no_aplica_vuelve_a_contar(): void
    {
        $this->marcarComo($this->usuario(Rol::Gestor))->assertOk();

        $this->como(Rol::Gestor)->deleteJson($this->url('/no-aplica'))->assertOk()
            ->assertJsonPath('requisito.estado', 'pendiente')
            ->assertJsonPath('requisito.no_aplica', null)
            ->assertJsonPath('progreso.total', 8);

        $this->assertSame(['no_aplica_marcado', 'no_aplica_retirado'], $this->eventos());
    }

    // ------------------------------------------------------------ con aprobación

    public function test_con_aprobacion_queda_pendiente_y_sigue_contando(): void
    {
        $this->conAprobacion();

        $this->marcarComo($this->usuario(Rol::Gestor))->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica_pendiente')
            ->assertJsonPath('requisito.cuenta', true)
            ->assertJsonPath('progreso.total', 8);
    }

    public function test_un_gestor_no_aprueba_lo_que_el_mismo_marco_y_otro_si(): void
    {
        $this->conAprobacion();
        $quienMarca = $this->usuario(Rol::Gestor);
        $revisor = $this->usuario(Rol::Gestor);
        $this->marcarComo($quienMarca)->assertOk();

        $this->como($quienMarca)->postJson($this->url('/no-aplica/aprobar'))->assertStatus(422)
            ->assertJsonPath('mensaje', 'Otra persona debe revisar este "no aplica": usted lo marcó.');

        $this->como($revisor)->postJson($this->url('/no-aplica/aprobar'))->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica')
            ->assertJsonPath('requisito.no_aplica.aprobado_por', $revisor->name)
            ->assertJsonPath('progreso.total', 7);
    }

    public function test_un_administrador_aprueba_lo_que_marco_y_queda_registrado(): void
    {
        $this->conAprobacion();
        $admin = $this->usuario(Rol::Administrador);
        $this->marcarComo($admin)->assertOk();

        $this->como($admin)->postJson($this->url('/no-aplica/aprobar'))->assertOk()->assertJsonPath('requisito.estado', 'no_aplica');

        $registro = DB::table('checklist_historial')->where('evento', 'no_aplica_aprobado')->first();
        $this->assertTrue(json_decode($registro->despues, true)['autoaprobado']);
    }

    public function test_rechazar_vuelve_a_aplica_con_el_motivo_en_el_historial(): void
    {
        $this->conAprobacion();
        $this->marcarComo($this->usuario(Rol::Gestor))->assertOk();

        $this->como(Rol::Gestor)->postJson($this->url('/no-aplica/rechazar'), ['motivo' => 'Sí hay predios'])->assertOk()
            ->assertJsonPath('requisito.estado', 'pendiente');

        $registro = DB::table('checklist_historial')->where('evento', 'no_aplica_rechazado')->first();
        $this->assertSame('Sí hay predios', json_decode($registro->despues, true)['motivo']);
    }

    public function test_no_se_aprueba_lo_que_no_esta_por_aprobar(): void
    {
        $this->marcarComo($this->usuario(Rol::Gestor))->assertOk(); // sin aprobación: ya es no_aplica

        $this->como(Rol::Administrador)->postJson($this->url('/no-aplica/aprobar'))->assertStatus(422)
            ->assertJsonPath('mensaje', 'Este requisito no tiene un "no aplica" por aprobar.');
    }

    // ---------------------------------------------------------------- límites

    public function test_consulta_no_puede_marcar_no_aplica(): void
    {
        $this->como(Rol::Consulta)->postJson($this->url('/no-aplica'), ['justificacion' => 'x'])->assertForbidden();
    }

    public function test_un_expediente_radicado_no_admite_no_aplica(): void
    {
        $this->expediente->update(['estado' => ProyectoExpediente::RADICADO]);

        $this->marcarComo($this->usuario(Rol::Gestor))->assertStatus(422);
    }

    // ----------------------------------------------------------- responsable

    public function test_asignar_y_quitar_el_responsable_queda_registrado(): void
    {
        $ana = DB::table('responsable')->insertGetId(['nombre' => 'Ana Pérez', 'cargo' => 'Ingeniera', 'activo' => true]);

        $this->como(Rol::Gestor)->patchJson($this->url(), ['responsable_id' => $ana])->assertOk()
            ->assertJsonPath('requisito.responsable', ['id' => $ana, 'nombre' => 'Ana Pérez', 'cargo' => 'Ingeniera']);
        $this->como(Rol::Gestor)->patchJson($this->url(), ['responsable_id' => null])->assertOk()
            ->assertJsonPath('requisito.responsable', null);

        $registros = DB::table('checklist_historial')->where('evento', 'responsable_cambiado')->orderBy('id')->get();
        $this->assertSame(['responsable' => 'Ana Pérez'], json_decode($registros[0]->despues, true));
        $this->assertSame(['responsable' => 'Ana Pérez'], json_decode($registros[1]->antes, true));
    }

    public function test_no_se_asigna_un_responsable_inactivo_o_inexistente(): void
    {
        $inactivo = DB::table('responsable')->insertGetId(['nombre' => 'Luis', 'activo' => false]);

        $this->como(Rol::Gestor)->patchJson($this->url(), ['responsable_id' => $inactivo])->assertStatus(422)
            ->assertJsonPath('mensaje', 'El responsable "Luis" está inactivo.');
        $this->como(Rol::Gestor)->patchJson($this->url(), ['responsable_id' => 999])->assertStatus(422)
            ->assertJsonPath('mensaje', 'El responsable no existe.');
    }

    public function test_un_responsable_desactivado_se_conserva_en_la_fila_que_ya_lo_tiene(): void
    {
        $luis = DB::table('responsable')->insertGetId(['nombre' => 'Luis', 'activo' => true]);
        $this->como(Rol::Gestor)->patchJson($this->url(), ['responsable_id' => $luis])->assertOk();
        DB::table('responsable')->where('id', $luis)->update(['activo' => false]);

        $this->como(Rol::Gestor)->patchJson($this->url(), ['responsable_id' => $luis, 'observacion' => 'Sigue'])->assertOk();
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Viabilizacion/NoAplicaRequisitoApiTest.php`
Expected: FALLAN las pruebas, porque no existen las rutas `/no-aplica` (el comodín de `routes/web.php` responde 405 a un POST) y el PATCH ignora `responsable_id`.

- [ ] **Paso 3: Implementar**

Crear `app/Services/Viabilizacion/NoAplicaRequisito.php`:

```php
<?php

namespace App\Services\Viabilizacion;

use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "No aplica" de un requisito: lo marca una persona con justificación y, si el
 * requisito lo pide, otra lo aprueba. Mientras espera aprobación cuenta como pendiente.
 */
final class NoAplicaRequisito
{
    private const SIN_NO_APLICA = [
        'estado_aplicabilidad' => ProyectoRequisito::APLICA,
        'justificacion_no_aplica' => null,
        'usuario_no_aplica_id' => null,
        'usuario_aprobo_id' => null,
        'fecha_aprobacion' => null,
    ];

    public function __construct(
        private readonly ExpedienteService $expedientes,
        private readonly HistorialChecklist $historial,
    ) {
    }

    public function marcar(ProyectoExpediente $expediente, ProyectoRequisito $fila, string $justificacion, User $usuario): ProyectoRequisito
    {
        $this->expedientes->exigirEditable($expediente, $fila);

        $justificacion = trim($justificacion);
        if ($justificacion === '') {
            throw new ReglaNegocioException('Explique por qué el requisito no aplica.');
        }
        if ($fila->cumplido) {
            throw new ReglaNegocioException('El requisito está cumplido: desmárquelo antes de indicar que no aplica.');
        }
        if (! in_array($fila->estado_aplicabilidad, [ProyectoRequisito::APLICA, ProyectoRequisito::POR_DEFINIR], true)) {
            throw new ReglaNegocioException('El requisito ya está marcado como no aplica.');
        }

        $fila->loadMissing('requisito');
        $estado = $fila->requisito->requiere_aprobacion_no_aplica
            ? ProyectoRequisito::NO_APLICA_PENDIENTE
            : ProyectoRequisito::NO_APLICA;

        return $this->guardar($expediente, $fila, 'no_aplica_marcado', [
            ...self::SIN_NO_APLICA,
            'estado_aplicabilidad' => $estado,
            'justificacion_no_aplica' => $justificacion,
            'usuario_no_aplica_id' => $usuario->id,
        ], $usuario, ['justificacion' => $justificacion]);
    }

    /** Quien se equivocó lo deshace: el requisito vuelve a aplicar. */
    public function retirar(ProyectoExpediente $expediente, ProyectoRequisito $fila, User $usuario): ProyectoRequisito
    {
        $this->expedientes->exigirEditable($expediente, $fila);

        $marcado = [ProyectoRequisito::NO_APLICA_PENDIENTE, ProyectoRequisito::NO_APLICA];
        if (! in_array($fila->estado_aplicabilidad, $marcado, true)) {
            throw new ReglaNegocioException('El requisito no está marcado como no aplica.');
        }

        return $this->guardar($expediente, $fila, 'no_aplica_retirado', self::SIN_NO_APLICA, $usuario);
    }

    public function aprobar(ProyectoExpediente $expediente, ProyectoRequisito $fila, User $usuario): ProyectoRequisito
    {
        $this->expedientes->exigirEditable($expediente, $fila);
        $this->exigirRevisor($fila, $usuario);

        return $this->guardar($expediente, $fila, 'no_aplica_aprobado', [
            'estado_aplicabilidad' => ProyectoRequisito::NO_APLICA,
            'usuario_aprobo_id' => $usuario->id,
            'fecha_aprobacion' => now(),
        ], $usuario, ['autoaprobado' => $fila->usuario_no_aplica_id === $usuario->id]);
    }

    public function rechazar(ProyectoExpediente $expediente, ProyectoRequisito $fila, ?string $motivo, User $usuario): ProyectoRequisito
    {
        $this->expedientes->exigirEditable($expediente, $fila);
        $this->exigirRevisor($fila, $usuario);

        $motivo = trim((string) $motivo);

        return $this->guardar($expediente, $fila, 'no_aplica_rechazado', self::SIN_NO_APLICA, $usuario, [
            'motivo' => $motivo !== '' ? $motivo : null,
        ]);
    }

    private function exigirRevisor(ProyectoRequisito $fila, User $usuario): void
    {
        if ($fila->estado_aplicabilidad !== ProyectoRequisito::NO_APLICA_PENDIENTE) {
            throw new ReglaNegocioException('Este requisito no tiene un "no aplica" por aprobar.');
        }
        // Un Administrador puede revisar lo que él mismo marcó, y queda registrado: con un
        // solo usuario, el flujo se bloquearía.
        if ($usuario->rol !== Rol::Administrador && $fila->usuario_no_aplica_id === $usuario->id) {
            throw new ReglaNegocioException('Otra persona debe revisar este "no aplica": usted lo marcó.');
        }
    }

    private function guardar(ProyectoExpediente $expediente, ProyectoRequisito $fila, string $evento, array $cambios, User $usuario, array $extra = []): ProyectoRequisito
    {
        DB::transaction(function () use ($expediente, $fila, $evento, $cambios, $usuario, $extra) {
            $antes = ['estado' => $fila->estado_aplicabilidad, 'justificacion' => $fila->justificacion_no_aplica];
            $fila->update($cambios);
            $this->historial->registrarFila($fila, $evento, $antes, ['estado' => $fila->estado_aplicabilidad, ...$extra], $usuario);
            $expediente->touch();
        });

        return $fila;
    }
}
```

Crear `app/Http/Controllers/NoAplicaRequisitoController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\NoAplicaRequisito;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "No aplica" de un requisito del checklist. Rutas con can:editar-datos. */
class NoAplicaRequisitoController extends Controller
{
    private const MENSAJES = [
        'justificacion.required' => 'Explique por qué el requisito no aplica.',
        'justificacion.max' => 'La justificación admite hasta 2000 caracteres.',
        'motivo.max' => 'El motivo admite hasta 2000 caracteres.',
    ];

    public function __construct(
        private readonly NoAplicaRequisito $noAplica,
        private readonly ExpedienteService $expedientes,
    ) {
    }

    public function marcar(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        $datos = $request->validate(['justificacion' => ['required', 'string', 'max:2000']], self::MENSAJES);

        return $this->respuesta($expediente, $this->noAplica->marcar($expediente, $requisito, $datos['justificacion'], $request->user()));
    }

    public function retirar(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        return $this->respuesta($expediente, $this->noAplica->retirar($expediente, $requisito, $request->user()));
    }

    public function aprobar(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        return $this->respuesta($expediente, $this->noAplica->aprobar($expediente, $requisito, $request->user()));
    }

    public function rechazar(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        $datos = $request->validate(['motivo' => ['nullable', 'string', 'max:2000']], self::MENSAJES);

        return $this->respuesta($expediente, $this->noAplica->rechazar($expediente, $requisito, $datos['motivo'] ?? null, $request->user()));
    }

    private function respuesta(ProyectoExpediente $expediente, ProyectoRequisito $fila): JsonResponse
    {
        return response()->json([
            'requisito' => $this->expedientes->presentarFila($fila->refresh()),
            'progreso' => $this->expedientes->progresoDe($expediente),
        ]);
    }
}
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 5):

```php
use App\Models\RequisitoTipologia;
use App\Models\User;
```

por:

```php
use App\Models\RequisitoTipologia;
use App\Models\Responsable;
use App\Models\User;
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 5):

```php
    /** @param array{cumplido?: bool, observacion?: ?string} $datos */
```

por:

```php
    /** @param array{cumplido?: bool, observacion?: ?string, responsable_id?: ?int} $datos */
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (3 de 5):

```php
                ? trim($datos['observacion'])
                : null;
        }
```

por:

```php
                ? trim($datos['observacion'])
                : null;
        }
        if (array_key_exists('responsable_id', $datos)) {
            $fila->responsable_id = $this->responsableValido($datos['responsable_id'], $fila);
        }
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (4 de 5):

```php
        if ($fila->isDirty('observacion')) {
            $this->historial->registrarFila(
                $fila,
                'observacion_cambiada',
                ['observacion' => $fila->getOriginal('observacion')],
                ['observacion' => $fila->observacion],
                $usuario
            );
        }
    }
```

por:

```php
        if ($fila->isDirty('observacion')) {
            $this->historial->registrarFila(
                $fila,
                'observacion_cambiada',
                ['observacion' => $fila->getOriginal('observacion')],
                ['observacion' => $fila->observacion],
                $usuario
            );
        }
        if ($fila->isDirty('responsable_id')) {
            // Se guarda el nombre: el historial debe leerse aunque el responsable se elimine.
            $nombre = fn ($id) => $id ? Responsable::whereKey($id)->value('nombre') : null;
            $this->historial->registrarFila(
                $fila,
                'responsable_cambiado',
                ['responsable' => $nombre($fila->getOriginal('responsable_id'))],
                ['responsable' => $nombre($fila->responsable_id)],
                $usuario
            );
        }
    }

    /** Acepta un responsable inactivo solo si la fila ya lo tenía. */
    private function responsableValido(mixed $id, ProyectoRequisito $fila): ?int
    {
        if ($id === null) {
            return null;
        }

        $responsable = Responsable::find((int) $id)
            ?? throw new ReglaNegocioException('El responsable no existe.');
        if (! $responsable->activo && $responsable->id !== $fila->responsable_id) {
            throw new ReglaNegocioException("El responsable \"{$responsable->nombre}\" está inactivo.");
        }

        return $responsable->id;
    }
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (5 de 5):

```php
    private function exigirEditable(ProyectoExpediente $expediente, ProyectoRequisito $fila): void
```

por:

```php
    public function exigirEditable(ProyectoExpediente $expediente, ProyectoRequisito $fila): void
```

En `app/Http/Controllers/ExpedienteController.php`, reemplazar (1 de 1):

```php
            'observacion' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
```

por:

```php
            'observacion' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'responsable_id' => ['sometimes', 'nullable', 'integer'],
        ]);
```

En `routes/viabilizacion.php`, reemplazar (1 de 2):

```php
use App\Http\Controllers\ExpedienteController;
```

por:

```php
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\NoAplicaRequisitoController;
```

En `routes/viabilizacion.php`, reemplazar (2 de 2):

```php
        Route::delete('/expedientes/{expediente}/requisitos/{requisito}/soporte', [ExpedienteController::class, 'eliminarSoporte'])
            ->name('expedientes.requisitos.soporte.eliminar');
```

por:

```php
        Route::delete('/expedientes/{expediente}/requisitos/{requisito}/soporte', [ExpedienteController::class, 'eliminarSoporte'])
            ->name('expedientes.requisitos.soporte.eliminar');

        Route::post('/expedientes/{expediente}/requisitos/{requisito}/no-aplica', [NoAplicaRequisitoController::class, 'marcar'])
            ->name('expedientes.requisitos.no-aplica.marcar');
        Route::delete('/expedientes/{expediente}/requisitos/{requisito}/no-aplica', [NoAplicaRequisitoController::class, 'retirar'])
            ->name('expedientes.requisitos.no-aplica.retirar');
        Route::post('/expedientes/{expediente}/requisitos/{requisito}/no-aplica/aprobar', [NoAplicaRequisitoController::class, 'aprobar'])
            ->name('expedientes.requisitos.no-aplica.aprobar');
        Route::post('/expedientes/{expediente}/requisitos/{requisito}/no-aplica/rechazar', [NoAplicaRequisitoController::class, 'rechazar'])
            ->name('expedientes.requisitos.no-aplica.rechazar');
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Viabilizacion/NoAplicaRequisitoApiTest.php`
Expected: pasan las 14.

Run: `php artisan test`
Expected: pasan todas, 325.

- [ ] **Paso 5: Comitear**

```bash
git add app/Services/Viabilizacion/NoAplicaRequisito.php app/Http/Controllers/NoAplicaRequisitoController.php tests/Feature/Viabilizacion/NoAplicaRequisitoApiTest.php app/Services/Viabilizacion/ExpedienteService.php app/Http/Controllers/ExpedienteController.php routes/viabilizacion.php
```

```bash
git commit -m "feat: add not-applicable approval and requirement owner"
```

---

### Tarea 6: Lógica de pantalla

**Files:**
- Modify: `resources/js/utils/expedienteChecklist.js`
- Modify: `resources/js/utils/expedienteChecklist.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**


- [ ] **Paso 2: Comprobar que fallan**

Run: `npm test`
Expected: FALLAN las pruebas de `expedienteChecklist.test.js`, porque no existen `describirEvento`, `puedeMarcarNoAplica`, `puedeMarcarRequisito`, `puedeRevisarNoAplica` ni `responsablesDisponibles`.

- [ ] **Paso 3: Implementar**

En `resources/js/utils/expedienteChecklist.js`, reemplazar (1 de 3):

```js
const ETIQUETA_ESTADO = { borrador: 'Borrador', radicado: 'Radicado' };
```

por:

```js
const ETIQUETA_ESTADO = { borrador: 'Borrador', radicado: 'Radicado' };

/** Estado de un requisito del checklist. Lo calcula el backend. */
export const ETIQUETA_ESTADO_REQUISITO = {
    por_definir: 'Por definir',
    pendiente: 'Pendiente',
    cumplido: 'Cumplido',
    no_aplica_pendiente: 'No aplica, por aprobar',
    no_aplica: 'No aplica',
};

/** Solo se cumple lo que aplica; un "no aplica" se retira primero. */
export const puedeMarcarRequisito = (fila) => ['pendiente', 'cumplido'].includes(fila.estado);

export const puedeMarcarNoAplica = (fila) => ['pendiente', 'por_definir'].includes(fila.estado);

/**
 * Igual que el backend: revisa otra persona, salvo un Administrador, que puede revisar
 * lo que él mismo marcó.
 */
export const puedeRevisarNoAplica = (fila, usuario) =>
    fila.estado === 'no_aplica_pendiente'
    && (usuario?.rol === 'Administrador' || fila.no_aplica?.usuario_id !== usuario?.id);

/** Responsables que se pueden elegir: los activos y el que la fila ya tiene. */
export const responsablesDisponibles = (responsables, actualId) =>
    responsables.filter((r) => r.activo || r.id === actualId);

const EVENTOS = {
    cumplido_marcado: 'Marcó como cumplido',
    cumplido_desmarcado: 'Desmarcó el cumplimiento',
    observacion_cambiada: 'Cambió la observación',
    responsable_cambiado: 'Cambió el responsable',
    soporte_subido: 'Adjuntó un soporte',
    soporte_reemplazado: 'Reemplazó el soporte',
    soporte_eliminado: 'Eliminó el soporte',
    no_aplica_marcado: 'Indicó que no aplica',
    no_aplica_retirado: 'Retiró el "no aplica"',
    no_aplica_aprobado: 'Aprobó el "no aplica"',
    no_aplica_rechazado: 'Rechazó el "no aplica"',
};

/** Texto de una entrada del historial de un requisito. */
export const describirEvento = (entrada) => {
    const base = EVENTOS[entrada.evento] ?? entrada.evento;
    const d = entrada.despues ?? {};
    const a = entrada.antes ?? {};
    switch (entrada.evento) {
        case 'observacion_cambiada':
            return d.observacion ? `${base}: "${d.observacion}"` : `${base}: la quitó`;
        case 'responsable_cambiado':
            return d.responsable ? `${base}: ${d.responsable}` : `${base}: lo quitó (era ${a.responsable ?? '—'})`;
        case 'soporte_subido':
            return `${base}: ${d.soporte_nombre}`;
        case 'soporte_reemplazado':
            return `${base}: ${a.soporte_nombre} por ${d.soporte_nombre}`;
        case 'soporte_eliminado':
            return `${base}: ${a.soporte_nombre}`;
        case 'no_aplica_marcado':
            return `${base}: ${d.justificacion}`;
        case 'no_aplica_aprobado':
            return d.autoaprobado ? `${base} que él mismo marcó` : base;
        case 'no_aplica_rechazado':
            return d.motivo ? `${base}: ${d.motivo}` : base;
        default:
            return base;
    }
};
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (2 de 3):

```js
            total: deSeccion.length,
            cumplidos: deSeccion.filter((f) => f.cumplido).length,
```

por:

```js
            // Lo que cuenta lo decide el backend: lo no aplicable y lo opcional no suman.
            total: deSeccion.filter((f) => f.cuenta).length,
            cumplidos: deSeccion.filter((f) => f.cuenta && f.estado === 'cumplido').length,
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (3 de 3):

```js
        'Nota': r.nota ?? '',
        'Cumplido': f.cumplido ? 'Sí' : 'No',
        'Fecha de cumplimiento': soloFecha(f.fecha_cumplido),
        'Responsable': f.usuario_cumplido ?? '',
```

por:

```js
        'Nota': r.nota ?? '',
        'Estado': ETIQUETA_ESTADO_REQUISITO[f.estado] ?? f.estado,
        'Cumplido': f.cumplido ? 'Sí' : 'No',
        'Fecha de cumplimiento': soloFecha(f.fecha_cumplido),
        'Marcado por': f.usuario_cumplido ?? '',
        'Responsable': f.responsable?.nombre ?? '',
        'Justificación de no aplica': f.no_aplica?.justificacion ?? '',
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (1 de 8):

```js
    agruparRequisitos,
    alternarId,
    alternarSector,
    construirHojasExcel,
    mensajeError,
    nombreArchivoExcel,
    puedeRadicar,
```

por:

```js
    agruparRequisitos,
    alternarId,
    alternarSector,
    construirHojasExcel,
    describirEvento,
    mensajeError,
    nombreArchivoExcel,
    puedeMarcarNoAplica,
    puedeMarcarRequisito,
    puedeRadicar,
    puedeRevisarNoAplica,
    responsablesDisponibles,
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (2 de 8):

```js
const fila = (id, seccion, extra = {}) => ({
    id,
    cumplido: false,
```

por:

```js
const fila = (id, seccion, extra = {}) => ({
    id,
    estado: 'pendiente',
    cuenta: true,
    no_aplica: null,
    responsable: null,
    cumplido: false,
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (3 de 8):

```js
        const [a] = agruparRequisitos([fila(1, 'A', { fila: { cumplido: true } }), fila(2, 'A')]);
        expect(a.total).toBe(2);
        expect(a.cumplidos).toBe(1);
    });
```

por:

```js
        const [a] = agruparRequisitos([fila(1, 'A', { fila: { cumplido: true, estado: 'cumplido' } }), fila(2, 'A')]);
        expect(a.total).toBe(2);
        expect(a.cumplidos).toBe(1);
    });

    it('lo que no cuenta no suma al total ni a los cumplidos de la sección', () => {
        const [a] = agruparRequisitos([
            fila(1, 'A', { fila: { estado: 'no_aplica', cuenta: false } }),
            fila(2, 'A', { fila: { estado: 'cumplido', cumplido: true, cuenta: false } }), // opcional
            fila(3, 'A', { fila: { estado: 'no_aplica_pendiente', cumplido: true } }),
            fila(4, 'A'),
        ]);
        expect(a.total).toBe(2);
        expect(a.cumplidos).toBe(0);
    });
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (4 de 8):

```js
                fila: {
                    cumplido: true,
                    fecha_cumplido: '2026-11-03T15:00:00-05:00',
```

por:

```js
                fila: {
                    estado: 'cumplido',
                    responsable: { id: 3, nombre: 'Luis Gómez', cargo: null },
                    cumplido: true,
                    fecha_cumplido: '2026-11-03T15:00:00-05:00',
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (5 de 8):

```js
            fila(2, 'C', {
                requisito: { fuente: 'ot', sector: salud, tipo: { nombre: 'Infraestructura en salud' }, subtitulo: '7.1' },
            }),
        ],
    };
```

por:

```js
            fila(2, 'C', {
                fila: { estado: 'no_aplica', cuenta: false, no_aplica: { justificacion: 'Sin predios' } },
                requisito: { fuente: 'ot', sector: salud, tipo: { nombre: 'Infraestructura en salud' }, subtitulo: '7.1' },
            }),
        ],
    };
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (6 de 8):

```js
            'Cumplido': 'Sí',
            'Fecha de cumplimiento': '2026-11-03',
            'Responsable': 'Ana',
```

por:

```js
            'Estado': 'Cumplido',
            'Cumplido': 'Sí',
            'Fecha de cumplimiento': '2026-11-03',
            'Marcado por': 'Ana',
            'Responsable': 'Luis Gómez',
            'Justificación de no aplica': '',
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (7 de 8):

```js
            'Cumplido': 'No',
            'Fecha de cumplimiento': '',
        });
```

por:

```js
            'Estado': 'No aplica',
            'Cumplido': 'No',
            'Fecha de cumplimiento': '',
            'Justificación de no aplica': 'Sin predios',
        });
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (8 de 8):

```js
describe('puedeRadicar', () => {
```

por:

```js
describe('acciones sobre un requisito', () => {
    it('solo se cumple lo pendiente o lo ya cumplido', () => {
        expect(puedeMarcarRequisito({ estado: 'pendiente' })).toBe(true);
        expect(puedeMarcarRequisito({ estado: 'cumplido' })).toBe(true);
        expect(puedeMarcarRequisito({ estado: 'no_aplica' })).toBe(false);
        expect(puedeMarcarRequisito({ estado: 'por_definir' })).toBe(false);
    });

    it('se marca no aplica lo pendiente o por definir, nunca lo cumplido', () => {
        expect(puedeMarcarNoAplica({ estado: 'pendiente' })).toBe(true);
        expect(puedeMarcarNoAplica({ estado: 'por_definir' })).toBe(true);
        expect(puedeMarcarNoAplica({ estado: 'cumplido' })).toBe(false);
        expect(puedeMarcarNoAplica({ estado: 'no_aplica_pendiente' })).toBe(false);
    });

    it('revisa otra persona, o un Administrador aunque lo haya marcado', () => {
        const porAprobar = { estado: 'no_aplica_pendiente', no_aplica: { usuario_id: 5 } };
        expect(puedeRevisarNoAplica(porAprobar, { id: 5, rol: 'Gestor' })).toBe(false);
        expect(puedeRevisarNoAplica(porAprobar, { id: 6, rol: 'Gestor' })).toBe(true);
        expect(puedeRevisarNoAplica(porAprobar, { id: 5, rol: 'Administrador' })).toBe(true);
        expect(puedeRevisarNoAplica({ ...porAprobar, estado: 'no_aplica' }, { id: 6, rol: 'Gestor' })).toBe(false);
    });

    it('ofrece los responsables activos y conserva el actual aunque esté inactivo', () => {
        const lista = [{ id: 1, activo: true }, { id: 2, activo: false }, { id: 3, activo: false }];
        expect(responsablesDisponibles(lista, 2).map((r) => r.id)).toEqual([1, 2]);
        expect(responsablesDisponibles(lista, null).map((r) => r.id)).toEqual([1]);
    });
});

describe('describirEvento', () => {
    it('explica cada cambio con sus datos', () => {
        expect(describirEvento({ evento: 'cumplido_marcado' })).toBe('Marcó como cumplido');
        expect(describirEvento({ evento: 'soporte_reemplazado', antes: { soporte_nombre: 'v1.pdf' }, despues: { soporte_nombre: 'v2.pdf' } }))
            .toBe('Reemplazó el soporte: v1.pdf por v2.pdf');
        expect(describirEvento({ evento: 'no_aplica_marcado', despues: { justificacion: 'Sin predios' } }))
            .toBe('Indicó que no aplica: Sin predios');
        expect(describirEvento({ evento: 'no_aplica_aprobado', despues: { autoaprobado: true } }))
            .toBe('Aprobó el "no aplica" que él mismo marcó');
        expect(describirEvento({ evento: 'responsable_cambiado', antes: { responsable: 'Ana' }, despues: { responsable: null } }))
            .toBe('Cambió el responsable: lo quitó (era Ana)');
        expect(describirEvento({ evento: 'otro_evento' })).toBe('otro_evento');
    });
});

describe('puedeRadicar', () => {
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npm test`
Expected: pasan todas, 140.

- [ ] **Paso 5: Comitear**

```bash
git add resources/js/utils/expedienteChecklist.js resources/js/utils/expedienteChecklist.test.js
```

```bash
git commit -m "feat: add requirement state helpers to the checklist screen logic"
```

---

### Tarea 7: Acciones en la pantalla del expediente

Sin pruebas automáticas de componentes en el repositorio: se comprueba en el navegador en la tarea 8.

**Files:**
- Create: `resources/js/components/ExpedienteHistorialRequisito.jsx`
- Modify: `resources/js/components/ExpedienteChecklist.jsx`
- Modify: `resources/js/components/ExpedienteViabilizacion.jsx`
- Modify: `resources/css/ExpedienteViabilizacion.css`
- Generated: `public/js/app.js`, `public/js/app.js.map`, `public/mix-manifest.json`

- [ ] **Paso 1: Implementar**

Crear `resources/js/components/ExpedienteHistorialRequisito.jsx`:

```jsx
import React, { useEffect, useState } from 'react';
import { faDownload } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { describirEvento, mensajeError } from '../utils/expedienteChecklist';

const fechaHora = (iso) => (iso ? new Date(iso).toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'short' }) : '');

/** Historial de un requisito. Se vuelve a pedir cada vez que la fila cambia. */
const ExpedienteHistorialRequisito = ({ fila, onCargar, onDescargar }) => {
    const [entradas, setEntradas] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        let vigente = true;
        setError(null);
        onCargar(fila)
            .then((datos) => { if (vigente) setEntradas(datos); })
            .catch((e) => { if (vigente) setError(mensajeError(e)); });
        return () => { vigente = false; };
    }, [fila, onCargar]);

    if (error) return <p className="exv-historial-vacio">{error}</p>;
    if (entradas === null) return <p className="exv-historial-vacio">Cargando historial…</p>;
    if (entradas.length === 0) return <p className="exv-historial-vacio">Sin cambios registrados todavía.</p>;

    return (
        <ol className="exv-historial">
            {entradas.map((entrada) => (
                <li key={entrada.id}>
                    <span className="exv-historial-fecha">{fechaHora(entrada.fecha)}</span>
                    <span><b>{entrada.usuario ?? 'Usuario eliminado'}</b> · {describirEvento(entrada)}</span>
                    {entrada.soporte && (
                        <button type="button" className="exv-mini" onClick={() => onDescargar(entrada)}>
                            <FontAwesomeIcon icon={faDownload} /> {entrada.soporte.nombre}
                        </button>
                    )}
                </li>
            ))}
        </ol>
    );
};

export default ExpedienteHistorialRequisito;
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (1 de 5):

```jsx
import { faPaperclip, faDownload, faTrash, faCommentDots } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { ETIQUETA_FUENTE, agruparRequisitos } from '../utils/expedienteChecklist';
```

por:

```jsx
import {
    faPaperclip, faDownload, faTrash, faCommentDots, faBan, faRotateLeft, faCheck, faXmark, faClockRotateLeft,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    ETIQUETA_ESTADO_REQUISITO,
    ETIQUETA_FUENTE,
    agruparRequisitos,
    puedeMarcarNoAplica,
    puedeMarcarRequisito,
    puedeRevisarNoAplica,
    responsablesDisponibles,
} from '../utils/expedienteChecklist';
import ExpedienteHistorialRequisito from './ExpedienteHistorialRequisito';
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (2 de 5):

```jsx
const ItemRequisito = ({ fila, editable, onMarcar, onObservacion, onSubirSoporte, onDescargarSoporte, onEliminarSoporte }) => {
    const { requisito: r } = fila;
    const [viendoObservacion, setViendoObservacion] = useState(Boolean(fila.observacion));

    return (
        <article className={`exv-item${fila.cumplido ? ' cumplido' : ''}`}>
            <label className="exv-item-principal">
                <input
                    type="checkbox"
                    checked={fila.cumplido}
                    disabled={!editable}
                    onChange={(e) => onMarcar(fila, e.target.checked)}
                />
                <div className="exv-item-cuerpo">
                    <div className="exv-item-tags">
                        <span className="exv-codigo">{r.codigo}</span>
                        <span className={`exv-fuente exv-fuente-${r.fuente}`}>{ETIQUETA_FUENTE[r.fuente]}</span>
                    </div>
```

por:

```jsx
const ItemRequisito = ({
    fila, editable, usuario, responsables, onMarcar, onObservacion, onSubirSoporte, onDescargarSoporte, onEliminarSoporte,
    onNoAplica, onRetirarNoAplica, onAprobarNoAplica, onRechazarNoAplica, onResponsable, onCargarHistorial, onDescargarHistorial,
}) => {
    const { requisito: r } = fila;
    const [viendoObservacion, setViendoObservacion] = useState(Boolean(fila.observacion));
    const [viendoHistorial, setViendoHistorial] = useState(false);
    const marcadoNoAplica = ['no_aplica_pendiente', 'no_aplica'].includes(fila.estado);
    const opciones = responsablesDisponibles(responsables, fila.responsable?.id ?? null);

    return (
        <article className={`exv-item${fila.estado === 'cumplido' ? ' cumplido' : ''}${fila.cuenta ? '' : ' no-cuenta'}`}>
            <label className="exv-item-principal">
                <input
                    type="checkbox"
                    checked={fila.cumplido}
                    disabled={!editable || !puedeMarcarRequisito(fila)}
                    onChange={(e) => onMarcar(fila, e.target.checked)}
                />
                <div className="exv-item-cuerpo">
                    <div className="exv-item-tags">
                        <span className="exv-codigo">{r.codigo}</span>
                        <span className={`exv-fuente exv-fuente-${r.fuente}`}>{ETIQUETA_FUENTE[r.fuente]}</span>
                        {fila.estado !== 'pendiente' && fila.estado !== 'cumplido' && (
                            <span className={`exv-estado exv-estado-${fila.estado}`}>{ETIQUETA_ESTADO_REQUISITO[fila.estado]}</span>
                        )}
                        {fila.caracter !== 'obligatorio' && <span className="exv-estado">{fila.caracter === 'opcional' ? 'Opcional' : 'Condicionado'}</span>}
                    </div>
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (3 de 5):

```jsx
            {fila.cumplido && fila.fecha_cumplido && (
                <div className="exv-item-meta">
                    Cumplido el {fechaCorta(fila.fecha_cumplido)}
                    {fila.usuario_cumplido && ` por ${fila.usuario_cumplido}`}
                </div>
            )}
```

por:

```jsx
            {fila.cumplido && fila.fecha_cumplido && (
                <div className="exv-item-meta">
                    Cumplido el {fechaCorta(fila.fecha_cumplido)}
                    {fila.usuario_cumplido && ` por ${fila.usuario_cumplido}`}
                </div>
            )}

            {fila.no_aplica && (
                <div className="exv-no-aplica">
                    <b>{ETIQUETA_ESTADO_REQUISITO[fila.estado]}:</b> {fila.no_aplica.justificacion}
                    <span className="exv-no-aplica-quien">
                        {fila.no_aplica.usuario && ` Marcado por ${fila.no_aplica.usuario}.`}
                        {fila.no_aplica.aprobado_por && ` Aprobado por ${fila.no_aplica.aprobado_por} el ${fechaCorta(fila.no_aplica.fecha_aprobacion)}.`}
                        {fila.estado === 'no_aplica_pendiente' && r.cargo_revision && ` Debería revisarlo: ${r.cargo_revision}.`}
                    </span>
                </div>
            )}

            <div className="exv-responsable">
                <span>Responsable:</span>
                {editable ? (
                    <select
                        value={fila.responsable?.id ?? ''}
                        aria-label="Responsable del requisito"
                        onChange={(e) => onResponsable(fila, e.target.value ? Number(e.target.value) : null)}
                    >
                        <option value="">Sin asignar</option>
                        {opciones.map((p) => (
                            <option key={p.id} value={p.id}>{p.nombre}{p.cargo ? ` (${p.cargo})` : ''}</option>
                        ))}
                    </select>
                ) : (
                    <b>{fila.responsable ? fila.responsable.nombre : 'Sin asignar'}</b>
                )}
                {r.cargo_cumplimiento && <span className="exv-responsable-cargo">Cargo indicado: {r.cargo_cumplimiento}</span>}
            </div>
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (4 de 5):

```jsx
                {fila.soporte && (
                    <>
                        <button type="button" className="exv-mini" onClick={() => onDescargarSoporte(fila)}>
```

por:

```jsx
                {editable && puedeMarcarNoAplica(fila) && (
                    <button type="button" className="exv-mini" onClick={() => onNoAplica(fila)}>
                        <FontAwesomeIcon icon={faBan} /> No aplica
                    </button>
                )}
                {editable && marcadoNoAplica && (
                    <button type="button" className="exv-mini" onClick={() => onRetirarNoAplica(fila)}>
                        <FontAwesomeIcon icon={faRotateLeft} /> Retirar "no aplica"
                    </button>
                )}
                {editable && puedeRevisarNoAplica(fila, usuario) && (
                    <>
                        <button type="button" className="exv-mini exv-mini-exito" onClick={() => onAprobarNoAplica(fila)}>
                            <FontAwesomeIcon icon={faCheck} /> Aprobar
                        </button>
                        <button type="button" className="exv-mini exv-mini-peligro" onClick={() => onRechazarNoAplica(fila)}>
                            <FontAwesomeIcon icon={faXmark} /> Rechazar
                        </button>
                    </>
                )}
                <button type="button" className="exv-mini" aria-expanded={viendoHistorial} onClick={() => setViendoHistorial((v) => !v)}>
                    <FontAwesomeIcon icon={faClockRotateLeft} /> Historial
                </button>
                {fila.soporte && (
                    <>
                        <button type="button" className="exv-mini" onClick={() => onDescargarSoporte(fila)}>
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (5 de 5):

```jsx
            {/* Solo impresión: los controles anteriores no salen en el PDF. */}
            {(fila.observacion || fila.soporte) && (
                <div className="exv-solo-impresion exv-item-impresion">
                    {fila.observacion && <div><b>Observación:</b> {fila.observacion}</div>}
                    {fila.soporte && <div><b>Soporte:</b> {fila.soporte.nombre}</div>}
                </div>
            )}
```

por:

```jsx
            {viendoHistorial && (
                <ExpedienteHistorialRequisito fila={fila} onCargar={onCargarHistorial} onDescargar={onDescargarHistorial} />
            )}

            {/* Solo impresión: los controles anteriores no salen en el PDF. */}
            {(fila.observacion || fila.soporte || fila.responsable) && (
                <div className="exv-solo-impresion exv-item-impresion">
                    {fila.responsable && <div><b>Responsable:</b> {fila.responsable.nombre}</div>}
                    {fila.observacion && <div><b>Observación:</b> {fila.observacion}</div>}
                    {fila.soporte && <div><b>Soporte:</b> {fila.soporte.nombre}</div>}
                </div>
            )}
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (1 de 5):

```jsx
    const [errorCarga, setErrorCarga] = useState(null);
```

por:

```jsx
    const [errorCarga, setErrorCarga] = useState(null);
    const [responsables, setResponsables] = useState([]);
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (2 de 5):

```jsx
    useEffect(() => () => clearTimeout(envio.current.temporizador), []);
```

por:

```jsx
    useEffect(() => () => clearTimeout(envio.current.temporizador), []);

    // Sin la lista, el checklist igual funciona: solo no se puede elegir responsable.
    useEffect(() => {
        let vigente = true;
        axios.get('/responsables')
            .then(({ data }) => { if (vigente) setResponsables(data); })
            .catch(() => { if (vigente) setResponsables([]); });
        return () => { vigente = false; };
    }, []);
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (3 de 5):

```jsx
    const marcar = async (fila, cumplido) => {
        reemplazarFila({ ...fila, cumplido });
```

por:

```jsx
    const marcar = async (fila, cumplido) => {
        reemplazarFila({ ...fila, cumplido, estado: cumplido ? 'cumplido' : 'pendiente' });
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (4 de 5):

```jsx
    const descargarSoporte = async (fila) => {
        try {
            // Con axios, para enviar el JWT: un enlace directo no lleva la cabecera.
            const { data } = await axios.get(`${urlFila(fila)}/soporte`, { responseType: 'blob' });
            const url = URL.createObjectURL(data);
            const enlace = document.createElement('a');
            enlace.href = url;
            enlace.download = fila.soporte.nombre;
            enlace.click();
            URL.revokeObjectURL(url);
        } catch (error) {
            avisarError('No se pudo descargar el soporte', error);
        }
    };
```

por:

```jsx
    const descargar = async (ruta, nombre) => {
        try {
            // Con axios, para enviar el JWT: un enlace directo no lleva la cabecera.
            const { data } = await axios.get(ruta, { responseType: 'blob' });
            const url = URL.createObjectURL(data);
            const enlace = document.createElement('a');
            enlace.href = url;
            enlace.download = nombre;
            enlace.click();
            URL.revokeObjectURL(url);
        } catch (error) {
            avisarError('No se pudo descargar el soporte', error);
        }
    };

    const descargarSoporte = (fila) => descargar(`${urlFila(fila)}/soporte`, fila.soporte.nombre);

    const descargarSoporteHistorial = (entrada) =>
        descargar(`/expedientes/${expediente.id}/historial/${entrada.id}/soporte`, entrada.soporte.nombre);

    const cargarHistorial = useCallback(
        async (fila) => (await axios.get(`/expedientes/${expediente.id}/requisitos/${fila.id}/historial`)).data,
        [expediente?.id]
    );

    const accionFila = async (peticion, titulo) => {
        try {
            const { data } = await peticion();
            reemplazarFila(data.requisito, data.progreso);
        } catch (error) {
            avisarError(titulo, error);
        }
    };

    const marcarNoAplica = async (fila) => {
        const { isConfirmed, value } = await Swal.fire({
            title: 'Este requisito no aplica',
            text: fila.requisito.requiere_aprobacion_no_aplica ? 'Otra persona deberá aprobarlo.' : undefined,
            input: 'textarea',
            inputLabel: 'Justificación',
            inputPlaceholder: 'Explique por qué no aplica a este proyecto',
            inputAttributes: { maxlength: '2000' },
            inputValidator: (texto) => (!texto?.trim() ? 'Escriba la justificación.' : undefined),
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        await accionFila(() => axios.post(`${urlFila(fila)}/no-aplica`, { justificacion: value }), 'No se pudo marcar como no aplica');
    };

    const retirarNoAplica = (fila) =>
        accionFila(() => axios.delete(`${urlFila(fila)}/no-aplica`), 'No se pudo retirar el "no aplica"');

    const aprobarNoAplica = (fila) =>
        accionFila(() => axios.post(`${urlFila(fila)}/no-aplica/aprobar`), 'No se pudo aprobar el "no aplica"');

    const rechazarNoAplica = async (fila) => {
        const { isConfirmed, value } = await Swal.fire({
            title: '¿Rechazar el "no aplica"?',
            text: 'El requisito volverá a estar pendiente.',
            input: 'textarea',
            inputLabel: 'Motivo (opcional)',
            inputAttributes: { maxlength: '2000' },
            showCancelButton: true,
            confirmButtonText: 'Rechazar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        await accionFila(() => axios.post(`${urlFila(fila)}/no-aplica/rechazar`, { motivo: value || null }), 'No se pudo rechazar el "no aplica"');
    };

    const asignarResponsable = (fila, responsableId) =>
        accionFila(() => axios.patch(urlFila(fila), { responsable_id: responsableId }), 'No se pudo asignar el responsable');
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (5 de 5):

```jsx
                                        onDescargarSoporte={descargarSoporte}
                                        onEliminarSoporte={eliminarSoporte}
                                    />
```

por:

```jsx
                                        onDescargarSoporte={descargarSoporte}
                                        onEliminarSoporte={eliminarSoporte}
                                        usuario={user}
                                        responsables={responsables}
                                        onNoAplica={marcarNoAplica}
                                        onRetirarNoAplica={retirarNoAplica}
                                        onAprobarNoAplica={aprobarNoAplica}
                                        onRechazarNoAplica={rechazarNoAplica}
                                        onResponsable={asignarResponsable}
                                        onCargarHistorial={cargarHistorial}
                                        onDescargarHistorial={descargarSoporteHistorial}
                                    />
```

En `resources/css/ExpedienteViabilizacion.css`, reemplazar (1 de 2):

```css
.exv-item-acciones { display: flex; flex-wrap: wrap; gap: .35rem; margin: .45rem 0 0 calc(17px + .8rem); }
```

por:

```css
.exv-item-acciones { display: flex; flex-wrap: wrap; gap: .35rem; margin: .45rem 0 0 calc(17px + .8rem); }
.exv-item.no-cuenta { opacity: .72; }
.exv-estado { padding: .06rem .4rem; font-size: .66rem; font-weight: 600; color: var(--exv-tinta-suave); background: var(--gp-superficie-3); border-radius: 2px; }
.exv-estado-no_aplica_pendiente, .exv-estado-por_definir { color: var(--exv-tinta); background: var(--exv-alerta-tinte); }
.exv-no-aplica { margin: .4rem 0 0 calc(17px + .8rem); padding: .4rem .6rem; font-size: .8rem; color: var(--exv-tinta); background: var(--exv-control); border-left: 3px solid var(--exv-alerta); border-radius: 2px; }
.exv-no-aplica-quien { color: var(--exv-tinta-suave); }
.exv-responsable { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; margin: .4rem 0 0 calc(17px + .8rem); font-size: .78rem; color: var(--exv-tinta-suave); }
.exv-responsable select { max-width: 100%; padding: .18rem .4rem; font: inherit; color: var(--exv-tinta); background: var(--exv-control); border: 1px solid var(--exv-linea); border-radius: 2px; }
.exv-responsable b { color: var(--exv-tinta); font-weight: 600; }
.exv-responsable-cargo { color: var(--exv-tinta-tenue); }
.exv-historial { margin: .5rem 0 0 calc(17px + .8rem); padding: 0; list-style: none; font-size: .78rem; color: var(--exv-tinta-suave); }
.exv-historial li { display: flex; flex-wrap: wrap; gap: .3rem .6rem; align-items: center; padding: .3rem 0; border-top: 1px solid var(--exv-linea-suave); }
.exv-historial b { color: var(--exv-tinta); font-weight: 600; }
.exv-historial-fecha { color: var(--exv-tinta-tenue); font-variant-numeric: tabular-nums; }
.exv-historial-vacio { margin: .5rem 0 0 calc(17px + .8rem); font-size: .78rem; color: var(--exv-tinta-tenue); }
```

En `resources/css/ExpedienteViabilizacion.css`, reemplazar (2 de 2):

```css
    .gp-riel, .gp-barra-superior, .gp-barra-inferior, .exv-config, .exv-barra, .exv-no-imprimir, .exv-item-acciones, .exv-observacion, .exv-observacion-lectura { display: none !important; }
```

por:

```css
    .gp-riel, .gp-barra-superior, .gp-barra-inferior, .exv-config, .exv-barra, .exv-no-imprimir, .exv-item-acciones, .exv-observacion, .exv-observacion-lectura, .exv-responsable, .exv-historial, .exv-historial-vacio { display: none !important; }
```

En `resources/css/ExpedienteViabilizacion.css`, reemplazar (1 de 1):

```css
.exv-mini-peligro:hover { border-color: var(--exv-alerta); color: var(--exv-alerta); }
```

por:

```css
.exv-mini-peligro:hover { border-color: var(--exv-alerta); color: var(--exv-alerta); }
.exv-mini-exito:hover { border-color: var(--exv-verde); color: var(--exv-verde); }
```

- [ ] **Paso 2: Compilar**

Run: `npm run production`
Expected: `Compiled Successfully`, sin errores.

- [ ] **Paso 3: Ejecutar las pruebas**

Run: `npm test`
Expected: pasan todas, 140.

- [ ] **Paso 4: Comitear**

```bash
git add resources/js/components/ExpedienteHistorialRequisito.jsx resources/js/components/ExpedienteChecklist.jsx resources/js/components/ExpedienteViabilizacion.jsx resources/css/ExpedienteViabilizacion.css public/js/app.js public/js/app.js.map public/mix-manifest.json
```

```bash
git commit -m "feat: show state, owner, not-applicable and history on each requirement"
```

---

### Tarea 8: Verificación en la copia descartable

Usa la copia y las fotos de la tarea 1. Nunca `gest_pro`.

- [ ] **Paso 1: Migrar la copia y comparar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

Expected: se aplican las 3 migraciones nuevas.

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_etapa3.php" "$TMP_DIR/e3_despues.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_etapa3.php" "$TMP_DIR/e3_antes.json" "$TMP_DIR/e3_despues.json"
```

Expected: `SIN DIFERENCIAS`. Cualquier diferencia detiene la etapa.

- [ ] **Paso 2: Revisar lo migrado**

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT estado_aplicabilidad, caracter, COUNT(*) FROM proyectos_requisitos GROUP BY 1, 2; SELECT COUNT(*) AS total, SUM(codigo_comun = clave) AS comun, SUM(caracter = 'obligatorio') AS obligatorios FROM requisitos_catalogo; SELECT COUNT(*) AS historial FROM checklist_historial;"
```

Expected: todas las filas en `aplica` y `obligatorio`, los 207 requisitos con `codigo_comun = clave` y obligatorios, y el historial vacío.

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SHOW CREATE TABLE proyectos_requisitos\G SHOW CREATE TABLE requisitos_catalogo\G SHOW CREATE TABLE checklist_historial\G"
```

Expected: en `proyectos_requisitos`, claves foráneas `SET NULL` de `usuario_no_aplica_id`, `usuario_aprobo_id` y `responsable_id`. En `requisitos_catalogo`, `seccion`, `fuente` y `codigo` aceptan nulos y se conservan sus índices. En `checklist_historial`, los índices `checklist_historial_entidad_idx` y `checklist_historial_expediente_idx`, sin claves foráneas.

- [ ] **Paso 3: Revertir y comparar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=3 --force
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_etapa3.php" "$TMP_DIR/e3_rollback.json" gestpro_verif_tmp --solo-tablas
```

```bash
php "$TMP_DIR/gestpro_comparar_etapa3.php" "$TMP_DIR/e3_tablas_antes.json" "$TMP_DIR/e3_rollback.json"
```

Expected: `SIN DIFERENCIAS`, y `seccion`, `fuente` y `codigo` vuelven a ser obligatorios.

- [ ] **Paso 4: Volver a migrar y comparar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_etapa3.php" "$TMP_DIR/e3_remigrado.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_etapa3.php" "$TMP_DIR/e3_antes.json" "$TMP_DIR/e3_remigrado.json"
```

Expected: `SIN DIFERENCIAS`.

- [ ] **Paso 5: Probar en el navegador sobre la copia**

Arrancar el servidor desde `public/` (`artisan serve` recargaría `gest_pro` desde `.env`):

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8765 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Crear un usuario de prueba solo en la copia; nunca usar sesiones de usuarios reales de `gest_pro`:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'verif@gestpro.test', 'rol' => App\Enums\Rol::Administrador]);"
```

Para probar la aprobación, activarla en un requisito, solo en la copia:

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "UPDATE requisitos_catalogo SET requiere_aprobacion_no_aplica = 1 WHERE clave = 'a8';"
```

La contraseña es la predeterminada de `UserFactory`. En un expediente en borrador comprobar:

1. Marcar un requisito pendiente como "no aplica" sin justificación muestra "Escriba la justificación."; con justificación, el total del avance baja en 1 y la casilla queda deshabilitada.
2. Asignar un responsable y abrir el historial: aparecen el "no aplica" y el responsable, con fecha y usuario.
3. En el requisito `a8`, el diálogo avisa "Otra persona deberá aprobarlo."; queda "No aplica, por aprobar", el total no baja y aparecen Aprobar y Rechazar. Aprobar baja el total y registra que lo aprobó quien lo marcó.
4. Retirar el "no aplica" devuelve el requisito a pendiente y el total sube.
5. Reemplazar un soporte: el historial ofrece descargar el anterior.
6. El Excel trae las columnas Estado, Marcado por, Responsable y Justificación de no aplica.
7. El expediente radicado no muestra acciones, y la consola del navegador no muestra errores.

- [ ] **Paso 6: Limpiar**

Detener el servidor y eliminar la copia:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 7: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas, 325.

Run: `npm test`
Expected: pasan todas, 140.

- [ ] **Paso 8: Informar al usuario y pedir validación**

Informar: totales de pruebas, resultado de las tres comparaciones y lo revisado en el navegador. Pedir dos decisiones por separado: si valida la etapa y si autoriza aplicar las migraciones en `gest_pro`.

---

### Tarea 9: Aplicar en `gest_pro` (solo con autorización)

No ejecutar esta tarea sin un sí explícito del usuario a este paso. Si la comparación muestra diferencias, restaurar el respaldo: no usar `migrate:rollback`.

- [ ] **Paso 1: Respaldar**

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro > "$TMP_DIR/gest_pro_antes_etapa_3.sql"
```

Expected: el archivo existe y no está vacío.

- [ ] **Paso 2: Tomar la foto de las tablas antes de migrar**

Con el código nuevo, la API ya espera las columnas de seguimiento, así que antes de migrar solo se toman las tablas:

```bash
php "$TMP_DIR/gestpro_foto_etapa3.php" "$TMP_DIR/e3_real_antes.json" gest_pro --solo-tablas
```

- [ ] **Paso 3: Migrar hacia adelante**

Run: `php artisan migrate --force`
Expected: se aplican las 3 migraciones nuevas.

- [ ] **Paso 4: Comparar**

```bash
php "$TMP_DIR/gestpro_foto_etapa3.php" "$TMP_DIR/e3_real_despues.json" gest_pro
```

```bash
php "$TMP_DIR/gestpro_comparar_etapa3.php" "$TMP_DIR/e3_real_antes.json" "$TMP_DIR/e3_real_despues.json"
```

Expected: `SIN DIFERENCIAS`.

- [ ] **Paso 5: Informar**

Dar el resultado y la ruta completa del respaldo, y avisar que está en una carpeta temporal.

---

## Criterio de la etapa

La etapa está lista cuando, según el diseño:

- Estados, responsables, "no aplica" con aprobación e historial funcionan sobre expedientes SGR.
- Las cifras de los expedientes existentes no cambian, ni al migrar ni al revertir.
- El avance se calcula en un solo lugar, y el tablero, el detalle y la radicación coinciden.
