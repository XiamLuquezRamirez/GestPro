# Contratación, etapa 3B: módulo Contratos. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** que los contratos tengan un módulo propio en el menú. Ahí se ven todos los contratos, con o sin proyecto, se filtran, se crean sin proyecto y se abren con su ejecución completa. Además, se puede registrar un contrato desde un proceso adjudicado. La pantalla de contratos de Parámetros sigue igual, pero su código sale de `Parametros.jsx`.

**Arquitectura:**
- **Backend:** `GET /contratos/buscar` y `GET /contratos/{contrato}/detalle`, atendidas por `ContratoController` y `ContratosService`. `listarContratos` reutiliza la misma construcción y su respuesta no cambia.
- **Pantallas:**
  - La gestión de contratos de `Parametros.jsx` pasa a `resources/js/components/contratos/ContratoEditor.jsx` (un contrato con sus pestañas) y a `ContratosProyecto.jsx` (el diálogo del proyecto). Los cálculos van a utilidades con pruebas, y el formato de moneda a `utils/moneda.js`.
  - `ContratosModulo.jsx` atiende `/contratos`, `/contratos/nuevo` y `/contratos/:contratoId`.
  - `ProcesosProyecto.jsx` gana el botón "Registrar contrato".
- **Sin migraciones.**

**Tecnologías:** Laravel 12, PHP 8.2, MariaDB 10.4, React 18 con Laravel Mix y react-router, PHPUnit sobre SQLite en memoria, Vitest.

**Fuente:**
- La propuesta `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md` (fase 2).
- Las decisiones del usuario del 2026-11-18:
  - la empresa es **contratista** y muchas veces solo ejecuta contratos, así que **un contrato puede no tener proyecto**;
  - la fase 2 queda en tres etapas: 3A, datos y reglas, ya aplicada; 3B, este módulo; 3C, SECOP II y la ficha del proyecto en página propia.

**Estado de partida:** rama `feature/contratacion`, igual a `main` (`7bcfb6c`). La etapa 3A está aplicada en `gest_pro`. Pasan 1008 pruebas PHP y 526 JS.

---

## Reglas de ejecución

- **Esta etapa no tiene migraciones ni toca `gest_pro`.** La única base que se usa es una copia descartable, en las tareas 1 y 7.
- Commits autorizados solo en `feature/contratacion`. Sin push. Mensajes en inglés, terminados con la línea `Co-Authored-By` del modelo que comitea.
- Nomenclatura en español sin tildes para clases, métodos y archivos nuevos. Los textos para el usuario van en español con tuteo.
- Pruebas: `php artisan test` sin definir `DB_DATABASE`, para que use SQLite en memoria, y `npm test`. Si Vitest crea `resources/js/node_modules`, se borra.
- **Aplicar los bloques exactos.** Cada reemplazo aparece una sola vez en su archivo. Si no lo encuentras tal cual, detente y avisa. Copia cada bloque completo, carácter por carácter: en etapas anteriores se perdió el espacio después de un `=` al recortar un reemplazo. Se recomienda aplicar los bloques con un script que los lea del plan y exija una coincidencia por reemplazo, en lugar de copiarlos a mano.
- **No usar `git stash`:** el usuario tiene stashes propios.
- El usuario suele tener `npm run watch` corriendo, que reescribe `public/`. Solo la tarea 6 comitea `public/`, después de `npm run production`.
- No tocar ni comitear `public/images/marca-completa.png` ni `public/images/marca-completa1.png`: son cambios del usuario.
- `.env` está versionado: nunca se agrega a un commit. Comitear siempre con rutas explícitas.
- `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio. Hay que definirlo en cada comando, porque el estado de la terminal no se conserva.
- **El prefijo de la API (`/GestPro`) es también la base del SPA.** Las rutas nuevas del front (`/contratos`, `/contratos/nuevo` y `/contratos/:id`) no son rutas GET de la API, y una prueba lo comprueba.

## Decisiones de este plan

**API**
- `GET /contratos/buscar` (lectura):
  - Filtros opcionales: `q`, `estado`, `proyecto` y `proceso`.
    - `q` busca, sin distinguir mayúsculas, en el número, el objeto, el contratista, el contratante, el NIT y la identificación. Un `%` o un `_` se buscan como texto.
    - `proyecto` es un id o `sin`.
  - Orden: id descendente, sin paginación.
  - Cada fila trae el proyecto, el proceso y el tipo resumidos.
  - Un filtro inválido da 422 por campo.
- `GET /contratos/{contrato}/detalle` (lectura):
  - Devuelve la misma forma que un elemento de `GET /listarContratos`, más `proyecto_nombre`.
  - Un contrato que no existe o un id fuera de rango dan 404.

**Extracción de la pantalla de contratos**
- No cambia el comportamiento: mismas llamadas, textos y cálculos.
- La diferencia es que el formulario empieza limpio en cada contrato. Antes, al cerrar con × podía quedar la pestaña o un formulario a medias del contrato anterior.

**Módulo Contratos**
- **Lista:** número, objeto, contratista, proyecto (o "Sin proyecto"), proceso, estado, valor vigente y avances.
- **Filtros:** búsqueda, estado y proyecto, guardados en la URL.
- **Nuevo contrato:** con un proyecto opcional. Al elegir proyecto se cargan sus procesos.
- **Abrir un contrato:** muestra el editor completo.
  - A un contrato sin proyecto se le puede asignar uno.
  - A uno con proyecto se le puede cambiar, pero no quitar.
- **Lectores:** solo consultan.
- **Después de guardar:** un contrato nuevo se abre para seguir con anexos, actas y las demás pestañas. Uno existente vuelve a la lista con sus filtros.

**Navegación**
- Destino "Contratos" (`/contratos`) en el trabajo diario.
- El análisis que se llamaba "Contratos" pasa a llamarse "Avance de contratos", también en la pestaña del tablero, y tiene otro icono.

**Registrar contrato desde el proceso**
- Solo en procesos `adjudicado` o `celebrado`, y para quien puede editar.
- Abre el editor sobre el diálogo de procesos, con el proceso elegido y la precarga aplicada.
- **Al guardar:**
  - se recargan los procesos;
  - se avisa a Parámetros para que su lista de contratos no quede vieja;
  - se propone pasar el proceso a celebrado, como en la 3A.
- Mientras guarda, el diálogo no se cierra con Escape ni con la ×.

## Fuera de esta etapa

Etapa 3C:
- **SECOP II:** buscar por número, ID o enlace, listar los contratos de la empresa por su NIT, importar y "Actualizar desde SECOP".
- **Ficha del proyecto:** página propia en `/proyectos/:id`, con General, Formulación, Procesos y Contratos.

Eliminar contratos desde el módulo: se sigue haciendo desde Parámetros.

## Lo que ya se ensayó

El plan se ensayó completo en un clon descartable fuera del repositorio, y este documento se generó con el código de ese ensayo. Un script comprobó que aplicar sus bloques sobre la rama reproduce el ensayo exactamente.

- **Pruebas:** con el código de las tareas 2 a 5 pasan 1028 pruebas PHP y 579 JS.
- **En una copia de `gest_pro` con los datos reales,** la foto de la tarea 1 no mostró ninguna diferencia entre el código anterior y el nuevo. Cubre `GET /listarContratos`, los contratos de `GET /proyectos` y los expedientes.
- **En el navegador, sobre esa copia:**
  - **Módulo Contratos:**
    - `/contratos` listó los 7 contratos con proyecto, estado, valor vigente y avances;
    - un contrato nuevo sin proyecto se guardó y se abrió con sus pestañas;
    - una adición registrada desde el módulo subió su valor vigente de $250.000.000 a $300.000.000;
    - los filtros "Sin proyecto" y de texto lo encontraron.
  - **Registrar contrato desde un proceso:**
    - con el proceso adjudicado, "Registrar contrato" abrió el editor encima del diálogo de procesos, con la precarga;
    - al guardar, la propuesta lo pasó a celebrado y la lista mostró 1 contrato.
  - **Parámetros:** la pantalla de contratos de un proyecto se vio y guardó igual que en la etapa 3A, con sus pestañas de actas y actividades.
  - **Usuario de Consulta:** no vio "Nuevo contrato" y abrió el contrato en solo lectura.
  - **Consola:** sin errores nuevos.
- **Una revisión independiente** comparó línea por línea la pantalla extraída con la de `Parametros.jsx` y no encontró regresiones. Sus hallazgos, uno medio y cinco bajos, ya están corregidos en las tareas correspondientes:
  - tras registrar un contrato desde un proceso, la lista de contratos de Parámetros quedaba vieja;
  - al cambiar de proyecto, se podían elegir procesos del proyecto anterior mientras cargaban los nuevos;
  - con un error de búsqueda se veía la tabla del filtro anterior;
  - un id fuera de rango daba 500;
  - el editor se podía cerrar mientras guardaba;
  - la pestaña del tablero seguía diciendo "Contratos".

---

### Tarea 1: Punto de partida y foto con el código actual

La foto se toma **antes de cambiar código**. Sirve para comprobar, en la tarea 7, que los listados de contratos de la API devuelven lo mismo con el código nuevo. La copia `gestpro_verif_tmp` se conserva hasta la tarea 7.

- [ ] **Paso 1: Comprobar la rama, el estado y las pruebas**

Run: `git branch --show-current`
Expected: `feature/contratacion`

Run: `git status --short`
Expected: como mucho, `public/` (si corre `npm run watch`) y las dos imágenes del usuario.

Run: `php artisan test`
Expected: pasan todas, 1008. Si alguna falla, detenerse y avisar.

Run: `npm test`
Expected: pasan todas, 526.

- [ ] **Paso 2: Crear la copia descartable**

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE IF EXISTS gestpro_verif_tmp; CREATE DATABASE gestpro_verif_tmp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro | /c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp
```

Si MySQL no responde (`ERROR 2002`), está apagado. Hay que pedirle al usuario que lo arranque desde XAMPP.

- [ ] **Paso 3: Crear los dos scripts de comprobación**

Si siguen en `$TMP_DIR` desde la etapa 3A, `gestpro_foto_contratacion3.php` y `gestpro_comparar_contratacion3.php` se reutilizan tal cual. Si no, se crean con este contenido.

`$TMP_DIR/gestpro_foto_contratacion3.php`:

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

`$TMP_DIR/gestpro_comparar_contratacion3.php`:

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

- [ ] **Paso 4: Tomar la foto de antes, con el código actual**

Desde la raíz del repositorio:

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion3.php" "$TMP_DIR/c4_antes.json" gestpro_verif_tmp
```

Expected: `Contratos: 7, procesos: 1, actas: 2, expedientes: 10, con API`, con las cifras actuales de `gest_pro`.

---

### Tarea 2: API de búsqueda y detalle de contratos

`GET /contratos/buscar` con filtros y `GET /contratos/{contrato}/detalle` con la forma de `listarContratos`, que reutiliza la misma construcción sin cambiar su respuesta.

**Files:**
- Test (create): `tests/Feature/Contratacion/ContratosBusquedaApiTest.php`
- Modify: `app/Http/Controllers/ContratoController.php`
- Modify: `app/Http/Controllers/ProyectoController.php`
- Modify: `app/Services/Contratacion/ContratosService.php`
- Modify: `routes/contratacion.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/ContratosBusquedaApiTest.php`:

```php
<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\ActividadAvance;
use App\Models\ActividadContrato;
use App\Models\AnexoContrato;
use App\Models\AvanceFinanciero;
use App\Models\Contrato;
use App\Models\Modalidad;
use App\Models\ModificacionContrato;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Contratos: búsqueda de todos los contratos (con o sin proyecto) y detalle de uno. */
class ContratosBusquedaApiTest extends TestCase
{
    use RefreshDatabase;

    private const BUSCAR = '/GestPro/contratos/buscar';

    private Proyecto $proyecto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
    }

    private function como(Rol|User $quien = Rol::Consulta): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function contrato(array $mas = []): Contrato
    {
        return Contrato::create([
            'proyecto' => $this->proyecto->id,
            'n_contrato' => 'C-001-2026',
            'objeto' => 'Construcción del acueducto veredal',
            'valor_inicial' => 1000000,
            'valor' => 1000000,
            'estado' => 'en_ejecucion',
            ...$mas,
        ]);
    }

    private function proceso(?Proyecto $proyecto = null): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => ($proyecto ?? $this->proyecto)->id,
            'objeto' => 'Obra del acueducto',
            'modalidad' => Modalidad::where('clave', 'licitacion_publica')->firstOrFail()->id,
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-001-2026',
            'estado' => 'adjudicado',
        ]);
    }

    private function ids(array $filtros = []): array
    {
        return collect($this->como()->getJson(self::BUSCAR . '?' . http_build_query($filtros))->assertOk()->json())->pluck('id')->all();
    }

    // ------------------------------------------------------------------ buscar

    public function test_la_fila_trae_los_campos_acordados(): void
    {
        $tipo = TipoProceso::create(['nombre' => 'Obra', 'activo' => true]);
        $proceso = $this->proceso();
        $c = $this->contrato([
            'contratista' => 'Constructora S.A.S.', 'contratante' => 'Alcaldía', 'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31', 'avance_fisico' => 40, 'avance_financiero' => 30,
            'tipo_contrato_id' => $tipo->id, 'proceso_licitacion' => $proceso->id, 'secop_id_contrato' => 'CO1.PCCNTR.1',
        ]);

        $filas = $this->como()->getJson(self::BUSCAR)->assertOk()->json();

        $this->assertCount(1, $filas);
        $fila = $filas[0];
        $this->assertSame(
            ['id', 'n_contrato', 'objeto', 'contratista', 'contratante', 'estado', 'valor', 'valor_inicial', 'fecha_inicio', 'fecha_fin',
                'avance_fisico', 'avance_financiero', 'tipo_contrato', 'proyecto', 'proceso', 'secop_id_contrato', 'updated_at'],
            array_keys($fila),
        );
        $this->assertSame($c->id, $fila['id']);
        $this->assertSame('C-001-2026', $fila['n_contrato']);
        $this->assertSame('Constructora S.A.S.', $fila['contratista']);
        $this->assertSame('en_ejecucion', $fila['estado']);
        $this->assertEquals(1000000, $fila['valor']);
        $this->assertEquals(1000000, $fila['valor_inicial']);
        $this->assertSame('2026-01-01', substr($fila['fecha_inicio'], 0, 10));
        $this->assertSame(40, $fila['avance_fisico']);
        $this->assertSame(30, $fila['avance_financiero']);
        $this->assertSame(['id' => $tipo->id, 'nombre' => 'Obra'], $fila['tipo_contrato']);
        $this->assertSame(['id' => $this->proyecto->id, 'nombre' => 'Acueducto veredal'], $fila['proyecto']);
        $this->assertSame(['id', 'codigo_proceso', 'objeto', 'estado', 'modalidad', 'plataforma', 'url_proceso'], array_keys($fila['proceso']));
        $this->assertSame($proceso->id, $fila['proceso']['id']);
        $this->assertSame('CO1.PCCNTR.1', $fila['secop_id_contrato']);
        $this->assertNotNull($fila['updated_at']);
    }

    public function test_un_contrato_sin_proyecto_ni_proceso_ni_tipo_trae_nulos(): void
    {
        $this->contrato(['proyecto' => null]);

        $fila = $this->como()->getJson(self::BUSCAR)->assertOk()->json(0);

        $this->assertNull($fila['proyecto']);
        $this->assertNull($fila['proceso']);
        $this->assertNull($fila['tipo_contrato']);
        $this->assertNull($fila['avance_fisico']);
    }

    public function test_devuelve_todos_ordenados_por_id_descendente(): void
    {
        $a = $this->contrato(['n_contrato' => 'A']);
        $b = $this->contrato(['n_contrato' => 'B', 'proyecto' => null]);
        $c = $this->contrato(['n_contrato' => 'C']);

        $this->assertSame([$c->id, $b->id, $a->id], $this->ids());
    }

    public function test_sin_contratos_devuelve_una_lista_vacia(): void
    {
        $this->como()->getJson(self::BUSCAR)->assertOk()->assertExactJson([]);
    }

    public function test_filtra_por_texto_en_todos_los_campos_sin_distinguir_mayusculas(): void
    {
        $numero = $this->contrato(['n_contrato' => 'CTO-777']);
        $objeto = $this->contrato(['n_contrato' => 'X1', 'objeto' => 'Pavimentación de la Vía Norte']);
        $contratista = $this->contrato(['n_contrato' => 'X2', 'contratista' => 'Ingeniería Andina']);
        $contratante = $this->contrato(['n_contrato' => 'X3', 'contratante' => 'Gobernación del Cauca']);
        $nit = $this->contrato(['n_contrato' => 'X4', 'contratante_nit' => '800.123.456-7']);
        $identificacion = $this->contrato(['n_contrato' => 'X5', 'contratista_identificacion' => '900555111']);
        $this->contrato(['n_contrato' => 'X6']);

        $this->assertSame([$numero->id], $this->ids(['q' => 'cto-777']));
        $this->assertSame([$objeto->id], $this->ids(['q' => 'vía norte']));
        $this->assertSame([$contratista->id], $this->ids(['q' => 'ANDINA']));
        $this->assertSame([$contratante->id], $this->ids(['q' => 'cauca']));
        $this->assertSame([$nit->id], $this->ids(['q' => '123.456']));
        $this->assertSame([$identificacion->id], $this->ids(['q' => '555111']));
    }

    public function test_el_texto_no_trata_porcentaje_ni_guion_bajo_como_comodines(): void
    {
        $this->contrato(['n_contrato' => 'ABC']);
        $literal = $this->contrato(['n_contrato' => 'A%C_1']);

        $this->assertSame([$literal->id], $this->ids(['q' => '%C_']));
        $this->assertSame([], $this->ids(['q' => 'A_C']));
    }

    public function test_filtra_por_estado(): void
    {
        $this->contrato(['n_contrato' => 'A', 'estado' => 'en_ejecucion']);
        $liquidado = $this->contrato(['n_contrato' => 'B', 'estado' => 'liquidado']);

        $this->assertSame([$liquidado->id], $this->ids(['estado' => 'liquidado']));
    }

    public function test_filtra_por_proyecto_y_sin_proyecto(): void
    {
        $otro = Proyecto::create(['nombre' => 'Otro']);
        $this->contrato(['n_contrato' => 'A']);
        $deOtro = $this->contrato(['n_contrato' => 'B', 'proyecto' => $otro->id]);
        $suelto = $this->contrato(['n_contrato' => 'C', 'proyecto' => null]);

        $this->assertSame([$deOtro->id], $this->ids(['proyecto' => $otro->id]));
        $this->assertSame([$suelto->id], $this->ids(['proyecto' => 'sin']));
    }

    public function test_filtra_por_proceso(): void
    {
        $proceso = $this->proceso();
        $this->contrato(['n_contrato' => 'A']);
        $vinculado = $this->contrato(['n_contrato' => 'B', 'proceso_licitacion' => $proceso->id]);

        $this->assertSame([$vinculado->id], $this->ids(['proceso' => $proceso->id]));
    }

    public function test_combina_filtros(): void
    {
        $esta = $this->contrato(['n_contrato' => 'A', 'objeto' => 'Puente', 'estado' => 'terminado']);
        $this->contrato(['n_contrato' => 'B', 'objeto' => 'Puente', 'estado' => 'en_ejecucion']);
        $this->contrato(['n_contrato' => 'C', 'objeto' => 'Vía', 'estado' => 'terminado']);

        $this->assertSame([$esta->id], $this->ids(['q' => 'puente', 'estado' => 'terminado', 'proyecto' => $this->proyecto->id]));
    }

    public function test_los_filtros_vacios_se_ignoran(): void
    {
        $a = $this->contrato();

        $this->assertSame([$a->id], $this->ids(['q' => '', 'estado' => '', 'proyecto' => '', 'proceso' => '']));
    }

    public function test_filtros_invalidos_responden_422_con_mensaje_por_campo(): void
    {
        $respuesta = $this->como()->getJson(self::BUSCAR . '?' . http_build_query([
            'estado' => 'cualquiera', 'proyecto' => 'abc', 'proceso' => '-1', 'q' => str_repeat('a', 101),
        ]))->assertStatus(422);

        $errores = $respuesta->json('errors');
        $this->assertEqualsCanonicalizing(['estado', 'proyecto', 'proceso', 'q'], array_keys($errores));
        $this->assertSame('Elige un estado de la lista.', $errores['estado'][0]);
        $this->assertSame('Elige un proyecto de la lista o "sin".', $errores['proyecto'][0]);
        $this->assertSame('Revisa el proceso: debe ser un número entero.', $errores['proceso'][0]);
        $this->assertSame('En la búsqueda caben hasta 100 caracteres.', $errores['q'][0]);
    }

    public function test_cualquier_usuario_autenticado_lee_y_sin_sesion_no(): void
    {
        $this->contrato();

        $this->getJson(self::BUSCAR)->assertUnauthorized();
        foreach ([Rol::Consulta, Rol::Gestor, Rol::Administrador] as $rol) {
            $this->como($rol)->getJson(self::BUSCAR)->assertOk()->assertJsonCount(1);
        }
    }

    // ------------------------------------------------------------------ detalle

    private function conTodo(): Contrato
    {
        $tipo = TipoProceso::create(['nombre' => 'Obra', 'activo' => true]);
        $c = $this->contrato(['proceso_licitacion' => $this->proceso()->id, 'tipo_contrato_id' => $tipo->id]);
        AnexoContrato::create(['contrato_id' => $c->id, 'descripcion' => 'Minuta', 'nombre_archivo' => 'm.pdf', 'ruta_archivo' => 'anexos_contratos/m.pdf', 'fecha' => '2026-01-02']);
        AvanceFinanciero::create(['contrato_id' => $c->id, 'descripcion' => 'Acta 1', 'fecha_acta' => '2026-02-01', 'valor_facturado' => 10]);
        ModificacionContrato::create(['contrato_id' => $c->id, 'tipo' => 'prorroga', 'dias_prorroga' => 10, 'fecha_modificacion' => '2026-03-01']);
        $actividad = ActividadContrato::create(['contrato_id' => $c->id, 'nombre' => 'Excavación', 'peso' => 50]);
        ActividadAvance::create(['actividad_id' => $actividad->id, 'fecha' => '2026-02-01', 'porcentaje_ejecucion' => 20]);
        ActividadAvance::create(['actividad_id' => $actividad->id, 'fecha' => '2026-03-01', 'porcentaje_ejecucion' => 60]);

        return $c;
    }

    public function test_el_detalle_tiene_la_misma_forma_que_un_elemento_de_listar_contratos(): void
    {
        $c = $this->conTodo();

        $listado = $this->como()->getJson('/GestPro/listarContratos?proyecto=' . $this->proyecto->id)->assertOk()->json(0);
        $detalle = $this->como()->getJson("/GestPro/contratos/{$c->id}/detalle")->assertOk()->json();

        $this->assertSame('Acueducto veredal', $detalle['proyecto_nombre']);
        unset($detalle['proyecto_nombre']);
        $this->assertEquals($listado, $detalle);
        $this->assertSame(array_keys($listado), array_keys($detalle));
        $this->assertCount(1, $detalle['anexos']);
        $this->assertCount(1, $detalle['avancesFinancieros']);
        $this->assertCount(1, $detalle['modificaciones']);
        $this->assertEquals(60, $detalle['actividades'][0]['ultimo_avance']);
        $this->assertCount(2, $detalle['actividades'][0]['avances']);
        $this->assertSame($c->proceso_licitacion, $detalle['proceso_id']);
        $this->assertSame('LP-001-2026', $detalle['proceso']['codigo_proceso']);
    }

    public function test_el_detalle_de_un_contrato_sin_proyecto(): void
    {
        $c = $this->contrato(['proyecto' => null]);

        $detalle = $this->como()->getJson("/GestPro/contratos/{$c->id}/detalle")->assertOk()->json();

        $this->assertNull($detalle['proyecto']);
        $this->assertNull($detalle['proyecto_nombre']);
        $this->assertNull($detalle['proceso']);
        $this->assertSame([], $detalle['anexos']);
        $this->assertSame([], $detalle['actividades']);
    }

    public function test_el_detalle_inexistente_responde_404_con_mensaje(): void
    {
        $this->como()->getJson('/GestPro/contratos/9999/detalle')->assertNotFound()->assertExactJson(['mensaje' => 'El contrato no existe.']);
    }

    public function test_el_detalle_con_un_id_fuera_de_rango_responde_404_con_mensaje(): void
    {
        foreach (['99999999999999999999', '9223372036854775808'] as $id) {
            $this->como()->getJson("/GestPro/contratos/{$id}/detalle")->assertNotFound()->assertExactJson(['mensaje' => 'El contrato no existe.']);
        }
    }

    public function test_el_detalle_exige_sesion_y_lo_lee_cualquier_rol(): void
    {
        $c = $this->contrato();

        $this->getJson("/GestPro/contratos/{$c->id}/detalle")->assertUnauthorized();
        $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$c->id}/detalle")->assertOk();
    }

    // ------------------------------------------------------------------ rutas

    public function test_las_rutas_del_spa_no_son_rutas_get_de_la_api(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => in_array('GET', $ruta->methods(), true))
            ->map(fn ($ruta) => $ruta->uri())
            ->all();

        $this->assertNotContains('GestPro/contratos', $uris);
        $this->assertNotContains('GestPro/contratos/{contrato}', $uris);
        $this->assertContains('GestPro/contratos/buscar', $uris);
        $this->assertContains('GestPro/contratos/{contrato}/detalle', $uris);
    }

    public function test_las_rutas_existentes_de_contratos_no_cambian(): void
    {
        $this->contrato();

        $this->como()->getJson('/GestPro/listarContratos?proyecto=' . $this->proyecto->id)->assertOk()->assertJsonCount(1);
        $this->como(Rol::Consulta)->postJson('/GestPro/guardarContrato', ['formContrato' => []])->assertForbidden();
        $this->como(Rol::Consulta)->postJson('/GestPro/eliminarContrato', ['id' => 1])->assertForbidden();
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/ContratosBusquedaApiTest.php`
Expected: FALLAN 19 y pasa 1: las rutas `GET /contratos/buscar` y `GET /contratos/{contrato}/detalle` no existen.

- [ ] **Paso 3: Implementar**

En `app/Http/Controllers/ContratoController.php`, reemplazar (1 de 3):

```php
use Illuminate\Validation\ValidationException;

/**
 * Guardar y eliminar contratos (can:editar-datos). La lectura sigue en ProyectoController
 * (GET /proyectos y GET /listarContratos).
 */
class ContratoController extends Controller
{
```

por:

```php
use Illuminate\Validation\ValidationException;

/**
 * Guardar y eliminar contratos (can:editar-datos). Lectura: buscar y detalle (cualquier usuario
 * autenticado); los listados por proyecto siguen en ProyectoController (GET /proyectos y
 * GET /listarContratos).
 */
class ContratoController extends Controller
{
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (2 de 3):

```php
        'anexos.*.rutaArchivo.required' => 'El anexo no es válido: vuelve a subirlo.',
        'anexos.*.fecha.required' => 'Falta la fecha del anexo.',
        'id.required' => 'Falta el contrato que quieres eliminar.',
    ];

    private const ATRIBUTOS = [
        'id' => 'el contrato',
        'proyecto' => 'el proyecto',
        'proceso_id' => 'el proceso',
        'tipo_contrato_id' => 'el tipo de contrato',
        'n_contrato' => 'el número del contrato',
```

por:

```php
        'anexos.*.rutaArchivo.required' => 'El anexo no es válido: vuelve a subirlo.',
        'anexos.*.fecha.required' => 'Falta la fecha del anexo.',
        'id.required' => 'Falta el contrato que quieres eliminar.',
        'q.max' => 'En la búsqueda caben hasta 100 caracteres.',
        'q.string' => 'Revisa la búsqueda: debe ser un texto.',
    ];

    private const ATRIBUTOS = [
        'id' => 'el contrato',
        'q' => 'la búsqueda',
        'proyecto' => 'el proyecto',
        'proceso' => 'el proceso',
        'proceso_id' => 'el proceso',
        'tipo_contrato_id' => 'el tipo de contrato',
        'n_contrato' => 'el número del contrato',
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (3 de 3):

```php
        ]);
    }

    public function eliminar(Request $request): JsonResponse
    {
        $datos = $request->validate(['id' => ['bail', 'required', $this->entero()]], self::MENSAJES, self::ATRIBUTOS);
```

por:

```php
        ]);
    }

    /**
     * Todos los contratos, con o sin proyecto. Filtros opcionales: `q`, `estado`, `proyecto`
     * (un id o `sin`) y `proceso`. Un filtro inválido responde 422.
     */
    public function buscar(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'q' => ['sometimes', 'nullable', 'bail', 'string', 'max:100'],
            'estado' => ['sometimes', 'nullable', 'bail', 'string', Rule::in(Contrato::ESTADOS)],
            'proyecto' => ['sometimes', 'nullable', 'bail', fn (string $atributo, mixed $valor, Closure $falla) => $valor === 'sin'
                || (is_string($valor) && preg_match('/^\d+$/', $valor) === 1)
                || $falla('Elige un proyecto de la lista o "sin".')],
            'proceso' => ['sometimes', 'nullable', 'bail', $this->entero()],
        ], self::MENSAJES, self::ATRIBUTOS);

        return response()->json($this->contratos->buscar($filtros));
    }

    /**
     * El contrato completo, con la forma de un elemento de GET /listarContratos más `proyecto_nombre`.
     * El id llega como texto: uno de más de 18 cifras no cabe en un entero y tampoco existe.
     */
    public function detalle(string $contrato): JsonResponse
    {
        $fila = preg_match('/^[0-9]{1,18}$/', $contrato) === 1 ? $this->contratos->detalle((int) $contrato) : null;
        if ($fila === null) {
            return response()->json(['mensaje' => 'El contrato no existe.'], 404);
        }

        return response()->json($fila);
    }

    public function eliminar(Request $request): JsonResponse
    {
        $datos = $request->validate(['id' => ['bail', 'required', $this->entero()]], self::MENSAJES, self::ATRIBUTOS);
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (1 de 1):

```php
        $proyecto = $request->all();
        $contratos = DB::table('contratos')->where('proyecto', $proyecto['proyecto'])->get();
        app(ContratosService::class)->agregarProcesos($contratos);

        foreach ($contratos as $contrato) {
            $anexos = DB::table('anexos_contratos')->where('contrato_id', $contrato->id)->get();
            $contrato->anexos = $anexos;

            $contrato->avancesFinancieros = DB::table('avance_financiero')
                ->where('contrato_id', $contrato->id)
                ->orderBy('fecha_acta', 'desc')
                ->get();

            $contrato->modificaciones = DB::table('modificaciones_contrato')
                ->where('contrato_id', $contrato->id)
                ->orderBy('fecha_modificacion', 'asc')
                ->orderBy('id', 'asc')
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
                $actividad->avances = DB::table('actividad_avances')
                    ->select('id', 'fecha', 'porcentaje_ejecucion')
                    ->where('actividad_id', $actividad->id)
                    ->orderBy('fecha', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();
            }
            $contrato->actividades = $actividades;
        }

        return response()->json($contratos);
    }
```

por:

```php
        $proyecto = $request->all();
        $contratos = DB::table('contratos')->where('proyecto', $proyecto['proyecto'])->get();
        app(ContratosService::class)->agregarProcesos($contratos);
        app(ContratosService::class)->agregarDetalle($contratos);

        return response()->json($contratos);
    }
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (1 de 1):

```php
        }
    }

    /** @return array{id: int, codigo_proceso: ?string, objeto: ?string, estado: string, modalidad: ?array, plataforma: ?string, url_proceso: ?string} */
    private function resumenProceso(ProcesoLicitacion $proceso): array
    {
```

por:

```php
        }
    }

    /**
     * Completa las filas de contratos de los listados (consultas con DB::table) con sus anexos, actas
     * financieras, modificaciones y actividades (cada una con su último avance y su historial).
     *
     * @param iterable<object> $filas
     */
    public function agregarDetalle(iterable $filas): void
    {
        foreach ($filas as $contrato) {
            $contrato->anexos = DB::table('anexos_contratos')->where('contrato_id', $contrato->id)->get();

            $contrato->avancesFinancieros = DB::table('avance_financiero')
                ->where('contrato_id', $contrato->id)
                ->orderBy('fecha_acta', 'desc')
                ->get();

            $contrato->modificaciones = DB::table('modificaciones_contrato')
                ->where('contrato_id', $contrato->id)
                ->orderBy('fecha_modificacion', 'asc')
                ->orderBy('id', 'asc')
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
                $actividad->avances = DB::table('actividad_avances')
                    ->select('id', 'fecha', 'porcentaje_ejecucion')
                    ->where('actividad_id', $actividad->id)
                    ->orderBy('fecha', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();
            }
            $contrato->actividades = $actividades;
        }
    }

    /**
     * Todos los contratos, con o sin proyecto, del más reciente al más antiguo. Filtros opcionales:
     * `q` (texto en número, objeto, contratista, contratante, NIT e identificación), `estado`,
     * `proyecto` (id o 'sin') y `proceso` (id).
     *
     * @param array{q?: ?string, estado?: ?string, proyecto?: int|string|null, proceso?: int|string|null} $filtros
     * @return list<array<string, mixed>>
     */
    public function buscar(array $filtros): array
    {
        $consulta = DB::table('contratos')
            ->leftJoin('proyectos', 'proyectos.id', '=', 'contratos.proyecto')
            ->leftJoin('tipos_procesos', 'tipos_procesos.id', '=', 'contratos.tipo_contrato_id')
            ->select([
                'contratos.id', 'contratos.n_contrato', 'contratos.objeto', 'contratos.contratista', 'contratos.contratante',
                'contratos.estado', 'contratos.valor', 'contratos.valor_inicial', 'contratos.fecha_inicio', 'contratos.fecha_fin',
                'contratos.avance_fisico', 'contratos.avance_financiero', 'contratos.secop_id_contrato', 'contratos.updated_at',
                'contratos.proceso_licitacion',
                'contratos.proyecto as proyecto_id', 'proyectos.nombre as proyecto_nombre',
                'contratos.tipo_contrato_id', 'tipos_procesos.nombre as tipo_nombre',
            ])
            ->orderByDesc('contratos.id');

        $texto = trim((string) ($filtros['q'] ?? ''));
        if ($texto !== '') {
            // Sin comodines: el % y el _ que escribe la persona se buscan tal cual.
            $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $texto) . '%';
            $consulta->where(function ($q) use ($patron) {
                foreach (['n_contrato', 'objeto', 'contratista', 'contratante', 'contratante_nit', 'contratista_identificacion'] as $columna) {
                    $q->orWhereRaw("contratos.{$columna} LIKE ? ESCAPE '!'", [$patron]);
                }
            });
        }
        if (($filtros['estado'] ?? null) !== null) {
            $consulta->where('contratos.estado', $filtros['estado']);
        }
        if (($filtros['proyecto'] ?? null) !== null) {
            $filtros['proyecto'] === 'sin'
                ? $consulta->whereNull('contratos.proyecto')
                : $consulta->where('contratos.proyecto', (int) $filtros['proyecto']);
        }
        if (($filtros['proceso'] ?? null) !== null) {
            $consulta->where('contratos.proceso_licitacion', (int) $filtros['proceso']);
        }

        $filas = $consulta->get();
        $this->agregarProcesos($filas);

        return $filas->map(fn ($f) => [
            'id' => (int) $f->id,
            'n_contrato' => $f->n_contrato,
            'objeto' => $f->objeto,
            'contratista' => $f->contratista,
            'contratante' => $f->contratante,
            'estado' => $f->estado,
            'valor' => $f->valor,
            'valor_inicial' => $f->valor_inicial,
            'fecha_inicio' => $f->fecha_inicio,
            'fecha_fin' => $f->fecha_fin,
            'avance_fisico' => $this->entero($f->avance_fisico),
            'avance_financiero' => $this->entero($f->avance_financiero),
            'tipo_contrato' => $f->tipo_contrato_id !== null ? ['id' => (int) $f->tipo_contrato_id, 'nombre' => $f->tipo_nombre] : null,
            'proyecto' => $f->proyecto_id !== null ? ['id' => (int) $f->proyecto_id, 'nombre' => $f->proyecto_nombre] : null,
            'proceso' => $f->proceso,
            'secop_id_contrato' => $f->secop_id_contrato,
            'updated_at' => $f->updated_at,
        ])->all();
    }

    /**
     * El contrato con la misma forma que un elemento de GET /listarContratos, más `proyecto_nombre`,
     * o null si no existe.
     */
    public function detalle(int $id): ?object
    {
        $fila = DB::table('contratos')
            ->leftJoin('proyectos', 'proyectos.id', '=', 'contratos.proyecto')
            ->where('contratos.id', $id)
            ->select(['contratos.*', 'proyectos.nombre as proyecto_nombre'])
            ->first();
        if ($fila === null) {
            return null;
        }
        $filas = [$fila];
        $this->agregarProcesos($filas);
        $this->agregarDetalle($filas);

        return $fila;
    }

    /** @return array{id: int, codigo_proceso: ?string, objeto: ?string, estado: string, modalidad: ?array, plataforma: ?string, url_proceso: ?string} */
    private function resumenProceso(ProcesoLicitacion $proceso): array
    {
```

En `routes/contratacion.php`, reemplazar (1 de 2):

```php
 * estas rutas exigen JWT; aquí solo se agrega el permiso de escritura.
 */

use App\Http\Controllers\ProcesoContratacionController;
use Illuminate\Support\Facades\Route;
```

por:

```php
 * estas rutas exigen JWT; aquí solo se agrega el permiso de escritura.
 */

use App\Http\Controllers\ContratoController;
use App\Http\Controllers\ProcesoContratacionController;
use Illuminate\Support\Facades\Route;
```

En `routes/contratacion.php`, reemplazar (2 de 2):

```php
Route::get('/procesos/{proceso}/precarga-contrato', [ProcesoContratacionController::class, 'precargaContrato'])
    ->name('procesos.precarga-contrato');

// Procesos de contratación: Administrador o Gestor.
Route::middleware('can:editar-datos')->group(function () {
    Route::post('/proyectos/{proyecto}/procesos', [ProcesoContratacionController::class, 'store'])
```

por:

```php
Route::get('/procesos/{proceso}/precarga-contrato', [ProcesoContratacionController::class, 'precargaContrato'])
    ->name('procesos.precarga-contrato');

// Contratos de todos los proyectos (y los que no tienen proyecto). Sin GET /contratos ni
// GET /contratos/{id}: esas rutas son del SPA y no deben coincidir con la API.
Route::get('/contratos/buscar', [ContratoController::class, 'buscar'])
    ->name('contratos.buscar');
Route::get('/contratos/{contrato}/detalle', [ContratoController::class, 'detalle'])
    ->whereNumber('contrato')
    ->name('contratos.detalle');

// Procesos de contratación: Administrador o Gestor.
Route::middleware('can:editar-datos')->group(function () {
    Route::post('/proyectos/{proyecto}/procesos', [ProcesoContratacionController::class, 'store'])
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/ContratosBusquedaApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1028 passed.

- [ ] **Paso 5: Commit**

```bash
git add tests/Feature/Contratacion/ContratosBusquedaApiTest.php app/Http/Controllers/ContratoController.php app/Http/Controllers/ProyectoController.php app/Services/Contratacion/ContratosService.php routes/contratacion.php
git commit -m "feat: contracts search and detail API"
```

---

### Tarea 3: Pantalla de contratos fuera de Parámetros

La gestión de contratos de `Parametros.jsx` pasa a `ContratoEditor` y `ContratosProyecto`, sin cambiar el comportamiento; los cálculos, a utilidades con pruebas, y el formato de moneda, a `utils/moneda.js`.

**Files:**
- Create: `resources/js/components/contratos/ContratoEditor.jsx`
- Create: `resources/js/components/contratos/ContratosProyecto.jsx`
- Create: `resources/js/utils/moneda.js`
- Test (create): `resources/js/utils/moneda.test.js`
- Modify: `resources/js/components/Parametros.jsx`
- Modify: `resources/js/utils/contratos.js`
- Test: `resources/js/utils/contratos.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `resources/js/utils/moneda.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { formatoMoneda, limpiarMoneda, monedaEnEdicion, numeroDeMoneda } from './moneda';

// Intl separa el símbolo con un espacio duro: se compara con espacios normales.
const normal = (texto) => texto.replace(/\s/g, ' ');

describe('formatoMoneda', () => {
    it('pesos colombianos con dos decimales', () => {
        expect(normal(formatoMoneda(1234567.5))).toBe('$ 1.234.567,50');
        expect(normal(formatoMoneda(0))).toBe('$ 0,00');
    });
});

describe('numeroDeMoneda', () => {
    it('lee lo escrito con miles y coma decimal', () => {
        expect(numeroDeMoneda('$ 1.487.342,33')).toBe(1487342.33);
        expect(numeroDeMoneda('1500000')).toBe(1500000);
        expect(numeroDeMoneda(2500)).toBe(2500);
    });

    it('vacío o sin cifras es cero', () => {
        expect(numeroDeMoneda('')).toBe(0);
        expect(numeroDeMoneda(null)).toBe(0);
        expect(numeroDeMoneda(undefined)).toBe(0);
        expect(numeroDeMoneda('$ ')).toBe(0);
    });
});

describe('limpiarMoneda', () => {
    it('deja cifras y una coma con hasta dos decimales', () => {
        expect(limpiarMoneda('$ 1.487.342')).toBe('1487342');
        expect(limpiarMoneda('1487342,339')).toBe('1487342,33');
        expect(limpiarMoneda('12,3,4')).toBe('12,34');
        expect(limpiarMoneda('abc')).toBe('');
    });
});

describe('monedaEnEdicion', () => {
    it('agrupa los miles y conserva la parte decimal escrita', () => {
        expect(monedaEnEdicion('1487342')).toBe('$ 1.487.342');
        expect(monedaEnEdicion('1487342,')).toBe('$ 1.487.342,');
        expect(monedaEnEdicion('1487342,3')).toBe('$ 1.487.342,3');
        expect(monedaEnEdicion(',5')).toBe('$ 0,5');
    });
});
```

En `resources/js/utils/contratos.test.js`, reemplazar (1 de 2):

```js
import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import {
    CAMPOS_CONTRATO,
    CONTRATO_VACIO,
    erroresGuardarContrato,
```

por:

```js
import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import {
    ACTA_VACIA,
    MODIFICACION_VACIA,
    actividadConAvance,
    anexosDelContrato,
    anexosParaGuardar,
    avanceFinancieroDeActas,
    avanceFisicoDeActividades,
    calculoActa,
    datosActa,
    datosModificacion,
    etiquetaTipoModificacion,
    sumaPesos,
    CAMPOS_CONTRATO,
    CONTRATO_VACIO,
    erroresGuardarContrato,
```

En `resources/js/utils/contratos.test.js`, reemplazar (2 de 2):

```js
        expect(primerCampoConError({})).toBeNull();
    });
});
```

por:

```js
        expect(primerCampoConError({})).toBeNull();
    });
});

