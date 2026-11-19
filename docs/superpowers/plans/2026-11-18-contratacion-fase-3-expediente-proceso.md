# Contratación, fase 3: expediente de contratación por proceso. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** que cada proceso de contratación tenga su propio checklist, con los requisitos agrupados por etapa, el avance total y por etapa, y un cierre que solo avisa de lo pendiente. El checklist usa el mismo motor que el expediente de viabilización, sin cambiar nada de la viabilización.

**Arquitectura:**
- **Migración 20:** `proyectos_expedientes` gana `tipo` (`viabilizacion` por defecto) y `proceso_id` (único, restrict).
- **Separación de tipos:** `Proyecto::expedientes()` y `resumenPorProyecto()` quedan filtrados a viabilización.
- **Servicios de contratación:**
  - `App\Services\Contratacion\PlantillasContratacion` decide qué plantillas son compatibles, por modalidad, fuente y sector.
  - `ExpedientesContratacion` crea el expediente: solo, al guardar el proceso o al cambiar el sector del proyecto; a mano; o con un comando.
  - `ExpedienteService` presenta las etapas, cierra y congela.
- **Pantallas:** `ExpedienteContratacion.jsx` en `/contratacion/:procesoId`, que reutiliza los subcomponentes del checklist. También el avance y el enlace en el diálogo de procesos y en la ficha del proyecto.

**Tecnologías:** Laravel 12, PHP 8.2, MariaDB 10.4, React 18 con Laravel Mix y react-router, PHPUnit sobre SQLite en memoria, Vitest.

**Fuente:**
- La propuesta `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, aprobada:
  - fase 3;
  - criterios 7, 8, 12 y 13 de la sección 9;
  - regla R12: un expediente cerrado queda congelado.
- El diseño `docs/superpowers/specs/2026-11-17-contratacion-revision-design.md`: la revisión por etapa solo avisa.

**Estado de partida:** rama `feature/contratacion`, igual a `main` (`32ac9da`). La fase 2 está completa (etapas 3A, 3B y 3C). Pasan 1133 pruebas PHP y 665 JS.

**Datos reales comprobados:**
- **Expedientes:** 10 de viabilización.
- **Plantillas de contratación:** solo "Licitación pública – SECOP II", **en borrador**.
- **Procesos:** 1, de selección abreviada.

Al aplicar esta fase **ningún proceso recibe expediente** hasta que la entidad publique una plantilla de contratación que corresponda.

---

## Reglas de ejecución

- **Nada de esto se aplica a `gest_pro` sin autorización del usuario.** Allí nunca se usa `migrate:rollback`: si hay que volver atrás, se restaura el respaldo. La tarea 10 aplica la migración, y solo con un sí explícito.
- **La viabilización no cambia ni una respuesta ni una cifra.** Las pruebas de `tests/Feature/Viabilizacion/**` y los fixtures de paridad no se modifican y deben pasar. `ExpedientesContratacionAisladosTest` comprueba que nada de la viabilización cambia cuando hay expedientes de contratación.
- Commits autorizados solo en `feature/contratacion`. Sin push. Mensajes en inglés, terminados con la línea `Co-Authored-By` del modelo que comitea.
- Nomenclatura en español sin tildes para tablas, columnas, clases y métodos nuevos. Los textos para el usuario van en español con tuteo. Las migraciones, que son herramientas del operador, conservan el registro de usted.
- Pruebas: `php artisan test` sin definir `DB_DATABASE`, para que use SQLite en memoria, y `npm test`. Si Vitest crea `resources/js/node_modules`, se borra.
- **Aplicar los bloques exactos.** Cada reemplazo aparece una sola vez en su archivo. Si no lo encuentras tal cual, detente y avisa. Se recomienda aplicarlos con un script que los lea del plan y exija una coincidencia por reemplazo.
- **No usar `git stash`:** el usuario tiene stashes propios.
- El usuario suele tener `npm run watch` corriendo, que reescribe `public/`. Solo la tarea 7 comitea `public/`, después de `npm run production`.
- No tocar ni comitear `public/images/marca-completa.png` ni `public/images/marca-completa1.png`: son cambios del usuario.
- `.env` está versionado: nunca se agrega a un commit. Comitear siempre con rutas explícitas.
- `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio. Hay que definirlo en cada comando, porque el estado de la terminal no se conserva.
- **SQLite y MariaDB difieren en las cascadas.** Borrar un proyecto con un expediente de contratación falla en MariaDB (error 1451), por el restrict de `proceso_id`, si no se borran antes esos expedientes. SQLite no lo muestra. La tarea 8 lo comprueba en MariaDB.
- **El prefijo de la API (`/GestPro`) es también la base del SPA.** `/contratacion/:procesoId` no es una ruta GET de la API.

## Decisiones de este plan

**Datos**
- `tipo` es `viabilizacion` o `contratacion`. `proceso_id` es único: un expediente por proceso, y un proceso con expediente no se borra.
- El estado nuevo `cerrado` es para contratación (borrador → cerrado). La viabilización sigue borrador → radicado.
- El cierre reutiliza `fecha_radicado` y `usuario_radicado_id`, que en contratación se presentan como `fecha_cierre` y `usuario_cierre`.

**Separación de tipos**
- `Proyecto::expedientes()` devuelve solo los de viabilización, y hay una relación aparte para los de contratación.
- `resumenPorProyecto()` también filtra viabilización.
- Por eso el tablero, la ficha (bloque Formulación), la creación automática, el "un solo borrador", las plantillas compatibles y `expedientes:crear-faltantes` siguen viendo solo viabilización.
- La configuración SGR, su confirmación y la radicación rechazan con 422 un expediente de contratación. Cerrar rechaza uno de viabilización.

**Plantillas compatibles**
- Una plantilla es compatible si es de uso contratación, está activa, tiene motor simple y una versión vigente publicada.
- Además, debe cubrir:
  - la **modalidad** del proceso, o todas;
  - su **fuente**, o todas; sin fuente, solo las de todas las fuentes;
  - el **sector** del proyecto, o todos; sin sector, solo los de todos.

**Creación del expediente**
- **Automática:** solo si hay **exactamente una** plantilla compatible, y nunca interrumpe el guardado. Se intenta cuando:
  - se crea un proceso;
  - se le cambia la modalidad o la fuente;
  - cambia el sector del proyecto.
- **Manual:** `POST /procesos/{p}/expediente` con las plantillas elegidas. La primera es la principal.
- **Comando:** `expedientes:crear-faltantes-contratacion {--simular}`.
- **Concurrencia:** la creación bloquea el proceso y valida dentro de la transacción. Borrar un proyecto bloquea sus procesos y borra antes sus expedientes de contratación.

**Checklist**
- Cada fila trae su etapa.
- La respuesta trae el avance por etapa, en el orden del catálogo, con "Sin etapa" al final.
- Los requisitos propios de contratación exigen etapa.
- El avance se calcula por conteo, como hoy. La ponderación queda para la fase 4.

**Cerrar**
- `POST /expedientes/{e}/cerrar`:
  - sin `confirmar` y con pendientes, responde 422 con cuántos faltan y en qué etapas, y no cierra;
  - con `confirmar`, cierra y lo registra en el historial.
- Cerrado = congelado: marcar, soportes, no aplica, propios, versiones nuevas y eliminar responden 422 "El expediente está cerrado."

**Proceso**
- Cada proceso trae su `expediente` (estado, avance y aviso de plantilla) sin N+1.
- El aviso distingue una plantilla inactiva de una que ya no corresponde a la modalidad, la fuente o el sector.
- Un proceso con expediente no se elimina.

**Pantallas**
- **`/contratacion/:procesoId`:**
  - cabecera del proceso con enlace a la ficha;
  - avance total y por etapa;
  - requisitos por etapa, con todo lo del checklist de viabilización (soportes, no aplica, propios con etapa, historial, versión nueva);
  - "Cerrar expediente" con el aviso de pendientes;
  - solo lectura si está cerrado o si quien entra es lector;
  - sin expediente: las plantillas compatibles y "Crear expediente".
- **Diálogo de procesos y ficha del proyecto:** el avance del expediente de cada proceso (nunca sumado con otros avances) y el enlace "Checklist".

## Fuera de esta etapa

Fases siguientes de la propuesta:
- **Fase 4:** ponderación, fechas programadas por etapa y alertas.
- **Fase 5:** revisión por etapa.
- **Fase 6:** tablero de contratación.

Tampoco están reabrir un expediente cerrado ni exportarlo a Excel.

## Lo que ya se ensayó

El plan se ensayó completo en un clon descartable fuera del repositorio, y este documento se generó con el código de ese ensayo. Un script comprobó que aplicar sus bloques sobre la rama reproduce el ensayo exactamente.

- **Pruebas:** con el código de las tareas 2 a 6 pasan 1202 pruebas PHP y 708 JS. Cada tarea intermedia también pasa sus suites completas. Ninguna prueba existente de viabilización ni fixture de paridad se modificó.
- **En una copia de `gest_pro` con los datos reales,** las fotos de la tarea 1 no mostraron ninguna diferencia al migrar, al revertir la migración 20 (comparando solo tablas) ni al volver a migrar. Cubren los 10 expedientes de viabilización con su API, las plantillas y los requisitos, los contratos, los procesos y los listados.
- **En MariaDB** se confirmó que borrar un proyecto con un expediente de contratación fallaba (error 1451). Con la corrección de la tarea 3, `eliminarProyecto` lo borra limpio y deja intactos los 10 expedientes de viabilización.
- **En el navegador, sobre esa copia:**
  - **Creación automática:** con la plantilla de licitación pública publicada solo en la copia y el proceso cambiado a esa modalidad, el expediente nació solo, con 25 requisitos.
  - **Página `/contratacion/1`:** mostró las 11 etapas con su avance. Marcar un requisito actualizó el total y la etapa.
  - **Cierre:** "Cerrar expediente" pidió confirmación con los 24 pendientes por etapa. Al confirmar quedó "Cerrado" y en solo lectura, y la API rechazó escribir con "El expediente está cerrado.".
  - **Viabilización:** `GET /expedientes` siguió con 10, la página de viabilización se vio igual y la ficha del proyecto no mezcló los expedientes. El bloque Procesos mostró "1 de 25 requisitos · 4 % · Cerrado" con su enlace al checklist.
  - **Consola:** sin errores nuevos.
- **Una revisión independiente** no encontró fugas entre tipos ni problemas graves. Sus hallazgos, uno medio y cuatro bajos, ya están corregidos en las tareas correspondientes:
  - una carrera entre borrar un proyecto y crear un expediente;
  - el aviso de plantilla inactiva;
  - ruido en el log de la creación automática;
  - validar dentro de la transacción;
  - crear el expediente al cambiar el sector del proyecto.

---

### Tarea 1: Punto de partida y fotos con el código actual

Las fotos se toman **antes de cambiar código**. La copia `gestpro_verif_tmp` se conserva hasta la tarea 8.

- [ ] **Paso 1: Comprobar la rama, el estado y las pruebas**

Run: `git branch --show-current`
Expected: `feature/contratacion`

Run: `git status --short`
Expected: como mucho, `public/` (si corre `npm run watch`) y las dos imágenes del usuario.

Run: `php artisan test`
Expected: pasan todas, 1133. Si alguna falla, detenerse y avisar.

Run: `npm test`
Expected: pasan todas, 665.

- [ ] **Paso 2: Crear la copia descartable**

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE IF EXISTS gestpro_verif_tmp; CREATE DATABASE gestpro_verif_tmp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro | /c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp
```

Si MySQL no responde (`ERROR 2002`), está apagado. Hay que pedirle al usuario que lo arranque desde XAMPP.

- [ ] **Paso 3: Crear los scripts de comprobación**

Si siguen en `$TMP_DIR` desde etapas anteriores, se reutilizan tal cual. Si no, se crean con este contenido.

`$TMP_DIR/gestpro_foto_contratacion2.php` toma los expedientes, con la API de viabilización, y las plantillas y requisitos del catálogo:

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

`$TMP_DIR/gestpro_foto_contratacion3.php` toma los contratos con su ejecución, los procesos y los listados de la API:

```php
<?php
// Foto para comparar antes y después de migrar: contratos (con su ejecución), procesos y
// expedientes de viabilización, que esta etapa no debe alterar.
// Uso, desde la raíz del repositorio:
//   php gestpro_foto_contratacion3.php <archivo.json> <base esperada> [--solo-tablas]
// --solo-tablas lee las tablas sin pasar por controladores ni servicios.
// El estado del contrato cambia a propósito (texto libre → valor controlado): se compara el
// texto original, que la migración guarda en estado_original.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ProyectoController;
use App\Models\ProyectoExpediente;
use App\Services\Viabilizacion\ExpedienteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (DB::getDatabaseName() !== $argv[2]) {
    exit('Abortado: la base es ' . DB::getDatabaseName() . ", se esperaba {$argv[2]}\n");
}

// Solo las columnas que existían antes de esta etapa (sin estado, que se compara aparte).
$columnasContrato = ['id', 'proyecto', 'n_contrato', 'objeto', 'contratante', 'contratista', 'valor', 'valor_inicial',
    'fecha_inicio', 'fecha_fin', 'fecha_fin_inicial', 'interventoria', 'avance_fisico', 'avance_financiero',
    'anticipo', 'porcentaje_anticipo', 'proceso_licitacion'];
$columnasProceso = ['id', 'proyecto', 'codigo_proceso', 'tipo_proceso', 'modalidad', 'entidad_contratante',
    'tipo_proponente', 'entidad_proponente', 'monto', 'objeto', 'plataforma', 'url_proceso', 'estado',
    'usuario_creo_id', 'created_at', 'updated_at'];
$estadoTexto = Schema::hasColumn('contratos', 'estado_original') ? 'COALESCE(estado_original, estado)' : 'estado';

$foto = [
    'tablas' => [
        'contratos' => DB::table('contratos')->orderBy('id')->get($columnasContrato),
        'estado_texto' => DB::table('contratos')->orderBy('id')->pluck(DB::raw($estadoTexto), 'id'),
        'procesos' => DB::table('procesos_licitacion')->orderBy('id')->get($columnasProceso),
        'actas' => DB::table('avance_financiero')->orderBy('id')->get(),
        'actividades' => DB::table('actividades_contrato')->orderBy('id')->get(),
        'avances_actividad' => DB::table('actividad_avances')->orderBy('id')->get(),
        'modificaciones' => DB::table('modificaciones_contrato')->orderBy('id')->get(),
        'anexos' => DB::table('anexos_contratos')->orderBy('id')->get(),
        'expedientes' => DB::table('proyectos_expedientes')->orderBy('id')->get(['id', 'proyecto_id', 'estado']),
        'filas' => DB::table('proyectos_requisitos')->orderBy('id')->get(['id', 'expediente_id', 'cumplido', 'estado_aplicabilidad']),
    ],
];

if (! in_array('--solo-tablas', $argv, true)) {
    $controlador = app(ProyectoController::class);
    $sinEstado = function (array $contrato): array {
        unset($contrato['estado']);

        return $contrato;
    };
    $porProyecto = [];
    foreach (DB::table('contratos')->whereNotNull('proyecto')->distinct()->orderBy('proyecto')->pluck('proyecto') as $proyecto) {
        $respuesta = $controlador->listarContratos(new Request(['proyecto' => $proyecto]));
        $porProyecto[$proyecto] = array_map($sinEstado, json_decode($respuesta->getContent(), true));
    }
    $proyectos = json_decode($controlador->proyectos()->getContent(), true);
    $contratosDeProyectos = [];
    foreach ($proyectos as $proyecto) {
        foreach ($proyecto['contratos'] ?? [] as $contrato) {
            $contratosDeProyectos[$contrato['id']] = $sinEstado($contrato);
        }
    }
    ksort($contratosDeProyectos);
    $servicio = app(ExpedienteService::class);
    $foto['api'] = [
        'listar_contratos' => $porProyecto,
        'contratos_en_proyectos' => $contratosDeProyectos,
        'resumen_expedientes' => $servicio->resumenPorProyecto(),
        'expedientes' => ProyectoExpediente::query()->orderBy('id')->get()
            ->map(fn (ProyectoExpediente $e) => $servicio->presentar($e))->all(),
    ];
}

file_put_contents($argv[1], json_encode($foto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo 'Contratos: ' . count($foto['tablas']['contratos']) . ', procesos: ' . count($foto['tablas']['procesos'])
    . ', actas: ' . count($foto['tablas']['actas']) . ', expedientes: ' . count($foto['tablas']['expedientes'])
    . (isset($foto['api']) ? ', con API' : ', solo tablas') . "\n";
```

`$TMP_DIR/gestpro_comparar_contratacion3.php` es el mismo comparador para las dos fotos:

```php
<?php
// Comprueba que todo lo que había antes sigue igual después. Lo nuevo (campos que la etapa
// agrega) se permite; un valor distinto o algo que falta, no.
// Uso: php gestpro_comparar_contratacion3.php <antes.json> <despues.json>

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

- [ ] **Paso 4: Tomar las fotos de antes, con el código actual**

Desde la raíz del repositorio, por cada foto (`2` y `3`), una completa y una solo de tablas:

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c6_antes_$f.json" gestpro_verif_tmp; DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c6_tablas_antes_$f.json" gestpro_verif_tmp --solo-tablas; done
```

Expected: `Expedientes: 10, filas: 293, plantillas: 3, requisitos: 237` y `Contratos: 7, procesos: 1, actas: 2, expedientes: 10`, con las cifras actuales de `gest_pro`.

---

### Tarea 2: Migración 20: tipo y proceso del expediente, y filtros de viabilización

`proyectos_expedientes` gana `tipo` y `proceso_id`; todo lo que hoy lee expedientes de un proyecto queda filtrado a viabilización, y las rutas SGR rechazan los de contratación.

**Files:**
- Create: `database/migrations/2026_11_18_100019_add_tipo_y_proceso_a_expedientes.php`
- Test (create): `tests/Feature/Contratacion/ExpedientesContratacionAisladosTest.php`
- Test (create): `tests/Feature/Schema/ExpedientesTipoProcesoTest.php`
- Modify: `app/Models/ProcesoLicitacion.php`
- Modify: `app/Models/Proyecto.php`
- Modify: `app/Models/ProyectoExpediente.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/ExpedientesContratacionAisladosTest.php`:

```php
<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\Municipio;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Database\Seeders\DepartamentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La viabilización no cambia con los expedientes de contratación: cada lectura y cada regla de
 * viabilización da exactamente lo mismo con ellos que sin ellos. Los expedientes de
 * contratación se insertan directamente: su creación es de otro servicio.
 */
class ExpedientesContratacionAisladosTest extends TestCase
{
    use RefreshDatabase;

    private int $formulacion;
    private int $licitacion;
    private string $municipio;

    /** Proyecto con expedientes de viabilización: uno radicado y uno en borrador, el más reciente. */
    private Proyecto $conExpedientes;
    private ProyectoExpediente $borrador;

    /** Proyecto en Formulación con fuente, todavía sin expediente de viabilización. */
    private Proyecto $sinExpediente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->seed(DepartamentosSeeder::class);
        $this->municipio = Municipio::create(['codigo' => '20045', 'nombre' => 'BECERRIL', 'activo' => true, 'departamento' => '20'])->codigo;
        $this->formulacion = DB::table('fases')->insertGetId(['nombre' => 'Formulación']);
        $this->licitacion = DB::table('fases')->insertGetId(['nombre' => 'Licitación']);

        $servicio = app(ExpedienteService::class);
        $this->conExpedientes = $this->proyecto('Acueducto');
        $radicado = $servicio->crear($this->conExpedientes, ['fase_sgr' => 3], null);
        DB::table('proyectos_expedientes')->where('id', $radicado->id)->update(['estado' => ProyectoExpediente::RADICADO]);
        $this->borrador = $servicio->crear($this->conExpedientes, ['fase_sgr' => 2], null);
        $this->sinExpediente = $this->proyecto('Parque');

        // Los procesos existen desde antes: lo único que cambia entre las dos lecturas son los
        // expedientes de contratación.
        foreach ([$this->conExpedientes, $this->conExpedientes, $this->sinExpediente] as $i => $proyecto) {
            $this->proceso($proyecto, 'LP-00' . ($i + 1));
        }
    }

    private function proyecto(string $nombre): Proyecto
    {
        $proyecto = Proyecto::create(['municipio' => $this->municipio, 'nombre' => $nombre, 'fase' => $this->formulacion]);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        return $proyecto;
    }

    private function proceso(Proyecto $proyecto, string $codigo): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => $proyecto->id,
            'objeto' => 'Obra',
            'modalidad' => Modalidad::where('clave', 'licitacion_publica')->firstOrFail()->id,
            'plataforma' => 'secop_ii',
            'codigo_proceso' => $codigo,
        ]);
    }

    /** Un expediente de contratación por proceso, más reciente que los de viabilización y en borrador. */
    private function agregarExpedientesContratacion(): array
    {
        return ProcesoLicitacion::orderBy('id')->get()->map(fn (ProcesoLicitacion $p) => DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => $p->proyecto,
            'tipo' => ProyectoExpediente::TIPO_CONTRATACION,
            'proceso_id' => $p->id,
            'estado' => ProyectoExpediente::BORRADOR,
            'fecha_configuracion' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]))->all();
    }

    private function como(Rol $rol = Rol::Consulta): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    /** Todo lo que la viabilización lee de los expedientes de un proyecto. */
    private function lecturas(): array
    {
        $servicio = app(ExpedienteService::class);
        $lecturas = [
            'resumen' => $this->como()->getJson('/GestPro/expedientes')->assertOk()->json(),
            'simular' => $this->simular(),
        ];

        foreach (['con' => $this->conExpedientes, 'sin' => $this->sinExpediente] as $clave => $proyecto) {
            $proyecto = $proyecto->fresh();
            $saliendo = (clone $proyecto)->forceFill(['fase' => $this->licitacion]);
            $lecturas[$clave] = [
                'index' => $this->como()->getJson("/GestPro/proyectos/{$proyecto->id}/expedientes")->assertOk()->json(),
                'resumen' => $servicio->resumenPorProyecto($proyecto->id),
                'ficha' => $this->como()->getJson("/GestPro/proyectos/{$proyecto->id}/ficha")->assertOk()->json('expediente'),
                'corresponde' => $servicio->correspondeAutomatico($proyecto),
                'advertencia' => $servicio->advertenciaAlSalirDeFormulacion($saliendo, $this->formulacion),
                'compatibles' => $this->como()->getJson("/GestPro/proyectos/{$proyecto->id}/plantillas-compatibles")->assertOk()->json(),
            ];
        }

        return $lecturas;
    }

    private function simular(): string
    {
        Artisan::call('expedientes:crear-faltantes', ['--simular' => true]);

        return Artisan::output();
    }

    public function test_las_lecturas_de_viabilizacion_no_cambian_con_expedientes_de_contratacion(): void
    {
        $sin = $this->lecturas();
        // Lo que se compara no está vacío: hay expedientes, resumen y un proyecto por crear.
        $this->assertCount(2, $sin['con']['index']['expedientes']);
        $this->assertSame($this->borrador->id, $sin['con']['ficha']['expediente_id']);
        $this->assertSame(2, $sin['con']['ficha']['cantidad_expedientes']);
        $this->assertTrue($sin['sin']['corresponde']);
        $this->assertNull($sin['sin']['ficha']);
        $this->assertStringContainsString("#{$this->sinExpediente->id} Parque", $sin['simular']);
        $this->assertSame("El proyecto salió de Formulación con el expediente #{$this->borrador->id} sin radicar.", $sin['con']['advertencia']);

        $contratacion = $this->agregarExpedientesContratacion();
        $this->assertGreaterThan($this->borrador->id, min($contratacion));

        $this->assertSame($sin, $this->lecturas());
    }

    public function test_el_expediente_de_contratacion_no_impide_crear_el_de_viabilizacion(): void
    {
        $this->agregarExpedientesContratacion();
        $servicio = app(ExpedienteService::class);

        // El borrador de contratación no cuenta como borrador del proyecto.
        $creado = $servicio->crearAutomatico($this->sinExpediente->fresh(), null);
        $this->assertNotNull($creado);
        $this->assertSame(ProyectoExpediente::TIPO_VIABILIZACION, $creado->refresh()->tipo);
        $this->assertNull($creado->proceso_id);
        $this->assertSame([$creado->id], $this->sinExpediente->expedientes()->pluck('id')->all());

        // El de viabilización en borrador sí lo impide, con el mismo mensaje.
        try {
            $servicio->crear($this->conExpedientes, ['fase_sgr' => 3], null);
            $this->fail('Debía impedirse un segundo borrador.');
        } catch (ReglaNegocioException $e) {
            $this->assertSame(
                "El proyecto ya tiene el expediente #{$this->borrador->id} en borrador. Radícalo o elimínalo antes de crear otro.",
                $e->getMessage()
            );
        }
    }

    public function test_la_api_tambien_crea_el_de_viabilizacion_junto_a_uno_de_contratacion(): void
    {
        $this->agregarExpedientesContratacion();

        $id = $this->como(Rol::Gestor)->postJson("/GestPro/proyectos/{$this->sinExpediente->id}/expedientes", ['fase_sgr' => 3])
            ->assertCreated()
            ->json('id');

        $this->assertSame(ProyectoExpediente::TIPO_VIABILIZACION, ProyectoExpediente::findOrFail($id)->tipo);
    }

    public function test_las_plantillas_compatibles_no_aceptan_un_expediente_de_contratacion(): void
    {
        [$contratacion] = $this->agregarExpedientesContratacion();

        $this->como()->getJson("/GestPro/proyectos/{$this->conExpedientes->id}/plantillas-compatibles?expediente={$contratacion}")
            ->assertNotFound();
        $this->como()->getJson("/GestPro/proyectos/{$this->conExpedientes->id}/plantillas-compatibles?expediente={$this->borrador->id}")
            ->assertOk();
    }

    public function test_la_configuracion_sgr_y_la_radicacion_rechazan_un_expediente_de_contratacion(): void
    {
        [$contratacion] = $this->agregarExpedientesContratacion();
        $antes = DB::table('proyectos_expedientes')->where('id', $contratacion)->first();
        $historial = DB::table('checklist_historial')->count();
        $mensaje = 'El expediente es de contratación: no tiene configuración SGR ni se radica.';

        $this->como(Rol::Gestor)->putJson("/GestPro/expedientes/{$contratacion}/configuracion", ['fase_sgr' => 2])
            ->assertStatus(422)->assertExactJson(['mensaje' => $mensaje]);
        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$contratacion}/configuracion/confirmar", ['fase_sgr' => 2])
            ->assertStatus(422)->assertExactJson(['mensaje' => $mensaje]);
        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$contratacion}/radicar")
            ->assertStatus(422)->assertExactJson(['mensaje' => $mensaje]);

        $this->assertEquals($antes, DB::table('proyectos_expedientes')->where('id', $contratacion)->first());
        $this->assertSame($historial, DB::table('checklist_historial')->count());
    }
}
```

Crear `tests/Feature/Schema/ExpedientesTipoProcesoTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Migración 20: el expediente sabe si es de viabilización o de contratación y, el de
 * contratación, de qué proceso es. Las pruebas no crean filas hijas del expediente (requisitos,
 * plantillas, historial): en SQLite, poner o quitar una clave foránea reconstruye la tabla y,
 * dentro de la transacción de RefreshDatabase, eso borraría en cascada las filas hijas. En
 * MariaDB no pasa.
 */
class ExpedientesTipoProcesoTest extends TestCase
{
    use RefreshDatabase;

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_18_100019_add_tipo_y_proceso_a_expedientes.php');
    }

    private function columna(string $columna): ?array
    {
        return collect(Schema::getColumns('proyectos_expedientes'))->firstWhere('name', $columna);
    }

    private function foranea(): ?array
    {
        return collect(Schema::getForeignKeys('proyectos_expedientes'))->first(fn ($f) => $f['columns'] === ['proceso_id']);
    }

    private function indice(array $columnas, bool $unico): ?array
    {
        return collect(Schema::getIndexes('proyectos_expedientes'))
            ->first(fn ($i) => $i['columns'] === $columnas && $i['unique'] === $unico);
    }

    private function proyecto(): int
    {
        return DB::table('proyectos')->insertGetId(['nombre' => 'Acueducto']);
    }

    private function proceso(?int $proyecto = null): int
    {
        return DB::table('procesos_licitacion')->insertGetId([
            'proyecto' => $proyecto ?? $this->proyecto(), 'codigo_proceso' => 'LP-001',
        ]);
    }

    /** Un expediente insertado como lo haría el código anterior a la migración: sin tipo ni proceso. */
    private function expedienteAntiguo(?int $proyecto = null): int
    {
        return DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => $proyecto ?? $this->proyecto(), 'estado' => 'borrador',
        ]);
    }

    private function expedienteContratacion(int $proceso, array $cambios = []): int
    {
        return DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => DB::table('procesos_licitacion')->where('id', $proceso)->value('proyecto'),
            'tipo' => 'contratacion', 'proceso_id' => $proceso, 'estado' => 'borrador', ...$cambios,
        ]);
    }

    private function asertarMigrada(): void
    {
        $tipo = $this->columna('tipo');
        $this->assertNotNull($tipo);
        $this->assertFalse($tipo['nullable']);
        $this->assertStringContainsString('viabilizacion', (string) $tipo['default']);

        $proceso = $this->columna('proceso_id');
        $this->assertNotNull($proceso);
        $this->assertTrue($proceso['nullable']);

        $foranea = $this->foranea();
        $this->assertSame('procesos_licitacion', $foranea['foreign_table']);
        $this->assertSame('restrict', strtolower($foranea['on_delete']));

        $this->assertNotNull($this->indice(['proceso_id'], true));
        $this->assertNotNull($this->indice(['proyecto_id', 'tipo'], false));
    }

    private function asertarRevertida(): void
    {
        $this->assertFalse(Schema::hasColumn('proyectos_expedientes', 'tipo'));
        $this->assertFalse(Schema::hasColumn('proyectos_expedientes', 'proceso_id'));
        $this->assertNull($this->foranea());
        $this->assertNull($this->indice(['proceso_id'], true));
        $this->assertNull($this->indice(['proyecto_id', 'tipo'], false));
    }

    // ------------------------------------------------------------- esquema

    public function test_el_expediente_tiene_tipo_y_proceso_con_su_clave_foranea_e_indices(): void
    {
        $this->asertarMigrada();
    }

    public function test_los_expedientes_existentes_quedan_de_viabilizacion_y_sin_proceso(): void
    {
        $migracion = $this->migracion();
        $migracion->down();
        $id = $this->expedienteAntiguo();

        $migracion->up();

        $fila = DB::table('proyectos_expedientes')->where('id', $id)->first();
        $this->assertSame('viabilizacion', $fila->tipo);
        $this->assertNull($fila->proceso_id);
        $this->assertSame('viabilizacion', DB::table('proyectos_expedientes')->where('id', $this->expedienteAntiguo())->value('tipo'));
    }

    public function test_un_proceso_tiene_como_maximo_un_expediente(): void
    {
        $proceso = $this->proceso();
        $this->expedienteContratacion($proceso);

        $this->expectException(QueryException::class);
        $this->expedienteContratacion($proceso);
    }

    public function test_un_proceso_con_expediente_no_se_borra(): void
    {
        $proceso = $this->proceso();
        $this->expedienteContratacion($proceso);
        $libre = $this->proceso();

        DB::table('procesos_licitacion')->where('id', $libre)->delete();
        $this->assertFalse(DB::table('procesos_licitacion')->where('id', $libre)->exists());

        $this->expectException(QueryException::class);
        DB::table('procesos_licitacion')->where('id', $proceso)->delete();
    }

    // ------------------------------------------------------------- rollback

    public function test_el_rollback_se_detiene_si_hay_expedientes_de_contratacion(): void
    {
        $id = $this->expedienteContratacion($this->proceso());

        try {
            $this->migracion()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('contratación', $e->getMessage());
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }
        $this->asertarMigrada();

        // Un expediente sin tipo de contratación pero con proceso también se perdería.
        DB::table('proyectos_expedientes')->where('id', $id)->update(['tipo' => 'viabilizacion']);
        try {
            $this->migracion()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }
        $this->asertarMigrada();

        DB::table('proyectos_expedientes')->where('id', $id)->update(['proceso_id' => null]);
        $this->migracion()->down();
        $this->asertarRevertida();
        $this->assertTrue(DB::table('proyectos_expedientes')->where('id', $id)->exists());
    }

    public function test_en_ambos_sentidos_se_puede_repetir_y_conserva_las_filas(): void
    {
        $migracion = $this->migracion();
        $id = $this->expedienteAntiguo();

        $migracion->up(); // ya migrada: no falla
        $this->asertarMigrada();

        $migracion->down();
        $migracion->down();
        $this->asertarRevertida();

        $migracion->up();
        $migracion->up();
        $this->asertarMigrada();
        $this->assertSame('viabilizacion', DB::table('proyectos_expedientes')->where('id', $id)->value('tipo'));
    }

    public function test_reintentar_tras_un_fallo_a_medias_completa_la_migracion_y_la_reversion(): void
    {
        $migracion = $this->migracion();
        $migracion->down();
        $id = $this->expedienteAntiguo();

        // Lo que dejaría un fallo de la subida: las columnas y el índice único, sin la clave
        // foránea ni el índice por proyecto y tipo.
        Schema::table('proyectos_expedientes', function (Blueprint $table) {
            $table->string('tipo', 20)->default('viabilizacion');
            $table->unsignedBigInteger('proceso_id')->nullable();
        });
        Schema::table('proyectos_expedientes', function (Blueprint $table) {
            $table->unique('proceso_id');
        });
        $this->assertNull($this->foranea());

        $migracion->up();
        $this->asertarMigrada();
        $this->assertSame('viabilizacion', DB::table('proyectos_expedientes')->where('id', $id)->value('tipo'));

        // Lo que dejaría un fallo de la bajada: la clave foránea y el índice por proyecto y
        // tipo ya quitados.
        Schema::table('proyectos_expedientes', function (Blueprint $table) {
            $table->dropForeign(['proceso_id']);
        });
        $indice = $this->indice(['proyecto_id', 'tipo'], false)['name'];
        Schema::table('proyectos_expedientes', function (Blueprint $table) use ($indice) {
            $table->dropIndex($indice);
        });

        $migracion->down();
        $this->asertarRevertida();
        $this->assertTrue(DB::table('proyectos_expedientes')->where('id', $id)->exists());
    }

    // ------------------------------------------------------------- modelos

    public function test_el_modelo_conoce_tipos_estados_proceso_y_scopes(): void
    {
        $this->assertSame('viabilizacion', ProyectoExpediente::TIPO_VIABILIZACION);
        $this->assertSame('contratacion', ProyectoExpediente::TIPO_CONTRATACION);
        $this->assertSame(['viabilizacion', 'contratacion'], ProyectoExpediente::TIPOS);
        $this->assertSame('cerrado', ProyectoExpediente::CERRADO);

        $proyecto = $this->proyecto();
        $proceso = $this->proceso($proyecto);
        $viabilizacion = ProyectoExpediente::create(['proyecto_id' => $proyecto, 'estado' => 'borrador'])->refresh();
        $contratacion = ProyectoExpediente::create([
            'proyecto_id' => $proyecto, 'estado' => 'borrador',
            'tipo' => ProyectoExpediente::TIPO_CONTRATACION, 'proceso_id' => $proceso,
        ])->refresh();

        $this->assertSame('viabilizacion', $viabilizacion->tipo);
        $this->assertTrue($viabilizacion->esViabilizacion());
        $this->assertFalse($viabilizacion->esContratacion());
        $this->assertNull($viabilizacion->proceso);

        $this->assertTrue($contratacion->esContratacion());
        $this->assertFalse($contratacion->esViabilizacion());
        $this->assertSame($proceso, $contratacion->proceso_id);
        $this->assertSame($proceso, $contratacion->proceso->id);

        $this->assertSame([$viabilizacion->id], ProyectoExpediente::viabilizacion()->pluck('id')->all());
        $this->assertSame([$contratacion->id], ProyectoExpediente::contratacion()->pluck('id')->all());

        $modelo = Proyecto::findOrFail($proyecto);
        $this->assertSame([$viabilizacion->id], $modelo->expedientes()->pluck('id')->all());
        $this->assertSame([$viabilizacion->id], $modelo->expedientes->pluck('id')->all());
        $this->assertSame([$contratacion->id], $modelo->expedientesContratacion()->pluck('id')->all());

        $this->assertSame($contratacion->id, ProcesoLicitacion::findOrFail($proceso)->expediente->id);
        $this->assertNull(ProcesoLicitacion::findOrFail($this->proceso($proyecto))->expediente);
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/ExpedientesContratacionAisladosTest.php tests/Feature/Schema/ExpedientesTipoProcesoTest.php`
Expected: FALLAN las 13: no existen las columnas `tipo` y `proceso_id`, los scopes ni el filtro de viabilización en `Proyecto::expedientes()` y `resumenPorProyecto()`.

- [ ] **Paso 3: Implementar**

Crear `database/migrations/2026_11_18_100019_add_tipo_y_proceso_a_expedientes.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Un expediente es de viabilización (el de siempre, por proyecto) o de contratación (uno por
 * proceso de contratación). `tipo` lo dice; todos los expedientes que ya existen quedan de
 * viabilización. `proceso_id` es el proceso del expediente de contratación: único (un expediente
 * por proceso) y con restricción (un proceso con expediente no se borra). El índice por proyecto
 * y tipo sirve a las consultas de viabilización, que filtran siempre por tipo.
 */
return new class extends Migration
{
    private const TABLA = 'proyectos_expedientes';
    private const UNICO = 'proy_expedientes_proceso_unique';
    private const INDICE = 'proy_expedientes_proyecto_tipo_idx';

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. El índice único va antes de la clave foránea,
        // que así lo usa en vez de crear el suyo.
        if (! Schema::hasColumn(self::TABLA, 'tipo')) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->string('tipo', 20)->default('viabilizacion');
            });
        }
        if (! Schema::hasColumn(self::TABLA, 'proceso_id')) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->unsignedBigInteger('proceso_id')->nullable();
            });
        }
        if (! $this->indice(['proceso_id'], true)) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->unique('proceso_id', self::UNICO);
            });
        }
        if (! $this->foranea()) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->foreign('proceso_id')->references('id')->on('procesos_licitacion')->restrictOnDelete();
            });
        }
        if (! $this->indice(['proyecto_id', 'tipo'], false)) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->index(['proyecto_id', 'tipo'], self::INDICE);
            });
        }
    }

    public function down(): void
    {
        $contratacion = Schema::hasColumn(self::TABLA, 'tipo')
            && DB::table(self::TABLA)->where('tipo', '!=', 'viabilizacion')->exists();
        $conProceso = Schema::hasColumn(self::TABLA, 'proceso_id')
            && DB::table(self::TABLA)->whereNotNull('proceso_id')->exists();
        if ($contratacion || $conProceso) {
            throw new RuntimeException('No se puede revertir: hay expedientes de contratación o asociados a un proceso de contratación, que se perderían. Restaure un respaldo.');
        }

        // Primero la clave foránea y los índices, después las columnas; cada paso con su guarda.
        // Se quitan por el nombre que entrega el esquema; SQLite no da nombre a la clave foránea
        // y ahí se deja la deducción de Laravel.
        if ($foranea = $this->foranea()) {
            $nombre = $foranea['name'] ?? ['proceso_id'];
            Schema::table(self::TABLA, function (Blueprint $table) use ($nombre) {
                $table->dropForeign($nombre);
            });
        }
        if ($unico = $this->indice(['proceso_id'], true)) {
            Schema::table(self::TABLA, function (Blueprint $table) use ($unico) {
                $table->dropUnique($unico['name']);
            });
        }
        if ($indice = $this->indice(['proyecto_id', 'tipo'], false)) {
            Schema::table(self::TABLA, function (Blueprint $table) use ($indice) {
                $table->dropIndex($indice['name']);
            });
        }
        foreach (['proceso_id', 'tipo'] as $columna) {
            if (Schema::hasColumn(self::TABLA, $columna)) {
                Schema::table(self::TABLA, function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }
    }

    private function indice(array $columnas, bool $unico): ?array
    {
        return collect(Schema::getIndexes(self::TABLA))
            ->first(fn ($i) => $i['columns'] === $columnas && $i['unique'] === $unico && ! $i['primary']);
    }

    private function foranea(): ?array
    {
        return collect(Schema::getForeignKeys(self::TABLA))->first(fn ($f) => $f['columns'] === ['proceso_id']);
    }
};
```

En `app/Models/ProcesoLicitacion.php`, reemplazar (1 de 2):

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcesoLicitacion extends Model
{
```

por:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProcesoLicitacion extends Model
{
```

En `app/Models/ProcesoLicitacion.php`, reemplazar (2 de 2):

```php
    {
        return $this->hasMany(Contrato::class, 'proceso_licitacion');
    }
}
```

por:

```php
    {
        return $this->hasMany(Contrato::class, 'proceso_licitacion');
    }

    /** Expediente de contratación del proceso: como máximo uno. */
    public function expediente(): HasOne
    {
        return $this->hasOne(ProyectoExpediente::class, 'proceso_id');
    }
}
```

En `app/Models/Proyecto.php`, reemplazar (1 de 1):

```php
        return $this->hasMany(CheckFormulacion::class, 'proyecto');
    }

    public function expedientes(): HasMany
    {
        return $this->hasMany(ProyectoExpediente::class, 'proyecto_id');
    }

    public function contratos(): HasMany
