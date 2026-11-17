# Contratación, etapa 2: plantillas de contratación. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** que un administrador cree, asocie a modalidades y publique plantillas de **contratación**, con cada requisito asignado a una etapa, y que un comando siembre el borrador "Licitación pública – SECOP II" a partir de la guía de Colombia Compra. Las plantillas de contratación nunca se ofrecen a un expediente de viabilización.

**Arquitectura:**
- **Migración 16:** agrega a `plantillas_checklist` el `uso` (`viabilizacion` o `contratacion`) y las modalidades (`todas_modalidades` y `plantilla_modalidades`).
- **Migración 17:** agrega la etapa a los requisitos del catálogo (`etapa_id`) y, para la etapa 4, a los requisitos propios (`etapa_propia_id`).
- **Backend:** `PlantillaService` y `CatalogoService` aplican las reglas de uso, modalidades y etapa. `PlantillasExpediente` filtra la viabilización. `App\Services\Contratacion\PlantillaLicitacionPublica` hace la siembra, que dispara el comando `plantillas:sembrar-contratacion`.
- **Pantallas:** las de administración de plantillas existentes, con el uso, las modalidades y los requisitos agrupados por etapa.

**Tecnologías:** Laravel 12, PHP 8.2, MariaDB 10.4, React 18 con Laravel Mix, PHPUnit sobre SQLite en memoria, Vitest.

**Diseño:** `docs/superpowers/specs/2026-11-17-contratacion-revision-design.md`:
- sección 3: "Expediente de contratación", "Etapas" y "Procesos que no van por SECOP";
- sección 4: plantilla sembrada;
- sección 6: migraciones 16 y 17;
- sección 7: interfaces;
- sección 9: etapa 2;
- `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, aprobada el 2026-11-18: proceso y contrato separados y vinculados, y la sección 10 del diseño anulada. Esta etapa es la fase 1 de esa propuesta y no cambia: las plantillas se asocian a modalidades.

**Estado de partida:** rama `feature/contratacion`, etapa 1 validada y aplicada en `gest_pro`, 797 pruebas PHP y 430 JS pasando.

---

## Reglas de ejecución

- **Nada de esto se aplica a `gest_pro` sin autorización del usuario.** Nunca se usa `migrate:rollback` allí: si hay que volver atrás, se restaura el respaldo. La tarea 10 aplica las migraciones, y solo con un sí explícito. El comando de siembra sobre `gest_pro` es otra autorización aparte.
- Commits autorizados solo en `feature/contratacion`. Sin push. Mensajes en inglés, terminados con la línea `Co-Authored-By` del modelo que comitea.
- Nomenclatura en español sin tildes para tablas, columnas, clases y métodos nuevos. Textos para el usuario en español con tuteo. Las migraciones y el comando de siembra, que son herramientas del operador, conservan el registro de usted.
- Pruebas: `php artisan test` sin definir `DB_DATABASE` (usa SQLite en memoria) y `npm test`. Si Vitest crea `resources/js/node_modules`, se borra.
- **Editar con la herramienta Edit, no con `sed`.** Cada reemplazo de este plan aparece una sola vez en su archivo. Si no lo encuentras tal cual, detente y avisa. Si Edit falla solo por finales de línea CRLF, adapta solo eso.
- **No usar `git stash`:** el usuario tiene stashes propios.
- El usuario suele tener `npm run watch` corriendo, que reescribe `public/`. Solo la tarea 7 comitea `public/`, después de `npm run production`.
- No tocar ni comitear `public/images/marca-completa.png` ni `public/images/marca-completa1.png`: son cambios del usuario.
- `.env` está versionado: nunca se agrega a un commit. Comitear siempre con rutas explícitas.
- `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio. Hay que definirlo en cada comando, porque el estado de la terminal no se conserva.
- **SQLite:** agregar columnas con clave foránea reconstruye la tabla y, dentro de `RefreshDatabase`, borra en cascada las filas hijas. Las pruebas de las migraciones no crean filas hijas.
- **Estado intermedio:** entre las tareas 3 y 5, crear una plantilla desde la pantalla da 422, porque el uso pasa a ser obligatorio y la pantalla aún no lo envía. Se resuelve en la tarea 5, antes del compilado.
- **Backend y compilado van juntos.** El código de esta etapa exige el uso al crear y la etapa en los requisitos de contratación, y el JavaScript anterior no los envía. Por eso no se despliega el backend sin el compilado de la tarea 7, ni el compilado sin las migraciones de la tarea 10.

## Decisiones de este plan

**Uso**
- Se elige al crear la plantilla y no cambia después.
- Al duplicar, la copia conserva el uso.
- La plantilla SGR es de viabilización, y todas las plantillas existentes quedan así.
- Una plantilla de contratación solo puede tener motor `simple`.
- **Viabilización:** las plantillas de contratación no se ofrecen, no se preseleccionan y se rechazan en un expediente de viabilización, también en su creación automática y en `crear-faltantes`.

**Modalidades**
- Una plantilla de contratación se asocia a modalidades, o a todas, además de a fuentes y sectores. En una de viabilización no se aceptan.
- Se eligen modalidades activas; las inactivas que la plantilla ya tenía se conservan.
- Si una edición no envía modalidades, se conservan las que tiene.
- Publicar una plantilla de contratación exige modalidades.

**Etapa del requisito**
- En contratación es obligatoria: una etapa activa, o la que el requisito ya tiene aunque esté inactiva. En viabilización se rechaza.
- El borrador nuevo y la copia conservan la etapa de cada requisito.
- Publicar exige etapa en cada requisito activo. Si falta, se listan los requisitos sin etapa.

**Siembra (`plantillas:sembrar-contratacion`)**
- Crea "Licitación pública – SECOP II" (clave `licitacion_publica_secop_ii`): de contratación, para la modalidad licitación pública, todas las fuentes y todos los sectores, con una versión **borrador** de 25 requisitos (20 obligatorios y 5 condicionados) repartidos en las 11 etapas, cada uno con la referencia a la guía CCE-SEC-G1-18.
- No publica: la entidad valida el contenido antes.
- Es idempotente y tiene `--simular`.
- Se detiene sin escribir nada si falta o está inactiva la modalidad o alguna etapa, o si ya existe una plantilla con ese nombre.
- El ítem de compra de alimentos (Decreto 248 de 2021) va en la etapa "Creación del proceso y pliego borrador".
- "Garantías aprobadas" lleva en la nota que la entidad debe validarlo, porque la guía no lo detalla.

## Fuera de esta etapa

- Proceso y contrato en un solo registro: etapa 3 del diseño (sección 10).
- Expediente de contratación, revisión por etapa, cronograma y tablero: etapas 4 a 6.
- Ejecutar la siembra en `gest_pro`: se hace con autorización aparte, después de aplicar las migraciones.

## Lo que ya se ensayó

El plan se ensayó completo en un clon descartable fuera del repositorio, y este documento se generó con el código de ese ensayo: un script comprobó que aplicar sus bloques sobre la rama reproduce el ensayo exactamente.

- **Pruebas:** 848 PHP y 461 JS.
- **En una copia de `gest_pro` (MariaDB)**, los 10 expedientes y sus 293 filas no cambiaron en ningún paso:
  - al migrar;
  - al revertir las dos migraciones, comparando solo tablas;
  - al volver a migrar;
  - al sembrar la plantilla.
- **El comando de siembra** creó "Licitación pública – SECOP II" en borrador con 25 requisitos. `--simular` los listó sin escribir, y una segunda ejecución no creó nada.
- **En el navegador, sobre esa copia:**
  - la pestaña "Contratación" mostró la plantilla sembrada con su modalidad, y "Viabilización" las de siempre;
  - los requisitos aparecieron agrupados por etapa en el orden del catálogo;
  - se creó una plantilla de contratación desde la pantalla, con la lista de las seis modalidades;
  - su requisito exigió etapa, y publicar sin modalidades mostró el error;
  - la consola no mostró errores nuevos.
- **Una revisión independiente** del ensayo confirmó que ninguna plantilla de contratación llega a un expediente de viabilización por ningún camino. Sus hallazgos, todos de gravedad baja, se corrigieron en las tareas correspondientes.

---

### Tarea 1: Punto de partida y foto con el código actual

La foto se toma **antes de cambiar código**. La copia `gestpro_verif_tmp` se conserva hasta la tarea 8.

- [ ] **Paso 1: Comprobar la rama, el estado y las pruebas**

Run: `git branch --show-current`
Expected: `feature/contratacion`

Run: `git status --short`
Expected: como mucho, `public/` (si corre `npm run watch`) y las dos imágenes del usuario.

Run: `php artisan test`
Expected: pasan todas, 797. Si alguna falla, detenerse y avisar.

Run: `npm test`
Expected: pasan todas, 430.

- [ ] **Paso 2: Crear la copia descartable**

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE IF EXISTS gestpro_verif_tmp; CREATE DATABASE gestpro_verif_tmp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro | /c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp
```

Si MySQL no responde (`ERROR 2002`), está apagado. Hay que pedirle al usuario que lo arranque desde XAMPP o arrancarlo con `/c/xampp/mysql/bin/mysqld --defaults-file=/c/xampp/mysql/bin/my.ini --standalone` en segundo plano.

- [ ] **Paso 3: Crear los dos scripts de comprobación**

Crear `$TMP_DIR/gestpro_foto_contratacion2.php`. Toma los expedientes, que esta etapa no debe tocar, y las plantillas y requisitos que amplían las migraciones 16 y 17. Se detiene si la base no es la esperada:

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

Crear `$TMP_DIR/gestpro_comparar_contratacion2.php`. Lo nuevo se permite; un valor distinto o algo que falta, no:

```php
<?php
// Comprueba que todo lo que había antes sigue igual después. Lo nuevo (campos que la etapa
// agrega) se permite; un valor distinto o algo que falta, no.
// Uso: php gestpro_comparar_contratacion2.php <antes.json> <despues.json>

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
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion2.php" "$TMP_DIR/c2_antes.json" gestpro_verif_tmp
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion2.php" "$TMP_DIR/c2_tablas_antes.json" gestpro_verif_tmp --solo-tablas
```

La segunda foto, solo de tablas, sirve para comparar después de revertir: con las migraciones revertidas, el código nuevo ya no puede leer las plantillas por el servicio.

Expected: `Expedientes: 10, filas: 293, plantillas: N, requisitos: M, con API`, con las cifras actuales de `gest_pro`; la segunda termina en `solo tablas`.

---

### Tarea 2: Migraciones 16 y 17: uso, modalidades y etapa

Uso y modalidades de las plantillas, etapa de los requisitos, y el filtro que deja las plantillas de contratación fuera de la viabilización.

**Files:**
- Create: `database/migrations/2026_11_17_100015_add_uso_y_modalidades_a_plantillas.php`
- Create: `database/migrations/2026_11_17_100016_add_etapa_a_requisitos.php`
- Test (create): `tests/Feature/Schema/PlantillasContratacionTest.php`
- Modify: `app/Models/PlantillaChecklist.php`
- Modify: `app/Models/ProyectoRequisito.php`
- Modify: `app/Models/RequisitoCatalogo.php`
- Modify: `app/Services/Viabilizacion/PlantillasExpediente.php`
- Test: `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`
- Test: `tests/Feature/Viabilizacion/PlantillasExpedienteApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Schema/PlantillasContratacionTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Migraciones 16 y 17: uso y modalidades de las plantillas, y etapa de los requisitos. Las
 * pruebas que bajan la 17 no dejan filas en proyectos_requisitos: en SQLite, poner o quitar una
 * clave foránea reconstruye requisitos_catalogo y proyectos_requisitos y, dentro de la
 * transacción de RefreshDatabase, eso borraría en cascada las filas hijas. En MariaDB no pasa.
 */
class PlantillasContratacionTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNAS_ETAPA = [
        ['requisitos_catalogo', 'etapa_id'],
        ['proyectos_requisitos', 'etapa_propia_id'],
    ];

    private function migracionUso(): object
    {
        return require database_path('migrations/2026_11_17_100015_add_uso_y_modalidades_a_plantillas.php');
    }

    private function migracionEtapa(): object
    {
        return require database_path('migrations/2026_11_17_100016_add_etapa_a_requisitos.php');
    }

    private function columna(string $tabla, string $columna): ?array
    {
        return collect(Schema::getColumns($tabla))->firstWhere('name', $columna);
    }

    private function foranea(string $tabla, string $columna): ?array
    {
        return collect(Schema::getForeignKeys($tabla))->first(fn ($f) => $f['columns'] === [$columna]);
    }

    private function plantilla(array $cambios = []): PlantillaChecklist
    {
        return PlantillaChecklist::create([
            'nombre' => 'Licitación pública', 'motor' => PlantillaChecklist::MOTOR_SIMPLE,
            'todas_fuentes' => true, 'todos_sectores' => true, ...$cambios,
        ]);
    }

    private function modalidad(string $clave = 'licitacion_publica'): int
    {
        return (int) Modalidad::where('clave', $clave)->value('id');
    }

    private function etapa(string $clave = 'planeacion'): int
    {
        return (int) EtapaContratacion::where('clave', $clave)->value('id');
    }

    private function requisito(array $cambios = []): int
    {
        $version = DB::table('catalogo_versiones')->insertGetId([
            'plantilla_id' => $this->plantilla(['nombre' => 'Plantilla ' . (DB::table('plantillas_checklist')->count() + 1)])->id,
            'numero' => 1, 'estado' => CatalogoVersion::BORRADOR, 'vigente' => false,
        ]);

        return DB::table('requisitos_catalogo')->insertGetId([
            'catalogo_version_id' => $version, 'clave' => 'r1', 'texto' => 'Estudios previos', ...$cambios,
        ]);
    }

    // ------------------------------------------------------------- uso y modalidades (16)

    private function asertarUsoMigrado(): void
    {
        $uso = $this->columna('plantillas_checklist', 'uso');
        $this->assertNotNull($uso);
        $this->assertFalse($uso['nullable']);
        $this->assertStringContainsString('viabilizacion', (string) $uso['default']);

        $todas = $this->columna('plantillas_checklist', 'todas_modalidades');
        $this->assertNotNull($todas);
        $this->assertFalse($todas['nullable']);

        $this->assertTrue(Schema::hasTable('plantilla_modalidades'));
        $primaria = collect(Schema::getIndexes('plantilla_modalidades'))->firstWhere('primary', true);
        $this->assertSame(['plantilla_id', 'modalidad_id'], $primaria['columns']);

        $plantilla = $this->foranea('plantilla_modalidades', 'plantilla_id');
        $this->assertSame('plantillas_checklist', $plantilla['foreign_table']);
        $this->assertSame('cascade', strtolower($plantilla['on_delete']));
        $modalidad = $this->foranea('plantilla_modalidades', 'modalidad_id');
        $this->assertSame('modalidades', $modalidad['foreign_table']);
        $this->assertSame('restrict', strtolower($modalidad['on_delete']));
    }

    private function asertarUsoRevertido(): void
    {
        $this->assertFalse(Schema::hasColumn('plantillas_checklist', 'uso'));
        $this->assertFalse(Schema::hasColumn('plantillas_checklist', 'todas_modalidades'));
        $this->assertFalse(Schema::hasTable('plantilla_modalidades'));
    }

    public function test_las_plantillas_tienen_uso_y_todas_las_modalidades_con_sus_valores_por_defecto(): void
    {
        $this->asertarUsoMigrado();

        $sgr = DB::table('plantillas_checklist')->where('clave', PlantillaChecklist::CLAVE_SGR)->first();
        $this->assertSame(PlantillaChecklist::USO_VIABILIZACION, $sgr->uso);
        $this->assertFalse((bool) $sgr->todas_modalidades);

        $id = DB::table('plantillas_checklist')->insertGetId(['nombre' => 'Sin uso', 'motor' => 'simple']);
        $fila = DB::table('plantillas_checklist')->where('id', $id)->first();
        $this->assertSame('viabilizacion', $fila->uso);
        $this->assertFalse((bool) $fila->todas_modalidades);
    }

    public function test_el_modelo_de_plantilla_conoce_el_uso_y_sus_modalidades(): void
    {
        $this->assertSame(['viabilizacion', 'contratacion'], PlantillaChecklist::USOS);
        $this->assertSame(PlantillaChecklist::USO_VIABILIZACION, $this->plantilla()->uso);

        $plantilla = $this->plantilla(['nombre' => 'Contratación', 'uso' => PlantillaChecklist::USO_CONTRATACION, 'todas_modalidades' => true]);
        $plantilla->modalidades()->sync([$this->modalidad(), $this->modalidad('minima_cuantia')]);

        $plantilla = $plantilla->fresh();
        $this->assertSame(PlantillaChecklist::USO_CONTRATACION, $plantilla->uso);
        $this->assertTrue($plantilla->todas_modalidades);
        $this->assertSame(['licitacion_publica', 'minima_cuantia'], $plantilla->modalidades->pluck('clave')->sort()->values()->all());
    }

    public function test_borrar_la_plantilla_borra_sus_modalidades_y_una_modalidad_en_uso_no_se_borra(): void
    {
        $plantilla = $this->plantilla(['uso' => PlantillaChecklist::USO_CONTRATACION]);
        $plantilla->modalidades()->sync([$this->modalidad()]);

        try {
            DB::table('modalidades')->where('id', $this->modalidad())->delete();
            $this->fail('La modalidad en uso no debía borrarse.');
        } catch (QueryException) {
            $this->assertSame(1, DB::table('plantilla_modalidades')->count());
        }

        $plantilla->delete();
        $this->assertSame(0, DB::table('plantilla_modalidades')->count());
    }

    public function test_el_rollback_del_uso_se_detiene_si_hay_datos_de_contratacion(): void
    {
        $plantilla = $this->plantilla();
        foreach ([
            'una plantilla de contratación' => fn () => $plantilla->update(['uso' => PlantillaChecklist::USO_CONTRATACION]),
            'una plantilla con todas las modalidades' => fn () => $plantilla->update(['todas_modalidades' => true]),
            'una modalidad asociada' => fn () => $plantilla->modalidades()->sync([$this->modalidad()]),
        ] as $caso => $preparar) {
            $preparar();

            try {
                $this->migracionUso()->down();
                $this->fail("El rollback debía detenerse con {$caso}.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('contratación', $e->getMessage(), $caso);
                $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage(), $caso);
            }

            $this->asertarUsoMigrado();
            $plantilla->update(['uso' => PlantillaChecklist::USO_VIABILIZACION, 'todas_modalidades' => false]);
            $plantilla->modalidades()->sync([]);
        }

        $this->migracionUso()->down();
        $this->asertarUsoRevertido();
        $this->assertTrue(DB::table('plantillas_checklist')->where('id', $plantilla->id)->exists());
    }

    public function test_el_uso_en_ambos_sentidos_se_puede_repetir(): void
    {
        $migracion = $this->migracionUso();

        $migracion->up(); // ya migrada: no falla
        $this->asertarUsoMigrado();

        $migracion->down();
        $migracion->down();
        $this->asertarUsoRevertido();

        $migracion->up();
        $migracion->up();
        $this->asertarUsoMigrado();
        $this->assertSame(PlantillaChecklist::USO_VIABILIZACION, DB::table('plantillas_checklist')->where('clave', 'sgr')->value('uso'));
    }

    public function test_reintentar_el_uso_tras_un_fallo_a_medias_completa_la_migracion(): void
    {
        $migracion = $this->migracionUso();
        $migracion->down();

        // Lo que dejaría un fallo de la subida: una columna y el pivote sin sus claves foráneas.
        Schema::table('plantillas_checklist', function (Blueprint $table) {
            $table->string('uso', 20)->default('viabilizacion');
        });
        Schema::create('plantilla_modalidades', function (Blueprint $table) {
            $table->unsignedBigInteger('plantilla_id');
            $table->unsignedBigInteger('modalidad_id');
            $table->primary(['plantilla_id', 'modalidad_id']);
        });

        $migracion->up();
        $this->asertarUsoMigrado();

        // Lo que dejaría un fallo de la bajada: el pivote ya borrado.
        Schema::drop('plantilla_modalidades');
        $migracion->down();
        $this->asertarUsoRevertido();
    }

    // ------------------------------------------------------------- etapa de los requisitos (17)

    private function asertarEtapaMigrada(): void
    {
        foreach (self::COLUMNAS_ETAPA as [$tabla, $columna]) {
            $definicion = $this->columna($tabla, $columna);
            $this->assertNotNull($definicion, "{$tabla}.{$columna}");
            $this->assertTrue($definicion['nullable'], "{$tabla}.{$columna}");
            $foranea = $this->foranea($tabla, $columna);
            $this->assertNotNull($foranea, "{$tabla}.{$columna}");
            $this->assertSame('etapas_contratacion', $foranea['foreign_table']);
            $this->assertSame(['id'], $foranea['foreign_columns']);
            $this->assertSame('restrict', strtolower($foranea['on_delete']));
        }
    }

    private function asertarEtapaRevertida(): void
    {
        foreach (self::COLUMNAS_ETAPA as [$tabla, $columna]) {
            $this->assertFalse(Schema::hasColumn($tabla, $columna), "{$tabla}.{$columna}");
            $this->assertNull($this->foranea($tabla, $columna), "{$tabla}.{$columna}");
        }
    }

    public function test_los_requisitos_tienen_una_etapa_opcional_con_clave_foranea(): void
    {
        $this->asertarEtapaMigrada();

        $sin = $this->requisito();
        $this->assertNull(DB::table('requisitos_catalogo')->where('id', $sin)->value('etapa_id'));
    }

    public function test_los_modelos_conocen_la_etapa_del_requisito_y_la_del_propio(): void
    {
        $requisito = RequisitoCatalogo::findOrFail($this->requisito(['etapa_id' => $this->etapa('adendas')]));
        $this->assertSame($this->etapa('adendas'), $requisito->etapa_id);
        $this->assertSame('adendas', $requisito->etapa->clave);

        $propio = new ProyectoRequisito(['etapa_propia_id' => $this->etapa('cierre')]);
        $this->assertSame($this->etapa('cierre'), $propio->etapa_propia_id);
        $this->assertSame('cierre', $propio->etapaPropia->clave);
    }

    public function test_una_etapa_asignada_a_un_requisito_no_se_borra(): void
    {
        $this->requisito(['etapa_id' => $this->etapa()]);

        $this->expectException(QueryException::class);
        DB::table('etapas_contratacion')->where('id', $this->etapa())->delete();
    }

    public function test_el_rollback_de_la_etapa_se_detiene_si_algun_requisito_la_tiene(): void
    {
        $this->requisito(['etapa_id' => $this->etapa()]);

        try {
            $this->migracionEtapa()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('etapa', $e->getMessage());
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }

        $this->asertarEtapaMigrada();
    }

    public function test_el_rollback_de_la_etapa_se_detiene_si_algun_requisito_propio_la_tiene(): void
    {
        $expediente = DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => DB::table('proyectos')->insertGetId(['nombre' => 'Acueducto']), 'fase_sgr' => 3, 'estado' => 'borrador',
        ]);
        DB::table('proyectos_requisitos')->insert([
            'expediente_id' => $expediente, 'titulo_propio' => 'Acta de cierre', 'etapa_propia_id' => $this->etapa('cierre'),
        ]);

        try {
            $this->migracionEtapa()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('propios', $e->getMessage());
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }

        $this->asertarEtapaMigrada();
    }

    public function test_la_etapa_en_ambos_sentidos_se_puede_repetir_y_conserva_los_requisitos(): void
    {
        $migracion = $this->migracionEtapa();
        $id = $this->requisito();

        $migracion->up(); // ya migrada: no falla
        $this->asertarEtapaMigrada();

        $migracion->down();
        $migracion->down();
        $this->asertarEtapaRevertida();

        $migracion->up();
        $migracion->up();
        $this->asertarEtapaMigrada();
        $this->assertSame('Estudios previos', DB::table('requisitos_catalogo')->where('id', $id)->value('texto'));
    }

    public function test_reintentar_la_etapa_tras_un_fallo_a_medias_completa_la_migracion(): void
    {
        $migracion = $this->migracionEtapa();
        $migracion->down();

        // Lo que dejaría un fallo de la subida: la primera columna con su clave foránea y la
        // segunda sin ella.
        Schema::table('requisitos_catalogo', function (Blueprint $table) {
            $table->foreignId('etapa_id')->nullable()->constrained('etapas_contratacion')->restrictOnDelete();
        });
        Schema::table('proyectos_requisitos', function (Blueprint $table) {
            $table->unsignedBigInteger('etapa_propia_id')->nullable();
        });
        $this->assertNull($this->foranea('proyectos_requisitos', 'etapa_propia_id'));

        $migracion->up();
        $this->asertarEtapaMigrada();

        // Lo que dejaría un fallo de la bajada: una columna ya sin clave foránea.
        Schema::table('proyectos_requisitos', function (Blueprint $table) {
            $table->dropForeign(['etapa_propia_id']);
        });
        $migracion->down();
        $this->asertarEtapaRevertida();
    }
}
```

En `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`, reemplazar (1 de 2):

```php
{
    use RefreshDatabase;

    private PlantillaChecklist $sgr;

    protected function setUp(): void
```

por:

```php
{
    use RefreshDatabase;

    private const NO_ES_DE_VIABILIZACION = 'La plantilla "Licitación pública" es de contratación y no se usa en un expediente de viabilización.';

    private PlantillaChecklist $sgr;

    protected function setUp(): void
```

En `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`, reemplazar (2 de 2):

```php
        $this->assertCount(2, json_decode($registro->despues, true)['plantillas']);
    }

    // ------------------------------------------------------------ presentación

    public function test_una_fila_compartida_muestra_el_requisito_de_la_principal_y_sus_origenes(): void
```

por:

```php
        $this->assertCount(2, json_decode($registro->despues, true)['plantillas']);
    }

    // ------------------------------------------------------- contratación

    /** Publicada, activa y de todas las fuentes y sectores: compatible en todo salvo el uso. */
    private function plantillaDeContratacion(): PlantillaChecklist
    {
        return $this->plantilla('Licitación pública', ['Estudios previos' => [null]], [
            'uso' => PlantillaChecklist::USO_CONTRATACION, 'todas_modalidades' => true,
        ]);
    }

    public function test_crear_un_expediente_rechaza_una_plantilla_de_contratacion(): void
    {
        $contratacion = $this->plantillaDeContratacion();
        $proyecto = $this->proyecto();

        $this->crear($proyecto, ['fase_sgr' => 3, 'plantilla_ids' => [$contratacion->id]])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'La configuración del expediente no es válida.')
            ->assertJsonPath('errores', [self::NO_ES_DE_VIABILIZACION]);
        $this->crear($proyecto, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $contratacion->id], 'principal_id' => $this->sgr->id])
            ->assertStatus(422)
            ->assertJsonPath('errores', [self::NO_ES_DE_VIABILIZACION]);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_configurar_un_expediente_rechaza_una_plantilla_de_contratacion_y_conserva_la_seleccion(): void
    {
        $contratacion = $this->plantillaDeContratacion();
        $id = $this->crear($this->proyecto(), ['fase_sgr' => 3])->assertCreated()->json('id');

        $this->configurar($id, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $contratacion->id], 'principal_id' => $this->sgr->id])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'La configuración del expediente no es válida.')
            ->assertJsonPath('errores', [self::NO_ES_DE_VIABILIZACION]);

        $this->assertSame([$this->sgr->id], ProyectoExpediente::findOrFail($id)->versionesPlantilla()->pluck('catalogo_versiones.plantilla_id')->all());
    }

    // ------------------------------------------------------------ presentación

    public function test_una_fila_compartida_muestra_el_requisito_de_la_principal_y_sus_origenes(): void
