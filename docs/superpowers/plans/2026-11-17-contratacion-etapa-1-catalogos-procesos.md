# Contratación, etapa 1: catálogos y procesos. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** que un proyecto registre sus procesos de contratación (modalidad, plataforma, número, enlace, entidad, valor y estado), con los catálogos de modalidades y etapas de contratación administrables. Es la base de las etapas siguientes: plantillas, expediente, revisión por etapa y tablero.

**Arquitectura:**
- **Migraciones 13 a 15:**
  - siembran las modalidades en la tabla existente `modalidades`, con clave y orden;
  - crean el catálogo `etapas_contratacion` con las 11 etapas del diseño;
  - amplían la tabla existente `procesos_licitacion`.
- **API:** `ModalidadController` y `EtapaContratacionController` (escritura solo Administrador), y `ProcesoContratacionController` con el servicio `App\Services\Contratacion\ProcesosContratacion` (escritura Administrador o Gestor), cuyas rutas viven en `routes/contratacion.php`.
- **Historial:** los cambios de un proceso quedan en `checklist_historial` con `entidad_tipo = 'proceso'`.
- **Front:** dos secciones nuevas en Parámetros y un botón "Procesos" por proyecto. Son componentes propios, porque `Parametros.jsx` ya tiene 3300 líneas.

**Tecnologías:** Laravel 12, PHP 8.2, MariaDB 10.4, React 18 con Laravel Mix, PHPUnit sobre SQLite en memoria, Vitest.

**Diseño:** `docs/superpowers/specs/2026-11-17-contratacion-revision-design.md`:
- sección 3: "Proceso de contratación" y "Etapas";
- sección 6: migraciones 13 a 15;
- sección 7: interfaces;
- sección 9: etapa 1.

**Estado de partida:** rama `feature/contratacion` (desde `main` en `501367c`), con el diseño comiteado (`115a729`). Pasan 672 pruebas PHP y 370 JS.

---

## Reglas de ejecución

- **Nada de esto se aplica a `gest_pro` sin autorización del usuario.** Nunca se usa `migrate:rollback` allí: si hay que volver atrás, se restaura el respaldo. La tarea 11 aplica las migraciones, y solo con un sí explícito.
- Commits autorizados solo en `feature/contratacion`. Sin push. Mensajes en inglés, terminados con la línea `Co-Authored-By` del modelo que comitea.
- Nomenclatura en español sin tildes para tablas, columnas, clases y métodos nuevos. Textos para el usuario en español con tuteo. Los mensajes de las migraciones conservan el registro de usted de la serie.
- Pruebas: `php artisan test` sin definir `DB_DATABASE` (usa SQLite en memoria) y `npm test`. Si Vitest crea `resources/js/node_modules`, se borra.
- **Editar con la herramienta Edit, no con `sed`.** Cada reemplazo de este plan aparece una sola vez en su archivo. Si no lo encuentras tal cual, detente y avisa. Si Edit falla solo por finales de línea CRLF, adapta solo eso.
- **No usar `git stash`:** el usuario tiene stashes propios.
- El usuario suele tener `npm run watch` corriendo, que reescribe `public/`. Solo la tarea 8 comitea `public/`, después de `npm run production`.
- No tocar ni comitear `public/images/marca-completa.png` ni `public/images/marca-completa1.png`: son cambios del usuario.
- `.env` está versionado: nunca se agrega a un commit. Comitear siempre con rutas explícitas.
- `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio. Hay que definirlo en cada comando, porque el estado de la terminal no se conserva.
- **SQLite:** agregar columnas con clave foránea reconstruye la tabla y, dentro de `RefreshDatabase`, borra en cascada las filas hijas. Las pruebas de las migraciones no crean filas hijas de `procesos_licitacion`.

## Decisiones de este plan

**Permisos**
- Modalidades y etapas son catálogos: solo el Administrador escribe (`can:gestionar-catalogos`), como fuentes y sectores.
- Los procesos son datos de negocio: los escriben el Administrador y el Gestor (`can:editar-datos`).
- Todos los usuarios autenticados leen.
- La pantalla oculta las acciones que el rol no puede hacer.

**Modalidades (migración 13)**
- Agrega `clave` (única y opcional) y `orden`, y siembra seis modalidades: licitación pública, selección abreviada, concurso de méritos, contratación directa, mínima cuantía y régimen especial.
- Si ya existe una modalidad con el mismo nombre, sin contar tildes ni mayúsculas, recibe la clave en vez de duplicarse.
- `down()` quita las columnas y conserva las filas. Límite aceptado: si una modalidad sembrada se renombró, al volver a subir no se reconoce, queda sin clave y se inserta otra con el nombre original.
- Se retira la ruta `/eliminarModalidad`, que apuntaba a un método inexistente: los catálogos se desactivan, no se borran.

**Etapas de contratación (migración 14)**
- Catálogo con las 11 etapas del diseño, en orden.
- Al crear una etapa, la clave sale del nombre (minúsculas, sin tildes, con guion bajo) y no cambia después.
- El orden se cambia enviando la lista completa de etapas.
- `down()` se detiene si hay etapas nuevas o si una sembrada cambió, porque se perdería lo editado.

**Procesos (migración 15)**
- Agrega `objeto`, `plataforma`, `url_proceso`, `estado` (por defecto `preparacion`), `usuario_creo_id` y timestamps.
- `down()` se detiene si algún proceso tiene datos en esas columnas.
- **Campos:**
  - obligatorios: objeto, modalidad y plataforma;
  - opcionales: número, enlace (http o https), entidad contratante y valor (hasta 13 cifras enteras y 2 decimales);
  - se pueden elegir modalidades y entidades activas, y al editar se acepta la inactiva que el proceso ya tiene.
- **Estado:** solo cambia por su propia ruta, con observación opcional, y nunca se bloquea. La pantalla pide confirmación si el cambio sale de un estado de cierre o retrocede en el flujo. Volver al mismo estado se rechaza.
- **Historial:** `proceso_creado`, `proceso_editado` (solo lo que cambió), `proceso_estado_cambiado` y `proceso_eliminado`.
- **Eliminar:** un proceso con contratos vinculados no se elimina. Los eventos de `eventos_licitacion`, que hoy nadie usa, se borran en cascada, como ya define su tabla.
- `tipo_proceso`, `tipo_proponente` y `entidad_proponente` no se usan en esta etapa.

## Fuera de esta etapa

- Plantillas de contratación, expedientes, revisión por etapa, cronograma, tablero y "Registrar contrato" (etapas 2 a 5 del diseño).
- La consulta del historial de un proceso en pantalla: llega con la revisión en la etapa 4.
- Las rutas de tipos de proceso, que siguen apuntando a métodos inexistentes.

## Lo que ya se ensayó

El plan se ensayó completo en un clon descartable fuera del repositorio, y este documento se generó con el código de ese ensayo: un script comprobó que aplicar sus bloques sobre la rama reproduce el ensayo exactamente.

- **Pruebas:** 797 PHP y 430 JS.
- **En una copia de `gest_pro` (MariaDB)**, los 10 expedientes y sus 293 filas no cambiaron al migrar, al revertir las tres migraciones ni al volver a migrar. Al revertir se conservaron las seis modalidades, y al volver a migrar recibieron su clave sin duplicarse.
- **En el navegador, sobre esa copia:**
  - las once etapas se reordenaron con las flechas y el orden quedó guardado;
  - se creó un proceso de licitación pública en SECOP II: un enlace sin `http` mostró su error bajo el campo, y el valor `1.234.567,50` se guardó como 1234567.50;
  - pasar a Publicado no pidió confirmación, y volver a "En preparación" sí ("El proceso vuelve atrás"); los tres eventos quedaron en el historial;
  - como Consulta, Modalidades y los procesos se vieron sin acciones, con el texto de quién puede modificarlos;
  - la consola no mostró errores.
- **Una revisión independiente** del ensayo, con sus hallazgos corregidos en las tareas correspondientes.

---

### Tarea 1: Punto de partida y foto con el código actual

La foto se toma **antes de cambiar código**. La copia `gestpro_verif_tmp` se conserva hasta la tarea 9.

- [ ] **Paso 1: Comprobar la rama, el estado y las pruebas**

Run: `git branch --show-current`
Expected: `feature/contratacion`

Run: `git status --short`
Expected: como mucho, `public/` (si corre `npm run watch`) y las dos imágenes del usuario.

Run: `php artisan test`
Expected: pasan todas, 672. Si alguna falla, detenerse y avisar.

Run: `npm test`
Expected: pasan todas, 370.

- [ ] **Paso 2: Crear la copia descartable**

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE IF EXISTS gestpro_verif_tmp; CREATE DATABASE gestpro_verif_tmp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro | /c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp
```

Si MySQL no responde (`ERROR 2002`), está apagado. Hay que pedirle al usuario que lo arranque desde XAMPP o arrancarlo con `/c/xampp/mysql/bin/mysqld --defaults-file=/c/xampp/mysql/bin/my.ini --standalone` en segundo plano.

- [ ] **Paso 3: Crear los dos scripts de comprobación**

Crear `$TMP_DIR/gestpro_foto_contratacion.php`. Toma los expedientes (que esta etapa no debe tocar) y lo que amplían las migraciones 13 a 15. Se detiene si la base no es la esperada:

```php
<?php
// Foto para comparar antes y después de migrar: los expedientes (que esta etapa no debe tocar)
// y lo que las migraciones 13 a 15 amplían (modalidades, procesos y su vínculo con contratos).
// Uso, desde la raíz del repositorio:
//   php gestpro_foto_contratacion.php <archivo.json> <base esperada> [--solo-tablas]
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
    . ', modalidades: ' . count($foto['tablas']['modalidades']) . ', procesos: ' . count($foto['tablas']['procesos'])
    . (isset($foto['api']) ? ', con API' : ', solo tablas') . "\n";
```

Crear `$TMP_DIR/gestpro_comparar_contratacion.php`. Lo nuevo se permite; un valor distinto o algo que falta, no:

```php
<?php
// Comprueba que todo lo que había antes sigue igual después. Lo nuevo (campos que la etapa
// agrega) se permite; un valor distinto o algo que falta, no.
// Uso: php gestpro_comparar_contratacion.php <antes.json> <despues.json>

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
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion.php" "$TMP_DIR/c1_antes.json" gestpro_verif_tmp
```

Expected: `Expedientes: 10, filas: 293, modalidades: 0, procesos: 0, con API`, o las cifras actuales de `gest_pro`.

---

### Tarea 2: Migraciones 13 a 15: modalidades, etapas y procesos

Siembra las modalidades en la tabla existente, crea el catálogo de etapas de contratación y amplía `procesos_licitacion`.

**Files:**
- Create: `app/Models/EtapaContratacion.php`
- Create: `database/migrations/2026_11_17_100012_siembra_modalidades.php`
- Create: `database/migrations/2026_11_17_100013_create_etapas_contratacion_table.php`
- Create: `database/migrations/2026_11_17_100014_amplia_procesos_licitacion.php`
- Test (create): `tests/Feature/Schema/ContratacionCatalogosTest.php`
- Modify: `app/Models/Modalidad.php`
- Modify: `app/Models/ProcesoLicitacion.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Schema/ContratacionCatalogosTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\EtapaContratacion;
use App\Models\Modalidad;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Migraciones 13 a 15: catálogos de modalidades y etapas de contratación, y columnas nuevas de
 * procesos_licitacion. Las pruebas de la 15 no crean contratos ni eventos a propósito: en SQLite,
 * quitar la clave foránea reconstruye procesos_licitacion y, dentro de la transacción de
 * RefreshDatabase, eso afectaría en cascada a las filas hijas. En MariaDB no pasa.
 */
class ContratacionCatalogosTest extends TestCase
{
    use RefreshDatabase;

    private const MODALIDADES = [
        'licitacion_publica' => 'Licitación pública',
        'seleccion_abreviada' => 'Selección abreviada',
        'concurso_meritos' => 'Concurso de méritos',
        'contratacion_directa' => 'Contratación directa',
        'minima_cuantia' => 'Mínima cuantía',
        'regimen_especial' => 'Régimen especial',
    ];

    private const ETAPAS = [
        'planeacion' => 'Planeación y estudios previos',
        'pliego_borrador' => 'Creación del proceso y pliego borrador',
        'publicacion_borrador' => 'Publicación del proyecto de pliego y aviso de convocatoria',
        'observaciones_borrador' => 'Observaciones al proyecto de pliego',
        'pliego_definitivo' => 'Apertura y pliego definitivo',
        'adendas' => 'Adendas',
        'cierre' => 'Cierre y apertura de ofertas',
        'evaluacion' => 'Evaluación y subsanaciones',
        'observaciones_informe' => 'Observaciones al informe de evaluación',
        'adjudicacion' => 'Adjudicación o declaratoria de desierto',
        'contrato' => 'Contrato y garantías',
    ];

    private const COLUMNAS_PROCESO = ['objeto', 'plataforma', 'url_proceso', 'estado', 'usuario_creo_id', 'created_at', 'updated_at'];

    private function migracionModalidades(): object
    {
        return require database_path('migrations/2026_11_17_100012_siembra_modalidades.php');
    }

    private function migracionEtapas(): object
    {
        return require database_path('migrations/2026_11_17_100013_create_etapas_contratacion_table.php');
    }

    private function migracionProcesos(): object
    {
        return require database_path('migrations/2026_11_17_100014_amplia_procesos_licitacion.php');
    }

    private function indiceUnico(string $tabla, string $columna): ?array
    {
        return collect(Schema::getIndexes($tabla))->first(fn ($i) => $i['unique'] && $i['columns'] === [$columna]);
    }

    private function foraneaUsuario(): ?array
    {
        return collect(Schema::getForeignKeys('procesos_licitacion'))->first(fn ($f) => $f['columns'] === ['usuario_creo_id']);
    }

    private function proceso(array $cambios = []): int
    {
        return DB::table('procesos_licitacion')->insertGetId([
            'proyecto' => DB::table('proyectos')->insertGetId(['nombre' => 'Acueducto']),
            'codigo_proceso' => 'LP-001',
            ...$cambios,
        ]);
    }

    // ------------------------------------------------------------- modalidades (13)

    private function asertarModalidadesMigradas(): void
    {
        $this->assertTrue(Schema::hasColumns('modalidades', ['clave', 'orden']));
        $this->assertTrue(collect(Schema::getColumns('modalidades'))->firstWhere('name', 'clave')['nullable']);
        $this->assertNotNull($this->indiceUnico('modalidades', 'clave'));
    }

    private function asertarModalidadesRevertidas(): void
    {
        $this->assertFalse(Schema::hasColumn('modalidades', 'clave'));
        $this->assertFalse(Schema::hasColumn('modalidades', 'orden'));
    }

    public function test_siembra_las_seis_modalidades_con_clave_y_en_orden(): void
    {
        $this->asertarModalidadesMigradas();

        $modalidades = Modalidad::orderBy('orden')->get();
        $this->assertSame(self::MODALIDADES, $modalidades->pluck('nombre', 'clave')->all());
        $this->assertSame([1, 2, 3, 4, 5, 6], $modalidades->pluck('orden')->all());
        $this->assertTrue($modalidades->every->activo);
    }

    public function test_la_clave_de_una_modalidad_es_opcional_y_no_se_repite(): void
    {
        $sinClave = Modalidad::create(['nombre' => 'Obra por administración']);
        $this->assertNull($sinClave->fresh()->clave);
        Modalidad::create(['nombre' => 'Convenio']);

        $this->expectException(QueryException::class);
        Modalidad::create(['nombre' => 'Repetida', 'clave' => 'minima_cuantia']);
    }

    public function test_una_modalidad_existente_con_el_mismo_nombre_recibe_la_clave_sin_duplicarse(): void
    {
        $migracion = $this->migracionModalidades();
        $migracion->down();
        DB::table('modalidades')->delete();

        $licitacion = DB::table('modalidades')->insertGetId(['nombre' => 'LICITACION PUBLICA', 'activo' => false]);
        $otra = DB::table('modalidades')->insertGetId(['nombre' => 'Obra por administración', 'activo' => true]);
        $minima = DB::table('modalidades')->insertGetId(['nombre' => '  mínima  CUANTÍA ', 'activo' => true]);

        $migracion->up();

        $this->assertSame(7, DB::table('modalidades')->count()); // 3 existentes + 4 sembradas nuevas
        $fila = DB::table('modalidades')->where('id', $licitacion)->first();
        $this->assertSame('licitacion_publica', $fila->clave);
        $this->assertSame('LICITACION PUBLICA', $fila->nombre);
        $this->assertSame(1, (int) $fila->orden);
        $this->assertFalse((bool) $fila->activo);
        $this->assertSame('minima_cuantia', DB::table('modalidades')->where('id', $minima)->value('clave'));
        $this->assertSame(5, (int) DB::table('modalidades')->where('id', $minima)->value('orden'));

        // La que no corresponde a ninguna queda sin clave, después de las sembradas.
        $this->assertNull(DB::table('modalidades')->where('id', $otra)->value('clave'));
        $this->assertSame(7, (int) DB::table('modalidades')->where('id', $otra)->value('orden'));
        $this->assertSame(6, DB::table('modalidades')->whereNotNull('clave')->count());
    }

    public function test_el_rollback_de_modalidades_quita_las_columnas_y_conserva_las_filas(): void
    {
        Modalidad::create(['nombre' => 'Convenio']);

        $this->migracionModalidades()->down();

        $this->asertarModalidadesRevertidas();
        $this->assertSame(7, DB::table('modalidades')->count());
    }

    public function test_volver_a_subir_modalidades_no_las_duplica(): void
    {
        $migracion = $this->migracionModalidades();

        $migracion->up(); // ya migrada: no falla ni duplica
        $this->assertSame(6, DB::table('modalidades')->count());

        $migracion->down();
        $migracion->down();
        $this->asertarModalidadesRevertidas();

        $migracion->up();
        $migracion->up();
        $this->asertarModalidadesMigradas();
        $this->assertSame(6, DB::table('modalidades')->count());
        $this->assertSame(self::MODALIDADES, Modalidad::orderBy('orden')->pluck('nombre', 'clave')->all());
    }

    public function test_reintentar_modalidades_tras_un_fallo_a_medias_completa_la_migracion(): void
    {
        $migracion = $this->migracionModalidades();
        $migracion->down();

        // Lo que dejaría un fallo entre la columna y su índice único.
        Schema::table('modalidades', function (Blueprint $table) {
            $table->string('clave', 40)->nullable();
        });

        $migracion->up();
        $this->asertarModalidadesMigradas();
        $this->assertSame(6, DB::table('modalidades')->count());

        // Lo que dejaría un fallo de la bajada: el índice ya quitado.
        Schema::table('modalidades', function (Blueprint $table) {
            $table->dropUnique(['clave']);
        });
        $migracion->down();
        $this->asertarModalidadesRevertidas();
    }

    // ------------------------------------------------------------- etapas (14)

    public function test_el_catalogo_de_etapas_tiene_sus_columnas(): void
    {
        $this->assertTrue(Schema::hasColumns('etapas_contratacion', [
            'id', 'clave', 'nombre', 'descripcion', 'orden', 'activo', 'created_at', 'updated_at',
        ]));
        $this->assertNotNull($this->indiceUnico('etapas_contratacion', 'clave'));
    }

    public function test_siembra_las_once_etapas_en_orden(): void
    {
        $etapas = EtapaContratacion::orderBy('orden')->get();

        $this->assertSame(self::ETAPAS, $etapas->pluck('nombre', 'clave')->all());
        $this->assertSame(range(1, 11), $etapas->pluck('orden')->all());
        $this->assertTrue($etapas->every->activo);
        $this->assertTrue($etapas->every(fn ($e) => filled($e->descripcion)));
    }

    public function test_la_clave_de_una_etapa_no_se_repite(): void
    {
        $this->expectException(QueryException::class);
        EtapaContratacion::create(['clave' => 'adendas', 'nombre' => 'Otra', 'orden' => 12]);
    }

    public function test_el_rollback_de_etapas_borra_el_catalogo_sin_cambios(): void
    {
        $this->migracionEtapas()->down();

        $this->assertFalse(Schema::hasTable('etapas_contratacion'));
    }

    public function test_el_rollback_de_etapas_se_detiene_si_hay_una_etapa_nueva(): void
    {
        EtapaContratacion::create(['clave' => 'liquidacion', 'nombre' => 'Liquidación', 'orden' => 12]);

        try {
            $this->migracionEtapas()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Liquidación', $e->getMessage());
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }

        $this->assertSame(12, EtapaContratacion::count());
    }

    public function test_el_rollback_de_etapas_se_detiene_si_una_sembrada_fue_editada(): void
    {
        foreach ([
            'nombre' => 'Adendas al pliego',
            'descripcion' => 'Otra descripción',
            'orden' => 20,
            'activo' => false,
        ] as $campo => $valor) {
            $etapa = EtapaContratacion::where('clave', 'adendas')->firstOrFail();
            $original = $etapa->getAttributes();
            $etapa->update([$campo => $valor]);

            try {
                $this->migracionEtapas()->down();
                $this->fail("El rollback debía detenerse con {$campo} editado.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString("adendas: {$campo}", $e->getMessage());
                $this->assertStringContainsStringIgnoringCase('restaure un respaldo.', $e->getMessage());
            }

            $this->assertTrue(Schema::hasTable('etapas_contratacion'));
            DB::table('etapas_contratacion')->where('id', $etapa->id)->update($original);
        }

        $this->migracionEtapas()->down();
        $this->assertFalse(Schema::hasTable('etapas_contratacion'));
    }

    public function test_volver_a_subir_etapas_no_las_duplica(): void
    {
        $migracion = $this->migracionEtapas();

        $migracion->up(); // ya migrada: no falla ni duplica
        $this->assertSame(11, EtapaContratacion::count());

        $migracion->down();
        $migracion->down();
        $this->assertFalse(Schema::hasTable('etapas_contratacion'));

        $migracion->up();
        $migracion->up();
        $this->assertSame(self::ETAPAS, EtapaContratacion::orderBy('orden')->pluck('nombre', 'clave')->all());
    }

    public function test_reintentar_etapas_tras_un_fallo_a_medias_completa_la_migracion(): void
    {
        $migracion = $this->migracionEtapas();
        $migracion->down();

        // Lo que dejaría un fallo entre el CREATE y el índice único.
        Schema::create('etapas_contratacion', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40);
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $migracion->up();

        $this->assertNotNull($this->indiceUnico('etapas_contratacion', 'clave'));
        $this->assertSame(self::ETAPAS, EtapaContratacion::orderBy('orden')->pluck('nombre', 'clave')->all());
    }

    // ------------------------------------------------------------- procesos (15)

    private function asertarProcesosMigrados(): void
    {
        $this->assertTrue(Schema::hasColumns('procesos_licitacion', self::COLUMNAS_PROCESO));
        $foranea = $this->foraneaUsuario();
        $this->assertNotNull($foranea);
        $this->assertSame('users', $foranea['foreign_table']);
        $this->assertSame('set null', strtolower($foranea['on_delete']));
    }

    private function asertarProcesosRevertidos(): void
    {
        foreach (self::COLUMNAS_PROCESO as $columna) {
            $this->assertFalse(Schema::hasColumn('procesos_licitacion', $columna), $columna);
        }
        $this->assertNull($this->foraneaUsuario());
    }

    public function test_los_procesos_tienen_las_columnas_nuevas_y_el_estado_por_defecto(): void
    {
        $this->asertarProcesosMigrados();

        $fila = DB::table('procesos_licitacion')->where('id', $this->proceso())->first();
        $this->assertSame(ProcesoLicitacion::PREPARACION, $fila->estado);
        $this->assertNull($fila->objeto);
        $this->assertNull($fila->plataforma);
    }

    public function test_el_modelo_de_proceso_registra_fechas_y_relaciones(): void
    {
        $usuario = User::factory()->create();
        $proyecto = Proyecto::create(['nombre' => 'Acueducto']);
        $modalidad = Modalidad::where('clave', 'licitacion_publica')->firstOrFail();

        $proceso = ProcesoLicitacion::create([
            'proyecto' => $proyecto->id,
            'objeto' => 'Construcción del acueducto',
            'modalidad' => $modalidad->id,
            'plataforma' => ProcesoLicitacion::SECOP_II,
            'url_proceso' => 'https://community.secop.gov.co/proceso/1',
            'usuario_creo_id' => $usuario->id,
        ]);

        $proceso = $proceso->fresh();
        $this->assertSame(ProcesoLicitacion::PREPARACION, $proceso->estado);
        $this->assertNotNull($proceso->created_at);
        $this->assertNotNull($proceso->updated_at);
        $this->assertTrue($proceso->modalidadRel->is($modalidad));
        $this->assertTrue($proceso->proyectoRel->is($proyecto));
        $this->assertTrue($proceso->usuarioCreo->is($usuario));
        $this->assertSame(['secop_ii', 'secop_i', 'tvec', 'ninguna'], ProcesoLicitacion::PLATAFORMAS);
        $this->assertSame(
            ['preparacion', 'publicado', 'evaluacion', 'adjudicado', 'desierto', 'celebrado', 'cancelado'],
            ProcesoLicitacion::ESTADOS
        );
    }

    public function test_borrar_el_usuario_que_creo_el_proceso_lo_deja_en_null(): void
    {
        $usuario = User::factory()->create();
        $id = $this->proceso(['usuario_creo_id' => $usuario->id]);

        $usuario->delete();

        $this->assertNull(DB::table('procesos_licitacion')->where('id', $id)->value('usuario_creo_id'));
    }

    public function test_el_rollback_de_procesos_se_detiene_si_alguno_tiene_datos_nuevos(): void
    {
        foreach ([
            'objeto' => 'Obra',
            'plataforma' => ProcesoLicitacion::TVEC,
            'url_proceso' => 'https://example.com',
            'estado' => ProcesoLicitacion::PUBLICADO,
            'usuario_creo_id' => User::factory()->create()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ] as $columna => $valor) {
            DB::table('procesos_licitacion')->delete();
            $this->proceso([$columna => $valor]);

            try {
                $this->migracionProcesos()->down();
                $this->fail("El rollback debía detenerse con {$columna}.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('procesos', $e->getMessage());
                $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
            }

            $this->asertarProcesosMigrados();
        }
    }

    public function test_el_rollback_de_procesos_sin_datos_nuevos_conserva_los_procesos(): void
    {
        $id = $this->proceso();

        $this->migracionProcesos()->down();

        $this->asertarProcesosRevertidos();
        $this->assertSame('LP-001', DB::table('procesos_licitacion')->where('id', $id)->value('codigo_proceso'));
    }

    public function test_procesos_en_ambos_sentidos_se_pueden_repetir(): void
    {
        $migracion = $this->migracionProcesos();

        $migracion->up(); // ya migrada: no falla
        $this->asertarProcesosMigrados();

        $migracion->down();
        $migracion->down();
        $this->asertarProcesosRevertidos();

        $migracion->up();
        $migracion->up();
        $this->asertarProcesosMigrados();
    }

    public function test_reintentar_procesos_tras_un_fallo_a_medias_completa_la_migracion(): void
    {
        $migracion = $this->migracionProcesos();
        $migracion->down();

        // Lo que dejaría un fallo de la subida: algunas columnas, y la del usuario sin clave foránea.
        Schema::table('procesos_licitacion', function (Blueprint $table) {
            $table->text('objeto')->nullable();
        });
        Schema::table('procesos_licitacion', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_creo_id')->nullable();
        });

        $migracion->up();
        $this->asertarProcesosMigrados();

        // Lo que dejaría un fallo de la bajada: la clave foránea ya quitada.
        Schema::table('procesos_licitacion', function (Blueprint $table) {
            $table->dropForeign(['usuario_creo_id']);
        });
        $migracion->down();
        $this->asertarProcesosRevertidos();
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Schema/ContratacionCatalogosTest.php`
Expected: FALLAN las 21: no existen la columna `clave` de modalidades, la tabla `etapas_contratacion` ni las columnas nuevas de `procesos_licitacion`.

- [ ] **Paso 3: Implementar**

Crear `app/Models/EtapaContratacion.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Etapa del catálogo de contratación. La clave no se edita; el orden lo fija el administrador. */
class EtapaContratacion extends Model
{
    protected $table = 'etapas_contratacion';
    protected $fillable = ['clave', 'nombre', 'descripcion', 'orden', 'activo'];
    protected $casts = ['activo' => 'boolean', 'orden' => 'integer'];
}
```

Crear `database/migrations/2026_11_17_100012_siembra_modalidades.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * Modalidades de contratación: el catálogo existía vacío. Se agrega `clave`, que identifica las
 * modalidades que el sistema necesita reconocer (las plantillas de contratación se asociarán a
 * ellas), y `orden`, y se siembran las seis modalidades de la propuesta.
 */
return new class extends Migration
{
    private const MODALIDADES = [
        ['licitacion_publica', 'Licitación pública'],
        ['seleccion_abreviada', 'Selección abreviada'],
        ['concurso_meritos', 'Concurso de méritos'],
        ['contratacion_directa', 'Contratación directa'],
        ['minima_cuantia', 'Mínima cuantía'],
        ['regimen_especial', 'Régimen especial'],
    ];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. Primero la columna, después su índice único.
        if (! Schema::hasColumn('modalidades', 'clave')) {
            Schema::table('modalidades', function (Blueprint $table) {
                $table->string('clave', 40)->nullable();
            });
        }
        if (! $this->indiceClave()) {
            Schema::table('modalidades', function (Blueprint $table) {
                $table->unique('clave');
            });
        }
        if (! Schema::hasColumn('modalidades', 'orden')) {
            Schema::table('modalidades', function (Blueprint $table) {
                $table->unsignedInteger('orden')->default(0);
            });
        }

        DB::transaction(function () {
            // Una modalidad que ya existe con el mismo nombre (sin contar tildes, mayúsculas ni
            // espacios) recibe la clave en vez de duplicarse; conserva su nombre y su estado.
            $sinClave = DB::table('modalidades')->whereNull('clave')->orderBy('id')->get(['id', 'nombre']);

            foreach (self::MODALIDADES as $indice => [$clave, $nombre]) {
                if (DB::table('modalidades')->where('clave', $clave)->exists()) {
                    continue;
                }

                $igual = $sinClave->first(fn (object $m) => $this->normalizar($m->nombre) === $this->normalizar($nombre));
                if ($igual) {
                    DB::table('modalidades')->where('id', $igual->id)->update(['clave' => $clave, 'orden' => $indice + 1]);
                    $sinClave = $sinClave->reject(fn (object $m) => $m->id === $igual->id);
                } else {
                    DB::table('modalidades')->insert(['clave' => $clave, 'nombre' => $nombre, 'orden' => $indice + 1, 'activo' => true]);
                }
            }

            // Las demás quedan después de las sembradas, en el orden en que se crearon.
            $siguiente = (int) DB::table('modalidades')->max('orden');
            foreach (DB::table('modalidades')->whereNull('clave')->where('orden', 0)->orderBy('id')->pluck('id') as $id) {
                DB::table('modalidades')->where('id', $id)->update(['orden' => ++$siguiente]);
            }
        });
    }

    public function down(): void
    {
        // Es un catálogo: las filas se conservan, también las sembradas. Al volver a subir, cada
        // una recupera su clave por el nombre. Límite conocido y aceptado: una sembrada que se
        // renombró no se reconoce, queda sin clave y la subida inserta otra con el nombre
        // original. Primero el índice, después las columnas.
        if ($indice = $this->indiceClave()) {
            Schema::table('modalidades', function (Blueprint $table) use ($indice) {
                $table->dropUnique($indice['name']);
            });
        }
        foreach (['clave', 'orden'] as $columna) {
            if (Schema::hasColumn('modalidades', $columna)) {
                Schema::table('modalidades', function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }
    }

    private function indiceClave(): ?array
    {
        return collect(Schema::getIndexes('modalidades'))->first(fn ($i) => $i['unique'] && $i['columns'] === ['clave']);
    }

    private function normalizar(?string $texto): string
    {
        return preg_replace('/\s+/', ' ', Str::lower(Str::ascii(trim((string) $texto))));
    }
};
```

