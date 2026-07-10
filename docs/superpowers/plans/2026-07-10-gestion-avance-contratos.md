# Gestión de Avance Financiero y Avance Físico en Contratos — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Habilitar la gestión real de avance financiero (suma de actas / valor del contrato) y avance físico (suma ponderada de actividades) en los contratos, completando los botones "Calcular" que hoy son decorativos.

**Architecture:** Se reutiliza la tabla `avance_financiero` ya existente para las actas financieras; se crean dos tablas nuevas (`actividades_contrato`, `actividad_avances`) para el modelo de actividades ponderadas con historial por fecha. Todo vive en `ProyectoController.php` (sin controlador nuevo) y en dos pestañas nuevas del modal de contrato en `Parametros.jsx`, siguiendo exactamente el mismo patrón arquitectónico que ya usan los "Anexos" de contrato.

**Tech Stack:** Laravel 12 (PHP), MySQL, React 18 (sin TypeScript), Laravel Mix.

**Spec:** `docs/superpowers/specs/2026-07-10-gestion-avance-contratos-design.md`

---

### Task 1: Migraciones, modelos y relación en `Contrato`

**Files:**
- Create: `database/migrations/2026_07_10_100000_create_actividades_contrato_table.php`
- Create: `database/migrations/2026_07_10_100001_create_actividad_avances_table.php`
- Create: `app/Models/ActividadContrato.php`
- Create: `app/Models/ActividadAvance.php`
- Modify: `app/Models/Contrato.php`
- Test: `tests/Feature/Schema/AvancesTest.php`

- [ ] **Step 1: Crear la migración de `actividades_contrato`**

Crear `database/migrations/2026_07_10_100000_create_actividades_contrato_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades_contrato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->string('nombre');
            $table->unsignedTinyInteger('peso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades_contrato');
    }
};
```

- [ ] **Step 2: Crear la migración de `actividad_avances`**

Crear `database/migrations/2026_07_10_100001_create_actividad_avances_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividad_avances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('actividades_contrato')->cascadeOnDelete();
            $table->date('fecha');
            $table->unsignedTinyInteger('porcentaje_ejecucion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividad_avances');
    }
};
```

- [ ] **Step 3: Correr las migraciones**

Run: `php artisan migrate`
Expected: ambas tablas se crean sin errores (`actividades_contrato`, `actividad_avances`).

- [ ] **Step 4: Crear el modelo `ActividadContrato`**

Crear `app/Models/ActividadContrato.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActividadContrato extends Model
{
    public $timestamps = false;

    protected $table = 'actividades_contrato';
    protected $fillable = ['contrato_id', 'nombre', 'peso'];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function avances(): HasMany
    {
        return $this->hasMany(ActividadAvance::class, 'actividad_id');
    }
}
```

- [ ] **Step 5: Crear el modelo `ActividadAvance`**

Crear `app/Models/ActividadAvance.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActividadAvance extends Model
{
    public $timestamps = false;

    protected $table = 'actividad_avances';
    protected $fillable = ['actividad_id', 'fecha', 'porcentaje_ejecucion'];
    protected $casts = ['fecha' => 'date'];

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(ActividadContrato::class, 'actividad_id');
    }
}
```

- [ ] **Step 6: Agregar la relación `actividades()` en `Contrato.php`**

En `app/Models/Contrato.php`, agregar este método justo después de `avancesFisicos()` (antes del cierre `}` de la clase):

```php
    public function actividades(): HasMany
    {
        return $this->hasMany(ActividadContrato::class, 'contrato_id');
    }
```

- [ ] **Step 7: Escribir los tests de schema (fallando primero)**

Agregar al final de la clase en `tests/Feature/Schema/AvancesTest.php` (antes del `}` de cierre), y agregar los imports `use App\Models\ActividadAvance;` y `use App\Models\ActividadContrato;` junto a los imports existentes al inicio del archivo:

```php
    public function test_actividad_contrato_belongs_to_contrato(): void
    {
        $contrato = $this->crearContrato();

        $actividad = ActividadContrato::create([
            'contrato_id' => $contrato->id,
            'nombre' => 'Cimentación',
            'peso' => 30,
        ]);

        $this->assertTrue($actividad->fresh()->contrato->is($contrato));
        $this->assertSame(30, $actividad->fresh()->peso);
    }

    public function test_actividad_avance_belongs_to_actividad_and_tracks_history(): void
    {
        $contrato = $this->crearContrato();
        $actividad = ActividadContrato::create(['contrato_id' => $contrato->id, 'nombre' => 'Estructura', 'peso' => 40]);

        ActividadAvance::create(['actividad_id' => $actividad->id, 'fecha' => '2026-01-01', 'porcentaje_ejecucion' => 20]);
        $segundo = ActividadAvance::create(['actividad_id' => $actividad->id, 'fecha' => '2026-02-01', 'porcentaje_ejecucion' => 50]);

        $this->assertTrue($segundo->fresh()->actividad->is($actividad));
        $this->assertCount(2, $actividad->fresh()->avances);
    }

    public function test_deleting_actividad_cascades_to_its_avances(): void
    {
        $contrato = $this->crearContrato();
        $actividad = ActividadContrato::create(['contrato_id' => $contrato->id, 'nombre' => 'Acabados', 'peso' => 30]);
        ActividadAvance::create(['actividad_id' => $actividad->id, 'fecha' => '2026-01-01', 'porcentaje_ejecucion' => 10]);

        $actividad->delete();

        $this->assertDatabaseCount('actividad_avances', 0);
    }
```

- [ ] **Step 8: Correr los tests**

Run: `php artisan test --filter=AvancesTest`
Expected: `Tests: 6 passed` (los 2 tests originales + los 3 nuevos).

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_07_10_100000_create_actividades_contrato_table.php database/migrations/2026_07_10_100001_create_actividad_avances_table.php app/Models/ActividadContrato.php app/Models/ActividadAvance.php app/Models/Contrato.php tests/Feature/Schema/AvancesTest.php
git commit -m "feat: add actividades_contrato and actividad_avances tables and models"
```

---

### Task 2: Backend — actas de avance financiero

**Files:**
- Modify: `app/Http/Controllers/ProyectoController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Agregar los métodos al controlador**

En `app/Http/Controllers/ProyectoController.php`, agregar estos tres métodos nuevos justo antes del método `listarContratos()` (antes de la línea `public function listarContratos(Request $request)`):