```

En `tests/Feature/Viabilizacion/PlantillasExpedienteApiTest.php`, reemplazar (1 de 1):

```php

        $this->assertSame($anterior, $seleccion['versiones'][$propios->id]);
    }
}
```

por:

```php

        $this->assertSame($anterior, $seleccion['versiones'][$propios->id]);
    }

    // ------------------------------------------------------- contratación

    public function test_una_plantilla_de_contratacion_no_se_ofrece_ni_se_preselecciona_en_un_expediente(): void
    {
        // Activa, publicada, de todas las fuentes y sectores: compatible en todo salvo el uso.
        $contratacion = $this->plantilla('Licitación pública', [], ['Estudios previos' => true], [
            'uso' => PlantillaChecklist::USO_CONTRATACION, 'todas_modalidades' => true,
        ]);
        // Ni siquiera con motor sgr, que es lo que se preselecciona (el administrador no puede
        // crearla así; se fuerza para probar el filtro).
        $conMotorSgr = $this->plantilla('Contratación con motor SGR', [], ['Requisito' => true], [
            'uso' => PlantillaChecklist::USO_CONTRATACION, 'motor' => PlantillaChecklist::MOTOR_SGR,
        ]);
        $proyecto = $this->proyecto(['recursos_propios'], $this->agua);

        $this->assertSame([], $this->servicio()->idsCompatibles($proyecto));
        $this->assertSame([], $this->nombres($this->compatibles($proyecto)->assertOk()));
        $this->assertNull($this->servicio()->sgrCompatible($proyecto));
        $this->assertSame(['plantilla_ids' => [], 'principal_id' => null], $this->servicio()->preseleccion($proyecto));

        $errores = $this->errores(fn () => $this->servicio()->validar(
            ['plantilla_ids' => [$contratacion->id, $conMotorSgr->id], 'principal_id' => $contratacion->id], $proyecto, null
        ));
        $this->assertSame([
            'La plantilla "Licitación pública" es de contratación y no se usa en un expediente de viabilización.',
            'La plantilla "Contratación con motor SGR" es de contratación y no se usa en un expediente de viabilización.',
        ], $errores);
    }

    public function test_un_expediente_automatico_no_recibe_una_plantilla_de_contratacion(): void
    {
        $this->plantilla('Contratación con motor SGR', [], ['Requisito' => true], [
            'uso' => PlantillaChecklist::USO_CONTRATACION, 'motor' => PlantillaChecklist::MOTOR_SGR,
        ]);
        $proyecto = $this->proyecto(['recursos_propios'], $this->agua);
        $proyecto->update(['fase' => DB::table('fases')->insertGetId(['nombre' => 'Formulación'])]);

        $expediente = app(ExpedienteService::class)->crearAutomatico($proyecto->refresh(), null);

        $this->assertNotNull($expediente);
        $this->assertSame([], $expediente->versionesPlantilla()->pluck('catalogo_versiones.id')->all());
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Schema/PlantillasContratacionTest.php tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php tests/Feature/Viabilizacion/PlantillasExpedienteApiTest.php`
Expected: FALLAN 17 y pasan 51: no existen las columnas `uso`, `todas_modalidades` ni `etapa_id`, la tabla `plantilla_modalidades` ni las relaciones del modelo, y una plantilla de contratación todavía se ofrecería a un expediente de viabilización.

- [ ] **Paso 3: Implementar**

Crear `database/migrations/2026_11_17_100015_add_uso_y_modalidades_a_plantillas.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Una plantilla de checklist es de viabilización o de contratación (`uso`). El uso se elige al
 * crearla y no cambia; las que ya existen, también la SGR, quedan de viabilización. Una
 * plantilla de contratación se asocia además a modalidades (plantilla_modalidades) o a todas
 * (`todas_modalidades`). Los valores válidos los controla la aplicación (PlantillaChecklist::USOS).
 */
return new class extends Migration
{
    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. Las claves foráneas van después de la tabla.
        if (! Schema::hasColumn('plantillas_checklist', 'uso')) {
            Schema::table('plantillas_checklist', function (Blueprint $table) {
                $table->string('uso', 20)->default('viabilizacion');
            });
        }
        if (! Schema::hasColumn('plantillas_checklist', 'todas_modalidades')) {
            Schema::table('plantillas_checklist', function (Blueprint $table) {
                $table->boolean('todas_modalidades')->default(false);
            });
        }

        if (! Schema::hasTable('plantilla_modalidades')) {
            Schema::create('plantilla_modalidades', function (Blueprint $table) {
                $table->unsignedBigInteger('plantilla_id');
                $table->unsignedBigInteger('modalidad_id');
                $table->primary(['plantilla_id', 'modalidad_id']);
            });
        }
        if (! $this->foranea('plantilla_id')) {
            Schema::table('plantilla_modalidades', function (Blueprint $table) {
                $table->foreign('plantilla_id')->references('id')->on('plantillas_checklist')->cascadeOnDelete();
            });
        }
        if (! $this->foranea('modalidad_id')) {
            // Una modalidad asociada a una plantilla no se borra: se desactiva.
            Schema::table('plantilla_modalidades', function (Blueprint $table) {
                $table->foreign('modalidad_id')->references('id')->on('modalidades')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        $contratacion = Schema::hasColumn('plantillas_checklist', 'uso')
            && DB::table('plantillas_checklist')->where('uso', '!=', 'viabilizacion')->exists();
        $todas = Schema::hasColumn('plantillas_checklist', 'todas_modalidades')
            && DB::table('plantillas_checklist')->where('todas_modalidades', true)->exists();
        $asociadas = Schema::hasTable('plantilla_modalidades') && DB::table('plantilla_modalidades')->exists();
        if ($contratacion || $todas || $asociadas) {
            throw new RuntimeException('No se puede revertir: hay plantillas de contratación o plantillas asociadas a modalidades, que se perderían. Restaure un respaldo.');
        }

        // Primero el pivote, después las columnas; cada paso con su guarda.
        Schema::dropIfExists('plantilla_modalidades');
        foreach (['todas_modalidades', 'uso'] as $columna) {
            if (Schema::hasColumn('plantillas_checklist', $columna)) {
                Schema::table('plantillas_checklist', function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }
    }

    private function foranea(string $columna): ?array
    {
        return collect(Schema::getForeignKeys('plantilla_modalidades'))->first(fn ($f) => $f['columns'] === [$columna]);
    }
};
```

Crear `database/migrations/2026_11_17_100016_add_etapa_a_requisitos.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Cada requisito de una plantilla de contratación pertenece a una etapa del catálogo
 * (requisitos_catalogo.etapa_id), y un requisito propio de un expediente de contratación elige la
 * suya (proyectos_requisitos.etapa_propia_id). Ambas son opcionales: la viabilización no usa
 * etapas. Una etapa asignada no se borra: se desactiva.
 */
return new class extends Migration
{
    private const COLUMNAS = [
        'requisitos_catalogo' => 'etapa_id',
        'proyectos_requisitos' => 'etapa_propia_id',
    ];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. Primero la columna, después la clave foránea.
        foreach (self::COLUMNAS as $tabla => $columna) {
            if (! Schema::hasColumn($tabla, $columna)) {
                Schema::table($tabla, function (Blueprint $table) use ($columna) {
                    $table->unsignedBigInteger($columna)->nullable();
                });
            }
            if (! $this->foranea($tabla, $columna)) {
                Schema::table($tabla, function (Blueprint $table) use ($columna) {
                    $table->foreign($columna)->references('id')->on('etapas_contratacion')->restrictOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        $existentes = array_filter(self::COLUMNAS, fn (string $columna, string $tabla) => Schema::hasColumn($tabla, $columna), ARRAY_FILTER_USE_BOTH);

        $usadas = [];
        foreach ($existentes as $tabla => $columna) {
            if (DB::table($tabla)->whereNotNull($columna)->exists()) {
                $usadas[] = $tabla === 'requisitos_catalogo' ? 'requisitos de plantilla' : 'requisitos propios de expedientes';
            }
        }
        if ($usadas) {
            throw new RuntimeException('No se puede revertir: hay ' . implode(' y ', $usadas) . ' con una etapa de contratación asignada, que se perdería. Restaure un respaldo.');
        }

        // Primero las claves foráneas, después las columnas; cada paso con su guarda. Se quitan
        // por el nombre que entrega el esquema; SQLite no da nombre y ahí se deja la deducción
        // de Laravel.
        foreach ($existentes as $tabla => $columna) {
            if ($foranea = $this->foranea($tabla, $columna)) {
                $nombre = $foranea['name'] ?? [$columna];
                Schema::table($tabla, function (Blueprint $table) use ($nombre) {
                    $table->dropForeign($nombre);
                });
            }
            Schema::table($tabla, function (Blueprint $table) use ($columna) {
                $table->dropColumn($columna);
            });
        }
    }

    private function foranea(string $tabla, string $columna): ?array
    {
        return collect(Schema::getForeignKeys($tabla))->first(fn ($f) => $f['columns'] === [$columna]);
    }
};
```

En `app/Models/PlantillaChecklist.php`, reemplazar (1 de 2):

```php
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
```

por:

```php
    public const MOTOR_SGR = 'sgr';
    public const MOTOR_SIMPLE = 'simple';

    // El uso se elige al crear la plantilla y no cambia: una de contratación nunca se ofrece a un
    // expediente de viabilización.
    public const USO_VIABILIZACION = 'viabilizacion';
    public const USO_CONTRATACION = 'contratacion';
    public const USOS = [self::USO_VIABILIZACION, self::USO_CONTRATACION];

    protected $table = 'plantillas_checklist';
    protected $fillable = [
        'clave', 'nombre', 'descripcion', 'alcance', 'todas_fuentes', 'todos_sectores',
        'motor', 'activo', 'usuario_creo_id', 'uso', 'todas_modalidades',
    ];
    protected $attributes = [
        'uso' => self::USO_VIABILIZACION,
        'todas_modalidades' => false,
    ];
    protected $casts = [
        'todas_fuentes' => 'boolean',
        'todos_sectores' => 'boolean',
        'todas_modalidades' => 'boolean',
        'activo' => 'boolean',
    ];
```

En `app/Models/PlantillaChecklist.php`, reemplazar (2 de 2):

```php
    {
        return $this->belongsToMany(Sector::class, 'plantilla_sectores', 'plantilla_id', 'sector_id');
    }
}
```

por:

```php
    {
        return $this->belongsToMany(Sector::class, 'plantilla_sectores', 'plantilla_id', 'sector_id');
    }

    /** Modalidades de contratación a las que aplica; solo las tiene una plantilla de contratación. */
    public function modalidades(): BelongsToMany
    {
        return $this->belongsToMany(Modalidad::class, 'plantilla_modalidades', 'plantilla_id', 'modalidad_id');
    }

    public function esContratacion(): bool
    {
        return $this->uso === self::USO_CONTRATACION;
    }
}
```

En `app/Models/ProyectoRequisito.php`, reemplazar (1 de 3):

```php
        'usuario_cumplido_id', 'observacion', 'soporte_archivo', 'soporte_nombre', 'orden',
        'estado_aplicabilidad', 'justificacion_no_aplica', 'usuario_no_aplica_id', 'usuario_aprobo_id',
        'fecha_aprobacion', 'caracter', 'responsable_id',
        'titulo_propio', 'descripcion_propia', 'categoria_propia',
    ];
    protected $attributes = [
        'estado_aplicabilidad' => self::APLICA,
```

por:

```php
        'usuario_cumplido_id', 'observacion', 'soporte_archivo', 'soporte_nombre', 'orden',
        'estado_aplicabilidad', 'justificacion_no_aplica', 'usuario_no_aplica_id', 'usuario_aprobo_id',
        'fecha_aprobacion', 'caracter', 'responsable_id',
        'titulo_propio', 'descripcion_propia', 'categoria_propia', 'etapa_propia_id',
    ];
    protected $attributes = [
        'estado_aplicabilidad' => self::APLICA,
```

En `app/Models/ProyectoRequisito.php`, reemplazar (2 de 3):

```php
        'usuario_aprobo_id' => 'integer',
        'fecha_aprobacion' => 'datetime',
        'responsable_id' => 'integer',
    ];

    /**
```

por:

```php
        'usuario_aprobo_id' => 'integer',
        'fecha_aprobacion' => 'datetime',
        'responsable_id' => 'integer',
        'etapa_propia_id' => 'integer',
    ];

    /**
```

En `app/Models/ProyectoRequisito.php`, reemplazar (3 de 3):

```php
        return $this->belongsToMany(RequisitoCatalogo::class, 'proyecto_requisito_origenes', 'proyecto_requisito_id', 'requisito_catalogo_id');
    }

    /** Requisito agregado en el expediente, con sus textos en la fila y sin plantilla. */
    public function esPropio(): bool
    {
```

por:

```php
        return $this->belongsToMany(RequisitoCatalogo::class, 'proyecto_requisito_origenes', 'proyecto_requisito_id', 'requisito_catalogo_id');
    }

    /**
     * Etapa que eligió un requisito propio de un expediente de contratación. Los de plantilla
     * toman la etapa de su requisito.
     */
    public function etapaPropia(): BelongsTo
    {
        return $this->belongsTo(EtapaContratacion::class, 'etapa_propia_id');
    }

    /** Requisito agregado en el expediente, con sus textos en la fila y sin plantilla. */
    public function esPropio(): bool
    {
```

En `app/Models/RequisitoCatalogo.php`, reemplazar (1 de 3):

```php
        'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
        'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
        'requiere_aprobacion_no_aplica', 'codigo_comun', 'responsable_cumplimiento_id', 'responsable_revision_id',
    ];
    protected $casts = [
        'catalogo_version_id' => 'integer',
```

por:

```php
        'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
        'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
        'requiere_aprobacion_no_aplica', 'codigo_comun', 'responsable_cumplimiento_id', 'responsable_revision_id',
        'etapa_id',
    ];
    protected $casts = [
        'catalogo_version_id' => 'integer',
```

En `app/Models/RequisitoCatalogo.php`, reemplazar (2 de 3):

```php
        'orden' => 'integer',
        'responsable_cumplimiento_id' => 'integer',
        'responsable_revision_id' => 'integer',
        'activo' => 'boolean',
        'requiere_aprobacion_no_aplica' => 'boolean',
    ];
```

por:

```php
        'orden' => 'integer',
        'responsable_cumplimiento_id' => 'integer',
        'responsable_revision_id' => 'integer',
        'etapa_id' => 'integer',
        'activo' => 'boolean',
        'requiere_aprobacion_no_aplica' => 'boolean',
    ];
```

En `app/Models/RequisitoCatalogo.php`, reemplazar (3 de 3):

```php
    {
        return $this->belongsTo(Responsable::class, 'responsable_revision_id');
    }
}
```

por:

```php
    {
        return $this->belongsTo(Responsable::class, 'responsable_revision_id');
    }

    /** Etapa de contratación a la que pertenece. Solo la tienen los requisitos de una plantilla de contratación. */
    public function etapa(): BelongsTo
    {
        return $this->belongsTo(EtapaContratacion::class, 'etapa_id');
    }
}
```

En `app/Services/Viabilizacion/PlantillasExpediente.php`, reemplazar (1 de 3):

```php
 * y con qué versión quedan.
 *
 * Reglas:
 * - Se ofrecen las plantillas activas con versión vigente de alguna fuente del proyecto
 *   (o de todas) y de su sector (o de todos). Sin sector, solo las de todos los sectores.
 * - Una plantilla nueva en el expediente entra con su versión vigente; la que ya tenía
 *   conserva su versión aunque haya dejado de ofrecerse (sin cambios retroactivos), hasta
 *   que se le aplique otra de forma explícita (VersionesExpediente).
```

por:

```php
 * y con qué versión quedan.
 *
 * Reglas:
 * - Se ofrecen las plantillas de viabilización activas con versión vigente de alguna fuente
 *   del proyecto (o de todas) y de su sector (o de todos). Sin sector, solo las de todos los
 *   sectores. Una plantilla de contratación nunca se ofrece ni se elige.
 * - Una plantilla nueva en el expediente entra con su versión vigente; la que ya tenía
 *   conserva su versión aunque haya dejado de ofrecerse (sin cambios retroactivos), hasta
 *   que se le aplique otra de forma explícita (VersionesExpediente).
```

En `app/Services/Viabilizacion/PlantillasExpediente.php`, reemplazar (2 de 3):

```php
        $sector = $proyecto->sector;

        return PlantillaChecklist::query()
            ->where('activo', true)
            ->whereHas('versiones', fn (Builder $q) => $q->vigente())
            ->where(fn (Builder $q) => $q->where('todas_fuentes', true)
```

por:

```php
        $sector = $proyecto->sector;

        return PlantillaChecklist::query()
            ->where('uso', PlantillaChecklist::USO_VIABILIZACION)
            ->where('activo', true)
            ->whereHas('versiones', fn (Builder $q) => $q->vigente())
            ->where(fn (Builder $q) => $q->where('todas_fuentes', true)
```

En `app/Services/Viabilizacion/PlantillasExpediente.php`, reemplazar (3 de 3):

```php

            $version = $previas->get($id) ?? $plantilla->versiones->first();
            if (! $previas->has($id)) {
                if (! $plantilla->activo) {
                    $errores[] = "La plantilla \"{$plantilla->nombre}\" está inactiva.";
                } elseif (! $version) {
                    $errores[] = "La plantilla \"{$plantilla->nombre}\" no tiene una versión publicada.";
```

por:

```php

            $version = $previas->get($id) ?? $plantilla->versiones->first();
            if (! $previas->has($id)) {
                if ($plantilla->esContratacion()) {
                    $errores[] = "La plantilla \"{$plantilla->nombre}\" es de contratación y no se usa en un expediente de viabilización.";
                } elseif (! $plantilla->activo) {
                    $errores[] = "La plantilla \"{$plantilla->nombre}\" está inactiva.";
                } elseif (! $version) {
                    $errores[] = "La plantilla \"{$plantilla->nombre}\" no tiene una versión publicada.";
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Schema/PlantillasContratacionTest.php tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php tests/Feature/Viabilizacion/PlantillasExpedienteApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 814 passed.

- [ ] **Paso 5: Commit**

```bash
git add database/migrations/2026_11_17_100015_add_uso_y_modalidades_a_plantillas.php database/migrations/2026_11_17_100016_add_etapa_a_requisitos.php tests/Feature/Schema/PlantillasContratacionTest.php app/Models/PlantillaChecklist.php app/Models/ProyectoRequisito.php app/Models/RequisitoCatalogo.php app/Services/Viabilizacion/PlantillasExpediente.php tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php tests/Feature/Viabilizacion/PlantillasExpedienteApiTest.php
git commit -m "feat: template usage, modalities and requirement stage columns (migrations 16-17)"
```

---

### Tarea 3: Plantillas de contratación en la administración

Uso, modalidades y etapa en crear, editar, duplicar, listar, guardar requisitos, crear borrador y publicar.

**Files:**
- Test (create): `tests/Feature/ReglaNegocioExceptionTest.php`
- Test (create): `tests/Feature/Viabilizacion/PlantillasContratacionApiTest.php`
- Modify: `app/Exceptions/ReglaNegocioException.php`
- Modify: `app/Http/Controllers/CatalogoRequisitosController.php`
- Modify: `app/Http/Controllers/PlantillaChecklistController.php`
- Modify: `app/Services/Viabilizacion/CatalogoService.php`
- Modify: `app/Services/Viabilizacion/PlantillaService.php`
- Test: `tests/Feature/Viabilizacion/PlantillasAdminApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/ReglaNegocioExceptionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Exceptions\ReglaNegocioException;
use Tests\TestCase;

/** La respuesta 422 de una regla de negocio: mensaje, lista y, si los hay, errores por campo. */
class ReglaNegocioExceptionTest extends TestCase
{
    private function respuesta(ReglaNegocioException $e): array
    {
        $respuesta = $e->render();
        $this->assertSame(422, $respuesta->getStatusCode());

        return $respuesta->getData(true);
    }

    public function test_sin_campos_responde_como_antes(): void
    {
        $this->assertSame(['mensaje' => 'El expediente está radicado.'], $this->respuesta(new ReglaNegocioException('El expediente está radicado.')));
        $this->assertSame(
            ['mensaje' => 'No es válido.', 'errores' => ['Falta el nombre.']],
            $this->respuesta(new ReglaNegocioException('No es válido.', ['Falta el nombre.'])),
        );
    }

    public function test_los_campos_viajan_como_errors_junto_al_mensaje_y_la_lista(): void
    {
        $e = new ReglaNegocioException('El requisito no es válido.', ['Elige la etapa.', 'Falta la descripción.'], ['etapa_id' => ['Elige la etapa.']]);

        $this->assertSame([
            'mensaje' => 'El requisito no es válido.',
            'errores' => ['Elige la etapa.', 'Falta la descripción.'],
            'errors' => ['etapa_id' => ['Elige la etapa.']],
        ], $this->respuesta($e));
        $this->assertSame(['etapa_id' => ['Elige la etapa.']], $e->campos());
        $this->assertSame(['Elige la etapa.', 'Falta la descripción.'], $e->errores());
    }

    public function test_un_campo_sin_mensajes_no_viaja(): void
    {
        $this->assertSame(
            ['mensaje' => 'No es válido.', 'errores' => ['Falta el nombre.']],
            $this->respuesta(new ReglaNegocioException('No es válido.', ['Falta el nombre.'], ['etapa_id' => []])),
        );
    }
}
```

Crear `tests/Feature/Viabilizacion/PlantillasContratacionApiTest.php`:

```php
<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Plantillas de contratación: uso, modalidades y etapa de cada requisito en la administración. */
class PlantillasContratacionApiTest extends TestCase
{
    use RefreshDatabase;

    private int $fuentePropios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->fuentePropios = FuenteFinanciacion::where('clave', FuenteFinanciacion::RECURSOS_PROPIOS)->value('id');
    }

    private function comoAdmin(): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => Rol::Administrador])));
    }

    private function modalidad(string $clave): Modalidad
    {
        return Modalidad::where('clave', $clave)->firstOrFail();
    }

    private function etapa(string $clave): EtapaContratacion
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail();
    }

    private function datos(array $cambios = []): array
    {
        return [
            'nombre' => 'Licitación pública – SECOP II',
            'uso' => PlantillaChecklist::USO_CONTRATACION,
            'todas_fuentes' => true,
            'todos_sectores' => true,
            'modalidad_ids' => [$this->modalidad('licitacion_publica')->id],
            ...$cambios,
        ];
    }

    private function crear(array $cambios = [])
    {
        return $this->comoAdmin()->postJson('/GestPro/plantillas', $this->datos($cambios));
    }

    private function editar(int $id, array $datos)
    {
        return $this->comoAdmin()->putJson("/GestPro/plantillas/{$id}", $datos);
    }

    /** Plantilla de contratación creada por la API, con su borrador. */
    private function plantilla(array $cambios = []): PlantillaChecklist
    {
        return PlantillaChecklist::findOrFail($this->crear($cambios)->assertCreated()->json('id'));
    }

    private function borrador(PlantillaChecklist $plantilla): CatalogoVersion
    {
        return $plantilla->versiones()->where('estado', CatalogoVersion::BORRADOR)->firstOrFail();
    }

    private function requisitos(CatalogoVersion $version): string
    {
        return "/GestPro/catalogo-requisitos/versiones/{$version->id}/requisitos";
    }

    private function crearRequisito(CatalogoVersion $version, array $datos = [])
    {
        return $this->comoAdmin()->postJson($this->requisitos($version), [
            'texto' => 'Estudios previos', 'etapa_id' => $this->etapa('planeacion')->id, ...$datos,
        ]);
    }

    private function publicar(CatalogoVersion $version)
    {
        return $this->comoAdmin()->postJson("/GestPro/catalogo-requisitos/versiones/{$version->id}/publicar");
    }

    // ------------------------------------------------------------- uso

    public function test_crear_exige_el_uso(): void
    {
        $sinUso = $this->datos();
        unset($sinUso['uso']);

        $this->comoAdmin()->postJson('/GestPro/plantillas', $sinUso)->assertStatus(422)
            ->assertJsonPath('errors.uso.0', 'Elige el uso de la plantilla: viabilización o contratación.');
        $this->crear(['uso' => 'ejecucion'])->assertStatus(422)
            ->assertJsonPath('errors.uso.0', 'Elige el uso de la plantilla: viabilización o contratación.');
    }

    public function test_crear_una_plantilla_de_contratacion_la_deja_simple_con_sus_modalidades(): void
    {
        $minima = $this->modalidad('minima_cuantia');

        $r = $this->crear(['modalidad_ids' => [$this->modalidad('licitacion_publica')->id, $minima->id]])->assertCreated()
            ->assertJsonPath('uso', 'contratacion')
            ->assertJsonPath('motor', 'simple')
            ->assertJsonPath('todas_modalidades', false)
            ->assertJsonPath('borrador.numero', 1);

        $this->assertSame([
            ['id' => $this->modalidad('licitacion_publica')->id, 'nombre' => 'Licitación pública', 'activo' => true],
            ['id' => $minima->id, 'nombre' => 'Mínima cuantía', 'activo' => true],
        ], $r->json('modalidades'));
    }

    public function test_con_todas_las_modalidades_no_guarda_modalidades_sueltas(): void
    {
        $plantilla = $this->plantilla(['todas_modalidades' => true]);

        $this->assertTrue($plantilla->todas_modalidades);
        $this->assertSame(0, $plantilla->modalidades()->count());
    }

    public function test_una_plantilla_de_viabilizacion_no_acepta_modalidades(): void
    {
        $viabilizacion = ['uso' => PlantillaChecklist::USO_VIABILIZACION, 'nombre' => 'Inversión', 'modalidad_ids' => []];
        $mensaje = ['Una plantilla de viabilización no se asocia a modalidades: solo las de contratación las tienen.'];

        $this->crear([...$viabilizacion, 'todas_modalidades' => true])->assertStatus(422)->assertJsonPath('errores', $mensaje);
        $this->crear([...$viabilizacion, 'modalidad_ids' => [$this->modalidad('minima_cuantia')->id]])->assertStatus(422)
            ->assertJsonPath('errores', $mensaje);

        $r = $this->crear([...$viabilizacion, 'todas_modalidades' => false])->assertCreated()
            ->assertJsonPath('uso', 'viabilizacion')
            ->assertJsonPath('modalidades', [])
            ->assertJsonPath('todas_modalidades', false);
        $this->assertSame(0, PlantillaChecklist::findOrFail($r->json('id'))->modalidades()->count());
    }

    public function test_el_uso_no_cambia_al_editar(): void
    {
        $plantilla = $this->plantilla();

        $this->editar($plantilla->id, $this->datos(['uso' => PlantillaChecklist::USO_VIABILIZACION]))->assertStatus(422)
            ->assertJsonPath('errores', ['El uso de una plantilla no cambia después de crearla: esta es de contratación. Si necesitas otro uso, crea una plantilla nueva.']);

        $this->editar($plantilla->id, $this->datos(['descripcion' => 'Con el mismo uso']))->assertOk()->assertJsonPath('uso', 'contratacion');
        $sinUso = $this->datos(['descripcion' => 'Sin indicar el uso']);
        unset($sinUso['uso']);
        $this->editar($plantilla->id, $sinUso)->assertOk()->assertJsonPath('uso', 'contratacion');
        $this->assertSame(PlantillaChecklist::USO_CONTRATACION, $plantilla->fresh()->uso);

        $sgr = PlantillaChecklist::sgr();
        $this->editar($sgr->id, ['nombre' => 'SGR', 'uso' => 'contratacion', 'fuente_ids' => [FuenteFinanciacion::where('clave', 'sgr')->value('id')], 'todos_sectores' => true])
            ->assertStatus(422)
            ->assertJsonPath('errores.0', 'El uso de una plantilla no cambia después de crearla: esta es de viabilización. Si necesitas otro uso, crea una plantilla nueva.');
    }

    // ------------------------------------------------------------- modalidades

    public function test_editar_cambia_las_modalidades_y_sin_ellas_en_el_pedido_las_conserva(): void
    {
        $plantilla = $this->plantilla();
        $minima = $this->modalidad('minima_cuantia');

        $this->editar($plantilla->id, $this->datos(['modalidad_ids' => [$minima->id]]))->assertOk()
            ->assertJsonPath('modalidades', [['id' => $minima->id, 'nombre' => 'Mínima cuantía', 'activo' => true]]);

        $sinModalidades = $this->datos(['descripcion' => 'Otra']);
        unset($sinModalidades['modalidad_ids']);
        $this->editar($plantilla->id, $sinModalidades)->assertOk()
            ->assertJsonPath('modalidades.0.id', $minima->id)
            ->assertJsonPath('todas_modalidades', false);

        $this->editar($plantilla->id, $this->datos(['todas_modalidades' => true]))->assertOk()
            ->assertJsonPath('todas_modalidades', true)
            ->assertJsonPath('modalidades', []);
    }

    public function test_no_asocia_una_modalidad_inactiva_o_inexistente_pero_conserva_la_inactiva_que_ya_tenia(): void
    {
        $plantilla = $this->plantilla();
        $licitacion = $this->modalidad('licitacion_publica');
        $licitacion->update(['activo' => false]);

        $this->editar($plantilla->id, $this->datos(['descripcion' => 'Editada']))->assertOk()
            ->assertJsonPath('modalidades', [['id' => $licitacion->id, 'nombre' => 'Licitación pública', 'activo' => false]]);

        $this->crear(['nombre' => 'Otra', 'modalidad_ids' => [$licitacion->id, 99999]])->assertStatus(422)
            ->assertJsonPath('errores', [
                'La modalidad "Licitación pública" está inactiva.',
                'La modalidad con id 99999 no existe.',
            ]);
    }

    public function test_la_lista_filtra_por_uso_y_trae_el_uso_y_las_modalidades(): void
    {
        $this->plantilla();

        $contratacion = $this->comoAdmin()->getJson('/GestPro/plantillas?uso=contratacion')->assertOk()->json();
        $this->assertSame(['Licitación pública – SECOP II'], array_column($contratacion, 'nombre'));
        $this->assertSame('contratacion', $contratacion[0]['uso']);
        $this->assertFalse($contratacion[0]['todas_modalidades']);
        $this->assertSame('Licitación pública', $contratacion[0]['modalidades'][0]['nombre']);

        $viabilizacion = $this->comoAdmin()->getJson('/GestPro/plantillas?uso=viabilizacion')->assertOk()->json();
        $this->assertSame(['SGR'], array_column($viabilizacion, 'nombre'));

        $this->assertCount(2, $this->comoAdmin()->getJson('/GestPro/plantillas')->assertOk()->json());
        $this->comoAdmin()->getJson('/GestPro/plantillas?uso=otro')->assertStatus(422)
            ->assertJsonPath('errors.uso.0', 'Elige el uso de la plantilla: viabilización o contratación.');
    }

    // ------------------------------------------------------------- etapa de los requisitos

    public function test_un_requisito_de_contratacion_exige_su_etapa(): void
    {
        $borrador = $this->borrador($this->plantilla());

        $this->crearRequisito($borrador, ['etapa_id' => null])->assertStatus(422)
            ->assertJsonPath('errores', ['Elige la etapa de contratación del requisito.']);
        $sinEtapa = $this->comoAdmin()->postJson($this->requisitos($borrador), ['texto' => 'Estudios previos'])->assertStatus(422);
        $sinEtapa->assertJsonPath('errores', ['Elige la etapa de contratación del requisito.']);

        $this->crearRequisito($borrador)->assertCreated()->assertJsonPath('etapa_id', $this->etapa('planeacion')->id);
    }

    public function test_la_etapa_debe_ser_un_entero_de_una_etapa_activa(): void
    {
        $borrador = $this->borrador($this->plantilla());
        $adendas = $this->etapa('adendas');
        $adendas->update(['activo' => false]);

        foreach (['1', true, 1.5] as $valor) {
            $this->crearRequisito($borrador, ['etapa_id' => $valor])->assertStatus(422)
                ->assertJsonPath('errors.etapa_id.0', 'Revisa la etapa: debe ser un número entero.');
        }
        $this->crearRequisito($borrador, ['etapa_id' => 99999])->assertStatus(422)
            ->assertJsonPath('errores', ['La etapa de contratación con id 99999 no existe.']);
        $this->crearRequisito($borrador, ['etapa_id' => $adendas->id])->assertStatus(422)
            ->assertJsonPath('errores', ['La etapa "Adendas" está inactiva.']);
    }

    public function test_al_editar_conserva_la_etapa_inactiva_que_ya_tenia_y_sin_etapa_en_el_pedido_no_la_cambia(): void
    {
        $borrador = $this->borrador($this->plantilla());
        $adendas = $this->etapa('adendas');
        $id = $this->crearRequisito($borrador, ['etapa_id' => $adendas->id])->assertCreated()->json('id');
        $adendas->update(['activo' => false]);
        $url = $this->requisitos($borrador) . "/{$id}";

        $this->comoAdmin()->putJson($url, ['texto' => 'Adendas publicadas', 'etapa_id' => $adendas->id])->assertOk()
            ->assertJsonPath('etapa_id', $adendas->id);
        $this->comoAdmin()->putJson($url, ['texto' => 'Adendas publicadas a tiempo'])->assertOk()
            ->assertJsonPath('etapa_id', $adendas->id);
        $this->comoAdmin()->putJson($url, ['texto' => 'Adendas', 'etapa_id' => null])->assertStatus(422)
            ->assertJsonPath('errores', ['Elige la etapa de contratación del requisito.']);

        $cierre = $this->etapa('cierre');
        $this->comoAdmin()->putJson($url, ['texto' => 'Acta de cierre', 'etapa_id' => $cierre->id])->assertOk()
            ->assertJsonPath('etapa_id', $cierre->id);
        $this->comoAdmin()->putJson($url, ['texto' => 'Acta de cierre', 'etapa_id' => $adendas->id])->assertStatus(422)
            ->assertJsonPath('errores', ['La etapa "Adendas" está inactiva.']);
    }

    public function test_un_requisito_de_viabilizacion_no_acepta_etapa(): void
    {
        $simple = PlantillaChecklist::findOrFail($this->crear(['uso' => PlantillaChecklist::USO_VIABILIZACION, 'nombre' => 'Inversión', 'modalidad_ids' => []])->assertCreated()->json('id'));
        $mensaje = ['Los requisitos de una plantilla de viabilización no llevan etapa de contratación.'];

        $this->crearRequisito($this->borrador($simple))->assertStatus(422)
            ->assertJsonPath('errores', $mensaje)->assertJsonPath('errors', ['etapa_id' => $mensaje]);
        $this->comoAdmin()->postJson($this->requisitos($this->borrador($simple)), ['texto' => 'Sin etapa'])->assertCreated()
            ->assertJsonPath('etapa_id', null);

        $borradorSgr = app(CatalogoService::class)->crearBorrador('Ajustes');
        $this->comoAdmin()->postJson($this->requisitos($borradorSgr), [
            'clave' => 'a99', 'seccion' => 'A', 'fuente' => 'a12', 'codigo' => '99', 'texto' => 'Nuevo', 'condicion' => 'siempre',
            'etapa_id' => $this->etapa('planeacion')->id,
        ])->assertStatus(422)->assertJsonPath('errores', $mensaje)->assertJsonPath('errors', ['etapa_id' => $mensaje]);
    }

    public function test_los_errores_de_la_etapa_vienen_ademas_en_su_campo(): void
    {
        $borrador = $this->borrador($this->plantilla());
        $sinEtapa = 'Elige la etapa de contratación del requisito.';

        // Solo la etapa: en la lista, para quien lee el aviso, y en errors.etapa_id, para el campo.
        $this->crearRequisito($borrador, ['etapa_id' => null])->assertStatus(422)->assertExactJson([
            'mensaje' => 'El requisito no es válido.',
            'errores' => [$sinEtapa],
            'errors' => ['etapa_id' => [$sinEtapa]],
        ]);

        // Con otros errores: la lista los trae todos, la etapa primero; el campo, solo los suyos.
        $this->crearRequisito($borrador, ['etapa_id' => 99999, 'caracter' => RequisitoCatalogo::CONDICIONADO])->assertStatus(422)->assertExactJson([
            'mensaje' => 'El requisito no es válido.',
            'errores' => ['La etapa de contratación con id 99999 no existe.', 'Un requisito condicionado necesita describir cuándo aplica.'],
            'errors' => ['etapa_id' => ['La etapa de contratación con id 99999 no existe.']],
        ]);

        // Sin error de etapa no hay errors.
        $this->crearRequisito($borrador, ['caracter' => RequisitoCatalogo::CONDICIONADO])->assertStatus(422)->assertExactJson([
            'mensaje' => 'El requisito no es válido.',
            'errores' => ['Un requisito condicionado necesita describir cuándo aplica.'],
        ]);
    }

    public function test_la_lista_de_requisitos_trae_la_etapa(): void
    {
        $borrador = $this->borrador($this->plantilla());
        $cierre = $this->etapa('cierre');
        $this->crearRequisito($borrador, ['etapa_id' => $cierre->id])->assertCreated();

        $fila = $this->comoAdmin()->getJson($this->requisitos($borrador))->assertOk()->json('0');

        $this->assertSame($cierre->id, $fila['etapa_id']);
        $this->assertSame(['id' => $cierre->id, 'clave' => 'cierre', 'nombre' => 'Cierre y apertura de ofertas', 'orden' => 7, 'activo' => true], $fila['etapa']);
    }

    // ------------------------------------------------------------- publicar, borrador y copia

    public function test_publicar_exige_modalidades_o_todas(): void
    {
        $plantilla = $this->plantilla(['modalidad_ids' => []]);
        $borrador = $this->borrador($plantilla);
        $this->crearRequisito($borrador)->assertCreated();

        $this->publicar($borrador)->assertStatus(422)
            ->assertJsonPath('mensaje', 'Asocia la plantilla de contratación a sus modalidades, o marca "Todas las modalidades", antes de publicar.');

        $plantilla->update(['todas_modalidades' => true]);
        $this->publicar($borrador)->assertOk()->assertJsonPath('estado', 'publicada');
    }

    public function test_publicar_exige_la_etapa_de_cada_requisito_activo(): void
    {
        $borrador = $this->borrador($this->plantilla());
        $this->crearRequisito($borrador, ['texto' => 'Estudios previos'])->assertCreated();
        $this->crearRequisito($borrador, ['texto' => 'CDP'])->assertCreated();
        $this->crearRequisito($borrador, ['texto' => 'Retirado', 'activo' => false])->assertCreated();
        // Lo que dejaría un requisito anterior a las etapas, o una copia de datos sin ellas.
        $borrador->requisitos()->whereIn('texto', ['CDP', 'Retirado'])->update(['etapa_id' => null]);

        $this->publicar($borrador)->assertStatus(422)
            ->assertJsonPath('mensaje', 'La versión tiene requisitos sin etapa de contratación y no puede publicarse.')
            ->assertJsonPath('errores', ['CDP: elige su etapa de contratación.']);

        $borrador->requisitos()->where('texto', 'CDP')->update(['etapa_id' => $this->etapa('planeacion')->id]);
        $this->publicar($borrador)->assertOk()->assertJsonPath('estado', 'publicada');
    }

    public function test_un_borrador_nuevo_copia_la_etapa_de_cada_requisito(): void
    {
        $plantilla = $this->plantilla();
        $primero = $this->borrador($plantilla);
        $this->crearRequisito($primero, ['etapa_id' => $this->etapa('cierre')->id])->assertCreated();
        $this->publicar($primero)->assertOk();

        $segundo = app(CatalogoService::class)->crearBorrador('Ajustes', $plantilla);

        $this->assertSame([$this->etapa('cierre')->id], $segundo->requisitos()->pluck('etapa_id')->all());
    }

    public function test_duplicar_conserva_el_uso_las_modalidades_y_las_etapas(): void
    {
        $minima = $this->modalidad('minima_cuantia');
        $plantilla = $this->plantilla(['modalidad_ids' => [$this->modalidad('licitacion_publica')->id, $minima->id]]);
        $borrador = $this->borrador($plantilla);
        $this->crearRequisito($borrador, ['etapa_id' => $this->etapa('adendas')->id])->assertCreated();
        $this->publicar($borrador)->assertOk();
        $this->etapa('adendas')->update(['activo' => false]);
        $minima->update(['activo' => false]);

        $r = $this->comoAdmin()->postJson("/GestPro/plantillas/{$plantilla->id}/duplicar", ['nombre' => 'Licitación pública – sin plataforma'])
            ->assertCreated()
            ->assertJsonPath('uso', 'contratacion')
            ->assertJsonPath('motor', 'simple')
            ->assertJsonPath('todas_modalidades', false)
            ->assertJsonPath('modalidades', [['id' => $this->modalidad('licitacion_publica')->id, 'nombre' => 'Licitación pública', 'activo' => true]]);

        $copia = PlantillaChecklist::findOrFail($r->json('id'));
        $this->assertSame([$this->etapa('adendas')->id], $this->borrador($copia)->requisitos()->pluck('etapa_id')->all());

        $plantilla->update(['todas_modalidades' => true]);
        $plantilla->modalidades()->sync([]);
        $this->comoAdmin()->postJson("/GestPro/plantillas/{$plantilla->id}/duplicar", ['nombre' => 'Copia con todas'])
            ->assertCreated()->assertJsonPath('todas_modalidades', true);
    }

    public function test_una_plantilla_de_contratacion_solo_usa_el_motor_simple(): void
    {
        $plantilla = $this->plantilla();
        $borrador = $this->borrador($plantilla);
        $this->crearRequisito($borrador)->assertCreated();
        $this->publicar($borrador)->assertOk();
        // Forzada en la base: la administración no deja crearla así.
        $plantilla->update(['motor' => PlantillaChecklist::MOTOR_SGR]);

        $this->comoAdmin()->postJson("/GestPro/plantillas/{$plantilla->id}/duplicar", ['nombre' => 'Copia SGR', 'motor' => 'sgr'])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'Una plantilla de contratación solo puede usar el motor simple.');
    }

    public function test_duplicar_una_de_viabilizacion_la_deja_de_viabilizacion(): void
    {
        $sgr = PlantillaChecklist::sgr();
        $requisito = $sgr->versionVigente()->requisitos()->orderBy('orden')->first();

        $this->comoAdmin()->postJson("/GestPro/plantillas/{$sgr->id}/duplicar", ['nombre' => 'Copia SGR', 'requisito_ids' => [$requisito->id]])
            ->assertCreated()
            ->assertJsonPath('uso', 'viabilizacion')
            ->assertJsonPath('modalidades', []);
        $this->assertNull(RequisitoCatalogo::where('texto', $requisito->texto)->latest('id')->value('etapa_id'));
    }
}
```

En `tests/Feature/Viabilizacion/PlantillasAdminApiTest.php`, reemplazar (1 de 1):

```php
    {
        return [
            'nombre' => 'Requisitos generales de inversión pública',
            'descripcion' => 'Para todo proyecto de inversión',
            'alcance' => 'general',
            'todas_fuentes' => false,
```

por:

```php
    {
        return [
            'nombre' => 'Requisitos generales de inversión pública',
            'uso' => PlantillaChecklist::USO_VIABILIZACION,
            'descripcion' => 'Para todo proyecto de inversión',
            'alcance' => 'general',
            'todas_fuentes' => false,
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/ReglaNegocioExceptionTest.php tests/Feature/Viabilizacion/PlantillasContratacionApiTest.php tests/Feature/Viabilizacion/PlantillasAdminApiTest.php`
Expected: FALLAN 21 y pasan 25: `ReglaNegocioException` no acepta campos, y crear, editar, duplicar, listar, guardar requisitos y publicar todavía no conocen el uso, las modalidades ni la etapa.

- [ ] **Paso 3: Implementar**

En `app/Exceptions/ReglaNegocioException.php`, reemplazar (1 de 2):

```php
 * Violación de una regla de negocio (p. ej. editar un expediente radicado).
 * Laravel llama a render() automáticamente: responde 422 con un mensaje legible
 * en vez de un 500.
 */
class ReglaNegocioException extends RuntimeException
{
    /** @param list<string> $errores */
    public function __construct(string $mensaje, private readonly array $errores = [])
    {
        parent::__construct($mensaje);
    }
```

por:

```php
 * Violación de una regla de negocio (p. ej. editar un expediente radicado).
 * Laravel llama a render() automáticamente: responde 422 con un mensaje legible
 * en vez de un 500.
 *
 * `errores` es la lista completa de lo que impide la operación. `campos`, opcional, dice además
 * a qué campo del formulario corresponde alguno de esos mensajes (campo => mensajes): viaja como
 * `errors`, con la misma forma que la validación de Laravel, para que la pantalla lo muestre bajo
 * el campo. Quien lo usa repite esos mensajes en `errores`, que es lo que lee el resto.
 */
class ReglaNegocioException extends RuntimeException
{
    /**
     * @param list<string> $errores
     * @param array<string, list<string>> $campos
     */
    public function __construct(
        string $mensaje,
        private readonly array $errores = [],
        private readonly array $campos = [],
    ) {
        parent::__construct($mensaje);
    }
```

En `app/Exceptions/ReglaNegocioException.php`, reemplazar (2 de 2):

```php
        return $this->errores;
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'mensaje' => $this->getMessage(),
            'errores' => $this->errores ?: null,
        ]), 422);
    }
}
```

por:

```php
        return $this->errores;
    }

    /** @return array<string, list<string>> */
    public function campos(): array
    {
        return $this->campos;
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'mensaje' => $this->getMessage(),
            'errores' => $this->errores ?: null,
            'errors' => array_filter($this->campos) ?: null,
        ]), 422);
    }
}
```

En `app/Http/Controllers/CatalogoRequisitosController.php`, reemplazar (1 de 3):

```php
            $version->requisitos()->with([
                'sector:id,clave,nombre', 'tipo:id,clave,nombre',
                'responsableCumplimiento:id,nombre,cargo,activo', 'responsableRevision:id,nombre,cargo,activo',
            ])->orderBy('orden')->get()
        );
    }
```

por:

```php
            $version->requisitos()->with([
                'sector:id,clave,nombre', 'tipo:id,clave,nombre',
                'responsableCumplimiento:id,nombre,cargo,activo', 'responsableRevision:id,nombre,cargo,activo',
                'etapa:id,clave,nombre,orden,activo',
            ])->orderBy('orden')->get()
        );
    }
```

En `app/Http/Controllers/CatalogoRequisitosController.php`, reemplazar (2 de 3):

```php
        'fuente' => 'la fuente normativa', 'sector_id' => 'el sector', 'tipo_id' => 'el tipo de proyecto',
        'condicion' => 'la condición', 'nota' => 'las observaciones', 'orden' => 'el orden',
        'activo' => 'el estado activo', 'ids' => 'el orden de los requisitos', 'ids.*' => 'cada requisito',
    ];

    private const MENSAJES = [
```

por:

```php
        'fuente' => 'la fuente normativa', 'sector_id' => 'el sector', 'tipo_id' => 'el tipo de proyecto',
        'condicion' => 'la condición', 'nota' => 'las observaciones', 'orden' => 'el orden',
        'activo' => 'el estado activo', 'ids' => 'el orden de los requisitos', 'ids.*' => 'cada requisito',
        'etapa_id' => 'la etapa',
    ];

    private const MENSAJES = [
```

En `app/Http/Controllers/CatalogoRequisitosController.php`, reemplazar (3 de 3):

```php
            // Tope: el orden de una fila SGR no debe salir de su sección (ExpedienteService).
            'orden' => ['sometimes', 'integer', 'min:0', 'max:99999'],
            'activo' => ['sometimes', 'boolean'],
        ];

        if ($version->plantilla->motor === PlantillaChecklist::MOTOR_SIMPLE) {
```

por:

```php
            // Tope: el orden de una fila SGR no debe salir de su sección (ExpedienteService).
            'orden' => ['sometimes', 'integer', 'min:0', 'max:99999'],
            'activo' => ['sometimes', 'boolean'],
            // Si la plantilla la exige o la rechaza, y si está activa, lo decide el servicio.
            'etapa_id' => ['nullable', 'bail', 'integer', $entero],
        ];

        if ($version->plantilla->motor === PlantillaChecklist::MOTOR_SIMPLE) {
```

En `app/Http/Controllers/PlantillaChecklistController.php`, reemplazar (1 de 5):

```php
        'nombre.max' => 'El nombre admite hasta 199 caracteres.',
        'alcance.in' => 'Elige el alcance: general o específica.',
        'motor.in' => 'El motor debe ser sgr o simple.',
    ];

    private const ATRIBUTOS = [
```

por:

```php
        'nombre.max' => 'El nombre admite hasta 199 caracteres.',
        'alcance.in' => 'Elige el alcance: general o específica.',
        'motor.in' => 'El motor debe ser sgr o simple.',
        'uso.required' => 'Elige el uso de la plantilla: viabilización o contratación.',
        'uso.in' => 'Elige el uso de la plantilla: viabilización o contratación.',
    ];

    private const ATRIBUTOS = [
```

En `app/Http/Controllers/PlantillaChecklistController.php`, reemplazar (2 de 5):

```php
        'fuente_ids.*' => 'cada fuente',
        'sector_ids' => 'los sectores',
        'sector_ids.*' => 'cada sector',
        'activo' => 'el estado activo',
        'motor' => 'el motor',
        'requisito_ids' => 'los requisitos',
```

por:

```php
        'fuente_ids.*' => 'cada fuente',
        'sector_ids' => 'los sectores',
        'sector_ids.*' => 'cada sector',
        'uso' => 'el uso',
        'todas_modalidades' => 'la opción "Todas las modalidades"',
        'modalidad_ids' => 'las modalidades',
        'modalidad_ids.*' => 'cada modalidad',
        'activo' => 'el estado activo',
        'motor' => 'el motor',
        'requisito_ids' => 'los requisitos',
```

En `app/Http/Controllers/PlantillaChecklistController.php`, reemplazar (3 de 5):

```php

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate(['buscar' => ['nullable', 'string', 'max:199']], self::MENSAJES, self::ATRIBUTOS);

        return response()->json($this->plantillas->listar($datos['buscar'] ?? null));
    }

    public function show(PlantillaChecklist $plantilla): JsonResponse
```

por:

```php

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:199'],
            'uso' => ['nullable', Rule::in(PlantillaChecklist::USOS)],
        ], self::MENSAJES, self::ATRIBUTOS);

        return response()->json($this->plantillas->listar($datos['buscar'] ?? null, $datos['uso'] ?? null));
    }

    public function show(PlantillaChecklist $plantilla): JsonResponse
```

En `app/Http/Controllers/PlantillaChecklistController.php`, reemplazar (4 de 5):

```php

    public function store(Request $request): JsonResponse
    {
        $plantilla = $this->plantillas->crear($request->validate($this->reglas(), self::MENSAJES, self::ATRIBUTOS), $request->user());

        return response()->json($this->plantillas->presentar($plantilla), 201);
    }

    public function update(Request $request, PlantillaChecklist $plantilla): JsonResponse
    {
        $this->plantillas->actualizar($plantilla, $request->validate($this->reglas(), self::MENSAJES, self::ATRIBUTOS));

        return response()->json($this->plantillas->presentar($plantilla->refresh()));
    }
```

por:

```php

    public function store(Request $request): JsonResponse
    {
        $reglas = [...$this->reglas(), 'uso' => ['required', Rule::in(PlantillaChecklist::USOS)]];
        $plantilla = $this->plantillas->crear($request->validate($reglas, self::MENSAJES, self::ATRIBUTOS), $request->user());

        return response()->json($this->plantillas->presentar($plantilla), 201);
    }

    public function update(Request $request, PlantillaChecklist $plantilla): JsonResponse
    {
        // El uso no cambia: si llega, el servicio comprueba que sea el mismo.
        $reglas = [...$this->reglas(), 'uso' => ['sometimes', Rule::in(PlantillaChecklist::USOS)]];
        $this->plantillas->actualizar($plantilla, $request->validate($reglas, self::MENSAJES, self::ATRIBUTOS));

        return response()->json($this->plantillas->presentar($plantilla->refresh()));
    }
```

En `app/Http/Controllers/PlantillaChecklistController.php`, reemplazar (5 de 5):

```php
            'fuente_ids.*' => ['integer'],
            'sector_ids' => ['sometimes', 'array'],
            'sector_ids.*' => ['integer'],
        ];
    }
}
```

por:

```php
            'fuente_ids.*' => ['integer'],
            'sector_ids' => ['sometimes', 'array'],
            'sector_ids.*' => ['integer'],
            // Solo en contratación; en viabilización el servicio las rechaza.
            'todas_modalidades' => ['sometimes', 'boolean'],
            'modalidad_ids' => ['sometimes', 'array'],
            'modalidad_ids.*' => ['integer'],
        ];
    }
}
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (1 de 10):

```php

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Models\RequisitoComponente;
```

por:

```php

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Models\RequisitoComponente;
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (2 de 10):

```php
                'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
                'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
                'requiere_aprobacion_no_aplica', 'codigo_comun', 'responsable_cumplimiento_id', 'responsable_revision_id',
            ];
            $vigente?->requisitos()->orderBy('id')->select($columnas)->get()
                ->map(fn ($r) => ['catalogo_version_id' => $borrador->id, ...$r->only($columnas)])
```

por:

```php
                'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
                'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
                'requiere_aprobacion_no_aplica', 'codigo_comun', 'responsable_cumplimiento_id', 'responsable_revision_id',
                'etapa_id',
            ];
            $vigente?->requisitos()->orderBy('id')->select($columnas)->get()
                ->map(fn ($r) => ['catalogo_version_id' => $borrador->id, ...$r->only($columnas)])
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (3 de 10):

```php
            if ($sinFuentes || $sinSectores) {
                throw new ReglaNegocioException('Asocia la plantilla a sus fuentes y sectores antes de publicar.');
            }

            [$errores, $mensaje] = $plantilla->motor === PlantillaChecklist::MOTOR_SGR
                ? [$this->erroresDeCondiciones($actual), 'La versión tiene condiciones inválidas y no puede publicarse.']
```

por:

```php
            if ($sinFuentes || $sinSectores) {
                throw new ReglaNegocioException('Asocia la plantilla a sus fuentes y sectores antes de publicar.');
            }
            if ($plantilla->esContratacion()) {
                $this->exigirContratacionCompleta($plantilla, $actual);
            }

            [$errores, $mensaje] = $plantilla->motor === PlantillaChecklist::MOTOR_SGR
                ? [$this->erroresDeCondiciones($actual), 'La versión tiene condiciones inválidas y no puede publicarse.']
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (4 de 10):

```php
        return $version->refresh();
    }

    /** Motor sgr: cada condición debe ser válida en el DSL. En la sección C es opcional. */
    private function erroresDeCondiciones(CatalogoVersion $version): array
    {
```

por:

```php
        return $version->refresh();
    }

    /** Contratación: la plantilla aplica a alguna modalidad y cada requisito activo tiene su etapa. */
    private function exigirContratacionCompleta(PlantillaChecklist $plantilla, CatalogoVersion $version): void
    {
        if (! $plantilla->todas_modalidades && ! $plantilla->modalidades()->exists()) {
            throw new ReglaNegocioException('Asocia la plantilla de contratación a sus modalidades, o marca "Todas las modalidades", antes de publicar.');
        }

        $sinEtapa = $version->requisitos()->where('activo', true)->whereNull('etapa_id')
            ->orderBy('orden')->orderBy('id')
            ->pluck('texto')
            ->map(fn (string $texto) => "{$texto}: elige su etapa de contratación.")
            ->all();
        if ($sinEtapa) {
            throw new ReglaNegocioException('La versión tiene requisitos sin etapa de contratación y no puede publicarse.', $sinEtapa);
        }
    }

    /** Motor sgr: cada condición debe ser válida en el DSL. En la sección C es opcional. */
    private function erroresDeCondiciones(CatalogoVersion $version): array
    {
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (5 de 10):

```php
        }

        $errores = [];
        $seccion = $datos['seccion'];

        $claveRepetida = RequisitoCatalogo::where('catalogo_version_id', $version->id)
            ->where('clave', $datos['clave'])
```

por:

```php
        }

        $errores = [];
        $erroresEtapa = [];
        $seccion = $datos['seccion'];
        $this->etapaDelRequisito($version, $datos, $requisito, $erroresEtapa);

        $claveRepetida = RequisitoCatalogo::where('catalogo_version_id', $version->id)
            ->where('clave', $datos['clave'])
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (6 de 10):

```php
        }
        $datos['condicion'] = $condicion !== '' ? $condicion : null;

        if ($errores) {
            throw new ReglaNegocioException('El requisito no es válido.', $errores);
        }

        $valores = [
            'catalogo_version_id' => $version->id,
```

por:

```php
        }
        $datos['condicion'] = $condicion !== '' ? $condicion : null;

        $this->rechazarSiHayErrores($erroresEtapa, $errores);

        $valores = [
            'catalogo_version_id' => $version->id,
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (7 de 10):

```php
    private function guardarRequisitoSimple(CatalogoVersion $version, array $datos, ?RequisitoCatalogo $requisito): RequisitoCatalogo
    {
        $errores = [];
        $clave = trim((string) ($datos['clave'] ?? '')) ?: ($requisito?->clave ?? $this->claveLibre($version));

        $repetida = fn (string $columna, ?string $valor) => $valor !== null && RequisitoCatalogo::where('catalogo_version_id', $version->id)
            ->where($columna, $valor)
```

por:

```php
    private function guardarRequisitoSimple(CatalogoVersion $version, array $datos, ?RequisitoCatalogo $requisito): RequisitoCatalogo
    {
        $errores = [];
        $erroresEtapa = [];
        $clave = trim((string) ($datos['clave'] ?? '')) ?: ($requisito?->clave ?? $this->claveLibre($version));
        $etapa = $this->etapaDelRequisito($version, $datos, $requisito, $erroresEtapa);

        $repetida = fn (string $columna, ?string $valor) => $valor !== null && RequisitoCatalogo::where('catalogo_version_id', $version->id)
            ->where($columna, $valor)
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (8 de 10):

```php
            $errores[] = 'Un requisito condicionado necesita describir cuándo aplica.';
        }

        if ($errores) {
            throw new ReglaNegocioException('El requisito no es válido.', $errores);
        }

        $valores = [
            'catalogo_version_id' => $version->id,
```

por:

```php
            $errores[] = 'Un requisito condicionado necesita describir cuándo aplica.';
        }

        $this->rechazarSiHayErrores($erroresEtapa, $errores);

        $valores = [
            'catalogo_version_id' => $version->id,
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (9 de 10):

```php
            'activo' => array_key_exists('activo', $datos) ? (bool) $datos['activo'] : ($requisito?->activo ?? true),
            'codigo_comun' => $codigoComun,
            'requiere_aprobacion_no_aplica' => (bool) ($datos['requiere_aprobacion_no_aplica'] ?? $requisito?->requiere_aprobacion_no_aplica ?? false),
            ...$seguimiento,
        ];
```

por:

```php
            'activo' => array_key_exists('activo', $datos) ? (bool) $datos['activo'] : ($requisito?->activo ?? true),
            'codigo_comun' => $codigoComun,
            'requiere_aprobacion_no_aplica' => (bool) ($datos['requiere_aprobacion_no_aplica'] ?? $requisito?->requiere_aprobacion_no_aplica ?? false),
            'etapa_id' => $etapa,
            ...$seguimiento,
        ];
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (10 de 10):

```php
        return RequisitoCatalogo::create($valores);
    }

    /** Campos que la propuesta pide a todo requisito. Lo que no llega conserva su valor. */
    private function camposDeSeguimiento(array $datos, ?RequisitoCatalogo $requisito, bool $sinCaracter = false): array
    {
```

por:

```php
        return RequisitoCatalogo::create($valores);
    }

    /**
     * El requisito no se guarda si hay errores. Los de la etapa van primero en la lista y, además,
     * en el campo `etapa_id`, para que la pantalla los muestre bajo el select sin adivinar cuáles son.
     *
     * @param list<string> $deEtapa
     * @param list<string> $otros
     */
    private function rechazarSiHayErrores(array $deEtapa, array $otros): void
    {
        if ($deEtapa || $otros) {
            throw new ReglaNegocioException('El requisito no es válido.', [...$deEtapa, ...$otros], $deEtapa ? ['etapa_id' => $deEtapa] : []);
        }
    }

    /**
     * Etapa de contratación del requisito. En una plantilla de contratación es obligatoria: una
     * etapa activa o la que ya tenía, aunque esté inactiva; sin el campo en el pedido, no cambia.
     * En una de viabilización no se acepta. Los errores se agregan a $errores.
     */
    private function etapaDelRequisito(CatalogoVersion $version, array $datos, ?RequisitoCatalogo $requisito, array &$errores): ?int
    {
        $pedida = array_key_exists('etapa_id', $datos) ? $datos['etapa_id'] : $requisito?->etapa_id;

        if (! $version->plantilla->esContratacion()) {
            if ($pedida !== null) {
                $errores[] = 'Los requisitos de una plantilla de viabilización no llevan etapa de contratación.';
            }

            return null;
        }

        if ($pedida === null) {
            $errores[] = 'Elige la etapa de contratación del requisito.';

            return null;
        }
        $etapa = EtapaContratacion::find($pedida);
        if (! $etapa) {
            $errores[] = "La etapa de contratación con id {$pedida} no existe.";
        } elseif (! $etapa->activo && $etapa->id !== $requisito?->etapa_id) {
            $errores[] = "La etapa \"{$etapa->nombre}\" está inactiva.";
        }

        return (int) $pedida;
    }

    /** Campos que la propuesta pide a todo requisito. Lo que no llega conserva su valor. */
    private function camposDeSeguimiento(array $datos, ?RequisitoCatalogo $requisito, bool $sinCaracter = false): array
    {
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (1 de 17):

```php
use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Models\RequisitoSector;
```

por:

```php
use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Models\RequisitoSector;
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (2 de 17):

```php
use Illuminate\Support\Str;

/**
 * Administración de plantillas de checklist (rol Administrador): datos generales,
 * fuentes y sectores a los que aplican, y activación. Los requisitos y las versiones
 * se administran con CatalogoService.
 */
final class PlantillaService
{
    public const ALCANCES = ['general', 'especifica'];

    /** Norma que indicaba la fuente de un requisito SGR, para conservarla al pasar a simple. */
    private const NORMAS = ['a12' => 'Acuerdo 12 de 2024', 'ot' => 'Orientaciones Transitorias'];
```

por:

```php
use Illuminate\Support\Str;

/**
 * Administración de plantillas de checklist (rol Administrador): datos generales, uso,
 * fuentes, sectores y modalidades a los que aplican, y activación. Los requisitos y las
 * versiones se administran con CatalogoService.
 *
 * El uso (viabilización o contratación) se elige al crear y no cambia, ni al copiar. Solo una
 * plantilla de contratación se asocia a modalidades, y solo usa el motor simple.
 */
final class PlantillaService
{
    public const ALCANCES = ['general', 'especifica'];

    /** Cómo se nombra cada uso en los mensajes. */
    private const NOMBRES_USO = [
        PlantillaChecklist::USO_VIABILIZACION => 'viabilización',
        PlantillaChecklist::USO_CONTRATACION => 'contratación',
    ];

    /** Norma que indicaba la fuente de un requisito SGR, para conservarla al pasar a simple. */
    private const NORMAS = ['a12' => 'Acuerdo 12 de 2024', 'ot' => 'Orientaciones Transitorias'];
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (3 de 17):

```php
        return ['requisitos', 'requisitos as requisitos_activos_count' => fn ($q) => $q->where('activo', true)];
    }

    public function listar(?string $buscar): array
    {
        $buscar = trim((string) $buscar);

        return PlantillaChecklist::query()
            ->with(['fuentes:id,nombre', 'sectores:id,nombre', 'versiones' => fn ($q) => $q->withCount($this->cuentasRequisitos())])
            ->when($buscar !== '', function ($q) use ($buscar) {
                $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $buscar) . '%';
                $q->where(fn ($w) => $w->whereRaw("nombre like ? escape '!'", [$patron])->orWhereRaw("descripcion like ? escape '!'", [$patron]));
```

por:

```php
        return ['requisitos', 'requisitos as requisitos_activos_count' => fn ($q) => $q->where('activo', true)];
    }

    /** Modalidades en el orden del catálogo. */
    private function modalidadesOrdenadas(): \Closure
    {
        return fn ($q) => $q->orderBy('modalidades.orden')->orderBy('modalidades.nombre');
    }

    public function listar(?string $buscar, ?string $uso = null): array
    {
        $buscar = trim((string) $buscar);

        return PlantillaChecklist::query()
            ->with([
                'fuentes:id,nombre', 'sectores:id,nombre', 'modalidades' => $this->modalidadesOrdenadas(),
                'versiones' => fn ($q) => $q->withCount($this->cuentasRequisitos()),
            ])
            ->when($uso !== null, fn ($q) => $q->where('uso', $uso))
            ->when($buscar !== '', function ($q) use ($buscar) {
                $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $buscar) . '%';
                $q->where(fn ($w) => $w->whereRaw("nombre like ? escape '!'", [$patron])->orWhereRaw("descripcion like ? escape '!'", [$patron]));
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (4 de 17):

```php
    public function presentar(PlantillaChecklist $plantilla): array
    {
        $plantilla->load([
            'fuentes:id,nombre', 'sectores:id,nombre',
            'versiones' => fn ($q) => $q->withCount([...$this->cuentasRequisitos(), 'expedientes'])->with('usuarioPublico:id,name')->orderByDesc('numero'),
        ]);
```

por:

```php
    public function presentar(PlantillaChecklist $plantilla): array
    {
        $plantilla->load([
            'fuentes:id,nombre', 'sectores:id,nombre', 'modalidades' => $this->modalidadesOrdenadas(),
            'versiones' => fn ($q) => $q->withCount([...$this->cuentasRequisitos(), 'expedientes'])->with('usuarioPublico:id,name')->orderByDesc('numero'),
        ]);
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (5 de 17):

```php
        ];
    }

    /** Las plantillas nuevas usan el motor simple y nacen con su primera versión en borrador. */
    public function crear(array $datos, ?User $usuario): PlantillaChecklist
    {
        $asociaciones = $this->validar($datos, null);
```

por:

```php
        ];
    }

    /**
     * Las plantillas nuevas usan el motor simple y nacen con su primera versión en borrador.
     * El uso llega validado por el controlador.
     */
    public function crear(array $datos, ?User $usuario): PlantillaChecklist
    {
        $asociaciones = $this->validar($datos, null);
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (6 de 17):

```php
        return DB::transaction(function () use ($datos, $asociaciones, $usuario) {
            $plantilla = PlantillaChecklist::create([
                ...$this->datosGenerales($datos),
                'motor' => PlantillaChecklist::MOTOR_SIMPLE,
                'activo' => true,
                'usuario_creo_id' => $usuario?->id,
```

por:

```php
        return DB::transaction(function () use ($datos, $asociaciones, $usuario) {
            $plantilla = PlantillaChecklist::create([
                ...$this->datosGenerales($datos),
                'uso' => $datos['uso'],
                'todas_modalidades' => $asociaciones['todas_modalidades'],
                'motor' => PlantillaChecklist::MOTOR_SIMPLE,
                'activo' => true,
                'usuario_creo_id' => $usuario?->id,
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (7 de 17):

```php
        $asociaciones = $this->validar($datos, $plantilla);

        return DB::transaction(function () use ($plantilla, $datos, $asociaciones) {
            $plantilla->update($this->datosGenerales($datos));
            $this->asociar($plantilla, $asociaciones);

            return $plantilla;
```

por:

```php
        $asociaciones = $this->validar($datos, $plantilla);

        return DB::transaction(function () use ($plantilla, $datos, $asociaciones) {
            $plantilla->update([...$this->datosGenerales($datos), 'todas_modalidades' => $asociaciones['todas_modalidades']]);
            $this->asociar($plantilla, $asociaciones);

            return $plantilla;
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (8 de 17):

```php
     * Copia independiente de una plantilla, en borrador, con los requisitos de su versión
     * vigente que se elijan y el mismo código común. Una copia con motor sgr puede pasar a
     * simple: las condiciones automáticas se vuelven condicionados que decide una persona.
     */
    public function duplicar(PlantillaChecklist $origen, array $datos, ?User $usuario): PlantillaChecklist
    {
```

por:

```php
     * Copia independiente de una plantilla, en borrador, con los requisitos de su versión
     * vigente que se elijan y el mismo código común. Una copia con motor sgr puede pasar a
     * simple: las condiciones automáticas se vuelven condicionados que decide una persona.
     * La copia conserva el uso y, en contratación, las modalidades activas y la etapa de cada
     * requisito.
     */
    public function duplicar(PlantillaChecklist $origen, array $datos, ?User $usuario): PlantillaChecklist
    {
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (9 de 17):

```php
            ?? throw new ReglaNegocioException('La plantilla no tiene una versión publicada que copiar.');

        $motor = $datos['motor'] ?? $origen->motor;
        if ($origen->motor === PlantillaChecklist::MOTOR_SIMPLE && $motor !== PlantillaChecklist::MOTOR_SIMPLE) {
            throw new ReglaNegocioException('Una plantilla simple solo se puede copiar como simple.');
        }
```

por:

```php
            ?? throw new ReglaNegocioException('La plantilla no tiene una versión publicada que copiar.');

        $motor = $datos['motor'] ?? $origen->motor;
        if ($origen->esContratacion() && $motor !== PlantillaChecklist::MOTOR_SIMPLE) {
            throw new ReglaNegocioException('Una plantilla de contratación solo puede usar el motor simple.');
        }
        if ($origen->motor === PlantillaChecklist::MOTOR_SIMPLE && $motor !== PlantillaChecklist::MOTOR_SIMPLE) {
            throw new ReglaNegocioException('Una plantilla simple solo se puede copiar como simple.');
        }
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (10 de 17):

```php

        $general = [
            'nombre' => $datos['nombre'],
            'descripcion' => $origen->descripcion,
            'alcance' => $origen->alcance,
            'todas_fuentes' => $origen->todas_fuentes,
```

por:

```php

        $general = [
            'nombre' => $datos['nombre'],
            'uso' => $origen->uso,
            'descripcion' => $origen->descripcion,
            'alcance' => $origen->alcance,
            'todas_fuentes' => $origen->todas_fuentes,
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (11 de 17):

```php
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
```

por:

```php
            'fuente_ids' => $origen->fuentes()->where('activo', true)->pluck('fuentes_financiacion.id')->all(),
            'sector_ids' => $origen->sectores()->where('activo', true)->pluck('sectores.id')->all(),
        ];
        if ($origen->esContratacion()) {
            // Sin modalidades activas que heredar, la copia queda sin ellas: publicar las pide.
            $general['todas_modalidades'] = $origen->todas_modalidades;
            $general['modalidad_ids'] = $origen->modalidades()->where('activo', true)->pluck('modalidades.id')->all();
        }
        $asociaciones = $this->validar($general, null);

        return DB::transaction(function () use ($general, $asociaciones, $motor, $requisitos, $vigente, $origen, $usuario) {
            $copia = PlantillaChecklist::create([
                ...$this->datosGenerales($general),
                'uso' => $general['uso'],
                'todas_modalidades' => $asociaciones['todas_modalidades'],
                'motor' => $motor,
                'activo' => true,
                'usuario_creo_id' => $usuario?->id,
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (12 de 17):

```php
                        'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
                        'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
                        'requiere_aprobacion_no_aplica', 'codigo_comun', 'responsable_cumplimiento_id', 'responsable_revision_id',
                    ]),
                    'catalogo_version_id' => $borrador->id,
                    'orden' => $posicion + 1,
```

por:

```php
                        'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
                        'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
                        'requiere_aprobacion_no_aplica', 'codigo_comun', 'responsable_cumplimiento_id', 'responsable_revision_id',
                        'etapa_id',
                    ]),
                    'catalogo_version_id' => $borrador->id,
                    'orden' => $posicion + 1,
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (13 de 17):

```php
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
```

por:

```php
            'nombre' => $plantilla->nombre,
            'descripcion' => $plantilla->descripcion,
            'alcance' => $plantilla->alcance,
            'uso' => $plantilla->uso,
            'motor' => $plantilla->motor,
            'activo' => $plantilla->activo,
            'todas_fuentes' => $plantilla->todas_fuentes,
            'todos_sectores' => $plantilla->todos_sectores,
            'todas_modalidades' => $plantilla->todas_modalidades,
            'fuentes' => $plantilla->fuentes->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre])->values()->all(),
            'sectores' => $plantilla->sectores->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre])->values()->all(),
            'modalidades' => $plantilla->modalidades->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre, 'activo' => $m->activo])->values()->all(),
            'vigente' => $version($vigente),
            'borrador' => $version($borrador),
        ];
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (14 de 17):

```php
        ];
    }

    /** @return array{fuente_ids: list<int>, sector_ids: list<int>} */
    private function validar(array $datos, ?PlantillaChecklist $plantilla): array
    {
        $errores = [];

        $nombre = trim((string) ($datos['nombre'] ?? ''));
        // Se compara en PHP para que MariaDB y SQLite ignoren igual mayúsculas y tildes.
        $normalizar = fn (string $texto) => Str::lower(Str::ascii(trim($texto)));
```

por:

```php
        ];
    }

    /**
     * Al crear, el uso llega en los datos; al editar es el de la plantilla y, si llega otro, es
     * un error. Las modalidades que no llegan al editar se conservan.
     *
     * @return array{fuente_ids: list<int>, sector_ids: list<int>, todas_modalidades: bool, modalidad_ids: list<int>}
     */
    private function validar(array $datos, ?PlantillaChecklist $plantilla): array
    {
        $errores = [];

        $uso = $plantilla?->uso ?? $datos['uso'];
        if ($plantilla && isset($datos['uso']) && $datos['uso'] !== $plantilla->uso) {
            $errores[] = 'El uso de una plantilla no cambia después de crearla: esta es de ' . self::NOMBRES_USO[$plantilla->uso] . '. Si necesitas otro uso, crea una plantilla nueva.';
        }

        $nombre = trim((string) ($datos['nombre'] ?? ''));
        // Se compara en PHP para que MariaDB y SQLite ignoren igual mayúsculas y tildes.
        $normalizar = fn (string $texto) => Str::lower(Str::ascii(trim($texto)));
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (15 de 17):

```php
            $errores[] = 'Elige al menos un sector o marca "Todos los sectores".';
        }

        // La plantilla SGR debe seguir ofreciéndose a los proyectos de regalías.
        if ($plantilla?->clave === PlantillaChecklist::CLAVE_SGR && ! $todasFuentes) {
            $sgr = FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id');
```

por:

```php
            $errores[] = 'Elige al menos un sector o marca "Todos los sectores".';
        }

        // Las modalidades pueden faltar mientras se arma la plantilla: publicar las exige.
        $todasModalidades = false;
        $modalidadIds = [];
        if ($uso === PlantillaChecklist::USO_CONTRATACION) {
            $previas = $plantilla?->modalidades()->pluck('modalidades.id')->all() ?? [];
            $todasModalidades = (bool) ($datos['todas_modalidades'] ?? $plantilla?->todas_modalidades ?? false);
            $modalidadIds = match (true) {
                $todasModalidades => [],
                array_key_exists('modalidad_ids', $datos) => $this->idsValidos(Modalidad::class, $datos['modalidad_ids'], $previas, ['La modalidad', 'inactiva'], $errores),
                default => array_map('intval', $previas),
            };
        } elseif (! empty($datos['todas_modalidades']) || ! empty($datos['modalidad_ids'])) {
            $errores[] = 'Una plantilla de viabilización no se asocia a modalidades: solo las de contratación las tienen.';
        }

        // La plantilla SGR debe seguir ofreciéndose a los proyectos de regalías.
        if ($plantilla?->clave === PlantillaChecklist::CLAVE_SGR && ! $todasFuentes) {
            $sgr = FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id');
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (16 de 17):

```php
            throw new ReglaNegocioException('La plantilla no es válida.', $errores);
        }

        return ['fuente_ids' => $fuenteIds, 'sector_ids' => $sectorIds];
    }

    /** Ids que existen; uno inactivo solo si la plantilla ya lo tenía. */
```

por:

```php
            throw new ReglaNegocioException('La plantilla no es válida.', $errores);
        }

        return ['fuente_ids' => $fuenteIds, 'sector_ids' => $sectorIds, 'todas_modalidades' => $todasModalidades, 'modalidad_ids' => $modalidadIds];
    }

    /** Ids que existen; uno inactivo solo si la plantilla ya lo tenía. */
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (17 de 17):

```php
    {
        $plantilla->fuentes()->sync($asociaciones['fuente_ids']);
        $plantilla->sectores()->sync($asociaciones['sector_ids']);
    }
}
```

por:

```php
    {
        $plantilla->fuentes()->sync($asociaciones['fuente_ids']);
        $plantilla->sectores()->sync($asociaciones['sector_ids']);
        $plantilla->modalidades()->sync($asociaciones['modalidad_ids']);
    }
}
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/ReglaNegocioExceptionTest.php tests/Feature/Viabilizacion/PlantillasContratacionApiTest.php tests/Feature/Viabilizacion/PlantillasAdminApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 837 passed.

- [ ] **Paso 5: Commit**

```bash
git add tests/Feature/ReglaNegocioExceptionTest.php tests/Feature/Viabilizacion/PlantillasContratacionApiTest.php app/Exceptions/ReglaNegocioException.php app/Http/Controllers/CatalogoRequisitosController.php app/Http/Controllers/PlantillaChecklistController.php app/Services/Viabilizacion/CatalogoService.php app/Services/Viabilizacion/PlantillaService.php tests/Feature/Viabilizacion/PlantillasAdminApiTest.php
git commit -m "feat: contracting templates with modalities and requirement stages"
```

---

### Tarea 4: Comando para sembrar la plantilla de licitación pública

`plantillas:sembrar-contratacion` crea el borrador "Licitación pública – SECOP II" a partir de la guía de Colombia Compra.

**Files:**
- Create: `app/Services/Contratacion/PlantillaLicitacionPublica.php`
- Test (create): `tests/Feature/Contratacion/SembrarPlantillaContratacionTest.php`
- Modify: `app/Services/Viabilizacion/PlantillaService.php`
- Modify: `routes/console.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/SembrarPlantillaContratacionTest.php`:

```php
<?php

namespace Tests\Feature\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\RequisitoCatalogo;
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Comando plantillas:sembrar-contratacion: el borrador "Licitación pública – SECOP II". */
class SembrarPlantillaContratacionTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'licitacion_publica_secop_ii';
    private const NOMBRE = 'Licitación pública – SECOP II';
    private const REFERENCIA = 'Guía CCE-SEC-G1-18 (SECOP II, licitación pública)';

    /** Requisitos que siembra cada etapa (sección 4 del diseño). */
    private const POR_ETAPA = [
        'planeacion' => 4,
        'pliego_borrador' => 6,
        'publicacion_borrador' => 2,
        'observaciones_borrador' => 1,
        'pliego_definitivo' => 2,
        'adendas' => 1,
        'cierre' => 1,
        'evaluacion' => 2,
        'observaciones_informe' => 2,
        'adjudicacion' => 2,
        'contrato' => 2,
    ];

    /** Los condicionados y cuándo aplican; el resto es obligatorio. */
    private const CONDICIONADOS = [
        'Código BPIN' => 'Si el gasto es de inversión.',
        'Porcentaje mínimo para pequeños productores (Decreto 248 de 2021)' => 'Si se compran alimentos.',
        'Adendas publicadas a más tardar 3 días hábiles antes del cierre' => 'Si hubo adendas.',
        'Subsanaciones solicitadas e incorporadas' => 'Si hubo subsanaciones.',
        'Nuevo informe de evaluación' => 'Si cambió la evaluación.',
    ];

    private function sembrada(): ?PlantillaChecklist
    {
        return PlantillaChecklist::where('clave', self::CLAVE)->first();
    }

    private function requisitos(PlantillaChecklist $plantilla)
    {
        return RequisitoCatalogo::query()
            ->whereIn('catalogo_version_id', $plantilla->versiones()->select('id'))
            ->with('etapa')
            ->orderBy('orden')
            ->get();
    }

    public function test_crea_la_plantilla_de_contratacion_en_borrador_con_los_requisitos_de_cada_etapa(): void
    {
        $this->artisan('plantillas:sembrar-contratacion')
            ->expectsOutputToContain('Plantilla "' . self::NOMBRE . '" creada en borrador con 25 requisitos.')
            ->assertSuccessful();

        $plantilla = $this->sembrada();
        $this->assertNotNull($plantilla);
        $this->assertSame(self::NOMBRE, $plantilla->nombre);
        $this->assertSame(PlantillaChecklist::USO_CONTRATACION, $plantilla->uso);
        $this->assertSame(PlantillaChecklist::MOTOR_SIMPLE, $plantilla->motor);
        $this->assertTrue($plantilla->activo);
        $this->assertTrue($plantilla->todas_fuentes);
        $this->assertTrue($plantilla->todos_sectores);
        $this->assertFalse($plantilla->todas_modalidades);
        $this->assertSame(['licitacion_publica'], $plantilla->modalidades()->pluck('clave')->all());

        // Solo un borrador: no se publica hasta que la entidad la valide.
        $this->assertSame([CatalogoVersion::BORRADOR], $plantilla->versiones()->pluck('estado')->all());
        $this->assertNull($plantilla->versionVigente());

        $requisitos = $this->requisitos($plantilla);
        $this->assertSame(self::POR_ETAPA, $requisitos->countBy(fn ($r) => $r->etapa->clave)->all());
        $this->assertSame(range(1, 25), $requisitos->pluck('orden')->all());
        // En el orden del catálogo de etapas.
        $this->assertSame($requisitos->pluck('etapa.orden')->sort()->values()->all(), $requisitos->pluck('etapa.orden')->all());
        $this->assertTrue($requisitos->every(fn ($r) => $r->activo && $r->referencia_normativa === self::REFERENCIA));

        $condicionados = $requisitos->where('caracter', RequisitoCatalogo::CONDICIONADO);
        $this->assertSame(self::CONDICIONADOS, $condicionados->pluck('condicion_descripcion', 'texto')->all());
        $this->assertSame(20, $requisitos->where('caracter', RequisitoCatalogo::OBLIGATORIO)->count());

        $this->assertSame('pliego_borrador', $requisitos->firstWhere('texto', 'Porcentaje mínimo para pequeños productores (Decreto 248 de 2021)')->etapa->clave);
        $this->assertSame('adendas', $requisitos->firstWhere('texto', 'Adendas publicadas a más tardar 3 días hábiles antes del cierre')->etapa->clave);
        $this->assertStringContainsString('la guía no lo detalla', $requisitos->firstWhere('texto', 'Garantías aprobadas')->nota);
    }

    public function test_no_se_ofrece_a_los_expedientes_de_viabilizacion_ni_publicada(): void
    {
        $this->artisan('plantillas:sembrar-contratacion')->assertSuccessful();
        $plantilla = $this->sembrada();
        $proyecto = Proyecto::create(['nombre' => 'Acueducto']);
        $proyecto->fuentes()->sync(FuenteFinanciacion::pluck('id')->all());
        $plantillas = app(PlantillasExpediente::class);

        $this->assertNotContains($plantilla->id, $plantillas->idsCompatibles($proyecto));

        // Publicarla (lo hará un administrador) tampoco la ofrece: es de contratación.
        $borrador = $plantilla->versiones()->sole();
        app(CatalogoService::class)->publicar($borrador);
        $this->assertSame(CatalogoVersion::PUBLICADA, $borrador->fresh()->estado);

        $this->assertNotContains($plantilla->id, $plantillas->idsCompatibles($proyecto));
        $this->assertNotContains($plantilla->id, array_column($plantillas->disponibles($proyecto)['plantillas'], 'id'));
        $this->assertNull($plantillas->sgrCompatible($proyecto));
    }

    public function test_una_segunda_ejecucion_no_duplica_nada(): void
    {
        $this->artisan('plantillas:sembrar-contratacion')->assertSuccessful();

        $this->artisan('plantillas:sembrar-contratacion')
            ->expectsOutputToContain('La plantilla "' . self::NOMBRE . '" ya existe: no se creó nada.')
            ->assertSuccessful();

        $this->assertSame(1, PlantillaChecklist::where('clave', self::CLAVE)->count());
        $this->assertSame(1, $this->sembrada()->versiones()->count());
        $this->assertCount(25, $this->requisitos($this->sembrada()));
    }

    public function test_en_simulacion_lista_lo_que_crearia_sin_escribir(): void
    {
        $plantillas = PlantillaChecklist::count();

        $this->artisan('plantillas:sembrar-contratacion', ['--simular' => true])
            ->expectsOutputToContain('Planeación y estudios previos: Código BPIN (condicionado: Si el gasto es de inversión.)')
            ->expectsOutputToContain('Contrato y garantías: Garantías aprobadas')
            ->expectsOutputToContain('Se crearía la plantilla "' . self::NOMBRE . '" en borrador con 25 requisitos.')
            ->assertSuccessful();

        $this->assertSame($plantillas, PlantillaChecklist::count());
        $this->assertDatabaseCount('requisitos_catalogo', 0);
    }

    public function test_falla_con_un_mensaje_claro_si_falta_una_etapa_sembrada(): void
    {
        EtapaContratacion::whereIn('clave', ['adendas', 'cierre'])->delete();

        $this->artisan('plantillas:sembrar-contratacion')
            ->expectsOutputToContain('Faltan etapas de contratación sembradas: adendas, cierre.')
            ->assertFailed();

        $this->assertNull($this->sembrada());
    }

    public function test_falla_con_un_mensaje_claro_si_una_etapa_esta_inactiva(): void
    {
        EtapaContratacion::where('clave', 'adendas')->update(['activo' => false]);

        $this->artisan('plantillas:sembrar-contratacion', ['--simular' => true])
            ->expectsOutputToContain('Hay etapas de contratación inactivas: adendas.')
            ->assertFailed();

        $this->assertNull($this->sembrada());
    }

    public function test_falla_con_un_mensaje_claro_si_falta_la_modalidad_sembrada(): void
    {
        Modalidad::where('clave', 'licitacion_publica')->delete();

        $this->artisan('plantillas:sembrar-contratacion')
            ->expectsOutputToContain('Falta la modalidad sembrada licitacion_publica.')
            ->assertFailed();

        $this->assertNull($this->sembrada());
    }

    public function test_si_ya_hay_una_plantilla_con_el_mismo_nombre_no_crea_nada_y_lo_dice(): void
    {
        DB::table('plantillas_checklist')->insert(['nombre' => self::NOMBRE, 'motor' => 'simple', 'todas_fuentes' => true, 'todos_sectores' => true]);

        $this->artisan('plantillas:sembrar-contratacion')
            ->expectsOutputToContain('Ya existe una plantilla llamada "' . self::NOMBRE . '".')
            ->assertFailed();

        $this->assertNull($this->sembrada());
        $this->assertDatabaseCount('requisitos_catalogo', 0);
    }

    public function test_en_simulacion_tambien_avisa_si_ya_hay_una_plantilla_con_el_mismo_nombre(): void
    {
        // Sin la clave y con otras mayúsculas y sin tildes: PlantillaService lo tomaría por el mismo nombre.
        DB::table('plantillas_checklist')->insert(['nombre' => 'LICITACION PUBLICA – SECOP II', 'motor' => 'simple', 'todas_fuentes' => true, 'todos_sectores' => true]);

        $this->artisan('plantillas:sembrar-contratacion', ['--simular' => true])
            ->expectsOutputToContain('Ya existe una plantilla llamada "LICITACION PUBLICA – SECOP II".')
            ->doesntExpectOutputToContain('Se crearía la plantilla')
            ->assertFailed();

        $this->assertNull($this->sembrada());
    }

    public function test_si_falla_a_mitad_de_la_siembra_no_deja_nada(): void
    {
        $plantillas = PlantillaChecklist::count();
        $versiones = CatalogoVersion::count();
        // Un error al guardar el requisito 15, cuando ya hay plantilla, modalidad, borrador y 14 requisitos.
        RequisitoCatalogo::creating(function (RequisitoCatalogo $requisito) {
            if ($requisito->texto === 'Pliego definitivo publicado') {
                throw new ReglaNegocioException('El requisito no es válido.', ['Falla simulada.']);
            }
        });

        $this->artisan('plantillas:sembrar-contratacion')
            ->expectsOutputToContain('Falla simulada.')
            ->assertFailed();

        $this->assertNull($this->sembrada());
        $this->assertSame($plantillas, PlantillaChecklist::count());
        $this->assertDatabaseCount('requisitos_catalogo', 0);
        $this->assertDatabaseCount('plantilla_modalidades', 0);
        $this->assertSame($versiones, CatalogoVersion::count());
    }

    public function test_el_comando_describe_lo_que_hace(): void
    {
        $this->artisan('list')
            ->expectsOutputToContain('Crea en borrador la plantilla de contratación "Licitación pública – SECOP II"')
            ->assertSuccessful();
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/SembrarPlantillaContratacionTest.php`
Expected: FALLAN las 11: el comando `plantillas:sembrar-contratacion` no existe.

- [ ] **Paso 3: Implementar**

Crear `app/Services/Contratacion/PlantillaLicitacionPublica.php`:

```php
<?php

namespace App\Services\Contratacion;

use App\Models\EtapaContratacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\PlantillaService;
use Illuminate\Support\Facades\DB;

/**
 * Borrador de la plantilla de contratación "Licitación pública – SECOP II", armado a partir de
 * la guía CCE-SEC-G1-18 de Colombia Compra (sección 4 del diseño de contratación). Lo usa el
 * comando plantillas:sembrar-contratacion.
 *
 * Se crea con los mismos servicios que la pantalla de plantillas, para que valide igual, y queda
 * en borrador: no se ofrece a ningún proceso hasta que la entidad la valide y un administrador
 * la publique. Todos los ítems son obligatorios salvo los condicionados, que dicen cuándo aplican.
 */
final class PlantillaLicitacionPublica
{
    public const CLAVE = 'licitacion_publica_secop_ii';
    public const NOMBRE = 'Licitación pública – SECOP II';
    public const MODALIDAD = 'licitacion_publica';
    public const REFERENCIA = 'Guía CCE-SEC-G1-18 (SECOP II, licitación pública)';

    private const DESCRIPCION = 'Borrador a partir de la guía CCE-SEC-G1-18 de Colombia Compra (SECOP II, licitación pública). '
        . 'La entidad (jurídica o contratación) debe validarlo antes de publicarlo.';

    /**
     * Requisitos en el orden en que se siembran: [clave de la etapa, texto, cuándo aplica si es
     * condicionado, observaciones].
     *
     * @var list<array{0: string, 1: string, 2?: ?string, 3?: string}>
     */
    private const REQUISITOS = [
        ['planeacion', 'Estudios previos'],
        ['planeacion', 'Certificado de disponibilidad presupuestal (CDP)'],
        ['planeacion', 'Vínculo con el Plan Anual de Adquisiciones o su justificación'],
        ['planeacion', 'Código BPIN', 'Si el gasto es de inversión.'],
        ['pliego_borrador', 'Proceso creado en SECOP II con su número'],
        ['pliego_borrador', 'Cronograma con todos los hitos, incluidas las audiencias de riesgos y de adjudicación'],
        ['pliego_borrador', 'Cuestionario y lista de precios configurados'],
        ['pliego_borrador', 'Modelo de evaluación y criterios de desempate'],
        ['pliego_borrador', 'Garantías definidas'],
        // La guía lo trae al configurar el proceso: el pliego fija el porcentaje.
        ['pliego_borrador', 'Porcentaje mínimo para pequeños productores (Decreto 248 de 2021)', 'Si se compran alimentos.'],
        ['publicacion_borrador', 'Aviso de convocatoria publicado con la misma fecha que los estudios previos'],
        ['publicacion_borrador', 'Proyecto de pliego publicado'],
        ['observaciones_borrador', 'Todas las observaciones respondidas y publicadas'],
        ['pliego_definitivo', 'Acto administrativo de apertura'],
        ['pliego_definitivo', 'Pliego definitivo publicado'],
        ['adendas', 'Adendas publicadas a más tardar 3 días hábiles antes del cierre', 'Si hubo adendas.'],
        ['cierre', 'Lista de oferentes o acta de cierre publicada'],
        ['evaluacion', 'Informe de evaluación publicado con su plazo de observaciones'],
        ['evaluacion', 'Subsanaciones solicitadas e incorporadas', 'Si hubo subsanaciones.'],
        ['observaciones_informe', 'Respuestas a las observaciones al informe publicadas'],
        ['observaciones_informe', 'Nuevo informe de evaluación', 'Si cambió la evaluación.'],
        ['adjudicacion', 'Acto de adjudicación o de declaratoria de desierto, en firme'],
        ['adjudicacion', 'Informe de selección publicado'],
        ['contrato', 'Contrato electrónico aceptado por el contratista'],
        ['contrato', 'Garantías aprobadas', null, 'A validar por la entidad: la guía no lo detalla.'],
    ];

    public function __construct(
        private readonly PlantillaService $plantillas,
        private readonly CatalogoService $catalogo,
    ) {
    }

    public function existente(): ?PlantillaChecklist
    {
        return PlantillaChecklist::where('clave', self::CLAVE)->first();
    }

    /**
     * Lo que impide sembrar: otra plantilla ya tiene el nombre (sin la clave, que existente()
     * descarta antes; PlantillaService lo rechazaría igual), o la modalidad o alguna etapa no
     * existen o están inactivas. Así --simular dice lo mismo que la ejecución real.
     *
     * @return list<string>
     */
    public function impedimentos(): array
    {
        $errores = [];

        if ($repetido = $this->plantillas->nombreEnUso(self::NOMBRE)) {
            $errores[] = "Ya existe una plantilla llamada \"{$repetido}\". Cámbiele el nombre en Plantillas de checklist para poder sembrar esta.";
        }

        $modalidad = Modalidad::where('clave', self::MODALIDAD)->first();
        if (! $modalidad) {
            $errores[] = 'Falta la modalidad sembrada ' . self::MODALIDAD . '. Ejecute las migraciones.';
        } elseif (! $modalidad->activo) {
            $errores[] = "La modalidad \"{$modalidad->nombre}\" está inactiva. Actívela en Parámetros → Modalidades.";
        }

        $claves = $this->clavesDeEtapas();
        $etapas = EtapaContratacion::whereIn('clave', $claves)->get()->keyBy('clave');
        $faltan = array_values(array_filter($claves, fn (string $clave) => ! $etapas->has($clave)));
        $inactivas = $etapas->reject->activo->keys()->all();
        if ($faltan) {
            $errores[] = 'Faltan etapas de contratación sembradas: ' . implode(', ', $faltan) . '. Ejecute las migraciones.';
        }
        if ($inactivas) {
            $errores[] = 'Hay etapas de contratación inactivas: ' . implode(', ', $inactivas) . '. Actívelas en Parámetros → Etapas de contratación.';
        }

        return $errores;
    }

    /**
     * Los requisitos que se sembrarían, con el nombre de su etapa.
     *
     * @return list<array{etapa: string, texto: string, condicion: ?string}>
     */
    public function vistaPrevia(): array
    {
        $nombres = EtapaContratacion::whereIn('clave', $this->clavesDeEtapas())->pluck('nombre', 'clave');

        return array_map(fn (array $r) => [
            'etapa' => $nombres[$r[0]] ?? $r[0],
            'texto' => $r[1],
            'condicion' => $r[2] ?? null,
        ], self::REQUISITOS);
    }

    public function cantidad(): int
    {
        return count(self::REQUISITOS);
    }

    /**
     * Crea la plantilla con su versión en borrador y los requisitos, todo o nada. Quien llama
     * comprueba antes existente() e impedimentos(); los servicios vuelven a validarlo todo.
     */
    public function sembrar(): PlantillaChecklist
    {
        return DB::transaction(function () {
            $plantilla = $this->plantillas->crear([
                'nombre' => self::NOMBRE,
                'descripcion' => self::DESCRIPCION,
                'uso' => PlantillaChecklist::USO_CONTRATACION,
                'todas_fuentes' => true,
                'todos_sectores' => true,
                'modalidad_ids' => [Modalidad::where('clave', self::MODALIDAD)->value('id')],
            ], null);
            $plantilla->update(['clave' => self::CLAVE]);

            $borrador = $plantilla->versiones()->sole();
            $etapas = EtapaContratacion::whereIn('clave', $this->clavesDeEtapas())->pluck('id', 'clave');
            foreach (self::REQUISITOS as $r) {
                $condicion = $r[2] ?? null;
                $this->catalogo->guardarRequisito($borrador, [
                    'texto' => $r[1],
                    'etapa_id' => (int) $etapas[$r[0]],
                    'caracter' => $condicion ? RequisitoCatalogo::CONDICIONADO : RequisitoCatalogo::OBLIGATORIO,
                    'condicion_descripcion' => $condicion,
                    'referencia_normativa' => self::REFERENCIA,
                    'nota' => $r[3] ?? null,
                ]);
            }

            return $plantilla;
        });
    }

    /** @return list<string> */
    private function clavesDeEtapas(): array
    {
        return array_values(array_unique(array_column(self::REQUISITOS, 0)));
    }
}
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (1 de 2):

```php
        ];
    }

    /**
     * Al crear, el uso llega en los datos; al editar es el de la plantilla y, si llega otro, es
     * un error. Las modalidades que no llegan al editar se conservan.
```

por:

```php
        ];
    }

    /**
     * El nombre, tal como está guardado, de otra plantilla que se llama igual sin contar mayúsculas,
     * tildes ni espacios en los extremos; null si no hay. `$excepto` es la plantilla que se edita.
     */
    public function nombreEnUso(string $nombre, ?PlantillaChecklist $excepto = null): ?string
    {
        // Se compara en PHP para que MariaDB y SQLite ignoren igual mayúsculas y tildes.
        $normalizar = fn (string $texto) => Str::lower(Str::ascii(trim($texto)));

        return PlantillaChecklist::query()
            ->when($excepto, fn ($q) => $q->whereKeyNot($excepto->id))
            ->pluck('nombre')
            ->first(fn ($guardado) => $normalizar($guardado) === $normalizar($nombre));
    }

    /**
     * Al crear, el uso llega en los datos; al editar es el de la plantilla y, si llega otro, es
     * un error. Las modalidades que no llegan al editar se conservan.
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (2 de 2):

```php
            $errores[] = 'El uso de una plantilla no cambia después de crearla: esta es de ' . self::NOMBRES_USO[$plantilla->uso] . '. Si necesitas otro uso, crea una plantilla nueva.';
        }

        $nombre = trim((string) ($datos['nombre'] ?? ''));
        // Se compara en PHP para que MariaDB y SQLite ignoren igual mayúsculas y tildes.
        $normalizar = fn (string $texto) => Str::lower(Str::ascii(trim($texto)));
        $existente = PlantillaChecklist::query()
            ->when($plantilla, fn ($q) => $q->whereKeyNot($plantilla->id))
            ->pluck('nombre')
            ->first(fn ($guardado) => $normalizar($guardado) === $normalizar($nombre));
        if ($existente !== null) {
            $errores[] = "Ya existe una plantilla llamada \"{$existente}\".";
        }
```

por:

```php
            $errores[] = 'El uso de una plantilla no cambia después de crearla: esta es de ' . self::NOMBRES_USO[$plantilla->uso] . '. Si necesitas otro uso, crea una plantilla nueva.';
        }

        $existente = $this->nombreEnUso((string) ($datos['nombre'] ?? ''), $plantilla);
        if ($existente !== null) {
            $errores[] = "Ya existe una plantilla llamada \"{$existente}\".";
        }
```

En `routes/console.php`, reemplazar (1 de 2):

```php
<?php

use App\Models\Proyecto;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Illuminate\Foundation\Inspiring;
```

por:

```php
<?php

use App\Exceptions\ReglaNegocioException;
use App\Models\Proyecto;
use App\Services\Contratacion\PlantillaLicitacionPublica;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Illuminate\Foundation\Inspiring;
```

En `routes/console.php`, reemplazar (2 de 2):

```php
    $this->info("Expedientes creados: {$creados} de {$proyectos->count()}.");
})->purpose('Crea el expediente, sin configurar, a los proyectos en Formulación con al menos una fuente definida que no tienen');

Artisan::command('proyectos:informe-fuentes', function () {
    $porFuente = DB::table('fuentes_financiacion as f')
        ->leftJoin('proyecto_fuentes as pf', 'pf.fuente_id', 'f.id')
```

por:

```php
    $this->info("Expedientes creados: {$creados} de {$proyectos->count()}.");
})->purpose('Crea el expediente, sin configurar, a los proyectos en Formulación con al menos una fuente definida que no tienen');

Artisan::command('plantillas:sembrar-contratacion {--simular : Solo lista los requisitos que crearía, sin escribir nada}', function (PlantillaLicitacionPublica $semilla) {
    if ($existente = $semilla->existente()) {
        $this->info("La plantilla \"{$existente->nombre}\" ya existe: no se creó nada.");

        return 0;
    }

    if ($impedimentos = $semilla->impedimentos()) {
        foreach ($impedimentos as $impedimento) {
            $this->error($impedimento);
        }

        return 1;
    }

    if ($this->option('simular')) {
        foreach ($semilla->vistaPrevia() as $requisito) {
            $this->line("{$requisito['etapa']}: {$requisito['texto']}" . ($requisito['condicion'] ? " (condicionado: {$requisito['condicion']})" : ''));
        }
        $this->info('Se crearía la plantilla "' . PlantillaLicitacionPublica::NOMBRE . "\" en borrador con {$semilla->cantidad()} requisitos.");

        return 0;
    }

    try {
        $plantilla = $semilla->sembrar();
    } catch (ReglaNegocioException $e) {
        foreach ($e->errores() ?: [$e->getMessage()] as $error) {
            $this->error($error);
        }

        return 1;
    }

    $this->info("Plantilla \"{$plantilla->nombre}\" creada en borrador con {$semilla->cantidad()} requisitos. La entidad debe validarla antes de que un administrador la publique en Plantillas de checklist.");

    return 0;
})->purpose('Crea en borrador la plantilla de contratación "Licitación pública – SECOP II" de la guía CCE-SEC-G1-18, si no existe');

Artisan::command('proyectos:informe-fuentes', function () {
    $porFuente = DB::table('fuentes_financiacion as f')
        ->leftJoin('proyecto_fuentes as pf', 'pf.fuente_id', 'f.id')
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/SembrarPlantillaContratacionTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 848 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Services/Contratacion/PlantillaLicitacionPublica.php tests/Feature/Contratacion/SembrarPlantillaContratacionTest.php app/Services/Viabilizacion/PlantillaService.php routes/console.php
git commit -m "feat: command to seed the public tender template draft"
```

---

### Tarea 5: Uso y modalidades en las pantallas de plantillas

Pestañas por uso en la lista, y uso y modalidades en el detalle.

**Files:**
- Modify: `resources/css/Plantillas.css`
- Modify: `resources/js/components/PlantillaDetalle.jsx`
- Modify: `resources/js/components/PlantillasChecklist.jsx`
- Modify: `resources/js/utils/plantillasChecklist.js`
- Test: `resources/js/utils/plantillasChecklist.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/plantillasChecklist.test.js`, reemplazar (1 de 3):

```js
    resumenVersiones,
    textoAsociaciones,
    versionDeTrabajo,
} from './plantillasChecklist';

describe('formulario de la plantilla', () => {
    const plantilla = {
```

por:

```js
    resumenVersiones,
    textoAsociaciones,
    versionDeTrabajo,
    USOS,
    USO_CONTRATACION,
    USO_VIABILIZACION,
    catalogoConPropios,
    datosNuevaPlantilla,
    esContratacion,
    etiquetaUso,
    rutaListaPlantillas,
    textoModalidades,
    usoDeConsulta,
    usoDePlantilla,
} from './plantillasChecklist';
import { RUTA_PLANTILLAS } from './navegacion';

describe('formulario de la plantilla', () => {
    const plantilla = {
```

En `resources/js/utils/plantillasChecklist.test.js`, reemplazar (2 de 3):

```js
        const formulario = formularioPlantilla(plantilla);
        expect(formulario).toEqual({
            nombre: 'Educación', descripcion: '', alcance: 'especifica', todas_fuentes: false, todos_sectores: false,
            fuente_ids: [4], sector_ids: [2],
        });
        expect(datosPlantilla({ ...formulario, nombre: '  Educación  ', descripcion: ' ' })).toEqual({
            nombre: 'Educación', descripcion: null, alcance: 'especifica', todas_fuentes: false, todos_sectores: false,
            fuente_ids: [4], sector_ids: [2],
```

por:

```js
        const formulario = formularioPlantilla(plantilla);
        expect(formulario).toEqual({
            nombre: 'Educación', descripcion: '', alcance: 'especifica', todas_fuentes: false, todos_sectores: false,
            fuente_ids: [4], sector_ids: [2], todas_modalidades: false, modalidad_ids: [],
        });
        // En viabilización las modalidades no viajan: el servidor las rechaza.
        expect(datosPlantilla({ ...formulario, nombre: '  Educación  ', descripcion: ' ' })).toEqual({
            nombre: 'Educación', descripcion: null, alcance: 'especifica', todas_fuentes: false, todos_sectores: false,
            fuente_ids: [4], sector_ids: [2],
```

En `resources/js/utils/plantillasChecklist.test.js`, reemplazar (3 de 3):

```js
            .toEqual(['Condicionado']);
    });
});
```

por:

```js
            .toEqual(['Condicionado']);
    });
});

describe('uso de la plantilla', () => {
    it('los usos coinciden con PlantillaChecklist::USOS', () => {
        const modelo = readFileSync(new URL('../../../app/Models/PlantillaChecklist.php', import.meta.url), 'utf8');
        const valor = (n) => modelo.match(new RegExp(`const ${n} = '([a-z_]+)'`))[1];
        expect(USOS.map(([v]) => v)).toEqual([valor('USO_VIABILIZACION'), valor('USO_CONTRATACION')]);
        expect(modelo).toMatch(/const USOS = \[self::USO_VIABILIZACION, self::USO_CONTRATACION\]/);
        expect(USOS.map(([, t]) => t)).toEqual(['Viabilización', 'Contratación']);
        expect(etiquetaUso(USO_CONTRATACION)).toBe('Contratación');
    });

    it('una plantilla sin uso es de viabilización', () => {
        expect(esContratacion({ uso: 'contratacion' })).toBe(true);
        expect(esContratacion({ uso: 'viabilizacion' })).toBe(false);
        expect(esContratacion({})).toBe(false);
        expect(esContratacion(null)).toBe(false);
        expect(usoDePlantilla({ uso: 'contratacion' })).toBe(USO_CONTRATACION);
        expect(usoDePlantilla(undefined)).toBe(USO_VIABILIZACION);
    });

    it('lee el uso de la URL y vuelve a la lista en esa pestaña', () => {
        expect(usoDeConsulta('contratacion')).toBe(USO_CONTRATACION);
        expect(usoDeConsulta('viabilizacion')).toBe(USO_VIABILIZACION);
        expect(usoDeConsulta(null)).toBe(USO_VIABILIZACION);
        expect(usoDeConsulta('otro')).toBe(USO_VIABILIZACION);
        expect(rutaListaPlantillas(USO_CONTRATACION)).toBe(`${RUTA_PLANTILLAS}?uso=contratacion`);
        expect(rutaListaPlantillas(USO_VIABILIZACION)).toBe(RUTA_PLANTILLAS);
        expect(usoDeConsulta(new URL(`http://x${rutaListaPlantillas(USO_CONTRATACION)}`).searchParams.get('uso'))).toBe(USO_CONTRATACION);
    });

    it('crea con el uso de la pestaña; la de contratación, sin modalidades', () => {
        expect(datosNuevaPlantilla('  Mínima cuantía ', USO_CONTRATACION)).toEqual({
            nombre: 'Mínima cuantía', uso: 'contratacion', todas_fuentes: true, todos_sectores: true,
            todas_modalidades: false, modalidad_ids: [],
        });
        expect(datosNuevaPlantilla('PGN', USO_VIABILIZACION)).toEqual({
            nombre: 'PGN', uso: 'viabilizacion', todas_fuentes: true, todos_sectores: true,
        });
    });
});

describe('modalidades de la plantilla', () => {
    const contratacion = {
        uso: 'contratacion', nombre: 'Licitación', descripcion: null, alcance: null, todas_fuentes: true, todos_sectores: true,
        fuentes: [], sectores: [], todas_modalidades: false,
        modalidades: [{ id: 3, nombre: 'Licitación pública', activo: true }, { id: 7, nombre: 'Concurso de méritos', activo: false }],
    };

    it('lee las modalidades y las envía solo en contratación', () => {
        const formulario = formularioPlantilla(contratacion);
        expect(formulario).toMatchObject({ todas_modalidades: false, modalidad_ids: [3, 7] });
        expect(datosPlantilla(formulario, USO_CONTRATACION)).toMatchObject({ todas_modalidades: false, modalidad_ids: [3, 7] });
        expect(datosPlantilla(formulario, USO_VIABILIZACION)).not.toHaveProperty('modalidad_ids');
        expect(datosPlantilla(formulario)).not.toHaveProperty('todas_modalidades');
    });

    it('con "Todas las modalidades" envía la lista vacía', () => {
        const formulario = { ...formularioPlantilla(contratacion), todas_modalidades: true };
        expect(datosPlantilla(formulario, USO_CONTRATACION)).toMatchObject({ todas_modalidades: true, modalidad_ids: [] });
    });

    it('al guardar sigue el uso de la plantilla y no lo envía: no cambia', () => {
        const formulario = formularioPlantilla(contratacion);
        const datos = datosGuardarPlantilla(formulario, contratacion, []);
        expect(datos).toMatchObject({ modalidad_ids: [3, 7] });
        expect(datos).not.toHaveProperty('uso');
        expect(datosGuardarPlantilla(formulario, { uso: 'viabilizacion', es_sgr: false }, [])).not.toHaveProperty('modalidad_ids');
        expect(datosGuardarPlantilla(formulario, { uso: 'viabilizacion', es_sgr: true }, [])).not.toHaveProperty('modalidad_ids');
        expect(PLANTILLA_VACIA).toMatchObject({ todas_modalidades: false, modalidad_ids: [] });
    });

    it('resume las modalidades en la lista y marca las inactivas', () => {
        expect(textoModalidades(contratacion)).toBe('Licitación pública, Concurso de méritos (inactiva)');
        expect(textoModalidades({ ...contratacion, todas_modalidades: true })).toBe('Todas las modalidades');
        expect(textoModalidades({ ...contratacion, modalidades: [] })).toBe('Sin modalidades');
        expect(textoModalidades({})).toBe('Sin modalidades');
    });

    it('conserva en el catálogo lo que la plantilla tiene y el catálogo no trae', () => {
        const catalogo = [{ id: 3, nombre: 'Licitación pública', activo: true }, { id: 4, nombre: 'Selección abreviada', activo: true }];
        expect(catalogoConPropios(catalogo, contratacion.modalidades).map((m) => m.id)).toEqual([3, 4, 7]);
        expect(catalogoConPropios(null, contratacion.modalidades).map((m) => m.id)).toEqual([3, 7]);
        expect(catalogoConPropios(catalogo, undefined)).toEqual(catalogo);
        // La inactiva que la plantilla ya tiene sigue entre las opciones; otra inactiva, no.
        const opciones = opcionesCatalogo([...catalogo, { id: 9, nombre: 'Otra', activo: false }, contratacion.modalidades[1]], [3, 7]);
        expect(opciones.map((m) => m.id)).toEqual([3, 4, 7]);
    });
});
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/plantillasChecklist.test.js`
Expected: FALLAN 10 y pasan 50: las utilidades de plantillas todavía no manejan el uso ni las modalidades.

- [ ] **Paso 3: Implementar**

En `resources/css/Plantillas.css`, reemplazar (1 de 1):

```css
.pl-pestana:hover { color: var(--gp-texto); }
.pl-pestana.activa { color: var(--gp-azul-texto); border-bottom-color: var(--gp-azul-texto); }

.pl-formulario { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 16rem), 1fr)); gap: .9rem 1.1rem; padding: 1.1rem; }
.pl-campo { display: flex; flex-direction: column; gap: .3rem; min-width: 0; margin: 0; }
.pl-campo > span { font-size: .78rem; font-weight: 600; color: var(--gp-texto-2); }
```

por:

```css
.pl-pestana:hover { color: var(--gp-texto); }
.pl-pestana.activa { color: var(--gp-azul-texto); border-bottom-color: var(--gp-azul-texto); }

/* Selector de uso de la lista: botones con aria-pressed, de los que uno está pulsado. */
.pl-segmento { display: inline-flex; flex-wrap: wrap; gap: .2rem; margin-bottom: 1rem; padding: .2rem; background: var(--gp-superficie-2); border: 1px solid var(--gp-linea); border-radius: var(--gp-radio); }
.pl-segmento-opcion { padding: .4rem .9rem; font: inherit; font-size: .88rem; font-weight: 600; color: var(--gp-texto-2); background: none; border: 0; border-radius: var(--gp-radio); cursor: pointer; }
.pl-segmento-opcion:hover { color: var(--gp-texto); }
.pl-segmento-opcion:focus-visible { outline: none; box-shadow: var(--gp-enfoque); }
.pl-segmento-opcion.activa { color: var(--gp-sobre-acento); background: var(--gp-azul); }

/* Dato de solo lectura dentro de un formulario (el uso de la plantilla). */
.pl-solo-lectura { margin: 0; font-size: .9rem; color: var(--gp-texto); }

.pl-formulario { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 16rem), 1fr)); gap: .9rem 1.1rem; padding: 1.1rem; }
.pl-campo { display: flex; flex-direction: column; gap: .3rem; min-width: 0; margin: 0; }
.pl-campo > span { font-size: .78rem; font-weight: 600; color: var(--gp-texto-2); }
```

En `resources/js/components/PlantillaDetalle.jsx`, reemplazar (1 de 8):

```jsx
import axios from '../axios';
import { alternarId } from '../utils/expedienteChecklist';
import { avisarError } from '../utils/avisosPlantillas';
import { ALCANCES, datosGuardarPlantilla, formularioPlantilla, mensajeApi, opcionesCatalogo, pestanaPorTecla, resumenVersiones, versionDeTrabajo } from '../utils/plantillasChecklist';
import PlantillaRequisitos from './PlantillaRequisitos';
import PlantillaVistaPrevia from './PlantillaVistaPrevia';
import PlantillaVersiones from './PlantillaVersiones';
```

por:

```jsx
import axios from '../axios';
import { alternarId } from '../utils/expedienteChecklist';
import { avisarError } from '../utils/avisosPlantillas';
import {
    ALCANCES, USO_CONTRATACION, catalogoConPropios, datosGuardarPlantilla, etiquetaUso, formularioPlantilla, mensajeApi, opcionesCatalogo,
    pestanaPorTecla, resumenVersiones, rutaListaPlantillas, usoDePlantilla, versionDeTrabajo,
} from '../utils/plantillasChecklist';
import PlantillaRequisitos from './PlantillaRequisitos';
import PlantillaVistaPrevia from './PlantillaVistaPrevia';
import PlantillaVersiones from './PlantillaVersiones';
```

En `resources/js/components/PlantillaDetalle.jsx`, reemplazar (2 de 8):

```jsx
const ID_PANEL = 'pl-panel-detalle';