```

por:

```php
        return $this->hasMany(CheckFormulacion::class, 'proyecto');
    }

    /** Expedientes de viabilización del proyecto. Los de contratación son de cada proceso. */
    public function expedientes(): HasMany
    {
        return $this->hasMany(ProyectoExpediente::class, 'proyecto_id')->viabilizacion();
    }

    /** Expedientes de contratación de los procesos del proyecto. */
    public function expedientesContratacion(): HasMany
    {
        return $this->hasMany(ProyectoExpediente::class, 'proyecto_id')->contratacion();
    }

    public function contratos(): HasMany
```

En `app/Models/ProyectoExpediente.php`, reemplazar (1 de 3):

```php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

por:

```php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

En `app/Models/ProyectoExpediente.php`, reemplazar (2 de 3):

```php

class ProyectoExpediente extends Model
{
    public const BORRADOR = 'borrador';
    public const RADICADO = 'radicado';

    protected $table = 'proyectos_expedientes';
    protected $fillable = [
        'proyecto_id', 'fase_sgr', 'fecha_configuracion', 'estado',
        'fecha_radicado', 'usuario_radicado_id', 'usuario_creo_id',
    ];
    protected $casts = [
        'proyecto_id' => 'integer',
        'fase_sgr' => 'integer',
        'fecha_configuracion' => 'datetime',
        'fecha_radicado' => 'datetime',
```

por:

```php

class ProyectoExpediente extends Model
{
    // De viabilización, por proyecto: borrador -> radicado. De contratación, uno por proceso:
    // borrador -> cerrado.
    public const TIPO_VIABILIZACION = 'viabilizacion';
    public const TIPO_CONTRATACION = 'contratacion';
    public const TIPOS = [self::TIPO_VIABILIZACION, self::TIPO_CONTRATACION];

    public const BORRADOR = 'borrador';
    public const RADICADO = 'radicado';
    public const CERRADO = 'cerrado';

    protected $table = 'proyectos_expedientes';
    protected $fillable = [
        'proyecto_id', 'tipo', 'proceso_id', 'fase_sgr', 'fecha_configuracion', 'estado',
        'fecha_radicado', 'usuario_radicado_id', 'usuario_creo_id',
    ];
    // El mismo valor por defecto de la columna, para que un expediente recién creado lo tenga
    // sin volver a leerlo.
    protected $attributes = ['tipo' => self::TIPO_VIABILIZACION];
    protected $casts = [
        'proyecto_id' => 'integer',
        'proceso_id' => 'integer',
        'fase_sgr' => 'integer',
        'fecha_configuracion' => 'datetime',
        'fecha_radicado' => 'datetime',
```

En `app/Models/ProyectoExpediente.php`, reemplazar (3 de 3):

```php
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    /** Versiones de plantilla que usa el expediente. La etapa 5 permite más de una. */
    public function versionesPlantilla(): BelongsToMany
    {
```

por:

```php
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    /** Proceso de contratación del expediente; null en los de viabilización. */
    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoLicitacion::class, 'proceso_id');
    }

    public function scopeViabilizacion(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('tipo'), self::TIPO_VIABILIZACION);
    }

    public function scopeContratacion(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('tipo'), self::TIPO_CONTRATACION);
    }

    public function esViabilizacion(): bool
    {
        return $this->tipo === self::TIPO_VIABILIZACION;
    }

    public function esContratacion(): bool
    {
        return $this->tipo === self::TIPO_CONTRATACION;
    }

    /** Versiones de plantilla que usa el expediente. La etapa 5 permite más de una. */
    public function versionesPlantilla(): BelongsToMany
    {
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 6):

```php
 *   observación y soporte) y los que vuelven a aplicar se recuperan tal cual.
 * - Solo se radica con todos los requisitos que aplican cumplidos.
 * - Radicado, el expediente queda congelado: no se reconfigura ni se marca.
 */
final class ExpedienteService
{
```

por:

```php
 *   observación y soporte) y los que vuelven a aplicar se recuperan tal cual.
 * - Solo se radica con todos los requisitos que aplican cumplidos.
 * - Radicado, el expediente queda congelado: no se reconfigura ni se marca.
 * - Los expedientes del proyecto son los de viabilización (Proyecto::expedientes()). Uno de
 *   contratación (de un proceso) comparte el checklist, pero no se configura ni se radica.
 */
final class ExpedienteService
{
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 6):

```php

