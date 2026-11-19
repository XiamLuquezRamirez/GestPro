# Contratación, etapa 4A: peso por requisito y avance ponderado. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** que cada requisito de una plantilla de contratación tenga un peso, y que el porcentaje de avance de un expediente de contratación pondere cada requisito por su peso. Los conteos ("N de M requisitos") y el cierre con pendientes siguen contando requisitos. La viabilización no cambia: ignora el peso.

**Arquitectura:**
- **Migración 21:** `requisitos_catalogo` gana `peso` (smallint sin signo, 1 por defecto, después de `etapa_id`). Todos los requisitos existentes quedan con 1. El `down()` se detiene si algún peso es distinto de 1.
- **Plantillas:** `CatalogoService` valida y guarda el peso (solo en contratación), y lo copia al crear una versión nueva. `PlantillaService::duplicar` también lo copia.
- **Avance ponderado:** `ExpedienteService` calcula el progreso de contratación con `peso_total` y `peso_cumplido`, en total y por etapa. Lo usan la presentación del expediente, las respuestas parciales, `VersionesExpediente` y `ExpedientesContratacion::resumenDeProcesos`.
- **Pantallas:** el campo "Peso" en el constructor de plantillas (`PlantillaRequisitos.jsx`) y, en `ExpedienteContratacion.jsx`, la nota de ponderación y la etiqueta "Peso N" en cada fila.

**Tecnologías:** Laravel 12, PHP 8.2, MariaDB 10.4, React 18 con Laravel Mix y react-router, PHPUnit sobre SQLite en memoria, Vitest.

**Fuente:**
- La propuesta `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, aprobada:
  - fase 4, que el usuario dividió en **4A** (esta: peso y avance ponderado) y **4B** (fechas por etapa y avisos);
  - regla R10 y criterios 9 y 10 de la sección 9;
  - sección 6: `requisitos_catalogo.peso`, smallint sin signo, 1 por defecto, se ignora en viabilización;
  - sección 7: el campo `peso` solo se ve en las plantillas de uso contratación;
  - sección 8: la migración se detiene al revertir si algún peso es distinto de 1.

**Estado de partida:** rama `feature/contratacion`, igual a `main` (`71de934`). La fase 3 está validada y aplicada en `gest_pro`. Pasan 1202 pruebas PHP y 708 JS.

**Datos reales comprobados:**
- **Expedientes:** 10 de viabilización y 0 de contratación.
- **Requisitos de catálogo:** 237.
- **Plantillas de contratación:** solo "Licitación pública – SECOP II", **en borrador**.

Al aplicar esta etapa todos los requisitos quedan con peso 1, así que **ningún porcentaje cambia** hasta que alguien le ponga a un requisito un peso distinto.

---

## Reglas de ejecución

- **Nada de esto se aplica a `gest_pro` sin autorización del usuario.** Allí nunca se usa `migrate:rollback`: si hay que volver atrás, se restaura el respaldo. La tarea 8 aplica la migración 21, y solo con un sí explícito.
- **La viabilización no cambia ni una respuesta ni una cifra.** Las pruebas existentes de `tests/Feature/Viabilizacion/**`, los fixtures de paridad (`tests/Fixtures/paridad_*`) y `ExpedientesContratacionAisladosTest` no se modifican y deben pasar. La única prueba existente que cambia es `ExpedienteContratacionApiTest`, de contratación: sus resúmenes de avance ganan `peso_total` y `peso_cumplido`.
- Commits autorizados solo en `feature/contratacion`. Sin push. Mensajes en inglés, terminados con la línea `Co-Authored-By` del modelo que comitea.
- Nomenclatura en español sin tildes para tablas, columnas, clases y métodos nuevos. Los textos para el usuario van en español con tuteo. Las migraciones, que son herramientas del operador, conservan el registro de usted.
- Pruebas: `php artisan test` sin definir `DB_DATABASE`, para que use SQLite en memoria, y `npm test`. Si Vitest crea `resources/js/node_modules`, se borra.
- **Aplicar los bloques exactos.** Cada reemplazo aparece una sola vez en su archivo. Si no lo encuentras tal cual, detente y avisa. Se recomienda aplicarlos con un script que los lea del plan y exija una coincidencia por reemplazo.
- **No usar `git stash`:** el usuario tiene stashes propios.
- El usuario suele tener `npm run watch` corriendo, que reescribe `public/`. Solo la tarea 5 comitea `public/`, después de `npm run production`.
- No tocar ni comitear `public/images/marca-completa.png` ni `public/images/marca-completa1.png`: son cambios del usuario.
- `.env` está versionado: nunca se agrega a un commit. Comitear siempre con rutas explícitas.
- `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio. Hay que definirlo en cada comando, porque el estado de la terminal no se conserva.
- **MariaDB no hace transaccional el DDL.** Por eso cada paso de la migración 21 lleva su guarda (`hasColumn`) y se puede reintentar. La tarea 6 comprueba en MariaDB que el `down()` se detiene con un peso distinto de 1.
- **El prefijo de la API (`/GestPro`) es también la base del SPA.** `/contratacion/:procesoId` y `/plantillas-checklist` no son rutas GET de la API.

## Decisiones de este plan

**Datos**
- `requisitos_catalogo.peso`: entero sin signo, NOT NULL, 1 por defecto. `RequisitoCatalogo` lo tiene en `fillable` y con cast a entero.
- Crear una versión nueva (`CatalogoService::nuevaVersion`) y duplicar una plantilla copian el peso.
- El `down()` de la migración 21 se detiene con un mensaje claro si algún peso es distinto de 1, para no perder información. Si todos valen 1, quita la columna.

**Constructor de plantillas**
- **Contratación:** `peso` es opcional, entero de 1 a 100. Sin el campo, el requisito conserva el que tenía (1 al crearlo).
- **Viabilización:** un `peso` distinto de 1 se rechaza con "Los requisitos de una plantilla de viabilización no llevan peso.". Con 1 o sin el campo se acepta, y siempre se guarda 1: editar un requisito sin peso lo deja en 1.
- La validación del controlador exige un entero de verdad, como para `etapa_id`, con el atributo "el peso". Los errores del peso también van en el campo `peso`, para mostrarlos bajo el input.
- **Pantalla:** en contratación, un input numérico "Peso" (1 a 100, 1 por defecto) junto a la etapa, con la ayuda "Cuánto pesa en el avance del proceso. 1 si todos valen lo mismo.". La lista muestra "Peso N" solo si N es distinto de 1. En viabilización no aparece nada.
- Publicar no exige nada nuevo.

**Avance ponderado (R10)**
- El peso de una fila del expediente es el del **requisito que muestra**, el mismo que le da la etapa: el primer origen por posición de plantilla, o el ancla. Un requisito propio **pesa siempre 1**: no se pide peso al crearlo.
- En contratación el progreso es `{total, cumplidos, porcentaje, peso_total, peso_cumplido}`:
  - `total` y `cumplidos` siguen siendo **conteos**, para "N de M requisitos" y para el cierre con pendientes;
  - `porcentaje = round(peso_cumplido / peso_total * 100)`, o 0 si `peso_total` es 0;
  - con todos los pesos en 1, el porcentaje es idéntico al de antes.
- El avance por etapa trae también `peso_total` y `peso_cumplido` de cada etapa.
- Los opcionales y los "no aplica" aprobados no cuentan, y su peso tampoco.
- La vista previa de pasar un expediente de contratación a una versión nueva de su plantilla cuenta como cambio uno solo de peso o de etapa.
- Lo usan la presentación del expediente, las respuestas parciales, `VersionesExpediente` (progreso antes y después) y `ExpedientesContratacion::resumenDeProcesos`. Este último carga juntas las filas que cuentan de todos los expedientes: sus consultas son fijas, no crecen con los procesos.
- **Viabilización:** el progreso no cambia, ni siquiera con un peso distinto de 1 escrito directo en la base (criterio 10). Sus filas no traen `peso`; las de contratación sí.
- Prueba del criterio 9 con valores fijos: 5 requisitos, uno de peso 3 y cuatro de peso 1. Cumplir solo el de peso 3 da `peso_cumplido = 3`, `peso_total = 7`, `porcentaje = 43`, `cumplidos = 1` y `total = 5`.

**Expediente en pantalla**
- El medidor general y el de cada etapa siguen diciendo "N / M requisitos cumplidos", con el porcentaje ya ponderado.
- Si algún requisito que cuenta pesa distinto de 1, bajo el medidor general aparece la nota "El porcentaje pondera cada requisito por su peso.", ligada a la barra de progreso para los lectores de pantalla.
- Cada fila con peso distinto de 1 muestra la etiqueta "Peso N".
- La ficha del proyecto y la lista de procesos no cambian de forma: el porcentaje ya llega ponderado.

## Fuera de esta etapa

- **Etapa 4B:** la tabla `expediente_etapas`, las fechas programada y real por etapa, y los avisos.
- **Fase 5:** revisión por etapa.
- **Fase 6:** tablero de contratación.

## Lo que ya se ensayó

El plan se ensayó completo en un clon descartable fuera del repositorio, y este documento se generó con el código de ese ensayo. Un script comprobó que aplicar sus bloques sobre la rama reproduce el ensayo exactamente.

- **Pruebas:** con el código de las tareas 2 a 4 pasan 1229 pruebas PHP y 722 JS. Cada tarea intermedia también pasa sus suites completas. Ninguna prueba existente de viabilización ni fixture de paridad se modificó.
- **En una copia de `gest_pro` con los datos reales,** las fotos de la tarea 1 no mostraron ninguna diferencia al migrar, al revertir la migración 21 (comparando solo tablas) ni al volver a migrar. Los 237 requisitos de catálogo quedaron con peso 1.
- **En MariaDB** se confirmó que, con un requisito de peso 3, revertir la migración 21 se detiene con su mensaje y deja la columna en su sitio.
- **En el navegador, sobre esa copia:**
  - **Constructor:** en `/plantillas-checklist`, pestaña Contratación, "Estudios previos" de la plantilla de licitación se editó con Peso 3, y la lista mostró "Peso 3".
  - **Expediente:** con la versión publicada solo en la copia y el proceso 1 pasado a licitación pública, el expediente nació con 25 requisitos y `peso_total` 27.
  - **Página `/contratacion/1`:** mostró la nota "El porcentaje pondera cada requisito por su peso." y la etiqueta "Peso 3". Al marcar "Estudios previos" quedó "1 / 25" con 11 %, y la etapa 1 al 50 %.
  - **Ficha `/proyectos/3`:** el bloque Procesos mostró "1 de 25 requisitos · 11 %".
  - **Consola:** sin errores nuevos; solo un 401 de una sesión previa y el 422 esperado.
- **Una revisión independiente** no encontró hallazgos altos ni medios. Sus tres hallazgos bajos ya están corregidos en las tareas correspondientes:
  - la vista previa de una versión nueva mostraba "sin cambios" cuando solo cambiaban el peso o la etapa;
  - la nota del medidor no estaba ligada a la barra de progreso para los lectores de pantalla;
  - el progreso cargaba relaciones de etapa que no necesitaba.

---

### Tarea 1: Punto de partida y fotos con el código actual

Las fotos se toman **antes de cambiar código**. La copia `gestpro_verif_tmp` se conserva hasta la tarea 6.

- [ ] **Paso 1: Comprobar la rama, el estado y las pruebas**

Run: `git branch --show-current`
Expected: `feature/contratacion`

Run: `git status --short`
Expected: como mucho, `public/` (si corre `npm run watch`) y las dos imágenes del usuario.

Run: `php artisan test`
Expected: pasan todas, 1202. Si alguna falla, detenerse y avisar.

Run: `npm test`
Expected: pasan todas, 708.

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
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c7_antes_$f.json" gestpro_verif_tmp; DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c7_tablas_antes_$f.json" gestpro_verif_tmp --solo-tablas; done
```

Expected: `Expedientes: 10, filas: 293, plantillas: 3, requisitos: 237` y `Contratos: 7, procesos: 1, actas: 2, expedientes: 10`, con las cifras actuales de `gest_pro`.

---

### Tarea 2: Migración 21: peso por requisito de plantilla

`requisitos_catalogo` gana `peso` (1 por defecto). El constructor de plantillas lo valida y lo guarda solo en contratación, y las versiones nuevas y las plantillas duplicadas lo copian.

**Files:**
- Create: `database/migrations/2026_11_19_100020_add_peso_a_requisitos_catalogo.php`
- Test (create): `tests/Feature/Schema/PesoRequisitosTest.php`
- Test (create): `tests/Feature/Viabilizacion/PesoRequisitosApiTest.php`
- Modify: `app/Http/Controllers/CatalogoRequisitosController.php`
- Modify: `app/Models/RequisitoCatalogo.php`
- Modify: `app/Services/Viabilizacion/CatalogoService.php`
- Modify: `app/Services/Viabilizacion/PlantillaService.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Schema/PesoRequisitosTest.php`:

```php
<?php

namespace Tests\Feature\Schema;

use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Migración 21: peso de cada requisito de plantilla (requisitos_catalogo.peso). Las pruebas no
 * crean filas hijas del requisito: en SQLite, quitar una columna reconstruye la tabla y, dentro
 * de la transacción de RefreshDatabase, eso borraría en cascada las filas hijas. En MariaDB no pasa.
 */
class PesoRequisitosTest extends TestCase
{
    use RefreshDatabase;

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_19_100020_add_peso_a_requisitos_catalogo.php');
    }

    private function columna(): ?array
    {
        return collect(Schema::getColumns('requisitos_catalogo'))->firstWhere('name', 'peso');
    }

    /** Un requisito insertado como lo haría el código anterior a la migración: sin peso. */
    private function requisito(array $cambios = []): int
    {
        $plantilla = PlantillaChecklist::create([
            'nombre' => 'Plantilla ' . (DB::table('plantillas_checklist')->count() + 1), 'motor' => PlantillaChecklist::MOTOR_SIMPLE,
            'todas_fuentes' => true, 'todos_sectores' => true,
        ]);
        $version = DB::table('catalogo_versiones')->insertGetId([
            'plantilla_id' => $plantilla->id, 'numero' => 1, 'estado' => CatalogoVersion::BORRADOR, 'vigente' => false,
        ]);

        return DB::table('requisitos_catalogo')->insertGetId([
            'catalogo_version_id' => $version, 'clave' => 'r1', 'texto' => 'Estudios previos', ...$cambios,
        ]);
    }

    private function asertarMigrada(): void
    {
        $peso = $this->columna();
        $this->assertNotNull($peso);
        $this->assertFalse($peso['nullable']);
        $this->assertStringContainsString('1', (string) $peso['default']);
    }

    public function test_los_requisitos_tienen_peso_obligatorio_que_por_defecto_vale_1(): void
    {
        $this->asertarMigrada();

        $id = $this->requisito();
        $this->assertSame(1, (int) DB::table('requisitos_catalogo')->where('id', $id)->value('peso'));
    }

    public function test_los_requisitos_existentes_quedan_con_peso_1(): void
    {
        $migracion = $this->migracion();
        $migracion->down();
        $id = $this->requisito();

        $migracion->up();

        $this->assertSame(1, (int) DB::table('requisitos_catalogo')->where('id', $id)->value('peso'));
    }

    public function test_el_modelo_conoce_el_peso_como_entero(): void
    {
        $requisito = RequisitoCatalogo::findOrFail($this->requisito());
        $this->assertSame(1, $requisito->peso);

        $requisito->update(['peso' => '3']);
        $this->assertSame(3, $requisito->fresh()->peso);
    }

    public function test_el_rollback_se_detiene_si_algun_peso_es_distinto_de_1(): void
    {
        $id = $this->requisito(['peso' => 3]);

        try {
            $this->migracion()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('peso', $e->getMessage());
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }
        $this->asertarMigrada();
        $this->assertSame(3, (int) DB::table('requisitos_catalogo')->where('id', $id)->value('peso'));

        DB::table('requisitos_catalogo')->where('id', $id)->update(['peso' => 1]);
        $this->migracion()->down();
        $this->assertFalse(Schema::hasColumn('requisitos_catalogo', 'peso'));
        $this->assertTrue(DB::table('requisitos_catalogo')->where('id', $id)->exists());
    }

    public function test_en_ambos_sentidos_se_puede_repetir_y_conserva_los_requisitos(): void
    {
        $migracion = $this->migracion();
        $id = $this->requisito();

        $migracion->up(); // ya migrada: no falla
        $this->asertarMigrada();

        $migracion->down();
        $migracion->down();
        $this->assertFalse(Schema::hasColumn('requisitos_catalogo', 'peso'));

        $migracion->up();
        $migracion->up();
        $this->asertarMigrada();
        $this->assertSame('Estudios previos', DB::table('requisitos_catalogo')->where('id', $id)->value('texto'));
        $this->assertSame(1, (int) DB::table('requisitos_catalogo')->where('id', $id)->value('peso'));
    }
}
```

Crear `tests/Feature/Viabilizacion/PesoRequisitosApiTest.php`:

```php
<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Peso de cada requisito en la administración de plantillas: solo lo llevan las de contratación. */
class PesoRequisitosApiTest extends TestCase
{
    use RefreshDatabase;