Crear `database/migrations/2026_11_17_100013_create_etapas_contratacion_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Catálogo administrable de etapas de un proceso de contratación. Se siembra con las etapas de la
 * guía CCE-SEC-G1-18 de Colombia Compra (SECOP II, licitación pública), más la planeación al
 * inicio y el contrato al final. `clave` no se edita: la usarán las plantillas y el código.
 */
return new class extends Migration
{
    private const ETAPAS = [
        ['planeacion', 'Planeación y estudios previos', 'Configuración financiera (CDP, BPIN y Plan Anual de Adquisiciones) y estudios previos.'],
        ['pliego_borrador', 'Creación del proceso y pliego borrador', 'Guía CCE-SEC-G1-18, capítulos 2 y 3.'],
        ['publicacion_borrador', 'Publicación del proyecto de pliego y aviso de convocatoria', 'Guía CCE-SEC-G1-18, capítulo 4.'],
        ['observaciones_borrador', 'Observaciones al proyecto de pliego', 'Guía CCE-SEC-G1-18, capítulo 6.'],
        ['pliego_definitivo', 'Apertura y pliego definitivo', 'Guía CCE-SEC-G1-18, capítulos 7 y 8.'],
        ['adendas', 'Adendas', 'Guía CCE-SEC-G1-18, capítulo 9.'],
        ['cierre', 'Cierre y apertura de ofertas', 'Guía CCE-SEC-G1-18, capítulo 10.'],
        ['evaluacion', 'Evaluación y subsanaciones', 'Guía CCE-SEC-G1-18, capítulo 11.'],
        ['observaciones_informe', 'Observaciones al informe de evaluación', 'Guía CCE-SEC-G1-18, capítulo 12.'],
        ['adjudicacion', 'Adjudicación o declaratoria de desierto', 'Guía CCE-SEC-G1-18, capítulos 13 y 14.'],
        ['contrato', 'Contrato y garantías', 'Contrato electrónico (guía CCE-SEC-G1-18, capítulo 14).'],
    ];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: la tabla y su índice único son pasos separados,
        // cada uno con su guarda, para poder reintentar sin perder lo que ya se sembró.
        if (! Schema::hasTable('etapas_contratacion')) {
            Schema::create('etapas_contratacion', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 40);
                $table->string('nombre', 120);
                $table->text('descripcion')->nullable();
                $table->unsignedInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }
        if (! $this->indiceClave()) {
            Schema::table('etapas_contratacion', function (Blueprint $table) {
                $table->unique('clave');
            });
        }

        DB::transaction(function () {
            foreach (self::ETAPAS as $indice => [$clave, $nombre, $descripcion]) {
                if (! DB::table('etapas_contratacion')->where('clave', $clave)->exists()) {
                    DB::table('etapas_contratacion')->insert([
                        'clave' => $clave,
                        'nombre' => $nombre,
                        'descripcion' => $descripcion,
                        'orden' => $indice + 1,
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('etapas_contratacion')) {
            return;
        }

        $sembradas = collect(self::ETAPAS)->mapWithKeys(fn ($e, $i) => [$e[0] => [
            'nombre' => $e[1], 'descripcion' => $e[2], 'orden' => $i + 1, 'activo' => true,
        ]]);
        $nuevas = [];
        $editadas = [];
        foreach (DB::table('etapas_contratacion')->orderBy('orden')->get() as $etapa) {
            $original = $sembradas->get($etapa->clave);
            if (! $original) {
                $nuevas[] = $etapa->nombre;
                continue;
            }

            $campos = array_keys(array_filter([
                'nombre' => $etapa->nombre !== $original['nombre'],
                'descripcion' => $etapa->descripcion !== $original['descripcion'],
                'orden' => (int) $etapa->orden !== $original['orden'],
                'activo' => (bool) $etapa->activo !== $original['activo'],
            ]));
            if ($campos) {
                $editadas[] = $etapa->clave . ': ' . implode(', ', $campos);
            }
        }

        if ($nuevas) {
            throw new RuntimeException('No se puede revertir: hay etapas de contratación creadas después de la migración (' . implode(', ', $nuevas) . '), que se perderían. Restaure un respaldo.');
        }
        if ($editadas) {
            throw new RuntimeException('No se puede revertir: hay etapas de contratación editadas (' . implode('; ', $editadas) . ') y esos cambios se perderían. Deshaga esos cambios desde Parámetros → Etapas de contratación o restaure un respaldo.');
        }

        Schema::dropIfExists('etapas_contratacion');
    }

    private function indiceClave(): ?array
    {
        return collect(Schema::getIndexes('etapas_contratacion'))->first(fn ($i) => $i['unique'] && $i['columns'] === ['clave']);
    }
};
```

Crear `database/migrations/2026_11_17_100014_amplia_procesos_licitacion.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Un proceso de contratación registra su objeto, la plataforma (secop_ii, secop_i, tvec o
 * ninguna), el enlace, su estado (preparacion, publicado, evaluacion, adjudicado, desierto,
 * celebrado o cancelado) y quién lo creó. Los valores válidos los controla la aplicación
 * (ProcesoLicitacion::PLATAFORMAS y ::ESTADOS), como en el resto de la serie.
 */
return new class extends Migration
{
    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. La clave foránea va después de su columna.
        $this->agregar('objeto', fn (Blueprint $t) => $t->text('objeto')->nullable());
        $this->agregar('plataforma', fn (Blueprint $t) => $t->string('plataforma', 20)->nullable());
        $this->agregar('url_proceso', fn (Blueprint $t) => $t->string('url_proceso', 500)->nullable());
        $this->agregar('estado', fn (Blueprint $t) => $t->string('estado', 20)->default('preparacion'));
        $this->agregar('usuario_creo_id', fn (Blueprint $t) => $t->unsignedBigInteger('usuario_creo_id')->nullable());
        if (! $this->foraneaUsuario()) {
            Schema::table('procesos_licitacion', function (Blueprint $table) {
                $table->foreign('usuario_creo_id')->references('id')->on('users')->nullOnDelete();
            });
        }
        $this->agregar('created_at', fn (Blueprint $t) => $t->timestamp('created_at')->nullable());
        $this->agregar('updated_at', fn (Blueprint $t) => $t->timestamp('updated_at')->nullable());
    }

    public function down(): void
    {
        $existentes = array_values(array_filter(
            ['objeto', 'plataforma', 'url_proceso', 'estado', 'usuario_creo_id', 'created_at', 'updated_at'],
            fn (string $c) => Schema::hasColumn('procesos_licitacion', $c)
        ));

        $conDatos = $existentes && DB::table('procesos_licitacion')
            ->where(function ($q) use ($existentes) {
                foreach ($existentes as $columna) {
                    $columna === 'estado'
                        ? $q->orWhere('estado', '!=', 'preparacion')
                        : $q->orWhereNotNull($columna);
                }
            })
            ->exists();
        if ($conDatos) {
            throw new RuntimeException('No se puede revertir: hay procesos de contratación con objeto, plataforma, enlace, estado, usuario que los creó o fechas registrados, que se perderían. Restaure un respaldo.');
        }

        // Primero la clave foránea, después las columnas; cada paso con su guarda. Se quita por
        // el nombre que entrega el esquema; SQLite no da nombre y ahí se deja la deducción de Laravel.
        if ($foranea = $this->foraneaUsuario()) {
            $nombre = $foranea['name'] ?? ['usuario_creo_id'];
            Schema::table('procesos_licitacion', function (Blueprint $table) use ($nombre) {
                $table->dropForeign($nombre);
            });
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

    private function foraneaUsuario(): ?array
    {
        return collect(Schema::getForeignKeys('procesos_licitacion'))->first(fn ($f) => $f['columns'] === ['usuario_creo_id']);
    }
};
```

En `app/Models/Modalidad.php`, reemplazar (1 de 1):

```php
    public $timestamps = false;

    protected $table = 'modalidades';
    protected $fillable = ['nombre', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function procesosLicitacion(): HasMany
    {
```

por:

```php
    public $timestamps = false;

    protected $table = 'modalidades';
    protected $fillable = ['clave', 'nombre', 'orden', 'activo'];
    protected $casts = ['activo' => 'boolean', 'orden' => 'integer'];

    public function procesosLicitacion(): HasMany
    {
```

En `app/Models/ProcesoLicitacion.php`, reemplazar (1 de 2):

```php

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
```

por:

```php

class ProcesoLicitacion extends Model
{
    public const SECOP_II = 'secop_ii';
    public const SECOP_I = 'secop_i';
    public const TVEC = 'tvec';
    public const NINGUNA = 'ninguna';
    public const PLATAFORMAS = [self::SECOP_II, self::SECOP_I, self::TVEC, self::NINGUNA];

    public const PREPARACION = 'preparacion';
    public const PUBLICADO = 'publicado';
    public const EVALUACION = 'evaluacion';
    public const ADJUDICADO = 'adjudicado';
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
    protected $casts = ['monto' => 'decimal:2', 'usuario_creo_id' => 'integer'];

    // proyectoRel()/modalidadRel(): both FK columns (proyecto, modalidad) are single words that
    // would collide with the natural relation name (see Task 4's note on Estado::faseRel()).
```

En `app/Models/ProcesoLicitacion.php`, reemplazar (2 de 2):

```php
        return $this->belongsTo(Entidad::class, 'entidad_contratante');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoLicitacion::class, 'proceso');
```

por:

```php
        return $this->belongsTo(Entidad::class, 'entidad_contratante');
    }

    public function usuarioCreo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_creo_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoLicitacion::class, 'proceso');
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Schema/ContratacionCatalogosTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 693 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Models/EtapaContratacion.php database/migrations/2026_11_17_100012_siembra_modalidades.php database/migrations/2026_11_17_100013_create_etapas_contratacion_table.php database/migrations/2026_11_17_100014_amplia_procesos_licitacion.php tests/Feature/Schema/ContratacionCatalogosTest.php app/Models/Modalidad.php app/Models/ProcesoLicitacion.php
git commit -m "feat: catalogs for procurement modalities and stages, wider processes (migrations 13-15)"
```

---

### Tarea 3: API de modalidades

`ModalidadController` reemplaza los métodos inexistentes de `ProyectoController`. Escritura solo Administrador.

**Files:**
- Create: `app/Http/Controllers/ModalidadController.php`
- Test (create): `tests/Feature/ModalidadesApiTest.php`
- Modify: `routes/api.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/ModalidadesApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Modalidad;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModalidadesApiTest extends TestCase
{
    use RefreshDatabase;

    private const GUARDAR = '/GestPro/guardarModalidad';
    private const ACTIVAR = '/GestPro/activarModalidad';

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function modalidad(string $clave): Modalidad
    {
        return Modalidad::where('clave', $clave)->firstOrFail();
    }

    public function test_cualquier_usuario_autenticado_lista_el_catalogo_en_orden(): void
    {
        $this->modalidad('regimen_especial')->update(['activo' => false]);

        $this->como(Rol::Consulta)->getJson('/GestPro/modalidades')
            ->assertOk()
            ->assertJsonCount(6)
            ->assertJsonPath('0.clave', 'licitacion_publica')
            ->assertJsonPath('0.nombre', 'Licitación pública')
            ->assertJsonPath('0.orden', 1)
            ->assertJsonPath('0.activo', true)
            ->assertJsonPath('5.clave', 'regimen_especial')
            ->assertJsonPath('5.activo', false);
    }

    public function test_a_igual_orden_lista_por_nombre(): void
    {
        Modalidad::create(['nombre' => 'Zeta', 'orden' => 7]);
        Modalidad::create(['nombre' => 'Alfa', 'orden' => 7]);

        $this->como(Rol::Consulta)->getJson('/GestPro/modalidades')
            ->assertOk()
            ->assertJsonPath('6.nombre', 'Alfa')
            ->assertJsonPath('7.nombre', 'Zeta');
    }

    public function test_el_administrador_agrega_una_modalidad_al_final_sin_clave(): void
    {
        $this->como(Rol::Administrador)->postJson(self::GUARDAR, [
            'accion' => 'Agregar',
            'nombre' => 'Convenio interadministrativo',
            'clave' => 'convenio',
            'orden' => 1,
        ])->assertOk()->assertJsonPath('success', 'Modalidad guardada correctamente');

        $modalidad = Modalidad::where('nombre', 'Convenio interadministrativo')->firstOrFail();
        $this->assertSame(7, $modalidad->orden);
        $this->assertTrue($modalidad->activo);
        $this->assertNull($modalidad->clave);
    }

    public function test_editar_cambia_el_nombre_y_conserva_clave_orden_y_estado(): void
    {
        $modalidad = $this->modalidad('minima_cuantia');
        $modalidad->update(['activo' => false]);

        $this->como(Rol::Administrador)->postJson(self::GUARDAR, [
            'accion' => 'Editar',
            'id' => $modalidad->id,
            'nombre' => 'Mínima cuantía (hasta 10 % de la menor cuantía)',
            'clave' => 'otra',
            'orden' => 1,
        ])->assertOk();

        $modalidad = $modalidad->fresh();
        $this->assertSame('Mínima cuantía (hasta 10 % de la menor cuantía)', $modalidad->nombre);
        $this->assertSame('minima_cuantia', $modalidad->clave);
        $this->assertSame(5, $modalidad->orden);
        $this->assertFalse($modalidad->activo);
    }

    public function test_rechaza_nombres_vacios_o_largos_con_mensajes_en_espanol(): void
    {
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => ''])
            ->assertStatus(422)->assertJsonPath('errors.nombre.0', 'Escribe el nombre de la modalidad.');
        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => str_repeat('a', 200)])
            ->assertStatus(422)->assertJsonPath('errors.nombre.0', 'El nombre admite hasta 199 caracteres.');
        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => str_repeat('a', 199)])->assertOk();
    }

    public function test_el_nombre_no_se_repite_sin_distinguir_mayusculas_ni_tildes(): void
    {
        $admin = $this->como(Rol::Administrador);

        foreach (['Licitación pública', 'LICITACIÓN PÚBLICA', 'licitacion publica'] as $nombre) {
            $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => $nombre])
                ->assertStatus(422)
                ->assertJsonPath('errors.nombre.0', 'Ya existe una modalidad con ese nombre.');
        }

        $this->assertDatabaseCount('modalidades', 6);
    }

    public function test_editar_sin_cambiar_el_nombre_o_solo_sus_mayusculas_no_choca_consigo_misma(): void
    {
        $modalidad = $this->modalidad('concurso_meritos');
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $modalidad->id, 'nombre' => $modalidad->nombre])->assertOk();
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $modalidad->id, 'nombre' => 'CONCURSO DE MÉRITOS'])->assertOk();

        $this->assertSame('CONCURSO DE MÉRITOS', $modalidad->fresh()->nombre);
    }

    public function test_no_se_puede_renombrar_con_el_nombre_de_otra_modalidad(): void
    {
        $modalidad = $this->modalidad('concurso_meritos');

        $this->como(Rol::Administrador)->postJson(self::GUARDAR, [
            'accion' => 'Editar', 'id' => $modalidad->id, 'nombre' => 'contratación DIRECTA',
        ])->assertStatus(422)->assertJsonValidationErrors('nombre');

        $this->assertSame('Concurso de méritos', $modalidad->fresh()->nombre);
    }

    public function test_activar_alterna_el_estado_segun_la_base_de_datos(): void
    {
        $modalidad = $this->modalidad('regimen_especial');
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::ACTIVAR, ['id' => $modalidad->id])
            ->assertOk()->assertJsonPath('success', 'Modalidad actualizada correctamente');
        $this->assertFalse($modalidad->fresh()->activo);

        $admin->postJson(self::ACTIVAR, ['id' => $modalidad->id])->assertOk();
        $this->assertTrue($modalidad->fresh()->activo);
    }

    public function test_solo_el_administrador_modifica_el_catalogo(): void
    {
        $modalidad = $this->modalidad('licitacion_publica');

        foreach ([Rol::Gestor, Rol::Consulta] as $rol) {
            $usuario = $this->como($rol);

            $usuario->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Nueva'])->assertForbidden();
            $usuario->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $modalidad->id, 'nombre' => 'Otra'])->assertForbidden();
            $usuario->postJson(self::ACTIVAR, ['id' => $modalidad->id])->assertForbidden();
        }

        $this->assertDatabaseCount('modalidades', 6);
        $this->assertSame('Licitación pública', $modalidad->fresh()->nombre);
        $this->assertTrue($modalidad->fresh()->activo);
    }

    public function test_rechaza_acciones_e_ids_invalidos(): void
    {
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['nombre' => 'Nueva'])->assertStatus(422)->assertJsonValidationErrors('accion');
        $admin->postJson(self::GUARDAR, ['accion' => 'Borrar', 'nombre' => 'Nueva'])->assertStatus(422);
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'nombre' => 'Nueva'])->assertStatus(422)->assertJsonValidationErrors('id');
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => [1], 'nombre' => 'Nueva'])->assertStatus(422);
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => 999999, 'nombre' => 'Nueva'])->assertNotFound();
        $admin->postJson(self::ACTIVAR, [])->assertStatus(422);
        $admin->postJson(self::ACTIVAR, ['id' => [1]])->assertStatus(422);
        $admin->postJson(self::ACTIVAR, ['id' => 999999])->assertNotFound();

        $this->assertDatabaseCount('modalidades', 6);
    }

    public function test_el_id_debe_ser_un_entero_de_verdad(): void
    {
        $primera = Modalidad::orderBy('id')->firstOrFail();
        $nombre = $primera->nombre;
        $admin = $this->como(Rol::Administrador);
        $mensaje = 'El id de la modalidad debe ser un número entero.';

        // true o "1" no son la modalidad 1.
        foreach ([true, '1', '01'] as $raro) {
            $admin->postJson(self::ACTIVAR, ['id' => $raro])
                ->assertStatus(422)->assertJsonValidationErrors(['id' => $mensaje]);
            $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $raro, 'nombre' => 'Otra'])
                ->assertStatus(422)->assertJsonValidationErrors(['id' => $mensaje]);
        }

        $primera->refresh();
        $this->assertTrue($primera->activo);
        $this->assertSame($nombre, $primera->nombre);
    }

    public function test_el_alta_lee_nombres_y_orden_dentro_de_su_transaccion(): void
    {
        // Lo primero que lee está dentro de la transacción del bloqueo: dos altas a la vez se ordenan.
        $admin = $this->como(Rol::Administrador);
        $fuera = DB::transactionLevel();
        $niveles = [];
        DB::listen(function (QueryExecuted $consulta) use (&$niveles) {
            if (preg_match('/^select .* from "modalidades"/i', ltrim($consulta->sql))) {
                $niveles[] = DB::transactionLevel();
            }
        });

        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Convenio'])->assertOk();

        $this->assertGreaterThan($fuera, $niveles[0] ?? $fuera);
        $this->assertSame(7, Modalidad::where('nombre', 'Convenio')->value('orden'));
    }

    public function test_las_modalidades_no_se_eliminan(): void
    {
        $this->assertFalse(
            collect(Route::getRoutes()->getRoutes())->contains(fn ($ruta) => $ruta->uri() === 'GestPro/eliminarModalidad'),
            'La ruta para eliminar modalidades debía retirarse.'
        );

        // Solo la atrapa la ruta GET del front, que no acepta POST.
        $this->como(Rol::Administrador)
            ->postJson('/GestPro/eliminarModalidad', ['id' => $this->modalidad('licitacion_publica')->id])
            ->assertStatus(405);
        $this->assertDatabaseCount('modalidades', 6);
    }

    public function test_exige_autenticacion(): void
    {
        $this->getJson('/GestPro/modalidades')->assertUnauthorized();
        $this->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Nueva'])->assertUnauthorized();
        $this->postJson(self::ACTIVAR, ['id' => 1])->assertUnauthorized();
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/ModalidadesApiTest.php`
Expected: FALLAN 13 y pasan 2: `ProyectoController::modalidades()` no existe (500) y no hay controlador de modalidades.

- [ ] **Paso 3: Implementar**

Crear `app/Http/Controllers/ModalidadController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Modalidad;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo de modalidades de contratación. Las modalidades no se eliminan: se desactivan, y una
 * desactivada se conserva en los procesos que ya la tienen. La clave la fija la siembra y no se
 * edita por aquí.
 */
class ModalidadController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Modalidad::query()->orderBy('orden')->orderBy('nombre')->get()
        );
    }

    public function guardar(Request $request): JsonResponse
    {
        $request->validate([
            'accion' => ['required', Rule::in(['Agregar', 'Editar'])],
            'id' => ['bail', 'required_if:accion,Editar', $this->entero()],
        ]);

        $modalidad = $request->input('accion') === 'Editar'
            ? Modalidad::findOrFail($request->input('id'))
            : new Modalidad(['activo' => true]);

        $datos = $request->validate([
            'nombre' => ['bail', 'required', 'string', 'max:199'],
        ], [
            'nombre.required' => 'Escribe el nombre de la modalidad.',
            'nombre.string' => 'Escribe el nombre de la modalidad.',
            'nombre.max' => 'El nombre admite hasta 199 caracteres.',
        ]);

        // Con todas las modalidades bloqueadas, dos altas o renombres a la vez no eligen el mismo
        // nombre ni el mismo orden: el segundo espera y ve lo que guardó el primero. `nombre` es
        // TEXT y no tiene índice único que lo respalde.
        DB::transaction(function () use ($modalidad, $datos) {
            $todas = Modalidad::query()->lockForUpdate()->get(['id', 'nombre', 'orden']);
            $this->exigirNombreLibre($modalidad, $datos['nombre'], $todas);
            if (! $modalidad->exists) {
                $modalidad->orden = (int) $todas->max('orden') + 1;
            }
            $modalidad->fill($datos)->save();
        });

        return response()->json(['success' => 'Modalidad guardada correctamente']);
    }

    public function activar(Request $request): JsonResponse
    {
        $request->validate(['id' => ['bail', 'required', $this->entero()]]);

        $modalidad = Modalidad::findOrFail($request->input('id'));
        $modalidad->update(['activo' => ! $modalidad->activo]);

        return response()->json(['success' => 'Modalidad actualizada correctamente']);
    }

    /** Enteros de verdad: `integer` admitiría true como 1 o "1". */
    private function entero(): Closure
    {
        return fn (string $atributo, mixed $valor, Closure $falla) => is_int($valor)
            || $falla('El id de la modalidad debe ser un número entero.');
    }

    /**
     * El nombre no se repite sin contar mayúsculas ni tildes. Se compara en PHP para que MariaDB y
     * SQLite lo hagan igual; el catálogo es pequeño.
     *
     * @param Collection<int, Modalidad> $todas
     */
    private function exigirNombreLibre(Modalidad $modalidad, string $nombre, Collection $todas): void
    {
        $normalizar = fn (?string $texto) => Str::lower(Str::ascii(trim((string) $texto)));
        $repetido = $todas->contains(fn (Modalidad $otra) => $otra->id !== $modalidad->id
            && $normalizar($otra->nombre) === $normalizar($nombre));

        if ($repetido) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una modalidad con ese nombre.']);
        }
    }
}
```

En `routes/api.php`, reemplazar (1 de 3):

```php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FuenteFinanciacionController;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\AuthController;
```

por:

```php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FuenteFinanciacionController;
use App\Http\Controllers\ModalidadController;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\AuthController;
```

En `routes/api.php`, reemplazar (2 de 3):

```php
    Route::get('/sectores', [ProyectoController::class, 'sectores']);
    Route::get('/fuentesFinanciacion', [FuenteFinanciacionController::class, 'index']);
    Route::get('/tiposProcesos', [ProyectoController::class, 'tiposProcesos']);
    Route::get('/modalidades', [ProyectoController::class, 'modalidades']);

    // Escritura de datos de negocio: Administrador o Gestor
    Route::middleware('can:editar-datos')->group(function () {
```

por:

```php
    Route::get('/sectores', [ProyectoController::class, 'sectores']);
    Route::get('/fuentesFinanciacion', [FuenteFinanciacionController::class, 'index']);
    Route::get('/tiposProcesos', [ProyectoController::class, 'tiposProcesos']);
    Route::get('/modalidades', [ModalidadController::class, 'index']);

    // Escritura de datos de negocio: Administrador o Gestor
    Route::middleware('can:editar-datos')->group(function () {
```

En `routes/api.php`, reemplazar (3 de 3):

```php
        Route::post('/guardarTipoProceso', [ProyectoController::class, 'guardarTipoProceso']);
        Route::post('/eliminarTipoProceso', [ProyectoController::class, 'eliminarTipoProceso']);
        Route::post('/activarTipoProceso', [ProyectoController::class, 'activarTipoProceso']);
        Route::post('/guardarModalidad', [ProyectoController::class, 'guardarModalidad']);
        Route::post('/eliminarModalidad', [ProyectoController::class, 'eliminarModalidad']);
        Route::post('/activarModalidad', [ProyectoController::class, 'activarModalidad']);
    });

    // Gestión de usuarios: solo Administrador
```

por:

```php
        Route::post('/guardarTipoProceso', [ProyectoController::class, 'guardarTipoProceso']);
        Route::post('/eliminarTipoProceso', [ProyectoController::class, 'eliminarTipoProceso']);
        Route::post('/activarTipoProceso', [ProyectoController::class, 'activarTipoProceso']);
        Route::post('/guardarModalidad', [ModalidadController::class, 'guardar']);
        Route::post('/activarModalidad', [ModalidadController::class, 'activar']);
    });

    // Gestión de usuarios: solo Administrador
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/ModalidadesApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 708 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Http/Controllers/ModalidadController.php tests/Feature/ModalidadesApiTest.php routes/api.php
git commit -m "feat: modalities API"
```

---

### Tarea 4: API de etapas de contratación

Catálogo de etapas con clave generada, activar y ordenar. Escritura solo Administrador.