    public function confirmarConfiguracion(ProyectoExpediente $expediente, array $datos, ?User $usuario = null): ProyectoExpediente
    {
        return DB::transaction(function () use ($expediente, $datos, $usuario) {
            // Todo se decide sobre la fila bloqueada: un PUT simultáneo que quite las
            // plantillas espera a que esto termine, y luego ya lo ve confirmado. Con el
```

por:

```php

    public function confirmarConfiguracion(ProyectoExpediente $expediente, array $datos, ?User $usuario = null): ProyectoExpediente
    {
        $this->exigirViabilizacion($expediente);

        return DB::transaction(function () use ($expediente, $datos, $usuario) {
            // Todo se decide sobre la fila bloqueada: un PUT simultáneo que quite las
            // plantillas espera a que esto termine, y luego ya lo ve confirmado. Con el
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (3 de 6):

```php
     */
    public function actualizarConfiguracion(ProyectoExpediente $expediente, array $datos, ?User $usuario = null): ProyectoExpediente
    {
        return DB::transaction(function () use ($expediente, $datos, $usuario) {
            // Borrador, plantillas y "ya confirmado" se leen de la fila bloqueada.
            $this->bloquear($expediente);
```

por:

```php
     */
    public function actualizarConfiguracion(ProyectoExpediente $expediente, array $datos, ?User $usuario = null): ProyectoExpediente
    {
        $this->exigirViabilizacion($expediente);

        return DB::transaction(function () use ($expediente, $datos, $usuario) {
            // Borrador, plantillas y "ya confirmado" se leen de la fila bloqueada.
            $this->bloquear($expediente);
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (4 de 6):

```php

    public function radicar(ProyectoExpediente $expediente, ?User $usuario): ProyectoExpediente
    {
        DB::transaction(function () use ($expediente, $usuario) {
            // Con la fila bloqueada, un propio que se agrega a la vez espera o ya cuenta.
            $this->bloquear($expediente);
```

por:

```php

    public function radicar(ProyectoExpediente $expediente, ?User $usuario): ProyectoExpediente
    {
        $this->exigirViabilizacion($expediente);

        DB::transaction(function () use ($expediente, $usuario) {
            // Con la fila bloqueada, un propio que se agrega a la vez espera o ya cuenta.
            $this->bloquear($expediente);
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (5 de 6):

```php
    public function resumenPorProyecto(?int $proyectoId = null): array
    {
        $cantidades = ProyectoExpediente::query()
            ->when($proyectoId !== null, fn ($q) => $q->where('proyecto_id', $proyectoId))
            ->selectRaw('MAX(id) AS ultimo_id, COUNT(*) AS cantidad')
            ->groupBy('proyecto_id')
```

por:

```php
    public function resumenPorProyecto(?int $proyectoId = null): array
    {
        $cantidades = ProyectoExpediente::query()
            ->viabilizacion()
            ->when($proyectoId !== null, fn ($q) => $q->where('proyecto_id', $proyectoId))
            ->selectRaw('MAX(id) AS ultimo_id, COUNT(*) AS cantidad')
            ->groupBy('proyecto_id')
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (6 de 6):

```php
        }
    }

    private function exigirConfigurado(ProyectoExpediente $expediente): void
    {
        if (! $expediente->estaConfigurado()) {
```

por:

```php
        }
    }

    /** La configuración SGR y la radicación son solo de los expedientes de viabilización. */
    private function exigirViabilizacion(ProyectoExpediente $expediente): void
    {
        if ($expediente->esContratacion()) {
            throw new ReglaNegocioException('El expediente es de contratación: no tiene configuración SGR ni se radica.');
        }
    }

    private function exigirConfigurado(ProyectoExpediente $expediente): void
    {
        if (! $expediente->estaConfigurado()) {
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/ExpedientesContratacionAisladosTest.php tests/Feature/Schema/ExpedientesTipoProcesoTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1146 passed.

- [ ] **Paso 5: Commit**

```bash
git add database/migrations/2026_11_18_100019_add_tipo_y_proceso_a_expedientes.php tests/Feature/Contratacion/ExpedientesContratacionAisladosTest.php tests/Feature/Schema/ExpedientesTipoProcesoTest.php app/Models/ProcesoLicitacion.php app/Models/Proyecto.php app/Models/ProyectoExpediente.php app/Services/Viabilizacion/ExpedienteService.php
git commit -m "feat: expedient type and process columns, viability filters (migration 20)"
```

---

### Tarea 3: Plantillas compatibles y expediente de un proceso

Compatibilidad de plantillas de contratación por modalidad, fuente y sector; creación del expediente automática, manual y por comando; borrado seguro de proyectos.

**Files:**
- Create: `app/Services/Contratacion/ExpedientesContratacion.php`
- Test (create): `tests/Feature/Contratacion/ExpedienteProcesoApiTest.php`
- Modify: `app/Http/Controllers/ProcesoContratacionController.php`
- Modify: `app/Http/Controllers/ProyectoController.php`
- Modify: `app/Services/Contratacion/ProcesosContratacion.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `routes/console.php`
- Modify: `routes/contratacion.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/ExpedienteProcesoApiTest.php`:

```php
<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\User;
use App\Services\Contratacion\ExpedientesContratacion;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Tests\TestCase;

/**
 * Expediente de contratación de un proceso: plantillas compatibles, creación automática al
 * registrar o cambiar el proceso, creación manual, comando para los que faltan y su efecto al
 * eliminar el proyecto o el proceso.
 */
class ExpedienteProcesoApiTest extends TestCase
{
    use RefreshDatabase;

    private Proyecto $proyecto;
    private int $sgr;
    private int $propios;
    private int $vias;
    private int $agua;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->sgr = FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id');
        $this->propios = FuenteFinanciacion::where('clave', FuenteFinanciacion::RECURSOS_PROPIOS)->value('id');
        $this->vias = DB::table('sectores')->insertGetId(['nombre' => 'Vías', 'activo' => true]);
        $this->agua = DB::table('sectores')->insertGetId(['nombre' => 'Agua Potable', 'activo' => true]);
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal', 'sector' => $this->agua]);
    }

    private function como(Rol $rol = Rol::Gestor): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function modalidad(string $clave = 'licitacion_publica'): int
    {
        return Modalidad::where('clave', $clave)->firstOrFail()->id;
    }

    private function etapa(string $clave = 'planeacion'): int
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail()->id;
    }

    /**
     * Plantilla de contratación creada por la API. Por defecto: licitación pública, todas las
     * fuentes y todos los sectores, con dos requisitos activos y uno inactivo, publicada.
     */
    private function plantilla(string $nombre, array $cambios = [], bool $publicar = true, array $requisitos = []): PlantillaChecklist
    {
        $admin = $this->como(Rol::Administrador);
        $id = $admin->postJson('/GestPro/plantillas', [
            'nombre' => $nombre,
            'uso' => PlantillaChecklist::USO_CONTRATACION,
            'todas_fuentes' => true,
            'todos_sectores' => true,
            'modalidad_ids' => [$this->modalidad()],
            ...$cambios,
        ])->assertCreated()->json('id');
        $plantilla = PlantillaChecklist::findOrFail($id);
        $borrador = $plantilla->versiones()->where('estado', CatalogoVersion::BORRADOR)->firstOrFail();
        $requisitos = $requisitos ?: [
            ['texto' => "{$nombre}: estudios previos"],
            ['texto' => "{$nombre}: pliego de condiciones", 'etapa_id' => $this->etapa('pliego_definitivo')],
            ['texto' => "{$nombre}: retirado", 'activo' => false],
        ];
        foreach ($requisitos as $requisito) {
            $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos", [
                'etapa_id' => $this->etapa(), ...$requisito,
            ])->assertCreated();
        }
        if ($publicar) {
            $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();
        }

        return $plantilla->fresh();
    }

    /** Proceso creado directamente: sin pasar por la creación automática. */
    private function proceso(array $cambios = [], ?Proyecto $proyecto = null): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => ($proyecto ?? $this->proyecto)->id,
            'objeto' => 'Construcción del acueducto',
            'modalidad' => $this->modalidad(),
            'plataforma' => 'secop_ii',
            ...$cambios,
        ]);
    }

    /** Proceso creado por la API, como desde la pantalla. */
    private function registrar(array $cambios = [])
    {
        return $this->como()->postJson("/GestPro/proyectos/{$this->proyecto->id}/procesos", [
            'objeto' => 'Construcción del acueducto',
            'modalidad_id' => $this->modalidad(),
            'plataforma' => 'secop_ii',
            ...$cambios,
        ]);
    }

    /** Edita como desde el formulario: los obligatorios siempre van, con lo que el proceso tiene. */
    private function editar(ProcesoLicitacion $proceso, array $datos)
    {
        $actual = $proceso->fresh();

        return $this->como()->putJson("/GestPro/procesos/{$proceso->id}", [
            'objeto' => $actual->objeto,
            'modalidad_id' => $actual->modalidad,
            'plataforma' => $actual->plataforma,
            'actualizado' => $actual->updated_at?->toIso8601String(),
            ...$datos,
        ]);
    }

    /** @return list<int> */
    private function compatibles(ProcesoLicitacion $proceso): array
    {
        return array_column($this->como(Rol::Consulta)->getJson("/GestPro/procesos/{$proceso->id}/plantillas-compatibles")
            ->assertOk()->json('plantillas'), 'id');
    }

    private function crearManual(ProcesoLicitacion $proceso, array $plantillas, Rol $rol = Rol::Gestor)
    {
        return $this->como($rol)->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => $plantillas]);
    }

    // ------------------------------------------------------- compatibilidad

    public function test_compatibles_por_modalidad_o_todas(): void
    {
        $licitacion = $this->plantilla('Licitación');
        $this->plantilla('Mínima cuantía', ['modalidad_ids' => [$this->modalidad('minima_cuantia')]]);
        $todas = $this->plantilla('Todas', ['modalidad_ids' => [], 'todas_modalidades' => true]);

        $this->assertSame([$licitacion->id, $todas->id], $this->compatibles($this->proceso()));
    }

    public function test_compatibles_por_fuente_o_todas_y_sin_fuente_solo_todas(): void
    {
        $regalias = $this->plantilla('Regalías', ['todas_fuentes' => false, 'fuente_ids' => [$this->sgr]]);
        $this->plantilla('Propios', ['todas_fuentes' => false, 'fuente_ids' => [$this->propios]]);
        $todas = $this->plantilla('Todas las fuentes');

        $this->assertSame([$regalias->id, $todas->id], $this->compatibles($this->proceso(['fuente_id' => $this->sgr])));
        $this->assertSame([$todas->id], $this->compatibles($this->proceso()));
    }

    public function test_compatibles_por_sector_o_todos_y_sin_sector_solo_todos(): void
    {
        $agua = $this->plantilla('Agua', ['todos_sectores' => false, 'sector_ids' => [$this->agua]]);
        $this->plantilla('Vías', ['todos_sectores' => false, 'sector_ids' => [$this->vias]]);
        $todos = $this->plantilla('Todos los sectores');
        $sinSector = Proyecto::create(['nombre' => 'Sin sector']);

        $this->assertSame([$agua->id, $todos->id], $this->compatibles($this->proceso()));
        $this->assertSame([$todos->id], $this->compatibles($this->proceso([], $sinSector)));
    }

    public function test_solo_de_contratacion_activas_con_version_publicada_y_motor_simple(): void
    {
        $valida = $this->plantilla('Válida');
        $this->plantilla('Sin publicar', [], false);
        $inactiva = $this->plantilla('Inactiva');
        $inactiva->update(['activo' => false]);
        $sgr = $this->plantilla('Motor SGR');
        DB::table('plantillas_checklist')->where('id', $sgr->id)->update(['motor' => PlantillaChecklist::MOTOR_SGR]);
        // Una plantilla de viabilización para todas las fuentes y sectores no se ofrece.
        $viabilizacion = PlantillaChecklist::sgr();
        $viabilizacion->update(['todas_fuentes' => true, 'todos_sectores' => true]);
        // Con una versión nueva en borrador sigue ofreciéndose con la publicada.
        $this->como(Rol::Administrador)->postJson("/GestPro/plantillas/{$valida->id}/versiones")->assertSuccessful();

        $r = $this->como(Rol::Consulta)->getJson("/GestPro/procesos/{$this->proceso()->id}/plantillas-compatibles")->assertOk();

        $this->assertSame([$valida->id], array_column($r->json('plantillas'), 'id'));
        $this->assertSame(1, $r->json('plantillas.0.version.numero'));
    }

    public function test_la_respuesta_describe_el_proceso_y_cada_plantilla(): void
    {
        $plantilla = $this->plantilla('Licitación pública', ['todas_fuentes' => false, 'fuente_ids' => [$this->sgr]]);
        $proceso = $this->proceso(['fuente_id' => $this->sgr]);
        $version = $plantilla->versionVigente();

        $this->como(Rol::Consulta)->getJson("/GestPro/procesos/{$proceso->id}/plantillas-compatibles")
            ->assertOk()
            ->assertExactJson([
                'proceso' => [
                    'id' => $proceso->id,
                    'modalidad' => ['id' => $this->modalidad(), 'nombre' => 'Licitación pública'],
                    'fuente' => ['id' => $this->sgr, 'nombre' => FuenteFinanciacion::find($this->sgr)->nombre],
                    'sector' => ['id' => $this->agua, 'nombre' => 'Agua Potable'],
                ],
                'expediente_id' => null,
                'plantillas' => [[
                    'id' => $plantilla->id,
                    'nombre' => 'Licitación pública',
                    'descripcion' => null,
                    'todas_modalidades' => false,
                    'todas_fuentes' => false,
                    'todos_sectores' => true,
                    'requisitos' => 2,
                    'version' => ['id' => $version->id, 'numero' => 1],
                ]],
            ]);
    }

    public function test_compatibles_de_un_proceso_inexistente_es_404(): void
    {
        $this->como(Rol::Consulta)->getJson('/GestPro/procesos/99999/plantillas-compatibles')->assertNotFound();
    }

    // -------------------------------------------------- creación automática

    public function test_un_proceso_nuevo_con_una_plantilla_compatible_nace_con_su_expediente(): void
    {
        $plantilla = $this->plantilla('Licitación');

        $id = $this->registrar()->assertCreated()->json('id');

        $expediente = ProcesoLicitacion::findOrFail($id)->expediente;
        $this->assertNotNull($expediente);
        $this->assertSame(ProyectoExpediente::TIPO_CONTRATACION, $expediente->tipo);
        $this->assertSame($this->proyecto->id, $expediente->proyecto_id);
        $this->assertSame(ProyectoExpediente::BORRADOR, $expediente->estado);
        $this->assertNotNull($expediente->fecha_configuracion);
        $this->assertNotNull($expediente->usuario_creo_id);
        $this->assertSame([$plantilla->versionVigente()->id], $expediente->versionesPlantilla()->pluck('catalogo_versiones.id')->all());
        // Motor simple: todos los requisitos activos de la versión, en su orden.
        $this->assertSame(
            ['Licitación: estudios previos', 'Licitación: pliego de condiciones'],
            $expediente->requisitos()->where('aplica', true)->orderBy('orden')->with('requisito')->get()->pluck('requisito.texto')->all()
        );
        // La viabilización del proyecto no cambia: sigue sin expedientes.
        $this->assertSame(0, $this->proyecto->expedientes()->count());
    }

    public function test_sin_plantillas_compatibles_o_con_varias_no_se_crea(): void
    {
        $sin = $this->registrar()->assertCreated()->json('id');
        $this->plantilla('Una');
        $this->plantilla('Otra');
        $varias = $this->registrar()->assertCreated()->json('id');

        $this->assertNull(ProcesoLicitacion::find($sin)->expediente);
        $this->assertNull(ProcesoLicitacion::find($varias)->expediente);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_al_cambiar_la_modalidad_o_la_fuente_sin_expediente_se_crea(): void
    {
        $this->plantilla('Mínima', ['modalidad_ids' => [$this->modalidad('minima_cuantia')]]);
        $this->plantilla('Regalías', ['todas_fuentes' => false, 'fuente_ids' => [$this->sgr], 'modalidad_ids' => [$this->modalidad('seleccion_abreviada')]]);
        $porModalidad = $this->proceso();
        $porFuente = $this->proceso(['modalidad' => $this->modalidad('seleccion_abreviada')]);

        $this->editar($porModalidad, ['objeto' => 'Otro objeto'])->assertOk();
        $this->assertNull($porModalidad->fresh()->expediente);

        $this->editar($porModalidad, ['modalidad_id' => $this->modalidad('minima_cuantia')])->assertOk();
        $this->editar($porFuente, ['fuente_id' => $this->sgr])->assertOk();

        $this->assertNotNull($porModalidad->fresh()->expediente);
        $this->assertNotNull($porFuente->fresh()->expediente);
    }

    public function test_con_expediente_cambiar_la_modalidad_no_lo_toca(): void
    {
        $this->plantilla('Licitación');
        $this->plantilla('Mínima', ['modalidad_ids' => [$this->modalidad('minima_cuantia')]]);
        $proceso = ProcesoLicitacion::findOrFail($this->registrar()->assertCreated()->json('id'));
        $expediente = $proceso->expediente;
        $versiones = $expediente->versionesPlantilla()->pluck('catalogo_versiones.id')->all();

        $this->editar($proceso, ['modalidad_id' => $this->modalidad('minima_cuantia')])->assertOk();

        $this->assertSame([$expediente->id], ProyectoExpediente::pluck('id')->all());
        $this->assertSame($versiones, $expediente->versionesPlantilla()->pluck('catalogo_versiones.id')->all());
    }

    public function test_si_falla_la_creacion_el_proceso_se_guarda_igual(): void
    {
        $this->plantilla('Licitación');
        Exceptions::fake();
        DB::listen(function ($q) {
            if (str_contains($q->sql, '"plantillas_checklist"')) {
                throw new \RuntimeException('Se cayó la conexión.');
            }
        });

        $this->registrar()->assertCreated();

        $this->assertDatabaseCount('procesos_licitacion', 1);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
        Exceptions::assertReported(\RuntimeException::class);
    }

    // ------------------------------------------------------ creación manual

    public function test_crear_a_mano_con_varias_plantillas_compatibles(): void
    {
        $una = $this->plantilla('Una', [], true, [['texto' => 'Estudios previos', 'codigo_comun' => 'EP'], ['texto' => 'Solo de una']]);
        $otra = $this->plantilla('Otra', [], true, [['texto' => 'Estudios previos (otra)', 'codigo_comun' => 'EP'], ['texto' => 'Solo de otra']]);
        $proceso = $this->proceso();

        $r = $this->crearManual($proceso, [$otra->id, $una->id])->assertCreated()
            ->assertJsonPath('estado', ProyectoExpediente::BORRADOR)
            ->assertJsonPath('configurado', true)
            ->assertJsonPath('proyecto.id', $this->proyecto->id)
            ->assertJsonPath('plantillas.0.plantilla_id', $otra->id)
            ->assertJsonPath('plantillas.0.es_principal', true)
            ->assertJsonPath('plantillas.1.plantilla_id', $una->id)
            ->assertJsonPath('plantillas.1.es_principal', false)
            ->assertJsonPath('progreso.total', 3);

        $expediente = ProyectoExpediente::findOrFail($r->json('id'));
        $this->assertSame($proceso->id, $expediente->proceso_id);
        $this->assertTrue($expediente->esContratacion());
        // El código común junta los dos "Estudios previos" en una fila con dos orígenes.
        $this->assertCount(3, $r->json('requisitos'));
        $this->assertSame('Estudios previos (otra)', $r->json('requisitos.0.requisito.texto'));
    }

    public function test_crear_a_mano_registra_el_historial(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $proceso = $this->proceso();

        $id = $this->crearManual($proceso, [$plantilla->id])->assertCreated()->json('id');

        $historial = DB::table('checklist_historial')->where('entidad_tipo', 'expediente')->where('entidad_id', $id)->sole();
        $this->assertSame('expediente_creado', $historial->evento);
        $this->assertSame($id, (int) $historial->expediente_id);
        $this->assertSame([
            'proceso_id' => $proceso->id,
            'plantillas' => [['plantilla_id' => $plantilla->id, 'catalogo_version_id' => $plantilla->versionVigente()->id, 'es_principal' => true]],
        ], json_decode($historial->despues, true));
    }

    public function test_crear_a_mano_exige_plantillas_compatibles(): void
    {
        $compatible = $this->plantilla('Licitación');
        $minima = $this->plantilla('Mínima', ['modalidad_ids' => [$this->modalidad('minima_cuantia')]]);
        $sinPublicar = $this->plantilla('Sin publicar', [], false);
        $proceso = $this->proceso();

        $this->crearManual($proceso, [])->assertStatus(422)->assertJsonValidationErrors('plantillas');
        $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", [])->assertStatus(422)->assertJsonValidationErrors('plantillas');
        $this->crearManual($proceso, ['x'])->assertStatus(422)->assertJsonValidationErrors('plantillas.0');
        $this->crearManual($proceso, [$compatible->id, $minima->id, $sinPublicar->id, PlantillaChecklist::sgr()->id, 99999])
            ->assertStatus(422)
            ->assertExactJson([
                'mensaje' => 'El expediente no se puede crear.',
                'errores' => [
                    'La plantilla "Mínima" no es compatible con la modalidad, la fuente o el sector del proceso.',
                    'La plantilla "Sin publicar" no tiene una versión publicada.',
                    'La plantilla "' . PlantillaChecklist::sgr()->nombre . '" es de viabilización y no se usa en un expediente de contratación.',
                    'La plantilla con id 99999 no existe.',
                ],
            ]);

        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_un_proceso_tiene_un_solo_expediente(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $proceso = $this->proceso();
        $id = $this->crearManual($proceso, [$plantilla->id])->assertCreated()->json('id');

        $this->crearManual($proceso, [$plantilla->id])->assertStatus(422)
            ->assertJsonPath('mensaje', "El proceso ya tiene el expediente #{$id}.");
        $this->assertDatabaseCount('proyectos_expedientes', 1);
        $this->assertSame($id, $this->como(Rol::Consulta)->getJson("/GestPro/procesos/{$proceso->id}/plantillas-compatibles")->json('expediente_id'));
    }

    public function test_crear_a_mano_exige_permiso_de_edicion(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $proceso = $this->proceso();

        $this->crearManual($proceso, [$plantilla->id], Rol::Consulta)->assertForbidden();
        $this->assertDatabaseCount('proyectos_expedientes', 0);
        $this->crearManual($proceso, [$plantilla->id], Rol::Administrador)->assertCreated();
    }

    public function test_el_expediente_creado_se_lee_y_se_marca_como_los_demas(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $id = $this->crearManual($this->proceso(), [$plantilla->id])->assertCreated()->json('id');
        $fila = $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$id}")->assertOk()->json('requisitos.0');

        $this->como()->patchJson("/GestPro/expedientes/{$id}/requisitos/{$fila['id']}", ['cumplido' => true])->assertOk();

        $this->assertSame(50, (int) app(ExpedienteService::class)->progresoDe(ProyectoExpediente::find($id))['porcentaje']);
    }

    // --------------------------------------------------------------- comando

    public function test_el_comando_crea_los_que_tienen_una_sola_plantilla_y_se_puede_repetir(): void
    {
        $this->plantilla('Licitación');
        $this->plantilla('Mínima A', ['modalidad_ids' => [$this->modalidad('minima_cuantia')]]);
        $this->plantilla('Mínima B', ['modalidad_ids' => [$this->modalidad('minima_cuantia')]]);
        $falta = $this->proceso(['codigo_proceso' => 'LP-001']);
        $varias = $this->proceso(['modalidad' => $this->modalidad('minima_cuantia')]);
        $ninguna = $this->proceso(['modalidad' => $this->modalidad('seleccion_abreviada')]);
        $tiene = $this->proceso();
        $previo = $this->crearManual($tiene, [PlantillaChecklist::where('nombre', 'Licitación')->value('id')])->assertCreated()->json('id');

        $this->artisan('expedientes:crear-faltantes-contratacion')
            ->expectsOutput('Expedientes creados: 1 de 1.')
            ->assertSuccessful();

        $this->assertNotNull($falta->fresh()->expediente);
        $this->assertNull($varias->fresh()->expediente);
        $this->assertNull($ninguna->fresh()->expediente);
        $this->assertSame($previo, $tiene->fresh()->expediente->id);
        $this->assertDatabaseCount('proyectos_expedientes', 2);

        $this->artisan('expedientes:crear-faltantes-contratacion')->expectsOutput('Expedientes creados: 0 de 0.')->assertSuccessful();
        $this->assertDatabaseCount('proyectos_expedientes', 2);
    }

    public function test_el_comando_en_simulacion_lista_y_no_crea_nada(): void
    {
        $this->plantilla('Licitación');
        $conCodigo = $this->proceso(['codigo_proceso' => 'LP-001']);
        $sinCodigo = $this->proceso(['objeto' => 'Interventoría']);

        $this->artisan('expedientes:crear-faltantes-contratacion', ['--simular' => true])
            ->expectsOutput("#{$conCodigo->id} LP-001 (Acueducto veredal): plantilla Licitación")
            ->expectsOutput("#{$sinCodigo->id} Interventoría (Acueducto veredal): plantilla Licitación")
            ->expectsOutput('Se crearían 2 expedientes.')
            ->assertSuccessful();

        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_el_comando_describe_su_regla(): void
    {
        $this->artisan('list')
            ->expectsOutputToContain('Crea el expediente de contratación a los procesos sin expediente con una sola plantilla compatible')
            ->assertSuccessful();
    }

    // ------------------------------------------------------ carreras (MariaDB)

    /**
     * Eliminar el proyecto bloquea sus procesos antes de borrar sus expedientes: así una
     * creación simultánea (que bloquea el proceso) espera y después no encuentra el proceso.
     * La carrera solo se ve en MariaDB; SQLite no admite FOR UPDATE, así que se comprueban las
     * sentencias con la gramática de MySQL sin ejecutarlas.
     */
    public function test_eliminar_el_proyecto_bloquea_sus_procesos_antes_de_borrar(): void
    {
        $conexion = DB::connection();
        $gramatica = $conexion->getQueryGrammar();
        $conexion->setQueryGrammar(new MySqlGrammar($conexion));
        try {
            $sentencias = array_column($conexion->pretend(
                fn () => app(ExpedientesContratacion::class)->eliminarDeProyecto($this->proyecto->id)
            ), 'query');
        } finally {
            $conexion->setQueryGrammar($gramatica);
        }

        $this->assertCount(2, $sentencias);
        $this->assertMatchesRegularExpression('/^select .* from `procesos_licitacion` where `proyecto` = \d+ for update$/', $sentencias[0]);
        $this->assertStringStartsWith('delete from `proyectos_expedientes`', $sentencias[1]);
    }

    /** El proceso desaparece entre leerlo y bloquearlo (lo borró la eliminación del proyecto). */
    private function borrarAlLeer(): void
    {
        $hecho = false;
        ProcesoLicitacion::retrieved(function (ProcesoLicitacion $p) use (&$hecho) {
            if (! $hecho) {
                $hecho = true;
                DB::table('procesos_licitacion')->where('id', $p->id)->delete();
            }
        });
    }

    public function test_crear_a_mano_un_proceso_que_desaparecio_responde_404(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $proceso = $this->proceso();
        $this->borrarAlLeer();

        $this->crearManual($proceso, [$plantilla->id])->assertNotFound();

        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_la_creacion_automatica_de_un_proceso_que_desaparecio_no_falla(): void
    {
        $this->plantilla('Licitación');
        $proceso = $this->proceso();
        DB::table('procesos_licitacion')->where('id', $proceso->id)->delete();
        Exceptions::fake();

        $this->assertNull(app(ExpedientesContratacion::class)->crearAutomatico($proceso, null));

        $this->assertDatabaseCount('proyectos_expedientes', 0);
        Exceptions::assertNothingReported();
    }

    public function test_si_a_mano_se_creo_antes_la_automatica_no_lo_reporta(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $proceso = $this->proceso();
        $servicio = app(ExpedientesContratacion::class);
        // La creación a mano gana la carrera justo después de que la automática comprobó que
        // el proceso no tenía expediente.
        $hecho = false;
        PlantillaChecklist::retrieved(function () use (&$hecho, $servicio, $proceso, $plantilla) {
            if (! $hecho) {
                $hecho = true;
                $servicio->crear($proceso, [$plantilla->id], null);
            }
        });
        Exceptions::fake();

        $this->assertNull($servicio->crearAutomatico($proceso, null));

        $this->assertDatabaseCount('proyectos_expedientes', 1);
        Exceptions::assertNothingReported();
    }

    public function test_la_compatibilidad_se_decide_con_el_proceso_bloqueado(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $proceso = $this->proceso();
        // Otro usuario cambia la modalidad después de que se leyó el proceso de la ruta.
        $hecho = false;
        ProcesoLicitacion::retrieved(function (ProcesoLicitacion $p) use (&$hecho) {
            if (! $hecho) {
                $hecho = true;
                DB::table('procesos_licitacion')->where('id', $p->id)->update(['modalidad' => $this->modalidad('minima_cuantia')]);
            }
        });

        $this->crearManual($proceso, [$plantilla->id])->assertStatus(422)->assertExactJson([
            'mensaje' => 'El expediente no se puede crear.',
            'errores' => ['La plantilla "Licitación" no es compatible con la modalidad, la fuente o el sector del proceso.'],
        ]);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    // ------------------------------------------- cambiar el sector del proyecto

    /** Guarda el proyecto como desde su formulario, con el sector dado y sin tocar sus fuentes. */
    private function guardarProyecto(?int $sector, ?Proyecto $proyecto = null)
    {
        $proyecto = ($proyecto ?? $this->proyecto)->fresh();

        return $this->como()->postJson('/GestPro/guardarProyecto', [
            'accion' => 'Editar',
            'id' => $proyecto->id,
            'municipio' => $proyecto->municipio,
            'nombre' => $proyecto->nombre,
            'descripcion' => $proyecto->descripcion,
            'fechaInicio' => $proyecto->fecha_inicio,
            'estado' => $proyecto->estado,
            'fase' => $proyecto->fase,
            'presupuesto' => $proyecto->presupuesto,
            'entidadPresenta' => $proyecto->entidad_presenta,
            'entidadFinancia' => $proyecto->entidad_financia,
            'sector' => $sector,
        ]);
    }

    public function test_al_cambiar_el_sector_del_proyecto_se_crea_el_expediente_de_sus_procesos(): void
    {
        $vias = $this->plantilla('Vías', ['todos_sectores' => false, 'sector_ids' => [$this->vias]]);
        $sin = $this->proceso(['codigo_proceso' => 'LP-001']);
        $otro = $this->proceso(['codigo_proceso' => 'LP-002']);
        $tiene = $this->proceso(['codigo_proceso' => 'LP-003']);
        $previo = app(ExpedientesContratacion::class)->crear($tiene, [$this->plantilla('Agua', ['todos_sectores' => false, 'sector_ids' => [$this->agua]])->id], null);

        $this->guardarProyecto($this->vias)->assertOk();

        foreach ([$sin, $otro] as $proceso) {
            $expediente = $proceso->fresh()->expediente;
            $this->assertNotNull($expediente);
            $this->assertSame([$vias->versionVigente()->id], $expediente->versionesPlantilla()->pluck('catalogo_versiones.id')->all());
        }
        $this->assertSame($previo->id, $tiene->fresh()->expediente->id);
        $this->assertDatabaseCount('proyectos_expedientes', 3);
        $this->assertSame(0, $this->proyecto->expedientes()->count());
    }

    public function test_guardar_el_proyecto_sin_cambiar_el_sector_no_intenta_crearlo(): void
    {
        $this->plantilla('Agua', ['todos_sectores' => false, 'sector_ids' => [$this->agua]]);
        $proceso = $this->proceso();

        $this->guardarProyecto($this->agua)->assertOk();

        $this->assertNull($proceso->fresh()->expediente);
    }

    public function test_al_cambiar_el_sector_con_cero_o_varias_compatibles_no_se_crea(): void
    {
        $this->plantilla('Vías A', ['todos_sectores' => false, 'sector_ids' => [$this->vias]]);
        $this->plantilla('Vías B', ['todos_sectores' => false, 'sector_ids' => [$this->vias]]);
        $this->proceso();

        $this->guardarProyecto($this->vias)->assertOk();
        $this->assertDatabaseCount('proyectos_expedientes', 0);

        $this->guardarProyecto(DB::table('sectores')->insertGetId(['nombre' => 'Salud', 'activo' => true]))->assertOk();
        $this->guardarProyecto(null)->assertOk();
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_si_falla_la_creacion_al_cambiar_el_sector_el_proyecto_se_guarda_igual(): void
    {
        $this->plantilla('Vías', ['todos_sectores' => false, 'sector_ids' => [$this->vias]]);
        $this->proceso();
        Exceptions::fake();
        DB::listen(function ($q) {
            if (str_starts_with($q->sql, 'insert into "proyectos_expedientes"')) {
                throw new \RuntimeException('Se cayó la conexión.');
            }
        });

        $this->guardarProyecto($this->vias)->assertOk()->assertJsonPath('success', 'Proyecto guardado correctamente');

        $this->assertSame($this->vias, (int) $this->proyecto->fresh()->sector);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
        Exceptions::assertReported(\RuntimeException::class);
    }

    // ------------------------------------------------- eliminar proyecto o proceso

    /** Debe comprobarse también en MariaDB: allí el borrado sin este paso falla. */
    public function test_eliminar_un_proyecto_con_expedientes_de_contratacion_y_viabilizacion(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $proceso = $this->proceso();
        $contratacion = $this->crearManual($proceso, [$plantilla->id])->assertCreated()->json('id');
        $viabilizacion = app(ExpedienteService::class)->crear($this->proyecto, ['plantilla_ids' => []], null);
        $otro = Proyecto::create(['nombre' => 'Otro']);
        $ajeno = $this->crearManual($this->proceso([], $otro), [$plantilla->id])->assertCreated()->json('id');

        $borrados = [];
        DB::listen(function ($q) use (&$borrados) {
            if (preg_match('/^delete from "(\w+)"/', $q->sql, $m)) {
                $borrados[] = $m[1];
            }
        });

        $this->como()->postJson('/GestPro/eliminarProyecto', ['id' => $this->proyecto->id])->assertOk();

        // SQLite resuelve las cascadas sin chocar con el restrict de proceso_id; MariaDB no
        // (ERROR 1451): los expedientes de contratación se borran antes que el proyecto.
        $this->assertSame(['proyectos_expedientes', 'proyectos'], $borrados);
        $this->assertDatabaseMissing('proyectos', ['id' => $this->proyecto->id]);
        $this->assertDatabaseMissing('procesos_licitacion', ['id' => $proceso->id]);
        $this->assertDatabaseMissing('proyectos_expedientes', ['id' => $contratacion]);
        $this->assertDatabaseMissing('proyectos_expedientes', ['id' => $viabilizacion->id]);
        $this->assertDatabaseMissing('proyectos_requisitos', ['expediente_id' => $contratacion]);
        $this->assertDatabaseHas('proyectos_expedientes', ['id' => $ajeno]);
    }

    public function test_un_proceso_con_expediente_no_se_elimina(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $proceso = $this->proceso();
        $this->crearManual($proceso, [$plantilla->id])->assertCreated();

        $this->como()->deleteJson("/GestPro/procesos/{$proceso->id}")->assertStatus(422)
            ->assertJsonPath('mensaje', 'El proceso tiene un expediente de contratación: no se puede eliminar.');
        $this->assertDatabaseHas('procesos_licitacion', ['id' => $proceso->id]);
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/ExpedienteProcesoApiTest.php`
Expected: FALLAN 28 y pasan 3: no existen la compatibilidad de plantillas de contratación, las rutas `/procesos/{p}/plantillas-compatibles` y `/procesos/{p}/expediente`, la creación automática ni el comando.

- [ ] **Paso 3: Implementar**

Crear `app/Services/Contratacion/ExpedientesContratacion.php`:

```php
<?php

namespace App\Services\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\User;
use Closure;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Expediente de contratación de un proceso: qué plantillas se le ofrecen y cómo nace.
 *
 * Reglas:
 * - Se ofrecen las plantillas de contratación activas, con motor simple y versión publicada
 *   vigente, que cubren la modalidad del proceso (o todas), su fuente (o todas; sin fuente,
 *   solo las de todas las fuentes) y el sector del proyecto (o todos; sin sector, solo las de
 *   todos los sectores). Las de viabilización nunca (PlantillasExpediente es la de ellas).
 * - Un proceso tiene como máximo un expediente. Nace con una o varias plantillas compatibles,
 *   cada una con su versión vigente; la primera elegida es la principal, la que ordena el
 *   checklist primero. El checklist lo arma el motor del expediente (ExpedienteService).
 * - Al registrar un proceso, o al cambiarle la modalidad o la fuente mientras no tenga
 *   expediente, el expediente nace solo si hay exactamente una plantilla compatible; con cero
 *   o varias, se crea a mano eligiéndolas. Lo mismo para los procesos sin expediente de un
 *   proyecto al que le cambia el sector. Nunca interrumpe a quien guarda el proceso o el proyecto.
 * - Cambiar la modalidad o la fuente de un proceso con expediente no lo toca.
 */
final class ExpedientesContratacion
{
    public function __construct(private readonly ExpedienteService $expedientes)
    {
    }

    /**
     * Lo que se ofrece para crear el expediente del proceso.
     *
     * @return array{proceso: array, expediente_id: ?int, plantillas: list<array>}
     */
    public function disponibles(ProcesoLicitacion $proceso): array
    {
        $proceso->loadMissing(['modalidadRel:id,nombre', 'fuente:id,nombre', 'proyectoRel:id,sector', 'proyectoRel.sectorRel:id,nombre']);
        $sector = $proceso->proyectoRel?->sectorRel;
        $opcion = fn ($fila) => $fila ? ['id' => $fila->id, 'nombre' => $fila->nombre] : null;

        $plantillas = $this->compatibles($proceso)
            ->with(['versiones' => fn ($q) => $this->publicada($q)->withCount(['requisitos' => fn ($r) => $r->where('activo', true)])])
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();

        return [
            'proceso' => [
                'id' => $proceso->id,
                'modalidad' => $opcion($proceso->modalidadRel),
                'fuente' => $opcion($proceso->fuente),
                'sector' => $opcion($sector),
            ],
            'expediente_id' => $proceso->expediente()->value('id'),
            'plantillas' => $plantillas->map(function (PlantillaChecklist $p) {
                $version = $p->versiones->first();

                return [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'descripcion' => $p->descripcion,
                    'todas_modalidades' => $p->todas_modalidades,
                    'todas_fuentes' => $p->todas_fuentes,
                    'todos_sectores' => $p->todos_sectores,
                    'requisitos' => (int) $version->requisitos_count,
                    'version' => ['id' => $version->id, 'numero' => $version->numero],
                ];
            })->values()->all(),
        ];
    }

    /** @return list<int> ids de las plantillas que hoy se ofrecen al proceso */
    public function idsCompatibles(ProcesoLicitacion $proceso): array
    {
        return $this->compatibles($proceso)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Crea el expediente del proceso con las plantillas elegidas, en el orden pedido.
     *
     * @param list<int> $plantillaIds
     */
    public function crear(ProcesoLicitacion $proceso, array $plantillaIds, ?User $usuario): ProyectoExpediente
    {
        // Se validan con el proceso ya bloqueado: un cambio simultáneo de su modalidad o su
        // fuente no deja un expediente con una plantilla que ya no le corresponde.
        return $this->expedientes->crearDeProceso(
            $proceso,
            fn (ProcesoLicitacion $bloqueado) => $this->validar($bloqueado, $plantillaIds),
            $usuario
        );
    }

    /**
     * Expediente que nace al registrar el proceso o cambiarle la modalidad o la fuente, si no
     * tiene y hay exactamente una plantilla compatible. Nunca interrumpe a quien guarda el
     * proceso: un fallo se registra y el expediente se puede crear a mano.
     */
    public function crearAutomatico(ProcesoLicitacion $proceso, ?User $usuario): ?ProyectoExpediente
    {
        // Dentro de intentar: un fallo al consultarlo tampoco interrumpe el guardado.
        return $this->intentar(function () use ($proceso, $usuario) {
            $unica = $this->plantillaAutomatica($proceso);

            return $unica ? $this->crear($proceso, [$unica->id], $usuario) : null;
        });
    }

    /**
     * Expedientes que nacen al cambiar el sector del proyecto: el de cada proceso suyo sin
     * expediente que ahora tiene exactamente una plantilla compatible. Como crearAutomatico,
     * nunca interrumpe a quien guarda el proyecto.
     *
     * @return int cuántos se crearon
     */
    public function crearAutomaticosDeProyecto(int $proyectoId, ?User $usuario): int
    {
        try {
            $faltantes = $this->faltantes($proyectoId);
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }

        return $faltantes->filter(fn (array $fila) => $this->intentar(
            fn () => $this->crear($fila['proceso'], [$fila['plantilla']->id], $usuario)
        ) !== null)->count();
    }

    /**
     * Una creación automática: un fallo se registra y devuelve null. Una regla incumplida no es
     * un fallo (otra petición creó el expediente primero, o la plantilla dejó de corresponder
     * al proceso): devuelve null sin registrarse.
     */
    private function intentar(Closure $crear): ?ProyectoExpediente
    {
        try {
            return $crear();
        } catch (ReglaNegocioException) {
            return null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** La única plantilla compatible de un proceso sin expediente; null si no hay o hay varias. */
    public function plantillaAutomatica(ProcesoLicitacion $proceso): ?PlantillaChecklist
    {
        if ($proceso->expediente()->exists()) {
            return null;
        }
        $compatibles = $this->compatibles($proceso)->orderBy('id')->limit(2)->get();

        return $compatibles->count() === 1 ? $compatibles->first() : null;
    }

    /**
     * Procesos sin expediente con exactamente una plantilla compatible, con ella; todos, o solo
     * los del proyecto dado.
     *
     * @return Collection<int, array{proceso: ProcesoLicitacion, plantilla: PlantillaChecklist}>
     */
    public function faltantes(?int $proyectoId = null): Collection
    {
        return ProcesoLicitacion::query()
            ->when($proyectoId !== null, fn ($q) => $q->where('proyecto', $proyectoId))
            ->whereDoesntHave('expediente')
            ->with('proyectoRel:id,nombre,sector')
            ->orderBy('id')
            ->get()
            ->map(fn (ProcesoLicitacion $proceso) => ['proceso' => $proceso, 'plantilla' => $this->plantillaAutomatica($proceso)])
            ->filter(fn (array $fila) => $fila['plantilla'] !== null)
            ->values();
    }

    /**
     * Elimina los expedientes de contratación del proyecto, antes de eliminarlo: el proyecto
     * borra en cascada sus procesos y sus expedientes, pero un proceso con expediente no se
     * borra (restrict), y en MariaDB el orden de las cascadas no está garantizado. Como con los
     * de viabilización al eliminar el proyecto, sus soportes quedan en el disco y el historial
     * se conserva. Se llama dentro de la transacción que elimina el proyecto.
     */
    public function eliminarDeProyecto(int $proyectoId): int
    {
        // Antes de borrar se bloquean los procesos del proyecto: un expediente que nace a la vez
        // (crearDeProceso bloquea su proceso) espera a que termine la eliminación y después no
        // encuentra el proceso (a mano, 404; la creación automática lo absorbe). La carrera solo
        // se ve en MariaDB: SQLite, el de las pruebas, no tiene FOR UPDATE.
        ProcesoLicitacion::query()->where('proyecto', $proyectoId)->lockForUpdate()->get(['id']);

        return ProyectoExpediente::query()->contratacion()->where('proyecto_id', $proyectoId)->delete();
    }

    /**
     * Comprueba las plantillas pedidas y decide la versión de cada una, con la forma que guarda
     * PlantillasExpediente::guardar().
     *
     * @param list<int> $plantillaIds
     * @return array{versiones: array<int, int>, principal_id: int}
     */
    private function validar(ProcesoLicitacion $proceso, array $plantillaIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $plantillaIds)));
        if ($ids === []) {
            throw new ReglaNegocioException('El expediente no se puede crear.', ['Elige al menos una plantilla para el expediente.']);
        }
        if (count($ids) > PlantillasExpediente::MAX_PLANTILLAS) {
            throw new ReglaNegocioException('El expediente no se puede crear.', [
                'Un expediente admite como máximo ' . PlantillasExpediente::MAX_PLANTILLAS . ' plantillas.',
            ]);
        }
        $compatibles = array_flip($this->idsCompatibles($proceso));
        $encontradas = PlantillaChecklist::query()->whereIn('id', $ids)
            ->with(['versiones' => fn ($q) => $this->publicada($q)])
            ->get()->keyBy('id');

        $errores = [];
        $versiones = [];
        foreach ($ids as $id) {
            $plantilla = $encontradas->get($id);
            $version = $plantilla?->versiones->first();
            $error = match (true) {
                ! $plantilla => "La plantilla con id {$id} no existe.",
                ! $plantilla->esContratacion() => "La plantilla \"{$plantilla->nombre}\" es de viabilización y no se usa en un expediente de contratación.",
                ! $plantilla->activo => "La plantilla \"{$plantilla->nombre}\" está inactiva.",
                ! $version => "La plantilla \"{$plantilla->nombre}\" no tiene una versión publicada.",
                ! isset($compatibles[$id]) => "La plantilla \"{$plantilla->nombre}\" no es compatible con la modalidad, la fuente o el sector del proceso.",
                default => null,
            };
            if ($error !== null) {
                $errores[] = $error;
            } else {
                $versiones[$id] = $version->id;
            }
        }
        if ($errores) {
            throw new ReglaNegocioException('El expediente no se puede crear.', $errores);
        }

        return ['versiones' => $versiones, 'principal_id' => $ids[0]];
    }

    private function compatibles(ProcesoLicitacion $proceso): Builder
    {
        $sector = Proyecto::query()->whereKey($proceso->proyecto)->value('sector');

        return PlantillaChecklist::query()
            ->where('uso', PlantillaChecklist::USO_CONTRATACION)
            ->where('motor', PlantillaChecklist::MOTOR_SIMPLE)
            ->where('activo', true)
            ->whereHas('versiones', fn ($q) => $this->publicada($q))
            ->where(fn (Builder $q) => $q->where('todas_modalidades', true)
                ->when($proceso->modalidad !== null, fn (Builder $m) => $m->orWhereHas('modalidades', fn (Builder $w) => $w->where('modalidades.id', $proceso->modalidad))))
            ->where(fn (Builder $q) => $q->where('todas_fuentes', true)
                ->when($proceso->fuente_id !== null, fn (Builder $f) => $f->orWhereHas('fuentes', fn (Builder $w) => $w->where('fuentes_financiacion.id', $proceso->fuente_id))))
            ->where(fn (Builder $q) => $q->where('todos_sectores', true)
                ->when($sector !== null, fn (Builder $s) => $s->orWhereHas('sectores', fn (Builder $w) => $w->where('sectores.id', $sector))));
    }

    /**
     * La versión vigente, si está publicada: un borrador nunca arma un expediente.
     *
     * @param Builder|HasMany $versiones consulta de versiones de plantilla
     */
    private function publicada(Builder|HasMany $versiones): Builder|HasMany
    {
        return $versiones->vigente()->where('estado', CatalogoVersion::PUBLICADA);
    }
}
```

En `app/Http/Controllers/ProcesoContratacionController.php`, reemplazar (1 de 5):

```php

use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Services\Contratacion\ProcesosContratacion;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
```

por:

```php

use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Services\Contratacion\ExpedientesContratacion;
use App\Services\Contratacion\ProcesosContratacion;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
```

En `app/Http/Controllers/ProcesoContratacionController.php`, reemplazar (2 de 5):

```php
        'observacion.max' => 'La observación admite hasta 2000 caracteres.',
        'actualizado.present' => 'Falta la versión del proceso que estabas editando. Ciérralo y vuelve a abrirlo.',
        'actualizado.date' => 'La versión del proceso no es válida. Ciérralo y vuelve a abrirlo.',
    ];

    private const ATRIBUTOS = [
```

por:

```php
        'observacion.max' => 'La observación admite hasta 2000 caracteres.',
        'actualizado.present' => 'Falta la versión del proceso que estabas editando. Ciérralo y vuelve a abrirlo.',
        'actualizado.date' => 'La versión del proceso no es válida. Ciérralo y vuelve a abrirlo.',
        'plantillas.required' => 'Elige al menos una plantilla para el expediente.',
        'plantillas.array' => 'Elige al menos una plantilla para el expediente.',
        'plantillas.max' => 'Un expediente admite como máximo :max plantillas.',
    ];

    private const ATRIBUTOS = [
```

En `app/Http/Controllers/ProcesoContratacionController.php`, reemplazar (3 de 5):

```php
        'estado' => 'el estado',
        'observacion' => 'la observación',
        'actualizado' => 'la versión del proceso',
    ];

    // La columna es decimal(15, 2): 13 cifras enteras y 2 decimales.
```

por:

```php
        'estado' => 'el estado',
        'observacion' => 'la observación',
        'actualizado' => 'la versión del proceso',
        'plantillas' => 'las plantillas',
        'plantillas.*' => 'cada plantilla',
    ];

    // La columna es decimal(15, 2): 13 cifras enteras y 2 decimales.
```

En `app/Http/Controllers/ProcesoContratacionController.php`, reemplazar (4 de 5):

```php
    // proceso sin fechas, anterior a ellas. Con otro valor, el servicio responde 409.
    private const VERSION = ['present', 'nullable', 'bail', 'string', 'date'];

    public function __construct(private readonly ProcesosContratacion $procesos)
    {
    }

    public function index(Proyecto $proyecto): JsonResponse
```

por:

```php
    // proceso sin fechas, anterior a ellas. Con otro valor, el servicio responde 409.
    private const VERSION = ['present', 'nullable', 'bail', 'string', 'date'];

    public function __construct(
        private readonly ProcesosContratacion $procesos,
        private readonly ExpedientesContratacion $expedientes,
        private readonly ExpedienteService $expediente,
    ) {
    }

    public function index(Proyecto $proyecto): JsonResponse
```

En `app/Http/Controllers/ProcesoContratacionController.php`, reemplazar (5 de 5):

```php
        return response()->json($this->procesos->precargaContrato($proceso));
    }

    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $proceso = $this->procesos->crear($proyecto, $this->validar($request), $request->user());
```

por:

```php
        return response()->json($this->procesos->precargaContrato($proceso));
    }

    /** Las plantillas de contratación con que se puede crear el expediente del proceso. */
    public function plantillasCompatibles(ProcesoLicitacion $proceso): JsonResponse
    {
        return response()->json($this->expedientes->disponibles($proceso));
    }

    /** Crea el expediente del proceso con las plantillas elegidas; la primera es la principal. */
    public function crearExpediente(Request $request, ProcesoLicitacion $proceso): JsonResponse
    {
        $entero = fn (string $atributo, mixed $valor, Closure $falla) => is_int($valor)
            || $falla('Revisa :attribute: debe ser un número entero.');
        $datos = $request->validate([
            'plantillas' => ['bail', 'required', 'array', 'max:' . PlantillasExpediente::MAX_PLANTILLAS],
            'plantillas.*' => ['bail', 'integer', $entero],
        ], self::MENSAJES, self::ATRIBUTOS);
        $creado = $this->expedientes->crear($proceso, $datos['plantillas'], $request->user());

        return response()->json($this->expediente->presentar($creado), 201);
    }

    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $proceso = $this->procesos->crear($proyecto, $this->validar($request), $request->user());
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (1 de 5):

```php
use App\Models\Contrato;
use App\Models\Proyecto;
use App\Services\Contratacion\ContratosService;
use App\Services\Contratacion\ProcesosContratacion;
use App\Services\Financiacion\FuentesProyecto;
use App\Services\Viabilizacion\ExpedienteService;
```

por:

```php
use App\Models\Contrato;
use App\Models\Proyecto;
use App\Services\Contratacion\ContratosService;
use App\Services\Contratacion\ExpedientesContratacion;
use App\Services\Contratacion\ProcesosContratacion;
use App\Services\Financiacion\FuentesProyecto;
use App\Services\Viabilizacion\ExpedienteService;
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (2 de 5):

```php
        return response()->json(['success' => 'Fase activada correctamente']);
    }

    public function guardarProyecto(Request $request, ExpedienteService $expedientes, FuentesProyecto $fuentes)
    {
        $proyecto = $request->all();
        $proyectoId = $proyecto['id'] ?? null;
        $faseAnterior = null;

        // Antes de la transacción: su catch convertiría una regla incumplida en un 500.
        // Al editar sin enviar fuentes se conservan las que tiene.
```

por:

```php
        return response()->json(['success' => 'Fase activada correctamente']);
    }

    public function guardarProyecto(Request $request, ExpedienteService $expedientes, FuentesProyecto $fuentes, ExpedientesContratacion $expedientesContratacion)
    {
        $proyecto = $request->all();
        $proyectoId = $proyecto['id'] ?? null;
        $faseAnterior = null;
        $cambiaSector = false;

        // Antes de la transacción: su catch convertiría una regla incumplida en un 500.
        // Al editar sin enviar fuentes se conservan las que tiene.
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (3 de 5):

```php

                // Solo si viene en la petición: quien no lo envía no debe borrarlo.
                if (array_key_exists('sector', $proyecto)) {
                    DB::table('proyectos')->where('id', $proyecto['id'])->update(['sector' => $proyecto['sector'] ?: null]);
                }
```

por:

```php

                // Solo si viene en la petición: quien no lo envía no debe borrarlo.
                if (array_key_exists('sector', $proyecto)) {
                    $sectorAnterior = DB::table('proyectos')->where('id', $proyecto['id'])->value('sector');
                    $cambiaSector = (int) ($sectorAnterior ?? 0) !== (int) ($proyecto['sector'] ?: 0);
                    DB::table('proyectos')->where('id', $proyecto['id'])->update(['sector' => $proyecto['sector'] ?: null]);
                }
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (4 de 5):

```php
        )) {
            $expedientes->crearAutomatico($guardado, $request->user());
        }
        if ($guardado && ! $esNuevo && ($advertencia = $expedientes->advertenciaAlSalirDeFormulacion($guardado, $faseAnterior))) {
            $respuesta['advertencia'] = $advertencia;
        }
```

por:

```php
        )) {
            $expedientes->crearAutomatico($guardado, $request->user());
        }
        // Con otro sector, un proceso sin expediente puede tener ahora una sola plantilla de
        // contratación compatible: nace su expediente, sin interrumpir el guardado.
        if ($guardado && $cambiaSector) {
            $expedientesContratacion->crearAutomaticosDeProyecto($guardado->id, $request->user());
        }
        if ($guardado && ! $esNuevo && ($advertencia = $expedientes->advertenciaAlSalirDeFormulacion($guardado, $faseAnterior))) {
            $respuesta['advertencia'] = $advertencia;
        }
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (5 de 5):

```php
        return response()->json($respuesta);
    }

    public function eliminarProyecto(Request $request)
    {
        $proyecto = $request->all();
        DB::table('proyectos')->where('id', $proyecto['id'])->delete();
        return response()->json(['success' => 'Proyecto eliminado correctamente']);
    }
```

por:

```php
        return response()->json($respuesta);
    }

    public function eliminarProyecto(Request $request, ExpedientesContratacion $expedientesContratacion)
    {
        $proyecto = $request->all();
        // Primero los expedientes de contratación: sin ellos, la cascada a los procesos no
        // choca con el restrict de proyectos_expedientes.proceso_id.
        DB::transaction(function () use ($proyecto, $expedientesContratacion) {
            $expedientesContratacion->eliminarDeProyecto((int) $proyecto['id']);
            DB::table('proyectos')->where('id', $proyecto['id'])->delete();
        });
        return response()->json(['success' => 'Proyecto eliminado correctamente']);
    }
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (1 de 7):

```php
 * - Un proceso nuevo empieza en preparación. El estado solo cambia con cambiarEstado(): lo
 *   cambia una persona, sin transiciones prohibidas (el checklist solo avisa), pero no al mismo
 *   estado.
 * - No se elimina un proceso con contratos vinculados (contratos.proceso_licitacion).
 * - Todo queda en checklist_historial (entidad_tipo = proceso, sin expediente): al editar, solo
 *   lo que cambió; al eliminar, los datos para saber qué era.
 * - Los cambios sobre un proceso bloquean su fila: dos ediciones no se pisan en el historial.
```

por:

```php
 * - Un proceso nuevo empieza en preparación. El estado solo cambia con cambiarEstado(): lo
 *   cambia una persona, sin transiciones prohibidas (el checklist solo avisa), pero no al mismo
 *   estado.
 * - No se elimina un proceso con contratos vinculados (contratos.proceso_licitacion) ni con
 *   expediente de contratación.
 * - Al registrarlo, o al cambiarle la modalidad o la fuente mientras no tenga expediente, se
 *   intenta crear su expediente (ExpedientesContratacion::crearAutomatico), ya guardado el
 *   proceso: nunca interrumpe el guardado.
 * - Todo queda en checklist_historial (entidad_tipo = proceso, sin expediente): al editar, solo
 *   lo que cambió; al eliminar, los datos para saber qué era.
 * - Los cambios sobre un proceso bloquean su fila: dos ediciones no se pisan en el historial.
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (2 de 7):

```php

    private const RELACIONES = ['modalidadRel:id,nombre,clave,activo', 'entidadContratante:id,nombre', 'fuente:id,nombre', 'responsable:id,nombre', 'usuarioCreo:id,name'];

    public function __construct(private readonly HistorialChecklist $historial)
    {
    }

    /** Los procesos del proyecto en el orden en que se crearon, con la forma de presentar(). */
```

por:

```php

    private const RELACIONES = ['modalidadRel:id,nombre,clave,activo', 'entidadContratante:id,nombre', 'fuente:id,nombre', 'responsable:id,nombre', 'usuarioCreo:id,name'];

    public function __construct(
        private readonly HistorialChecklist $historial,
        private readonly ExpedientesContratacion $expedientes,
    ) {
    }

    /** Los procesos del proyecto en el orden en que se crearon, con la forma de presentar(). */
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (3 de 7):

```php
    {
        $valores = $this->valores([...array_fill_keys(array_keys(self::CAMPOS), null), ...$datos]);

        return DB::transaction(function () use ($proyecto, $valores, $usuario) {
            $this->exigirCatalogos($valores, null);
            $this->exigirFechas($valores);
            $proceso = ProcesoLicitacion::create([
```

por:

```php
    {
        $valores = $this->valores([...array_fill_keys(array_keys(self::CAMPOS), null), ...$datos]);

        $proceso = DB::transaction(function () use ($proyecto, $valores, $usuario) {
            $this->exigirCatalogos($valores, null);
            $this->exigirFechas($valores);
            $proceso = ProcesoLicitacion::create([
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (4 de 7):

```php

            return $proceso;
        });
    }

    /**
```

por:

```php

            return $proceso;
        });
        // Después del commit: el proceso queda guardado pase lo que pase con el expediente.
        $this->expedientes->crearAutomatico($proceso, $usuario);

        return $proceso;
    }

    /**
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (5 de 7):

```php
    {
        $valores = $this->valores($datos);

        return DB::transaction(function () use ($proceso, $valores, $actualizado, $usuario) {
            // Compara con la fila bloqueada: así también se decide sobre los catálogos y las
            // fechas que el proceso tiene ahora, no los de cuando llegó el pedido.
            $actual = $this->bloquearVigente($proceso, $actualizado);
```

por:

```php
    {
        $valores = $this->valores($datos);

        [$actual, $cambios] = DB::transaction(function () use ($proceso, $valores, $actualizado, $usuario) {
            // Compara con la fila bloqueada: así también se decide sobre los catálogos y las
            // fechas que el proceso tiene ahora, no los de cuando llegó el pedido.
            $actual = $this->bloquearVigente($proceso, $actualizado);
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (6 de 7):

```php
                $this->historial->registrarProceso($actual, 'proceso_editado', array_intersect_key($antes, $cambios), $cambios, $usuario);
            }

            return $actual;
        });
    }

    /** `$actualizado` es el `actualizado` del proceso que tenía el formulario. */
```

por:

```php
                $this->historial->registrarProceso($actual, 'proceso_editado', array_intersect_key($antes, $cambios), $cambios, $usuario);
            }

            return [$actual, $cambios];
        });
        // Después del commit, como al crearlo. crearAutomatico no toca un expediente que ya existe.
        if (array_intersect_key($cambios, ['modalidad_id' => true, 'fuente_id' => true]) !== []) {
            $this->expedientes->crearAutomatico($actual, $usuario);
        }

        return $actual;
    }

    /** `$actualizado` es el `actualizado` del proceso que tenía el formulario. */
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (7 de 7):

```php
                    ? 'El proceso tiene 1 contrato vinculado: no se puede eliminar.'
                    : "El proceso tiene {$contratos} contratos vinculados: no se puede eliminar.");
            }

            // El registro sobrevive al proceso: con el proyecto y los nombres de los catálogos.
            $actual->load(['modalidadRel:id,nombre', 'entidadContratante:id,nombre', 'fuente:id,nombre', 'responsable:id,nombre']);
```

por:

```php
                    ? 'El proceso tiene 1 contrato vinculado: no se puede eliminar.'
                    : "El proceso tiene {$contratos} contratos vinculados: no se puede eliminar.");
            }
            if ($actual->expediente()->exists()) {
                throw new ReglaNegocioException('El proceso tiene un expediente de contratación: no se puede eliminar.');
            }

            // El registro sobrevive al proceso: con el proyecto y los nombres de los catálogos.
            $actual->load(['modalidadRel:id,nombre', 'entidadContratante:id,nombre', 'fuente:id,nombre', 'responsable:id,nombre']);
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 4):

```php
use App\Models\CatalogoVersion;
use App\Models\Fase;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
```

por:

```php
use App\Models\CatalogoVersion;
use App\Models\Fase;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 4):

```php
use App\Models\RequisitoTipologia;
use App\Models\Responsable;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
```

por:

```php
use App\Models\RequisitoTipologia;
use App\Models\Responsable;
use App\Models\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (3 de 4):

```php
 * - Solo se radica con todos los requisitos que aplican cumplidos.
 * - Radicado, el expediente queda congelado: no se reconfigura ni se marca.
 * - Los expedientes del proyecto son los de viabilización (Proyecto::expedientes()). Uno de
 *   contratación (de un proceso) comparte el checklist, pero no se configura ni se radica.
 */
final class ExpedienteService
{
```

por:

```php
 * - Solo se radica con todos los requisitos que aplican cumplidos.
 * - Radicado, el expediente queda congelado: no se reconfigura ni se marca.
 * - Los expedientes del proyecto son los de viabilización (Proyecto::expedientes()). Uno de
 *   contratación (de un proceso) nace con crearDeProceso(), ya configurado: comparte el
 *   checklist, pero no se configura ni se radica.
 */
final class ExpedienteService
{
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (4 de 4):

```php
        });
    }

    /**
     * Expediente que nace al guardar el proyecto, si le corresponde. Con plantilla SGR compatible,
     * la preselecciona con la fase III y el sector sugerido por el proyecto; sin ella, nace
```

por:

```php
        });
    }

    /**
     * Expediente de contratación de un proceso, con las plantillas que valida quien lo pide
     * (ExpedientesContratacion) con el proceso ya bloqueado. Nace configurado: no tiene
     * configuración SGR que confirmar. La fase SGR conserva el valor por defecto de la columna,
     * que no se usa. Un proceso tiene como máximo un expediente.
     *
     * @param Closure(ProcesoLicitacion): array{versiones: array<int, int>, principal_id: int} $plantillas
     *        recibe el proceso bloqueado y devuelve plantilla_id => catalogo_version_id, con la
     *        forma de PlantillasExpediente::validar()
     */
    public function crearDeProceso(ProcesoLicitacion $proceso, Closure $plantillas, ?User $usuario): ProyectoExpediente
    {
        return DB::transaction(function () use ($proceso, $plantillas, $usuario) {
            // El bloqueo evita dos expedientes por una doble petición simultánea (el índice
            // único de proceso_id es la última defensa) y que el proceso cambie o se elimine
            // mientras nace: eliminado, no se encuentra (404).
            $proceso = ProcesoLicitacion::query()->whereKey($proceso->id)->lockForUpdate()->firstOrFail();
            $plantillas = $plantillas($proceso);
            $existente = ProyectoExpediente::query()->where('proceso_id', $proceso->id)->value('id');
            if ($existente) {
                throw new ReglaNegocioException("El proceso ya tiene el expediente #{$existente}.");
            }

            $expediente = ProyectoExpediente::create([
                'proyecto_id' => $proceso->proyecto,
                'tipo' => ProyectoExpediente::TIPO_CONTRATACION,
                'proceso_id' => $proceso->id,
                'estado' => ProyectoExpediente::BORRADOR,
                'fecha_configuracion' => now(),
                'usuario_creo_id' => $usuario?->id,
            ])->refresh();

            $this->plantillas->guardar($expediente, $plantillas);
            $this->sincronizar($expediente);
            $this->historial->registrarExpediente($expediente, 'expediente_creado', [], [
                'proceso_id' => $proceso->id,
                'plantillas' => $this->plantillasRegistrables($this->plantillas->versionesDe($expediente)),
            ], $usuario);

            return $expediente;
        });
    }

    /**
     * Expediente que nace al guardar el proyecto, si le corresponde. Con plantilla SGR compatible,
     * la preselecciona con la fase III y el sector sugerido por el proyecto; sin ella, nace
```

En `routes/console.php`, reemplazar (1 de 2):

```php

use App\Exceptions\ReglaNegocioException;
use App\Models\Proyecto;
use App\Services\Contratacion\PlantillaLicitacionPublica;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
```

por:

```php

use App\Exceptions\ReglaNegocioException;
use App\Models\Proyecto;
use App\Services\Contratacion\ExpedientesContratacion;
use App\Services\Contratacion\PlantillaLicitacionPublica;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
```

En `routes/console.php`, reemplazar (2 de 2):

```php
    $this->info("Expedientes creados: {$creados} de {$proyectos->count()}.");
})->purpose('Crea el expediente, sin configurar, a los proyectos en Formulación con al menos una fuente definida que no tienen');

Artisan::command('plantillas:sembrar-contratacion {--simular : Solo lista los requisitos que crearía, sin escribir nada}', function (PlantillaLicitacionPublica $semilla) {
    if ($existente = $semilla->existente()) {
        $this->info("La plantilla \"{$existente->nombre}\" ya existe: no se creó nada.");
```

por:

```php
    $this->info("Expedientes creados: {$creados} de {$proyectos->count()}.");
})->purpose('Crea el expediente, sin configurar, a los proyectos en Formulación con al menos una fuente definida que no tienen');

Artisan::command('expedientes:crear-faltantes-contratacion {--simular : Solo lista los procesos y la plantilla con que nacería su expediente, sin crear nada}', function (ExpedientesContratacion $servicio) {
    $faltantes = $servicio->faltantes();

    if ($this->option('simular')) {
        $faltantes->each(function (array $fila) {
            $proceso = $fila['proceso'];
            $nombre = $proceso->codigo_proceso ?? Str::limit($proceso->objeto, 60);
            $this->line("#{$proceso->id} {$nombre} ({$proceso->proyectoRel?->nombre}): plantilla {$fila['plantilla']->nombre}");
        });
        $this->info("Se crearían {$faltantes->count()} expedientes.");

        return;
    }

    $creados = $faltantes->filter(fn (array $fila) => $servicio->crearAutomatico($fila['proceso'], null) !== null)->count();
    $this->info("Expedientes creados: {$creados} de {$faltantes->count()}.");
})->purpose('Crea el expediente de contratación a los procesos sin expediente con una sola plantilla compatible publicada');

Artisan::command('plantillas:sembrar-contratacion {--simular : Solo lista los requisitos que crearía, sin escribir nada}', function (PlantillaLicitacionPublica $semilla) {
    if ($existente = $semilla->existente()) {
        $this->info("La plantilla \"{$existente->nombre}\" ya existe: no se creó nada.");
```

En `routes/contratacion.php`, reemplazar (1 de 2):

```php
    ->name('procesos.index');
Route::get('/procesos/{proceso}/precarga-contrato', [ProcesoContratacionController::class, 'precargaContrato'])
    ->name('procesos.precarga-contrato');

// Contratos de todos los proyectos (y los que no tienen proyecto). Sin GET /contratos ni
// GET /contratos/{id}: esas rutas son del SPA y no deben coincidir con la API.
```

por:

```php
    ->name('procesos.index');
Route::get('/procesos/{proceso}/precarga-contrato', [ProcesoContratacionController::class, 'precargaContrato'])
    ->name('procesos.precarga-contrato');
Route::get('/procesos/{proceso}/plantillas-compatibles', [ProcesoContratacionController::class, 'plantillasCompatibles'])
    ->name('procesos.plantillas-compatibles');

// Contratos de todos los proyectos (y los que no tienen proyecto). Sin GET /contratos ni
// GET /contratos/{id}: esas rutas son del SPA y no deben coincidir con la API.
```

En `routes/contratacion.php`, reemplazar (2 de 2):

```php
        ->name('procesos.estado');
    Route::delete('/procesos/{proceso}', [ProcesoContratacionController::class, 'destroy'])
        ->name('procesos.destroy');
});

// SECOP II (datos abiertos): consultar para importar contratos. Administrador o Gestor, con
```

por:

```php
        ->name('procesos.estado');
    Route::delete('/procesos/{proceso}', [ProcesoContratacionController::class, 'destroy'])
        ->name('procesos.destroy');
    Route::post('/procesos/{proceso}/expediente', [ProcesoContratacionController::class, 'crearExpediente'])
        ->name('procesos.expediente.store');
});

// SECOP II (datos abiertos): consultar para importar contratos. Administrador o Gestor, con
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/ExpedienteProcesoApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1177 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Services/Contratacion/ExpedientesContratacion.php tests/Feature/Contratacion/ExpedienteProcesoApiTest.php app/Http/Controllers/ProcesoContratacionController.php app/Http/Controllers/ProyectoController.php app/Services/Contratacion/ProcesosContratacion.php app/Services/Viabilizacion/ExpedienteService.php routes/console.php routes/contratacion.php
git commit -m "feat: contracting templates for a process and its expedient"
```

---

### Tarea 4: Etapas, avance por etapa y cierre con aviso

Requisitos con etapa, propios con etapa obligatoria, avance por etapa, cierre que solo avisa, congelado, y el expediente en la API del proceso.

**Files:**
- Create: `app/Exceptions/CierreConPendientesException.php`
- Create: `app/Services/Contratacion/PlantillasContratacion.php`
- Test (create): `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`
- Modify: `app/Http/Controllers/ExpedienteController.php`
- Modify: `app/Http/Controllers/NoAplicaRequisitoController.php`
- Modify: `app/Http/Controllers/RequisitoPropioController.php`
- Modify: `app/Models/ProyectoExpediente.php`
- Modify: `app/Models/ProyectoRequisito.php`
- Modify: `app/Services/Contratacion/ExpedientesContratacion.php`
- Modify: `app/Services/Contratacion/ProcesosContratacion.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `app/Services/Viabilizacion/RequisitosPropios.php`
- Modify: `routes/viabilizacion.php`
- Test: `tests/Feature/Contratacion/ExpedienteProcesoApiTest.php`
- Test: `tests/Feature/Contratacion/ProcesosContratacionApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`:

```php
<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
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

/**
 * Checklist de un expediente de contratación: etapas de cada fila, avance por etapa, requisitos
 * propios con etapa, cierre con aviso de pendientes, expediente cerrado congelado y el bloque del
 * expediente en la API del proceso. La API de viabilización no cambia.
 */
class ExpedienteContratacionApiTest extends TestCase
{
    use RefreshDatabase;

    private const CERRADO = 'El expediente está cerrado.';

    private Proyecto $proyecto;
    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $sector = DB::table('sectores')->insertGetId(['nombre' => 'Agua Potable', 'activo' => true]);
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal', 'sector' => $sector]);
        $this->gestor = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Gestora']);
    }

    private function como(Rol $rol = Rol::Gestor): static
    {
        $usuario = $rol === Rol::Gestor ? $this->gestor : User::factory()->create(['rol' => $rol]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function modalidad(string $clave = 'licitacion_publica'): int
    {
        return Modalidad::where('clave', $clave)->firstOrFail()->id;
    }

    private function etapa(string $clave): EtapaContratacion
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail();
    }

    private function etapaJson(string $clave): array
    {
        $etapa = $this->etapa($clave);

        return ['id' => $etapa->id, 'nombre' => $etapa->nombre, 'orden' => $etapa->orden];
    }

    /**
     * Plantilla de contratación publicada, por la API: licitación pública, todas las fuentes y
     * todos los sectores. Por defecto, tres requisitos: dos de planeación (uno opcional) y uno
     * del pliego definitivo, creados en desorden para comprobar el orden del catálogo.
     */
    private function plantilla(string $nombre = 'Licitación', array $requisitos = []): PlantillaChecklist
    {
        $admin = $this->como(Rol::Administrador);
        $id = $admin->postJson('/GestPro/plantillas', [
            'nombre' => $nombre,
            'uso' => PlantillaChecklist::USO_CONTRATACION,
            'todas_fuentes' => true,
            'todos_sectores' => true,
            'modalidad_ids' => [$this->modalidad()],
        ])->assertCreated()->json('id');
        $plantilla = PlantillaChecklist::findOrFail($id);
        $borrador = $plantilla->versiones()->where('estado', CatalogoVersion::BORRADOR)->firstOrFail();
        $requisitos = $requisitos ?: [
            ['texto' => 'Pliego de condiciones', 'etapa_id' => $this->etapa('pliego_definitivo')->id],
            ['texto' => 'Estudios previos', 'etapa_id' => $this->etapa('planeacion')->id],
            ['texto' => 'Análisis del sector', 'etapa_id' => $this->etapa('planeacion')->id, 'caracter' => RequisitoCatalogo::OPCIONAL],
        ];
        foreach ($requisitos as $requisito) {
            $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos", $requisito)->assertCreated();
        }
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();

        return $plantilla->fresh();
    }

    private function proceso(array $cambios = []): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => $this->proyecto->id,
            'objeto' => 'Construcción del acueducto',
            'modalidad' => $this->modalidad(),
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-001',
            ...$cambios,
        ]);
    }

    /** Expediente de contratación de un proceso nuevo, creado por la API. */
    private function expediente(?PlantillaChecklist $plantilla = null, ?ProcesoLicitacion $proceso = null): ProyectoExpediente
    {
        $plantilla ??= $this->plantilla();
        $proceso ??= $this->proceso();
        $id = $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => [$plantilla->id]])
            ->assertCreated()->json('id');

        return ProyectoExpediente::findOrFail($id);
    }

    private function leer(ProyectoExpediente $expediente)
    {
        return $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$expediente->id}")->assertOk();
    }

    private function fila(ProyectoExpediente $expediente, string $texto): ProyectoRequisito
    {
        return $expediente->requisitos()->whereHas('requisito', fn ($q) => $q->where('texto', $texto))->firstOrFail();
    }

    private function cumplir(ProyectoExpediente $expediente, ProyectoRequisito $fila)
    {
        return $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$fila->id}", ['cumplido' => true])->assertOk();
    }

    /** Expediente de viabilización del proyecto, con la plantilla SGR y la configuración confirmada. */
    private function viabilizacion(): ProyectoExpediente
    {
        $this->proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        $servicio = app(ExpedienteService::class);
        $expediente = $servicio->crear($this->proyecto->fresh(), ['fase_sgr' => 3], $this->gestor);

        return $servicio->confirmarConfiguracion($expediente, ['fase_sgr' => 3], $this->gestor);
    }

    // ------------------------------------------------------------ respuesta

    public function test_la_respuesta_trae_el_proceso_las_etapas_y_el_avance_por_etapa(): void
    {
        $plantilla = $this->plantilla();
        $proceso = $this->proceso();
        $expediente = $this->expediente($plantilla, $proceso);
        $r = $this->leer($expediente);

        $this->assertSame([
            'id', 'proyecto', 'plantillas', 'tiene_sgr', 'configurado', 'estado', 'usuario_creo', 'creado', 'actualizado',
            'tipo', 'proceso', 'fecha_cierre', 'usuario_cierre',
            'progreso', 'avance_por_etapa', 'requisitos', 'avisos_duplicados', 'avisos_duplicados_total', 'retenidos',
        ], array_keys($r->json()));
        $r->assertJsonPath('tipo', ProyectoExpediente::TIPO_CONTRATACION)
            ->assertJsonPath('proceso', [
                'id' => $proceso->id,
                'codigo_proceso' => 'LP-001',
                'objeto' => 'Construcción del acueducto',
                'estado' => ProcesoLicitacion::PREPARACION,
                'modalidad' => ['id' => $this->modalidad(), 'nombre' => Modalidad::find($this->modalidad())->nombre],
            ])
            ->assertJsonPath('plantillas.0.plantilla_id', $plantilla->id)
            ->assertJsonPath('plantillas.0.compatible', true)
            ->assertJsonPath('fecha_cierre', null)
            ->assertJsonPath('usuario_cierre', null)
            ->assertJsonPath('progreso', ['total' => 2, 'cumplidos' => 0, 'porcentaje' => 0]);

        $etapas = collect($r->json('requisitos'))->pluck('etapa', 'requisito.texto')->all();
        $this->assertSame([
            'Pliego de condiciones' => $this->etapaJson('pliego_definitivo'),
            'Estudios previos' => $this->etapaJson('planeacion'),
            'Análisis del sector' => $this->etapaJson('planeacion'),
        ], $etapas);
        // En el orden del catálogo de etapas, no en el de los requisitos.
        $this->assertSame([
            ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0],
            ['etapa' => $this->etapaJson('pliego_definitivo'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0],
        ], $r->json('avance_por_etapa'));
    }

    public function test_la_plantilla_deja_de_ser_compatible_al_cambiar_la_modalidad_del_proceso(): void
    {
        $expediente = $this->expediente();
        $expediente->proceso->update(['modalidad' => $this->modalidad('minima_cuantia')]);

        $this->leer($expediente)->assertJsonPath('plantillas.0.compatible', false);
    }

    public function test_el_avance_por_etapa_cuenta_como_el_progreso(): void
    {
        $expediente = $this->expediente($this->plantilla('Licitación', [
            ['texto' => 'Estudios previos', 'etapa_id' => $this->etapa('planeacion')->id],
            ['texto' => 'CDP', 'etapa_id' => $this->etapa('planeacion')->id],
            ['texto' => 'Plan de adquisiciones', 'etapa_id' => $this->etapa('planeacion')->id],
            ['texto' => 'Pliego de condiciones', 'etapa_id' => $this->etapa('pliego_definitivo')->id],
            ['texto' => 'Acta de cierre', 'etapa_id' => $this->etapa('cierre')->id],
        ]));
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'));
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, 'CDP')->id}/no-aplica", [
            'justificacion' => 'Ya se expidió con el proyecto.',
        ])->assertOk();
        // Un requisito que quedó sin etapa (datos anteriores) va al final.
        RequisitoCatalogo::where('texto', 'Acta de cierre')->update(['etapa_id' => null]);

        $r = $this->leer($expediente);

        $this->assertSame([
            ['etapa' => $this->etapaJson('planeacion'), 'total' => 2, 'cumplidos' => 1, 'porcentaje' => 50],
            ['etapa' => $this->etapaJson('pliego_definitivo'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0],
            ['etapa' => null, 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0],
        ], $r->json('avance_por_etapa'));
        $r->assertJsonPath('progreso', ['total' => 4, 'cumplidos' => 1, 'porcentaje' => 25]);
        $this->assertSame(
            $r->json('progreso.total'),
            array_sum(array_column($r->json('avance_por_etapa'), 'total'))
        );
    }

    public function test_marcar_responde_tambien_el_avance_por_etapa(): void
    {
        $expediente = $this->expediente();

        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'))
            ->assertJsonPath('requisito.etapa', $this->etapaJson('planeacion'))
            ->assertJsonPath('progreso', ['total' => 2, 'cumplidos' => 1, 'porcentaje' => 50])
            ->assertJsonPath('avance_por_etapa.0', ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 1, 'porcentaje' => 100]);
    }