    private const SIN_PESO = 'Los requisitos de una plantilla de viabilización no llevan peso.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
    }

    private function comoAdmin(): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => Rol::Administrador])));
    }

    private function etapa(string $clave = 'planeacion'): EtapaContratacion
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail();
    }

    /** Plantilla creada por la API (de contratación si no se indica otro uso), con su borrador. */
    private function plantilla(string $uso = PlantillaChecklist::USO_CONTRATACION): PlantillaChecklist
    {
        $id = $this->comoAdmin()->postJson('/GestPro/plantillas', [
            'nombre' => $uso === PlantillaChecklist::USO_CONTRATACION ? 'Licitación pública – SECOP II' : 'Inversión',
            'uso' => $uso,
            'todas_fuentes' => true,
            'todos_sectores' => true,
            'modalidad_ids' => $uso === PlantillaChecklist::USO_CONTRATACION ? [Modalidad::where('clave', 'licitacion_publica')->value('id')] : [],
        ])->assertCreated()->json('id');

        return PlantillaChecklist::findOrFail($id);
    }

    private function borrador(PlantillaChecklist $plantilla): CatalogoVersion
    {
        return $plantilla->versiones()->where('estado', CatalogoVersion::BORRADOR)->firstOrFail();
    }

    private function requisitos(CatalogoVersion $version): string
    {
        return "/GestPro/catalogo-requisitos/versiones/{$version->id}/requisitos";
    }

    /** Requisito de contratación: con su etapa, salvo que se indique otra cosa. */
    private function crearRequisito(CatalogoVersion $version, array $datos = [])
    {
        return $this->comoAdmin()->postJson($this->requisitos($version), [
            'texto' => 'Estudios previos', 'etapa_id' => $this->etapa()->id, ...$datos,
        ]);
    }

    // ------------------------------------------------------------- contratación

    public function test_en_contratacion_el_peso_es_opcional_y_por_defecto_vale_1(): void
    {
        $borrador = $this->borrador($this->plantilla());

        $this->crearRequisito($borrador)->assertCreated()->assertJsonPath('peso', 1);
        $this->crearRequisito($borrador, ['texto' => 'CDP', 'peso' => 3])->assertCreated()->assertJsonPath('peso', 3);
        $this->crearRequisito($borrador, ['texto' => 'Pliego', 'peso' => 100])->assertCreated()->assertJsonPath('peso', 100);

        $this->assertSame([1, 3, 100], $borrador->requisitos()->orderBy('id')->pluck('peso')->all());
    }

    public function test_al_editar_sin_peso_en_el_pedido_conserva_el_que_tenia(): void
    {
        $borrador = $this->borrador($this->plantilla());
        $id = $this->crearRequisito($borrador, ['peso' => 3])->assertCreated()->json('id');
        $url = $this->requisitos($borrador) . "/{$id}";

        $this->comoAdmin()->putJson($url, ['texto' => 'Estudios previos y anexos'])->assertOk()->assertJsonPath('peso', 3);
        $this->comoAdmin()->putJson($url, ['texto' => 'Estudios previos', 'peso' => 5])->assertOk()->assertJsonPath('peso', 5);
        $this->comoAdmin()->putJson($url, ['texto' => 'Estudios previos', 'peso' => 1])->assertOk()->assertJsonPath('peso', 1);
        $this->assertSame(1, RequisitoCatalogo::findOrFail($id)->peso);
    }

    public function test_el_peso_debe_ser_un_entero_de_1_a_100(): void
    {
        $borrador = $this->borrador($this->plantilla());

        foreach (['3', true, 1.5, null] as $valor) {
            $this->crearRequisito($borrador, ['peso' => $valor])->assertStatus(422)
                ->assertJsonPath('errors.peso.0', 'Revisa el peso: debe ser un número entero.');
        }
        $this->crearRequisito($borrador, ['peso' => 0])->assertStatus(422)
            ->assertJsonPath('errors.peso.0', 'Revisa el peso: el mínimo es 1.');
        $this->crearRequisito($borrador, ['peso' => 101])->assertStatus(422)
            ->assertJsonPath('errors.peso.0', 'Revisa el peso: el máximo es 100.');

        $this->assertSame(0, $borrador->requisitos()->count());
    }

    public function test_la_lista_de_requisitos_trae_el_peso(): void
    {
        $borrador = $this->borrador($this->plantilla());
        $this->crearRequisito($borrador, ['peso' => 4])->assertCreated();

        $this->assertSame(4, $this->comoAdmin()->getJson($this->requisitos($borrador))->assertOk()->json('0.peso'));
    }

    public function test_un_borrador_nuevo_y_una_copia_conservan_el_peso_de_cada_requisito(): void
    {
        $plantilla = $this->plantilla();
        $primero = $this->borrador($plantilla);
        $this->crearRequisito($primero, ['peso' => 7])->assertCreated();
        $this->crearRequisito($primero, ['texto' => 'CDP'])->assertCreated();
        $this->comoAdmin()->postJson("/GestPro/catalogo-requisitos/versiones/{$primero->id}/publicar")->assertOk();

        $segundo = app(CatalogoService::class)->crearBorrador('Ajustes', $plantilla);
        $this->assertSame([7, 1], $segundo->requisitos()->orderBy('id')->pluck('peso')->all());

        $copia = $this->comoAdmin()->postJson("/GestPro/plantillas/{$plantilla->id}/duplicar", ['nombre' => 'Licitación pública – copia'])
            ->assertCreated()->json('id');
        $this->assertSame([7, 1], $this->borrador(PlantillaChecklist::findOrFail($copia))->requisitos()->orderBy('orden')->pluck('peso')->all());
    }

    // ------------------------------------------------------------- viabilización

    public function test_en_viabilizacion_un_peso_distinto_de_1_se_rechaza_tambien_en_su_campo(): void
    {
        $borrador = $this->borrador($this->plantilla(PlantillaChecklist::USO_VIABILIZACION));

        $this->comoAdmin()->postJson($this->requisitos($borrador), ['texto' => 'Estudios', 'peso' => 3])->assertStatus(422)->assertExactJson([
            'mensaje' => 'El requisito no es válido.',
            'errores' => [self::SIN_PESO],
            'errors' => ['peso' => [self::SIN_PESO]],
        ]);
        $this->assertSame(0, $borrador->requisitos()->count());

        $this->comoAdmin()->postJson($this->requisitos($borrador), ['texto' => 'Con peso 1', 'peso' => 1])->assertCreated()->assertJsonPath('peso', 1);
        $this->comoAdmin()->postJson($this->requisitos($borrador), ['texto' => 'Sin peso'])->assertCreated()->assertJsonPath('peso', 1);
    }

    public function test_en_la_plantilla_sgr_un_peso_distinto_de_1_se_rechaza(): void
    {
        $borrador = app(CatalogoService::class)->crearBorrador('Ajustes');
        $datos = ['clave' => 'a99', 'seccion' => 'A', 'fuente' => 'a12', 'codigo' => '99', 'texto' => 'Nuevo', 'condicion' => 'siempre'];

        $this->comoAdmin()->postJson($this->requisitos($borrador), [...$datos, 'peso' => 2])->assertStatus(422)
            ->assertJsonPath('errores', [self::SIN_PESO])->assertJsonPath('errors', ['peso' => [self::SIN_PESO]]);

        $this->comoAdmin()->postJson($this->requisitos($borrador), [...$datos, 'peso' => 1])->assertCreated()->assertJsonPath('peso', 1);
    }

    public function test_en_viabilizacion_guardar_deja_el_peso_en_1_aunque_la_base_tuviera_otro(): void
    {
        $borrador = $this->borrador($this->plantilla(PlantillaChecklist::USO_VIABILIZACION));
        $id = $this->comoAdmin()->postJson($this->requisitos($borrador), ['texto' => 'Estudios'])->assertCreated()->json('id');
        // Escrito directo en la base: la administración no deja ponerlo.
        RequisitoCatalogo::whereKey($id)->update(['peso' => 4]);

        $this->comoAdmin()->putJson($this->requisitos($borrador) . "/{$id}", ['texto' => 'Estudios previos'])->assertOk()->assertJsonPath('peso', 1);
        $this->assertSame(1, RequisitoCatalogo::findOrFail($id)->peso);
    }

    // ------------------------------------------------------------- errores en su campo

    public function test_los_errores_del_peso_vienen_despues_de_los_de_la_etapa_y_en_su_campo(): void
    {
        $viabilizacion = $this->borrador($this->plantilla(PlantillaChecklist::USO_VIABILIZACION));
        $sinEtapa = 'Los requisitos de una plantilla de viabilización no llevan etapa de contratación.';

        // Etapa y peso: en la lista, la etapa primero; cada campo, con los suyos.
        $this->crearRequisito($viabilizacion, ['peso' => 2])->assertStatus(422)->assertExactJson([
            'mensaje' => 'El requisito no es válido.',
            'errores' => [$sinEtapa, self::SIN_PESO],
            'errors' => ['etapa_id' => [$sinEtapa], 'peso' => [self::SIN_PESO]],
        ]);

        // Peso y otros errores: la lista los trae todos, el peso primero; el campo, solo los suyos.
        $this->comoAdmin()->postJson($this->requisitos($viabilizacion), [
            'texto' => 'Estudios', 'peso' => 2, 'caracter' => RequisitoCatalogo::CONDICIONADO,
        ])->assertStatus(422)->assertExactJson([
            'mensaje' => 'El requisito no es válido.',
            'errores' => [self::SIN_PESO, 'Un requisito condicionado necesita describir cuándo aplica.'],
            'errors' => ['peso' => [self::SIN_PESO]],
        ]);
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Schema/PesoRequisitosTest.php tests/Feature/Viabilizacion/PesoRequisitosApiTest.php`
Expected: FALLAN las 14: no existe la columna `peso` ("table requisitos_catalogo has no column named peso"), el modelo no la conoce y el constructor de plantillas no valida, guarda ni copia el peso.

- [ ] **Paso 3: Implementar**

Crear `database/migrations/2026_11_19_100020_add_peso_a_requisitos_catalogo.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Cada requisito de plantilla tiene un peso (requisitos_catalogo.peso) con el que cuenta en el
 * avance de un expediente de contratación. Todos los requisitos que ya existen quedan con 1, que
 * es contar como hasta ahora. La viabilización no lo usa.
 */
return new class extends Migration
{
    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: el paso lleva su guarda, para poder reintentar.
        if (! Schema::hasColumn('requisitos_catalogo', 'peso')) {
            Schema::table('requisitos_catalogo', function (Blueprint $table) {
                $table->unsignedSmallInteger('peso')->default(1)->after('etapa_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('requisitos_catalogo', 'peso')) {
            return;
        }
        if (DB::table('requisitos_catalogo')->where('peso', '!=', 1)->exists()) {
            throw new RuntimeException('No se puede revertir: hay requisitos de plantilla con un peso distinto de 1, que se perdería. Restaure un respaldo.');
        }

        Schema::table('requisitos_catalogo', function (Blueprint $table) {
            $table->dropColumn('peso');
        });
    }
};
```

En `app/Http/Controllers/CatalogoRequisitosController.php`, reemplazar (1 de 3):

```php
        'fuente' => 'la fuente normativa', 'sector_id' => 'el sector', 'tipo_id' => 'el tipo de proyecto',
        'condicion' => 'la condición', 'nota' => 'las observaciones', 'orden' => 'el orden',
        'activo' => 'el estado activo', 'ids' => 'el orden de los requisitos', 'ids.*' => 'cada requisito',
        'etapa_id' => 'la etapa',
    ];

    private const MENSAJES = [
```

por:

```php
        'fuente' => 'la fuente normativa', 'sector_id' => 'el sector', 'tipo_id' => 'el tipo de proyecto',
        'condicion' => 'la condición', 'nota' => 'las observaciones', 'orden' => 'el orden',
        'activo' => 'el estado activo', 'ids' => 'el orden de los requisitos', 'ids.*' => 'cada requisito',
        'etapa_id' => 'la etapa', 'peso' => 'el peso',
    ];

    private const MENSAJES = [
```

En `app/Http/Controllers/CatalogoRequisitosController.php`, reemplazar (2 de 3):

```php
        'string' => 'Revisa :attribute: debe ser un texto.',
        'min' => 'Revisa :attribute: el mínimo es :min.',
        'orden.max' => 'Revisa el orden: el máximo es :max.',
        'codigo_comun.regex' => 'El código común solo admite letras, números, punto, guion y guion bajo.',
        'texto.required' => 'Escribe el nombre del requisito.',
        'clave.regex' => 'La clave solo admite minúsculas, números y guion bajo.',
```

por:

```php
        'string' => 'Revisa :attribute: debe ser un texto.',
        'min' => 'Revisa :attribute: el mínimo es :min.',
        'orden.max' => 'Revisa el orden: el máximo es :max.',
        'peso.max' => 'Revisa el peso: el máximo es :max.',
        'codigo_comun.regex' => 'El código común solo admite letras, números, punto, guion y guion bajo.',
        'texto.required' => 'Escribe el nombre del requisito.',
        'clave.regex' => 'La clave solo admite minúsculas, números y guion bajo.',
```

En `app/Http/Controllers/CatalogoRequisitosController.php`, reemplazar (3 de 3):

```php
            'activo' => ['sometimes', 'boolean'],
            // Si la plantilla la exige o la rechaza, y si está activa, lo decide el servicio.
            'etapa_id' => ['nullable', 'bail', 'integer', $entero],
        ];

        if ($version->plantilla->motor === PlantillaChecklist::MOTOR_SIMPLE) {
```

por:

```php
            'activo' => ['sometimes', 'boolean'],
            // Si la plantilla la exige o la rechaza, y si está activa, lo decide el servicio.
            'etapa_id' => ['nullable', 'bail', 'integer', $entero],
            // De 1 a 100 en contratación; si la plantilla lo admite distinto de 1 lo decide el servicio.
            'peso' => ['sometimes', 'bail', 'integer', $entero, 'min:1', 'max:100'],
        ];

        if ($version->plantilla->motor === PlantillaChecklist::MOTOR_SIMPLE) {
```

En `app/Models/RequisitoCatalogo.php`, reemplazar (1 de 2):

```php
        'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
        'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
        'requiere_aprobacion_no_aplica', 'codigo_comun', 'responsable_cumplimiento_id', 'responsable_revision_id',
        'etapa_id',
    ];
    protected $casts = [
        'catalogo_version_id' => 'integer',
```

por:

```php
        'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
        'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
        'requiere_aprobacion_no_aplica', 'codigo_comun', 'responsable_cumplimiento_id', 'responsable_revision_id',
        'etapa_id', 'peso',
    ];
    protected $casts = [
        'catalogo_version_id' => 'integer',
```

En `app/Models/RequisitoCatalogo.php`, reemplazar (2 de 2):

```php
        'responsable_cumplimiento_id' => 'integer',
        'responsable_revision_id' => 'integer',
        'etapa_id' => 'integer',
        'activo' => 'boolean',
        'requiere_aprobacion_no_aplica' => 'boolean',
    ];
```

por:

```php
        'responsable_cumplimiento_id' => 'integer',
        'responsable_revision_id' => 'integer',
        'etapa_id' => 'integer',
        'peso' => 'integer',
        'activo' => 'boolean',
        'requiere_aprobacion_no_aplica' => 'boolean',
    ];
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (1 de 8):

```php
                'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
                'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
                'requiere_aprobacion_no_aplica', 'codigo_comun', 'responsable_cumplimiento_id', 'responsable_revision_id',
                'etapa_id',
            ];
            $vigente?->requisitos()->orderBy('id')->select($columnas)->get()
                ->map(fn ($r) => ['catalogo_version_id' => $borrador->id, ...$r->only($columnas)])
```

por:

```php
                'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
                'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
                'requiere_aprobacion_no_aplica', 'codigo_comun', 'responsable_cumplimiento_id', 'responsable_revision_id',
                'etapa_id', 'peso',
            ];
            $vigente?->requisitos()->orderBy('id')->select($columnas)->get()
                ->map(fn ($r) => ['catalogo_version_id' => $borrador->id, ...$r->only($columnas)])
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (2 de 8):

```php

        $errores = [];
        $erroresEtapa = [];
        $seccion = $datos['seccion'];
        $this->etapaDelRequisito($version, $datos, $requisito, $erroresEtapa);

        $claveRepetida = RequisitoCatalogo::where('catalogo_version_id', $version->id)
            ->where('clave', $datos['clave'])
```

por:

```php

        $errores = [];
        $erroresEtapa = [];
        $erroresPeso = [];
        $seccion = $datos['seccion'];
        $this->etapaDelRequisito($version, $datos, $requisito, $erroresEtapa);
        $peso = $this->pesoDelRequisito($version, $datos, $requisito, $erroresPeso);

        $claveRepetida = RequisitoCatalogo::where('catalogo_version_id', $version->id)
            ->where('clave', $datos['clave'])
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (3 de 8):

```php
        }
        $datos['condicion'] = $condicion !== '' ? $condicion : null;

        $this->rechazarSiHayErrores($erroresEtapa, $errores);

        $valores = [
            'catalogo_version_id' => $version->id,
```

por:

```php
        }
        $datos['condicion'] = $condicion !== '' ? $condicion : null;

        $this->rechazarSiHayErrores($erroresEtapa, $erroresPeso, $errores);

        $valores = [
            'catalogo_version_id' => $version->id,
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (4 de 8):

```php
            'condicion' => $datos['condicion'],
            'orden' => $datos['orden'] ?? ($requisito?->orden ?? (int) $version->requisitos()->max('orden') + 1),
            'activo' => array_key_exists('activo', $datos) ? (bool) $datos['activo'] : ($requisito?->activo ?? true),
            ...$this->camposDeSeguimiento($datos, $requisito, sinCaracter: true),
        ];
        if ($version->plantilla->clave === PlantillaChecklist::CLAVE_SGR) {
```

por:

```php
            'condicion' => $datos['condicion'],
            'orden' => $datos['orden'] ?? ($requisito?->orden ?? (int) $version->requisitos()->max('orden') + 1),
            'activo' => array_key_exists('activo', $datos) ? (bool) $datos['activo'] : ($requisito?->activo ?? true),
            'peso' => $peso,
            ...$this->camposDeSeguimiento($datos, $requisito, sinCaracter: true),
        ];
        if ($version->plantilla->clave === PlantillaChecklist::CLAVE_SGR) {
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (5 de 8):

```php
    {
        $errores = [];
        $erroresEtapa = [];
        $clave = trim((string) ($datos['clave'] ?? '')) ?: ($requisito?->clave ?? $this->claveLibre($version));
        $etapa = $this->etapaDelRequisito($version, $datos, $requisito, $erroresEtapa);

        $repetida = fn (string $columna, ?string $valor) => $valor !== null && RequisitoCatalogo::where('catalogo_version_id', $version->id)
            ->where($columna, $valor)
```

por:

```php
    {
        $errores = [];
        $erroresEtapa = [];
        $erroresPeso = [];
        $clave = trim((string) ($datos['clave'] ?? '')) ?: ($requisito?->clave ?? $this->claveLibre($version));
        $etapa = $this->etapaDelRequisito($version, $datos, $requisito, $erroresEtapa);
        $peso = $this->pesoDelRequisito($version, $datos, $requisito, $erroresPeso);

        $repetida = fn (string $columna, ?string $valor) => $valor !== null && RequisitoCatalogo::where('catalogo_version_id', $version->id)
            ->where($columna, $valor)
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (6 de 8):

```php
            $errores[] = 'Un requisito condicionado necesita describir cuándo aplica.';
        }

        $this->rechazarSiHayErrores($erroresEtapa, $errores);

        $valores = [
            'catalogo_version_id' => $version->id,
```

por:

```php
            $errores[] = 'Un requisito condicionado necesita describir cuándo aplica.';
        }

        $this->rechazarSiHayErrores($erroresEtapa, $erroresPeso, $errores);

        $valores = [
            'catalogo_version_id' => $version->id,
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (7 de 8):

```php
            'codigo_comun' => $codigoComun,
            'requiere_aprobacion_no_aplica' => (bool) ($datos['requiere_aprobacion_no_aplica'] ?? $requisito?->requiere_aprobacion_no_aplica ?? false),
            'etapa_id' => $etapa,
            ...$seguimiento,
        ];
```

por:

```php
            'codigo_comun' => $codigoComun,
            'requiere_aprobacion_no_aplica' => (bool) ($datos['requiere_aprobacion_no_aplica'] ?? $requisito?->requiere_aprobacion_no_aplica ?? false),
            'etapa_id' => $etapa,
            'peso' => $peso,
            ...$seguimiento,
        ];
```

En `app/Services/Viabilizacion/CatalogoService.php`, reemplazar (8 de 8):

```php
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
```

por:

```php
    }

    /**
     * El requisito no se guarda si hay errores. Los de la etapa van primero en la lista, después
     * los del peso, y además cada uno en su campo (`etapa_id`, `peso`), para que la pantalla los
     * muestre bajo su input sin adivinar cuáles son.
     *
     * @param list<string> $deEtapa
     * @param list<string> $dePeso
     * @param list<string> $otros
     */
    private function rechazarSiHayErrores(array $deEtapa, array $dePeso, array $otros): void
    {
        if ($deEtapa || $dePeso || $otros) {
            $porCampo = array_filter(['etapa_id' => $deEtapa, 'peso' => $dePeso]);
            throw new ReglaNegocioException('El requisito no es válido.', [...$deEtapa, ...$dePeso, ...$otros], $porCampo);
        }
    }

    /**
     * Peso del requisito en el avance del expediente. En una plantilla de contratación es
     * opcional: de 1 a 100 (lo valida el controlador); sin el campo en el pedido, conserva el que
     * tenía (1 al crear). En una de viabilización no se usa: un peso distinto de 1 se rechaza y
     * siempre se guarda 1. Los errores se agregan a $errores.
     */
    private function pesoDelRequisito(CatalogoVersion $version, array $datos, ?RequisitoCatalogo $requisito, array &$errores): int
    {
        if (! $version->plantilla->esContratacion()) {
            if (array_key_exists('peso', $datos) && $datos['peso'] !== 1) {
                $errores[] = 'Los requisitos de una plantilla de viabilización no llevan peso.';
            }

            return 1;
        }

        return array_key_exists('peso', $datos) ? (int) $datos['peso'] : ($requisito?->peso ?? 1);
    }

    /**
     * Etapa de contratación del requisito. En una plantilla de contratación es obligatoria: una
     * etapa activa o la que ya tenía, aunque esté inactiva; sin el campo en el pedido, no cambia.
```

En `app/Services/Viabilizacion/PlantillaService.php`, reemplazar (1 de 1):

```php
                        'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
                        'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
                        'requiere_aprobacion_no_aplica', 'codigo_comun', 'responsable_cumplimiento_id', 'responsable_revision_id',
                        'etapa_id',
                    ]),
                    'catalogo_version_id' => $borrador->id,
                    'orden' => $posicion + 1,
```

por:

```php
                        'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
                        'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
                        'requiere_aprobacion_no_aplica', 'codigo_comun', 'responsable_cumplimiento_id', 'responsable_revision_id',
                        'etapa_id', 'peso',
                    ]),
                    'catalogo_version_id' => $borrador->id,
                    'orden' => $posicion + 1,
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Schema/PesoRequisitosTest.php tests/Feature/Viabilizacion/PesoRequisitosApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1216 passed.

- [ ] **Paso 5: Commit**

```bash
git add database/migrations/2026_11_19_100020_add_peso_a_requisitos_catalogo.php tests/Feature/Schema/PesoRequisitosTest.php tests/Feature/Viabilizacion/PesoRequisitosApiTest.php app/Http/Controllers/CatalogoRequisitosController.php app/Models/RequisitoCatalogo.php app/Services/Viabilizacion/CatalogoService.php app/Services/Viabilizacion/PlantillaService.php
git commit -m "feat: weight per template requirement (migration 21)"
```

---

### Tarea 3: Avance ponderado de los expedientes de contratación

El progreso de contratación, total y por etapa, trae `peso_total` y `peso_cumplido`, y su porcentaje pondera cada requisito por su peso. Lo usan la presentación del expediente, las respuestas parciales, las versiones del expediente y el resumen de los procesos. La viabilización no cambia.

**Files:**
- Test (create): `tests/Feature/Contratacion/AvancePonderadoApiTest.php`
- Modify: `app/Services/Contratacion/ExpedientesContratacion.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `app/Services/Viabilizacion/VersionesExpediente.php`
- Test: `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/AvancePonderadoApiTest.php`:

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
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Avance ponderado de un expediente de contratación (R10): el porcentaje pesa cada requisito que
 * cuenta por el peso del requisito que muestra la fila (un propio pesa 1); `total` y `cumplidos`
 * siguen siendo conteos. La viabilización no cambia (criterio 10).
 */
class AvancePonderadoApiTest extends TestCase
{
    use RefreshDatabase;

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

    private function modalidad(): int
    {
        return Modalidad::where('clave', 'licitacion_publica')->firstOrFail()->id;
    }

    private function etapa(string $clave = 'planeacion'): EtapaContratacion
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail();
    }

    private function etapaJson(string $clave): array
    {
        $etapa = $this->etapa($clave);

        return ['id' => $etapa->id, 'nombre' => $etapa->nombre, 'orden' => $etapa->orden];
    }

    /** Requisito de plantilla: de planeación salvo que se diga otra etapa. */
    private function req(string $texto, int $peso = 1, array $cambios = []): array
    {
        return ['texto' => $texto, 'etapa_id' => $this->etapa()->id, 'peso' => $peso, ...$cambios];
    }

    /** Plantilla de contratación publicada por la API, con los requisitos dados. */
    private function plantilla(string $nombre, array $requisitos): PlantillaChecklist
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
        foreach ($requisitos as $requisito) {
            $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos", $requisito)->assertCreated();
        }
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();

        return $plantilla->fresh();
    }

    /** La del criterio 9: cinco requisitos, uno de peso 3 y cuatro de peso 1. */
    private function plantillaCriterio9(): PlantillaChecklist
    {
        return $this->plantilla('Licitación', [
            $this->req('Estudios previos', 3),
            $this->req('CDP'),
            $this->req('Plan de adquisiciones'),
            $this->req('Pliego de condiciones', 1, ['etapa_id' => $this->etapa('pliego_definitivo')->id]),
            $this->req('Acta de cierre', 1, ['etapa_id' => $this->etapa('cierre')->id]),
        ]);
    }

    private function proceso(string $codigo = 'LP-001'): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => $this->proyecto->id,
            'objeto' => 'Construcción del acueducto',
            'modalidad' => $this->modalidad(),
            'plataforma' => 'secop_ii',
            'codigo_proceso' => $codigo,
        ]);
    }

    /** @param list<PlantillaChecklist> $plantillas la primera es la principal */
    private function expediente(array $plantillas, ?ProcesoLicitacion $proceso = null): ProyectoExpediente
    {
        $proceso ??= $this->proceso();
        $id = $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => array_map(fn ($p) => $p->id, $plantillas)])
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

    private function cumplir(ProyectoExpediente $expediente, string $texto)
    {
        return $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, $texto)->id}", ['cumplido' => true])->assertOk();
    }

    private function progreso(int $total, int $cumplidos, int $porcentaje, int $pesoTotal, int $pesoCumplido): array
    {
        return ['total' => $total, 'cumplidos' => $cumplidos, 'porcentaje' => $porcentaje, 'peso_total' => $pesoTotal, 'peso_cumplido' => $pesoCumplido];
    }

    // ------------------------------------------------------------ criterio 9

    public function test_criterio_9_cumplir_el_requisito_de_peso_3_de_cinco_da_43_por_ciento(): void
    {
        $expediente = $this->expediente([$this->plantillaCriterio9()]);
        $this->leer($expediente)->assertJsonPath('progreso', $this->progreso(5, 0, 0, 7, 0));

        $esperado = $this->progreso(5, 1, 43, 7, 3);
        $this->cumplir($expediente, 'Estudios previos')
            ->assertJsonPath('progreso', $esperado)
            ->assertJsonPath('requisito.peso', 3);

        $r = $this->leer($expediente)->assertJsonPath('progreso', $esperado);
        $this->assertSame(
            ['Estudios previos' => 3, 'CDP' => 1, 'Plan de adquisiciones' => 1, 'Pliego de condiciones' => 1, 'Acta de cierre' => 1],
            collect($r->json('requisitos'))->pluck('peso', 'requisito.texto')->all()
        );
        $this->assertSame($esperado, app(ExpedienteService::class)->progresoDe($expediente));
    }

    public function test_con_todos_los_pesos_en_1_el_porcentaje_es_el_del_conteo(): void
    {
        $expediente = $this->expediente([$this->plantilla('Licitación', [
            $this->req('Estudios previos'), $this->req('CDP'), $this->req('Plan de adquisiciones'),
        ])]);

        $this->cumplir($expediente, 'Estudios previos')->assertJsonPath('progreso', $this->progreso(3, 1, 33, 3, 1));
    }

    public function test_un_opcional_y_un_no_aplica_aprobado_no_suman_peso(): void
    {
        $expediente = $this->expediente([$this->plantilla('Licitación', [
            $this->req('Estudios previos', 3),
            $this->req('Análisis del sector', 5, ['caracter' => RequisitoCatalogo::OPCIONAL]),
            $this->req('CDP', 4),
            $this->req('Plan de adquisiciones', 2),
        ])]);
        $this->cumplir($expediente, 'Análisis del sector');
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, 'CDP')->id}/no-aplica", [
            'justificacion' => 'Ya se expidió con el proyecto.',
        ])->assertOk()->assertJsonPath('progreso', $this->progreso(2, 0, 0, 5, 0));

        $this->cumplir($expediente, 'Plan de adquisiciones')->assertJsonPath('progreso', $this->progreso(2, 1, 40, 5, 2));
    }

    public function test_un_propio_pesa_1(): void
    {
        $expediente = $this->expediente([$this->plantilla('Licitación', [$this->req('Estudios previos', 3)])]);

        $r = $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/requisitos-propios", [
            'titulo' => 'Póliza', 'etapa_propia_id' => $this->etapa('adjudicacion')->id,
        ])->assertCreated()
            ->assertJsonPath('requisito.peso', 1)
            ->assertJsonPath('progreso', $this->progreso(2, 0, 0, 4, 0));

        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$r->json('requisito.id')}", ['cumplido' => true])->assertOk()
            ->assertJsonPath('progreso', $this->progreso(2, 1, 25, 4, 1))
            ->assertJsonPath('avance_por_etapa.1', ['etapa' => $this->etapaJson('adjudicacion'), ...$this->progreso(1, 1, 100, 1, 1)]);
    }

    public function test_el_avance_por_etapa_tambien_se_pondera(): void
    {
        $expediente = $this->expediente([$this->plantillaCriterio9()]);

        $avance = [
            ['etapa' => $this->etapaJson('planeacion'), ...$this->progreso(3, 1, 60, 5, 3)],
            ['etapa' => $this->etapaJson('pliego_definitivo'), ...$this->progreso(1, 0, 0, 1, 0)],
            ['etapa' => $this->etapaJson('cierre'), ...$this->progreso(1, 0, 0, 1, 0)],
        ];
        $this->assertSame($avance, $this->cumplir($expediente, 'Estudios previos')->json('avance_por_etapa'));
        $this->assertSame($avance, $this->leer($expediente)->json('avance_por_etapa'));
        $this->assertSame($avance, app(ExpedienteService::class)->avancePorEtapaDe($expediente));
    }

    public function test_el_cierre_con_pendientes_sigue_contando_requisitos(): void
    {
        $expediente = $this->expediente([$this->plantilla('Licitación', [$this->req('Estudios previos', 3), $this->req('CDP')])]);
        $this->cumplir($expediente, 'CDP');

        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar")->assertStatus(422)
            ->assertJsonPath('pendientes.total', 1);
    }

    public function test_la_fila_pesa_lo_del_requisito_que_muestra_el_de_la_plantilla_principal(): void
    {
        $una = $this->plantilla('Una', [$this->req('Estudios previos', 2, ['codigo_comun' => 'EP'])]);
        $otra = $this->plantilla('Otra', [$this->req('Estudios previos (otra)', 5, ['codigo_comun' => 'EP'])]);

        $conUna = $this->expediente([$una, $otra]);
        $this->leer($conUna)->assertJsonPath('requisitos.0.peso', 2)->assertJsonPath('progreso.peso_total', 2);

        $conOtra = $this->expediente([$otra, $una], $this->proceso('LP-002'));
        $this->leer($conOtra)->assertJsonPath('requisitos.0.peso', 5)->assertJsonPath('progreso.peso_total', 5);
    }

    // ------------------------------------------------------------ versiones y procesos

    public function test_la_vista_previa_de_una_version_nueva_trae_el_progreso_ponderado(): void
    {
        $plantilla = $this->plantilla('Licitación', [$this->req('Estudios previos', 3), $this->req('CDP')]);
        $expediente = $this->expediente([$plantilla]);
        $this->cumplir($expediente, 'Estudios previos');

        $borrador = app(CatalogoService::class)->crearBorrador('Más peso al CDP', $plantilla);
        $cdp = $borrador->requisitos()->where('texto', 'CDP')->sole();
        $admin = $this->como(Rol::Administrador);
        $admin->putJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos/{$cdp->id}", ['texto' => 'CDP', 'peso' => 5])->assertOk();
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();

        $url = "/GestPro/expedientes/{$expediente->id}/plantillas/{$plantilla->id}/version-nueva";
        $this->como()->getJson($url)->assertOk()
            ->assertJsonPath('progreso_antes', $this->progreso(2, 1, 75, 4, 3))
            ->assertJsonPath('progreso_despues', $this->progreso(2, 1, 38, 8, 3));

        $this->como()->postJson($url, ['version_id' => $borrador->id])->assertOk()
            ->assertJsonPath('progreso', $this->progreso(2, 1, 38, 8, 3));
    }

    public function test_la_vista_previa_muestra_como_cambio_un_cambio_solo_de_peso_o_de_etapa(): void
    {
        $plantilla = $this->plantilla('Licitación', [$this->req('Estudios previos'), $this->req('CDP')]);
        $expediente = $this->expediente([$plantilla]);

        $borrador = app(CatalogoService::class)->crearBorrador('Peso y etapa', $plantilla);
        $cdp = $borrador->requisitos()->where('texto', 'CDP')->sole();
        $cierre = $this->etapa('cierre');
        $admin = $this->como(Rol::Administrador);
        $admin->putJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos/{$cdp->id}", ['texto' => 'CDP', 'peso' => 5, 'etapa_id' => $cierre->id])->assertOk();
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();

        $respuesta = $this->como()->getJson("/GestPro/expedientes/{$expediente->id}/plantillas/{$plantilla->id}/version-nueva")->assertOk();
        $respuesta->assertJsonCount(1, 'cambian')->assertJsonPath('siguen', 1)
            ->assertJsonPath('cambian.0.antes.peso', 1)->assertJsonPath('cambian.0.despues.peso', 5)
            ->assertJsonPath('cambian.0.antes.etapa.id', $this->etapa()->id)->assertJsonPath('cambian.0.despues.etapa.id', $cierre->id);
    }

    public function test_el_progreso_de_un_expediente_no_carga_las_etapas(): void
    {
        $expediente = $this->expediente([$this->plantillaCriterio9()]);
        $tabla = (new EtapaContratacion())->getTable();

        $consultas = [];
        DB::listen(function ($q) use (&$consultas) {
            $consultas[] = $q->sql;
        });
        $progreso = app(ExpedienteService::class)->progresoDe($expediente);

        $this->assertSame(5, $progreso['total']);
        $this->assertEmpty(array_filter($consultas, fn (string $sql) => str_contains($sql, $tabla)));
    }

    public function test_los_procesos_y_la_ficha_traen_el_avance_ponderado(): void
    {
        $expediente = $this->expediente([$this->plantillaCriterio9()]);
        $this->cumplir($expediente, 'Estudios previos');
        $esperado = $this->progreso(5, 1, 43, 7, 3);

        $procesos = $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk()->json('procesos');
        $this->assertSame($esperado, $procesos[0]['expediente']['progreso']);

        $ficha = $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/ficha")->assertOk()->json('procesos');
        $this->assertSame($esperado, $ficha[0]['expediente']['progreso']);
    }

    public function test_el_resumen_de_procesos_ponderado_no_hace_una_consulta_por_proceso(): void
    {
        $plantilla = $this->plantillaCriterio9();
        $this->expediente([$plantilla]);
        $consultas = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };
        $conUno = $consultas();

        foreach (range(2, 4) as $i) {
            $otro = $this->expediente([$plantilla], $this->proceso("LP-00{$i}"));
            $this->cumplir($otro, 'Estudios previos');
        }

        $this->assertSame($conUno, $consultas());
    }

    // ------------------------------------------------------------ criterio 10

    public function test_criterio_10_en_viabilizacion_un_peso_distinto_de_1_no_cambia_nada(): void
    {
        $this->proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        $servicio = app(ExpedienteService::class);
        $expediente = $servicio->confirmarConfiguracion($servicio->crear($this->proyecto->fresh(), ['fase_sgr' => 3], $this->gestor), ['fase_sgr' => 3], $this->gestor);
        $fila = $expediente->requisitos()->where('aplica', true)->orderBy('orden')->firstOrFail();
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$fila->id}", ['cumplido' => true])->assertOk();

        $leer = fn () => $this->leer($expediente)->json();
        $resumen = fn () => collect($this->como(Rol::Consulta)->getJson('/GestPro/expedientes')->assertOk()->json())->firstWhere('expediente_id', $expediente->id);
        $antes = [$leer(), $resumen()];

        // Escrito directo en la base: la administración no deja ponerlo en viabilización.
        RequisitoCatalogo::whereKey($fila->requisito_catalogo_id)->update(['peso' => 5]);
        RequisitoCatalogo::whereIn('id', $fila->origenes()->pluck('requisitos_catalogo.id'))->update(['peso' => 5]);

        $despues = [$leer(), $resumen()];
        $this->assertSame($antes, $despues);
        $this->assertSame(['total', 'cumplidos', 'porcentaje'], array_keys($despues[0]['progreso']));
        $this->assertArrayNotHasKey('peso', $despues[0]['requisitos'][0]);
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$fila->id}", ['observacion' => 'Revisado'])->assertOk()
            ->assertJsonPath('progreso', $antes[0]['progreso'])
            ->assertJsonMissingPath('requisito.peso');
    }
}
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (1 de 7):