**Files:**
- Create: `app/Http/Controllers/EtapaContratacionController.php`
- Test (create): `tests/Feature/EtapasContratacionApiTest.php`
- Modify: `routes/api.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/EtapasContratacionApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\EtapaContratacion;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EtapasContratacionApiTest extends TestCase
{
    use RefreshDatabase;

    private const LISTAR = '/GestPro/etapasContratacion';
    private const GUARDAR = '/GestPro/guardarEtapaContratacion';
    private const ACTIVAR = '/GestPro/activarEtapaContratacion';
    private const ORDENAR = '/GestPro/etapasContratacion/orden';

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function etapa(string $clave): EtapaContratacion
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail();
    }

    private function agregar(string $nombre, array $mas = []): EtapaContratacion
    {
        $this->como(Rol::Administrador)
            ->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => $nombre, ...$mas])
            ->assertOk();

        return EtapaContratacion::where('nombre', $nombre)->firstOrFail();
    }

    /** @return list<int> */
    private function idsEnOrden(): array
    {
        return EtapaContratacion::orderBy('orden')->pluck('id')->all();
    }

    // ------------------------------------------------------------- listar

    public function test_cualquier_usuario_autenticado_lista_todas_las_etapas_en_orden(): void
    {
        $this->etapa('adendas')->update(['activo' => false]);

        $this->como(Rol::Consulta)->getJson(self::LISTAR)
            ->assertOk()
            ->assertJsonCount(11)
            ->assertJsonPath('0.clave', 'planeacion')
            ->assertJsonPath('0.nombre', 'Planeación y estudios previos')
            ->assertJsonPath('0.orden', 1)
            ->assertJsonPath('0.activo', true)
            ->assertJsonPath('5.clave', 'adendas')
            ->assertJsonPath('5.activo', false)
            ->assertJsonPath('10.clave', 'contrato');
    }

    public function test_exige_autenticacion(): void
    {
        $this->getJson(self::LISTAR)->assertUnauthorized();
        $this->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Nueva'])->assertUnauthorized();
        $this->postJson(self::ACTIVAR, ['id' => 1])->assertUnauthorized();
        $this->putJson(self::ORDENAR, ['ids' => [1]])->assertUnauthorized();
    }

    // ------------------------------------------------------------- guardar

    public function test_agregar_crea_la_etapa_al_final_con_clave_generada_del_nombre(): void
    {
        $this->como(Rol::Administrador)->postJson(self::GUARDAR, [
            'accion' => 'Agregar',
            'nombre' => 'Liquidación del contrato',
            'descripcion' => 'Acta de liquidación y cierre del expediente.',
            'clave' => 'otra',
            'orden' => 1,
        ])->assertOk()
            ->assertJsonPath('success', 'Etapa de contratación guardada correctamente')
            ->assertJsonPath('etapa.clave', 'liquidacion_del_contrato');

        $etapa = EtapaContratacion::where('nombre', 'Liquidación del contrato')->firstOrFail();
        $this->assertSame('liquidacion_del_contrato', $etapa->clave);
        $this->assertSame('Acta de liquidación y cierre del expediente.', $etapa->descripcion);
        $this->assertSame(12, $etapa->orden);
        $this->assertTrue($etapa->activo);
    }

    public function test_la_clave_generada_quita_tildes_y_simbolos(): void
    {
        $this->assertSame('reunion_de_diseno_y_cotizacion_urgente', $this->agregar('Reunión de diseño   y cotización (¡urgente!)')->clave);
        $this->assertSame('pago_al_100_contratista', $this->agregar('Pago al 100 % @ contratista')->clave);
        $this->assertSame('visita_al_sitio_de_obra', $this->agregar('VISITA AL SITIO DE OBRA')->clave);
        $this->assertSame('etapa', $this->agregar('¿?')->clave);
    }

    public function test_la_clave_generada_se_desambigua_con_un_sufijo(): void
    {
        // "cierre" y "evaluacion" ya son claves sembradas.
        $this->assertSame('cierre_2', $this->agregar('Cierre')->clave);
        $this->assertSame('cierre_3', $this->agregar('¡Cierre!')->clave);
        $this->assertSame('evaluacion_2', $this->agregar('Evaluación')->clave);
    }

    public function test_la_clave_generada_no_pasa_de_cuarenta_caracteres(): void
    {
        $nombre = str_repeat('Revisión jurídica ', 6) . 'final';
        $primera = $this->agregar($nombre);
        $segunda = $this->agregar($nombre . ' bis');

        $this->assertSame(40, strlen($primera->clave));
        $this->assertStringStartsWith('revision_juridica_revision', $primera->clave);
        $this->assertLessThanOrEqual(40, strlen($segunda->clave));
        $this->assertStringEndsWith('_2', $segunda->clave);
        $this->assertNotSame($primera->clave, $segunda->clave);
    }

    public function test_editar_cambia_nombre_y_descripcion_y_conserva_clave_orden_y_estado(): void
    {
        $etapa = $this->etapa('adendas');
        $etapa->update(['activo' => false]);

        $this->como(Rol::Administrador)->postJson(self::GUARDAR, [
            'accion' => 'Editar',
            'id' => $etapa->id,
            'nombre' => 'Adendas al pliego',
            'descripcion' => null,
            'clave' => 'otra',
            'orden' => 1,
        ])->assertOk()->assertJsonPath('etapa.clave', 'adendas');

        $etapa = $etapa->fresh();
        $this->assertSame('Adendas al pliego', $etapa->nombre);
        $this->assertNull($etapa->descripcion);
        $this->assertSame('adendas', $etapa->clave);
        $this->assertSame(6, $etapa->orden);
        $this->assertFalse($etapa->activo);
    }

    public function test_rechaza_datos_invalidos_con_mensajes_en_espanol(): void
    {
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => ''])
            ->assertStatus(422)->assertJsonPath('errors.nombre.0', 'Escribe el nombre de la etapa.');
        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => str_repeat('a', 121)])
            ->assertStatus(422)->assertJsonPath('errors.nombre.0', 'El nombre admite hasta 120 caracteres.');
        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Nueva', 'descripcion' => str_repeat('a', 2001)])
            ->assertStatus(422)->assertJsonPath('errors.descripcion.0', 'La descripción admite hasta 2000 caracteres.');
        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => ['Nueva']])
            ->assertStatus(422)->assertJsonValidationErrors('nombre');

        $this->assertDatabaseCount('etapas_contratacion', 11);

        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => str_repeat('a', 120), 'descripcion' => str_repeat('a', 2000)])
            ->assertOk();
    }

    public function test_el_nombre_no_se_repite_sin_distinguir_mayusculas_ni_tildes(): void
    {
        $admin = $this->como(Rol::Administrador);

        foreach (['Adendas', 'ADENDAS', 'Evaluacion y subsanaciones'] as $nombre) {
            $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => $nombre])
                ->assertStatus(422)
                ->assertJsonPath('errors.nombre.0', 'Ya existe una etapa con ese nombre.');
        }

        $otra = $this->etapa('cierre');
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $otra->id, 'nombre' => 'adendas'])
            ->assertStatus(422)->assertJsonValidationErrors('nombre');
        $this->assertSame('Cierre y apertura de ofertas', $otra->fresh()->nombre);
        $this->assertDatabaseCount('etapas_contratacion', 11);
    }

    public function test_editar_sin_cambiar_el_nombre_o_solo_sus_mayusculas_no_choca_consigo_misma(): void
    {
        $etapa = $this->etapa('adendas');
        $descripcion = $etapa->descripcion;
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $etapa->id, 'nombre' => 'Adendas'])->assertOk();
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $etapa->id, 'nombre' => 'ADENDAS'])->assertOk();

        $this->assertSame('ADENDAS', $etapa->fresh()->nombre);
        // Sin descripción en la petición, se conserva la que tenía.
        $this->assertSame($descripcion, $etapa->fresh()->descripcion);
    }

    public function test_rechaza_acciones_e_ids_invalidos(): void
    {
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['nombre' => 'Nueva'])->assertStatus(422)->assertJsonValidationErrors('accion');
        $admin->postJson(self::GUARDAR, ['accion' => 'Borrar', 'nombre' => 'Nueva'])->assertStatus(422);
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'nombre' => 'Nueva'])->assertStatus(422)->assertJsonValidationErrors('id');
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => [1], 'nombre' => 'Nueva'])->assertStatus(422);
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => 999999, 'nombre' => 'Nueva'])->assertNotFound();

        $this->assertDatabaseCount('etapas_contratacion', 11);
    }

    public function test_el_id_debe_ser_un_entero_de_verdad(): void
    {
        $primera = EtapaContratacion::orderBy('id')->firstOrFail();
        $nombre = $primera->nombre;
        $admin = $this->como(Rol::Administrador);
        $mensaje = 'El id de la etapa debe ser un número entero.';

        // true o "1" no son la etapa 1.
        foreach ([true, '1', '01'] as $raro) {
            $admin->postJson(self::ACTIVAR, ['id' => $raro])
                ->assertStatus(422)->assertJsonValidationErrors(['id' => $mensaje]);
            $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $raro, 'nombre' => 'Otra'])
                ->assertStatus(422)->assertJsonValidationErrors(['id' => $mensaje]);
        }

        $primera->refresh();
        $this->assertTrue($primera->activo);
        $this->assertSame($nombre, $primera->nombre);
    }

    public function test_el_alta_lee_nombres_claves_y_orden_dentro_de_su_transaccion(): void
    {
        // Lo primero que lee está dentro de la transacción del bloqueo: dos altas a la vez se ordenan.
        $admin = $this->como(Rol::Administrador);
        $fuera = DB::transactionLevel();
        $niveles = [];
        DB::listen(function (QueryExecuted $consulta) use (&$niveles) {
            if (preg_match('/^select .* from "etapas_contratacion"/i', ltrim($consulta->sql))) {
                $niveles[] = DB::transactionLevel();
            }
        });

        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Cierre'])->assertOk();

        $this->assertGreaterThan($fuera, $niveles[0] ?? $fuera);
        $this->assertSame(12, $this->etapa('cierre_2')->orden);
    }

    public function test_si_la_clave_se_ocupa_a_la_vez_responde_422_sin_guardar(): void
    {
        // Algo que no pasa por esta ruta toma la clave justo antes de insertar.
        EtapaContratacion::creating(function (EtapaContratacion $etapa) {
            DB::table('etapas_contratacion')->insert(['clave' => $etapa->clave, 'nombre' => 'Intrusa', 'orden' => 99]);
        });

        $this->como(Rol::Administrador)->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Liquidación'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre' => 'Otra etapa se guardó al mismo tiempo con la misma clave. Vuelve a intentarlo.']);

        $this->assertDatabaseCount('etapas_contratacion', 11);
    }

    // ------------------------------------------------------------- activar

    public function test_activar_alterna_el_estado_segun_la_base_de_datos(): void
    {
        $etapa = $this->etapa('adendas');
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::ACTIVAR, ['id' => $etapa->id])
            ->assertOk()->assertJsonPath('success', 'Etapa de contratación actualizada correctamente');
        $this->assertFalse($etapa->fresh()->activo);

        $admin->postJson(self::ACTIVAR, ['id' => $etapa->id])->assertOk();
        $this->assertTrue($etapa->fresh()->activo);

        $admin->postJson(self::ACTIVAR, [])->assertStatus(422);
        $admin->postJson(self::ACTIVAR, ['id' => [1]])->assertStatus(422);
        $admin->postJson(self::ACTIVAR, ['id' => 999999])->assertNotFound();
    }

    // ------------------------------------------------------------- ordenar

    public function test_ordenar_reescribe_el_orden_con_la_lista_completa(): void
    {
        $this->etapa('contrato')->update(['orden' => 40]); // huecos o repetidos se normalizan
        $ids = array_reverse($this->idsEnOrden());

        $this->como(Rol::Administrador)->putJson(self::ORDENAR, ['ids' => $ids])
            ->assertOk()->assertJsonPath('success', 'Orden de las etapas guardado correctamente');

        $this->assertSame($ids, $this->idsEnOrden());
        $this->assertSame(range(1, 11), EtapaContratacion::orderBy('orden')->pluck('orden')->all());
        $this->assertSame('contrato', EtapaContratacion::where('orden', 1)->value('clave'));
    }

    public function test_ordenar_usa_una_sola_actualizacion(): void
    {
        $ids = array_reverse($this->idsEnOrden());
        $actualizaciones = 0;
        DB::listen(function (QueryExecuted $consulta) use (&$actualizaciones) {
            if (str_starts_with(strtolower(ltrim($consulta->sql)), 'update')) {
                $actualizaciones++;
            }
        });

        $this->como(Rol::Administrador)->putJson(self::ORDENAR, ['ids' => $ids])->assertOk();

        $this->assertSame(1, $actualizaciones);
        $this->assertSame($ids, $this->idsEnOrden());
    }

    public function test_ordenar_incluye_las_etapas_inactivas_y_las_nuevas(): void
    {
        $this->etapa('adendas')->update(['activo' => false]);
        $nueva = $this->agregar('Liquidación');
        $ids = $this->idsEnOrden();
        array_unshift($ids, array_pop($ids));

        $this->como(Rol::Administrador)->putJson(self::ORDENAR, ['ids' => $ids])->assertOk();

        $this->assertSame(1, $nueva->fresh()->orden);
        $this->assertSame(7, $this->etapa('adendas')->orden);
    }

    public function test_ordenar_rechaza_listas_incompletas_repetidas_o_con_extranos(): void
    {
        $ids = $this->idsEnOrden();
        $antes = $ids;
        $admin = $this->como(Rol::Administrador);
        $mensaje = 'El orden debe incluir todas las etapas, una vez cada una.';

        $admin->putJson(self::ORDENAR, ['ids' => array_slice($ids, 1)])
            ->assertStatus(422)->assertJsonPath('errors.ids.0', $mensaje);
        $admin->putJson(self::ORDENAR, ['ids' => [...$ids, 999999]])
            ->assertStatus(422)->assertJsonPath('errors.ids.0', $mensaje);
        $admin->putJson(self::ORDENAR, ['ids' => [...array_slice($ids, 1), 999999]])
            ->assertStatus(422)->assertJsonPath('errors.ids.0', $mensaje);
        $admin->putJson(self::ORDENAR, ['ids' => [...$ids, $ids[0]]])
            ->assertStatus(422)->assertJsonValidationErrors(['ids.11' => 'Una etapa aparece más de una vez en el orden.']);

        $this->assertSame($antes, $this->idsEnOrden());
    }

    public function test_ordenar_exige_una_lista_de_enteros(): void
    {
        $ids = $this->idsEnOrden();
        $admin = $this->como(Rol::Administrador);

        $admin->putJson(self::ORDENAR, [])
            ->assertStatus(422)->assertJsonPath('errors.ids.0', 'Envía el orden de las etapas.');
        $admin->putJson(self::ORDENAR, ['ids' => []])
            ->assertStatus(422)->assertJsonValidationErrors('ids');
        $admin->putJson(self::ORDENAR, ['ids' => '1,2,3'])
            ->assertStatus(422)->assertJsonValidationErrors('ids');

        foreach (['3', true, 3.5, null, ['3']] as $raro) {
            $lista = $ids;
            $lista[0] = $raro;
            $admin->putJson(self::ORDENAR, ['ids' => $lista])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['ids.0' => 'Cada etapa del orden debe ser un número entero.']);
        }
    }

    // ------------------------------------------------------------- roles

    public function test_solo_el_administrador_modifica_el_catalogo(): void
    {
        $etapa = $this->etapa('adendas');
        $ids = $this->idsEnOrden();

        foreach ([Rol::Gestor, Rol::Consulta] as $rol) {
            $usuario = $this->como($rol);

            $usuario->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Nueva'])->assertForbidden();
            $usuario->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $etapa->id, 'nombre' => 'Otra'])->assertForbidden();
            $usuario->postJson(self::ACTIVAR, ['id' => $etapa->id])->assertForbidden();
            $usuario->putJson(self::ORDENAR, ['ids' => array_reverse($ids)])->assertForbidden();
        }

        $this->assertDatabaseCount('etapas_contratacion', 11);
        $this->assertSame('Adendas', $etapa->fresh()->nombre);
        $this->assertTrue($etapa->fresh()->activo);
        $this->assertSame($ids, $this->idsEnOrden());
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/EtapasContratacionApiTest.php`
Expected: FALLAN las 21: las rutas de etapas de contratación no existen y el comodín web responde HTML (200) en vez de JSON o 401.

- [ ] **Paso 3: Implementar**

Crear `app/Http/Controllers/EtapaContratacionController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\EtapaContratacion;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo de etapas de contratación. Las etapas no se eliminan: se desactivan. La clave se
 * genera del nombre al crear la etapa y no cambia después, porque la usarán las plantillas.
 */
class EtapaContratacionController extends Controller
{
    private const LARGO_CLAVE = 40;

    public function index(): JsonResponse
    {
        return response()->json(
            EtapaContratacion::query()->orderBy('orden')->orderBy('id')->get()
        );
    }

    public function guardar(Request $request): JsonResponse
    {
        $request->validate([
            'accion' => ['required', Rule::in(['Agregar', 'Editar'])],
            'id' => ['bail', 'required_if:accion,Editar', $this->entero('El id de la etapa debe ser un número entero.')],
        ]);

        $etapa = $request->input('accion') === 'Editar'
            ? EtapaContratacion::findOrFail($request->input('id'))
            : new EtapaContratacion(['activo' => true]);

        $datos = $request->validate([
            'nombre' => ['bail', 'required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
        ], [
            'nombre.required' => 'Escribe el nombre de la etapa.',
            'nombre.string' => 'Escribe el nombre de la etapa.',
            'nombre.max' => 'El nombre admite hasta 120 caracteres.',
            'descripcion.string' => 'La descripción debe ser un texto.',
            'descripcion.max' => 'La descripción admite hasta 2000 caracteres.',
        ]);

        try {
            // Con todas las etapas bloqueadas, dos altas o renombres a la vez no eligen el mismo
            // nombre, clave ni orden: el segundo espera y ve lo que guardó el primero.
            DB::transaction(function () use ($etapa, $datos) {
                $todas = EtapaContratacion::query()->lockForUpdate()->get(['id', 'clave', 'nombre', 'orden']);
                $this->exigirNombreLibre($etapa, $datos['nombre'], $todas);

                // Solo nombre y descripción, y la descripción solo si llegó: la clave y el orden no
                // se editan por aquí.
                $etapa->fill($datos);
                if (! $etapa->exists) {
                    $etapa->clave = $this->claveNueva($datos['nombre'], $todas->pluck('clave'));
                    $etapa->orden = (int) $todas->max('orden') + 1;
                }
                $etapa->save();
            });
        } catch (UniqueConstraintViolationException) {
            // La clave la tomó a la vez algo que no pasa por esta ruta: se pide repetir, sin un 500.
            throw ValidationException::withMessages([
                'nombre' => 'Otra etapa se guardó al mismo tiempo con la misma clave. Vuelve a intentarlo.',
            ]);
        }

        return response()->json([
            'success' => 'Etapa de contratación guardada correctamente',
            'etapa' => $etapa->fresh(),
        ]);
    }

    public function activar(Request $request): JsonResponse
    {
        $request->validate(['id' => ['bail', 'required', $this->entero('El id de la etapa debe ser un número entero.')]]);

        $etapa = EtapaContratacion::findOrFail($request->input('id'));
        $etapa->update(['activo' => ! $etapa->activo]);

        return response()->json(['success' => 'Etapa de contratación actualizada correctamente']);
    }

    /** Reescribe el orden 1..n con la lista completa de etapas, en una sola actualización. */
    public function ordenar(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['bail', $this->entero('Cada etapa del orden debe ser un número entero.'), 'distinct:strict'],
        ], [
            'ids.required' => 'Envía el orden de las etapas.',
            'ids.array' => 'Envía el orden de las etapas.',
            'ids.*.distinct' => 'Una etapa aparece más de una vez en el orden.',
        ])['ids'];

        DB::transaction(function () use ($ids) {
            $todas = EtapaContratacion::query()->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            if (collect($ids)->sort()->values()->all() !== $todas) {
                throw ValidationException::withMessages(['ids' => 'El orden debe incluir todas las etapas, una vez cada una.']);
            }

            // Los ids ya son enteros validados: se escriben en la sentencia sin riesgo de inyección.
            $casos = collect($ids)->map(fn (int $id, int $posicion) => 'when ' . $id . ' then ' . ($posicion + 1))->implode(' ');
            EtapaContratacion::query()->update(['orden' => DB::raw("case id {$casos} end")]);
        });

        return response()->json(['success' => 'Orden de las etapas guardado correctamente']);
    }

    /** Enteros de verdad: `integer` admitiría true como 1 o "1". */
    private function entero(string $mensaje): Closure
    {
        return fn (string $atributo, mixed $valor, Closure $falla) => is_int($valor) || $falla($mensaje);
    }

    /**
     * El nombre no se repite sin contar mayúsculas ni tildes. Se compara en PHP para que MariaDB y
     * SQLite lo hagan igual; el catálogo es pequeño.
     *
     * @param Collection<int, EtapaContratacion> $todas
     */
    private function exigirNombreLibre(EtapaContratacion $etapa, string $nombre, Collection $todas): void
    {
        $normalizar = fn (?string $texto) => Str::lower(Str::ascii(trim((string) $texto)));
        $repetido = $todas->contains(fn (EtapaContratacion $otra) => $otra->id !== $etapa->id
            && $normalizar($otra->nombre) === $normalizar($nombre));

        if ($repetido) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una etapa con ese nombre.']);
        }
    }

    /**
     * Clave del nombre en minúsculas, sin tildes y con guion bajo, de hasta 40 caracteres. Si ya
     * está entre `$claves`, se le agrega _2, _3... recortando la base para no pasar del largo.
     *
     * @param Collection<int, string> $claves
     */
    private function claveNueva(string $nombre, Collection $claves): string
    {
        $base = rtrim(substr(Str::slug($nombre, '_', 'en', []), 0, self::LARGO_CLAVE), '_') ?: 'etapa';
        $existentes = $claves->flip();

        $clave = $base;
        for ($numero = 2; $existentes->has($clave); $numero++) {
            $sufijo = '_' . $numero;
            $clave = rtrim(substr($base, 0, self::LARGO_CLAVE - strlen($sufijo)), '_') . $sufijo;
        }

        return $clave;
    }
}
```

En `routes/api.php`, reemplazar (1 de 3):

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FuenteFinanciacionController;
use App\Http\Controllers\ModalidadController;
use App\Http\Controllers\ProyectoController;
```

por:

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EtapaContratacionController;
use App\Http\Controllers\FuenteFinanciacionController;
use App\Http\Controllers\ModalidadController;
use App\Http\Controllers\ProyectoController;
```

En `routes/api.php`, reemplazar (2 de 3):

```php
    Route::get('/fuentesFinanciacion', [FuenteFinanciacionController::class, 'index']);
    Route::get('/tiposProcesos', [ProyectoController::class, 'tiposProcesos']);
    Route::get('/modalidades', [ModalidadController::class, 'index']);

    // Escritura de datos de negocio: Administrador o Gestor
    Route::middleware('can:editar-datos')->group(function () {
```

por:

```php
    Route::get('/fuentesFinanciacion', [FuenteFinanciacionController::class, 'index']);
    Route::get('/tiposProcesos', [ProyectoController::class, 'tiposProcesos']);
    Route::get('/modalidades', [ModalidadController::class, 'index']);
    Route::get('/etapasContratacion', [EtapaContratacionController::class, 'index']);

    // Escritura de datos de negocio: Administrador o Gestor
    Route::middleware('can:editar-datos')->group(function () {
```

En `routes/api.php`, reemplazar (3 de 3):

```php
        Route::post('/activarTipoProceso', [ProyectoController::class, 'activarTipoProceso']);
        Route::post('/guardarModalidad', [ModalidadController::class, 'guardar']);
        Route::post('/activarModalidad', [ModalidadController::class, 'activar']);
    });

    // Gestión de usuarios: solo Administrador
```

por:

```php
        Route::post('/activarTipoProceso', [ProyectoController::class, 'activarTipoProceso']);
        Route::post('/guardarModalidad', [ModalidadController::class, 'guardar']);
        Route::post('/activarModalidad', [ModalidadController::class, 'activar']);
        Route::post('/guardarEtapaContratacion', [EtapaContratacionController::class, 'guardar']);
        Route::post('/activarEtapaContratacion', [EtapaContratacionController::class, 'activar']);
        Route::put('/etapasContratacion/orden', [EtapaContratacionController::class, 'ordenar']);
    });

    // Gestión de usuarios: solo Administrador
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/EtapasContratacionApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 729 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Http/Controllers/EtapaContratacionController.php tests/Feature/EtapasContratacionApiTest.php routes/api.php
git commit -m "feat: procurement stages API"
```

---

### Tarea 5: API de procesos de contratación

Servicio `ProcesosContratacion` y sus rutas en `routes/contratacion.php`, con historial.

**Files:**
- Create: `app/Exceptions/EdicionDesactualizadaException.php`
- Create: `app/Http/Controllers/ProcesoContratacionController.php`
- Create: `app/Services/Contratacion/ProcesosContratacion.php`
- Create: `routes/contratacion.php`
- Test (create): `tests/Feature/Contratacion/ProcesosContratacionApiTest.php`
- Modify: `app/Models/ProcesoLicitacion.php`
- Modify: `app/Services/Viabilizacion/HistorialChecklist.php`
- Modify: `routes/api.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/ProcesosContratacionApiTest.php`:

```php
<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\Contrato;
use App\Models\Entidad;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Procesos de contratación de un proyecto: listar, crear, editar, cambiar estado y eliminar. */
class ProcesosContratacionApiTest extends TestCase
{
    use RefreshDatabase;

    private Proyecto $proyecto;
    private Entidad $entidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
        $this->entidad = Entidad::create(['nombre' => 'Gobernación del Cesar']);
    }

    private function como(Rol|User $quien = Rol::Gestor): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function modalidad(string $clave): Modalidad
    {
        return Modalidad::where('clave', $clave)->firstOrFail();
    }

    private function urlProyecto(?Proyecto $proyecto = null): string
    {
        return '/GestPro/proyectos/' . ($proyecto ?? $this->proyecto)->id . '/procesos';
    }

    private function urlProceso(ProcesoLicitacion|int $proceso, string $sufijo = ''): string
    {
        return '/GestPro/procesos/' . ($proceso instanceof ProcesoLicitacion ? $proceso->id : $proceso) . $sufijo;
    }

    private function datos(array $mas = []): array
    {
        return [
            'objeto' => 'Construcción del acueducto veredal',
            'modalidad_id' => $this->modalidad('licitacion_publica')->id,
            'plataforma' => 'secop_ii',
            ...$mas,
        ];
    }

    private function crear(array $mas = [], ?Proyecto $proyecto = null)
    {
        return $this->como()->postJson($this->urlProyecto($proyecto), $this->datos($mas));
    }

    private function proceso(array $mas = [], ?Proyecto $proyecto = null): ProcesoLicitacion
    {
        return ProcesoLicitacion::findOrFail($this->crear($mas, $proyecto)->assertCreated()->json('id'));
    }

    /** El `actualizado` que tendría ahora el formulario del proceso: el de presentar(). */
    private function version(ProcesoLicitacion $proceso): ?string
    {
        return $proceso->fresh()->updated_at?->toIso8601String();
    }

    /** Edita como desde un formulario recién abierto, salvo que `$datos` traiga otro `actualizado`. */
    private function editar(ProcesoLicitacion $proceso, array $datos)
    {
        return $this->como()->putJson($this->urlProceso($proceso), ['actualizado' => $this->version($proceso), ...$datos]);
    }

    /** Cambia el estado como desde un formulario recién abierto, salvo que `$datos` traiga otro `actualizado`. */
    private function cambiarEstado(ProcesoLicitacion $proceso, array $datos)
    {
        return $this->como()->postJson($this->urlProceso($proceso, '/estado'), ['actualizado' => $this->version($proceso), ...$datos]);
    }

    /** @return list<array{0: string, 1: ?array, 2: ?array}> */
    private function eventos(ProcesoLicitacion|int $proceso): array
    {
        return DB::table('checklist_historial')
            ->where('entidad_tipo', 'proceso')
            ->where('entidad_id', $proceso instanceof ProcesoLicitacion ? $proceso->id : $proceso)
            ->orderBy('id')->get()
            ->map(fn ($h) => [$h->evento, json_decode((string) $h->antes, true), json_decode((string) $h->despues, true)])
            ->all();
    }

    // ------------------------------------------------------------------ crear

    public function test_un_gestor_crea_un_proceso_en_preparacion(): void
    {
        $gestor = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Ana Gestora']);
        $modalidad = $this->modalidad('licitacion_publica');

        $r = $this->como($gestor)->postJson($this->urlProyecto(), $this->datos([
            'objeto' => '  Construcción del acueducto veredal  ',
            'codigo_proceso' => 'LP-001-2026',
            'url_proceso' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.1',
            'entidad_contratante_id' => $this->entidad->id,
            'monto' => 1500000.5,
        ]))->assertCreated();

        $r->assertExactJson([
            'id' => $r->json('id'),
            'objeto' => 'Construcción del acueducto veredal',
            'modalidad' => ['id' => $modalidad->id, 'nombre' => 'Licitación pública', 'clave' => 'licitacion_publica', 'activo' => true],
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-001-2026',
            'url_proceso' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.1',
            'entidad_contratante' => ['id' => $this->entidad->id, 'nombre' => 'Gobernación del Cesar'],
            'monto' => '1500000.50',
            'estado' => 'preparacion',
            'contratos' => 0,
            'creado' => $r->json('creado'),
            'actualizado' => $r->json('actualizado'),
            'usuario_creo' => 'Ana Gestora',
        ]);
        $this->assertNotNull($r->json('creado'));

        $proceso = ProcesoLicitacion::findOrFail($r->json('id'));
        $this->assertSame($this->proyecto->id, (int) $proceso->proyecto);
        $this->assertSame($gestor->id, $proceso->usuario_creo_id);
        $this->assertSame($modalidad->id, (int) $proceso->modalidad);
        $this->assertSame($this->entidad->id, (int) $proceso->entidad_contratante);

        $this->assertSame([['proceso_creado', null, [
            'proyecto_id' => $this->proyecto->id,
            'objeto' => 'Construcción del acueducto veredal',
            'modalidad_id' => $modalidad->id,
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-001-2026',
            'url_proceso' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.1',
            'entidad_contratante_id' => $this->entidad->id,
            'monto' => '1500000.50',
            'estado' => 'preparacion',
        ]]], $this->eventos($proceso));
        $this->assertNull(DB::table('checklist_historial')->where('entidad_tipo', 'proceso')->value('expediente_id'));
        $this->assertSame($gestor->id, (int) DB::table('checklist_historial')->where('entidad_tipo', 'proceso')->value('usuario_id'));
    }

    public function test_los_campos_opcionales_pueden_faltar_o_venir_vacios(): void
    {
        $this->crear(['codigo_proceso' => '   ', 'url_proceso' => null])->assertCreated()
            ->assertJsonPath('codigo_proceso', null)
            ->assertJsonPath('url_proceso', null)
            ->assertJsonPath('entidad_contratante', null)
            ->assertJsonPath('monto', null);
    }

    public function test_el_estado_no_se_fija_al_crear_ni_al_editar(): void
    {
        $proceso = $this->proceso(['estado' => 'adjudicado']);
        $this->assertSame('preparacion', $proceso->estado);

        $this->editar($proceso, $this->datos(['estado' => 'celebrado']))
            ->assertOk()->assertJsonPath('estado', 'preparacion');
    }

    public function test_el_monto_admite_un_texto_numerico(): void
    {
        $this->crear(['monto' => '2500000'])->assertCreated()->assertJsonPath('monto', '2500000.00');
        $this->crear(['monto' => 0])->assertCreated()->assertJsonPath('monto', '0.00');
        $this->crear(['monto' => '9999999999999.99'])->assertCreated()->assertJsonPath('monto', '9999999999999.99');
    }

    // ----------------------------------------------------------------- listar

    public function test_lista_los_procesos_del_proyecto_en_orden_con_sus_contratos(): void
    {
        $otroProyecto = Proyecto::create(['nombre' => 'Vía terciaria']);
        $obra = $this->proceso();
        $ajeno = $this->proceso(['objeto' => 'Pavimentación'], $otroProyecto);
        $interventoria = $this->proceso([
            'objeto' => 'Interventoría del acueducto',
            'modalidad_id' => $this->modalidad('concurso_meritos')->id,
            'plataforma' => 'secop_i',
        ]);
        Contrato::create(['proyecto' => $this->proyecto->id, 'proceso_licitacion' => $interventoria->id]);
        Contrato::create(['proyecto' => $this->proyecto->id, 'proceso_licitacion' => $interventoria->id]);

        $r = $this->como(Rol::Consulta)->getJson($this->urlProyecto())->assertOk();

        $r->assertJsonPath('proyecto', ['id' => $this->proyecto->id, 'nombre' => 'Acueducto veredal'])
            ->assertJsonCount(2, 'procesos')
            ->assertJsonPath('procesos.0.id', $obra->id)
            ->assertJsonPath('procesos.0.contratos', 0)
            ->assertJsonPath('procesos.1.id', $interventoria->id)
            ->assertJsonPath('procesos.1.objeto', 'Interventoría del acueducto')
            ->assertJsonPath('procesos.1.modalidad.clave', 'concurso_meritos')
            ->assertJsonPath('procesos.1.plataforma', 'secop_i')
            ->assertJsonPath('procesos.1.contratos', 2);
        $this->assertNotContains($ajeno->id, array_column($r->json('procesos'), 'id'));

        // Cada proceso del listado tiene la misma forma que devuelve crear.
        $this->assertSame(
            array_keys($this->crear()->json()),
            array_keys($r->json('procesos.0'))
        );
    }

    public function test_el_listado_no_consulta_por_cada_proceso(): void
    {
        $this->proceso(['entidad_contratante_id' => $this->entidad->id]);
        $consultas = function (): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson($this->urlProyecto())->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $conUno = $consultas();

        $otra = Entidad::create(['nombre' => 'Alcaldía de Becerril']);
        foreach (['minima_cuantia', 'seleccion_abreviada', 'contratacion_directa'] as $clave) {
            $proceso = $this->proceso(['modalidad_id' => $this->modalidad($clave)->id, 'entidad_contratante_id' => $otra->id]);
            Contrato::create(['proyecto' => $this->proyecto->id, 'proceso_licitacion' => $proceso->id]);
        }

        $this->assertSame($conUno, $consultas());
    }

    public function test_un_proyecto_sin_procesos_lista_vacio(): void
    {
        $this->como(Rol::Consulta)->getJson($this->urlProyecto())->assertOk()->assertJsonPath('procesos', []);
    }

    // ----------------------------------------------------------------- editar

    public function test_edita_y_registra_solo_lo_que_cambio(): void
    {
        $proceso = $this->proceso(['codigo_proceso' => 'LP-001', 'monto' => 1000]);
        $directa = $this->modalidad('contratacion_directa');

        $this->editar($proceso, $this->datos([
            'objeto' => 'Construcción del acueducto veredal, fase 2',
            'modalidad_id' => $directa->id,
            'codigo_proceso' => 'LP-001',
            'entidad_contratante_id' => $this->entidad->id,
            'monto' => '1000.00',
        ]))->assertOk()
            ->assertJsonPath('id', $proceso->id)
            ->assertJsonPath('objeto', 'Construcción del acueducto veredal, fase 2')
            ->assertJsonPath('modalidad.id', $directa->id)
            ->assertJsonPath('entidad_contratante.id', $this->entidad->id)
            ->assertJsonPath('monto', '1000.00');

        $this->assertSame(['proceso_editado', [
            'objeto' => 'Construcción del acueducto veredal',
            'modalidad_id' => $this->modalidad('licitacion_publica')->id,
            'entidad_contratante_id' => null,
        ], [
            'objeto' => 'Construcción del acueducto veredal, fase 2',
            'modalidad_id' => $directa->id,
            'entidad_contratante_id' => $this->entidad->id,
        ]], $this->eventos($proceso)[1]);
    }

    public function test_los_opcionales_que_no_vienen_no_cambian_y_con_null_se_borran(): void
    {
        $proceso = $this->proceso([
            'codigo_proceso' => 'LP-001', 'url_proceso' => 'http://secop.gov.co/1', 'monto' => 5000,
            'entidad_contratante_id' => $this->entidad->id,
        ]);

        $this->editar($proceso, $this->datos(['codigo_proceso' => null, 'monto' => null]))
            ->assertOk()
            ->assertJsonPath('codigo_proceso', null)
            ->assertJsonPath('monto', null)
            ->assertJsonPath('url_proceso', 'http://secop.gov.co/1')
            ->assertJsonPath('entidad_contratante.id', $this->entidad->id);

        $this->assertSame(['proceso_editado', ['codigo_proceso' => 'LP-001', 'monto' => '5000.00'], ['codigo_proceso' => null, 'monto' => null]],
            $this->eventos($proceso)[1]);
    }

    public function test_editar_sin_cambios_no_registra_nada(): void
    {
        $proceso = $this->proceso(['monto' => 1200]);

        $this->editar($proceso, $this->datos(['monto' => '1200']))->assertOk();

        $this->assertCount(1, $this->eventos($proceso));
    }

    // ------------------------------------------------- ediciones simultáneas

    public function test_un_formulario_desactualizado_no_deshace_lo_que_guardo_otra_persona(): void
    {
        $proceso = $this->proceso(['codigo_proceso' => 'LP-001']);
        // Las dos personas abren el formulario a la vez: el mismo `actualizado`, el del listado.
        $abierto = $this->como(Rol::Consulta)->getJson($this->urlProyecto())->json('procesos.0.actualizado');
        $this->assertSame($this->version($proceso), $abierto);

        $this->editar($proceso, $this->datos(['objeto' => 'Lo de la primera', 'actualizado' => $abierto]))->assertOk();
        $this->editar($proceso, $this->datos(['codigo_proceso' => null, 'actualizado' => $abierto]))
            ->assertStatus(409)
            ->assertExactJson(['mensaje' => 'Otra persona cambió este proceso mientras lo editabas. Ciérralo y vuelve a abrirlo para ver lo último.']);

        $proceso->refresh();
        $this->assertSame('Lo de la primera', $proceso->objeto);
        $this->assertSame('LP-001', $proceso->codigo_proceso);
        $this->assertCount(2, $this->eventos($proceso));

        // Con la versión de ahora pasa.
        $this->editar($proceso, $this->datos(['objeto' => 'Lo de la primera', 'codigo_proceso' => null]))
            ->assertOk()->assertJsonPath('codigo_proceso', null);
    }

    public function test_cada_cambio_mueve_la_version_aunque_caiga_en_el_mismo_segundo(): void
    {
        $this->freezeSecond();
        $proceso = $this->proceso();
        $versiones = [$this->version($proceso)];

        $versiones[] = $this->editar($proceso, $this->datos(['objeto' => 'Uno']))->assertOk()->json('actualizado');
        $versiones[] = $this->cambiarEstado($proceso, ['estado' => 'publicado'])->assertOk()->json('actualizado');
        $versiones[] = $this->editar($proceso, $this->datos(['objeto' => 'Dos']))->assertOk()->json('actualizado');

        $this->assertSame($versiones, array_values(array_unique($versiones)));
        $this->assertSame(end($versiones), $this->version($proceso));
    }

    public function test_editar_sin_cambios_no_mueve_la_version(): void
    {
        $proceso = $this->proceso();
        $version = $this->version($proceso);

        $this->editar($proceso, $this->datos())->assertOk()->assertJsonPath('actualizado', $version);
    }

    public function test_un_cambio_de_estado_desactualizado_se_rechaza(): void
    {
        $proceso = $this->proceso();
        $abierto = $this->version($proceso);
        $this->editar($proceso, $this->datos(['objeto' => 'Otro objeto']))->assertOk();

        $this->cambiarEstado($proceso, ['estado' => 'publicado', 'actualizado' => $abierto])
            ->assertStatus(409)
            ->assertJsonPath('mensaje', 'Otra persona cambió este proceso mientras lo editabas. Ciérralo y vuelve a abrirlo para ver lo último.');
        $this->assertSame('preparacion', $proceso->refresh()->estado);
        $this->assertCount(2, $this->eventos($proceso));

        $this->cambiarEstado($proceso, ['estado' => 'publicado'])->assertOk()->assertJsonPath('estado', 'publicado');
    }

    public function test_la_version_es_obligatoria_y_debe_ser_una_fecha(): void
    {
        $proceso = $this->proceso();
        $falta = 'Falta la versión del proceso que estabas editando. Ciérralo y vuelve a abrirlo.';

        $this->como()->putJson($this->urlProceso($proceso), $this->datos(['objeto' => 'Otro']))->assertStatus(422)
            ->assertJsonValidationErrors(['actualizado' => $falta]);
        $this->como()->postJson($this->urlProceso($proceso, '/estado'), ['estado' => 'publicado'])->assertStatus(422)
            ->assertJsonValidationErrors(['actualizado' => $falta]);
        $this->editar($proceso, $this->datos(['objeto' => 'Otro', 'actualizado' => 'ayer no']))->assertStatus(422)
            ->assertJsonValidationErrors(['actualizado' => 'La versión del proceso no es válida. Ciérralo y vuelve a abrirlo.']);

        $this->assertCount(1, $this->eventos($proceso));
    }

    public function test_la_version_se_compara_en_cualquier_zona_horaria(): void
    {
        $proceso = $this->proceso();
        $enUtc = $proceso->fresh()->updated_at->copy()->utc()->format('Y-m-d\TH:i:s\Z');

        $this->editar($proceso, $this->datos(['objeto' => 'Otro', 'actualizado' => $enUtc]))->assertOk();
    }

    public function test_un_proceso_sin_fechas_se_edita_con_version_null(): void
    {
        // Un proceso anterior a las fechas: presentar() da `actualizado` null.
        $proceso = $this->proceso();
        DB::table('procesos_licitacion')->where('id', $proceso->id)->update(['created_at' => null, 'updated_at' => null]);
        $this->assertNull($this->como(Rol::Consulta)->getJson($this->urlProyecto())->json('procesos.0.actualizado'));

        $this->editar($proceso, $this->datos(['objeto' => 'Otro', 'actualizado' => now()->toIso8601String()]))->assertStatus(409);
        $this->editar($proceso, $this->datos(['objeto' => 'Otro', 'actualizado' => null]))->assertOk()
            ->assertJsonPath('objeto', 'Otro');
        $this->assertNotNull($this->version($proceso));
    }

    // ------------------------------------------------------ catálogos inactivos

    public function test_al_crear_la_modalidad_y_la_entidad_deben_estar_activas(): void
    {
        $this->modalidad('minima_cuantia')->update(['activo' => false]);
        $this->entidad->update(['activo' => false]);

        $this->crear([
            'modalidad_id' => $this->modalidad('minima_cuantia')->id,
            'entidad_contratante_id' => $this->entidad->id,
        ])->assertStatus(422)->assertJsonValidationErrors([
            'modalidad_id' => 'La modalidad elegida está inactiva: elige otra.',
            'entidad_contratante_id' => 'La entidad elegida está inactiva: elige otra.',
        ]);

        $this->assertSame(0, ProcesoLicitacion::count());
    }

    public function test_al_editar_se_conservan_la_modalidad_y_la_entidad_inactivas_que_ya_tiene(): void
    {
        $proceso = $this->proceso(['entidad_contratante_id' => $this->entidad->id]);
        $this->modalidad('licitacion_publica')->update(['activo' => false]);
        $this->entidad->update(['activo' => false]);

        $this->editar($proceso, $this->datos([
            'objeto' => 'Otro objeto', 'entidad_contratante_id' => $this->entidad->id,
        ]))->assertOk()
            ->assertJsonPath('objeto', 'Otro objeto')
            ->assertJsonPath('modalidad.activo', false)
            ->assertJsonPath('entidad_contratante.id', $this->entidad->id);
    }

    public function test_al_editar_no_se_cambia_a_una_modalidad_o_entidad_inactiva(): void
    {
        $proceso = $this->proceso();
        $this->modalidad('regimen_especial')->update(['activo' => false]);
        $this->entidad->update(['activo' => false]);

        $this->editar($proceso, $this->datos([
            'modalidad_id' => $this->modalidad('regimen_especial')->id,
            'entidad_contratante_id' => $this->entidad->id,
        ]))->assertStatus(422)->assertJsonValidationErrors([
            'modalidad_id' => 'La modalidad elegida está inactiva: elige otra.',
            'entidad_contratante_id' => 'La entidad elegida está inactiva: elige otra.',
        ]);

        $this->assertSame($this->modalidad('licitacion_publica')->id, (int) $proceso->refresh()->modalidad);
        $this->assertCount(1, $this->eventos($proceso));
    }

    public function test_modalidad_o_entidad_inexistente(): void
    {
        $this->crear(['modalidad_id' => 9999, 'entidad_contratante_id' => 9999])->assertStatus(422)
            ->assertJsonValidationErrors([
                'modalidad_id' => 'Elige una modalidad de la lista.',
                'entidad_contratante_id' => 'Elige una entidad de la lista.',
            ]);
    }

    // ------------------------------------------------------------ validaciones

    public static function invalidos(): array
    {
        return [
            'sin objeto' => [['objeto' => null], 'objeto', 'Escribe el objeto del proceso.'],
            'objeto en blanco' => [['objeto' => '   '], 'objeto', 'Escribe el objeto del proceso.'],
            'objeto no texto' => [['objeto' => ['a']], 'objeto', 'Revisa el objeto: debe ser un texto.'],
            'objeto largo' => [['objeto' => str_repeat('a', 5001)], 'objeto', 'El objeto admite hasta 5000 caracteres.'],
            'sin modalidad' => [['modalidad_id' => null], 'modalidad_id', 'Elige la modalidad del proceso.'],
            'modalidad texto' => [['modalidad_id' => '1'], 'modalidad_id', 'Revisa la modalidad: debe ser un número entero.'],
            'modalidad booleana' => [['modalidad_id' => true], 'modalidad_id', 'Revisa la modalidad: debe ser un número entero.'],
            'sin plataforma' => [['plataforma' => null], 'plataforma', 'Elige la plataforma del proceso.'],
            'plataforma ajena' => [['plataforma' => 'colombia_compra'], 'plataforma', 'Elige una plataforma de la lista.'],
            'código largo' => [['codigo_proceso' => str_repeat('a', 101)], 'codigo_proceso', 'El número del proceso admite hasta 100 caracteres.'],
            'enlace sin http' => [['url_proceso' => 'secop.gov.co/proceso'], 'url_proceso', 'Escribe un enlace completo que empiece por http:// o https://.'],
            'enlace ftp' => [['url_proceso' => 'ftp://secop.gov.co/proceso'], 'url_proceso', 'Escribe un enlace completo que empiece por http:// o https://.'],
            'enlace largo' => [['url_proceso' => 'https://secop.gov.co/' . str_repeat('a', 480)], 'url_proceso', 'El enlace admite hasta 500 caracteres.'],
            'entidad texto' => [['entidad_contratante_id' => '1'], 'entidad_contratante_id', 'Revisa la entidad contratante: debe ser un número entero.'],
            'monto no numérico' => [['monto' => 'mil'], 'monto', 'Escribe el valor como un número.'],
            'monto negativo' => [['monto' => -1], 'monto', 'El valor no puede ser negativo.'],
            'monto con tres decimales' => [['monto' => '10.555'], 'monto', 'El valor admite hasta 2 decimales.'],
            'monto enorme' => [['monto' => '10000000000000'], 'monto', 'El valor admite hasta 13 cifras enteras y 2 decimales.'],
        ];
    }

    #[DataProvider('invalidos')]
    public function test_valida_al_crear_con_mensajes_en_espanol(array $datos, string $campo, string $mensaje): void
    {
        $this->crear($datos)->assertStatus(422)->assertJsonValidationErrors([$campo => $mensaje]);

        $this->assertSame(0, ProcesoLicitacion::count());
    }

    #[DataProvider('invalidos')]
    public function test_valida_al_editar_con_los_mismos_mensajes(array $datos, string $campo, string $mensaje): void
    {
        $proceso = $this->proceso();

        $this->editar($proceso, $this->datos($datos))
            ->assertStatus(422)->assertJsonValidationErrors([$campo => $mensaje]);

        $this->assertCount(1, $this->eventos($proceso));
    }

    // ----------------------------------------------------------------- estado

    public function test_cambia_el_estado_y_lo_registra_con_la_observacion(): void
    {
        $proceso = $this->proceso();

        $this->cambiarEstado($proceso, [
            'estado' => 'publicado', 'observacion' => '  Publicado en SECOP II el 17 de noviembre  ',
        ])->assertOk()
            ->assertJsonPath('id', $proceso->id)
            ->assertJsonPath('estado', 'publicado')
            ->assertJsonPath('modalidad.clave', 'licitacion_publica');

        $this->cambiarEstado($proceso, ['estado' => 'evaluacion'])->assertOk();

        $eventos = $this->eventos($proceso);
        $this->assertSame(['proceso_estado_cambiado', ['estado' => 'preparacion'], [
            'estado' => 'publicado', 'observacion' => 'Publicado en SECOP II el 17 de noviembre',
        ]], $eventos[1]);
        $this->assertSame(['proceso_estado_cambiado', ['estado' => 'publicado'], ['estado' => 'evaluacion', 'observacion' => null]], $eventos[2]);
        $this->assertSame('evaluacion', $proceso->refresh()->estado);
    }

    public function test_no_hay_transiciones_prohibidas(): void
    {
        $proceso = $this->proceso();

        foreach (['cancelado', 'preparacion', 'celebrado', 'desierto'] as $estado) {
            $this->cambiarEstado($proceso, ['estado' => $estado])
                ->assertOk()->assertJsonPath('estado', $estado);
        }
    }

    public function test_el_mismo_estado_se_rechaza(): void
    {
        $proceso = $this->proceso();

        $this->cambiarEstado($proceso, ['estado' => 'preparacion'])
            ->assertStatus(422)->assertJsonPath('mensaje', 'El proceso ya está en ese estado.');

        $this->assertCount(1, $this->eventos($proceso));
    }

    public function test_valida_el_estado_y_la_observacion(): void
    {
        $proceso = $this->proceso();

        $this->cambiarEstado($proceso, [])->assertStatus(422)
            ->assertJsonValidationErrors(['estado' => 'Elige el estado del proceso.']);
        $this->cambiarEstado($proceso, ['estado' => 'archivado'])->assertStatus(422)
            ->assertJsonValidationErrors(['estado' => 'Elige un estado de la lista.']);
        $this->cambiarEstado($proceso, ['estado' => 'publicado', 'observacion' => str_repeat('a', 2001)])->assertStatus(422)
            ->assertJsonValidationErrors(['observacion' => 'La observación admite hasta 2000 caracteres.']);

        $this->assertSame('preparacion', $proceso->refresh()->estado);
    }

    // --------------------------------------------------------------- eliminar

    public function test_elimina_un_proceso_sin_contratos_y_deja_en_el_historial_que_era(): void
    {
        $proceso = $this->proceso([
            'codigo_proceso' => 'LP-001', 'entidad_contratante_id' => $this->entidad->id, 'monto' => 800,
        ]);
        $this->cambiarEstado($proceso, ['estado' => 'cancelado'])->assertOk();

        $this->como()->deleteJson($this->urlProceso($proceso))->assertOk()
            ->assertJsonPath('mensaje', 'Proceso eliminado.');

        $this->assertNull(ProcesoLicitacion::find($proceso->id));
        $this->assertSame(['proceso_eliminado', [
            'proyecto_id' => $this->proyecto->id,
            'objeto' => 'Construcción del acueducto veredal',
            'modalidad_id' => $this->modalidad('licitacion_publica')->id,
            'modalidad' => 'Licitación pública',
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-001',
            'url_proceso' => null,
            'entidad_contratante_id' => $this->entidad->id,
            'entidad_contratante' => 'Gobernación del Cesar',
            'monto' => '800.00',
            'estado' => 'cancelado',
        ], null], $this->eventos($proceso)[2]);
    }

    public function test_no_elimina_un_proceso_con_contratos(): void
    {
        $proceso = $this->proceso();
        Contrato::create(['proyecto' => $this->proyecto->id, 'proceso_licitacion' => $proceso->id]);

        $this->como()->deleteJson($this->urlProceso($proceso))->assertStatus(422)
            ->assertJsonPath('mensaje', 'El proceso tiene 1 contrato vinculado: no se puede eliminar.');

        Contrato::create(['proyecto' => $this->proyecto->id, 'proceso_licitacion' => $proceso->id]);
        $this->como()->deleteJson($this->urlProceso($proceso))->assertStatus(422)
            ->assertJsonPath('mensaje', 'El proceso tiene 2 contratos vinculados: no se puede eliminar.');

        $this->assertNotNull(ProcesoLicitacion::find($proceso->id));
        $this->assertCount(1, $this->eventos($proceso));
    }

    // -------------------------------------------------------------- historial

    public function test_el_historial_de_los_procesos_no_aparece_en_el_de_ningun_expediente(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        $expediente = app(ExpedienteService::class)->crear($this->proyecto, ['fase_sgr' => 3], User::factory()->create(['rol' => Rol::Gestor]));
        $historial = fn () => $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$expediente->id}/historial")->assertOk()->json();
        $antes = $historial();
        $this->assertGreaterThan(0, $antes['total']);

        // Un proceso del mismo proyecto con cada evento, y otro que queda; sus ids pueden coincidir
        // con los del expediente.
        $proceso = $this->proceso();
        $this->editar($proceso, $this->datos(['objeto' => 'Otro objeto']))->assertOk();
        $this->cambiarEstado($proceso, ['estado' => 'cancelado'])->assertOk();
        $this->como()->deleteJson($this->urlProceso($proceso))->assertOk();
        $this->proceso();

        $despues = $historial();
        $this->assertSame(5, DB::table('checklist_historial')->where('entidad_tipo', 'proceso')->whereNull('expediente_id')->count());
        $this->assertSame($antes['total'], $despues['total']);
        $this->assertSame($antes['entradas'], $despues['entradas']);
        $this->assertNotContains('proceso', array_column($despues['entradas'], 'entidad'));
    }

    // ---------------------------------------------------------- permisos y 404

    public function test_consulta_lee_pero_no_escribe(): void
    {
        $proceso = $this->proceso();

        $this->como(Rol::Consulta)->getJson($this->urlProyecto())->assertOk()->assertJsonCount(1, 'procesos');
        $this->como(Rol::Consulta)->postJson($this->urlProyecto(), $this->datos())->assertForbidden();
        $this->como(Rol::Consulta)->putJson($this->urlProceso($proceso), $this->datos(['objeto' => 'Otro']))->assertForbidden();
        $this->como(Rol::Consulta)->postJson($this->urlProceso($proceso, '/estado'), ['estado' => 'publicado'])->assertForbidden();
        $this->como(Rol::Consulta)->deleteJson($this->urlProceso($proceso))->assertForbidden();

        $this->assertSame(1, ProcesoLicitacion::count());
        $this->assertSame('Construcción del acueducto veredal', $proceso->refresh()->objeto);
        $this->assertSame('preparacion', $proceso->estado);
    }

    public function test_un_administrador_tambien_escribe(): void
    {
        $this->como(Rol::Administrador)->postJson($this->urlProyecto(), $this->datos())->assertCreated();
    }

    public function test_sin_sesion_no_hay_acceso(): void
    {
        $this->getJson($this->urlProyecto())->assertUnauthorized();
        $this->postJson($this->urlProyecto(), $this->datos())->assertUnauthorized();
    }

    public function test_proyecto_o_proceso_inexistente_responde_404(): void
    {
        $this->como(Rol::Consulta)->getJson('/GestPro/proyectos/9999/procesos')->assertNotFound();
        $this->como()->postJson('/GestPro/proyectos/9999/procesos', $this->datos())->assertNotFound();
        $this->como()->putJson($this->urlProceso(9999), $this->datos())->assertNotFound();
        $this->como()->postJson($this->urlProceso(9999, '/estado'), ['estado' => 'publicado'])->assertNotFound();
        $this->como()->deleteJson($this->urlProceso(9999))->assertNotFound();

        $this->assertSame(0, DB::table('checklist_historial')->count());
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/ProcesosContratacionApiTest.php`
Expected: FALLAN las 68: las rutas de procesos no existen (405 por el comodín web) y no está el servicio `ProcesosContratacion`.

- [ ] **Paso 3: Implementar**

Crear `app/Exceptions/EdicionDesactualizadaException.php`:

```php
<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Alguien envió un cambio sobre una versión que ya no es la última: otra persona guardó entretanto.
 * Laravel llama a render() automáticamente: responde 409 (conflicto) con un mensaje legible. No es
 * un 422 porque el pedido es válido; el front lo distingue por el código, avisa y recarga.
 */
class EdicionDesactualizadaException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['mensaje' => $this->getMessage()], 409);
    }
}
```

Crear `app/Http/Controllers/ProcesoContratacionController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Services\Contratacion\ProcesosContratacion;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Procesos de contratación de un proyecto.
 * Lectura: cualquier usuario autenticado. Escritura: can:editar-datos.
 */
class ProcesoContratacionController extends Controller
{
    private const MENSAJES = [
        'required' => 'Completa :attribute.',
        'string' => 'Revisa :attribute: debe ser un texto.',
        'integer' => 'Revisa :attribute: debe ser un número entero.',
        'in' => 'Revisa :attribute: no es un valor válido.',
        'max' => 'En :attribute caben hasta :max caracteres.',
        'objeto.required' => 'Escribe el objeto del proceso.',
        'objeto.max' => 'El objeto admite hasta 5000 caracteres.',
        'modalidad_id.required' => 'Elige la modalidad del proceso.',
        'plataforma.required' => 'Elige la plataforma del proceso.',
        'plataforma.in' => 'Elige una plataforma de la lista.',
        'codigo_proceso.max' => 'El número del proceso admite hasta 100 caracteres.',
        'url_proceso.url' => 'Escribe un enlace completo que empiece por http:// o https://.',
        'url_proceso.max' => 'El enlace admite hasta 500 caracteres.',
        'monto.numeric' => 'Escribe el valor como un número.',
        'monto.min' => 'El valor no puede ser negativo.',
        'monto.decimal' => 'El valor admite hasta 2 decimales.',
        'estado.required' => 'Elige el estado del proceso.',
        'estado.in' => 'Elige un estado de la lista.',
        'observacion.max' => 'La observación admite hasta 2000 caracteres.',
        'actualizado.present' => 'Falta la versión del proceso que estabas editando. Ciérralo y vuelve a abrirlo.',
        'actualizado.date' => 'La versión del proceso no es válida. Ciérralo y vuelve a abrirlo.',
    ];

    private const ATRIBUTOS = [
        'objeto' => 'el objeto',
        'modalidad_id' => 'la modalidad',
        'plataforma' => 'la plataforma',
        'codigo_proceso' => 'el número del proceso',
        'url_proceso' => 'el enlace',
        'entidad_contratante_id' => 'la entidad contratante',
        'monto' => 'el valor',
        'estado' => 'el estado',
        'observacion' => 'la observación',
        'actualizado' => 'la versión del proceso',
    ];

    // La columna es decimal(15, 2): 13 cifras enteras y 2 decimales.
    private const CIFRAS_ENTERAS = 13;

    // El `actualizado` del proceso que tenía el formulario: siempre presente; null solo para un
    // proceso sin fechas, anterior a ellas. Con otro valor, el servicio responde 409.
    private const VERSION = ['present', 'nullable', 'bail', 'string', 'date'];

    public function __construct(private readonly ProcesosContratacion $procesos)
    {
    }

    public function index(Proyecto $proyecto): JsonResponse
    {
        return response()->json([
            'proyecto' => ['id' => $proyecto->id, 'nombre' => $proyecto->nombre],
            'procesos' => $this->procesos->deProyecto($proyecto),
        ]);
    }

    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $proceso = $this->procesos->crear($proyecto, $this->validar($request), $request->user());

        return response()->json($this->procesos->presentar($proceso), 201);
    }

    public function update(Request $request, ProcesoLicitacion $proceso): JsonResponse
    {
        $datos = $this->validar($request, ['actualizado' => self::VERSION]);
        $proceso = $this->procesos->editar($proceso, $datos, $datos['actualizado'], $request->user());

        return response()->json($this->procesos->presentar($proceso));
    }

    public function cambiarEstado(Request $request, ProcesoLicitacion $proceso): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['bail', 'required', 'string', Rule::in(ProcesoLicitacion::ESTADOS)],
            'observacion' => ['sometimes', 'nullable', 'bail', 'string', 'max:2000'],
            'actualizado' => self::VERSION,
        ], self::MENSAJES, self::ATRIBUTOS);
        $proceso = $this->procesos->cambiarEstado(
            $proceso, $datos['estado'], $datos['observacion'] ?? null, $datos['actualizado'], $request->user()
        );

        return response()->json($this->procesos->presentar($proceso));
    }

    public function destroy(Request $request, ProcesoLicitacion $proceso): JsonResponse
    {
        $this->procesos->eliminar($proceso, $request->user());

        return response()->json(['mensaje' => 'Proceso eliminado.']);
    }

    /**
     * Al editar, los opcionales que no vienen en el pedido no cambian y con null se borran; los
     * obligatorios siempre se envían. El estado no se recibe aquí: tiene su propia ruta. `$mas`
     * agrega reglas, como la versión al editar.
     */
    private function validar(Request $request, array $mas = []): array
    {
        // Enteros de verdad: `integer` admitiría true como 1 o "1".
        $entero = fn (string $atributo, mixed $valor, Closure $falla) => is_int($valor)
            || $falla('Revisa :attribute: debe ser un número entero.');
        $cifras = fn (string $atributo, mixed $valor, Closure $falla) => strlen(ltrim(explode('.', ltrim((string) $valor, '+-'))[0], '0')) <= self::CIFRAS_ENTERAS
            || $falla('El valor admite hasta ' . self::CIFRAS_ENTERAS . ' cifras enteras y 2 decimales.');

        return $request->validate([
            'objeto' => ['bail', 'required', 'string', 'max:5000'],
            'modalidad_id' => ['bail', 'required', 'integer', $entero],
            'plataforma' => ['bail', 'required', 'string', Rule::in(ProcesoLicitacion::PLATAFORMAS)],
            'codigo_proceso' => ['sometimes', 'nullable', 'bail', 'string', 'max:100'],
            'url_proceso' => ['sometimes', 'nullable', 'bail', 'string', 'url:http,https', 'max:500'],
            'entidad_contratante_id' => ['sometimes', 'nullable', 'bail', 'integer', $entero],
            'monto' => ['sometimes', 'nullable', 'bail', 'numeric', 'min:0', 'decimal:0,2', $cifras],
            ...$mas,
        ], self::MENSAJES, self::ATRIBUTOS);
    }
}
```

Crear `app/Services/Contratacion/ProcesosContratacion.php`:

```php
<?php

namespace App\Services\Contratacion;

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
use Illuminate\Validation\ValidationException;

/**
 * Procesos de contratación de un proyecto (la obra, la interventoría...).
 *
 * Reglas:
 * - El objeto, la modalidad y la plataforma son obligatorios; el número, el enlace, la entidad
 *   contratante y el valor, opcionales.
 * - La modalidad y la entidad deben existir y estar activas. Al editar se acepta la que el
 *   proceso ya tiene aunque se haya desactivado: los catálogos se desactivan, no se eliminan.
 * - Un proceso nuevo empieza en preparación. El estado solo cambia con cambiarEstado(): lo
 *   cambia una persona, sin transiciones prohibidas (el checklist solo avisa), pero no al mismo
 *   estado.
 * - No se elimina un proceso con contratos vinculados (contratos.proceso_licitacion).
 * - Todo queda en checklist_historial (entidad_tipo = proceso, sin expediente): al editar, solo
 *   lo que cambió; al eliminar, los datos para saber qué era.
 * - Los cambios sobre un proceso bloquean su fila: dos ediciones no se pisan en el historial.
 * - Editar y cambiar el estado traen `actualizado`, el de presentar() cuando se abrió el
 *   formulario: si ya no es el de la fila, otra persona lo cambió entretanto y se rechaza (409)
 *   en vez de deshacer en silencio lo suyo. Cada cambio adelanta updated_at al menos un segundo.
 */
final class ProcesosContratacion
{
    /** Campo de la API => columna del proceso. */
    private const CAMPOS = [
        'objeto' => 'objeto',
        'modalidad_id' => 'modalidad',
        'plataforma' => 'plataforma',
        'codigo_proceso' => 'codigo_proceso',
        'url_proceso' => 'url_proceso',
        'entidad_contratante_id' => 'entidad_contratante',
        'monto' => 'monto',
    ];

    private const TEXTOS = ['objeto', 'codigo_proceso', 'url_proceso'];

    private const OBLIGATORIOS = [
        'objeto' => 'Escribe el objeto del proceso.',
        'modalidad_id' => 'Elige la modalidad del proceso.',
        'plataforma' => 'Elige la plataforma del proceso.',
    ];

    public const DESACTUALIZADO = 'Otra persona cambió este proceso mientras lo editabas. Ciérralo y vuelve a abrirlo para ver lo último.';

    private const RELACIONES = ['modalidadRel:id,nombre,clave,activo', 'entidadContratante:id,nombre', 'usuarioCreo:id,name'];

    public function __construct(private readonly HistorialChecklist $historial)
    {
    }

    /** Los procesos del proyecto en el orden en que se crearon, con la forma de presentar(). */
    public function deProyecto(Proyecto $proyecto): array
    {
        return ProcesoLicitacion::query()
            ->where('proyecto', $proyecto->id)
            ->with(self::RELACIONES)
            ->withCount('contratos')
            ->orderBy('id')
            ->get()
            ->map(fn (ProcesoLicitacion $proceso) => $this->forma($proceso))
            ->all();
    }

    public function presentar(ProcesoLicitacion $proceso): array
    {
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
                'estado' => ProcesoLicitacion::PREPARACION,
                'usuario_creo_id' => $usuario?->id,
            ]);
            $this->historial->registrarProceso($proceso, 'proceso_creado', [], [
                'proyecto_id' => $proyecto->id,
                ...$this->valoresDe($proceso),
                'estado' => $proceso->estado,
            ], $usuario);

            return $proceso;
        });
    }

    /**
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
                $actual->update($this->columnas($cambios));
                $this->historial->registrarProceso($actual, 'proceso_editado', array_intersect_key($antes, $cambios), $cambios, $usuario);
            }

            return $actual;
        });
    }

    /** `$actualizado` es el `actualizado` del proceso que tenía el formulario. */
    public function cambiarEstado(ProcesoLicitacion $proceso, string $estado, ?string $observacion, ?string $actualizado, ?User $usuario): ProcesoLicitacion
    {
        $observacion = trim((string) $observacion);
        $observacion = $observacion !== '' ? $observacion : null;

        return DB::transaction(function () use ($proceso, $estado, $observacion, $actualizado, $usuario) {
            // Primero la versión: con un formulario viejo, "ya está en ese estado" engañaría.
            $actual = $this->bloquearVigente($proceso, $actualizado);
            $anterior = $actual->estado;
            if ($anterior === $estado) {
                throw new ReglaNegocioException('El proceso ya está en ese estado.');
            }

            $this->adelantarActualizado($actual);
            $actual->update(['estado' => $estado]);
            $this->historial->registrarProceso($actual, 'proceso_estado_cambiado', ['estado' => $anterior], [
                'estado' => $estado,
                'observacion' => $observacion,
            ], $usuario);

            return $actual;
        });
    }

    public function eliminar(ProcesoLicitacion $proceso, ?User $usuario): void
    {
        DB::transaction(function () use ($proceso, $usuario) {
            // Con la fila bloqueada, un contrato que se vincule a la vez espera y luego no
            // encuentra el proceso, o termina antes y aquí se cuenta.
            $actual = $this->bloquear($proceso);
            $contratos = $actual->contratos()->count();
            if ($contratos > 0) {
                throw new ReglaNegocioException($contratos === 1
                    ? 'El proceso tiene 1 contrato vinculado: no se puede eliminar.'
                    : "El proceso tiene {$contratos} contratos vinculados: no se puede eliminar.");
            }

            // El registro sobrevive al proceso: con el proyecto y los nombres de los catálogos.
            $actual->load(['modalidadRel:id,nombre', 'entidadContratante:id,nombre']);
            $datos = $this->valoresDe($actual);
            $this->historial->registrarProceso($actual, 'proceso_eliminado', [
                'proyecto_id' => $actual->proyecto,
                'objeto' => $datos['objeto'],
                'modalidad_id' => $datos['modalidad_id'],
                'modalidad' => $actual->modalidadRel?->nombre,
                'plataforma' => $datos['plataforma'],
                'codigo_proceso' => $datos['codigo_proceso'],
                'url_proceso' => $datos['url_proceso'],
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
     */
    private function valores(array $datos): array
    {
        $valores = array_intersect_key(array_replace(self::CAMPOS, $datos), $datos, self::CAMPOS);
        foreach (self::TEXTOS as $campo) {
            if (array_key_exists($campo, $valores) && $valores[$campo] !== null) {
                $texto = trim((string) $valores[$campo]);
                $valores[$campo] = $texto !== '' ? $texto : null;
            }
        }
        if (isset($valores['monto'])) {
            $valores['monto'] = (string) BigDecimal::of((string) $valores['monto'])->toScale(2);
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

    /** La modalidad y la entidad del pedido existen y están activas, o son las que el proceso ya tiene. */
    private function exigirCatalogos(array $valores, ?ProcesoLicitacion $actual): void
    {
        $errores = array_filter([
            'modalidad_id' => $this->errorCatalogo(Modalidad::class, $valores['modalidad_id'] ?? null, $actual?->modalidad, 'modalidad'),
            'entidad_contratante_id' => $this->errorCatalogo(Entidad::class, $valores['entidad_contratante_id'] ?? null, $actual?->entidad_contratante, 'entidad'),
        ]);
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
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
    {
        $columnas = [];
        foreach ($valores as $campo => $valor) {
            $columnas[self::CAMPOS[$campo]] = $valor;
        }

        return $columnas;
    }

    private function forma(ProcesoLicitacion $proceso): array
    {
        $modalidad = $proceso->modalidadRel;
        $entidad = $proceso->entidadContratante;

        return [
            'id' => $proceso->id,
            'objeto' => $proceso->objeto,
            'modalidad' => $modalidad ? [
                'id' => $modalidad->id,
                'nombre' => $modalidad->nombre,
                'clave' => $modalidad->clave,
                'activo' => $modalidad->activo,
            ] : null,
            'plataforma' => $proceso->plataforma,
            'codigo_proceso' => $proceso->codigo_proceso,
            'url_proceso' => $proceso->url_proceso,
            'entidad_contratante' => $entidad ? ['id' => $entidad->id, 'nombre' => $entidad->nombre] : null,
            'monto' => $proceso->monto,
            'estado' => $proceso->estado,
            'contratos' => (int) $proceso->contratos_count,
            'creado' => $proceso->created_at?->toIso8601String(),
            'actualizado' => $proceso->updated_at?->toIso8601String(),
            'usuario_creo' => $proceso->usuarioCreo?->name,
        ];
    }
}
```

