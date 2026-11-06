# Plantillas de checklist, etapa 1: fuentes y sectores. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** reemplazar el texto libre de la fuente de financiación por un catálogo administrable, con una o varias fuentes por proyecto o "Por definir", y crear la pantalla de sectores que falta.

**Arquitectura:** tres migraciones reversibles agregan el catálogo, la relación con proyectos y la conversión del texto actual. `FuentesProyecto` concentra las reglas de asignación. `proyectos.fuente_financiacion` se conserva con los nombres unidos, para no romper las vistas que ya la muestran. El expediente automático deja de buscar palabras y detecta la fuente con clave `sgr`.

**Tecnologías:** Laravel 12, PHP 8.2, React 18 con Laravel Mix, PHPUnit sobre SQLite en memoria, Vitest.

**Diseño:** `docs/superpowers/specs/2026-11-06-plantillas-checklist-design.md`

---

## Plan general

Cada etapa tiene su propio plan, que se escribe al validar la anterior, porque cada una depende de lo que se aprenda en la previa. Este documento cubre solo la etapa 1.

| Etapa | Plan | Estado |
|---|---|---|
| 1. Fuentes y sectores | Este documento | Listo para ejecutar |
| 2. Plantillas sobre versiones | Plan propio | Se escribe al validar la etapa 1 |
| 3. Seguimiento del requisito | Plan propio | Se escribe al validar la etapa 2 |
| 4. Administración de plantillas | Plan propio | Se escribe al validar la etapa 3 |
| 5. Composición en el expediente | Plan propio | Se escribe al validar la etapa 4. Debe incluir el cambio de nombre a "Expediente", según "Textos en pantalla" en la sección 6 del diseño |
| 6. Versiones y cierre | Plan propio | Se escribe al validar la etapa 5 |

## Reglas de ejecución

- **Nada de esto se aplica a `gest_pro` sin autorización del usuario.** Tampoco se usa `migrate:rollback` allí. Las pruebas de migración se hacen en SQLite y en la base temporal `gestpro_verif_tmp`.
- **Los pasos de commit requieren que el usuario haya autorizado comitear.** Sin esa autorización, se deja el trabajo sin comitear y se avisa.
- Nomenclatura en español sin tildes para tablas, columnas y métodos nuevos. Los mensajes de commit van en inglés, como el historial del repositorio.
- Comando de pruebas PHP: `php artisan test`. Comando de pruebas JS: `npm test`.

## Fuera de esta etapa

- Crear el expediente al editar un proyecto que deja de estar "Por definir", y crearlo para fuentes distintas de SGR. Ambos son de la etapa 5, porque antes no hay plantillas para otras fuentes.
- Eliminar fuentes o sectores. La propuesta pide crear, editar y desactivar. La ruta `/eliminarSector` apunta a un método que no existe, así que se retira.
- Las rutas de tipos de proceso y modalidades también apuntan a métodos que no existen. No se tocan porque no son de este trabajo.

## Error existente que corrige esta etapa

`handleSubmit` en `Parametros.jsx` compara `type === 'entidades'`, pero `type` no existe en esa función: guardar una entidad lanza `ReferenceError`, y lo mismo ocurriría con cualquier catálogo nuevo que llegara a esa línea. La tarea 9 la cambia a `modalType`.

## Archivos

| Archivo | Acción | Responsabilidad |
|---|---|---|
| `database/migrations/2026_11_06_100000_create_fuentes_financiacion_table.php` | Crear | Catálogo y las 10 fuentes iniciales |
| `database/migrations/2026_11_06_100001_create_proyecto_fuentes_table.php` | Crear | Relación proyecto y fuente |
| `database/migrations/2026_11_06_100002_add_financiacion_to_proyectos_table.php` | Crear | Columnas nuevas y conversión del texto |
| `app/Models/FuenteFinanciacion.php` | Crear | Modelo del catálogo |
| `app/Models/Proyecto.php` | Modificar | Relación `fuentes()` y columnas nuevas |
| `app/Services/Financiacion/FuentesProyecto.php` | Crear | Reglas para asignar fuentes a un proyecto |
| `app/Http/Controllers/FuenteFinanciacionController.php` | Crear | Listar, guardar y activar fuentes |
| `app/Http/Controllers/SectorController.php` | Crear | Guardar y activar sectores |
| `app/Http/Controllers/ProyectoController.php` | Modificar | Guardar y listar fuentes del proyecto |
| `app/Services/Viabilizacion/ExpedienteService.php` | Modificar | Detectar SGR por el catálogo |
| `routes/api.php` | Modificar | Rutas de fuentes y sectores |
| `routes/console.php` | Modificar | Comando de informe y descripción del existente |
| `database/seeders/DemoDataSeeder.php` | Modificar | Asignar fuentes a los proyectos de demostración |
| `resources/js/utils/fuentesProyecto.js` | Crear | Lógica pura del selector de fuentes |
| `resources/js/utils/navegacion.js`, `iconosNavegacion.js` | Modificar | Dos secciones nuevas de catálogo |
| `resources/js/components/Parametros.jsx` | Modificar | Selector de fuentes y dos catálogos |
| `resources/css/Parametros.css` | Modificar | Estilo del selector |
| `tests/Feature/Schema/FuentesFinanciacionTest.php` | Crear | Esquema y catálogo inicial |
| `tests/Feature/Schema/MigracionFuentesProyectosTest.php` | Crear | Conversión del texto y rollback |
| `tests/Feature/FuentesFinanciacionApiTest.php` | Crear | API del catálogo |
| `tests/Feature/SectoresApiTest.php` | Crear | API de sectores |
| `tests/Feature/ProyectoFuentesTest.php` | Crear | Reglas al guardar un proyecto e informe |
| `tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php` | Modificar | Detección por catálogo |
| `tests/Feature/AutorizacionTest.php` | Modificar | Nuevo formato del formulario |
| `resources/js/utils/fuentesProyecto.test.js` | Crear | Pruebas del selector |
| `resources/js/utils/navegacion.test.js` | Modificar | Rutas nuevas |

---

### Tarea 1: Preparar la rama y la línea base

**Archivos:** ninguno.

- [ ] **Paso 1: Crear la rama desde el trabajo actual**

```bash
git switch feature/rediseno-interfaz
git switch -c feature/plantillas-checklist
```

- [ ] **Paso 2: Ejecutar las pruebas PHP para registrar la línea base**

Run: `php artisan test`
Expected: todas pasan. Anotar el total en el informe de la etapa. Si alguna falla, detenerse y avisar: no es parte de este trabajo y hay que decidir qué hacer con ella.

- [ ] **Paso 3: Ejecutar las pruebas JS para registrar la línea base**

Run: `npm test`
Expected: todas pasan. Anotar el total.

---

### Tarea 2: Catálogo de fuentes y relación con proyectos

**Archivos:**
- Crear: `database/migrations/2026_11_06_100000_create_fuentes_financiacion_table.php`
- Crear: `database/migrations/2026_11_06_100001_create_proyecto_fuentes_table.php`
- Crear: `app/Models/FuenteFinanciacion.php`
- Modificar: `app/Models/Proyecto.php`
- Prueba: `tests/Feature/Schema/FuentesFinanciacionTest.php`

- [ ] **Paso 1: Escribir la prueba que falla**

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\FuenteFinanciacion;
use App\Models\Proyecto;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuentesFinanciacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_catalogo_inicia_con_las_diez_fuentes_de_la_propuesta(): void
    {
        $this->assertSame([
            'Sistema General de Regalías (SGR)',
            'Presupuesto General de la Nación (PGN)',
            'Sistema General de Participaciones (SGP)',
            'Recursos propios',
            'Cofinanciación',
            'Crédito público',
            'Cooperación internacional',
            'Obras por Impuestos',
            'Asociaciones Público-Privadas (APP)',
            'Otras fuentes',
        ], FuenteFinanciacion::orderBy('orden')->pluck('nombre')->all());

        $this->assertTrue(FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('activo'));
        $this->assertSame('Recursos propios', FuenteFinanciacion::where('clave', FuenteFinanciacion::RECURSOS_PROPIOS)->value('nombre'));
    }

    public function test_un_proyecto_puede_tener_varias_fuentes(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto']);
        $ids = FuenteFinanciacion::whereIn('clave', [FuenteFinanciacion::SGR, FuenteFinanciacion::RECURSOS_PROPIOS])->pluck('id');

        $proyecto->fuentes()->sync($ids);

        $this->assertEqualsCanonicalizing($ids->all(), $proyecto->fuentes()->pluck('fuentes_financiacion.id')->all());
    }

    public function test_no_se_puede_borrar_una_fuente_con_proyectos(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto']);
        $fuente = FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->first();
        $proyecto->fuentes()->sync([$fuente->id]);

        $this->expectException(QueryException::class);
        $fuente->delete();
    }

    public function test_borrar_un_proyecto_borra_su_relacion_con_las_fuentes(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto']);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        $proyecto->delete();

        $this->assertDatabaseCount('proyecto_fuentes', 0);
    }
}
```

- [ ] **Paso 2: Ejecutar la prueba y comprobar que falla**

Run: `php artisan test tests/Feature/Schema/FuentesFinanciacionTest.php`
Expected: FALLA con `Class "App\Models\FuenteFinanciacion" not found`.

- [ ] **Paso 3: Crear la migración del catálogo**

`database/migrations/2026_11_06_100000_create_fuentes_financiacion_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FUENTES = [
        ['sgr', 'Sistema General de Regalías (SGR)'],
        ['pgn', 'Presupuesto General de la Nación (PGN)'],
        ['sgp', 'Sistema General de Participaciones (SGP)'],
        ['recursos_propios', 'Recursos propios'],
        ['cofinanciacion', 'Cofinanciación'],
        ['credito_publico', 'Crédito público'],
        ['cooperacion_internacional', 'Cooperación internacional'],
        ['obras_por_impuestos', 'Obras por Impuestos'],
        ['app', 'Asociaciones Público-Privadas (APP)'],
        ['otras', 'Otras fuentes'],
    ];

    public function up(): void
    {
        // Catálogo administrable. `clave` identifica las fuentes que el sistema necesita
        // reconocer (p. ej. sgr); las que crea un administrador no la llevan.
        Schema::create('fuentes_financiacion', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->nullable()->unique();
            $table->string('nombre', 199)->unique();
            $table->text('descripcion')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
        });

        foreach (self::FUENTES as $indice => [$clave, $nombre]) {
            DB::table('fuentes_financiacion')->insert([
                'clave' => $clave,
                'nombre' => $nombre,
                'orden' => $indice + 1,
                'activo' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuentes_financiacion');
    }
};
```

- [ ] **Paso 4: Crear la migración de la relación**

`database/migrations/2026_11_06_100001_create_proyecto_fuentes_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_fuentes', function (Blueprint $table) {
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('fuente_id')->constrained('fuentes_financiacion')->restrictOnDelete();
            $table->primary(['proyecto_id', 'fuente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_fuentes');
    }
};
```

- [ ] **Paso 5: Crear el modelo**

`app/Models/FuenteFinanciacion.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FuenteFinanciacion extends Model
{
    public const SGR = 'sgr';
    public const RECURSOS_PROPIOS = 'recursos_propios';

    public $timestamps = false;

    protected $table = 'fuentes_financiacion';
    protected $fillable = ['clave', 'nombre', 'descripcion', 'orden', 'activo'];
    protected $casts = ['activo' => 'boolean', 'orden' => 'integer'];

    public function proyectos(): BelongsToMany
    {
        return $this->belongsToMany(Proyecto::class, 'proyecto_fuentes', 'fuente_id', 'proyecto_id');
    }
}
```

- [ ] **Paso 6: Agregar la relación al proyecto**

En `app/Models/Proyecto.php`, agregar el import después de `use Illuminate\Database\Eloquent\Relations\BelongsTo;`:

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

Y agregar este método antes de `public function presupuestoComponentes()`:

```php
    public function fuentes(): BelongsToMany
    {
        return $this->belongsToMany(FuenteFinanciacion::class, 'proyecto_fuentes', 'proyecto_id', 'fuente_id');
    }