const NOTA_SGR = 'La plantilla SGR aplica a todos los sectores y siempre incluye la fuente SGR.';

const ListaChequeo = ({ titulo, todas, textoTodas, onTodas, bloquearTodas, items, elegidos, onAlternar, sufijoInactivo, fijos = [], nota }) => (
    <fieldset className="pl-asociacion">
```

por:

```jsx
const ID_PANEL = 'pl-panel-detalle';

const NOTA_SGR = 'La plantilla SGR aplica a todos los sectores y siempre incluye la fuente SGR.';
const NOTA_MODALIDADES = 'Elige al menos una modalidad, o marca "Todas las modalidades", antes de publicar.';

const ListaChequeo = ({ titulo, todas, textoTodas, onTodas, bloquearTodas, items, elegidos, onAlternar, sufijoInactivo, fijos = [], nota }) => (
    <fieldset className="pl-asociacion">
```

En `resources/js/components/PlantillaDetalle.jsx`, reemplazar (3 de 8):

```jsx
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
    const [guardando, guardarUnaVez] = useOcupado();
```

por:

```jsx
    </fieldset>
);

/**
 * Detalle de una plantilla: datos, asociaciones, requisitos, vista previa y versiones. El uso se
 * muestra pero no se edita; una plantilla de contratación se asocia además a modalidades y usa
 * siempre el motor simple.
 */
const PlantillaDetalle = () => {
    const { plantillaId } = useParams();
    const [plantilla, setPlantilla] = useState(null);
    const [formulario, setFormulario] = useState(null);
    const [fuentes, setFuentes] = useState([]);
    const [sectores, setSectores] = useState([]);
    const [modalidades, setModalidades] = useState([]);
    const [requisitos, setRequisitos] = useState([]);
    const [pestana, setPestana] = useState('general');
    const [guardando, guardarUnaVez] = useOcupado();
```

En `resources/js/components/PlantillaDetalle.jsx`, reemplazar (4 de 8):

```jsx
    const cargada = useRef(false);

    const version = versionDeTrabajo(plantilla);

    // `reiniciar` vuelve a llenar el formulario de General; las recargas por requisitos o versiones lo respetan.
    // Solo vale la última petición, y solo el fallo de la carga inicial reemplaza la página.
```

por:

```jsx
    const cargada = useRef(false);

    const version = versionDeTrabajo(plantilla);
    const uso = usoDePlantilla(plantilla);
    const contratacion = uso === USO_CONTRATACION;

    // `reiniciar` vuelve a llenar el formulario de General; las recargas por requisitos o versiones lo respetan.
    // Solo vale la última petición, y solo el fallo de la carga inicial reemplaza la página.
```

En `resources/js/components/PlantillaDetalle.jsx`, reemplazar (5 de 8):

```jsx
        return () => { vigente = false; };
    }, []);

    const cambiar = (campo, valor) => setFormulario((f) => ({ ...f, [campo]: valor }));

    const guardar = (e) => {
```

por:

```jsx
        return () => { vigente = false; };
    }, []);

    // Las modalidades solo hacen falta en una plantilla de contratación. Trae también las inactivas:
    // opcionesCatalogo ofrece las activas y las que la plantilla ya tiene.
    useEffect(() => {
        if (!contratacion) return undefined;
        let vigente = true;
        axios.get('/modalidades')
            .then(({ data }) => { if (vigente) setModalidades(Array.isArray(data) ? data : []); })
            .catch((e) => { if (vigente) avisarError('No se pudieron cargar las modalidades', e); });
        return () => { vigente = false; };
    }, [contratacion]);

    const cambiar = (campo, valor) => setFormulario((f) => ({ ...f, [campo]: valor }));

    const guardar = (e) => {
```

En `resources/js/components/PlantillaDetalle.jsx`, reemplazar (6 de 8):

```jsx

    return (
        <div className="pl-pagina">
            <Link to={RUTA_PLANTILLAS} className="pl-volver"><FontAwesomeIcon icon={faArrowLeft} /> Plantillas</Link>
            <header className="gp-cabecera">
                <span className="gp-etiqueta">
                    {plantilla.motor === 'sgr' ? 'Evaluador SGR' : 'Plantilla simple'} · {resumenVersiones(plantilla)}
                </span>
                <h1>{plantilla.nombre}</h1>
                {!plantilla.activo && <p>Inactiva: no se ofrece en expedientes nuevos.</p>}
```

por:

```jsx

    return (
        <div className="pl-pagina">
            <Link to={rutaListaPlantillas(uso)} className="pl-volver">
                <FontAwesomeIcon icon={faArrowLeft} /> Plantillas de {etiquetaUso(uso).toLowerCase()}
            </Link>
            <header className="gp-cabecera">
                <span className="gp-etiqueta">
                    {contratacion ? 'Plantilla de contratación' : (plantilla.motor === 'sgr' ? 'Evaluador SGR' : 'Plantilla simple')} · {resumenVersiones(plantilla)}
                </span>
                <h1>{plantilla.nombre}</h1>
                {!plantilla.activo && <p>Inactiva: no se ofrece en expedientes nuevos.</p>}
```

En `resources/js/components/PlantillaDetalle.jsx`, reemplazar (7 de 8):

```jsx
            <div role="tabpanel" id={ID_PANEL} aria-labelledby={idPestana(pestana)}>
                {pestana === 'general' && (
                    <form className="gp-panel pl-formulario" onSubmit={guardar}>
                        <label className="pl-campo">
                            <span>Nombre</span>
                            <input value={formulario.nombre} maxLength={199} required onChange={(e) => cambiar('nombre', e.target.value)} />
```

por:

```jsx
            <div role="tabpanel" id={ID_PANEL} aria-labelledby={idPestana(pestana)}>
                {pestana === 'general' && (
                    <form className="gp-panel pl-formulario" onSubmit={guardar}>
                        <div className="pl-campo">
                            <span>Uso</span>
                            <p className="pl-solo-lectura">{etiquetaUso(uso)}</p>
                            <small className="pl-ayuda">Se elige al crear la plantilla y no cambia.</small>
                        </div>
                        <label className="pl-campo">
                            <span>Nombre</span>
                            <input value={formulario.nombre} maxLength={199} required onChange={(e) => cambiar('nombre', e.target.value)} />
```

En `resources/js/components/PlantillaDetalle.jsx`, reemplazar (8 de 8):

```jsx
                            elegidos={formulario.sector_ids}
                            onAlternar={(id) => cambiar('sector_ids', alternarId(formulario.sector_ids, id))}
                        />
                        <div className="pl-formulario-acciones">
                            <button type="submit" className="gp-boton gp-boton-primario" disabled={guardando}>
                                <FontAwesomeIcon icon={faFloppyDisk} /> Guardar
```

por:

```jsx
                            elegidos={formulario.sector_ids}
                            onAlternar={(id) => cambiar('sector_ids', alternarId(formulario.sector_ids, id))}
                        />
                        {contratacion && (
                            <ListaChequeo
                                titulo="Modalidades de contratación"
                                sufijoInactivo="inactiva"
                                nota={NOTA_MODALIDADES}
                                todas={formulario.todas_modalidades}
                                textoTodas="Todas las modalidades"
                                onTodas={(v) => cambiar('todas_modalidades', v)}
                                items={catalogoConPropios(modalidades, plantilla.modalidades)}
                                elegidos={formulario.modalidad_ids}
                                onAlternar={(id) => cambiar('modalidad_ids', alternarId(formulario.modalidad_ids, id))}
                            />
                        )}
                        <div className="pl-formulario-acciones">
                            <button type="submit" className="gp-boton gp-boton-primario" disabled={guardando}>
                                <FontAwesomeIcon icon={faFloppyDisk} /> Guardar
```

En `resources/js/components/PlantillasChecklist.jsx`, reemplazar (1 de 8):

```jsx
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Swal from 'sweetalert2';
import { faCopy, faPlus, faPowerOff } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
import { filtrarPlantillas, mensajeApi, resumenVersiones, textoAsociaciones } from '../utils/plantillasChecklist';
import { RUTA_PLANTILLAS } from '../utils/navegacion';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';

/** Lista de plantillas de checklist (solo Administrador). */
const PlantillasChecklist = () => {
    const navigate = useNavigate();
    const [plantillas, setPlantillas] = useState(null);
    const [buscar, setBuscar] = useState('');
    const [soloActivas, setSoloActivas] = useState(false);
    const [error, setError] = useState(null);
    const [ocupado, ejecutar] = useOcupado();
    const peticion = useRef(0);
    const primera = useRef(true);

    // Solo vale la respuesta de la última petición: las anteriores llegan desordenadas y se descartan.
    const cargar = useCallback(async (texto) => {
        const numero = ++peticion.current;
        try {
            const { data } = await axios.get('/plantillas', { params: texto ? { buscar: texto } : {} });
            if (numero !== peticion.current) return;
            setPlantillas(data);
            setError(null);
```

por:

```jsx
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import Swal from 'sweetalert2';
import { faCopy, faPlus, faPowerOff } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
import {
    USOS, USO_CONTRATACION, USO_VIABILIZACION, datosNuevaPlantilla, esContratacion, etiquetaUso, filtrarPlantillas, mensajeApi,
    resumenVersiones, textoAsociaciones, textoModalidades, usoDeConsulta,
} from '../utils/plantillasChecklist';
import { RUTA_PLANTILLAS } from '../utils/navegacion';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';

const DESCRIPCION_USO = {
    [USO_VIABILIZACION]: 'Conjuntos de requisitos que se aplican a los expedientes según su fuente de financiación y su sector.',
    [USO_CONTRATACION]: 'Conjuntos de requisitos de un proceso de contratación, por etapa, según su modalidad, su fuente de financiación y su sector.',
};

// Al crear se dice el uso: no cambia después.
const AYUDA_CREAR = {
    [USO_VIABILIZACION]: 'Plantilla de viabilización (el uso no cambia después). Nace para todas las fuentes y sectores, con su primera versión en borrador. Ajústalo en el detalle.',
    [USO_CONTRATACION]: 'Plantilla de contratación (el uso no cambia después). Nace para todas las fuentes y sectores, sin modalidades y con su primera versión en borrador. Elige sus modalidades en el detalle antes de publicar.',
};

/**
 * Lista de plantillas de checklist (solo Administrador), por uso: viabilización o contratación.
 * El uso elegido vive en la URL (`?uso=contratacion`) para que un enlace o el volver lo conserven.
 */
const PlantillasChecklist = () => {
    const navigate = useNavigate();
    const [consulta, setConsulta] = useSearchParams();
    const uso = usoDeConsulta(consulta.get('uso'));
    const [plantillas, setPlantillas] = useState(null);
    const [buscar, setBuscar] = useState('');
    const [soloActivas, setSoloActivas] = useState(false);
    const [error, setError] = useState(null);
    const [ocupado, ejecutar] = useOcupado();
    const peticion = useRef(0);
    // Lo que la lista muestra ahora (uso y búsqueda). Una recarga tras una acción pide esto y no
    // lo que había al empezarla: si entretanto se cambió de pestaña, no la pisa con la anterior.
    const vigente = useRef(null);

    // Solo vale la respuesta de la última petición: las anteriores llegan desordenadas y se descartan.
    const cargar = useCallback(async (texto, usoLista) => {
        const numero = ++peticion.current;
        try {
            const { data } = await axios.get('/plantillas', { params: { uso: usoLista, ...(texto ? { buscar: texto } : {}) } });
            if (numero !== peticion.current) return;
            setPlantillas(data);
            setError(null);
```

En `resources/js/components/PlantillasChecklist.jsx`, reemplazar (2 de 8):

```jsx
        }
    }, []);

    // La primera carga es inmediata; la búsqueda espera a que se deje de escribir.
    useEffect(() => {
        const espera = primera.current ? 0 : 300;
        primera.current = false;
        const temporizador = setTimeout(() => cargar(buscar.trim()), espera);
        return () => clearTimeout(temporizador);
    }, [buscar, cargar]);

    const visibles = useMemo(() => filtrarPlantillas(plantillas ?? [], { soloActivas }), [plantillas, soloActivas]);
```

por:

```jsx
        }
    }, []);

    // La primera carga y el cambio de uso son inmediatos; la búsqueda espera a que se deje de
    // escribir. Al cambiar de uso no se muestran, mientras tanto, las plantillas del otro.
    useEffect(() => {
        const cambioUso = vigente.current?.uso !== uso;
        if (cambioUso && vigente.current !== null) {
            setPlantillas(null);
            setError(null);
        }
        vigente.current = { uso, buscar: buscar.trim() };
        const temporizador = setTimeout(() => cargar(buscar.trim(), uso), cambioUso ? 0 : 300);
        return () => clearTimeout(temporizador);
    }, [buscar, uso, cargar]);

    const recargar = () => cargar(vigente.current.buscar, vigente.current.uso);

    // Reemplaza la entrada del historial: cambiar de pestaña no es una página nueva.
    const elegirUso = (nuevo) => setConsulta((actual) => {
        const siguiente = new URLSearchParams(actual);
        if (nuevo === USO_VIABILIZACION) siguiente.delete('uso');
        else siguiente.set('uso', nuevo);
        return siguiente;
    }, { replace: true });

    const visibles = useMemo(() => filtrarPlantillas(plantillas ?? [], { soloActivas }), [plantillas, soloActivas]);
```

En `resources/js/components/PlantillasChecklist.jsx`, reemplazar (3 de 8):

```jsx
    });

    const crear = () => ejecutar(async () => {
        const { isConfirmed, value } = await pedirNombre(
            'Nueva plantilla',
            'Nace para todas las fuentes y sectores, con su primera versión en borrador. Ajústalo en el detalle.'
        );
        if (!isConfirmed) return;
        try {
            const { data } = await axios.post('/plantillas', { nombre: value.trim(), todas_fuentes: true, todos_sectores: true });
            navigate(`${RUTA_PLANTILLAS}/${data.id}`);
        } catch (e) {
            avisarError('No se pudo crear la plantilla', e);
```

por:

```jsx
    });

    const crear = () => ejecutar(async () => {
        const { isConfirmed, value } = await pedirNombre(`Nueva plantilla de ${etiquetaUso(uso).toLowerCase()}`, AYUDA_CREAR[uso]);
        if (!isConfirmed) return;
        try {
            const { data } = await axios.post('/plantillas', datosNuevaPlantilla(value, uso));
            navigate(`${RUTA_PLANTILLAS}/${data.id}`);
        } catch (e) {
            avisarError('No se pudo crear la plantilla', e);
```

En `resources/js/components/PlantillasChecklist.jsx`, reemplazar (4 de 8):

```jsx
        }
        try {
            await axios.post(`/plantillas/${plantilla.id}/activar`, { activo: !plantilla.activo });
            await cargar(buscar.trim());
        } catch (e) {
            avisarError('No se pudo cambiar el estado', e);
        }
```

por:

```jsx
        }
        try {
            await axios.post(`/plantillas/${plantilla.id}/activar`, { activo: !plantilla.activo });
            await recargar();
        } catch (e) {
            avisarError('No se pudo cambiar el estado', e);
        }
```

En `resources/js/components/PlantillasChecklist.jsx`, reemplazar (5 de 8):

```jsx
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Catálogos</span>
                <h1>Plantillas de checklist</h1>
                <p>Conjuntos de requisitos que se aplican a los expedientes según su fuente de financiación y su sector.</p>
            </header>

            <div className="pl-barra">
                <input
                    type="search"
```

por:

```jsx
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Catálogos</span>
                <h1>Plantillas de checklist</h1>
                <p>{DESCRIPCION_USO[uso]}</p>
            </header>

            <div className="pl-segmento" role="group" aria-label="Uso de las plantillas">
                {USOS.map(([valor, texto]) => (
                    <button key={valor} type="button" aria-pressed={uso === valor}
                        className={`pl-segmento-opcion${uso === valor ? ' activa' : ''}`} onClick={() => elegirUso(valor)}>
                        {texto}
                    </button>
                ))}
            </div>

            <div className="pl-barra">
                <input
                    type="search"
```

En `resources/js/components/PlantillasChecklist.jsx`, reemplazar (6 de 8):

```jsx

            {error && <p className="pl-error" role="alert">{error}</p>}
            {plantillas === null && !error && <p className="gp-vacio">Cargando plantillas…</p>}
            {plantillas !== null && visibles.length === 0 && <p className="gp-vacio">No hay plantillas que coincidan.</p>}

            <ul className="pl-lista">
                {visibles.map((p) => (
```

por:

```jsx

            {error && <p className="pl-error" role="alert">{error}</p>}
            {plantillas === null && !error && <p className="gp-vacio">Cargando plantillas…</p>}
            {plantillas !== null && visibles.length === 0 && (
                <p className="gp-vacio">No hay plantillas de {etiquetaUso(uso).toLowerCase()} que coincidan.</p>
            )}

            <ul className="pl-lista">
                {visibles.map((p) => (
```

En `resources/js/components/PlantillasChecklist.jsx`, reemplazar (7 de 8):

```jsx
                            <div className="pl-tarjeta-titulo">
                                <Link to={`${RUTA_PLANTILLAS}/${p.id}`}>{p.nombre}</Link>
                                {p.es_sgr && <span className="pl-insignia">SGR</span>}
                                <span className="pl-insignia pl-insignia-suave">{p.motor === 'sgr' ? 'Evaluador SGR' : 'Simple'}</span>
                                {!p.activo && <span className="pl-insignia pl-insignia-alerta">Inactiva</span>}
                            </div>
                            {p.descripcion && <p className="pl-tarjeta-descripcion">{p.descripcion}</p>}
```

por:

```jsx
                            <div className="pl-tarjeta-titulo">
                                <Link to={`${RUTA_PLANTILLAS}/${p.id}`}>{p.nombre}</Link>
                                {p.es_sgr && <span className="pl-insignia">SGR</span>}
                                {!esContratacion(p) && <span className="pl-insignia pl-insignia-suave">{p.motor === 'sgr' ? 'Evaluador SGR' : 'Simple'}</span>}
                                {!p.activo && <span className="pl-insignia pl-insignia-alerta">Inactiva</span>}
                            </div>
                            {p.descripcion && <p className="pl-tarjeta-descripcion">{p.descripcion}</p>}
```

En `resources/js/components/PlantillasChecklist.jsx`, reemplazar (8 de 8):

```jsx
                                <div><dt>Requisitos</dt><dd>{p.vigente?.requisitos ?? p.borrador?.requisitos ?? 0}</dd></div>
                                <div><dt>Fuentes</dt><dd>{textoAsociaciones(p.todas_fuentes, p.fuentes, 'Todas')}</dd></div>
                                <div><dt>Sectores</dt><dd>{textoAsociaciones(p.todos_sectores, p.sectores, 'Todos')}</dd></div>
                            </dl>
                        </div>
                        <div className="pl-tarjeta-acciones">
```

por:

```jsx
                                <div><dt>Requisitos</dt><dd>{p.vigente?.requisitos ?? p.borrador?.requisitos ?? 0}</dd></div>
                                <div><dt>Fuentes</dt><dd>{textoAsociaciones(p.todas_fuentes, p.fuentes, 'Todas')}</dd></div>
                                <div><dt>Sectores</dt><dd>{textoAsociaciones(p.todos_sectores, p.sectores, 'Todos')}</dd></div>
                                {esContratacion(p) && <div><dt>Modalidades</dt><dd>{textoModalidades(p)}</dd></div>}
                            </dl>
                        </div>
                        <div className="pl-tarjeta-acciones">
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (1 de 7):

```js
// Funciones puras, probadas en plantillasChecklist.test.js. Las reglas las valida el backend.

import { CATEGORIAS, ORDEN_SECCIONES, SECCIONES, mensajeError, textoResponsable } from './expedienteChecklist';

// La lista vive en expedienteChecklist.js porque el checklist del expediente también agrupa por categoría.
export { CATEGORIAS };
```

por:

```js
// Funciones puras, probadas en plantillasChecklist.test.js. Las reglas las valida el backend.

import { CATEGORIAS, ORDEN_SECCIONES, SECCIONES, mensajeError, textoResponsable } from './expedienteChecklist';
import { RUTA_PLANTILLAS } from './navegacion';

// La lista vive en expedienteChecklist.js porque el checklist del expediente también agrupa por categoría.
export { CATEGORIAS };
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (2 de 7):

```js
    ['especifica', 'Específica'],
];

const etiqueta = (lista, valor) => lista.find(([v]) => v === valor)?.[1] ?? '';

export const etiquetaCategoria = (valor) => etiqueta(CATEGORIAS, valor);
```

por:

```js
    ['especifica', 'Específica'],
];

// Los valores son los de PlantillaChecklist::USOS (plantillasChecklist.test.js lo comprueba).
export const USO_VIABILIZACION = 'viabilizacion';
export const USO_CONTRATACION = 'contratacion';

/** Usos de una plantilla, en el orden del selector de la lista. El uso se elige al crear y no cambia. */
export const USOS = [
    [USO_VIABILIZACION, 'Viabilización'],
    [USO_CONTRATACION, 'Contratación'],
];

const etiqueta = (lista, valor) => lista.find(([v]) => v === valor)?.[1] ?? '';

export const etiquetaCategoria = (valor) => etiqueta(CATEGORIAS, valor);
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (3 de 7):

```js
export const etiquetaCaracter = (valor) => etiqueta(CARACTERES, valor);
export const etiquetaAlcance = (valor) => etiqueta(ALCANCES, valor);
export const etiquetaFuenteNormativa = (valor) => etiqueta(FUENTES_NORMATIVAS, valor);

/** "Todas las fuentes" o los nombres separados por coma. */
export const textoAsociaciones = (todas, items, textoTodas) =>
    todas ? textoTodas : (items.length ? items.map((i) => i.nombre).join(', ') : '—');

export const PLANTILLA_VACIA = {
    nombre: '',
    descripcion: '',
```

por:

```js
export const etiquetaCaracter = (valor) => etiqueta(CARACTERES, valor);
export const etiquetaAlcance = (valor) => etiqueta(ALCANCES, valor);
export const etiquetaFuenteNormativa = (valor) => etiqueta(FUENTES_NORMATIVAS, valor);
export const etiquetaUso = (valor) => etiqueta(USOS, valor);

/** Una plantilla sin uso es de viabilización: así quedaron todas las anteriores a la contratación. */
export const esContratacion = (plantilla) => plantilla?.uso === USO_CONTRATACION;
export const usoDePlantilla = (plantilla) => (esContratacion(plantilla) ? USO_CONTRATACION : USO_VIABILIZACION);

/** El uso que pide la URL de la lista (`?uso=`); sin él o con un valor desconocido, viabilización. */
export const usoDeConsulta = (valor) => (USOS.some(([v]) => v === valor) ? valor : USO_VIABILIZACION);

/** La ruta de la lista en la pestaña de un uso: la de viabilización es la de siempre, sin parámetro. */
export const rutaListaPlantillas = (uso) => (uso === USO_CONTRATACION ? `${RUTA_PLANTILLAS}?uso=${USO_CONTRATACION}` : RUTA_PLANTILLAS);

/**
 * Lo que se envía al crear una plantilla desde la lista: el uso de la pestaña activa, para todas
 * las fuentes y sectores. Una de contratación nace sin modalidades: se eligen en el detalle y
 * publicar las exige, para que no se ofrezca a todas sin que nadie lo haya decidido.
 */
export const datosNuevaPlantilla = (nombre, uso) => ({
    nombre: String(nombre ?? '').trim(),
    uso,
    todas_fuentes: true,
    todos_sectores: true,
    ...(uso === USO_CONTRATACION ? { todas_modalidades: false, modalidad_ids: [] } : {}),
});

/** "Todas las fuentes" o los nombres separados por coma. */
export const textoAsociaciones = (todas, items, textoTodas) =>
    todas ? textoTodas : (items.length ? items.map((i) => i.nombre).join(', ') : '—');

/** Las modalidades de una plantilla de contratación en la lista; las inactivas, marcadas. */
export const textoModalidades = (plantilla) => {
    if (plantilla?.todas_modalidades) return 'Todas las modalidades';
    const modalidades = plantilla?.modalidades ?? [];
    if (!modalidades.length) return 'Sin modalidades';
    return modalidades.map((m) => (m.activo === false ? `${m.nombre} (inactiva)` : m.nombre)).join(', ');
};

export const PLANTILLA_VACIA = {
    nombre: '',
    descripcion: '',
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (4 de 7):

```js
    todos_sectores: true,
    fuente_ids: [],
    sector_ids: [],
};

export const formularioPlantilla = (plantilla) => ({
    nombre: plantilla.nombre ?? '',
    descripcion: plantilla.descripcion ?? '',
```

por:

```js
    todos_sectores: true,
    fuente_ids: [],
    sector_ids: [],
    todas_modalidades: false,
    modalidad_ids: [],
};

/** El formulario de General. Las modalidades solo se usan en una plantilla de contratación. */
export const formularioPlantilla = (plantilla) => ({
    nombre: plantilla.nombre ?? '',
    descripcion: plantilla.descripcion ?? '',
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (5 de 7):

```js
    todos_sectores: Boolean(plantilla.todos_sectores),
    fuente_ids: (plantilla.fuentes ?? []).map((f) => f.id),
    sector_ids: (plantilla.sectores ?? []).map((s) => s.id),
});

/** Lo que espera la API: con "todas", la lista se envía vacía. */
export const datosPlantilla = (formulario) => ({
    nombre: formulario.nombre.trim(),
    descripcion: formulario.descripcion.trim() || null,
    alcance: formulario.alcance || null,
```

por:

```js
    todos_sectores: Boolean(plantilla.todos_sectores),
    fuente_ids: (plantilla.fuentes ?? []).map((f) => f.id),
    sector_ids: (plantilla.sectores ?? []).map((s) => s.id),
    todas_modalidades: Boolean(plantilla.todas_modalidades),
    modalidad_ids: (plantilla.modalidades ?? []).map((m) => m.id),
});

/**
 * Lo que espera la API: con "todas", la lista se envía vacía. Las modalidades solo viajan en una
 * plantilla de contratación (`uso`): la de viabilización las rechaza. El uso no se envía: no cambia.
 */
export const datosPlantilla = (formulario, uso = USO_VIABILIZACION) => ({
    nombre: formulario.nombre.trim(),
    descripcion: formulario.descripcion.trim() || null,
    alcance: formulario.alcance || null,
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (6 de 7):

```js
    todos_sectores: formulario.todos_sectores,
    fuente_ids: formulario.todas_fuentes ? [] : formulario.fuente_ids,
    sector_ids: formulario.todos_sectores ? [] : formulario.sector_ids,
});

/**
```

por:

```js
    todos_sectores: formulario.todos_sectores,
    fuente_ids: formulario.todas_fuentes ? [] : formulario.fuente_ids,
    sector_ids: formulario.todos_sectores ? [] : formulario.sector_ids,
    ...(uso === USO_CONTRATACION ? {
        todas_modalidades: Boolean(formulario.todas_modalidades),
        modalidad_ids: formulario.todas_modalidades ? [] : (formulario.modalidad_ids ?? []),
    } : {}),
});

/**
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (7 de 7):

```js
 * fuente SGR (salvo que ya sea para todas las fuentes): un dato inconsistente no debe llegar como 422.
 */
export const datosGuardarPlantilla = (formulario, plantilla, fuentes = []) => {
    if (!plantilla?.es_sgr) return datosPlantilla(formulario);
    const idSgr = fuentes.find((f) => f.clave === 'sgr')?.id;
    const conSgr = idSgr === undefined || formulario.todas_fuentes || formulario.fuente_ids.includes(idSgr)
        ? formulario.fuente_ids
        : [...formulario.fuente_ids, idSgr];
    return datosPlantilla({ ...formulario, todos_sectores: true, fuente_ids: conSgr });
};

/** Opciones de un catálogo: las activas y las que la plantilla ya tiene. */
export const opcionesCatalogo = (items, elegidos) => items.filter((i) => i.activo || elegidos.includes(i.id));

const CAMPOS_TEXTO = [
    'texto', 'descripcion', 'categoria', 'tipo_requisito', 'cargo_cumplimiento', 'cargo_revision',
    'soporte_requerido', 'referencia_normativa', 'nota', 'condicion_descripcion', 'codigo_comun',
```

por:

```js
 * fuente SGR (salvo que ya sea para todas las fuentes): un dato inconsistente no debe llegar como 422.
 */
export const datosGuardarPlantilla = (formulario, plantilla, fuentes = []) => {
    if (!plantilla?.es_sgr) return datosPlantilla(formulario, plantilla?.uso);
    const idSgr = fuentes.find((f) => f.clave === 'sgr')?.id;
    const conSgr = idSgr === undefined || formulario.todas_fuentes || formulario.fuente_ids.includes(idSgr)
        ? formulario.fuente_ids
        : [...formulario.fuente_ids, idSgr];
    return datosPlantilla({ ...formulario, todos_sectores: true, fuente_ids: conSgr }, plantilla.uso);
};

/** Opciones de un catálogo: las activas y las que la plantilla ya tiene. */
export const opcionesCatalogo = (items, elegidos) => items.filter((i) => i.activo || elegidos.includes(i.id));

/**
 * El catálogo más lo que la plantilla ya tiene y el catálogo no trae (por ejemplo, porque no se
 * pudo cargar): así no desaparece una casilla marcada. Lo que falta va al final, como llega.
 */
export const catalogoConPropios = (items, propios) => {
    const lista = items ?? [];
    const faltan = (propios ?? []).filter((p) => !lista.some((i) => i.id === p.id));
    return [...lista, ...faltan];
};

const CAMPOS_TEXTO = [
    'texto', 'descripcion', 'categoria', 'tipo_requisito', 'cargo_cumplimiento', 'cargo_revision',
    'soporte_requerido', 'referencia_normativa', 'nota', 'condicion_descripcion', 'codigo_comun',
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/plantillasChecklist.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 848 passed.

Run: `npm test`
Expected: 439 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/css/Plantillas.css resources/js/components/PlantillaDetalle.jsx resources/js/components/PlantillasChecklist.jsx resources/js/utils/plantillasChecklist.js resources/js/utils/plantillasChecklist.test.js
git commit -m "feat: usage and modalities in the template screens"
```

---

### Tarea 6: Requisitos por etapa y vista previa

Etapa obligatoria en el requisito de contratación, y requisitos y vista previa agrupados por etapa.

**Files:**
- Modify: `resources/css/Plantillas.css`
- Modify: `resources/js/components/PlantillaRequisitos.jsx`
- Modify: `resources/js/components/PlantillaVistaPrevia.jsx`
- Modify: `resources/js/utils/contratacion.js`
- Test: `resources/js/utils/contratacion.test.js`
- Modify: `resources/js/utils/plantillasChecklist.js`
- Test: `resources/js/utils/plantillasChecklist.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/contratacion.test.js`, reemplazar (1 de 2):

```js
    indiceFocoAtrapado,
    numeroProceso,
    opcionesConActual,
    puedeEditarProcesos,
    textoContratos,
    textoValor,
```

por:

```js
    indiceFocoAtrapado,
    numeroProceso,
    opcionesConActual,
    opcionesEtapa,
    puedeEditarProcesos,
    textoContratos,
    textoValor,
```

En `resources/js/utils/contratacion.test.js`, reemplazar (2 de 2):

```js
        expect(indiceFocoAtrapado(-1, 0, false)).toBe(-1);
    });
});
```

por:

```js
        expect(indiceFocoAtrapado(-1, 0, false)).toBe(-1);
    });
});

describe('opcionesEtapa', () => {
    const etapas = [
        { id: 12, nombre: 'Selección', orden: 3, activo: true },
        { id: 10, nombre: 'Planeación', orden: 1, activo: true },
        { id: 11, nombre: 'Precontractual', orden: 2, activo: false },
    ];

    it('ofrece las activas en el orden del catálogo, numeradas', () => {
        expect(opcionesEtapa(etapas, null)).toEqual([
            { valor: '10', etiqueta: '1. Planeación' },
            { valor: '12', etiqueta: '3. Selección' },
        ]);
    });

    it('conserva la etapa inactiva que el requisito ya tiene, marcada', () => {
        expect(opcionesEtapa(etapas, { id: 11, nombre: 'Precontractual', orden: 2, activo: false }).at(-1))
            .toEqual({ valor: '11', etiqueta: '2. Precontractual (inactiva)' });
        expect(opcionesEtapa(etapas, { id: 10, nombre: 'Planeación', orden: 1 })).toHaveLength(2);
    });

    it('a igual orden decide el id, como el servidor y la lista agrupada, no el nombre', () => {
        const empatadas = [
            { id: 21, nombre: 'Adendas', orden: 6, activo: true },
            { id: 20, nombre: 'Cierre', orden: 6, activo: true },
        ];
        expect(opcionesEtapa(empatadas, null).map((o) => o.valor)).toEqual(['20', '21']);
    });

    it('sin el catálogo cargado solo ofrece la actual, sin marca', () => {
        expect(opcionesEtapa(null, { id: 11, nombre: 'Precontractual', orden: 2 })).toEqual([{ valor: '11', etiqueta: '2. Precontractual' }]);
        expect(opcionesEtapa(undefined, null)).toEqual([]);
    });
});
```

En `resources/js/utils/plantillasChecklist.test.js`, reemplazar (1 de 2):

```js
    textoModalidades,
    usoDeConsulta,
    usoDePlantilla,
} from './plantillasChecklist';
import { RUTA_PLANTILLAS } from './navegacion';
```

por:

```js
    textoModalidades,
    usoDeConsulta,
    usoDePlantilla,
    SIN_ETAPA,
    agruparPorEtapa,
    compararEtapas,
    erroresDeEtapa,
    mismaEtapa,
    nombreEtapa,
    ordenTrasMoverEnEtapa,
    sinEtapa,
} from './plantillasChecklist';
import { RUTA_PLANTILLAS } from './navegacion';
```

En `resources/js/utils/plantillasChecklist.test.js`, reemplazar (2 de 2):

```js
        expect(opciones.map((m) => m.id)).toEqual([3, 4, 7]);
    });
});
```

por:

```js
        expect(opciones.map((m) => m.id)).toEqual([3, 4, 7]);
    });
});

