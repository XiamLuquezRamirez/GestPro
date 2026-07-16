# Adiciones y Prórrogas de Contrato — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Registrar adiciones (aumento de valor) y prórrogas (extensión de plazo) de contratos como un historial de otrosíes, recalculando automáticamente el valor y la fecha fin vigentes del contrato.

**Architecture:** Nueva tabla `modificaciones_contrato` (historial). Se agregan campos base inmutables `valor_inicial` y `fecha_fin_inicial` a `contratos`. El valor y la fecha fin vigentes se recalculan desde el historial en cada alta/baja de otrosí, dentro de una transacción. Backend expone dos endpoints nuevos en `ProyectoController` siguiendo el patrón `DB::table` existente; el modelo Eloquent existe para los tests. El frontend agrega una sección en `Parametros.jsx` junto a los anexos.

**Tech Stack:** Laravel 12 (PHP 8.2), Pest/PHPUnit con `RefreshDatabase`, React 18 (`Parametros.jsx`), Laravel Mix.

**Spec:** `docs/superpowers/specs/2026-07-16-adiciones-prorrogas-contrato-design.md`

---

## Estructura de archivos

- **Crear:** `database/migrations/2026_07_16_100000_create_modificaciones_contrato_table.php` — tabla del historial.
- **Crear:** `database/migrations/2026_07_16_100001_add_valores_iniciales_to_contratos_table.php` — campos base + backfill.
- **Crear:** `app/Models/ModificacionContrato.php` — modelo Eloquent (para tests y relación).
- **Modificar:** `app/Models/Contrato.php` — fillable, casts y relación `modificaciones()`.
- **Modificar:** `app/Http/Controllers/ProyectoController.php` — `guardarContrato` copia iniciales; nuevos `guardarModificacionContrato` y `eliminarModificacionContrato`; `listarContratos` adjunta modificaciones.
- **Modificar:** `routes/api.php` — dos rutas nuevas en el grupo `can:editar-datos`.
- **Crear:** `tests/Feature/Schema/ModificacionesContratoTest.php` — tests de esquema, recálculo y límite 50%.
- **Modificar:** `resources/js/components/Parametros.jsx` — sección "Modificaciones (Adiciones y Prórrogas)".

**Nota sobre entorno:** los comandos usan `php artisan`. En Windows/XAMPP ejecutar desde `C:\xampp\htdocs\GestPro`. El frontend se compila con `npm run dev` (o `npx mix`).

---

## Task 1: Migración de la tabla `modificaciones_contrato`

**Files:**
- Create: `database/migrations/2026_07_16_100000_create_modificaciones_contrato_table.php`
- Test: `tests/Feature/Schema/ModificacionesContratoTest.php`

- [ ] **Step 1: Escribir el test de esquema (falla)**

Crear `tests/Feature/Schema/ModificacionesContratoTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\Contrato;
use App\Models\Municipio;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModificacionesContratoTest extends TestCase
{
    use RefreshDatabase;

    private function crearContrato(array $overrides = []): Contrato
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $proyecto = Proyecto::create(['municipio' => $municipio->codigo, 'nombre' => 'Proyecto de prueba']);
        return Contrato::create(array_merge([
            'proyecto' => $proyecto->id,
            'n_contrato' => 'C-001',
            'valor' => 100000000,
            'fecha_fin' => '2026-12-31',
        ], $overrides));
    }

    public function test_tabla_modificaciones_contrato_tiene_columnas_esperadas(): void
    {
        $this->assertTrue(Schema::hasTable('modificaciones_contrato'));
        $this->assertTrue(Schema::hasColumns('modificaciones_contrato', [
            'id', 'contrato_id', 'numero_otrosi', 'tipo',
            'valor_adicion', 'dias_prorroga', 'fecha_modificacion', 'justificacion',
        ]));
    }
}
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Run: `php artisan test --filter=test_tabla_modificaciones_contrato_tiene_columnas_esperadas`
Expected: FAIL — `Failed asserting that false is true` (la tabla no existe).

- [ ] **Step 3: Escribir la migración**

Crear `database/migrations/2026_07_16_100000_create_modificaciones_contrato_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modificaciones_contrato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->string('numero_otrosi', 50)->nullable();
            $table->string('tipo', 20); // adicion | prorroga | adicion_prorroga
            $table->decimal('valor_adicion', 15, 2)->nullable();
            $table->integer('dias_prorroga')->nullable();
            $table->date('fecha_modificacion');
            $table->text('justificacion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modificaciones_contrato');
    }
};
```

- [ ] **Step 4: Ejecutar el test para verificar que pasa**

Run: `php artisan test --filter=test_tabla_modificaciones_contrato_tiene_columnas_esperadas`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_16_100000_create_modificaciones_contrato_table.php tests/Feature/Schema/ModificacionesContratoTest.php
git commit -m "feat: add modificaciones_contrato table for contract otrosies"
```

