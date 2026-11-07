# Plantillas de checklist, etapa 4: administración de plantillas. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** que un administrador cree, duplique, asocie, configure y publique una plantilla de checklist sin intervención de un desarrollador.

**Arquitectura:** `PlantillaService` administra los datos generales, las fuentes y sectores, la activación y la copia de plantillas. `CatalogoService` ya administraba versiones y requisitos del SGR; ahora distingue el motor de la plantilla: el motor `simple` guarda los campos que pide la propuesta y deja que una persona decida los condicionados, y el `sgr` conserva sus reglas y su evaluador. La pantalla nueva, solo para Administrador, tiene una lista y un detalle con cuatro pestañas: general y asociaciones, requisitos, vista previa y versiones.

**Tecnologías:** Laravel 12, PHP 8.2, React 18 con Laravel Mix, PHPUnit sobre SQLite en memoria, Vitest.

**Diseño:** `docs/superpowers/specs/2026-11-06-plantillas-checklist-design.md`: "Duplicar una plantilla" y "Versiones" de la sección 2, tabla "Administración" de la sección 6, y las notas de la etapa 4 en la sección 8.

**Estado de partida:** rama `feature/plantillas-checklist`, etapas 1 a 3 validadas y aplicadas en `gest_pro`, 353 pruebas PHP y 140 JS pasando.

---

## Reglas de ejecución

- **Esta etapa no tiene migraciones.** Solo cambia el `down()` de la migración de plantillas, que nunca se ejecuta en `gest_pro`. Nunca se usa `migrate:rollback` allí.
- Commits autorizados solo en `feature/plantillas-checklist`. Sin push. Mensajes en inglés, como el historial, terminados con la línea `Co-Authored-By` del modelo que comitea.
- Nomenclatura en español sin tildes para clases, métodos y columnas. Textos para el usuario en español con tuteo, como el resto del módulo.
- Pruebas: `php artisan test` sin definir `DB_DATABASE` (usa SQLite en memoria) y `npm test`.
- **Editar con la herramienta Edit, no con `sed`.** Cada reemplazo de este plan aparece una sola vez en su archivo; si no lo encuentras tal cual, detente y avisa.
- El usuario suele tener `npm run watch` corriendo, que reescribe `public/`. Solo la tarea 8 comitea `public/`, después de `npm run production`.
- No tocar ni comitear `public/images/marca-completa.png` ni `public/images/marca-completa1.png`, si siguen modificados: son cambios del usuario.

## Fuera de esta etapa

- Elegir plantillas para un expediente, combinar duplicados y requisitos propios: etapa 5.
- Aplicar una versión nueva a un expediente existente y la pantalla de historial del expediente: etapa 6.
- Administrar sectores, tipos, componentes y tipologías del SGR: ya tienen API y no cambian.

## Decisiones de este plan

- **Plantillas nuevas:** usan el motor `simple` y nacen para todas las fuentes y sectores, con su primera versión en borrador y vacía. El administrador ajusta las asociaciones en el detalle.
- **Motor `sgr`:** solo lo tienen la plantilla SGR y sus copias. En él, el carácter es siempre obligatorio, porque qué aplica lo decide el evaluador.
- **Plantilla SGR:** no se puede desactivar y debe seguir asociada a la fuente SGR. Sus requisitos usan la clave como código común, también al editarla, y siempre exigen aprobación del "no aplica" (decisión del usuario). En las demás plantillas, el código común y la aprobación los define el administrador.
- **Código común:** en el motor `simple` es opcional y no se repite dentro de una versión. No se genera solo: dos plantillas con el mismo código por casualidad se fusionarían en la etapa 5.
- **Duplicar:** la pantalla copia todos los requisitos activos de la versión vigente; los que no sirvan se quitan del borrador antes de publicar. La API también acepta una lista de requisitos. Al pasar del SGR a `simple`, lo que dependía de una condición o de un sector queda condicionado, y la descripción guarda lo que decidía el evaluador.
- **Publicar:** exige al menos un requisito activo y que la plantilla tenga fuentes y sectores. En `simple`, cada condicionado debe describir cuándo aplica; en `sgr`, las condiciones se validan como hasta ahora.
- **Categoría, tipo y alcance:** listas de la propuesta. Categoría: técnico, jurídico, financiero, ambiental, social, administrativo u otro. Tipo: documental, validación o condición. Alcance: general o específica.
- **Rollback:** la migración de plantillas se detiene si la plantilla SGR fue editada, para no perder esos cambios. Responde a la nota de la etapa 4 del diseño.
- **Navegación:** la sección "Plantillas de checklist" aparece en Catálogos solo para el Administrador; otros roles que entren por la URL vuelven al tablero.

## Lo que ya se ensayó

El plan se ensayó completo en un clon descartable fuera del repositorio, y este documento se generó con el código de ese ensayo: un script comprobó que aplicar sus bloques sobre la rama reproduce el ensayo exactamente.

- Pruebas: 386 PHP y 154 JS.
- En el navegador, sobre una copia de `gest_pro`, como Administrador:
  - Se creó una plantilla, se asoció a dos fuentes y se le agregaron dos requisitos, uno condicionado.
  - Se ordenaron los requisitos, se revisó la vista previa y se publicó la versión.
  - Se duplicó la plantilla SGR como simple: 207 requisitos, 203 de ellos condicionados.
- Como Gestor, la sección no aparece y la URL lleva al tablero.

## Archivos

| Archivo | Responsabilidad |
|---|---|
| `app/Services/Viabilizacion/PlantillaService.php` | Datos, asociaciones, activación y copia de plantillas |
| `app/Http/Controllers/PlantillaChecklistController.php` | Rutas de plantillas |
| `app/Services/Viabilizacion/CatalogoService.php` | Primera versión vacía, requisitos y publicación según el motor, orden |
| `app/Http/Controllers/CatalogoRequisitosController.php` | Reglas de validación según el motor y ruta del orden |
| `resources/js/utils/plantillasChecklist.js` | Formularios, vista previa y textos de la administración |
| `resources/js/components/PlantillasChecklist.jsx` | Lista: buscar, crear, duplicar, activar |
| `resources/js/components/PlantillaDetalle.jsx` | Detalle con pestañas y la pestaña general |
| `resources/js/components/PlantillaRequisitos.jsx` | Constructor de requisitos |
| `resources/js/components/PlantillaVistaPrevia.jsx` | El checklist como lo verá el usuario |
| `resources/js/components/PlantillaVersiones.jsx` | Versiones: publicar, crear y eliminar borrador |

---

### Tarea 1: Punto de partida

- [ ] **Paso 1: Comprobar la rama, el estado y las pruebas**

Run: `git branch --show-current`
Expected: `feature/plantillas-checklist`

Run: `git status --short`
Expected: solo, si acaso, `public/` del `npm run watch` y las dos imágenes del usuario.

Run: `php artisan test`
Expected: pasan todas, 353. Si alguna falla, detenerse y avisar.

Run: `npm test`
Expected: pasan todas, 140.

---

### Tarea 2: Requisitos y publicación según el motor

**Files:**
- Test (create): `tests/Feature/Viabilizacion/PlantillasRequisitosApiTest.php`
- Modify: `app/Models/RequisitoCatalogo.php`
- Modify: `app/Services/Viabilizacion/CatalogoService.php`
- Modify: `app/Http/Controllers/CatalogoRequisitosController.php`
- Modify: `routes/viabilizacion.php`
- Test: `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`

Cambia a propósito una prueba existente de `PlantillasVersionesTest`: en la plantilla SGR, editar un requisito ahora aplica las reglas del SGR (código común = clave, aprobación exigida).

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Viabilizacion/PlantillasRequisitosApiTest.php`:

```php
<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlantillasRequisitosApiTest extends TestCase
{
    use RefreshDatabase;

    private PlantillaChecklist $plantilla;
    private CatalogoVersion $borrador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->plantilla = PlantillaChecklist::create([
            'nombre' => 'Inversión pública', 'motor' => PlantillaChecklist::MOTOR_SIMPLE,
            'todas_fuentes' => true, 'todos_sectores' => true,
        ]);
        $this->borrador = app(CatalogoService::class)->crearBorrador(null, $this->plantilla);
    }

    private function comoAdmin(): static
    {
        return $this->como(Rol::Administrador);
    }

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function url(string $resto = ''): string
    {
        return "/GestPro/catalogo-requisitos/versiones/{$this->borrador->id}{$resto}";
    }

    private function crear(array $datos = [])
    {
        return $this->comoAdmin()->postJson($this->url('/requisitos'), ['texto' => 'Certificado de disponibilidad presupuestal', ...$datos]);
    }

    // ------------------------------------------------------------ versiones

    public function test_la_primera_version_de_una_plantilla_nueva_nace_vacia(): void
    {
        $this->assertSame(1, $this->borrador->numero);
        $this->assertSame(0, $this->borrador->requisitos()->count());
    }

    public function test_con_versiones_publicadas_y_ninguna_vigente_no_se_crea_un_borrador(): void
    {
        $this->borrador->update(['estado' => CatalogoVersion::PUBLICADA, 'vigente' => false]);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('No hay una versión vigente que copiar.');
        app(CatalogoService::class)->crearBorrador(null, $this->plantilla);
    }

    // ------------------------------------------------------------ requisitos

    public function test_crea_un_requisito_simple_con_los_campos_de_la_propuesta(): void
    {
        $datos = [
            'descripcion' => 'Expedido por la secretaría de hacienda', 'categoria' => 'financiero',
            'tipo_requisito' => 'documental', 'caracter' => 'opcional', 'cargo_cumplimiento' => 'Secretario de hacienda',
            'cargo_revision' => 'Jefe jurídico', 'soporte_requerido' => 'CDP firmado', 'referencia_normativa' => 'Decreto 111 de 1996',
            'nota' => 'Vigencia no mayor a 30 días', 'requiere_aprobacion_no_aplica' => true, 'codigo_comun' => 'cdp',
        ];

        $this->crear($datos)->assertCreated()->assertJsonPath('clave', 'r1');

        $fila = $this->borrador->requisitos()->first();
        foreach ($datos as $campo => $valor) {
            $this->assertEquals($valor, $fila->{$campo}, $campo);
        }
        $this->assertNull($fila->seccion);
        $this->assertSame(1, $fila->orden);
    }

    public function test_la_clave_y_el_orden_se_generan_si_no_se_indican(): void
    {
        $this->crear()->assertCreated();
        $this->crear(['texto' => 'Otro'])->assertCreated();

        $this->assertSame(['r1' => 1, 'r2' => 2], $this->borrador->requisitos()->orderBy('orden')->pluck('orden', 'clave')->all());
    }

    public function test_un_condicionado_exige_describir_cuando_aplica(): void
    {
        $this->crear(['caracter' => 'condicionado'])->assertStatus(422)
            ->assertJsonPath('errores.0', 'Un requisito condicionado necesita describir cuándo aplica.');

        $this->crear(['caracter' => 'condicionado', 'condicion_descripcion' => 'Si el proyecto compra predios'])->assertCreated();
    }

    public function test_el_codigo_comun_no_se_repite_en_una_version(): void
    {
        $this->crear(['codigo_comun' => 'cdp'])->assertCreated();

        $this->crear(['texto' => 'Otro', 'codigo_comun' => 'cdp'])->assertStatus(422)
            ->assertJsonPath('errores.0', 'Ya existe un requisito con el código común "cdp" en esta versión.');
    }

    public function test_categoria_tipo_y_caracter_se_validan(): void
    {
        $this->crear(['categoria' => 'cualquiera', 'tipo_requisito' => 'otro', 'caracter' => 'urgente'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['categoria', 'tipo_requisito', 'caracter'])
            ->assertJsonPath('errors.categoria.0', 'Elige una categoría de la lista.');
    }

    public function test_editar_conserva_lo_que_no_se_envia(): void
    {
        $id = $this->crear(['categoria' => 'tecnico', 'codigo_comun' => 'cdp'])->json('id');

        $this->comoAdmin()->putJson($this->url("/requisitos/{$id}"), ['texto' => 'Nombre nuevo'])->assertOk();

        $fila = $this->borrador->requisitos()->find($id);
        $this->assertSame('Nombre nuevo', $fila->texto);
        $this->assertSame('tecnico', $fila->categoria);
        $this->assertSame('cdp', $fila->codigo_comun);
        $this->assertSame('r1', $fila->clave);
    }

    public function test_ordenar_requisitos(): void
    {
        $ids = collect(['A', 'B', 'C'])->map(fn ($t) => $this->crear(['texto' => $t])->json('id'))->all();

        $this->comoAdmin()->putJson($this->url('/requisitos-orden'), ['ids' => array_reverse($ids)])->assertOk();
        $this->assertSame(['C', 'B', 'A'], $this->borrador->requisitos()->orderBy('orden')->pluck('texto')->all());

        $this->comoAdmin()->putJson($this->url('/requisitos-orden'), ['ids' => [$ids[0]]])->assertStatus(422)
            ->assertJsonPath('mensaje', 'El orden debe incluir todos los requisitos de la versión, una vez cada uno.');
    }

    public function test_solo_un_administrador_administra_requisitos(): void
    {
        $this->como(Rol::Gestor)->postJson($this->url('/requisitos'), ['texto' => 'x'])->assertForbidden();
    }

    // ------------------------------------------------------------ publicar

    public function test_no_se_publica_una_version_sin_requisitos_activos(): void
    {
        $this->comoAdmin()->postJson($this->url('/publicar'))->assertStatus(422)
            ->assertJsonPath('mensaje', 'La versión no tiene requisitos activos y no puede publicarse.');
    }

    public function test_no_se_publica_sin_fuentes_ni_sectores(): void
    {
        $this->crear()->assertCreated();
        $this->plantilla->update(['todas_fuentes' => false]);

        $this->comoAdmin()->postJson($this->url('/publicar'))->assertStatus(422)
            ->assertJsonPath('mensaje', 'Asocia la plantilla a sus fuentes y sectores antes de publicar.');
    }

    public function test_no_se_publica_un_condicionado_sin_descripcion(): void
    {
        $this->crear()->assertCreated();
        DB::table('requisitos_catalogo')->where('catalogo_version_id', $this->borrador->id)
            ->update(['caracter' => 'condicionado', 'condicion_descripcion' => null]);

        $this->comoAdmin()->postJson($this->url('/publicar'))->assertStatus(422)
            ->assertJsonPath('errores.0', 'Certificado de disponibilidad presupuestal: describe cuándo aplica este requisito condicionado.');
    }

    public function test_publicar_una_plantilla_simple_no_toca_la_sgr(): void
    {
        $this->crear()->assertCreated();
        $sgr = PlantillaChecklist::sgr()->versionVigente();

        $this->comoAdmin()->postJson($this->url('/publicar'))->assertOk()->assertJsonPath('vigente', true);

        $this->assertSame($this->borrador->id, $this->plantilla->versionVigente()->id);
        $this->assertSame($sgr->id, PlantillaChecklist::sgr()->versionVigente()->id);
    }

    // ------------------------------------------------------------- motor sgr

    public function test_en_el_sgr_se_guardan_los_campos_de_seguimiento_pero_no_el_caracter(): void
    {
        $borrador = app(CatalogoService::class)->crearBorrador(null);
        $a1 = DB::table('requisitos_catalogo')->where('catalogo_version_id', $borrador->id)->where('clave', 'a1')->first();

        $this->comoAdmin()->putJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos/{$a1->id}", [
            'clave' => $a1->clave, 'seccion' => $a1->seccion, 'fuente' => $a1->fuente, 'codigo' => $a1->codigo,
            'texto' => $a1->texto, 'condicion' => $a1->condicion, 'categoria' => 'tecnico', 'caracter' => 'opcional',
            'cargo_revision' => 'Oficina jurídica',
        ])->assertOk();

        $editado = DB::table('requisitos_catalogo')->where('id', $a1->id)->first();
        $this->assertSame('tecnico', $editado->categoria);
        $this->assertSame('Oficina jurídica', $editado->cargo_revision);
        $this->assertSame('obligatorio', $editado->caracter);
    }
}
```

En `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`, reemplazar (1 de 2):

```php
    public function test_editar_un_requisito_conserva_su_codigo_comun_y_su_aprobacion_del_no_aplica(): void
```

por:

```php
    public function test_editar_un_requisito_sgr_aplica_las_reglas_del_sgr(): void
```

En `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`, reemplazar (2 de 2):

```php
        $this->assertSame('Texto editado', $editado->texto);
        $this->assertSame('comun', $editado->codigo_comun);
        $this->assertEquals(0, $editado->requiere_aprobacion_no_aplica);
```

por:

```php
        $this->assertSame('Texto editado', $editado->texto);
        $this->assertSame('a1', $editado->codigo_comun);
        $this->assertEquals(1, $editado->requiere_aprobacion_no_aplica);
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Viabilizacion/PlantillasRequisitosApiTest.php tests/Feature/Viabilizacion/PlantillasVersionesTest.php`
Expected: FALLAN las de `PlantillasRequisitosApiTest`: el `setUp` lanza "No hay una versión vigente que copiar." y luego la plantilla simple usa las reglas del SGR. También falla `test_editar_un_requisito_sgr_aplica_las_reglas_del_sgr`.

- [ ] **Paso 3: Implementar**

En `app/Models/RequisitoCatalogo.php`, reemplazar (1 de 1):

```php
    public const CONDICIONADO = 'condicionado';
```

por:

```php
    public const CONDICIONADO = 'condicionado';
    public const CARACTERES = [self::OBLIGATORIO, self::OPCIONAL, self::CONDICIONADO];

    // Valores que pide la propuesta; las etiquetas con tilde las pone la pantalla.
    public const CATEGORIAS = ['tecnico', 'juridico', 'financiero', 'ambiental', 'social', 'administrativo', 'otro'];
    public const TIPOS = ['documental', 'validacion', 'condicion'];
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (1 de 6):

```php
        $vigente = $plantilla->versionVigente()
            ?? throw new ReglaNegocioException('No hay una versión vigente que copiar.');

        return DB::transaction(function () use ($plantilla, $vigente, $descripcion) {
```

por:

