# Contratación, etapa 3C: SECOP II y ficha del proyecto. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** que un contrato se pueda traer de SECOP II en lugar de digitarlo y que se pueda actualizar desde allí eligiendo qué copiar. También que se listen los contratos que la empresa tiene como proveedor, y que cada proyecto tenga una ficha propia con su formulación, sus procesos y sus contratos.

**Arquitectura:**
- **Backend:**
  - `App\Services\Contratacion\SecopII` consulta los datos abiertos de SECOP II en datos.gov.co: por ID, proceso o número del contrato, o por el NIT del proveedor. `FilaSecop` traduce cada fila a los campos del contrato.
  - `SecopController` atiende `GET /secop/contratos`, `GET /secop/contratos-empresa` y `GET /secop/contratos/{id}`.
  - `POST /guardarContrato` acepta la referencia de SECOP II.
  - `GET /proyectos/{proyecto}/ficha` reúne lo que ya exponen `GET /proyectos`, el resumen de expedientes, los procesos y la búsqueda de contratos.
- **Pantallas:**
  - En `ContratoEditor`: buscar, importar, vincular, actualizar con diferencias y desvincular de SECOP II.
  - En el módulo Contratos: la vista "En SECOP II" con los contratos de la empresa.
  - `FichaProyecto.jsx` en `/proyectos/:proyectoId`, con enlaces desde Parámetros, el tablero y Ctrl+K.
- **Sin migraciones:** las columnas de SECOP II se crearon en la etapa 3A.

**Tecnologías:** Laravel 12 (cliente `Http`), PHP 8.2, MariaDB 10.4, React 18 con Laravel Mix y react-router, PHPUnit sobre SQLite en memoria, Vitest.

**Fuente:**
- La propuesta `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`.
- Las decisiones del usuario del 2026-11-18:
  - la empresa es **contratista**;
  - SECOP II funciona con "**buscar y copiar**" más "**Actualizar desde SECOP**" con diferencias y la lista de los contratos de la empresa por NIT;
  - si un contrato no está en SECOP II, se llena a mano;
  - la ficha del proyecto va en **página propia**.

**Estado de partida:** rama `feature/contratacion`, igual a `main` (`f246351`). Las etapas 3A y 3B están integradas. Pasan 1028 pruebas PHP y 579 JS.

---

## Reglas de ejecución

- **Esta etapa no tiene migraciones ni toca `gest_pro`.** La única base que se usa es una copia descartable, en las tareas 1 y 8.
- **Las pruebas nunca llaman a SECOP II de verdad:** usan `Http::fake()` y `Http::preventStrayRequests()`. Solo la prueba en el navegador de la tarea 8 consulta la API real, y solo con datos públicos de ejemplo.
- Commits autorizados solo en `feature/contratacion`. Sin push. Mensajes en inglés, terminados con la línea `Co-Authored-By` del modelo que comitea.
- Nomenclatura en español sin tildes para clases, métodos y archivos nuevos. Los textos para el usuario van en español con tuteo.
- Pruebas: `php artisan test` sin definir `DB_DATABASE`, para que use SQLite en memoria, y `npm test`. Si Vitest crea `resources/js/node_modules`, se borra.
- **Aplicar los bloques exactos.** Cada reemplazo aparece una sola vez en su archivo. Si no lo encuentras tal cual, detente y avisa. Se recomienda aplicarlos con un script que los lea del plan y exija una coincidencia por reemplazo, en lugar de copiarlos a mano.
- **No usar `git stash`:** el usuario tiene stashes propios.
- El usuario suele tener `npm run watch` corriendo, que reescribe `public/`. Solo la tarea 7 comitea `public/`, después de `npm run production`.
- No tocar ni comitear `public/images/marca-completa.png` ni `public/images/marca-completa1.png`: son cambios del usuario.
- `.env` está versionado: nunca se toca ni se agrega a un commit. La configuración nueva va en `config/services.php`, con valores por defecto y `env()` opcional.
- `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio. Hay que definirlo en cada comando, porque el estado de la terminal no se conserva.
- **El prefijo de la API (`/GestPro`) es también la base del SPA.** `GET /proyectos` es de la API, así que el front usa solo `/proyectos/:id`, y una prueba lo comprueba.

## Decisiones de este plan

**SECOP II: fuente y búsqueda**
- **Fuente:** `https://www.datos.gov.co/resource/jbjy-vk9h.json` (contratos electrónicos de SECOP II, Socrata, sin clave). En `config/services.php` → `secop` van `url`, `token` (opcional, `X-App-Token`), `timeout` (15 s) y `ca_bundle` (opcional, `SECOP_CA_BUNDLE`). `ca_bundle` es un paquete de certificados propio para equipos cuyo PHP no reconoce la cadena, por ejemplo con un antivirus que inspecciona HTTPS. La verificación nunca se desactiva.
- **Qué se busca:**
  - `CO1.PCCNTR.<n>`, como ID o dentro de un enlace, busca por `id_contrato`;
  - `CO1.BDOS.<n>` o `CO1.REQ.<n>` busca por `proceso_de_compra`;
  - un enlace del aviso (`CO1.NTC…`) o sin ID reconocible responde 422 y explica qué pegar;
  - cualquier otro texto es el número del contrato (`upper(referencia_del_contrato)`), con el NIT de la entidad si se da.
- **Seguridad de la consulta:** los literales se escapan (comillas duplicadas) y los parámetros viajan codificados. La URL sale solo de la configuración.
- **Contratos de la empresa:** se buscan por `documento_proveedor`, sin el dígito de verificación, hasta 50, de los más recientes a los más antiguos.
- **Errores:** sin conexión, tiempo agotado o una respuesta inesperada dan 502 con "No se pudo consultar SECOP II. Intenta de nuevo en unos minutos."
- **Permisos:** las rutas exigen `can:editar-datos` y `throttle:30,1`.

**SECOP II: mapeo**
- Número, objeto, entidad y su NIT, contratista y su documento, valor, fechas de firma, inicio y fin, tipo, estado e ID, proceso y enlace de SECOP II.
- **Tipo:** coincidencia sin mayúsculas ni tildes con los tipos activos, más las equivalencias "Suministros" → Suministro y "Acuerdo Marco de Precios" → Orden de compra.
- **Estado:**
  - "En ejecución", "Activo", "Modificado", "Prorrogado" y "cedido" pasan a `en_ejecucion`;
  - "Celebrado", "Firmado" y "Aprobado", a `celebrado`;
  - "Suspendido", a `suspendido`;
  - "terminado" y "Cerrado", a `terminado`;
  - "Liquidado", a `liquidado`;
  - "Cancelado" y "Anulado", a `anulado`;
  - con cualquier otro valor, la pantalla pide elegir.
- Cada resultado indica si ya está registrado en GestPro.

**Guardar la referencia**
- `secop_id_contrato` tiene la forma `CO1.PCCNTR.<n>`, hasta 40 caracteres, y es único. Si ya existe, responde 422 "ya está registrado: contrato N° …", también en una carrera.
- También se guardan `secop_proceso` y `url_secop`.
- Con `secop_consultado`, el servidor fija `secop_consultado_at`.

**Editor**
- **Contrato nuevo:** "Buscar en SECOP II" con "Usar este". Si el formulario ya tiene datos, pide confirmación. Los campos que el resultado no trae se vacían, para no mezclar dos contratos.
- **Contrato existente sin ID:** se vincula y luego se revisa la tabla de diferencias.
- **Contrato existente con ID:**
  - "Actualizar desde SECOP II" muestra solo las diferencias, con casillas.
  - El valor inicial, la fecha fin inicial y el estado aparecen sin marcar, con una nota.
  - Si no hay diferencias, la consulta igual queda registrada al guardar.
- **Desvincular:** "Desvincular de SECOP II", con confirmación, corrige un vínculo equivocado.

**Contratos de la empresa**
- Vista `/contratos?vista=secop` con el NIT de la empresa, recordado en el navegador y llevado en la URL.
- Cada contrato muestra "Ya registrado" (enlace) o "Importar", que abre el formulario precargado.
- Solo para quien puede editar.

**Ficha del proyecto**
- `/proyectos/:id` con cuatro bloques:
  - **General**;
  - **Formulación**: resumen del expediente y enlace;
  - **Procesos**: el diálogo existente;
  - **Contratos**: enlaces y "Nuevo contrato" con el proyecto ya elegido. Al volver, se regresa a la ficha.
- Los avances del expediente, de los procesos y de cada contrato se muestran por separado.
- Se llega desde el botón "Ficha" de Parámetros, "Ver ficha" en el tablero y los proyectos de Ctrl+K.

## Fuera de esta etapa

- La sincronización automática o periódica con SECOP II: la consulta siempre la inicia una persona.
- SECOP I.
- Las fases siguientes de la propuesta: expediente de contratación por proceso, ponderación, fechas y alertas, y revisión por etapa.

## Lo que ya se ensayó

El plan se ensayó completo en un clon descartable fuera del repositorio, y este documento se generó con el código de ese ensayo. Un script comprobó que aplicar sus bloques sobre la rama reproduce el ensayo exactamente.

- **Pruebas:** con el código de las tareas 2 a 6 pasan 1133 pruebas PHP y 665 JS. Cada tarea intermedia también pasa sus suites completas.
- **En una copia de `gest_pro` con los datos reales,** la foto de la tarea 1 no mostró diferencias entre el código anterior y el nuevo. La foto cubre `GET /listarContratos`, `GET /proyectos` con sus contratos y los expedientes.
- **En el navegador, sobre esa copia y con SECOP II real** (solo datos públicos de ejemplo):
  - **Importar:**
    - con `CO1.PCCNTR.4078659`, la búsqueda trajo el contrato `1-305-2022` del Municipio de Cartago;
    - "Usar este" llenó el número, el objeto, la entidad y su NIT, el contratista y su documento, $40.000.000, el estado "En ejecución", el tipo "Prestación de servicios" y las fechas;
    - al guardar, el contrato quedó con su ID, su enlace y la fecha de la consulta.
  - **Actualizar:** después de cambiar el objeto en GestPro, "Actualizar desde SECOP II" mostró solo esa diferencia.
  - **Contratos de la empresa:** con el NIT de proveedor `900919250` se listaron 50 contratos:
    - el importado apareció como "Ya registrado";
    - "Importar" en otro llenó el formulario, y su estado "Cerrado" se tradujo a "Terminado".
  - **Ficha:** el proyecto con contratos mostró General, Formulación (100 % del expediente), Procesos y Contratos:
    - el botón "Ficha" de Parámetros llevó allí;
    - un id inexistente mostró "El proyecto no existe.".
- **Certificados en este equipo:** un antivirus que inspecciona HTTPS impedía que el PHP de XAMPP verificara `www.datos.gov.co`. Por eso se agregó la opción `SECOP_CA_BUNDLE` (tarea 2), con la que la prueba usó un paquete de certificados propio sin desactivar la verificación.
- **Búsqueda por número:** tarda unos 5 segundos contra los datos reales, dentro del límite de 15.
- **Una revisión independiente** no encontró problemas de seguridad: ni inyección SoQL, ni SSRF, ni XSS; los permisos y el throttle están bien, y el único resiste la carrera. Sus hallazgos, uno medio y siete bajos, ya están corregidos en las tareas correspondientes:
  - importar un segundo resultado mezclaba datos de dos contratos;
  - el ID de SECOP II no tenía longitud máxima;
  - la carrera del único se detectaba por el texto del error;
  - el estado venía marcado para copiar;
  - una consulta sin diferencias no quedaba registrada;
  - desde la ficha, "Volver" no regresaba a la ficha;
  - el enlace "Ya registrado" descartaba el formulario;
  - faltaba poder desvincular un ID equivocado.

---

### Tarea 1: Punto de partida y foto con el código actual

La foto se toma **antes de cambiar código**. Sirve para comprobar, en la tarea 8, que los listados de contratos y de proyectos de la API devuelven lo mismo con el código nuevo (la ficha reutiliza `proyectos()`, que se reorganiza). La copia `gestpro_verif_tmp` se conserva hasta la tarea 8.

- [ ] **Paso 1: Comprobar la rama, el estado y las pruebas**

Run: `git branch --show-current`
Expected: `feature/contratacion`

Run: `git status --short`
Expected: como mucho, `public/` (si corre `npm run watch`) y las dos imágenes del usuario.

Run: `php artisan test`
Expected: pasan todas, 1028. Si alguna falla, detenerse y avisar.

Run: `npm test`
Expected: pasan todas, 579.

- [ ] **Paso 2: Crear la copia descartable**

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE IF EXISTS gestpro_verif_tmp; CREATE DATABASE gestpro_verif_tmp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro | /c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp
```

Si MySQL no responde (`ERROR 2002`), está apagado. Hay que pedirle al usuario que lo arranque desde XAMPP.

- [ ] **Paso 3: Crear los dos scripts de comprobación**

Si siguen en `$TMP_DIR` desde las etapas anteriores, `gestpro_foto_contratacion3.php` y `gestpro_comparar_contratacion3.php` se reutilizan tal cual. Si no, se crean con este contenido.

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
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion3.php" "$TMP_DIR/c5_antes.json" gestpro_verif_tmp
```

Expected: `Contratos: 7, procesos: 1, actas: 2, expedientes: 10, con API`, con las cifras actuales de `gest_pro`.

---

### Tarea 2: Servicio y API de SECOP II

Consulta de los datos abiertos de SECOP II (por ID, proceso, número o NIT de la empresa) con el mapeo a los campos del contrato, errores 502 claros y un paquete de certificados opcional.

**Files:**
- Create: `app/Exceptions/SecopNoDisponibleException.php`
- Create: `app/Http/Controllers/SecopController.php`
- Create: `app/Services/Contratacion/FilaSecop.php`
- Create: `app/Services/Contratacion/SecopII.php`
- Test (create): `tests/Feature/Contratacion/SecopApiTest.php`
- Test (create): `tests/Unit/Contratacion/FilaSecopTest.php`
- Modify: `config/services.php`
- Modify: `routes/contratacion.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/SecopApiTest.php`:

```php
<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\Contrato;
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** SECOP II (datos abiertos): buscar un contrato, los de la empresa por su NIT y uno por su ID. */
class SecopApiTest extends TestCase
{
    use RefreshDatabase;

    private const BUSCAR = '/GestPro/secop/contratos';

    private const EMPRESA = '/GestPro/secop/contratos-empresa';

    private const FUENTE = 'https://www.datos.gov.co/resource/jbjy-vk9h.json';

    private const NO_DISPONIBLE = 'No se pudo consultar SECOP II. Intenta de nuevo en unos minutos.';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.secop.token' => null]);
    }

    private function como(Rol|User $quien = Rol::Gestor): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    /** Una fila real de jbjy-vk9h (MUNICIPIO DE CARTAGO), recortada. */
    private function fila(array $mas = []): array
    {
        return [
            'nombre_entidad' => 'MUNICIPIO DE CARTAGO',
            'nit_entidad' => '891900493',
            'proceso_de_compra' => 'CO1.BDOS.3336966',
            'id_contrato' => 'CO1.PCCNTR.4078659',
            'referencia_del_contrato' => '1-305-2022',
            'estado_contrato' => 'En ejecución',
            'descripcion_del_proceso' => 'Prestación de servicios de apoyo',
            'objeto_del_contrato' => 'Prestación de servicios de apoyo a la gestión',
            'tipo_de_contrato' => 'Prestación de servicios',
            'modalidad_de_contratacion' => 'Contratación directa',
            'fecha_de_firma' => '2022-09-30T14:21:47.161',
            'fecha_de_inicio_del_contrato' => '2022-09-30T15:00:00.000',
            'fecha_de_fin_del_contrato' => '2022-12-30T23:59:00.000',
            'documento_proveedor' => '900919250',
            'proveedor_adjudicado' => 'CORPORACION SOLIDARIA',
            'valor_del_contrato' => '40000000',
            'valor_facturado' => '0',
            'valor_pagado' => '0',
            'dias_adicionados' => '0',
            'urlproceso' => ['url' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.3342434&isFromPublicArea=True&isModal=true&asPopupView=true'],
            ...$mas,
        ];
    }

    private function responder(array $filas = [], int $estado = 200): void
    {
        Http::fake(['www.datos.gov.co/*' => Http::response($filas, $estado)]);
    }

    /** Los parámetros de la consulta tal como viajaron en la URL. */
    private function consulta(Request $pedido): array
    {
        parse_str((string) parse_url($pedido->url(), PHP_URL_QUERY), $parametros);

        return $parametros;
    }

    private function unicoPedido(): Request
    {
        $pedidos = Http::recorded();
        $this->assertCount(1, $pedidos);

        return $pedidos[0][0];
    }

    // ------------------------------------------------------------------ buscar

    public function test_busca_por_el_id_del_contrato_aunque_venga_en_un_enlace(): void
    {
        $this->responder([$this->fila()]);

        $this->como()->getJson(self::BUSCAR . '?' . http_build_query(['q' => 'https://community.secop.gov.co/Public/Tendering/ContractNoticePhases/View?PPI=CO1.PPI.1&id=co1.pccntr.4078659']))
            ->assertOk()
            ->assertJsonPath('resultados.0.contrato.secop_id_contrato', 'CO1.PCCNTR.4078659');

        $pedido = $this->unicoPedido();
        $this->assertSame('GET', $pedido->method());
        $this->assertStringStartsWith(self::FUENTE . '?', $pedido->url());
        $this->assertSame(['id_contrato' => 'CO1.PCCNTR.4078659', '$limit' => '25'], $this->consulta($pedido));
    }

    public function test_el_resultado_trae_la_forma_acordada(): void
    {
        $tipo = TipoProceso::where('nombre', 'Prestación de servicios')->firstOrFail();
        $this->responder([$this->fila()]);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.PCCNTR.4078659')
            ->assertOk()
            ->assertExactJson(['resultados' => [[
                'contrato' => [
                    'n_contrato' => '1-305-2022',
                    'objeto' => 'Prestación de servicios de apoyo a la gestión',
                    'contratante' => 'MUNICIPIO DE CARTAGO',
                    'contratante_nit' => '891900493',
                    'contratista' => 'CORPORACION SOLIDARIA',
                    'contratista_identificacion' => '900919250',
                    'valor_inicial' => '40000000.00',
                    'fecha_suscripcion' => '2022-09-30',
                    'fecha_inicio' => '2022-09-30',
                    'fecha_fin_inicial' => '2022-12-30',
                    'tipo_contrato_id' => $tipo->id,
                    'estado' => 'en_ejecucion',
                    'secop_id_contrato' => 'CO1.PCCNTR.4078659',
                    'secop_proceso' => 'CO1.BDOS.3336966',
                    'url_secop' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.3342434&isFromPublicArea=True&isModal=true&asPopupView=true',
                ],
                'secop' => [
                    'tipo_contrato_secop' => 'Prestación de servicios',
                    'modalidad_secop' => 'Contratación directa',
                    'estado_secop' => 'En ejecución',
                    'dias_adicionados' => 0,
                    'valor_pagado' => '0.00',
                    'valor_facturado' => '0.00',
                ],
                'registrado' => null,
            ]]]);
    }

    public function test_un_tipo_inactivo_no_se_propone(): void
    {
        TipoProceso::where('nombre', 'Prestación de servicios')->update(['activo' => false]);
        $this->responder([$this->fila()]);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.PCCNTR.4078659')
            ->assertOk()
            ->assertJsonPath('resultados.0.contrato.tipo_contrato_id', null)
            ->assertJsonPath('resultados.0.secop.tipo_contrato_secop', 'Prestación de servicios');
    }

    public function test_la_equivalencia_de_tipo_usa_el_catalogo_activo(): void
    {
        $suministro = TipoProceso::where('nombre', 'Suministro')->firstOrFail();
        $this->responder([$this->fila(['tipo_de_contrato' => 'Suministros']), $this->fila(['tipo_de_contrato' => 'Acuerdo Marco de Precios'])]);
        TipoProceso::where('nombre', 'Orden de compra')->update(['activo' => false]);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.BDOS.3336966')
            ->assertOk()
            ->assertJsonPath('resultados.0.contrato.tipo_contrato_id', $suministro->id)
            ->assertJsonPath('resultados.1.contrato.tipo_contrato_id', null);
    }

    public function test_marca_los_que_ya_estan_registrados(): void
    {
        $c = Contrato::create([
            'n_contrato' => 'C-305', 'objeto' => 'Apoyo', 'valor_inicial' => 1, 'valor' => 1,
            'estado' => 'en_ejecucion', 'secop_id_contrato' => 'CO1.PCCNTR.4078659',
        ]);
        $this->responder([$this->fila(), $this->fila(['id_contrato' => 'CO1.PCCNTR.999', 'referencia_del_contrato' => '1-306-2022'])]);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.BDOS.3336966')
            ->assertOk()
            ->assertJsonPath('resultados.0.registrado', ['id' => $c->id, 'n_contrato' => 'C-305'])
            ->assertJsonPath('resultados.1.registrado', null);
    }

    public function test_busca_por_el_proceso_de_compra(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '?q=' . urlencode(' co1.req.12345 '))
            ->assertOk()
            ->assertExactJson(['resultados' => []]);

        $this->assertSame(['proceso_de_compra' => 'CO1.REQ.12345', '$limit' => '25'], $this->consulta($this->unicoPedido()));
    }

    public function test_el_enlace_del_aviso_no_sirve_para_buscar(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '?' . http_build_query(['q' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.3342434&isFromPublicArea=True']))
            ->assertStatus(422)
            ->assertJsonPath('errors.q.0', 'Ese enlace es del aviso del proceso. Pega el enlace o el ID del contrato (CO1.PCCNTR…) o búscalo por su número.');
        Http::assertNothingSent();
    }

    public function test_un_enlace_sin_id_del_contrato_no_se_busca_como_numero(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '?' . http_build_query(['q' => 'https://community.secop.gov.co/Public/Tendering/ContractNoticePhases/View?PPI=CO1.PPI.1']))
            ->assertStatus(422)
            ->assertJsonPath('errors.q.0', 'Ese enlace no trae el ID del contrato. Pega el ID (CO1.PCCNTR…) o busca el contrato por su número.');
        Http::assertNothingSent();
    }

    public function test_busca_por_el_numero_del_contrato_y_el_nit_de_la_entidad(): void
    {
        $this->responder([$this->fila()]);

        $this->como()->getJson(self::BUSCAR . '?' . http_build_query(['q' => ' 1-305-2022 ', 'nit' => '891.900.493-1']))
            ->assertOk()
            ->assertJsonCount(1, 'resultados');

        $this->assertSame([
            '$where' => "upper(referencia_del_contrato) = '1-305-2022' AND nit_entidad = '891900493'",
            '$order' => 'fecha_de_firma DESC',
            '$limit' => '25',
        ], $this->consulta($this->unicoPedido()));
    }

    public function test_el_numero_va_en_mayusculas_y_sin_nit_si_no_viene(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '?q=lp-001-2026')->assertOk();

        $this->assertSame("upper(referencia_del_contrato) = 'LP-001-2026'", $this->consulta($this->unicoPedido())['$where']);
    }

    public function test_las_comillas_del_usuario_se_escapan_en_soql(): void
    {
        $this->responder([]);
        $texto = "1-305' OR '1'='1";

        $this->como()->getJson(self::BUSCAR . '?' . http_build_query(['q' => $texto]))->assertOk();

        $this->assertSame("upper(referencia_del_contrato) = '1-305'' OR ''1''=''1'", $this->consulta($this->unicoPedido())['$where']);
    }

    public function test_la_url_lleva_el_texto_codificado(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '?' . http_build_query(['q' => "año 5 & \$limit=1#x'"]))->assertOk();

        $pedido = $this->unicoPedido();
        $url = $pedido->url();
        $this->assertStringNotContainsString(' ', $url);
        $this->assertStringNotContainsString("'", $url);
        $this->assertStringNotContainsString('#', $url);
        $this->assertStringNotContainsString('ñ', $url);
        $this->assertSame(1, substr_count($url, '$limit=') + substr_count($url, '%24limit='));
        $this->assertSame([
            '$where' => "upper(referencia_del_contrato) = 'AÑO 5 & \$LIMIT=1#X'''",
            '$order' => 'fecha_de_firma DESC',
            '$limit' => '25',
        ], $this->consulta($pedido));
    }

    public function test_valida_la_busqueda(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR)->assertStatus(422)->assertJsonPath('errors.q.0', 'Escribe el número, el ID o el enlace del contrato.');
        $this->como()->getJson(self::BUSCAR . '?q=%20%20')->assertStatus(422)->assertJsonPath('errors.q.0', 'Escribe el número, el ID o el enlace del contrato.');
        $this->como()->getJson(self::BUSCAR . '?q=' . str_repeat('1', 101))->assertStatus(422)->assertJsonPath('errors.q.0', 'El número del contrato admite hasta 100 caracteres.');
        $this->como()->getJson(self::BUSCAR . '?q=' . str_repeat('a', 501))->assertStatus(422)->assertJsonPath('errors.q.0', 'En la búsqueda caben hasta 500 caracteres.');
        $this->como()->getJson(self::BUSCAR . '?q=1-305&nit=' . urlencode("8919' OR 1=1"))->assertStatus(422)->assertJsonPath('errors.nit.0', 'Escribe el NIT solo con números (puede llevar puntos y el dígito de verificación con guion).');
        Http::assertNothingSent();
    }

    public function test_un_id_largo_del_aviso_cabe_en_la_busqueda(): void
    {
        $this->responder([$this->fila()]);
        $enlace = 'https://community.secop.gov.co/Public/Tendering/ContractNoticePhases/View?PPI=CO1.PPI.20000000&isFromPublicArea=True&isModal=False&contrato=CO1.PCCNTR.4078659';

        $this->como()->getJson(self::BUSCAR . '?' . http_build_query(['q' => $enlace]))->assertOk();
    }

    // ------------------------------------------------------------------ de la empresa

    public function test_lista_los_contratos_de_la_empresa_por_su_nit(): void
    {
        $this->responder([$this->fila()]);

        $this->como()->getJson(self::EMPRESA . '?nit=' . urlencode('900.919.250-3'))
            ->assertOk()
            ->assertJsonPath('resultados.0.contrato.contratista_identificacion', '900919250')
            ->assertJsonPath('resultados.0.registrado', null);

        $this->assertSame([
            'documento_proveedor' => '900919250',
            '$order' => 'fecha_de_firma DESC',
            '$limit' => '50',
        ], $this->consulta($this->unicoPedido()));
    }

    public function test_valida_el_nit_de_la_empresa(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::EMPRESA)->assertStatus(422)->assertJsonPath('errors.nit.0', 'Escribe el NIT de la empresa.');
        $this->como()->getJson(self::EMPRESA . '?nit=900919250-12')->assertStatus(422)->assertJsonPath('errors.nit.0', 'Escribe el NIT solo con números (puede llevar puntos y el dígito de verificación con guion).');
        $this->como()->getJson(self::EMPRESA . '?nit=' . urlencode("900' OR '1'='1"))->assertStatus(422);
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------ por id

    public function test_trae_un_contrato_por_su_id(): void
    {
        $this->responder([$this->fila(['estado_contrato' => 'Cerrado'])]);

        $this->como()->getJson(self::BUSCAR . '/CO1.PCCNTR.4078659')
            ->assertOk()
            ->assertJsonPath('resultado.contrato.estado', 'terminado')
            ->assertJsonPath('resultado.secop.estado_secop', 'Cerrado')
            ->assertJsonPath('resultado.registrado', null);

        $this->assertSame(['id_contrato' => 'CO1.PCCNTR.4078659', '$limit' => '1'], $this->consulta($this->unicoPedido()));
    }

    public function test_por_id_responde_404_si_secop_no_lo_tiene(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '/CO1.PCCNTR.1')
            ->assertNotFound()
            ->assertExactJson(['mensaje' => 'SECOP II no tiene ese contrato.']);
    }

    public function test_por_id_valida_el_formato(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '/CO1.BDOS.3336966')
            ->assertStatus(422)
            ->assertJsonPath('errors.idContrato.0', 'El ID del contrato de SECOP II tiene la forma CO1.PCCNTR.123456.');
        $this->como()->getJson(self::BUSCAR . '/' . rawurlencode("CO1.PCCNTR.1' OR '1'='1"))->assertStatus(422);
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------ errores de SECOP

    public function test_si_secop_falla_responde_502(): void
    {
        $this->responder(['message' => 'Internal error'], 500);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.PCCNTR.4078659')->assertStatus(502)->assertExactJson(['mensaje' => self::NO_DISPONIBLE]);
        $this->como()->getJson(self::EMPRESA . '?nit=900919250')->assertStatus(502)->assertExactJson(['mensaje' => self::NO_DISPONIBLE]);
        $this->como()->getJson(self::BUSCAR . '/CO1.PCCNTR.4078659')->assertStatus(502)->assertExactJson(['mensaje' => self::NO_DISPONIBLE]);
    }

    public function test_si_no_hay_conexion_responde_502(): void
    {
        Http::fake(['www.datos.gov.co/*' => Http::failedConnection()]);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.PCCNTR.4078659')->assertStatus(502)->assertExactJson(['mensaje' => self::NO_DISPONIBLE]);
    }

    public function test_si_secop_responde_algo_que_no_es_una_lista_responde_502(): void
    {
        Http::fake(['www.datos.gov.co/*' => Http::response('<html>mantenimiento</html>', 200)]);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.PCCNTR.4078659')->assertStatus(502)->assertExactJson(['mensaje' => self::NO_DISPONIBLE]);
    }

    // ------------------------------------------------------------------ configuración y permisos

    public function test_usa_la_url_y_el_token_configurados(): void
    {
        config(['services.secop.url' => 'https://www.datos.gov.co/resource/otra.json', 'services.secop.token' => 'abc123']);
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.PCCNTR.1')->assertOk();

        $pedido = $this->unicoPedido();
        $this->assertStringStartsWith('https://www.datos.gov.co/resource/otra.json?', $pedido->url());
        $this->assertSame(['abc123'], $pedido->header('X-App-Token'));
    }

    public function test_sin_token_no_manda_la_cabecera(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.PCCNTR.1')->assertOk();

        $this->assertFalse($this->unicoPedido()->hasHeader('X-App-Token'));
    }

    public function test_solo_quien_puede_editar_consulta_secop(): void
    {
        $this->responder([]);

        $this->como(Rol::Consulta)->getJson(self::BUSCAR . '?q=CO1.PCCNTR.1')->assertForbidden();
        $this->como(Rol::Consulta)->getJson(self::EMPRESA . '?nit=900919250')->assertForbidden();
        $this->como(Rol::Consulta)->getJson(self::BUSCAR . '/CO1.PCCNTR.1')->assertForbidden();
        $this->como(Rol::Administrador)->getJson(self::BUSCAR . '?q=CO1.PCCNTR.1')->assertOk();
        Http::assertSentCount(1);
    }

    public function test_sin_sesion_responde_401(): void
    {
        $this->getJson(self::BUSCAR . '?q=CO1.PCCNTR.1')->assertUnauthorized();
    }
}
```

Crear `tests/Unit/Contratacion/FilaSecopTest.php`:

```php
<?php

namespace Tests\Unit\Contratacion;

use App\Services\Contratacion\FilaSecop;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Mapeo de una fila de SECOP II (datos abiertos, jbjy-vk9h) a los campos del contrato. */
class FilaSecopTest extends TestCase
{
    private const TIPOS = ['Obra' => 1, 'Interventoría' => 2, 'Suministro' => 4, 'Prestación de servicios' => 5, 'Orden de compra' => 6];

    /** Una fila real (MUNICIPIO DE CARTAGO, CO1.PCCNTR.4078659), recortada. */
    private function fila(array $mas = []): array
    {
        return [
            'nombre_entidad' => 'MUNICIPIO DE CARTAGO',
            'nit_entidad' => '891900493',
            'proceso_de_compra' => 'CO1.BDOS.3336966',
            'id_contrato' => 'CO1.PCCNTR.4078659',
            'referencia_del_contrato' => '1-305-2022',
            'estado_contrato' => 'En ejecución',
            'descripcion_del_proceso' => 'Descripción del proceso',
            'objeto_del_contrato' => 'Prestación de servicios de apoyo a la gestión',
            'tipo_de_contrato' => 'Prestación de servicios',
            'modalidad_de_contratacion' => 'Contratación directa',
            'fecha_de_firma' => '2022-09-30T14:21:47.161',
            'fecha_de_inicio_del_contrato' => '2022-09-30T15:00:00.000',
            'fecha_de_fin_del_contrato' => '2022-12-30T23:59:00.000',
            'documento_proveedor' => '900919250',
            'proveedor_adjudicado' => 'CORPORACION SOLIDARIA',
            'valor_del_contrato' => '40000000',
            'valor_facturado' => '13333333',
            'valor_pagado' => '0',
            'dias_adicionados' => '0',
            'urlproceso' => ['url' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.3342434&isFromPublicArea=True&isModal=true&asPopupView=true'],
            ...$mas,
        ];
    }

    public function test_mapea_una_fila_real_a_los_campos_del_contrato(): void
    {
        $this->assertSame([
            'contrato' => [
                'n_contrato' => '1-305-2022',
                'objeto' => 'Prestación de servicios de apoyo a la gestión',
                'contratante' => 'MUNICIPIO DE CARTAGO',
                'contratante_nit' => '891900493',
                'contratista' => 'CORPORACION SOLIDARIA',
                'contratista_identificacion' => '900919250',
                'valor_inicial' => '40000000.00',
                'fecha_suscripcion' => '2022-09-30',
                'fecha_inicio' => '2022-09-30',
                'fecha_fin_inicial' => '2022-12-30',
                'tipo_contrato_id' => 5,
                'estado' => 'en_ejecucion',
                'secop_id_contrato' => 'CO1.PCCNTR.4078659',
                'secop_proceso' => 'CO1.BDOS.3336966',
                'url_secop' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.3342434&isFromPublicArea=True&isModal=true&asPopupView=true',
            ],
            'secop' => [
                'tipo_contrato_secop' => 'Prestación de servicios',
                'modalidad_secop' => 'Contratación directa',
                'estado_secop' => 'En ejecución',
                'dias_adicionados' => 0,
                'valor_pagado' => '0.00',
                'valor_facturado' => '13333333.00',
            ],
        ], FilaSecop::mapear($this->fila(), self::TIPOS));
    }

    public static function estados(): array
    {
        return [
            ['En ejecución', 'en_ejecucion'], ['EN EJECUCION', 'en_ejecucion'], ['Activo', 'en_ejecucion'],
            ['Modificado', 'en_ejecucion'], ['Prorrogado', 'en_ejecucion'], ['Cedido', 'en_ejecucion'],
            ['Celebrado', 'celebrado'], ['Firmado', 'celebrado'], ['Suspendido', 'suspendido'],
            ['Terminado', 'terminado'], ['Cerrado', 'terminado'], ['  liquidado ', 'liquidado'],
            ['Cancelado', 'anulado'], ['Anulado', 'anulado'], ['Aprobado', 'celebrado'], ['terminado', 'terminado'],
            ['cedido', 'en_ejecucion'], ['En aprobación', null], ['enviado Proveedor', null], ['Borrador', null], ['', null],
        ];
    }

    #[DataProvider('estados')]
    public function test_mapea_el_estado_sin_mayusculas_ni_tildes(string $secop, ?string $estado): void
    {
        $mapeo = FilaSecop::mapear($this->fila(['estado_contrato' => $secop]), self::TIPOS);

        $this->assertSame($estado, $mapeo['contrato']['estado']);
    }

    public function test_el_tipo_coincide_sin_mayusculas_ni_tildes_o_queda_vacio(): void
    {
        $this->assertSame(2, FilaSecop::mapear($this->fila(['tipo_de_contrato' => 'INTERVENTORIA']), self::TIPOS)['contrato']['tipo_contrato_id']);

        $otro = FilaSecop::mapear($this->fila(['tipo_de_contrato' => 'Compraventa']), self::TIPOS);
        $this->assertNull($otro['contrato']['tipo_contrato_id']);
        $this->assertSame('Compraventa', $otro['secop']['tipo_contrato_secop']);
    }

    public static function equivalencias(): array
    {
        return [
            ['Suministros', 4], ['SUMINISTROS', 4], ['Acuerdo Marco de Precios', 6], ['acuerdo marco de precios', 6],
            ['Suministro', 4], ['Comodato', null], ['No Definido', null],
        ];
    }

    #[DataProvider('equivalencias')]
    public function test_algunos_tipos_de_secop_tienen_equivalente_en_el_catalogo(string $secop, ?int $id): void
    {
        $this->assertSame($id, FilaSecop::mapear($this->fila(['tipo_de_contrato' => $secop]), self::TIPOS)['contrato']['tipo_contrato_id']);
    }

    public function test_la_equivalencia_sin_tipo_activo_de_destino_queda_vacia(): void
    {
        $sinDestino = ['Obra' => 1, 'Prestación de servicios' => 5];

        $this->assertNull(FilaSecop::mapear($this->fila(['tipo_de_contrato' => 'Suministros']), $sinDestino)['contrato']['tipo_contrato_id']);
        $this->assertNull(FilaSecop::mapear($this->fila(['tipo_de_contrato' => 'Acuerdo Marco de Precios']), $sinDestino)['contrato']['tipo_contrato_id']);
    }

    public function test_sin_objeto_del_contrato_usa_la_descripcion_del_proceso(): void
    {
        $sinObjeto = $this->fila(['objeto_del_contrato' => '  ']);

        $this->assertSame('Descripción del proceso', FilaSecop::mapear($sinObjeto, self::TIPOS)['contrato']['objeto']);
    }

    public function test_campos_ausentes_o_invalidos_quedan_vacios(): void
    {
        $mapeo = FilaSecop::mapear([
            'id_contrato' => 'CO1.PCCNTR.1',
            'valor_del_contrato' => 'no es un número',
            'fecha_de_firma' => '30/09/2022',
            'dias_adicionados' => 'x',
            'urlproceso' => ['url' => 'javascript:alert(1)'],
        ], self::TIPOS);

        $this->assertNull($mapeo['contrato']['n_contrato']);
        $this->assertNull($mapeo['contrato']['objeto']);
        $this->assertNull($mapeo['contrato']['valor_inicial']);
        $this->assertNull($mapeo['contrato']['fecha_suscripcion']);
        $this->assertNull($mapeo['contrato']['fecha_inicio']);
        $this->assertNull($mapeo['contrato']['tipo_contrato_id']);
        $this->assertNull($mapeo['contrato']['estado']);
        $this->assertNull($mapeo['contrato']['url_secop']);
        $this->assertNull($mapeo['secop']['dias_adicionados']);
        $this->assertNull($mapeo['secop']['valor_pagado']);
        $this->assertSame('CO1.PCCNTR.1', $mapeo['contrato']['secop_id_contrato']);
    }

    public function test_el_valor_lleva_dos_decimales_redondeados(): void
    {
        $this->assertSame('1234.57', FilaSecop::mapear($this->fila(['valor_del_contrato' => '1234.567']), self::TIPOS)['contrato']['valor_inicial']);
        $this->assertSame('15.50', FilaSecop::mapear($this->fila(['valor_del_contrato' => '15.5']), self::TIPOS)['contrato']['valor_inicial']);
    }

    public function test_el_enlace_tambien_puede_venir_como_texto(): void
    {
        $mapeo = FilaSecop::mapear($this->fila(['urlproceso' => 'https://community.secop.gov.co/x']), self::TIPOS);

        $this->assertSame('https://community.secop.gov.co/x', $mapeo['contrato']['url_secop']);
    }

    public function test_dias_adicionados_es_un_entero(): void
    {
        $this->assertSame(30, FilaSecop::mapear($this->fila(['dias_adicionados' => '30']), self::TIPOS)['secop']['dias_adicionados']);
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/SecopApiTest.php tests/Unit/Contratacion/FilaSecopTest.php`
Expected: FALLAN 61 y pasa 1: no existen el servicio de SECOP II, el mapeo `FilaSecop` ni las rutas `/secop/...`.