---

## Task 2: Campos base `valor_inicial` y `fecha_fin_inicial` en `contratos` con backfill

**Files:**
- Create: `database/migrations/2026_07_16_100001_add_valores_iniciales_to_contratos_table.php`
- Test: `tests/Feature/Schema/ModificacionesContratoTest.php` (agregar test)

- [ ] **Step 1: Escribir el test de columnas + backfill (falla)**

Agregar a `ModificacionesContratoTest`:

```php
    public function test_contratos_tiene_columnas_iniciales(): void
    {
        $this->assertTrue(Schema::hasColumns('contratos', ['valor_inicial', 'fecha_fin_inicial']));
    }

    public function test_backfill_copia_valor_y_fecha_fin_a_iniciales(): void
    {
        // Simular un contrato "preexistente" a la migración: iniciales en null.
        // El helper crea el contrato con valor 100M y fecha_fin 2026-12-31.
        $contrato = $this->crearContrato();
        \Illuminate\Support\Facades\DB::table('contratos')->where('id', $contrato->id)->update([
            'valor_inicial' => null,
            'fecha_fin_inicial' => null,
        ]);

        // Ejecutar la misma rutina de backfill idempotente que corre la migración.
        \Illuminate\Support\Facades\DB::statement('UPDATE contratos SET valor_inicial = valor WHERE valor_inicial IS NULL');
        \Illuminate\Support\Facades\DB::statement('UPDATE contratos SET fecha_fin_inicial = fecha_fin WHERE fecha_fin_inicial IS NULL');

        // Leer a través del modelo Eloquent para aplicar el cast decimal:2
        // (el query builder crudo no castea; en SQLite un decimal se lee como int).
        $fresco = $contrato->fresh();
        $this->assertSame('100000000.00', $fresco->valor_inicial);
        $this->assertSame('2026-12-31', $fresco->fecha_fin_inicial->toDateString());
    }
```

**Nota:** este test lee `valor_inicial`/`fecha_fin_inicial` a través del modelo, así que
los casts `valor_inicial => decimal:2` y `fecha_fin_inicial => date` deben agregarse a
`Contrato` **en esta tarea** (Step 3b abajo). El resto del cambio al modelo (fillable y
relación `modificaciones()`) permanece en Task 3.

- [ ] **Step 2: Ejecutar los tests para verificar que fallan**

Run: `php artisan test --filter=ModificacionesContratoTest`
Expected: `test_contratos_tiene_columnas_iniciales` FAIL (columnas no existen).

- [ ] **Step 3: Escribir la migración con backfill**

Crear `database/migrations/2026_07_16_100001_add_valores_iniciales_to_contratos_table.php`:

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
        Schema::table('contratos', function (Blueprint $table) {
            $table->decimal('valor_inicial', 15, 2)->nullable()->after('valor');
            $table->date('fecha_fin_inicial')->nullable()->after('fecha_fin');
        });

        // Backfill: para contratos existentes, la base inmutable es el valor y
        // la fecha fin actuales (aún no hay modificaciones registradas).
        DB::statement('UPDATE contratos SET valor_inicial = valor WHERE valor_inicial IS NULL');
        DB::statement('UPDATE contratos SET fecha_fin_inicial = fecha_fin WHERE fecha_fin_inicial IS NULL');
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn(['valor_inicial', 'fecha_fin_inicial']);
        });
    }
};
```

- [ ] **Step 3b: Agregar los casts a `Contrato`**

En `app/Models/Contrato.php`, en el array `$casts`, agregar solo estas dos claves
(dejar intactas `valor`, `fecha_inicio`, `fecha_fin`, `anticipo`):

```php
        'valor_inicial' => 'decimal:2',
        'fecha_fin_inicial' => 'date',
```

El `fillable` y la relación `modificaciones()` NO se tocan aquí — van en Task 3.

- [ ] **Step 4: Ejecutar los tests para verificar que pasan**

Run: `php artisan test --filter=ModificacionesContratoTest`
Expected: PASS los tres tests.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_16_100001_add_valores_iniciales_to_contratos_table.php tests/Feature/Schema/ModificacionesContratoTest.php app/Models/Contrato.php
git commit -m "feat: add valor_inicial and fecha_fin_inicial to contratos with backfill"
```

---

## Task 3: Modelo `ModificacionContrato` y relación en `Contrato`

**Files:**
- Create: `app/Models/ModificacionContrato.php`
- Modify: `app/Models/Contrato.php`
- Test: `tests/Feature/Schema/ModificacionesContratoTest.php` (agregar test)