describe('pestañas del contrato', () => {
    it('los anexos del listado se leen para la pestaña y se envían sin id', () => {
        const contrato = { anexos: [{ id: 4, descripcion: 'Póliza', nombre_archivo: 'p.pdf', ruta_archivo: 'anexos_contratos/p.pdf', fecha: '2026-01-10' }] };
        const anexos = anexosDelContrato(contrato);
        expect(anexos).toEqual([{ id: 4, descripcion: 'Póliza', nombreArchivo: 'p.pdf', rutaArchivo: 'anexos_contratos/p.pdf', fecha: '2026-01-10' }]);
        expect(anexosParaGuardar(anexos)).toEqual([{ descripcion: 'Póliza', nombreArchivo: 'p.pdf', rutaArchivo: 'anexos_contratos/p.pdf', fecha: '2026-01-10' }]);
        expect(anexosDelContrato({ anexos: [] })).toEqual([]);
        expect(anexosDelContrato(null)).toEqual([]);
    });

    it('el acta amortiza el anticipo y calcula el porcentaje sobre el vigente', () => {
        const formulario = { valor_inicial: '1.000.000', anticipo: true, porcentaje_anticipo: '30' };
        expect(calculoActa(200000, formulario, null)).toEqual({ amortizacion: 60000, valorPresente: 140000, porcentajeEjecutado: 20 });
        // Con una adición de 1.000.000 el vigente es 2.000.000.
        expect(calculoActa(200000, { ...formulario, anticipo: false }, { valor: '2000000.00', valor_inicial: '1000000.00' }))
            .toEqual({ amortizacion: 0, valorPresente: 200000, porcentajeEjecutado: 10 });
        expect(calculoActa(5000, { valor_inicial: '', anticipo: false }, null).porcentajeEjecutado).toBe(0);
    });

    it('lo que se envía de un acta', () => {
        const formulario = {
            ...ACTA_VACIA, descripcion: 'Acta 1', fecha_acta: '2026-02-01', valor_facturado: '$ 1.500,50',
            amortizacion_50: 0, valor_presente_acta: 1500.5, porcentaje_ejecutado: 15,
        };
        expect(datosActa(7, formulario, 'actas/a.pdf')).toEqual({
            contrato_id: 7, descripcion: 'Acta 1', fecha_acta: '2026-02-01', valor_facturado: 1500.5,
            amortizacion_50: 0, valor_presente_acta: 1500.5, porcentaje_ejecutado: 15, anexo: 'actas/a.pdf',
        });
    });

    it('el avance financiero suma lo facturado sobre el vigente; sin actas o sin vigente, vacío', () => {
        expect(avanceFinancieroDeActas([{ valor_facturado: '250000.00' }, { valor_facturado: 83333 }], 1000000)).toBe(33.33);
        expect(avanceFinancieroDeActas([], 1000000)).toBe('');
        expect(avanceFinancieroDeActas([{ valor_facturado: 10 }], 0)).toBe('');
    });

    it('el avance físico pondera el último avance de cada actividad', () => {
        const actividades = [{ peso: 60, ultimo_avance: 50 }, { peso: 40, ultimo_avance: null }, { peso: 0 }];
        expect(sumaPesos(actividades)).toBe(100);
        expect(avanceFisicoDeActividades(actividades)).toBe(30);
        expect(avanceFisicoDeActividades([{ peso: 33, ultimo_avance: 50 }])).toBe(17);
        expect(avanceFisicoDeActividades([])).toBe(0);
    });

    it('un avance solo pasa a ser el último si es igual o más reciente', () => {
        const actividad = { id: 1, ultimo_avance: 40, fecha_ultimo_avance: '2026-03-10' };
        expect(actividadConAvance(actividad, '2026-03-10', '55')).toEqual({ id: 1, ultimo_avance: 55, fecha_ultimo_avance: '2026-03-10' });
        expect(actividadConAvance(actividad, '2026-03-01', '55')).toBe(actividad);
        expect(actividadConAvance({ id: 2, ultimo_avance: null, fecha_ultimo_avance: null }, '2026-01-01', '10').ultimo_avance).toBe(10);
    });

    it('una prórroga no envía valor ni una adición días', () => {
        const base = {
            ...MODIFICACION_VACIA, numero_otrosi: 'Otrosí 1', valor_adicion: '$ 500.000,25', dias_prorroga: '30',
            fecha_modificacion: '2026-04-01', justificacion: 'Lluvias',
        };
        expect(datosModificacion(7, base)).toEqual({
            contrato_id: 7, numero_otrosi: 'Otrosí 1', tipo: 'adicion', valor_adicion: 500000.25,
            dias_prorroga: null, fecha_modificacion: '2026-04-01', justificacion: 'Lluvias',
        });
        expect(datosModificacion(7, { ...base, tipo: 'prorroga' })).toMatchObject({ valor_adicion: null, dias_prorroga: '30' });
        expect(datosModificacion(7, { ...base, tipo: 'adicion_prorroga', valor_adicion: '', dias_prorroga: '' }))
            .toMatchObject({ valor_adicion: null, dias_prorroga: null });
    });

    it('la etiqueta del tipo de modificación', () => {
        expect(etiquetaTipoModificacion('adicion')).toBe('Adición');
        expect(etiquetaTipoModificacion('prorroga')).toBe('Prórroga');
        expect(etiquetaTipoModificacion('adicion_prorroga')).toBe('Adición y Prórroga');
    });
});
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/moneda.test.js resources/js/utils/contratos.test.js`
Expected: FALLAN los 2 archivos (8 pruebas y la carga de `moneda.test.js`): no existen `resources/js/utils/moneda.js` ni las utilidades extraídas de la pantalla de contratos (`calculoActa`, `datosActa`, `anexosDelContrato` y las demás).

- [ ] **Paso 3: Implementar**

Crear `resources/js/components/contratos/ContratoEditor.jsx`:

```jsx
import React, { useEffect, useRef, useState } from 'react';
import { faCalculator, faEye, faFile, faInfo, faPlus, faSave, faTimes, faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
import axios from '../../axios';
import { avisarError } from '../../utils/avisosPlantillas';
import { datosEstado, esEdicionDesactualizada, textoValor, valorEnEdicion } from '../../utils/contratacion';
import {
    ACTA_VACIA, ACTIVIDAD_VACIA, ANEXO_VACIO, AVANCE_VACIO, ESTADOS_CONTRATO, MODIFICACION_VACIA, actividadConAvance, anexosDelContrato,
    anexosParaGuardar, aplicarPrecarga, avanceFinancieroDeActas, avanceFisicoDeActividades, calculoActa, contratoVacio, datosActa,
    datosContrato, datosDelProceso, datosModificacion, erroresGuardarContrato, etiquetaTipoModificacion, formularioContrato, notaProceso,
    opcionesProceso, opcionesTipoContrato, primerCampoConError, procesoElegido, sumaPesos, validarContrato, valorVigente, vigentesDistintos,
} from '../../utils/contratos';
import { formatoMoneda, limpiarMoneda, monedaEnEdicion, numeroDeMoneda } from '../../utils/moneda';
import { htmlErrores, mensajeApi } from '../../utils/plantillasChecklist';
import '../../../css/Parametros.css';
import '../../../css/Contratacion.css';

/** Catálogo de tipos de contrato (GET /tiposProcesos); null si no se pudo traer. */
export const pedirTiposContrato = async () => {
    try {
        const { data } = await axios.get('/tiposProcesos');
        return Array.isArray(data) ? data : [];
    } catch (error) {
        // Sin catálogo, el select ofrece solo el tipo que el contrato ya tiene.
        return null;
    }
};

/** Procesos de contratación del proyecto (GET /proyectos/{id}/procesos); null si fallan; [] sin proyecto. */
export const pedirProcesosProyecto = async (proyectoId) => {
    if (!proyectoId) return [];
    try {
        const { data } = await axios.get(`/proyectos/${proyectoId}/procesos`);
        return data?.procesos ?? [];
    } catch (error) {
        return null;
    }
};

/**
 * Un contrato, nuevo o existente: su formulario y las pestañas de anexos, actas, actividades y
 * modificaciones, con el pie de Cancelar y Guardar. Se pinta dentro de un `.contratos-modal`
 * (pone su `.contratos-modal-body` y su `.modal-actions`) y se monta de nuevo para cada contrato.
 *
 * - `proyecto`: el proyecto del contrato nuevo ({id, nombre}) o null; sus procesos llenan el select.
 * - `contrato`: el que se edita (un elemento de GET /listarContratos o de /contratos/{id}/detalle), o null para uno nuevo.
 * - `procesoInicial`: en un contrato nuevo, el id del proceso ya elegido; se aplica su precarga.
 * - `tiposContrato`: el catálogo si quien lo pinta ya lo trae (null mientras carga o si falló); sin la prop, lo pide el editor.
 * - `procesosIniciales`: los procesos del proyecto que ya se tienen, mientras llegan los recién pedidos.
 * - `puedeEditar`: sin permiso, el contrato se ve sin controles para modificarlo.
 * - `recargar(id)`: vuelve a traer los contratos tras un cambio en las pestañas; resuelve el contrato `id` recargado o null.
 * - `onGuardado(contrato)`: tras guardar (con los avisos ya mostrados); quien lo pinta cierra el editor.
 * - `onCancelar()`: "Cancelar" o "Volver a la lista".
 */
const ContratoEditor = ({
    proyecto = null, contrato = null, procesoInicial = null, tiposContrato, procesosIniciales = null, puedeEditar = true,
    recargar, onGuardado, onCancelar,
}) => {
    const [editingContrato, setEditingContrato] = useState(contrato);
    const [contratoActiveTab, setContratoActiveTab] = useState('informacion');
    // Campos de formulario para contratos (los de la API: utils/contratos.js)
    const [formContrato, setFormContrato] = useState(() => {
        if (contrato) return formularioContrato(contrato);
        const vacio = contratoVacio(proyecto?.id);
        return procesoInicial ? { ...vacio, proceso_id: String(procesoInicial) } : vacio;
    });
    // Errores del formulario por campo, de la validación local o de un 422.
    const [erroresContrato, setErroresContrato] = useState({});
    const [guardandoContrato, setGuardandoContrato] = useState(false);
    // Evita un segundo envío mientras el primero no termina (el estado tarda un render en verse).
    const enviandoContrato = useRef(false);
    // Cada error de validación sube el contador: tras pintar, se enfoca el primer campo con error.
    const [focoErrorContrato, setFocoErrorContrato] = useState(0);
    const formularioContratoRef = useRef(null);
    // Turno de la carga de procesos: una respuesta atrasada (de otra carga) se descarta.
    const turnoProcesosContrato = useRef(0);
    // Catálogo de tipos de contrato y procesos del proyecto; null mientras no cargan.
    const [tiposPropios, setTiposPropios] = useState(null);
    const tipos = tiposContrato === undefined ? tiposPropios : tiposContrato;
    const [procesosContrato, setProcesosContrato] = useState(procesosIniciales);

    const [anexos, setAnexos] = useState(() => anexosDelContrato(contrato));
    const [formAnexo, setFormAnexo] = useState(ANEXO_VACIO);

    const [actasFinancieras, setActasFinancieras] = useState(contrato?.avancesFinancieros || []);
    const [formActaFinanciera, setFormActaFinanciera] = useState(ACTA_VACIA);

    const [actividades, setActividades] = useState(contrato?.actividades || []);
    const [formActividad, setFormActividad] = useState(ACTIVIDAD_VACIA);
    const [actividadRegistrandoAvance, setActividadRegistrandoAvance] = useState(null);
    const [formNuevoAvance, setFormNuevoAvance] = useState(AVANCE_VACIO);

    const [modificaciones, setModificaciones] = useState(contrato?.modificaciones || []);
    const [formModificacion, setFormModificacion] = useState(MODIFICACION_VACIA);
    const [resumenModificacion, setResumenModificacion] = useState(null);

    useEffect(() => {
        // Los estados de los procesos pueden haber cambiado desde que se abrió la lista.
        cargarProcesosContrato(contrato?.proyecto ?? proyecto?.id);
        if (tiposContrato === undefined) pedirTiposContrato().then(setTiposPropios);
        if (!contrato && procesoInicial) precargarDesdeProceso(String(procesoInicial));
    }, []);

    // Manejar cambios en el formulario de contratos
    const handleContratoChange = (e) => {
        const { name, value, type, checked } = e.target;
        setErroresContrato(prev => (prev[name] ? { ...prev, [name]: undefined } : prev));
        if (name === 'proceso_id') {
            setFormContrato(prev => ({ ...prev, proceso_id: value }));
            // R8: en un contrato nuevo, el proceso elegido llena los campos que siguen vacíos.
            if (value && !editingContrato) precargarDesdeProceso(value);
            return;
        }
        if (name === 'anticipo' && !checked) {
            setFormContrato(prev => ({ ...prev, anticipo: false, porcentaje_anticipo: '' }));
            return;
        }

        if (name === 'porcentaje_anticipo') {
            if (value === '') {
                setFormContrato(prev => ({ ...prev, porcentaje_anticipo: '0' }));
                return;
            }
            if (value < 0 || value > 50) {
                Swal.fire({ icon: 'warning', title: 'El porcentaje de anticipo debe ser entre 0 y 50' });
                return;
            }
            setFormContrato(prev => ({ ...prev, porcentaje_anticipo: value }));
            return;
        }

        setFormContrato(prev => ({ ...prev, [name]: type === 'checkbox' ? checked : value }));
    };

    // Valor inicial con los miles agrupados ("1.234.567,89"), como el valor de los procesos.
    const handleContratoValorChange = (e) => {
        const { value } = e.target;
        setErroresContrato(prev => (prev.valor_inicial ? { ...prev, valor_inicial: undefined } : prev));
        setFormContrato(prev => ({ ...prev, valor_inicial: valorEnEdicion(value, prev.valor_inicial) }));
    };

    const precargarDesdeProceso = async (procesoId) => {
        try {
            const { data } = await axios.get(`/procesos/${procesoId}/precarga-contrato`);
            // Si entretanto se eligió otro proceso, esta precarga ya no aplica.
            setFormContrato(prev => (prev.proceso_id === procesoId ? aplicarPrecarga(prev, data?.precarga) : prev));
        } catch (error) {
            avisarError('No se pudieron traer los datos del proceso', error);
        }
    };

    // Devuelve la lista pedida (o null si falló); solo la última carga llega al formulario.
    const cargarProcesosContrato = async (proyectoId) => {
        const numero = ++turnoProcesosContrato.current;
        const lista = await pedirProcesosProyecto(proyectoId);
        if (numero === turnoProcesosContrato.current) setProcesosContrato(lista);
        return lista;
    };

    useEffect(() => {
        if (!focoErrorContrato) return;
        const campo = primerCampoConError(erroresContrato);
        if (campo) formularioContratoRef.current?.querySelector(`[name="${campo}"]`)?.focus();
    }, [focoErrorContrato]);

    // Manejar cambios en el formulario de anexos
    const handleAnexoChange = (e) => {
        const { name, value, files } = e.target;
        if (name === 'archivo') {
            setFormAnexo(prev => ({ ...prev, [name]: files[0] }));
        } else {
            setFormAnexo(prev => ({ ...prev, [name]: value }));
        }
    };

    // Agregar anexo
    const handleAddAnexo = async () => {
        if (!formAnexo.descripcion || !formAnexo.archivo) {
            Swal.fire({ icon: 'warning', title: 'La descripción y el archivo son obligatorios' });
            return;
        }

        try {
            // Crear FormData para enviar el archivo
            const formData = new FormData();
            formData.append('archivo', formAnexo.archivo);
            formData.append('descripcion', formAnexo.descripcion);

            // Subir archivo al servidor
            const response = await axios.post('/subirAnexo', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                }
            });

            if (response.data.success) {
                const nuevoAnexo = {
                    id: Date.now(),
                    descripcion: formAnexo.descripcion,
                    nombreArchivo: formAnexo.archivo.name,
                    rutaArchivo: response.data.ruta,
                    fecha: new Date().toISOString().split('T')[0]
                };

                setAnexos(prev => [...prev, nuevoAnexo]);
                setFormAnexo(ANEXO_VACIO);

                Swal.fire({
                    icon: 'success',
                    title: 'Anexo agregado correctamente',
                    text: response.data.mensaje
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al subir el archivo',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al subir anexo:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error al subir el archivo',
                text: error.response?.data?.mensaje || 'Error interno del servidor'
            });
        }
    };

    // Eliminar anexo
    const handleDeleteAnexo = async (id) => {
        try {
            const response = await axios.post('/eliminarAnexo', { id: id });

            if (response.data.success) {
                setAnexos(prev => prev.filter(anexo => anexo.id !== id));
                Swal.fire({
                    icon: 'success',
                    title: 'Anexo eliminado correctamente',
                    text: response.data.mensaje
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al eliminar el anexo',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al eliminar anexo:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error al eliminar el anexo',
                text: error.response?.data?.mensaje || 'Error interno del servidor'
            });
        }
    };

    const handleChangeValorFacturado = (e) => {
        const limpio = limpiarMoneda(e.target.value);
        const valorFacturado = numeroDeMoneda(limpio);
        const { amortizacion, valorPresente, porcentajeEjecutado } = calculoActa(valorFacturado, formContrato, editingContrato);
        setFormActaFinanciera(prev => ({
            ...prev,
            valor_facturado: limpio ? monedaEnEdicion(limpio) : '',
            amortizacion_50: limpio ? amortizacion : '',
            valor_presente_acta: limpio ? valorPresente : '',
            porcentaje_ejecutado: limpio ? porcentajeEjecutado : ''
        }));
    };

    // Si el usuario cambia el anticipo/porcentaje/valor del contrato después de haber escrito
    // el valor facturado del acta, recalcula amortización/valor presente/% ejecutado.
    useEffect(() => {
        setFormActaFinanciera(prev => {
            if (prev.valor_facturado === '') return prev;
            const valorFacturado = numeroDeMoneda(prev.valor_facturado);
            const { amortizacion, valorPresente, porcentajeEjecutado } = calculoActa(valorFacturado, formContrato, editingContrato);
            return { ...prev, amortizacion_50: amortizacion, valor_presente_acta: valorPresente, porcentaje_ejecutado: porcentajeEjecutado };
        });
    }, [formContrato.anticipo, formContrato.porcentaje_anticipo, formContrato.valor_inicial, editingContrato]);

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

            const response = await axios.post('/guardarActaFinanciera', datosActa(editingContrato.id, formActaFinanciera, rutaAnexo));

            if (response.data.success) {
                setActasFinancieras(prev => [
                    {
                        ...formActaFinanciera,
                        id: response.data.id,
                        anexo: rutaAnexo,
                        valor_facturado: numeroDeMoneda(formActaFinanciera.valor_facturado),
                    },
                    ...prev
                ]);
                setFormActaFinanciera(ACTA_VACIA);
                recargar(editingContrato.id);
                Swal.fire({ icon: 'success', title: 'Acta agregada correctamente' });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al guardar el acta',
                    text: response.data.mensaje
                });
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
                recargar(editingContrato.id);
                Swal.fire({ icon: 'success', title: 'Acta eliminada correctamente' });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al eliminar el acta',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al eliminar acta financiera:', error);
            Swal.fire({ icon: 'error', title: 'Error al eliminar el acta', text: error.response?.data?.mensaje || 'Error interno del servidor' });
        }
    };

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
                setFormActividad(ACTIVIDAD_VACIA);
                recargar(editingContrato.id);
                Swal.fire({ icon: 'success', title: 'Actividad agregada correctamente' });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al agregar la actividad',
                    text: response.data.mensaje
                });
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
                recargar(editingContrato.id);
                Swal.fire({ icon: 'success', title: 'Actividad eliminada correctamente' });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al eliminar la actividad',
                    text: response.data.mensaje
                });
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
                setActividades(prev => prev.map(act => (
                    act.id === actividadId ? actividadConAvance(act, formNuevoAvance.fecha, formNuevoAvance.porcentaje_ejecucion) : act
                )));
                setActividadRegistrandoAvance(null);
                setFormNuevoAvance(AVANCE_VACIO);
                recargar(editingContrato.id);
                Swal.fire({ icon: 'success', title: 'Avance registrado correctamente' });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al registrar el avance',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al registrar avance:', error);
            Swal.fire({ icon: 'error', title: 'Error al registrar el avance', text: error.response?.data?.mensaje || 'Error interno del servidor' });
        }
    };

    // Calcular avance financiero automáticamente: suma de valor_facturado de todas las actas / valor vigente del contrato
    useEffect(() => {
        const porcentaje = avanceFinancieroDeActas(actasFinancieras, valorVigente(formContrato, editingContrato));
        setFormContrato(prev => (prev.avance_financiero === porcentaje ? prev : { ...prev, avance_financiero: porcentaje }));
    }, [actasFinancieras, formContrato.valor_inicial, editingContrato]);

    // Calcular avance físico: suma ponderada de (peso x último % de ejecución) de cada actividad
    const handleCalcularAvanceFisico = () => {
        if (actividades.length === 0) {
            Swal.fire({ icon: 'warning', title: 'No hay actividades de avance físico registradas' });
            return;
        }
        setFormContrato(prev => ({ ...prev, avance_fisico: avanceFisicoDeActividades(actividades) }));
    };

    // Primer contrato de un proceso adjudicado: se propone pasar el proceso a celebrado.
    const proponerProcesoCelebrado = async (guardado) => {
        const { isConfirmed } = await Swal.fire({
            icon: 'question',
            titleText: '¿Pasar el proceso a celebrado?',
            text: 'Es el primer contrato de este proceso adjudicado. El cambio queda en el historial del proceso.',
            showCancelButton: true,
            confirmButtonText: 'Sí, pasar a celebrado',
            cancelButtonText: 'Ahora no',
        });
        if (!isConfirmed) return;
        // Recién cargado, el proceso trae su `actualizado` vigente.
        const lista = await cargarProcesosContrato(guardado.proyecto);
        const proceso = (lista ?? []).find(p => p.id === guardado.proceso_id);
        if (!proceso) {
            Swal.fire({ icon: 'error', titleText: 'No se pudo cambiar el estado', text: 'No se pudo cargar el proceso: cambia su estado desde Procesos.' });
            return;
        }
        try {
            await axios.post(`/procesos/${proceso.id}/estado`, datosEstado(proceso, 'celebrado', null));
            await cargarProcesosContrato(guardado.proyecto);
            await Swal.fire({ icon: 'success', title: 'El proceso quedó celebrado' });
        } catch (error) {
            if (esEdicionDesactualizada(error)) {
                await Swal.fire({ icon: 'warning', titleText: 'No se cambió el estado del proceso', text: mensajeApi(error) });
                return;
            }
            avisarError('No se pudo cambiar el estado del proceso', error);
        }
    };

    // Guardar contrato (agregar o actualizar), una vez a la vez.
    const handleSaveContrato = async () => {
        if (enviandoContrato.current) return;
        enviandoContrato.current = true;
        try {
            await enviarContrato();
        } finally {
            enviandoContrato.current = false;
        }
    };

    const enviarContrato = async () => {
        const locales = validarContrato(formContrato);
        setErroresContrato(locales);
        if (Object.keys(locales).length) {
            setContratoActiveTab('informacion');
            setFocoErrorContrato(n => n + 1);
            return;
        }

        setGuardandoContrato(true);
        let respuesta;
        try {
            respuesta = await axios.post('/guardarContrato', {
                formContrato: datosContrato(formContrato, editingContrato),
                anexos: anexosParaGuardar(anexos),
            });
        } catch (error) {
            if (esEdicionDesactualizada(error)) {
                // Otra persona cambió el contrato: se avisa y se recarga la lista. El formulario
                // sigue abierto para copiar lo escrito; al reabrirlo trae lo último.
                await Promise.all([
                    recargar(editingContrato?.id),
                    Swal.fire({ icon: 'warning', titleText: 'No se guardó tu cambio', text: mensajeApi(error) }),
                ]);
                return;
            }
            const errores = erroresGuardarContrato(error);
            if (!errores) {
                avisarError('No se pudo guardar el contrato', error);
                return;
            }
            // Los de campos con control, junto a su control; los demás (el proyecto, los anexos), en un aviso.
            if (Object.keys(errores.campos).length) {
                setErroresContrato(errores.campos);
                setContratoActiveTab('informacion');
                setFocoErrorContrato(n => n + 1);
            }
            if (errores.otros.length) {
                Swal.fire({ icon: 'error', titleText: 'No se pudo guardar el contrato', html: htmlErrores('Revisa lo siguiente:', errores.otros) });
            } else if (!Object.keys(errores.campos).length) {
                avisarError('No se pudo guardar el contrato', error);
            }
            return;
        } finally {
            setGuardandoContrato(false);
        }

        const { contrato: guardado, avisos = [], proponer_celebrado: proponerCelebrado } = respuesta.data ?? {};
        if (avisos.length) {
            await Swal.fire({ icon: 'warning', titleText: 'Contrato guardado, con avisos', html: htmlErrores('Revisa lo siguiente:', avisos) });
        } else {
            await Swal.fire({ icon: 'success', title: 'Contrato guardado correctamente' });
        }
        if (proponerCelebrado && guardado?.proceso_id) await proponerProcesoCelebrado(guardado);

        onGuardado(guardado);
    };

    // Refrescar los datos del contrato abierto tras registrar o eliminar una modificación
    const refrescarContratoAbierto = async () => {
        const actual = await recargar(formContrato.id);
        if (formContrato.id && actual) {
            setModificaciones(actual.modificaciones || []);
            // Los vigentes recalculados salen del contrato recargado; lo escrito en el
            // formulario se conserva. Registrar una modificación no cambia la versión
            // (updated_at), y se guarda la que tenía al abrirse: así un cambio de otra
            // persona entretanto sigue dando 409.
            setEditingContrato(prev => ({ ...actual, updated_at: prev?.updated_at ?? actual.updated_at }));
        }
    };

    // Manejar cambios en el formulario de modificación (otrosí)
    const handleModificacionChange = (e) => {
        const { name, value } = e.target;
        if (name === 'valor_adicion') {
            // Preservar la coma decimal (centavos), igual que los demás inputs de moneda.
            const limpio = limpiarMoneda(value);
            setFormModificacion(prev => ({ ...prev, valor_adicion: limpio ? monedaEnEdicion(limpio) : '' }));
            return;
        }
        setFormModificacion(prev => ({ ...prev, [name]: value }));
    };

    // Guardar una modificación (otrosí) y recalcular el contrato
    const guardarModificacion = async () => {
        if (!formContrato.id) {
            Swal.fire({ icon: 'warning', title: 'Guarda primero el contrato antes de registrar modificaciones.' });
            return;
        }
        if (!formModificacion.fecha_modificacion) {
            Swal.fire({ icon: 'warning', title: 'La fecha de la modificación es obligatoria.' });
            return;
        }
        try {
            const resp = await axios.post('/guardarModificacionContrato', datosModificacion(formContrato.id, formModificacion));
            setResumenModificacion(resp.data.resumen);
            if (resp.data.resumen?.supera_limite) {
                Swal.fire({ icon: 'warning', title: 'Adición supera el 50% legal', text: 'Se registró de todas formas.' });
            }
            await refrescarContratoAbierto();
            setFormModificacion(MODIFICACION_VACIA);
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error al guardar la modificación', text: e.response?.data?.error || e.message });
        }
    };

    // Eliminar una modificación (otrosí) y recalcular el contrato
    const eliminarModificacion = async (id) => {
        const result = await Swal.fire({
            icon: 'warning', title: '¿Eliminar esta modificación?',
            text: 'Se recalculará el valor y la fecha fin del contrato.',
            showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
        });
        if (!result.isConfirmed) return;
        try {
            const resp = await axios.post('/eliminarModificacionContrato', { id });
            setResumenModificacion(resp.data.resumen);
            await refrescarContratoAbierto();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error al eliminar la modificación', text: e.response?.data?.error || e.message });
        }
    };

    // Formulario de contrato: proceso elegido, nota R4 y vigentes que difieren de los iniciales.
    const procesoDelContrato = procesoElegido(procesosContrato, editingContrato?.proceso ?? null, formContrato.proceso_id);
    const datosProcesoContrato = procesoDelContrato ? datosDelProceso(procesoDelContrato) : null;
    const notaProcesoContrato = notaProceso(procesosContrato, formContrato.proceso_id);
    const vigentesContrato = vigentesDistintos(editingContrato);

    // Propiedades comunes de un control del formulario de contrato: valor, cambio y aviso de error.
    // Sin permiso para editar, el control queda deshabilitado.
    const controlContrato = (campo) => ({
        id: campo,
        name: campo,
        value: formContrato[campo] ?? '',
        onChange: handleContratoChange,
        'aria-invalid': Boolean(erroresContrato[campo]),
        'aria-describedby': erroresContrato[campo] ? `${campo}-error` : undefined,
        ...(puedeEditar ? {} : { disabled: true }),
    });
    const campoContrato = (campo, etiqueta, control) => (
        <div className="form-group">
            <label htmlFor={campo}>{etiqueta}</label>
            {control}
            {erroresContrato[campo] && <small id={`${campo}-error`} className="ct-error-campo" role="alert">{erroresContrato[campo]}</small>}
        </div>
    );

    return (
        <>
            <div className="contratos-modal-body">
                <div className="contrato-form-header">
                    <h3>{editingContrato ? 'Editar Contrato' : 'Agregar Nuevo Contrato'}</h3>
                    <button
                        onClick={onCancelar}
                        className="btn-back-to-list"
                    >
                        ← Volver a la lista
                    </button>
                </div>

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
                    {editingContrato && (
                        <button
                            className={`contrato-tab ${contratoActiveTab === 'modificaciones' ? 'active' : ''}`}
                            onClick={() => setContratoActiveTab('modificaciones')}
                        >
                            <FontAwesomeIcon icon={faFile} /> Modificaciones ({modificaciones.length})
                        </button>
                    )}
                </div>

                {/* Contenido de la pestaña de información */}
                {contratoActiveTab === 'informacion' && (
                    <div className="contratos-modal-section">
                        <p className="contratos-info">
                            Completa los datos del contrato. Los campos marcados con * son obligatorios.
                        </p>
                        <div className="contrato-form" ref={formularioContratoRef}>
                            <div className="form-row">
                                {campoContrato('proceso_id', 'Proceso de contratación', (
                                    <select {...controlContrato('proceso_id')}>
                                        {opcionesProceso(procesosContrato, editingContrato?.proceso ?? null).map(o => (
                                            <option key={o.valor} value={o.valor}>{o.etiqueta}</option>
                                        ))}
                                    </select>
                                ))}
                                {campoContrato('tipo_contrato_id', 'Tipo de contrato', (
                                    <select {...controlContrato('tipo_contrato_id')}>
                                        <option value="">Sin tipo</option>
                                        {opcionesTipoContrato(tipos, editingContrato).map(o => (
                                            <option key={o.valor} value={o.valor}>{o.etiqueta}</option>
                                        ))}
                                    </select>
                                ))}
                            </div>
                            {notaProcesoContrato && <p className="contrato-nota">{notaProcesoContrato}</p>}
                            {datosProcesoContrato && (
                                <dl className="contrato-proceso-datos" aria-label="Datos del proceso">
                                    <div><dt>Número del proceso</dt><dd>{datosProcesoContrato.numero}</dd></div>
                                    <div><dt>Modalidad</dt><dd>{datosProcesoContrato.modalidad}</dd></div>
                                    <div><dt>Plataforma</dt><dd>{datosProcesoContrato.plataforma}</dd></div>
                                    <div>
                                        <dt>Enlace</dt>
                                        <dd>
                                            {datosProcesoContrato.enlace
                                                ? <a className="ct-enlace" href={datosProcesoContrato.enlace} target="_blank" rel="noopener noreferrer">Ver el proceso</a>
                                                : '—'}
                                        </dd>
                                    </div>
                                </dl>
                            )}
                            <div className="form-row">
                                {campoContrato('n_contrato', 'N° Contrato *', <input type="text" maxLength={100} {...controlContrato('n_contrato')} />)}
                                {campoContrato('estado', 'Estado *', (
                                    <select {...controlContrato('estado')}>
                                        <option value="">Elige el estado</option>
                                        {ESTADOS_CONTRATO.map(([valor, etiqueta]) => (
                                            <option key={valor} value={valor}>{etiqueta}</option>
                                        ))}
                                    </select>
                                ))}
                            </div>
                            {campoContrato('objeto', 'Objeto *', (
                                <textarea rows="4" maxLength={5000} placeholder="Escribe el objeto del contrato" {...controlContrato('objeto')} />
                            ))}
                            <div className="form-row">
                                {campoContrato('contratante', 'Contratante', <input type="text" maxLength={5000} {...controlContrato('contratante')} />)}
                                {campoContrato('contratante_nit', 'NIT de la entidad contratante', <input type="text" maxLength={30} {...controlContrato('contratante_nit')} />)}
                            </div>
                            <div className="form-row">
                                {campoContrato('contratista', 'Contratista', <input type="text" maxLength={5000} {...controlContrato('contratista')} />)}
                                {campoContrato('contratista_identificacion', 'Identificación del contratista', <input type="text" maxLength={30} {...controlContrato('contratista_identificacion')} />)}
                            </div>
                            <div className="form-row">
                                {campoContrato('valor_inicial', 'Valor inicial *', (
                                    <input type="text" inputMode="decimal" placeholder="0" {...controlContrato('valor_inicial')} onChange={handleContratoValorChange} />
                                ))}
                                {vigentesContrato.valor && (
                                    <div className="form-group">
                                        <label htmlFor="valor_vigente">Valor vigente</label>
                                        <input type="text" id="valor_vigente" disabled value={textoValor(valorVigente(formContrato, editingContrato))} aria-describedby="vigentes-nota" />
                                    </div>
                                )}
                            </div>
                            <div className="form-row">
                                {campoContrato('fecha_suscripcion', 'Fecha de suscripción', <input type="date" {...controlContrato('fecha_suscripcion')} />)}
                                {campoContrato('fecha_acta_inicio', 'Fecha del acta de inicio', <input type="date" {...controlContrato('fecha_acta_inicio')} />)}
                            </div>
                            <div className="form-row">
                                {campoContrato('fecha_inicio', 'Fecha de inicio', <input type="date" {...controlContrato('fecha_inicio')} />)}
                                {campoContrato('fecha_fin_inicial', 'Fecha fin inicial', <input type="date" {...controlContrato('fecha_fin_inicial')} />)}
                                {vigentesContrato.fechaFin && (
                                    <div className="form-group">
                                        <label htmlFor="fecha_fin_vigente">Fecha fin vigente</label>
                                        <input type="date" id="fecha_fin_vigente" disabled value={String(editingContrato.fecha_fin).slice(0, 10)} aria-describedby="vigentes-nota" />
                                    </div>
                                )}
                            </div>
                            {(vigentesContrato.valor || vigentesContrato.fechaFin) && (
                                <p id="vigentes-nota" className="contrato-nota">
                                    Los vigentes suman al valor inicial y a la fecha fin inicial las adiciones y prórrogas de la pestaña Modificaciones; se recalculan al guardar.
                                </p>
                            )}
                            <div className="form-row">
                                {campoContrato('numero_rp', 'Número del RP', <input type="text" maxLength={40} {...controlContrato('numero_rp')} />)}
                                {campoContrato('fecha_rp', 'Fecha del RP', <input type="date" {...controlContrato('fecha_rp')} />)}
                            </div>
                            <div className="form-row">
                                {campoContrato('supervisor', 'Supervisor', <input type="text" maxLength={255} {...controlContrato('supervisor')} />)}
                                {campoContrato('interventoria', 'Interventoría', <input type="text" maxLength={5000} {...controlContrato('interventoria')} />)}
                            </div>
                            <div className="form-row">
                                {campoContrato('avance_financiero', 'Avance Financiero', <input type="text" disabled {...controlContrato('avance_financiero')} />)}
                                {campoContrato('avance_fisico', 'Avance Físico', (
                                    <div className="avance-fisico-container">
                                        <input type="text" disabled {...controlContrato('avance_fisico')} />
                                        {puedeEditar && <button type="button" className="btn-calcular-avance" title='Calcular Avance Físico' onClick={handleCalcularAvanceFisico}><FontAwesomeIcon icon={faCalculator} /></button>}
                                    </div>
                                ))}
                            </div>
                            <div className="form-row">
                                <div className="form-group anticipo-group">

                                    <label htmlFor="anticipo" className="checkbox-label">
                                        <input
                                            type="checkbox"
                                            id="anticipo"
                                            name="anticipo"
                                            checked={formContrato.anticipo}
                                            onChange={handleContratoChange}
                                            disabled={!puedeEditar}
                                        />
                                        <span>¿Lleva anticipo?</span>
                                    </label>

                                    <div className="anticipo-porcentaje">
                                        <label htmlFor="porcentaje_anticipo">% de Anticipo</label>
                                        <input
                                            type="number"
                                            id="porcentaje_anticipo"
                                            name="porcentaje_anticipo"
                                            value={formContrato.porcentaje_anticipo}
                                            onChange={handleContratoChange}
                                            min="0"
                                            max="50"
                                            disabled={!formContrato.anticipo || !puedeEditar}
                                            aria-invalid={Boolean(erroresContrato.porcentaje_anticipo)}
                                            aria-describedby={erroresContrato.porcentaje_anticipo ? 'porcentaje_anticipo-error' : undefined}
                                        />
                                        {erroresContrato.porcentaje_anticipo && <small id="porcentaje_anticipo-error" className="ct-error-campo" role="alert">{erroresContrato.porcentaje_anticipo}</small>}
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Contenido de la pestaña de anexos */}
                {contratoActiveTab === 'anexos' && (
                    <div className="contratos-modal-section">
                        <p className="contratos-info">
                            Gestione los anexos del contrato. Puede agregar y eliminar documentos relacionados.
                        </p>

                        {/* Formulario para agregar anexo */}
                        {puedeEditar && (
                            <div className="anexo-form">
                                <h4>Agregar Nuevo Anexo</h4>
                                <div className="form-row">
                                    <div className="form-group">
                                        <label htmlFor="descripcion_anexo">Descripción *</label>
                                        <input
                                            type="text"
                                            id="descripcion_anexo"
                                            name="descripcion"
                                            value={formAnexo.descripcion}
                                            onChange={handleAnexoChange}
                                            placeholder="Descripción del anexo"
                                        />
                                    </div>
                                    <div className="form-group">
                                        <label htmlFor="archivo_anexo">Archivo *</label>
                                        <input
                                            type="file"
                                            id="archivo_anexo"
                                            name="archivo"
                                            onChange={handleAnexoChange}
                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                        />
                                    </div>
                                    <div className="form-group">
                                        <button
                                            type="button"
                                            onClick={handleAddAnexo}
                                            className="btn-add-anexo"
                                            disabled={!formAnexo.descripcion || !formAnexo.archivo}
                                        >
                                            <FontAwesomeIcon icon={faPlus} /> Agregar Anexo
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Lista de anexos */}
                        <div className="anexos-list">
                            <h4>Anexos del Contrato ({anexos.length})</h4>
                            {anexos.length === 0 ? (
                                <div className="no-anexos">
                                    <p>No hay anexos agregados para este contrato</p>
                                </div>
                            ) : (
                                <div className="anexos-table">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Descripción</th>
                                                <th>Archivo</th>
                                                <th>Fecha</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {anexos.map(anexo => (
                                                <tr key={anexo.id}>
                                                    <td>{anexo.descripcion}</td>
                                                    <td>{anexo.nombreArchivo}</td>
                                                    <td>{anexo.fecha}</td>
                                                    <td>
                                                        <div className="anexo-actions">
                                                            <button
                                                                type="button"
                                                                onClick={() => window.open('/' + anexo.rutaArchivo, '_blank')}
                                                                className="btn-view-anexo"
                                                                title="Ver anexo"
                                                            >
                                                                <FontAwesomeIcon icon={faEye} />
                                                            </button>
                                                            {puedeEditar && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => handleDeleteAnexo(anexo.id)}
                                                                    className="btn-delete-anexo"
                                                                    title="Eliminar anexo"
                                                                >
                                                                    <FontAwesomeIcon icon={faTrash} />
                                                                </button>
                                                            )}
                                                        </div>
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

                {/* Contenido de la pestaña de avance financiero */}
                {contratoActiveTab === 'avance_financiero' && (
                    <div className="contratos-modal-section">
                        <p className="contratos-info">
                            Registre las actas de avance financiero del contrato. El avance financiero se calcula sumando el valor presente de todas las actas.
                        </p>

                        {puedeEditar && (
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
                                        <input
                                            type="text"
                                            id="valor_facturado"
                                            name="valor_facturado"
                                            value={formActaFinanciera.valor_facturado}
                                            onChange={handleChangeValorFacturado}
                                            inputMode="decimal"
                                            placeholder="$ 0"
                                        />
                                    </div>
                                    <div className="form-group">
                                        <label htmlFor="amortizacion_50">
                                            Amortización ({formContrato.anticipo ? `${formContrato.porcentaje_anticipo || 0}%` : '0%'})
                                        </label>
                                        <input type="text"
                                            id="amortizacion_50"
                                            name="amortizacion_50"
                                            value={formActaFinanciera.amortizacion_50 !== '' ? formatoMoneda(formActaFinanciera.amortizacion_50) : ''}
                                            disabled
                                            placeholder="$ 0"
                                        />
                                    </div>
                                </div>
                                <div className="form-row">
                                    <div className="form-group">
                                        <label htmlFor="valor_presente_acta">Valor Presente del Acta</label>
                                        <input type="text"
                                            id="valor_presente_acta"
                                            name="valor_presente_acta"
                                            value={formActaFinanciera.valor_presente_acta !== '' ? formatoMoneda(formActaFinanciera.valor_presente_acta) : ''}
                                            disabled
                                            placeholder="$ 0"
                                        />
                                    </div>
                                    <div className="form-group">
                                        <label htmlFor="porcentaje_ejecutado">% Ejecutado</label>
                                        <input type="text" id="porcentaje_ejecutado" name="porcentaje_ejecutado" value={formActaFinanciera.porcentaje_ejecutado !== '' ? `${formActaFinanciera.porcentaje_ejecutado}%` : ''} disabled placeholder="0%" />
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
                        )}

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
                                                    <td>{formatoMoneda(Number(acta.valor_facturado || 0))}</td>
                                                    <td>{formatoMoneda(Number(acta.valor_presente_acta || 0))}</td>
                                                    <td>{acta.porcentaje_ejecutado ? `${acta.porcentaje_ejecutado}%` : '-'}</td>
                                                    <td>
                                                        {acta.anexo ? (
                                                            <button type="button" onClick={() => window.open('/' + acta.anexo, '_blank')} className="btn-view-anexo" title="Ver archivo">
                                                                <FontAwesomeIcon icon={faEye} />
                                                            </button>
                                                        ) : '-'}
                                                    </td>
                                                    <td>
                                                        {puedeEditar && (
                                                            <button type="button" onClick={() => handleDeleteActaFinanciera(acta.id)} className="btn-delete-anexo" title="Eliminar acta">
                                                                <FontAwesomeIcon icon={faTrash} />
                                                            </button>
                                                        )}
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

                        {puedeEditar && (
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
                        )}

                        <div className="anexos-list">
                            <h4>Actividades del Contrato ({actividades.length})</h4>
                            {(() => {
                                const pesos = sumaPesos(actividades);
                                return actividades.length === 0 ? (
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
                                                                        {puedeEditar && (
                                                                            <>
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
                                                                            </>
                                                                        )}
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
                                            <span className={`avance-fisico-suma-pesos ${pesos !== 100 ? 'advertencia' : ''}`}>
                                                Suma de pesos: {pesos}%
                                            </span>
                                            <span className="avance-fisico-total">
                                                Avance físico total: {avanceFisicoDeActividades(actividades)}%
                                            </span>
                                        </div>
                                    </>
                                );
                            })()}
                        </div>
                    </div>
                )}

                {/* Contenido de la pestaña de modificaciones (otrosíes) */}
                {contratoActiveTab === 'modificaciones' && (
                    <div className="contratos-modal-section">
                        <p className="contratos-info">
                            Registre las adiciones y prórrogas (otrosíes) del contrato. El valor y la fecha fin vigentes se recalculan automáticamente desde la base inicial más el historial de modificaciones.
                        </p>

                        {resumenModificacion && (
                            <div className="anexo-form">
                                <h4>Resumen recalculado</h4>
                                <div className="form-row">
                                    <div className="form-group">
                                        <label>Valor inicial</label>
                                        <span>{formatoMoneda(Number(resumenModificacion.valor_inicial || 0))}</span>
                                    </div>
                                    <div className="form-group">
                                        <label>Valor vigente</label>
                                        <span>{formatoMoneda(Number(resumenModificacion.valor_vigente || 0))}</span>
                                    </div>
                                    <div className="form-group">
                                        <label>Fecha fin inicial</label>
                                        <span>{resumenModificacion.fecha_fin_inicial || '-'}</span>
                                    </div>
                                    <div className="form-group">
                                        <label>Fecha fin vigente</label>
                                        <span>{resumenModificacion.fecha_fin_vigente || '-'}</span>
                                    </div>
                                    <div className="form-group">
                                        <label>% Adicionado</label>
                                        <span className={resumenModificacion.supera_limite ? 'texto-alerta' : ''}>
                                            {resumenModificacion.porcentaje_adicionado}%
                                        </span>
                                    </div>
                                </div>
                                {resumenModificacion.supera_limite && (
                                    <p className="texto-alerta">
                                        La suma de adiciones supera el 50% legal del valor inicial.
                                    </p>
                                )}
                            </div>
                        )}

                        {puedeEditar && (
                            <div className="anexo-form">
                                <h4>Registrar Nueva Modificación</h4>
                                <div className="form-row">
                                    <div className="form-group">
                                        <label htmlFor="numero_otrosi">N° Otrosí</label>
                                        <input type="text" id="numero_otrosi" name="numero_otrosi" value={formModificacion.numero_otrosi} onChange={handleModificacionChange} placeholder="Ej: Otrosí No. 1" />
                                    </div>
                                    <div className="form-group">
                                        <label htmlFor="tipo_modificacion">Tipo *</label>
                                        <select id="tipo_modificacion" name="tipo" value={formModificacion.tipo} onChange={handleModificacionChange}>
                                            <option value="adicion">Adición</option>
                                            <option value="prorroga">Prórroga</option>
                                            <option value="adicion_prorroga">Adición y Prórroga</option>
                                        </select>
                                    </div>
                                    <div className="form-group">
                                        <label htmlFor="fecha_modificacion">Fecha *</label>
                                        <input type="date" id="fecha_modificacion" name="fecha_modificacion" value={formModificacion.fecha_modificacion} onChange={handleModificacionChange} />
                                    </div>
                                </div>
                                <div className="form-row">
                                    {formModificacion.tipo !== 'prorroga' && (
                                        <div className="form-group">
                                            <label htmlFor="valor_adicion">Valor de la adición</label>
                                            <input type="text" id="valor_adicion" name="valor_adicion" value={formModificacion.valor_adicion} onChange={handleModificacionChange} placeholder="$ 0,00" />
                                        </div>
                                    )}
                                    {formModificacion.tipo !== 'adicion' && (
                                        <div className="form-group">
                                            <label htmlFor="dias_prorroga">Días de prórroga</label>
                                            <input type="number" id="dias_prorroga" name="dias_prorroga" value={formModificacion.dias_prorroga} onChange={handleModificacionChange} min="0" />
                                        </div>
                                    )}
                                    <div className="form-group">
                                        <label htmlFor="justificacion_modificacion">Justificación</label>
                                        <input type="text" id="justificacion_modificacion" name="justificacion" value={formModificacion.justificacion} onChange={handleModificacionChange} placeholder="Motivo de la modificación" />
                                    </div>
                                </div>
                                <div className="form-row">
                                    <div className="form-group">
                                        <button
                                            type="button"
                                            onClick={guardarModificacion}
                                            className="btn-add-anexo"
                                            disabled={!formContrato.id || !formModificacion.fecha_modificacion}
                                        >
                                            <FontAwesomeIcon icon={faSave} /> Registrar Modificación
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="anexos-list">
                            <h4>Historial de Modificaciones ({modificaciones.length})</h4>
                            {modificaciones.length === 0 ? (
                                <div className="no-anexos">
                                    <p>No hay modificaciones registradas</p>
                                </div>
                            ) : (
                                <div className="anexos-table">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>N° Otrosí</th>
                                                <th>Tipo</th>
                                                <th>Valor adición</th>
                                                <th>Días prórroga</th>
                                                <th>Fecha</th>
                                                <th>Justificación</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {modificaciones.map(mod => (
                                                <tr key={mod.id}>
                                                    <td>{mod.numero_otrosi || '-'}</td>
                                                    <td>{etiquetaTipoModificacion(mod.tipo)}</td>
                                                    <td>{mod.valor_adicion ? formatoMoneda(Number(mod.valor_adicion)) : '-'}</td>
                                                    <td>{mod.dias_prorroga || '-'}</td>
                                                    <td>{mod.fecha_modificacion || '-'}</td>
                                                    <td>{mod.justificacion || '-'}</td>
                                                    <td>
                                                        <div className="anexo-actions">
                                                            {puedeEditar && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => eliminarModificacion(mod.id)}
                                                                    className="btn-delete-anexo"
                                                                    title="Eliminar modificación"
                                                                >
                                                                    <FontAwesomeIcon icon={faTrash} />
                                                                </button>
                                                            )}
                                                        </div>
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
            </div>

            <div className="modal-actions">
                <button type="button" onClick={onCancelar} className="btn-cancel">
                    <FontAwesomeIcon icon={faTimes} /> Cancelar
                </button>
                {puedeEditar && (
                    <button
                        type="button"
                        onClick={handleSaveContrato}
                        className="btn-save"
                        disabled={guardandoContrato}
                    >
                        <FontAwesomeIcon icon={faSave} /> {guardandoContrato ? 'Guardando…' : `${editingContrato ? 'Actualizar' : 'Guardar'} Contrato`}
                    </button>
                )}
            </div>
        </>
    );
};

export default ContratoEditor;
```

Crear `resources/js/components/contratos/ContratosProyecto.jsx`:

```jsx
import React, { useEffect, useState } from 'react';
import { faEdit, faFile, faPlus, faTimes, faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
import axios from '../../axios';
import { avisarError } from '../../utils/avisosPlantillas';
import { etiquetaEstadoContrato } from '../../utils/contratos';
import { formatoMoneda } from '../../utils/moneda';
import ContratoEditor, { pedirProcesosProyecto, pedirTiposContrato } from './ContratoEditor';
import '../../../css/Parametros.css';

/**
 * El diálogo "Gestión de Contratos" de un proyecto (Parámetros → Proyectos → Contrato): la lista
 * de sus contratos y, al agregar o editar uno, el editor. Se monta al abrirse.
 *
 * - `proyecto`: la fila del proyecto (GET /proyectos), con sus `contratos` para empezar.
 * - `onCerrar()`: al cerrar el diálogo.
 * - `onCambio()`: tras guardar o eliminar un contrato (Parámetros recarga sus proyectos).
 * - `puedeEditar`: sin permiso, la lista y los contratos se ven sin controles para modificarlos.
 */
const ContratosProyecto = ({ proyecto, onCerrar, onCambio, puedeEditar = true }) => {
    // Contratos del proyecto: los de su fila hasta la primera recarga.
    const [contratos, setContratos] = useState(() => (proyecto.contratos?.length > 0 ? proyecto.contratos : []));
    // El editor abierto: null en la lista; { contrato } al editar, { contrato: null } al agregar.
    const [editor, setEditor] = useState(null);
    // Catálogo de tipos y procesos del proyecto, pedidos al abrir; null mientras no cargan.
    const [tiposContrato, setTiposContrato] = useState(null);
    const [procesos, setProcesos] = useState(null);

    useEffect(() => {
        pedirTiposContrato().then(setTiposContrato);
        pedirProcesosProyecto(proyecto.id).then(setProcesos);
    }, []);

    const listContratos = async () => {
        const response = await axios.get('/listarContratos', {
            params: {
                proyecto: proyecto.id
            }
        });
        setContratos(response.data);
        return response.data;
    };

    // Recarga la lista y devuelve el contrato `id` recargado (o null): lo usa el editor.
    const recargar = async (id) => {
        const lista = await listContratos();
        return (Array.isArray(lista) && lista.find(c => c.id === id)) || null;
    };

    // Abrir formulario para agregar contrato
    const handleAddNewContrato = () => setEditor({ contrato: null });

    // Editar contrato
    const handleEditContrato = (contrato) => setEditor({ contrato });

    const handleGuardado = () => {
        listContratos();
        onCambio?.();
        setEditor(null);
    };

    // Eliminar contrato. El servidor lo rechaza si tiene actas, actividades o modificaciones, y
    // explica por qué: en ese caso se anula.
    const handleDeleteContrato = async (contrato) => {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            titleText: `¿Eliminar el contrato ${contrato.n_contrato}?`,
            text: 'También se borran sus anexos. No se puede deshacer.',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        try {
            await axios.post('/eliminarContrato', { id: contrato.id });
            await listContratos();
            onCambio?.();
            Swal.fire({ icon: 'success', title: 'Contrato eliminado' });
        } catch (error) {
            avisarError('No se pudo eliminar el contrato', error);
        }
    };

    return (
        <div className="modal-overlay">
            <div className="modal-content contratos-modal" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2><FontAwesomeIcon icon={faFile} /> Gestión de Contratos - {proyecto?.nombre}</h2>
                    <button onClick={onCerrar} className="btn-close">×</button>
                </div>

                {editor ? (
                    <ContratoEditor
                        key={editor.contrato?.id ?? 'nuevo'}
                        proyecto={proyecto}
                        contrato={editor.contrato}
                        tiposContrato={tiposContrato}
                        procesosIniciales={procesos}
                        puedeEditar={puedeEditar}
                        recargar={recargar}
                        onGuardado={handleGuardado}
                        onCancelar={() => setEditor(null)}
                    />
                ) : (
                    <>
                        <div className="contratos-modal-body">
                            <div className="contratos-list-header">
                                <h3>Contratos del Proyecto</h3>
                                {puedeEditar && (
                                    <button
                                        onClick={handleAddNewContrato}
                                        className="btn-add-contrato-header"
                                    >
                                        <FontAwesomeIcon icon={faPlus} /> Agregar Contrato
                                    </button>
                                )}
                            </div>

                            <div className="contratos-list">
                                {contratos.length === 0 ? (
                                    <div className="no-contratos-container">
                                        <p className="no-contratos">No hay contratos registrados para este proyecto</p>
                                        {puedeEditar && (
                                            <button
                                                onClick={handleAddNewContrato}
                                                className="btn-add-first-contrato"
                                            >
                                                <FontAwesomeIcon icon={faPlus} /> Agregar Primer Contrato
                                            </button>
                                        )}
                                    </div>
                                ) : (
                                    <div className="contratos-table">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>N° Contrato</th>
                                                    <th>Objeto</th>
                                                    <th>Valor</th>
                                                    <th>Estado</th>
                                                    <th>Anexos</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {contratos.map(contrato => (
                                                    <tr key={contrato.id}>
                                                        <td>{contrato.n_contrato}</td>
                                                        <td>{contrato.objeto}</td>
                                                        <td>{formatoMoneda(Number(contrato.valor))}</td>
                                                        <td>{etiquetaEstadoContrato(contrato.estado)}</td>
                                                        <td>
                                                            <span className="anexos-count">
                                                                {contrato.anexos ? contrato.anexos.length : 0} anexos
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div className="contrato-actions">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => handleEditContrato(contrato)}
                                                                    className="btn-edit-contrato"
                                                                    title="Editar contrato"
                                                                >
                                                                    <FontAwesomeIcon icon={faEdit} />
                                                                </button>
                                                                {puedeEditar && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => handleDeleteContrato(contrato)}
                                                                        className="btn-delete-contrato"
                                                                        title="Eliminar contrato"
                                                                    >
                                                                        <FontAwesomeIcon icon={faTrash} />
                                                                    </button>
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="modal-actions">
                            <button type="button" onClick={onCerrar} className="btn-cancel">
                                <FontAwesomeIcon icon={faTimes} /> Cerrar
                            </button>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
};

export default ContratosProyecto;
```

Crear `resources/js/utils/moneda.js`:

```js
// Montos en pesos de los formularios de Parámetros (componentes del presupuesto) y de contratos
// (actas y modificaciones): cómo se muestran, cómo se escriben y cómo se leen.
// Funciones puras, probadas en moneda.test.js.

const PESOS_CON_CENTAVOS = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/** Un monto con centavos: 1234567.5 → "$ 1.234.567,50". */
export const formatoMoneda = (monto) => PESOS_CON_CENTAVOS.format(monto);

/** El número de lo escrito en un campo de moneda ("$ 1.487.342,33" → 1487342.33); vacío o inválido, 0. */
export const numeroDeMoneda = (valor) => {
    if (valor === '' || valor === null || valor === undefined) return 0;
    const limpio = String(valor).replace(/[^\d,]/g, '');
    if (!limpio) return 0;
    const [enteros, ...decimalesPartes] = limpio.split(',');
    const decimales = decimalesPartes.join('');
    const normalizado = decimales ? `${enteros}.${decimales}` : enteros;
    const numero = parseFloat(normalizado);
    return isNaN(numero) ? 0 : numero;
};

/**
 * Lo que se escribe en un campo de moneda: solo dígitos y una coma decimal, con máximo 2 dígitos
 * después de la coma (formato "1487342187,33").
 */
export const limpiarMoneda = (valor) => {
    const soloCifras = valor.replace(/[^\d,]/g, '');
    const primeraComa = soloCifras.indexOf(',');
    if (primeraComa === -1) return soloCifras;
    const enteros = soloCifras.slice(0, primeraComa);
    const decimales = soloCifras.slice(primeraComa + 1).replace(/,/g, '').slice(0, 2);
    return `${enteros},${decimales}`;
};

/**
 * Un valor ya limpio, formateado en vivo: agrupa los miles de la parte entera y conserva la parte
 * decimal tal cual se está escribiendo (sin redondearla mientras se teclea).
 */
export const monedaEnEdicion = (limpio) => {
    const [enteros, decimales] = limpio.split(',');
    const agrupados = enteros ? String(parseInt(enteros, 10)).replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '0';
    return decimales !== undefined ? `$ ${agrupados},${decimales}` : `$ ${agrupados}`;
};
```

En `resources/js/components/Parametros.jsx`, reemplazar (1 de 13):

```jsx
import React, { useState, useEffect, useRef } from 'react';
import { Navigate, useNavigate, useParams } from 'react-router-dom';
import axios from '../axios';
import { faPlus, faTrash, faSave, faTimes, faCalculator, faFile, faInfo, faEdit, faClipboardCheck, faEye, faGavel } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
import EmojiPicker from 'emoji-picker-react';
```

por:

```jsx
import React, { useState, useEffect } from 'react';
import { Navigate, useNavigate, useParams } from 'react-router-dom';
import axios from '../axios';
import { faPlus, faTrash, faSave, faTimes, faFile, faEdit, faClipboardCheck, faGavel } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
import EmojiPicker from 'emoji-picker-react';
```

En `resources/js/components/Parametros.jsx`, reemplazar (2 de 13):

```jsx
import '../../css/Parametros.css';
import { seccionDesdeSlug } from '../utils/navegacion';
import { alternarFuente, datosFinanciacionDe, financiacionCompleta, fuentesDisponibles, mensajeDeError } from '../utils/fuentesProyecto';
import { datosEstado, esEdicionDesactualizada, textoValor, valorEnEdicion } from '../utils/contratacion';
import {
    ESTADOS_CONTRATO, aplicarPrecarga, contratoVacio, datosContrato, datosDelProceso, erroresGuardarContrato, etiquetaEstadoContrato,
    formularioContrato, notaProceso, opcionesProceso, opcionesTipoContrato, primerCampoConError, procesoElegido, validarContrato,
    valorVigente, vigentesDistintos,
} from '../utils/contratos';
import { avisarError } from '../utils/avisosPlantillas';
import { htmlErrores, mensajeApi } from '../utils/plantillasChecklist';
import CatalogoModalidades from './CatalogoModalidades';
import CatalogoEtapasContratacion from './CatalogoEtapasContratacion';
import CatalogoTiposContrato from './CatalogoTiposContrato';
import ProcesosProyecto from './ProcesosProyecto';

// Componente auxiliar: escucha clics en el mapa y reporta la coordenada al padre.
// Debe vivir dentro de <MapContainer> — el hook useMapEvents solo funciona ahí.
```

por:

```jsx
import '../../css/Parametros.css';
import { seccionDesdeSlug } from '../utils/navegacion';
import { alternarFuente, datosFinanciacionDe, financiacionCompleta, fuentesDisponibles, mensajeDeError } from '../utils/fuentesProyecto';
import { formatoMoneda, limpiarMoneda, monedaEnEdicion, numeroDeMoneda } from '../utils/moneda';
import CatalogoModalidades from './CatalogoModalidades';
import CatalogoEtapasContratacion from './CatalogoEtapasContratacion';
import CatalogoTiposContrato from './CatalogoTiposContrato';
import ProcesosProyecto from './ProcesosProyecto';
import ContratosProyecto from './contratos/ContratosProyecto';

// Componente auxiliar: escucha clics en el mapa y reporta la coordenada al padre.
// Debe vivir dentro de <MapContainer> — el hook useMapEvents solo funciona ahí.
```

En `resources/js/components/Parametros.jsx`, reemplazar (3 de 13):

```jsx
    const [showModal, setShowModal] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [modalType, setModalType] = useState('');
    // Estados para el modal de contratos
    const [showContratosModal, setShowContratosModal] = useState(false);
    // Proyecto cuyos procesos de contratación se ven en el diálogo, o null.
    const [proyectoProcesos, setProyectoProcesos] = useState(null);
    const [proyectoContratos, setProyectoContratos] = useState(null);
    const [showContratoForm, setShowContratoForm] = useState(false);
    const [editingContrato, setEditingContrato] = useState(null);
    const [modalActiveTab, setModalActiveTab] = useState('datos');
    const [contratoActiveTab, setContratoActiveTab] = useState('informacion');
    const [anexos, setAnexos] = useState([]);
    const [formAnexo, setFormAnexo] = useState({
        descripcion: '',
        archivo: null
    });
    const [proyectos, setProyectos] = useState([]);
    const [municipios, setMunicipios] = useState([]);
    const [estados, setEstados] = useState([]);
```

por:

```jsx
    const [showModal, setShowModal] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [modalType, setModalType] = useState('');
    // Proyecto cuyos procesos de contratación se ven en el diálogo, o null.
    const [proyectoProcesos, setProyectoProcesos] = useState(null);
    // Proyecto cuyos contratos se ven en el diálogo, o null.
    const [proyectoContratos, setProyectoContratos] = useState(null);
    const [modalActiveTab, setModalActiveTab] = useState('datos');
    const [proyectos, setProyectos] = useState([]);
    const [municipios, setMunicipios] = useState([]);
    const [estados, setEstados] = useState([]);
```

En `resources/js/components/Parametros.jsx`, reemplazar (4 de 13):

```jsx
        accion: 'Agregar'
    });

    // Estado para la lista de contratos
    const [contratos, setContratos] = useState([]);

    // Campos de formulario para contratos (los de la API: utils/contratos.js)
    const [formContrato, setFormContrato] = useState(() => contratoVacio());
    // Errores del formulario por campo, de la validación local o de un 422.
    const [erroresContrato, setErroresContrato] = useState({});
    const [guardandoContrato, setGuardandoContrato] = useState(false);
    // Evita un segundo envío mientras el primero no termina (el estado tarda un render en verse).
    const enviandoContrato = useRef(false);
    // Cada error de validación sube el contador: tras pintar, se enfoca el primer campo con error.
    const [focoErrorContrato, setFocoErrorContrato] = useState(0);
    const formularioContratoRef = useRef(null);
    // Turno de la carga de procesos: una respuesta atrasada (de otro contrato) se descarta.
    const turnoProcesosContrato = useRef(0);
    // Catálogo de tipos de contrato y procesos del proyecto; null mientras no cargan.
    const [tiposContrato, setTiposContrato] = useState(null);
    const [procesosContrato, setProcesosContrato] = useState(null);

    const [actasFinancieras, setActasFinancieras] = useState([]);
    const [formActaFinanciera, setFormActaFinanciera] = useState({
        descripcion: '', fecha_acta: '', valor_facturado: '', amortizacion_50: '',
        valor_presente_acta: '', porcentaje_ejecutado: '', archivo: null
    });

    const [actividades, setActividades] = useState([]);
    const [formActividad, setFormActividad] = useState({ nombre: '', peso: '' });
    const [actividadRegistrandoAvance, setActividadRegistrandoAvance] = useState(null);
    const [formNuevoAvance, setFormNuevoAvance] = useState({ fecha: '', porcentaje_ejecucion: '' });

    const [modificaciones, setModificaciones] = useState([]);
    const [formModificacion, setFormModificacion] = useState({
        numero_otrosi: '', tipo: 'adicion', valor_adicion: '',
        dias_prorroga: '', fecha_modificacion: '', justificacion: '',
    });
    const [resumenModificacion, setResumenModificacion] = useState(null);

    // Manejar cambios en el formulario de contratos
    const handleContratoChange = (e) => {
        const { name, value, type, checked } = e.target;
        setErroresContrato(prev => (prev[name] ? { ...prev, [name]: undefined } : prev));
        if (name === 'proceso_id') {
            setFormContrato(prev => ({ ...prev, proceso_id: value }));
            // R8: en un contrato nuevo, el proceso elegido llena los campos que siguen vacíos.
            if (value && !editingContrato) precargarDesdeProceso(value);
            return;
        }
        if (name === 'anticipo' && !checked) {
            setFormContrato(prev => ({ ...prev, anticipo: false, porcentaje_anticipo: '' }));
            return;
        }

        if (name === 'porcentaje_anticipo') {
            if (value === '') {
                setFormContrato(prev => ({ ...prev, porcentaje_anticipo: '0' }));
                return;
            }
            if (value < 0 || value > 50) {
                Swal.fire({ icon: 'warning', title: 'El porcentaje de anticipo debe ser entre 0 y 50' });
                return;
            }
            setFormContrato(prev => ({ ...prev, porcentaje_anticipo: value }));
            return;
        }

        setFormContrato(prev => ({ ...prev, [name]: type === 'checkbox' ? checked : value }));
    };

    const precargarDesdeProceso = async (procesoId) => {
        try {
            const { data } = await axios.get(`/procesos/${procesoId}/precarga-contrato`);
            // Si entretanto se eligió otro proceso, esta precarga ya no aplica.
            setFormContrato(prev => (prev.proceso_id === procesoId ? aplicarPrecarga(prev, data?.precarga) : prev));
        } catch (error) {
            avisarError('No se pudieron traer los datos del proceso', error);
        }
    };

    // Tipos de contrato (GET /tiposProcesos) y procesos del proyecto para el formulario.
    const cargarTiposContrato = async () => {
        try {
            const { data } = await axios.get('/tiposProcesos');
            setTiposContrato(Array.isArray(data) ? data : []);
        } catch (error) {
            // Sin catálogo, el select ofrece solo el tipo que el contrato ya tiene.
            setTiposContrato(null);
        }
    };

    // Devuelve la lista pedida (o null si falló); solo la última carga llega al formulario.
    const cargarProcesosContrato = async (proyectoId) => {
        const numero = ++turnoProcesosContrato.current;
        if (!proyectoId) {
            setProcesosContrato([]);
            return [];
        }
        try {
            const { data } = await axios.get(`/proyectos/${proyectoId}/procesos`);
            const lista = data?.procesos ?? [];
            if (numero === turnoProcesosContrato.current) setProcesosContrato(lista);
            return lista;
        } catch (error) {
            if (numero === turnoProcesosContrato.current) setProcesosContrato(null);
            return null;
        }
    };

    useEffect(() => {
        if (!focoErrorContrato) return;
        const campo = primerCampoConError(erroresContrato);
        if (campo) formularioContratoRef.current?.querySelector(`[name="${campo}"]`)?.focus();
    }, [focoErrorContrato]);

    // Manejar cambios en el formulario de anexos
    const handleAnexoChange = (e) => {
        const { name, value, files } = e.target;
        if (name === 'archivo') {
            setFormAnexo(prev => ({ ...prev, [name]: files[0] }));
        } else {
            setFormAnexo(prev => ({ ...prev, [name]: value }));
        }
    };

    // Agregar anexo
    const handleAddAnexo = async () => {
        if (!formAnexo.descripcion || !formAnexo.archivo) {
            Swal.fire({ icon: 'warning', title: 'La descripción y el archivo son obligatorios' });
            return;
        }

        try {
            // Crear FormData para enviar el archivo
            const formData = new FormData();
            formData.append('archivo', formAnexo.archivo);
            formData.append('descripcion', formAnexo.descripcion);

            // Subir archivo al servidor
            const response = await axios.post('/subirAnexo', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                }
            });

            if (response.data.success) {
                const nuevoAnexo = {
                    id: Date.now(),
                    descripcion: formAnexo.descripcion,
                    nombreArchivo: formAnexo.archivo.name,
                    rutaArchivo: response.data.ruta,
                    fecha: new Date().toISOString().split('T')[0]
                };

                setAnexos(prev => [...prev, nuevoAnexo]);
                setFormAnexo({ descripcion: '', archivo: null });

                Swal.fire({
                    icon: 'success',
                    title: 'Anexo agregado correctamente',
                    text: response.data.mensaje
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al subir el archivo',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al subir anexo:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error al subir el archivo',
                text: error.response?.data?.mensaje || 'Error interno del servidor'
            });
        }
    };

    // Eliminar anexo
    const handleDeleteAnexo = async (id) => {
        try {
            const response = await axios.post('/eliminarAnexo', { id: id });

            if (response.data.success) {
                setAnexos(prev => prev.filter(anexo => anexo.id !== id));
                Swal.fire({
                    icon: 'success',
                    title: 'Anexo eliminado correctamente',
                    text: response.data.mensaje
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al eliminar el anexo',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al eliminar anexo:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error al eliminar el anexo',
                text: error.response?.data?.mensaje || 'Error interno del servidor'
            });
        }
    };

    const calcularAmortizacionYValorPresente = (valorFacturado) => {
        const porcentajeAnticipo = parseInt(formContrato.porcentaje_anticipo, 10) || 0;
        const amortizacion = formContrato.anticipo ? Math.round(valorFacturado * (porcentajeAnticipo / 100) * 100) / 100 : 0;
        const valorPresente = Math.round((valorFacturado - amortizacion) * 100) / 100;
        const vigente = valorVigente(formContrato, editingContrato);
        const porcentajeEjecutado = vigente ? Math.round((valorFacturado / vigente) * 100 * 100) / 100 : 0;
        return { amortizacion, valorPresente, porcentajeEjecutado };
    };

    const handleChangeValorFacturado = (e) => {
        const sanitized = sanitizeCurrencyInput(e.target.value);
        const valorFacturado = parseCurrencyValue(sanitized);
        const { amortizacion, valorPresente, porcentajeEjecutado } = calcularAmortizacionYValorPresente(valorFacturado);
        setFormActaFinanciera(prev => ({
            ...prev,
            valor_facturado: sanitized ? formatCurrencyInput(sanitized) : '',
            amortizacion_50: sanitized ? amortizacion : '',
            valor_presente_acta: sanitized ? valorPresente : '',
            porcentaje_ejecutado: sanitized ? porcentajeEjecutado : ''
        }));
    };

    // Si el usuario cambia el anticipo/porcentaje/valor del contrato después de haber escrito
    // el valor facturado del acta, recalcula amortización/valor presente/% ejecutado.
    useEffect(() => {
        setFormActaFinanciera(prev => {
            if (prev.valor_facturado === '') return prev;
            const valorFacturado = parseCurrencyValue(prev.valor_facturado);
            const { amortizacion, valorPresente, porcentajeEjecutado } = calcularAmortizacionYValorPresente(valorFacturado);
            return { ...prev, amortizacion_50: amortizacion, valor_presente_acta: valorPresente, porcentaje_ejecutado: porcentajeEjecutado };
        });
    }, [formContrato.anticipo, formContrato.porcentaje_anticipo, formContrato.valor_inicial, editingContrato]);

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
                valor_facturado: parseCurrencyValue(formActaFinanciera.valor_facturado),
                amortizacion_50: formActaFinanciera.amortizacion_50,
                valor_presente_acta: formActaFinanciera.valor_presente_acta,
                porcentaje_ejecutado: formActaFinanciera.porcentaje_ejecutado,
                anexo: rutaAnexo
            });

            if (response.data.success) {
                setActasFinancieras(prev => [
                    {
                        ...formActaFinanciera,
                        id: response.data.id,
                        anexo: rutaAnexo,
                        valor_facturado: parseCurrencyValue(formActaFinanciera.valor_facturado),
                    },
                    ...prev
                ]);
                setFormActaFinanciera({
                    descripcion: '', fecha_acta: '', valor_facturado: '', amortizacion_50: '',
                    valor_presente_acta: '', porcentaje_ejecutado: '', archivo: null
                });
                listContratos();
                Swal.fire({ icon: 'success', title: 'Acta agregada correctamente' });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al guardar el acta',
                    text: response.data.mensaje
                });
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
                listContratos();
                Swal.fire({ icon: 'success', title: 'Acta eliminada correctamente' });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al eliminar el acta',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al eliminar acta financiera:', error);
            Swal.fire({ icon: 'error', title: 'Error al eliminar el acta', text: error.response?.data?.mensaje || 'Error interno del servidor' });
        }
    };

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
                listContratos();
                Swal.fire({ icon: 'success', title: 'Actividad agregada correctamente' });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al agregar la actividad',
                    text: response.data.mensaje
                });
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
                listContratos();
                Swal.fire({ icon: 'success', title: 'Actividad eliminada correctamente' });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al eliminar la actividad',
                    text: response.data.mensaje
                });
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
                listContratos();
                Swal.fire({ icon: 'success', title: 'Avance registrado correctamente' });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al registrar el avance',
                    text: response.data.mensaje
                });
            }
        } catch (error) {
            console.error('Error al registrar avance:', error);
            Swal.fire({ icon: 'error', title: 'Error al registrar el avance', text: error.response?.data?.mensaje || 'Error interno del servidor' });
        }
    };

    // Calcular avance financiero automáticamente: suma de valor_facturado de todas las actas / valor vigente del contrato
    useEffect(() => {
        const vigente = valorVigente(formContrato, editingContrato);
        if (actasFinancieras.length === 0 || !vigente) {
            setFormContrato(prev => (prev.avance_financiero === '' ? prev : { ...prev, avance_financiero: '' }));
            return;
        }
        const totalFacturado = actasFinancieras.reduce((sum, acta) => sum + (parseFloat(acta.valor_facturado) || 0), 0);
        const porcentaje = Math.round((totalFacturado / vigente) * 100 * 100) / 100;
        setFormContrato(prev => (prev.avance_financiero === porcentaje ? prev : { ...prev, avance_financiero: porcentaje }));
    }, [actasFinancieras, formContrato.valor_inicial, editingContrato]);

    // Calcular avance físico: suma ponderada de (peso x último % de ejecución) de cada actividad
    const handleCalcularAvanceFisico = () => {
        if (actividades.length === 0) {
            Swal.fire({ icon: 'warning', title: 'No hay actividades de avance físico registradas' });
            return;
        }
        const total = actividades.reduce((sum, act) => sum + ((act.peso || 0) / 100) * (act.ultimo_avance || 0), 0);
        setFormContrato(prev => ({ ...prev, avance_fisico: Math.round(total) }));
    };

    // Eliminar contrato. El servidor lo rechaza si tiene actas, actividades o modificaciones, y
    // explica por qué: en ese caso se anula.
    const handleDeleteContrato = async (contrato) => {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            titleText: `¿Eliminar el contrato ${contrato.n_contrato}?`,
            text: 'También se borran sus anexos. No se puede deshacer.',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        try {
            await axios.post('/eliminarContrato', { id: contrato.id });
            await listContratos();
            listProyectos();
            Swal.fire({ icon: 'success', title: 'Contrato eliminado' });
        } catch (error) {
            avisarError('No se pudo eliminar el contrato', error);
        }
    };

    // Editar contrato
    const handleEditContrato = (contrato) => {
        setEditingContrato(contrato);
        setFormContrato(formularioContrato(contrato));
        setErroresContrato({});
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
        setModificaciones(contrato.modificaciones || []);
        setResumenModificacion(null);
        setShowContratoForm(true);
        // Los estados de los procesos pueden haber cambiado desde que se abrió la lista.
        cargarProcesosContrato(contrato.proyecto ?? proyectoContratos?.id);
    };

    // Primer contrato de un proceso adjudicado: se propone pasar el proceso a celebrado.
    const proponerProcesoCelebrado = async (contrato) => {
        const { isConfirmed } = await Swal.fire({
            icon: 'question',
            titleText: '¿Pasar el proceso a celebrado?',
            text: 'Es el primer contrato de este proceso adjudicado. El cambio queda en el historial del proceso.',
            showCancelButton: true,
            confirmButtonText: 'Sí, pasar a celebrado',
            cancelButtonText: 'Ahora no',
        });
        if (!isConfirmed) return;
        // Recién cargado, el proceso trae su `actualizado` vigente.
        const lista = await cargarProcesosContrato(contrato.proyecto);
        const proceso = (lista ?? []).find(p => p.id === contrato.proceso_id);
        if (!proceso) {
            Swal.fire({ icon: 'error', titleText: 'No se pudo cambiar el estado', text: 'No se pudo cargar el proceso: cambia su estado desde Procesos.' });
            return;
        }
        try {
            await axios.post(`/procesos/${proceso.id}/estado`, datosEstado(proceso, 'celebrado', null));
            await cargarProcesosContrato(contrato.proyecto);
            await Swal.fire({ icon: 'success', title: 'El proceso quedó celebrado' });
        } catch (error) {
            if (esEdicionDesactualizada(error)) {
                await Swal.fire({ icon: 'warning', titleText: 'No se cambió el estado del proceso', text: mensajeApi(error) });
                return;
            }
            avisarError('No se pudo cambiar el estado del proceso', error);
        }
    };

    // Guardar contrato (agregar o actualizar), una vez a la vez.
    const handleSaveContrato = async () => {
        if (enviandoContrato.current) return;
        enviandoContrato.current = true;
        try {
            await enviarContrato();
        } finally {
            enviandoContrato.current = false;
        }
    };

    const enviarContrato = async () => {
        const locales = validarContrato(formContrato);
        setErroresContrato(locales);
        if (Object.keys(locales).length) {
            setContratoActiveTab('informacion');
            setFocoErrorContrato(n => n + 1);
            return;
        }

        setGuardandoContrato(true);
        let respuesta;
        try {
            respuesta = await axios.post('/guardarContrato', {
                formContrato: datosContrato(formContrato, editingContrato),
                anexos: anexos.map(anexo => ({
                    descripcion: anexo.descripcion,
                    nombreArchivo: anexo.nombreArchivo,
                    rutaArchivo: anexo.rutaArchivo,
                    fecha: anexo.fecha
                }))
            });
        } catch (error) {
            if (esEdicionDesactualizada(error)) {
                // Otra persona cambió el contrato: se avisa y se recarga la lista. El formulario
                // sigue abierto para copiar lo escrito; al reabrirlo trae lo último.
                await Promise.all([
                    listContratos(),
                    Swal.fire({ icon: 'warning', titleText: 'No se guardó tu cambio', text: mensajeApi(error) }),
                ]);
                return;
            }
            const errores = erroresGuardarContrato(error);
            if (!errores) {
                avisarError('No se pudo guardar el contrato', error);
                return;
            }
            // Los de campos con control, junto a su control; los demás (el proyecto, los anexos), en un aviso.
            if (Object.keys(errores.campos).length) {
                setErroresContrato(errores.campos);
                setContratoActiveTab('informacion');
                setFocoErrorContrato(n => n + 1);
            }
            if (errores.otros.length) {
                Swal.fire({ icon: 'error', titleText: 'No se pudo guardar el contrato', html: htmlErrores('Revisa lo siguiente:', errores.otros) });
            } else if (!Object.keys(errores.campos).length) {
                avisarError('No se pudo guardar el contrato', error);
            }
            return;
        } finally {
            setGuardandoContrato(false);
        }

        const { contrato, avisos = [], proponer_celebrado: proponerCelebrado } = respuesta.data ?? {};
        if (avisos.length) {
            await Swal.fire({ icon: 'warning', titleText: 'Contrato guardado, con avisos', html: htmlErrores('Revisa lo siguiente:', avisos) });
        } else {
            await Swal.fire({ icon: 'success', title: 'Contrato guardado correctamente' });
        }
        if (proponerCelebrado && contrato?.proceso_id) await proponerProcesoCelebrado(contrato);

        listContratos();
        listProyectos();
        handleCancelContratoForm();
    };

    // Cancelar edición/agregado de contrato
    const handleCancelContratoForm = () => {
        setShowContratoForm(false);
        setEditingContrato(null);
        setFormContrato(contratoVacio(proyectoContratos?.id));
        setErroresContrato({});
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
        setModificaciones([]);
        setFormModificacion({ numero_otrosi: '', tipo: 'adicion', valor_adicion: '', dias_prorroga: '', fecha_modificacion: '', justificacion: '' });
        setResumenModificacion(null);
        setContratoActiveTab('informacion');
    };

    // Abrir formulario para agregar contrato
    const handleAddNewContrato = () => {
        setEditingContrato(null);
        setFormContrato(contratoVacio(proyectoContratos?.id));
        setErroresContrato({});
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
        setModificaciones([]);
        setFormModificacion({ numero_otrosi: '', tipo: 'adicion', valor_adicion: '', dias_prorroga: '', fecha_modificacion: '', justificacion: '' });
        setResumenModificacion(null);
        setContratoActiveTab('informacion');
        setShowContratoForm(true);
        cargarProcesosContrato(proyectoContratos?.id);
    };

    const listContratos = async () => {
        const response = await axios.get('/listarContratos', {
            params: {
                proyecto: proyectoContratos.id
            }
        });
        setContratos(response.data);
        return response.data;
    }

    // Refrescar los datos del contrato actualmente abierto en el modal
    const refrescarContratoAbierto = async () => {
        const lista = await listContratos();
        if (formContrato.id && Array.isArray(lista)) {
            const actual = lista.find(c => c.id === formContrato.id);
            if (actual) {
                setModificaciones(actual.modificaciones || []);
                // Los vigentes recalculados salen del contrato recargado; lo escrito en el
                // formulario se conserva. Registrar una modificación no cambia la versión
                // (updated_at), y se guarda la que tenía al abrirse: así un cambio de otra
                // persona entretanto sigue dando 409.
                setEditingContrato(prev => ({ ...actual, updated_at: prev?.updated_at ?? actual.updated_at }));
            }
        }
    };

    // Manejar cambios en el formulario de modificación (otrosí)
    const handleModificacionChange = (e) => {
        const { name, value } = e.target;
        if (name === 'valor_adicion') {
            // Preservar la coma decimal (centavos), igual que los demás inputs de moneda.
            const sanitized = sanitizeCurrencyInput(value);
            setFormModificacion(prev => ({ ...prev, valor_adicion: sanitized ? formatCurrencyInput(sanitized) : '' }));
            return;
        }
        setFormModificacion(prev => ({ ...prev, [name]: value }));
    };

    // Guardar una modificación (otrosí) y recalcular el contrato
    const guardarModificacion = async () => {
        if (!formContrato.id) {
            Swal.fire({ icon: 'warning', title: 'Guarda primero el contrato antes de registrar modificaciones.' });
            return;
        }
        if (!formModificacion.fecha_modificacion) {
            Swal.fire({ icon: 'warning', title: 'La fecha de la modificación es obligatoria.' });
            return;
        }
        try {
            const resp = await axios.post('/guardarModificacionContrato', {
                contrato_id: formContrato.id,
                numero_otrosi: formModificacion.numero_otrosi,
                tipo: formModificacion.tipo,
                valor_adicion: formModificacion.tipo === 'prorroga' ? null : parseCurrencyValue(formModificacion.valor_adicion) || null,
                dias_prorroga: formModificacion.tipo === 'adicion' ? null : (formModificacion.dias_prorroga || null),
                fecha_modificacion: formModificacion.fecha_modificacion,
                justificacion: formModificacion.justificacion,
            });
            setResumenModificacion(resp.data.resumen);
            if (resp.data.resumen?.supera_limite) {
                Swal.fire({ icon: 'warning', title: 'Adición supera el 50% legal', text: 'Se registró de todas formas.' });
            }
            await refrescarContratoAbierto();
            setFormModificacion({ numero_otrosi: '', tipo: 'adicion', valor_adicion: '', dias_prorroga: '', fecha_modificacion: '', justificacion: '' });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error al guardar la modificación', text: e.response?.data?.error || e.message });
        }
    };

    // Eliminar una modificación (otrosí) y recalcular el contrato
    const eliminarModificacion = async (id) => {
        const result = await Swal.fire({
            icon: 'warning', title: '¿Eliminar esta modificación?',
            text: 'Se recalculará el valor y la fecha fin del contrato.',
            showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
        });
        if (!result.isConfirmed) return;
        try {
            const resp = await axios.post('/eliminarModificacionContrato', { id });
            setResumenModificacion(resp.data.resumen);
            await refrescarContratoAbierto();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error al eliminar la modificación', text: e.response?.data?.error || e.message });
        }
    };


    const handleEmojiClick = (emojiData) => {
        setFormData(prev => ({
            ...prev,
```

por:

```jsx
        accion: 'Agregar'
    });

    // Contratos del proyecto que se edita: van con él a /guardarProyecto
    const [contratos, setContratos] = useState([]);

    const handleEmojiClick = (emojiData) => {
        setFormData(prev => ({
            ...prev,
```

En `resources/js/components/Parametros.jsx`, reemplazar (5 de 13):

```jsx
        return new Date(dateString).toLocaleDateString('es-ES', options);
    };

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    };

    const parseCurrencyValue = (value) => {
        if (value === '' || value === null || value === undefined) return 0;
        const cleaned = String(value).replace(/[^\d,]/g, '');
        if (!cleaned) return 0;
        const [enteros, ...decimalesPartes] = cleaned.split(',');
        const decimales = decimalesPartes.join('');
        const normalizado = decimales ? `${enteros}.${decimales}` : enteros;
        const parsed = parseFloat(normalizado);
        return isNaN(parsed) ? 0 : parsed;
    };

    // Sanitiza lo que el usuario escribe en un input de moneda: solo dígitos y una coma
    // decimal, con máximo 2 dígitos después de la coma (formato "1487342187,33").
    const sanitizeCurrencyInput = (value) => {
        const stripped = value.replace(/[^\d,]/g, '');
        const primeraComa = stripped.indexOf(',');
        if (primeraComa === -1) return stripped;
        const enteros = stripped.slice(0, primeraComa);
        const decimales = stripped.slice(primeraComa + 1).replace(/,/g, '').slice(0, 2);
        return `${enteros},${decimales}`;
    };

    // Formatea en vivo un valor ya sanitizado: agrupa miles en la parte entera y conserva
    // la parte decimal tal cual se está escribiendo (sin redondearla mientras se tipea).
    const formatCurrencyInput = (raw) => {
        const [enteros, decimales] = raw.split(',');
        const enterosAgrupados = enteros ? String(parseInt(enteros, 10)).replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '0';
        return decimales !== undefined ? `$ ${enterosAgrupados},${decimales}` : `$ ${enterosAgrupados}`;
    };

    const handleValorChange = (e) => {
        const sanitized = sanitizeCurrencyInput(e.target.value);
        setFormData(prev => ({
            ...prev,
            valor: sanitized ? formatCurrencyInput(sanitized) : ''
        }));
    };

    // Valor inicial con los miles agrupados ("1.234.567,89"), como el valor de los procesos.
    const handleContratoValorChange = (e) => {
        const { value } = e.target;
        setErroresContrato(prev => (prev.valor_inicial ? { ...prev, valor_inicial: undefined } : prev));
        setFormContrato(prev => ({ ...prev, valor_inicial: valorEnEdicion(value, prev.valor_inicial) }));
    };

    const handleAddComponente = () => {
        const valorNumerico = parseCurrencyValue(formData.valor);
        if (formData.descripcionComponente && valorNumerico > 0) {
            const nuevoComponente = {
                id: Date.now(),
```

por:

```jsx
        return new Date(dateString).toLocaleDateString('es-ES', options);
    };

    const handleValorChange = (e) => {
        const sanitized = limpiarMoneda(e.target.value);
        setFormData(prev => ({
            ...prev,
            valor: sanitized ? monedaEnEdicion(sanitized) : ''
        }));
    };

    const handleAddComponente = () => {
        const valorNumerico = numeroDeMoneda(formData.valor);
        if (formData.descripcionComponente && valorNumerico > 0) {
            const nuevoComponente = {
                id: Date.now(),
```

En `resources/js/components/Parametros.jsx`, reemplazar (6 de 13):

```jsx
        return detallesPresupuesto.reduce((sum, comp) => sum + comp.valor, 0);
    };

    const handleAddContratoProyecto = (item, type) => {
        console.log(item);
        setProyectoContratos(item);
        // Cargar contratos existentes del proyecto
        if (item.contratos && item.contratos.length > 0) {
            setContratos(item.contratos);
        } else {
            setContratos([]);
        }
        // Resetear estado del formulario
        setShowContratoForm(false);
        setEditingContrato(null);
        setFormContrato(contratoVacio(item.id));
        setErroresContrato({});
        setProcesosContrato(null);
        cargarTiposContrato();
        cargarProcesosContrato(item.id);
        setShowContratosModal(true);
    };

    const renderTable = (data, type) => {
        // Paginación
        const [currentPage, setCurrentPage] = useState(1);
```

por:

```jsx
        return detallesPresupuesto.reduce((sum, comp) => sum + comp.valor, 0);
    };

    const renderTable = (data, type) => {
        // Paginación
        const [currentPage, setCurrentPage] = useState(1);
```

En `resources/js/components/Parametros.jsx`, reemplazar (7 de 13):

```jsx
                                    )}
                                    {type === 'tiposEventos' && <td data-etiqueta="Icono">{item.icono}</td>}
                                    {type === 'proyectos' && <td data-etiqueta="Municipio">{item.descripcion_municipio}</td>}
                                    {type === 'proyectos' && <td data-etiqueta="Presupuesto" className="numero">{formatCurrency(item.presupuesto)}</td>}
                                    {type === 'proyectos' && (
                                        <td data-etiqueta="Estado">
                                            <span className="status-badge" style={{ backgroundColor: item.color_estado, color: 'white' }}>
```

por:

```jsx
                                    )}
                                    {type === 'tiposEventos' && <td data-etiqueta="Icono">{item.icono}</td>}
                                    {type === 'proyectos' && <td data-etiqueta="Municipio">{item.descripcion_municipio}</td>}
                                    {type === 'proyectos' && <td data-etiqueta="Presupuesto" className="numero">{formatoMoneda(item.presupuesto)}</td>}
                                    {type === 'proyectos' && (
                                        <td data-etiqueta="Estado">
                                            <span className="status-badge" style={{ backgroundColor: item.color_estado, color: 'white' }}>
```

En `resources/js/components/Parametros.jsx`, reemplazar (8 de 13):

```jsx

                                            {type === 'proyectos' && (
                                                <button
                                                    onClick={() => handleAddContratoProyecto(item, type)}
                                                    className="btn-add-contrato-list"
                                                    title="Agregar Contrato"
                                                >
```

por:

```jsx

                                            {type === 'proyectos' && (
                                                <button
                                                    onClick={() => setProyectoContratos(item)}
                                                    className="btn-add-contrato-list"
                                                    title="Agregar Contrato"
                                                >
```

En `resources/js/components/Parametros.jsx`, reemplazar (9 de 13):

```jsx

    if (!seccion) return <Navigate to="/parametros/proyectos" replace />;

    // Formulario de contrato: proceso elegido, nota R4 y vigentes que difieren de los iniciales.
    const procesoDelContrato = procesoElegido(procesosContrato, editingContrato?.proceso ?? null, formContrato.proceso_id);
    const datosProcesoContrato = procesoDelContrato ? datosDelProceso(procesoDelContrato) : null;
    const notaProcesoContrato = notaProceso(procesosContrato, formContrato.proceso_id);
    const vigentesContrato = vigentesDistintos(editingContrato);

    // Propiedades comunes de un control del formulario de contrato: valor, cambio y aviso de error.
    const controlContrato = (campo) => ({
        id: campo,
        name: campo,
        value: formContrato[campo] ?? '',
        onChange: handleContratoChange,
        'aria-invalid': Boolean(erroresContrato[campo]),
        'aria-describedby': erroresContrato[campo] ? `${campo}-error` : undefined,
    });
    const campoContrato = (campo, etiqueta, control) => (
        <div className="form-group">
            <label htmlFor={campo}>{etiqueta}</label>
            {control}
            {erroresContrato[campo] && <small id={`${campo}-error`} className="ct-error-campo" role="alert">{erroresContrato[campo]}</small>}
        </div>
    );

    return (
        <div className="parametros-container">
            <CabeceraSeccion seccion={seccion} />
```

por:

```jsx

    if (!seccion) return <Navigate to="/parametros/proyectos" replace />;

    return (
        <div className="parametros-container">
            <CabeceraSeccion seccion={seccion} />
```

En `resources/js/components/Parametros.jsx`, reemplazar (10 de 13):

```jsx
                                                type="button"
                                                onClick={handleAddComponente}
                                                className="btn-add-componente"
                                                disabled={!formData.descripcionComponente || parseCurrencyValue(formData.valor) <= 0}
                                            >
                                                <FontAwesomeIcon icon={faPlus} /> Agregar Componente
                                            </button>
```

por:

```jsx
                                                type="button"
                                                onClick={handleAddComponente}
                                                className="btn-add-componente"
                                                disabled={!formData.descripcionComponente || numeroDeMoneda(formData.valor) <= 0}
                                            >
                                                <FontAwesomeIcon icon={faPlus} /> Agregar Componente
                                            </button>
```

En `resources/js/components/Parametros.jsx`, reemplazar (11 de 13):

```jsx
                                                            {detallesPresupuesto.map(componente => (
                                                                <tr key={componente.id}>
                                                                    <td>{componente.descripcionComponente}</td>
                                                                    <td>{formatCurrency(componente.valor)}</td>
                                                                    <td>
                                                                        <button
                                                                            type="button"
```

por:

```jsx
                                                            {detallesPresupuesto.map(componente => (
                                                                <tr key={componente.id}>
                                                                    <td>{componente.descripcionComponente}</td>
                                                                    <td>{formatoMoneda(componente.valor)}</td>
                                                                    <td>
                                                                        <button
                                                                            type="button"
```

En `resources/js/components/Parametros.jsx`, reemplazar (12 de 13):

```jsx
                                        <div className="presupuesto-total">
                                            <div className="total-card">
                                                <span className="total-label">Presupuesto Total:</span>
                                                <span className="total-amount">{formatCurrency(calcularTotalPresupuesto())}</span>
                                            </div>
                                        </div>
                                    </div>
```

por:

```jsx
                                        <div className="presupuesto-total">
                                            <div className="total-card">
                                                <span className="total-label">Presupuesto Total:</span>
                                                <span className="total-amount">{formatoMoneda(calcularTotalPresupuesto())}</span>
                                            </div>
                                        </div>
                                    </div>
```

En `resources/js/components/Parametros.jsx`, reemplazar (13 de 13):

```jsx
            )}

            {/* Modal para gestión de contratos */}
            {showContratosModal && (
                <div className="modal-overlay">
                    <div className="modal-content contratos-modal" onClick={e => e.stopPropagation()}>
                        <div className="modal-header">
                            <h2><FontAwesomeIcon icon={faFile} /> Gestión de Contratos - {proyectoContratos?.nombre}</h2>
                            <button onClick={() => setShowContratosModal(false)} className="btn-close">×</button>
                        </div>

                        <div className="contratos-modal-body">
                            {!showContratoForm ? (
                                // Vista de lista de contratos
                                <>
                                    <div className="contratos-list-header">
                                        <h3>Contratos del Proyecto</h3>
                                        <button
                                            onClick={handleAddNewContrato}
                                            className="btn-add-contrato-header"
                                        >
                                            <FontAwesomeIcon icon={faPlus} /> Agregar Contrato
                                        </button>
                                    </div>

                                    <div className="contratos-list">
                                        {contratos.length === 0 ? (
                                            <div className="no-contratos-container">
                                                <p className="no-contratos">No hay contratos registrados para este proyecto</p>
                                                <button
                                                    onClick={handleAddNewContrato}
                                                    className="btn-add-first-contrato"
                                                >
                                                    <FontAwesomeIcon icon={faPlus} /> Agregar Primer Contrato
                                                </button>
                                            </div>
                                        ) : (
                                            <div className="contratos-table">
                                                <table>
                                                    <thead>
                                                        <tr>
                                                            <th>N° Contrato</th>
                                                            <th>Objeto</th>
                                                            <th>Valor</th>
                                                            <th>Estado</th>
                                                            <th>Anexos</th>
                                                            <th>Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {contratos.map(contrato => (
                                                            <tr key={contrato.id}>
                                                                <td>{contrato.n_contrato}</td>
                                                                <td>{contrato.objeto}</td>
                                                                <td>{formatCurrency(Number(contrato.valor))}</td>
                                                                <td>{etiquetaEstadoContrato(contrato.estado)}</td>
                                                                <td>
                                                                    <span className="anexos-count">
                                                                        {contrato.anexos ? contrato.anexos.length : 0} anexos
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <div className="contrato-actions">
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleEditContrato(contrato)}
                                                                            className="btn-edit-contrato"
                                                                            title="Editar contrato"
                                                                        >
                                                                            <FontAwesomeIcon icon={faEdit} />
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleDeleteContrato(contrato)}
                                                                            className="btn-delete-contrato"
                                                                            title="Eliminar contrato"
                                                                        >
                                                                            <FontAwesomeIcon icon={faTrash} />
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </div>
                                </>
                            ) : (
                                // Vista del formulario
                                <>
                                    <div className="contrato-form-header">
                                        <h3>{editingContrato ? 'Editar Contrato' : 'Agregar Nuevo Contrato'}</h3>
                                        <button
                                            onClick={handleCancelContratoForm}
                                            className="btn-back-to-list"
                                        >
                                            ← Volver a la lista
                                        </button>
                                    </div>

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
                                        {editingContrato && (
                                            <button
                                                className={`contrato-tab ${contratoActiveTab === 'modificaciones' ? 'active' : ''}`}
                                                onClick={() => setContratoActiveTab('modificaciones')}
                                            >
                                                <FontAwesomeIcon icon={faFile} /> Modificaciones ({modificaciones.length})
                                            </button>
                                        )}
                                    </div>

                                    {/* Contenido de la pestaña de información */}
                                    {contratoActiveTab === 'informacion' && (
                                        <div className="contratos-modal-section">
                                            <p className="contratos-info">
                                                Completa los datos del contrato. Los campos marcados con * son obligatorios.
                                            </p>
                                            <div className="contrato-form" ref={formularioContratoRef}>
                                                <div className="form-row">
                                                    {campoContrato('proceso_id', 'Proceso de contratación', (
                                                        <select {...controlContrato('proceso_id')}>
                                                            {opcionesProceso(procesosContrato, editingContrato?.proceso ?? null).map(o => (
                                                                <option key={o.valor} value={o.valor}>{o.etiqueta}</option>
                                                            ))}
                                                        </select>
                                                    ))}
                                                    {campoContrato('tipo_contrato_id', 'Tipo de contrato', (
                                                        <select {...controlContrato('tipo_contrato_id')}>
                                                            <option value="">Sin tipo</option>
                                                            {opcionesTipoContrato(tiposContrato, editingContrato).map(o => (
                                                                <option key={o.valor} value={o.valor}>{o.etiqueta}</option>
                                                            ))}
                                                        </select>
                                                    ))}
                                                </div>
                                                {notaProcesoContrato && <p className="contrato-nota">{notaProcesoContrato}</p>}
                                                {datosProcesoContrato && (
                                                    <dl className="contrato-proceso-datos" aria-label="Datos del proceso">
                                                        <div><dt>Número del proceso</dt><dd>{datosProcesoContrato.numero}</dd></div>
                                                        <div><dt>Modalidad</dt><dd>{datosProcesoContrato.modalidad}</dd></div>
                                                        <div><dt>Plataforma</dt><dd>{datosProcesoContrato.plataforma}</dd></div>
                                                        <div>
                                                            <dt>Enlace</dt>
                                                            <dd>
                                                                {datosProcesoContrato.enlace
                                                                    ? <a className="ct-enlace" href={datosProcesoContrato.enlace} target="_blank" rel="noopener noreferrer">Ver el proceso</a>
                                                                    : '—'}
                                                            </dd>
                                                        </div>
                                                    </dl>
                                                )}
                                                <div className="form-row">
                                                    {campoContrato('n_contrato', 'N° Contrato *', <input type="text" maxLength={100} {...controlContrato('n_contrato')} />)}
                                                    {campoContrato('estado', 'Estado *', (
                                                        <select {...controlContrato('estado')}>
                                                            <option value="">Elige el estado</option>
                                                            {ESTADOS_CONTRATO.map(([valor, etiqueta]) => (
                                                                <option key={valor} value={valor}>{etiqueta}</option>
                                                            ))}
                                                        </select>
                                                    ))}
                                                </div>
                                                {campoContrato('objeto', 'Objeto *', (
                                                    <textarea rows="4" maxLength={5000} placeholder="Escribe el objeto del contrato" {...controlContrato('objeto')} />
                                                ))}
                                                <div className="form-row">
                                                    {campoContrato('contratante', 'Contratante', <input type="text" maxLength={5000} {...controlContrato('contratante')} />)}
                                                    {campoContrato('contratante_nit', 'NIT de la entidad contratante', <input type="text" maxLength={30} {...controlContrato('contratante_nit')} />)}
                                                </div>
                                                <div className="form-row">
                                                    {campoContrato('contratista', 'Contratista', <input type="text" maxLength={5000} {...controlContrato('contratista')} />)}
                                                    {campoContrato('contratista_identificacion', 'Identificación del contratista', <input type="text" maxLength={30} {...controlContrato('contratista_identificacion')} />)}
                                                </div>
                                                <div className="form-row">
                                                    {campoContrato('valor_inicial', 'Valor inicial *', (
                                                        <input type="text" inputMode="decimal" placeholder="0" {...controlContrato('valor_inicial')} onChange={handleContratoValorChange} />
                                                    ))}
                                                    {vigentesContrato.valor && (
                                                        <div className="form-group">
                                                            <label htmlFor="valor_vigente">Valor vigente</label>
                                                            <input type="text" id="valor_vigente" disabled value={textoValor(valorVigente(formContrato, editingContrato))} aria-describedby="vigentes-nota" />
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="form-row">
                                                    {campoContrato('fecha_suscripcion', 'Fecha de suscripción', <input type="date" {...controlContrato('fecha_suscripcion')} />)}
                                                    {campoContrato('fecha_acta_inicio', 'Fecha del acta de inicio', <input type="date" {...controlContrato('fecha_acta_inicio')} />)}
                                                </div>
                                                <div className="form-row">
                                                    {campoContrato('fecha_inicio', 'Fecha de inicio', <input type="date" {...controlContrato('fecha_inicio')} />)}
                                                    {campoContrato('fecha_fin_inicial', 'Fecha fin inicial', <input type="date" {...controlContrato('fecha_fin_inicial')} />)}
                                                    {vigentesContrato.fechaFin && (
                                                        <div className="form-group">
                                                            <label htmlFor="fecha_fin_vigente">Fecha fin vigente</label>
                                                            <input type="date" id="fecha_fin_vigente" disabled value={String(editingContrato.fecha_fin).slice(0, 10)} aria-describedby="vigentes-nota" />
                                                        </div>
                                                    )}
                                                </div>
                                                {(vigentesContrato.valor || vigentesContrato.fechaFin) && (
                                                    <p id="vigentes-nota" className="contrato-nota">
                                                        Los vigentes suman al valor inicial y a la fecha fin inicial las adiciones y prórrogas de la pestaña Modificaciones; se recalculan al guardar.
                                                    </p>
                                                )}
                                                <div className="form-row">
                                                    {campoContrato('numero_rp', 'Número del RP', <input type="text" maxLength={40} {...controlContrato('numero_rp')} />)}
                                                    {campoContrato('fecha_rp', 'Fecha del RP', <input type="date" {...controlContrato('fecha_rp')} />)}
                                                </div>
                                                <div className="form-row">
                                                    {campoContrato('supervisor', 'Supervisor', <input type="text" maxLength={255} {...controlContrato('supervisor')} />)}
                                                    {campoContrato('interventoria', 'Interventoría', <input type="text" maxLength={5000} {...controlContrato('interventoria')} />)}
                                                </div>
                                                <div className="form-row">
                                                    {campoContrato('avance_financiero', 'Avance Financiero', <input type="text" disabled {...controlContrato('avance_financiero')} />)}
                                                    {campoContrato('avance_fisico', 'Avance Físico', (
                                                        <div className="avance-fisico-container">
                                                            <input type="text" disabled {...controlContrato('avance_fisico')} />
                                                            <button type="button" className="btn-calcular-avance" title='Calcular Avance Físico' onClick={handleCalcularAvanceFisico}><FontAwesomeIcon icon={faCalculator} /></button>
                                                        </div>
                                                    ))}
                                                </div>
                                                <div className="form-row">
                                                    <div className="form-group anticipo-group">

                                                        <label htmlFor="anticipo" className="checkbox-label">
                                                            <input
                                                                type="checkbox"
                                                                id="anticipo"
                                                                name="anticipo"
                                                                checked={formContrato.anticipo}
                                                                onChange={handleContratoChange}
                                                            />
                                                            <span>¿Lleva anticipo?</span>
                                                        </label>

                                                        <div className="anticipo-porcentaje">
                                                            <label htmlFor="porcentaje_anticipo">% de Anticipo</label>
                                                            <input
                                                                type="number"
                                                                id="porcentaje_anticipo"
                                                                name="porcentaje_anticipo"
                                                                value={formContrato.porcentaje_anticipo}
                                                                onChange={handleContratoChange}
                                                                min="0"
                                                                max="50"
                                                                disabled={!formContrato.anticipo}
                                                                aria-invalid={Boolean(erroresContrato.porcentaje_anticipo)}
                                                                aria-describedby={erroresContrato.porcentaje_anticipo ? 'porcentaje_anticipo-error' : undefined}
                                                            />
                                                            {erroresContrato.porcentaje_anticipo && <small id="porcentaje_anticipo-error" className="ct-error-campo" role="alert">{erroresContrato.porcentaje_anticipo}</small>}
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Contenido de la pestaña de anexos */}
                                    {contratoActiveTab === 'anexos' && (
                                        <div className="contratos-modal-section">
                                            <p className="contratos-info">
                                                Gestione los anexos del contrato. Puede agregar y eliminar documentos relacionados.
                                            </p>

                                            {/* Formulario para agregar anexo */}
                                            <div className="anexo-form">
                                                <h4>Agregar Nuevo Anexo</h4>
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="descripcion_anexo">Descripción *</label>
                                                        <input
                                                            type="text"
                                                            id="descripcion_anexo"
                                                            name="descripcion"
                                                            value={formAnexo.descripcion}
                                                            onChange={handleAnexoChange}
                                                            placeholder="Descripción del anexo"
                                                        />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="archivo_anexo">Archivo *</label>
                                                        <input
                                                            type="file"
                                                            id="archivo_anexo"
                                                            name="archivo"
                                                            onChange={handleAnexoChange}
                                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                                        />
                                                    </div>
                                                    <div className="form-group">
                                                        <button
                                                            type="button"
                                                            onClick={handleAddAnexo}
                                                            className="btn-add-anexo"
                                                            disabled={!formAnexo.descripcion || !formAnexo.archivo}
                                                        >
                                                            <FontAwesomeIcon icon={faPlus} /> Agregar Anexo
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Lista de anexos */}
                                            <div className="anexos-list">
                                                <h4>Anexos del Contrato ({anexos.length})</h4>
                                                {anexos.length === 0 ? (
                                                    <div className="no-anexos">
                                                        <p>No hay anexos agregados para este contrato</p>
                                                    </div>
                                                ) : (
                                                    <div className="anexos-table">
                                                        <table>
                                                            <thead>
                                                                <tr>
                                                                    <th>Descripción</th>
                                                                    <th>Archivo</th>
                                                                    <th>Fecha</th>
                                                                    <th>Acciones</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {anexos.map(anexo => (
                                                                    <tr key={anexo.id}>
                                                                        <td>{anexo.descripcion}</td>
                                                                        <td>{anexo.nombreArchivo}</td>
                                                                        <td>{anexo.fecha}</td>
                                                                        <td>
                                                                            <div className="anexo-actions">
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => window.open('/' + anexo.rutaArchivo, '_blank')}
                                                                                    className="btn-view-anexo"
                                                                                    title="Ver anexo"
                                                                                >
                                                                                    <FontAwesomeIcon icon={faEye} />
                                                                                </button>
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => handleDeleteAnexo(anexo.id)}
                                                                                    className="btn-delete-anexo"
                                                                                    title="Eliminar anexo"
                                                                                >
                                                                                    <FontAwesomeIcon icon={faTrash} />
                                                                                </button>
                                                                            </div>
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
                                                        <input
                                                            type="text"
                                                            id="valor_facturado"
                                                            name="valor_facturado"
                                                            value={formActaFinanciera.valor_facturado}
                                                            onChange={handleChangeValorFacturado}
                                                            inputMode="decimal"
                                                            placeholder="$ 0"
                                                        />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="amortizacion_50">
                                                            Amortización ({formContrato.anticipo ? `${formContrato.porcentaje_anticipo || 0}%` : '0%'})
                                                        </label>
                                                        <input type="text"
                                                            id="amortizacion_50"
                                                            name="amortizacion_50"
                                                            value={formActaFinanciera.amortizacion_50 !== '' ? formatCurrency(formActaFinanciera.amortizacion_50) : ''}
                                                            disabled
                                                            placeholder="$ 0"
                                                        />
                                                    </div>
                                                </div>
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="valor_presente_acta">Valor Presente del Acta</label>
                                                        <input type="text"
                                                            id="valor_presente_acta"
                                                            name="valor_presente_acta"
                                                            value={formActaFinanciera.valor_presente_acta !== '' ? formatCurrency(formActaFinanciera.valor_presente_acta) : ''}
                                                            disabled
                                                            placeholder="$ 0"
                                                        />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="porcentaje_ejecutado">% Ejecutado</label>
                                                        <input type="text" id="porcentaje_ejecutado" name="porcentaje_ejecutado" value={formActaFinanciera.porcentaje_ejecutado !== '' ? `${formActaFinanciera.porcentaje_ejecutado}%` : ''} disabled placeholder="0%" />
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
                                                                        <td>{formatCurrency(Number(acta.valor_facturado || 0))}</td>
                                                                        <td>{formatCurrency(Number(acta.valor_presente_acta || 0))}</td>
                                                                        <td>{acta.porcentaje_ejecutado ? `${acta.porcentaje_ejecutado}%` : '-'}</td>
                                                                        <td>
                                                                            {acta.anexo ? (
                                                                                <button type="button" onClick={() => window.open('/' + acta.anexo, '_blank')} className="btn-view-anexo" title="Ver archivo">
                                                                                    <FontAwesomeIcon icon={faEye} />
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
                                                {(() => {
                                                    const sumaPesos = actividades.reduce((s, a) => s + (a.peso || 0), 0);
                                                    return actividades.length === 0 ? (
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
                                                                <span className={`avance-fisico-suma-pesos ${sumaPesos !== 100 ? 'advertencia' : ''}`}>
                                                                    Suma de pesos: {sumaPesos}%
                                                                </span>
                                                                <span className="avance-fisico-total">
                                                                    Avance físico total: {Math.round(actividades.reduce((s, a) => s + ((a.peso || 0) / 100) * (a.ultimo_avance || 0), 0))}%
                                                                </span>
                                                            </div>
                                                        </>
                                                    );
                                                })()}
                                            </div>
                                        </div>
                                    )}

                                    {/* Contenido de la pestaña de modificaciones (otrosíes) */}
                                    {contratoActiveTab === 'modificaciones' && (
                                        <div className="contratos-modal-section">
                                            <p className="contratos-info">
                                                Registre las adiciones y prórrogas (otrosíes) del contrato. El valor y la fecha fin vigentes se recalculan automáticamente desde la base inicial más el historial de modificaciones.
                                            </p>

                                            {resumenModificacion && (
                                                <div className="anexo-form">
                                                    <h4>Resumen recalculado</h4>
                                                    <div className="form-row">
                                                        <div className="form-group">
                                                            <label>Valor inicial</label>
                                                            <span>{formatCurrency(Number(resumenModificacion.valor_inicial || 0))}</span>
                                                        </div>
                                                        <div className="form-group">
                                                            <label>Valor vigente</label>
                                                            <span>{formatCurrency(Number(resumenModificacion.valor_vigente || 0))}</span>
                                                        </div>
                                                        <div className="form-group">
                                                            <label>Fecha fin inicial</label>
                                                            <span>{resumenModificacion.fecha_fin_inicial || '-'}</span>
                                                        </div>
                                                        <div className="form-group">
                                                            <label>Fecha fin vigente</label>
                                                            <span>{resumenModificacion.fecha_fin_vigente || '-'}</span>
                                                        </div>
                                                        <div className="form-group">
                                                            <label>% Adicionado</label>
                                                            <span className={resumenModificacion.supera_limite ? 'texto-alerta' : ''}>
                                                                {resumenModificacion.porcentaje_adicionado}%
                                                            </span>
                                                        </div>
                                                    </div>
                                                    {resumenModificacion.supera_limite && (
                                                        <p className="texto-alerta">
                                                            La suma de adiciones supera el 50% legal del valor inicial.
                                                        </p>
                                                    )}
                                                </div>
                                            )}

                                            <div className="anexo-form">
                                                <h4>Registrar Nueva Modificación</h4>
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <label htmlFor="numero_otrosi">N° Otrosí</label>
                                                        <input type="text" id="numero_otrosi" name="numero_otrosi" value={formModificacion.numero_otrosi} onChange={handleModificacionChange} placeholder="Ej: Otrosí No. 1" />
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="tipo_modificacion">Tipo *</label>
                                                        <select id="tipo_modificacion" name="tipo" value={formModificacion.tipo} onChange={handleModificacionChange}>
                                                            <option value="adicion">Adición</option>
                                                            <option value="prorroga">Prórroga</option>
                                                            <option value="adicion_prorroga">Adición y Prórroga</option>
                                                        </select>
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="fecha_modificacion">Fecha *</label>
                                                        <input type="date" id="fecha_modificacion" name="fecha_modificacion" value={formModificacion.fecha_modificacion} onChange={handleModificacionChange} />
                                                    </div>
                                                </div>
                                                <div className="form-row">
                                                    {formModificacion.tipo !== 'prorroga' && (
                                                        <div className="form-group">
                                                            <label htmlFor="valor_adicion">Valor de la adición</label>
                                                            <input type="text" id="valor_adicion" name="valor_adicion" value={formModificacion.valor_adicion} onChange={handleModificacionChange} placeholder="$ 0,00" />
                                                        </div>
                                                    )}
                                                    {formModificacion.tipo !== 'adicion' && (
                                                        <div className="form-group">
                                                            <label htmlFor="dias_prorroga">Días de prórroga</label>
                                                            <input type="number" id="dias_prorroga" name="dias_prorroga" value={formModificacion.dias_prorroga} onChange={handleModificacionChange} min="0" />
                                                        </div>
                                                    )}
                                                    <div className="form-group">
                                                        <label htmlFor="justificacion_modificacion">Justificación</label>
                                                        <input type="text" id="justificacion_modificacion" name="justificacion" value={formModificacion.justificacion} onChange={handleModificacionChange} placeholder="Motivo de la modificación" />
                                                    </div>
                                                </div>
                                                <div className="form-row">
                                                    <div className="form-group">
                                                        <button
                                                            type="button"
                                                            onClick={guardarModificacion}
                                                            className="btn-add-anexo"
                                                            disabled={!formContrato.id || !formModificacion.fecha_modificacion}
                                                        >
                                                            <FontAwesomeIcon icon={faSave} /> Registrar Modificación
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="anexos-list">
                                                <h4>Historial de Modificaciones ({modificaciones.length})</h4>
                                                {modificaciones.length === 0 ? (
                                                    <div className="no-anexos">
                                                        <p>No hay modificaciones registradas</p>
                                                    </div>
                                                ) : (
                                                    <div className="anexos-table">
                                                        <table>
                                                            <thead>
                                                                <tr>
                                                                    <th>N° Otrosí</th>
                                                                    <th>Tipo</th>
                                                                    <th>Valor adición</th>
                                                                    <th>Días prórroga</th>
                                                                    <th>Fecha</th>
                                                                    <th>Justificación</th>
                                                                    <th>Acciones</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {modificaciones.map(mod => (
                                                                    <tr key={mod.id}>
                                                                        <td>{mod.numero_otrosi || '-'}</td>
                                                                        <td>{mod.tipo === 'adicion' ? 'Adición' : mod.tipo === 'prorroga' ? 'Prórroga' : 'Adición y Prórroga'}</td>
                                                                        <td>{mod.valor_adicion ? formatCurrency(Number(mod.valor_adicion)) : '-'}</td>
                                                                        <td>{mod.dias_prorroga || '-'}</td>
                                                                        <td>{mod.fecha_modificacion || '-'}</td>
                                                                        <td>{mod.justificacion || '-'}</td>
                                                                        <td>
                                                                            <div className="anexo-actions">
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => eliminarModificacion(mod.id)}
                                                                                    className="btn-delete-anexo"
                                                                                    title="Eliminar modificación"
                                                                                >
                                                                                    <FontAwesomeIcon icon={faTrash} />
                                                                                </button>
                                                                            </div>
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
                                </>
                            )}
                        </div>

                        <div className="modal-actions">
                            {!showContratoForm && (
                                <button type="button" onClick={() => setShowContratosModal(false)} className="btn-cancel">
                                    <FontAwesomeIcon icon={faTimes} /> Cerrar
                                </button>
                            )}
                            {showContratoForm && (
                                <>
                                    <button type="button" onClick={handleCancelContratoForm} className="btn-cancel">
                                        <FontAwesomeIcon icon={faTimes} /> Cancelar
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleSaveContrato}
                                        className="btn-save"
                                        disabled={guardandoContrato}
                                    >
                                        <FontAwesomeIcon icon={faSave} /> {guardandoContrato ? 'Guardando…' : `${editingContrato ? 'Actualizar' : 'Guardar'} Contrato`}
                                    </button>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {proyectoProcesos && (
```

por:

```jsx
            )}

            {/* Modal para gestión de contratos */}
            {proyectoContratos && (
                <ContratosProyecto
                    key={proyectoContratos.id}
                    proyecto={proyectoContratos}
                    onCerrar={() => setProyectoContratos(null)}
                    onCambio={listProyectos}
                />
            )}

            {proyectoProcesos && (
```

En `resources/js/utils/contratos.js`, reemplazar (1 de 2):

```js
// Lógica de presentación del formulario de contratos: estados, formulario, lo que se envía a
// /guardarContrato, el proceso del contrato y su precarga, y los valores vigentes.
// Funciones puras, probadas en contratos.test.js. Las reglas las valida el backend
// (ContratoController y ContratosService); aquí se adelantan los mismos mensajes.

import { erroresPorCampo, etiquetaEstadoProceso, etiquetaPlataforma, numeroProceso, valorDesdeApi, valorParaApi } from './contratacion';

// Los valores son las constantes de Contrato (contratos.test.js lo comprueba).
export const ESTADOS_CONTRATO = [
```

por:

```js
// Lógica de presentación del formulario de contratos: estados, formulario, lo que se envía a
// /guardarContrato, el proceso del contrato y su precarga, los valores vigentes y las pestañas
// del contrato (anexos, actas, actividades y modificaciones).
// Funciones puras, probadas en contratos.test.js. Las reglas las valida el backend
// (ContratoController y ContratosService); aquí se adelantan los mismos mensajes.

import { erroresPorCampo, etiquetaEstadoProceso, etiquetaPlataforma, numeroProceso, valorDesdeApi, valorParaApi } from './contratacion';
import { numeroDeMoneda } from './moneda';

// Los valores son las constantes de Contrato (contratos.test.js lo comprueba).
export const ESTADOS_CONTRATO = [
```

En `resources/js/utils/contratos.js`, reemplazar (2 de 2):

```js
    }
    return opciones;
};
```

por:

```js
    }
    return opciones;
};

// ---------- Pestañas del contrato ----------

// Formularios vacíos de las pestañas.
export const ANEXO_VACIO = { descripcion: '', archivo: null };
export const ACTA_VACIA = {
    descripcion: '', fecha_acta: '', valor_facturado: '', amortizacion_50: '',
    valor_presente_acta: '', porcentaje_ejecutado: '', archivo: null,
};
export const ACTIVIDAD_VACIA = { nombre: '', peso: '' };
export const AVANCE_VACIO = { fecha: '', porcentaje_ejecucion: '' };
export const MODIFICACION_VACIA = {
    numero_otrosi: '', tipo: 'adicion', valor_adicion: '',
    dias_prorroga: '', fecha_modificacion: '', justificacion: '',
};

/** Los anexos del contrato del listado (`nombre_archivo`, `ruta_archivo`) como los usa la pestaña. */
export const anexosDelContrato = (contrato) => (contrato?.anexos?.length
    ? contrato.anexos.map((anexo) => ({
        id: anexo.id,
        descripcion: anexo.descripcion,
        nombreArchivo: anexo.nombre_archivo,
        rutaArchivo: anexo.ruta_archivo,
        fecha: anexo.fecha,
    }))
    : []);

/** Los anexos como los espera `anexos` de /guardarContrato. */
export const anexosParaGuardar = (anexos) => anexos.map((anexo) => ({
    descripcion: anexo.descripcion,
    nombreArchivo: anexo.nombreArchivo,
    rutaArchivo: anexo.rutaArchivo,
    fecha: anexo.fecha,
}));

/**
 * Lo que se calcula de un acta con su valor facturado: la amortización del anticipo (si el
 * contrato lo lleva), el valor presente y el porcentaje ejecutado sobre el valor vigente.
 */
export const calculoActa = (valorFacturado, formulario, contrato) => {
    const porcentajeAnticipo = parseInt(formulario.porcentaje_anticipo, 10) || 0;
    const amortizacion = formulario.anticipo ? Math.round(valorFacturado * (porcentajeAnticipo / 100) * 100) / 100 : 0;
    const valorPresente = Math.round((valorFacturado - amortizacion) * 100) / 100;
    const vigente = valorVigente(formulario, contrato);
    const porcentajeEjecutado = vigente ? Math.round((valorFacturado / vigente) * 100 * 100) / 100 : 0;
    return { amortizacion, valorPresente, porcentajeEjecutado };
};

/** Lo que espera /guardarActaFinanciera. */
export const datosActa = (contratoId, formulario, rutaAnexo) => ({
    contrato_id: contratoId,
    descripcion: formulario.descripcion,
    fecha_acta: formulario.fecha_acta,
    valor_facturado: numeroDeMoneda(formulario.valor_facturado),
    amortizacion_50: formulario.amortizacion_50,
    valor_presente_acta: formulario.valor_presente_acta,
    porcentaje_ejecutado: formulario.porcentaje_ejecutado,
    anexo: rutaAnexo,
});

/**
 * Avance financiero: lo facturado en todas las actas sobre el valor vigente, con dos decimales;
 * sin actas o sin valor vigente, vacío.
 */
export const avanceFinancieroDeActas = (actas, vigente) => {
    if (actas.length === 0 || !vigente) return '';
    const totalFacturado = actas.reduce((suma, acta) => suma + (parseFloat(acta.valor_facturado) || 0), 0);
    return Math.round((totalFacturado / vigente) * 100 * 100) / 100;
};

/** La suma de los pesos de las actividades (deben sumar 100). */
export const sumaPesos = (actividades) => actividades.reduce((suma, a) => suma + (a.peso || 0), 0);

/** Avance físico: la suma ponderada (peso × último % de ejecución) de las actividades, redondeada. */
export const avanceFisicoDeActividades = (actividades) => Math.round(
    actividades.reduce((suma, a) => suma + ((a.peso || 0) / 100) * (a.ultimo_avance || 0), 0),
);

/**
 * La actividad tras registrarle un avance: si es el más reciente (o el primero), pasa a ser su
 * último avance; uno de una fecha anterior queda solo en el historial.
 */
export const actividadConAvance = (actividad, fecha, porcentaje) => {
    const esMasReciente = !actividad.fecha_ultimo_avance || fecha >= actividad.fecha_ultimo_avance;
    return esMasReciente
        ? { ...actividad, ultimo_avance: parseInt(porcentaje, 10), fecha_ultimo_avance: fecha }
        : actividad;
};

/** Lo que espera /guardarModificacionContrato: una prórroga no lleva valor, ni una adición días. */
export const datosModificacion = (contratoId, formulario) => ({
    contrato_id: contratoId,
    numero_otrosi: formulario.numero_otrosi,
    tipo: formulario.tipo,
    valor_adicion: formulario.tipo === 'prorroga' ? null : numeroDeMoneda(formulario.valor_adicion) || null,
    dias_prorroga: formulario.tipo === 'adicion' ? null : (formulario.dias_prorroga || null),
    fecha_modificacion: formulario.fecha_modificacion,
    justificacion: formulario.justificacion,
});

/** La etiqueta del tipo de modificación. */
export const etiquetaTipoModificacion = (tipo) => {
    if (tipo === 'adicion') return 'Adición';
    if (tipo === 'prorroga') return 'Prórroga';
    return 'Adición y Prórroga';
};
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/moneda.test.js resources/js/utils/contratos.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1028 passed.

Run: `npm test`
Expected: 539 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/js/components/contratos/ContratoEditor.jsx resources/js/components/contratos/ContratosProyecto.jsx resources/js/utils/moneda.js resources/js/utils/moneda.test.js resources/js/components/Parametros.jsx resources/js/utils/contratos.js resources/js/utils/contratos.test.js
git commit -m "refactor: contract editor extracted from the parameters screen"
```

---

### Tarea 4: Módulo Contratos y navegación

Lista con filtros en la URL, nuevo contrato con proyecto opcional, abrir un contrato con toda su ejecución, solo lectura para lectores, y los destinos "Contratos" y "Avance de contratos".

**Files:**
- Create: `resources/js/components/contratos/ContratosModulo.jsx`
- Create: `resources/js/utils/contratosModulo.js`
- Test (create): `resources/js/utils/contratosModulo.test.js`
- Modify: `resources/css/Contratacion.css`
- Modify: `resources/js/app.jsx`
- Modify: `resources/js/components/Dashboard.jsx`
- Modify: `resources/js/components/contratos/ContratoEditor.jsx`
- Modify: `resources/js/utils/contratos.js`
- Test: `resources/js/utils/contratos.test.js`
- Modify: `resources/js/utils/navegacion.js`
- Test: `resources/js/utils/navegacion.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `resources/js/utils/contratosModulo.test.js`:

```js
import { describe, it, expect } from 'vitest';
import {
    SIN_PROYECTO, consultaDeFiltros, esIdContrato, filaContrato, filtrosDeConsulta, hayFiltros, opcionesFiltroEstado, opcionesFiltroProyecto,
    opcionesProyecto, parametrosBusqueda, puedeEditarContratos, rutaContrato, rutaListaDesde, textoAvance, textoListaVacia,
    tonoEstadoContrato,
} from './contratosModulo';
import { ESTADOS_CONTRATO } from './contratos';
import { RUTA_CONTRATOS } from './navegacion';

const consulta = (texto) => new URLSearchParams(texto);
// Intl separa el símbolo con un espacio duro: se compara con espacios normales.
const normal = (texto) => texto.replace(/\s/g, ' ');

describe('filtrosDeConsulta', () => {
    it('lee la búsqueda, el estado y el proyecto de la URL', () => {
        expect(filtrosDeConsulta(consulta('q=puente&estado=en_ejecucion&proyecto=7')))
            .toEqual({ q: 'puente', estado: 'en_ejecucion', proyecto: '7' });
        expect(filtrosDeConsulta(consulta('proyecto=sin'))).toEqual({ q: '', estado: '', proyecto: SIN_PROYECTO });
    });

    it('sin parámetros, sin filtros', () => {
        expect(filtrosDeConsulta(consulta(''))).toEqual({ q: '', estado: '', proyecto: '' });
        expect(filtrosDeConsulta(undefined)).toEqual({ q: '', estado: '', proyecto: '' });
    });

    it('descarta lo que el servidor rechazaría', () => {
        expect(filtrosDeConsulta(consulta('estado=borrador&proyecto=abc'))).toEqual({ q: '', estado: '', proyecto: '' });
        expect(filtrosDeConsulta(consulta('proyecto=0')).proyecto).toBe('');
        expect(filtrosDeConsulta(consulta('proyecto=-3')).proyecto).toBe('');
    });

    it('recorta la búsqueda a 100 caracteres sin espacios sobrantes', () => {
        expect(filtrosDeConsulta(consulta('q=%20%20obra%20%20')).q).toBe('obra');
        expect(filtrosDeConsulta(consulta(`q=${'a'.repeat(150)}`)).q).toHaveLength(100);
    });
});

describe('consultaDeFiltros', () => {
    it('escribe solo los filtros con valor', () => {
        expect(consultaDeFiltros({ q: 'vía', estado: '', proyecto: 'sin' }).toString()).toBe('q=v%C3%ADa&proyecto=sin');
        expect(consultaDeFiltros({ q: '', estado: '', proyecto: '' }).toString()).toBe('');
    });

    it('ida y vuelta con filtrosDeConsulta', () => {
        const filtros = { q: 'acueducto', estado: 'suspendido', proyecto: '12' };
        expect(filtrosDeConsulta(consultaDeFiltros(filtros))).toEqual(filtros);
    });

    it('recorta la búsqueda antes de escribirla', () => {
        expect(consultaDeFiltros({ q: '  obra ', estado: '', proyecto: '' }).get('q')).toBe('obra');
        expect(consultaDeFiltros({ q: '   ', estado: '', proyecto: '' }).has('q')).toBe(false);
    });
});

describe('parametrosBusqueda', () => {
    it('lo que se envía a GET /contratos/buscar: sin los filtros vacíos', () => {
        expect(parametrosBusqueda({ q: 'obra', estado: '', proyecto: 'sin' })).toEqual({ q: 'obra', proyecto: 'sin' });
        expect(parametrosBusqueda({ q: '', estado: '', proyecto: '' })).toEqual({});
    });
});

describe('hayFiltros y textoListaVacia', () => {
    it('distingue una lista vacía de una búsqueda sin resultados', () => {
        expect(hayFiltros({ q: '', estado: '', proyecto: '' })).toBe(false);
        expect(hayFiltros({ q: '', estado: 'anulado', proyecto: '' })).toBe(true);
        expect(textoListaVacia({ q: '', estado: '', proyecto: '' })).toBe('Aún no hay contratos registrados.');
        expect(textoListaVacia({ q: 'x', estado: '', proyecto: '' })).toBe('No hay contratos que coincidan con los filtros.');
    });
});

describe('opcionesFiltroEstado', () => {
    it('"Todos los estados" y luego cada estado de contrato', () => {
        const opciones = opcionesFiltroEstado();
        expect(opciones[0]).toEqual({ valor: '', etiqueta: 'Todos los estados' });
        expect(opciones.slice(1).map((o) => o.valor)).toEqual(ESTADOS_CONTRATO.map(([v]) => v));
        expect(opciones.find((o) => o.valor === 'en_ejecucion').etiqueta).toBe('En ejecución');
    });
});

const proyectos = [
    { id: 3, nombre: 'Vía terciaria' },
    { id: 1, nombre: 'Acueducto veredal' },
    { id: 2, nombre: 'Escuela rural' },
];

describe('opcionesFiltroProyecto', () => {
    it('"Todos", "Sin proyecto" y los proyectos por nombre', () => {
        expect(opcionesFiltroProyecto(proyectos, '')).toEqual([
            { valor: '', etiqueta: 'Todos los proyectos' },
            { valor: 'sin', etiqueta: 'Sin proyecto' },
            { valor: '1', etiqueta: 'Acueducto veredal' },
            { valor: '2', etiqueta: 'Escuela rural' },
            { valor: '3', etiqueta: 'Vía terciaria' },
        ]);
    });

    it('conserva el proyecto de la URL aunque la lista no lo traiga (o no haya cargado)', () => {
        expect(opcionesFiltroProyecto(null, '9').map((o) => o.valor)).toEqual(['', 'sin', '9']);
        expect(opcionesFiltroProyecto(null, '9')[2].etiqueta).toBe('Proyecto 9');
        expect(opcionesFiltroProyecto(proyectos, '2').filter((o) => o.valor === '2')).toHaveLength(1);
    });
});

describe('opcionesProyecto (selector del editor)', () => {
    it('un contrato nuevo puede quedar sin proyecto', () => {
        expect(opcionesProyecto(proyectos, null).map((o) => o.valor)).toEqual(['', '1', '2', '3']);
        expect(opcionesProyecto(proyectos, null)[0]).toEqual({ valor: '', etiqueta: 'Sin proyecto' });
    });

    it('uno sin proyecto puede recibir uno', () => {
        expect(opcionesProyecto(proyectos, { id: 5, proyecto: null }).map((o) => o.valor)).toEqual(['', '1', '2', '3']);
    });

    it('uno con proyecto puede cambiarlo, pero no quitarlo', () => {
        expect(opcionesProyecto(proyectos, { id: 5, proyecto: 2 }).map((o) => o.valor)).toEqual(['1', '2', '3']);
    });

    it('ofrece el proyecto del contrato aunque la lista no lo traiga', () => {
        const contrato = { id: 5, proyecto: 8, proyecto_nombre: 'Puente colgante' };
        expect(opcionesProyecto(null, contrato)).toEqual([{ valor: '8', etiqueta: 'Puente colgante' }]);
        expect(opcionesProyecto([], { id: 5, proyecto: 8 })).toEqual([{ valor: '8', etiqueta: 'Proyecto 8' }]);
    });
});

describe('filaContrato', () => {
    const contrato = {
        id: 4,
        n_contrato: 'CO-001-2026',
        objeto: 'Construcción de placa huella',
        contratista: 'Consorcio Vías',
        estado: 'en_ejecucion',
        valor: '1500000.50',
        valor_inicial: '1000000.00',
        avance_fisico: 45,
        avance_financiero: 0,
        proyecto: { id: 2, nombre: 'Escuela rural' },
        proceso: { id: 9, codigo_proceso: 'LP-003-2026' },
    };

    it('lo que muestra la lista de un contrato', () => {
        const fila = filaContrato(contrato);
        expect({ ...fila, valor: normal(fila.valor) }).toEqual({
            id: 4,
            numero: 'CO-001-2026',
            objeto: 'Construcción de placa huella',
            contratista: 'Consorcio Vías',
            proyecto: 'Escuela rural',
            sinProyecto: false,
            proceso: 'LP-003-2026',
            estado: 'En ejecución',
            tono: 'verde',
            valor: '$ 1.500.000,50',
            avanceFisico: '45%',
            avanceFinanciero: '0%',
        });
    });

    it('sin proyecto, sin proceso, sin avances ni datos opcionales', () => {
        const fila = filaContrato({ id: 5, n_contrato: '', objeto: 'Suministro', contratista: null, estado: 'celebrado', valor: null, avance_fisico: null, proyecto: null, proceso: null });
        expect(fila).toMatchObject({
            numero: 'Sin número', contratista: '—', proyecto: 'Sin proyecto', sinProyecto: true, proceso: '—',
            valor: '—', avanceFisico: '—', avanceFinanciero: '—',
        });
    });

    it('un proceso sin código se nombra', () => {
        expect(filaContrato({ ...contrato, proceso: { id: 9, codigo_proceso: '  ' } }).proceso).toBe('Sin número');
    });

    // El valor de la fila es el vigente (con adiciones), no el inicial.
    it('muestra el valor vigente', () => {
        expect(normal(filaContrato({ ...contrato, valor: '2000000.00' }).valor)).toBe('$ 2.000.000,00');
    });
});

describe('textoAvance', () => {
    it('un porcentaje o una raya', () => {
        expect(textoAvance(30)).toBe('30%');
        expect(textoAvance(0)).toBe('0%');
        expect(textoAvance(null)).toBe('—');
        expect(textoAvance(undefined)).toBe('—');
    });
});

describe('tonoEstadoContrato', () => {
    it('cada estado tiene tono; uno desconocido es neutro', () => {
        ESTADOS_CONTRATO.forEach(([v]) => expect(tonoEstadoContrato(v)).toMatch(/^(neutro|azul|ambar|verde|verde-pleno|coral)$/));
        expect(tonoEstadoContrato('suspendido')).toBe('ambar');
        expect(tonoEstadoContrato('anulado')).toBe('coral');
        expect(tonoEstadoContrato('otro')).toBe('neutro');
    });
});

describe('rutas', () => {
    it('la de un contrato y la de volver a la lista con sus filtros', () => {
        expect(RUTA_CONTRATOS).toBe('/contratos');
        expect(rutaContrato(4)).toBe('/contratos/4');
        expect(rutaListaDesde({ lista: '?estado=anulado' })).toBe('/contratos?estado=anulado');
        expect(rutaListaDesde({ lista: 'https://otro' })).toBe('/contratos');
        expect(rutaListaDesde(null)).toBe('/contratos');
    });
});

describe('esIdContrato', () => {
    it('solo un entero positivo es el id de un contrato', () => {
        expect(esIdContrato('12')).toBe(true);
        expect(esIdContrato(3)).toBe(true);
        ['0', '-1', '1.5', 'abc', '', undefined, null, '12x', 'nuevo'].forEach((v) => expect(esIdContrato(v)).toBe(false));
    });

    it('rechaza un id de más de 18 cifras o mayor que el entero seguro, sin llamar a la API', () => {
        expect(esIdContrato(String(Number.MAX_SAFE_INTEGER))).toBe(true);
        expect(esIdContrato(String(Number.MAX_SAFE_INTEGER + 1))).toBe(false);
        expect(esIdContrato('99999999999999999999')).toBe(false);
        expect(esIdContrato('1234567890123456789')).toBe(false);
    });
});

describe('puedeEditarContratos', () => {
    it('Administrador o Gestor, como editar-datos', () => {
        expect(puedeEditarContratos({ rol: 'Administrador' })).toBe(true);
        expect(puedeEditarContratos({ rol: 'Gestor' })).toBe(true);
        expect(puedeEditarContratos({ rol: 'Consulta' })).toBe(false);
        expect(puedeEditarContratos(null)).toBe(false);
    });
});
```

En `resources/js/utils/contratos.test.js`, reemplazar (1 de 3):

```js
    primerCampoConError,
    ESTADOS_CONTRATO,
    aplicarPrecarga,
    contratoVacio,
    datosContrato,
    datosDelProceso,
```

por:

```js
    primerCampoConError,
    ESTADOS_CONTRATO,
    aplicarPrecarga,
    cambiarProyecto,
    contratoVacio,
    datosContrato,
    datosDelProceso,
```

En `resources/js/utils/contratos.test.js`, reemplazar (2 de 3):

```js
    opcionesProceso,
    opcionesTipoContrato,
    procesoElegido,
    validarContrato,
    valorVigente,
    vigentesDistintos,
```

por:

```js
    opcionesProceso,
    opcionesTipoContrato,
    procesoElegido,
    procesoDelContrato,
    validarContrato,
    valorVigente,
    vigentesDistintos,
```

En `resources/js/utils/contratos.test.js`, reemplazar (3 de 3):

```js
    });
});

describe('aplicarPrecarga', () => {
    const precarga = { objeto: 'Puente vehicular', contratante: 'Gobernación del Cesar', valor_inicial: '2500000.00' };
```

por:

```js
    });
});

describe('proyecto del contrato', () => {
    it('cambiar de proyecto quita el proceso, que era del anterior', () => {
        const nuevo = { ...contratoVacio(), proceso_id: '4', objeto: 'Obra' };
        expect(cambiarProyecto(nuevo, '9', null)).toEqual({ ...nuevo, proyecto: '9', proceso_id: '' });
        expect(cambiarProyecto({ ...nuevo, proyecto: '9' }, '', null)).toMatchObject({ proyecto: '', proceso_id: '' });
    });

    it('volver al proyecto del contrato abierto le devuelve su proceso', () => {
        const formulario = { ...formularioContrato(presentado), proyecto: '8', proceso_id: '' };
        expect(cambiarProyecto(formulario, '3', presentado)).toMatchObject({ proyecto: '3', proceso_id: '5' });
        expect(cambiarProyecto(formulario, 3, listado)).toMatchObject({ proyecto: '3', proceso_id: '5' });
    });

    it('elegir el mismo proyecto no cambia nada', () => {
        const formulario = { ...formularioContrato(presentado), proceso_id: '6' };
        expect(cambiarProyecto(formulario, '3', presentado)).toBe(formulario);
    });

    it('el proceso que el contrato ya tiene solo cuenta mientras siga en su proyecto', () => {
        expect(procesoDelContrato(presentado, formularioContrato(presentado))).toBe(presentado.proceso);
        expect(procesoDelContrato(listado, formularioContrato(listado))).toBe(listado.proceso);
        expect(procesoDelContrato(presentado, { ...formularioContrato(presentado), proyecto: '8' })).toBeNull();
        expect(procesoDelContrato(null, contratoVacio(3))).toBeNull();
        expect(procesoDelContrato({ ...presentado, proceso: null }, formularioContrato(presentado))).toBeNull();
    });
});

describe('aplicarPrecarga', () => {
    const precarga = { objeto: 'Puente vehicular', contratante: 'Gobernación del Cesar', valor_inicial: '2500000.00' };
```

En `resources/js/utils/navegacion.test.js`, reemplazar (1 de 5):

```js
import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import { DESTINOS, GRUPO, RUTA_PLANTILLAS, SECCIONES_PARAMETROS, buscarEnPaleta, destinoActivo, destinosPara, puedeVer, seccionDesdeSlug, tituloDeRuta } from './navegacion';
import { ICONOS } from './iconosNavegacion';

const proyectos = [
```

por:

```js
import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import { DESTINOS, GRUPO, RUTA_CONTRATOS, RUTA_PLANTILLAS, SECCIONES_PARAMETROS, buscarEnPaleta, destinoActivo, destinosPara, puedeVer, seccionDesdeSlug, tituloDeRuta } from './navegacion';
import { ICONOS } from './iconosNavegacion';

const proyectos = [
```

En `resources/js/utils/navegacion.test.js`, reemplazar (2 de 5):

```js
        });
    });

    it('no repite identificadores ni rutas', () => {
        expect(new Set(DESTINOS.map((d) => d.id)).size).toBe(DESTINOS.length);
        expect(new Set(DESTINOS.map((d) => d.ruta)).size).toBe(DESTINOS.length);
```

por:

```js
        });
    });

    it('Contratos es trabajo diario y su avance es análisis, con otro nombre', () => {
        expect(DESTINOS.find((d) => d.id === 'contratos')).toMatchObject({ titulo: 'Contratos', ruta: '/contratos', grupo: GRUPO.TRABAJO });
        expect(DESTINOS.find((d) => d.id === 'avance-contratos')).toMatchObject({ titulo: 'Avance de contratos', ruta: '/dashboard?vista=contratos', grupo: GRUPO.ANALISIS });
        expect(DESTINOS.filter((d) => d.titulo === 'Contratos')).toHaveLength(1);
    });

    it('Contratos va después de Expedientes, antes del análisis', () => {
        const ids = DESTINOS.map((d) => d.id);
        expect(ids.indexOf('contratos')).toBe(ids.indexOf('expedientes') + 1);
        expect(DESTINOS[ids.indexOf('contratos') + 1].grupo).toBe(GRUPO.ANALISIS);
    });

    it('cada destino tiene un icono registrado', () => {
        DESTINOS.forEach((d) => expect(ICONOS[d.icono]).toBeDefined());
    });

    it('el avance de contratos no repite el icono de Contratos', () => {
        const icono = (id) => DESTINOS.find((d) => d.id === id).icono;
        expect(icono('avance-contratos')).not.toBe(icono('contratos'));
    });

    it('no repite identificadores ni rutas', () => {
        expect(new Set(DESTINOS.map((d) => d.id)).size).toBe(DESTINOS.length);
        expect(new Set(DESTINOS.map((d) => d.ruta)).size).toBe(DESTINOS.length);
```

En `resources/js/utils/navegacion.test.js`, reemplazar (3 de 5):

```js
        expect(titulos('interventoria')).toContain('Tipos de contrato');
    });

    it('encuentra proyectos por nombre o municipio y los deja después de los destinos', () => {
        const porNombre = buscarEnPaleta('construccion', DESTINOS, proyectos);
        expect(porNombre.at(-1)).toMatchObject({ tipo: 'proyecto', titulo: 'Construcción sede educativa', ruta: '/dashboard?proyecto=1' });
```

por:

```js
        expect(titulos('interventoria')).toContain('Tipos de contrato');
    });

    it('"contratos" lleva al módulo y al avance; "contratista" o "avance", a cada uno', () => {
        const claves = (q) => buscarEnPaleta(q, DESTINOS, []).map((r) => r.clave);

        expect(claves('contratos')).toEqual(expect.arrayContaining(['contratos', 'avance-contratos']));
        expect(claves('contratista')).toContain('contratos');
        expect(claves('otrosí')).toContain('contratos');
        expect(claves('desfase')).toEqual(['avance-contratos']);
        expect(buscarEnPaleta('contratos', DESTINOS, []).find((r) => r.clave === 'contratos')).toMatchObject({ titulo: 'Contratos', ruta: '/contratos', detalle: GRUPO.TRABAJO });
    });

    it('encuentra proyectos por nombre o municipio y los deja después de los destinos', () => {
        const porNombre = buscarEnPaleta('construccion', DESTINOS, proyectos);
        expect(porNombre.at(-1)).toMatchObject({ tipo: 'proyecto', titulo: 'Construcción sede educativa', ruta: '/dashboard?proyecto=1' });
```

En `resources/js/utils/navegacion.test.js`, reemplazar (4 de 5):

```js
    it('distingue las vistas analíticas del tablero', () => {
        expect(destinoActivo('/dashboard', '')).toBe('tablero');
        expect(destinoActivo('/dashboard', '?vista=formulacion')).toBe('tablero');
        expect(destinoActivo('/dashboard', '?vista=contratos')).toBe('contratos');
        expect(destinoActivo('/dashboard', '?vista=expedientes')).toBe('expedientes');
    });

    it('un expediente pertenece a Expedientes', () => {
        expect(destinoActivo('/viabilizacion/4', '')).toBe('expedientes');
    });
```

por:

```js
    it('distingue las vistas analíticas del tablero', () => {
        expect(destinoActivo('/dashboard', '')).toBe('tablero');
        expect(destinoActivo('/dashboard', '?vista=formulacion')).toBe('tablero');
        expect(destinoActivo('/dashboard', '?vista=contratos')).toBe('avance-contratos');
        expect(destinoActivo('/dashboard', '?vista=expedientes')).toBe('expedientes');
    });

    it('la lista de contratos, uno nuevo y uno abierto marcan Contratos', () => {
        expect(RUTA_CONTRATOS).toBe('/contratos');
        expect(destinoActivo('/contratos', '')).toBe('contratos');
        expect(destinoActivo('/contratos', '?estado=anulado')).toBe('contratos');
        expect(destinoActivo('/contratos/nuevo', '')).toBe('contratos');
        expect(destinoActivo('/contratos/12', '')).toBe('contratos');
        expect(destinoActivo('/contratosx', '')).toBeNull();
    });

    it('un expediente pertenece a Expedientes', () => {
        expect(destinoActivo('/viabilizacion/4', '')).toBe('expedientes');
    });
```

En `resources/js/utils/navegacion.test.js`, reemplazar (5 de 5):

```js
        expect(tituloDeRuta('/parametros/etapas-contratacion', '')).toBe('Etapas de contratación');
        expect(tituloDeRuta('/dashboard', '')).toBe('Tablero');
        expect(tituloDeRuta('/viabilizacion/4', '')).toBe('Expedientes');
        expect(tituloDeRuta('/otra', '')).toBe('GestPro');
    });
});
```

por:

```js
        expect(tituloDeRuta('/parametros/etapas-contratacion', '')).toBe('Etapas de contratación');
        expect(tituloDeRuta('/dashboard', '')).toBe('Tablero');
        expect(tituloDeRuta('/viabilizacion/4', '')).toBe('Expedientes');
        expect(tituloDeRuta('/contratos/12', '')).toBe('Contratos');
        expect(tituloDeRuta('/dashboard', '?vista=contratos')).toBe('Avance de contratos');
        expect(tituloDeRuta('/otra', '')).toBe('GestPro');
    });
});
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/contratosModulo.test.js resources/js/utils/contratos.test.js resources/js/utils/navegacion.test.js`
Expected: FALLAN los 3 archivos (10 pruebas y la carga de `contratosModulo.test.js`): no existen las utilidades del módulo ni `cambiarProyecto`, y la navegación no tiene el destino "Contratos" ni "Avance de contratos".

- [ ] **Paso 3: Implementar**

Crear `resources/js/components/contratos/ContratosModulo.jsx`:

```jsx
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, Navigate, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { faArrowLeft, faPlus } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../../axios';
import {
    consultaDeFiltros, esIdContrato, filaContrato, filtrosDeConsulta, opcionesFiltroEstado, opcionesFiltroProyecto, parametrosBusqueda,
    puedeEditarContratos, rutaContrato, rutaListaDesde, textoListaVacia,
} from '../../utils/contratosModulo';
import { RUTA_CONTRATOS } from '../../utils/navegacion';
import { mensajeApi } from '../../utils/plantillasChecklist';
import ContratoEditor from './ContratoEditor';
import '../../../css/Plantillas.css';
import '../../../css/Parametros.css';
import '../../../css/Contratacion.css';

const RUTA_NUEVO = `${RUTA_CONTRATOS}/nuevo`;

/** El contrato completo (GET /contratos/{id}/detalle), con la forma que espera ContratoEditor. */
const pedirDetalle = async (id) => (await axios.get(`/contratos/${id}/detalle`)).data;

/** El `recargar` del editor: el contrato `id` recargado, o null (sin id o si falla). */
const recargarDetalle = async (id) => {
    if (!id) return null;
    try {
        return await pedirDetalle(id);
    } catch (e) {
        return null;
    }
};

/**
 * Los proyectos para el filtro y el selector de proyecto (GET /proyectos, solo id y nombre):
 * `lista` es null mientras cargan; si fallan, `error` y `reintentar`.
 */
const useProyectos = () => {
    const [lista, setLista] = useState(null);
    const [error, setError] = useState(null);
    const cargar = useCallback(async () => {
        setError(null);
        try {
            const { data } = await axios.get('/proyectos');
            setLista((Array.isArray(data) ? data : []).map(({ id, nombre }) => ({ id, nombre })));
        } catch (e) {
            setError(mensajeApi(e));
        }
    }, []);
    useEffect(() => { cargar(); }, [cargar]);
    return { lista, error, reintentar: cargar };
};

const AvisoProyectos = ({ proyectos }) => (proyectos.error ? (
    <p className="pl-error" role="alert">
        No se pudieron cargar los proyectos: {proyectos.error}{' '}
        <button type="button" className="gp-boton" onClick={proyectos.reintentar}>Reintentar</button>
    </p>
) : null);

const Volver = ({ a }) => (
    <Link to={a} className="pl-volver"><FontAwesomeIcon icon={faArrowLeft} /> Contratos</Link>
);

/** Lista de todos los contratos, con o sin proyecto. Los filtros viven en la URL. */
const ListaContratos = ({ edita, proyectos }) => {
    const { search } = useLocation();
    const [consulta, setConsulta] = useSearchParams();
    const filtros = useMemo(() => filtrosDeConsulta(consulta), [consulta]);
    const clave = consultaDeFiltros(filtros).toString();
    const [texto, setTexto] = useState(filtros.q);
    const [contratos, setContratos] = useState(null);
    const [error, setError] = useState(null);
    const peticion = useRef(0);

    // Reemplaza la entrada del historial: filtrar no es una página nueva.
    const filtrar = useCallback((cambios) => {
        setConsulta(consultaDeFiltros({ ...filtrosDeConsulta(consulta), ...cambios }), { replace: true });
    }, [consulta, setConsulta]);

    // La búsqueda llega a la URL cuando se deja de escribir; si la URL cambia (al volver), el campo la sigue.
    useEffect(() => {
        if (texto.trim() === filtros.q) return undefined;
        const temporizador = setTimeout(() => filtrar({ q: texto }), 300);
        return () => clearTimeout(temporizador);
    }, [texto, filtros.q, filtrar]);
    useEffect(() => {
        setTexto((actual) => (actual.trim() === filtros.q ? actual : filtros.q));
    }, [filtros.q]);

    // Solo vale la respuesta de la última petición.
    const cargar = useCallback(async () => {
        const numero = ++peticion.current;
        setError(null);
        try {
            const params = parametrosBusqueda(filtrosDeConsulta(new URLSearchParams(clave)));
            const { data } = await axios.get('/contratos/buscar', { params });
            if (numero === peticion.current) setContratos(Array.isArray(data) ? data : []);
        } catch (e) {
            if (numero === peticion.current) setError(mensajeApi(e));
        }
    }, [clave]);
    useEffect(() => { cargar(); }, [cargar]);

    const filas = useMemo(() => (contratos ?? []).map(filaContrato), [contratos]);
    // Al abrir un contrato se lleva la lista con sus filtros, para volver a ella.
    const estadoNavegacion = { lista: search };

    return (
        <div className="pl-pagina">
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Trabajo diario</span>
                <h1>Contratos</h1>
                <p>Todos los contratos, con o sin proyecto: su estado, su valor vigente y su avance.</p>
            </header>

            <AvisoProyectos proyectos={proyectos} />

            <div className="pl-barra">
                <input
                    type="search"
                    className="pl-buscar"
                    placeholder="Buscar por número, objeto, contratista, contratante o NIT"
                    aria-label="Buscar contratos"
                    maxLength={100}
                    value={texto}
                    onChange={(e) => setTexto(e.target.value)}
                />
                <select className="pl-buscar ct-filtro" aria-label="Estado" value={filtros.estado} onChange={(e) => filtrar({ estado: e.target.value })}>
                    {opcionesFiltroEstado().map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                </select>
                <select className="pl-buscar ct-filtro" aria-label="Proyecto" value={filtros.proyecto} onChange={(e) => filtrar({ proyecto: e.target.value })}>
                    {opcionesFiltroProyecto(proyectos.lista, filtros.proyecto).map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                </select>
                {edita && (
                    <Link to={RUTA_NUEVO} state={estadoNavegacion} className="gp-boton gp-boton-primario">
                        <FontAwesomeIcon icon={faPlus} /> Nuevo contrato
                    </Link>
                )}
            </div>

            {error && (
                <p className="pl-error" role="alert">
                    {error} <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>
                </p>
            )}
            {contratos === null && !error && <p className="gp-vacio" role="status">Cargando contratos…</p>}
            {contratos !== null && !error && filas.length === 0 && <p className="gp-vacio">{textoListaVacia(filtros)}</p>}

            {/* Con un error no se muestra la tabla del filtro anterior: no es lo que se buscó. */}
            {!error && filas.length > 0 && (
                <div className="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Objeto</th>
                                <th>Contratista</th>
                                <th>Proyecto</th>
                                <th>Proceso</th>
                                <th>Estado</th>
                                <th>Valor vigente</th>
                                <th>Físico</th>
                                <th>Financiero</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filas.map((f) => (
                                <tr key={f.id}>
                                    <td data-etiqueta="Número">
                                        <Link to={rutaContrato(f.id)} state={estadoNavegacion} className="ct-enlace">{f.numero}</Link>
                                    </td>
                                    <td data-etiqueta="Objeto"><span className="ct-objeto" title={f.objeto}>{f.objeto}</span></td>
                                    <td data-etiqueta="Contratista">{f.contratista}</td>
                                    <td data-etiqueta="Proyecto" className={f.sinProyecto ? 'ct-sin-dato' : undefined}>{f.proyecto}</td>
                                    <td data-etiqueta="Proceso" className="numero">{f.proceso}</td>
                                    <td data-etiqueta="Estado"><span className={`ct-estado ct-estado-${f.tono}`}>{f.estado}</span></td>
                                    <td data-etiqueta="Valor vigente" className="numero">{f.valor}</td>
                                    <td data-etiqueta="Físico" className="numero">{f.avanceFisico}</td>
                                    <td data-etiqueta="Financiero" className="numero">{f.avanceFinanciero}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
};

/** Un contrato nuevo, con proyecto opcional. Al guardarlo se abre, para seguir con sus pestañas. */
const NuevoContrato = ({ proyectos }) => {
    const { state } = useLocation();
    const navigate = useNavigate();
    const volver = rutaListaDesde(state);

    return (
        <div className="pl-pagina">
            <Volver a={volver} />
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Contratos</span>
                <h1>Nuevo contrato</h1>
            </header>
            <AvisoProyectos proyectos={proyectos} />
            <div className="gp-panel ct-editor">
                <ContratoEditor
                    proyectos={proyectos.lista}
                    recargar={recargarDetalle}
                    onGuardado={(guardado) => navigate(guardado?.id ? rutaContrato(guardado.id) : volver, { replace: true, state })}
                    onCancelar={() => navigate(volver)}
                />
            </div>
        </div>
    );
};

/** Un contrato existente: el editor completo. Sin permiso, se ve sin controles para modificarlo. */
const ContratoAbierto = ({ contratoId, edita, proyectos }) => {
    const { state } = useLocation();
    const navigate = useNavigate();
    const volver = rutaListaDesde(state);
    const [contrato, setContrato] = useState(null);
    // { mensaje, reintentable }: un contrato que no existe no se reintenta.
    const [error, setError] = useState(null);
    const peticion = useRef(0);

    const cargar = useCallback(async () => {
        const numero = ++peticion.current;
        setError(null);
        try {
            const datos = await pedirDetalle(contratoId);
            if (numero === peticion.current) setContrato(datos);
        } catch (e) {
            if (numero !== peticion.current) return;
            const noExiste = e?.response?.status === 404;
            setError({ mensaje: noExiste ? 'El contrato no existe.' : mensajeApi(e), reintentable: !noExiste });
        }
    }, [contratoId]);
    useEffect(() => { cargar(); }, [cargar]);

    // El editor recarga el contrato tras un cambio en sus pestañas; la cabecera también se actualiza.
    const recargar = useCallback(async (id) => {
        const datos = await recargarDetalle(id);
        if (datos && String(datos.id) === String(contratoId)) setContrato(datos);
        return datos;
    }, [contratoId]);

    if (error) {
        return (
            <div className="pl-pagina">
                <Volver a={volver} />
                <p className="pl-error" role="alert">
                    {error.mensaje}{' '}
                    {error.reintentable && <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>}
                </p>
            </div>
        );
    }
    if (!contrato) return <p className="gp-vacio" role="status">Cargando contrato…</p>;

    return (
        <div className="pl-pagina">
            <Volver a={volver} />
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Contrato · {contrato.proyecto_nombre || 'Sin proyecto'}</span>
                <h1>{contrato.n_contrato || 'Sin número'}</h1>
                {!edita && <p>Solo lectura: tu rol puede consultar el contrato, pero no modificarlo.</p>}
            </header>
            {edita && <AvisoProyectos proyectos={proyectos} />}
            <div className="gp-panel ct-editor">
                <ContratoEditor
                    key={contrato.id}
                    contrato={contrato}
                    proyectos={proyectos.lista}
                    puedeEditar={edita}
                    recargar={recargar}
                    onGuardado={() => navigate(volver)}
                    onCancelar={() => navigate(volver)}
                />
            </div>
        </div>
    );
};

/**
 * El módulo Contratos: la lista (`/contratos`), un contrato nuevo (`/contratos/nuevo`, solo quien
 * puede editar) y uno abierto (`/contratos/:contratoId`).
 */
const ContratosModulo = ({ user, nuevo = false }) => {
    const { contratoId } = useParams();
    const edita = puedeEditarContratos(user);
    const proyectos = useProyectos();

    if (nuevo) {
        if (!edita) return <Navigate to={RUTA_CONTRATOS} replace />;
        return <NuevoContrato proyectos={proyectos} />;
    }
    if (contratoId !== undefined) {
        if (!esIdContrato(contratoId)) {
            return (
                <div className="pl-pagina">
                    <Volver a={RUTA_CONTRATOS} />
                    <p className="pl-error" role="alert">El contrato no existe.</p>
                </div>
            );
        }
        return <ContratoAbierto key={contratoId} contratoId={contratoId} edita={edita} proyectos={proyectos} />;
    }
    return <ListaContratos edita={edita} proyectos={proyectos} />;
};

export default ContratosModulo;
```

Crear `resources/js/utils/contratosModulo.js`:

```js
// Lógica de presentación del módulo Contratos (/contratos): filtros en la URL, filas de la lista,
// opciones de los selectores de proyecto y rutas. Funciones puras, probadas en
// contratosModulo.test.js. Los filtros los valida también GET /contratos/buscar.

import { ESTADOS_CONTRATO, etiquetaEstadoContrato } from './contratos';
import { formatoMoneda } from './moneda';
import { RUTA_CONTRATOS } from './navegacion';

/** El valor del filtro de proyecto para los contratos sin proyecto (el `proyecto=sin` de la API). */
export const SIN_PROYECTO = 'sin';

// Largo máximo de `q` en GET /contratos/buscar.
const MAXIMO_BUSQUEDA = 100;
const ES_ID = /^[1-9]\d{0,17}$/;

/**
 * Si el `:contratoId` de la ruta es un id (y no un texto cualquiera): un entero positivo de hasta
 * 18 cifras (lo que acepta la API) que JavaScript representa exacto.
 */
export const esIdContrato = (valor) => {
    const texto = String(valor ?? '');
    return ES_ID.test(texto) && Number(texto) <= Number.MAX_SAFE_INTEGER;
};

/** Los contratos se escriben con editar-datos: Administrador o Gestor. */
export const puedeEditarContratos = (usuario) => ['Administrador', 'Gestor'].includes(usuario?.rol);

// ---------- Filtros ----------

/**
 * Los filtros de la URL (`?q=&estado=&proyecto=`). Lo que el servidor rechazaría (un estado que
 * no existe, un proyecto que no es un id ni "sin") se descarta.
 */
export const filtrosDeConsulta = (consulta) => {
    const leer = (clave) => String(consulta?.get?.(clave) ?? '').trim();
    const estado = leer('estado');
    const proyecto = leer('proyecto');
    return {
        q: leer('q').slice(0, MAXIMO_BUSQUEDA),
        estado: ESTADOS_CONTRATO.some(([v]) => v === estado) ? estado : '',
        proyecto: proyecto === SIN_PROYECTO || ES_ID.test(proyecto) ? proyecto : '',
    };
};

/** Los filtros con valor, en el orden de la barra: lo que se envía a GET /contratos/buscar. */
export const parametrosBusqueda = (filtros) => Object.fromEntries(
    ['q', 'estado', 'proyecto']
        .map((clave) => [clave, String(filtros?.[clave] ?? '').trim()])
        .filter(([, valor]) => valor !== ''),
);

/** La consulta de la URL para los filtros: solo los que tienen valor. */
export const consultaDeFiltros = (filtros) => new URLSearchParams(parametrosBusqueda(filtros));

export const hayFiltros = (filtros) => Object.keys(parametrosBusqueda(filtros)).length > 0;

export const textoListaVacia = (filtros) => (hayFiltros(filtros)
    ? 'No hay contratos que coincidan con los filtros.'
    : 'Aún no hay contratos registrados.');

export const opcionesFiltroEstado = () => [
    { valor: '', etiqueta: 'Todos los estados' },
    ...ESTADOS_CONTRATO.map(([valor, etiqueta]) => ({ valor, etiqueta })),
];

// ---------- Proyectos ----------

const etiquetaProyecto = (id, nombre) => String(nombre ?? '').trim() || `Proyecto ${id}`;

/** Los proyectos (GET /proyectos) como opciones, por nombre; `extra` ({id, nombre}) se agrega si falta. */
const opcionesDeProyectos = (proyectos, extra = null) => {
    const opciones = (proyectos ?? [])
        .map((p) => ({ valor: String(p.id), etiqueta: etiquetaProyecto(p.id, p.nombre) }))
        .sort((a, b) => a.etiqueta.localeCompare(b.etiqueta, 'es', { sensitivity: 'base' }));
    if (extra && !opciones.some((o) => o.valor === String(extra.id))) {
        opciones.push({ valor: String(extra.id), etiqueta: etiquetaProyecto(extra.id, extra.nombre) });
    }
    return opciones;
};

/**
 * Opciones del filtro de proyecto: todos, los sin proyecto o uno. El de la URL se conserva
 * aunque la lista de proyectos no lo traiga o aún no haya llegado.
 */
export const opcionesFiltroProyecto = (proyectos, actual) => [
    { valor: '', etiqueta: 'Todos los proyectos' },
    { valor: SIN_PROYECTO, etiqueta: 'Sin proyecto' },
    ...opcionesDeProyectos(proyectos, ES_ID.test(String(actual ?? '')) ? { id: actual } : null),
];

/**
 * Opciones del selector de proyecto del editor. Un contrato nuevo o sin proyecto puede quedar
 * "Sin proyecto"; uno con proyecto puede cambiarlo, pero no quitarlo (el servidor lo rechaza).
 * El proyecto del contrato se ofrece aunque la lista no lo traiga.
 */
export const opcionesProyecto = (proyectos, contrato) => {
    const actual = contrato?.proyecto ?? null;
    if (actual === null || actual === '') {
        return [{ valor: '', etiqueta: 'Sin proyecto' }, ...opcionesDeProyectos(proyectos)];
    }
    return opcionesDeProyectos(proyectos, { id: actual, nombre: contrato.proyecto_nombre });
};

// ---------- Lista ----------

// Tonos de la insignia de estado (clases ct-estado-<tono> de Contratacion.css).
const TONOS_ESTADO_CONTRATO = {
    celebrado: 'azul',
    en_ejecucion: 'verde',
    suspendido: 'ambar',
    terminado: 'verde-pleno',
    liquidado: 'neutro',
    anulado: 'coral',
};

export const tonoEstadoContrato = (estado) => TONOS_ESTADO_CONTRATO[estado] ?? 'neutro';

export const textoAvance = (valor) => (valor === null || valor === undefined || valor === '' ? '—' : `${valor}%`);

const texto = (valor, siVacio = '—') => String(valor ?? '').trim() || siVacio;

/** Una fila de GET /contratos/buscar tal como la muestra la lista. El valor es el vigente. */
export const filaContrato = (contrato) => ({
    id: contrato.id,
    numero: texto(contrato.n_contrato, 'Sin número'),
    objeto: texto(contrato.objeto),
    contratista: texto(contrato.contratista),
    proyecto: contrato.proyecto ? texto(contrato.proyecto.nombre, `Proyecto ${contrato.proyecto.id}`) : 'Sin proyecto',
    sinProyecto: !contrato.proyecto,
    proceso: contrato.proceso ? texto(contrato.proceso.codigo_proceso, 'Sin número') : '—',
    estado: etiquetaEstadoContrato(contrato.estado),
    tono: tonoEstadoContrato(contrato.estado),
    valor: contrato.valor === null || contrato.valor === undefined || contrato.valor === '' ? '—' : formatoMoneda(Number(contrato.valor)),
    avanceFisico: textoAvance(contrato.avance_fisico),
    avanceFinanciero: textoAvance(contrato.avance_financiero),
});

// ---------- Rutas ----------

export const rutaContrato = (id) => `${RUTA_CONTRATOS}/${id}`;

/**
 * La lista a la que se vuelve desde un contrato: la que se dejó, con sus filtros, si la
 * navegación la trae en su estado (`{ lista: '?q=…' }`); si no, la lista sin filtros.
 */
export const rutaListaDesde = (estado) => {
    const lista = estado?.lista;
    return typeof lista === 'string' && lista.startsWith('?') ? `${RUTA_CONTRATOS}${lista}` : RUTA_CONTRATOS;
};
```

En `resources/css/Contratacion.css`, reemplazar (1 de 1):

```css
.ct-estado-verde-pleno { color: var(--gp-sobre-acento); background: var(--gp-verde-texto); }
.ct-estado-coral { color: var(--gp-coral); background: var(--gp-coral-suave); }

@media (max-width: 720px) {
    .ct-dialogo-fondo { align-items: flex-end; padding: 0; }
    .ct-dialogo { width: 100%; max-height: 92dvh; border-radius: 14px 14px 0 0; }
    .ct-dialogo-cuerpo { padding: .9rem 1rem 1.1rem; }
}
```

por:

```css
.ct-estado-verde-pleno { color: var(--gp-sobre-acento); background: var(--gp-verde-texto); }
.ct-estado-coral { color: var(--gp-coral); background: var(--gp-coral-suave); }

/* ---------- Módulo Contratos (/contratos) ---------- */

.ct-filtro { flex: 0 1 13rem; }
/* El objeto puede ser largo: dos líneas en la lista; completo en el contrato. */
.ct-objeto { display: -webkit-box; max-width: 28rem; overflow: hidden; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow-wrap: anywhere; }
.ct-sin-dato { color: var(--gp-texto-3); font-style: italic; }
/* El editor de ContratoEditor, en una página en vez de un diálogo: el pie de acciones queda dentro del panel. */
.ct-editor { overflow: clip; }
.ct-editor .modal-actions { margin: 0; border-radius: 0 0 var(--gp-radio-grande) var(--gp-radio-grande); }

@media (max-width: 720px) {
    .ct-dialogo-fondo { align-items: flex-end; padding: 0; }
    .ct-dialogo { width: 100%; max-height: 92dvh; border-radius: 14px 14px 0 0; }
    .ct-dialogo-cuerpo { padding: .9rem 1rem 1.1rem; }
    .ct-filtro { flex: 1 1 100%; }
    .ct-objeto { max-width: none; text-align: right; }
    /* Sobre la barra inferior del armazón. */
    .ct-editor .modal-actions { bottom: calc(var(--gp-barra-inferior) + env(safe-area-inset-bottom)); }
}
```

En `resources/js/app.jsx`, reemplazar (1 de 2):

```jsx
import ExpedienteViabilizacion from './components/ExpedienteViabilizacion';
import PlantillasChecklist from './components/PlantillasChecklist';
import PlantillaDetalle from './components/PlantillaDetalle';
import { RUTA_PLANTILLAS, puedeVer } from './utils/navegacion';
import axios from './axios';
import { EVENTO_SESION_EXPIRADA } from './utils/sesion';
import '../css/app.css';
```

por:

```jsx
import ExpedienteViabilizacion from './components/ExpedienteViabilizacion';
import PlantillasChecklist from './components/PlantillasChecklist';
import PlantillaDetalle from './components/PlantillaDetalle';
import ContratosModulo from './components/contratos/ContratosModulo';
import { RUTA_CONTRATOS, RUTA_PLANTILLAS, puedeVer } from './utils/navegacion';
import axios from './axios';
import { EVENTO_SESION_EXPIRADA } from './utils/sesion';
import '../css/app.css';
```

En `resources/js/app.jsx`, reemplazar (2 de 2):

```jsx
          <Route path="/parametros/:seccion" element={<Parametros user={user} />} />
          {/* No usar /expedientes/...: bajo /GestPro chocaría con la API del mismo nombre. */}
          <Route path="/viabilizacion/:proyectoId" element={<ExpedienteViabilizacion user={user} />} />
          {/* Solo Administrador: el backend también lo exige. */}
          <Route path={RUTA_PLANTILLAS} element={puedeVer(user, 'plantillas') ? <PlantillasChecklist /> : <Navigate to={INICIO} replace />} />
          <Route path={`${RUTA_PLANTILLAS}/:plantillaId`} element={puedeVer(user, 'plantillas') ? <PlantillaDetalle /> : <Navigate to={INICIO} replace />} />
```

por:

```jsx
          <Route path="/parametros/:seccion" element={<Parametros user={user} />} />
          {/* No usar /expedientes/...: bajo /GestPro chocaría con la API del mismo nombre. */}
          <Route path="/viabilizacion/:proyectoId" element={<ExpedienteViabilizacion user={user} />} />
          {/* Lectura para todos; nuevo y modificar, solo quien edita (ContratosModulo lo decide). */}
          <Route path={RUTA_CONTRATOS} element={<ContratosModulo user={user} />} />
          <Route path={`${RUTA_CONTRATOS}/nuevo`} element={<ContratosModulo user={user} nuevo />} />
          <Route path={`${RUTA_CONTRATOS}/:contratoId`} element={<ContratosModulo user={user} />} />
          {/* Solo Administrador: el backend también lo exige. */}
          <Route path={RUTA_PLANTILLAS} element={puedeVer(user, 'plantillas') ? <PlantillasChecklist /> : <Navigate to={INICIO} replace />} />
          <Route path={`${RUTA_PLANTILLAS}/:plantillaId`} element={puedeVer(user, 'plantillas') ? <PlantillaDetalle /> : <Navigate to={INICIO} replace />} />
```

En `resources/js/components/Dashboard.jsx`, reemplazar (1 de 1):

```jsx

const VISTAS_ANALISIS = [
    { id: VISTA.ESTADISTICAS, titulo: 'Estadísticas', icono: faChartColumn },
    { id: VISTA.CONTRATOS, titulo: 'Contratos', icono: faFileSignature },
    { id: VISTA.EXPEDIENTES, titulo: 'Expedientes', icono: faClipboardCheck },
];
```

por:

```jsx

const VISTAS_ANALISIS = [
    { id: VISTA.ESTADISTICAS, titulo: 'Estadísticas', icono: faChartColumn },
    { id: VISTA.CONTRATOS, titulo: 'Avance de contratos', icono: faFileSignature },
    { id: VISTA.EXPEDIENTES, titulo: 'Expedientes', icono: faClipboardCheck },
];
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (1 de 5):

```jsx
import { datosEstado, esEdicionDesactualizada, textoValor, valorEnEdicion } from '../../utils/contratacion';
import {
    ACTA_VACIA, ACTIVIDAD_VACIA, ANEXO_VACIO, AVANCE_VACIO, ESTADOS_CONTRATO, MODIFICACION_VACIA, actividadConAvance, anexosDelContrato,
    anexosParaGuardar, aplicarPrecarga, avanceFinancieroDeActas, avanceFisicoDeActividades, calculoActa, contratoVacio, datosActa,
    datosContrato, datosDelProceso, datosModificacion, erroresGuardarContrato, etiquetaTipoModificacion, formularioContrato, notaProceso,
    opcionesProceso, opcionesTipoContrato, primerCampoConError, procesoElegido, sumaPesos, validarContrato, valorVigente, vigentesDistintos,
} from '../../utils/contratos';
import { formatoMoneda, limpiarMoneda, monedaEnEdicion, numeroDeMoneda } from '../../utils/moneda';
import { htmlErrores, mensajeApi } from '../../utils/plantillasChecklist';
import '../../../css/Parametros.css';
```

por:

```jsx
import { datosEstado, esEdicionDesactualizada, textoValor, valorEnEdicion } from '../../utils/contratacion';
import {
    ACTA_VACIA, ACTIVIDAD_VACIA, ANEXO_VACIO, AVANCE_VACIO, ESTADOS_CONTRATO, MODIFICACION_VACIA, actividadConAvance, anexosDelContrato,
    anexosParaGuardar, aplicarPrecarga, avanceFinancieroDeActas, avanceFisicoDeActividades, calculoActa, cambiarProyecto, contratoVacio,
    datosActa, datosContrato, datosDelProceso, datosModificacion, erroresGuardarContrato, etiquetaTipoModificacion, formularioContrato,
    notaProceso, opcionesProceso, opcionesTipoContrato, primerCampoConError, procesoDelContrato, procesoElegido, sumaPesos, validarContrato,
    valorVigente, vigentesDistintos,
} from '../../utils/contratos';
import { opcionesProyecto } from '../../utils/contratosModulo';
import { formatoMoneda, limpiarMoneda, monedaEnEdicion, numeroDeMoneda } from '../../utils/moneda';
import { htmlErrores, mensajeApi } from '../../utils/plantillasChecklist';
import '../../../css/Parametros.css';
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (2 de 5):

```jsx
 * - `procesoInicial`: en un contrato nuevo, el id del proceso ya elegido; se aplica su precarga.
 * - `tiposContrato`: el catálogo si quien lo pinta ya lo trae (null mientras carga o si falló); sin la prop, lo pide el editor.
 * - `procesosIniciales`: los procesos del proyecto que ya se tienen, mientras llegan los recién pedidos.
 * - `puedeEditar`: sin permiso, el contrato se ve sin controles para modificarlo.
 * - `recargar(id)`: vuelve a traer los contratos tras un cambio en las pestañas; resuelve el contrato `id` recargado o null.
 * - `onGuardado(contrato)`: tras guardar (con los avisos ya mostrados); quien lo pinta cierra el editor.
 * - `onCancelar()`: "Cancelar" o "Volver a la lista".
 */
const ContratoEditor = ({
    proyecto = null, contrato = null, procesoInicial = null, tiposContrato, procesosIniciales = null, puedeEditar = true,
    recargar, onGuardado, onCancelar,
}) => {
    const [editingContrato, setEditingContrato] = useState(contrato);
```

por:

```jsx
 * - `procesoInicial`: en un contrato nuevo, el id del proceso ya elegido; se aplica su precarga.
 * - `tiposContrato`: el catálogo si quien lo pinta ya lo trae (null mientras carga o si falló); sin la prop, lo pide el editor.
 * - `procesosIniciales`: los procesos del proyecto que ya se tienen, mientras llegan los recién pedidos.
 * - `proyectos`: con la prop (la lista de GET /proyectos, o null mientras carga), el formulario
 *   muestra un selector de proyecto: uno nuevo o sin proyecto puede quedar "Sin proyecto"; uno
 *   con proyecto puede cambiarlo, pero no quitarlo. Al cambiarlo se cargan los procesos del
 *   elegido. Sin la prop, el proyecto es el de `proyecto` o el del contrato, sin selector.
 * - `puedeEditar`: sin permiso, el contrato se ve sin controles para modificarlo.
 * - `recargar(id)`: vuelve a traer los contratos tras un cambio en las pestañas; resuelve el contrato `id` recargado o null.
 * - `onGuardado(contrato)`: tras guardar (con los avisos ya mostrados); quien lo pinta cierra el editor.
 * - `onCancelar()`: "Cancelar" o "Volver a la lista".
 */
const ContratoEditor = ({
    proyecto = null, contrato = null, procesoInicial = null, tiposContrato, procesosIniciales = null, proyectos, puedeEditar = true,
    recargar, onGuardado, onCancelar,
}) => {
    const [editingContrato, setEditingContrato] = useState(contrato);
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (3 de 5):

```jsx
    const handleContratoChange = (e) => {
        const { name, value, type, checked } = e.target;
        setErroresContrato(prev => (prev[name] ? { ...prev, [name]: undefined } : prev));
        if (name === 'proceso_id') {
            setFormContrato(prev => ({ ...prev, proceso_id: value }));
            // R8: en un contrato nuevo, el proceso elegido llena los campos que siguen vacíos.
```

por:

```jsx
    const handleContratoChange = (e) => {
        const { name, value, type, checked } = e.target;
        setErroresContrato(prev => (prev[name] ? { ...prev, [name]: undefined } : prev));
        if (name === 'proyecto') {
            // El proceso era del proyecto anterior: se quita y se cargan los del elegido.
            setErroresContrato(prev => (prev.proceso_id ? { ...prev, proceso_id: undefined } : prev));
            setFormContrato(prev => cambiarProyecto(prev, value, editingContrato));
            // Mientras llegan los del elegido no se ofrecen los del proyecto anterior.
            setProcesosContrato(null);
            cargarProcesosContrato(value);
            return;
        }
        if (name === 'proceso_id') {
            setFormContrato(prev => ({ ...prev, proceso_id: value }));
            // R8: en un contrato nuevo, el proceso elegido llena los campos que siguen vacíos.
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (4 de 5):

```jsx
    };

    // Formulario de contrato: proceso elegido, nota R4 y vigentes que difieren de los iniciales.
    const procesoDelContrato = procesoElegido(procesosContrato, editingContrato?.proceso ?? null, formContrato.proceso_id);
    const datosProcesoContrato = procesoDelContrato ? datosDelProceso(procesoDelContrato) : null;
    const notaProcesoContrato = notaProceso(procesosContrato, formContrato.proceso_id);
    const vigentesContrato = vigentesDistintos(editingContrato);
```

por:

```jsx
    };

    // Formulario de contrato: proceso elegido, nota R4 y vigentes que difieren de los iniciales.
    // El proceso que el contrato ya tiene solo se ofrece mientras siga en su proyecto.
    const procesoActual = procesoDelContrato(editingContrato, formContrato);
    const procesoFormulario = procesoElegido(procesosContrato, procesoActual, formContrato.proceso_id);
    const datosProcesoContrato = procesoFormulario ? datosDelProceso(procesoFormulario) : null;
    const notaProcesoContrato = notaProceso(procesosContrato, formContrato.proceso_id);
    const vigentesContrato = vigentesDistintos(editingContrato);
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (5 de 5):

```jsx
                            Completa los datos del contrato. Los campos marcados con * son obligatorios.
                        </p>
                        <div className="contrato-form" ref={formularioContratoRef}>
                            <div className="form-row">
                                {campoContrato('proceso_id', 'Proceso de contratación', (
                                    <select {...controlContrato('proceso_id')}>
                                        {opcionesProceso(procesosContrato, editingContrato?.proceso ?? null).map(o => (
                                            <option key={o.valor} value={o.valor}>{o.etiqueta}</option>
                                        ))}
                                    </select>
```

por:

```jsx
                            Completa los datos del contrato. Los campos marcados con * son obligatorios.
                        </p>
                        <div className="contrato-form" ref={formularioContratoRef}>
                            {proyectos !== undefined && campoContrato('proyecto', 'Proyecto', (
                                // Los errores del proyecto llegan en un aviso (erroresGuardarContrato): aquí, solo la nota.
                                <select {...controlContrato('proyecto')} aria-describedby={puedeEditar ? 'proyecto-nota' : undefined}>
                                    {opcionesProyecto(proyectos, editingContrato).map(o => (
                                        <option key={o.valor} value={o.valor}>{o.etiqueta}</option>
                                    ))}
                                </select>
                            ))}
                            {proyectos !== undefined && puedeEditar && (
                                <p id="proyecto-nota" className="contrato-nota">
                                    {editingContrato?.proyecto
                                        ? 'Puedes pasar el contrato a otro proyecto, pero no dejarlo sin proyecto.'
                                        : 'Opcional: un contrato puede no tener proyecto. Si lo eliges, puedes vincularlo a uno de sus procesos.'}
                                </p>
                            )}
                            <div className="form-row">
                                {campoContrato('proceso_id', 'Proceso de contratación', (
                                    <select {...controlContrato('proceso_id')}>
                                        {opcionesProceso(procesosContrato, procesoActual).map(o => (
                                            <option key={o.valor} value={o.valor}>{o.etiqueta}</option>
                                        ))}
                                    </select>
```

En `resources/js/utils/contratos.js`, reemplazar (1 de 1):

```js
    return resultado;
};

// ---------- Valores vigentes ----------

/**
```

por:

```js
    return resultado;
};

// ---------- Proyecto del contrato ----------

/**
 * El formulario tras elegir otro proyecto en el selector del módulo Contratos. El proceso era del
 * proyecto anterior y se quita; al volver al proyecto del contrato abierto (`contrato`), recupera
 * el suyo. Elegir el mismo proyecto devuelve el formulario sin cambios.
 */
export const cambiarProyecto = (formulario, proyectoId, contrato = null) => {
    const proyecto = comoTexto(proyectoId);
    if (proyecto === comoTexto(formulario.proyecto)) return formulario;
    const original = contrato ? formularioContrato(contrato) : null;
    return {
        ...formulario,
        proyecto,
        proceso_id: original && original.proyecto === proyecto ? original.proceso_id : '',
    };
};

/**
 * El proceso que el contrato abierto ya tiene (su resumen), mientras el formulario siga en el
 * proyecto del contrato; si se eligió otro proyecto, ese proceso ya no se ofrece. null si no hay.
 */
export const procesoDelContrato = (contrato, formulario) => {
    if (!contrato?.proceso) return null;
    return comoTexto(contrato.proyecto) === comoTexto(formulario?.proyecto) ? contrato.proceso : null;
};

// ---------- Valores vigentes ----------

/**
```

En `resources/js/utils/navegacion.js`, reemplazar (1 de 2):

```js
// No usar /plantillas: bajo /GestPro chocaría con la API del mismo nombre.
export const RUTA_PLANTILLAS = '/plantillas-checklist';

export const DESTINOS = [
    { id: 'tablero', titulo: 'Tablero', ruta: '/dashboard', grupo: GRUPO.TRABAJO, icono: 'faGaugeHigh', palabras: 'inicio dashboard panel mapa' },
    ...SECCIONES_PARAMETROS.filter((s) => s.grupo === GRUPO.TRABAJO).map((s) => ({ ...s, id: s.slug, ruta: `/parametros/${s.slug}` })),
    { id: 'expedientes', titulo: 'Expedientes', ruta: '/dashboard?vista=expedientes', grupo: GRUPO.TRABAJO, icono: 'faClipboardCheck', palabras: 'sgr viabilizacion regalias requisitos checklist plantillas seguimiento' },
    { id: 'contratos', titulo: 'Contratos', ruta: '/dashboard?vista=contratos', grupo: GRUPO.ANALISIS, icono: 'faFileSignature', palabras: 'avance fisico financiero desfase' },
    { id: 'estadisticas', titulo: 'Estadísticas', ruta: '/dashboard?vista=estadisticas', grupo: GRUPO.ANALISIS, icono: 'faChartColumn', palabras: 'graficas filtros cadena presupuestal' },
    ...SECCIONES_PARAMETROS.filter((s) => s.grupo === GRUPO.CATALOGOS).map((s) => ({ ...s, id: s.slug, ruta: `/parametros/${s.slug}` })),
    { id: 'plantillas', titulo: 'Plantillas de checklist', ruta: RUTA_PLANTILLAS, grupo: GRUPO.CATALOGOS, icono: 'faListCheck', palabras: 'requisitos checklist fuentes sectores versiones catalogo', soloRol: 'Administrador' },
```

por:

```js
// No usar /plantillas: bajo /GestPro chocaría con la API del mismo nombre.
export const RUTA_PLANTILLAS = '/plantillas-checklist';

// Todos los contratos, con o sin proyecto. Bajo /GestPro no es una ruta GET de la API.
export const RUTA_CONTRATOS = '/contratos';

export const DESTINOS = [
    { id: 'tablero', titulo: 'Tablero', ruta: '/dashboard', grupo: GRUPO.TRABAJO, icono: 'faGaugeHigh', palabras: 'inicio dashboard panel mapa' },
    ...SECCIONES_PARAMETROS.filter((s) => s.grupo === GRUPO.TRABAJO).map((s) => ({ ...s, id: s.slug, ruta: `/parametros/${s.slug}` })),
    { id: 'expedientes', titulo: 'Expedientes', ruta: '/dashboard?vista=expedientes', grupo: GRUPO.TRABAJO, icono: 'faClipboardCheck', palabras: 'sgr viabilizacion regalias requisitos checklist plantillas seguimiento' },
    { id: 'contratos', titulo: 'Contratos', ruta: RUTA_CONTRATOS, grupo: GRUPO.TRABAJO, icono: 'faFileSignature', palabras: 'contratista contratante obra interventoria actas anexos otrosi adiciones prorrogas modificaciones' },
    { id: 'avance-contratos', titulo: 'Avance de contratos', ruta: '/dashboard?vista=contratos', grupo: GRUPO.ANALISIS, icono: 'faCircleHalfStroke', palabras: 'avance fisico financiero desfase' },
    { id: 'estadisticas', titulo: 'Estadísticas', ruta: '/dashboard?vista=estadisticas', grupo: GRUPO.ANALISIS, icono: 'faChartColumn', palabras: 'graficas filtros cadena presupuestal' },
    ...SECCIONES_PARAMETROS.filter((s) => s.grupo === GRUPO.CATALOGOS).map((s) => ({ ...s, id: s.slug, ruta: `/parametros/${s.slug}` })),
    { id: 'plantillas', titulo: 'Plantillas de checklist', ruta: RUTA_PLANTILLAS, grupo: GRUPO.CATALOGOS, icono: 'faListCheck', palabras: 'requisitos checklist fuentes sectores versiones catalogo', soloRol: 'Administrador' },
```

En `resources/js/utils/navegacion.js`, reemplazar (2 de 2):

```js
export const destinoActivo = (pathname, search) => {
    if (pathname.startsWith('/viabilizacion/')) return 'expedientes';
    if (pathname === RUTA_PLANTILLAS || pathname.startsWith(`${RUTA_PLANTILLAS}/`)) return 'plantillas';
    if (pathname.startsWith('/parametros/')) return pathname.split('/')[2];
    if (pathname.startsWith('/dashboard')) {
        const vista = new URLSearchParams(search).get('vista');
        return ['contratos', 'expedientes', 'estadisticas'].includes(vista) ? vista : 'tablero';
    }
    return null;
};
```

por:

```js
export const destinoActivo = (pathname, search) => {
    if (pathname.startsWith('/viabilizacion/')) return 'expedientes';
    if (pathname === RUTA_PLANTILLAS || pathname.startsWith(`${RUTA_PLANTILLAS}/`)) return 'plantillas';
    if (pathname === RUTA_CONTRATOS || pathname.startsWith(`${RUTA_CONTRATOS}/`)) return 'contratos';
    if (pathname.startsWith('/parametros/')) return pathname.split('/')[2];
    if (pathname.startsWith('/dashboard')) {
        const vista = new URLSearchParams(search).get('vista');
        if (vista === 'contratos') return 'avance-contratos';
        return ['expedientes', 'estadisticas'].includes(vista) ? vista : 'tablero';
    }
    return null;
};
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/contratosModulo.test.js resources/js/utils/contratos.test.js resources/js/utils/navegacion.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1028 passed.

Run: `npm test`
Expected: 575 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/js/components/contratos/ContratosModulo.jsx resources/js/utils/contratosModulo.js resources/js/utils/contratosModulo.test.js resources/css/Contratacion.css resources/js/app.jsx resources/js/components/Dashboard.jsx resources/js/components/contratos/ContratoEditor.jsx resources/js/utils/contratos.js resources/js/utils/contratos.test.js resources/js/utils/navegacion.js resources/js/utils/navegacion.test.js
git commit -m "feat: contracts module with filters, new and open, and navigation"
```

---

### Tarea 5: Registrar contrato desde el proceso

Botón "Registrar contrato" en los procesos adjudicados o celebrados, que abre el editor con el proceso elegido y la precarga.

**Files:**
- Modify: `resources/js/components/Parametros.jsx`
- Modify: `resources/js/components/ProcesosProyecto.jsx`
- Modify: `resources/js/components/contratos/ContratoEditor.jsx`
- Modify: `resources/js/utils/contratacion.js`
- Test: `resources/js/utils/contratacion.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/contratacion.test.js`, reemplazar (1 de 4):

```js
import {
    CAMPOS_PROCESO,
    CATALOGO_VACIO,
    ESTADOS_CIERRE,
    ESTADOS_PROCESO,
    PLATAFORMAS,
```

por:

```js
import {
    CAMPOS_PROCESO,
    CATALOGO_VACIO,
    ESTADOS_ADMITEN_CONTRATOS,
    ESTADOS_CIERRE,
    ESTADOS_PROCESO,
    PLATAFORMAS,
```

En `resources/js/utils/contratacion.test.js`, reemplazar (2 de 4):

```js
    opcionesEtapa,
    opcionesFuenteProceso,
    puedeEditarProcesos,
    textoContratos,
    textoValor,
    tonoEstado,
```

por:

```js
    opcionesEtapa,
    opcionesFuenteProceso,
    puedeEditarProcesos,
    puedeRegistrarContrato,
    modoTrasGuardarContrato,
    textoContratos,
    textoValor,
    tonoEstado,
```

En `resources/js/utils/contratacion.test.js`, reemplazar (3 de 4):

```js
        expect(ESTADOS_PROCESO.map(([v]) => v)).toEqual(lista('ESTADOS'));
        expect(ESTADOS_CIERRE.every((e) => lista('ESTADOS').includes(e))).toBe(true);
    });
});

describe('puedeEditarProcesos', () => {
```

por:

```js
        expect(ESTADOS_PROCESO.map(([v]) => v)).toEqual(lista('ESTADOS'));
        expect(ESTADOS_CIERRE.every((e) => lista('ESTADOS').includes(e))).toBe(true);
    });

    it('los estados que admiten contratos coinciden con ADMITEN_CONTRATOS', () => {
        expect(ESTADOS_ADMITEN_CONTRATOS).toEqual(lista('ADMITEN_CONTRATOS'));
    });
});

describe('puedeEditarProcesos', () => {
```

En `resources/js/utils/contratacion.test.js`, reemplazar (4 de 4):

```js
    });
});

describe('etiquetas de plataforma y estado', () => {
    it('dan el nombre legible', () => {
        expect(etiquetaPlataforma('secop_ii')).toBe('SECOP II');
```

por:

```js
    });
});

describe('modoTrasGuardarContrato', () => {
    const proceso = { id: 4 };

    it('cierra el contrato de ese proceso', () => {
        expect(modoTrasGuardarContrato({ tipo: 'contrato', proceso }, 4)).toBeNull();
    });

    it('no cierra otro modo abierto entretanto', () => {
        const estado = { tipo: 'estado', proceso };
        const edicion = { tipo: 'proceso', proceso: null };
        const otroContrato = { tipo: 'contrato', proceso: { id: 5 } };
        expect(modoTrasGuardarContrato(estado, 4)).toBe(estado);
        expect(modoTrasGuardarContrato(edicion, 4)).toBe(edicion);
        expect(modoTrasGuardarContrato(otroContrato, 4)).toBe(otroContrato);
        expect(modoTrasGuardarContrato(null, 4)).toBeNull();
    });
});

describe('puedeRegistrarContrato', () => {
    const gestor = { rol: 'Gestor' };

    it('solo un proceso adjudicado o celebrado, y solo para quien puede editar', () => {
        expect(puedeRegistrarContrato(gestor, { estado: 'adjudicado' })).toBe(true);
        expect(puedeRegistrarContrato({ rol: 'Administrador' }, { estado: 'celebrado' })).toBe(true);
        ['preparacion', 'publicado', 'evaluacion', 'desierto', 'cancelado', 'revocado'].forEach((estado) => {
            expect(puedeRegistrarContrato(gestor, { estado })).toBe(false);
        });
        expect(puedeRegistrarContrato({ rol: 'Consulta' }, { estado: 'adjudicado' })).toBe(false);
        expect(puedeRegistrarContrato(undefined, { estado: 'adjudicado' })).toBe(false);
        expect(puedeRegistrarContrato(gestor, undefined)).toBe(false);
    });
});

describe('etiquetas de plataforma y estado', () => {
    it('dan el nombre legible', () => {
        expect(etiquetaPlataforma('secop_ii')).toBe('SECOP II');
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/contratacion.test.js`
Expected: FALLAN 4 y pasan 72: no existen `ESTADOS_ADMITEN_CONTRATOS`, `puedeRegistrarContrato` ni `modoTrasGuardarContrato`.

- [ ] **Paso 3: Implementar**

En `resources/js/components/Parametros.jsx`, reemplazar (1 de 1):

```jsx
                    proyecto={proyectoProcesos}
                    user={user}
                    onCerrar={() => setProyectoProcesos(null)}
                />
            )}
```

por:

```jsx
                    proyecto={proyectoProcesos}
                    user={user}
                    onCerrar={() => setProyectoProcesos(null)}
                    onCambio={listProyectos}
                />
            )}
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (1 de 9):

```jsx
import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowRightArrowLeft, faArrowUpRightFromSquare, faGavel, faPen, faPlus, faTrash, faXmark } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
```

por:

```jsx
import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowRightArrowLeft, faArrowUpRightFromSquare, faFileSignature, faGavel, faPen, faPlus, faTrash, faXmark } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (2 de 9):

```jsx
import {
    CAMPOS_PROCESO, ESTADOS_PROCESO, MAXIMO_OBSERVACION, MAXIMO_OBSERVACIONES_PROCESO, PLATAFORMAS, avisoCambioEstado, datosEstado, datosProceso,
    erroresPorCampo, esEdicionDesactualizada, etiquetaEstadoProceso, etiquetaPlataforma, formularioProceso,
    indiceFocoAtrapado, numeroProceso, opcionesConActual, opcionesFuenteProceso, ordenarCatalogo, puedeEditarProcesos,
    textoContratos, textoFecha, textoValor, tonoEstado, validarProceso, valorEnEdicion,
} from '../utils/contratacion';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';
```

por:

```jsx
import {
    CAMPOS_PROCESO, ESTADOS_PROCESO, MAXIMO_OBSERVACION, MAXIMO_OBSERVACIONES_PROCESO, PLATAFORMAS, avisoCambioEstado, datosEstado, datosProceso,
    erroresPorCampo, esEdicionDesactualizada, etiquetaEstadoProceso, etiquetaPlataforma, formularioProceso,
    indiceFocoAtrapado, modoTrasGuardarContrato, numeroProceso, opcionesConActual, opcionesFuenteProceso, ordenarCatalogo, puedeEditarProcesos, puedeRegistrarContrato,
    textoContratos, textoFecha, textoValor, tonoEstado, validarProceso, valorEnEdicion,
} from '../utils/contratacion';
import ContratoEditor from './contratos/ContratoEditor';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (3 de 9):

```jsx
    { clave: 'responsables', ruta: '/responsables', guardar: (d, { setResponsables }) => setResponsables(comoLista(d)) },
];

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
```

por:

```jsx
    { clave: 'responsables', ruta: '/responsables', guardar: (d, { setResponsables }) => setResponsables(comoLista(d)) },
];

// Un contrato nuevo no tiene pestañas que recargar: el editor lo cierra quien lo abrió al guardar.
const sinRecargar = async () => null;

const ENFOCABLES = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Comportamiento de un diálogo modal sobre `caja`: el foco entra al abrir, Tab no sale de él y,
 * al cerrar, vuelve a donde estaba. Devuelve el `onKeyDown` de la caja: Escape llama a `onEscape`.
 */
const useDialogoModal = (caja, onEscape) => {
    useEffect(() => {
        const anterior = document.activeElement;
        caja.current?.focus();
        return () => {
            if (anterior instanceof HTMLElement && document.contains(anterior)) anterior.focus();
        };
    }, [caja]);

    return (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            onEscape();
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (4 de 9):

```jsx
        e.preventDefault();
        (focos[destino] ?? caja.current).focus();
    };

    return (
        <div className="ct-dialogo-fondo" onMouseDown={(e) => { if (e.target === e.currentTarget) onFondo(); }}>
```

por:

```jsx
        e.preventDefault();
        (focos[destino] ?? caja.current).focus();
    };
};

/**
 * Diálogo modal accesible: Escape llama a `onEscape`, un clic en el fondo a `onFondo` y, al
 * cerrar, el foco vuelve a donde estaba (el botón "Procesos" de la fila).
 */
const Dialogo = ({ titulo, subtitulo, onCerrar, onEscape, onFondo, children }) => {
    const caja = useRef(null);
    const idTitulo = useId();
    const alPulsar = useDialogoModal(caja, onEscape);

    return (
        <div className="ct-dialogo-fondo" onMouseDown={(e) => { if (e.target === e.currentTarget) onFondo(); }}>
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (5 de 9):

```jsx
    );
};

const Campo = ({ etiqueta, error, idError, ancho, children }) => (
    <label className={`pl-campo${ancho ? ' pl-campo-ancho' : ''}`}>
        <span>{etiqueta}</span>
```

por:

```jsx
    );
};

/**
 * El editor de un contrato nuevo del proyecto, con ese proceso ya elegido (y su precarga), en un
 * diálogo propio sobre el de procesos. Los controles de cerrar son los del editor (Cancelar) y la
 * "×"; Escape cancela y un clic en el fondo no descarta lo escrito. Mientras el editor guarda,
 * ni Escape ni la "×" lo cierran.
 */
const DialogoContrato = ({ proyecto, proceso, onGuardado, onCancelar }) => {
    const caja = useRef(null);
    const idTitulo = useId();
    const [guardando, setGuardando] = useState(false);
    const cancelar = () => { if (!guardando) onCancelar(); };
    const alPulsar = useDialogoModal(caja, cancelar);

    return (
        <div className="modal-overlay">
            <div ref={caja} className="modal-content contratos-modal" role="dialog" aria-modal="true" aria-labelledby={idTitulo} tabIndex={-1} onKeyDown={alPulsar}>
                <div className="modal-header">
                    <h2 id={idTitulo}><FontAwesomeIcon icon={faFileSignature} aria-hidden="true" /> Nuevo contrato - {proyecto.nombre}</h2>
                    <button type="button" onClick={cancelar} className="btn-close" aria-label="Cerrar" disabled={guardando}>×</button>
                </div>
                <ContratoEditor
                    proyecto={proyecto}
                    procesoInicial={proceso.id}
                    recargar={sinRecargar}
                    onGuardado={onGuardado}
                    onCancelar={cancelar}
                    onOcupado={setGuardando}
                />
            </div>
        </div>
    );
};

const Campo = ({ etiqueta, error, idError, ancho, children }) => (
    <label className={`pl-campo${ancho ? ' pl-campo-ancho' : ''}`}>
        <span>{etiqueta}</span>
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (6 de 9):

```jsx
 * Parámetros → Proyectos. Un Administrador o Gestor los crea, edita, cambia de estado y elimina;
 * los demás solo los consultan. El backend aplica los mismos permisos y reglas.
 */
const ProcesosProyecto = ({ proyecto, user, onCerrar }) => {
    const [procesos, setProcesos] = useState(null);
    const [error, setError] = useState(null);
    const [modalidades, setModalidades] = useState(null);
```

por:

```jsx
 * Parámetros → Proyectos. Un Administrador o Gestor los crea, edita, cambia de estado y elimina;
 * los demás solo los consultan. El backend aplica los mismos permisos y reglas.
 */
const ProcesosProyecto = ({ proyecto, user, onCerrar, onCambio }) => {
    const [procesos, setProcesos] = useState(null);
    const [error, setError] = useState(null);
    const [modalidades, setModalidades] = useState(null);
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (7 de 9):

```jsx
    const [fuentes, setFuentes] = useState(null);
    const [responsables, setResponsables] = useState(null);
    const [errorCatalogos, setErrorCatalogos] = useState(null);
    // null; { tipo: 'proceso', proceso: null | proceso } o { tipo: 'estado', proceso }.
    const [modo, setModo] = useState(null);
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
```

por:

```jsx
    const [fuentes, setFuentes] = useState(null);
    const [responsables, setResponsables] = useState(null);
    const [errorCatalogos, setErrorCatalogos] = useState(null);
    // null; { tipo: 'proceso', proceso: null | proceso }, { tipo: 'estado', proceso } o { tipo: 'contrato', proceso }.
    const [modo, setModo] = useState(null);
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (8 de 9):

```jsx
        }
    });

    const eliminar = (proceso) => ejecutar(async () => {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
```

por:

```jsx
        }
    });

    // El contrato se guardó (el editor ya mostró sus avisos): se cierra (si sigue abierto) y se
    // recarga la lista, que trae la cuenta de contratos y el estado, que pudo pasar a celebrado con
    // la propuesta del editor. `onCambio` avisa a quien abrió el diálogo: sus contratos cambiaron.
    const alGuardarContrato = (procesoId) => {
        setModo((actual) => modoTrasGuardarContrato(actual, procesoId));
        cargar();
        onCambio?.();
    };

    const eliminar = (proceso) => ejecutar(async () => {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (9 de 9):

```jsx
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
                        fuenteIds={proyecto.fuente_ids ?? []}
                        modalidades={modalidades}
                        entidades={entidades}
                        fuentes={fuentes}
                        responsables={responsables}
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
                                            <div><dt>Fuente de financiación</dt><dd>{p.fuente?.nombre ?? '—'}</dd></div>
                                            <div><dt>Responsable</dt><dd>{p.responsable?.nombre ?? '—'}</dd></div>
                                            <div><dt>Apertura</dt><dd>{textoFecha(p.fecha_apertura)}</dd></div>
                                            <div><dt>Cierre</dt><dd>{textoFecha(p.fecha_cierre)}</dd></div>
                                            <div><dt>Contratos vinculados</dt><dd>{textoContratos(p.contratos)}</dd></div>
                                        </dl>
                                        {p.observaciones && <p className="pl-ayuda">{p.observaciones}</p>}
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
```

por:

```jsx
    const editandoProceso = modo?.tipo === 'proceso';

    return (
        <>
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
                            fuenteIds={proyecto.fuente_ids ?? []}
                            modalidades={modalidades}
                            entidades={entidades}
                            fuentes={fuentes}
                            responsables={responsables}
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
                                                <div><dt>Fuente de financiación</dt><dd>{p.fuente?.nombre ?? '—'}</dd></div>
                                                <div><dt>Responsable</dt><dd>{p.responsable?.nombre ?? '—'}</dd></div>
                                                <div><dt>Apertura</dt><dd>{textoFecha(p.fecha_apertura)}</dd></div>
                                                <div><dt>Cierre</dt><dd>{textoFecha(p.fecha_cierre)}</dd></div>
                                                <div><dt>Contratos vinculados</dt><dd>{textoContratos(p.contratos)}</dd></div>
                                            </dl>
                                            {p.observaciones && <p className="pl-ayuda">{p.observaciones}</p>}
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
                                                    {puedeRegistrarContrato(user, p) && (
                                                        <button type="button" className="gp-boton" data-foco={`contrato:${p.id}`} disabled={ocupado || modo !== null}
                                                            onClick={() => abrir({ tipo: 'contrato', proceso: p }, `contrato:${p.id}`)}>
                                                            <FontAwesomeIcon icon={faFileSignature} /> Registrar contrato
                                                        </button>
                                                    )}
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
            {/* Hermano y posterior al diálogo de procesos: queda por encima y su foco no lo atrapa aquel. */}
            {modo?.tipo === 'contrato' && (
                <DialogoContrato
                    proyecto={proyecto}
                    proceso={modo.proceso}
                    onGuardado={() => alGuardarContrato(modo.proceso.id)}
                    onCancelar={() => setModo(null)}
                />
            )}
        </>
    );
};
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (1 de 2):

```jsx
 * - `recargar(id)`: vuelve a traer los contratos tras un cambio en las pestañas; resuelve el contrato `id` recargado o null.
 * - `onGuardado(contrato)`: tras guardar (con los avisos ya mostrados); quien lo pinta cierra el editor.
 * - `onCancelar()`: "Cancelar" o "Volver a la lista".
 */
const ContratoEditor = ({
    proyecto = null, contrato = null, procesoInicial = null, tiposContrato, procesosIniciales = null, proyectos, puedeEditar = true,
    recargar, onGuardado, onCancelar,
}) => {
    const [editingContrato, setEditingContrato] = useState(contrato);
    const [contratoActiveTab, setContratoActiveTab] = useState('informacion');
```

por:

```jsx
 * - `recargar(id)`: vuelve a traer los contratos tras un cambio en las pestañas; resuelve el contrato `id` recargado o null.
 * - `onGuardado(contrato)`: tras guardar (con los avisos ya mostrados); quien lo pinta cierra el editor.
 * - `onCancelar()`: "Cancelar" o "Volver a la lista".
 * - `onOcupado(ocupado)` (opcional): true al empezar a guardar y false al terminar (tras `onGuardado`,
 *   si se guardó), para que quien lo pinta no lo cierre a medias.
 */
const ContratoEditor = ({
    proyecto = null, contrato = null, procesoInicial = null, tiposContrato, procesosIniciales = null, proyectos, puedeEditar = true,
    recargar, onGuardado, onCancelar, onOcupado,
}) => {
    const [editingContrato, setEditingContrato] = useState(contrato);
    const [contratoActiveTab, setContratoActiveTab] = useState('informacion');
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (2 de 2):

```jsx
    const handleSaveContrato = async () => {
        if (enviandoContrato.current) return;
        enviandoContrato.current = true;
        try {
            await enviarContrato();
        } finally {
            enviandoContrato.current = false;
        }
    };
```

por:

```jsx
    const handleSaveContrato = async () => {
        if (enviandoContrato.current) return;
        enviandoContrato.current = true;
        onOcupado?.(true);
        try {
            await enviarContrato();
        } finally {
            enviandoContrato.current = false;
            onOcupado?.(false);
        }
    };
```

En `resources/js/utils/contratacion.js`, reemplazar (1 de 1):

```js

export const MAXIMO_OBSERVACION = 2000;

const etiqueta = (lista, valor) => lista.find(([v]) => v === valor)?.[1];

export const etiquetaPlataforma = (valor) => etiqueta(PLATAFORMAS, valor) ?? '—';
```

por:

```js

export const MAXIMO_OBSERVACION = 2000;

/** Estados en que un proceso admite contratos (ProcesoLicitacion::ADMITEN_CONTRATOS). */
export const ESTADOS_ADMITEN_CONTRATOS = ['adjudicado', 'celebrado'];

/** "Registrar contrato" desde un proceso: quien edita procesos, y solo si está adjudicado o celebrado. */
export const puedeRegistrarContrato = (usuario, proceso) => (
    puedeEditarProcesos(usuario) && ESTADOS_ADMITEN_CONTRATOS.includes(proceso?.estado)
);

/**
 * El modo de la lista de procesos tras guardar el contrato del proceso `procesoId`: se cierra si
 * sigue abierto ese contrato; cualquier otro modo abierto entretanto se conserva.
 */
export const modoTrasGuardarContrato = (modo, procesoId) => (
    modo?.tipo === 'contrato' && String(modo.proceso?.id) === String(procesoId) ? null : modo
);

const etiqueta = (lista, valor) => lista.find(([v]) => v === valor)?.[1];

export const etiquetaPlataforma = (valor) => etiqueta(PLATAFORMAS, valor) ?? '—';
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/contratacion.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1028 passed.

Run: `npm test`
Expected: 579 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/js/components/Parametros.jsx resources/js/components/ProcesosProyecto.jsx resources/js/components/contratos/ContratoEditor.jsx resources/js/utils/contratacion.js resources/js/utils/contratacion.test.js
git commit -m "feat: register a contract from an awarded process"
```

---

### Tarea 6: Compilar para producción

- [ ] **Paso 1: Compilar**

Run: `npm run production`
Expected: `Compiled Successfully`, sin errores.

Justo antes de comitear, comprobar que `public/js/app.js` es el de producción: minificado y sin comentarios `/***/` al inicio. Si `npm run watch` lo reescribió, compilar otra vez.

- [ ] **Paso 2: Commit**

```bash
git add public/js/app.js public/js/app.js.map public/mix-manifest.json
git commit -m "build: production bundle for procurement stage 3B"
```

Los archivos que no cambiaron se omiten.

---

### Tarea 7: Verificación en la copia descartable

Usa la copia y la foto de la tarea 1. Nunca `gest_pro`.

- [ ] **Paso 1: Comparar los listados con el código nuevo**

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion3.php" "$TMP_DIR/c4_despues.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c4_antes.json" "$TMP_DIR/c4_despues.json"
```

Expected: `SIN DIFERENCIAS`. Esta comparación cubre `GET /listarContratos`, `GET /proyectos` (contratos) y los expedientes. Cualquier diferencia detiene la etapa.

- [ ] **Paso 2: Probar en el navegador sobre la copia**

Compilar en desarrollo (`npx mix`) si `public/` no tiene el código de esta etapa. Arrancar el servidor desde `public/`, en un puerto libre: `artisan serve` recargaría `gest_pro` desde `.env`, y en el 8765 escucha otra aplicación. Comprobar con `netstat -ano | grep 8796` que nadie más escucha en ese puerto.

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8796 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

En Windows, ese servidor a veces corta `app.js` y la página queda en blanco: basta recargar.

Crear un Administrador y un usuario de Consulta de prueba, solo en la copia. Nunca usar sesiones de usuarios reales de `gest_pro`:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'verif@gestpro.test', 'rol' => App\Enums\Rol::Administrador]); App\Models\User::factory()->create(['email' => 'lector@gestpro.test', 'rol' => App\Enums\Rol::Consulta]);"
```

La contraseña es la predeterminada de `UserFactory`. Entrar por `http://127.0.0.1:8796/GestPro/`. Si el navegador guarda una sesión anterior, borrar `localStorage` antes de entrar. Si el panel del navegador está oculto, las animaciones de SweetAlert no terminan y un aviso puede quedar visible después de cerrarlo: no es un fallo de la aplicación.

1. **Barra lateral:** muestra "Contratos" en el trabajo diario y "Avance de contratos" en el análisis, cada uno con su icono.
2. **`/contratos`:** lista los 7 contratos con proyecto, estado, valor vigente y avances.
3. **"Nuevo contrato" sin proyecto:**
   - guardarlo con número, objeto, contratante, valor inicial y estado;
   - el módulo lo abre con sus pestañas;
   - registrar una adición en Modificaciones: el valor vigente sube.
4. **Filtros:** "Sin proyecto" muestra solo ese contrato, y la búsqueda por texto también lo encuentra.
5. **Registrar contrato desde un proceso:**
   - poner el proceso de la copia en `adjudicado` y con monto (por la pantalla de procesos o por la API);
   - en Parámetros → Proyectos → Procesos, el botón "Registrar contrato" abre el editor encima, con el proceso elegido y la precarga;
   - al guardar, propone pasar el proceso a celebrado, y la lista de procesos muestra "Celebrado" y 1 contrato;
   - "Contrato" del mismo proyecto ya incluye el contrato nuevo.
6. **Parámetros → Proyectos → "Contrato"** de un proyecto con contratos:
   - la lista y el formulario de un contrato existente se ven como antes, con las pestañas de actas y actividades y sus conteos;
   - guardar sin cambios funciona.
7. **Usuario de Consulta:** en `/contratos` no ve "Nuevo contrato". Un contrato abierto muestra "Solo lectura", sin botón de guardar y con los campos deshabilitados.

La consola del navegador no muestra errores nuevos. Los 401 de una sesión vieja y los 422 de las validaciones probadas son esperados.

- [ ] **Paso 3: Limpiar**

Detener el servidor, eliminar la copia y devolver `public/` a lo comiteado si se compiló en desarrollo:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 4: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas, 1028.

Run: `npm test`
Expected: pasan todas, 579.

---

### Tarea 8: Informar y pedir validación

- [ ] **Paso 1: Actualizar el estado de la propuesta**

En `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, reemplazar:

```markdown
La etapa 3A está validada y aplicada en `gest_pro` (2026-11-18).
```

por:

```markdown
La etapa 3A está validada y aplicada en `gest_pro` (2026-11-18). La etapa 3B (módulo Contratos, sin migraciones) está implementada y verificada en copia, pendiente de validar. Lo de SECOP II y la ficha del proyecto pasa a la etapa 3C.
```

```bash
git add docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md
git commit -m "docs: mark procurement stage 3B as implemented"
```

- [ ] **Paso 2: Informar**

Informar:
- los totales de pruebas;
- la comparación de los listados;
- lo revisado en el navegador.

Pedir que valide la etapa. Esta etapa no tiene migraciones: para usarla con `gest_pro` basta con desplegar el código y el compilado juntos, y con integrar la rama en `main` si el usuario lo pide.

---

## Criterio de la etapa

La etapa está lista cuando:

- el módulo Contratos lista, filtra, crea (también sin proyecto) y abre contratos con toda su ejecución;
- desde un proceso adjudicado se registra un contrato con precarga;
- la pantalla de contratos de Parámetros funciona igual que antes;
- `GET /listarContratos` y `GET /proyectos` devuelven lo mismo que antes con los datos reales.