```

- [ ] **Paso 7: Ejecutar la prueba y comprobar que pasa**

Run: `php artisan test tests/Feature/Schema/FuentesFinanciacionTest.php`
Expected: PASA, 4 pruebas.

- [ ] **Paso 8: Comitear**

```bash
git add database/migrations/2026_11_06_100000_create_fuentes_financiacion_table.php database/migrations/2026_11_06_100001_create_proyecto_fuentes_table.php app/Models/FuenteFinanciacion.php app/Models/Proyecto.php tests/Feature/Schema/FuentesFinanciacionTest.php
git commit -m "feat: add the funding source catalog and link it to projects" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarea 3: Convertir el texto de la fuente de los proyectos existentes

**Archivos:**
- Crear: `database/migrations/2026_11_06_100002_add_financiacion_to_proyectos_table.php`
- Modificar: `app/Models/Proyecto.php`
- Prueba: `tests/Feature/Schema/MigracionFuentesProyectosTest.php`

- [ ] **Paso 1: Escribir la prueba que falla**

La prueba baja la migración, inserta proyectos con el texto antiguo y la vuelve a subir, para comprobar la conversión y el rollback con datos reales.

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\FuenteFinanciacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigracionFuentesProyectosTest extends TestCase
{
    use RefreshDatabase;

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_06_100002_add_financiacion_to_proyectos_table.php');
    }

    /** Crea un proyecto con el esquema anterior, convierte y devuelve el resultado. */
    private function convertir(?string $texto): object
    {
        $migracion = $this->migracion();
        $migracion->down();
        $id = DB::table('proyectos')->insertGetId(['nombre' => 'Prueba', 'fuente_financiacion' => $texto]);
        $migracion->up();

        return DB::table('proyectos')->where('id', $id)->first();
    }

    private function clavesDe(object $proyecto): array
    {
        return DB::table('proyecto_fuentes')
            ->join('fuentes_financiacion', 'fuentes_financiacion.id', 'proyecto_fuentes.fuente_id')
            ->where('proyecto_id', $proyecto->id)
            ->orderBy('orden')
            ->pluck('clave')
            ->all();
    }

    public function test_el_texto_mas_comun_se_convierte_en_dos_fuentes(): void
    {
        $proyecto = $this->convertir('Recursos propios y regalías');

        $this->assertSame([FuenteFinanciacion::SGR, FuenteFinanciacion::RECURSOS_PROPIOS], $this->clavesDe($proyecto));
        $this->assertSame('Sistema General de Regalías (SGR), Recursos propios', $proyecto->fuente_financiacion);
        $this->assertSame('Recursos propios y regalías', $proyecto->fuente_financiacion_original);
        $this->assertEquals(0, $proyecto->financiacion_por_definir);
    }

    public function test_reconoce_regalias_sin_importar_tildes_ni_mayusculas(): void
    {
        foreach (['REGALÍAS', 'regalias directas', 'Sistema General de Regalias', 'Recursos SGR'] as $texto) {
            $this->assertSame([FuenteFinanciacion::SGR], $this->clavesDe($this->convertir($texto)), $texto);
        }
    }

    public function test_reconoce_recursos_propios(): void
    {
        $this->assertSame([FuenteFinanciacion::RECURSOS_PROPIOS], $this->clavesDe($this->convertir('RECURSOS PROPIOS del municipio')));
    }

    public function test_un_texto_sin_reconocer_queda_por_definir_y_conserva_lo_escrito(): void
    {
        $proyecto = $this->convertir('Convenio con la gobernación');

        $this->assertSame([], $this->clavesDe($proyecto));
        $this->assertEquals(1, $proyecto->financiacion_por_definir);
        $this->assertSame('Convenio con la gobernación', $proyecto->fuente_financiacion);
        $this->assertSame('Convenio con la gobernación', $proyecto->fuente_financiacion_original);
    }

    public function test_un_proyecto_sin_texto_queda_por_definir(): void
    {
        $proyecto = $this->convertir(null);

        $this->assertSame([], $this->clavesDe($proyecto));
        $this->assertEquals(1, $proyecto->financiacion_por_definir);
        $this->assertNull($proyecto->fuente_financiacion_original);
    }

    public function test_el_rollback_restaura_el_texto_y_quita_las_columnas(): void
    {
        $proyecto = $this->convertir('Recursos propios y regalías');

        $this->migracion()->down();

        $this->assertSame('Recursos propios y regalías', DB::table('proyectos')->where('id', $proyecto->id)->value('fuente_financiacion'));
        $this->assertFalse(Schema::hasColumn('proyectos', 'financiacion_por_definir'));
        $this->assertFalse(Schema::hasColumn('proyectos', 'fuente_financiacion_original'));
        $this->assertDatabaseCount('proyecto_fuentes', 0);
    }
}
```

- [ ] **Paso 2: Ejecutar la prueba y comprobar que falla**

Run: `php artisan test tests/Feature/Schema/MigracionFuentesProyectosTest.php`
Expected: FALLA porque el archivo de la migración no existe.

- [ ] **Paso 3: Crear la migración**

`database/migrations/2026_11_06_100002_add_financiacion_to_proyectos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            $table->boolean('financiacion_por_definir')->default(false)->after('fuente_financiacion');
            $table->text('fuente_financiacion_original')->nullable()->after('financiacion_por_definir');
        });

        $catalogo = DB::table('fuentes_financiacion')
            ->whereIn('clave', ['sgr', 'recursos_propios'])
            ->orderBy('orden')
            ->get()
            ->keyBy('clave');

        DB::table('proyectos')->select('id', 'fuente_financiacion')->orderBy('id')
            ->each(function (object $proyecto) use ($catalogo) {
                $texto = Str::lower(Str::ascii(trim((string) $proyecto->fuente_financiacion)));

                $reconocidas = $catalogo->filter(fn (object $fuente) => match ($fuente->clave) {
                    'sgr' => Str::contains($texto, ['regalia', 'sgr']),
                    'recursos_propios' => Str::contains($texto, 'recursos propios'),
                });

                foreach ($reconocidas as $fuente) {
                    DB::table('proyecto_fuentes')->insert(['proyecto_id' => $proyecto->id, 'fuente_id' => $fuente->id]);
                }

                // Sin reconocer: queda por definir y se sigue viendo lo que estaba escrito.
                DB::table('proyectos')->where('id', $proyecto->id)->update([
                    'financiacion_por_definir' => $reconocidas->isEmpty(),
                    'fuente_financiacion_original' => $proyecto->fuente_financiacion,
                    'fuente_financiacion' => $reconocidas->isEmpty()
                        ? $proyecto->fuente_financiacion
                        : $reconocidas->pluck('nombre')->implode(', '),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('proyecto_fuentes')->delete();

        DB::table('proyectos')->whereNotNull('fuente_financiacion_original')->update([
            'fuente_financiacion' => DB::raw('fuente_financiacion_original'),
        ]);

        Schema::table('proyectos', function (Blueprint $table) {
            $table->dropColumn(['financiacion_por_definir', 'fuente_financiacion_original']);
        });
    }
};
```

- [ ] **Paso 4: Actualizar el modelo**

En `app/Models/Proyecto.php`, reemplazar:

```php
        'presupuesto', 'entidad_presenta', 'entidad_financia', 'fuente_financiacion', 'progreso', 'sector',
    ];
```

por:

```php
        'presupuesto', 'entidad_presenta', 'entidad_financia', 'fuente_financiacion', 'financiacion_por_definir',
        'fuente_financiacion_original', 'progreso', 'sector',
    ];
```

Y reemplazar:

```php
        'progreso' => 'integer',
    ];
```

por:

```php
        'progreso' => 'integer',
        'financiacion_por_definir' => 'boolean',
    ];
```

- [ ] **Paso 5: Ejecutar la prueba y comprobar que pasa**

Run: `php artisan test tests/Feature/Schema/MigracionFuentesProyectosTest.php`
Expected: PASA, 6 pruebas.

- [ ] **Paso 6: Comitear**

```bash
git add database/migrations/2026_11_06_100002_add_financiacion_to_proyectos_table.php app/Models/Proyecto.php tests/Feature/Schema/MigracionFuentesProyectosTest.php
git commit -m "feat: convert the free-text funding source into catalog entries" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarea 4: API del catálogo de fuentes

**Archivos:**
- Crear: `app/Http/Controllers/FuenteFinanciacionController.php`
- Modificar: `routes/api.php`
- Prueba: `tests/Feature/FuentesFinanciacionApiTest.php`