- [ ] **Paso 3: Implementar**

Crear `app/Exceptions/SecopNoDisponibleException.php`:

```php
<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * SECOP II no respondió o respondió algo que no sirve (sin conexión, tiempo agotado, HTTP distinto
 * de 200 o un cuerpo que no es una lista). Laravel llama a render(): responde 502 con un mensaje
 * para la pantalla; el detalle queda en el mensaje de la excepción para el registro.
 */
class SecopNoDisponibleException extends RuntimeException
{
    public const MENSAJE = 'No se pudo consultar SECOP II. Intenta de nuevo en unos minutos.';

    public function render(): JsonResponse
    {
        return response()->json(['mensaje' => self::MENSAJE], 502);
    }
}
```

Crear `app/Http/Controllers/SecopController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\Contratacion\SecopII;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consulta de contratos en SECOP II para importarlos (can:editar-datos). Cada resultado tiene
 * `contrato` (campos para el formulario), `secop` (lo informativo) y `registrado`; ver SecopII.
 * Si SECOP II falla responde 502 {mensaje} (SecopNoDisponibleException).
 */
class SecopController extends Controller
{
    private const MENSAJES = [
        'q.required' => 'Escribe el número, el ID o el enlace del contrato.',
        'q.string' => 'Revisa la búsqueda: debe ser un texto.',
        'q.max' => 'En la búsqueda caben hasta 500 caracteres.',
        'nit.required' => 'Escribe el NIT de la empresa.',
        'nit.string' => SecopII::NIT_INVALIDO,
        'nit.max' => SecopII::NIT_INVALIDO,
        'nit.regex' => SecopII::NIT_INVALIDO,
    ];

    public function __construct(private readonly SecopII $secop)
    {
    }

    /** `q`: número, ID o enlace del contrato (un enlace puede ser largo); `nit`: de la entidad, opcional. */
    public function buscar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'q' => ['bail', 'required', 'string', 'max:500'],
            'nit' => ['sometimes', 'nullable', 'bail', 'string', 'max:20', 'regex:' . SecopII::FORMA_NIT],
        ], self::MENSAJES);

        return response()->json(['resultados' => $this->secop->buscar($datos['q'], $datos['nit'] ?? null)]);
    }

    /** Los contratos de la empresa (el proveedor) por su NIT. */
    public function deEmpresa(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nit' => ['bail', 'required', 'string', 'max:20', 'regex:' . SecopII::FORMA_NIT],
        ], self::MENSAJES);

        return response()->json(['resultados' => $this->secop->deEmpresa($datos['nit'])]);
    }

    /** Uno por su ID de SECOP II, para "Actualizar desde SECOP II". */
    public function porId(string $idContrato): JsonResponse
    {
        $resultado = $this->secop->porId($idContrato);
        if ($resultado === null) {
            return response()->json(['mensaje' => 'SECOP II no tiene ese contrato.'], 404);
        }

        return response()->json(['resultado' => $resultado]);
    }
}
```

Crear `app/Services/Contratacion/FilaSecop.php`:

```php
<?php

namespace App\Services\Contratacion;

use App\Models\Contrato;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Str;

/**
 * Una fila de SECOP II (datos abiertos, jbjy-vk9h) llevada a los campos del contrato. Función
 * pura: los tipos de contrato activos llegan como argumento.
 *
 * - `contrato`: lo que se puede copiar al formulario, con los nombres de POST /guardarContrato.
 *   Lo que falta o no se entiende queda en null (la pantalla lo pide).
 * - `secop`: lo que SECOP II dice y GestPro no guarda; sirve para avisar (el estado o el tipo sin
 *   equivalente, los días adicionados que hay que registrar como prórroga).
 */
final class FilaSecop
{
    /**
     * Estado de SECOP II (sin mayúsculas ni tildes) => estado del contrato. Otro (p. ej. "En
     * aprobación", "enviado Proveedor", "Borrador"): null, y la pantalla pide elegirlo.
     */
    private const ESTADOS = [
        'en ejecucion' => Contrato::EN_EJECUCION,
        'activo' => Contrato::EN_EJECUCION,
        'modificado' => Contrato::EN_EJECUCION,
        'prorrogado' => Contrato::EN_EJECUCION,
        'cedido' => Contrato::EN_EJECUCION,
        'celebrado' => Contrato::CELEBRADO,
        'firmado' => Contrato::CELEBRADO,
        'aprobado' => Contrato::CELEBRADO,
        'suspendido' => Contrato::SUSPENDIDO,
        'terminado' => Contrato::TERMINADO,
        'cerrado' => Contrato::TERMINADO,
        'liquidado' => Contrato::LIQUIDADO,
        'cancelado' => Contrato::ANULADO,
        'anulado' => Contrato::ANULADO,
    ];

    /**
     * Tipo de SECOP II (normalizado) => nombre del tipo sembrado en el catálogo, para los que no
     * coinciden por nombre. Si ese tipo no existe o está inactivo, no se propone ninguno.
     */
    private const TIPOS_EQUIVALENTES = [
        'suministros' => 'Suministro',
        'acuerdo marco de precios' => 'Orden de compra',
    ];

    /**
     * @param array<string, mixed> $fila
     * @param array<string, int> $tiposActivos nombre del tipo de contrato => id
     * @return array{contrato: array<string, mixed>, secop: array<string, mixed>}
     */
    public static function mapear(array $fila, array $tiposActivos): array
    {
        $tipoSecop = self::texto($fila, 'tipo_de_contrato');
        $estadoSecop = self::texto($fila, 'estado_contrato');

        return [
            'contrato' => [
                'n_contrato' => self::texto($fila, 'referencia_del_contrato'),
                'objeto' => self::texto($fila, 'objeto_del_contrato') ?? self::texto($fila, 'descripcion_del_proceso'),
                'contratante' => self::texto($fila, 'nombre_entidad'),
                'contratante_nit' => self::texto($fila, 'nit_entidad'),
                'contratista' => self::texto($fila, 'proveedor_adjudicado'),
                'contratista_identificacion' => self::texto($fila, 'documento_proveedor'),
                'valor_inicial' => self::valor($fila, 'valor_del_contrato'),
                'fecha_suscripcion' => self::fecha($fila, 'fecha_de_firma'),
                'fecha_inicio' => self::fecha($fila, 'fecha_de_inicio_del_contrato'),
                'fecha_fin_inicial' => self::fecha($fila, 'fecha_de_fin_del_contrato'),
                'tipo_contrato_id' => self::tipo($tipoSecop, $tiposActivos),
                'estado' => $estadoSecop === null ? null : (self::ESTADOS[self::normalizar($estadoSecop)] ?? null),
                'secop_id_contrato' => self::texto($fila, 'id_contrato'),
                'secop_proceso' => self::texto($fila, 'proceso_de_compra'),
                'url_secop' => self::enlace($fila['urlproceso'] ?? null),
            ],
            'secop' => [
                'tipo_contrato_secop' => $tipoSecop,
                'modalidad_secop' => self::texto($fila, 'modalidad_de_contratacion'),
                'estado_secop' => $estadoSecop,
                'dias_adicionados' => self::entero($fila, 'dias_adicionados'),
                'valor_pagado' => self::valor($fila, 'valor_pagado'),
                'valor_facturado' => self::valor($fila, 'valor_facturado'),
            ],
        ];
    }

    /** Sin mayúsculas, tildes ni espacios sobrantes. */
    public static function normalizar(string $texto): string
    {
        return preg_replace('/\s+/', ' ', Str::lower(Str::ascii(trim($texto))));
    }

    private static function texto(array $fila, string $campo): ?string
    {
        $valor = $fila[$campo] ?? null;
        if (! is_string($valor) && ! is_int($valor) && ! is_float($valor)) {
            return null;
        }
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    /** Con 2 decimales, como las columnas decimal(15, 2). */
    private static function valor(array $fila, string $campo): ?string
    {
        $texto = self::texto($fila, $campo);
        if ($texto === null) {
            return null;
        }
        try {
            return (string) BigDecimal::of($texto)->toScale(2, RoundingMode::HALF_UP);
        } catch (MathException) {
            return null;
        }
    }

    /** SECOP II da fecha y hora local (2022-09-30T14:21:47.161): solo se toma la fecha. */
    private static function fecha(array $fila, string $campo): ?string
    {
        $texto = self::texto($fila, $campo);
        if ($texto === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $texto, $m) !== 1) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "{$m[1]}-{$m[2]}-{$m[3]}" : null;
    }

    private static function entero(array $fila, string $campo): ?int
    {
        $texto = self::texto($fila, $campo);

        return $texto !== null && preg_match('/^-?\d+$/', $texto) === 1 ? (int) $texto : null;
    }

    /** @param array<string, int> $tiposActivos */
    private static function tipo(?string $tipoSecop, array $tiposActivos): ?int
    {
        if ($tipoSecop === null) {
            return null;
        }
        $buscado = self::normalizar($tipoSecop);
        $equivalente = self::TIPOS_EQUIVALENTES[$buscado] ?? null;
        foreach ([$buscado, ...($equivalente !== null ? [self::normalizar($equivalente)] : [])] as $nombreBuscado) {
            foreach ($tiposActivos as $nombre => $id) {
                if (self::normalizar((string) $nombre) === $nombreBuscado) {
                    return $id;
                }
            }
        }

        return null;
    }

    /** `urlproceso` viene como {url: …}; se acepta también un texto. Solo http o https. */
    private static function enlace(mixed $urlproceso): ?string
    {
        $url = is_array($urlproceso) ? ($urlproceso['url'] ?? null) : $urlproceso;
        if (! is_string($url)) {
            return null;
        }
        $url = trim($url);

        return strlen($url) <= 500 && preg_match('#^https?://#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false
            ? $url
            : null;
    }
}
```

Crear `app/Services/Contratacion/SecopII.php`:

```php
<?php

namespace App\Services\Contratacion;

use App\Exceptions\SecopNoDisponibleException;
use App\Models\Contrato;
use App\Models\TipoProceso;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Consulta de contratos en SECOP II (datos abiertos de datos.gov.co, conjunto jbjy-vk9h, API
 * Socrata). Solo lee: lo encontrado se muestra y la pantalla decide qué copiar al contrato.
 *
 * - Los parámetros viajan como query de la petición (Http los codifica): nunca se pegan a la URL.
 * - Los valores escritos por el usuario que van dentro de SoQL ($where) se escapan duplicando las
 *   comillas simples; los ID (CO1.PCCNTR.…, CO1.BDOS.…) y los NIT ya llegan reducidos a su forma.
 * - Cada resultado es FilaSecop::mapear() más `registrado`: {id, n_contrato} del contrato de
 *   GestPro con ese secop_id_contrato, o null.
 * - Sin conexión, tiempo agotado, HTTP distinto de 200 o una respuesta que no es una lista:
 *   SecopNoDisponibleException (502).
 */
final class SecopII
{
    public const LIMITE_BUSQUEDA = 25;

    public const LIMITE_EMPRESA = 50;

    public const MAX_NUMERO = 100;

    public const AVISO_NTC = 'Ese enlace es del aviso del proceso. Pega el enlace o el ID del contrato (CO1.PCCNTR…) o búscalo por su número.';

    public const ENLACE_SIN_ID = 'Ese enlace no trae el ID del contrato. Pega el ID (CO1.PCCNTR…) o busca el contrato por su número.';

    public const NUMERO_LARGO = 'El número del contrato admite hasta 100 caracteres.';

    public const NIT_INVALIDO = 'Escribe el NIT solo con números (puede llevar puntos y el dígito de verificación con guion).';

    /** El NIT como se escribe: cifras con puntos o espacios y, opcional, -dígito de verificación. */
    public const FORMA_NIT = '/^[0-9][0-9. ]*(-[0-9])?$/';

    public const FORMA_ID_CONTRATO = '/^CO1\.PCCNTR\.\d+$/';

    /**
     * Interpreta lo que pegó el usuario: un ID o un enlace del contrato (CO1.PCCNTR.…), uno del
     * proceso de compra (CO1.BDOS.… o CO1.REQ.…) o el número del contrato, este último junto con
     * el NIT de la entidad si viene. El enlace del aviso (CO1.NTC.…) u otro enlace sin ID se
     * rechazan con un mensaje para `q`.
     *
     * @return list<array<string, mixed>>
     */
    public function buscar(string $texto, ?string $nitEntidad = null): array
    {
        $texto = trim($texto);

        if (preg_match('/CO1\.PCCNTR\.\d+/i', $texto, $m) === 1) {
            $consulta = ['id_contrato' => strtoupper($m[0])];
        } elseif (preg_match('/CO1\.(BDOS|REQ)\.\d+/i', $texto, $m) === 1) {
            $consulta = ['proceso_de_compra' => strtoupper($m[0])];
        } elseif (preg_match('/CO1\.NTC\.\d+/i', $texto) === 1) {
            throw ValidationException::withMessages(['q' => self::AVISO_NTC]);
        } elseif (preg_match('#^https?://|secop\.gov\.co#i', $texto) === 1) {
            throw ValidationException::withMessages(['q' => self::ENLACE_SIN_ID]);
        } else {
            if (mb_strlen($texto) > self::MAX_NUMERO) {
                throw ValidationException::withMessages(['q' => self::NUMERO_LARGO]);
            }
            $where = 'upper(referencia_del_contrato) = ' . self::literal(mb_strtoupper($texto));
            $nit = $nitEntidad !== null && trim($nitEntidad) !== '' ? self::nit($nitEntidad, 'nit') : null;
            if ($nit !== null) {
                $where .= ' AND nit_entidad = ' . self::literal($nit);
            }
            $consulta = ['$where' => $where, '$order' => 'fecha_de_firma DESC'];
        }

        return $this->presentar($this->consultar([...$consulta, '$limit' => self::LIMITE_BUSQUEDA]));
    }

    /**
     * Los contratos en que la empresa es el proveedor, del más reciente (por fecha de firma) al
     * más antiguo. El NIT puede traer puntos y el dígito de verificación: se quitan.
     *
     * @return list<array<string, mixed>>
     */
    public function deEmpresa(string $nit): array
    {
        return $this->presentar($this->consultar([
            'documento_proveedor' => self::nit($nit, 'nit'),
            '$order' => 'fecha_de_firma DESC',
            '$limit' => self::LIMITE_EMPRESA,
        ]));
    }

    /** Un contrato por su ID de SECOP II (CO1.PCCNTR.…), o null si SECOP II no lo tiene. */
    public function porId(string $idContrato): ?array
    {
        $id = strtoupper(trim($idContrato));
        if (preg_match(self::FORMA_ID_CONTRATO, $id) !== 1) {
            throw ValidationException::withMessages(['idContrato' => 'El ID del contrato de SECOP II tiene la forma CO1.PCCNTR.123456.']);
        }

        return $this->presentar($this->consultar(['id_contrato' => $id, '$limit' => 1]))[0] ?? null;
    }

    /** Solo las cifras del NIT, sin puntos, espacios ni el dígito de verificación. */
    public static function nit(string $nit, string $campo): string
    {
        $nit = trim($nit);
        if (preg_match(self::FORMA_NIT, $nit) !== 1) {
            throw ValidationException::withMessages([$campo => self::NIT_INVALIDO]);
        }

        return preg_replace('/[^0-9]/', '', preg_replace('/-[0-9]$/', '', $nit));
    }

    /** Un texto como literal de SoQL: entre comillas simples, con las internas duplicadas. */
    public static function literal(string $valor): string
    {
        return "'" . str_replace("'", "''", $valor) . "'";
    }

    /**
     * @param array<string, string|int> $consulta
     * @return list<array<string, mixed>>
     */
    private function consultar(array $consulta): array
    {
        $config = config('services.secop');
        $pedido = Http::acceptJson()->timeout((int) ($config['timeout'] ?? 15));
        if (! empty($config['token'])) {
            $pedido = $pedido->withHeaders(['X-App-Token' => $config['token']]);
        }
        // Paquete de certificados propio, para equipos cuyo PHP no reconoce la cadena (por
        // ejemplo, con un antivirus que inspecciona HTTPS). Nunca se desactiva la verificación.
        if (! empty($config['ca_bundle'])) {
            $pedido = $pedido->withOptions(['verify' => $config['ca_bundle']]);
        }

        try {
            /** @var Response $respuesta */
            $respuesta = $pedido->get($config['url'], $consulta);
        } catch (ConnectionException $e) {
            throw new SecopNoDisponibleException('SECOP II sin conexión: ' . $e->getMessage(), 0, $e);
        }
        if ($respuesta->status() !== 200) {
            throw new SecopNoDisponibleException('SECOP II respondió HTTP ' . $respuesta->status());
        }
        $filas = $respuesta->json();
        if (! is_array($filas) || ! array_is_list($filas)) {
            throw new SecopNoDisponibleException('SECOP II respondió algo que no es una lista.');
        }

        return array_values(array_filter($filas, 'is_array'));
    }

    /**
     * @param list<array<string, mixed>> $filas
     * @return list<array<string, mixed>>
     */
    private function presentar(array $filas): array
    {
        if ($filas === []) {
            return [];
        }
        $tipos = TipoProceso::where('activo', true)->orderBy('id')->pluck('id', 'nombre')->map(fn ($id) => (int) $id)->all();
        $mapeadas = array_map(fn (array $fila) => FilaSecop::mapear($fila, $tipos), $filas);

        $ids = array_values(array_unique(array_filter(array_map(fn (array $m) => $m['contrato']['secop_id_contrato'], $mapeadas))));
        $registrados = $ids === [] ? collect() : Contrato::whereIn('secop_id_contrato', $ids)
            ->get(['id', 'n_contrato', 'secop_id_contrato'])
            ->keyBy('secop_id_contrato');

        return array_map(function (array $m) use ($registrados) {
            $c = $registrados->get($m['contrato']['secop_id_contrato']);

            return [...$m, 'registrado' => $c ? ['id' => $c->id, 'n_contrato' => $c->n_contrato] : null];
        }, $mapeadas);
    }
}
```

En `config/services.php`, reemplazar (1 de 1):

```php
        ],
    ],

];
```

por:

```php
        ],
    ],

    // SECOP II, contratos electrónicos (datos abiertos, API Socrata pública). El token es opcional:
    // sin él, datos.gov.co limita más las consultas por dirección IP.
    'secop' => [
        'url' => env('SECOP_URL', 'https://www.datos.gov.co/resource/jbjy-vk9h.json'),
        'token' => env('SECOP_TOKEN'),
        'timeout' => (int) env('SECOP_TIMEOUT', 15),
        'ca_bundle' => env('SECOP_CA_BUNDLE'),
    ],

];
```

En `routes/contratacion.php`, reemplazar (1 de 2):

```php

use App\Http\Controllers\ContratoController;
use App\Http\Controllers\ProcesoContratacionController;
use Illuminate\Support\Facades\Route;

// Lectura: cualquier usuario autenticado.
```

por:

```php

use App\Http\Controllers\ContratoController;
use App\Http\Controllers\ProcesoContratacionController;
use App\Http\Controllers\SecopController;
use Illuminate\Support\Facades\Route;

// Lectura: cualquier usuario autenticado.
```

En `routes/contratacion.php`, reemplazar (2 de 2):

```php
    Route::delete('/procesos/{proceso}', [ProcesoContratacionController::class, 'destroy'])
        ->name('procesos.destroy');
});
```

por:

```php
    Route::delete('/procesos/{proceso}', [ProcesoContratacionController::class, 'destroy'])
        ->name('procesos.destroy');
});

// SECOP II (datos abiertos): consultar para importar contratos. Administrador o Gestor, con
// límite de consultas: cada una llama a datos.gov.co.
Route::middleware(['can:editar-datos', 'throttle:30,1'])->prefix('secop')->group(function () {
    Route::get('/contratos', [SecopController::class, 'buscar'])
        ->name('secop.buscar');
    Route::get('/contratos-empresa', [SecopController::class, 'deEmpresa'])
        ->name('secop.empresa');
    Route::get('/contratos/{idContrato}', [SecopController::class, 'porId'])
        ->name('secop.contrato');
});
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/SecopApiTest.php tests/Unit/Contratacion/FilaSecopTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1090 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Exceptions/SecopNoDisponibleException.php app/Http/Controllers/SecopController.php app/Services/Contratacion/FilaSecop.php app/Services/Contratacion/SecopII.php tests/Feature/Contratacion/SecopApiTest.php tests/Unit/Contratacion/FilaSecopTest.php config/services.php routes/contratacion.php
git commit -m "feat: SECOP II open data service and API"
```

---

### Tarea 3: Guardar contratos con la referencia de SECOP II

`POST /guardarContrato` acepta el ID, el proceso y el enlace de SECOP II, con el ID único, y registra la fecha de la consulta.

**Files:**
- Test (create): `tests/Feature/Contratacion/ContratosSecopApiTest.php`
- Modify: `app/Http/Controllers/ContratoController.php`
- Modify: `app/Services/Contratacion/ContratosService.php`
- Test: `tests/Feature/Contratacion/ContratosApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/ContratosSecopApiTest.php`:

```php
<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Http\Controllers\ContratoController;
use App\Models\Contrato;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Contratos: guardar con la referencia de SECOP II. */
class ContratosSecopApiTest extends TestCase
{
    use RefreshDatabase;

    private const GUARDAR = '/GestPro/guardarContrato';

    private Proyecto $proyecto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function guardar(array $formContrato)
    {
        $usuario = User::factory()->create(['rol' => Rol::Gestor]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario))
            ->postJson(self::GUARDAR, ['formContrato' => $formContrato, 'anexos' => []]);
    }

    private function datos(array $mas = []): array
    {
        return [
            'n_contrato' => 'C-001-2026',
            'objeto' => 'Construcción del acueducto veredal',
            'valor_inicial' => 1000000,
            'estado' => 'en_ejecucion',
            'proyecto' => $this->proyecto->id,
            ...$mas,
        ];
    }

    private function crear(array $mas = []): Contrato
    {
        return Contrato::findOrFail($this->guardar($this->datos($mas))->assertOk()->json('contrato.id'));
    }

    private function editar(Contrato $contrato, array $mas = [])
    {
        return $this->guardar([
            ...$this->datos(),
            'id' => $contrato->id,
            'actualizado' => $contrato->fresh()->updated_at?->format('Y-m-d H:i:s'),
            ...$mas,
        ]);
    }

    /** @return list<array{0: string, 1: ?array, 2: ?array}> */
    private function eventos(Contrato $contrato): array
    {
        return DB::table('checklist_historial')
            ->where('entidad_tipo', 'contrato')->where('entidad_id', $contrato->id)
            ->orderBy('id')->get()
            ->map(fn ($h) => [$h->evento, json_decode($h->antes, true), json_decode($h->despues, true)])
            ->all();
    }

    public function test_se_crea_con_la_referencia_de_secop_y_el_id_queda_en_mayusculas(): void
    {
        Carbon::setTestNow('2026-11-18 10:00:00');
        $enlace = 'https://community.secop.gov.co/Public/Tendering/ContractNoticeManagement/Index?id=1';

        $r = $this->guardar($this->datos([
            'secop_id_contrato' => ' co1.pccntr.8912345 ',
            'secop_proceso' => ' CO1.REQ.123 ',
            'url_secop' => $enlace,
            'secop_consultado' => true,
        ]))->assertOk();

        $r->assertJsonPath('contrato.secop_id_contrato', 'CO1.PCCNTR.8912345')
            ->assertJsonPath('contrato.secop_proceso', 'CO1.REQ.123')
            ->assertJsonPath('contrato.url_secop', $enlace)
            ->assertJsonPath('contrato.secop_consultado_at', '2026-11-18 10:00:00');
        $this->assertSame('CO1.PCCNTR.8912345', Contrato::findOrFail($r->json('contrato.id'))->secop_id_contrato);
    }

    public function test_sin_consultado_o_con_falso_no_hay_fecha_de_consulta(): void
    {
        $this->assertNull($this->crear(['secop_id_contrato' => 'CO1.PCCNTR.1'])->secop_consultado_at);

        $falso = $this->guardar($this->datos(['n_contrato' => 'C-2', 'secop_id_contrato' => 'CO1.PCCNTR.2', 'secop_consultado' => false]))->assertOk();
        $this->assertNull($falso->json('contrato.secop_consultado_at'));
    }

    public function test_el_pedido_no_puede_fijar_la_fecha_de_consulta(): void
    {
        $r = $this->guardar($this->datos(['secop_consultado_at' => '2020-01-01 00:00:00']))->assertOk();

        $this->assertNull($r->json('contrato.secop_consultado_at'));
    }

    public static function pedidosInvalidos(): array
    {
        $forma = 'El ID del contrato de SECOP II tiene la forma CO1.PCCNTR.123456.';
        $web = 'El enlace de SECOP II debe empezar por http:// o https://.';

        return [
            'id incompleto' => [['secop_id_contrato' => 'CO1.PCCNTR'], 'secop_id_contrato', $forma],
            'id de un proceso' => [['secop_id_contrato' => 'CO1.REQ.55'], 'secop_id_contrato', $forma],
            'id con letras al final' => [['secop_id_contrato' => 'CO1.PCCNTR.12a'], 'secop_id_contrato', $forma],
            // 41 caracteres: la columna es varchar(40).
            'id largo' => [['secop_id_contrato' => 'CO1.PCCNTR.' . str_repeat('1', 30)], 'secop_id_contrato', 'El ID del contrato de SECOP II admite hasta 40 caracteres.'],
            'proceso largo' => [['secop_proceso' => str_repeat('a', 41)], 'secop_proceso', 'El proceso de SECOP II admite hasta 40 caracteres.'],
            'enlace ftp' => [['url_secop' => 'ftp://secop.gov.co/x'], 'url_secop', $web],
            'enlace javascript' => [['url_secop' => 'javascript:alert(1)'], 'url_secop', $web],
            'enlace largo' => [['url_secop' => 'https://secop.gov.co/' . str_repeat('a', 480)], 'url_secop', 'El enlace de SECOP II admite hasta 500 caracteres.'],
            'consultado no booleano' => [['secop_consultado' => 'tal vez'], 'secop_consultado', 'Revisa la consulta a SECOP II: debe ser sí o no.'],
        ];
    }

    #[DataProvider('pedidosInvalidos')]
    public function test_un_pedido_invalido_se_rechaza_por_campo(array $cambios, string $campo, string $mensaje): void
    {
        $this->guardar($this->datos($cambios))->assertStatus(422)->assertJsonPath("errors.$campo.0", $mensaje);
        $this->assertSame(0, Contrato::count());
    }

    public function test_el_id_de_secop_es_unico_al_crear_sin_distinguir_mayusculas(): void
    {
        $this->crear(['n_contrato' => 'C-ORIGINAL', 'secop_id_contrato' => 'CO1.PCCNTR.77']);

        $this->guardar($this->datos(['n_contrato' => 'C-2', 'secop_id_contrato' => 'co1.pccntr.77']))
            ->assertStatus(422)
            ->assertJsonPath('errors.secop_id_contrato.0', 'Ese contrato de SECOP II ya está registrado: contrato N° C-ORIGINAL.');
        $this->assertSame(1, Contrato::count());
    }

    public function test_al_editar_el_id_es_unico_pero_el_propio_no_choca(): void
    {
        $uno = $this->crear(['n_contrato' => 'C-1', 'secop_id_contrato' => 'CO1.PCCNTR.1']);
        $dos = $this->crear(['n_contrato' => 'C-2', 'secop_id_contrato' => 'CO1.PCCNTR.2']);

        $this->editar($dos, ['n_contrato' => 'C-2', 'secop_id_contrato' => 'CO1.PCCNTR.1'])
            ->assertStatus(422)
            ->assertJsonPath('errors.secop_id_contrato.0', 'Ese contrato de SECOP II ya está registrado: contrato N° C-1.');
        $this->assertSame('CO1.PCCNTR.2', $dos->fresh()->secop_id_contrato);

        // Reenviar el suyo (en minúsculas) no es un choque.
        $this->editar($uno, ['n_contrato' => 'C-1', 'secop_id_contrato' => 'co1.pccntr.1', 'objeto' => 'Otro objeto'])->assertOk();
        $this->assertSame('Otro objeto', $uno->fresh()->objeto);
    }

    public function test_si_dos_guardan_a_la_vez_el_indice_unico_da_el_mismo_422(): void
    {
        // El otro guardado gana la carrera: su fila entra justo después de que este comprobó que el
        // ID estaba libre y antes de su insert.
        $competidor = false;
        DB::listen(function ($consulta) use (&$competidor) {
            if (! $competidor && str_starts_with($consulta->sql, 'select') && str_contains($consulta->sql, '"secop_id_contrato" = ?')) {
                $competidor = true;
                DB::table('contratos')->insert([
                    'n_contrato' => 'C-GANADOR', 'objeto' => 'x', 'valor' => 1, 'valor_inicial' => 1,
                    'estado' => 'celebrado', 'secop_id_contrato' => 'CO1.PCCNTR.9',
                ]);
            }
        });

        $this->guardar($this->datos(['secop_id_contrato' => 'CO1.PCCNTR.9']))
            ->assertStatus(422)
            ->assertJsonPath('errors.secop_id_contrato.0', 'Ese contrato de SECOP II ya está registrado: contrato N° C-GANADOR.');
        $this->assertSame(1, Contrato::count());
    }

    public function test_un_id_de_40_caracteres_se_acepta(): void
    {
        $id = 'CO1.PCCNTR.' . str_repeat('1', 29);

        $this->assertSame($id, $this->crear(['secop_id_contrato' => $id])->secop_id_contrato);
    }

    public function test_el_choque_de_otro_indice_unico_no_se_confunde_con_el_de_secop(): void
    {
        // Otro índice único de la tabla: el insert que lo viola menciona la columna secop_id_contrato
        // en su SQL, pero el choque no es del ID de SECOP II y no debe responderse como tal.
        DB::statement('CREATE UNIQUE INDEX contratos_n_contrato_prueba_unique ON contratos (n_contrato)');
        $this->crear(['n_contrato' => 'C-REPETIDO']);

        $r = $this->guardar($this->datos(['n_contrato' => 'C-REPETIDO', 'secop_id_contrato' => 'CO1.PCCNTR.31']));

        $r->assertStatus(500);
        $this->assertNull($r->json('errors.secop_id_contrato'));
        $this->assertSame(1, Contrato::count());
    }

    public static function mensajesDelMotor(): array
    {
        return [
            'mariadb, el índice del ID' => ["Duplicate entry 'CO1.PCCNTR.9' for key 'contratos_secop_id_contrato_unique'", true],
            'mysql 8, con la tabla' => ["Duplicate entry 'CO1.PCCNTR.9' for key 'contratos.contratos_secop_id_contrato_unique'", true],
            'sqlite, la columna' => ['UNIQUE constraint failed: contratos.secop_id_contrato', true],
            'mariadb, otro índice' => ["Duplicate entry 'C-1' for key 'contratos_n_contrato_unique'", false],
            'sqlite, otra columna' => ['UNIQUE constraint failed: contratos.n_contrato', false],
            'sqlite, índice compuesto' => ['UNIQUE constraint failed: contratos.secop_id_contrato, contratos.n_contrato', false],
            'sin detalle' => [null, false],
        ];
    }

    #[DataProvider('mensajesDelMotor')]
    public function test_el_choque_se_reconoce_por_el_indice_en_el_mensaje_del_motor(?string $detalle, bool $esDelIdSecop): void
    {
        $pdo = new \PDOException('SQLSTATE[23000]: Integrity constraint violation');
        $pdo->errorInfo = $detalle === null ? null : ['23000', 1062, $detalle];
        // El SQL siempre menciona la columna (en el insert y en un JSON del historial).
        $e = new UniqueConstraintViolationException('mysql', 'insert into contratos (secop_id_contrato) values (?)', ['{"secop_id_contrato":1}'], $pdo);

        $metodo = new \ReflectionMethod(ContratoController::class, 'esChoqueDelIdSecop');

        $this->assertSame($esDelIdSecop, $metodo->invoke(null, $e));
    }

    public function test_varios_contratos_pueden_quedar_sin_id_de_secop(): void
    {
        $this->crear(['n_contrato' => 'C-1']);
        $this->crear(['n_contrato' => 'C-2', 'secop_id_contrato' => null]);
        $this->crear(['n_contrato' => 'C-3', 'secop_id_contrato' => '']);

        $this->assertSame(3, Contrato::whereNull('secop_id_contrato')->count());
    }

    public function test_al_editar_un_campo_secop_ausente_no_cambia_y_null_lo_borra(): void
    {
        Carbon::setTestNow('2026-11-18 10:00:00');
        $contrato = $this->crear([
            'secop_id_contrato' => 'CO1.PCCNTR.5', 'secop_proceso' => 'CO1.REQ.5',
            'url_secop' => 'https://secop.gov.co/5', 'secop_consultado' => true,
        ]);
        Carbon::setTestNow('2026-11-18 11:00:00');

        // Ausentes: no cambian (tampoco la fecha de consulta).
        $this->editar($contrato, ['objeto' => 'Otro objeto'])->assertOk()
            ->assertJsonPath('contrato.secop_id_contrato', 'CO1.PCCNTR.5')
            ->assertJsonPath('contrato.secop_proceso', 'CO1.REQ.5')
            ->assertJsonPath('contrato.url_secop', 'https://secop.gov.co/5')
            ->assertJsonPath('contrato.secop_consultado_at', '2026-11-18 10:00:00');

        // Null los borra.
        Carbon::setTestNow('2026-11-18 12:00:00');
        $this->editar($contrato, ['secop_id_contrato' => null, 'secop_proceso' => null, 'url_secop' => null])->assertOk()
            ->assertJsonPath('contrato.secop_id_contrato', null)
            ->assertJsonPath('contrato.secop_proceso', null)
            ->assertJsonPath('contrato.url_secop', null)
            ->assertJsonPath('contrato.secop_consultado_at', '2026-11-18 10:00:00');
    }

    public function test_consultar_de_nuevo_actualiza_la_fecha_de_consulta(): void
    {
        Carbon::setTestNow('2026-11-18 10:00:00');
        $contrato = $this->crear(['secop_id_contrato' => 'CO1.PCCNTR.5', 'secop_consultado' => true]);
        Carbon::setTestNow('2026-11-19 09:30:00');

        $this->editar($contrato, ['secop_consultado' => true])->assertOk()
            ->assertJsonPath('contrato.secop_consultado_at', '2026-11-19 09:30:00');
        $this->assertSame('2026-11-19 09:30:00', $contrato->fresh()->secop_consultado_at->format('Y-m-d H:i:s'));
    }

    public function test_los_campos_secop_entran_al_historial(): void
    {
        Carbon::setTestNow('2026-11-18 10:00:00');
        $contrato = $this->crear([
            'secop_id_contrato' => 'co1.pccntr.5', 'secop_proceso' => 'CO1.REQ.5',
            'url_secop' => 'https://secop.gov.co/5', 'secop_consultado' => true,
        ]);

        [$evento, $antes, $despues] = $this->eventos($contrato)[0];
        $this->assertSame('contrato_creado', $evento);
        $this->assertNull($antes);
        $this->assertSame('CO1.PCCNTR.5', $despues['secop_id_contrato']);
        $this->assertSame('CO1.REQ.5', $despues['secop_proceso']);
        $this->assertSame('https://secop.gov.co/5', $despues['url_secop']);
        $this->assertSame('2026-11-18 10:00:00', $despues['secop_consultado_at']);

        Carbon::setTestNow('2026-11-19 08:00:00');
        $this->editar($contrato, ['secop_id_contrato' => 'CO1.PCCNTR.6', 'url_secop' => 'https://secop.gov.co/6', 'secop_consultado' => true])->assertOk();

        [$evento, $antes, $despues] = $this->eventos($contrato)[1];
        $this->assertSame('contrato_editado', $evento);
        $this->assertSame(
            ['secop_id_contrato' => 'CO1.PCCNTR.5', 'url_secop' => 'https://secop.gov.co/5', 'secop_consultado_at' => '2026-11-18 10:00:00'],
            $antes
        );
        $this->assertSame(
            ['secop_id_contrato' => 'CO1.PCCNTR.6', 'url_secop' => 'https://secop.gov.co/6', 'secop_consultado_at' => '2026-11-19 08:00:00'],
            $despues
        );
    }

    public function test_reenviar_los_mismos_datos_secop_no_registra_cambios(): void
    {
        $contrato = $this->crear(['secop_id_contrato' => 'CO1.PCCNTR.5', 'secop_proceso' => 'CO1.REQ.5']);

        $this->editar($contrato, ['secop_id_contrato' => 'co1.pccntr.5', 'secop_proceso' => 'CO1.REQ.5'])->assertOk();

        $this->assertCount(1, $this->eventos($contrato));
    }
}
```

