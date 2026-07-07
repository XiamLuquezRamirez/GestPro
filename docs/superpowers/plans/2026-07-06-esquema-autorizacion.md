# Esquema versionado y autorización por rol — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Versionar en migraciones de Laravel las ~19 tablas de negocio de GestPro (con tipos corregidos y llaves foráneas reales, preservando el catálogo real de municipios/departamentos), crear los modelos Eloquent correspondientes, e introducir autorización por rol (Administrador/Gestor/Consulta) sobre las rutas de la API.

**Architecture:** Migraciones `Schema::create` en orden de dependencia (catálogos → proyectos → tablas hijas), con seeders que repueblan `departamentos`/`municipios` desde datos ya exportados de la base real y crean un usuario Administrador semilla. Un modelo Eloquent por tabla con `casts` y relaciones. Autorización vía `Gate::define()` en `AppServiceProvider` + middleware nativo `can:` en `routes/api.php`. `ProyectoController.php` no se toca — sigue usando `DB::table()` sobre las mismas tablas y columnas (mismos nombres, ahora con tipos y constraints correctos).

**Tech Stack:** Laravel 12, MySQL/MariaDB (local) y SQLite en memoria (tests, ya configurado en `phpunit.xml`), PHPUnit 11.

**Contexto importante para quien ejecute este plan:**
- Este es un repo Laravel + React (SPA sin Inertia). El backend expone la API bajo el prefijo `/GestPro` (ver `bootstrap/app.php:13`, `apiPrefix: 'GestPro'`).
- La base de datos local (`gest_pro` en MySQL/MariaDB vía XAMPP) **ya tiene** las 19 tablas de negocio creadas fuera de git (nunca se corrió `php artisan migrate` sobre ellas — confirmado: la tabla `migrations` en la BD solo registra 4 migraciones de Laravel por defecto). Este plan las recrea desde cero vía `migrate:fresh --seed`, excepto `municipios`/`departamentos` cuyos datos reales ya fueron exportados a `database/seeders/data/*.json` (ver Task 1).
- **No existe entorno de producción con datos reales** — confirmado con el usuario. Es seguro recrear tablas de prueba.
- El controlador existente `app/Http/Controllers/ProyectoController.php` sigue usando `DB::table('proyectos')`, etc. con los **mismos nombres de columna** que ya existen (ej. `municipio`, no `municipio_id`). Las migraciones de este plan usan esos mismos nombres para no romper nada.
- Spec completo: `docs/superpowers/specs/2026-07-06-esquema-autorizacion-design.md`.

---

## Task 1: Departamentos — migración, modelo, datos reales

**Files:**
- Create: `database/migrations/2026_07_06_160000_create_departamentos_table.php`
- Create: `app/Models/Departamento.php`
- Create: `database/seeders/DepartamentosSeeder.php`
- (ya existe, no tocar) `database/seeders/data/departamentos.json` — 34 filas exportadas de la BD real (`codigo`, `nombre`, `habilitado` como "SI"/"NO")
- Test: `tests/Feature/Schema/DepartamentosTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Departamento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates the departamentos table with the expected columns', function () {
    expect(Schema::hasTable('departamentos'))->toBeTrue();
    expect(Schema::hasColumns('departamentos', ['codigo', 'nombre', 'activo']))->toBeTrue();
});

it('seeds the real DANE catalog of 34 departamentos', function () {
    $this->seed(\Database\Seeders\DepartamentosSeeder::class);

    expect(Departamento::count())->toBe(34);

    $antioquia = Departamento::find('05');
    expect($antioquia->nombre)->toBe('ANTIOQUIA');
    expect($antioquia->activo)->toBeBool();
});
```

Si el proyecto usa PHPUnit clásico en vez de Pest (revisa si existe `tests/Pest.php`; si no existe, usa PHPUnit), escribe en su lugar:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\Departamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DepartamentosTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_departamentos_table_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('departamentos'));
        $this->assertTrue(Schema::hasColumns('departamentos', ['codigo', 'nombre', 'activo']));
    }

    public function test_seeds_the_real_catalog_of_34_departamentos(): void
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);

        $this->assertSame(34, Departamento::count());

        $antioquia = Departamento::find('05');
        $this->assertSame('ANTIOQUIA', $antioquia->nombre);
        $this->assertIsBool($antioquia->activo);
    }
}
```

Usa el segundo bloque (PHPUnit) para todos los pasos de este plan, ya que el proyecto trae `phpunit/phpunit` en `composer.json` y no tiene `pestphp/pest` instalado.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DepartamentosTest`
Expected: FAIL — la clase `App\Models\Departamento` no existe y la tabla `departamentos` no existe en la conexión de test (sqlite en memoria).

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->string('codigo', 5)->primary();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamentos');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    protected $primaryKey = 'codigo';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class, 'departamento', 'codigo');
    }
}
```

- [ ] **Step 5: Write the seeder**

El archivo `database/seeders/data/departamentos.json` ya existe en el repo (exportado de la BD real) con este formato: `[{"codigo":"05","nombre":"ANTIOQUIA","habilitado":"SI"}, ...]`.

```php
<?php

namespace Database\Seeders;

use App\Models\Departamento;
use Illuminate\Database\Seeder;