- [ ] **Paso 1: Escribir la prueba que falla**

```php
<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\FuenteFinanciacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuentesFinanciacionApiTest extends TestCase
{
    use RefreshDatabase;

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    public function test_cualquier_usuario_autenticado_lista_el_catalogo_en_orden(): void
    {
        $this->como(Rol::Consulta)->getJson('/GestPro/fuentesFinanciacion')
            ->assertOk()
            ->assertJsonCount(10)
            ->assertJsonPath('0.clave', 'sgr')
            ->assertJsonPath('0.activo', true)
            ->assertJsonPath('9.clave', 'otras');
    }

    public function test_el_administrador_agrega_una_fuente_al_final(): void
    {
        $this->como(Rol::Administrador)->postJson('/GestPro/guardarFuenteFinanciacion', [
            'accion' => 'Agregar',
            'id' => 1730000000000,
            'nombre' => 'Fondo de Adaptación',
            'descripcion' => 'Fondo nacional',
        ])->assertOk();

        $fuente = FuenteFinanciacion::where('nombre', 'Fondo de Adaptación')->firstOrFail();
        $this->assertSame(11, $fuente->orden);
        $this->assertTrue($fuente->activo);
        $this->assertNull($fuente->clave);
        $this->assertSame('Fondo nacional', $fuente->descripcion);
    }

    public function test_editar_cambia_el_nombre_y_conserva_la_clave(): void
    {
        $fuente = FuenteFinanciacion::where('clave', 'pgn')->firstOrFail();

        $this->como(Rol::Administrador)->postJson('/GestPro/guardarFuenteFinanciacion', [
            'accion' => 'Editar',
            'id' => $fuente->id,
            'nombre' => 'PGN',
            'clave' => 'otra',
        ])->assertOk();

        $this->assertSame('PGN', $fuente->fresh()->nombre);
        $this->assertSame('pgn', $fuente->fresh()->clave);
    }

    public function test_rechaza_nombres_vacios_o_repetidos(): void
    {
        $admin = $this->como(Rol::Administrador);

        $admin->postJson('/GestPro/guardarFuenteFinanciacion', ['accion' => 'Agregar', 'nombre' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('nombre');
        $admin->postJson('/GestPro/guardarFuenteFinanciacion', ['accion' => 'Agregar', 'nombre' => 'Recursos propios'])
            ->assertStatus(422)->assertJsonValidationErrors('nombre');
    }

    public function test_editar_sin_cambiar_el_nombre_no_choca_consigo_mismo(): void
    {
        $fuente = FuenteFinanciacion::where('clave', 'sgp')->firstOrFail();

        $this->como(Rol::Administrador)->postJson('/GestPro/guardarFuenteFinanciacion', [
            'accion' => 'Editar', 'id' => $fuente->id, 'nombre' => $fuente->nombre,
        ])->assertOk();
    }

    public function test_activar_alterna_el_estado_segun_la_base_de_datos(): void
    {
        $fuente = FuenteFinanciacion::where('clave', 'otras')->firstOrFail();
        $admin = $this->como(Rol::Administrador);

        $admin->postJson('/GestPro/activarFuenteFinanciacion', ['id' => $fuente->id])->assertOk();
        $this->assertFalse($fuente->fresh()->activo);

        $admin->postJson('/GestPro/activarFuenteFinanciacion', ['id' => $fuente->id])->assertOk();
        $this->assertTrue($fuente->fresh()->activo);
    }

    public function test_solo_el_administrador_modifica_el_catalogo(): void
    {
        $fuente = FuenteFinanciacion::first();

        foreach ([Rol::Gestor, Rol::Consulta] as $rol) {
            $this->como($rol)->postJson('/GestPro/guardarFuenteFinanciacion', ['accion' => 'Agregar', 'nombre' => 'Nueva'])->assertForbidden();
            $this->como($rol)->postJson('/GestPro/activarFuenteFinanciacion', ['id' => $fuente->id])->assertForbidden();
        }
    }
}
```

- [ ] **Paso 2: Ejecutar la prueba y comprobar que falla**

Run: `php artisan test tests/Feature/FuentesFinanciacionApiTest.php`
Expected: FALLA porque las rutas no existen. El GET comodín de `routes/web.php` devuelve HTML y los POST responden 405.

- [ ] **Paso 3: Crear el controlador**

`app/Http/Controllers/FuenteFinanciacionController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\FuenteFinanciacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catálogo de fuentes de financiación. Las fuentes no se eliminan: se desactivan,
 * y una desactivada se conserva en los proyectos que ya la tienen.
 */
class FuenteFinanciacionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            FuenteFinanciacion::query()->orderBy('orden')->orderBy('nombre')->get()
        );
    }

    public function guardar(Request $request): JsonResponse
    {
        $fuente = $request->input('accion') === 'Editar'
            ? FuenteFinanciacion::findOrFail($request->input('id'))
            : new FuenteFinanciacion(['orden' => (int) FuenteFinanciacion::max('orden') + 1, 'activo' => true]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:199', Rule::unique('fuentes_financiacion', 'nombre')->ignore($fuente->id)],
            'descripcion' => ['nullable', 'string'],
        ]);

        $fuente->fill($datos)->save();

        return response()->json(['success' => 'Fuente de financiación guardada correctamente']);
    }

    public function activar(Request $request): JsonResponse
    {
        $fuente = FuenteFinanciacion::findOrFail($request->input('id'));
        $fuente->update(['activo' => ! $fuente->activo]);

        return response()->json(['success' => 'Fuente de financiación actualizada correctamente']);
    }
}
```

- [ ] **Paso 4: Registrar las rutas**

En `routes/api.php`, agregar con los demás imports de controladores:

```php
use App\Http\Controllers\FuenteFinanciacionController;
```

Agregar después de `Route::get('/sectores', [ProyectoController::class, 'sectores']);`:

```php
    Route::get('/fuentesFinanciacion', [FuenteFinanciacionController::class, 'index']);
```

Y agregar dentro del grupo `can:gestionar-catalogos`, antes de `Route::post('/guardarSector', ...)`:

```php
        Route::post('/guardarFuenteFinanciacion', [FuenteFinanciacionController::class, 'guardar']);
        Route::post('/activarFuenteFinanciacion', [FuenteFinanciacionController::class, 'activar']);
```

- [ ] **Paso 5: Ejecutar la prueba y comprobar que pasa**

Run: `php artisan test tests/Feature/FuentesFinanciacionApiTest.php`
Expected: PASA, 7 pruebas.

- [ ] **Paso 6: Comitear**