En `tests/Feature/Contratacion/ContratosApiTest.php`, reemplazar (1 de 2):

```php
            // Lo que la pantalla manda de más y no se escribe desde aquí.
            'valor' => 999,
            'fecha_fin' => '2030-01-01',
            'secop_id_contrato' => 'CO1.PCCNTR.1',
            'actividades' => [],
        ]), [], $gestor)->assertOk();
```

por:

```php
            // Lo que la pantalla manda de más y no se escribe desde aquí.
            'valor' => 999,
            'fecha_fin' => '2030-01-01',
            'secop_consultado_at' => '2020-01-01 00:00:00',
            'actividades' => [],
        ]), [], $gestor)->assertOk();
```

En `tests/Feature/Contratacion/ContratosApiTest.php`, reemplazar (2 de 2):

```php
            'porcentaje_anticipo' => null,
            'avance_fisico' => null,
            'avance_financiero' => null,
        ], $despues);
    }
```

por:

```php
            'porcentaje_anticipo' => null,
            'avance_fisico' => null,
            'avance_financiero' => null,
            'secop_id_contrato' => null,
            'secop_proceso' => null,
            'url_secop' => null,
            'secop_consultado_at' => null,
        ], $despues);
    }
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/ContratosSecopApiTest.php tests/Feature/Contratacion/ContratosApiTest.php`
Expected: FALLAN 25 y pasan 82: `POST /guardarContrato` todavía ignora `secop_id_contrato`, `secop_proceso`, `url_secop` y `secop_consultado`.

- [ ] **Paso 3: Implementar**

En `app/Http/Controllers/ContratoController.php`, reemplazar (1 de 9):

```php
use App\Models\Contrato;
use App\Services\Contratacion\ContratosService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
```

por:

```php
use App\Models\Contrato;
use App\Services\Contratacion\ContratosService;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (2 de 9):

```php
        'avance_fisico.max' => 'El avance físico va de 0 a 100.',
        'avance_financiero.min' => 'El avance financiero va de 0 a 100.',
        'avance_financiero.max' => 'El avance financiero va de 0 a 100.',
        'actualizado.present' => 'Falta la versión del contrato que estabas editando. Ciérralo y vuelve a abrirlo.',
        'actualizado.date' => 'La versión del contrato no es válida. Ciérralo y vuelve a abrirlo.',
        'anexos.*.descripcion.required' => 'Escribe la descripción del anexo.',
```

por:

```php
        'avance_fisico.max' => 'El avance físico va de 0 a 100.',
        'avance_financiero.min' => 'El avance financiero va de 0 a 100.',
        'avance_financiero.max' => 'El avance financiero va de 0 a 100.',
        'secop_id_contrato.max' => 'El ID del contrato de SECOP II admite hasta 40 caracteres.',
        'secop_id_contrato.regex' => 'El ID del contrato de SECOP II tiene la forma CO1.PCCNTR.123456.',
        'secop_proceso.max' => 'El proceso de SECOP II admite hasta 40 caracteres.',
        'url_secop.regex' => 'El enlace de SECOP II debe empezar por http:// o https://.',
        'url_secop.max' => 'El enlace de SECOP II admite hasta 500 caracteres.',
        'actualizado.present' => 'Falta la versión del contrato que estabas editando. Ciérralo y vuelve a abrirlo.',
        'actualizado.date' => 'La versión del contrato no es válida. Ciérralo y vuelve a abrirlo.',
        'anexos.*.descripcion.required' => 'Escribe la descripción del anexo.',
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (3 de 9):

```php
        'porcentaje_anticipo' => 'el porcentaje de anticipo',
        'avance_fisico' => 'el avance físico',
        'avance_financiero' => 'el avance financiero',
        'actualizado' => 'la versión del contrato',
        'anexos' => 'los anexos',
        'anexos.*.descripcion' => 'la descripción del anexo',
```

por:

```php
        'porcentaje_anticipo' => 'el porcentaje de anticipo',
        'avance_fisico' => 'el avance físico',
        'avance_financiero' => 'el avance financiero',
        'secop_id_contrato' => 'el ID del contrato de SECOP II',
        'secop_proceso' => 'el proceso de SECOP II',
        'url_secop' => 'el enlace de SECOP II',
        'secop_consultado' => 'la consulta a SECOP II',
        'actualizado' => 'la versión del contrato',
        'anexos' => 'los anexos',
        'anexos.*.descripcion' => 'la descripción del anexo',
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (4 de 9):

```php
    // Las columnas de valor son decimal(15, 2): 13 cifras enteras y 2 decimales.
    private const CIFRAS_ENTERAS = 13;

    // Lo que sube subirAnexo: un archivo directo en public/anexos_contratos. Otra ruta se rechaza:
    // al eliminar el contrato se borra el archivo.
    private const RUTA_ANEXO = '#^anexos_contratos/[^/\\\\]+$#';
```

por:

```php
    // Las columnas de valor son decimal(15, 2): 13 cifras enteras y 2 decimales.
    private const CIFRAS_ENTERAS = 13;

    // El ID de un contrato en SECOP II (sin distinguir mayúsculas) y su enlace.
    private const FORMATO_SECOP = '/^CO1\.PCCNTR\.\d+$/i';

    private const URL_SECOP = '#^https?://\S+$#i';

    // Lo que sube subirAnexo: un archivo directo en public/anexos_contratos. Otra ruta se rechaza:
    // al eliminar el contrato se borra el archivo.
    private const RUTA_ANEXO = '#^anexos_contratos/[^/\\\\]+$#';
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (5 de 9):

```php
    /**
     * Crea (sin `formContrato.id`) o edita un contrato. El pedido es `formContrato` con los campos
     * y `anexos`; al editar, los anexos que trae reemplazan a los que había. Los campos que no se
     * escriben desde aquí (el valor y la fecha fin vigentes, la referencia de SECOP, lo que la
     * pantalla manda de más) se ignoran.
     */
    public function guardar(Request $request): JsonResponse
    {
```

por:

```php
    /**
     * Crea (sin `formContrato.id`) o edita un contrato. El pedido es `formContrato` con los campos
     * y `anexos`; al editar, los anexos que trae reemplazan a los que había. Los campos que no se
     * escriben desde aquí (el valor y la fecha fin vigentes, lo que la pantalla manda de más) se
     * ignoran. La referencia de SECOP II se guarda con `secop_consultado`: si es verdadero, el
     * servidor pone la fecha de la consulta (`secop_consultado_at` del pedido nunca se acepta).
     */
    public function guardar(Request $request): JsonResponse
    {
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (6 de 9):

```php
        $form = is_array($form) ? $form : [];
        $editar = ! empty($form['id']);

        $validador = Validator::make($form, $this->reglas($editar), self::MENSAJES, self::ATRIBUTOS);
        $validadorAnexos = Validator::make($request->only('anexos'), $this->reglasAnexos(), self::MENSAJES, self::ATRIBUTOS);
        // Los dos a la vez: así la pantalla muestra todos los errores de una vez.
        $fallaForm = $validador->fails();
```

por:

```php
        $form = is_array($form) ? $form : [];
        $editar = ! empty($form['id']);

        $validador = Validator::make($form, $this->reglas($editar ? (int) $form['id'] : null), self::MENSAJES, self::ATRIBUTOS);
        $validadorAnexos = Validator::make($request->only('anexos'), $this->reglasAnexos(), self::MENSAJES, self::ATRIBUTOS);
        // Los dos a la vez: así la pantalla muestra todos los errores de una vez.
        $fallaForm = $validador->fails();
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (7 de 9):

```php
            throw ValidationException::withMessages([...$validador->errors()->toArray(), ...$validadorAnexos->errors()->toArray()]);
        }
        $datos = $validador->validated();
        // `anexos: null` es como no enviarlos: se conservan.
        $anexos = $request->input('anexos') !== null ? $validadorAnexos->validated()['anexos'] : null;
        unset($datos['id'], $datos['actualizado']);

        if ($editar) {
            $contrato = Contrato::find($form['id']);
            if ($contrato === null) {
                return response()->json(['mensaje' => 'El contrato no existe.'], 404);
            }
            $guardado = $this->contratos->editar($contrato, $datos, $anexos, $form['actualizado'], $request->user());
        } else {
            $guardado = $this->contratos->crear($datos, $anexos ?? [], $request->user());
        }

        return response()->json([
```

por:

```php
            throw ValidationException::withMessages([...$validador->errors()->toArray(), ...$validadorAnexos->errors()->toArray()]);
        }
        $datos = $validador->validated();
        if (array_key_exists('secop_consultado', $datos)) {
            if ($datos['secop_consultado']) {
                $datos['secop_consultado_at'] = now()->format('Y-m-d H:i:s');
            }
            unset($datos['secop_consultado']);
        }
        // `anexos: null` es como no enviarlos: se conservan.
        $anexos = $request->input('anexos') !== null ? $validadorAnexos->validated()['anexos'] : null;
        unset($datos['id'], $datos['actualizado']);

        $contrato = null;
        if ($editar) {
            $contrato = Contrato::find($form['id']);
            if ($contrato === null) {
                return response()->json(['mensaje' => 'El contrato no existe.'], 404);
            }
        }

        try {
            $guardado = $editar
                ? $this->contratos->editar($contrato, $datos, $anexos, $form['actualizado'], $request->user())
                : $this->contratos->crear($datos, $anexos ?? [], $request->user());
        } catch (UniqueConstraintViolationException $e) {
            // Otro guardado se llevó el ID de SECOP II entre la validación y el insert.
            if (! self::esChoqueDelIdSecop($e)) {
                throw $e;
            }
            $id = strtoupper(trim((string) ($datos['secop_id_contrato'] ?? '')));
            throw ValidationException::withMessages(['secop_id_contrato' => $this->yaRegistrado(Contrato::where('secop_id_contrato', $id)->first())]);
        }

        return response()->json([
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (8 de 9):

```php
     * obligatorios siempre se envían, y también `actualizado` (el updated_at del contrato que
     * tenía el formulario; null para un contrato antiguo, sin fechas).
     */
    private function reglas(bool $editar): array
    {
        $cifras = fn (string $atributo, mixed $valor, Closure $falla) => strlen(ltrim(explode('.', ltrim((string) $valor, '+-'))[0], '0')) <= self::CIFRAS_ENTERAS
            || $falla('El valor inicial admite hasta ' . self::CIFRAS_ENTERAS . ' cifras enteras y 2 decimales.');
        $opcional = fn (mixed ...$reglas) => ['sometimes', 'nullable', 'bail', ...$reglas];
        $fecha = $opcional('string', 'date_format:Y-m-d');

        return [
            'id' => $opcional($this->entero()),
```

por:

```php
     * obligatorios siempre se envían, y también `actualizado` (el updated_at del contrato que
     * tenía el formulario; null para un contrato antiguo, sin fechas).
     */
    private function reglas(?int $editando): array
    {
        $cifras = fn (string $atributo, mixed $valor, Closure $falla) => strlen(ltrim(explode('.', ltrim((string) $valor, '+-'))[0], '0')) <= self::CIFRAS_ENTERAS
            || $falla('El valor inicial admite hasta ' . self::CIFRAS_ENTERAS . ' cifras enteras y 2 decimales.');
        $opcional = fn (mixed ...$reglas) => ['sometimes', 'nullable', 'bail', ...$reglas];
        $fecha = $opcional('string', 'date_format:Y-m-d');
        // Único entre contratos (menos el que se edita). Se guarda en mayúsculas.
        $secopUnico = function (string $atributo, mixed $valor, Closure $falla) use ($editando) {
            $otro = Contrato::query()
                ->where('secop_id_contrato', strtoupper(trim((string) $valor)))
                ->when($editando !== null, fn ($q) => $q->where('id', '<>', $editando))
                ->first();
            if ($otro !== null) {
                $falla($this->yaRegistrado($otro));
            }
        };

        return [
            'id' => $opcional($this->entero()),
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (9 de 9):

```php
            // Los calcula la pantalla y pueden traer decimales: el servicio los redondea.
            'avance_fisico' => $opcional('numeric', 'min:0', 'max:100'),
            'avance_financiero' => $opcional('numeric', 'min:0', 'max:100'),
            ...($editar ? ['actualizado' => ['present', 'nullable', 'bail', 'string', 'date']] : []),
        ];
    }

    private function reglasAnexos(): array
    {
        $ruta = fn (string $atributo, mixed $valor, Closure $falla) => (preg_match(self::RUTA_ANEXO, (string) $valor) === 1
```

por:

```php
            // Los calcula la pantalla y pueden traer decimales: el servicio los redondea.
            'avance_fisico' => $opcional('numeric', 'min:0', 'max:100'),
            'avance_financiero' => $opcional('numeric', 'min:0', 'max:100'),
            'secop_id_contrato' => $opcional('string', 'max:40', 'regex:' . self::FORMATO_SECOP, $secopUnico),
            'secop_proceso' => $opcional('string', 'max:40'),
            'url_secop' => $opcional('string', 'max:500', 'regex:' . self::URL_SECOP),
            'secop_consultado' => $opcional('boolean'),
            ...($editando !== null ? ['actualizado' => ['present', 'nullable', 'bail', 'string', 'date']] : []),
        ];
    }

    /**
     * Si la violación es la del índice único del ID de SECOP II (y no la de otro índice, aunque el
     * SQL del insert mencione la columna): se mira solo el mensaje del motor (`errorInfo`), que
     * nombra el índice en MariaDB/MySQL y la columna sola en SQLite.
     */
    private static function esChoqueDelIdSecop(UniqueConstraintViolationException $e): bool
    {
        $detalle = trim((string) ($e->errorInfo[2] ?? ''));

        return preg_match("/for key '(?:contratos\.)?contratos_secop_id_contrato_unique'$/", $detalle) === 1
            || preg_match('/^UNIQUE constraint failed: contratos\.secop_id_contrato$/', $detalle) === 1;
    }

    private function yaRegistrado(?Contrato $contrato): string
    {
        return 'Ese contrato de SECOP II ya está registrado: contrato N° ' . ($contrato?->n_contrato ?? '') . '.';
    }

    private function reglasAnexos(): array
    {
        $ruta = fn (string $atributo, mixed $valor, Closure $falla) => (preg_match(self::RUTA_ANEXO, (string) $valor) === 1
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (1 de 4):

```php
 *   estado. Desvincular se permite.
 * - El tipo de contrato debe existir y estar activo, o ser el que el contrato ya tiene.
 * - La fecha fin inicial no es anterior a la de inicio (con lo que quedaría guardado).
 * - El pedido escribe el valor inicial y la fecha fin inicial. El valor y la fecha fin vigentes
 *   los deriva recalcular(): inicial más adiciones y prórrogas. Al crear son los iniciales.
 * - Avisos que no impiden guardar: los contratos del proceso que no están anulados suman más que
 *   su monto (R9). Al crear el primer contrato de un proceso adjudicado se propone pasarlo a
```

por:

```php
 *   estado. Desvincular se permite.
 * - El tipo de contrato debe existir y estar activo, o ser el que el contrato ya tiene.
 * - La fecha fin inicial no es anterior a la de inicio (con lo que quedaría guardado).
 * - El pedido escribe el valor inicial y la fecha fin inicial, y la referencia de SECOP II (ID en
 *   mayúsculas, proceso, enlace y fecha de la última consulta). El valor y la fecha fin vigentes
 *   los deriva recalcular(): inicial más adiciones y prórrogas. Al crear son los iniciales.
 * - Avisos que no impiden guardar: los contratos del proceso que no están anulados suman más que
 *   su monto (R9). Al crear el primer contrato de un proceso adjudicado se propone pasarlo a
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (2 de 4):

```php
        'porcentaje_anticipo' => 'porcentaje_anticipo',
        'avance_fisico' => 'avance_fisico',
        'avance_financiero' => 'avance_financiero',
    ];

    private const TEXTOS = [
        'n_contrato', 'objeto', 'contratante', 'contratante_nit', 'contratista',
        'contratista_identificacion', 'supervisor', 'interventoria', 'numero_rp',
    ];

    private const ENTEROS = ['proyecto', 'proceso_id', 'tipo_contrato_id', 'porcentaje_anticipo'];
```

por:

```php
        'porcentaje_anticipo' => 'porcentaje_anticipo',
        'avance_fisico' => 'avance_fisico',
        'avance_financiero' => 'avance_financiero',
        'secop_id_contrato' => 'secop_id_contrato',
        'secop_proceso' => 'secop_proceso',
        'url_secop' => 'url_secop',
        'secop_consultado_at' => 'secop_consultado_at',
    ];

    private const TEXTOS = [
        'n_contrato', 'objeto', 'contratante', 'contratante_nit', 'contratista',
        'contratista_identificacion', 'supervisor', 'interventoria', 'numero_rp',
        'secop_id_contrato', 'secop_proceso', 'url_secop',
    ];

    private const ENTEROS = ['proyecto', 'proceso_id', 'tipo_contrato_id', 'porcentaje_anticipo'];
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (3 de 4):

```php
            }
            $texto = trim((string) $valor);
            $valores[$campo] = match (true) {
                in_array($campo, self::TEXTOS, true) => $texto !== '' ? $texto : null,
                in_array($campo, self::ENTEROS, true) => (int) $valor,
                in_array($campo, self::AVANCES, true) => (int) round((float) $valor),
```

por:

```php
            }
            $texto = trim((string) $valor);
            $valores[$campo] = match (true) {
                $campo === 'secop_id_contrato' => $texto !== '' ? strtoupper($texto) : null,
                in_array($campo, self::TEXTOS, true) => $texto !== '' ? $texto : null,
                in_array($campo, self::ENTEROS, true) => (int) $valor,
                in_array($campo, self::AVANCES, true) => (int) round((float) $valor),
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (4 de 4):

```php
            $valor = $contrato->{$columna};
            $valores[$campo] = match (true) {
                $valor === null => null,
                $valor instanceof DateTimeInterface => $valor->format('Y-m-d'),
                in_array($campo, self::ENTEROS, true), in_array($campo, self::AVANCES, true) => (int) $valor,
                $campo === 'valor_inicial' => (string) BigDecimal::of((string) $valor)->toScale(2),
```

por:

```php
            $valor = $contrato->{$columna};
            $valores[$campo] = match (true) {
                $valor === null => null,
                $campo === 'secop_consultado_at' => $valor->format('Y-m-d H:i:s'),
                $valor instanceof DateTimeInterface => $valor->format('Y-m-d'),
                in_array($campo, self::ENTEROS, true), in_array($campo, self::AVANCES, true) => (int) $valor,
                $campo === 'valor_inicial' => (string) BigDecimal::of((string) $valor)->toScale(2),
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/ContratosSecopApiTest.php tests/Feature/Contratacion/ContratosApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1119 passed.

- [ ] **Paso 5: Commit**

```bash
git add tests/Feature/Contratacion/ContratosSecopApiTest.php app/Http/Controllers/ContratoController.php app/Services/Contratacion/ContratosService.php tests/Feature/Contratacion/ContratosApiTest.php
git commit -m "feat: save contracts with their SECOP II reference"
```

---

### Tarea 4: SECOP II en el editor de contratos

Buscar e importar en un contrato nuevo, vincular uno existente, actualizar con la tabla de diferencias y desvincular.

**Files:**
- Create: `resources/js/components/contratos/SecopContrato.jsx`
- Create: `resources/js/components/useDialogoModal.js`
- Create: `resources/js/utils/secop.js`
- Test (create): `resources/js/utils/secop.test.js`
- Modify: `resources/css/Contratacion.css`
- Modify: `resources/js/components/ProcesosProyecto.jsx`
- Modify: `resources/js/components/contratos/ContratoEditor.jsx`
- Modify: `resources/js/utils/contratos.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `resources/js/utils/secop.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { contratoVacio, datosContrato, datosSecop, formularioContrato } from './contratos';
import {
    CAMPOS_DESDE_SECOP,
    aplicarDiferencias,
    avisosSecop,
    camposCambiados,
    camposMarcados,
    desvincularSecop,
    diferenciasSecop,
    filaResultadoSecop,
    formularioTieneDatos,
    importarSecop,
    mensajeSecop,
    referenciaDesdeSecop,
    referenciaSecop,
    valoresDesdeSecop,
    vincularSecop,
} from './secop';

// Un elemento de `resultados` de GET /secop/contratos, como lo arma el servidor.
const resultado = {
    contrato: {
        n_contrato: 'CO-045-2026',
        objeto: 'Construcción del puente',
        contratante: 'Alcaldía de Becerril',
        contratante_nit: '800123456',
        contratista: 'Constructora S.A.S.',
        contratista_identificacion: '900555111',
        valor_inicial: '40000000.00',
        fecha_suscripcion: '2026-01-05',
        fecha_inicio: '2026-01-10',
        fecha_fin_inicial: '2026-06-30',
        tipo_contrato_id: 2,
        estado: 'en_ejecucion',
        secop_id_contrato: 'CO1.PCCNTR.123456',
        secop_proceso: 'CO1.BDOS.987',
        url_secop: 'https://community.secop.gov.co/Public/Tendering/ContractNoticePhases/View?PPI=CO1.PPI.1',
    },
    secop: {
        tipo_contrato_secop: 'Obra', modalidad_secop: 'Licitación pública', estado_secop: 'En ejecución',
        dias_adicionados: 0, valor_pagado: '0.00', valor_facturado: null,
    },
    registrado: null,
};

const tipos = [{ id: 2, nombre: 'Obra', activo: true }, { id: 3, nombre: 'Suministro', activo: true }];

// El contrato abierto (presentar), con la referencia de SECOP II guardada.
const guardado = {
    id: 7,
    proyecto: 3,
    n_contrato: 'CO-045-2026',
    objeto: 'Construcción del puente',
    contratante: 'Alcaldía de Becerril',
    contratante_nit: '800123456',
    contratista: 'Constructora S.A.S.',
    contratista_identificacion: '900555111',
    tipo_contrato_id: 2,
    tipo_contrato: { id: 2, nombre: 'Obra', activo: true },
    valor_inicial: '40000000.00',
    valor: '40000000.00',
    fecha_suscripcion: '2026-01-05',
    fecha_inicio: '2026-01-10',
    fecha_fin_inicial: '2026-06-30',
    fecha_fin: '2026-06-30',
    estado: 'en_ejecucion',
    anticipo: false,
    secop_id_contrato: 'CO1.PCCNTR.123456',
    secop_proceso: 'CO1.BDOS.987',
    url_secop: resultado.contrato.url_secop,
    secop_consultado_at: '2026-11-01 10:00:00',
    updated_at: '2026-11-02T10:00:00.000000Z',
};

// Intl separa el símbolo con un espacio de no separación.
const plano = (texto) => texto.replace(/ /g, ' ');
const planos = (diferencias) => diferencias.map((d) => ({ ...d, actual: plano(d.actual), secop: plano(d.secop) }));

const conContrato = (cambios) => ({ ...resultado, contrato: { ...resultado.contrato, ...cambios } });

describe('valoresDesdeSecop', () => {
    it('pasa cada campo al formato del formulario', () => {
        expect(valoresDesdeSecop(resultado.contrato)).toEqual({
            tipo_contrato_id: '2',
            n_contrato: 'CO-045-2026',
            estado: 'en_ejecucion',
            objeto: 'Construcción del puente',
            contratante: 'Alcaldía de Becerril',
            contratante_nit: '800123456',
            contratista: 'Constructora S.A.S.',
            contratista_identificacion: '900555111',
            valor_inicial: '40.000.000',
            fecha_suscripcion: '2026-01-05',
            fecha_inicio: '2026-01-10',
            fecha_fin_inicial: '2026-06-30',
        });
    });

    it('da los mismos valores que formularioContrato con los mismos datos', () => {
        const formulario = formularioContrato({ ...resultado.contrato, valor_inicial: '1500000.50' });
        const valores = valoresDesdeSecop({ ...resultado.contrato, valor_inicial: '1500000.50' });
        Object.entries(valores).forEach(([campo, valor]) => expect(valor, campo).toBe(formulario[campo]));
    });

    it('omite los nulos, los vacíos y un estado que no es de la lista', () => {
        const valores = valoresDesdeSecop({ ...resultado.contrato, estado: null, objeto: '  ', tipo_contrato_id: null, fecha_inicio: null });
        expect(valores).not.toHaveProperty('estado');
        expect(valores).not.toHaveProperty('objeto');
        expect(valores).not.toHaveProperty('tipo_contrato_id');
        expect(valores).not.toHaveProperty('fecha_inicio');
        expect(valoresDesdeSecop({ estado: 'vigente' })).toEqual({});
        expect(valoresDesdeSecop(null)).toEqual({});
    });

    it('deja solo el día de una fecha con hora y quita espacios', () => {
        expect(valoresDesdeSecop({ fecha_inicio: '2026-01-10T00:00:00.000', n_contrato: ' CO-1 ' })).toEqual({ fecha_inicio: '2026-01-10', n_contrato: 'CO-1' });
    });
});

describe('referenciaDesdeSecop', () => {
    it('trae los tres campos y marca la consulta', () => {
        expect(referenciaDesdeSecop(resultado.contrato)).toEqual({
            secop_id_contrato: 'CO1.PCCNTR.123456',
            secop_proceso: 'CO1.BDOS.987',
            url_secop: resultado.contrato.url_secop,
            secop_consultado: true,
        });
    });

    it('deja vacíos los que no vienen y descarta un enlace que no es web', () => {
        expect(referenciaDesdeSecop({ secop_id_contrato: 'CO1.PCCNTR.1', secop_proceso: null, url_secop: 'javascript:alert(1)' })).toEqual({
            secop_id_contrato: 'CO1.PCCNTR.1', secop_proceso: '', url_secop: '', secop_consultado: true,
        });
    });
});

describe('importarSecop y vincularSecop', () => {
    it('un contrato nuevo toma todo lo de SECOP II, vacía lo que no trae y conserva lo demás', () => {
        const formulario = { ...contratoVacio(3), supervisor: 'Ana', estado: 'celebrado', objeto: 'Escrito a mano' };
        const importado = importarSecop(formulario, conContrato({ estado: null, objeto: null }));
        expect(importado.proyecto).toBe('3');
        expect(importado.supervisor).toBe('Ana');
        // SECOP II no tradujo el estado ni trae el objeto: quedan vacíos, para elegirlos.
        expect(importado.estado).toBe('');
        expect(importado.objeto).toBe('');
        expect(importado.n_contrato).toBe('CO-045-2026');
        expect(importado.valor_inicial).toBe('40.000.000');
        expect(importado.secop_id_contrato).toBe('CO1.PCCNTR.123456');
        expect(importado.secop_consultado).toBe(true);
    });

    it('otro resultado reemplaza la referencia del anterior', () => {
        const primero = importarSecop(contratoVacio(), resultado);
        const segundo = importarSecop(primero, conContrato({ secop_id_contrato: 'CO1.PCCNTR.9', secop_proceso: null, url_secop: null }));
        expect(segundo.secop_id_contrato).toBe('CO1.PCCNTR.9');
        expect(segundo.secop_proceso).toBe('');
        expect(segundo.url_secop).toBe('');
    });

    it('otro resultado no mezcla los datos de los dos contratos', () => {
        const primero = importarSecop({ ...contratoVacio(3), supervisor: 'Ana' }, resultado);
        const otro = {
            ...resultado,
            contrato: {
                n_contrato: 'CO-099-2026', objeto: 'Suministro de tubería', secop_id_contrato: 'CO1.PCCNTR.9',
                contratante: null, contratante_nit: null, contratista: null, contratista_identificacion: null, valor_inicial: null,
                fecha_suscripcion: null, fecha_inicio: null, fecha_fin_inicial: null, tipo_contrato_id: null, estado: 'vigente',
            },
        };
        const segundo = importarSecop(primero, otro);
        expect(segundo.n_contrato).toBe('CO-099-2026');
        expect(segundo.objeto).toBe('Suministro de tubería');
        CAMPOS_DESDE_SECOP.filter(([campo]) => !['n_contrato', 'objeto'].includes(campo))
            .forEach(([campo]) => expect(segundo[campo], campo).toBe(''));
        expect(segundo.supervisor).toBe('Ana');
        expect(segundo.proyecto).toBe('3');
    });

    it('un contrato guardado que no venía de SECOP II conserva lo que SECOP II no trae', () => {
        const formulario = formularioContrato({ ...guardado, secop_id_contrato: null, estado: 'liquidado' });
        const importado = importarSecop(formulario, conContrato({ estado: null, objeto: null }));
        expect(importado.estado).toBe('liquidado');
        expect(importado.objeto).toBe('Construcción del puente');
        // Si ya venía de SECOP II, el nuevo resultado lo reemplaza entero.
        const deSecop = importarSecop({ ...formulario, secop_consultado: true }, conContrato({ estado: null, objeto: null }));
        expect(deSecop.estado).toBe('');
        expect(deSecop.objeto).toBe('');
    });

    it('vincular solo copia la referencia', () => {
        const formulario = formularioContrato({ ...guardado, secop_id_contrato: null, objeto: 'Otro objeto' });
        const vinculado = vincularSecop(formulario, resultado);
        expect(vinculado.objeto).toBe('Otro objeto');
        expect(vinculado.secop_id_contrato).toBe('CO1.PCCNTR.123456');
        expect(vinculado.secop_consultado).toBe(true);
    });
});

describe('formularioTieneDatos', () => {
    it('solo cuentan los campos que trae SECOP II', () => {
        expect(formularioTieneDatos(contratoVacio(3))).toBe(false);
        expect(formularioTieneDatos({ ...contratoVacio(), supervisor: 'Ana', anticipo: true })).toBe(false);
        expect(formularioTieneDatos({ ...contratoVacio(), objeto: 'Obra' })).toBe(true);
        expect(formularioTieneDatos({ ...contratoVacio(), valor_inicial: '1.000' })).toBe(true);
    });

    it('la lista de campos son claves del formulario', () => {
        const vacio = contratoVacio();
        CAMPOS_DESDE_SECOP.forEach(([campo]) => expect(vacio, campo).toHaveProperty(campo));
    });
});

describe('referenciaSecop', () => {
    it('sin consulta, la guardada del contrato', () => {
        expect(referenciaSecop(formularioContrato(guardado), guardado)).toEqual({
            id: 'CO1.PCCNTR.123456',
            proceso: 'CO1.BDOS.987',
            enlace: guardado.url_secop,
            consultado: '2026-11-01 10:00:00',
            pendiente: false,
            desvinculado: false,
        });
    });

    it('tras usar SECOP II, la del formulario, pendiente de guardar', () => {
        const formulario = vincularSecop(formularioContrato({ ...guardado, secop_id_contrato: null }), resultado);
        expect(referenciaSecop(formulario, { ...guardado, secop_id_contrato: null })).toMatchObject({ id: 'CO1.PCCNTR.123456', pendiente: true });
    });

    it('un enlace guardado que no es web no se ofrece', () => {
        expect(referenciaSecop(contratoVacio(), { url_secop: 'ftp://x' }).enlace).toBeNull();
        expect(referenciaSecop(contratoVacio(), null)).toEqual({ id: '', proceso: '', enlace: null, consultado: null, pendiente: false, desvinculado: false });
    });
});

describe('desvincularSecop', () => {
    it('deja la referencia vacía en el formulario, marcada para enviarse, sin consulta', () => {
        const formulario = formularioContrato(guardado);
        const desvinculado = desvincularSecop(formulario);
        expect(desvinculado).toEqual({
            ...formulario, secop_id_contrato: '', secop_proceso: '', url_secop: '', secop_consultado: false, secop_desvincular: true,
        });
    });

    it('al guardar, los tres campos van como null y sin secop_consultado', () => {
        const datos = datosContrato(desvincularSecop(formularioContrato(guardado)), guardado);
        expect(datos).toMatchObject({ secop_id_contrato: null, secop_proceso: null, url_secop: null });
        expect(datos).not.toHaveProperty('secop_consultado');
        expect(datos.objeto).toBe('Construcción del puente');
    });

    it('lo que tomó de SECOP II antes de desvincular también se descarta', () => {
        const aplicado = aplicarDiferencias({ ...formularioContrato(guardado), objeto: 'X' }, resultado, ['objeto'], guardado);
        expect(datosSecop(desvincularSecop(aplicado))).toEqual({ secop_id_contrato: null, secop_proceso: null, url_secop: null });
    });

    it('la referencia que muestra el editor queda vacía y pendiente de guardar', () => {
        expect(referenciaSecop(desvincularSecop(formularioContrato(guardado)), guardado)).toEqual({
            id: '', proceso: '', enlace: null, consultado: '2026-11-01 10:00:00', pendiente: true, desvinculado: true,
        });
    });

    it('volver a tomar un resultado de SECOP II envía la nueva referencia', () => {
        const otra = vincularSecop(desvincularSecop(formularioContrato(guardado)), conContrato({ secop_id_contrato: 'CO1.PCCNTR.9' }));
        expect(datosSecop(otra)).toMatchObject({ secop_id_contrato: 'CO1.PCCNTR.9', secop_consultado: true });
        expect(referenciaSecop(otra, guardado)).toMatchObject({ id: 'CO1.PCCNTR.9', desvinculado: false });
    });
});

describe('avisosSecop', () => {
    it('sin estado ni días adicionados, ninguno', () => {
        expect(avisosSecop(resultado)).toEqual([]);
        expect(avisosSecop({ ...resultado, secop: { ...resultado.secop, dias_adicionados: null } })).toEqual([]);
    });

    it('pide elegir el estado con el texto de SECOP II', () => {
        expect(avisosSecop({ ...conContrato({ estado: null }), secop: { ...resultado.secop, estado_secop: 'En aprobación' } }))
            .toEqual(['Elige el estado: SECOP II dice «En aprobación».']);
        expect(avisosSecop({ ...conContrato({ estado: null }), secop: { ...resultado.secop, estado_secop: null } }))
            .toEqual(['Elige el estado: SECOP II no lo informa.']);
    });

    it('con el formulario resultante, pide el estado solo si quedó sin elegir', () => {
        const sinEstado = { ...conContrato({ estado: null }), secop: { ...resultado.secop, estado_secop: 'En aprobación' } };
        expect(avisosSecop(sinEstado, { ...contratoVacio(), estado: 'celebrado' })).toEqual([]);
        expect(avisosSecop(sinEstado, importarSecop(contratoVacio(), sinEstado))).toEqual(['Elige el estado: SECOP II dice «En aprobación».']);
        // El estado de SECOP II no se copió (no se marcó) y el formulario no tiene: también se pide.
        expect(avisosSecop(resultado, contratoVacio())).toEqual(['Elige el estado: SECOP II dice «En ejecución».']);
    });

    it('recuerda registrar los días adicionados como prórroga', () => {
        expect(avisosSecop({ ...resultado, secop: { ...resultado.secop, dias_adicionados: 30 } }))
            .toEqual(['SECOP II reporta 30 días adicionados: regístralos como prórroga en Modificaciones.']);
        expect(avisosSecop({ ...resultado, secop: { ...resultado.secop, dias_adicionados: 1 } }))
            .toEqual(['SECOP II reporta 1 día adicionado: regístralos como prórroga en Modificaciones.']);
    });
});

describe('filaResultadoSecop', () => {
    it('lo que se lista de un resultado', () => {
        const fila = filaResultadoSecop(resultado);
        expect({ ...fila, valor: plano(fila.valor) }).toEqual({
            clave: 'CO1.PCCNTR.123456',
            idSecop: 'CO1.PCCNTR.123456',
            numero: 'CO-045-2026',
            entidad: 'Alcaldía de Becerril',
            contratista: 'Constructora S.A.S.',
            valor: '$ 40.000.000',
            suscripcion: '05/01/2026',
            inicio: '10/01/2026',
            fin: '30/06/2026',
            estado: 'En ejecución',
            registrado: null,
        });
    });

    it('uno ya registrado enlaza a su contrato', () => {
        const fila = filaResultadoSecop({ ...resultado, registrado: { id: 12, n_contrato: 'CO-045' } });
        expect(fila.registrado).toEqual({ ruta: '/contratos/12', numero: 'CO-045' });
    });

    it('los datos que faltan', () => {
        const fila = filaResultadoSecop({ contrato: {}, secop: null, registrado: null });
        expect(fila).toMatchObject({ numero: 'Sin número', entidad: '—', contratista: '—', valor: '—', inicio: '—', estado: '—' });
    });
});

describe('diferenciasSecop', () => {
    it('sin diferencias, la lista vacía (el valor se compara como número)', () => {
        expect(diferenciasSecop(formularioContrato(guardado), resultado, { tipos, contrato: guardado })).toEqual([]);
        const conCentavos = formularioContrato({ ...guardado, valor_inicial: '40000000' });
        expect(diferenciasSecop(conCentavos, resultado, { tipos, contrato: guardado })).toEqual([]);
    });

    it('solo los campos que difieren, legibles, en el orden del formulario', () => {
        const formulario = { ...formularioContrato(guardado), objeto: 'Puente viejo', valor_inicial: '35.000.000', tipo_contrato_id: '3', estado: 'suspendido' };
        expect(planos(diferenciasSecop(formulario, resultado, { tipos, contrato: guardado }))).toEqual([
            { campo: 'tipo_contrato_id', etiqueta: 'Tipo de contrato', actual: 'Suministro', secop: 'Obra', marcado: true, nota: null },
            {
                campo: 'estado', etiqueta: 'Estado', actual: 'Suspendido', secop: 'En ejecución', marcado: false,
                nota: 'Revisa antes de copiar: el estado de SECOP II puede ir detrás del de GestPro.',
            },
            { campo: 'objeto', etiqueta: 'Objeto', actual: 'Puente viejo', secop: 'Construcción del puente', marcado: true, nota: null },
            {
                campo: 'valor_inicial', etiqueta: 'Valor inicial', actual: '$ 35.000.000', secop: '$ 40.000.000', marcado: false,
                nota: 'Es la base del valor vigente: al guardar, el vigente se recalcula con las adiciones de Modificaciones.',
            },
        ]);
    });

    it('la fecha fin inicial no va marcada y lleva su nota; las fechas se leen día/mes/año', () => {
        const formulario = { ...formularioContrato(guardado), fecha_fin_inicial: '' };
        const [fin] = diferenciasSecop(formulario, resultado, { tipos, contrato: guardado });
        expect(fin).toMatchObject({ campo: 'fecha_fin_inicial', actual: '—', secop: '30/06/2026', marcado: false });
        expect(fin.nota).toMatch(/prórrogas/);
    });

    it('lo que SECOP II no trae no se compara', () => {
        const formulario = { ...formularioContrato(guardado), objeto: 'Otro', estado: 'liquidado' };
        expect(diferenciasSecop(formulario, conContrato({ objeto: null, estado: null }), { tipos, contrato: guardado })).toEqual([]);
    });

    it('compara el proceso y el enlace con los guardados', () => {
        const otro = conContrato({ secop_proceso: 'CO1.BDOS.111', url_secop: 'https://community.secop.gov.co/otro' });
        const diferencias = diferenciasSecop(formularioContrato(guardado), otro, { tipos, contrato: guardado });
        expect(diferencias.map((d) => d.campo)).toEqual(['secop_proceso', 'url_secop']);
        expect(diferencias[0]).toMatchObject({ etiqueta: 'Proceso en SECOP II', actual: 'CO1.BDOS.987', secop: 'CO1.BDOS.111', marcado: true });
    });

    it('un tipo que no está en el catálogo se nombra con el del contrato o con su id', () => {
        const formulario = { ...formularioContrato(guardado), tipo_contrato_id: '9' };
        expect(diferenciasSecop(formulario, resultado, { tipos: null, contrato: guardado })[0]).toMatchObject({ actual: 'Tipo 9', secop: 'Obra' });
    });

    it('camposMarcados da los marcados por defecto', () => {
        const formulario = { ...formularioContrato(guardado), objeto: 'X', valor_inicial: '1', fecha_fin_inicial: '2026-05-01', estado: 'terminado' };
        expect(camposMarcados(diferenciasSecop(formulario, resultado, { tipos, contrato: guardado }))).toEqual(['objeto']);
        expect(camposMarcados(null)).toEqual([]);
    });
});

describe('aplicarDiferencias', () => {
    const formulario = { ...formularioContrato(guardado), objeto: 'X', valor_inicial: '1', estado: 'suspendido' };
    const otro = conContrato({ secop_proceso: 'CO1.BDOS.111', url_secop: 'https://community.secop.gov.co/otro' });

    it('copia solo los elegidos y conserva la referencia que no se eligió', () => {
        const aplicado = aplicarDiferencias(formulario, otro, ['objeto', 'url_secop'], guardado);
        expect(aplicado.objeto).toBe('Construcción del puente');
        expect(aplicado.valor_inicial).toBe('1');
        expect(aplicado.estado).toBe('suspendido');
        expect(aplicado.secop_id_contrato).toBe('CO1.PCCNTR.123456');
        expect(aplicado.secop_proceso).toBe('CO1.BDOS.987');
        expect(aplicado.url_secop).toBe('https://community.secop.gov.co/otro');
        expect(aplicado.secop_consultado).toBe(true);
    });

    it('sin elegidos, solo marca la consulta', () => {
        const aplicado = aplicarDiferencias(formulario, otro, [], guardado);
        expect(aplicado.objeto).toBe('X');
        expect(datosSecop(aplicado)).toEqual({
            secop_id_contrato: 'CO1.PCCNTR.123456', secop_proceso: 'CO1.BDOS.987', url_secop: guardado.url_secop, secop_consultado: true,
        });
    });

    it('sin diferencias, el formulario queda igual y marcado como consultado (la consulta se registra al guardar)', () => {
        const formulario = formularioContrato(guardado);
        expect(diferenciasSecop(formulario, resultado, { tipos, contrato: guardado })).toEqual([]);
        const consultado = aplicarDiferencias(formulario, resultado, camposMarcados([]), guardado);
        expect(consultado).toEqual({
            ...formulario,
            secop_id_contrato: guardado.secop_id_contrato, secop_proceso: guardado.secop_proceso, url_secop: guardado.url_secop, secop_consultado: true,
        });
        expect(datosContrato(consultado, guardado)).toMatchObject({ secop_id_contrato: 'CO1.PCCNTR.123456', secop_consultado: true });
    });

    it('al vincular un contrato sin ID, aplica sobre la referencia recién tomada', () => {
        const sinId = { ...guardado, secop_id_contrato: null, secop_proceso: null, url_secop: null };
        const vinculado = vincularSecop({ ...formularioContrato(sinId), objeto: 'X' }, otro);
        const aplicado = aplicarDiferencias(vinculado, otro, ['objeto'], sinId);
        expect(aplicado.secop_proceso).toBe('CO1.BDOS.111');
        expect(aplicado.objeto).toBe('Construcción del puente');
    });

    it('camposCambiados da los campos que SECOP II cambió', () => {
        expect(camposCambiados(formulario, aplicarDiferencias(formulario, otro, ['objeto', 'estado'], guardado))).toEqual(['estado', 'objeto']);
    });
});

describe('datosSecop y datosContrato', () => {
    it('sin consulta no se envía la referencia: la guardada no cambia', () => {
        expect(datosSecop(formularioContrato(guardado))).toEqual({});
        const datos = datosContrato(formularioContrato(guardado), guardado);
        ['secop_id_contrato', 'secop_proceso', 'url_secop', 'secop_consultado'].forEach((campo) => expect(datos).not.toHaveProperty(campo));
    });

    it('tras usar SECOP II, van los tres campos (vacío como null) y la consulta', () => {
        const importado = importarSecop(contratoVacio(), conContrato({ secop_proceso: null }));
        expect(datosContrato(importado)).toMatchObject({
            n_contrato: 'CO-045-2026',
            valor_inicial: '40000000',
            tipo_contrato_id: 2,
            secop_id_contrato: 'CO1.PCCNTR.123456',
            secop_proceso: null,
            url_secop: resultado.contrato.url_secop,
            secop_consultado: true,
        });
    });
});

describe('mensajeSecop', () => {
    const error = (status, data) => ({ response: { status, data } });

    it('usa el mensaje del servidor de un 502 o un 404', () => {
        expect(mensajeSecop(error(502, { mensaje: 'No se pudo consultar SECOP II. Intenta de nuevo en unos minutos.' })))
            .toBe('No se pudo consultar SECOP II. Intenta de nuevo en unos minutos.');
        expect(mensajeSecop(error(404, { mensaje: 'SECOP II no tiene ese contrato.' }))).toBe('SECOP II no tiene ese contrato.');
    });

    it('de un 422, el primer error', () => {
        expect(mensajeSecop(error(422, { message: 'x', errors: { q: ['Escribe el número, el ID o el enlace del contrato.'] } })))
            .toBe('Escribe el número, el ID o el enlace del contrato.');
    });

    it('sin mensaje, el genérico de la API', () => {
        expect(mensajeSecop(error(403, {}))).toBe('No tienes permiso para esta acción.');
        expect(mensajeSecop({ request: {} })).toBe('No hay conexión con el servidor.');
    });
});
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/secop.test.js`
Expected: FALLA la carga de `secop.test.js`: no existe `resources/js/utils/secop.js`.

- [ ] **Paso 3: Implementar**

Crear `resources/js/components/contratos/SecopContrato.jsx`:

```jsx
import React, { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Link } from 'react-router-dom';
import { faArrowUpRightFromSquare, faMagnifyingGlass, faXmark } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../../axios';
import { camposMarcados, filaResultadoSecop, mensajeSecop } from '../../utils/secop';
import useDialogoModal from '../useDialogoModal';
import '../../../css/Plantillas.css';
import '../../../css/Contratacion.css';

/**
 * Diálogo modal de SECOP II, en `document.body`: así queda encima del editor aunque este viva en
 * otro diálogo. Escape, la "×" y un clic en el fondo llaman a `onCerrar`. Escape y Tab no llegan al
 * diálogo de afuera (que cerraría el editor o movería el foco fuera de este).
 */
export const DialogoSecop = ({ titulo, onCerrar, children }) => {
    const caja = useRef(null);
    const idTitulo = useId();
    const alPulsar = useDialogoModal(caja, onCerrar);
    const pulsar = (e) => {
        alPulsar(e);
        if (e.key === 'Escape' || e.key === 'Tab') e.stopPropagation();
    };

    return createPortal(
        <div className="ct-dialogo-fondo" onMouseDown={(e) => { if (e.target === e.currentTarget) onCerrar(); }}>
            <div ref={caja} className="ct-dialogo" role="dialog" aria-modal="true" aria-labelledby={idTitulo} tabIndex={-1} onKeyDown={pulsar}>
                <header className="ct-dialogo-cabecera">
                    <div>
                        <span className="gp-etiqueta">SECOP II</span>
                        <h2 id={idTitulo}>{titulo}</h2>
                    </div>
                    <button type="button" className="gp-icono" onClick={onCerrar} aria-label="Cerrar">
                        <FontAwesomeIcon icon={faXmark} />
                    </button>
                </header>
                <div className="ct-dialogo-cuerpo">{children}</div>
            </div>
        </div>,
        document.body,
    );
};

/**
 * Resultados de SECOP II (elementos de `resultados`): número, entidad, contratista, valor, fechas
 * y estado en SECOP II. Uno ya registrado enlaza a su contrato (con `enlaceEnOtraPestana`, en otra
 * pestaña, para no dejar el formulario que se está llenando); los demás llevan el botón
 * `textoAccion`, que llama a `onElegir(resultado)`.
 */
export const ResultadosSecop = ({ resultados, textoAccion = 'Usar este', onElegir, enlaceEnOtraPestana = false }) => (
    <ul className="ct-secop-resultados">
        {resultados.map((resultado) => {
            const fila = filaResultadoSecop(resultado);
            return (
                <li key={fila.clave} className="ct-secop-resultado">
                    <div className="ct-secop-resultado-cabecera">
                        <div>
                            <strong>{fila.numero}</strong> <span className="ct-clave">{fila.idSecop}</span>
                        </div>
                        <span className="ct-estado ct-estado-neutro" title="Estado en SECOP II">{fila.estado}</span>
                    </div>
                    <dl className="ct-secop-datos">
                        <div><dt>Entidad</dt><dd>{fila.entidad}</dd></div>
                        <div><dt>Contratista</dt><dd>{fila.contratista}</dd></div>
                        <div><dt>Valor</dt><dd className="ct-valor">{fila.valor}</dd></div>
                        <div><dt>Suscripción</dt><dd>{fila.suscripcion}</dd></div>
                        <div><dt>Inicio</dt><dd>{fila.inicio}</dd></div>
                        <div><dt>Fin</dt><dd>{fila.fin}</dd></div>
                    </dl>
                    <div className="ct-secop-resultado-accion">
                        {fila.registrado ? (
                            <span>
                                Ya registrado:{' '}
                                {enlaceEnOtraPestana ? (
                                    <Link to={fila.registrado.ruta} target="_blank" rel="noopener noreferrer" className="ct-enlace">
                                        contrato {fila.registrado.numero} <FontAwesomeIcon icon={faArrowUpRightFromSquare} aria-hidden="true" />
                                        <span className="ct-oculto"> (se abre en otra pestaña)</span>
                                    </Link>
                                ) : (
                                    <Link to={fila.registrado.ruta} className="ct-enlace">contrato {fila.registrado.numero}</Link>
                                )}
                            </span>
                        ) : (
                            <button type="button" className="gp-boton gp-boton-primario" onClick={() => onElegir(resultado)} aria-label={`${textoAccion}: contrato ${fila.numero}`}>
                                {textoAccion}
                            </button>
                        )}
                    </div>
                </li>
            );
        })}
    </ul>
);

const SIN_TEXTO = 'Escribe el número, el ID o el enlace del contrato.';

/**
 * "Buscar en SECOP II": el número, el ID (CO1.PCCNTR…) o el enlace del contrato y, opcional, el
 * NIT de la entidad (GET /secop/contratos). Solo la última búsqueda llega a la lista; al cerrar,
 * una respuesta atrasada se descarta. "Usar este" llama a `onElegir(resultado)`.
 */
export const BuscarSecop = ({ onElegir, onCerrar }) => {
    const [texto, setTexto] = useState('');
    const [nit, setNit] = useState('');
    const [resultados, setResultados] = useState(null);
    const [buscando, setBuscando] = useState(false);
    const [error, setError] = useState(null);
    const turno = useRef(0);
    const idAyuda = useId();
    const idError = useId();

    // Al cerrar, cualquier respuesta pendiente queda atrasada.
    useEffect(() => () => { turno.current += 1; }, []);

    const buscar = async (e) => {
        e.preventDefault();
        const q = texto.trim();
        if (!q) {
            setError(SIN_TEXTO);
            return;
        }
        const numero = ++turno.current;
        setBuscando(true);
        setError(null);
        try {
            const { data } = await axios.get('/secop/contratos', { params: { q, ...(nit.trim() ? { nit: nit.trim() } : {}) } });
            if (numero !== turno.current) return;
            setResultados(Array.isArray(data?.resultados) ? data.resultados : []);
        } catch (falla) {
            if (numero !== turno.current) return;
            setResultados(null);
            setError(mensajeSecop(falla));
        } finally {
            if (numero === turno.current) setBuscando(false);
        }
    };

    return (
        <DialogoSecop titulo="Buscar en SECOP II" onCerrar={onCerrar}>
            <form className="pl-formulario ct-secop-busqueda" onSubmit={buscar} noValidate aria-label="Buscar el contrato en SECOP II">
                <label className="pl-campo pl-campo-ancho">
                    <span>Número, ID o enlace del contrato</span>
                    <input
                        type="text"
                        value={texto}
                        onChange={(e) => { setTexto(e.target.value); setError(null); }}
                        maxLength={500}
                        placeholder="CO1.PCCNTR.1234567 o el número del contrato"
                        aria-describedby={error ? `${idAyuda} ${idError}` : idAyuda}
                        aria-invalid={error === SIN_TEXTO}
                    />
                    <small id={idAyuda} className="contrato-nota">Pega el ID o el enlace del contrato en SECOP II, o escribe su número.</small>
                </label>
                <label className="pl-campo">
                    <span>NIT de la entidad (opcional)</span>
                    <input type="text" value={nit} onChange={(e) => setNit(e.target.value)} maxLength={30} inputMode="numeric" />
                </label>
                <div className="pl-formulario-acciones">
                    <button type="submit" className="gp-boton gp-boton-primario" disabled={buscando}>
                        <FontAwesomeIcon icon={faMagnifyingGlass} /> {buscando ? 'Buscando…' : 'Buscar'}
                    </button>
                </div>
            </form>
            {error && <p id={idError} className="pl-error" role="alert">{error}</p>}
            <div aria-live="polite">
                {resultados?.length === 0 && <p className="gp-vacio">SECOP II no tiene contratos con esos datos. Si no está en SECOP II, llena el formulario a mano.</p>}
                {resultados?.length > 0 && <ResultadosSecop resultados={resultados} onElegir={onElegir} enlaceEnOtraPestana />}
            </div>
        </DialogoSecop>
    );
};

/**
 * Las diferencias entre el formulario y SECOP II (diferenciasSecop), con una casilla por campo
 * (marcadas las de `marcado`) y los `avisos` del resultado. "Copiar al formulario" llama a
 * `onCopiar(campos elegidos)`.
 */
export const DiferenciasSecop = ({ titulo, diferencias, avisos = [], onCopiar, onCerrar }) => {
    const [elegidos, setElegidos] = useState(() => camposMarcados(diferencias));
    const alternar = (campo) => setElegidos((prev) => (prev.includes(campo) ? prev.filter((c) => c !== campo) : [...prev, campo]));

    return (
        <DialogoSecop titulo={titulo} onCerrar={onCerrar}>
            <p className="contrato-nota">Marca lo que quieres copiar al formulario. Nada se guarda hasta que guardes el contrato.</p>
            {avisos.length > 0 && (
                <ul className="ct-secop-avisos">
                    {avisos.map((aviso) => <li key={aviso}>{aviso}</li>)}
                </ul>
            )}
            <div className="ct-secop-tabla">
                <table>
                    <thead>
                        <tr>
                            <th scope="col"><span className="ct-oculto">Copiar</span></th>
                            <th scope="col">Campo</th>
                            <th scope="col">En GestPro</th>
                            <th scope="col">En SECOP II</th>
                        </tr>
                    </thead>
                    <tbody>
                        {diferencias.map((d) => (
                            <tr key={d.campo}>
                                <td>
                                    <input
                                        type="checkbox"
                                        checked={elegidos.includes(d.campo)}
                                        onChange={() => alternar(d.campo)}
                                        aria-label={`Copiar ${d.etiqueta}`}
                                        aria-describedby={d.nota ? `secop-nota-${d.campo}` : undefined}
                                    />
                                </td>
                                <th scope="row">
                                    {d.etiqueta}
                                    {d.nota && <small id={`secop-nota-${d.campo}`} className="ct-secop-nota">{d.nota}</small>}
                                </th>
                                <td>{d.actual}</td>
                                <td>{d.secop}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="pl-formulario-acciones ct-secop-acciones">
                <button type="button" className="gp-boton" onClick={onCerrar}>Cancelar</button>
                <button type="button" className="gp-boton gp-boton-primario" onClick={() => onCopiar(elegidos)}>Copiar al formulario</button>
            </div>
        </DialogoSecop>
    );
};
```

Crear `resources/js/components/useDialogoModal.js`:

```js
import { useEffect } from 'react';
import { indiceFocoAtrapado } from '../utils/contratacion';

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
            return;
        }
        if (e.key !== 'Tab') return;
        const focos = [...caja.current.querySelectorAll(ENFOCABLES)].filter((el) => el.getClientRects().length > 0);
        const destino = indiceFocoAtrapado(focos.indexOf(document.activeElement), focos.length, e.shiftKey);
        if (destino === null) return;
        e.preventDefault();
        (focos[destino] ?? caja.current).focus();
    };
};

export default useDialogoModal;
```

Crear `resources/js/utils/secop.js`:

```js
// SECOP II en el formulario de contratos: los resultados de GET /secop/contratos (y de
// /secop/contratos/{id}) como valores del formulario, los avisos, las diferencias con lo que ya
// tiene el formulario y lo que se copia de ellas.
// Funciones puras, probadas en secop.test.js. El mapeo de SECOP II a los campos del contrato lo
// hace el servidor (Resultado.contrato trae las claves de /guardarContrato); aquí solo se pasa al
// formato del formulario, con las mismas conversiones que formularioContrato.

import { esEnlaceWeb, textoFecha, textoValor, valorDesdeApi, valorParaApi } from './contratacion';
import { ESTADOS_CONTRATO, etiquetaEstadoContrato } from './contratos';
import { rutaContrato } from './contratosModulo';
import { mensajeApi } from './plantillasChecklist';

/** Los campos del contrato que trae SECOP II, en el orden del formulario, con su etiqueta. */
export const CAMPOS_DESDE_SECOP = [
    ['tipo_contrato_id', 'Tipo de contrato'],
    ['n_contrato', 'N° Contrato'],
    ['estado', 'Estado'],
    ['objeto', 'Objeto'],
    ['contratante', 'Contratante'],
    ['contratante_nit', 'NIT de la entidad contratante'],
    ['contratista', 'Contratista'],
    ['contratista_identificacion', 'Identificación del contratista'],
    ['valor_inicial', 'Valor inicial'],
    ['fecha_suscripcion', 'Fecha de suscripción'],
    ['fecha_inicio', 'Fecha de inicio'],
    ['fecha_fin_inicial', 'Fecha fin inicial'],
];

// La referencia de SECOP II que se compara al actualizar (el ID es el mismo: es el que se consultó).
const REFERENCIA_COMPARADA = [
    ['secop_proceso', 'Proceso en SECOP II'],
    ['url_secop', 'Enlace en SECOP II'],
];

const CAMPOS_FECHA = ['fecha_suscripcion', 'fecha_inicio', 'fecha_fin_inicial'];

// Al actualizar no se marcan por defecto: el valor inicial y la fecha fin inicial cambian la base
// del recálculo de los vigentes, y el estado de SECOP II suele ir detrás del que se lleva aquí.
const NOTAS_SIN_MARCAR = {
    estado: 'Revisa antes de copiar: el estado de SECOP II puede ir detrás del de GestPro.',
    valor_inicial: 'Es la base del valor vigente: al guardar, el vigente se recalcula con las adiciones de Modificaciones.',
    fecha_fin_inicial: 'Es la base de la fecha fin vigente: al guardar, la vigente se recalcula con las prórrogas de Modificaciones.',
};

const vacio = (valor) => valor === null || valor === undefined || String(valor).trim() === '';
const texto = (valor) => (vacio(valor) ? '' : String(valor).trim());

/**
 * Los campos del contrato de un resultado de SECOP II en el formato del formulario: el valor con
 * los miles agrupados, las fechas como día y los ids como texto. Solo los que SECOP II trae: un
 * campo nulo o un estado que no es de la lista no aparece (así no borra lo que tiene el formulario).
 */
export const valoresDesdeSecop = (contratoSecop) => {
    const valores = {};
    CAMPOS_DESDE_SECOP.forEach(([campo]) => {
        const valor = contratoSecop?.[campo];
        if (vacio(valor)) return;
        if (campo === 'valor_inicial') valores[campo] = valorDesdeApi(valor);
        else if (CAMPOS_FECHA.includes(campo)) valores[campo] = String(valor).slice(0, 10);
        else if (campo === 'estado') {
            if (ESTADOS_CONTRATO.some(([v]) => v === valor)) valores[campo] = valor;
        } else valores[campo] = texto(valor);
    });
    return valores;
};

/**
 * La referencia de SECOP II de un resultado, para el formulario: el ID, el proceso y el enlace
 * (solo si es un enlace web), siempre los tres (vacío si no vienen, para no dejar los de otro
 * resultado), y `secop_consultado`, que hace que se envíen al guardar (datosSecop).
 */
export const referenciaDesdeSecop = (contratoSecop) => ({
    secop_id_contrato: texto(contratoSecop?.secop_id_contrato),
    secop_proceso: texto(contratoSecop?.secop_proceso),
    url_secop: esEnlaceWeb(contratoSecop?.url_secop) ? texto(contratoSecop.url_secop) : '',
    secop_consultado: true,
});

/**
 * "Usar este" en un contrato nuevo: el formulario con todo lo que trae SECOP II. En un contrato
 * nuevo, o en un formulario que ya venía de SECOP II (`secop_consultado`), los campos de SECOP II
 * que el resultado no trae quedan vacíos: así no se mezclan los datos de dos contratos. Los demás
 * campos (proyecto, proceso, supervisor, RP, anticipo…) se conservan.
 */
export const importarSecop = (formulario, resultado) => {
    const reemplazaTodo = !formulario?.id || Boolean(formulario?.secop_consultado);
    const vacios = reemplazaTodo ? Object.fromEntries(CAMPOS_DESDE_SECOP.map(([campo]) => [campo, ''])) : {};
    return {
        ...formulario,
        ...vacios,
        ...valoresDesdeSecop(resultado?.contrato),
        ...referenciaDesdeSecop(resultado?.contrato),
    };
};

/** La confirmación de "Usar este" cuando el formulario ya tiene datos: lo que de verdad hace importarSecop. */
export const TEXTO_REEMPLAZAR_CON_SECOP = 'El tipo, el número, el estado, el objeto, las partes, el valor y las fechas se reemplazan '
    + 'por los de SECOP II, y los que SECOP II no informa quedan vacíos. El proyecto, el proceso, el supervisor, '
    + 'la interventoría, el RP y el anticipo se conservan.';

/** "Usar este" en un contrato existente sin ID de SECOP II: solo la referencia; lo demás, con las diferencias. */
export const vincularSecop = (formulario, resultado) => ({
    ...formulario,
    ...referenciaDesdeSecop(resultado?.contrato),
});

/**
 * "Desvincular de SECOP II" en un contrato guardado con un ID equivocado: la referencia queda vacía
 * y `secop_desvincular` hace que se envíe (como null, sin `secop_consultado`: datosSecop) para
 * que el servidor la borre. Lo demás del formulario no cambia. Tras guardar se puede volver a buscar.
 */
export const desvincularSecop = (formulario) => ({
    ...formulario,
    secop_id_contrato: '',
    secop_proceso: '',
    url_secop: '',
    secop_consultado: false,
    secop_desvincular: true,
});

/** Si el formulario ya tiene escrito alguno de los campos que trae SECOP II: entonces se confirma antes de llenarlo. */
export const formularioTieneDatos = (formulario) => CAMPOS_DESDE_SECOP.some(([campo]) => !vacio(formulario?.[campo]));

/**
 * La referencia de SECOP II que muestra el editor: la que el formulario tomó de SECOP II, si la
 * tomó (`pendiente`: se guarda con el contrato), o la que el contrato tiene guardada. Si se
 * desvinculó (`desvinculado`, pendiente de guardar), la vacía del formulario.
 * `enlace` es null si no es un enlace web; `consultado`, la fecha de la última consulta guardada.
 */
export const referenciaSecop = (formulario, contrato) => {
    const desvinculado = Boolean(formulario?.secop_desvincular) && !formulario?.secop_consultado;
    const fuente = formulario?.secop_consultado || desvinculado ? formulario : contrato;
    const enlace = texto(fuente?.url_secop);
    return {
        id: texto(fuente?.secop_id_contrato),
        proceso: texto(fuente?.secop_proceso),
        enlace: esEnlaceWeb(enlace) ? enlace : null,
        consultado: contrato?.secop_consultado_at ?? null,
        pendiente: Boolean(formulario?.secop_consultado) || desvinculado,
        desvinculado,
    };
};

/**
 * Avisos de un resultado: el estado que falta elegir y los días adicionados en SECOP II. Con el
 * `formulario` que resultó de copiar, el estado se pide si el formulario quedó sin él; sin
 * formulario, si SECOP II no lo trae (o no se pudo traducir).
 */
export const avisosSecop = (resultado, formulario = null) => {
    const avisos = [];
    const sinEstado = formulario ? vacio(formulario.estado) : vacio(valoresDesdeSecop(resultado?.contrato).estado);
    if (sinEstado) {
        const estadoSecop = texto(resultado?.secop?.estado_secop);
        avisos.push(estadoSecop ? `Elige el estado: SECOP II dice «${estadoSecop}».` : 'Elige el estado: SECOP II no lo informa.');
    }
    const dias = Number(resultado?.secop?.dias_adicionados);
    if (Number.isFinite(dias) && dias > 0) {
        avisos.push(`SECOP II reporta ${dias} ${dias === 1 ? 'día adicionado' : 'días adicionados'}: regístralos como prórroga en Modificaciones.`);
    }
    return avisos;
};

/** Un resultado como se lista: número, entidad, contratista, valor, fechas, estado en SECOP II y si ya está registrado. */
export const filaResultadoSecop = (resultado) => {
    const contrato = resultado?.contrato ?? {};
    const registrado = resultado?.registrado?.id ? resultado.registrado : null;
    return {
        clave: texto(contrato.secop_id_contrato) || texto(contrato.n_contrato),
        idSecop: texto(contrato.secop_id_contrato) || '—',
        numero: texto(contrato.n_contrato) || 'Sin número',
        entidad: texto(contrato.contratante) || '—',
        contratista: texto(contrato.contratista) || '—',
        valor: textoValor(contrato.valor_inicial),
        suscripcion: textoFecha(contrato.fecha_suscripcion),
        inicio: textoFecha(contrato.fecha_inicio),
        fin: textoFecha(contrato.fecha_fin_inicial),
        estado: texto(resultado?.secop?.estado_secop) || '—',
        registrado: registrado ? { ruta: rutaContrato(registrado.id), numero: texto(registrado.n_contrato) || 'Sin número' } : null,
    };
};

// El nombre del tipo de contrato del catálogo o, si no está, el del contrato abierto.
const nombreTipo = (id, tipos, contrato) => {
    const enLista = (tipos ?? []).find((t) => String(t.id) === String(id));
    if (enLista) return enLista.nombre;
    const delContrato = contrato?.tipo_contrato;
    return delContrato && String(delContrato.id) === String(id) ? delContrato.nombre : `Tipo ${id}`;
};

// Un valor del formulario como se lee en la tabla de diferencias.
const textoCampo = (campo, valor, tipos, contrato) => {
    if (vacio(valor)) return '—';
    if (campo === 'valor_inicial') return textoValor(valorParaApi(valor));
    if (CAMPOS_FECHA.includes(campo)) return textoFecha(valor);
    if (campo === 'estado') return etiquetaEstadoContrato(valor);
    if (campo === 'tipo_contrato_id') return nombreTipo(valor, tipos, contrato);
    return String(valor).trim();
};

const iguales = (campo, a, b) => {
    if (campo === 'valor_inicial') return Number(valorParaApi(a) ?? 0) === Number(valorParaApi(b) ?? 0) && vacio(a) === vacio(b);
    return texto(a) === texto(b);
};

/**
 * "Actualizar desde SECOP II": los campos en que el formulario difiere de SECOP II, en el orden
 * del formulario, con su etiqueta, los dos valores legibles (`actual`, el del formulario, y
 * `secop`), si va `marcado` por defecto y su `nota`. Un campo que SECOP II no trae no se compara.
 * El estado, el valor inicial y la fecha fin inicial no van marcados (NOTAS_SIN_MARCAR).
 */
export const diferenciasSecop = (formulario, resultado, { tipos = null, contrato = null } = {}) => {
    const deSecop = { ...valoresDesdeSecop(resultado?.contrato), ...referenciaDesdeSecop(resultado?.contrato) };
    const referencia = referenciaSecop(formulario, contrato);
    const actuales = { ...formulario, secop_proceso: referencia.proceso, url_secop: referencia.enlace ?? '' };
    return [...CAMPOS_DESDE_SECOP, ...REFERENCIA_COMPARADA]
        .filter(([campo]) => !vacio(deSecop[campo]) && !iguales(campo, actuales[campo], deSecop[campo]))
        .map(([campo, etiqueta]) => ({
            campo,
            etiqueta,
            actual: textoCampo(campo, actuales[campo], tipos, contrato),
            secop: textoCampo(campo, deSecop[campo], tipos, contrato),
            marcado: !NOTAS_SIN_MARCAR[campo],
            nota: NOTAS_SIN_MARCAR[campo] ?? null,
        }));
};

/** Los campos marcados por defecto de una tabla de diferencias. */
export const camposMarcados = (diferencias) => (diferencias ?? []).filter((d) => d.marcado).map((d) => d.campo);

/**
 * "Copiar al formulario": el formulario con los campos `elegidos` tomados de SECOP II y la
 * referencia del resultado (el proceso y el enlace, solo si se eligieron; si no, los que ya
 * tenía), marcado como consultado en SECOP II.
 */
export const aplicarDiferencias = (formulario, resultado, elegidos, contrato = null) => {
    const deSecop = valoresDesdeSecop(resultado?.contrato);
    const nuevaReferencia = referenciaDesdeSecop(resultado?.contrato);
    const referencia = referenciaSecop(formulario, contrato);
    const lista = elegidos ?? [];
    const copiados = Object.fromEntries(CAMPOS_DESDE_SECOP
        .filter(([campo]) => lista.includes(campo) && deSecop[campo] !== undefined)
        .map(([campo]) => [campo, deSecop[campo]]));
    return {
        ...formulario,
        ...copiados,
        secop_id_contrato: nuevaReferencia.secop_id_contrato,
        secop_proceso: lista.includes('secop_proceso') ? nuevaReferencia.secop_proceso : referencia.proceso,
        url_secop: lista.includes('url_secop') ? nuevaReferencia.url_secop : (referencia.enlace ?? ''),
        secop_consultado: true,
    };
};

/** Los campos del formulario que SECOP II cambió entre dos formularios: sus errores ya no aplican. */
export const camposCambiados = (antes, despues) => CAMPOS_DESDE_SECOP.map(([campo]) => campo)
    .filter((campo) => (antes?.[campo] ?? '') !== (despues?.[campo] ?? ''));

/**
 * El mensaje de un error de las rutas de SECOP II: el del servidor (502 y 404 traen `mensaje`; un
 * 422, el primero de `errors`) o, si no trae, el genérico de la API.
 */
export const mensajeSecop = (error) => {
    const datos = error?.response?.data;
    if (typeof datos?.mensaje === 'string' && datos.mensaje.trim()) return datos.mensaje;
    if (error?.response?.status === 422 && datos?.errors && typeof datos.errors === 'object') {
        const primero = Object.values(datos.errors).flatMap((m) => [].concat(m ?? [])).find(Boolean);
        if (primero) return String(primero);
    }
    return mensajeApi(error);
};
```

En `resources/css/Contratacion.css`, reemplazar (1 de 1):

```css
.ct-editor { overflow: clip; }
.ct-editor .modal-actions { margin: 0; border-radius: 0 0 var(--gp-radio-grande) var(--gp-radio-grande); }

@media (max-width: 720px) {
    .ct-dialogo-fondo { align-items: flex-end; padding: 0; }
    .ct-dialogo { width: 100%; max-height: 92dvh; border-radius: 14px 14px 0 0; }
```

por:

```css
.ct-editor { overflow: clip; }
.ct-editor .modal-actions { margin: 0; border-radius: 0 0 var(--gp-radio-grande) var(--gp-radio-grande); }

/* SECOP II en el editor de contratos: la referencia, los botones y sus diálogos. */
.ct-secop { display: grid; gap: .45rem; padding: .75rem 1rem; margin: 0 0 1rem; background: var(--gp-superficie-2); border: 1px solid var(--gp-linea); border-radius: var(--gp-radio); }
.ct-secop-cabecera { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
.ct-secop-cabecera h4 { flex: 1 1 auto; margin: 0; font-size: .95rem; }
.ct-secop-referencia { display: flex; flex-wrap: wrap; gap: .25rem 1rem; align-items: baseline; margin: 0; font-size: .88rem; }
.ct-secop-nota { display: block; margin: 0; font-size: .8rem; font-weight: 400; color: var(--gp-texto-3); }
.ct-secop-mensaje { margin: 0; font-size: .85rem; color: var(--gp-verde-texto); }
.ct-secop-avisos { display: grid; gap: .3rem; padding: .55rem .8rem .55rem 1.8rem; margin: 0; font-size: .85rem; color: var(--gp-texto); background: var(--gp-ambar-suave); border-left: 3px solid var(--gp-ambar); border-radius: var(--gp-radio); }
.ct-secop-busqueda { padding: 0 0 1rem; }
.ct-secop-resultados { display: grid; gap: .7rem; padding: 0; margin: 0; list-style: none; }
.ct-secop-resultado { display: grid; gap: .55rem; padding: .8rem 1rem; background: var(--gp-superficie); border: 1px solid var(--gp-linea); border-radius: var(--gp-radio); }
.ct-secop-resultado-cabecera { display: flex; flex-wrap: wrap; gap: .4rem 1rem; align-items: baseline; justify-content: space-between; overflow-wrap: anywhere; }
.ct-secop-datos { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: .4rem 1rem; margin: 0; }
.ct-secop-datos dt { font-size: .72rem; font-weight: 600; color: var(--gp-texto-3); }
.ct-secop-datos dd { margin: .1rem 0 0; font-size: .86rem; color: var(--gp-texto); overflow-wrap: anywhere; }
.ct-secop-resultado-accion { display: flex; justify-content: flex-end; font-size: .86rem; }
.ct-secop-tabla { overflow-x: auto; margin: .8rem 0 1rem; }
.ct-secop-tabla table { width: 100%; border-collapse: collapse; font-size: .86rem; }
.ct-secop-tabla th, .ct-secop-tabla td { padding: .5rem .6rem; text-align: left; vertical-align: top; border-bottom: 1px solid var(--gp-linea); overflow-wrap: anywhere; }
.ct-secop-tabla thead th { font-size: .74rem; font-weight: 600; color: var(--gp-texto-3); }
.ct-secop-tabla tbody th { font-weight: 600; }
.ct-secop-tabla tbody th .ct-secop-nota { margin-top: .2rem; }

@media (max-width: 720px) {
    .ct-dialogo-fondo { align-items: flex-end; padding: 0; }
    .ct-dialogo { width: 100%; max-height: 92dvh; border-radius: 14px 14px 0 0; }
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (1 de 2):

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

por:

```jsx
import {
    CAMPOS_PROCESO, ESTADOS_PROCESO, MAXIMO_OBSERVACION, MAXIMO_OBSERVACIONES_PROCESO, PLATAFORMAS, avisoCambioEstado, datosEstado, datosProceso,
    erroresPorCampo, esEdicionDesactualizada, etiquetaEstadoProceso, etiquetaPlataforma, formularioProceso,
    modoTrasGuardarContrato, numeroProceso, opcionesConActual, opcionesFuenteProceso, ordenarCatalogo, puedeEditarProcesos, puedeRegistrarContrato,
    textoContratos, textoFecha, textoValor, tonoEstado, validarProceso, valorEnEdicion,
} from '../utils/contratacion';
import ContratoEditor from './contratos/ContratoEditor';
import useDialogoModal from './useDialogoModal';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (2 de 2):

```jsx
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
            return;
        }
        if (e.key !== 'Tab') return;
        const focos = [...caja.current.querySelectorAll(ENFOCABLES)].filter((el) => el.getClientRects().length > 0);
        const destino = indiceFocoAtrapado(focos.indexOf(document.activeElement), focos.length, e.shiftKey);
        if (destino === null) return;
        e.preventDefault();
        (focos[destino] ?? caja.current).focus();
    };
};

/**
 * Diálogo modal accesible: Escape llama a `onEscape`, un clic en el fondo a `onFondo` y, al
 * cerrar, el foco vuelve a donde estaba (el botón "Procesos" de la fila).
```

por:

```jsx
// Un contrato nuevo no tiene pestañas que recargar: el editor lo cierra quien lo abrió al guardar.
const sinRecargar = async () => null;

/**
 * Diálogo modal accesible: Escape llama a `onEscape`, un clic en el fondo a `onFondo` y, al
 * cerrar, el foco vuelve a donde estaba (el botón "Procesos" de la fila).
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (1 de 8):

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
    anexosParaGuardar, aplicarPrecarga, avanceFinancieroDeActas, avanceFisicoDeActividades, calculoActa, cambiarProyecto, contratoVacio,
```

por:

```jsx
import React, { useEffect, useRef, useState } from 'react';
import {
    faArrowUpRightFromSquare, faCalculator, faEye, faFile, faInfo, faLinkSlash, faMagnifyingGlass, faPlus, faRotate, faSave, faTimes, faTrash,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
import axios from '../../axios';
import { avisarError } from '../../utils/avisosPlantillas';
import { datosEstado, esEdicionDesactualizada, textoFecha, textoValor, valorEnEdicion } from '../../utils/contratacion';
import {
    ACTA_VACIA, ACTIVIDAD_VACIA, ANEXO_VACIO, AVANCE_VACIO, ESTADOS_CONTRATO, MODIFICACION_VACIA, actividadConAvance, anexosDelContrato,
    anexosParaGuardar, aplicarPrecarga, avanceFinancieroDeActas, avanceFisicoDeActividades, calculoActa, cambiarProyecto, contratoVacio,
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (2 de 8):

```jsx
import { opcionesProyecto } from '../../utils/contratosModulo';
import { formatoMoneda, limpiarMoneda, monedaEnEdicion, numeroDeMoneda } from '../../utils/moneda';
import { htmlErrores, mensajeApi } from '../../utils/plantillasChecklist';
import '../../../css/Parametros.css';
import '../../../css/Contratacion.css';

/** Catálogo de tipos de contrato (GET /tiposProcesos); null si no se pudo traer. */
export const pedirTiposContrato = async () => {
    try {
```

por:

```jsx
import { opcionesProyecto } from '../../utils/contratosModulo';
import { formatoMoneda, limpiarMoneda, monedaEnEdicion, numeroDeMoneda } from '../../utils/moneda';
import { htmlErrores, mensajeApi } from '../../utils/plantillasChecklist';
import {
    TEXTO_REEMPLAZAR_CON_SECOP, aplicarDiferencias, avisosSecop, camposCambiados, desvincularSecop, diferenciasSecop, formularioTieneDatos, importarSecop,
    mensajeSecop, referenciaSecop, vincularSecop,
} from '../../utils/secop';
import { BuscarSecop, DiferenciasSecop } from './SecopContrato';
import '../../../css/Parametros.css';
import '../../../css/Contratacion.css';

const MENSAJE_COINCIDE_SECOP = 'Coincide con SECOP II: guarda el contrato para registrar la consulta.';
const MENSAJE_VINCULADO_SECOP = 'Contrato vinculado a SECOP II: guárdalo para conservar el vínculo.';

/** Catálogo de tipos de contrato (GET /tiposProcesos); null si no se pudo traer. */
export const pedirTiposContrato = async () => {
    try {
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (3 de 8):

```jsx
 *   con proyecto puede cambiarlo, pero no quitarlo. Al cambiarlo se cargan los procesos del
 *   elegido. Sin la prop, el proyecto es el de `proyecto` o el del contrato, sin selector.
 * - `puedeEditar`: sin permiso, el contrato se ve sin controles para modificarlo.
 * - `recargar(id)`: vuelve a traer los contratos tras un cambio en las pestañas; resuelve el contrato `id` recargado o null.
 * - `onGuardado(contrato)`: tras guardar (con los avisos ya mostrados); quien lo pinta cierra el editor.
 * - `onCancelar()`: "Cancelar" o "Volver a la lista".
```

por:

```jsx
 *   con proyecto puede cambiarlo, pero no quitarlo. Al cambiarlo se cargan los procesos del
 *   elegido. Sin la prop, el proyecto es el de `proyecto` o el del contrato, sin selector.
 * - `puedeEditar`: sin permiso, el contrato se ve sin controles para modificarlo.
 *   Con permiso, el formulario ofrece SECOP II: "Buscar en SECOP II" (contrato nuevo o sin ID de
 *   SECOP II) y "Actualizar desde SECOP II" (con ID). Lo que se copia de SECOP II se guarda con el contrato.
 * - `recargar(id)`: vuelve a traer los contratos tras un cambio en las pestañas; resuelve el contrato `id` recargado o null.
 * - `onGuardado(contrato)`: tras guardar (con los avisos ya mostrados); quien lo pinta cierra el editor.
 * - `onCancelar()`: "Cancelar" o "Volver a la lista".
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (4 de 8):

```jsx
    const [formModificacion, setFormModificacion] = useState(MODIFICACION_VACIA);
    const [resumenModificacion, setResumenModificacion] = useState(null);

    useEffect(() => {
        // Los estados de los procesos pueden haber cambiado desde que se abrió la lista.
        cargarProcesosContrato(contrato?.proyecto ?? proyecto?.id);
```

por:

```jsx
    const [formModificacion, setFormModificacion] = useState(MODIFICACION_VACIA);
    const [resumenModificacion, setResumenModificacion] = useState(null);

    // SECOP II: el diálogo abierto (null, { tipo: 'buscar' } o { tipo: 'diferencias', titulo,
    // resultado, diferencias, avisos }), los avisos del último resultado usado y el mensaje de la
    // última acción. Turno de "Actualizar desde SECOP II": una respuesta atrasada se descarta.
    const [dialogoSecop, setDialogoSecop] = useState(null);
    const [avisosDeSecop, setAvisosDeSecop] = useState([]);
    const [mensajeDeSecop, setMensajeDeSecop] = useState(null);
    const [consultandoSecop, setConsultandoSecop] = useState(false);
    const turnoSecop = useRef(0);
    // El formulario del último render, para comparar con SECOP II cuando llega la respuesta.
    const formularioActual = useRef(formContrato);
    formularioActual.current = formContrato;

    useEffect(() => {
        // Los estados de los procesos pueden haber cambiado desde que se abrió la lista.
        cargarProcesosContrato(contrato?.proyecto ?? proyecto?.id);
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (5 de 8):

```jsx
        return lista;
    };

    useEffect(() => {
        if (!focoErrorContrato) return;
        const campo = primerCampoConError(erroresContrato);
```

por:

```jsx
        return lista;
    };

    // ---------- SECOP II ----------

    // El formulario con lo copiado de SECOP II: los errores de los campos que cambiaron ya no aplican.
    const copiarAlFormulario = (siguiente, avisos, mensaje) => {
        const cambiados = camposCambiados(formularioActual.current, siguiente);
        setErroresContrato(prev => (cambiados.some(c => prev[c])
            ? { ...prev, ...Object.fromEntries(cambiados.map(c => [c, undefined])) }
            : prev));
        setFormContrato(siguiente);
        setAvisosDeSecop(avisos);
        setMensajeDeSecop(mensaje);
    };

    // Las diferencias con SECOP II en su diálogo o, si no hay, el formulario marcado como
    // consultado (así "Última consulta" se actualiza al guardar) y `mensajeSinDiferencias`.
    const mostrarDiferencias = (formulario, resultado, titulo, mensajeSinDiferencias = MENSAJE_COINCIDE_SECOP) => {
        const diferencias = diferenciasSecop(formulario, resultado, { tipos, contrato: editingContrato });
        if (!diferencias.length) {
            const consultado = aplicarDiferencias(formulario, resultado, [], editingContrato);
            setDialogoSecop(null);
            copiarAlFormulario(consultado, avisosSecop(resultado, consultado), mensajeSinDiferencias);
            return;
        }
        setDialogoSecop({ tipo: 'diferencias', titulo, resultado, diferencias, avisos: avisosSecop(resultado, formulario) });
    };

    // "Usar este": un contrato nuevo se llena con lo de SECOP II (si ya tenía datos, se confirma);
    // uno existente se vincula y luego se ofrecen sus diferencias para copiar los demás campos.
    const usarResultadoSecop = async (resultado) => {
        if (editingContrato) {
            const vinculado = vincularSecop(formularioActual.current, resultado);
            copiarAlFormulario(vinculado, avisosSecop(resultado, vinculado), MENSAJE_VINCULADO_SECOP);
            mostrarDiferencias(vinculado, resultado, '¿Copiar también los demás datos?', MENSAJE_VINCULADO_SECOP);
            return;
        }
        if (formularioTieneDatos(formularioActual.current)) {
            const { isConfirmed } = await Swal.fire({
                icon: 'question',
                titleText: '¿Reemplazar lo que escribiste?',
                text: TEXTO_REEMPLAZAR_CON_SECOP,
                showCancelButton: true,
                confirmButtonText: 'Sí, usar SECOP II',
                cancelButtonText: 'Cancelar',
            });
            if (!isConfirmed) return;
        }
        const importado = importarSecop(formularioActual.current, resultado);
        copiarAlFormulario(importado, avisosSecop(resultado, importado),
            'Formulario lleno con los datos de SECOP II: revísalos y guarda el contrato.');
        setDialogoSecop(null);
    };

    // "Actualizar desde SECOP II" (GET /secop/contratos/{id}): las diferencias con el formulario.
    const actualizarDesdeSecop = async () => {
        const numero = ++turnoSecop.current;
        setConsultandoSecop(true);
        setMensajeDeSecop(null);
        try {
            const { data } = await axios.get(`/secop/contratos/${encodeURIComponent(editingContrato.secop_id_contrato)}`);
            if (numero !== turnoSecop.current) return;
            mostrarDiferencias(formularioActual.current, data?.resultado, 'Actualizar desde SECOP II');
        } catch (error) {
            if (numero !== turnoSecop.current) return;
            Swal.fire({ icon: error?.response?.status === 404 ? 'warning' : 'error', titleText: 'No se pudo actualizar desde SECOP II', text: mensajeSecop(error) });
        } finally {
            if (numero === turnoSecop.current) setConsultandoSecop(false);
        }
    };

    const copiarDiferencias = (elegidos) => {
        const { resultado } = dialogoSecop;
        const aplicado = aplicarDiferencias(formularioActual.current, resultado, elegidos, editingContrato);
        copiarAlFormulario(aplicado, avisosSecop(resultado, aplicado),
            'Datos de SECOP II copiados al formulario: guarda el contrato para conservarlos.');
        setDialogoSecop(null);
    };

    // "Desvincular de SECOP II" (un ID equivocado): con confirmación, el formulario queda sin la
    // referencia y, al guardar, el servidor la borra. Una consulta pendiente queda atrasada.
    const desvincularDeSecop = async () => {
        const { isConfirmed } = await Swal.fire({
            icon: 'question',
            titleText: '¿Desvincular de SECOP II?',
            text: 'Al guardar el contrato se borran el ID, el proceso y el enlace de SECOP II. Los demás datos no cambian. Después podrás buscarlo de nuevo.',
            showCancelButton: true,
            confirmButtonText: 'Sí, desvincular',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        turnoSecop.current += 1;
        setConsultandoSecop(false);
        setDialogoSecop(null);
        setFormContrato(prev => desvincularSecop(prev));
        setAvisosDeSecop([]);
        setMensajeDeSecop('Contrato desvinculado de SECOP II: guárdalo para quitar el vínculo.');
    };

    // Al desmontar el editor, una consulta pendiente queda atrasada.
    useEffect(() => () => { turnoSecop.current += 1; }, []);

    useEffect(() => {
        if (!focoErrorContrato) return;
        const campo = primerCampoConError(erroresContrato);
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (6 de 8):

```jsx
    const datosProcesoContrato = procesoFormulario ? datosDelProceso(procesoFormulario) : null;
    const notaProcesoContrato = notaProceso(procesosContrato, formContrato.proceso_id);
    const vigentesContrato = vigentesDistintos(editingContrato);

    // Propiedades comunes de un control del formulario de contrato: valor, cambio y aviso de error.
    // Sin permiso para editar, el control queda deshabilitado.
```

por:

```jsx
    const datosProcesoContrato = procesoFormulario ? datosDelProceso(procesoFormulario) : null;
    const notaProcesoContrato = notaProceso(procesosContrato, formContrato.proceso_id);
    const vigentesContrato = vigentesDistintos(editingContrato);
    // SECOP II: la referencia que se muestra y qué se ofrece según tenga o no ID guardado.
    const referenciaContratoSecop = referenciaSecop(formContrato, editingContrato);
    const puedeBuscarSecop = puedeEditar && !editingContrato?.secop_id_contrato;
    const puedeActualizarSecop = puedeEditar && Boolean(editingContrato?.secop_id_contrato) && !referenciaContratoSecop.desvinculado;

    // Propiedades comunes de un control del formulario de contrato: valor, cambio y aviso de error.
    // Sin permiso para editar, el control queda deshabilitado.
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (7 de 8):

```jsx
                        <p className="contratos-info">
                            Completa los datos del contrato. Los campos marcados con * son obligatorios.
                        </p>
                        <div className="contrato-form" ref={formularioContratoRef}>
                            {proyectos !== undefined && campoContrato('proyecto', 'Proyecto', (
                                // Los errores del proyecto llegan en un aviso (erroresGuardarContrato): aquí, solo la nota.
```

por:

```jsx
                        <p className="contratos-info">
                            Completa los datos del contrato. Los campos marcados con * son obligatorios.
                        </p>
                        {(puedeEditar || referenciaContratoSecop.id) && (
                            <section className="ct-secop" aria-labelledby="secop-titulo">
                                <div className="ct-secop-cabecera">
                                    <h4 id="secop-titulo">SECOP II</h4>
                                    {puedeBuscarSecop && (
                                        <button type="button" className="gp-boton" onClick={() => setDialogoSecop({ tipo: 'buscar' })}>
                                            <FontAwesomeIcon icon={faMagnifyingGlass} /> Buscar en SECOP II
                                        </button>
                                    )}
                                    {puedeActualizarSecop && (
                                        <button type="button" className="gp-boton" onClick={actualizarDesdeSecop} disabled={consultandoSecop}>
                                            <FontAwesomeIcon icon={faRotate} /> {consultandoSecop ? 'Consultando…' : 'Actualizar desde SECOP II'}
                                        </button>
                                    )}
                                    {puedeActualizarSecop && (
                                        <button type="button" className="gp-boton" onClick={desvincularDeSecop}>
                                            <FontAwesomeIcon icon={faLinkSlash} /> Desvincular de SECOP II
                                        </button>
                                    )}
                                </div>
                                {referenciaContratoSecop.id ? (
                                    <p className="ct-secop-referencia">
                                        <span>
                                            ID del contrato:{' '}
                                            {referenciaContratoSecop.enlace ? (
                                                <a className="ct-enlace" href={referenciaContratoSecop.enlace} target="_blank" rel="noopener noreferrer">
                                                    {referenciaContratoSecop.id} <FontAwesomeIcon icon={faArrowUpRightFromSquare} aria-hidden="true" />
                                                    <span className="ct-oculto"> (se abre en otra pestaña)</span>
                                                </a>
                                            ) : <span className="ct-clave">{referenciaContratoSecop.id}</span>}
                                        </span>
                                        <span className="ct-secop-nota">
                                            {referenciaContratoSecop.pendiente
                                                ? 'Se guarda con el contrato.'
                                                : (referenciaContratoSecop.consultado
                                                    ? `Última consulta: ${textoFecha(referenciaContratoSecop.consultado)}`
                                                    : 'Sin consultas a SECOP II registradas.')}
                                        </span>
                                    </p>
                                ) : referenciaContratoSecop.desvinculado ? (
                                    <p className="ct-secop-nota">Al guardar, el contrato queda sin vínculo con SECOP II. Después podrás buscarlo de nuevo.</p>
                                ) : (
                                    <p className="ct-secop-nota">Si el contrato está en SECOP II, búscalo para llenar el formulario con sus datos; si no, llénalo a mano.</p>
                                )}
                                <div aria-live="polite">
                                    {mensajeDeSecop && <p className="ct-secop-mensaje">{mensajeDeSecop}</p>}
                                    {avisosDeSecop.length > 0 && (
                                        <ul className="ct-secop-avisos">
                                            {avisosDeSecop.map(aviso => <li key={aviso}>{aviso}</li>)}
                                        </ul>
                                    )}
                                </div>
                            </section>
                        )}
                        <div className="contrato-form" ref={formularioContratoRef}>
                            {proyectos !== undefined && campoContrato('proyecto', 'Proyecto', (
                                // Los errores del proyecto llegan en un aviso (erroresGuardarContrato): aquí, solo la nota.
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (8 de 8):

```jsx
                )}
            </div>

            <div className="modal-actions">
                <button type="button" onClick={onCancelar} className="btn-cancel">
                    <FontAwesomeIcon icon={faTimes} /> Cancelar
```

por:

```jsx
                )}
            </div>

            {dialogoSecop?.tipo === 'buscar' && (
                <BuscarSecop onElegir={usarResultadoSecop} onCerrar={() => setDialogoSecop(null)} />
            )}
            {dialogoSecop?.tipo === 'diferencias' && (
                <DiferenciasSecop
                    titulo={dialogoSecop.titulo}
                    diferencias={dialogoSecop.diferencias}
                    avisos={dialogoSecop.avisos}
                    onCopiar={copiarDiferencias}
                    onCerrar={() => setDialogoSecop(null)}
                />
            )}

            <div className="modal-actions">
                <button type="button" onClick={onCancelar} className="btn-cancel">
                    <FontAwesomeIcon icon={faTimes} /> Cancelar
```

En `resources/js/utils/contratos.js`, reemplazar (1 de 3):

```js
    };
};

const avance = (valor) => {
    if (vacio(valor)) return null;
    const numero = Number(String(valor).replace(',', '.'));
```

por:

```js
    };
};

// ---------- Referencia de SECOP II ----------

/** Los campos de la referencia de SECOP II que guarda el contrato (sin control en el formulario). */
export const CAMPOS_SECOP = ['secop_id_contrato', 'secop_proceso', 'url_secop'];

/**
 * La referencia de SECOP II para /guardarContrato. Solo va si el formulario usó SECOP II
 * (`secop_consultado`, que marcan las utilidades de secop.js): entonces van los tres campos, vacío
 * como null, y `secop_consultado`, para que el servidor anote la fecha de la consulta. Si no, no
 * se envía nada y la referencia guardada no cambia, salvo que se haya desvinculado
 * (`secop_desvincular`, de desvincularSecop): entonces van los tres como null, sin consulta.
 */
export const datosSecop = (formulario) => {
    if (!formulario?.secop_consultado) {
        return formulario?.secop_desvincular ? Object.fromEntries(CAMPOS_SECOP.map((campo) => [campo, null])) : {};
    }
    return {
        ...Object.fromEntries(CAMPOS_SECOP.map((campo) => [campo, vacio(formulario[campo]) ? null : String(formulario[campo]).trim()])),
        secop_consultado: true,
    };
};

const avance = (valor) => {
    if (vacio(valor)) return null;
    const numero = Number(String(valor).replace(',', '.'));
```

En `resources/js/utils/contratos.js`, reemplazar (2 de 3):

```js
 * Lo que espera `formContrato` de /guardarContrato. Se envían todos los campos: un opcional vacío
 * va como null y así se borra al editar. El valor y la fecha fin vigentes no se envían: los
 * calcula el servidor. Al editar, `contrato` es el que se abrió: van su id y su `updated_at`,
 * para que el servidor rechace el cambio si otra persona lo modificó entretanto.
 */
export const datosContrato = (formulario, contrato = null) => {
    const texto = (campo) => (vacio(formulario[campo]) ? null : String(formulario[campo]).trim());
```

por:

```js
 * Lo que espera `formContrato` de /guardarContrato. Se envían todos los campos: un opcional vacío
 * va como null y así se borra al editar. El valor y la fecha fin vigentes no se envían: los
 * calcula el servidor. Al editar, `contrato` es el que se abrió: van su id y su `updated_at`,
 * para que el servidor rechace el cambio si otra persona lo modificó entretanto. La referencia de
 * SECOP II va solo si el formulario la tomó de SECOP II (datosSecop).
 */
export const datosContrato = (formulario, contrato = null) => {
    const texto = (campo) => (vacio(formulario[campo]) ? null : String(formulario[campo]).trim());
```

En `resources/js/utils/contratos.js`, reemplazar (3 de 3):

```js
        porcentaje_anticipo: anticipo && texto('porcentaje_anticipo') !== null ? Number(texto('porcentaje_anticipo')) : null,
        avance_fisico: avance(formulario.avance_fisico),
        avance_financiero: avance(formulario.avance_financiero),
        ...(contrato ? { actualizado: contrato.updated_at ?? null } : {}),
    };
};
```

por:

```js
        porcentaje_anticipo: anticipo && texto('porcentaje_anticipo') !== null ? Number(texto('porcentaje_anticipo')) : null,
        avance_fisico: avance(formulario.avance_fisico),
        avance_financiero: avance(formulario.avance_financiero),
        ...datosSecop(formulario),
        ...(contrato ? { actualizado: contrato.updated_at ?? null } : {}),
    };
};
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/secop.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1119 passed.

Run: `npm test`
Expected: 624 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/js/components/contratos/SecopContrato.jsx resources/js/components/useDialogoModal.js resources/js/utils/secop.js resources/js/utils/secop.test.js resources/css/Contratacion.css resources/js/components/ProcesosProyecto.jsx resources/js/components/contratos/ContratoEditor.jsx resources/js/utils/contratos.js
git commit -m "feat: search, import and update from SECOP II in the contract editor"
```

---

### Tarea 5: Contratos de la empresa en SECOP II

Vista del módulo Contratos con los contratos del NIT de la empresa como proveedor, con "Ya registrado" o "Importar".

**Files:**
- Modify: `resources/css/Contratacion.css`
- Modify: `resources/js/components/contratos/ContratoEditor.jsx`
- Modify: `resources/js/components/contratos/ContratosModulo.jsx`
- Modify: `resources/js/components/contratos/SecopContrato.jsx`
- Modify: `resources/js/utils/contratosModulo.js`
- Test: `resources/js/utils/contratosModulo.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/contratosModulo.test.js`, reemplazar (1 de 2):

```js
import {
    SIN_PROYECTO, consultaDeFiltros, esIdContrato, filaContrato, filtrosDeConsulta, hayFiltros, opcionesFiltroEstado, opcionesFiltroProyecto,
    opcionesProyecto, parametrosBusqueda, puedeEditarContratos, rutaContrato, rutaListaDesde, textoAvance, textoListaVacia,
    tonoEstadoContrato,
} from './contratosModulo';
import { ESTADOS_CONTRATO } from './contratos';
import { RUTA_CONTRATOS } from './navegacion';
```

por:

```js
import {
    SIN_PROYECTO, consultaDeFiltros, esIdContrato, filaContrato, filtrosDeConsulta, hayFiltros, opcionesFiltroEstado, opcionesFiltroProyecto,
    opcionesProyecto, parametrosBusqueda, puedeEditarContratos, rutaContrato, rutaListaDesde, textoAvance, textoListaVacia,
    tonoEstadoContrato, CLAVE_NIT_EMPRESA, TEXTO_SECOP_VACIO, VISTA_LISTA, VISTA_SECOP, consultaVistaSecop, esNitValido,
    errorNit, estadoImportarSecop, estadoSinSecop, guardarNitEmpresa, leerNitEmpresa, nitDeConsulta, nitParaMostrar, normalizarNit,
    resultadoSecopDeEstado, rutaVistaSecop, vistaDeConsulta,
} from './contratosModulo';
import { ESTADOS_CONTRATO } from './contratos';
import { RUTA_CONTRATOS } from './navegacion';
```

En `resources/js/utils/contratosModulo.test.js`, reemplazar (2 de 2):

```js
        expect(puedeEditarContratos(null)).toBe(false);
    });
});
```

por:

```js
        expect(puedeEditarContratos(null)).toBe(false);
    });
});