```php
    public function guardarActaFinanciera(Request $request)
    {
        $data = $request->all();

        $nullableDecimal = fn ($value) => ($value === '' || $value === null) ? null : (float) $value;
        $nullableInt = fn ($value) => ($value === '' || $value === null) ? null : (int) $value;
        $nullableString = fn ($value) => ($value === '' || $value === null) ? null : $value;

        try {
            $id = DB::table('avance_financiero')->insertGetId([
                'contrato_id' => $data['contrato_id'],
                'descripcion' => $nullableString($data['descripcion'] ?? null),
                'fecha_acta' => $data['fecha_acta'],
                'valor_facturado' => $nullableDecimal($data['valor_facturado'] ?? null),
                'amortizacion_50' => $nullableDecimal($data['amortizacion_50'] ?? null),
                'valor_presente_acta' => $nullableDecimal($data['valor_presente_acta'] ?? null),
                'porcentaje_ejecutado' => $nullableInt($data['porcentaje_ejecutado'] ?? null),
                'anexo' => $nullableString($data['anexo'] ?? null),
            ]);

            return response()->json(['success' => true, 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function eliminarActaFinanciera(Request $request)
    {
        try {
            $actaId = $request->input('id');
            $acta = DB::table('avance_financiero')->where('id', $actaId)->first();

            if (!$acta) {
                return response()->json(['success' => false, 'mensaje' => 'Acta no encontrada'], 404);
            }

            if ($acta->anexo) {
                $rutaCompleta = public_path($acta->anexo);
                if (file_exists($rutaCompleta)) {
                    unlink($rutaCompleta);
                }
            }

            DB::table('avance_financiero')->where('id', $actaId)->delete();

            return response()->json(['success' => true, 'mensaje' => 'Acta eliminada correctamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'mensaje' => 'Error al eliminar el acta: ' . $e->getMessage()], 500);
        }
    }

    public function subirActa(Request $request)
    {
        try {
            if ($request->hasFile('archivo')) {
                $archivo = $request->file('archivo');

                $request->validate([
                    'archivo' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
                ]);

                $nombreArchivo = time() . '_' . uniqid() . '.' . $archivo->getClientOriginalExtension();

                $directorio = 'actas_avance';
                $rutaCompleta = public_path($directorio);
                if (!file_exists($rutaCompleta)) {
                    mkdir($rutaCompleta, 0755, true);
                }

                $archivo->move($rutaCompleta, $nombreArchivo);
                $rutaArchivo = $directorio . '/' . $nombreArchivo;

                return response()->json([
                    'success' => true,
                    'ruta' => $rutaArchivo,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'mensaje' => 'Archivo subido correctamente'
                ]);
            }

            return response()->json(['success' => false, 'mensaje' => 'No se encontró ningún archivo'], 400);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'mensaje' => 'Error al subir el archivo: ' . $e->getMessage()], 500);
        }
    }

```

- [ ] **Step 2: Adjuntar `avancesFinancieros` a cada contrato en `proyectos()` y `listarContratos()`**

En `app/Http/Controllers/ProyectoController.php`, dentro de `proyectos()`, justo después de este bloque (dentro del `foreach ($contratos as $contrato) { ... }` que ya carga los anexos):

```php
            // Cargar anexos para cada contrato
            foreach ($contratos as $contrato) {
                $anexos = DB::table('anexos_contratos')
                    ->select('id', 'descripcion', 'nombre_archivo', 'ruta_archivo', 'fecha')
                    ->where('contrato_id', $contrato->id)
                    ->get();
                $contrato->anexos = $anexos;
            }
```

reemplazar por (agrega la carga de `avancesFinancieros` dentro del mismo `foreach`):

```php
            // Cargar anexos para cada contrato
            foreach ($contratos as $contrato) {
                $anexos = DB::table('anexos_contratos')
                    ->select('id', 'descripcion', 'nombre_archivo', 'ruta_archivo', 'fecha')
                    ->where('contrato_id', $contrato->id)
                    ->get();
                $contrato->anexos = $anexos;

                $contrato->avancesFinancieros = DB::table('avance_financiero')
                    ->select('id', 'descripcion', 'fecha_acta', 'valor_facturado', 'amortizacion_50', 'valor_presente_acta', 'porcentaje_ejecutado', 'anexo')
                    ->where('contrato_id', $contrato->id)
                    ->orderBy('fecha_acta', 'desc')
                    ->get();
            }
```

En `listarContratos()`, el bloque actual es:

```php
        foreach ($contratos as $contrato) {
            $anexos = DB::table('anexos_contratos')->where('contrato_id', $contrato->id)->get();
            $contrato->anexos = $anexos;
        }
```

reemplazar por:

```php
        foreach ($contratos as $contrato) {
            $anexos = DB::table('anexos_contratos')->where('contrato_id', $contrato->id)->get();
            $contrato->anexos = $anexos;

            $contrato->avancesFinancieros = DB::table('avance_financiero')
                ->where('contrato_id', $contrato->id)
                ->orderBy('fecha_acta', 'desc')
                ->get();
        }
```

- [ ] **Step 3: Agregar las rutas nuevas**

En `routes/api.php`, dentro del grupo `Route::middleware('can:editar-datos')->group(function () { ... })`, el bloque actual es:

```php
        Route::post('/guardarContrato', [ProyectoController::class, 'guardarContrato']);
        Route::post('/subirAnexo', [ProyectoController::class, 'subirAnexo']);
        Route::post('/subirActa', [ProyectoController::class, 'subirActa']);
        Route::post('/eliminarAnexo', [ProyectoController::class, 'eliminarAnexo']);
        Route::post('/eliminarContrato', [ProyectoController::class, 'eliminarContrato']);
```

reemplazar por (la ruta `/subirActa` ya existía declarada; ahora tiene método real):

```php
        Route::post('/guardarContrato', [ProyectoController::class, 'guardarContrato']);
        Route::post('/subirAnexo', [ProyectoController::class, 'subirAnexo']);
        Route::post('/subirActa', [ProyectoController::class, 'subirActa']);
        Route::post('/eliminarAnexo', [ProyectoController::class, 'eliminarAnexo']);
        Route::post('/eliminarContrato', [ProyectoController::class, 'eliminarContrato']);
        Route::post('/guardarActaFinanciera', [ProyectoController::class, 'guardarActaFinanciera']);
        Route::post('/eliminarActaFinanciera', [ProyectoController::class, 'eliminarActaFinanciera']);
```

- [ ] **Step 4: Verificar que los tests siguen en verde**

Run: `php artisan test`
Expected: `Tests: 39 passed` (36 anteriores + 3 de Task 1).

- [ ] **Step 5: Verificar manualmente el endpoint**

