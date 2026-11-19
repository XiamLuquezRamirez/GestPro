# Contratación, fase 7A: terminación y liquidación del contrato. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** que un contrato se pueda **terminar**, con su fecha de terminación real, y **liquidar**, con su fecha de liquidación y su valor liquidado, desde botones propios del contrato. Cada acción cambia el estado y guarda los datos de una vez, con historial. Los contratos terminados o liquidados sin sus datos muestran un aviso. El valor vigente, la fecha fin, el avance físico, el financiero y la cadena presupuestal **no cambian**. Suspensión y reinicio (7B) e informes de supervisión (7C) quedan para planes aparte.

**Arquitectura:**
- **Migración 24:** `2026_11_19_100023_add_liquidacion_a_contratos` agrega a `contratos` tres columnas nullable, cada una con su guarda: `fecha_terminacion_real` (date), `fecha_liquidacion` (date) y `valor_liquidado` (decimal(15,2), como `valor`). El `down()` se detiene si alguna tiene datos.
- **Cierre (API):**
  - el servicio `App\Services\Contratacion\CierreContratos` (`terminar()`, `liquidar()` y `aviso()`), que devuelve un `CierreGuardado` (el contrato presentado y sus avisos);
  - las rutas `POST /contratos/{contrato}/terminar` y `POST /contratos/{contrato}/liquidar`, en `ContratoController`, bajo `can:editar-datos`;
  - control de versión con `actualizado` (409), bloqueo de la fila e historial `contrato_terminado` / `contrato_liquidado` en `checklist_historial`.
- **Contratos (API):**
  - `ContratosService::editar` rechaza pasar a terminado o a liquidado desde otro estado y valida la fecha de inicio contra la de terminación real;
  - `presentar()`, la búsqueda, el detalle, `listarContratos` y GET /proyectos traen los tres datos y `aviso_cierre`, sin consultas por fila;
  - el historial de un contrato eliminado conserva los datos de cierre.
- **Pantallas:**
  - en `ContratoEditor.jsx`, los botones "Terminar" / "Corregir terminación" y "Liquidar" / "Corregir liquidación", que abren los diálogos de `CierreContrato.jsx`;
  - el bloque "Cierre" de solo lectura y el select de estado con terminado y liquidado deshabilitados;
  - "Actualizar desde SECOP II" sin aplicar esos estados a un contrato guardado;
  - el chip del aviso en el módulo Contratos, en la lista de Parámetros y en la ficha del proyecto;
  - las utilidades puras, en `utils/contratos.js` y `utils/secop.js`, con pruebas Vitest.

**Tecnologías:** Laravel 12, PHP 8.2, MariaDB 10.4, React 18 con Laravel Mix y react-router, PHPUnit sobre SQLite en memoria, Vitest.

**Fuente:**
- La propuesta `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, aprobada:
  - fase 7: "Ejecución ampliada y cierre". Se hace por orden y a pedido: primero terminación y liquidación (fechas y valor). Cada pieza se entrega por separado y no altera el avance físico ni el financiero;
  - sección 3: los estados `terminado` y `liquidado`, con fecha y valor de liquidación;
  - sección 6.2: `fecha_terminacion_real`, `fecha_liquidacion`, `valor_liquidado`;
  - reglas R6 (estados del contrato), R13 (ningún avance se suma ni se promedia), R15 (los cambios de estado y fechas del contrato quedan en `checklist_historial`) y R16 (indicadores solo con contratos).
- Decisiones del usuario (2026-11-19), abajo.

**Estado de partida:** rama `feature/contratacion`, igual a `main` (`8c0101d`). La fase 6 y el menú de catálogos están integrados. Pasan 1313 pruebas PHP y 859 JS.

**Datos reales comprobados hoy en `gest_pro`:**
- **Contratos:** 7. Seis están `en_ejecucion` y uno, 00028 (id 5), está `liquidado` sin fechas ni valor de cierre.
- **Procesos:** 3. **Expedientes:** 14.

Las tres columnas nacen vacías y ninguna respuesta existente cambia de valor: solo ganan claves nuevas.

---

## Reglas de ejecución

- **Nada de esto se aplica a `gest_pro` sin autorización del usuario.** Allí nunca se usa `migrate:rollback`: si hay que volver atrás, se restaura el respaldo. La tarea 7 aplica la migración 24, y solo con un sí explícito.
- **La viabilización no cambia ni una respuesta ni una cifra.** Las pruebas existentes de `tests/Feature/Viabilizacion/**`, los fixtures de paridad (`tests/Fixtures/paridad_*`) y `ExpedientesContratacionAisladosTest` no se modifican y deben pasar.
- **Pruebas existentes que cambian:**
  - **PHP:** solo dos de contratos, que ganan las claves nuevas: `ContratosApiTest` (la respuesta exacta de `presentar()`) y `ContratosBusquedaApiTest` (las claves de la fila de búsqueda).
  - **JS:** `contratos.test.js` y `secop.test.js` solo ganan casos.
- Commits autorizados solo en `feature/contratacion`. Sin push. Mensajes en inglés, terminados con la línea `Co-Authored-By` del modelo que comitea.
- Nomenclatura en español sin tildes para columnas, clases y métodos nuevos. Los textos para el usuario van en español con tuteo. Las migraciones, que son herramientas del operador, conservan el registro de usted.
- **"Hoy" en la zona `config('contratacion.zona')` (`America/Bogota`)**, con `AvisosContratacion::hoy()` en PHP y `fechaHoyBogota()` en JS. Fechas `Y-m-d`.
- Pruebas: `php artisan test` sin definir `DB_DATABASE`, para que use SQLite en memoria, y `npm test`. Si Vitest crea `resources/js/node_modules`, se borra.
- **Aplicar los bloques exactos.** Cada reemplazo aparece una sola vez en su archivo. Si no lo encuentras tal cual, detente y avisa. Se recomienda aplicarlos con un script que los lea del plan y exija una coincidencia por reemplazo.
- **No usar `git stash`, `git clean` ni `git checkout -- .`:** el usuario tiene stashes y cambios propios.
- El usuario suele tener `npm run watch` corriendo, que reescribe `public/`. Solo la tarea 4 comitea `public/`, después de `npm run production`.
- No tocar ni comitear `public/images/marca-completa.png` ni `public/images/marca-completa1.png`: son cambios del usuario.
- `.env` está versionado: nunca se agrega a un commit. Comitear siempre con rutas explícitas.
- `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio. Hay que definirlo en cada comando, porque el estado de la terminal no se conserva.
- **MariaDB no hace transaccional el DDL.** Por eso cada columna de la migración 24 lleva su guarda `hasColumn` y se puede reintentar.
- **El prefijo de la API (`/GestPro`) es también la base del SPA.** Las rutas nuevas son POST y no coinciden con ninguna ruta del SPA. No se agrega ninguna ruta GET.

## Decisiones de este plan

**Decisiones del usuario (2026-11-19)**
1. **Solo la 7A:** terminación y liquidación. Suspensión y reinicio (7B) e informes de supervisión (7C) van en planes aparte.
2. **Acciones propias:** botones "Terminar" y "Liquidar" en el contrato, cada uno con su diálogo. Cambian el estado y guardan los datos de una vez, con historial.
3. **Obligatorios al cambiar:**
   - terminar pide la fecha de terminación real;
   - liquidar pide la fecha de liquidación y el valor liquidado, y la de terminación si falta;
   - el contrato que ya está liquidado en `gest_pro` (00028) queda como está, con el aviso "Faltan datos de liquidación".

**Datos**
- `down()` se detiene con "No se puede revertir: hay contratos con fecha de terminación, fecha de liquidación o valor liquidado registrados, que se perderían. Restaure un respaldo." si alguna fila tiene alguno de los tres. Si no, quita las columnas, cada una con su guarda.

**Terminar (`POST /contratos/{contrato}/terminar`)**
- Permiso `can:editar-datos`: Consulta recibe 403 y sin token, 401. Un contrato que no existe responde 404.
- Cuerpo `{fecha_terminacion_real, observacion?, actualizado}`. La fecha es obligatoria (`Y-m-d`) y la observación, de hasta 2000 caracteres.
- Estados:
  - se termina un contrato `celebrado`, `en_ejecucion`, `suspendido` o `terminado` (corrige la fecha);
  - `liquidado` responde 422 "El contrato está liquidado: corrige la terminación desde «Liquidar».";
  - `anulado` responde 422 "El contrato está anulado.";
  - un estado vacío o fuera de la lista responde 422 "Elige primero un estado válido para el contrato.".
- Fechas, con 422 en `fecha_terminacion_real`:
  - no posterior a hoy: "La fecha de terminación no puede ser posterior a hoy.";
  - no anterior a `fecha_inicio`: "La fecha de terminación no puede ser anterior a la de inicio.";
  - no posterior a una `fecha_liquidacion` que el contrato conserve: "La fecha de terminación no puede ser posterior a la de liquidación.".
- Guarda estado `terminado` y la fecha, adelanta `updated_at` y registra `contrato_terminado`:
  - `antes`: `{estado, fecha_terminacion_real}`;
  - `despues`: lo mismo más la observación.
- Responde `{success, contrato: presentar(), avisos: []}`.

**Liquidar (`POST /contratos/{contrato}/liquidar`)**
- Cuerpo `{fecha_terminacion_real?, fecha_liquidacion, valor_liquidado, observacion?, actualizado}`. El valor es numérico, mayor o igual a 0, con dos decimales.
- Se liquida cualquier estado salvo `anulado`. Desde `liquidado`, completa o corrige los datos.
- **Fecha de terminación:** la que llega o, si no llega, la que ya tiene. Si no hay ninguna, 422 "Escribe la fecha de terminación real.". Sigue las mismas reglas que al terminar.
- **Fecha de liquidación:** no posterior a hoy ("La fecha de liquidación no puede ser posterior a hoy.") ni anterior a la de terminación ("La fecha de liquidación no puede ser anterior a la de terminación.").
- Guarda estado `liquidado` y los tres datos, adelanta `updated_at` y registra `contrato_liquidado`, con antes y después de los cuatro campos más la observación.
- **Aviso que no impide guardar:** si el valor liquidado supera el valor vigente, `avisos` trae "El valor liquidado supera el valor vigente del contrato.".

**Comunes**
- **Versión:** primero se comprueba la versión (`actualizado`), y si es vieja responde 409 con "Otra persona cambió este contrato mientras lo editabas…". Después, el estado y las fechas.
- **Sin cambios:** con el mismo estado, los mismos datos y sin observación no se guarda nada (ni versión nueva ni historial). Responde 200 con el contrato y sus avisos.

**Editar y crear**
- **Editar** (`guardarContrato` con id):
  - pasar el estado **a** `terminado` o `liquidado` desde otro estado responde 422 en `estado`: "Para terminar o liquidar el contrato usa «Terminar» o «Liquidar».";
  - salir de esos estados por el formulario se permite y conserva los datos de cierre. El formulario no envía ni cambia esos datos;
  - una `fecha_inicio` posterior a la terminación real responde 422 en `fecha_inicio`.
- **Crear:** acepta cualquier estado, por ejemplo un contrato de SECOP II que ya está liquidado. Queda con el aviso.

**Aviso `aviso_cierre`**
- Vale null, o `{faltan: [...]}` con los nombres de columna que faltan, en orden:
  - en `terminado`, la fecha de terminación;
  - en `liquidado`, las tres que falten.
- Va en `presentar()`, la búsqueda, el detalle, `listarContratos` y los contratos de GET /proyectos.

**Pantallas**
- **Botones** (solo para quien puede editar y en un contrato guardado):
  - "Terminar" en celebrado, en ejecución y suspendido, y "Corregir terminación" en terminado;
  - "Liquidar" en cualquier estado salvo anulado, y "Corregir liquidación" en liquidado;
  - con cambios sin guardar quedan con `aria-disabled` y la nota "Guarda o descarta tus cambios primero";
  - un contrato guardado sin estado válido muestra "Elige un estado para el contrato y guárdalo para poder terminarlo.".
- **Diálogos:**
  - **Terminar:** fecha y observación;
  - **Liquidar:** fecha de terminación (precargada), fecha de liquidación, valor (con el valor vigente debajo y el saldo en vivo, en ámbar si es negativo) y observación;
  - los errores del 422 aparecen bajo cada campo;
  - al guardar, "Contrato terminado", "Contrato liquidado" o "…, con avisos";
  - un 409 recarga y muestra "No se guardó el cierre".
- **Bloque "Cierre"** en la información del contrato: las dos fechas, el valor liquidado, el saldo (vigente − liquidado) y el aviso.
- **Select de estado:** "Terminado (usa «Terminar»)" y "Liquidado (usa «Liquidar»)" deshabilitados, salvo que sea el estado guardado o el contrato sea nuevo.
- **SECOP II:** si propone terminado o liquidado para un contrato guardado que no lo está, no se aplica el estado y aparece "SECOP II dice «Liquidado»: usa «Liquidar» para registrarlo con sus fechas y valor." (o el de «Terminado»).
- **Chip:** "Faltan datos de liquidación" o "Falta la fecha de terminación", en el módulo Contratos, la lista de Parámetros y la ficha del proyecto.
- **Lista de Parámetros:** se pide al abrirla, y el editor la recarga al terminar o liquidar.

## Fuera de esta etapa

- **7B:** suspensión y reinicio sobre `modificaciones_contrato`.
- **7C:** informes de supervisión.
- Pagos y obligaciones, solo si se confirman.

## Lo que ya se ensayó

El plan se ensayó completo en un clon descartable fuera del repositorio, y este documento se generó con el código de ese ensayo. Un script comprobó que aplicar sus bloques sobre la rama reproduce el ensayo exactamente.

- **Pruebas:** con el código de las tareas 2 y 3 pasan 1346 pruebas PHP y 918 JS. Cada tarea también pasa sus suites completas. Solo cambiaron dos pruebas PHP existentes de contratos, por las claves nuevas; ninguna de viabilización ni fixture de paridad.
- **En una copia de `gest_pro` con los datos reales** (14 expedientes, 7 contratos, 3 procesos):
  - migrar, revertir y volver a migrar dio `SIN DIFERENCIAS` en las seis comparaciones;
  - las tres columnas nacieron vacías;
  - con datos de cierre, revertir se detuvo con su mensaje y las columnas siguieron ahí.
- **En el navegador, sobre esa copia,** con un Administrador y un usuario Consulta de prueba creados solo en la copia, el 2026-11-19:
  - **Listas:** el contrato 00028 (liquidado, sin datos) mostró "Faltan datos de liquidación" en `/contratos`, en la lista de contratos de Parámetros y en la ficha `/proyectos/1`.
  - **Completar la liquidación de 00028:** una fecha de liquidación futura se rechazó bajo el campo. Con terminación 10/07/2026, liquidación 15/11/2026 y 6.950.000.000 guardó. El bloque "Cierre" mostró el saldo $ 50.000.000 y el chip desapareció. Guardar el contrato después, sin cambios, no dio 409.
  - **Terminar y liquidar C-TEST-001:**
    - con un cambio sin guardar, los dos botones quedaron deshabilitados con "Guarda o descarta tus cambios primero";
    - "Terminar" con la fecha de hoy funcionó y el botón pasó a "Corregir terminación";
    - "Liquidar" con 5.100.000.000 mostró el saldo negativo en ámbar y guardó con el aviso "El valor liquidado supera el valor vigente del contrato.".
  - **Select de estado:** "Terminado (usa «Terminar»)" y "Liquidado (usa «Liquidar»)" aparecieron deshabilitados.
  - **409:** con el diálogo abierto, otra liquidación por API; la pantalla dijo "No se guardó el cierre" y se conservó el cambio de la API.
  - **Parámetros:** se terminó un contrato desde la lista del proyecto, se cerró y se volvió a abrir. La lista mostró "Terminado" y guardar el contrato no dio 409.
  - **Consulta:** vio el bloque "Cierre" sin botones.
  - **Base:** valor, valor inicial, fechas fin y avances quedaron como antes. El historial registró `contrato_terminado` y `contrato_liquidado` con antes, después y la observación.
- **Una revisión independiente** encontró 1 hallazgo alto, 1 medio y 8 bajos. Todos quedaron corregidos en las tareas correspondientes, salvo dos bajos aceptados:
  - **Alto:** la lista de contratos de Parámetros no traía los datos de cierre y quedaba con una versión vieja tras terminar, lo que daba 409. Ahora GET /proyectos los trae, la lista se pide al abrirla y el editor avisa al terminar o liquidar (verificado en el navegador).
  - **Medio:** "Actualizar desde SECOP II" copiaba «liquidado» a un contrato guardado sin estado. Ahora decide por si el contrato está guardado.
  - **Bajos corregidos:**
    - la terminación no puede ser posterior a una liquidación conservada;
    - la fecha de inicio no puede ser posterior a la de terminación real;
    - terminar o liquidar sin cambios ni observación no guarda nada;
    - el historial de un contrato eliminado conserva los datos de cierre;
    - una nota explica por qué un contrato sin estado no se puede terminar;
    - los botones deshabilitados usan `aria-disabled` y siguen enfocables;
    - la ficha del proyecto muestra el chip;
    - una recarga fallida ya no deja una promesa sin manejar.
  - **Bajos aceptados:**
    - un id inexistente con datos inválidos responde 422 y no 404, como `guardarContrato`;
    - si se abre un contrato en Parámetros antes de que llegue la lista, el editor usa la fila del proyecto y podría dar 409.

---

### Tarea 1: Punto de partida y fotos con el código actual

Las fotos se toman **antes de cambiar código**. La copia `gestpro_verif_tmp` se conserva hasta la tarea 5.

- [ ] **Paso 1: Comprobar la rama, el estado y las pruebas**

Run: `git branch --show-current`
Expected: `feature/contratacion`

Run: `git status --short`
Expected: como mucho, `public/` (si corre `npm run watch`) y las dos imágenes del usuario.

Run: `php artisan test`
Expected: pasan todas, 1313. Si alguna falla, detenerse y avisar.

Run: `npm test`
Expected: pasan todas, 859.

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
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c11_antes_$f.json" gestpro_verif_tmp; DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c11_tablas_antes_$f.json" gestpro_verif_tmp --solo-tablas; done
```

Expected: `Expedientes: 14, filas: 331, plantillas: 5, requisitos: 241` y `Contratos: 7, procesos: 3, actas: 2, expedientes: 14`, con las cifras actuales de `gest_pro`.

---

### Tarea 2: API para terminar y liquidar un contrato (migración 24)

La migración 24 con las tres columnas de cierre; el servicio `CierreContratos` (con `CierreGuardado`) y las rutas `POST /contratos/{contrato}/terminar` y `/liquidar` en `ContratoController`, con versión, bloqueo e historial; en `ContratosService`, la regla de editar (no pasar a terminado ni a liquidado por el formulario; la fecha de inicio no posterior a la de terminación), los datos de cierre y `aviso_cierre` en `presentar()`, la búsqueda, el detalle y `listarContratos`, y los datos de cierre en el historial de un contrato eliminado; GET /proyectos trae lo mismo en sus contratos. Valor, fecha fin y avances no cambian.

**Files:**
- Create: `app/Services/Contratacion/CierreContratos.php`
- Create: `app/Services/Contratacion/CierreGuardado.php`
- Create: `database/migrations/2026_11_19_100023_add_liquidacion_a_contratos.php`
- Test (create): `tests/Feature/Contratacion/CierreContratosApiTest.php`
- Modify: `app/Http/Controllers/ContratoController.php`
- Modify: `app/Http/Controllers/ProyectoController.php`
- Modify: `app/Models/Contrato.php`
- Modify: `app/Services/Contratacion/ContratosService.php`
- Modify: `routes/contratacion.php`
- Test: `tests/Feature/Contratacion/ContratosApiTest.php`
- Test: `tests/Feature/Contratacion/ContratosBusquedaApiTest.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/CierreContratosApiTest.php`:

```php
<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\Contrato;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Contratacion\ContratosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Fase 7A: terminar y liquidar un contrato (POST /contratos/{id}/terminar y /liquidar), la regla de
 * editar que no deja pasar a terminado o liquidado por el formulario, el aviso de datos de cierre
 * faltantes en los listados y la migración 24. Hoy es el 19 de noviembre de 2026 (en Bogotá) salvo
 * que la prueba diga otra cosa.
 */
class CierreContratosApiTest extends TestCase
{
    use RefreshDatabase;

    private const HOY = '2026-11-19';