```bash
git add app/Http/Controllers/FuenteFinanciacionController.php routes/api.php tests/Feature/FuentesFinanciacionApiTest.php
git commit -m "feat: manage funding sources through the API" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarea 5: API de sectores

`routes/api.php` declara `guardarSector`, `activarSector` y `eliminarSector`, pero `ProyectoController` no tiene esos métodos: hoy responden con error. Esta tarea implementa los dos primeros y retira el tercero.

**Archivos:**
- Crear: `app/Http/Controllers/SectorController.php`
- Modificar: `routes/api.php`
- Prueba: `tests/Feature/SectoresApiTest.php`

- [ ] **Paso 1: Escribir la prueba que falla**

```php
<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Proyecto;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectoresApiTest extends TestCase
{
    use RefreshDatabase;

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    public function test_el_administrador_agrega_y_edita_un_sector(): void
    {
        $admin = $this->como(Rol::Administrador);

        $admin->postJson('/GestPro/guardarSector', ['accion' => 'Agregar', 'id' => 1730000000000, 'nombre' => 'Cultura'])->assertOk();
        $sector = Sector::where('nombre', 'Cultura')->firstOrFail();
        $this->assertTrue($sector->activo);

        $admin->postJson('/GestPro/guardarSector', ['accion' => 'Editar', 'id' => $sector->id, 'nombre' => 'Cultura y patrimonio'])->assertOk();
        $this->assertSame('Cultura y patrimonio', $sector->fresh()->nombre);
    }

    public function test_rechaza_nombres_vacios_o_repetidos(): void
    {
        Sector::create(['nombre' => 'Salud', 'activo' => true]);
        $admin = $this->como(Rol::Administrador);

        $admin->postJson('/GestPro/guardarSector', ['accion' => 'Agregar', 'nombre' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('nombre');
        $admin->postJson('/GestPro/guardarSector', ['accion' => 'Agregar', 'nombre' => 'Salud'])
            ->assertStatus(422)->assertJsonValidationErrors('nombre');
    }

    public function test_desactivar_conserva_el_sector_en_sus_proyectos(): void
    {
        $sector = Sector::create(['nombre' => 'Vías', 'activo' => true]);
        $proyecto = Proyecto::create(['nombre' => 'Puente', 'sector' => $sector->id]);

        $this->como(Rol::Administrador)->postJson('/GestPro/activarSector', ['id' => $sector->id])->assertOk();

        $this->assertFalse($sector->fresh()->activo);
        $this->assertSame($sector->id, (int) $proyecto->fresh()->sector);
    }

    public function test_solo_el_administrador_modifica_los_sectores(): void
    {
        $sector = Sector::create(['nombre' => 'Vías', 'activo' => true]);

        foreach ([Rol::Gestor, Rol::Consulta] as $rol) {
            $this->como($rol)->postJson('/GestPro/guardarSector', ['accion' => 'Agregar', 'nombre' => 'Nuevo'])->assertForbidden();
            $this->como($rol)->postJson('/GestPro/activarSector', ['id' => $sector->id])->assertForbidden();
        }
    }

    public function test_los_sectores_no_se_eliminan(): void
    {
        $sector = Sector::create(['nombre' => 'Vías', 'activo' => true]);

        // La ruta no existe. routes/web.php tiene un GET comodín, así que puede responder 405.
        $respuesta = $this->como(Rol::Administrador)->postJson('/GestPro/eliminarSector', ['id' => $sector->id]);

        $this->assertContains($respuesta->status(), [404, 405]);
        $this->assertNotNull($sector->fresh());
    }
}
```

- [ ] **Paso 2: Ejecutar la prueba y comprobar que falla**

Run: `php artisan test tests/Feature/SectoresApiTest.php`
Expected: FALLA con error 500 porque el método `guardarSector` no existe.

- [ ] **Paso 3: Crear el controlador**

`app/Http/Controllers/SectorController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Administración del catálogo de sectores. No se eliminan: se desactivan, y uno
 * desactivado se conserva en los proyectos que ya lo tienen.
 */
class SectorController extends Controller
{
    public function guardar(Request $request): JsonResponse
    {
        $sector = $request->input('accion') === 'Editar'
            ? Sector::findOrFail($request->input('id'))
            : new Sector(['activo' => true]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:199', Rule::unique('sectores', 'nombre')->ignore($sector->id)],
        ]);

        $sector->fill($datos)->save();

        return response()->json(['success' => 'Sector guardado correctamente']);
    }

    public function activar(Request $request): JsonResponse
    {
        $sector = Sector::findOrFail($request->input('id'));
        $sector->update(['activo' => ! $sector->activo]);

        return response()->json(['success' => 'Sector actualizado correctamente']);
    }
}
```

- [ ] **Paso 4: Actualizar las rutas**

En `routes/api.php`, agregar con los demás imports:

```php
use App\Http\Controllers\SectorController;
```

Reemplazar:

```php
        Route::post('/guardarSector', [ProyectoController::class, 'guardarSector']);
        Route::post('/eliminarSector', [ProyectoController::class, 'eliminarSector']);
        Route::post('/activarSector', [ProyectoController::class, 'activarSector']);
```

por:

```php
        Route::post('/guardarSector', [SectorController::class, 'guardar']);
        Route::post('/activarSector', [SectorController::class, 'activar']);
```

- [ ] **Paso 5: Ejecutar la prueba y comprobar que pasa**

Run: `php artisan test tests/Feature/SectoresApiTest.php`
Expected: PASA, 5 pruebas.

- [ ] **Paso 6: Comitear**

```bash
git add app/Http/Controllers/SectorController.php routes/api.php tests/Feature/SectoresApiTest.php
git commit -m "fix: implement the sector routes that pointed to missing methods" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarea 6: Reglas para asignar fuentes a un proyecto

**Archivos:**
- Crear: `app/Services/Financiacion/FuentesProyecto.php`
- Modificar: `app/Http/Controllers/ProyectoController.php`
- Modificar: `routes/console.php`
- Modificar: `database/seeders/DemoDataSeeder.php`
- Modificar: `tests/Feature/AutorizacionTest.php:90`
- Prueba: `tests/Feature/ProyectoFuentesTest.php`

- [ ] **Paso 1: Escribir la prueba que falla**

```php
<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\FuenteFinanciacion;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProyectoFuentesTest extends TestCase
{
    use RefreshDatabase;

    private int $sgr;
    private int $propios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sgr = FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id');
        $this->propios = FuenteFinanciacion::where('clave', FuenteFinanciacion::RECURSOS_PROPIOS)->value('id');
    }

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function guardar(array $cambios)
    {
        return $this->como(Rol::Gestor)->postJson('/GestPro/guardarProyecto', [
            'accion' => 'Agregar',
            'municipio' => null,
            'nombre' => 'Acueducto',
            'descripcion' => 'Prueba',
            'fechaInicio' => '2026-01-01',
            'estado' => null,
            'fase' => null,
            'presupuesto' => null,
            'entidadPresenta' => null,
            'entidadFinancia' => null,
            ...$cambios,
        ]);
    }

    public function test_guarda_varias_fuentes_y_une_sus_nombres_en_el_orden_del_catalogo(): void
    {
        $this->guardar(['fuenteIds' => [$this->propios, $this->sgr]])->assertOk();

        $proyecto = Proyecto::where('nombre', 'Acueducto')->firstOrFail();
        $this->assertEqualsCanonicalizing([$this->sgr, $this->propios], $proyecto->fuentes()->pluck('fuentes_financiacion.id')->all());
        $this->assertSame('Sistema General de Regalías (SGR), Recursos propios', $proyecto->fuente_financiacion);
        $this->assertFalse($proyecto->financiacion_por_definir);
    }

    public function test_puede_quedar_por_definir(): void
    {
        $this->guardar(['fuenteIds' => [], 'financiacionPorDefinir' => true])->assertOk();

        $proyecto = Proyecto::where('nombre', 'Acueducto')->firstOrFail();
        $this->assertSame(0, $proyecto->fuentes()->count());
        $this->assertTrue($proyecto->financiacion_por_definir);
        $this->assertSame('Por definir', $proyecto->fuente_financiacion);
    }

    public function test_ningun_proyecto_queda_sin_fuente_ni_por_definir(): void
    {
        $this->guardar(['fuenteIds' => []])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'Seleccione al menos una fuente de financiación o marque que está por definir.');
        $this->guardar([])->assertStatus(422);

        $this->assertDatabaseCount('proyectos', 0);
    }

    public function test_no_admite_fuentes_y_por_definir_a_la_vez(): void
    {
        $this->guardar(['fuenteIds' => [$this->sgr], 'financiacionPorDefinir' => true])->assertStatus(422);
    }

    public function test_rechaza_fuentes_que_no_existen_o_estan_inactivas(): void
    {
        $this->guardar(['fuenteIds' => [999999]])->assertStatus(422);

        FuenteFinanciacion::whereKey($this->sgr)->update(['activo' => false]);
        $this->guardar(['fuenteIds' => [$this->sgr]])->assertStatus(422);
    }

    public function test_al_editar_conserva_una_fuente_que_ya_tenia_aunque_este_inactiva(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Puente']);
        $proyecto->fuentes()->sync([$this->sgr]);
        FuenteFinanciacion::whereKey($this->sgr)->update(['activo' => false]);

        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id, 'fuenteIds' => [$this->sgr, $this->propios]])->assertOk();

        $this->assertEqualsCanonicalizing([$this->sgr, $this->propios], $proyecto->fuentes()->pluck('fuentes_financiacion.id')->all());
    }

    public function test_al_editar_reemplaza_las_fuentes(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Puente', 'financiacion_por_definir' => true, 'fuente_financiacion' => 'Por definir']);

        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id, 'fuenteIds' => [$this->propios]])->assertOk();

        $proyecto->refresh();
        $this->assertFalse($proyecto->financiacion_por_definir);
        $this->assertSame('Recursos propios', $proyecto->fuente_financiacion);
        $this->assertSame([$this->propios], $proyecto->fuentes()->pluck('fuentes_financiacion.id')->all());
    }

    public function test_al_editar_sin_enviar_fuentes_las_conserva(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Puente', 'fuente_financiacion' => 'Recursos propios']);
        $proyecto->fuentes()->sync([$this->propios]);

        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id])->assertOk();

        $proyecto->refresh();
        $this->assertSame('Recursos propios', $proyecto->fuente_financiacion);
        $this->assertSame([$this->propios], $proyecto->fuentes()->pluck('fuentes_financiacion.id')->all());
    }

    public function test_el_listado_de_proyectos_incluye_las_fuentes(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Puente', 'financiacion_por_definir' => false]);
        $proyecto->fuentes()->sync([$this->propios]);
        Proyecto::create(['nombre' => 'Sin fuentes', 'financiacion_por_definir' => true]);

        $respuesta = $this->como(Rol::Consulta)->getJson('/GestPro/proyectos')->assertOk()->json();

        $porNombre = collect($respuesta)->keyBy('nombre');
        $this->assertSame([$this->propios], $porNombre['Puente']['fuente_ids']);
        $this->assertSame([], $porNombre['Sin fuentes']['fuente_ids']);
        $this->assertEquals(1, $porNombre['Sin fuentes']['financiacion_por_definir']);
    }

    public function test_el_informe_lista_los_proyectos_por_definir_con_su_texto_original(): void
    {
        Proyecto::create([
            'nombre' => 'Sin reconocer',
            'financiacion_por_definir' => true,
            'fuente_financiacion_original' => 'Convenio con la gobernación',
        ]);

        // Con Artisan::output(): expectsOutputToContain no admite dos textos en la misma línea.
        $this->assertSame(0, Artisan::call('proyectos:informe-fuentes'));
        $salida = Artisan::output();

        $this->assertStringContainsString('Sin reconocer', $salida);
        $this->assertStringContainsString('Convenio con la gobernación', $salida);
        $this->assertStringContainsString('Proyectos por definir: 1', $salida);
    }

    public function test_el_informe_muestra_cada_texto_original_con_el_resultado_de_la_conversion(): void
    {
        $proyecto = Proyecto::create([
            'nombre' => 'Convertido',
            'fuente_financiacion' => 'Recursos propios',
            'fuente_financiacion_original' => 'Sin regalías, recursos propios',
        ]);
        $proyecto->fuentes()->sync([$this->propios]);

        $this->assertSame(0, Artisan::call('proyectos:informe-fuentes'));
        $salida = Artisan::output();

        $this->assertStringContainsString('Sin regalías, recursos propios', $salida);
        $this->assertStringContainsString('Recursos propios', $salida);
    }
}
```

- [ ] **Paso 2: Ejecutar la prueba y comprobar que falla**

Run: `php artisan test tests/Feature/ProyectoFuentesTest.php`
Expected: FALLA. Las pruebas de guardar responden 500 por `Undefined array key "fuenteFinanciamiento"`.

- [ ] **Paso 3: Crear el servicio**

`app/Services/Financiacion/FuentesProyecto.php`:

```php
<?php

namespace App\Services\Financiacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\FuenteFinanciacion;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de la financiación de un proyecto: una o varias fuentes del catálogo, o
 * "por definir". `proyectos.fuente_financiacion` guarda los nombres unidos para las
 * vistas que lo muestran; el dato real está en `proyecto_fuentes`.
 */
final class FuentesProyecto
{
    public const TEXTO_POR_DEFINIR = 'Por definir';

    /**
     * @param array{fuenteIds?: array, financiacionPorDefinir?: mixed} $datos
     * @return array{ids: list<int>, por_definir: bool, texto: string}
     */
    public function validar(array $datos, ?int $proyectoId = null): array
    {
        $porDefinir = filter_var($datos['financiacionPorDefinir'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $ids = array_values(array_unique(array_map('intval', $datos['fuenteIds'] ?? [])));

        if ($porDefinir && $ids !== []) {
            throw new ReglaNegocioException('Un proyecto con fuentes de financiación no puede estar también por definir.');
        }
        if (! $porDefinir && $ids === []) {
            throw new ReglaNegocioException('Seleccione al menos una fuente de financiación o marque que está por definir.');
        }
        if ($porDefinir) {
            return ['ids' => [], 'por_definir' => true, 'texto' => self::TEXTO_POR_DEFINIR];
        }

        $fuentes = FuenteFinanciacion::query()->whereIn('id', $ids)->orderBy('orden')->orderBy('nombre')->get();
        $this->exigirValidas($ids, $fuentes, $proyectoId);

        return ['ids' => $ids, 'por_definir' => false, 'texto' => $fuentes->pluck('nombre')->implode(', ')];
    }

    /** @param array{ids: list<int>, por_definir: bool, texto: string} $financiacion */
    public function guardar(int $proyectoId, array $financiacion): void
    {
        DB::table('proyecto_fuentes')->where('proyecto_id', $proyectoId)->delete();
        foreach ($financiacion['ids'] as $id) {
            DB::table('proyecto_fuentes')->insert(['proyecto_id' => $proyectoId, 'fuente_id' => $id]);
        }

        DB::table('proyectos')->where('id', $proyectoId)->update([
            'financiacion_por_definir' => $financiacion['por_definir'],
            'fuente_financiacion' => $financiacion['texto'],
        ]);
    }

    /** Una fuente inactiva solo se admite si el proyecto ya la tenía. */
    private function exigirValidas(array $ids, $fuentes, ?int $proyectoId): void
    {
        $previas = $proyectoId
            ? DB::table('proyecto_fuentes')->where('proyecto_id', $proyectoId)->pluck('fuente_id')->map(fn ($id) => (int) $id)->all()
            : [];
        $porId = $fuentes->keyBy('id');
        $errores = [];

        foreach ($ids as $id) {
            $fuente = $porId->get($id);
            if (! $fuente) {
                $errores[] = "La fuente de financiación con id {$id} no existe.";
            } elseif (! $fuente->activo && ! in_array($id, $previas, true)) {
                $errores[] = "La fuente de financiación \"{$fuente->nombre}\" está inactiva.";
            }
        }

        if ($errores) {
            throw new ReglaNegocioException('Las fuentes de financiación no son válidas.', $errores);
        }
    }
}
```

- [ ] **Paso 4: Inyectar el servicio y validar antes de la transacción**

En `app/Http/Controllers/ProyectoController.php`, agregar el import después de `use App\Models\Proyecto;`:

```php
use App\Services\Financiacion\FuentesProyecto;
```

Reemplazar:

```php
    public function guardarProyecto(Request $request, ExpedienteService $expedientes)
    {
        $proyecto = $request->all();
        $proyectoId = $proyecto['id'] ?? null;
        $faseAnterior = null;
        DB::beginTransaction();
```

por:

```php
    public function guardarProyecto(Request $request, ExpedienteService $expedientes, FuentesProyecto $fuentes)
    {
        $proyecto = $request->all();
        $proyectoId = $proyecto['id'] ?? null;
        $faseAnterior = null;

        // Antes de la transacción: su catch convertiría una regla incumplida en un 500.
        // Al editar sin enviar fuentes se conservan las que tiene.
        $esNuevo = $proyecto['accion'] == 'Agregar';
        $financiacion = $esNuevo || array_key_exists('fuenteIds', $proyecto) || array_key_exists('financiacionPorDefinir', $proyecto)
            ? $fuentes->validar($proyecto, $esNuevo ? null : (int) $proyectoId)
            : null;

        DB::beginTransaction();
```

- [ ] **Paso 5: Dejar de leer el texto libre al insertar**

En el mismo método, reemplazar:

```php
                    'entidad_financia' => $proyecto['entidadFinancia'],
                    'fuente_financiacion' => $proyecto['fuenteFinanciamiento'],
                    'sector' => ($proyecto['sector'] ?? null) ?: null
                ]);

```

por:

```php
                    'entidad_financia' => $proyecto['entidadFinancia'],
                    'sector' => ($proyecto['sector'] ?? null) ?: null
                ]);

                $fuentes->guardar($proyectoId, $financiacion);

```

- [ ] **Paso 6: Dejar de leer el texto libre al editar**

En el mismo método, reemplazar:

```php
                    'entidad_financia' => $proyecto['entidadFinancia'],
                    'fuente_financiacion' => $proyecto['fuenteFinanciamiento']
                ]);

                // Solo si viene en la petición: quien no lo envía no debe borrarlo.
```

por:

```php
                    'entidad_financia' => $proyecto['entidadFinancia']
                ]);

                if ($financiacion !== null) {
                    $fuentes->guardar((int) $proyecto['id'], $financiacion);
                }

                // Solo si viene en la petición: quien no lo envía no debe borrarlo.
```

- [ ] **Paso 7: Incluir las fuentes en el listado de proyectos**

En el método `proyectos()`, reemplazar:

```php
                'proyectos.fuente_financiacion',
                'proyectos.sector',
```

por:

```php
                'proyectos.fuente_financiacion',
                'proyectos.financiacion_por_definir',
                'proyectos.sector',
```

Y reemplazar:

```php
        $totalPresupuesto = 0;
        foreach ($proyectos as $proyecto) {
```

por:

```php
        $fuentesPorProyecto = DB::table('proyecto_fuentes')->get()->groupBy('proyecto_id');

        $totalPresupuesto = 0;
        foreach ($proyectos as $proyecto) {
            $proyecto->fuente_ids = $fuentesPorProyecto->get($proyecto->id, collect())
                ->map(fn ($fila) => (int) $fila->fuente_id)
                ->values();

```

- [ ] **Paso 8: Crear el comando de informe**

En `routes/console.php`, agregar el import después de `use Illuminate\Support\Facades\Artisan;`:

```php
use Illuminate\Support\Facades\DB;
```

Y agregar al final del archivo:

```php

Artisan::command('proyectos:informe-fuentes', function () {
    $porFuente = DB::table('fuentes_financiacion as f')
        ->leftJoin('proyecto_fuentes as pf', 'pf.fuente_id', 'f.id')
        ->select('f.nombre', DB::raw('COUNT(pf.proyecto_id) as proyectos'))
        ->groupBy('f.id', 'f.nombre', 'f.orden')
        ->orderBy('f.orden')
        ->get();
    $this->table(['Fuente', 'Proyectos'], $porFuente->map(fn ($f) => [$f->nombre, $f->proyectos])->all());

    // Cada texto distinto una vez: las coincidencias por palabra no detectan una negación
    // ("Sin regalías") ni una fuente omitida, y eso solo se ve revisando el resultado.
    $conversiones = Proyecto::query()->whereNotNull('fuente_financiacion_original')->with('fuentes:id,nombre')->get()
        ->groupBy('fuente_financiacion_original')
        ->map(fn ($grupo, $texto) => [
            $texto,
            $grupo->first()->fuentes->pluck('nombre')->implode(', ') ?: 'Por definir',
            $grupo->count(),
        ]);
    $this->table(['Texto original', 'Resultado', 'Proyectos'], $conversiones->values()->all());

    $pendientes = Proyecto::query()->where('financiacion_por_definir', true)->orderBy('id')->get();
    $this->table(
        ['Id', 'Proyecto', 'Texto original'],
        $pendientes->map(fn (Proyecto $p) => [$p->id, $p->nombre, $p->fuente_financiacion_original ?? '(vacío)'])->all()
    );
    $this->info("Proyectos por definir: {$pendientes->count()}");
})->purpose('Muestra los proyectos por fuente y los que quedaron por definir, con su texto original');
```

- [ ] **Paso 9: Ajustar la prueba de autorización**

En `tests/Feature/AutorizacionTest.php`, reemplazar:

```php
                'fuenteFinanciamiento' => null,
```

por:

```php
                'fuenteIds' => [],
                'financiacionPorDefinir' => true,
```

- [ ] **Paso 10: Asignar fuentes a los proyectos de demostración**

En `database/seeders/DemoDataSeeder.php`, agregar el import después de `use App\Models\Fase;`:

```php
use App\Models\FuenteFinanciacion;
```

Reemplazar:

```php
                        'fuente_financiacion' => 'Recursos propios y regalías',
```

por:

```php
                        'fuente_financiacion' => 'Sistema General de Regalías (SGR), Recursos propios',
```

Y reemplazar:

```php
                $proyectos[] = ['proyecto' => $proyecto, 'fase' => $faseNombre];
```

por:

```php
                $proyecto->fuentes()->sync(
                    FuenteFinanciacion::whereIn('clave', [FuenteFinanciacion::SGR, FuenteFinanciacion::RECURSOS_PROPIOS])->pluck('id')
                );

                $proyectos[] = ['proyecto' => $proyecto, 'fase' => $faseNombre];
```

- [ ] **Paso 11: Ejecutar las pruebas y comprobar que pasan**

Run: `php artisan test tests/Feature/ProyectoFuentesTest.php tests/Feature/AutorizacionTest.php`
Expected: PASAN.

- [ ] **Paso 12: Comitear**

```bash
git add app/Services/Financiacion/FuentesProyecto.php app/Http/Controllers/ProyectoController.php routes/console.php database/seeders/DemoDataSeeder.php tests/Feature/ProyectoFuentesTest.php tests/Feature/AutorizacionTest.php
git commit -m "feat: save project funding sources from the catalog" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

En este punto `ExpedienteAutomaticoTest` falla porque sigue enviando el formato antiguo. La siguiente tarea lo corrige.

---

### Tarea 7: Detectar SGR por el catálogo en el expediente automático

**Archivos:**
- Modificar: `tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`
- Modificar: `app/Services/Viabilizacion/ExpedienteService.php:97-103`, `:527-532`
- Modificar: `routes/console.php`

- [ ] **Paso 1: Actualizar los datos de apoyo de la prueba**

En `tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`, agregar el import después de `use App\Enums\Rol;`:

```php
use App\Models\FuenteFinanciacion;
```

Reemplazar:

```php
    private const FUENTE_SGR = 'Recursos propios y regalías';

    private int $formulacion;
```

por:

```php
    private int $fuenteSgr;
    private int $fuentePropios;
    private int $formulacion;
```

Reemplazar:

```php
        Storage::fake('local');
    }

    private function como(Rol $rol): static