describe('etapa del requisito de contratación', () => {
    const etapa = { id: 11, clave: 'seleccion', nombre: 'Selección', orden: 3, activo: false };

    it('un requisito nuevo de contratación nace sin etapa; uno de viabilización no la tiene', () => {
        expect(requisitoVacio('simple', USO_CONTRATACION)).toMatchObject({ etapa_id: '', etapa_actual: null });
        expect(requisitoVacio('simple')).not.toHaveProperty('etapa_id');
        expect(requisitoVacio('sgr', USO_VIABILIZACION)).not.toHaveProperty('etapa_actual');
    });

    it('lee la etapa del requisito y conserva la actual para su opción', () => {
        expect(formularioRequisito({ texto: 'x', etapa_id: 11, etapa }, 'simple', USO_CONTRATACION))
            .toMatchObject({ etapa_id: '11', etapa_actual: etapa });
        expect(formularioRequisito({ texto: 'x', etapa_id: null, etapa: null }, 'simple', USO_CONTRATACION))
            .toMatchObject({ etapa_id: '', etapa_actual: null });
        // Sin el objeto de la API, el id basta para no perder la opción.
        expect(formularioRequisito({ texto: 'x', etapa_id: 4 }, 'simple', USO_CONTRATACION).etapa_actual).toEqual({ id: 4, nombre: 'Etapa 4' });
        expect(formularioRequisito({ texto: 'x', etapa_id: 11, etapa }, 'simple')).not.toHaveProperty('etapa_id');
    });

    it('envía la etapa como número solo en contratación', () => {
        const formulario = formularioRequisito({ texto: 'Estudios previos', etapa_id: 11, etapa }, 'simple', USO_CONTRATACION);
        expect(datosRequisito(formulario, 'simple', false, USO_CONTRATACION).etapa_id).toBe(11);
        expect(datosRequisito({ ...formulario, etapa_id: '' }, 'simple', false, USO_CONTRATACION).etapa_id).toBeNull();
        expect(datosRequisito(formulario, 'simple')).not.toHaveProperty('etapa_id');
        expect(datosRequisito(formulario, 'simple', true, USO_VIABILIZACION)).not.toHaveProperty('etapa_id');
    });
});