    private Proyecto $proyecto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(self::HOY . ' 10:00:00');
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
    }

    private function como(Rol|User $quien = Rol::Gestor): static
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
            'valor' => 1200000,
            'fecha_inicio' => '2026-02-01',
            'fecha_fin_inicial' => '2026-10-31',
            'fecha_fin' => '2026-11-30',
            'avance_fisico' => 80,
            'avance_financiero' => 60,
            'estado' => Contrato::EN_EJECUCION,
            ...$mas,
        ]);
    }

    private function version(Contrato $contrato): ?string
    {
        return $contrato->fresh()->updated_at?->format('Y-m-d H:i:s');
    }

    private function terminar(Contrato $contrato, array $datos = [], Rol|User $quien = Rol::Gestor)
    {
        return $this->como($quien)->postJson("/GestPro/contratos/{$contrato->id}/terminar", [
            'fecha_terminacion_real' => '2026-11-15',
            'actualizado' => $this->version($contrato),
            ...$datos,
        ]);
    }

    private function liquidar(Contrato $contrato, array $datos = [], Rol|User $quien = Rol::Gestor)
    {
        return $this->como($quien)->postJson("/GestPro/contratos/{$contrato->id}/liquidar", [
            'fecha_liquidacion' => '2026-11-18',
            'valor_liquidado' => 1150000.5,
            'actualizado' => $this->version($contrato),
            ...$datos,
        ]);
    }

    /** @return list<array{0: string, 1: ?array, 2: ?array}> */
    private function eventos(Contrato $contrato): array
    {
        return DB::table('checklist_historial')
            ->where('entidad_tipo', 'contrato')
            ->where('entidad_id', $contrato->id)
            ->orderBy('id')->get()
            ->map(fn ($h) => [$h->evento, json_decode((string) $h->antes, true), json_decode((string) $h->despues, true)])
            ->all();
    }

    /** Formulario de edición completo del contrato (como lo manda la pantalla). */
    private function editar(Contrato $contrato, array $mas = [])
    {
        $c = $contrato->fresh();

        return $this->como()->postJson('/GestPro/guardarContrato', ['formContrato' => [
            'id' => $c->id,
            'actualizado' => $this->version($c),
            'n_contrato' => $c->n_contrato,
            'objeto' => $c->objeto,
            'valor_inicial' => $c->valor_inicial,
            'estado' => $c->estado,
            'proyecto' => $c->proyecto,
            ...$mas,
        ], 'anexos' => null]);
    }

    // ------------------------------------------------------------------ permisos

    public function test_sin_token_responde_401_y_consulta_403(): void
    {
        $c = $this->contrato();

        $this->postJson("/GestPro/contratos/{$c->id}/terminar", ['fecha_terminacion_real' => '2026-11-15', 'actualizado' => null])->assertUnauthorized();
        $this->postJson("/GestPro/contratos/{$c->id}/liquidar", ['fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => 1, 'actualizado' => null])->assertUnauthorized();
        $this->terminar($c, [], Rol::Consulta)->assertForbidden();
        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-15'], Rol::Consulta)->assertForbidden();

        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);
        $this->assertSame([], $this->eventos($c));
    }

    public function test_un_contrato_que_no_existe_responde_404(): void
    {
        $this->como()->postJson('/GestPro/contratos/9999/terminar', ['fecha_terminacion_real' => '2026-11-15', 'actualizado' => null])
            ->assertNotFound()->assertExactJson(['mensaje' => 'El contrato no existe.']);
        $this->como()->postJson('/GestPro/contratos/9999/liquidar', ['fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => 1, 'actualizado' => null])
            ->assertNotFound()->assertExactJson(['mensaje' => 'El contrato no existe.']);
    }

    // ------------------------------------------------------------------ terminar

    public static function estadosQueTerminan(): array
    {
        return [
            'celebrado' => [Contrato::CELEBRADO],
            'en ejecución' => [Contrato::EN_EJECUCION],
            'suspendido' => [Contrato::SUSPENDIDO],
            'terminado (corrige la fecha)' => [Contrato::TERMINADO],
        ];
    }

    #[DataProvider('estadosQueTerminan')]
    public function test_termina_desde_cada_estado_permitido(string $estado): void
    {
        $c = $this->contrato(['estado' => $estado, 'fecha_terminacion_real' => $estado === Contrato::TERMINADO ? '2026-11-01' : null]);

        $r = $this->terminar($c, ['fecha_terminacion_real' => '2026-11-15'])->assertOk();

        $this->assertSame(['success', 'contrato', 'avisos'], array_keys($r->json()));
        $r->assertJsonPath('contrato.id', $c->id)
            ->assertJsonPath('contrato.estado', Contrato::TERMINADO)
            ->assertJsonPath('contrato.fecha_terminacion_real', '2026-11-15')
            ->assertJsonPath('contrato.fecha_liquidacion', null)
            ->assertJsonPath('contrato.valor_liquidado', null)
            ->assertJsonPath('contrato.aviso_cierre', null)
            ->assertJsonPath('avisos', []);
        $fila = $c->fresh();
        $this->assertSame(Contrato::TERMINADO, $fila->estado);
        $this->assertSame('2026-11-15', $fila->fecha_terminacion_real->format('Y-m-d'));
    }

    public function test_no_termina_un_contrato_liquidado_ni_uno_anulado(): void
    {
        $liquidado = $this->contrato(['estado' => Contrato::LIQUIDADO]);
        $anulado = $this->contrato(['n_contrato' => 'C-002', 'estado' => Contrato::ANULADO]);

        $this->terminar($liquidado)->assertUnprocessable()
            ->assertJsonPath('mensaje', 'El contrato está liquidado: corrige la terminación desde «Liquidar».');
        $this->terminar($anulado)->assertUnprocessable()->assertJsonPath('mensaje', 'El contrato está anulado.');

        $this->assertNull($liquidado->fresh()->fecha_terminacion_real);
        $this->assertSame(Contrato::ANULADO, $anulado->fresh()->estado);
        $this->assertSame([], $this->eventos($liquidado));
    }

    public function test_la_fecha_de_terminacion_es_obligatoria_y_con_formato(): void
    {
        $c = $this->contrato();

        $this->terminar($c, ['fecha_terminacion_real' => null])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_terminacion_real.0', 'Escribe la fecha de terminación real.');
        $this->terminar($c, ['fecha_terminacion_real' => '15/11/2026'])->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_terminacion_real');
        $this->terminar($c, ['observacion' => str_repeat('a', 2001)])->assertUnprocessable()
            ->assertJsonValidationErrors('observacion');
        $this->como()->postJson("/GestPro/contratos/{$c->id}/terminar", ['fecha_terminacion_real' => '2026-11-15'])
            ->assertUnprocessable()->assertJsonValidationErrors('actualizado');
    }

    public function test_la_fecha_de_terminacion_no_es_posterior_a_hoy_en_bogota(): void
    {
        // 03:00 del 20 en UTC son las 22:00 del 19 en Bogotá: hoy es el 19.
        $this->travelTo('2026-11-20 03:00:00');
        $c = $this->contrato();

        $this->terminar($c, ['fecha_terminacion_real' => '2026-11-20'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_terminacion_real.0', 'La fecha de terminación no puede ser posterior a hoy.');
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);

        $this->terminar($c, ['fecha_terminacion_real' => '2026-11-19'])->assertOk();
    }

    public function test_la_fecha_de_terminacion_no_es_anterior_a_la_de_inicio(): void
    {
        $c = $this->contrato(['fecha_inicio' => '2026-02-01']);

        $this->terminar($c, ['fecha_terminacion_real' => '2026-01-31'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_terminacion_real.0', 'La fecha de terminación no puede ser anterior a la de inicio.');
        $this->terminar($c, ['fecha_terminacion_real' => '2026-02-01'])->assertOk();

        // Sin fecha de inicio, no hay límite inferior.
        $sinInicio = $this->contrato(['n_contrato' => 'C-002', 'fecha_inicio' => null]);
        $this->terminar($sinInicio, ['fecha_terminacion_real' => '2020-01-01'])->assertOk();
    }

    public function test_terminar_con_una_version_vieja_responde_409_y_adelanta_la_version(): void
    {
        $c = $this->contrato();
        $vieja = $this->version($c);

        $this->terminar($c, ['actualizado' => '2020-01-01 00:00:00'])->assertStatus(409)
            ->assertExactJson(['mensaje' => ContratosService::DESACTUALIZADO]);
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);

        // En el mismo segundo: la versión igual cambia.
        $r = $this->terminar($c)->assertOk();
        $this->assertNotSame($vieja, $r->json('contrato.updated_at'));
        $this->assertSame($r->json('contrato.updated_at'), $this->version($c));
        $this->terminar($c, ['actualizado' => $vieja])->assertStatus(409);
    }

    public function test_terminar_deja_historial_con_antes_y_despues(): void
    {
        $c = $this->contrato();

        $this->terminar($c, ['fecha_terminacion_real' => '2026-11-15', 'observacion' => '  Acta de terminación  '])->assertOk();
        $this->terminar($c->fresh(), ['fecha_terminacion_real' => '2026-11-16'])->assertOk();

        $this->assertSame([
            ['contrato_terminado',
                ['estado' => 'en_ejecucion', 'fecha_terminacion_real' => null],
                ['estado' => 'terminado', 'fecha_terminacion_real' => '2026-11-15', 'observacion' => 'Acta de terminación']],
            ['contrato_terminado',
                ['estado' => 'terminado', 'fecha_terminacion_real' => '2026-11-15'],
                ['estado' => 'terminado', 'fecha_terminacion_real' => '2026-11-16', 'observacion' => null]],
        ], $this->eventos($c));
    }

    // ------------------------------------------------------------------ liquidar

    public function test_liquida_un_contrato_terminado_con_la_fecha_que_ya_tiene(): void
    {
        $c = $this->contrato(['estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => '2026-11-10']);

        $r = $this->liquidar($c, ['fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => '1150000.5'])->assertOk();

        $this->assertSame(['success', 'contrato', 'avisos'], array_keys($r->json()));
        $r->assertJsonPath('contrato.estado', Contrato::LIQUIDADO)
            ->assertJsonPath('contrato.fecha_terminacion_real', '2026-11-10')
            ->assertJsonPath('contrato.fecha_liquidacion', '2026-11-18')
            ->assertJsonPath('contrato.valor_liquidado', '1150000.50')
            ->assertJsonPath('contrato.aviso_cierre', null)
            ->assertJsonPath('avisos', []);
        $fila = $c->fresh();
        $this->assertSame(Contrato::LIQUIDADO, $fila->estado);
        $this->assertSame('1150000.50', $fila->valor_liquidado);
    }

    public function test_liquidar_sin_fecha_de_terminacion_la_pide(): void
    {
        $c = $this->contrato();

        $this->liquidar($c)->assertUnprocessable()
            ->assertJsonPath('errors.fecha_terminacion_real.0', 'Escribe la fecha de terminación real.');
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);

        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-12'])->assertOk()
            ->assertJsonPath('contrato.estado', Contrato::LIQUIDADO)
            ->assertJsonPath('contrato.fecha_terminacion_real', '2026-11-12');
    }

    public function test_liquidar_un_liquidado_completa_y_corrige_sus_datos(): void
    {
        // Como el contrato 5 de gest_pro: liquidado sin datos de cierre.
        $c = $this->contrato(['estado' => Contrato::LIQUIDADO]);

        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-01', 'fecha_liquidacion' => '2026-11-05', 'valor_liquidado' => 1000000])->assertOk()
            ->assertJsonPath('contrato.aviso_cierre', null);
        $this->liquidar($c->fresh(), ['fecha_liquidacion' => '2026-11-06', 'valor_liquidado' => 999999.99])->assertOk()
            ->assertJsonPath('contrato.fecha_terminacion_real', '2026-11-01')
            ->assertJsonPath('contrato.fecha_liquidacion', '2026-11-06')
            ->assertJsonPath('contrato.valor_liquidado', '999999.99');

        $eventos = $this->eventos($c);
        $this->assertSame(['contrato_liquidado', 'contrato_liquidado'], array_column($eventos, 0));
        $this->assertSame(
            ['estado' => 'liquidado', 'fecha_terminacion_real' => '2026-11-01', 'fecha_liquidacion' => '2026-11-05', 'valor_liquidado' => '1000000.00'],
            $eventos[1][1]
        );
    }

    public function test_no_liquida_un_contrato_anulado(): void
    {
        $c = $this->contrato(['estado' => Contrato::ANULADO]);

        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-01'])->assertUnprocessable()
            ->assertJsonPath('mensaje', 'El contrato está anulado.');
        $this->assertNull($c->fresh()->fecha_liquidacion);
    }

    public function test_las_fechas_de_liquidar(): void
    {
        $c = $this->contrato();

        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-15', 'fecha_liquidacion' => '2026-11-14'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_liquidacion.0', 'La fecha de liquidación no puede ser anterior a la de terminación.');
        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-15', 'fecha_liquidacion' => '2026-11-20'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_liquidacion.0', 'La fecha de liquidación no puede ser posterior a hoy.');
        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-20', 'fecha_liquidacion' => '2026-11-19'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_terminacion_real.0', 'La fecha de terminación no puede ser posterior a hoy.');
        $this->liquidar($c, ['fecha_terminacion_real' => '2026-01-15'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_terminacion_real.0', 'La fecha de terminación no puede ser anterior a la de inicio.');
        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-15', 'fecha_liquidacion' => null])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_liquidacion.0', 'Escribe la fecha de liquidación.');
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);

        // El mismo día de la terminación vale.
        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-15', 'fecha_liquidacion' => '2026-11-15'])->assertOk();
    }

    public function test_el_valor_liquidado_es_obligatorio_no_negativo_y_con_dos_decimales(): void
    {
        $c = $this->contrato(['estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => '2026-11-10']);

        $this->liquidar($c, ['valor_liquidado' => -1])->assertUnprocessable()
            ->assertJsonPath('errors.valor_liquidado.0', 'El valor liquidado no puede ser negativo.');
        $this->liquidar($c, ['valor_liquidado' => null])->assertUnprocessable()
            ->assertJsonPath('errors.valor_liquidado.0', 'Escribe el valor liquidado.');
        $this->liquidar($c, ['valor_liquidado' => '10.123'])->assertUnprocessable()
            ->assertJsonValidationErrors('valor_liquidado');
        $this->liquidar($c, ['valor_liquidado' => 'mil'])->assertUnprocessable()
            ->assertJsonValidationErrors('valor_liquidado');
        $this->liquidar($c, ['valor_liquidado' => '12345678901234'])->assertUnprocessable()
            ->assertJsonValidationErrors('valor_liquidado');
        $this->assertSame(Contrato::TERMINADO, $c->fresh()->estado);

        $this->liquidar($c, ['valor_liquidado' => 0])->assertOk()->assertJsonPath('contrato.valor_liquidado', '0.00');
    }

    public function test_avisa_si_el_valor_liquidado_supera_el_vigente(): void
    {
        $c = $this->contrato(['estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => '2026-11-10', 'valor' => 1200000]);

        $this->liquidar($c, ['valor_liquidado' => '1200000.01'])->assertOk()
            ->assertJsonPath('avisos', ['El valor liquidado supera el valor vigente del contrato.'])
            ->assertJsonPath('contrato.estado', Contrato::LIQUIDADO);
        $this->liquidar($c->fresh(), ['valor_liquidado' => 1200000])->assertOk()->assertJsonPath('avisos', []);
    }

    public function test_liquidar_con_una_version_vieja_responde_409(): void
    {
        $c = $this->contrato(['estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => '2026-11-10']);

        $this->liquidar($c, ['actualizado' => '2020-01-01 00:00:00'])->assertStatus(409)
            ->assertExactJson(['mensaje' => ContratosService::DESACTUALIZADO]);
        $this->assertSame(Contrato::TERMINADO, $c->fresh()->estado);
    }

    public function test_liquidar_deja_historial_con_antes_y_despues(): void
    {
        $c = $this->contrato();

        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-12', 'observacion' => 'Acta de liquidación bilateral'])->assertOk();

        $this->assertSame([
            ['contrato_liquidado',
                ['estado' => 'en_ejecucion', 'fecha_terminacion_real' => null, 'fecha_liquidacion' => null, 'valor_liquidado' => null],
                ['estado' => 'liquidado', 'fecha_terminacion_real' => '2026-11-12', 'fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => '1150000.50', 'observacion' => 'Acta de liquidación bilateral']],
        ], $this->eventos($c));
    }

    public function test_terminar_y_liquidar_no_cambian_valores_fechas_fin_ni_avances(): void
    {
        $c = $this->contrato();
        $campos = ['valor', 'valor_inicial', 'fecha_fin', 'fecha_fin_inicial', 'avance_fisico', 'avance_financiero'];
        $antes = (array) DB::table('contratos')->where('id', $c->id)->first($campos);

        $this->terminar($c)->assertOk();
        $this->assertEquals($antes, (array) DB::table('contratos')->where('id', $c->id)->first($campos));
        $this->liquidar($c->fresh(), ['valor_liquidado' => 5])->assertOk();
        $this->assertEquals($antes, (array) DB::table('contratos')->where('id', $c->id)->first($campos));
    }

    // ------------------------------------------------------------------ editar y crear

    public function test_editar_no_pasa_a_terminado_ni_a_liquidado(): void
    {
        $c = $this->contrato();
        $terminado = $this->contrato(['n_contrato' => 'C-002', 'estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => '2026-11-10']);

        foreach ([Contrato::TERMINADO, Contrato::LIQUIDADO] as $estado) {
            $this->editar($c, ['estado' => $estado])->assertUnprocessable()
                ->assertJsonPath('errors.estado.0', 'Para terminar o liquidar el contrato usa «Terminar» o «Liquidar».');
        }
        $this->editar($terminado, ['estado' => Contrato::LIQUIDADO])->assertUnprocessable()
            ->assertJsonValidationErrors('estado');
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);
        $this->assertSame(Contrato::TERMINADO, $terminado->fresh()->estado);

        // Quedarse en el mismo estado sí: se editan otros campos.
        $this->editar($terminado, ['objeto' => 'Objeto corregido'])->assertOk()->assertJsonPath('contrato.estado', Contrato::TERMINADO);
    }

    public function test_editar_saca_de_liquidado_y_conserva_los_datos_de_cierre(): void
    {
        $c = $this->contrato(['estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => '2026-11-10']);
        $this->liquidar($c)->assertOk();

        // El formulario no escribe los datos de cierre aunque los mande.
        $this->editar($c, ['estado' => Contrato::EN_EJECUCION, 'fecha_liquidacion' => null, 'valor_liquidado' => 1, 'fecha_terminacion_real' => null])
            ->assertOk()
            ->assertJsonPath('contrato.estado', Contrato::EN_EJECUCION)
            ->assertJsonPath('contrato.fecha_terminacion_real', '2026-11-10')
            ->assertJsonPath('contrato.fecha_liquidacion', '2026-11-18')
            ->assertJsonPath('contrato.valor_liquidado', '1150000.50')
            ->assertJsonPath('contrato.aviso_cierre', null);

        $fila = $c->fresh();
        $this->assertSame('2026-11-18', $fila->fecha_liquidacion->format('Y-m-d'));
        $this->assertSame('1150000.50', $fila->valor_liquidado);
    }

    public function test_crear_un_contrato_liquidado_queda_con_el_aviso(): void
    {
        $r = $this->como()->postJson('/GestPro/guardarContrato', ['formContrato' => [
            'n_contrato' => 'C-SECOP',
            'objeto' => 'Contrato que ya estaba liquidado en SECOP II',
            'valor_inicial' => 500000,
            'estado' => Contrato::LIQUIDADO,
            'proyecto' => $this->proyecto->id,
            'fecha_liquidacion' => '2026-01-01',
        ], 'anexos' => []])->assertOk();

        $r->assertJsonPath('contrato.estado', Contrato::LIQUIDADO)
            ->assertJsonPath('contrato.fecha_liquidacion', null)
            ->assertJsonPath('contrato.aviso_cierre', ['faltan' => ['fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado']]);

        $this->como()->postJson('/GestPro/guardarContrato', ['formContrato' => [
            'n_contrato' => 'C-SECOP-2', 'objeto' => 'Terminado en SECOP II', 'valor_inicial' => 1,
            'estado' => Contrato::TERMINADO, 'proyecto' => $this->proyecto->id,
        ], 'anexos' => []])->assertOk()
            ->assertJsonPath('contrato.aviso_cierre', ['faltan' => ['fecha_terminacion_real']]);
    }

    // ------------------------------------------------------------------ listados

    public function test_el_aviso_de_cierre_en_la_busqueda_el_detalle_y_el_listado_por_proyecto(): void
    {
        $sinDatos = $this->contrato(['n_contrato' => 'A', 'estado' => Contrato::LIQUIDADO]);
        $faltaValor = $this->contrato(['n_contrato' => 'B', 'estado' => Contrato::LIQUIDADO, 'fecha_terminacion_real' => '2026-11-01', 'fecha_liquidacion' => '2026-11-05']);
        $terminadoSinFecha = $this->contrato(['n_contrato' => 'C', 'estado' => Contrato::TERMINADO]);
        $completo = $this->contrato(['n_contrato' => 'D', 'estado' => Contrato::LIQUIDADO, 'fecha_terminacion_real' => '2026-11-01', 'fecha_liquidacion' => '2026-11-05', 'valor_liquidado' => 990000]);
        $enEjecucion = $this->contrato(['n_contrato' => 'E']);

        $esperado = [
            $sinDatos->id => ['faltan' => ['fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado']],
            $faltaValor->id => ['faltan' => ['valor_liquidado']],
            $terminadoSinFecha->id => ['faltan' => ['fecha_terminacion_real']],
            $completo->id => null,
            $enEjecucion->id => null,
        ];

        DB::enableQueryLog();
        $busqueda = collect($this->como(Rol::Consulta)->getJson('/GestPro/contratos/buscar')->assertOk()->json())->keyBy('id');
        $consultasCinco = count(DB::getQueryLog());
        $listado = collect($this->como(Rol::Consulta)->getJson('/GestPro/listarContratos?proyecto=' . $this->proyecto->id)->assertOk()->json())->keyBy('id');

        foreach ($esperado as $id => $aviso) {
            $this->assertSame($aviso, $busqueda[$id]['aviso_cierre'], "búsqueda #{$id}");
            $this->assertSame($aviso, $listado[$id]['aviso_cierre'], "listado #{$id}");
            $this->assertSame($aviso, $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$id}/detalle")->assertOk()->json('aviso_cierre'), "detalle #{$id}");
        }
        $this->assertEquals(990000, $busqueda[$completo->id]['valor_liquidado']);
        $this->assertSame('2026-11-01', substr($busqueda[$completo->id]['fecha_terminacion_real'], 0, 10));
        $this->assertSame('2026-11-05', substr($listado[$completo->id]['fecha_liquidacion'], 0, 10));

        // Sin consultas por fila: con más contratos, la búsqueda hace las mismas consultas.
        $this->contrato(['n_contrato' => 'F', 'estado' => Contrato::LIQUIDADO]);
        $this->contrato(['n_contrato' => 'G', 'estado' => Contrato::TERMINADO]);
        DB::flushQueryLog();
        $this->como(Rol::Consulta)->getJson('/GestPro/contratos/buscar')->assertOk();
        $this->assertSame($consultasCinco, count(DB::getQueryLog()));
    }

    public function test_get_proyectos_trae_los_datos_y_el_aviso_de_cierre(): void
    {
        $sinDatos = $this->contrato(['n_contrato' => 'A', 'estado' => Contrato::LIQUIDADO]);
        $completo = $this->contrato(['n_contrato' => 'B', 'estado' => Contrato::LIQUIDADO, 'fecha_terminacion_real' => '2026-11-01', 'fecha_liquidacion' => '2026-11-05', 'valor_liquidado' => 990000]);

        $contratos = collect(collect($this->como(Rol::Consulta)->getJson('/GestPro/proyectos')->assertOk()->json())
            ->firstWhere('id', $this->proyecto->id)['contratos'])->keyBy('id');

        $this->assertSame(['faltan' => ['fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado']], $contratos[$sinDatos->id]['aviso_cierre']);
        $this->assertNull($contratos[$sinDatos->id]['fecha_liquidacion']);
        $this->assertNull($contratos[$completo->id]['aviso_cierre']);
        $this->assertSame('2026-11-01', substr($contratos[$completo->id]['fecha_terminacion_real'], 0, 10));
        $this->assertSame('2026-11-05', substr($contratos[$completo->id]['fecha_liquidacion'], 0, 10));
        $this->assertEquals(990000, $contratos[$completo->id]['valor_liquidado']);
    }

    // ------------------------------------------------------------------ revisión: fechas cruzadas

    public function test_terminar_no_acepta_una_fecha_posterior_a_la_liquidacion_que_conserva(): void
    {
        // Liquidado y sacado de liquidado por el formulario: conserva la fecha de liquidación.
        $c = $this->contrato(['estado' => Contrato::EN_EJECUCION, 'fecha_terminacion_real' => '2026-11-01', 'fecha_liquidacion' => '2026-11-10', 'valor_liquidado' => 5]);

        $this->terminar($c, ['fecha_terminacion_real' => '2026-11-11'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_terminacion_real.0', 'La fecha de terminación no puede ser posterior a la de liquidación.');
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);
        $this->assertSame([], $this->eventos($c));

        $this->terminar($c, ['fecha_terminacion_real' => '2026-11-10'])->assertOk()
            ->assertJsonPath('contrato.fecha_terminacion_real', '2026-11-10');
    }

    public function test_editar_no_acepta_una_fecha_de_inicio_posterior_a_la_terminacion_real(): void
    {
        $c = $this->contrato(['estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => '2026-11-10', 'fecha_fin_inicial' => '2026-12-31']);

        $this->editar($c, ['fecha_inicio' => '2026-11-11'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_inicio.0', 'La fecha de inicio no puede ser posterior a la de terminación real.');
        $this->assertSame('2026-02-01', $c->fresh()->fecha_inicio->format('Y-m-d'));

        $this->editar($c, ['fecha_inicio' => '2026-11-10'])->assertOk();
        // Sin terminación real no hay límite.
        $otro = $this->contrato(['n_contrato' => 'C-002', 'fecha_fin_inicial' => '2026-12-31']);
        $this->editar($otro, ['fecha_inicio' => '2026-11-30'])->assertOk();
    }

    // ------------------------------------------------------------------ revisión: sin cambios

    public function test_terminar_sin_cambios_ni_observacion_no_guarda_ni_adelanta_la_version(): void
    {
        $c = $this->contrato(['estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => '2026-11-15']);
        $version = $this->version($c);

        $this->terminar($c, ['fecha_terminacion_real' => '2026-11-15'])->assertOk()
            ->assertJsonPath('contrato.updated_at', $version)
            ->assertJsonPath('contrato.estado', Contrato::TERMINADO)
            ->assertJsonPath('avisos', []);
        $this->terminar($c, ['fecha_terminacion_real' => '2026-11-15', 'observacion' => '   '])->assertOk();
        $this->assertSame($version, $this->version($c));
        $this->assertSame([], $this->eventos($c));

        // Con observación sí queda registro y cambia la versión.
        $r = $this->terminar($c, ['fecha_terminacion_real' => '2026-11-15', 'observacion' => 'Acta firmada'])->assertOk();
        $this->assertNotSame($version, $r->json('contrato.updated_at'));
        $this->assertSame(['contrato_terminado'], array_column($this->eventos($c), 0));
    }

    public function test_liquidar_sin_cambios_ni_observacion_no_guarda_pero_responde_los_avisos(): void
    {
        $c = $this->contrato(['estado' => Contrato::LIQUIDADO, 'valor' => 1000, 'fecha_terminacion_real' => '2026-11-10', 'fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => 1500]);
        $version = $this->version($c);

        $this->liquidar($c, ['fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => '1500.00'])->assertOk()
            ->assertJsonPath('contrato.updated_at', $version)
            ->assertJsonPath('contrato.valor_liquidado', '1500.00')
            ->assertJsonPath('avisos', ['El valor liquidado supera el valor vigente del contrato.']);
        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-10', 'fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => 1500])->assertOk();
        $this->assertSame($version, $this->version($c));
        $this->assertSame([], $this->eventos($c));

        // Una versión vieja sigue respondiendo 409 aunque no haya cambios.
        $this->liquidar($c, ['fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => 1500, 'actualizado' => '2020-01-01 00:00:00'])->assertStatus(409);

        $this->liquidar($c, ['fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => 1500, 'observacion' => 'Revisada'])->assertOk();
        $this->assertNotSame($version, $this->version($c));
        $this->assertSame(['contrato_liquidado'], array_column($this->eventos($c), 0));
    }

    // ------------------------------------------------------------------ revisión: eliminar

    public function test_eliminar_guarda_los_datos_de_cierre_en_el_historial(): void
    {
        $c = $this->contrato(['estado' => Contrato::LIQUIDADO, 'fecha_terminacion_real' => '2026-11-10', 'fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => 1500.5]);

        $this->como()->postJson('/GestPro/eliminarContrato', ['id' => $c->id])->assertOk();

        [$evento, $antes] = collect($this->eventos($c))->last();
        $this->assertSame('contrato_eliminado', $evento);
        $this->assertSame('2026-11-10', $antes['fecha_terminacion_real']);
        $this->assertSame('2026-11-18', $antes['fecha_liquidacion']);
        $this->assertSame('1500.50', $antes['valor_liquidado']);
    }

    // ------------------------------------------------------------------ migración

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_19_100023_add_liquidacion_a_contratos.php');
    }

    public function test_la_migracion_agrega_las_columnas_nulas_y_su_rollback_se_detiene_con_datos(): void
    {
        $columnas = collect(Schema::getColumns('contratos'))->keyBy('name');
        foreach (['fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado'] as $columna) {
            $this->assertTrue($columnas->has($columna), $columna);
            $this->assertTrue($columnas[$columna]['nullable'], $columna);
        }

        $migracion = $this->migracion();
        $migracion->up(); // ya migrada: no falla
        $c = $this->contrato();

        foreach ([['fecha_terminacion_real' => '2026-11-01'], ['fecha_liquidacion' => '2026-11-05'], ['valor_liquidado' => 0]] as $dato) {
            DB::table('contratos')->where('id', $c->id)->update(['fecha_terminacion_real' => null, 'fecha_liquidacion' => null, 'valor_liquidado' => null, ...$dato]);
            try {
                $migracion->down();
                $this->fail('El rollback debía detenerse: ' . key($dato));
            } catch (RuntimeException $e) {
                $this->assertSame('No se puede revertir: hay contratos con fecha de terminación, fecha de liquidación o valor liquidado registrados, que se perderían. Restaure un respaldo.', $e->getMessage());
            }
            $this->assertTrue(Schema::hasColumn('contratos', 'valor_liquidado'));
        }

        DB::table('contratos')->update(['fecha_terminacion_real' => null, 'fecha_liquidacion' => null, 'valor_liquidado' => null]);
        $migracion->down();
        $migracion->down();
        foreach (['fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado'] as $columna) {
            $this->assertFalse(Schema::hasColumn('contratos', $columna), $columna);
        }
        $this->assertSame('C-001-2026', DB::table('contratos')->value('n_contrato'));
        $migracion->up();
        $migracion->up();
        $this->assertTrue(Schema::hasColumn('contratos', 'fecha_liquidacion'));
    }
}
```

En `tests/Feature/Contratacion/ContratosApiTest.php`, reemplazar (1 de 1):

```php
                'interventoria' => 'Interventoría Andina',
                'estado' => 'celebrado',
                'estado_original' => null,
                'anticipo' => true,
                'porcentaje_anticipo' => 30,
                'avance_fisico' => 10,
```

por:

```php
                'interventoria' => 'Interventoría Andina',
                'estado' => 'celebrado',
                'estado_original' => null,
                // Fase 7A: datos de cierre (los escriben «Terminar» y «Liquidar», no el formulario).
                'fecha_terminacion_real' => null,
                'fecha_liquidacion' => null,
                'valor_liquidado' => null,
                'aviso_cierre' => null,
                'anticipo' => true,
                'porcentaje_anticipo' => 30,
                'avance_fisico' => 10,
```

En `tests/Feature/Contratacion/ContratosBusquedaApiTest.php`, reemplazar (1 de 1):

```php
        $fila = $filas[0];
        $this->assertSame(
            ['id', 'n_contrato', 'objeto', 'contratista', 'contratante', 'estado', 'valor', 'valor_inicial', 'fecha_inicio', 'fecha_fin',
                'avance_fisico', 'avance_financiero', 'tipo_contrato', 'proyecto', 'proceso', 'aviso_proceso', 'secop_id_contrato', 'updated_at'],
            array_keys($fila),
        );
        $this->assertSame($c->id, $fila['id']);
```

por:

```php
        $fila = $filas[0];
        $this->assertSame(
            ['id', 'n_contrato', 'objeto', 'contratista', 'contratante', 'estado', 'valor', 'valor_inicial', 'fecha_inicio', 'fecha_fin',
                // Fase 7A: datos de cierre y su aviso.
                'fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado',
                'avance_fisico', 'avance_financiero', 'tipo_contrato', 'proyecto', 'proceso', 'aviso_proceso', 'aviso_cierre', 'secop_id_contrato', 'updated_at'],
            array_keys($fila),
        );
        $this->assertSame($c->id, $fila['id']);
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/CierreContratosApiTest.php tests/Feature/Contratacion/ContratosApiTest.php tests/Feature/Contratacion/ContratosBusquedaApiTest.php`
Expected: FALLAN 35 y pasan 96. Las 33 de `CierreContratosApiTest` (30 pruebas, una con 4 casos): las rutas `POST /contratos/{contrato}/terminar` y `/liquidar` aún no existen y la ruta comodín del SPA, que solo atiende GET, responde 405 ("Failed asserting that 405 is identical to 200.", "... to 401.", "... to 404."); las de la regla de editar, los listados y la migración fallan porque las columnas y las claves nuevas aún no existen. En `ContratosApiTest` y `ContratosBusquedaApiTest` falla 1 en cada una, la que compara la forma exacta de la respuesta, porque faltan `fecha_terminacion_real`, `fecha_liquidacion`, `valor_liquidado` y `aviso_cierre`.

- [ ] **Paso 3: Implementar**

Crear `app/Services/Contratacion/CierreContratos.php`:

```php
<?php

namespace App\Services\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\Contrato;
use App\Models\User;
use App\Services\Viabilizacion\HistorialChecklist;
use Brick\Math\BigDecimal;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cierre de un contrato (fase 7A): terminarlo y liquidarlo, cada uno con su acción.
 *
 * Reglas:
 * - Terminar pide la fecha de terminación real. Se termina un contrato celebrado, en ejecución,
 *   suspendido o ya terminado (corrige la fecha); uno liquidado se corrige desde liquidar y uno
 *   anulado no se termina.
 * - Liquidar pide la fecha de liquidación y el valor liquidado (no negativo, dos decimales), y la
 *   fecha de terminación si el contrato no la tiene. Se liquida desde cualquier estado salvo
 *   anulado; desde liquidado completa o corrige los datos.
 * - Ninguna fecha de cierre es posterior a hoy (en config('contratacion.zona')); la de terminación
 *   no es anterior a la de inicio, si la hay, y la de liquidación no es anterior a la de terminación.
 * - Aviso que no impide: el valor liquidado supera el valor vigente (`valor`).
 * - Solo cambian el estado y los datos de cierre: el valor, la fecha fin (vigentes e iniciales) y
 *   los avances físico y financiero no se tocan (R13).
 * - Al terminar, la fecha de terminación tampoco es posterior a la de liquidación que conserve el
 *   contrato (uno que salió de liquidado por el formulario).
 * - Traen `actualizado`, como editar: si ya no es el de la fila, se rechaza (409). Cada cambio
 *   adelanta updated_at y queda en checklist_historial (entidad contrato): `contrato_terminado` y
 *   `contrato_liquidado`, con antes y después (R15).
 * - Sin ningún cambio (mismo estado y mismos datos) y sin observación no se guarda nada: ni versión
 *   nueva ni historial; responde el contrato tal cual y sus avisos. Con observación sí se registra.
 * - aviso() dice qué datos de cierre le faltan a un contrato según su estado, sin consultas.
 */
final class CierreContratos
{
    use ControlDeVersion;

    public const TERMINAR_LIQUIDADO = 'El contrato está liquidado: corrige la terminación desde «Liquidar».';

    public const ANULADO = 'El contrato está anulado.';

    public const SIN_ESTADO = 'Elige primero un estado válido para el contrato.';

    public const SIN_TERMINACION = 'Escribe la fecha de terminación real.';

    public const TERMINACION_FUTURA = 'La fecha de terminación no puede ser posterior a hoy.';

    public const TERMINACION_ANTERIOR = 'La fecha de terminación no puede ser anterior a la de inicio.';

    public const TERMINACION_TRAS_LIQUIDACION = 'La fecha de terminación no puede ser posterior a la de liquidación.';

    public const LIQUIDACION_FUTURA = 'La fecha de liquidación no puede ser posterior a hoy.';

    public const LIQUIDACION_ANTERIOR = 'La fecha de liquidación no puede ser anterior a la de terminación.';

    public const SUPERA_VIGENTE = 'El valor liquidado supera el valor vigente del contrato.';

    // Estados desde los que se termina un contrato.
    private const TERMINABLES = [Contrato::CELEBRADO, Contrato::EN_EJECUCION, Contrato::SUSPENDIDO, Contrato::TERMINADO];

    public function __construct(
        private readonly HistorialChecklist $historial,
        private readonly AvisosContratacion $avisos,
    ) {
    }

    /** @param string $fecha año-mes-día */
    public function terminar(Contrato $contrato, string $fecha, ?string $observacion, ?string $actualizado, ?User $usuario): CierreGuardado
    {
        $observacion = $this->texto($observacion);

        return DB::transaction(function () use ($contrato, $fecha, $observacion, $actualizado, $usuario) {
            // Primero la versión: con un formulario viejo, el estado que se ve engañaría.
            $actual = $this->bloquear($contrato);
            $this->exigirVersion($actual, $actualizado, ContratosService::DESACTUALIZADO);
            if ($actual->estado === Contrato::LIQUIDADO) {
                throw new ReglaNegocioException(self::TERMINAR_LIQUIDADO);
            }
            if ($actual->estado === Contrato::ANULADO) {
                throw new ReglaNegocioException(self::ANULADO);
            }
            // Un estado fuera de la lista (un contrato antiguo sin convertir) tampoco se termina.
            if (! in_array($actual->estado, self::TERMINABLES, true)) {
                throw new ReglaNegocioException(self::SIN_ESTADO);
            }
            $this->exigirTerminacion($actual, $fecha);
            $liquidacion = $this->fecha($actual->fecha_liquidacion);
            if ($liquidacion !== null && $fecha > $liquidacion) {
                throw ValidationException::withMessages(['fecha_terminacion_real' => self::TERMINACION_TRAS_LIQUIDACION]);
            }

            $antes = ['estado' => $actual->estado, 'fecha_terminacion_real' => $this->fecha($actual->fecha_terminacion_real)];
            $despues = ['estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => $fecha];
            if ($antes === $despues && $observacion === null) {
                return new CierreGuardado($actual, []);
            }
            $this->adelantarActualizado($actual);
            $actual->fill($despues)->save();
            $this->historial->registrarContrato($actual, 'contrato_terminado', $antes, [...$despues, 'observacion' => $observacion], $usuario);

            return new CierreGuardado($actual->refresh(), []);
        });
    }

    /**
     * @param ?string $terminacion año-mes-día, o null para conservar la que tiene
     * @param string $liquidacion año-mes-día
     * @param string $valor numérico, no negativo, hasta dos decimales (lo valida el controlador)
     */
    public function liquidar(Contrato $contrato, ?string $terminacion, string $liquidacion, string $valor, ?string $observacion, ?string $actualizado, ?User $usuario): CierreGuardado
    {
        $observacion = $this->texto($observacion);
        $valor = (string) BigDecimal::of($valor)->toScale(2);

        return DB::transaction(function () use ($contrato, $terminacion, $liquidacion, $valor, $observacion, $actualizado, $usuario) {
            $actual = $this->bloquear($contrato);
            $this->exigirVersion($actual, $actualizado, ContratosService::DESACTUALIZADO);
            if ($actual->estado === Contrato::ANULADO) {
                throw new ReglaNegocioException(self::ANULADO);
            }
            $terminacion ??= $this->fecha($actual->fecha_terminacion_real);
            if ($terminacion === null) {
                throw ValidationException::withMessages(['fecha_terminacion_real' => self::SIN_TERMINACION]);
            }
            $this->exigirTerminacion($actual, $terminacion);
            if ($liquidacion > $this->hoy()) {
                throw ValidationException::withMessages(['fecha_liquidacion' => self::LIQUIDACION_FUTURA]);
            }
            if ($liquidacion < $terminacion) {
                throw ValidationException::withMessages(['fecha_liquidacion' => self::LIQUIDACION_ANTERIOR]);
            }

            $antes = [
                'estado' => $actual->estado,
                'fecha_terminacion_real' => $this->fecha($actual->fecha_terminacion_real),
                'fecha_liquidacion' => $this->fecha($actual->fecha_liquidacion),
                'valor_liquidado' => $actual->valor_liquidado !== null ? (string) BigDecimal::of((string) $actual->valor_liquidado)->toScale(2) : null,
            ];
            $despues = [
                'estado' => Contrato::LIQUIDADO,
                'fecha_terminacion_real' => $terminacion,
                'fecha_liquidacion' => $liquidacion,
                'valor_liquidado' => $valor,
            ];
            $supera = $actual->valor !== null && BigDecimal::of($valor)->isGreaterThan(BigDecimal::of((string) $actual->valor));
            $avisos = $supera ? [self::SUPERA_VIGENTE] : [];
            if ($antes === $despues && $observacion === null) {
                return new CierreGuardado($actual, $avisos);
            }
            $this->adelantarActualizado($actual);
            $actual->fill($despues)->save();
            $this->historial->registrarContrato($actual, 'contrato_liquidado', $antes, [...$despues, 'observacion' => $observacion], $usuario);

            return new CierreGuardado($actual->refresh(), $avisos);
        });
    }

    /**
     * Los datos de cierre que le faltan a un contrato según su estado: terminado, la fecha de
     * terminación; liquidado, las tres que falten. null si no falta nada o el estado no los pide.
     *
     * @return ?array{faltan: list<string>}
     */
    public static function aviso(?string $estado, mixed $terminacion, mixed $liquidacion, mixed $valor): ?array
    {
        $datos = match ($estado) {
            Contrato::TERMINADO => ['fecha_terminacion_real' => $terminacion],
            Contrato::LIQUIDADO => ['fecha_terminacion_real' => $terminacion, 'fecha_liquidacion' => $liquidacion, 'valor_liquidado' => $valor],
            default => [],
        };
        $faltan = array_keys(array_filter($datos, fn ($valor) => $valor === null || $valor === ''));

        return $faltan === [] ? null : ['faltan' => $faltan];
    }

    /** La fecha de terminación no es posterior a hoy ni anterior a la de inicio, si la hay. */
    private function exigirTerminacion(Contrato $contrato, string $fecha): void
    {
        if ($fecha > $this->hoy()) {
            throw ValidationException::withMessages(['fecha_terminacion_real' => self::TERMINACION_FUTURA]);
        }
        $inicio = $this->fecha($contrato->fecha_inicio);
        if ($inicio !== null && $fecha < $inicio) {
            throw ValidationException::withMessages(['fecha_terminacion_real' => self::TERMINACION_ANTERIOR]);
        }
    }

    private function hoy(): string
    {
        return $this->avisos->hoy()->toDateString();
    }

    private function bloquear(Contrato $contrato): Contrato
    {
        return Contrato::query()->whereKey($contrato->id)->lockForUpdate()->firstOrFail();
    }

    private function fecha(?DateTimeInterface $valor): ?string
    {
        return $valor?->format('Y-m-d');
    }

    private function texto(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : null;
    }
}
```

Crear `app/Services/Contratacion/CierreGuardado.php`:

```php
<?php

namespace App\Services\Contratacion;

use App\Models\Contrato;

/** Lo que deja terminar o liquidar un contrato: el contrato como quedó y los avisos que no impiden guardar. */
final class CierreGuardado
{
    /** @param list<string> $avisos */
    public function __construct(
        public readonly Contrato $contrato,
        public readonly array $avisos,
    ) {
    }
}
```

Crear `database/migrations/2026_11_19_100023_add_liquidacion_a_contratos.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Cierre del contrato (fase 7A): la fecha de terminación real, la fecha de liquidación y el valor
 * liquidado (decimal(15, 2), como `valor`). Todas admiten nulo: los contratos que ya existen
 * quedan sin datos de cierre, también los que ya están terminados o liquidados.
 */
return new class extends Migration
{
    private const TABLA = 'contratos';

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada columna lleva su guarda, para poder
        // reintentar sin dejar nada a medias.
        $columnas = [
            'fecha_terminacion_real' => fn (Blueprint $t) => $t->date('fecha_terminacion_real')->nullable()->after('fecha_fin'),
            'fecha_liquidacion' => fn (Blueprint $t) => $t->date('fecha_liquidacion')->nullable()->after('fecha_terminacion_real'),
            'valor_liquidado' => fn (Blueprint $t) => $t->decimal('valor_liquidado', 15, 2)->nullable()->after('fecha_liquidacion'),
        ];
        foreach ($columnas as $columna => $definir) {
            if (! Schema::hasColumn(self::TABLA, $columna)) {
                Schema::table(self::TABLA, $definir);
            }
        }
    }

    public function down(): void
    {
        $existentes = array_values(array_filter(
            ['fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado'],
            fn (string $c) => Schema::hasColumn(self::TABLA, $c)
        ));
        if ($existentes && DB::table(self::TABLA)->where(function ($q) use ($existentes) {
            foreach ($existentes as $columna) {
                $q->orWhereNotNull($columna);
            }
        })->exists()) {
            throw new RuntimeException('No se puede revertir: hay contratos con fecha de terminación, fecha de liquidación o valor liquidado registrados, que se perderían. Restaure un respaldo.');
        }

        // Cada columna con su guarda.
        foreach (array_reverse($existentes) as $columna) {
            Schema::table(self::TABLA, function (Blueprint $table) use ($columna) {
                $table->dropColumn($columna);
            });
        }
    }
};
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (1 de 6):

```php
namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Services\Contratacion\ContratosService;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
```

por:

```php
namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Services\Contratacion\CierreContratos;
use App\Services\Contratacion\CierreGuardado;
use App\Services\Contratacion\ContratosService;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (2 de 6):

```php
use Illuminate\Validation\ValidationException;

/**
 * Guardar y eliminar contratos (can:editar-datos). Lectura: buscar y detalle (cualquier usuario
 * autenticado); los listados por proyecto siguen en ProyectoController (GET /proyectos y
 * GET /listarContratos).
 */
```

por:

```php
use Illuminate\Validation\ValidationException;

/**
 * Guardar, eliminar, terminar y liquidar contratos (can:editar-datos). Lectura: buscar y detalle (cualquier usuario
 * autenticado); los listados por proyecto siguen en ProyectoController (GET /proyectos y
 * GET /listarContratos).
 */
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (3 de 6):

```php
        'id.required' => 'Falta el contrato que quieres eliminar.',
        'q.max' => 'En la búsqueda caben hasta 100 caracteres.',
        'q.string' => 'Revisa la búsqueda: debe ser un texto.',
    ];

    private const ATRIBUTOS = [
```

por:

```php
        'id.required' => 'Falta el contrato que quieres eliminar.',
        'q.max' => 'En la búsqueda caben hasta 100 caracteres.',
        'q.string' => 'Revisa la búsqueda: debe ser un texto.',
        'fecha_terminacion_real.required' => 'Escribe la fecha de terminación real.',
        'fecha_liquidacion.required' => 'Escribe la fecha de liquidación.',
        'valor_liquidado.required' => 'Escribe el valor liquidado.',
        'valor_liquidado.numeric' => 'Escribe el valor liquidado como un número.',
        'valor_liquidado.min' => 'El valor liquidado no puede ser negativo.',
        'valor_liquidado.decimal' => 'El valor liquidado admite hasta 2 decimales.',
        'observacion.max' => 'La observación admite hasta 2000 caracteres.',
    ];

    private const ATRIBUTOS = [
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (4 de 6):

```php
        'anexos.*.nombreArchivo' => 'el nombre del archivo del anexo',
        'anexos.*.rutaArchivo' => 'el archivo del anexo',
        'anexos.*.fecha' => 'la fecha del anexo',
    ];

    // Las columnas de valor son decimal(15, 2): 13 cifras enteras y 2 decimales.
    private const CIFRAS_ENTERAS = 13;

    // El ID de un contrato en SECOP II (sin distinguir mayúsculas) y su enlace.
    private const FORMATO_SECOP = '/^CO1\.PCCNTR\.\d+$/i';
```

por:

```php
        'anexos.*.nombreArchivo' => 'el nombre del archivo del anexo',
        'anexos.*.rutaArchivo' => 'el archivo del anexo',
        'anexos.*.fecha' => 'la fecha del anexo',
        'fecha_terminacion_real' => 'la fecha de terminación real',
        'fecha_liquidacion' => 'la fecha de liquidación',
        'valor_liquidado' => 'el valor liquidado',
        'observacion' => 'la observación',
    ];

    // Las columnas de valor son decimal(15, 2): 13 cifras enteras y 2 decimales.
    private const CIFRAS_ENTERAS = 13;

    // El `actualizado` del contrato que tenía la pantalla: siempre presente; null solo para un
    // contrato antiguo, sin fechas. Con otro valor, el servicio responde 409.
    private const VERSION = ['present', 'nullable', 'bail', 'string', 'date'];

    // El ID de un contrato en SECOP II (sin distinguir mayúsculas) y su enlace.
    private const FORMATO_SECOP = '/^CO1\.PCCNTR\.\d+$/i';
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (5 de 6):

```php
    // al eliminar el contrato se borra el archivo.
    private const RUTA_ANEXO = '#^anexos_contratos/[^/\\\\]+$#';

    public function __construct(private readonly ContratosService $contratos)
    {
    }

    /**
```

por:

```php
    // al eliminar el contrato se borra el archivo.
    private const RUTA_ANEXO = '#^anexos_contratos/[^/\\\\]+$#';

    public function __construct(
        private readonly ContratosService $contratos,
        private readonly CierreContratos $cierre,
    ) {
    }

    /**
```

En `app/Http/Controllers/ContratoController.php`, reemplazar (6 de 6):

```php
        return response()->json(['success' => true, 'mensaje' => 'Contrato eliminado.']);
    }

    /**
     * Al editar, los opcionales que no vienen en el pedido no cambian y con null se borran; los
     * obligatorios siempre se envían, y también `actualizado` (el updated_at del contrato que
```

por:

```php
        return response()->json(['success' => true, 'mensaje' => 'Contrato eliminado.']);
    }

    /**
     * Termina el contrato (o corrige su fecha de terminación): `fecha_terminacion_real`,
     * `observacion` opcional y `actualizado`. Responde el contrato y los avisos.
     */
    public function terminar(Request $request, string $contrato): JsonResponse
    {
        $datos = $request->validate([
            'fecha_terminacion_real' => ['bail', 'required', 'string', 'date_format:Y-m-d'],
            'observacion' => ['sometimes', 'nullable', 'bail', 'string', 'max:2000'],
            'actualizado' => self::VERSION,
        ], self::MENSAJES, self::ATRIBUTOS);
        $fila = Contrato::find($contrato);
        if ($fila === null) {
            return response()->json(['mensaje' => 'El contrato no existe.'], 404);
        }

        return $this->cerrado($this->cierre->terminar(
            $fila, $datos['fecha_terminacion_real'], $datos['observacion'] ?? null, $datos['actualizado'], $request->user()
        ));
    }

    /**
     * Liquida el contrato (o completa y corrige su liquidación): `fecha_liquidacion`,
     * `valor_liquidado`, `fecha_terminacion_real` si no la tiene o se corrige, `observacion`
     * opcional y `actualizado`. Responde el contrato y los avisos.
     */
    public function liquidar(Request $request, string $contrato): JsonResponse
    {
        $cifras = fn (string $atributo, mixed $valor, Closure $falla) => strlen(ltrim(explode('.', ltrim((string) $valor, '+-'))[0], '0')) <= self::CIFRAS_ENTERAS
            || $falla('El valor liquidado admite hasta ' . self::CIFRAS_ENTERAS . ' cifras enteras y 2 decimales.');
        $datos = $request->validate([
            'fecha_terminacion_real' => ['sometimes', 'nullable', 'bail', 'string', 'date_format:Y-m-d'],
            'fecha_liquidacion' => ['bail', 'required', 'string', 'date_format:Y-m-d'],
            'valor_liquidado' => ['bail', 'required', 'numeric', 'min:0', 'decimal:0,2', $cifras],
            'observacion' => ['sometimes', 'nullable', 'bail', 'string', 'max:2000'],
            'actualizado' => self::VERSION,
        ], self::MENSAJES, self::ATRIBUTOS);
        $fila = Contrato::find($contrato);
        if ($fila === null) {
            return response()->json(['mensaje' => 'El contrato no existe.'], 404);
        }

        return $this->cerrado($this->cierre->liquidar(
            $fila,
            $datos['fecha_terminacion_real'] ?? null,
            $datos['fecha_liquidacion'],
            (string) $datos['valor_liquidado'],
            $datos['observacion'] ?? null,
            $datos['actualizado'],
            $request->user()
        ));
    }

    private function cerrado(CierreGuardado $guardado): JsonResponse
    {
        return response()->json([
            'success' => true,
            'contrato' => $this->contratos->presentar($guardado->contrato),
            'avisos' => $guardado->avisos,
        ]);
    }

    /**
     * Al editar, los opcionales que no vienen en el pedido no cambian y con null se borran; los
     * obligatorios siempre se envían, y también `actualizado` (el updated_at del contrato que
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (1 de 3):

```php
                    'proyecto', 'proceso_licitacion', 'valor_inicial', 'fecha_fin_inicial',
                    'tipo_contrato_id', 'contratista_identificacion', 'fecha_suscripcion', 'supervisor', 'numero_rp', 'fecha_rp',
                    'fecha_acta_inicio', 'estado_original', 'usuario_creo_id', 'created_at', 'updated_at',
                    'contratante_nit', 'secop_id_contrato', 'secop_proceso', 'url_secop', 'secop_consultado_at'
                )
                ->where('proyecto', $proyecto->id)
                ->get();
```

por:

```php
                    'proyecto', 'proceso_licitacion', 'valor_inicial', 'fecha_fin_inicial',
                    'tipo_contrato_id', 'contratista_identificacion', 'fecha_suscripcion', 'supervisor', 'numero_rp', 'fecha_rp',
                    'fecha_acta_inicio', 'estado_original', 'usuario_creo_id', 'created_at', 'updated_at',
                    'contratante_nit', 'secop_id_contrato', 'secop_proceso', 'url_secop', 'secop_consultado_at',
                    // Fase 7A: los datos de cierre (con ellos, aviso_cierre).
                    'fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado'
                )
                ->where('proyecto', $proyecto->id)
                ->get();
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (2 de 3):

```php
        }

        // El proceso de cada contrato, resumido: una sola consulta para todos los proyectos.
        app(ContratosService::class)->agregarProcesos($proyectos->flatMap(fn ($proyecto) => $proyecto->contratos));

        return response()->json($proyectos);
    }
```

por:

```php
        }

        // El proceso de cada contrato, resumido: una sola consulta para todos los proyectos.
        $todos = $proyectos->flatMap(fn ($proyecto) => $proyecto->contratos);
        app(ContratosService::class)->agregarProcesos($todos);
        // Los datos de cierre que faltan, como en el detalle y en /listarContratos (sin consultas).
        app(ContratosService::class)->agregarAvisoCierre($todos);

        return response()->json($proyectos);
    }
```

En `app/Http/Controllers/ProyectoController.php`, reemplazar (3 de 3):

```php
    {
        $proyecto = $request->all();
        $contratos = DB::table('contratos')->where('proyecto', $proyecto['proyecto'])->get();
        // Con lo que avisa el proceso de cada contrato, como en el detalle.
        app(ContratosService::class)->agregarProcesos($contratos, true);
        app(ContratosService::class)->agregarDetalle($contratos);

        return response()->json($contratos);
```

por:

```php
    {
        $proyecto = $request->all();
        $contratos = DB::table('contratos')->where('proyecto', $proyecto['proyecto'])->get();
        // Con lo que avisa el proceso de cada contrato y los datos de cierre que faltan, como en el detalle.
        app(ContratosService::class)->agregarProcesos($contratos, true);
        app(ContratosService::class)->agregarAvisoCierre($contratos);
        app(ContratosService::class)->agregarDetalle($contratos);

        return response()->json($contratos);
```

En `app/Models/Contrato.php`, reemplazar (1 de 2):

```php
        'tipo_contrato_id', 'contratista_identificacion', 'fecha_suscripcion', 'supervisor',
        'numero_rp', 'fecha_rp', 'fecha_acta_inicio', 'usuario_creo_id',
        'contratante_nit', 'secop_id_contrato', 'secop_proceso', 'url_secop', 'secop_consultado_at',
    ];

    protected $casts = [
```

por:

```php
        'tipo_contrato_id', 'contratista_identificacion', 'fecha_suscripcion', 'supervisor',
        'numero_rp', 'fecha_rp', 'fecha_acta_inicio', 'usuario_creo_id',
        'contratante_nit', 'secop_id_contrato', 'secop_proceso', 'url_secop', 'secop_consultado_at',
        'fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado',
    ];

    protected $casts = [
```

En `app/Models/Contrato.php`, reemplazar (2 de 2):

```php
        'fecha_rp' => 'date',
        'fecha_acta_inicio' => 'date',
        'secop_consultado_at' => 'datetime',
        'anticipo' => 'boolean',
        'tipo_contrato_id' => 'integer',
        'usuario_creo_id' => 'integer',
```

por:

```php
        'fecha_rp' => 'date',
        'fecha_acta_inicio' => 'date',
        'secop_consultado_at' => 'datetime',
        'fecha_terminacion_real' => 'date',
        'fecha_liquidacion' => 'date',
        'valor_liquidado' => 'decimal:2',
        'anticipo' => 'boolean',
        'tipo_contrato_id' => 'integer',
        'usuario_creo_id' => 'integer',
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (1 de 12):

```php
 * - La búsqueda, el detalle y el listado por proyecto traen `aviso_proceso`: lo que avisa el
 *   proceso del contrato (etapas vencidas, cierre vencido o sin actualización), o null si no
 *   tiene proceso o este no avisa nada (ExpedientesContratacion::resumenDeProcesos).
 */
final class ContratosService
{
```

por:

```php
 * - La búsqueda, el detalle y el listado por proyecto traen `aviso_proceso`: lo que avisa el
 *   proceso del contrato (etapas vencidas, cierre vencido o sin actualización), o null si no
 *   tiene proceso o este no avisa nada (ExpedientesContratacion::resumenDeProcesos).
 * - Al crear se admite cualquier estado (un contrato de SECOP II puede llegar ya liquidado). Al
 *   editar, el estado no pasa a terminado ni a liquidado desde otro: eso lo hacen «Terminar» y
 *   «Liquidar» (CierreContratos), con sus fechas y su valor. Salir de esos estados sí se permite.
 *   El formulario no escribe los datos de cierre (fecha de terminación real, fecha de liquidación
 *   y valor liquidado): se conservan.
 * - presentar(), la búsqueda, el detalle y el listado por proyecto traen los datos de cierre y
 *   `aviso_cierre` (CierreContratos::aviso), calculado con la fila, sin consultas.
 */
final class ContratosService
{
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (2 de 12):

```php

    public const FIN_ANTERIOR = 'La fecha de fin no puede ser anterior a la de inicio.';

    public const SIN_PROYECTO = 'El contrato debe seguir en un proyecto.';

    private const CARPETA_ANEXOS = 'anexos_contratos';

    public function __construct(
```

por:

```php

    public const FIN_ANTERIOR = 'La fecha de fin no puede ser anterior a la de inicio.';

    public const INICIO_POSTERIOR_TERMINACION = 'La fecha de inicio no puede ser posterior a la de terminación real.';

    public const SIN_PROYECTO = 'El contrato debe seguir en un proyecto.';

    public const CIERRE_POR_FORMULARIO = 'Para terminar o liquidar el contrato usa «Terminar» o «Liquidar».';

    // Estados a los que solo se llega con su acción (CierreContratos), no con el formulario.
    private const ESTADOS_DE_CIERRE = [Contrato::TERMINADO, Contrato::LIQUIDADO];

    private const CARPETA_ANEXOS = 'anexos_contratos';

    public function __construct(
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (3 de 12):

```php
            // Se decide con la fila bloqueada: el tipo, el proceso y las fechas que tiene ahora.
            $actual = $this->bloquear($contrato);
            $this->exigirVersion($actual, $actualizado, self::DESACTUALIZADO);
            $this->exigirTipo($valores, $actual);
            [$valores] = $this->exigirVinculos($valores, $actual);
            // Sin proceso que lo dé, un proyecto null quitaría el que tiene.
```

por:

```php
            // Se decide con la fila bloqueada: el tipo, el proceso y las fechas que tiene ahora.
            $actual = $this->bloquear($contrato);
            $this->exigirVersion($actual, $actualizado, self::DESACTUALIZADO);
            $nuevoEstado = $valores['estado'] ?? $actual->estado;
            if ($nuevoEstado !== $actual->estado && in_array($nuevoEstado, self::ESTADOS_DE_CIERRE, true)) {
                throw ValidationException::withMessages(['estado' => self::CIERRE_POR_FORMULARIO]);
            }
            $this->exigirTipo($valores, $actual);
            [$valores] = $this->exigirVinculos($valores, $actual);
            // Sin proceso que lo dé, un proyecto null quitaría el que tiene.
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (4 de 12):

```php
                throw ValidationException::withMessages(['proyecto' => self::SIN_PROYECTO]);
            }
            $antes = $this->valoresDe($actual);
            $this->exigirFechas([...$antes, ...$valores]);

            $cambios = array_filter($valores, fn ($valor, string $campo) => $valor !== $antes[$campo], ARRAY_FILTER_USE_BOTH);
            $anexosAntes = $this->anexosDe($actual);
```

por:

```php
                throw ValidationException::withMessages(['proyecto' => self::SIN_PROYECTO]);
            }
            $antes = $this->valoresDe($actual);
            $this->exigirFechas([...$antes, ...$valores], $actual->fecha_terminacion_real?->format('Y-m-d'));

            $cambios = array_filter($valores, fn ($valor, string $campo) => $valor !== $antes[$campo], ARRAY_FILTER_USE_BOTH);
            $anexosAntes = $this->anexosDe($actual);
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (5 de 12):

```php
                ...$this->valoresDe($actual),
                'valor' => $actual->valor,
                'fecha_fin' => $actual->fecha_fin?->format('Y-m-d'),
                'anexos' => $anexos,
            ], [], $usuario);
            $actual->anexos()->delete();
```

por:

```php
                ...$this->valoresDe($actual),
                'valor' => $actual->valor,
                'fecha_fin' => $actual->fecha_fin?->format('Y-m-d'),
                // Fase 7A: los datos de cierre, que el formulario no edita.
                'fecha_terminacion_real' => $actual->fecha_terminacion_real?->format('Y-m-d'),
                'fecha_liquidacion' => $actual->fecha_liquidacion?->format('Y-m-d'),
                'valor_liquidado' => $actual->valor_liquidado !== null ? (string) BigDecimal::of((string) $actual->valor_liquidado)->toScale(2) : null,
                'anexos' => $anexos,
            ], [], $usuario);
            $actual->anexos()->delete();
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (6 de 12):

```php
            'interventoria' => $contrato->interventoria,
            'estado' => $contrato->estado,
            'estado_original' => $contrato->estado_original,
            'anticipo' => (bool) $contrato->anticipo,
            'porcentaje_anticipo' => $this->entero($contrato->porcentaje_anticipo),
            'avance_fisico' => $this->entero($contrato->avance_fisico),
```

por:

```php
            'interventoria' => $contrato->interventoria,
            'estado' => $contrato->estado,
            'estado_original' => $contrato->estado_original,
            'fecha_terminacion_real' => $fecha($contrato->fecha_terminacion_real),
            'fecha_liquidacion' => $fecha($contrato->fecha_liquidacion),
            'valor_liquidado' => $contrato->valor_liquidado,
            'aviso_cierre' => CierreContratos::aviso(
                $contrato->estado, $contrato->fecha_terminacion_real, $contrato->fecha_liquidacion, $contrato->valor_liquidado
            ),
            'anticipo' => (bool) $contrato->anticipo,
            'porcentaje_anticipo' => $this->entero($contrato->porcentaje_anticipo),
            'avance_fisico' => $this->entero($contrato->avance_fisico),
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (7 de 12):

```php
        }
    }

    /**
     * Lo que avisa el proceso de un contrato en el módulo Contratos: null si no tiene etapas
     * vencidas, ni el cierre vencido, ni está sin actualización.
```

por:

```php
        }
    }

    /**
     * Completa las filas de contratos de los listados (consultas con DB::table que traen el estado
     * y los datos de cierre) con `aviso_cierre` (CierreContratos::aviso), sin consultas.
     *
     * @param iterable<object> $filas
     */
    public function agregarAvisoCierre(iterable $filas): void
    {
        foreach ($filas as $fila) {
            $fila->aviso_cierre = CierreContratos::aviso($fila->estado, $fila->fecha_terminacion_real, $fila->fecha_liquidacion, $fila->valor_liquidado);
        }
    }

    /**
     * Lo que avisa el proceso de un contrato en el módulo Contratos: null si no tiene etapas
     * vencidas, ni el cierre vencido, ni está sin actualización.
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (8 de 12):

```php
                'contratos.estado', 'contratos.valor', 'contratos.valor_inicial', 'contratos.fecha_inicio', 'contratos.fecha_fin',
                'contratos.avance_fisico', 'contratos.avance_financiero', 'contratos.secop_id_contrato', 'contratos.updated_at',
                'contratos.proceso_licitacion',
                'contratos.proyecto as proyecto_id', 'proyectos.nombre as proyecto_nombre',
                'contratos.tipo_contrato_id', 'tipos_procesos.nombre as tipo_nombre',
            ])
```

por:

```php
                'contratos.estado', 'contratos.valor', 'contratos.valor_inicial', 'contratos.fecha_inicio', 'contratos.fecha_fin',
                'contratos.avance_fisico', 'contratos.avance_financiero', 'contratos.secop_id_contrato', 'contratos.updated_at',
                'contratos.proceso_licitacion',
                'contratos.fecha_terminacion_real', 'contratos.fecha_liquidacion', 'contratos.valor_liquidado',
                'contratos.proyecto as proyecto_id', 'proyectos.nombre as proyecto_nombre',
                'contratos.tipo_contrato_id', 'tipos_procesos.nombre as tipo_nombre',
            ])
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (9 de 12):

```php

        $filas = $consulta->get();
        $this->agregarProcesos($filas, true, $resumenes);

        return $filas->map(fn ($f) => [
            'id' => (int) $f->id,
```

por:

```php

        $filas = $consulta->get();
        $this->agregarProcesos($filas, true, $resumenes);
        $this->agregarAvisoCierre($filas);

        return $filas->map(fn ($f) => [
            'id' => (int) $f->id,
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (10 de 12):

```php
            'valor_inicial' => $f->valor_inicial,
            'fecha_inicio' => $f->fecha_inicio,
            'fecha_fin' => $f->fecha_fin,
            'avance_fisico' => $this->entero($f->avance_fisico),
            'avance_financiero' => $this->entero($f->avance_financiero),
            'tipo_contrato' => $f->tipo_contrato_id !== null ? ['id' => (int) $f->tipo_contrato_id, 'nombre' => $f->tipo_nombre] : null,
            'proyecto' => $f->proyecto_id !== null ? ['id' => (int) $f->proyecto_id, 'nombre' => $f->proyecto_nombre] : null,
            'proceso' => $f->proceso,
            'aviso_proceso' => $f->aviso_proceso,
            'secop_id_contrato' => $f->secop_id_contrato,
            'updated_at' => $f->updated_at,
        ])->all();
    }

    /**
     * El contrato con la misma forma que un elemento de GET /listarContratos (con `aviso_proceso`),
     * más `proyecto_nombre`, o null si no existe.
     */
    public function detalle(int $id): ?object
```

por:

```php
            'valor_inicial' => $f->valor_inicial,
            'fecha_inicio' => $f->fecha_inicio,
            'fecha_fin' => $f->fecha_fin,
            'fecha_terminacion_real' => $f->fecha_terminacion_real,
            'fecha_liquidacion' => $f->fecha_liquidacion,
            'valor_liquidado' => $f->valor_liquidado,
            'avance_fisico' => $this->entero($f->avance_fisico),
            'avance_financiero' => $this->entero($f->avance_financiero),
            'tipo_contrato' => $f->tipo_contrato_id !== null ? ['id' => (int) $f->tipo_contrato_id, 'nombre' => $f->tipo_nombre] : null,
            'proyecto' => $f->proyecto_id !== null ? ['id' => (int) $f->proyecto_id, 'nombre' => $f->proyecto_nombre] : null,
            'proceso' => $f->proceso,
            'aviso_proceso' => $f->aviso_proceso,
            'aviso_cierre' => $f->aviso_cierre,
            'secop_id_contrato' => $f->secop_id_contrato,
            'updated_at' => $f->updated_at,
        ])->all();
    }

    /**
     * El contrato con la misma forma que un elemento de GET /listarContratos (con `aviso_proceso` y `aviso_cierre`),
     * más `proyecto_nombre`, o null si no existe.
     */
    public function detalle(int $id): ?object
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (11 de 12):

```php
        }
        $filas = [$fila];
        $this->agregarProcesos($filas, true);
        $this->agregarDetalle($filas);

        return $fila;
```

por:

```php
        }
        $filas = [$fila];
        $this->agregarProcesos($filas, true);
        $this->agregarAvisoCierre($filas);
        $this->agregarDetalle($filas);

        return $fila;
```

En `app/Services/Contratacion/ContratosService.php`, reemplazar (12 de 12):

```php
    }

    /** La fecha fin inicial no es anterior a la de inicio; `$valores` trae las dos, como quedarían. */
    private function exigirFechas(array $valores): void
    {
        $inicio = $valores['fecha_inicio'] ?? null;
        $fin = $valores['fecha_fin_inicial'] ?? null;
        if ($inicio !== null && $fin !== null && $fin < $inicio) {
            throw ValidationException::withMessages(['fecha_fin_inicial' => self::FIN_ANTERIOR]);
        }
    }

    /** @param list<array{descripcion: string, nombreArchivo: string, rutaArchivo: string, fecha: string}> $anexos */
```

por:

```php
    }

    /** La fecha fin inicial no es anterior a la de inicio; `$valores` trae las dos, como quedarían. */
    /** @param ?string $terminacion la fecha de terminación real que conserva el contrato (año-mes-día), si la tiene */
    private function exigirFechas(array $valores, ?string $terminacion = null): void
    {
        $inicio = $valores['fecha_inicio'] ?? null;
        $fin = $valores['fecha_fin_inicial'] ?? null;
        if ($inicio !== null && $fin !== null && $fin < $inicio) {
            throw ValidationException::withMessages(['fecha_fin_inicial' => self::FIN_ANTERIOR]);
        }
        if ($inicio !== null && $terminacion !== null && $inicio > $terminacion) {
            throw ValidationException::withMessages(['fecha_inicio' => self::INICIO_POSTERIOR_TERMINACION]);
        }
    }

    /** @param list<array{descripcion: string, nombreArchivo: string, rutaArchivo: string, fecha: string}> $anexos */
```

En `routes/contratacion.php`, reemplazar (1 de 2):

```php
Route::get('/tablero/contratacion', [TableroContratacionController::class, 'index'])
    ->name('tablero.contratacion');

// Procesos de contratación: Administrador o Gestor.
Route::middleware('can:editar-datos')->group(function () {
    Route::post('/proyectos/{proyecto}/procesos', [ProcesoContratacionController::class, 'store'])
        ->name('procesos.store');
```

por:

```php
Route::get('/tablero/contratacion', [TableroContratacionController::class, 'index'])
    ->name('tablero.contratacion');

// Procesos de contratación y cierre de contratos: Administrador o Gestor.
Route::middleware('can:editar-datos')->group(function () {
    Route::post('/proyectos/{proyecto}/procesos', [ProcesoContratacionController::class, 'store'])
        ->name('procesos.store');
```

En `routes/contratacion.php`, reemplazar (2 de 2):

```php
        ->name('procesos.destroy');
    Route::post('/procesos/{proceso}/expediente', [ProcesoContratacionController::class, 'crearExpediente'])
        ->name('procesos.expediente.store');
});

// SECOP II (datos abiertos): consultar para importar contratos. Administrador o Gestor, con
```

por:

```php
        ->name('procesos.destroy');
    Route::post('/procesos/{proceso}/expediente', [ProcesoContratacionController::class, 'crearExpediente'])
        ->name('procesos.expediente.store');

    // Cierre del contrato (fase 7A): cada acción cambia el estado y guarda sus datos de una vez.
    Route::post('/contratos/{contrato}/terminar', [ContratoController::class, 'terminar'])
        ->whereNumber('contrato')
        ->name('contratos.terminar');
    Route::post('/contratos/{contrato}/liquidar', [ContratoController::class, 'liquidar'])
        ->whereNumber('contrato')
        ->name('contratos.liquidar');
});

// SECOP II (datos abiertos): consultar para importar contratos. Administrador o Gestor, con
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/CierreContratosApiTest.php tests/Feature/Contratacion/ContratosApiTest.php tests/Feature/Contratacion/ContratosBusquedaApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1346 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Services/Contratacion/CierreContratos.php app/Services/Contratacion/CierreGuardado.php database/migrations/2026_11_19_100023_add_liquidacion_a_contratos.php tests/Feature/Contratacion/CierreContratosApiTest.php app/Http/Controllers/ContratoController.php app/Http/Controllers/ProyectoController.php app/Models/Contrato.php app/Services/Contratacion/ContratosService.php routes/contratacion.php tests/Feature/Contratacion/ContratosApiTest.php tests/Feature/Contratacion/ContratosBusquedaApiTest.php
git commit -m "feat: contract termination and liquidation API (migration 24)"
```

---

### Tarea 3: Terminar y liquidar en el editor del contrato

Los botones y diálogos de cierre (`CierreContrato.jsx`) en `ContratoEditor.jsx`, el bloque "Cierre", el select de estado con terminado y liquidado deshabilitados, "Actualizar desde SECOP II" sin aplicar esos estados a un contrato guardado, la nota de un contrato sin estado y el chip del aviso en el módulo Contratos, la lista de Parámetros (que ahora se pide al abrirla y se recarga al cerrar un contrato) y la ficha del proyecto. Las utilidades puras, en `utils/contratos.js` y `utils/secop.js`, con pruebas Vitest.

**Files:**
- Create: `resources/js/components/contratos/CierreContrato.jsx`
- Modify: `resources/css/Contratacion.css`
- Modify: `resources/js/components/FichaProyecto.jsx`
- Modify: `resources/js/components/contratos/ContratoEditor.jsx`
- Modify: `resources/js/components/contratos/ContratosModulo.jsx`
- Modify: `resources/js/components/contratos/ContratosProyecto.jsx`
- Modify: `resources/js/components/contratos/SecopContrato.jsx`
- Modify: `resources/js/utils/contratos.js`
- Test: `resources/js/utils/contratos.test.js`
- Modify: `resources/js/utils/secop.js`
- Test: `resources/js/utils/secop.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

En `resources/js/utils/contratos.test.js`, reemplazar (1 de 2):

```js
    valorVigente,
    vigentesDistintos,
} from './contratos';

// Un contrato como lo devuelve presentar (POST /guardarContrato).
const presentado = {
```

por:

```js
    valorVigente,
    vigentesDistintos,
} from './contratos';
import {
    CAMPOS_LIQUIDAR,
    CAMPOS_TERMINAR,
    accionesCierre,
    notaSinEstadoCierre,
    avisoEstadoCierreSecop,
    cambiosSinGuardar,
    contratoTrasCierre,
    datosLiquidar,
    datosTerminar,
    erroresCierre,
    erroresLiquidar,
    erroresTerminar,
    estadoCierreBloqueado,
    formularioLiquidar,
    formularioTerminar,
    muestraBloqueCierre,
    opcionesEstadoContrato,
    saldoLiquidacion,
    textoAvisoCierre,
} from './contratos';

// Un contrato como lo devuelve presentar (POST /guardarContrato).
const presentado = {
```

En `resources/js/utils/contratos.test.js`, reemplazar (2 de 2):

```js
        expect(etiquetaTipoModificacion('adicion_prorroga')).toBe('Adición y Prórroga');
    });
});
```

por:

```js
        expect(etiquetaTipoModificacion('adicion_prorroga')).toBe('Adición y Prórroga');
    });
});

// ---------- Cierre del contrato (fase 7A) ----------

describe('accionesCierre', () => {
    const con = (estado) => accionesCierre({ ...presentado, estado });

    it('un contrato celebrado, en ejecución o suspendido se termina y se liquida', () => {
        ['celebrado', 'en_ejecucion', 'suspendido'].forEach((estado) => {
            expect(con(estado)).toEqual({ terminar: true, liquidar: true, etiquetaTerminar: 'Terminar', etiquetaLiquidar: 'Liquidar' });
        });
    });

    it('uno terminado corrige su terminación y se liquida', () => {
        expect(con('terminado')).toEqual({ terminar: true, liquidar: true, etiquetaTerminar: 'Corregir terminación', etiquetaLiquidar: 'Liquidar' });
    });

    it('uno liquidado no se termina: corrige su liquidación', () => {
        expect(con('liquidado')).toEqual({ terminar: false, liquidar: true, etiquetaTerminar: 'Terminar', etiquetaLiquidar: 'Corregir liquidación' });
    });

    it('uno anulado no ofrece ninguna', () => {
        expect(con('anulado')).toMatchObject({ terminar: false, liquidar: false });
    });

    it('un estado antiguo fuera de la lista se liquida pero no se termina, como en el servidor', () => {
        expect(con('Vigente')).toMatchObject({ terminar: false, liquidar: true });
    });

    it('un contrato nuevo (sin id) o ninguno no ofrece nada', () => {
        expect(accionesCierre({ ...presentado, id: undefined })).toMatchObject({ terminar: false, liquidar: false });
        expect(accionesCierre(null)).toMatchObject({ terminar: false, liquidar: false });
    });
});

describe('notaSinEstadoCierre', () => {
    const nota = 'Elige un estado para el contrato y guárdalo para poder terminarlo.';

    it('un contrato guardado sin estado o con uno fuera de la lista: elegir y guardar el estado', () => {
        expect(notaSinEstadoCierre({ ...presentado, estado: null })).toBe(nota);
        expect(notaSinEstadoCierre({ ...presentado, estado: '' })).toBe(nota);
        expect(notaSinEstadoCierre({ ...presentado, estado: 'Vigente' })).toBe(nota);
    });

    it('con un estado de la lista, o sin contrato guardado, no hay nota', () => {
        ESTADOS_CONTRATO.forEach(([estado]) => expect(notaSinEstadoCierre({ ...presentado, estado })).toBeNull());
        expect(notaSinEstadoCierre({ ...presentado, id: undefined, estado: null })).toBeNull();
        expect(notaSinEstadoCierre(null)).toBeNull();
    });

    it('esos contratos se liquidan (el servidor lo acepta) pero no se terminan', () => {
        expect(accionesCierre({ ...presentado, estado: null })).toMatchObject({ terminar: false, liquidar: true });
    });
});

describe('textoAvisoCierre', () => {
    it('sin aviso o sin faltantes, null', () => {
        expect(textoAvisoCierre(null)).toBeNull();
        expect(textoAvisoCierre(undefined)).toBeNull();
        expect(textoAvisoCierre({ faltan: [] })).toBeNull();
    });

    it('si falta algo de la liquidación', () => {
        expect(textoAvisoCierre({ faltan: ['fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado'] })).toBe('Faltan datos de liquidación');
        expect(textoAvisoCierre({ faltan: ['valor_liquidado'] })).toBe('Faltan datos de liquidación');
        expect(textoAvisoCierre({ faltan: ['fecha_liquidacion'] })).toBe('Faltan datos de liquidación');
    });

    it('si solo falta la fecha de terminación', () => {
        expect(textoAvisoCierre({ faltan: ['fecha_terminacion_real'] })).toBe('Falta la fecha de terminación');
    });
});

describe('saldoLiquidacion', () => {
    it('el valor vigente menos el liquidado, en centavos exactos', () => {
        expect(saldoLiquidacion({ valor: '1500000.50', valor_liquidado: '1250000.25' })).toBe('250000.25');
        // Con coma flotante, 0.3 - 0.1 daría 0.19999999999999998.
        expect(saldoLiquidacion({ valor: '0.30', valor_liquidado: '0.10' })).toBe('0.20');
        expect(saldoLiquidacion({ valor: '9999999999999.99', valor_liquidado: '0.01' })).toBe('9999999999999.98');
        expect(saldoLiquidacion({ valor: '1000', valor_liquidado: '1000.00' })).toBe('0.00');
    });

    it('negativo si lo liquidado supera lo vigente', () => {
        expect(saldoLiquidacion({ valor: '1000000.00', valor_liquidado: '1000000.50' })).toBe('-0.50');
        expect(saldoLiquidacion({ valor: '100', valor_liquidado: '250.5' })).toBe('-150.50');
    });

    it('acepta números y textos de la API con uno o dos decimales', () => {
        expect(saldoLiquidacion({ valor: 1500000.5, valor_liquidado: '500000.5' })).toBe('1000000.00');
    });

    it('sin valor vigente o sin valor liquidado (o con uno ilegible), null', () => {
        expect(saldoLiquidacion({ valor: '1000.00', valor_liquidado: null })).toBeNull();
        expect(saldoLiquidacion({ valor: null, valor_liquidado: '10.00' })).toBeNull();
        expect(saldoLiquidacion({ valor: '1000.00', valor_liquidado: '' })).toBeNull();
        expect(saldoLiquidacion({ valor: 'mil', valor_liquidado: '10.00' })).toBeNull();
        expect(saldoLiquidacion({ valor: '10.001', valor_liquidado: '10.00' })).toBeNull();
        expect(saldoLiquidacion(null)).toBeNull();
    });
});

describe('opcionesEstadoContrato', () => {
    const deshabilitados = (opciones) => opciones.filter((o) => o.deshabilitado).map((o) => o.valor);

    it('todos los estados, en el orden de la lista', () => {
        expect(opcionesEstadoContrato('en_ejecucion', false).map((o) => o.valor)).toEqual(ESTADOS_CONTRATO.map(([v]) => v));
    });

    it('al editar, terminado y liquidado se deshabilitan y dicen qué acción usar', () => {
        const opciones = opcionesEstadoContrato('en_ejecucion', false);
        expect(deshabilitados(opciones)).toEqual(['terminado', 'liquidado']);
        expect(opciones.find((o) => o.valor === 'terminado').etiqueta).toBe('Terminado (usa «Terminar»)');
        expect(opciones.find((o) => o.valor === 'liquidado').etiqueta).toBe('Liquidado (usa «Liquidar»)');
        expect(opciones.find((o) => o.valor === 'en_ejecucion')).toEqual({ valor: 'en_ejecucion', etiqueta: 'En ejecución', deshabilitado: false });
    });

    it('el estado guardado sigue habilitado: un terminado puede quedarse así, pero no pasar a liquidado', () => {
        expect(deshabilitados(opcionesEstadoContrato('terminado', false))).toEqual(['liquidado']);
        expect(deshabilitados(opcionesEstadoContrato('liquidado', false))).toEqual(['terminado']);
        expect(opcionesEstadoContrato('liquidado', false).find((o) => o.valor === 'liquidado').etiqueta).toBe('Liquidado');
    });

    it('un contrato nuevo puede llegar en cualquier estado', () => {
        expect(deshabilitados(opcionesEstadoContrato('', true))).toEqual([]);
    });
});

describe('formularios y datos de terminar y liquidar', () => {
    const terminado = { ...presentado, estado: 'terminado', fecha_terminacion_real: '2026-10-31', fecha_liquidacion: null, valor_liquidado: null };

    it('el formulario de terminar empieza con la fecha guardada, si la hay', () => {
        expect(formularioTerminar(presentado)).toEqual({ fecha_terminacion_real: '', observacion: '' });
        expect(formularioTerminar(terminado)).toEqual({ fecha_terminacion_real: '2026-10-31', observacion: '' });
    });

    it('el formulario de liquidar empieza con los datos guardados y el valor con los miles agrupados', () => {
        expect(formularioLiquidar(terminado)).toEqual({ fecha_terminacion_real: '2026-10-31', fecha_liquidacion: '', valor_liquidado: '', observacion: '' });
        expect(formularioLiquidar({ ...terminado, estado: 'liquidado', fecha_liquidacion: '2026-11-15 00:00:00', valor_liquidado: '1250000.50' }))
            .toEqual({ fecha_terminacion_real: '2026-10-31', fecha_liquidacion: '2026-11-15', valor_liquidado: '1.250.000,50', observacion: '' });
    });

    it('lo que espera POST /contratos/{id}/terminar, con la versión del contrato', () => {
        expect(datosTerminar({ fecha_terminacion_real: '2026-11-19', observacion: '  Acta final  ' }, presentado))
            .toEqual({ fecha_terminacion_real: '2026-11-19', observacion: 'Acta final', actualizado: '2026-11-18 09:00:00' });
        expect(datosTerminar({ fecha_terminacion_real: '2026-11-19', observacion: ' ' }, { ...presentado, updated_at: null }))
            .toEqual({ fecha_terminacion_real: '2026-11-19', observacion: null, actualizado: null });
    });

    it('lo que espera POST /contratos/{id}/liquidar: el valor como número de la API', () => {
        expect(datosLiquidar({
            fecha_terminacion_real: '2026-10-31', fecha_liquidacion: '2026-11-15', valor_liquidado: '1.250.000,5', observacion: '',
        }, terminado)).toEqual({
            fecha_terminacion_real: '2026-10-31', fecha_liquidacion: '2026-11-15', valor_liquidado: '1250000.5', observacion: null, actualizado: '2026-11-18 09:00:00',
        });
    });
});

describe('erroresTerminar', () => {
    const hoy = '2026-11-19';
    const formulario = { fecha_terminacion_real: '2026-11-19', observacion: '' };

    it('sin errores con una fecha entre el inicio y hoy', () => {
        expect(erroresTerminar(formulario, presentado, hoy)).toEqual({});
        expect(erroresTerminar({ ...formulario, fecha_terminacion_real: '2026-01-10' }, presentado, hoy)).toEqual({});
    });

    it('pide la fecha', () => {
        expect(erroresTerminar({ ...formulario, fecha_terminacion_real: '' }, presentado, hoy))
            .toEqual({ fecha_terminacion_real: 'Escribe la fecha de terminación real.' });
    });

    it('la fecha no es posterior a hoy ni anterior a la de inicio', () => {
        expect(erroresTerminar({ ...formulario, fecha_terminacion_real: '2026-11-20' }, presentado, hoy))
            .toEqual({ fecha_terminacion_real: 'La fecha de terminación no puede ser posterior a hoy.' });
        expect(erroresTerminar({ ...formulario, fecha_terminacion_real: '2026-01-09' }, { ...presentado, fecha_inicio: '2026-01-10 00:00:00' }, hoy))
            .toEqual({ fecha_terminacion_real: 'La fecha de terminación no puede ser anterior a la de inicio.' });
        expect(erroresTerminar({ ...formulario, fecha_terminacion_real: '2020-01-01' }, { ...presentado, fecha_inicio: null }, hoy)).toEqual({});
    });

    it('la observación admite hasta 2000 caracteres', () => {
        expect(erroresTerminar({ ...formulario, observacion: 'a'.repeat(2000) }, presentado, hoy)).toEqual({});
        expect(erroresTerminar({ ...formulario, observacion: 'a'.repeat(2001) }, presentado, hoy))
            .toEqual({ observacion: 'La observación admite hasta 2000 caracteres.' });
    });
});

describe('erroresLiquidar', () => {
    const hoy = '2026-11-19';
    const formulario = { fecha_terminacion_real: '2026-10-31', fecha_liquidacion: '2026-11-15', valor_liquidado: '1.250.000,50', observacion: '' };

    it('sin errores con los tres datos en orden', () => {
        expect(erroresLiquidar(formulario, presentado, hoy)).toEqual({});
        expect(erroresLiquidar({ ...formulario, fecha_liquidacion: '2026-10-31', valor_liquidado: '0' }, presentado, hoy)).toEqual({});
    });

    it('pide los tres datos', () => {
        expect(erroresLiquidar({ fecha_terminacion_real: '', fecha_liquidacion: '', valor_liquidado: '', observacion: '' }, presentado, hoy)).toEqual({
            fecha_terminacion_real: 'Escribe la fecha de terminación real.',
            fecha_liquidacion: 'Escribe la fecha de liquidación.',
            valor_liquidado: 'Escribe el valor liquidado.',
        });
    });

    it('las fechas no son posteriores a hoy; la de terminación no es anterior al inicio ni la de liquidación a la de terminación', () => {
        expect(erroresLiquidar({ ...formulario, fecha_terminacion_real: '2026-11-20', fecha_liquidacion: '2026-11-20' }, presentado, hoy)).toEqual({
            fecha_terminacion_real: 'La fecha de terminación no puede ser posterior a hoy.',
            fecha_liquidacion: 'La fecha de liquidación no puede ser posterior a hoy.',
        });
        expect(erroresLiquidar({ ...formulario, fecha_terminacion_real: '2026-01-01' }, presentado, hoy))
            .toEqual({ fecha_terminacion_real: 'La fecha de terminación no puede ser anterior a la de inicio.' });
        expect(erroresLiquidar({ ...formulario, fecha_liquidacion: '2026-10-30' }, presentado, hoy))
            .toEqual({ fecha_liquidacion: 'La fecha de liquidación no puede ser anterior a la de terminación.' });
    });

    it('el valor admite hasta 13 cifras enteras', () => {
        expect(erroresLiquidar({ ...formulario, valor_liquidado: '9.999.999.999.999,99' }, presentado, hoy)).toEqual({});
        expect(erroresLiquidar({ ...formulario, valor_liquidado: '10.000.000.000.000' }, presentado, hoy))
            .toEqual({ valor_liquidado: 'El valor liquidado admite hasta 13 cifras enteras y 2 decimales.' });
    });

    it('la observación admite hasta 2000 caracteres', () => {
        expect(erroresLiquidar({ ...formulario, observacion: 'a'.repeat(2001) }, presentado, hoy))
            .toEqual({ observacion: 'La observación admite hasta 2000 caracteres.' });
    });
});

describe('erroresCierre', () => {
    const error = (status, data) => ({ response: { status, data } });

    it('un 422 por campo: los del diálogo junto a su campo y los demás aparte', () => {
        expect(erroresCierre(error(422, { errors: { fecha_liquidacion: ['La fecha de liquidación no puede ser posterior a hoy.'], actualizado: ['Falta la versión.'] } }), CAMPOS_LIQUIDAR))
            .toEqual({ campos: { fecha_liquidacion: 'La fecha de liquidación no puede ser posterior a hoy.' }, general: 'Falta la versión.' });
    });

    it('un 422 por estado trae `mensaje`: va como error general', () => {
        expect(erroresCierre(error(422, { mensaje: 'El contrato está anulado.' }), CAMPOS_TERMINAR))
            .toEqual({ campos: {}, general: 'El contrato está anulado.' });
    });

    it('los campos del diálogo de terminar no incluyen los de liquidar', () => {
        expect(erroresCierre(error(422, { errors: { valor_liquidado: ['x'] } }), CAMPOS_TERMINAR)).toEqual({ campos: {}, general: 'x' });
    });

    it('otro error no es de validación: null', () => {
        expect(erroresCierre(error(409, { mensaje: 'Otra persona…' }), CAMPOS_TERMINAR)).toBeNull();
        expect(erroresCierre(error(500, {}), CAMPOS_TERMINAR)).toBeNull();
    });
});

describe('cambiosSinGuardar', () => {
    const anexos = [{ id: 1, descripcion: 'Póliza', nombre_archivo: 'p.pdf', ruta_archivo: 'anexos_contratos/p.pdf', fecha: '2026-01-05' }];
    const contrato = { ...presentado, anexos };
    const delContrato = anexosDelContrato(contrato);

    it('el formulario recién abierto no tiene cambios, del listado o de presentar', () => {
        expect(cambiosSinGuardar(formularioContrato(contrato), contrato, delContrato)).toBe(false);
        expect(cambiosSinGuardar(formularioContrato({ ...listado, anexos }), { ...listado, anexos }, delContrato)).toBe(false);
    });

    it('un campo escrito es un cambio; volver a lo guardado, no', () => {
        const formulario = formularioContrato(contrato);
        expect(cambiosSinGuardar({ ...formulario, objeto: 'Otro objeto' }, contrato, delContrato)).toBe(true);
        expect(cambiosSinGuardar({ ...formulario, objeto: 'Construcción del puente ' }, contrato, delContrato)).toBe(false);
        expect(cambiosSinGuardar({ ...formulario, valor_inicial: '1.500.000,5' }, contrato, delContrato)).toBe(false);
        expect(cambiosSinGuardar({ ...formulario, estado: 'suspendido' }, contrato, delContrato)).toBe(true);
    });

    it('el avance financiero que se recalcula solo con las actas no cuenta como cambio', () => {
        expect(cambiosSinGuardar({ ...formularioContrato(contrato), avance_financiero: 31.5 }, contrato, delContrato)).toBe(false);
    });

    it('lo copiado de SECOP II o una desvinculación pendiente son cambios', () => {
        expect(cambiosSinGuardar({ ...formularioContrato(contrato), secop_consultado: true }, contrato, delContrato)).toBe(true);
        expect(cambiosSinGuardar({ ...formularioContrato(contrato), secop_desvincular: true }, contrato, delContrato)).toBe(true);
    });

    it('un anexo subido que el contrato no tiene es un cambio; uno eliminado ya se guardó', () => {
        const nuevo = { id: 1700000000000, descripcion: 'Acta', nombreArchivo: 'a.pdf', rutaArchivo: 'anexos_contratos/a.pdf', fecha: '2026-11-19' };
        expect(cambiosSinGuardar(formularioContrato(contrato), contrato, [...delContrato, nuevo])).toBe(true);
        expect(cambiosSinGuardar(formularioContrato(contrato), contrato, [])).toBe(false);
    });

    it('sin contrato guardado, false', () => {
        expect(cambiosSinGuardar(contratoVacio(), null, [])).toBe(false);
    });
});

describe('contratoTrasCierre', () => {
    it('toma del contrato presentado el estado, los datos de cierre, su aviso y la versión; lo demás se conserva', () => {
        const abierto = { ...listado, anexos: [{ id: 1 }], modificaciones: [{ id: 2 }] };
        const respuesta = {
            ...presentado, estado: 'liquidado', fecha_terminacion_real: '2026-10-31', fecha_liquidacion: '2026-11-15',
            valor_liquidado: '1250000.50', aviso_cierre: null, updated_at: '2026-11-19T15:00:00-05:00', objeto: 'No se toma',
        };
        expect(contratoTrasCierre(abierto, respuesta)).toEqual({
            ...abierto, estado: 'liquidado', fecha_terminacion_real: '2026-10-31', fecha_liquidacion: '2026-11-15',
            valor_liquidado: '1250000.50', aviso_cierre: null, updated_at: '2026-11-19T15:00:00-05:00',
        });
    });
});

describe('avisoEstadoCierreSecop', () => {
    it('SECOP II propone terminado o liquidado y el contrato guardado no lo está', () => {
        expect(avisoEstadoCierreSecop('liquidado', 'en_ejecucion'))
            .toBe('SECOP II dice «Liquidado»: usa «Liquidar» para registrarlo con sus fechas y valor.');
        expect(avisoEstadoCierreSecop('terminado', 'en_ejecucion'))
            .toBe('SECOP II dice «Terminado»: usa «Terminar» para registrarlo con su fecha de terminación.');
    });

    it('un contrato liquidado al que SECOP II ve terminado: SECOP II va detrás, sin aviso', () => {
        expect(avisoEstadoCierreSecop('terminado', 'liquidado')).toBeNull();
    });

    it('null si el contrato ya está en ese estado, si SECOP II propone otro o si el contrato es nuevo', () => {
        expect(avisoEstadoCierreSecop('liquidado', 'liquidado')).toBeNull();
        expect(avisoEstadoCierreSecop('en_ejecucion', 'liquidado')).toBeNull();
        expect(avisoEstadoCierreSecop(undefined, 'en_ejecucion')).toBeNull();
        expect(avisoEstadoCierreSecop('liquidado', null, true)).toBeNull();
        expect(avisoEstadoCierreSecop('terminado', '', true)).toBeNull();
    });

    it('un contrato guardado sin estado también recibe el aviso: lo decide «es nuevo», no el estado vacío', () => {
        expect(avisoEstadoCierreSecop('liquidado', null, false))
            .toBe('SECOP II dice «Liquidado»: usa «Liquidar» para registrarlo con sus fechas y valor.');
        expect(avisoEstadoCierreSecop('terminado', '', false))
            .toBe('SECOP II dice «Terminado»: usa «Terminar» para registrarlo con su fecha de terminación.');
        expect(avisoEstadoCierreSecop('liquidado', null))
            .toBe('SECOP II dice «Liquidado»: usa «Liquidar» para registrarlo con sus fechas y valor.');
    });
});

describe('estadoCierreBloqueado', () => {
    it('SECOP II no cambia el estado de un contrato guardado a terminado ni a liquidado', () => {
        expect(estadoCierreBloqueado('liquidado', 'en_ejecucion')).toBe(true);
        expect(estadoCierreBloqueado('terminado', 'en_ejecucion')).toBe(true);
        expect(estadoCierreBloqueado('terminado', 'liquidado')).toBe(true);
        expect(estadoCierreBloqueado('liquidado', 'terminado')).toBe(true);
    });

    it('sí lo deja si ya está en ese estado, si es otro estado o si el contrato es nuevo', () => {
        expect(estadoCierreBloqueado('liquidado', 'liquidado')).toBe(false);
        expect(estadoCierreBloqueado('en_ejecucion', 'liquidado')).toBe(false);
        expect(estadoCierreBloqueado(undefined, 'en_ejecucion')).toBe(false);
        expect(estadoCierreBloqueado('liquidado', null, true)).toBe(false);
        expect(estadoCierreBloqueado('terminado', '', true)).toBe(false);
    });

    it('un contrato guardado con estado null o vacío tampoco lo toma de SECOP II', () => {
        expect(estadoCierreBloqueado('liquidado', null, false)).toBe(true);
        expect(estadoCierreBloqueado('terminado', '', false)).toBe(true);
        expect(estadoCierreBloqueado('liquidado', undefined)).toBe(true);
        expect(estadoCierreBloqueado('en_ejecucion', null, false)).toBe(false);
    });
});

describe('muestraBloqueCierre', () => {
    it('terminado o liquidado, aunque no tenga datos', () => {
        expect(muestraBloqueCierre({ ...presentado, estado: 'terminado' })).toBe(true);
        expect(muestraBloqueCierre({ ...presentado, estado: 'liquidado', fecha_terminacion_real: null })).toBe(true);
    });

    it('en otro estado, solo si conserva algún dato de cierre (salió de terminado por el formulario)', () => {
        expect(muestraBloqueCierre(presentado)).toBe(false);
        expect(muestraBloqueCierre({ ...presentado, fecha_terminacion_real: null, fecha_liquidacion: null, valor_liquidado: null })).toBe(false);
        expect(muestraBloqueCierre({ ...presentado, estado: 'en_ejecucion', fecha_terminacion_real: '2026-10-31' })).toBe(true);
        expect(muestraBloqueCierre({ ...presentado, valor_liquidado: '0.00' })).toBe(true);
    });

    it('sin contrato, no', () => {
        expect(muestraBloqueCierre(null)).toBe(false);
    });
});
```

En `resources/js/utils/secop.test.js`, reemplazar (1 de 1):

```js
        expect(mensajeSecop({ request: {} })).toBe('No hay conexión con el servidor.');
    });
});
```

por:

```js
        expect(mensajeSecop({ request: {} })).toBe('No hay conexión con el servidor.');
    });
});

describe('estado de cierre que propone SECOP II (fase 7A)', () => {
    const liquidadoEnSecop = { ...conContrato({ estado: 'liquidado' }), secop: { ...resultado.secop, estado_secop: 'Liquidado' } };
    const terminadoEnSecop = { ...conContrato({ estado: 'terminado' }), secop: { ...resultado.secop, estado_secop: 'Terminado' } };

    it('en un contrato guardado, el estado terminado o liquidado de SECOP II no se ofrece ni se copia', () => {
        const formulario = formularioContrato(guardado);
        expect(diferenciasSecop(formulario, liquidadoEnSecop, { tipos, contrato: guardado })).toEqual([]);
        expect(diferenciasSecop({ ...formulario, objeto: 'X' }, terminadoEnSecop, { tipos, contrato: guardado }).map((d) => d.campo)).toEqual(['objeto']);
        expect(aplicarDiferencias(formulario, liquidadoEnSecop, ['estado'], guardado).estado).toBe('en_ejecucion');
    });

    it('y avisa qué acción usar', () => {
        expect(avisosSecop(liquidadoEnSecop, formularioContrato(guardado), guardado))
            .toEqual(['SECOP II dice «Liquidado»: usa «Liquidar» para registrarlo con sus fechas y valor.']);
        expect(avisosSecop(terminadoEnSecop, formularioContrato(guardado), guardado))
            .toEqual(['SECOP II dice «Terminado»: usa «Terminar» para registrarlo con su fecha de terminación.']);
    });

    it('si el contrato guardado ya está en ese estado, nada cambia', () => {
        const liquidado = { ...guardado, estado: 'liquidado' };
        expect(diferenciasSecop(formularioContrato(liquidado), liquidadoEnSecop, { tipos, contrato: liquidado })).toEqual([]);
        expect(avisosSecop(liquidadoEnSecop, formularioContrato(liquidado), liquidado)).toEqual([]);
    });

    it('un contrato guardado sin estado tampoco toma el estado de cierre de SECOP II, y se avisa', () => {
        const sinEstado = { ...guardado, estado: null };
        const formulario = formularioContrato(sinEstado);
        expect(diferenciasSecop(formulario, liquidadoEnSecop, { tipos, contrato: sinEstado }).map((d) => d.campo)).not.toContain('estado');
        expect(aplicarDiferencias(formulario, liquidadoEnSecop, ['estado'], sinEstado).estado).toBe('');
        expect(avisosSecop(liquidadoEnSecop, formulario, sinEstado))
            .toContain('SECOP II dice «Liquidado»: usa «Liquidar» para registrarlo con sus fechas y valor.');
        expect(avisosSecop(terminadoEnSecop, formulario, sinEstado))
            .toContain('SECOP II dice «Terminado»: usa «Terminar» para registrarlo con su fecha de terminación.');
    });

    it('un contrato nuevo sí toma el estado de cierre de SECOP II, sin aviso', () => {
        const importado = importarSecop(contratoVacio(), liquidadoEnSecop);
        expect(importado.estado).toBe('liquidado');
        expect(avisosSecop(liquidadoEnSecop, importado)).toEqual([]);
        expect(diferenciasSecop({ ...importado, estado: 'en_ejecucion' }, liquidadoEnSecop, { tipos }).map((d) => d.campo)).toEqual(['estado']);
    });
});
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/contratos.test.js resources/js/utils/secop.test.js`
Expected: FALLAN 57 y pasan 111, en los 2 archivos: en `contratos.test.js`, las nuevas de `accionesCierre`, `notaSinEstadoCierre`, `textoAvisoCierre`, `saldoLiquidacion`, `opcionesEstadoContrato`, los formularios y errores de los diálogos, `contratoTrasCierre`, `cambiosSinGuardar` y `muestraBloqueCierre` ("accionesCierre is not a function" y similares); en `secop.test.js`, las del estado de cierre que SECOP II no debe aplicar a un contrato guardado.

- [ ] **Paso 3: Implementar**

Crear `resources/js/components/contratos/CierreContrato.jsx`:

```jsx
import React, { useEffect, useRef, useState } from 'react';
import axios from '../../axios';
import { MAXIMO_OBSERVACION, esEdicionDesactualizada, textoFecha, textoValor, valorEnEdicion, valorParaApi } from '../../utils/contratacion';
import {
    CAMPOS_LIQUIDAR, CAMPOS_TERMINAR, datosLiquidar, datosTerminar, erroresCierre, erroresLiquidar, erroresTerminar, formularioLiquidar,
    formularioTerminar, saldoLiquidacion, textoAvisoCierre,
} from '../../utils/contratos';
import { fechaHoyBogota } from '../../utils/expedienteContratacion';
import { mensajeApi } from '../../utils/plantillasChecklist';
import { DialogoSecop } from './SecopContrato';
import '../../../css/Plantillas.css';
import '../../../css/Contratacion.css';

// Lo que cambia entre los dos diálogos: la ruta, los campos y sus textos.
const TIPOS = {
    terminar: {
        ruta: 'terminar',
        campos: CAMPOS_TERMINAR,
        formulario: formularioTerminar,
        datos: datosTerminar,
        errores: erroresTerminar,
    },
    liquidar: {
        ruta: 'liquidar',
        campos: CAMPOS_LIQUIDAR,
        formulario: formularioLiquidar,
        datos: datosLiquidar,
        errores: erroresLiquidar,
    },
};

const textos = (tipo, contrato) => {
    if (tipo === 'terminar') {
        return contrato?.estado === 'terminado'
            ? { titulo: 'Corregir la terminación', boton: 'Guardar corrección', explicacion: 'Corrige la fecha en que el contrato terminó. El cambio queda en el historial del contrato.' }
            : {
                titulo: 'Terminar el contrato',
                boton: 'Terminar',
                explicacion: 'El contrato pasa a «Terminado» con la fecha en que terminó. El valor, la fecha fin y los avances no cambian. El cambio queda en el historial del contrato.',
            };
    }
    return contrato?.estado === 'liquidado'
        ? { titulo: 'Corregir la liquidación', boton: 'Guardar liquidación', explicacion: 'Completa o corrige los datos de la liquidación. El cambio queda en el historial del contrato.' }
        : {
            titulo: 'Liquidar el contrato',
            boton: 'Liquidar',
            explicacion: 'El contrato pasa a «Liquidado» con su fecha de terminación, la de liquidación y el valor liquidado. El valor vigente, la fecha fin y los avances no cambian. El cambio queda en el historial del contrato.',
        };
};

/**
 * El diálogo de «Terminar» o «Liquidar» (`tipo`) de un contrato guardado: sus fechas, el valor
 * liquidado y una observación opcional. Valida como el servidor (con hoy en Bogotá) y muestra
 * junto a cada campo los errores de un 422.
 *
 * - `onGuardado(data)`: la respuesta ({ contrato, avisos }); quien lo pinta cierra el diálogo.
 * - `onDesactualizado(error)`: un 409 (otra persona cambió el contrato); quien lo pinta avisa.
 * - `onCerrar()`: Cancelar, la "×", Escape o un clic en el fondo (no mientras se guarda).
 */
export const DialogoCierre = ({ tipo, contrato, onGuardado, onDesactualizado, onCerrar }) => {
    const config = TIPOS[tipo];
    const { titulo, boton, explicacion } = textos(tipo, contrato);
    const [formulario, setFormulario] = useState(() => config.formulario(contrato));
    const [errores, setErrores] = useState({});
    const [general, setGeneral] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [foco, setFoco] = useState(0);
    const cuerpo = useRef(null);
    const hoy = fechaHoyBogota();

    // Tras un error, el foco va al primer campo con error.
    useEffect(() => {
        if (!foco) return;
        const campo = config.campos.find((c) => errores[c]);
        if (campo) cuerpo.current?.querySelector(`[name="${campo}"]`)?.focus();
    }, [foco]);

    const cambiar = (e) => {
        const { name, value } = e.target;
        setErrores((prev) => (prev[name] ? { ...prev, [name]: undefined } : prev));
        setFormulario((prev) => ({ ...prev, [name]: name === 'valor_liquidado' ? valorEnEdicion(value, prev.valor_liquidado) : value }));
    };

    const enviar = async (e) => {
        e.preventDefault();
        if (enviando) return;
        const locales = config.errores(formulario, contrato, hoy);
        setErrores(locales);
        setGeneral(null);
        if (Object.keys(locales).length) {
            setFoco((n) => n + 1);
            return;
        }
        setEnviando(true);
        try {
            const { data } = await axios.post(`/contratos/${contrato.id}/${config.ruta}`, config.datos(formulario, contrato));
            onGuardado(data);
        } catch (error) {
            setEnviando(false);
            if (esEdicionDesactualizada(error)) {
                onDesactualizado(error);
                return;
            }
            const delServidor = erroresCierre(error, config.campos);
            if (delServidor) {
                setErrores(delServidor.campos);
                setGeneral(delServidor.general);
                if (Object.keys(delServidor.campos).length) setFoco((n) => n + 1);
                return;
            }
            setGeneral(mensajeApi(error));
        }
    };

    const cerrar = () => { if (!enviando) onCerrar(); };

    // Un campo con su etiqueta y su error.
    const campo = (nombre, etiqueta, control, nota = null) => (
        <div className="pl-campo">
            <label htmlFor={`cierre-${nombre}`}>{etiqueta}</label>
            {React.cloneElement(control, {
                id: `cierre-${nombre}`,
                name: nombre,
                value: formulario[nombre] ?? '',
                onChange: cambiar,
                disabled: enviando,
                'aria-invalid': Boolean(errores[nombre]),
                'aria-describedby': [errores[nombre] ? `cierre-${nombre}-error` : null, nota ? `cierre-${nombre}-nota` : null].filter(Boolean).join(' ') || undefined,
            })}
            {nota && <small id={`cierre-${nombre}-nota`} className="ct-secop-nota">{nota}</small>}
            {errores[nombre] && <small id={`cierre-${nombre}-error`} className="ct-error-campo" role="alert">{errores[nombre]}</small>}
        </div>
    );

    const inicio = String(contrato?.fecha_inicio ?? '').slice(0, 10) || undefined;
    // El saldo con lo que se está escribiendo: avisa (sin impedir) si lo liquidado supera lo vigente.
    const saldo = tipo === 'liquidar' ? saldoLiquidacion({ valor: contrato?.valor, valor_liquidado: valorParaApi(formulario.valor_liquidado) }) : null;

    return (
        <DialogoSecop titulo={titulo} etiqueta={`Contrato ${contrato?.n_contrato || 'sin número'}`} estrecho onCerrar={cerrar}>
            <form ref={cuerpo} className="ct-cierre-formulario" onSubmit={enviar} noValidate>
                <p className="contrato-nota ct-cierre-explicacion">{explicacion}</p>
                {general && <p className="pl-error ct-cierre-explicacion" role="alert">{general}</p>}
                {campo('fecha_terminacion_real', 'Fecha de terminación real *', <input type="date" min={inicio} max={hoy} />)}
                {tipo === 'liquidar' && campo('fecha_liquidacion', 'Fecha de liquidación *', (
                    <input type="date" min={formulario.fecha_terminacion_real || undefined} max={hoy} />
                ))}
                {tipo === 'liquidar' && campo('valor_liquidado', 'Valor liquidado *', <input type="text" inputMode="decimal" placeholder="0" />,
                    `Valor vigente: ${textoValor(contrato?.valor)}`)}
                {saldo !== null && (
                    <p className={`ct-cierre-saldo${saldo.startsWith('-') ? ' ct-cierre-supera' : ''}`} aria-live="polite">
                        Saldo (vigente − liquidado): <span className="ct-valor">{textoValor(saldo)}</span>
                        {saldo.startsWith('-') && <> · El valor liquidado supera el valor vigente del contrato: puedes guardarlo igual.</>}
                    </p>
                )}
                <div className="pl-campo ct-cierre-ancho">
                    <label htmlFor="cierre-observacion">Observación (opcional)</label>
                    <textarea
                        id="cierre-observacion"
                        name="observacion"
                        rows="3"
                        maxLength={MAXIMO_OBSERVACION}
                        placeholder="Por ejemplo, el acta de terminación o de liquidación"
                        value={formulario.observacion}
                        onChange={cambiar}
                        disabled={enviando}
                        aria-invalid={Boolean(errores.observacion)}
                        aria-describedby={errores.observacion ? 'cierre-observacion-error' : undefined}
                    />
                    {errores.observacion && <small id="cierre-observacion-error" className="ct-error-campo" role="alert">{errores.observacion}</small>}
                </div>
                <div className="pl-formulario-acciones ct-cierre-ancho">
                    <button type="button" className="gp-boton" onClick={cerrar} disabled={enviando}>Cancelar</button>
                    <button type="submit" className="gp-boton gp-boton-primario" disabled={enviando}>{enviando ? 'Guardando…' : boton}</button>
                </div>
            </form>
        </DialogoSecop>
    );
};

/**
 * El bloque "Cierre" de la pestaña de información (si muestraBloqueCierre), de solo lectura: las fechas de terminación y
 * de liquidación, el valor liquidado, el saldo (vigente − liquidado) y el aviso de datos que faltan.
 */
export const BloqueCierre = ({ contrato }) => {
    const aviso = textoAvisoCierre(contrato?.aviso_cierre);
    const saldo = saldoLiquidacion(contrato);
    return (
        <section className="ct-cierre" aria-labelledby="cierre-titulo">
            <div className="ct-cierre-cabecera">
                <h4 id="cierre-titulo">Cierre</h4>
                {aviso && <span className="ct-estado ct-estado-ambar">{aviso}</span>}
            </div>
            <dl className="contrato-proceso-datos ct-cierre-datos">
                <div><dt>Fecha de terminación real</dt><dd>{textoFecha(contrato?.fecha_terminacion_real)}</dd></div>
                <div><dt>Fecha de liquidación</dt><dd>{textoFecha(contrato?.fecha_liquidacion)}</dd></div>
                <div><dt>Valor liquidado</dt><dd className="ct-valor">{textoValor(contrato?.valor_liquidado)}</dd></div>
                <div><dt>Saldo (vigente − liquidado)</dt><dd className="ct-valor">{saldo === null ? '—' : textoValor(saldo)}</dd></div>
            </dl>
        </section>
    );
};
```

En `resources/css/Contratacion.css`, reemplazar (1 de 1):

```css
    /* Sobre la barra inferior del armazón. */
    .ct-editor .modal-actions { bottom: calc(var(--gp-barra-inferior) + env(safe-area-inset-bottom)); }
}
```

por:

```css
    /* Sobre la barra inferior del armazón. */
    .ct-editor .modal-actions { bottom: calc(var(--gp-barra-inferior) + env(safe-area-inset-bottom)); }
}

