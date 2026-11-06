# Plantillas de checklist, etapa 2: plantillas sobre versiones. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** convertir el checklist SGR en la primera plantilla, de modo que varias plantillas puedan tener versiones propias, sin cambiar ningún expediente ni nada en pantalla.

**Arquitectura:** tres migraciones agregan `plantillas_checklist` encima de `catalogo_versiones` y registran la versión de cada expediente en `expediente_plantillas`. Ningún dato se mueve. `CatalogoService` pasa a trabajar por plantilla, con la SGR como valor por defecto, y la búsqueda de la versión vigente deja de ser global. El checklist se sigue leyendo de `proyectos_expedientes.catalogo_version_id`; la tabla nueva solo se escribe, y se empieza a leer en la etapa 5.

**Tecnologías:** Laravel 12, PHP 8.2, MariaDB 10.4, PHPUnit sobre SQLite en memoria.

**Diseño:** `docs/superpowers/specs/2026-11-06-plantillas-checklist-design.md`, migraciones 4 a 6 de la sección 4 y pasos 1 y 2 de la sección 7.

**Estado de partida:** rama `feature/plantillas-checklist`, 248 pruebas PHP pasando, etapa 1 validada y aplicada en `gest_pro`.

---

## Reglas de ejecución

- **Nada de esto se aplica a `gest_pro` sin autorización del usuario.** Nunca se usa `migrate:rollback` allí, porque sus lotes están mezclados: si hay que volver atrás, se restaura el respaldo.
- Commits autorizados solo en `feature/plantillas-checklist`. Sin push. Los mensajes van en inglés, como el historial, y terminan con `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Nomenclatura en español sin tildes para tablas, columnas y métodos nuevos.
- Comando de pruebas: `php artisan test`, sin definir `DB_DATABASE`, porque `phpunit.xml` ya usa SQLite en memoria.
- **Editar PHP con la herramienta Edit, no con `sed`:** `sed` ya eliminó las barras invertidas de líneas `use` en la etapa 1.
- Esta etapa no toca JavaScript ni CSS, así que no hace falta compilar `public/`. El usuario suele tener `npm run watch` corriendo: si `public/` aparece modificado, no se comitea.

## Fuera de esta etapa

- Pantallas y rutas de administración de plantillas: etapa 4. Por eso `/catalogo-requisitos/*` sigue atendiendo solo a la plantilla SGR.
- Que `sincronizar` recorra varias plantillas, y cualquier lectura de `expediente_plantillas`: etapa 5.
- Reglas por motor en `guardarRequisito` y `publicar`: etapa 4, cuando pueda existir una plantilla con motor `simple` y requisitos.
- Retirar `proyectos_expedientes.catalogo_version_id`: etapa 6.
- El cambio de nombre a "Expediente" en pantalla: etapa 5.

## Lo que ya se ensayó

Este plan se ensayó antes de escribirlo, y luego se retiró todo. Las 268 pruebas pasaron con este código. En una copia de `gest_pro`, migrar, repetir la migración, reintentar tras un fallo parcial y hacer rollback funcionaron, y la foto de los expedientes salió idéntica. Las pruebas de la versión vigente, la publicación, el seeder, la lista de versiones y el registro del expediente se comprobaron rompiendo a propósito el código que protegen.

**Cuidado con SQLite.** Cambiar una columna reconstruye la tabla y, dentro de la transacción de `RefreshDatabase`, eso borra en cascada las filas hijas: en el ensayo, los 207 requisitos pasaron a 0. Por eso las pruebas que bajan y suben la migración de versiones no crean requisitos ni expedientes. En MariaDB no ocurre: la tarea 6 lo comprueba con datos reales, y sus cifras son las que cuentan.

## Archivos

| Archivo | Responsabilidad |
|---|---|
| `database/migrations/2026_11_06_100003_create_plantillas_checklist_tables.php` | Tablas de plantillas y la plantilla SGR |
| `database/migrations/2026_11_06_100004_add_plantilla_to_catalogo_versiones_table.php` | Versiones por plantilla |
| `database/migrations/2026_11_06_100005_create_expediente_plantillas_table.php` | Versión usada por cada expediente |
| `app/Models/PlantillaChecklist.php` | Modelo, y acceso a la plantilla SGR y a su versión vigente |
| `app/Models/CatalogoVersion.php` | Relación con la plantilla |
| `app/Models/ProyectoExpediente.php` | Relación `versionesPlantilla` |
| `app/Services/Viabilizacion/CatalogoService.php` | Borrador, vigente y publicación por plantilla |
| `app/Services/Viabilizacion/ExpedienteService.php` | Usa la vigente de SGR y registra la versión del expediente |
| `app/Http/Controllers/CatalogoRequisitosController.php` | Lista solo versiones SGR y pasa quién publica |
| `database/seeders/CatalogoRequisitosSgrSeeder.php` | Cuelga la versión 1 de la plantilla SGR |
| `tests/Feature/Schema/PlantillasChecklistTest.php` | Esquema y migraciones |
| `tests/Feature/Viabilizacion/PlantillasVersionesTest.php` | Comportamiento por plantilla |

---

### Tarea 1: Confirmar el punto de partida

- [ ] **Paso 1: Comprobar la rama y el estado**

Run: `git branch --show-current`
Expected: `feature/plantillas-checklist`

Run: `git status --short`
Expected: sin salida. Si aparece solo `public/`, es `npm run watch`: no tocarlo y seguir.

- [ ] **Paso 2: Ejecutar las pruebas**

Run: `php artisan test`
Expected: pasan todas, 248 o más. Anotar el total. Si alguna falla, detenerse y avisar.

---

### Tarea 2: Tablas de plantillas y plantilla SGR

**Files:**
- Create: `database/migrations/2026_11_06_100003_create_plantillas_checklist_tables.php`
- Create: `app/Models/PlantillaChecklist.php`
- Test: `tests/Feature/Schema/PlantillasChecklistTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Schema/PlantillasChecklistTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlantillasChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function otraPlantilla(): int
    {
        return PlantillaChecklist::create(['nombre' => 'Otra', 'motor' => PlantillaChecklist::MOTOR_SIMPLE])->id;
    }

    // ------------------------------------------------------------- plantillas

    public function test_la_plantilla_sgr_existe_al_migrar_y_solo_se_asocia_a_la_fuente_sgr(): void
    {
        $sgr = PlantillaChecklist::sgr();

        $this->assertSame(PlantillaChecklist::MOTOR_SGR, $sgr->motor);
        $this->assertTrue($sgr->activo);
        $this->assertTrue($sgr->todos_sectores);
        $this->assertFalse($sgr->todas_fuentes);
        $this->assertSame([FuenteFinanciacion::SGR], $sgr->fuentes()->pluck('clave')->all());
        $this->assertSame(0, $sgr->sectores()->count());
    }

    public function test_una_fuente_asociada_a_una_plantilla_no_se_puede_borrar(): void
    {
        $this->expectException(QueryException::class);
        DB::table('fuentes_financiacion')->where('clave', FuenteFinanciacion::SGR)->delete();
    }

    public function test_un_sector_asociado_a_una_plantilla_no_se_puede_borrar(): void
    {
        $sector = DB::table('sectores')->insertGetId(['nombre' => 'Agua', 'activo' => true]);
        DB::table('plantilla_sectores')->insert(['plantilla_id' => $this->otraPlantilla(), 'sector_id' => $sector]);

        $this->expectException(QueryException::class);
        DB::table('sectores')->where('id', $sector)->delete();
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Schema/PlantillasChecklistTest.php`
Expected: FALLAN porque la clase `App\Models\PlantillaChecklist` no existe. `test_una_fuente_asociada...` falla porque hoy esa fuente sí se puede borrar.

- [ ] **Paso 3: Crear la migración**

Crear `database/migrations/2026_11_06_100003_create_plantillas_checklist_tables.php`. La fuente se busca antes de crear las tablas, para fallar sin dejar nada a medias. Las guardas permiten reintentar, porque en MariaDB el DDL no es transaccional.

`alcance` queda vacío: no se inventa, lo redacta la entidad responsable. `plantilla_sectores` queda sin filas porque `todos_sectores` ya cubre todos.

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
        $fuenteSgr = DB::table('fuentes_financiacion')->where('clave', 'sgr')->value('id')
            ?? throw new RuntimeException('Falta la fuente con clave sgr en fuentes_financiacion.');

        if (! Schema::hasTable('plantillas_checklist')) {
            Schema::create('plantillas_checklist', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 40)->nullable()->unique();
                $table->string('nombre', 199);
                $table->text('descripcion')->nullable();
                $table->text('alcance')->nullable();
                $table->boolean('todas_fuentes')->default(false);
                $table->boolean('todos_sectores')->default(false);
                $table->string('motor', 20)->default('simple');
                $table->boolean('activo')->default(true);
                $table->foreignId('usuario_creo_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('plantilla_fuentes')) {
            Schema::create('plantilla_fuentes', function (Blueprint $table) {
                $table->foreignId('plantilla_id')->constrained('plantillas_checklist')->cascadeOnDelete();
                $table->foreignId('fuente_id')->constrained('fuentes_financiacion')->restrictOnDelete();
                $table->primary(['plantilla_id', 'fuente_id']);
            });
        }

        if (! Schema::hasTable('plantilla_sectores')) {
            Schema::create('plantilla_sectores', function (Blueprint $table) {
                $table->foreignId('plantilla_id')->constrained('plantillas_checklist')->cascadeOnDelete();
                $table->foreignId('sector_id')->constrained('sectores')->restrictOnDelete();
                $table->primary(['plantilla_id', 'sector_id']);
            });
        }

        DB::transaction(function () use ($fuenteSgr) {
            if (DB::table('plantillas_checklist')->where('clave', 'sgr')->exists()) {
                return;
            }

            $plantilla = DB::table('plantillas_checklist')->insertGetId([
                'clave' => 'sgr',
                'nombre' => 'SGR',
                'descripcion' => 'Orientaciones Transitorias + Acuerdo 12 de 2024',
                'todas_fuentes' => false,
                'todos_sectores' => true,
                'motor' => 'sgr',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('plantilla_fuentes')->insert(['plantilla_id' => $plantilla, 'fuente_id' => $fuenteSgr]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_sectores');
        Schema::dropIfExists('plantilla_fuentes');
        Schema::dropIfExists('plantillas_checklist');
    }
};
```

- [ ] **Paso 4: Crear el modelo**

Crear `app/Models/PlantillaChecklist.php`. `versiones()` y `versionVigente()` se usan a partir de la tarea 3.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantillaChecklist extends Model
{
    public const CLAVE_SGR = 'sgr';
    public const MOTOR_SGR = 'sgr';
    public const MOTOR_SIMPLE = 'simple';

    protected $table = 'plantillas_checklist';
    protected $fillable = [
        'clave', 'nombre', 'descripcion', 'alcance', 'todas_fuentes', 'todos_sectores',
        'motor', 'activo', 'usuario_creo_id',
    ];
    protected $casts = [
        'todas_fuentes' => 'boolean',
        'todos_sectores' => 'boolean',
        'activo' => 'boolean',
    ];

    /** La plantilla del checklist de regalías. La crea una migración, así que siempre existe. */
    public static function sgr(): self
    {
        return self::where('clave', self::CLAVE_SGR)->firstOrFail();
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(CatalogoVersion::class, 'plantilla_id');
    }

    public function versionVigente(): ?CatalogoVersion
    {
        return $this->versiones()->vigente()->first();
    }

    public function fuentes(): BelongsToMany
    {
        return $this->belongsToMany(FuenteFinanciacion::class, 'plantilla_fuentes', 'plantilla_id', 'fuente_id');
    }

    public function sectores(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'plantilla_sectores', 'plantilla_id', 'sector_id');
    }
}
```

- [ ] **Paso 5: Comprobar que pasan**

Run: `php artisan test tests/Feature/Schema/PlantillasChecklistTest.php`
Expected: 3 pruebas pasan.

Run: `php artisan test`
Expected: pasan todas. Esta migración no toca lo existente.

- [ ] **Paso 6: Comitear**

```bash
git add database/migrations/2026_11_06_100003_create_plantillas_checklist_tables.php app/Models/PlantillaChecklist.php tests/Feature/Schema/PlantillasChecklistTest.php
```

```bash
git commit -m "feat: add checklist templates with the SGR template

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarea 3: Versiones por plantilla