    public function test_la_api_de_viabilizacion_no_trae_las_claves_de_contratacion(): void
    {
        $viabilizacion = $this->viabilizacion();
        $r = $this->leer($viabilizacion);

        $this->assertSame([
            'id', 'proyecto', 'plantillas', 'tiene_sgr', 'fase_sgr', 'configurado', 'estado', 'fecha_radicado',
            'usuario_radico', 'usuario_creo', 'creado', 'actualizado',
            'configuracion', 'progreso', 'requisitos', 'avisos_duplicados', 'avisos_duplicados_total', 'retenidos',
        ], array_keys($r->json()));
        $this->assertNotEmpty($r->json('requisitos'));
        $this->assertSame([
            'id', 'estado', 'caracter', 'cuenta', 'no_aplica', 'responsable', 'cumplido', 'fecha_cumplido',
            'usuario_cumplido', 'observacion', 'soporte', 'requiere_aprobacion_no_aplica', 'requisito', 'plantilla',
            'origenes', 'propio', 'categoria',
        ], array_keys($r->json('requisitos.0')));
        $this->assertTrue($r->json('plantillas.0.compatible'));

        $fila = $viabilizacion->requisitos()->firstOrFail();
        $this->como()->patchJson("/GestPro/expedientes/{$viabilizacion->id}/requisitos/{$fila->id}", ['observacion' => 'Revisado'])
            ->assertOk()->assertJsonMissingPath('avance_por_etapa')->assertJsonMissingPath('requisito.etapa');
    }

    // ---------------------------------------------------- requisitos propios

    public function test_un_propio_de_contratacion_exige_una_etapa_activa(): void
    {
        $expediente = $this->expediente();
        $url = "/GestPro/expedientes/{$expediente->id}/requisitos-propios";
        $inactiva = $this->etapa('adendas');
        $inactiva->update(['activo' => false]);

        $this->como()->postJson($url, ['titulo' => 'Póliza'])->assertStatus(422)
            ->assertJsonPath('errors.etapa_propia_id.0', 'Elige la etapa de contratación del requisito.');
        $this->como()->postJson($url, ['titulo' => 'Póliza', 'etapa_propia_id' => 99999])->assertStatus(422)
            ->assertJsonPath('errors.etapa_propia_id.0', 'La etapa de contratación con id 99999 no existe.');
        $this->como()->postJson($url, ['titulo' => 'Póliza', 'etapa_propia_id' => $inactiva->id])->assertStatus(422)
            ->assertJsonPath('errors.etapa_propia_id.0', "La etapa \"{$inactiva->nombre}\" está inactiva.");
        $this->como()->postJson($url, ['titulo' => 'Póliza', 'etapa_propia_id' => '3'])->assertStatus(422)
            ->assertJsonValidationErrors('etapa_propia_id');
        $this->assertSame(0, $expediente->requisitos()->whereNull('requisito_catalogo_id')->count());

        $this->como()->postJson($url, ['titulo' => 'Póliza', 'etapa_propia_id' => $this->etapa('adjudicacion')->id])
            ->assertCreated()
            ->assertJsonPath('requisito.etapa', $this->etapaJson('adjudicacion'))
            ->assertJsonPath('avance_por_etapa.2', ['etapa' => $this->etapaJson('adjudicacion'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0]);

        $propio = $expediente->requisitos()->whereNull('requisito_catalogo_id')->sole();
        $this->assertSame($this->etapa('adjudicacion')->id, $propio->etapa_propia_id);
        $historial = DB::table('checklist_historial')->where('evento', 'requisito_propio_agregado')->sole();
        $this->assertSame($this->etapa('adjudicacion')->id, json_decode($historial->despues, true)['etapa_propia_id']);
    }

    public function test_editar_un_propio_conserva_o_cambia_su_etapa(): void
    {
        $expediente = $this->expediente();
        $adendas = $this->etapa('adendas');
        $id = $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/requisitos-propios", [
            'titulo' => 'Póliza', 'etapa_propia_id' => $adendas->id,
        ])->assertCreated()->json('requisito.id');
        $url = "/GestPro/expedientes/{$expediente->id}/requisitos-propios/{$id}";
        $adendas->update(['activo' => false]);

        // Sin el campo, no cambia; la que ya tiene se acepta aunque esté inactiva.
        $this->como()->putJson($url, ['titulo' => 'Póliza de seriedad'])->assertOk()
            ->assertJsonPath('requisito.etapa.id', $adendas->id);
        $this->como()->putJson($url, ['titulo' => 'Póliza de seriedad', 'etapa_propia_id' => $adendas->id])->assertOk();
        $this->como()->putJson($url, ['titulo' => 'Póliza de seriedad', 'etapa_propia_id' => null])->assertStatus(422)
            ->assertJsonPath('errors.etapa_propia_id.0', 'Elige la etapa de contratación del requisito.');

        $this->como()->putJson($url, ['titulo' => 'Póliza de seriedad', 'etapa_propia_id' => $this->etapa('cierre')->id])->assertOk()
            ->assertJsonPath('requisito.etapa', $this->etapaJson('cierre'));
        $historial = DB::table('checklist_historial')->where('evento', 'requisito_propio_editado')->orderByDesc('id')->first();
        $this->assertSame(['etapa_propia_id' => $adendas->id], json_decode($historial->antes, true));
        $this->assertSame(['etapa_propia_id' => $this->etapa('cierre')->id], json_decode($historial->despues, true));
    }

    public function test_un_propio_de_viabilizacion_no_lleva_etapa(): void
    {
        $viabilizacion = $this->viabilizacion();
        $url = "/GestPro/expedientes/{$viabilizacion->id}/requisitos-propios";

        $this->como()->postJson($url, ['titulo' => 'Acta', 'etapa_propia_id' => $this->etapa('planeacion')->id])->assertStatus(422)
            ->assertJsonPath('errors.etapa_propia_id.0', 'Los requisitos propios de un expediente de viabilización no llevan etapa de contratación.');
        $this->assertSame(0, $viabilizacion->requisitos()->whereNull('requisito_catalogo_id')->count());

        $r = $this->como()->postJson($url, ['titulo' => 'Acta'])->assertCreated()->assertJsonMissingPath('requisito.etapa');
        $this->assertNull(ProyectoRequisito::find($r->json('requisito.id'))->etapa_propia_id);
        $historial = DB::table('checklist_historial')->where('evento', 'requisito_propio_agregado')->sole();
        $this->assertArrayNotHasKey('etapa_propia_id', json_decode($historial->despues, true));
    }

    // ---------------------------------------------------------------- cierre

    public function test_cerrar_con_pendientes_sin_confirmar_avisa_y_no_cierra(): void
    {
        $expediente = $this->expediente();
        $historial = DB::table('checklist_historial')->count();

        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar")
            ->assertStatus(422)
            ->assertExactJson([
                'mensaje' => 'Faltan 2 requisitos por cumplir. Confirma para cerrar el expediente de todos modos.',
                'pendientes' => [
                    'total' => 2,
                    'por_etapa' => [
                        ['etapa' => $this->etapaJson('planeacion'), 'pendientes' => 1],
                        ['etapa' => $this->etapaJson('pliego_definitivo'), 'pendientes' => 1],
                    ],
                ],
            ]);
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'));
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => false])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'Falta 1 requisito por cumplir. Confirma para cerrar el expediente de todos modos.')
            ->assertJsonPath('pendientes.por_etapa', [['etapa' => $this->etapaJson('pliego_definitivo'), 'pendientes' => 1]]);

        $this->assertSame(ProyectoExpediente::BORRADOR, $expediente->fresh()->estado);
        $this->assertSame($historial + 1, DB::table('checklist_historial')->count()); // solo la marca
    }

    public function test_cerrar_confirmando_cierra_con_pendientes_y_lo_registra(): void
    {
        $expediente = $this->expediente();
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(10, 0));

        $r = $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertOk()
            ->assertJsonPath('estado', ProyectoExpediente::CERRADO)
            ->assertJsonPath('usuario_cierre', 'Gestora');
        $this->assertSame(now()->toIso8601String(), $r->json('fecha_cierre'));

        $cerrado = $expediente->fresh();
        $this->assertSame(ProyectoExpediente::CERRADO, $cerrado->estado);
        $this->assertSame($this->gestor->id, (int) $cerrado->usuario_radicado_id);
        $entrada = DB::table('checklist_historial')->where('evento', 'expediente_cerrado')->sole();
        $this->assertSame($expediente->id, (int) $entrada->expediente_id);
        $this->assertSame([
            'requisitos' => 2,
            'cumplidos' => 0,
            'pendientes' => [
                'total' => 2,
                'por_etapa' => [
                    ['etapa' => $this->etapaJson('planeacion'), 'pendientes' => 1],
                    ['etapa' => $this->etapaJson('pliego_definitivo'), 'pendientes' => 1],
                ],
            ],
        ], json_decode($entrada->despues, true));
    }

    public function test_sin_pendientes_cierra_sin_confirmar(): void
    {
        $expediente = $this->expediente();
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'));
        $this->cumplir($expediente, $this->fila($expediente, 'Pliego de condiciones'));

        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar")->assertOk()
            ->assertJsonPath('estado', ProyectoExpediente::CERRADO)
            ->assertJsonPath('progreso.porcentaje', 100);
        $this->assertSame(
            ['requisitos' => 2, 'cumplidos' => 2, 'pendientes' => ['total' => 0, 'por_etapa' => []]],
            json_decode(DB::table('checklist_historial')->where('evento', 'expediente_cerrado')->value('despues'), true)
        );
    }

    public function test_cerrar_exige_permiso_y_un_expediente_de_contratacion_abierto(): void
    {
        $expediente = $this->expediente();
        $viabilizacion = $this->viabilizacion();

        $this->como(Rol::Consulta)->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertForbidden();
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => 'si'])->assertStatus(422)
            ->assertJsonValidationErrors('confirmar');
        $this->como()->postJson("/GestPro/expedientes/{$viabilizacion->id}/cerrar", ['confirmar' => true])->assertStatus(422)
            ->assertExactJson(['mensaje' => 'El expediente es de viabilización: se radica, no se cierra.']);
        $this->assertSame(ProyectoExpediente::BORRADOR, $viabilizacion->fresh()->estado);

        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertOk();
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertStatus(422)
            ->assertExactJson(['mensaje' => self::CERRADO]);
        $this->assertSame(1, DB::table('checklist_historial')->where('evento', 'expediente_cerrado')->count());
    }

    public function test_un_expediente_cerrado_queda_congelado(): void
    {
        Storage::fake(ExpedienteService::DISCO);
        $plantilla = $this->plantilla();
        $expediente = $this->expediente($plantilla);
        $fila = $this->fila($expediente, 'Estudios previos');
        $base = "/GestPro/expedientes/{$expediente->id}";
        $this->como()->postJson("{$base}/requisitos/{$fila->id}/soporte", [
            'archivo' => UploadedFile::fake()->create('cdp.pdf', 10, 'application/pdf'),
        ])->assertOk();
        $propio = $this->como()->postJson("{$base}/requisitos-propios", [
            'titulo' => 'Póliza', 'etapa_propia_id' => $this->etapa('cierre')->id,
        ])->assertCreated()->json('requisito.id');
        $this->como()->postJson("{$base}/cerrar", ['confirmar' => true])->assertOk();
        $antes = [
            DB::table('proyectos_expedientes')->where('id', $expediente->id)->first(),
            DB::table('proyectos_requisitos')->where('expediente_id', $expediente->id)->orderBy('id')->get(),
            DB::table('checklist_historial')->count(),
        ];

        $pedidos = [
            ['patchJson', "{$base}/requisitos/{$fila->id}", ['cumplido' => true]],
            ['postJson', "{$base}/requisitos/{$fila->id}/soporte", ['archivo' => UploadedFile::fake()->create('otro.pdf', 10, 'application/pdf')]],
            ['deleteJson', "{$base}/requisitos/{$fila->id}/soporte", []],
            ['postJson', "{$base}/requisitos/{$fila->id}/no-aplica", ['justificacion' => 'No aplica']],
            ['deleteJson', "{$base}/requisitos/{$fila->id}/no-aplica", []],
            ['postJson', "{$base}/requisitos/{$fila->id}/aplica", []],
            ['postJson', "{$base}/requisitos/{$fila->id}/no-aplica/aprobar", []],
            ['postJson', "{$base}/requisitos/{$fila->id}/no-aplica/rechazar", []],
            ['postJson', "{$base}/requisitos-propios", ['titulo' => 'Otro', 'etapa_propia_id' => $this->etapa('cierre')->id]],
            ['putJson', "{$base}/requisitos-propios/{$propio}", ['titulo' => 'Póliza editada']],
            ['deleteJson', "{$base}/requisitos-propios/{$propio}", []],
            ['getJson', "{$base}/plantillas/{$plantilla->id}/version-nueva", []],
            ['postJson', "{$base}/plantillas/{$plantilla->id}/version-nueva", ['version_id' => $plantilla->versionVigente()->id]],
            ['deleteJson', $base, []],
        ];
        foreach ($pedidos as [$metodo, $url, $datos]) {
            $this->como()->{$metodo}($url, $datos)->assertStatus(422)->assertExactJson(['mensaje' => self::CERRADO]);
        }

        $this->assertEquals($antes, [
            DB::table('proyectos_expedientes')->where('id', $expediente->id)->first(),
            DB::table('proyectos_requisitos')->where('expediente_id', $expediente->id)->orderBy('id')->get(),
            DB::table('checklist_historial')->count(),
        ]);
        // Se sigue leyendo, con su cierre.
        $this->leer($expediente)->assertJsonPath('estado', ProyectoExpediente::CERRADO)->assertJsonPath('usuario_cierre', 'Gestora');
    }

    public function test_un_radicado_de_viabilizacion_conserva_su_mensaje(): void
    {
        $viabilizacion = $this->viabilizacion();
        DB::table('proyectos_expedientes')->where('id', $viabilizacion->id)->update(['estado' => ProyectoExpediente::RADICADO]);

        $this->como()->deleteJson("/GestPro/expedientes/{$viabilizacion->id}")->assertStatus(422)
            ->assertExactJson(['mensaje' => 'El expediente está radicado: su checklist está congelado y no admite cambios.']);
    }

    public function test_un_expediente_de_contratacion_en_borrador_se_elimina_y_libera_el_proceso(): void
    {
        $expediente = $this->expediente();
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'));

        $this->como()->deleteJson("/GestPro/expedientes/{$expediente->id}")->assertOk();

        $this->assertDatabaseMissing('proyectos_expedientes', ['id' => $expediente->id]);
        $this->como()->deleteJson("/GestPro/procesos/{$expediente->proceso_id}")->assertOk();
    }

    // ------------------------------------------------------ API del proceso

    public function test_los_procesos_traen_su_expediente_con_avance(): void
    {
        $plantilla = $this->plantilla();
        $sin = $this->proceso(['codigo_proceso' => 'LP-000']);
        $expediente = $this->expediente($plantilla);
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'));

        $procesos = $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk()->json('procesos');

        $this->assertSame([$sin->id, $expediente->proceso_id], array_column($procesos, 'id'));
        $this->assertNull($procesos[0]['expediente']);
        $this->assertSame([
            'id' => $expediente->id,
            'estado' => ProyectoExpediente::BORRADOR,
            'progreso' => ['total' => 2, 'cumplidos' => 1, 'porcentaje' => 50],
            'aviso_plantilla' => null,
        ], $procesos[1]['expediente']);
    }

    public function test_el_proceso_avisa_cuando_su_plantilla_ya_no_corresponde(): void
    {
        $expediente = $this->expediente();
        $proceso = $expediente->proceso;

        $r = $this->como()->putJson("/GestPro/procesos/{$proceso->id}", [
            'objeto' => $proceso->objeto,
            'modalidad_id' => $this->modalidad('minima_cuantia'),
            'plataforma' => $proceso->plataforma,
            'actualizado' => $proceso->updated_at?->toIso8601String(),
        ])->assertOk();

        $r->assertJsonPath('expediente.id', $expediente->id)
            ->assertJsonPath('expediente.aviso_plantilla', 'La plantilla del expediente ya no corresponde a la modalidad, la fuente o el sector del proceso.');
        $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")
            ->assertJsonPath('procesos.0.expediente.aviso_plantilla', 'La plantilla del expediente ya no corresponde a la modalidad, la fuente o el sector del proceso.');
    }

    public function test_con_varias_plantillas_avisa_solo_si_ninguna_corresponde(): void
    {
        $una = $this->plantilla('Una');
        $otra = $this->plantilla('Otra');
        $proceso = $this->proceso();
        $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => [$una->id, $otra->id]])->assertCreated();
        $aviso = fn () => $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")
            ->json('procesos.0.expediente.aviso_plantilla');

        $otra->update(['activo' => false]);
        $this->assertNull($aviso());

        $proceso->update(['modalidad' => $this->modalidad('minima_cuantia')]);
        $this->assertSame('Ninguna plantilla del expediente corresponde ya a la modalidad, la fuente o el sector del proceso.', $aviso());
    }

    private function avisoDelProceso(): ?string
    {
        return $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")
            ->assertOk()->json('procesos.0.expediente.aviso_plantilla');
    }

    public function test_si_la_plantilla_esta_inactiva_el_aviso_lo_dice(): void
    {
        $plantilla = $this->plantilla();
        $expediente = $this->expediente($plantilla);

        $plantilla->update(['activo' => false]);

        // Sigue cubriendo la modalidad, la fuente y el sector: no se dice que no corresponde.
        $this->assertSame('La plantilla del expediente está inactiva.', $this->avisoDelProceso());
        $this->leer($expediente)
            ->assertJsonPath('plantillas.0.compatible', false)
            ->assertJsonPath('plantillas.0.corresponde', true)
            ->assertJsonPath('plantillas.0.activa', false)
            ->assertJsonPath('plantillas.0.publicada', true);
    }

    public function test_si_la_plantilla_ya_no_tiene_version_publicada_el_aviso_lo_dice(): void
    {
        $plantilla = $this->plantilla();
        $expediente = $this->expediente($plantilla);

        DB::table('catalogo_versiones')->where('plantilla_id', $plantilla->id)->update(['vigente' => false]);

        $this->assertSame('La plantilla del expediente ya no tiene una versión publicada.', $this->avisoDelProceso());
        $this->leer($expediente)
            ->assertJsonPath('plantillas.0.compatible', false)
            ->assertJsonPath('plantillas.0.corresponde', true)
            ->assertJsonPath('plantillas.0.activa', true)
            ->assertJsonPath('plantillas.0.publicada', false);
    }

    public function test_inactiva_y_fuera_de_la_modalidad_avisa_que_no_corresponde(): void
    {
        $plantilla = $this->plantilla();
        $expediente = $this->expediente($plantilla);

        $plantilla->update(['activo' => false]);
        $expediente->proceso->update(['modalidad' => $this->modalidad('minima_cuantia')]);

        $this->assertSame('La plantilla del expediente ya no corresponde a la modalidad, la fuente o el sector del proceso.', $this->avisoDelProceso());
        $this->leer($expediente)
            ->assertJsonPath('plantillas.0.corresponde', false)
            ->assertJsonPath('plantillas.0.activa', false);
    }

    public function test_con_varias_plantillas_el_aviso_da_la_razon_de_todas(): void
    {
        $una = $this->plantilla('Una');
        $otra = $this->plantilla('Otra');
        $proceso = $this->proceso();
        $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => [$una->id, $otra->id]])->assertCreated();

        $una->update(['activo' => false]);
        $otra->update(['activo' => false]);
        $this->assertSame('Las plantillas del expediente están inactivas.', $this->avisoDelProceso());

        $vigente = $otra->versionVigente()->id;
        DB::table('catalogo_versiones')->where('id', $vigente)->update(['vigente' => false]);
        $otra->update(['activo' => true]);
        $this->assertSame(
            'Ninguna plantilla del expediente sigue vigente: unas están inactivas y otras ya no tienen una versión publicada.',
            $this->avisoDelProceso()
        );

        $otra->update(['activo' => false]);
        DB::table('catalogo_versiones')->where('id', $vigente)->update(['vigente' => true]);
        DB::table('plantilla_modalidades')->where('plantilla_id', $otra->id)->update(['modalidad_id' => $this->modalidad('minima_cuantia')]);
        $this->assertSame(
            'Ninguna plantilla del expediente sigue vigente: unas ya no corresponden a la modalidad, la fuente o el sector del proceso y otras están inactivas.',
            $this->avisoDelProceso()
        );
    }

    public function test_un_proceso_nuevo_responde_el_expediente_que_nacio_con_el(): void
    {
        $this->plantilla();

        $r = $this->como()->postJson("/GestPro/proyectos/{$this->proyecto->id}/procesos", [
            'objeto' => 'Interventoría', 'modalidad_id' => $this->modalidad(), 'plataforma' => 'secop_ii',
        ])->assertCreated();

        $r->assertJsonPath('expediente.estado', ProyectoExpediente::BORRADOR)
            ->assertJsonPath('expediente.progreso', ['total' => 2, 'cumplidos' => 0, 'porcentaje' => 0])
            ->assertJsonPath('expediente.aviso_plantilla', null);
    }

    public function test_el_listado_de_procesos_no_hace_una_consulta_por_proceso(): void
    {
        $plantilla = $this->plantilla();
        $this->expediente($plantilla);
        $consultas = fn () => $this->contarConsultas(fn () => $this->como(Rol::Consulta)
            ->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk());
        $conUno = $consultas();

        foreach (range(2, 4) as $i) {
            $this->expediente($plantilla, $this->proceso(['codigo_proceso' => "LP-00{$i}"]));
        }
        $this->proceso(['codigo_proceso' => 'LP-sin']);

        $this->assertSame($conUno, $consultas());
    }

    private function contarConsultas(\Closure $accion): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $accion();
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    }
}
```

En `tests/Feature/Contratacion/ExpedienteProcesoApiTest.php`, reemplazar (1 de 1):

```php
        Exceptions::assertReported(\RuntimeException::class);
    }

    // ------------------------------------------------- eliminar proyecto o proceso

    /** Debe comprobarse también en MariaDB: allí el borrado sin este paso falla. */
```

por:

```php
        Exceptions::assertReported(\RuntimeException::class);
    }

    public function test_cambiar_el_sector_no_hace_una_consulta_por_proceso(): void
    {
        $this->plantilla('Agua', ['todos_sectores' => false, 'sector_ids' => [$this->agua]]);
        $consultas = function (int $sector) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->guardarProyecto($sector)->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };
        $this->proceso(['codigo_proceso' => 'LP-001']);
        $conUno = $consultas($this->vias);

        foreach (range(2, 5) as $i) {
            $this->proceso(['codigo_proceso' => "LP-00{$i}"]);
        }
        $salud = DB::table('sectores')->insertGetId(['nombre' => 'Salud', 'activo' => true]);

        $this->assertSame($conUno, $consultas($salud));
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    // ------------------------------------------------- eliminar proyecto o proceso

    /** Debe comprobarse también en MariaDB: allí el borrado sin este paso falla. */
```

En `tests/Feature/Contratacion/ProcesosContratacionApiTest.php`, reemplazar (1 de 1):

```php
            'observaciones' => null,
            'estado' => 'preparacion',
            'contratos' => 0,
            'creado' => $r->json('creado'),
            'actualizado' => $r->json('actualizado'),
            'usuario_creo' => 'Ana Gestora',
```

por:

```php
            'observaciones' => null,
            'estado' => 'preparacion',
            'contratos' => 0,
            // Sin plantilla de contratación publicada, no nace expediente.
            'expediente' => null,
            'creado' => $r->json('creado'),
            'actualizado' => $r->json('actualizado'),
            'usuario_creo' => 'Ana Gestora',
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/ExpedienteContratacionApiTest.php tests/Feature/Contratacion/ExpedienteProcesoApiTest.php tests/Feature/Contratacion/ProcesosContratacionApiTest.php`
Expected: FALLAN 21 y pasan 144: los expedientes de contratación todavía no traen etapas ni avance por etapa, no existe `cerrar`, los propios no piden etapa y el proceso no trae su `expediente`.

- [ ] **Paso 3: Implementar**

Crear `app/Exceptions/CierreConPendientesException.php`:

```php
<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

/**
 * Cerrar un expediente de contratación con requisitos pendientes, sin confirmarlo. No es un
 * bloqueo: la pantalla muestra cuántos faltan y en qué etapas, y se cierra confirmando.
 * Responde 422 con el mensaje y `pendientes` ({total, por_etapa: [{etapa, pendientes}]}).
 */
class CierreConPendientesException extends ReglaNegocioException
{
    /** @param array{total: int, por_etapa: list<array{etapa: ?array, pendientes: int}>} $pendientes */
    public function __construct(string $mensaje, private readonly array $pendientes)
    {
        parent::__construct($mensaje);
    }

    /** @return array{total: int, por_etapa: list<array{etapa: ?array, pendientes: int}>} */
    public function pendientes(): array
    {
        return $this->pendientes;
    }

    public function render(): JsonResponse
    {
        return response()->json(['mensaje' => $this->getMessage(), 'pendientes' => $this->pendientes], 422);
    }
}
```

Crear `app/Services/Contratacion/PlantillasContratacion.php`:

```php
<?php

namespace App\Services\Contratacion;

use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Qué plantillas de contratación corresponden a un proceso. Es la única definición: la usan la
 * creación de su expediente (ExpedientesContratacion), la cabecera del expediente y el aviso del
 * proceso. Las de viabilización tienen la suya (PlantillasExpediente).
 *
 * Corresponden las plantillas de contratación activas, con motor simple y versión publicada
 * vigente, que cubren la modalidad del proceso (o todas), su fuente (o todas; sin fuente, solo las
 * de todas las fuentes) y el sector del proyecto (o todos; sin sector, solo las de todos los
 * sectores). Se decide en memoria con una lectura de las candidatas y sus coberturas, así que
 * cuesta lo mismo para un proceso que para todos los de un proyecto.
 */
final class PlantillasContratacion
{
    /** @return list<int> ids de las plantillas que hoy corresponden al proceso, de menor a mayor */
    public function idsCompatibles(ProcesoLicitacion $proceso): array
    {
        return $this->idsCompatiblesPorProceso(collect([$proceso]))[$proceso->id];
    }

    /**
     * @param Collection<int, ProcesoLicitacion> $procesos
     * @return array<int, list<int>> proceso_id => ids de sus plantillas compatibles, de menor a mayor
     */
    public function idsCompatiblesPorProceso(Collection $procesos): array
    {
        if ($procesos->isEmpty()) {
            return [];
        }
        $candidatas = PlantillaChecklist::query()
            ->where('uso', PlantillaChecklist::USO_CONTRATACION)
            ->where('motor', PlantillaChecklist::MOTOR_SIMPLE)
            ->where('activo', true)
            ->whereHas('versiones', fn ($q) => $this->publicada($q))
            ->orderBy('id')
            ->get(['id', 'todas_modalidades', 'todas_fuentes', 'todos_sectores']);

        return $this->cubiertasPorProceso($procesos, $candidatas);
    }

    /**
     * Cómo está cada una de las plantillas dadas frente a cada proceso, para avisar por qué ya no
     * le sirve: si cubre su modalidad, su fuente y su sector (sin mirar si está activa o
     * publicada; una que no es de contratación con motor simple no cubre), si está activa y si
     * tiene versión publicada vigente. Cuesta lo mismo para un proceso que para varios.
     *
     * @param Collection<int, ProcesoLicitacion> $procesos
     * @param list<int> $plantillaIds
     * @return array<int, array<int, array{corresponde: bool, activa: bool, publicada: bool}>> proceso_id => plantilla_id => estado
     */
    public function estadoPorProceso(Collection $procesos, array $plantillaIds): array
    {
        if ($procesos->isEmpty()) {
            return [];
        }
        $plantillas = $plantillaIds === [] ? collect() : PlantillaChecklist::query()
            ->whereIn('id', $plantillaIds)
            ->withExists(['versiones as tiene_publicada' => fn ($q) => $this->publicada($q)])
            ->orderBy('id')
            ->get()
            ->keyBy('id');
        $cubiertas = $this->cubiertasPorProceso($procesos, $plantillas->filter(
            fn (PlantillaChecklist $p) => $p->esContratacion() && $p->motor === PlantillaChecklist::MOTOR_SIMPLE
        )->values());

        return $procesos->mapWithKeys(fn (ProcesoLicitacion $proceso) => [$proceso->id => collect($plantillaIds)
            ->mapWithKeys(fn (int $id) => [$id => [
                'corresponde' => in_array($id, $cubiertas[$proceso->id], true),
                'activa' => (bool) $plantillas->get($id)?->activo,
                'publicada' => (bool) $plantillas->get($id)?->tiene_publicada,
            ]])->all()])->all();
    }