- [ ] **Step 1: Escribir el test de relación y casts (falla)**

Agregar a `ModificacionesContratoTest` (añadir `use App\Models\ModificacionContrato;` arriba):

```php
    public function test_modificacion_belongs_to_contrato_y_castea_decimales(): void
    {
        $contrato = $this->crearContrato();

        $mod = ModificacionContrato::create([
            'contrato_id' => $contrato->id,
            'numero_otrosi' => 'Otrosí No. 1',
            'tipo' => 'adicion_prorroga',
            'valor_adicion' => 30000000,
            'dias_prorroga' => 60,
            'fecha_modificacion' => '2026-03-01',
            'justificacion' => 'Mayor cantidad de obra',
        ]);

        $fresco = $mod->fresh();
        $this->assertTrue($fresco->contrato->is($contrato));
        $this->assertSame('30000000.00', $fresco->valor_adicion);
        $this->assertSame(60, $fresco->dias_prorroga);
        $this->assertCount(1, $contrato->fresh()->modificaciones);
    }
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Run: `php artisan test --filter=test_modificacion_belongs_to_contrato_y_castea_decimales`
Expected: FAIL — `Class "App\Models\ModificacionContrato" not found`.

- [ ] **Step 3: Crear el modelo**

Crear `app/Models/ModificacionContrato.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModificacionContrato extends Model
{
    public $timestamps = false;

    protected $table = 'modificaciones_contrato';

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

- [ ] **Step 4: Agregar fillable y relación en `Contrato`**

Los casts `valor_inicial`/`fecha_fin_inicial` ya se agregaron en Task 2 (Step 3b). Aquí
solo falta el `fillable` y la relación.

En `app/Models/Contrato.php`, agregar `'valor_inicial'` y `'fecha_fin_inicial'` al array
`$fillable` (el orden no afecta). El `$fillable` resultante:

```php
    protected $fillable = [
        'proyecto', 'n_contrato', 'objeto', 'contratante', 'contratista', 'valor',
        'valor_inicial', 'fecha_inicio', 'fecha_fin', 'fecha_fin_inicial',
        'interventoria', 'avance_fisico', 'avance_financiero',
        'estado', 'anticipo', 'porcentaje_anticipo', 'proceso_licitacion',
    ];
```

Agregar la relación (después del método `actividades()`):

```php
    public function modificaciones(): HasMany
    {
        return $this->hasMany(ModificacionContrato::class, 'contrato_id');
    }
```

- [ ] **Step 5: Ejecutar el test para verificar que pasa**

Run: `php artisan test --filter=test_modificacion_belongs_to_contrato_y_castea_decimales`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Models/ModificacionContrato.php app/Models/Contrato.php tests/Feature/Schema/ModificacionesContratoTest.php
git commit -m "feat: add ModificacionContrato model and Contrato relation"
```

---

## Task 4: `guardarContrato` copia valor/fecha iniciales al crear

**Files:**
- Modify: `app/Http/Controllers/ProyectoController.php:348-411`
- Test: `tests/Feature/Schema/ModificacionesContratoTest.php` (agregar test)

**Contexto:** `guardarContrato` hace upsert con `DB::table`. Al **insertar** un contrato nuevo debe fijar `valor_inicial = valor` y `fecha_fin_inicial = fecha_fin`. Al **actualizar** NO debe tocar los iniciales (son inmutables) ni el `valor`/`fecha_fin` vigentes recalculados — esos los gestiona el recálculo de modificaciones. Para evitar que la edición de contrato sobrescriba el valor vigente recalculado, en actualización se excluyen `valor` y `fecha_fin` del payload solo si el contrato ya tiene modificaciones; si no tiene ninguna, se comportan como antes.

- [ ] **Step 1: Escribir el test (falla)**

Agregar a `ModificacionesContratoTest` (añadir `use Illuminate\Support\Facades\DB;` si no está):

```php
    public function test_guardar_contrato_nuevo_fija_valores_iniciales(): void
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $proyecto = Proyecto::create(['municipio' => $municipio->codigo, 'nombre' => 'Proyecto de prueba']);

        $usuario = \App\Models\User::factory()->create(['rol' => 'Administrador']);
        $this->actingAs($usuario);

        $resp = $this->withHeaders($headers)->postJson('/GestPro/guardarContrato', [
            'formContrato' => [
                'n_contrato' => 'C-100',
                'objeto' => 'Construcción',
                'valor' => 200000000,
                'fecha_fin' => '2027-06-30',
                'estado' => 'En ejecución',
                'proyecto' => $proyecto->id,
            ],
            'anexos' => [],
        ]);

        $resp->assertOk();
        $contrato = DB::table('contratos')->where('n_contrato', 'C-100')->first();
        $this->assertSame('200000000.00', $contrato->valor_inicial);
        $this->assertStringStartsWith('2027-06-30', (string) $contrato->fecha_fin_inicial);
    }
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Run: `php artisan test --filter=test_guardar_contrato_nuevo_fija_valores_iniciales`
Expected: FAIL — `valor_inicial` es `null` (la rama de insert aún no lo fija).