```php
        $vigente = $plantilla->versionVigente();
        // Sin versiones publicadas, el borrador nace vacío: es la primera versión de la plantilla.
        if (! $vigente && $plantilla->versiones()->where('estado', CatalogoVersion::PUBLICADA)->exists()) {
            throw new ReglaNegocioException('No hay una versión vigente que copiar.');
        }

        return DB::transaction(function () use ($plantilla, $vigente, $descripcion) {
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (2 de 6):

```php
            $vigente->requisitos()->orderBy('id')->select($columnas)->get()
                ->map(fn ($r) => ['catalogo_version_id' => $borrador->id, ...$r->only($columnas)])
                ->chunk(100)
                ->each(fn ($lote) => DB::table('requisitos_catalogo')->insert($lote->values()->all()));
```

por:

```php
            $vigente?->requisitos()->orderBy('id')->select($columnas)->get()
                ->map(fn ($r) => ['catalogo_version_id' => $borrador->id, ...$r->only($columnas)])
                ->chunk(100)
                ->each(fn ($lote) => DB::table('requisitos_catalogo')->insert($lote->values()->all()));
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (3 de 6):

```php
        $this->exigirBorrador($version);

        $errores = [];
        $componentes = RequisitoComponente::pluck('clave')->all();
        $tipologias = RequisitoTipologia::pluck('clave')->all();
        // En la sección C la condición es opcional: solo se valida si la hay.
        $conCondicion = $version->requisitos()->where('activo', true)
            ->where(fn ($q) => $q->where('seccion', '!=', 'C')->orWhereNotNull('condicion'))
            ->get();
        foreach ($conCondicion as $r) {
            foreach ($this->evaluador->validarCondicion($r->condicion, $componentes, $tipologias) as $error) {
                $errores[] = "{$r->clave}: {$error}";
            }
        }
        if ($errores) {
            throw new ReglaNegocioException('La versión tiene condiciones inválidas y no puede publicarse.', $errores);
        }
```

por:

```php
        $this->exigirBorrador($version);
        $plantilla = $version->plantilla;

        if (! $version->requisitos()->where('activo', true)->exists()) {
            throw new ReglaNegocioException('La versión no tiene requisitos activos y no puede publicarse.');
        }
        $sinFuentes = ! $plantilla->todas_fuentes && ! $plantilla->fuentes()->exists();
        $sinSectores = ! $plantilla->todos_sectores && ! $plantilla->sectores()->exists();
        if ($sinFuentes || $sinSectores) {
            throw new ReglaNegocioException('Asocia la plantilla a sus fuentes y sectores antes de publicar.');
        }

        [$errores, $mensaje] = $plantilla->motor === PlantillaChecklist::MOTOR_SGR
            ? [$this->erroresDeCondiciones($version), 'La versión tiene condiciones inválidas y no puede publicarse.']
            : [$this->erroresDeCondicionados($version), 'La versión tiene requisitos condicionados sin describir y no puede publicarse.'];
        if ($errores) {
            throw new ReglaNegocioException($mensaje, $errores);
        }
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (4 de 6):

```php
    public function eliminarBorrador(CatalogoVersion $version): void
```

por:

```php
    /** Motor sgr: cada condición debe ser válida en el DSL. En la sección C es opcional. */
    private function erroresDeCondiciones(CatalogoVersion $version): array
    {
        $errores = [];
        $componentes = RequisitoComponente::pluck('clave')->all();
        $tipologias = RequisitoTipologia::pluck('clave')->all();
        $conCondicion = $version->requisitos()->where('activo', true)
            ->where(fn ($q) => $q->where('seccion', '!=', 'C')->orWhereNotNull('condicion'))
            ->get();
        foreach ($conCondicion as $r) {
            foreach ($this->evaluador->validarCondicion($r->condicion, $componentes, $tipologias) as $error) {
                $errores[] = "{$r->clave}: {$error}";
            }
        }

        return $errores;
    }

    /** Motor simple: un requisito condicionado debe decir cuándo aplica, para que una persona lo decida. */
    private function erroresDeCondicionados(CatalogoVersion $version): array
    {
        return $version->requisitos()->where('activo', true)
            ->where('caracter', RequisitoCatalogo::CONDICIONADO)
            ->where(fn ($q) => $q->whereNull('condicion_descripcion')->orWhere('condicion_descripcion', ''))
            ->orderBy('orden')
            ->get()
            ->map(fn (RequisitoCatalogo $r) => "{$r->texto}: describe cuándo aplica este requisito condicionado.")
            ->all();
    }

    public function eliminarBorrador(CatalogoVersion $version): void
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (5 de 6):

```php
    public function guardarRequisito(CatalogoVersion $version, array $datos, ?RequisitoCatalogo $requisito = null): RequisitoCatalogo
    {
        $this->exigirBorrador($version);
        if ($requisito && $requisito->catalogo_version_id !== $version->id) {
            throw new ReglaNegocioException('El requisito no pertenece a esta versión.');
        }

        $errores = [];
```

por:

```php
    public function guardarRequisito(CatalogoVersion $version, array $datos, ?RequisitoCatalogo $requisito = null): RequisitoCatalogo
    {
        $this->exigirBorrador($version);
        if ($requisito && $requisito->catalogo_version_id !== $version->id) {
            throw new ReglaNegocioException('El requisito no pertenece a esta versión.');
        }

        if ($version->plantilla->motor === PlantillaChecklist::MOTOR_SIMPLE) {
            return $this->guardarRequisitoSimple($version, $datos, $requisito);
        }

        $errores = [];
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (6 de 6):

```php
            'orden' => $datos['orden'] ?? ($requisito?->orden ?? (int) $version->requisitos()->max('orden') + 1),
            'activo' => $datos['activo'] ?? true,
        ];

        if ($requisito) {
            $requisito->update($valores);

            return $requisito;
        }

        // Al crear en el SGR: el código común es la clave y todo "no aplica" se aprueba. Al editar se conservan.
        return RequisitoCatalogo::create([...$valores, 'codigo_comun' => $datos['clave'], 'requiere_aprobacion_no_aplica' => true]);
    }
```

por:

```php
            'orden' => $datos['orden'] ?? ($requisito?->orden ?? (int) $version->requisitos()->max('orden') + 1),
            'activo' => $datos['activo'] ?? true,
            ...$this->camposDeSeguimiento($datos, $requisito, sinCaracter: true),
        ];
        if ($version->plantilla->clave === PlantillaChecklist::CLAVE_SGR) {
            // En la plantilla SGR el código común es la clave, también al editarla, y todo
            // "no aplica" se aprueba (decisión del usuario).
            $valores['codigo_comun'] = $datos['clave'];
            $valores['requiere_aprobacion_no_aplica'] = true;
        } else {
            // Otra plantilla con motor sgr (una copia): el código común lo define el administrador.
            $valores['codigo_comun'] = array_key_exists('codigo_comun', $datos)
                ? (trim((string) $datos['codigo_comun']) ?: null)
                : $requisito?->codigo_comun;
            $valores['requiere_aprobacion_no_aplica'] = (bool) ($datos['requiere_aprobacion_no_aplica'] ?? $requisito?->requiere_aprobacion_no_aplica ?? true);
        }

        if ($requisito) {
            $requisito->update($valores);

            return $requisito;
        }

        return RequisitoCatalogo::create($valores);
    }

    /** Motor simple: sin sección ni condiciones automáticas; una persona decide si un condicionado aplica. */
    private function guardarRequisitoSimple(CatalogoVersion $version, array $datos, ?RequisitoCatalogo $requisito): RequisitoCatalogo
    {
        $errores = [];
        $clave = trim((string) ($datos['clave'] ?? '')) ?: ($requisito?->clave ?? $this->claveLibre($version));

        $repetida = fn (string $columna, ?string $valor) => $valor !== null && RequisitoCatalogo::where('catalogo_version_id', $version->id)
            ->where($columna, $valor)
            ->when($requisito, fn ($q) => $q->where('id', '!=', $requisito->id))
            ->exists();
        if ($repetida('clave', $clave)) {
            $errores[] = "Ya existe un requisito con la clave \"{$clave}\" en esta versión.";
        }

        $seguimiento = $this->camposDeSeguimiento($datos, $requisito);
        $codigoComun = array_key_exists('codigo_comun', $datos)
            ? (trim((string) $datos['codigo_comun']) ?: null)
            : $requisito?->codigo_comun;
        if ($repetida('codigo_comun', $codigoComun)) {
            $errores[] = "Ya existe un requisito con el código común \"{$codigoComun}\" en esta versión.";
        }
        if ($seguimiento['caracter'] === RequisitoCatalogo::CONDICIONADO && ! $seguimiento['condicion_descripcion']) {
            $errores[] = 'Un requisito condicionado necesita describir cuándo aplica.';
        }

        if ($errores) {
            throw new ReglaNegocioException('El requisito no es válido.', $errores);
        }

        $valores = [
            'catalogo_version_id' => $version->id,
            'clave' => $clave,
            'seccion' => null,
            'fuente' => null,
            'sector_id' => null,
            'tipo_id' => null,
            'condicion' => null,
            'subtitulo' => $datos['subtitulo'] ?? $requisito?->subtitulo,
            'codigo' => $datos['codigo'] ?? $requisito?->codigo,
            'texto' => $datos['texto'],
            'nota' => $datos['nota'] ?? null,
            'orden' => $datos['orden'] ?? ($requisito?->orden ?? (int) $version->requisitos()->max('orden') + 1),
            'activo' => $datos['activo'] ?? ($requisito?->activo ?? true),
            'codigo_comun' => $codigoComun,
            'requiere_aprobacion_no_aplica' => (bool) ($datos['requiere_aprobacion_no_aplica'] ?? $requisito?->requiere_aprobacion_no_aplica ?? false),
            ...$seguimiento,
        ];

        if ($requisito) {
            $requisito->update($valores);

            return $requisito;
        }

        return RequisitoCatalogo::create($valores);
    }

    /** Campos que la propuesta pide a todo requisito. Lo que no llega conserva su valor. */
    private function camposDeSeguimiento(array $datos, ?RequisitoCatalogo $requisito, bool $sinCaracter = false): array
    {
        $texto = fn (string $campo) => array_key_exists($campo, $datos)
            ? (trim((string) $datos[$campo]) ?: null)
            : $requisito?->{$campo};

        $campos = [
            'descripcion' => $texto('descripcion'),
            'categoria' => $texto('categoria'),
            'tipo_requisito' => $texto('tipo_requisito'),
            'cargo_cumplimiento' => $texto('cargo_cumplimiento'),
            'cargo_revision' => $texto('cargo_revision'),
            'soporte_requerido' => $texto('soporte_requerido'),
            'referencia_normativa' => $texto('referencia_normativa'),
            'condicion_descripcion' => $texto('condicion_descripcion'),
        ];
        // En el motor sgr el carácter es siempre obligatorio: qué aplica lo decide el evaluador.
        if (! $sinCaracter) {
            $campos['caracter'] = $datos['caracter'] ?? ($requisito?->caracter ?? RequisitoCatalogo::OBLIGATORIO);
        }

        return $campos;
    }

    private function claveLibre(CatalogoVersion $version): string
    {
        $numero = $version->requisitos()->count() + 1;
        while ($version->requisitos()->where('clave', "r{$numero}")->exists()) {
            $numero++;
        }

        return "r{$numero}";
    }

    /** Orden de presentación: los ids en el orden en que deben mostrarse. */
    public function ordenarRequisitos(CatalogoVersion $version, array $ids): void
    {
        $this->exigirBorrador($version);

        $propios = $version->requisitos()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $pedidos = collect($ids)->map(fn ($id) => (int) $id);
        if ($pedidos->sort()->values()->all() !== $propios) {
            throw new ReglaNegocioException('El orden debe incluir todos los requisitos de la versión, una vez cada uno.');
        }

        DB::transaction(function () use ($pedidos) {
            foreach ($pedidos->values() as $posicion => $id) {
                RequisitoCatalogo::whereKey($id)->update(['orden' => $posicion + 1]);
            }
        });
    }
```

En `app/Http/Controllers/CatalogoRequisitosController.php`, reemplazar (1 de 5):

```php
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
```

por:

```php
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use Illuminate\Validation\Rule;
```

En `app/Http/Controllers/CatalogoRequisitosController.php`, reemplazar (2 de 5):

```php
        $requisito = $this->servicio->guardarRequisito($version, $request->validate($this->reglasRequisito()));
```

por:

```php
        $requisito = $this->servicio->guardarRequisito($version, $request->validate($this->reglasRequisito($version), self::MENSAJES));
```

En `app/Http/Controllers/CatalogoRequisitosController.php`, reemplazar (3 de 5):

```php
        return response()->json($this->servicio->guardarRequisito($version, $request->validate($this->reglasRequisito()), $requisito));
```

por:

```php
        return response()->json($this->servicio->guardarRequisito($version, $request->validate($this->reglasRequisito($version), self::MENSAJES), $requisito));
```

En `app/Http/Controllers/CatalogoRequisitosController.php`, reemplazar (4 de 5):

```php
    public function eliminarRequisito(CatalogoVersion $version, RequisitoCatalogo $requisito): JsonResponse
```

por:

```php
    public function ordenarRequisitos(Request $request, CatalogoVersion $version): JsonResponse
    {
        $datos = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        $this->servicio->ordenarRequisitos($version, $datos['ids']);

        return response()->json(['mensaje' => 'Orden guardado.']);
    }

    public function eliminarRequisito(CatalogoVersion $version, RequisitoCatalogo $requisito): JsonResponse
