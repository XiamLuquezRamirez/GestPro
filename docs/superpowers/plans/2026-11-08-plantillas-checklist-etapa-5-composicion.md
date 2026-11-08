# Plantillas de checklist, etapa 5: composición en el expediente. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** que un usuario elija las plantillas de un proyecto (una principal y las complementarias), combine los requisitos duplicados, agregue requisitos propios y siga el checklist resultante desde el módulo existente, para proyectos de cualquier fuente de financiación. Con esto se cumple el criterio de aceptación principal del diseño.

**Arquitectura:** `PlantillasExpediente` decide qué plantillas son compatibles con el proyecto, valida la selección y la guarda en `expediente_plantillas`. `ExpedienteService::sincronizar`, que sigue siendo el único motor, recorre todas las plantillas del expediente:
- en la de motor `sgr`, el evaluador decide qué aplica, sin cambios;
- en las de motor `simple`, aplica todo lo activo y los condicionados nacen "por definir";
- los requisitos con el mismo `codigo_comun` forman una sola fila con varios orígenes (`proyecto_requisito_origenes`).

Los requisitos propios son filas sin requisito de catálogo, con el mismo seguimiento que las demás. En pantalla, el expediente pasa a llamarse "Expediente" y muestra sus plantillas; lo propio de regalías solo aparece si el expediente tiene una plantilla `sgr`.

**Tecnologías:** Laravel 12, PHP 8.2, MariaDB 10.4, React 18 con Laravel Mix, PHPUnit sobre SQLite en memoria, Vitest.

**Diseño:** `docs/superpowers/specs/2026-11-06-plantillas-checklist-design.md`:
- sección 2: selección, motores, duplicados, requisitos propios, "no aplica";
- sección 4: migración 10;
- sección 5: flujo;
- sección 6: "Trabajo diario" y "Textos en pantalla";
- sección 7: crear-faltantes;
- notas de la sección 8 para la etapa 5.

**Estado de partida:** rama `feature/plantillas-checklist`, etapas 1 a 4 validadas, de la 1 a la 3 aplicadas en `gest_pro`; 428 pruebas PHP y 183 JS pasando.

---

## Reglas de ejecución

- **Nada de esto se aplica a `gest_pro` sin autorización del usuario.** Nunca se usa `migrate:rollback` allí: si hay que volver atrás, se restaura el respaldo. La tarea 16 aplica la migración, y solo con un sí explícito.
- Commits autorizados solo en `feature/plantillas-checklist`. Sin push. Mensajes en inglés, como el historial, terminados con la línea `Co-Authored-By` del modelo que comitea.
- Nomenclatura en español sin tildes para tablas, columnas, clases y métodos nuevos. Textos para el usuario en español con tuteo, como el resto del módulo; los mensajes de rollback de las migraciones conservan el registro de usted de la serie.
- Pruebas: `php artisan test` sin definir `DB_DATABASE` (usa SQLite en memoria) y `npm test`. Si Vitest crea `resources/js/node_modules`, se borra.
- **Editar con la herramienta Edit, no con `sed`.** Cada reemplazo de este plan aparece una sola vez en su archivo. Si no lo encuentras tal cual, detente y avisa.
- El usuario suele tener `npm run watch` corriendo, que reescribe `public/`. Solo la tarea 14 comitea `public/`, después de `npm run production`.
- No tocar ni comitear `public/images/marca-completa.png` ni `public/images/marca-completa1.png` si siguen modificados: son cambios del usuario.
- `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio. Hay que definirlo en cada comando, porque el estado de la terminal no se conserva.
- **Cuidado con SQLite.** Cambiar una columna reconstruye la tabla y, dentro de la transacción de `RefreshDatabase`, eso afecta a las filas hijas. Por eso las pruebas que bajan y suben la migración 10 no crean filas de checklist. En MariaDB no ocurre, y la tarea 15 lo comprueba con datos reales.

## Fuera de esta etapa

- Aplicar una versión nueva de una plantilla a un expediente existente, con vista previa, y la pantalla de historial del expediente: etapa 6.
- Tablero generalizado y filtros por fuente y responsable: etapa 6.
- Retirar `proyectos_expedientes.catalogo_version_id` (migración 11): etapa 6. Hasta entonces guarda la versión SGR del expediente, o null si no la tiene.
- La pantalla del expediente sigue cargando `/catalogo-requisitos/vigente` al abrirse. Ya lo hacía y no cambia aquí.

## Decisiones de este plan

**Plantillas compatibles**
- Se ofrecen las plantillas activas con versión vigente asociadas a alguna fuente del proyecto (o a todas) y a su sector (o a todos).
- Un proyecto sin sector solo recibe las de todos los sectores, con aviso.
- Un expediente conserva las plantillas que ya tiene aunque dejen de ser compatibles o se desactiven, y la pantalla lo avisa.

**Selección**
- Se elige una lista de plantillas y una principal, con a lo sumo una de motor `sgr`.
- Una plantilla que ya está en el expediente conserva su versión. Una nueva entra con la vigente.
- Confirmar la configuración exige al menos una plantilla, y un expediente confirmado no puede quedarse sin ellas.
- Si el pedido no trae plantillas, la selección no cambia.

**Creación**
- Si el proyecto admite una plantilla de motor `sgr`, esta queda preseleccionada como principal, con su sector sugerido. Si no, el expediente nace sin plantillas.
- El expediente automático corresponde a todo proyecto en Formulación con al menos una fuente definida y sin expediente.
- "Nuevo expediente" desde un radicado copia la configuración y las plantillas que siguen disponibles; avisa cuáles se quitaron. No copia el cumplimiento ni los requisitos propios.

**Filas del checklist**
- Cada `codigo_comun` (o cada requisito sin código común) es una sola fila.
- La fila se ancla al requisito que la creó, y sus orígenes son los requisitos que aplican con esa llave.
- El carácter es el más exigente de los orígenes.
- Una fila sin orígenes queda retirada y conserva su trabajo.
- El orden pone primero la plantilla principal, así que un expediente que solo tiene la SGR queda idéntico al de hoy. Una prueba de paridad y la comparación con datos reales lo comprueban.

**Texto mostrado**
- Cada fila muestra el requisito de su primer origen, empezando por la plantilla principal.
- Los textos parecidos sin código común solo generan un aviso; nunca se fusionan.

**"No aplica" y "por definir"**
- La aprobación del "no aplica" se exige si algún origen la exige. Un requisito propio no la exige.
- "Aplica" resuelve un requisito por definir.
- Retirar o rechazar un "no aplica" devuelve la fila al estado que tenía al marcarse. Esto cambia lo que decía la sección 2 del diseño, que ya está actualizada.

**Requisitos propios**
- Tienen título, descripción, categoría y carácter (obligatorio u opcional).
- Se editan y eliminan solo en borrador configurado. No se eliminan si están cumplidos o tienen soporte.
- Todo queda en el historial.

**Migración 10 y textos**
- El rollback de la migración 10 se detiene si perdería datos:
  - hay requisitos propios;
  - hay expedientes sin versión SGR;
  - hay expedientes con más de una plantilla;
  - hay filas con más de un origen;
  - hay filas que vienen de plantillas distintas de la SGR.
- Los textos de pantalla siguen la tabla del diseño. "SGR" se conserva donde es propio de regalías y la búsqueda lo sigue encontrando.

## Lo que ya se ensayó

El plan se ensayó completo en un clon descartable fuera del repositorio, y este documento se generó con el código de ese ensayo: un script comprobó que aplicar sus bloques sobre la rama reproduce el ensayo exactamente.

- **Pruebas:** 523 PHP y 260 JS. El build de producción compila sin avisos.
- **En una copia de `gest_pro` (MariaDB)**, lo que la API entrega de los 10 expedientes y sus 286 filas no cambió en ningún paso:
  - al migrar: quedaron 233 orígenes, uno por fila que aplica;
  - al recalcular los 9 borradores con el motor nuevo;
  - al revertir, que restaura los NOT NULL;
  - al volver a migrar.

  `expedientes:crear-faltantes --simular` no encontró proyectos sin expediente.
- **En el navegador, sobre esa copia:**
  - una plantilla simple publicada se agregó a un expediente SGR, y el requisito con código común quedó en una sola fila con "También en";
  - un condicionado nació por definir, con su condición visible, y "Aplica" lo resolvió;
  - se agregó un requisito propio;
  - al quitar la SGR de otro expediente, desaparecieron el panel y la fase SGR y el selector mostró la plantilla nueva;
  - el aviso de compatibilidad apareció en un proyecto cuya fuente ya no es SGR.

## Archivos

| Archivo | Responsabilidad |
|---|---|
| `database/migrations/2026_11_08_100009_add_requisitos_propios_table.php` | Requisitos propios, versión SGR opcional y orígenes |
| `app/Models/ProyectoRequisito.php` | Orígenes, propio y aprobación según los orígenes |
| `app/Services/Viabilizacion/PlantillasExpediente.php` | Plantillas compatibles, selección y su guardado |
| `app/Http/Controllers/PlantillasExpedienteController.php` | Plantillas compatibles con un proyecto |
| `app/Services/Viabilizacion/ExpedienteService.php` | Crear, configurar, recalcular con varias plantillas y presentar |
| `app/Services/Viabilizacion/NoAplicaRequisito.php` | "No aplica", "Aplica" y estado anterior |
| `app/Services/Viabilizacion/RequisitosPropios.php` y `app/Http/Controllers/RequisitoPropioController.php` | Requisitos propios |
| `routes/viabilizacion.php`, `routes/console.php` | Rutas nuevas y `expedientes:crear-faltantes` |
| `resources/js/utils/plantillasExpediente.js` | Selección de plantillas y sus avisos |
| `resources/js/utils/expedienteChecklist.js` | Grupos, acciones, textos de fila, propios y Excel |
| `resources/js/components/ExpedienteConfiguracion.jsx` | Bloque de plantillas y panel SGR condicional |
| `resources/js/components/ExpedienteChecklist.jsx` | Grupos, "Aplica" y requisitos propios |
| `resources/js/components/ExpedienteViabilizacion.jsx` | Llamadas a la API, título y avisos |
| Navegación, tablero, modo sala, modal y parámetros | Textos "Expediente" |

---
### Tarea 1: Punto de partida y foto con el código actual

La foto de la API tiene que tomarse **antes de cambiar código**, porque después el servicio ya lee las columnas y la tabla nuevas. La copia `gestpro_verif_tmp` se conserva hasta la tarea 15.

- [ ] **Paso 1: Comprobar la rama, el estado y las pruebas**

Run: `git branch --show-current`
Expected: `feature/plantillas-checklist`

Run: `git status --short`
Expected: como mucho, `public/` (si corre `npm run watch`) y las dos imágenes del usuario.

Run: `php artisan test`
Expected: pasan todas, 428. Si alguna falla, detenerse y avisar.

Run: `npm test`
Expected: pasan todas, 183.

- [ ] **Paso 2: Crear la copia descartable**

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE IF EXISTS gestpro_verif_tmp; CREATE DATABASE gestpro_verif_tmp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro | /c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp
```

Si MySQL no responde (`ERROR 2002`), está apagado. Hay que pedirle al usuario que lo arranque desde XAMPP o arrancarlo con `/c/xampp/mysql/bin/mysqld --defaults-file=/c/xampp/mysql/bin/my.ini --standalone` en segundo plano.

- [ ] **Paso 3: Crear los tres scripts de comprobación**

Crear `$TMP_DIR/gestpro_foto_etapa5.php`. Toma las tablas y, salvo con `--solo-tablas`, lo que la API entrega de cada expediente. Se detiene si la base no es la esperada:

```php
<?php
// Foto de los expedientes para comparar antes y después de migrar.
// Uso, desde la raíz del repositorio:
//   php gestpro_foto_etapa5.php <archivo.json> <base esperada> [--solo-tablas]
// --solo-tablas lee las tablas sin pasar por el servicio: sirve cuando el código y el
// esquema no coinciden (antes de migrar con el código nuevo, o después de revertir).
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

Crear `$TMP_DIR/gestpro_comparar_etapa5.php`. Exige que todo lo de antes siga igual y permite campos nuevos:

```php
<?php
// Comprueba que todo lo que había antes sigue igual después. Lo nuevo (campos que la etapa
// agrega) se permite; un valor distinto o algo que falta, no.
// Uso: php gestpro_comparar_etapa5.php <antes.json> <despues.json>

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

Crear `$TMP_DIR/gestpro_resincronizar_etapa5.php`. Recalcula con el motor nuevo los expedientes en borrador, para comprobar que componer plantillas no cambia un expediente que solo tiene la SGR:

```php
<?php
// Recalcula con el motor nuevo cada expediente en borrador de la base indicada.
// Uso, desde la raíz del repositorio: php gestpro_resincronizar_etapa5.php <base esperada>
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProyectoExpediente;
use App\Services\Viabilizacion\ExpedienteService;
use Illuminate\Support\Facades\DB;

if (DB::getDatabaseName() !== $argv[1]) {
    exit('Abortado: la base es ' . DB::getDatabaseName() . ", se esperaba {$argv[1]}\n");
}

$servicio = app(ExpedienteService::class);
$recalculados = 0;
foreach (ProyectoExpediente::query()->where('estado', ProyectoExpediente::BORRADOR)->orderBy('id')->get() as $expediente) {
    $servicio->sincronizar($expediente);
    $recalculados++;
}
echo "Recalculados: {$recalculados}\n";
```

- [ ] **Paso 4: Tomar las fotos de antes, con el código de la etapa 4**

Desde la raíz del repositorio:

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_etapa5.php" "$TMP_DIR/e5_antes.json" gestpro_verif_tmp
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_etapa5.php" "$TMP_DIR/e5_tablas_antes.json" gestpro_verif_tmp --solo-tablas
```

Expected: `Expedientes: 10, filas: 286, con API` y luego `solo tablas`, o las cifras actuales de `gest_pro`.

---

### Tarea 2: Migración 10: requisitos propios y orígenes

**Files:**
- Create: `database/migrations/2026_11_08_100009_add_requisitos_propios_table.php`
- Test (create): `tests/Feature/Schema/RequisitosPropiosTest.php`
- Modify: `app/Models/ProyectoExpediente.php`
- Modify: `app/Models/ProyectoRequisito.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Schema/RequisitosPropiosTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\PlantillaChecklist;
use App\Models\ProyectoRequisito;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class RequisitosPropiosTest extends TestCase
{
    use RefreshDatabase;

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_08_100009_add_requisitos_propios_table.php');
    }

    private function version(?int $plantilla = null): int
    {
        return DB::table('catalogo_versiones')->insertGetId([
            'plantilla_id' => $plantilla ?? PlantillaChecklist::sgr()->id, 'numero' => 1, 'estado' => 'publicada', 'vigente' => true,
        ]);
    }

    private function requisito(int $version, string $clave = 'a1'): int
    {
        return DB::table('requisitos_catalogo')->insertGetId([
            'catalogo_version_id' => $version, 'clave' => $clave, 'seccion' => 'A', 'fuente' => 'a12',
            'codigo' => '1', 'texto' => 'Texto', 'codigo_comun' => $clave,
        ]);
    }

    private function expediente(?int $version): int
    {
        return DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => DB::table('proyectos')->insertGetId(['nombre' => 'Prueba']),
            'catalogo_version_id' => $version, 'fase_sgr' => 3, 'estado' => 'borrador',
        ]);
    }

    private function fila(int $expediente, ?int $requisito, array $cambios = []): int
    {
        return DB::table('proyectos_requisitos')->insertGetId([
            'expediente_id' => $expediente, 'requisito_catalogo_id' => $requisito, ...$cambios,
        ]);
    }

    private function nullable(string $tabla, string $columna): bool
    {
        return collect(Schema::getColumns($tabla))->firstWhere('name', $columna)['nullable'];
    }

    private function foranea(string $tabla, string $columna): ?array
    {
        return collect(Schema::getForeignKeys($tabla))->first(fn ($f) => $f['columns'] === [$columna]);
    }

    private function rollbackDetenido(string $mensaje): void
    {
        try {
            $this->migracion()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($mensaje, $e->getMessage());
        }

        // Nada se revirtió.
        $this->assertTrue(Schema::hasTable('proyecto_requisito_origenes'));
        $this->assertTrue(Schema::hasColumn('proyectos_requisitos', 'titulo_propio'));
        $this->assertTrue($this->nullable('proyectos_requisitos', 'requisito_catalogo_id'));
    }

    // ---------------------------------------------------------------- columnas

    public function test_agrega_los_textos_propios_y_vuelve_opcionales_las_referencias(): void
    {
        $this->assertTrue(Schema::hasColumns('proyectos_requisitos', ['titulo_propio', 'descripcion_propia', 'categoria_propia']));
        $this->assertTrue(Schema::hasColumns('proyecto_requisito_origenes', ['proyecto_requisito_id', 'requisito_catalogo_id']));
        $this->assertTrue($this->nullable('proyectos_requisitos', 'requisito_catalogo_id'));
        $this->assertTrue($this->nullable('proyectos_expedientes', 'catalogo_version_id'));

        // Las claves foráneas siguen en su sitio.
        $this->assertSame('requisitos_catalogo', $this->foranea('proyectos_requisitos', 'requisito_catalogo_id')['foreign_table']);
        $this->assertSame('catalogo_versiones', $this->foranea('proyectos_expedientes', 'catalogo_version_id')['foreign_table']);
        $this->assertTrue(Schema::hasIndex('proyectos_requisitos', 'proy_req_expediente_requisito_unique'));
    }

    public function test_un_expediente_admite_varios_requisitos_propios(): void
    {
        $expediente = $this->expediente(null);

        $this->fila($expediente, null, ['titulo_propio' => 'Acta de vecinos', 'categoria_propia' => 'social']);
        $this->fila($expediente, null, ['titulo_propio' => 'Permiso de la junta']);

        $filas = ProyectoRequisito::where('expediente_id', $expediente)->orderBy('id')->get();
        $this->assertCount(2, $filas);
        $this->assertTrue($filas[0]->esPropio());
        $this->assertSame('social', $filas[0]->categoria_propia);
        $this->assertNull($filas[0]->requisito_catalogo_id);
    }

    public function test_un_requisito_del_catalogo_no_es_propio_y_sigue_siendo_unico_en_el_expediente(): void
    {
        $requisito = $this->requisito($this->version());
        $expediente = $this->expediente(null);
        $fila = ProyectoRequisito::find($this->fila($expediente, $requisito));

        $this->assertFalse($fila->esPropio());
        $this->expectException(QueryException::class);
        $this->fila($expediente, $requisito);
    }

    // ---------------------------------------------------------------- orígenes

    public function test_los_origenes_de_una_fila_se_leen_por_la_relacion(): void
    {
        $version = $this->version();
        $uno = $this->requisito($version, 'a1');
        $dos = $this->requisito($version, 'a2');
        $fila = $this->fila($this->expediente($version), $uno);
        DB::table('proyecto_requisito_origenes')->insert([
            ['proyecto_requisito_id' => $fila, 'requisito_catalogo_id' => $uno],
            ['proyecto_requisito_id' => $fila, 'requisito_catalogo_id' => $dos],
        ]);

        $this->assertEqualsCanonicalizing([$uno, $dos], ProyectoRequisito::find($fila)->origenes()->pluck('requisitos_catalogo.id')->all());
    }

    public function test_un_origen_no_se_repite_en_la_misma_fila(): void
    {
        $version = $this->version();
        $requisito = $this->requisito($version);
        $fila = $this->fila($this->expediente($version), $requisito);
        DB::table('proyecto_requisito_origenes')->insert(['proyecto_requisito_id' => $fila, 'requisito_catalogo_id' => $requisito]);

        $this->expectException(QueryException::class);
        DB::table('proyecto_requisito_origenes')->insert(['proyecto_requisito_id' => $fila, 'requisito_catalogo_id' => $requisito]);
    }

    public function test_borrar_la_fila_borra_sus_origenes_y_un_requisito_con_origenes_no_se_borra(): void
    {
        $version = $this->version();
        $requisito = $this->requisito($version);
        $otra = $this->requisito($version, 'a2');
        $expediente = $this->expediente($version);
        $fila = $this->fila($expediente, $requisito);
        $conOrigen = $this->fila($expediente, $otra);
        DB::table('proyecto_requisito_origenes')->insert([
            ['proyecto_requisito_id' => $fila, 'requisito_catalogo_id' => $requisito],
            ['proyecto_requisito_id' => $conOrigen, 'requisito_catalogo_id' => $requisito],
        ]);

        DB::table('proyectos_requisitos')->where('id', $fila)->delete();
        $this->assertSame([$conOrigen], DB::table('proyecto_requisito_origenes')->pluck('proyecto_requisito_id')->map(fn ($id) => (int) $id)->all());

        $this->expectException(QueryException::class);
        DB::table('requisitos_catalogo')->where('id', $requisito)->delete();
    }

    /*
     * Esta prueba no pasa por down(). En SQLite, cambiar una columna reconstruye la tabla y,
     * dentro de la transacción de RefreshDatabase, eso borraría en cascada las filas hijas. Se
     * prueba el reintento que deja un intento fallido antes de crear la tabla de orígenes: las
     * columnas ya son opcionales, así que up() no reconstruye nada. En MariaDB no pasa: se
     * comprueba en la copia descartable.
     */
    public function test_migrar_registra_un_origen_por_cada_fila_que_aplica(): void
    {
        $version = $this->version();
        $a1 = $this->requisito($version, 'a1');
        $a2 = $this->requisito($version, 'a2');
        $a3 = $this->requisito($version, 'a3');
        $expediente = $this->expediente($version);
        $aplica = $this->fila($expediente, $a1);
        $tambien = $this->fila($expediente, $a2, ['cumplido' => true]);
        $this->fila($expediente, $a3, ['aplica' => false, 'cumplido' => true]);
        Schema::drop('proyecto_requisito_origenes');

        $this->migracion()->up();

        $origenes = DB::table('proyecto_requisito_origenes')->orderBy('proyecto_requisito_id')->get();
        $this->assertSame(
            [[$aplica, $a1], [$tambien, $a2]],
            $origenes->map(fn ($o) => [(int) $o->proyecto_requisito_id, (int) $o->requisito_catalogo_id])->all()
        );
        $this->assertSame(3, DB::table('proyectos_requisitos')->count());
    }

    // ---------------------------------------------------------------- rollback

    public function test_el_rollback_se_detiene_si_hay_requisitos_propios(): void
    {
        $this->fila($this->expediente($this->version()), null, ['titulo_propio' => 'Acta de vecinos']);

        $this->rollbackDetenido('hay requisitos propios');
    }

    public function test_el_rollback_se_detiene_si_hay_expedientes_sin_version_sgr(): void
    {
        $this->expediente(null);

        $this->rollbackDetenido('expedientes sin versión SGR');
    }

    public function test_el_rollback_se_detiene_si_un_expediente_tiene_mas_de_una_plantilla(): void
    {
        $sgr = $this->version();
        $otra = $this->version(PlantillaChecklist::create(['nombre' => 'Agua', 'motor' => PlantillaChecklist::MOTOR_SIMPLE])->id);
        $expediente = $this->expediente($sgr);
        DB::table('expediente_plantillas')->insert([
            ['expediente_id' => $expediente, 'catalogo_version_id' => $sgr, 'es_principal' => true],
            ['expediente_id' => $expediente, 'catalogo_version_id' => $otra, 'es_principal' => false],
        ]);

        $this->rollbackDetenido('más de una plantilla');
    }

    public function test_el_rollback_se_detiene_si_una_fila_tiene_mas_de_un_origen(): void
    {
        $version = $this->version();
        $a1 = $this->requisito($version, 'a1');
        $a2 = $this->requisito($version, 'a2');
        $fila = $this->fila($this->expediente($version), $a1);
        DB::table('proyecto_requisito_origenes')->insert([
            ['proyecto_requisito_id' => $fila, 'requisito_catalogo_id' => $a1],
            ['proyecto_requisito_id' => $fila, 'requisito_catalogo_id' => $a2],
        ]);

        $this->rollbackDetenido('más de un origen');
    }

    public function test_el_rollback_se_detiene_si_una_fila_viene_de_una_plantilla_distinta_de_la_sgr(): void
    {
        // La fila nació de una plantilla que el expediente ya no tiene: solo le queda la SGR.
        $sgr = $this->version();
        $otra = $this->version(PlantillaChecklist::create(['nombre' => 'Agua', 'motor' => PlantillaChecklist::MOTOR_SIMPLE])->id);
        $expediente = $this->expediente($sgr);
        $this->fila($expediente, $this->requisito($sgr, 'a1'));
        $this->fila($expediente, $this->requisito($otra, 'agua1'), ['aplica' => false, 'cumplido' => true]);

        $this->rollbackDetenido('plantillas distintas de la SGR');
    }

    /*
     * Sin filas a propósito: en SQLite, restaurar el NOT NULL reconstruye las tablas y,
     * dentro de la transacción de RefreshDatabase, borraría en cascada las filas hijas.
     */
    public function test_el_rollback_restaura_el_esquema_y_ambos_sentidos_se_pueden_repetir(): void
    {
        $migracion = $this->migracion();

        $migracion->down();
        $migracion->down(); // repetirlo no falla

        $this->assertFalse(Schema::hasTable('proyecto_requisito_origenes'));
        foreach (['titulo_propio', 'descripcion_propia', 'categoria_propia'] as $columna) {
            $this->assertFalse(Schema::hasColumn('proyectos_requisitos', $columna), $columna);
        }
        $this->assertFalse($this->nullable('proyectos_requisitos', 'requisito_catalogo_id'));
        $this->assertFalse($this->nullable('proyectos_expedientes', 'catalogo_version_id'));
        $this->assertNotNull($this->foranea('proyectos_requisitos', 'requisito_catalogo_id'));
        $this->assertNotNull($this->foranea('proyectos_expedientes', 'catalogo_version_id'));

        $migracion->up();
        $migracion->up(); // repetirlo no falla

        $this->assertTrue(Schema::hasTable('proyecto_requisito_origenes'));
        $this->assertTrue(Schema::hasColumns('proyectos_requisitos', ['titulo_propio', 'descripcion_propia', 'categoria_propia']));
        $this->assertTrue($this->nullable('proyectos_requisitos', 'requisito_catalogo_id'));
        $this->assertTrue($this->nullable('proyectos_expedientes', 'catalogo_version_id'));
        $this->assertNotNull($this->foranea('proyectos_requisitos', 'requisito_catalogo_id'));
        $this->assertNotNull($this->foranea('proyectos_expedientes', 'catalogo_version_id'));
        $this->assertTrue(Schema::hasIndex('proyectos_requisitos', 'proy_req_expediente_requisito_unique'));
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Schema/RequisitosPropiosTest.php`
Expected: FALLAN las 13: no existen las columnas propias ni la tabla `proyecto_requisito_origenes`, y `requisito_catalogo_id` todavía es obligatorio (`QueryException` al insertar un propio).

- [ ] **Paso 3: Implementar**

Crear `database/migrations/2026_11_08_100009_add_requisitos_propios_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TEXTOS_PROPIOS = ['titulo_propio', 'descripcion_propia', 'categoria_propia'];

    /** Referencias que pasan a opcionales: tabla => [columna, tabla referida]. */
    private const REFERENCIAS = [
        'proyectos_expedientes' => ['catalogo_version_id', 'catalogo_versiones'],
        'proyectos_requisitos' => ['requisito_catalogo_id', 'requisitos_catalogo'],
    ];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. Quitar la clave foránea, cambiar la columna
        // y volver a ponerla son tres pasos.
        // null en catalogo_version_id: el expediente no tiene plantilla con motor sgr.
        // null en requisito_catalogo_id: requisito propio del proyecto, con sus textos en la fila.
        foreach (self::REFERENCIAS as $tabla => [$columna, $referida]) {
            $this->cambiarNulabilidad($tabla, $columna, $referida, true);
        }

        $this->agregar('titulo_propio', fn (Blueprint $t) => $t->text('titulo_propio')->nullable());
        $this->agregar('descripcion_propia', fn (Blueprint $t) => $t->text('descripcion_propia')->nullable());
        $this->agregar('categoria_propia', fn (Blueprint $t) => $t->string('categoria_propia', 30)->nullable());

        // up() solo corre si la migración no quedó registrada: si la tabla existe, es un resto de
        // un intento fallido, quizá sin su clave primaria o sus claves foráneas. Sus filas se
        // reconstruyen abajo.
        Schema::dropIfExists('proyecto_requisito_origenes');

        // Requisitos de catálogo que dan origen a cada fila del checklist. Varias plantillas con
        // el mismo código común comparten una fila, con un origen por plantilla.
        Schema::create('proyecto_requisito_origenes', function (Blueprint $table) {
            $table->foreignId('proyecto_requisito_id')->constrained('proyectos_requisitos')->cascadeOnDelete();
            $table->foreignId('requisito_catalogo_id')->constrained('requisitos_catalogo')->restrictOnDelete();
            $table->primary(['proyecto_requisito_id', 'requisito_catalogo_id']);
        });

        // Solo aporta origen lo que aplica: una fila retirada no tiene ninguno.
        DB::table('proyecto_requisito_origenes')->insertUsing(
            ['proyecto_requisito_id', 'requisito_catalogo_id'],
            DB::table('proyectos_requisitos')->select('id', 'requisito_catalogo_id')
                ->where('aplica', true)
                ->whereNotNull('requisito_catalogo_id')
        );
    }

    public function down(): void
    {
        $propios = Schema::hasColumn('proyectos_requisitos', 'titulo_propio')
            ? DB::table('proyectos_requisitos')->where(function ($q) {
                $q->whereNull('requisito_catalogo_id');
                foreach (self::TEXTOS_PROPIOS as $columna) {
                    $q->orWhereNotNull($columna);
                }
            })->exists()
            : DB::table('proyectos_requisitos')->whereNull('requisito_catalogo_id')->exists();
        if ($propios) {
            throw new RuntimeException('No se puede revertir: hay requisitos propios de los proyectos que se perderían. Elimínelos o restaure un respaldo.');
        }

        if (DB::table('proyectos_expedientes')->whereNull('catalogo_version_id')->exists()) {
            throw new RuntimeException('No se puede revertir: hay expedientes sin versión SGR. Elimínelos o restaure un respaldo.');
        }

        // Al revertir, expediente_plantillas solo se reconstruye con la versión de catalogo_version_id.
        $variasPlantillas = DB::table('expediente_plantillas')
            ->select('expediente_id')->groupBy('expediente_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($variasPlantillas) {
            throw new RuntimeException('No se puede revertir: hay expedientes con más de una plantilla y las complementarias se perderían. Restaure un respaldo.');
        }

        $variosOrigenes = Schema::hasTable('proyecto_requisito_origenes') && DB::table('proyecto_requisito_origenes')
            ->select('proyecto_requisito_id')->groupBy('proyecto_requisito_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($variosOrigenes) {
            throw new RuntimeException('No se puede revertir: hay requisitos con más de un origen que se perderían. Restaure un respaldo.');
        }

        // Antes de esta migración, cada fila venía de la versión SGR de su expediente. Una fila
        // anclada en otra versión (de una plantilla que el expediente tiene o tuvo) quedaría
        // retirada al revertir, con su trabajo.
        $otrasPlantillas = DB::table('proyectos_requisitos')
            ->join('proyectos_expedientes', 'proyectos_expedientes.id', '=', 'proyectos_requisitos.expediente_id')
            ->join('requisitos_catalogo', 'requisitos_catalogo.id', '=', 'proyectos_requisitos.requisito_catalogo_id')
            ->whereColumn('requisitos_catalogo.catalogo_version_id', '!=', 'proyectos_expedientes.catalogo_version_id')
            ->exists();
        if ($otrasPlantillas) {
            throw new RuntimeException('No se puede revertir: hay requisitos que vienen de plantillas distintas de la SGR y se perderían. Restaure un respaldo.');
        }

        Schema::dropIfExists('proyecto_requisito_origenes');

        $existentes = array_values(array_filter(self::TEXTOS_PROPIOS, fn ($c) => Schema::hasColumn('proyectos_requisitos', $c)));
        if ($existentes) {
            Schema::table('proyectos_requisitos', function (Blueprint $table) use ($existentes) {
                $table->dropColumn($existentes);
            });
        }

        // Orden inverso al de up(): primero el checklist y luego los expedientes.
        foreach (array_reverse(self::REFERENCIAS, true) as $tabla => [$columna, $referida]) {
            $this->cambiarNulabilidad($tabla, $columna, $referida, false);
        }
    }

    /** Quita la clave foránea, cambia la columna y la repone; cada paso con su guarda. */
    private function cambiarNulabilidad(string $tabla, string $columna, string $referida, bool $nullable): void
    {
        if ($this->esNullable($tabla, $columna) !== $nullable) {
            if ($this->tieneForanea($tabla, $columna)) {
                Schema::table($tabla, function (Blueprint $table) use ($columna) {
                    $table->dropForeign([$columna]);
                });
            }
            Schema::table($tabla, function (Blueprint $table) use ($columna, $nullable) {
                $table->unsignedBigInteger($columna)->nullable($nullable)->change();
            });
        }
        if (! $this->tieneForanea($tabla, $columna)) {
            Schema::table($tabla, function (Blueprint $table) use ($columna, $referida) {
                $table->foreign($columna)->references('id')->on($referida)->restrictOnDelete();
            });
        }
    }

    private function agregar(string $columna, callable $definicion): void
    {
        if (! Schema::hasColumn('proyectos_requisitos', $columna)) {
            Schema::table('proyectos_requisitos', function (Blueprint $table) use ($definicion) {
                $definicion($table);
            });
        }
    }

    private function esNullable(string $tabla, string $columna): bool
    {
        return (bool) collect(Schema::getColumns($tabla))->firstWhere('name', $columna)['nullable'];
    }

    private function tieneForanea(string $tabla, string $columna): bool
    {
        return collect(Schema::getForeignKeys($tabla))->contains(fn ($f) => $f['columns'] === [$columna]);
    }
};
```

En `app/Models/ProyectoExpediente.php`, reemplazar (1 de 1):

```php
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CatalogoVersion::class, 'catalogo_version_id');
```

por:

```php
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    /** Versión de la plantilla SGR del expediente; null si no tiene plantilla con motor sgr. */
    public function version(): BelongsTo
    {
        return $this->belongsTo(CatalogoVersion::class, 'catalogo_version_id');
```

En `app/Models/ProyectoRequisito.php`, reemplazar (1 de 3):

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyectoRequisito extends Model
{
```

por:

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProyectoRequisito extends Model
{
```

En `app/Models/ProyectoRequisito.php`, reemplazar (2 de 3):

```php
        'usuario_cumplido_id', 'observacion', 'soporte_archivo', 'soporte_nombre', 'orden',
        'estado_aplicabilidad', 'justificacion_no_aplica', 'usuario_no_aplica_id', 'usuario_aprobo_id',
        'fecha_aprobacion', 'caracter', 'responsable_id',
    ];
    protected $attributes = [
        'estado_aplicabilidad' => self::APLICA,
```

por:

```php
        'usuario_cumplido_id', 'observacion', 'soporte_archivo', 'soporte_nombre', 'orden',
        'estado_aplicabilidad', 'justificacion_no_aplica', 'usuario_no_aplica_id', 'usuario_aprobo_id',
        'fecha_aprobacion', 'caracter', 'responsable_id',
        'titulo_propio', 'descripcion_propia', 'categoria_propia',
    ];
    protected $attributes = [
        'estado_aplicabilidad' => self::APLICA,
```

En `app/Models/ProyectoRequisito.php`, reemplazar (3 de 3):

```php
        return $this->belongsTo(ProyectoExpediente::class, 'expediente_id');
    }

    public function requisito(): BelongsTo
    {
        return $this->belongsTo(RequisitoCatalogo::class, 'requisito_catalogo_id');
    }

    public function usuarioCumplido(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_cumplido_id');
```

por:

```php
        return $this->belongsTo(ProyectoExpediente::class, 'expediente_id');
    }

    /**
     * Requisito que creó la fila (su ancla): no cambia aunque cambien las plantillas.
     * Null en un requisito propio del proyecto.
     */
    public function requisito(): BelongsTo
    {
        return $this->belongsTo(RequisitoCatalogo::class, 'requisito_catalogo_id');
    }

    /** Requisitos de las plantillas del expediente que aplican con la llave de esta fila. */
    public function origenes(): BelongsToMany
    {
        return $this->belongsToMany(RequisitoCatalogo::class, 'proyecto_requisito_origenes', 'proyecto_requisito_id', 'requisito_catalogo_id');
    }

    /** Requisito agregado en el expediente, con sus textos en la fila y sin plantilla. */
    public function esPropio(): bool
    {
        return $this->requisito_catalogo_id === null;
    }

    public function usuarioCumplido(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_cumplido_id');
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Schema/RequisitosPropiosTest.php`
Expected: pasan las 13.

Run: `php artisan test`
Expected: pasan todas, 441.

- [ ] **Paso 5: Commit**

```bash
git add database/migrations/2026_11_08_100009_add_requisitos_propios_table.php tests/Feature/Schema/RequisitosPropiosTest.php app/Models/ProyectoExpediente.php app/Models/ProyectoRequisito.php
git commit -m "feat: allow own requirements and record requirement origins"
```

---

### Tarea 3: Plantillas compatibles con el proyecto

**Files:**
- Create: `app/Http/Controllers/PlantillasExpedienteController.php`
- Create: `app/Services/Viabilizacion/PlantillasExpediente.php`
- Test (create): `tests/Feature/Viabilizacion/PlantillasExpedienteApiTest.php`
- Modify: `routes/viabilizacion.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Viabilizacion/PlantillasExpedienteApiTest.php`:

```php
<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Plantillas que se ofrecen a un proyecto y selección de las de un expediente. */
class PlantillasExpedienteApiTest extends TestCase
{
    use RefreshDatabase;

    private PlantillaChecklist $sgr;
    private int $agua;
    private int $vias;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->sgr = PlantillaChecklist::sgr();
        $this->agua = DB::table('sectores')->insertGetId(['nombre' => 'Agua Potable', 'activo' => true]);
        $this->vias = DB::table('sectores')->insertGetId(['nombre' => 'Vías', 'activo' => true]);
    }

    private function fuente(string $clave): int
    {
        return (int) FuenteFinanciacion::where('clave', $clave)->value('id');
    }

    private function proyecto(array $fuentes = ['sgr'], ?int $sector = null): Proyecto
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto veredal', 'sector' => $sector]);
        $proyecto->fuentes()->sync(array_map(fn ($c) => $this->fuente($c), $fuentes));

        return $proyecto;
    }

    /** Plantilla simple con su versión 1 vigente y los requisitos indicados (texto => activo). */
    private function plantilla(string $nombre, array $asociacion = [], array $requisitos = ['Requisito' => true], array $datos = []): PlantillaChecklist
    {
        $plantilla = PlantillaChecklist::create([
            'nombre' => $nombre,
            'descripcion' => "Descripción de {$nombre}",
            'motor' => PlantillaChecklist::MOTOR_SIMPLE,
            'activo' => true,
            'todas_fuentes' => ! isset($asociacion['fuentes']),
            'todos_sectores' => ! isset($asociacion['sectores']),
            ...$datos,
        ]);
        $plantilla->fuentes()->sync(array_map(fn ($c) => $this->fuente($c), $asociacion['fuentes'] ?? []));
        $plantilla->sectores()->sync($asociacion['sectores'] ?? []);
        $version = $plantilla->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        $orden = 0;
        foreach ($requisitos as $texto => $activo) {
            $orden++;
            RequisitoCatalogo::create([
                'catalogo_version_id' => $version->id, 'clave' => "r{$orden}", 'texto' => $texto,
                'orden' => $orden, 'activo' => $activo, 'codigo_comun' => "{$nombre}-{$orden}",
            ]);
        }

        return $plantilla;
    }

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function compatibles(Proyecto $proyecto, ?int $expediente = null)
    {
        $consulta = $expediente ? "?expediente={$expediente}" : '';

        return $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$proyecto->id}/plantillas-compatibles{$consulta}");
    }

    private function ofrecida($respuesta, PlantillaChecklist $plantilla): ?array
    {
        return collect($respuesta->json('plantillas'))->firstWhere('id', $plantilla->id);
    }

    private function nombres($respuesta): array
    {
        return collect($respuesta->json('plantillas'))->pluck('nombre')->sort()->values()->all();
    }

    private function servicio(): PlantillasExpediente
    {
        return app(PlantillasExpediente::class);
    }

    /** Expediente SGR creado como hoy: con la plantilla SGR como principal. */
    private function expediente(Proyecto $proyecto): ProyectoExpediente
    {
        return app(ExpedienteService::class)->crear($proyecto, ['fase_sgr' => 3], null);
    }

    private function errores(callable $accion): array
    {
        try {
            $accion();
            $this->fail('La selección debía rechazarse.');
        } catch (ReglaNegocioException $e) {
            return $e->errores();
        }
    }

    // --------------------------------------------------------- compatibles

    public function test_ofrece_las_plantillas_de_alguna_fuente_del_proyecto_o_de_todas(): void
    {
        $this->plantilla('Propios', ['fuentes' => ['recursos_propios']]);
        $this->plantilla('Nación', ['fuentes' => ['pgn']]);
        $this->plantilla('General');

        $r = $this->compatibles($this->proyecto(['sgr', 'recursos_propios'], $this->agua))->assertOk();

        $this->assertSame(['General', 'Propios', 'SGR'], $this->nombres($r));
        $r->assertJsonPath('sin_sector', false);
    }

    public function test_ofrece_las_plantillas_del_sector_del_proyecto_o_de_todos(): void
    {
        $this->plantilla('Acueductos', ['sectores' => [$this->agua]]);
        $this->plantilla('Carreteras', ['sectores' => [$this->vias]]);

        $r = $this->compatibles($this->proyecto(['sgr'], $this->agua))->assertOk();

        $this->assertSame(['Acueductos', 'SGR'], $this->nombres($r));
    }

    public function test_sin_sector_solo_ofrece_las_de_todos_los_sectores_y_lo_avisa(): void
    {
        $this->plantilla('Acueductos', ['sectores' => [$this->agua]]);
        $this->plantilla('General');

        $r = $this->compatibles($this->proyecto(['sgr']))->assertOk();

        $r->assertJsonPath('sin_sector', true);
        $this->assertSame(['General', 'SGR'], $this->nombres($r));
    }

    public function test_no_ofrece_plantillas_inactivas_ni_sin_version_publicada(): void
    {
        $this->plantilla('Inactiva', [], ['Requisito' => true], ['activo' => false]);
        $borrador = PlantillaChecklist::create(['nombre' => 'Sin publicar', 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'todas_fuentes' => true, 'todos_sectores' => true]);
        $borrador->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::BORRADOR, 'vigente' => false]);

        $r = $this->compatibles($this->proyecto(['sgr'], $this->agua))->assertOk();

        $this->assertSame(['SGR'], $this->nombres($r));
    }

    public function test_cada_plantilla_trae_sus_datos_y_los_requisitos_activos_de_la_vigente(): void
    {
        $plantilla = $this->plantilla('Obras de agua', ['fuentes' => ['recursos_propios'], 'sectores' => [$this->agua]], [
            'Licencia' => true, 'Planos' => true, 'Retirado' => false,
        ]);

        $r = $this->compatibles($this->proyecto(['recursos_propios'], $this->agua))->assertOk();

        $version = $plantilla->versionVigente();
        $this->assertSame([
            'id' => $plantilla->id,
            'nombre' => 'Obras de agua',
            'descripcion' => 'Descripción de Obras de agua',
            'motor' => 'simple',
            'es_sgr' => false,
            'activa' => true,
            'compatible' => true,
            'fuentes' => [['id' => $this->fuente('recursos_propios'), 'nombre' => 'Recursos propios']],
            'sectores' => [['id' => $this->agua, 'nombre' => 'Agua Potable']],
            'todas_fuentes' => false,
            'todos_sectores' => false,
            'requisitos' => 2,
            'version' => ['id' => $version->id, 'numero' => 1],
            'en_expediente' => null,
        ], $this->ofrecida($r, $plantilla));

        $sgr = $this->ofrecida($this->compatibles($this->proyecto(['sgr'], $this->agua)), $this->sgr);
        $this->assertTrue($sgr['es_sgr']);
        $this->assertSame('sgr', $sgr['motor']);
        $this->assertSame(207, $sgr['requisitos']);
    }

    public function test_con_expediente_agrega_las_que_ya_tiene_aunque_ya_no_sean_compatibles(): void
    {
        $proyecto = $this->proyecto(['sgr', 'recursos_propios'], $this->agua);
        $propios = $this->plantilla('Propios', ['fuentes' => ['recursos_propios']]);
        $expediente = $this->expediente($proyecto);
        $this->servicio()->guardar($expediente, $this->servicio()->validar(
            ['plantilla_ids' => [$this->sgr->id, $propios->id], 'principal_id' => $this->sgr->id], $proyecto, $expediente
        ));
        $anterior = $propios->versionVigente();

        // El proyecto deja la fuente, la plantilla se desactiva y publica una versión nueva.
        $proyecto->fuentes()->sync([$this->fuente('sgr')]);
        $propios->update(['activo' => false]);
        $anterior->update(['vigente' => false]);
        $nueva = $propios->versiones()->create(['numero' => 2, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);

        $sinExpediente = $this->compatibles($proyecto)->assertOk();
        $this->assertNull($this->ofrecida($sinExpediente, $propios));

        $r = $this->compatibles($proyecto, $expediente->id)->assertOk();
        $ofrecida = $this->ofrecida($r, $propios);
        $this->assertFalse($ofrecida['compatible']);
        $this->assertFalse($ofrecida['activa']);
        $this->assertSame(['id' => $nueva->id, 'numero' => 2], $ofrecida['version']);
        $this->assertSame(['version' => ['id' => $anterior->id, 'numero' => 1], 'es_principal' => false], $ofrecida['en_expediente']);
        $this->assertTrue($this->ofrecida($r, $this->sgr)['en_expediente']['es_principal']);
    }

    public function test_el_expediente_debe_ser_del_proyecto(): void
    {
        $otro = $this->expediente($this->proyecto(['sgr']));

        $this->compatibles($this->proyecto(['sgr']), $otro->id)
            ->assertNotFound()
            ->assertJsonPath('mensaje', 'El expediente no es de este proyecto.');
    }

    public function test_exige_sesion(): void
    {
        $proyecto = $this->proyecto();

        $this->getJson("/GestPro/proyectos/{$proyecto->id}/plantillas-compatibles")->assertUnauthorized();
    }

    // ------------------------------------------------------------ selección

    public function test_rechaza_ids_inexistentes_e_incompatibles_inactivas_o_sin_version(): void
    {
        $proyecto = $this->proyecto(['sgr'], $this->agua);
        $nacion = $this->plantilla('Nación', ['fuentes' => ['pgn']]);
        $inactiva = $this->plantilla('Inactiva', [], ['Requisito' => true], ['activo' => false]);
        $sinVersion = PlantillaChecklist::create(['nombre' => 'Sin publicar', 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'todas_fuentes' => true, 'todos_sectores' => true]);

        $errores = $this->errores(fn () => $this->servicio()->validar([
            'plantilla_ids' => [$this->sgr->id, 99999, $nacion->id, $inactiva->id, $sinVersion->id],
            'principal_id' => $this->sgr->id,
        ], $proyecto, null));

        $this->assertSame([
            'La plantilla con id 99999 no existe.',
            'La plantilla "Nación" no es compatible con las fuentes o el sector del proyecto.',
            'La plantilla "Inactiva" está inactiva.',
            'La plantilla "Sin publicar" no tiene una versión publicada.',
        ], $errores);
    }

    public function test_admite_como_maximo_una_plantilla_con_motor_sgr(): void
    {
        $proyecto = $this->proyecto(['sgr']);
        $copia = $this->plantilla('Copia SGR', [], ['Requisito' => true], ['motor' => PlantillaChecklist::MOTOR_SGR]);

        $errores = $this->errores(fn () => $this->servicio()->validar(
            ['plantilla_ids' => [$this->sgr->id, $copia->id], 'principal_id' => $this->sgr->id], $proyecto, null
        ));

        $this->assertSame(['Un expediente admite como máximo una plantilla con motor SGR.'], $errores);
    }

    public function test_la_principal_debe_estar_entre_las_elegidas(): void
    {
        $proyecto = $this->proyecto(['sgr']);
        $general = $this->plantilla('General');
        $validar = fn (array $datos) => $this->errores(fn () => $this->servicio()->validar($datos, $proyecto, null));

        $this->assertSame(['Elige cuál de las plantillas es la principal.'], $validar(['plantilla_ids' => [$this->sgr->id, $general->id]]));
        $this->assertSame(['La plantilla principal debe estar entre las elegidas.'], $validar(['plantilla_ids' => [$this->sgr->id], 'principal_id' => $general->id]));
        $this->assertSame(['La plantilla principal debe estar entre las elegidas.'], $validar(['plantilla_ids' => [], 'principal_id' => $general->id]));
    }

    public function test_una_sola_plantilla_es_la_principal_y_sin_plantillas_no_hay_principal(): void
    {
        $proyecto = $this->proyecto(['sgr']);
        $general = $this->plantilla('General');

        $una = $this->servicio()->validar(['plantilla_ids' => [$general->id]], $proyecto, null);
        $ninguna = $this->servicio()->validar(['plantilla_ids' => []], $proyecto, null);

        $this->assertSame($general->id, $una['principal_id']);
        $this->assertSame([$general->id => $general->versionVigente()->id], $una['versiones']);
        $this->assertNull($una['version_sgr_id']);
        $this->assertNull($ninguna['principal_id']);
        $this->assertSame([], $ninguna['versiones']);
    }

    public function test_guardar_registra_las_versiones_la_principal_y_la_version_sgr(): void
    {
        $proyecto = $this->proyecto(['sgr']);
        $general = $this->plantilla('General');
        $expediente = $this->expediente($proyecto);
        $sgrVersion = $this->sgr->versionVigente()->id;

        $this->servicio()->guardar($expediente, $this->servicio()->validar(
            ['plantilla_ids' => [$this->sgr->id, $general->id], 'principal_id' => $general->id], $proyecto, $expediente
        ));

        $this->assertEqualsCanonicalizing([
            ['catalogo_version_id' => $sgrVersion, 'es_principal' => 0],
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
    {
        $proyecto = $this->proyecto(['sgr', 'recursos_propios']);
        $propios = $this->plantilla('Propios', ['fuentes' => ['recursos_propios']]);
        $expediente = $this->expediente($proyecto);
        $datos = ['plantilla_ids' => [$this->sgr->id, $propios->id], 'principal_id' => $this->sgr->id];
        $this->servicio()->guardar($expediente, $this->servicio()->validar($datos, $proyecto, $expediente));
        $anterior = $propios->versionVigente()->id;

        $proyecto->fuentes()->sync([$this->fuente('sgr')]);
        $propios->update(['activo' => false]);
        $propios->versiones()->update(['vigente' => false]);
        $propios->versiones()->create(['numero' => 2, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);

        $seleccion = $this->servicio()->validar($datos, $proyecto, $expediente);

        $this->assertSame($anterior, $seleccion['versiones'][$propios->id]);
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Viabilizacion/PlantillasExpedienteApiTest.php`
Expected: FALLAN las 14: la ruta `GET /proyectos/{proyecto}/plantillas-compatibles` no existe, y el comodín web responde HTML.

- [ ] **Paso 3: Implementar**

Crear `app/Http/Controllers/PlantillasExpedienteController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Services\Viabilizacion\PlantillasExpediente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Plantillas que se ofrecen al configurar el expediente de un proyecto. Lectura: autenticado. */
class PlantillasExpedienteController extends Controller
{
    public function __construct(private readonly PlantillasExpediente $plantillas)
    {
    }

    /** Con ?expediente={id}, agrega las que ese expediente ya tiene, aunque ya no se ofrezcan. */
    public function compatibles(Request $request, Proyecto $proyecto): JsonResponse
    {
        $datos = $request->validate(['expediente' => ['sometimes', 'integer']]);

        $expediente = null;
        if (isset($datos['expediente'])) {
            $expediente = $proyecto->expedientes()->find((int) $datos['expediente']);
            if (! $expediente) {
                return response()->json(['mensaje' => 'El expediente no es de este proyecto.'], 404);
            }
        }

        return response()->json($this->plantillas->disponibles($proyecto, $expediente));
    }
}
```

Crear `app/Services/Viabilizacion/PlantillasExpediente.php`:

```php
<?php

namespace App\Services\Viabilizacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Plantillas de un expediente: cuáles se ofrecen a un proyecto, cuáles se pueden elegir
 * y con qué versión quedan.
 *
 * Reglas:
 * - Se ofrecen las plantillas activas con versión vigente de alguna fuente del proyecto
 *   (o de todas) y de su sector (o de todos). Sin sector, solo las de todos los sectores.
 * - Una plantilla nueva en el expediente entra con su versión vigente; la que ya tenía
 *   conserva su versión aunque haya dejado de ofrecerse (sin cambios retroactivos).
 * - Como máximo una plantilla con motor sgr; si hay plantillas, una es la principal.
 * - proyectos_expedientes.catalogo_version_id guarda la versión de la plantilla sgr, o null.
 */
final class PlantillasExpediente
{
    /**
     * Plantillas que se ofrecen al proyecto, y al expediente si se indica: las compatibles
     * más las que ya tiene, aunque hayan dejado de ser compatibles o estén inactivas.
     *
     * @return array{sin_sector: bool, plantillas: list<array>}
     */
    public function disponibles(Proyecto $proyecto, ?ProyectoExpediente $expediente = null): array
    {
        $compatibles = $this->idsCompatibles($proyecto);
        $enExpediente = $expediente ? $this->versionesDe($expediente)->keyBy('plantilla_id') : collect();

        $plantillas = PlantillaChecklist::query()
            ->whereIn('id', [...$compatibles, ...$enExpediente->keys()])
            ->with([
                'fuentes:id,nombre', 'sectores:id,nombre',
                'versiones' => fn ($q) => $q->vigente()->withCount(['requisitos' => fn ($r) => $r->where('activo', true)]),
            ])
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get();

        return [
            'sin_sector' => $proyecto->sector === null,
            'plantillas' => $plantillas->map(function (PlantillaChecklist $p) use ($compatibles, $enExpediente) {
                $vigente = $p->versiones->first();
                $propia = $enExpediente->get($p->id);

                return [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'descripcion' => $p->descripcion,
                    'motor' => $p->motor,
                    'es_sgr' => $p->clave === PlantillaChecklist::CLAVE_SGR,
                    'activa' => $p->activo,
                    'compatible' => in_array($p->id, $compatibles, true),
                    'fuentes' => $p->fuentes->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre])->values()->all(),
                    'sectores' => $p->sectores->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre])->values()->all(),
                    'todas_fuentes' => $p->todas_fuentes,
                    'todos_sectores' => $p->todos_sectores,
                    'requisitos' => (int) ($vigente?->requisitos_count ?? 0),
                    'version' => $vigente ? ['id' => $vigente->id, 'numero' => $vigente->numero] : null,
                    'en_expediente' => $propia ? [
                        'version' => ['id' => $propia->id, 'numero' => $propia->numero],
                        'es_principal' => (bool) $propia->pivot->es_principal,
                    ] : null,
                ];
            })->values()->all(),
        ];
    }

    /** @return list<int> ids de las plantillas que hoy se ofrecen al proyecto */
    public function idsCompatibles(Proyecto $proyecto): array
    {
        $fuentes = $proyecto->fuentes()->pluck('fuentes_financiacion.id')->all();
        $sector = $proyecto->sector;

        return PlantillaChecklist::query()
            ->where('activo', true)
            ->whereHas('versiones', fn (Builder $q) => $q->vigente())
            ->where(fn (Builder $q) => $q->where('todas_fuentes', true)
                ->orWhereHas('fuentes', fn (Builder $f) => $f->whereIn('fuentes_financiacion.id', $fuentes)))
            ->where(fn (Builder $q) => $q->where('todos_sectores', true)
                ->when($sector !== null, fn (Builder $s) => $s->orWhereHas('sectores', fn (Builder $w) => $w->where('sectores.id', $sector))))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Versiones de plantilla del expediente, con su plantilla: la principal primero y luego
     * por plantilla. Es el orden en que se arma y se muestra el checklist.
     *
     * @return Collection<int, CatalogoVersion> cada una con pivot->es_principal
     */
    public function versionesDe(ProyectoExpediente $expediente): Collection
    {
        return $expediente->versionesPlantilla()
            ->with('plantilla')
            ->get()
            ->sortBy([
                fn (CatalogoVersion $a, CatalogoVersion $b) => (int) $b->pivot->es_principal <=> (int) $a->pivot->es_principal,
                fn (CatalogoVersion $a, CatalogoVersion $b) => $a->plantilla_id <=> $b->plantilla_id,
            ])
            ->values();
    }

    /**
     * Comprueba la selección pedida y decide la versión de cada plantilla.
     *
     * @param array{plantilla_ids?: list<int>, principal_id?: ?int} $datos
     * @return array{versiones: array<int, int>, principal_id: ?int, version_sgr_id: ?int}
     *         versiones: plantilla_id => catalogo_version_id, en el orden pedido
     */
    public function validar(array $datos, Proyecto $proyecto, ?ProyectoExpediente $expediente): array
    {
        $ids = array_values(array_unique(array_map('intval', $datos['plantilla_ids'] ?? [])));
        $principal = isset($datos['principal_id']) ? (int) $datos['principal_id'] : null;
        $previas = $expediente ? $this->versionesDe($expediente)->keyBy('plantilla_id') : collect();
        $compatibles = $this->idsCompatibles($proyecto);
        $encontradas = PlantillaChecklist::query()->whereIn('id', $ids)->get()->keyBy('id');

        $errores = [];
        $versiones = [];
        $sgr = [];
        foreach ($ids as $id) {
            $plantilla = $encontradas->get($id);
            if (! $plantilla) {
                $errores[] = "La plantilla con id {$id} no existe.";
                continue;
            }

            $version = $previas->get($id) ?? $plantilla->versionVigente();
            if (! $previas->has($id)) {
                if (! $plantilla->activo) {
                    $errores[] = "La plantilla \"{$plantilla->nombre}\" está inactiva.";
                } elseif (! $version) {
                    $errores[] = "La plantilla \"{$plantilla->nombre}\" no tiene una versión publicada.";
                } elseif (! in_array($id, $compatibles, true)) {
                    $errores[] = "La plantilla \"{$plantilla->nombre}\" no es compatible con las fuentes o el sector del proyecto.";
                }
            }
            if ($version) {
                $versiones[$id] = $version->id;
            }
            if ($plantilla->motor === PlantillaChecklist::MOTOR_SGR && $version) {
                $sgr[] = $version->id;
            }
        }

        if (count($sgr) > 1) {
            $errores[] = 'Un expediente admite como máximo una plantilla con motor SGR.';
        }

        if ($principal === null && count($ids) === 1) {
            $principal = $ids[0];
        } elseif ($principal === null && count($ids) > 1) {
            $errores[] = 'Elige cuál de las plantillas es la principal.';
        } elseif ($principal !== null && ! in_array($principal, $ids, true)) {
            $errores[] = 'La plantilla principal debe estar entre las elegidas.';
        }

        if ($errores) {
            throw new ReglaNegocioException('La configuración del expediente no es válida.', $errores);
        }

        return ['versiones' => $versiones, 'principal_id' => $principal, 'version_sgr_id' => $sgr[0] ?? null];
    }

    /** Deja el expediente con las plantillas elegidas, sus versiones y la versión SGR. */
    public function guardar(ProyectoExpediente $expediente, array $seleccion): void
    {
        $expediente->versionesPlantilla()->sync(
            collect($seleccion['versiones'])
                ->mapWithKeys(fn (int $version, int $plantilla) => [$version => ['es_principal' => $plantilla === $seleccion['principal_id']]])
                ->all()
        );
        $expediente->update(['catalogo_version_id' => $seleccion['version_sgr_id']]);
        $expediente->unsetRelation('versionesPlantilla')->unsetRelation('version');
    }
}
```

En `routes/viabilizacion.php`, reemplazar (1 de 2):

```php
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\NoAplicaRequisitoController;
use App\Http\Controllers\PlantillaChecklistController;
use Illuminate\Support\Facades\Route;

// Lectura: cualquier usuario autenticado.
```

por:

```php
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\NoAplicaRequisitoController;
use App\Http\Controllers\PlantillaChecklistController;
use App\Http\Controllers\PlantillasExpedienteController;
use Illuminate\Support\Facades\Route;

// Lectura: cualquier usuario autenticado.
```

En `routes/viabilizacion.php`, reemplazar (2 de 2):

```php
    ->name('catalogo-requisitos.vigente');
Route::get('/proyectos/{proyecto}/expedientes', [ExpedienteController::class, 'index'])
    ->name('expedientes.index');
Route::get('/expedientes', [ExpedienteController::class, 'resumen'])
    ->name('expedientes.resumen');
Route::get('/expedientes/{expediente}', [ExpedienteController::class, 'show'])
```

por:

```php
    ->name('catalogo-requisitos.vigente');
Route::get('/proyectos/{proyecto}/expedientes', [ExpedienteController::class, 'index'])
    ->name('expedientes.index');
Route::get('/proyectos/{proyecto}/plantillas-compatibles', [PlantillasExpedienteController::class, 'compatibles'])
    ->name('expedientes.plantillas-compatibles');
Route::get('/expedientes', [ExpedienteController::class, 'resumen'])
    ->name('expedientes.resumen');
Route::get('/expedientes/{expediente}', [ExpedienteController::class, 'show'])
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Viabilizacion/PlantillasExpedienteApiTest.php`
Expected: pasan las 14.

Run: `php artisan test`
Expected: pasan todas, 455.

- [ ] **Paso 5: Commit**

```bash
git add app/Http/Controllers/PlantillasExpedienteController.php app/Services/Viabilizacion/PlantillasExpediente.php tests/Feature/Viabilizacion/PlantillasExpedienteApiTest.php routes/viabilizacion.php
git commit -m "feat: list the checklist templates compatible with a project"
```

---

### Tarea 4: Recalcular el checklist con varias plantillas

`sincronizar` sigue siendo el único motor. La prueba de paridad compara un expediente con solo la SGR contra lo que producía el código de la etapa 4 en los 13 casos de `paridad_evaluador_sgr.json`, guardado en `tests/Fixtures/paridad_expediente_sgr.json`.

**Files:**
- Test (create): `tests/Feature/Viabilizacion/ComposicionExpedienteTest.php`
- Test (create): `tests/Fixtures/paridad_expediente_sgr.json`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Viabilizacion/ComposicionExpedienteTest.php`:

```php
<?php

namespace Tests\Feature\Viabilizacion;

use App\Models\CatalogoVersion;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** El checklist de un expediente armado con varias plantillas, siempre con sincronizar. */
class ComposicionExpedienteTest extends TestCase
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

    private function proyecto(string $nombre = 'Acueducto veredal'): Proyecto
    {
        $proyecto = Proyecto::create(['nombre' => $nombre]);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        return $proyecto;
    }

    /**
     * Plantilla simple con su versión vigente. Cada requisito: [codigo_comun, caracter, activo?].
     *
     * @param array<string, array{0: ?string, 1: string, 2?: bool}> $requisitos texto => datos
     */
    private function plantilla(string $nombre, array $requisitos): PlantillaChecklist
    {
        $plantilla = PlantillaChecklist::create([
            'nombre' => $nombre, 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'activo' => true,
            'todas_fuentes' => true, 'todos_sectores' => true,
        ]);
        $version = $plantilla->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        $orden = 0;
        foreach ($requisitos as $texto => $datos) {
            $orden++;
            RequisitoCatalogo::create([
                'catalogo_version_id' => $version->id, 'clave' => "r{$orden}", 'texto' => $texto, 'orden' => $orden,
                'codigo_comun' => $datos[0], 'caracter' => $datos[1], 'activo' => $datos[2] ?? true,
                'condicion_descripcion' => $datos[1] === RequisitoCatalogo::CONDICIONADO ? 'Si el proyecto lo necesita' : null,
            ]);
        }

        return $plantilla;
    }

    private function requisito(PlantillaChecklist $plantilla, string $texto): RequisitoCatalogo
    {
        return RequisitoCatalogo::where('catalogo_version_id', $plantilla->versionVigente()->id)->where('texto', $texto)->sole();
    }

    /** Expediente SGR (fase III sin nada: 8 requisitos generales), creado y confirmado como hoy. */
    private function expediente(array $config = ['fase_sgr' => 3]): ProyectoExpediente
    {
        $expediente = $this->servicio->crear($this->proyecto(), $config, null);
        $this->servicio->confirmarConfiguracion($expediente, $config);

        return $expediente;
    }

    /** Cambia las plantillas del expediente y recalcula, como lo hará la configuración. */
    private function componer(ProyectoExpediente $expediente, array $plantillas, ?PlantillaChecklist $principal = null): void
    {
        $seleccion = app(PlantillasExpediente::class)->validar([
            'plantilla_ids' => array_map(fn (PlantillaChecklist $p) => $p->id, $plantillas),
            'principal_id' => ($principal ?? $plantillas[0] ?? null)?->id,
        ], $expediente->proyecto, $expediente);
        app(PlantillasExpediente::class)->guardar($expediente, $seleccion);
        $this->servicio->sincronizar($expediente->refresh());
    }

    private function fila(ProyectoExpediente $expediente, RequisitoCatalogo $ancla): ProyectoRequisito
    {
        return $expediente->requisitos()->where('requisito_catalogo_id', $ancla->id)->sole();
    }

    private function origenes(ProyectoRequisito $fila): array
    {
        return $fila->origenes()->orderBy('requisitos_catalogo.id')->pluck('requisitos_catalogo.id')->map(fn ($id) => (int) $id)->all();
    }

    // ------------------------------------------------------------------ paridad

    private function id(string $tabla, string $clave, ?string $sector = null): int
    {
        $q = DB::table($tabla)->where('clave', $clave);
        if ($sector) {
            $q->where('sector_id', $this->id('requisitos_sectores', $sector));
        }

        return (int) $q->value('id');
    }

    private function configuracion(array $c): array
    {
        return [
            'fase_sgr' => $c['fase_sgr'],
            'sector_ids' => array_map(fn ($s) => $this->id('requisitos_sectores', $s), $c['sectores']),
            'tipo_ids' => array_map(function ($t) {
                [$sector, $clave] = explode('::', $t);

                return $this->id('requisitos_tipos', $clave, $sector);
            }, $c['tipos']),
            'componente_ids' => array_map(fn ($x) => $this->id('requisitos_componentes', $x), $c['componentes']),
            'tipologia_ids' => array_map(fn ($x) => $this->id('requisitos_tipologias', $x), $c['tipologias']),
        ];
    }

    /** Lo mismo que guardó el generador de tests/Fixtures/paridad_expediente_sgr.json. */
    private function foto(ProyectoExpediente $expediente): array
    {
        $filas = $expediente->requisitos()->with('requisito:id,clave')->orderBy('orden')->orderBy('id')->get();

        return [
            'filas' => $filas->map(fn ($f) => implode(':', [$f->requisito->clave, (int) $f->aplica, $f->orden, (int) $f->cumplido]))->all(),
            'caracteres' => $filas->pluck('caracter')->unique()->values()->all(),
            'estados' => $filas->pluck('estado_aplicabilidad')->unique()->values()->all(),
            'progreso' => $this->servicio->progresoDe($expediente),
            'retenidos' => $this->servicio->presentar($expediente)['retenidos'],
        ];
    }

    /** Cada fila que aplica tiene un solo origen, su ancla; las retiradas no tienen ninguno. */
    private function assertOrigenSgr(ProyectoExpediente $expediente, string $caso): void
    {
        foreach ($expediente->requisitos()->get() as $fila) {
            $this->assertSame($fila->aplica ? [$fila->requisito_catalogo_id] : [], $this->origenes($fila), "{$caso}: fila {$fila->id}");
        }
    }

    public function test_un_expediente_solo_sgr_queda_igual_que_antes_de_componer_plantillas(): void
    {
        $base = json_decode(file_get_contents(base_path('tests/Fixtures/paridad_evaluador_sgr.json')), true)['casos'];
        $esperados = json_decode(file_get_contents(base_path('tests/Fixtures/paridad_expediente_sgr.json')), true)['casos'];
        $this->assertCount(count($base), $esperados);

        foreach ($base as $i => $caso) {
            $esperado = $esperados[$i];
            $this->assertSame($caso['nombre'], $esperado['nombre']);
            $config = $this->configuracion($caso['configuracion']);
            $siguiente = $this->configuracion($base[($i + 1) % count($base)]['configuracion']);

            $expediente = $this->servicio->crear($this->proyecto("Caso {$i}"), $config, null);
            $this->servicio->confirmarConfiguracion($expediente, $config);
            foreach ($expediente->requisitos()->where('aplica', true)->orderBy('orden')->get()->values() as $k => $fila) {
                if ($k % 3 === 0) {
                    $this->servicio->marcar($expediente, $fila, ['cumplido' => true], null);
                }
            }
            $this->assertSame($esperado['inicial'], $this->foto($expediente), "{$caso['nombre']}: inicial");
            $this->assertOrigenSgr($expediente, $caso['nombre']);

            $this->servicio->actualizarConfiguracion($expediente, $siguiente);
            $this->assertSame($esperado['cambiada'], $this->foto($expediente->refresh()), "{$caso['nombre']}: cambiada");
            $this->assertOrigenSgr($expediente, $caso['nombre']);

            $this->servicio->actualizarConfiguracion($expediente, $config);
            $this->assertSame($esperado['restaurada'], $this->foto($expediente->refresh()), "{$caso['nombre']}: restaurada");
            $this->assertOrigenSgr($expediente, $caso['nombre']);
        }
    }

    // ------------------------------------------------------- código común

    public function test_dos_plantillas_con_el_mismo_codigo_comun_comparten_una_fila_con_el_caracter_mas_exigente(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Licencia ambiental' => ['licencia', 'opcional'], 'Planos' => [null, 'obligatorio']]);
        $vias = $this->plantilla('Vías', ['Licencia de la autoridad' => ['licencia', 'condicionado']]);
        $expediente = $this->expediente();

        $this->componer($expediente, [$agua, $vias]);

        $licencia = $this->fila($expediente, $this->requisito($agua, 'Licencia ambiental'));
        $this->assertSame(1, $expediente->requisitos()->where('aplica', true)->whereIn('requisito_catalogo_id', [
            $this->requisito($agua, 'Licencia ambiental')->id, $this->requisito($vias, 'Licencia de la autoridad')->id,
        ])->count());
        $this->assertTrue($licencia->aplica);
        $this->assertSame(RequisitoCatalogo::CONDICIONADO, $licencia->caracter);
        $this->assertSame([$this->requisito($agua, 'Licencia ambiental')->id, $this->requisito($vias, 'Licencia de la autoridad')->id], $this->origenes($licencia));
        $this->assertFalse($expediente->requisitos()->where('requisito_catalogo_id', $this->requisito($vias, 'Licencia de la autoridad')->id)->exists());
    }

    public function test_el_codigo_comun_se_comparte_con_la_sgr_y_solo_aporta_origen_lo_que_aplica(): void
    {
        // b1 (predios) no aplica en fase III sin componentes: la SGR no aporta origen.
        $predios = $this->plantilla('Predios', ['Estudio de títulos' => ['b1', 'obligatorio']]);
        $expediente = $this->expediente();
        $this->componer($expediente, [$this->sgr, $predios]);
        $estudio = $this->requisito($predios, 'Estudio de títulos');
        $b1 = RequisitoCatalogo::where('catalogo_version_id', $this->sgr->versionVigente()->id)->where('clave', 'b1')->sole();

        $fila = $this->fila($expediente, $estudio);
        $this->assertSame([$estudio->id], $this->origenes($fila));

        // Con el componente, la SGR también lo pide: la misma fila suma su origen y conserva su ancla.
        $this->servicio->actualizarConfiguracion($expediente, ['fase_sgr' => 2, 'componente_ids' => [$this->id('requisitos_componentes', 'predios')]]);

        $this->assertSame($fila->id, $this->fila($expediente, $estudio)->id);
        $this->assertSame([$b1->id, $estudio->id], $this->origenes($fila));
        $this->assertFalse($expediente->requisitos()->where('requisito_catalogo_id', $b1->id)->exists());
    }

    // ------------------------------------------------------- quitar y volver

    public function test_quitar_una_plantilla_retira_sus_filas_con_su_trabajo_y_volver_a_ponerla_las_recupera(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos' => [null, 'obligatorio']]);
        $expediente = $this->expediente();
        $this->componer($expediente, [$this->sgr, $agua]);
        $planos = $this->fila($expediente, $this->requisito($agua, 'Planos'));
        $this->servicio->marcar($expediente, $planos, ['cumplido' => true, 'observacion' => 'Firmados'], null);

        $this->componer($expediente, [$this->sgr]);

        $retirada = $planos->refresh();
        $this->assertFalse($retirada->aplica);
        $this->assertTrue($retirada->cumplido);
        $this->assertSame('Firmados', $retirada->observacion);
        $this->assertSame([], $this->origenes($retirada));
        $this->assertSame(8, $this->servicio->progresoDe($expediente)['total']);

        $this->componer($expediente, [$this->sgr, $agua]);

        $recuperada = $planos->refresh();
        $this->assertTrue($recuperada->aplica);
        $this->assertTrue($recuperada->cumplido);
        $this->assertSame('Firmados', $recuperada->observacion);
        $this->assertSame([$this->requisito($agua, 'Planos')->id], $this->origenes($recuperada));
    }

    public function test_quitar_una_de_dos_plantillas_que_comparten_fila_la_conserva_con_el_otro_origen(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Licencia ambiental' => ['licencia', 'obligatorio']]);
        $vias = $this->plantilla('Vías', ['Licencia de la autoridad' => ['licencia', 'opcional']]);
        $expediente = $this->expediente();
        $this->componer($expediente, [$agua, $vias]);
        $ancla = $this->requisito($agua, 'Licencia ambiental');
        $this->assertSame(RequisitoCatalogo::OBLIGATORIO, $this->fila($expediente, $ancla)->caracter);

        $this->componer($expediente, [$vias]);

        $fila = $this->fila($expediente, $ancla); // el ancla no cambia
        $this->assertTrue($fila->aplica);
        $this->assertSame(RequisitoCatalogo::OPCIONAL, $fila->caracter);
        $this->assertSame([$this->requisito($vias, 'Licencia de la autoridad')->id], $this->origenes($fila));
    }

    public function test_un_requisito_inactivo_no_aplica(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos' => [null, 'obligatorio'], 'Retirado' => [null, 'obligatorio', false]]);
        $expediente = $this->expediente();

        $this->componer($expediente, [$agua]);

        $this->assertSame(['Planos'], $expediente->requisitos()->where('aplica', true)->with('requisito')->get()->pluck('requisito.texto')->all());
    }

    // ----------------------------------------------------------- por definir

    public function test_un_condicionado_nace_por_definir_y_recalcular_no_cambia_su_estado(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, 'condicionado'], 'Planos' => [null, 'obligatorio']]);
        $expediente = $this->expediente();

        $this->componer($expediente, [$this->sgr, $agua]);

        $permiso = $this->fila($expediente, $this->requisito($agua, 'Permiso de vertimientos'));
        $this->assertSame(ProyectoRequisito::POR_DEFINIR, $permiso->estado_aplicabilidad);
        $this->assertSame(ProyectoRequisito::APLICA, $this->fila($expediente, $this->requisito($agua, 'Planos'))->estado_aplicabilidad);
        $this->assertSame(0, $expediente->requisitos()->where('aplica', true)->whereHas('requisito', fn ($q) => $q->whereNotNull('seccion'))
            ->where('estado_aplicabilidad', '!=', ProyectoRequisito::APLICA)->count());

        // Una persona decidió que aplica: recalcular no la devuelve a por definir.
        $permiso->update(['estado_aplicabilidad' => ProyectoRequisito::APLICA]);
        $this->componer($expediente, [$this->sgr, $agua]);
        $this->assertSame(ProyectoRequisito::APLICA, $permiso->refresh()->estado_aplicabilidad);
    }

    // ------------------------------------------------------------------ orden

    public function test_el_orden_sigue_a_la_plantilla_del_primer_origen_con_la_principal_primero(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos' => [null, 'obligatorio'], 'Presupuesto' => ['a2', 'obligatorio']]);
        $expediente = $this->expediente();

        $this->componer($expediente, [$this->sgr, $agua], $agua);

        $this->assertSame(1, $this->fila($expediente, $this->requisito($agua, 'Planos'))->orden);
        // a2 lo trae primero la principal, aunque la fila ya existía con el ancla SGR.
        $a2 = RequisitoCatalogo::where('catalogo_version_id', $this->sgr->versionVigente()->id)->where('clave', 'a2')->sole();
        $this->assertSame(2, $this->fila($expediente, $a2)->orden);
        // La SGR es la segunda plantilla: su posición suma 10 000 000 al orden por sección.
        $a1 = RequisitoCatalogo::where('catalogo_version_id', $this->sgr->versionVigente()->id)->where('clave', 'a1')->sole();
        $this->assertSame(10_000_000 + 100_000 + 1, $this->fila($expediente, $a1)->orden);

        $this->componer($expediente, [$this->sgr, $agua], $this->sgr);

        $this->assertSame(100_001, $this->fila($expediente, $a1)->orden);
        $this->assertSame(10_000_000 + 1, $this->fila($expediente, $this->requisito($agua, 'Planos'))->orden);
        $this->assertSame(100_002, $this->fila($expediente, $a2)->orden);
    }

    // --------------------------------------------------------------- propios

    public function test_sincronizar_no_toca_los_requisitos_propios(): void
    {
        $expediente = $this->expediente();
        $propio = $expediente->requisitos()->create([
            'requisito_catalogo_id' => null, 'titulo_propio' => 'Acta de socialización', 'aplica' => true,
            'caracter' => RequisitoCatalogo::OBLIGATORIO, 'orden' => 0,
        ]);
        $propio->update(['orden' => 900_000_000 + $propio->id, 'cumplido' => true]);

        $this->componer($expediente, [$this->plantilla('Obras de agua', ['Planos' => [null, 'obligatorio']])]);
        $this->componer($expediente, []);

        $despues = $propio->refresh();
        $this->assertTrue($despues->aplica);
        $this->assertTrue($despues->cumplido);
        $this->assertSame(900_000_000 + $propio->id, $despues->orden);
        $this->assertSame([], $this->origenes($despues));
        $this->assertSame(0, $expediente->requisitos()->where('aplica', true)->whereNotNull('requisito_catalogo_id')->count());
    }
}
```

Crear `tests/Fixtures/paridad_expediente_sgr.json`:

```json
{
    "origen": "Generado con ExpedienteService::sincronizar de la etapa 4, antes de la composición de plantillas. Por cada caso de paridad_evaluador_sgr.json: se crea y confirma, se cumple una de cada tres filas que aplican, se reconfigura con el caso siguiente y se vuelve a la configuración original. Cada fila es clave:aplica:orden:cumplido, ordenada por orden.",
    "casos": [
        {
            "nombre": "fase 3 sin nada",
            "luego": "fase 1 sin nada",
            "inicial": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a8:1:100008:0",
                    "a9:1:100009:0",
                    "a10:1:100010:1",
                    "a11:1:100011:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 8,
                    "cumplidos": 3,
                    "porcentaje": 38
                },
                "retenidos": 0
            },
            "cambiada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a6:1:100006:0",
                    "a7:1:100007:0",
                    "a8:0:100008:0",
                    "a9:0:100009:0",
                    "a10:0:100010:1",
                    "a11:0:100011:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 6,
                    "cumplidos": 2,
                    "porcentaje": 33
                },
                "retenidos": 1
            },
            "restaurada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a6:0:100006:0",
                    "a7:0:100007:0",
                    "a8:1:100008:0",
                    "a9:1:100009:0",
                    "a10:1:100010:1",
                    "a11:1:100011:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 8,
                    "cumplidos": 3,
                    "porcentaje": 38
                },
                "retenidos": 0
            }
        },
        {
            "nombre": "fase 1 sin nada",
            "luego": "fase 2 con predios y licencias",
            "inicial": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a6:1:100006:0",
                    "a7:1:100007:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 6,
                    "cumplidos": 2,
                    "porcentaje": 33
                },
                "retenidos": 0
            },
            "cambiada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a6:1:100006:0",
                    "a7:1:100007:0",
                    "b1:1:200021:0",
                    "b2:1:200022:0",
                    "b3:1:200023:0",
                    "b4:1:200024:0",
                    "b5:1:200025:0",
                    "b6:1:200026:0",
                    "b7:1:200027:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 13,
                    "cumplidos": 2,
                    "porcentaje": 15
                },
                "retenidos": 0
            },
            "restaurada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a6:1:100006:0",
                    "a7:1:100007:0",
                    "b1:0:200021:0",
                    "b2:0:200022:0",
                    "b3:0:200023:0",
                    "b4:0:200024:0",
                    "b5:0:200025:0",
                    "b6:0:200026:0",
                    "b7:0:200027:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 6,
                    "cumplidos": 2,
                    "porcentaje": 33
                },
                "retenidos": 0
            }
        },
        {
            "nombre": "fase 2 con predios y licencias",
            "luego": "fase 1 con predios (no aplica B en fase 1)",
            "inicial": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a6:1:100006:0",
                    "a7:1:100007:0",
                    "b1:1:200021:1",
                    "b2:1:200022:0",
                    "b3:1:200023:0",
                    "b4:1:200024:1",
                    "b5:1:200025:0",
                    "b6:1:200026:0",
                    "b7:1:200027:1"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 13,
                    "cumplidos": 5,
                    "porcentaje": 38
                },
                "retenidos": 0
            },
            "cambiada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a6:1:100006:0",
                    "a7:1:100007:0",
                    "b1:0:200021:1",
                    "b2:0:200022:0",
                    "b3:0:200023:0",
                    "b4:0:200024:1",
                    "b5:0:200025:0",
                    "b6:0:200026:0",
                    "b7:0:200027:1"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 6,
                    "cumplidos": 2,
                    "porcentaje": 33
                },
                "retenidos": 3
            },
            "restaurada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a6:1:100006:0",
                    "a7:1:100007:0",
                    "b1:1:200021:1",
                    "b2:1:200022:0",
                    "b3:1:200023:0",
                    "b4:1:200024:1",
                    "b5:1:200025:0",
                    "b6:1:200026:0",
                    "b7:1:200027:1"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 13,
                    "cumplidos": 5,
                    "porcentaje": 38
                },
                "retenidos": 0
            }
        },
        {
            "nombre": "fase 1 con predios (no aplica B en fase 1)",
            "luego": "fase 3 todos los componentes",
            "inicial": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a6:1:100006:0",
                    "a7:1:100007:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 6,
                    "cumplidos": 2,
                    "porcentaje": 33
                },
                "retenidos": 0
            },
            "cambiada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a5:1:100005:0",
                    "a6:0:100006:0",
                    "a7:0:100007:0",
                    "a8:1:100008:0",
                    "a9:1:100009:0",
                    "a10:1:100010:0",
                    "a11:1:100011:0",
                    "a12:1:100012:0",
                    "a13:1:100013:0",
                    "a14:1:100014:0",
                    "a15:1:100015:0",
                    "a16:1:100016:0",
                    "a17:1:100017:0",
                    "a18:1:100018:0",
                    "a19:1:100019:0",
                    "a20:1:100020:0",
                    "b1:1:200021:0",
                    "b2:1:200022:0",
                    "b3:1:200023:0",
                    "b4:1:200024:0",
                    "b5:1:200025:0",
                    "b6:1:200026:0",
                    "b7:1:200027:0",
                    "b8:1:200028:0",
                    "b9:1:200029:0",
                    "b10:1:200030:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 28,
                    "cumplidos": 2,
                    "porcentaje": 7
                },
                "retenidos": 0
            },
            "restaurada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a5:0:100005:0",
                    "a6:1:100006:0",
                    "a7:1:100007:0",
                    "a8:0:100008:0",
                    "a9:0:100009:0",
                    "a10:0:100010:0",
                    "a11:0:100011:0",
                    "a12:0:100012:0",
                    "a13:0:100013:0",
                    "a14:0:100014:0",
                    "a15:0:100015:0",
                    "a16:0:100016:0",
                    "a17:0:100017:0",
                    "a18:0:100018:0",
                    "a19:0:100019:0",
                    "a20:0:100020:0",
                    "b1:0:200021:0",
                    "b2:0:200022:0",
                    "b3:0:200023:0",
                    "b4:0:200024:0",
                    "b5:0:200025:0",
                    "b6:0:200026:0",
                    "b7:0:200027:0",
                    "b8:0:200028:0",
                    "b9:0:200029:0",
                    "b10:0:200030:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 6,
                    "cumplidos": 2,
                    "porcentaje": 33
                },
                "retenidos": 0
            }
        },
        {
            "nombre": "fase 3 todos los componentes",
            "luego": "fase 3 desastre sin sector",
            "inicial": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a5:1:100005:0",
                    "a8:1:100008:0",
                    "a9:1:100009:1",
                    "a10:1:100010:0",
                    "a11:1:100011:0",
                    "a12:1:100012:1",
                    "a13:1:100013:0",
                    "a14:1:100014:0",
                    "a15:1:100015:1",
                    "a16:1:100016:0",
                    "a17:1:100017:0",
                    "a18:1:100018:1",
                    "a19:1:100019:0",
                    "a20:1:100020:0",
                    "b1:1:200021:1",
                    "b2:1:200022:0",
                    "b3:1:200023:0",
                    "b4:1:200024:1",
                    "b5:1:200025:0",
                    "b6:1:200026:0",
                    "b7:1:200027:1",
                    "b8:1:200028:0",
                    "b9:1:200029:0",
                    "b10:1:200030:1"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 28,
                    "cumplidos": 10,
                    "porcentaje": 36
                },
                "retenidos": 0
            },
            "cambiada": {
                "filas": [
                    "a1:0:100001:1",
                    "a2:0:100002:0",
                    "a3:0:100003:0",
                    "a4:0:100004:1",
                    "a5:0:100005:0",
                    "a8:0:100008:0",
                    "a9:0:100009:1",
                    "a10:0:100010:0",
                    "a11:0:100011:0",
                    "a12:0:100012:1",
                    "a13:0:100013:0",
                    "a14:0:100014:0",
                    "a15:0:100015:1",
                    "a16:0:100016:0",
                    "a17:0:100017:0",
                    "a18:0:100018:1",
                    "a19:0:100019:0",
                    "a20:0:100020:0",
                    "b1:0:200021:1",
                    "b2:0:200022:0",
                    "b3:0:200023:0",
                    "b4:0:200024:1",
                    "b5:0:200025:0",
                    "b6:0:200026:0",
                    "b7:0:200027:1",
                    "b8:0:200028:0",
                    "b9:0:200029:0",
                    "b10:0:200030:1",
                    "d1:1:300036:0",
                    "d2:1:300037:0",
                    "d3:1:300038:0",
                    "d4:1:300039:0",
                    "d5:1:300040:0",
                    "d6:1:300041:0",
                    "d7:1:300042:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 7,
                    "cumplidos": 0,
                    "porcentaje": 0
                },
                "retenidos": 10
            },
            "restaurada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a5:1:100005:0",
                    "a8:1:100008:0",
                    "a9:1:100009:1",
                    "a10:1:100010:0",
                    "a11:1:100011:0",
                    "a12:1:100012:1",
                    "a13:1:100013:0",
                    "a14:1:100014:0",
                    "a15:1:100015:1",
                    "a16:1:100016:0",
                    "a17:1:100017:0",
                    "a18:1:100018:1",
                    "a19:1:100019:0",
                    "a20:1:100020:0",
                    "b1:1:200021:1",
                    "b2:1:200022:0",
                    "b3:1:200023:0",
                    "b4:1:200024:1",
                    "b5:1:200025:0",
                    "b6:1:200026:0",
                    "b7:1:200027:1",
                    "b8:1:200028:0",
                    "b9:1:200029:0",
                    "b10:1:200030:1",
                    "d1:0:300036:0",
                    "d2:0:300037:0",
                    "d3:0:300038:0",
                    "d4:0:300039:0",
                    "d5:0:300040:0",
                    "d6:0:300041:0",
                    "d7:0:300042:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 28,
                    "cumplidos": 10,
                    "porcentaje": 36
                },
                "retenidos": 0
            }
        },
        {
            "nombre": "fase 3 desastre sin sector",
            "luego": "fase 3 desastre + predios + salud",
            "inicial": {
                "filas": [
                    "d1:1:300036:1",
                    "d2:1:300037:0",
                    "d3:1:300038:0",
                    "d4:1:300039:1",
                    "d5:1:300040:0",
                    "d6:1:300041:0",
                    "d7:1:300042:1"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 7,
                    "cumplidos": 3,
                    "porcentaje": 43
                },
                "retenidos": 0
            },
            "cambiada": {
                "filas": [
                    "b1:1:200021:0",
                    "b2:1:200022:0",
                    "b3:1:200023:0",
                    "b4:1:200024:0",
                    "b5:1:200025:0",
                    "b6:1:200026:0",
                    "d1:1:300036:1",
                    "d2:1:300037:0",
                    "d3:1:300038:0",
                    "d4:1:300039:1",
                    "d5:1:300040:0",
                    "d6:1:300041:0",
                    "d7:1:300042:1",
                    "sa1a:1:400141:0",
                    "sa1b:1:400142:0",
                    "sa1c:1:400143:0",
                    "sa1d:1:400144:0",
                    "sa1e:1:400145:0",
                    "sa2a:1:400146:0",
                    "sa2b:1:400147:0",
                    "sa2c:1:400148:0",
                    "sa3:1:400149:0",
                    "sa4a:1:400150:0",
                    "sa4b:1:400151:0",
                    "sa5:1:400152:0",
                    "sa6a:1:400153:0",
                    "sa6b:1:400154:0",
                    "sa6c:1:400155:0",
                    "sa6d:1:400156:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 29,
                    "cumplidos": 3,
                    "porcentaje": 10
                },
                "retenidos": 0
            },
            "restaurada": {
                "filas": [
                    "b1:0:200021:0",
                    "b2:0:200022:0",
                    "b3:0:200023:0",
                    "b4:0:200024:0",
                    "b5:0:200025:0",
                    "b6:0:200026:0",
                    "d1:1:300036:1",
                    "d2:1:300037:0",
                    "d3:1:300038:0",
                    "d4:1:300039:1",
                    "d5:1:300040:0",
                    "d6:1:300041:0",
                    "d7:1:300042:1",
                    "sa1a:0:400141:0",
                    "sa1b:0:400142:0",
                    "sa1c:0:400143:0",
                    "sa1d:0:400144:0",
                    "sa1e:0:400145:0",
                    "sa2a:0:400146:0",
                    "sa2b:0:400147:0",
                    "sa2c:0:400148:0",
                    "sa3:0:400149:0",
                    "sa4a:0:400150:0",
                    "sa4b:0:400151:0",
                    "sa5:0:400152:0",
                    "sa6a:0:400153:0",
                    "sa6b:0:400154:0",
                    "sa6c:0:400155:0",
                    "sa6d:0:400156:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 7,
                    "cumplidos": 3,
                    "porcentaje": 43
                },
                "retenidos": 0
            }
        },
        {
            "nombre": "fase 3 desastre + predios + salud",
            "luego": "fase 3 justicia completo",
            "inicial": {
                "filas": [
                    "b1:1:200021:1",
                    "b2:1:200022:0",
                    "b3:1:200023:0",
                    "b4:1:200024:1",
                    "b5:1:200025:0",
                    "b6:1:200026:0",
                    "d1:1:300036:1",
                    "d2:1:300037:0",
                    "d3:1:300038:0",
                    "d4:1:300039:1",
                    "d5:1:300040:0",
                    "d6:1:300041:0",
                    "d7:1:300042:1",
                    "sa1a:1:400141:0",
                    "sa1b:1:400142:0",
                    "sa1c:1:400143:1",
                    "sa1d:1:400144:0",
                    "sa1e:1:400145:0",
                    "sa2a:1:400146:1",
                    "sa2b:1:400147:0",
                    "sa2c:1:400148:0",
                    "sa3:1:400149:1",
                    "sa4a:1:400150:0",
                    "sa4b:1:400151:0",
                    "sa5:1:400152:1",
                    "sa6a:1:400153:0",
                    "sa6b:1:400154:0",
                    "sa6c:1:400155:1",
                    "sa6d:1:400156:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 29,
                    "cumplidos": 10,
                    "porcentaje": 34
                },
                "retenidos": 0
            },
            "cambiada": {
                "filas": [
                    "a1:1:100001:0",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:0",
                    "a8:1:100008:0",
                    "a9:1:100009:0",
                    "a10:1:100010:0",
                    "a11:1:100011:0",
                    "b1:0:200021:1",
                    "b2:0:200022:0",
                    "b3:0:200023:0",
                    "b4:0:200024:1",
                    "b5:0:200025:0",
                    "b6:0:200026:0",
                    "d1:0:300036:1",
                    "d2:0:300037:0",
                    "d3:0:300038:0",
                    "d4:0:300039:1",
                    "d5:0:300040:0",
                    "d6:0:300041:0",
                    "d7:0:300042:1",
                    "j1:1:400043:0",
                    "j2a:1:400044:0",
                    "j2b:1:400045:0",
                    "j2c:1:400046:0",
                    "j2d:1:400047:0",
                    "j2e:1:400048:0",
                    "j2f:1:400049:0",
                    "j2g:1:400050:0",
                    "j2h:1:400051:0",
                    "j2i:1:400052:0",
                    "j2j:1:400053:0",
                    "j2k:1:400054:0",
                    "j2l:1:400055:0",
                    "j2m:1:400056:0",
                    "j3a:1:400057:0",
                    "j3b:1:400058:0",
                    "j3c:1:400059:0",
                    "j3d:1:400060:0",
                    "sa1a:0:400141:0",
                    "sa1b:0:400142:0",
                    "sa1c:0:400143:1",
                    "sa1d:0:400144:0",
                    "sa1e:0:400145:0",
                    "sa2a:0:400146:1",
                    "sa2b:0:400147:0",
                    "sa2c:0:400148:0",
                    "sa3:0:400149:1",
                    "sa4a:0:400150:0",
                    "sa4b:0:400151:0",
                    "sa5:0:400152:1",
                    "sa6a:0:400153:0",
                    "sa6b:0:400154:0",
                    "sa6c:0:400155:1",
                    "sa6d:0:400156:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 26,
                    "cumplidos": 0,
                    "porcentaje": 0
                },
                "retenidos": 10
            },
            "restaurada": {
                "filas": [
                    "a1:0:100001:0",
                    "a2:0:100002:0",
                    "a3:0:100003:0",
                    "a4:0:100004:0",
                    "a8:0:100008:0",
                    "a9:0:100009:0",
                    "a10:0:100010:0",
                    "a11:0:100011:0",
                    "b1:1:200021:1",
                    "b2:1:200022:0",
                    "b3:1:200023:0",
                    "b4:1:200024:1",
                    "b5:1:200025:0",
                    "b6:1:200026:0",
                    "d1:1:300036:1",
                    "d2:1:300037:0",
                    "d3:1:300038:0",
                    "d4:1:300039:1",
                    "d5:1:300040:0",
                    "d6:1:300041:0",
                    "d7:1:300042:1",
                    "j1:0:400043:0",
                    "j2a:0:400044:0",
                    "j2b:0:400045:0",
                    "j2c:0:400046:0",
                    "j2d:0:400047:0",
                    "j2e:0:400048:0",
                    "j2f:0:400049:0",
                    "j2g:0:400050:0",
                    "j2h:0:400051:0",
                    "j2i:0:400052:0",
                    "j2j:0:400053:0",
                    "j2k:0:400054:0",
                    "j2l:0:400055:0",
                    "j2m:0:400056:0",
                    "j3a:0:400057:0",
                    "j3b:0:400058:0",
                    "j3c:0:400059:0",
                    "j3d:0:400060:0",
                    "sa1a:1:400141:0",
                    "sa1b:1:400142:0",
                    "sa1c:1:400143:1",
                    "sa1d:1:400144:0",
                    "sa1e:1:400145:0",
                    "sa2a:1:400146:1",
                    "sa2b:1:400147:0",
                    "sa2c:1:400148:0",
                    "sa3:1:400149:1",
                    "sa4a:1:400150:0",
                    "sa4b:1:400151:0",
                    "sa5:1:400152:1",
                    "sa6a:1:400153:0",
                    "sa6b:1:400154:0",
                    "sa6c:1:400155:1",
                    "sa6d:1:400156:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 29,
                    "cumplidos": 10,
                    "porcentaje": 34
                },
                "retenidos": 0
            }
        },
        {
            "nombre": "fase 3 justicia completo",
            "luego": "fase 3 justicia solo casas de justicia",
            "inicial": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a8:1:100008:0",
                    "a9:1:100009:0",
                    "a10:1:100010:1",
                    "a11:1:100011:0",
                    "j1:1:400043:0",
                    "j2a:1:400044:1",
                    "j2b:1:400045:0",
                    "j2c:1:400046:0",
                    "j2d:1:400047:1",
                    "j2e:1:400048:0",
                    "j2f:1:400049:0",
                    "j2g:1:400050:1",
                    "j2h:1:400051:0",
                    "j2i:1:400052:0",
                    "j2j:1:400053:1",
                    "j2k:1:400054:0",
                    "j2l:1:400055:0",
                    "j2m:1:400056:1",
                    "j3a:1:400057:0",
                    "j3b:1:400058:0",
                    "j3c:1:400059:1",
                    "j3d:1:400060:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 26,
                    "cumplidos": 9,
                    "porcentaje": 35
                },
                "retenidos": 0
            },
            "cambiada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a8:1:100008:0",
                    "a9:1:100009:0",
                    "a10:1:100010:1",
                    "a11:1:100011:0",
                    "j1:1:400043:0",
                    "j2a:0:400044:1",
                    "j2b:0:400045:0",
                    "j2c:0:400046:0",
                    "j2d:0:400047:1",
                    "j2e:0:400048:0",
                    "j2f:0:400049:0",
                    "j2g:0:400050:1",
                    "j2h:0:400051:0",
                    "j2i:0:400052:0",
                    "j2j:0:400053:1",
                    "j2k:0:400054:0",
                    "j2l:0:400055:0",
                    "j2m:0:400056:1",
                    "j3a:0:400057:0",
                    "j3b:0:400058:0",
                    "j3c:0:400059:1",
                    "j3d:0:400060:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 9,
                    "cumplidos": 3,
                    "porcentaje": 33
                },
                "retenidos": 6
            },
            "restaurada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a8:1:100008:0",
                    "a9:1:100009:0",
                    "a10:1:100010:1",
                    "a11:1:100011:0",
                    "j1:1:400043:0",
                    "j2a:1:400044:1",
                    "j2b:1:400045:0",
                    "j2c:1:400046:0",
                    "j2d:1:400047:1",
                    "j2e:1:400048:0",
                    "j2f:1:400049:0",
                    "j2g:1:400050:1",
                    "j2h:1:400051:0",
                    "j2i:1:400052:0",
                    "j2j:1:400053:1",
                    "j2k:1:400054:0",
                    "j2l:1:400055:0",
                    "j2m:1:400056:1",
                    "j3a:1:400057:0",
                    "j3b:1:400058:0",
                    "j3c:1:400059:1",
                    "j3d:1:400060:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 26,
                    "cumplidos": 9,
                    "porcentaje": 35
                },
                "retenidos": 0
            }
        },
        {
            "nombre": "fase 3 justicia solo casas de justicia",
            "luego": "fase 3 educación completo + vivienda solo agua",
            "inicial": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a8:1:100008:0",
                    "a9:1:100009:0",
                    "a10:1:100010:1",
                    "a11:1:100011:0",
                    "j1:1:400043:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 9,
                    "cumplidos": 3,
                    "porcentaje": 33
                },
                "retenidos": 0
            },
            "cambiada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a8:1:100008:0",
                    "a9:1:100009:0",
                    "a10:1:100010:1",
                    "a11:1:100011:0",
                    "j1:0:400043:0",
                    "ed1a:1:400100:0",
                    "ed1b:1:400101:0",
                    "ed1c:1:400102:0",
                    "ed1d:1:400103:0",
                    "ed1e:1:400104:0",
                    "ed2a:1:400105:0",
                    "ed2b:1:400106:0",
                    "ed2c:1:400107:0",
                    "ed3a:1:400108:0",
                    "ed3b:1:400109:0",
                    "ed3c:1:400110:0",
                    "ed4a:1:400111:0",
                    "ed4b:1:400112:0",
                    "ed4c:1:400113:0",
                    "ed5:1:400114:0",
                    "vi1a:1:400188:0",
                    "vi1b:1:400189:0",
                    "vi1c:1:400190:0",
                    "vi1d:1:400191:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 27,
                    "cumplidos": 3,
                    "porcentaje": 11
                },
                "retenidos": 0
            },
            "restaurada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a8:1:100008:0",
                    "a9:1:100009:0",
                    "a10:1:100010:1",
                    "a11:1:100011:0",
                    "j1:1:400043:0",
                    "ed1a:0:400100:0",
                    "ed1b:0:400101:0",
                    "ed1c:0:400102:0",
                    "ed1d:0:400103:0",
                    "ed1e:0:400104:0",
                    "ed2a:0:400105:0",
                    "ed2b:0:400106:0",
                    "ed2c:0:400107:0",
                    "ed3a:0:400108:0",
                    "ed3b:0:400109:0",
                    "ed3c:0:400110:0",
                    "ed4a:0:400111:0",
                    "ed4b:0:400112:0",
                    "ed4c:0:400113:0",
                    "ed5:0:400114:0",
                    "vi1a:0:400188:0",
                    "vi1b:0:400189:0",
                    "vi1c:0:400190:0",
                    "vi1d:0:400191:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 9,
                    "cumplidos": 3,
                    "porcentaje": 33
                },
                "retenidos": 0
            }
        },
        {
            "nombre": "fase 3 educación completo + vivienda solo agua",
            "luego": "fase 1 tipologías especiales + maquinaria",
            "inicial": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a8:1:100008:0",
                    "a9:1:100009:0",
                    "a10:1:100010:1",
                    "a11:1:100011:0",
                    "ed1a:1:400100:0",
                    "ed1b:1:400101:1",
                    "ed1c:1:400102:0",
                    "ed1d:1:400103:0",
                    "ed1e:1:400104:1",
                    "ed2a:1:400105:0",
                    "ed2b:1:400106:0",
                    "ed2c:1:400107:1",
                    "ed3a:1:400108:0",
                    "ed3b:1:400109:0",
                    "ed3c:1:400110:1",
                    "ed4a:1:400111:0",
                    "ed4b:1:400112:0",
                    "ed4c:1:400113:1",
                    "ed5:1:400114:0",
                    "vi1a:1:400188:0",
                    "vi1b:1:400189:1",
                    "vi1c:1:400190:0",
                    "vi1d:1:400191:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 27,
                    "cumplidos": 9,
                    "porcentaje": 33
                },
                "retenidos": 0
            },
            "cambiada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a6:1:100006:0",
                    "a7:1:100007:0",
                    "a8:0:100008:0",
                    "a9:0:100009:0",
                    "a10:0:100010:1",
                    "a11:0:100011:0",
                    "b8:1:200028:0",
                    "b9:1:200029:0",
                    "b10:1:200030:0",
                    "b11:1:200031:0",
                    "b12:1:200032:0",
                    "b13:1:200033:0",
                    "b14:1:200034:0",
                    "b15:1:200035:0",
                    "ed1a:0:400100:0",
                    "ed1b:0:400101:1",
                    "ed1c:0:400102:0",
                    "ed1d:0:400103:0",
                    "ed1e:0:400104:1",
                    "ed2a:0:400105:0",
                    "ed2b:0:400106:0",
                    "ed2c:0:400107:1",
                    "ed3a:0:400108:0",
                    "ed3b:0:400109:0",
                    "ed3c:0:400110:1",
                    "ed4a:0:400111:0",
                    "ed4b:0:400112:0",
                    "ed4c:0:400113:1",
                    "ed5:0:400114:0",
                    "vi1a:0:400188:0",
                    "vi1b:0:400189:1",
                    "vi1c:0:400190:0",
                    "vi1d:0:400191:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 14,
                    "cumplidos": 2,
                    "porcentaje": 14
                },
                "retenidos": 7
            },
            "restaurada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a6:0:100006:0",
                    "a7:0:100007:0",
                    "a8:1:100008:0",
                    "a9:1:100009:0",
                    "a10:1:100010:1",
                    "a11:1:100011:0",
                    "b8:0:200028:0",
                    "b9:0:200029:0",
                    "b10:0:200030:0",
                    "b11:0:200031:0",
                    "b12:0:200032:0",
                    "b13:0:200033:0",
                    "b14:0:200034:0",
                    "b15:0:200035:0",
                    "ed1a:1:400100:0",
                    "ed1b:1:400101:1",
                    "ed1c:1:400102:0",
                    "ed1d:1:400103:0",
                    "ed1e:1:400104:1",
                    "ed2a:1:400105:0",
                    "ed2b:1:400106:0",
                    "ed2c:1:400107:1",
                    "ed3a:1:400108:0",
                    "ed3b:1:400109:0",
                    "ed3c:1:400110:1",
                    "ed4a:1:400111:0",
                    "ed4b:1:400112:0",
                    "ed4c:1:400113:1",
                    "ed5:1:400114:0",
                    "vi1a:1:400188:0",
                    "vi1b:1:400189:1",
                    "vi1c:1:400190:0",
                    "vi1d:1:400191:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 27,
                    "cumplidos": 9,
                    "porcentaje": 33
                },
                "retenidos": 0
            }
        },
        {
            "nombre": "fase 1 tipologías especiales + maquinaria",
            "luego": "fase 3 ctei solo formación (incluye ítems de todo el sector)",
            "inicial": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a6:1:100006:0",
                    "a7:1:100007:0",
                    "b8:1:200028:1",
                    "b9:1:200029:0",
                    "b10:1:200030:0",
                    "b11:1:200031:1",
                    "b12:1:200032:0",
                    "b13:1:200033:0",
                    "b14:1:200034:1",
                    "b15:1:200035:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 14,
                    "cumplidos": 5,
                    "porcentaje": 36
                },
                "retenidos": 0
            },
            "cambiada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a6:0:100006:0",
                    "a7:0:100007:0",
                    "a8:1:100008:0",
                    "a9:1:100009:0",
                    "a10:1:100010:0",
                    "a11:1:100011:0",
                    "b8:0:200028:1",
                    "b9:0:200029:0",
                    "b10:0:200030:0",
                    "b11:0:200031:1",
                    "b12:0:200032:0",
                    "b13:0:200033:0",
                    "b14:0:200034:1",
                    "b15:0:200035:0",
                    "ct1a:1:400061:0",
                    "ct1b:1:400062:0",
                    "ct1c:1:400063:0",
                    "ct3a:1:400066:0",
                    "ct3b:1:400067:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 13,
                    "cumplidos": 2,
                    "porcentaje": 15
                },
                "retenidos": 3
            },
            "restaurada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a6:1:100006:0",
                    "a7:1:100007:0",
                    "a8:0:100008:0",
                    "a9:0:100009:0",
                    "a10:0:100010:0",
                    "a11:0:100011:0",
                    "b8:1:200028:1",
                    "b9:1:200029:0",
                    "b10:1:200030:0",
                    "b11:1:200031:1",
                    "b12:1:200032:0",
                    "b13:1:200033:0",
                    "b14:1:200034:1",
                    "b15:1:200035:0",
                    "ct1a:0:400061:0",
                    "ct1b:0:400062:0",
                    "ct1c:0:400063:0",
                    "ct3a:0:400066:0",
                    "ct3b:0:400067:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 14,
                    "cumplidos": 5,
                    "porcentaje": 36
                },
                "retenidos": 0
            }
        },
        {
            "nombre": "fase 3 ctei solo formación (incluye ítems de todo el sector)",
            "luego": "fase 2 todos los sectores + suelo",
            "inicial": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a8:1:100008:0",
                    "a9:1:100009:0",
                    "a10:1:100010:1",
                    "a11:1:100011:0",
                    "ct1a:1:400061:0",
                    "ct1b:1:400062:1",
                    "ct1c:1:400063:0",
                    "ct3a:1:400066:0",
                    "ct3b:1:400067:1"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 13,
                    "cumplidos": 5,
                    "porcentaje": 38
                },
                "retenidos": 0
            },
            "cambiada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a5:1:100005:0",
                    "a6:1:100006:0",
                    "a7:1:100007:0",
                    "a8:0:100008:0",
                    "a9:0:100009:0",
                    "a10:0:100010:1",
                    "a11:0:100011:0",
                    "j1:1:400043:0",
                    "j2a:1:400044:0",
                    "j2b:1:400045:0",
                    "j2c:1:400046:0",
                    "j2d:1:400047:0",
                    "j2e:1:400048:0",
                    "j2f:1:400049:0",
                    "j2g:1:400050:0",
                    "j2h:1:400051:0",
                    "j2i:1:400052:0",
                    "j2j:1:400053:0",
                    "j2k:1:400054:0",
                    "j2l:1:400055:0",
                    "j2m:1:400056:0",
                    "j3a:1:400057:0",
                    "j3b:1:400058:0",
                    "j3c:1:400059:0",
                    "j3d:1:400060:0",
                    "ct1a:1:400061:0",
                    "ct1b:1:400062:1",
                    "ct1c:1:400063:0",
                    "ct2a:1:400064:0",
                    "ct2b:1:400065:0",
                    "ct3a:1:400066:0",
                    "ct3b:1:400067:1",
                    "ct4a:1:400068:0",
                    "ct4b:1:400069:0",
                    "ct5a:1:400070:0",
                    "ct5b:1:400071:0",
                    "co1:1:400072:0",
                    "co2a:1:400073:0",
                    "co2b:1:400074:0",
                    "co2c:1:400075:0",
                    "co2d:1:400076:0",
                    "co2e:1:400077:0",
                    "co2f:1:400078:0",
                    "co3:1:400079:0",
                    "co4:1:400080:0",
                    "ag1a:1:400081:0",
                    "ag1b:1:400082:0",
                    "ag1c:1:400083:0",
                    "ag2:1:400084:0",
                    "ag3:1:400085:0",
                    "am1:1:400086:0",
                    "am2:1:400087:0",
                    "cu1:1:400088:0",
                    "cu2a:1:400089:0",
                    "cu2b:1:400090:0",
                    "cu3a:1:400091:0",
                    "cu3b:1:400092:0",
                    "cu3c:1:400093:0",
                    "de1:1:400094:0",
                    "de2:1:400095:0",
                    "de3:1:400096:0",
                    "de4:1:400097:0",
                    "de5:1:400098:0",
                    "de6:1:400099:0",
                    "ed1a:1:400100:0",
                    "ed1b:1:400101:0",
                    "ed1c:1:400102:0",
                    "ed1d:1:400103:0",
                    "ed1e:1:400104:0",
                    "ed2a:1:400105:0",
                    "ed2b:1:400106:0",
                    "ed2c:1:400107:0",
                    "ed3a:1:400108:0",
                    "ed3b:1:400109:0",
                    "ed3c:1:400110:0",
                    "ed4a:1:400111:0",
                    "ed4b:1:400112:0",
                    "ed4c:1:400113:0",
                    "ed5:1:400114:0",
                    "in1a:1:400115:0",
                    "in1b:1:400116:0",
                    "in2a:1:400117:0",
                    "in2b:1:400118:0",
                    "in3:1:400119:0",
                    "in4a:1:400120:0",
                    "in4b:1:400121:0",
                    "in4c:1:400122:0",
                    "mi1a:1:400123:0",
                    "mi1b:1:400124:0",
                    "mi1c:1:400125:0",
                    "mi1d:1:400126:0",
                    "mi2b:1:400128:0",
                    "mi3a:1:400136:0",
                    "mi3b:1:400137:0",
                    "mi3c:1:400138:0",
                    "mi3d:1:400139:0",
                    "mi3e:1:400140:0",
                    "sa1a:1:400141:0",
                    "sa1b:1:400142:0",
                    "sa1c:1:400143:0",
                    "sa1d:1:400144:0",
                    "sa1e:1:400145:0",
                    "sa2a:1:400146:0",
                    "sa2b:1:400147:0",
                    "sa2c:1:400148:0",
                    "sa3:1:400149:0",
                    "sa4a:1:400150:0",
                    "sa4b:1:400151:0",
                    "sa5:1:400152:0",
                    "sa6a:1:400153:0",
                    "sa6b:1:400154:0",
                    "sa6c:1:400155:0",
                    "sa6d:1:400156:0",
                    "ti1a:1:400157:0",
                    "ti1b:1:400158:0",
                    "ti1c:1:400159:0",
                    "ti2a:1:400160:0",
                    "ti2b:1:400161:0",
                    "ti2c:1:400162:0",
                    "ti2d:1:400163:0",
                    "ti3:1:400164:0",
                    "ti4a:1:400165:0",
                    "ti4b:1:400166:0",
                    "ti5a:1:400167:0",
                    "ti5b:1:400168:0",
                    "ti5c:1:400169:0",
                    "tr1a:1:400170:0",
                    "tr1b:1:400171:0",
                    "tr2a:1:400172:0",
                    "tr2b:1:400173:0",
                    "tr2c:1:400174:0",
                    "tr2d:1:400175:0",
                    "tr3:1:400176:0",
                    "tr4a:1:400177:0",
                    "tr4b:1:400178:0",
                    "tr5:1:400179:0",
                    "tr6:1:400180:0",
                    "tr7a:1:400181:0",
                    "tr7b:1:400182:0",
                    "tr8:1:400183:0",
                    "tr9a:1:400184:0",
                    "tr9b:1:400185:0",
                    "tr10a:1:400186:0",
                    "tr10b:1:400187:0",
                    "vi1a:1:400188:0",
                    "vi1b:1:400189:0",
                    "vi1c:1:400190:0",
                    "vi1d:1:400191:0",
                    "vi2:1:400192:0",
                    "vi3a:1:400193:0",
                    "vi3b:1:400194:0",
                    "vi4a:1:400195:0",
                    "vi5a:1:400197:0",
                    "vi5b:1:400198:0",
                    "vi5c:1:400199:0",
                    "vi6a:1:400200:0",
                    "vi6b:1:400201:0",
                    "vi7a:1:400202:0",
                    "vi7b:1:400203:0",
                    "vi8a:1:400204:0",
                    "vi8b:1:400205:0",
                    "vi9a:1:400206:0",
                    "vi9b:1:400207:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 163,
                    "cumplidos": 4,
                    "porcentaje": 2
                },
                "retenidos": 1
            },
            "restaurada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a5:0:100005:0",
                    "a6:0:100006:0",
                    "a7:0:100007:0",
                    "a8:1:100008:0",
                    "a9:1:100009:0",
                    "a10:1:100010:1",
                    "a11:1:100011:0",
                    "j1:0:400043:0",
                    "j2a:0:400044:0",
                    "j2b:0:400045:0",
                    "j2c:0:400046:0",
                    "j2d:0:400047:0",
                    "j2e:0:400048:0",
                    "j2f:0:400049:0",
                    "j2g:0:400050:0",
                    "j2h:0:400051:0",
                    "j2i:0:400052:0",
                    "j2j:0:400053:0",
                    "j2k:0:400054:0",
                    "j2l:0:400055:0",
                    "j2m:0:400056:0",
                    "j3a:0:400057:0",
                    "j3b:0:400058:0",
                    "j3c:0:400059:0",
                    "j3d:0:400060:0",
                    "ct1a:1:400061:0",
                    "ct1b:1:400062:1",
                    "ct1c:1:400063:0",
                    "ct2a:0:400064:0",
                    "ct2b:0:400065:0",
                    "ct3a:1:400066:0",
                    "ct3b:1:400067:1",
                    "ct4a:0:400068:0",
                    "ct4b:0:400069:0",
                    "ct5a:0:400070:0",
                    "ct5b:0:400071:0",
                    "co1:0:400072:0",
                    "co2a:0:400073:0",
                    "co2b:0:400074:0",
                    "co2c:0:400075:0",
                    "co2d:0:400076:0",
                    "co2e:0:400077:0",
                    "co2f:0:400078:0",
                    "co3:0:400079:0",
                    "co4:0:400080:0",
                    "ag1a:0:400081:0",
                    "ag1b:0:400082:0",
                    "ag1c:0:400083:0",
                    "ag2:0:400084:0",
                    "ag3:0:400085:0",
                    "am1:0:400086:0",
                    "am2:0:400087:0",
                    "cu1:0:400088:0",
                    "cu2a:0:400089:0",
                    "cu2b:0:400090:0",
                    "cu3a:0:400091:0",
                    "cu3b:0:400092:0",
                    "cu3c:0:400093:0",
                    "de1:0:400094:0",
                    "de2:0:400095:0",
                    "de3:0:400096:0",
                    "de4:0:400097:0",
                    "de5:0:400098:0",
                    "de6:0:400099:0",
                    "ed1a:0:400100:0",
                    "ed1b:0:400101:0",
                    "ed1c:0:400102:0",
                    "ed1d:0:400103:0",
                    "ed1e:0:400104:0",
                    "ed2a:0:400105:0",
                    "ed2b:0:400106:0",
                    "ed2c:0:400107:0",
                    "ed3a:0:400108:0",
                    "ed3b:0:400109:0",
                    "ed3c:0:400110:0",
                    "ed4a:0:400111:0",
                    "ed4b:0:400112:0",
                    "ed4c:0:400113:0",
                    "ed5:0:400114:0",
                    "in1a:0:400115:0",
                    "in1b:0:400116:0",
                    "in2a:0:400117:0",
                    "in2b:0:400118:0",
                    "in3:0:400119:0",
                    "in4a:0:400120:0",
                    "in4b:0:400121:0",
                    "in4c:0:400122:0",
                    "mi1a:0:400123:0",
                    "mi1b:0:400124:0",
                    "mi1c:0:400125:0",
                    "mi1d:0:400126:0",
                    "mi2b:0:400128:0",
                    "mi3a:0:400136:0",
                    "mi3b:0:400137:0",
                    "mi3c:0:400138:0",
                    "mi3d:0:400139:0",
                    "mi3e:0:400140:0",
                    "sa1a:0:400141:0",
                    "sa1b:0:400142:0",
                    "sa1c:0:400143:0",
                    "sa1d:0:400144:0",
                    "sa1e:0:400145:0",
                    "sa2a:0:400146:0",
                    "sa2b:0:400147:0",
                    "sa2c:0:400148:0",
                    "sa3:0:400149:0",
                    "sa4a:0:400150:0",
                    "sa4b:0:400151:0",
                    "sa5:0:400152:0",
                    "sa6a:0:400153:0",
                    "sa6b:0:400154:0",
                    "sa6c:0:400155:0",
                    "sa6d:0:400156:0",
                    "ti1a:0:400157:0",
                    "ti1b:0:400158:0",
                    "ti1c:0:400159:0",
                    "ti2a:0:400160:0",
                    "ti2b:0:400161:0",
                    "ti2c:0:400162:0",
                    "ti2d:0:400163:0",
                    "ti3:0:400164:0",
                    "ti4a:0:400165:0",
                    "ti4b:0:400166:0",
                    "ti5a:0:400167:0",
                    "ti5b:0:400168:0",
                    "ti5c:0:400169:0",
                    "tr1a:0:400170:0",
                    "tr1b:0:400171:0",
                    "tr2a:0:400172:0",
                    "tr2b:0:400173:0",
                    "tr2c:0:400174:0",
                    "tr2d:0:400175:0",
                    "tr3:0:400176:0",
                    "tr4a:0:400177:0",
                    "tr4b:0:400178:0",
                    "tr5:0:400179:0",
                    "tr6:0:400180:0",
                    "tr7a:0:400181:0",
                    "tr7b:0:400182:0",
                    "tr8:0:400183:0",
                    "tr9a:0:400184:0",
                    "tr9b:0:400185:0",
                    "tr10a:0:400186:0",
                    "tr10b:0:400187:0",
                    "vi1a:0:400188:0",
                    "vi1b:0:400189:0",
                    "vi1c:0:400190:0",
                    "vi1d:0:400191:0",
                    "vi2:0:400192:0",
                    "vi3a:0:400193:0",
                    "vi3b:0:400194:0",
                    "vi4a:0:400195:0",
                    "vi5a:0:400197:0",
                    "vi5b:0:400198:0",
                    "vi5c:0:400199:0",
                    "vi6a:0:400200:0",
                    "vi6b:0:400201:0",
                    "vi7a:0:400202:0",
                    "vi7b:0:400203:0",
                    "vi8a:0:400204:0",
                    "vi8b:0:400205:0",
                    "vi9a:0:400206:0",
                    "vi9b:0:400207:0"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 13,
                    "cumplidos": 5,
                    "porcentaje": 38
                },
                "retenidos": 0
            }
        },
        {
            "nombre": "fase 2 todos los sectores + suelo",
            "luego": "fase 3 sin nada",
            "inicial": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a5:1:100005:0",
                    "a6:1:100006:0",
                    "a7:1:100007:1",
                    "j1:1:400043:0",
                    "j2a:1:400044:0",
                    "j2b:1:400045:1",
                    "j2c:1:400046:0",
                    "j2d:1:400047:0",
                    "j2e:1:400048:1",
                    "j2f:1:400049:0",
                    "j2g:1:400050:0",
                    "j2h:1:400051:1",
                    "j2i:1:400052:0",
                    "j2j:1:400053:0",
                    "j2k:1:400054:1",
                    "j2l:1:400055:0",
                    "j2m:1:400056:0",
                    "j3a:1:400057:1",
                    "j3b:1:400058:0",
                    "j3c:1:400059:0",
                    "j3d:1:400060:1",
                    "ct1a:1:400061:0",
                    "ct1b:1:400062:0",
                    "ct1c:1:400063:1",
                    "ct2a:1:400064:0",
                    "ct2b:1:400065:0",
                    "ct3a:1:400066:1",
                    "ct3b:1:400067:0",
                    "ct4a:1:400068:0",
                    "ct4b:1:400069:1",
                    "ct5a:1:400070:0",
                    "ct5b:1:400071:0",
                    "co1:1:400072:1",
                    "co2a:1:400073:0",
                    "co2b:1:400074:0",
                    "co2c:1:400075:1",
                    "co2d:1:400076:0",
                    "co2e:1:400077:0",
                    "co2f:1:400078:1",
                    "co3:1:400079:0",
                    "co4:1:400080:0",
                    "ag1a:1:400081:1",
                    "ag1b:1:400082:0",
                    "ag1c:1:400083:0",
                    "ag2:1:400084:1",
                    "ag3:1:400085:0",
                    "am1:1:400086:0",
                    "am2:1:400087:1",
                    "cu1:1:400088:0",
                    "cu2a:1:400089:0",
                    "cu2b:1:400090:1",
                    "cu3a:1:400091:0",
                    "cu3b:1:400092:0",
                    "cu3c:1:400093:1",
                    "de1:1:400094:0",
                    "de2:1:400095:0",
                    "de3:1:400096:1",
                    "de4:1:400097:0",
                    "de5:1:400098:0",
                    "de6:1:400099:1",
                    "ed1a:1:400100:0",
                    "ed1b:1:400101:0",
                    "ed1c:1:400102:1",
                    "ed1d:1:400103:0",
                    "ed1e:1:400104:0",
                    "ed2a:1:400105:1",
                    "ed2b:1:400106:0",
                    "ed2c:1:400107:0",
                    "ed3a:1:400108:1",
                    "ed3b:1:400109:0",
                    "ed3c:1:400110:0",
                    "ed4a:1:400111:1",
                    "ed4b:1:400112:0",
                    "ed4c:1:400113:0",
                    "ed5:1:400114:1",
                    "in1a:1:400115:0",
                    "in1b:1:400116:0",
                    "in2a:1:400117:1",
                    "in2b:1:400118:0",
                    "in3:1:400119:0",
                    "in4a:1:400120:1",
                    "in4b:1:400121:0",
                    "in4c:1:400122:0",
                    "mi1a:1:400123:1",
                    "mi1b:1:400124:0",
                    "mi1c:1:400125:0",
                    "mi1d:1:400126:1",
                    "mi2b:1:400128:0",
                    "mi3a:1:400136:0",
                    "mi3b:1:400137:1",
                    "mi3c:1:400138:0",
                    "mi3d:1:400139:0",
                    "mi3e:1:400140:1",
                    "sa1a:1:400141:0",
                    "sa1b:1:400142:0",
                    "sa1c:1:400143:1",
                    "sa1d:1:400144:0",
                    "sa1e:1:400145:0",
                    "sa2a:1:400146:1",
                    "sa2b:1:400147:0",
                    "sa2c:1:400148:0",
                    "sa3:1:400149:1",
                    "sa4a:1:400150:0",
                    "sa4b:1:400151:0",
                    "sa5:1:400152:1",
                    "sa6a:1:400153:0",
                    "sa6b:1:400154:0",
                    "sa6c:1:400155:1",
                    "sa6d:1:400156:0",
                    "ti1a:1:400157:0",
                    "ti1b:1:400158:1",
                    "ti1c:1:400159:0",
                    "ti2a:1:400160:0",
                    "ti2b:1:400161:1",
                    "ti2c:1:400162:0",
                    "ti2d:1:400163:0",
                    "ti3:1:400164:1",
                    "ti4a:1:400165:0",
                    "ti4b:1:400166:0",
                    "ti5a:1:400167:1",
                    "ti5b:1:400168:0",
                    "ti5c:1:400169:0",
                    "tr1a:1:400170:1",
                    "tr1b:1:400171:0",
                    "tr2a:1:400172:0",
                    "tr2b:1:400173:1",
                    "tr2c:1:400174:0",
                    "tr2d:1:400175:0",
                    "tr3:1:400176:1",
                    "tr4a:1:400177:0",
                    "tr4b:1:400178:0",
                    "tr5:1:400179:1",
                    "tr6:1:400180:0",
                    "tr7a:1:400181:0",
                    "tr7b:1:400182:1",
                    "tr8:1:400183:0",
                    "tr9a:1:400184:0",
                    "tr9b:1:400185:1",
                    "tr10a:1:400186:0",
                    "tr10b:1:400187:0",
                    "vi1a:1:400188:1",
                    "vi1b:1:400189:0",
                    "vi1c:1:400190:0",
                    "vi1d:1:400191:1",
                    "vi2:1:400192:0",
                    "vi3a:1:400193:0",
                    "vi3b:1:400194:1",
                    "vi4a:1:400195:0",
                    "vi5a:1:400197:0",
                    "vi5b:1:400198:1",
                    "vi5c:1:400199:0",
                    "vi6a:1:400200:0",
                    "vi6b:1:400201:1",
                    "vi7a:1:400202:0",
                    "vi7b:1:400203:0",
                    "vi8a:1:400204:1",
                    "vi8b:1:400205:0",
                    "vi9a:1:400206:0",
                    "vi9b:1:400207:1"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 163,
                    "cumplidos": 55,
                    "porcentaje": 34
                },
                "retenidos": 0
            },
            "cambiada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a5:0:100005:0",
                    "a6:0:100006:0",
                    "a7:0:100007:1",
                    "a8:1:100008:0",
                    "a9:1:100009:0",
                    "a10:1:100010:0",
                    "a11:1:100011:0",
                    "j1:0:400043:0",
                    "j2a:0:400044:0",
                    "j2b:0:400045:1",
                    "j2c:0:400046:0",
                    "j2d:0:400047:0",
                    "j2e:0:400048:1",
                    "j2f:0:400049:0",
                    "j2g:0:400050:0",
                    "j2h:0:400051:1",
                    "j2i:0:400052:0",
                    "j2j:0:400053:0",
                    "j2k:0:400054:1",
                    "j2l:0:400055:0",
                    "j2m:0:400056:0",
                    "j3a:0:400057:1",
                    "j3b:0:400058:0",
                    "j3c:0:400059:0",
                    "j3d:0:400060:1",
                    "ct1a:0:400061:0",
                    "ct1b:0:400062:0",
                    "ct1c:0:400063:1",
                    "ct2a:0:400064:0",
                    "ct2b:0:400065:0",
                    "ct3a:0:400066:1",
                    "ct3b:0:400067:0",
                    "ct4a:0:400068:0",
                    "ct4b:0:400069:1",
                    "ct5a:0:400070:0",
                    "ct5b:0:400071:0",
                    "co1:0:400072:1",
                    "co2a:0:400073:0",
                    "co2b:0:400074:0",
                    "co2c:0:400075:1",
                    "co2d:0:400076:0",
                    "co2e:0:400077:0",
                    "co2f:0:400078:1",
                    "co3:0:400079:0",
                    "co4:0:400080:0",
                    "ag1a:0:400081:1",
                    "ag1b:0:400082:0",
                    "ag1c:0:400083:0",
                    "ag2:0:400084:1",
                    "ag3:0:400085:0",
                    "am1:0:400086:0",
                    "am2:0:400087:1",
                    "cu1:0:400088:0",
                    "cu2a:0:400089:0",
                    "cu2b:0:400090:1",
                    "cu3a:0:400091:0",
                    "cu3b:0:400092:0",
                    "cu3c:0:400093:1",
                    "de1:0:400094:0",
                    "de2:0:400095:0",
                    "de3:0:400096:1",
                    "de4:0:400097:0",
                    "de5:0:400098:0",
                    "de6:0:400099:1",
                    "ed1a:0:400100:0",
                    "ed1b:0:400101:0",
                    "ed1c:0:400102:1",
                    "ed1d:0:400103:0",
                    "ed1e:0:400104:0",
                    "ed2a:0:400105:1",
                    "ed2b:0:400106:0",
                    "ed2c:0:400107:0",
                    "ed3a:0:400108:1",
                    "ed3b:0:400109:0",
                    "ed3c:0:400110:0",
                    "ed4a:0:400111:1",
                    "ed4b:0:400112:0",
                    "ed4c:0:400113:0",
                    "ed5:0:400114:1",
                    "in1a:0:400115:0",
                    "in1b:0:400116:0",
                    "in2a:0:400117:1",
                    "in2b:0:400118:0",
                    "in3:0:400119:0",
                    "in4a:0:400120:1",
                    "in4b:0:400121:0",
                    "in4c:0:400122:0",
                    "mi1a:0:400123:1",
                    "mi1b:0:400124:0",
                    "mi1c:0:400125:0",
                    "mi1d:0:400126:1",
                    "mi2b:0:400128:0",
                    "mi3a:0:400136:0",
                    "mi3b:0:400137:1",
                    "mi3c:0:400138:0",
                    "mi3d:0:400139:0",
                    "mi3e:0:400140:1",
                    "sa1a:0:400141:0",
                    "sa1b:0:400142:0",
                    "sa1c:0:400143:1",
                    "sa1d:0:400144:0",
                    "sa1e:0:400145:0",
                    "sa2a:0:400146:1",
                    "sa2b:0:400147:0",
                    "sa2c:0:400148:0",
                    "sa3:0:400149:1",
                    "sa4a:0:400150:0",
                    "sa4b:0:400151:0",
                    "sa5:0:400152:1",
                    "sa6a:0:400153:0",
                    "sa6b:0:400154:0",
                    "sa6c:0:400155:1",
                    "sa6d:0:400156:0",
                    "ti1a:0:400157:0",
                    "ti1b:0:400158:1",
                    "ti1c:0:400159:0",
                    "ti2a:0:400160:0",
                    "ti2b:0:400161:1",
                    "ti2c:0:400162:0",
                    "ti2d:0:400163:0",
                    "ti3:0:400164:1",
                    "ti4a:0:400165:0",
                    "ti4b:0:400166:0",
                    "ti5a:0:400167:1",
                    "ti5b:0:400168:0",
                    "ti5c:0:400169:0",
                    "tr1a:0:400170:1",
                    "tr1b:0:400171:0",
                    "tr2a:0:400172:0",
                    "tr2b:0:400173:1",
                    "tr2c:0:400174:0",
                    "tr2d:0:400175:0",
                    "tr3:0:400176:1",
                    "tr4a:0:400177:0",
                    "tr4b:0:400178:0",
                    "tr5:0:400179:1",
                    "tr6:0:400180:0",
                    "tr7a:0:400181:0",
                    "tr7b:0:400182:1",
                    "tr8:0:400183:0",
                    "tr9a:0:400184:0",
                    "tr9b:0:400185:1",
                    "tr10a:0:400186:0",
                    "tr10b:0:400187:0",
                    "vi1a:0:400188:1",
                    "vi1b:0:400189:0",
                    "vi1c:0:400190:0",
                    "vi1d:0:400191:1",
                    "vi2:0:400192:0",
                    "vi3a:0:400193:0",
                    "vi3b:0:400194:1",
                    "vi4a:0:400195:0",
                    "vi5a:0:400197:0",
                    "vi5b:0:400198:1",
                    "vi5c:0:400199:0",
                    "vi6a:0:400200:0",
                    "vi6b:0:400201:1",
                    "vi7a:0:400202:0",
                    "vi7b:0:400203:0",
                    "vi8a:0:400204:1",
                    "vi8b:0:400205:0",
                    "vi9a:0:400206:0",
                    "vi9b:0:400207:1"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 8,
                    "cumplidos": 2,
                    "porcentaje": 25
                },
                "retenidos": 53
            },
            "restaurada": {
                "filas": [
                    "a1:1:100001:1",
                    "a2:1:100002:0",
                    "a3:1:100003:0",
                    "a4:1:100004:1",
                    "a5:1:100005:0",
                    "a6:1:100006:0",
                    "a7:1:100007:1",
                    "a8:0:100008:0",
                    "a9:0:100009:0",
                    "a10:0:100010:0",
                    "a11:0:100011:0",
                    "j1:1:400043:0",
                    "j2a:1:400044:0",
                    "j2b:1:400045:1",
                    "j2c:1:400046:0",
                    "j2d:1:400047:0",
                    "j2e:1:400048:1",
                    "j2f:1:400049:0",
                    "j2g:1:400050:0",
                    "j2h:1:400051:1",
                    "j2i:1:400052:0",
                    "j2j:1:400053:0",
                    "j2k:1:400054:1",
                    "j2l:1:400055:0",
                    "j2m:1:400056:0",
                    "j3a:1:400057:1",
                    "j3b:1:400058:0",
                    "j3c:1:400059:0",
                    "j3d:1:400060:1",
                    "ct1a:1:400061:0",
                    "ct1b:1:400062:0",
                    "ct1c:1:400063:1",
                    "ct2a:1:400064:0",
                    "ct2b:1:400065:0",
                    "ct3a:1:400066:1",
                    "ct3b:1:400067:0",
                    "ct4a:1:400068:0",
                    "ct4b:1:400069:1",
                    "ct5a:1:400070:0",
                    "ct5b:1:400071:0",
                    "co1:1:400072:1",
                    "co2a:1:400073:0",
                    "co2b:1:400074:0",
                    "co2c:1:400075:1",
                    "co2d:1:400076:0",
                    "co2e:1:400077:0",
                    "co2f:1:400078:1",
                    "co3:1:400079:0",
                    "co4:1:400080:0",
                    "ag1a:1:400081:1",
                    "ag1b:1:400082:0",
                    "ag1c:1:400083:0",
                    "ag2:1:400084:1",
                    "ag3:1:400085:0",
                    "am1:1:400086:0",
                    "am2:1:400087:1",
                    "cu1:1:400088:0",
                    "cu2a:1:400089:0",
                    "cu2b:1:400090:1",
                    "cu3a:1:400091:0",
                    "cu3b:1:400092:0",
                    "cu3c:1:400093:1",
                    "de1:1:400094:0",
                    "de2:1:400095:0",
                    "de3:1:400096:1",
                    "de4:1:400097:0",
                    "de5:1:400098:0",
                    "de6:1:400099:1",
                    "ed1a:1:400100:0",
                    "ed1b:1:400101:0",
                    "ed1c:1:400102:1",
                    "ed1d:1:400103:0",
                    "ed1e:1:400104:0",
                    "ed2a:1:400105:1",
                    "ed2b:1:400106:0",
                    "ed2c:1:400107:0",
                    "ed3a:1:400108:1",
                    "ed3b:1:400109:0",
                    "ed3c:1:400110:0",
                    "ed4a:1:400111:1",
                    "ed4b:1:400112:0",
                    "ed4c:1:400113:0",
                    "ed5:1:400114:1",
                    "in1a:1:400115:0",
                    "in1b:1:400116:0",
                    "in2a:1:400117:1",
                    "in2b:1:400118:0",
                    "in3:1:400119:0",
                    "in4a:1:400120:1",
                    "in4b:1:400121:0",
                    "in4c:1:400122:0",
                    "mi1a:1:400123:1",
                    "mi1b:1:400124:0",
                    "mi1c:1:400125:0",
                    "mi1d:1:400126:1",
                    "mi2b:1:400128:0",
                    "mi3a:1:400136:0",
                    "mi3b:1:400137:1",
                    "mi3c:1:400138:0",
                    "mi3d:1:400139:0",
                    "mi3e:1:400140:1",
                    "sa1a:1:400141:0",
                    "sa1b:1:400142:0",
                    "sa1c:1:400143:1",
                    "sa1d:1:400144:0",
                    "sa1e:1:400145:0",
                    "sa2a:1:400146:1",
                    "sa2b:1:400147:0",
                    "sa2c:1:400148:0",
                    "sa3:1:400149:1",
                    "sa4a:1:400150:0",
                    "sa4b:1:400151:0",
                    "sa5:1:400152:1",
                    "sa6a:1:400153:0",
                    "sa6b:1:400154:0",
                    "sa6c:1:400155:1",
                    "sa6d:1:400156:0",
                    "ti1a:1:400157:0",
                    "ti1b:1:400158:1",
                    "ti1c:1:400159:0",
                    "ti2a:1:400160:0",
                    "ti2b:1:400161:1",
                    "ti2c:1:400162:0",
                    "ti2d:1:400163:0",
                    "ti3:1:400164:1",
                    "ti4a:1:400165:0",
                    "ti4b:1:400166:0",
                    "ti5a:1:400167:1",
                    "ti5b:1:400168:0",
                    "ti5c:1:400169:0",
                    "tr1a:1:400170:1",
                    "tr1b:1:400171:0",
                    "tr2a:1:400172:0",
                    "tr2b:1:400173:1",
                    "tr2c:1:400174:0",
                    "tr2d:1:400175:0",
                    "tr3:1:400176:1",
                    "tr4a:1:400177:0",
                    "tr4b:1:400178:0",
                    "tr5:1:400179:1",
                    "tr6:1:400180:0",
                    "tr7a:1:400181:0",
                    "tr7b:1:400182:1",
                    "tr8:1:400183:0",
                    "tr9a:1:400184:0",
                    "tr9b:1:400185:1",
                    "tr10a:1:400186:0",
                    "tr10b:1:400187:0",
                    "vi1a:1:400188:1",
                    "vi1b:1:400189:0",
                    "vi1c:1:400190:0",
                    "vi1d:1:400191:1",
                    "vi2:1:400192:0",
                    "vi3a:1:400193:0",
                    "vi3b:1:400194:1",
                    "vi4a:1:400195:0",
                    "vi5a:1:400197:0",
                    "vi5b:1:400198:1",
                    "vi5c:1:400199:0",
                    "vi6a:1:400200:0",
                    "vi6b:1:400201:1",
                    "vi7a:1:400202:0",
                    "vi7b:1:400203:0",
                    "vi8a:1:400204:1",
                    "vi8b:1:400205:0",
                    "vi9a:1:400206:0",
                    "vi9b:1:400207:1"
                ],
                "caracteres": [
                    "obligatorio"
                ],
                "estados": [
                    "aplica"
                ],
                "progreso": {
                    "total": 163,
                    "cumplidos": 55,
                    "porcentaje": 34
                },
                "retenidos": 0
            }
        }
    ]
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Viabilizacion/ComposicionExpedienteTest.php`
Expected: FALLAN las 9. La de paridad falla porque todavía no hay orígenes, y las demás porque `sincronizar` solo lee `catalogo_version_id`.

- [ ] **Paso 3: Implementar**

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 3):

```php

    private const ORDEN_SECCION = ['A' => 1, 'B' => 2, 'D' => 3, 'C' => 4];

    private const RELACIONES_FILA = [
        'requisito.sector:id,clave,nombre,fuente', 'requisito.tipo:id,clave,nombre', 'usuarioCumplido:id,name',
        'usuarioNoAplica:id,name', 'usuarioAprobo:id,name', 'responsable:id,nombre,cargo',
```

por:

```php

    private const ORDEN_SECCION = ['A' => 1, 'B' => 2, 'D' => 3, 'C' => 4];

    // Orden de una fila: posición de la plantilla del primer origen, sección (motor sgr) y orden
    // del requisito. La principal tiene posición 0: un expediente solo SGR conserva su orden.
    private const ORDEN_POR_PLANTILLA = 10_000_000;
    private const ORDEN_POR_SECCION = 100_000;

    /** Del más al menos exigente: una fila con varios orígenes toma el primero que aparezca. */
    private const CARACTER_POR_EXIGENCIA = [RequisitoCatalogo::OBLIGATORIO, RequisitoCatalogo::CONDICIONADO, RequisitoCatalogo::OPCIONAL];

    private const RELACIONES_FILA = [
        'requisito.sector:id,clave,nombre,fuente', 'requisito.tipo:id,clave,nombre', 'usuarioCumplido:id,name',
        'usuarioNoAplica:id,name', 'usuarioAprobo:id,name', 'responsable:id,nombre,cargo',
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 3):

```php
    public function __construct(
        private readonly EvaluadorRequisitos $evaluador,
        private readonly HistorialChecklist $historial,
    ) {
    }
```

por:

```php
    public function __construct(
        private readonly EvaluadorRequisitos $evaluador,
        private readonly HistorialChecklist $historial,
        private readonly PlantillasExpediente $plantillas,
    ) {
    }
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (3 de 3):

```php
    // ---------------------------------------------------------- recálculo

    /**
     * Alinea el checklist con la configuración actual del expediente.
     *
     * @return array{aplican:int, retenidos:int}
     */
    public function sincronizar(ProyectoExpediente $expediente): array
    {
        $cfg = $this->configuracionDe($expediente);

        $catalogo = RequisitoCatalogo::query()
            ->where('catalogo_version_id', $expediente->catalogo_version_id)
            ->where('activo', true)
            ->with(['sector:id,clave', 'tipo:id,clave'])
            ->orderBy('orden')
            ->get();

        $aplican = array_flip($this->evaluador->aplicables(
            $catalogo->map(fn (RequisitoCatalogo $r) => [
                'clave' => $r->clave,
                'seccion' => $r->seccion,
                'condicion' => $r->condicion,
                'sector' => $r->sector?->clave,
                'tipo' => $r->tipo?->clave,
            ]),
            $cfg
        ));

        $existentes = $expediente->requisitos()->get()->keyBy('requisito_catalogo_id');
        $vistos = [];

        foreach ($catalogo as $requisito) {
            $vistos[$requisito->id] = true;
            $aplica = isset($aplican[$requisito->clave]);
            $orden = self::ORDEN_SECCION[$requisito->seccion] * 100000 + $requisito->orden;
            $fila = $existentes->get($requisito->id);

            if ($fila) {
                if ($fila->aplica !== $aplica || $fila->orden !== $orden) {
                    $fila->update(['aplica' => $aplica, 'orden' => $orden]);
                }
            } elseif ($aplica) {
                $expediente->requisitos()->create([
                    'requisito_catalogo_id' => $requisito->id,
                    'aplica' => true,
                    'caracter' => $requisito->caracter,
                    'orden' => $orden,
                ]);
            }
        }

        // Filas de requisitos desactivados en el catálogo: dejan de aplicar.
        $expediente->requisitos()
            ->whereNotIn('requisito_catalogo_id', array_keys($vistos))
            ->where('aplica', true)
            ->update(['aplica' => false]);

        return [
            'aplican' => count($aplican),
            'retenidos' => $expediente->requisitos()->where('aplica', false)->count(),
        ];
    }

    public function configuracionDe(ProyectoExpediente $expediente): ConfiguracionExpediente
    {
        $expediente->loadMissing(['sectores:id,clave', 'tipos.sector:id,clave', 'componentes:id,clave', 'tipologias:id,clave']);
```

por:

```php
    // ---------------------------------------------------------- recálculo

    /**
     * Alinea el checklist con las plantillas y la configuración actuales del expediente.
     *
     * - Recorre las versiones del expediente, la principal primero y luego por plantilla.
     *   Motor sgr: aplican los que decide el evaluador. Motor simple: todos los activos.
     * - Cada llave (el código común del requisito, o req:{id} si no tiene) es una sola fila.
     *   Sus orígenes son los requisitos que aplican con esa llave, de todas las plantillas.
     * - La fila la crea el primer origen (su ancla, que no cambia) y se reconoce por la llave
     *   de su ancla. Sin orígenes queda aplica = false y conserva su trabajo; al volver a
     *   tenerlos se recupera tal cual.
     * - Carácter: el más exigente de los orígenes. Una fila nueva condicionada nace por definir;
     *   una existente no cambia de estado por recalcular.
     * - Orden: el del primer origen, desplazado por la posición de su plantilla.
     * - Los requisitos propios no se tocan.
     *
     * @return array{aplican:int, retenidos:int}
     */
    public function sincronizar(ProyectoExpediente $expediente): array
    {
        $llaves = $this->origenesPorLlave($expediente);

        $existentes = [];
        $sinLlave = [];
        $filas = $expediente->requisitos()->whereNotNull('requisito_catalogo_id')
            ->with('requisito:id,codigo_comun')->orderBy('id')->get();
        foreach ($filas as $fila) {
            $llave = $this->llave($fila->requisito);
            // Dos filas con la misma llave solo pueden venir de datos anteriores a esta regla:
            // la primera conserva la llave y las demás quedan sin orígenes.
            if (isset($existentes[$llave])) {
                $sinLlave[] = $fila;
            } else {
                $existentes[$llave] = $fila;
            }
        }

        $deseados = [];
        foreach ($llaves as $llave => $datos) {
            $cambios = ['aplica' => true, 'caracter' => $datos['caracter'], 'orden' => $datos['orden']];
            $fila = $existentes[$llave] ?? null;
            unset($existentes[$llave]);

            if ($fila) {
                $fila->fill($cambios);
                if ($fila->isDirty()) {
                    $fila->save();
                }
            } else {
                $ancla = $datos['origenes'][0];
                $fila = $expediente->requisitos()->create([
                    ...$cambios,
                    'requisito_catalogo_id' => $ancla->id,
                    'estado_aplicabilidad' => $datos['caracter'] === RequisitoCatalogo::CONDICIONADO
                        ? ProyectoRequisito::POR_DEFINIR
                        : ProyectoRequisito::APLICA,
                ]);
            }
            $deseados[$fila->id] = array_map(fn (RequisitoCatalogo $r) => $r->id, $datos['origenes']);
        }

        // Sin orígenes: la fila deja de aplicar y conserva cumplimiento, observación y soporte.
        foreach ([...array_values($existentes), ...$sinLlave] as $fila) {
            if ($fila->aplica) {
                $fila->update(['aplica' => false]);
            }
        }

        $this->guardarOrigenes($expediente, $deseados);

        return [
            'aplican' => count($llaves),
            'retenidos' => $expediente->requisitos()->where('aplica', false)->count(),
        ];
    }

    /**
     * Requisitos que aplican en cada plantilla del expediente, agrupados por llave en el
     * orden del checklist. Cada llave trae sus orígenes (el primero es el ancla de una fila
     * nueva), el carácter más exigente y el orden que da el primer origen.
     *
     * @return array<string, array{origenes: list<RequisitoCatalogo>, caracter: string, orden: int}>
     */
    private function origenesPorLlave(ProyectoExpediente $expediente): array
    {
        $llaves = [];
        $cfg = null;

        foreach ($this->plantillas->versionesDe($expediente)->values() as $posicion => $version) {
            $esSgr = $version->plantilla->motor === PlantillaChecklist::MOTOR_SGR;
            $requisitos = RequisitoCatalogo::query()
                ->where('catalogo_version_id', $version->id)
                ->where('activo', true)
                ->when($esSgr, fn ($q) => $q->with(['sector:id,clave', 'tipo:id,clave']))
                ->orderBy('orden')
                ->orderBy('id')
                ->get();

            if ($esSgr) {
                $cfg ??= $this->configuracionDe($expediente);
                $aplican = array_flip($this->evaluador->aplicables(
                    $requisitos->map(fn (RequisitoCatalogo $r) => [
                        'clave' => $r->clave,
                        'seccion' => $r->seccion,
                        'condicion' => $r->condicion,
                        'sector' => $r->sector?->clave,
                        'tipo' => $r->tipo?->clave,
                    ]),
                    $cfg
                ));
                $requisitos = $requisitos->filter(fn (RequisitoCatalogo $r) => isset($aplican[$r->clave]));
            }

            foreach ($requisitos as $requisito) {
                $llave = $this->llave($requisito);
                if (! isset($llaves[$llave])) {
                    $llaves[$llave] = [
                        'origenes' => [],
                        'caracter' => $requisito->caracter,
                        'orden' => $posicion * self::ORDEN_POR_PLANTILLA
                            + ($esSgr ? self::ORDEN_SECCION[$requisito->seccion] * self::ORDEN_POR_SECCION : 0)
                            + $requisito->orden,
                    ];
                }
                $llaves[$llave]['origenes'][] = $requisito;
                $llaves[$llave]['caracter'] = $this->masExigente($llaves[$llave]['caracter'], $requisito->caracter);
            }
        }

        return $llaves;
    }

    /** Llave de fila: el código común; sin él, el propio requisito. */
    private function llave(RequisitoCatalogo $requisito): string
    {
        return $requisito->codigo_comun ?? "req:{$requisito->id}";
    }

    private function masExigente(string $a, string $b): string
    {
        $rango = fn (string $c) => array_search($c, self::CARACTER_POR_EXIGENCIA, true);

        return $rango($b) < $rango($a) ? $b : $a;
    }

    /**
     * Deja en proyecto_requisito_origenes exactamente los orígenes calculados: las filas que
     * no están en $deseados se quedan sin ninguno. Los propios nunca tienen orígenes.
     *
     * @param array<int, list<int>> $deseados fila => requisitos de catálogo
     */
    private function guardarOrigenes(ProyectoExpediente $expediente, array $deseados): void
    {
        $actuales = DB::table('proyecto_requisito_origenes')
            ->whereIn('proyecto_requisito_id', $expediente->requisitos()->select('id'))
            ->get()
            ->groupBy('proyecto_requisito_id')
            ->map(fn ($origenes) => $origenes->pluck('requisito_catalogo_id')->map(fn ($id) => (int) $id)->all());

        $nuevos = [];
        foreach ($deseados as $fila => $requisitos) {
            foreach (array_diff($requisitos, $actuales->get($fila, [])) as $requisito) {
                $nuevos[] = ['proyecto_requisito_id' => $fila, 'requisito_catalogo_id' => $requisito];
            }
        }
        foreach ($actuales as $fila => $requisitos) {
            $sobran = array_diff($requisitos, $deseados[$fila] ?? []);
            if ($sobran) {
                DB::table('proyecto_requisito_origenes')
                    ->where('proyecto_requisito_id', $fila)
                    ->whereIn('requisito_catalogo_id', $sobran)
                    ->delete();
            }
        }
        foreach (array_chunk($nuevos, 200) as $lote) {
            DB::table('proyecto_requisito_origenes')->insert($lote);
        }
    }

    public function configuracionDe(ProyectoExpediente $expediente): ConfiguracionExpediente
    {
        $expediente->loadMissing(['sectores:id,clave', 'tipos.sector:id,clave', 'componentes:id,clave', 'tipologias:id,clave']);
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Viabilizacion/ComposicionExpedienteTest.php`
Expected: pasan las 9.

Run: `php artisan test`
Expected: pasan todas, 464.

- [ ] **Paso 5: Commit**

```bash
git add tests/Feature/Viabilizacion/ComposicionExpedienteTest.php tests/Fixtures/paridad_expediente_sgr.json app/Services/Viabilizacion/ExpedienteService.php
git commit -m "feat: compose every template of the expediente when recalculating its checklist"
```

---

### Tarea 5: Crear, configurar y presentar con plantillas

Cambia a propósito varias pruebas existentes. Sus proyectos no tenían fuente y aun así recibían la SGR. Ahora la SGR solo se preselecciona si el proyecto la admite, así que esas pruebas le dan la fuente SGR al proyecto (`proyectoSgr()`), que es el caso real, y siguen comprobando lo mismo. En `ExpedienteAutomaticoTest`, el fallo de creación se provoca con un sector SGR inactivo, porque sin versión vigente el expediente ahora nace sin plantillas.

**Files:**
- Test (create): `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`
- Modify: `app/Http/Controllers/ExpedienteController.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `app/Services/Viabilizacion/PlantillasExpediente.php`
- Test: `tests/Feature/Viabilizacion/CatalogoRequisitosApiTest.php`
- Test: `tests/Feature/Viabilizacion/ExpedienteApiTest.php`
- Test: `tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`
- Test: `tests/Feature/Viabilizacion/HistorialRequisitoApiTest.php`
- Test: `tests/Feature/Viabilizacion/NoAplicaRequisitoApiTest.php`
- Test: `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`
- Test: `tests/Feature/Viabilizacion/SeguimientoRequisitoApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`:

```php
<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Crear, configurar y presentar un expediente con sus plantillas. */
class ExpedientePlantillasApiTest extends TestCase
{
    use RefreshDatabase;

    private PlantillaChecklist $sgr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->sgr = PlantillaChecklist::sgr();
    }

    private function proyecto(array $fuentes = ['sgr']): Proyecto
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
        $proyecto->fuentes()->sync(FuenteFinanciacion::whereIn('clave', $fuentes)->pluck('id')->all());

        return $proyecto;
    }

    /** @param array<string, array{0: ?string, 1?: string, 2?: ?string}> $requisitos texto => [codigo_comun, caracter, categoria] */
    private function plantilla(string $nombre, array $requisitos, array $datos = []): PlantillaChecklist
    {
        $plantilla = PlantillaChecklist::create([
            'nombre' => $nombre, 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'activo' => true,
            'todas_fuentes' => true, 'todos_sectores' => true, ...$datos,
        ]);
        $version = $plantilla->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        $orden = 0;
        foreach ($requisitos as $texto => $r) {
            $orden++;
            RequisitoCatalogo::create([
                'catalogo_version_id' => $version->id, 'clave' => "r{$orden}", 'texto' => $texto, 'orden' => $orden,
                'codigo_comun' => $r[0], 'caracter' => $r[1] ?? RequisitoCatalogo::OBLIGATORIO, 'categoria' => $r[2] ?? null,
            ]);
        }

        return $plantilla;
    }

    private function como(Rol $rol = Rol::Gestor): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function crear(Proyecto $proyecto, array $datos)
    {
        return $this->como()->postJson("/GestPro/proyectos/{$proyecto->id}/expedientes", $datos);
    }

    private function configurar(int $expediente, array $datos, bool $confirmar = false)
    {
        return $confirmar
            ? $this->como()->postJson("/GestPro/expedientes/{$expediente}/configuracion/confirmar", $datos)
            : $this->como()->putJson("/GestPro/expedientes/{$expediente}/configuracion", $datos);
    }

    private function sgrA1(): RequisitoCatalogo
    {
        return RequisitoCatalogo::where('catalogo_version_id', $this->sgr->versionVigente()->id)->where('clave', 'a1')->sole();
    }

    private function filaPorTexto($respuesta, string $texto): ?array
    {
        return collect($respuesta->json('requisitos'))->firstWhere('requisito.texto', $texto);
    }

    // --------------------------------------------------------------- creación

    public function test_sin_plantillas_en_el_pedido_preselecciona_la_sgr_compatible(): void
    {
        $r = $this->crear($this->proyecto(), ['fase_sgr' => 3])->assertCreated();

        $version = $this->sgr->versionVigente();
        $r->assertJsonPath('plantillas', [[
            'plantilla_id' => $this->sgr->id, 'nombre' => 'SGR', 'motor' => 'sgr', 'es_principal' => true,
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
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null], 'Licencia' => [null]]);

        $r = $this->crear($this->proyecto(['recursos_propios']), ['plantilla_ids' => [$agua->id]])->assertCreated();

        $r->assertJsonPath('plantillas.0.plantilla_id', $agua->id)
            ->assertJsonPath('plantillas.0.es_principal', true)
            ->assertJsonPath('tiene_sgr', false)
            ->assertJsonPath('catalogo_version', null)
            ->assertJsonPath('progreso.total', 2);
        $this->assertSame(['Planos firmados', 'Licencia'], collect($r->json('requisitos'))->pluck('requisito.texto')->all());
    }

    public function test_con_la_sgr_la_fase_es_obligatoria(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $proyecto = $this->proyecto();

        $this->crear($proyecto, ['plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])
            ->assertStatus(422)->assertJsonValidationErrors('fase_sgr');
        $this->crear($proyecto, ['plantilla_ids' => [$agua->id]])->assertCreated();
        $this->assertDatabaseCount('proyectos_expedientes', 1);
    }

    public function test_combina_la_sgr_con_otra_plantilla(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);

        $r = $this->crear($this->proyecto(), ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])
            ->assertCreated();

        $r->assertJsonPath('progreso.total', 9)
            ->assertJsonPath('plantillas.0.plantilla_id', $this->sgr->id)
            ->assertJsonPath('plantillas.1.plantilla_id', $agua->id)
            ->assertJsonPath('plantillas.1.es_principal', false);
        $this->assertSame('Planos firmados', collect($r->json('requisitos'))->last()['requisito']['texto']);
    }

    public function test_rechaza_una_seleccion_invalida_sin_crear_nada(): void
    {
        $nacion = $this->plantilla('Nación', ['Planos' => [null]], ['todas_fuentes' => false]);
        $nacion->fuentes()->sync(FuenteFinanciacion::where('clave', 'pgn')->pluck('id')->all());

        $this->crear($this->proyecto(), ['fase_sgr' => 3, 'plantilla_ids' => [$nacion->id]])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'La configuración del expediente no es válida.')
            ->assertJsonPath('errores', ['La plantilla "Nación" no es compatible con las fuentes o el sector del proyecto.']);
        $this->crear($this->proyecto(), ['plantilla_ids' => 'sgr'])->assertStatus(422)->assertJsonValidationErrors('plantilla_ids');
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    // --------------------------------------------------------- configuración

    public function test_configurar_sin_plantillas_en_el_pedido_conserva_la_seleccion(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $id = $this->crear($this->proyecto(), ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])->json('id');

        $this->configurar($id, ['fase_sgr' => 2])->assertOk()
            ->assertJsonPath('fase_sgr', 2)
            ->assertJsonCount(2, 'plantillas');
    }

    public function test_configurar_con_plantillas_cambia_la_seleccion_y_recalcula(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $id = $this->crear($this->proyecto(), ['fase_sgr' => 3])->json('id');

        $con = $this->configurar($id, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $agua->id])->assertOk();
        $con->assertJsonPath('plantillas.0.plantilla_id', $agua->id)->assertJsonPath('progreso.total', 9);
        $this->assertSame('Planos firmados', $con->json('requisitos.0.requisito.texto'));

        // Sin la SGR no hace falta la fase, y sus filas quedan retiradas.
        $sin = $this->configurar($id, ['plantilla_ids' => [$agua->id]])->assertOk();
        $sin->assertJsonPath('tiene_sgr', false)->assertJsonPath('catalogo_version', null)
            ->assertJsonPath('progreso.total', 1)->assertJsonPath('fase_sgr', 3);
        $this->assertSame(8, ProyectoExpediente::find($id)->requisitos()->where('aplica', false)->count());

        $this->configurar($id, ['plantilla_ids' => [$this->sgr->id]])->assertStatus(422)->assertJsonValidationErrors('fase_sgr');
    }

    public function test_confirmar_exige_al_menos_una_plantilla(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $id = $this->crear($this->proyecto(['recursos_propios']), [])->assertCreated()->json('id');
        $mensaje = 'Elige al menos una plantilla para armar el checklist.';

        $this->configurar($id, [], true)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $this->configurar($id, ['plantilla_ids' => []], true)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $this->assertNull(ProyectoExpediente::find($id)->fecha_configuracion);

        $this->configurar($id, ['plantilla_ids' => [$agua->id]], true)->assertOk()->assertJsonPath('configurado', true);
    }

    public function test_un_expediente_confirmado_no_se_queda_sin_plantillas_y_uno_sin_confirmar_si(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $id = $this->crear($this->proyecto(['recursos_propios']), ['plantilla_ids' => [$agua->id]])->assertCreated()->json('id');

        // Mientras se arma, puede quedarse sin plantillas.
        $this->configurar($id, ['plantilla_ids' => []])->assertOk()->assertJsonPath('plantillas', []);
        $this->configurar($id, ['plantilla_ids' => [$agua->id]], true)->assertOk();

        $this->configurar($id, ['plantilla_ids' => []])->assertStatus(422)
            ->assertJsonPath('mensaje', 'Elige al menos una plantilla para armar el checklist.');
        $this->assertSame(1, ProyectoExpediente::find($id)->versionesPlantilla()->count());
        $this->assertSame(1, ProyectoExpediente::find($id)->requisitos()->where('aplica', true)->count());

        // Sin plantilla_ids, la selección no cambia y el cambio se acepta.
        $this->configurar($id, [])->assertOk()->assertJsonCount(1, 'plantillas');
    }

    public function test_un_cambio_de_plantillas_queda_en_el_historial(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $id = $this->crear($this->proyecto(), ['fase_sgr' => 3])->json('id');
        $this->configurar($id, ['fase_sgr' => 3], true)->assertOk();

        $this->configurar($id, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])->assertOk();

        $registro = DB::table('checklist_historial')->where('evento', 'configuracion_cambiada')->sole();
        $this->assertCount(1, json_decode($registro->antes, true)['plantillas']);
        $this->assertCount(2, json_decode($registro->despues, true)['plantillas']);
    }

    // ------------------------------------------------------------ presentación

    public function test_una_fila_compartida_muestra_el_requisito_de_la_principal_y_sus_origenes(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Presupuesto detallado de agua' => ['a1', 'obligatorio', 'financiero']]);
        $proyecto = $this->proyecto();
        $id = $this->crear($proyecto, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])->json('id');
        $a1 = $this->sgrA1();

        $r = $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$id}")->assertOk();

        $fila = collect($r->json('requisitos'))->firstWhere('requisito.id', $a1->id);
        $this->assertSame(['id' => $this->sgr->id, 'nombre' => 'SGR', 'motor' => 'sgr'], $fila['plantilla']);
        $this->assertSame([['plantilla_id' => $this->sgr->id, 'nombre' => 'SGR'], ['plantilla_id' => $agua->id, 'nombre' => 'Obras de agua']], $fila['origenes']);
        $this->assertNull($fila['propio']);
        $this->assertNull($fila['categoria']);
        $this->assertSame(8, $r->json('progreso.total'));

        // Con la otra como principal, se muestra el requisito de esa plantilla.
        $otra = $this->configurar($id, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $agua->id]);
        $fila = $this->filaPorTexto($otra, 'Presupuesto detallado de agua');
        $this->assertSame(['id' => $agua->id, 'nombre' => 'Obras de agua', 'motor' => 'simple'], $fila['plantilla']);
        $this->assertSame('financiero', $fila['categoria']);
        $this->assertSame([['plantilla_id' => $agua->id, 'nombre' => 'Obras de agua'], ['plantilla_id' => $this->sgr->id, 'nombre' => 'SGR']], $fila['origenes']);
        $this->assertNull($this->filaPorTexto($otra, $a1->texto));
    }

    public function test_una_fila_sin_origenes_muestra_su_ancla(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $id = $this->crear($this->proyecto(), ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])->json('id');
        $this->configurar($id, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id]])->assertOk();
        $retirada = ProyectoExpediente::find($id)->requisitos()->where('aplica', false)->sole();

        $fila = app(ExpedienteService::class)->presentarFila($retirada);

        $this->assertSame('Planos firmados', $fila['requisito']['texto']);
        $this->assertSame(['id' => $agua->id, 'nombre' => 'Obras de agua', 'motor' => 'simple'], $fila['plantilla']);
        $this->assertSame([], $fila['origenes']);
    }

    public function test_una_fila_trae_la_descripcion_la_condicion_y_el_soporte_del_requisito(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, RequisitoCatalogo::CONDICIONADO]]);
        RequisitoCatalogo::where('texto', 'Permiso de vertimientos')->update([
            'descripcion' => 'Lo expide la autoridad ambiental.',
            'condicion_descripcion' => 'Si la obra descarga aguas residuales.',
            'soporte_requerido' => 'Resolución del permiso',
        ]);

        $r = $this->crear($this->proyecto(), ['plantilla_ids' => [$agua->id]])->assertCreated();

        $fila = $this->filaPorTexto($r, 'Permiso de vertimientos');
        $this->assertSame('por_definir', $fila['estado']);
        $this->assertSame('Lo expide la autoridad ambiental.', $fila['requisito']['descripcion']);
        $this->assertSame('Si la obra descarga aguas residuales.', $fila['requisito']['condicion_descripcion']);
        $this->assertSame('Resolución del permiso', $fila['requisito']['soporte_requerido']);

        // Sin esos datos llegan como null, también en la plantilla SGR.
        $a1 = collect($this->crear($this->proyecto(), ['fase_sgr' => 3])->json('requisitos'))->firstWhere('requisito.id', $this->sgrA1()->id);
        $this->assertArrayHasKey('condicion_descripcion', $a1['requisito']);
        $this->assertNull($a1['requisito']['condicion_descripcion']);
    }

    public function test_un_requisito_propio_se_presenta_con_sus_textos(): void
    {
        $id = $this->crear($this->proyecto(), ['fase_sgr' => 3])->json('id');
        $propio = ProyectoExpediente::find($id)->requisitos()->create([
            'requisito_catalogo_id' => null, 'aplica' => true, 'orden' => 900_000_000,
            'titulo_propio' => 'Acta de socialización', 'descripcion_propia' => 'Con la junta de acción comunal', 'categoria_propia' => 'social',
        ]);

        $fila = collect($this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$id}")->json('requisitos'))->firstWhere('id', $propio->id);

        $this->assertNull($fila['requisito']);
        $this->assertSame(['titulo' => 'Acta de socialización', 'descripcion' => 'Con la junta de acción comunal', 'categoria' => 'social'], $fila['propio']);
        $this->assertSame('social', $fila['categoria']);
        $this->assertNull($fila['plantilla']);
        $this->assertSame([], $fila['origenes']);
    }

    public function test_la_cabecera_avisa_si_una_plantilla_dejo_de_ser_compatible(): void
    {
        $propios = $this->plantilla('Propios', ['Planos firmados' => [null]], ['todas_fuentes' => false]);
        $propios->fuentes()->sync(FuenteFinanciacion::where('clave', 'recursos_propios')->pluck('id')->all());
        $proyecto = $this->proyecto(['sgr', 'recursos_propios']);
        $this->crear($proyecto, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $propios->id], 'principal_id' => $this->sgr->id])->assertCreated();

        $proyecto->fuentes()->sync(FuenteFinanciacion::where('clave', 'sgr')->pluck('id')->all());

        $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$proyecto->id}/expedientes")->assertOk()
            ->assertJsonPath('expedientes.0.tiene_sgr', true)
            ->assertJsonPath('expedientes.0.plantillas.0.compatible', true)
            ->assertJsonPath('expedientes.0.plantillas.1.nombre', 'Propios')
            ->assertJsonPath('expedientes.0.plantillas.1.compatible', false);
    }

    // ------------------------------------------------------ avisos duplicados

    public function test_avisa_de_textos_parecidos_entre_plantillas_sin_fusionarlos(): void
    {
        $a1 = $this->sgrA1();
        // Mismas palabras largas que a1, con otros signos, tildes y palabras cortas: Jaccard 1.
        $parecido = mb_strtoupper(str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $a1->texto)) . ' — de la';
        $agua = $this->plantilla('Obras de agua', [$parecido => ['agua-1'], 'Planos firmados por el ingeniero civil' => ['agua-2']]);

        $r = $this->crear($this->proyecto(), ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])
            ->assertCreated();

        $filaA1 = collect($r->json('requisitos'))->firstWhere('requisito.id', $a1->id)['id'];
        $filaAgua = $this->filaPorTexto($r, $parecido)['id'];
        $r->assertJsonPath('progreso.total', 10)
            ->assertJsonPath('avisos_duplicados', [[
                'filas' => [$filaA1, $filaAgua],
                'textos' => [$a1->texto, $parecido],
                'plantillas' => ['SGR', 'Obras de agua'],
                'similitud' => 1, // JSON no conserva el .0
            ]]);
    }

    public function test_no_avisa_dentro_de_una_plantilla_ni_de_textos_poco_parecidos(): void
    {
        // Jaccard 4/5 = 0,8: serían aviso entre plantillas distintas.
        $agua = $this->plantilla('Obras de agua', [
            'Certificado de disponibilidad del servicio de acueducto' => ['agua-1'],
            'Certificado de disponibilidad del servicio de acueducto vigente' => ['agua-2'],
        ]);
        // Jaccard 3/5 con la primera de Obras de agua.
        $energia = $this->plantilla('Energía', ['Certificado de disponibilidad del servicio de energía' => ['energia-1']]);

        $sola = $this->crear($this->proyecto(), ['plantilla_ids' => [$agua->id]])->assertCreated();
        $sola->assertJsonPath('avisos_duplicados', []);

        $this->configurar($sola->json('id'), ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id, $energia->id], 'principal_id' => $agua->id])
            ->assertOk()->assertJsonPath('progreso.total', 11)->assertJsonPath('avisos_duplicados', []);
    }

    public function test_el_umbral_de_parecido_es_ocho_decimos(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Certificado de disponibilidad del servicio de acueducto' => ['agua-1']]);
        $vias = $this->plantilla('Vías', ['Certificado de disponibilidad del servicio de acueducto vigente' => ['vias-1']]);

        $r = $this->crear($this->proyecto(['recursos_propios']), ['plantilla_ids' => [$agua->id, $vias->id], 'principal_id' => $agua->id])
            ->assertCreated();

        $r->assertJsonCount(1, 'avisos_duplicados')
            ->assertJsonPath('avisos_duplicados.0.plantillas', ['Obras de agua', 'Vías'])
            ->assertJsonPath('avisos_duplicados.0.similitud', 0.8);
    }
}
```

En `tests/Feature/Viabilizacion/CatalogoRequisitosApiTest.php`, reemplazar (1 de 4):

```php

use App\Enums\Rol;
use App\Models\Municipio;
use App\Models\Proyecto;
use App\Models\User;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
```

por:

```php

use App\Enums\Rol;
use App\Models\Municipio;
use App\Models\FuenteFinanciacion;
use App\Models\Proyecto;
use App\Models\User;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
```

En `tests/Feature/Viabilizacion/CatalogoRequisitosApiTest.php`, reemplazar (2 de 4):

```php
        $this->seed(CatalogoRequisitosSgrSeeder::class);
    }

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
```

por:

```php
        $this->seed(CatalogoRequisitosSgrSeeder::class);
    }

    /** Proyecto con la fuente SGR: solo a esos se les preselecciona la plantilla SGR. */
    private function proyectoSgr(array $datos): Proyecto
    {
        $proyecto = Proyecto::create($datos);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        return $proyecto;
    }

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
```

En `tests/Feature/Viabilizacion/CatalogoRequisitosApiTest.php`, reemplazar (3 de 4):

```php
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $proyecto = Proyecto::create(['municipio' => $municipio->codigo, 'nombre' => 'Vía terciaria']);

        $antes = $this->admin()->postJson("/GestPro/proyectos/{$proyecto->id}/expedientes", ['fase_sgr' => 3])->assertCreated();
        $textoOriginal = collect($antes->json('requisitos'))->firstWhere('requisito.clave', 'a1')['requisito']['texto'];
```

por:

```php
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $proyecto = $this->proyectoSgr(['municipio' => $municipio->codigo, 'nombre' => 'Vía terciaria']);

        $antes = $this->admin()->postJson("/GestPro/proyectos/{$proyecto->id}/expedientes", ['fase_sgr' => 3])->assertCreated();
        $textoOriginal = collect($antes->json('requisitos'))->firstWhere('requisito.clave', 'a1')['requisito']['texto'];
```

En `tests/Feature/Viabilizacion/CatalogoRequisitosApiTest.php`, reemplazar (4 de 4):

```php
        $this->assertSame($textoOriginal, collect($viejo->json('requisitos'))->firstWhere('requisito.clave', 'a1')['requisito']['texto']);

        // Uno nuevo usa v2.
        $otroProyecto = Proyecto::create(['municipio' => $municipio->codigo, 'nombre' => 'Vía secundaria']);
        $nuevo = $this->admin()->postJson("/GestPro/proyectos/{$otroProyecto->id}/expedientes", ['fase_sgr' => 3])->assertCreated();
        $nuevo->assertJsonPath('catalogo_version.numero', 2)->assertJsonPath('progreso.total', 7);
        $this->assertSame('Texto v2', collect($nuevo->json('requisitos'))->firstWhere('requisito.clave', 'a1')['requisito']['texto']);
```

por:

```php
        $this->assertSame($textoOriginal, collect($viejo->json('requisitos'))->firstWhere('requisito.clave', 'a1')['requisito']['texto']);

        // Uno nuevo usa v2.
        $otroProyecto = $this->proyectoSgr(['municipio' => $municipio->codigo, 'nombre' => 'Vía secundaria']);
        $nuevo = $this->admin()->postJson("/GestPro/proyectos/{$otroProyecto->id}/expedientes", ['fase_sgr' => 3])->assertCreated();
        $nuevo->assertJsonPath('catalogo_version.numero', 2)->assertJsonPath('progreso.total', 7);
        $this->assertSame('Texto v2', collect($nuevo->json('requisitos'))->firstWhere('requisito.clave', 'a1')['requisito']['texto']);
```

En `tests/Feature/Viabilizacion/ExpedienteApiTest.php`, reemplazar (1 de 3):

```php

use App\Enums\Rol;
use App\Models\Municipio;
use App\Models\Proyecto;
use App\Models\User;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
```

por:

```php

use App\Enums\Rol;
use App\Models\Municipio;
use App\Models\FuenteFinanciacion;
use App\Models\Proyecto;
use App\Models\User;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
```

En `tests/Feature/Viabilizacion/ExpedienteApiTest.php`, reemplazar (2 de 3):

```php
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $this->proyecto = Proyecto::create(['municipio' => $municipio->codigo, 'nombre' => 'Acueducto veredal']);
        Storage::fake('local');
    }

    private function como(Rol $rol): static
    {
        $usuario = User::factory()->create(['rol' => $rol]);
```

por:

```php
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $this->proyecto = $this->proyectoSgr(['municipio' => $municipio->codigo, 'nombre' => 'Acueducto veredal']);
        Storage::fake('local');
    }

    /** Proyecto con la fuente SGR: solo a esos se les preselecciona la plantilla SGR. */
    private function proyectoSgr(array $datos): Proyecto
    {
        $proyecto = Proyecto::create($datos);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        return $proyecto;
    }

    private function como(Rol $rol): static
    {
        $usuario = User::factory()->create(['rol' => $rol]);
```

En `tests/Feature/Viabilizacion/ExpedienteApiTest.php`, reemplazar (3 de 3):

```php

    private function otroProyecto(string $nombre): Proyecto
    {
        return Proyecto::create(['municipio' => $this->proyecto->municipio, 'nombre' => $nombre]);
    }

    public function test_resumen_trae_el_expediente_mas_reciente_de_cada_proyecto(): void
```

por:

```php

    private function otroProyecto(string $nombre): Proyecto
    {
        return $this->proyectoSgr(['municipio' => $this->proyecto->municipio, 'nombre' => $nombre]);
    }

    public function test_resumen_trae_el_expediente_mas_reciente_de_cada_proyecto(): void
```

En `tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`, reemplazar (1 de 3):

```php
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_si_falla_la_creacion_el_proyecto_se_guarda_igual(): void
    {
        DB::table('catalogo_versiones')->update(['vigente' => false]);

        $this->guardar()->assertOk();

        $this->assertDatabaseHas('proyectos', ['nombre' => 'Acueducto veredal']);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
```

por:

```php
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    /**
     * Un sector cuyo equivalente SGR está inactivo: la creación automática lo preselecciona y
     * la validación de la configuración la rechaza. Sin versión vigente SGR ya no hay fallo:
     * la plantilla deja de ser compatible y el expediente nace sin plantillas.
     */
    private function sectorConEquivalenteInactivo(): int
    {
        $sector = $this->sectorMapeado();
        DB::table('requisitos_sectores')->where('clave', 'vivienda')->update(['activo' => false]);

        return $sector;
    }

    public function test_si_falla_la_creacion_el_proyecto_se_guarda_igual(): void
    {
        $this->guardar(['sector' => $this->sectorConEquivalenteInactivo()])->assertOk();

        $this->assertDatabaseHas('proyectos', ['nombre' => 'Acueducto veredal']);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
```

En `tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`, reemplazar (2 de 3):

```php
    public function test_un_fallo_de_la_creacion_queda_registrado(): void
    {
        Exceptions::fake();
        DB::table('catalogo_versiones')->update(['vigente' => false]);

        $this->guardar()->assertOk();

        Exceptions::assertReported(ReglaNegocioException::class);
    }
```

por:

```php
    public function test_un_fallo_de_la_creacion_queda_registrado(): void
    {
        Exceptions::fake();

        $this->guardar(['sector' => $this->sectorConEquivalenteInactivo()])->assertOk();

        Exceptions::assertReported(ReglaNegocioException::class);
    }
```

En `tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`, reemplazar (3 de 3):

```php

        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id, 'fase' => $this->licitacion])
            ->assertOk()
            ->assertJsonPath('advertencia', "El proyecto salió de Formulación con el expediente de viabilización #{$expediente->id} sin radicar.");

        $this->assertDatabaseHas('proyectos', ['id' => $proyecto->id, 'fase' => $this->licitacion]);
    }
```

por:

```php

        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id, 'fase' => $this->licitacion])
            ->assertOk()
            ->assertJsonPath('advertencia', "El proyecto salió de Formulación con el expediente #{$expediente->id} sin radicar.");

        $this->assertDatabaseHas('proyectos', ['id' => $proyecto->id, 'fase' => $this->licitacion]);
    }
```

En `tests/Feature/Viabilizacion/HistorialRequisitoApiTest.php`, reemplazar (1 de 4):

```php

use App\Enums\Rol;
use App\Models\ChecklistHistorial;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
```

por:

```php

use App\Enums\Rol;
use App\Models\ChecklistHistorial;
use App\Models\FuenteFinanciacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
```

En `tests/Feature/Viabilizacion/HistorialRequisitoApiTest.php`, reemplazar (2 de 4):

```php

        $this->gestor = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Gestora Uno']);
        $servicio = app(ExpedienteService::class);
        $this->expediente = $servicio->crear(Proyecto::create(['nombre' => 'Acueducto']), ['fase_sgr' => 3], $this->gestor);
        $servicio->confirmarConfiguracion($this->expediente, ['fase_sgr' => 3], $this->gestor);
        $this->fila = $this->expediente->requisitos()->where('aplica', true)->orderBy('orden')->first();
    }

    private function como(Rol|User $quien): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);
```

por:

```php

        $this->gestor = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Gestora Uno']);
        $servicio = app(ExpedienteService::class);
        $this->expediente = $servicio->crear($this->proyectoSgr(['nombre' => 'Acueducto']), ['fase_sgr' => 3], $this->gestor);
        $servicio->confirmarConfiguracion($this->expediente, ['fase_sgr' => 3], $this->gestor);
        $this->fila = $this->expediente->requisitos()->where('aplica', true)->orderBy('orden')->first();
    }

    /** Proyecto con la fuente SGR: solo a esos se les preselecciona la plantilla SGR. */
    private function proyectoSgr(array $datos): Proyecto
    {
        $proyecto = Proyecto::create($datos);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        return $proyecto;
    }

    private function como(Rol|User $quien): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);
```

En `tests/Feature/Viabilizacion/HistorialRequisitoApiTest.php`, reemplazar (3 de 4):

```php
        $this->subir('v2.pdf');
        $entrada = $this->historial()[0]['id'];
        $servicio = app(ExpedienteService::class);
        $otro = $servicio->crear(Proyecto::create(['nombre' => 'Vía']), ['fase_sgr' => 3], null);

        $this->como(Rol::Consulta)->get("/GestPro/expedientes/{$otro->id}/historial/{$entrada}/soporte")->assertNotFound();
    }
```

por:

```php
        $this->subir('v2.pdf');
        $entrada = $this->historial()[0]['id'];
        $servicio = app(ExpedienteService::class);
        $otro = $servicio->crear($this->proyectoSgr(['nombre' => 'Vía']), ['fase_sgr' => 3], null);

        $this->como(Rol::Consulta)->get("/GestPro/expedientes/{$otro->id}/historial/{$entrada}/soporte")->assertNotFound();
    }
```

En `tests/Feature/Viabilizacion/HistorialRequisitoApiTest.php`, reemplazar (4 de 4):

```php

    public function test_no_se_descarga_el_soporte_de_otro_expediente_subiendo_de_carpeta(): void
    {
        $otro = app(ExpedienteService::class)->crear(Proyecto::create(['nombre' => 'Vía']), ['fase_sgr' => 3], null);
        Storage::disk('local')->put("expedientes/{$otro->id}/x.pdf", 'x');
        $entrada = ChecklistHistorial::create([
            'entidad_tipo' => 'requisito', 'entidad_id' => $this->fila->id, 'expediente_id' => $this->expediente->id,
```

por:

```php

    public function test_no_se_descarga_el_soporte_de_otro_expediente_subiendo_de_carpeta(): void
    {
        $otro = app(ExpedienteService::class)->crear($this->proyectoSgr(['nombre' => 'Vía']), ['fase_sgr' => 3], null);
        Storage::disk('local')->put("expedientes/{$otro->id}/x.pdf", 'x');
        $entrada = ChecklistHistorial::create([
            'entidad_tipo' => 'requisito', 'entidad_id' => $this->fila->id, 'expediente_id' => $this->expediente->id,
```

En `tests/Feature/Viabilizacion/NoAplicaRequisitoApiTest.php`, reemplazar (1 de 3):

```php
namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
```

por:

```php
namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\FuenteFinanciacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
```

En `tests/Feature/Viabilizacion/NoAplicaRequisitoApiTest.php`, reemplazar (2 de 3):

```php

        // Fase III sin sectores: 8 requisitos generales.
        $servicio = app(ExpedienteService::class);
        $this->expediente = $servicio->crear(Proyecto::create(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);
        $servicio->confirmarConfiguracion($this->expediente, ['fase_sgr' => 3]);
        $this->fila = $this->expediente->requisitos()->where('aplica', true)->orderBy('orden')->first();
    }

    private function usuario(Rol $rol): User
    {
        return User::factory()->create(['rol' => $rol]);
```

por:

```php

        // Fase III sin sectores: 8 requisitos generales.
        $servicio = app(ExpedienteService::class);
        $this->expediente = $servicio->crear($this->proyectoSgr(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);
        $servicio->confirmarConfiguracion($this->expediente, ['fase_sgr' => 3]);
        $this->fila = $this->expediente->requisitos()->where('aplica', true)->orderBy('orden')->first();
    }

    /** Proyecto con la fuente SGR: solo a esos se les preselecciona la plantilla SGR. */
    private function proyectoSgr(array $datos): Proyecto
    {
        $proyecto = Proyecto::create($datos);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        return $proyecto;
    }

    private function usuario(Rol $rol): User
    {
        return User::factory()->create(['rol' => $rol]);
```

En `tests/Feature/Viabilizacion/NoAplicaRequisitoApiTest.php`, reemplazar (3 de 3):

```php
    public function test_un_requisito_de_otro_expediente_no_sirve(): void
    {
        $servicio = app(ExpedienteService::class);
        $otro = $servicio->crear(Proyecto::create(['nombre' => 'Vía']), ['fase_sgr' => 3], null);
        $servicio->confirmarConfiguracion($otro, ['fase_sgr' => 3]);
        $ajena = $otro->requisitos()->where('aplica', true)->first();
```

por:

```php
    public function test_un_requisito_de_otro_expediente_no_sirve(): void
    {
        $servicio = app(ExpedienteService::class);
        $otro = $servicio->crear($this->proyectoSgr(['nombre' => 'Vía']), ['fase_sgr' => 3], null);
        $servicio->confirmarConfiguracion($otro, ['fase_sgr' => 3]);
        $ajena = $otro->requisitos()->where('aplica', true)->first();
```

En `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`, reemplazar (1 de 4):

```php
use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
```

por:

```php
use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\FuenteFinanciacion;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
```

En `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`, reemplazar (2 de 4):

```php
        $this->seed(CatalogoRequisitosSgrSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['rol' => Rol::Administrador]);
```

por:

```php
        $this->seed(CatalogoRequisitosSgrSeeder::class);
    }

    /** Proyecto con la fuente SGR: solo a esos se les preselecciona la plantilla SGR. */
    private function proyectoSgr(array $datos): Proyecto
    {
        $proyecto = Proyecto::create($datos);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        return $proyecto;
    }

    private function admin(): User
    {
        return User::factory()->create(['rol' => Rol::Administrador]);
```

En `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`, reemplazar (3 de 4):

```php
            ->assertOk()->assertJsonPath('version.id', $sgr->id);

        $expediente = app(ExpedienteService::class)
            ->crear(Proyecto::create(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);

        $this->assertSame($sgr->id, $expediente->catalogo_version_id);
    }
```

por:

```php
            ->assertOk()->assertJsonPath('version.id', $sgr->id);

        $expediente = app(ExpedienteService::class)
            ->crear($this->proyectoSgr(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);

        $this->assertSame($sgr->id, $expediente->catalogo_version_id);
    }
```

En `tests/Feature/Viabilizacion/PlantillasVersionesTest.php`, reemplazar (4 de 4):

```php
    public function test_un_expediente_nuevo_registra_su_version_como_principal(): void
    {
        $servicio = app(ExpedienteService::class);
        $expediente = $servicio->crear(Proyecto::create(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);

        $version = $expediente->versionesPlantilla()->sole();
        $this->assertSame($expediente->catalogo_version_id, $version->id);
```

por:

```php
    public function test_un_expediente_nuevo_registra_su_version_como_principal(): void
    {
        $servicio = app(ExpedienteService::class);
        $expediente = $servicio->crear($this->proyectoSgr(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);

        $version = $expediente->versionesPlantilla()->sole();
        $this->assertSame($expediente->catalogo_version_id, $version->id);
```

En `tests/Feature/Viabilizacion/SeguimientoRequisitoApiTest.php`, reemplazar (1 de 3):

```php
namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
```

por:

```php
namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\FuenteFinanciacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
```

En `tests/Feature/Viabilizacion/SeguimientoRequisitoApiTest.php`, reemplazar (2 de 3):

```php

        // Fase III sin sectores: 8 requisitos generales.
        $servicio = app(ExpedienteService::class);
        $this->expediente = $servicio->crear(Proyecto::create(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);
        $servicio->confirmarConfiguracion($this->expediente, ['fase_sgr' => 3]);
    }

    private function como(Rol|User $quien): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);
```

por:

```php

        // Fase III sin sectores: 8 requisitos generales.
        $servicio = app(ExpedienteService::class);
        $this->expediente = $servicio->crear($this->proyectoSgr(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);
        $servicio->confirmarConfiguracion($this->expediente, ['fase_sgr' => 3]);
    }

    /** Proyecto con la fuente SGR: solo a esos se les preselecciona la plantilla SGR. */
    private function proyectoSgr(array $datos): Proyecto
    {
        $proyecto = Proyecto::create($datos);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        return $proyecto;
    }

    private function como(Rol|User $quien): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);
```

En `tests/Feature/Viabilizacion/SeguimientoRequisitoApiTest.php`, reemplazar (3 de 3):

```php
        DB::table('requisitos_catalogo')->update(['caracter' => 'opcional']);
        $servicio = app(ExpedienteService::class);

        $otro = $servicio->crear(Proyecto::create(['nombre' => 'Vía']), ['fase_sgr' => 3], null);

        $this->assertGreaterThan(0, $otro->requisitos()->count());
        $this->assertSame(0, $otro->requisitos()->where('caracter', '!=', 'opcional')->count());
```

por:

```php
        DB::table('requisitos_catalogo')->update(['caracter' => 'opcional']);
        $servicio = app(ExpedienteService::class);

        $otro = $servicio->crear($this->proyectoSgr(['nombre' => 'Vía']), ['fase_sgr' => 3], null);

        $this->assertGreaterThan(0, $otro->requisitos()->count());
        $this->assertSame(0, $otro->requisitos()->where('caracter', '!=', 'opcional')->count());
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Viabilizacion`
Expected: FALLAN 20 y pasan 139. Fallan las nuevas de `ExpedientePlantillasApiTest`: crear con plantillas responde 422, falta `plantillas` en la respuesta y la preselección no existe. También fallan las existentes que comprueban textos nuevos.

- [ ] **Paso 3: Implementar**

En `app/Http/Controllers/ExpedienteController.php`, reemplazar (1 de 1):

```php
 */
class ExpedienteController extends Controller
{
    private const REGLAS_CONFIGURACION = [
        'fase_sgr' => ['required', 'integer', 'in:1,2,3'],
        'sector_ids' => ['sometimes', 'array'],
        'sector_ids.*' => ['integer'],
        'tipo_ids' => ['sometimes', 'array'],
```

por:

```php
 */
class ExpedienteController extends Controller
{
    // Sin plantilla_ids, las plantillas no cambian (al crear, se preselecciona la SGR si
    // corresponde). La fase solo la exige el servicio, y solo si hay plantilla con motor sgr.
    private const REGLAS_CONFIGURACION = [
        'plantilla_ids' => ['sometimes', 'array'],
        'plantilla_ids.*' => ['integer'],
        'principal_id' => ['sometimes', 'nullable', 'integer'],
        'fase_sgr' => ['sometimes', 'integer', 'in:1,2,3'],
        'sector_ids' => ['sometimes', 'array'],
        'sector_ids.*' => ['integer'],
        'tipo_ids' => ['sometimes', 'array'],
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 15):

```php
namespace App\Services\Viabilizacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\Fase;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
```

por:

```php
namespace App\Services\Viabilizacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\Fase;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 15):

```php
use App\Models\Responsable;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ciclo de vida del expediente de viabilización (la instancia por proyecto).
 *
 * Reglas:
 * - Se crea con la versión VIGENTE del catálogo y queda atada a ella.
 * - Cada proyecto tiene como máximo un expediente en borrador.
 * - Nace sin configurar: solo se chequea o radica tras confirmar la configuración,
 *   porque ella decide qué requisitos aplican y, con eso, el porcentaje de avance.
```

por:

```php
use App\Models\Responsable;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo de vida del expediente de viabilización (la instancia por proyecto).
 *
 * Reglas:
 * - Lleva una o varias plantillas, cada una con la versión con que entró (la vigente
 *   de ese momento). Sin plantillas en el pedido, nace con la plantilla SGR si el
 *   proyecto la admite, o sin plantillas.
 * - La fase SGR solo se exige si el expediente tiene una plantilla con motor sgr.
 * - Cada proyecto tiene como máximo un expediente en borrador.
 * - Nace sin configurar: solo se chequea o radica tras confirmar la configuración,
 *   porque ella decide qué requisitos aplican y, con eso, el porcentaje de avance.
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (3 de 15):

```php

    private const ORDEN_SECCION = ['A' => 1, 'B' => 2, 'D' => 3, 'C' => 4];

    // Orden de una fila: posición de la plantilla del primer origen, sección (motor sgr) y orden
    // del requisito. La principal tiene posición 0: un expediente solo SGR conserva su orden.
    private const ORDEN_POR_PLANTILLA = 10_000_000;
```

por:

```php

    private const ORDEN_SECCION = ['A' => 1, 'B' => 2, 'D' => 3, 'C' => 4];

    private const SIN_PLANTILLAS = 'Elige al menos una plantilla para armar el checklist.';

    // Orden de una fila: posición de la plantilla del primer origen, sección (motor sgr) y orden
    // del requisito. La principal tiene posición 0: un expediente solo SGR conserva su orden.
    private const ORDEN_POR_PLANTILLA = 10_000_000;
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (4 de 15):

```php
    private const CARACTER_POR_EXIGENCIA = [RequisitoCatalogo::OBLIGATORIO, RequisitoCatalogo::CONDICIONADO, RequisitoCatalogo::OPCIONAL];

    private const RELACIONES_FILA = [
        'requisito.sector:id,clave,nombre,fuente', 'requisito.tipo:id,clave,nombre', 'usuarioCumplido:id,name',
        'usuarioNoAplica:id,name', 'usuarioAprobo:id,name', 'responsable:id,nombre,cargo',
    ];

    public function __construct(
```

por:

```php
    private const CARACTER_POR_EXIGENCIA = [RequisitoCatalogo::OBLIGATORIO, RequisitoCatalogo::CONDICIONADO, RequisitoCatalogo::OPCIONAL];

    private const RELACIONES_FILA = [
        'requisito.sector:id,clave,nombre,fuente', 'requisito.tipo:id,clave,nombre',
        'requisito.version:id,plantilla_id', 'requisito.version.plantilla:id,nombre,motor',
        'origenes.sector:id,clave,nombre,fuente', 'origenes.tipo:id,clave,nombre',
        'origenes.version:id,plantilla_id', 'origenes.version.plantilla:id,nombre,motor',
        'usuarioCumplido:id,name', 'usuarioNoAplica:id,name', 'usuarioAprobo:id,name', 'responsable:id,nombre,cargo',
    ];

    public function __construct(
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (5 de 15):

```php

    // -------------------------------------------------------------- escritura

    public function crear(Proyecto $proyecto, array $datos, ?User $usuario): ProyectoExpediente
    {
        $version = PlantillaChecklist::sgr()->versionVigente()
            ?? throw new ReglaNegocioException('No hay una versión vigente del catálogo de requisitos.');

        $seleccion = $this->validarSeleccion($datos, null);

        return DB::transaction(function () use ($proyecto, $version, $datos, $seleccion, $usuario) {
            // El bloqueo evita dos borradores por una doble petición simultánea.
            Proyecto::query()->whereKey($proyecto->id)->lockForUpdate()->first();
            $this->exigirSinBorrador($proyecto);

            $expediente = ProyectoExpediente::create([
                'proyecto_id' => $proyecto->id,
                'catalogo_version_id' => $version->id,
                'fase_sgr' => (int) $datos['fase_sgr'],
                'estado' => ProyectoExpediente::BORRADOR,
                'usuario_creo_id' => $usuario?->id,
            ]);

            $expediente->versionesPlantilla()->attach($version->id, ['es_principal' => true]);
            $this->guardarSeleccion($expediente, $seleccion);
            $this->sincronizar($expediente);
            $this->historial->registrarExpediente($expediente, 'expediente_creado', [], ['catalogo_version_id' => $version->id], $usuario);

            return $expediente;
        });
```

por:

```php

    // -------------------------------------------------------------- escritura

    /**
     * @param array{plantilla_ids?: list<int>, principal_id?: ?int, fase_sgr?: int} $datos sin
     *        plantilla_ids, se preselecciona la plantilla SGR compatible, si la hay
     */
    public function crear(Proyecto $proyecto, array $datos, ?User $usuario): ProyectoExpediente
    {
        $plantillas = $this->plantillas->validar(
            array_key_exists('plantilla_ids', $datos) ? $datos : $this->plantillas->preseleccion($proyecto),
            $proyecto,
            null
        );
        $this->exigirFaseSgr($datos, $plantillas['version_sgr_id'] !== null);
        $seleccion = $this->validarSeleccion($datos, null);

        return DB::transaction(function () use ($proyecto, $plantillas, $datos, $seleccion, $usuario) {
            // El bloqueo evita dos borradores por una doble petición simultánea.
            Proyecto::query()->whereKey($proyecto->id)->lockForUpdate()->first();
            $this->exigirSinBorrador($proyecto);

            // Sin fase en el pedido, la columna conserva su valor por defecto.
            $expediente = ProyectoExpediente::create([
                'proyecto_id' => $proyecto->id,
                'estado' => ProyectoExpediente::BORRADOR,
                'usuario_creo_id' => $usuario?->id,
                ...(isset($datos['fase_sgr']) ? ['fase_sgr' => (int) $datos['fase_sgr']] : []),
            ])->refresh();

            $this->plantillas->guardar($expediente, $plantillas);
            $this->guardarSeleccion($expediente, $seleccion);
            $this->sincronizar($expediente);
            $this->historial->registrarExpediente($expediente, 'expediente_creado', [], [
                'catalogo_version_id' => $expediente->catalogo_version_id,
                'plantillas' => $this->plantillasRegistrables($expediente),
            ], $usuario);

            return $expediente;
        });
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (6 de 15):

```php

    public function confirmarConfiguracion(ProyectoExpediente $expediente, array $datos, ?User $usuario = null): ProyectoExpediente
    {
        return DB::transaction(function () use ($expediente, $datos, $usuario) {
            $this->actualizarConfiguracion($expediente, $datos, $usuario);
            if (! $expediente->estaConfigurado()) {
```

por:

```php

    public function confirmarConfiguracion(ProyectoExpediente $expediente, array $datos, ?User $usuario = null): ProyectoExpediente
    {
        $sinPlantillas = array_key_exists('plantilla_ids', $datos)
            ? $datos['plantilla_ids'] === []
            : ! $expediente->versionesPlantilla()->exists();
        if ($sinPlantillas) {
            throw new ReglaNegocioException(self::SIN_PLANTILLAS);
        }

        return DB::transaction(function () use ($expediente, $datos, $usuario) {
            $this->actualizarConfiguracion($expediente, $datos, $usuario);
            if (! $expediente->estaConfigurado()) {
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (7 de 15):

```php
        $borrador = $proyecto->expedientes()->where('estado', ProyectoExpediente::BORRADOR)->latest('id')->first();

        return $borrador
            ? "El proyecto salió de Formulación con el expediente de viabilización #{$borrador->id} sin radicar."
            : null;
    }

    public function actualizarConfiguracion(ProyectoExpediente $expediente, array $datos, ?User $usuario = null): ProyectoExpediente
    {
        $this->exigirBorrador($expediente);
        $seleccion = $this->validarSeleccion($datos, $expediente);

        return DB::transaction(function () use ($expediente, $datos, $seleccion, $usuario) {
            $antes = $this->configuracionRegistrable($expediente);
            $expediente->update(['fase_sgr' => (int) $datos['fase_sgr']]);
            $this->guardarSeleccion($expediente, $seleccion);
            $this->sincronizar($expediente);
            $despues = $this->configuracionRegistrable($expediente);
```

por:

```php
        $borrador = $proyecto->expedientes()->where('estado', ProyectoExpediente::BORRADOR)->latest('id')->first();

        return $borrador
            ? "El proyecto salió de Formulación con el expediente #{$borrador->id} sin radicar."
            : null;
    }

    /**
     * Sin plantilla_ids en el pedido, las plantillas del expediente no cambian. Mientras se
     * arma puede quedarse sin plantillas; ya confirmado, no.
     */
    public function actualizarConfiguracion(ProyectoExpediente $expediente, array $datos, ?User $usuario = null): ProyectoExpediente
    {
        $this->exigirBorrador($expediente);
        $plantillas = array_key_exists('plantilla_ids', $datos)
            ? $this->plantillas->validar($datos, $expediente->proyecto, $expediente)
            : null;
        if ($plantillas && $plantillas['versiones'] === [] && $expediente->estaConfigurado()) {
            throw new ReglaNegocioException(self::SIN_PLANTILLAS);
        }
        $this->exigirFaseSgr($datos, $plantillas
            ? $plantillas['version_sgr_id'] !== null
            : $this->plantillas->tieneSgr($this->plantillas->versionesDe($expediente)));
        $seleccion = $this->validarSeleccion($datos, $expediente);

        return DB::transaction(function () use ($expediente, $datos, $plantillas, $seleccion, $usuario) {
            $antes = $this->configuracionRegistrable($expediente);
            if (isset($datos['fase_sgr'])) {
                $expediente->update(['fase_sgr' => (int) $datos['fase_sgr']]);
            }
            if ($plantillas) {
                $this->plantillas->guardar($expediente, $plantillas);
            }
            $this->guardarSeleccion($expediente, $seleccion);
            $this->sincronizar($expediente);
            $despues = $this->configuracionRegistrable($expediente);
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (8 de 15):

```php
    public function presentar(ProyectoExpediente $expediente): array
    {
        $expediente->load([
            'proyecto:id,nombre', 'version:id,numero', 'usuarioRadico:id,name', 'usuarioCreo:id,name',
            'sectores', 'tipos', 'componentes', 'tipologias',
        ]);

        $filas = $expediente->requisitos()
            ->where('aplica', true)
            ->with(self::RELACIONES_FILA)
            ->orderBy('orden')
            ->get();

        return [
            ...$this->presentarCabecera($expediente),
            'configuracion' => [
                'sectores' => $this->opciones($expediente->sectores),
                'tipos' => $expediente->tipos->map(fn ($t) => ['id' => $t->id, 'sector_id' => $t->sector_id, 'clave' => $t->clave, 'nombre' => $t->nombre])->values(),
```

por:

```php
    public function presentar(ProyectoExpediente $expediente): array
    {
        $expediente->load([
            'proyecto:id,nombre,sector', 'version:id,numero', 'usuarioRadico:id,name', 'usuarioCreo:id,name',
            'sectores', 'tipos', 'componentes', 'tipologias',
        ]);
        $versiones = $this->plantillas->versionesDe($expediente);
        $posiciones = $this->posiciones($versiones);

        $filas = $expediente->requisitos()
            ->where('aplica', true)
            ->with(self::RELACIONES_FILA)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        return [
            ...$this->presentarCabecera($expediente, $versiones),
            'configuracion' => [
                'sectores' => $this->opciones($expediente->sectores),
                'tipos' => $expediente->tipos->map(fn ($t) => ['id' => $t->id, 'sector_id' => $t->sector_id, 'clave' => $t->clave, 'nombre' => $t->nombre])->values(),
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (9 de 15):

```php
                'tipologias' => $this->opciones($expediente->tipologias),
            ],
            'progreso' => $this->progresoDe($expediente),
            'requisitos' => $filas->map(fn (ProyectoRequisito $f) => $this->presentarFila($f))->values(),
            // Filas que dejaron de aplicar pero guardan trabajo hecho.
            'retenidos' => $expediente->requisitos()->where('aplica', false)
                ->where(fn ($q) => $q->where('cumplido', true)->orWhereNotNull('observacion')->orWhereNotNull('soporte_archivo')
```

por:

```php
                'tipologias' => $this->opciones($expediente->tipologias),
            ],
            'progreso' => $this->progresoDe($expediente),
            'requisitos' => $filas->map(fn (ProyectoRequisito $f) => $this->presentarFila($f, $posiciones))->values(),
            'avisos_duplicados' => $versiones->count() > 1 ? $this->avisosDuplicados($filas, $posiciones) : [],
            // Filas que dejaron de aplicar pero guardan trabajo hecho.
            'retenidos' => $expediente->requisitos()->where('aplica', false)
                ->where(fn ($q) => $q->where('cumplido', true)->orWhereNotNull('observacion')->orWhereNotNull('soporte_archivo')
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (10 de 15):

```php
            ->all();
    }

    public function presentarCabecera(ProyectoExpediente $expediente): array
    {
        $expediente->loadMissing(['proyecto:id,nombre', 'version:id,numero', 'usuarioRadico:id,name', 'usuarioCreo:id,name']);

        return [
            'id' => $expediente->id,
            'proyecto' => ['id' => $expediente->proyecto->id, 'nombre' => $expediente->proyecto->nombre],
            'catalogo_version' => ['id' => $expediente->version->id, 'numero' => $expediente->version->numero],
            'fase_sgr' => $expediente->fase_sgr,
            'configurado' => $expediente->estaConfigurado(),
            'estado' => $expediente->estado,
```

por:

```php
            ->all();
    }

    /** @param ?Collection<int, CatalogoVersion> $versiones las de versionesDe(), si ya se leyeron */
    public function presentarCabecera(ProyectoExpediente $expediente, ?Collection $versiones = null): array
    {
        $expediente->loadMissing(['proyecto:id,nombre,sector', 'version:id,numero', 'usuarioRadico:id,name', 'usuarioCreo:id,name']);
        $versiones ??= $this->plantillas->versionesDe($expediente);
        $compatibles = $versiones->isEmpty() ? [] : $this->plantillas->idsCompatibles($expediente->proyecto);

        return [
            'id' => $expediente->id,
            'proyecto' => ['id' => $expediente->proyecto->id, 'nombre' => $expediente->proyecto->nombre],
            // Solo con plantilla SGR: es la versión que siguen las pantallas y rutas del catálogo SGR.
            'catalogo_version' => $expediente->version ? ['id' => $expediente->version->id, 'numero' => $expediente->version->numero] : null,
            'plantillas' => $versiones->map(fn (CatalogoVersion $v) => [
                'plantilla_id' => $v->plantilla_id,
                'nombre' => $v->plantilla->nombre,
                'motor' => $v->plantilla->motor,
                'es_principal' => (bool) $v->pivot->es_principal,
                // Cambiar las fuentes o el sector del proyecto no altera el expediente: solo se avisa.
                'compatible' => in_array($v->plantilla_id, $compatibles, true),
                'version' => ['id' => $v->id, 'numero' => $v->numero],
            ])->values()->all(),
            'tiene_sgr' => $this->plantillas->tieneSgr($versiones),
            'fase_sgr' => $expediente->fase_sgr,
            'configurado' => $expediente->estaConfigurado(),
            'estado' => $expediente->estado,
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (11 de 15):

```php
        );
    }

    public function presentarFila(ProyectoRequisito $fila): array
    {
        $fila->loadMissing(self::RELACIONES_FILA);
        $r = $fila->requisito;

        return [
            'id' => $fila->id,
```

por:

```php
        );
    }

    /**
     * Se muestra el requisito del primer origen (la principal primero); sin orígenes, el ancla.
     * Un requisito propio no tiene requisito de catálogo: trae sus textos en `propio`.
     *
     * @param ?array<int, int> $posiciones plantilla_id => posición en el expediente, si ya se leyeron
     */
    public function presentarFila(ProyectoRequisito $fila, ?array $posiciones = null): array
    {
        $fila->loadMissing(self::RELACIONES_FILA);
        $posiciones ??= $this->posiciones($this->plantillas->versionesDe($fila->expediente));
        $origenes = $this->origenesOrdenados($fila, $posiciones);
        $r = $origenes->first() ?? $fila->requisito;
        $plantilla = $r?->version->plantilla;

        return [
            'id' => $fila->id,
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (12 de 15):

```php
            'usuario_cumplido' => $fila->usuarioCumplido?->name,
            'observacion' => $fila->observacion,
            'soporte' => $fila->soporte_archivo ? ['nombre' => $fila->soporte_nombre] : null,
            'requisito' => [
                'id' => $r->id,
                'clave' => $r->clave,
                'seccion' => $r->seccion,
```

por:

```php
            'usuario_cumplido' => $fila->usuarioCumplido?->name,
            'observacion' => $fila->observacion,
            'soporte' => $fila->soporte_archivo ? ['nombre' => $fila->soporte_nombre] : null,
            'requisito' => $r ? [
                'id' => $r->id,
                'clave' => $r->clave,
                'seccion' => $r->seccion,
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (13 de 15):

```php
                'codigo' => $r->codigo,
                'texto' => $r->texto,
                'nota' => $r->nota,
                'cargo_cumplimiento' => $r->cargo_cumplimiento,
                'cargo_revision' => $r->cargo_revision,
                'requiere_aprobacion_no_aplica' => $r->requiere_aprobacion_no_aplica,
            ],
        ];
    }

    // ------------------------------------------------------------ internos

    /**
```

por:

```php
                'codigo' => $r->codigo,
                'texto' => $r->texto,
                'nota' => $r->nota,
                'descripcion' => $r->descripcion,
                // En palabras, cuándo aplica un condicionado: lo lee quien decide si aplica.
                'condicion_descripcion' => $r->condicion_descripcion,
                'soporte_requerido' => $r->soporte_requerido,
                'cargo_cumplimiento' => $r->cargo_cumplimiento,
                'cargo_revision' => $r->cargo_revision,
                'requiere_aprobacion_no_aplica' => $r->requiere_aprobacion_no_aplica,
            ] : null,
            'plantilla' => $plantilla ? ['id' => $plantilla->id, 'nombre' => $plantilla->nombre, 'motor' => $plantilla->motor] : null,
            'origenes' => $origenes->map(fn (RequisitoCatalogo $o) => [
                'plantilla_id' => $o->version->plantilla_id,
                'nombre' => $o->version->plantilla->nombre,
            ])->values()->all(),
            'propio' => $fila->esPropio() ? [
                'titulo' => $fila->titulo_propio,
                'descripcion' => $fila->descripcion_propia,
                'categoria' => $fila->categoria_propia,
            ] : null,
            'categoria' => $fila->esPropio() ? $fila->categoria_propia : $r->categoria,
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
        return $fila->origenes
            ->sortBy(fn (RequisitoCatalogo $o) => [$posiciones[$o->version->plantilla_id] ?? PHP_INT_MAX, $o->id])
            ->values();
    }

    /**
     * Pares de filas que aplican, de plantillas distintas y sin llave compartida, cuyos textos
     * se parecen: Jaccard de sus palabras normalizadas de 0,8 o más. Solo es un aviso: nunca
     * se fusionan. Las filas comparten plantilla si alguna de sus plantillas de origen coincide.
     *
     * @return list<array{filas: array{int, int}, textos: array{string, string}, plantillas: array{string, string}, similitud: float}>
     */
    private function avisosDuplicados(Collection $filas, array $posiciones): array
    {
        $candidatas = $filas
            ->reject(fn (ProyectoRequisito $f) => $f->esPropio())
            ->map(function (ProyectoRequisito $f) use ($posiciones) {
                $origenes = $this->origenesOrdenados($f, $posiciones);
                $mostrado = $origenes->first() ?? $f->requisito;

                return [
                    'id' => $f->id,
                    'texto' => $mostrado->texto,
                    'plantilla' => $mostrado->version->plantilla->nombre,
                    'plantillas' => $origenes->map(fn (RequisitoCatalogo $o) => $o->version->plantilla_id)->unique()->all(),
                    'palabras' => $this->palabras($mostrado->texto),
                ];
            })
            ->filter(fn (array $c) => $c['palabras'] !== [])
            ->values()
            ->all();

        $avisos = [];
        foreach ($candidatas as $i => $a) {
            for ($j = $i + 1; $j < count($candidatas); $j++) {
                $b = $candidatas[$j];
                if (array_intersect($a['plantillas'], $b['plantillas'])) {
                    continue;
                }
                $comunes = count(array_intersect_key($a['palabras'], $b['palabras']));
                $todas = count($a['palabras'] + $b['palabras']);
                // comunes / todas >= 0,8, sin redondeo de coma flotante.
                if ($comunes * 5 >= $todas * 4) {
                    $avisos[] = [
                        'filas' => [$a['id'], $b['id']],
                        'textos' => [$a['texto'], $b['texto']],
                        'plantillas' => [$a['plantilla'], $b['plantilla']],
                        'similitud' => round($comunes / $todas, 2),
                    ];
                }
            }
        }

        return $avisos;
    }

    /** @return array<string, true> palabras de más de 3 letras, en minúsculas y sin tildes ni signos */
    private function palabras(?string $texto): array
    {
        $palabras = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii((string) $texto)), -1, PREG_SPLIT_NO_EMPTY);

        return array_fill_keys(array_filter($palabras, fn (string $p) => strlen($p) > 3), true);
    }

    // ------------------------------------------------------------ internos

    /**
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (14 de 15):

```php
        return (new $modelo())->getTable();
    }

    /** Fase e ids ordenados de cada selección, para comparar y registrar la configuración. */
    private function configuracionRegistrable(ProyectoExpediente $expediente): array
    {
        $ids = fn (string $relacion, string $tabla) => $expediente->{$relacion}()
            ->orderBy("{$tabla}.id")->pluck("{$tabla}.id")->map(fn ($id) => (int) $id)->all();

        return [
            'fase_sgr' => (int) $expediente->fase_sgr,
            'sector_ids' => $ids('sectores', 'requisitos_sectores'),
            'tipo_ids' => $ids('tipos', 'requisitos_tipos'),
```

por:

```php
        return (new $modelo())->getTable();
    }

    /** Plantillas, fase e ids ordenados de cada selección, para comparar y registrar la configuración. */
    private function configuracionRegistrable(ProyectoExpediente $expediente): array
    {
        $ids = fn (string $relacion, string $tabla) => $expediente->{$relacion}()
            ->orderBy("{$tabla}.id")->pluck("{$tabla}.id")->map(fn ($id) => (int) $id)->all();

        return [
            'plantillas' => $this->plantillasRegistrables($expediente),
            'fase_sgr' => (int) $expediente->fase_sgr,
            'sector_ids' => $ids('sectores', 'requisitos_sectores'),
            'tipo_ids' => $ids('tipos', 'requisitos_tipos'),
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (15 de 15):

```php
        ];
    }

    private function guardarSeleccion(ProyectoExpediente $expediente, array $seleccion): void
    {
        $expediente->sectores()->sync($seleccion['sector_ids']);
```

por:

```php
        ];
    }

    /** @return list<array{plantilla_id: int, catalogo_version_id: int, es_principal: bool}> */
    private function plantillasRegistrables(ProyectoExpediente $expediente): array
    {
        return $this->plantillas->versionesDe($expediente)
            ->map(fn (CatalogoVersion $v) => [
                'plantilla_id' => $v->plantilla_id,
                'catalogo_version_id' => $v->id,
                'es_principal' => (bool) $v->pivot->es_principal,
            ])
            ->values()
            ->all();
    }

    /** La fase solo se pide si el expediente tiene una plantilla con motor sgr. */
    private function exigirFaseSgr(array $datos, bool $tieneSgr): void
    {
        if ($tieneSgr && ! isset($datos['fase_sgr'])) {
            throw ValidationException::withMessages(['fase_sgr' => 'Elige la fase SGR del expediente.']);
        }
    }

    private function guardarSeleccion(ProyectoExpediente $expediente, array $seleccion): void
    {
        $expediente->sectores()->sync($seleccion['sector_ids']);
```

En `app/Services/Viabilizacion/PlantillasExpediente.php`, reemplazar (1 de 1):

```php
            ->all();
    }

    /**
     * Versiones de plantilla del expediente, con su plantilla: la principal primero y luego
     * por plantilla. Es el orden en que se arma y se muestra el checklist.
```

por:

```php
            ->all();
    }

    /**
     * Selección con que nace un expediente si el pedido no trae plantillas: la plantilla
     * compatible con motor sgr como principal (la de clave sgr antes que una copia), o ninguna.
     *
     * @return array{plantilla_ids: list<int>, principal_id: ?int}
     */
    public function preseleccion(Proyecto $proyecto): array
    {
        $sgr = PlantillaChecklist::query()
            ->whereIn('id', $this->idsCompatibles($proyecto))
            ->where('motor', PlantillaChecklist::MOTOR_SGR)
            ->get()
            ->sortBy(fn (PlantillaChecklist $p) => [$p->clave === PlantillaChecklist::CLAVE_SGR ? 0 : 1, $p->id])
            ->first();

        return ['plantilla_ids' => $sgr ? [$sgr->id] : [], 'principal_id' => $sgr?->id];
    }

    /** Si alguna de las versiones es de una plantilla con motor sgr. */
    public function tieneSgr(Collection $versiones): bool
    {
        return $versiones->contains(fn (CatalogoVersion $v) => $v->plantilla->motor === PlantillaChecklist::MOTOR_SGR);
    }

    /**
     * Versiones de plantilla del expediente, con su plantilla: la principal primero y luego
     * por plantilla. Es el orden en que se arma y se muestra el checklist.
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Viabilizacion`
Expected: pasan todas.

Run: `php artisan test`
Expected: pasan todas, 483.

- [ ] **Paso 5: Commit**

```bash
git add tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php app/Http/Controllers/ExpedienteController.php app/Services/Viabilizacion/ExpedienteService.php app/Services/Viabilizacion/PlantillasExpediente.php tests/Feature/Viabilizacion/CatalogoRequisitosApiTest.php tests/Feature/Viabilizacion/ExpedienteApiTest.php tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php tests/Feature/Viabilizacion/HistorialRequisitoApiTest.php tests/Feature/Viabilizacion/NoAplicaRequisitoApiTest.php tests/Feature/Viabilizacion/PlantillasVersionesTest.php tests/Feature/Viabilizacion/SeguimientoRequisitoApiTest.php
git commit -m "feat: create, configure and present expedientes with their templates"
```

---

### Tarea 6: "No aplica", "Aplica" y aprobación según los orígenes

**Files:**
- Test (create): `tests/Feature/Viabilizacion/NoAplicaOrigenesApiTest.php`
- Modify: `app/Http/Controllers/NoAplicaRequisitoController.php`
- Modify: `app/Models/ProyectoRequisito.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `app/Services/Viabilizacion/HistorialChecklist.php`
- Modify: `app/Services/Viabilizacion/NoAplicaRequisito.php`
- Modify: `routes/viabilizacion.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Viabilizacion/NoAplicaOrigenesApiTest.php`:

```php
<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** "No aplica", "por definir" y aprobación de una fila según sus orígenes. */
class NoAplicaOrigenesApiTest extends TestCase
{
    use RefreshDatabase;

    private ExpedienteService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->servicio = app(ExpedienteService::class);
    }

    /** @param array<string, array{0: ?string, 1?: string, 2?: bool}> $requisitos texto => [codigo_comun, caracter, requiere_aprobacion] */
    private function plantilla(string $nombre, array $requisitos): PlantillaChecklist
    {
        $plantilla = PlantillaChecklist::create([
            'nombre' => $nombre, 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'activo' => true,
            'todas_fuentes' => true, 'todos_sectores' => true,
        ]);
        $version = $plantilla->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        $orden = 0;
        foreach ($requisitos as $texto => $r) {
            $orden++;
            RequisitoCatalogo::create([
                'catalogo_version_id' => $version->id, 'clave' => "r{$orden}", 'texto' => $texto, 'orden' => $orden,
                'codigo_comun' => $r[0], 'caracter' => $r[1] ?? RequisitoCatalogo::OBLIGATORIO,
                'requiere_aprobacion_no_aplica' => $r[2] ?? false,
            ]);
        }

        return $plantilla;
    }

    /** Expediente confirmado con las plantillas dadas; la primera es la principal. */
    private function expediente(PlantillaChecklist ...$plantillas): ProyectoExpediente
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', 'recursos_propios')->value('id')]);
        $seleccion = ['plantilla_ids' => array_map(fn ($p) => $p->id, $plantillas), 'principal_id' => $plantillas[0]->id];
        $expediente = $this->servicio->crear($proyecto, $seleccion, null);

        return $this->servicio->confirmarConfiguracion($expediente, $seleccion);
    }

    private function fila(ProyectoExpediente $expediente, string $texto): ProyectoRequisito
    {
        return $expediente->requisitos()->whereHas('requisito', fn ($q) => $q->where('texto', $texto))->sole();
    }

    private function propio(ProyectoExpediente $expediente): ProyectoRequisito
    {
        return $expediente->requisitos()->create([
            'requisito_catalogo_id' => null, 'aplica' => true, 'orden' => 900_000_000,
            'titulo_propio' => 'Acta de socialización',
        ]);
    }

    private function como(Rol|User $quien = Rol::Gestor): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function url(ProyectoRequisito $fila, string $resto): string
    {
        return "/GestPro/expedientes/{$fila->expediente_id}/requisitos/{$fila->id}{$resto}";
    }

    private function marcar(ProyectoRequisito $fila)
    {
        return $this->como()->postJson($this->url($fila, '/no-aplica'), ['justificacion' => 'No hay predios.']);
    }

    private function decidirAplica(ProyectoRequisito $fila, Rol $rol = Rol::Gestor)
    {
        return $this->como($rol)->postJson($this->url($fila, '/aplica'));
    }

    private function historial(ProyectoRequisito $fila): array
    {
        return DB::table('checklist_historial')->where('entidad_tipo', 'requisito')->where('entidad_id', $fila->id)
            ->orderBy('id')->get()
            ->map(fn ($h) => [$h->evento, json_decode((string) $h->antes, true)['estado'] ?? null, json_decode((string) $h->despues, true)['estado'] ?? null])
            ->all();
    }

    // ------------------------------------------------------------ aprobación

    public function test_la_fila_exige_aprobacion_si_algun_origen_la_exige(): void
    {
        // El ancla (de la principal) no la exige; el otro origen sí.
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => ['planos']]);
        $vias = $this->plantilla('Vías', ['Planos de la obra' => ['planos', 'obligatorio', true]]);
        $fila = $this->fila($this->expediente($agua, $vias), 'Planos firmados');

        $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$fila->expediente_id}")->assertOk()
            ->assertJsonPath('requisitos.0.requiere_aprobacion_no_aplica', true);

        $this->marcar($fila)->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica_pendiente')
            ->assertJsonPath('requisito.requiere_aprobacion_no_aplica', true);
    }

    public function test_sin_ningun_origen_que_la_exija_el_no_aplica_vale_de_inmediato(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => ['planos']]);
        $vias = $this->plantilla('Vías', ['Planos de la obra' => ['planos']]);
        $fila = $this->fila($this->expediente($agua, $vias), 'Planos firmados');

        $this->marcar($fila)->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica')
            ->assertJsonPath('requisito.requiere_aprobacion_no_aplica', false)
            ->assertJsonPath('progreso.total', 0);
    }

    public function test_si_el_origen_que_la_exigia_sale_del_expediente_ya_no_se_exige(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => ['planos']]);
        $vias = $this->plantilla('Vías', ['Planos de la obra' => ['planos', 'obligatorio', true]]);
        $expediente = $this->expediente($agua, $vias);
        $this->servicio->actualizarConfiguracion($expediente, ['plantilla_ids' => [$agua->id]]);

        $this->marcar($this->fila($expediente, 'Planos firmados'))->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica')
            ->assertJsonPath('requisito.origenes', [['plantilla_id' => $agua->id, 'nombre' => 'Obras de agua']]);
    }

    public function test_una_fila_sin_origenes_muestra_la_aprobacion_de_su_ancla(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null, 'obligatorio', true]]);
        $vias = $this->plantilla('Vías', ['Licencia' => [null]]);
        $expediente = $this->expediente($vias, $agua);
        $this->servicio->actualizarConfiguracion($expediente, ['plantilla_ids' => [$vias->id]]);
        $retirada = $expediente->requisitos()->where('aplica', false)->sole();

        $this->assertTrue($retirada->requiereAprobacionNoAplica());
        $this->assertTrue($this->servicio->presentarFila($retirada)['requiere_aprobacion_no_aplica']);
    }

    public function test_en_el_sgr_cada_fila_sigue_pidiendo_aprobacion(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto']);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        $expediente = $this->servicio->crear($proyecto, ['fase_sgr' => 3], null);

        $filas = $this->servicio->presentar($expediente)['requisitos'];

        $this->assertCount(8, $filas);
        $this->assertSame([true], collect($filas)->pluck('requiere_aprobacion_no_aplica')->unique()->values()->all());
    }

    // ------------------------------------------------------------ por definir

    public function test_aplica_decide_una_fila_por_definir_y_queda_en_el_historial(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, 'condicionado']]);
        $fila = $this->fila($this->expediente($agua), 'Permiso de vertimientos');
        $this->assertSame(ProyectoRequisito::POR_DEFINIR, $fila->estado_aplicabilidad);

        $this->decidirAplica($fila)->assertOk()
            ->assertJsonPath('requisito.estado', 'pendiente')
            ->assertJsonPath('requisito.no_aplica', null)
            ->assertJsonPath('progreso.total', 1);

        $this->assertSame([['aplicabilidad_decidida', 'por_definir', 'aplica']], $this->historial($fila));
    }

    public function test_aplica_solo_vale_en_una_fila_por_definir(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null], 'Licencia' => [null]]);
        $expediente = $this->expediente($agua);
        $marcada = $this->fila($expediente, 'Licencia');
        $this->marcar($marcada)->assertOk();

        foreach ([$this->fila($expediente, 'Planos firmados'), $marcada, $this->propio($expediente)] as $fila) {
            $this->decidirAplica($fila)->assertStatus(422)->assertJsonPath('mensaje', 'Este requisito no está por definir.');
        }
        $this->assertSame(ProyectoRequisito::NO_APLICA, $marcada->fresh()->estado_aplicabilidad);
    }

    public function test_aplica_exige_permiso_un_borrador_configurado_y_una_fila_del_expediente(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, 'condicionado']]);
        $expediente = $this->expediente($agua);
        $fila = $this->fila($expediente, 'Permiso de vertimientos');
        $otra = $this->fila($this->expediente($agua), 'Permiso de vertimientos');

        $this->decidirAplica($fila, Rol::Consulta)->assertForbidden();
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$otra->id}/aplica")->assertNotFound();

        $expediente->update(['estado' => ProyectoExpediente::RADICADO]);
        $this->decidirAplica($fila)->assertStatus(422)
            ->assertJsonPath('mensaje', 'El expediente está radicado: su checklist está congelado y no admite cambios.');

        $this->assertSame(ProyectoRequisito::POR_DEFINIR, $fila->fresh()->estado_aplicabilidad);
    }

    // ------------------------------------------------- volver al estado anterior

    public function test_retirar_un_no_aplica_devuelve_la_fila_a_por_definir(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, 'condicionado']]);
        $fila = $this->fila($this->expediente($agua), 'Permiso de vertimientos');
        $this->marcar($fila)->assertOk()->assertJsonPath('requisito.estado', 'no_aplica');

        $this->como()->deleteJson($this->url($fila, '/no-aplica'))->assertOk()
            ->assertJsonPath('requisito.estado', 'por_definir')
            ->assertJsonPath('requisito.no_aplica.justificacion', null);

        $this->assertSame(
            [['no_aplica_marcado', 'por_definir', 'no_aplica'], ['no_aplica_retirado', 'no_aplica', 'por_definir']],
            $this->historial($fila)
        );
    }

    public function test_rechazar_un_no_aplica_devuelve_la_fila_a_por_definir(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, 'condicionado', true]]);
        $fila = $this->fila($this->expediente($agua), 'Permiso de vertimientos');
        $this->marcar($fila)->assertOk()->assertJsonPath('requisito.estado', 'no_aplica_pendiente');

        $this->como()->postJson($this->url($fila, '/no-aplica/rechazar'), ['motivo' => 'Sí hay vertimientos'])->assertOk()
            ->assertJsonPath('requisito.estado', 'por_definir');

        $guardada = $fila->fresh();
        $this->assertNull($guardada->justificacion_no_aplica);
        $this->assertNull($guardada->usuario_no_aplica_id);
    }

    public function test_un_no_aplica_aprobado_y_retirado_vuelve_a_por_definir(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, 'condicionado', true]]);
        $fila = $this->fila($this->expediente($agua), 'Permiso de vertimientos');
        $this->marcar($fila)->assertOk();
        $this->como(Rol::Administrador)->postJson($this->url($fila, '/no-aplica/aprobar'))->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica');

        $this->como()->deleteJson($this->url($fila, '/no-aplica'))->assertOk()
            ->assertJsonPath('requisito.estado', 'por_definir');
        $this->assertNull($fila->fresh()->usuario_aprobo_id);
    }

    public function test_cuenta_el_ultimo_no_aplica_marcado(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, 'condicionado']]);
        $fila = $this->fila($this->expediente($agua), 'Permiso de vertimientos');

        // Por definir → no aplica → por definir → aplica → no aplica → aplica.
        $this->marcar($fila)->assertOk();
        $this->como()->deleteJson($this->url($fila, '/no-aplica'))->assertOk()->assertJsonPath('requisito.estado', 'por_definir');
        $this->decidirAplica($fila)->assertOk();
        $this->marcar($fila)->assertOk();

        $this->como()->deleteJson($this->url($fila, '/no-aplica'))->assertOk()->assertJsonPath('requisito.estado', 'pendiente');
    }

    public function test_sin_registro_del_no_aplica_la_fila_vuelve_a_aplica(): void
    {
        // Datos anteriores al historial: la fila quedó marcada sin evento no_aplica_marcado.
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, 'condicionado']]);
        $fila = $this->fila($this->expediente($agua), 'Permiso de vertimientos');
        $fila->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA, 'justificacion_no_aplica' => 'No hay vertimientos.']);

        $this->como()->deleteJson($this->url($fila, '/no-aplica'))->assertOk()->assertJsonPath('requisito.estado', 'pendiente');
    }

    // ---------------------------------------------------------------- propios

    public function test_un_requisito_propio_no_exige_aprobacion_y_vuelve_a_aplica(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null, 'obligatorio', true]]);
        $propio = $this->propio($this->expediente($agua));

        $this->assertFalse($propio->requiereAprobacionNoAplica());
        $this->marcar($propio)->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica')
            ->assertJsonPath('requisito.requiere_aprobacion_no_aplica', false)
            ->assertJsonPath('requisito.requisito', null)
            ->assertJsonPath('requisito.propio.titulo', 'Acta de socialización')
            ->assertJsonPath('progreso.total', 1);

        $this->como()->deleteJson($this->url($propio, '/no-aplica'))->assertOk()
            ->assertJsonPath('requisito.estado', 'pendiente')
            ->assertJsonPath('progreso.total', 2);
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Viabilizacion/NoAplicaOrigenesApiTest.php`
Expected: FALLAN 12 y pasan 2. La fila todavía no expone `requiere_aprobacion_no_aplica`, `requiereAprobacionNoAplica` no existe (`BadMethodCallException`) y la ruta `/aplica` tampoco.

- [ ] **Paso 3: Implementar**

En `app/Http/Controllers/NoAplicaRequisitoController.php`, reemplazar (1 de 2):

```php
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "No aplica" de un requisito del checklist. Rutas con can:editar-datos. */
class NoAplicaRequisitoController extends Controller
{
    private const MENSAJES = [
```

por:

```php
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "No aplica" y "aplica" de un requisito del checklist. Rutas con can:editar-datos. */
class NoAplicaRequisitoController extends Controller
{
    private const MENSAJES = [
```

En `app/Http/Controllers/NoAplicaRequisitoController.php`, reemplazar (2 de 2):

```php
        return $this->respuesta($expediente, $this->noAplica->retirar($expediente, $requisito, $request->user()));
    }

    public function aprobar(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        return $this->respuesta($expediente, $this->noAplica->aprobar($expediente, $requisito, $request->user()));
```

por:

```php
        return $this->respuesta($expediente, $this->noAplica->retirar($expediente, $requisito, $request->user()));
    }

    public function decidirAplica(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        return $this->respuesta($expediente, $this->noAplica->decidirAplica($expediente, $requisito, $request->user()));
    }

    public function aprobar(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        return $this->respuesta($expediente, $this->noAplica->aprobar($expediente, $requisito, $request->user()));
```

En `app/Models/ProyectoRequisito.php`, reemplazar (1 de 1):

```php
        return $this->requisito_catalogo_id === null;
    }

    public function usuarioCumplido(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_cumplido_id');
```

por:

```php
        return $this->requisito_catalogo_id === null;
    }

    /**
     * Si un "no aplica" de esta fila necesita aprobación: la exige si alguno de sus orígenes
     * la exige. Sin orígenes (fila retirada) decide su ancla; un propio nunca la exige.
     * Es la única definición: la usan el "no aplica" y la presentación de la fila.
     */
    public function requiereAprobacionNoAplica(): bool
    {
        if ($this->esPropio()) {
            return false;
        }

        return $this->origenes->isNotEmpty()
            ? $this->origenes->contains(fn (RequisitoCatalogo $r) => $r->requiere_aprobacion_no_aplica)
            : (bool) $this->requisito?->requiere_aprobacion_no_aplica;
    }

    public function usuarioCumplido(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_cumplido_id');
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 1):

```php
            'usuario_cumplido' => $fila->usuarioCumplido?->name,
            'observacion' => $fila->observacion,
            'soporte' => $fila->soporte_archivo ? ['nombre' => $fila->soporte_nombre] : null,
            'requisito' => $r ? [
                'id' => $r->id,
                'clave' => $r->clave,
```

por:

```php
            'usuario_cumplido' => $fila->usuarioCumplido?->name,
            'observacion' => $fila->observacion,
            'soporte' => $fila->soporte_archivo ? ['nombre' => $fila->soporte_nombre] : null,
            // La de la fila, que reúne todos sus orígenes; la de `requisito` es solo la del mostrado.
            'requiere_aprobacion_no_aplica' => $fila->requiereAprobacionNoAplica(),
            'requisito' => $r ? [
                'id' => $r->id,
                'clave' => $r->clave,
```

En `app/Services/Viabilizacion/HistorialChecklist.php`, reemplazar (1 de 1):

```php
            ->all();
    }

    /** @return list<string> rutas de los soportes conservados en el historial del expediente */
    public function archivosDe(ProyectoExpediente $expediente): array
    {
```

por:

```php
            ->all();
    }

    /**
     * Estado de aplicabilidad que tenía la fila cuando se marcó su último "no aplica", o null
     * si no hay registro.
     */
    public function estadoAlMarcarNoAplica(ProyectoRequisito $fila): ?string
    {
        $entrada = ChecklistHistorial::query()
            ->where('entidad_tipo', self::REQUISITO)
            ->where('entidad_id', $fila->id)
            ->where('evento', 'no_aplica_marcado')
            ->orderByDesc('id')
            ->first();

        return $entrada?->antes['estado'] ?? null;
    }

    /** @return list<string> rutas de los soportes conservados en el historial del expediente */
    public function archivosDe(ProyectoExpediente $expediente): array
    {
```

En `app/Services/Viabilizacion/NoAplicaRequisito.php`, reemplazar (1 de 6):

```php
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
```

por:

```php
use Illuminate\Support\Facades\DB;

/**
 * Aplicabilidad de una fila del checklist, que decide una persona.
 *
 * - "No aplica": lo marca una persona con justificación y, si la fila lo pide (algún origen
 *   lo exige; un propio nunca), otra lo aprueba. Mientras espera aprobación cuenta como pendiente.
 * - Retirarlo o rechazarlo devuelve la fila al estado que tenía al marcarlo: por definir o aplica.
 * - "Aplica": decide una fila por definir.
 */
final class NoAplicaRequisito
{
    private const SIN_NO_APLICA = [
        'justificacion_no_aplica' => null,
        'usuario_no_aplica_id' => null,
        'usuario_aprobo_id' => null,
```

En `app/Services/Viabilizacion/NoAplicaRequisito.php`, reemplazar (2 de 6):

```php
                throw new ReglaNegocioException('El requisito ya está marcado como no aplica.');
            }

            $estado = $actual->requisito->requiere_aprobacion_no_aplica
                ? ProyectoRequisito::NO_APLICA_PENDIENTE
                : ProyectoRequisito::NO_APLICA;
```

por:

```php
                throw new ReglaNegocioException('El requisito ya está marcado como no aplica.');
            }

            $estado = $actual->requiereAprobacionNoAplica()
                ? ProyectoRequisito::NO_APLICA_PENDIENTE
                : ProyectoRequisito::NO_APLICA;
```

En `app/Services/Viabilizacion/NoAplicaRequisito.php`, reemplazar (3 de 6):

```php
        });
    }

    /** Quien se equivocó lo deshace: el requisito vuelve a aplicar. */
    public function retirar(ProyectoExpediente $expediente, ProyectoRequisito $fila, User $usuario): ProyectoRequisito
    {
        return $this->guardar($expediente, $fila, $usuario, function (ProyectoRequisito $actual) {
```

por:

```php
        });
    }

    /** Quien se equivocó lo deshace: la fila vuelve al estado que tenía al marcarlo. */
    public function retirar(ProyectoExpediente $expediente, ProyectoRequisito $fila, User $usuario): ProyectoRequisito
    {
        return $this->guardar($expediente, $fila, $usuario, function (ProyectoRequisito $actual) {
```

En `app/Services/Viabilizacion/NoAplicaRequisito.php`, reemplazar (4 de 6):

```php
                throw new ReglaNegocioException('El requisito no está marcado como no aplica.');
            }

            return ['no_aplica_retirado', self::SIN_NO_APLICA];
        });
    }
```

por:

```php
                throw new ReglaNegocioException('El requisito no está marcado como no aplica.');
            }

            return ['no_aplica_retirado', $this->sinNoAplica($actual)];
        });
    }

    /** Una fila por definir pasa a aplicar. */
    public function decidirAplica(ProyectoExpediente $expediente, ProyectoRequisito $fila, User $usuario): ProyectoRequisito
    {
        return $this->guardar($expediente, $fila, $usuario, function (ProyectoRequisito $actual) {
            if ($actual->estado_aplicabilidad !== ProyectoRequisito::POR_DEFINIR) {
                throw new ReglaNegocioException('Este requisito no está por definir.');
            }

            return ['aplicabilidad_decidida', ['estado_aplicabilidad' => ProyectoRequisito::APLICA, ...self::SIN_NO_APLICA]];
        });
    }
```

En `app/Services/Viabilizacion/NoAplicaRequisito.php`, reemplazar (5 de 6):

```php
        return $this->guardar($expediente, $fila, $usuario, function (ProyectoRequisito $actual) use ($usuario, $motivo) {
            $this->exigirRevisor($actual, $usuario);

            return ['no_aplica_rechazado', self::SIN_NO_APLICA, ['motivo' => $motivo !== '' ? $motivo : null]];
        });
    }

    private function exigirRevisor(ProyectoRequisito $fila, User $usuario): void
    {
        if ($fila->estado_aplicabilidad !== ProyectoRequisito::NO_APLICA_PENDIENTE) {
```

por:

```php
        return $this->guardar($expediente, $fila, $usuario, function (ProyectoRequisito $actual) use ($usuario, $motivo) {
            $this->exigirRevisor($actual, $usuario);

            return ['no_aplica_rechazado', $this->sinNoAplica($actual), ['motivo' => $motivo !== '' ? $motivo : null]];
        });
    }

    /**
     * Cambios que quitan el "no aplica": la fila vuelve al estado que tenía al marcarlo
     * (antes.estado del último no_aplica_marcado). Sin ese registro, aplica.
     */
    private function sinNoAplica(ProyectoRequisito $fila): array
    {
        $previo = $this->historial->estadoAlMarcarNoAplica($fila) === ProyectoRequisito::POR_DEFINIR
            ? ProyectoRequisito::POR_DEFINIR
            : ProyectoRequisito::APLICA;

        return ['estado_aplicabilidad' => $previo, ...self::SIN_NO_APLICA];
    }

    private function exigirRevisor(ProyectoRequisito $fila, User $usuario): void
    {
        if ($fila->estado_aplicabilidad !== ProyectoRequisito::NO_APLICA_PENDIENTE) {
```

En `app/Services/Viabilizacion/NoAplicaRequisito.php`, reemplazar (6 de 6):

```php
        $this->expedientes->exigirEditable($expediente, $fila);

        DB::transaction(function () use ($expediente, $fila, $usuario, $decidir) {
            $actual = ProyectoRequisito::query()->whereKey($fila->id)->lockForUpdate()->with('requisito')->firstOrFail();
            $this->expedientes->exigirQueAplique($actual);
            [$evento, $cambios, $extra] = $decidir($actual) + [2 => []];
```

por:

```php
        $this->expedientes->exigirEditable($expediente, $fila);

        DB::transaction(function () use ($expediente, $fila, $usuario, $decidir) {
            $actual = ProyectoRequisito::query()->whereKey($fila->id)->lockForUpdate()->with(['requisito', 'origenes'])->firstOrFail();
            $this->expedientes->exigirQueAplique($actual);
            [$evento, $cambios, $extra] = $decidir($actual) + [2 => []];
```

En `routes/viabilizacion.php`, reemplazar (1 de 1):

```php
        Route::delete('/expedientes/{expediente}/requisitos/{requisito}/soporte', [ExpedienteController::class, 'eliminarSoporte'])
            ->name('expedientes.requisitos.soporte.eliminar');

        Route::post('/expedientes/{expediente}/requisitos/{requisito}/no-aplica', [NoAplicaRequisitoController::class, 'marcar'])
            ->name('expedientes.requisitos.no-aplica.marcar');
        Route::delete('/expedientes/{expediente}/requisitos/{requisito}/no-aplica', [NoAplicaRequisitoController::class, 'retirar'])
```

por:

```php
        Route::delete('/expedientes/{expediente}/requisitos/{requisito}/soporte', [ExpedienteController::class, 'eliminarSoporte'])
            ->name('expedientes.requisitos.soporte.eliminar');

        Route::post('/expedientes/{expediente}/requisitos/{requisito}/aplica', [NoAplicaRequisitoController::class, 'decidirAplica'])
            ->name('expedientes.requisitos.aplica');
        Route::post('/expedientes/{expediente}/requisitos/{requisito}/no-aplica', [NoAplicaRequisitoController::class, 'marcar'])
            ->name('expedientes.requisitos.no-aplica.marcar');
        Route::delete('/expedientes/{expediente}/requisitos/{requisito}/no-aplica', [NoAplicaRequisitoController::class, 'retirar'])
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Viabilizacion/NoAplicaOrigenesApiTest.php`
Expected: pasan las 14.

Run: `php artisan test`
Expected: pasan todas, 497.

- [ ] **Paso 5: Commit**

```bash
git add tests/Feature/Viabilizacion/NoAplicaOrigenesApiTest.php app/Http/Controllers/NoAplicaRequisitoController.php app/Models/ProyectoRequisito.php app/Services/Viabilizacion/ExpedienteService.php app/Services/Viabilizacion/HistorialChecklist.php app/Services/Viabilizacion/NoAplicaRequisito.php routes/viabilizacion.php
git commit -m "feat: decide undefined requirements and take the not-applicable approval from the origins"
```

---

### Tarea 7: Requisitos propios del proyecto

**Files:**
- Create: `app/Http/Controllers/RequisitoPropioController.php`
- Create: `app/Services/Viabilizacion/RequisitosPropios.php`
- Test (create): `tests/Feature/Viabilizacion/RequisitosPropiosApiTest.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `routes/viabilizacion.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Viabilizacion/RequisitosPropiosApiTest.php`:

```php
<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Requisitos propios del proyecto: se agregan en el checklist, sin plantilla. */
class RequisitosPropiosApiTest extends TestCase
{
    use RefreshDatabase;

    private ExpedienteService $servicio;
    private ProyectoExpediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->servicio = app(ExpedienteService::class);

        // Fase III sin sectores: 8 requisitos SGR.
        $this->expediente = $this->expedienteSgr();
        $this->servicio->confirmarConfiguracion($this->expediente, ['fase_sgr' => 3]);
    }

    private function expedienteSgr(): ProyectoExpediente
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        return $this->servicio->crear($proyecto, ['fase_sgr' => 3], null);
    }

    private function como(Rol|User $quien = Rol::Gestor): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function url(?ProyectoRequisito $fila = null, ?ProyectoExpediente $expediente = null): string
    {
        $expediente ??= $this->expediente;

        return "/GestPro/expedientes/{$expediente->id}/requisitos-propios" . ($fila ? "/{$fila->id}" : '');
    }

    private function agregar(array $datos = [])
    {
        return $this->como()->postJson($this->url(), [
            'titulo' => 'Acta de socialización con la comunidad', ...$datos,
        ]);
    }

    private function propio(array $datos = []): ProyectoRequisito
    {
        return ProyectoRequisito::findOrFail($this->agregar($datos)->assertCreated()->json('requisito.id'));
    }

    private function eventos(ProyectoRequisito $fila): array
    {
        return DB::table('checklist_historial')->where('entidad_tipo', 'requisito')->where('entidad_id', $fila->id)
            ->orderBy('id')->get()
            ->map(fn ($h) => [$h->evento, json_decode((string) $h->antes, true), json_decode((string) $h->despues, true)])
            ->all();
    }

    private function filaSgr(): ProyectoRequisito
    {
        return $this->expediente->requisitos()->whereNotNull('requisito_catalogo_id')->where('aplica', true)->orderBy('orden')->first();
    }

    // ------------------------------------------------------------------ crear

    public function test_agrega_un_requisito_propio_al_final_del_checklist(): void
    {
        $r = $this->agregar([
            'titulo' => '  Acta de socialización con la comunidad  ',
            'descripcion' => 'Firmada por la junta de acción comunal',
            'categoria' => 'social',
        ])->assertCreated();

        $r->assertJsonPath('requisito.requisito', null)
            ->assertJsonPath('requisito.propio', [
                'titulo' => 'Acta de socialización con la comunidad',
                'descripcion' => 'Firmada por la junta de acción comunal',
                'categoria' => 'social',
            ])
            ->assertJsonPath('requisito.categoria', 'social')
            ->assertJsonPath('requisito.caracter', 'obligatorio')
            ->assertJsonPath('requisito.estado', 'pendiente')
            ->assertJsonPath('requisito.cuenta', true)
            ->assertJsonPath('requisito.plantilla', null)
            ->assertJsonPath('requisito.origenes', [])
            ->assertJsonPath('progreso.total', 9);

        $fila = ProyectoRequisito::findOrFail($r->json('requisito.id'));
        $this->assertTrue($fila->esPropio());
        $this->assertTrue($fila->aplica);
        $this->assertSame(900_000_000 + $fila->id, $fila->orden);

        $requisitos = $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$this->expediente->id}")->json('requisitos');
        $this->assertCount(9, $requisitos);
        $this->assertSame($fila->id, end($requisitos)['id']);
    }

    public function test_un_propio_opcional_no_cuenta_y_sin_descripcion_ni_categoria_quedan_vacias(): void
    {
        $this->agregar(['caracter' => 'opcional'])->assertCreated()
            ->assertJsonPath('requisito.caracter', 'opcional')
            ->assertJsonPath('requisito.cuenta', false)
            ->assertJsonPath('requisito.propio.descripcion', null)
            ->assertJsonPath('requisito.propio.categoria', null)
            ->assertJsonPath('requisito.categoria', null)
            ->assertJsonPath('progreso.total', 8);
    }

    public function test_agregar_queda_en_el_historial(): void
    {
        $fila = $this->propio(['categoria' => 'juridico']);

        $this->assertSame([['requisito_propio_agregado', null, [
            'titulo' => 'Acta de socialización con la comunidad', 'descripcion' => null, 'categoria' => 'juridico', 'caracter' => 'obligatorio',
        ]]], $this->eventos($fila));
    }

    public function test_valida_los_campos_con_mensajes_en_espanol(): void
    {
        $this->como()->postJson($this->url(), ['titulo' => '   '])->assertStatus(422)
            ->assertJsonValidationErrors(['titulo' => 'Escribe el título del requisito.']);
        $this->agregar(['titulo' => str_repeat('a', 501)])->assertStatus(422)
            ->assertJsonValidationErrors(['titulo' => 'El título admite hasta 500 caracteres.']);
        $this->agregar(['descripcion' => str_repeat('a', 2001)])->assertStatus(422)
            ->assertJsonValidationErrors(['descripcion' => 'La descripción admite hasta 2000 caracteres.']);
        $this->agregar(['categoria' => 'deportivo'])->assertStatus(422)
            ->assertJsonValidationErrors(['categoria' => 'Elige una categoría de la lista.']);
        $this->agregar(['caracter' => 'condicionado'])->assertStatus(422)
            ->assertJsonValidationErrors(['caracter' => 'El carácter de un requisito propio es obligatorio u opcional.']);
        $this->agregar(['titulo' => ['a']])->assertStatus(422)
            ->assertJsonValidationErrors(['titulo' => 'Revisa el título: debe ser un texto.']);

        $this->assertSame(0, $this->expediente->requisitos()->whereNull('requisito_catalogo_id')->count());
    }

    public function test_solo_se_agrega_en_un_borrador_configurado(): void
    {
        $this->expediente->update(['estado' => ProyectoExpediente::RADICADO]);
        $this->agregar()->assertStatus(422)
            ->assertJsonPath('mensaje', 'El expediente está radicado: su checklist está congelado y no admite cambios.');

        $otro = $this->expedienteSgr(); // sin confirmar
        $this->como()->postJson($this->url(null, $otro), ['titulo' => 'Acta'])->assertStatus(422)
            ->assertJsonPath('mensaje', 'Confirma la configuración del expediente antes de chequear sus requisitos.');

        $this->assertSame(0, ProyectoRequisito::whereNull('requisito_catalogo_id')->count());
    }

    public function test_consulta_no_puede_agregar_editar_ni_eliminar(): void
    {
        $fila = $this->propio();

        $this->como(Rol::Consulta)->postJson($this->url(), ['titulo' => 'Acta'])->assertForbidden();
        $this->como(Rol::Consulta)->putJson($this->url($fila), ['titulo' => 'Otro'])->assertForbidden();
        $this->como(Rol::Consulta)->deleteJson($this->url($fila))->assertForbidden();
    }

    // ----------------------------------------------------------------- editar

    public function test_edita_un_propio_y_registra_solo_lo_que_cambio(): void
    {
        $fila = $this->propio(['descripcion' => 'Con la junta', 'categoria' => 'social']);

        $this->como()->putJson($this->url($fila), ['titulo' => 'Acta de socialización firmada', 'categoria' => null, 'caracter' => 'opcional'])
            ->assertOk()
            ->assertJsonPath('requisito.propio', ['titulo' => 'Acta de socialización firmada', 'descripcion' => 'Con la junta', 'categoria' => null])
            ->assertJsonPath('requisito.caracter', 'opcional')
            ->assertJsonPath('progreso.total', 8);

        $this->assertSame(['requisito_propio_editado', [
            'titulo' => 'Acta de socialización con la comunidad', 'categoria' => 'social', 'caracter' => 'obligatorio',
        ], [
            'titulo' => 'Acta de socialización firmada', 'categoria' => null, 'caracter' => 'opcional',
        ]], $this->eventos($fila)[1]);
    }

    public function test_editar_sin_cambios_no_registra_nada(): void
    {
        $fila = $this->propio();

        $this->como()->putJson($this->url($fila), ['titulo' => 'Acta de socialización con la comunidad'])->assertOk();

        $this->assertCount(1, $this->eventos($fila));
    }

    public function test_editar_conserva_el_cumplimiento_y_el_orden(): void
    {
        $fila = $this->propio();
        $this->servicio->marcar($this->expediente, $fila, ['cumplido' => true, 'observacion' => 'Entregada'], null);

        $this->como()->putJson($this->url($fila), ['titulo' => 'Acta firmada'])->assertOk()
            ->assertJsonPath('requisito.cumplido', true)
            ->assertJsonPath('requisito.observacion', 'Entregada');
        $this->assertSame(900_000_000 + $fila->id, $fila->fresh()->orden);
    }

    public function test_un_requisito_de_plantilla_no_se_edita_ni_se_elimina_como_propio(): void
    {
        $fila = $this->filaSgr();

        $this->como()->putJson($this->url($fila), ['titulo' => 'Otro'])->assertStatus(422)
            ->assertJsonPath('mensaje', 'Este requisito viene de una plantilla: solo se editan los requisitos propios del proyecto.');
        $this->como()->deleteJson($this->url($fila))->assertStatus(422)
            ->assertJsonPath('mensaje', 'Este requisito viene de una plantilla: solo se eliminan los requisitos propios del proyecto.');

        $this->assertNull($fila->fresh()->titulo_propio);
    }

    public function test_un_propio_de_otro_expediente_no_sirve(): void
    {
        $fila = $this->propio();
        $otro = $this->expedienteSgr();
        $this->servicio->confirmarConfiguracion($otro, ['fase_sgr' => 3]);

        $this->como()->putJson($this->url($fila, $otro), ['titulo' => 'Otro'])->assertNotFound();
        $this->como()->deleteJson($this->url($fila, $otro))->assertNotFound();
    }

    public function test_un_expediente_radicado_no_admite_editar_ni_eliminar(): void
    {
        $fila = $this->propio();
        $this->expediente->update(['estado' => ProyectoExpediente::RADICADO]);

        $this->como()->putJson($this->url($fila), ['titulo' => 'Otro'])->assertStatus(422);
        $this->como()->deleteJson($this->url($fila))->assertStatus(422);
        $this->assertSame('Acta de socialización con la comunidad', $fila->fresh()->titulo_propio);
    }

    // --------------------------------------------------------------- eliminar

    public function test_elimina_un_propio_y_queda_en_el_historial(): void
    {
        $fila = $this->propio(['categoria' => 'social']);

        $this->como()->deleteJson($this->url($fila))->assertOk()
            ->assertJsonPath('mensaje', 'Requisito eliminado.')
            ->assertJsonPath('progreso.total', 8);

        $this->assertNull(ProyectoRequisito::find($fila->id));
        $this->assertSame(['requisito_propio_eliminado', [
            'titulo' => 'Acta de socialización con la comunidad', 'descripcion' => null, 'categoria' => 'social',
            'caracter' => 'obligatorio', 'observacion' => null,
        ], null], $this->eventos($fila)[1]);
        $this->assertSame($this->expediente->id, (int) DB::table('checklist_historial')->where('evento', 'requisito_propio_eliminado')->value('expediente_id'));
    }

    public function test_no_se_elimina_un_propio_cumplido_ni_con_soporte(): void
    {
        $fila = $this->propio();
        $this->servicio->marcar($this->expediente, $fila, ['cumplido' => true], null);
        $this->como()->deleteJson($this->url($fila))->assertStatus(422)
            ->assertJsonPath('mensaje', 'El requisito está cumplido: desmárcalo antes de eliminarlo.');

        $this->servicio->marcar($this->expediente, $fila, ['cumplido' => false], null);
        $this->servicio->guardarSoporte($this->expediente, $fila, UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'));
        $this->como()->deleteJson($this->url($fila))->assertStatus(422)
            ->assertJsonPath('mensaje', 'El requisito tiene un soporte: quítalo antes de eliminarlo.');

        $this->assertNotNull(ProyectoRequisito::find($fila->id));
    }

    // ---------------------------------------------- trabajo diario con propios

    public function test_un_propio_se_cumple_con_soporte_responsable_e_historial(): void
    {
        $fila = $this->propio();
        $url = "/GestPro/expedientes/{$this->expediente->id}/requisitos/{$fila->id}";

        $this->como()->patchJson($url, ['cumplido' => true, 'observacion' => 'Entregada'])->assertOk()
            ->assertJsonPath('requisito.estado', 'cumplido')
            ->assertJsonPath('progreso.cumplidos', 1);
        $this->como()->post("{$url}/soporte", ['archivo' => UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf')])->assertOk()
            ->assertJsonPath('requisito.soporte.nombre', 'acta.pdf');
        $this->como(Rol::Consulta)->get("{$url}/soporte")->assertOk();
        $this->como()->deleteJson("{$url}/soporte")->assertOk()->assertJsonPath('requisito.soporte', null);

        $eventos = collect($this->como(Rol::Consulta)->getJson("{$url}/historial")->assertOk()->json())->pluck('evento')->all();
        $this->assertSame(['soporte_eliminado', 'soporte_subido', 'observacion_cambiada', 'cumplido_marcado', 'requisito_propio_agregado'], $eventos);
    }

    public function test_un_propio_admite_no_aplica_sin_aprobacion(): void
    {
        $fila = $this->propio();
        $url = "/GestPro/expedientes/{$this->expediente->id}/requisitos/{$fila->id}/no-aplica";

        $this->como()->postJson($url, ['justificacion' => 'Ya se socializó en otro proyecto.'])->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica')
            ->assertJsonPath('progreso.total', 8);
        $this->como()->deleteJson($url)->assertOk()->assertJsonPath('requisito.estado', 'pendiente');
    }

    public function test_un_propio_pendiente_impide_radicar_y_cumplido_lo_permite(): void
    {
        $fila = $this->propio();
        $this->expediente->requisitos()->whereNotNull('requisito_catalogo_id')->where('aplica', true)->update(['cumplido' => true]);

        $this->como()->postJson("/GestPro/expedientes/{$this->expediente->id}/radicar")->assertStatus(422)
            ->assertJsonPath('mensaje', 'No se puede radicar: falta 1 de 9 requisitos por cumplir.');

        $this->servicio->marcar($this->expediente, $fila, ['cumplido' => true], null);
        $this->como()->postJson("/GestPro/expedientes/{$this->expediente->id}/radicar")->assertOk()
            ->assertJsonPath('estado', 'radicado')
            ->assertJsonPath('progreso', ['total' => 9, 'cumplidos' => 9, 'porcentaje' => 100]);
    }

    public function test_recalcular_el_checklist_no_toca_los_propios(): void
    {
        $fila = $this->propio();
        $this->servicio->marcar($this->expediente, $fila, ['cumplido' => true], null);
        $antes = $fila->fresh()->only(['aplica', 'cumplido', 'orden', 'caracter', 'estado_aplicabilidad']);

        // Cambiar la SGR por otra plantilla retira las filas de la SGR y deja el propio como estaba.
        $agua = PlantillaChecklist::create(['nombre' => 'Obras de agua', 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'activo' => true, 'todas_fuentes' => true, 'todos_sectores' => true]);
        $version = $agua->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        RequisitoCatalogo::create(['catalogo_version_id' => $version->id, 'clave' => 'r1', 'texto' => 'Planos', 'orden' => 1]);
        $this->servicio->actualizarConfiguracion($this->expediente, ['plantilla_ids' => [$agua->id]]);

        $this->assertSame($antes, $fila->fresh()->only(['aplica', 'cumplido', 'orden', 'caracter', 'estado_aplicabilidad']));
        $this->assertSame(0, DB::table('proyecto_requisito_origenes')->where('proyecto_requisito_id', $fila->id)->count());
        $this->assertSame(['total' => 2, 'cumplidos' => 1, 'porcentaje' => 50], $this->servicio->progresoDe($this->expediente));
    }

    public function test_el_resumen_del_tablero_cuenta_los_propios(): void
    {
        $this->propio();
        $this->propio(['caracter' => 'opcional']);

        $resumen = collect($this->como(Rol::Consulta)->getJson('/GestPro/expedientes')->assertOk()->json())
            ->firstWhere('expediente_id', $this->expediente->id);
        $this->assertSame(9, $resumen['progreso']['total']);
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Viabilizacion/RequisitosPropiosApiTest.php`
Expected: FALLAN las 19: las rutas `/requisitos-propios` no existen y responden 405.

- [ ] **Paso 3: Implementar**

Crear `app/Http/Controllers/RequisitoPropioController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\RequisitosPropios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Requisitos propios del checklist de un expediente. Rutas con can:editar-datos. */
class RequisitoPropioController extends Controller
{
    private const MENSAJES = [
        'required' => 'Completa :attribute.',
        'max' => 'En :attribute caben hasta :max caracteres.',
        'string' => 'Revisa :attribute: debe ser un texto.',
        'in' => 'Revisa :attribute: no es un valor válido.',
        'titulo.required' => 'Escribe el título del requisito.',
        'titulo.max' => 'El título admite hasta 500 caracteres.',
        'descripcion.max' => 'La descripción admite hasta 2000 caracteres.',
        'categoria.in' => 'Elige una categoría de la lista.',
        'caracter.in' => 'El carácter de un requisito propio es obligatorio u opcional.',
    ];

    private const ATRIBUTOS = [
        'titulo' => 'el título',
        'descripcion' => 'la descripción',
        'categoria' => 'la categoría',
        'caracter' => 'el carácter',
    ];

    public function __construct(
        private readonly RequisitosPropios $propios,
        private readonly ExpedienteService $expedientes,
    ) {
    }

    public function store(Request $request, ProyectoExpediente $expediente): JsonResponse
    {
        $fila = $this->propios->crear($expediente, $this->validar($request), $request->user());

        return $this->respuesta($expediente, $fila, 201);
    }

    public function update(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        $fila = $this->propios->editar($expediente, $requisito, $this->validar($request), $request->user());

        return $this->respuesta($expediente, $fila);
    }

    public function destroy(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        $this->propios->eliminar($expediente, $requisito, $request->user());

        return response()->json([
            'mensaje' => 'Requisito eliminado.',
            'progreso' => $this->expedientes->progresoDe($expediente),
        ]);
    }

    /** Al editar, los campos que no vienen en el pedido no cambian; el título siempre se envía. */
    private function validar(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:500'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'categoria' => ['sometimes', 'nullable', Rule::in(RequisitoCatalogo::CATEGORIAS)],
            'caracter' => ['sometimes', Rule::in(RequisitosPropios::CARACTERES)],
        ], self::MENSAJES, self::ATRIBUTOS);
    }

    private function respuesta(ProyectoExpediente $expediente, ProyectoRequisito $fila, int $estado = 200): JsonResponse
    {
        return response()->json([
            'requisito' => $this->expedientes->presentarFila($fila->refresh()),
            'progreso' => $this->expedientes->progresoDe($expediente),
        ], $estado);
    }
}
```

Crear `app/Services/Viabilizacion/RequisitosPropios.php`:

```php
<?php

namespace App\Services\Viabilizacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Requisitos propios del proyecto: filas del checklist sin plantilla, con sus textos en la fila.
 *
 * Reglas:
 * - Se agregan, editan y eliminan solo en un borrador con la configuración confirmada.
 * - Aplican siempre y `sincronizar` no los toca; tienen el mismo seguimiento que los demás.
 * - Su carácter es obligatorio u opcional (por defecto, obligatorio). Nunca piden aprobación
 *   del "no aplica".
 * - Van al final del checklist, en el orden en que se agregaron.
 * - Solo se elimina uno que no esté cumplido ni tenga soporte.
 * - Todo queda en el historial de la fila.
 */
final class RequisitosPropios
{
    public const CARACTERES = [RequisitoCatalogo::OBLIGATORIO, RequisitoCatalogo::OPCIONAL];

    // Después de cualquier plantilla: ExpedienteService ordena las suyas de 10_000_000 en 10_000_000.
    private const ORDEN_BASE = 900_000_000;

    /** Campo de la API => columna de la fila. */
    private const CAMPOS = [
        'titulo' => 'titulo_propio',
        'descripcion' => 'descripcion_propia',
        'categoria' => 'categoria_propia',
        'caracter' => 'caracter',
    ];

    public function __construct(
        private readonly ExpedienteService $expedientes,
        private readonly HistorialChecklist $historial,
    ) {
    }

    /** @param array{titulo: string, descripcion?: ?string, categoria?: ?string, caracter?: string} $datos */
    public function crear(ProyectoExpediente $expediente, array $datos, ?User $usuario): ProyectoRequisito
    {
        $this->expedientes->exigirChecklistEditable($expediente);
        $valores = $this->valores([
            'descripcion' => null, 'categoria' => null, 'caracter' => RequisitoCatalogo::OBLIGATORIO, ...$datos,
        ]);

        return DB::transaction(function () use ($expediente, $valores, $usuario) {
            $fila = $expediente->requisitos()->create([
                ...$this->columnas($valores),
                'requisito_catalogo_id' => null,
                'aplica' => true,
                'estado_aplicabilidad' => ProyectoRequisito::APLICA,
            ]);
            $fila->update(['orden' => self::ORDEN_BASE + $fila->id]);
            $this->historial->registrarFila($fila, 'requisito_propio_agregado', [], $valores, $usuario);
            $expediente->touch();

            return $fila;
        });
    }

    /**
     * Cambia los campos que trae el pedido; los demás no cambian.
     *
     * @param array{titulo: string, descripcion?: ?string, categoria?: ?string, caracter?: string} $datos
     */
    public function editar(ProyectoExpediente $expediente, ProyectoRequisito $fila, array $datos, ?User $usuario): ProyectoRequisito
    {
        $this->exigirPropio($expediente, $fila, 'solo se editan los requisitos propios del proyecto.');
        $valores = $this->valores($datos);

        DB::transaction(function () use ($expediente, $fila, $valores, $usuario) {
            // Bloquea la fila y compara con su estado actual: dos ediciones no se pisan en el historial.
            $actual = ProyectoRequisito::query()->whereKey($fila->id)->lockForUpdate()->firstOrFail();
            $antes = $this->valoresDe($actual);
            $cambios = array_filter($valores, fn ($valor, string $campo) => $valor !== $antes[$campo], ARRAY_FILTER_USE_BOTH);
            if ($cambios === []) {
                return;
            }

            $actual->update($this->columnas($cambios));
            $this->historial->registrarFila($actual, 'requisito_propio_editado', array_intersect_key($antes, $cambios), $cambios, $usuario);
            $expediente->touch();
        });

        return $fila->refresh();
    }

    public function eliminar(ProyectoExpediente $expediente, ProyectoRequisito $fila, ?User $usuario): void
    {
        $this->exigirPropio($expediente, $fila, 'solo se eliminan los requisitos propios del proyecto.');

        DB::transaction(function () use ($expediente, $fila, $usuario) {
            // Bloquea la fila: una marca o una subida simultánea no puede perderse con ella.
            $actual = ProyectoRequisito::query()->whereKey($fila->id)->lockForUpdate()->firstOrFail();
            if ($actual->cumplido) {
                throw new ReglaNegocioException('El requisito está cumplido: desmárcalo antes de eliminarlo.');
            }
            if ($actual->soporte_archivo) {
                throw new ReglaNegocioException('El requisito tiene un soporte: quítalo antes de eliminarlo.');
            }

            // El registro sobrevive a la fila, con sus textos.
            $this->historial->registrarFila($actual, 'requisito_propio_eliminado', [
                ...$this->valoresDe($actual),
                'observacion' => $actual->observacion,
            ], [], $usuario);
            $actual->delete();
            $expediente->touch();
        });
    }

    private function exigirPropio(ProyectoExpediente $expediente, ProyectoRequisito $fila, string $motivo): void
    {
        $this->expedientes->exigirEditable($expediente, $fila);
        if (! $fila->esPropio()) {
            throw new ReglaNegocioException("Este requisito viene de una plantilla: {$motivo}");
        }
    }

    /** Valores del pedido en el orden de CAMPOS, con los textos recortados y los vacíos como null. */
    private function valores(array $datos): array
    {
        $valores = array_intersect_key(array_replace(self::CAMPOS, $datos), $datos, self::CAMPOS);
        foreach (['titulo', 'descripcion'] as $campo) {
            if (array_key_exists($campo, $valores)) {
                $texto = trim((string) $valores[$campo]);
                $valores[$campo] = $texto !== '' ? $texto : null;
            }
        }
        if (array_key_exists('titulo', $valores) && $valores['titulo'] === null) {
            throw new ReglaNegocioException('Escribe el título del requisito.');
        }

        return $valores;
    }

    /** @return array{titulo: ?string, descripcion: ?string, categoria: ?string, caracter: string} */
    private function valoresDe(ProyectoRequisito $fila): array
    {
        return array_map(fn (string $columna) => $fila->{$columna}, self::CAMPOS);
    }

    private function columnas(array $valores): array
    {
        $columnas = [];
        foreach ($valores as $campo => $valor) {
            $columnas[self::CAMPOS[$campo]] = $valor;
        }

        return $columnas;
    }
}
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 1):

```php
        return $responsable->id;
    }

    public function exigirEditable(ProyectoExpediente $expediente, ProyectoRequisito $fila): void
    {
        $this->exigirBorrador($expediente);
        $this->exigirConfigurado($expediente);

        if ($fila->expediente_id !== $expediente->id) {
            throw new ReglaNegocioException('El requisito no pertenece a este expediente.');
```

por:

```php
        return $responsable->id;
    }

    /** El checklist admite cambios: el expediente está en borrador y con la configuración confirmada. */
    public function exigirChecklistEditable(ProyectoExpediente $expediente): void
    {
        $this->exigirBorrador($expediente);
        $this->exigirConfigurado($expediente);
    }

    public function exigirEditable(ProyectoExpediente $expediente, ProyectoRequisito $fila): void
    {
        $this->exigirChecklistEditable($expediente);

        if ($fila->expediente_id !== $expediente->id) {
            throw new ReglaNegocioException('El requisito no pertenece a este expediente.');
```

En `routes/viabilizacion.php`, reemplazar (1 de 2):

```php
use App\Http\Controllers\NoAplicaRequisitoController;
use App\Http\Controllers\PlantillaChecklistController;
use App\Http\Controllers\PlantillasExpedienteController;
use Illuminate\Support\Facades\Route;

// Lectura: cualquier usuario autenticado.
```

por:

```php
use App\Http\Controllers\NoAplicaRequisitoController;
use App\Http\Controllers\PlantillaChecklistController;
use App\Http\Controllers\PlantillasExpedienteController;
use App\Http\Controllers\RequisitoPropioController;
use Illuminate\Support\Facades\Route;

// Lectura: cualquier usuario autenticado.
```

En `routes/viabilizacion.php`, reemplazar (2 de 2):

```php
    Route::delete('/expedientes/{expediente}', [ExpedienteController::class, 'destroy'])
        ->name('expedientes.destroy');

    Route::scopeBindings()->group(function () {
        Route::patch('/expedientes/{expediente}/requisitos/{requisito}', [ExpedienteController::class, 'updateRequisito'])
            ->name('expedientes.requisitos.update');
        Route::post('/expedientes/{expediente}/requisitos/{requisito}/soporte', [ExpedienteController::class, 'subirSoporte'])
```

por:

```php
    Route::delete('/expedientes/{expediente}', [ExpedienteController::class, 'destroy'])
        ->name('expedientes.destroy');

    Route::post('/expedientes/{expediente}/requisitos-propios', [RequisitoPropioController::class, 'store'])
        ->name('expedientes.requisitos-propios.store');

    Route::scopeBindings()->group(function () {
        Route::put('/expedientes/{expediente}/requisitos-propios/{requisito}', [RequisitoPropioController::class, 'update'])
            ->name('expedientes.requisitos-propios.update');
        Route::delete('/expedientes/{expediente}/requisitos-propios/{requisito}', [RequisitoPropioController::class, 'destroy'])
            ->name('expedientes.requisitos-propios.destroy');

        Route::patch('/expedientes/{expediente}/requisitos/{requisito}', [ExpedienteController::class, 'updateRequisito'])
            ->name('expedientes.requisitos.update');
        Route::post('/expedientes/{expediente}/requisitos/{requisito}/soporte', [ExpedienteController::class, 'subirSoporte'])
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Viabilizacion/RequisitosPropiosApiTest.php`
Expected: pasan las 19.

Run: `php artisan test`
Expected: pasan todas, 516.

- [ ] **Paso 5: Commit**

```bash
git add app/Http/Controllers/RequisitoPropioController.php app/Services/Viabilizacion/RequisitosPropios.php tests/Feature/Viabilizacion/RequisitosPropiosApiTest.php app/Services/Viabilizacion/ExpedienteService.php routes/viabilizacion.php
git commit -m "feat: add, edit and remove own requirements of a project"
```

---

### Tarea 8: Expediente automático para toda fuente, resumen y comando

Cambia a propósito pruebas de `ExpedienteAutomaticoTest`: un proyecto en Formulación con solo "Recursos propios" ahora sí recibe expediente, sin plantillas.

**Files:**
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `app/Services/Viabilizacion/PlantillasExpediente.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`
- Test: `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`, reemplazar (1 de 3):

```php

use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\FuenteFinanciacion;
use App\Models\Municipio;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\User;
```

por:

```php

use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\FuenteFinanciacion;
use App\Models\Municipio;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\User;
```

En `tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`, reemplazar (2 de 3):

```php

    public function test_detecta_regalias_por_el_catalogo_y_no_por_el_texto(): void
    {
        $proyecto = $this->proyecto(['nombre' => 'Texto engañoso', 'fuente_financiacion' => 'Sin regalías'], [$this->fuentePropios]);

        $this->assertFalse(app(ExpedienteService::class)->correspondeAutomatico($proyecto));
    }

    public function test_no_se_crea_si_no_corresponde(): void
    {
        $this->guardar(['nombre' => 'En licitacion', 'fase' => $this->licitacion])->assertOk();
        $this->guardar(['nombre' => 'Recursos propios', 'fuenteIds' => [$this->fuentePropios]])->assertOk();
        $this->guardar(['nombre' => 'Por definir', 'fuenteIds' => [], 'financiacionPorDefinir' => true])->assertOk();
        $this->guardar(['nombre' => 'Sin fase', 'fase' => null])->assertOk();

        $this->assertDatabaseCount('proyectos', 4);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }
```

por:

```php

    public function test_detecta_regalias_por_el_catalogo_y_no_por_el_texto(): void
    {
        $proyecto = $this->proyecto(['nombre' => 'Texto engañoso', 'fuente_financiacion' => 'Recursos propios y regalías'], [$this->fuentePropios]);

        $expediente = app(ExpedienteService::class)->crearAutomatico($proyecto, null);

        $this->assertSame(0, $expediente->versionesPlantilla()->count());
    }

    public function test_un_proyecto_con_solo_recursos_propios_nace_con_expediente_sin_plantillas(): void
    {
        // Con sector mapeado a la SGR: sin plantilla SGR no se preselecciona ningún sector.
        $this->guardar(['nombre' => 'Recursos propios', 'fuenteIds' => [$this->fuentePropios], 'sector' => $this->sectorMapeado()])->assertOk();

        $expediente = Proyecto::where('nombre', 'Recursos propios')->firstOrFail()->expedientes()->sole();
        $this->assertNull($expediente->fecha_configuracion);
        $this->assertNull($expediente->catalogo_version_id);
        $this->assertSame(0, $expediente->versionesPlantilla()->count());
        $this->assertSame(0, $expediente->sectores()->count());
        $this->assertSame(0, $expediente->requisitos()->count());

        $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$expediente->id}")->assertOk()
            ->assertJsonPath('plantillas', [])
            ->assertJsonPath('tiene_sgr', false);
    }

    public function test_una_plantilla_compatible_que_no_es_sgr_no_se_preselecciona(): void
    {
        $agua = PlantillaChecklist::create(['nombre' => 'Obras de agua', 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'activo' => true, 'todas_fuentes' => true, 'todos_sectores' => true]);
        $agua->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);

        $sinSgr = app(ExpedienteService::class)->crearAutomatico($this->proyecto([], [$this->fuentePropios]), null);
        $conSgr = app(ExpedienteService::class)->crearAutomatico($this->proyecto([], [$this->fuentePropios, $this->fuenteSgr]), null);

        $this->assertSame([], $sinSgr->versionesPlantilla()->pluck('plantilla_id')->all());
        $this->assertSame([PlantillaChecklist::sgr()->id], $conSgr->versionesPlantilla()->pluck('plantilla_id')->all());
        $this->assertSame(8, $conSgr->requisitos()->where('aplica', true)->count());
    }

    public function test_corresponde_con_al_menos_una_fuente_en_formulacion_y_sin_expediente(): void
    {
        $servicio = app(ExpedienteService::class);
        $conExpediente = $this->proyecto();
        $this->expedienteSinConfigurar($conExpediente);

        $this->assertTrue($servicio->correspondeAutomatico($this->proyecto([], [$this->fuenteSgr])));
        $this->assertTrue($servicio->correspondeAutomatico($this->proyecto([], [$this->fuentePropios])));
        $this->assertFalse($servicio->correspondeAutomatico($this->proyecto(['financiacion_por_definir' => true], [])));
        $this->assertFalse($servicio->correspondeAutomatico($this->proyecto(['fase' => $this->licitacion])));
        $this->assertFalse($servicio->correspondeAutomatico($conExpediente));
    }

    public function test_no_se_crea_si_no_corresponde(): void
    {
        $this->guardar(['nombre' => 'En licitacion', 'fase' => $this->licitacion])->assertOk();
        $this->guardar(['nombre' => 'Por definir', 'fuenteIds' => [], 'financiacionPorDefinir' => true])->assertOk();
        $this->guardar(['nombre' => 'Sin fase', 'fase' => null])->assertOk();

        $this->assertDatabaseCount('proyectos', 3);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }
```

En `tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php`, reemplazar (3 de 3):

```php
    public function test_el_comando_crea_los_que_faltan_y_se_puede_repetir(): void
    {
        $falta = $this->proyecto(['nombre' => 'Falta']);
        $tiene = $this->proyecto(['nombre' => 'Ya tiene']);
        $previo = $this->expedienteSinConfigurar($tiene);
        $this->proyecto(['nombre' => 'Otra fase', 'fase' => $this->licitacion]);
        $this->proyecto(['nombre' => 'Sin regalías'], [$this->fuentePropios]);

        $this->artisan('expedientes:crear-faltantes')->assertSuccessful();

        $this->assertSame(1, $falta->expedientes()->count());
        $this->assertNull($falta->expedientes()->sole()->fecha_configuracion);
        $this->assertSame([$previo->id], $tiene->expedientes()->pluck('id')->all());
        $this->assertDatabaseCount('proyectos_expedientes', 2);

        $this->artisan('expedientes:crear-faltantes')->assertSuccessful();
        $this->assertDatabaseCount('proyectos_expedientes', 2);
    }

    public function test_el_comando_en_simulacion_no_crea_nada(): void
    {
        $this->proyecto(['nombre' => 'Falta']);

        $this->artisan('expedientes:crear-faltantes', ['--simular' => true])->assertSuccessful();

        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }
}
```

por:

```php
    public function test_el_comando_crea_los_que_faltan_y_se_puede_repetir(): void
    {
        $falta = $this->proyecto(['nombre' => 'Falta']);
        $propios = $this->proyecto(['nombre' => 'Sin regalías'], [$this->fuentePropios]);
        $tiene = $this->proyecto(['nombre' => 'Ya tiene']);
        $previo = $this->expedienteSinConfigurar($tiene);
        $this->proyecto(['nombre' => 'Otra fase', 'fase' => $this->licitacion]);
        $this->proyecto(['nombre' => 'Por definir', 'financiacion_por_definir' => true], []);

        $this->artisan('expedientes:crear-faltantes')
            ->expectsOutput('Expedientes creados: 2 de 2.')
            ->assertSuccessful();

        $this->assertSame(1, $falta->expedientes()->count());
        $this->assertNull($falta->expedientes()->sole()->fecha_configuracion);
        $this->assertSame(1, $falta->expedientes()->sole()->versionesPlantilla()->count());
        $this->assertSame(0, $propios->expedientes()->sole()->versionesPlantilla()->count());
        $this->assertSame([$previo->id], $tiene->expedientes()->pluck('id')->all());
        $this->assertDatabaseCount('proyectos_expedientes', 3);

        $this->artisan('expedientes:crear-faltantes')->assertSuccessful();
        $this->assertDatabaseCount('proyectos_expedientes', 3);
    }

    public function test_el_comando_en_simulacion_muestra_la_plantilla_y_no_crea_nada(): void
    {
        $sgr = $this->proyecto(['nombre' => 'Acueducto']);
        $propios = $this->proyecto(['nombre' => 'Parque'], [$this->fuentePropios]);

        $this->artisan('expedientes:crear-faltantes', ['--simular' => true])
            ->expectsOutput("#{$sgr->id} Acueducto: plantilla SGR")
            ->expectsOutput("#{$propios->id} Parque: sin plantilla")
            ->expectsOutput('Se crearían 2 expedientes.')
            ->assertSuccessful();

        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_el_comando_describe_la_regla_nueva(): void
    {
        $this->artisan('list')
            ->expectsOutputToContain('Crea el expediente, sin configurar, a los proyectos en Formulación con al menos una fuente definida que no tienen')
            ->assertSuccessful();
    }
}
```

En `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`, reemplazar (1 de 2):

```php
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    // --------------------------------------------------------- configuración

    public function test_configurar_sin_plantillas_en_el_pedido_conserva_la_seleccion(): void
```

por:

```php
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    /**
     * El frontend crea el nuevo expediente enviando la configuración del abierto, ahora con
     * plantilla_ids y principal_id: el backend no necesita nada más. Cada plantilla entra con
     * su versión vigente y no se copian el cumplimiento ni los requisitos propios.
     */
    public function test_un_nuevo_expediente_desde_un_radicado_copia_las_plantillas_con_su_vigente_actual(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $proyecto = $this->proyecto();
        $seleccion = ['fase_sgr' => 2, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $agua->id];
        $radicado = ProyectoExpediente::findOrFail($this->crear($proyecto, $seleccion)->json('id'));
        $servicio = app(ExpedienteService::class);
        $servicio->confirmarConfiguracion($radicado, $seleccion);
        $radicado->requisitos()->create([
            'requisito_catalogo_id' => null, 'aplica' => true, 'orden' => 900_000_000, 'titulo_propio' => 'Acta de socialización',
        ]);
        $radicado->requisitos()->update(['cumplido' => true]);
        $servicio->radicar($radicado, null);

        // Obras de agua publica una versión 2 después de radicar.
        $v1 = $agua->versionVigente();
        $v1->update(['vigente' => false]);
        $v2 = $agua->versiones()->create(['numero' => 2, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        RequisitoCatalogo::create(['catalogo_version_id' => $v2->id, 'clave' => 'r1', 'texto' => 'Planos firmados y aprobados', 'orden' => 1]);

        $nuevo = $this->crear($proyecto, $seleccion)->assertCreated();

        $nuevo->assertJsonPath('plantillas.0.plantilla_id', $agua->id)
            ->assertJsonPath('plantillas.0.es_principal', true)
            ->assertJsonPath('plantillas.0.version', ['id' => $v2->id, 'numero' => 2])
            ->assertJsonPath('plantillas.1.plantilla_id', $this->sgr->id)
            ->assertJsonPath('fase_sgr', 2)
            ->assertJsonPath('configurado', false)
            ->assertJsonPath('progreso.cumplidos', 0);
        $this->assertSame('Planos firmados y aprobados', $nuevo->json('requisitos.0.requisito.texto'));
        $this->assertSame([], collect($nuevo->json('requisitos'))->whereNotNull('propio')->all());
        $this->assertSame($v1->id, $radicado->versionesPlantilla()->where('plantilla_id', $agua->id)->sole()->id);
    }

    // --------------------------------------------------------- configuración

    public function test_configurar_sin_plantillas_en_el_pedido_conserva_la_seleccion(): void
```

En `tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`, reemplazar (2 de 2):

```php
            ->assertJsonPath('avisos_duplicados.0.plantillas', ['Obras de agua', 'Vías'])
            ->assertJsonPath('avisos_duplicados.0.similitud', 0.8);
    }
}
```

por:

```php
            ->assertJsonPath('avisos_duplicados.0.plantillas', ['Obras de agua', 'Vías'])
            ->assertJsonPath('avisos_duplicados.0.similitud', 0.8);
    }

    // ---------------------------------------------------------------- resumen

    public function test_el_resumen_trae_las_plantillas_con_la_principal_primero_y_si_tiene_sgr(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $vias = $this->plantilla('Vías', ['Licencia' => [null]]);
        $mixto = $this->proyecto();
        $this->crear($mixto, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id, $vias->id], 'principal_id' => $vias->id])->assertCreated();
        $soloSgr = $this->proyecto();
        $this->crear($soloSgr, ['fase_sgr' => 3])->assertCreated();
        $sinPlantillas = $this->proyecto(['recursos_propios']);
        $this->crear($sinPlantillas, [])->assertCreated();

        $resumen = collect($this->como(Rol::Consulta)->getJson('/GestPro/expedientes')->assertOk()->json())->keyBy('proyecto_id');

        $this->assertSame(['Vías', 'SGR', 'Obras de agua'], $resumen[$mixto->id]['plantillas']);
        $this->assertTrue($resumen[$mixto->id]['tiene_sgr']);
        $this->assertSame(['SGR'], $resumen[$soloSgr->id]['plantillas']);
        $this->assertTrue($resumen[$soloSgr->id]['tiene_sgr']);
        $this->assertSame([], $resumen[$sinPlantillas->id]['plantillas']);
        $this->assertFalse($resumen[$sinPlantillas->id]['tiene_sgr']);
    }

    public function test_el_resumen_hace_tres_consultas_sin_importar_cuantos_proyectos_haya(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $servicio = app(ExpedienteService::class);
        $contar = function () use ($servicio) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $servicio->resumenPorProyecto();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
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

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php`
Expected: FALLAN 9 y pasan 42. El expediente automático todavía exige la fuente SGR, el resumen no trae `plantillas` y el `--simular` no dice qué plantilla se preseleccionaría.

- [ ] **Paso 3: Implementar**

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 7):

```php
use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\Fase;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
```

por:

```php
use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\Fase;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 7):

```php
    }

    /**
     * Expediente que nace con el proyecto, si le corresponde. Nunca interrumpe a quien
     * guarda el proyecto: un fallo se registra y el expediente se puede crear a mano.
     */
    public function crearAutomatico(Proyecto $proyecto, ?User $usuario): ?ProyectoExpediente
    {
```

por:

```php
    }

    /**
     * Expediente que nace con el proyecto, si le corresponde. Con plantilla SGR compatible,
     * la preselecciona con la fase III y el sector sugerido por el proyecto; sin ella, nace
     * sin plantillas. Nunca interrumpe a quien guarda el proyecto: un fallo se registra y el
     * expediente se puede crear a mano.
     */
    public function crearAutomatico(Proyecto $proyecto, ?User $usuario): ?ProyectoExpediente
    {
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (3 de 7):

```php
            return null;
        }

        $proyecto->loadMissing('sectorRel');
        $sugerido = $proyecto->sectorRel?->requisitos_sector_id;

        try {
            return $this->crear($proyecto, ['fase_sgr' => 3, 'sector_ids' => $sugerido ? [$sugerido] : []], $usuario);
        } catch (\Throwable $e) {
            report($e);
```

por:

```php
            return null;
        }

        try {
            return $this->crear($proyecto, $this->seleccionAutomatica($proyecto), $usuario);
        } catch (\Throwable $e) {
            report($e);
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (4 de 7):

```php
        }
    }

    /** Proyecto en Formulación, con la fuente SGR y todavía sin expediente. */
    public function correspondeAutomatico(Proyecto $proyecto): bool
    {
        return $this->esFormulacion($proyecto->fase)
            && $proyecto->fuentes()->where('clave', FuenteFinanciacion::SGR)->exists()
            && ! $proyecto->expedientes()->exists();
    }

    public function confirmarConfiguracion(ProyectoExpediente $expediente, array $datos, ?User $usuario = null): ProyectoExpediente
    {
        $sinPlantillas = array_key_exists('plantilla_ids', $datos)
```

por:

```php
        }
    }

    /** Proyecto en Formulación, con al menos una fuente definida y todavía sin expediente. */
    public function correspondeAutomatico(Proyecto $proyecto): bool
    {
        // "Por definir" no es una fuente definida, aunque quedaran fuentes de antes.
        return $this->esFormulacion($proyecto->fase)
            && ! $proyecto->financiacion_por_definir
            && $proyecto->fuentes()->exists()
            && ! $proyecto->expedientes()->exists();
    }

    /** @return array{plantilla_ids: list<int>, principal_id: ?int, fase_sgr?: int, sector_ids?: list<int>} */
    private function seleccionAutomatica(Proyecto $proyecto): array
    {
        $seleccion = $this->plantillas->preseleccion($proyecto);
        if ($seleccion['plantilla_ids'] === []) {
            return $seleccion;
        }

        $proyecto->loadMissing('sectorRel');
        $sugerido = $proyecto->sectorRel?->requisitos_sector_id;

        return [...$seleccion, 'fase_sgr' => 3, 'sector_ids' => $sugerido ? [$sugerido] : []];
    }

    public function confirmarConfiguracion(ProyectoExpediente $expediente, array $datos, ?User $usuario = null): ProyectoExpediente
    {
        $sinPlantillas = array_key_exists('plantilla_ids', $datos)
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (5 de 7):

```php
    }

    /**
     * Expediente más reciente de cada proyecto que tiene alguno, con su avance.
     * Son dos consultas sin importar cuántos proyectos haya.
     */
    public function resumenPorProyecto(): array
    {
```

por:

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

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (6 de 7):

```php
            ->groupBy('proyecto_id')
            ->pluck('cantidad', 'ultimo_id');

        return ProyectoExpediente::query()
            ->whereIn('id', $cantidades->keys())
            ->withCount([
```

por:

```php
            ->groupBy('proyecto_id')
            ->pluck('cantidad', 'ultimo_id');

        // Mismo orden que versionesDe(): la principal primero y luego por plantilla.
        $plantillas = DB::table('expediente_plantillas')
            ->join('catalogo_versiones', 'catalogo_versiones.id', '=', 'expediente_plantillas.catalogo_version_id')
            ->join('plantillas_checklist', 'plantillas_checklist.id', '=', 'catalogo_versiones.plantilla_id')
            ->whereIn('expediente_plantillas.expediente_id', $cantidades->keys())
            ->orderByDesc('expediente_plantillas.es_principal')
            ->orderBy('plantillas_checklist.id')
            ->get(['expediente_plantillas.expediente_id', 'plantillas_checklist.nombre', 'plantillas_checklist.motor'])
            ->groupBy('expediente_id');

        return ProyectoExpediente::query()
            ->whereIn('id', $cantidades->keys())
            ->withCount([
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (7 de 7):

```php
                'estado' => $e->estado,
                'fase_sgr' => $e->fase_sgr,
                'configurado' => $e->estaConfigurado(),
                'fecha_radicado' => $e->fecha_radicado?->toIso8601String(),
                'actualizado' => $e->updated_at?->toIso8601String(),
                'progreso' => $this->progreso((int) $e->requisitos_aplican, (int) $e->requisitos_cumplidos),
```

por:

```php
                'estado' => $e->estado,
                'fase_sgr' => $e->fase_sgr,
                'configurado' => $e->estaConfigurado(),
                'plantillas' => $plantillas->get($e->id, collect())->pluck('nombre')->values()->all(),
                'tiene_sgr' => $plantillas->get($e->id, collect())->contains('motor', PlantillaChecklist::MOTOR_SGR),
                'fecha_radicado' => $e->fecha_radicado?->toIso8601String(),
                'actualizado' => $e->updated_at?->toIso8601String(),
                'progreso' => $this->progreso((int) $e->requisitos_aplican, (int) $e->requisitos_cumplidos),
```

En `app/Services/Viabilizacion/PlantillasExpediente.php`, reemplazar (1 de 1):

```php

    /**
     * Selección con que nace un expediente si el pedido no trae plantillas: la plantilla
     * compatible con motor sgr como principal (la de clave sgr antes que una copia), o ninguna.
     *
     * @return array{plantilla_ids: list<int>, principal_id: ?int}
     */
    public function preseleccion(Proyecto $proyecto): array
    {
        $sgr = PlantillaChecklist::query()
            ->whereIn('id', $this->idsCompatibles($proyecto))
            ->where('motor', PlantillaChecklist::MOTOR_SGR)
            ->get()
            ->sortBy(fn (PlantillaChecklist $p) => [$p->clave === PlantillaChecklist::CLAVE_SGR ? 0 : 1, $p->id])
            ->first();

        return ['plantilla_ids' => $sgr ? [$sgr->id] : [], 'principal_id' => $sgr?->id];
    }

    /** Si alguna de las versiones es de una plantilla con motor sgr. */
```

por:

```php

    /**
     * Selección con que nace un expediente si el pedido no trae plantillas: la plantilla
     * compatible con motor sgr como principal, o ninguna.
     *
     * @return array{plantilla_ids: list<int>, principal_id: ?int}
     */
    public function preseleccion(Proyecto $proyecto): array
    {
        $sgr = $this->sgrCompatible($proyecto);

        return ['plantilla_ids' => $sgr ? [$sgr->id] : [], 'principal_id' => $sgr?->id];
    }

    /** Plantilla compatible con motor sgr que se preselecciona: la de clave sgr antes que una copia. */
    public function sgrCompatible(Proyecto $proyecto): ?PlantillaChecklist
    {
        return PlantillaChecklist::query()
            ->whereIn('id', $this->idsCompatibles($proyecto))
            ->where('motor', PlantillaChecklist::MOTOR_SGR)
            ->get()
            ->sortBy(fn (PlantillaChecklist $p) => [$p->clave === PlantillaChecklist::CLAVE_SGR ? 0 : 1, $p->id])
            ->first();
    }

    /** Si alguna de las versiones es de una plantilla con motor sgr. */
```

En `routes/console.php`, reemplazar (1 de 3):

```php

use App\Models\Proyecto;
use App\Services\Viabilizacion\ExpedienteService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
```

por:

```php

use App\Models\Proyecto;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
```

En `routes/console.php`, reemplazar (2 de 3):

```php
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('expedientes:crear-faltantes {--simular : Solo lista los proyectos, sin crear nada}', function (ExpedienteService $servicio) {
    $proyectos = Proyecto::query()->orderBy('id')->get()->filter(fn (Proyecto $p) => $servicio->correspondeAutomatico($p));

    if ($this->option('simular')) {
        $proyectos->each(fn (Proyecto $p) => $this->line("#{$p->id} {$p->nombre}"));
        $this->info("Se crearían {$proyectos->count()} expedientes.");

        return;
```

por:

```php
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('expedientes:crear-faltantes {--simular : Solo lista los proyectos y la plantilla que se preseleccionaría, sin crear nada}', function (ExpedienteService $servicio, PlantillasExpediente $plantillas) {
    $proyectos = Proyecto::query()->orderBy('id')->get()->filter(fn (Proyecto $p) => $servicio->correspondeAutomatico($p));

    if ($this->option('simular')) {
        $proyectos->each(function (Proyecto $p) use ($plantillas) {
            $plantilla = $plantillas->sgrCompatible($p);
            $this->line("#{$p->id} {$p->nombre}: " . ($plantilla ? "plantilla {$plantilla->nombre}" : 'sin plantilla'));
        });
        $this->info("Se crearían {$proyectos->count()} expedientes.");

        return;
```

En `routes/console.php`, reemplazar (3 de 3):

```php

    $creados = $proyectos->filter(fn (Proyecto $p) => $servicio->crearAutomatico($p, null) !== null)->count();
    $this->info("Expedientes creados: {$creados} de {$proyectos->count()}.");
})->purpose('Crea el expediente SGR, sin configurar, a los proyectos en Formulación con la fuente SGR que no tienen');

Artisan::command('proyectos:informe-fuentes', function () {
    $porFuente = DB::table('fuentes_financiacion as f')
```

por:

```php

    $creados = $proyectos->filter(fn (Proyecto $p) => $servicio->crearAutomatico($p, null) !== null)->count();
    $this->info("Expedientes creados: {$creados} de {$proyectos->count()}.");
})->purpose('Crea el expediente, sin configurar, a los proyectos en Formulación con al menos una fuente definida que no tienen');

Artisan::command('proyectos:informe-fuentes', function () {
    $porFuente = DB::table('fuentes_financiacion as f')
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test`
Expected: pasan todas, 523.

- [ ] **Paso 5: Commit**

```bash
git add app/Services/Viabilizacion/ExpedienteService.php app/Services/Viabilizacion/PlantillasExpediente.php routes/console.php tests/Feature/Viabilizacion/ExpedienteAutomaticoTest.php tests/Feature/Viabilizacion/ExpedientePlantillasApiTest.php
git commit -m "feat: automatic expediente for every funding source, with templates in the summary"
```

---

### Tarea 9: Lógica de pantalla

**Files:**
- Create: `resources/js/utils/plantillasExpediente.js`
- Test (create): `resources/js/utils/plantillasExpediente.test.js`
- Modify: `resources/js/utils/expedienteChecklist.js`
- Test: `resources/js/utils/expedienteChecklist.test.js`
- Modify: `resources/js/utils/plantillasChecklist.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `resources/js/utils/plantillasExpediente.test.js`:

```js
import { describe, it, expect } from 'vitest';
import {
    AVISO_SIN_SECTOR,
    alternarPlantilla,
    avisoIncompatibles,
    avisosPlantilla,
    detallePlantilla,
    elegirPrincipal,
    hayCompatibleSgr,
    incluyeSgr,
    opcionPlantilla,
    seleccionParaNuevo,
} from './plantillasExpediente';

/** Plantilla como la devuelve GET /proyectos/{id}/plantillas-compatibles. */
const plantilla = (id, extra = {}) => ({
    id,
    nombre: `Plantilla ${id}`,
    descripcion: null,
    motor: 'simple',
    es_sgr: false,
    activa: true,
    compatible: true,
    fuentes: [],
    sectores: [],
    todas_fuentes: true,
    todos_sectores: true,
    requisitos: 3,
    version: { id: id * 10, numero: 1 },
    en_expediente: null,
    ...extra,
});

const SGR = plantilla(1, { nombre: 'SGR', motor: 'sgr', es_sgr: true, requisitos: 206 });
const COPIA_SGR = plantilla(2, { nombre: 'SGR copia', motor: 'sgr' });
const AGUA = plantilla(5, { nombre: 'Obras de agua' });
const VIAS = plantilla(6, { nombre: 'Vías' });

const base = { fase_sgr: 3, sector_ids: [4], tipo_ids: [], componente_ids: [], tipologia_ids: [] };

describe('incluyeSgr', () => {
    it('mira el motor de las plantillas elegidas', () => {
        expect(incluyeSgr([5, 1], [SGR, AGUA])).toBe(true);
        expect(incluyeSgr([5], [SGR, AGUA])).toBe(false);
        expect(incluyeSgr([], [SGR])).toBe(false);
    });

    it('una copia de la SGR con motor sgr también cuenta', () => {
        expect(incluyeSgr([2], [COPIA_SGR])).toBe(true);
    });

    it('acepta las plantillas del expediente, que traen plantilla_id', () => {
        expect(incluyeSgr([1], [{ plantilla_id: 1, motor: 'sgr' }])).toBe(true);
    });

    it('una plantilla que no está en la lista no cuenta', () => {
        expect(incluyeSgr([9], [SGR])).toBe(false);
        expect(incluyeSgr([1], null)).toBe(false);
    });
});

describe('hayCompatibleSgr', () => {
    it('solo cuenta una plantilla sgr que hoy se ofrece', () => {
        expect(hayCompatibleSgr([AGUA, SGR])).toBe(true);
        expect(hayCompatibleSgr([AGUA, { ...SGR, compatible: false }])).toBe(false);
        expect(hayCompatibleSgr(null)).toBe(false);
    });
});

describe('alternarPlantilla', () => {
    it('la primera que se marca queda como principal', () => {
        expect(alternarPlantilla({ ...base, plantilla_ids: [], principal_id: null }, 5))
            .toEqual({ ...base, plantilla_ids: [5], principal_id: 5 });
    });

    it('marcar otra no cambia la principal', () => {
        expect(alternarPlantilla({ ...base, plantilla_ids: [1], principal_id: 1 }, 5))
            .toMatchObject({ plantilla_ids: [1, 5], principal_id: 1 });
    });

    it('quitar la principal pasa la principal a la primera que queda', () => {
        expect(alternarPlantilla({ ...base, plantilla_ids: [1, 5, 6], principal_id: 1 }, 1))
            .toMatchObject({ plantilla_ids: [5, 6], principal_id: 5 });
    });

    it('quitar la última deja la selección sin principal', () => {
        expect(alternarPlantilla({ ...base, plantilla_ids: [5], principal_id: 5 }, 5))
            .toMatchObject({ plantilla_ids: [], principal_id: null });
    });

    it('no toca la configuración SGR', () => {
        expect(alternarPlantilla({ ...base, plantilla_ids: [1], principal_id: 1 }, 1)).toMatchObject(base);
    });
});

describe('elegirPrincipal', () => {
    it('elige una de las marcadas', () => {
        expect(elegirPrincipal({ ...base, plantilla_ids: [1, 5], principal_id: 1 }, 5)).toMatchObject({ principal_id: 5 });
    });

    it('una que no está marcada no puede ser principal', () => {
        const seleccion = { ...base, plantilla_ids: [1], principal_id: 1 };
        expect(elegirPrincipal(seleccion, 5)).toBe(seleccion);
    });
});

describe('opcionPlantilla', () => {
    const enExpediente = [{ plantilla_id: 1, motor: 'sgr' }];

    it('una plantilla compatible se puede marcar', () => {
        expect(opcionPlantilla(AGUA, { plantilla_ids: [] }, [], [AGUA])).toEqual({ marcada: false, deshabilitada: false, motivo: null });
    });

    it('una que ya no se ofrece solo se conserva: no se puede volver a marcar', () => {
        const vieja = { ...AGUA, compatible: false };
        expect(opcionPlantilla(vieja, { plantilla_ids: [] }, [], [vieja]))
            .toEqual({ marcada: false, deshabilitada: true, motivo: 'Ya no se ofrece a este proyecto: solo la conserva un expediente que ya la tenía.' });
        // Mientras el expediente aún la tenga guardada, se puede volver a marcar.
        expect(opcionPlantilla(vieja, { plantilla_ids: [] }, [{ plantilla_id: 5, motor: 'simple' }], [vieja]).deshabilitada).toBe(false);
        // Marcada, siempre se puede quitar.
        expect(opcionPlantilla(vieja, { plantilla_ids: [5] }, [], [vieja])).toMatchObject({ marcada: true, deshabilitada: false });
    });

    it('con una plantilla sgr elegida, otra sgr no se puede marcar', () => {
        expect(opcionPlantilla(COPIA_SGR, { plantilla_ids: [1] }, enExpediente, [SGR, COPIA_SGR]))
            .toEqual({ marcada: false, deshabilitada: true, motivo: 'El expediente ya tiene una plantilla con motor SGR: solo admite una.' });
        expect(opcionPlantilla(SGR, { plantilla_ids: [1] }, enExpediente, [SGR, COPIA_SGR]).deshabilitada).toBe(false);
    });

    it('en un expediente configurado, la última plantilla no se puede quitar', () => {
        const enUno = { plantilla_ids: [5] };
        expect(opcionPlantilla(AGUA, enUno, [], [AGUA], { configurado: true }))
            .toEqual({ marcada: true, deshabilitada: true, motivo: 'Un expediente configurado necesita al menos una plantilla.' });
        expect(opcionPlantilla(AGUA, enUno, [], [AGUA], { configurado: false }).deshabilitada).toBe(false);
        expect(opcionPlantilla(AGUA, { plantilla_ids: [5, 6] }, [], [AGUA, VIAS], { configurado: true }).deshabilitada).toBe(false);
    });

    it('sin selección cargada, todo queda deshabilitado', () => {
        expect(opcionPlantilla(AGUA, null, [], [AGUA])).toMatchObject({ marcada: false, deshabilitada: true });
    });
});

describe('avisosPlantilla', () => {
    it('una compatible y activa no tiene avisos', () => {
        expect(avisosPlantilla(AGUA)).toEqual([]);
    });

    it('avisa si ya no es compatible o si está inactiva', () => {
        expect(avisosPlantilla({ ...AGUA, compatible: false })).toEqual(['Ya no es compatible con el proyecto']);
        expect(avisosPlantilla({ ...AGUA, compatible: false, activa: false })).toEqual(['Inactiva']);
    });

    it('el aviso del proyecto sin sector es el del diseño', () => {
        expect(AVISO_SIN_SECTOR).toBe('El proyecto no tiene sector: solo se ofrecen las plantillas para todos los sectores');
    });
});

describe('detallePlantilla', () => {
    it('resume fuentes, sectores, requisitos y versión', () => {
        const p = plantilla(7, {
            todas_fuentes: false,
            fuentes: [{ id: 1, nombre: 'SGR' }, { id: 2, nombre: 'Recursos propios' }],
            todos_sectores: true,
            requisitos: 1,
            version: { id: 70, numero: 3 },
        });
        expect(detallePlantilla(p)).toEqual({
            fuentes: 'SGR, Recursos propios',
            sectores: 'Todos los sectores',
            requisitos: '1 requisito',
            version: 'v3',
        });
    });

    it('dice qué versión usa el expediente si no es la vigente', () => {
        const p = plantilla(7, { requisitos: 12, version: { id: 71, numero: 2 }, en_expediente: { version: { id: 70, numero: 1 }, es_principal: true } });
        expect(detallePlantilla(p)).toMatchObject({ requisitos: '12 requisitos', version: 'v1 en el expediente · v2 vigente' });
    });

    it('con las plantillas del expediente, la versión sale de ellas y no de la lista, que puede ser vieja', () => {
        const p = plantilla(7, { version: { id: 71, numero: 2 }, en_expediente: { version: { id: 70, numero: 1 }, es_principal: true } });
        // Ya se quitó del expediente: no hay versión propia que mostrar.
        expect(detallePlantilla(p, []).version).toBe('v2');
        expect(detallePlantilla(p, [{ plantilla_id: 7, version: { id: 70, numero: 1 } }]).version).toBe('v1 en el expediente · v2 vigente');
    });

    it('sin versión vigente lo dice', () => {
        expect(detallePlantilla(plantilla(8, { version: null, todas_fuentes: false, fuentes: [] })))
            .toMatchObject({ fuentes: '—', version: 'Sin versión publicada' });
    });
});

describe('seleccionParaNuevo', () => {
    const seleccion = { ...base, plantilla_ids: [1, 5, 6], principal_id: 1 };

    it('envía solo las plantillas que siguen disponibles y compatibles', () => {
        const disponibles = [SGR, AGUA, { ...VIAS, compatible: false }];
        const { datos, quitadas } = seleccionParaNuevo(seleccion, disponibles);

        expect(datos).toEqual({ ...base, plantilla_ids: [1, 5], principal_id: 1 });
        expect(quitadas).toEqual(['Vías']);
    });

    it('si se quita la principal, pasa a la primera que queda', () => {
        const { datos, quitadas } = seleccionParaNuevo(seleccion, [{ ...SGR, activa: false, compatible: false }, AGUA, VIAS]);
        expect(datos).toMatchObject({ plantilla_ids: [5, 6], principal_id: 5 });
        expect(quitadas).toEqual(['SGR']);
    });

    it('una plantilla sin versión publicada o fuera de la lista también se quita', () => {
        const { datos, quitadas } = seleccionParaNuevo(seleccion, [SGR, { ...AGUA, version: null }], [{ plantilla_id: 6, nombre: 'Vías' }]);
        expect(datos).toMatchObject({ plantilla_ids: [1], principal_id: 1 });
        expect(quitadas).toEqual(['Obras de agua', 'Vías']);
    });

    it('sin nombre conocido, la nombra por su número', () => {
        expect(seleccionParaNuevo({ ...base, plantilla_ids: [9], principal_id: 9 }, []).quitadas).toEqual(['Plantilla #9']);
    });

    it('si se quitan todas, el nuevo nace sin plantillas', () => {
        const { datos } = seleccionParaNuevo({ ...base, plantilla_ids: [5], principal_id: 5 }, []);
        expect(datos).toMatchObject({ plantilla_ids: [], principal_id: null });
    });

    it('sin la lista de disponibles, envía la selección tal cual y el servidor decide', () => {
        expect(seleccionParaNuevo(seleccion, null)).toEqual({ datos: seleccion, quitadas: [] });
    });
});

describe('avisoIncompatibles', () => {
    it('sin plantillas incompatibles no hay aviso', () => {
        expect(avisoIncompatibles([{ nombre: 'SGR', compatible: true }])).toBeNull();
        expect(avisoIncompatibles(undefined)).toBeNull();
    });

    it('con una sola, en singular', () => {
        expect(avisoIncompatibles([{ nombre: 'SGR', compatible: false }, { nombre: 'Obras de agua', compatible: true }])).toEqual({
            titulo: 'Una plantilla ya no es compatible con el proyecto',
            detalle: 'SGR. Este expediente la conserva tal como está; un expediente nuevo no la tendrá.',
        });
    });

    it('con varias, en plural', () => {
        expect(avisoIncompatibles([{ nombre: 'SGR', compatible: false }, { nombre: 'Obras de agua', compatible: false }])).toEqual({
            titulo: 'Algunas plantillas ya no son compatibles con el proyecto',
            detalle: 'SGR, Obras de agua. Este expediente las conserva tal como están; un expediente nuevo no las tendrá.',
        });
    });
});
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (1 de 7):

```js
import { describe, it, expect } from 'vitest';
import {
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
    sectoresSinTipo,
    seleccionDesde,
    sectoresConFuenteA12,
} from './expedienteChecklist';

const fila = (id, seccion, extra = {}) => ({
    id,
    estado: 'pendiente',
    cuenta: true,
    no_aplica: null,
    responsable: null,
```

por:

```js
import { describe, it, expect } from 'vitest';
import {
    ANCHOS_HOJA_CHECKLIST,
    accionesAplicabilidad,
    actualizarExpedienteEnLista,
    agruparChecklist,
    agruparRequisitos,
    alternarId,
    alternarSector,
    construirHojasExcel,
    contenidoFila,
    datosPropio,
    describirEvento,
    formularioPropio,
    mensajeError,
    motivoNoEliminarPropio,
    nombreArchivoExcel,
    puedeMarcarNoAplica,
    puedeMarcarRequisito,
    puedeRadicar,
    puedeRevisarNoAplica,
    requiereAprobacionNoAplica,
    responsablesDisponibles,
    sectoresSinTipo,
    seleccionDesde,
    sectoresConFuenteA12,
    textoOrigenes,
    textoPlantillas,
    textoPorDefinir,
} from './expedienteChecklist';

const fila = (id, seccion, extra = {}) => ({
    id,
    estado: 'pendiente',
    caracter: 'obligatorio',
    cuenta: true,
    no_aplica: null,
    responsable: null,
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (2 de 7):

```js
    },
});

const salud = { id: 1, clave: 'salud', nombre: 'Salud', fuente: null };
const justicia = { id: 2, clave: 'justicia', nombre: 'Justicia', fuente: 'Anexo 08 del Acuerdo 12/2024' };
```

por:

```js
    },
});

const SGR = { id: 1, nombre: 'SGR', motor: 'sgr' };
const AGUA = { id: 5, nombre: 'Obras de agua', motor: 'simple' };
const VIAS = { id: 6, nombre: 'Vías', motor: 'simple' };

/** Fila de una plantilla simple: sin sección ni sector. */
const filaSimple = (id, extra = {}) => {
    const base = fila(id, null, { fila: extra.fila, requisito: { fuente: null, codigo: null, categoria: null, ...extra.requisito } });
    return { ...base, plantilla: extra.plantilla ?? AGUA, origenes: extra.origenes ?? [{ plantilla_id: (extra.plantilla ?? AGUA).id, nombre: (extra.plantilla ?? AGUA).nombre }] };
};

/** Requisito propio: sin requisito de catálogo ni plantilla. */
const filaPropia = (id, extra = {}) => ({
    ...fila(id, null, { fila: extra.fila }),
    requisito: null,
    plantilla: null,
    origenes: [],
    requiere_aprobacion_no_aplica: false,
    propio: { titulo: `Propio ${id}`, descripcion: null, categoria: null, ...extra.propio },
    categoria: extra.propio?.categoria ?? null,
});

const salud = { id: 1, clave: 'salud', nombre: 'Salud', fuente: null };
const justicia = { id: 2, clave: 'justicia', nombre: 'Justicia', fuente: 'Anexo 08 del Acuerdo 12/2024' };
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (3 de 7):

```js
    it('seleccionDesde extrae los ids de la configuración del expediente', () => {
        const expediente = {
            fase_sgr: 2,
            configuracion: {
                sectores: [{ id: 1 }],
                tipos: [{ id: 5, sector_id: 1 }],
```

por:

```js
    it('seleccionDesde extrae los ids de la configuración del expediente', () => {
        const expediente = {
            fase_sgr: 2,
            plantillas: [
                { plantilla_id: 4, nombre: 'SGR', motor: 'sgr', es_principal: true },
                { plantilla_id: 9, nombre: 'Obras de agua', motor: 'simple', es_principal: false },
            ],
            configuracion: {
                sectores: [{ id: 1 }],
                tipos: [{ id: 5, sector_id: 1 }],
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (4 de 7):

```js
            },
        };
        expect(seleccionDesde(expediente)).toEqual({
            fase_sgr: 2, sector_ids: [1], tipo_ids: [5], componente_ids: [7], tipologia_ids: [9],
        });
    });

    it('alternarId agrega y quita', () => {
        expect(alternarId([1, 2], 3)).toEqual([1, 2, 3]);
        expect(alternarId([1, 2], 1)).toEqual([2]);
```

por:

```js
            },
        };
        expect(seleccionDesde(expediente)).toEqual({
            plantilla_ids: [4, 9],
            principal_id: 4,
            fase_sgr: 2, sector_ids: [1], tipo_ids: [5], componente_ids: [7], tipologia_ids: [9],
        });
    });

    it('seleccionDesde de un expediente sin plantillas no tiene principal', () => {
        const expediente = {
            fase_sgr: 3,
            plantillas: [],
            configuracion: { sectores: [], tipos: [], componentes: [], tipologias: [] },
        };
        expect(seleccionDesde(expediente)).toMatchObject({ plantilla_ids: [], principal_id: null });
    });

    it('la principal es la marcada aunque no venga primera', () => {
        const expediente = {
            fase_sgr: 3,
            plantillas: [
                { plantilla_id: 9, nombre: 'Obras de agua', motor: 'simple', es_principal: false },
                { plantilla_id: 4, nombre: 'SGR', motor: 'sgr', es_principal: true },
            ],
            configuracion: { sectores: [], tipos: [], componentes: [], tipologias: [] },
        };
        expect(seleccionDesde(expediente)).toMatchObject({ plantilla_ids: [9, 4], principal_id: 4 });
    });

    it('alternarId agrega y quita', () => {
        expect(alternarId([1, 2], 3)).toEqual([1, 2, 3]);
        expect(alternarId([1, 2], 1)).toEqual([2]);
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (5 de 7):

```js
        id: 7,
        proyecto: { id: 1, nombre: 'Acueducto: veredal/norte' },
        catalogo_version: { numero: 1 },
        fase_sgr: 3,
        estado: 'borrador',
        fecha_radicado: null,
```

por:

```js
        id: 7,
        proyecto: { id: 1, nombre: 'Acueducto: veredal/norte' },
        catalogo_version: { numero: 1 },
        plantillas: [{ plantilla_id: 1, nombre: 'SGR', motor: 'sgr', es_principal: true, version: { id: 1, numero: 1 } }],
        tiene_sgr: true,
        fase_sgr: 3,
        estado: 'borrador',
        fecha_radicado: null,
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (6 de 7):

```js
        expect(valor('Avance')).toBe('1 de 2 (50%)');
    });

    it('el nombre del archivo no lleva caracteres inválidos', () => {
        expect(nombreArchivoExcel(expediente)).toBe('expediente-7-Acueducto-veredal-norte.xlsx');
    });
```

por:

```js
        expect(valor('Avance')).toBe('1 de 2 (50%)');
    });

    it('la hoja de resumen nombra las plantillas con su versión', () => {
        const { resumen } = construirHojasExcel(expediente);
        expect(resumen.find((r) => r.Campo === 'Plantillas')?.Valor).toBe('SGR (v1)');
    });

    it('sin plantilla SGR no lleva la fase ni la configuración SGR, y aguanta filas propias', () => {
        const simple = {
            ...expediente,
            catalogo_version: null,
            tiene_sgr: false,
            plantillas: [{ plantilla_id: 5, nombre: 'Obras de agua', motor: 'simple', es_principal: true, version: { id: 8, numero: 2 } }],
            requisitos: [
                filaSimple(3, { requisito: { categoria: 'tecnico' }, fila: { caracter: 'opcional' } }),
                filaPropia(4, { propio: { titulo: 'Acta de vecinos', descripcion: 'Firmada', categoria: 'social' } }),
            ],
        };
        const { resumen, checklist } = construirHojasExcel(simple);
        const campos = resumen.map((r) => r.Campo);

        expect(campos).not.toContain('Fase SGR');
        expect(campos).not.toContain('Sectores');
        expect(campos).not.toContain('Tipologías');
        expect(resumen.find((r) => r.Campo === 'Plantillas')?.Valor).toBe('Obras de agua (v2)');
        expect(checklist[0]).toMatchObject({
            'Plantilla': 'Obras de agua', 'Sección': '', 'Categoría': 'Técnico', 'Código': '', 'Fuente': '', 'Requisito': 'Texto 3', 'Carácter': 'Opcional',
        });
        expect(checklist[1]).toMatchObject({
            'Plantilla': 'Propio del proyecto', 'Sección': '', 'Categoría': 'Social', 'Requisito': 'Acta de vecinos', 'Nota': 'Firmada', 'Carácter': 'Obligatorio',
        });
    });

    it('hay un ancho por cada columna de la hoja de checklist', () => {
        const { checklist } = construirHojasExcel(expediente);
        expect(ANCHOS_HOJA_CHECKLIST).toHaveLength(Object.keys(checklist[0]).length);
    });

    it('sin plantillas, el resumen lo dice', () => {
        const { resumen } = construirHojasExcel({ ...expediente, plantillas: [], tiene_sgr: false, catalogo_version: null, requisitos: [] });
        expect(resumen.find((r) => r.Campo === 'Plantillas')?.Valor).toBe('Sin plantillas');
    });

    it('el nombre del archivo no lleva caracteres inválidos', () => {
        expect(nombreArchivoExcel(expediente)).toBe('expediente-7-Acueducto-veredal-norte.xlsx');
    });
```

En `resources/js/utils/expedienteChecklist.test.js`, reemplazar (7 de 7):

```js
        expect(sectoresSinTipo({ sector_ids: [], tipo_ids: [] }, catalogo)).toEqual([]);
    });
});
```

por:

```js
        expect(sectoresSinTipo({ sector_ids: [], tipo_ids: [] }, catalogo)).toEqual([]);
    });
});

describe('agruparChecklist', () => {
    const sgr = (id, seccion, extra = {}) => ({ ...fila(id, seccion, extra), plantilla: SGR, origenes: [{ plantilla_id: 1, nombre: 'SGR' }] });

    it('un expediente solo SGR queda en un bloque con sus secciones, como hoy', () => {
        const filas = [sgr(1, 'A'), sgr(2, 'B'), sgr(3, 'A', { fila: { estado: 'cumplido', cumplido: true } })];
        const bloques = agruparChecklist(filas);

        expect(bloques).toHaveLength(1);
        expect(bloques[0]).toMatchObject({ tipo: 'sgr', plantilla: SGR, total: 3, cumplidos: 1 });
        expect(bloques[0].secciones).toEqual(agruparRequisitos(filas));
    });

    it('las demás plantillas se agrupan por plantilla y luego por categoría, en el orden de la lista', () => {
        const bloques = agruparChecklist([
            sgr(1, 'A'),
            filaSimple(2, { requisito: { categoria: 'juridico' } }),
            filaSimple(3, { requisito: { categoria: 'tecnico' }, fila: { estado: 'cumplido', cumplido: true } }),
            filaSimple(4, { requisito: { categoria: null } }),
            filaSimple(5, { requisito: { categoria: 'juridico' } }),
            filaSimple(6, { plantilla: VIAS, requisito: { categoria: 'ambiental' } }),
        ]);

        expect(bloques.map((b) => [b.tipo, b.plantilla?.nombre])).toEqual([['sgr', 'SGR'], ['simple', 'Obras de agua'], ['simple', 'Vías']]);
        const agua = bloques[1];
        expect(agua.categorias.map((c) => [c.clave, c.titulo, c.filas.map((f) => f.id)])).toEqual([
            ['tecnico', 'Técnico', [3]],
            ['juridico', 'Jurídico', [2, 5]],
            ['sin', 'Sin categoría', [4]],
        ]);
        expect(agua).toMatchObject({ total: 4, cumplidos: 1 });
        expect(agua.categorias[0]).toMatchObject({ total: 1, cumplidos: 1 });
    });

    it('una categoría desconocida cae en "Sin categoría"', () => {
        const [bloque] = agruparChecklist([filaSimple(1, { requisito: { categoria: 'inventada' } })]);
        expect(bloque.categorias.map((c) => c.clave)).toEqual(['sin']);
    });

    it('los propios van al final, aunque lleguen antes', () => {
        const bloques = agruparChecklist([filaPropia(9), sgr(1, 'A'), filaSimple(2), filaPropia(10, { fila: { cuenta: false } })]);

        expect(bloques.map((b) => b.tipo)).toEqual(['sgr', 'simple', 'propios']);
        expect(bloques[2]).toMatchObject({ titulo: 'Requisitos propios del proyecto', total: 1, cumplidos: 0 });
        expect(bloques[2].filas.map((f) => f.id)).toEqual([9, 10]);
    });

    it('lo que no cuenta no suma en la plantilla ni en la categoría', () => {
        const [bloque] = agruparChecklist([
            filaSimple(1, { fila: { cuenta: false, estado: 'cumplido', cumplido: true } }),
            filaSimple(2, { fila: { estado: 'no_aplica_pendiente', cumplido: true } }),
        ]);
        expect(bloque).toMatchObject({ total: 1, cumplidos: 0 });
    });

    it('sin filas no hay bloques', () => {
        expect(agruparChecklist([])).toEqual([]);
    });
});

describe('accionesAplicabilidad', () => {
    const gestor = { id: 6, rol: 'Gestor' };

    it('por definir ofrece "Aplica" y "No aplica"', () => {
        expect(accionesAplicabilidad({ estado: 'por_definir', no_aplica: null }, gestor)).toEqual(['aplica', 'no_aplica']);
    });

    it('pendiente solo ofrece "No aplica"; cumplido, nada', () => {
        expect(accionesAplicabilidad({ estado: 'pendiente', no_aplica: null }, gestor)).toEqual(['no_aplica']);
        expect(accionesAplicabilidad({ estado: 'cumplido', no_aplica: null }, gestor)).toEqual([]);
    });

    it('un "no aplica" se retira y, si está por aprobar, lo revisa otra persona', () => {
        const porAprobar = { estado: 'no_aplica_pendiente', no_aplica: { usuario_id: 5 } };
        expect(accionesAplicabilidad(porAprobar, gestor)).toEqual(['retirar_no_aplica', 'aprobar', 'rechazar']);
        expect(accionesAplicabilidad(porAprobar, { id: 5, rol: 'Gestor' })).toEqual(['retirar_no_aplica']);
        expect(accionesAplicabilidad({ estado: 'no_aplica', no_aplica: { usuario_id: 5 } }, gestor)).toEqual(['retirar_no_aplica']);
    });
});

describe('requiereAprobacionNoAplica', () => {
    it('se lee de la fila, que reúne todos sus orígenes, y no del requisito mostrado', () => {
        expect(requiereAprobacionNoAplica({ requiere_aprobacion_no_aplica: true, requisito: { requiere_aprobacion_no_aplica: false } })).toBe(true);
        expect(requiereAprobacionNoAplica({ requiere_aprobacion_no_aplica: false, requisito: { requiere_aprobacion_no_aplica: true } })).toBe(false);
    });

    it('un requisito propio no tiene requisito de catálogo y no la exige', () => {
        expect(requiereAprobacionNoAplica(filaPropia(1))).toBe(false);
    });
});

describe('textoPlantillas', () => {
    it('nombra las plantillas en el orden recibido, la principal primero', () => {
        expect(textoPlantillas([{ nombre: 'SGR' }, { nombre: 'Obras de agua' }])).toBe('Plantillas: SGR, Obras de agua');
        expect(textoPlantillas(['SGR', 'Obras de agua'])).toBe('Plantillas: SGR, Obras de agua');
    });

    it('con una sola plantilla, en singular', () => {
        expect(textoPlantillas([{ nombre: 'Obras de agua' }])).toBe('Plantilla: Obras de agua');
    });

    it('sin plantillas lo dice', () => {
        expect(textoPlantillas([])).toBe('Sin plantillas');
        expect(textoPlantillas(undefined)).toBe('Sin plantillas');
    });
});

describe('textoOrigenes', () => {
    it('con más de un origen, nombra las demás plantillas', () => {
        const f = filaSimple(1, { origenes: [{ plantilla_id: 5, nombre: 'Obras de agua' }, { plantilla_id: 6, nombre: 'Vías' }, { plantilla_id: 7, nombre: 'Riego' }] });
        expect(textoOrigenes(f)).toBe('También en: Vías, Riego');
    });

    it('con un solo origen, o ninguno, no dice nada', () => {
        expect(textoOrigenes(filaSimple(1))).toBeNull();
        expect(textoOrigenes(filaPropia(2))).toBeNull();
    });
});

describe('contenidoFila', () => {
    it('una fila de plantilla muestra su requisito', () => {
        const f = fila(1, 'A', { requisito: { nota: 'Una nota' } });
        expect(contenidoFila(f)).toEqual({
            propio: false, codigo: 'c1', fuente: 'A12', texto: 'Texto 1', nota: 'Una nota', categoria: null, cargoRevision: null, cargoCumplimiento: null,
            descripcion: null, aplicaSi: null, soporteRequerido: null,
        });
    });

    it('una fila propia muestra sus textos, sin código ni fuente', () => {
        const f = filaPropia(2, { propio: { titulo: 'Acta', descripcion: 'Firmada', categoria: 'social' } });
        expect(contenidoFila(f)).toEqual({
            propio: true, codigo: null, fuente: null, texto: 'Acta', nota: 'Firmada', categoria: 'Social', cargoRevision: null, cargoCumplimiento: null,
            descripcion: null, aplicaSi: null, soporteRequerido: null,
        });
    });

    it('una fila simple sin código ni fuente no los inventa', () => {
        const f = filaSimple(3, { requisito: { categoria: 'tecnico', cargo_revision: 'Interventor' } });
        expect(contenidoFila(f)).toMatchObject({ codigo: null, fuente: null, categoria: 'Técnico', cargoRevision: 'Interventor' });
    });

    it('trae la descripción y el soporte requerido del requisito', () => {
        const f = filaSimple(4, { requisito: { descripcion: 'Lo expide la autoridad ambiental.', soporte_requerido: 'Resolución' } });
        expect(contenidoFila(f)).toMatchObject({ descripcion: 'Lo expide la autoridad ambiental.', soporteRequerido: 'Resolución', aplicaSi: null });
    });

    it('un condicionado dice cuándo aplica, esté por definir o ya decidido', () => {
        const condicion = { requisito: { condicion_descripcion: 'Si la obra descarga aguas residuales.' } };
        expect(contenidoFila(filaSimple(5, { ...condicion, fila: { caracter: 'condicionado', estado: 'por_definir' } })).aplicaSi)
            .toBe('Si la obra descarga aguas residuales.');
        expect(contenidoFila(filaSimple(6, { ...condicion, fila: { caracter: 'condicionado', estado: 'pendiente' } })).aplicaSi)
            .toBe('Si la obra descarga aguas residuales.');
        // Un obligatorio no muestra una condición que no lo rige.
        expect(contenidoFila(filaSimple(7, { ...condicion, fila: { caracter: 'obligatorio' } })).aplicaSi).toBeNull();
    });
});

describe('textoPorDefinir', () => {
    it('dice cuándo aplica, si la plantilla lo explica', () => {
        const f = filaSimple(1, { requisito: { condicion_descripcion: 'Si hay predios.' }, fila: { caracter: 'condicionado', estado: 'por_definir' } });
        expect(textoPorDefinir(f)).toBe('Aplica si: Si hay predios.');
    });

    it('sin explicación, dice que falta decidir', () => {
        const f = filaSimple(2, { fila: { caracter: 'condicionado', estado: 'por_definir' } });
        expect(textoPorDefinir(f)).toBe('Falta decidir si este requisito aplica al proyecto.');
    });
});

describe('requisitos propios', () => {
    it('el formulario nuevo es obligatorio y sin categoría', () => {
        expect(formularioPropio(null)).toEqual({ titulo: '', descripcion: '', categoria: '', caracter: 'obligatorio' });
    });

    it('el formulario de edición parte de la fila', () => {
        const f = filaPropia(1, { propio: { titulo: 'Acta', descripcion: null, categoria: 'social' }, fila: { caracter: 'opcional' } });
        expect(formularioPropio(f)).toEqual({ titulo: 'Acta', descripcion: '', categoria: 'social', caracter: 'opcional' });
    });

    it('lo que se envía va recortado y lo vacío como null', () => {
        expect(datosPropio({ titulo: '  Acta  ', descripcion: '   ', categoria: '', caracter: 'opcional' }))
            .toEqual({ titulo: 'Acta', descripcion: null, categoria: null, caracter: 'opcional' });
    });

    it('solo se elimina un propio que no esté cumplido ni tenga soporte', () => {
        expect(motivoNoEliminarPropio(filaPropia(1))).toBeNull();
        expect(motivoNoEliminarPropio(filaPropia(1, { fila: { cumplido: true } }))).toBe('El requisito está cumplido: desmárcalo antes de eliminarlo.');
        expect(motivoNoEliminarPropio(filaPropia(1, { fila: { soporte: { nombre: 'a.pdf' } } }))).toBe('El requisito tiene un soporte: quítalo antes de eliminarlo.');
    });
});

describe('describirEvento de la etapa 5', () => {
    it('explica "aplica" y los cambios de un requisito propio', () => {
        expect(describirEvento({ evento: 'aplicabilidad_decidida' })).toBe('Indicó que aplica');
        expect(describirEvento({ evento: 'requisito_propio_agregado', despues: { titulo: 'Acta' } })).toBe('Agregó el requisito propio: Acta');
        expect(describirEvento({ evento: 'requisito_propio_editado', antes: { titulo: 'A', caracter: 'obligatorio' }, despues: { titulo: 'B', caracter: 'opcional' } }))
            .toBe('Editó el requisito propio: título y carácter');
        expect(describirEvento({ evento: 'requisito_propio_editado', despues: { categoria: 'social' } })).toBe('Editó el requisito propio: categoría');
        expect(describirEvento({ evento: 'requisito_propio_eliminado', antes: { titulo: 'Acta' } })).toBe('Eliminó el requisito propio: Acta');
    });
});

describe('actualizarExpedienteEnLista', () => {
    const lista = [
        { id: 3, estado: 'borrador', configurado: true, plantillas: [{ plantilla_id: 1, nombre: 'SGR' }], tiene_sgr: true, progreso: { total: 8 } },
        { id: 2, estado: 'radicado', configurado: true, plantillas: [{ plantilla_id: 1, nombre: 'SGR' }], tiene_sgr: true, progreso: { total: 8 } },
    ];
    const actualizado = {
        id: 3, estado: 'borrador', configurado: true, plantillas: [{ plantilla_id: 5, nombre: 'Obras de agua' }], tiene_sgr: false,
        progreso: { total: 4 }, requisitos: [{ id: 1 }], configuracion: {},
    };

    it('cambia ese expediente con sus plantillas actuales y deja los demás', () => {
        const nueva = actualizarExpedienteEnLista(lista, actualizado);
        expect(nueva[0]).toEqual({ id: 3, estado: 'borrador', configurado: true, plantillas: [{ plantilla_id: 5, nombre: 'Obras de agua' }], tiene_sgr: false, progreso: { total: 4 } });
        expect(nueva[1]).toBe(lista[1]);
    });

    it('conserva la forma de la lista: no agrega el checklist', () => {
        expect(actualizarExpedienteEnLista(lista, actualizado)[0]).not.toHaveProperty('requisitos');
    });

    it('sin lista, o si el expediente no está, no cambia nada', () => {
        expect(actualizarExpedienteEnLista(undefined, actualizado)).toBeUndefined();
        expect(actualizarExpedienteEnLista(lista, { ...actualizado, id: 9 })).toEqual(lista);
    });
});
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/plantillasExpediente.test.js resources/js/utils/expedienteChecklist.test.js`
Expected: FALLAN 38 y pasan 25. `./plantillasExpediente` no existe, y `seleccionDesde` todavía no envía `plantilla_ids` ni `principal_id`.

- [ ] **Paso 3: Implementar**

Crear `resources/js/utils/plantillasExpediente.js`:

```js
// Plantillas de un expediente: cuáles se ofrecen, cómo se eligen y qué se envía.
// Funciones puras, probadas en plantillasExpediente.test.js. Las reglas las valida el
// backend (PlantillasExpediente::validar); aquí solo se evita pedir lo que va a rechazar.

import { MOTOR_SGR } from './expedienteChecklist';
import { textoAsociaciones } from './plantillasChecklist';

export const AVISO_SIN_SECTOR = 'El proyecto no tiene sector: solo se ofrecen las plantillas para todos los sectores';

const MOTIVO_NO_OFRECIDA = 'Ya no se ofrece a este proyecto: solo la conserva un expediente que ya la tenía.';
const MOTIVO_OTRA_SGR = 'El expediente ya tiene una plantilla con motor SGR: solo admite una.';
const MOTIVO_ULTIMA = 'Un expediente configurado necesita al menos una plantilla.';

/** Id de una plantilla de GET plantillas-compatibles (`id`) o de las de un expediente (`plantilla_id`). */
const idDe = (plantilla) => plantilla.id ?? plantilla.plantilla_id;

const buscar = (lista, id) => (lista ?? []).find((p) => idDe(p) === id);

const esSgr = (plantilla) => plantilla?.motor === MOTOR_SGR;

/** Hoy se puede elegir para un expediente nuevo: compatible (activa y con vigente) y con versión. */
const seOfrece = (plantilla) => Boolean(plantilla?.compatible && plantilla.activa !== false && plantilla.version);

/** Si alguna de las plantillas elegidas tiene motor sgr. Sirve la lista de disponibles o la del expediente. */
export const incluyeSgr = (plantillaIds, plantillas) => (plantillaIds ?? []).some((id) => esSgr(buscar(plantillas, id)));

/** Si entre las disponibles hay una plantilla sgr que hoy se ofrece al proyecto. */
export const hayCompatibleSgr = (disponibles) => (disponibles ?? []).some((p) => esSgr(p) && seOfrece(p));

/** Marca o quita una plantilla. La primera que se marca es la principal; si se quita la principal, pasa a la primera que queda. */
export const alternarPlantilla = (seleccion, id) => {
    const marcada = seleccion.plantilla_ids.includes(id);
    const plantilla_ids = marcada ? seleccion.plantilla_ids.filter((x) => x !== id) : [...seleccion.plantilla_ids, id];
    const principalSigue = seleccion.principal_id !== null && plantilla_ids.includes(seleccion.principal_id);
    return { ...seleccion, plantilla_ids, principal_id: principalSigue ? seleccion.principal_id : (plantilla_ids[0] ?? null) };
};

/** Solo una plantilla marcada puede ser la principal. */
export const elegirPrincipal = (seleccion, id) =>
    (seleccion.plantilla_ids.includes(id) ? { ...seleccion, principal_id: id } : seleccion);

/**
 * Estado de la casilla de una plantilla disponible.
 * - Marcada, siempre se puede quitar.
 * - Una que ya no se ofrece solo se puede volver a marcar mientras el expediente la tenga
 *   guardada (`plantillasExpediente`, las de la última respuesta del servidor): una plantilla
 *   nueva en el expediente debe ser compatible.
 * - Con una plantilla sgr elegida, otra sgr no se puede marcar.
 * - Un expediente ya configurado no se queda sin plantillas: la última no se puede quitar.
 */
export const opcionPlantilla = (plantilla, seleccion, plantillasExpediente, disponibles, { configurado = false } = {}) => {
    if (!seleccion) return { marcada: false, deshabilitada: true, motivo: null };
    const id = plantilla.id;
    const marcada = seleccion.plantilla_ids.includes(id);
    if (marcada && configurado && seleccion.plantilla_ids.length === 1) {
        return { marcada, deshabilitada: true, motivo: MOTIVO_ULTIMA };
    }
    if (marcada) return { marcada, deshabilitada: false, motivo: null };

    if (!seOfrece(plantilla) && !buscar(plantillasExpediente, id)) {
        return { marcada, deshabilitada: true, motivo: MOTIVO_NO_OFRECIDA };
    }
    const otraSgr = seleccion.plantilla_ids.some((otra) => otra !== id && esSgr(buscar(disponibles, otra) ?? buscar(plantillasExpediente, otra)));
    if (esSgr(plantilla) && otraSgr) {
        return { marcada, deshabilitada: true, motivo: MOTIVO_OTRA_SGR };
    }
    return { marcada, deshabilitada: false, motivo: null };
};

/** Avisos de una plantilla disponible. Una inactiva tampoco es compatible: basta con decir que está inactiva. */
export const avisosPlantilla = (plantilla) => {
    if (plantilla.activa === false) return ['Inactiva'];
    if (!plantilla.compatible) return ['Ya no es compatible con el proyecto'];
    return [];
};

/**
 * Aviso de las plantillas del expediente que ya no son compatibles con el proyecto, con la
 * concordancia de una o varias; null si no hay ninguna.
 */
export const avisoIncompatibles = (plantillas) => {
    const nombres = (plantillas ?? []).filter((p) => !p.compatible).map((p) => p.nombre);
    if (nombres.length === 0) return null;
    const una = nombres.length === 1;
    return {
        titulo: una ? 'Una plantilla ya no es compatible con el proyecto' : 'Algunas plantillas ya no son compatibles con el proyecto',
        detalle: una
            ? `${nombres[0]}. Este expediente la conserva tal como está; un expediente nuevo no la tendrá.`
            : `${nombres.join(', ')}. Este expediente las conserva tal como están; un expediente nuevo no las tendrá.`,
    };
};

const plural = (n, singular, varios) => `${n} ${n === 1 ? singular : varios}`;

/**
 * Textos de la tarjeta de una plantilla: fuentes, sectores, requisitos de la vigente y versión.
 * Con `plantillasExpediente` (las de la última respuesta del servidor), la versión del expediente
 * sale de ahí: `en_expediente` es de cuando se cargó la lista y no sigue los cambios del panel.
 */
export const detallePlantilla = (plantilla, plantillasExpediente = null) => {
    const propia = plantillasExpediente ? buscar(plantillasExpediente, plantilla.id)?.version : plantilla.en_expediente?.version;
    const vigente = plantilla.version;
    let version = vigente ? `v${vigente.numero}` : 'Sin versión publicada';
    if (propia && propia.id !== vigente?.id) {
        version = vigente ? `v${propia.numero} en el expediente · v${vigente.numero} vigente` : `v${propia.numero} en el expediente`;
    }
    return {
        fuentes: textoAsociaciones(plantilla.todas_fuentes, plantilla.fuentes ?? [], 'Todas las fuentes'),
        sectores: textoAsociaciones(plantilla.todos_sectores, plantilla.sectores ?? [], 'Todos los sectores'),
        requisitos: plural(plantilla.requisitos ?? 0, 'requisito', 'requisitos'),
        version,
    };
};

/**
 * Selección para un expediente nuevo a partir de la de otro (un radicado): solo las plantillas
 * que hoy se ofrecen al proyecto. Devuelve los datos para la API y los nombres de las que se
 * quitaron, para avisar. Sin la lista de disponibles, la selección va tal cual y decide el servidor.
 *
 * @param plantillasExpediente las del expediente de origen, solo para nombrar las que no estén en la lista
 */
export const seleccionParaNuevo = (seleccion, disponibles, plantillasExpediente = []) => {
    if (!disponibles) return { datos: seleccion, quitadas: [] };

    const quedan = seleccion.plantilla_ids.filter((id) => seOfrece(buscar(disponibles, id)));
    const quitadas = seleccion.plantilla_ids
        .filter((id) => !quedan.includes(id))
        .map((id) => (buscar(disponibles, id) ?? buscar(plantillasExpediente, id))?.nombre ?? `Plantilla #${id}`);
    const principal_id = quedan.includes(seleccion.principal_id) ? seleccion.principal_id : (quedan[0] ?? null);

    return { datos: { ...seleccion, plantilla_ids: quedan, principal_id }, quitadas };
};
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (1 de 8):

```js
// Lógica de presentación del expediente de viabilización SGR.
// Funciones puras, probadas en expedienteChecklist.test.js. Qué requisitos aplican
// lo decide siempre el backend (EvaluadorRequisitos); aquí solo se organizan.

export const SECCIONES = {
    A: { num: 'S.1', titulo: 'Requisitos generales', cita: 'Acuerdo 12/2024 · Subsección 1' },
```

por:

```js
// Lógica de presentación del expediente y su checklist.
// Funciones puras, probadas en expedienteChecklist.test.js. Qué requisitos aplican
// lo decide siempre el backend (sincronizar y EvaluadorRequisitos); aquí solo se organizan.

/** Categorías de un requisito, en el orden en que se agrupan. La administración de plantillas usa la misma lista. */
export const CATEGORIAS = [
    ['tecnico', 'Técnico'],
    ['juridico', 'Jurídico'],
    ['financiero', 'Financiero'],
    ['ambiental', 'Ambiental'],
    ['social', 'Social'],
    ['administrativo', 'Administrativo'],
    ['otro', 'Otro'],
];

const ETIQUETA_CATEGORIA = Object.fromEntries(CATEGORIAS);

const ETIQUETA_CARACTER = { obligatorio: 'Obligatorio', opcional: 'Opcional', condicionado: 'Condicionado' };

export const MOTOR_SGR = 'sgr';

export const TITULO_PROPIOS = 'Requisitos propios del proyecto';

export const SECCIONES = {
    A: { num: 'S.1', titulo: 'Requisitos generales', cita: 'Acuerdo 12/2024 · Subsección 1' },
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (2 de 8):

```js

export const puedeMarcarNoAplica = (fila) => ['pendiente', 'por_definir'].includes(fila.estado);

/**
 * Igual que el backend: revisa otra persona, salvo un Administrador, que puede revisar
 * lo que él mismo marcó.
```

por:

```js

export const puedeMarcarNoAplica = (fila) => ['pendiente', 'por_definir'].includes(fila.estado);

/** Solo una fila por definir se decide como "aplica". */
export const puedeDecidirAplica = (fila) => fila.estado === 'por_definir';

/**
 * La exigencia de aprobación del "no aplica" es de la fila, que reúne todos sus orígenes.
 * No se lee de `fila.requisito`: es solo el requisito mostrado, y en un propio es null.
 */
export const requiereAprobacionNoAplica = (fila) => Boolean(fila?.requiere_aprobacion_no_aplica);

/**
 * Igual que el backend: revisa otra persona, salvo un Administrador, que puede revisar
 * lo que él mismo marcó.
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (3 de 8):

```js
    && fila.estado === 'no_aplica_pendiente'
    && (usuario.rol === 'Administrador' || fila.no_aplica?.usuario_id !== usuario.id);

/** Responsables que se pueden elegir: los activos y el que la fila ya tiene. */
export const responsablesDisponibles = (responsables, actualId) =>
    (responsables ?? []).filter((r) => r.activo || r.id === actualId);
```

por:

```js
    && fila.estado === 'no_aplica_pendiente'
    && (usuario.rol === 'Administrador' || fila.no_aplica?.usuario_id !== usuario.id);

/**
 * Acciones de aplicabilidad de una fila según su estado, en el orden en que se muestran:
 * 'aplica', 'no_aplica', 'retirar_no_aplica', 'aprobar' y 'rechazar'.
 * Como puedeRevisarNoAplica, no mira el rol ni el estado del expediente.
 */
export const accionesAplicabilidad = (fila, usuario) => {
    const acciones = [];
    if (puedeDecidirAplica(fila)) acciones.push('aplica');
    if (puedeMarcarNoAplica(fila)) acciones.push('no_aplica');
    if (['no_aplica_pendiente', 'no_aplica'].includes(fila.estado)) acciones.push('retirar_no_aplica');
    if (puedeRevisarNoAplica(fila, usuario)) acciones.push('aprobar', 'rechazar');
    return acciones;
};

/** Responsables que se pueden elegir: los activos y el que la fila ya tiene. */
export const responsablesDisponibles = (responsables, actualId) =>
    (responsables ?? []).filter((r) => r.activo || r.id === actualId);
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (4 de 8):

```js
    no_aplica_retirado: 'Retiró el "no aplica"',
    no_aplica_aprobado: 'Aprobó el "no aplica"',
    no_aplica_rechazado: 'Rechazó el "no aplica"',
};

/** Texto de una entrada del historial de un requisito. */
export const describirEvento = (entrada) => {
    const base = EVENTOS[entrada.evento] ?? entrada.evento;
```

por:

```js
    no_aplica_retirado: 'Retiró el "no aplica"',
    no_aplica_aprobado: 'Aprobó el "no aplica"',
    no_aplica_rechazado: 'Rechazó el "no aplica"',
    aplicabilidad_decidida: 'Indicó que aplica',
    requisito_propio_agregado: 'Agregó el requisito propio',
    requisito_propio_editado: 'Editó el requisito propio',
    requisito_propio_eliminado: 'Eliminó el requisito propio',
};

const CAMPOS_PROPIO = { titulo: 'título', descripcion: 'descripción', categoria: 'categoría', caracter: 'carácter' };

/** "título", "título y carácter", "título, categoría y carácter". */
const enumerar = (items) => (items.length <= 1 ? items.join('') : `${items.slice(0, -1).join(', ')} y ${items[items.length - 1]}`);

/** Texto de una entrada del historial de un requisito. */
export const describirEvento = (entrada) => {
    const base = EVENTOS[entrada.evento] ?? entrada.evento;
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (5 de 8):

```js
            return d.autoaprobado ? `${base} que él mismo marcó` : base;
        case 'no_aplica_rechazado':
            return d.motivo ? `${base}: ${d.motivo}` : base;
        default:
            return base;
    }
```

por:

```js
            return d.autoaprobado ? `${base} que él mismo marcó` : base;
        case 'no_aplica_rechazado':
            return d.motivo ? `${base}: ${d.motivo}` : base;
        case 'requisito_propio_agregado':
            return d.titulo ? `${base}: ${d.titulo}` : base;
        case 'requisito_propio_editado': {
            const campos = Object.keys(d).filter((c) => CAMPOS_PROPIO[c]).map((c) => CAMPOS_PROPIO[c]);
            return campos.length ? `${base}: ${enumerar(campos)}` : base;
        }
        case 'requisito_propio_eliminado':
            return a.titulo ? `${base}: ${a.titulo}` : base;
        default:
            return base;
    }
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (6 de 8):

```js
        }];
    });

/** Configuración del expediente en el formato que espera la API. */
export const seleccionDesde = (expediente) => ({
    fase_sgr: expediente.fase_sgr,
    sector_ids: expediente.configuracion.sectores.map((s) => s.id),
    tipo_ids: expediente.configuracion.tipos.map((t) => t.id),
```

por:

```js
        }];
    });

/** Lo que cuenta lo decide el backend: lo no aplicable y lo opcional no suman. */
const conteo = (filas) => ({
    total: filas.filter((f) => f.cuenta).length,
    cumplidos: filas.filter((f) => f.cuenta && f.estado === 'cumplido').length,
});

export const esPropio = (fila) => Boolean(fila.propio);

/** Clave de una categoría conocida, o null: lo desconocido va a "Sin categoría". */
const categoriaDe = (fila) => {
    const valor = fila.categoria ?? fila.propio?.categoria ?? fila.requisito?.categoria ?? null;
    return ETIQUETA_CATEGORIA[valor] ? valor : null;
};

export const etiquetaCategoriaFila = (fila) => ETIQUETA_CATEGORIA[categoriaDe(fila)] ?? null;

/** Filas de una plantilla simple por categoría, en el orden de CATEGORIAS y "Sin categoría" al final. */
const agruparPorCategoria = (filas) =>
    [...CATEGORIAS, [null, 'Sin categoría']]
        .map(([clave, titulo]) => {
            const deCategoria = filas.filter((f) => categoriaDe(f) === clave);
            return { clave: clave ?? 'sin', titulo, ...conteo(deCategoria), filas: deCategoria };
        })
        .filter((c) => c.filas.length > 0);

/**
 * Bloques del checklist, en el orden de las filas (el del backend: la principal primero):
 * - `sgr`: una plantilla con motor sgr, por sección como siempre (agruparRequisitos);
 * - `simple`: otra plantilla, por categoría;
 * - `propios`: los requisitos propios, siempre al final.
 * La plantilla de una fila es la de su primer origen, la que muestra el backend.
 */
export const agruparChecklist = (filas) => {
    const porPlantilla = new Map();
    filas.filter((f) => !esPropio(f)).forEach((f) => {
        const clave = f.plantilla?.id ?? 'sin-plantilla';
        if (!porPlantilla.has(clave)) porPlantilla.set(clave, { plantilla: f.plantilla ?? null, filas: [] });
        porPlantilla.get(clave).filas.push(f);
    });

    const bloques = [...porPlantilla.entries()].map(([clave, { plantilla, filas: dePlantilla }]) => (
        plantilla?.motor === MOTOR_SGR
            ? { tipo: 'sgr', clave: `plantilla-${clave}`, plantilla, ...conteo(dePlantilla), secciones: agruparRequisitos(dePlantilla) }
            : { tipo: 'simple', clave: `plantilla-${clave}`, plantilla, ...conteo(dePlantilla), categorias: agruparPorCategoria(dePlantilla) }
    ));

    const propios = filas.filter(esPropio);
    if (propios.length > 0) {
        bloques.push({ tipo: 'propios', clave: 'propios', titulo: TITULO_PROPIOS, ...conteo(propios), filas: propios });
    }
    return bloques;
};

/** Lo que se muestra de una fila, sea de plantilla o propia. Un propio no tiene código ni fuente. */
export const contenidoFila = (fila) => {
    const r = fila.requisito;
    if (esPropio(fila) || !r) {
        return {
            propio: true,
            codigo: null,
            fuente: null,
            texto: fila.propio?.titulo ?? '',
            nota: fila.propio?.descripcion ?? null,
            categoria: etiquetaCategoriaFila(fila),
            cargoRevision: null,
            cargoCumplimiento: null,
            descripcion: null,
            aplicaSi: null,
            soporteRequerido: null,
        };
    }
    // Cuándo aplica, en palabras: solo rige a un condicionado (o a una fila aún por definir).
    const condicionado = fila.caracter === 'condicionado' || fila.estado === 'por_definir';
    return {
        propio: false,
        codigo: r.codigo ?? null,
        fuente: ETIQUETA_FUENTE[r.fuente] ?? null,
        texto: r.texto ?? '',
        nota: r.nota ?? null,
        categoria: etiquetaCategoriaFila(fila),
        cargoRevision: r.cargo_revision ?? null,
        cargoCumplimiento: r.cargo_cumplimiento ?? null,
        descripcion: r.descripcion ?? null,
        aplicaSi: condicionado ? (r.condicion_descripcion ?? null) : null,
        soporteRequerido: r.soporte_requerido ?? null,
    };
};

/** Texto del recuadro "Por definir": cuándo aplica o, si la plantilla no lo dice, que falta decidirlo. */
export const textoPorDefinir = (fila) => {
    const { aplicaSi } = contenidoFila(fila);
    return aplicaSi ? `Aplica si: ${aplicaSi}` : 'Falta decidir si este requisito aplica al proyecto.';
};

/**
 * Lista de expedientes del proyecto (GET /proyectos/{id}/expedientes) con uno de ellos al día:
 * toma de la respuesta del expediente solo los campos que la lista ya tiene, como sus plantillas.
 */
export const actualizarExpedienteEnLista = (lista, expediente) => lista?.map((e) => (
    e.id === expediente.id
        ? { ...e, ...Object.fromEntries(Object.keys(e).filter((k) => k in expediente).map((k) => [k, expediente[k]])) }
        : e
));

/** "Plantillas: SGR, Obras de agua", "Plantilla: SGR" o "Sin plantillas". Acepta nombres u objetos con `nombre`. */
export const textoPlantillas = (plantillas) => {
    const nombres = (plantillas ?? []).map((p) => (typeof p === 'string' ? p : p.nombre)).filter(Boolean);
    if (nombres.length === 0) return 'Sin plantillas';
    return `${nombres.length === 1 ? 'Plantilla' : 'Plantillas'}: ${nombres.join(', ')}`;
};

/** Con más de un origen, las demás plantillas de las que viene la fila: "También en: Vías, Riego". */
export const textoOrigenes = (fila) => {
    const origenes = fila.origenes ?? [];
    if (origenes.length < 2) return null;
    const mostrada = origenes[0].plantilla_id;
    const otras = [...new Set(origenes.filter((o) => o.plantilla_id !== mostrada).map((o) => o.nombre))];
    return otras.length ? `También en: ${otras.join(', ')}` : null;
};

export const CARACTERES_PROPIO = [['obligatorio', 'Obligatorio'], ['opcional', 'Opcional']];

/** Formulario de un requisito propio: vacío para uno nuevo, o con los datos de la fila. */
export const formularioPropio = (fila) => ({
    titulo: fila?.propio?.titulo ?? '',
    descripcion: fila?.propio?.descripcion ?? '',
    categoria: fila?.propio?.categoria ?? '',
    caracter: fila?.caracter === 'opcional' ? 'opcional' : 'obligatorio',
});

/** Lo que espera la API: textos recortados y lo vacío como null. */
export const datosPropio = (formulario) => ({
    titulo: formulario.titulo.trim(),
    descripcion: formulario.descripcion.trim() || null,
    categoria: formulario.categoria || null,
    caracter: formulario.caracter,
});

/** Igual que el backend: un propio cumplido o con soporte no se elimina. Null si se puede. */
export const motivoNoEliminarPropio = (fila) => {
    if (fila.cumplido) return 'El requisito está cumplido: desmárcalo antes de eliminarlo.';
    if (fila.soporte) return 'El requisito tiene un soporte: quítalo antes de eliminarlo.';
    return null;
};

/**
 * Configuración del expediente en el formato que espera la API. Lleva siempre las plantillas
 * (la principal primero, como las entrega el backend) y la configuración SGR, aunque no haya
 * plantilla sgr: así no se pierde si se quita y se vuelve a poner.
 */
export const seleccionDesde = (expediente) => ({
    plantilla_ids: (expediente.plantillas ?? []).map((p) => p.plantilla_id),
    principal_id: (expediente.plantillas ?? []).find((p) => p.es_principal)?.plantilla_id ?? null,
    fase_sgr: expediente.fase_sgr,
    sector_ids: expediente.configuracion.sectores.map((s) => s.id),
    tipo_ids: expediente.configuracion.tipos.map((t) => t.id),
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (7 de 8):

```js
const lista = (items) => (items.length ? items.map((i) => i.nombre).join('; ') : '—');
const soloFecha = (iso) => (iso ? String(iso).slice(0, 10) : '');

/** Filas de las dos hojas del Excel: resumen del expediente y checklist. */
export const construirHojasExcel = (expediente) => {
    const { configuracion: c, progreso: p } = expediente;
```

por:

```js
const lista = (items) => (items.length ? items.map((i) => i.nombre).join('; ') : '—');
const soloFecha = (iso) => (iso ? String(iso).slice(0, 10) : '');

/** "SGR (v1), Obras de agua (v2)" o "Sin plantillas". */
const plantillasConVersion = (plantillas) => (
    (plantillas ?? []).length
        ? plantillas.map((pl) => (pl.version ? `${pl.nombre} (v${pl.version.numero})` : pl.nombre)).join(', ')
        : 'Sin plantillas'
);

/**
 * Filas de las dos hojas del Excel: resumen del expediente y checklist. La fase y la
 * configuración SGR solo van si el expediente tiene una plantilla con motor sgr.
 */
export const construirHojasExcel = (expediente) => {
    const { configuracion: c, progreso: p } = expediente;
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (8 de 8):

```js
        ['Expediente', `#${expediente.id}`],
        ['Estado', ETIQUETA_ESTADO[expediente.estado] ?? expediente.estado],
        ['Fecha de radicación', soloFecha(expediente.fecha_radicado) || '—'],
        ['Versión del catálogo', `v${expediente.catalogo_version.numero}`],
        ['Fase SGR', faseLegible(expediente.fase_sgr)],
        ['Sectores', lista(c.sectores)],
        ['Tipos de proyecto', lista(c.tipos)],
        ['Componentes', lista(c.componentes)],
        ['Tipologías', lista(c.tipologias)],
        ['Avance', `${p.cumplidos} de ${p.total} (${p.porcentaje}%)`],
    ].map(([Campo, Valor]) => ({ Campo, Valor }));

    const checklist = expediente.requisitos.map(({ requisito: r, ...f }) => ({
        'Sección': `${SECCIONES[r.seccion].num} ${SECCIONES[r.seccion].titulo}`,
        'Sector': r.sector?.nombre ?? '',
        'Tipo de proyecto': r.tipo?.nombre ?? '',
        'Subtítulo': r.subtitulo ?? '',
        'Código': r.codigo,
        'Fuente': ETIQUETA_FUENTE[r.fuente] ?? r.fuente,
        'Requisito': r.texto,
        'Nota': r.nota ?? '',
        'Estado': ETIQUETA_ESTADO_REQUISITO[f.estado] ?? f.estado,
        'Cumplido': f.cumplido ? 'Sí' : 'No',
        'Fecha de cumplimiento': soloFecha(f.fecha_cumplido),
        'Marcado por': f.usuario_cumplido ?? '',
        'Responsable': f.responsable?.nombre ?? '',
        'Justificación de no aplica': f.no_aplica?.justificacion ?? '',
        'Aprobado por': f.no_aplica?.aprobado_por ?? '',
        'Observación': f.observacion ?? '',
        'Soporte': f.soporte?.nombre ?? '',
    }));

    return { resumen, checklist };
};

export const nombreArchivoExcel = (expediente) => {
    const proyecto = expediente.proyecto.nombre
        .normalize('NFD')
```

por:

```js
        ['Expediente', `#${expediente.id}`],
        ['Estado', ETIQUETA_ESTADO[expediente.estado] ?? expediente.estado],
        ['Fecha de radicación', soloFecha(expediente.fecha_radicado) || '—'],
        ['Plantillas', plantillasConVersion(expediente.plantillas)],
        ...(expediente.tiene_sgr ? [
            ['Fase SGR', faseLegible(expediente.fase_sgr)],
            ['Sectores', lista(c.sectores)],
            ['Tipos de proyecto', lista(c.tipos)],
            ['Componentes', lista(c.componentes)],
            ['Tipologías', lista(c.tipologias)],
        ] : []),
        ['Avance', `${p.cumplidos} de ${p.total} (${p.porcentaje}%)`],
    ].map(([Campo, Valor]) => ({ Campo, Valor }));

    const checklist = expediente.requisitos.map((f) => {
        const r = f.requisito;
        const contenido = contenidoFila(f);
        // La sección solo es del motor sgr; una plantilla simple copiada de la SGR puede conservarla.
        const seccion = !f.plantilla || f.plantilla.motor === MOTOR_SGR ? SECCIONES[r?.seccion] : null;
        return {
            'Plantilla': contenido.propio ? 'Propio del proyecto' : (f.plantilla?.nombre ?? ''),
            'Sección': seccion ? `${seccion.num} ${seccion.titulo}` : '',
            'Categoría': contenido.categoria ?? '',
            'Sector': r?.sector?.nombre ?? '',
            'Tipo de proyecto': r?.tipo?.nombre ?? '',
            'Subtítulo': r?.subtitulo ?? '',
            'Código': contenido.codigo ?? '',
            'Fuente': contenido.fuente ?? '',
            'Requisito': contenido.texto,
            'Nota': contenido.nota ?? '',
            'Carácter': ETIQUETA_CARACTER[f.caracter] ?? '',
            'Estado': ETIQUETA_ESTADO_REQUISITO[f.estado] ?? f.estado,
            'Cumplido': f.cumplido ? 'Sí' : 'No',
            'Fecha de cumplimiento': soloFecha(f.fecha_cumplido),
            'Marcado por': f.usuario_cumplido ?? '',
            'Responsable': f.responsable?.nombre ?? '',
            'Justificación de no aplica': f.no_aplica?.justificacion ?? '',
            'Aprobado por': f.no_aplica?.aprobado_por ?? '',
            'Observación': f.observacion ?? '',
            'Soporte': f.soporte?.nombre ?? '',
        };
    });

    return { resumen, checklist };
};

/** Ancho de cada columna de la hoja de checklist, en el orden de construirHojasExcel (20 columnas). */
export const ANCHOS_HOJA_CHECKLIST = [24, 28, 14, 26, 30, 30, 14, 8, 80, 40, 14, 18, 10, 14, 22, 22, 40, 22, 40, 26];

export const nombreArchivoExcel = (expediente) => {
    const proyecto = expediente.proyecto.nombre
        .normalize('NFD')
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (1 de 1):

```js
// Lógica de presentación de la administración de plantillas de checklist.
// Funciones puras, probadas en plantillasChecklist.test.js. Las reglas las valida el backend.

import { ORDEN_SECCIONES, SECCIONES, mensajeError } from './expedienteChecklist';

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
```

por:

```js
// Lógica de presentación de la administración de plantillas de checklist.
// Funciones puras, probadas en plantillasChecklist.test.js. Las reglas las valida el backend.

import { CATEGORIAS, ORDEN_SECCIONES, SECCIONES, mensajeError } from './expedienteChecklist';

// La lista vive en expedienteChecklist.js porque el checklist del expediente también agrupa por categoría.
export { CATEGORIAS };

export const TIPOS_REQUISITO = [
    ['documental', 'Documental'],
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npm test`
Expected: pasan todas, 253.

- [ ] **Paso 5: Commit**

```bash
git add resources/js/utils/plantillasExpediente.js resources/js/utils/plantillasExpediente.test.js resources/js/utils/expedienteChecklist.js resources/js/utils/expedienteChecklist.test.js resources/js/utils/plantillasChecklist.js
git commit -m "feat: screen logic for templates, grouping and own requirements"
```

---

### Tarea 10: Plantillas en la configuración del expediente

Sin pruebas de componentes en el repositorio: se comprueba en el navegador en la tarea 15.

**Files:**
- Modify: `resources/css/ExpedienteViabilizacion.css`
- Modify: `resources/js/components/ExpedienteConfiguracion.jsx`

- [ ] **Paso 1: Implementar**

En `resources/css/ExpedienteViabilizacion.css`, reemplazar (1 de 1):

```css
.exv-opt input:disabled { cursor: not-allowed; }
.exv-opt-sub { display: block; font-size: .74rem; color: var(--exv-tinta-tenue); }

.exv-seg { display: flex; gap: .4rem; clear: both; }
.exv-seg label {
    position: relative;
```

por:

```css
.exv-opt input:disabled { cursor: not-allowed; }
.exv-opt-sub { display: block; font-size: .74rem; color: var(--exv-tinta-tenue); }

/* Plantillas del expediente: primer bloque del panel */
.exv-config-aviso, .exv-config-nota { margin: 0 0 .6rem; font-size: .78rem; line-height: 1.45; }
.exv-config-aviso { padding: .4rem .55rem; color: var(--exv-tinta); background: var(--exv-alerta-tinte); border-left: 3px solid var(--exv-alerta); border-radius: 2px; }
.exv-config-nota { color: var(--exv-tinta-tenue); }
.exv-plantilla { padding: .5rem .6rem; margin-bottom: .5rem; border: 1px solid var(--exv-linea-suave); border-radius: var(--exv-radio); }
.exv-plantilla:last-child { margin-bottom: 0; }
.exv-plantilla.marcada { border-color: var(--gp-azul); background: var(--exv-a12-tinte); }
.exv-plantilla .exv-opt { padding: 0; }
.exv-plantilla-nombre { font-weight: 700; color: var(--exv-tinta); }
.exv-plantilla-aviso {
    display: inline-block;
    margin-left: .4rem;
    padding: .02rem .35rem;
    font-size: .64rem;
    font-weight: 600;
    color: var(--exv-tinta);
    background: var(--exv-alerta-tinte);
    border-radius: 2px;
}
.exv-plantilla-detalle { margin: .3rem 0 0 calc(15px + .6rem); font-size: .74rem; line-height: 1.4; color: var(--exv-tinta-suave); }
.exv-plantilla-detalle p { margin: 0 0 .25rem; }
.exv-plantilla-detalle dl { margin: 0; }
.exv-plantilla-detalle dl div { display: flex; gap: .35rem; }
.exv-plantilla-detalle dt { flex-shrink: 0; font-weight: 600; color: var(--exv-tinta-tenue); }
.exv-plantilla-detalle dd { margin: 0; min-width: 0; overflow-wrap: anywhere; }
.exv-plantilla-motivo { margin-top: .3rem !important; font-style: italic; color: var(--exv-tinta-tenue); }
.exv-plantilla-principal {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    margin: .35rem 0 0 calc(15px + .6rem);
    font-size: .74rem;
    font-weight: 600;
    color: var(--exv-bronce-oscuro);
    cursor: pointer;
}
span.exv-plantilla-principal { cursor: default; }
.exv-plantilla-principal input { margin: 0; accent-color: var(--gp-azul); }
.exv-panel-sgr { border-top: 1px solid var(--exv-linea-suave); }
.exv-panel-sgr-titulo {
    margin: 0;
    padding: .8rem 1.1rem 0;
    font-size: .76rem;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--exv-tinta);
}

.exv-seg { display: flex; gap: .4rem; clear: both; }
.exv-seg label {
    position: relative;
```

En `resources/js/components/ExpedienteConfiguracion.jsx`, reemplazar (1 de 3):

```jsx
import React from 'react';
import { FASES, alternarId, alternarSector } from '../utils/expedienteChecklist';

// Opciones del catálogo vigente más lo ya elegido que se haya desactivado después:
// un expediente debe poder seguir mostrando (y soltar) lo que ya tenía.
```

por:

```jsx
import React from 'react';
import { FASES, alternarId, alternarSector } from '../utils/expedienteChecklist';
import {
    AVISO_SIN_SECTOR, alternarPlantilla, avisosPlantilla, detallePlantilla, elegirPrincipal, incluyeSgr, opcionPlantilla,
} from '../utils/plantillasExpediente';

// Opciones del catálogo vigente más lo ya elegido que se haya desactivado después:
// un expediente debe poder seguir mostrando (y soltar) lo que ya tenía.
```

En `resources/js/components/ExpedienteConfiguracion.jsx`, reemplazar (2 de 3):

```jsx
    </label>
);

const ExpedienteConfiguracion = ({ catalogo, expediente, seleccion, deshabilitada, recalculando, onCambiar }) => {
    const { configuracion, progreso } = expediente;
    const sectores = conSeleccionadas(catalogo.sectores, configuracion.sectores);
    const componentes = conSeleccionadas(catalogo.componentes, configuracion.componentes);
    const tipologias = conSeleccionadas(catalogo.tipologias, configuracion.tipologias);
    const sectoresMarcados = sectores.filter((s) => seleccion.sector_ids.includes(s.id));

    const tiposDe = (sector) => conSeleccionadas(
        sector.tipos ?? [],
```

por:

```jsx
    </label>
);

/** Una plantilla disponible: casilla, opción de principal, datos y avisos. */
const OpcionPlantilla = ({ plantilla, plantillasExpediente, opcion, esPrincipal, varias, deshabilitada, onAlternar, onPrincipal }) => {
    const detalle = detallePlantilla(plantilla, plantillasExpediente);
    const avisos = avisosPlantilla(plantilla);
    const idDetalle = `exv-plantilla-${plantilla.id}-detalle`;

    return (
        <div className={`exv-plantilla${opcion.marcada ? ' marcada' : ''}`}>
            <label className="exv-opt">
                <input
                    type="checkbox"
                    checked={opcion.marcada}
                    disabled={deshabilitada || opcion.deshabilitada}
                    aria-describedby={idDetalle}
                    onChange={onAlternar}
                />
                <span>
                    <b className="exv-plantilla-nombre">{plantilla.nombre}</b>
                    {avisos.map((aviso) => <span key={aviso} className="exv-plantilla-aviso">{aviso}</span>)}
                </span>
            </label>
            <div id={idDetalle} className="exv-plantilla-detalle">
                {plantilla.descripcion && <p>{plantilla.descripcion}</p>}
                <dl>
                    <div><dt>Fuentes</dt><dd>{detalle.fuentes}</dd></div>
                    <div><dt>Sectores</dt><dd>{detalle.sectores}</dd></div>
                    <div><dt>Requisitos</dt><dd>{detalle.requisitos} · {detalle.version}</dd></div>
                </dl>
                {opcion.motivo && <p className="exv-plantilla-motivo">{opcion.motivo}</p>}
            </div>
            {opcion.marcada && (varias ? (
                <label className="exv-plantilla-principal">
                    <input
                        type="radio"
                        name="exv-plantilla-principal"
                        checked={esPrincipal}
                        disabled={deshabilitada}
                        aria-label={`Usar ${plantilla.nombre} como plantilla principal`}
                        onChange={onPrincipal}
                    />
                    Principal
                </label>
            ) : <span className="exv-plantilla-principal">Principal</span>)}
        </div>
    );
};

/** Primer bloque del panel: las plantillas que se ofrecen al proyecto y las que ya tiene el expediente. */
const BloquePlantillas = ({ disponibles, errorPlantillas, onReintentar, expediente, seleccion, deshabilitada, onCambiar }) => {
    if (errorPlantillas) {
        return (
            <fieldset className="exv-plantillas">
                <legend>Plantillas</legend>
                <p className="exv-config-aviso" role="alert">No se pudieron cargar las plantillas. {errorPlantillas}</p>
                {onReintentar && <button type="button" className="exv-mini" onClick={onReintentar}>Reintentar</button>}
            </fieldset>
        );
    }
    if (!disponibles) {
        return (
            <fieldset className="exv-plantillas">
                <legend>Plantillas</legend>
                <p className="exv-config-nota">Cargando plantillas…</p>
            </fieldset>
        );
    }

    const lista = disponibles.plantillas;
    const varias = seleccion.plantilla_ids.length > 1;

    return (
        <fieldset className="exv-plantillas">
            <legend>Plantillas <small>(definen el checklist)</small></legend>
            {disponibles.sin_sector && <p className="exv-config-aviso">{AVISO_SIN_SECTOR}.</p>}
            {lista.length === 0 ? (
                <p className="exv-config-nota">
                    Ninguna plantilla se ofrece a este proyecto. Revisa sus fuentes de financiación y su sector, o pide a un Administrador que asocie una plantilla.
                </p>
            ) : lista.map((p) => (
                <OpcionPlantilla
                    key={p.id}
                    plantilla={p}
                    plantillasExpediente={expediente.plantillas}
                    opcion={opcionPlantilla(p, seleccion, expediente.plantillas, lista, { configurado: expediente.configurado })}
                    esPrincipal={seleccion.principal_id === p.id}
                    varias={varias}
                    deshabilitada={deshabilitada}
                    onAlternar={() => onCambiar(alternarPlantilla(seleccion, p.id))}
                    onPrincipal={() => onCambiar(elegirPrincipal(seleccion, p.id))}
                />
            ))}
        </fieldset>
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
```

En `resources/js/components/ExpedienteConfiguracion.jsx`, reemplazar (3 de 3):

```jsx
                <span>{recalculando ? 'Recalculando…' : `${progreso.total} ${progreso.total === 1 ? 'requisito' : 'requisitos'}`}</span>
            </div>

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
        </aside>
    );
};
```

por:

```jsx
                <span>{recalculando ? 'Recalculando…' : `${progreso.total} ${progreso.total === 1 ? 'requisito' : 'requisitos'}`}</span>
            </div>

            <BloquePlantillas
                disponibles={disponibles}
                errorPlantillas={errorPlantillas}
                onReintentar={onReintentarPlantillas}
                expediente={expediente}
                seleccion={seleccion}
                deshabilitada={deshabilitada}
                onCambiar={onCambiar}
            />

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
    );
};
```

- [ ] **Paso 2: Comprobar que nada se rompió**

Run: `npm test`
Expected: pasan todas, 253.

- [ ] **Paso 3: Commit**

```bash
git add resources/css/ExpedienteViabilizacion.css resources/js/components/ExpedienteConfiguracion.jsx
git commit -m "feat: choose the templates of an expediente and show the SGR panel only with SGR"
```

---

### Tarea 11: Checklist por plantilla, "Aplica" y requisitos propios

Sin pruebas de componentes en el repositorio: se comprueba en el navegador en la tarea 15.

**Files:**
- Modify: `resources/css/ExpedienteViabilizacion.css`
- Modify: `resources/js/components/ExpedienteChecklist.jsx`

- [ ] **Paso 1: Implementar**

En `resources/css/ExpedienteViabilizacion.css`, reemplazar (1 de 4):

```css
    border-bottom: 2px solid var(--exv-tinta);
}
.exv-num { flex-shrink: 0; padding: .12rem .5rem; font-size: .78rem; font-weight: 600; color: var(--exv-sobre-acento); background: var(--exv-bronce); border-radius: 2px; }
.exv-seccion-head h3 { flex: 1; margin: 0; font-size: 1.06rem; font-weight: 700; color: var(--exv-tinta); }
.exv-cita { font-size: .74rem; text-align: right; color: var(--exv-tinta-tenue); }
.exv-subhead {
    margin: .9rem 0 .35rem;
```

por:

```css
    border-bottom: 2px solid var(--exv-tinta);
}
.exv-num { flex-shrink: 0; padding: .12rem .5rem; font-size: .78rem; font-weight: 600; color: var(--exv-sobre-acento); background: var(--exv-bronce); border-radius: 2px; }
.exv-seccion-head h3, .exv-seccion-head h4 { flex: 1; margin: 0; font-size: 1.06rem; font-weight: 700; color: var(--exv-tinta); }
.exv-cita { font-size: .74rem; text-align: right; color: var(--exv-tinta-tenue); }
.exv-subhead {
    margin: .9rem 0 .35rem;
```

En `resources/css/ExpedienteViabilizacion.css`, reemplazar (2 de 4):

```css
.exv-mini:hover { border-color: var(--exv-a12); color: var(--exv-a12); }
.exv-mini-peligro:hover { border-color: var(--exv-alerta); color: var(--exv-alerta); }
.exv-mini-exito:hover { border-color: var(--exv-verde); color: var(--exv-verde); }
.exv-observacion, .exv-observacion-lectura {
    display: block;
    width: calc(100% - 17px - .8rem);
```

por:

```css
.exv-mini:hover { border-color: var(--exv-a12); color: var(--exv-a12); }
.exv-mini-peligro:hover { border-color: var(--exv-alerta); color: var(--exv-alerta); }
.exv-mini-exito:hover { border-color: var(--exv-verde); color: var(--exv-verde); }
.exv-mini:disabled { opacity: .55; cursor: not-allowed; }
.exv-observacion, .exv-observacion-lectura {
    display: block;
    width: calc(100% - 17px - .8rem);
```

En `resources/css/ExpedienteViabilizacion.css`, reemplazar (3 de 4):

```css
.exv-vacio .exv-btn { margin-top: 1rem; }
.exv-pie { margin: 1.5rem 0 0; font-size: .78rem; line-height: 1.5; color: var(--exv-tinta-tenue); }

@media (max-width: 920px) {
    .exv-shell { grid-template-columns: minmax(0, 1fr); gap: 1rem; }
    .exv-config { position: static; max-height: none; }
```

por:

```css
.exv-vacio .exv-btn { margin-top: 1rem; }
.exv-pie { margin: 1.5rem 0 0; font-size: .78rem; line-height: 1.5; color: var(--exv-tinta-tenue); }

/* Checklist con varias plantillas y requisitos propios */
.exv-bloque { margin-bottom: 1.75rem; }
.exv-plantilla-titulo {
    display: flex;
    align-items: baseline;
    gap: .75rem;
    margin-bottom: .75rem;
    padding: .45rem .75rem;
    background: var(--exv-a12-tinte);
    border-left: 3px solid var(--gp-azul);
    border-radius: 2px;
}
.exv-plantilla-titulo h3 { flex: 1; margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--exv-tinta); }
.exv-item-origenes { margin-top: .25rem; font-size: .76rem; color: var(--exv-tinta-tenue); }
.exv-item-condicion { margin-top: .25rem; font-size: .8rem; color: var(--exv-tinta-suave); }
.exv-item-condicion b { font-weight: 600; color: var(--exv-tinta); }
.exv-por-definir { border-left-color: var(--exv-bronce); }
.exv-item-descripcion { display: block; margin-top: .25rem; font-size: .82rem; color: var(--exv-tinta-suave); white-space: pre-wrap; }
.exv-estado-propio { color: var(--exv-a12); background: var(--exv-a12-tinte); }
.exv-duplicados ul { margin: .4rem 0 0; padding-left: 1.1rem; }
.exv-duplicados li { margin-top: .2rem; }
.exv-duplicados-similitud { color: var(--exv-tinta-suave); }
.exv-propios-vacio { margin: 0 0 .75rem; font-size: .85rem; color: var(--exv-tinta-suave); }
.exv-propio-form {
    display: grid;
    gap: .7rem;
    padding: .9rem 1rem;
    margin-bottom: .6rem;
    background: var(--exv-tarjeta);
    border: 1px solid var(--gp-azul);
    border-radius: var(--exv-radio);
}
.exv-campo { display: grid; gap: .25rem; margin: 0; font-size: .82rem; font-weight: 600; color: var(--exv-tinta); }
.exv-campo small { font-weight: 400; color: var(--exv-tinta-tenue); }
.exv-campo textarea, .exv-campo select {
    width: 100%;
    box-sizing: border-box;
    padding: .4rem .55rem;
    font: inherit;
    font-weight: 400;
    color: var(--exv-tinta);
    background: var(--exv-control);
    border: 1px solid var(--exv-linea);
    border-radius: var(--exv-radio);
}
.exv-campo textarea { resize: vertical; }
.exv-campo textarea:focus, .exv-campo select:focus { outline: 2px solid var(--exv-a12); outline-offset: 1px; }
.exv-campo-grupo { margin: 0; padding: 0; border: 0; font-size: .82rem; color: var(--exv-tinta); }
.exv-campo-grupo legend { margin-bottom: .25rem; padding: 0; font-weight: 600; }
.exv-campo-grupo label { display: inline-flex; align-items: center; gap: .35rem; margin: 0 1rem 0 0; font-weight: 400; cursor: pointer; }
.exv-campo-ayuda { margin: .3rem 0 0; font-size: .76rem; color: var(--exv-tinta-tenue); }
.exv-propio-acciones { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .5rem; }

@media (max-width: 920px) {
    .exv-shell { grid-template-columns: minmax(0, 1fr); gap: 1rem; }
    .exv-config { position: static; max-height: none; }
```

En `resources/css/ExpedienteViabilizacion.css`, reemplazar (4 de 4):

```css
        --exv-a12: #3A5A8C;
        --exv-a12-tinte: #E4EAF3;
    }
    .gp-riel, .gp-barra-superior, .gp-barra-inferior, .exv-config, .exv-barra, .exv-no-imprimir, .exv-item-acciones, .exv-observacion, .exv-observacion-lectura, .exv-responsable, .exv-historial, .exv-historial-vacio { display: none !important; }
    /* El body de la app tiene un degradado que saldría con "gráficos de fondo". */
    html, body { background: #fff !important; }
    .gp-app { display: block !important; }
```

por:

```css
        --exv-a12: #3A5A8C;
        --exv-a12-tinte: #E4EAF3;
    }
    .gp-riel, .gp-barra-superior, .gp-barra-inferior, .exv-config, .exv-barra, .exv-no-imprimir, .exv-propio-form, .exv-item-acciones, .exv-observacion, .exv-observacion-lectura, .exv-responsable, .exv-historial, .exv-historial-vacio { display: none !important; }
    /* El body de la app tiene un degradado que saldría con "gráficos de fondo". */
    html, body { background: #fff !important; }
    .gp-app { display: block !important; }
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (1 de 10):

```jsx
import React, { useMemo, useState } from 'react';
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

por:

```jsx
import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
    faPaperclip, faDownload, faTrash, faCommentDots, faBan, faRotateLeft, faCheck, faXmark, faClockRotateLeft, faCircleCheck,
    faPen, faPlus,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    CARACTERES_PROPIO,
    CATEGORIAS,
    ETIQUETA_ESTADO_REQUISITO,
    TITULO_PROPIOS,
    accionesAplicabilidad,
    agruparChecklist,
    contenidoFila,
    formularioPropio,
    puedeMarcarRequisito,
    responsablesDisponibles,
    textoOrigenes,
    textoPorDefinir,
} from '../utils/expedienteChecklist';
import ExpedienteHistorialRequisito from './ExpedienteHistorialRequisito';
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (2 de 10):

```jsx

const fechaCorta = (iso) => (iso ? new Date(iso).toLocaleDateString('es-CO') : '');

const ItemRequisito = ({
    fila, editable, usuario, responsables, onMarcar, onObservacion, onSubirSoporte, onDescargarSoporte, onEliminarSoporte,
    onNoAplica, onRetirarNoAplica, onAprobarNoAplica, onRechazarNoAplica, onResponsable, onCargarHistorial, onDescargarHistorial,
}) => {
    const { requisito: r } = fila;
    const [viendoObservacion, setViendoObservacion] = useState(Boolean(fila.observacion));
    const [viendoHistorial, setViendoHistorial] = useState(false);
    const marcadoNoAplica = ['no_aplica_pendiente', 'no_aplica'].includes(fila.estado);
    const disponibles = responsablesDisponibles(responsables, fila.responsable?.id ?? null);
    // Si la lista falla o aún no llega, el asignado se muestra igual.
    const opciones = fila.responsable && !disponibles.some((p) => p.id === fila.responsable.id)
```

por:

```jsx

const fechaCorta = (iso) => (iso ? new Date(iso).toLocaleDateString('es-CO') : '');

/** Nombre corto de una fila para las etiquetas accesibles: su código o el comienzo de su texto. */
const nombreFila = (contenido) => contenido.codigo ?? (contenido.texto.length > 60 ? `${contenido.texto.slice(0, 60)}…` : contenido.texto);

/** Textos parecidos entre plantillas distintas. Solo avisan: el sistema nunca los fusiona. */
export const AvisosDuplicados = ({ avisos }) => {
    if (!avisos?.length) return null;
    return (
        <div className="exv-aviso exv-duplicados exv-no-imprimir" role="status">
            <b>{avisos.length === 1 ? 'Un requisito podría estar repetido' : `${avisos.length} requisitos podrían estar repetidos`}</b>{' '}
            entre plantillas: sus textos se parecen, pero no comparten código común. Revísalos; el sistema no los une.
            <ul>
                {avisos.map((aviso) => (
                    <li key={aviso.filas.join('-')}>
                        «{aviso.textos[0]}» ({aviso.plantillas[0]}) y «{aviso.textos[1]}» ({aviso.plantillas[1]})
                        <span className="exv-duplicados-similitud"> · {Math.round(aviso.similitud * 100)} % de palabras en común</span>
                    </li>
                ))}
            </ul>
        </div>
    );
};

/** Formulario de un requisito propio, para agregarlo o editarlo. */
const FormularioPropio = ({ fila, ocupado, onGuardar, onCancelar }) => {
    const [formulario, setFormulario] = useState(() => formularioPropio(fila));
    const titulo = useRef(null);
    const prefijo = `exv-propio-${fila?.id ?? 'nuevo'}`;
    const cambiar = (campo, valor) => setFormulario((f) => ({ ...f, [campo]: valor }));

    useEffect(() => { titulo.current?.focus(); }, []);

    return (
        <form
            className="exv-propio-form"
            aria-label={fila ? 'Editar requisito propio' : 'Agregar requisito propio'}
            onSubmit={(e) => { e.preventDefault(); onGuardar(formulario); }}
        >
            <label className="exv-campo" htmlFor={`${prefijo}-titulo`}>
                <span>Título</span>
                <textarea
                    id={`${prefijo}-titulo`}
                    ref={titulo}
                    rows={2}
                    maxLength={500}
                    required
                    value={formulario.titulo}
                    onChange={(e) => cambiar('titulo', e.target.value)}
                />
            </label>
            <label className="exv-campo" htmlFor={`${prefijo}-descripcion`}>
                <span>Descripción <small>(opcional)</small></span>
                <textarea
                    id={`${prefijo}-descripcion`}
                    rows={3}
                    maxLength={2000}
                    value={formulario.descripcion}
                    onChange={(e) => cambiar('descripcion', e.target.value)}
                />
            </label>
            <label className="exv-campo" htmlFor={`${prefijo}-categoria`}>
                <span>Categoría</span>
                <select id={`${prefijo}-categoria`} value={formulario.categoria} onChange={(e) => cambiar('categoria', e.target.value)}>
                    <option value="">Sin categoría</option>
                    {CATEGORIAS.map(([valor, texto]) => <option key={valor} value={valor}>{texto}</option>)}
                </select>
            </label>
            <fieldset className="exv-campo-grupo" aria-describedby={`${prefijo}-ayuda`}>
                <legend>Carácter</legend>
                {CARACTERES_PROPIO.map(([valor, texto]) => (
                    <label key={valor}>
                        <input
                            type="radio"
                            name={`${prefijo}-caracter`}
                            value={valor}
                            checked={formulario.caracter === valor}
                            onChange={() => cambiar('caracter', valor)}
                        />
                        {texto}
                    </label>
                ))}
                <p id={`${prefijo}-ayuda`} className="exv-campo-ayuda">
                    Un requisito opcional se puede marcar, pero no cuenta en el avance ni bloquea la radicación.
                </p>
            </fieldset>
            <div className="exv-propio-acciones">
                <button type="button" className="exv-btn" onClick={onCancelar} disabled={ocupado}>Cancelar</button>
                <button type="submit" className="exv-btn exv-btn-primario" disabled={ocupado || !formulario.titulo.trim()}>
                    {fila ? 'Guardar cambios' : 'Agregar requisito'}
                </button>
            </div>
        </form>
    );
};

const ItemRequisito = ({
    fila, editable, usuario, responsables, ocupado, onMarcar, onObservacion, onSubirSoporte, onDescargarSoporte, onEliminarSoporte,
    onAplica, onNoAplica, onRetirarNoAplica, onAprobarNoAplica, onRechazarNoAplica, onResponsable, onCargarHistorial, onDescargarHistorial,
    onEditarPropio, onEliminarPropio,
}) => {
    const contenido = contenidoFila(fila);
    const [viendoObservacion, setViendoObservacion] = useState(Boolean(fila.observacion));
    const [viendoHistorial, setViendoHistorial] = useState(false);
    const acciones = editable ? accionesAplicabilidad(fila, usuario) : [];
    const origenes = textoOrigenes(fila);
    const disponibles = responsablesDisponibles(responsables, fila.responsable?.id ?? null);
    // Si la lista falla o aún no llega, el asignado se muestra igual.
    const opciones = fila.responsable && !disponibles.some((p) => p.id === fila.responsable.id)
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (3 de 10):

```jsx
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
                    <div className="exv-item-texto">
                        {r.texto}
                        {r.nota && <span className="exv-item-nota">↳ {r.nota}</span>}
                    </div>
                </div>
            </label>
```

por:

```jsx
                />
                <div className="exv-item-cuerpo">
                    <div className="exv-item-tags">
                        {contenido.codigo && <span className="exv-codigo">{contenido.codigo}</span>}
                        {contenido.fuente && <span className={`exv-fuente exv-fuente-${fila.requisito.fuente}`}>{contenido.fuente}</span>}
                        {contenido.propio && <span className="exv-estado exv-estado-propio">Propio</span>}
                        {contenido.propio && contenido.categoria && <span className="exv-estado">{contenido.categoria}</span>}
                        {fila.estado !== 'pendiente' && fila.estado !== 'cumplido' && (
                            <span className={`exv-estado exv-estado-${fila.estado}`}>{ETIQUETA_ESTADO_REQUISITO[fila.estado]}</span>
                        )}
                        {fila.caracter !== 'obligatorio' && <span className="exv-estado">{fila.caracter === 'opcional' ? 'Opcional' : 'Condicionado'}</span>}
                    </div>
                    <div className="exv-item-texto">
                        {contenido.texto}
                        {contenido.nota && (
                            <span className={contenido.propio ? 'exv-item-descripcion' : 'exv-item-nota'}>
                                {contenido.propio ? contenido.nota : `↳ ${contenido.nota}`}
                            </span>
                        )}
                        {contenido.descripcion && <span className="exv-item-descripcion">{contenido.descripcion}</span>}
                    </div>
                    {/* Por definir, la condición va en su recuadro; decidida, queda a la vista bajo el texto. */}
                    {contenido.aplicaSi && fila.estado !== 'por_definir' && (
                        <div className="exv-item-condicion"><b>Aplica si:</b> {contenido.aplicaSi}</div>
                    )}
                    {origenes && <div className="exv-item-origenes">{origenes}</div>}
                </div>
            </label>
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (4 de 10):

```jsx
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
```

por:

```jsx
                </div>
            )}

            {fila.estado === 'por_definir' && (
                <div className="exv-no-aplica exv-por-definir">
                    <b>{ETIQUETA_ESTADO_REQUISITO.por_definir}:</b> {textoPorDefinir(fila)}
                </div>
            )}

            {fila.no_aplica && fila.estado !== 'por_definir' && (
                <div className="exv-no-aplica">
                    <b>{ETIQUETA_ESTADO_REQUISITO[fila.estado]}:</b> {fila.no_aplica.justificacion}
                    <span className="exv-no-aplica-quien">
                        {fila.no_aplica.usuario && ` Marcado por ${fila.no_aplica.usuario}.`}
                        {fila.no_aplica.aprobado_por && ` Aprobado por ${fila.no_aplica.aprobado_por} el ${fechaCorta(fila.no_aplica.fecha_aprobacion)}.`}
                        {fila.estado === 'no_aplica_pendiente' && contenido.cargoRevision && ` Debería revisarlo: ${contenido.cargoRevision}.`}
                    </span>
                </div>
            )}
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (5 de 10):

```jsx
                {editable ? (
                    <select
                        value={fila.responsable?.id ?? ''}
                        aria-label={`Responsable del requisito ${r.codigo}`}
                        onChange={(e) => onResponsable(fila, e.target.value ? Number(e.target.value) : null)}
                    >
                        <option value="">Sin asignar</option>
```

por:

```jsx
                {editable ? (
                    <select
                        value={fila.responsable?.id ?? ''}
                        aria-label={`Responsable del requisito ${nombreFila(contenido)}`}
                        onChange={(e) => onResponsable(fila, e.target.value ? Number(e.target.value) : null)}
                    >
                        <option value="">Sin asignar</option>
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (6 de 10):

```jsx
                ) : (
                    <b>{fila.responsable ? fila.responsable.nombre : 'Sin asignar'}</b>
                )}
                {r.cargo_cumplimiento && <span className="exv-responsable-cargo">Cargo indicado: {r.cargo_cumplimiento}</span>}
            </div>

            <div className="exv-item-acciones">
```

por:

```jsx
                ) : (
                    <b>{fila.responsable ? fila.responsable.nombre : 'Sin asignar'}</b>
                )}
                {contenido.cargoCumplimiento && <span className="exv-responsable-cargo">Cargo indicado: {contenido.cargoCumplimiento}</span>}
                {contenido.soporteRequerido && <span className="exv-responsable-cargo">Soporte requerido: {contenido.soporteRequerido}</span>}
            </div>

            <div className="exv-item-acciones">
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (7 de 10):

```jsx
                        />
                    </label>
                )}
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
```

por:

```jsx
                        />
                    </label>
                )}
                {acciones.includes('aplica') && (
                    <button type="button" className="exv-mini exv-mini-exito" disabled={ocupado} onClick={() => onAplica(fila)}>
                        <FontAwesomeIcon icon={faCircleCheck} /> Aplica
                    </button>
                )}
                {acciones.includes('no_aplica') && (
                    <button type="button" className="exv-mini" onClick={() => onNoAplica(fila)}>
                        <FontAwesomeIcon icon={faBan} /> No aplica
                    </button>
                )}
                {acciones.includes('retirar_no_aplica') && (
                    <button type="button" className="exv-mini" onClick={() => onRetirarNoAplica(fila)}>
                        <FontAwesomeIcon icon={faRotateLeft} /> Retirar "no aplica"
                    </button>
                )}
                {acciones.includes('aprobar') && (
                    <>
                        <button type="button" className="exv-mini exv-mini-exito" onClick={() => onAprobarNoAplica(fila)}>
                            <FontAwesomeIcon icon={faCheck} /> Aprobar
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (8 de 10):

```jsx
                        </button>
                    </>
                )}
                <button type="button" className="exv-mini" aria-expanded={viendoHistorial} onClick={() => setViendoHistorial((v) => !v)}>
                    <FontAwesomeIcon icon={faClockRotateLeft} /> Historial
                </button>
```

por:

```jsx
                        </button>
                    </>
                )}
                {editable && contenido.propio && (
                    <>
                        <button type="button" className="exv-mini" disabled={ocupado} data-foco={`editar-propio-${fila.id}`} onClick={() => onEditarPropio(fila)}>
                            <FontAwesomeIcon icon={faPen} /> Editar
                        </button>
                        <button type="button" className="exv-mini exv-mini-peligro" disabled={ocupado} onClick={() => onEliminarPropio(fila)}>
                            <FontAwesomeIcon icon={faTrash} /> Eliminar
                        </button>
                    </>
                )}
                <button type="button" className="exv-mini" aria-expanded={viendoHistorial} onClick={() => setViendoHistorial((v) => !v)}>
                    <FontAwesomeIcon icon={faClockRotateLeft} /> Historial
                </button>
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (9 de 10):

```jsx
                        rows={2}
                        maxLength={2000}
                        placeholder="Observación sobre este requisito"
                        defaultValue={fila.observacion ?? ''}
                        onBlur={(e) => {
                            if (e.target.value.trim() !== (fila.observacion ?? '')) {
```

por:

```jsx
                        rows={2}
                        maxLength={2000}
                        placeholder="Observación sobre este requisito"
                        aria-label={`Observación del requisito ${nombreFila(contenido)}`}
                        defaultValue={fila.observacion ?? ''}
                        onBlur={(e) => {
                            if (e.target.value.trim() !== (fila.observacion ?? '')) {
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (10 de 10):

```jsx
    );
};

const ExpedienteChecklist = ({ expediente, editable, ...acciones }) => {
    const secciones = useMemo(() => agruparRequisitos(expediente.requisitos), [expediente.requisitos]);
    const sectores = expediente.configuracion.sectores;

    if (secciones.length === 0) {
        return (
            <div className="exv-vacio">
                <b>Sin requisitos que mostrar</b>
                <p>Ajusta la configuración del proyecto para generar el checklist.</p>
            </div>
        );
    }

    return secciones.map((seccion) => {
        const cita = seccion.clave === 'C'
            ? (sectores.length === 1 ? sectores[0].nombre : `${sectores.length} sectores`)
            : seccion.cita;

        return (
            <section key={seccion.clave} className="exv-seccion">
                <div className="exv-seccion-head">
                    <span className="exv-num">{seccion.num}</span>
                    <h3>{seccion.titulo}</h3>
                    <span className="exv-cita">{cita} · {seccion.cumplidos}/{seccion.total}</span>
                </div>
                {seccion.grupos.map((grupo) => (
                    <div key={grupo.sector?.id ?? 'general'}>
                        {seccion.clave === 'C' && sectores.length > 1 && (
                            <div className="exv-subhead exv-subhead-sector">{grupo.sector.nombre}</div>
                        )}
                        {grupo.subgrupos.map((sub, i) => (
                            <div key={`${sub.subtitulo ?? 'sin'}-${i}`}>
                                {sub.subtitulo && <div className="exv-subhead">{sub.subtitulo}</div>}
                                {sub.filas.map((fila) => (
                                    <ItemRequisito key={fila.id} fila={fila} editable={editable} {...acciones} />
                                ))}
                            </div>
                        ))}
                    </div>
                ))}
            </section>
        );
    });
};

export default ExpedienteChecklist;
```

por:

```jsx
    );
};

/** Cabecera de una plantilla dentro del checklist. */
const CabeceraPlantilla = ({ nombre, cumplidos, total }) => (
    <div className="exv-plantilla-titulo">
        <h3>{nombre}</h3>
        <span className="exv-cita">{cumplidos}/{total}</span>
    </div>
);

/** Plantilla con motor sgr: por sección, sector y subtítulo, como siempre. */
const BloqueSgr = ({ bloque, conCabecera, sectores, renderFila }) => {
    const Titulo = conCabecera ? 'h4' : 'h3';
    return (
        <div className="exv-bloque">
            {conCabecera && <CabeceraPlantilla nombre={bloque.plantilla.nombre} cumplidos={bloque.cumplidos} total={bloque.total} />}
            {bloque.secciones.map((seccion) => {
                const cita = seccion.clave === 'C'
                    ? (sectores.length === 1 ? sectores[0].nombre : `${sectores.length} sectores`)
                    : seccion.cita;

                return (
                    <section key={seccion.clave} className="exv-seccion">
                        <div className="exv-seccion-head">
                            <span className="exv-num">{seccion.num}</span>
                            <Titulo>{seccion.titulo}</Titulo>
                            <span className="exv-cita">{cita} · {seccion.cumplidos}/{seccion.total}</span>
                        </div>
                        {seccion.grupos.map((grupo) => (
                            <div key={grupo.sector?.id ?? 'general'}>
                                {seccion.clave === 'C' && sectores.length > 1 && (
                                    <div className="exv-subhead exv-subhead-sector">{grupo.sector.nombre}</div>
                                )}
                                {grupo.subgrupos.map((sub, i) => (
                                    <div key={`${sub.subtitulo ?? 'sin'}-${i}`}>
                                        {sub.subtitulo && <div className="exv-subhead">{sub.subtitulo}</div>}
                                        {sub.filas.map(renderFila)}
                                    </div>
                                ))}
                            </div>
                        ))}
                    </section>
                );
            })}
        </div>
    );
};

/** Otra plantilla: por categoría. Si todo es "Sin categoría", no hace falta el subtítulo. */
const BloqueSimple = ({ bloque, renderFila }) => {
    const soloSinCategoria = bloque.categorias.length === 1 && bloque.categorias[0].clave === 'sin';
    return (
        <section className="exv-seccion exv-bloque">
            <CabeceraPlantilla nombre={bloque.plantilla?.nombre ?? 'Sin plantilla'} cumplidos={bloque.cumplidos} total={bloque.total} />
            {bloque.categorias.map((categoria) => (
                <div key={categoria.clave}>
                    {!soloSinCategoria && (
                        <div className="exv-subhead">{categoria.titulo} · {categoria.cumplidos}/{categoria.total}</div>
                    )}
                    {categoria.filas.map(renderFila)}
                </div>
            ))}
        </section>
    );
};

const ExpedienteChecklist = ({ expediente, editable, ocupado, onGuardarPropio, onEliminarPropio, ...acciones }) => {
    const bloques = useMemo(() => agruparChecklist(expediente.requisitos), [expediente.requisitos]);
    const [formulario, setFormulario] = useState(null); // null, 'nuevo' o el id de la fila propia que se edita
    const contenedor = useRef(null);
    const retorno = useRef(null); // data-foco del botón que abrió el formulario
    const sectores = expediente.configuracion.sectores;
    const deCatalogo = bloques.filter((b) => b.tipo !== 'propios');
    const propios = bloques.find((b) => b.tipo === 'propios');
    // Con una sola plantilla el checklist se ve como siempre; con varias, cada una lleva su nombre.
    const conCabecera = expediente.plantillas.length > 1 || deCatalogo.length > 1;

    // Al cerrar el formulario, el foco vuelve al botón que lo abrió (si la fila sigue ahí).
    useEffect(() => {
        if (formulario !== null || !retorno.current) return;
        contenedor.current?.querySelector(`[data-foco="${retorno.current}"]`)?.focus();
        retorno.current = null;
    }, [formulario]);

    const abrir = (fila) => {
        retorno.current = fila ? `editar-propio-${fila.id}` : 'agregar-propio';
        setFormulario(fila ? fila.id : 'nuevo');
    };

    const guardar = async (fila, datos) => {
        if (await onGuardarPropio(fila, datos)) setFormulario(null);
    };

    const renderFila = (fila) => (
        formulario === fila.id ? (
            <FormularioPropio
                key={fila.id}
                fila={fila}
                ocupado={ocupado}
                onGuardar={(datos) => guardar(fila, datos)}
                onCancelar={() => setFormulario(null)}
            />
        ) : (
            <ItemRequisito
                key={fila.id}
                fila={fila}
                editable={editable}
                ocupado={ocupado || formulario !== null}
                onEditarPropio={abrir}
                onEliminarPropio={onEliminarPropio}
                {...acciones}
            />
        )
    );

    return (
        <div ref={contenedor}>
            <AvisosDuplicados avisos={expediente.avisos_duplicados} />

            {deCatalogo.length === 0 && !propios && (
                <div className="exv-vacio">
                    <b>Sin requisitos que mostrar</b>
                    <p>
                        {expediente.plantillas.length === 0
                            ? 'Elige al menos una plantilla en la configuración para armar el checklist.'
                            : 'Ajusta la configuración del proyecto para generar el checklist.'}
                    </p>
                </div>
            )}

            {deCatalogo.map((bloque) => (bloque.tipo === 'sgr' ? (
                <BloqueSgr key={bloque.clave} bloque={bloque} conCabecera={conCabecera} sectores={sectores} renderFila={renderFila} />
            ) : (
                <BloqueSimple key={bloque.clave} bloque={bloque} renderFila={renderFila} />
            )))}

            {(propios || editable) && (
                <section className="exv-seccion exv-bloque exv-propios" aria-labelledby="exv-propios-titulo">
                    <div className="exv-plantilla-titulo">
                        <h3 id="exv-propios-titulo">{TITULO_PROPIOS}</h3>
                        {propios && <span className="exv-cita">{propios.cumplidos}/{propios.total}</span>}
                    </div>
                    {!propios && (
                        <p className="exv-propios-vacio exv-no-imprimir">
                            Agrega aquí lo que el proyecto debe cumplir y no trae ninguna plantilla. No cambia ninguna plantilla.
                        </p>
                    )}
                    {propios?.filas.map(renderFila)}
                    {editable && (formulario === 'nuevo' ? (
                        <FormularioPropio
                            ocupado={ocupado}
                            onGuardar={(datos) => guardar(null, datos)}
                            onCancelar={() => setFormulario(null)}
                        />
                    ) : (
                        <button
                            type="button"
                            className="exv-btn exv-no-imprimir"
                            data-foco="agregar-propio"
                            disabled={ocupado || formulario !== null}
                            onClick={() => abrir(null)}
                        >
                            <FontAwesomeIcon icon={faPlus} /> Agregar requisito propio
                        </button>
                    ))}
                </section>
            )}
        </div>
    );
};

export default ExpedienteChecklist;
```

- [ ] **Paso 2: Comprobar que nada se rompió**

Run: `npm test`
Expected: pasan todas, 253.

- [ ] **Paso 3: Commit**

```bash
git add resources/css/ExpedienteViabilizacion.css resources/js/components/ExpedienteChecklist.jsx
git commit -m "feat: group the checklist by template and handle undefined and own requirements"
```

---

### Tarea 12: Pantalla del expediente con plantillas

Sin pruebas de componentes en el repositorio: se comprueba en el navegador en la tarea 15.

**Files:**
- Modify: `resources/css/ExpedienteViabilizacion.css`
- Modify: `resources/js/components/ExpedienteViabilizacion.jsx`

- [ ] **Paso 1: Implementar**

En `resources/css/ExpedienteViabilizacion.css`, reemplazar (1 de 1):

```css
}
.exv-titulo-texto { flex: 1; min-width: 240px; }
.exv-titulo-texto h2 { font-size: 1.5rem; line-height: 1.2; font-weight: 800; margin: .2rem 0 0; color: var(--exv-tinta); }
.exv-eyebrow { font-size: .72rem; letter-spacing: .12em; text-transform: uppercase; color: var(--exv-bronce-oscuro); }
.exv-titulo-acciones { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
.exv-titulo-acciones select {
```

por:

```css
}
.exv-titulo-texto { flex: 1; min-width: 240px; }
.exv-titulo-texto h2 { font-size: 1.5rem; line-height: 1.2; font-weight: 800; margin: .2rem 0 0; color: var(--exv-tinta); }
.exv-subtitulo { display: flex; flex-wrap: wrap; gap: .25rem .75rem; align-items: baseline; margin: .3rem 0 0; font-size: .9rem; color: var(--exv-tinta-suave); }
.exv-subtitulo b { font-weight: 700; color: var(--exv-tinta); }
.exv-vacio-plantillas { font-size: .85rem !important; color: var(--exv-tinta-suave); }
.exv-resumen-impresion h2 small { margin-left: .5rem; font-size: .8rem; font-weight: 400; }
.exv-eyebrow { font-size: .72rem; letter-spacing: .12em; text-transform: uppercase; color: var(--exv-bronce-oscuro); }
.exv-titulo-acciones { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
.exv-titulo-acciones select {
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (1 de 25):

```jsx
import { faArrowLeft, faCheck, faFileExcel, faPlus, faPrint, faSliders, faStamp, faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import ExpedienteChecklist from './ExpedienteChecklist';
import ExpedienteConfiguracion from './ExpedienteConfiguracion';
import {
    construirHojasExcel,
    faseLegible,
    mensajeError,
    nombreArchivoExcel,
    puedeRadicar,
    sectoresConFuenteA12,
    sectoresSinTipo,
    seleccionDesde,
} from '../utils/expedienteChecklist';
import '../../css/ExpedienteViabilizacion.css';

const ROLES_EDICION = ['Administrador', 'Gestor'];
const ESPERA_RECALCULO_MS = 350;

const avisarError = (titulo, error) => Swal.fire({ icon: 'error', title: titulo, text: mensajeError(error) });

const ExpedienteViabilizacion = ({ user }) => {
    const { proyectoId } = useParams();
```

por:

```jsx
import { faArrowLeft, faCheck, faFileExcel, faPlus, faPrint, faSliders, faStamp, faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import ExpedienteChecklist, { AvisosDuplicados } from './ExpedienteChecklist';
import ExpedienteConfiguracion from './ExpedienteConfiguracion';
import useOcupado from './useOcupado';
import {
    ANCHOS_HOJA_CHECKLIST,
    actualizarExpedienteEnLista,
    construirHojasExcel,
    datosPropio,
    faseLegible,
    mensajeError,
    motivoNoEliminarPropio,
    nombreArchivoExcel,
    puedeRadicar,
    requiereAprobacionNoAplica,
    sectoresConFuenteA12,
    sectoresSinTipo,
    seleccionDesde,
    textoPlantillas,
} from '../utils/expedienteChecklist';
import { AVISO_SIN_SECTOR, avisoIncompatibles, hayCompatibleSgr, seleccionParaNuevo } from '../utils/plantillasExpediente';
import { mensajeApi } from '../utils/plantillasChecklist';
import { avisarError } from '../utils/avisosPlantillas';
import '../../css/ExpedienteViabilizacion.css';

const ROLES_EDICION = ['Administrador', 'Gestor'];
const ESPERA_RECALCULO_MS = 350;

/** "SGR v1 · Obras de agua v2", para el resumen impreso. */
const plantillasConVersion = (plantillas) => plantillas.map((p) => `${p.nombre} v${p.version.numero}`).join(' · ');

const ExpedienteViabilizacion = ({ user }) => {
    const { proyectoId } = useParams();
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (2 de 25):

```jsx
    const [configAbierta, setConfigAbierta] = useState(false);
    const [errorCarga, setErrorCarga] = useState(null);
    const [responsables, setResponsables] = useState([]);

    const puedeEditar = ROLES_EDICION.includes(user?.rol);
    const editable = puedeEditar && expediente?.estado === 'borrador';
```

por:

```jsx
    const [configAbierta, setConfigAbierta] = useState(false);
    const [errorCarga, setErrorCarga] = useState(null);
    const [responsables, setResponsables] = useState([]);
    // Plantillas que se ofrecen al proyecto (y las que ya tiene el expediente abierto).
    const [disponibles, setDisponibles] = useState(null);
    const [errorPlantillas, setErrorPlantillas] = useState(null);
    const [ocupado, ejecutar] = useOcupado();

    const puedeEditar = ROLES_EDICION.includes(user?.rol);
    const editable = puedeEditar && expediente?.estado === 'borrador';
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (3 de 25):

```jsx
        return data;
    }, [proyectoId]);

    const cargarExpediente = useCallback(async (id) => {
        const { data } = await axios.get(`/expedientes/${id}`);
        setExpediente(data);
        setSeleccion(seleccionDesde(data));
    }, []);

    useEffect(() => {
        let vigente = true;
```

por:

```jsx
        return data;
    }, [proyectoId]);

    // El selector de expedientes sale de la lista del proyecto: se pone al día con cada respuesta del expediente.
    const actualizarEnLista = useCallback((data) => setDatosProyecto((prev) => (
        prev ? { ...prev, expedientes: actualizarExpedienteEnLista(prev.expedientes, data) } : prev
    )), []);

    const cargarExpediente = useCallback(async (id) => {
        const { data } = await axios.get(`/expedientes/${id}`);
        setExpediente(data);
        setSeleccion(seleccionDesde(data));
        actualizarEnLista(data);
    }, [actualizarEnLista]);

    // Solo vale la última petición: el expediente abierto puede cambiar mientras llega.
    const peticionPlantillas = useRef(0);
    const cargarPlantillas = useCallback(async (expedienteId) => {
        const numero = ++peticionPlantillas.current;
        setErrorPlantillas(null);
        try {
            const { data } = await axios.get(`/proyectos/${proyectoId}/plantillas-compatibles`, {
                params: expedienteId ? { expediente: expedienteId } : {},
            });
            if (numero === peticionPlantillas.current) setDisponibles(data);
        } catch (error) {
            if (numero === peticionPlantillas.current) {
                setDisponibles(null);
                setErrorPlantillas(mensajeApi(error));
            }
        }
    }, [proyectoId]);

    useEffect(() => {
        let vigente = true;
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (4 de 25):

```jsx
        return () => { vigente = false; };
    }, [idActual, cargarExpediente]);

    useEffect(() => () => clearTimeout(envio.current.temporizador), []);

    // Sin la lista, el checklist igual funciona: solo no se puede elegir responsable.
```

por:

```jsx
        return () => { vigente = false; };
    }, [idActual, cargarExpediente]);

    // También sin expediente: dice qué plantillas tendría uno nuevo. Espera al proyecto para
    // saber cuál es el expediente abierto y no pedir la lista dos veces.
    const proyectoCargado = Boolean(datosProyecto);
    useEffect(() => {
        if (!proyectoCargado) return;
        setDisponibles(null);
        cargarPlantillas(idActual);
    }, [proyectoCargado, idActual, cargarPlantillas]);

    useEffect(() => () => clearTimeout(envio.current.temporizador), []);

    // Sin la lista, el checklist igual funciona: solo no se puede elegir responsable.
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (5 de 25):

```jsx
        try {
            const { data } = await axios.put(`/expedientes/${id}/configuracion`, datos);
            if (!envio.current.pendiente) setExpediente(data);
        } catch (error) {
            envio.current.pendiente = null;
            avisarError('No se pudo actualizar la configuración', error);
```

por:

```jsx
        try {
            const { data } = await axios.put(`/expedientes/${id}/configuracion`, datos);
            if (!envio.current.pendiente) setExpediente(data);
            actualizarEnLista(data);
        } catch (error) {
            envio.current.pendiente = null;
            avisarError('No se pudo actualizar la configuración', error);
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (6 de 25):

```jsx
            if (siguiente) enviarConfiguracion(id, siguiente);
            else setRecalculando(false);
        }
    }, [cargarExpediente]);

    const cambiarSeleccion = (nueva) => {
        setSeleccion(nueva);
```

por:

```jsx
            if (siguiente) enviarConfiguracion(id, siguiente);
            else setRecalculando(false);
        }
    }, [cargarExpediente, actualizarEnLista]);

    const cambiarSeleccion = (nueva) => {
        setSeleccion(nueva);
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (7 de 25):

```jsx
            setExpediente(data);
            setSeleccion(seleccionDesde(data));
            setConfigAbierta(false);
            await cargarProyecto();
        } catch (error) {
            avisarError('No se pudo confirmar la configuración', error);
```

por:

```jsx
            setExpediente(data);
            setSeleccion(seleccionDesde(data));
            setConfigAbierta(false);
            // Las plantillas nuevas ya son del expediente: la lista lo refleja.
            cargarPlantillas(data.id);
            await cargarProyecto();
        } catch (error) {
            avisarError('No se pudo confirmar la configuración', error);
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (8 de 25):

```jsx
        requisitos: prev.requisitos.map((f) => (f.id === fila.id ? fila : f)),
    }));

    const urlFila = (fila) => `/expedientes/${expediente.id}/requisitos/${fila.id}`;

    const marcar = async (fila, cumplido) => {
```

por:

```jsx
        requisitos: prev.requisitos.map((f) => (f.id === fila.id ? fila : f)),
    }));

    // Un propio nuevo va al final: el backend ordena los propios después de toda plantilla.
    const agregarFila = (fila, progreso) => setExpediente((prev) => ({
        ...prev,
        progreso: progreso ?? prev.progreso,
        requisitos: [...prev.requisitos, fila],
    }));

    const quitarFila = (fila, progreso) => setExpediente((prev) => ({
        ...prev,
        progreso: progreso ?? prev.progreso,
        requisitos: prev.requisitos.filter((f) => f.id !== fila.id),
    }));

    const urlFila = (fila) => `/expedientes/${expediente.id}/requisitos/${fila.id}`;

    const marcar = async (fila, cumplido) => {
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (9 de 25):

```jsx
    const marcarNoAplica = async (fila) => {
        const { isConfirmed, value } = await Swal.fire({
            title: 'Este requisito no aplica',
            text: fila.requisito.requiere_aprobacion_no_aplica ? 'Otra persona deberá aprobarlo.' : undefined,
            input: 'textarea',
            inputLabel: 'Justificación',
            inputPlaceholder: 'Explica por qué no aplica a este proyecto',
```

por:

```jsx
    const marcarNoAplica = async (fila) => {
        const { isConfirmed, value } = await Swal.fire({
            title: 'Este requisito no aplica',
            text: requiereAprobacionNoAplica(fila) ? 'Otra persona deberá aprobarlo.' : undefined,
            input: 'textarea',
            inputLabel: 'Justificación',
            inputPlaceholder: 'Explica por qué no aplica a este proyecto',
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (10 de 25):

```jsx
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            title: '¿Retirar el "no aplica"?',
            text: 'El requisito volverá a estar pendiente. Si ya estaba aprobado, necesitará otra aprobación.',
            showCancelButton: true,
            confirmButtonText: 'Sí, retirar',
            cancelButtonText: 'Cancelar',
```

por:

```jsx
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            title: '¿Retirar el "no aplica"?',
            text: 'El requisito volverá al estado que tenía antes de marcarlo: pendiente o por definir. Si ya estaba aprobado, necesitará otra aprobación.',
            showCancelButton: true,
            confirmButtonText: 'Sí, retirar',
            cancelButtonText: 'Cancelar',
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (11 de 25):

```jsx
    const rechazarNoAplica = async (fila) => {
        const { isConfirmed, value } = await Swal.fire({
            title: '¿Rechazar el "no aplica"?',
            text: 'El requisito volverá a estar pendiente.',
            input: 'textarea',
            inputLabel: 'Motivo (opcional)',
            inputAttributes: { maxlength: '2000' },
```

por:

```jsx
    const rechazarNoAplica = async (fila) => {
        const { isConfirmed, value } = await Swal.fire({
            title: '¿Rechazar el "no aplica"?',
            text: 'El requisito volverá al estado que tenía antes de marcarlo: pendiente o por definir.',
            input: 'textarea',
            inputLabel: 'Motivo (opcional)',
            inputAttributes: { maxlength: '2000' },
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (12 de 25):

```jsx
        await accionFila(() => axios.post(`${urlFila(fila)}/no-aplica/rechazar`, { motivo: value || null }), 'No se pudo rechazar el "no aplica"');
    };

    const asignarResponsable = async (fila, responsableId) => {
        reemplazarFila({ ...fila, responsable: responsables.find((r) => r.id === responsableId) ?? null });
        try {
```

por:

```jsx
        await accionFila(() => axios.post(`${urlFila(fila)}/no-aplica/rechazar`, { motivo: value || null }), 'No se pudo rechazar el "no aplica"');
    };

    // Pasar de "por definir" a "aplica" no tiene vuelta atrás: después solo cabe un "no aplica" justificado.
    const decidirAplica = (fila) => ejecutar(async () => {
        const { isConfirmed } = await Swal.fire({
            icon: 'question',
            titleText: '¿Este requisito aplica al proyecto?',
            text: 'Quedará pendiente de cumplir. Si luego no aplica, tendrás que marcarlo como "no aplica" con una justificación.',
            showCancelButton: true,
            confirmButtonText: 'Sí, aplica',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        await accionFila(() => axios.post(`${urlFila(fila)}/aplica`), 'No se pudo indicar que el requisito aplica');
    });

    const urlPropios = () => `/expedientes/${expediente.id}/requisitos-propios`;

    /** Devuelve true si se guardó, para que el formulario se cierre. */
    const guardarPropio = async (fila, formulario) => Boolean(await ejecutar(async () => {
        const datos = datosPropio(formulario);
        try {
            if (fila) {
                const { data } = await axios.put(`${urlPropios()}/${fila.id}`, datos);
                reemplazarFila(data.requisito, data.progreso);
            } else {
                const { data } = await axios.post(urlPropios(), datos);
                agregarFila(data.requisito, data.progreso);
            }
            return true;
        } catch (error) {
            avisarError(fila ? 'No se pudo guardar el requisito propio' : 'No se pudo agregar el requisito propio', error);
            return false;
        }
    }));

    const eliminarPropio = (fila) => ejecutar(async () => {
        const motivo = motivoNoEliminarPropio(fila);
        if (motivo) {
            await Swal.fire({ icon: 'info', titleText: 'Este requisito no se puede eliminar', text: motivo });
            return;
        }
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            titleText: '¿Eliminar este requisito propio?',
            text: fila.propio.titulo,
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        try {
            const { data } = await axios.delete(`${urlPropios()}/${fila.id}`);
            quitarFila(fila, data.progreso);
        } catch (error) {
            avisarError('No se pudo eliminar el requisito propio', error);
            // Pudo cambiar en el servidor (otra persona lo marcó o le adjuntó un soporte).
            cargarExpediente(expediente.id).catch(() => {});
        }
    });

    const asignarResponsable = async (fila, responsableId) => {
        reemplazarFila({ ...fila, responsable: responsables.find((r) => r.id === responsableId) ?? null });
        try {
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (13 de 25):

```jsx

    const abrirExpediente = (id) => setSearchParams(id ? { expediente: String(id) } : {});

    const crearExpediente = async () => {
        // Uno nuevo parte de la configuración del abierto (caso re-radicación); sin
        // ninguno, de la fase III y el sector sugerido por el proyecto. El cumplimiento
        // no se copia: cada expediente se evalúa desde cero.
        const sugerido = datosProyecto.sector_sugerido_id;
        const datos = seleccion ?? { fase_sgr: 3, sector_ids: sugerido ? [sugerido] : [] };
        try {
            const { data } = await axios.post(`/proyectos/${proyectoId}/expedientes`, datos);
            await cargarProyecto();
            abrirExpediente(data.id);
        } catch (error) {
            avisarError('No se pudo crear el expediente', error);
        }
    };

    const radicar = async () => {
        const { isConfirmed } = await Swal.fire({
```

por:

```jsx

    const abrirExpediente = (id) => setSearchParams(id ? { expediente: String(id) } : {});

    const crearExpediente = () => ejecutar(async () => {
        // Uno nuevo parte de la configuración del abierto (caso re-radicación), solo con las
        // plantillas que hoy se ofrecen al proyecto. Sin ninguno, el servidor preselecciona la
        // plantilla SGR compatible, si la hay; con ella van la fase III y el sector sugerido.
        // El cumplimiento no se copia: cada expediente se evalúa desde cero.
        let datos;
        let quitadas = [];
        if (seleccion) {
            ({ datos, quitadas } = seleccionParaNuevo(seleccion, disponibles?.plantillas ?? null, expediente?.plantillas ?? []));
        } else {
            const sugerido = datosProyecto.sector_sugerido_id;
            // Sin la lista no se sabe si habrá SGR: se envía el sector, que sin ella no tiene efecto.
            const conSgr = disponibles ? hayCompatibleSgr(disponibles.plantillas) : true;
            datos = { fase_sgr: 3, sector_ids: sugerido && conSgr ? [sugerido] : [] };
        }
        try {
            const { data } = await axios.post(`/proyectos/${proyectoId}/expedientes`, datos);
            await cargarProyecto();
            abrirExpediente(data.id);
        } catch (error) {
            avisarError('No se pudo crear el expediente', error);
            return;
        }
        if (quitadas.length > 0) {
            Swal.fire({
                icon: 'warning',
                titleText: quitadas.length === 1 ? 'Una plantilla no pasó al expediente nuevo' : 'Algunas plantillas no pasaron al expediente nuevo',
                text: `${quitadas.length === 1 ? 'Ya no se ofrece' : 'Ya no se ofrecen'} a este proyecto: ${quitadas.join(', ')}. Revisa la configuración antes de confirmarla.`,
            });
        }
    });

    const radicar = async () => {
        const { isConfirmed } = await Swal.fire({
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (14 de 25):

```jsx
        hojaResumen['!cols'] = [{ wch: 24 }, { wch: 80 }];
        const hojaChecklist = XLSX.utils.json_to_sheet(checklist);
        // Un ancho por columna, en el orden de construirHojasExcel (17 columnas).
        hojaChecklist['!cols'] = [28, 26, 30, 30, 14, 8, 80, 40, 18, 10, 14, 22, 22, 40, 22, 40, 26].map((wch) => ({ wch }));
        XLSX.utils.book_append_sheet(libro, hojaResumen, 'Resumen');
        XLSX.utils.book_append_sheet(libro, hojaChecklist, 'Checklist');
        XLSX.writeFile(libro, nombreArchivoExcel(expediente));
```

por:

```jsx
        hojaResumen['!cols'] = [{ wch: 24 }, { wch: 80 }];
        const hojaChecklist = XLSX.utils.json_to_sheet(checklist);
        // Un ancho por columna, en el orden de construirHojasExcel (17 columnas).
        hojaChecklist['!cols'] = ANCHOS_HOJA_CHECKLIST.map((wch) => ({ wch }));
        XLSX.utils.book_append_sheet(libro, hojaResumen, 'Resumen');
        XLSX.utils.book_append_sheet(libro, hojaChecklist, 'Checklist');
        XLSX.writeFile(libro, nombreArchivoExcel(expediente));
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (15 de 25):

```jsx
    }

    const cargado = datosProyecto && catalogo && (!idActual || (expediente && seleccion));
    const a12 = expediente ? sectoresConFuenteA12(expediente.configuracion.sectores) : [];
    const esDesastre = expediente?.configuracion.tipologias.some((t) => t.clave === 'desastre');
    const { cumplidos = 0, total = 0, porcentaje = 0 } = expediente?.progreso ?? {};
    const sinConfigurar = Boolean(expediente) && !expediente.configurado;
    const panelVisible = sinConfigurar ? editable : configAbierta;
    const hayBorrador = datosProyecto?.expedientes.some((e) => e.estado === 'borrador');
    const sinTipo = sinConfigurar && seleccion ? sectoresSinTipo(seleccion, catalogo) : [];

    return (
        <div className="exv-pagina">
```

por:

```jsx
    }

    const cargado = datosProyecto && catalogo && (!idActual || (expediente && seleccion));
    // Lo propio de regalías solo se muestra si el expediente tiene una plantilla con motor sgr.
    const conSgr = Boolean(expediente?.tiene_sgr);
    const a12 = expediente && conSgr ? sectoresConFuenteA12(expediente.configuracion.sectores) : [];
    const esDesastre = conSgr && expediente.configuracion.tipologias.some((t) => t.clave === 'desastre');
    const { cumplidos = 0, total = 0, porcentaje = 0 } = expediente?.progreso ?? {};
    const sinConfigurar = Boolean(expediente) && !expediente.configurado;
    const panelVisible = sinConfigurar ? editable : configAbierta;
    const hayBorrador = datosProyecto?.expedientes.some((e) => e.estado === 'borrador');
    const sinTipo = sinConfigurar && conSgr && seleccion ? sectoresSinTipo(seleccion, catalogo) : [];
    const sinPlantillasElegidas = Boolean(seleccion) && seleccion.plantilla_ids.length === 0;
    const incompatibles = avisoIncompatibles(expediente?.plantillas);

    return (
        <div className="exv-pagina">
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (16 de 25):

```jsx
                                <FontAwesomeIcon icon={faArrowLeft} /> Volver
                            </button>
                            <div className="exv-titulo-texto">
                                <span className="exv-eyebrow">Viabilización de proyectos · Sistema General de Regalías</span>
                                <h2>{datosProyecto.proyecto.nombre}</h2>
                            </div>
                            <div className="exv-titulo-acciones exv-no-imprimir">
                                {datosProyecto.expedientes.length > 0 && (
```

por:

```jsx
                                <FontAwesomeIcon icon={faArrowLeft} /> Volver
                            </button>
                            <div className="exv-titulo-texto">
                                <span className="exv-eyebrow">Seguimiento de proyectos</span>
                                <h2>{datosProyecto.proyecto.nombre}</h2>
                                {expediente && (
                                    <p className="exv-subtitulo">
                                        <b>Expediente #{expediente.id}</b>
                                        <span>{textoPlantillas(expediente.plantillas)}</span>
                                    </p>
                                )}
                            </div>
                            <div className="exv-titulo-acciones exv-no-imprimir">
                                {datosProyecto.expedientes.length > 0 && (
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (17 de 25):

```jsx
                                    >
                                        {datosProyecto.expedientes.map((e) => (
                                            <option key={e.id} value={e.id}>
                                                #{e.id} · {e.estado === 'radicado' ? 'Radicado' : 'Borrador'} · catálogo v{e.catalogo_version.numero}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                {puedeEditar && !hayBorrador && (
                                    <button type="button" className="exv-btn" onClick={crearExpediente}>
                                        <FontAwesomeIcon icon={faPlus} /> Nuevo expediente
                                    </button>
                                )}
```

por:

```jsx
                                    >
                                        {datosProyecto.expedientes.map((e) => (
                                            <option key={e.id} value={e.id}>
                                                #{e.id} · {e.estado === 'radicado' ? 'Radicado' : 'Borrador'} · {e.plantillas.length ? e.plantillas.map((p) => p.nombre).join(', ') : 'Sin plantillas'}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                {puedeEditar && !hayBorrador && (
                                    <button type="button" className="exv-btn" onClick={crearExpediente} disabled={ocupado}>
                                        <FontAwesomeIcon icon={faPlus} /> Nuevo expediente
                                    </button>
                                )}
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (18 de 25):

```jsx

                        {!expediente ? (
                            <div className="exv-vacio">
                                <b>Este proyecto aún no tiene expediente de viabilización</b>
                                <p>
                                    {puedeEditar
                                        ? 'Crea uno para armar su checklist de requisitos del SGR.'
                                        : 'Un Administrador o Gestor debe crearlo.'}
                                </p>
                            </div>
                        ) : (
                            <div className={`exv-shell${panelVisible ? '' : ' plegada'}`}>
```

por:

```jsx

                        {!expediente ? (
                            <div className="exv-vacio">
                                <b>Este proyecto aún no tiene expediente</b>
                                <p>
                                    {puedeEditar
                                        ? 'Crea uno para armar su checklist de requisitos.'
                                        : 'Un Administrador o Gestor debe crearlo.'}
                                </p>
                                {puedeEditar && disponibles && (
                                    <p className="exv-vacio-plantillas">
                                        {disponibles.plantillas.length > 0
                                            ? `Plantillas que se ofrecen a este proyecto: ${disponibles.plantillas.map((p) => p.nombre).join(', ')}.`
                                            : 'Hoy no se le ofrece ninguna plantilla: el expediente nacerá sin plantillas.'}
                                        {disponibles.sin_sector && ` ${AVISO_SIN_SECTOR}.`}
                                    </p>
                                )}
                            </div>
                        ) : (
                            <div className={`exv-shell${panelVisible ? '' : ' plegada'}`}>
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (19 de 25):

```jsx
                                        deshabilitada={!editable}
                                        recalculando={recalculando}
                                        onCambiar={cambiarSeleccion}
                                    />
                                )}
```

por:

```jsx
                                        deshabilitada={!editable}
                                        recalculando={recalculando}
                                        onCambiar={cambiarSeleccion}
                                        disponibles={disponibles}
                                        errorPlantillas={errorPlantillas}
                                        onReintentarPlantillas={() => cargarPlantillas(expediente.id)}
                                    />
                                )}
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (20 de 25):

```jsx
                                                <>
                                                    <h3>Configura el expediente para empezar a chequear</h3>
                                                    <p>
                                                        Se hace una sola vez. Revisa en el panel la fase SGR, el sector, el tipo de proyecto y
                                                        los componentes: definen qué requisitos aplican y, con eso, el porcentaje de avance.
                                                    </p>
                                                    {seleccion.sector_ids.length === 0 && (
                                                        <div className="exv-aviso">
                                                            <b>Sin sector.</b> Solo se cargarán los requisitos generales.
                                                        </div>
                                                    )}
                                                    {sinTipo.length > 0 && (
```

por:

```jsx
                                                <>
                                                    <h3>Configura el expediente para empezar a chequear</h3>
                                                    <p>
                                                        {conSgr
                                                            ? 'Se hace una sola vez. Elige en el panel las plantillas y revisa la fase SGR, el sector, el tipo de proyecto y los componentes: definen qué requisitos aplican y, con eso, el porcentaje de avance.'
                                                            : 'Se hace una sola vez. Elige en el panel las plantillas del expediente: definen qué requisitos aplican y, con eso, el porcentaje de avance.'}
                                                    </p>
                                                    {sinPlantillasElegidas && (
                                                        <div className="exv-aviso">
                                                            <b>Sin plantillas.</b> Elige al menos una plantilla para armar el checklist.
                                                        </div>
                                                    )}
                                                    {conSgr && seleccion.sector_ids.length === 0 && (
                                                        <div className="exv-aviso">
                                                            <b>Sin sector.</b> Solo se cargarán los requisitos generales del SGR.
                                                        </div>
                                                    )}
                                                    {sinTipo.length > 0 && (
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (21 de 25):

```jsx
                                                            <b>Sin tipo de proyecto: {sinTipo.join(', ')}.</b> Se cargarán todos los requisitos de ese sector, incluidos los que no le apliquen al proyecto.
                                                        </div>
                                                    )}
                                                    <div className="exv-confirmar-pie">
                                                        <span>
                                                            {recalculando
```

por:

```jsx
                                                            <b>Sin tipo de proyecto: {sinTipo.join(', ')}.</b> Se cargarán todos los requisitos de ese sector, incluidos los que no le apliquen al proyecto.
                                                        </div>
                                                    )}
                                                    <AvisosDuplicados avisos={expediente.avisos_duplicados} />
                                                    <div className="exv-confirmar-pie">
                                                        <span>
                                                            {recalculando
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (22 de 25):

```jsx
                                                            type="button"
                                                            className="exv-btn exv-btn-primario"
                                                            onClick={confirmarConfiguracion}
                                                            disabled={recalculando || confirmando}
                                                        >
                                                            <FontAwesomeIcon icon={faCheck} /> Confirmar configuración
                                                        </button>
```

por:

```jsx
                                                            type="button"
                                                            className="exv-btn exv-btn-primario"
                                                            onClick={confirmarConfiguracion}
                                                            disabled={recalculando || confirmando || sinPlantillasElegidas}
                                                        >
                                                            <FontAwesomeIcon icon={faCheck} /> Confirmar configuración
                                                        </button>
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (23 de 25):

```jsx
                                    </div>

                                    <div className="exv-solo-impresion exv-resumen-impresion">
                                        <h2>Expediente de viabilización SGR #{expediente.id}</h2>
                                        <div>
                                            <span>Fase {faseLegible(expediente.fase_sgr)}</span>
                                            <span>{cumplidos} de {total} requisitos ({porcentaje}%)</span>
                                            <span>Catálogo v{expediente.catalogo_version.numero}</span>
                                            <span>{expediente.estado === 'radicado' ? `Radicado ${new Date(expediente.fecha_radicado).toLocaleDateString('es-CO')}` : 'Borrador'}</span>
                                        </div>
                                        <div>Sectores: {expediente.configuracion.sectores.map((s) => s.nombre).join('; ') || 'Sin sector (solo generales)'}</div>
                                        {expediente.configuracion.tipos.length > 0 && <div>Tipos de proyecto: {expediente.configuracion.tipos.map((t) => t.nombre).join('; ')}</div>}
                                        {expediente.configuracion.componentes.length > 0 && <div>Componentes: {expediente.configuracion.componentes.map((c) => c.nombre).join('; ')}</div>}
                                        {expediente.configuracion.tipologias.length > 0 && <div>Tipologías: {expediente.configuracion.tipologias.map((t) => t.nombre).join('; ')}</div>}
                                        <div className="exv-resumen-clave">A12 = Acuerdo 12/2024 · OT = Orientaciones Transitorias</div>
                                    </div>

                                    {expediente.estado === 'radicado' && (
```

por:

```jsx
                                    </div>

                                    <div className="exv-solo-impresion exv-resumen-impresion">
                                        <h2>Expediente #{expediente.id} <small>{textoPlantillas(expediente.plantillas)}</small></h2>
                                        <div>
                                            {conSgr && <span>Fase SGR {faseLegible(expediente.fase_sgr)}</span>}
                                            <span>{cumplidos} de {total} requisitos ({porcentaje}%)</span>
                                            {expediente.plantillas.length > 0 && <span>{plantillasConVersion(expediente.plantillas)}</span>}
                                            <span>{expediente.estado === 'radicado' ? `Radicado ${new Date(expediente.fecha_radicado).toLocaleDateString('es-CO')}` : 'Borrador'}</span>
                                        </div>
                                        {conSgr && (
                                            <>
                                                <div>Sectores: {expediente.configuracion.sectores.map((s) => s.nombre).join('; ') || 'Sin sector (solo generales)'}</div>
                                                {expediente.configuracion.tipos.length > 0 && <div>Tipos de proyecto: {expediente.configuracion.tipos.map((t) => t.nombre).join('; ')}</div>}
                                                {expediente.configuracion.componentes.length > 0 && <div>Componentes: {expediente.configuracion.componentes.map((c) => c.nombre).join('; ')}</div>}
                                                {expediente.configuracion.tipologias.length > 0 && <div>Tipologías: {expediente.configuracion.tipologias.map((t) => t.nombre).join('; ')}</div>}
                                                <div className="exv-resumen-clave">A12 = Acuerdo 12/2024 · OT = Orientaciones Transitorias</div>
                                            </>
                                        )}
                                    </div>

                                    {expediente.estado === 'radicado' && (
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (24 de 25):

```jsx
                                            Su checklist está congelado. Para corregirlo, crea un expediente nuevo.
                                        </div>
                                    )}
                                    {!configAbierta && (
                                        <div className="exv-config-resumen exv-no-imprimir">
                                            <span><b>Fase</b> {faseLegible(expediente.fase_sgr)}</span>
                                            <span><b>Sectores</b> {expediente.configuracion.sectores.map((s) => s.nombre).join('; ') || 'Solo generales'}</span>
                                            {expediente.configuracion.tipos.length > 0 && <span><b>Tipos</b> {expediente.configuracion.tipos.map((t) => t.nombre).join('; ')}</span>}
                                            {expediente.configuracion.componentes.length > 0 && <span><b>Componentes</b> {expediente.configuracion.componentes.map((c) => c.nombre).join('; ')}</span>}
                                        </div>
                                    )}
                                    {configAbierta && expediente.configuracion.sectores.length > 0 && (
                                        <div className="exv-nota exv-no-imprimir">
                                            <b>Sectores seleccionados:</b> se suman los requisitos generales más los de cada sector marcado. Marca los <b>tipos de proyecto</b> para acotar cada sector (si no marcas ninguno de un sector, se muestra completo).
                                            {a12.length > 0 && <small>{a12.join(', ')}: se rige(n) por el Acuerdo 12/2024 (vigente), que reemplazó las Orientaciones Transitorias.</small>}
```

por:

```jsx
                                            Su checklist está congelado. Para corregirlo, crea un expediente nuevo.
                                        </div>
                                    )}
                                    {incompatibles && (
                                        <div className="exv-aviso exv-no-imprimir">
                                            <b>{incompatibles.titulo}:</b> {incompatibles.detalle}
                                        </div>
                                    )}
                                    {!configAbierta && (
                                        <div className="exv-config-resumen exv-no-imprimir">
                                            <span><b>Plantillas</b> {expediente.plantillas.map((p) => p.nombre).join(', ') || 'Ninguna'}</span>
                                            {conSgr && (
                                                <>
                                                    <span><b>Fase SGR</b> {faseLegible(expediente.fase_sgr)}</span>
                                                    <span><b>Sectores</b> {expediente.configuracion.sectores.map((s) => s.nombre).join('; ') || 'Solo generales'}</span>
                                                    {expediente.configuracion.tipos.length > 0 && <span><b>Tipos</b> {expediente.configuracion.tipos.map((t) => t.nombre).join('; ')}</span>}
                                                    {expediente.configuracion.componentes.length > 0 && <span><b>Componentes</b> {expediente.configuracion.componentes.map((c) => c.nombre).join('; ')}</span>}
                                                </>
                                            )}
                                        </div>
                                    )}
                                    {configAbierta && conSgr && expediente.configuracion.sectores.length > 0 && (
                                        <div className="exv-nota exv-no-imprimir">
                                            <b>Sectores seleccionados:</b> se suman los requisitos generales más los de cada sector marcado. Marca los <b>tipos de proyecto</b> para acotar cada sector (si no marcas ninguno de un sector, se muestra completo).
                                            {a12.length > 0 && <small>{a12.join(', ')}: se rige(n) por el Acuerdo 12/2024 (vigente), que reemplazó las Orientaciones Transitorias.</small>}
```

En `resources/js/components/ExpedienteViabilizacion.jsx`, reemplazar (25 de 25):

```jsx
                                    <ExpedienteChecklist
                                        expediente={expediente}
                                        editable={editable}
                                        onMarcar={marcar}
                                        onObservacion={guardarObservacion}
                                        onSubirSoporte={subirSoporte}
```

por:

```jsx
                                    <ExpedienteChecklist
                                        expediente={expediente}
                                        editable={editable}
                                        ocupado={ocupado}
                                        onAplica={decidirAplica}
                                        onGuardarPropio={guardarPropio}
                                        onEliminarPropio={eliminarPropio}
                                        onMarcar={marcar}
                                        onObservacion={guardarObservacion}
                                        onSubirSoporte={subirSoporte}
```

- [ ] **Paso 2: Comprobar que nada se rompió**

Run: `npm test`
Expected: pasan todas, 253.

- [ ] **Paso 3: Commit**

```bash
git add resources/css/ExpedienteViabilizacion.css resources/js/components/ExpedienteViabilizacion.jsx
git commit -m "feat: expediente screen with templates, aplica and own requirements"
```

---

### Tarea 13: Textos "Expediente" en el resto de pantallas

Cambia a propósito pruebas de `navegacion.test.js`, `tablero.test.js` y `expedientesResumen.test.js`, que comprueban los textos de la tabla del diseño.

**Files:**
- Modify: `resources/css/ExpedientesSgrPanel.css`
- Modify: `resources/js/components/Dashboard.jsx`
- Modify: `resources/js/components/ExpedientesSgrPanel.jsx`
- Modify: `resources/js/components/ModalProyecto.jsx`
- Modify: `resources/js/components/ModoSala.jsx`
- Modify: `resources/js/components/Parametros.jsx`
- Modify: `resources/js/utils/expedientesResumen.js`
- Test: `resources/js/utils/expedientesResumen.test.js`
- Modify: `resources/js/utils/navegacion.js`
- Test: `resources/js/utils/navegacion.test.js`
- Modify: `resources/js/utils/tablero.js`
- Test: `resources/js/utils/tablero.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/expedientesResumen.test.js`, reemplazar (1 de 3):

```js
    esFormulacion,
    filtrarFilas,
    rutaExpediente,
    textoAvanceProyecto,
} from './expedientesResumen';
```

por:

```js
    esFormulacion,
    filtrarFilas,
    rutaExpediente,
    textoExpediente,
    textoAvanceProyecto,
} from './expedientesResumen';
```

En `resources/js/utils/expedientesResumen.test.js`, reemplazar (2 de 3):

```js
    expediente_id: proyectoId * 10,
    estado,
    fase_sgr: 3,
    configurado: true,
    fecha_radicado: null,
    actualizado: '2026-11-04T10:00:00-05:00',
```

por:

```js
    expediente_id: proyectoId * 10,
    estado,
    fase_sgr: 3,
    plantillas: ['SGR'],
    tiene_sgr: true,
    configurado: true,
    fecha_radicado: null,
    actualizado: '2026-11-04T10:00:00-05:00',
```

En `resources/js/utils/expedientesResumen.test.js`, reemplazar (3 de 3):

```js
        expect(textoAvanceProyecto(1, null)).toBe('—');
    });
});
```

por:

```js
        expect(textoAvanceProyecto(1, null)).toBe('—');
    });
});

describe('plantillas y fase SGR en el seguimiento', () => {
    it('la fila lleva las plantillas del expediente y si tiene SGR', () => {
        const [fila] = construirFilas([proyecto(1, 'Acueducto')], [resumen(1, 'borrador', 8, 3, { plantillas: ['SGR', 'Obras de agua'] })]);
        expect(fila).toMatchObject({ plantillas: ['SGR', 'Obras de agua'], tieneSgr: true, faseSgr: 3 });
    });

    it('sin expediente no hay plantillas ni SGR', () => {
        const [fila] = construirFilas([proyecto(1, 'Acueducto')], []);
        expect(fila).toMatchObject({ plantillas: [], tieneSgr: false });
    });

    it('un expediente con plantilla sgr muestra su fase SGR', () => {
        const [fila] = construirFilas([proyecto(1, 'Acueducto')], [resumen(1, 'borrador', 8, 3)]);
        expect(textoExpediente(fila)).toBe('#10 · Fase SGR III — Factibilidad');
    });

    it('un expediente sin plantilla sgr no muestra "Fase SGR"', () => {
        const [fila] = construirFilas([proyecto(1, 'Acueducto')], [resumen(1, 'borrador', 8, 3, { plantillas: ['Obras de agua'], tiene_sgr: false })]);
        expect(textoExpediente(fila)).toBe('#10');
        expect(textoExpediente(fila)).not.toContain('Fase SGR');
    });

    it('sin configurar o sin expediente, tampoco', () => {
        const [sinConfigurar] = construirFilas([proyecto(1, 'Acueducto')], [resumen(1, 'borrador', 0, 0, { configurado: false })]);
        expect(textoExpediente(sinConfigurar)).toBe('#10');
        const [sinExpediente] = construirFilas([proyecto(2, 'Vía')], []);
        expect(textoExpediente(sinExpediente)).toBe('—');
    });

    it('un resumen anterior a las plantillas no rompe la fila', () => {
        const viejo = resumen(1, 'borrador', 8, 3);
        delete viejo.plantillas;
        delete viejo.tiene_sgr;
        const [fila] = construirFilas([proyecto(1, 'Acueducto')], [viejo]);
        expect(fila).toMatchObject({ plantillas: [], tieneSgr: false });
    });
});
```

En `resources/js/utils/navegacion.test.js`, reemplazar (1 de 3):

```js
});

describe('buscarEnPaleta', () => {
    it('sin texto muestra solo los destinos', () => {
        const resultados = buscarEnPaleta('', DESTINOS, proyectos);
```

por:

```js
});

describe('buscarEnPaleta', () => {
    it('"SGR" sigue llevando a Expedientes aunque el título ya no lo diga', () => {
        const expedientes = DESTINOS.find((d) => d.id === 'expedientes');
        expect(expedientes.titulo).toBe('Expedientes');
        expect(buscarEnPaleta('SGR', DESTINOS, []).map((r) => r.clave)).toContain('expedientes');
        expect(buscarEnPaleta('viabilización', DESTINOS, []).map((r) => r.clave)).toContain('expedientes');
    });

    it('sin texto muestra solo los destinos', () => {
        const resultados = buscarEnPaleta('', DESTINOS, proyectos);
```

En `resources/js/utils/navegacion.test.js`, reemplazar (2 de 3):

```js
        expect(destinoActivo('/dashboard', '?vista=expedientes')).toBe('expedientes');
    });

    it('un expediente pertenece al seguimiento SGR', () => {
        expect(destinoActivo('/viabilizacion/4', '')).toBe('expedientes');
    });
});
```

por:

```js
        expect(destinoActivo('/dashboard', '?vista=expedientes')).toBe('expedientes');
    });

    it('un expediente pertenece a Expedientes', () => {
        expect(destinoActivo('/viabilizacion/4', '')).toBe('expedientes');
    });
});
```

En `resources/js/utils/navegacion.test.js`, reemplazar (3 de 3):

```js
    it('da el título de la barra móvil', () => {
        expect(tituloDeRuta('/parametros/tipos-evento', '')).toBe('Tipos de evento');
        expect(tituloDeRuta('/dashboard', '')).toBe('Tablero');
        expect(tituloDeRuta('/viabilizacion/4', '')).toBe('Expedientes SGR');
        expect(tituloDeRuta('/otra', '')).toBe('GestPro');
    });
});
```

por:

```js
    it('da el título de la barra móvil', () => {
        expect(tituloDeRuta('/parametros/tipos-evento', '')).toBe('Tipos de evento');
        expect(tituloDeRuta('/dashboard', '')).toBe('Tablero');
        expect(tituloDeRuta('/viabilizacion/4', '')).toBe('Expedientes');
        expect(tituloDeRuta('/otra', '')).toBe('GestPro');
    });
});
```

En `resources/js/utils/tablero.test.js`, reemplazar (1 de 2):

```js
        expect(licitacion).toMatchObject({ cantidad: 0, presupuesto: 0, proporcion: 0 });
    });

    it('en Formulación mide requisitos SGR, no contratos', () => {
        const resumenes = [{
            proyecto_id: 1,
            estado: 'borrador',
```

por:

```js
        expect(licitacion).toMatchObject({ cantidad: 0, presupuesto: 0, proporcion: 0 });
    });

    it('en Formulación mide requisitos, no contratos', () => {
        const resumenes = [{
            proyecto_id: 1,
            estado: 'borrador',
```

En `resources/js/utils/tablero.test.js`, reemplazar (2 de 2):

```js
            progreso: { total: 10, cumplidos: 5, porcentaje: 50 },
        }];

        expect(resumirFases(fases, proyectos, resumenes)[0].metrica).toEqual({ pct: 50, etiqueta: 'Requisitos SGR', detalle: '1 de 2 configurados' });
    });

    it('sin datos no inventa un 0 %', () => {
```

por:

```js
            progreso: { total: 10, cumplidos: 5, porcentaje: 50 },
        }];

        expect(resumirFases(fases, proyectos, resumenes)[0].metrica).toEqual({ pct: 50, etiqueta: 'Requisitos', detalle: '1 de 2 configurados' });
    });

    it('sin datos no inventa un 0 %', () => {
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/expedientesResumen.test.js resources/js/utils/navegacion.test.js resources/js/utils/tablero.test.js`
Expected: FALLAN 9 y pasan 47. Los títulos todavía dicen "Expedientes SGR", `textoExpediente` no existe y el resumen no trae `plantillas` ni `tieneSgr`.

- [ ] **Paso 3: Implementar**

En `resources/css/ExpedientesSgrPanel.css`, reemplazar (1 de 1):

```css
/* Seguimiento de expedientes SGR por proyecto. */
.exs-panel { display: grid; gap: 1rem; align-content: start; }
.exs-secundario { font-size: .72rem; font-weight: 400; color: var(--gp-texto-3); }

.exs-mensaje { padding: 1.5rem; font-size: .9rem; text-align: center; color: var(--gp-texto-2); background: var(--gp-superficie); border: 1px solid var(--gp-linea); border-radius: var(--gp-radio); }
.exs-reintentar { margin-top: .75rem; }
```

por:

```css
/* Seguimiento de expedientes por proyecto. */
.exs-panel { display: grid; gap: 1rem; align-content: start; }
.exs-secundario { font-size: .72rem; font-weight: 400; color: var(--gp-texto-3); }
.exs-plantillas { max-width: 16rem; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }

.exs-mensaje { padding: 1.5rem; font-size: .9rem; text-align: center; color: var(--gp-texto-2); background: var(--gp-superficie); border: 1px solid var(--gp-linea); border-radius: var(--gp-radio); }
.exs-reintentar { margin-top: .75rem; }
```

En `resources/js/components/Dashboard.jsx`, reemplazar (1 de 1):

```jsx
const VISTAS_ANALISIS = [
    { id: VISTA.ESTADISTICAS, titulo: 'Estadísticas', icono: faChartColumn },
    { id: VISTA.CONTRATOS, titulo: 'Contratos', icono: faFileSignature },
    { id: VISTA.EXPEDIENTES, titulo: 'Expedientes SGR', icono: faClipboardCheck },
];

const hoyEnTexto = () => new Date().toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
```

por:

```jsx
const VISTAS_ANALISIS = [
    { id: VISTA.ESTADISTICAS, titulo: 'Estadísticas', icono: faChartColumn },
    { id: VISTA.CONTRATOS, titulo: 'Contratos', icono: faFileSignature },
    { id: VISTA.EXPEDIENTES, titulo: 'Expedientes', icono: faClipboardCheck },
];

const hoyEnTexto = () => new Date().toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
```

En `resources/js/components/ExpedientesSgrPanel.jsx`, reemplazar (1 de 2):

```jsx
import React, { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import '../../css/ExpedientesSgrPanel.css';
import { faseLegible } from '../utils/expedienteChecklist';
import { ESTADO, construirFilas, contarPorEstado, filtrarFilas, rutaExpediente } from '../utils/expedientesResumen';
import { filasEnSala } from '../utils/tablero';

const ETIQUETA_ESTADO = {
```

por:

```jsx
import React, { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import '../../css/ExpedientesSgrPanel.css';
import { textoPlantillas } from '../utils/expedienteChecklist';
import { ESTADO, construirFilas, contarPorEstado, filtrarFilas, rutaExpediente, textoExpediente } from '../utils/expedientesResumen';
import { filasEnSala } from '../utils/tablero';

const ETIQUETA_ESTADO = {
```

En `resources/js/components/ExpedientesSgrPanel.jsx`, reemplazar (2 de 2):

```jsx
            <td data-etiqueta="Expediente">
                {tieneExpediente ? (
                    <>
                        <div>#{fila.expedienteId}{medible && ` · Fase ${faseLegible(fila.faseSgr)}`}</div>
                        {fila.cantidad > 1 && <div className="exs-secundario">{fila.cantidad} expedientes en total</div>}
                    </>
                ) : '—'}
```

por:

```jsx
            <td data-etiqueta="Expediente">
                {tieneExpediente ? (
                    <>
                        <div>{textoExpediente(fila)}</div>
                        {/* En una línea: si no caben todas, se recortan y el título las muestra completas. */}
                        <div className="exs-secundario exs-plantillas" title={textoPlantillas(fila.plantillas)}>
                            {textoPlantillas(fila.plantillas)}
                        </div>
                        {fila.cantidad > 1 && <div className="exs-secundario">{fila.cantidad} expedientes en total</div>}
                    </>
                ) : '—'}
```

En `resources/js/components/ModalProyecto.jsx`, reemplazar (1 de 1):

```jsx
                            <Dato etiqueta="Entidad que presenta">{proyecto.descripcion_entidad_presenta}</Dato>
                            <Dato etiqueta="Entidad que financia">{proyecto.descripcion_entidad_financia}</Dato>
                            <Dato etiqueta="Fuente de financiación">{proyecto.fuente_financiacion}</Dato>
                            <Dato etiqueta="Requisitos SGR cumplidos">{textoAvanceProyecto(proyecto.id, resumenExpedientes)}</Dato>
                        </dl>
                        <button type="button" className="gp-boton gp-boton-primario tb-modal-accion" onClick={() => navigate(`/viabilizacion/${proyecto.id}`)}>
                            <FontAwesomeIcon icon={faClipboardCheck} /> Abrir expediente de viabilización SGR
                        </button>
                    </>
                )}
```

por:

```jsx
                            <Dato etiqueta="Entidad que presenta">{proyecto.descripcion_entidad_presenta}</Dato>
                            <Dato etiqueta="Entidad que financia">{proyecto.descripcion_entidad_financia}</Dato>
                            <Dato etiqueta="Fuente de financiación">{proyecto.fuente_financiacion}</Dato>
                            <Dato etiqueta="Requisitos cumplidos">{textoAvanceProyecto(proyecto.id, resumenExpedientes)}</Dato>
                        </dl>
                        <button type="button" className="gp-boton gp-boton-primario tb-modal-accion" onClick={() => navigate(`/viabilizacion/${proyecto.id}`)}>
                            <FontAwesomeIcon icon={faClipboardCheck} /> Abrir expediente
                        </button>
                    </>
                )}
```

En `resources/js/components/ModoSala.jsx`, reemplazar (1 de 1):

```jsx
const MINUTOS_ENTRE_RENOVACIONES = 30;
const SEGUNDOS_CONTROLES_VISIBLES = 4;

const TITULOS_ANALISIS = { [VISTA.CONTRATOS]: 'Contratos', [VISTA.EXPEDIENTES]: 'Expedientes SGR' };

const horaCorta = (fecha) => fecha.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
```

por:

```jsx
const MINUTOS_ENTRE_RENOVACIONES = 30;
const SEGUNDOS_CONTROLES_VISIBLES = 4;

const TITULOS_ANALISIS = { [VISTA.CONTRATOS]: 'Contratos', [VISTA.EXPEDIENTES]: 'Expedientes' };

const horaCorta = (fecha) => fecha.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
```

En `resources/js/components/Parametros.jsx`, reemplazar (1 de 1):

```jsx
                                                <button
                                                    onClick={() => navigate(`/viabilizacion/${item.id}`)}
                                                    className="btn-add-contrato-list btn-expediente"
                                                    title="Expediente de viabilización SGR"
                                                >
                                                    <FontAwesomeIcon icon={faClipboardCheck} /> Expediente SGR
                                                </button>
                                            )}
```

por:

```jsx
                                                <button
                                                    onClick={() => navigate(`/viabilizacion/${item.id}`)}
                                                    className="btn-add-contrato-list btn-expediente"
                                                    title="Expediente"
                                                >
                                                    <FontAwesomeIcon icon={faClipboardCheck} /> Expediente
                                                </button>
                                            )}
```

En `resources/js/utils/expedientesResumen.js`, reemplazar (1 de 3):

```js
// Lógica del seguimiento de expedientes en el Dashboard: une los proyectos con el
// resumen de GET /expedientes, que solo trae los proyectos que tienen expediente.

export const ESTADO = {
    SIN_CONFIGURAR: 'sin_configurar',
    SIN_EXPEDIENTE: 'sin_expediente',
```

por:

```js
// Lógica del seguimiento de expedientes en el Dashboard: une los proyectos con el
// resumen de GET /expedientes, que solo trae los proyectos que tienen expediente.

import { faseLegible } from './expedienteChecklist';

export const ESTADO = {
    SIN_CONFIGURAR: 'sin_configurar',
    SIN_EXPEDIENTE: 'sin_expediente',
```

En `resources/js/utils/expedientesResumen.js`, reemplazar (2 de 3):

```js
        expedienteId: resumen?.expediente_id ?? null,
        cantidad: resumen?.cantidad_expedientes ?? 0,
        faseSgr: resumen?.fase_sgr ?? null,
        fechaRadicado: resumen?.fecha_radicado ?? null,
        actualizado: resumen?.actualizado ?? null,
        total,
```

por:

```js
        expedienteId: resumen?.expediente_id ?? null,
        cantidad: resumen?.cantidad_expedientes ?? 0,
        faseSgr: resumen?.fase_sgr ?? null,
        // La fase solo es de regalías: se muestra si el expediente tiene una plantilla con motor sgr.
        tieneSgr: Boolean(resumen?.tiene_sgr),
        plantillas: resumen?.plantillas ?? [],
        fechaRadicado: resumen?.fecha_radicado ?? null,
        actualizado: resumen?.actualizado ?? null,
        total,
```

En `resources/js/utils/expedientesResumen.js`, reemplazar (3 de 3):

```js
    );
};

export const rutaExpediente = (fila) =>
    fila.expedienteId
        ? `/viabilizacion/${fila.proyectoId}?expediente=${fila.expedienteId}`
```

por:

```js
    );
};

/**
 * Columna "Expediente" del seguimiento: "#12 · Fase SGR III — Factibilidad" o "#12".
 * La fase SGR solo se muestra en un expediente medible con plantilla de motor sgr.
 */
export const textoExpediente = (fila) => {
    if (fila.expedienteId === null) return '—';
    const medible = fila.estado === ESTADO.BORRADOR || fila.estado === ESTADO.RADICADO;
    return medible && fila.tieneSgr ? `#${fila.expedienteId} · Fase SGR ${faseLegible(fila.faseSgr)}` : `#${fila.expedienteId}`;
};

export const rutaExpediente = (fila) =>
    fila.expedienteId
        ? `/viabilizacion/${fila.proyectoId}?expediente=${fila.expedienteId}`
```

En `resources/js/utils/navegacion.js`, reemplazar (1 de 1):

```js
export const DESTINOS = [
    { id: 'tablero', titulo: 'Tablero', ruta: '/dashboard', grupo: GRUPO.TRABAJO, icono: 'faGaugeHigh', palabras: 'inicio dashboard panel mapa' },
    ...SECCIONES_PARAMETROS.filter((s) => s.grupo === GRUPO.TRABAJO).map((s) => ({ ...s, id: s.slug, ruta: `/parametros/${s.slug}` })),
    { id: 'expedientes', titulo: 'Expedientes SGR', ruta: '/dashboard?vista=expedientes', grupo: GRUPO.TRABAJO, icono: 'faClipboardCheck', palabras: 'viabilizacion regalias requisitos checklist seguimiento' },
    { id: 'contratos', titulo: 'Contratos', ruta: '/dashboard?vista=contratos', grupo: GRUPO.ANALISIS, icono: 'faFileSignature', palabras: 'avance fisico financiero desfase' },
    { id: 'estadisticas', titulo: 'Estadísticas', ruta: '/dashboard?vista=estadisticas', grupo: GRUPO.ANALISIS, icono: 'faChartColumn', palabras: 'graficas filtros cadena presupuestal' },
    ...SECCIONES_PARAMETROS.filter((s) => s.grupo === GRUPO.CATALOGOS).map((s) => ({ ...s, id: s.slug, ruta: `/parametros/${s.slug}` })),
```

por:

```js
export const DESTINOS = [
    { id: 'tablero', titulo: 'Tablero', ruta: '/dashboard', grupo: GRUPO.TRABAJO, icono: 'faGaugeHigh', palabras: 'inicio dashboard panel mapa' },
    ...SECCIONES_PARAMETROS.filter((s) => s.grupo === GRUPO.TRABAJO).map((s) => ({ ...s, id: s.slug, ruta: `/parametros/${s.slug}` })),
    { id: 'expedientes', titulo: 'Expedientes', ruta: '/dashboard?vista=expedientes', grupo: GRUPO.TRABAJO, icono: 'faClipboardCheck', palabras: 'sgr viabilizacion regalias requisitos checklist plantillas seguimiento' },
    { id: 'contratos', titulo: 'Contratos', ruta: '/dashboard?vista=contratos', grupo: GRUPO.ANALISIS, icono: 'faFileSignature', palabras: 'avance fisico financiero desfase' },
    { id: 'estadisticas', titulo: 'Estadísticas', ruta: '/dashboard?vista=estadisticas', grupo: GRUPO.ANALISIS, icono: 'faChartColumn', palabras: 'graficas filtros cadena presupuestal' },
    ...SECCIONES_PARAMETROS.filter((s) => s.grupo === GRUPO.CATALOGOS).map((s) => ({ ...s, id: s.slug, ruta: `/parametros/${s.slug}` })),
```

En `resources/js/utils/tablero.js`, reemplazar (1 de 1):

```js
const metricaDeFase = (fase, delGrupo, resumenes) => {
    if (esFormulacion(fase.nombre)) {
        const { pct, medidos, total } = avanceRequisitos(delGrupo, resumenes);
        return { pct, etiqueta: 'Requisitos SGR', detalle: `${medidos} de ${total} configurados` };
    }

    const avance = calcularAvanceGrupo(delGrupo);
```

por:

```js
const metricaDeFase = (fase, delGrupo, resumenes) => {
    if (esFormulacion(fase.nombre)) {
        const { pct, medidos, total } = avanceRequisitos(delGrupo, resumenes);
        return { pct, etiqueta: 'Requisitos', detalle: `${medidos} de ${total} configurados` };
    }

    const avance = calcularAvanceGrupo(delGrupo);
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npm test`
Expected: pasan todas, 260.

- [ ] **Paso 5: Commit**

```bash
git add resources/css/ExpedientesSgrPanel.css resources/js/components/Dashboard.jsx resources/js/components/ExpedientesSgrPanel.jsx resources/js/components/ModalProyecto.jsx resources/js/components/ModoSala.jsx resources/js/components/Parametros.jsx resources/js/utils/expedientesResumen.js resources/js/utils/expedientesResumen.test.js resources/js/utils/navegacion.js resources/js/utils/navegacion.test.js resources/js/utils/tablero.js resources/js/utils/tablero.test.js
git commit -m "feat: screens say Expediente and keep SGR only where it belongs"
```

---

### Tarea 14: Compilar para producción

- [ ] **Paso 1: Compilar**

Run: `npm run production`
Expected: `Compiled Successfully`, sin errores ni avisos.

Justo antes de comitear, comprobar que `public/js/app.js` es el de producción: minificado y sin comentarios `/***/`. Si `npm run watch` lo reescribió, compilar otra vez.

- [ ] **Paso 2: Commit**

```bash
git add public/js/app.js public/js/app.js.map public/mix-manifest.json
git commit -m "build: production bundle for stage 5"
```

---

### Tarea 15: Verificación en la copia descartable

Usa la copia y las fotos de la tarea 1. Nunca `gest_pro`.

- [ ] **Paso 1: Migrar la copia y comparar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

Expected: se aplica `2026_11_08_100009_add_requisitos_propios_table`.

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_etapa5.php" "$TMP_DIR/e5_despues.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_etapa5.php" "$TMP_DIR/e5_antes.json" "$TMP_DIR/e5_despues.json"
```

Expected: `SIN DIFERENCIAS`. Cualquier diferencia detiene la etapa.

- [ ] **Paso 2: Recalcular los borradores con el motor nuevo y comparar**

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_resincronizar_etapa5.php" gestpro_verif_tmp
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_etapa5.php" "$TMP_DIR/e5_recalculado.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_etapa5.php" "$TMP_DIR/e5_antes.json" "$TMP_DIR/e5_recalculado.json"
```

Expected: `Recalculados: 9` (los borradores actuales) y `SIN DIFERENCIAS`.

- [ ] **Paso 3: Revisar lo migrado**

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT (SELECT COUNT(*) FROM proyecto_requisito_origenes) AS origenes, (SELECT COUNT(*) FROM proyectos_requisitos WHERE aplica = 1) AS aplican; SHOW CREATE TABLE proyectos_requisitos\G SHOW CREATE TABLE proyectos_expedientes\G SHOW CREATE TABLE proyecto_requisito_origenes\G"
```

Expected:
- `origenes` igual a `aplican` (233 con los datos actuales).
- `requisito_catalogo_id` y `catalogo_version_id` con `DEFAULT NULL`.
- Las dos claves foráneas restauradas, sin `ON DELETE`.
- El índice único `proy_req_expediente_requisito_unique`.
- En `proyecto_requisito_origenes`: clave primaria compuesta, cascada desde `proyectos_requisitos` y restricción desde `requisitos_catalogo`.

- [ ] **Paso 4: Revertir y comparar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=1 --force
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_etapa5.php" "$TMP_DIR/e5_rollback.json" gestpro_verif_tmp --solo-tablas
```

```bash
php "$TMP_DIR/gestpro_comparar_etapa5.php" "$TMP_DIR/e5_tablas_antes.json" "$TMP_DIR/e5_rollback.json"
```

Expected: `SIN DIFERENCIAS`. `requisito_catalogo_id` y `catalogo_version_id` vuelven a `NOT NULL`, y la tabla de orígenes ya no existe.

- [ ] **Paso 5: Volver a migrar y comparar**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_etapa5.php" "$TMP_DIR/e5_remigrado.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_etapa5.php" "$TMP_DIR/e5_antes.json" "$TMP_DIR/e5_remigrado.json"
```

Expected: `SIN DIFERENCIAS`.

- [ ] **Paso 6: Simular el comando de expedientes faltantes**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan expedientes:crear-faltantes --simular
```

Expected: una línea por proyecto en Formulación con fuente y sin expediente, con la plantilla que se preseleccionaría, y al final `Se crearían N expedientes.` (0 con los datos actuales). Anotar el resultado para el informe; el comando sin `--simular` solo se ejecuta con autorización del usuario.

- [ ] **Paso 7: Probar en el navegador sobre la copia**

Arrancar el servidor desde `public/` (`artisan serve` recargaría `gest_pro` desde `.env`):

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8765 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Crear usuarios de prueba solo en la copia; nunca usar sesiones de usuarios reales de `gest_pro`:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'verif@gestpro.test', 'rol' => App\Enums\Rol::Administrador]); App\Models\User::factory()->create(['email' => 'gestor@gestpro.test', 'rol' => App\Enums\Rol::Gestor]);"
```

La contraseña es la predeterminada de `UserFactory`. Como Administrador:

1. En Plantillas de checklist, crear "Obras de agua" para todas las fuentes y sectores, con tres requisitos, y publicarla:
   - uno obligatorio con categoría;
   - uno condicionado con "¿Cuándo aplica?";
   - uno con código común `a1`, la clave del primer requisito SGR.
2. Un expediente radicado solo con la SGR se ve como antes, con el título "Expediente #N" y "Plantilla: SGR", sin nada editable.
3. En un borrador con la SGR:
   - marcar "Obras de agua" en el bloque Plantillas. El requisito `a1` queda en una sola fila con "También en: Obras de agua";
   - el condicionado aparece "Por definir" con "Aplica si: …", y "Aplica" lo pasa a pendiente;
   - el avance suma los requisitos nuevos.
4. Agregar un requisito propio: aparece en "Requisitos propios del proyecto" y el foco vuelve al botón. Editarlo y eliminarlo; uno cumplido no se puede eliminar.
5. En otro borrador, agregar "Obras de agua" y quitar la SGR:
   - desaparecen el panel SGR y la fase SGR;
   - las filas SGR se retiran con su trabajo;
   - el selector de expedientes muestra la plantilla nueva;
   - volver a poner la SGR las recupera.
6. Si algún proyecto ya no tiene la fuente SGR, su expediente avisa que la plantilla ya no es compatible.
7. "Nuevo expediente" desde un radicado copia las plantillas que siguen disponibles y avisa de las quitadas.
8. El tablero: "Expedientes" en el menú, y la fase SGR solo en expedientes con SGR. Ctrl+K con "SGR" lleva a "Expedientes".
9. Excel e impresión de un expediente con plantilla simple y requisitos propios.

Como Gestor: los mismos pasos 3 a 5 funcionan, y la administración de plantillas no aparece. La consola del navegador no muestra errores nuevos.

- [ ] **Paso 8: Limpiar**

Detener el servidor y eliminar la copia:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 9: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas, 523.

Run: `npm test`
Expected: pasan todas, 260.

- [ ] **Paso 10: Informar al usuario y pedir validación**

Informar los totales de pruebas, el resultado de las cuatro comparaciones, el `--simular` y lo revisado en el navegador. Pedir dos decisiones por separado: si valida la etapa y si autoriza aplicar la migración en `gest_pro`.

---

### Tarea 16: Aplicar en `gest_pro` (solo con autorización)

No ejecutar esta tarea sin un sí explícito del usuario a este paso. Si la comparación muestra diferencias, restaurar el respaldo: no usar `migrate:rollback`.

- [ ] **Paso 1: Respaldar**

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro > "$TMP_DIR/gest_pro_antes_etapa_5.sql"
```

Expected: el archivo existe y no está vacío.

- [ ] **Paso 2: Tomar la foto de las tablas antes de migrar**

Con el código nuevo, la API ya espera la tabla de orígenes, así que antes de migrar solo se toman las tablas:

```bash
php "$TMP_DIR/gestpro_foto_etapa5.php" "$TMP_DIR/e5_real_antes.json" gest_pro --solo-tablas
```

- [ ] **Paso 3: Migrar hacia adelante**

Run: `php artisan migrate --force`
Expected: se aplica `2026_11_08_100009_add_requisitos_propios_table`.

- [ ] **Paso 4: Comparar**

```bash
php "$TMP_DIR/gestpro_foto_etapa5.php" "$TMP_DIR/e5_real_despues.json" gest_pro
```

```bash
php "$TMP_DIR/gestpro_comparar_etapa5.php" "$TMP_DIR/e5_real_antes.json" "$TMP_DIR/e5_real_despues.json"
```

Expected: `SIN DIFERENCIAS`.

- [ ] **Paso 5: Informar**

Dar el resultado y la ruta completa del respaldo, y avisar que está en una carpeta temporal. `expedientes:crear-faltantes` sin `--simular` se ejecuta solo si el usuario lo pide aparte.

---

## Criterio de la etapa

La etapa está lista cuando, según el diseño:

- un usuario elige la principal y las complementarias entre las plantillas compatibles con las fuentes y el sector de un proyecto;
- los duplicados se combinan por código común, se agregan requisitos propios y el checklist resultante se sigue desde el módulo existente;
- el expediente automático cubre todas las fuentes y los textos en pantalla dicen "Expediente";
- un expediente que solo tiene la SGR queda exactamente igual que antes.

Junto con la etapa 4, esto cumple el criterio de aceptación principal: un administrador crea y publica una plantilla, y un usuario la usa en un proyecto.