La migración hace obligatorio `plantilla_id`, así que el seeder y `crearBorrador` cambian en la misma tarea. Si no, las pruebas actuales fallarían.

**Files:**
- Create: `database/migrations/2026_11_06_100004_add_plantilla_to_catalogo_versiones_table.php`
- Modify: `app/Models/CatalogoVersion.php`
- Modify: `database/seeders/CatalogoRequisitosSgrSeeder.php`
- Modify: `app/Services/Viabilizacion/CatalogoService.php`
- Test: `tests/Feature/Schema/PlantillasChecklistTest.php`
- Test: `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`

- [ ] **Paso 1: Agregar las pruebas de esquema**

En `tests/Feature/Schema/PlantillasChecklistTest.php`, agregar estos dos `use`, junto a los demás:

```php
use Illuminate\Support\Facades\Schema;
use RuntimeException;
```

Agregar estos métodos auxiliares después de `otraPlantilla`:

```php
    private function migracionVersiones(): object
    {
        return require database_path('migrations/2026_11_06_100004_add_plantilla_to_catalogo_versiones_table.php');
    }

    private function version(int $plantilla, int $numero = 1): int
    {
        return DB::table('catalogo_versiones')->insertGetId([
            'plantilla_id' => $plantilla, 'numero' => $numero, 'estado' => 'publicada', 'vigente' => true,
        ]);
    }
```