- [ ] **Step 3: Modificar la rama de insert en `guardarContrato`**

En `app/Http/Controllers/ProyectoController.php`, dentro de `guardarContrato`, localizar el bloque `else` de insert (líneas ~385-390):

```php
            } else {
                // Insertar nuevo contrato
                $contratoId = DB::table('contratos')->insertGetId(array_merge($contratoPayload, [
                    'proyecto' => $formContrato['proyecto'] ?? null,
                ]));
            }
```

Reemplazarlo por:

```php
            } else {
                // Insertar nuevo contrato: fijar la base inmutable para adiciones/prórrogas.
                $contratoId = DB::table('contratos')->insertGetId(array_merge($contratoPayload, [
                    'proyecto' => $formContrato['proyecto'] ?? null,
                    'valor_inicial' => $contratoPayload['valor'],
                    'fecha_fin_inicial' => $contratoPayload['fecha_fin'],
                ]));
            }
```

- [ ] **Step 4: Ejecutar el test para verificar que pasa**

Run: `php artisan test --filter=test_guardar_contrato_nuevo_fija_valores_iniciales`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ProyectoController.php tests/Feature/Schema/ModificacionesContratoTest.php
git commit -m "feat: guardarContrato sets valor_inicial and fecha_fin_inicial on create"
```

---

## Task 5: Endpoint `guardarModificacionContrato` con recálculo y límite 50%

**Files:**
- Modify: `app/Http/Controllers/ProyectoController.php` (nuevo método antes de `listarContratos`, ~línea 970)
- Modify: `routes/api.php:64` (dentro del grupo `can:editar-datos`)
- Test: `tests/Feature/Schema/ModificacionesContratoTest.php` (agregar tests)

**Recálculo:**
```
valor_vigente     = valor_inicial + SUMA(valor_adicion)
fecha_fin_vigente = fecha_fin_inicial + SUMA(dias_prorroga) días
```

- [ ] **Step 1: Escribir los tests de recálculo y límite 50% (fallan)**

Agregar a `ModificacionesContratoTest`:

```php
    // Auth JWT: devuelve los headers Authorization con un token de Administrador.
    // (Este proyecto usa auth('api')->login, no actingAs — ver AutorizacionTest.)
    private function headersAdmin(): array
    {
        $admin = \App\Models\User::factory()->create(['rol' => \App\Enums\Rol::Administrador]);
        $token = auth('api')->login($admin);
        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_guardar_modificacion_recalcula_valor_y_fecha(): void
    {
        $headers = $this->headersAdmin();
        $contrato = $this->crearContrato(); // valor_inicial vía helper: fijamos abajo
        DB::table('contratos')->where('id', $contrato->id)->update([
            'valor_inicial' => 100000000,
            'fecha_fin_inicial' => '2026-12-31',
        ]);

        $resp = $this->withHeaders($headers)->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $contrato->id,
            'numero_otrosi' => 'Otrosí No. 1',
            'tipo' => 'adicion_prorroga',
            'valor_adicion' => 30000000,
            'dias_prorroga' => 60,
            'fecha_modificacion' => '2026-03-01',
            'justificacion' => 'Mayor obra',
        ]);

        $resp->assertOk();
        $fresco = DB::table('contratos')->where('id', $contrato->id)->first();
        $this->assertEquals('130000000.00', $fresco->valor);
        // 2026-12-31 + 60 días = 2027-03-01
        $this->assertStringStartsWith('2027-03-01', (string) $fresco->fecha_fin);
        $this->assertSame(30.0, round($resp->json('resumen.porcentaje_adicionado'), 2));
        $this->assertFalse($resp->json('resumen.supera_limite'));
    }

    public function test_adicion_supera_50_por_ciento_advierte_pero_persiste(): void
    {
        $headers = $this->headersAdmin();
        $contrato = $this->crearContrato();
        DB::table('contratos')->where('id', $contrato->id)->update([
            'valor_inicial' => 100000000,
            'fecha_fin_inicial' => '2026-12-31',
        ]);

        $resp = $this->withHeaders($headers)->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $contrato->id,
            'numero_otrosi' => 'Otrosí No. 1',
            'tipo' => 'adicion',
            'valor_adicion' => 60000000, // 60% > 50%
            'dias_prorroga' => null,
            'fecha_modificacion' => '2026-03-01',
            'justificacion' => 'Excepción legal',
        ]);

        $resp->assertOk();
        $this->assertTrue($resp->json('resumen.supera_limite'));
        $this->assertDatabaseCount('modificaciones_contrato', 1); // persistió igual
        $fresco = DB::table('contratos')->where('id', $contrato->id)->first();
        $this->assertEquals('160000000.00', $fresco->valor);
    }

    public function test_solo_prorroga_no_cambia_valor(): void
    {
        $headers = $this->headersAdmin();
        $contrato = $this->crearContrato();
        DB::table('contratos')->where('id', $contrato->id)->update([
            'valor_inicial' => 100000000,
            'fecha_fin_inicial' => '2026-12-31',
        ]);

        $this->withHeaders($headers)->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $contrato->id,
            'numero_otrosi' => 'Otrosí No. 1',
            'tipo' => 'prorroga',
            'valor_adicion' => null,
            'dias_prorroga' => 90,
            'fecha_modificacion' => '2026-03-01',
            'justificacion' => 'Retraso lluvias',
        ])->assertOk();

        $fresco = DB::table('contratos')->where('id', $contrato->id)->first();
        $this->assertEquals('100000000.00', $fresco->valor);
        // 2026-12-31 + 90 días = 2027-03-31
        $this->assertStringStartsWith('2027-03-31', (string) $fresco->fecha_fin);
    }