class DepartamentosSeeder extends Seeder
{
    public function run(): void
    {
        $ruta = database_path('seeders/data/departamentos.json');
        $filas = json_decode(file_get_contents($ruta), true);

        foreach ($filas as $fila) {
            Departamento::updateOrCreate(
                ['codigo' => $fila['codigo']],
                [
                    'nombre' => $fila['nombre'],
                    'activo' => $fila['habilitado'] === 'SI',
                ]
            );
        }
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=DepartamentosTest`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_06_160000_create_departamentos_table.php app/Models/Departamento.php database/seeders/DepartamentosSeeder.php database/seeders/data/departamentos.json tests/Feature/Schema/DepartamentosTest.php
git commit -m "feat: version departamentos table with migration, model and real seed data"
```

---

## Task 2: Municipios — migración, modelo, datos reales (depende de departamentos)

**Files:**
- Create: `database/migrations/2026_07_06_160001_create_municipios_table.php`
- Create: `app/Models/Municipio.php`
- Create: `database/seeders/MunicipiosSeeder.php`
- (ya existe) `database/seeders/data/municipios.json` — 1.123 filas reales exportadas (se excluyeron 2 filas de prueba obviamente falsas: id 1125 "SSES"/"ESES" e id 1126 "rfddf"/"dff", que no correspondían a municipios reales de Colombia)
- Test: `tests/Feature/Schema/MunicipiosTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\Municipio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MunicipiosTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_municipios_table_with_fk_to_departamentos(): void
    {
        $this->assertTrue(Schema::hasTable('municipios'));
        $this->assertTrue(Schema::hasColumns('municipios', ['id', 'codigo', 'nombre', 'activo', 'departamento', 'imagen']));
    }

    public function test_seeds_the_real_catalog_of_1123_municipios(): void
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $this->seed(\Database\Seeders\MunicipiosSeeder::class);

        $this->assertSame(1123, Municipio::count());

        $medellin = Municipio::where('nombre', 'MEDELLÍN')->first();
        $this->assertNotNull($medellin);
        $this->assertSame('05', $medellin->departamentoRel->codigo);
    }

    public function test_rejects_a_municipio_with_an_unknown_departamento(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Municipio::create([
            'codigo' => '99999',
            'nombre' => 'MUNICIPIO INEXISTENTE',
            'activo' => true,
            'departamento' => 'ZZ',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MunicipiosTest`
Expected: FAIL — falta la tabla, el modelo y el seeder.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->nullable();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->string('departamento', 5);
            $table->longText('imagen')->nullable();

            $table->foreign('departamento')->references('codigo')->on('departamentos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipios');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipio extends Model
{
    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'activo', 'departamento', 'imagen'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Named departamentoRel() (not departamento()) because the FK column is also called
    // `departamento`. Eloquent's getAttribute() always returns the raw column value when a
    // relation method has the exact same name as an existing attribute, so `$municipio->departamento`
    // would never resolve to the relation if the method were named departamento() — it would
    // silently keep returning the raw string code instead. This project's convention: whenever a
    // belongsTo FK column is a single word (no underscore) that would collide with the natural
    // relation name, suffix the relation method with `Rel`.
    public function departamentoRel(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento', 'codigo');
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'municipio');
    }
}
```

- [ ] **Step 5: Write the seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\Municipio;
use Illuminate\Database\Seeder;

class MunicipiosSeeder extends Seeder
{
    public function run(): void
    {
        $ruta = database_path('seeders/data/municipios.json');
        $filas = json_decode(file_get_contents($ruta), true);

        foreach ($filas as $fila) {
            Municipio::updateOrCreate(
                ['id' => $fila['id']],
                [
                    'codigo' => $fila['codigo'],
                    'nombre' => $fila['nombre'],
                    'activo' => (bool) $fila['activo'],
                    'departamento' => $fila['departamento'],
                    'imagen' => $fila['imagen'],
                ]
            );
        }
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=MunicipiosTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_06_160001_create_municipios_table.php app/Models/Municipio.php database/seeders/MunicipiosSeeder.php database/seeders/data/municipios.json tests/Feature/Schema/MunicipiosTest.php
git commit -m "feat: version municipios table with FK to departamentos and real seed data"
```

---

## Task 3: Catálogos simples sin dependencias (Sector, Entidad, TipoProceso, Modalidad, TipoEvento, Prioridad, Responsable)

Estas 7 tablas comparten la misma forma (`id`, `nombre`, `activo`, alguna con un campo extra). Se agrupan en una sola tarea porque son mecánicamente idénticas.

**Files:**
- Create: `database/migrations/2026_07_06_160002_create_sectores_table.php`
- Create: `database/migrations/2026_07_06_160003_create_entidades_table.php`
- Create: `database/migrations/2026_07_06_160005_create_tipos_procesos_table.php`
- Create: `database/migrations/2026_07_06_160006_create_modalidades_table.php`
- Create: `database/migrations/2026_07_06_160007_create_tipo_eventos_table.php`
- Create: `database/migrations/2026_07_06_160008_create_prioridades_table.php`
- Create: `database/migrations/2026_07_06_160009_create_responsable_table.php`
- Create: `app/Models/Sector.php`, `app/Models/Entidad.php`, `app/Models/TipoProceso.php`, `app/Models/Modalidad.php`, `app/Models/TipoEvento.php`, `app/Models/Prioridad.php`, `app/Models/Responsable.php`
- Test: `tests/Feature/Schema/CatalogosSimplesTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\Entidad;
use App\Models\Modalidad;
use App\Models\Prioridad;
use App\Models\Responsable;
use App\Models\Sector;
use App\Models\TipoEvento;
use App\Models\TipoProceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogosSimplesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_simple_catalog_tables_exist_with_activo_boolean(): void
    {
        $tablas = [
            'sectores' => Sector::class,
            'entidades' => Entidad::class,
            'tipos_procesos' => TipoProceso::class,
            'modalidades' => Modalidad::class,
            'tipo_eventos' => TipoEvento::class,
            'prioridades' => Prioridad::class,
            'responsable' => Responsable::class,
        ];

        foreach ($tablas as $tabla => $modeloClase) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}");
            $this->assertTrue(Schema::hasColumns($tabla, ['id', 'nombre', 'activo']), "Faltan columnas en {$tabla}");

            $registro = $modeloClase::create(['nombre' => 'Ejemplo de prueba', 'activo' => true]);
            $this->assertTrue($registro->fresh()->activo);
        }
    }

    public function test_tipo_evento_and_responsable_have_their_extra_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('tipo_eventos', 'icono'));
        $this->assertTrue(Schema::hasColumns('responsable', ['email', 'cargo']));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CatalogosSimplesTest`
Expected: FAIL — ninguna de las 7 tablas ni modelos existe todavía.

- [ ] **Step 3: Write the 7 migrations**

`database/migrations/2026_07_06_160002_create_sectores_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 199)->nullable();
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sectores');
    }
};
```

`database/migrations/2026_07_06_160003_create_entidades_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entidades', function (Blueprint $table) {
            $table->id();
            $table->text('nombre')->nullable();
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entidades');
    }
};
```

`database/migrations/2026_07_06_160005_create_tipos_procesos_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_procesos', function (Blueprint $table) {
            $table->id();
            $table->text('nombre')->nullable();
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_procesos');
    }
};
```

`database/migrations/2026_07_06_160006_create_modalidades_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modalidades', function (Blueprint $table) {
            $table->id();
            $table->text('nombre')->nullable();
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modalidades');
    }
};
```

`database/migrations/2026_07_06_160007_create_tipo_eventos_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 199)->nullable();
            $table->string('icono', 20)->nullable();
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_eventos');
    }
};
```

`database/migrations/2026_07_06_160008_create_prioridades_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prioridades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 199)->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prioridades');
    }
};
```

`database/migrations/2026_07_06_160009_create_responsable_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responsable', function (Blueprint $table) {
            $table->id();
            $table->text('nombre')->nullable();
            $table->string('email', 100)->nullable();
            $table->string('cargo', 100)->nullable();
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responsable');
    }
};
```

- [ ] **Step 4: Write the 7 models**

`app/Models/Sector.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    public $timestamps = false;

    protected $table = 'sectores';
    protected $fillable = ['nombre', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'sector');
    }
}
```

`app/Models/Entidad.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entidad extends Model
{
    public $timestamps = false;

    // Explicit $table required: Laravel's pluralizer guesses "entidads" from "Entidad", not
    // "entidades" (same reason Modalidad and Prioridad below also need it).
    protected $table = 'entidades';
    protected $fillable = ['nombre', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function proyectosQuePresenta(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'entidad_presenta');
    }

    public function proyectosQueFinancia(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'entidad_financia');
    }

    public function procesosLicitacion(): HasMany
    {
        return $this->hasMany(ProcesoLicitacion::class, 'entidad_contratante');
    }
}
```

`app/Models/TipoProceso.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoProceso extends Model
{
    public $timestamps = false;

    protected $table = 'tipos_procesos';
    protected $fillable = ['nombre', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function procesosLicitacion(): HasMany
    {
        return $this->hasMany(ProcesoLicitacion::class, 'tipo_proceso');
    }
}
```

`app/Models/Modalidad.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modalidad extends Model
{
    public $timestamps = false;

    // Explicit $table required: Laravel's pluralizer guesses "modalidads" from "Modalidad", not
    // "modalidades".
    protected $table = 'modalidades';
    protected $fillable = ['nombre', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function procesosLicitacion(): HasMany
    {
        return $this->hasMany(ProcesoLicitacion::class, 'modalidad');
    }
}
```

`app/Models/TipoEvento.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoEvento extends Model
{
    public $timestamps = false;

    protected $fillable = ['nombre', 'icono', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class, 'tipo_eventos');
    }
}
```

`app/Models/Prioridad.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prioridad extends Model
{
    public $timestamps = false;

    // Explicit $table required: Laravel's pluralizer guesses "prioridads" from "Prioridad", not
    // "prioridades".
    protected $table = 'prioridades';
    protected $fillable = ['nombre', 'color', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class, 'prioridad');
    }
}
```

`app/Models/Responsable.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Responsable extends Model
{
    public $timestamps = false;

    protected $table = 'responsable';
    protected $fillable = ['nombre', 'email', 'cargo', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class, 'responsable');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=CatalogosSimplesTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_06_160002_create_sectores_table.php database/migrations/2026_07_06_160003_create_entidades_table.php database/migrations/2026_07_06_160005_create_tipos_procesos_table.php database/migrations/2026_07_06_160006_create_modalidades_table.php database/migrations/2026_07_06_160007_create_tipo_eventos_table.php database/migrations/2026_07_06_160008_create_prioridades_table.php database/migrations/2026_07_06_160009_create_responsable_table.php app/Models/Sector.php app/Models/Entidad.php app/Models/TipoProceso.php app/Models/Modalidad.php app/Models/TipoEvento.php app/Models/Prioridad.php app/Models/Responsable.php tests/Feature/Schema/CatalogosSimplesTest.php
git commit -m "feat: version simple catalog tables (sector, entidad, tipo_proceso, modalidad, tipo_evento, prioridad, responsable)"
```

---

## Task 4: Fases y Estados (Estados depende de Fases)

**Files:**
- Create: `database/migrations/2026_07_06_160004_create_fases_table.php`
- Create: `database/migrations/2026_07_06_160010_create_estados_table.php`
- Create: `app/Models/Fase.php`, `app/Models/Estado.php`
- Test: `tests/Feature/Schema/FasesEstadosTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\Estado;
use App\Models\Fase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FasesEstadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_fases_table_has_dashboard_flag(): void
    {
        $this->assertTrue(Schema::hasColumns('fases', ['id', 'nombre', 'color', 'activo', 'dashboard']));
    }

    public function test_estado_belongs_to_a_fase(): void
    {
        $fase = Fase::create(['nombre' => 'Ejecución', 'color' => '#1976d2', 'activo' => true, 'dashboard' => true]);
        $estado = Estado::create(['nombre' => 'Aprobado', 'color' => '#43a047', 'activo' => true, 'icono' => 'check', 'fase' => $fase->id]);

        $this->assertTrue($estado->fresh()->faseRel->is($fase));
        $this->assertTrue($fase->fresh()->dashboard);
    }

    public function test_cannot_delete_a_fase_referenced_by_an_estado(): void
    {
        $fase = Fase::create(['nombre' => 'Formulación', 'activo' => true, 'dashboard' => false]);
        Estado::create(['nombre' => 'En revisión', 'activo' => true, 'fase' => $fase->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $fase->delete();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FasesEstadosTest`
Expected: FAIL — faltan tablas y modelos.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_07_06_160004_create_fases_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fases', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 199)->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('dashboard')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fases');
    }
};
```

`database/migrations/2026_07_06_160010_create_estados_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 199)->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->string('icono', 20)->nullable();
            $table->foreignId('fase')->nullable()->constrained('fases')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estados');
    }
};
```

- [ ] **Step 4: Write the models**

`app/Models/Fase.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fase extends Model
{
    public $timestamps = false;

    protected $fillable = ['nombre', 'color', 'activo', 'dashboard'];
    protected $casts = [
        'activo' => 'boolean',
        'dashboard' => 'boolean',
    ];

    public function estados(): HasMany
    {
        return $this->hasMany(Estado::class, 'fase');
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'fase');
    }
}
```

`app/Models/Estado.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estado extends Model
{
    public $timestamps = false;

    protected $fillable = ['nombre', 'color', 'activo', 'icono', 'fase'];
    protected $casts = ['activo' => 'boolean'];

    // Named faseRel() (not fase()) because the FK column is also called `fase` — Eloquent's
    // getAttribute() always returns the raw column value when a relation method has the exact
    // same name as an existing attribute, so `$estado->fase` would never resolve to the relation.
    // This project's convention: whenever a belongsTo FK column is a single word (no underscore)
    // and would collide with the natural relation name, suffix the relation method with `Rel`.
    public function faseRel(): BelongsTo
    {
        return $this->belongsTo(Fase::class, 'fase');
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'estado');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=FasesEstadosTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_06_160004_create_fases_table.php database/migrations/2026_07_06_160010_create_estados_table.php app/Models/Fase.php app/Models/Estado.php tests/Feature/Schema/FasesEstadosTest.php
git commit -m "feat: version fases and estados tables with FK relationship"
```

---

## Task 5: Roles de usuario (Rol enum + columnas rol/activo + usuario admin semilla)

**Files:**
- Create: `app/Enums/Rol.php`
- Create: `database/migrations/2026_07_06_160011_add_rol_and_activo_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Create: `database/seeders/AdminUserSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Schema/UserRolTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Schema;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRolTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_rol_is_cast_to_the_rol_enum(): void
    {
        $user = User::factory()->create(['rol' => Rol::Gestor]);

        $this->assertInstanceOf(Rol::class, $user->fresh()->rol);
        $this->assertSame(Rol::Gestor, $user->fresh()->rol);
    }

    public function test_user_activo_is_cast_to_boolean(): void
    {
        $user = User::factory()->create(['activo' => 1]);

        $this->assertIsBool($user->fresh()->activo);
        $this->assertTrue($user->fresh()->activo);
    }

    public function test_admin_seeder_creates_a_default_administrador(): void
    {
        $this->seed(\Database\Seeders\AdminUserSeeder::class);

        $admin = User::where('email', 'admin@gestpro.local')->first();

        $this->assertNotNull($admin);
        $this->assertSame(Rol::Administrador, $admin->rol);
        $this->assertTrue($admin->activo);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Admin123!', $admin->password));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UserRolTest`
Expected: FAIL — no existe `App\Enums\Rol`, ni las columnas `rol`/`activo` en el `users` de prueba (sqlite en memoria recreado por `RefreshDatabase` desde las migraciones actuales, que aún no las tienen), ni `AdminUserSeeder`.

- [ ] **Step 3: Write the Rol enum**

```php
<?php

namespace App\Enums;

enum Rol: string
{
    case Administrador = 'Administrador';
    case Gestor = 'Gestor';
    case Consulta = 'Consulta';
}
```

- [ ] **Step 4: Write the migration**

```php
<?php

use App\Enums\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 20)->nullable()->default(Rol::Consulta->value);
            $table->boolean('activo')->nullable()->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rol', 'activo']);
        });
    }
};
```

- [ ] **Step 5: Modify the User model**

En `app/Models/User.php`, reemplaza el bloque `$fillable` y `$casts`:

```php
    protected $fillable = [
        'name',
        'email',
        'password',
        'rol',
        'activo',
    ];
```

```php
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'rol' => \App\Enums\Rol::class,
        'activo' => 'boolean',
    ];
```

- [ ] **Step 6: Modify the UserFactory**

En `database/factories/UserFactory.php`, añade `rol` y `activo` a `definition()` e importa el enum:

```php
use App\Enums\Rol;
```

```php
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'rol' => Rol::Consulta,
            'activo' => true,
        ];
    }
```

- [ ] **Step 7: Write the AdminUserSeeder**

```php
<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@gestpro.local'],
            [
                'name' => 'Administrador GestPro',
                'password' => Hash::make('Admin123!'),
                'rol' => Rol::Administrador,
                'activo' => true,
            ]
        );
    }
}
```

- [ ] **Step 8: Update DatabaseSeeder to call the new seeders**

Reemplaza el contenido de `database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartamentosSeeder::class,
            MunicipiosSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
```

- [ ] **Step 9: Run test to verify it passes**

Run: `php artisan test --filter=UserRolTest`
Expected: PASS (3 tests)

- [ ] **Step 10: Commit**

```bash
git add app/Enums/Rol.php database/migrations/2026_07_06_160011_add_rol_and_activo_to_users_table.php app/Models/User.php database/factories/UserFactory.php database/seeders/AdminUserSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Schema/UserRolTest.php
git commit -m "feat: add Rol enum, cast users.rol/activo, and seed a default Administrador user"
```

---

## Task 6: Proyectos (múltiples FKs a catálogos)

**Files:**
- Create: `database/migrations/2026_07_06_160012_create_proyectos_table.php`
- Create: `app/Models/Proyecto.php`
- Test: `tests/Feature/Schema/ProyectosTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\Entidad;
use App\Models\Estado;
use App\Models\Fase;
use App\Models\Municipio;
use App\Models\Proyecto;
use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProyectosTest extends TestCase
{
    use RefreshDatabase;

    private function crearCatalogosBase(): array
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $fase = Fase::create(['nombre' => 'Ejecución', 'activo' => true, 'dashboard' => true]);
        $estado = Estado::create(['nombre' => 'Aprobado', 'activo' => true, 'fase' => $fase->id]);
        $sector = Sector::create(['nombre' => 'Vías', 'activo' => true]);
        $entidad = Entidad::create(['nombre' => 'Alcaldía de Medellín', 'activo' => true]);

        return compact('municipio', 'fase', 'estado', 'sector', 'entidad');
    }

    public function test_proyectos_table_has_expected_columns_and_types(): void
    {
        $this->assertTrue(Schema::hasColumns('proyectos', [
            'id', 'municipio', 'nombre', 'descripcion', 'fecha_inicio', 'estado', 'fase',
            'presupuesto', 'entidad_presenta', 'entidad_financia', 'fuente_financiacion', 'progreso', 'sector',
        ]));
    }

    public function test_proyecto_resolves_all_its_relationships(): void
    {
        $c = $this->crearCatalogosBase();

        $proyecto = Proyecto::create([
            'municipio' => $c['municipio']->id,
            'nombre' => 'Pavimentación vía rural',
            'descripcion' => 'Prueba',
            'fecha_inicio' => '2026-01-01',
            'estado' => $c['estado']->id,
            'fase' => $c['fase']->id,
            'presupuesto' => 1500000.50,
            'entidad_presenta' => $c['entidad']->id,
            'entidad_financia' => $c['entidad']->id,
            'progreso' => 42,
            'sector' => $c['sector']->id,
        ]);

        $fresco = $proyecto->fresh();
        $this->assertSame('1500000.50', $fresco->presupuesto);
        $this->assertSame(42, $fresco->progreso);
        $this->assertTrue($fresco->municipioRel->is($c['municipio']));
        $this->assertTrue($fresco->estadoRel->is($c['estado']));
        $this->assertTrue($fresco->faseRel->is($c['fase']));
        $this->assertTrue($fresco->sectorRel->is($c['sector']));
        $this->assertTrue($fresco->entidadPresenta->is($c['entidad']));
    }

    public function test_cannot_delete_a_municipio_with_proyectos(): void
    {
        $c = $this->crearCatalogosBase();
        Proyecto::create(['municipio' => $c['municipio']->id, 'nombre' => 'X']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $c['municipio']->delete();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProyectosTest`
Expected: FAIL — no existe la tabla `proyectos` ni el modelo `Proyecto`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyectos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio')->nullable()->constrained('municipios')->restrictOnDelete();
            $table->text('nombre')->nullable();
            $table->text('descripcion')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->foreignId('estado')->nullable()->constrained('estados')->restrictOnDelete();
            $table->foreignId('fase')->nullable()->constrained('fases')->restrictOnDelete();
            $table->decimal('presupuesto', 15, 2)->nullable();
            $table->foreignId('entidad_presenta')->nullable()->constrained('entidades')->restrictOnDelete();
            $table->foreignId('entidad_financia')->nullable()->constrained('entidades')->restrictOnDelete();
            $table->text('fuente_financiacion')->nullable();
            $table->unsignedTinyInteger('progreso')->nullable();
            $table->foreignId('sector')->nullable()->constrained('sectores')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyectos');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proyecto extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'municipio', 'nombre', 'descripcion', 'fecha_inicio', 'estado', 'fase',
        'presupuesto', 'entidad_presenta', 'entidad_financia', 'fuente_financiacion', 'progreso', 'sector',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'presupuesto' => 'decimal:2',
        'progreso' => 'integer',
    ];

    // These four are named *Rel() (not municipio()/estado()/fase()/sector()) because each FK
    // column is a single word matching what the relation would naturally be called. Eloquent's
    // getAttribute() always returns the raw column value when a relation method has the exact
    // same name as an existing attribute, so e.g. `$proyecto->municipio` would never resolve to
    // the relation if the method were named municipio(). entidadPresenta()/entidadFinancia() don't
    // need the suffix: their columns are snake_case (entidad_presenta/entidad_financia), which
    // never matches the camelCase method name, so there's no collision.
    public function municipioRel(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio');
    }

    public function estadoRel(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'estado');
    }

    public function faseRel(): BelongsTo
    {
        return $this->belongsTo(Fase::class, 'fase');
    }

    public function sectorRel(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'sector');
    }

    public function entidadPresenta(): BelongsTo
    {
        return $this->belongsTo(Entidad::class, 'entidad_presenta');
    }

    public function entidadFinancia(): BelongsTo
    {
        return $this->belongsTo(Entidad::class, 'entidad_financia');
    }

    public function presupuestoComponentes(): HasMany
    {
        return $this->hasMany(PresupuestoProyecto::class, 'proyecto');
    }

    public function procesosLicitacion(): HasMany
    {
        return $this->hasMany(ProcesoLicitacion::class, 'proyecto');
    }

    public function eventosLicitacion(): HasMany
    {
        return $this->hasMany(EventoLicitacion::class, 'proyecto');
    }

    public function checksFormulacion(): HasMany
    {
        return $this->hasMany(CheckFormulacion::class, 'proyecto');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'proyecto');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class, 'proyecto');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ProyectosTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_06_160012_create_proyectos_table.php app/Models/Proyecto.php tests/Feature/Schema/ProyectosTest.php