Con un servidor de pruebas corriendo (`php -S 127.0.0.1:8030 -t public` en otra terminal) y un contrato ya existente en la base de datos (usa `php artisan tinker` para confirmar un `contrato_id` real), verifica con `curl` (ajusta `contrato_id` y la cookie/token de sesión si hace falta autenticación, o simplemente pasa a la verificación manual vía navegador en la Tarea 5 si prefieres) que `POST /guardarActaFinanciera` inserta una fila en `avance_financiero`, y que `GET /listarContratos?proyecto=<id>` devuelve el contrato con su array `avancesFinancieros` poblado.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ProyectoController.php routes/api.php
git commit -m "feat: add guardarActaFinanciera/eliminarActaFinanciera/subirActa endpoints"
```

---

### Task 3: Backend — actividades ponderadas de avance físico

**Files:**
- Modify: `app/Http/Controllers/ProyectoController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Agregar los métodos al controlador**

En `app/Http/Controllers/ProyectoController.php`, agregar estos tres métodos justo después de los tres agregados en la Task 2 (`guardarActaFinanciera`, `eliminarActaFinanciera`, `subirActa`), antes de `listarContratos()`:

```php
    public function guardarActividad(Request $request)
    {
        $data = $request->all();

        try {
            if (isset($data['id']) && $data['id']) {
                DB::table('actividades_contrato')->where('id', $data['id'])->update([
                    'nombre' => $data['nombre'],
                    'peso' => (int) $data['peso'],
                ]);
                $id = $data['id'];
            } else {
                $id = DB::table('actividades_contrato')->insertGetId([
                    'contrato_id' => $data['contrato_id'],
                    'nombre' => $data['nombre'],
                    'peso' => (int) $data['peso'],
                ]);
            }

            return response()->json(['success' => true, 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function eliminarActividad(Request $request)
    {
        try {
            DB::table('actividades_contrato')->where('id', $request->input('id'))->delete();
            return response()->json(['success' => true, 'mensaje' => 'Actividad eliminada correctamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'mensaje' => 'Error al eliminar la actividad: ' . $e->getMessage()], 500);
        }
    }

    public function registrarAvanceActividad(Request $request)
    {
        $data = $request->all();

        try {
            DB::table('actividad_avances')->insert([
                'actividad_id' => $data['actividad_id'],
                'fecha' => $data['fecha'],
                'porcentaje_ejecucion' => (int) $data['porcentaje_ejecucion'],
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

```

- [ ] **Step 2: Adjuntar `actividades` (con su último avance) a cada contrato en `proyectos()` y `listarContratos()`**

En `proyectos()`, dentro del mismo `foreach ($contratos as $contrato) { ... }` que ya quedó tras la Task 2 (con `anexos` y `avancesFinancieros`), agregar al final del cuerpo del `foreach`:

```php
                $actividades = DB::table('actividades_contrato')
                    ->select('id', 'nombre', 'peso')
                    ->where('contrato_id', $contrato->id)
                    ->get();

                foreach ($actividades as $actividad) {
                    $ultimoAvance = DB::table('actividad_avances')
                        ->where('actividad_id', $actividad->id)
                        ->orderBy('fecha', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();
                    $actividad->ultimo_avance = $ultimoAvance->porcentaje_ejecucion ?? null;
                    $actividad->fecha_ultimo_avance = $ultimoAvance->fecha ?? null;
                }

                $contrato->actividades = $actividades;
```

El `foreach ($contratos as $contrato) { ... }` de `proyectos()` queda así en su totalidad tras las Tasks 2 y 3:

```php
            // Cargar anexos para cada contrato
            foreach ($contratos as $contrato) {
                $anexos = DB::table('anexos_contratos')
                    ->select('id', 'descripcion', 'nombre_archivo', 'ruta_archivo', 'fecha')
                    ->where('contrato_id', $contrato->id)
                    ->get();
                $contrato->anexos = $anexos;

                $contrato->avancesFinancieros = DB::table('avance_financiero')
                    ->select('id', 'descripcion', 'fecha_acta', 'valor_facturado', 'amortizacion_50', 'valor_presente_acta', 'porcentaje_ejecutado', 'anexo')
                    ->where('contrato_id', $contrato->id)
                    ->orderBy('fecha_acta', 'desc')
                    ->get();

                $actividades = DB::table('actividades_contrato')
                    ->select('id', 'nombre', 'peso')
                    ->where('contrato_id', $contrato->id)
                    ->get();

                foreach ($actividades as $actividad) {
                    $ultimoAvance = DB::table('actividad_avances')
                        ->where('actividad_id', $actividad->id)
                        ->orderBy('fecha', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();
                    $actividad->ultimo_avance = $ultimoAvance->porcentaje_ejecucion ?? null;
                    $actividad->fecha_ultimo_avance = $ultimoAvance->fecha ?? null;
                }

                $contrato->actividades = $actividades;
            }
```

En `listarContratos()`, el bloque tras la Task 2 es:

```php
        foreach ($contratos as $contrato) {
            $anexos = DB::table('anexos_contratos')->where('contrato_id', $contrato->id)->get();
            $contrato->anexos = $anexos;

            $contrato->avancesFinancieros = DB::table('avance_financiero')
                ->where('contrato_id', $contrato->id)
                ->orderBy('fecha_acta', 'desc')
                ->get();
        }
```

reemplazar por:

```php
        foreach ($contratos as $contrato) {
            $anexos = DB::table('anexos_contratos')->where('contrato_id', $contrato->id)->get();
            $contrato->anexos = $anexos;

            $contrato->avancesFinancieros = DB::table('avance_financiero')
                ->where('contrato_id', $contrato->id)
                ->orderBy('fecha_acta', 'desc')
                ->get();

            $actividades = DB::table('actividades_contrato')->where('contrato_id', $contrato->id)->get();
            foreach ($actividades as $actividad) {
                $ultimoAvance = DB::table('actividad_avances')
                    ->where('actividad_id', $actividad->id)
                    ->orderBy('fecha', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();
                $actividad->ultimo_avance = $ultimoAvance->porcentaje_ejecucion ?? null;
                $actividad->fecha_ultimo_avance = $ultimoAvance->fecha ?? null;
            }
            $contrato->actividades = $actividades;
        }
```

- [ ] **Step 3: Agregar las rutas nuevas**

En `routes/api.php`, dentro del mismo grupo `can:editar-datos`, agregar después de las dos rutas de la Task 2:

```php
        Route::post('/guardarActividad', [ProyectoController::class, 'guardarActividad']);
        Route::post('/eliminarActividad', [ProyectoController::class, 'eliminarActividad']);
        Route::post('/registrarAvanceActividad', [ProyectoController::class, 'registrarAvanceActividad']);
```

- [ ] **Step 4: Verificar que los tests siguen en verde**

Run: `php artisan test`
Expected: `Tests: 39 passed`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ProyectoController.php routes/api.php
git commit -m "feat: add guardarActividad/eliminarActividad/registrarAvanceActividad endpoints"
```

---

### Task 4: Frontend — estado y handlers de avance financiero

**Files:**
- Modify: `resources/js/components/Parametros.jsx`

- [ ] **Step 1: Agregar el estado de actas financieras**

En `resources/js/components/Parametros.jsx`, justo después de la declaración de `formContrato` (después del bloque `const [formContrato, setFormContrato] = useState({...});`), agregar:

```jsx
    const [actasFinancieras, setActasFinancieras] = useState([]);
    const [formActaFinanciera, setFormActaFinanciera] = useState({
        descripcion: '', fecha_acta: '', valor_facturado: '', amortizacion_50: '',
        valor_presente_acta: '', porcentaje_ejecutado: '', archivo: null
    });
```

- [ ] **Step 2: Agregar los handlers de actas financieras**

Justo después de `handleDeleteAnexo` (después de su llave de cierre `};`), agregar:

```jsx
    // Manejar cambios en el formulario de acta financiera
    const handleActaFinancieraChange = (e) => {
        const { name, value, files } = e.target;
        if (name === 'archivo') {
            setFormActaFinanciera(prev => ({ ...prev, archivo: files[0] }));
        } else {
            setFormActaFinanciera(prev => ({ ...prev, [name]: value }));
        }
    };

    // Agregar acta de avance financiero
    const handleAddActaFinanciera = async () => {
        if (!formActaFinanciera.fecha_acta || !formActaFinanciera.valor_facturado) {
            Swal.fire({ icon: 'warning', title: 'La fecha del acta y el valor facturado son obligatorios' });
            return;
        }

        try {
            let rutaAnexo = null;
            if (formActaFinanciera.archivo) {
                const formData = new FormData();
                formData.append('archivo', formActaFinanciera.archivo);
                const uploadResponse = await axios.post('/subirActa', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });
                if (!uploadResponse.data.success) {
                    Swal.fire({ icon: 'error', title: 'Error al subir el archivo', text: uploadResponse.data.mensaje });
                    return;
                }
                rutaAnexo = uploadResponse.data.ruta;
            }

            const response = await axios.post('/guardarActaFinanciera', {
                contrato_id: editingContrato.id,
                descripcion: formActaFinanciera.descripcion,
                fecha_acta: formActaFinanciera.fecha_acta,
                valor_facturado: formActaFinanciera.valor_facturado,
                amortizacion_50: formActaFinanciera.amortizacion_50,
                valor_presente_acta: formActaFinanciera.valor_presente_acta,
                porcentaje_ejecutado: formActaFinanciera.porcentaje_ejecutado,
                anexo: rutaAnexo
            });

            if (response.data.success) {
                setActasFinancieras(prev => [
                    { ...formActaFinanciera, id: response.data.id, anexo: rutaAnexo },
                    ...prev
                ]);
                setFormActaFinanciera({
                    descripcion: '', fecha_acta: '', valor_facturado: '', amortizacion_50: '',
                    valor_presente_acta: '', porcentaje_ejecutado: '', archivo: null
                });
                Swal.fire({ icon: 'success', title: 'Acta agregada correctamente' });
            }
        } catch (error) {
            console.error('Error al agregar acta financiera:', error);
            Swal.fire({ icon: 'error', title: 'Error al agregar el acta', text: error.response?.data?.mensaje || 'Error interno del servidor' });
        }
    };

    // Eliminar acta de avance financiero
    const handleDeleteActaFinanciera = async (id) => {
        try {
            const response = await axios.post('/eliminarActaFinanciera', { id });
            if (response.data.success) {
                setActasFinancieras(prev => prev.filter(acta => acta.id !== id));
                Swal.fire({ icon: 'success', title: 'Acta eliminada correctamente' });
            }
        } catch (error) {
            console.error('Error al eliminar acta financiera:', error);
            Swal.fire({ icon: 'error', title: 'Error al eliminar el acta', text: error.response?.data?.mensaje || 'Error interno del servidor' });
        }
    };
```

- [ ] **Step 3: Verificar que compila**

Run: `npm run dev`
Expected: compila sin errores (el estado/handlers aún no se usan desde ningún JSX, eso llega en la Task 7 — es normal que no haya efecto visible todavía).

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Parametros.jsx
git commit -m "feat: add avance financiero state and handlers to Parametros.jsx"
```

---

### Task 5: Frontend — estado y handlers de avance físico

**Files:**
- Modify: `resources/js/components/Parametros.jsx`

- [ ] **Step 1: Agregar el estado de actividades**

Justo después del bloque de estado agregado en la Task 4 (`formActaFinanciera`), agregar:

```jsx
    const [actividades, setActividades] = useState([]);
    const [formActividad, setFormActividad] = useState({ nombre: '', peso: '' });
    const [actividadRegistrandoAvance, setActividadRegistrandoAvance] = useState(null);
    const [formNuevoAvance, setFormNuevoAvance] = useState({ fecha: '', porcentaje_ejecucion: '' });
```

- [ ] **Step 2: Agregar los handlers de actividades**

Justo después de `handleDeleteActaFinanciera` (agregado en la Task 4), agregar:

```jsx
    // Manejar cambios en el formulario de nueva actividad
    const handleActividadChange = (e) => {
        const { name, value } = e.target;
        setFormActividad(prev => ({ ...prev, [name]: value }));
    };

    // Agregar actividad ponderada
    const handleAddActividad = async () => {
        if (!formActividad.nombre || !formActividad.peso) {
            Swal.fire({ icon: 'warning', title: 'El nombre y el peso son obligatorios' });
            return;
        }
        try {
            const response = await axios.post('/guardarActividad', {
                contrato_id: editingContrato.id,
                nombre: formActividad.nombre,
                peso: formActividad.peso
            });
            if (response.data.success) {
                setActividades(prev => [
                    ...prev,
                    { id: response.data.id, nombre: formActividad.nombre, peso: parseInt(formActividad.peso, 10), ultimo_avance: null, fecha_ultimo_avance: null }
                ]);
                setFormActividad({ nombre: '', peso: '' });
                Swal.fire({ icon: 'success', title: 'Actividad agregada correctamente' });
            }
        } catch (error) {
            console.error('Error al agregar actividad:', error);
            Swal.fire({ icon: 'error', title: 'Error al agregar la actividad', text: error.response?.data?.mensaje || 'Error interno del servidor' });
        }
    };

    // Eliminar actividad
    const handleDeleteActividad = async (id) => {
        try {
            const response = await axios.post('/eliminarActividad', { id });
            if (response.data.success) {
                setActividades(prev => prev.filter(act => act.id !== id));
                Swal.fire({ icon: 'success', title: 'Actividad eliminada correctamente' });
            }
        } catch (error) {
            console.error('Error al eliminar actividad:', error);
            Swal.fire({ icon: 'error', title: 'Error al eliminar la actividad', text: error.response?.data?.mensaje || 'Error interno del servidor' });
        }
    };

    // Manejar cambios en el mini-formulario de "registrar avance"
    const handleNuevoAvanceChange = (e) => {
        const { name, value } = e.target;
        setFormNuevoAvance(prev => ({ ...prev, [name]: value }));
    };

    // Registrar un nuevo avance (histórico, por fecha) para una actividad
    const handleRegistrarAvance = async (actividadId) => {
        if (!formNuevoAvance.fecha || formNuevoAvance.porcentaje_ejecucion === '') {
            Swal.fire({ icon: 'warning', title: 'La fecha y el porcentaje son obligatorios' });
            return;
        }
        try {
            const response = await axios.post('/registrarAvanceActividad', {
                actividad_id: actividadId,
                fecha: formNuevoAvance.fecha,
                porcentaje_ejecucion: formNuevoAvance.porcentaje_ejecucion
            });
            if (response.data.success) {
                setActividades(prev => prev.map(act => {
                    if (act.id !== actividadId) return act;
                    const esMasReciente = !act.fecha_ultimo_avance || formNuevoAvance.fecha >= act.fecha_ultimo_avance;
                    return esMasReciente
                        ? { ...act, ultimo_avance: parseInt(formNuevoAvance.porcentaje_ejecucion, 10), fecha_ultimo_avance: formNuevoAvance.fecha }
                        : act;
                }));
                setActividadRegistrandoAvance(null);
                setFormNuevoAvance({ fecha: '', porcentaje_ejecucion: '' });
                Swal.fire({ icon: 'success', title: 'Avance registrado correctamente' });
            }
        } catch (error) {
            console.error('Error al registrar avance:', error);
            Swal.fire({ icon: 'error', title: 'Error al registrar el avance', text: error.response?.data?.mensaje || 'Error interno del servidor' });
        }
    };
```

- [ ] **Step 3: Verificar que compila**

Run: `npm run dev`
Expected: compila sin errores.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Parametros.jsx
git commit -m "feat: add avance fisico (actividades) state and handlers to Parametros.jsx"
```

---

### Task 6: Frontend — cargar/resetear avances y conectar los botones "Calcular"

**Files:**
- Modify: `resources/js/components/Parametros.jsx`

- [ ] **Step 1: Cargar actas/actividades al editar un contrato**

En `handleEditContrato`, el bloque actual es:

```jsx
        // Cargar anexos del contrato si existen
        if (contrato.anexos && contrato.anexos.length > 0) {
            const anexosFormateados = contrato.anexos.map(anexo => ({
                id: anexo.id,
                descripcion: anexo.descripcion,
                nombreArchivo: anexo.nombre_archivo,
                rutaArchivo: anexo.ruta_archivo,
                fecha: anexo.fecha
            }));
            setAnexos(anexosFormateados);
        } else {
            setAnexos([]);
        }
        setShowContratoForm(true);
    };
```

reemplazar por:

```jsx
        // Cargar anexos del contrato si existen
        if (contrato.anexos && contrato.anexos.length > 0) {
            const anexosFormateados = contrato.anexos.map(anexo => ({
                id: anexo.id,
                descripcion: anexo.descripcion,
                nombreArchivo: anexo.nombre_archivo,
                rutaArchivo: anexo.ruta_archivo,
                fecha: anexo.fecha
            }));
            setAnexos(anexosFormateados);
        } else {
            setAnexos([]);
        }
        setActasFinancieras(contrato.avancesFinancieros || []);
        setActividades(contrato.actividades || []);
        setShowContratoForm(true);
    };
```

- [ ] **Step 2: Resetear actas/actividades al cancelar y al agregar un contrato nuevo**

En `handleCancelContratoForm`, el bloque actual es:

```jsx
        setAnexos([]);
        setFormAnexo({ descripcion: '', archivo: null });
        setContratoActiveTab('informacion');
    };
```

reemplazar por:

```jsx
        setAnexos([]);
        setFormAnexo({ descripcion: '', archivo: null });
        setActasFinancieras([]);
        setFormActaFinanciera({
            descripcion: '', fecha_acta: '', valor_facturado: '', amortizacion_50: '',
            valor_presente_acta: '', porcentaje_ejecutado: '', archivo: null
        });
        setActividades([]);
        setFormActividad({ nombre: '', peso: '' });
        setActividadRegistrandoAvance(null);
        setFormNuevoAvance({ fecha: '', porcentaje_ejecucion: '' });
        setContratoActiveTab('informacion');
    };
```

En `handleAddNewContrato`, el bloque actual es:

```jsx
        setAnexos([]);
        setFormAnexo({ descripcion: '', archivo: null });
        setContratoActiveTab('informacion');
        setShowContratoForm(true);
    };
```

reemplazar por:

```jsx
        setAnexos([]);
        setFormAnexo({ descripcion: '', archivo: null });
        setActasFinancieras([]);
        setFormActaFinanciera({
            descripcion: '', fecha_acta: '', valor_facturado: '', amortizacion_50: '',
            valor_presente_acta: '', porcentaje_ejecutado: '', archivo: null
        });
        setActividades([]);
        setFormActividad({ nombre: '', peso: '' });
        setActividadRegistrandoAvance(null);
        setFormNuevoAvance({ fecha: '', porcentaje_ejecucion: '' });
        setContratoActiveTab('informacion');
        setShowContratoForm(true);
    };