```php
            ->assertJsonPath('plantillas.0.compatible', true)
            ->assertJsonPath('fecha_cierre', null)
            ->assertJsonPath('usuario_cierre', null)
            ->assertJsonPath('progreso', ['total' => 2, 'cumplidos' => 0, 'porcentaje' => 0]);

        $etapas = collect($r->json('requisitos'))->pluck('etapa', 'requisito.texto')->all();
        $this->assertSame([
```

por:

```php
            ->assertJsonPath('plantillas.0.compatible', true)
            ->assertJsonPath('fecha_cierre', null)
            ->assertJsonPath('usuario_cierre', null)
            ->assertJsonPath('progreso', ['total' => 2, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 2, 'peso_cumplido' => 0]);

        $etapas = collect($r->json('requisitos'))->pluck('etapa', 'requisito.texto')->all();
        $this->assertSame([
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (2 de 7):

```php
        ], $etapas);
        // En el orden del catálogo de etapas, no en el de los requisitos.
        $this->assertSame([
            ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0],
            ['etapa' => $this->etapaJson('pliego_definitivo'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0],
        ], $r->json('avance_por_etapa'));
    }
```

por:

```php
        ], $etapas);
        // En el orden del catálogo de etapas, no en el de los requisitos.
        $this->assertSame([
            ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0],
            ['etapa' => $this->etapaJson('pliego_definitivo'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0],
        ], $r->json('avance_por_etapa'));
    }
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (3 de 7):