    /**
     * Por qué una plantilla ya no le sirve al proceso, con el estado de estadoPorProceso(); null si
     * le sirve. Primero la cobertura: es lo que cambia al editar el proceso o el proyecto.
     *
     * @param array{corresponde: bool, activa: bool, publicada: bool} $estado
     * @return ?string 'no_corresponde', 'inactiva' o 'sin_publicada'
     */
    public static function razon(array $estado): ?string
    {
        return match (true) {
            ! $estado['corresponde'] => 'no_corresponde',
            ! $estado['activa'] => 'inactiva',
            ! $estado['publicada'] => 'sin_publicada',
            default => null,
        };
    }

    /**
     * Las candidatas que cubren la modalidad, la fuente y el sector de cada proceso.
     *
     * @param Collection<int, ProcesoLicitacion> $procesos
     * @param Collection<int, PlantillaChecklist> $candidatas ordenadas por id
     * @return array<int, list<int>> proceso_id => ids, de menor a mayor
     */
    private function cubiertasPorProceso(Collection $procesos, Collection $candidatas): array
    {
        $ids = $candidatas->pluck('id');
        $cubre = fn (string $tabla, string $columna) => $ids->isEmpty() ? collect() : DB::table($tabla)
            ->whereIn('plantilla_id', $ids)
            ->get(['plantilla_id', $columna])
            ->groupBy('plantilla_id')
            ->map(fn (Collection $filas) => $filas->pluck($columna)->map(fn ($id) => (int) $id)->flip());
        $modalidades = $cubre('plantilla_modalidades', 'modalidad_id');
        $fuentes = $cubre('plantilla_fuentes', 'fuente_id');
        $sectores = $cubre('plantilla_sectores', 'sector_id');
        $sectorDe = Proyecto::query()->whereIn('id', $procesos->pluck('proyecto')->unique()->values())->pluck('sector', 'id');

        $cubierto = fn (bool $todas, ?Collection $elegidas, mixed $valor) => $todas
            || ($valor !== null && $elegidas?->has((int) $valor));

        return $procesos->mapWithKeys(function (ProcesoLicitacion $proceso) use ($candidatas, $modalidades, $fuentes, $sectores, $sectorDe, $cubierto) {
            $sector = $sectorDe->get($proceso->proyecto);

            return [$proceso->id => $candidatas
                ->filter(fn (PlantillaChecklist $p) => $cubierto((bool) $p->todas_modalidades, $modalidades->get($p->id), $proceso->modalidad)
                    && $cubierto((bool) $p->todas_fuentes, $fuentes->get($p->id), $proceso->fuente_id)
                    && $cubierto((bool) $p->todos_sectores, $sectores->get($p->id), $sector))
                ->pluck('id')->map(fn ($id) => (int) $id)->values()->all()];
        })->all();
    }

    /**
     * La versión vigente, si está publicada: un borrador nunca arma un expediente.
     *
     * @param Builder|HasMany $versiones consulta de versiones de plantilla
     */
    public function publicada(Builder|HasMany $versiones): Builder|HasMany
    {
        return $versiones->vigente()->where('estado', CatalogoVersion::PUBLICADA);
    }
}
```

En `app/Http/Controllers/ExpedienteController.php`, reemplazar (1 de 3):

```php
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Expedientes de viabilización SGR de un proyecto y su checklist.
 * Lectura: cualquier usuario autenticado. Escritura: can:editar-datos.
 */
class ExpedienteController extends Controller
```

por:

```php
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Expedientes de un proyecto (viabilización SGR) o de un proceso (contratación) y su checklist.
 * Lectura: cualquier usuario autenticado. Escritura: can:editar-datos.
 */
class ExpedienteController extends Controller
```

En `app/Http/Controllers/ExpedienteController.php`, reemplazar (2 de 3):

```php

        return response()->json([
            'requisito' => $this->servicio->presentarFila($fila->refresh()),
            'progreso' => $this->servicio->progresoDe($expediente),
        ]);
    }
```

por:

```php

        return response()->json([
            'requisito' => $this->servicio->presentarFila($fila->refresh()),
            ...$this->servicio->avanceDe($expediente),
        ]);
    }
```

En `app/Http/Controllers/ExpedienteController.php`, reemplazar (3 de 3):

```php
        return response()->json($this->servicio->presentar($expediente->refresh()));
    }

    public function historialRequisito(ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        return response()->json($this->historial->deFila($requisito));
```

por:

```php
        return response()->json($this->servicio->presentar($expediente->refresh()));
    }

    /**
     * Cierra un expediente de contratación. Con requisitos pendientes, sin `confirmar: true`
     * responde 422 con los pendientes por etapa y no cierra.
     */
    public function cerrar(Request $request, ProyectoExpediente $expediente): JsonResponse
    {
        $datos = $request->validate(
            ['confirmar' => ['sometimes', 'boolean']],
            ['confirmar.boolean' => 'Indica si confirmas el cierre con verdadero o falso.']
        );
        $this->servicio->cerrar($expediente, (bool) ($datos['confirmar'] ?? false), $request->user());

        return response()->json($this->servicio->presentar($expediente->refresh()));
    }

    public function historialRequisito(ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        return response()->json($this->historial->deFila($requisito));
```

En `app/Http/Controllers/NoAplicaRequisitoController.php`, reemplazar (1 de 1):

```php
    {
        return response()->json([
            'requisito' => $this->expedientes->presentarFila($fila->refresh()),
            'progreso' => $this->expedientes->progresoDe($expediente),
        ]);
    }
}
```

por:

```php
    {
        return response()->json([
            'requisito' => $this->expedientes->presentarFila($fila->refresh()),
            ...$this->expedientes->avanceDe($expediente),
        ]);
    }
}
```

En `app/Http/Controllers/RequisitoPropioController.php`, reemplazar (1 de 4):

```php
        'max' => 'En :attribute caben hasta :max caracteres.',
        'string' => 'Revisa :attribute: debe ser un texto.',
        'in' => 'Revisa :attribute: no es un valor válido.',
        'titulo.required' => 'Escribe el título del requisito.',
        'titulo.max' => 'El título admite hasta 500 caracteres.',
        'descripcion.max' => 'La descripción admite hasta 2000 caracteres.',
```

por:

```php
        'max' => 'En :attribute caben hasta :max caracteres.',
        'string' => 'Revisa :attribute: debe ser un texto.',
        'in' => 'Revisa :attribute: no es un valor válido.',
        'integer' => 'Revisa :attribute: debe ser un número entero.',
        'titulo.required' => 'Escribe el título del requisito.',
        'titulo.max' => 'El título admite hasta 500 caracteres.',
        'descripcion.max' => 'La descripción admite hasta 2000 caracteres.',
```

En `app/Http/Controllers/RequisitoPropioController.php`, reemplazar (2 de 4):

```php
        'descripcion' => 'la descripción',
        'categoria' => 'la categoría',
        'caracter' => 'el carácter',
    ];

    public function __construct(
```

por:

```php
        'descripcion' => 'la descripción',
        'categoria' => 'la categoría',
        'caracter' => 'el carácter',
        'etapa_propia_id' => 'la etapa',
    ];

    public function __construct(
```

En `app/Http/Controllers/RequisitoPropioController.php`, reemplazar (3 de 4):

```php

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
```

por:

```php

        return response()->json([
            'mensaje' => 'Requisito eliminado.',
            ...$this->expedientes->avanceDe($expediente),
        ]);
    }

    /**
     * Al editar, los campos que no vienen en el pedido no cambian; el título siempre se envía. La
     * etapa la exige el servicio, y solo en un expediente de contratación.
     */
    private function validar(Request $request): array
    {
        // Enteros de verdad: `integer` admitiría true como 1 o "1".
        $entero = fn (string $atributo, mixed $valor, \Closure $falla) => is_int($valor)
            || $falla('Revisa :attribute: debe ser un número entero.');

        return $request->validate([
            'titulo' => ['required', 'string', 'max:500'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'categoria' => ['sometimes', 'nullable', Rule::in(RequisitoCatalogo::CATEGORIAS)],
            'caracter' => ['sometimes', Rule::in(RequisitosPropios::CARACTERES)],
            'etapa_propia_id' => ['sometimes', 'nullable', 'bail', 'integer', $entero],
        ], self::MENSAJES, self::ATRIBUTOS);
    }
```

En `app/Http/Controllers/RequisitoPropioController.php`, reemplazar (4 de 4):

```php
    {
        return response()->json([
            'requisito' => $this->expedientes->presentarFila($fila->refresh()),
            'progreso' => $this->expedientes->progresoDe($expediente),
        ], $estado);
    }
}
```

por:

```php
    {
        return response()->json([
            'requisito' => $this->expedientes->presentarFila($fila->refresh()),
            ...$this->expedientes->avanceDe($expediente),
        ], $estado);
    }
}
```

En `app/Models/ProyectoExpediente.php`, reemplazar (1 de 1):

```php
class ProyectoExpediente extends Model
{
    // De viabilización, por proyecto: borrador -> radicado. De contratación, uno por proceso:
    // borrador -> cerrado.
    public const TIPO_VIABILIZACION = 'viabilizacion';
    public const TIPO_CONTRATACION = 'contratacion';
    public const TIPOS = [self::TIPO_VIABILIZACION, self::TIPO_CONTRATACION];
```

por:

```php
class ProyectoExpediente extends Model
{
    // De viabilización, por proyecto: borrador -> radicado. De contratación, uno por proceso:
    // borrador -> cerrado. El cierre guarda su fecha y su usuario en fecha_radicado y
    // usuario_radicado_id, los del acto que termina el trámite.
    public const TIPO_VIABILIZACION = 'viabilizacion';
    public const TIPO_CONTRATACION = 'contratacion';
    public const TIPOS = [self::TIPO_VIABILIZACION, self::TIPO_CONTRATACION];
```

En `app/Models/ProyectoRequisito.php`, reemplazar (1 de 1):

```php
            && $this->estado_aplicabilidad !== self::NO_APLICA;
    }

    /** Estado que ve el usuario: por_definir, pendiente, cumplido, no_aplica_pendiente o no_aplica. */
    public function estadoVisible(): string
    {
```

por:

```php
            && $this->estado_aplicabilidad !== self::NO_APLICA;
    }

    /** La fila es de las cumplidas que cuentan: la misma definición de scopeCumplidaQueCuenta. */
    public function cumplidaQueCuenta(): bool
    {
        return $this->cuenta() && $this->estado_aplicabilidad === self::APLICA && $this->cumplido;
    }