Crear `routes/contratacion.php`:

```php
<?php

/*
 * Módulo de contratación.
 * Se incluye desde routes/api.php DENTRO del grupo auth:api, así que todas
 * estas rutas exigen JWT; aquí solo se agrega el permiso de escritura.
 */

use App\Http\Controllers\ProcesoContratacionController;
use Illuminate\Support\Facades\Route;

// Lectura: cualquier usuario autenticado.
Route::get('/proyectos/{proyecto}/procesos', [ProcesoContratacionController::class, 'index'])
    ->name('procesos.index');

// Procesos de contratación: Administrador o Gestor.
Route::middleware('can:editar-datos')->group(function () {
    Route::post('/proyectos/{proyecto}/procesos', [ProcesoContratacionController::class, 'store'])
        ->name('procesos.store');
    Route::put('/procesos/{proceso}', [ProcesoContratacionController::class, 'update'])
        ->name('procesos.update');
    Route::post('/procesos/{proceso}/estado', [ProcesoContratacionController::class, 'cambiarEstado'])
        ->name('procesos.estado');
    Route::delete('/procesos/{proceso}', [ProcesoContratacionController::class, 'destroy'])
        ->name('procesos.destroy');
});
```

En `app/Models/ProcesoLicitacion.php`, reemplazar (1 de 1):

```php
        'estado', 'entidad_contratante', 'tipo_proponente', 'entidad_proponente', 'monto', 'usuario_creo_id',
    ];
    protected $attributes = ['estado' => self::PREPARACION];
    protected $casts = ['monto' => 'decimal:2', 'usuario_creo_id' => 'integer'];

    // proyectoRel()/modalidadRel(): both FK columns (proyecto, modalidad) are single words that
    // would collide with the natural relation name (see Task 4's note on Estado::faseRel()).
```

por:

```php
        'estado', 'entidad_contratante', 'tipo_proponente', 'entidad_proponente', 'monto', 'usuario_creo_id',
    ];
    protected $attributes = ['estado' => self::PREPARACION];
    protected $casts = [
        'proyecto' => 'integer',
        'modalidad' => 'integer',
        'entidad_contratante' => 'integer',
        'monto' => 'decimal:2',
        'usuario_creo_id' => 'integer',
    ];

    // proyectoRel()/modalidadRel(): both FK columns (proyecto, modalidad) are single words that
    // would collide with the natural relation name (see Task 4's note on Estado::faseRel()).
```

En `app/Services/Viabilizacion/HistorialChecklist.php`, reemplazar (1 de 3):

```php
namespace App\Services\Viabilizacion;

use App\Models\ChecklistHistorial;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\User;
```

por:

```php
namespace App\Services\Viabilizacion;

use App\Models\ChecklistHistorial;
use App\Models\ProcesoLicitacion;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\User;
```

En `app/Services/Viabilizacion/HistorialChecklist.php`, reemplazar (2 de 3):

```php
{
    public const REQUISITO = 'requisito';
    public const EXPEDIENTE = 'expediente';

    /** Entradas por página del historial del expediente. */
    public const POR_PAGINA = 50;
```

por:

```php
{
    public const REQUISITO = 'requisito';
    public const EXPEDIENTE = 'expediente';
    public const PROCESO = 'proceso';

    /** Entradas por página del historial del expediente. */
    public const POR_PAGINA = 50;
```

En `app/Services/Viabilizacion/HistorialChecklist.php`, reemplazar (3 de 3):

```php
        $this->registrar(self::EXPEDIENTE, $expediente->id, $expediente->id, $evento, $antes, $despues, $usuario);
    }

    /** Historial de una fila, del más reciente al más antiguo. */
    public function deFila(ProyectoRequisito $fila): array
    {
```

por:

```php
        $this->registrar(self::EXPEDIENTE, $expediente->id, $expediente->id, $evento, $antes, $despues, $usuario);
    }

    /** Un proceso de contratación no pertenece a un expediente: su entrada va sin expediente_id. */
    public function registrarProceso(ProcesoLicitacion $proceso, string $evento, array $antes, array $despues, ?User $usuario): void
    {
        $this->registrar(self::PROCESO, $proceso->id, null, $evento, $antes, $despues, $usuario);
    }

    /** Historial de una fila, del más reciente al más antiguo. */
    public function deFila(ProyectoRequisito $fila): array
    {
```

En `routes/api.php`, reemplazar (1 de 1):

```php

    // Expedientes de viabilización SGR (heredan auth:api de este grupo).
    require __DIR__ . '/viabilizacion.php';
});
```

por:

```php

    // Expedientes de viabilización SGR (heredan auth:api de este grupo).
    require __DIR__ . '/viabilizacion.php';

    // Procesos de contratación (heredan auth:api de este grupo).
    require __DIR__ . '/contratacion.php';
});
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/ProcesosContratacionApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 797 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Exceptions/EdicionDesactualizadaException.php app/Http/Controllers/ProcesoContratacionController.php app/Services/Contratacion/ProcesosContratacion.php routes/contratacion.php tests/Feature/Contratacion/ProcesosContratacionApiTest.php app/Models/ProcesoLicitacion.php app/Services/Viabilizacion/HistorialChecklist.php routes/api.php
git commit -m "feat: procurement processes API"
```

---

### Tarea 6: Parámetros: Modalidades y Etapas de contratación

Dos secciones nuevas de Parámetros, en componentes propios.

**Files:**
- Create: `resources/css/Contratacion.css`
- Create: `resources/js/components/CatalogoEtapasContratacion.jsx`
- Create: `resources/js/components/CatalogoModalidades.jsx`
- Create: `resources/js/components/FormularioCatalogo.jsx`
- Create: `resources/js/utils/contratacion.js`
- Test (create): `resources/js/utils/contratacion.test.js`
- Modify: `resources/js/app.jsx`
- Modify: `resources/js/components/Parametros.jsx`
- Modify: `resources/js/utils/iconosNavegacion.js`
- Modify: `resources/js/utils/navegacion.js`
- Test: `resources/js/utils/navegacion.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `resources/js/utils/contratacion.test.js`:

```js
import { describe, it, expect } from 'vitest';
import {
    CATALOGO_VACIO,
    REGLAS_ETAPA,
    REGLAS_MODALIDAD,
    datosCatalogo,
    erroresPorCampo,
    formularioCatalogo,
    idsTrasMover,
    ordenarCatalogo,
    puedeAdministrarCatalogos,
    textoEstadoCatalogo,
    validarCatalogo,
} from './contratacion';

const modalidades = [
    { id: 1, nombre: 'Licitación pública', clave: 'licitacion_publica', orden: 1, activo: true },
    { id: 2, nombre: 'Mínima cuantía', clave: 'minima_cuantia', orden: 5, activo: true },
];

describe('puedeAdministrarCatalogos', () => {
    it('solo un Administrador modifica modalidades y etapas', () => {
        expect(puedeAdministrarCatalogos({ rol: 'Administrador' })).toBe(true);
        expect(puedeAdministrarCatalogos({ rol: 'Gestor' })).toBe(false);
        expect(puedeAdministrarCatalogos({ rol: 'Consulta' })).toBe(false);
        expect(puedeAdministrarCatalogos(null)).toBe(false);
    });
});

describe('formularioCatalogo', () => {
    it('parte vacío o de lo que ya tiene el elemento, sin null en los campos', () => {
        expect(formularioCatalogo(null)).toEqual(CATALOGO_VACIO);
        expect(formularioCatalogo({ id: 3, nombre: 'Adendas', descripcion: null })).toEqual({ nombre: 'Adendas', descripcion: '' });
    });
});

describe('validarCatalogo', () => {
    it('exige el nombre', () => {
        expect(validarCatalogo({ nombre: '   ' }, modalidades, undefined, REGLAS_MODALIDAD)).toEqual({ nombre: 'Escribe el nombre de la modalidad.' });
        expect(validarCatalogo({ nombre: '' }, [], undefined, REGLAS_ETAPA)).toEqual({ nombre: 'Escribe el nombre de la etapa.' });
    });

    it('no repite un nombre sin contar mayúsculas, tildes ni espacios', () => {
        expect(validarCatalogo({ nombre: ' minima CUANTIA ' }, modalidades, undefined, REGLAS_MODALIDAD))
            .toEqual({ nombre: 'Ya existe una modalidad con ese nombre.' });
    });

    it('al editar, el nombre propio no cuenta como repetido', () => {
        expect(validarCatalogo({ nombre: 'Mínima cuantía' }, modalidades, 2, REGLAS_MODALIDAD)).toEqual({});
        expect(validarCatalogo({ nombre: 'Mínima cuantía' }, modalidades, 1, REGLAS_MODALIDAD)).toEqual({ nombre: 'Ya existe una modalidad con ese nombre.' });
    });

    it('aplica los largos del servidor a nombre y descripción', () => {
        expect(validarCatalogo({ nombre: 'x'.repeat(200) }, [], undefined, REGLAS_MODALIDAD)).toEqual({ nombre: 'El nombre admite hasta 199 caracteres.' });
        expect(validarCatalogo({ nombre: 'x'.repeat(199) }, [], undefined, REGLAS_MODALIDAD)).toEqual({});
        expect(validarCatalogo({ nombre: 'Etapa', descripcion: 'd'.repeat(2001) }, [], undefined, REGLAS_ETAPA))
            .toEqual({ descripcion: 'La descripción admite hasta 2000 caracteres.' });
        expect(validarCatalogo({ nombre: 'x'.repeat(121), descripcion: '' }, [], undefined, REGLAS_ETAPA)).toEqual({ nombre: 'El nombre admite hasta 120 caracteres.' });
    });

    it('las modalidades no tienen descripción que validar', () => {
        expect(validarCatalogo({ nombre: 'Otra', descripcion: 'd'.repeat(5000) }, [], undefined, REGLAS_MODALIDAD)).toEqual({});
    });
});

describe('datosCatalogo', () => {
    it('agrega o edita con el nombre recortado', () => {
        expect(datosCatalogo({ nombre: '  Régimen especial ' }, null)).toEqual({ accion: 'Agregar', nombre: 'Régimen especial' });
        expect(datosCatalogo({ nombre: 'Régimen especial' }, { id: 6 })).toEqual({ accion: 'Editar', id: 6, nombre: 'Régimen especial' });
    });

    it('en etapas envía la descripción, vacía como null', () => {
        expect(datosCatalogo({ nombre: 'Adendas', descripcion: '  ' }, { id: 6 }, true)).toEqual({ accion: 'Editar', id: 6, nombre: 'Adendas', descripcion: null });
        expect(datosCatalogo({ nombre: 'Adendas', descripcion: ' Capítulo 9 ' }, null, true)).toEqual({ accion: 'Agregar', nombre: 'Adendas', descripcion: 'Capítulo 9' });
    });
});

describe('ordenarCatalogo', () => {
    it('ordena por orden, luego por nombre y luego por id, sin cambiar la lista recibida', () => {
        const lista = [
            { id: 4, nombre: 'Zeta', orden: 2 },
            { id: 3, nombre: 'Beta', orden: 2 },
            { id: 9, nombre: 'Alfa', orden: 1 },
            { id: 1, nombre: 'Beta', orden: 2 },
        ];
        expect(ordenarCatalogo(lista).map((i) => i.id)).toEqual([9, 1, 3, 4]);
        expect(lista[0].id).toBe(4);
        expect(ordenarCatalogo(null)).toEqual([]);
    });
});

describe('idsTrasMover', () => {
    const etapas = [{ id: 10 }, { id: 20 }, { id: 30 }];

    it('devuelve la lista completa de ids con la etapa movida una posición', () => {
        expect(idsTrasMover(etapas, 1, -1)).toEqual([20, 10, 30]);
        expect(idsTrasMover(etapas, 1, 1)).toEqual([10, 30, 20]);
    });

    it('en los bordes no cambia el orden', () => {
        expect(idsTrasMover(etapas, 0, -1)).toEqual([10, 20, 30]);
        expect(idsTrasMover(etapas, 2, 1)).toEqual([10, 20, 30]);
    });
});

describe('erroresPorCampo', () => {
    const error = (status, data) => ({ response: { status, data } });

    it('reparte el primer mensaje de cada campo del formulario y aparta los demás', () => {
        const respuesta = error(422, { message: 'x', errors: { nombre: ['Ya existe una etapa con ese nombre.', 'Otro'], ids: ['El orden debe incluir todas las etapas.'] } });
        expect(erroresPorCampo(respuesta, ['nombre', 'descripcion'])).toEqual({
            campos: { nombre: 'Ya existe una etapa con ese nombre.' },
            otros: ['El orden debe incluir todas las etapas.'],
        });
    });

    it('acepta un mensaje suelto en lugar de lista', () => {
        expect(erroresPorCampo(error(422, { errors: { nombre: 'Escribe el nombre.' } }), ['nombre'])).toEqual({ campos: { nombre: 'Escribe el nombre.' }, otros: [] });
    });

    it('null si no es un error de validación por campos', () => {
        expect(erroresPorCampo(error(422, { mensaje: 'El proceso ya está en ese estado.' }), ['nombre'])).toBeNull();
        expect(erroresPorCampo(error(403, { message: 'This action is unauthorized.' }), ['nombre'])).toBeNull();
        expect(erroresPorCampo(new Error('red'), ['nombre'])).toBeNull();
    });
});

describe('textoEstadoCatalogo', () => {
    it('modalidades y etapas son femeninas', () => {
        expect(textoEstadoCatalogo(true)).toBe('Activa');
        expect(textoEstadoCatalogo(false)).toBe('Inactiva');
    });
});
```

En `resources/js/utils/navegacion.test.js`, reemplazar (1 de 4):

```js
import { describe, it, expect } from 'vitest';
import { DESTINOS, RUTA_PLANTILLAS, buscarEnPaleta, destinoActivo, destinosPara, puedeVer, seccionDesdeSlug, tituloDeRuta } from './navegacion';

const proyectos = [
    { id: 1, nombre: 'Construcción sede educativa', descripcion_municipio: 'MEDELLÍN' },
```

por:

```js
import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import { DESTINOS, GRUPO, RUTA_PLANTILLAS, SECCIONES_PARAMETROS, buscarEnPaleta, destinoActivo, destinosPara, puedeVer, seccionDesdeSlug, tituloDeRuta } from './navegacion';
import { ICONOS } from './iconosNavegacion';

const proyectos = [
    { id: 1, nombre: 'Construcción sede educativa', descripcion_municipio: 'MEDELLÍN' },
```

En `resources/js/utils/navegacion.test.js`, reemplazar (2 de 4):

```js
        expect(seccionDesdeSlug('nada')).toBeNull();
        expect(seccionDesdeSlug(undefined)).toBeNull();
    });
});

describe('DESTINOS', () => {
    it('lleva a cada catálogo con una sola ruta directa', () => {
        const rutas = DESTINOS.map((d) => d.ruta);

        ['municipios', 'estados', 'fases', 'tipos-evento', 'prioridades', 'responsables', 'entidades', 'fuentes-financiacion', 'sectores'].forEach((slug) => {
            expect(rutas).toContain(`/parametros/${slug}`);
        });
    });
```

por:

```js
        expect(seccionDesdeSlug('nada')).toBeNull();
        expect(seccionDesdeSlug(undefined)).toBeNull();
    });

    it('modalidades y etapas de contratación son catálogos con componente propio', () => {
        expect(seccionDesdeSlug('modalidades')).toMatchObject({ tab: 'modalidades', titulo: 'Modalidades', grupo: GRUPO.CATALOGOS, propia: true });
        expect(seccionDesdeSlug('etapas-contratacion')).toMatchObject({ tab: 'etapas-contratacion', titulo: 'Etapas de contratación', grupo: GRUPO.CATALOGOS, propia: true });
        expect(SECCIONES_PARAMETROS.filter((s) => s.propia).map((s) => s.slug)).toEqual(['modalidades', 'etapas-contratacion']);
    });

    it('cada sección tiene un icono registrado', () => {
        SECCIONES_PARAMETROS.forEach((s) => expect(ICONOS[s.icono]).toBeDefined());
    });
});

describe('secciones en Parametros.jsx', () => {
    // Ruta relativa a este archivo, no al directorio desde donde se lance Vitest.
    const fuente = readFileSync(new URL('../components/Parametros.jsx', import.meta.url), 'utf8');

    // Parametros llama hooks dentro de renderTable: cada sección que lo monta debe pintar una tabla,
    // y cada sección propia, que se pinta sin Parametros, debe tener su componente.
    it('cada sección de tablas llama renderTable y cada sección propia tiene componente', () => {
        const propias = fuente.match(/const SECCIONES_PROPIAS = \{([^}]*)\}/)[1];
        SECCIONES_PARAMETROS.forEach((s) => {
            if (s.propia) expect(propias).toMatch(new RegExp(`^\\s*'?${s.tab}'?:`, 'm'));
            else expect(fuente).toContain(`activeTab === '${s.tab}' && renderTable(`);
        });
    });
});