describe('requisitos por etapa', () => {
    const planeacion = { id: 10, nombre: 'Planeación', orden: 1, activo: true };
    const seleccion = { id: 12, nombre: 'Selección', orden: 3, activo: false };
    const r = (id, orden, etapa, extra) => ({ id, orden, activo: true, texto: `R${id}`, etapa_id: etapa?.id ?? null, etapa: etapa ?? null, ...extra });

    it('agrupa en el orden del catálogo, numera las etapas y deja "Sin etapa" al final', () => {
        const grupos = agruparPorEtapa([r(1, 1, seleccion), r(2, 2, null), r(3, 3, planeacion), r(4, 4, seleccion)]);
        expect(grupos.map((g) => g.titulo)).toEqual(['1. Planeación', '3. Selección', 'Sin etapa']);
        expect(grupos.map((g) => g.clave)).toEqual(['etapa-10', 'etapa-12', SIN_ETAPA]);
        expect(grupos.map((g) => g.inactiva)).toEqual([false, true, false]);
        expect(grupos[1].requisitos.map((x) => x.id)).toEqual([1, 4]);
        expect(grupos[2].requisitos.map((x) => x.id)).toEqual([2]);
    });

    it('a igual orden decide el id; sin el objeto de la etapa usa el catálogo', () => {
        const otra = { id: 9, nombre: 'Otra', orden: 3, activo: true };
        const sinObjeto = { ...r(5, 1, otra), etapa: undefined };
        const grupos = agruparPorEtapa([r(1, 2, seleccion), sinObjeto], [otra, seleccion]);
        expect(grupos.map((g) => g.titulo)).toEqual(['3. Otra', '3. Selección']);
        expect(agruparPorEtapa([{ ...sinObjeto }], null)[0].titulo).toBe('Etapa 9');
        expect(agruparPorEtapa([], null)).toEqual([]);
        expect(nombreEtapa({ nombre: 'Planeación', orden: 1 })).toBe('1. Planeación');
        expect(nombreEtapa({ nombre: 'Planeación' })).toBe('Planeación');
        expect(nombreEtapa(null)).toBe('');
    });

    it('sin "Sin etapa" si todos la tienen', () => {
        expect(agruparPorEtapa([r(1, 1, planeacion)]).map((g) => g.clave)).toEqual(['etapa-10']);
        expect(sinEtapa({ etapa_id: null })).toBe(true);
        expect(sinEtapa({})).toBe(true);
        expect(sinEtapa({ etapa_id: 3 })).toBe(false);
    });

    it('la vista previa de contratación agrupa por etapa los activos, por orden', () => {
        const grupos = agruparVistaPrevia([
            r(1, 5, planeacion), r(2, 1, planeacion), r(3, 2, null, { activo: false }), r(4, 3, seleccion), r(5, 4, null),
        ], 'simple', USO_CONTRATACION);
        expect(grupos.map((g) => g.clave)).toEqual(['etapa-10', 'etapa-12', SIN_ETAPA]);
        expect(grupos[0].requisitos.map((x) => x.id)).toEqual([2, 1]);
        expect(grupos[2].requisitos.map((x) => x.id)).toEqual([5]);
        // Una plantilla simple de viabilización sigue por categoría.
        expect(agruparVistaPrevia([r(1, 1, planeacion, { categoria: 'juridico' })], 'simple').map((g) => g.titulo)).toEqual(['Jurídico']);
    });
});