/* ---------- Cierre del contrato: terminar y liquidar (fase 7A) ---------- */

/* Botones junto al título del editor, con la nota de cambios sin guardar debajo. */
.ct-cierre-acciones { display: flex; flex-wrap: wrap; gap: .4rem .5rem; align-items: center; margin-left: auto; }
.ct-cierre-nota { flex-basis: 100%; font-size: .78rem; color: var(--gp-texto-3); text-align: right; }
/* Con cambios sin guardar: se ve deshabilitado pero sigue enfocable (aria-disabled), con la nota. */
.ct-cierre-acciones .gp-boton[aria-disabled="true"] { opacity: .5; cursor: not-allowed; }
/* Bloque "Cierre" de la pestaña de información, de solo lectura. */
.ct-cierre { display: grid; gap: .45rem; margin: 0 0 1rem; }
.ct-cierre-cabecera { display: flex; flex-wrap: wrap; gap: .4rem .8rem; align-items: center; }
.ct-cierre-cabecera h4 { margin: 0; font-size: .95rem; }
.ct-cierre-datos { margin: 0; }
/* Chip de datos de cierre que faltan, en las listas, bajo el estado. */
.ct-aviso-cierre { display: block; width: fit-content; max-width: 12rem; margin-top: .25rem; white-space: normal; border-radius: .5rem; }
/* Diálogo de terminar o liquidar: un formulario corto. */
.ct-dialogo.ct-dialogo-estrecho { width: min(36rem, 100%); }
.ct-cierre-formulario { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 13rem), 1fr)); gap: .9rem 1.1rem; }
.ct-cierre-explicacion, .ct-cierre-ancho, .ct-cierre-saldo { grid-column: 1 / -1; margin: 0; }
.ct-cierre-saldo { font-size: .86rem; color: var(--gp-texto-2); }
.ct-cierre-supera { padding: .45rem .7rem; color: var(--gp-texto); background: var(--gp-ambar-suave); border-left: 3px solid var(--gp-ambar); border-radius: var(--gp-radio); }