```php
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
```

por:

```php
        $r = $this->leer($expediente);

        $this->assertSame([
            ['etapa' => $this->etapaJson('planeacion'), 'total' => 2, 'cumplidos' => 1, 'porcentaje' => 50, 'peso_total' => 2, 'peso_cumplido' => 1],
            ['etapa' => $this->etapaJson('pliego_definitivo'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0],
            ['etapa' => null, 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0],
        ], $r->json('avance_por_etapa'));
        $r->assertJsonPath('progreso', ['total' => 4, 'cumplidos' => 1, 'porcentaje' => 25, 'peso_total' => 4, 'peso_cumplido' => 1]);
        $this->assertSame(
            $r->json('progreso.total'),
            array_sum(array_column($r->json('avance_por_etapa'), 'total'))
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (4 de 7):

```php

        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'))
            ->assertJsonPath('requisito.etapa', $this->etapaJson('planeacion'))
            ->assertJsonPath('progreso', ['total' => 2, 'cumplidos' => 1, 'porcentaje' => 50])
            ->assertJsonPath('avance_por_etapa.0', ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 1, 'porcentaje' => 100]);
    }

    public function test_la_api_de_viabilizacion_no_trae_las_claves_de_contratacion(): void
```

por:

```php

        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'))
            ->assertJsonPath('requisito.etapa', $this->etapaJson('planeacion'))
            ->assertJsonPath('progreso', ['total' => 2, 'cumplidos' => 1, 'porcentaje' => 50, 'peso_total' => 2, 'peso_cumplido' => 1])
            ->assertJsonPath('avance_por_etapa.0', ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 1, 'porcentaje' => 100, 'peso_total' => 1, 'peso_cumplido' => 1]);
    }

    public function test_la_api_de_viabilizacion_no_trae_las_claves_de_contratacion(): void
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (5 de 7):

```php
        $this->como()->postJson($url, ['titulo' => 'Póliza', 'etapa_propia_id' => $this->etapa('adjudicacion')->id])
            ->assertCreated()
            ->assertJsonPath('requisito.etapa', $this->etapaJson('adjudicacion'))
            ->assertJsonPath('avance_por_etapa.2', ['etapa' => $this->etapaJson('adjudicacion'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0]);

        $propio = $expediente->requisitos()->whereNull('requisito_catalogo_id')->sole();
        $this->assertSame($this->etapa('adjudicacion')->id, $propio->etapa_propia_id);
```

por:

```php
        $this->como()->postJson($url, ['titulo' => 'Póliza', 'etapa_propia_id' => $this->etapa('adjudicacion')->id])
            ->assertCreated()
            ->assertJsonPath('requisito.etapa', $this->etapaJson('adjudicacion'))
            ->assertJsonPath('avance_por_etapa.2', ['etapa' => $this->etapaJson('adjudicacion'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0]);

        $propio = $expediente->requisitos()->whereNull('requisito_catalogo_id')->sole();
        $this->assertSame($this->etapa('adjudicacion')->id, $propio->etapa_propia_id);
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (6 de 7):

```php
        $this->assertSame([
            'id' => $expediente->id,
            'estado' => ProyectoExpediente::BORRADOR,
            'progreso' => ['total' => 2, 'cumplidos' => 1, 'porcentaje' => 50],
            'aviso_plantilla' => null,
        ], $procesos[1]['expediente']);
    }
```

por:

```php
        $this->assertSame([
            'id' => $expediente->id,
            'estado' => ProyectoExpediente::BORRADOR,
            'progreso' => ['total' => 2, 'cumplidos' => 1, 'porcentaje' => 50, 'peso_total' => 2, 'peso_cumplido' => 1],
            'aviso_plantilla' => null,
        ], $procesos[1]['expediente']);
    }
```

En `tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`, reemplazar (7 de 7):

```php
        ])->assertCreated();

        $r->assertJsonPath('expediente.estado', ProyectoExpediente::BORRADOR)
            ->assertJsonPath('expediente.progreso', ['total' => 2, 'cumplidos' => 0, 'porcentaje' => 0])
            ->assertJsonPath('expediente.aviso_plantilla', null);
    }