```

por:

```php
        Storage::fake('local');
        $this->fuenteSgr = FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id');
        $this->fuentePropios = FuenteFinanciacion::where('clave', FuenteFinanciacion::RECURSOS_PROPIOS)->value('id');
    }

    private function como(Rol $rol): static
```

Reemplazar:

```php
            'fuenteFinanciamiento' => self::FUENTE_SGR,
            ...$cambios,
```

por:

```php
            'fuenteIds' => [$this->fuenteSgr],
            ...$cambios,
```

Reemplazar:

```php
    private function proyecto(array $cambios = []): Proyecto
    {
        return Proyecto::create([
            'municipio' => $this->municipio,
            'nombre' => 'Proyecto',
            'fase' => $this->formulacion,
            'fuente_financiacion' => self::FUENTE_SGR,
            ...$cambios,
        ]);
    }
```

por:

```php
    private function proyecto(array $cambios = [], ?array $fuentes = null): Proyecto
    {
        $proyecto = Proyecto::create([
            'municipio' => $this->municipio,
            'nombre' => 'Proyecto',
            'fase' => $this->formulacion,
            ...$cambios,
        ]);
        $proyecto->fuentes()->sync($fuentes ?? [$this->fuenteSgr]);

        return $proyecto;
    }
```

- [ ] **Paso 2: Reescribir las pruebas de detección**

Reemplazar la prueba `test_reconoce_la_fuente_sin_importar_tildes_ni_mayusculas` completa por esta, que comprueba lo que ahora importa:

```php
    public function test_reconoce_la_fuente_sgr_junto_a_otras(): void
    {
        $this->guardar(['nombre' => 'Mixto', 'fuenteIds' => [$this->fuentePropios, $this->fuenteSgr]])->assertOk();

        $this->assertSame(1, Proyecto::where('nombre', 'Mixto')->firstOrFail()->expedientes()->count());
    }

    public function test_detecta_regalias_por_el_catalogo_y_no_por_el_texto(): void
    {
        $proyecto = $this->proyecto(['nombre' => 'Texto engañoso', 'fuente_financiacion' => 'Sin regalías'], [$this->fuentePropios]);

        $this->assertFalse(app(ExpedienteService::class)->correspondeAutomatico($proyecto));
    }