describe('DESTINOS', () => {
    it('lleva a cada catálogo con una sola ruta directa', () => {
        const rutas = DESTINOS.map((d) => d.ruta);

        ['municipios', 'estados', 'fases', 'tipos-evento', 'prioridades', 'responsables', 'entidades', 'fuentes-financiacion', 'sectores', 'modalidades', 'etapas-contratacion'].forEach((slug) => {
            expect(rutas).toContain(`/parametros/${slug}`);
        });
    });
```

En `resources/js/utils/navegacion.test.js`, reemplazar (3 de 4):

```js
        expect(titulos('estadisticas')).toContain('Estadísticas');
        expect(titulos('alcaldia')).toContain('Entidades');
        expect(titulos('catalogo')).toContain('Municipios');
    });

    it('encuentra proyectos por nombre o municipio y los deja después de los destinos', () => {
```

por:

```js
        expect(titulos('estadisticas')).toContain('Estadísticas');
        expect(titulos('alcaldia')).toContain('Entidades');
        expect(titulos('catalogo')).toContain('Municipios');
        expect(titulos('secop')).toEqual(expect.arrayContaining(['Modalidades', 'Etapas de contratación']));
        expect(titulos('licitación')).toEqual(expect.arrayContaining(['Modalidades', 'Etapas de contratación']));
        expect(titulos('minima cuantia')).toContain('Modalidades');
    });

    it('encuentra proyectos por nombre o municipio y los deja después de los destinos', () => {
```

En `resources/js/utils/navegacion.test.js`, reemplazar (4 de 4):

```js
describe('tituloDeRuta', () => {
    it('da el título de la barra móvil', () => {
        expect(tituloDeRuta('/parametros/tipos-evento', '')).toBe('Tipos de evento');
        expect(tituloDeRuta('/dashboard', '')).toBe('Tablero');
        expect(tituloDeRuta('/viabilizacion/4', '')).toBe('Expedientes');
        expect(tituloDeRuta('/otra', '')).toBe('GestPro');
```

por:

```js
describe('tituloDeRuta', () => {
    it('da el título de la barra móvil', () => {
        expect(tituloDeRuta('/parametros/tipos-evento', '')).toBe('Tipos de evento');
        expect(tituloDeRuta('/parametros/etapas-contratacion', '')).toBe('Etapas de contratación');
        expect(tituloDeRuta('/dashboard', '')).toBe('Tablero');
        expect(tituloDeRuta('/viabilizacion/4', '')).toBe('Expedientes');
        expect(tituloDeRuta('/otra', '')).toBe('GestPro');
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/contratacion.test.js resources/js/utils/navegacion.test.js`
Expected: FALLAN 5 y pasan 16 (y el archivo nuevo no carga): no existe `./contratacion` y la navegación todavía no tiene Modalidades ni Etapas de contratación.

- [ ] **Paso 3: Implementar**

Crear `resources/css/Contratacion.css`:

```css
/* Contratación: catálogos de modalidades y etapas. Usa los tokens --gp-* del armazón y los
   formularios .pl-* de la administración de plantillas. */

.ct-seccion { display: grid; gap: 1rem; min-width: 0; }
.ct-barra { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; justify-content: space-between; }
.ct-formulario-titulo { grid-column: 1 / -1; margin: 0; font-size: 1.05rem; }
.ct-error-campo { font-size: .8rem; color: var(--gp-coral); }
.pl-campo [aria-invalid="true"] { border-color: var(--gp-coral); }

/* Fila o elemento de un catálogo desactivado: se lee, pero atenuado. */
.ct-inactiva { opacity: .6; }
.ct-inactiva:hover, .ct-inactiva:focus-within { opacity: .85; }

.ct-clave { font-family: var(--gp-fuente-datos); font-size: .78rem; color: var(--gp-azul-texto); }

.ct-etapas li { align-items: flex-start; }
.ct-etapa { display: flex; flex: 1 1 20rem; gap: .75rem; align-items: baseline; min-width: 0; }
.ct-etapa-orden { flex-shrink: 0; width: 1.6rem; font-family: var(--gp-fuente-datos); font-size: .8rem; text-align: right; color: var(--gp-texto-3); }
.ct-etapa-texto { display: grid; gap: .15rem; min-width: 0; }
.ct-etapa-texto strong { font-size: .92rem; color: var(--gp-texto); }
.ct-etapa-texto small { font-size: .82rem; color: var(--gp-texto-2); overflow-wrap: anywhere; }
```

Crear `resources/js/components/CatalogoEtapasContratacion.jsx`:

```jsx
import React, { useCallback, useEffect, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowDown, faArrowUp, faPen, faPlus, faPowerOff } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
import { mensajeApi } from '../utils/plantillasChecklist';
import {
    REGLAS_ETAPA, datosCatalogo, erroresPorCampo, idsTrasMover, ordenarCatalogo, puedeAdministrarCatalogos, textoEstadoCatalogo,
} from '../utils/contratacion';
import FormularioCatalogo from './FormularioCatalogo';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';

/**
 * Parámetros → Etapas de contratación: las etapas de un proceso en su orden. No se eliminan: se
 * desactivan. La clave se genera del nombre al crearla y no cambia. Solo un Administrador modifica.
 */
const CatalogoEtapasContratacion = ({ user }) => {
    const [etapas, setEtapas] = useState(null);
    const [error, setError] = useState(null);
    const [editando, setEditando] = useState(null); // null, 'nueva' o la etapa
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    // data-foco del control que debe recuperar el foco cuando la lista vuelva a pintarse: el botón
    // que abrió el formulario, la flecha de la etapa que se movió o el de activar o desactivar.
    const retorno = useRef(null);
    const administra = puedeAdministrarCatalogos(user);

    const cargar = useCallback(async () => {
        try {
            const { data } = await axios.get('/etapasContratacion');
            setEtapas(ordenarCatalogo(data));
            setError(null);
        } catch (e) {
            setError(mensajeApi(e));
        }
    }, []);

    useEffect(() => { cargar(); }, [cargar]);

    // Cuando se cierra el formulario, llega el orden nuevo o termina el cambio de estado, el foco
    // vuelve a su control. Una flecha que quedó en el borde está deshabilitada: el foco pasa a la
    // otra flecha de la etapa.
    useEffect(() => {
        if (editando || ocupado || !retorno.current) return;
        const buscar = (clave) => contenedor.current?.querySelector(`[data-foco="${clave}"]:not(:disabled)`);
        const [accion, id] = retorno.current.split(':');
        const otra = { subir: 'bajar', bajar: 'subir' }[accion];
        (buscar(retorno.current) ?? (otra ? buscar(`${otra}:${id}`) : null))?.focus();
        retorno.current = null;
    }, [editando, ocupado, etapas]);

    const abrir = (etapa) => {
        retorno.current = etapa === 'nueva' ? 'nueva' : `editar:${etapa.id}`;
        setEditando(etapa);
    };

    const guardar = (formulario) => ejecutar(async () => {
        const item = editando === 'nueva' ? null : editando;
        try {
            await axios.post('/guardarEtapaContratacion', datosCatalogo(formulario, item, true));
            setEditando(null);
            await cargar();
            return null;
        } catch (e) {
            const errores = erroresPorCampo(e, ['nombre', 'descripcion']);
            if (!errores || errores.otros.length || !Object.keys(errores.campos).length) avisarError('No se pudo guardar la etapa', e);
            return errores?.campos ?? null;
        }
    });

    const alternarActiva = (etapa) => ejecutar(async () => {
        retorno.current = `activar:${etapa.id}`;
        if (etapa.activo) {
            const { isConfirmed } = await Swal.fire({
                icon: 'warning',
                titleText: `¿Desactivar "${etapa.nombre}"?`,
                text: 'No se ofrecerá en requisitos nuevos. Lo que ya la usa la conserva.',
                showCancelButton: true,
                confirmButtonText: 'Sí, desactivar',
                cancelButtonText: 'Cancelar',
            });
            if (!isConfirmed) return;
        }
        try {
            await axios.post('/activarEtapaContratacion', { id: etapa.id });
            await cargar();
        } catch (e) {
            avisarError('No se pudo cambiar el estado', e);
        }
    });

    // Se envía la lista completa; los botones quedan deshabilitados hasta que llega el orden nuevo.
    const moverEtapa = (indice, delta) => ejecutar(async () => {
        retorno.current = `${delta < 0 ? 'subir' : 'bajar'}:${etapas[indice].id}`;
        try {
            await axios.put('/etapasContratacion/orden', { ids: idsTrasMover(etapas, indice, delta) });
        } catch (e) {
            avisarError('No se pudo cambiar el orden', e);
        }
        // También tras un error: otra persona pudo cambiar el catálogo entretanto.
        await cargar();
    });

    if (editando) {
        return (
            <FormularioCatalogo
                titulo={editando === 'nueva' ? 'Nueva etapa' : 'Editar etapa'}
                item={editando === 'nueva' ? null : editando}
                items={etapas}
                reglas={REGLAS_ETAPA}
                conDescripcion
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
                        ? 'El orden es el de un proceso: ordena con las flechas. Las etapas no se eliminan: desactiva las que no uses.'
                        : 'Solo un Administrador puede modificar las etapas.'}
                </p>
                {administra && (
                    <button type="button" className="gp-boton gp-boton-primario" data-foco="nueva" onClick={() => abrir('nueva')} disabled={ocupado || etapas === null}>
                        <FontAwesomeIcon icon={faPlus} /> Nueva etapa
                    </button>
                )}
            </div>

            {error && (
                <p className="pl-error" role="alert">
                    {error} <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>
                </p>
            )}
            {etapas === null && !error && <p className="gp-vacio" role="status">Cargando etapas…</p>}
            {etapas !== null && etapas.length === 0 && <p className="gp-vacio">Aún no hay etapas.</p>}

            {etapas !== null && etapas.length > 0 && (
                <ol className="pl-tabla ct-etapas" aria-label="Etapas de contratación en orden">
                    {etapas.map((etapa, indice) => (
                        <li key={etapa.id} className={etapa.activo ? undefined : 'ct-inactiva'}>
                            <div className="ct-etapa">
                                <span className="ct-etapa-orden" aria-hidden="true">{indice + 1}</span>
                                <div className="ct-etapa-texto">
                                    <strong>{etapa.nombre}</strong>
                                    {etapa.descripcion && <small>{etapa.descripcion}</small>}
                                </div>
                                {!etapa.activo && <span className="pl-insignia pl-insignia-alerta">{textoEstadoCatalogo(false)}</span>}
                            </div>
                            {administra && (
                                <div className="pl-fila-acciones">
                                    <button type="button" className="gp-icono" data-foco={`subir:${etapa.id}`} aria-label={`Subir ${etapa.nombre}`} disabled={ocupado || indice === 0} onClick={() => moverEtapa(indice, -1)}>
                                        <FontAwesomeIcon icon={faArrowUp} />
                                    </button>
                                    <button type="button" className="gp-icono" data-foco={`bajar:${etapa.id}`} aria-label={`Bajar ${etapa.nombre}`} disabled={ocupado || indice === etapas.length - 1} onClick={() => moverEtapa(indice, 1)}>
                                        <FontAwesomeIcon icon={faArrowDown} />
                                    </button>
                                    <button type="button" className="gp-icono" data-foco={`editar:${etapa.id}`} aria-label={`Editar ${etapa.nombre}`} disabled={ocupado} onClick={() => abrir(etapa)}>
                                        <FontAwesomeIcon icon={faPen} />
                                    </button>
                                    <button
                                        type="button"
                                        className="gp-icono"
                                        data-foco={`activar:${etapa.id}`}
                                        aria-label={`${etapa.activo ? 'Desactivar' : 'Activar'} ${etapa.nombre}`}
                                        title={etapa.activo ? 'Desactivar' : 'Activar'}
                                        disabled={ocupado}
                                        onClick={() => alternarActiva(etapa)}
                                    >
                                        <FontAwesomeIcon icon={faPowerOff} />
                                    </button>
                                </div>
                            )}
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
};

export default CatalogoEtapasContratacion;
```

Crear `resources/js/components/CatalogoModalidades.jsx`:

```jsx
import React, { useCallback, useEffect, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faEdit, faPlus } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
import { mensajeApi } from '../utils/plantillasChecklist';
import {
    REGLAS_MODALIDAD, datosCatalogo, erroresPorCampo, ordenarCatalogo, puedeAdministrarCatalogos, textoEstadoCatalogo,
} from '../utils/contratacion';
import FormularioCatalogo from './FormularioCatalogo';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';

/**
 * Parámetros → Modalidades. Las modalidades no se eliminan: se desactivan, y un proceso conserva
 * la suya aunque se desactive. La clave la fija la siembra. Solo un Administrador modifica.
 */
const CatalogoModalidades = ({ user }) => {
    const [modalidades, setModalidades] = useState(null);
    const [error, setError] = useState(null);
    const [editando, setEditando] = useState(null); // null, 'nueva' o la modalidad
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    // data-foco del control que debe recuperar el foco cuando la lista vuelva a pintarse: el botón
    // que abrió el formulario o el de activar o desactivar que se usó.
    const retorno = useRef(null);
    const administra = puedeAdministrarCatalogos(user);

    const cargar = useCallback(async () => {
        try {
            const { data } = await axios.get('/modalidades');
            setModalidades(ordenarCatalogo(data));
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
    }, [editando, ocupado, modalidades]);

    const abrir = (modalidad) => {
        retorno.current = modalidad === 'nueva' ? 'nueva' : `editar:${modalidad.id}`;
        setEditando(modalidad);
    };

    const guardar = (formulario) => ejecutar(async () => {
        const item = editando === 'nueva' ? null : editando;
        try {
            await axios.post('/guardarModalidad', datosCatalogo(formulario, item));
            setEditando(null);
            await cargar();
            return null;
        } catch (e) {
            const errores = erroresPorCampo(e, ['nombre']);
            if (!errores || errores.otros.length || !Object.keys(errores.campos).length) avisarError('No se pudo guardar la modalidad', e);
            return errores?.campos ?? null;
        }
    });

    const alternarActiva = (modalidad) => ejecutar(async () => {
        retorno.current = `activar:${modalidad.id}`;
        if (modalidad.activo) {
            const { isConfirmed } = await Swal.fire({
                icon: 'warning',
                titleText: `¿Desactivar "${modalidad.nombre}"?`,
                text: 'No se ofrecerá en procesos nuevos. Los procesos que ya la tienen la conservan.',
                showCancelButton: true,
                confirmButtonText: 'Sí, desactivar',
                cancelButtonText: 'Cancelar',
            });
            if (!isConfirmed) return;
        }
        try {
            await axios.post('/activarModalidad', { id: modalidad.id });
            await cargar();
        } catch (e) {
            avisarError('No se pudo cambiar el estado', e);
        }
    });

    if (editando) {
        return (
            <FormularioCatalogo
                titulo={editando === 'nueva' ? 'Nueva modalidad' : 'Editar modalidad'}
                item={editando === 'nueva' ? null : editando}
                items={modalidades}
                reglas={REGLAS_MODALIDAD}
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
                        ? 'Las modalidades no se eliminan: desactiva las que ya no uses. Los procesos conservan la suya.'
                        : 'Solo un Administrador puede modificar las modalidades.'}
                </p>
                {administra && (
                    <button type="button" className="gp-boton gp-boton-primario" data-foco="nueva" onClick={() => abrir('nueva')} disabled={ocupado || modalidades === null}>
                        <FontAwesomeIcon icon={faPlus} /> Nueva modalidad
                    </button>
                )}
            </div>

            {error && (
                <p className="pl-error" role="alert">
                    {error} <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>
                </p>
            )}
            {modalidades === null && !error && <p className="gp-vacio" role="status">Cargando modalidades…</p>}
            {modalidades !== null && modalidades.length === 0 && <p className="gp-vacio">Aún no hay modalidades.</p>}

            {modalidades !== null && modalidades.length > 0 && (
                <div className="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Clave</th>
                                <th>Estado</th>
                                {administra && <th>Acciones</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {modalidades.map((m) => (
                                <tr key={m.id} className={m.activo ? undefined : 'ct-inactiva'}>
                                    <td data-etiqueta="Nombre">{m.nombre}</td>
                                    <td data-etiqueta="Clave">{m.clave ? <span className="ct-clave">{m.clave}</span> : '—'}</td>
                                    <td data-etiqueta="Estado">
                                        <span className={`status-badge ${m.activo ? 'active' : 'inactive'}`}>{textoEstadoCatalogo(m.activo)}</span>
                                    </td>
                                    {administra && (
                                        <td className="celda-acciones">
                                            <div className="action-buttons">
                                                <button type="button" className="btn-edit" data-foco={`editar:${m.id}`} onClick={() => abrir(m)} disabled={ocupado} aria-label={`Editar ${m.nombre}`}>
                                                    <FontAwesomeIcon icon={faEdit} /> Editar
                                                </button>
                                                <button
                                                    type="button"
                                                    className={`btn-toggle ${m.activo ? 'deactivate' : 'activate'}`}
                                                    data-foco={`activar:${m.id}`}
                                                    onClick={() => alternarActiva(m)}
                                                    disabled={ocupado}
                                                    aria-label={`${m.activo ? 'Desactivar' : 'Activar'} ${m.nombre}`}
                                                >
                                                    {m.activo ? 'Desactivar' : 'Activar'}
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

export default CatalogoModalidades;
```

Crear `resources/js/components/FormularioCatalogo.jsx`:

```jsx
import React, { useId, useRef, useState } from 'react';
import { formularioCatalogo, validarCatalogo } from '../utils/contratacion';

/**
 * Formulario de un elemento de catálogo (modalidad o etapa): nombre y, si `conDescripcion`,
 * descripción. Valida antes de enviar con las reglas del servidor; `onGuardar` devuelve los
 * errores por campo del servidor, o null. Escape cancela.
 */
const FormularioCatalogo = ({ titulo, item, items, reglas, conDescripcion = false, guardando, onGuardar, onCancelar }) => {
    const [f, setF] = useState(() => formularioCatalogo(item));
    const [errores, setErrores] = useState({});
    const formulario = useRef(null);
    const id = useId();

    const enfocarPrimerError = (lista) => {
        const campo = ['nombre', 'descripcion'].find((c) => lista[c]);
        if (campo) formulario.current?.querySelector(`[name="${campo}"]`)?.focus();
    };

    const enviar = async (e) => {
        e.preventDefault();
        const locales = validarCatalogo(f, items, item?.id, reglas);
        setErrores(locales);
        if (Object.keys(locales).length) {
            enfocarPrimerError(locales);
            return;
        }
        const delServidor = await onGuardar(f);
        if (delServidor && Object.keys(delServidor).length) {
            setErrores(delServidor);
            enfocarPrimerError(delServidor);
        }
    };

    const cambiar = (campo, valor) => {
        setF((actual) => ({ ...actual, [campo]: valor }));
        setErrores((actual) => (actual[campo] ? { ...actual, [campo]: undefined } : actual));
    };

    const alPulsar = (e) => {
        if (e.key === 'Escape' && !guardando) {
            e.stopPropagation();
            onCancelar();
        }
    };

    const ayuda = (campo) => (errores[campo] ? `${id}-${campo}` : undefined);

    return (
        <form ref={formulario} className="gp-panel pl-formulario" onSubmit={enviar} onKeyDown={alPulsar} noValidate aria-labelledby={`${id}-titulo`}>
            <h2 id={`${id}-titulo`} className="ct-formulario-titulo">{titulo}</h2>
            <label className="pl-campo pl-campo-ancho">
                <span>Nombre</span>
                <input
                    name="nombre"
                    autoFocus
                    value={f.nombre}
                    maxLength={reglas.maximoNombre}
                    aria-invalid={Boolean(errores.nombre)}
                    aria-describedby={ayuda('nombre')}
                    onChange={(e) => cambiar('nombre', e.target.value)}
                />
                {errores.nombre && <small id={ayuda('nombre')} className="ct-error-campo">{errores.nombre}</small>}
            </label>
            {conDescripcion && (
                <label className="pl-campo pl-campo-ancho">
                    <span>Descripción (opcional)</span>
                    <textarea
                        name="descripcion"
                        rows={3}
                        value={f.descripcion}
                        maxLength={reglas.maximoDescripcion}
                        aria-invalid={Boolean(errores.descripcion)}
                        aria-describedby={ayuda('descripcion')}
                        onChange={(e) => cambiar('descripcion', e.target.value)}
                    />
                    {errores.descripcion && <small id={ayuda('descripcion')} className="ct-error-campo">{errores.descripcion}</small>}
                </label>
            )}
            <div className="pl-formulario-acciones">
                <button type="button" className="gp-boton" onClick={onCancelar} disabled={guardando}>Cancelar</button>
                <button type="submit" className="gp-boton gp-boton-primario" disabled={guardando}>Guardar</button>
            </div>
        </form>
    );
};

export default FormularioCatalogo;
```

Crear `resources/js/utils/contratacion.js`:

```js
// Lógica de presentación del módulo de contratación: catálogos de modalidades y etapas.
// Funciones puras, probadas en contratacion.test.js. Las reglas las valida el backend; aquí se
// adelantan los mismos mensajes para no esperar un 422.

import { normalizar } from './navegacion';
import { mover } from './plantillasChecklist';

/** Modalidades y etapas se administran con gestionar-catalogos: solo un Administrador. */
export const puedeAdministrarCatalogos = (usuario) => usuario?.rol === 'Administrador';

/** Las reglas del formulario de cada catálogo, con los límites y mensajes del controlador. */
export const REGLAS_MODALIDAD = {
    maximoNombre: 199,
    faltaNombre: 'Escribe el nombre de la modalidad.',
    repetido: 'Ya existe una modalidad con ese nombre.',
};

export const REGLAS_ETAPA = {
    maximoNombre: 120,
    maximoDescripcion: 2000,
    faltaNombre: 'Escribe el nombre de la etapa.',
    repetido: 'Ya existe una etapa con ese nombre.',
};

export const CATALOGO_VACIO = { nombre: '', descripcion: '' };

export const formularioCatalogo = (item) => ({
    nombre: item?.nombre ?? '',
    descripcion: item?.descripcion ?? '',
});

/**
 * Errores del formulario por campo; {} si se puede enviar. El nombre no se repite sin contar
 * mayúsculas ni tildes, como en el servidor. `idActual` es el del registro que se edita.
 */
export const validarCatalogo = (formulario, items, idActual, reglas) => {
    const errores = {};
    const nombre = String(formulario.nombre ?? '').trim();
    if (!nombre) {
        errores.nombre = reglas.faltaNombre;
    } else if (nombre.length > reglas.maximoNombre) {
        errores.nombre = `El nombre admite hasta ${reglas.maximoNombre} caracteres.`;
    } else if ((items ?? []).some((i) => i.id !== idActual && normalizar(i.nombre) === normalizar(nombre))) {
        errores.nombre = reglas.repetido;
    }
    const descripcion = String(formulario.descripcion ?? '').trim();
    if (reglas.maximoDescripcion && descripcion.length > reglas.maximoDescripcion) {
        errores.descripcion = `La descripción admite hasta ${reglas.maximoDescripcion} caracteres.`;
    }
    return errores;
};

/** Lo que esperan /guardarModalidad y /guardarEtapaContratacion. `conDescripcion` solo en etapas. */
export const datosCatalogo = (formulario, item, conDescripcion = false) => ({
    accion: item ? 'Editar' : 'Agregar',
    ...(item ? { id: item.id } : {}),
    nombre: String(formulario.nombre ?? '').trim(),
    ...(conDescripcion ? { descripcion: String(formulario.descripcion ?? '').trim() || null } : {}),
});

/** Por orden y, a igual orden, por nombre (como las modalidades del servidor) y por id. */
export const ordenarCatalogo = (items) => [...(items ?? [])].sort((a, b) => (
    (a.orden ?? 0) - (b.orden ?? 0)
    || String(a.nombre ?? '').localeCompare(String(b.nombre ?? ''), 'es')
    || a.id - b.id
));

/** Los ids de la lista con el elemento `indice` movido una posición: lo que espera el PUT de orden. */
export const idsTrasMover = (items, indice, delta) => mover((items ?? []).map((i) => i.id), indice, delta);

/**
 * Los errores de validación de un 422 ({ errors }) repartidos: el primer mensaje de cada campo
 * del formulario y, aparte, los de otros campos. null si el error no es de validación.
 */
export const erroresPorCampo = (error, campos) => {
    const errores = error?.response?.status === 422 ? error.response.data?.errors : null;
    if (!errores || typeof errores !== 'object') return null;
    const enCampos = {};
    const otros = [];
    Object.entries(errores).forEach(([campo, mensajes]) => {
        const lista = [].concat(mensajes ?? []).map(String);
        if (campos.includes(campo)) {
            if (lista.length) enCampos[campo] = lista[0];
        } else {
            otros.push(...lista);
        }
    });
    return { campos: enCampos, otros };
};

/** Modalidades y etapas son femeninas: "Activa" o "Inactiva". */
export const textoEstadoCatalogo = (activo) => (activo ? 'Activa' : 'Inactiva');
```

En `resources/js/app.jsx`, reemplazar (1 de 1):

```jsx
        <Route element={privada(<AppShell user={user} onLogout={logout} />)}>
          <Route path="/dashboard" element={<Dashboard />} />
          <Route path="/parametros" element={<Navigate to="/parametros/proyectos" replace />} />
          <Route path="/parametros/:seccion" element={<Parametros />} />
          {/* No usar /expedientes/...: bajo /GestPro chocaría con la API del mismo nombre. */}
          <Route path="/viabilizacion/:proyectoId" element={<ExpedienteViabilizacion user={user} />} />
          {/* Solo Administrador: el backend también lo exige. */}
```

por:

```jsx
        <Route element={privada(<AppShell user={user} onLogout={logout} />)}>
          <Route path="/dashboard" element={<Dashboard />} />
          <Route path="/parametros" element={<Navigate to="/parametros/proyectos" replace />} />
          <Route path="/parametros/:seccion" element={<Parametros user={user} />} />
          {/* No usar /expedientes/...: bajo /GestPro chocaría con la API del mismo nombre. */}
          <Route path="/viabilizacion/:proyectoId" element={<ExpedienteViabilizacion user={user} />} />
          {/* Solo Administrador: el backend también lo exige. */}
```

En `resources/js/components/Parametros.jsx`, reemplazar (1 de 4):

```jsx
import '../../css/Parametros.css';
import { seccionDesdeSlug } from '../utils/navegacion';
import { alternarFuente, datosFinanciacionDe, financiacionCompleta, fuentesDisponibles, mensajeDeError } from '../utils/fuentesProyecto';

// Componente auxiliar: escucha clics en el mapa y reporta la coordenada al padre.
// Debe vivir dentro de <MapContainer> — el hook useMapEvents solo funciona ahí.
```

por:

```jsx
import '../../css/Parametros.css';
import { seccionDesdeSlug } from '../utils/navegacion';
import { alternarFuente, datosFinanciacionDe, financiacionCompleta, fuentesDisponibles, mensajeDeError } from '../utils/fuentesProyecto';
import CatalogoModalidades from './CatalogoModalidades';
import CatalogoEtapasContratacion from './CatalogoEtapasContratacion';

// Componente auxiliar: escucha clics en el mapa y reporta la coordenada al padre.
// Debe vivir dentro de <MapContainer> — el hook useMapEvents solo funciona ahí.
```

En `resources/js/components/Parametros.jsx`, reemplazar (2 de 4):

```jsx
    return null;
};

const Parametros = () => {
    const navigate = useNavigate();
    // La sección vive en la URL: se puede enlazar, recargar y volver con el navegador.
```

por:

```jsx
    return null;
};

// Secciones con componente propio (`propia` en navegacion.js), por su `tab`.
const SECCIONES_PROPIAS = {
    modalidades: CatalogoModalidades,
    'etapas-contratacion': CatalogoEtapasContratacion,
};

const CabeceraSeccion = ({ seccion }) => (
    <header className="gp-cabecera">
        <span className="gp-etiqueta">{seccion.grupo}</span>
        <h1>{seccion.titulo}</h1>
        <p>{seccion.descripcion}</p>
    </header>
);

const Parametros = () => {
    const navigate = useNavigate();
    // La sección vive en la URL: se puede enlazar, recargar y volver con el navegador.
```

En `resources/js/components/Parametros.jsx`, reemplazar (3 de 4):

```jsx

    return (
        <div className="parametros-container">
            <header className="gp-cabecera">
                <span className="gp-etiqueta">{seccion.grupo}</span>
                <h1>{seccion.titulo}</h1>
                <p>{seccion.descripcion}</p>
            </header>

            {activeTab === 'proyectos' && renderTable(proyectos, 'proyectos')}
                        {activeTab === 'eventos-calendario' && renderTable(eventos, 'eventos')}
```

por:

```jsx

    return (
        <div className="parametros-container">
            <CabeceraSeccion seccion={seccion} />

            {activeTab === 'proyectos' && renderTable(proyectos, 'proyectos')}
                        {activeTab === 'eventos-calendario' && renderTable(eventos, 'eventos')}
```

En `resources/js/components/Parametros.jsx`, reemplazar (4 de 4):

```jsx
    );
};

export default Parametros;
```

por:

```jsx
    );
};

// Una sección propia se pinta sin montar Parametros: no pide sus catálogos ni llama los hooks de
// renderTable. Parametros solo se monta para una sección de tablas, que llama renderTable una vez,
// así que siempre ve el mismo número de hooks (navegacion.test.js lo comprueba).
const ParametrosPorSeccion = ({ user }) => {
    const seccion = seccionDesdeSlug(useParams().seccion);
    if (!seccion) return <Navigate to="/parametros/proyectos" replace />;
    const Propia = seccion?.propia ? SECCIONES_PROPIAS[seccion.tab] : null;
    if (!Propia) return <Parametros />;

    return (
        <div className="parametros-container">
            <CabeceraSeccion seccion={seccion} />
            <Propia user={user} />
        </div>
    );
};

export default ParametrosPorSeccion;
```

En `resources/js/utils/iconosNavegacion.js`, reemplazar (1 de 1):

```js
import {
    faCalendarDays, faChartColumn, faCircleHalfStroke, faClipboardCheck, faCoins, faDiagramProject, faFileSignature,
    faFlag, faFolderOpen, faGaugeHigh, faLandmark, faLayerGroup, faListCheck, faMapLocationDot, faTags, faUsers,
} from '@fortawesome/free-solid-svg-icons';

// navegacion.js guarda el nombre del icono para seguir siendo lógica pura y probable.
export const ICONOS = {
    faCalendarDays, faChartColumn, faCircleHalfStroke, faClipboardCheck, faCoins, faDiagramProject, faFileSignature,
    faFlag, faFolderOpen, faGaugeHigh, faLandmark, faLayerGroup, faListCheck, faMapLocationDot, faTags, faUsers,
};
```

por:

```js
import {
    faCalendarDays, faChartColumn, faCircleHalfStroke, faClipboardCheck, faCoins, faDiagramProject, faFileSignature,
    faFlag, faFolderOpen, faGaugeHigh, faGavel, faLandmark, faLayerGroup, faListCheck, faListOl, faMapLocationDot, faTags, faUsers,
} from '@fortawesome/free-solid-svg-icons';

// navegacion.js guarda el nombre del icono para seguir siendo lógica pura y probable.
export const ICONOS = {
    faCalendarDays, faChartColumn, faCircleHalfStroke, faClipboardCheck, faCoins, faDiagramProject, faFileSignature,
    faFlag, faFolderOpen, faGaugeHigh, faGavel, faLandmark, faLayerGroup, faListCheck, faListOl, faMapLocationDot, faTags, faUsers,
};
```

En `resources/js/utils/navegacion.js`, reemplazar (1 de 1):

```js
    { slug: 'entidades', tab: 'entidades', titulo: 'Entidades', descripcion: 'Quienes presentan y financian los proyectos.', grupo: GRUPO.CATALOGOS, icono: 'faLandmark', palabras: 'alcaldia gobernacion ministerio catalogo' },
    { slug: 'fuentes-financiacion', tab: 'fuentes-financiacion', titulo: 'Fuentes de financiación', descripcion: 'De dónde provienen los recursos de los proyectos.', grupo: GRUPO.CATALOGOS, icono: 'faCoins', palabras: 'regalias sgr recursos propios cofinanciacion catalogo' },
    { slug: 'sectores', tab: 'sectores', titulo: 'Sectores', descripcion: 'Sectores de inversión de los proyectos.', grupo: GRUPO.CATALOGOS, icono: 'faLayerGroup', palabras: 'educacion salud transporte vivienda catalogo' },
];

// No usar /plantillas: bajo /GestPro chocaría con la API del mismo nombre.
```

por:

```js
    { slug: 'entidades', tab: 'entidades', titulo: 'Entidades', descripcion: 'Quienes presentan y financian los proyectos.', grupo: GRUPO.CATALOGOS, icono: 'faLandmark', palabras: 'alcaldia gobernacion ministerio catalogo' },
    { slug: 'fuentes-financiacion', tab: 'fuentes-financiacion', titulo: 'Fuentes de financiación', descripcion: 'De dónde provienen los recursos de los proyectos.', grupo: GRUPO.CATALOGOS, icono: 'faCoins', palabras: 'regalias sgr recursos propios cofinanciacion catalogo' },
    { slug: 'sectores', tab: 'sectores', titulo: 'Sectores', descripcion: 'Sectores de inversión de los proyectos.', grupo: GRUPO.CATALOGOS, icono: 'faLayerGroup', palabras: 'educacion salud transporte vivienda catalogo' },
    // `propia`: la sección es un componente aparte que se pinta sin Parametros, no una de sus tablas.
    { slug: 'modalidades', tab: 'modalidades', titulo: 'Modalidades', descripcion: 'Formas de contratación: licitación pública, mínima cuantía…', grupo: GRUPO.CATALOGOS, icono: 'faGavel', palabras: 'contratacion licitacion seleccion abreviada concurso meritos directa minima cuantia secop catalogo', propia: true },
    { slug: 'etapas-contratacion', tab: 'etapas-contratacion', titulo: 'Etapas de contratación', descripcion: 'Etapas de un proceso y su orden.', grupo: GRUPO.CATALOGOS, icono: 'faListOl', palabras: 'contratacion licitacion proceso pliego adjudicacion cronograma secop catalogo', propia: true },
];

// No usar /plantillas: bajo /GestPro chocaría con la API del mismo nombre.
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/contratacion.test.js resources/js/utils/navegacion.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 797 passed.

Run: `npm test`
Expected: 389 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/css/Contratacion.css resources/js/components/CatalogoEtapasContratacion.jsx resources/js/components/CatalogoModalidades.jsx resources/js/components/FormularioCatalogo.jsx resources/js/utils/contratacion.js resources/js/utils/contratacion.test.js resources/js/app.jsx resources/js/components/Parametros.jsx resources/js/utils/iconosNavegacion.js resources/js/utils/navegacion.js resources/js/utils/navegacion.test.js
git commit -m "feat: parameters screens for modalities and procurement stages"
```

---

### Tarea 7: Procesos de contratación del proyecto

Botón "Procesos" por proyecto y el diálogo `ProcesosProyecto`.

**Files:**
- Create: `resources/js/components/ProcesosProyecto.jsx`
- Modify: `resources/css/Contratacion.css`
- Modify: `resources/js/components/Parametros.jsx`
- Modify: `resources/js/utils/contratacion.js`
- Test: `resources/js/utils/contratacion.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/contratacion.test.js`, reemplazar (1 de 3):

```js
import { describe, it, expect } from 'vitest';
import {
    CATALOGO_VACIO,
    REGLAS_ETAPA,
    REGLAS_MODALIDAD,
    datosCatalogo,
```

por:

```js
import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import {
    CAMPOS_PROCESO,
    CATALOGO_VACIO,
    ESTADOS_CIERRE,
    ESTADOS_PROCESO,
    PLATAFORMAS,
    PROCESO_VACIO,
    REGLAS_ETAPA,
    REGLAS_MODALIDAD,
    datosCatalogo,
```

En `resources/js/utils/contratacion.test.js`, reemplazar (2 de 3):

```js
    puedeAdministrarCatalogos,
    textoEstadoCatalogo,
    validarCatalogo,
} from './contratacion';

const modalidades = [
```

por:

```js
    puedeAdministrarCatalogos,
    textoEstadoCatalogo,
    validarCatalogo,
    avisoCambioEstado,
    datosEstado,
    datosProceso,
    esEdicionDesactualizada,
    esEnlaceWeb,
    etiquetaEstadoProceso,
    etiquetaPlataforma,
    formularioProceso,
    indiceFocoAtrapado,
    numeroProceso,
    opcionesConActual,
    puedeEditarProcesos,
    textoContratos,
    textoValor,
    tonoEstado,
    validarProceso,
    valorDesdeApi,
    valorEnEdicion,
    valorParaApi,
} from './contratacion';

const modalidades = [
```

En `resources/js/utils/contratacion.test.js`, reemplazar (3 de 3):

```js
        expect(textoEstadoCatalogo(false)).toBe('Inactiva');
    });
});
```

por:

```js
        expect(textoEstadoCatalogo(false)).toBe('Inactiva');
    });
});

// ---------- Procesos de contratación ----------

/** El texto con espacios normales: Intl separa el signo de pesos con un espacio duro. */
const plano = (texto) => texto.replace(/\s/g, ' ');

describe('constantes contra PHP', () => {
    // Ruta relativa a este archivo, no al directorio desde donde se lance Vitest.
    const modelo = readFileSync(new URL('../../../app/Models/ProcesoLicitacion.php', import.meta.url), 'utf8');
    const valor = (nombre) => modelo.match(new RegExp(`const ${nombre} = '([a-z_]+)'`))[1];
    const lista = (nombre) => modelo.match(new RegExp(`const ${nombre} = \\[([^\\]]*)\\]`))[1]
        .split(',').map((x) => x.trim()).filter(Boolean).map((x) => valor(x.replace('self::', '')));

    it('las plataformas y los estados coinciden con ProcesoLicitacion', () => {
        expect(PLATAFORMAS.map(([v]) => v)).toEqual(lista('PLATAFORMAS'));
        expect(ESTADOS_PROCESO.map(([v]) => v)).toEqual(lista('ESTADOS'));
        expect(ESTADOS_CIERRE.every((e) => lista('ESTADOS').includes(e))).toBe(true);
    });
});

describe('puedeEditarProcesos', () => {
    it('Administrador o Gestor, como editar-datos', () => {
        expect(puedeEditarProcesos({ rol: 'Administrador' })).toBe(true);
        expect(puedeEditarProcesos({ rol: 'Gestor' })).toBe(true);
        expect(puedeEditarProcesos({ rol: 'Consulta' })).toBe(false);
        expect(puedeEditarProcesos(undefined)).toBe(false);
    });
});

describe('etiquetas de plataforma y estado', () => {
    it('dan el nombre legible', () => {
        expect(etiquetaPlataforma('secop_ii')).toBe('SECOP II');
        expect(etiquetaPlataforma('secop_i')).toBe('SECOP I');
        expect(etiquetaPlataforma('tvec')).toBe('Tienda Virtual');
        expect(etiquetaPlataforma('ninguna')).toBe('Sin plataforma');
        expect(etiquetaEstadoProceso('preparacion')).toBe('En preparación');
        expect(etiquetaEstadoProceso('evaluacion')).toBe('En evaluación');
    });

    it('un valor desconocido o vacío no rompe la pantalla', () => {
        expect(etiquetaPlataforma(null)).toBe('—');
        expect(etiquetaEstadoProceso('otro')).toBe('otro');
        expect(etiquetaEstadoProceso(null)).toBe('—');
    });

    it('cada estado tiene un tono para su insignia', () => {
        expect(ESTADOS_PROCESO.map(([v]) => tonoEstado(v))).toEqual(['neutro', 'azul', 'ambar', 'verde', 'coral', 'verde-pleno', 'coral']);
        expect(tonoEstado('otro')).toBe('neutro');
    });
});

describe('avisoCambioEstado', () => {
    it('el avance por el flujo no avisa', () => {
        [
            ['preparacion', 'publicado'], ['publicado', 'evaluacion'], ['evaluacion', 'adjudicado'],
            ['evaluacion', 'desierto'], ['adjudicado', 'celebrado'], ['adjudicado', 'desierto'],
            ['preparacion', 'celebrado'],
        ].forEach(([de, a]) => expect(avisoCambioEstado(de, a)).toBeNull());
    });

    it('cancelar desde un estado abierto no avisa', () => {
        ['preparacion', 'publicado', 'evaluacion', 'adjudicado'].forEach((de) => expect(avisoCambioEstado(de, 'cancelado')).toBeNull());
    });

    it('volver atrás en el flujo avisa', () => {
        expect(avisoCambioEstado('evaluacion', 'publicado')).toEqual({
            titulo: 'El proceso vuelve atrás',
            texto: '"Publicado" es un paso anterior a "En evaluación" en el flujo del proceso. ¿Seguro que quieres cambiarlo? El cambio queda en el historial.',
        });
        expect(avisoCambioEstado('adjudicado', 'preparacion')?.titulo).toBe('El proceso vuelve atrás');
        expect(avisoCambioEstado('publicado', 'preparacion')?.titulo).toBe('El proceso vuelve atrás');
    });

    it('salir de un estado de cierre avisa, también hacia otro cierre o hacia adelante', () => {
        expect(avisoCambioEstado('celebrado', 'evaluacion')).toEqual({
            titulo: 'El proceso ya está cerrado',
            texto: '"Celebrado" es un estado de cierre. ¿Seguro que quieres pasar el proceso a "En evaluación"? El cambio queda en el historial.',
        });
        expect(avisoCambioEstado('desierto', 'celebrado')?.titulo).toBe('El proceso ya está cerrado');
        expect(avisoCambioEstado('cancelado', 'preparacion')?.titulo).toBe('El proceso ya está cerrado');
        expect(avisoCambioEstado('desierto', 'cancelado')?.titulo).toBe('El proceso ya está cerrado');
    });

    it('sin cambio o sin datos no hay aviso', () => {
        expect(avisoCambioEstado('celebrado', 'celebrado')).toBeNull();
        expect(avisoCambioEstado(null, 'publicado')).toBeNull();
        expect(avisoCambioEstado('publicado', '')).toBeNull();
    });
});

describe('valor en pesos', () => {
    it('agrupa los miles mientras se escribe y deja hasta 2 decimales', () => {
        expect(valorEnEdicion('1234567')).toBe('1.234.567');
        expect(valorEnEdicion('1234567,891')).toBe('1.234.567,89');
        expect(valorEnEdicion('$ 1.234.5678')).toBe('12.345.678');
        expect(valorEnEdicion('0012')).toBe('12');
        expect(valorEnEdicion(',5')).toBe('0,5');
        expect(valorEnEdicion('12,')).toBe('12,');
        expect(valorEnEdicion('1,2,3')).toBe('1,23');
        expect(valorEnEdicion('abc')).toBe('');
        expect(valorEnEdicion('')).toBe('');
    });

    it('sin coma, un punto con 1 o 2 cifras al final es el decimal; con 3, de miles', () => {
        expect(valorEnEdicion('1234567.89')).toBe('1.234.567,89');
        expect(valorEnEdicion('150.5')).toBe('150,5');
        expect(valorEnEdicion('$ 1,234.5')).toBe('1,23');
        expect(valorEnEdicion('1.500')).toBe('1.500');
        expect(valorEnEdicion('1.234.567.89')).toBe('1.234.567,89');
        expect(valorEnEdicion('150.')).toBe('150');
    });

    it('al borrar de un valor agrupado el punto sigue siendo de miles', () => {
        expect(valorEnEdicion('1.23', '1.234')).toBe('123');
        expect(valorEnEdicion('12.34', '12.345')).toBe('1.234');
        expect(valorEnEdicion('1.234.56', '1.234.567')).toBe('123.456');
        expect(valorEnEdicion('1.34', '1.234')).toBe('134');
        // Pegar sobre lo que había no es borrar.
        expect(valorEnEdicion('150.5', '1.234.567')).toBe('150,5');
        expect(valorEnEdicion('150.5', '')).toBe('150,5');
    });

    it('lo envía a la API sin separadores de miles y con punto decimal', () => {
        expect(valorParaApi('1.234.567,5')).toBe('1234567.5');
        expect(valorParaApi('1.234.567')).toBe('1234567');
        expect(valorParaApi('0,5')).toBe('0.5');
        expect(valorParaApi('12,')).toBe('12');
        expect(valorParaApi('0')).toBe('0');
        expect(valorParaApi('')).toBeNull();
        expect(valorParaApi(',')).toBeNull();
        expect(valorParaApi(null)).toBeNull();
    });

    it('a la API, con la misma regla del punto decimal', () => {
        expect(valorParaApi('1234567.89')).toBe('1234567.89');
        expect(valorParaApi('150.5')).toBe('150.5');
        expect(valorParaApi('1.500')).toBe('1500');
    });

    it('lo trae de la API al formulario sin centavos en cero', () => {
        expect(valorDesdeApi('1234567.50')).toBe('1.234.567,50');
        expect(valorDesdeApi('1000.00')).toBe('1.000');
        expect(valorDesdeApi('0.00')).toBe('0');
        expect(valorDesdeApi(null)).toBe('');
        expect(valorParaApi(valorDesdeApi('9999999999999.99'))).toBe('9999999999999.99');
    });

    it('lo muestra en pesos colombianos', () => {
        expect(plano(textoValor('1234567.00'))).toBe('$ 1.234.567');
        expect(plano(textoValor('1234567.50'))).toBe('$ 1.234.567,50');
        expect(plano(textoValor(0))).toBe('$ 0');
        expect(textoValor(null)).toBe('—');
        expect(textoValor('')).toBe('—');
        expect(textoValor('abc')).toBe('—');
    });
});

describe('textoContratos', () => {
    it('cuenta los contratos vinculados', () => {
        expect(textoContratos(0)).toBe('Sin contratos');
        expect(textoContratos(undefined)).toBe('Sin contratos');
        expect(textoContratos(1)).toBe('1 contrato');
        expect(textoContratos(3)).toBe('3 contratos');
    });
});

describe('enlace y número del proceso', () => {
    it('solo un enlace http o https completo es enlace', () => {
        expect(esEnlaceWeb('https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.1')).toBe(true);
        expect(esEnlaceWeb(' http://ejemplo.gov.co ')).toBe(true);
        expect(esEnlaceWeb('javascript:alert(1)')).toBe(false);
        expect(esEnlaceWeb('ftp://ejemplo.gov.co')).toBe(false);
        expect(esEnlaceWeb('www.secop.gov.co')).toBe(false);
        expect(esEnlaceWeb('https://')).toBe(false);
        expect(esEnlaceWeb(null)).toBe(false);
    });

    it('el número enlaza al proceso si tiene enlace', () => {
        expect(numeroProceso({ codigo_proceso: 'LP-001-2026', url_proceso: 'https://secop.gov.co/x' })).toEqual({ texto: 'LP-001-2026', enlace: 'https://secop.gov.co/x' });
        expect(numeroProceso({ codigo_proceso: null, url_proceso: 'https://secop.gov.co/x' })).toEqual({ texto: 'Ver proceso', enlace: 'https://secop.gov.co/x' });
        expect(numeroProceso({ codigo_proceso: 'LP-001-2026', url_proceso: null })).toEqual({ texto: 'LP-001-2026', enlace: null });
        expect(numeroProceso({ codigo_proceso: '', url_proceso: 'javascript:alert(1)' })).toEqual({ texto: '—', enlace: null });
    });
});

describe('opcionesConActual', () => {
    const entidades = [
        { id: 1, nombre: 'Alcaldía', activo: 1 },
        { id: 2, nombre: 'Gobernación', activo: 0 },
        { id: 3, nombre: 'Ministerio', activo: true },
    ];

    it('ofrece las activas, aunque `activo` venga como 1/0', () => {
        expect(opcionesConActual(entidades, null)).toEqual([
            { valor: '1', etiqueta: 'Alcaldía' },
            { valor: '3', etiqueta: 'Ministerio' },
        ]);
    });

    it('conserva la actual inactiva, marcada', () => {
        expect(opcionesConActual(entidades, { id: 2, nombre: 'Gobernación' }).at(-1)).toEqual({ valor: '2', etiqueta: 'Gobernación (inactiva)' });
    });

    it('conserva la actual que el catálogo ya no trae, marcada', () => {
        expect(opcionesConActual(entidades, { id: 7, nombre: 'Ministerio de Minas' }).at(-1)).toEqual({ valor: '7', etiqueta: 'Ministerio de Minas (inactiva)' });
    });

    it('sin el catálogo cargado conserva la actual sin marcarla: no se sabe si está inactiva', () => {
        expect(opcionesConActual(null, { id: 7, nombre: 'Gobernación' })).toEqual([{ valor: '7', etiqueta: 'Gobernación' }]);
        expect(opcionesConActual(undefined, { id: 7, nombre: 'Gobernación' })).toEqual([{ valor: '7', etiqueta: 'Gobernación' }]);
        expect(opcionesConActual(null, null)).toEqual([]);
    });

    it('la actual activa no se repite', () => {
        expect(opcionesConActual(entidades, { id: 1, nombre: 'Alcaldía' })).toHaveLength(2);
    });
});

describe('formularioProceso', () => {
    const proceso = {
        id: 5,
        objeto: 'Construcción de la sede',
        modalidad: { id: 1, nombre: 'Licitación pública', activo: true },
        plataforma: 'secop_ii',
        codigo_proceso: 'LP-001',
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
        expect(formularioProceso(proceso)).toEqual({
            objeto: 'Construcción de la sede',
            modalidad_id: '1',
            plataforma: 'secop_ii',
            codigo_proceso: 'LP-001',
            url_proceso: '',
            entidad_contratante_id: '3',
            monto: '1.500.000',
        });
    });

    it('un proceso anterior sin plataforma, entidad ni valor la elige al editar', () => {
        expect(formularioProceso({ ...proceso, plataforma: null, entidad_contratante: null, monto: null })).toMatchObject({
            plataforma: '', entidad_contratante_id: '', monto: '',
        });
    });
});

describe('validarProceso', () => {
    const valido = {
        ...PROCESO_VACIO, objeto: 'Interventoría', modalidad_id: '4', plataforma: 'secop_ii',
    };

    it('acepta lo mínimo: objeto, modalidad y plataforma', () => {
        expect(validarProceso(valido)).toEqual({});
    });

    it('exige objeto, modalidad y plataforma con los mensajes del servidor', () => {
        expect(validarProceso({ ...PROCESO_VACIO, objeto: '  ' })).toEqual({
            objeto: 'Escribe el objeto del proceso.',
            modalidad_id: 'Elige la modalidad del proceso.',
            plataforma: 'Elige la plataforma del proceso.',
        });
        expect(validarProceso({ ...valido, plataforma: 'otra' })).toEqual({ plataforma: 'Elige una plataforma de la lista.' });
    });

    it('revisa el enlace y los largos', () => {
        expect(validarProceso({ ...valido, url_proceso: 'secop.gov.co/x' })).toEqual({ url_proceso: 'Escribe un enlace completo que empiece por http:// o https://.' });
        expect(validarProceso({ ...valido, url_proceso: `https://secop.gov.co/${'x'.repeat(480)}` })).toEqual({ url_proceso: 'El enlace admite hasta 500 caracteres.' });
        expect(validarProceso({ ...valido, codigo_proceso: 'x'.repeat(101) })).toEqual({ codigo_proceso: 'El número del proceso admite hasta 100 caracteres.' });
        expect(validarProceso({ ...valido, objeto: 'x'.repeat(5001) })).toEqual({ objeto: 'El objeto admite hasta 5000 caracteres.' });
    });

    it('el valor admite hasta 13 cifras enteras', () => {
        expect(validarProceso({ ...valido, monto: '9.999.999.999.999,99' })).toEqual({});
        expect(validarProceso({ ...valido, monto: '10.000.000.000.000' })).toEqual({ monto: 'El valor admite hasta 13 cifras enteras y 2 decimales.' });
    });
});

describe('datosProceso', () => {
    it('envía todos los campos; los opcionales vacíos como null', () => {
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

    it('al editar envía el `actualizado` del proceso que se abrió; al crear, no', () => {
        const formulario = { ...PROCESO_VACIO, objeto: 'Obra', modalidad_id: '2', plataforma: 'ninguna' };
        expect(datosProceso(formulario, { id: 4, actualizado: '2026-11-17T10:00:00-05:00' })).toMatchObject({ actualizado: '2026-11-17T10:00:00-05:00' });
        expect(datosProceso(formulario, { id: 4, actualizado: null })).toMatchObject({ actualizado: null });
        expect(datosProceso(formulario)).not.toHaveProperty('actualizado');
    });
});

describe('datosEstado', () => {
    it('envía el estado, la observación sin espacios o null, y el `actualizado` del proceso', () => {
        expect(datosEstado({ id: 4, actualizado: '2026-11-17T10:00:00-05:00' }, 'publicado', '  En SECOP II ')).toEqual({
            estado: 'publicado', observacion: 'En SECOP II', actualizado: '2026-11-17T10:00:00-05:00',
        });
        expect(datosEstado({ id: 4, actualizado: null }, 'cancelado', '   ')).toEqual({ estado: 'cancelado', observacion: null, actualizado: null });
    });
});

describe('esEdicionDesactualizada', () => {
    it('solo el 409 del servidor', () => {
        expect(esEdicionDesactualizada({ response: { status: 409, data: { mensaje: 'Otra persona cambió este proceso…' } } })).toBe(true);
        expect(esEdicionDesactualizada({ response: { status: 422, data: {} } })).toBe(false);
        expect(esEdicionDesactualizada(new Error('sin red'))).toBe(false);
        expect(esEdicionDesactualizada(null)).toBe(false);
    });
});

describe('indiceFocoAtrapado', () => {
    it('Tab en el último vuelve al primero; Mayús+Tab en el primero va al último', () => {
        expect(indiceFocoAtrapado(4, 5, false)).toBe(0);
        expect(indiceFocoAtrapado(0, 5, true)).toBe(4);
    });

    it('en medio el navegador sigue solo', () => {
        expect(indiceFocoAtrapado(2, 5, false)).toBeNull();
        expect(indiceFocoAtrapado(2, 5, true)).toBeNull();
    });

    it('desde el propio diálogo entra por el primero o el último', () => {
        expect(indiceFocoAtrapado(-1, 5, false)).toBe(0);
        expect(indiceFocoAtrapado(-1, 5, true)).toBe(4);
    });

    it('sin controles, el foco se queda en el diálogo', () => {
        expect(indiceFocoAtrapado(-1, 0, false)).toBe(-1);
    });
});
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/contratacion.test.js`
Expected: FALLAN 41 y pasan 16: `utils/contratacion.js` todavía no tiene las funciones de procesos (`puedeEditarProcesos`, plataformas, estados, valor, avisos).

- [ ] **Paso 3: Implementar**

Crear `resources/js/components/ProcesosProyecto.jsx`:

```jsx
import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowRightArrowLeft, faArrowUpRightFromSquare, faGavel, faPen, faPlus, faTrash, faXmark } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
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
 * Diálogo modal accesible: el foco entra al abrir, Tab no sale de él, Escape llama a `onEscape`,
 * un clic en el fondo a `onFondo` y, al cerrar, el foco vuelve a donde estaba (el botón
 * "Procesos" de la fila).
 */
const Dialogo = ({ titulo, subtitulo, onCerrar, onEscape, onFondo, children }) => {
    const caja = useRef(null);
    const idTitulo = useId();

    useEffect(() => {
        const anterior = document.activeElement;
        caja.current?.focus();
        return () => {
            if (anterior instanceof HTMLElement && document.contains(anterior)) anterior.focus();
        };
    }, []);

    const alPulsar = (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            onEscape();
            return;
        }
        if (e.key !== 'Tab') return;
        const focos = [...caja.current.querySelectorAll(ENFOCABLES)].filter((el) => el.getClientRects().length > 0);
        const destino = indiceFocoAtrapado(focos.indexOf(document.activeElement), focos.length, e.shiftKey);
        if (destino === null) return;
        e.preventDefault();
        (focos[destino] ?? caja.current).focus();
    };

    return (
        <div className="ct-dialogo-fondo" onMouseDown={(e) => { if (e.target === e.currentTarget) onFondo(); }}>
            <div ref={caja} className="ct-dialogo" role="dialog" aria-modal="true" aria-labelledby={idTitulo} tabIndex={-1} onKeyDown={alPulsar}>
                <header className="ct-dialogo-cabecera">
                    <div>
                        {subtitulo && <span className="gp-etiqueta">{subtitulo}</span>}
                        <h2 id={idTitulo}>{titulo}</h2>
                    </div>
                    <button type="button" className="gp-icono" onClick={onCerrar} aria-label="Cerrar">
                        <FontAwesomeIcon icon={faXmark} />
                    </button>
                </header>
                <div className="ct-dialogo-cuerpo">{children}</div>
            </div>
        </div>
    );
};

const Campo = ({ etiqueta, error, idError, ancho, children }) => (
    <label className={`pl-campo${ancho ? ' pl-campo-ancho' : ''}`}>
        <span>{etiqueta}</span>
        {children}
        {error && <small id={idError} className="ct-error-campo">{error}</small>}
    </label>
);

/**
 * Formulario de un proceso. Valida antes de enviar con las reglas del servidor; `onGuardar`
 * devuelve los errores por campo del servidor, o null. Escape cancela sin cerrar el diálogo.
 */
const FormularioProceso = ({ proceso, modalidades, entidades, guardando, onGuardar, onCancelar }) => {
    const [f, setF] = useState(() => formularioProceso(proceso));
    const [errores, setErrores] = useState({});
    const formulario = useRef(null);
    const id = useId();

    const enfocarPrimerError = (lista) => {
        const campo = CAMPOS_PROCESO.find((c) => lista[c]);
        if (campo) formulario.current?.querySelector(`[name="${campo}"]`)?.focus();
    };

    const enviar = async (e) => {
        e.preventDefault();
        const locales = validarProceso(f);
        setErrores(locales);
        if (Object.keys(locales).length) {
            enfocarPrimerError(locales);
            return;
        }
        const delServidor = await onGuardar(f);
        if (delServidor && Object.keys(delServidor).length) {
            setErrores(delServidor);
            enfocarPrimerError(delServidor);
        }
    };

    const cambiar = (campo, valor) => {
        setF((actual) => ({ ...actual, [campo]: valor }));
        setErrores((actual) => (actual[campo] ? { ...actual, [campo]: undefined } : actual));
    };

    const alPulsar = (e) => {
        if (e.key !== 'Escape') return;
        e.stopPropagation();
        if (!guardando) onCancelar();
    };

    // Propiedades comunes de cada control: nombre, valor, aviso de error y su descripción.
    const control = (campo) => ({
        name: campo,
        value: f[campo],
        'aria-invalid': Boolean(errores[campo]),
        'aria-describedby': errores[campo] ? `${id}-${campo}` : undefined,
        onChange: (e) => cambiar(campo, e.target.value),
    });
    const campo = (nombre, etiqueta, hijo, ancho = false) => (
        <Campo etiqueta={etiqueta} error={errores[nombre]} idError={`${id}-${nombre}`} ancho={ancho}>{hijo}</Campo>
    );

    return (
        <form ref={formulario} className="gp-panel pl-formulario" onSubmit={enviar} onKeyDown={alPulsar} noValidate aria-labelledby={`${id}-titulo`}>
            <h3 id={`${id}-titulo`} className="ct-formulario-titulo">{proceso ? 'Editar proceso' : 'Nuevo proceso'}</h3>
            {campo('objeto', 'Objeto', <textarea rows={3} maxLength={5000} autoFocus {...control('objeto')} />, true)}
            {campo('modalidad_id', 'Modalidad', (
                <select {...control('modalidad_id')}>
                    <option value="">Elige la modalidad</option>
                    {opcionesConActual(modalidades, proceso?.modalidad).map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                </select>
            ))}
            {campo('plataforma', 'Plataforma', (
                <select {...control('plataforma')}>
                    <option value="">Elige la plataforma</option>
                    {PLATAFORMAS.map(([valor, texto]) => <option key={valor} value={valor}>{texto}</option>)}
                </select>
            ))}
            {campo('codigo_proceso', 'Número del proceso (opcional)', <input maxLength={100} {...control('codigo_proceso')} />)}
            {campo('url_proceso', 'Enlace al proceso (opcional)', (
                <input type="url" inputMode="url" maxLength={500} placeholder="https://" {...control('url_proceso')} />
            ))}
            {campo('entidad_contratante_id', 'Entidad contratante (opcional)', (
                <select {...control('entidad_contratante_id')}>
                    <option value="">Sin entidad</option>
                    {opcionesConActual(entidades, proceso?.entidad_contratante).map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                </select>
            ))}
            {campo('monto', 'Valor en pesos (opcional)', (
                <input inputMode="decimal" placeholder="0" {...control('monto')} onChange={(e) => cambiar('monto', valorEnEdicion(e.target.value, f.monto))} />
            ))}
            <div className="pl-formulario-acciones">
                <button type="button" className="gp-boton" onClick={onCancelar} disabled={guardando}>Cancelar</button>
                <button type="submit" className="gp-boton gp-boton-primario" disabled={guardando}>Guardar proceso</button>
            </div>
        </form>
    );
};

/**
 * Cambio de estado de un proceso, con observación opcional. Nunca se bloquea: si el cambio es
 * inusual (sale de un cierre o vuelve atrás en el flujo) se pide confirmación antes de enviarlo.
 */
const FormularioEstado = ({ proceso, guardando, onGuardar, onCancelar }) => {
    const [estado, setEstado] = useState('');
    const [observacion, setObservacion] = useState('');
    const [errores, setErrores] = useState({});
    const id = useId();

    const enviar = async (e) => {
        e.preventDefault();
        if (!estado) {
            setErrores({ estado: 'Elige el estado del proceso.' });
            return;
        }
        const delServidor = await onGuardar(estado, observacion);
        if (delServidor && Object.keys(delServidor).length) setErrores(delServidor);
    };

    const alPulsar = (e) => {
        if (e.key !== 'Escape') return;
        e.stopPropagation();
        if (!guardando) onCancelar();
    };

    return (
        <form className="pl-formulario ct-cambio-estado" onSubmit={enviar} onKeyDown={alPulsar} noValidate aria-label="Cambiar estado del proceso">
            <Campo etiqueta={`Nuevo estado (ahora: ${etiquetaEstadoProceso(proceso.estado)})`} error={errores.estado} idError={`${id}-estado`}>
                <select
                    autoFocus
                    value={estado}
                    aria-invalid={Boolean(errores.estado)}
                    aria-describedby={errores.estado ? `${id}-estado` : undefined}
                    onChange={(e) => { setEstado(e.target.value); setErrores({}); }}
                >
                    <option value="">Elige el estado</option>
                    {ESTADOS_PROCESO.filter(([valor]) => valor !== proceso.estado).map(([valor, texto]) => <option key={valor} value={valor}>{texto}</option>)}
                </select>
            </Campo>
            <Campo etiqueta="Observación (opcional)" error={errores.observacion} idError={`${id}-observacion`} ancho>
                <textarea
                    rows={2}
                    maxLength={MAXIMO_OBSERVACION}
                    value={observacion}
                    aria-invalid={Boolean(errores.observacion)}
                    aria-describedby={errores.observacion ? `${id}-observacion` : undefined}
                    onChange={(e) => setObservacion(e.target.value)}
                />
            </Campo>
            <div className="pl-formulario-acciones">
                <button type="button" className="gp-boton" onClick={onCancelar} disabled={guardando}>Cancelar</button>
                <button type="submit" className="gp-boton gp-boton-primario" disabled={guardando}>Cambiar estado</button>
            </div>
        </form>
    );
};

const NumeroProceso = ({ proceso }) => {
    const { texto, enlace } = numeroProceso(proceso);
    if (!enlace) return texto;
    return (
        <a href={enlace} target="_blank" rel="noopener noreferrer" className="ct-enlace">
            {texto} <FontAwesomeIcon icon={faArrowUpRightFromSquare} aria-hidden="true" />
            <span className="ct-oculto"> (se abre en otra pestaña)</span>
        </a>
    );
};

/**
 * Procesos de contratación de un proyecto (la obra, la interventoría…), en un diálogo que abre
 * Parámetros → Proyectos. Un Administrador o Gestor los crea, edita, cambia de estado y elimina;
 * los demás solo los consultan. El backend aplica los mismos permisos y reglas.
 */
const ProcesosProyecto = ({ proyecto, user, onCerrar }) => {
    const [procesos, setProcesos] = useState(null);
    const [error, setError] = useState(null);
    const [modalidades, setModalidades] = useState(null);
    const [entidades, setEntidades] = useState(null);
    const [errorCatalogos, setErrorCatalogos] = useState(null);
    // null; { tipo: 'proceso', proceso: null | proceso } o { tipo: 'estado', proceso }.
    const [modo, setModo] = useState(null);
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    const retorno = useRef(null); // data-foco del control que recupera el foco al volver a la lista
    const turno = useRef(0);
    const turnoCatalogos = useRef(0);
    const edita = puedeEditarProcesos(user);

    // Solo vale la última respuesta, y solo si es de este proyecto: las tardías se descartan.
    const cargar = useCallback(async () => {
        const numero = ++turno.current;
        try {
            const { data } = await axios.get(`/proyectos/${proyecto.id}/procesos`);
            if (numero !== turno.current || String(data?.proyecto?.id) !== String(proyecto.id)) return;
            setProcesos(data.procesos ?? []);
            setError(null);
        } catch (e) {
            if (numero === turno.current) setError(mensajeApi(e));
        }
    }, [proyecto.id]);

    useEffect(() => {
        setProcesos(null);
        setError(null);
        setModo(null);
        cargar();
        return () => { turno.current += 1; };
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
        cargarCatalogos();
        return () => { turnoCatalogos.current += 1; };
    }, [cargarCatalogos]);

    // El botón desaparece mientras se reintenta: el foco pasa al diálogo para no perderse.
    const reintentarCatalogos = () => {
        contenedor.current?.closest('[role="dialog"]')?.focus();
        cargarCatalogos({ conModalidades: modalidades === null, conEntidades: entidades === null });
    };

    // Al volver a la lista, el foco regresa al botón que abrió el formulario (o al diálogo).
    useEffect(() => {
        if (modo || ocupado || !retorno.current) return;
        const destino = contenedor.current?.querySelector(`[data-foco="${retorno.current}"]:not(:disabled)`)
            ?? contenedor.current?.closest('[role="dialog"]');
        destino?.focus();
        retorno.current = null;
    }, [modo, ocupado, procesos]);

    const abrir = (nuevoModo, foco) => {
        retorno.current = foco;
        setModo(nuevoModo);
    };

    // Escape cierra primero el formulario abierto y, si no hay, el diálogo. Un clic en el fondo
    // no descarta un formulario a medio llenar.
    const alEscape = () => {
        if (ocupado) return;
        if (modo) setModo(null);
        else onCerrar();
    };
    const alFondo = () => {
        if (!ocupado && !modo) onCerrar();
    };

    // Otra persona cambió el proceso mientras estaba abierto: se avisa y se recarga la lista. El
    // formulario sigue abierto para copiar lo escrito; al reabrirlo trae lo último.
    const avisarDesactualizado = (e) => Promise.all([
        cargar(),
        Swal.fire({ icon: 'warning', titleText: 'No se guardó tu cambio', text: mensajeApi(e) }),
    ]);

    const guardar = (formulario) => ejecutar(async () => {
        const proceso = modo?.proceso ?? null;
        try {
            if (proceso) await axios.put(`/procesos/${proceso.id}`, datosProceso(formulario, proceso));
            else await axios.post(`/proyectos/${proyecto.id}/procesos`, datosProceso(formulario));
            setModo(null);
            await cargar();
            return null;
        } catch (e) {
            if (esEdicionDesactualizada(e)) {
                await avisarDesactualizado(e);
                return null;
            }
            const errores = erroresPorCampo(e, CAMPOS_PROCESO);
            if (!errores || errores.otros.length || !Object.keys(errores.campos).length) avisarError('No se pudo guardar el proceso', e);
            return errores?.campos ?? null;
        }
    });

    const cambiarEstado = (estado, observacion) => ejecutar(async () => {
        const { proceso } = modo;
        const aviso = avisoCambioEstado(proceso.estado, estado);
        if (aviso) {
            const { isConfirmed } = await Swal.fire({
                icon: 'warning',
                titleText: aviso.titulo,
                text: aviso.texto,
                showCancelButton: true,
                confirmButtonText: 'Sí, cambiar el estado',
                cancelButtonText: 'Cancelar',
            });
            if (!isConfirmed) return null;
        }
        try {
            await axios.post(`/procesos/${proceso.id}/estado`, datosEstado(proceso, estado, observacion));
            setModo(null);
            await cargar();
            return null;
        } catch (e) {
            if (esEdicionDesactualizada(e)) {
                await avisarDesactualizado(e);
                return null;
            }
            const errores = erroresPorCampo(e, ['estado', 'observacion']);
            if (!errores || errores.otros.length || !Object.keys(errores.campos).length) avisarError('No se pudo cambiar el estado', e);
            return errores?.campos ?? null;
        }
    });

    const eliminar = (proceso) => ejecutar(async () => {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            titleText: '¿Eliminar este proceso?',
            text: proceso.objeto,
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        retorno.current = 'nuevo';
        try {
            await axios.delete(`/procesos/${proceso.id}`);
        } catch (e) {
            // Con contratos vinculados el servidor lo rechaza y explica por qué.
            avisarError('No se pudo eliminar el proceso', e);
            retorno.current = `eliminar:${proceso.id}`;
        }
        await cargar();
    });

    const editandoProceso = modo?.tipo === 'proceso';

    return (
        <Dialogo titulo={proyecto.nombre} subtitulo="Procesos de contratación" onCerrar={() => { if (!ocupado) onCerrar(); }} onEscape={alEscape} onFondo={alFondo}>
            <div className="ct-seccion" ref={contenedor}>
                {errorCatalogos && (
                    <p className="pl-aviso-suave" role="status">
                        {errorCatalogos} <button type="button" className="gp-boton" onClick={reintentarCatalogos}>Reintentar</button>
                    </p>
                )}

                {editandoProceso ? (
                    <FormularioProceso
                        proceso={modo.proceso}
                        modalidades={modalidades}
                        entidades={entidades}
                        guardando={ocupado}
                        onGuardar={guardar}
                        onCancelar={() => setModo(null)}
                    />
                ) : (
                    <>
                        <div className="ct-barra">
                            <p className="pl-ayuda">
                                {edita
                                    ? 'Un proyecto puede tener varios procesos, por ejemplo la obra y la interventoría.'
                                    : 'Solo un Administrador o Gestor puede registrar o cambiar procesos.'}
                            </p>
                            {edita && (
                                <button
                                    type="button"
                                    className="gp-boton gp-boton-primario"
                                    data-foco="nuevo"
                                    disabled={ocupado || procesos === null || modalidades === null}
                                    onClick={() => abrir({ tipo: 'proceso', proceso: null }, 'nuevo')}
                                >
                                    <FontAwesomeIcon icon={faPlus} /> Nuevo proceso
                                </button>
                            )}
                        </div>

                        {error && (
                            <p className="pl-error" role="alert">
                                {error} <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>
                            </p>
                        )}
                        {procesos === null && !error && <p className="gp-vacio" role="status">Cargando procesos…</p>}
                        {procesos !== null && procesos.length === 0 && (
                            <p className="gp-vacio">
                                <FontAwesomeIcon icon={faGavel} aria-hidden="true" /> Este proyecto aún no tiene procesos de contratación.
                            </p>
                        )}

                        {procesos !== null && procesos.length > 0 && (
                            <ul className="pl-lista" aria-label="Procesos del proyecto">
                                {procesos.map((p) => (
                                    <li key={p.id} className="gp-panel ct-proceso">
                                        <div className="ct-proceso-cabecera">
                                            <p className="ct-proceso-objeto">{p.objeto || 'Sin objeto'}</p>
                                            <span className={`ct-estado ct-estado-${tonoEstado(p.estado)}`}>{etiquetaEstadoProceso(p.estado)}</span>
                                        </div>
                                        <dl className="pl-datos">
                                            <div>
                                                <dt>Modalidad</dt>
                                                <dd>{p.modalidad ? `${p.modalidad.nombre}${p.modalidad.activo ? '' : ' (inactiva)'}` : '—'}</dd>
                                            </div>
                                            <div><dt>Plataforma</dt><dd>{etiquetaPlataforma(p.plataforma)}</dd></div>
                                            <div><dt>Número</dt><dd><NumeroProceso proceso={p} /></dd></div>
                                            <div><dt>Entidad contratante</dt><dd>{p.entidad_contratante?.nombre ?? '—'}</dd></div>
                                            <div><dt>Valor</dt><dd className="ct-valor">{textoValor(p.monto)}</dd></div>
                                            <div><dt>Contratos vinculados</dt><dd>{textoContratos(p.contratos)}</dd></div>
                                        </dl>
                                        {modo?.tipo === 'estado' && modo.proceso.id === p.id ? (
                                            <FormularioEstado proceso={p} guardando={ocupado} onGuardar={cambiarEstado} onCancelar={() => setModo(null)} />
                                        ) : edita && (
                                            <div className="pl-tarjeta-acciones">
                                                <button type="button" className="gp-boton" data-foco={`editar:${p.id}`} disabled={ocupado || modo !== null || modalidades === null}
                                                    onClick={() => abrir({ tipo: 'proceso', proceso: p }, `editar:${p.id}`)}>
                                                    <FontAwesomeIcon icon={faPen} /> Editar
                                                </button>
                                                <button type="button" className="gp-boton" data-foco={`estado:${p.id}`} disabled={ocupado || modo !== null}
                                                    onClick={() => abrir({ tipo: 'estado', proceso: p }, `estado:${p.id}`)}>
                                                    <FontAwesomeIcon icon={faArrowRightArrowLeft} /> Cambiar estado
                                                </button>
                                                <button type="button" className="gp-boton" data-foco={`eliminar:${p.id}`} disabled={ocupado || modo !== null} onClick={() => eliminar(p)}>
                                                    <FontAwesomeIcon icon={faTrash} /> Eliminar
                                                </button>
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </>
                )}
            </div>
        </Dialogo>
    );
};

export default ProcesosProyecto;
```

En `resources/css/Contratacion.css`, reemplazar (1 de 1):

```css
.ct-etapa-texto { display: grid; gap: .15rem; min-width: 0; }
.ct-etapa-texto strong { font-size: .92rem; color: var(--gp-texto); }
.ct-etapa-texto small { font-size: .82rem; color: var(--gp-texto-2); overflow-wrap: anywhere; }
```

por:

```css
.ct-etapa-texto { display: grid; gap: .15rem; min-width: 0; }
.ct-etapa-texto strong { font-size: .92rem; color: var(--gp-texto); }
.ct-etapa-texto small { font-size: .82rem; color: var(--gp-texto-2); overflow-wrap: anywhere; }

/* ---------- Procesos de contratación de un proyecto ---------- */

/* Botón "Procesos" de la fila de Parámetros → Proyectos: azul, junto a Contrato (ámbar) y Expediente (verde). */
.btn-add-contrato-list.btn-procesos { --accion: var(--gp-azul-texto); }

.ct-oculto { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

/* Por debajo de SweetAlert (2100), que confirma y avisa encima del diálogo. */
.ct-dialogo-fondo {
    position: fixed;
    inset: 0;
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(4, 9, 7, .72);
    backdrop-filter: blur(3px);
}
.ct-dialogo {
    display: flex;
    flex-direction: column;
    width: min(60rem, 100%);
    max-height: 90dvh;
    background: var(--gp-fondo-2);
    border: 1px solid var(--gp-linea-fuerte);
    border-radius: var(--gp-radio-grande);
    box-shadow: var(--gp-sombra);
}
.ct-dialogo:focus { outline: none; }
.ct-dialogo-cabecera { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1rem 1.2rem .7rem; border-bottom: 1px solid var(--gp-linea); }
.ct-dialogo-cabecera h2 { margin: .1rem 0 0; font-size: 1.25rem; overflow-wrap: anywhere; }
.ct-dialogo-cuerpo { overflow-y: auto; padding: 1rem 1.2rem 1.2rem; }

.ct-proceso { display: grid; gap: .7rem; padding: .95rem 1.05rem; }
.ct-proceso-cabecera { display: flex; flex-wrap: wrap; gap: .5rem 1rem; align-items: flex-start; justify-content: space-between; }
.ct-proceso-objeto { flex: 1 1 20rem; min-width: 0; margin: 0; font-weight: 650; color: var(--gp-texto); white-space: pre-line; overflow-wrap: anywhere; }
.ct-proceso .pl-datos { margin: 0; }
.ct-valor { font-family: var(--gp-fuente-datos); }
.ct-enlace { display: inline-flex; gap: .3rem; align-items: baseline; color: var(--gp-azul-texto); overflow-wrap: anywhere; }
.ct-enlace svg { font-size: .7em; }
.ct-cambio-estado { padding: .8rem 0 0; border-top: 1px solid var(--gp-linea); }

.ct-estado { flex-shrink: 0; padding: .12rem .6rem; font-size: .74rem; font-weight: 650; white-space: nowrap; border-radius: 999px; }
.ct-estado-neutro { color: var(--gp-texto-2); background: var(--gp-superficie-3); }
.ct-estado-azul { color: var(--gp-azul-texto); background: var(--gp-azul-suave); }
.ct-estado-ambar { color: var(--gp-ambar); background: var(--gp-ambar-suave); }
.ct-estado-verde { color: var(--gp-verde-texto); background: var(--gp-verde-suave); }
.ct-estado-verde-pleno { color: var(--gp-sobre-acento); background: var(--gp-verde-texto); }
.ct-estado-coral { color: var(--gp-coral); background: var(--gp-coral-suave); }

@media (max-width: 720px) {
    .ct-dialogo-fondo { align-items: flex-end; padding: 0; }
    .ct-dialogo { width: 100%; max-height: 92dvh; border-radius: 14px 14px 0 0; }
    .ct-dialogo-cuerpo { padding: .9rem 1rem 1.1rem; }
}
```

En `resources/js/components/Parametros.jsx`, reemplazar (1 de 7):

```jsx
import React, { useState, useEffect } from 'react';
import { Navigate, useNavigate, useParams } from 'react-router-dom';
import axios from '../axios';
import { faPlus, faTrash, faSave, faTimes, faCalculator, faFile, faInfo, faEdit, faClipboardCheck, faEye } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
import EmojiPicker from 'emoji-picker-react';
```

por:

```jsx
import React, { useState, useEffect } from 'react';
import { Navigate, useNavigate, useParams } from 'react-router-dom';
import axios from '../axios';
import { faPlus, faTrash, faSave, faTimes, faCalculator, faFile, faInfo, faEdit, faClipboardCheck, faEye, faGavel } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
import EmojiPicker from 'emoji-picker-react';
```

En `resources/js/components/Parametros.jsx`, reemplazar (2 de 7):

```jsx
import { alternarFuente, datosFinanciacionDe, financiacionCompleta, fuentesDisponibles, mensajeDeError } from '../utils/fuentesProyecto';
import CatalogoModalidades from './CatalogoModalidades';
import CatalogoEtapasContratacion from './CatalogoEtapasContratacion';

// Componente auxiliar: escucha clics en el mapa y reporta la coordenada al padre.
// Debe vivir dentro de <MapContainer> — el hook useMapEvents solo funciona ahí.
```

por:

```jsx
import { alternarFuente, datosFinanciacionDe, financiacionCompleta, fuentesDisponibles, mensajeDeError } from '../utils/fuentesProyecto';
import CatalogoModalidades from './CatalogoModalidades';
import CatalogoEtapasContratacion from './CatalogoEtapasContratacion';
import ProcesosProyecto from './ProcesosProyecto';

// Componente auxiliar: escucha clics en el mapa y reporta la coordenada al padre.
// Debe vivir dentro de <MapContainer> — el hook useMapEvents solo funciona ahí.
```

En `resources/js/components/Parametros.jsx`, reemplazar (3 de 7):

```jsx
    </header>
);

const Parametros = () => {
    const navigate = useNavigate();
    // La sección vive en la URL: se puede enlazar, recargar y volver con el navegador.
    const { seccion: slugSeccion } = useParams();
```

por:

```jsx
    </header>
);

const Parametros = ({ user }) => {
    const navigate = useNavigate();
    // La sección vive en la URL: se puede enlazar, recargar y volver con el navegador.
    const { seccion: slugSeccion } = useParams();
```

En `resources/js/components/Parametros.jsx`, reemplazar (4 de 7):

```jsx
    const [modalType, setModalType] = useState('');
    // Estados para el modal de contratos
    const [showContratosModal, setShowContratosModal] = useState(false);
    const [proyectoContratos, setProyectoContratos] = useState(null);
    const [showContratoForm, setShowContratoForm] = useState(false);
    const [editingContrato, setEditingContrato] = useState(null);
```

por:

```jsx
    const [modalType, setModalType] = useState('');
    // Estados para el modal de contratos
    const [showContratosModal, setShowContratosModal] = useState(false);
    // Proyecto cuyos procesos de contratación se ven en el diálogo, o null.
    const [proyectoProcesos, setProyectoProcesos] = useState(null);
    const [proyectoContratos, setProyectoContratos] = useState(null);
    const [showContratoForm, setShowContratoForm] = useState(false);
    const [editingContrato, setEditingContrato] = useState(null);
```

En `resources/js/components/Parametros.jsx`, reemplazar (5 de 7):

```jsx
                                                </button>
                                            )}

                                            <button
                                                onClick={() => handleEdit(item, type)}
                                                className="btn-edit"
```

por:

```jsx
                                                </button>
                                            )}

                                            {type === 'proyectos' && (
                                                <button
                                                    onClick={() => setProyectoProcesos(item)}
                                                    className="btn-add-contrato-list btn-procesos"
                                                    title="Procesos de contratación"
                                                >
                                                    <FontAwesomeIcon icon={faGavel} /> Procesos
                                                </button>
                                            )}

                                            <button
                                                onClick={() => handleEdit(item, type)}
                                                className="btn-edit"
```

En `resources/js/components/Parametros.jsx`, reemplazar (6 de 7):

```jsx
                </div>
            )}

            {/* Modal para seleccionar emoji (global) */}
            {showEmojiModal && (
                <div className="emoji-fondo" onClick={closeEmojiModal}>
```

por:

```jsx
                </div>
            )}

            {proyectoProcesos && (
                <ProcesosProyecto
                    key={proyectoProcesos.id}
                    proyecto={proyectoProcesos}
                    user={user}
                    onCerrar={() => setProyectoProcesos(null)}
                />
            )}

            {/* Modal para seleccionar emoji (global) */}
            {showEmojiModal && (
                <div className="emoji-fondo" onClick={closeEmojiModal}>
```

En `resources/js/components/Parametros.jsx`, reemplazar (7 de 7):

```jsx
    const seccion = seccionDesdeSlug(useParams().seccion);
    if (!seccion) return <Navigate to="/parametros/proyectos" replace />;
    const Propia = seccion?.propia ? SECCIONES_PROPIAS[seccion.tab] : null;
    if (!Propia) return <Parametros />;

    return (
        <div className="parametros-container">
```

por:

```jsx
    const seccion = seccionDesdeSlug(useParams().seccion);
    if (!seccion) return <Navigate to="/parametros/proyectos" replace />;
    const Propia = seccion?.propia ? SECCIONES_PROPIAS[seccion.tab] : null;
    if (!Propia) return <Parametros user={user} />;

    return (
        <div className="parametros-container">
```

En `resources/js/utils/contratacion.js`, reemplazar (1 de 2):

```js
// Lógica de presentación del módulo de contratación: catálogos de modalidades y etapas.
// Funciones puras, probadas en contratacion.test.js. Las reglas las valida el backend; aquí se
// adelantan los mismos mensajes para no esperar un 422.
```

por:

```js
// Lógica de presentación del módulo de contratación: catálogos de modalidades y etapas, y
// procesos de contratación de un proyecto.
// Funciones puras, probadas en contratacion.test.js. Las reglas las valida el backend; aquí se
// adelantan los mismos mensajes para no esperar un 422.
```

En `resources/js/utils/contratacion.js`, reemplazar (2 de 2):

```js

/** Modalidades y etapas son femeninas: "Activa" o "Inactiva". */
export const textoEstadoCatalogo = (activo) => (activo ? 'Activa' : 'Inactiva');
```

por:

```js

/** Modalidades y etapas son femeninas: "Activa" o "Inactiva". */
export const textoEstadoCatalogo = (activo) => (activo ? 'Activa' : 'Inactiva');

// ---------- Procesos de contratación ----------

/** Los procesos se escriben con editar-datos: Administrador o Gestor. */
export const puedeEditarProcesos = (usuario) => ['Administrador', 'Gestor'].includes(usuario?.rol);

// Los valores son las constantes de ProcesoLicitacion (contratacion.test.js lo comprueba).
export const PLATAFORMAS = [
    ['secop_ii', 'SECOP II'],
    ['secop_i', 'SECOP I'],
    ['tvec', 'Tienda Virtual'],
    ['ninguna', 'Sin plataforma'],
];

export const ESTADOS_PROCESO = [
    ['preparacion', 'En preparación'],
    ['publicado', 'Publicado'],
    ['evaluacion', 'En evaluación'],
    ['adjudicado', 'Adjudicado'],
    ['desierto', 'Desierto'],
    ['celebrado', 'Celebrado'],
    ['cancelado', 'Cancelado'],
];

/** Estados que cierran un proceso: salir de ellos es inusual. */
export const ESTADOS_CIERRE = ['desierto', 'celebrado', 'cancelado'];

// Paso de cada estado en el flujo preparación → publicado → evaluación → adjudicado o desierto →
// celebrado. Cancelado no tiene paso: se puede cancelar desde cualquier estado.
const PASO_FLUJO = { preparacion: 0, publicado: 1, evaluacion: 2, adjudicado: 3, desierto: 3, celebrado: 4 };

const TONOS_ESTADO = {
    preparacion: 'neutro',
    publicado: 'azul',
    evaluacion: 'ambar',
    adjudicado: 'verde',
    celebrado: 'verde-pleno',
    desierto: 'coral',
    cancelado: 'coral',
};

export const MAXIMO_OBSERVACION = 2000;

const etiqueta = (lista, valor) => lista.find(([v]) => v === valor)?.[1];

export const etiquetaPlataforma = (valor) => etiqueta(PLATAFORMAS, valor) ?? '—';
export const etiquetaEstadoProceso = (valor) => etiqueta(ESTADOS_PROCESO, valor) ?? (valor || '—');
/** Tono de la insignia de estado; la clase CSS es ct-estado-<tono>. */
export const tonoEstado = (valor) => TONOS_ESTADO[valor] ?? 'neutro';

/**
 * El cambio de estado nunca se bloquea (el checklist solo avisa), pero salir de un estado de
 * cierre o volver atrás en el flujo pide confirmación. Devuelve { titulo, texto } del aviso, o
 * null si el cambio es el esperado.
 */
export const avisoCambioEstado = (anterior, nuevo) => {
    if (!anterior || !nuevo || anterior === nuevo) return null;
    const de = etiquetaEstadoProceso(anterior);
    const a = etiquetaEstadoProceso(nuevo);
    if (ESTADOS_CIERRE.includes(anterior)) {
        return {
            titulo: 'El proceso ya está cerrado',
            texto: `"${de}" es un estado de cierre. ¿Seguro que quieres pasar el proceso a "${a}"? El cambio queda en el historial.`,
        };
    }
    if (anterior in PASO_FLUJO && nuevo in PASO_FLUJO && PASO_FLUJO[nuevo] < PASO_FLUJO[anterior]) {
        return {
            titulo: 'El proceso vuelve atrás',
            texto: `"${a}" es un paso anterior a "${de}" en el flujo del proceso. ¿Seguro que quieres cambiarlo? El cambio queda en el historial.`,
        };
    }
    return null;
};

// Valor en pesos. La columna es decimal(15, 2): 13 cifras enteras y 2 decimales. En el formulario
// se escribe como en Colombia, con punto de miles y coma decimal; a la API va "1234567.5".
const CIFRAS_ENTERAS = 13;

/**
 * Sin coma, un punto seguido de 1 o 2 cifras al final es el decimal: "1234567.89" o "150.5", como
 * llega un valor pegado de una hoja de cálculo. Con 3 cifras ("1.500") sigue siendo de miles.
 */
const conComaDecimal = (texto) => (texto.includes(',') ? texto : texto.replace(/\.(\d{1,2})$/, ',$1'));

/** `texto` es `anterior` con un tramo seguido borrado (retroceso, suprimir o cortar). */
const esBorrado = (texto, anterior) => {
    if (texto.length >= anterior.length) return false;
    let inicio = 0;
    while (inicio < texto.length && texto[inicio] === anterior[inicio]) inicio += 1;
    let fin = 0;
    while (fin < texto.length - inicio && texto[texto.length - 1 - fin] === anterior[anterior.length - 1 - fin]) fin += 1;
    return inicio + fin === texto.length;
};

/**
 * Lo que el usuario escribe en el valor, con los miles agrupados: "1234567,891" → "1.234.567,89".
 * Un punto que se teclea es de miles (se escribe "1.500.000" sin romper nada): el decimal se
 * teclea con coma. `anterior` es lo que mostraba el campo: al borrar de "1.234" queda "1.23", que
 * no es un decimal.
 */
export const valorEnEdicion = (texto, anterior = '') => {
    const crudo = String(texto ?? '').replace(/[^\d.,]/g, '');
    const limpio = (esBorrado(crudo, String(anterior ?? '')) ? crudo : conComaDecimal(crudo)).replace(/[^\d,]/g, '');
    if (!limpio) return '';
    const coma = limpio.indexOf(',');
    const enteros = (coma === -1 ? limpio : limpio.slice(0, coma)).replace(/^0+(?=\d)/, '');
    const agrupados = (enteros || '0').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    if (coma === -1) return agrupados;
    return `${agrupados},${limpio.slice(coma + 1).replace(/,/g, '').slice(0, 2)}`;
};

/** El valor del formulario como lo espera la API: "1.234.567,5" → "1234567.5"; vacío → null. */
export const valorParaApi = (texto) => {
    const limpio = conComaDecimal(String(texto ?? '').replace(/[^\d.,]/g, '')).replace(/[^\d,]/g, '');
    if (!limpio.replace(/,/g, '')) return null;
    const [enteros, decimales = ''] = limpio.split(',');
    const entero = enteros.replace(/^0+(?=\d)/, '') || '0';
    return decimales ? `${entero}.${decimales}` : entero;
};

/** El valor de la API para el formulario: "1234567.50" → "1.234.567,50"; "1000.00" → "1.000". */
export const valorDesdeApi = (monto) => {
    if (monto === null || monto === undefined || monto === '') return '';
    const [enteros, decimales = ''] = String(monto).split('.');
    return valorEnEdicion(/^0*$/.test(decimales) ? enteros : `${enteros},${decimales}`);
};

const PESOS = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 });
const PESOS_CON_CENTAVOS = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** "$ 1.234.567" o, con centavos, "$ 1.234.567,50"; sin valor, "—". */
export const textoValor = (monto) => {
    if (monto === null || monto === undefined || monto === '') return '—';
    const numero = Number(monto);
    if (!Number.isFinite(numero)) return '—';
    return (Number.isInteger(numero) ? PESOS : PESOS_CON_CENTAVOS).format(numero);
};

export const textoContratos = (cantidad) => {
    const n = Number(cantidad) || 0;
    if (n === 0) return 'Sin contratos';
    return n === 1 ? '1 contrato' : `${n} contratos`;
};

/** Solo un enlace http(s) completo se muestra como enlace: nunca javascript: ni rutas relativas. */
export const esEnlaceWeb = (texto) => {
    const valor = String(texto ?? '').trim();
    if (!/^https?:\/\//i.test(valor)) return false;
    try {
        return Boolean(new URL(valor).hostname);
    } catch {
        return false;
    }
};

/** El número del proceso y su enlace: sin número pero con enlace, "Ver proceso". */
export const numeroProceso = (proceso) => {
    const enlace = esEnlaceWeb(proceso?.url_proceso) ? String(proceso.url_proceso).trim() : null;
    const codigo = String(proceso?.codigo_proceso ?? '').trim();
    return { texto: codigo || (enlace ? 'Ver proceso' : '—'), enlace };
};

// La API de entidades devuelve `activo` como 1/0 (consulta sin modelo); la de modalidades, booleano.
const esActivo = (valor) => valor === true || valor === 1 || valor === '1';

/**
 * Opciones de un select de catálogo: las activas y, si `actual` (lo que el proceso ya tiene) está
 * inactiva o ya no viene en la lista, también ella, marcada "(inactiva)". Con la lista sin cargar
 * (null) no se sabe: se ofrece la actual, sin marca, para no perderla.
 */
export const opcionesConActual = (items, actual) => {
    const lista = items ?? [];
    const opciones = lista.filter((i) => esActivo(i.activo)).map((i) => ({ valor: String(i.id), etiqueta: i.nombre }));
    if (actual?.id !== undefined && actual?.id !== null && !opciones.some((o) => o.valor === String(actual.id))) {
        const enLista = lista.find((i) => String(i.id) === String(actual.id));
        const nombre = enLista?.nombre ?? actual.nombre;
        opciones.push({ valor: String(actual.id), etiqueta: items === null || items === undefined ? nombre : `${nombre} (inactiva)` });
    }
    return opciones;
};

export const PROCESO_VACIO = {
    objeto: '',
    modalidad_id: '',
    plataforma: '',
    codigo_proceso: '',
    url_proceso: '',
    entidad_contratante_id: '',
    monto: '',
};

/** Los campos del formulario, en su orden: también son los nombres de la API. */
export const CAMPOS_PROCESO = Object.keys(PROCESO_VACIO);

export const formularioProceso = (proceso) => {
    if (!proceso) return { ...PROCESO_VACIO };
    const id = (valor) => (valor?.id === undefined || valor?.id === null ? '' : String(valor.id));
    return {
        objeto: proceso.objeto ?? '',
        modalidad_id: id(proceso.modalidad),
        // Un proceso anterior a la plataforma obligatoria la elige al editarse.
        plataforma: PLATAFORMAS.some(([v]) => v === proceso.plataforma) ? proceso.plataforma : '',
        codigo_proceso: proceso.codigo_proceso ?? '',
        url_proceso: proceso.url_proceso ?? '',
        entidad_contratante_id: id(proceso.entidad_contratante),
        monto: valorDesdeApi(proceso.monto),
    };
};

/** Errores del formulario por campo, con los mensajes del servidor; {} si se puede enviar. */
export const validarProceso = (formulario) => {
    const errores = {};
    const texto = (campo) => String(formulario[campo] ?? '').trim();
    if (!texto('objeto')) errores.objeto = 'Escribe el objeto del proceso.';
    else if (texto('objeto').length > 5000) errores.objeto = 'El objeto admite hasta 5000 caracteres.';
    if (!texto('modalidad_id')) errores.modalidad_id = 'Elige la modalidad del proceso.';
    if (!texto('plataforma')) errores.plataforma = 'Elige la plataforma del proceso.';
    else if (!PLATAFORMAS.some(([v]) => v === texto('plataforma'))) errores.plataforma = 'Elige una plataforma de la lista.';
    if (texto('codigo_proceso').length > 100) errores.codigo_proceso = 'El número del proceso admite hasta 100 caracteres.';
    if (texto('url_proceso')) {
        if (!esEnlaceWeb(texto('url_proceso'))) errores.url_proceso = 'Escribe un enlace completo que empiece por http:// o https://.';
        else if (texto('url_proceso').length > 500) errores.url_proceso = 'El enlace admite hasta 500 caracteres.';
    }
    const monto = valorParaApi(formulario.monto);
    if (monto !== null && monto.split('.')[0].length > CIFRAS_ENTERAS) {
        errores.monto = `El valor admite hasta ${CIFRAS_ENTERAS} cifras enteras y 2 decimales.`;
    }
    return errores;
};

/**
 * Lo que esperan el POST y el PUT de procesos. Se envían todos los campos: un opcional vacío va
 * como null y así se borra al editar. El estado no se envía: tiene su propia ruta. Al editar
 * `proceso` es el que se abrió: va su `actualizado`, para que el servidor rechace el cambio si
 * otra persona lo modificó entretanto.
 */
export const datosProceso = (formulario, proceso = null) => {
    const texto = (campo) => String(formulario[campo] ?? '').trim() || null;
    const id = (campo) => (texto(campo) === null ? null : Number(texto(campo)));
    return {
        objeto: texto('objeto'),
        modalidad_id: id('modalidad_id'),
        plataforma: texto('plataforma'),
        codigo_proceso: texto('codigo_proceso'),
        url_proceso: texto('url_proceso'),
        entidad_contratante_id: id('entidad_contratante_id'),
        monto: valorParaApi(formulario.monto),
        ...(proceso ? { actualizado: proceso.actualizado ?? null } : {}),
    };
};

/** Lo que espera el cambio de estado: con el `actualizado` del proceso que se abrió. */
export const datosEstado = (proceso, estado, observacion) => ({
    estado,
    observacion: String(observacion ?? '').trim() || null,
    actualizado: proceso?.actualizado ?? null,
});

/** El servidor rechazó el cambio (409) porque otra persona modificó el proceso entretanto. */
export const esEdicionDesactualizada = (error) => error?.response?.status === 409;

/**
 * Foco atrapado en un diálogo: el índice al que debe saltar Tab (o Mayús+Tab) desde `indice` entre
 * `total` controles, o null si el navegador puede seguir solo. -1 es un foco fuera de la lista
 * (el propio diálogo); sin controles, -1 deja el foco en el diálogo.
 */
export const indiceFocoAtrapado = (indice, total, haciaAtras) => {
    if (total <= 0) return -1;
    if (haciaAtras) return indice <= 0 ? total - 1 : null;
    return indice === -1 || indice >= total - 1 ? 0 : null;
};
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/contratacion.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 797 passed.

Run: `npm test`
Expected: 430 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/js/components/ProcesosProyecto.jsx resources/css/Contratacion.css resources/js/components/Parametros.jsx resources/js/utils/contratacion.js resources/js/utils/contratacion.test.js
git commit -m "feat: procurement processes of a project"
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
git commit -m "build: production bundle for procurement stage 1"
```

Si `public/css/app.css` cambió por esta etapa, se agrega también. Lo que no cambió se omite.

---

### Tarea 9: Verificación en la copia descartable

Usa la copia y la foto de la tarea 1. Nunca `gest_pro`.

- [ ] **Paso 1: Migrar la copia y comparar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

Expected: se aplican `2026_11_17_100012_siembra_modalidades`, `2026_11_17_100013_create_etapas_contratacion_table` y `2026_11_17_100014_amplia_procesos_licitacion`.

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion.php" "$TMP_DIR/c1_despues.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_contratacion.php" "$TMP_DIR/c1_antes.json" "$TMP_DIR/c1_despues.json"
```

Expected: `SIN DIFERENCIAS`. Cualquier diferencia detiene la etapa.

```bash
/c/xampp/mysql/bin/mysql -u root --default-character-set=utf8mb4 gestpro_verif_tmp -e "SELECT clave, nombre, orden, activo FROM modalidades ORDER BY orden; SELECT orden, clave, nombre FROM etapas_contratacion ORDER BY orden; SHOW COLUMNS FROM procesos_licitacion;"
```

Expected: las seis modalidades con su clave, las once etapas en orden, y en `procesos_licitacion` las columnas `objeto`, `plataforma`, `url_proceso`, `estado` (por defecto `preparacion`), `usuario_creo_id`, `created_at` y `updated_at`.

- [ ] **Paso 2: Revertir, comparar y volver a migrar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=3 --force
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion.php" "$TMP_DIR/c1_rollback.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_contratacion.php" "$TMP_DIR/c1_antes.json" "$TMP_DIR/c1_rollback.json"
```

Expected: `SIN DIFERENCIAS`. Las modalidades siguen ahí, sin `clave` ni `orden`; ya no existe `etapas_contratacion`.

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT COUNT(*) AS modalidades, SUM(clave IS NOT NULL) AS con_clave FROM modalidades; SELECT COUNT(*) AS etapas FROM etapas_contratacion;"
```

Expected: 6 modalidades, las 6 con clave (no se duplicaron), y 11 etapas.

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion.php" "$TMP_DIR/c1_remigrado.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_contratacion.php" "$TMP_DIR/c1_antes.json" "$TMP_DIR/c1_remigrado.json"
```

Expected: `SIN DIFERENCIAS`.

- [ ] **Paso 3: Probar en el navegador sobre la copia**

Compilar en desarrollo (`npx mix`) si `public/` no tiene el código de esta etapa. Arrancar el servidor desde `public/`, en un puerto libre: `artisan serve` recargaría `gest_pro` desde `.env`, y en el 8765 escucha otra aplicación. Comprobar con `netstat -ano | grep 8796` que nadie más escucha en ese puerto.

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8796 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

En Windows, ese servidor a veces corta `app.js` y la página queda en blanco: basta recargar.

Crear usuarios de prueba solo en la copia; nunca usar sesiones de usuarios reales de `gest_pro`:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="foreach (['verif@gestpro.test' => App\Enums\Rol::Administrador, 'gestor@gestpro.test' => App\Enums\Rol::Gestor, 'consulta@gestpro.test' => App\Enums\Rol::Consulta] as \$e => \$r) { App\Models\User::factory()->create(['email' => \$e, 'rol' => \$r]); }"
```

La contraseña es la predeterminada de `UserFactory`. Entrar por `http://127.0.0.1:8796/GestPro/`.

**Como Administrador:**
1. Parámetros → Etapas de contratación muestra las 11 etapas.
   - Bajar la primera y volver a subirla cambia el orden y lo guarda; el foco queda en la flecha.
   - Crear una etapa, editarla y desactivarla.
2. Parámetros → Modalidades muestra las seis con su clave. Crear una, editar su nombre y desactivarla.
3. Parámetros → Proyectos → "Procesos" de un proyecto abre el diálogo con "Este proyecto aún no tiene procesos de contratación".
4. "Nuevo proceso":
   - un enlace sin `http` muestra su error bajo el campo;
   - con un enlace válido y el valor `1.234.567,50`, el proceso aparece con "En preparación", "$ 1.234.567,50" y el número como enlace.
5. "Cambiar estado" a Publicado no pide confirmación. Volver a "En preparación" pide confirmación ("El proceso vuelve atrás"). Ambos cambios quedan en `checklist_historial` con `entidad_tipo = 'proceso'`.
6. Editar el proceso y eliminarlo. Uno con un contrato vinculado no se elimina y muestra el mensaje del servidor.

**Como Gestor:** los pasos 3 a 6 funcionan. En Modalidades y Etapas no hay acciones.

**Como Consulta:** todo se ve en solo lectura, con el texto de quién puede modificar.

La consola del navegador no muestra errores nuevos.

- [ ] **Paso 4: Limpiar**

Detener el servidor, eliminar la copia y devolver `public/` a lo comiteado si se compiló en desarrollo:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 5: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas, 797.

Run: `npm test`
Expected: pasan todas, 430.

---

### Tarea 10: Informar y pedir validación

- [ ] **Paso 1: Actualizar el estado del diseño**

En `docs/superpowers/specs/2026-11-17-contratacion-revision-design.md`, reemplazar:

```markdown
**Estado:** borrador para revisión del usuario.
```

por:

```markdown
**Estado:** aprobado por el usuario el 2026-11-17. Etapa 1 (catálogos y procesos) implementada y verificada en copia; pendiente de validar y aplicar en `gest_pro`.
```

```bash
git add docs/superpowers/specs/2026-11-17-contratacion-revision-design.md
git commit -m "docs: mark procurement stage 1 as implemented"
```

- [ ] **Paso 2: Informar**

Informar:
- los totales de pruebas;
- las comparaciones (migrar, revertir y volver a migrar);
- lo revisado en el navegador con los tres roles.

Pedir dos decisiones por separado: si valida la etapa y si autoriza aplicar las migraciones 13 a 15 en `gest_pro`.

---

### Tarea 11: Aplicar en `gest_pro` (solo con autorización)

No ejecutar esta tarea sin un sí explícito del usuario a este paso. Si la comparación muestra diferencias, restaurar el respaldo: no usar `migrate:rollback`.

- [ ] **Paso 1: Respaldar**

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro > "$TMP_DIR/gest_pro_antes_contratacion_1.sql"
```

Expected: el archivo existe y termina en `-- Dump completed`.

- [ ] **Paso 2: Tomar la foto antes de migrar**

```bash
php "$TMP_DIR/gestpro_foto_contratacion.php" "$TMP_DIR/c1_real_antes.json" gest_pro
```

- [ ] **Paso 3: Migrar hacia adelante**

Run: `php artisan migrate --force`
Expected: se aplican las tres migraciones `2026_11_17_*`.

- [ ] **Paso 4: Comparar**

```bash
php "$TMP_DIR/gestpro_foto_contratacion.php" "$TMP_DIR/c1_real_despues.json" gest_pro
```

```bash
php "$TMP_DIR/gestpro_comparar_contratacion.php" "$TMP_DIR/c1_real_antes.json" "$TMP_DIR/c1_real_despues.json"
```

Expected: `SIN DIFERENCIAS`.

- [ ] **Paso 5: Informar**

Dar el resultado y la ruta completa del respaldo, y avisar que está en una carpeta temporal.

---

## Criterio de la etapa

La etapa está lista cuando, según el diseño:

- modalidades y etapas de contratación se administran desde Parámetros, y solo el Administrador las modifica;
- un proyecto registra sus procesos con modalidad, plataforma, número, enlace, entidad, valor y estado, y los cambios quedan en el historial;
- ningún expediente cambia al migrar ni al revertir.