```

por:

```php
        ])->assertCreated();

        $r->assertJsonPath('expediente.estado', ProyectoExpediente::BORRADOR)
            ->assertJsonPath('expediente.progreso', ['total' => 2, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 2, 'peso_cumplido' => 0])
            ->assertJsonPath('expediente.aviso_plantilla', null);
    }
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/AvancePonderadoApiTest.php tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`
Expected: FALLAN 15 y pasan 22: en `AvancePonderadoApiTest` fallan 9 porque el progreso de contratación todavía no trae `peso_total` ni `peso_cumplido` y su porcentaje no pondera (ni por etapa, ni en la vista previa de versión nueva, ni en los procesos); en `ExpedienteContratacionApiTest` fallan los 6 que esperan esas claves nuevas en el progreso y en el avance por etapa.

- [ ] **Paso 3: Implementar**

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (1 de 3):

```php
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
```

por:

```php
    }

    /**
     * El expediente de cada proceso, resumido para la API del proceso: su estado, su avance
     * ponderado (ExpedienteService::progresoPonderadoDe) y el aviso si ninguna de sus plantillas
     * le sirve ya (no corresponde a la modalidad, la fuente o el sector, está inactiva o no tiene
     * versión publicada). Las consultas no crecen con los procesos.
     *
     * @param Collection<int, ProcesoLicitacion> $procesos
     * @return array<int, ?array{id: int, estado: string, progreso: array{total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}, aviso_plantilla: ?string}> proceso_id => resumen; null sin expediente
     */
    public function resumenDeProcesos(Collection $procesos): array
    {
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (2 de 3):

```php
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
```

por:

```php
            ->contratacion()
            ->whereIn('proceso_id', $procesos->pluck('id'))
            ->with('versionesPlantilla')
            ->get()
            ->keyBy('proceso_id');
        $progresos = $this->expedientes->progresoPonderadoDe($expedientes->values());
        $estados = $this->plantillas->estadoPorProceso(
            $procesos->filter(fn (ProcesoLicitacion $p) => $expedientes->has($p->id))->values(),
            $expedientes->flatMap(fn (ProyectoExpediente $e) => $e->versionesPlantilla->pluck('plantilla_id'))
                ->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        return $procesos->mapWithKeys(function (ProcesoLicitacion $proceso) use ($expedientes, $estados, $progresos) {
            $expediente = $expedientes->get($proceso->id);
            if (! $expediente) {
                return [$proceso->id => null];
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (3 de 3):

```php
            return [$proceso->id => [
                'id' => $expediente->id,
                'estado' => $expediente->estado,
                'progreso' => $this->expedientes->progreso((int) $expediente->requisitos_aplican, (int) $expediente->requisitos_cumplidos),
                'aviso_plantilla' => $this->avisoPlantilla($razones),
            ]];
        })->all();
```

por:

```php
            return [$proceso->id => [
                'id' => $expediente->id,
                'estado' => $expediente->estado,
                'progreso' => $progresos[$expediente->id],
                'aviso_plantilla' => $this->avisoPlantilla($razones),
            ]];
        })->all();
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 8):

```php
 *   contratación (de un proceso) nace con crearDeProceso(), ya configurado: comparte el
 *   checklist, pero no se configura ni se radica. Sus filas llevan la etapa de contratación
 *   (la del requisito mostrado, o la elegida en un propio), con el avance por etapa, y se
 *   cierra con cerrar(): solo avisa de los pendientes. Cerrado, queda congelado como uno
 *   radicado. La fecha y el usuario del cierre se guardan en fecha_radicado y
 *   usuario_radicado_id: los del acto que termina el trámite del expediente.
 */
```

por:

```php
 *   contratación (de un proceso) nace con crearDeProceso(), ya configurado: comparte el
 *   checklist, pero no se configura ni se radica. Sus filas llevan la etapa de contratación
 *   (la del requisito mostrado, o la elegida en un propio), con el avance por etapa, y se
 *   cierra con cerrar(): solo avisa de los pendientes. Su porcentaje de avance es ponderado:
 *   cada fila que cuenta pesa lo del requisito que muestra (un propio pesa 1); `total` y
 *   `cumplidos` siguen siendo conteos. En viabilización el peso no se usa. Cerrado, queda congelado como uno
 *   radicado. La fecha y el usuario del cierre se guardan en fecha_radicado y
 *   usuario_radicado_id: los del acto que termina el trámite del expediente.
 */
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (2 de 8):

```php
    ];

    // Solo en los de contratación: la etapa de cada fila.
    private const RELACIONES_ETAPA = ['requisito.etapa', 'origenes.etapa', 'etapaPropia'];

    public function __construct(
        private readonly EvaluadorRequisitos $evaluador,
```

por:

```php
    ];

    // Solo en los de contratación: la etapa de cada fila.
    public const RELACIONES_ETAPA = ['requisito.etapa', 'origenes.etapa', 'etapaPropia'];

    // Lo que se acumula del avance ponderado de un expediente de contratación o de una etapa.
    private const SUMA_VACIA = ['total' => 0, 'cumplidos' => 0, 'peso_total' => 0, 'peso_cumplido' => 0];

    public function __construct(
        private readonly EvaluadorRequisitos $evaluador,
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (3 de 8):

```php
    /**
     * Lo que falta en cada etapa del avance, solo las etapas con algo pendiente.
     *
     * @param list<array{etapa: ?array, total: int, cumplidos: int, porcentaje: int}> $avance el de avancePorEtapa()
     * @return array{total: int, por_etapa: list<array{etapa: ?array, pendientes: int}>}
     */
    private function pendientes(array $avance): array
```

por:

```php
    /**
     * Lo que falta en cada etapa del avance, solo las etapas con algo pendiente.
     *
     * @param list<array{etapa: ?array, total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}> $avance el de avancePorEtapa()
     * @return array{total: int, por_etapa: list<array{etapa: ?array, pendientes: int}>}
     */
    private function pendientes(array $avance): array
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (4 de 8):

```php
                'componentes' => $this->opciones($expediente->componentes),
                'tipologias' => $this->opciones($expediente->tipologias),
            ]]),
            'progreso' => $this->progresoDe($expediente),
            ...($contratacion ? ['avance_por_etapa' => $this->avancePorEtapa($filas, $posiciones)] : []),
            'requisitos' => $filas->map(fn (ProyectoRequisito $f) => $this->presentarFila($f, $posiciones))->values(),
            // Los más parecidos, hasta MAX_AVISOS; el total dice cuántos hay en realidad.
```

por:

```php
                'componentes' => $this->opciones($expediente->componentes),
                'tipologias' => $this->opciones($expediente->tipologias),
            ]]),
            'progreso' => $contratacion ? $this->progresoPonderado($filas, $posiciones) : $this->progresoDe($expediente),
            ...($contratacion ? ['avance_por_etapa' => $this->avancePorEtapa($filas, $posiciones)] : []),
            'requisitos' => $filas->map(fn (ProyectoRequisito $f) => $this->presentarFila($f, $posiciones))->values(),
            // Los más parecidos, hasta MAX_AVISOS; el total dice cuántos hay en realidad.
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (5 de 8):

```php
        ];
    }

    public function progresoDe(ProyectoExpediente $expediente): array
    {
        return $this->progreso(
            $expediente->requisitos()->cuenta()->count(),
            $expediente->requisitos()->cumplidaQueCuenta()->count()
```

por:

```php
        ];
    }

    /**
     * Progreso del expediente: el de progreso() en viabilización; ponderado en contratación (el
     * de progresoPonderado()).
     */
    public function progresoDe(ProyectoExpediente $expediente): array
    {
        if ($expediente->esContratacion()) {
            return $this->progresoPonderado($this->filasDelAvance($expediente, false), $this->posiciones($this->plantillas->versionesDe($expediente)));
        }

        return $this->progreso(
            $expediente->requisitos()->cuenta()->count(),
            $expediente->requisitos()->cumplidaQueCuenta()->count()
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (6 de 8):

```php

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
```

por:

```php

    /**
     * El avance que acompaña la respuesta de una acción sobre el checklist: el progreso y, en
     * uno de contratación, también el avance por etapa, ambos de las mismas filas.
     */
    public function avanceDe(ProyectoExpediente $expediente): array
    {
        if (! $expediente->esContratacion()) {
            return ['progreso' => $this->progresoDe($expediente)];
        }
        $filas = $this->filasDelAvance($expediente);
        $posiciones = $this->posiciones($this->plantillas->versionesDe($expediente));

        return [
            'progreso' => $this->progresoPonderado($filas, $posiciones),
            'avance_por_etapa' => $this->avancePorEtapa($filas, $posiciones),
        ];
    }

    /** @return list<array{etapa: ?array, total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}> el de avancePorEtapa() */
    public function avancePorEtapaDe(ProyectoExpediente $expediente): array
    {
        return $this->avancePorEtapa($this->filasDelAvance($expediente), $this->posiciones($this->plantillas->versionesDe($expediente)));
    }

    /**
     * Progreso ponderado de varios expedientes de contratación, con consultas que no crecen con
     * los expedientes: todas sus filas que cuentan se leen juntas. Espera cargadas sus
     * `versionesPlantilla`, que deciden el requisito que muestra cada fila.
     *
     * @param Collection<int, ProyectoExpediente> $expedientes
     * @return array<int, array{total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}> expediente_id => progreso
     */
    public function progresoPonderadoDe(Collection $expedientes): array
    {
        $filas = $expedientes->isEmpty() ? collect() : ProyectoRequisito::query()
            ->cuenta()
            ->whereIn('expediente_id', $expedientes->pluck('id'))
            ->with(['requisito', 'origenes.version:id,plantilla_id'])
            ->get()
            ->groupBy('expediente_id');

        return $expedientes->mapWithKeys(fn (ProyectoExpediente $e) => [$e->id => $this->progresoPonderado(
            $filas->get($e->id, collect()),
            $this->posiciones($this->plantillas->ordenar($e->versionesPlantilla))
        )])->all();
    }

    /**
     * La etapa y el peso de una fila de contratación. Espera cargadas las relaciones de etapa.
     *
     * @return array{etapa: ?array{id: int, nombre: string, orden: int}, peso: int}
     */
    public function etapaYPesoDe(ProyectoRequisito $fila, array $posiciones): array
    {
        return ['etapa' => $this->presentarEtapa($this->etapaDe($fila, $posiciones)), 'peso' => $this->pesoDe($fila, $posiciones)];
    }

    /** Filas que aplican, con lo que necesitan para el requisito que muestran y su peso (con `$conEtapa`, también su etapa). */
    private function filasDelAvance(ProyectoExpediente $expediente, bool $conEtapa = true): Collection
    {
        return $expediente->requisitos()
            ->where('aplica', true)
            ->with(['requisito', 'origenes.version:id,plantilla_id', ...($conEtapa ? self::RELACIONES_ETAPA : [])])
            ->get();
    }

    /**
     * Progreso de un expediente de contratación, de sus filas que aplican: `total` y `cumplidos`
     * son conteos, como en progreso(); el porcentaje pondera cada fila por su peso (pesoDe()).
     * Con todos los pesos en 1 es el mismo de progreso(). Espera cargados `requisito` y
     * `origenes.version`.
     *
     * @param Collection<int, ProyectoRequisito> $filas
     * @return array{total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}
     */
    private function progresoPonderado(Collection $filas, array $posiciones): array
    {
        $suma = self::SUMA_VACIA;
        foreach ($filas as $fila) {
            $this->sumarFila($suma, $fila, $posiciones);
        }

        return $this->progresoConPeso($suma);
    }

    /**
     * Suma la fila al avance si cuenta: uno al total y su peso al peso total; si está cumplida,
     * también a los cumplidos y al peso cumplido.
     *
     * @param array{total: int, cumplidos: int, peso_total: int, peso_cumplido: int} $suma
     */
    private function sumarFila(array &$suma, ProyectoRequisito $fila, array $posiciones): void
    {
        if (! $fila->cuenta()) {
            return;
        }
        $peso = $this->pesoDe($fila, $posiciones);
        $suma['total']++;
        $suma['peso_total'] += $peso;
        if ($fila->cumplidaQueCuenta()) {
            $suma['cumplidos']++;
            $suma['peso_cumplido'] += $peso;
        }
    }

    /**
     * Peso de una fila de contratación en el avance: el del requisito que muestra, el mismo que
     * da su etapa; un propio pesa siempre 1.
     */
    private function pesoDe(ProyectoRequisito $fila, array $posiciones): int
    {
        return $fila->esPropio() ? 1 : (int) ($this->requisitoMostrado($fila, $posiciones)?->peso ?? 1);
    }

    /**
     * @param array{total: int, cumplidos: int, peso_total: int, peso_cumplido: int} $suma
     * @return array{total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}
     */
    private function progresoConPeso(array $suma): array
    {
        return [
            'total' => $suma['total'],
            'cumplidos' => $suma['cumplidos'],
            'porcentaje' => $suma['peso_total'] > 0 ? (int) round($suma['peso_cumplido'] / $suma['peso_total'] * 100) : 0,
            'peso_total' => $suma['peso_total'],
            'peso_cumplido' => $suma['peso_cumplido'],
        ];
    }

    /**
     * Avance de cada etapa con filas en el checklist, con la misma definición del progreso
     * ponderado: cuentan las que cuentan y, entre ellas, las cumplidas, cada una con su peso. En
     * el orden del catálogo de etapas; las filas sin etapa, al final. Espera cargadas las
     * relaciones de etapa, `requisito` y `origenes.version`.
     *
     * @param Collection<int, ProyectoRequisito> $filas las que aplican
     * @return list<array{etapa: ?array{id: int, nombre: string, orden: int}, total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}>
     */
    private function avancePorEtapa(Collection $filas, array $posiciones): array
    {
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (7 de 8):

```php
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
```

por:

```php
        foreach ($filas as $fila) {
            $etapa = $this->etapaDe($fila, $posiciones);
            $clave = $etapa?->id ?? 0;
            $grupos[$clave] ??= ['etapa' => $etapa, 'suma' => self::SUMA_VACIA];
            $this->sumarFila($grupos[$clave]['suma'], $fila, $posiciones);
        }

        return collect($grupos)
            ->sortBy(fn (array $g) => [$g['etapa'] === null ? 1 : 0, $g['etapa']?->orden ?? 0, $g['etapa']?->id ?? 0])
            ->map(fn (array $g) => ['etapa' => $this->presentarEtapa($g['etapa']), ...$this->progresoConPeso($g['suma'])])
            ->values()
            ->all();
    }
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (8 de 8):

```php
            'categoria' => $fila->esPropio() ? $fila->categoria_propia : $r->categoria,
        ];

        // La etapa solo en los de contratación: la respuesta de viabilización no cambia.
        return $contratacion ? [...$presentada, 'etapa' => $this->presentarEtapa($this->etapaDe($fila, $posiciones))] : $presentada;
    }

    /**
```

por:

```php
            'categoria' => $fila->esPropio() ? $fila->categoria_propia : $r->categoria,
        ];

        // La etapa y el peso solo en los de contratación: la respuesta de viabilización no cambia.
        return $contratacion ? [
            ...$presentada,
            'etapa' => $this->presentarEtapa($this->etapaDe($fila, $posiciones)),
            'peso' => $this->pesoDe($fila, $posiciones),
        ] : $presentada;
    }

    /**
```

En `app/Services/Viabilizacion/VersionesExpediente.php`, reemplazar (1 de 1):

```php
    private function foto(ProyectoExpediente $expediente): Collection
    {
        $posiciones = $this->expedientes->posiciones($this->plantillas->versionesDe($expediente));

        return $expediente->requisitos()
            ->with(['requisito', 'origenes.version:id,plantilla_id'])
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (ProyectoRequisito $f) => [$f->id => ['aplica' => $f->aplica, 'fila' => $this->resumen($f, $posiciones)]]);
    }

    /** Lo que muestra la fila, como presentarFila(): el requisito del primer origen o, sin orígenes, el ancla. */
    private function resumen(ProyectoRequisito $fila, array $posiciones): array
    {
        if ($fila->esPropio()) {
            // Como en la pantalla, un propio se muestra por su título y no tiene código.
            return ['id' => $fila->id, 'codigo' => null, 'texto' => $fila->titulo_propio, 'caracter' => $fila->caracter, 'titulo' => $fila->titulo_propio];
        }
        $requisito = $this->expedientes->requisitoMostrado($fila, $posiciones);

        return ['id' => $fila->id, 'codigo' => $requisito->codigo, 'texto' => $requisito->texto, 'caracter' => $fila->caracter];
    }

    /** @return array{plantilla_id: int, plantilla: string, version: int, catalogo_version_id: int} */