describe('ordenTrasMoverEnEtapa', () => {
    const a = { id: 'a', orden: 1 };
    const b = { id: 'b', orden: 2 };
    // Orden de la versión: a1, b1, a2, b2, sin1, a3. Se ve: a1, a2, a3 | b1, b2 | sin1.
    const lista = [
        { id: 1, etapa_id: 'a', etapa: a }, { id: 2, etapa_id: 'b', etapa: b }, { id: 3, etapa_id: 'a', etapa: a },
        { id: 4, etapa_id: 'b', etapa: b }, { id: 5, etapa_id: null }, { id: 6, etapa_id: 'a', etapa: a },
    ];
    const ids = (l) => l.map((x) => x.id);

    it('envía el orden que se ve, por etapas, con el requisito cambiado por su vecino de etapa', () => {
        expect(ids(ordenTrasMoverEnEtapa(lista, 3, -1))).toEqual([3, 1, 6, 2, 4, 5]);
        expect(ids(ordenTrasMoverEnEtapa(lista, 3, 1))).toEqual([1, 6, 3, 2, 4, 5]);
        expect(ids(ordenTrasMoverEnEtapa(lista, 2, 1))).toEqual([1, 3, 6, 4, 2, 5]);
    });

    it('el orden enviado coincide con la lista agrupada tras recargar', () => {
        const nueva = ordenTrasMoverEnEtapa(lista, 4, -1).map((r, i) => ({ ...r, orden: i + 1 }));
        expect(agruparPorEtapa(nueva).flatMap((g) => g.requisitos)).toEqual(nueva);
    });

    it('sin vecino en su etapa hacia ese lado, o con un movimiento inválido, no hay orden nuevo', () => {
        expect(ordenTrasMoverEnEtapa(lista, 1, -1)).toBeNull();
        expect(ordenTrasMoverEnEtapa(lista, 6, 1)).toBeNull();
        expect(ordenTrasMoverEnEtapa(lista, 2, -1)).toBeNull();
        expect(ordenTrasMoverEnEtapa(lista, 5, -1)).toBeNull();
        expect(ordenTrasMoverEnEtapa(lista, 99, 1)).toBeNull();
        expect(ordenTrasMoverEnEtapa(lista, 3, 0)).toBeNull();
        expect(ordenTrasMoverEnEtapa(lista, 3, 2)).toBeNull();
    });

    it('sin etapa también es un grupo, y las etapas sin objeto se ordenan con el catálogo', () => {
        expect(mismaEtapa({ etapa_id: null }, {})).toBe(true);
        expect(mismaEtapa({ etapa_id: 1 }, { etapa_id: 2 })).toBe(false);
        const sinObjeto = [{ id: 1 }, { id: 2, etapa_id: 20 }, { id: 3, etapa_id: null }, { id: 4, etapa_id: 10 }];
        expect(ids(ordenTrasMoverEnEtapa(sinObjeto, 3, -1, [{ id: 10, orden: 1 }, { id: 20, orden: 2 }]))).toEqual([4, 2, 3, 1]);
    });
});