// ---------- Contratos de la empresa en SECOP II ----------

describe('vistaDeConsulta', () => {
    it('?vista=secop abre los contratos de la empresa en SECOP II para quien puede editar', () => {
        expect(vistaDeConsulta(consulta('vista=secop'), true)).toBe(VISTA_SECOP);
        expect(vistaDeConsulta(consulta('vista=secop&nit=900123456'), true)).toBe(VISTA_SECOP);
    });

    it('para un lector, o sin vista o con otra, es la lista', () => {
        expect(vistaDeConsulta(consulta('vista=secop'), false)).toBe(VISTA_LISTA);
        expect(vistaDeConsulta(consulta(''), true)).toBe(VISTA_LISTA);
        expect(vistaDeConsulta(consulta('vista=otra'), true)).toBe(VISTA_LISTA);
        expect(vistaDeConsulta(undefined, true)).toBe(VISTA_LISTA);
    });

    it('la consulta de cada vista', () => {
        expect(consultaVistaSecop('').toString()).toBe('vista=secop');
        expect(consultaVistaSecop('900.123.456-7').toString()).toBe('vista=secop&nit=900123456-7');
        expect(rutaVistaSecop()).toBe('/contratos?vista=secop');
    });
});

describe('NIT de la empresa', () => {
    it('acepta lo que acepta el servidor: dígitos con puntos o espacios y el dígito de verificación', () => {
        ['900123456', '900.123.456', '900 123 456', '900.123.456-7', '9'].forEach((v) => expect(esNitValido(v)).toBe(true));
        expect(esNitValido(' 900123456 ')).toBe(true);
    });

    it('rechaza lo demás', () => {
        ['', '  ', 'abc', '-900', '900-12', '900123456-', '.900', '900,123', '1'.repeat(21), null, undefined].forEach((v) => expect(esNitValido(v)).toBe(false));
    });

    it('normalizarNit quita puntos y espacios y conserva el dígito de verificación', () => {
        expect(normalizarNit(' 900.123 456-7 ')).toBe('900123456-7');
        expect(normalizarNit('900123456')).toBe('900123456');
        expect(normalizarNit('abc')).toBe('');
    });

    it('nitParaMostrar agrupa los miles con puntos', () => {
        expect(nitParaMostrar('900123456-7')).toBe('900.123.456-7');
        expect(nitParaMostrar('900 123 456')).toBe('900.123.456');
        expect(nitParaMostrar('12345')).toBe('12.345');
        expect(nitParaMostrar('123')).toBe('123');
        expect(nitParaMostrar('')).toBe('');
        expect(nitParaMostrar('abc')).toBe('');
    });

    it('errorNit: el mensaje del campo antes de consultar, como los del servidor', () => {
        expect(errorNit('  ')).toBe('Escribe el NIT de la empresa.');
        expect(errorNit('abc')).toBe('Escribe el NIT solo con números (puede llevar puntos y el dígito de verificación con guion).');
        expect(errorNit('900.123.456-7')).toBeNull();
    });

    it('nitDeConsulta toma el NIT de la URL solo si es válido, normalizado', () => {
        expect(nitDeConsulta(consulta('vista=secop&nit=900.123.456-7'))).toBe('900123456-7');
        expect(nitDeConsulta(consulta('vista=secop&nit=abc'))).toBe('');
        expect(nitDeConsulta(consulta('vista=secop'))).toBe('');
        expect(nitDeConsulta(undefined)).toBe('');
    });
});

describe('NIT recordado', () => {
    const almacen = (inicial = {}) => {
        const datos = { ...inicial };
        return {
            datos,
            getItem: (clave) => (clave in datos ? datos[clave] : null),
            setItem: (clave, valor) => { datos[clave] = String(valor); },
        };
    };
    const roto = { getItem: () => { throw new Error('bloqueado'); }, setItem: () => { throw new Error('bloqueado'); } };

    it('se guarda normalizado con una clave propia y se lee igual', () => {
        const a = almacen();
        guardarNitEmpresa('900.123.456-7', a);
        expect(a.datos).toEqual({ [CLAVE_NIT_EMPRESA]: '900123456-7' });
        expect(CLAVE_NIT_EMPRESA).toBe('gestpro.secop.nitEmpresa');
        expect(leerNitEmpresa(a)).toBe('900123456-7');
    });

    it('un NIT no válido no se guarda; uno guardado que no es válido se lee vacío', () => {
        const a = almacen();
        guardarNitEmpresa('abc', a);
        expect(a.datos).toEqual({});
        expect(leerNitEmpresa(almacen({ [CLAVE_NIT_EMPRESA]: '<script>' }))).toBe('');
        expect(leerNitEmpresa(almacen())).toBe('');
    });

    it('sin almacenamiento (bloqueado o ausente) no falla', () => {
        expect(leerNitEmpresa(roto)).toBe('');
        expect(() => guardarNitEmpresa('900123456', roto)).not.toThrow();
        expect(leerNitEmpresa(null)).toBe('');
        expect(() => guardarNitEmpresa('900123456', null)).not.toThrow();
    });
});

describe('importar de SECOP II', () => {
    const resultado = { contrato: { n_contrato: 'C-1', secop_id_contrato: 'CO1.PCCNTR.1' }, secop: {}, registrado: null };

    it('el estado de navegación lleva la lista de la que se viene y el resultado', () => {
        expect(estadoImportarSecop(resultado, '?vista=secop&nit=900123456'))
            .toEqual({ lista: '?vista=secop&nit=900123456', secop: resultado });
    });

    it('resultadoSecopDeEstado devuelve el resultado solo si tiene la forma de uno', () => {
        expect(resultadoSecopDeEstado({ lista: '?vista=secop', secop: resultado })).toBe(resultado);
        [null, undefined, {}, { secop: null }, { secop: 'x' }, { secop: {} }, { secop: { contrato: 'x' } }, { secop: { contrato: [] } }]
            .forEach((estado) => expect(resultadoSecopDeEstado(estado)).toBeNull());
    });

    it('al guardar se conserva solo la lista, no el resultado', () => {
        expect(estadoSinSecop({ lista: '?vista=secop', secop: resultado })).toEqual({ lista: '?vista=secop' });
        expect(estadoSinSecop(null)).toEqual({});
    });

    it('el texto sin resultados', () => {
        expect(TEXTO_SECOP_VACIO).toBe('SECOP II no tiene contratos con ese NIT como proveedor.');
    });
});
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/contratosModulo.test.js`
Expected: FALLAN 16 y pasan 26: no existen las utilidades de la vista de SECOP II del módulo (`vistaDeConsulta`, el NIT recordado y la importación).

- [ ] **Paso 3: Implementar**

En `resources/css/Contratacion.css`, reemplazar (1 de 1):

```css
.ct-secop-mensaje { margin: 0; font-size: .85rem; color: var(--gp-verde-texto); }
.ct-secop-avisos { display: grid; gap: .3rem; padding: .55rem .8rem .55rem 1.8rem; margin: 0; font-size: .85rem; color: var(--gp-texto); background: var(--gp-ambar-suave); border-left: 3px solid var(--gp-ambar); border-radius: var(--gp-radio); }
.ct-secop-busqueda { padding: 0 0 1rem; }
.ct-secop-resultados { display: grid; gap: .7rem; padding: 0; margin: 0; list-style: none; }
.ct-secop-resultado { display: grid; gap: .55rem; padding: .8rem 1rem; background: var(--gp-superficie); border: 1px solid var(--gp-linea); border-radius: var(--gp-radio); }
.ct-secop-resultado-cabecera { display: flex; flex-wrap: wrap; gap: .4rem 1rem; align-items: baseline; justify-content: space-between; overflow-wrap: anywhere; }
```

por:

```css
.ct-secop-mensaje { margin: 0; font-size: .85rem; color: var(--gp-verde-texto); }
.ct-secop-avisos { display: grid; gap: .3rem; padding: .55rem .8rem .55rem 1.8rem; margin: 0; font-size: .85rem; color: var(--gp-texto); background: var(--gp-ambar-suave); border-left: 3px solid var(--gp-ambar); border-radius: var(--gp-radio); }
.ct-secop-busqueda { padding: 0 0 1rem; }
/* Contratos de la empresa en SECOP II (módulo Contratos, ?vista=secop). */
.ct-secop-titulo { margin: 0 0 .3rem; font-size: 1.05rem; }
.ct-secop-empresa { align-items: flex-start; justify-content: flex-start; margin-top: .9rem; }
.ct-secop-empresa .pl-campo { flex: 1 1 16rem; max-width: 24rem; }
.ct-secop-empresa > .gp-boton { margin-top: 1.45rem; }
.ct-secop-conteo { margin-bottom: .6rem; }
.ct-secop-resultados { display: grid; gap: .7rem; padding: 0; margin: 0; list-style: none; }
.ct-secop-resultado { display: grid; gap: .55rem; padding: .8rem 1rem; background: var(--gp-superficie); border: 1px solid var(--gp-linea); border-radius: var(--gp-radio); }
.ct-secop-resultado-cabecera { display: flex; flex-wrap: wrap; gap: .4rem 1rem; align-items: baseline; justify-content: space-between; overflow-wrap: anywhere; }
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (1 de 6):

```jsx
import '../../../css/Parametros.css';
import '../../../css/Contratacion.css';

const MENSAJE_COINCIDE_SECOP = 'Coincide con SECOP II: guarda el contrato para registrar la consulta.';
const MENSAJE_VINCULADO_SECOP = 'Contrato vinculado a SECOP II: guárdalo para conservar el vínculo.';
```

por:

```jsx
import '../../../css/Parametros.css';
import '../../../css/Contratacion.css';

const MENSAJE_IMPORTADO_SECOP = 'Formulario lleno con los datos de SECOP II: revísalos y guarda el contrato.';
const MENSAJE_COINCIDE_SECOP = 'Coincide con SECOP II: guarda el contrato para registrar la consulta.';
const MENSAJE_VINCULADO_SECOP = 'Contrato vinculado a SECOP II: guárdalo para conservar el vínculo.';
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (2 de 6):

```jsx
 * - `proyecto`: el proyecto del contrato nuevo ({id, nombre}) o null; sus procesos llenan el select.
 * - `contrato`: el que se edita (un elemento de GET /listarContratos o de /contratos/{id}/detalle), o null para uno nuevo.
 * - `procesoInicial`: en un contrato nuevo, el id del proceso ya elegido; se aplica su precarga.
 * - `tiposContrato`: el catálogo si quien lo pinta ya lo trae (null mientras carga o si falló); sin la prop, lo pide el editor.
 * - `procesosIniciales`: los procesos del proyecto que ya se tienen, mientras llegan los recién pedidos.
 * - `proyectos`: con la prop (la lista de GET /proyectos, o null mientras carga), el formulario