```

por:

```php
    private function foto(ProyectoExpediente $expediente): Collection
    {
        $posiciones = $this->expedientes->posiciones($this->plantillas->versionesDe($expediente));
        $contratacion = $expediente->esContratacion();

        return $expediente->requisitos()
            ->with(['requisito', 'origenes.version:id,plantilla_id', ...($contratacion ? ExpedienteService::RELACIONES_ETAPA : [])])
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (ProyectoRequisito $f) => [$f->id => ['aplica' => $f->aplica, 'fila' => $this->resumen($f, $posiciones, $contratacion)]]);
    }

    /** Lo que muestra la fila, como presentarFila(): el requisito del primer origen o, sin orígenes, el ancla. */
    private function resumen(ProyectoRequisito $fila, array $posiciones, bool $contratacion): array
    {
        if ($fila->esPropio()) {
            // Como en la pantalla, un propio se muestra por su título y no tiene código.
            $resumen = ['id' => $fila->id, 'codigo' => null, 'texto' => $fila->titulo_propio, 'caracter' => $fila->caracter, 'titulo' => $fila->titulo_propio];
        } else {
            $requisito = $this->expedientes->requisitoMostrado($fila, $posiciones);
            $resumen = ['id' => $fila->id, 'codigo' => $requisito->codigo, 'texto' => $requisito->texto, 'caracter' => $fila->caracter];
        }

        // En contratación también el peso y la etapa: un cambio solo de ellos es un cambio. La viabilización no cambia.
        return $contratacion ? [...$resumen, ...$this->expedientes->etapaYPesoDe($fila, $posiciones)] : $resumen;
    }

    /** @return array{plantilla_id: int, plantilla: string, version: int, catalogo_version_id: int} */
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/AvancePonderadoApiTest.php tests/Feature/Contratacion/ExpedienteContratacionApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1229 passed.

- [ ] **Paso 5: Commit**

```bash
git add tests/Feature/Contratacion/AvancePonderadoApiTest.php app/Services/Contratacion/ExpedientesContratacion.php app/Services/Viabilizacion/ExpedienteService.php app/Services/Viabilizacion/VersionesExpediente.php tests/Feature/Contratacion/ExpedienteContratacionApiTest.php
git commit -m "feat: weighted progress for contracting expedients"
```

---

### Tarea 4: El peso en el constructor de plantillas y en el expediente

El campo "Peso" en las plantillas de contratación, la etiqueta "Peso N" en la lista y en el expediente, y la nota de ponderación bajo el medidor general.

**Files:**
- Modify: `resources/css/ExpedienteContratacion.css`
- Modify: `resources/js/components/ExpedienteChecklist.jsx`
- Modify: `resources/js/components/ExpedienteContratacion.jsx`
- Modify: `resources/js/components/PlantillaRequisitos.jsx`
- Modify: `resources/js/utils/expedienteContratacion.js`
- Test: `resources/js/utils/expedienteContratacion.test.js`
- Modify: `resources/js/utils/plantillasChecklist.js`
- Test: `resources/js/utils/plantillasChecklist.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/expedienteContratacion.test.js`, reemplazar (1 de 2):

```js
    esIdProceso,
    formularioPropioContratacion,
    htmlPendientesCierre,
    opcionesEtapaPropio,
    propioCompleto,
    seleccionInicial,
    textoEstadoExpediente,
```

por:

```js
    esIdProceso,
    formularioPropioContratacion,
    htmlPendientesCierre,
    NOTA_PONDERADO,
    opcionesEtapaPropio,
    pondera,
    propioCompleto,
    seleccionInicial,
    textoEstadoExpediente,
```

En `resources/js/utils/expedienteContratacion.test.js`, reemplazar (2 de 2):

```js
        expect(avisoIncompatiblesProceso(undefined)).toBeNull();
    });
});
```

por:

```js
        expect(avisoIncompatiblesProceso(undefined)).toBeNull();
    });
});

describe('pondera', () => {
    it('el texto de la nota bajo el medidor general', () => {
        expect(NOTA_PONDERADO).toBe('El porcentaje pondera cada requisito por su peso.');
    });

    it('sí si algún requisito que cuenta pesa distinto de 1', () => {
        expect(pondera([fila(1, null, { peso: 1 }), fila(2, null, { peso: 3 })])).toBe(true);
    });

    it('no si todos los que cuentan pesan 1, aunque uno que no cuenta pese más', () => {
        expect(pondera([fila(1, null, { peso: 1 }), fila(2, null, { peso: 3, cuenta: false })])).toBe(false);
    });

    it('no en viabilización (sus filas no traen peso) ni sin filas', () => {
        expect(pondera([fila(1, null), fila(2, null)])).toBe(false);
        expect(pondera([])).toBe(false);
        expect(pondera(undefined)).toBe(false);
    });
});
```

En `resources/js/utils/plantillasChecklist.test.js`, reemplazar (1 de 3):

```js
    agruparPorEtapa,
    compararEtapas,
    erroresDeEtapa,
    mismaEtapa,
    nombreEtapa,
    ordenTrasMoverEnEtapa,
```

por:

```js
    agruparPorEtapa,
    compararEtapas,
    erroresDeEtapa,
    erroresDelRequisito,
    etiquetaPeso,
    PESO_MAXIMO,
    PESO_MINIMO,
    mismaEtapa,
    nombreEtapa,
    ordenTrasMoverEnEtapa,
```

En `resources/js/utils/plantillasChecklist.test.js`, reemplazar (2 de 3):

```js
    });
});

describe('requisitos por etapa', () => {
    const planeacion = { id: 10, nombre: 'Planeación', orden: 1, activo: true };
    const seleccion = { id: 12, nombre: 'Selección', orden: 3, activo: false };
```

por:

```js
    });
});

describe('peso del requisito de contratación', () => {
    it('los límites coinciden con CatalogoRequisitosController (1 a 100)', () => {
        expect([PESO_MINIMO, PESO_MAXIMO]).toEqual([1, 100]);
    });

    it('un requisito nuevo de contratación pesa 1; uno de viabilización no lleva peso', () => {
        expect(requisitoVacio('simple', USO_CONTRATACION)).toMatchObject({ peso: '1' });
        expect(requisitoVacio('simple')).not.toHaveProperty('peso');
        expect(requisitoVacio('sgr', USO_VIABILIZACION)).not.toHaveProperty('peso');
    });

    it('lee el peso del requisito; sin él, 1', () => {
        expect(formularioRequisito({ texto: 'x', etapa_id: 11, peso: 3 }, 'simple', USO_CONTRATACION)).toMatchObject({ peso: '3' });
        expect(formularioRequisito({ texto: 'x', etapa_id: 11 }, 'simple', USO_CONTRATACION)).toMatchObject({ peso: '1' });
        expect(formularioRequisito({ texto: 'x', etapa_id: 11, peso: null }, 'simple', USO_CONTRATACION)).toMatchObject({ peso: '1' });
        expect(formularioRequisito({ texto: 'x', peso: 3 }, 'simple')).not.toHaveProperty('peso');
    });

    it('envía el peso como entero solo en contratación', () => {
        const formulario = formularioRequisito({ texto: 'Estudios previos', etapa_id: 11, peso: 3 }, 'simple', USO_CONTRATACION);
        expect(datosRequisito(formulario, 'simple', false, USO_CONTRATACION).peso).toBe(3);
        expect(datosRequisito({ ...formulario, peso: ' 7 ' }, 'simple', false, USO_CONTRATACION).peso).toBe(7);
        expect(datosRequisito({ ...formulario, peso: 12 }, 'sgr', true, USO_CONTRATACION).peso).toBe(12);
        // Vacío vuelve al valor por defecto.
        expect(datosRequisito({ ...formulario, peso: '' }, 'simple', false, USO_CONTRATACION).peso).toBe(1);
        expect(datosRequisito(formulario, 'simple')).not.toHaveProperty('peso');
        expect(datosRequisito(formulario, 'sgr', true, USO_VIABILIZACION)).not.toHaveProperty('peso');
    });

    it('no redondea un peso que no es entero: lo envía para que el servidor lo objete', () => {
        const formulario = requisitoVacio('simple', USO_CONTRATACION);
        expect(datosRequisito({ ...formulario, peso: '2.5' }, 'simple', false, USO_CONTRATACION).peso).toBe(2.5);
        expect(datosRequisito({ ...formulario, peso: 'abc' }, 'simple', false, USO_CONTRATACION).peso).toBe('abc');
    });

    it('la lista dice "Peso N" solo si N no es 1', () => {
        expect(etiquetaPeso(3)).toBe('Peso 3');
        expect(etiquetaPeso('100')).toBe('Peso 100');
        expect(etiquetaPeso(1)).toBeNull();
        expect(etiquetaPeso('1')).toBeNull();
        expect(etiquetaPeso(undefined)).toBeNull();
        expect(etiquetaPeso(null)).toBeNull();
    });
});

describe('requisitos por etapa', () => {
    const planeacion = { id: 10, nombre: 'Planeación', orden: 1, activo: true };
    const seleccion = { id: 12, nombre: 'Selección', orden: 3, activo: false };
```

En `resources/js/utils/plantillasChecklist.test.js`, reemplazar (3 de 3):

```js
    });
});

describe('errores al publicar una plantilla de contratación', () => {
    it('los requisitos sin etapa llegan como lista; la falta de modalidades, como mensaje', () => {
        const sinEtapa = {
```

por:

```js
    });
});

describe('errores de la etapa y del peso al guardar un requisito', () => {
    const respuesta = (status, data) => ({ response: { status, data } });
    const sinPeso = 'Los requisitos de una plantilla de viabilización no llevan peso.';

    it('de una regla de negocio reparte etapa y peso, cada uno en su campo, y deja el resto para el aviso', () => {
        const error = respuesta(422, {
            mensaje: 'El requisito no es válido.',
            errores: ['Elige la etapa de contratación del requisito.', sinPeso, 'Un requisito condicionado necesita describir cuándo aplica.'],
            errors: { etapa_id: ['Elige la etapa de contratación del requisito.'], peso: [sinPeso] },
        });
        expect(erroresDelRequisito(error)).toEqual({
            etapa: ['Elige la etapa de contratación del requisito.'],
            peso: [sinPeso],
            mensaje: 'El requisito no es válido.',
            otros: ['Un requisito condicionado necesita describir cuándo aplica.'],
        });
    });

    it('separa el peso de la validación de Laravel', () => {
        const error = respuesta(422, {
            message: 'Revisa el peso: el máximo es 100.',
            errors: { peso: ['Revisa el peso: el máximo es 100.'], texto: ['Escribe el nombre del requisito.'] },
        });
        expect(erroresDelRequisito(error)).toEqual({
            etapa: [],
            peso: ['Revisa el peso: el máximo es 100.'],
            mensaje: 'El requisito no es válido.',
            otros: ['Escribe el nombre del requisito.'],
        });
    });

    it('sin errores de campo todo queda para el aviso; otro estado no se reparte', () => {
        expect(erroresDelRequisito(respuesta(422, { mensaje: 'El requisito no pertenece a esta versión.' })))
            .toEqual({ etapa: [], peso: [], mensaje: 'El requisito no pertenece a esta versión.', otros: [] });
        expect(erroresDelRequisito(respuesta(500, {}))).toBeNull();
        expect(erroresDelRequisito(new Error('x'))).toBeNull();
    });

    it('erroresDeEtapa no cambia: los del peso quedan para el aviso', () => {
        const error = respuesta(422, {
            message: 'Revisa el peso: el mínimo es 1.',
            errors: { peso: ['Revisa el peso: el mínimo es 1.'] },
        });
        expect(erroresDeEtapa(error)).toEqual({ etapa: [], mensaje: 'El requisito no es válido.', otros: ['Revisa el peso: el mínimo es 1.'] });
    });
});

describe('errores al publicar una plantilla de contratación', () => {
    it('los requisitos sin etapa llegan como lista; la falta de modalidades, como mensaje', () => {
        const sinEtapa = {
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/expedienteContratacion.test.js resources/js/utils/plantillasChecklist.test.js`
Expected: FALLAN los 2 archivos, 13 pruebas, y pasan 114: no existen `NOTA_PONDERADO` ni `pondera` ("pondera is not a function"), ni los límites, la etiqueta y el envío del peso en `plantillasChecklist.js`.

- [ ] **Paso 3: Implementar**

En `resources/css/ExpedienteContratacion.css`, reemplazar (1 de 1):

```css
    border-radius: var(--exv-radio);
}
.exc-etapas .exv-medidor-top b { font-size: .88rem; }

.exc-crear { text-align: left; }
.exc-plantillas {
```

por:

```css
    border-radius: var(--exv-radio);
}
.exc-etapas .exv-medidor-top b { font-size: .88rem; }
.exc-medidor-pie { display: block; margin-top: .35rem; font-size: .78rem; color: var(--exv-tinta-tenue); }

.exc-crear { text-align: left; }
.exc-plantillas {
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (1 de 2):

```jsx
import {
    agruparPorEtapa, formularioPropioContratacion, opcionesEtapaPropio, propioCompleto,
} from '../utils/expedienteContratacion';
import ExpedienteHistorialRequisito from './ExpedienteHistorialRequisito';

const TIPOS_SOPORTE = '.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx';
```

por:

```jsx
import {
    agruparPorEtapa, formularioPropioContratacion, opcionesEtapaPropio, propioCompleto,
} from '../utils/expedienteContratacion';
import { etiquetaPeso } from '../utils/plantillasChecklist';
import ExpedienteHistorialRequisito from './ExpedienteHistorialRequisito';

const TIPOS_SOPORTE = '.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx';
```

En `resources/js/components/ExpedienteChecklist.jsx`, reemplazar (2 de 2):

```jsx
                            <span className={`exv-estado exv-estado-${fila.estado}`}>{ETIQUETA_ESTADO_REQUISITO[fila.estado]}</span>
                        )}
                        {fila.caracter !== 'obligatorio' && <span className="exv-estado">{fila.caracter === 'opcional' ? 'Opcional' : 'Condicionado'}</span>}
                    </div>
                    <div className="exv-item-texto">
                        {contenido.texto}
```

por:

```jsx
                            <span className={`exv-estado exv-estado-${fila.estado}`}>{ETIQUETA_ESTADO_REQUISITO[fila.estado]}</span>
                        )}
                        {fila.caracter !== 'obligatorio' && <span className="exv-estado">{fila.caracter === 'opcional' ? 'Opcional' : 'Condicionado'}</span>}
                        {/* Solo las filas de contratación traen peso; las de viabilización no muestran nada. */}
                        {etiquetaPeso(fila.peso) && <span className="exv-estado">{etiquetaPeso(fila.peso)}</span>}
                    </div>
                    <div className="exv-item-texto">
                        {contenido.texto}
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (1 de 5):