describe('compararEtapas', () => {
    it('por orden y, a igual orden, por id; sin orden, al final', () => {
        const etapas = [{ id: 7, orden: 2 }, { id: 3 }, { id: 9, orden: 1 }, { id: 5, orden: 2 }];
        expect([...etapas].sort(compararEtapas).map((e) => e.id)).toEqual([9, 5, 7, 3]);
    });
});

describe('errores de la etapa al guardar un requisito', () => {
    const respuesta = (status, data) => ({ response: { status, data } });

    it('de una regla de negocio toma los de errors.etapa_id y deja el resto de la lista para el aviso', () => {
        const error = respuesta(422, {
            mensaje: 'El requisito no es válido.',
            errores: ['La etapa "Selección" está inactiva.', 'Ya existe un requisito con la clave "x" en esta versión.'],
            errors: { etapa_id: ['La etapa "Selección" está inactiva.'] },
        });
        expect(erroresDeEtapa(error)).toEqual({
            etapa: ['La etapa "Selección" está inactiva.'],
            mensaje: 'El requisito no es válido.',
            otros: ['Ya existe un requisito con la clave "x" en esta versión.'],
        });
    });

    it('no adivina por el texto: sin errors.etapa_id, todo queda para el aviso', () => {
        const error = respuesta(422, { mensaje: 'El requisito no es válido.', errores: ['La etapa de algo que no es el campo.'] });
        expect(erroresDeEtapa(error)).toEqual({ etapa: [], mensaje: 'El requisito no es válido.', otros: ['La etapa de algo que no es el campo.'] });
    });

    it('solo con el error de la etapa no queda nada para el aviso', () => {
        const mensaje = 'Elige la etapa de contratación del requisito.';
        expect(erroresDeEtapa(respuesta(422, { mensaje: 'El requisito no es válido.', errores: [mensaje], errors: { etapa_id: [mensaje] } })))
            .toEqual({ etapa: [mensaje], mensaje: 'El requisito no es válido.', otros: [] });
    });

    it('separa los de la etapa de la validación de Laravel', () => {
        const error = respuesta(422, {
            message: 'Revisa la etapa: debe ser un número entero.',
            errors: { etapa_id: ['Revisa la etapa: debe ser un número entero.'], texto: ['Escribe el nombre del requisito.'] },
        });
        expect(erroresDeEtapa(error)).toEqual({
            etapa: ['Revisa la etapa: debe ser un número entero.'],
            mensaje: 'El requisito no es válido.',
            otros: ['Escribe el nombre del requisito.'],
        });
    });

    it('sin errores de la etapa, todo queda para el aviso; otro estado no se reparte', () => {
        expect(erroresDeEtapa(respuesta(422, { mensaje: 'El requisito no pertenece a esta versión.' })))
            .toEqual({ etapa: [], mensaje: 'El requisito no pertenece a esta versión.', otros: [] });
        expect(erroresDeEtapa(respuesta(500, {}))).toBeNull();
        expect(erroresDeEtapa(new Error('x'))).toBeNull();
    });
});

describe('errores al publicar una plantilla de contratación', () => {
    it('los requisitos sin etapa llegan como lista; la falta de modalidades, como mensaje', () => {
        const sinEtapa = {
            response: {
                status: 422,
                data: {
                    mensaje: 'La versión tiene requisitos sin etapa de contratación y no puede publicarse.',
                    errores: ['Estudios previos: elige su etapa de contratación.'],
                },
            },
        };
        expect(errorConLista(sinEtapa)).toEqual({
            mensaje: 'La versión tiene requisitos sin etapa de contratación y no puede publicarse.',
            errores: ['Estudios previos: elige su etapa de contratación.'],
        });
        const sinModalidades = {
            response: { status: 422, data: { mensaje: 'Asocia la plantilla de contratación a sus modalidades, o marca "Todas las modalidades", antes de publicar.' } },
        };
        expect(errorConLista(sinModalidades)).toBeNull();
        expect(mensajeApi(sinModalidades)).toBe('Asocia la plantilla de contratación a sus modalidades, o marca "Todas las modalidades", antes de publicar.');
    });
});
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/contratacion.test.js resources/js/utils/plantillasChecklist.test.js`
Expected: FALLAN 21 y pasan 118: no existen `opcionesEtapa` ni la agrupación y el orden por etapa.

- [ ] **Paso 3: Implementar**

En `resources/css/Plantillas.css`, reemplazar (1 de 2):

```css

.pl-formulario { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 16rem), 1fr)); gap: .9rem 1.1rem; padding: 1.1rem; }
.pl-campo { display: flex; flex-direction: column; gap: .3rem; min-width: 0; margin: 0; }
.pl-campo > span { font-size: .78rem; font-weight: 600; color: var(--gp-texto-2); }
.pl-campo-ancho, .pl-asociacion, .pl-formulario-acciones, .pl-formulario > .pl-ayuda { grid-column: 1 / -1; }
.pl-en-linea { display: flex; gap: .4rem; }
.pl-en-linea input { flex: 1; }
```

por:

```css

.pl-formulario { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 16rem), 1fr)); gap: .9rem 1.1rem; padding: 1.1rem; }
.pl-campo { display: flex; flex-direction: column; gap: .3rem; min-width: 0; margin: 0; }
.pl-campo > span, .pl-campo > label { font-size: .78rem; font-weight: 600; color: var(--gp-texto-2); }
.pl-campo-ancho, .pl-asociacion, .pl-formulario-acciones, .pl-formulario > .pl-ayuda { grid-column: 1 / -1; }
.pl-en-linea { display: flex; gap: .4rem; }
.pl-en-linea input { flex: 1; }
```

En `resources/css/Plantillas.css`, reemplazar (2 de 2):

```css
.pl-fila-acciones { display: inline-flex; flex-wrap: wrap; gap: .3rem; align-items: center; }
.pl-codigo { font-family: var(--gp-fuente-datos); font-size: .74rem; color: var(--gp-azul-texto); }

.pl-vista { display: grid; gap: .8rem; }
.pl-vista-grupo { padding: .9rem 1rem; }
.pl-vista-grupo h2 { margin: 0 0 .6rem; font-size: 1rem; }
```

por:

```css
.pl-fila-acciones { display: inline-flex; flex-wrap: wrap; gap: .3rem; align-items: center; }
.pl-codigo { font-family: var(--gp-fuente-datos); font-size: .74rem; color: var(--gp-azul-texto); }

/* Requisitos de una plantilla de contratación, por etapa. */
.pl-grupo { display: grid; gap: .5rem; margin-bottom: 1.1rem; }
.pl-grupo .pl-aviso-suave { margin-bottom: 0; }
.pl-grupo-titulo { display: flex; flex-wrap: wrap; gap: .45rem; align-items: baseline; margin: 0; font-size: 1rem; }

.pl-vista { display: grid; gap: .8rem; }
.pl-vista-grupo { padding: .9rem 1rem; }
.pl-vista-grupo h2 { margin: 0 0 .6rem; font-size: 1rem; }
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (1 de 11):

```jsx
import React, { useEffect, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowDown, faArrowUp, faPen, faPlus, faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { ORDEN_SECCIONES, SECCIONES } from '../utils/expedienteChecklist';
import { avisarError } from '../utils/avisosPlantillas';
import {
    CARACTERES, CATEGORIAS, CONDICIONADO, FUENTES_NORMATIVAS, TIPOS_REQUISITO, datosRequisito, etiquetaCaracter, etiquetaCategoria, formularioRequisito,
    mensajeApi, mover, opcionesResponsable, porRedactar, requisitoVacio,
} from '../utils/plantillasChecklist';
import useOcupado from './useOcupado';

const Campo = ({ etiqueta, children, ancho }) => (
    <label className={`pl-campo${ancho ? ' pl-campo-ancho' : ''}`}>
```

por:

```jsx
import React, { useEffect, useMemo, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowDown, faArrowUp, faPen, faPlus, faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { ORDEN_SECCIONES, SECCIONES } from '../utils/expedienteChecklist';
import { avisarError } from '../utils/avisosPlantillas';
import { opcionesEtapa } from '../utils/contratacion';
import {
    CARACTERES, CATEGORIAS, CONDICIONADO, FUENTES_NORMATIVAS, SIN_ETAPA, TIPOS_REQUISITO, USO_CONTRATACION, agruparPorEtapa, datosRequisito,
    erroresDeEtapa, etiquetaCaracter, etiquetaCategoria, formularioRequisito, htmlErrores, mensajeApi, mover, opcionesResponsable,
    ordenTrasMoverEnEtapa, porRedactar, requisitoVacio, sinEtapa, usoDePlantilla,
} from '../utils/plantillasChecklist';
import useOcupado from './useOcupado';
import '../../css/Contratacion.css';

const Campo = ({ etiqueta, children, ancho }) => (
    <label className={`pl-campo${ancho ? ' pl-campo-ancho' : ''}`}>
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (2 de 11):

```jsx
/**
 * Formulario de un requisito. En el motor sgr agrega sección, sector, tipo y condición.
 * `responsables` es el catálogo de Responsables (para elegir personas), o null si no se pudo cargar.
 */
const FormularioRequisito = ({ motor, esSgr, inicial, catalogoSgr, responsables, guardando, onGuardar, onCancelar }) => {
    const [f, setF] = useState(inicial);
    const [revisionCondicion, setRevisionCondicion] = useState(null);
    const cambiar = (campo, valor) => setF((actual) => ({ ...actual, [campo]: valor }));
    const texto = (campo, props = {}) => <input value={f[campo]} onChange={(e) => cambiar(campo, e.target.value)} {...props} />;
    // Las personas salen de los responsables activos; la que el requisito ya tiene se conserva
    // aunque esté inactiva, y un cargo sin persona (dato anterior), como "Solo cargo". Sin el
```

por:

```jsx
/**
 * Formulario de un requisito. En el motor sgr agrega sección, sector, tipo y condición.
 * `responsables` es el catálogo de Responsables (para elegir personas), o null si no se pudo cargar.
 * En una plantilla de contratación pide la etapa: `etapas` es su catálogo (null mientras carga o si
 * falló, con `avisoEtapas`) y `errorEtapa`, { texto, enfocar } con lo que el servidor objetó de
 * ella y si el foco debe ir ya al campo (no mientras se muestra el aviso con los demás errores).
 */
const FormularioRequisito = ({
    motor, esSgr, contratacion, inicial, catalogoSgr, responsables, etapas, avisoEtapas, errorEtapa, onCambioEtapa, guardando, onGuardar, onCancelar,
}) => {
    const [f, setF] = useState(inicial);
    const [revisionCondicion, setRevisionCondicion] = useState(null);
    const campoEtapa = useRef(null);
    const cambiar = (campo, valor) => setF((actual) => ({ ...actual, [campo]: valor }));

    // Si el servidor objeta la etapa, el foco va al campo, donde se muestra el error.
    useEffect(() => {
        if (errorEtapa?.enfocar) campoEtapa.current?.focus();
    }, [errorEtapa]);

    const texto = (campo, props = {}) => <input value={f[campo]} onChange={(e) => cambiar(campo, e.target.value)} {...props} />;
    // Las personas salen de los responsables activos; la que el requisito ya tiene se conserva
    // aunque esté inactiva, y un cargo sin persona (dato anterior), como "Solo cargo". Sin el
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (3 de 11):

```jsx
                <textarea rows={2} value={f.descripcion} onChange={(e) => cambiar('descripcion', e.target.value)} />
            </Campo>

            {motor === 'sgr' && (
                <>
                    <Campo etiqueta="Clave">{texto('clave', { required: true, maxLength: 40, pattern: '[a-z0-9_]+', title: 'Solo minúsculas, números y guion bajo.' })}</Campo>
```

por:

```jsx
                <textarea rows={2} value={f.descripcion} onChange={(e) => cambiar('descripcion', e.target.value)} />
            </Campo>

            {/* El error y el aviso quedan fuera del <label>: se leen como descripción, no como nombre del campo. */}
            {contratacion && (
                <div className="pl-campo">
                    <label htmlFor="pl-etapa">Etapa</label>
                    <select
                        id="pl-etapa"
                        ref={campoEtapa}
                        required
                        value={f.etapa_id}
                        aria-invalid={Boolean(errorEtapa)}
                        aria-describedby={[errorEtapa && 'pl-etapa-error', avisoEtapas && 'pl-etapa-aviso'].filter(Boolean).join(' ') || undefined}
                        onChange={(e) => { cambiar('etapa_id', e.target.value); onCambioEtapa(); }}
                    >
                        <option value="">Elige la etapa</option>
                        {opcionesEtapa(etapas, f.etapa_actual).map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                    </select>
                    {errorEtapa && <small id="pl-etapa-error" className="ct-error-campo">{errorEtapa.texto}</small>}
                    {avisoEtapas && <small id="pl-etapa-aviso" className="pl-ayuda">{avisoEtapas}</small>}
                </div>
            )}

            {motor === 'sgr' && (
                <>
                    <Campo etiqueta="Clave">{texto('clave', { required: true, maxLength: 40, pattern: '[a-z0-9_]+', title: 'Solo minúsculas, números y guion bajo.' })}</Campo>
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (4 de 11):

```jsx
    );
};