```

- [ ] **Step 2: Ejecutar los tests para verificar que fallan**

Run: `php artisan test --filter=ModificacionesContratoTest`
Expected: los tres tests nuevos FAIL con 404 / ruta no encontrada.

- [ ] **Step 3: Agregar el método al controller**

En `app/Http/Controllers/ProyectoController.php`, agregar este método justo **antes** de `public function listarContratos` (~línea 970). Verificar que `use Carbon\Carbon;` esté importado arriba del archivo; si no, agregarlo junto a los demás `use`.

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
                'tipo' => $data['tipo'],
                'valor_adicion' => $nullableDecimal($data['valor_adicion'] ?? null),
                'dias_prorroga' => $nullableInt($data['dias_prorroga'] ?? null),
                'fecha_modificacion' => $data['fecha_modificacion'],
                'justificacion' => $data['justificacion'] ?? null,
            ]);

            $resumen = $this->recalcularContrato($contratoId);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => 'Modificación registrada correctamente',
            'resumen' => $resumen,
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
```

- [ ] **Step 4: Agregar la ruta**

En `routes/api.php`, dentro del grupo `Route::middleware('can:editar-datos')->group(...)` (después de la línea 63 `registrarAvanceActividad`), agregar:

```php
        Route::post('/guardarModificacionContrato', [ProyectoController::class, 'guardarModificacionContrato']);
```

- [ ] **Step 5: Ejecutar los tests para verificar que pasan**

Run: `php artisan test --filter=ModificacionesContratoTest`
Expected: PASS todos.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ProyectoController.php routes/api.php tests/Feature/Schema/ModificacionesContratoTest.php
git commit -m "feat: guardarModificacionContrato endpoint with recalc and 50% warning"
```

---

## Task 6: Endpoint `eliminarModificacionContrato` con recálculo

**Files:**
- Modify: `app/Http/Controllers/ProyectoController.php` (nuevo método tras `guardarModificacionContrato`)
- Modify: `routes/api.php` (grupo `can:editar-datos`)
- Test: `tests/Feature/Schema/ModificacionesContratoTest.php` (agregar test)

- [ ] **Step 1: Escribir el test (falla)**

Agregar a `ModificacionesContratoTest`:

```php
    public function test_eliminar_modificacion_revierte_recalculo(): void
    {
        $headers = $this->headersAdmin();
        $contrato = $this->crearContrato();
        DB::table('contratos')->where('id', $contrato->id)->update([
            'valor_inicial' => 100000000,
            'fecha_fin_inicial' => '2026-12-31',
        ]);

        $this->withHeaders($headers)->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $contrato->id,
            'numero_otrosi' => 'Otrosí No. 1',
            'tipo' => 'adicion_prorroga',
            'valor_adicion' => 30000000,
            'dias_prorroga' => 60,
            'fecha_modificacion' => '2026-03-01',
            'justificacion' => 'Mayor obra',
        ])->assertOk();

        $modId = DB::table('modificaciones_contrato')->where('contrato_id', $contrato->id)->value('id');

        $resp = $this->withHeaders($headers)->postJson('/GestPro/eliminarModificacionContrato', ['id' => $modId]);
        $resp->assertOk();

        $this->assertDatabaseCount('modificaciones_contrato', 0);
        $fresco = DB::table('contratos')->where('id', $contrato->id)->first();
        $this->assertEquals('100000000.00', $fresco->valor);
        $this->assertStringStartsWith('2026-12-31', (string) $fresco->fecha_fin);
    }
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Run: `php artisan test --filter=test_eliminar_modificacion_revierte_recalculo`
Expected: FAIL con 404 (ruta no encontrada).