```

Reemplazar el cuerpo de `test_no_se_crea_si_no_corresponde`:

```php
        $this->guardar(['nombre' => 'En licitacion', 'fase' => $this->licitacion])->assertOk();
        $this->guardar(['nombre' => 'Recursos propios', 'fuenteFinanciamiento' => 'Recursos propios'])->assertOk();
        $this->guardar(['nombre' => 'Sin fuente', 'fuenteFinanciamiento' => null])->assertOk();
        $this->guardar(['nombre' => 'Sin fase', 'fase' => null])->assertOk();
```

por:

```php
        $this->guardar(['nombre' => 'En licitacion', 'fase' => $this->licitacion])->assertOk();
        $this->guardar(['nombre' => 'Recursos propios', 'fuenteIds' => [$this->fuentePropios]])->assertOk();
        $this->guardar(['nombre' => 'Por definir', 'fuenteIds' => [], 'financiacionPorDefinir' => true])->assertOk();
        $this->guardar(['nombre' => 'Sin fase', 'fase' => null])->assertOk();
```

En `test_el_comando_crea_los_que_faltan_y_se_puede_repetir`, reemplazar:

```php
        $this->proyecto(['nombre' => 'Sin regalías', 'fuente_financiacion' => 'Recursos propios']);
```

por:

```php
        $this->proyecto(['nombre' => 'Sin regalías'], [$this->fuentePropios]);
```

- [ ] **Paso 3: Ejecutar la prueba y comprobar que la detección falla**

Run: `php artisan test tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`
Expected: FALLAN al menos `test_detecta_regalias_por_el_catalogo_y_no_por_el_texto`, porque el texto contiene "regalías", y `test_el_comando_crea_los_que_faltan_y_se_puede_repetir`, porque el proyecto de prueba ya no tiene texto. Las que guardan por formulario pueden pasar, porque el nombre de la fuente contiene "Regalías".

- [ ] **Paso 4: Cambiar la detección en el servicio**

En `app/Services/Viabilizacion/ExpedienteService.php`, agregar el import después de `use App\Models\Fase;`:

```php
use App\Models\FuenteFinanciacion;
```

Reemplazar:

```php
    /** Proyecto en Formulación, financiado con regalías y todavía sin expediente. */
    public function correspondeAutomatico(Proyecto $proyecto): bool
    {
        return $this->esFormulacion($proyecto->fase)
            && $this->financiadoConRegalias($proyecto)
            && ! $proyecto->expedientes()->exists();
    }
```

por:

```php
    /** Proyecto en Formulación, con la fuente SGR y todavía sin expediente. */
    public function correspondeAutomatico(Proyecto $proyecto): bool
    {
        return $this->esFormulacion($proyecto->fase)
            && $proyecto->fuentes()->where('clave', FuenteFinanciacion::SGR)->exists()
            && ! $proyecto->expedientes()->exists();
    }
```

Y eliminar este bloque:

```php
    // La fuente es texto libre: se reconoce por palabra clave. Si no se detecta, el
    // expediente se puede crear a mano desde la página del proyecto.
    private function financiadoConRegalias(Proyecto $proyecto): bool
    {
        return Str::contains($this->normalizar($proyecto->fuente_financiacion), ['regalia', 'sgr']);
    }

```

- [ ] **Paso 5: Actualizar la descripción del comando**

En `routes/console.php`, reemplazar:

```php
})->purpose('Crea el expediente SGR, sin configurar, a los proyectos en Formulación con regalías que no tienen');
```

por:

```php
})->purpose('Crea el expediente SGR, sin configurar, a los proyectos en Formulación con la fuente SGR que no tienen');
```

- [ ] **Paso 6: Ejecutar toda la suite PHP**

Run: `php artisan test`
Expected: pasan todas. El total es la línea base de la tarea 1 más las pruebas nuevas.

- [ ] **Paso 7: Comitear**

```bash
git add tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php app/Services/Viabilizacion/ExpedienteService.php routes/console.php
git commit -m "refactor: detect SGR projects from the catalog instead of free text" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarea 8: Lógica del selector de fuentes y navegación

**Archivos:**
- Crear: `resources/js/utils/fuentesProyecto.js`
- Prueba: `resources/js/utils/fuentesProyecto.test.js`
- Modificar: `resources/js/utils/navegacion.js`
- Modificar: `resources/js/utils/iconosNavegacion.js`
- Modificar: `resources/js/utils/navegacion.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

`resources/js/utils/fuentesProyecto.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { alternarFuente, datosFinanciacionDe, financiacionCompleta, fuentesDisponibles } from './fuentesProyecto';

const fuentes = [
    { id: 1, nombre: 'SGR', activo: true },
    { id: 2, nombre: 'PGN', activo: false },
    { id: 3, nombre: 'Propios', activo: true },
];

describe('fuentesDisponibles', () => {
    it('ofrece las activas', () => {
        expect(fuentesDisponibles(fuentes, []).map((f) => f.id)).toEqual([1, 3]);
    });

    it('conserva una inactiva si el proyecto ya la tiene', () => {
        expect(fuentesDisponibles(fuentes, [2]).map((f) => f.id)).toEqual([1, 2, 3]);
    });
});

describe('alternarFuente', () => {
    it('agrega una fuente y quita el por definir', () => {
        expect(alternarFuente({ fuenteIds: [1], financiacionPorDefinir: true }, 3))
            .toEqual({ fuenteIds: [1, 3], financiacionPorDefinir: false });
    });

    it('quita una fuente ya marcada', () => {
        expect(alternarFuente({ fuenteIds: [1, 3], financiacionPorDefinir: false }, 1))
            .toEqual({ fuenteIds: [3], financiacionPorDefinir: false });
    });
});

describe('financiacionCompleta', () => {
    it('exige una fuente o el por definir', () => {
        expect(financiacionCompleta({ fuenteIds: [], financiacionPorDefinir: false })).toBe(false);
        expect(financiacionCompleta({ fuenteIds: [1], financiacionPorDefinir: false })).toBe(true);
        expect(financiacionCompleta({ fuenteIds: [], financiacionPorDefinir: true })).toBe(true);
    });
});