```

- [ ] **Step 3: Agregar los handlers de "Calcular"**

Justo después de `handleRegistrarAvance` (agregado en la Task 5), agregar:

```jsx
    // Calcular avance financiero: suma de valor_presente_acta de todas las actas / valor del contrato
    const handleCalcularAvanceFinanciero = () => {
        if (actasFinancieras.length === 0) {
            Swal.fire({ icon: 'warning', title: 'No hay actas de avance financiero registradas' });
            return;
        }
        const valorContrato = parseCurrencyValue(formContrato.valor);
        if (!valorContrato) {
            Swal.fire({ icon: 'warning', title: 'Ingrese el valor del contrato antes de calcular' });
            return;
        }
        const totalEjecutado = actasFinancieras.reduce((sum, acta) => sum + (parseFloat(acta.valor_presente_acta) || 0), 0);
        const porcentaje = Math.round((totalEjecutado / valorContrato) * 100);
        setFormContrato(prev => ({ ...prev, avance_financiero: porcentaje }));
    };

    // Calcular avance físico: suma ponderada de (peso x último % de ejecución) de cada actividad
    const handleCalcularAvanceFisico = () => {
        if (actividades.length === 0) {
            Swal.fire({ icon: 'warning', title: 'No hay actividades de avance físico registradas' });
            return;
        }
        const total = actividades.reduce((sum, act) => sum + ((act.peso || 0) / 100) * (act.ultimo_avance || 0), 0);
        setFormContrato(prev => ({ ...prev, avance_fisico: Math.round(total) }));
    };
```

- [ ] **Step 4: Conectar los botones "Calcular" en la pestaña "Información"**

En la pestaña "informacion" del formulario de contrato, el bloque actual es:

```jsx
                                                    <div className="form-group">
                                                        <label htmlFor="avance_financiero">Avance Financiero</label>
                                                        <div className="avance-financiero-container">
                                                            <input type="text" disabled id="avance_financiero" name="avance_financiero" value={formContrato.avance_financiero} onChange={handleContratoChange} />
                                                            <button type="button" className="btn-calcular-avance" title='Calcular Avance Financiero'><FontAwesomeIcon icon={faCalculator} /></button>
                                                        </div>
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="avance_fisico">Avance Físico</label>
                                                        <div className="avance-fisico-container">
                                                            <input type="text" id="avance_fisico" disabled name="avance_fisico" value={formContrato.avance_fisico} onChange={handleContratoChange} />
                                                            <button type="button" className="btn-calcular-avance" title='Calcular Avance Físico'><FontAwesomeIcon icon={faCalculator} /></button>
                                                        </div>
                                                    </div>
```

reemplazar por:

```jsx
                                                    <div className="form-group">
                                                        <label htmlFor="avance_financiero">Avance Financiero</label>
                                                        <div className="avance-financiero-container">
                                                            <input type="text" disabled id="avance_financiero" name="avance_financiero" value={formContrato.avance_financiero} onChange={handleContratoChange} />
                                                            <button type="button" className="btn-calcular-avance" title='Calcular Avance Financiero' onClick={handleCalcularAvanceFinanciero}><FontAwesomeIcon icon={faCalculator} /></button>
                                                        </div>
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="avance_fisico">Avance Físico</label>
                                                        <div className="avance-fisico-container">
                                                            <input type="text" id="avance_fisico" disabled name="avance_fisico" value={formContrato.avance_fisico} onChange={handleContratoChange} />
                                                            <button type="button" className="btn-calcular-avance" title='Calcular Avance Físico' onClick={handleCalcularAvanceFisico}><FontAwesomeIcon icon={faCalculator} /></button>
                                                        </div>
                                                    </div>
```

Nota: `editingContrato` ya está definido y accesible en el mismo scope de todos estos handlers (es un `useState` del componente `Parametros`), así que `handleAddActaFinanciera`/`handleAddActividad` (Tasks 4 y 5) pueden usar `editingContrato.id` directamente sin ningún cambio adicional aquí.

- [ ] **Step 5: Verificar que compila**

Run: `npm run dev`
Expected: compila sin errores.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/Parametros.jsx
git commit -m "feat: load/reset avance financiero and fisico state, wire up Calcular buttons"
```

---

### Task 7: Frontend — pestañas nuevas "Avance Financiero" y "Avance Físico"

**Files:**
- Modify: `resources/js/components/Parametros.jsx`

- [ ] **Step 1: Agregar los botones de las pestañas nuevas**

El bloque de pestañas del formulario de contrato hoy es:

```jsx
                                    {/* Pestañas del formulario de contrato */}
                                    <div className="contrato-tabs">
                                        <button
                                            className={`contrato-tab ${contratoActiveTab === 'informacion' ? 'active' : ''}`}
                                            onClick={() => setContratoActiveTab('informacion')}
                                        >
                                        <FontAwesomeIcon icon={faInfo} />   Información del Contrato
                                        </button>
                                        <button
                                            className={`contrato-tab ${contratoActiveTab === 'anexos' ? 'active' : ''}`}
                                            onClick={() => setContratoActiveTab('anexos')}
                                        >
                                            <FontAwesomeIcon icon={faFile} /> Anexos ({anexos.length})
                                        </button>
                                    </div>
```

reemplazar por (las dos pestañas nuevas solo aparecen si el contrato ya existe — `editingContrato` no es `null` — porque las actas/actividades necesitan un `contrato_id` real):

```jsx
                                    {/* Pestañas del formulario de contrato */}
                                    <div className="contrato-tabs">
                                        <button
                                            className={`contrato-tab ${contratoActiveTab === 'informacion' ? 'active' : ''}`}
                                            onClick={() => setContratoActiveTab('informacion')}
                                        >
                                        <FontAwesomeIcon icon={faInfo} />   Información del Contrato
                                        </button>
                                        <button
                                            className={`contrato-tab ${contratoActiveTab === 'anexos' ? 'active' : ''}`}
                                            onClick={() => setContratoActiveTab('anexos')}
                                        >
                                            <FontAwesomeIcon icon={faFile} /> Anexos ({anexos.length})
                                        </button>
                                        {editingContrato && (
                                            <button
                                                className={`contrato-tab ${contratoActiveTab === 'avance_financiero' ? 'active' : ''}`}
                                                onClick={() => setContratoActiveTab('avance_financiero')}
                                            >
                                                <FontAwesomeIcon icon={faCalculator} /> Avance Financiero ({actasFinancieras.length})
                                            </button>
                                        )}
                                        {editingContrato && (
                                            <button
                                                className={`contrato-tab ${contratoActiveTab === 'avance_fisico' ? 'active' : ''}`}
                                                onClick={() => setContratoActiveTab('avance_fisico')}
                                            >
                                                <FontAwesomeIcon icon={faCalculator} /> Avance Físico ({actividades.length})
                                            </button>
                                        )}
                                    </div>
```

- [ ] **Step 2: Agregar el contenido de la pestaña "Avance Financiero"**

Justo después del cierre de la pestaña de anexos — la línea:

```jsx
                                    )}
                                </>
                            )}
                        </div>
```

(el `)}` que cierra `{contratoActiveTab === 'anexos' && (...)}`, seguido de `</>` que cierra el fragment) — insertar el contenido nuevo ANTES del `</>`, quedando así:

```jsx
                                    )}

                                    {/* Contenido de la pestaña de avance financiero */}
                                    {contratoActiveTab === 'avance_financiero' && (
                                        <div className="contratos-modal-section">
                                            <p className="contratos-info">
                                                Registre las actas de avance financiero del contrato. El avance financiero se calcula sumando el valor presente de todas las actas.
                                            </p>

                                            <div className="anexo-form">
                                                <h4>Agregar Nueva Acta</h4>
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="descripcion_acta_financiera">Descripción</label>
                                                        <input type="text" id="descripcion_acta_financiera" name="descripcion" value={formActaFinanciera.descripcion} onChange={handleActaFinancieraChange} placeholder="Descripción del acta" />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="fecha_acta">Fecha del Acta *</label>
                                                        <input type="date" id="fecha_acta" name="fecha_acta" value={formActaFinanciera.fecha_acta} onChange={handleActaFinancieraChange} />
                                                    </div>
                                                </div>
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="valor_facturado">Valor Facturado *</label>
                                                        <input type="number" id="valor_facturado" name="valor_facturado" value={formActaFinanciera.valor_facturado} onChange={handleActaFinancieraChange} />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="amortizacion_50">Amortización 50%</label>
                                                        <input type="number" id="amortizacion_50" name="amortizacion_50" value={formActaFinanciera.amortizacion_50} onChange={handleActaFinancieraChange} />
                                                    </div>
                                                </div>
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="valor_presente_acta">Valor Presente del Acta</label>
                                                        <input type="number" id="valor_presente_acta" name="valor_presente_acta" value={formActaFinanciera.valor_presente_acta} onChange={handleActaFinancieraChange} />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="porcentaje_ejecutado">% Ejecutado</label>
                                                        <input type="number" id="porcentaje_ejecutado" name="porcentaje_ejecutado" value={formActaFinanciera.porcentaje_ejecutado} onChange={handleActaFinancieraChange} min="0" max="100" />
                                                    </div>
                                                </div>
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="archivo_acta_financiera">Archivo (opcional)</label>
                                                        <input
                                                            type="file"
                                                            id="archivo_acta_financiera"
                                                            name="archivo"
                                                            onChange={handleActaFinancieraChange}
                                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                                        />
                                                    </div>
                                                    <div className="form-group">
                                                        <button
                                                            type="button"
                                                            onClick={handleAddActaFinanciera}
                                                            className="btn-add-anexo"
                                                            disabled={!formActaFinanciera.fecha_acta || !formActaFinanciera.valor_facturado}
                                                        >
                                                            <FontAwesomeIcon icon={faPlus} /> Agregar Acta
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="anexos-list">
                                                <h4>Actas Registradas ({actasFinancieras.length})</h4>
                                                {actasFinancieras.length === 0 ? (
                                                    <div className="no-anexos">
                                                        <p>No hay actas de avance financiero registradas</p>
                                                    </div>
                                                ) : (
                                                    <div className="anexos-table">
                                                        <table>
                                                            <thead>
                                                                <tr>
                                                                    <th>Fecha</th>
                                                                    <th>Valor Facturado</th>
                                                                    <th>Valor Presente</th>
                                                                    <th>% Ejecutado</th>
                                                                    <th>Archivo</th>
                                                                    <th>Acciones</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {actasFinancieras.map(acta => (
                                                                    <tr key={acta.id}>
                                                                        <td>{acta.fecha_acta}</td>
                                                                        <td>$ {Number(acta.valor_facturado || 0).toLocaleString()}</td>
                                                                        <td>$ {Number(acta.valor_presente_acta || 0).toLocaleString()}</td>
                                                                        <td>{acta.porcentaje_ejecutado ? `${acta.porcentaje_ejecutado}%` : '-'}</td>
                                                                        <td>
                                                                            {acta.anexo ? (
                                                                                <button type="button" onClick={() => window.open('/' + acta.anexo, '_blank')} className="btn-view-anexo" title="Ver archivo">
                                                                                    👁️
                                                                                </button>
                                                                            ) : '-'}
                                                                        </td>
                                                                        <td>
                                                                            <button type="button" onClick={() => handleDeleteActaFinanciera(acta.id)} className="btn-delete-anexo" title="Eliminar acta">
                                                                                <FontAwesomeIcon icon={faTrash} />
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                ))}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {/* Contenido de la pestaña de avance físico */}
                                    {contratoActiveTab === 'avance_fisico' && (
                                        <div className="contratos-modal-section">
                                            <p className="contratos-info">
                                                Registre las actividades del contrato con su peso relativo (deben sumar 100%). El avance físico total es la suma de (peso × % de ejecución) de cada actividad.
                                            </p>

                                            <div className="anexo-form">
                                                <h4>Agregar Nueva Actividad</h4>
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="nombre_actividad">Nombre *</label>
                                                        <input type="text" id="nombre_actividad" name="nombre" value={formActividad.nombre} onChange={handleActividadChange} placeholder="Ej: Cimentación" />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="peso_actividad">Peso (%) *</label>
                                                        <input type="number" id="peso_actividad" name="peso" value={formActividad.peso} onChange={handleActividadChange} min="0" max="100" />
                                                    </div>
                                                    <div className="form-group">
                                                        <button
                                                            type="button"
                                                            onClick={handleAddActividad}
                                                            className="btn-add-anexo"
                                                            disabled={!formActividad.nombre || !formActividad.peso}
                                                        >
                                                            <FontAwesomeIcon icon={faPlus} /> Agregar Actividad
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="anexos-list">
                                                <h4>Actividades del Contrato ({actividades.length})</h4>
                                                {actividades.length === 0 ? (
                                                    <div className="no-anexos">
                                                        <p>No hay actividades registradas</p>
                                                    </div>
                                                ) : (
                                                    <>
                                                        <div className="anexos-table">
                                                            <table>
                                                                <thead>
                                                                    <tr>
                                                                        <th>Actividad</th>
                                                                        <th>Peso</th>
                                                                        <th>% Ejecución</th>
                                                                        <th>Última actualización</th>
                                                                        <th>Acciones</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    {actividades.map(actividad => (
                                                                        <React.Fragment key={actividad.id}>
                                                                            <tr>
                                                                                <td>{actividad.nombre}</td>
                                                                                <td>{actividad.peso}%</td>
                                                                                <td>{actividad.ultimo_avance !== null && actividad.ultimo_avance !== undefined ? `${actividad.ultimo_avance}%` : 'Sin registrar'}</td>
                                                                                <td>{actividad.fecha_ultimo_avance || '-'}</td>
                                                                                <td>
                                                                                    <div className="anexo-actions">
                                                                                        <button
                                                                                            type="button"
                                                                                            onClick={() => {
                                                                                                setActividadRegistrandoAvance(actividadRegistrandoAvance === actividad.id ? null : actividad.id);
                                                                                                setFormNuevoAvance({ fecha: '', porcentaje_ejecucion: '' });
                                                                                            }}
                                                                                            className="btn-view-anexo"
                                                                                            title="Registrar avance"
                                                                                        >
                                                                                            <FontAwesomeIcon icon={faCalculator} />
                                                                                        </button>
                                                                                        <button
                                                                                            type="button"
                                                                                            onClick={() => handleDeleteActividad(actividad.id)}
                                                                                            className="btn-delete-anexo"
                                                                                            title="Eliminar actividad"
                                                                                        >
                                                                                            <FontAwesomeIcon icon={faTrash} />
                                                                                        </button>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                            {actividadRegistrandoAvance === actividad.id && (
                                                                                <tr>
                                                                                    <td colSpan="5">
                                                                                        <div className="form-row">
                                                                                            <div className="form-group">
                                                                                                <label htmlFor="fecha_nuevo_avance">Fecha</label>
                                                                                                <input type="date" id="fecha_nuevo_avance" name="fecha" value={formNuevoAvance.fecha} onChange={handleNuevoAvanceChange} />
                                                                                            </div>
                                                                                            <div className="form-group">
                                                                                                <label htmlFor="porcentaje_nuevo_avance">% Ejecución</label>
                                                                                                <input type="number" id="porcentaje_nuevo_avance" name="porcentaje_ejecucion" value={formNuevoAvance.porcentaje_ejecucion} onChange={handleNuevoAvanceChange} min="0" max="100" />
                                                                                            </div>
                                                                                            <div className="form-group">
                                                                                                <button type="button" onClick={() => handleRegistrarAvance(actividad.id)} className="btn-add-anexo">
                                                                                                    <FontAwesomeIcon icon={faSave} /> Guardar
                                                                                                </button>
                                                                                            </div>
                                                                                        </div>
                                                                                    </td>
                                                                                </tr>
                                                                            )}
                                                                        </React.Fragment>
                                                                    ))}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                        <div className="avance-fisico-resumen">
                                                            <span className={`avance-fisico-suma-pesos ${actividades.reduce((s, a) => s + (a.peso || 0), 0) !== 100 ? 'advertencia' : ''}`}>
                                                                Suma de pesos: {actividades.reduce((s, a) => s + (a.peso || 0), 0)}%
                                                            </span>
                                                            <span className="avance-fisico-total">
                                                                Avance físico total: {Math.round(actividades.reduce((s, a) => s + ((a.peso || 0) / 100) * (a.ultimo_avance || 0), 0))}%
                                                            </span>
                                                        </div>
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </>
                            )}
                        </div>
```