@media (max-width: 720px) {
    .ct-cierre-acciones { width: 100%; margin-left: 0; }
    .ct-cierre-acciones .gp-boton { flex: 1 1 auto; justify-content: center; }
    .ct-cierre-nota { text-align: left; }
}
```

En `resources/js/components/FichaProyecto.jsx`, reemplazar (1 de 3):

```jsx
import axios from '../axios';
import { datosGenerales, errorDeFicha, esIdProyecto, filaProceso, resumenFormulacion } from '../utils/fichaProyecto';
import { estadoDesdeFicha, estadoNuevoContratoDeProyecto, filaContrato, puedeEditarContratos, rutaContrato } from '../utils/contratosModulo';
import { RUTA_CONTRATOS } from '../utils/navegacion';
import ChipsAvisosProceso from './ChipsAvisosProceso';
import ProcesosProyecto from './ProcesosProyecto';
```

por:

```jsx
import axios from '../axios';
import { datosGenerales, errorDeFicha, esIdProyecto, filaProceso, resumenFormulacion } from '../utils/fichaProyecto';
import { estadoDesdeFicha, estadoNuevoContratoDeProyecto, filaContrato, puedeEditarContratos, rutaContrato } from '../utils/contratosModulo';
import { textoAvisoCierre } from '../utils/contratos';
import { RUTA_CONTRATOS } from '../utils/navegacion';
import ChipsAvisosProceso from './ChipsAvisosProceso';
import ProcesosProyecto from './ProcesosProyecto';
```

En `resources/js/components/FichaProyecto.jsx`, reemplazar (2 de 3):

```jsx
};