```

En `app/Http/Controllers/CatalogoRequisitosController.php`, reemplazar (5 de 5):

```php
    private function reglasRequisito(): array
    {
        return [
            'clave' => self::CLAVE,
            'seccion' => ['required', 'in:A,B,C,D'],
            'fuente' => ['required', 'in:a12,ot'],
            'sector_id' => ['nullable', 'integer'],
            'tipo_id' => ['nullable', 'integer'],
            'subtitulo' => ['nullable', 'string', 'max:255'],
            'codigo' => ['required', 'string', 'max:60'],
            'texto' => ['required', 'string'],
            'nota' => ['nullable', 'string'],
            'condicion' => ['nullable', 'string', 'max:255'],
            'orden' => ['sometimes', 'integer', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
```

por:

```php
    private const MENSAJES = [
        'texto.required' => 'Escribe el nombre del requisito.',
        'clave.regex' => 'La clave solo admite minúsculas, números y guion bajo.',
        'caracter.in' => 'El carácter debe ser obligatorio, opcional o condicionado.',
        'categoria.in' => 'Elige una categoría de la lista.',
        'tipo_requisito.in' => 'Elige un tipo de requisito de la lista.',
    ];

    /** Las reglas dependen del motor de la plantilla de la versión. */
    private function reglasRequisito(CatalogoVersion $version): array
    {
        $seguimiento = [
            'descripcion' => ['nullable', 'string'],
            'categoria' => ['nullable', Rule::in(RequisitoCatalogo::CATEGORIAS)],
            'tipo_requisito' => ['nullable', Rule::in(RequisitoCatalogo::TIPOS)],
            'cargo_cumplimiento' => ['nullable', 'string', 'max:150'],
            'cargo_revision' => ['nullable', 'string', 'max:150'],
            'soporte_requerido' => ['nullable', 'string', 'max:255'],
            'referencia_normativa' => ['nullable', 'string', 'max:255'],
            'requiere_aprobacion_no_aplica' => ['sometimes', 'boolean'],
            'codigo_comun' => ['nullable', 'string', 'max:40'],
            'nota' => ['nullable', 'string'],
            'orden' => ['sometimes', 'integer', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ];

        if ($version->plantilla->motor === PlantillaChecklist::MOTOR_SIMPLE) {
            return [
                ...$seguimiento,
                'clave' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/'],
                'texto' => ['required', 'string'],
                'caracter' => ['sometimes', Rule::in(RequisitoCatalogo::CARACTERES)],
                'condicion_descripcion' => ['nullable', 'string'],
                'codigo' => ['nullable', 'string', 'max:60'],
                'subtitulo' => ['nullable', 'string', 'max:255'],
            ];
        }

        return [
            ...$seguimiento,
            'clave' => self::CLAVE,
            'seccion' => ['required', 'in:A,B,C,D'],
            'fuente' => ['required', 'in:a12,ot'],
            'sector_id' => ['nullable', 'integer'],
            'tipo_id' => ['nullable', 'integer'],
            'subtitulo' => ['nullable', 'string', 'max:255'],
            'codigo' => ['required', 'string', 'max:60'],
            'texto' => ['required', 'string'],
            'condicion' => ['nullable', 'string', 'max:255'],
        ];
    }
```

En `routes/viabilizacion.php`, reemplazar (1 de 1):

```php
    Route::get('/versiones/{version}/requisitos', [CatalogoRequisitosController::class, 'requisitos'])->name('requisitos.index');
```

por:

```php
    Route::get('/versiones/{version}/requisitos', [CatalogoRequisitosController::class, 'requisitos'])->name('requisitos.index');
    Route::put('/versiones/{version}/requisitos-orden', [CatalogoRequisitosController::class, 'ordenarRequisitos'])->name('requisitos.orden');
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test`
Expected: pasan todas, 368. `CatalogoRequisitosApiTest` sigue pasando: el SGR conserva sus reglas y su mensaje de condiciones inválidas.

- [ ] **Paso 5: Comitear**

```bash
git add tests/Feature/Viabilizacion/PlantillasRequisitosApiTest.php app/Models/RequisitoCatalogo.php app/Services/Viabilizacion/CatalogoService.php app/Http/Controllers/CatalogoRequisitosController.php routes/viabilizacion.php tests/Feature/Viabilizacion/PlantillasVersionesTest.php
```

```bash
git commit -m "feat: manage requirements and publishing per template engine"
```

---

### Tarea 3: Plantillas: crear, editar, asociar y activar

**Files:**
- Create: `app/Services/Viabilizacion/PlantillaService.php`
- Create: `app/Http/Controllers/PlantillaChecklistController.php`
- Test (create): `tests/Feature/Viabilizacion/PlantillasAdminApiTest.php`
- Modify: `app/Models/CatalogoVersion.php`
- Modify: `routes/viabilizacion.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Viabilizacion/PlantillasAdminApiTest.php`:

```php
<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\User;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlantillasAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private int $sectorEducacion;
    private int $fuentePropios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->sectorEducacion = DB::table('sectores')->insertGetId(['nombre' => 'Educación', 'activo' => true]);
        $this->fuentePropios = FuenteFinanciacion::where('clave', FuenteFinanciacion::RECURSOS_PROPIOS)->value('id');
    }

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function datos(array $cambios = []): array
    {
        return [
            'nombre' => 'Requisitos generales de inversión pública',
            'descripcion' => 'Para todo proyecto de inversión',
            'alcance' => 'general',
            'todas_fuentes' => false,
            'fuente_ids' => [$this->fuentePropios],
            'todos_sectores' => true,
            ...$cambios,
        ];
    }

    private function crear(array $cambios = [])
    {
        return $this->como(Rol::Administrador)->postJson('/GestPro/plantillas', $this->datos($cambios));
    }

    public function test_crear_una_plantilla_la_deja_simple_activa_y_con_su_primera_version_en_borrador(): void
    {
        $respuesta = $this->crear()->assertCreated()
            ->assertJsonPath('motor', 'simple')
            ->assertJsonPath('activo', true)
            ->assertJsonPath('es_sgr', false)
            ->assertJsonPath('fuentes.0.id', $this->fuentePropios)
            ->assertJsonPath('vigente', null)
            ->assertJsonPath('borrador.numero', 1)
            ->assertJsonPath('versiones.0.estado', 'borrador');

        $this->assertSame(0, $respuesta->json('borrador.requisitos'));
    }

    public function test_la_lista_busca_por_nombre_y_muestra_la_version_vigente(): void
    {
        $this->crear()->assertCreated();

        $lista = $this->como(Rol::Administrador)->getJson('/GestPro/plantillas')->assertOk()->json();
        $this->assertCount(2, $lista);
        $sgr = collect($lista)->firstWhere('es_sgr', true);
        $this->assertSame(207, $sgr['vigente']['requisitos']);

        $buscada = $this->como(Rol::Administrador)->getJson('/GestPro/plantillas?buscar=inversi')->assertOk()->json();
        $this->assertSame(['Requisitos generales de inversión pública'], array_column($buscada, 'nombre'));
    }

    public function test_el_nombre_no_se_repite(): void
    {
        $this->crear()->assertCreated();

        $this->crear(['nombre' => 'REQUISITOS GENERALES DE INVERSIÓN PÚBLICA'])->assertStatus(422);
        $this->crear(['nombre' => '  '])->assertStatus(422)->assertJsonPath('errors.nombre.0', 'Escribe el nombre de la plantilla.');
    }

    public function test_exige_fuentes_y_sectores_o_todas(): void
    {
        $this->crear(['fuente_ids' => [], 'todos_sectores' => false])->assertStatus(422)
            ->assertJsonPath('errores', [
                'Elige al menos una fuente de financiación o marca "Todas las fuentes".',
                'Elige al menos un sector o marca "Todos los sectores".',
            ]);
    }

    public function test_no_asocia_una_fuente_inactiva_nueva_pero_conserva_la_que_ya_tenia(): void
    {
        $id = $this->crear()->json('id');
        FuenteFinanciacion::whereKey($this->fuentePropios)->update(['activo' => false]);

        $this->como(Rol::Administrador)->putJson("/GestPro/plantillas/{$id}", $this->datos(['descripcion' => 'Editada']))->assertOk();

        $otra = $this->crear(['nombre' => 'Otra'])->assertStatus(422);
        $this->assertStringContainsString('está inactiva', $otra->json('errores.0'));
    }

    public function test_editar_cambia_datos_y_asociaciones(): void
    {
        $id = $this->crear()->json('id');

        $this->como(Rol::Administrador)->putJson("/GestPro/plantillas/{$id}", $this->datos([
            'nombre' => 'Sector educación', 'alcance' => 'especifica', 'todas_fuentes' => true, 'todos_sectores' => false,
            'sector_ids' => [$this->sectorEducacion],
        ]))->assertOk()
            ->assertJsonPath('nombre', 'Sector educación')
            ->assertJsonPath('alcance', 'especifica')
            ->assertJsonPath('fuentes', [])
            ->assertJsonPath('sectores.0.id', $this->sectorEducacion);
    }

    public function test_la_plantilla_sgr_no_se_desactiva_ni_pierde_la_fuente_sgr(): void
    {
        $sgr = PlantillaChecklist::sgr();

        $this->como(Rol::Administrador)->postJson("/GestPro/plantillas/{$sgr->id}/activar", ['activo' => false])->assertStatus(422)
            ->assertJsonPath('mensaje', 'La plantilla SGR no se puede desactivar: la usan los expedientes de regalías.');
        $this->como(Rol::Administrador)->putJson("/GestPro/plantillas/{$sgr->id}", $this->datos(['nombre' => 'SGR']))->assertStatus(422)
            ->assertJsonPath('errores.0', 'La plantilla SGR debe seguir asociada a la fuente SGR.');
    }

    public function test_activar_y_desactivar_otra_plantilla(): void
    {
        $id = $this->crear()->json('id');

        $this->como(Rol::Administrador)->postJson("/GestPro/plantillas/{$id}/activar", ['activo' => false])->assertOk()->assertJsonPath('activo', false);
        $this->como(Rol::Administrador)->postJson("/GestPro/plantillas/{$id}/activar", ['activo' => true])->assertOk()->assertJsonPath('activo', true);
    }

    public function test_crear_una_version_nueva_copia_la_vigente(): void
    {
        $sgr = PlantillaChecklist::sgr();

        $this->como(Rol::Administrador)->postJson("/GestPro/plantillas/{$sgr->id}/versiones", ['descripcion' => 'Ajustes'])
            ->assertCreated()->assertJsonPath('borrador.numero', 2)->assertJsonPath('borrador.requisitos', 207);
    }

    public function test_el_detalle_dice_quien_publico_cada_version(): void
    {
        $admin = User::factory()->create(['rol' => Rol::Administrador, 'name' => 'Ana Admin']);
        $sgr = PlantillaChecklist::sgr();
        $sgr->versionVigente()->update(['usuario_publico_id' => $admin->id]);

        $this->como(Rol::Administrador)->getJson("/GestPro/plantillas/{$sgr->id}")->assertOk()
            ->assertJsonPath('versiones.0.publicada_por', 'Ana Admin')
            ->assertJsonPath('versiones.0.requisitos', 207);
    }

    public function test_solo_un_administrador_administra_plantillas(): void
    {
        $this->como(Rol::Gestor)->getJson('/GestPro/plantillas')->assertForbidden();
        $this->como(Rol::Gestor)->postJson('/GestPro/plantillas', $this->datos())->assertForbidden();
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Viabilizacion/PlantillasAdminApiTest.php`
Expected: FALLAN porque no existen las rutas `/plantillas` (el comodín de `routes/web.php` responde 405 a un POST y HTML a un GET).

- [ ] **Paso 3: Implementar**

Crear `app/Services/Viabilizacion/PlantillaService.php`:

```php
<?php

namespace App\Services\Viabilizacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Administración de plantillas de checklist (rol Administrador): datos generales,
 * fuentes y sectores a los que aplican, y activación. Los requisitos y las versiones
 * se administran con CatalogoService.
 */
final class PlantillaService
{
    public const ALCANCES = ['general', 'especifica'];

    public function __construct(private readonly CatalogoService $catalogo)
    {
    }

    public function listar(?string $buscar): array
    {
        $buscar = trim((string) $buscar);

        return PlantillaChecklist::query()
            ->with(['fuentes:id,nombre', 'sectores:id,nombre', 'versiones' => fn ($q) => $q->withCount('requisitos')])
            ->when($buscar !== '', function ($q) use ($buscar) {
                $patron = '%' . addcslashes($buscar, '%_\\') . '%';
                $q->where(fn ($w) => $w->where('nombre', 'like', $patron)->orWhere('descripcion', 'like', $patron));
            })
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get()
            ->map(fn (PlantillaChecklist $p) => $this->resumen($p))
            ->all();
    }

    public function presentar(PlantillaChecklist $plantilla): array
    {
        $plantilla->load([
            'fuentes:id,nombre', 'sectores:id,nombre',
            'versiones' => fn ($q) => $q->withCount(['requisitos', 'expedientes'])->with('usuarioPublico:id,name')->orderByDesc('numero'),
        ]);

        return [
            ...$this->resumen($plantilla),
            'versiones' => $plantilla->versiones->map(fn (CatalogoVersion $v) => [
                'id' => $v->id,
                'numero' => $v->numero,
                'estado' => $v->estado,
                'vigente' => $v->vigente,
                'descripcion' => $v->descripcion,
                'fecha_publicacion' => $v->fecha_publicacion?->toIso8601String(),
                'publicada_por' => $v->usuarioPublico?->name,
                'requisitos' => $v->requisitos_count,
                'expedientes' => $v->expedientes_count,
            ])->values()->all(),
        ];
    }

    /** Las plantillas nuevas usan el motor simple y nacen con su primera versión en borrador. */
    public function crear(array $datos, ?User $usuario): PlantillaChecklist
    {
        $asociaciones = $this->validar($datos, null);

        return DB::transaction(function () use ($datos, $asociaciones, $usuario) {
            $plantilla = PlantillaChecklist::create([
                ...$this->datosGenerales($datos),
                'motor' => PlantillaChecklist::MOTOR_SIMPLE,
                'activo' => true,
                'usuario_creo_id' => $usuario?->id,
            ]);
            $this->asociar($plantilla, $asociaciones);
            $this->catalogo->crearBorrador('Versión inicial', $plantilla);

            return $plantilla;
        });
    }

    public function actualizar(PlantillaChecklist $plantilla, array $datos): PlantillaChecklist
    {
        $asociaciones = $this->validar($datos, $plantilla);

        return DB::transaction(function () use ($plantilla, $datos, $asociaciones) {
            $plantilla->update($this->datosGenerales($datos));
            $this->asociar($plantilla, $asociaciones);

            return $plantilla;
        });
    }

    public function activar(PlantillaChecklist $plantilla, bool $activo): PlantillaChecklist
    {
        if (! $activo && $plantilla->clave === PlantillaChecklist::CLAVE_SGR) {
            throw new ReglaNegocioException('La plantilla SGR no se puede desactivar: la usan los expedientes de regalías.');
        }
        $plantilla->update(['activo' => $activo]);

        return $plantilla;
    }

    // ------------------------------------------------------------- internos

    private function resumen(PlantillaChecklist $plantilla): array
    {
        $vigente = $plantilla->versiones->firstWhere('vigente', true);
        $borrador = $plantilla->versiones->firstWhere('estado', CatalogoVersion::BORRADOR);
        $version = fn (?CatalogoVersion $v) => $v ? ['id' => $v->id, 'numero' => $v->numero, 'requisitos' => $v->requisitos_count] : null;

        return [
            'id' => $plantilla->id,
            'clave' => $plantilla->clave,
            'es_sgr' => $plantilla->clave === PlantillaChecklist::CLAVE_SGR,
            'nombre' => $plantilla->nombre,
            'descripcion' => $plantilla->descripcion,
            'alcance' => $plantilla->alcance,
            'motor' => $plantilla->motor,
            'activo' => $plantilla->activo,
            'todas_fuentes' => $plantilla->todas_fuentes,
            'todos_sectores' => $plantilla->todos_sectores,
            'fuentes' => $plantilla->fuentes->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre])->values()->all(),
            'sectores' => $plantilla->sectores->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre])->values()->all(),
            'vigente' => $version($vigente),
            'borrador' => $version($borrador),
        ];
    }

    private function datosGenerales(array $datos): array
    {
        return [
            'nombre' => trim($datos['nombre']),
            'descripcion' => trim((string) ($datos['descripcion'] ?? '')) ?: null,
            'alcance' => $datos['alcance'] ?? null,
            'todas_fuentes' => (bool) ($datos['todas_fuentes'] ?? false),
            'todos_sectores' => (bool) ($datos['todos_sectores'] ?? false),
        ];
    }

    /** @return array{fuente_ids: list<int>, sector_ids: list<int>} */
    private function validar(array $datos, ?PlantillaChecklist $plantilla): array
    {
        $errores = [];

        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $repetido = PlantillaChecklist::query()
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->when($plantilla, fn ($q) => $q->whereKeyNot($plantilla->id))
            ->exists();
        if ($repetido) {
            $errores[] = "Ya existe una plantilla llamada \"{$nombre}\".";
        }

        $todasFuentes = (bool) ($datos['todas_fuentes'] ?? false);
        $todosSectores = (bool) ($datos['todos_sectores'] ?? false);
        $fuenteIds = $todasFuentes ? [] : $this->idsValidos(FuenteFinanciacion::class, $datos['fuente_ids'] ?? [], $plantilla?->fuentes()->pluck('fuentes_financiacion.id')->all() ?? [], ['La fuente', 'inactiva'], $errores);
        $sectorIds = $todosSectores ? [] : $this->idsValidos(Sector::class, $datos['sector_ids'] ?? [], $plantilla?->sectores()->pluck('sectores.id')->all() ?? [], ['El sector', 'inactivo'], $errores);

        if (! $todasFuentes && ! $fuenteIds) {
            $errores[] = 'Elige al menos una fuente de financiación o marca "Todas las fuentes".';
        }
        if (! $todosSectores && ! $sectorIds) {
            $errores[] = 'Elige al menos un sector o marca "Todos los sectores".';
        }

        // La plantilla SGR debe seguir ofreciéndose a los proyectos de regalías.
        if ($plantilla?->clave === PlantillaChecklist::CLAVE_SGR && ! $todasFuentes) {
            $sgr = FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id');
            if (! in_array($sgr, $fuenteIds, true)) {
                $errores[] = 'La plantilla SGR debe seguir asociada a la fuente SGR.';
            }
        }

        if ($errores) {
            throw new ReglaNegocioException('La plantilla no es válida.', $errores);
        }

        return ['fuente_ids' => $fuenteIds, 'sector_ids' => $sectorIds];
    }

    /** Ids que existen; uno inactivo solo si la plantilla ya lo tenía. */
    /** @param array{0: string, 1: string} $etiqueta p. ej. ['La fuente', 'inactiva'] */
    private function idsValidos(string $modelo, array $ids, array $previos, array $etiqueta, array &$errores): array
    {
        [$nombre, $inactivo] = $etiqueta;
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $previos = array_map('intval', $previos);
        $encontrados = $modelo::query()->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($ids as $id) {
            $item = $encontrados->get($id);
            if (! $item) {
                $errores[] = "{$nombre} con id {$id} no existe.";
            } elseif (! $item->activo && ! in_array($id, $previos, true)) {
                $errores[] = "{$nombre} \"{$item->nombre}\" está {$inactivo}.";
            }
        }

        return $ids;
    }

    private function asociar(PlantillaChecklist $plantilla, array $asociaciones): void
    {
        $plantilla->fuentes()->sync($asociaciones['fuente_ids']);
        $plantilla->sectores()->sync($asociaciones['sector_ids']);
    }
}
```

Crear `app/Http/Controllers/PlantillaChecklistController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\PlantillaChecklist;
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\PlantillaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Plantillas de checklist. Rutas con can:gestionar-catalogos (rol Administrador). */
class PlantillaChecklistController extends Controller
{
    private const MENSAJES = [
        'nombre.required' => 'Escribe el nombre de la plantilla.',
        'nombre.max' => 'El nombre admite hasta 199 caracteres.',
        'alcance.in' => 'El alcance debe ser general o específico.',
        'motor.in' => 'El motor debe ser sgr o simple.',
    ];

    public function __construct(
        private readonly PlantillaService $plantillas,
        private readonly CatalogoService $catalogo,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->plantillas->listar($request->query('buscar')));
    }

    public function show(PlantillaChecklist $plantilla): JsonResponse
    {
        return response()->json($this->plantillas->presentar($plantilla));
    }

    public function store(Request $request): JsonResponse
    {
        $plantilla = $this->plantillas->crear($request->validate($this->reglas(), self::MENSAJES), $request->user());

        return response()->json($this->plantillas->presentar($plantilla), 201);
    }

    public function update(Request $request, PlantillaChecklist $plantilla): JsonResponse
    {
        $this->plantillas->actualizar($plantilla, $request->validate($this->reglas(), self::MENSAJES));

        return response()->json($this->plantillas->presentar($plantilla->refresh()));
    }

    public function activar(Request $request, PlantillaChecklist $plantilla): JsonResponse
    {
        $datos = $request->validate(['activo' => ['required', 'boolean']]);
        $this->plantillas->activar($plantilla, (bool) $datos['activo']);

        return response()->json($this->plantillas->presentar($plantilla->refresh()));
    }

    public function crearVersion(Request $request, PlantillaChecklist $plantilla): JsonResponse
    {
        $datos = $request->validate(['descripcion' => ['nullable', 'string', 'max:255']]);
        $this->catalogo->crearBorrador($datos['descripcion'] ?? null, $plantilla);

        return response()->json($this->plantillas->presentar($plantilla), 201);
    }

    private function reglas(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:199'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'alcance' => ['nullable', Rule::in(PlantillaService::ALCANCES)],
            'todas_fuentes' => ['sometimes', 'boolean'],
            'todos_sectores' => ['sometimes', 'boolean'],
            'fuente_ids' => ['sometimes', 'array'],
            'fuente_ids.*' => ['integer'],
            'sector_ids' => ['sometimes', 'array'],
            'sector_ids.*' => ['integer'],
        ];
    }
}
```

En `app/Models/CatalogoVersion.php`, reemplazar (1 de 1):

```php
    public function requisitos(): HasMany
```

por:

```php
    public function usuarioPublico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_publico_id');
    }

    public function requisitos(): HasMany
```

En `routes/viabilizacion.php`, reemplazar (1 de 2):

```php
use App\Http\Controllers\NoAplicaRequisitoController;
```

por:

```php
use App\Http\Controllers\NoAplicaRequisitoController;
use App\Http\Controllers\PlantillaChecklistController;
```

En `routes/viabilizacion.php`, reemplazar (2 de 2):

```php
// Administración del catálogo maestro: solo Administrador.
```

por:

```php
// Plantillas de checklist: solo Administrador. Sus requisitos y versiones se
// administran con las rutas de /catalogo-requisitos/versiones/{version}.
Route::middleware('can:gestionar-catalogos')->prefix('plantillas')->name('plantillas.')->group(function () {
    Route::get('/', [PlantillaChecklistController::class, 'index'])->name('index');
    Route::post('/', [PlantillaChecklistController::class, 'store'])->name('store');
    Route::get('/{plantilla}', [PlantillaChecklistController::class, 'show'])->name('show');
    Route::put('/{plantilla}', [PlantillaChecklistController::class, 'update'])->name('update');
    Route::post('/{plantilla}/activar', [PlantillaChecklistController::class, 'activar'])->name('activar');
    Route::post('/{plantilla}/versiones', [PlantillaChecklistController::class, 'crearVersion'])->name('versiones.store');
});