```jsx
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import Swal from 'sweetalert2';
import { faArrowLeft, faArrowsRotate, faClockRotateLeft, faLock, faPlus } from '@fortawesome/free-solid-svg-icons';
```

por:

```jsx
import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import Swal from 'sweetalert2';
import { faArrowLeft, faArrowsRotate, faClockRotateLeft, faLock, faPlus } from '@fortawesome/free-solid-svg-icons';
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (2 de 5):

```jsx
} from '../utils/expedienteChecklist';
import {
    alternarPlantillaNueva, avanceEtapas, avisoIncompatiblesProceso, conAvance, datosPropioContratacion, errorDeProceso,
    esIdProceso, htmlPendientesCierre, seleccionInicial, textoEstadoExpediente, textoSinPlantillas,
} from '../utils/expedienteContratacion';
import { etiquetaEstadoProceso, numeroProceso, tonoEstado } from '../utils/contratacion';
import { RUTA_PLANTILLAS, rutaFichaProyecto } from '../utils/navegacion';
```

por:

```jsx
} from '../utils/expedienteChecklist';
import {
    alternarPlantillaNueva, avanceEtapas, avisoIncompatiblesProceso, conAvance, datosPropioContratacion, errorDeProceso,
    esIdProceso, htmlPendientesCierre, NOTA_PONDERADO, pondera, seleccionInicial, textoEstadoExpediente, textoSinPlantillas,
} from '../utils/expedienteContratacion';
import { etiquetaEstadoProceso, numeroProceso, tonoEstado } from '../utils/contratacion';
import { RUTA_PLANTILLAS, rutaFichaProyecto } from '../utils/navegacion';
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (3 de 5):

```jsx
const ROLES_EDICION = ['Administrador', 'Gestor'];
const ID_HISTORIAL = 'exc-historial-expediente';

/** Barra de avance con sus cifras: "3 / 8 requisitos cumplidos" y el porcentaje, por separado. */
const Medidor = ({ titulo, cumplidos, total, porcentaje, nota }) => (
    <div className="exv-medidor">
        <div className="exv-medidor-top">
            <b>
```

por:

```jsx
const ROLES_EDICION = ['Administrador', 'Gestor'];
const ID_HISTORIAL = 'exc-historial-expediente';

/**
 * Barra de avance con sus cifras: "3 / 8 requisitos cumplidos" y el porcentaje, por separado.
 * `pie`, si lo hay, va bajo la barra (cómo se calcula el porcentaje).
 */
const Medidor = ({ titulo, cumplidos, total, porcentaje, nota, pie }) => {
    const pieId = useId();
    return (
    <div className="exv-medidor">
        <div className="exv-medidor-top">
            <b>
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (4 de 5):

```jsx
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
```

por:

```jsx
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={porcentaje}
            aria-describedby={pie ? pieId : undefined}
        >
            <div className={`exv-relleno${porcentaje === 100 && total > 0 ? ' completo' : ''}`} style={{ width: `${porcentaje}%` }} />
        </div>
        {pie && <small id={pieId} className="exc-medidor-pie">{pie}</small>}
    </div>
    );
};