```

por:

```jsx
 * - `proyecto`: el proyecto del contrato nuevo ({id, nombre}) o null; sus procesos llenan el select.
 * - `contrato`: el que se edita (un elemento de GET /listarContratos o de /contratos/{id}/detalle), o null para uno nuevo.
 * - `procesoInicial`: en un contrato nuevo, el id del proceso ya elegido; se aplica su precarga.
 * - `importacionSecop`: en un contrato nuevo, un resultado de SECOP II (de GET /secop/…) con que
 *   el formulario empieza lleno, como con "Usar este", con sus avisos. Sin la prop empieza vacío.
 * - `tiposContrato`: el catálogo si quien lo pinta ya lo trae (null mientras carga o si falló); sin la prop, lo pide el editor.
 * - `procesosIniciales`: los procesos del proyecto que ya se tienen, mientras llegan los recién pedidos.
 * - `proyectos`: con la prop (la lista de GET /proyectos, o null mientras carga), el formulario
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (3 de 6):

```jsx
 *   si se guardó), para que quien lo pinta no lo cierre a medias.
 */
const ContratoEditor = ({
    proyecto = null, contrato = null, procesoInicial = null, tiposContrato, procesosIniciales = null, proyectos, puedeEditar = true,
    recargar, onGuardado, onCancelar, onOcupado,
}) => {
    const [editingContrato, setEditingContrato] = useState(contrato);
```

por:

```jsx
 *   si se guardó), para que quien lo pinta no lo cierre a medias.
 */
const ContratoEditor = ({
    proyecto = null, contrato = null, procesoInicial = null, importacionSecop = null, tiposContrato, procesosIniciales = null, proyectos, puedeEditar = true,
    recargar, onGuardado, onCancelar, onOcupado,
}) => {
    const [editingContrato, setEditingContrato] = useState(contrato);
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (4 de 6):

```jsx
    const [formContrato, setFormContrato] = useState(() => {
        if (contrato) return formularioContrato(contrato);
        const vacio = contratoVacio(proyecto?.id);
        return procesoInicial ? { ...vacio, proceso_id: String(procesoInicial) } : vacio;
    });
    // Errores del formulario por campo, de la validación local o de un 422.
    const [erroresContrato, setErroresContrato] = useState({});
    const [guardandoContrato, setGuardandoContrato] = useState(false);
```

por:

```jsx
    const [formContrato, setFormContrato] = useState(() => {
        if (contrato) return formularioContrato(contrato);
        const vacio = contratoVacio(proyecto?.id);
        const inicial = procesoInicial ? { ...vacio, proceso_id: String(procesoInicial) } : vacio;
        return importacionSecop ? importarSecop(inicial, importacionSecop) : inicial;
    });
    // Un contrato nuevo importado de SECOP II empieza con los avisos y el mensaje de "Usar este".
    const importadoDeSecop = !contrato && Boolean(importacionSecop);
    // Errores del formulario por campo, de la validación local o de un 422.
    const [erroresContrato, setErroresContrato] = useState({});
    const [guardandoContrato, setGuardandoContrato] = useState(false);
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (5 de 6):

```jsx
    // resultado, diferencias, avisos }), los avisos del último resultado usado y el mensaje de la
    // última acción. Turno de "Actualizar desde SECOP II": una respuesta atrasada se descarta.
    const [dialogoSecop, setDialogoSecop] = useState(null);
    const [avisosDeSecop, setAvisosDeSecop] = useState([]);
    const [mensajeDeSecop, setMensajeDeSecop] = useState(null);
    const [consultandoSecop, setConsultandoSecop] = useState(false);
    const turnoSecop = useRef(0);
    // El formulario del último render, para comparar con SECOP II cuando llega la respuesta.
```

por:

```jsx
    // resultado, diferencias, avisos }), los avisos del último resultado usado y el mensaje de la
    // última acción. Turno de "Actualizar desde SECOP II": una respuesta atrasada se descarta.
    const [dialogoSecop, setDialogoSecop] = useState(null);
    const [avisosDeSecop, setAvisosDeSecop] = useState(() => (importadoDeSecop ? avisosSecop(importacionSecop, formContrato) : []));
    const [mensajeDeSecop, setMensajeDeSecop] = useState(importadoDeSecop ? MENSAJE_IMPORTADO_SECOP : null);
    const [consultandoSecop, setConsultandoSecop] = useState(false);
    const turnoSecop = useRef(0);
    // El formulario del último render, para comparar con SECOP II cuando llega la respuesta.
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (6 de 6):

```jsx
            if (!isConfirmed) return;
        }
        const importado = importarSecop(formularioActual.current, resultado);
        copiarAlFormulario(importado, avisosSecop(resultado, importado),
            'Formulario lleno con los datos de SECOP II: revísalos y guarda el contrato.');
        setDialogoSecop(null);
    };
```

por:

```jsx
            if (!isConfirmed) return;
        }
        const importado = importarSecop(formularioActual.current, resultado);
        copiarAlFormulario(importado, avisosSecop(resultado, importado), MENSAJE_IMPORTADO_SECOP);
        setDialogoSecop(null);
    };
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (1 de 7):

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
```

por:

```jsx
import React, { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import { Link, Navigate, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { faArrowLeft, faMagnifyingGlass, faPlus } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../../axios';
import {
    TEXTO_SECOP_VACIO, VISTA_LISTA, VISTA_SECOP, consultaDeFiltros, consultaVistaSecop, errorNit, esIdContrato, estadoImportarSecop,
    estadoSinSecop, filaContrato, filtrosDeConsulta, guardarNitEmpresa, leerNitEmpresa, nitDeConsulta, nitParaMostrar, normalizarNit,
    opcionesFiltroEstado, opcionesFiltroProyecto, parametrosBusqueda, puedeEditarContratos, resultadoSecopDeEstado, rutaContrato,
    rutaListaDesde, rutaVistaSecop, textoListaVacia, vistaDeConsulta,
} from '../../utils/contratosModulo';
import { RUTA_CONTRATOS } from '../../utils/navegacion';
import { mensajeApi } from '../../utils/plantillasChecklist';
import { mensajeSecop } from '../../utils/secop';
import ContratoEditor from './ContratoEditor';
import { ResultadosSecop } from './SecopContrato';
import '../../../css/Plantillas.css';
import '../../../css/Parametros.css';
import '../../../css/Contratacion.css';
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (2 de 7):

```jsx
    <Link to={a} className="pl-volver"><FontAwesomeIcon icon={faArrowLeft} /> Contratos</Link>
);

/** Lista de todos los contratos, con o sin proyecto. Los filtros viven en la URL. */
const ListaContratos = ({ edita, proyectos }) => {
    const { search } = useLocation();
```

por:

```jsx
    <Link to={a} className="pl-volver"><FontAwesomeIcon icon={faArrowLeft} /> Contratos</Link>
);

/** Las vistas de /contratos para quien puede editar: la lista y los contratos de la empresa en SECOP II. */
const PestanasVista = ({ vista }) => {
    const pestana = (propia) => ({
        className: `pl-pestana${vista === propia ? ' activa' : ''}`,
        'aria-current': vista === propia ? 'page' : undefined,
    });
    return (
        <nav className="pl-pestanas" aria-label="Vistas de contratos">
            <Link to={RUTA_CONTRATOS} {...pestana(VISTA_LISTA)}>En GestPro</Link>
            <Link to={rutaVistaSecop()} {...pestana(VISTA_SECOP)}>En SECOP II</Link>
        </nav>
    );
};

/** Lista de todos los contratos, con o sin proyecto. Los filtros viven en la URL. */
const ListaContratos = ({ edita, proyectos }) => {
    const { search } = useLocation();
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (3 de 7):

```jsx
                <p>Todos los contratos, con o sin proyecto: su estado, su valor vigente y su avance.</p>
            </header>

            <AvisoProyectos proyectos={proyectos} />

            <div className="pl-barra">
```

por:

```jsx
                <p>Todos los contratos, con o sin proyecto: su estado, su valor vigente y su avance.</p>
            </header>

            {edita && <PestanasVista vista={VISTA_LISTA} />}
            <AvisoProyectos proyectos={proyectos} />

            <div className="pl-barra">
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (4 de 7):

```jsx
    );
};

/** Un contrato nuevo, con proyecto opcional. Al guardarlo se abre, para seguir con sus pestañas. */
const NuevoContrato = ({ proyectos }) => {
    const { state } = useLocation();
    const navigate = useNavigate();
    const volver = rutaListaDesde(state);

    return (
        <div className="pl-pagina">
```

por:

```jsx
    );
};

/**
 * Los contratos de la empresa en SECOP II (`?vista=secop`): aquellos en que la empresa es el
 * proveedor (GET /secop/contratos-empresa). El NIT se recuerda en el navegador y el consultado va
 * en la URL, así que al volver de un contrato o de importar se consulta de nuevo. Solo vale la
 * última respuesta. "Importar" abre un contrato nuevo lleno con los datos del resultado.
 */
const ContratosSecop = () => {
    const { search } = useLocation();
    const navigate = useNavigate();
    const [consulta, setConsulta] = useSearchParams();
    const nitConsultado = nitDeConsulta(consulta);
    const [nit, setNit] = useState(() => nitConsultado || leerNitEmpresa());
    const [errorCampo, setErrorCampo] = useState(null);
    // Los resultados del NIT consultado: null sin consulta o mientras carga.
    const [consultado, setConsultado] = useState({ nit: '', resultados: null });
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const turno = useRef(0);
    const idAyuda = useId();
    const idError = useId();

    // Al salir de la vista, cualquier respuesta pendiente queda atrasada.
    useEffect(() => () => { turno.current += 1; }, []);

    const cargar = useCallback(async (nitBuscado) => {
        const numero = ++turno.current;
        setCargando(true);
        setError(null);
        setConsultado({ nit: nitBuscado, resultados: null });
        try {
            const { data } = await axios.get('/secop/contratos-empresa', { params: { nit: nitBuscado } });
            if (numero !== turno.current) return;
            setConsultado({ nit: nitBuscado, resultados: Array.isArray(data?.resultados) ? data.resultados : [] });
        } catch (falla) {
            if (numero !== turno.current) return;
            setError(mensajeSecop(falla));
        } finally {
            if (numero === turno.current) setCargando(false);
        }
    }, []);

    // El NIT de la URL manda: se consulta al llegar o al volver; sin NIT no hay resultados.
    useEffect(() => {
        if (nitConsultado) {
            setNit((actual) => (normalizarNit(actual) === nitConsultado ? actual : nitConsultado));
            setErrorCampo(null);
            cargar(nitConsultado);
            return;
        }
        turno.current += 1;
        setCargando(false);
        setError(null);
        setConsultado({ nit: '', resultados: null });
    }, [nitConsultado, cargar]);

    const consultar = (e) => {
        e.preventDefault();
        const falla = errorNit(nit);
        setErrorCampo(falla);
        if (falla) return;
        const normalizado = normalizarNit(nit);
        guardarNitEmpresa(normalizado);
        // Consultar no es una página nueva; el mismo NIT se consulta otra vez.
        if (normalizado === nitConsultado) cargar(normalizado);
        else setConsulta(consultaVistaSecop(normalizado), { replace: true });
    };

    // La vista (con su NIT) va en el estado: "Volver" y "Cancelar" regresan a ella.
    const importar = (resultado) => navigate(RUTA_NUEVO, { state: estadoImportarSecop(resultado, search) });
    const { resultados } = consultado;
    const listos = !cargando && !error && resultados !== null;

    return (
        <div className="pl-pagina">
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Trabajo diario</span>
                <h1>Contratos</h1>
                <p>Todos los contratos, con o sin proyecto: su estado, su valor vigente y su avance.</p>
            </header>

            <PestanasVista vista={VISTA_SECOP} />

            <section aria-labelledby="secop-empresa-titulo">
                <h2 id="secop-empresa-titulo" className="ct-secop-titulo">Contratos de la empresa en SECOP II</h2>
                <p className="ct-secop-nota">
                    Los contratos en que la empresa es el proveedor, según los datos abiertos de SECOP II. Importa los que aún no están en GestPro.
                </p>

                <form className="pl-barra ct-secop-empresa" onSubmit={consultar} noValidate aria-label="Consultar los contratos de la empresa en SECOP II">
                    <label className="pl-campo">
                        <span>NIT de la empresa</span>
                        <input
                            type="text"
                            value={nit}
                            onChange={(e) => { setNit(e.target.value); setErrorCampo(null); }}
                            maxLength={20}
                            inputMode="numeric"
                            placeholder="900.123.456-7"
                            aria-describedby={errorCampo ? `${idAyuda} ${idError}` : idAyuda}
                            aria-invalid={Boolean(errorCampo)}
                        />
                        <small id={idAyuda} className="contrato-nota">Se recuerda en este navegador.</small>
                    </label>
                    <button type="submit" className="gp-boton gp-boton-primario" disabled={cargando}>
                        <FontAwesomeIcon icon={faMagnifyingGlass} /> {cargando ? 'Consultando…' : 'Consultar'}
                    </button>
                </form>
                {errorCampo && <p id={idError} className="pl-error" role="alert">{errorCampo}</p>}

                {error && (
                    <p className="pl-error" role="alert">
                        {error} <button type="button" className="gp-boton" onClick={() => cargar(consultado.nit)}>Reintentar</button>
                    </p>
                )}
                <div aria-live="polite">
                    {cargando && <p className="gp-vacio" role="status">Consultando SECOP II…</p>}
                    {listos && resultados.length === 0 && <p className="gp-vacio">{TEXTO_SECOP_VACIO}</p>}
                    {listos && resultados.length > 0 && (
                        <>
                            <p className="ct-secop-nota ct-secop-conteo">
                                {resultados.length === 1 ? '1 contrato' : `${resultados.length} contratos`} con el NIT {nitParaMostrar(consultado.nit)} como proveedor.
                            </p>
                            <ResultadosSecop resultados={resultados} textoAccion="Importar" onElegir={importar} estadoEnlace={{ lista: search }} />
                        </>
                    )}
                </div>
            </section>
        </div>
    );
};

/**
 * Un contrato nuevo, con proyecto opcional. Si llega de "Importar" (con el resultado de SECOP II
 * en el estado de navegación), el formulario empieza lleno; sin estado (al abrir la dirección a
 * mano), vacío. Al guardarlo se abre, para seguir con sus pestañas.
 */
const NuevoContrato = ({ proyectos }) => {
    const { state, key } = useLocation();
    const navigate = useNavigate();
    const volver = rutaListaDesde(state);
    const importacion = resultadoSecopDeEstado(state);

    return (
        <div className="pl-pagina">
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (5 de 7):

```jsx
            <AvisoProyectos proyectos={proyectos} />
            <div className="gp-panel ct-editor">
                <ContratoEditor
                    proyectos={proyectos.lista}
                    recargar={recargarDetalle}
                    onGuardado={(guardado) => navigate(guardado?.id ? rutaContrato(guardado.id) : volver, { replace: true, state })}
                    onCancelar={() => navigate(volver)}
                />
            </div>
```

por:

```jsx
            <AvisoProyectos proyectos={proyectos} />
            <div className="gp-panel ct-editor">
                <ContratoEditor
                    key={key}
                    proyectos={proyectos.lista}
                    importacionSecop={importacion}
                    recargar={recargarDetalle}
                    onGuardado={(guardado) => navigate(guardado?.id ? rutaContrato(guardado.id) : volver, { replace: true, state: estadoSinSecop(state) })}
                    onCancelar={() => navigate(volver)}
                />
            </div>
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (6 de 7):

```jsx
};

/**
 * El módulo Contratos: la lista (`/contratos`), un contrato nuevo (`/contratos/nuevo`, solo quien
 * puede editar) y uno abierto (`/contratos/:contratoId`).
 */
const ContratosModulo = ({ user, nuevo = false }) => {
    const { contratoId } = useParams();
    const edita = puedeEditarContratos(user);
    const proyectos = useProyectos();
```

por:

```jsx
};

/**
 * El módulo Contratos: la lista (`/contratos`), los contratos de la empresa en SECOP II
 * (`/contratos?vista=secop`), un contrato nuevo (`/contratos/nuevo`; estos dos, solo quien puede
 * editar) y uno abierto (`/contratos/:contratoId`).
 */
const ContratosModulo = ({ user, nuevo = false }) => {
    const { contratoId } = useParams();
    const [consulta] = useSearchParams();
    const edita = puedeEditarContratos(user);
    const proyectos = useProyectos();
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (7 de 7):

```jsx
        }
        return <ContratoAbierto key={contratoId} contratoId={contratoId} edita={edita} proyectos={proyectos} />;
    }
    return <ListaContratos edita={edita} proyectos={proyectos} />;
};
```

por:

```jsx
        }
        return <ContratoAbierto key={contratoId} contratoId={contratoId} edita={edita} proyectos={proyectos} />;
    }
    if (vistaDeConsulta(consulta, edita) === VISTA_SECOP) return <ContratosSecop />;
    return <ListaContratos edita={edita} proyectos={proyectos} />;
};
```

En `resources/js/components/contratos/SecopContrato.jsx`, reemplazar (1 de 2):

```jsx

/**
 * Resultados de SECOP II (elementos de `resultados`): número, entidad, contratista, valor, fechas
 * y estado en SECOP II. Uno ya registrado enlaza a su contrato (con `enlaceEnOtraPestana`, en otra
 * pestaña, para no dejar el formulario que se está llenando); los demás llevan el botón
 * `textoAccion`, que llama a `onElegir(resultado)`.
 */