// Administración del catálogo maestro: solo Administrador.
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test`
Expected: pasan todas, 379.

- [ ] **Paso 5: Comitear**

```bash
git add app/Services/Viabilizacion/PlantillaService.php app/Http/Controllers/PlantillaChecklistController.php tests/Feature/Viabilizacion/PlantillasAdminApiTest.php app/Models/CatalogoVersion.php routes/viabilizacion.php
```

```bash
git commit -m "feat: add checklist template administration API"
```

---

### Tarea 4: Duplicar una plantilla

**Files:**
- Test (create): `tests/Feature/Viabilizacion/PlantillasDuplicarApiTest.php`
- Modify: `app/Services/Viabilizacion/PlantillaService.php`
- Modify: `app/Http/Controllers/PlantillaChecklistController.php`
- Modify: `routes/viabilizacion.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Viabilizacion/PlantillasDuplicarApiTest.php`:

```php
<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\PlantillaChecklist;
use App\Models\User;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlantillasDuplicarApiTest extends TestCase
{
    use RefreshDatabase;

    private PlantillaChecklist $sgr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->sgr = PlantillaChecklist::sgr();
    }

    private function duplicar(array $datos)
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => Rol::Administrador])))
            ->postJson("/GestPro/plantillas/{$this->sgr->id}/duplicar", ['nombre' => 'Copia SGR', ...$datos]);
    }

    private function requisito(string $clave): object
    {
        return DB::table('requisitos_catalogo')
            ->where('catalogo_version_id', $this->sgr->versionVigente()->id)->where('clave', $clave)->first();
    }

    private function copiaDe(int $plantilla, string $clave): object
    {
        $version = PlantillaChecklist::find($plantilla)->versiones()->first();

        return DB::table('requisitos_catalogo')->where('catalogo_version_id', $version->id)->where('clave', $clave)->first();
    }

    public function test_una_copia_es_independiente_y_conserva_el_codigo_comun(): void
    {
        $respuesta = $this->duplicar(['requisito_ids' => [$this->requisito('a1')->id, $this->requisito('a2')->id]])
            ->assertCreated()
            ->assertJsonPath('motor', 'sgr')
            ->assertJsonPath('clave', null)
            ->assertJsonPath('borrador.requisitos', 2)
            ->assertJsonPath('vigente', null)
            ->assertJsonPath('fuentes.0.nombre', 'Sistema General de Regalías (SGR)');

        $copia = $this->copiaDe($respuesta->json('id'), 'a1');
        $this->assertSame('a1', $copia->codigo_comun);
        $this->assertSame(207, $this->sgr->versionVigente()->requisitos()->count()); // el origen no cambia
    }

    public function test_sin_elegir_requisitos_copia_todos_los_activos(): void
    {
        $this->duplicar([])->assertCreated()->assertJsonPath('borrador.requisitos', 207);
    }

    public function test_pasar_a_simple_vuelve_condicionadas_las_condiciones_automaticas(): void
    {
        $siempre = DB::table('requisitos_catalogo')->where('catalogo_version_id', $this->sgr->versionVigente()->id)
            ->where('condicion', 'siempre')->value('clave');
        $conCondicion = DB::table('requisitos_catalogo')->where('catalogo_version_id', $this->sgr->versionVigente()->id)
            ->whereNotIn('condicion', ['siempre'])->whereNotNull('condicion')->where('seccion', '!=', 'C')->first();
        $sectorial = DB::table('requisitos_catalogo')->where('catalogo_version_id', $this->sgr->versionVigente()->id)
            ->where('seccion', 'C')->whereNull('condicion')->first();

        $id = $this->duplicar([
            'motor' => 'simple',
            'requisito_ids' => [$this->requisito($siempre)->id, $conCondicion->id, $sectorial->id],
        ])->assertCreated()->assertJsonPath('motor', 'simple')->json('id');

        $simple = $this->copiaDe($id, $siempre);
        $this->assertSame('obligatorio', $simple->caracter);
        $this->assertNull($simple->seccion);
        $this->assertNull($simple->condicion);

        $condicionado = $this->copiaDe($id, $conCondicion->clave);
        $this->assertSame('condicionado', $condicionado->caracter);
        $this->assertSame("Condición original: {$conCondicion->condicion}", $condicionado->condicion_descripcion);

        $porSector = $this->copiaDe($id, $sectorial->clave);
        $this->assertSame('condicionado', $porSector->caracter);
        $this->assertNull($porSector->sector_id);
        $this->assertStringStartsWith('Sector: ', $porSector->condicion_descripcion);
    }

    public function test_el_nombre_no_se_repite_y_los_requisitos_deben_ser_del_origen(): void
    {
        $this->duplicar(['nombre' => 'SGR'])->assertStatus(422);
        $this->duplicar(['requisito_ids' => [999999]])->assertStatus(422)
            ->assertJsonPath('mensaje', 'Algunos requisitos elegidos no son de la versión vigente de la plantilla.');
        $this->duplicar(['requisito_ids' => []])->assertStatus(422)
            ->assertJsonPath('mensaje', 'Elige al menos un requisito para la copia.');
    }

    public function test_una_plantilla_simple_no_se_copia_como_sgr(): void
    {
        $simple = $this->duplicar(['motor' => 'simple', 'requisito_ids' => [$this->requisito('a1')->id]])->json('id');
        $version = PlantillaChecklist::find($simple)->versiones()->first();
        $version->update(['estado' => 'publicada', 'vigente' => true]);

        $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => Rol::Administrador])))
            ->postJson("/GestPro/plantillas/{$simple}/duplicar", ['nombre' => 'Otra', 'motor' => 'sgr'])
            ->assertStatus(422)->assertJsonPath('mensaje', 'Una plantilla simple solo se puede copiar como simple.');
    }

    public function test_en_una_copia_con_motor_sgr_el_codigo_comun_se_conserva_al_editar(): void
    {
        $id = $this->duplicar(['requisito_ids' => [$this->requisito('a1')->id]])->json('id');
        $version = PlantillaChecklist::find($id)->versiones()->first();
        $a1 = $this->copiaDe($id, 'a1');

        $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => Rol::Administrador])))
            ->putJson("/GestPro/catalogo-requisitos/versiones/{$version->id}/requisitos/{$a1->id}", [
                'clave' => 'a1_copia', 'seccion' => $a1->seccion, 'fuente' => $a1->fuente, 'codigo' => $a1->codigo,
                'texto' => $a1->texto, 'condicion' => $a1->condicion, 'requiere_aprobacion_no_aplica' => false,
            ])->assertOk();

        $editado = DB::table('requisitos_catalogo')->where('id', $a1->id)->first();
        $this->assertSame('a1', $editado->codigo_comun);
        $this->assertEquals(0, $editado->requiere_aprobacion_no_aplica);
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Viabilizacion/PlantillasDuplicarApiTest.php`
Expected: FALLAN porque no existe la ruta `/plantillas/{plantilla}/duplicar`.

- [ ] **Paso 3: Implementar**

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (1 de 2):

```php
use App\Models\PlantillaChecklist;
use App\Models\Sector;
```

por:

```php
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Models\RequisitoSector;
use App\Models\RequisitoTipo;
use App\Models\Sector;
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (2 de 2):

```php
    // ------------------------------------------------------------- internos
```

por:

```php
    /**
     * Copia independiente de una plantilla, en borrador, con los requisitos de su versión
     * vigente que se elijan y el mismo código común. Una copia con motor sgr puede pasar a
     * simple: las condiciones automáticas se vuelven condicionados que decide una persona.
     */
    public function duplicar(PlantillaChecklist $origen, array $datos, ?User $usuario): PlantillaChecklist
    {
        $vigente = $origen->versionVigente()
            ?? throw new ReglaNegocioException('La plantilla no tiene una versión publicada que copiar.');

        $motor = $datos['motor'] ?? $origen->motor;
        if ($origen->motor === PlantillaChecklist::MOTOR_SIMPLE && $motor !== PlantillaChecklist::MOTOR_SIMPLE) {
            throw new ReglaNegocioException('Una plantilla simple solo se puede copiar como simple.');
        }

        $requisitos = $vigente->requisitos()->where('activo', true)->orderBy('orden')->get();
        if (array_key_exists('requisito_ids', $datos)) {
            $pedidos = array_map('intval', $datos['requisito_ids']);
            $ajenos = array_diff($pedidos, $requisitos->pluck('id')->all());
            if ($ajenos) {
                throw new ReglaNegocioException('Algunos requisitos elegidos no son de la versión vigente de la plantilla.');
            }
            $requisitos = $requisitos->whereIn('id', $pedidos)->values();
        }
        if ($requisitos->isEmpty()) {
            throw new ReglaNegocioException('Elige al menos un requisito para la copia.');
        }

        $general = [
            'nombre' => $datos['nombre'],
            'descripcion' => $origen->descripcion,
            'alcance' => $origen->alcance,
            'todas_fuentes' => $origen->todas_fuentes,
            'todos_sectores' => $origen->todos_sectores,
            'fuente_ids' => $origen->fuentes()->where('activo', true)->pluck('fuentes_financiacion.id')->all(),
            'sector_ids' => $origen->sectores()->where('activo', true)->pluck('sectores.id')->all(),
        ];
        $asociaciones = $this->validar($general, null);

        return DB::transaction(function () use ($general, $asociaciones, $motor, $requisitos, $vigente, $origen, $usuario) {
            $copia = PlantillaChecklist::create([
                ...$this->datosGenerales($general),
                'motor' => $motor,
                'activo' => true,
                'usuario_creo_id' => $usuario?->id,
            ]);
            $this->asociar($copia, $asociaciones);
            $borrador = $this->catalogo->crearBorrador("Copia de {$origen->nombre} v{$vigente->numero}", $copia);

            $aSimple = $origen->motor === PlantillaChecklist::MOTOR_SGR && $motor === PlantillaChecklist::MOTOR_SIMPLE;
            $sectores = $aSimple ? RequisitoSector::pluck('nombre', 'id')->all() : [];
            $tipos = $aSimple ? RequisitoTipo::pluck('nombre', 'id')->all() : [];

            foreach ($requisitos->values() as $posicion => $requisito) {
                $fila = [
                    ...$requisito->only([
                        'clave', 'seccion', 'fuente', 'sector_id', 'tipo_id', 'subtitulo', 'codigo', 'texto', 'nota', 'condicion',
                        'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
                        'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
                        'requiere_aprobacion_no_aplica', 'codigo_comun',
                    ]),
                    'catalogo_version_id' => $borrador->id,
                    'orden' => $posicion + 1,
                    'activo' => true,
                ];
                RequisitoCatalogo::create($aSimple ? $this->aSimple($fila, $sectores, $tipos) : $fila);
            }

            return $copia;
        });
    }

    // ------------------------------------------------------------- internos

    /**
     * Un requisito SGR en una plantilla simple: deja la sección y las condiciones automáticas.
     * Si dependía de una condición o de un sector, queda condicionado y la descripción guarda
     * lo que decidía el evaluador, para que el administrador la redacte antes de publicar.
     */
    private function aSimple(array $fila, array $sectores, array $tipos): array
    {
        $condicion = $fila['condicion'];
        $porCondicion = $condicion !== null && $condicion !== 'siempre';
        $porSector = $fila['sector_id'] !== null;

        $partes = [];
        if ($porSector) {
            $partes[] = 'Sector: ' . ($sectores[$fila['sector_id']] ?? '')
                . ($fila['tipo_id'] ? ' — ' . ($tipos[$fila['tipo_id']] ?? '') : '');
        }
        if ($porCondicion) {
            $partes[] = "Condición original: {$condicion}";
        }

        return [
            ...$fila,
            'seccion' => null,
            'fuente' => null,
            'sector_id' => null,
            'tipo_id' => null,
            'condicion' => null,
            'caracter' => ($porCondicion || $porSector) ? RequisitoCatalogo::CONDICIONADO : $fila['caracter'],
            'condicion_descripcion' => $partes ? implode('. ', $partes) : $fila['condicion_descripcion'],
        ];
    }
```

En `app/Http/Controllers/PlantillaChecklistController.php`, reemplazar (1 de 1):

```php
    public function crearVersion(Request $request, PlantillaChecklist $plantilla): JsonResponse
```

por:

```php
    public function duplicar(Request $request, PlantillaChecklist $plantilla): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:199'],
            'motor' => ['sometimes', Rule::in([PlantillaChecklist::MOTOR_SGR, PlantillaChecklist::MOTOR_SIMPLE])],
            'requisito_ids' => ['sometimes', 'array'],
            'requisito_ids.*' => ['integer'],
        ], self::MENSAJES);
        $copia = $this->plantillas->duplicar($plantilla, $datos, $request->user());

        return response()->json($this->plantillas->presentar($copia), 201);
    }

    public function crearVersion(Request $request, PlantillaChecklist $plantilla): JsonResponse
```

En `routes/viabilizacion.php`, reemplazar (1 de 1):

```php
    Route::post('/{plantilla}/activar', [PlantillaChecklistController::class, 'activar'])->name('activar');
```

por:

```php
    Route::post('/{plantilla}/activar', [PlantillaChecklistController::class, 'activar'])->name('activar');
    Route::post('/{plantilla}/duplicar', [PlantillaChecklistController::class, 'duplicar'])->name('duplicar');
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test`
Expected: pasan todas, 385.

- [ ] **Paso 5: Comitear**

```bash
git add tests/Feature/Viabilizacion/PlantillasDuplicarApiTest.php app/Services/Viabilizacion/PlantillaService.php app/Http/Controllers/PlantillaChecklistController.php routes/viabilizacion.php
```

```bash
git commit -m "feat: duplicate a checklist template, optionally as a simple one"
```

---

### Tarea 5: El rollback no pierde lo editado en la plantilla SGR

**Files:**
- Test: `tests/Feature/Schema/PlantillasChecklistTest.php`
- Modify: `database/migrations/2026_11_06_100003_create_plantillas_checklist_tables.php`

- [ ] **Paso 1: Escribir la prueba que falla**

En `tests/Feature/Schema/PlantillasChecklistTest.php`, reemplazar (1 de 1):

```php
    public function test_reintentar_la_migracion_rehace_lo_que_quedo_a_medias(): void
```

por:

```php
    public function test_el_rollback_de_plantillas_se_detiene_si_la_sgr_fue_editada(): void
    {
        $this->migracionExpedientes()->down();
        $this->migracionVersiones()->down();
        $cambios = [
            ['nombre' => 'SGR editada'], ['alcance' => 'general'], ['todas_fuentes' => true], ['activo' => false],
        ];
        foreach ($cambios as $cambio) {
            $original = (array) DB::table('plantillas_checklist')->where('clave', 'sgr')->first();
            DB::table('plantillas_checklist')->where('clave', 'sgr')->update($cambio);

            try {
                $this->migracionPlantillas()->down();
                $this->fail('El rollback debía detenerse con ' . json_encode($cambio));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('la plantilla SGR fue editada', $e->getMessage());
            }
            DB::table('plantillas_checklist')->where('clave', 'sgr')->update(array_intersect_key($original, $cambio));
        }

        $sgr = PlantillaChecklist::sgr()->id;
        DB::table('plantilla_sectores')->insert(['plantilla_id' => $sgr, 'sector_id' => DB::table('sectores')->insertGetId(['nombre' => 'Agua', 'activo' => true])]);
        $this->expectException(RuntimeException::class);
        $this->migracionPlantillas()->down();
    }

    public function test_reintentar_la_migracion_rehace_lo_que_quedo_a_medias(): void
```

- [ ] **Paso 2: Comprobar que falla**

Run: `php artisan test tests/Feature/Schema/PlantillasChecklistTest.php`
Expected: FALLA `test_el_rollback_de_plantillas_se_detiene_si_la_sgr_fue_editada`: hoy el rollback borra las tablas.

- [ ] **Paso 3: Implementar**

En `database/migrations/2026_11_06_100003_create_plantillas_checklist_tables.php`, reemplazar (1 de 4):

```php
return new class extends Migration
{
    public function up(): void
```

por:

```php
return new class extends Migration
{
    private const NOMBRE_SGR = 'SGR';
    private const DESCRIPCION_SGR = 'Orientaciones Transitorias + Acuerdo 12 de 2024';

    public function up(): void
```

En `database/migrations/2026_11_06_100003_create_plantillas_checklist_tables.php`, reemplazar (2 de 4):

```php
                'clave' => 'sgr',
                'nombre' => 'SGR',
                'descripcion' => 'Orientaciones Transitorias + Acuerdo 12 de 2024',
```

por:

```php
                'clave' => 'sgr',
                'nombre' => self::NOMBRE_SGR,
                'descripcion' => self::DESCRIPCION_SGR,
```

En `database/migrations/2026_11_06_100003_create_plantillas_checklist_tables.php`, reemplazar (3 de 4):

```php
        // Sin esto, se borrarían las asociaciones antes de que falle la clave foránea de las versiones.
```

por:

```php
        if ($this->sgrEditada()) {
            throw new RuntimeException('No se puede revertir: la plantilla SGR fue editada y esos cambios se perderían. Restaure un respaldo.');
        }

        // Sin esto, se borrarían las asociaciones antes de que falle la clave foránea de las versiones.
```

En `database/migrations/2026_11_06_100003_create_plantillas_checklist_tables.php`, reemplazar (4 de 4):

```php
    private function eliminarTablas(): void
```

por:

```php
    /** La plantilla SGR ya no es la que creó up(): sus datos o asociaciones cambiaron. */
    private function sgrEditada(): bool
    {
        $sgr = Schema::hasTable('plantillas_checklist') ? DB::table('plantillas_checklist')->where('clave', 'sgr')->first() : null;
        if (! $sgr) {
            return false;
        }

        $fuentes = DB::table('plantilla_fuentes')
            ->join('fuentes_financiacion', 'fuentes_financiacion.id', 'plantilla_fuentes.fuente_id')
            ->where('plantilla_id', $sgr->id)
            ->pluck('fuentes_financiacion.clave')
            ->all();

        return $sgr->nombre !== self::NOMBRE_SGR
            || $sgr->descripcion !== self::DESCRIPCION_SGR
            || $sgr->alcance !== null
            || (bool) $sgr->todas_fuentes
            || ! (bool) $sgr->todos_sectores
            || ! (bool) $sgr->activo
            || $fuentes !== ['sgr']
            || DB::table('plantilla_sectores')->where('plantilla_id', $sgr->id)->exists();
    }

    private function eliminarTablas(): void
```

- [ ] **Paso 4: Comprobar que pasa**

Run: `php artisan test`
Expected: pasan todas, 386.

- [ ] **Paso 5: Comitear**

```bash
git add tests/Feature/Schema/PlantillasChecklistTest.php database/migrations/2026_11_06_100003_create_plantillas_checklist_tables.php
```

```bash
git commit -m "fix: stop the template rollback when the SGR template was edited"
```

---

### Tarea 6: Lógica de pantalla de la administración

Funciones puras, sin componentes todavía.

**Files:**
- Create: `resources/js/utils/plantillasChecklist.js`
- Test (create): `resources/js/utils/plantillasChecklist.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `resources/js/utils/plantillasChecklist.test.js`:

```js
import { describe, it, expect } from 'vitest';
import {
    agruparVistaPrevia,
    datosPlantilla,
    datosRequisito,
    etiquetaCategoria,
    etiquetaVersion,
    filtrarPlantillas,
    formularioPlantilla,
    formularioRequisito,
    mover,
    opcionesCatalogo,
    requisitoVacio,
    resumenVersiones,
    textoAsociaciones,
    versionDeTrabajo,
} from './plantillasChecklist';

describe('formulario de la plantilla', () => {
    const plantilla = {
        nombre: 'Educación', descripcion: null, alcance: 'especifica', todas_fuentes: false, todos_sectores: false,
        fuentes: [{ id: 4, nombre: 'Recursos propios' }], sectores: [{ id: 2, nombre: 'Educación' }],
    };

    it('lee la plantilla y la devuelve en el formato de la API', () => {
        const formulario = formularioPlantilla(plantilla);
        expect(formulario).toEqual({
            nombre: 'Educación', descripcion: '', alcance: 'especifica', todas_fuentes: false, todos_sectores: false,
            fuente_ids: [4], sector_ids: [2],
        });
        expect(datosPlantilla({ ...formulario, nombre: '  Educación  ', descripcion: ' ' })).toEqual({
            nombre: 'Educación', descripcion: null, alcance: 'especifica', todas_fuentes: false, todos_sectores: false,
            fuente_ids: [4], sector_ids: [2],
        });
    });

    it('con "todas" envía la lista vacía', () => {
        const datos = datosPlantilla({ ...formularioPlantilla(plantilla), todas_fuentes: true, todos_sectores: true, alcance: '' });
        expect(datos.fuente_ids).toEqual([]);
        expect(datos.sector_ids).toEqual([]);
        expect(datos.alcance).toBeNull();
    });

    it('ofrece los activos y conserva los inactivos ya elegidos', () => {
        const items = [{ id: 1, activo: true }, { id: 2, activo: false }, { id: 3, activo: false }];
        expect(opcionesCatalogo(items, [2]).map((i) => i.id)).toEqual([1, 2]);
    });

    it('resume las asociaciones', () => {
        expect(textoAsociaciones(true, [], 'Todas las fuentes')).toBe('Todas las fuentes');
        expect(textoAsociaciones(false, [{ nombre: 'SGR' }, { nombre: 'PGN' }], 'x')).toBe('SGR, PGN');
        expect(textoAsociaciones(false, [], 'x')).toBe('—');
    });
});

describe('formulario del requisito', () => {
    it('un requisito nuevo del SGR nace con aprobación y sección A; uno simple, sin ella', () => {
        expect(requisitoVacio('sgr')).toMatchObject({ seccion: 'A', fuente: 'a12', condicion: 'siempre', requiere_aprobacion_no_aplica: true });
        expect(requisitoVacio('simple')).toMatchObject({ seccion: '', caracter: 'obligatorio', requiere_aprobacion_no_aplica: false });
    });

    it('en el motor simple solo envía la condición si es condicionado', () => {
        const formulario = { ...formularioRequisito({ texto: ' CDP ', categoria: 'financiero' }, 'simple'), condicion_descripcion: 'Si compra predios' };
        expect(datosRequisito(formulario, 'simple')).toMatchObject({ texto: 'CDP', categoria: 'financiero', condicion_descripcion: null, descripcion: null });
        expect(datosRequisito({ ...formulario, caracter: 'condicionado' }, 'simple').condicion_descripcion).toBe('Si compra predios');
    });

    it('en el motor sgr el sector y el tipo solo van en la sección C', () => {
        const base = formularioRequisito({ texto: 'x', seccion: 'A', sector_id: 3, tipo_id: 9, condicion: 'fase=3' }, 'sgr');
        expect(datosRequisito(base, 'sgr')).toMatchObject({ sector_id: null, tipo_id: null, condicion: 'fase=3' });
        expect(datosRequisito({ ...base, seccion: 'C' }, 'sgr')).toMatchObject({ sector_id: 3, tipo_id: 9 });
        expect(datosRequisito(base, 'sgr')).not.toHaveProperty('caracter');
    });
});

describe('vista previa', () => {
    const r = (id, extra) => ({ id, orden: id, activo: true, texto: `R${id}`, ...extra });

    it('agrupa el SGR por sección en el orden del expediente y omite los inactivos', () => {
        const grupos = agruparVistaPrevia([r(1, { seccion: 'C' }), r(2, { seccion: 'A' }), r(3, { seccion: 'A', activo: false })], 'sgr');
        expect(grupos.map((g) => g.clave)).toEqual(['A', 'C']);
        expect(grupos[0].requisitos.map((x) => x.id)).toEqual([2]);
    });

    it('agrupa una plantilla simple por categoría y deja al final lo que no tiene', () => {
        const grupos = agruparVistaPrevia([r(2, { categoria: null }), r(1, { categoria: 'juridico' }), r(3, { categoria: 'tecnico' })], 'simple');
        expect(grupos.map((g) => g.titulo)).toEqual(['Técnico', 'Jurídico', 'Sin categoría']);
    });
});

describe('utilidades', () => {
    it('mueve dentro de los límites', () => {
        expect(mover([1, 2, 3], 1, -1)).toEqual([2, 1, 3]);
        expect(mover([1, 2, 3], 2, 1)).toEqual([1, 2, 3]);
    });

    it('nombra las versiones y elige la de trabajo', () => {
        const vigente = { id: 1, numero: 1, estado: 'publicada', vigente: true };
        const borrador = { id: 2, numero: 2, estado: 'borrador', vigente: false };
        expect(etiquetaVersion(vigente)).toBe('v1 · vigente');
        expect(etiquetaVersion(borrador)).toBe('v2 · borrador');
        expect(etiquetaVersion(null)).toBe('—');
        expect(versionDeTrabajo({ versiones: [borrador, vigente] })).toBe(borrador);
        expect(versionDeTrabajo({ versiones: [vigente] })).toBe(vigente);
        expect(versionDeTrabajo({ versiones: [] })).toBeNull();
        expect(resumenVersiones({ vigente: { numero: 1 }, borrador: { numero: 2 } })).toBe('v1 vigente · v2 en borrador');
        expect(resumenVersiones({ vigente: null, borrador: { numero: 1 } })).toBe('Sin versión publicada · v1 en borrador');
        expect(resumenVersiones({ vigente: { numero: 3 }, borrador: null })).toBe('v3 vigente');
    });

    it('etiqueta categorías y filtra plantillas activas', () => {
        expect(etiquetaCategoria('juridico')).toBe('Jurídico');
        expect(etiquetaCategoria('nada')).toBe('');
        expect(filtrarPlantillas([{ activo: true }, { activo: false }], { soloActivas: true })).toHaveLength(1);
    });
});
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npm test`
Expected: FALLA `plantillasChecklist.test.js` porque no existe `plantillasChecklist.js`.

- [ ] **Paso 3: Implementar**

Crear `resources/js/utils/plantillasChecklist.js`:

```js
// Lógica de presentación de la administración de plantillas de checklist.
// Funciones puras, probadas en plantillasChecklist.test.js. Las reglas las valida el backend.

import { SECCIONES } from './expedienteChecklist';

export const CATEGORIAS = [
    ['tecnico', 'Técnico'],
    ['juridico', 'Jurídico'],
    ['financiero', 'Financiero'],
    ['ambiental', 'Ambiental'],
    ['social', 'Social'],
    ['administrativo', 'Administrativo'],
    ['otro', 'Otro'],
];

export const TIPOS_REQUISITO = [
    ['documental', 'Documental'],
    ['validacion', 'Validación'],
    ['condicion', 'Condición'],
];

export const CARACTERES = [
    ['obligatorio', 'Obligatorio'],
    ['opcional', 'Opcional'],
    ['condicionado', 'Condicionado'],
];

export const ALCANCES = [
    ['general', 'General'],
    ['especifica', 'Específica'],
];

const etiqueta = (lista, valor) => lista.find(([v]) => v === valor)?.[1] ?? '';

export const etiquetaCategoria = (valor) => etiqueta(CATEGORIAS, valor);
export const etiquetaTipo = (valor) => etiqueta(TIPOS_REQUISITO, valor);
export const etiquetaCaracter = (valor) => etiqueta(CARACTERES, valor);
export const etiquetaAlcance = (valor) => etiqueta(ALCANCES, valor);

/** "Todas las fuentes" o los nombres separados por coma. */
export const textoAsociaciones = (todas, items, textoTodas) =>
    todas ? textoTodas : (items.length ? items.map((i) => i.nombre).join(', ') : '—');

export const PLANTILLA_VACIA = {
    nombre: '',
    descripcion: '',
    alcance: '',
    todas_fuentes: false,
    todos_sectores: true,
    fuente_ids: [],
    sector_ids: [],
};

export const formularioPlantilla = (plantilla) => ({
    nombre: plantilla.nombre ?? '',
    descripcion: plantilla.descripcion ?? '',
    alcance: plantilla.alcance ?? '',
    todas_fuentes: Boolean(plantilla.todas_fuentes),
    todos_sectores: Boolean(plantilla.todos_sectores),
    fuente_ids: (plantilla.fuentes ?? []).map((f) => f.id),
    sector_ids: (plantilla.sectores ?? []).map((s) => s.id),
});

/** Lo que espera la API: con "todas", la lista se envía vacía. */
export const datosPlantilla = (formulario) => ({
    nombre: formulario.nombre.trim(),
    descripcion: formulario.descripcion.trim() || null,
    alcance: formulario.alcance || null,
    todas_fuentes: formulario.todas_fuentes,
    todos_sectores: formulario.todos_sectores,
    fuente_ids: formulario.todas_fuentes ? [] : formulario.fuente_ids,
    sector_ids: formulario.todos_sectores ? [] : formulario.sector_ids,
});

/** Opciones de un catálogo: las activas y las que la plantilla ya tiene. */
export const opcionesCatalogo = (items, elegidos) => items.filter((i) => i.activo || elegidos.includes(i.id));

const CAMPOS_TEXTO = [
    'texto', 'descripcion', 'categoria', 'tipo_requisito', 'cargo_cumplimiento', 'cargo_revision',
    'soporte_requerido', 'referencia_normativa', 'nota', 'condicion_descripcion', 'codigo_comun',
    'clave', 'codigo', 'subtitulo', 'seccion', 'fuente', 'condicion',
];

export const requisitoVacio = (motor) => ({
    ...Object.fromEntries(CAMPOS_TEXTO.map((c) => [c, ''])),
    caracter: 'obligatorio',
    requiere_aprobacion_no_aplica: motor === 'sgr',
    activo: true,
    sector_id: '',
    tipo_id: '',
    ...(motor === 'sgr' ? { seccion: 'A', fuente: 'a12', condicion: 'siempre' } : {}),
});

export const formularioRequisito = (requisito, motor) => ({
    ...requisitoVacio(motor),
    ...Object.fromEntries(CAMPOS_TEXTO.map((c) => [c, requisito[c] ?? ''])),
    caracter: requisito.caracter ?? 'obligatorio',
    requiere_aprobacion_no_aplica: Boolean(requisito.requiere_aprobacion_no_aplica),
    activo: requisito.activo ?? true,
    sector_id: requisito.sector_id ?? '',
    tipo_id: requisito.tipo_id ?? '',
});

/** Lo que espera la API según el motor. Los textos vacíos viajan como null. */
export const datosRequisito = (formulario, motor) => {
    const texto = (campo) => (String(formulario[campo] ?? '').trim() || null);
    const comunes = {
        texto: texto('texto'),
        descripcion: texto('descripcion'),
        categoria: texto('categoria'),
        tipo_requisito: texto('tipo_requisito'),
        cargo_cumplimiento: texto('cargo_cumplimiento'),
        cargo_revision: texto('cargo_revision'),
        soporte_requerido: texto('soporte_requerido'),
        referencia_normativa: texto('referencia_normativa'),
        nota: texto('nota'),
        requiere_aprobacion_no_aplica: formulario.requiere_aprobacion_no_aplica,
        activo: formulario.activo,
        codigo_comun: texto('codigo_comun'),
    };

    if (motor === 'sgr') {
        return {
            ...comunes,
            clave: texto('clave'),
            seccion: formulario.seccion,
            fuente: formulario.fuente,
            sector_id: formulario.seccion === 'C' ? (Number(formulario.sector_id) || null) : null,
            tipo_id: formulario.seccion === 'C' ? (Number(formulario.tipo_id) || null) : null,
            subtitulo: texto('subtitulo'),
            codigo: texto('codigo'),
            condicion: texto('condicion'),
        };
    }

    return {
        ...comunes,
        clave: texto('clave'),
        caracter: formulario.caracter,
        condicion_descripcion: formulario.caracter === 'condicionado' ? texto('condicion_descripcion') : null,
        codigo: texto('codigo'),
    };
};

/**
 * Vista previa: los requisitos activos como los verá el usuario. El SGR por sección,
 * en el orden del expediente; una plantilla simple por categoría.
 */
export const agruparVistaPrevia = (requisitos, motor) => {
    const activos = requisitos.filter((r) => r.activo).sort((a, b) => a.orden - b.orden);

    if (motor === 'sgr') {
        return ['A', 'B', 'D', 'C']
            .map((clave) => ({
                clave,
                titulo: `${SECCIONES[clave].num} ${SECCIONES[clave].titulo}`,
                requisitos: activos.filter((r) => r.seccion === clave),
            }))
            .filter((g) => g.requisitos.length);
    }

    const grupos = [...CATEGORIAS, [null, 'Sin categoría']]
        .map(([clave, titulo]) => ({ clave: clave ?? 'sin', titulo, requisitos: activos.filter((r) => (r.categoria ?? null) === clave) }));
    return grupos.filter((g) => g.requisitos.length);
};

/** Mueve un elemento una posición arriba (-1) o abajo (+1). Fuera de rango, no cambia nada. */
export const mover = (lista, indice, delta) => {
    const destino = indice + delta;
    if (destino < 0 || destino >= lista.length) return lista;
    const copia = [...lista];
    [copia[indice], copia[destino]] = [copia[destino], copia[indice]];
    return copia;
};

export const etiquetaVersion = (version) => {
    if (!version) return '—';
    if (version.estado === 'borrador') return `v${version.numero} · borrador`;
    return version.vigente ? `v${version.numero} · vigente` : `v${version.numero}`;
};

/** "v1 vigente · v2 en borrador" o "Sin versión publicada · v1 en borrador". */
export const resumenVersiones = (plantilla) => [
    plantilla.vigente ? `v${plantilla.vigente.numero} vigente` : 'Sin versión publicada',
    plantilla.borrador ? `v${plantilla.borrador.numero} en borrador` : null,
].filter(Boolean).join(' · ');

/** La versión que se edita: el borrador si existe; si no, la vigente en solo lectura. */
export const versionDeTrabajo = (plantilla) => {
    const versiones = plantilla?.versiones ?? [];
    return versiones.find((v) => v.estado === 'borrador') ?? versiones.find((v) => v.vigente) ?? null;
};

export const filtrarPlantillas = (plantillas, { soloActivas }) =>
    plantillas.filter((p) => !soloActivas || p.activo);
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npm test`
Expected: pasan todas, 152.

- [ ] **Paso 5: Comitear**

```bash
git add resources/js/utils/plantillasChecklist.js resources/js/utils/plantillasChecklist.test.js
```

```bash
git commit -m "feat: add template administration screen logic"
```

---

### Tarea 7: Navegación solo para Administrador

`app.jsx` importa los dos componentes de la tarea 8: hasta entonces la compilación falla, por eso esta tarea no compila y se prueba solo con `npm test`.

**Files:**
- Test: `resources/js/utils/navegacion.test.js`
- Modify: `resources/js/utils/navegacion.js`
- Modify: `resources/js/utils/iconosNavegacion.js`
- Modify: `resources/js/components/AppShell.jsx`
- Modify: `resources/js/components/PaletaComandos.jsx`
- Modify: `resources/js/app.jsx`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/navegacion.test.js`, reemplazar (1 de 2):

```js
import { DESTINOS, buscarEnPaleta, destinoActivo, seccionDesdeSlug, tituloDeRuta } from './navegacion';
```

por:

```js
import { DESTINOS, buscarEnPaleta, destinoActivo, destinosPara, seccionDesdeSlug, tituloDeRuta } from './navegacion';
```

En `resources/js/utils/navegacion.test.js`, reemplazar (2 de 2):

```js
describe('buscarEnPaleta', () => {
```

por:

```js
describe('destinosPara', () => {
    it('las plantillas de checklist solo se muestran a un Administrador', () => {
        const ids = (usuario) => destinosPara(usuario).map((d) => d.id);

        expect(ids({ rol: 'Administrador' })).toContain('plantillas');
        expect(ids({ rol: 'Gestor' })).not.toContain('plantillas');
        expect(ids(null)).not.toContain('plantillas');
        expect(ids({ rol: 'Gestor' })).toContain('tablero');
    });

    it('la ruta de plantillas y su detalle marcan el destino', () => {
        expect(destinoActivo('/plantillas', '')).toBe('plantillas');
        expect(destinoActivo('/plantillas/7', '')).toBe('plantillas');
        expect(tituloDeRuta('/plantillas/7', '')).toBe('Plantillas de checklist');
    });
});

describe('buscarEnPaleta', () => {
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npm test`
Expected: FALLAN las pruebas nuevas de `navegacion.test.js`: no existe `destinosPara` ni el destino `plantillas`.

- [ ] **Paso 3: Implementar**

En `resources/js/utils/navegacion.js`, reemplazar (1 de 2):

```js
    ...SECCIONES_PARAMETROS.filter((s) => s.grupo === GRUPO.CATALOGOS).map((s) => ({ ...s, id: s.slug, ruta: `/parametros/${s.slug}` })),
];
```

por:

```js
    ...SECCIONES_PARAMETROS.filter((s) => s.grupo === GRUPO.CATALOGOS).map((s) => ({ ...s, id: s.slug, ruta: `/parametros/${s.slug}` })),
    { id: 'plantillas', titulo: 'Plantillas de checklist', ruta: '/plantillas', grupo: GRUPO.CATALOGOS, icono: 'faListCheck', palabras: 'requisitos checklist fuentes sectores versiones catalogo', soloRol: 'Administrador' },
];

/** Los destinos que puede abrir el usuario: algunos son solo de un rol. */
export const destinosPara = (usuario) => DESTINOS.filter((d) => !d.soloRol || d.soloRol === usuario?.rol);
```

En `resources/js/utils/navegacion.js`, reemplazar (2 de 2):

```js
    if (pathname.startsWith('/viabilizacion/')) return 'expedientes';
```

por:

```js
    if (pathname.startsWith('/viabilizacion/')) return 'expedientes';
    if (pathname === '/plantillas' || pathname.startsWith('/plantillas/')) return 'plantillas';
```

En `resources/js/utils/iconosNavegacion.js`, reemplazar (1 de 2):

```js
    faFlag, faFolderOpen, faGaugeHigh, faLandmark, faLayerGroup, faMapLocationDot, faTags, faUsers,
} from '@fortawesome/free-solid-svg-icons';
```

por:

```js
    faFlag, faFolderOpen, faGaugeHigh, faLandmark, faLayerGroup, faListCheck, faMapLocationDot, faTags, faUsers,
} from '@fortawesome/free-solid-svg-icons';
```

En `resources/js/utils/iconosNavegacion.js`, reemplazar (2 de 2):

```js
    faFlag, faFolderOpen, faGaugeHigh, faLandmark, faLayerGroup, faMapLocationDot, faTags, faUsers,
};
```

por:

```js
    faFlag, faFolderOpen, faGaugeHigh, faLandmark, faLayerGroup, faListCheck, faMapLocationDot, faTags, faUsers,
};
```

En `resources/js/components/AppShell.jsx`, reemplazar (1 de 6):

```jsx
import { DESTINOS, GRUPO, destinoActivo, tituloDeRuta } from '../utils/navegacion';
```

por:

```jsx
import { DESTINOS, GRUPO, destinoActivo, destinosPara, tituloDeRuta } from '../utils/navegacion';
```

En `resources/js/components/AppShell.jsx`, reemplazar (2 de 6):

```jsx
const ListaDestinos = ({ activo, plegado, onNavegar }) => GRUPOS.map((grupo) => (
    <div className="gp-nav-grupo" key={grupo}>
        <div className="gp-nav-titulo gp-etiqueta">{grupo}</div>
        {DESTINOS.filter((d) => d.grupo === grupo).map((destino) => (
```

por:

```jsx
const ListaDestinos = ({ destinos, activo, plegado, onNavegar }) => GRUPOS.map((grupo) => (
    <div className="gp-nav-grupo" key={grupo}>
        <div className="gp-nav-titulo gp-etiqueta">{grupo}</div>
        {destinos.filter((d) => d.grupo === grupo).map((destino) => (
```

En `resources/js/components/AppShell.jsx`, reemplazar (3 de 6):

```jsx
    const enBarraInferior = IDS_BARRA_INFERIOR.includes(activo);
```

por:

```jsx
    const enBarraInferior = IDS_BARRA_INFERIOR.includes(activo);
    const destinos = destinosPara(user);
```

En `resources/js/components/AppShell.jsx`, reemplazar (4 de 6):

```jsx
                    <ListaDestinos activo={activo} plegado={plegado} />
```

por:

```jsx
                    <ListaDestinos destinos={destinos} activo={activo} plegado={plegado} />
```

En `resources/js/components/AppShell.jsx`, reemplazar (5 de 6):

```jsx
                        <ListaDestinos activo={activo} onNavegar={() => setMasAbierto(false)} />
```

por:

```jsx
                        <ListaDestinos destinos={destinos} activo={activo} onNavegar={() => setMasAbierto(false)} />
```

En `resources/js/components/AppShell.jsx`, reemplazar (6 de 6):

```jsx
            {paletaAbierta && <PaletaComandos onCerrar={() => setPaletaAbierta(false)} />}
```

por:

```jsx
            {paletaAbierta && <PaletaComandos destinos={destinos} onCerrar={() => setPaletaAbierta(false)} />}
```

En `resources/js/components/PaletaComandos.jsx`, reemplazar (1 de 3):

```jsx
import { DESTINOS, buscarEnPaleta } from '../utils/navegacion';
```

por:

```jsx
import { buscarEnPaleta } from '../utils/navegacion';
```

En `resources/js/components/PaletaComandos.jsx`, reemplazar (2 de 3):

```jsx
const PaletaComandos = ({ onCerrar }) => {
```

por:

```jsx
const PaletaComandos = ({ destinos, onCerrar }) => {
```

En `resources/js/components/PaletaComandos.jsx`, reemplazar (3 de 3):

```jsx
    const resultados = useMemo(() => buscarEnPaleta(consulta, DESTINOS, proyectos), [consulta, proyectos]);
```

por:

```jsx
    const resultados = useMemo(() => buscarEnPaleta(consulta, destinos, proyectos), [consulta, destinos, proyectos]);
```

En `resources/js/app.jsx`, reemplazar (1 de 2):

```jsx
import ExpedienteViabilizacion from './components/ExpedienteViabilizacion';
```

por:

```jsx
import ExpedienteViabilizacion from './components/ExpedienteViabilizacion';
import PlantillasChecklist from './components/PlantillasChecklist';
import PlantillaDetalle from './components/PlantillaDetalle';
```

En `resources/js/app.jsx`, reemplazar (2 de 2):

```jsx
          <Route path="/viabilizacion/:proyectoId" element={<ExpedienteViabilizacion user={user} />} />
```

por:

```jsx
          <Route path="/viabilizacion/:proyectoId" element={<ExpedienteViabilizacion user={user} />} />
          {/* Solo Administrador: el backend también lo exige. */}
          <Route path="/plantillas" element={user?.rol === 'Administrador' ? <PlantillasChecklist /> : <Navigate to={INICIO} replace />} />
          <Route path="/plantillas/:plantillaId" element={user?.rol === 'Administrador' ? <PlantillaDetalle /> : <Navigate to={INICIO} replace />} />
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npm test`
Expected: pasan todas, 154.

- [ ] **Paso 5: Comitear**

```bash
git add resources/js/utils/navegacion.test.js resources/js/utils/navegacion.js resources/js/utils/iconosNavegacion.js resources/js/components/AppShell.jsx resources/js/components/PaletaComandos.jsx resources/js/app.jsx
```

```bash
git commit -m "feat: show checklist templates in the navigation for administrators"
```

---

### Tarea 8: Pantallas de plantillas

Sin pruebas automáticas de componentes en el repositorio: se comprueban en el navegador en la tarea 9.

**Files:**
- Create: `resources/js/components/PlantillasChecklist.jsx`
- Create: `resources/js/components/PlantillaDetalle.jsx`
- Create: `resources/js/components/PlantillaRequisitos.jsx`
- Create: `resources/js/components/PlantillaVistaPrevia.jsx`
- Create: `resources/js/components/PlantillaVersiones.jsx`
- Create: `resources/css/Plantillas.css`
- Generated: `public/js/app.js`, `public/js/app.js.map`, `public/mix-manifest.json`

- [ ] **Paso 1: Crear los archivos**

Crear `resources/js/components/PlantillasChecklist.jsx`:

```jsx
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Swal from 'sweetalert2';
import { faCopy, faPlus, faPowerOff } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { mensajeError } from '../utils/expedienteChecklist';
import { filtrarPlantillas, resumenVersiones, textoAsociaciones } from '../utils/plantillasChecklist';
import '../../css/Plantillas.css';

const avisarError = (titulo, error) => Swal.fire({ icon: 'error', title: titulo, text: mensajeError(error) });

/** Lista de plantillas de checklist (solo Administrador). */
const PlantillasChecklist = () => {
    const navigate = useNavigate();
    const [plantillas, setPlantillas] = useState(null);
    const [buscar, setBuscar] = useState('');
    const [soloActivas, setSoloActivas] = useState(false);
    const [error, setError] = useState(null);

    const cargar = useCallback(async (texto) => {
        try {
            const { data } = await axios.get('/plantillas', { params: texto ? { buscar: texto } : {} });
            setPlantillas(data);
            setError(null);
        } catch (e) {
            setError(mensajeError(e));
        }
    }, []);

    // La búsqueda espera a que se deje de escribir.
    useEffect(() => {
        const temporizador = setTimeout(() => cargar(buscar.trim()), 300);
        return () => clearTimeout(temporizador);
    }, [buscar, cargar]);

    const visibles = useMemo(() => filtrarPlantillas(plantillas ?? [], { soloActivas }), [plantillas, soloActivas]);

    const pedirNombre = (titulo, texto, valor = '') => Swal.fire({
        title: titulo,
        text: texto,
        input: 'text',
        inputValue: valor,
        inputLabel: 'Nombre',
        inputAttributes: { maxlength: '199' },
        inputValidator: (nombre) => (!nombre?.trim() ? 'Escribe el nombre de la plantilla.' : undefined),
        showCancelButton: true,
        confirmButtonText: 'Crear',
        cancelButtonText: 'Cancelar',
    });

    const crear = async () => {
        const { isConfirmed, value } = await pedirNombre(
            'Nueva plantilla',
            'Nace para todas las fuentes y sectores, con su primera versión en borrador. Ajústalo en el detalle.'
        );
        if (!isConfirmed) return;
        try {
            const { data } = await axios.post('/plantillas', { nombre: value.trim(), todas_fuentes: true, todos_sectores: true });
            navigate(`/plantillas/${data.id}`);
        } catch (e) {
            avisarError('No se pudo crear la plantilla', e);
        }
    };

    const duplicar = async (plantilla) => {
        const { isConfirmed, value } = await Swal.fire({
            title: `Duplicar "${plantilla.nombre}"`,
            html: plantilla.motor === 'sgr'
                ? 'Copia los requisitos activos de la versión vigente en una plantilla nueva, en borrador. Puedes quitar los que no sirvan antes de publicar.'
                : 'Copia los requisitos activos de la versión vigente en una plantilla nueva, en borrador.',
            input: 'text',
            inputValue: `Copia de ${plantilla.nombre}`,
            inputLabel: 'Nombre de la copia',
            inputValidator: (nombre) => (!nombre?.trim() ? 'Escribe el nombre de la plantilla.' : undefined),
            showCancelButton: true,
            confirmButtonText: 'Duplicar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;

        let motor = plantilla.motor;
        if (plantilla.motor === 'sgr') {
            const eleccion = await Swal.fire({
                title: '¿Cómo deciden si aplica cada requisito?',
                text: 'Simple: una persona decide los condicionados. SGR: el evaluador automático de regalías.',
                input: 'select',
                inputOptions: { simple: 'Simple (recomendado para otras fuentes)', sgr: 'SGR (evaluador automático)' },
                inputValue: 'simple',
                showCancelButton: true,
                confirmButtonText: 'Duplicar',
                cancelButtonText: 'Cancelar',
            });
            if (!eleccion.isConfirmed) return;
            motor = eleccion.value;
        }

        try {
            const { data } = await axios.post(`/plantillas/${plantilla.id}/duplicar`, { nombre: value.trim(), motor });
            navigate(`/plantillas/${data.id}`);
        } catch (e) {
            avisarError('No se pudo duplicar la plantilla', e);
        }
    };

    const alternarActiva = async (plantilla) => {
        if (plantilla.activo) {
            const { isConfirmed } = await Swal.fire({
                icon: 'warning',
                title: `¿Desactivar "${plantilla.nombre}"?`,
                text: 'No se ofrecerá en expedientes nuevos. Los que ya la usan no cambian.',
                showCancelButton: true,
                confirmButtonText: 'Sí, desactivar',
                cancelButtonText: 'Cancelar',
            });
            if (!isConfirmed) return;
        }
        try {
            await axios.post(`/plantillas/${plantilla.id}/activar`, { activo: !plantilla.activo });
            await cargar(buscar.trim());
        } catch (e) {
            avisarError('No se pudo cambiar el estado', e);
        }
    };

    return (
        <div className="pl-pagina">
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Catálogos</span>
                <h1>Plantillas de checklist</h1>
                <p>Conjuntos de requisitos que se aplican a los expedientes según su fuente de financiación y su sector.</p>
            </header>

            <div className="pl-barra">
                <input
                    type="search"
                    className="pl-buscar"
                    placeholder="Buscar por nombre o descripción"
                    aria-label="Buscar plantillas"
                    value={buscar}
                    onChange={(e) => setBuscar(e.target.value)}
                />
                <label className="pl-check">
                    <input type="checkbox" checked={soloActivas} onChange={(e) => setSoloActivas(e.target.checked)} /> Solo activas
                </label>
                <button type="button" className="gp-boton gp-boton-primario" onClick={crear}>
                    <FontAwesomeIcon icon={faPlus} /> Nueva plantilla
                </button>
            </div>

            {error && <p className="pl-error" role="alert">{error}</p>}
            {plantillas === null && !error && <p className="gp-vacio">Cargando plantillas…</p>}
            {plantillas !== null && visibles.length === 0 && <p className="gp-vacio">No hay plantillas que coincidan.</p>}

            <ul className="pl-lista">
                {visibles.map((p) => (
                    <li key={p.id} className={`gp-panel pl-tarjeta${p.activo ? '' : ' inactiva'}`}>
                        <div className="pl-tarjeta-cuerpo">
                            <div className="pl-tarjeta-titulo">
                                <Link to={`/plantillas/${p.id}`}>{p.nombre}</Link>
                                {p.es_sgr && <span className="pl-insignia">SGR</span>}
                                <span className="pl-insignia pl-insignia-suave">{p.motor === 'sgr' ? 'Evaluador SGR' : 'Simple'}</span>
                                {!p.activo && <span className="pl-insignia pl-insignia-alerta">Inactiva</span>}
                            </div>
                            {p.descripcion && <p className="pl-tarjeta-descripcion">{p.descripcion}</p>}
                            <dl className="pl-datos">
                                <div><dt>Versión</dt><dd>{resumenVersiones(p)}</dd></div>
                                <div><dt>Requisitos</dt><dd>{p.vigente?.requisitos ?? p.borrador?.requisitos ?? 0}</dd></div>
                                <div><dt>Fuentes</dt><dd>{textoAsociaciones(p.todas_fuentes, p.fuentes, 'Todas')}</dd></div>
                                <div><dt>Sectores</dt><dd>{textoAsociaciones(p.todos_sectores, p.sectores, 'Todos')}</dd></div>
                            </dl>
                        </div>
                        <div className="pl-tarjeta-acciones">
                            <button type="button" className="gp-boton" onClick={() => duplicar(p)} disabled={!p.vigente}
                                title={p.vigente ? undefined : 'Publica una versión antes de duplicar'}>
                                <FontAwesomeIcon icon={faCopy} /> Duplicar
                            </button>
                            {!p.es_sgr && (
                                <button type="button" className="gp-boton" onClick={() => alternarActiva(p)}>
                                    <FontAwesomeIcon icon={faPowerOff} /> {p.activo ? 'Desactivar' : 'Activar'}
                                </button>
                            )}
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
};

export default PlantillasChecklist;
```

Crear `resources/js/components/PlantillaDetalle.jsx`:

```jsx
import React, { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import Swal from 'sweetalert2';
import { faArrowLeft, faFloppyDisk } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { alternarId, mensajeError } from '../utils/expedienteChecklist';
import { ALCANCES, datosPlantilla, formularioPlantilla, opcionesCatalogo, resumenVersiones, versionDeTrabajo } from '../utils/plantillasChecklist';
import PlantillaRequisitos from './PlantillaRequisitos';
import PlantillaVistaPrevia from './PlantillaVistaPrevia';
import PlantillaVersiones from './PlantillaVersiones';
import '../../css/Plantillas.css';

const PESTANAS = [
    ['general', 'General y asociaciones'],
    ['requisitos', 'Requisitos'],
    ['vista', 'Vista previa'],
    ['versiones', 'Versiones'],
];

const avisarError = (titulo, error) => Swal.fire({ icon: 'error', title: titulo, text: mensajeError(error) });

const ListaChequeo = ({ titulo, todas, textoTodas, onTodas, items, elegidos, onAlternar }) => (
    <fieldset className="pl-asociacion">
        <legend>{titulo}</legend>
        <label className="pl-check">
            <input type="checkbox" checked={todas} onChange={(e) => onTodas(e.target.checked)} /> {textoTodas}
        </label>
        {!todas && (
            <div className="pl-opciones">
                {opcionesCatalogo(items, elegidos).map((item) => (
                    <label key={item.id} className="pl-check">
                        <input type="checkbox" checked={elegidos.includes(item.id)} onChange={() => onAlternar(item.id)} />
                        {item.nombre}{!item.activo && ' (inactiva)'}
                    </label>
                ))}
            </div>
        )}
    </fieldset>
);

/** Detalle de una plantilla: datos, asociaciones, requisitos, vista previa y versiones. */
const PlantillaDetalle = () => {
    const { plantillaId } = useParams();
    const [plantilla, setPlantilla] = useState(null);
    const [formulario, setFormulario] = useState(null);
    const [fuentes, setFuentes] = useState([]);
    const [sectores, setSectores] = useState([]);
    const [requisitos, setRequisitos] = useState([]);
    const [pestana, setPestana] = useState('general');
    const [guardando, setGuardando] = useState(false);
    const [error, setError] = useState(null);

    const version = versionDeTrabajo(plantilla);

    const cargar = useCallback(async () => {
        try {
            const { data } = await axios.get(`/plantillas/${plantillaId}`);
            setPlantilla(data);
            setFormulario(formularioPlantilla(data));
            const trabajo = versionDeTrabajo(data);
            setRequisitos(trabajo ? (await axios.get(`/catalogo-requisitos/versiones/${trabajo.id}/requisitos`)).data : []);
        } catch (e) {
            setError(e?.response?.status === 404 ? 'La plantilla no existe.' : mensajeError(e));
        }
    }, [plantillaId]);

    useEffect(() => { cargar(); }, [cargar]);

    useEffect(() => {
        let vigente = true;
        Promise.all([axios.get('/fuentesFinanciacion'), axios.get('/sectores')])
            .then(([f, s]) => { if (vigente) { setFuentes(f.data); setSectores(s.data); } })
            .catch(() => {});
        return () => { vigente = false; };
    }, []);

    const cambiar = (campo, valor) => setFormulario((f) => ({ ...f, [campo]: valor }));

    const guardar = async (e) => {
        e.preventDefault();
        setGuardando(true);
        try {
            await axios.put(`/plantillas/${plantilla.id}`, datosPlantilla(formulario));
            await cargar();
            Swal.fire({ icon: 'success', title: 'Plantilla guardada', timer: 1400, showConfirmButton: false });
        } catch (err) {
            avisarError('No se pudo guardar la plantilla', err);
        } finally {
            setGuardando(false);
        }
    };

    if (error) {
        return (
            <div className="pl-pagina">
                <p className="pl-error" role="alert">{error}</p>
                <Link to="/plantillas" className="gp-boton"><FontAwesomeIcon icon={faArrowLeft} /> Volver a plantillas</Link>
            </div>
        );
    }
    if (!plantilla || !formulario) return <p className="gp-vacio">Cargando plantilla…</p>;

    return (
        <div className="pl-pagina">
            <Link to="/plantillas" className="pl-volver"><FontAwesomeIcon icon={faArrowLeft} /> Plantillas</Link>
            <header className="gp-cabecera">
                <span className="gp-etiqueta">
                    {plantilla.motor === 'sgr' ? 'Evaluador SGR' : 'Plantilla simple'} · {resumenVersiones(plantilla)}
                </span>
                <h1>{plantilla.nombre}</h1>
                {!plantilla.activo && <p>Inactiva: no se ofrece en expedientes nuevos.</p>}
            </header>

            <div className="pl-pestanas" role="tablist">
                {PESTANAS.map(([id, titulo]) => (
                    <button key={id} type="button" role="tab" aria-selected={pestana === id}
                        className={`pl-pestana${pestana === id ? ' activa' : ''}`} onClick={() => setPestana(id)}>
                        {titulo}
                    </button>
                ))}
            </div>

            {pestana === 'general' && (
                <form className="gp-panel pl-formulario" onSubmit={guardar}>
                    <label className="pl-campo">
                        <span>Nombre</span>
                        <input value={formulario.nombre} maxLength={199} required onChange={(e) => cambiar('nombre', e.target.value)} />
                    </label>
                    <label className="pl-campo">
                        <span>Descripción</span>
                        <textarea rows={3} value={formulario.descripcion} onChange={(e) => cambiar('descripcion', e.target.value)} />
                    </label>
                    <label className="pl-campo">
                        <span>Alcance</span>
                        <select value={formulario.alcance} onChange={(e) => cambiar('alcance', e.target.value)}>
                            <option value="">Sin definir</option>
                            {ALCANCES.map(([valor, texto]) => <option key={valor} value={valor}>{texto}</option>)}
                        </select>
                    </label>
                    <ListaChequeo
                        titulo="Fuentes de financiación"
                        todas={formulario.todas_fuentes}
                        textoTodas="Todas las fuentes"
                        onTodas={(v) => cambiar('todas_fuentes', v)}
                        items={fuentes}
                        elegidos={formulario.fuente_ids}
                        onAlternar={(id) => cambiar('fuente_ids', alternarId(formulario.fuente_ids, id))}
                    />
                    <ListaChequeo
                        titulo="Sectores"
                        todas={formulario.todos_sectores}
                        textoTodas="Todos los sectores"
                        onTodas={(v) => cambiar('todos_sectores', v)}
                        items={sectores}
                        elegidos={formulario.sector_ids}
                        onAlternar={(id) => cambiar('sector_ids', alternarId(formulario.sector_ids, id))}
                    />
                    <div className="pl-formulario-acciones">
                        <button type="submit" className="gp-boton gp-boton-primario" disabled={guardando}>
                            <FontAwesomeIcon icon={faFloppyDisk} /> Guardar
                        </button>
                    </div>
                </form>
            )}

            {pestana === 'requisitos' && (
                <PlantillaRequisitos plantilla={plantilla} version={version} requisitos={requisitos} onCambio={cargar} />
            )}

            {pestana === 'vista' && <PlantillaVistaPrevia plantilla={plantilla} version={version} requisitos={requisitos} />}

            {pestana === 'versiones' && <PlantillaVersiones plantilla={plantilla} onCambio={cargar} />}
        </div>
    );
};

export default PlantillaDetalle;
```

Crear `resources/js/components/PlantillaRequisitos.jsx`:

```jsx
import React, { useEffect, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowDown, faArrowUp, faPen, faPlus, faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { SECCIONES, mensajeError } from '../utils/expedienteChecklist';
import {
    CARACTERES, CATEGORIAS, TIPOS_REQUISITO, datosRequisito, etiquetaCaracter, etiquetaCategoria, formularioRequisito,
    mover, requisitoVacio,
} from '../utils/plantillasChecklist';

const avisarError = (titulo, error) => Swal.fire({ icon: 'error', title: titulo, text: mensajeError(error) });

const Campo = ({ etiqueta, children, ancho }) => (
    <label className={`pl-campo${ancho ? ' pl-campo-ancho' : ''}`}>
        <span>{etiqueta}</span>
        {children}
    </label>
);

/** Formulario de un requisito. En el motor sgr agrega sección, sector, tipo y condición. */
const FormularioRequisito = ({ motor, esSgr, inicial, catalogoSgr, onGuardar, onCancelar }) => {
    const [f, setF] = useState(inicial);
    const [revisionCondicion, setRevisionCondicion] = useState(null);
    const cambiar = (campo, valor) => setF((actual) => ({ ...actual, [campo]: valor }));
    const texto = (campo, props = {}) => <input value={f[campo]} onChange={(e) => cambiar(campo, e.target.value)} {...props} />;

    const sector = catalogoSgr?.sectores.find((s) => s.id === Number(f.sector_id));

    const validarCondicion = async () => {
        try {
            const { data } = await axios.post('/catalogo-requisitos/validar-condicion', { condicion: f.condicion });
            setRevisionCondicion(data.valida ? 'La condición es válida.' : data.errores.join(' '));
        } catch (e) {
            setRevisionCondicion(mensajeError(e));
        }
    };

    return (
        <form className="gp-panel pl-formulario pl-formulario-requisito" onSubmit={(e) => { e.preventDefault(); onGuardar(f); }}>
            <Campo etiqueta="Nombre del requisito" ancho>
                <textarea rows={2} required value={f.texto} onChange={(e) => cambiar('texto', e.target.value)} />
            </Campo>
            <Campo etiqueta="Descripción" ancho>
                <textarea rows={2} value={f.descripcion} onChange={(e) => cambiar('descripcion', e.target.value)} />
            </Campo>

            {motor === 'sgr' && (
                <>
                    <Campo etiqueta="Clave">{texto('clave', { required: true, maxLength: 40, pattern: '[a-z0-9_]+' })}</Campo>
                    <Campo etiqueta="Código">{texto('codigo', { required: true, maxLength: 60 })}</Campo>
                    <Campo etiqueta="Sección">
                        <select value={f.seccion} onChange={(e) => cambiar('seccion', e.target.value)}>
                            {['A', 'B', 'D', 'C'].map((s) => <option key={s} value={s}>{SECCIONES[s].num} {SECCIONES[s].titulo}</option>)}
                        </select>
                    </Campo>
                    <Campo etiqueta="Fuente normativa">
                        <select value={f.fuente} onChange={(e) => cambiar('fuente', e.target.value)}>
                            <option value="a12">Acuerdo 12/2024</option>
                            <option value="ot">Orientaciones Transitorias</option>
                        </select>
                    </Campo>
                    {f.seccion === 'C' && (
                        <>
                            <Campo etiqueta="Sector SGR">
                                <select value={f.sector_id} onChange={(e) => setF((a) => ({ ...a, sector_id: e.target.value, tipo_id: '' }))}>
                                    <option value="">Elige un sector</option>
                                    {(catalogoSgr?.sectores ?? []).map((s) => <option key={s.id} value={s.id}>{s.nombre}</option>)}
                                </select>
                            </Campo>
                            <Campo etiqueta="Tipo de proyecto">
                                <select value={f.tipo_id} onChange={(e) => cambiar('tipo_id', e.target.value)}>
                                    <option value="">Todo el sector</option>
                                    {(sector?.tipos ?? []).map((t) => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                                </select>
                            </Campo>
                        </>
                    )}
                    <Campo etiqueta="Subtítulo">{texto('subtitulo', { maxLength: 255 })}</Campo>
                    <Campo etiqueta="Condición (evaluador SGR)">
                        <span className="pl-en-linea">
                            {texto('condicion', { maxLength: 255, placeholder: 'siempre, fase=3, comp=predios…' })}
                            <button type="button" className="gp-boton" onClick={validarCondicion}>Validar</button>
                        </span>
                        {revisionCondicion && <small className="pl-ayuda" role="status">{revisionCondicion}</small>}
                    </Campo>
                </>
            )}

            <Campo etiqueta="Categoría">
                <select value={f.categoria} onChange={(e) => cambiar('categoria', e.target.value)}>
                    <option value="">Sin categoría</option>
                    {CATEGORIAS.map(([v, t]) => <option key={v} value={v}>{t}</option>)}
                </select>
            </Campo>
            <Campo etiqueta="Tipo de requisito">
                <select value={f.tipo_requisito} onChange={(e) => cambiar('tipo_requisito', e.target.value)}>
                    <option value="">Sin definir</option>
                    {TIPOS_REQUISITO.map(([v, t]) => <option key={v} value={v}>{t}</option>)}
                </select>
            </Campo>

            {motor === 'simple' && (
                <>
                    <Campo etiqueta="Carácter">
                        <select value={f.caracter} onChange={(e) => cambiar('caracter', e.target.value)}>
                            {CARACTERES.map(([v, t]) => <option key={v} value={v}>{t}</option>)}
                        </select>
                    </Campo>
                    {f.caracter === 'condicionado' && (
                        <Campo etiqueta="¿Cuándo aplica?" ancho>
                            <textarea rows={2} required value={f.condicion_descripcion} onChange={(e) => cambiar('condicion_descripcion', e.target.value)} />
                        </Campo>
                    )}
                </>
            )}

            <Campo etiqueta="Responsable de cumplimiento (cargo)">{texto('cargo_cumplimiento', { maxLength: 150 })}</Campo>
            <Campo etiqueta="Responsable de revisión (cargo)">{texto('cargo_revision', { maxLength: 150 })}</Campo>
            <Campo etiqueta="Documento o soporte requerido">{texto('soporte_requerido', { maxLength: 255 })}</Campo>
            <Campo etiqueta="Referencia normativa">{texto('referencia_normativa', { maxLength: 255 })}</Campo>
            {!esSgr && <Campo etiqueta="Código común (para no repetir entre plantillas)">{texto('codigo_comun', { maxLength: 40 })}</Campo>}
            <Campo etiqueta="Observaciones e instrucciones" ancho>
                <textarea rows={2} value={f.nota} onChange={(e) => cambiar('nota', e.target.value)} />
            </Campo>
            <label className="pl-check">
                <input type="checkbox" checked={f.activo} onChange={(e) => cambiar('activo', e.target.checked)} /> Activo
            </label>
            {esSgr ? (
                <p className="pl-ayuda">En la plantilla SGR todo "no aplica" necesita aprobación.</p>
            ) : (
                <label className="pl-check">
                    <input type="checkbox" checked={f.requiere_aprobacion_no_aplica} onChange={(e) => cambiar('requiere_aprobacion_no_aplica', e.target.checked)} />
                    El "no aplica" necesita aprobación
                </label>
            )}

            <div className="pl-formulario-acciones">
                <button type="button" className="gp-boton" onClick={onCancelar}>Cancelar</button>
                <button type="submit" className="gp-boton gp-boton-primario">Guardar requisito</button>
            </div>
        </form>
    );
};

/** Requisitos de la versión de trabajo: se editan en borrador; una versión publicada solo se lee. */
const PlantillaRequisitos = ({ plantilla, version, requisitos, onCambio }) => {
    const [editando, setEditando] = useState(null); // null, 'nuevo' o el requisito
    const [catalogoSgr, setCatalogoSgr] = useState(null);
    const editable = version?.estado === 'borrador';
    const esSgr = plantilla.es_sgr;
    const url = version ? `/catalogo-requisitos/versiones/${version.id}` : null;

    useEffect(() => {
        if (plantilla.motor !== 'sgr') return undefined;
        let vigente = true;
        axios.get('/catalogo-requisitos/vigente', { params: { incluir_inactivos: 1 } })
            .then(({ data }) => { if (vigente) setCatalogoSgr(data); })
            .catch(() => {});
        return () => { vigente = false; };
    }, [plantilla.motor]);

    const crearBorrador = async () => {
        try {
            await axios.post(`/plantillas/${plantilla.id}/versiones`, { descripcion: null });
            await onCambio();
        } catch (e) {
            avisarError('No se pudo crear el borrador', e);
        }
    };

    const guardar = async (formulario) => {
        const datos = datosRequisito(formulario, plantilla.motor);
        try {
            if (editando === 'nuevo') {
                await axios.post(`${url}/requisitos`, datos);
            } else {
                await axios.put(`${url}/requisitos/${editando.id}`, datos);
            }
            setEditando(null);
            await onCambio();
        } catch (e) {
            avisarError('No se pudo guardar el requisito', e);
        }
    };

    const eliminar = async (requisito) => {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            title: '¿Eliminar este requisito del borrador?',
            text: requisito.texto,
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        try {
            await axios.delete(`${url}/requisitos/${requisito.id}`);
            await onCambio();
        } catch (e) {
            avisarError('No se pudo eliminar el requisito', e);
        }
    };

    const moverRequisito = async (indice, delta) => {
        const ids = mover(requisitos.map((r) => r.id), indice, delta);
        try {
            await axios.put(`${url}/requisitos-orden`, { ids });
            await onCambio();
        } catch (e) {
            avisarError('No se pudo cambiar el orden', e);
        }
    };

    if (!version) {
        return (
            <div className="gp-panel pl-aviso">
                <p>La plantilla no tiene versiones.</p>
                <button type="button" className="gp-boton gp-boton-primario" onClick={crearBorrador}>Crear borrador</button>
            </div>
        );
    }

    if (editando) {
        return (
            <FormularioRequisito
                motor={plantilla.motor}
                esSgr={esSgr}
                catalogoSgr={catalogoSgr}
                inicial={editando === 'nuevo' ? requisitoVacio(plantilla.motor) : formularioRequisito(editando, plantilla.motor)}
                onGuardar={guardar}
                onCancelar={() => setEditando(null)}
            />
        );
    }

    return (
        <div className="pl-requisitos">
            <div className="pl-barra">
                <p className="pl-ayuda">
                    {editable
                        ? `Editando el borrador v${version.numero}. Los cambios se ven en los expedientes nuevos cuando se publique.`
                        : `La versión v${version.numero} está publicada y no se modifica.`}
                </p>
                {editable ? (
                    <button type="button" className="gp-boton gp-boton-primario" onClick={() => setEditando('nuevo')}>
                        <FontAwesomeIcon icon={faPlus} /> Agregar requisito
                    </button>
                ) : (
                    <button type="button" className="gp-boton gp-boton-primario" onClick={crearBorrador}>Crear borrador para editar</button>
                )}
            </div>

            {requisitos.length === 0 && <p className="gp-vacio">Esta versión aún no tiene requisitos.</p>}

            <ol className="pl-tabla">
                {requisitos.map((r, indice) => (
                    <li key={r.id} className={r.activo ? '' : 'inactivo'}>
                        <div className="pl-fila-texto">
                            <span className="pl-codigo">{r.codigo || r.clave}</span>
                            <span>{r.texto}</span>
                            <span className="pl-fila-etiquetas">
                                {r.categoria && <span className="pl-insignia pl-insignia-suave">{etiquetaCategoria(r.categoria)}</span>}
                                {plantilla.motor === 'simple' && <span className="pl-insignia pl-insignia-suave">{etiquetaCaracter(r.caracter)}</span>}
                                {!r.activo && <span className="pl-insignia pl-insignia-alerta">Inactivo</span>}
                            </span>
                        </div>
                        {editable && (
                            <div className="pl-fila-acciones">
                                <button type="button" className="gp-icono" aria-label={`Subir ${r.texto}`} disabled={indice === 0} onClick={() => moverRequisito(indice, -1)}>
                                    <FontAwesomeIcon icon={faArrowUp} />
                                </button>
                                <button type="button" className="gp-icono" aria-label={`Bajar ${r.texto}`} disabled={indice === requisitos.length - 1} onClick={() => moverRequisito(indice, 1)}>
                                    <FontAwesomeIcon icon={faArrowDown} />
                                </button>
                                <button type="button" className="gp-icono" aria-label={`Editar ${r.texto}`} onClick={() => setEditando(r)}>
                                    <FontAwesomeIcon icon={faPen} />
                                </button>
                                <button type="button" className="gp-icono" aria-label={`Eliminar ${r.texto}`} onClick={() => eliminar(r)}>
                                    <FontAwesomeIcon icon={faTrash} />
                                </button>
                            </div>
                        )}
                    </li>
                ))}
            </ol>
        </div>
    );
};

export default PlantillaRequisitos;
```