- [ ] **Step 3: Verificar que compila**

Run: `npm run dev`
Expected: compila sin errores.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Parametros.jsx
git commit -m "feat: add Avance Financiero and Avance Fisico tabs to contract modal"
```

---

### Task 8: CSS del resumen de avance físico

**Files:**
- Modify: `resources/css/Parametros.css`

- [ ] **Step 1: Agregar el CSS**

Al final de `resources/css/Parametros.css`, agregar:

```css
.avance-fisico-resumen {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    font-weight: 600;
}

.avance-fisico-suma-pesos {
    color: #43a047;
}

.avance-fisico-suma-pesos.advertencia {
    color: #e53935;
}

.avance-fisico-total {
    color: #1976d2;
    font-size: 1.1rem;
}
```

- [ ] **Step 2: Verificar que compila**

Run: `npm run dev`
Expected: compila sin errores.

- [ ] **Step 3: Commit**

```bash
git add resources/css/Parametros.css
git commit -m "style: add avance fisico summary styles"
```

---

### Task 9: Verificación manual end-to-end

- [ ] **Step 1: Levantar el backend y compilar el frontend**

```bash
php -S 127.0.0.1:8030 -t public
npm run dev
```

- [ ] **Step 2: Verificar el flujo de avance financiero**

Inicia sesión, ve a Gestión de Proyectos → un proyecto → Contratos, edita un contrato existente (o crea uno y guárdalo primero — recuerda que las pestañas nuevas solo aparecen al editar). Entra a la pestaña "Avance Financiero", agrega una acta con fecha, valor facturado y valor presente del acta (con y sin archivo adjunto en dos pruebas distintas), confirma que aparece en la tabla. Ve a "Información" y presiona "Calcular Avance Financiero" — confirma que el campo se actualiza con `round(suma valor_presente_acta / valor del contrato * 100)`.

- [ ] **Step 3: Verificar el flujo de avance físico**

En la pestaña "Avance Físico", agrega 2-3 actividades cuyos pesos sumen 100% (ej. 30/40/30). Para cada una, usa "Registrar avance" para agregar un % de ejecución con fecha de hoy. Confirma que el resumen debajo de la tabla muestra la suma de pesos en verde (100%) y el avance físico total calculado correctamente (ej. 30%×100% + 40%×50% + 30%×0% = 50%). Ve a "Información" y presiona "Calcular Avance Físico" — confirma que el campo se actualiza con ese mismo total.

- [ ] **Step 4: Verificar eliminación y persistencia**

Elimina una acta financiera y una actividad, confirma que desaparecen de sus tablas. Cierra el modal de contrato y vuelve a abrirlo en modo edición — confirma que las actas/actividades restantes siguen ahí (se recargaron correctamente desde el backend vía `listarContratos`/`proyectos`).

- [ ] **Step 5: Verificar que un contrato nuevo (sin guardar) no muestra las pestañas nuevas**

Abre "Agregar Nuevo Contrato" — confirma que las pestañas "Avance Financiero"/"Avance Físico" NO aparecen (solo "Información" y "Anexos"), ya que el contrato todavía no tiene `id`.

- [ ] **Step 6: Correr la suite completa de tests una vez más**

Run: `php artisan test`
Expected: `Tests: 39 passed`.

- [ ] **Step 7: Detener el servidor de prueba**

Detener el proceso de `php -S 127.0.0.1:8030` iniciado en el Step 1.