export const ResultadosSecop = ({ resultados, textoAccion = 'Usar este', onElegir, enlaceEnOtraPestana = false }) => (
    <ul className="ct-secop-resultados">
        {resultados.map((resultado) => {
            const fila = filaResultadoSecop(resultado);
```

por:

```jsx

/**
 * Resultados de SECOP II (elementos de `resultados`): número, entidad, contratista, valor, fechas
 * y estado en SECOP II. Uno ya registrado enlaza a su contrato (con `estadoEnlace` como estado de
 * navegación, si viene; con `enlaceEnOtraPestana`, en otra pestaña, para no dejar el formulario
 * que se está llenando); los demás llevan el botón `textoAccion`, que llama a `onElegir(resultado)`.
 */
export const ResultadosSecop = ({ resultados, textoAccion = 'Usar este', onElegir, estadoEnlace, enlaceEnOtraPestana = false }) => (
    <ul className="ct-secop-resultados">
        {resultados.map((resultado) => {
            const fila = filaResultadoSecop(resultado);
```

En `resources/js/components/contratos/SecopContrato.jsx`, reemplazar (2 de 2):

```jsx
                                        <span className="ct-oculto"> (se abre en otra pestaña)</span>
                                    </Link>
                                ) : (
                                    <Link to={fila.registrado.ruta} className="ct-enlace">contrato {fila.registrado.numero}</Link>
                                )}
                            </span>
                        ) : (
```

por:

```jsx
                                        <span className="ct-oculto"> (se abre en otra pestaña)</span>
                                    </Link>
                                ) : (
                                    <Link to={fila.registrado.ruta} state={estadoEnlace} className="ct-enlace">contrato {fila.registrado.numero}</Link>
                                )}
                            </span>
                        ) : (
```

En `resources/js/utils/contratosModulo.js`, reemplazar (1 de 2):

```js
// Lógica de presentación del módulo Contratos (/contratos): filtros en la URL, filas de la lista,
// opciones de los selectores de proyecto y rutas. Funciones puras, probadas en
// contratosModulo.test.js. Los filtros los valida también GET /contratos/buscar.

import { ESTADOS_CONTRATO, etiquetaEstadoContrato } from './contratos';
```

por:

```js
// Lógica de presentación del módulo Contratos (/contratos): filtros en la URL, filas de la lista,
// opciones de los selectores de proyecto, rutas y la vista de los contratos de la empresa en
// SECOP II (la vista en la URL, el NIT recordado y el estado de "Importar"). Funciones puras, probadas en
// contratosModulo.test.js. Los filtros los valida también GET /contratos/buscar.

import { ESTADOS_CONTRATO, etiquetaEstadoContrato } from './contratos';
```

En `resources/js/utils/contratosModulo.js`, reemplazar (2 de 2):

```js
    const lista = estado?.lista;
    return typeof lista === 'string' && lista.startsWith('?') ? `${RUTA_CONTRATOS}${lista}` : RUTA_CONTRATOS;
};
```

por:

```js
    const lista = estado?.lista;
    return typeof lista === 'string' && lista.startsWith('?') ? `${RUTA_CONTRATOS}${lista}` : RUTA_CONTRATOS;
};

// ---------- Contratos de la empresa en SECOP II ----------

/** Las vistas de /contratos: la lista y, para quien puede editar, la de SECOP II (`?vista=secop`). */
export const VISTA_LISTA = 'lista';
export const VISTA_SECOP = 'secop';

/** La vista de la URL. Un lector no tiene la de SECOP II (sus rutas piden editar-datos): ve la lista. */
export const vistaDeConsulta = (consulta, edita) => (
    edita && String(consulta?.get?.('vista') ?? '').trim() === VISTA_SECOP ? VISTA_SECOP : VISTA_LISTA
);

// La forma del NIT que acepta GET /secop/contratos-empresa (SecopII::FORMA_NIT, máx. 20).
const FORMA_NIT = /^[0-9][0-9. ]*(-[0-9])?$/;
const MAXIMO_NIT = 20;

export const esNitValido = (valor) => {
    const nit = String(valor ?? '').trim();
    return nit.length <= MAXIMO_NIT && FORMA_NIT.test(nit);
};

/** El error del campo NIT antes de consultar (los mensajes del servidor), o null si se puede consultar. */
export const errorNit = (valor) => {
    if (String(valor ?? '').trim() === '') return 'Escribe el NIT de la empresa.';
    return esNitValido(valor) ? null : 'Escribe el NIT solo con números (puede llevar puntos y el dígito de verificación con guion).';
};

/** El NIT sin puntos ni espacios, con el dígito de verificación si lo trae; vacío si no es válido. */
export const normalizarNit = (valor) => (esNitValido(valor) ? String(valor).trim().replace(/[. ]/g, '') : '');

/** El NIT como se lee: los miles con puntos y el dígito de verificación con guion ("900.123.456-7"). */
export const nitParaMostrar = (valor) => {
    const nit = normalizarNit(valor);
    if (!nit) return '';
    const [numero, verificacion] = nit.split('-');
    const agrupado = numero.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return verificacion === undefined ? agrupado : `${agrupado}-${verificacion}`;
};

/** El NIT consultado, en la URL (`?vista=secop&nit=`): así, al volver, se consulta de nuevo. */
export const nitDeConsulta = (consulta) => normalizarNit(consulta?.get?.('nit'));

export const consultaVistaSecop = (nit = '') => {
    const normalizado = normalizarNit(nit);
    return new URLSearchParams(normalizado ? { vista: VISTA_SECOP, nit: normalizado } : { vista: VISTA_SECOP });
};

export const rutaVistaSecop = () => `${RUTA_CONTRATOS}?${consultaVistaSecop()}`;

/** La clave del NIT de la empresa en el almacenamiento del navegador. */
export const CLAVE_NIT_EMPRESA = 'gestpro.secop.nitEmpresa';

// `localStorage` puede no existir o lanzar al tocarlo (bloqueado o en modo privado).
const almacenNavegador = () => globalThis.localStorage;

/** El NIT de la empresa recordado en este navegador, normalizado, o vacío (si no hay o no se puede leer). */
export const leerNitEmpresa = (almacen = undefined) => {
    try {
        const destino = almacen === undefined ? almacenNavegador() : almacen;
        return normalizarNit(destino?.getItem(CLAVE_NIT_EMPRESA));
    } catch {
        return '';
    }
};

/** Recuerda el NIT de la empresa (normalizado); uno no válido no se guarda. Sin almacenamiento no pasa nada. */
export const guardarNitEmpresa = (nit, almacen = undefined) => {
    const normalizado = normalizarNit(nit);
    if (!normalizado) return;
    try {
        const destino = almacen === undefined ? almacenNavegador() : almacen;
        destino?.setItem(CLAVE_NIT_EMPRESA, normalizado);
    } catch {
        // Solo es una comodidad: sin almacenamiento, el NIT se vuelve a escribir.
    }
};

export const TEXTO_SECOP_VACIO = 'SECOP II no tiene contratos con ese NIT como proveedor.';

/**
 * "Importar": el estado de navegación de /contratos/nuevo, con la lista de la que se viene (para
 * volver a ella) y el resultado de SECOP II con que se llena el formulario.
 */
export const estadoImportarSecop = (resultado, lista) => ({ lista, secop: resultado });

const esObjeto = (valor) => valor !== null && typeof valor === 'object' && !Array.isArray(valor);

/**
 * El resultado de SECOP II del estado de navegación, o null. Sin estado (al abrir la dirección a
 * mano) o con uno que no tiene la forma de un resultado, el formulario empieza vacío.
 */
export const resultadoSecopDeEstado = (estado) => {
    const resultado = estado?.secop;
    return esObjeto(resultado) && esObjeto(resultado.contrato) ? resultado : null;
};

/** El estado de navegación sin el resultado de SECOP II: lo que se lleva al contrato ya guardado. */
export const estadoSinSecop = (estado) => {
    if (!esObjeto(estado)) return {};
    const { secop, ...resto } = estado;
    return resto;
};
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/contratosModulo.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1119 passed.

Run: `npm test`
Expected: 640 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/css/Contratacion.css resources/js/components/contratos/ContratoEditor.jsx resources/js/components/contratos/ContratosModulo.jsx resources/js/components/contratos/SecopContrato.jsx resources/js/utils/contratosModulo.js resources/js/utils/contratosModulo.test.js
git commit -m "feat: the company's SECOP II contracts in the contracts module"
```

---

### Tarea 6: Ficha del proyecto

`GET /proyectos/{proyecto}/ficha` y la página `/proyectos/:id` con General, Formulación, Procesos y Contratos, enlazada desde Parámetros, el tablero y Ctrl+K.

**Files:**
- Create: `resources/css/FichaProyecto.css`
- Create: `resources/js/components/FichaProyecto.jsx`
- Create: `resources/js/utils/fichaProyecto.js`
- Test (create): `resources/js/utils/fichaProyecto.test.js`
- Test (create): `tests/Feature/ProyectoFichaApiTest.php`
- Modify: `app/Http/Controllers/ProyectoController.php`
- Modify: `app/Services/Viabilizacion/ExpedienteService.php`
- Modify: `resources/css/Tablero.css`
- Modify: `resources/js/app.jsx`
- Modify: `resources/js/components/ModalProyecto.jsx`
- Modify: `resources/js/components/Parametros.jsx`
- Modify: `resources/js/components/contratos/ContratosModulo.jsx`
- Modify: `resources/js/utils/contratosModulo.js`
- Test: `resources/js/utils/contratosModulo.test.js`
- Modify: `resources/js/utils/navegacion.js`
- Test: `resources/js/utils/navegacion.test.js`
- Modify: `routes/api.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `resources/js/utils/fichaProyecto.test.js`:

```js
import { describe, it, expect } from 'vitest';
import {
    datosGenerales, errorDeFicha, esIdProyecto, filaProceso, resumenFormulacion, textoFuentes, textoSector,
} from './fichaProyecto';

// Intl separa el símbolo con un espacio duro: se compara con espacios normales.
const normal = (texto) => texto.replace(/\s/g, ' ');

const proyecto = {
    id: 7,
    nombre: 'Acueducto veredal',
    descripcion: 'Construcción del acueducto',
    descripcion_municipio: 'BECERRIL',
    descripcion_fase: 'Ejecución',
    descripcion_estado: 'Activo',
    descripcion_entidad_presenta: 'Alcaldía',
    descripcion_entidad_financia: null,
    fecha_inicio: '2026-01-15',
    presupuesto: 5000000,
    totalPresupuesto: 3500000,
    sector: 2,
    fuente_ids: [1, 3],
    financiacion_por_definir: 0,
    fuente_financiacion: 'SGR y propios',
};
const fuentes = [{ id: 1, nombre: 'SGR' }, { id: 2, nombre: 'Crédito' }, { id: 3, nombre: 'Recursos propios' }];
const sectores = [{ id: 2, nombre: 'Agua potable' }];

describe('esIdProyecto', () => {
    it('acepta un entero positivo de hasta 18 cifras', () => {
        expect(esIdProyecto('7')).toBe(true);
        expect(esIdProyecto('123456789012345')).toBe(true);
    });

    it('rechaza lo que no es un id, sin llamar a la API', () => {
        ['', '0', '07', 'abc', '7a', '-1', '1.5', '99999999999999999999', undefined, null].forEach((valor) => {
            expect(esIdProyecto(valor)).toBe(false);
        });
    });
});

describe('errorDeFicha', () => {
    it('un 404 es un proyecto que no existe y no se reintenta', () => {
        expect(errorDeFicha({ response: { status: 404 } })).toEqual({ mensaje: 'El proyecto no existe.', reintentable: false });
    });

    it('los demás errores se pueden reintentar', () => {
        expect(errorDeFicha({ response: { status: 500 } })).toEqual({ mensaje: 'Error del servidor. Inténtalo de nuevo.', reintentable: true });
        expect(errorDeFicha({ request: {} })).toEqual({ mensaje: 'No hay conexión con el servidor.', reintentable: true });
    });
});

describe('textoFuentes', () => {
    it('nombra las fuentes del proyecto con el catálogo', () => {
        expect(textoFuentes(proyecto, fuentes)).toBe('SGR, Recursos propios');
    });

    it('por definir, sin fuentes o sin catálogo', () => {
        expect(textoFuentes({ ...proyecto, financiacion_por_definir: 1, fuente_ids: [] }, fuentes)).toBe('Por definir');
        expect(textoFuentes({ ...proyecto, fuente_ids: [] }, fuentes)).toBe('—');
        // Sin el catálogo (cargando o falló) se usa el texto que el proyecto ya tiene.
        expect(textoFuentes(proyecto, null)).toBe('SGR y propios');
        expect(textoFuentes({ ...proyecto, fuente_financiacion: '' }, null)).toBe('2 fuentes');
        expect(textoFuentes({ ...proyecto, fuente_ids: [1], fuente_financiacion: null }, null)).toBe('1 fuente');
    });

    it('una fuente que el catálogo no trae no se inventa', () => {
        expect(textoFuentes({ ...proyecto, fuente_ids: [1, 9] }, fuentes)).toBe('SGR, Fuente 9');
    });
});

describe('textoSector', () => {
    it('el nombre del sector del catálogo', () => {
        expect(textoSector(proyecto, sectores)).toBe('Agua potable');
        expect(textoSector({ ...proyecto, sector: '2' }, sectores)).toBe('Agua potable');
    });

    it('sin sector, sin catálogo o fuera de él, una raya', () => {
        expect(textoSector({ ...proyecto, sector: null }, sectores)).toBe('—');
        expect(textoSector(proyecto, null)).toBe('—');
        expect(textoSector({ ...proyecto, sector: 9 }, sectores)).toBe('—');
    });
});

describe('datosGenerales', () => {
    it('los datos del bloque General, en orden', () => {
        const datos = datosGenerales(proyecto, { fuentes, sectores });
        expect(datos.map((d) => d.etiqueta)).toEqual([
            'Municipio', 'Fase', 'Estado', 'Sector', 'Fuentes de financiación', 'Presupuesto', 'Fecha de inicio',
            'Entidad que presenta', 'Entidad que financia', 'Descripción',
        ]);
        const valor = (etiqueta) => datos.find((d) => d.etiqueta === etiqueta).valor;
        expect(valor('Municipio')).toBe('BECERRIL');
        expect(valor('Sector')).toBe('Agua potable');
        expect(valor('Fuentes de financiación')).toBe('SGR, Recursos propios');
        expect(normal(valor('Presupuesto'))).toBe('$ 3.500.000');
        expect(valor('Fecha de inicio')).toBe('15/01/2026');
        expect(valor('Entidad que financia')).toBe('—');
        expect(datos.find((d) => d.etiqueta === 'Descripción').ancho).toBe(true);
    });

    it('sin componentes, el presupuesto es el del proyecto', () => {
        const datos = datosGenerales({ ...proyecto, totalPresupuesto: 0 }, { fuentes, sectores });
        expect(normal(datos.find((d) => d.etiqueta === 'Presupuesto').valor)).toBe('$ 5.000.000');
    });

    it('sin datos, rayas', () => {
        const datos = datosGenerales({ id: 1, nombre: 'Vacío', fuente_ids: [] }, {});
        expect(datos.filter((d) => d.etiqueta !== 'Descripción').every((d) => d.valor === '—')).toBe(true);
    });
});

describe('resumenFormulacion', () => {
    const expediente = {
        proyecto_id: 7,
        cantidad_expedientes: 1,
        expediente_id: 12,
        estado: 'borrador',
        fase_sgr: 3,
        configurado: true,
        plantillas: ['SGR', 'Obras de agua'],
        tiene_sgr: true,
        fecha_radicado: null,
        actualizado: '2026-11-10T10:00:00-05:00',
        progreso: { total: 20, cumplidos: 9, porcentaje: 45 },
        responsables: [],
    };

    it('sin expediente', () => {
        expect(resumenFormulacion(null, 7)).toEqual({ tiene: false, texto: 'Sin expediente' });
    });

    it('un expediente en borrador: su avance propio y el enlace', () => {
        expect(resumenFormulacion(expediente, 7)).toEqual({
            tiene: true,
            estado: 'Borrador',
            porcentaje: 45,
            avance: '45% (9 de 20 requisitos cumplidos)',
            plantillas: 'SGR, Obras de agua',
            faseSgr: 'III — Factibilidad',
            radicado: null,
            otros: null,
            ruta: '/viabilizacion/7',
        });
    });

    it('sin configurar no hay avance que medir', () => {
        const r = resumenFormulacion({ ...expediente, configurado: false }, 7);
        expect(r.estado).toBe('Sin configurar');
        expect(r.porcentaje).toBeNull();
        expect(r.avance).toBe('Sin configurar: aún no hay requisitos que medir');
    });

    it('radicado, sin plantilla SGR y con varios expedientes', () => {
        const r = resumenFormulacion({
            ...expediente, estado: 'radicado', fecha_radicado: '2026-10-01T09:00:00-05:00', tiene_sgr: false, plantillas: [], cantidad_expedientes: 3,
        }, 7);
        expect(r.estado).toBe('Radicado');
        expect(r.radicado).toBe('01/10/2026');
        expect(r.faseSgr).toBeNull();
        expect(r.plantillas).toBe('—');
        expect(r.otros).toBe('El proyecto tiene 3 expedientes: se muestra el más reciente.');
    });
});

describe('filaProceso', () => {
    const proceso = {
        id: 4,
        objeto: 'Obra del acueducto',
        modalidad: { id: 1, nombre: 'Licitación pública', clave: 'licitacion_publica', activo: true },
        codigo_proceso: 'LP-001-2026',
        url_proceso: 'https://community.secop.gov.co/x',
        monto: '1500000.00',
        estado: 'adjudicado',
        contratos: 1,
    };

    it('una fila de la lista de procesos', () => {
        const fila = filaProceso(proceso);
        expect(fila).toMatchObject({
            id: 4,
            numero: 'LP-001-2026',
            enlace: 'https://community.secop.gov.co/x',
            objeto: 'Obra del acueducto',
            modalidad: 'Licitación pública',
            estado: 'Adjudicado',
            tono: 'verde',
            contratos: '1 contrato',
        });
        expect(normal(fila.monto)).toBe('$ 1.500.000');
    });

    it('sin número, modalidad ni monto', () => {
        const fila = filaProceso({ id: 5, objeto: '', modalidad: null, codigo_proceso: null, url_proceso: null, monto: null, estado: 'preparacion', contratos: 0 });
        expect(fila).toMatchObject({ numero: '—', enlace: null, objeto: '—', modalidad: '—', monto: '—', estado: 'En preparación', contratos: 'Sin contratos' });
    });
});
```

Crear `tests/Feature/ProyectoFichaApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Contrato;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\Municipio;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\TipoProceso;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Database\Seeders\DepartamentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Ficha del proyecto: sus datos generales, su expediente, sus procesos y sus contratos. */
class ProyectoFichaApiTest extends TestCase
{
    use RefreshDatabase;

    private Proyecto $proyecto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '20045', 'nombre' => 'BECERRIL', 'activo' => true, 'departamento' => '20'])->codigo;
        $fase = DB::table('fases')->insertGetId(['nombre' => 'Ejecución', 'color' => '#00aa00', 'dashboard' => 1]);
        $estado = DB::table('estados')->insertGetId(['nombre' => 'Activo', 'color' => '#0000aa']);
        $this->proyecto = Proyecto::create([
            'nombre' => 'Acueducto veredal',
            'descripcion' => 'Construcción del acueducto',
            'municipio' => $municipio,
            'fase' => $fase,
            'estado' => $estado,
            'presupuesto' => 5000000,
            'fecha_inicio' => '2026-01-15',
        ]);
        $this->proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        DB::table('presupuesto_proyecto')->insert([
            ['proyecto' => $this->proyecto->id, 'componente' => 'Obra', 'valor' => 3000000],
            ['proyecto' => $this->proyecto->id, 'componente' => 'Interventoría', 'valor' => 500000],
        ]);
        DB::table('proyecto_ubicaciones')->insert(['proyecto' => $this->proyecto->id, 'lat' => 9.7, 'lng' => -73.28]);
    }

    private function como(Rol $rol = Rol::Consulta): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function url(int|string|null $id = null): string
    {
        return '/GestPro/proyectos/' . ($id ?? $this->proyecto->id) . '/ficha';
    }

    private function proceso(array $mas = [], ?Proyecto $proyecto = null): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => ($proyecto ?? $this->proyecto)->id,
            'objeto' => 'Obra del acueducto',
            'modalidad' => Modalidad::where('clave', 'licitacion_publica')->firstOrFail()->id,
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-001-2026',
            'estado' => 'adjudicado',
            ...$mas,
        ]);
    }

    private function contrato(array $mas = [], ?Proyecto $proyecto = null): Contrato
    {
        return Contrato::create([
            'proyecto' => ($proyecto ?? $this->proyecto)->id,
            'n_contrato' => 'C-001-2026',
            'objeto' => 'Construcción del acueducto veredal',
            'valor_inicial' => 1000000,
            'valor' => 1000000,
            'estado' => 'en_ejecucion',
            ...$mas,
        ]);
    }

    private function expediente(Proyecto $proyecto): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        app(ExpedienteService::class)->crear($proyecto, ['fase_sgr' => 3], null);
    }

    // ------------------------------------------------------------------ forma

    public function test_la_ficha_trae_proyecto_expediente_procesos_y_contratos(): void
    {
        $ficha = $this->como()->getJson($this->url())->assertOk()->json();

        $this->assertSame(['proyecto', 'expediente', 'procesos', 'contratos'], array_keys($ficha));
    }

    public function test_el_proyecto_es_la_fila_de_get_proyectos_sin_sus_contratos(): void
    {
        $this->contrato();
        Proyecto::create(['nombre' => 'Otro proyecto']);

        $fila = collect($this->como()->getJson('/GestPro/proyectos')->assertOk()->json())->firstWhere('id', $this->proyecto->id);
        $proyecto = $this->como()->getJson($this->url())->assertOk()->json('proyecto');

        $this->assertCount(1, $fila['contratos']);
        unset($fila['contratos']);
        $this->assertSame(array_keys($fila), array_keys($proyecto));
        $this->assertEquals($fila, $proyecto);
        $this->assertSame('Acueducto veredal', $proyecto['nombre']);
        $this->assertSame('BECERRIL', $proyecto['descripcion_municipio']);
        $this->assertSame('Ejecución', $proyecto['descripcion_fase']);
        $this->assertSame('Activo', $proyecto['descripcion_estado']);
        $this->assertCount(1, $proyecto['fuente_ids']);
        $this->assertCount(2, $proyecto['componentesPresupuesto']);
        $this->assertEquals(3500000, $proyecto['totalPresupuesto']);
        $this->assertCount(1, $proyecto['puntosUbicacion']);
    }

    public function test_un_proyecto_sin_datos_opcionales_trae_listas_vacias(): void
    {
        $vacio = Proyecto::create(['nombre' => 'Sin nada']);

        $ficha = $this->como()->getJson($this->url($vacio->id))->assertOk()->json();

        $this->assertSame([], $ficha['proyecto']['fuente_ids']);
        $this->assertSame([], $ficha['proyecto']['componentesPresupuesto']);
        $this->assertEquals(0, $ficha['proyecto']['totalPresupuesto']);
        $this->assertSame([], $ficha['proyecto']['puntosUbicacion']);
        $this->assertNull($ficha['proyecto']['descripcion_municipio']);
        $this->assertNull($ficha['expediente']);
        $this->assertSame([], $ficha['procesos']);
        $this->assertSame([], $ficha['contratos']);
    }

    // ------------------------------------------------------------------ expediente

    public function test_el_expediente_es_el_resumen_de_get_expedientes_de_ese_proyecto(): void
    {
        $otro = Proyecto::create(['nombre' => 'Otro proyecto']);
        $this->expediente($otro);
        $this->expediente($this->proyecto);

        $resumen = collect($this->como()->getJson('/GestPro/expedientes')->assertOk()->json())->firstWhere('proyecto_id', $this->proyecto->id);
        $expediente = $this->como()->getJson($this->url())->assertOk()->json('expediente');

        $this->assertNotNull($resumen);
        $this->assertSame($resumen, $expediente);
        $this->assertSame(['total', 'cumplidos', 'porcentaje'], array_keys($expediente['progreso']));
    }

    public function test_sin_expediente_es_null(): void
    {
        $this->expediente(Proyecto::create(['nombre' => 'Otro proyecto']));

        $this->assertNull($this->como()->getJson($this->url())->assertOk()->json('expediente'));
    }

    public function test_get_expedientes_sigue_trayendo_todos_los_proyectos(): void
    {
        $this->expediente(Proyecto::create(['nombre' => 'Otro proyecto']));
        $this->expediente($this->proyecto);

        $this->como()->getJson('/GestPro/expedientes')->assertOk()->assertJsonCount(2);
    }

    // ------------------------------------------------------------------ procesos y contratos

    public function test_los_procesos_son_los_de_get_procesos_del_proyecto(): void
    {
        $proceso = $this->proceso();
        $this->contrato(['proceso_licitacion' => $proceso->id]);
        $this->proceso(['codigo_proceso' => 'MC-002-2026', 'estado' => 'preparacion']);
        $this->proceso(['codigo_proceso' => 'AJENO'], Proyecto::create(['nombre' => 'Otro proyecto']));

        $esperados = $this->como()->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk()->json('procesos');
        $procesos = $this->como()->getJson($this->url())->assertOk()->json('procesos');

        $this->assertCount(2, $procesos);
        $this->assertSame($esperados, $procesos);
        $this->assertSame(1, $procesos[0]['contratos']);
    }

    public function test_los_contratos_son_los_de_buscar_con_ese_proyecto(): void
    {
        $this->contrato(['avance_fisico' => 40, 'avance_financiero' => 30]);
        $this->contrato(['n_contrato' => 'C-002-2026', 'proceso_licitacion' => $this->proceso()->id]);
        $this->contrato(['n_contrato' => 'AJENO'], Proyecto::create(['nombre' => 'Otro proyecto']));
        $this->contrato(['n_contrato' => 'SIN PROYECTO', 'proyecto' => null]);

        $esperados = $this->como()->getJson('/GestPro/contratos/buscar?proyecto=' . $this->proyecto->id)->assertOk()->json();
        $contratos = $this->como()->getJson($this->url())->assertOk()->json('contratos');

        $this->assertSame($esperados, $contratos);
        $this->assertSame(['C-002-2026', 'C-001-2026'], array_column($contratos, 'n_contrato'));
        $this->assertSame(40, $contratos[1]['avance_fisico']);
    }

    public function test_la_ficha_no_consulta_por_cada_proceso_contrato_o_expediente(): void
    {
        $consultas = function (): int {
            $peticion = $this->como();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson($this->url())->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $this->expediente($this->proyecto);
        $this->contrato(['proceso_licitacion' => $this->proceso()->id]);
        $conUno = $consultas();

        foreach (['minima_cuantia', 'seleccion_abreviada', 'contratacion_directa'] as $i => $clave) {
            $proceso = $this->proceso(['modalidad' => Modalidad::where('clave', $clave)->firstOrFail()->id, 'codigo_proceso' => "P-{$i}"]);
            $tipo = TipoProceso::create(['nombre' => "Tipo {$i}", 'activo' => true]);
            $this->contrato(['n_contrato' => "C-{$i}", 'proceso_licitacion' => $proceso->id, 'tipo_contrato_id' => $tipo->id]);
        }

        $this->assertSame($conUno, $consultas());
    }

    // ------------------------------------------------------------------ errores, permisos y rutas

    public function test_un_proyecto_inexistente_responde_404_con_mensaje(): void
    {
        $this->como()->getJson($this->url(9999))->assertNotFound()->assertExactJson(['mensaje' => 'El proyecto no existe.']);
    }

    public function test_un_id_fuera_de_rango_responde_404_con_mensaje(): void
    {
        foreach (['99999999999999999999', '9223372036854775808'] as $id) {
            $this->como()->getJson($this->url($id))->assertNotFound()->assertExactJson(['mensaje' => 'El proyecto no existe.']);
        }
    }

    public function test_un_id_que_no_es_numero_no_llega_a_la_ficha(): void
    {
        $uri = fn (string $url) => Route::getRoutes()->match(Request::create($url))->uri();

        $this->assertSame('GestPro/proyectos/{proyecto}/ficha', $uri($this->url()));
        $this->assertNotSame('GestPro/proyectos/{proyecto}/ficha', $uri($this->url('abc')));
    }

    public function test_exige_sesion_y_la_lee_cualquier_rol(): void
    {
        $this->getJson($this->url())->assertUnauthorized();
        foreach ([Rol::Consulta, Rol::Gestor, Rol::Administrador] as $rol) {
            $this->como($rol)->getJson($this->url())->assertOk();
        }
    }

    public function test_la_ruta_del_spa_no_es_una_ruta_get_de_la_api(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => in_array('GET', $ruta->methods(), true))
            ->map(fn ($ruta) => $ruta->uri())
            ->all();

        $this->assertNotContains('GestPro/proyectos/{proyecto}', $uris);
        $this->assertNotContains('GestPro/proyectos/{id}', $uris);
        $this->assertContains('GestPro/proyectos/{proyecto}/ficha', $uris);
    }
}
```

En `resources/js/utils/contratosModulo.test.js`, reemplazar (1 de 3):

```js
    SIN_PROYECTO, consultaDeFiltros, esIdContrato, filaContrato, filtrosDeConsulta, hayFiltros, opcionesFiltroEstado, opcionesFiltroProyecto,
    opcionesProyecto, parametrosBusqueda, puedeEditarContratos, rutaContrato, rutaListaDesde, textoAvance, textoListaVacia,
    tonoEstadoContrato, CLAVE_NIT_EMPRESA, TEXTO_SECOP_VACIO, VISTA_LISTA, VISTA_SECOP, consultaVistaSecop, esNitValido,
    errorNit, estadoImportarSecop, estadoSinSecop, guardarNitEmpresa, leerNitEmpresa, nitDeConsulta, nitParaMostrar, normalizarNit,
    resultadoSecopDeEstado, rutaVistaSecop, vistaDeConsulta,
} from './contratosModulo';
import { ESTADOS_CONTRATO } from './contratos';
import { RUTA_CONTRATOS } from './navegacion';
```

por:

```js
    SIN_PROYECTO, consultaDeFiltros, esIdContrato, filaContrato, filtrosDeConsulta, hayFiltros, opcionesFiltroEstado, opcionesFiltroProyecto,
    opcionesProyecto, parametrosBusqueda, puedeEditarContratos, rutaContrato, rutaListaDesde, textoAvance, textoListaVacia,
    tonoEstadoContrato, CLAVE_NIT_EMPRESA, TEXTO_SECOP_VACIO, VISTA_LISTA, VISTA_SECOP, consultaVistaSecop, esNitValido,
    errorNit, estadoDesdeFicha, estadoImportarSecop, estadoNuevoContratoDeProyecto, textoVolverDesde, estadoSinSecop, guardarNitEmpresa, leerNitEmpresa, nitDeConsulta, nitParaMostrar, normalizarNit,
    proyectoDeEstado, resultadoSecopDeEstado, rutaVistaSecop, vistaDeConsulta,
} from './contratosModulo';
import { ESTADOS_CONTRATO } from './contratos';
import { RUTA_CONTRATOS } from './navegacion';
```

En `resources/js/utils/contratosModulo.test.js`, reemplazar (2 de 3):

```js
        expect(rutaListaDesde({ lista: 'https://otro' })).toBe('/contratos');
        expect(rutaListaDesde(null)).toBe('/contratos');
    });
});

describe('esIdContrato', () => {
```

por:

```js
        expect(rutaListaDesde({ lista: 'https://otro' })).toBe('/contratos');
        expect(rutaListaDesde(null)).toBe('/contratos');
    });

    it('desde la ficha de un proyecto, se vuelve a la ficha', () => {
        expect(estadoDesdeFicha(7)).toEqual({ ficha: 7 });
        expect(rutaListaDesde(estadoDesdeFicha(7))).toBe('/proyectos/7');
        expect(rutaListaDesde({ ficha: '12' })).toBe('/proyectos/12');
        expect(textoVolverDesde(estadoDesdeFicha(7))).toBe('Ficha del proyecto');
        expect(textoVolverDesde({ lista: '?q=x' })).toBe('Contratos');
        expect(textoVolverDesde(null)).toBe('Contratos');
    });

    it('una ficha que no es un id válido vuelve a la lista', () => {
        ['abc', 0, -1, '7/../x', null, { id: 7 }].forEach((ficha) => {
            expect(rutaListaDesde({ ficha }), String(ficha)).toBe('/contratos');
            expect(textoVolverDesde({ ficha }), String(ficha)).toBe('Contratos');
        });
        // Si trae las dos, gana la ficha (no se llega a la ficha desde la lista con filtros).
        expect(rutaListaDesde({ ficha: 3, lista: '?q=x' })).toBe('/proyectos/3');
    });
});

describe('esIdContrato', () => {
```

En `resources/js/utils/contratosModulo.test.js`, reemplazar (3 de 3):

```js
        expect(TEXTO_SECOP_VACIO).toBe('SECOP II no tiene contratos con ese NIT como proveedor.');
    });
});
```

por:

```js
        expect(TEXTO_SECOP_VACIO).toBe('SECOP II no tiene contratos con ese NIT como proveedor.');
    });
});

describe('contrato nuevo de un proyecto', () => {
    it('la ficha lleva el proyecto en el estado de navegación, y de dónde se viene', () => {
        expect(estadoNuevoContratoDeProyecto({ id: 7, nombre: 'Acueducto', fuente_ids: [1] })).toEqual({ proyecto: { id: 7, nombre: 'Acueducto' }, ficha: 7 });
        expect(rutaListaDesde(estadoNuevoContratoDeProyecto({ id: 7, nombre: 'Acueducto' }))).toBe('/proyectos/7');
        // Al guardar, el contrato abierto conserva el origen: "Volver" sigue yendo a la ficha.
        expect(rutaListaDesde(estadoSinSecop(estadoNuevoContratoDeProyecto({ id: 7, nombre: 'Acueducto' })))).toBe('/proyectos/7');
    });

    it('el editor recibe el proyecto del estado', () => {
        expect(proyectoDeEstado({ proyecto: { id: 7, nombre: 'Acueducto' } })).toEqual({ id: 7, nombre: 'Acueducto' });
        expect(proyectoDeEstado({ proyecto: { id: '7' } })).toEqual({ id: '7', nombre: '' });
        expect(proyectoDeEstado(estadoNuevoContratoDeProyecto({ id: 3, nombre: 'Vía' }))).toEqual({ id: 3, nombre: 'Vía' });
    });

    it('sin estado o con uno sin un id válido, sin proyecto', () => {
        expect(proyectoDeEstado(undefined)).toBeNull();
        expect(proyectoDeEstado({})).toBeNull();
        expect(proyectoDeEstado({ lista: '?q=x' })).toBeNull();
        expect(proyectoDeEstado({ proyecto: { id: 'abc' } })).toBeNull();
        expect(proyectoDeEstado({ proyecto: 7 })).toBeNull();
        expect(proyectoDeEstado({ proyecto: { id: 0 } })).toBeNull();
    });
});
```

En `resources/js/utils/navegacion.test.js`, reemplazar (1 de 3):

```js
import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import { DESTINOS, GRUPO, RUTA_CONTRATOS, RUTA_PLANTILLAS, SECCIONES_PARAMETROS, buscarEnPaleta, destinoActivo, destinosPara, puedeVer, seccionDesdeSlug, tituloDeRuta } from './navegacion';
import { ICONOS } from './iconosNavegacion';

const proyectos = [
```

por:

```js
import { readFileSync } from 'node:fs';
import { describe, it, expect } from 'vitest';
import { DESTINOS, GRUPO, RUTA_CONTRATOS, RUTA_PLANTILLAS, SECCIONES_PARAMETROS, buscarEnPaleta, destinoActivo, destinosPara, puedeVer, rutaFichaProyecto, seccionDesdeSlug, tituloDeRuta } from './navegacion';
import { ICONOS } from './iconosNavegacion';

const proyectos = [
```

En `resources/js/utils/navegacion.test.js`, reemplazar (2 de 3):

```js

    it('encuentra proyectos por nombre o municipio y los deja después de los destinos', () => {
        const porNombre = buscarEnPaleta('construccion', DESTINOS, proyectos);
        expect(porNombre.at(-1)).toMatchObject({ tipo: 'proyecto', titulo: 'Construcción sede educativa', ruta: '/dashboard?proyecto=1' });

        expect(buscarEnPaleta('bello', DESTINOS, proyectos).map((r) => r.titulo)).toEqual(['Acueducto veredal']);
    });
```

por:

```js

    it('encuentra proyectos por nombre o municipio y los deja después de los destinos', () => {
        const porNombre = buscarEnPaleta('construccion', DESTINOS, proyectos);
        expect(porNombre.at(-1)).toMatchObject({ tipo: 'proyecto', titulo: 'Construcción sede educativa', ruta: '/proyectos/1' });

        expect(buscarEnPaleta('bello', DESTINOS, proyectos).map((r) => r.titulo)).toEqual(['Acueducto veredal']);
    });
```

En `resources/js/utils/navegacion.test.js`, reemplazar (3 de 3):

```js
    it('un expediente pertenece a Expedientes', () => {
        expect(destinoActivo('/viabilizacion/4', '')).toBe('expedientes');
    });
});

describe('tituloDeRuta', () => {
```

por:

```js
    it('un expediente pertenece a Expedientes', () => {
        expect(destinoActivo('/viabilizacion/4', '')).toBe('expedientes');
    });

    it('la ficha de un proyecto pertenece a Proyectos', () => {
        expect(destinoActivo('/proyectos/4', '')).toBe('proyectos');
        expect(tituloDeRuta('/proyectos/4', '')).toBe('Proyectos');
        // GET /proyectos es de la API: el SPA no tiene /proyectos sin id.
        expect(destinoActivo('/proyectos', '')).toBeNull();
        expect(destinoActivo('/proyectosx/4', '')).toBeNull();
    });
});

describe('rutaFichaProyecto', () => {
    it('la ficha del proyecto, nunca /proyectos sin id', () => {
        expect(rutaFichaProyecto(7)).toBe('/proyectos/7');
        expect(rutaFichaProyecto('12')).toBe('/proyectos/12');
    });
});

describe('tituloDeRuta', () => {
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/ProyectoFichaApiTest.php`
Expected: FALLAN 12 y pasan 2: la ruta `GET /proyectos/{proyecto}/ficha` no existe.

Run: `npx vitest run resources/js/utils/fichaProyecto.test.js resources/js/utils/contratosModulo.test.js resources/js/utils/navegacion.test.js`
Expected: FALLAN los 3 archivos (8 pruebas y la carga de `fichaProyecto.test.js`): no existen las utilidades de la ficha, el regreso a la ficha desde un contrato ni la ruta de la ficha en la paleta.

- [ ] **Paso 3: Implementar**

Crear `resources/css/FichaProyecto.css`:

```css
/* Ficha del proyecto (/proyectos/:id): bloques General, Formulación, Procesos y Contratos. */
.fp-bloques { display: grid; gap: 1rem; }
.fp-bloque { min-width: 0; padding: 1rem 1.1rem; }
.fp-bloque-cabecera { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; justify-content: space-between; margin-bottom: .8rem; }
.fp-bloque-cabecera h2 { font-size: 1.05rem; font-weight: 700; }
.fp-acciones { display: flex; flex-wrap: wrap; gap: .5rem; }
.fp-datos { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: .85rem 1.25rem; margin: 0; }
.fp-dato { min-width: 0; }
.fp-dato.ancho { grid-column: 1 / -1; }
.fp-dato dd { margin: .1rem 0 0; font-size: .93rem; overflow-wrap: anywhere; }
.fp-progreso { display: block; height: .4rem; margin: .25rem 0 .3rem; overflow: hidden; background: var(--gp-superficie-3); border-radius: 999px; }
.fp-progreso span { display: block; height: 100%; background: var(--gp-verde-texto); border-radius: inherit; }
.fp-nota { margin-top: .8rem; font-size: .85rem; color: var(--gp-texto-3); }
.fp-bloque .gp-vacio { padding: .75rem; }
```

Crear `resources/js/components/FichaProyecto.jsx`:

```jsx
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { faArrowLeft, faArrowUpRightFromSquare, faClipboardCheck, faGavel, faPlus } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { datosGenerales, errorDeFicha, esIdProyecto, filaProceso, resumenFormulacion } from '../utils/fichaProyecto';
import { estadoDesdeFicha, estadoNuevoContratoDeProyecto, filaContrato, puedeEditarContratos, rutaContrato } from '../utils/contratosModulo';
import { RUTA_CONTRATOS } from '../utils/navegacion';
import ProcesosProyecto from './ProcesosProyecto';
import '../../css/Plantillas.css';
import '../../css/Parametros.css';
import '../../css/Contratacion.css';
import '../../css/FichaProyecto.css';

const RUTA_PROYECTOS = '/parametros/proyectos';

const Volver = () => (
    <Link to={RUTA_PROYECTOS} className="pl-volver"><FontAwesomeIcon icon={faArrowLeft} /> Proyectos</Link>
);

/** Un catálogo para mostrar nombres (fuentes, sectores): null mientras carga o si falla. */
const useCatalogo = (ruta) => {
    const [lista, setLista] = useState(null);
    useEffect(() => {
        let vigente = true;
        axios.get(ruta)
            .then(({ data }) => { if (vigente) setLista(Array.isArray(data) ? data : []); })
            .catch(() => {});
        return () => { vigente = false; };
    }, [ruta]);
    return lista;
};

const Bloque = ({ titulo, acciones, children }) => (
    <section className="gp-panel fp-bloque" aria-label={titulo}>
        <header className="fp-bloque-cabecera">
            <h2>{titulo}</h2>
            {acciones && <div className="fp-acciones">{acciones}</div>}
        </header>
        {children}
    </section>
);

const General = ({ proyecto, fuentes, sectores }) => (
    <Bloque titulo="General">
        <dl className="fp-datos">
            {datosGenerales(proyecto, { fuentes, sectores }).map((d) => (
                <div key={d.etiqueta} className={`fp-dato${d.ancho ? ' ancho' : ''}`}>
                    <dt className="gp-etiqueta">{d.etiqueta}</dt>
                    <dd>{d.valor}</dd>
                </div>
            ))}
        </dl>
    </Bloque>
);

const Formulacion = ({ proyectoId, expediente }) => {
    const resumen = resumenFormulacion(expediente, proyectoId);
    if (!resumen.tiene) {
        return <Bloque titulo="Formulación"><p className="gp-vacio">{resumen.texto}</p></Bloque>;
    }
    return (
        <Bloque
            titulo="Formulación"
            acciones={(
                <Link to={resumen.ruta} className="gp-boton">
                    <FontAwesomeIcon icon={faClipboardCheck} /> Abrir expediente
                </Link>
            )}
        >
            <dl className="fp-datos">
                <div className="fp-dato"><dt className="gp-etiqueta">Estado</dt><dd>{resumen.estado}</dd></div>
                <div className="fp-dato">
                    <dt className="gp-etiqueta">Avance del expediente</dt>
                    <dd>
                        {resumen.porcentaje !== null && (
                            <span className="fp-progreso" aria-hidden="true"><span style={{ width: `${resumen.porcentaje}%` }} /></span>
                        )}
                        {resumen.avance}
                    </dd>
                </div>
                <div className="fp-dato"><dt className="gp-etiqueta">Plantillas</dt><dd>{resumen.plantillas}</dd></div>
                {resumen.faseSgr && <div className="fp-dato"><dt className="gp-etiqueta">Fase SGR</dt><dd>{resumen.faseSgr}</dd></div>}
                {resumen.radicado && <div className="fp-dato"><dt className="gp-etiqueta">Radicado</dt><dd>{resumen.radicado}</dd></div>}
            </dl>
            {resumen.otros && <p className="fp-nota">{resumen.otros}</p>}
        </Bloque>
    );
};

const Procesos = ({ procesos, onAbrir }) => {
    const filas = procesos.map(filaProceso);
    return (
        <Bloque
            titulo="Procesos de contratación"
            acciones={<button type="button" className="gp-boton" onClick={onAbrir}><FontAwesomeIcon icon={faGavel} /> Procesos</button>}
        >
            {filas.length === 0 ? <p className="gp-vacio">El proyecto no tiene procesos de contratación.</p> : (
                <div className="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Objeto</th>
                                <th>Modalidad</th>
                                <th>Estado</th>
                                <th>Valor</th>
                                <th>Contratos</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filas.map((f) => (
                                <tr key={f.id}>
                                    <td data-etiqueta="Número">
                                        {f.enlace ? (
                                            <a href={f.enlace} target="_blank" rel="noopener noreferrer" className="ct-enlace">
                                                {f.numero} <FontAwesomeIcon icon={faArrowUpRightFromSquare} />
                                            </a>
                                        ) : f.numero}
                                    </td>
                                    <td data-etiqueta="Objeto"><span className="ct-objeto" title={f.objeto}>{f.objeto}</span></td>
                                    <td data-etiqueta="Modalidad">{f.modalidad}</td>
                                    <td data-etiqueta="Estado"><span className={`ct-estado ct-estado-${f.tono}`}>{f.estado}</span></td>
                                    <td data-etiqueta="Valor" className="numero">{f.monto}</td>
                                    <td data-etiqueta="Contratos">{f.contratos}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Bloque>
    );
};

const Contratos = ({ proyecto, contratos, edita }) => {
    const filas = contratos.map(filaContrato);
    return (
        <Bloque
            titulo="Contratos"
            acciones={edita && (
                <Link to={`${RUTA_CONTRATOS}/nuevo`} state={estadoNuevoContratoDeProyecto(proyecto)} className="gp-boton gp-boton-primario">
                    <FontAwesomeIcon icon={faPlus} /> Nuevo contrato
                </Link>
            )}
        >
            {filas.length === 0 ? <p className="gp-vacio">El proyecto no tiene contratos.</p> : (
                <div className="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Objeto</th>
                                <th>Estado</th>
                                <th>Valor vigente</th>
                                <th>Físico</th>
                                <th>Financiero</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filas.map((f) => (
                                <tr key={f.id}>
                                    <td data-etiqueta="Número"><Link to={rutaContrato(f.id)} state={estadoDesdeFicha(proyecto.id)} className="ct-enlace">{f.numero}</Link></td>
                                    <td data-etiqueta="Objeto"><span className="ct-objeto" title={f.objeto}>{f.objeto}</span></td>
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
        </Bloque>
    );
};

/** La ficha de un proyecto con id válido. Solo vale la respuesta de la última petición. */
const Ficha = ({ proyectoId, user }) => {
    const [ficha, setFicha] = useState(null);
    // { mensaje, reintentable }: un proyecto que no existe no se reintenta.
    const [error, setError] = useState(null);
    const [procesosAbiertos, setProcesosAbiertos] = useState(false);
    const peticion = useRef(0);
    const fuentes = useCatalogo('/fuentesFinanciacion');
    const sectores = useCatalogo('/sectores');
    const edita = puedeEditarContratos(user);

    const cargar = useCallback(async () => {
        const numero = ++peticion.current;
        setError(null);
        try {
            const { data } = await axios.get(`/proyectos/${proyectoId}/ficha`);
            if (numero === peticion.current) setFicha(data);
        } catch (e) {
            if (numero === peticion.current) setError(errorDeFicha(e));
        }
    }, [proyectoId]);
    useEffect(() => {
        cargar();
        return () => { peticion.current += 1; };
    }, [cargar]);

    const reintentar = error?.reintentable && <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>;
    // Con la ficha ya pintada, un fallo al recargarla no la quita: se avisa encima.
    const aviso = error && <p className="pl-error" role="alert">{error.mensaje} {reintentar}</p>;
    const proyecto = ficha?.proyecto;
    const subtitulo = useMemo(() => (proyecto
        ? [proyecto.descripcion_municipio, proyecto.descripcion_fase].filter(Boolean).join(' · ')
        : ''), [proyecto]);

    if (!ficha) {
        return (
            <div className="pl-pagina">
                <Volver />
                {aviso || <p className="gp-vacio" role="status">Cargando proyecto…</p>}
            </div>
        );
    }

    return (
        <div className="pl-pagina">
            <Volver />
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Proyecto{subtitulo ? ` · ${subtitulo}` : ''}</span>
                <h1>{proyecto.nombre || 'Sin nombre'}</h1>
                <p>Cada avance se mide por separado: el del expediente cuenta requisitos; el de cada contrato, su ejecución física y financiera.</p>
            </header>
            {aviso}
            <div className="fp-bloques">
                <General proyecto={proyecto} fuentes={fuentes} sectores={sectores} />
                <Formulacion proyectoId={proyecto.id} expediente={ficha.expediente} />
                <Procesos procesos={ficha.procesos ?? []} onAbrir={() => setProcesosAbiertos(true)} />
                <Contratos proyecto={proyecto} contratos={ficha.contratos ?? []} edita={edita} />
            </div>

            {procesosAbiertos && (
                <ProcesosProyecto
                    key={proyecto.id}
                    proyecto={proyecto}
                    user={user}
                    onCerrar={() => { setProcesosAbiertos(false); cargar(); }}
                    onCambio={cargar}
                />
            )}
        </div>
    );
};

/**
 * La ficha del proyecto (`/proyectos/:proyectoId`): sus datos generales, su formulación, sus
 * procesos de contratación y sus contratos, cada bloque con enlace a lo que ya existe. Un id
 * que no es válido no llega a la API.
 */
const FichaProyecto = ({ user }) => {
    const { proyectoId } = useParams();
    if (!esIdProyecto(proyectoId)) {
        return (
            <div className="pl-pagina">
                <Volver />
                <p className="pl-error" role="alert">El proyecto no existe.</p>
            </div>
        );
    }
    return <Ficha key={proyectoId} proyectoId={proyectoId} user={user} />;
};

export default FichaProyecto;
```

Crear `resources/js/utils/fichaProyecto.js`:

```js
// Lógica de presentación de la ficha del proyecto (/proyectos/:proyectoId, GET /proyectos/{id}/ficha):
// el bloque General, el resumen de la formulación y las filas de procesos. Las filas de contratos
// son las de la lista de Contratos (filaContrato). Los avances del expediente, de los procesos y de
// cada contrato se presentan por separado: nunca se suman ni se promedian. Funciones puras,
// probadas en fichaProyecto.test.js.

import { esIdContrato } from './contratosModulo';
import { etiquetaEstadoProceso, numeroProceso, textoContratos, textoFecha, textoValor, tonoEstado } from './contratacion';
import { faseLegible } from './expedienteChecklist';
import { mensajeApi } from './plantillasChecklist';

/** Si el `:proyectoId` de la ruta es un id: la misma regla que el de un contrato (lo que acepta la API). */
export const esIdProyecto = (valor) => esIdContrato(valor);

/** El error al cargar la ficha: un proyecto que no existe no se reintenta. */
export const errorDeFicha = (error) => (error?.response?.status === 404
    ? { mensaje: 'El proyecto no existe.', reintentable: false }
    : { mensaje: mensajeApi(error), reintentable: true });

const texto = (valor, siVacio = '—') => String(valor ?? '').trim() || siVacio;
const mismoId = (a, b) => String(a) === String(b);
const plural = (n, singular, varios) => (n === 1 ? `1 ${singular}` : `${n} ${varios}`);

// ---------- General ----------

/**
 * Las fuentes del proyecto por su nombre en el catálogo (GET /fuentesFinanciacion). Sin el
 * catálogo (cargando o falló), el texto de financiación que el proyecto ya tiene o cuántas son.
 */
export const textoFuentes = (proyecto, fuentes) => {
    const ids = proyecto?.fuente_ids ?? [];
    if (Boolean(Number(proyecto?.financiacion_por_definir)) && ids.length === 0) return 'Por definir';
    if (ids.length === 0) return '—';
    if (!Array.isArray(fuentes)) return texto(proyecto.fuente_financiacion, plural(ids.length, 'fuente', 'fuentes'));
    return ids.map((id) => fuentes.find((f) => mismoId(f.id, id))?.nombre ?? `Fuente ${id}`).join(', ');
};

/** El sector del proyecto por su nombre en el catálogo (GET /sectores), o una raya. */
export const textoSector = (proyecto, sectores) => {
    if (proyecto?.sector === null || proyecto?.sector === undefined || !Array.isArray(sectores)) return '—';
    return texto(sectores.find((s) => mismoId(s.id, proyecto.sector))?.nombre);
};

/**
 * Los datos del bloque General ({etiqueta, valor, ancho}). El presupuesto es la suma de los
 * componentes; sin componentes, el presupuesto del proyecto (como en el tablero).
 */
export const datosGenerales = (proyecto, { fuentes = null, sectores = null } = {}) => [
    { etiqueta: 'Municipio', valor: texto(proyecto.descripcion_municipio) },
    { etiqueta: 'Fase', valor: texto(proyecto.descripcion_fase) },
    { etiqueta: 'Estado', valor: texto(proyecto.descripcion_estado) },
    { etiqueta: 'Sector', valor: textoSector(proyecto, sectores) },
    { etiqueta: 'Fuentes de financiación', valor: textoFuentes(proyecto, fuentes) },
    { etiqueta: 'Presupuesto', valor: textoValor(Number(proyecto.totalPresupuesto) || proyecto.presupuesto) },
    { etiqueta: 'Fecha de inicio', valor: textoFecha(proyecto.fecha_inicio) },
    { etiqueta: 'Entidad que presenta', valor: texto(proyecto.descripcion_entidad_presenta) },
    { etiqueta: 'Entidad que financia', valor: texto(proyecto.descripcion_entidad_financia) },
    { etiqueta: 'Descripción', valor: texto(proyecto.descripcion), ancho: true },
];

// ---------- Formulación ----------

const ESTADO_EXPEDIENTE = { borrador: 'Borrador', radicado: 'Radicado' };

/**
 * El resumen del expediente más reciente (el de GET /expedientes) con su propio avance de
 * requisitos. Sin configurar, la lista de requisitos aún no es la real: no hay avance.
 */
export const resumenFormulacion = (expediente, proyectoId) => {
    if (!expediente) return { tiene: false, texto: 'Sin expediente' };

    const sinConfigurar = expediente.estado === 'borrador' && !expediente.configurado;
    const { total = 0, cumplidos = 0, porcentaje = 0 } = expediente.progreso ?? {};
    const cantidad = Number(expediente.cantidad_expedientes) || 0;

    return {
        tiene: true,
        estado: sinConfigurar ? 'Sin configurar' : ESTADO_EXPEDIENTE[expediente.estado] ?? texto(expediente.estado),
        porcentaje: sinConfigurar ? null : porcentaje,
        avance: sinConfigurar
            ? 'Sin configurar: aún no hay requisitos que medir'
            : `${porcentaje}% (${cumplidos} de ${total} requisitos cumplidos)`,
        plantillas: (expediente.plantillas ?? []).length ? expediente.plantillas.join(', ') : '—',
        // La fase solo es de regalías: se muestra si el expediente tiene una plantilla con motor sgr.
        faseSgr: expediente.tiene_sgr ? faseLegible(expediente.fase_sgr) : null,
        radicado: expediente.fecha_radicado ? textoFecha(expediente.fecha_radicado) : null,
        otros: cantidad > 1 ? `El proyecto tiene ${cantidad} expedientes: se muestra el más reciente.` : null,
        ruta: `/viabilizacion/${proyectoId}`,
    };
};

// ---------- Procesos ----------

/** Una fila de la lista de procesos (un elemento de GET /proyectos/{id}/procesos). */
export const filaProceso = (proceso) => {
    const numero = numeroProceso(proceso);
    return {
        id: proceso.id,
        numero: numero.texto,
        enlace: numero.enlace,
        objeto: texto(proceso.objeto),
        modalidad: texto(proceso.modalidad?.nombre),
        estado: etiquetaEstadoProceso(proceso.estado),
        tono: tonoEstado(proceso.estado),
        monto: textoValor(proceso.monto),
        contratos: textoContratos(proceso.contratos),
    };
};
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (1 de 4):

```php
use App\Models\Contrato;
use App\Models\Proyecto;
use App\Services\Contratacion\ContratosService;
use App\Services\Financiacion\FuentesProyecto;
use App\Services\Viabilizacion\ExpedienteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProyectoController extends Controller
{
    public function proyectos()
    {
        $proyectos = DB::table('proyectos')
            ->leftJoin('municipios', 'proyectos.municipio', 'municipios.codigo')
            ->leftJoin('entidades as entidad_presenta', 'proyectos.entidad_presenta', 'entidad_presenta.id')
            ->leftJoin('entidades as entidad_financia', 'proyectos.entidad_financia', 'entidad_financia.id')
            ->leftJoin('fases as fase', 'proyectos.fase', 'fase.id')
            ->leftJoin('estados as estado', 'proyectos.estado', 'estado.id')
            ->select(
                'proyectos.id',
                'proyectos.nombre',
                'proyectos.descripcion',
                'proyectos.municipio',
                'proyectos.fecha_inicio',
                'proyectos.estado',
                'proyectos.fase',
                'proyectos.presupuesto',
                'proyectos.entidad_presenta',
                'proyectos.entidad_financia',
                'proyectos.fuente_financiacion',
                'proyectos.financiacion_por_definir',
                'proyectos.sector',
                'fase.color as color_fase',
                'fase.dashboard as dashboard_fase',
                'estado.color as color_estado',
                'proyectos.progreso',
                DB::raw('municipios.nombre as descripcion_municipio'),
                DB::raw('entidad_presenta.nombre as descripcion_entidad_presenta'),
                DB::raw('entidad_financia.nombre as descripcion_entidad_financia'),
                DB::raw('fase.nombre as descripcion_fase'),
                DB::raw('estado.nombre as descripcion_estado')
            )
            ->get();

        
```

por:

```php
use App\Models\Contrato;
use App\Models\Proyecto;
use App\Services\Contratacion\ContratosService;
use App\Services\Contratacion\ProcesosContratacion;
use App\Services\Financiacion\FuentesProyecto;
use App\Services\Viabilizacion\ExpedienteService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProyectoController extends Controller
{
    public function proyectos()
    {
        $proyectos = $this->consultaProyectos()->get();

        
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (2 de 4):

```php
                ->map(fn ($fila) => (int) $fila->fuente_id)
                ->values();

            $componentesPresupuesto = DB::table('presupuesto_proyecto')
                ->select('componente', 'valor', 'id',)
                ->where('proyecto', $proyecto->id)
                ->get();
            $proyecto->componentesPresupuesto = $componentesPresupuesto;
            $totalPresupuesto = $componentesPresupuesto->sum('valor');
```

por:

```php
                ->map(fn ($fila) => (int) $fila->fuente_id)
                ->values();

            $componentesPresupuesto = $this->componentesPresupuesto($proyecto->id);
            $proyecto->componentesPresupuesto = $componentesPresupuesto;
            $totalPresupuesto = $componentesPresupuesto->sum('valor');
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (3 de 4):

```php

            $proyecto->contratos = $contratos;

            $proyecto->puntosUbicacion = DB::table('proyecto_ubicaciones')
                ->select('id', 'lat', 'lng')
                ->where('proyecto', $proyecto->id)
                ->get();
        }

        // El proceso de cada contrato, resumido: una sola consulta para todos los proyectos.
```

por:

```php

            $proyecto->contratos = $contratos;

            $proyecto->puntosUbicacion = $this->puntosUbicacion($proyecto->id);
        }

        // El proceso de cada contrato, resumido: una sola consulta para todos los proyectos.
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (4 de 4):

```php
        return response()->json($proyectos);
    }

    public function eventos()
    {
        $eventos = DB::table('eventos')
```

por:

```php
        return response()->json($proyectos);
    }

    /**
     * Ficha del proyecto: sus datos generales (la fila de GET /proyectos sin sus contratos), el
     * resumen de su expediente (el de GET /expedientes, o null), sus procesos (los de
     * GET /proyectos/{id}/procesos) y sus contratos (los de GET /contratos/buscar?proyecto=id).
     * El id llega como texto: uno de más de 18 cifras no cabe en un entero y tampoco existe.
     */
    public function ficha(string $proyecto, ExpedienteService $expedientes, ProcesosContratacion $procesos, ContratosService $contratos)
    {
        $modelo = preg_match('/^[0-9]{1,18}$/', $proyecto) === 1 ? Proyecto::find((int) $proyecto) : null;
        if ($modelo === null) {
            return response()->json(['mensaje' => 'El proyecto no existe.'], 404);
        }

        $fila = $this->consultaProyectos()->where('proyectos.id', $modelo->id)->first();
        $fila->fuente_ids = DB::table('proyecto_fuentes')->where('proyecto_id', $modelo->id)->pluck('fuente_id')
            ->map(fn ($id) => (int) $id)
            ->values();
        $fila->componentesPresupuesto = $this->componentesPresupuesto($modelo->id);
        $fila->totalPresupuesto = $fila->componentesPresupuesto->sum('valor');
        $fila->puntosUbicacion = $this->puntosUbicacion($modelo->id);

        return response()->json([
            'proyecto' => $fila,
            'expediente' => $expedientes->resumenPorProyecto($modelo->id)[0] ?? null,
            'procesos' => $procesos->deProyecto($modelo),
            'contratos' => $contratos->buscar(['proyecto' => (string) $modelo->id]),
        ]);
    }

    /** Los proyectos con los nombres de su municipio, entidades, fase y estado: las columnas de GET /proyectos. */
    private function consultaProyectos(): Builder
    {
        return DB::table('proyectos')
            ->leftJoin('municipios', 'proyectos.municipio', 'municipios.codigo')
            ->leftJoin('entidades as entidad_presenta', 'proyectos.entidad_presenta', 'entidad_presenta.id')
            ->leftJoin('entidades as entidad_financia', 'proyectos.entidad_financia', 'entidad_financia.id')
            ->leftJoin('fases as fase', 'proyectos.fase', 'fase.id')
            ->leftJoin('estados as estado', 'proyectos.estado', 'estado.id')
            ->select(
                'proyectos.id',
                'proyectos.nombre',
                'proyectos.descripcion',
                'proyectos.municipio',
                'proyectos.fecha_inicio',
                'proyectos.estado',
                'proyectos.fase',
                'proyectos.presupuesto',
                'proyectos.entidad_presenta',
                'proyectos.entidad_financia',
                'proyectos.fuente_financiacion',
                'proyectos.financiacion_por_definir',
                'proyectos.sector',
                'fase.color as color_fase',
                'fase.dashboard as dashboard_fase',
                'estado.color as color_estado',
                'proyectos.progreso',
                DB::raw('municipios.nombre as descripcion_municipio'),
                DB::raw('entidad_presenta.nombre as descripcion_entidad_presenta'),
                DB::raw('entidad_financia.nombre as descripcion_entidad_financia'),
                DB::raw('fase.nombre as descripcion_fase'),
                DB::raw('estado.nombre as descripcion_estado')
            );
    }

    private function componentesPresupuesto(int $proyectoId): Collection
    {
        return DB::table('presupuesto_proyecto')
            ->select('componente', 'valor', 'id',)
            ->where('proyecto', $proyectoId)
            ->get();
    }

    private function puntosUbicacion(int $proyectoId): Collection
    {
        return DB::table('proyecto_ubicaciones')
            ->select('id', 'lat', 'lng')
            ->where('proyecto', $proyectoId)
            ->get();
    }

    public function eventos()
    {
        $eventos = DB::table('eventos')
```

En `app/Services/Viabilizacion/ExpedienteService.php`, reemplazar (1 de 1):

```php
     * Expediente más reciente de cada proyecto que tiene alguno, con su avance, sus
     * plantillas y los responsables con requisitos pendientes. Son cuatro consultas sin
     * importar cuántos proyectos haya: cantidades, plantillas, responsables y expedientes
     * con su avance.
     */
    public function resumenPorProyecto(): array
    {
        $cantidades = ProyectoExpediente::query()
            ->selectRaw('MAX(id) AS ultimo_id, COUNT(*) AS cantidad')
            ->groupBy('proyecto_id')
            ->pluck('cantidad', 'ultimo_id');
```

por:

```php
     * Expediente más reciente de cada proyecto que tiene alguno, con su avance, sus
     * plantillas y los responsables con requisitos pendientes. Son cuatro consultas sin
     * importar cuántos proyectos haya: cantidades, plantillas, responsables y expedientes
     * con su avance. Con `$proyectoId`, solo el de ese proyecto (la ficha del proyecto).
     */
    public function resumenPorProyecto(?int $proyectoId = null): array
    {
        $cantidades = ProyectoExpediente::query()
            ->when($proyectoId !== null, fn ($q) => $q->where('proyecto_id', $proyectoId))
            ->selectRaw('MAX(id) AS ultimo_id, COUNT(*) AS cantidad')
            ->groupBy('proyecto_id')
            ->pluck('cantidad', 'ultimo_id');
```

En `resources/css/Tablero.css`, reemplazar (1 de 1):

```css
.tb-dato.ancho { grid-column: 1 / -1; }
.tb-dato dd { margin-top: .1rem; font-size: .93rem; overflow-wrap: anywhere; }
.tb-modal-accion { width: 100%; margin-top: 1.1rem; white-space: normal; }

/* ---------- Pantallas amplias: territorio, distribución y eventos en una fila ---------- */
@media (min-width: 1320px) {
```

por:

```css
.tb-dato.ancho { grid-column: 1 / -1; }
.tb-dato dd { margin-top: .1rem; font-size: .93rem; overflow-wrap: anywhere; }
.tb-modal-accion { width: 100%; margin-top: 1.1rem; white-space: normal; }
.tb-modal-accion + .tb-modal-accion { margin-top: .5rem; }

/* ---------- Pantallas amplias: territorio, distribución y eventos en una fila ---------- */
@media (min-width: 1320px) {
```

En `resources/js/app.jsx`, reemplazar (1 de 2):

```jsx
import PlantillasChecklist from './components/PlantillasChecklist';
import PlantillaDetalle from './components/PlantillaDetalle';
import ContratosModulo from './components/contratos/ContratosModulo';
import { RUTA_CONTRATOS, RUTA_PLANTILLAS, puedeVer } from './utils/navegacion';
import axios from './axios';
import { EVENTO_SESION_EXPIRADA } from './utils/sesion';
```

por:

```jsx
import PlantillasChecklist from './components/PlantillasChecklist';
import PlantillaDetalle from './components/PlantillaDetalle';
import ContratosModulo from './components/contratos/ContratosModulo';
import FichaProyecto from './components/FichaProyecto';
import { RUTA_CONTRATOS, RUTA_PLANTILLAS, puedeVer } from './utils/navegacion';
import axios from './axios';
import { EVENTO_SESION_EXPIRADA } from './utils/sesion';
```

En `resources/js/app.jsx`, reemplazar (2 de 2):

```jsx
          <Route path="/parametros/:seccion" element={<Parametros user={user} />} />
          {/* No usar /expedientes/...: bajo /GestPro chocaría con la API del mismo nombre. */}
          <Route path="/viabilizacion/:proyectoId" element={<ExpedienteViabilizacion user={user} />} />
          {/* Lectura para todos; nuevo y modificar, solo quien edita (ContratosModulo lo decide). */}
          <Route path={RUTA_CONTRATOS} element={<ContratosModulo user={user} />} />
          <Route path={`${RUTA_CONTRATOS}/nuevo`} element={<ContratosModulo user={user} nuevo />} />
```

por:

```jsx
          <Route path="/parametros/:seccion" element={<Parametros user={user} />} />
          {/* No usar /expedientes/...: bajo /GestPro chocaría con la API del mismo nombre. */}
          <Route path="/viabilizacion/:proyectoId" element={<ExpedienteViabilizacion user={user} />} />
          {/* Siempre con id: GET /proyectos (sin id) es de la API. */}
          <Route path="/proyectos/:proyectoId" element={<FichaProyecto user={user} />} />
          {/* Lectura para todos; nuevo y modificar, solo quien edita (ContratosModulo lo decide). */}
          <Route path={RUTA_CONTRATOS} element={<ContratosModulo user={user} />} />
          <Route path={`${RUTA_CONTRATOS}/nuevo`} element={<ContratosModulo user={user} nuevo />} />
```

En `resources/js/components/ModalProyecto.jsx`, reemplazar (1 de 2):

```jsx
import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faClipboardCheck, faXmark } from '@fortawesome/free-solid-svg-icons';
import { COLOR_SEVERIDAD, construirSerieTemporal, severidadDesfase } from '../utils/avanceContratos';
import { textoAvanceProyecto } from '../utils/expedientesResumen';
import { etiquetaEstadoContrato } from '../utils/contratos';

const moneda = (valor) => (valor || valor === 0 ? `$ ${Math.round(Number(valor)).toLocaleString('es-CO')}` : '—');
```

por:

```jsx
import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faClipboardCheck, faIdCard, faXmark } from '@fortawesome/free-solid-svg-icons';
import { COLOR_SEVERIDAD, construirSerieTemporal, severidadDesfase } from '../utils/avanceContratos';
import { textoAvanceProyecto } from '../utils/expedientesResumen';
import { etiquetaEstadoContrato } from '../utils/contratos';
import { rutaFichaProyecto } from '../utils/navegacion';

const moneda = (valor) => (valor || valor === 0 ? `$ ${Math.round(Number(valor)).toLocaleString('es-CO')}` : '—');
```

En `resources/js/components/ModalProyecto.jsx`, reemplazar (2 de 2):

```jsx
                        <button type="button" className="gp-boton gp-boton-primario tb-modal-accion" onClick={() => navigate(`/viabilizacion/${proyecto.id}`)}>
                            <FontAwesomeIcon icon={faClipboardCheck} /> Abrir expediente
                        </button>
                    </>
                )}
```

por:

```jsx
                        <button type="button" className="gp-boton gp-boton-primario tb-modal-accion" onClick={() => navigate(`/viabilizacion/${proyecto.id}`)}>
                            <FontAwesomeIcon icon={faClipboardCheck} /> Abrir expediente
                        </button>
                        <button type="button" className="gp-boton tb-modal-accion" onClick={() => navigate(rutaFichaProyecto(proyecto.id))}>
                            <FontAwesomeIcon icon={faIdCard} /> Ver ficha
                        </button>
                    </>
                )}
```

En `resources/js/components/Parametros.jsx`, reemplazar (1 de 3):

```jsx
import React, { useState, useEffect } from 'react';
import { Navigate, useNavigate, useParams } from 'react-router-dom';
import axios from '../axios';
import { faPlus, faTrash, faSave, faTimes, faFile, faEdit, faClipboardCheck, faGavel } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
import EmojiPicker from 'emoji-picker-react';
```

por:

```jsx
import React, { useState, useEffect } from 'react';
import { Navigate, useNavigate, useParams } from 'react-router-dom';
import axios from '../axios';
import { faPlus, faTrash, faSave, faTimes, faFile, faEdit, faClipboardCheck, faGavel, faIdCard } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
import EmojiPicker from 'emoji-picker-react';
```

En `resources/js/components/Parametros.jsx`, reemplazar (2 de 3):

```jsx
import 'leaflet/dist/leaflet.css';
import '../leafletIconFix';
import '../../css/Parametros.css';
import { seccionDesdeSlug } from '../utils/navegacion';
import { alternarFuente, datosFinanciacionDe, financiacionCompleta, fuentesDisponibles, mensajeDeError } from '../utils/fuentesProyecto';
import { formatoMoneda, limpiarMoneda, monedaEnEdicion, numeroDeMoneda } from '../utils/moneda';
import CatalogoModalidades from './CatalogoModalidades';
```

por:

```jsx
import 'leaflet/dist/leaflet.css';
import '../leafletIconFix';
import '../../css/Parametros.css';
import { rutaFichaProyecto, seccionDesdeSlug } from '../utils/navegacion';
import { alternarFuente, datosFinanciacionDe, financiacionCompleta, fuentesDisponibles, mensajeDeError } from '../utils/fuentesProyecto';
import { formatoMoneda, limpiarMoneda, monedaEnEdicion, numeroDeMoneda } from '../utils/moneda';
import CatalogoModalidades from './CatalogoModalidades';
```

En `resources/js/components/Parametros.jsx`, reemplazar (3 de 3):

```jsx
                                                </button>
                                            )}

                                            {type === 'proyectos' && (
                                                <button
                                                    onClick={() => setProyectoContratos(item)}
```

por:

```jsx
                                                </button>
                                            )}

                                            {type === 'proyectos' && (
                                                <button
                                                    onClick={() => navigate(rutaFichaProyecto(item.id))}
                                                    className="btn-add-contrato-list"
                                                    title="Ficha del proyecto"
                                                >
                                                    <FontAwesomeIcon icon={faIdCard} /> Ficha
                                                </button>
                                            )}

                                            {type === 'proyectos' && (
                                                <button
                                                    onClick={() => setProyectoContratos(item)}
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (1 de 6):

```jsx
import {
    TEXTO_SECOP_VACIO, VISTA_LISTA, VISTA_SECOP, consultaDeFiltros, consultaVistaSecop, errorNit, esIdContrato, estadoImportarSecop,
    estadoSinSecop, filaContrato, filtrosDeConsulta, guardarNitEmpresa, leerNitEmpresa, nitDeConsulta, nitParaMostrar, normalizarNit,
    opcionesFiltroEstado, opcionesFiltroProyecto, parametrosBusqueda, puedeEditarContratos, resultadoSecopDeEstado, rutaContrato,
    rutaListaDesde, rutaVistaSecop, textoListaVacia, vistaDeConsulta,
} from '../../utils/contratosModulo';
import { RUTA_CONTRATOS } from '../../utils/navegacion';
import { mensajeApi } from '../../utils/plantillasChecklist';
```

por:

```jsx
import {
    TEXTO_SECOP_VACIO, VISTA_LISTA, VISTA_SECOP, consultaDeFiltros, consultaVistaSecop, errorNit, esIdContrato, estadoImportarSecop,
    estadoSinSecop, filaContrato, filtrosDeConsulta, guardarNitEmpresa, leerNitEmpresa, nitDeConsulta, nitParaMostrar, normalizarNit,
    opcionesFiltroEstado, opcionesFiltroProyecto, parametrosBusqueda, proyectoDeEstado, puedeEditarContratos, resultadoSecopDeEstado, rutaContrato,
    rutaListaDesde, rutaVistaSecop, textoListaVacia, textoVolverDesde, vistaDeConsulta,
} from '../../utils/contratosModulo';
import { RUTA_CONTRATOS } from '../../utils/navegacion';
import { mensajeApi } from '../../utils/plantillasChecklist';
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (2 de 6):

```jsx
    </p>
) : null);

const Volver = ({ a }) => (
    <Link to={a} className="pl-volver"><FontAwesomeIcon icon={faArrowLeft} /> Contratos</Link>
);

/** Las vistas de /contratos para quien puede editar: la lista y los contratos de la empresa en SECOP II. */
```

por:

```jsx
    </p>
) : null);

const Volver = ({ a, texto = 'Contratos' }) => (
    <Link to={a} className="pl-volver"><FontAwesomeIcon icon={faArrowLeft} /> {texto}</Link>
);

/** Las vistas de /contratos para quien puede editar: la lista y los contratos de la empresa en SECOP II. */
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (3 de 6):

```jsx

/**
 * Un contrato nuevo, con proyecto opcional. Si llega de "Importar" (con el resultado de SECOP II
 * en el estado de navegación), el formulario empieza lleno; sin estado (al abrir la dirección a
 * mano), vacío. Al guardarlo se abre, para seguir con sus pestañas.
 */
const NuevoContrato = ({ proyectos }) => {
    const { state, key } = useLocation();
    const navigate = useNavigate();
    const volver = rutaListaDesde(state);
    const importacion = resultadoSecopDeEstado(state);

    return (
        <div className="pl-pagina">
            <Volver a={volver} />
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Contratos</span>
                <h1>Nuevo contrato</h1>
```

por:

```jsx

/**
 * Un contrato nuevo, con proyecto opcional. Si llega de "Importar" (con el resultado de SECOP II
 * en el estado de navegación), el formulario empieza lleno; si llega de la ficha del proyecto,
 * con ese proyecto ya elegido; sin estado (al abrir la dirección a mano), vacío. Al guardarlo se
 * abre, para seguir con sus pestañas.
 */
const NuevoContrato = ({ proyectos }) => {
    const { state, key } = useLocation();
    const navigate = useNavigate();
    const volver = rutaListaDesde(state);
    const importacion = resultadoSecopDeEstado(state);
    const proyecto = proyectoDeEstado(state);

    return (
        <div className="pl-pagina">
            <Volver a={volver} texto={textoVolverDesde(state)} />
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Contratos</span>
                <h1>Nuevo contrato</h1>
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (4 de 6):

```jsx
            <div className="gp-panel ct-editor">
                <ContratoEditor
                    key={key}
                    proyectos={proyectos.lista}
                    importacionSecop={importacion}
                    recargar={recargarDetalle}
```

por:

```jsx
            <div className="gp-panel ct-editor">
                <ContratoEditor
                    key={key}
                    proyecto={proyecto}
                    proyectos={proyectos.lista}
                    importacionSecop={importacion}
                    recargar={recargarDetalle}
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (5 de 6):

```jsx
    if (error) {
        return (
            <div className="pl-pagina">
                <Volver a={volver} />
                <p className="pl-error" role="alert">
                    {error.mensaje}{' '}
                    {error.reintentable && <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>}
```

por:

```jsx
    if (error) {
        return (
            <div className="pl-pagina">
                <Volver a={volver} texto={textoVolverDesde(state)} />
                <p className="pl-error" role="alert">
                    {error.mensaje}{' '}
                    {error.reintentable && <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>}
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (6 de 6):

```jsx

    return (
        <div className="pl-pagina">
            <Volver a={volver} />
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Contrato · {contrato.proyecto_nombre || 'Sin proyecto'}</span>
                <h1>{contrato.n_contrato || 'Sin número'}</h1>
```

por:

```jsx

    return (
        <div className="pl-pagina">
            <Volver a={volver} texto={textoVolverDesde(state)} />
            <header className="gp-cabecera">
                <span className="gp-etiqueta">Contrato · {contrato.proyecto_nombre || 'Sin proyecto'}</span>
                <h1>{contrato.n_contrato || 'Sin número'}</h1>
```

En `resources/js/utils/contratosModulo.js`, reemplazar (1 de 3):

```js
// Lógica de presentación del módulo Contratos (/contratos): filtros en la URL, filas de la lista,
// opciones de los selectores de proyecto, rutas y la vista de los contratos de la empresa en
// SECOP II (la vista en la URL, el NIT recordado y el estado de "Importar"). Funciones puras, probadas en
// contratosModulo.test.js. Los filtros los valida también GET /contratos/buscar.

import { ESTADOS_CONTRATO, etiquetaEstadoContrato } from './contratos';
import { formatoMoneda } from './moneda';
import { RUTA_CONTRATOS } from './navegacion';

/** El valor del filtro de proyecto para los contratos sin proyecto (el `proyecto=sin` de la API). */
export const SIN_PROYECTO = 'sin';
```

por:

```js
// Lógica de presentación del módulo Contratos (/contratos): filtros en la URL, filas de la lista,
// opciones de los selectores de proyecto, rutas y la vista de los contratos de la empresa en
// SECOP II (la vista en la URL, el NIT recordado y el estado de "Importar") y el proyecto de un
// contrato nuevo abierto desde la ficha del proyecto. Funciones puras, probadas en
// contratosModulo.test.js. Los filtros los valida también GET /contratos/buscar.

import { ESTADOS_CONTRATO, etiquetaEstadoContrato } from './contratos';
import { formatoMoneda } from './moneda';
import { RUTA_CONTRATOS, rutaFichaProyecto } from './navegacion';

/** El valor del filtro de proyecto para los contratos sin proyecto (el `proyecto=sin` de la API). */
export const SIN_PROYECTO = 'sin';
```

En `resources/js/utils/contratosModulo.js`, reemplazar (2 de 3):

```js
export const rutaContrato = (id) => `${RUTA_CONTRATOS}/${id}`;

/**
 * La lista a la que se vuelve desde un contrato: la que se dejó, con sus filtros, si la
 * navegación la trae en su estado (`{ lista: '?q=…' }`); si no, la lista sin filtros.
 */
export const rutaListaDesde = (estado) => {
    const lista = estado?.lista;
    return typeof lista === 'string' && lista.startsWith('?') ? `${RUTA_CONTRATOS}${lista}` : RUTA_CONTRATOS;
};

// ---------- Contratos de la empresa en SECOP II ----------

/** Las vistas de /contratos: la lista y, para quien puede editar, la de SECOP II (`?vista=secop`). */
```

por:

```js
export const rutaContrato = (id) => `${RUTA_CONTRATOS}/${id}`;

/**
 * El estado de navegación de un enlace a un contrato desde la ficha de un proyecto: "Volver",
 * "Cancelar" y el guardado regresan a esa ficha (rutaListaDesde).
 */
export const estadoDesdeFicha = (proyectoId) => ({ ficha: proyectoId });

// El id de la ficha de la que se viene, si el estado lo trae y es un id válido.
const fichaDeEstado = (estado) => (esIdContrato(estado?.ficha) ? estado.ficha : null);

/**
 * A dónde se vuelve desde un contrato: la ficha del proyecto, si se vino de ella
 * (`{ ficha: id }`, estadoDesdeFicha); la lista que se dejó, con sus filtros, si la navegación la
 * trae en su estado (`{ lista: '?q=…' }`); si no, la lista sin filtros.
 */
export const rutaListaDesde = (estado) => {
    const ficha = fichaDeEstado(estado);
    if (ficha !== null) return rutaFichaProyecto(ficha);
    const lista = estado?.lista;
    return typeof lista === 'string' && lista.startsWith('?') ? `${RUTA_CONTRATOS}${lista}` : RUTA_CONTRATOS;
};

/** El texto del enlace "Volver" que va a rutaListaDesde(estado). */
export const textoVolverDesde = (estado) => (fichaDeEstado(estado) !== null ? 'Ficha del proyecto' : 'Contratos');

// ---------- Contratos de la empresa en SECOP II ----------

/** Las vistas de /contratos: la lista y, para quien puede editar, la de SECOP II (`?vista=secop`). */
```

En `resources/js/utils/contratosModulo.js`, reemplazar (3 de 3):

```js
    const { secop, ...resto } = estado;
    return resto;
};
```

por:

```js
    const { secop, ...resto } = estado;
    return resto;
};

// ---------- Contrato nuevo de un proyecto ----------

/**
 * "Nuevo contrato" desde la ficha del proyecto: el estado de navegación de /contratos/nuevo con
 * el proyecto ya elegido y la ficha de la que se viene (para volver a ella).
 */
export const estadoNuevoContratoDeProyecto = (proyecto) => ({
    proyecto: { id: proyecto.id, nombre: proyecto.nombre },
    ...estadoDesdeFicha(proyecto.id),
});

/**
 * El proyecto del estado de navegación ({id, nombre}), o null. Sin estado (al abrir la dirección
 * a mano) o con un id que no es válido, el contrato empieza sin proyecto.
 */
export const proyectoDeEstado = (estado) => {
    const proyecto = estado?.proyecto;
    if (!esObjeto(proyecto) || !esIdContrato(proyecto.id)) return null;
    return { id: proyecto.id, nombre: String(proyecto.nombre ?? '') };
};
```

En `resources/js/utils/navegacion.js`, reemplazar (1 de 3):

```js
// Todos los contratos, con o sin proyecto. Bajo /GestPro no es una ruta GET de la API.
export const RUTA_CONTRATOS = '/contratos';

export const DESTINOS = [
    { id: 'tablero', titulo: 'Tablero', ruta: '/dashboard', grupo: GRUPO.TRABAJO, icono: 'faGaugeHigh', palabras: 'inicio dashboard panel mapa' },
    ...SECCIONES_PARAMETROS.filter((s) => s.grupo === GRUPO.TRABAJO).map((s) => ({ ...s, id: s.slug, ruta: `/parametros/${s.slug}` })),
```

por:

```js
// Todos los contratos, con o sin proyecto. Bajo /GestPro no es una ruta GET de la API.
export const RUTA_CONTRATOS = '/contratos';

// La ficha de un proyecto. Siempre con id: GET /proyectos (sin id) es de la API.
export const rutaFichaProyecto = (id) => `/proyectos/${id}`;

export const DESTINOS = [
    { id: 'tablero', titulo: 'Tablero', ruta: '/dashboard', grupo: GRUPO.TRABAJO, icono: 'faGaugeHigh', palabras: 'inicio dashboard panel mapa' },
    ...SECCIONES_PARAMETROS.filter((s) => s.grupo === GRUPO.TRABAJO).map((s) => ({ ...s, id: s.slug, ruta: `/parametros/${s.slug}` })),
```

En `resources/js/utils/navegacion.js`, reemplazar (2 de 3):

```js
            clave: `proyecto-${p.id}`,
            titulo: p.nombre,
            detalle: p.descripcion_municipio ?? '',
            ruta: `/dashboard?proyecto=${p.id}`,
            icono: 'faFolderOpen',
        }));
```

por:

```js
            clave: `proyecto-${p.id}`,
            titulo: p.nombre,
            detalle: p.descripcion_municipio ?? '',
            ruta: rutaFichaProyecto(p.id),
            icono: 'faFolderOpen',
        }));
```

En `resources/js/utils/navegacion.js`, reemplazar (3 de 3):

```js

export const destinoActivo = (pathname, search) => {
    if (pathname.startsWith('/viabilizacion/')) return 'expedientes';
    if (pathname === RUTA_PLANTILLAS || pathname.startsWith(`${RUTA_PLANTILLAS}/`)) return 'plantillas';
    if (pathname === RUTA_CONTRATOS || pathname.startsWith(`${RUTA_CONTRATOS}/`)) return 'contratos';
    if (pathname.startsWith('/parametros/')) return pathname.split('/')[2];
```

por:

```js

export const destinoActivo = (pathname, search) => {
    if (pathname.startsWith('/viabilizacion/')) return 'expedientes';
    if (pathname.startsWith('/proyectos/')) return 'proyectos';
    if (pathname === RUTA_PLANTILLAS || pathname.startsWith(`${RUTA_PLANTILLAS}/`)) return 'plantillas';
    if (pathname === RUTA_CONTRATOS || pathname.startsWith(`${RUTA_CONTRATOS}/`)) return 'contratos';
    if (pathname.startsWith('/parametros/')) return pathname.split('/')[2];
```

En `routes/api.php`, reemplazar (1 de 1):

```php

    // Lectura: cualquier rol autenticado (Administrador, Gestor o Consulta)
    Route::get('/proyectos', [ProyectoController::class, 'proyectos']);
    Route::get('/municipios', [ProyectoController::class, 'municipios']);
    Route::get('/estados', [ProyectoController::class, 'estados']);
    Route::get('/fases', [ProyectoController::class, 'fases']);
```

por:

```php

    // Lectura: cualquier rol autenticado (Administrador, Gestor o Consulta)
    Route::get('/proyectos', [ProyectoController::class, 'proyectos']);
    // Ficha del proyecto. Sin GET /proyectos/{id}: esa ruta es del SPA y no debe coincidir con la API.
    Route::get('/proyectos/{proyecto}/ficha', [ProyectoController::class, 'ficha'])->whereNumber('proyecto');
    Route::get('/municipios', [ProyectoController::class, 'municipios']);
    Route::get('/estados', [ProyectoController::class, 'estados']);
    Route::get('/fases', [ProyectoController::class, 'fases']);
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/ProyectoFichaApiTest.php`
Expected: pasan todas.

Run: `npx vitest run resources/js/utils/fichaProyecto.test.js resources/js/utils/contratosModulo.test.js resources/js/utils/navegacion.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1133 passed.

Run: `npm test`
Expected: 665 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/css/FichaProyecto.css resources/js/components/FichaProyecto.jsx resources/js/utils/fichaProyecto.js resources/js/utils/fichaProyecto.test.js tests/Feature/ProyectoFichaApiTest.php app/Http/Controllers/ProyectoController.php app/Services/Viabilizacion/ExpedienteService.php resources/css/Tablero.css resources/js/app.jsx resources/js/components/ModalProyecto.jsx resources/js/components/Parametros.jsx resources/js/components/contratos/ContratosModulo.jsx resources/js/utils/contratosModulo.js resources/js/utils/contratosModulo.test.js resources/js/utils/navegacion.js resources/js/utils/navegacion.test.js routes/api.php
git commit -m "feat: project overview page with formulation, processes and contracts"
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
git commit -m "build: production bundle for procurement stage 3C"
```

Los archivos que no cambiaron se omiten.

---

### Tarea 8: Verificación en la copia descartable

Usa la copia y la foto de la tarea 1. Nunca `gest_pro`.

- [ ] **Paso 1: Comparar los listados con el código nuevo**

```bash
DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion3.php" "$TMP_DIR/c5_despues.json" gestpro_verif_tmp
```

```bash
php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c5_antes.json" "$TMP_DIR/c5_despues.json"
```

Expected: `SIN DIFERENCIAS` en `GET /listarContratos`, en `GET /proyectos` (con sus contratos) y en los expedientes. Cualquier diferencia detiene la etapa.

- [ ] **Paso 2: Comprobar que PHP llega a SECOP II**

```bash
php -r '$c=curl_init("https://www.datos.gov.co/resource/jbjy-vk9h.json?\$limit=1&\$select=id_contrato"); curl_setopt($c,CURLOPT_RETURNTRANSFER,1); curl_setopt($c,CURLOPT_TIMEOUT,40); $r=curl_exec($c); echo $r===false ? curl_error($c) : $r, PHP_EOL;'
```

Expected: una lista con un `id_contrato`.

Si responde `SSL certificate problem: unable to get local issuer certificate`, el PHP de ese equipo no reconoce la cadena de certificados. Esto pasa, por ejemplo, cuando un antivirus inspecciona HTTPS. Se comprueba con:

```bash
echo | openssl s_client -connect www.datos.gov.co:443 -servername www.datos.gov.co 2>/dev/null | grep "i:"
```

Si el emisor es el antivirus, se arma en `$TMP_DIR` un paquete con `C:\xampp\php\extras\ssl\cacert.pem` más la raíz del antivirus, exportada en PEM desde el almacén de Windows. Para la prueba se arranca el servidor con `SECOP_CA_BUNDLE=<ruta del paquete>`. Nunca se desactiva la verificación ni se toca el `.env`.

- [ ] **Paso 3: Probar en el navegador sobre la copia**

Compilar en desarrollo (`npx mix`) si `public/` no tiene el código de esta etapa. Arrancar el servidor desde `public/`, en un puerto libre (`netstat -ano | grep 8796`), agregando `SECOP_CA_BUNDLE=...` solo si el paso 2 lo pidió:

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8796 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Crear un Administrador de prueba solo en la copia. Nunca usar sesiones de usuarios reales de `gest_pro`:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'verif@gestpro.test', 'rol' => App\Enums\Rol::Administrador]);"
```

Entrar por `http://127.0.0.1:8796/GestPro/`, borrando `localStorage` si hay una sesión anterior. Usar solo **datos públicos de ejemplo** de SECOP II: el contrato `CO1.PCCNTR.4078659` (número `1-305-2022`, Municipio de Cartago) y el NIT de proveedor `900919250`. Los datos de la empresa no se usan en esta prueba.

1. **`/contratos/nuevo` → "Buscar en SECOP II"** con `CO1.PCCNTR.4078659`:
   - aparece el contrato con la entidad, el contratista, $40.000.000 y las fechas;
   - "Usar este" llena el número, el objeto, la entidad y su NIT, el contratista y su documento, el valor, el estado ("En ejecución"), el tipo ("Prestación de servicios") y las fechas;
   - al guardar, el bloque SECOP II muestra el ID, el enlace y "Última consulta".
2. **Actualizar desde SECOP II:**
   - cambiar el objeto en GestPro y guardar;
   - al volver a abrir el contrato, "Actualizar desde SECOP II" muestra solo la diferencia del objeto, marcada.
3. **`/contratos?vista=secop` con el NIT `900919250`:**
   - lista los contratos del proveedor;
   - el importado aparece como "Ya registrado";
   - "Importar" en otro abre `/contratos/nuevo` con el formulario lleno.
4. **`/proyectos/<id de un proyecto con contratos>`:**
   - muestra General, Formulación (con el avance del expediente), Procesos y Contratos;
   - el botón "Ficha" de Parámetros → Proyectos lleva allí;
   - un id inexistente muestra "El proyecto no existe."

La consola del navegador no muestra errores nuevos. Los 401 de una sesión vieja y los 502 de un intento sin el paquete de certificados son esperados.

- [ ] **Paso 4: Limpiar**

Detener el servidor, eliminar la copia y devolver `public/` a lo comiteado si se compiló en desarrollo:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 5: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas, 1133.

Run: `npm test`
Expected: pasan todas, 665.

---

### Tarea 9: Informar y pedir validación

- [ ] **Paso 1: Actualizar el estado de la propuesta**

En `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, reemplazar:

```markdown
La etapa 3B (módulo Contratos, sin migraciones) está validada (2026-11-18).
```

por:

```markdown
La etapa 3B (módulo Contratos, sin migraciones) está validada (2026-11-18). La etapa 3C (SECOP II y ficha del proyecto, sin migraciones) está implementada y verificada en copia, pendiente de validar.
```

```bash
git add docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md
git commit -m "docs: mark procurement stage 3C as implemented"
```

- [ ] **Paso 2: Informar**

Informar:
- los totales de pruebas;
- la comparación de los listados;
- lo revisado en el navegador, incluida la consulta real a SECOP II.

Si el paso 2 de la tarea 8 necesitó el paquete de certificados, avisar que el servidor donde corre GestPro debe poder verificar `www.datos.gov.co`. Si no puede, se configura `SECOP_CA_BUNDLE` allí. Pedir que valide la etapa. Esta etapa no tiene migraciones.

---

## Criterio de la etapa

La etapa está lista cuando:

- un contrato se trae de SECOP II por ID, número o enlace, se guarda con su referencia, y se actualiza eligiendo qué copiar;
- los contratos de la empresa en SECOP II se listan por NIT y se importan;
- cada proyecto tiene su ficha con formulación, procesos y contratos;
- los listados existentes devuelven lo mismo que antes;
- ninguna prueba llama a SECOP II de verdad.