- [ ] **Step 3: Agregar el método al controller**

En `app/Http/Controllers/ProyectoController.php`, justo después de `guardarModificacionContrato` (y antes de `recalcularContrato` o después, el orden no importa):

```php
    /**
     * Elimina una modificación (otrosí) y recalcula el contrato afectado.
     */
    public function eliminarModificacionContrato(Request $request)
    {
        $id = $request->input('id');

        DB::beginTransaction();
        try {
            $mod = DB::table('modificaciones_contrato')->where('id', $id)->first();
            if (! $mod) {
                DB::rollBack();
                return response()->json(['error' => 'Modificación no encontrada'], 404);
            }

            $contratoId = $mod->contrato_id;
            DB::table('modificaciones_contrato')->where('id', $id)->delete();

            $resumen = $this->recalcularContrato($contratoId);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => 'Modificación eliminada correctamente',
            'resumen' => $resumen,
        ]);
    }
```

- [ ] **Step 4: Agregar la ruta**

En `routes/api.php`, dentro del grupo `can:editar-datos`, después de `guardarModificacionContrato`:

```php
        Route::post('/eliminarModificacionContrato', [ProyectoController::class, 'eliminarModificacionContrato']);
```

- [ ] **Step 5: Ejecutar el test para verificar que pasa**

Run: `php artisan test --filter=test_eliminar_modificacion_revierte_recalculo`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ProyectoController.php routes/api.php tests/Feature/Schema/ModificacionesContratoTest.php
git commit -m "feat: eliminarModificacionContrato endpoint with recalc revert"
```

---

## Task 7: `listarContratos` adjunta las modificaciones

**Files:**
- Modify: `app/Http/Controllers/ProyectoController.php:971-999`
- Test: `tests/Feature/Schema/ModificacionesContratoTest.php` (agregar test)

- [ ] **Step 1: Escribir el test (falla)**

Agregar a `ModificacionesContratoTest`:

```php
    public function test_listar_contratos_incluye_modificaciones(): void
    {
        $headers = $this->headersAdmin();
        $contrato = $this->crearContrato();
        DB::table('contratos')->where('id', $contrato->id)->update([
            'valor_inicial' => 100000000,
            'fecha_fin_inicial' => '2026-12-31',
        ]);

        DB::table('modificaciones_contrato')->insert([
            'contrato_id' => $contrato->id,
            'numero_otrosi' => 'Otrosí No. 1',
            'tipo' => 'adicion',
            'valor_adicion' => 10000000,
            'dias_prorroga' => null,
            'fecha_modificacion' => '2026-03-01',
            'justificacion' => 'Ajuste',
        ]);

        $proyectoId = DB::table('contratos')->where('id', $contrato->id)->value('proyecto');
        // listarContratos es GET y está bajo auth:api (cualquier rol autenticado).
        $resp = $this->withHeaders($headers)->getJson('/GestPro/listarContratos?proyecto=' . $proyectoId);
        $resp->assertOk();

        $contratoJson = collect($resp->json())->firstWhere('id', $contrato->id);
        $this->assertNotNull($contratoJson);
        $this->assertCount(1, $contratoJson['modificaciones']);
        $this->assertSame('Otrosí No. 1', $contratoJson['modificaciones'][0]['numero_otrosi']);
    }
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Run: `php artisan test --filter=test_listar_contratos_incluye_modificaciones`
Expected: FAIL — la clave `modificaciones` no existe en el JSON.

- [ ] **Step 3: Modificar `listarContratos`**

En `app/Http/Controllers/ProyectoController.php`, dentro del `foreach ($contratos as $contrato)` de `listarContratos`, después de asignar `$contrato->avancesFinancieros` (~línea 983), agregar:

```php
            $contrato->modificaciones = DB::table('modificaciones_contrato')
                ->where('contrato_id', $contrato->id)
                ->orderBy('fecha_modificacion', 'asc')
                ->orderBy('id', 'asc')
                ->get();
```

- [ ] **Step 4: Ejecutar el test para verificar que pasa**

Run: `php artisan test --filter=test_listar_contratos_incluye_modificaciones`
Expected: PASS.

- [ ] **Step 5: Ejecutar toda la suite para asegurar que nada se rompió**