const Contratos = ({ proyecto, contratos, edita }) => {
    const filas = contratos.map(filaContrato);
    return (
        <Bloque
            titulo="Contratos"
```

por:

```jsx
};

const Contratos = ({ proyecto, contratos, edita }) => {
    // Como en la lista del módulo Contratos: con el aviso de datos de cierre que faltan.
    const filas = contratos.map((c) => ({ ...filaContrato(c), avisoCierre: textoAvisoCierre(c.aviso_cierre) }));
    return (
        <Bloque
            titulo="Contratos"
```

En `resources/js/components/FichaProyecto.jsx`, reemplazar (3 de 3):

```jsx
                                <tr key={f.id}>
                                    <td data-etiqueta="Número"><Link to={rutaContrato(f.id)} state={estadoDesdeFicha(proyecto.id)} className="ct-enlace">{f.numero}</Link></td>
                                    <td data-etiqueta="Objeto"><span className="ct-objeto" title={f.objeto}>{f.objeto}</span></td>
                                    <td data-etiqueta="Estado"><span className={`ct-estado ct-estado-${f.tono}`}>{f.estado}</span></td>
                                    <td data-etiqueta="Valor vigente" className="numero">{f.valor}</td>
                                    <td data-etiqueta="Físico" className="numero">{f.avanceFisico}</td>
                                    <td data-etiqueta="Financiero" className="numero">{f.avanceFinanciero}</td>
```

por:

```jsx
                                <tr key={f.id}>
                                    <td data-etiqueta="Número"><Link to={rutaContrato(f.id)} state={estadoDesdeFicha(proyecto.id)} className="ct-enlace">{f.numero}</Link></td>
                                    <td data-etiqueta="Objeto"><span className="ct-objeto" title={f.objeto}>{f.objeto}</span></td>
                                    <td data-etiqueta="Estado">
                                        <span className={`ct-estado ct-estado-${f.tono}`}>{f.estado}</span>
                                        {f.avisoCierre && <span className="ct-aviso-cierre ct-estado ct-estado-ambar">{f.avisoCierre}</span>}
                                    </td>
                                    <td data-etiqueta="Valor vigente" className="numero">{f.valor}</td>
                                    <td data-etiqueta="Físico" className="numero">{f.avanceFisico}</td>
                                    <td data-etiqueta="Financiero" className="numero">{f.avanceFinanciero}</td>
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (1 de 16):

```jsx
import React, { useEffect, useRef, useState } from 'react';
import {
    faArrowUpRightFromSquare, faCalculator, faEye, faFile, faInfo, faLinkSlash, faMagnifyingGlass, faPlus, faRotate, faSave, faTimes, faTrash,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
```

por:

```jsx
import React, { useEffect, useId, useRef, useState } from 'react';
import {
    faArrowUpRightFromSquare, faCalculator, faEye, faFile, faFileSignature, faFlagCheckered, faInfo, faLinkSlash, faMagnifyingGlass, faPlus, faRotate,
    faSave, faTimes, faTrash,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (2 de 16):

```jsx
import { avisarError } from '../../utils/avisosPlantillas';
import { datosEstado, esEdicionDesactualizada, textoFecha, textoValor, valorEnEdicion } from '../../utils/contratacion';
import {
    ACTA_VACIA, ACTIVIDAD_VACIA, ANEXO_VACIO, AVANCE_VACIO, ESTADOS_CONTRATO, MODIFICACION_VACIA, actividadConAvance, anexosDelContrato,
    anexosParaGuardar, aplicarPrecarga, avanceFinancieroDeActas, avanceFisicoDeActividades, calculoActa, cambiarProyecto, contratoVacio,
    datosActa, datosContrato, datosDelProceso, datosModificacion, erroresGuardarContrato, etiquetaTipoModificacion, formularioContrato,
    notaProceso, opcionesProceso, opcionesTipoContrato, primerCampoConError, procesoDelContrato, procesoElegido, sumaPesos, validarContrato,
    valorVigente, vigentesDistintos,
} from '../../utils/contratos';
import { opcionesProyecto } from '../../utils/contratosModulo';
import { formatoMoneda, limpiarMoneda, monedaEnEdicion, numeroDeMoneda } from '../../utils/moneda';
```

por:

```jsx
import { avisarError } from '../../utils/avisosPlantillas';
import { datosEstado, esEdicionDesactualizada, textoFecha, textoValor, valorEnEdicion } from '../../utils/contratacion';
import {
    ACTA_VACIA, ACTIVIDAD_VACIA, ANEXO_VACIO, AVANCE_VACIO, MODIFICACION_VACIA, accionesCierre, actividadConAvance, anexosDelContrato,
    anexosParaGuardar, aplicarPrecarga, avanceFinancieroDeActas, avanceFisicoDeActividades, calculoActa, cambiarProyecto, cambiosSinGuardar,
    contratoTrasCierre, contratoVacio, datosActa, datosContrato, datosDelProceso, datosModificacion, erroresGuardarContrato,
    etiquetaTipoModificacion, formularioContrato, muestraBloqueCierre, notaProceso, notaSinEstadoCierre, opcionesEstadoContrato, opcionesProceso,
    opcionesTipoContrato, primerCampoConError, procesoDelContrato, procesoElegido, sumaPesos, validarContrato, valorVigente, vigentesDistintos,
} from '../../utils/contratos';
import { opcionesProyecto } from '../../utils/contratosModulo';
import { formatoMoneda, limpiarMoneda, monedaEnEdicion, numeroDeMoneda } from '../../utils/moneda';
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (3 de 16):

```jsx
    TEXTO_REEMPLAZAR_CON_SECOP, aplicarDiferencias, avisosSecop, camposCambiados, desvincularSecop, diferenciasSecop, formularioTieneDatos, importarSecop,
    mensajeSecop, referenciaSecop, vincularSecop,
} from '../../utils/secop';
import { BuscarSecop, DiferenciasSecop } from './SecopContrato';
import '../../../css/Parametros.css';
import '../../../css/Contratacion.css';
```

por:

```jsx
    TEXTO_REEMPLAZAR_CON_SECOP, aplicarDiferencias, avisosSecop, camposCambiados, desvincularSecop, diferenciasSecop, formularioTieneDatos, importarSecop,
    mensajeSecop, referenciaSecop, vincularSecop,
} from '../../utils/secop';
import { BloqueCierre, DialogoCierre } from './CierreContrato';
import { BuscarSecop, DiferenciasSecop } from './SecopContrato';
import '../../../css/Parametros.css';
import '../../../css/Contratacion.css';
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (4 de 16):

```jsx
const MENSAJE_IMPORTADO_SECOP = 'Formulario lleno con los datos de SECOP II: revísalos y guarda el contrato.';
const MENSAJE_COINCIDE_SECOP = 'Coincide con SECOP II: guarda el contrato para registrar la consulta.';
const MENSAJE_VINCULADO_SECOP = 'Contrato vinculado a SECOP II: guárdalo para conservar el vínculo.';

/** Catálogo de tipos de contrato (GET /tiposProcesos); null si no se pudo traer. */
export const pedirTiposContrato = async () => {
```

por:

```jsx
const MENSAJE_IMPORTADO_SECOP = 'Formulario lleno con los datos de SECOP II: revísalos y guarda el contrato.';
const MENSAJE_COINCIDE_SECOP = 'Coincide con SECOP II: guarda el contrato para registrar la consulta.';
const MENSAJE_VINCULADO_SECOP = 'Contrato vinculado a SECOP II: guárdalo para conservar el vínculo.';
const NOTA_CAMBIOS_SIN_GUARDAR = 'Guarda o descarta tus cambios primero';

/** Catálogo de tipos de contrato (GET /tiposProcesos); null si no se pudo traer. */
export const pedirTiposContrato = async () => {
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (5 de 16):

```jsx
 *   con proyecto puede cambiarlo, pero no quitarlo. Al cambiarlo se cargan los procesos del
 *   elegido. Sin la prop, el proyecto es el de `proyecto` o el del contrato, sin selector.
 * - `puedeEditar`: sin permiso, el contrato se ve sin controles para modificarlo.
 *   Con permiso, el formulario ofrece SECOP II: "Buscar en SECOP II" (contrato nuevo o sin ID de
 *   SECOP II) y "Actualizar desde SECOP II" (con ID). Lo que se copia de SECOP II se guarda con el contrato.
 * - `recargar(id)`: vuelve a traer los contratos tras un cambio en las pestañas; resuelve el contrato `id` recargado o null.
 * - `onGuardado(contrato)`: tras guardar (con los avisos ya mostrados); quien lo pinta cierra el editor.
 * - `onCancelar()`: "Cancelar" o "Volver a la lista".
 * - `onOcupado(ocupado)` (opcional): true al empezar a guardar y false al terminar (tras `onGuardado`,
```

por:

```jsx
 *   con proyecto puede cambiarlo, pero no quitarlo. Al cambiarlo se cargan los procesos del
 *   elegido. Sin la prop, el proyecto es el de `proyecto` o el del contrato, sin selector.
 * - `puedeEditar`: sin permiso, el contrato se ve sin controles para modificarlo.
 *   Con permiso, un contrato guardado ofrece «Terminar» y «Liquidar» (o sus correcciones), cada uno
 *   con su diálogo; quedan deshabilitados mientras el formulario tenga cambios sin guardar.
 *   Con permiso, el formulario ofrece SECOP II: "Buscar en SECOP II" (contrato nuevo o sin ID de
 *   SECOP II) y "Actualizar desde SECOP II" (con ID). Lo que se copia de SECOP II se guarda con el contrato.
 * - `recargar(id)`: vuelve a traer los contratos tras un cambio en las pestañas; resuelve el contrato `id` recargado o null.
 * - `onCambio()` (opcional): tras terminar o liquidar el contrato, para que quien lo pinta (y quien
 *   pinta a este) recargue lo suyo: el contrato tiene una versión nueva.
 * - `onGuardado(contrato)`: tras guardar (con los avisos ya mostrados); quien lo pinta cierra el editor.
 * - `onCancelar()`: "Cancelar" o "Volver a la lista".
 * - `onOcupado(ocupado)` (opcional): true al empezar a guardar y false al terminar (tras `onGuardado`,
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (6 de 16):

```jsx
 */
const ContratoEditor = ({
    proyecto = null, contrato = null, procesoInicial = null, importacionSecop = null, tiposContrato, procesosIniciales = null, proyectos, puedeEditar = true,
    recargar, onGuardado, onCancelar, onOcupado,
}) => {
    const [editingContrato, setEditingContrato] = useState(contrato);
    const [contratoActiveTab, setContratoActiveTab] = useState('informacion');
```

por:

```jsx
 */
const ContratoEditor = ({
    proyecto = null, contrato = null, procesoInicial = null, importacionSecop = null, tiposContrato, procesosIniciales = null, proyectos, puedeEditar = true,
    recargar, onGuardado, onCancelar, onOcupado, onCambio,
}) => {
    const [editingContrato, setEditingContrato] = useState(contrato);
    const [contratoActiveTab, setContratoActiveTab] = useState('informacion');
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (7 de 16):

```jsx
    const [mensajeDeSecop, setMensajeDeSecop] = useState(importadoDeSecop ? MENSAJE_IMPORTADO_SECOP : null);
    const [consultandoSecop, setConsultandoSecop] = useState(false);
    const turnoSecop = useRef(0);
    // El formulario del último render, para comparar con SECOP II cuando llega la respuesta.
    const formularioActual = useRef(formContrato);
    formularioActual.current = formContrato;
```

por:

```jsx
    const [mensajeDeSecop, setMensajeDeSecop] = useState(importadoDeSecop ? MENSAJE_IMPORTADO_SECOP : null);
    const [consultandoSecop, setConsultandoSecop] = useState(false);
    const turnoSecop = useRef(0);
    // Cierre del contrato: el diálogo abierto ('terminar', 'liquidar' o null).
    const [dialogoCierre, setDialogoCierre] = useState(null);
    const idNotaCierre = useId();
    // El formulario del último render, para comparar con SECOP II cuando llega la respuesta.
    const formularioActual = useRef(formContrato);
    formularioActual.current = formContrato;
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (8 de 16):

```jsx
        if (!diferencias.length) {
            const consultado = aplicarDiferencias(formulario, resultado, [], editingContrato);
            setDialogoSecop(null);
            copiarAlFormulario(consultado, avisosSecop(resultado, consultado), mensajeSinDiferencias);
            return;
        }
        setDialogoSecop({ tipo: 'diferencias', titulo, resultado, diferencias, avisos: avisosSecop(resultado, formulario) });
    };

    // "Usar este": un contrato nuevo se llena con lo de SECOP II (si ya tenía datos, se confirma);
```

por:

```jsx
        if (!diferencias.length) {
            const consultado = aplicarDiferencias(formulario, resultado, [], editingContrato);
            setDialogoSecop(null);
            copiarAlFormulario(consultado, avisosSecop(resultado, consultado, editingContrato), mensajeSinDiferencias);
            return;
        }
        setDialogoSecop({ tipo: 'diferencias', titulo, resultado, diferencias, avisos: avisosSecop(resultado, formulario, editingContrato) });
    };

    // "Usar este": un contrato nuevo se llena con lo de SECOP II (si ya tenía datos, se confirma);
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (9 de 16):

```jsx
    const usarResultadoSecop = async (resultado) => {
        if (editingContrato) {
            const vinculado = vincularSecop(formularioActual.current, resultado);
            copiarAlFormulario(vinculado, avisosSecop(resultado, vinculado), MENSAJE_VINCULADO_SECOP);
            mostrarDiferencias(vinculado, resultado, '¿Copiar también los demás datos?', MENSAJE_VINCULADO_SECOP);
            return;
        }
```

por:

```jsx
    const usarResultadoSecop = async (resultado) => {
        if (editingContrato) {
            const vinculado = vincularSecop(formularioActual.current, resultado);
            copiarAlFormulario(vinculado, avisosSecop(resultado, vinculado, editingContrato), MENSAJE_VINCULADO_SECOP);
            mostrarDiferencias(vinculado, resultado, '¿Copiar también los demás datos?', MENSAJE_VINCULADO_SECOP);
            return;
        }
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (10 de 16):

```jsx
    const copiarDiferencias = (elegidos) => {
        const { resultado } = dialogoSecop;
        const aplicado = aplicarDiferencias(formularioActual.current, resultado, elegidos, editingContrato);
        copiarAlFormulario(aplicado, avisosSecop(resultado, aplicado),
            'Datos de SECOP II copiados al formulario: guarda el contrato para conservarlos.');
        setDialogoSecop(null);
    };
```

por:

```jsx
    const copiarDiferencias = (elegidos) => {
        const { resultado } = dialogoSecop;
        const aplicado = aplicarDiferencias(formularioActual.current, resultado, elegidos, editingContrato);
        copiarAlFormulario(aplicado, avisosSecop(resultado, aplicado, editingContrato),
            'Datos de SECOP II copiados al formulario: guarda el contrato para conservarlos.');
        setDialogoSecop(null);
    };
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (11 de 16):

```jsx
        onGuardado(guardado);
    };

    // Refrescar los datos del contrato abierto tras registrar o eliminar una modificación
    const refrescarContratoAbierto = async () => {
        const actual = await recargar(formContrato.id);
```

por:

```jsx
        onGuardado(guardado);
    };

    // ---------- Cierre del contrato: terminar y liquidar ----------

    // Recarga los contratos de quien pinta el editor sin dejar una promesa rechazada: true si se
    // recargaron, false si falló.
    const recargarTrasCierre = async (id) => {
        try {
            await recargar?.(id);
            return true;
        } catch {
            return false;
        }
    };

    // Avisa a quien pinta el editor (p. ej. Parámetros recarga sus proyectos); un fallo suyo no
    // deja una promesa sin manejar.
    const avisarCambio = () => {
        Promise.resolve().then(() => onCambio?.()).catch(() => {});
    };

    // Tras terminar o liquidar: el contrato abierto toma el estado, los datos de cierre y la
    // versión nueva (así el formulario se puede seguir guardando sin 409), el select de estado
    // lo sigue (no había cambios sin guardar), quien lo pinta recarga su lista y se avisa. Si la
    // lista no se pudo recargar, se dice: al reabrir el contrato desde ella daría desactualizado.
    const alCerrarContrato = async (tipo, data) => {
        const { contrato: presentado, avisos = [] } = data ?? {};
        setDialogoCierre(null);
        if (!presentado) return;
        setEditingContrato(prev => contratoTrasCierre(prev, presentado));
        setFormContrato(prev => ({ ...prev, estado: presentado.estado ?? prev.estado }));
        avisarCambio();
        const titulo = tipo === 'terminar' ? 'Contrato terminado' : 'Contrato liquidado';
        const recargado = await recargarTrasCierre(editingContrato.id);
        const sinRecargar = recargado ? [] : ['No se pudo actualizar la lista de contratos: ciérrala y vuelve a abrirla para ver lo último.'];
        const lista = [...avisos, ...sinRecargar];
        await (lista.length
            ? Swal.fire({ icon: 'warning', titleText: `${titulo}, con avisos`, html: htmlErrores('Revisa lo siguiente:', lista) })
            : Swal.fire({ icon: 'success', title: titulo }));
    };

    // Otra persona cambió el contrato: como al guardar, se avisa y se recarga la lista.
    const alCierreDesactualizado = async (error) => {
        setDialogoCierre(null);
        await Promise.all([
            recargarTrasCierre(editingContrato?.id),
            Swal.fire({ icon: 'warning', titleText: 'No se guardó el cierre', text: mensajeApi(error) }),
        ]);
    };

    // Refrescar los datos del contrato abierto tras registrar o eliminar una modificación
    const refrescarContratoAbierto = async () => {
        const actual = await recargar(formContrato.id);
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (12 de 16):

```jsx
    const referenciaContratoSecop = referenciaSecop(formContrato, editingContrato);
    const puedeBuscarSecop = puedeEditar && !editingContrato?.secop_id_contrato;
    const puedeActualizarSecop = puedeEditar && Boolean(editingContrato?.secop_id_contrato) && !referenciaContratoSecop.desvinculado;

    // Propiedades comunes de un control del formulario de contrato: valor, cambio y aviso de error.
    // Sin permiso para editar, el control queda deshabilitado.
```

por:

```jsx
    const referenciaContratoSecop = referenciaSecop(formContrato, editingContrato);
    const puedeBuscarSecop = puedeEditar && !editingContrato?.secop_id_contrato;
    const puedeActualizarSecop = puedeEditar && Boolean(editingContrato?.secop_id_contrato) && !referenciaContratoSecop.desvinculado;
    // Cierre: qué acciones ofrece el contrato guardado y si el formulario tiene cambios sin guardar
    // (terminar o liquidar cambia la versión: lo escrito ya no se podría guardar).
    const cierre = accionesCierre(editingContrato);
    const ofreceCierre = puedeEditar && (cierre.terminar || cierre.liquidar);
    const conCambios = ofreceCierre && cambiosSinGuardar(formContrato, editingContrato, anexos);
    // Un contrato guardado sin estado válido no se termina hasta elegirlo y guardarlo.
    const notaSinEstado = ofreceCierre ? notaSinEstadoCierre(editingContrato) : null;
    // Con cambios sin guardar, los botones siguen enfocables (aria-disabled) y la nota explica por
    // qué no hacen nada; mientras se guarda, sí quedan deshabilitados.
    const abrirCierre = (tipo) => {
        if (!conCambios) setDialogoCierre(tipo);
    };

    // Propiedades comunes de un control del formulario de contrato: valor, cambio y aviso de error.
    // Sin permiso para editar, el control queda deshabilitado.
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (13 de 16):

```jsx
            <div className="contratos-modal-body">
                <div className="contrato-form-header">
                    <h3>{editingContrato ? 'Editar Contrato' : 'Agregar Nuevo Contrato'}</h3>
                    <button
                        onClick={onCancelar}
                        className="btn-back-to-list"
```

por:

```jsx
            <div className="contratos-modal-body">
                <div className="contrato-form-header">
                    <h3>{editingContrato ? 'Editar Contrato' : 'Agregar Nuevo Contrato'}</h3>
                    {ofreceCierre && (
                        <div className="ct-cierre-acciones">
                            {cierre.terminar && (
                                <button
                                    type="button"
                                    className="gp-boton"
                                    onClick={() => abrirCierre('terminar')}
                                    disabled={guardandoContrato}
                                    aria-disabled={conCambios ? 'true' : undefined}
                                    aria-describedby={conCambios ? idNotaCierre : undefined}
                                >
                                    <FontAwesomeIcon icon={faFlagCheckered} /> {cierre.etiquetaTerminar}
                                </button>
                            )}
                            {cierre.liquidar && (
                                <button
                                    type="button"
                                    className="gp-boton"
                                    onClick={() => abrirCierre('liquidar')}
                                    disabled={guardandoContrato}
                                    aria-disabled={conCambios ? 'true' : undefined}
                                    aria-describedby={conCambios ? idNotaCierre : undefined}
                                >
                                    <FontAwesomeIcon icon={faFileSignature} /> {cierre.etiquetaLiquidar}
                                </button>
                            )}
                            {conCambios && <small id={idNotaCierre} className="ct-cierre-nota">{NOTA_CAMBIOS_SIN_GUARDAR}</small>}
                            {notaSinEstado && <small className="ct-cierre-nota">{notaSinEstado}</small>}
                        </div>
                    )}
                    <button
                        onClick={onCancelar}
                        className="btn-back-to-list"
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (14 de 16):

```jsx
                                {campoContrato('estado', 'Estado *', (
                                    <select {...controlContrato('estado')}>
                                        <option value="">Elige el estado</option>
                                        {ESTADOS_CONTRATO.map(([valor, etiqueta]) => (
                                            <option key={valor} value={valor}>{etiqueta}</option>
                                        ))}
                                    </select>
                                ))}
```

por:

```jsx
                                {campoContrato('estado', 'Estado *', (
                                    <select {...controlContrato('estado')}>
                                        <option value="">Elige el estado</option>
                                        {opcionesEstadoContrato(editingContrato?.estado, !editingContrato).map(o => (
                                            <option key={o.valor} value={o.valor} disabled={o.deshabilitado}>{o.etiqueta}</option>
                                        ))}
                                    </select>
                                ))}
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (15 de 16):

```jsx
                                    Los vigentes suman al valor inicial y a la fecha fin inicial las adiciones y prórrogas de la pestaña Modificaciones; se recalculan al guardar.
                                </p>
                            )}
                            <div className="form-row">
                                {campoContrato('numero_rp', 'Número del RP', <input type="text" maxLength={40} {...controlContrato('numero_rp')} />)}
                                {campoContrato('fecha_rp', 'Fecha del RP', <input type="date" {...controlContrato('fecha_rp')} />)}
```

por:

```jsx
                                    Los vigentes suman al valor inicial y a la fecha fin inicial las adiciones y prórrogas de la pestaña Modificaciones; se recalculan al guardar.
                                </p>
                            )}
                            {editingContrato && muestraBloqueCierre(editingContrato) && <BloqueCierre contrato={editingContrato} />}
                            <div className="form-row">
                                {campoContrato('numero_rp', 'Número del RP', <input type="text" maxLength={40} {...controlContrato('numero_rp')} />)}
                                {campoContrato('fecha_rp', 'Fecha del RP', <input type="date" {...controlContrato('fecha_rp')} />)}
```

En `resources/js/components/contratos/ContratoEditor.jsx`, reemplazar (16 de 16):

```jsx
                    onCerrar={() => setDialogoSecop(null)}
                />
            )}

            <div className="modal-actions">
                <button type="button" onClick={onCancelar} className="btn-cancel">
```

por:

```jsx
                    onCerrar={() => setDialogoSecop(null)}
                />
            )}
            {dialogoCierre && editingContrato && (
                <DialogoCierre
                    tipo={dialogoCierre}
                    contrato={editingContrato}
                    onGuardado={(data) => alCerrarContrato(dialogoCierre, data)}
                    onDesactualizado={alCierreDesactualizado}
                    onCerrar={() => setDialogoCierre(null)}
                />
            )}

            <div className="modal-actions">
                <button type="button" onClick={onCancelar} className="btn-cancel">
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (1 de 3):

```jsx
    rutaListaDesde, rutaVistaSecop, textoListaVacia, textoVolverDesde, vistaDeConsulta,
} from '../../utils/contratosModulo';
import { avisoProcesoContrato } from '../../utils/avisosContratacion';
import { RUTA_CONTRATOS } from '../../utils/navegacion';
import { mensajeApi } from '../../utils/plantillasChecklist';
import { mensajeSecop } from '../../utils/secop';
```

por:

```jsx
    rutaListaDesde, rutaVistaSecop, textoListaVacia, textoVolverDesde, vistaDeConsulta,
} from '../../utils/contratosModulo';
import { avisoProcesoContrato } from '../../utils/avisosContratacion';
import { textoAvisoCierre } from '../../utils/contratos';
import { RUTA_CONTRATOS } from '../../utils/navegacion';
import { mensajeApi } from '../../utils/plantillasChecklist';
import { mensajeSecop } from '../../utils/secop';
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (2 de 3):

```jsx
    }, [clave]);
    useEffect(() => { cargar(); }, [cargar]);

    const filas = useMemo(() => (contratos ?? []).map((c) => ({ ...filaContrato(c), avisoProceso: c.aviso_proceso })), [contratos]);
    // Al abrir un contrato se lleva la lista con sus filtros, para volver a ella.
    const estadoNavegacion = { lista: search };
```

por:

```jsx
    }, [clave]);
    useEffect(() => { cargar(); }, [cargar]);

    const filas = useMemo(() => (contratos ?? []).map((c) => ({
        ...filaContrato(c), avisoProceso: c.aviso_proceso, avisoCierre: textoAvisoCierre(c.aviso_cierre),
    })), [contratos]);
    // Al abrir un contrato se lleva la lista con sus filtros, para volver a ella.
    const estadoNavegacion = { lista: search };
```

En `resources/js/components/contratos/ContratosModulo.jsx`, reemplazar (3 de 3):

```jsx
                                        {f.proceso}
                                        <AvisoProceso aviso={f.avisoProceso} />
                                    </td>
                                    <td data-etiqueta="Estado"><span className={`ct-estado ct-estado-${f.tono}`}>{f.estado}</span></td>
                                    <td data-etiqueta="Valor vigente" className="numero">{f.valor}</td>
                                    <td data-etiqueta="Físico" className="numero">{f.avanceFisico}</td>
                                    <td data-etiqueta="Financiero" className="numero">{f.avanceFinanciero}</td>
```

por:

```jsx
                                        {f.proceso}
                                        <AvisoProceso aviso={f.avisoProceso} />
                                    </td>
                                    <td data-etiqueta="Estado">
                                        <span className={`ct-estado ct-estado-${f.tono}`}>{f.estado}</span>
                                        {f.avisoCierre && <span className="ct-aviso-cierre ct-estado ct-estado-ambar">{f.avisoCierre}</span>}
                                    </td>
                                    <td data-etiqueta="Valor vigente" className="numero">{f.valor}</td>
                                    <td data-etiqueta="Físico" className="numero">{f.avanceFisico}</td>
                                    <td data-etiqueta="Financiero" className="numero">{f.avanceFinanciero}</td>
```

En `resources/js/components/contratos/ContratosProyecto.jsx`, reemplazar (1 de 6):

```jsx
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
```

por:

```jsx
import Swal from 'sweetalert2';
import axios from '../../axios';
import { avisarError } from '../../utils/avisosPlantillas';
import { etiquetaEstadoContrato, textoAvisoCierre } from '../../utils/contratos';
import { formatoMoneda } from '../../utils/moneda';
import ContratoEditor, { pedirProcesosProyecto, pedirTiposContrato } from './ContratoEditor';
import '../../../css/Parametros.css';
import '../../../css/Contratacion.css';

/**
 * El diálogo "Gestión de Contratos" de un proyecto (Parámetros → Proyectos → Contrato): la lista
 * de sus contratos y, al agregar o editar uno, el editor. Se monta al abrirse.
 *
 * - `proyecto`: la fila del proyecto (GET /proyectos); sus `contratos` se muestran mientras llega
 *   la lista de GET /listarContratos, que se pide al abrir: la fila puede traer versiones viejas
 *   (p. ej. de un contrato terminado después de cargar Parámetros) y editar desde ella daría 409.
 * - `onCerrar()`: al cerrar el diálogo.
 * - `onCambio()`: tras guardar, eliminar, terminar o liquidar un contrato (Parámetros recarga sus proyectos).
 * - `puedeEditar`: sin permiso, la lista y los contratos se ven sin controles para modificarlos.
 */
const ContratosProyecto = ({ proyecto, onCerrar, onCambio, puedeEditar = true }) => {
    // Contratos del proyecto: los de su fila hasta que llega la lista pedida al abrir.
    const [contratos, setContratos] = useState(() => (proyecto.contratos?.length > 0 ? proyecto.contratos : []));
    // El editor abierto: null en la lista; { contrato } al editar, { contrato: null } al agregar.
    const [editor, setEditor] = useState(null);
```

En `resources/js/components/contratos/ContratosProyecto.jsx`, reemplazar (2 de 6):

```jsx
    const [tiposContrato, setTiposContrato] = useState(null);
    const [procesos, setProcesos] = useState(null);

    useEffect(() => {
        pedirTiposContrato().then(setTiposContrato);
        pedirProcesosProyecto(proyecto.id).then(setProcesos);
    }, []);

    const listContratos = async () => {
        const response = await axios.get('/listarContratos', {
            params: {
```

por:

```jsx
    const [tiposContrato, setTiposContrato] = useState(null);
    const [procesos, setProcesos] = useState(null);

    const listContratos = async () => {
        const response = await axios.get('/listarContratos', {
            params: {
```

En `resources/js/components/contratos/ContratosProyecto.jsx`, reemplazar (3 de 6):

```jsx
        return response.data;
    };

    // Recarga la lista y devuelve el contrato `id` recargado (o null): lo usa el editor.
    const recargar = async (id) => {
        const lista = await listContratos();
```

por:

```jsx
        return response.data;
    };

    useEffect(() => {
        pedirTiposContrato().then(setTiposContrato);
        pedirProcesosProyecto(proyecto.id).then(setProcesos);
        // La lista al día; si falla, quedan los de la fila y se avisa.
        listContratos().catch((error) => avisarError('No se pudo cargar la lista de contratos', error));
    }, []);

    // Recarga la lista y devuelve el contrato `id` recargado (o null): lo usa el editor.
    const recargar = async (id) => {
        const lista = await listContratos();
```

En `resources/js/components/contratos/ContratosProyecto.jsx`, reemplazar (4 de 6):

```jsx
    const handleEditContrato = (contrato) => setEditor({ contrato });

    const handleGuardado = () => {
        listContratos();
        onCambio?.();
        setEditor(null);
    };
```

por:

```jsx
    const handleEditContrato = (contrato) => setEditor({ contrato });

    const handleGuardado = () => {
        listContratos().catch((error) => avisarError('No se pudo recargar la lista de contratos', error));
        onCambio?.();
        setEditor(null);
    };
```

En `resources/js/components/contratos/ContratosProyecto.jsx`, reemplazar (5 de 6):

```jsx
                        procesosIniciales={procesos}
                        puedeEditar={puedeEditar}
                        recargar={recargar}
                        onGuardado={handleGuardado}
                        onCancelar={() => setEditor(null)}
                    />
```

por:

```jsx
                        procesosIniciales={procesos}
                        puedeEditar={puedeEditar}
                        recargar={recargar}
                        onCambio={onCambio}
                        onGuardado={handleGuardado}
                        onCancelar={() => setEditor(null)}
                    />
```

En `resources/js/components/contratos/ContratosProyecto.jsx`, reemplazar (6 de 6):

```jsx
                                                        <td>{contrato.n_contrato}</td>
                                                        <td>{contrato.objeto}</td>
                                                        <td>{formatoMoneda(Number(contrato.valor))}</td>
                                                        <td>{etiquetaEstadoContrato(contrato.estado)}</td>
                                                        <td>
                                                            <span className="anexos-count">
                                                                {contrato.anexos ? contrato.anexos.length : 0} anexos
```

por:

```jsx
                                                        <td>{contrato.n_contrato}</td>
                                                        <td>{contrato.objeto}</td>
                                                        <td>{formatoMoneda(Number(contrato.valor))}</td>
                                                        <td>
                                                            {etiquetaEstadoContrato(contrato.estado)}
                                                            {textoAvisoCierre(contrato.aviso_cierre) && (
                                                                <span className="ct-aviso-cierre ct-estado ct-estado-ambar">{textoAvisoCierre(contrato.aviso_cierre)}</span>
                                                            )}
                                                        </td>
                                                        <td>
                                                            <span className="anexos-count">
                                                                {contrato.anexos ? contrato.anexos.length : 0} anexos
```

En `resources/js/components/contratos/SecopContrato.jsx`, reemplazar (1 de 2):

```jsx
/**
 * Diálogo modal de SECOP II, en `document.body`: así queda encima del editor aunque este viva en
 * otro diálogo. Escape, la "×" y un clic en el fondo llaman a `onCerrar`. Escape y Tab no llegan al
 * diálogo de afuera (que cerraría el editor o movería el foco fuera de este).
 */
export const DialogoSecop = ({ titulo, onCerrar, children }) => {
    const caja = useRef(null);
    const idTitulo = useId();
    const alPulsar = useDialogoModal(caja, onCerrar);
```

por:

```jsx
/**
 * Diálogo modal de SECOP II, en `document.body`: así queda encima del editor aunque este viva en
 * otro diálogo. Escape, la "×" y un clic en el fondo llaman a `onCerrar`. Escape y Tab no llegan al
 * diálogo de afuera (que cerraría el editor o movería el foco fuera de este). Lo usan también los
 * diálogos de cierre del contrato: `etiqueta` es el rótulo sobre el título y `estrecho`, para un
 * formulario corto.
 */
export const DialogoSecop = ({ titulo, etiqueta = 'SECOP II', estrecho = false, onCerrar, children }) => {
    const caja = useRef(null);
    const idTitulo = useId();
    const alPulsar = useDialogoModal(caja, onCerrar);
```

En `resources/js/components/contratos/SecopContrato.jsx`, reemplazar (2 de 2):

```jsx

    return createPortal(
        <div className="ct-dialogo-fondo" onMouseDown={(e) => { if (e.target === e.currentTarget) onCerrar(); }}>
            <div ref={caja} className="ct-dialogo" role="dialog" aria-modal="true" aria-labelledby={idTitulo} tabIndex={-1} onKeyDown={pulsar}>
                <header className="ct-dialogo-cabecera">
                    <div>
                        <span className="gp-etiqueta">SECOP II</span>
                        <h2 id={idTitulo}>{titulo}</h2>
                    </div>
                    <button type="button" className="gp-icono" onClick={onCerrar} aria-label="Cerrar">
```

por:

```jsx

    return createPortal(
        <div className="ct-dialogo-fondo" onMouseDown={(e) => { if (e.target === e.currentTarget) onCerrar(); }}>
            <div ref={caja} className={`ct-dialogo${estrecho ? ' ct-dialogo-estrecho' : ''}`} role="dialog" aria-modal="true" aria-labelledby={idTitulo} tabIndex={-1} onKeyDown={pulsar}>
                <header className="ct-dialogo-cabecera">
                    <div>
                        <span className="gp-etiqueta">{etiqueta}</span>
                        <h2 id={idTitulo}>{titulo}</h2>
                    </div>
                    <button type="button" className="gp-icono" onClick={onCerrar} aria-label="Cerrar">
```

En `resources/js/utils/contratos.js`, reemplazar (1 de 2):

```js
// Funciones puras, probadas en contratos.test.js. Las reglas las valida el backend
// (ContratoController y ContratosService); aquí se adelantan los mismos mensajes.

import { erroresPorCampo, etiquetaEstadoProceso, etiquetaPlataforma, numeroProceso, valorDesdeApi, valorParaApi } from './contratacion';
import { numeroDeMoneda } from './moneda';

// Los valores son las constantes de Contrato (contratos.test.js lo comprueba).
```

por:

```js
// Funciones puras, probadas en contratos.test.js. Las reglas las valida el backend
// (ContratoController y ContratosService); aquí se adelantan los mismos mensajes.

import { MAXIMO_OBSERVACION, erroresPorCampo, etiquetaEstadoProceso, etiquetaPlataforma, numeroProceso, valorDesdeApi, valorParaApi } from './contratacion';
import { numeroDeMoneda } from './moneda';

// Los valores son las constantes de Contrato (contratos.test.js lo comprueba).
```

En `resources/js/utils/contratos.js`, reemplazar (2 de 2):

```js
    if (tipo === 'prorroga') return 'Prórroga';
    return 'Adición y Prórroga';
};
```

por:

```js
    if (tipo === 'prorroga') return 'Prórroga';
    return 'Adición y Prórroga';
};

// ---------- Cierre del contrato: terminar y liquidar (fase 7A) ----------

// Estados a los que solo se llega con su acción («Terminar», «Liquidar»), no con el formulario
// (ContratosService::ESTADOS_DE_CIERRE).
const ESTADOS_DE_CIERRE = ['terminado', 'liquidado'];
// Estados desde los que se termina un contrato (CierreContratos::TERMINABLES).
const TERMINABLES = ['celebrado', 'en_ejecucion', 'suspendido', 'terminado'];
const ACCION_DE_ESTADO = { terminado: 'Terminar', liquidado: 'Liquidar' };

/** Los campos de los diálogos de terminar y liquidar, con los nombres de la API. */
export const CAMPOS_TERMINAR = ['fecha_terminacion_real', 'observacion'];
export const CAMPOS_LIQUIDAR = ['fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado', 'observacion'];

// Los mensajes de CierreContratos y ContratoController.
const MENSAJES_CIERRE = {
    sinTerminacion: 'Escribe la fecha de terminación real.',
    terminacionFutura: 'La fecha de terminación no puede ser posterior a hoy.',
    terminacionAnterior: 'La fecha de terminación no puede ser anterior a la de inicio.',
    sinLiquidacion: 'Escribe la fecha de liquidación.',
    liquidacionFutura: 'La fecha de liquidación no puede ser posterior a hoy.',
    liquidacionAnterior: 'La fecha de liquidación no puede ser anterior a la de terminación.',
    sinValor: 'Escribe el valor liquidado.',
    valorLargo: `El valor liquidado admite hasta ${CIFRAS_ENTERAS} cifras enteras y 2 decimales.`,
    observacionLarga: `La observación admite hasta ${MAXIMO_OBSERVACION} caracteres.`,
};

/**
 * Qué acciones de cierre ofrece un contrato guardado: terminar (celebrado, en ejecución,
 * suspendido o terminado, para corregir la fecha) y liquidar (cualquier estado salvo anulado;
 * liquidado, para completar o corregir), con la etiqueta de cada botón.
 */
export const accionesCierre = (contrato) => {
    const existe = !vacio(contrato?.id);
    const estado = contrato?.estado ?? null;
    return {
        terminar: existe && TERMINABLES.includes(estado),
        liquidar: existe && estado !== 'anulado',
        etiquetaTerminar: estado === 'terminado' ? 'Corregir terminación' : 'Terminar',
        etiquetaLiquidar: estado === 'liquidado' ? 'Corregir liquidación' : 'Liquidar',
    };
};

/**
 * La nota junto a los botones de cierre de un contrato guardado sin estado o con uno fuera de la
 * lista (un contrato antiguo sin convertir): no se puede terminar hasta elegir y guardar un estado
 * (el servidor lo rechaza); liquidar sí, como en el servidor. null si no aplica.
 */
export const notaSinEstadoCierre = (contrato) => {
    if (vacio(contrato?.id)) return null;
    return ESTADOS_CONTRATO.some(([valor]) => valor === contrato.estado)
        ? null
        : 'Elige un estado para el contrato y guárdalo para poder terminarlo.';
};

/**
 * El texto del aviso de datos de cierre que faltan (`aviso_cierre` de la API): "Faltan datos de
 * liquidación" si falta la fecha de liquidación o el valor; "Falta la fecha de terminación" si
 * solo falta esa. null si no falta nada.
 */
export const textoAvisoCierre = (aviso) => {
    const faltan = Array.isArray(aviso?.faltan) ? aviso.faltan : [];
    if (faltan.includes('fecha_liquidacion') || faltan.includes('valor_liquidado')) return 'Faltan datos de liquidación';
    if (faltan.includes('fecha_terminacion_real')) return 'Falta la fecha de terminación';
    return null;
};

// Un monto de la API ("1250000.50", "1000", 1500000.5) en centavos, como BigInt: así la resta es
// exacta. null si no hay monto o no se puede leer (más de dos decimales, texto).
const centavos = (monto) => {
    if (vacio(monto)) return null;
    const partes = /^(-?)(\d+)(?:\.(\d{1,2}))?$/.exec(String(monto).trim());
    if (!partes) return null;
    const valor = BigInt(partes[2]) * 100n + BigInt((partes[3] ?? '').padEnd(2, '0'));
    return partes[1] ? -valor : valor;
};

const montoDeCentavos = (valor) => {
    const absoluto = valor < 0n ? -valor : valor;
    const centimos = String(absoluto % 100n).padStart(2, '0');
    return `${valor < 0n ? '-' : ''}${absoluto / 100n}.${centimos}`;
};

/**
 * El saldo de la liquidación: el valor vigente (`valor`) menos el liquidado, con dos decimales
 * exactos ("250000.25"; negativo si lo liquidado supera lo vigente). null si falta alguno.
 */
export const saldoLiquidacion = (contrato) => {
    const vigente = centavos(contrato?.valor);
    const liquidado = centavos(contrato?.valor_liquidado);
    if (vigente === null || liquidado === null) return null;
    return montoDeCentavos(vigente - liquidado);
};

/** Si el contrato guardado muestra el bloque "Cierre": está terminado o liquidado, o tiene algún dato de cierre. */
export const muestraBloqueCierre = (contrato) => Boolean(contrato) && (
    ESTADOS_DE_CIERRE.includes(contrato.estado)
    || [contrato.fecha_terminacion_real, contrato.fecha_liquidacion, contrato.valor_liquidado].some((valor) => !vacio(valor))
);

/**
 * Las opciones del select de estado. Al editar, terminado y liquidado se deshabilitan (se llega a
 * ellos con «Terminar» y «Liquidar») salvo el estado guardado; un contrato nuevo puede llegar en
 * cualquiera (p. ej. uno de SECOP II ya liquidado).
 */
export const opcionesEstadoContrato = (estadoGuardado, esNuevo) => ESTADOS_CONTRATO.map(([valor, etiqueta]) => {
    const deshabilitado = !esNuevo && ESTADOS_DE_CIERRE.includes(valor) && valor !== estadoGuardado;
    return { valor, etiqueta: deshabilitado ? `${etiqueta} (usa «${ACCION_DE_ESTADO[valor]}»)` : etiqueta, deshabilitado };
});

/** El formulario del diálogo de terminar: la fecha guardada, si la hay. */
export const formularioTerminar = (contrato) => ({
    fecha_terminacion_real: comoFecha(contrato?.fecha_terminacion_real),
    observacion: '',
});

/** El formulario del diálogo de liquidar: los datos guardados, el valor con los miles agrupados. */
export const formularioLiquidar = (contrato) => ({
    fecha_terminacion_real: comoFecha(contrato?.fecha_terminacion_real),
    fecha_liquidacion: comoFecha(contrato?.fecha_liquidacion),
    valor_liquidado: valorDesdeApi(contrato?.valor_liquidado),
    observacion: '',
});

const observacionParaApi = (formulario) => (vacio(formulario?.observacion) ? null : String(formulario.observacion).trim());

/** Lo que espera POST /contratos/{id}/terminar, con la versión (`updated_at`) del contrato abierto. */
export const datosTerminar = (formulario, contrato) => ({
    fecha_terminacion_real: comoFecha(formulario.fecha_terminacion_real),
    observacion: observacionParaApi(formulario),
    actualizado: contrato?.updated_at ?? null,
});

/** Lo que espera POST /contratos/{id}/liquidar: el valor como número de la API ("1250000.5"). */
export const datosLiquidar = (formulario, contrato) => ({
    fecha_terminacion_real: comoFecha(formulario.fecha_terminacion_real),
    fecha_liquidacion: comoFecha(formulario.fecha_liquidacion),
    valor_liquidado: valorParaApi(formulario.valor_liquidado),
    observacion: observacionParaApi(formulario),
    actualizado: contrato?.updated_at ?? null,
});

// La fecha de terminación: obligatoria, no posterior a hoy ni anterior a la de inicio.
const errorTerminacion = (fecha, contrato, hoy) => {
    if (!fecha) return MENSAJES_CIERRE.sinTerminacion;
    if (fecha > hoy) return MENSAJES_CIERRE.terminacionFutura;
    const inicio = comoFecha(contrato?.fecha_inicio);
    if (inicio && fecha < inicio) return MENSAJES_CIERRE.terminacionAnterior;
    return null;
};

const errorObservacion = (formulario) => (
    String(formulario?.observacion ?? '').trim().length > MAXIMO_OBSERVACION ? MENSAJES_CIERRE.observacionLarga : null
);

const sinNulos = (errores) => Object.fromEntries(Object.entries(errores).filter(([, mensaje]) => mensaje));

/**
 * Errores del diálogo de terminar por campo, con los mensajes del servidor; {} si se puede
 * enviar. `hoy` es el día (año-mes-día) en la zona de contratación.
 */
export const erroresTerminar = (formulario, contrato, hoy) => sinNulos({
    fecha_terminacion_real: errorTerminacion(comoFecha(formulario?.fecha_terminacion_real), contrato, hoy),
    observacion: errorObservacion(formulario),
});

/**
 * Errores del diálogo de liquidar por campo; {} si se puede enviar. Las fechas no son posteriores
 * a `hoy`; la de terminación no es anterior a la de inicio ni la de liquidación a la de terminación.
 */
export const erroresLiquidar = (formulario, contrato, hoy) => {
    const terminacion = comoFecha(formulario?.fecha_terminacion_real);
    const liquidacion = comoFecha(formulario?.fecha_liquidacion);
    let errorLiquidacion = null;
    if (!liquidacion) errorLiquidacion = MENSAJES_CIERRE.sinLiquidacion;
    else if (liquidacion > hoy) errorLiquidacion = MENSAJES_CIERRE.liquidacionFutura;
    else if (terminacion && liquidacion < terminacion) errorLiquidacion = MENSAJES_CIERRE.liquidacionAnterior;
    const valor = valorParaApi(formulario?.valor_liquidado);
    let errorValor = null;
    if (valor === null) errorValor = MENSAJES_CIERRE.sinValor;
    else if (valor.split('.')[0].length > CIFRAS_ENTERAS) errorValor = MENSAJES_CIERRE.valorLargo;
    return sinNulos({
        fecha_terminacion_real: errorTerminacion(terminacion, contrato, hoy),
        fecha_liquidacion: errorLiquidacion,
        valor_liquidado: errorValor,
        observacion: errorObservacion(formulario),
    });
};

/**
 * Los errores de un 422 de terminar o liquidar: `campos`, los de los `campos` del diálogo, junto a
 * su control, y `general`, el primero de los demás o el `mensaje` de un 422 por estado (anulado,
 * liquidado). null si no es un 422.
 */
export const erroresCierre = (error, campos) => {
    if (error?.response?.status !== 422) return null;
    const porCampo = erroresPorCampo(error, campos);
    if (porCampo) return { campos: porCampo.campos, general: porCampo.otros[0] ?? null };
    const mensaje = error.response.data?.mensaje;
    return { campos: {}, general: typeof mensaje === 'string' && mensaje.trim() ? mensaje : null };
};

/**
 * El contrato abierto tras terminarlo o liquidarlo: del contrato presentado que responde la API
 * toma el estado, los datos de cierre, su aviso y la versión nueva; lo demás (anexos, actas…) se
 * conserva.
 */
export const contratoTrasCierre = (abierto, presentado) => ({
    ...abierto,
    estado: presentado.estado,
    fecha_terminacion_real: presentado.fecha_terminacion_real,
    fecha_liquidacion: presentado.fecha_liquidacion,
    valor_liquidado: presentado.valor_liquidado,
    aviso_cierre: presentado.aviso_cierre ?? null,
    updated_at: presentado.updated_at,
});

// Campos que no cuentan como cambio: el avance financiero se recalcula solo con las actas.
const SIN_COMPARAR = ['avance_financiero'];

/**
 * Si el formulario del contrato guardado tiene cambios sin guardar: lo que se enviaría a
 * /guardarContrato difiere de lo del contrato (sin el avance financiero, que se recalcula solo),
 * o hay anexos subidos que el contrato aún no tiene (los eliminados ya se borraron en el
 * servidor). Un contrato nuevo (sin `contrato`) no cuenta.
 */
export const cambiosSinGuardar = (formulario, contrato, anexos = []) => {
    if (!contrato) return false;
    // El valor se compara en centavos: "1500000.5" y "1500000.50" son el mismo.
    const comparable = (datos) => JSON.stringify(Object.fromEntries(Object.entries(datos)
        .filter(([campo]) => !SIN_COMPARAR.includes(campo))
        .map(([campo, valor]) => [campo, campo === 'valor_inicial' ? String(centavos(valor)) : valor])));
    if (comparable(datosContrato(formulario, contrato)) !== comparable(datosContrato(formularioContrato(contrato), contrato))) return true;
    const guardados = new Set((contrato.anexos ?? []).map((anexo) => String(anexo.id)));
    return (anexos ?? []).some((anexo) => !guardados.has(String(anexo.id)));
};

/**
 * Si SECOP II no puede cambiar el estado del contrato guardado: propone terminado o liquidado y
 * el contrato no está en ese estado (a esos estados se llega con «Terminar» y «Liquidar»). Lo
 * decide `esNuevo`, como en opcionesEstadoContrato: un contrato nuevo sí lo toma; uno guardado,
 * aunque su estado esté vacío, no.
 */
export const estadoCierreBloqueado = (estadoSecop, estadoGuardado, esNuevo = false) => (
    !esNuevo && ESTADOS_DE_CIERRE.includes(estadoSecop) && estadoSecop !== estadoGuardado
);

/**
 * El aviso de "Actualizar desde SECOP II" cuando propone terminado o liquidado y el contrato
 * guardado no lo está: qué acción usar. null si no aplica (contrato nuevo incluido), o si el
 * contrato ya está liquidado y SECOP II lo ve terminado (va detrás).
 */
export const avisoEstadoCierreSecop = (estadoSecop, estadoGuardado, esNuevo = false) => {
    if (!estadoCierreBloqueado(estadoSecop, estadoGuardado, esNuevo)) return null;
    if (estadoSecop === 'liquidado') return 'SECOP II dice «Liquidado»: usa «Liquidar» para registrarlo con sus fechas y valor.';
    if (estadoGuardado === 'liquidado') return null;
    return 'SECOP II dice «Terminado»: usa «Terminar» para registrarlo con su fecha de terminación.';
};
```

En `resources/js/utils/secop.js`, reemplazar (1 de 5):

```js
// formato del formulario, con las mismas conversiones que formularioContrato.

import { esEnlaceWeb, textoFecha, textoValor, valorDesdeApi, valorParaApi } from './contratacion';
import { ESTADOS_CONTRATO, etiquetaEstadoContrato } from './contratos';
import { rutaContrato } from './contratosModulo';
import { mensajeApi } from './plantillasChecklist';
```

por:

```js
// formato del formulario, con las mismas conversiones que formularioContrato.

import { esEnlaceWeb, textoFecha, textoValor, valorDesdeApi, valorParaApi } from './contratacion';
import { ESTADOS_CONTRATO, avisoEstadoCierreSecop, estadoCierreBloqueado, etiquetaEstadoContrato } from './contratos';
import { rutaContrato } from './contratosModulo';
import { mensajeApi } from './plantillasChecklist';
```

En `resources/js/utils/secop.js`, reemplazar (2 de 5):

```js
};

const vacio = (valor) => valor === null || valor === undefined || String(valor).trim() === '';
const texto = (valor) => (vacio(valor) ? '' : String(valor).trim());

/**
```

por:

```js
};

const vacio = (valor) => valor === null || valor === undefined || String(valor).trim() === '';
// Sin contrato guardado (o sin id), el formulario es de un contrato nuevo.
const esNuevo = (contrato) => vacio(contrato?.id);
const texto = (valor) => (vacio(valor) ? '' : String(valor).trim());

/**
```

En `resources/js/utils/secop.js`, reemplazar (3 de 5):

```js
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
```

por:

```js
};

/**
 * Avisos de un resultado: el estado que falta elegir, el estado de cierre que no se copia y los
 * días adicionados en SECOP II. Con el `formulario` que resultó de copiar, el estado se pide si el
 * formulario quedó sin él; sin formulario, si SECOP II no lo trae (o no se pudo traducir). Con el
 * `contrato` guardado, si SECOP II lo ve terminado o liquidado y el contrato no lo está, se avisa
 * qué acción usar (avisoEstadoCierreSecop).
 */
export const avisosSecop = (resultado, formulario = null, contrato = null) => {
    const avisos = [];
    const sinEstado = formulario ? vacio(formulario.estado) : vacio(valoresDesdeSecop(resultado?.contrato).estado);
    if (sinEstado) {
        const estadoSecop = texto(resultado?.secop?.estado_secop);
        avisos.push(estadoSecop ? `Elige el estado: SECOP II dice «${estadoSecop}».` : 'Elige el estado: SECOP II no lo informa.');
    }
    const avisoCierre = avisoEstadoCierreSecop(valoresDesdeSecop(resultado?.contrato).estado, contrato?.estado, esNuevo(contrato));
    if (avisoCierre) avisos.push(avisoCierre);
    const dias = Number(resultado?.secop?.dias_adicionados);
    if (Number.isFinite(dias) && dias > 0) {
        avisos.push(`SECOP II reporta ${dias} ${dias === 1 ? 'día adicionado' : 'días adicionados'}: regístralos como prórroga en Modificaciones.`);
```

En `resources/js/utils/secop.js`, reemplazar (4 de 5):

```js
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
```

por:

```js
 * "Actualizar desde SECOP II": los campos en que el formulario difiere de SECOP II, en el orden
 * del formulario, con su etiqueta, los dos valores legibles (`actual`, el del formulario, y
 * `secop`), si va `marcado` por defecto y su `nota`. Un campo que SECOP II no trae no se compara.
 * El estado, el valor inicial y la fecha fin inicial no van marcados (NOTAS_SIN_MARCAR); en un
 * contrato guardado, un estado terminado o liquidado de SECOP II no se ofrece (estadoCierreBloqueado).
 */
export const diferenciasSecop = (formulario, resultado, { tipos = null, contrato = null } = {}) => {
    const deSecop = { ...valoresDesdeSecop(resultado?.contrato), ...referenciaDesdeSecop(resultado?.contrato) };
    // A terminado y liquidado se llega con «Terminar» y «Liquidar»: ese estado no se ofrece.
    if (estadoCierreBloqueado(deSecop.estado, contrato?.estado, esNuevo(contrato))) delete deSecop.estado;
    const referencia = referenciaSecop(formulario, contrato);
    const actuales = { ...formulario, secop_proceso: referencia.proceso, url_secop: referencia.enlace ?? '' };
    return [...CAMPOS_DESDE_SECOP, ...REFERENCIA_COMPARADA]
```

En `resources/js/utils/secop.js`, reemplazar (5 de 5):

```js
 */
export const aplicarDiferencias = (formulario, resultado, elegidos, contrato = null) => {
    const deSecop = valoresDesdeSecop(resultado?.contrato);
    const nuevaReferencia = referenciaDesdeSecop(resultado?.contrato);
    const referencia = referenciaSecop(formulario, contrato);
    const lista = elegidos ?? [];
```

por:

```js
 */
export const aplicarDiferencias = (formulario, resultado, elegidos, contrato = null) => {
    const deSecop = valoresDesdeSecop(resultado?.contrato);
    if (estadoCierreBloqueado(deSecop.estado, contrato?.estado, esNuevo(contrato))) delete deSecop.estado;
    const nuevaReferencia = referenciaDesdeSecop(resultado?.contrato);
    const referencia = referenciaSecop(formulario, contrato);
    const lista = elegidos ?? [];
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/contratos.test.js resources/js/utils/secop.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1346 passed.

Run: `npm test`
Expected: 918 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/js/components/contratos/CierreContrato.jsx resources/css/Contratacion.css resources/js/components/FichaProyecto.jsx resources/js/components/contratos/ContratoEditor.jsx resources/js/components/contratos/ContratosModulo.jsx resources/js/components/contratos/ContratosProyecto.jsx resources/js/components/contratos/SecopContrato.jsx resources/js/utils/contratos.js resources/js/utils/contratos.test.js resources/js/utils/secop.js resources/js/utils/secop.test.js
git commit -m "feat: contract termination and liquidation in the contract editor"
```

---

### Tarea 4: Compilar para producción

- [ ] **Paso 1: Compilar**

Run: `npm run production`
Expected: `Compiled Successfully`, sin errores.

Justo antes de comitear, comprobar que `public/js/app.js` es el de producción: minificado y sin comentarios `/***/` al inicio. Si `npm run watch` lo reescribió, compilar otra vez.

- [ ] **Paso 2: Commit**

```bash
git add public/js/app.js public/js/app.js.map public/mix-manifest.json
git commit -m "build: production bundle for procurement phase 7A"
```

Los archivos que no cambiaron se omiten. `public/css/app.css` no se comitea si no cambió.

---

### Tarea 5: Verificación en la copia descartable

Usa la copia y las fotos de la tarea 1. Nunca `gest_pro`.

- [ ] **Paso 1: Migrar, revertir y volver a migrar, comparando**

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

Expected: se aplica `2026_11_19_100023_add_liquidacion_a_contratos`.

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c11_despues_$f.json" gestpro_verif_tmp && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c11_antes_$f.json" "$TMP_DIR/c11_despues_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces. El comparador admite claves nuevas; un valor distinto o algo que falta, no.

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT COUNT(*) FROM contratos WHERE fecha_terminacion_real IS NOT NULL OR fecha_liquidacion IS NOT NULL OR valor_liquidado IS NOT NULL;"
```

Expected: `0`.

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=1 --force
```

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c11_rb_$f.json" gestpro_verif_tmp --solo-tablas && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c11_tablas_antes_$f.json" "$TMP_DIR/c11_rb_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate --force
```

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c11_re_$f.json" gestpro_verif_tmp && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c11_antes_$f.json" "$TMP_DIR/c11_re_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

- [ ] **Paso 2: Probar en el navegador sobre la copia**

Compilar en desarrollo (`npx mix`) si `public/` no tiene el código de esta fase. Arrancar el servidor desde `public/`, en un puerto libre (`netstat -ano | grep 8796`):

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8796 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Crear un Administrador de prueba y un usuario Consulta de prueba, solo en la copia. Nunca usar sesiones de usuarios reales de `gest_pro`:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'verif@gestpro.test', 'rol' => App\Enums\Rol::Administrador]); App\Models\User::factory()->create(['email' => 'consulta@gestpro.test', 'rol' => App\Enums\Rol::Consulta]);"
```

Entrar por `http://127.0.0.1:8796/GestPro/` como el Administrador de prueba, borrando `localStorage` si hay una sesión anterior. Todo lo que sigue se hace **solo en la copia**.

Comprobar:

1. **El contrato liquidado sin datos (00028, id 5):** en `/contratos` su fila muestra "Liquidado" y el chip "Faltan datos de liquidación"; también en la lista de contratos del proyecto (Parámetros → Proyectos → Construcción sede educativa - Medellín → Contratos) y en la ficha `/proyectos/1`.
2. **Completar su liquidación** en `/contratos/5` con "Corregir liquidación":
   - una fecha de liquidación futura muestra "La fecha de liquidación no puede ser posterior a hoy." bajo el campo y no guarda;
   - con terminación 10/07/2026, liquidación de una fecha pasada, valor 6.950.000.000 y una observación, guarda: "Contrato liquidado". El bloque "Cierre" muestra las dos fechas, el valor y el saldo $ 50.000.000, y el chip desaparece de las listas;
   - "Actualizar Contrato" sin cambios guarda sin 409.
3. **Terminar y liquidar `C-TEST-001` (id 6):**
   - escribir algo en el objeto sin guardar: "Terminar" y "Liquidar" quedan deshabilitados con "Guarda o descarta tus cambios primero"; recargar para descartar;
   - "Terminar" con la fecha de hoy: "Contrato terminado"; el botón pasa a "Corregir terminación";
   - "Liquidar" con la fecha de hoy y valor 5.100.000.000: el diálogo muestra el saldo negativo en ámbar; al guardar, "Contrato liquidado, con avisos" con "El valor liquidado supera el valor vigente del contrato.".
4. **El select de estado** de un contrato en ejecución muestra "Terminado (usa «Terminar»)" y "Liquidado (usa «Liquidar»)" deshabilitados.
5. **409:** con el diálogo "Corregir liquidación" abierto, liquidar el mismo contrato por API con su `updated_at`; al guardar en la pantalla sale "No se guardó el cierre" y se conserva el cambio de la API.
6. **Parámetros:** terminar un contrato desde la lista de contratos del proyecto, cerrar y volver a abrir la lista: muestra el estado nuevo y se puede volver a guardar sin 409.
7. **El usuario Consulta de prueba** ve el bloque "Cierre" sin botones; `POST /contratos/6/terminar` con su token responde 403.
8. **Las cifras no cambian:**

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT id, estado, valor, valor_inicial, fecha_fin, fecha_fin_inicial, avance_fisico, avance_financiero, fecha_terminacion_real, fecha_liquidacion, valor_liquidado FROM contratos WHERE id IN (5,6); SELECT evento, antes, despues FROM checklist_historial WHERE entidad_tipo='contrato' ORDER BY id DESC LIMIT 4;"
```

Expected: valor, valor inicial, fechas fin y avances como en la foto de antes; el historial con `contrato_terminado` y `contrato_liquidado` con su antes y después.

La consola del navegador no muestra errores nuevos. Los 401 de una sesión vieja, los 422 de las validaciones probadas, el 409 provocado y el 403 del usuario Consulta son esperados.

- [ ] **Paso 3: Comprobar que revertir se detiene con datos de cierre**

Con los contratos liquidados del paso 2 todavía en la copia:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan migrate:rollback --step=1 --force
```

Expected: se detiene con "No se puede revertir: hay contratos con fecha de terminación, fecha de liquidación o valor liquidado registrados, que se perderían. Restaure un respaldo." y las columnas siguen ahí.

- [ ] **Paso 4: Limpiar**

Detener el servidor, eliminar la copia y devolver `public/` a lo comiteado si se compiló en desarrollo:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 5: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas, 1346.

Run: `npm test`
Expected: pasan todas, 918.

---

### Tarea 6: Informar y pedir validación

- [ ] **Paso 1: Actualizar el estado de la propuesta**

En `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, reemplazar:

```markdown
La fase 6 (tablero de Contratación y vista detallada del proceso, sin migraciones) está validada (2026-11-19).
```

por:

```markdown
La fase 6 (tablero de Contratación y vista detallada del proceso, sin migraciones) está validada (2026-11-19). La fase 7A (terminación y liquidación del contrato, migración 24) está implementada y verificada en copia, pendiente de validar y aplicar en `gest_pro`.
```

```bash
git add docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md
git commit -m "docs: mark procurement phase 7A as implemented"
```

- [ ] **Paso 2: Informar**

Informar:
- los totales de pruebas;
- las comparaciones (migrar, revertir y volver a migrar) y que revertir se detiene con datos de cierre;
- lo revisado en el navegador.

Pedir **dos** decisiones por separado:
1. si valida la fase 7A;
2. si autoriza aplicar la migración 24 en `gest_pro`.

Recordar que, con los datos actuales, las tres columnas nacen vacías: el contrato 00028 sigue "Liquidado" con el aviso "Faltan datos de liquidación" hasta que alguien complete su liquidación, y ninguna cifra cambia.

---

### Tarea 7: Aplicar en `gest_pro` (solo con autorización)

No ejecutar esta tarea sin un sí explícito del usuario a este paso. Si la comparación muestra diferencias, restaurar el respaldo: no usar `migrate:rollback`.

- [ ] **Paso 1: Respaldar**

```bash
/c/xampp/mysql/bin/mysqldump -u root gest_pro > "$TMP_DIR/gest_pro_antes_contratacion_fase7a.sql"
```

Expected: el archivo existe y termina en `-- Dump completed`.

- [ ] **Paso 2: Tomar las fotos antes de migrar (solo tablas)**

```bash
for f in 2 3; do php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c11_real_antes_$f.json" gest_pro --solo-tablas; done
```

- [ ] **Paso 3: Migrar hacia adelante**

Run: `php artisan migrate --force`
Expected: se aplica `2026_11_19_100023_add_liquidacion_a_contratos`.

- [ ] **Paso 4: Comparar**

```bash
for f in 2 3; do php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c11_real_despues_$f.json" gest_pro && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c11_real_antes_$f.json" "$TMP_DIR/c11_real_despues_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces.

- [ ] **Paso 5: Informar**

Dar el resultado y la ruta completa del respaldo, y avisar que está en una carpeta temporal.

---

## Criterio de la fase

La fase está lista cuando:

- un contrato se termina con su fecha de terminación real y se liquida con su fecha de liquidación y su valor liquidado, por API y con los botones "Terminar" y "Liquidar" del contrato, y los datos se pueden corregir;
- el formulario de edición ya no pasa un contrato a terminado ni a liquidado; salir de esos estados sí se permite y conserva los datos;
- los contratos terminados o liquidados sin sus datos muestran el aviso en el módulo Contratos, la lista de Parámetros, la ficha del proyecto y el contrato;
- cada terminación y liquidación queda en el historial del contrato, con antes, después y la observación;
- el valor vigente, la fecha fin, los valores iniciales, el avance físico, el financiero y la cadena presupuestal no cambian;
- la viabilización no cambia: mismas respuestas, mismas cifras, y sus pruebas y la paridad del evaluador pasan;
- ningún dato cambia al migrar ni al revertir, y revertir se detiene si hay datos de cierre.