Crear `resources/js/components/PlantillaVistaPrevia.jsx`:

```jsx
import React, { useMemo } from 'react';
import { agruparVistaPrevia, etiquetaCaracter, etiquetaTipo } from '../utils/plantillasChecklist';

/** El checklist de la versión de trabajo como lo verá el usuario en un expediente. */
const PlantillaVistaPrevia = ({ plantilla, version, requisitos }) => {
    const grupos = useMemo(() => agruparVistaPrevia(requisitos, plantilla.motor), [requisitos, plantilla.motor]);

    if (!version) return <p className="gp-vacio">La plantilla no tiene versiones.</p>;
    if (grupos.length === 0) return <p className="gp-vacio">La versión v{version.numero} no tiene requisitos activos.</p>;

    return (
        <div className="pl-vista">
            <p className="pl-ayuda">
                Versión v{version.numero}{version.estado === 'borrador' ? ' (borrador)' : ''}.
                {plantilla.motor === 'sgr'
                    ? ' En cada expediente, el evaluador SGR decide cuáles aplican según la fase, el sector y los componentes.'
                    : ' Los condicionados nacen "por definir" y una persona decide si aplican.'}
            </p>
            {grupos.map((g) => (
                <section key={g.clave} className="gp-panel pl-vista-grupo">
                    <h3>{g.titulo} <span className="pl-ayuda">· {g.requisitos.length}</span></h3>
                    <ul>
                        {g.requisitos.map((r) => (
                            <li key={r.id}>
                                <input type="checkbox" disabled aria-label={`Vista previa de ${r.texto}`} />
                                <div>
                                    <div>{r.texto}</div>
                                    {r.descripcion && <small>{r.descripcion}</small>}
                                    <small className="pl-vista-datos">
                                        {plantilla.motor === 'simple' && etiquetaCaracter(r.caracter)}
                                        {r.tipo_requisito && ` · ${etiquetaTipo(r.tipo_requisito)}`}
                                        {r.soporte_requerido && ` · Soporte: ${r.soporte_requerido}`}
                                        {r.cargo_cumplimiento && ` · Cumple: ${r.cargo_cumplimiento}`}
                                        {r.caracter === 'condicionado' && r.condicion_descripcion && ` · Aplica si: ${r.condicion_descripcion}`}
                                    </small>
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
};

export default PlantillaVistaPrevia;
```

Crear `resources/js/components/PlantillaVersiones.jsx`:

```jsx
import React from 'react';
import Swal from 'sweetalert2';
import { faBullhorn, faPlus, faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { mensajeError } from '../utils/expedienteChecklist';

const avisarError = (titulo, error) => Swal.fire({ icon: 'error', title: titulo, text: mensajeError(error) });
const fecha = (iso) => (iso ? new Date(iso).toLocaleDateString('es-CO') : '—');

/** Historial de versiones: quién publicó y cuándo; publicar, crear y eliminar borrador. */
const PlantillaVersiones = ({ plantilla, onCambio }) => {
    const borrador = plantilla.versiones.find((v) => v.estado === 'borrador');

    const confirmar = async (titulo, texto, boton) => (await Swal.fire({
        icon: 'warning', title: titulo, text: texto, showCancelButton: true, confirmButtonText: boton, cancelButtonText: 'Cancelar',
    })).isConfirmed;

    const accion = async (peticion, error) => {
        try {
            await peticion();
            await onCambio();
        } catch (e) {
            avisarError(error, e);
        }
    };

    const publicar = async (version) => {
        if (!(await confirmar(`¿Publicar la versión v${version.numero}?`, 'Pasa a ser la vigente: la usarán los expedientes nuevos. Los que ya existen conservan la suya. Una versión publicada no se modifica.', 'Sí, publicar'))) return;
        await accion(() => axios.post(`/catalogo-requisitos/versiones/${version.id}/publicar`), 'No se pudo publicar la versión');
    };

    const eliminar = async (version) => {
        if (!(await confirmar(`¿Eliminar el borrador v${version.numero}?`, 'Se pierden sus cambios. Las versiones publicadas no se tocan.', 'Sí, eliminar'))) return;
        await accion(() => axios.delete(`/catalogo-requisitos/versiones/${version.id}`), 'No se pudo eliminar el borrador');
    };

    const crear = async () => {
        const { isConfirmed, value } = await Swal.fire({
            title: 'Nuevo borrador',
            text: plantilla.vigente ? 'Copia la versión vigente para editarla.' : 'La plantilla aún no tiene versión publicada: el borrador nace vacío.',
            input: 'text',
            inputLabel: 'Qué cambia (opcional)',
            inputAttributes: { maxlength: '255' },
            showCancelButton: true,
            confirmButtonText: 'Crear',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        await accion(() => axios.post(`/plantillas/${plantilla.id}/versiones`, { descripcion: value || null }), 'No se pudo crear el borrador');
    };

    return (
        <div className="pl-versiones">
            {!borrador && (
                <div className="pl-barra">
                    <p className="pl-ayuda">Para cambiar requisitos se crea un borrador, se edita y se publica.</p>
                    <button type="button" className="gp-boton gp-boton-primario" onClick={crear}>
                        <FontAwesomeIcon icon={faPlus} /> Crear borrador
                    </button>
                </div>
            )}
            <div className="pl-tabla-versiones" role="table" aria-label="Versiones de la plantilla">
                <div role="row" className="pl-versiones-cabecera">
                    <span role="columnheader">Versión</span>
                    <span role="columnheader">Estado</span>
                    <span role="columnheader">Publicada</span>
                    <span role="columnheader">Requisitos</span>
                    <span role="columnheader">Expedientes</span>
                    <span role="columnheader">Acciones</span>
                </div>
                {plantilla.versiones.map((v) => (
                    <div role="row" key={v.id}>
                        <span role="cell"><b>v{v.numero}</b>{v.descripcion && <small> · {v.descripcion}</small>}</span>
                        <span role="cell">{v.estado === 'borrador' ? 'Borrador' : (v.vigente ? 'Vigente' : 'Publicada')}</span>
                        <span role="cell">{v.estado === 'borrador' ? '—' : `${fecha(v.fecha_publicacion)}${v.publicada_por ? ` por ${v.publicada_por}` : ''}`}</span>
                        <span role="cell">{v.requisitos}</span>
                        <span role="cell">{v.expedientes}</span>
                        <span role="cell" className="pl-fila-acciones">
                            {v.estado === 'borrador' && (
                                <>
                                    <button type="button" className="gp-boton gp-boton-primario" onClick={() => publicar(v)}>
                                        <FontAwesomeIcon icon={faBullhorn} /> Publicar
                                    </button>
                                    <button type="button" className="gp-icono" aria-label={`Eliminar el borrador v${v.numero}`} onClick={() => eliminar(v)}>
                                        <FontAwesomeIcon icon={faTrash} />
                                    </button>
                                </>
                            )}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
};

export default PlantillaVersiones;
```

Crear `resources/css/Plantillas.css`:

```css
/* Administración de plantillas de checklist. Usa los tokens --gp-* del armazón. */

.pl-pagina { min-width: 0; }
.pl-volver { display: inline-flex; align-items: center; gap: .4rem; margin-bottom: .6rem; font-size: .85rem; color: var(--gp-azul-texto); text-decoration: none; }
.pl-error { padding: .7rem .9rem; margin-bottom: 1rem; color: var(--gp-texto); background: var(--gp-coral-suave); border-left: 3px solid var(--gp-coral); border-radius: var(--gp-radio); }
.pl-ayuda { margin: 0; font-size: .82rem; color: var(--gp-texto-3); }

.pl-barra { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
.pl-buscar, .pl-campo input, .pl-campo select, .pl-campo textarea {
    min-width: 0;
    padding: .5rem .65rem;
    font: inherit;
    font-size: .9rem;
    color: var(--gp-texto);
    background: var(--gp-superficie-2);
    border: 1px solid var(--gp-linea-fuerte);
    border-radius: var(--gp-radio);
}
.pl-buscar { flex: 1 1 18rem; }
.pl-buscar:focus, .pl-campo input:focus, .pl-campo select:focus, .pl-campo textarea:focus { outline: none; box-shadow: var(--gp-enfoque); }
.pl-check { display: inline-flex; align-items: center; gap: .45rem; font-size: .88rem; color: var(--gp-texto-2); cursor: pointer; }
.pl-check input { width: 16px; height: 16px; accent-color: var(--gp-azul); }

.pl-lista { display: grid; gap: .75rem; margin: 0; padding: 0; list-style: none; }
.pl-tarjeta { display: flex; flex-wrap: wrap; gap: .8rem 1.2rem; align-items: flex-start; justify-content: space-between; padding: 1rem 1.1rem; }
.pl-tarjeta.inactiva { opacity: .7; }
.pl-tarjeta-cuerpo { flex: 1 1 22rem; min-width: 0; }
.pl-tarjeta-titulo { display: flex; flex-wrap: wrap; gap: .45rem; align-items: center; font-size: 1.05rem; font-weight: 700; }
.pl-tarjeta-titulo a { color: var(--gp-texto); text-decoration: none; }
.pl-tarjeta-titulo a:hover { color: var(--gp-azul-texto); }
.pl-tarjeta-descripcion { margin: .3rem 0 0; font-size: .88rem; color: var(--gp-texto-2); }
.pl-tarjeta-acciones { display: flex; flex-wrap: wrap; gap: .4rem; }
.pl-datos { display: flex; flex-wrap: wrap; gap: .4rem 1.4rem; margin: .6rem 0 0; }
.pl-datos div { min-width: 0; }
.pl-datos dt { font-family: var(--gp-fuente-datos); font-size: .66rem; letter-spacing: .08em; text-transform: uppercase; color: var(--gp-texto-3); }
.pl-datos dd { margin: 0; font-size: .86rem; color: var(--gp-texto); overflow-wrap: anywhere; }

.pl-insignia { padding: .08rem .45rem; font-family: var(--gp-fuente-datos); font-size: .68rem; font-weight: 500; color: var(--gp-sobre-acento); background: var(--gp-ambar); border-radius: 999px; }
.pl-insignia-suave { color: var(--gp-azul-texto); background: var(--gp-azul-suave); }
.pl-insignia-alerta { color: var(--gp-texto); background: var(--gp-coral-suave); }

.pl-pestanas { display: flex; gap: .2rem; margin-bottom: 1.1rem; overflow-x: auto; border-bottom: 1px solid var(--gp-linea); }
.pl-pestana { padding: .55rem .9rem; font-size: .9rem; font-weight: 600; white-space: nowrap; color: var(--gp-texto-3); background: none; border: 0; border-bottom: 2px solid transparent; }
.pl-pestana:hover { color: var(--gp-texto); }
.pl-pestana.activa { color: var(--gp-azul-texto); border-bottom-color: var(--gp-azul-texto); }

.pl-formulario { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 16rem), 1fr)); gap: .9rem 1.1rem; padding: 1.1rem; }
.pl-campo { display: flex; flex-direction: column; gap: .3rem; min-width: 0; margin: 0; }
.pl-campo > span { font-size: .78rem; font-weight: 600; color: var(--gp-texto-2); }
.pl-campo-ancho, .pl-asociacion, .pl-formulario-acciones, .pl-formulario > .pl-ayuda { grid-column: 1 / -1; }
.pl-en-linea { display: flex; gap: .4rem; }
.pl-en-linea input { flex: 1; }
.pl-asociacion { min-width: 0; margin: 0; padding: .7rem .85rem; border: 1px solid var(--gp-linea); border-radius: var(--gp-radio); }
.pl-asociacion legend { padding: 0 .3rem; font-size: .8rem; font-weight: 700; color: var(--gp-texto-2); }
.pl-opciones { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 14rem), 1fr)); gap: .35rem .9rem; margin-top: .55rem; }
.pl-formulario-acciones { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: flex-end; }

.pl-aviso { display: flex; flex-wrap: wrap; gap: .8rem; align-items: center; justify-content: space-between; padding: 1rem 1.1rem; }

.pl-tabla { display: grid; gap: .35rem; margin: 0; padding: 0; list-style: none; }
.pl-tabla li { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; justify-content: space-between; padding: .6rem .8rem; background: var(--gp-superficie); border: 1px solid var(--gp-linea); border-radius: var(--gp-radio); }
.pl-tabla li.inactivo { opacity: .6; }
.pl-fila-texto { display: flex; flex: 1 1 20rem; flex-wrap: wrap; gap: .3rem .6rem; align-items: baseline; min-width: 0; font-size: .9rem; }
.pl-fila-etiquetas { display: inline-flex; flex-wrap: wrap; gap: .3rem; }
.pl-fila-acciones { display: inline-flex; flex-wrap: wrap; gap: .3rem; align-items: center; }
.pl-codigo { font-family: var(--gp-fuente-datos); font-size: .74rem; color: var(--gp-azul-texto); }

.pl-vista { display: grid; gap: .8rem; }
.pl-vista-grupo { padding: .9rem 1rem; }
.pl-vista-grupo h3 { margin: 0 0 .6rem; font-size: 1rem; }
.pl-vista-grupo ul { display: grid; gap: .55rem; margin: 0; padding: 0; list-style: none; }
.pl-vista-grupo li { display: flex; gap: .6rem; align-items: flex-start; font-size: .9rem; }
.pl-vista-grupo li input { flex-shrink: 0; margin-top: .2rem; }
.pl-vista-grupo small { display: block; font-size: .78rem; color: var(--gp-texto-3); }
.pl-vista-datos { margin-top: .15rem; }

.pl-tabla-versiones { display: grid; gap: .35rem; }
.pl-tabla-versiones [role="row"] { display: grid; grid-template-columns: 1.4fr .8fr 1.4fr .7fr .7fr 1.3fr; gap: .6rem; align-items: center; padding: .6rem .8rem; font-size: .88rem; background: var(--gp-superficie); border: 1px solid var(--gp-linea); border-radius: var(--gp-radio); }
.pl-tabla-versiones .pl-versiones-cabecera { font-family: var(--gp-fuente-datos); font-size: .68rem; letter-spacing: .08em; text-transform: uppercase; color: var(--gp-texto-3); background: none; border: 0; }
.pl-tabla-versiones small { color: var(--gp-texto-3); }

@media (max-width: 720px) {
    .pl-tabla-versiones .pl-versiones-cabecera { display: none; }
    .pl-tabla-versiones [role="row"] { grid-template-columns: 1fr 1fr; }
}
```

- [ ] **Paso 2: Compilar**

Run: `npm run production`
Expected: `Compiled Successfully`, sin errores.

- [ ] **Paso 3: Ejecutar las pruebas**

Run: `npm test`
Expected: pasan todas, 154.

- [ ] **Paso 4: Comitear**

Confirmar antes que `public/js/app.js` es de producción (minificado, sin comentarios `/***/`); si el watch lo pisó, compilar de nuevo y comitear enseguida.

```bash
git add resources/js/components/PlantillasChecklist.jsx resources/js/components/PlantillaDetalle.jsx resources/js/components/PlantillaRequisitos.jsx resources/js/components/PlantillaVistaPrevia.jsx resources/js/components/PlantillaVersiones.jsx resources/css/Plantillas.css public/js/app.js public/js/app.js.map public/mix-manifest.json
```

```bash
git commit -m "feat: add checklist template administration screens"
```

---

### Tarea 9: Verificación en una copia descartable

Base descartable, nunca `gest_pro`. Esta etapa no tiene migraciones: la copia solo sirve para probar con datos reales sin tocarlos.

- [ ] **Paso 1: Crear la copia y los usuarios de prueba**

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE IF EXISTS gestpro_verif_tmp; CREATE DATABASE gestpro_verif_tmp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro | /c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp
```

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'verif@gestpro.test', 'rol' => App\Enums\Rol::Administrador]); App\Models\User::factory()->create(['email' => 'gestor@gestpro.test', 'rol' => App\Enums\Rol::Gestor]);"
```

Run: `DB_DATABASE=gestpro_verif_tmp php artisan migrate:status`
Expected: ninguna migración pendiente.

- [ ] **Paso 2: Probar en el navegador**

Arrancar el servidor desde `public/` (`artisan serve` recargaría `gest_pro` desde `.env`):

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8765 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

La contraseña es la predeterminada de `UserFactory`. Como Administrador:

1. Catálogos → Plantillas de checklist muestra la plantilla SGR con "v1 vigente" y 207 requisitos.
2. "Nueva plantilla": pide el nombre y abre el detalle con "Sin versión publicada · v1 en borrador".
3. General y asociaciones: quitar "Todas las fuentes", elegir dos fuentes y guardar. Sin fuentes ni "todas", el guardado muestra el error.
4. Requisitos: agregar uno obligatorio con categoría y soporte, y uno condicionado; sin "¿Cuándo aplica?", el formulario no se envía. Subir y bajar cambia el orden.
5. Vista previa: los dos requisitos agrupados por categoría.
6. Versiones: publicar. La versión queda "Vigente" con fecha y quién publicó, y Requisitos pasa a solo lectura con "Crear borrador para editar".
7. En la lista, duplicar la plantilla SGR como simple: se abre la copia con 207 requisitos, la mayoría condicionados.
8. En la plantilla SGR, editar un requisito de un borrador: no ofrece carácter ni casilla de aprobación, y avisa que todo "no aplica" se aprueba.
9. Desactivar y activar la plantilla creada. La plantilla SGR no ofrece "Desactivar".

Como Gestor: la sección no aparece en el menú y `/plantillas` lleva al tablero. La consola del navegador no muestra errores nuevos.

- [ ] **Paso 3: Limpiar**

Detener el servidor y eliminar la copia:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 4: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas, 386.

Run: `npm test`
Expected: pasan todas, 154.

- [ ] **Paso 5: Informar al usuario y pedir validación**

Informar: totales de pruebas y lo revisado en el navegador. Como no hay migraciones, no hay nada que aplicar en `gest_pro`: basta con que el servidor local use esta rama. Pedir que valide la etapa.

---

## Criterio de la etapa

La etapa está lista cuando, según el diseño, un administrador crea, duplica, asocia, configura y publica una plantilla sin desarrollador, y la plantilla SGR y sus expedientes siguen funcionando igual.