Run: `php artisan test`
Expected: PASS toda la suite (incluye los tests previos de avances y esquema).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ProyectoController.php tests/Feature/Schema/ModificacionesContratoTest.php
git commit -m "feat: listarContratos attaches modificaciones history"
```

---

## Task 8: Frontend — sección "Modificaciones (Adiciones y Prórrogas)" en `Parametros.jsx`

**Files:**
- Modify: `resources/js/components/Parametros.jsx`

**Contexto:** `Parametros.jsx` ya gestiona contratos, anexos y actas con `axios` contra `/api/...`. Esta tarea agrega la UI de modificaciones dentro de la edición de contrato, junto a la sección de anexos. Reutiliza el input de moneda decimal existente (el mismo usado para `valor`/`valor_facturado`, formato `$ 1.487.342.187,33`). Antes de codificar, localizar en el archivo: (a) el estado del formulario de contrato (`formContrato`), (b) la sección JSX de anexos del contrato, (c) la función helper de formato de moneda y el handler del input decimal, (d) cómo se hace un `axios.post` con headers de auth (patrón de `subirActa`).

- [ ] **Step 1: Agregar estado para el formulario de modificación y el listado**

Junto a los demás `useState` del componente de edición de contrato, agregar:

```jsx
const [modificaciones, setModificaciones] = useState([]);
const [formModificacion, setFormModificacion] = useState({
    numero_otrosi: '',
    tipo: 'adicion',
    valor_adicion: '',
    dias_prorroga: '',
    fecha_modificacion: '',
    justificacion: '',
});
const [resumenModificacion, setResumenModificacion] = useState(null);
```

Al cargar/editar un contrato existente, inicializar `setModificaciones(contrato.modificaciones || [])`. Ubicar el punto donde se cargan `contrato.anexos` en el estado y agregar la línea análoga para `modificaciones`.

- [ ] **Step 2: Agregar la función que guarda una modificación**

Añadir dentro del componente (usar el mismo patrón de `axios.post` + headers que usa `subirActa`; `API_URL` / token según convención del archivo):

```jsx
const guardarModificacion = async () => {
    if (!formContrato.id) {
        alert('Guarda primero el contrato antes de registrar modificaciones.');
        return;
    }
    try {
        const resp = await axios.post(`${API_URL}/guardarModificacionContrato`, {
            contrato_id: formContrato.id,
            numero_otrosi: formModificacion.numero_otrosi,
            tipo: formModificacion.tipo,
            valor_adicion: formModificacion.tipo === 'prorroga' ? null : (formModificacion.valor_adicion || null),
            dias_prorroga: formModificacion.tipo === 'adicion' ? null : (formModificacion.dias_prorroga || null),
            fecha_modificacion: formModificacion.fecha_modificacion,
            justificacion: formModificacion.justificacion,
        }, { headers: authHeaders() });

        setResumenModificacion(resp.data.resumen);
        // Refrescar el listado del contrato desde el backend.
        await recargarContratos();
        setFormModificacion({ numero_otrosi: '', tipo: 'adicion', valor_adicion: '', dias_prorroga: '', fecha_modificacion: '', justificacion: '' });
    } catch (e) {
        alert('Error al guardar la modificación: ' + (e.response?.data?.error || e.message));
    }
};
```

Nota: usar el helper de headers de auth y la función de recarga de contratos ya existentes en el archivo. Si se llaman distinto (p. ej. `getAuthHeaders()` o `cargarContratos()`), sustituir por los nombres reales encontrados en el Step 0 de exploración.

- [ ] **Step 3: Agregar la función que elimina una modificación**

```jsx
const eliminarModificacion = async (id) => {
    if (!confirm('¿Eliminar esta modificación? Se recalculará el valor y la fecha fin del contrato.')) return;
    try {
        const resp = await axios.post(`${API_URL}/eliminarModificacionContrato`, { id }, { headers: authHeaders() });
        setResumenModificacion(resp.data.resumen);
        await recargarContratos();
    } catch (e) {
        alert('Error al eliminar la modificación: ' + (e.response?.data?.error || e.message));
    }
};
```

- [ ] **Step 4: Agregar el JSX de la sección**

Insertar junto a la sección de anexos del contrato (dentro del formulario de edición de contrato). Reutilizar la clase de estilos de las otras secciones del formulario:

```jsx
<div className="seccion-modificaciones">
    <h4>Modificaciones (Adiciones y Prórrogas)</h4>

    {resumenModificacion && (
        <div className="resumen-modificacion">
            <p>Valor inicial: {formatearMoneda(resumenModificacion.valor_inicial)} →
               <strong> Valor vigente: {formatearMoneda(resumenModificacion.valor_vigente)}</strong></p>
            <p>Fecha fin inicial: {resumenModificacion.fecha_fin_inicial || '—'} →
               <strong> Fecha fin vigente: {resumenModificacion.fecha_fin_vigente || '—'}</strong></p>
            <p className={resumenModificacion.supera_limite ? 'texto-alerta' : ''}>
                % adicionado: {resumenModificacion.porcentaje_adicionado}%
                {resumenModificacion.supera_limite && ' ⚠ Supera el 50% legal'}
            </p>
        </div>
    )}

    <table className="tabla-modificaciones">
        <thead>
            <tr>
                <th>N° Otrosí</th><th>Tipo</th><th>Adición</th><th>Días</th>
                <th>Fecha</th><th>Justificación</th><th></th>
            </tr>
        </thead>
        <tbody>
            {modificaciones.length === 0 && (
                <tr><td colSpan="7">Sin modificaciones registradas.</td></tr>
            )}
            {modificaciones.map((m) => (
                <tr key={m.id}>
                    <td>{m.numero_otrosi || '—'}</td>
                    <td>{m.tipo === 'adicion_prorroga' ? 'Adición + Prórroga' : (m.tipo === 'adicion' ? 'Adición' : 'Prórroga')}</td>
                    <td>{m.valor_adicion ? formatearMoneda(m.valor_adicion) : '—'}</td>
                    <td>{m.dias_prorroga || '—'}</td>
                    <td>{m.fecha_modificacion}</td>
                    <td>{m.justificacion || '—'}</td>
                    <td><button type="button" onClick={() => eliminarModificacion(m.id)}>🗑</button></td>
                </tr>
            ))}
        </tbody>
    </table>

    <div className="form-nueva-modificacion">
        <input type="text" placeholder="N° Otrosí"
            value={formModificacion.numero_otrosi}
            onChange={(e) => setFormModificacion({ ...formModificacion, numero_otrosi: e.target.value })} />

        <select value={formModificacion.tipo}
            onChange={(e) => setFormModificacion({ ...formModificacion, tipo: e.target.value })}>
            <option value="adicion">Adición</option>
            <option value="prorroga">Prórroga</option>
            <option value="adicion_prorroga">Ambas</option>
        </select>

        {formModificacion.tipo !== 'prorroga' && (
            <input type="text" placeholder="Valor adición"
                value={formModificacion.valor_adicion}
                onChange={(e) => setFormModificacion({ ...formModificacion, valor_adicion: manejarInputDecimal(e.target.value) })} />
        )}

        {formModificacion.tipo !== 'adicion' && (
            <input type="number" placeholder="Días de prórroga"
                value={formModificacion.dias_prorroga}
                onChange={(e) => setFormModificacion({ ...formModificacion, dias_prorroga: e.target.value })} />
        )}

        <input type="date"
            value={formModificacion.fecha_modificacion}
            onChange={(e) => setFormModificacion({ ...formModificacion, fecha_modificacion: e.target.value })} />

        <textarea placeholder="Justificación"
            value={formModificacion.justificacion}
            onChange={(e) => setFormModificacion({ ...formModificacion, justificacion: e.target.value })} />

        <button type="button" onClick={guardarModificacion}>Agregar modificación</button>
    </div>