git commit -m "feat: version proyectos table with FKs to all its catalogs"
```

---

## Task 7: Tablas hijas de Proyectos (presupuesto_proyecto, procesos_licitacion, eventos_licitacion, check_formulacion)

**Files:**
- Create: `database/migrations/2026_07_06_160013_create_presupuesto_proyecto_table.php`
- Create: `database/migrations/2026_07_06_160014_create_procesos_licitacion_table.php`
- Create: `database/migrations/2026_07_06_160015_create_eventos_licitacion_table.php`
- Create: `database/migrations/2026_07_06_160016_create_check_formulacion_table.php`
- Create: `app/Models/PresupuestoProyecto.php`, `app/Models/ProcesoLicitacion.php`, `app/Models/EventoLicitacion.php`, `app/Models/CheckFormulacion.php`
- Test: `tests/Feature/Schema/HijasDeProyectoTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\CheckFormulacion;
use App\Models\EventoLicitacion;
use App\Models\Municipio;
use App\Models\PresupuestoProyecto;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HijasDeProyectoTest extends TestCase
{
    use RefreshDatabase;

    private function crearProyecto(): Proyecto
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        return Proyecto::create(['municipio' => $municipio->id, 'nombre' => 'Proyecto de prueba']);
    }

    public function test_presupuesto_proyecto_belongs_to_proyecto(): void
    {
        $proyecto = $this->crearProyecto();
        $componente = PresupuestoProyecto::create(['proyecto' => $proyecto->id, 'componente' => 'Interventoría', 'valor' => 5000]);

        $this->assertTrue($componente->fresh()->proyectoRel->is($proyecto));
        $this->assertSame('5000.00', $componente->fresh()->valor);
    }

    public function test_check_formulacion_stores_checklist_as_json(): void
    {
        $proyecto = $this->crearProyecto();
        $checklist = ['presupuesto' => ['peso' => 30, 'items' => []]];

        $check = CheckFormulacion::create(['proyecto' => $proyecto->id, 'checklist' => $checklist]);

        $this->assertSame($checklist, $check->fresh()->checklist);
    }

    public function test_evento_licitacion_belongs_to_proceso_and_proyecto(): void
    {
        $proyecto = $this->crearProyecto();
        $proceso = ProcesoLicitacion::create(['proyecto' => $proyecto->id, 'codigo_proceso' => 'LIC-001']);
        $evento = EventoLicitacion::create(['proyecto' => $proyecto->id, 'proceso' => $proceso->id, 'cumplido' => false]);

        $this->assertTrue($evento->fresh()->procesoRel->is($proceso));
        $this->assertFalse($evento->fresh()->cumplido);
    }

    public function test_deleting_a_proyecto_cascades_to_its_children(): void
    {
        $proyecto = $this->crearProyecto();
        PresupuestoProyecto::create(['proyecto' => $proyecto->id, 'componente' => 'X', 'valor' => 100]);

        $proyecto->delete();

        $this->assertSame(0, PresupuestoProyecto::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=HijasDeProyectoTest`
Expected: FAIL — faltan las 4 tablas y modelos.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_07_06_160013_create_presupuesto_proyecto_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuesto_proyecto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto')->constrained('proyectos')->cascadeOnDelete();
            $table->text('componente')->nullable();
            $table->decimal('valor', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_proyecto');
    }
};
```

`database/migrations/2026_07_06_160014_create_procesos_licitacion_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procesos_licitacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto')->constrained('proyectos')->cascadeOnDelete();
            $table->string('codigo_proceso', 100)->nullable();
            $table->foreignId('tipo_proceso')->nullable()->constrained('tipos_procesos')->restrictOnDelete();
            $table->foreignId('modalidad')->nullable()->constrained('modalidades')->restrictOnDelete();
            $table->foreignId('entidad_contratante')->nullable()->constrained('entidades')->restrictOnDelete();
            $table->unsignedTinyInteger('tipo_proponente')->nullable();
            $table->text('entidad_proponente')->nullable();
            $table->decimal('monto', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procesos_licitacion');
    }
};
```

`database/migrations/2026_07_06_160015_create_eventos_licitacion_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_licitacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto')->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('proceso')->constrained('procesos_licitacion')->cascadeOnDelete();
            $table->text('descripcion')->nullable();
            $table->date('fecha')->nullable();
            $table->boolean('cumplido')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_licitacion');
    }
};
```

`database/migrations/2026_07_06_160016_create_check_formulacion_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_formulacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto')->constrained('proyectos')->cascadeOnDelete();
            $table->json('checklist')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_formulacion');
    }
};
```

- [ ] **Step 4: Write the models**

`app/Models/PresupuestoProyecto.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresupuestoProyecto extends Model
{
    public $timestamps = false;

    protected $table = 'presupuesto_proyecto';
    protected $fillable = ['proyecto', 'componente', 'valor'];
    protected $casts = ['valor' => 'decimal:2'];

    // proyectoRel() not proyecto(): the FK column is also called `proyecto` (see Task 4's note
    // on Estado::faseRel() for why this project suffixes colliding relation names with `Rel`).
    public function proyectoRel(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto');
    }
}
```

`app/Models/ProcesoLicitacion.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcesoLicitacion extends Model
{
    public $timestamps = false;

    protected $table = 'procesos_licitacion';
    protected $fillable = [
        'proyecto', 'codigo_proceso', 'tipo_proceso', 'modalidad',
        'entidad_contratante', 'tipo_proponente', 'entidad_proponente', 'monto',
    ];
    protected $casts = ['monto' => 'decimal:2'];

    // proyectoRel()/modalidadRel(): both FK columns (proyecto, modalidad) are single words that
    // would collide with the natural relation name (see Task 4's note on Estado::faseRel()).
    // tipoProceso()/entidadContratante() don't need the suffix: their columns are snake_case
    // (tipo_proceso/entidad_contratante), which never matches the camelCase method name.
    public function proyectoRel(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto');
    }

    public function tipoProceso(): BelongsTo
    {
        return $this->belongsTo(TipoProceso::class, 'tipo_proceso');
    }

    public function modalidadRel(): BelongsTo
    {
        return $this->belongsTo(Modalidad::class, 'modalidad');
    }

    public function entidadContratante(): BelongsTo
    {
        return $this->belongsTo(Entidad::class, 'entidad_contratante');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoLicitacion::class, 'proceso');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'proceso_licitacion');
    }
}
```

`app/Models/EventoLicitacion.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoLicitacion extends Model
{
    public $timestamps = false;

    protected $table = 'eventos_licitacion';
    protected $fillable = ['proyecto', 'proceso', 'descripcion', 'fecha', 'cumplido'];
    protected $casts = [
        'fecha' => 'date',
        'cumplido' => 'boolean',
    ];

    // proyectoRel()/procesoRel(): both FK columns are single words that would collide with the
    // natural relation name (see Task 4's note on Estado::faseRel()).
    public function proyectoRel(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto');
    }

    public function procesoRel(): BelongsTo
    {
        return $this->belongsTo(ProcesoLicitacion::class, 'proceso');
    }
}
```

`app/Models/CheckFormulacion.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckFormulacion extends Model
{
    public $timestamps = false;

    protected $table = 'check_formulacion';
    protected $fillable = ['proyecto', 'checklist'];
    protected $casts = ['checklist' => 'array'];

    // proyectoRel() not proyecto(): the FK column is also called `proyecto` (see Task 4's note
    // on Estado::faseRel()).
    public function proyectoRel(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=HijasDeProyectoTest`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_06_160013_create_presupuesto_proyecto_table.php database/migrations/2026_07_06_160014_create_procesos_licitacion_table.php database/migrations/2026_07_06_160015_create_eventos_licitacion_table.php database/migrations/2026_07_06_160016_create_check_formulacion_table.php app/Models/PresupuestoProyecto.php app/Models/ProcesoLicitacion.php app/Models/EventoLicitacion.php app/Models/CheckFormulacion.php tests/Feature/Schema/HijasDeProyectoTest.php
git commit -m "feat: version proyecto child tables (presupuesto, procesos de licitacion, eventos de licitacion, checklist)"
```

---

## Task 8: Contratos + Anexos de contrato

`anexos_contratos` ya tiene una migración (`database/migrations/2025_01_20_000000_create_anexos_contratos_table.php`) que **nunca se ejecutó** contra la BD real (la tabla `migrations` no la registra) y que además referencia `contratos`, tabla que hasta ahora no tenía migración. Su timestamp (`2025_01_20`) la haría correr *antes* que `contratos` (`2026_07_06`), lo cual rompería la migración por el `foreign()` a una tabla inexistente. Este task la renombra para que corra después.

**Files:**
- Create: `database/migrations/2026_07_06_160017_create_contratos_table.php`
- Move: `database/migrations/2025_01_20_000000_create_anexos_contratos_table.php` → `database/migrations/2026_07_06_160018_create_anexos_contratos_table.php`
- Create: `app/Models/Contrato.php`, `app/Models/AnexoContrato.php`
- Test: `tests/Feature/Schema/ContratosTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\AnexoContrato;
use App\Models\Contrato;
use App\Models\Municipio;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratosTest extends TestCase
{
    use RefreshDatabase;

    private function crearProyecto(): Proyecto
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        return Proyecto::create(['municipio' => $municipio->id, 'nombre' => 'Proyecto de prueba']);
    }

    public function test_contrato_casts_valor_and_anticipo_correctly(): void
    {
        $proyecto = $this->crearProyecto();

        $contrato = Contrato::create([
            'proyecto' => $proyecto->id,
            'n_contrato' => 'C-001',
            'valor' => 25000000,
            'anticipo' => true,
        ]);

        $fresco = $contrato->fresh();
        $this->assertSame('25000000.00', $fresco->valor);
        $this->assertTrue($fresco->anticipo);
    }

    public function test_anexo_contrato_belongs_to_contrato_and_cascades_on_delete(): void
    {
        $proyecto = $this->crearProyecto();
        $contrato = Contrato::create(['proyecto' => $proyecto->id, 'n_contrato' => 'C-002']);

        AnexoContrato::create([
            'contrato_id' => $contrato->id,
            'descripcion' => 'Acta inicial',
            'nombre_archivo' => 'acta.pdf',
            'ruta_archivo' => 'anexos_contratos/acta.pdf',
            'fecha' => '2026-01-01',
        ]);

        $contrato->delete();

        $this->assertSame(0, AnexoContrato::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ContratosTest`
Expected: FAIL — no existe `contratos` ni sus modelos; `anexos_contratos` fallaría al migrar por el orden de timestamps si se intentara correr ahora.

- [ ] **Step 3: Move the anexos_contratos migration to run after contratos**

```bash
git mv database/migrations/2025_01_20_000000_create_anexos_contratos_table.php database/migrations/2026_07_06_160018_create_anexos_contratos_table.php
```

- [ ] **Step 4: Write the contratos migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto')->constrained('proyectos')->cascadeOnDelete();
            $table->string('n_contrato', 100)->nullable();
            $table->text('objeto')->nullable();
            $table->text('contratante')->nullable();
            $table->text('contratista')->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->text('interventoria')->nullable();
            $table->unsignedTinyInteger('avance_fisico')->nullable();
            $table->unsignedTinyInteger('avance_financiero')->nullable();
            $table->string('estado', 30)->nullable();
            $table->boolean('anticipo')->nullable()->default(false);
            $table->foreignId('proceso_licitacion')->nullable()->constrained('procesos_licitacion')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
```

- [ ] **Step 5: Write the models**

`app/Models/Contrato.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contrato extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'proyecto', 'n_contrato', 'objeto', 'contratante', 'contratista', 'valor',
        'fecha_inicio', 'fecha_fin', 'interventoria', 'avance_fisico', 'avance_financiero',
        'estado', 'anticipo', 'proceso_licitacion',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'anticipo' => 'boolean',
    ];

    // proyectoRel() not proyecto(): the FK column is also called `proyecto` (see Task 4's note
    // on Estado::faseRel()). procesoLicitacion() doesn't need the suffix: its column is
    // snake_case (proceso_licitacion), which never matches the camelCase method name.
    public function proyectoRel(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto');
    }

    public function procesoLicitacion(): BelongsTo
    {
        return $this->belongsTo(ProcesoLicitacion::class, 'proceso_licitacion');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(AnexoContrato::class, 'contrato_id');
    }

    public function avancesFinancieros(): HasMany
    {
        return $this->hasMany(AvanceFinanciero::class, 'contrato_id');
    }

    public function avancesFisicos(): HasMany
    {
        return $this->hasMany(AvanceFisico::class, 'contrato_id');
    }
}
```

`app/Models/AnexoContrato.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnexoContrato extends Model
{
    // Explicit $table required: Laravel's pluralizer guesses "anexo_contratos" from "AnexoContrato"
    // (singularizes "Anexo", pluralizes only "Contrato"), not the actual table "anexos_contratos".
    protected $table = 'anexos_contratos';
    protected $fillable = ['contrato_id', 'descripcion', 'nombre_archivo', 'ruta_archivo', 'fecha'];
    protected $casts = ['fecha' => 'date'];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=ContratosTest`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_06_160017_create_contratos_table.php database/migrations/2026_07_06_160018_create_anexos_contratos_table.php database/migrations/2025_01_20_000000_create_anexos_contratos_table.php app/Models/Contrato.php app/Models/AnexoContrato.php tests/Feature/Schema/ContratosTest.php
git commit -m "feat: version contratos table and reorder anexos_contratos migration to run after it"
```

---

## Task 9: Avances financiero y físico (dependen de Contratos)

**Files:**
- Create: `database/migrations/2026_07_06_160019_create_avance_financiero_table.php`
- Create: `database/migrations/2026_07_06_160020_create_avance_fisico_table.php`
- Create: `app/Models/AvanceFinanciero.php`, `app/Models/AvanceFisico.php`
- Test: `tests/Feature/Schema/AvancesTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\AvanceFinanciero;
use App\Models\AvanceFisico;
use App\Models\Contrato;
use App\Models\Municipio;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvancesTest extends TestCase
{
    use RefreshDatabase;

    private function crearContrato(): Contrato
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $proyecto = Proyecto::create(['municipio' => $municipio->id, 'nombre' => 'Proyecto de prueba']);
        return Contrato::create(['proyecto' => $proyecto->id, 'n_contrato' => 'C-001']);
    }

    public function test_avance_financiero_casts_decimals_and_percentage(): void
    {
        $contrato = $this->crearContrato();

        $avance = AvanceFinanciero::create([
            'contrato_id' => $contrato->id,
            'descripcion' => 'Acta 1',
            'fecha_acta' => '2026-02-01',
            'valor_facturado' => 1000000,
            'porcentaje_ejecutado' => 25,
        ]);

        $fresco = $avance->fresh();
        $this->assertSame('1000000.00', $fresco->valor_facturado);
        $this->assertSame(25, $fresco->porcentaje_ejecutado);
        $this->assertTrue($fresco->contrato->is($contrato));
    }

    public function test_avance_fisico_belongs_to_contrato(): void
    {
        $contrato = $this->crearContrato();

        $avance = AvanceFisico::create([
            'contrato_id' => $contrato->id,
            'descripcion_avance_fisico' => 'Excavación completa',
            'fecha_avance_fisico' => '2026-02-15',
            'valor_avance_fisico' => 60,
        ]);

        $this->assertTrue($avance->fresh()->contrato->is($contrato));
        $this->assertSame(60, $avance->fresh()->valor_avance_fisico);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AvancesTest`
Expected: FAIL — no existen las tablas ni modelos.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_07_06_160019_create_avance_financiero_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avance_financiero', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->text('descripcion')->nullable();
            $table->date('fecha_acta')->nullable();
            $table->decimal('valor_facturado', 15, 2)->nullable();
            $table->decimal('amortizacion_50', 15, 2)->nullable();
            $table->decimal('valor_presente_acta', 15, 2)->nullable();
            $table->unsignedTinyInteger('porcentaje_ejecutado')->nullable();
            $table->text('anexo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avance_financiero');
    }
};
```

`database/migrations/2026_07_06_160020_create_avance_fisico_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avance_fisico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->text('descripcion_avance_fisico')->nullable();
            $table->date('fecha_avance_fisico')->nullable();
            $table->unsignedTinyInteger('valor_avance_fisico')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avance_fisico');
    }
};
```

- [ ] **Step 4: Write the models**

`app/Models/AvanceFinanciero.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvanceFinanciero extends Model
{
    public $timestamps = false;

    protected $table = 'avance_financiero';
    protected $fillable = [
        'contrato_id', 'descripcion', 'fecha_acta', 'valor_facturado',
        'amortizacion_50', 'valor_presente_acta', 'porcentaje_ejecutado', 'anexo',
    ];

    protected $casts = [
        'fecha_acta' => 'date',
        'valor_facturado' => 'decimal:2',
        'amortizacion_50' => 'decimal:2',
        'valor_presente_acta' => 'decimal:2',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }
}
```

`app/Models/AvanceFisico.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvanceFisico extends Model
{
    public $timestamps = false;

    protected $table = 'avance_fisico';
    protected $fillable = ['contrato_id', 'descripcion_avance_fisico', 'fecha_avance_fisico', 'valor_avance_fisico'];
    protected $casts = ['fecha_avance_fisico' => 'date'];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=AvancesTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_06_160019_create_avance_financiero_table.php database/migrations/2026_07_06_160020_create_avance_fisico_table.php app/Models/AvanceFinanciero.php app/Models/AvanceFisico.php tests/Feature/Schema/AvancesTest.php
git commit -m "feat: version avance_financiero and avance_fisico tables with FK to contratos"
```

---

## Task 10: Eventos (calendario general)

**Files:**
- Create: `database/migrations/2026_07_06_160021_create_eventos_table.php`
- Create: `app/Models/Evento.php`
- Test: `tests/Feature/Schema/EventosTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\Evento;
use App\Models\Municipio;
use App\Models\Prioridad;
use App\Models\Proyecto;
use App\Models\Responsable;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventosTest extends TestCase
{
    use RefreshDatabase;

    public function test_evento_resolves_all_optional_relationships(): void
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $proyecto = Proyecto::create(['municipio' => $municipio->id, 'nombre' => 'Proyecto de prueba']);
        $tipo = TipoEvento::create(['nombre' => 'Reunión', 'activo' => true]);
        $prioridad = Prioridad::create(['nombre' => 'Alta', 'activo' => true]);
        $responsable = Responsable::create(['nombre' => 'Juan Pérez', 'activo' => true]);

        $evento = Evento::create([
            'titulo' => 'Visita de obra',
            'fecha' => '2026-08-01',
            'tipo_eventos' => $tipo->id,
            'prioridad' => $prioridad->id,
            'estado_evento' => 'pendiente',
            'proyecto' => $proyecto->id,
            'responsable' => $responsable->id,
        ]);

        $fresco = $evento->fresh();
        $this->assertTrue($fresco->tipoEvento->is($tipo));
        $this->assertTrue($fresco->prioridadRel->is($prioridad));
        $this->assertTrue($fresco->proyectoRel->is($proyecto));
        $this->assertTrue($fresco->responsableRel->is($responsable));
    }

    public function test_evento_can_exist_without_a_proyecto(): void
    {
        $tipo = TipoEvento::create(['nombre' => 'Recordatorio', 'activo' => true]);

        $evento = Evento::create(['titulo' => 'Evento sin proyecto', 'tipo_eventos' => $tipo->id]);

        $this->assertNull($evento->fresh()->proyectoRel);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EventosTest`
Expected: FAIL — no existe la tabla `eventos` (de calendario) ni el modelo `Evento`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->text('titulo')->nullable();
            $table->text('descripcion')->nullable();
            $table->date('fecha')->nullable();
            $table->foreignId('tipo_eventos')->nullable()->constrained('tipo_eventos')->restrictOnDelete();
            $table->foreignId('prioridad')->nullable()->constrained('prioridades')->restrictOnDelete();
            $table->string('estado_evento', 20)->nullable();
            $table->foreignId('proyecto')->nullable()->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('responsable')->nullable()->constrained('responsable')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evento extends Model
{
    public $timestamps = false;

    protected $fillable = ['titulo', 'descripcion', 'fecha', 'tipo_eventos', 'prioridad', 'estado_evento', 'proyecto', 'responsable'];
    protected $casts = ['fecha' => 'date'];

    // tipoEvento() doesn't need a suffix: its column is snake_case (tipo_eventos), which never
    // matches the camelCase method name. prioridad()/proyecto()/responsable() would each collide
    // with their own single-word FK column (see Task 4's note on Estado::faseRel()), so they're
    // suffixed with Rel.
    public function tipoEvento(): BelongsTo
    {
        return $this->belongsTo(TipoEvento::class, 'tipo_eventos');
    }

    public function prioridadRel(): BelongsTo
    {
        return $this->belongsTo(Prioridad::class, 'prioridad');
    }

    public function proyectoRel(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto');
    }

    public function responsableRel(): BelongsTo
    {
        return $this->belongsTo(Responsable::class, 'responsable');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=EventosTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_06_160021_create_eventos_table.php app/Models/Evento.php tests/Feature/Schema/EventosTest.php
git commit -m "feat: version eventos (calendar) table with FKs to its catalogs and proyecto"
```

---

## Task 11: Gates de autorización y protección de rutas

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/AutorizacionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutorizacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_can_manage_catalogos_and_usuarios(): void
    {
        $admin = User::factory()->create(['rol' => Rol::Administrador]);

        $this->assertTrue($admin->can('gestionar-catalogos'));
        $this->assertTrue($admin->can('gestionar-usuarios'));
        $this->assertTrue($admin->can('editar-datos'));
    }

    public function test_gestor_can_edit_data_but_not_catalogos_or_usuarios(): void
    {
        $gestor = User::factory()->create(['rol' => Rol::Gestor]);

        $this->assertTrue($gestor->can('editar-datos'));
        $this->assertFalse($gestor->can('gestionar-catalogos'));
        $this->assertFalse($gestor->can('gestionar-usuarios'));
    }

    public function test_consulta_cannot_write_anything(): void
    {
        $consulta = User::factory()->create(['rol' => Rol::Consulta]);

        $this->assertFalse($consulta->can('editar-datos'));
        $this->assertFalse($consulta->can('gestionar-catalogos'));
        $this->assertFalse($consulta->can('gestionar-usuarios'));
    }

    public function test_gestor_gets_403_when_creating_a_municipio_via_api(): void
    {
        $gestor = User::factory()->create(['rol' => Rol::Gestor]);
        $token = auth('api')->login($gestor);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/GestPro/guardarMunicipio', ['nombre' => 'Prueba']);

        $response->assertStatus(403);
    }

    public function test_gestor_can_create_a_proyecto_via_api(): void
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = \App\Models\Municipio::create(['nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $gestor = User::factory()->create(['rol' => Rol::Gestor]);
        $token = auth('api')->login($gestor);

        // ProyectoController::guardarProyecto (app/Http/Controllers/ProyectoController.php:530-549) lee estas
        // claves directamente de $request->all() sin isset(), incluida 'accion' => 'Agregar' para la rama de inserción.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/GestPro/guardarProyecto', [
                'accion' => 'Agregar',
                'municipio' => $municipio->id,
                'nombre' => 'Proyecto vía API',
                'descripcion' => 'Creado desde el test de autorización',
                'fechaInicio' => '2026-01-01',
                'estado' => null,
                'fase' => null,
                'presupuesto' => null,
                'entidadPresenta' => null,
                'entidadFinancia' => null,
                'fuenteFinanciamiento' => null,
                'sector' => null,
            ]);

        $response->assertStatus(200);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AutorizacionTest`
Expected: FAIL — los Gates `gestionar-catalogos`, `gestionar-usuarios` y `editar-datos` no existen todavía; las rutas no están protegidas por rol.

- [ ] **Step 3: Define the Gates**

En `app/Providers/AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Enums\Rol;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(\Tymon\JWTAuth\Providers\LaravelServiceProvider::class);
    }

    public function boot(): void
    {
        Gate::define('gestionar-catalogos', fn ($user) => $user->rol === Rol::Administrador);
        Gate::define('gestionar-usuarios', fn ($user) => $user->rol === Rol::Administrador);
        Gate::define('editar-datos', fn ($user) => in_array($user->rol, [Rol::Administrador, Rol::Gestor], true));
    }
}
```

- [ ] **Step 4: Apply the gates to routes/api.php**

Reemplaza el grupo protegido completo en `routes/api.php` (desde `Route::middleware('auth:api')->group(...)` hasta su cierre) por:

```php
// 🔒 Rutas protegidas con JWT
Route::middleware('auth:api')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    // Lectura: cualquier rol autenticado (Administrador, Gestor o Consulta)
    Route::get('/proyectos', [ProyectoController::class, 'proyectos']);
    Route::get('/municipios', [ProyectoController::class, 'municipios']);
    Route::get('/estados', [ProyectoController::class, 'estados']);
    Route::get('/fases', [ProyectoController::class, 'fases']);
    Route::get('/entidades', [ProyectoController::class, 'entidades']);
    Route::get('/eventos', [ProyectoController::class, 'eventos']);
    Route::get('/tiposEventos', [ProyectoController::class, 'tiposEventos']);
    Route::get('/prioridades', [ProyectoController::class, 'prioridades']);
    Route::get('/responsables', [ProyectoController::class, 'responsables']);
    Route::get('/departamentos', [ProyectoController::class, 'departamentos']);
    Route::get('/eventosListar', [ProyectoController::class, 'eventosListar']);
    Route::get('/listarContratos', [ProyectoController::class, 'listarContratos']);
    Route::get('/sectores', [ProyectoController::class, 'sectores']);
    Route::get('/tiposProcesos', [ProyectoController::class, 'tiposProcesos']);
    Route::get('/modalidades', [ProyectoController::class, 'modalidades']);

    // Escritura de datos de negocio: Administrador o Gestor
    Route::middleware('can:editar-datos')->group(function () {
        Route::post('/guardarProyecto', [ProyectoController::class, 'guardarProyecto']);
        Route::post('/eliminarProyecto', [ProyectoController::class, 'eliminarProyecto']);
        Route::post('/guardarEvento', [ProyectoController::class, 'guardarEvento']);
        Route::post('/eliminarEvento', [ProyectoController::class, 'eliminarEvento']);
        Route::post('/guardarContrato', [ProyectoController::class, 'guardarContrato']);
        Route::post('/subirAnexo', [ProyectoController::class, 'subirAnexo']);
        Route::post('/subirActa', [ProyectoController::class, 'subirActa']);
        Route::post('/eliminarAnexo', [ProyectoController::class, 'eliminarAnexo']);
        Route::post('/eliminarContrato', [ProyectoController::class, 'eliminarContrato']);
    });

    // Gestión de catálogos: solo Administrador
    Route::middleware('can:gestionar-catalogos')->group(function () {
        Route::post('/activarMunicipio', [ProyectoController::class, 'activarMunicipio']);
        Route::post('/guardarMunicipio', [ProyectoController::class, 'guardarMunicipio']);
        Route::post('/eliminarMunicipio', [ProyectoController::class, 'eliminarMunicipio']);
        Route::post('/guardarEstado', [ProyectoController::class, 'guardarEstado']);
        Route::post('/eliminarEstado', [ProyectoController::class, 'eliminarEstado']);
        Route::post('/activarEstado', [ProyectoController::class, 'activarEstado']);
        Route::post('/guardarFase', [ProyectoController::class, 'guardarFase']);
        Route::post('/eliminarFase', [ProyectoController::class, 'eliminarFase']);
        Route::post('/activarFase', [ProyectoController::class, 'activarFase']);
        Route::post('/guardarTipoEvento', [ProyectoController::class, 'guardarTipoEvento']);
        Route::post('/eliminarTipoEvento', [ProyectoController::class, 'eliminarTipoEvento']);
        Route::post('/activarTipoEvento', [ProyectoController::class, 'activarTipoEvento']);
        Route::post('/guardarPrioridad', [ProyectoController::class, 'guardarPrioridad']);
        Route::post('/eliminarPrioridad', [ProyectoController::class, 'eliminarPrioridad']);
        Route::post('/activarPrioridad', [ProyectoController::class, 'activarPrioridad']);
        Route::post('/guardarResponsable', [ProyectoController::class, 'guardarResponsable']);
        Route::post('/eliminarResponsable', [ProyectoController::class, 'eliminarResponsable']);
        Route::post('/activarResponsable', [ProyectoController::class, 'activarResponsable']);
        Route::post('/guardarEntidad', [ProyectoController::class, 'guardarEntidad']);
        Route::post('/eliminarEntidad', [ProyectoController::class, 'eliminarEntidad']);
        Route::post('/activarEntidad', [ProyectoController::class, 'activarEntidad']);
        Route::post('/activarFaseDashboard', [ProyectoController::class, 'activarFaseDashboard']);
        Route::post('/guardarSector', [ProyectoController::class, 'guardarSector']);
        Route::post('/eliminarSector', [ProyectoController::class, 'eliminarSector']);
        Route::post('/activarSector', [ProyectoController::class, 'activarSector']);
        Route::post('/guardarTipoProceso', [ProyectoController::class, 'guardarTipoProceso']);
        Route::post('/eliminarTipoProceso', [ProyectoController::class, 'eliminarTipoProceso']);
        Route::post('/activarTipoProceso', [ProyectoController::class, 'activarTipoProceso']);
        Route::post('/guardarModalidad', [ProyectoController::class, 'guardarModalidad']);
        Route::post('/eliminarModalidad', [ProyectoController::class, 'eliminarModalidad']);
        Route::post('/activarModalidad', [ProyectoController::class, 'activarModalidad']);
    });

    // Gestión de usuarios: solo Administrador
    Route::middleware('can:gestionar-usuarios')->group(function () {
        Route::get('/usuarios', [ProyectoController::class, 'usuarios']);
        Route::post('/activarUsuario', [ProyectoController::class, 'activarUsuario']);
        Route::post('/guardarUsuario', [ProyectoController::class, 'guardarUsuario']);
        Route::post('/validarEmail', [ProyectoController::class, 'validarEmail']);
    });
});
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=AutorizacionTest`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Providers/AppServiceProvider.php routes/api.php tests/Feature/AutorizacionTest.php
git commit -m "feat: enforce role-based authorization with Gates on catalog, user and business-data routes"
```

---

## Task 12: Manejo de error 403 en el frontend

**Files:**
- Modify: `resources/js/axios.js`

- [ ] **Step 1: Add a 403 handler to the existing response interceptor**

En `resources/js/axios.js`, reemplaza el interceptor de respuesta actual:

```javascript
// Interceptor para manejar errores 419 (CSRF token mismatch)
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 419) {
            // Recargar la página para obtener un nuevo token CSRF
            console.warn('CSRF token mismatch, recargando página...');
            window.location.reload();
        }
        return Promise.reject(error);
    }
);
```

por:

```javascript
// Interceptor para manejar errores 419 (CSRF) y 403 (sin permiso)
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 419) {
            // Recargar la página para obtener un nuevo token CSRF
            console.warn('CSRF token mismatch, recargando página...');
            window.location.reload();
        }

        if (error.response && error.response.status === 403) {
            console.warn('No tienes permiso para realizar esta acción.');
        }

        return Promise.reject(error);
    }
);
```

No se ocultan pestañas ni botones por rol en esta fase (decisión explícita registrada en el spec) — este cambio solo evita que un 403 se trate como un error genérico no manejado.

- [ ] **Step 2: Verify manually**

No hay test automatizado de frontend en este proyecto (no hay Jest/Vitest configurado). Verifica manualmente en el navegador: inicia sesión con un usuario Gestor, intenta guardar un municipio desde Parámetros, y confirma en la consola del navegador que aparece el mensaje `No tienes permiso...` en vez de un error sin manejar.

- [ ] **Step 3: Commit**

```bash
git add resources/js/axios.js
git commit -m "feat: handle 403 responses gracefully in the axios interceptor"
```

---

## Task 13: Ejecución final — migrar y verificar manualmente

**Files:** ninguno (solo comandos)

- [ ] **Step 1: Run the full automated test suite**

Run: `php artisan test`
Expected: todos los tests pasan (los de este plan más `tests/Feature/ExampleTest.php` y `tests/Unit/ExampleTest.php` ya existentes).

- [ ] **Step 2: Apply the migrations to the local MySQL database**

Este paso **borra y recrea** todas las tablas de negocio en la base `gest_pro` local (municipios/departamentos se repueblan desde los seeders con los datos reales exportados; el resto queda vacío, según lo acordado). Antes de correrlo, confirma que no hay ningún dato de prueba que quieras conservar manualmente — si lo hay, expórtalo a mano primero.

Run: `php artisan migrate:fresh --seed`
Expected: 22 migraciones nuevas + las 4 de Laravel corren sin error; al final ves el log del seeder poblando `departamentos`, `municipios` y creando el usuario `admin@gestpro.local`.

- [ ] **Step 3: Verify the schema manually**

Run: `"/c/xampp/mysql/bin/mysql.exe" -u root gest_pro -e "SELECT COUNT(*) FROM municipios; SELECT COUNT(*) FROM departamentos; SELECT email, rol, activo FROM users;"`
Expected: 1123 municipios, 34 departamentos, y una fila `admin@gestpro.local | Administrador | 1`.

- [ ] **Step 4: Verify login and role enforcement end-to-end**

Con el servidor corriendo (`php artisan serve` o vía XAMPP), inicia sesión desde el frontend con `admin@gestpro.local` / `Admin123!`, confirma que puedes ver el dashboard y Parámetros con normalidad. Si tienes forma de crear rápidamente un segundo usuario con rol `Gestor` (vía tinker: `php artisan tinker` → `App\Models\User::factory()->create(['email' => 'gestor@gestpro.local', 'password' => Hash::make('Gestor123!'), 'rol' => App\Enums\Rol::Gestor])`), inicia sesión con ese usuario y confirma que intentar guardar un municipio devuelve un 403 visible en la consola, mientras que crear un proyecto funciona con normalidad.

- [ ] **Step 5: Update the spec status**

En `docs/superpowers/specs/2026-07-06-esquema-autorizacion-design.md`, cambia la línea `**Estado:** Aprobado, pendiente de plan de implementación` por `**Estado:** Implementado 2026-07-06`.

- [ ] **Step 6: Commit**

```bash
git add docs/superpowers/specs/2026-07-06-esquema-autorizacion-design.md
git commit -m "docs: mark esquema-autorizacion spec as implemented"
```