Agregar estas pruebas al final de la clase:

```php
    public function test_una_plantilla_con_versiones_no_se_puede_borrar(): void
    {
        $plantilla = $this->otraPlantilla();
        $this->version($plantilla);

        $this->expectException(QueryException::class);
        DB::table('plantillas_checklist')->where('id', $plantilla)->delete();
    }

    // -------------------------------------------------------------- versiones

    public function test_el_numero_de_version_se_repite_entre_plantillas_pero_no_dentro_de_una(): void
    {
        $this->version(PlantillaChecklist::sgr()->id);
        $otra = $this->otraPlantilla();
        $this->version($otra);

        $this->expectException(QueryException::class);
        $this->version($otra);
    }

    public function test_toda_version_exige_plantilla(): void
    {
        $this->expectException(QueryException::class);
        DB::table('catalogo_versiones')->insert(['numero' => 9, 'estado' => 'borrador']);
    }

    /*
     * Estas pruebas no crean requisitos ni expedientes a propósito. En SQLite, cambiar una
     * columna reconstruye la tabla y, dentro de la transacción de RefreshDatabase, eso borra
     * en cascada las filas hijas. En MariaDB no pasa: se comprueba en la copia descartable.
     */
    public function test_migrar_asigna_la_plantilla_sgr_a_las_versiones_existentes(): void
    {
        $migracion = $this->migracionVersiones();
        $migracion->down();
        $this->assertFalse(Schema::hasColumn('catalogo_versiones', 'plantilla_id'));
        $this->assertTrue(Schema::hasIndex('catalogo_versiones', 'catalogo_versiones_numero_unique'));
        $id = DB::table('catalogo_versiones')->insertGetId(['numero' => 1, 'estado' => 'publicada', 'vigente' => true]);

        $migracion->up();

        $this->assertEquals(PlantillaChecklist::sgr()->id, DB::table('catalogo_versiones')->where('id', $id)->value('plantilla_id'));
        $this->assertFalse(Schema::hasIndex('catalogo_versiones', 'catalogo_versiones_numero_unique'));
    }

    public function test_la_migracion_de_versiones_se_puede_repetir(): void
    {
        $id = $this->version(PlantillaChecklist::sgr()->id);

        $this->migracionVersiones()->up();

        $this->assertEquals(PlantillaChecklist::sgr()->id, DB::table('catalogo_versiones')->where('id', $id)->value('plantilla_id'));
    }

    public function test_el_rollback_se_detiene_si_hay_versiones_de_otras_plantillas(): void
    {
        $this->version($this->otraPlantilla());

        try {
            $this->migracionVersiones()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('plantillas distintas de SGR', $e->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('catalogo_versiones', 'plantilla_id'));
    }
```

- [ ] **Paso 2: Crear las pruebas de comportamiento**

Crear `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`:

```php
<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlantillasVersionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['rol' => Rol::Administrador]);
    }

    private function comoAdmin(?User $usuario = null): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario ?? $this->admin()));
    }

    /** Otra plantilla con su versión 1 vigente y sin requisitos. */
    private function otraPlantilla(): PlantillaChecklist
    {
        $plantilla = PlantillaChecklist::create(['nombre' => 'Otra', 'motor' => PlantillaChecklist::MOTOR_SIMPLE]);
        $plantilla->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);

        return $plantilla;
    }

    public function test_la_version_sembrada_pertenece_a_la_plantilla_sgr(): void
    {
        $sgr = PlantillaChecklist::sgr();

        $this->assertSame(1, $sgr->versionVigente()->numero);
        $this->assertSame(207, $sgr->versionVigente()->requisitos()->count());
    }

    public function test_el_seeder_no_confunde_la_version_1_de_otra_plantilla(): void
    {
        DB::table('requisitos_catalogo')->delete();
        DB::table('catalogo_versiones')->delete();
        $otra = $this->otraPlantilla();

        $this->seed(CatalogoRequisitosSgrSeeder::class);

        $this->assertSame(0, $otra->versionVigente()->requisitos()->count());
        $this->assertSame(207, PlantillaChecklist::sgr()->versionVigente()->requisitos()->count());
    }

    public function test_cada_plantilla_numera_sus_versiones_y_tiene_su_propio_borrador(): void
    {
        $otra = $this->otraPlantilla();
        $servicio = app(CatalogoService::class);

        $deOtra = $servicio->crearBorrador(null, $otra);
        $deSgr = $servicio->crearBorrador(null);

        $this->assertSame($otra->id, $deOtra->plantilla_id);
        $this->assertSame(2, $deOtra->numero);
        $this->assertSame(PlantillaChecklist::sgr()->id, $deSgr->plantilla_id);
        $this->assertSame(2, $deSgr->numero);
        $this->assertSame(207, $deSgr->requisitos()->count());
    }

    public function test_crear_una_version_por_la_api_la_asocia_a_sgr(): void
    {
        $this->comoAdmin()->postJson('/GestPro/catalogo-requisitos/versiones', ['descripcion' => 'Ajustes'])
            ->assertCreated()
            ->assertJsonPath('plantilla_id', PlantillaChecklist::sgr()->id)
            ->assertJsonPath('numero', 2);
    }
}
```