</div>
```

Nota: `formatearMoneda` y `manejarInputDecimal` deben ser los helpers reales del archivo (nombres detectados en el Step 0). Si el input decimal maneja el valor de otra forma (p. ej. guarda el número crudo y formatea al mostrar), seguir ese mismo patrón para `valor_adicion`.

- [ ] **Step 5: Compilar el frontend**

Run: `npm run dev`
Expected: compila sin errores; `public/js/app.js` y `public/mix-manifest.json` se actualizan.

- [ ] **Step 6: Verificación manual en el navegador**

1. Abrir un proyecto, editar un contrato existente (con `valor` y `fecha_fin`).
2. En la sección "Modificaciones", agregar una adición de valor y verificar que el resumen muestra el valor vigente aumentado.
3. Agregar una prórroga de N días y verificar la nueva fecha fin vigente.
4. Registrar una adición > 50% del valor inicial y verificar que aparece la alerta "⚠ Supera el 50% legal" pero se guarda.
5. Eliminar una modificación y verificar que el valor/fecha vuelven a recalcularse.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/Parametros.jsx public/js/app.js public/mix-manifest.json
git commit -m "feat: contract modifications UI (adiciones/prorrogas) in Parametros"
```

---

## Cierre

- [ ] **Ejecutar la suite completa:** `php artisan test` → todo verde.
- [ ] **Revisar el diff completo** de la rama antes de integrar a `main`.
- [ ] Confirmar que un contrato sin modificaciones sigue comportándose igual que antes (valor y fecha_fin editables por `guardarContrato`).