describe('datosFinanciacionDe', () => {
    it('traduce el proyecto del listado al formato del formulario', () => {
        expect(datosFinanciacionDe({ fuente_ids: [1, 3], financiacion_por_definir: 0 }))
            .toEqual({ fuenteIds: [1, 3], financiacionPorDefinir: false });
        expect(datosFinanciacionDe({ fuente_ids: [], financiacion_por_definir: 1 }))
            .toEqual({ fuenteIds: [], financiacionPorDefinir: true });
    });

    it('tolera un elemento sin esos campos', () => {
        expect(datosFinanciacionDe({})).toEqual({ fuenteIds: [], financiacionPorDefinir: false });
    });
});
```

En `resources/js/utils/navegacion.test.js`, reemplazar:

```js
        ['municipios', 'estados', 'fases', 'tipos-evento', 'prioridades', 'responsables', 'entidades'].forEach((slug) => {
```

por:

```js
        ['municipios', 'estados', 'fases', 'tipos-evento', 'prioridades', 'responsables', 'entidades', 'fuentes-financiacion', 'sectores'].forEach((slug) => {
```

- [ ] **Paso 2: Ejecutar las pruebas y comprobar que fallan**

Run: `npm test`
Expected: FALLA `fuentesProyecto.test.js` porque el módulo no existe, y `navegacion.test.js` porque las rutas no existen.

- [ ] **Paso 3: Crear el módulo**

`resources/js/utils/fuentesProyecto.js`:

```js
// Lógica del selector de fuentes del formulario de proyecto. Las reglas definitivas
// están en el backend (FuentesProyecto); aquí solo se evita enviar lo que ya se sabe inválido.

const mismoId = (a, b) => String(a) === String(b);

/** Activas, más las inactivas que el proyecto ya tiene, para no obligar a quitarlas. */
export const fuentesDisponibles = (fuentes, seleccionadas) =>
    fuentes.filter((f) => f.activo || seleccionadas.some((id) => mismoId(id, f.id)));

/** Marcar o desmarcar una fuente siempre quita el "por definir". */
export const alternarFuente = ({ fuenteIds }, id) => ({
    fuenteIds: fuenteIds.some((actual) => mismoId(actual, id))
        ? fuenteIds.filter((actual) => !mismoId(actual, id))
        : [...fuenteIds, id],
    financiacionPorDefinir: false,
});

export const financiacionCompleta = ({ fuenteIds, financiacionPorDefinir }) =>
    Boolean(financiacionPorDefinir) || fuenteIds.length > 0;

export const datosFinanciacionDe = (proyecto) => ({
    fuenteIds: proyecto.fuente_ids ?? [],
    financiacionPorDefinir: Boolean(proyecto.financiacion_por_definir),
});
```

- [ ] **Paso 4: Agregar las secciones a la navegación**

En `resources/js/utils/navegacion.js`, reemplazar:

```js
    { slug: 'entidades', tab: 'entidades', titulo: 'Entidades', descripcion: 'Quienes presentan y financian los proyectos.', grupo: GRUPO.CATALOGOS, icono: 'faLandmark', palabras: 'alcaldia gobernacion ministerio catalogo' },
];
```

por:

```js
    { slug: 'entidades', tab: 'entidades', titulo: 'Entidades', descripcion: 'Quienes presentan y financian los proyectos.', grupo: GRUPO.CATALOGOS, icono: 'faLandmark', palabras: 'alcaldia gobernacion ministerio catalogo' },
    { slug: 'fuentes-financiacion', tab: 'fuentes-financiacion', titulo: 'Fuentes de financiación', descripcion: 'De dónde provienen los recursos de los proyectos.', grupo: GRUPO.CATALOGOS, icono: 'faCoins', palabras: 'regalias sgr recursos propios cofinanciacion catalogo' },
    { slug: 'sectores', tab: 'sectores', titulo: 'Sectores', descripcion: 'Sectores de inversión de los proyectos.', grupo: GRUPO.CATALOGOS, icono: 'faLayerGroup', palabras: 'educacion salud transporte vivienda catalogo' },
];
```

En `resources/js/utils/iconosNavegacion.js`, reemplazar el contenido completo por:

```js
import {
    faCalendarDays, faChartColumn, faCircleHalfStroke, faClipboardCheck, faCoins, faDiagramProject, faFileSignature,
    faFlag, faFolderOpen, faGaugeHigh, faLandmark, faLayerGroup, faMapLocationDot, faTags, faUsers,
} from '@fortawesome/free-solid-svg-icons';

// navegacion.js guarda el nombre del icono para seguir siendo lógica pura y probable.
export const ICONOS = {
    faCalendarDays, faChartColumn, faCircleHalfStroke, faClipboardCheck, faCoins, faDiagramProject, faFileSignature,
    faFlag, faFolderOpen, faGaugeHigh, faLandmark, faLayerGroup, faMapLocationDot, faTags, faUsers,
};
```

- [ ] **Paso 5: Ejecutar las pruebas y comprobar que pasan**

Run: `npm test`
Expected: pasan todas.

- [ ] **Paso 6: Comitear**

```bash
git add resources/js/utils/fuentesProyecto.js resources/js/utils/fuentesProyecto.test.js resources/js/utils/navegacion.js resources/js/utils/iconosNavegacion.js resources/js/utils/navegacion.test.js
git commit -m "feat: add funding source selector logic and catalog destinations" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarea 9: Pantallas de catálogo en Parámetros

`Parametros.jsx` no tiene pruebas de componente; esta tarea se verifica en el navegador en la tarea 11.

**Archivos:**
- Modificar: `resources/js/components/Parametros.jsx`

- [ ] **Paso 1: Agregar el import y el estado**

Reemplazar:

```js
import { seccionDesdeSlug } from '../utils/navegacion';
```

por:

```js
import { seccionDesdeSlug } from '../utils/navegacion';
import { alternarFuente, datosFinanciacionDe, financiacionCompleta, fuentesDisponibles } from '../utils/fuentesProyecto';
```

Reemplazar:

```js
    const [sectores, setSectores] = useState([]);
```

por:

```js
    const [sectores, setSectores] = useState([]);
    const [fuentes, setFuentes] = useState([]);
```

- [ ] **Paso 2: Registrar los catálogos en los datos locales**

`toggleActive` actualiza `parametros[type]` y falla si la clave no existe. Reemplazar:

```js
            { id: 4, nombre: 'Empresa 4', activo: false }
        ]
    });
```

por:

```js
            { id: 4, nombre: 'Empresa 4', activo: false }
        ],
        fuentes: [],
        sectores: []
    });
```

- [ ] **Paso 3: Cargar las fuentes**

Reemplazar:

```js
        listSectores();
        ///
```

por:

```js
        listSectores();
        listFuentes();
        ///
```

Reemplazar:

```js
    const listEntidades = async () => {
```

por:

```js
    const listFuentes = async () => {
        const response = await axios.get('/fuentesFinanciacion');
        setFuentes(response.data);
    }

    const listEntidades = async () => {
```

- [ ] **Paso 4: Cambiar los datos del formulario**

Con reemplazo global de `fuenteFinanciamiento: '',` (3 apariciones), usar:

```js
fuenteIds: [],
            financiacionPorDefinir: false,
```

Y reemplazar en `handleEdit`:

```js
            fuenteFinanciamiento: item.fuente_financiacion || '',
```

por:

```js
            ...datosFinanciacionDe(item),
```

- [ ] **Paso 5: Validar la financiación antes de enviar**

Reemplazar:

```js
        if (modalType === 'proyectos') {
            // Calcular el presupuesto total de los componentes
```

por:

```js
        if (modalType === 'proyectos') {
            if (!financiacionCompleta(formData)) {
                setModalActiveTab('fuentes');
                Swal.fire({
                    title: 'Falta la fuente de financiación',
                    text: 'Seleccione al menos una fuente o marque que está por definir.',
                    icon: 'warning'
                });
                return;
            }

            // Calcular el presupuesto total de los componentes
```

- [ ] **Paso 6: Guardar fuentes y sectores**

Agregar esta función antes de `const handleSubmit = async (e) => {`:

```js
    // Fuentes y sectores: el backend rechaza nombres repetidos con un 422.
    const guardarCatalogo = async (ruta, listar, titulo) => {
        try {
            await axios.post(ruta, { ...formData, id: editingItem ? editingItem.id : undefined });
            listar();
            handleCloseModal();
            Swal.fire({ title: titulo, icon: 'success' });
        } catch (error) {
            Swal.fire({
                title: 'No se pudo guardar',
                text: error.response?.data?.message ?? 'Inténtelo de nuevo.',
                icon: 'error'
            });
        }
    };

```

Reemplazar:

```js
        } else if (type === 'entidades') {
            const newEntidad = {
```

por lo siguiente. Además de agregar los dos catálogos, corrige el `type` inexistente, que hoy impide guardar entidades:

```js
        } else if (modalType === 'fuentes') {
            await guardarCatalogo('/guardarFuenteFinanciacion', listFuentes, 'Fuente de financiación guardada correctamente');
        } else if (modalType === 'sectores') {
            await guardarCatalogo('/guardarSector', listSectores, 'Sector guardado correctamente');
        } else if (modalType === 'entidades') {
            const newEntidad = {
```

- [ ] **Paso 7: Activar y desactivar**

En `toggleActive`, reemplazar:

```js
        } else if (type === 'entidades') {
            const response = await axios.post('/activarEntidad', { id: id, activo: activo });
            if (response.status === 200) {
                listEntidades();
            }
        }
```

por:

```js
        } else if (type === 'entidades') {
            const response = await axios.post('/activarEntidad', { id: id, activo: activo });
            if (response.status === 200) {
                listEntidades();
            }
        } else if (type === 'fuentes') {
            await axios.post('/activarFuenteFinanciacion', { id: id });
            listFuentes();
        } else if (type === 'sectores') {
            await axios.post('/activarSector', { id: id });
            listSectores();
        }
```

- [ ] **Paso 8: Títulos del modal**

Reemplazar:

```js
            responsables: 'Responsable',
            proyectos: 'Proyecto',
```

por:

```js
            responsables: 'Responsable',
            entidades: 'Entidad',
            fuentes: 'Fuente de financiación',
            sectores: 'Sector',
            proyectos: 'Proyecto',
```

- [ ] **Paso 9: Mostrar las tablas**

Reemplazar:

```js
                        {activeTab === 'entidades' && renderTable(entidades, 'entidades')}
```

por:

```js
                        {activeTab === 'entidades' && renderTable(entidades, 'entidades')}
                        {activeTab === 'fuentes-financiacion' && renderTable(fuentes, 'fuentes')}
                        {activeTab === 'sectores' && renderTable(sectores, 'sectores')}
```

- [ ] **Paso 10: Quitar "Eliminar" de estos dos catálogos**

Reemplazar:

```js
                                            <button
                                                onClick={() => handleDelete(item.id, type)}
                                                className="btn-delete"
                                                title="Eliminar"
                                            >
                                                <FontAwesomeIcon icon={faTrash} /> Eliminar
                                            </button>
```

por:

```js
                                            {/* Fuentes y sectores se desactivan: los proyectos los referencian. */}
                                            {type !== 'fuentes' && type !== 'sectores' && (
                                                <button
                                                    onClick={() => handleDelete(item.id, type)}
                                                    className="btn-delete"
                                                    title="Eliminar"
                                                >
                                                    <FontAwesomeIcon icon={faTrash} /> Eliminar
                                                </button>
                                            )}
```

- [ ] **Paso 11: Campos del formulario de catálogo**

Reemplazar:

```js
                            {(modalType === 'municipios' || modalType === 'estados' || modalType === 'fases' || modalType === 'tiposEventos' || modalType === 'prioridades' || modalType === 'responsables' || modalType === 'entidades') && (
```

por:

```js
                            {(modalType === 'municipios' || modalType === 'estados' || modalType === 'fases' || modalType === 'tiposEventos' || modalType === 'prioridades' || modalType === 'responsables' || modalType === 'entidades' || modalType === 'fuentes' || modalType === 'sectores') && (
```

Y reemplazar:

```js
                            {/* Campos específicos para municipios */}
```

por:

```js
                            {modalType === 'fuentes' && (
                                <div className="form-group">
                                    <label htmlFor="descripcion">Descripción</label>
                                    <textarea
                                        id="descripcion"
                                        name="descripcion"
                                        value={formData.descripcion}
                                        onChange={handleInputChange}
                                        rows="3"
                                        placeholder="Opcional"
                                    />
                                </div>
                            )}

                            {/* Campos específicos para municipios */}
```

- [ ] **Paso 12: Selector de fuentes en el formulario de proyecto**

Reemplazar:

```js
                                            <div className="form-group">
                                                <label htmlFor="fuente">Fuente de Financiamiento *</label>
                                                <textarea
                                                    type="text"
                                                    id="fuenteFinanciamiento"
                                                    name="fuenteFinanciamiento"
                                                    value={formData.fuenteFinanciamiento}
                                                    onChange={handleInputChange}
                                                    rows="4"
                                                    placeholder="Ingrese la fuente de financiamiento"
                                                />
                                            </div>
```

por:

```js
                                            <fieldset className="form-group fuentes-seleccion">
                                                <legend>Fuentes de financiación *</legend>
                                                <div className="fuentes-seleccion-lista">
                                                    {fuentesDisponibles(fuentes, formData.fuenteIds).map(fuente => (
                                                        <label key={fuente.id} className="checkbox-label">
                                                            <input
                                                                type="checkbox"
                                                                checked={formData.fuenteIds.some(id => String(id) === String(fuente.id))}
                                                                onChange={() => setFormData(prev => ({ ...prev, ...alternarFuente(prev, fuente.id) }))}
                                                            />
                                                            <span>{fuente.nombre}</span>
                                                        </label>
                                                    ))}
                                                </div>
                                                <label className="checkbox-label fuentes-seleccion-pendiente">
                                                    <input
                                                        type="checkbox"
                                                        checked={formData.financiacionPorDefinir}
                                                        onChange={(e) => setFormData(prev => ({
                                                            ...prev,
                                                            financiacionPorDefinir: e.target.checked,
                                                            fuenteIds: e.target.checked ? [] : prev.fuenteIds
                                                        }))}
                                                    />
                                                    <span>Por definir (etapa de estructuración)</span>
                                                </label>
                                            </fieldset>
```

- [ ] **Paso 13: Comprobar que no quedan referencias al campo antiguo**

Run: `git grep -n "fuenteFinanciamiento" -- resources app tests`
Expected: sin resultados.

---

### Tarea 10: Estilo del selector y compilación

**Archivos:**
- Modificar: `resources/css/Parametros.css`
- Generados: `public/css/app.css`, `public/js/app.js` y demás archivos de Mix

- [ ] **Paso 1: Agregar el estilo**

En `resources/css/Parametros.css`, agregar después de la línea que empieza con `.componente-form, .fuentes-form,`:

```css
.fuentes-seleccion { padding: 0; border: 0; }
.fuentes-seleccion legend { margin-bottom: .5rem; padding: 0; font-size: .84rem; font-weight: 550; color: var(--gp-texto-2); }
.fuentes-seleccion-lista { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: .5rem 1rem; }
.fuentes-seleccion .checkbox-label { white-space: normal; font-weight: 400; }
.fuentes-seleccion-pendiente { margin-top: .75rem !important; padding-top: .75rem; border-top: 1px solid var(--gp-linea); color: var(--gp-texto-2); }
```

- [ ] **Paso 2: Compilar**

Run: `npm run production`
Expected: compilación exitosa, sin errores.

- [ ] **Paso 3: Ejecutar las pruebas JS**

Run: `npm test`
Expected: pasan todas.

- [ ] **Paso 4: Comitear**

```bash
git add resources/js/components/Parametros.jsx resources/css/Parametros.css public/css public/js public/mix-manifest.json
git commit -m "feat: add funding source and sector screens and the project selector" -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarea 11: Verificar con una copia de los datos reales

Base descartable, nunca `gest_pro`. Se ejecuta sobre una copia para comprobar la conversión de los 31 proyectos y el rollback en MariaDB, que SQLite no cubre. `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio; hay que definirlo en cada comando porque el estado de la terminal no se conserva.

**Archivos:** ninguno.

- [ ] **Paso 1: Confirmar que no hay configuración en caché**

Run: `php artisan config:clear`
Expected: `Configuration cache cleared successfully.` Sin esto, `DB_DATABASE` podría ignorarse y la migración caería sobre `gest_pro`.

- [ ] **Paso 2: Crear la copia**

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE IF EXISTS gestpro_verif_tmp; CREATE DATABASE gestpro_verif_tmp CHARACTER SET utf8mb4;"
```

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro | /c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp
```

- [ ] **Paso 3: Guardar el estado de los expedientes antes de migrar**

Crear `$TMP_DIR/gestpro_expedientes_estado.php`:

```php
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

if (DB::getDatabaseName() !== 'gestpro_verif_tmp') {
    exit("Abortado: la base es " . DB::getDatabaseName() . "\n");
}

$estado = DB::table('proyectos_expedientes as e')
    ->leftJoin('proyectos_requisitos as r', 'r.expediente_id', 'e.id')
    ->select('e.id', 'e.proyecto_id', 'e.estado', 'e.fecha_radicado', 'e.fecha_configuracion',
        DB::raw('SUM(r.aplica = 1) as aplican'), DB::raw('SUM(r.aplica = 1 AND r.cumplido = 1) as cumplidos'))
    ->groupBy('e.id', 'e.proyecto_id', 'e.estado', 'e.fecha_radicado', 'e.fecha_configuracion')
    ->orderBy('e.id')
    ->get();

file_put_contents($argv[1], $estado->toJson(JSON_PRETTY_PRINT));
echo "Expedientes guardados: {$estado->count()}\n";
```

Run, desde la raíz del repositorio:

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_expedientes_estado.php" "$TMP_DIR/expedientes_antes.json"
```

Expected: `Expedientes guardados: 10`, o el número actual de `gest_pro`.

- [ ] **Paso 4: Migrar la copia**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

Expected: se aplican las 3 migraciones nuevas, sin errores.

- [ ] **Paso 5: Revisar la conversión**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan proyectos:informe-fuentes
```

Expected: SGR y Recursos propios con 30 proyectos cada uno y `Proyectos por definir: 1`, o las cifras actuales de `gest_pro` si cambiaron. Copiar la salida al informe de la etapa.

- [ ] **Paso 6: Comprobar que los expedientes no cambiaron**

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_expedientes_estado.php" "$TMP_DIR/expedientes_despues.json"
```

```bash
diff "$TMP_DIR/expedientes_antes.json" "$TMP_DIR/expedientes_despues.json"
```

Expected: `diff` sin salida. Cualquier diferencia detiene la etapa.

- [ ] **Paso 7: Probar en el navegador sobre la copia**

Arrancar el servidor desde `public/` (`artisan serve` recargaría `gest_pro` desde `.env`):

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8765 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Crear un usuario de prueba solo en la copia; nunca usar sesiones de usuarios reales de `gest_pro`:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'verif@gestpro.test', 'rol' => App\Enums\Rol::Administrador]);"
```

La contraseña es la predeterminada de `UserFactory`. Comprobar a 2560 px, 1440 px y 375 px:

1. Parámetros → Fuentes de financiación: aparecen las 10; agregar una, editarla, desactivarla y activarla. No hay botón Eliminar. Un nombre repetido muestra el error.
2. Parámetros → Sectores: lo mismo. Parámetros → Entidades: agregar una entidad ahora guarda sin error.
3. Ctrl+K encuentra ambas secciones.
4. Crear un proyecto sin fuente: avisa y lleva a la pestaña de financiación.
5. Crear un proyecto en Formulación con SGR y Recursos propios: se guarda y nace su expediente.
6. Crear un proyecto solo con Recursos propios: se guarda sin expediente.
7. Editar un proyecto convertido: aparecen sus fuentes marcadas. Marcar "Por definir" desmarca las fuentes, y marcar una fuente desmarca "Por definir".
8. Una fuente desactivada no se ofrece en un proyecto nuevo y se conserva en uno que ya la tiene.
9. El modal de detalle del proyecto muestra los nombres de las fuentes.
10. La consola del navegador no muestra errores.

- [ ] **Paso 8: Probar el rollback en la copia**

Hacer rollback de solo los 3 pasos de esta etapa, en la copia:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=3 --force
```

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT fuente_financiacion, COUNT(*) FROM proyectos GROUP BY fuente_financiacion;"
```

Expected: los proyectos no tocados vuelven a `Recursos propios y regalías` y el que no tenía texto vuelve a `NULL`, como antes de migrar. Los proyectos creados o editados en el paso 7 conservan los nombres de sus fuentes, porque el rollback no descarta lo ingresado después de migrar.

- [ ] **Paso 9: Limpiar**

Detener el servidor y eliminar la copia:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 10: Ejecutar las suites completas una última vez**

Run: `php artisan test`
Expected: pasan todas.

Run: `npm test`
Expected: pasan todas.

- [ ] **Paso 11: Informar al usuario y pedir validación**

Informar: total de pruebas, salida del informe de fuentes, resultado de la comparación y del rollback, y lo revisado en el navegador. Pedir dos decisiones por separado: si valida la etapa y si autoriza aplicar las migraciones en `gest_pro`.

---

### Tarea 12: Aplicar en `gest_pro` (solo con autorización)

No ejecutar esta tarea sin un sí explícito del usuario a este paso.

- [ ] **Paso 1: Respaldar**

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro > "$TMP_DIR/gest_pro_antes_etapa_1.sql"
```

Expected: el archivo existe y no está vacío.

- [ ] **Paso 2: Migrar hacia adelante**

Run: `php artisan migrate --force`
Expected: se aplican las 3 migraciones nuevas.

- [ ] **Paso 3: Revisar**

Run: `php artisan proyectos:informe-fuentes`
Expected: las mismas cifras que en la copia. Compartir con el usuario la lista de proyectos por definir, si hay.

---

## Criterio de la etapa

La etapa está lista cuando, según el diseño:

- El proyecto usa el catálogo de fuentes.
- Los proyectos existentes quedan convertidos, con informe.
- Las pantallas de fuentes y sectores funcionan.
- El expediente automático detecta SGR por la fuente `sgr`, no por palabras en el texto.
- Los expedientes existentes no cambian, comprobado con la comparación de la tarea 11.