- [ ] **Paso 3: Comprobar que fallan**

Run: `php artisan test tests/Feature/Schema/PlantillasChecklistTest.php tests/Feature/Viabilizacion/PlantillasVersionesTest.php`
Expected: FALLAN porque falta la migración y la columna `plantilla_id`.

- [ ] **Paso 4: Crear la migración**

Crear `database/migrations/2026_11_06_100004_add_plantilla_to_catalogo_versiones_table.php`. El diseño pide una falla con mensaje claro en el rollback si hay versiones de otras plantillas, porque restaurar `unique(numero)` chocaría con ellas.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNICO_NUEVO = 'catalogo_versiones_plantilla_numero_unique';
    private const UNICO_ANTERIOR = 'catalogo_versiones_numero_unique';

    public function up(): void
    {
        $plantillaSgr = $this->plantillaSgr();

        // En MariaDB el DDL no es transaccional: cada paso se puede repetir si uno falla.
        if (! Schema::hasColumn('catalogo_versiones', 'plantilla_id')) {
            Schema::table('catalogo_versiones', function (Blueprint $table) {
                $table->foreignId('plantilla_id')->nullable()->after('id');
            });
        }
        if (! Schema::hasColumn('catalogo_versiones', 'usuario_publico_id')) {
            Schema::table('catalogo_versiones', function (Blueprint $table) {
                $table->foreignId('usuario_publico_id')->nullable()->after('fecha_publicacion')
                    ->constrained('users')->nullOnDelete();
            });
        }

        DB::table('catalogo_versiones')->whereNull('plantilla_id')->update(['plantilla_id' => $plantillaSgr]);

        Schema::table('catalogo_versiones', function (Blueprint $table) {
            $table->foreignId('plantilla_id')->nullable(false)->change();
        });

        if (Schema::hasIndex('catalogo_versiones', self::UNICO_ANTERIOR)) {
            Schema::table('catalogo_versiones', function (Blueprint $table) {
                $table->dropUnique(self::UNICO_ANTERIOR);
            });
        }
        if (! Schema::hasIndex('catalogo_versiones', self::UNICO_NUEVO)) {
            Schema::table('catalogo_versiones', function (Blueprint $table) {
                $table->unique(['plantilla_id', 'numero'], self::UNICO_NUEVO);
            });
        }
        if (! collect(Schema::getForeignKeys('catalogo_versiones'))->contains(fn ($f) => $f['columns'] === ['plantilla_id'])) {
            Schema::table('catalogo_versiones', function (Blueprint $table) {
                $table->foreign('plantilla_id')->references('id')->on('plantillas_checklist')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::table('catalogo_versiones')->where('plantilla_id', '!=', $this->plantillaSgr())->exists()) {
            throw new RuntimeException('No se puede revertir: hay versiones de plantillas distintas de SGR. Elimínelas o restaure un respaldo.');
        }

        Schema::table('catalogo_versiones', function (Blueprint $table) {
            $table->dropForeign(['plantilla_id']);
        });
        Schema::table('catalogo_versiones', function (Blueprint $table) {
            $table->dropUnique(self::UNICO_NUEVO);
        });
        Schema::table('catalogo_versiones', function (Blueprint $table) {
            $table->dropForeign(['usuario_publico_id']);
        });
        Schema::table('catalogo_versiones', function (Blueprint $table) {
            $table->dropColumn(['plantilla_id', 'usuario_publico_id']);
        });
        Schema::table('catalogo_versiones', function (Blueprint $table) {
            $table->unique('numero');
        });
    }

    private function plantillaSgr(): int
    {
        return (int) (DB::table('plantillas_checklist')->where('clave', 'sgr')->value('id')
            ?? throw new RuntimeException('Falta la plantilla SGR en plantillas_checklist.'));
    }
};
```

- [ ] **Paso 5: Actualizar `CatalogoVersion`**

En `app/Models/CatalogoVersion.php`:

Agregar el `use`, junto a los demás:

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
```

Reemplazar `$fillable`:

```php
    protected $fillable = ['plantilla_id', 'numero', 'estado', 'descripcion', 'fecha_publicacion', 'usuario_publico_id', 'vigente'];
```

Reemplazar `$casts`:

```php
    protected $casts = [
        'plantilla_id' => 'integer',
        'numero' => 'integer',
        'usuario_publico_id' => 'integer',
        'vigente' => 'boolean',
        'fecha_publicacion' => 'datetime',
    ];
```

Agregar antes de `requisitos()`:

```php
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaChecklist::class, 'plantilla_id');
    }

```

- [ ] **Paso 6: Actualizar el seeder**

En `database/seeders/CatalogoRequisitosSgrSeeder.php`:

Reemplazar:

```php
            $versionId = DB::table('catalogo_versiones')->where('numero', self::VERSION)->value('id');
```

por:

```php
            $plantillaId = DB::table('plantillas_checklist')->where('clave', 'sgr')->value('id')
                ?? throw new RuntimeException('Falta la plantilla SGR: ejecute las migraciones.');

            $versionId = DB::table('catalogo_versiones')
                ->where('plantilla_id', $plantillaId)
                ->where('numero', self::VERSION)
                ->value('id');
```

Reemplazar:

```php
                $versionId = DB::table('catalogo_versiones')->insertGetId([
                    'numero' => self::VERSION,
```

por:

```php
                $versionId = DB::table('catalogo_versiones')->insertGetId([
                    'plantilla_id' => $plantillaId,
                    'numero' => self::VERSION,
```

Reemplazar:

```php
                // Solo puede haber una versión vigente.
                DB::table('catalogo_versiones')->where('id', '!=', $versionId)->update(['vigente' => false]);
```

por:

```php
                // Solo puede haber una versión vigente por plantilla.
                DB::table('catalogo_versiones')
                    ->where('plantilla_id', $plantillaId)
                    ->where('id', '!=', $versionId)
                    ->update(['vigente' => false]);
```

- [ ] **Paso 7: Crear borradores por plantilla**

En `app/Services/Viabilizacion/CatalogoService.php`:

Agregar el `use`, en orden alfabético después de `CatalogoVersion`:

```php
use App\Models\PlantillaChecklist;
```

En el comentario de la clase, reemplazar:

```php
 * Administración del catálogo maestro (rol Administrador).
 *
```

por:

```php
 * Administración del catálogo maestro (rol Administrador).
 *
 * Cada plantilla tiene sus versiones, con su propia numeración, un borrador y una
 * vigente como máximo. Sin indicar plantilla, se trabaja sobre la SGR.
 *
```

Reemplazar el comienzo de `crearBorrador`, hasta `'numero'`:

```php
    public function crearBorrador(?string $descripcion): CatalogoVersion
    {
        if (CatalogoVersion::where('estado', CatalogoVersion::BORRADOR)->exists()) {
            throw new ReglaNegocioException('Ya existe una versión en borrador; publíquela o elimínela antes de crear otra.');
        }
        $vigente = CatalogoVersion::vigente()->first()
            ?? throw new ReglaNegocioException('No hay una versión vigente que copiar.');

        return DB::transaction(function () use ($vigente, $descripcion) {
            $borrador = CatalogoVersion::create([
                'numero' => (int) CatalogoVersion::max('numero') + 1,
```

por:

```php
    public function crearBorrador(?string $descripcion, ?PlantillaChecklist $plantilla = null): CatalogoVersion
    {
        $plantilla ??= PlantillaChecklist::sgr();

        if ($plantilla->versiones()->where('estado', CatalogoVersion::BORRADOR)->exists()) {
            throw new ReglaNegocioException('Ya existe una versión en borrador; publíquela o elimínela antes de crear otra.');
        }
        $vigente = $plantilla->versionVigente()
            ?? throw new ReglaNegocioException('No hay una versión vigente que copiar.');

        return DB::transaction(function () use ($plantilla, $vigente, $descripcion) {
            $borrador = $plantilla->versiones()->create([
                'numero' => (int) $plantilla->versiones()->max('numero') + 1,
```

- [ ] **Paso 8: Comprobar que pasan**

Run: `php artisan test tests/Feature/Schema/PlantillasChecklistTest.php tests/Feature/Viabilizacion/PlantillasVersionesTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: pasan todas, incluidas `CatalogoRequisitosApiTest` y `CatalogoRequisitosTest`.

- [ ] **Paso 9: Comitear**

```bash
git add database/migrations/2026_11_06_100004_add_plantilla_to_catalogo_versiones_table.php app/Models/CatalogoVersion.php database/seeders/CatalogoRequisitosSgrSeeder.php app/Services/Viabilizacion/CatalogoService.php tests/Feature/Schema/PlantillasChecklistTest.php tests/Feature/Viabilizacion/PlantillasVersionesTest.php
```

```bash
git commit -m "feat: give each checklist template its own versions

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarea 4: La versión vigente deja de ser global

Hoy hay tres lugares que suponen una sola vigente en todo el sistema. En cuanto exista otra plantilla, un expediente SGR podría armarse con la versión equivocada.

**Files:**
- Modify: `app/Services/Viabilizacion/CatalogoService.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `app/Http/Controllers/CatalogoRequisitosController.php`
- Test: `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`, agregar estos dos `use`, junto a los demás:

```php
use App\Models\Proyecto;
use App\Services\Viabilizacion\ExpedienteService;
```

Agregar este método auxiliar después de `otraPlantilla`:

```php
    private function publicarNuevaSgr(): CatalogoVersion
    {
        $servicio = app(CatalogoService::class);

        return $servicio->publicar($servicio->crearBorrador('Ajustes'));
    }
```

Agregar estas pruebas al final de la clase:

```php
    public function test_publicar_no_afecta_la_version_vigente_de_otra_plantilla(): void
    {
        $otra = $this->otraPlantilla();
        $anterior = PlantillaChecklist::sgr()->versionVigente();

        $nueva = $this->publicarNuevaSgr();

        $this->assertSame($nueva->id, PlantillaChecklist::sgr()->versionVigente()->id);
        $this->assertFalse($anterior->fresh()->vigente);
        $this->assertSame(1, $otra->versionVigente()->numero);
    }

    public function test_el_catalogo_vigente_y_los_expedientes_nuevos_usan_la_version_sgr(): void
    {
        // La otra plantilla queda con un id menor que la nueva vigente de SGR: sin filtrar
        // por plantilla, tomar la primera vigente daría la versión equivocada.
        $this->otraPlantilla();
        $sgr = $this->publicarNuevaSgr();

        $this->comoAdmin()->getJson('/GestPro/catalogo-requisitos/vigente')
            ->assertOk()->assertJsonPath('version.id', $sgr->id);

        $expediente = app(ExpedienteService::class)
            ->crear(Proyecto::create(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);

        $this->assertSame($sgr->id, $expediente->catalogo_version_id);
    }

    public function test_la_lista_de_versiones_solo_muestra_las_de_sgr(): void
    {
        $this->otraPlantilla();

        $versiones = $this->comoAdmin()->getJson('/GestPro/catalogo-requisitos/versiones')->assertOk()->json();

        $this->assertCount(1, $versiones);
        $this->assertSame(207, $versiones[0]['requisitos']);
    }

    public function test_publicar_registra_quien_publico(): void
    {
        $admin = $this->admin();
        $borrador = app(CatalogoService::class)->crearBorrador(null);

        $this->comoAdmin($admin)->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();

        $this->assertSame($admin->id, $borrador->fresh()->usuario_publico_id);
    }
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Viabilizacion/PlantillasVersionesTest.php`
Expected: FALLAN `test_publicar_no_afecta...`, `test_la_lista_de_versiones...` y `test_publicar_registra...`. `test_el_catalogo_vigente...` todavía pasa, porque hoy publicar apaga la vigente de todas las plantillas y eso oculta el error: el paso 4 lo deja a la vista. Las 4 pruebas de la tarea 3 siguen pasando.

- [ ] **Paso 3: Publicar solo dentro de la plantilla**

En `app/Services/Viabilizacion/CatalogoService.php`:

Agregar el `use`, después de `RequisitoTipologia`:

```php
use App\Models\User;
```

Reemplazar la firma de `publicar`:

```php
    public function publicar(CatalogoVersion $version): CatalogoVersion
```

por:

```php
    public function publicar(CatalogoVersion $version, ?User $usuario = null): CatalogoVersion
```

Reemplazar la transacción de `publicar`:

```php
        return DB::transaction(function () use ($version) {
            CatalogoVersion::where('id', '!=', $version->id)->update(['vigente' => false]);
            $version->update(['estado' => CatalogoVersion::PUBLICADA, 'fecha_publicacion' => now(), 'vigente' => true]);
```

por:

```php
        return DB::transaction(function () use ($version, $usuario) {
            CatalogoVersion::where('plantilla_id', $version->plantilla_id)
                ->where('id', '!=', $version->id)
                ->update(['vigente' => false]);
            $version->update([
                'estado' => CatalogoVersion::PUBLICADA,
                'fecha_publicacion' => now(),
                'usuario_publico_id' => $usuario?->id,
                'vigente' => true,
            ]);
```

- [ ] **Paso 4: Comprobar que aparece el error de la vigente**

Run: `php artisan test tests/Feature/Viabilizacion/PlantillasVersionesTest.php --filter=test_el_catalogo_vigente`
Expected: FALLA: `/catalogo-requisitos/vigente` devuelve la versión de la otra plantilla.

- [ ] **Paso 5: Buscar la vigente por plantilla en el catálogo**

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar el comienzo de `vigente`:

```php
    /** Catálogo vigente para armar la configuración de un expediente. */
    public function vigente(bool $incluirInactivos = false): array
    {
        $version = CatalogoVersion::vigente()->first()
```

por:

```php
    /** Catálogo SGR vigente para armar la configuración de un expediente. */
    public function vigente(bool $incluirInactivos = false): array
    {
        $version = PlantillaChecklist::sgr()->versionVigente()
```

- [ ] **Paso 6: Actualizar `ExpedienteService`**

En `app/Services/Viabilizacion/ExpedienteService.php`:

Eliminar esta línea de los `use`, porque ya no se usa:

```php
use App\Models\CatalogoVersion;
```

Agregar este `use`, después de `FuenteFinanciacion`:

```php
use App\Models\PlantillaChecklist;
```

En `crear`, reemplazar:

```php
        $version = CatalogoVersion::vigente()->first()
```

por:

```php
        $version = PlantillaChecklist::sgr()->versionVigente()
```

- [ ] **Paso 7: Actualizar el controlador**

En `app/Http/Controllers/CatalogoRequisitosController.php`:

Agregar el `use`, después de `CatalogoVersion`:

```php
use App\Models\PlantillaChecklist;
```

En `versiones`, reemplazar:

```php
            CatalogoVersion::query()->withCount(['requisitos', 'expedientes'])->orderByDesc('numero')->get()
```

por:

```php
            PlantillaChecklist::sgr()->versiones()->withCount(['requisitos', 'expedientes'])->orderByDesc('numero')->get()
```

Reemplazar `publicarVersion`:

```php
    public function publicarVersion(CatalogoVersion $version): JsonResponse
    {
        return response()->json($this->servicio->publicar($version));
    }
```

por:

```php
    public function publicarVersion(Request $request, CatalogoVersion $version): JsonResponse
    {
        return response()->json($this->servicio->publicar($version, $request->user()));
    }
```

- [ ] **Paso 8: Comprobar que pasan**

Run: `php artisan test tests/Feature/Viabilizacion/PlantillasVersionesTest.php`
Expected: 8 pruebas pasan.

Run: `php artisan test`
Expected: pasan todas. `test_si_falla_la_creacion_el_proyecto_se_guarda_igual` sigue pasando, porque sin vigente el servicio lanza el mismo error.

- [ ] **Paso 9: Comitear**

```bash
git add app/Services/Viabilizacion/CatalogoService.php app/Services/Viabilizacion/ExpedienteService.php app/Http/Controllers/CatalogoRequisitosController.php tests/Feature/Viabilizacion/PlantillasVersionesTest.php
```

```bash
git commit -m "fix: look up the current version per template, not globally

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarea 5: Versión de cada expediente

`catalogo_version_id` sigue siendo lo que se lee. Esta tabla se escribe desde ahora para que en la etapa 5, cuando un expediente pueda tener varias plantillas, ya tenga los datos.

**Files:**
- Create: `database/migrations/2026_11_06_100005_create_expediente_plantillas_table.php`
- Modify: `app/Models/ProyectoExpediente.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Test: `tests/Feature/Schema/PlantillasChecklistTest.php`
- Test: `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`

- [ ] **Paso 1: Agregar las pruebas de esquema**

En `tests/Feature/Schema/PlantillasChecklistTest.php`, agregar estos métodos auxiliares después de `version`:

```php
    private function migracionExpedientes(): object
    {
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
    }
```

Agregar estas pruebas al final de la clase:

```php
    // ------------------------------------------------------------ expedientes

    public function test_migrar_registra_la_version_de_cada_expediente_como_principal(): void
    {
        $version = $this->version(PlantillaChecklist::sgr()->id);
        $expediente = $this->expediente($version);
        $migracion = $this->migracionExpedientes();
        $migracion->down();

        $migracion->up();
        $migracion->up(); // repetirla no duplica

        $filas = DB::table('expediente_plantillas')->get();
        $this->assertCount(1, $filas);
        $this->assertEquals($expediente, $filas[0]->expediente_id);
        $this->assertEquals($version, $filas[0]->catalogo_version_id);
        $this->assertEquals(1, $filas[0]->es_principal);
    }

    public function test_borrar_un_expediente_borra_su_registro_de_plantillas(): void
    {
        $version = $this->version(PlantillaChecklist::sgr()->id);
        $expediente = $this->expediente($version);
        DB::table('expediente_plantillas')->insert([
            'expediente_id' => $expediente, 'catalogo_version_id' => $version, 'es_principal' => true,
        ]);

        DB::table('proyectos_expedientes')->where('id', $expediente)->delete();

        $this->assertDatabaseCount('expediente_plantillas', 0);
    }
```

- [ ] **Paso 2: Agregar la prueba de comportamiento**

En `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`, agregar al final de la clase:

```php
    public function test_un_expediente_nuevo_registra_su_version_como_principal(): void
    {
        $servicio = app(ExpedienteService::class);
        $expediente = $servicio->crear(Proyecto::create(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);

        $version = $expediente->versionesPlantilla()->sole();
        $this->assertSame($expediente->catalogo_version_id, $version->id);
        $this->assertEquals(1, $version->pivot->es_principal);

        $servicio->eliminar($expediente);
        $this->assertDatabaseCount('expediente_plantillas', 0);
    }
```

- [ ] **Paso 3: Comprobar que fallan**

Run: `php artisan test tests/Feature/Schema/PlantillasChecklistTest.php tests/Feature/Viabilizacion/PlantillasVersionesTest.php`
Expected: FALLAN las 3 pruebas nuevas, porque no existen la migración ni `versionesPlantilla`.

- [ ] **Paso 4: Crear la migración**

Crear `database/migrations/2026_11_06_100005_create_expediente_plantillas_table.php`:

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
        if (! Schema::hasTable('expediente_plantillas')) {
            Schema::create('expediente_plantillas', function (Blueprint $table) {
                $table->foreignId('expediente_id')->constrained('proyectos_expedientes')->cascadeOnDelete();
                $table->foreignId('catalogo_version_id')->constrained('catalogo_versiones')->restrictOnDelete();
                $table->boolean('es_principal')->default(false);
                $table->primary(['expediente_id', 'catalogo_version_id']);
            });
        }

        DB::table('expediente_plantillas')->insertOrIgnoreUsing(
            ['expediente_id', 'catalogo_version_id', 'es_principal'],
            DB::table('proyectos_expedientes')->select('id', 'catalogo_version_id', DB::raw('1'))
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('expediente_plantillas');
    }
};
```

- [ ] **Paso 5: Agregar la relación**

En `app/Models/ProyectoExpediente.php`, agregar antes de `sectores()`:

```php
    /** Versiones de plantilla que usa el expediente. La etapa 5 permite más de una. */
    public function versionesPlantilla(): BelongsToMany
    {
        return $this->belongsToMany(CatalogoVersion::class, 'expediente_plantillas', 'expediente_id', 'catalogo_version_id')
            ->withPivot('es_principal');
    }

```

- [ ] **Paso 6: Registrar la versión al crear**

En `app/Services/Viabilizacion/ExpedienteService.php`, dentro de la transacción de `crear`, reemplazar:

```php
            $this->guardarSeleccion($expediente, $seleccion);
            $this->sincronizar($expediente);

            return $expediente;
        });
    }

    /**
     * Expediente que nace
```

por:

```php
            $expediente->versionesPlantilla()->attach($version->id, ['es_principal' => true]);
            $this->guardarSeleccion($expediente, $seleccion);
            $this->sincronizar($expediente);

            return $expediente;
        });
    }

    /**
     * Expediente que nace
```

No cambiar `sincronizar`, `presentar` ni nada que lea el checklist.

- [ ] **Paso 7: Comprobar que pasan**

Run: `php artisan test tests/Feature/Schema/PlantillasChecklistTest.php tests/Feature/Viabilizacion/PlantillasVersionesTest.php`
Expected: 11 y 9 pruebas pasan.

Run: `php artisan test`
Expected: pasan todas: el total de la tarea 1 más 20. Con 248 de partida, son 268.

Run: `npm test`
Expected: pasan todas. No se tocó JavaScript, así que el total no cambia.

- [ ] **Paso 8: Comitear**

```bash
git add database/migrations/2026_11_06_100005_create_expediente_plantillas_table.php app/Models/ProyectoExpediente.php app/Services/Viabilizacion/ExpedienteService.php tests/Feature/Schema/PlantillasChecklistTest.php tests/Feature/Viabilizacion/PlantillasVersionesTest.php
```

```bash
git commit -m "feat: record the template version each record uses

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarea 6: Verificación en una copia descartable

Base descartable, nunca `gest_pro`. Comprueba lo que SQLite no cubre y el criterio de la etapa: nada cambia. `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio; hay que definirlo en cada comando porque el estado de la terminal no se conserva.

- [ ] **Paso 1: Crear la copia**

```bash
/c/xampp/mysql/bin/mysql -u root -e "CREATE DATABASE gestpro_verif_tmp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro | /c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp
```

- [ ] **Paso 2: Crear el script de la foto**

Crear `$TMP_DIR/gestpro_expedientes_etapa2.php`. Guarda lo que la API entrega de cada expediente, además de las filas crudas, y se detiene si la base no es la esperada.

```php
<?php
// Uso, desde la raíz del repositorio: php gestpro_expedientes_etapa2.php <archivo.json> <base esperada>
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProyectoExpediente;
use App\Services\Viabilizacion\ExpedienteService;
use Illuminate\Support\Facades\DB;

if (DB::getDatabaseName() !== $argv[2]) {
    exit('Abortado: la base es ' . DB::getDatabaseName() . ", se esperaba {$argv[2]}\n");
}

$servicio = app(ExpedienteService::class);
$foto = [
    'resumen' => $servicio->resumenPorProyecto(),
    'expedientes' => ProyectoExpediente::query()->orderBy('id')->get()
        ->map(fn (ProyectoExpediente $e) => $servicio->presentar($e))->all(),
    'filas' => DB::table('proyectos_requisitos')->orderBy('id')->get(),
    'versiones' => DB::table('catalogo_versiones')->orderBy('id')
        ->get(['id', 'numero', 'estado', 'descripcion', 'fecha_publicacion', 'vigente']),
    'requisitos' => DB::table('requisitos_catalogo')->count(),
];

file_put_contents($argv[1], json_encode($foto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo 'Expedientes guardados: ' . count($foto['expedientes']) . ', filas: ' . count($foto['filas']) . "\n";
```

- [ ] **Paso 3: Tomar la foto antes de migrar**

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_expedientes_etapa2.php" "$TMP_DIR/e2_antes.json" gestpro_verif_tmp
```

Expected: el número de expedientes y filas actual de `gest_pro`, sin error.

- [ ] **Paso 4: Migrar y comparar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

Expected: se aplican las 3 migraciones nuevas.

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_expedientes_etapa2.php" "$TMP_DIR/e2_despues.json" gestpro_verif_tmp
```

```bash
diff "$TMP_DIR/e2_antes.json" "$TMP_DIR/e2_despues.json"
```

Expected: `diff` sin salida. Cualquier diferencia detiene la etapa.

- [ ] **Paso 5: Revisar lo migrado**

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT id, numero, plantilla_id, vigente FROM catalogo_versiones; SELECT COUNT(*) AS requisitos FROM requisitos_catalogo; SELECT COUNT(*) AS registros, SUM(es_principal) AS principales FROM expediente_plantillas; SELECT COUNT(*) AS expedientes FROM proyectos_expedientes; SELECT COUNT(*) AS distintos FROM expediente_plantillas p JOIN proyectos_expedientes e ON e.id = p.expediente_id WHERE e.catalogo_version_id <> p.catalogo_version_id;"
```

Expected: toda versión con la plantilla SGR, 207 requisitos, un registro principal por expediente y `distintos` en 0.

- [ ] **Paso 6: Probar el rollback y volver a comparar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=3 --force
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_expedientes_etapa2.php" "$TMP_DIR/e2_rollback.json" gestpro_verif_tmp
```

```bash
diff "$TMP_DIR/e2_antes.json" "$TMP_DIR/e2_rollback.json"
```

Expected: `diff` sin salida.

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SHOW INDEX FROM catalogo_versiones WHERE Key_name = 'catalogo_versiones_numero_unique'; SHOW TABLES LIKE 'plantilla%'; SHOW TABLES LIKE 'expediente_plantillas';"
```

Expected: el índice único de `numero` aparece y ninguna tabla nueva queda.

- [ ] **Paso 7: Volver a migrar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

Expected: se aplican las 3 migraciones de nuevo, sin errores.

- [ ] **Paso 8: Probar en el navegador sobre la copia**

Arrancar el servidor desde `public/` (`artisan serve` recargaría `gest_pro` desde `.env`):

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8765 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Crear un usuario de prueba solo en la copia; nunca usar sesiones de usuarios reales de `gest_pro`:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'verif@gestpro.test', 'rol' => App\Enums\Rol::Administrador]);"
```

La contraseña es la predeterminada de `UserFactory`. Comprobar:

1. El panel de expedientes del tablero muestra los mismos porcentajes que antes, según `$TMP_DIR/e2_antes.json`.
2. El expediente radicado abre y sigue congelado. Muestra "Catálogo v1".
3. Un expediente en borrador abre con su checklist: marcar y desmarcar un requisito actualiza el porcentaje.
4. Crear un proyecto en Formulación con la fuente SGR: nace su expediente sin configurar. Configurarlo genera el checklist.
5. Eliminar ese expediente en borrador funciona.
6. La consola del navegador no muestra errores.

- [ ] **Paso 9: Limpiar**

Detener el servidor y eliminar la copia:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 10: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas.

Run: `npm test`
Expected: pasan todas.

- [ ] **Paso 11: Informar al usuario y pedir validación**

Informar: total de pruebas, resultado de las dos comparaciones y lo revisado en el navegador. Pedir dos decisiones por separado: si valida la etapa y si autoriza aplicar las migraciones en `gest_pro`.

---

### Tarea 7: Aplicar en `gest_pro` (solo con autorización)

No ejecutar esta tarea sin un sí explícito del usuario a este paso. Si la comparación muestra diferencias, restaurar el respaldo: no usar `migrate:rollback`.

- [ ] **Paso 1: Respaldar**

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro > "$TMP_DIR/gest_pro_antes_etapa_2.sql"
```

Expected: el archivo existe y no está vacío.

- [ ] **Paso 2: Tomar la foto antes de migrar**

```bash
php "$TMP_DIR/gestpro_expedientes_etapa2.php" "$TMP_DIR/e2_real_antes.json" gest_pro
```

- [ ] **Paso 3: Migrar hacia adelante**

Run: `php artisan migrate --force`
Expected: se aplican las 3 migraciones nuevas.

- [ ] **Paso 4: Comparar**

```bash
php "$TMP_DIR/gestpro_expedientes_etapa2.php" "$TMP_DIR/e2_real_despues.json" gest_pro
```

```bash
diff "$TMP_DIR/e2_real_antes.json" "$TMP_DIR/e2_real_despues.json"
```

Expected: `diff` sin salida.

- [ ] **Paso 5: Informar**

Dar el resultado y la ruta completa del respaldo, y avisar que está en una carpeta temporal.

---

## Criterio de la etapa

La etapa está lista cuando, según el diseño:

- El SGR es una plantilla: sus versiones cuelgan de ella y su versión vigente se busca por plantilla.
- La comparación de los expedientes no muestra diferencias, ni al migrar ni al revertir.
- Nada cambia en pantalla.
- Publicar una versión no afecta a otras plantillas.