    /** Estado que ve el usuario: por_definir, pendiente, cumplido, no_aplica_pendiente o no_aplica. */
    public function estadoVisible(): string
    {
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (1 de 8):

```php
namespace App\Services\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\User;
use Closure;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Expediente de contratación de un proceso: qué plantillas se le ofrecen y cómo nace.
 *
 * Reglas:
 * - Se ofrecen las plantillas de contratación activas, con motor simple y versión publicada
 *   vigente, que cubren la modalidad del proceso (o todas), su fuente (o todas; sin fuente,
 *   solo las de todas las fuentes) y el sector del proyecto (o todos; sin sector, solo las de
 *   todos los sectores). Las de viabilización nunca (PlantillasExpediente es la de ellas).
 * - Un proceso tiene como máximo un expediente. Nace con una o varias plantillas compatibles,
 *   cada una con su versión vigente; la primera elegida es la principal, la que ordena el
 *   checklist primero. El checklist lo arma el motor del expediente (ExpedienteService).
```

por:

```php
namespace App\Services\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\ProyectoExpediente;
use App\Models\User;
use Closure;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Illuminate\Support\Collection;

/**
 * Expediente de contratación de un proceso: qué plantillas se le ofrecen y cómo nace.
 *
 * Reglas:
 * - Se ofrecen las plantillas que corresponden al proceso (PlantillasContratacion). Las de
 *   viabilización nunca (PlantillasExpediente es la de ellas).
 * - Un proceso tiene como máximo un expediente. Nace con una o varias plantillas compatibles,
 *   cada una con su versión vigente; la primera elegida es la principal, la que ordena el
 *   checklist primero. El checklist lo arma el motor del expediente (ExpedienteService).
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (2 de 8):

```php
 *   expediente, el expediente nace solo si hay exactamente una plantilla compatible; con cero
 *   o varias, se crea a mano eligiéndolas. Lo mismo para los procesos sin expediente de un
 *   proyecto al que le cambia el sector. Nunca interrumpe a quien guarda el proceso o el proyecto.
 * - Cambiar la modalidad o la fuente de un proceso con expediente no lo toca.
 */
final class ExpedientesContratacion
{
    public function __construct(private readonly ExpedienteService $expedientes)
    {
    }

    /**
```

por:

```php
 *   expediente, el expediente nace solo si hay exactamente una plantilla compatible; con cero
 *   o varias, se crea a mano eligiéndolas. Lo mismo para los procesos sin expediente de un
 *   proyecto al que le cambia el sector. Nunca interrumpe a quien guarda el proceso o el proyecto.
 * - Cambiar la modalidad o la fuente de un proceso con expediente no lo toca: el proceso avisa
 *   cuando ninguna de sus plantillas le sirve ya, y por qué (resumenDeProcesos).
 */
final class ExpedientesContratacion
{
    public function __construct(
        private readonly ExpedienteService $expedientes,
        private readonly PlantillasContratacion $plantillas,
    ) {
    }

    /**
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (3 de 8):

```php
        $sector = $proceso->proyectoRel?->sectorRel;
        $opcion = fn ($fila) => $fila ? ['id' => $fila->id, 'nombre' => $fila->nombre] : null;

        $plantillas = $this->compatibles($proceso)
            ->with(['versiones' => fn ($q) => $this->publicada($q)->withCount(['requisitos' => fn ($r) => $r->where('activo', true)])])
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();
```

por:

```php
        $sector = $proceso->proyectoRel?->sectorRel;
        $opcion = fn ($fila) => $fila ? ['id' => $fila->id, 'nombre' => $fila->nombre] : null;

        $plantillas = PlantillaChecklist::query()
            ->whereIn('id', $this->idsCompatibles($proceso))
            ->with(['versiones' => fn ($q) => $this->plantillas->publicada($q)->withCount(['requisitos' => fn ($r) => $r->where('activo', true)])])
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (4 de 8):

```php
    /** @return list<int> ids de las plantillas que hoy se ofrecen al proceso */
    public function idsCompatibles(ProcesoLicitacion $proceso): array
    {
        return $this->compatibles($proceso)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
```

por:

```php
    /** @return list<int> ids de las plantillas que hoy se ofrecen al proceso */
    public function idsCompatibles(ProcesoLicitacion $proceso): array
    {
        return $this->plantillas->idsCompatibles($proceso);
    }

    /**
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (5 de 8):

```php
    /**
     * Expedientes que nacen al cambiar el sector del proyecto: el de cada proceso suyo sin
     * expediente que ahora tiene exactamente una plantilla compatible. Como crearAutomatico,
     * nunca interrumpe a quien guarda el proyecto.
     *
     * @return int cuántos se crearon
     */
```

por:

```php
    /**
     * Expedientes que nacen al cambiar el sector del proyecto: el de cada proceso suyo sin
     * expediente que ahora tiene exactamente una plantilla compatible. Como crearAutomatico,
     * nunca interrumpe a quien guarda el proyecto. Decidir no cuesta más con más procesos.
     *
     * @return int cuántos se crearon
     */
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (6 de 8):

```php
        if ($proceso->expediente()->exists()) {
            return null;
        }
        $compatibles = $this->compatibles($proceso)->orderBy('id')->limit(2)->get();

        return $compatibles->count() === 1 ? $compatibles->first() : null;
    }

    /**
     * Procesos sin expediente con exactamente una plantilla compatible, con ella; todos, o solo
     * los del proyecto dado.
     *
     * @return Collection<int, array{proceso: ProcesoLicitacion, plantilla: PlantillaChecklist}>
     */
    public function faltantes(?int $proyectoId = null): Collection
    {
        return ProcesoLicitacion::query()
            ->when($proyectoId !== null, fn ($q) => $q->where('proyecto', $proyectoId))
            ->whereDoesntHave('expediente')
            ->with('proyectoRel:id,nombre,sector')
            ->orderBy('id')
            ->get()
            ->map(fn (ProcesoLicitacion $proceso) => ['proceso' => $proceso, 'plantilla' => $this->plantillaAutomatica($proceso)])
            ->filter(fn (array $fila) => $fila['plantilla'] !== null)
            ->values();
    }

    /**
     * Elimina los expedientes de contratación del proyecto, antes de eliminarlo: el proyecto
     * borra en cascada sus procesos y sus expedientes, pero un proceso con expediente no se
```

por:

```php
        if ($proceso->expediente()->exists()) {
            return null;
        }
        $compatibles = $this->idsCompatibles($proceso);

        return count($compatibles) === 1 ? PlantillaChecklist::find($compatibles[0]) : null;
    }

    /**
     * Procesos sin expediente con exactamente una plantilla compatible, con ella; todos, o solo
     * los del proyecto dado. Las consultas no crecen con los procesos.
     *
     * @return Collection<int, array{proceso: ProcesoLicitacion, plantilla: PlantillaChecklist}>
     */
    public function faltantes(?int $proyectoId = null): Collection
    {
        $procesos = ProcesoLicitacion::query()
            ->when($proyectoId !== null, fn ($q) => $q->where('proyecto', $proyectoId))
            ->whereDoesntHave('expediente')
            ->with('proyectoRel:id,nombre,sector')
            ->orderBy('id')
            ->get();
        $unicas = collect($this->plantillas->idsCompatiblesPorProceso($procesos))
            ->filter(fn (array $ids) => count($ids) === 1)
            ->map(fn (array $ids) => $ids[0]);
        $plantillas = PlantillaChecklist::query()->whereIn('id', $unicas->unique()->values())->get()->keyBy('id');

        return $procesos
            ->filter(fn (ProcesoLicitacion $proceso) => $unicas->has($proceso->id))
            ->map(fn (ProcesoLicitacion $proceso) => ['proceso' => $proceso, 'plantilla' => $plantillas[$unicas[$proceso->id]]])
            ->values();
    }

    /**
     * El expediente de cada proceso, resumido para la API del proceso: su estado, su avance y el
     * aviso si ninguna de sus plantillas le sirve ya (no corresponde a la modalidad, la fuente o
     * el sector, está inactiva o no tiene versión publicada). Las consultas no crecen con los
     * procesos.
     *
     * @param Collection<int, ProcesoLicitacion> $procesos
     * @return array<int, ?array{id: int, estado: string, progreso: array{total: int, cumplidos: int, porcentaje: int}, aviso_plantilla: ?string}> proceso_id => resumen; null sin expediente
     */
    public function resumenDeProcesos(Collection $procesos): array
    {
        $expedientes = $procesos->isEmpty() ? collect() : ProyectoExpediente::query()
            ->contratacion()
            ->whereIn('proceso_id', $procesos->pluck('id'))
            ->with('versionesPlantilla')
            ->withCount([
                'requisitos as requisitos_aplican' => fn ($q) => $q->cuenta(),
                'requisitos as requisitos_cumplidos' => fn ($q) => $q->cumplidaQueCuenta(),
            ])
            ->get()
            ->keyBy('proceso_id');
        $estados = $this->plantillas->estadoPorProceso(
            $procesos->filter(fn (ProcesoLicitacion $p) => $expedientes->has($p->id))->values(),
            $expedientes->flatMap(fn (ProyectoExpediente $e) => $e->versionesPlantilla->pluck('plantilla_id'))
                ->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        return $procesos->mapWithKeys(function (ProcesoLicitacion $proceso) use ($expedientes, $estados) {
            $expediente = $expedientes->get($proceso->id);
            if (! $expediente) {
                return [$proceso->id => null];
            }
            $razones = $expediente->versionesPlantilla
                ->map(fn ($v) => PlantillasContratacion::razon($estados[$proceso->id][(int) $v->plantilla_id]))
                ->all();

            return [$proceso->id => [
                'id' => $expediente->id,
                'estado' => $expediente->estado,
                'progreso' => $this->expedientes->progreso((int) $expediente->requisitos_aplican, (int) $expediente->requisitos_cumplidos),
                'aviso_plantilla' => $this->avisoPlantilla($razones),
            ]];
        })->all();
    }

    /**
     * El aviso cuando ninguna plantilla del expediente le sirve ya al proceso, con la razón: no
     * corresponde a su modalidad, su fuente o su sector; está inactiva; o ya no tiene versión
     * publicada. Null si alguna le sirve.
     *
     * @param list<?string> $razones las de PlantillasContratacion::razon(), una por plantilla
     */
    private function avisoPlantilla(array $razones): ?string
    {
        if ($razones === [] || in_array(null, $razones, true)) {
            return null;
        }
        $una = count($razones) === 1;
        $textos = [
            'no_corresponde' => [
                'La plantilla del expediente ya no corresponde a la modalidad, la fuente o el sector del proceso.',
                'Ninguna plantilla del expediente corresponde ya a la modalidad, la fuente o el sector del proceso.',
                'ya no corresponden a la modalidad, la fuente o el sector del proceso',
            ],
            'inactiva' => [
                'La plantilla del expediente está inactiva.',
                'Las plantillas del expediente están inactivas.',
                'están inactivas',
            ],
            'sin_publicada' => [
                'La plantilla del expediente ya no tiene una versión publicada.',
                'Las plantillas del expediente ya no tienen una versión publicada.',
                'ya no tienen una versión publicada',
            ],
        ];
        $distintas = array_values(array_intersect(array_keys($textos), $razones));
        if (count($distintas) === 1) {
            return $textos[$distintas[0]][$una ? 0 : 1];
        }
        $partes = array_map(fn (string $razon) => $textos[$razon][2], $distintas);
        $ultima = array_pop($partes);

        return 'Ninguna plantilla del expediente sigue vigente: unas ' . implode(', otras ', $partes) . " y otras {$ultima}.";
    }

    /**
     * Elimina los expedientes de contratación del proyecto, antes de eliminarlo: el proyecto
     * borra en cascada sus procesos y sus expedientes, pero un proceso con expediente no se
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (7 de 8):

```php
        }
        $compatibles = array_flip($this->idsCompatibles($proceso));
        $encontradas = PlantillaChecklist::query()->whereIn('id', $ids)
            ->with(['versiones' => fn ($q) => $this->publicada($q)])
            ->get()->keyBy('id');

        $errores = [];
```

por:

```php
        }
        $compatibles = array_flip($this->idsCompatibles($proceso));
        $encontradas = PlantillaChecklist::query()->whereIn('id', $ids)
            ->with(['versiones' => fn ($q) => $this->plantillas->publicada($q)])
            ->get()->keyBy('id');

        $errores = [];
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (8 de 8):

```php

        return ['versiones' => $versiones, 'principal_id' => $ids[0]];
    }

    private function compatibles(ProcesoLicitacion $proceso): Builder
    {
        $sector = Proyecto::query()->whereKey($proceso->proyecto)->value('sector');

        return PlantillaChecklist::query()
            ->where('uso', PlantillaChecklist::USO_CONTRATACION)
            ->where('motor', PlantillaChecklist::MOTOR_SIMPLE)
            ->where('activo', true)
            ->whereHas('versiones', fn ($q) => $this->publicada($q))
            ->where(fn (Builder $q) => $q->where('todas_modalidades', true)
                ->when($proceso->modalidad !== null, fn (Builder $m) => $m->orWhereHas('modalidades', fn (Builder $w) => $w->where('modalidades.id', $proceso->modalidad))))
            ->where(fn (Builder $q) => $q->where('todas_fuentes', true)
                ->when($proceso->fuente_id !== null, fn (Builder $f) => $f->orWhereHas('fuentes', fn (Builder $w) => $w->where('fuentes_financiacion.id', $proceso->fuente_id))))
            ->where(fn (Builder $q) => $q->where('todos_sectores', true)
                ->when($sector !== null, fn (Builder $s) => $s->orWhereHas('sectores', fn (Builder $w) => $w->where('sectores.id', $sector))));
    }

    /**
     * La versión vigente, si está publicada: un borrador nunca arma un expediente.
     *
     * @param Builder|HasMany $versiones consulta de versiones de plantilla
     */
    private function publicada(Builder|HasMany $versiones): Builder|HasMany
    {
        return $versiones->vigente()->where('estado', CatalogoVersion::PUBLICADA);
    }
}
```

por:

```php

        return ['versiones' => $versiones, 'principal_id' => $ids[0]];
    }
}
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (1 de 4):

```php
 * - Al registrarlo, o al cambiarle la modalidad o la fuente mientras no tenga expediente, se
 *   intenta crear su expediente (ExpedientesContratacion::crearAutomatico), ya guardado el
 *   proceso: nunca interrumpe el guardado.
 * - Todo queda en checklist_historial (entidad_tipo = proceso, sin expediente): al editar, solo
 *   lo que cambió; al eliminar, los datos para saber qué era.
 * - Los cambios sobre un proceso bloquean su fila: dos ediciones no se pisan en el historial.
```

por:

```php
 * - Al registrarlo, o al cambiarle la modalidad o la fuente mientras no tenga expediente, se
 *   intenta crear su expediente (ExpedientesContratacion::crearAutomatico), ya guardado el
 *   proceso: nunca interrumpe el guardado.
 * - presentar() trae el expediente del proceso resumido (estado, avance y aviso si su plantilla
 *   ya no le corresponde), o null sin expediente (ExpedientesContratacion::resumenDeProcesos).
 * - Todo queda en checklist_historial (entidad_tipo = proceso, sin expediente): al editar, solo
 *   lo que cambió; al eliminar, los datos para saber qué era.
 * - Los cambios sobre un proceso bloquean su fila: dos ediciones no se pisan en el historial.
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (2 de 4):

```php
    ) {
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

    /** @param array{objeto: string, modalidad_id: int, plataforma: string, codigo_proceso?: ?string, url_proceso?: ?string, entidad_contratante_id?: ?int, monto?: int|float|string|null, fuente_id?: ?int, responsable_id?: ?int, fecha_apertura?: ?string, fecha_cierre?: ?string, observaciones?: ?string} $datos */
```

por:

```php
    ) {
    }

    /**
     * Los procesos del proyecto en el orden en que se crearon, con la forma de presentar(). Las
     * consultas no crecen con los procesos, tampoco las de sus expedientes.
     */
    public function deProyecto(Proyecto $proyecto): array
    {
        $procesos = ProcesoLicitacion::query()
            ->where('proyecto', $proyecto->id)
            ->with(self::RELACIONES)
            ->withCount('contratos')
            ->orderBy('id')
            ->get();
        $expedientes = $this->expedientes->resumenDeProcesos($procesos);

        return $procesos->map(fn (ProcesoLicitacion $proceso) => $this->forma($proceso, $expedientes[$proceso->id]))->all();
    }

    public function presentar(ProcesoLicitacion $proceso): array
    {
        $proceso->load(self::RELACIONES)->loadCount('contratos');

        return $this->forma($proceso, $this->expedientes->resumenDeProcesos(collect([$proceso]))[$proceso->id]);
    }

    /** @param array{objeto: string, modalidad_id: int, plataforma: string, codigo_proceso?: ?string, url_proceso?: ?string, entidad_contratante_id?: ?int, monto?: int|float|string|null, fuente_id?: ?int, responsable_id?: ?int, fecha_apertura?: ?string, fecha_cierre?: ?string, observaciones?: ?string} $datos */
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (3 de 4):

```php
        return $columnas;
    }

    private function forma(ProcesoLicitacion $proceso): array
    {
        $modalidad = $proceso->modalidadRel;
        $entidad = $proceso->entidadContratante;
```

por:

```php
        return $columnas;
    }

    /** @param ?array $expediente el de ExpedientesContratacion::resumenDeProcesos(); null sin expediente */
    private function forma(ProcesoLicitacion $proceso, ?array $expediente): array
    {
        $modalidad = $proceso->modalidadRel;
        $entidad = $proceso->entidadContratante;
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (4 de 4):

```php
            'observaciones' => $proceso->observaciones,
            'estado' => $proceso->estado,
            'contratos' => (int) $proceso->contratos_count,
            'creado' => $proceso->created_at?->toIso8601String(),
            'actualizado' => $proceso->updated_at?->toIso8601String(),
            'usuario_creo' => $proceso->usuarioCreo?->name,
```

por:

```php
            'observaciones' => $proceso->observaciones,
            'estado' => $proceso->estado,
            'contratos' => (int) $proceso->contratos_count,
            'expediente' => $expediente,
            'creado' => $proceso->created_at?->toIso8601String(),
            'actualizado' => $proceso->updated_at?->toIso8601String(),
            'usuario_creo' => $proceso->usuarioCreo?->name,
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 14):

```php

namespace App\Services\Viabilizacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\Fase;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
```

por:

```php

namespace App\Services\Viabilizacion;

use App\Exceptions\CierreConPendientesException;
use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\Fase;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 14):

```php
use App\Models\RequisitoTipologia;
use App\Models\Responsable;
use App\Models\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
```

por:

```php
use App\Models\RequisitoTipologia;
use App\Models\Responsable;
use App\Models\User;
use App\Services\Contratacion\PlantillasContratacion;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (3 de 14):

```php
 * - Radicado, el expediente queda congelado: no se reconfigura ni se marca.
 * - Los expedientes del proyecto son los de viabilización (Proyecto::expedientes()). Uno de
 *   contratación (de un proceso) nace con crearDeProceso(), ya configurado: comparte el
 *   checklist, pero no se configura ni se radica.
 */
final class ExpedienteService
{
```

por:

```php
 * - Radicado, el expediente queda congelado: no se reconfigura ni se marca.
 * - Los expedientes del proyecto son los de viabilización (Proyecto::expedientes()). Uno de
 *   contratación (de un proceso) nace con crearDeProceso(), ya configurado: comparte el
 *   checklist, pero no se configura ni se radica. Sus filas llevan la etapa de contratación
 *   (la del requisito mostrado, o la elegida en un propio), con el avance por etapa, y se
 *   cierra con cerrar(): solo avisa de los pendientes. Cerrado, queda congelado como uno
 *   radicado. La fecha y el usuario del cierre se guardan en fecha_radicado y
 *   usuario_radicado_id: los del acto que termina el trámite del expediente.
 */
final class ExpedienteService
{
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (4 de 14):

```php
        'usuarioCumplido:id,name', 'usuarioNoAplica:id,name', 'usuarioAprobo:id,name', 'responsable:id,nombre,cargo',
    ];

    public function __construct(
        private readonly EvaluadorRequisitos $evaluador,
        private readonly HistorialChecklist $historial,
        private readonly PlantillasExpediente $plantillas,
    ) {
    }
```

por:

```php
        'usuarioCumplido:id,name', 'usuarioNoAplica:id,name', 'usuarioAprobo:id,name', 'responsable:id,nombre,cargo',
    ];

    // Solo en los de contratación: la etapa de cada fila.
    private const RELACIONES_ETAPA = ['requisito.etapa', 'origenes.etapa', 'etapaPropia'];

    public function __construct(
        private readonly EvaluadorRequisitos $evaluador,
        private readonly HistorialChecklist $historial,
        private readonly PlantillasExpediente $plantillas,
        private readonly PlantillasContratacion $contratacion,
    ) {
    }
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (5 de 14):

```php
        return $expediente;
    }

    public function eliminar(ProyectoExpediente $expediente, ?User $usuario = null): void
    {
        $this->exigirBorrador($expediente);
```

por:

```php
        return $expediente;
    }

    /**
     * Cierra un expediente de contratación. Solo avisa: con requisitos que cuentan sin cumplir,
     * sin `$confirmar` responde cuántos faltan y en qué etapas (CierreConPendientesException) y
     * no cierra; confirmado, cierra igual y el historial guarda lo que faltaba.
     */
    public function cerrar(ProyectoExpediente $expediente, bool $confirmar, ?User $usuario): ProyectoExpediente
    {
        $this->exigirContratacion($expediente);

        DB::transaction(function () use ($expediente, $confirmar, $usuario) {
            // Con la fila bloqueada, una marca o un propio que llega a la vez espera o ya cuenta.
            $this->bloquear($expediente);
            $this->exigirBorrador($expediente);

            $avance = $this->avancePorEtapaDe($expediente);
            $pendientes = $this->pendientes($avance);
            if ($pendientes['total'] > 0 && ! $confirmar) {
                $falta = $pendientes['total'] === 1 ? 'Falta 1 requisito' : "Faltan {$pendientes['total']} requisitos";
                throw new CierreConPendientesException("{$falta} por cumplir. Confirma para cerrar el expediente de todos modos.", $pendientes);
            }

            $expediente->update([
                'estado' => ProyectoExpediente::CERRADO,
                'fecha_radicado' => now(),
                'usuario_radicado_id' => $usuario?->id,
            ]);
            $this->historial->registrarExpediente($expediente, 'expediente_cerrado', [], [
                'requisitos' => array_sum(array_column($avance, 'total')),
                'cumplidos' => array_sum(array_column($avance, 'cumplidos')),
                'pendientes' => $pendientes,
            ], $usuario);
        });

        return $expediente;
    }

    /**
     * Lo que falta en cada etapa del avance, solo las etapas con algo pendiente.
     *
     * @param list<array{etapa: ?array, total: int, cumplidos: int, porcentaje: int}> $avance el de avancePorEtapa()
     * @return array{total: int, por_etapa: list<array{etapa: ?array, pendientes: int}>}
     */
    private function pendientes(array $avance): array
    {
        $porEtapa = [];
        foreach ($avance as $etapa) {
            if ($etapa['total'] > $etapa['cumplidos']) {
                $porEtapa[] = ['etapa' => $etapa['etapa'], 'pendientes' => $etapa['total'] - $etapa['cumplidos']];
            }
        }

        return ['total' => array_sum(array_column($porEtapa, 'pendientes')), 'por_etapa' => $porEtapa];
    }

    public function eliminar(ProyectoExpediente $expediente, ?User $usuario = null): void
    {
        $this->exigirBorrador($expediente);
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (6 de 14):

```php
        $posiciones = $this->posiciones($versiones);
        $avisos = ['avisos' => [], 'total' => 0];

        $filas = $expediente->requisitos()
            ->where('aplica', true)
            ->with(self::RELACIONES_FILA)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
        if ($versiones->count() > 1) {
            $avisos = $this->avisosDuplicados($filas, $posiciones);
        }

        return [
            ...$this->presentarCabecera($expediente, $versiones),
            'configuracion' => [
                'sectores' => $this->opciones($expediente->sectores),
                'tipos' => $expediente->tipos->map(fn ($t) => ['id' => $t->id, 'sector_id' => $t->sector_id, 'clave' => $t->clave, 'nombre' => $t->nombre])->values(),
                'componentes' => $this->opciones($expediente->componentes),
                'tipologias' => $this->opciones($expediente->tipologias),
            ],
            'progreso' => $this->progresoDe($expediente),
            'requisitos' => $filas->map(fn (ProyectoRequisito $f) => $this->presentarFila($f, $posiciones))->values(),
            // Los más parecidos, hasta MAX_AVISOS; el total dice cuántos hay en realidad.
            'avisos_duplicados' => $avisos['avisos'],
```

por:

```php
        $posiciones = $this->posiciones($versiones);
        $avisos = ['avisos' => [], 'total' => 0];

        $contratacion = $expediente->esContratacion();
        $filas = $expediente->requisitos()
            ->where('aplica', true)
            ->with($contratacion ? [...self::RELACIONES_FILA, ...self::RELACIONES_ETAPA] : self::RELACIONES_FILA)
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->each(fn (ProyectoRequisito $f) => $f->setRelation('expediente', $expediente));
        if ($versiones->count() > 1) {
            $avisos = $this->avisosDuplicados($filas, $posiciones);
        }

        return [
            ...$this->presentarCabecera($expediente, $versiones),
            // Un expediente de contratación no tiene configuración SGR: tiene etapas.
            ...($contratacion ? [] : ['configuracion' => [
                'sectores' => $this->opciones($expediente->sectores),
                'tipos' => $expediente->tipos->map(fn ($t) => ['id' => $t->id, 'sector_id' => $t->sector_id, 'clave' => $t->clave, 'nombre' => $t->nombre])->values(),
                'componentes' => $this->opciones($expediente->componentes),
                'tipologias' => $this->opciones($expediente->tipologias),
            ]]),
            'progreso' => $this->progresoDe($expediente),
            ...($contratacion ? ['avance_por_etapa' => $this->avancePorEtapa($filas, $posiciones)] : []),
            'requisitos' => $filas->map(fn (ProyectoRequisito $f) => $this->presentarFila($f, $posiciones))->values(),
            // Los más parecidos, hasta MAX_AVISOS; el total dice cuántos hay en realidad.
            'avisos_duplicados' => $avisos['avisos'],
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (7 de 14):

```php
    {
        $expediente->loadMissing(['proyecto:id,nombre,sector', 'usuarioRadico:id,name', 'usuarioCreo:id,name']);
        $versiones ??= $this->plantillas->versionesDe($expediente);
        $compatibles ??= $versiones->isEmpty() ? [] : $this->plantillas->idsCompatibles($expediente->proyecto);
        $nuevas ??= $this->plantillas->versionesNuevas($versiones);

        return [
            'id' => $expediente->id,
            'proyecto' => ['id' => $expediente->proyecto->id, 'nombre' => $expediente->proyecto->nombre],
            'plantillas' => $versiones->map(fn (CatalogoVersion $v) => [
```

por:

```php
    {
        $expediente->loadMissing(['proyecto:id,nombre,sector', 'usuarioRadico:id,name', 'usuarioCreo:id,name']);
        $versiones ??= $this->plantillas->versionesDe($expediente);
        $compatibles ??= match (true) {
            $versiones->isEmpty() => [],
            $expediente->esContratacion() => $this->contratacion->idsCompatibles($expediente->proceso),
            default => $this->plantillas->idsCompatibles($expediente->proyecto),
        };
        $nuevas ??= $this->plantillas->versionesNuevas($versiones);

        $cabecera = [
            'id' => $expediente->id,
            'proyecto' => ['id' => $expediente->proyecto->id, 'nombre' => $expediente->proyecto->nombre],
            'plantillas' => $versiones->map(fn (CatalogoVersion $v) => [
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (8 de 14):

```php
            'creado' => $expediente->created_at?->toIso8601String(),
            'actualizado' => $expediente->updated_at?->toIso8601String(),
        ];
    }

    public function progresoDe(ProyectoExpediente $expediente): array
```

por:

```php
            'creado' => $expediente->created_at?->toIso8601String(),
            'actualizado' => $expediente->updated_at?->toIso8601String(),
        ];

        return $expediente->esContratacion() ? $this->cabeceraDeContratacion($expediente, $cabecera) : $cabecera;
    }

    /**
     * La de un expediente de contratación: sin fase SGR, con su proceso, y con la fecha y el
     * usuario de radicación presentados como los del cierre, que es lo que guardan.
     */
    private function cabeceraDeContratacion(ProyectoExpediente $expediente, array $cabecera): array
    {
        $expediente->loadMissing('proceso.modalidadRel:id,nombre');
        $proceso = $expediente->proceso;
        $modalidad = $proceso->modalidadRel;
        // Por qué una plantilla ya no es compatible: no cubre la modalidad, la fuente o el sector
        // (`corresponde`, sin mirar si está activa o publicada), está inactiva o no tiene versión
        // publicada vigente. Así el aviso de la pantalla da la razón correcta.
        $ids = array_map(fn (array $p) => (int) $p['plantilla_id'], $cabecera['plantillas']);
        $estados = $ids === [] ? [] : $this->contratacion->estadoPorProceso(collect([$proceso]), $ids)[$proceso->id];

        return [
            ...Arr::except($cabecera, ['fase_sgr', 'fecha_radicado', 'usuario_radico']),
            'plantillas' => array_map(fn (array $p) => [...$p, ...$estados[(int) $p['plantilla_id']]], $cabecera['plantillas']),
            'tipo' => $expediente->tipo,
            'proceso' => [
                'id' => $proceso->id,
                'codigo_proceso' => $proceso->codigo_proceso,
                'objeto' => $proceso->objeto,
                'estado' => $proceso->estado,
                'modalidad' => $modalidad ? ['id' => $modalidad->id, 'nombre' => $modalidad->nombre] : null,
            ],
            'fecha_cierre' => $cabecera['fecha_radicado'],
            'usuario_cierre' => $cabecera['usuario_radico'],
        ];
    }

    public function progresoDe(ProyectoExpediente $expediente): array
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (9 de 14):

```php
        );
    }

    /**
     * Se muestra el requisito del primer origen (la principal primero); sin orígenes, el ancla.
     * Un requisito propio no tiene requisito de catálogo: trae sus textos en `propio`.
```

por:

```php
        );
    }

    /**
     * El avance que acompaña la respuesta de una acción sobre el checklist: el progreso y, en
     * uno de contratación, también el avance por etapa.
     */
    public function avanceDe(ProyectoExpediente $expediente): array
    {
        return [
            'progreso' => $this->progresoDe($expediente),
            ...($expediente->esContratacion() ? ['avance_por_etapa' => $this->avancePorEtapaDe($expediente)] : []),
        ];
    }

    /** @return list<array{etapa: ?array, total: int, cumplidos: int, porcentaje: int}> el de avancePorEtapa() */
    public function avancePorEtapaDe(ProyectoExpediente $expediente): array
    {
        $filas = $expediente->requisitos()
            ->where('aplica', true)
            ->with(['requisito', 'origenes.version:id,plantilla_id', ...self::RELACIONES_ETAPA])
            ->get();

        return $this->avancePorEtapa($filas, $this->posiciones($this->plantillas->versionesDe($expediente)));
    }

    /**
     * Avance de cada etapa con filas en el checklist, con la misma definición del progreso: cuentan
     * las que cuentan y, entre ellas, las cumplidas. En el orden del catálogo de etapas; las filas
     * sin etapa, al final. Espera cargadas las relaciones de etapa y `origenes.version`.
     *
     * @param Collection<int, ProyectoRequisito> $filas las que aplican
     * @return list<array{etapa: ?array{id: int, nombre: string, orden: int}, total: int, cumplidos: int, porcentaje: int}>
     */
    private function avancePorEtapa(Collection $filas, array $posiciones): array
    {
        $grupos = [];
        foreach ($filas as $fila) {
            $etapa = $this->etapaDe($fila, $posiciones);
            $clave = $etapa?->id ?? 0;
            $grupos[$clave] ??= ['etapa' => $etapa, 'total' => 0, 'cumplidos' => 0];
            $grupos[$clave]['total'] += $fila->cuenta() ? 1 : 0;
            $grupos[$clave]['cumplidos'] += $fila->cumplidaQueCuenta() ? 1 : 0;
        }

        return collect($grupos)
            ->sortBy(fn (array $g) => [$g['etapa'] === null ? 1 : 0, $g['etapa']?->orden ?? 0, $g['etapa']?->id ?? 0])
            ->map(fn (array $g) => ['etapa' => $this->presentarEtapa($g['etapa']), ...$this->progreso($g['total'], $g['cumplidos'])])
            ->values()
            ->all();
    }

    /** La de un propio es la que eligió; la de una fila de plantilla, la del requisito que muestra. */
    private function etapaDe(ProyectoRequisito $fila, array $posiciones): ?EtapaContratacion
    {
        return $fila->esPropio() ? $fila->etapaPropia : $this->requisitoMostrado($fila, $posiciones)?->etapa;
    }

    private function presentarEtapa(?EtapaContratacion $etapa): ?array
    {
        return $etapa ? ['id' => $etapa->id, 'nombre' => $etapa->nombre, 'orden' => $etapa->orden] : null;
    }

    /**
     * Se muestra el requisito del primer origen (la principal primero); sin orígenes, el ancla.
     * Un requisito propio no tiene requisito de catálogo: trae sus textos en `propio`.
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (10 de 14):

```php
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
            'estado' => $fila->estadoVisible(),
            'caracter' => $fila->caracter,
```

por:

```php
     */
    public function presentarFila(ProyectoRequisito $fila, ?array $posiciones = null): array
    {
        $contratacion = $fila->expediente->esContratacion();
        $fila->loadMissing($contratacion ? [...self::RELACIONES_FILA, ...self::RELACIONES_ETAPA] : self::RELACIONES_FILA);
        $posiciones ??= $this->posiciones($this->plantillas->versionesDe($fila->expediente));
        $origenes = $this->origenesOrdenados($fila, $posiciones);
        $r = $origenes->first() ?? $fila->requisito;
        $plantilla = $r?->version->plantilla;

        $presentada = [
            'id' => $fila->id,
            'estado' => $fila->estadoVisible(),
            'caracter' => $fila->caracter,
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (11 de 14):

```php
            ] : null,
            'categoria' => $fila->esPropio() ? $fila->categoria_propia : $r->categoria,
        ];
    }

    /**
```

por:

```php
            ] : null,
            'categoria' => $fila->esPropio() ? $fila->categoria_propia : $r->categoria,
        ];

        // La etapa solo en los de contratación: la respuesta de viabilización no cambia.
        return $contratacion ? [...$presentada, 'etapa' => $this->presentarEtapa($this->etapaDe($fila, $posiciones))] : $presentada;
    }

    /**
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (12 de 14):

```php
        $this->exigirChecklistEditable($expediente);
    }

    public function exigirBorrador(ProyectoExpediente $expediente): void
    {
        if (! $expediente->esBorrador()) {
            throw new ReglaNegocioException('El expediente está radicado: su checklist está congelado y no admite cambios.');
        }
```

por:

```php
        $this->exigirChecklistEditable($expediente);
    }

    /** Radicado (viabilización) o cerrado (contratación), el expediente está congelado. */
    public function exigirBorrador(ProyectoExpediente $expediente): void
    {
        if ($expediente->estado === ProyectoExpediente::CERRADO) {
            throw new ReglaNegocioException('El expediente está cerrado.');
        }
        if (! $expediente->esBorrador()) {
            throw new ReglaNegocioException('El expediente está radicado: su checklist está congelado y no admite cambios.');
        }
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (13 de 14):

```php
        }
    }

    private function exigirConfigurado(ProyectoExpediente $expediente): void
    {
        if (! $expediente->estaConfigurado()) {
```

por:

```php
        }
    }

    /** El cierre es solo de los expedientes de contratación. */
    private function exigirContratacion(ProyectoExpediente $expediente): void
    {
        if (! $expediente->esContratacion()) {
            throw new ReglaNegocioException('El expediente es de viabilización: se radica, no se cierra.');
        }
    }

    private function exigirConfigurado(ProyectoExpediente $expediente): void
    {
        if (! $expediente->estaConfigurado()) {
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (14 de 14):

```php
        }
    }

    private function progreso(int $total, int $cumplidos): array
    {
        return [
            'total' => $total,
```

por:

```php
        }
    }

    /** @return array{total: int, cumplidos: int, porcentaje: int} */
    public function progreso(int $total, int $cumplidos): array
    {
        return [
            'total' => $total,
```

En `app/Services/Viabilizacion/RequisitosPropios.php`, reemplazar (1 de 7):

```php
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Requisitos propios del proyecto: filas del checklist sin plantilla, con sus textos en la fila.
```

por:

```php
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\EtapaContratacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Requisitos propios del proyecto: filas del checklist sin plantilla, con sus textos en la fila.
```

En `app/Services/Viabilizacion/RequisitosPropios.php`, reemplazar (2 de 7):

```php
 * - Su carácter es obligatorio u opcional (por defecto, obligatorio). Nunca piden aprobación
 *   del "no aplica".
 * - Van al final del checklist, en el orden en que se agregaron.
 * - Solo se elimina uno que no esté cumplido ni tenga soporte.
 * - Todo queda en el historial de la fila.
 */
```

por:

```php
 * - Su carácter es obligatorio u opcional (por defecto, obligatorio). Nunca piden aprobación
 *   del "no aplica".
 * - Van al final del checklist, en el orden en que se agregaron.
 * - En un expediente de contratación llevan su etapa (etapa_propia_id), obligatoria: una etapa
 *   activa o la que ya tienen, aunque esté inactiva; sin el campo al editar, no cambia. En uno de
 *   viabilización no se acepta.
 * - Solo se elimina uno que no esté cumplido ni tenga soporte.
 * - Todo queda en el historial de la fila.
 */
```

En `app/Services/Viabilizacion/RequisitosPropios.php`, reemplazar (3 de 7):

```php
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
            // Con el expediente bloqueado: una radicación simultánea espera y ya cuenta este
```

por:

```php
        'caracter' => 'caracter',
    ];

    /** Los de un expediente de contratación llevan además su etapa. */
    private const CAMPOS_CONTRATACION = [...self::CAMPOS, 'etapa_propia_id' => 'etapa_propia_id'];

    public function __construct(
        private readonly ExpedienteService $expedientes,
        private readonly HistorialChecklist $historial,
    ) {
    }

    /** @param array{titulo: string, descripcion?: ?string, categoria?: ?string, caracter?: string, etapa_propia_id?: ?int} $datos */
    public function crear(ProyectoExpediente $expediente, array $datos, ?User $usuario): ProyectoRequisito
    {
        $this->expedientes->exigirChecklistEditable($expediente);
        $valores = $this->valores($expediente, [
            'descripcion' => null, 'categoria' => null, 'caracter' => RequisitoCatalogo::OBLIGATORIO, 'etapa_propia_id' => null, ...$datos,
        ]);
        $this->exigirEtapa($expediente, $datos, $valores, null);

        return DB::transaction(function () use ($expediente, $valores, $usuario) {
            // Con el expediente bloqueado: una radicación simultánea espera y ya cuenta este
```

En `app/Services/Viabilizacion/RequisitosPropios.php`, reemplazar (4 de 7):

```php
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
            // Primero el expediente y luego la fila, el orden de toda escritura del checklist.
            // Compara con el estado actual de la fila: dos ediciones no se pisan en el historial.
            $this->expedientes->bloquearChecklistEditable($expediente);
            $actual = ProyectoRequisito::query()->whereKey($fila->id)->lockForUpdate()->firstOrFail();
            $antes = $this->valoresDe($actual);
            $cambios = array_filter($valores, fn ($valor, string $campo) => $valor !== $antes[$campo], ARRAY_FILTER_USE_BOTH);
            if ($cambios === []) {
                return;
```

por:

```php
    /**
     * Cambia los campos que trae el pedido; los demás no cambian.
     *
     * @param array{titulo: string, descripcion?: ?string, categoria?: ?string, caracter?: string, etapa_propia_id?: ?int} $datos
     */
    public function editar(ProyectoExpediente $expediente, ProyectoRequisito $fila, array $datos, ?User $usuario): ProyectoRequisito
    {
        $this->exigirPropio($expediente, $fila, 'solo se editan los requisitos propios del proyecto.');
        $valores = $this->valores($expediente, $datos);

        DB::transaction(function () use ($expediente, $fila, $datos, $valores, $usuario) {
            // Primero el expediente y luego la fila, el orden de toda escritura del checklist.
            // Compara con el estado actual de la fila: dos ediciones no se pisan en el historial.
            $this->expedientes->bloquearChecklistEditable($expediente);
            $actual = ProyectoRequisito::query()->whereKey($fila->id)->lockForUpdate()->firstOrFail();
            // La etapa inactiva que ya tiene se decide sobre la fila bloqueada.
            $this->exigirEtapa($expediente, $datos, $valores, $actual);
            $antes = $this->valoresDe($expediente, $actual);
            $cambios = array_filter($valores, fn ($valor, string $campo) => $valor !== $antes[$campo], ARRAY_FILTER_USE_BOTH);
            if ($cambios === []) {
                return;
```

En `app/Services/Viabilizacion/RequisitosPropios.php`, reemplazar (5 de 7):

```php

            // El registro sobrevive a la fila, con sus textos.
            $this->historial->registrarFila($actual, 'requisito_propio_eliminado', [
                ...$this->valoresDe($actual),
                'observacion' => $actual->observacion,
                'responsable_id' => $actual->responsable_id,
                'responsable' => $actual->responsable?->nombre,
```

por:

```php

            // El registro sobrevive a la fila, con sus textos.
            $this->historial->registrarFila($actual, 'requisito_propio_eliminado', [
                ...$this->valoresDe($expediente, $actual),
                'observacion' => $actual->observacion,
                'responsable_id' => $actual->responsable_id,
                'responsable' => $actual->responsable?->nombre,
```

En `app/Services/Viabilizacion/RequisitosPropios.php`, reemplazar (6 de 7):

```php
        $this->expedientes->exigirEditable($expediente, $fila);
    }

    /** Valores del pedido en el orden de CAMPOS, con los textos recortados y los vacíos como null. */
    private function valores(array $datos): array
    {
        $valores = array_intersect_key(array_replace(self::CAMPOS, $datos), $datos, self::CAMPOS);
        foreach (['titulo', 'descripcion'] as $campo) {
            if (array_key_exists($campo, $valores)) {
                $texto = trim((string) $valores[$campo]);
```

por:

```php
        $this->expedientes->exigirEditable($expediente, $fila);
    }

    /**
     * Etapa del propio, solo en uno de contratación: obligatoria, y activa salvo que sea la que ya
     * tiene. Sin el campo al editar, no cambia. En uno de viabilización no se acepta.
     *
     * @param array $datos el pedido, para saber qué trae; $valores, lo que se guardaría
     */
    private function exigirEtapa(ProyectoExpediente $expediente, array $datos, array $valores, ?ProyectoRequisito $actual): void
    {
        if (! $expediente->esContratacion()) {
            if (($datos['etapa_propia_id'] ?? null) !== null) {
                throw ValidationException::withMessages(['etapa_propia_id' => 'Los requisitos propios de un expediente de viabilización no llevan etapa de contratación.']);
            }

            return;
        }
        if (! array_key_exists('etapa_propia_id', $valores)) {
            return;
        }

        $id = $valores['etapa_propia_id'];
        $etapa = $id !== null ? EtapaContratacion::find($id) : null;
        $error = match (true) {
            $id === null => 'Elige la etapa de contratación del requisito.',
            ! $etapa => "La etapa de contratación con id {$id} no existe.",
            ! $etapa->activo && $etapa->id !== $actual?->etapa_propia_id => "La etapa \"{$etapa->nombre}\" está inactiva.",
            default => null,
        };
        if ($error !== null) {
            throw ValidationException::withMessages(['etapa_propia_id' => $error]);
        }
    }

    /** @return array<string, string> campo de la API => columna, según el tipo del expediente */
    private function campos(ProyectoExpediente $expediente): array
    {
        return $expediente->esContratacion() ? self::CAMPOS_CONTRATACION : self::CAMPOS;
    }

    /** Valores del pedido en el orden de los campos, con los textos recortados y los vacíos como null. */
    private function valores(ProyectoExpediente $expediente, array $datos): array
    {
        $campos = $this->campos($expediente);
        $valores = array_intersect_key(array_replace($campos, $datos), $datos, $campos);
        foreach (['titulo', 'descripcion'] as $campo) {
            if (array_key_exists($campo, $valores)) {
                $texto = trim((string) $valores[$campo]);
```

En `app/Services/Viabilizacion/RequisitosPropios.php`, reemplazar (7 de 7):

```php
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
```

por:

```php
        return $valores;
    }

    /** @return array{titulo: ?string, descripcion: ?string, categoria: ?string, caracter: string, etapa_propia_id?: ?int} */
    private function valoresDe(ProyectoExpediente $expediente, ProyectoRequisito $fila): array
    {
        return array_map(fn (string $columna) => $fila->{$columna}, $this->campos($expediente));
    }

    private function columnas(array $valores): array
    {
        $columnas = [];
        foreach ($valores as $campo => $valor) {
            $columnas[self::CAMPOS_CONTRATACION[$campo]] = $valor;
        }

        return $columnas;
```

En `routes/viabilizacion.php`, reemplazar (1 de 1):

```php
        ->name('expedientes.configuracion.confirmar');
    Route::post('/expedientes/{expediente}/radicar', [ExpedienteController::class, 'radicar'])
        ->name('expedientes.radicar');
    Route::delete('/expedientes/{expediente}', [ExpedienteController::class, 'destroy'])
        ->name('expedientes.destroy');
```

por:

```php
        ->name('expedientes.configuracion.confirmar');
    Route::post('/expedientes/{expediente}/radicar', [ExpedienteController::class, 'radicar'])
        ->name('expedientes.radicar');
    // Solo los de contratación: se cierran, con aviso de los pendientes.
    Route::post('/expedientes/{expediente}/cerrar', [ExpedienteController::class, 'cerrar'])
        ->name('expedientes.cerrar');
    Route::delete('/expedientes/{expediente}', [ExpedienteController::class, 'destroy'])
        ->name('expedientes.destroy');
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/ExpedienteContratacionApiTest.php tests/Feature/Contratacion/ExpedienteProcesoApiTest.php tests/Feature/Contratacion/ProcesosContratacionApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1202 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Exceptions/CierreConPendientesException.php app/Services/Contratacion/PlantillasContratacion.php tests/Feature/Contratacion/ExpedienteContratacionApiTest.php app/Http/Controllers/ExpedienteController.php app/Http/Controllers/NoAplicaRequisitoController.php app/Http/Controllers/RequisitoPropioController.php app/Models/ProyectoExpediente.php app/Models/ProyectoRequisito.php app/Services/Contratacion/ExpedientesContratacion.php app/Services/Contratacion/ProcesosContratacion.php app/Services/Viabilizacion/ExpedienteService.php app/Services/Viabilizacion/RequisitosPropios.php routes/viabilizacion.php tests/Feature/Contratacion/ExpedienteProcesoApiTest.php tests/Feature/Contratacion/ProcesosContratacionApiTest.php
git commit -m "feat: stages, own requirements, progress by stage and closing with a warning"
```

---

### Tarea 5: Página del expediente de contratación

`/contratacion/:procesoId`: requisitos agrupados por etapa, avance, cierre con aviso, creación con las plantillas compatibles y solo lectura cuando está cerrado.

**Files:**
- Create: `resources/css/ExpedienteContratacion.css`
- Create: `resources/js/components/ExpedienteContratacion.jsx`
- Create: `resources/js/utils/expedienteContratacion.js`
- Test (create): `resources/js/utils/expedienteContratacion.test.js`
- Modify: `app/Services/Contratacion/ExpedientesContratacion.php`
- Modify: `resources/js/app.jsx`
- Modify: `resources/js/components/ExpedienteChecklist.jsx`
- Modify: `resources/js/utils/expedienteChecklist.js`
- Modify: `resources/js/utils/navegacion.js`
- Test: `resources/js/utils/navegacion.test.js`
- Test: `tests/Feature/Contratacion/ExpedienteProcesoApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `resources/js/utils/expedienteContratacion.test.js`:

```js
import { describe, it, expect } from 'vitest';
import {
    agruparPorEtapa,
    avisoIncompatiblesProceso,
    alternarPlantillaNueva,
    avanceEtapas,
    conAvance,
    datosPropioContratacion,
    errorDeProceso,
    esIdProceso,
    formularioPropioContratacion,
    htmlPendientesCierre,
    opcionesEtapaPropio,
    propioCompleto,
    seleccionInicial,
    textoEstadoExpediente,
    textoPendientesCierre,
    textoSinPlantillas,
} from './expedienteContratacion';
import { agruparChecklist, describirEvento } from './expedienteChecklist';

const etapa = (id, orden, nombre = `Etapa ${id}`) => ({ id, nombre, orden });

const fila = (id, et, cambios = {}) => ({
    id,
    etapa: et,
    estado: 'pendiente',
    cumplido: false,
    cuenta: true,
    caracter: 'obligatorio',
    requisito: { codigo: `R${id}`, texto: `Requisito ${id}` },
    plantilla: { id: 1, nombre: 'Licitación', motor: 'simple' },
    propio: null,
    ...cambios,
});

describe('esIdProceso', () => {
    it('acepta un entero positivo y rechaza lo demás', () => {
        expect(esIdProceso('12')).toBe(true);
        expect(esIdProceso('0')).toBe(false);
        expect(esIdProceso('abc')).toBe(false);
        expect(esIdProceso('1.5')).toBe(false);
        expect(esIdProceso(undefined)).toBe(false);
    });
});

describe('errorDeProceso', () => {
    it('un 404 no se reintenta', () => {
        expect(errorDeProceso({ response: { status: 404 } })).toEqual({ mensaje: 'El proceso no existe.', reintentable: false });
    });

    it('otro error se reintenta, con el mensaje de la API', () => {
        expect(errorDeProceso({ response: { status: 500 } })).toEqual({ mensaje: 'Error del servidor. Inténtalo de nuevo.', reintentable: true });
    });
});

describe('agruparPorEtapa', () => {
    it('agrupa por etapa en el orden del catálogo, con "Sin etapa" al final', () => {
        const a = etapa(5, 2, 'Selección');
        const b = etapa(3, 1, 'Planeación');
        const grupos = agruparPorEtapa([
            fila(1, null),
            fila(2, a),
            fila(3, b),
            fila(4, a, { estado: 'cumplido', cumplido: true }),
            fila(5, b, { propio: { titulo: 'Propio' }, requisito: null, plantilla: null }),
        ]);

        expect(grupos.map((g) => g.clave)).toEqual(['etapa-3', 'etapa-5', 'sin-etapa']);
        expect(grupos.map((g) => g.titulo)).toEqual(['1. Planeación', '2. Selección', 'Sin etapa']);
        // Dentro de cada etapa, en el orden en que llegan (los propios, que el backend pone al final, también).
        expect(grupos[0].filas.map((f) => f.id)).toEqual([3, 5]);
        expect(grupos[1].filas.map((f) => f.id)).toEqual([2, 4]);
        expect(grupos[2].filas.map((f) => f.id)).toEqual([1]);
    });

    it('a igual orden, desempata por id', () => {
        const grupos = agruparPorEtapa([fila(1, etapa(9, 1)), fila(2, etapa(4, 1))]);
        expect(grupos.map((g) => g.etapa.id)).toEqual([4, 9]);
    });

    it('cuenta solo lo que cuenta, como el backend', () => {
        const a = etapa(1, 1);
        const [grupo] = agruparPorEtapa([
            fila(1, a, { estado: 'cumplido', cumplido: true }),
            fila(2, a),
            fila(3, a, { cuenta: false, caracter: 'opcional', estado: 'cumplido', cumplido: true }),
            fila(4, a, { cuenta: false, estado: 'no_aplica' }),
        ]);
        expect(grupo.total).toBe(2);
        expect(grupo.cumplidos).toBe(1);
    });

    it('sin filas, sin grupos', () => {
        expect(agruparPorEtapa([])).toEqual([]);
        expect(agruparPorEtapa(undefined)).toEqual([]);
    });

    it('no cambia la agrupación de viabilización', () => {
        const filas = [fila(1, etapa(1, 1)), fila(2, null)];
        const bloques = agruparChecklist(filas);
        expect(bloques).toHaveLength(1);
        expect(bloques[0].tipo).toBe('simple');
    });
});

describe('avanceEtapas', () => {
    it('presenta el avance por etapa de la API, con título y clave', () => {
        expect(avanceEtapas([
            { etapa: etapa(3, 1, 'Planeación'), total: 4, cumplidos: 1, porcentaje: 25 },
            { etapa: null, total: 2, cumplidos: 2, porcentaje: 100 },
        ])).toEqual([
            { clave: 'etapa-3', titulo: '1. Planeación', total: 4, cumplidos: 1, porcentaje: 25 },
            { clave: 'sin-etapa', titulo: 'Sin etapa', total: 2, cumplidos: 2, porcentaje: 100 },
        ]);
    });

    it('sin avance, lista vacía', () => {
        expect(avanceEtapas(undefined)).toEqual([]);
    });
});

describe('conAvance', () => {
    const expediente = { id: 1, progreso: { total: 2, cumplidos: 0, porcentaje: 0 }, avance_por_etapa: [{ etapa: null, total: 2, cumplidos: 0, porcentaje: 0 }] };

    it('toma el progreso y el avance por etapa de una respuesta parcial', () => {
        const datos = { progreso: { total: 2, cumplidos: 1, porcentaje: 50 }, avance_por_etapa: [{ etapa: null, total: 2, cumplidos: 1, porcentaje: 50 }] };
        expect(conAvance(expediente, datos)).toEqual({ ...expediente, ...datos });
    });

    it('lo que la respuesta no trae queda como estaba', () => {
        expect(conAvance(expediente, {})).toEqual(expediente);
        expect(conAvance(expediente, undefined)).toEqual(expediente);
    });
});

describe('opcionesEtapaPropio', () => {
    const etapas = [
        { id: 2, nombre: 'Selección', orden: 2, activo: true },
        { id: 1, nombre: 'Planeación', orden: 1, activo: true },
        { id: 3, nombre: 'Vieja', orden: 3, activo: false },
    ];

    it('las activas en el orden del catálogo, numeradas', () => {
        expect(opcionesEtapaPropio(etapas, null)).toEqual([
            { valor: '1', etiqueta: '1. Planeación' },
            { valor: '2', etiqueta: '2. Selección' },
        ]);
    });

    it('más la que ya tiene el propio, aunque esté inactiva', () => {
        expect(opcionesEtapaPropio(etapas, { etapa: { id: 3, nombre: 'Vieja', orden: 3 } }).at(-1))
            .toEqual({ valor: '3', etiqueta: '3. Vieja (inactiva)' });
    });

    it('sin catálogo, solo la actual', () => {
        expect(opcionesEtapaPropio(null, { etapa: { id: 3, nombre: 'Vieja', orden: 3 } })).toEqual([{ valor: '3', etiqueta: '3. Vieja' }]);
        expect(opcionesEtapaPropio(null, null)).toEqual([]);
    });
});

describe('formulario de un propio de contratación', () => {
    it('vacío para uno nuevo, sin etapa', () => {
        expect(formularioPropioContratacion(null)).toEqual({ titulo: '', descripcion: '', categoria: '', caracter: 'obligatorio', etapa_propia_id: '' });
    });

    it('con la etapa de la fila', () => {
        const propio = fila(1, etapa(4, 1), { propio: { titulo: 'T', descripcion: null, categoria: 'tecnico' }, caracter: 'opcional' });
        expect(formularioPropioContratacion(propio)).toEqual({ titulo: 'T', descripcion: '', categoria: 'tecnico', caracter: 'opcional', etapa_propia_id: '4' });
    });

    it('lo que espera la API: la etapa como número', () => {
        expect(datosPropioContratacion({ titulo: ' T ', descripcion: '', categoria: '', caracter: 'obligatorio', etapa_propia_id: '4' }))
            .toEqual({ titulo: 'T', descripcion: null, categoria: null, caracter: 'obligatorio', etapa_propia_id: 4 });
        expect(datosPropioContratacion({ titulo: 'T', descripcion: '', categoria: '', caracter: 'obligatorio', etapa_propia_id: '' }).etapa_propia_id).toBeNull();
    });

    it('se puede guardar con título y etapa', () => {
        expect(propioCompleto({ titulo: 'T', etapa_propia_id: '4' })).toBe(true);
        expect(propioCompleto({ titulo: '  ', etapa_propia_id: '4' })).toBe(false);
        expect(propioCompleto({ titulo: 'T', etapa_propia_id: '' })).toBe(false);
    });
});

describe('pendientes al cerrar', () => {
    const pendientes = {
        total: 4,
        por_etapa: [
            { etapa: etapa(3, 1, 'Planeación'), pendientes: 3 },
            { etapa: null, pendientes: 1 },
        ],
    };

    it('cuántos faltan y en qué etapas', () => {
        expect(textoPendientesCierre(pendientes)).toEqual({
            titulo: 'Faltan 4 requisitos por cumplir',
            etapas: ['1. Planeación: 3 pendientes', 'Sin etapa: 1 pendiente'],
        });
    });

    it('en singular', () => {
        expect(textoPendientesCierre({ total: 1, por_etapa: [{ etapa: etapa(3, 1, 'Planeación'), pendientes: 1 }] }).titulo)
            .toBe('Falta 1 requisito por cumplir');
    });

    it('sin pendientes, null', () => {
        expect(textoPendientesCierre({ total: 0, por_etapa: [] })).toBeNull();
        expect(textoPendientesCierre(undefined)).toBeNull();
    });

    it('el html escapa los nombres de las etapas', () => {
        const html = htmlPendientesCierre({ total: 1, por_etapa: [{ etapa: etapa(3, 1, '<b>X</b>'), pendientes: 1 }] });
        expect(html).toContain('&lt;b&gt;X&lt;/b&gt;');
        expect(html).not.toContain('<b>X</b>');
        expect(html).toContain('Puedes cerrarlo de todos modos');
    });
});

describe('textoEstadoExpediente', () => {
    it('borrador', () => {
        expect(textoEstadoExpediente({ estado: 'borrador' })).toBe('Borrador');
    });

    it('cerrado, con fecha y usuario si los hay', () => {
        const texto = textoEstadoExpediente({ estado: 'cerrado', fecha_cierre: '2026-11-18T15:00:00Z', usuario_cierre: 'Ana' });
        expect(texto).toMatch(/^Cerrado el .+ por Ana$/);
        expect(textoEstadoExpediente({ estado: 'cerrado', fecha_cierre: null, usuario_cierre: null })).toBe('Cerrado');
    });
});

describe('selección de plantillas para el expediente nuevo', () => {
    it('con una sola plantilla compatible, ya va elegida; con varias, ninguna', () => {
        expect(seleccionInicial([{ id: 7 }])).toEqual([7]);
        expect(seleccionInicial([{ id: 7 }, { id: 8 }])).toEqual([]);
        expect(seleccionInicial(undefined)).toEqual([]);
    });

    it('la primera elegida es la principal: se conserva el orden en que se eligen', () => {
        let seleccion = [];
        seleccion = alternarPlantillaNueva(seleccion, 8);
        seleccion = alternarPlantillaNueva(seleccion, 7);
        expect(seleccion).toEqual([8, 7]);
        seleccion = alternarPlantillaNueva(seleccion, 8);
        expect(seleccion).toEqual([7]);
    });
});

describe('textoSinPlantillas', () => {
    it('dice por qué no hay plantillas', () => {
        expect(textoSinPlantillas).toBe('No hay plantillas de contratación publicadas que correspondan a la modalidad, la fuente y el sector de este proceso.');
    });
});

describe('historial', () => {
    it('describe el cierre del expediente', () => {
        expect(describirEvento({ evento: 'expediente_cerrado', antes: {}, despues: {} })).toBe('Cerró el expediente');
    });
});

describe('avisoIncompatiblesProceso', () => {
    it('nombra las plantillas que ya no corresponden al proceso', () => {
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: false }, { nombre: 'B', compatible: true }])).toEqual({
            titulo: 'Una plantilla ya no corresponde a la modalidad, la fuente o el sector del proceso',
            detalle: 'A. El expediente la conserva tal como está.',
        });
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: false }, { nombre: 'B', compatible: false }]).detalle)
            .toBe('A, B. El expediente las conserva tal como están.');
    });

    it('una inactiva que sigue cubriendo el proceso se avisa como inactiva', () => {
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: false, corresponde: true, activa: false, publicada: true }])).toEqual({
            titulo: 'Una plantilla del expediente está inactiva',
            detalle: 'A. El expediente la conserva tal como está.',
        });
        expect(avisoIncompatiblesProceso([
            { nombre: 'A', compatible: false, corresponde: true, activa: false, publicada: true },
            { nombre: 'B', compatible: false, corresponde: true, activa: false, publicada: false },
        ])?.titulo).toBe('Algunas plantillas del expediente están inactivas');
    });

    it('una sin versión publicada se avisa como tal', () => {
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: false, corresponde: true, activa: true, publicada: false }])?.titulo)
            .toBe('Una plantilla ya no tiene una versión publicada');
    });

    it('si no cubre el proceso, eso es lo que se avisa aunque además esté inactiva', () => {
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: false, corresponde: false, activa: false, publicada: true }])?.titulo)
            .toBe('Una plantilla ya no corresponde a la modalidad, la fuente o el sector del proceso');
    });

    it('con razones distintas, da la de cada una', () => {
        expect(avisoIncompatiblesProceso([
            { nombre: 'A', compatible: false, corresponde: false, activa: true, publicada: true },
            { nombre: 'B', compatible: false, corresponde: true, activa: false, publicada: true },
            { nombre: 'C', compatible: true, corresponde: true, activa: true, publicada: true },
        ])).toEqual({
            titulo: 'Algunas plantillas ya no sirven al proceso',
            detalle: 'A (ya no corresponde a la modalidad, la fuente o el sector), B (inactiva). El expediente las conserva tal como están.',
        });
    });

    it('sin incompatibles, null', () => {
        expect(avisoIncompatiblesProceso([{ nombre: 'A', compatible: true }])).toBeNull();
        expect(avisoIncompatiblesProceso(undefined)).toBeNull();
    });
});
```

En `resources/js/utils/navegacion.test.js`, reemplazar (1 de 2):

```js
import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import { DESTINOS, GRUPO, RUTA_CONTRATOS, RUTA_PLANTILLAS, SECCIONES_PARAMETROS, buscarEnPaleta, destinoActivo, destinosPara, puedeVer, rutaFichaProyecto, seccionDesdeSlug, tituloDeRuta } from './navegacion';
import { ICONOS } from './iconosNavegacion';

const proyectos = [
```

por:

```js
import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import { DESTINOS, GRUPO, RUTA_CONTRATOS, RUTA_PLANTILLAS, SECCIONES_PARAMETROS, buscarEnPaleta, destinoActivo, destinosPara, puedeVer, rutaExpedienteContratacion, rutaFichaProyecto, seccionDesdeSlug, tituloDeRuta } from './navegacion';
import { ICONOS } from './iconosNavegacion';

const proyectos = [
```

En `resources/js/utils/navegacion.test.js`, reemplazar (2 de 2):

```js
        expect(destinoActivo('/proyectos', '')).toBeNull();
        expect(destinoActivo('/proyectosx/4', '')).toBeNull();
    });
});

describe('rutaFichaProyecto', () => {
```

por:

```js
        expect(destinoActivo('/proyectos', '')).toBeNull();
        expect(destinoActivo('/proyectosx/4', '')).toBeNull();
    });

    it('el expediente de contratación de un proceso pertenece a Proyectos', () => {
        expect(destinoActivo('/contratacion/9', '')).toBe('proyectos');
        expect(tituloDeRuta('/contratacion/9', '')).toBe('Proyectos');
        expect(destinoActivo('/contratacion', '')).toBeNull();
        expect(destinoActivo('/contratacionx/9', '')).toBeNull();
    });
});

describe('rutaExpedienteContratacion', () => {
    it('el expediente del proceso, siempre con id', () => {
        expect(rutaExpedienteContratacion(9)).toBe('/contratacion/9');
    });
});

describe('rutaFichaProyecto', () => {
```

En `tests/Feature/Contratacion/ExpedienteProcesoApiTest.php`, reemplazar (1 de 2):

```php
    public function test_la_respuesta_describe_el_proceso_y_cada_plantilla(): void
    {
        $plantilla = $this->plantilla('Licitación pública', ['todas_fuentes' => false, 'fuente_ids' => [$this->sgr]]);
        $proceso = $this->proceso(['fuente_id' => $this->sgr]);
        $version = $plantilla->versionVigente();

        $this->como(Rol::Consulta)->getJson("/GestPro/procesos/{$proceso->id}/plantillas-compatibles")
```

por:

```php
    public function test_la_respuesta_describe_el_proceso_y_cada_plantilla(): void
    {
        $plantilla = $this->plantilla('Licitación pública', ['todas_fuentes' => false, 'fuente_ids' => [$this->sgr]]);
        $proceso = $this->proceso(['fuente_id' => $this->sgr, 'codigo_proceso' => 'LP-001-2026']);
        $version = $plantilla->versionVigente();

        $this->como(Rol::Consulta)->getJson("/GestPro/procesos/{$proceso->id}/plantillas-compatibles")
```

En `tests/Feature/Contratacion/ExpedienteProcesoApiTest.php`, reemplazar (2 de 2):

```php
            ->assertExactJson([
                'proceso' => [
                    'id' => $proceso->id,
                    'modalidad' => ['id' => $this->modalidad(), 'nombre' => 'Licitación pública'],
                    'fuente' => ['id' => $this->sgr, 'nombre' => FuenteFinanciacion::find($this->sgr)->nombre],
                    'sector' => ['id' => $this->agua, 'nombre' => 'Agua Potable'],
```

por:

```php
            ->assertExactJson([
                'proceso' => [
                    'id' => $proceso->id,
                    'codigo_proceso' => 'LP-001-2026',
                    'objeto' => 'Construcción del acueducto',
                    'estado' => 'preparacion',
                    'proyecto' => ['id' => $this->proyecto->id, 'nombre' => $this->proyecto->nombre],
                    'modalidad' => ['id' => $this->modalidad(), 'nombre' => 'Licitación pública'],
                    'fuente' => ['id' => $this->sgr, 'nombre' => FuenteFinanciacion::find($this->sgr)->nombre],
                    'sector' => ['id' => $this->agua, 'nombre' => 'Agua Potable'],
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/ExpedienteProcesoApiTest.php`
Expected: FALLA 1 y pasan 31: `plantillas-compatibles` todavía no trae el código, el objeto, el estado ni el proyecto del proceso.

Run: `npx vitest run resources/js/utils/expedienteContratacion.test.js resources/js/utils/navegacion.test.js`
Expected: FALLAN los 2 archivos (2 pruebas y la carga de `expedienteContratacion.test.js`): no existen las utilidades de la página ni la ruta del expediente de contratación en la navegación.

- [ ] **Paso 3: Implementar**

Crear `resources/css/ExpedienteContratacion.css`:

```css
/* Expediente de contratación de un proceso (/contratacion/:procesoId). Usa los estilos del
   expediente de viabilización (exv-*); aquí solo lo propio: avance por etapa y elegir plantillas. */

.exc-volver { margin-bottom: 1rem; }

.exc-etapas {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr));
    gap: .75rem 1.5rem;
    margin: 0 0 1rem;
    padding: .9rem 1rem;
    background: var(--exv-tarjeta);
    border: 1px solid var(--exv-linea-suave);
    border-radius: var(--exv-radio);
}
.exc-etapas .exv-medidor-top b { font-size: .88rem; }

.exc-crear { text-align: left; }
.exc-plantillas {
    display: grid;
    gap: .5rem;
    margin: 0 0 1rem;
    padding: 0;
    border: 0;
}
.exc-plantillas legend { font-size: .78rem; letter-spacing: .08em; text-transform: uppercase; color: var(--exv-tinta-tenue); margin-bottom: .4rem; }
.exc-plantillas label { display: flex; gap: .6rem; align-items: flex-start; cursor: pointer; font-size: .9rem; }
.exc-plantillas label small { display: block; color: var(--exv-tinta-suave); }
.exc-principal { margin-left: .5rem; }
```

Crear `resources/js/components/ExpedienteContratacion.jsx`:

```jsx
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import Swal from 'sweetalert2';
import { faArrowLeft, faArrowsRotate, faClockRotateLeft, faLock, faPlus } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { ChecklistPorEtapa } from './ExpedienteChecklist';
import ExpedienteHistorial from './ExpedienteHistorial';
import useOcupado from './useOcupado';
import {
    contarOpcionales, motivoNoEliminarPropio, plantillasConVersion, requiereAprobacionNoAplica, textoOpcionales,
} from '../utils/expedienteChecklist';
import {
    alternarPlantillaNueva, avanceEtapas, avisoIncompatiblesProceso, conAvance, datosPropioContratacion, errorDeProceso,
    esIdProceso, htmlPendientesCierre, seleccionInicial, textoEstadoExpediente, textoSinPlantillas,
} from '../utils/expedienteContratacion';
import { etiquetaEstadoProceso, numeroProceso, tonoEstado } from '../utils/contratacion';
import { RUTA_PLANTILLAS, rutaFichaProyecto } from '../utils/navegacion';
import { avisarError } from '../utils/avisosPlantillas';
import {
    etiquetaAplicarVersion, htmlVistaPrevia, plantillasConVersionNueva, textoVersionNueva,
} from '../utils/versionesExpediente';
import '../../css/ExpedienteViabilizacion.css';
import '../../css/Contratacion.css';
import '../../css/ExpedienteContratacion.css';

const ROLES_EDICION = ['Administrador', 'Gestor'];
const ID_HISTORIAL = 'exc-historial-expediente';

/** Barra de avance con sus cifras: "3 / 8 requisitos cumplidos" y el porcentaje, por separado. */
const Medidor = ({ titulo, cumplidos, total, porcentaje, nota }) => (
    <div className="exv-medidor">
        <div className="exv-medidor-top">
            <b>
                {titulo && <>{titulo} · </>}
                <span>{cumplidos}</span> / {total} {titulo ? 'cumplidos' : 'requisitos cumplidos'}
                {nota && <small className="exv-medidor-opcionales"> · {nota}</small>}
            </b>
            <em>{porcentaje}%</em>
        </div>
        <div
            className="exv-pista"
            role="progressbar"
            aria-label={titulo ? `Avance de ${titulo}` : 'Avance del expediente'}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={porcentaje}
        >
            <div className={`exv-relleno${porcentaje === 100 && total > 0 ? ' completo' : ''}`} style={{ width: `${porcentaje}%` }} />
        </div>
    </div>
);

/** Página con el botón de volver: la usan la carga, los errores y el expediente. */
const Pagina = ({ children }) => {
    const navigate = useNavigate();
    return (
        <div className="exv-pagina">
            <div className="exv-contenido">
                <button type="button" className="exv-btn exv-no-imprimir exc-volver" onClick={() => navigate(-1)}>
                    <FontAwesomeIcon icon={faArrowLeft} /> Volver
                </button>
                {children}
            </div>
        </div>
    );
};

/** Sin expediente: las plantillas compatibles para elegir (la primera elegida es la principal) y crearlo. */
const SinExpediente = ({ plantillas, puedeEditar, esAdministrador, seleccion, onCambiar, ocupado, onCrear }) => {
    if (plantillas.length === 0) {
        return (
            <div className="exv-vacio">
                <b>Este proceso aún no tiene expediente.</b>
                <p>{textoSinPlantillas}</p>
                {esAdministrador && (
                    <p><Link to={RUTA_PLANTILLAS} className="ct-enlace">Ir a Plantillas de checklist</Link></p>
                )}
            </div>
        );
    }
    return (
        <div className="exv-vacio exc-crear">
            <b>Este proceso aún no tiene expediente.</b>
            <p>
                {puedeEditar
                    ? 'Elige las plantillas de su checklist. La primera que elijas es la principal.'
                    : 'Un Administrador o Gestor debe crearlo. Estas son las plantillas que le corresponden:'}
            </p>
            <fieldset className="exc-plantillas" disabled={!puedeEditar || ocupado}>
                <legend>Plantillas compatibles</legend>
                {plantillas.map((p) => {
                    const posicion = seleccion.indexOf(p.id);
                    return (
                        <label key={p.id}>
                            <input type="checkbox" checked={posicion !== -1} onChange={() => onCambiar(alternarPlantillaNueva(seleccion, p.id))} />
                            <span>
                                <b>{p.nombre}</b> (v{p.version.numero}) · {p.requisitos === 1 ? '1 requisito' : `${p.requisitos} requisitos`}
                                {posicion === 0 && seleccion.length > 1 && <span className="exv-estado exc-principal">Principal</span>}
                                {p.descripcion && <small>{p.descripcion}</small>}
                            </span>
                        </label>
                    );
                })}
            </fieldset>
            {puedeEditar && (
                <button type="button" className="exv-btn exv-btn-primario" onClick={onCrear} disabled={ocupado || seleccion.length === 0}>
                    <FontAwesomeIcon icon={faPlus} /> Crear expediente
                </button>
            )}
        </div>
    );
};

/** El expediente de un proceso con id válido. */
const Expediente = ({ procesoId, user }) => {
    // GET /procesos/{p}/plantillas-compatibles: el proceso, su expediente_id y las plantillas que le corresponden.
    const [disponibles, setDisponibles] = useState(null);
    const [expediente, setExpediente] = useState(null);
    // { mensaje, reintentable }. Con la página ya pintada, se avisa encima sin quitarla.
    const [error, setError] = useState(null);
    const [seleccion, setSeleccion] = useState(null);
    const [etapas, setEtapas] = useState(null);
    const [responsables, setResponsables] = useState([]);
    const [historialAbierto, setHistorialAbierto] = useState(false);
    // Sube con cada cambio que el servidor ya registró: el historial abierto se vuelve a pedir.
    const [revision, setRevision] = useState(0);
    const nuevaRevision = useCallback(() => setRevision((r) => r + 1), []);
    const [ocupado, ejecutar] = useOcupado();

    const puedeEditar = ROLES_EDICION.includes(user?.rol);
    const editable = puedeEditar && expediente?.estado === 'borrador';

    // Turno de las cargas completas: solo vale la última, y ninguna que empezó antes de que una
    // acción pusiera el expediente completo. Desmontarse también lo sube.
    const turno = useRef(0);
    const montado = useRef(true);
    useEffect(() => {
        montado.current = true;
        return () => { montado.current = false; turno.current += 1; };
    }, []);

    const ponerExpediente = useCallback((data) => {
        turno.current += 1;
        setExpediente(data);
        nuevaRevision();
    }, [nuevaRevision]);

    const cargar = useCallback(async () => {
        const numero = ++turno.current;
        const alDia = () => numero === turno.current;
        setError(null);
        let datos;
        try {
            ({ data: datos } = await axios.get(`/procesos/${procesoId}/plantillas-compatibles`));
        } catch (e) {
            if (alDia()) setError(errorDeProceso(e));
            return;
        }
        if (!alDia()) return;
        let datosExpediente = null;
        if (datos.expediente_id) {
            try {
                ({ data: datosExpediente } = await axios.get(`/expedientes/${datos.expediente_id}`));
            } catch (e) {
                // Un 404 aquí es del expediente (otra persona lo eliminó), no del proceso: se reintenta.
                if (alDia()) setError(e?.response?.status === 404 ? { mensaje: 'El expediente del proceso ya no existe.', reintentable: true } : errorDeProceso(e));
                return;
            }
            if (!alDia()) return;
        }
        setDisponibles(datos);
        setExpediente(datosExpediente);
        // Lo elegido se conserva entre recargas; la primera vez, si hay una sola plantilla, va elegida.
        setSeleccion((prev) => prev ?? seleccionInicial(datos.plantillas));
        nuevaRevision();
    }, [procesoId, nuevaRevision]);

    useEffect(() => { cargar(); }, [cargar]);

    // Para el selector de etapa de los propios. Sin la lista, solo se ofrece la que el propio ya tiene.
    useEffect(() => {
        let vigente = true;
        axios.get('/etapasContratacion')
            .then(({ data }) => { if (vigente) setEtapas(Array.isArray(data) ? data : []); })
            .catch(() => {});
        return () => { vigente = false; };
    }, []);

    // Sin la lista, el checklist igual funciona: solo no se puede elegir responsable.
    useEffect(() => {
        let vigente = true;
        axios.get('/responsables')
            .then(({ data }) => { if (vigente) setResponsables(data); })
            .catch(() => { if (vigente) setResponsables([]); });
        return () => { vigente = false; };
    }, []);

    // Recarga tras un error de una acción: la fila o el expediente pudieron cambiar en el servidor.
    const recargar = () => { cargar(); };

    // Los avisos de duplicados cambian cuando una fila deja o vuelve a aplicar: se piden aparte.
    // Solo vale el último GET, y ninguno anterior a la última vez que se puso el expediente completo.
    const peticionAvisos = useRef(0);
    const refrescarAvisos = async (id) => {
        const numero = ++peticionAvisos.current;
        const base = turno.current;
        try {
            const { data } = await axios.get(`/expedientes/${id}`);
            if (numero !== peticionAvisos.current || base !== turno.current) return;
            setExpediente((prev) => (prev?.id === id
                ? { ...prev, avisos_duplicados: data.avisos_duplicados, avisos_duplicados_total: data.avisos_duplicados_total }
                : prev));
        } catch {
            // En silencio: los avisos quedan como estaban hasta la próxima carga.
        }
    };

    // pintarFila solo la muestra (cambio optimista o su reversión); las demás ponen lo que
    // respondió el servidor, que ya quedó en el historial. `datos` trae progreso y avance por etapa.
    const pintarFila = (fila, datos) => setExpediente((prev) => (
        prev?.requisitos.some((f) => f.id === fila.id)
            ? { ...conAvance(prev, datos), requisitos: prev.requisitos.map((f) => (f.id === fila.id ? fila : f)) }
            : prev
    ));

    const reemplazarFila = (fila, datos) => {
        pintarFila(fila, datos);
        nuevaRevision();
    };

    // Un propio nuevo va al final: el backend ordena los propios después de toda plantilla, y la
    // agrupación lo pone en su etapa.
    const agregarFila = (expedienteId, fila, datos) => {
        setExpediente((prev) => (prev?.id === expedienteId ? { ...conAvance(prev, datos), requisitos: [...prev.requisitos, fila] } : prev));
        nuevaRevision();
    };

    const quitarFila = (fila, datos) => {
        setExpediente((prev) => (
            prev?.requisitos.some((f) => f.id === fila.id)
                ? { ...conAvance(prev, datos), requisitos: prev.requisitos.filter((f) => f.id !== fila.id) }
                : prev
        ));
        nuevaRevision();
    };

    const urlFila = (fila) => `/expedientes/${expediente.id}/requisitos/${fila.id}`;

    const marcar = async (fila, cumplido) => {
        pintarFila({ ...fila, cumplido, estado: cumplido ? 'cumplido' : 'pendiente' });
        try {
            const { data } = await axios.patch(urlFila(fila), { cumplido });
            reemplazarFila(data.requisito, data);
        } catch (e) {
            pintarFila(fila);
            avisarError('No se pudo actualizar el requisito', e);
        }
    };

    const guardarObservacion = async (fila, observacion) => {
        try {
            const { data } = await axios.patch(urlFila(fila), { observacion });
            reemplazarFila(data.requisito, data);
        } catch (e) {
            avisarError('No se pudo guardar la observación', e);
        }
    };

    const asignarResponsable = async (fila, responsableId) => {
        pintarFila({ ...fila, responsable: responsables.find((r) => r.id === responsableId) ?? null });
        try {
            const { data } = await axios.patch(urlFila(fila), { responsable_id: responsableId });
            reemplazarFila(data.requisito, data);
        } catch (e) {
            pintarFila(fila);
            avisarError('No se pudo asignar el responsable', e);
        }
    };

    const subirSoporte = async (fila, archivo) => {
        const formulario = new FormData();
        formulario.append('archivo', archivo);
        try {
            const { data } = await axios.post(`${urlFila(fila)}/soporte`, formulario);
            reemplazarFila(data.requisito, data);
        } catch (e) {
            avisarError('No se pudo adjuntar el soporte', e);
        }
    };

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
        } catch (e) {
            avisarError('No se pudo descargar el soporte', e);
        }
    };

    const descargarSoporte = (fila) => descargar(`${urlFila(fila)}/soporte`, fila.soporte.nombre);

    const descargarSoporteHistorial = (entrada) =>
        descargar(`/expedientes/${expediente.id}/historial/${entrada.id}/soporte`, entrada.soporte.nombre);

    const cargarHistorial = useCallback(
        async (fila) => (await axios.get(`/expedientes/${expediente.id}/requisitos/${fila.id}/historial`)).data,
        [expediente?.id]
    );

    const eliminarSoporte = async (fila) => {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            title: '¿Eliminar este soporte?',
            text: fila.soporte.nombre,
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        try {
            const { data } = await axios.delete(`${urlFila(fila)}/soporte`);
            reemplazarFila(data.requisito, data);
        } catch (e) {
            avisarError('No se pudo eliminar el soporte', e);
        }
    };

    // Los propios no entran en los avisos de duplicados, y con una sola plantilla no hay avisos.
    const accionFila = async (peticion, titulo) => {
        const { id, plantillas } = expediente;
        try {
            const { data } = await peticion();
            reemplazarFila(data.requisito, data);
            if (plantillas.length > 1) refrescarAvisos(id);
        } catch (e) {
            avisarError(titulo, e);
            recargar();
        }
    };

    const marcarNoAplica = async (fila) => {
        const { isConfirmed, value } = await Swal.fire({
            title: 'Este requisito no aplica',
            text: requiereAprobacionNoAplica(fila) ? 'Otra persona deberá aprobarlo.' : undefined,
            input: 'textarea',
            inputLabel: 'Justificación',
            inputPlaceholder: 'Explica por qué no aplica a este proceso',
            inputAttributes: { maxlength: '2000' },
            inputValidator: (texto) => (!texto?.trim() ? 'Escribe la justificación.' : undefined),
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        await accionFila(() => axios.post(`${urlFila(fila)}/no-aplica`, { justificacion: value }), 'No se pudo marcar como no aplica');
    };

    const retirarNoAplica = async (fila) => {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            title: '¿Retirar el "no aplica"?',
            text: 'El requisito volverá al estado que tenía antes de marcarlo: pendiente o por definir. Si ya estaba aprobado, necesitará otra aprobación.',
            showCancelButton: true,
            confirmButtonText: 'Sí, retirar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        await accionFila(() => axios.delete(`${urlFila(fila)}/no-aplica`), 'No se pudo retirar el "no aplica"');
    };

    const aprobarNoAplica = (fila) => ejecutar(() =>
        accionFila(() => axios.post(`${urlFila(fila)}/no-aplica/aprobar`), 'No se pudo aprobar el "no aplica"'));

    const rechazarNoAplica = async (fila) => {
        const { isConfirmed, value } = await Swal.fire({
            title: '¿Rechazar el "no aplica"?',
            text: 'El requisito volverá al estado que tenía antes de marcarlo: pendiente o por definir.',
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

    // Pasar de "por definir" a "aplica" no tiene vuelta atrás: después solo cabe un "no aplica" justificado.
    const decidirAplica = (fila) => ejecutar(async () => {
        const { isConfirmed } = await Swal.fire({
            icon: 'question',
            titleText: '¿Este requisito aplica al proceso?',
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
        const datos = datosPropioContratacion(formulario);
        try {
            if (fila) {
                const { data } = await axios.put(`${urlPropios()}/${fila.id}`, datos);
                reemplazarFila(data.requisito, data);
            } else {
                const { data } = await axios.post(urlPropios(), datos);
                agregarFila(expediente.id, data.requisito, data);
            }
            return true;
        } catch (e) {
            avisarError(fila ? 'No se pudo guardar el requisito propio' : 'No se pudo agregar el requisito propio', e);
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
            quitarFila(fila, data);
        } catch (e) {
            avisarError('No se pudo eliminar el requisito propio', e);
            recargar();
        }
    });

    // Primero la vista previa, que no cambia nada; se aplica la versión que se mostró.
    const aplicarVersionNueva = (plantilla) => ejecutar(async () => {
        const url = `/expedientes/${expediente.id}/plantillas/${plantilla.plantilla_id}/version-nueva`;
        let vista;
        try {
            ({ data: vista } = await axios.get(url));
        } catch (e) {
            if (!montado.current) return;
            avisarError('No se pudo preparar la vista previa', e);
            recargar();
            return;
        }
        if (!montado.current) return;
        const { isConfirmed } = await Swal.fire({
            // titleText, no title: el nombre de la plantilla es un dato y Swal lo escapa.
            titleText: `Aplicar la versión ${vista.version_nueva.numero} de «${vista.plantilla.nombre}»`,
            html: htmlVistaPrevia(vista),
            width: '46rem',
            showCancelButton: true,
            confirmButtonText: 'Aplicar versión',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed || !montado.current) return;
        try {
            const { data } = await axios.post(url, { version_id: vista.version_nueva.id });
            if (montado.current) ponerExpediente(data);
        } catch (e) {
            if (!montado.current) return;
            avisarError('No se pudo aplicar la versión nueva', e);
            recargar();
        }
    });

    const crearExpediente = () => ejecutar(async () => {
        const plantillas = seleccion.filter((id) => disponibles.plantillas.some((p) => p.id === id));
        if (plantillas.length === 0) return;
        try {
            const { data } = await axios.post(`/procesos/${procesoId}/expediente`, { plantillas });
            if (!montado.current) return;
            setDisponibles((prev) => ({ ...prev, expediente_id: data.id }));
            ponerExpediente(data);
        } catch (e) {
            if (!montado.current) return;
            avisarError('No se pudo crear el expediente', e);
            // Otra persona pudo crearlo, o cambiar las plantillas que le corresponden.
            recargar();
        }
    });

    // Primero sin confirmar: si faltan requisitos, el servidor dice cuántos y en qué etapas, y se
    // pide confirmación (la revisión solo avisa). Si todo está cumplido, se confirma el congelado.
    const cerrarExpediente = () => ejecutar(async () => {
        const { id, progreso } = expediente;
        if (progreso.cumplidos >= progreso.total) {
            const { isConfirmed } = await Swal.fire({
                icon: 'question',
                titleText: '¿Cerrar el expediente?',
                text: 'El checklist quedará congelado y no admitirá más cambios.',
                showCancelButton: true,
                confirmButtonText: 'Sí, cerrar',
                cancelButtonText: 'Cancelar',
            });
            if (!isConfirmed) return;
        }
        const enviar = async (datos) => {
            const { data } = await axios.post(`/expedientes/${id}/cerrar`, datos);
            if (montado.current) ponerExpediente(data);
        };
        try {
            await enviar({});
            return;
        } catch (e) {
            const pendientes = e?.response?.status === 422 ? e.response.data?.pendientes : null;
            if (!pendientes) {
                avisarError('No se pudo cerrar el expediente', e);
                recargar();
                return;
            }
            if (!montado.current) return;
            const { isConfirmed } = await Swal.fire({
                icon: 'warning',
                titleText: '¿Cerrar con requisitos pendientes?',
                html: htmlPendientesCierre(pendientes),
                showCancelButton: true,
                confirmButtonText: 'Cerrar de todos modos',
                cancelButtonText: 'Cancelar',
            });
            // Lo pendiente pudo cambiar: el avance se pone al día con lo que dijo el servidor.
            recargar();
            if (!isConfirmed || !montado.current) return;
        }
        try {
            await enviar({ confirmar: true });
        } catch (e) {
            avisarError('No se pudo cerrar el expediente', e);
            recargar();
        }
    });

    const reintentar = error?.reintentable && <button type="button" className="exv-btn" onClick={cargar}>Reintentar</button>;

    if (!disponibles) {
        return (
            <Pagina>
                {error ? (
                    <div className="exv-vacio" role="alert">
                        <b>No se pudo abrir el expediente</b>
                        <p>{error.mensaje}</p>
                        {reintentar}
                    </div>
                ) : (
                    <div className="exv-vacio" role="status">Cargando expediente…</div>
                )}
            </Pagina>
        );
    }

    // La cabecera del proceso: la del expediente si lo hay (la más reciente), si no la de las plantillas.
    const proceso = { ...disponibles.proceso, ...(expediente?.proceso ?? {}) };
    const proyecto = expediente?.proyecto ?? disponibles.proceso.proyecto;
    const numero = numeroProceso(proceso);
    const { cumplidos = 0, total = 0, porcentaje = 0 } = expediente?.progreso ?? {};
    const avisoOpcionales = textoOpcionales(contarOpcionales(expediente?.requisitos));
    const etapasAvance = avanceEtapas(expediente?.avance_por_etapa);
    const cerrado = expediente?.estado === 'cerrado';
    const incompatibles = avisoIncompatiblesProceso(expediente?.plantillas);
    // Uno cerrado tiene el checklist congelado: no admite otra versión, y no se la ofrece.
    const versionesNuevas = expediente && !cerrado ? plantillasConVersionNueva(expediente.plantillas) : [];

    return (
        <Pagina>
            <div className="exv-titulo">
                <div className="exv-titulo-texto">
                    <span className="exv-eyebrow">Expediente de contratación</span>
                    <h2>{proceso.objeto || 'Proceso sin objeto'}</h2>
                    <p className="exv-subtitulo">
                        <span>
                            <b>Proceso</b>{' '}
                            {numero.enlace
                                ? <a href={numero.enlace} target="_blank" rel="noopener noreferrer" className="ct-enlace">{numero.texto}</a>
                                : numero.texto}
                        </span>
                        <span><b>Modalidad</b> {proceso.modalidad?.nombre ?? 'Sin modalidad'}</span>
                        <span className={`ct-estado ct-estado-${tonoEstado(proceso.estado)}`}>{etiquetaEstadoProceso(proceso.estado)}</span>
                        {proyecto && (
                            <span><b>Proyecto</b> <Link to={rutaFichaProyecto(proyecto.id)} className="ct-enlace">{proyecto.nombre || 'Sin nombre'}</Link></span>
                        )}
                    </p>
                    {expediente && (
                        <p className="exv-subtitulo">
                            <b>Expediente #{expediente.id}</b>
                            <span>{plantillasConVersion(expediente.plantillas)}</span>
                            <span>{textoEstadoExpediente(expediente)}</span>
                        </p>
                    )}
                </div>
            </div>

            {error && <div className="exv-aviso" role="alert">{error.mensaje} {reintentar}</div>}

            {!expediente ? (
                <SinExpediente
                    plantillas={disponibles.plantillas}
                    puedeEditar={puedeEditar}
                    esAdministrador={user?.rol === 'Administrador'}
                    seleccion={seleccion ?? []}
                    onCambiar={setSeleccion}
                    ocupado={ocupado}
                    onCrear={crearExpediente}
                />
            ) : (
                <main className="exv-tablero">
                    <div className="exv-barra">
                        <Medidor cumplidos={cumplidos} total={total} porcentaje={porcentaje} nota={avisoOpcionales} />
                        <div className="exv-barra-acciones">
                            <button
                                type="button"
                                className="exv-btn exv-btn-historial"
                                aria-expanded={historialAbierto}
                                aria-controls={historialAbierto ? ID_HISTORIAL : undefined}
                                onClick={() => setHistorialAbierto((abierto) => !abierto)}
                            >
                                <FontAwesomeIcon icon={faClockRotateLeft} /> Historial
                            </button>
                            {editable && (
                                <button type="button" className="exv-btn exv-btn-primario" onClick={cerrarExpediente} disabled={ocupado}>
                                    <FontAwesomeIcon icon={faLock} /> Cerrar expediente
                                </button>
                            )}
                        </div>
                    </div>

                    {etapasAvance.length > 0 && (
                        <section className="exc-etapas" aria-label="Avance por etapa">
                            {etapasAvance.map((e) => (
                                <Medidor key={e.clave} titulo={e.titulo} cumplidos={e.cumplidos} total={e.total} porcentaje={e.porcentaje} />
                            ))}
                        </section>
                    )}

                    {cerrado && (
                        <div className="exv-nota">
                            <b>{textoEstadoExpediente(expediente)}.</b> Su checklist está congelado: solo se puede consultar.
                        </div>
                    )}
                    {incompatibles && (
                        <div className="exv-aviso exv-no-imprimir">
                            <b>{incompatibles.titulo}:</b> {incompatibles.detalle}
                        </div>
                    )}
                    {versionesNuevas.length > 0 && (
                        <div className="exv-config-resumen exv-no-imprimir">
                            {versionesNuevas.map((p) => (
                                <span key={p.plantilla_id} className="exv-version-nueva">
                                    <b>{p.nombre}</b> {textoVersionNueva(p)}
                                    {editable && (
                                        <button
                                            type="button"
                                            className="exv-btn"
                                            onClick={() => aplicarVersionNueva(p)}
                                            disabled={ocupado}
                                            aria-label={etiquetaAplicarVersion(p)}
                                        >
                                            <FontAwesomeIcon icon={faArrowsRotate} /> Aplicar versión {p.version_nueva.numero}
                                        </button>
                                    )}
                                </span>
                            ))}
                        </div>
                    )}

                    {historialAbierto && (
                        <ExpedienteHistorial
                            key={expediente.id}
                            id={ID_HISTORIAL}
                            expedienteId={expediente.id}
                            revision={revision}
                            onDescargar={descargarSoporteHistorial}
                        />
                    )}

                    <ChecklistPorEtapa
                        expediente={expediente}
                        editable={editable}
                        ocupado={ocupado}
                        etapas={etapas}
                        onAplica={decidirAplica}
                        onGuardarPropio={guardarPropio}
                        onEliminarPropio={eliminarPropio}
                        onMarcar={marcar}
                        onObservacion={guardarObservacion}
                        onSubirSoporte={subirSoporte}
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
                </main>
            )}
        </Pagina>
    );
};

/**
 * El expediente de contratación de un proceso (`/contratacion/:procesoId`): su checklist por etapa,
 * el avance total y por etapa, y el cierre, que solo avisa de lo pendiente. Sin expediente, ofrece
 * crearlo con las plantillas que le corresponden. Un id que no es válido no llega a la API.
 */
const ExpedienteContratacion = ({ user }) => {
    const { procesoId } = useParams();
    if (!esIdProceso(procesoId)) {
        return (
            <Pagina>
                <div className="exv-vacio" role="alert"><b>No se pudo abrir el expediente</b><p>El proceso no existe.</p></div>
            </Pagina>
        );
    }
    return <Expediente key={procesoId} procesoId={procesoId} user={user} />;
};

export default ExpedienteContratacion;
```

Crear `resources/js/utils/expedienteContratacion.js`:

```js
// Lógica de presentación del expediente de contratación de un proceso (/contratacion/:procesoId):
// el checklist por etapa, el avance por etapa, los requisitos propios con su etapa, el aviso de
// pendientes al cerrar y la elección de plantillas para crearlo. La agrupación de viabilización
// (agruparChecklist) no cambia. Funciones puras, probadas en expedienteContratacion.test.js.

import { esIdContrato } from './contratosModulo';
import { opcionesEtapa } from './contratacion';
import { datosPropio, formularioPropio } from './expedienteChecklist';
import { compararEtapas, escaparHtml, mensajeApi, nombreEtapa, SIN_ETAPA } from './plantillasChecklist';

/** Si el `:procesoId` de la ruta es un id: la misma regla que el de un contrato (lo que acepta la API). */
export const esIdProceso = (valor) => esIdContrato(valor);

/** El error al cargar el proceso: un proceso que no existe no se reintenta. */
export const errorDeProceso = (error) => (error?.response?.status === 404
    ? { mensaje: 'El proceso no existe.', reintentable: false }
    : { mensaje: mensajeApi(error), reintentable: true });

export const TITULO_SIN_ETAPA = 'Sin etapa';

export const textoSinPlantillas = 'No hay plantillas de contratación publicadas que correspondan a la modalidad, la fuente y el sector de este proceso.';

const claveEtapa = (etapa) => (etapa ? `etapa-${etapa.id}` : SIN_ETAPA);
const tituloEtapa = (etapa) => (etapa ? nombreEtapa(etapa) : TITULO_SIN_ETAPA);

/** Lo que cuenta lo decide el backend: lo no aplicable y lo opcional no suman. */
const conteo = (filas) => ({
    total: filas.filter((f) => f.cuenta).length,
    cumplidos: filas.filter((f) => f.cuenta && f.estado === 'cumplido').length,
});

/**
 * Filas del checklist por etapa (`fila.etapa`, la del requisito o la del propio), en el orden del
 * catálogo (orden y, a igual orden, id, como el backend) y "Sin etapa" al final. Dentro de cada
 * etapa van en el orden en que llegan: el del backend, con los propios después de las plantillas.
 */
export const agruparPorEtapa = (filas) => {
    const grupos = new Map();
    (filas ?? []).forEach((f) => {
        const clave = claveEtapa(f.etapa);
        if (!grupos.has(clave)) grupos.set(clave, { clave, etapa: f.etapa ?? null, titulo: tituloEtapa(f.etapa), filas: [] });
        grupos.get(clave).filas.push(f);
    });
    const lista = [...grupos.values()];
    const conEtapa = lista.filter((g) => g.etapa).sort((a, b) => compararEtapas(a.etapa, b.etapa));
    const sinEtapa = lista.filter((g) => !g.etapa);
    return [...conEtapa, ...sinEtapa].map((g) => ({ ...g, ...conteo(g.filas) }));
};

/** El avance por etapa de la API (`avance_por_etapa`, ya en el orden del catálogo), para sus barras. */
export const avanceEtapas = (avance) => (avance ?? []).map((a) => ({
    clave: claveEtapa(a.etapa),
    titulo: tituloEtapa(a.etapa),
    total: a.total,
    cumplidos: a.cumplidos,
    porcentaje: a.porcentaje,
}));

/** El expediente con el progreso y el avance por etapa que trae una respuesta parcial, si los trae. */
export const conAvance = (expediente, datos) => ({
    ...expediente,
    progreso: datos?.progreso ?? expediente.progreso,
    avance_por_etapa: datos?.avance_por_etapa ?? expediente.avance_por_etapa,
});

/** Etapas que se ofrecen a un propio: las activas y la que ya tiene (aunque esté inactiva). */
export const opcionesEtapaPropio = (etapas, fila) => opcionesEtapa(etapas, fila?.etapa ?? null);

/** El formulario de un propio de contratación: el de siempre más su etapa (como texto, la del select). */
export const formularioPropioContratacion = (fila) => ({
    ...formularioPropio(fila),
    etapa_propia_id: fila?.etapa?.id ? String(fila.etapa.id) : '',
});

/** Lo que espera la API: lo de siempre más `etapa_propia_id`, obligatoria en contratación. */
export const datosPropioContratacion = (formulario) => ({
    ...datosPropio(formulario),
    etapa_propia_id: Number(formulario.etapa_propia_id) || null,
});

/** Un propio de contratación se guarda con título y etapa. */
export const propioCompleto = (formulario) => Boolean(formulario.titulo.trim()) && Boolean(formulario.etapa_propia_id);

const plural = (n, singular, varios) => (n === 1 ? `${n} ${singular}` : `${n} ${varios}`);

/**
 * Los pendientes con que el servidor rechazó cerrar sin confirmar (`pendientes` del 422):
 * "Faltan 4 requisitos por cumplir" y una línea por etapa. Null si no hay.
 */
export const textoPendientesCierre = (pendientes) => {
    if (!pendientes?.total) return null;
    return {
        titulo: `${pendientes.total === 1 ? 'Falta' : 'Faltan'} ${plural(pendientes.total, 'requisito', 'requisitos')} por cumplir`,
        etapas: (pendientes.por_etapa ?? []).map((e) => `${tituloEtapa(e.etapa)}: ${plural(e.pendientes, 'pendiente', 'pendientes')}`),
    };
};

/** El cuerpo del aviso antes de confirmar el cierre, con los nombres escapados. */
export const htmlPendientesCierre = (pendientes) => {
    const texto = textoPendientesCierre(pendientes);
    if (!texto) return '';
    const etapas = texto.etapas.map((e) => `<li>${escaparHtml(e)}</li>`).join('');
    return `<p>${escaparHtml(texto.titulo)}.</p>${etapas ? `<ul style="text-align:left">${etapas}</ul>` : ''}`
        + '<p>Puedes cerrarlo de todos modos: la revisión solo avisa. Cerrado, el checklist queda congelado.</p>';
};

const fecha = (iso) => (iso ? new Date(iso).toLocaleDateString('es-CO') : null);

/** "Borrador", o "Cerrado el 18/11/2026 por Ana" (con lo que se sepa). */
export const textoEstadoExpediente = (expediente) => {
    if (expediente?.estado !== 'cerrado') return 'Borrador';
    const cuando = fecha(expediente.fecha_cierre);
    return `Cerrado${cuando ? ` el ${cuando}` : ''}${expediente.usuario_cierre ? ` por ${expediente.usuario_cierre}` : ''}`;
};

/** Plantillas elegidas al abrir: si solo hay una compatible, ya va elegida. */
export const seleccionInicial = (plantillas) => (plantillas?.length === 1 ? [plantillas[0].id] : []);

/** Marca o desmarca una plantilla conservando el orden en que se eligen: la primera es la principal. */
export const alternarPlantillaNueva = (seleccion, id) => (
    seleccion.includes(id) ? seleccion.filter((x) => x !== id) : [...seleccion, id]
);

// Por qué una plantilla del expediente ya no le sirve al proceso: título con una, con varias, y
// la razón corta de cada una cuando las razones son distintas.
const RAZONES = {
    no_corresponde: [
        'Una plantilla ya no corresponde a la modalidad, la fuente o el sector del proceso',
        'Algunas plantillas ya no corresponden a la modalidad, la fuente o el sector del proceso',
        'ya no corresponde a la modalidad, la fuente o el sector',
    ],
    inactiva: ['Una plantilla del expediente está inactiva', 'Algunas plantillas del expediente están inactivas', 'inactiva'],
    sin_publicada: ['Una plantilla ya no tiene una versión publicada', 'Algunas plantillas ya no tienen una versión publicada', 'sin versión publicada'],
};

// Primero la cobertura (`corresponde`, que no mira si está activa o publicada): es lo que cambia
// al editar el proceso o el proyecto. Sin los campos de la razón, se entiende que no corresponde.
const razonPlantilla = (p) => {
    if (p.corresponde === false) return 'no_corresponde';
    if (p.activa === false) return 'inactiva';
    if (p.publicada === false) return 'sin_publicada';
    return 'no_corresponde';
};

/**
 * Aviso de las plantillas del expediente que ya no le sirven al proceso (cambió su modalidad o su
 * fuente, o el sector del proyecto; o la plantilla está inactiva o sin versión publicada), con la
 * razón; null si no hay. El expediente no cambia: solo se avisa.
 */
export const avisoIncompatiblesProceso = (plantillas) => {
    const incompatibles = (Array.isArray(plantillas) ? plantillas : [])
        .filter((p) => p && !p.compatible)
        .map((p) => ({ nombre: p.nombre, razon: razonPlantilla(p) }));
    if (incompatibles.length === 0) return null;
    const una = incompatibles.length === 1;
    const conserva = una ? 'El expediente la conserva tal como está.' : 'El expediente las conserva tal como están.';
    const razones = [...new Set(incompatibles.map((p) => p.razon))];
    if (razones.length === 1) {
        return { titulo: RAZONES[razones[0]][una ? 0 : 1], detalle: `${incompatibles.map((p) => p.nombre).join(', ')}. ${conserva}` };
    }
    return {
        titulo: 'Algunas plantillas ya no sirven al proceso',
        detalle: `${incompatibles.map((p) => `${p.nombre} (${RAZONES[p.razon][2]})`).join(', ')}. ${conserva}`,
    };
};
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (1 de 2):

```php
    }

    /**
     * Lo que se ofrece para crear el expediente del proceso.
     *
     * @return array{proceso: array, expediente_id: ?int, plantillas: list<array>}
     */
    public function disponibles(ProcesoLicitacion $proceso): array
    {
        $proceso->loadMissing(['modalidadRel:id,nombre', 'fuente:id,nombre', 'proyectoRel:id,sector', 'proyectoRel.sectorRel:id,nombre']);
        $sector = $proceso->proyectoRel?->sectorRel;
        $opcion = fn ($fila) => $fila ? ['id' => $fila->id, 'nombre' => $fila->nombre] : null;
```

por:

```php
    }

    /**
     * Lo que se ofrece para crear el expediente del proceso, con lo que la pantalla del expediente
     * muestra del proceso cuando todavía no lo tiene (código, objeto, estado y proyecto).
     *
     * @return array{proceso: array, expediente_id: ?int, plantillas: list<array>}
     */
    public function disponibles(ProcesoLicitacion $proceso): array
    {
        $proceso->loadMissing(['modalidadRel:id,nombre', 'fuente:id,nombre', 'proyectoRel:id,nombre,sector', 'proyectoRel.sectorRel:id,nombre']);
        $sector = $proceso->proyectoRel?->sectorRel;
        $opcion = fn ($fila) => $fila ? ['id' => $fila->id, 'nombre' => $fila->nombre] : null;
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (2 de 2):

```php
        return [
            'proceso' => [
                'id' => $proceso->id,
                'modalidad' => $opcion($proceso->modalidadRel),
                'fuente' => $opcion($proceso->fuente),
                'sector' => $opcion($sector),
```

por:

```php
        return [
            'proceso' => [
                'id' => $proceso->id,
                'codigo_proceso' => $proceso->codigo_proceso,
                'objeto' => $proceso->objeto,
                'estado' => $proceso->estado,
                'proyecto' => $opcion($proceso->proyectoRel),
                'modalidad' => $opcion($proceso->modalidadRel),
                'fuente' => $opcion($proceso->fuente),
                'sector' => $opcion($sector),
```

En `resources/js/app.jsx`, reemplazar (1 de 2):

```jsx
import PlantillaDetalle from './components/PlantillaDetalle';
import ContratosModulo from './components/contratos/ContratosModulo';
import FichaProyecto from './components/FichaProyecto';
import { RUTA_CONTRATOS, RUTA_PLANTILLAS, puedeVer } from './utils/navegacion';
import axios from './axios';
import { EVENTO_SESION_EXPIRADA } from './utils/sesion';
```

por:

```jsx
import PlantillaDetalle from './components/PlantillaDetalle';
import ContratosModulo from './components/contratos/ContratosModulo';
import FichaProyecto from './components/FichaProyecto';
import ExpedienteContratacion from './components/ExpedienteContratacion';
import { RUTA_CONTRATOS, RUTA_PLANTILLAS, puedeVer } from './utils/navegacion';
import axios from './axios';
import { EVENTO_SESION_EXPIRADA } from './utils/sesion';
```

En `resources/js/app.jsx`, reemplazar (2 de 2):

```jsx
          <Route path="/viabilizacion/:proyectoId" element={<ExpedienteViabilizacion user={user} />} />
          {/* Siempre con id: GET /proyectos (sin id) es de la API. */}
          <Route path="/proyectos/:proyectoId" element={<FichaProyecto user={user} />} />
          {/* Lectura para todos; nuevo y modificar, solo quien edita (ContratosModulo lo decide). */}
          <Route path={RUTA_CONTRATOS} element={<ContratosModulo user={user} />} />
          <Route path={`${RUTA_CONTRATOS}/nuevo`} element={<ContratosModulo user={user} nuevo />} />
```

por:

```jsx
          <Route path="/viabilizacion/:proyectoId" element={<ExpedienteViabilizacion user={user} />} />
          {/* Siempre con id: GET /proyectos (sin id) es de la API. */}
          <Route path="/proyectos/:proyectoId" element={<FichaProyecto user={user} />} />
          {/* El expediente de un proceso. No usar /procesos/...: bajo /GestPro chocaría con la API. */}
          <Route path="/contratacion/:procesoId" element={<ExpedienteContratacion user={user} />} />
          {/* Lectura para todos; nuevo y modificar, solo quien edita (ContratosModulo lo decide). */}
          <Route path={RUTA_CONTRATOS} element={<ContratosModulo user={user} />} />
          <Route path={`${RUTA_CONTRATOS}/nuevo`} element={<ContratosModulo user={user} nuevo />} />
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (1 de 8):

```jsx
    textoAvisosDuplicados,
    textoPorDefinir,
} from '../utils/expedienteChecklist';
import ExpedienteHistorialRequisito from './ExpedienteHistorialRequisito';

const TIPOS_SOPORTE = '.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx';
```

por:

```jsx
    textoAvisosDuplicados,
    textoPorDefinir,
} from '../utils/expedienteChecklist';
import {
    agruparPorEtapa, formularioPropioContratacion, opcionesEtapaPropio, propioCompleto,
} from '../utils/expedienteContratacion';
import ExpedienteHistorialRequisito from './ExpedienteHistorialRequisito';

const TIPOS_SOPORTE = '.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx';
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (2 de 8):

```jsx
    );
};

/** Formulario de un requisito propio, para agregarlo o editarlo. */
const FormularioPropio = ({ fila, ocupado, onGuardar, onCancelar }) => {
    const [formulario, setFormulario] = useState(() => formularioPropio(fila));
    const titulo = useRef(null);
    const prefijo = `exv-propio-${fila?.id ?? 'nuevo'}`;
    const cambiar = (campo, valor) => setFormulario((f) => ({ ...f, [campo]: valor }));
```

por:

```jsx
    );
};

/**
 * Formulario de un requisito propio, para agregarlo o editarlo. Con `conEtapa` (expediente de
 * contratación) pide además la etapa, obligatoria, entre `etapas` (GET /etapasContratacion, o
 * null si no llegó) y la que el propio ya tiene. Sin `conEtapa` es el de siempre.
 */
const FormularioPropio = ({ fila, ocupado, onGuardar, onCancelar, conEtapa = false, etapas = null }) => {
    const [formulario, setFormulario] = useState(() => (conEtapa ? formularioPropioContratacion(fila) : formularioPropio(fila)));
    const titulo = useRef(null);
    const prefijo = `exv-propio-${fila?.id ?? 'nuevo'}`;
    const cambiar = (campo, valor) => setFormulario((f) => ({ ...f, [campo]: valor }));
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (3 de 8):

```jsx
                    onChange={(e) => cambiar('descripcion', e.target.value)}
                />
            </label>
            <label className="exv-campo" htmlFor={`${prefijo}-categoria`}>
                <span>Categoría</span>
                <select id={`${prefijo}-categoria`} value={formulario.categoria} onChange={(e) => cambiar('categoria', e.target.value)}>
```

por:

```jsx
                    onChange={(e) => cambiar('descripcion', e.target.value)}
                />
            </label>
            {conEtapa && (
                <label className="exv-campo" htmlFor={`${prefijo}-etapa`}>
                    <span>Etapa</span>
                    <select
                        id={`${prefijo}-etapa`}
                        required
                        value={formulario.etapa_propia_id}
                        onChange={(e) => cambiar('etapa_propia_id', e.target.value)}
                    >
                        <option value="">Elige la etapa</option>
                        {opcionesEtapaPropio(etapas, fila).map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                    </select>
                </label>
            )}
            <label className="exv-campo" htmlFor={`${prefijo}-categoria`}>
                <span>Categoría</span>
                <select id={`${prefijo}-categoria`} value={formulario.categoria} onChange={(e) => cambiar('categoria', e.target.value)}>
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (4 de 8):

```jsx
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
```

por:

```jsx
                    </label>
                ))}
                <p id={`${prefijo}-ayuda`} className="exv-campo-ayuda">
                    {conEtapa
                        ? 'Un requisito opcional se puede marcar, pero no cuenta en el avance.'
                        : 'Un requisito opcional se puede marcar, pero no cuenta en el avance ni bloquea la radicación.'}
                </p>
            </fieldset>
            <div className="exv-propio-acciones">
                <button type="button" className="exv-btn" onClick={onCancelar} disabled={ocupado}>Cancelar</button>
                <button
                    type="submit"
                    className="exv-btn exv-btn-primario"
                    disabled={ocupado || !(conEtapa ? propioCompleto(formulario) : formulario.titulo.trim())}
                >
                    {fila ? 'Guardar cambios' : 'Agregar requisito'}
                </button>
            </div>
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (5 de 8):

```jsx
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

    // Otro expediente: el formulario abierto era del anterior. Se cierra sin devolver el foco.
    useEffect(() => {
```

por:

```jsx
    );
};

/**
 * El formulario de propios del checklist: cuál está abierto (null, 'nuevo' o el id de la fila
 * propia que se edita), y el foco de vuelta al botón que lo abrió al cerrarlo.
 */
const useFormularioPropios = (expediente, onGuardarPropio) => {
    const [formulario, setFormulario] = useState(null); // null, 'nuevo' o el id de la fila propia que se edita
    const contenedor = useRef(null);
    const retorno = useRef(null); // data-foco del botón que abrió el formulario

    // Otro expediente: el formulario abierto era del anterior. Se cierra sin devolver el foco.
    useEffect(() => {
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (6 de 8):

```jsx
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
                ocupado={ocupado}
                formularioAbierto={formulario !== null}
                onEditarPropio={abrir}
                onEliminarPropio={onEliminarPropio}
                {...acciones}
            />
        )
    );

    return (
        <div ref={contenedor}>
```

por:

```jsx
        if (await onGuardarPropio(fila, datos)) setFormulario(null);
    };

    const cerrar = () => setFormulario(null);

    return { formulario, contenedor, abrir, guardar, cerrar };
};

/** Dibuja una fila del checklist, o el formulario del propio si es el que se edita. */
const dibujarFila = ({ formulario, abrir, guardar, cerrar }, { editable, ocupado, onEliminarPropio, acciones, conEtapa, etapas }) => (fila) => (
    formulario === fila.id ? (
        <FormularioPropio
            key={fila.id}
            fila={fila}
            ocupado={ocupado}
            conEtapa={conEtapa}
            etapas={etapas}
            onGuardar={(datos) => guardar(fila, datos)}
            onCancelar={cerrar}
        />
    ) : (
        <ItemRequisito
            key={fila.id}
            fila={fila}
            editable={editable}
            ocupado={ocupado}
            formularioAbierto={formulario !== null}
            onEditarPropio={abrir}
            onEliminarPropio={onEliminarPropio}
            {...acciones}
        />
    )
);

const ExpedienteChecklist = ({ expediente, editable, ocupado, onGuardarPropio, onEliminarPropio, ...acciones }) => {
    const bloques = useMemo(() => agruparChecklist(expediente.requisitos), [expediente.requisitos]);
    const propiosAbiertos = useFormularioPropios(expediente, onGuardarPropio);
    const { formulario, contenedor, abrir, guardar, cerrar } = propiosAbiertos;
    const sectores = expediente.configuracion.sectores;
    const deCatalogo = bloques.filter((b) => b.tipo !== 'propios');
    const propios = bloques.find((b) => b.tipo === 'propios');
    // Con una sola plantilla el checklist se ve como siempre; con varias, cada una lleva su nombre.
    const conCabecera = expediente.plantillas.length > 1 || deCatalogo.length > 1;

    const renderFila = dibujarFila(propiosAbiertos, { editable, ocupado, onEliminarPropio, acciones });

    return (
        <div ref={contenedor}>
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (7 de 8):

```jsx
                        <FormularioPropio
                            ocupado={ocupado}
                            onGuardar={(datos) => guardar(null, datos)}
                            onCancelar={() => setFormulario(null)}
                        />
                    ) : (
                        <button
```

por:

```jsx
                        <FormularioPropio
                            ocupado={ocupado}
                            onGuardar={(datos) => guardar(null, datos)}
                            onCancelar={cerrar}
                        />
                    ) : (
                        <button
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (8 de 8):

```jsx
    );
};

export default ExpedienteChecklist;
```

por:

```jsx
    );
};

/**
 * El checklist de un expediente de contratación: por etapa, en el orden del catálogo y "Sin etapa"
 * al final (agruparPorEtapa). Cada propio va en su etapa, que es obligatoria; al final, quien
 * edita agrega uno nuevo eligiendo la etapa entre `etapas` (GET /etapasContratacion).
 */
export const ChecklistPorEtapa = ({ expediente, editable, ocupado, etapas, onGuardarPropio, onEliminarPropio, ...acciones }) => {
    const grupos = useMemo(() => agruparPorEtapa(expediente.requisitos), [expediente.requisitos]);
    const propiosAbiertos = useFormularioPropios(expediente, onGuardarPropio);
    const { formulario, contenedor, abrir, guardar, cerrar } = propiosAbiertos;
    const renderFila = dibujarFila(propiosAbiertos, { editable, ocupado, onEliminarPropio, acciones, conEtapa: true, etapas });

    return (
        <div ref={contenedor}>
            <AvisosDuplicados avisos={expediente.avisos_duplicados} total={expediente.avisos_duplicados_total} />

            {grupos.length === 0 && (
                <div className="exv-vacio">
                    <b>Sin requisitos que mostrar</b>
                    <p>Las plantillas del expediente no tienen requisitos activos.{editable ? ' Puedes agregar requisitos propios.' : ''}</p>
                </div>
            )}

            {grupos.map((grupo) => (
                <section key={grupo.clave} className="exv-seccion exv-bloque" aria-labelledby={`exv-${grupo.clave}-titulo`}>
                    <div className="exv-plantilla-titulo">
                        <h3 id={`exv-${grupo.clave}-titulo`}>{grupo.titulo}</h3>
                        <span className="exv-cita">{grupo.cumplidos}/{grupo.total}</span>
                    </div>
                    {grupo.filas.map(renderFila)}
                </section>
            ))}

            {editable && (
                <section className="exv-seccion exv-bloque exv-propios exv-no-imprimir" aria-labelledby="exv-propios-titulo">
                    <div className="exv-plantilla-titulo">
                        <h3 id="exv-propios-titulo">Requisitos propios del proceso</h3>
                    </div>
                    <p className="exv-propios-vacio">
                        Agrega lo que el proceso debe cumplir y no trae ninguna plantilla; queda en la etapa que elijas. No cambia ninguna plantilla.
                    </p>
                    {formulario === 'nuevo' ? (
                        <FormularioPropio
                            ocupado={ocupado}
                            conEtapa
                            etapas={etapas}
                            onGuardar={(datos) => guardar(null, datos)}
                            onCancelar={cerrar}
                        />
                    ) : (
                        <button
                            type="button"
                            className="exv-btn"
                            data-foco="agregar-propio"
                            disabled={ocupado || formulario !== null}
                            onClick={() => abrir(null)}
                        >
                            <FontAwesomeIcon icon={faPlus} /> Agregar requisito propio
                        </button>
                    )}
                </section>
            )}
        </div>
    );
};

export default ExpedienteChecklist;
```

En `resources/js/utils/expedienteChecklist.js`, reemplazar (1 de 1):

```js
    configuracion_confirmada: 'Confirmó la configuración',
    configuracion_cambiada: 'Cambió la configuración',
    expediente_radicado: 'Radicó el expediente',
    version_aplicada: 'Aplicó una versión nueva de una plantilla',
};
```

por:

```js
    configuracion_confirmada: 'Confirmó la configuración',
    configuracion_cambiada: 'Cambió la configuración',
    expediente_radicado: 'Radicó el expediente',
    expediente_cerrado: 'Cerró el expediente',
    version_aplicada: 'Aplicó una versión nueva de una plantilla',
};
```

En `resources/js/utils/navegacion.js`, reemplazar (1 de 2):

```js
// La ficha de un proyecto. Siempre con id: GET /proyectos (sin id) es de la API.
export const rutaFichaProyecto = (id) => `/proyectos/${id}`;

export const DESTINOS = [
    { id: 'tablero', titulo: 'Tablero', ruta: '/dashboard', grupo: GRUPO.TRABAJO, icono: 'faGaugeHigh', palabras: 'inicio dashboard panel mapa' },
    ...SECCIONES_PARAMETROS.filter((s) => s.grupo === GRUPO.TRABAJO).map((s) => ({ ...s, id: s.slug, ruta: `/parametros/${s.slug}` })),
```

por:

```js
// La ficha de un proyecto. Siempre con id: GET /proyectos (sin id) es de la API.
export const rutaFichaProyecto = (id) => `/proyectos/${id}`;

// El expediente de contratación de un proceso. Siempre con id; bajo /GestPro no es una ruta GET de la API.
export const rutaExpedienteContratacion = (id) => `/contratacion/${id}`;

export const DESTINOS = [
    { id: 'tablero', titulo: 'Tablero', ruta: '/dashboard', grupo: GRUPO.TRABAJO, icono: 'faGaugeHigh', palabras: 'inicio dashboard panel mapa' },
    ...SECCIONES_PARAMETROS.filter((s) => s.grupo === GRUPO.TRABAJO).map((s) => ({ ...s, id: s.slug, ruta: `/parametros/${s.slug}` })),
```

En `resources/js/utils/navegacion.js`, reemplazar (2 de 2):

```js
export const destinoActivo = (pathname, search) => {
    if (pathname.startsWith('/viabilizacion/')) return 'expedientes';
    if (pathname.startsWith('/proyectos/')) return 'proyectos';
    if (pathname === RUTA_PLANTILLAS || pathname.startsWith(`${RUTA_PLANTILLAS}/`)) return 'plantillas';
    if (pathname === RUTA_CONTRATOS || pathname.startsWith(`${RUTA_CONTRATOS}/`)) return 'contratos';
    if (pathname.startsWith('/parametros/')) return pathname.split('/')[2];
```

por:

```js
export const destinoActivo = (pathname, search) => {
    if (pathname.startsWith('/viabilizacion/')) return 'expedientes';
    if (pathname.startsWith('/proyectos/')) return 'proyectos';
    // Un proceso es de un proyecto: su expediente se abre desde la ficha o desde sus procesos.
    if (pathname.startsWith('/contratacion/')) return 'proyectos';
    if (pathname === RUTA_PLANTILLAS || pathname.startsWith(`${RUTA_PLANTILLAS}/`)) return 'plantillas';
    if (pathname === RUTA_CONTRATOS || pathname.startsWith(`${RUTA_CONTRATOS}/`)) return 'contratos';
    if (pathname.startsWith('/parametros/')) return pathname.split('/')[2];
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/ExpedienteProcesoApiTest.php`
Expected: pasan todas.

Run: `npx vitest run resources/js/utils/expedienteContratacion.test.js resources/js/utils/navegacion.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1202 passed.

Run: `npm test`
Expected: 702 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/css/ExpedienteContratacion.css resources/js/components/ExpedienteContratacion.jsx resources/js/utils/expedienteContratacion.js resources/js/utils/expedienteContratacion.test.js app/Services/Contratacion/ExpedientesContratacion.php resources/js/app.jsx resources/js/components/ExpedienteChecklist.jsx resources/js/utils/expedienteChecklist.js resources/js/utils/navegacion.js resources/js/utils/navegacion.test.js tests/Feature/Contratacion/ExpedienteProcesoApiTest.php
git commit -m "feat: contracting expedient page for a process"
```

---

### Tarea 6: Avance y enlaces en procesos y en la ficha

Cada proceso muestra el avance de su expediente y un enlace al checklist, en el diálogo de procesos y en la ficha del proyecto.

**Files:**
- Modify: `resources/js/components/FichaProyecto.jsx`
- Modify: `resources/js/components/ProcesosProyecto.jsx`
- Modify: `resources/js/utils/contratacion.js`
- Test: `resources/js/utils/contratacion.test.js`
- Modify: `resources/js/utils/fichaProyecto.js`
- Test: `resources/js/utils/fichaProyecto.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/contratacion.test.js`, reemplazar (1 de 2):

```js
    puedeRegistrarContrato,
    modoTrasGuardarContrato,
    textoContratos,
    textoValor,
    tonoEstado,
    validarProceso,
```

por:

```js
    puedeRegistrarContrato,
    modoTrasGuardarContrato,
    textoContratos,
    avanceExpediente,
    textoValor,
    tonoEstado,
    validarProceso,
```

En `resources/js/utils/contratacion.test.js`, reemplazar (2 de 2):

```js
        expect(opcionesEtapa(undefined, null)).toEqual([]);
    });
});
```

por:

```js
        expect(opcionesEtapa(undefined, null)).toEqual([]);
    });
});

describe('avanceExpediente', () => {
    it('sin expediente', () => {
        expect(avanceExpediente(null)).toEqual({ tiene: false, texto: 'Sin expediente', porcentaje: null, cerrado: false, aviso: null });
        expect(avanceExpediente(undefined).texto).toBe('Sin expediente');
    });

    it('el avance del expediente: cumplidos de total y porcentaje', () => {
        expect(avanceExpediente({ id: 3, estado: 'borrador', progreso: { total: 10, cumplidos: 3, porcentaje: 30 }, aviso_plantilla: null })).toEqual({
            tiene: true, texto: '3 de 10 requisitos · 30 %', porcentaje: 30, cerrado: false, aviso: null,
        });
    });

    it('un solo requisito va en singular', () => {
        expect(avanceExpediente({ id: 3, estado: 'borrador', progreso: { total: 1, cumplidos: 0, porcentaje: 0 } }).texto).toBe('0 de 1 requisito · 0 %');
    });

    it('cerrado se marca y el aviso de plantilla se conserva', () => {
        const avance = avanceExpediente({ id: 3, estado: 'cerrado', progreso: { total: 4, cumplidos: 4, porcentaje: 100 }, aviso_plantilla: 'Hay una versión nueva.' });
        expect(avance).toMatchObject({ cerrado: true, aviso: 'Hay una versión nueva.', porcentaje: 100 });
    });

    it('sin progreso cuenta cero y un aviso vacío no se muestra', () => {
        expect(avanceExpediente({ id: 3, estado: 'borrador', aviso_plantilla: '  ' })).toMatchObject({ texto: '0 de 0 requisitos · 0 %', aviso: null });
    });
});
```

En `resources/js/utils/fichaProyecto.test.js`, reemplazar (1 de 2):

```js
            estado: 'Adjudicado',
            tono: 'verde',
            contratos: '1 contrato',
        });
        expect(normal(fila.monto)).toBe('$ 1.500.000');
    });
```

por:

```js
            estado: 'Adjudicado',
            tono: 'verde',
            contratos: '1 contrato',
            expediente: { tiene: false, texto: 'Sin expediente', cerrado: false, aviso: null },
            rutaChecklist: '/contratacion/4',
        });
        expect(normal(fila.monto)).toBe('$ 1.500.000');
    });
```

En `resources/js/utils/fichaProyecto.test.js`, reemplazar (2 de 2):

```js
        const fila = filaProceso({ id: 5, objeto: '', modalidad: null, codigo_proceso: null, url_proceso: null, monto: null, estado: 'preparacion', contratos: 0 });
        expect(fila).toMatchObject({ numero: '—', enlace: null, objeto: '—', modalidad: '—', monto: '—', estado: 'En preparación', contratos: 'Sin contratos' });
    });
});
```

por:

```js
        const fila = filaProceso({ id: 5, objeto: '', modalidad: null, codigo_proceso: null, url_proceso: null, monto: null, estado: 'preparacion', contratos: 0 });
        expect(fila).toMatchObject({ numero: '—', enlace: null, objeto: '—', modalidad: '—', monto: '—', estado: 'En preparación', contratos: 'Sin contratos' });
    });

    it('el avance del expediente del proceso va aparte, sin tocar los demás datos', () => {
        const fila = filaProceso({ ...proceso, expediente: { id: 9, estado: 'cerrado', progreso: { total: 5, cumplidos: 2, porcentaje: 40 }, aviso_plantilla: 'Versión nueva.' } });
        expect(fila.expediente).toMatchObject({ tiene: true, texto: '2 de 5 requisitos · 40 %', cerrado: true, aviso: 'Versión nueva.' });
        expect(fila.contratos).toBe('1 contrato');
    });
});
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/contratacion.test.js resources/js/utils/fichaProyecto.test.js`
Expected: FALLAN 7 y pasan 93: no existe `avanceExpediente` ni el avance del expediente en las filas de procesos de la ficha.

- [ ] **Paso 3: Implementar**

En `resources/js/components/FichaProyecto.jsx`, reemplazar (1 de 2):

```jsx
                                <th>Estado</th>
                                <th>Valor</th>
                                <th>Contratos</th>
                            </tr>
                        </thead>
                        <tbody>
```

por:

```jsx
                                <th>Estado</th>
                                <th>Valor</th>
                                <th>Contratos</th>
                                <th>Expediente</th>
                            </tr>
                        </thead>
                        <tbody>
```

En `resources/js/components/FichaProyecto.jsx`, reemplazar (2 de 2):

```jsx
                                    <td data-etiqueta="Estado"><span className={`ct-estado ct-estado-${f.tono}`}>{f.estado}</span></td>
                                    <td data-etiqueta="Valor" className="numero">{f.monto}</td>
                                    <td data-etiqueta="Contratos">{f.contratos}</td>
                                </tr>
                            ))}
                        </tbody>
```

por:

```jsx
                                    <td data-etiqueta="Estado"><span className={`ct-estado ct-estado-${f.tono}`}>{f.estado}</span></td>
                                    <td data-etiqueta="Valor" className="numero">{f.monto}</td>
                                    <td data-etiqueta="Contratos">{f.contratos}</td>
                                    <td data-etiqueta="Expediente">
                                        {f.expediente.texto}{f.expediente.cerrado ? ' · Cerrado' : ''}
                                        {f.expediente.aviso && <span className="fp-nota"> {f.expediente.aviso}</span>}
                                        {' '}
                                        <Link to={f.rutaChecklist} className="ct-enlace">Checklist</Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (1 de 3):

```jsx
import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowRightArrowLeft, faArrowUpRightFromSquare, faFileSignature, faGavel, faPen, faPlus, faTrash, faXmark } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
import { mensajeApi } from '../utils/plantillasChecklist';
import {
    CAMPOS_PROCESO, ESTADOS_PROCESO, MAXIMO_OBSERVACION, MAXIMO_OBSERVACIONES_PROCESO, PLATAFORMAS, avisoCambioEstado, datosEstado, datosProceso,
    erroresPorCampo, esEdicionDesactualizada, etiquetaEstadoProceso, etiquetaPlataforma, formularioProceso,
    modoTrasGuardarContrato, numeroProceso, opcionesConActual, opcionesFuenteProceso, ordenarCatalogo, puedeEditarProcesos, puedeRegistrarContrato,
    textoContratos, textoFecha, textoValor, tonoEstado, validarProceso, valorEnEdicion,
```

por:

```jsx
import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowRightArrowLeft, faArrowUpRightFromSquare, faClipboardCheck, faFileSignature, faGavel, faPen, faPlus, faTrash, faXmark } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { Link } from 'react-router-dom';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
import { rutaExpedienteContratacion } from '../utils/navegacion';
import { mensajeApi } from '../utils/plantillasChecklist';
import {
    avanceExpediente, CAMPOS_PROCESO, ESTADOS_PROCESO, MAXIMO_OBSERVACION, MAXIMO_OBSERVACIONES_PROCESO, PLATAFORMAS, avisoCambioEstado, datosEstado, datosProceso,
    erroresPorCampo, esEdicionDesactualizada, etiquetaEstadoProceso, etiquetaPlataforma, formularioProceso,
    modoTrasGuardarContrato, numeroProceso, opcionesConActual, opcionesFuenteProceso, ordenarCatalogo, puedeEditarProcesos, puedeRegistrarContrato,
    textoContratos, textoFecha, textoValor, tonoEstado, validarProceso, valorEnEdicion,
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (2 de 3):

```jsx
    );
};

/**
 * Procesos de contratación de un proyecto (la obra, la interventoría…), en un diálogo que abre
 * Parámetros → Proyectos. Un Administrador o Gestor los crea, edita, cambia de estado y elimina;
```

por:

```jsx
    );
};

/**
 * El avance del expediente del proceso (su propio conteo de requisitos), su estado si está
 * cerrado, el aviso de plantilla y el enlace al checklist. Al navegar, la página que abrió el
 * diálogo se desmonta y con ella el diálogo, que devuelve el foco y se cierra limpio.
 */
const ExpedienteProceso = ({ proceso }) => {
    const avance = avanceExpediente(proceso.expediente);
    return (
        <>
            <div>
                <dt>Expediente</dt>
                <dd>
                    {avance.texto}{avance.cerrado ? ' · Cerrado' : ''}
                    {' '}
                    <Link to={rutaExpedienteContratacion(proceso.id)} className="ct-enlace">
                        <FontAwesomeIcon icon={faClipboardCheck} aria-hidden="true" /> Checklist
                    </Link>
                </dd>
            </div>
            {avance.aviso && <div><dt>Plantilla</dt><dd>{avance.aviso}</dd></div>}
        </>
    );
};

/**
 * Procesos de contratación de un proyecto (la obra, la interventoría…), en un diálogo que abre
 * Parámetros → Proyectos. Un Administrador o Gestor los crea, edita, cambia de estado y elimina;
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (3 de 3):

```jsx
                                                <div><dt>Apertura</dt><dd>{textoFecha(p.fecha_apertura)}</dd></div>
                                                <div><dt>Cierre</dt><dd>{textoFecha(p.fecha_cierre)}</dd></div>
                                                <div><dt>Contratos vinculados</dt><dd>{textoContratos(p.contratos)}</dd></div>
                                            </dl>
                                            {p.observaciones && <p className="pl-ayuda">{p.observaciones}</p>}
                                            {modo?.tipo === 'estado' && modo.proceso.id === p.id ? (
```

por:

```jsx
                                                <div><dt>Apertura</dt><dd>{textoFecha(p.fecha_apertura)}</dd></div>
                                                <div><dt>Cierre</dt><dd>{textoFecha(p.fecha_cierre)}</dd></div>
                                                <div><dt>Contratos vinculados</dt><dd>{textoContratos(p.contratos)}</dd></div>
                                                <ExpedienteProceso proceso={p} />
                                            </dl>
                                            {p.observaciones && <p className="pl-ayuda">{p.observaciones}</p>}
                                            {modo?.tipo === 'estado' && modo.proceso.id === p.id ? (
```

En `resources/js/utils/contratacion.js`, reemplazar (1 de 1):

```js
    return n === 1 ? '1 contrato' : `${n} contratos`;
};

/** Solo un enlace http(s) completo se muestra como enlace: nunca javascript: ni rutas relativas. */
export const esEnlaceWeb = (texto) => {
    const valor = String(texto ?? '').trim();
```

por:

```js
    return n === 1 ? '1 contrato' : `${n} contratos`;
};

/**
 * El avance del expediente de un proceso (el `expediente` de la API del proceso, o null): su
 * propio conteo de requisitos, nunca sumado con el de la formulación ni el de un contrato.
 */
export const avanceExpediente = (expediente) => {
    if (!expediente) return { tiene: false, texto: 'Sin expediente', porcentaje: null, cerrado: false, aviso: null };
    const { total = 0, cumplidos = 0, porcentaje = 0 } = expediente.progreso ?? {};
    return {
        tiene: true,
        texto: `${cumplidos} de ${total} ${total === 1 ? 'requisito' : 'requisitos'} · ${porcentaje} %`,
        porcentaje,
        cerrado: expediente.estado === 'cerrado',
        aviso: String(expediente.aviso_plantilla ?? '').trim() || null,
    };
};

/** Solo un enlace http(s) completo se muestra como enlace: nunca javascript: ni rutas relativas. */
export const esEnlaceWeb = (texto) => {
    const valor = String(texto ?? '').trim();
```

En `resources/js/utils/fichaProyecto.js`, reemplazar (1 de 2):

```js
// probadas en fichaProyecto.test.js.

import { esIdContrato } from './contratosModulo';
import { etiquetaEstadoProceso, numeroProceso, textoContratos, textoFecha, textoValor, tonoEstado } from './contratacion';
import { faseLegible } from './expedienteChecklist';
import { mensajeApi } from './plantillasChecklist';

/** Si el `:proyectoId` de la ruta es un id: la misma regla que el de un contrato (lo que acepta la API). */
```

por:

```js
// probadas en fichaProyecto.test.js.

import { esIdContrato } from './contratosModulo';
import { avanceExpediente, etiquetaEstadoProceso, numeroProceso, textoContratos, textoFecha, textoValor, tonoEstado } from './contratacion';
import { faseLegible } from './expedienteChecklist';
import { rutaExpedienteContratacion } from './navegacion';
import { mensajeApi } from './plantillasChecklist';

/** Si el `:proyectoId` de la ruta es un id: la misma regla que el de un contrato (lo que acepta la API). */
```

En `resources/js/utils/fichaProyecto.js`, reemplazar (2 de 2):

```js
        tono: tonoEstado(proceso.estado),
        monto: textoValor(proceso.monto),
        contratos: textoContratos(proceso.contratos),
    };
};
```

por:

```js
        tono: tonoEstado(proceso.estado),
        monto: textoValor(proceso.monto),
        contratos: textoContratos(proceso.contratos),
        expediente: avanceExpediente(proceso.expediente),
        rutaChecklist: rutaExpedienteContratacion(proceso.id),
    };
};
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/contratacion.test.js resources/js/utils/fichaProyecto.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1202 passed.

Run: `npm test`
Expected: 708 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/js/components/FichaProyecto.jsx resources/js/components/ProcesosProyecto.jsx resources/js/utils/contratacion.js resources/js/utils/contratacion.test.js resources/js/utils/fichaProyecto.js resources/js/utils/fichaProyecto.test.js
git commit -m "feat: expedient progress and checklist links in processes and the project page"
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
git commit -m "build: production bundle for procurement phase 3"
```

Los archivos que no cambiaron se omiten.

---

### Tarea 8: Verificación en la copia descartable

Usa la copia y las fotos de la tarea 1. Nunca `gest_pro`.

- [ ] **Paso 1: Migrar, revertir y volver a migrar, comparando**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

Expected: se aplica `2026_11_18_100019_add_tipo_y_proceso_a_expedientes`.

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c6_despues_$f.json" gestpro_verif_tmp && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c6_antes_$f.json" "$TMP_DIR/c6_despues_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=1 --force
```

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c6_rb_$f.json" gestpro_verif_tmp --solo-tablas && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c6_tablas_antes_$f.json" "$TMP_DIR/c6_rb_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c6_re_$f.json" gestpro_verif_tmp && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c6_antes_$f.json" "$TMP_DIR/c6_re_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

- [ ] **Paso 2: Probar en el navegador sobre la copia**

Compilar en desarrollo (`npx mix`) si `public/` no tiene el código de esta etapa. Arrancar el servidor desde `public/`, en un puerto libre (`netstat -ano | grep 8796`):

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8796 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Crear un Administrador de prueba solo en la copia. Nunca usar sesiones de usuarios reales de `gest_pro`:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'verif@gestpro.test', 'rol' => App\Enums\Rol::Administrador]);"
```

Entrar por `http://127.0.0.1:8796/GestPro/`, borrando `localStorage` si hay una sesión anterior. **Solo en la copia**, preparar los datos con la API, usando el token de la sesión:
- publicar la versión borrador de "Licitación pública – SECOP II" con `POST /catalogo-requisitos/versiones/{id}/publicar`;
- cambiar la modalidad del proceso a licitación pública con `PUT /procesos/{id}`.

Comprobar:

1. **El expediente nace solo:** al cambiar la modalidad, el proceso trae `expediente` con 25 requisitos.
2. **`/contratacion/<id del proceso>`:**
   - muestra la cabecera del proceso y su proyecto, el avance 0/25 y las 11 etapas con sus requisitos y su avance;
   - marcar un requisito actualiza el total y su etapa.
3. **"Cerrar expediente":**
   - pide confirmación con los pendientes por etapa;
   - al confirmar, queda "Cerrado el …" y toda la página en solo lectura;
   - `PATCH /expedientes/{id}/requisitos/{r}` responde 422 "El expediente está cerrado."
4. **La viabilización no cambia:**
   - `GET /expedientes` sigue con 10 expedientes;
   - `/viabilizacion/<proyecto con expediente>` se ve como antes;
   - en la ficha del proyecto del proceso, Formulación sigue diciendo lo de antes, y Procesos muestra el avance del expediente ("1 de 25 requisitos · 4 % · Cerrado") con el enlace "Checklist".
5. **Borrar un proyecto con expediente de contratación**, con `POST /eliminarProyecto` en la copia, funciona en MariaDB, sin el error 1451.

La consola del navegador no muestra errores nuevos. Los 401 de una sesión vieja y los 422 de las validaciones probadas son esperados.

- [ ] **Paso 3: Limpiar**

Detener el servidor, eliminar la copia y devolver `public/` a lo comiteado si se compiló en desarrollo:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 4: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas, 1202.

Run: `npm test`
Expected: pasan todas, 708.

---

### Tarea 9: Informar y pedir validación

- [ ] **Paso 1: Actualizar el estado de la propuesta**

En `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, reemplazar:

```markdown
La etapa 3C (SECOP II y ficha del proyecto, sin migraciones) está validada (2026-11-18).
```

por:

```markdown
La etapa 3C (SECOP II y ficha del proyecto, sin migraciones) está validada (2026-11-18). La fase 3 (expediente de contratación por proceso, migración 20) está implementada y verificada en copia, pendiente de validar y aplicar en `gest_pro`.
```

```bash
git add docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md
git commit -m "docs: mark procurement phase 3 as implemented"
```

- [ ] **Paso 2: Informar**

Informar:
- los totales de pruebas;
- las comparaciones (migrar, revertir y volver a migrar);
- lo revisado en el navegador.

Pedir **dos** decisiones por separado:
1. si valida la fase;
2. si autoriza aplicar la migración 20 en `gest_pro`.

Recordar que, con los datos actuales, ningún proceso recibe expediente hasta que se publique una plantilla de contratación que le corresponda.

---

### Tarea 10: Aplicar en `gest_pro` (solo con autorización)

No ejecutar esta tarea sin un sí explícito del usuario a este paso. Si la comparación muestra diferencias, restaurar el respaldo: no usar `migrate:rollback`.

- [ ] **Paso 1: Respaldar**

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro > "$TMP_DIR/gest_pro_antes_contratacion_fase3.sql"
```

Expected: el archivo existe y termina en `-- Dump completed`.

- [ ] **Paso 2: Tomar las fotos antes de migrar (solo tablas)**

```bash
for f in 2 3; do php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c6_real_antes_$f.json" gest_pro --solo-tablas; done
```

- [ ] **Paso 3: Migrar hacia adelante**

Run: `php artisan migrate --force`
Expected: se aplica `2026_11_18_100019_add_tipo_y_proceso_a_expedientes`.

- [ ] **Paso 4: Comparar**

```bash
for f in 2 3; do php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c6_real_despues_$f.json" gest_pro && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c6_real_antes_$f.json" "$TMP_DIR/c6_real_despues_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

- [ ] **Paso 5: Informar**

Dar el resultado y la ruta completa del respaldo, y avisar que está en una carpeta temporal.

---

## Criterio de la etapa

La etapa está lista cuando:

- cada proceso puede tener su expediente de contratación, creado solo o a mano, con los requisitos por etapa y su avance;
- el expediente se cierra con aviso y queda congelado;
- la viabilización no cambia: mismas respuestas, mismas cifras, y sus pruebas y la paridad del evaluador pasan;
- no hay tablas de checklist nuevas;
- un proceso con expediente no se elimina, y borrar un proyecto con expediente funciona en MariaDB;
- ningún dato cambia al migrar ni al revertir.