/** Página con el botón de volver: la usan la carga, los errores y el expediente. */
const Pagina = ({ children }) => {
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (5 de 5):

```jsx
            ) : (
                <main className="exv-tablero">
                    <div className="exv-barra">
                        <Medidor cumplidos={cumplidos} total={total} porcentaje={porcentaje} nota={avisoOpcionales} />
                        <div className="exv-barra-acciones">
                            <button
                                type="button"
```

por:

```jsx
            ) : (
                <main className="exv-tablero">
                    <div className="exv-barra">
                        <Medidor
                            cumplidos={cumplidos}
                            total={total}
                            porcentaje={porcentaje}
                            nota={avisoOpcionales}
                            pie={pondera(expediente.requisitos) ? NOTA_PONDERADO : null}
                        />
                        <div className="exv-barra-acciones">
                            <button
                                type="button"
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (1 de 10):

```jsx
import { avisarError } from '../utils/avisosPlantillas';
import { opcionesEtapa } from '../utils/contratacion';
import {
    CARACTERES, CATEGORIAS, CONDICIONADO, FUENTES_NORMATIVAS, SIN_ETAPA, TIPOS_REQUISITO, USO_CONTRATACION, agruparPorEtapa, datosRequisito,
    erroresDeEtapa, etiquetaCaracter, etiquetaCategoria, formularioRequisito, htmlErrores, mensajeApi, mover, opcionesResponsable,
    ordenTrasMoverEnEtapa, porRedactar, requisitoVacio, sinEtapa, usoDePlantilla,
} from '../utils/plantillasChecklist';
import useOcupado from './useOcupado';
import '../../css/Contratacion.css';
```

por:

```jsx
import { avisarError } from '../utils/avisosPlantillas';
import { opcionesEtapa } from '../utils/contratacion';
import {
    CARACTERES, CATEGORIAS, CONDICIONADO, FUENTES_NORMATIVAS, PESO_MAXIMO, PESO_MINIMO, SIN_ETAPA, TIPOS_REQUISITO, USO_CONTRATACION, agruparPorEtapa,
    datosRequisito, erroresDelRequisito, etiquetaCaracter, etiquetaCategoria, etiquetaPeso, formularioRequisito, htmlErrores, mensajeApi, mover,
    opcionesResponsable, ordenTrasMoverEnEtapa, porRedactar, requisitoVacio, sinEtapa, usoDePlantilla,
} from '../utils/plantillasChecklist';
import useOcupado from './useOcupado';
import '../../css/Contratacion.css';
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (2 de 10):

```jsx
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
```

por:

```jsx
 * En una plantilla de contratación pide la etapa: `etapas` es su catálogo (null mientras carga o si
 * falló, con `avisoEtapas`) y `errorEtapa`, { texto, enfocar } con lo que el servidor objetó de
 * ella y si el foco debe ir ya al campo (no mientras se muestra el aviso con los demás errores).
 * También pide el peso del requisito en el avance; `errorPeso` es como `errorEtapa`, para el peso.
 */
const FormularioRequisito = ({
    motor, esSgr, contratacion, inicial, catalogoSgr, responsables, etapas, avisoEtapas, errorEtapa, onCambioEtapa, errorPeso, onCambioPeso,
    guardando, onGuardar, onCancelar,
}) => {
    const [f, setF] = useState(inicial);
    const [revisionCondicion, setRevisionCondicion] = useState(null);
    const campoEtapa = useRef(null);
    const campoPeso = useRef(null);
    const cambiar = (campo, valor) => setF((actual) => ({ ...actual, [campo]: valor }));

    // Si el servidor objeta la etapa o el peso, el foco va al campo, donde se muestra el error.
    useEffect(() => {
        if (errorEtapa?.enfocar) campoEtapa.current?.focus();
    }, [errorEtapa]);
    useEffect(() => {
        if (errorPeso?.enfocar) campoPeso.current?.focus();
    }, [errorPeso]);

    const texto = (campo, props = {}) => <input value={f[campo]} onChange={(e) => cambiar(campo, e.target.value)} {...props} />;
    // Las personas salen de los responsables activos; la que el requisito ya tiene se conserva
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (3 de 10):

```jsx
                    {avisoEtapas && <small id="pl-etapa-aviso" className="pl-ayuda">{avisoEtapas}</small>}
                </div>
            )}

            {motor === 'sgr' && (
                <>
```

por:

```jsx
                    {avisoEtapas && <small id="pl-etapa-aviso" className="pl-ayuda">{avisoEtapas}</small>}
                </div>
            )}
            {contratacion && (
                <div className="pl-campo">
                    <label htmlFor="pl-peso">Peso</label>
                    <input
                        id="pl-peso"
                        ref={campoPeso}
                        type="number"
                        inputMode="numeric"
                        required
                        min={PESO_MINIMO}
                        max={PESO_MAXIMO}
                        step={1}
                        value={f.peso}
                        aria-invalid={Boolean(errorPeso)}
                        aria-describedby={[errorPeso && 'pl-peso-error', 'pl-peso-ayuda'].filter(Boolean).join(' ')}
                        onChange={(e) => { cambiar('peso', e.target.value); onCambioPeso(); }}
                    />
                    {errorPeso && <small id="pl-peso-error" className="ct-error-campo">{errorPeso.texto}</small>}
                    <small id="pl-peso-ayuda" className="pl-ayuda">Cuánto pesa en el avance del proceso. 1 si todos valen lo mismo.</small>
                </div>
            )}

            {motor === 'sgr' && (
                <>
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (4 de 10):

```jsx
};

/** Una fila de la lista de requisitos, con sus acciones si la versión es un borrador. */
const FilaRequisito = ({ requisito: r, motor, editable, ocupado, primero, ultimo, onMover, onEditar, onEliminar }) => (
    <li className={r.activo ? '' : 'inactivo'}>
        <div className="pl-fila-texto">
            <span className="pl-codigo">{r.codigo || r.clave}</span>
```

por:

```jsx
};

/** Una fila de la lista de requisitos, con sus acciones si la versión es un borrador. */
const FilaRequisito = ({ requisito: r, motor, contratacion, editable, ocupado, primero, ultimo, onMover, onEditar, onEliminar }) => (
    <li className={r.activo ? '' : 'inactivo'}>
        <div className="pl-fila-texto">
            <span className="pl-codigo">{r.codigo || r.clave}</span>
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (5 de 10):

```jsx
            <span className="pl-fila-etiquetas">
                {r.categoria && <span className="pl-insignia pl-insignia-suave">{etiquetaCategoria(r.categoria)}</span>}
                {motor === 'simple' && <span className="pl-insignia pl-insignia-suave">{etiquetaCaracter(r.caracter)}</span>}
                {porRedactar(r) && <span className="pl-insignia pl-insignia-alerta">Por redactar</span>}
                {!r.activo && <span className="pl-insignia pl-insignia-alerta">Inactivo</span>}
            </span>
```

por:

```jsx
            <span className="pl-fila-etiquetas">
                {r.categoria && <span className="pl-insignia pl-insignia-suave">{etiquetaCategoria(r.categoria)}</span>}
                {motor === 'simple' && <span className="pl-insignia pl-insignia-suave">{etiquetaCaracter(r.caracter)}</span>}
                {contratacion && etiquetaPeso(r.peso) && <span className="pl-insignia pl-insignia-suave">{etiquetaPeso(r.peso)}</span>}
                {porRedactar(r) && <span className="pl-insignia pl-insignia-alerta">Por redactar</span>}
                {!r.activo && <span className="pl-insignia pl-insignia-alerta">Inactivo</span>}
            </span>
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (6 de 10):

```jsx
    const [etapas, setEtapas] = useState(null);
    const [avisoEtapas, setAvisoEtapas] = useState(null);
    const [errorEtapa, setErrorEtapa] = useState(null); // { texto, enfocar } del servidor, bajo el campo
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    const retorno = useRef(null); // data-foco del botón que abrió el formulario
```

por:

```jsx
    const [etapas, setEtapas] = useState(null);
    const [avisoEtapas, setAvisoEtapas] = useState(null);
    const [errorEtapa, setErrorEtapa] = useState(null); // { texto, enfocar } del servidor, bajo el campo
    const [errorPeso, setErrorPeso] = useState(null); // lo mismo, para el peso
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    const retorno = useRef(null); // data-foco del botón que abrió el formulario
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (7 de 10):

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
```

por:

```jsx
    const abrir = (requisito) => {
        retorno.current = requisito === 'nuevo' ? 'nuevo' : `editar-${requisito.id}`;
        setErrorEtapa(null);
        setErrorPeso(null);
        setEditando(requisito);
    };

    const cerrar = () => {
        setErrorEtapa(null);
        setErrorPeso(null);
        setEditando(null);
    };
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (8 de 10):

```jsx
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

por:

```jsx
            cerrar();
            await onCambio();
        } catch (e) {
            // Lo que el servidor objeta de la etapa y del peso va bajo su campo; el resto, en el aviso
            // de siempre. El foco termina en el primero con error (la etapa y, si no, el peso):
            // enseguida si no hay aviso o, si lo hay, al cerrarlo (el aviso no lo devuelve a
            // "Guardar requisito").
            const repartidos = contratacion ? erroresDelRequisito(e) : null;
            if (!repartidos?.etapa.length && !repartidos?.peso.length) {
                avisarError('No se pudo guardar el requisito', e);
                return;
            }
            const deEtapa = repartidos.etapa.length ? repartidos.etapa.join(' ') : null;
            const dePeso = repartidos.peso.length ? repartidos.peso.join(' ') : null;
            const marcar = (enfocar) => {
                setErrorEtapa(deEtapa ? { texto: deEtapa, enfocar } : null);
                setErrorPeso(dePeso ? { texto: dePeso, enfocar: enfocar && !deEtapa } : null);
            };
            if (!repartidos.otros.length) {
                marcar(true);
                return;
            }
            marcar(false);
            await Swal.fire({
                icon: 'error',
                titleText: 'No se pudo guardar el requisito',
                html: htmlErrores(repartidos.mensaje, repartidos.otros),
                returnFocus: false,
            });
            if (deEtapa) setErrorEtapa((actual) => (actual ? { ...actual, enfocar: true } : actual));
            else setErrorPeso((actual) => (actual ? { ...actual, enfocar: true } : actual));
        }
    });
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (9 de 10):

```jsx
                avisoEtapas={avisoEtapas}
                errorEtapa={errorEtapa}
                onCambioEtapa={() => setErrorEtapa(null)}
                guardando={ocupado}
                inicial={editando === 'nuevo' ? requisitoVacio(plantilla.motor, uso) : formularioRequisito(editando, plantilla.motor, uso)}
                onGuardar={guardar}
```

por:

```jsx
                avisoEtapas={avisoEtapas}
                errorEtapa={errorEtapa}
                onCambioEtapa={() => setErrorEtapa(null)}
                errorPeso={errorPeso}
                onCambioPeso={() => setErrorPeso(null)}
                guardando={ocupado}
                inicial={editando === 'nuevo' ? requisitoVacio(plantilla.motor, uso) : formularioRequisito(editando, plantilla.motor, uso)}
                onGuardar={guardar}
```

En `resources/js/components/PlantillaRequisitos.jsx`, reemplazar (10 de 10):

```jsx
            key={r.id}
            requisito={r}
            motor={plantilla.motor}
            editable={editable}
            ocupado={ocupado}
            primero={primero}
```

por:

```jsx
            key={r.id}
            requisito={r}
            motor={plantilla.motor}
            contratacion={contratacion}
            editable={editable}
            ocupado={ocupado}
            primero={primero}
```

En `resources/js/utils/expedienteContratacion.js`, reemplazar (1 de 1):

```js
    return [...conEtapa, ...sinEtapa].map((g) => ({ ...g, ...conteo(g.filas) }));
};

/** El avance por etapa de la API (`avance_por_etapa`, ya en el orden del catálogo), para sus barras. */
export const avanceEtapas = (avance) => (avance ?? []).map((a) => ({
    clave: claveEtapa(a.etapa),
```

por:

```js
    return [...conEtapa, ...sinEtapa].map((g) => ({ ...g, ...conteo(g.filas) }));
};

export const NOTA_PONDERADO = 'El porcentaje pondera cada requisito por su peso.';

/**
 * Si el porcentaje del expediente está ponderado de verdad: algún requisito que cuenta pesa
 * distinto de 1. Con todos en 1 es el mismo que el conteo, y en viabilización las filas no traen peso.
 */
export const pondera = (filas) => (filas ?? []).some((f) => f.cuenta && f.peso !== undefined && f.peso !== null && Number(f.peso) !== 1);

/** El avance por etapa de la API (`avance_por_etapa`, ya en el orden del catálogo), para sus barras. */
export const avanceEtapas = (avance) => (avance ?? []).map((a) => ({
    clave: claveEtapa(a.etapa),
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (1 de 6):

```js
// En el formulario, la persona que el requisito ya tenía: conserva su opción aunque esté inactiva.
const personaDelFormulario = (campoObjeto) => campoObjeto.replace('responsable_', 'persona_');

/**
 * Un requisito nuevo. En una plantilla de contratación (`uso`) lleva además su etapa: el id del
 * select y, en `etapa_actual`, la que el requisito ya tiene (conserva su opción aunque esté inactiva).
 */
export const requisitoVacio = (motor, uso = USO_VIABILIZACION) => ({
    ...Object.fromEntries(CAMPOS_TEXTO.map((c) => [c, ''])),
```

por:

```js
// En el formulario, la persona que el requisito ya tenía: conserva su opción aunque esté inactiva.
const personaDelFormulario = (campoObjeto) => campoObjeto.replace('responsable_', 'persona_');

/** Límites del peso de un requisito de contratación (CatalogoRequisitosController::reglasRequisito). */
export const PESO_MINIMO = 1;
export const PESO_MAXIMO = 100;

/** "Peso 3" para la lista y el expediente; null si pesa 1 (o no trae peso): todos valen lo mismo. */
export const etiquetaPeso = (peso) => {
    if (peso === null || peso === undefined || Number(peso) === 1) return null;
    return `Peso ${peso}`;
};

/**
 * El peso que se envía: un entero; vacío, el de por defecto (1). Lo que no es un número entero
 * viaja tal cual para que el servidor lo objete bajo el campo, sin redondearlo en silencio.
 */
const pesoParaEnviar = (valor) => {
    const texto = String(valor ?? '').trim();
    if (texto === '') return PESO_MINIMO;
    const numero = Number(texto);
    return Number.isNaN(numero) ? texto : numero;
};

/**
 * Un requisito nuevo. En una plantilla de contratación (`uso`) lleva además su etapa: el id del
 * select y, en `etapa_actual`, la que el requisito ya tiene (conserva su opción aunque esté inactiva);
 * y su peso, como texto del input (1 por defecto).
 */
export const requisitoVacio = (motor, uso = USO_VIABILIZACION) => ({
    ...Object.fromEntries(CAMPOS_TEXTO.map((c) => [c, ''])),
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (2 de 6):

```js
    sector_id: '',
    tipo_id: '',
    ...(motor === 'sgr' ? { seccion: 'A', fuente: 'a12', condicion: 'siempre' } : {}),
    ...(uso === USO_CONTRATACION ? { etapa_id: '', etapa_actual: null } : {}),
});

/** La etapa que el requisito ya tiene, para el formulario; sin el objeto de la API, solo su id. */
```

por:

```js
    sector_id: '',
    tipo_id: '',
    ...(motor === 'sgr' ? { seccion: 'A', fuente: 'a12', condicion: 'siempre' } : {}),
    ...(uso === USO_CONTRATACION ? { etapa_id: '', etapa_actual: null, peso: String(PESO_MINIMO) } : {}),
});

/** La etapa que el requisito ya tiene, para el formulario; sin el objeto de la API, solo su id. */
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (3 de 6):

```js
        ...(uso === USO_CONTRATACION ? {
            etapa_id: requisito.etapa_id === null || requisito.etapa_id === undefined ? '' : String(requisito.etapa_id),
            etapa_actual: etapaActual(requisito),
        } : {}),
    };
};
```

por:

```js
        ...(uso === USO_CONTRATACION ? {
            etapa_id: requisito.etapa_id === null || requisito.etapa_id === undefined ? '' : String(requisito.etapa_id),
            etapa_actual: etapaActual(requisito),
            peso: String(requisito.peso ?? base.peso),
        } : {}),
    };
};
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (4 de 6):

```js
/**
 * Lo que espera la API según el motor. Los textos vacíos viajan como null. Con `conPersonas`
 * (el catálogo de responsables cargó y se eligieron en selects) van los ids de las personas; sin
 * él solo viaja el cargo escrito y las personas del requisito no cambian. La etapa solo viaja en
 * una plantilla de contratación (`uso`): la de viabilización la rechaza.
 */
export const datosRequisito = (formulario, motor, conPersonas = false, uso = USO_VIABILIZACION) => {
    const texto = (campo) => (String(formulario[campo] ?? '').trim() || null);
```

por:

```js
/**
 * Lo que espera la API según el motor. Los textos vacíos viajan como null. Con `conPersonas`
 * (el catálogo de responsables cargó y se eligieron en selects) van los ids de las personas; sin
 * él solo viaja el cargo escrito y las personas del requisito no cambian. La etapa y el peso solo
 * viajan en una plantilla de contratación (`uso`): la de viabilización los rechaza.
 */
export const datosRequisito = (formulario, motor, conPersonas = false, uso = USO_VIABILIZACION) => {
    const texto = (campo) => (String(formulario[campo] ?? '').trim() || null);
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (5 de 6):

```js
        activo: formulario.activo,
        codigo_comun: texto('codigo_comun'),
        ...personas,
        ...(uso === USO_CONTRATACION ? { etapa_id: Number(formulario.etapa_id) || null } : {}),
    };

    if (motor === 'sgr') {
```

por:

```js
        activo: formulario.activo,
        codigo_comun: texto('codigo_comun'),
        ...personas,
        ...(uso === USO_CONTRATACION ? { etapa_id: Number(formulario.etapa_id) || null, peso: pesoParaEnviar(formulario.peso) } : {}),
    };

    if (motor === 'sgr') {
```

En `resources/js/utils/plantillasChecklist.js`, reemplazar (6 de 6):

```js
    return { etapa, mensaje: 'El requisito no es válido.', otros: Object.values(resto).flat().map(String) };
};

/** El mensaje y la lista de un 422 de reglas de negocio ({ mensaje, errores }); null si el error es de otra clase. */
export const errorConLista = (error) => {
    const datos = error?.response?.data;
```

por:

```js
    return { etapa, mensaje: 'El requisito no es válido.', otros: Object.values(resto).flat().map(String) };
};

/**
 * Como erroresDeEtapa, pero reparte también los del peso (`errors.peso`), para mostrarlos bajo su
 * input: { etapa, peso, mensaje, otros }. Los dos campos se quitan de la lista del aviso. null si no es 422.
 */
export const erroresDelRequisito = (error) => {
    const base = erroresDeEtapa(error);
    if (!base) return null;
    const errores = error.response.data?.errors;
    const peso = [].concat((errores && typeof errores === 'object' ? errores.peso : null) ?? []).map(String);
    return { etapa: base.etapa, peso, mensaje: base.mensaje, otros: base.otros.filter((e) => !peso.includes(e)) };
};

/** El mensaje y la lista de un 422 de reglas de negocio ({ mensaje, errores }); null si el error es de otra clase. */
export const errorConLista = (error) => {
    const datos = error?.response?.data;
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/expedienteContratacion.test.js resources/js/utils/plantillasChecklist.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1229 passed.

Run: `npm test`
Expected: 722 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/css/ExpedienteContratacion.css resources/js/components/ExpedienteChecklist.jsx resources/js/components/ExpedienteContratacion.jsx resources/js/components/PlantillaRequisitos.jsx resources/js/utils/expedienteContratacion.js resources/js/utils/expedienteContratacion.test.js resources/js/utils/plantillasChecklist.js resources/js/utils/plantillasChecklist.test.js
git commit -m "feat: weight in the template builder and the contracting expedient"
```

---

### Tarea 5: Compilar para producción

- [ ] **Paso 1: Compilar**

Run: `npm run production`
Expected: `Compiled Successfully`, sin errores.

Justo antes de comitear, comprobar que `public/js/app.js` es el de producción: minificado y sin comentarios `/***/` al inicio. Si `npm run watch` lo reescribió, compilar otra vez.

- [ ] **Paso 2: Commit**

```bash
git add public/js/app.js public/js/app.js.map public/mix-manifest.json
git commit -m "build: production bundle for procurement stage 4A"
```

Los archivos que no cambiaron se omiten.

---

### Tarea 6: Verificación en la copia descartable

Usa la copia y las fotos de la tarea 1. Nunca `gest_pro`.

- [ ] **Paso 1: Migrar, revertir y volver a migrar, comparando**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

Expected: se aplica `2026_11_19_100020_add_peso_a_requisitos_catalogo`.

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c7_despues_$f.json" gestpro_verif_tmp && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c7_antes_$f.json" "$TMP_DIR/c7_despues_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

La foto 2 lee columnas fijas de `requisitos_catalogo`, así que no ve `peso`. Se comprueba aparte:

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT peso, COUNT(*) FROM requisitos_catalogo GROUP BY peso;"
```

Expected: una sola fila, `1` con `237`.

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=1 --force
```

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c7_rb_$f.json" gestpro_verif_tmp --solo-tablas && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c7_tablas_antes_$f.json" "$TMP_DIR/c7_rb_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c7_re_$f.json" gestpro_verif_tmp && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c7_antes_$f.json" "$TMP_DIR/c7_re_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

- [ ] **Paso 2: Comprobar que revertir se detiene con un peso distinto de 1**

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "UPDATE requisitos_catalogo SET peso = 3 ORDER BY id LIMIT 1;"
```

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=1 --force
```

Expected: falla con "No se puede revertir: hay requisitos de plantilla con un peso distinto de 1, que se perdería. Restaure un respaldo." y la columna sigue en su sitio.

Devolverlo a 1 y comprobar:

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "UPDATE requisitos_catalogo SET peso = 1 WHERE peso <> 1; SELECT peso, COUNT(*) FROM requisitos_catalogo GROUP BY peso;"
```

Expected: una sola fila, `1` con `237`.

- [ ] **Paso 3: Probar en el navegador sobre la copia**

Compilar en desarrollo (`npx mix`) si `public/` no tiene el código de esta etapa. Arrancar el servidor desde `public/`, en un puerto libre (`netstat -ano | grep 8796`):

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8796 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Crear un Administrador de prueba solo en la copia. Nunca usar sesiones de usuarios reales de `gest_pro`:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'verif@gestpro.test', 'rol' => App\Enums\Rol::Administrador]);"
```

Entrar por `http://127.0.0.1:8796/GestPro/`, borrando `localStorage` si hay una sesión anterior. Todo lo que sigue se hace **solo en la copia**.

Comprobar:

1. **El peso en el constructor:**
   - en `/plantillas-checklist`, pestaña Contratación, abrir "Licitación pública – SECOP II" (en borrador);
   - editar "Estudios previos": junto a la etapa aparece el campo "Peso", con 1 y su ayuda; poner 3 y guardar;
   - la lista muestra "Peso 3" en ese requisito, y nada en los que pesan 1;
   - en una plantilla de viabilización no aparece el campo "Peso".
2. **El expediente nace con el peso:** publicar la versión borrador con `POST /catalogo-requisitos/versiones/{id}/publicar`, usando el token de la sesión. Después pasar el proceso 1 a licitación pública con `PUT /procesos/1`, enviando `modalidad_id: 1` junto con su `objeto`, su `plataforma` y su `actualizado` actuales. El proceso trae `expediente` con 25 requisitos y `progreso.peso_total` 27.
3. **`/contratacion/1`:**
   - bajo el medidor general aparece la nota "El porcentaje pondera cada requisito por su peso.";
   - la fila de "Estudios previos" muestra la etiqueta "Peso 3";
   - al marcar "Estudios previos", el medidor general dice "1 / 25" con 11 %, y la etapa 1 queda al 50 %.
4. **La ficha `/proyectos/3`:** el bloque Procesos dice "1 de 25 requisitos · 11 %" con su enlace "Checklist".
5. **La viabilización no cambia:** `GET /expedientes` sigue con 10 expedientes y `/viabilizacion/<proyecto con expediente>` se ve como antes, sin pesos.

La consola del navegador no muestra errores nuevos. Los 401 de una sesión vieja y los 422 de las validaciones probadas son esperados.

- [ ] **Paso 4: Limpiar**

Detener el servidor, eliminar la copia y devolver `public/` a lo comiteado si se compiló en desarrollo:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 5: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas, 1229.

Run: `npm test`
Expected: pasan todas, 722.

---

### Tarea 7: Informar y pedir validación

- [ ] **Paso 1: Actualizar el estado de la propuesta**

En `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, reemplazar:

```markdown
La fase 3 (expediente de contratación por proceso, migración 20) está validada y aplicada en `gest_pro` (2026-11-18).
```

por:

```markdown
La fase 3 (expediente de contratación por proceso, migración 20) está validada y aplicada en `gest_pro` (2026-11-18). La etapa 4A (peso por requisito y avance ponderado, migración 21) está implementada y verificada en copia, pendiente de validar y aplicar en `gest_pro`.
```

```bash
git add docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md
git commit -m "docs: mark procurement stage 4A as implemented"
```

- [ ] **Paso 2: Informar**

Informar:
- los totales de pruebas;
- las comparaciones (migrar, revertir y volver a migrar), los 237 requisitos con peso 1 y el `down()` detenido con un peso 3;
- lo revisado en el navegador.

Pedir **dos** decisiones por separado:
1. si valida la etapa 4A;
2. si autoriza aplicar la migración 21 en `gest_pro`.

Recordar que, con los datos actuales, todos los requisitos quedan con peso 1 y ningún porcentaje cambia.

---

### Tarea 8: Aplicar en `gest_pro` (solo con autorización)

No ejecutar esta tarea sin un sí explícito del usuario a este paso. Si la comparación muestra diferencias, restaurar el respaldo: no usar `migrate:rollback`.

- [ ] **Paso 1: Respaldar**

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro > "$TMP_DIR/gest_pro_antes_contratacion_4a.sql"
```

Expected: el archivo existe y termina en `-- Dump completed`.

- [ ] **Paso 2: Tomar las fotos antes de migrar (solo tablas)**

```bash
for f in 2 3; do php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c7_real_antes_$f.json" gest_pro --solo-tablas; done
```

- [ ] **Paso 3: Migrar hacia adelante**

Run: `php artisan migrate --force`
Expected: se aplica `2026_11_19_100020_add_peso_a_requisitos_catalogo`.

- [ ] **Paso 4: Comparar**

```bash
for f in 2 3; do php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c7_real_despues_$f.json" gest_pro && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c7_real_antes_$f.json" "$TMP_DIR/c7_real_despues_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

```bash
/c/xampp/mysql/bin/mysql -u root gest_pro -e "SELECT peso, COUNT(*) FROM requisitos_catalogo GROUP BY peso;"
```

Expected: una sola fila, `1` con `237`.

- [ ] **Paso 5: Informar**

Dar el resultado y la ruta completa del respaldo, y avisar que está en una carpeta temporal.

---

## Criterio de la etapa

La etapa está lista cuando:

- cada requisito de una plantilla de contratación tiene un peso de 1 a 100, que se edita en el constructor y se copia al crear versiones y al duplicar;
- el porcentaje de un expediente de contratación pondera cada requisito por su peso, en total y por etapa, en la página, la ficha y la lista de procesos, mientras los conteos y el cierre siguen contando requisitos;
- la viabilización no cambia: mismas respuestas, mismas cifras, y sus pruebas y la paridad del evaluador pasan, aun con un peso distinto de 1 en la base;
- ningún dato cambia al migrar ni al revertir, y revertir se detiene si algún peso es distinto de 1.