/** Requisitos de la versión de trabajo: se editan en borrador; una versión publicada solo se lee. */
const PlantillaRequisitos = ({ plantilla, version, requisitos, onCambio }) => {
    const [editando, setEditando] = useState(null); // null, 'nuevo' o el requisito
    const [catalogoSgr, setCatalogoSgr] = useState(null);
    // Responsables, para elegir las personas del requisito. null mientras carga o si falla.
    const [responsables, setResponsables] = useState(null);
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    const retorno = useRef(null); // data-foco del botón que abrió el formulario
    const editable = version?.estado === 'borrador';
    const esSgr = plantilla.es_sgr;
    const url = version ? `/catalogo-requisitos/versiones/${version.id}` : null;

    useEffect(() => {
        if (plantilla.motor !== 'sgr') return undefined;
```

por:

```jsx
    );
};

/** Una fila de la lista de requisitos, con sus acciones si la versión es un borrador. */
const FilaRequisito = ({ requisito: r, motor, editable, ocupado, primero, ultimo, onMover, onEditar, onEliminar }) => (
    <li className={r.activo ? '' : 'inactivo'}>
        <div className="pl-fila-texto">
            <span className="pl-codigo">{r.codigo || r.clave}</span>
            <span>{r.texto}</span>
            <span className="pl-fila-etiquetas">
                {r.categoria && <span className="pl-insignia pl-insignia-suave">{etiquetaCategoria(r.categoria)}</span>}
                {motor === 'simple' && <span className="pl-insignia pl-insignia-suave">{etiquetaCaracter(r.caracter)}</span>}
                {porRedactar(r) && <span className="pl-insignia pl-insignia-alerta">Por redactar</span>}
                {!r.activo && <span className="pl-insignia pl-insignia-alerta">Inactivo</span>}
            </span>
        </div>
        {editable && (
            <div className="pl-fila-acciones">
                <button type="button" className="gp-icono" aria-label={`Subir ${r.texto}`} disabled={ocupado || primero} onClick={() => onMover(-1)}>
                    <FontAwesomeIcon icon={faArrowUp} />
                </button>
                <button type="button" className="gp-icono" aria-label={`Bajar ${r.texto}`} disabled={ocupado || ultimo} onClick={() => onMover(1)}>
                    <FontAwesomeIcon icon={faArrowDown} />
                </button>
                <button type="button" className="gp-icono" aria-label={`Editar ${r.texto}`} disabled={ocupado} data-foco={`editar-${r.id}`} onClick={onEditar}>
                    <FontAwesomeIcon icon={faPen} />
                </button>
                <button type="button" className="gp-icono" aria-label={`Eliminar ${r.texto}`} disabled={ocupado} onClick={onEliminar}>
                    <FontAwesomeIcon icon={faTrash} />
                </button>
            </div>
        )}
    </li>
);

/**
 * Requisitos de la versión de trabajo: se editan en borrador; una versión publicada solo se lee.
 * En una plantilla de contratación cada requisito lleva su etapa y la lista se agrupa por etapa,
 * en el orden del catálogo; las flechas mueven un requisito entre los de su etapa.
 */
const PlantillaRequisitos = ({ plantilla, version, requisitos, onCambio }) => {
    const [editando, setEditando] = useState(null); // null, 'nuevo' o el requisito
    const [catalogoSgr, setCatalogoSgr] = useState(null);
    // Responsables, para elegir las personas del requisito. null mientras carga o si falla.
    const [responsables, setResponsables] = useState(null);
    // Etapas de contratación, para el select del requisito. null mientras cargan o si fallan.
    const [etapas, setEtapas] = useState(null);
    const [avisoEtapas, setAvisoEtapas] = useState(null);
    const [errorEtapa, setErrorEtapa] = useState(null); // { texto, enfocar } del servidor, bajo el campo
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    const retorno = useRef(null); // data-foco del botón que abrió el formulario
    const editable = version?.estado === 'borrador';
    const esSgr = plantilla.es_sgr;
    const uso = usoDePlantilla(plantilla);
    const contratacion = uso === USO_CONTRATACION;
    const url = version ? `/catalogo-requisitos/versiones/${version.id}` : null;
    const grupos = useMemo(() => (contratacion ? agruparPorEtapa(requisitos, etapas) : null), [contratacion, requisitos, etapas]);

    useEffect(() => {
        if (plantilla.motor !== 'sgr') return undefined;
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (5 de 11):

```jsx
        return () => { vigente = false; };
    }, [plantilla.motor]);

    // Si falla, el formulario deja escribir los cargos a mano: no hace falta avisar.
    useEffect(() => {
        let vigente = true;
```

por:

```jsx
        return () => { vigente = false; };
    }, [plantilla.motor]);

    useEffect(() => {
        if (!contratacion) return undefined;
        let vigente = true;
        axios.get('/etapasContratacion')
            .then(({ data }) => { if (vigente) setEtapas(Array.isArray(data) ? data : null); })
            .catch((e) => { if (vigente) setAvisoEtapas(`No se pudo cargar la lista de etapas. ${mensajeApi(e)}`); });
        return () => { vigente = false; };
    }, [contratacion]);

    // Si falla, el formulario deja escribir los cargos a mano: no hace falta avisar.
    useEffect(() => {
        let vigente = true;
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (6 de 11):

```jsx

    const abrir = (requisito) => {
        retorno.current = requisito === 'nuevo' ? 'nuevo' : `editar-${requisito.id}`;
        setEditando(requisito);
    };

    const crearBorrador = () => ejecutar(async () => {
        try {
            await axios.post(`/plantillas/${plantilla.id}/versiones`, { descripcion: null });
```

por:

```jsx

    const abrir = (requisito) => {
        retorno.current = requisito === 'nuevo' ? 'nuevo' : `editar-${requisito.id}`;
        setErrorEtapa(null);
        setEditando(requisito);
    };

    const cerrar = () => {
        setErrorEtapa(null);
        setEditando(null);
    };

    const crearBorrador = () => ejecutar(async () => {
        try {
            await axios.post(`/plantillas/${plantilla.id}/versiones`, { descripcion: null });
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (7 de 11):

```jsx
    });

    const guardar = (formulario) => ejecutar(async () => {
        const datos = datosRequisito(formulario, plantilla.motor, responsables !== null);
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
    });
```

por:

```jsx
    });

    const guardar = (formulario) => ejecutar(async () => {
        const datos = datosRequisito(formulario, plantilla.motor, responsables !== null, uso);
        try {
            if (editando === 'nuevo') {
                await axios.post(`${url}/requisitos`, datos);
            } else {
                await axios.put(`${url}/requisitos/${editando.id}`, datos);
            }
            cerrar();
            await onCambio();
        } catch (e) {
            // Lo que el servidor objeta de la etapa va bajo su campo; el resto, en el aviso de siempre.
            // El foco termina en la etapa: enseguida si no hay aviso o, si lo hay, al cerrarlo (el
            // aviso no lo devuelve a "Guardar requisito").
            const repartidos = contratacion ? erroresDeEtapa(e) : null;
            if (!repartidos?.etapa.length) {
                avisarError('No se pudo guardar el requisito', e);
                return;
            }
            const texto = repartidos.etapa.join(' ');
            if (!repartidos.otros.length) {
                setErrorEtapa({ texto, enfocar: true });
                return;
            }
            setErrorEtapa({ texto, enfocar: false });
            await Swal.fire({
                icon: 'error',
                titleText: 'No se pudo guardar el requisito',
                html: htmlErrores(repartidos.mensaje, repartidos.otros),
                returnFocus: false,
            });
            setErrorEtapa((actual) => (actual ? { ...actual, enfocar: true } : actual));
        }
    });
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (8 de 11):

```jsx
        }
    });

    // Los botones quedan deshabilitados hasta que llega la recarga con el orden nuevo.
    const moverRequisito = (indice, delta) => ejecutar(async () => {
        const ids = mover(requisitos.map((r) => r.id), indice, delta);
        try {
            await axios.put(`${url}/requisitos-orden`, { ids });
            await onCambio();
```

por:

```jsx
        }
    });

    // Los botones quedan deshabilitados hasta que llega la recarga con el orden nuevo. Se envía el
    // orden de toda la versión; en contratación, el que se ve (por etapas), con el requisito
    // cambiado por el vecino de su etapa, para que el orden guardado coincida con la lista.
    const moverRequisito = (requisito, delta) => ejecutar(async () => {
        const nueva = contratacion
            ? ordenTrasMoverEnEtapa(requisitos, requisito.id, delta, etapas)
            : mover(requisitos, requisitos.indexOf(requisito), delta);
        if (!nueva) return;
        const ids = nueva.map((r) => r.id);
        try {
            await axios.put(`${url}/requisitos-orden`, { ids });
            await onCambio();
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (9 de 11):

```jsx
    });

    const porRedactarTotal = requisitos.filter((r) => r.activo && porRedactar(r)).length;

    if (!version) {
        return (
```

por:

```jsx
    });

    const porRedactarTotal = requisitos.filter((r) => r.activo && porRedactar(r)).length;
    // Publicar exige la etapa de cada requisito activo; los inactivos sin etapa no lo impiden.
    const sinEtapaActivos = contratacion ? requisitos.filter((r) => r.activo && sinEtapa(r)).length : 0;

    if (!version) {
        return (
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (10 de 11):

```jsx
            <FormularioRequisito
                motor={plantilla.motor}
                esSgr={esSgr}
                catalogoSgr={catalogoSgr}
                responsables={responsables}
                guardando={ocupado}
                inicial={editando === 'nuevo' ? requisitoVacio(plantilla.motor) : formularioRequisito(editando, plantilla.motor)}
                onGuardar={guardar}
                onCancelar={() => setEditando(null)}
            />
        );
    }

    return (
        <div className="pl-requisitos" ref={contenedor}>
            <div className="pl-barra">
```

por:

```jsx
            <FormularioRequisito
                motor={plantilla.motor}
                esSgr={esSgr}
                contratacion={contratacion}
                catalogoSgr={catalogoSgr}
                responsables={responsables}
                etapas={etapas}
                avisoEtapas={avisoEtapas}
                errorEtapa={errorEtapa}
                onCambioEtapa={() => setErrorEtapa(null)}
                guardando={ocupado}
                inicial={editando === 'nuevo' ? requisitoVacio(plantilla.motor, uso) : formularioRequisito(editando, plantilla.motor, uso)}
                onGuardar={guardar}
                onCancelar={cerrar}
            />
        );
    }

    const fila = (r, primero, ultimo) => (
        <FilaRequisito
            key={r.id}
            requisito={r}
            motor={plantilla.motor}
            editable={editable}
            ocupado={ocupado}
            primero={primero}
            ultimo={ultimo}
            onMover={(delta) => moverRequisito(r, delta)}
            onEditar={() => abrir(r)}
            onEliminar={() => eliminar(r)}
        />
    );

    return (
        <div className="pl-requisitos" ref={contenedor}>
            <div className="pl-barra">
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (11 de 11):

```jsx

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
                                {porRedactar(r) && <span className="pl-insignia pl-insignia-alerta">Por redactar</span>}
                                {!r.activo && <span className="pl-insignia pl-insignia-alerta">Inactivo</span>}
                            </span>
                        </div>
                        {editable && (
                            <div className="pl-fila-acciones">
                                <button type="button" className="gp-icono" aria-label={`Subir ${r.texto}`} disabled={ocupado || indice === 0} onClick={() => moverRequisito(indice, -1)}>
                                    <FontAwesomeIcon icon={faArrowUp} />
                                </button>
                                <button type="button" className="gp-icono" aria-label={`Bajar ${r.texto}`} disabled={ocupado || indice === requisitos.length - 1} onClick={() => moverRequisito(indice, 1)}>
                                    <FontAwesomeIcon icon={faArrowDown} />
                                </button>
                                <button type="button" className="gp-icono" aria-label={`Editar ${r.texto}`} disabled={ocupado} data-foco={`editar-${r.id}`} onClick={() => abrir(r)}>
                                    <FontAwesomeIcon icon={faPen} />
                                </button>
                                <button type="button" className="gp-icono" aria-label={`Eliminar ${r.texto}`} disabled={ocupado} onClick={() => eliminar(r)}>
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
```

por:

```jsx

            {requisitos.length === 0 && <p className="gp-vacio">Esta versión aún no tiene requisitos.</p>}

            {grupos ? grupos.map((g) => (
                <section key={g.clave} className="pl-grupo" aria-labelledby={`pl-grupo-${g.clave}`}>
                    <h2 id={`pl-grupo-${g.clave}`} className="pl-grupo-titulo">
                        {g.titulo}
                        {g.inactiva && <span className="pl-insignia pl-insignia-alerta">Etapa inactiva</span>}
                        <span className="pl-ayuda">· {g.requisitos.length}</span>
                    </h2>
                    {g.clave === SIN_ETAPA && sinEtapaActivos > 0 && (
                        <p className="pl-aviso-suave" role="status">
                            {sinEtapaActivos === 1
                                ? '1 requisito activo no tiene etapa: edítalo y elige su etapa antes de publicar.'
                                : `${sinEtapaActivos} requisitos activos no tienen etapa: edítalos y elige la etapa de cada uno antes de publicar.`}
                        </p>
                    )}
                    <ol className="pl-tabla">
                        {g.requisitos.map((r, j) => fila(r, j === 0, j === g.requisitos.length - 1))}
                    </ol>
                </section>
            )) : (
                <ol className="pl-tabla">
                    {requisitos.map((r, indice) => fila(r, indice === 0, indice === requisitos.length - 1))}
                </ol>
            )}
        </div>
    );
};
```

En `resources/js/components/PlantillaVistaPrevia.jsx`, reemplazar (1 de 2):

```jsx
import React, { useMemo } from 'react';
import { agruparVistaPrevia, datosVistaPrevia, porRedactar } from '../utils/plantillasChecklist';

/** El checklist de la versión de trabajo como lo verá el usuario en un expediente. */
const PlantillaVistaPrevia = ({ plantilla, version, requisitos }) => {
    const grupos = useMemo(() => agruparVistaPrevia(requisitos, plantilla.motor), [requisitos, plantilla.motor]);

    if (!version) return <p className="gp-vacio">La plantilla no tiene versiones.</p>;
    if (grupos.length === 0) return <p className="gp-vacio">La versión v{version.numero} no tiene requisitos activos.</p>;
```

por:

```jsx
import React, { useMemo } from 'react';
import { SIN_ETAPA, USO_CONTRATACION, agruparVistaPrevia, datosVistaPrevia, porRedactar, usoDePlantilla } from '../utils/plantillasChecklist';

/**
 * El checklist de la versión de trabajo como lo verá el usuario en un expediente. Una plantilla de
 * contratación se ve por etapa, en el orden del catálogo.
 */
const PlantillaVistaPrevia = ({ plantilla, version, requisitos }) => {
    const uso = usoDePlantilla(plantilla);
    const grupos = useMemo(() => agruparVistaPrevia(requisitos, plantilla.motor, uso), [requisitos, plantilla.motor, uso]);

    if (!version) return <p className="gp-vacio">La plantilla no tiene versiones.</p>;
    if (grupos.length === 0) return <p className="gp-vacio">La versión v{version.numero} no tiene requisitos activos.</p>;
```

En `resources/js/components/PlantillaVistaPrevia.jsx`, reemplazar (2 de 2):

```jsx
                Versión v{version.numero}{version.estado === 'borrador' ? ' (borrador)' : ''}.
                {plantilla.motor === 'sgr'
                    ? ' En cada expediente, el evaluador SGR decide cuáles aplican según la fase, el sector y los componentes.'
                    : ' Los condicionados nacen "por definir" y una persona decide si aplican.'}
            </p>
            {grupos.map((g) => (
                <section key={g.clave} className="gp-panel pl-vista-grupo">
                    <h2>{g.titulo} <span className="pl-ayuda">· {g.requisitos.length}</span></h2>
                    <ul>
                        {g.requisitos.map((r) => (
                            <li key={r.id}>
```

por:

```jsx
                Versión v{version.numero}{version.estado === 'borrador' ? ' (borrador)' : ''}.
                {plantilla.motor === 'sgr'
                    ? ' En cada expediente, el evaluador SGR decide cuáles aplican según la fase, el sector y los componentes.'
                    : `${uso === USO_CONTRATACION ? ' Por etapa del proceso de contratación.' : ''} Los condicionados nacen "por definir" y una persona decide si aplican.`}
            </p>
            {grupos.map((g) => (
                <section key={g.clave} className="gp-panel pl-vista-grupo">
                    <h2 className="pl-grupo-titulo">
                        {g.titulo}
                        {g.inactiva && <span className="pl-insignia pl-insignia-alerta">Etapa inactiva</span>}
                        <span className="pl-ayuda">· {g.requisitos.length}</span>
                    </h2>
                    {g.clave === SIN_ETAPA && <p className="pl-aviso-suave">Elige la etapa de estos requisitos antes de publicar.</p>}
                    <ul>
                        {g.requisitos.map((r) => (
                            <li key={r.id}>
```

En `resources/js/utils/contratacion.js`, reemplazar (1 de 2):

```js
// adelantan los mismos mensajes para no esperar un 422.

import { normalizar } from './navegacion';
import { mover } from './plantillasChecklist';

/** Modalidades y etapas se administran con gestionar-catalogos: solo un Administrador. */
export const puedeAdministrarCatalogos = (usuario) => usuario?.rol === 'Administrador';
```

por:

```js
// adelantan los mismos mensajes para no esperar un 422.

import { normalizar } from './navegacion';
import { compararEtapas, mover, nombreEtapa } from './plantillasChecklist';

/** Modalidades y etapas se administran con gestionar-catalogos: solo un Administrador. */
export const puedeAdministrarCatalogos = (usuario) => usuario?.rol === 'Administrador';
```

En `resources/js/utils/contratacion.js`, reemplazar (2 de 2):

```js
    return opciones;
};

export const PROCESO_VACIO = {
    objeto: '',
    modalidad_id: '',
```

por:

```js
    return opciones;
};

/**
 * Opciones del select de etapa de un requisito de contratación: las activas en el orden del
 * catálogo (por orden y, a igual orden, por id, como el servidor y la lista agrupada), numeradas,
 * y la que el requisito ya tiene (`actual`) si está inactiva, marcada. Con el catálogo sin cargar
 * (null) solo se ofrece la actual.
 */
export const opcionesEtapa = (etapas, actual) => {
    const numerada = (etapa) => ({ ...etapa, nombre: nombreEtapa(etapa) });
    const lista = etapas === null || etapas === undefined ? null : [...etapas].sort(compararEtapas).map(numerada);
    return opcionesConActual(lista, actual ? numerada(actual) : null);
};

export const PROCESO_VACIO = {
    objeto: '',
    modalidad_id: '',
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (1 de 8):

```js
// En el formulario, la persona que el requisito ya tenía: conserva su opción aunque esté inactiva.
const personaDelFormulario = (campoObjeto) => campoObjeto.replace('responsable_', 'persona_');

export const requisitoVacio = (motor) => ({
    ...Object.fromEntries(CAMPOS_TEXTO.map((c) => [c, ''])),
    ...Object.fromEntries(PERSONAS.flatMap(([campoId, , campoObjeto]) => [
        [campoId, OPCION_SIN_ASIGNAR], [personaDelFormulario(campoObjeto), null],
```

por:

```js
// En el formulario, la persona que el requisito ya tenía: conserva su opción aunque esté inactiva.
const personaDelFormulario = (campoObjeto) => campoObjeto.replace('responsable_', 'persona_');

/**
 * Un requisito nuevo. En una plantilla de contratación (`uso`) lleva además su etapa: el id del
 * select y, en `etapa_actual`, la que el requisito ya tiene (conserva su opción aunque esté inactiva).
 */
export const requisitoVacio = (motor, uso = USO_VIABILIZACION) => ({
    ...Object.fromEntries(CAMPOS_TEXTO.map((c) => [c, ''])),
    ...Object.fromEntries(PERSONAS.flatMap(([campoId, , campoObjeto]) => [
        [campoId, OPCION_SIN_ASIGNAR], [personaDelFormulario(campoObjeto), null],
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (2 de 8):

```js
    sector_id: '',
    tipo_id: '',
    ...(motor === 'sgr' ? { seccion: 'A', fuente: 'a12', condicion: 'siempre' } : {}),
});

export const formularioRequisito = (requisito, motor) => {
    const base = requisitoVacio(motor);
    // El respaldo solo cubre un campo que no viene (undefined); un null del servidor es un dato real.
    const texto = (campo) => (requisito[campo] === undefined ? base[campo] : requisito[campo]) ?? '';
    const conRespaldo = (campo) => requisito[campo] ?? base[campo];
```

por:

```js
    sector_id: '',
    tipo_id: '',
    ...(motor === 'sgr' ? { seccion: 'A', fuente: 'a12', condicion: 'siempre' } : {}),
    ...(uso === USO_CONTRATACION ? { etapa_id: '', etapa_actual: null } : {}),
});

/** La etapa que el requisito ya tiene, para el formulario; sin el objeto de la API, solo su id. */
const etapaActual = (requisito) => {
    if (requisito.etapa_id === null || requisito.etapa_id === undefined) return null;
    return requisito.etapa ?? { id: requisito.etapa_id, nombre: `Etapa ${requisito.etapa_id}` };
};

export const formularioRequisito = (requisito, motor, uso = USO_VIABILIZACION) => {
    const base = requisitoVacio(motor, uso);
    // El respaldo solo cubre un campo que no viene (undefined); un null del servidor es un dato real.
    const texto = (campo) => (requisito[campo] === undefined ? base[campo] : requisito[campo]) ?? '';
    const conRespaldo = (campo) => requisito[campo] ?? base[campo];
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (3 de 8):

```js
            const eleccion = id ? String(id) : (String(requisito[campoCargo] ?? '').trim() ? OPCION_SOLO_CARGO : OPCION_SIN_ASIGNAR);
            return [[campoId, eleccion], [personaDelFormulario(campoObjeto), id ? (requisito[campoObjeto] ?? null) : null]];
        })),
    };
};
```

por:

```js
            const eleccion = id ? String(id) : (String(requisito[campoCargo] ?? '').trim() ? OPCION_SOLO_CARGO : OPCION_SIN_ASIGNAR);
            return [[campoId, eleccion], [personaDelFormulario(campoObjeto), id ? (requisito[campoObjeto] ?? null) : null]];
        })),
        ...(uso === USO_CONTRATACION ? {
            etapa_id: requisito.etapa_id === null || requisito.etapa_id === undefined ? '' : String(requisito.etapa_id),
            etapa_actual: etapaActual(requisito),
        } : {}),
    };
};
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (4 de 8):

```js
/**
 * Lo que espera la API según el motor. Los textos vacíos viajan como null. Con `conPersonas`
 * (el catálogo de responsables cargó y se eligieron en selects) van los ids de las personas; sin
 * él solo viaja el cargo escrito y las personas del requisito no cambian.
 */
export const datosRequisito = (formulario, motor, conPersonas = false) => {
    const texto = (campo) => (String(formulario[campo] ?? '').trim() || null);
    // Con persona, el cargo lo copia el servidor; "Solo cargo" conserva el escrito; "Sin asignar" lo vacía.
    const personas = conPersonas ? Object.fromEntries(PERSONAS.flatMap(([campoId, campoCargo]) => {
```

por:

```js
/**
 * Lo que espera la API según el motor. Los textos vacíos viajan como null. Con `conPersonas`
 * (el catálogo de responsables cargó y se eligieron en selects) van los ids de las personas; sin
 * él solo viaja el cargo escrito y las personas del requisito no cambian. La etapa solo viaja en
 * una plantilla de contratación (`uso`): la de viabilización la rechaza.
 */
export const datosRequisito = (formulario, motor, conPersonas = false, uso = USO_VIABILIZACION) => {
    const texto = (campo) => (String(formulario[campo] ?? '').trim() || null);
    // Con persona, el cargo lo copia el servidor; "Solo cargo" conserva el escrito; "Sin asignar" lo vacía.
    const personas = conPersonas ? Object.fromEntries(PERSONAS.flatMap(([campoId, campoCargo]) => {
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (5 de 8):

```js
        activo: formulario.activo,
        codigo_comun: texto('codigo_comun'),
        ...personas,
    };

    if (motor === 'sgr') {
```

por:

```js
        activo: formulario.activo,
        codigo_comun: texto('codigo_comun'),
        ...personas,
        ...(uso === USO_CONTRATACION ? { etapa_id: Number(formulario.etapa_id) || null } : {}),
    };

    if (motor === 'sgr') {
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (6 de 8):

```js
    };
};

/**
 * Vista previa: los requisitos activos como los verá el usuario. El SGR por sección,
 * en el orden del expediente; una plantilla simple por categoría.
 */
export const agruparVistaPrevia = (requisitos, motor) => {
    const activos = requisitos.filter((r) => r.activo).sort((a, b) => a.orden - b.orden);

    if (motor === 'sgr') {
        return ORDEN_SECCIONES
            .map((clave) => ({
```

por:

```js
    };
};

/** "3. Selección" con el número de la etapa en el catálogo (su orden), o solo el nombre si no lo trae. */
export const nombreEtapa = (etapa) => (etapa?.orden ? `${etapa.orden}. ${etapa.nombre}` : String(etapa?.nombre ?? ''));

/** Clave del grupo de los requisitos de contratación que aún no tienen etapa. */
export const SIN_ETAPA = 'sin-etapa';

export const sinEtapa = (requisito) => requisito.etapa_id === null || requisito.etapa_id === undefined;

/** Los dos requisitos están en la misma etapa, o los dos sin ella. */
export const mismaEtapa = (a, b) => (a.etapa_id ?? null) === (b.etapa_id ?? null);

/**
 * Orden del catálogo de etapas, el mismo del servidor: por `orden` y, a igual orden, por id. Una
 * etapa sin `orden` (solo se conoce su id) va después de las demás.
 */
export const compararEtapas = (a, b) => (a.orden ?? Number.MAX_SAFE_INTEGER) - (b.orden ?? Number.MAX_SAFE_INTEGER) || a.id - b.id;

/**
 * Requisitos de una plantilla de contratación por etapa, en el orden del catálogo (orden y, a igual
 * orden, id) y, dentro de cada etapa, en el orden en que llegan. El título lleva el número de la
 * etapa (su orden, el de Parámetros → Etapas de contratación) y su nombre. "Sin etapa" va al final:
 * son requisitos que hay que completar antes de publicar. La etapa es la que trae cada requisito
 * de la API o, si no la trae, la de `etapas` (el catálogo) con su id.
 */
export const agruparPorEtapa = (requisitos, etapas = null) => {
    const grupos = new Map();
    const sinAsignar = [];
    (requisitos ?? []).forEach((r) => {
        if (sinEtapa(r)) {
            sinAsignar.push(r);
            return;
        }
        if (!grupos.has(r.etapa_id)) {
            const etapa = r.etapa ?? (etapas ?? []).find((e) => e.id === r.etapa_id) ?? { id: r.etapa_id, nombre: `Etapa ${r.etapa_id}` };
            grupos.set(r.etapa_id, { etapa, requisitos: [] });
        }
        grupos.get(r.etapa_id).requisitos.push(r);
    });

    const conEtapa = [...grupos.values()]
        .sort((a, b) => compararEtapas(a.etapa, b.etapa))
        .map(({ etapa, requisitos: lista }) => ({
            clave: `etapa-${etapa.id}`,
            titulo: nombreEtapa(etapa),
            inactiva: etapa.activo === false,
            requisitos: lista,
        }));
    return sinAsignar.length
        ? [...conEtapa, { clave: SIN_ETAPA, titulo: 'Sin etapa', inactiva: false, requisitos: sinAsignar }]
        : conEtapa;
};

/**
 * Vista previa: los requisitos activos como los verá el usuario. El SGR por sección,
 * en el orden del expediente; una plantilla de contratación (`uso`) por etapa; otra plantilla
 * simple, por categoría.
 */
export const agruparVistaPrevia = (requisitos, motor, uso = USO_VIABILIZACION, etapas = null) => {
    const activos = requisitos.filter((r) => r.activo).sort((a, b) => a.orden - b.orden);

    if (uso === USO_CONTRATACION) return agruparPorEtapa(activos, etapas);

    if (motor === 'sgr') {
        return ORDEN_SECCIONES
            .map((clave) => ({
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (7 de 8):

```js
    return copia;
};

/** Requisitos activos de una versión y, si los hay, cuántos inactivos tiene: "206" o "206 · 1 inactivo". */
export const textoRequisitosVersion = (version) => {
    const activos = version.requisitos_activos ?? version.requisitos;
```

por:

```js
    return copia;
};

/**
 * Los requisitos de una versión de contratación en el orden en que se ven tras mover el requisito
 * `id` un lugar arriba (-1) o abajo (+1) dentro de su etapa: los grupos de `agruparPorEtapa`, uno
 * tras otro, con el cambio aplicado. Es lo que se envía como orden de la versión, para que el
 * `orden` guardado coincida con la lista. null si no tiene vecino en su etapa hacia ese lado.
 */
export const ordenTrasMoverEnEtapa = (requisitos, id, delta, etapas = null) => {
    const visibles = agruparPorEtapa(requisitos, etapas).flatMap((g) => g.requisitos);
    const indice = visibles.findIndex((r) => r.id === id);
    const vecino = visibles[indice + delta];
    if (indice < 0 || (delta !== -1 && delta !== 1) || !vecino || !mismaEtapa(vecino, visibles[indice])) return null;
    return mover(visibles, indice, delta);
};

/** Requisitos activos de una versión y, si los hay, cuántos inactivos tiene: "206" o "206 · 1 inactivo". */
export const textoRequisitosVersion = (version) => {
    const activos = version.requisitos_activos ?? version.requisitos;
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (8 de 8):

```js
    return mensajeError(error);
};

/** El mensaje y la lista de un 422 de reglas de negocio ({ mensaje, errores }); null si el error es de otra clase. */
export const errorConLista = (error) => {
    const datos = error?.response?.data;
```

por:

```js
    return mensajeError(error);
};

/**
 * Los errores de un 422 al guardar un requisito de contratación, repartidos: `etapa`, para mostrarlos
 * bajo su campo, y `otros`, con su `mensaje`, para el aviso. Los de la etapa llegan en
 * `errors.etapa_id`, tanto de la validación de Laravel ({ message, errors }) como de una regla de
 * negocio ({ mensaje, errores, errors }), que además los repite en su lista `errores`: ahí se
 * quitan, para no avisarlos dos veces. null si no es 422.
 */
export const erroresDeEtapa = (error) => {
    if (error?.response?.status !== 422) return null;
    const datos = error.response.data ?? {};
    const { etapa_id: deEtapa = [], ...resto } = datos.errors && typeof datos.errors === 'object' ? datos.errors : {};
    const etapa = [].concat(deEtapa ?? []).map(String);
    if (Array.isArray(datos.errores) || datos.mensaje) {
        const lista = Array.isArray(datos.errores) ? datos.errores.map(String) : [];
        return { etapa, mensaje: datos.mensaje ?? 'El requisito no es válido.', otros: lista.filter((e) => !etapa.includes(e)) };
    }
    return { etapa, mensaje: 'El requisito no es válido.', otros: Object.values(resto).flat().map(String) };
};

/** El mensaje y la lista de un 422 de reglas de negocio ({ mensaje, errores }); null si el error es de otra clase. */
export const errorConLista = (error) => {
    const datos = error?.response?.data;
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/contratacion.test.js resources/js/utils/plantillasChecklist.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 848 passed.

Run: `npm test`
Expected: 461 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/css/Plantillas.css resources/js/components/PlantillaRequisitos.jsx resources/js/components/PlantillaVistaPrevia.jsx resources/js/utils/contratacion.js resources/js/utils/contratacion.test.js resources/js/utils/plantillasChecklist.js resources/js/utils/plantillasChecklist.test.js
git commit -m "feat: contracting template requirements grouped by stage"
```

---

### Tarea 7: Compilar para producción

- [ ] **Paso 1: Compilar**

Run: `npm run production`
Expected: `Compiled Successfully`, sin errores.

Justo antes de comitear, comprobar que `public/js/app.js` es el de producción: minificado y sin comentarios `/***/` al inicio. Si `npm run watch` lo reescribió, compilar otra vez.

- [ ] **Paso 2: Commit**

```bash
git add public/js/app.js public/js/app.js.map public/mix-manifest.json
git commit -m "build: production bundle for procurement stage 2"
```

Lo que no cambió se omite.

---

### Tarea 8: Verificación en la copia descartable

Usa la copia y la foto de la tarea 1. Nunca `gest_pro`.

- [ ] **Paso 1: Migrar la copia y comparar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

Expected: se aplican `2026_11_17_100015_add_uso_y_modalidades_a_plantillas` y `2026_11_17_100016_add_etapa_a_requisitos`.

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion2.php" "$TMP_DIR/c2_despues.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_contratacion2.php" "$TMP_DIR/c2_antes.json" "$TMP_DIR/c2_despues.json"
```

Expected: `SIN DIFERENCIAS`. Cualquier diferencia detiene la etapa.

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT uso, COUNT(*) FROM plantillas_checklist GROUP BY uso; SHOW COLUMNS FROM requisitos_catalogo LIKE 'etapa_id'; SHOW COLUMNS FROM proyectos_requisitos LIKE 'etapa_propia_id'; SHOW TABLES LIKE 'plantilla_modalidades';"
```

Expected: todas las plantillas existentes con uso `viabilizacion`, las dos columnas nulables y la tabla `plantilla_modalidades`.

- [ ] **Paso 2: Revertir, comparar y volver a migrar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=2 --force
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion2.php" "$TMP_DIR/c2_rollback.json" gestpro_verif_tmp --solo-tablas
```

```bash
php "$TMP_DIR/gestpro_comparar_contratacion2.php" "$TMP_DIR/c2_tablas_antes.json" "$TMP_DIR/c2_rollback.json"
```

Expected: `SIN DIFERENCIAS`. Con las migraciones revertidas, el código nuevo no puede pasar por el servicio (le falta la columna `uso`), por eso se compara solo con tablas.

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion2.php" "$TMP_DIR/c2_remigrado.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_contratacion2.php" "$TMP_DIR/c2_antes.json" "$TMP_DIR/c2_remigrado.json"
```

Expected: `SIN DIFERENCIAS`.

- [ ] **Paso 3: Sembrar la plantilla en la copia**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan plantillas:sembrar-contratacion --simular
```

Expected: la lista de los 25 requisitos por etapa, y que no se escribió nada.

```bash
DB_DATABASE=gestpro_verif_tmp php artisan plantillas:sembrar-contratacion
```

Expected: crea "Licitación pública – SECOP II" en borrador. Una segunda ejecución dice que ya existe y no crea nada.

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion2.php" "$TMP_DIR/c2_sembrado.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_contratacion2.php" "$TMP_DIR/c2_antes.json" "$TMP_DIR/c2_sembrado.json"
```

Expected: `SIN DIFERENCIAS`: la plantilla nueva es un agregado y ningún expediente cambia.

- [ ] **Paso 4: Probar en el navegador sobre la copia**

Compilar en desarrollo (`npx mix`) si `public/` no tiene el código de esta etapa. Arrancar el servidor desde `public/`, en un puerto libre: `artisan serve` recargaría `gest_pro` desde `.env`, y en el 8765 escucha otra aplicación. Comprobar con `netstat -ano | grep 8796` que nadie más escucha en ese puerto.

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8796 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

En Windows, ese servidor a veces corta `app.js` y la página queda en blanco: basta recargar.

Crear un Administrador de prueba solo en la copia; nunca usar sesiones de usuarios reales de `gest_pro`:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'verif@gestpro.test', 'rol' => App\Enums\Rol::Administrador]);"
```

La contraseña es la predeterminada de `UserFactory`. Entrar por `http://127.0.0.1:8796/GestPro/` y abrir Plantillas de checklist:
1. La pestaña "Viabilización" muestra las plantillas de siempre, y "Contratación" muestra la sembrada, con "Licitación pública" como modalidad. La URL recuerda la pestaña.
2. En la sembrada:
   - General muestra el uso y las modalidades;
   - Requisitos aparecen agrupados por etapa en el orden del catálogo;
   - la vista previa también se agrupa por etapa.
3. **Nueva plantilla de contratación:**
   - crear una, con una modalidad;
   - agregar un requisito, que exige etapa;
   - publicarla: sin modalidades o con un requisito sin etapa, el error lo dice.
4. **Nueva plantilla de viabilización:** se crea sin modalidades, y su requisito no pide etapa.
5. Un expediente de viabilización (`/viabilizacion/…`) no ofrece ninguna plantilla de contratación, ni siquiera publicada.

La consola del navegador no muestra errores nuevos.

- [ ] **Paso 5: Limpiar**

Detener el servidor, eliminar la copia y devolver `public/` a lo comiteado si se compiló en desarrollo:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 6: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas, 848.

Run: `npm test`
Expected: pasan todas, 461.

---

### Tarea 9: Informar y pedir validación

- [ ] **Paso 1: Actualizar el estado del diseño**

En `docs/superpowers/specs/2026-11-17-contratacion-revision-design.md`, reemplazar:

```markdown
Etapa 1 (catálogos y procesos) validada y aplicada en `gest_pro`.
```

por:

```markdown
Etapa 1 (catálogos y procesos) validada y aplicada en `gest_pro`. Etapa 2 (plantillas de contratación) implementada y verificada en copia; pendiente de validar y aplicar en `gest_pro`.
```

```bash
git add docs/superpowers/specs/2026-11-17-contratacion-revision-design.md
git commit -m "docs: mark procurement stage 2 as implemented"
```

- [ ] **Paso 2: Informar**

Informar:
- los totales de pruebas;
- las comparaciones (migrar, revertir, volver a migrar y sembrar);
- lo revisado en el navegador.

Pedir **tres** decisiones por separado:
1. si valida la etapa;
2. si autoriza aplicar las migraciones 16 y 17 en `gest_pro`;
3. si autoriza ejecutar la siembra de la plantilla de licitación pública en `gest_pro`.

---

### Tarea 10: Aplicar en `gest_pro` (solo con autorización)

No ejecutar esta tarea sin un sí explícito del usuario a este paso. Si la comparación muestra diferencias, restaurar el respaldo: no usar `migrate:rollback`.

- [ ] **Paso 1: Respaldar**

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro > "$TMP_DIR/gest_pro_antes_contratacion_2.sql"
```

Expected: el archivo existe y termina en `-- Dump completed`.

- [ ] **Paso 2: Tomar la foto antes de migrar**

Con el código de esta etapa, el servicio ya espera las columnas nuevas, así que antes de migrar solo se toman las tablas:

```bash
php "$TMP_DIR/gestpro_foto_contratacion2.php" "$TMP_DIR/c2_real_antes.json" gest_pro --solo-tablas
```

- [ ] **Paso 3: Migrar hacia adelante**

Run: `php artisan migrate --force`
Expected: se aplican las dos migraciones `2026_11_17_10001[56]_*`.

- [ ] **Paso 4: Comparar**

```bash
php "$TMP_DIR/gestpro_foto_contratacion2.php" "$TMP_DIR/c2_real_despues.json" gest_pro
```

```bash
php "$TMP_DIR/gestpro_comparar_contratacion2.php" "$TMP_DIR/c2_real_antes.json" "$TMP_DIR/c2_real_despues.json"
```

Expected: `SIN DIFERENCIAS`.

- [ ] **Paso 5: Sembrar (solo si el usuario lo autorizó aparte)**

```bash
php artisan plantillas:sembrar-contratacion
```

Expected: crea la plantilla en borrador. Comparar de nuevo con la foto de antes: `SIN DIFERENCIAS`.

- [ ] **Paso 6: Informar**

Dar el resultado y la ruta completa del respaldo, y avisar que está en una carpeta temporal.

---

## Criterio de la etapa

La etapa está lista cuando, según el diseño:

- un administrador crea, asocia a modalidades y publica una plantilla de contratación con requisitos por etapa;
- el comando siembra el borrador de licitación pública sin publicarlo;
- ninguna plantilla de contratación se ofrece a un expediente de viabilización;
- ningún expediente cambia al migrar, al revertir ni al sembrar.
