# Contratación, fase 6: tablero de Contratación y vista detallada del proceso. Plan de implementación

> **Para quien ejecute:** SUB-SKILL REQUERIDA: usar `subagent-driven-development` (recomendada) o `executing-plans` para seguir este plan tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** que el tablero tenga una pestaña **"Contratación"** con todos los procesos de contratación, de todos los proyectos: tarjetas por semáforo y por estado, los procesos por etapa actual, filtros y la tabla con el avance, los avisos y el enlace al checklist de cada proceso. Y que la página del proceso (`/contratacion/:procesoId`) muestre los **datos del proceso** y **sus contratos**, y deje **editar el proceso y cambiar su estado** ahí mismo, tenga o no expediente. **Sin migraciones.** La viabilización, las demás vistas del tablero y el modo sala no cambian.

**Arquitectura:**
- **Tablero (API):** `GET /tablero/contratacion` (`TableroContratacionController` y el servicio `App\Services\Contratacion\TableroContratacion`), de lectura para cualquier usuario autenticado. Devuelve cada proceso con su resumen de expediente, sus avisos, la `etapa_actual` y el `semaforo`; un `resumen` por estado, por semáforo y por etapa actual; y las `opciones` de filtro presentes. Los filtros (`modalidad_id`, `plataforma`, `fuente_id`, `responsable_id`, `estado`, `semaforo`) se validan y se aplican en el servidor. Reutiliza `ExpedientesContratacion::resumenDeProcesos`, que gana un parámetro opcional por referencia con el `avance_por_etapa` ya presentado de cada expediente, así que las consultas no crecen con los procesos.
- **Detalle (API):** `GET /procesos/{proceso}/detalle` (`ProcesosContratacion::detalle()` y `ProcesoContratacionController::detalle()`): el proyecto con sus `fuente_ids`, el proceso con la misma forma de la lista de procesos del proyecto (la que usa el formulario de edición), `admite_contratos` y los contratos del proceso con su valor inicial y vigente. `GET /expedientes/{id}` no cambia.
- **Tablero (front):** la vista `CONTRATACION` en `utils/tablero.js` y `Dashboard.jsx` (solo en el tablero normal, fuera de `secuenciaSala`), con `ContratacionPanel.jsx` y sus utilidades puras en `utils/contratacionPanel.js`. Los filtros viajan en la URL (`/dashboard?vista=contratacion&semaforo=vencida`).
- **Página del proceso (front):** `ProcesoDetalle.jsx`, dentro de `ExpedienteContratacion.jsx`, con los bloques "Proceso" y "Contratos", "Editar proceso", "Cambiar estado" y "Registrar contrato". El formulario del proceso, el de estado, el diálogo del contrato, la carga de catálogos y el guardado salen de `ProcesosProyecto.jsx` a `FormulariosProceso.jsx`, compartido con el diálogo "Procesos" de la ficha sin cambiar su comportamiento. Las utilidades nuevas del detalle van en `utils/expedienteContratacion.js`, con pruebas Vitest.

**Tecnologías:** Laravel 12, PHP 8.2, MariaDB 10.4, React 18 con Laravel Mix y react-router, PHPUnit sobre SQLite en memoria, Vitest.

**Fuente:**
- La propuesta `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, aprobada:
  - fase 6: "Vista detallada y tablero de Contratación"; lista cuando el tablero muestra procesos por estado y etapa, con alertas;
  - sección 7.2: la página del proceso (`/contratacion/:procesoId`) con los datos del proceso y sus contratos; la lista de procesos con número, modalidad, objeto, presupuesto, estado, % avance, fecha de cierre, alertas y botón al checklist; el tablero con la vista "Contratación" y las alertas;
  - regla R13: ningún avance se suma ni se promedia con otro.
- El diseño `docs/superpowers/specs/2026-11-17-contratacion-revision-design.md`, sección "Tablero": procesos por estado y por etapa actual (la primera sin aprobar), semáforo vencida/próxima/al día, pendientes y avance, filtros por modalidad, plataforma, fuente y responsable.
- Decisiones del usuario (2026-11-19), abajo.

**Estado de partida:** rama `feature/contratacion`, igual a `main` (`5fbe85b`). La fase 5 (revisión por etapa, migración 23) está validada y aplicada en `gest_pro`. Pasan 1295 pruebas PHP y 821 JS.

**Datos reales comprobados hoy en `gest_pro`:**
- **Expedientes:** 14 (11 de viabilización y 3 de contratación, con "nueva plantilla"), con 331 filas de requisitos.
- **Plantillas:** 5. **Requisitos de catálogo:** 241.
- **Procesos:** 3. **Contratos:** 7.

La fase solo agrega dos rutas de lectura y pantallas: **ninguna tabla cambia** y ninguna respuesta existente cambia de valor.

---

## Reglas de ejecución

- **Nada de esto toca `gest_pro`.** La fase no tiene migraciones: no se ejecuta `migrate` en ninguna base. La verificación usa una copia descartable (`gestpro_verif_tmp`) y compara las fotos tomadas con el código anterior y con el nuevo sobre esa misma copia. No hay tarea de aplicar en `gest_pro`: tras validar, basta integrar en `main`.
- **La viabilización no cambia ni una respuesta ni una cifra.** Las pruebas existentes de `tests/Feature/Viabilizacion/**`, los fixtures de paridad (`tests/Fixtures/paridad_*`) y `ExpedientesContratacionAisladosTest` no se modifican y deben pasar. No se modifica ninguna prueba PHP existente: las de la fase son nuevas (`TableroContratacionApiTest` y `DetalleProcesoApiTest`). En JS, `tablero.test.js` y `expedienteContratacion.test.js` ganan casos y `contratacionPanel.test.js` es nueva.
- **El tablero existente no cambia:** sus otras vistas y el modo sala siguen igual. La vista nueva no entra en `secuenciaSala`.
- Commits autorizados solo en `feature/contratacion`. Sin push. Mensajes en inglés, terminados con la línea `Co-Authored-By` del modelo que comitea.
- Nomenclatura en español sin tildes para clases, métodos y claves nuevas. Los textos para el usuario van en español con tuteo.
- **"Hoy" en la zona `config('contratacion.zona')` (`America/Bogota`)**, como los avisos de la 4B. Fechas `Y-m-d`.
- Pruebas: `php artisan test` sin definir `DB_DATABASE`, para que use SQLite en memoria, y `npm test`. Si Vitest crea `resources/js/node_modules`, se borra.
- **Aplicar los bloques exactos.** Cada reemplazo aparece una sola vez en su archivo. Si no lo encuentras tal cual, detente y avisa. Se recomienda aplicarlos con un script que los lea del plan y exija una coincidencia por reemplazo.
- **No usar `git stash`, `git clean` ni `git checkout -- .`:** el usuario tiene stashes y cambios propios.
- El usuario suele tener `npm run watch` corriendo, que reescribe `public/`. Solo la tarea 5 comitea `public/`, después de `npm run production`.
- No tocar ni comitear `public/images/marca-completa.png` ni `public/images/marca-completa1.png`: son cambios del usuario.
- `.env` está versionado: nunca se agrega a un commit. Comitear siempre con rutas explícitas.
- `$TMP_DIR` es el directorio temporal de la sesión, fuera del repositorio. Hay que definirlo en cada comando, porque el estado de la terminal no se conserva.
- **El prefijo de la API (`/GestPro`) es también la base del SPA.** Las rutas nuevas (`/tablero/contratacion` y `/procesos/{proceso}/detalle`) no coinciden con ninguna ruta del SPA: la vista del tablero es `/dashboard?vista=contratacion` y la página del proceso sigue siendo `/contratacion/:procesoId`. No se agrega `GET /procesos/{id}`.

## Decisiones de este plan

**Decisiones del usuario (2026-11-19)**
1. **Solo una pestaña del tablero:** la lista de todos los procesos va como pestaña "Contratación" del tablero, junto a "Expedientes". No hay módulo propio de procesos.
2. **Sin modo sala:** la vista nueva no se agrega a la sala.
3. **La página del proceso** gana los datos del proceso (número, monto, fuente, responsable, fechas de apertura y cierre, plataforma y enlace a SECOP, observaciones), sus contratos (con enlace al módulo Contratos y "Registrar contrato" si está adjudicado o celebrado) y **editar el proceso y cambiar su estado** ahí mismo, con el formulario existente.

**Tablero (`GET /tablero/contratacion`)**
- Lectura para cualquier usuario autenticado (Administrador, Gestor o Consulta); sin token, 401. Todos los procesos de todos los proyectos, por id ascendente.
- Cada proceso: `id`, `codigo_proceso`, `objeto`, `modalidad`, `plataforma`, `fuente`, `responsable`, `monto`, `estado`, `fecha_cierre`, `proyecto` (`{id, nombre, fuente_ids}` o null), `expediente` (el resumen de `GET /proyectos/{p}/procesos`: id, estado, progreso, aviso_plantilla; o null), `avisos` (el de la lista de procesos, para los chips), `etapa_actual` y `semaforo`.
- **Etapa actual:** la primera etapa, en el orden del catálogo, con requisitos que cuentan y sin aprobar (ni `aprobada` ni `aprobada_con_cambios`), con `estado_revision` (`pendiente`, `lista` o `devuelta`). Las etapas `sin_requisitos` se saltan. Es null sin expediente, con el expediente cerrado o con todas las etapas aprobadas.
- **Semáforo, el primero que se cumpla:** `terminado` (estado final del proceso: celebrado, desierto, cancelado o revocado; o expediente cerrado) > `sin_expediente` > `vencida` (etapas vencidas o cierre vencido) > `proxima` (etapas próximas o cierre próximo) > `al_dia`.
- **Resumen** de los procesos ya filtrados, como conteos (R13: nunca suma ni promedia avances): `total`, `por_estado` (las 8 claves en el orden del ciclo), `por_semaforo` (las 5 claves) y `por_etapa`, una **lista** de las etapas que son etapa actual de algún proceso, en el orden del catálogo, con cuántos procesos hay en cada una.
- **Opciones** de filtro de todos los procesos, sin depender de los filtros: modalidades, fuentes y responsables presentes, por nombre, y las plataformas presentes en el orden `secop_ii`, `secop_i`, `tvec`, `ninguna`.
- **Filtros validados:** un valor que no es válido responde 422 con el error del parámetro ("Revisa la modalidad: debe ser un número entero.", "Revisa el semáforo: no es un valor válido.", etc.). Un id que no existe no es error: la lista queda vacía.
- **Consultas constantes:** no crecen con los procesos, los proyectos, las fuentes ni los responsables. El filtrado se hace en memoria sobre la lista completa.

**Detalle del proceso (`GET /procesos/{proceso}/detalle`)**
- **Endpoint aparte**, no dentro de `GET /expedientes/{id}`: la página lo necesita tenga o no expediente, y las respuestas del expediente no cambian. Lectura para cualquier usuario autenticado; un proceso que no existe, 404.
- `proyecto` con **`fuente_ids`** (las fuentes del proyecto), para que el formulario agrupe "Fuentes del proyecto" como en la ficha.
- `proceso` con exactamente la forma de un elemento de `GET /proyectos/{p}/procesos`, la que responden `PUT /procesos/{id}` y `POST /procesos/{id}/estado`, con su `actualizado` para el control de cambios.
- `admite_contratos`: el estado es adjudicado o celebrado.
- `contratos`: los del proceso por id, **también los anulados** (con su estado), con `valor_inicial` y `valor` (el vigente). Las consultas no crecen con los contratos.

**Pantallas**
- **Pestaña "Contratación" del tablero** (`/dashboard?vista=contratacion`): 6 tarjetas por semáforo, en una fila (Procesos, Vencida, Próxima, Al día, Sin expediente, Terminado), que filtran al pulsarlas; "Por estado del proceso"; "Procesos por etapa actual" como **lista por etapa** ("Planeación y estudios previos · 1 proceso"); los filtros, con "Quitar filtros"; y la tabla "Procesos de contratación" con número, objeto, proyecto (enlace a la ficha), modalidad, monto, estado, etapa actual, avance del expediente, cierre, chips de avisos (`ChipsAvisosProceso`), semáforo y el enlace "Checklist" a `/contratacion/:procesoId`. Estados vacíos ("Ningún proceso coincide con los filtros elegidos.") y de error con "Reintentar".
- **Filtros en la URL:** cada filtro elegido queda en la consulta (`?vista=contratacion&semaforo=vencida`), se valida al leerlo (un valor que la API rechazaría se descarta, para que una URL vieja no deje el tablero en error) y un filtro cuyo valor ya no tiene ningún proceso se quita.
- **Etapa actual en la tabla:** el nombre de la etapa; "Sin expediente", "Expediente cerrado", "Todas aprobadas" o, si el expediente no tiene requisitos que cuenten, **"Sin requisitos"** (como los 3 expedientes reales con "nueva plantilla").
- **Página del proceso:** el bloque "Proceso" (número, monto, fuente de financiación, responsable, apertura, cierre, plataforma con el enlace "Ver el proceso en SECOP II" si hay url, entidad contratante, observaciones) y el bloque "Contratos" (cada uno con enlace a `/contratos/:id`, los anulados visibles y marcados; "Este proceso aún no tiene contratos."). Si falla, el checklist sigue funcionando y el bloque ofrece "Reintentar".
- **Editar y cambiar estado (solo Administrador o Gestor):** "Editar proceso" y "Cambiar estado" abren el formulario existente. **Los catálogos se cargan al abrir el formulario**, no al entrar a la página. "Registrar contrato" aparece si el proceso admite contratos y tiene proyecto, y abre el diálogo de contrato con el proceso precargado. Tras guardar se recargan el detalle y el expediente.
- **Formulario congelado al abrir:** el formulario guarda con el `actualizado` del proceso de cuando se abrió. Tras un 409 se avisa "No se guardó tu cambio" y se recarga el detalle, pero un segundo "Guardar" con el mismo formulario vuelve a dar 409 en lugar de pisar el cambio de otra persona. Al reabrir, trae lo último.
- **Consulta:** ve los datos y los contratos, sin botones.
- El diálogo "Procesos" de la ficha `/proyectos/:id` usa los mismos formularios extraídos y se comporta igual que antes.

## Fuera de esta etapa

- Un módulo propio de procesos (decisión 1).
- La vista en el modo sala (decisión 2).
- Una entrada de la vista en Ctrl+K.
- Conservar los filtros al cambiar de pestaña del tablero.
- El filtrado en SQL para muchos procesos: hoy se filtra en memoria sobre la lista completa.

## Lo que ya se ensayó

El plan se ensayó completo en un clon descartable fuera del repositorio, y este documento se generó con el código de ese ensayo. Un script comprobó que aplicar sus bloques sobre la rama reproduce el ensayo exactamente.

- **Pruebas:** con el código de las tareas 2 a 4 pasan 1313 pruebas PHP y 854 JS. Cada tarea intermedia también pasa sus suites completas. No se modificó ninguna prueba PHP existente, ni de viabilización ni fixture de paridad.
- **En una copia de `gest_pro` con los datos reales,** las fotos de la tarea 1 tomadas con el código anterior y con el nuevo, sobre la misma copia, no mostraron ninguna diferencia (`SIN DIFERENCIAS` dos veces). La fase no tiene migraciones.
- **En el navegador, sobre esa copia,** con un Administrador y un usuario Consulta de prueba creados solo en la copia, todo lo de la tarea 6 se vio así el 2026-11-19:
  - **Datos:** se borró el expediente cerrado del proceso 2 con su historial, se publicó la versión borrador de la plantilla de licitación, se creó el expediente del proceso 2 con ella y, por API, la etapa 1 recibió una fecha programada pasada.
  - **Tablero, `/dashboard?vista=contratacion`:** 6 tarjetas en una fila (Procesos 3, Vencida 1, Próxima 0, Al día 2, Sin expediente 0, Terminado 0), por estado, por etapa actual ("Planeación y estudios previos · 1 proceso"), los filtros y la tabla con los 3 procesos; los 2 de "nueva plantilla", con la etapa actual "Sin requisitos".
  - **Filtro:** el semáforo "Vencida" dejó 1 fila, con la URL `?vista=contratacion&semaforo=vencida` y el enlace "Checklist" a `/contratacion/2`.
  - **`/contratacion/2`:** los bloques "Proceso" y "Contratos". "Editar proceso" con el número LP-001-2026 y el monto 1.500.000.000 guardó. "Cambiar estado" a Adjudicado hizo aparecer "Registrar contrato", que abrió el formulario con el proceso precargado (se canceló).
  - **409:** con el formulario abierto, otra persona guardó por API; "Guardar proceso" avisó "No se guardó tu cambio", un segundo "Guardar" volvió a dar 409 y el cambio ajeno se conservó.
  - **Consulta:** vio los datos y los contratos, sin botones.
  - **Ficha `/proyectos/1`:** el diálogo "Procesos" siguió editando y guardando.
  - **Modo sala:** no muestra la vista Contratación.
  - **Consola:** solo los 401/403/422 provocados o de sesiones previas.
- **Una revisión independiente** encontró 1 hallazgo alto y 4 bajos, ya corregidos en las tareas correspondientes salvo un bajo aceptado:
  - alto: tras un 409, el segundo guardado en la página del proceso pisaba el cambio ajeno; ahora el formulario se congela al abrir y un segundo intento vuelve a dar 409 (corregido y verificado en el navegador);
  - bajo: las 6 tarjetas quedaban en 5 columnas por el orden del CSS; ahora van en una fila;
  - bajo: "Todas aprobadas" en expedientes sin requisitos; ahora dice "Sin requisitos";
  - bajo: el formulario de la página no agrupaba las fuentes del proyecto; ahora el detalle trae `fuente_ids`;
  - bajo aceptado: la carga del tablero crece a futuro con muchos procesos, porque filtra en memoria; el filtrado en SQL queda fuera de esta fase.

---

### Tarea 1: Punto de partida y fotos con el código actual

Las fotos se toman **antes de cambiar código**, con el código actual (`5fbe85b`). La copia `gestpro_verif_tmp` se conserva hasta la tarea 6.

- [ ] **Paso 1: Comprobar la rama, el estado y las pruebas**

Run: `git branch --show-current`
Expected: `feature/contratacion`

Run: `git status --short`
Expected: como mucho, `public/` (si corre `npm run watch`) y las dos imágenes del usuario.

Run: `php artisan test`
Expected: pasan todas, 1295. Si alguna falla, detenerse y avisar.

Run: `npm test`
Expected: pasan todas, 821.

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

Desde la raíz del repositorio, una foto completa por cada script (`2` y `3`). Como la fase no tiene migraciones, no hacen falta fotos solo de tablas: la tarea 6 toma las mismas fotos con el código nuevo sobre esta misma copia y las compara.

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c10_antes_$f.json" gestpro_verif_tmp; done
```

Expected: `Expedientes: 14, filas: 331, plantillas: 5, requisitos: 241` y `Contratos: 7, procesos: 3, actas: 2, expedientes: 14`, con las cifras actuales de `gest_pro`.

---

### Tarea 2: API del tablero de Contratación

La ruta `GET /tablero/contratacion` (`TableroContratacionController` y el servicio `App\Services\Contratacion\TableroContratacion`), de lectura para cualquier usuario autenticado: todos los procesos con su resumen de expediente, sus avisos, la etapa actual (la primera con requisitos sin aprobar) y el semáforo (terminado > sin expediente > vencida > próxima > al día); el resumen por estado, por semáforo y por etapa actual (una lista, en el orden del catálogo); las opciones de filtro presentes y los filtros validados, aplicados en el servidor. `ExpedientesContratacion::resumenDeProcesos` gana un parámetro opcional por referencia con el `avance_por_etapa` de cada expediente, así que las consultas no crecen con los procesos. Ninguna respuesta existente cambia.

**Files:**
- Create: `app/Http/Controllers/TableroContratacionController.php`
- Create: `app/Services/Contratacion/TableroContratacion.php`
- Test (create): `tests/Feature/Contratacion/TableroContratacionApiTest.php`
- Modify: `app/Services/Contratacion/ExpedientesContratacion.php`
- Modify: `routes/contratacion.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/TableroContratacionApiTest.php`:

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
use App\Models\Responsable;
use App\Models\User;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tablero de Contratación (fase 6): todos los procesos, de todos los proyectos, con su expediente
 * resumido, sus avisos, su etapa actual y su semáforo; el resumen por estado, etapa actual y
 * semáforo; las opciones de filtro y los filtros en el servidor. Hoy es el 19 de noviembre de 2026.
 */
class TableroContratacionApiTest extends TestCase
{
    use RefreshDatabase;

    private const HOY = '2026-11-19';
    private const RUTA = '/GestPro/tablero/contratacion';

    private Proyecto $proyecto;
    private Proyecto $otro;
    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(self::HOY . ' 10:00:00');
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $sector = DB::table('sectores')->insertGetId(['nombre' => 'Agua Potable', 'activo' => true]);
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal', 'sector' => $sector]);
        $this->otro = Proyecto::create(['nombre' => 'Vía terciaria', 'sector' => $sector]);
        $this->gestor = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Gestora']);
    }

    private function como(Rol $rol = Rol::Gestor): static
    {
        $usuario = $rol === Rol::Gestor ? $this->gestor : User::factory()->create(['rol' => $rol]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function etapa(string $clave): EtapaContratacion
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail();
    }

    private function modalidad(string $clave = 'licitacion_publica'): Modalidad
    {
        return Modalidad::where('clave', $clave)->firstOrFail();
    }

    /** Plantilla publicada: dos requisitos de planeación, uno del pliego definitivo y uno del cierre. */
    private function plantilla(): PlantillaChecklist
    {
        $admin = $this->como(Rol::Administrador);
        $id = $admin->postJson('/GestPro/plantillas', [
            'nombre' => 'Licitación',
            'uso' => PlantillaChecklist::USO_CONTRATACION,
            'todas_fuentes' => true,
            'todos_sectores' => true,
            'modalidad_ids' => [$this->modalidad()->id],
        ])->assertCreated()->json('id');
        $plantilla = PlantillaChecklist::findOrFail($id);
        $borrador = $plantilla->versiones()->where('estado', CatalogoVersion::BORRADOR)->firstOrFail();
        foreach ([
            ['Estudios previos', 'planeacion'], ['CDP', 'planeacion'],
            ['Pliego de condiciones', 'pliego_definitivo'], ['Acta de cierre', 'cierre'],
        ] as [$texto, $etapa]) {
            $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos", [
                'texto' => $texto, 'etapa_id' => $this->etapa($etapa)->id,
            ])->assertCreated();
        }
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();

        return $plantilla->fresh();
    }

    private function proceso(array $cambios = [], ?Proyecto $proyecto = null): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => ($proyecto ?? $this->proyecto)->id,
            'objeto' => 'Construcción del acueducto',
            'modalidad' => $this->modalidad()->id,
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-' . (ProcesoLicitacion::count() + 1),
            ...$cambios,
        ]);
    }

    private function expediente(PlantillaChecklist $plantilla, ProcesoLicitacion $proceso): ProyectoExpediente
    {
        $id = $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => [$plantilla->id]])
            ->assertCreated()->json('id');

        return ProyectoExpediente::findOrFail($id);
    }

    private function fechas(ProyectoExpediente $expediente, string $etapa, array $datos): void
    {
        $this->como()->putJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa($etapa)->id}", $datos)->assertOk();
    }

    private function aprobar(ProyectoExpediente $expediente, string $etapa): void
    {
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa($etapa)->id}/aprobar", ['confirmar' => true])->assertOk();
    }

    private function marcar(ProyectoExpediente $expediente, string $texto): void
    {
        $fila = $expediente->requisitos()->whereHas('requisito', fn ($q) => $q->where('texto', $texto))->firstOrFail();
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$fila->id}", ['cumplido' => true])->assertOk();
    }

    private function tablero(array $filtros = []): array
    {
        return $this->como(Rol::Consulta)->getJson(self::RUTA . ($filtros ? '?' . http_build_query($filtros) : ''))->assertOk()->json();
    }

    /** @return array<int, array> proceso_id => fila del tablero */
    private function porId(array $tablero): array
    {
        return array_column($tablero['procesos'], null, 'id');
    }

    private function etapaActual(string $clave, string $estado): array
    {
        $etapa = $this->etapa($clave);

        return ['id' => $etapa->id, 'nombre' => $etapa->nombre, 'orden' => $etapa->orden, 'estado_revision' => $estado];
    }

    private function fuente(string $nombre): FuenteFinanciacion
    {
        return FuenteFinanciacion::create(['clave' => 'f_' . uniqid(), 'nombre' => $nombre, 'orden' => 99, 'activo' => true]);
    }

    // ------------------------------------------------------------ lista

    public function test_lista_los_procesos_de_todos_los_proyectos_con_su_expediente_y_sus_avisos(): void
    {
        $plantilla = $this->plantilla();
        $fuente = $this->fuente('Fondo local');
        $responsable = Responsable::create(['nombre' => 'Ana Pérez', 'activo' => true]);
        $conExpediente = $this->proceso([
            'monto' => 1500000, 'fecha_cierre' => '2026-11-21', 'fuente_id' => $fuente->id, 'responsable_id' => $responsable->id,
        ]);
        $expediente = $this->expediente($plantilla, $conExpediente);
        $sinExpediente = $this->proceso(['objeto' => 'Mejoramiento de la vía'], $this->otro);

        $tablero = $this->tablero();

        $this->assertSame([$conExpediente->id, $sinExpediente->id], array_column($tablero['procesos'], 'id'));
        $fila = $tablero['procesos'][0];
        $this->assertSame([
            'id', 'codigo_proceso', 'objeto', 'modalidad', 'plataforma', 'fuente', 'responsable', 'monto', 'estado',
            'fecha_cierre', 'proyecto', 'expediente', 'avisos', 'etapa_actual', 'semaforo',
        ], array_keys($fila));
        $this->assertSame('LP-1', $fila['codigo_proceso']);
        $this->assertSame(['id' => $this->modalidad()->id, 'nombre' => $this->modalidad()->nombre], $fila['modalidad']);
        $this->assertSame('secop_ii', $fila['plataforma']);
        $this->assertSame(['id' => $fuente->id, 'nombre' => 'Fondo local'], $fila['fuente']);
        $this->assertSame(['id' => $responsable->id, 'nombre' => 'Ana Pérez'], $fila['responsable']);
        $this->assertSame('1500000.00', $fila['monto']);
        $this->assertSame('preparacion', $fila['estado']);
        $this->assertSame('2026-11-21', $fila['fecha_cierre']);
        $this->assertSame(['id' => $this->proyecto->id, 'nombre' => 'Acueducto veredal'], $fila['proyecto']);

        // El expediente y los avisos son los mismos de la lista de procesos del proyecto.
        $delProyecto = $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk()->json('procesos.0');
        $this->assertSame($delProyecto['expediente'], $fila['expediente']);
        $this->assertSame($expediente->id, $fila['expediente']['id']);
        $this->assertSame($delProyecto['avisos'], $fila['avisos']);

        $sin = $tablero['procesos'][1];
        $this->assertSame(['id' => $this->otro->id, 'nombre' => 'Vía terciaria'], $sin['proyecto']);
        $this->assertNull($sin['fuente']);
        $this->assertNull($sin['responsable']);
        $this->assertNull($sin['monto']);
        $this->assertNull($sin['fecha_cierre']);
        $this->assertNull($sin['expediente']);
        $this->assertNull($sin['etapa_actual']);
        $this->assertSame('sin_expediente', $sin['semaforo']);
        $this->assertSame(0, $sin['avisos']['etapas_vencidas']);
    }

    public function test_cualquier_usuario_autenticado_lo_lee(): void
    {
        $this->proceso();
        $this->getJson(self::RUTA)->assertUnauthorized();
        foreach ([Rol::Consulta, Rol::Gestor, Rol::Administrador] as $rol) {
            $this->como($rol)->getJson(self::RUTA)->assertOk()->assertJsonCount(1, 'procesos');
        }
    }

    public function test_sin_procesos_responde_vacio(): void
    {
        $tablero = $this->tablero();

        $this->assertSame([], $tablero['procesos']);
        $this->assertSame(0, $tablero['resumen']['total']);
        $this->assertSame([], $tablero['resumen']['por_etapa']);
        $this->assertSame(['modalidades' => [], 'plataformas' => [], 'fuentes' => [], 'responsables' => []], $tablero['opciones']);
    }

    // ------------------------------------------------------------ etapa actual

    public function test_la_etapa_actual_es_la_primera_con_requisitos_sin_aprobar(): void
    {
        $plantilla = $this->plantilla();
        $proceso = $this->proceso();
        $expediente = $this->expediente($plantilla, $proceso);
        $etapa = fn () => $this->porId($this->tablero())[$proceso->id]['etapa_actual'];

        $this->assertSame($this->etapaActual('planeacion', 'pendiente'), $etapa());

        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $this->assertSame($this->etapaActual('planeacion', 'lista'), $etapa());

        // Aprobada la planeación, la actual es la siguiente aunque una posterior esté lista.
        $this->marcar($expediente, 'Acta de cierre');
        $this->aprobar($expediente, 'planeacion');
        $this->assertSame($this->etapaActual('pliego_definitivo', 'pendiente'), $etapa());

        // Devuelta, sigue siendo la actual con su estado visible.
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa('pliego_definitivo')->id}/devolver", ['observacion' => 'Falta el pliego'])->assertOk();
        $this->assertSame($this->etapaActual('pliego_definitivo', 'devuelta'), $etapa());

        // Una aprobada con cambios después cuenta como aprobada.
        $this->aprobar($expediente, 'pliego_definitivo');
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$expediente->requisitos()->whereHas('requisito', fn ($q) => $q->where('texto', 'CDP'))->value('id')}", ['observacion' => 'Revisado'])->assertOk();
        $this->assertSame($this->etapaActual('cierre', 'lista'), $etapa());

        // Todas aprobadas: no hay etapa actual.
        $this->aprobar($expediente, 'cierre');
        $this->assertNull($etapa());
    }

    public function test_un_expediente_cerrado_no_tiene_etapa_actual(): void
    {
        $proceso = $this->proceso();
        $expediente = $this->expediente($this->plantilla(), $proceso);
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertOk();

        $fila = $this->porId($this->tablero())[$proceso->id];

        $this->assertNull($fila['etapa_actual']);
        $this->assertSame('terminado', $fila['semaforo']);
    }

    public function test_la_etapa_actual_salta_las_etapas_sin_requisitos_que_cuenten(): void
    {
        $proceso = $this->proceso();
        $expediente = $this->expediente($this->plantilla(), $proceso);
        // La planeación queda sin requisitos que cuenten: los dos con "no aplica" aprobado.
        foreach (['Estudios previos', 'CDP'] as $texto) {
            $fila = $expediente->requisitos()->whereHas('requisito', fn ($q) => $q->where('texto', $texto))->firstOrFail();
            ProyectoRequisito::whereKey($fila->id)->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA]);
        }

        $this->assertSame($this->etapaActual('pliego_definitivo', 'pendiente'), $this->porId($this->tablero())[$proceso->id]['etapa_actual']);
    }

    // ------------------------------------------------------------ semáforo

    public function test_el_semaforo_de_cada_proceso(): void
    {
        $plantilla = $this->plantilla();
        $vencida = $this->proceso();
        $this->fechas($this->expediente($plantilla, $vencida), 'planeacion', ['fecha_programada' => '2026-11-18']);
        $cierreVencido = $this->proceso(['fecha_cierre' => '2026-11-18']);
        $this->expediente($plantilla, $cierreVencido);
        $proxima = $this->proceso();
        $this->fechas($this->expediente($plantilla, $proxima), 'pliego_definitivo', ['fecha_programada' => '2026-11-22']);
        $cierreProximo = $this->proceso(['fecha_cierre' => '2026-11-20']);
        $this->expediente($plantilla, $cierreProximo);
        $alDia = $this->proceso(['fecha_cierre' => '2026-12-20']);
        $this->fechas($this->expediente($plantilla, $alDia), 'planeacion', ['fecha_programada' => '2026-12-01']);
        $sinExpediente = $this->proceso(['fecha_cierre' => '2026-11-10']);
        $celebrado = $this->proceso();
        $this->fechas($this->expediente($plantilla, $celebrado), 'planeacion', ['fecha_programada' => '2026-11-18']);
        $celebrado->update(['estado' => ProcesoLicitacion::CELEBRADO]);
        $desiertoSinExpediente = $this->proceso(['estado' => ProcesoLicitacion::DESIERTO]);

        $semaforos = array_map(fn (array $f) => $f['semaforo'], $this->porId($this->tablero()));

        $this->assertSame([
            $vencida->id => 'vencida',
            $cierreVencido->id => 'vencida',
            $proxima->id => 'proxima',
            $cierreProximo->id => 'proxima',
            $alDia->id => 'al_dia',
            $sinExpediente->id => 'sin_expediente',
            $celebrado->id => 'terminado',
            $desiertoSinExpediente->id => 'terminado',
        ], $semaforos);
    }

    // ------------------------------------------------------------ resumen, opciones y filtros

    /** Tres procesos en dos proyectos, con modalidades, plataformas, fuentes y responsables distintos. */
    private function variados(): array
    {
        $plantilla = $this->plantilla();
        $propios = $this->fuente('Fondo local');
        $sgr = $this->fuente('Cofinanciación nacional');
        $ana = Responsable::create(['nombre' => 'Ana Pérez', 'activo' => true]);
        $beto = Responsable::create(['nombre' => 'Beto Ruiz', 'activo' => true]);
        Responsable::create(['nombre' => 'Sin procesos', 'activo' => true]);
        $minima = $this->modalidad('minima_cuantia');

        $a = $this->proceso(['fuente_id' => $propios->id, 'responsable_id' => $ana->id]);
        $expediente = $this->expediente($plantilla, $a);
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-18']);
        $b = $this->proceso(['fuente_id' => $sgr->id, 'responsable_id' => $beto->id, 'plataforma' => 'secop_i'], $this->otro);
        $this->expediente($plantilla, $b);
        $c = $this->proceso([
            'modalidad' => $minima->id, 'plataforma' => 'ninguna', 'fuente_id' => $propios->id, 'estado' => ProcesoLicitacion::PUBLICADO,
        ]);

        return compact('a', 'b', 'c', 'propios', 'sgr', 'ana', 'beto', 'minima');
    }

    public function test_el_resumen_cuenta_por_estado_etapa_actual_y_semaforo_y_trae_las_opciones_presentes(): void
    {
        ['propios' => $propios, 'sgr' => $sgr, 'ana' => $ana, 'beto' => $beto, 'minima' => $minima] = $this->variados();

        $tablero = $this->tablero();
        $planeacion = $this->etapa('planeacion');

        $this->assertSame([
            'total' => 3,
            'por_estado' => [
                'preparacion' => 2, 'publicado' => 1, 'evaluacion' => 0, 'adjudicado' => 0,
                'desierto' => 0, 'celebrado' => 0, 'cancelado' => 0, 'revocado' => 0,
            ],
            'por_semaforo' => ['vencida' => 1, 'proxima' => 0, 'al_dia' => 1, 'sin_expediente' => 1, 'terminado' => 0],
            'por_etapa' => [
                ['id' => $planeacion->id, 'nombre' => $planeacion->nombre, 'orden' => $planeacion->orden, 'procesos' => 2],
            ],
        ], $tablero['resumen']);
        // Por nombre: Licitación pública antes que Mínima cuantía.
        $licitacion = $this->modalidad();
        $this->assertSame([
            'modalidades' => [
                ['id' => $licitacion->id, 'nombre' => 'Licitación pública'],
                ['id' => $minima->id, 'nombre' => 'Mínima cuantía'],
            ],
            'plataformas' => ['secop_ii', 'secop_i', 'ninguna'],
            'fuentes' => [['id' => $sgr->id, 'nombre' => 'Cofinanciación nacional'], ['id' => $propios->id, 'nombre' => 'Fondo local']],
            'responsables' => [['id' => $ana->id, 'nombre' => 'Ana Pérez'], ['id' => $beto->id, 'nombre' => 'Beto Ruiz']],
        ], $tablero['opciones']);
    }

    public function test_los_filtros_se_aplican_en_el_servidor_y_el_resumen_los_refleja(): void
    {
        ['a' => $a, 'b' => $b, 'c' => $c, 'propios' => $propios, 'beto' => $beto, 'minima' => $minima] = $this->variados();
        $ids = fn (array $filtros) => array_column($this->tablero($filtros)['procesos'], 'id');

        $this->assertSame([$c->id], $ids(['modalidad_id' => $minima->id]));
        $this->assertSame([$b->id], $ids(['plataforma' => 'secop_i']));
        $this->assertSame([$a->id, $c->id], $ids(['fuente_id' => $propios->id]));
        $this->assertSame([$b->id], $ids(['responsable_id' => $beto->id]));
        $this->assertSame([$c->id], $ids(['estado' => 'publicado']));
        $this->assertSame([$a->id], $ids(['semaforo' => 'vencida']));
        $this->assertSame([$a->id], $ids(['fuente_id' => $propios->id, 'estado' => 'preparacion']));
        $this->assertSame([], $ids(['fuente_id' => $propios->id, 'semaforo' => 'al_dia']));
        // Vacíos: como si no vinieran.
        $this->assertSame([$a->id, $b->id, $c->id], $ids(['fuente_id' => '', 'semaforo' => '']));

        $filtrado = $this->tablero(['fuente_id' => $propios->id]);
        $this->assertSame(2, $filtrado['resumen']['total']);
        $this->assertSame(['vencida' => 1, 'proxima' => 0, 'al_dia' => 0, 'sin_expediente' => 1, 'terminado' => 0], $filtrado['resumen']['por_semaforo']);
        $this->assertSame(1, $filtrado['resumen']['por_estado']['publicado']);
        $this->assertSame([1], array_column($filtrado['resumen']['por_etapa'], 'procesos'));
        // Las opciones no dependen de los filtros: se puede cambiar de filtro.
        $this->assertSame($this->tablero()['opciones'], $filtrado['opciones']);
    }

    public function test_un_filtro_no_valido_responde_422(): void
    {
        foreach ([
            ['modalidad_id' => 'uno'], ['fuente_id' => 'x'], ['responsable_id' => '1.5'],
            ['plataforma' => 'otra'], ['estado' => 'abierto'], ['semaforo' => 'rojo'],
        ] as $filtro) {
            $this->como(Rol::Consulta)->getJson(self::RUTA . '?' . http_build_query($filtro))
                ->assertUnprocessable()->assertJsonValidationErrors(array_keys($filtro));
        }
    }

    // ------------------------------------------------------------ consultas

    public function test_el_tablero_no_hace_una_consulta_por_proceso(): void
    {
        $plantilla = $this->plantilla();
        // El primero ya tiene todo lo que se lee: fuente, responsable y un evento tras la aprobación.
        $expediente = $this->expediente($plantilla, $this->proceso([
            'fecha_cierre' => '2026-11-21',
            'fuente_id' => $this->fuente('Fuente 0')->id,
            'responsable_id' => Responsable::create(['nombre' => 'Responsable 0', 'activo' => true])->id,
        ]));
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-18']);
        $this->aprobar($expediente, 'planeacion');
        $this->marcar($expediente, 'Acta de cierre');
        $consultas = function (): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson(self::RUTA)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $conUno = $consultas();

        foreach (range(1, 3) as $i) {
            $proyecto = $i % 2 ? $this->otro : $this->proyecto;
            $expediente = $this->expediente($plantilla, $this->proceso([
                'fecha_cierre' => '2026-11-0' . $i,
                'fuente_id' => $this->fuente("Fuente {$i}")->id,
                'responsable_id' => Responsable::create(['nombre' => "Responsable {$i}", 'activo' => true])->id,
            ], $proyecto));
            $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-18']);
            $this->aprobar($expediente, 'planeacion');
            $this->marcar($expediente, 'Acta de cierre');
        }
        $this->proceso();

        $this->assertSame($conUno, $consultas());
        $this->assertCount(5, $this->tablero()['procesos']);
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/TableroContratacionApiTest.php`
Expected: FALLAN las 11 de `TableroContratacionApiTest`: la ruta `GET /tablero/contratacion` aún no existe y la atiende la ruta comodín del SPA, que responde la página HTML con 200. Las que leen la respuesta dicen "Invalid JSON was returned from the route."; la de sin token, "Failed asserting that 200 is identical to 401.", y la del filtro no válido, "Failed asserting that 200 is identical to 422.".

- [ ] **Paso 3: Implementar**

Crear `app/Http/Controllers/TableroContratacionController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\ProcesoLicitacion;
use App\Services\Contratacion\TableroContratacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Vista "Contratación" del tablero: todos los procesos, con filtros. Lectura: cualquier usuario
 * autenticado.
 */
class TableroContratacionController extends Controller
{
    private const MENSAJES = [
        'integer' => 'Revisa :attribute: debe ser un número entero.',
        'in' => 'Revisa :attribute: no es un valor válido.',
    ];

    private const ATRIBUTOS = [
        'modalidad_id' => 'la modalidad',
        'plataforma' => 'la plataforma',
        'fuente_id' => 'la fuente de financiación',
        'responsable_id' => 'el responsable',
        'estado' => 'el estado',
        'semaforo' => 'el semáforo',
    ];

    public function __construct(private readonly TableroContratacion $tablero)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'modalidad_id' => ['sometimes', 'nullable', 'integer'],
            'plataforma' => ['sometimes', 'nullable', 'string', Rule::in(ProcesoLicitacion::PLATAFORMAS)],
            'fuente_id' => ['sometimes', 'nullable', 'integer'],
            'responsable_id' => ['sometimes', 'nullable', 'integer'],
            'estado' => ['sometimes', 'nullable', 'string', Rule::in(ProcesoLicitacion::ESTADOS)],
            'semaforo' => ['sometimes', 'nullable', 'string', Rule::in(TableroContratacion::SEMAFOROS)],
        ], self::MENSAJES, self::ATRIBUTOS);

        return response()->json($this->tablero->presentar($filtros));
    }
}
```

Crear `app/Services/Contratacion/TableroContratacion.php`:

```php
<?php

namespace App\Services\Contratacion;

use App\Models\ProcesoLicitacion;
use App\Models\ProyectoExpediente;
use Illuminate\Support\Collection;

/**
 * Vista "Contratación" del tablero: todos los procesos, de todos los proyectos. Solo lee.
 *
 * Reglas:
 * - Cada proceso trae su expediente resumido y sus avisos, los mismos de la lista de procesos del
 *   proyecto (ExpedientesContratacion::resumenDeProcesos), su etapa actual y su semáforo.
 * - Etapa actual: la primera etapa, en el orden del catálogo, con requisitos que cuentan y sin
 *   aprobar (ni aprobada ni aprobada con cambios), con su estado de revisión visible. Null sin
 *   expediente, con el expediente cerrado o con todas aprobadas.
 * - Semáforo, en este orden: terminado (proceso en un estado final o expediente cerrado);
 *   sin_expediente; vencida (etapas vencidas o cierre vencido); proxima (etapas próximas o cierre
 *   próximo); si no, al_dia.
 * - Filtros (modalidad, plataforma, fuente, responsable, estado y semáforo) en el servidor; el
 *   resumen (conteos por estado, por etapa actual y por semáforo) es el de los procesos filtrados.
 *   Las opciones de filtro son las presentes en todos los procesos: no dependen de los filtros.
 * - Ningún avance se suma ni promedia con otro (R13): el resumen solo cuenta procesos.
 * - Las consultas no crecen con los procesos.
 */
final class TableroContratacion
{
    public const VENCIDA = 'vencida';
    public const PROXIMA = 'proxima';
    public const AL_DIA = 'al_dia';
    public const SIN_EXPEDIENTE = 'sin_expediente';
    public const TERMINADO = 'terminado';
    public const SEMAFOROS = [self::VENCIDA, self::PROXIMA, self::AL_DIA, self::SIN_EXPEDIENTE, self::TERMINADO];

    /** Filtro de la API => columna del proceso. */
    private const FILTROS = [
        'modalidad_id' => 'modalidad',
        'plataforma' => 'plataforma',
        'fuente_id' => 'fuente_id',
        'responsable_id' => 'responsable_id',
        'estado' => 'estado',
    ];

    public function __construct(
        private readonly ExpedientesContratacion $expedientes,
        private readonly AvisosContratacion $avisos,
    ) {
    }

    /**
     * @param array{modalidad_id?: ?int, plataforma?: ?string, fuente_id?: ?int, responsable_id?: ?int, estado?: ?string, semaforo?: ?string} $filtros
     * @return array{procesos: list<array>, resumen: array, opciones: array}
     */
    public function presentar(array $filtros): array
    {
        $todos = ProcesoLicitacion::query()
            ->with(['modalidadRel:id,nombre', 'fuente:id,nombre', 'responsable:id,nombre', 'proyectoRel:id,nombre'])
            ->orderBy('id')
            ->get();
        $procesos = $todos->filter(function (ProcesoLicitacion $proceso) use ($filtros) {
            foreach (self::FILTROS as $filtro => $columna) {
                $valor = $filtros[$filtro] ?? null;
                if ($valor !== null && (string) $proceso->{$columna} !== (string) $valor) {
                    return false;
                }
            }

            return true;
        })->values();

        $resumenes = $this->expedientes->resumenDeProcesos($procesos, $avances);
        $filas = $procesos
            ->map(fn (ProcesoLicitacion $proceso) => $this->forma($proceso, $resumenes[$proceso->id], $avances[$proceso->id] ?? []))
            ->filter(fn (array $fila) => ($filtros['semaforo'] ?? null) === null || $fila['semaforo'] === $filtros['semaforo'])
            ->values()
            ->all();

        return [
            'procesos' => $filas,
            'resumen' => $this->resumen($filas),
            'opciones' => $this->opciones($todos),
        ];
    }

    /** @param list<array> $avancePorEtapa el del expediente del proceso, presentado; [] sin expediente */
    private function forma(ProcesoLicitacion $proceso, array $resumen, array $avancePorEtapa): array
    {
        $opcion = fn ($fila) => $fila ? ['id' => $fila->id, 'nombre' => $fila->nombre] : null;
        $expediente = $resumen['expediente'];

        return [
            'id' => $proceso->id,
            'codigo_proceso' => $proceso->codigo_proceso,
            'objeto' => $proceso->objeto,
            'modalidad' => $opcion($proceso->modalidadRel),
            'plataforma' => $proceso->plataforma,
            'fuente' => $opcion($proceso->fuente),
            'responsable' => $opcion($proceso->responsable),
            'monto' => $proceso->monto,
            'estado' => $proceso->estado,
            'fecha_cierre' => $proceso->fecha_cierre?->format('Y-m-d'),
            'proyecto' => $opcion($proceso->proyectoRel),
            'expediente' => $expediente,
            'avisos' => $resumen['avisos'],
            'etapa_actual' => $expediente && $expediente['estado'] !== ProyectoExpediente::CERRADO ? $this->etapaActual($avancePorEtapa) : null,
            'semaforo' => $this->semaforo($proceso, $expediente, $resumen['avisos']),
        ];
    }

    /** La primera etapa con requisitos que cuentan sin aprobar; null si todas están aprobadas. */
    private function etapaActual(array $avancePorEtapa): ?array
    {
        foreach ($avancePorEtapa as $etapa) {
            if ($etapa['etapa'] !== null && $etapa['total'] > 0 && ! $this->avisos->aprobada($etapa['revision'])) {
                return [...$etapa['etapa'], 'estado_revision' => $etapa['revision']['estado']];
            }
        }

        return null;
    }

    private function semaforo(ProcesoLicitacion $proceso, ?array $expediente, array $avisos): string
    {
        return match (true) {
            in_array($proceso->estado, AvisosContratacion::ESTADOS_FINALES, true),
            $expediente !== null && $expediente['estado'] === ProyectoExpediente::CERRADO => self::TERMINADO,
            $expediente === null => self::SIN_EXPEDIENTE,
            $avisos['etapas_vencidas'] > 0 || $avisos['cierre_vencido'] => self::VENCIDA,
            $avisos['etapas_proximas'] > 0 || $avisos['cierre_proximo'] => self::PROXIMA,
            default => self::AL_DIA,
        };
    }

    /** @param list<array> $filas las del tablero, ya filtradas */
    private function resumen(array $filas): array
    {
        $porEtapa = [];
        foreach ($filas as $fila) {
            $etapa = $fila['etapa_actual'];
            if ($etapa === null) {
                continue;
            }
            $porEtapa[$etapa['id']] ??= ['id' => $etapa['id'], 'nombre' => $etapa['nombre'], 'orden' => $etapa['orden'], 'procesos' => 0];
            $porEtapa[$etapa['id']]['procesos']++;
        }
        usort($porEtapa, fn (array $a, array $b) => [$a['orden'], $a['id']] <=> [$b['orden'], $b['id']]);
        $contar = fn (array $claves, string $campo) => array_replace(
            array_fill_keys($claves, 0),
            array_intersect_key(array_count_values(array_column($filas, $campo)), array_flip($claves))
        );

        return [
            'total' => count($filas),
            'por_estado' => $contar(ProcesoLicitacion::ESTADOS, 'estado'),
            'por_semaforo' => $contar(self::SEMAFOROS, 'semaforo'),
            'por_etapa' => $porEtapa,
        ];
    }

    /** @param Collection<int, ProcesoLicitacion> $procesos todos, sin filtrar */
    private function opciones(Collection $procesos): array
    {
        $lista = fn (string $relacion) => $procesos->pluck($relacion)->filter()->unique('id')
            ->sortBy([['nombre', 'asc'], ['id', 'asc']])
            ->map(fn ($fila) => ['id' => $fila->id, 'nombre' => $fila->nombre])
            ->values()->all();
        $plataformas = $procesos->pluck('plataforma')->filter()->unique()->all();

        return [
            'modalidades' => $lista('modalidadRel'),
            'plataformas' => array_values(array_filter(ProcesoLicitacion::PLATAFORMAS, fn (string $p) => in_array($p, $plataformas, true))),
            'fuentes' => $lista('fuente'),
            'responsables' => $lista('responsable'),
        ];
    }
}
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (1 de 2):

```php
     * cierre y la actualización). Las consultas no crecen con los procesos.
     *
     * @param Collection<int, ProcesoLicitacion> $procesos
     * @return array<int, array{expediente: ?array{id: int, estado: string, progreso: array{total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}, aviso_plantilla: ?string}, avisos: array}> proceso_id => resumen
     */
    public function resumenDeProcesos(Collection $procesos): array
    {
        $expedientes = $procesos->isEmpty() ? collect() : ProyectoExpediente::query()
            ->contratacion()
```

por:

```php
     * cierre y la actualización). Las consultas no crecen con los procesos.
     *
     * @param Collection<int, ProcesoLicitacion> $procesos
     * @param ?array $avancesPorEtapa recibe el avance_por_etapa presentado del expediente de cada
     *        proceso que lo tiene (proceso_id => lista), para quien necesita las etapas (el tablero)
     * @return array<int, array{expediente: ?array{id: int, estado: string, progreso: array{total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}, aviso_plantilla: ?string}, avisos: array}> proceso_id => resumen
     */
    public function resumenDeProcesos(Collection $procesos, ?array &$avancesPorEtapa = null): array
    {
        $expedientes = $procesos->isEmpty() ? collect() : ProyectoExpediente::query()
            ->contratacion()
```

En `app/Services/Contratacion/ExpedientesContratacion.php`, reemplazar (2 de 2):

```php
                ->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        return $procesos->mapWithKeys(function (ProcesoLicitacion $proceso) use ($expedientes, $estados, $avances, $eventos) {
            $expediente = $expedientes->get($proceso->id);
            $avisos = $this->avisos->resumen(
```

por:

```php
                ->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        $avancesPorEtapa = $expedientes->map(fn (ProyectoExpediente $e) => $avances[$e->id]['avance_por_etapa'])->all();

        return $procesos->mapWithKeys(function (ProcesoLicitacion $proceso) use ($expedientes, $estados, $avances, $eventos) {
            $expediente = $expedientes->get($proceso->id);
            $avisos = $this->avisos->resumen(
```

En `routes/contratacion.php`, reemplazar (1 de 2):

```php
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\ProcesoContratacionController;
use App\Http\Controllers\SecopController;
use Illuminate\Support\Facades\Route;

// Lectura: cualquier usuario autenticado.
```

por:

```php
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\ProcesoContratacionController;
use App\Http\Controllers\SecopController;
use App\Http\Controllers\TableroContratacionController;
use Illuminate\Support\Facades\Route;

// Lectura: cualquier usuario autenticado.
```

En `routes/contratacion.php`, reemplazar (2 de 2):

```php
    ->whereNumber('contrato')
    ->name('contratos.detalle');

// Procesos de contratación: Administrador o Gestor.
Route::middleware('can:editar-datos')->group(function () {
    Route::post('/proyectos/{proyecto}/procesos', [ProcesoContratacionController::class, 'store'])
```

por:

```php
    ->whereNumber('contrato')
    ->name('contratos.detalle');

// Vista "Contratación" del tablero: todos los procesos, con filtros. El tablero del SPA es
// /dashboard?vista=contratacion: esta ruta no coincide con ninguna del SPA.
Route::get('/tablero/contratacion', [TableroContratacionController::class, 'index'])
    ->name('tablero.contratacion');

// Procesos de contratación: Administrador o Gestor.
Route::middleware('can:editar-datos')->group(function () {
    Route::post('/proyectos/{proyecto}/procesos', [ProcesoContratacionController::class, 'store'])
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/TableroContratacionApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1306 passed.

- [ ] **Paso 5: Commit**

```bash
git add app/Http/Controllers/TableroContratacionController.php app/Services/Contratacion/TableroContratacion.php tests/Feature/Contratacion/TableroContratacionApiTest.php app/Services/Contratacion/ExpedientesContratacion.php routes/contratacion.php
git commit -m "feat: contracting dashboard API"
```

---

### Tarea 3: API del detalle del proceso para su página

La ruta `GET /procesos/{proceso}/detalle` (`ProcesosContratacion::detalle()` y `ProcesoContratacionController::detalle()`), aparte de `GET /expedientes/{id}` porque la página la necesita tenga o no expediente: el proyecto con sus `fuente_ids`, el proceso con la forma de la lista de procesos del proyecto (la del formulario de edición, con `actualizado`), `admite_contratos` y los contratos del proceso, también los anulados, con su valor inicial y vigente. Las consultas no crecen con los contratos.

**Files:**
- Test (create): `tests/Feature/Contratacion/DetalleProcesoApiTest.php`
- Modify: `app/Http/Controllers/ProcesoContratacionController.php`
- Modify: `app/Services/Contratacion/ProcesosContratacion.php`
- Modify: `routes/contratacion.php`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `tests/Feature/Contratacion/DetalleProcesoApiTest.php`:

```php
<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\Contrato;
use App\Models\EtapaContratacion;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\Responsable;
use App\Models\User;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Vista detallada del proceso (fase 6): GET /procesos/{id}/detalle trae los datos del proceso,
 * con la forma de la lista de procesos del proyecto (la que usa el formulario de editar), su
 * proyecto, si admite contratos y sus contratos; también sin expediente.
 */
class DetalleProcesoApiTest extends TestCase
{
    use RefreshDatabase;

    private Proyecto $proyecto;
    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-19 10:00:00');
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
        return Modalidad::where('clave', 'licitacion_publica')->value('id');
    }

    private function plantilla(): PlantillaChecklist
    {
        $admin = $this->como(Rol::Administrador);
        $id = $admin->postJson('/GestPro/plantillas', [
            'nombre' => 'Licitación',
            'uso' => PlantillaChecklist::USO_CONTRATACION,
            'todas_fuentes' => true,
            'todos_sectores' => true,
            'modalidad_ids' => [$this->modalidad()],
        ])->assertCreated()->json('id');
        $plantilla = PlantillaChecklist::findOrFail($id);
        $borrador = $plantilla->versiones()->where('estado', CatalogoVersion::BORRADOR)->firstOrFail();
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos", [
            'texto' => 'Estudios previos', 'etapa_id' => EtapaContratacion::where('clave', 'planeacion')->value('id'),
        ])->assertCreated();
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
            'codigo_proceso' => 'LP-' . (ProcesoLicitacion::count() + 1),
            ...$cambios,
        ]);
    }

    private function contrato(ProcesoLicitacion $proceso, string $numero, array $cambios = []): Contrato
    {
        return Contrato::create([
            'proyecto' => $this->proyecto->id,
            'proceso_licitacion' => $proceso->id,
            'n_contrato' => $numero,
            'objeto' => 'Construcción del acueducto',
            'contratista' => 'Consorcio Agua',
            'valor_inicial' => 1000000,
            'valor' => 1000000,
            'estado' => Contrato::EN_EJECUCION,
            ...$cambios,
        ]);
    }

    private function detalle(ProcesoLicitacion $proceso): array
    {
        return $this->como(Rol::Consulta)->getJson("/GestPro/procesos/{$proceso->id}/detalle")->assertOk()->json();
    }

    public function test_trae_los_datos_del_proceso_su_proyecto_y_sus_contratos(): void
    {
        $fuente = FuenteFinanciacion::create(['clave' => 'f_local', 'nombre' => 'Fondo local', 'orden' => 99, 'activo' => true]);
        $responsable = Responsable::create(['nombre' => 'Ana Pérez', 'activo' => true]);
        $proceso = $this->proceso([
            'monto' => 2500000, 'fuente_id' => $fuente->id, 'responsable_id' => $responsable->id,
            'fecha_apertura' => '2026-11-01', 'fecha_cierre' => '2026-11-30',
            'url_proceso' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.1',
            'observaciones' => 'Con visita de obra.', 'estado' => ProcesoLicitacion::ADJUDICADO,
        ]);
        $primero = $this->contrato($proceso, 'C-001', ['valor' => 1200000]);
        $segundo = $this->contrato($proceso, 'C-002', ['estado' => Contrato::ANULADO, 'valor' => null, 'contratista' => null]);
        $this->contrato($this->proceso(), 'C-999');

        $detalle = $this->detalle($proceso);

        $this->assertSame(['proyecto', 'proceso', 'admite_contratos', 'contratos'], array_keys($detalle));
        $this->assertSame(['id' => $this->proyecto->id, 'nombre' => 'Acueducto veredal', 'fuente_ids' => []], $detalle['proyecto']);
        // El proceso, con la misma forma de la lista de procesos del proyecto (la del formulario).
        $lista = $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk()->json('procesos.0');
        $this->assertSame($lista, $detalle['proceso']);
        $this->assertSame('2500000.00', $detalle['proceso']['monto']);
        $this->assertSame(['id' => $fuente->id, 'nombre' => 'Fondo local'], $detalle['proceso']['fuente']);
        $this->assertSame(['id' => $responsable->id, 'nombre' => 'Ana Pérez'], $detalle['proceso']['responsable']);
        $this->assertSame('2026-11-01', $detalle['proceso']['fecha_apertura']);
        $this->assertSame('2026-11-30', $detalle['proceso']['fecha_cierre']);
        $this->assertSame('secop_ii', $detalle['proceso']['plataforma']);
        $this->assertStringStartsWith('https://community.secop.gov.co/', $detalle['proceso']['url_proceso']);
        $this->assertSame('Con visita de obra.', $detalle['proceso']['observaciones']);
        $this->assertSame(2, $detalle['proceso']['contratos']);
        $this->assertNotNull($detalle['proceso']['actualizado']);
        $this->assertNull($detalle['proceso']['expediente']);
        $this->assertTrue($detalle['admite_contratos']);

        $this->assertSame([
            [
                'id' => $primero->id, 'n_contrato' => 'C-001', 'objeto' => 'Construcción del acueducto', 'contratista' => 'Consorcio Agua',
                'estado' => 'en_ejecucion', 'valor_inicial' => '1000000.00', 'valor' => '1200000.00',
            ],
            [
                'id' => $segundo->id, 'n_contrato' => 'C-002', 'objeto' => 'Construcción del acueducto', 'contratista' => null,
                'estado' => 'anulado', 'valor_inicial' => '1000000.00', 'valor' => null,
            ],
        ], $detalle['contratos']);
    }

    public function test_el_proyecto_trae_sus_fuentes_como_la_lista_de_proyectos(): void
    {
        $segunda = FuenteFinanciacion::create(['clave' => 'f_b', 'nombre' => 'Fondo B', 'orden' => 98, 'activo' => true]);
        $primera = FuenteFinanciacion::create(['clave' => 'f_a', 'nombre' => 'Fondo A', 'orden' => 99, 'activo' => true]);
        $otro = Proyecto::create(['nombre' => 'Otro', 'sector' => $this->proyecto->sector]);
        $this->proyecto->fuentes()->attach([$primera->id, $segunda->id]);
        $ajena = FuenteFinanciacion::create(['clave' => 'f_c', 'nombre' => 'Fondo C', 'orden' => 97, 'activo' => true]);
        $otro->fuentes()->attach([$segunda->id, $ajena->id]);

        $detalle = $this->detalle($this->proceso());

        // Los ids de las fuentes del proyecto (enteros, ascendentes; las de otro proyecto no): la
        // forma de `fuente_ids` en la lista de proyectos, con la que el formulario agrupa "Fuentes del proyecto".
        $ids = [min($primera->id, $segunda->id), max($primera->id, $segunda->id)];
        $this->assertSame(['id' => $this->proyecto->id, 'nombre' => 'Acueducto veredal', 'fuente_ids' => $ids], $detalle['proyecto']);
    }

    public function test_con_expediente_trae_su_resumen_y_sin_contratos_una_lista_vacia(): void
    {
        $proceso = $this->proceso();
        $id = $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => [$this->plantilla()->id]])
            ->assertCreated()->json('id');

        $detalle = $this->detalle($proceso);

        $this->assertSame($id, $detalle['proceso']['expediente']['id']);
        $this->assertSame(['id', 'estado', 'progreso', 'aviso_plantilla'], array_keys($detalle['proceso']['expediente']));
        $this->assertSame(['completadas' => 0, 'total' => 1], $detalle['proceso']['avisos']['hitos']);
        $this->assertFalse($detalle['admite_contratos']);
        $this->assertSame([], $detalle['contratos']);
    }

    public function test_admite_contratos_solo_adjudicado_o_celebrado(): void
    {
        foreach (ProcesoLicitacion::ESTADOS as $estado) {
            $this->assertSame(
                in_array($estado, ProcesoLicitacion::ADMITEN_CONTRATOS, true),
                $this->detalle($this->proceso(['estado' => $estado]))['admite_contratos'],
                $estado
            );
        }
    }

    public function test_el_actualizado_sirve_para_editar_y_cambiar_el_estado(): void
    {
        $proceso = $this->proceso();
        $actualizado = $this->detalle($proceso)['proceso']['actualizado'];

        $editado = $this->como()->putJson("/GestPro/procesos/{$proceso->id}", [
            'objeto' => 'Construcción del acueducto veredal', 'modalidad_id' => $this->modalidad(), 'plataforma' => 'secop_ii',
            'actualizado' => $actualizado,
        ])->assertOk()->json();
        $this->como()->postJson("/GestPro/procesos/{$proceso->id}/estado", [
            'estado' => ProcesoLicitacion::PUBLICADO, 'actualizado' => $editado['actualizado'],
        ])->assertOk();

        $this->assertSame('publicado', $this->detalle($proceso)['proceso']['estado']);
    }

    public function test_cualquier_usuario_autenticado_lo_lee_y_un_proceso_que_no_existe_es_404(): void
    {
        $proceso = $this->proceso();
        $this->getJson("/GestPro/procesos/{$proceso->id}/detalle")->assertUnauthorized();
        foreach ([Rol::Consulta, Rol::Gestor, Rol::Administrador] as $rol) {
            $this->como($rol)->getJson("/GestPro/procesos/{$proceso->id}/detalle")->assertOk();
        }
        $this->como(Rol::Consulta)->getJson('/GestPro/procesos/999999/detalle')->assertNotFound();
    }

    public function test_las_consultas_no_crecen_con_los_contratos(): void
    {
        $proceso = $this->proceso(['estado' => ProcesoLicitacion::ADJUDICADO]);
        $this->contrato($proceso, 'C-001');
        $consultas = function () use ($proceso): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson("/GestPro/procesos/{$proceso->id}/detalle")->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $conUno = $consultas();
        foreach (range(2, 5) as $i) {
            $this->contrato($proceso, "C-00{$i}");
        }

        $this->assertSame($conUno, $consultas());
        $this->assertCount(5, $this->detalle($proceso)['contratos']);
    }
}
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `php artisan test tests/Feature/Contratacion/DetalleProcesoApiTest.php`
Expected: FALLAN las 7 de `DetalleProcesoApiTest`: la ruta `GET /procesos/{proceso}/detalle` aún no existe y la atiende la ruta comodín del SPA, que responde la página HTML con 200. Las que leen la respuesta dicen "Invalid JSON was returned from the route." y la de sin token, "Failed asserting that 200 is identical to 401.".

- [ ] **Paso 3: Implementar**

En `app/Http/Controllers/ProcesoContratacionController.php`, reemplazar (1 de 1):

```php
        ]);
    }

    /** Lo que precarga el formulario de un contrato nuevo del proceso. */
    public function precargaContrato(ProcesoLicitacion $proceso): JsonResponse
    {
```

por:

```php
        ]);
    }

    /** La vista detallada del proceso: sus datos, su proyecto y sus contratos (ProcesosContratacion::detalle). */
    public function detalle(ProcesoLicitacion $proceso): JsonResponse
    {
        return response()->json($this->procesos->detalle($proceso));
    }

    /** Lo que precarga el formulario de un contrato nuevo del proceso. */
    public function precargaContrato(ProcesoLicitacion $proceso): JsonResponse
    {
```

En `app/Services/Contratacion/ProcesosContratacion.php`, reemplazar (1 de 1):

```php
        return $this->forma($proceso, $this->expedientes->resumenDeProcesos(collect([$proceso]))[$proceso->id]);
    }

    /** @param array{objeto: string, modalidad_id: int, plataforma: string, codigo_proceso?: ?string, url_proceso?: ?string, entidad_contratante_id?: ?int, monto?: int|float|string|null, fuente_id?: ?int, responsable_id?: ?int, fecha_apertura?: ?string, fecha_cierre?: ?string, observaciones?: ?string} $datos */
    public function crear(Proyecto $proyecto, array $datos, ?User $usuario): ProcesoLicitacion
    {
```

por:

```php
        return $this->forma($proceso, $this->expedientes->resumenDeProcesos(collect([$proceso]))[$proceso->id]);
    }

    /**
     * La vista detallada del proceso, tenga o no expediente: su proyecto, el proceso con la forma
     * de presentar() (la del formulario de editar y cambiar el estado), si admite contratos y sus
     * contratos en el orden en que se registraron (también los anulados), con el valor inicial y
     * el vigente (`valor`). Las consultas no crecen con los contratos.
     *
     * `proyecto.fuente_ids`: las fuentes del proyecto (ids ascendentes), como `fuente_ids` de GET
     * /proyectos/{id}, para agrupar "Fuentes del proyecto" en el formulario.
     *
     * @return array{proyecto: ?array{id: int, nombre: string, fuente_ids: list<int>}, proceso: array, admite_contratos: bool, contratos: list<array{id: int, n_contrato: ?string, objeto: ?string, contratista: ?string, estado: ?string, valor_inicial: ?string, valor: ?string}>}
     */
    public function detalle(ProcesoLicitacion $proceso): array
    {
        $proceso->load('proyectoRel:id,nombre');
        $proyecto = $proceso->proyectoRel;

        return [
            'proyecto' => $proyecto ? [
                'id' => $proyecto->id,
                'nombre' => $proyecto->nombre,
                'fuente_ids' => DB::table('proyecto_fuentes')->where('proyecto_id', $proyecto->id)
                    ->orderBy('fuente_id')->pluck('fuente_id')->map(fn ($id) => (int) $id)->values()->all(),
            ] : null,
            'proceso' => $this->presentar($proceso),
            'admite_contratos' => in_array($proceso->estado, ProcesoLicitacion::ADMITEN_CONTRATOS, true),
            'contratos' => $proceso->contratos()
                ->orderBy('id')
                ->get(['id', 'n_contrato', 'objeto', 'contratista', 'estado', 'valor_inicial', 'valor'])
                ->map(fn (Contrato $c) => [
                    'id' => $c->id,
                    'n_contrato' => $c->n_contrato,
                    'objeto' => $c->objeto,
                    'contratista' => $c->contratista,
                    'estado' => $c->estado,
                    'valor_inicial' => $c->valor_inicial,
                    'valor' => $c->valor,
                ])->all(),
        ];
    }

    /** @param array{objeto: string, modalidad_id: int, plataforma: string, codigo_proceso?: ?string, url_proceso?: ?string, entidad_contratante_id?: ?int, monto?: int|float|string|null, fuente_id?: ?int, responsable_id?: ?int, fecha_apertura?: ?string, fecha_cierre?: ?string, observaciones?: ?string} $datos */
    public function crear(Proyecto $proyecto, array $datos, ?User $usuario): ProcesoLicitacion
    {
```

En `routes/contratacion.php`, reemplazar (1 de 1):

```php
// Lectura: cualquier usuario autenticado.
Route::get('/proyectos/{proyecto}/procesos', [ProcesoContratacionController::class, 'index'])
    ->name('procesos.index');
Route::get('/procesos/{proceso}/precarga-contrato', [ProcesoContratacionController::class, 'precargaContrato'])
    ->name('procesos.precarga-contrato');
Route::get('/procesos/{proceso}/plantillas-compatibles', [ProcesoContratacionController::class, 'plantillasCompatibles'])
```

por:

```php
// Lectura: cualquier usuario autenticado.
Route::get('/proyectos/{proyecto}/procesos', [ProcesoContratacionController::class, 'index'])
    ->name('procesos.index');
// Vista detallada del proceso (la página /contratacion/:procesoId). Sin GET /procesos/{id}: como
// con los contratos, se deja libre por si el SPA la usa.
Route::get('/procesos/{proceso}/detalle', [ProcesoContratacionController::class, 'detalle'])
    ->whereNumber('proceso')
    ->name('procesos.detalle');
Route::get('/procesos/{proceso}/precarga-contrato', [ProcesoContratacionController::class, 'precargaContrato'])
    ->name('procesos.precarga-contrato');
Route::get('/procesos/{proceso}/plantillas-compatibles', [ProcesoContratacionController::class, 'plantillasCompatibles'])
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `php artisan test tests/Feature/Contratacion/DetalleProcesoApiTest.php`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1313 passed.

- [ ] **Paso 5: Commit**

```bash
git add tests/Feature/Contratacion/DetalleProcesoApiTest.php app/Http/Controllers/ProcesoContratacionController.php app/Services/Contratacion/ProcesosContratacion.php routes/contratacion.php
git commit -m "feat: process detail API for the contracting page"
```

---

### Tarea 4: Pestaña Contratación del tablero y página del proceso

La vista `CONTRATACION` del tablero (solo en el tablero normal, fuera de la sala) con `ContratacionPanel.jsx`: las 6 tarjetas por semáforo, por estado, los procesos por etapa actual como lista por etapa, los filtros en la URL y la tabla con el enlace al checklist. En la página del proceso, `ProcesoDetalle.jsx` con los bloques "Proceso" y "Contratos", "Editar proceso", "Cambiar estado" y "Registrar contrato"; los catálogos se cargan al abrir el formulario y el formulario se congela al abrir, para que tras un 409 un segundo guardado vuelva a dar 409. El formulario, el de estado, el diálogo del contrato y el guardado pasan de `ProcesosProyecto.jsx` a `FormulariosProceso.jsx`, sin cambiar el diálogo de la ficha. Las utilidades puras, en `utils/contratacionPanel.js` y `utils/expedienteContratacion.js`, con pruebas Vitest.

**Files:**
- Create: `resources/js/components/ContratacionPanel.jsx`
- Create: `resources/js/components/FormulariosProceso.jsx`
- Create: `resources/js/components/ProcesoDetalle.jsx`
- Create: `resources/js/utils/contratacionPanel.js`
- Test (create): `resources/js/utils/contratacionPanel.test.js`
- Modify: `resources/css/ExpedienteContratacion.css`
- Modify: `resources/css/Tablero.css`
- Modify: `resources/js/components/Dashboard.jsx`
- Modify: `resources/js/components/ExpedienteContratacion.jsx`
- Modify: `resources/js/components/ProcesosProyecto.jsx`
- Modify: `resources/js/utils/expedienteContratacion.js`
- Test: `resources/js/utils/expedienteContratacion.test.js`
- Modify: `resources/js/utils/tablero.js`
- Test: `resources/js/utils/tablero.test.js`

- [ ] **Paso 1: Escribir las pruebas que fallan**

Crear `resources/js/utils/contratacionPanel.test.js`:

```js
import { describe, it, expect } from 'vitest';
import {
    FILTROS_VACIOS,
    SEMAFOROS,
    alternarFiltro,
    consultaConFiltros,
    etapasActuales,
    etiquetaSemaforo,
    filaProceso,
    filtrosDeConsulta,
    filtrosFueraDeOpciones,
    hayFiltros,
    opcionesFiltros,
    parametrosApi,
    tarjetasEstado,
    tarjetasSemaforo,
    textoListaVacia,
} from './contratacionPanel';
// El monto sale con el formato de moneda de los procesos (textoValor).
import { textoValor as textoMonto } from './contratacion';

const consulta = (texto) => new URLSearchParams(texto);

const resumen = {
    total: 3,
    por_estado: { preparacion: 2, publicado: 1, evaluacion: 0, adjudicado: 0, desierto: 0, celebrado: 0, cancelado: 0, revocado: 0 },
    por_semaforo: { vencida: 1, proxima: 0, al_dia: 1, sin_expediente: 1, terminado: 0 },
    por_etapa: [
        { id: 1, nombre: 'Planeación', orden: 1, procesos: 1 },
        { id: 2, nombre: 'Selección', orden: 2, procesos: 1 },
    ],
};

const opciones = {
    modalidades: [{ id: 1, nombre: 'Licitación pública' }, { id: 5, nombre: 'Mínima cuantía' }],
    plataformas: ['secop_ii', 'ninguna'],
    fuentes: [{ id: 3, nombre: 'SGR' }],
    responsables: [{ id: 2, nombre: 'Ana Pérez' }],
};

const proceso = {
    id: 7,
    codigo_proceso: 'LP-001-2026',
    url_proceso: null,
    objeto: 'Construcción del acueducto',
    modalidad: { id: 1, nombre: 'Licitación pública' },
    plataforma: 'secop_ii',
    fuente: { id: 3, nombre: 'SGR' },
    responsable: { id: 2, nombre: 'Ana Pérez' },
    monto: '1500000.00',
    estado: 'preparacion',
    fecha_cierre: '2026-11-21',
    proyecto: { id: 4, nombre: 'Acueducto veredal' },
    expediente: { id: 12, estado: 'borrador', progreso: { total: 4, cumplidos: 1, porcentaje: 25 }, aviso_plantilla: null },
    avisos: { etapas_vencidas: 1 },
    etapa_actual: { id: 1, nombre: 'Planeación', orden: 1, estado_revision: 'devuelta' },
    semaforo: 'vencida',
};

describe('semáforo', () => {
    it('tiene los cinco valores de la API, en su orden', () => {
        expect(SEMAFOROS.map(([v]) => v)).toEqual(['vencida', 'proxima', 'al_dia', 'sin_expediente', 'terminado']);
    });

    it('da la etiqueta y el tono; un valor desconocido no inventa uno', () => {
        expect(etiquetaSemaforo('vencida')).toEqual({ valor: 'vencida', texto: 'Vencida', tono: 'coral' });
        expect(etiquetaSemaforo('al_dia')).toMatchObject({ texto: 'Al día', tono: 'verde' });
        expect(etiquetaSemaforo('otro')).toBeNull();
    });
});

describe('filtros en la URL', () => {
    it('sin parámetros, ningún filtro', () => {
        expect(filtrosDeConsulta(consulta(''))).toEqual(FILTROS_VACIOS);
        expect(hayFiltros(FILTROS_VACIOS)).toBe(false);
    });

    it('lee los válidos y descarta los que la API rechazaría', () => {
        const filtros = filtrosDeConsulta(consulta('vista=contratacion&modalidad_id=5&plataforma=secop_ii&fuente_id=abc&responsable_id=0&estado=publicado&semaforo=roja'));
        expect(filtros).toEqual({ ...FILTROS_VACIOS, modalidad_id: '5', plataforma: 'secop_ii', estado: 'publicado' });
        expect(hayFiltros(filtros)).toBe(true);
    });

    it('a la API solo van los filtros con valor', () => {
        expect(parametrosApi({ ...FILTROS_VACIOS, semaforo: 'vencida', fuente_id: '3' })).toEqual({ fuente_id: '3', semaforo: 'vencida' });
        expect(parametrosApi(FILTROS_VACIOS)).toEqual({});
    });

    it('al escribirlos en la URL conserva la vista y quita los vacíos', () => {
        const siguiente = consultaConFiltros(consulta('vista=contratacion&estado=publicado'), { ...FILTROS_VACIOS, semaforo: 'proxima' });
        expect(siguiente.toString()).toBe('vista=contratacion&semaforo=proxima');
    });

    it('alterna el filtro de una tarjeta: elegirla filtra, volver a elegirla lo quita', () => {
        const con = alternarFiltro(FILTROS_VACIOS, 'semaforo', 'vencida');
        expect(con.semaforo).toBe('vencida');
        expect(alternarFiltro(con, 'semaforo', 'vencida').semaforo).toBe('');
        expect(alternarFiltro(con, 'semaforo', '').semaforo).toBe('');
    });

    it('señala los filtros cuyo valor ya no está entre las opciones del servidor', () => {
        const filtros = { ...FILTROS_VACIOS, modalidad_id: '99', plataforma: 'tvec', fuente_id: '3', responsable_id: '2', estado: 'adjudicado' };
        expect(filtrosFueraDeOpciones(filtros, opciones)).toEqual(['modalidad_id', 'plataforma']);
        expect(filtrosFueraDeOpciones(FILTROS_VACIOS, opciones)).toEqual([]);
        expect(filtrosFueraDeOpciones(filtros, null)).toEqual([]);
    });
});

describe('opcionesFiltros', () => {
    it('arma las opciones de cada select desde las del servidor', () => {
        const o = opcionesFiltros(opciones);
        expect(o.modalidad_id).toEqual([{ valor: '1', etiqueta: 'Licitación pública' }, { valor: '5', etiqueta: 'Mínima cuantía' }]);
        expect(o.plataforma).toEqual([{ valor: 'secop_ii', etiqueta: 'SECOP II' }, { valor: 'ninguna', etiqueta: 'Sin plataforma' }]);
        expect(o.fuente_id).toEqual([{ valor: '3', etiqueta: 'SGR' }]);
        expect(o.responsable_id).toEqual([{ valor: '2', etiqueta: 'Ana Pérez' }]);
        expect(o.estado).toHaveLength(8);
        expect(o.estado[0]).toEqual({ valor: 'preparacion', etiqueta: 'En preparación' });
        expect(o.semaforo.map((s) => s.valor)).toEqual(['vencida', 'proxima', 'al_dia', 'sin_expediente', 'terminado']);
    });

    it('sin opciones, listas vacías (y los estados y semáforos de siempre)', () => {
        const o = opcionesFiltros(null);
        expect(o.modalidad_id).toEqual([]);
        expect(o.plataforma).toEqual([]);
        expect(o.estado).toHaveLength(8);
    });
});

describe('tarjetas', () => {
    it('por semáforo: las cinco, con su cantidad y la activa marcada', () => {
        const tarjetas = tarjetasSemaforo(resumen, 'vencida');
        expect(tarjetas.map((t) => [t.valor, t.cantidad, t.activa])).toEqual([
            ['vencida', 1, true], ['proxima', 0, false], ['al_dia', 1, false], ['sin_expediente', 1, false], ['terminado', 0, false],
        ]);
        expect(tarjetas[0]).toMatchObject({ etiqueta: 'Vencida', tono: 'coral' });
    });

    it('por estado: solo los que tienen procesos, y el filtrado aunque quede en cero', () => {
        expect(tarjetasEstado(resumen, '').map((t) => [t.valor, t.cantidad])).toEqual([['preparacion', 2], ['publicado', 1]]);
        expect(tarjetasEstado(resumen, 'celebrado').map((t) => t.valor)).toEqual(['preparacion', 'publicado', 'celebrado']);
        expect(tarjetasEstado(resumen, 'publicado')[1]).toMatchObject({ etiqueta: 'Publicado', activa: true });
    });

    it('sin resumen, todo en cero', () => {
        expect(tarjetasSemaforo(null, '').every((t) => t.cantidad === 0)).toBe(true);
        expect(tarjetasEstado(null, '')).toEqual([]);
    });
});

describe('etapasActuales', () => {
    it('cuenta los procesos de cada etapa actual y los que no tienen etapa actual', () => {
        const { etapas, sinEtapa } = etapasActuales(resumen);
        expect(etapas).toEqual([
            { id: 1, nombre: 'Planeación', orden: 1, procesos: 1, porcentaje: 33 },
            { id: 2, nombre: 'Selección', orden: 2, procesos: 1, porcentaje: 33 },
        ]);
        expect(sinEtapa).toBe(1);
    });

    it('sin procesos no divide por cero', () => {
        expect(etapasActuales({ total: 0, por_etapa: [] })).toEqual({ etapas: [], sinEtapa: 0 });
        expect(etapasActuales(null)).toEqual({ etapas: [], sinEtapa: 0 });
    });
});

describe('filaProceso', () => {
    it('presenta un proceso de la API para la tabla', () => {
        expect(filaProceso(proceso)).toEqual({
            id: 7,
            numero: { texto: 'LP-001-2026', enlace: null },
            objeto: 'Construcción del acueducto',
            proyecto: { id: 4, nombre: 'Acueducto veredal', ruta: '/proyectos/4' },
            modalidad: 'Licitación pública',
            monto: textoMonto('1500000'),
            estado: { texto: 'En preparación', tono: 'neutro' },
            etapa: { texto: 'Planeación', revision: { texto: 'Devuelta', tono: 'coral' } },
            avance: 25,
            cierre: '21/11/2026',
            semaforo: { valor: 'vencida', texto: 'Vencida', tono: 'coral' },
            avisos: { etapas_vencidas: 1 },
            rutaChecklist: '/contratacion/7',
        });
    });

    it('sin etapa actual explica por qué', () => {
        expect(filaProceso({ ...proceso, expediente: null, etapa_actual: null }).etapa).toEqual({ texto: 'Sin expediente', revision: null });
        expect(filaProceso({ ...proceso, expediente: { ...proceso.expediente, estado: 'cerrado' }, etapa_actual: null }).etapa.texto).toBe('Expediente cerrado');
        expect(filaProceso({ ...proceso, etapa_actual: null }).etapa.texto).toBe('Todas aprobadas');
    });

    it('un expediente abierto sin requisitos que cuenten no tiene etapas aprobadas: "Sin requisitos"', () => {
        const vacio = { ...proceso.expediente, estado: 'borrador', progreso: { ...proceso.expediente.progreso, total: 0, cumplidos: 0, porcentaje: 0 } };
        expect(filaProceso({ ...proceso, expediente: vacio, etapa_actual: null }).etapa).toEqual({ texto: 'Sin requisitos', revision: null });
        expect(filaProceso({ ...proceso, expediente: { ...vacio, progreso: null }, etapa_actual: null }).etapa.texto).toBe('Sin requisitos');
        // Cerrado sigue diciendo que está cerrado, tenga o no requisitos.
        expect(filaProceso({ ...proceso, expediente: { ...vacio, estado: 'cerrado' }, etapa_actual: null }).etapa.texto).toBe('Expediente cerrado');
    });

    it('una revisión pendiente no se anuncia: es lo normal de la etapa actual', () => {
        expect(filaProceso({ ...proceso, etapa_actual: { ...proceso.etapa_actual, estado_revision: 'pendiente' } }).etapa.revision).toBeNull();
        expect(filaProceso({ ...proceso, etapa_actual: { ...proceso.etapa_actual, estado_revision: 'lista' } }).etapa.revision.texto).toBe('Lista para revisar');
    });

    it('sin expediente no hay avance, y sin proyecto ni datos usa guiones', () => {
        const fila = filaProceso({ ...proceso, expediente: null, proyecto: null, modalidad: null, monto: null, fecha_cierre: null, objeto: '', codigo_proceso: null });
        expect(fila).toMatchObject({ avance: null, proyecto: null, modalidad: '—', monto: '—', cierre: '—', objeto: 'Sin objeto', numero: { texto: '—' } });
    });
});

describe('textoListaVacia', () => {
    it('distingue no tener procesos de que ninguno coincida', () => {
        expect(textoListaVacia(false)).toBe('Aún no hay procesos de contratación registrados.');
        expect(textoListaVacia(true)).toBe('Ningún proceso coincide con los filtros elegidos.');
    });
});
```

En `resources/js/utils/expedienteContratacion.test.js`, reemplazar (1 de 2):

```js
    textoPendientesCierre,
    textoSinPlantillas,
} from './expedienteContratacion';
import { agruparChecklist, describirEvento } from './expedienteChecklist';

const etapa = (id, orden, nombre = `Etapa ${id}`) => ({ id, nombre, orden });
```

por:

```js
    textoPendientesCierre,
    textoSinPlantillas,
} from './expedienteContratacion';
import {
    abrirFormularioDetalle, enlaceProceso, filaContratoProceso, fuentesDelDetalle, procesoDelFormulario, puedeRegistrarDesdeDetalle,
    textoContratosProceso,
} from './expedienteContratacion';
import { agruparChecklist, describirEvento } from './expedienteChecklist';

const etapa = (id, orden, nombre = `Etapa ${id}`) => ({ id, nombre, orden });
```

En `resources/js/utils/expedienteContratacion.test.js`, reemplazar (2 de 2):

```js
        await expect(aprobarConConfirmacion({ faltan: 0, enviar: vi.fn().mockRejectedValue(fallo), confirmar: vi.fn() })).rejects.toBe(fallo);
    });
});
```

por:

```js
        await expect(aprobarConConfirmacion({ faltan: 0, enviar: vi.fn().mockRejectedValue(fallo), confirmar: vi.fn() })).rejects.toBe(fallo);
    });
});

describe('detalle del proceso: contratos', () => {
    const contrato = { id: 31, n_contrato: 'C-001', objeto: 'Construcción', contratista: 'Consorcio Agua', estado: 'en_ejecucion', valor_inicial: '1000000.00', valor: '1200000.00' };

    it('presenta un contrato del proceso con su valor vigente y su enlace', () => {
        expect(filaContratoProceso(contrato)).toMatchObject({
            id: 31,
            numero: 'C-001',
            objeto: 'Construcción',
            contratista: 'Consorcio Agua',
            estado: 'En ejecución',
            tono: 'verde',
            ruta: '/contratos/31',
        });
        expect(filaContratoProceso(contrato).valor).toBe(filaContratoProceso({ ...contrato, valor: null, valor_inicial: '1200000.00' }).valor);
    });

    it('sin valor vigente usa el inicial, y sin ninguno un guion', () => {
        expect(filaContratoProceso({ ...contrato, valor: null }).valor).toMatch(/1\.000\.000/);
        expect(filaContratoProceso({ ...contrato, valor: null, valor_inicial: null }).valor).toBe('—');
        expect(filaContratoProceso({ ...contrato, n_contrato: '' }).numero).toBe('Sin número');
    });

    it('marca los anulados', () => {
        expect(filaContratoProceso(contrato).anulado).toBe(false);
        expect(filaContratoProceso({ ...contrato, estado: 'anulado' }).anulado).toBe(true);
    });

    it('cuenta los contratos', () => {
        expect(textoContratosProceso([])).toBe('Este proceso aún no tiene contratos.');
        expect(textoContratosProceso([contrato])).toBe('1 contrato');
        expect(textoContratosProceso([contrato, contrato])).toBe('2 contratos');
    });

    it('"Registrar contrato": quien edita, si el proceso los admite y tiene proyecto', () => {
        const detalle = { admite_contratos: true, proyecto: { id: 4, nombre: 'Acueducto' } };
        expect(puedeRegistrarDesdeDetalle({ rol: 'Gestor' }, detalle)).toBe(true);
        expect(puedeRegistrarDesdeDetalle({ rol: 'Administrador' }, detalle)).toBe(true);
        expect(puedeRegistrarDesdeDetalle({ rol: 'Consulta' }, detalle)).toBe(false);
        expect(puedeRegistrarDesdeDetalle({ rol: 'Gestor' }, { ...detalle, admite_contratos: false })).toBe(false);
        expect(puedeRegistrarDesdeDetalle({ rol: 'Gestor' }, { ...detalle, proyecto: null })).toBe(false);
        expect(puedeRegistrarDesdeDetalle({ rol: 'Gestor' }, null)).toBe(false);
    });
});

describe('detalle del proceso: enlace a la plataforma', () => {
    it('con un enlace web, lo ofrece con el nombre de la plataforma', () => {
        expect(enlaceProceso({ url_proceso: 'https://community.secop.gov.co/x', plataforma: 'secop_ii' }))
            .toEqual({ enlace: 'https://community.secop.gov.co/x', texto: 'Ver el proceso en SECOP II' });
        expect(enlaceProceso({ url_proceso: ' https://tienda.gov.co/y ', plataforma: 'tvec' }).texto).toBe('Ver el proceso en Tienda Virtual');
        expect(enlaceProceso({ url_proceso: 'https://x.gov.co', plataforma: 'ninguna' }).texto).toBe('Ver el proceso');
    });

    it('sin enlace, o con uno que no es web, ninguno', () => {
        expect(enlaceProceso({ url_proceso: null, plataforma: 'secop_ii' })).toBeNull();
        expect(enlaceProceso({ url_proceso: 'javascript:alert(1)', plataforma: 'secop_ii' })).toBeNull();
        expect(enlaceProceso(null)).toBeNull();
    });
});

describe('formularios del detalle del proceso', () => {
    const v1 = { id: 7, estado: 'preparacion', actualizado: '2026-11-19T10:00:00.000000Z' };
    const v2 = { ...v1, estado: 'publicado', actualizado: '2026-11-19T10:05:00.000000Z' };

    it('congela el proceso al abrir: tras un 409 el detalle se recarga, pero el siguiente intento sale con el actualizado de cuando se abrió', () => {
        const modo = abrirFormularioDetalle('editar', v1);
        // El detalle recargado (v2) no cambia lo que se guarda: otra vez 409 en lugar de pisar el cambio ajeno.
        const detalleRecargado = { proceso: v2 };
        expect(procesoDelFormulario(modo, 'editar')).toBe(v1);
        expect(procesoDelFormulario(modo, 'editar')).not.toBe(detalleRecargado.proceso);
        expect(procesoDelFormulario(abrirFormularioDetalle('estado', v1), 'estado').actualizado).toBe(v1.actualizado);
    });

    it('solo da el proceso del formulario que está abierto', () => {
        expect(procesoDelFormulario(abrirFormularioDetalle('estado', v1), 'editar')).toBeNull();
        expect(procesoDelFormulario(null, 'editar')).toBeNull();
        expect(abrirFormularioDetalle('contrato', v1)).toEqual({ tipo: 'contrato', proceso: v1 });
    });

    it('las fuentes del proyecto del detalle, para agrupar "Fuentes del proyecto"', () => {
        expect(fuentesDelDetalle({ proyecto: { id: 4, nombre: 'P', fuente_ids: [2, 5] } })).toEqual([2, 5]);
        expect(fuentesDelDetalle({ proyecto: { id: 4, nombre: 'P' } })).toEqual([]);
        expect(fuentesDelDetalle({ proyecto: null })).toEqual([]);
        expect(fuentesDelDetalle(null)).toEqual([]);
    });
});
```

En `resources/js/utils/tablero.test.js`, reemplazar (1 de 2):

```js
        expect(resolverVista(VISTA.EXPEDIENTES, [])).toBe(VISTA.EXPEDIENTES);
        expect(resolverVista(null, [])).toBeNull();
    });
});

describe('resumirFases', () => {
```

por:

```js
        expect(resolverVista(VISTA.EXPEDIENTES, [])).toBe(VISTA.EXPEDIENTES);
        expect(resolverVista(null, [])).toBeNull();
    });

    it('acepta la vista de Contratación', () => {
        expect(VISTA.CONTRATACION).toBe('contratacion');
        expect(resolverVista('contratacion', fases)).toBe(VISTA.CONTRATACION);
        expect(resolverVista(VISTA.CONTRATACION, [])).toBe(VISTA.CONTRATACION);
    });
});

describe('resumirFases', () => {
```

En `resources/js/utils/tablero.test.js`, reemplazar (2 de 2):

```js
        expect(secuenciaSala(fases)).toEqual(['formulacion', 'licitacion', 'ejecucion', VISTA.CONTRATOS, VISTA.EXPEDIENTES]);
    });

    it('avanza en círculo', () => {
        const secuencia = ['a', 'b', 'c'];
```

por:

```js
        expect(secuenciaSala(fases)).toEqual(['formulacion', 'licitacion', 'ejecucion', VISTA.CONTRATOS, VISTA.EXPEDIENTES]);
    });

    it('no pasa por Contratación: es solo del tablero normal', () => {
        expect(secuenciaSala(fases)).not.toContain(VISTA.CONTRATACION);
        expect(secuenciaSala([])).toEqual([VISTA.CONTRATOS, VISTA.EXPEDIENTES]);
    });

    it('avanza en círculo', () => {
        const secuencia = ['a', 'b', 'c'];
```

- [ ] **Paso 2: Comprobar que fallan**

Run: `npx vitest run resources/js/utils/contratacionPanel.test.js resources/js/utils/expedienteContratacion.test.js resources/js/utils/tablero.test.js`
Expected: FALLAN los 3 archivos, 11 pruebas, y pasan 125: `contratacionPanel.test.js` no carga, porque `utils/contratacionPanel.js` no existe ("Cannot find module './contratacionPanel'"); en `expedienteContratacion.test.js` fallan 10, las que usan lo nuevo del detalle, como `filaContratoProceso` ("filaContratoProceso is not a function"), `textoContratosProceso`, `puedeRegistrarDesdeDetalle`, `enlaceProceso`, `abrirFormularioDetalle`, `procesoDelFormulario` o `fuentesDelDetalle`; en `tablero.test.js` falla 1, "acepta la vista de Contratación", porque `VISTA.CONTRATACION` aún no existe.

- [ ] **Paso 3: Implementar**

Crear `resources/js/components/ContratacionPanel.jsx`:

```jsx
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faClipboardCheck } from '@fortawesome/free-solid-svg-icons';
import axios from '../axios';
import '../../css/ExpedientesSgrPanel.css';
import '../../css/Contratacion.css';
import ChipsAvisosProceso from './ChipsAvisosProceso';
import { NumeroProceso } from './FormulariosProceso';
import { mensajeError } from '../utils/expedienteChecklist';
import {
    alternarFiltro,
    consultaConFiltros,
    etapasActuales,
    filaProceso,
    filtrosDeConsulta,
    filtrosFueraDeOpciones,
    FILTROS_VACIOS,
    hayFiltros,
    opcionesFiltros,
    parametrosApi,
    tarjetasEstado,
    tarjetasSemaforo,
    textoListaVacia,
} from '../utils/contratacionPanel';

// Los selects de filtro, en el orden en que se muestran: clave, texto de "todos" y nombre accesible.
const SELECTS = [
    ['modalidad_id', 'Todas las modalidades', 'Filtrar por modalidad'],
    ['plataforma', 'Todas las plataformas', 'Filtrar por plataforma'],
    ['fuente_id', 'Todas las fuentes', 'Filtrar por fuente de financiación'],
    ['responsable_id', 'Todos los responsables', 'Filtrar por responsable'],
    ['estado', 'Todos los estados', 'Filtrar por estado del proceso'],
    ['semaforo', 'Todos los semáforos', 'Filtrar por semáforo'],
];

const Semaforo = ({ semaforo }) => (semaforo ? (
    <span className={`ct-estado ct-estado-${semaforo.tono} ctp-semaforo ctp-semaforo-${semaforo.valor}`}>
        <span className="ctp-punto" aria-hidden="true" />{semaforo.texto}
    </span>
) : '—');

const FilaProceso = ({ proceso }) => {
    const fila = filaProceso(proceso);
    return (
        <tr>
            <td data-etiqueta="Número"><NumeroProceso proceso={proceso} /></td>
            <td data-etiqueta="Objeto"><div className="ctp-objeto" title={fila.objeto}>{fila.objeto}</div></td>
            <td data-etiqueta="Proyecto">
                {fila.proyecto ? <Link to={fila.proyecto.ruta} className="ct-enlace">{fila.proyecto.nombre}</Link> : 'Sin proyecto'}
            </td>
            <td data-etiqueta="Modalidad">{fila.modalidad}</td>
            <td data-etiqueta="Monto" className="numero">{fila.monto}</td>
            <td data-etiqueta="Estado"><span className={`ct-estado ct-estado-${fila.estado.tono}`}>{fila.estado.texto}</span></td>
            <td data-etiqueta="Etapa actual">
                <div>{fila.etapa.texto}</div>
                {fila.etapa.revision && <span className={`ct-estado ct-estado-${fila.etapa.revision.tono}`}>{fila.etapa.revision.texto}</span>}
            </td>
            <td data-etiqueta="Avance" className="exs-col-avance">
                {fila.avance === null ? '—' : (
                    <>
                        <div className="exs-avance-texto"><span>Expediente</span><strong>{fila.avance}%</strong></div>
                        <span
                            className="tb-pista"
                            role="progressbar"
                            aria-valuenow={fila.avance}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-label={`Avance del expediente de ${fila.numero.texto === '—' ? fila.objeto : fila.numero.texto}`}
                        >
                            <span style={{ width: `${fila.avance}%`, background: fila.avance === 100 ? 'var(--gp-verde)' : undefined }} />
                        </span>
                    </>
                )}
            </td>
            <td data-etiqueta="Cierre" className="numero">{fila.cierre}</td>
            <td data-etiqueta="Avisos" className="ctp-col-avisos"><ChipsAvisosProceso avisos={fila.avisos} /></td>
            <td data-etiqueta="Semáforo"><Semaforo semaforo={fila.semaforo} /></td>
            <td className="exs-col-accion">
                <Link className="exs-abrir" to={fila.rutaChecklist}>
                    <FontAwesomeIcon icon={faClipboardCheck} aria-hidden="true" /> Checklist
                </Link>
            </td>
        </tr>
    );
};

/**
 * Vista "Contratación" del tablero: todos los procesos de contratación con su semáforo, su etapa
 * actual (la primera sin aprobar) y sus avisos, con tarjetas por semáforo y por estado y filtros
 * que se guardan en la URL junto a la vista. El servidor filtra y cuenta (GET /tablero/contratacion).
 */
const ContratacionPanel = () => {
    const [searchParams, setSearchParams] = useSearchParams();
    const [datos, setDatos] = useState(null);
    const [error, setError] = useState(null);
    const [cargando, setCargando] = useState(true);
    const turno = useRef(0);

    const consulta = searchParams.toString();
    const filtros = useMemo(() => filtrosDeConsulta(new URLSearchParams(consulta)), [consulta]);
    const claveFiltros = JSON.stringify(parametrosApi(filtros));

    const aplicar = useCallback((siguientes) => {
        setSearchParams((previos) => consultaConFiltros(previos, siguientes), { replace: true });
    }, [setSearchParams]);

    // Solo vale la última respuesta: si se cambia de filtro mientras carga, la anterior se descarta.
    const cargar = useCallback(async () => {
        const numero = ++turno.current;
        setCargando(true);
        setError(null);
        try {
            const { data } = await axios.get('/tablero/contratacion', { params: JSON.parse(claveFiltros) });
            if (numero !== turno.current) return;
            setDatos(data);
        } catch (e) {
            if (numero === turno.current) setError(mensajeError(e));
        } finally {
            if (numero === turno.current) setCargando(false);
        }
    }, [claveFiltros]);

    useEffect(() => {
        cargar();
        return () => { turno.current += 1; };
    }, [cargar]);

    // Un filtro cuyo valor ya no tiene ningún proceso (una URL vieja) se quita: no queda oculto.
    useEffect(() => {
        const fuera = filtrosFueraDeOpciones(filtros, datos?.opciones);
        if (fuera.length === 0) return;
        aplicar(Object.fromEntries(Object.entries(filtros).map(([clave, valor]) => [clave, fuera.includes(clave) ? '' : valor])));
    }, [datos, filtros, aplicar]);

    const opciones = useMemo(() => opcionesFiltros(datos?.opciones), [datos]);

    if (error && !datos) {
        return (
            <div className="exs-panel exs-mensaje" role="alert">
                <p>No se pudo cargar el seguimiento de contratación. {error}</p>
                <button type="button" className="gp-boton exs-reintentar" onClick={cargar}>Reintentar</button>
            </div>
        );
    }

    if (!datos) {
        return <div className="exs-panel exs-mensaje" role="status"><p>Cargando procesos de contratación…</p></div>;
    }

    const conFiltros = hayFiltros(filtros);
    if (datos.procesos.length === 0 && !conFiltros) {
        return <div className="exs-panel exs-mensaje"><p>{textoListaVacia(false)}</p></div>;
    }

    const { resumen } = datos;
    const semaforos = tarjetasSemaforo(resumen, filtros.semaforo);
    const estados = tarjetasEstado(resumen, filtros.estado);
    const { etapas, sinEtapa } = etapasActuales(resumen);

    return (
        <div className="exs-panel ctp-panel" aria-busy={cargando}>
            {error && (
                <div className="tb-aviso" role="alert">
                    <span>No se pudieron actualizar los procesos. {error}</span>
                    <button type="button" className="gp-boton" onClick={cargar}>Reintentar</button>
                </div>
            )}

            <div className="exs-resumen ctp-resumen" role="group" aria-label="Procesos por semáforo">
                <button
                    type="button"
                    className={`exs-tarjeta${filtros.semaforo === '' ? ' activa' : ''}`}
                    aria-pressed={filtros.semaforo === ''}
                    onClick={() => aplicar({ ...filtros, semaforo: '' })}
                >
                    <span className="exs-tarjeta-etiqueta">Procesos</span>
                    <span className="exs-tarjeta-valor">{resumen.total}</span>
                </button>
                {semaforos.map((t) => (
                    <button
                        key={t.valor}
                        type="button"
                        className={`exs-tarjeta ctp-tarjeta-${t.valor}${t.activa ? ' activa' : ''}`}
                        aria-pressed={t.activa}
                        onClick={() => aplicar(alternarFiltro(filtros, 'semaforo', t.valor))}
                    >
                        <span className="exs-tarjeta-etiqueta"><span className="ctp-punto" aria-hidden="true" /> {t.etiqueta}</span>
                        <span className="exs-tarjeta-valor">{t.cantidad}</span>
                    </button>
                ))}
            </div>

            <div className="ctp-fila">
                <section className="exs-tabla-marco" aria-labelledby="ctp-estados-titulo">
                    <h3 id="ctp-estados-titulo" className="ctp-subtitulo">Por estado del proceso</h3>
                    {estados.length === 0 ? (
                        <p className="exs-secundario">Ningún proceso con estos filtros.</p>
                    ) : (
                        <div className="ctp-estados" role="group" aria-label="Filtrar por estado del proceso">
                            {estados.map((t) => (
                                <button
                                    key={t.valor}
                                    type="button"
                                    className={`ctp-estado${t.activa ? ' activa' : ''}`}
                                    aria-pressed={t.activa}
                                    onClick={() => aplicar(alternarFiltro(filtros, 'estado', t.valor))}
                                >
                                    <span className={`ct-estado ct-estado-${t.tono}`}>{t.etiqueta}</span>
                                    <strong>{t.cantidad}</strong>
                                </button>
                            ))}
                        </div>
                    )}
                </section>

                <section className="exs-tabla-marco" aria-labelledby="ctp-etapas-titulo">
                    <h3 id="ctp-etapas-titulo" className="ctp-subtitulo">Procesos por etapa actual</h3>
                    {etapas.length === 0 ? (
                        <p className="exs-secundario">Ningún proceso tiene una etapa en curso.</p>
                    ) : (
                        <ul className="ctp-etapas">
                            {etapas.map((e) => (
                                <li key={e.id}>
                                    <span className="ctp-etapa-nombre">{e.nombre}</span>
                                    <span className="tb-pista" aria-hidden="true"><span style={{ width: `${e.porcentaje}%` }} /></span>
                                    <strong>{e.procesos === 1 ? '1 proceso' : `${e.procesos} procesos`}</strong>
                                </li>
                            ))}
                        </ul>
                    )}
                    {sinEtapa > 0 && (
                        <p className="exs-secundario ctp-sin-etapa">
                            {sinEtapa === 1 ? '1 proceso' : `${sinEtapa} procesos`} sin etapa en curso (sin expediente, cerrado o con todo aprobado).
                        </p>
                    )}
                </section>
            </div>

            <div className="exs-tabla-marco">
                <div className="exs-tabla-encabezado">
                    <h3>Procesos de contratación</h3>
                    <div className="exs-filtros">
                        {SELECTS.map(([clave, todos, nombre]) => (opciones[clave].length > 0 || filtros[clave]) && (
                            <select
                                key={clave}
                                className="exs-filtro"
                                aria-label={nombre}
                                value={filtros[clave]}
                                onChange={(e) => aplicar({ ...filtros, [clave]: e.target.value })}
                            >
                                <option value="">{todos}</option>
                                {opciones[clave].map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                            </select>
                        ))}
                        {conFiltros && <button type="button" className="gp-boton" onClick={() => aplicar(FILTROS_VACIOS)}>Quitar filtros</button>}
                    </div>
                </div>

                {datos.procesos.length === 0 ? (
                    <div className="exs-sin-resultados">
                        <p>{textoListaVacia(true)}</p>
                        <button type="button" className="gp-boton" onClick={() => aplicar(FILTROS_VACIOS)}>Quitar filtros</button>
                    </div>
                ) : (
                    <table className="tb-tabla tb-tabla-cartas exs-tabla ctp-tabla">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Objeto</th>
                                <th>Proyecto</th>
                                <th>Modalidad</th>
                                <th className="numero">Monto</th>
                                <th>Estado</th>
                                <th>Etapa actual</th>
                                <th>Avance</th>
                                <th className="numero">Cierre</th>
                                <th>Avisos</th>
                                <th>Semáforo</th>
                                <th aria-label="Acciones" />
                            </tr>
                        </thead>
                        <tbody>
                            {datos.procesos.map((p) => <FilaProceso key={p.id} proceso={p} />)}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
};

export default ContratacionPanel;
```

Crear `resources/js/components/FormulariosProceso.jsx`:

```jsx
import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowUpRightFromSquare, faFileSignature } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import axios from '../axios';
import { avisarError } from '../utils/avisosPlantillas';
import { mensajeApi } from '../utils/plantillasChecklist';
import {
    CAMPOS_PROCESO, ESTADOS_PROCESO, MAXIMO_OBSERVACION, MAXIMO_OBSERVACIONES_PROCESO, PLATAFORMAS, avisoCambioEstado, datosEstado, datosProceso,
    erroresPorCampo, esEdicionDesactualizada, etiquetaEstadoProceso, formularioProceso, numeroProceso, opcionesConActual, opcionesFuenteProceso,
    ordenarCatalogo, validarProceso, valorEnEdicion,
} from '../utils/contratacion';
import ContratoEditor from './contratos/ContratoEditor';
import useDialogoModal from './useDialogoModal';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';

// Lo que comparten el diálogo de procesos del proyecto (ProcesosProyecto) y la página del proceso
// (ExpedienteContratacion): el formulario del proceso, el cambio de estado, el registro de un
// contrato con la precarga del proceso, los catálogos del formulario y el guardado.

// Catálogos del formulario: su ruta y cómo se guarda cada respuesta.
const comoLista = (datos) => (Array.isArray(datos) ? datos : []);
export const CATALOGOS = [
    { clave: 'modalidades', ruta: '/modalidades', guardar: (d, { setModalidades }) => setModalidades(ordenarCatalogo(d)) },
    { clave: 'entidades', ruta: '/entidades', guardar: (d, { setEntidades }) => setEntidades(comoLista(d)) },
    { clave: 'fuentes', ruta: '/fuentesFinanciacion', guardar: (d, { setFuentes }) => setFuentes(comoLista(d)) },
    { clave: 'responsables', ruta: '/responsables', guardar: (d, { setResponsables }) => setResponsables(comoLista(d)) },
];

/**
 * Los catálogos del formulario del proceso, pedidos al montar. `cargarCatalogos(pendientes)` pide
 * otra vez los indicados (por defecto, todos); cada uno queda en null mientras no carga. Sin
 * modalidades no se crea ni edita; sin los demás el formulario conserva lo que el proceso tiene.
 */
export const useCatalogosProceso = () => {
    const [modalidades, setModalidades] = useState(null);
    const [entidades, setEntidades] = useState(null);
    const [fuentes, setFuentes] = useState(null);
    const [responsables, setResponsables] = useState(null);
    const [errorCatalogos, setErrorCatalogos] = useState(null);
    const turnoCatalogos = useRef(0);

    const cargarCatalogos = useCallback(async (pendientes = CATALOGOS.map((c) => c.clave)) => {
        const numero = ++turnoCatalogos.current;
        setErrorCatalogos(null);
        const pedidos = CATALOGOS.filter((c) => pendientes.includes(c.clave));
        const respuestas = await Promise.allSettled(pedidos.map((c) => axios.get(c.ruta)));
        if (numero !== turnoCatalogos.current) return;
        pedidos.forEach((c, i) => {
            if (respuestas[i].status === 'fulfilled') c.guardar(respuestas[i].value.data, { setModalidades, setEntidades, setFuentes, setResponsables });
        });
        const fallo = respuestas.find((r) => r.status === 'rejected');
        if (fallo) setErrorCatalogos(`No se pudieron cargar las modalidades, entidades, fuentes o responsables: ${mensajeApi(fallo.reason)}`);
    }, []);

    useEffect(() => {
        cargarCatalogos();
        return () => { turnoCatalogos.current += 1; };
    }, [cargarCatalogos]);

    const catalogos = { modalidades, entidades, fuentes, responsables };
    // Las claves de los que aún no cargaron: lo que se pide otra vez con "Reintentar".
    const pendientes = CATALOGOS.map((c) => c.clave).filter((clave) => catalogos[clave] === null);

    return { ...catalogos, errorCatalogos, cargarCatalogos, pendientes };
};

/** Aviso de que otra persona cambió el proceso mientras se editaba: no se guardó el cambio. */
export const avisarProcesoDesactualizado = (e) => Swal.fire({ icon: 'warning', titleText: 'No se guardó tu cambio', text: mensajeApi(e) });

/**
 * Crea (sin `proceso`, en el proyecto `proyectoId`) o edita un proceso. Tras guardar llama a
 * `onGuardado`; si otra persona lo cambió entretanto, a `onDesactualizado(error)`. Devuelve los
 * errores por campo del servidor (para el formulario) o null; los demás errores se avisan.
 */
export const guardarProceso = async ({ proceso, proyectoId, formulario, onGuardado, onDesactualizado }) => {
    try {
        if (proceso) await axios.put(`/procesos/${proceso.id}`, datosProceso(formulario, proceso));
        else await axios.post(`/proyectos/${proyectoId}/procesos`, datosProceso(formulario));
        await onGuardado();
        return null;
    } catch (e) {
        if (esEdicionDesactualizada(e)) {
            await onDesactualizado(e);
            return null;
        }
        const errores = erroresPorCampo(e, CAMPOS_PROCESO);
        if (!errores || errores.otros.length || !Object.keys(errores.campos).length) avisarError('No se pudo guardar el proceso', e);
        return errores?.campos ?? null;
    }
};

/**
 * Cambia el estado de un proceso. Si el cambio es inusual (sale de un cierre o vuelve atrás en el
 * flujo) pide confirmación antes; lo demás, como `guardarProceso`.
 */
export const cambiarEstadoProceso = async ({ proceso, estado, observacion, onGuardado, onDesactualizado }) => {
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
        await onGuardado();
        return null;
    } catch (e) {
        if (esEdicionDesactualizada(e)) {
            await onDesactualizado(e);
            return null;
        }
        const errores = erroresPorCampo(e, ['estado', 'observacion']);
        if (!errores || errores.otros.length || !Object.keys(errores.campos).length) avisarError('No se pudo cambiar el estado', e);
        return errores?.campos ?? null;
    }
};

// Un contrato nuevo no tiene pestañas que recargar: el editor lo cierra quien lo abrió al guardar.
const sinRecargar = async () => null;

/**
 * El editor de un contrato nuevo del proyecto, con ese proceso ya elegido (y su precarga), en un
 * diálogo propio. Los controles de cerrar son los del editor (Cancelar) y la "×"; Escape cancela
 * y un clic en el fondo no descarta lo escrito. Mientras el editor guarda, ni Escape ni la "×" lo
 * cierran.
 */
export const DialogoContrato = ({ proyecto, proceso, onGuardado, onCancelar }) => {
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

export const Campo = ({ etiqueta, error, idError, ancho, children }) => (
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
export const FormularioProceso = ({ proceso, fuenteIds, modalidades, entidades, fuentes, responsables, guardando, onGuardar, onCancelar }) => {
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

    const fuentesProceso = opcionesFuenteProceso(fuentes, proceso?.fuente, fuenteIds);

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
            {campo('fuente_id', 'Fuente de financiación (opcional)', (
                <select {...control('fuente_id')}>
                    <option value="">Sin fuente</option>
                    {fuentesProceso.delProyecto.length > 0 && (
                        <optgroup label="Fuentes del proyecto">
                            {fuentesProceso.delProyecto.map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                        </optgroup>
                    )}
                    {fuentesProceso.delProyecto.length > 0 && fuentesProceso.otras.length > 0 ? (
                        <optgroup label="Otras fuentes">
                            {fuentesProceso.otras.map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                        </optgroup>
                    ) : fuentesProceso.otras.map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                </select>
            ))}
            {campo('responsable_id', 'Responsable (opcional)', (
                <select {...control('responsable_id')}>
                    <option value="">Sin responsable</option>
                    {opcionesConActual(responsables, proceso?.responsable).map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                </select>
            ))}
            {campo('fecha_apertura', 'Fecha de apertura (opcional)', <input type="date" {...control('fecha_apertura')} />)}
            {campo('fecha_cierre', 'Fecha de cierre (opcional)', <input type="date" {...control('fecha_cierre')} />)}
            {campo('observaciones', 'Observaciones (opcional)', (
                <textarea rows={3} maxLength={MAXIMO_OBSERVACIONES_PROCESO} {...control('observaciones')} />
            ), true)}
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
export const FormularioEstado = ({ proceso, guardando, onGuardar, onCancelar }) => {
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

/** El número del proceso; con enlace web, abre el proceso (SECOP) en otra pestaña. */
export const NumeroProceso = ({ proceso }) => {
    const { texto, enlace } = numeroProceso(proceso);
    if (!enlace) return texto;
    return (
        <a href={enlace} target="_blank" rel="noopener noreferrer" className="ct-enlace">
            {texto} <FontAwesomeIcon icon={faArrowUpRightFromSquare} aria-hidden="true" />
            <span className="ct-oculto"> (se abre en otra pestaña)</span>
        </a>
    );
};
```

Crear `resources/js/components/ProcesoDetalle.jsx`:

```jsx
import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { faArrowRightArrowLeft, faArrowUpRightFromSquare, faFileSignature, faPen } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    etiquetaPlataforma, modoTrasGuardarContrato, puedeEditarProcesos, textoFecha, textoValor,
} from '../utils/contratacion';
import {
    abrirFormularioDetalle, enlaceProceso, filaContratoProceso, fuentesDelDetalle, procesoDelFormulario, puedeRegistrarDesdeDetalle, textoContratosProceso,
} from '../utils/expedienteContratacion';
import {
    DialogoContrato, FormularioEstado, FormularioProceso, avisarProcesoDesactualizado, cambiarEstadoProceso,
    guardarProceso, useCatalogosProceso,
} from './FormulariosProceso';
import useOcupado from './useOcupado';

/**
 * El formulario del proceso con sus catálogos, que se piden al abrirlo. Sin modalidades no se
 * edita: se muestra el error con "Reintentar". `fuenteIds`: las del proyecto, para agrupar "Fuentes del proyecto".
 */
const EditorProceso = ({ proceso, fuenteIds, guardando, onGuardar, onCancelar }) => {
    const { modalidades, entidades, fuentes, responsables, errorCatalogos, cargarCatalogos, pendientes } = useCatalogosProceso();

    if (modalidades === null) {
        return errorCatalogos ? (
            <div className="pl-error" role="alert">
                {errorCatalogos}{' '}
                <button type="button" className="gp-boton" onClick={() => cargarCatalogos(pendientes)}>Reintentar</button>{' '}
                <button type="button" className="gp-boton" onClick={onCancelar}>Cancelar</button>
            </div>
        ) : <p className="gp-vacio" role="status">Cargando el formulario…</p>;
    }

    return (
        <>
            {errorCatalogos && (
                <p className="pl-aviso-suave" role="status">
                    {errorCatalogos} <button type="button" className="gp-boton" onClick={() => cargarCatalogos(pendientes)}>Reintentar</button>
                </p>
            )}
            <FormularioProceso
                proceso={proceso}
                fuenteIds={fuenteIds}
                modalidades={modalidades}
                entidades={entidades}
                fuentes={fuentes}
                responsables={responsables}
                guardando={guardando}
                onGuardar={onGuardar}
                onCancelar={onCancelar}
            />
        </>
    );
};

const Dato = ({ titulo, children }) => <div><dt>{titulo}</dt><dd>{children}</dd></div>;

/**
 * Los bloques "Proceso" y "Contratos" de la página del proceso (GET /procesos/{id}/detalle), tenga
 * o no expediente. Quien edita procesos puede editarlo, cambiar su estado y, si el estado lo
 * admite, registrar un contrato con la precarga del proceso; tras guardar, `onCambio` recarga la
 * página (detalle y expediente). Usa los mismos formularios que el diálogo de procesos del proyecto.
 */
const ProcesoDetalle = ({ detalle, error, user, onReintentar, onCambio }) => {
    // null o { tipo: 'editar' | 'estado' | 'contrato', proceso } (abrirFormularioDetalle; la forma de
    // modoTrasGuardarContrato): el proceso queda congelado al abrir, con su `actualizado`.
    const [modo, setModo] = useState(null);
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    const retorno = useRef(null); // data-foco del botón que recupera el foco al cerrar el formulario
    const edita = puedeEditarProcesos(user);

    useEffect(() => {
        if (modo || ocupado || !retorno.current) return;
        contenedor.current?.querySelector(`[data-foco="${retorno.current}"]:not(:disabled)`)?.focus();
        retorno.current = null;
    }, [modo, ocupado, detalle]);

    if (!detalle) {
        return (
            <section className="exc-proceso" aria-label="Datos del proceso">
                {error ? (
                    <div className="exv-aviso" role="alert">
                        No se pudieron cargar los datos y contratos del proceso. {error.mensaje}{' '}
                        {error.reintentable && <button type="button" className="exv-btn" onClick={onReintentar}>Reintentar</button>}
                    </div>
                ) : <p className="gp-vacio" role="status">Cargando los datos del proceso…</p>}
            </section>
        );
    }

    const { proceso, contratos, proyecto } = detalle;
    const enlace = enlaceProceso(proceso);
    const registra = puedeRegistrarDesdeDetalle(user, detalle);

    const abrir = (nuevoModo, foco) => {
        retorno.current = foco;
        setModo(nuevoModo);
    };

    const alGuardar = async () => {
        setModo(null);
        await onCambio();
    };
    // Otra persona cambió el proceso: se avisa y se recarga. El formulario sigue abierto para copiar
    // lo escrito, con el proceso que se congeló al abrirlo: otro "Guardar" vuelve a dar 409 en vez de
    // pisar el cambio ajeno; al cerrarlo y reabrirlo trae lo último.
    const alDesactualizar = (e) => Promise.all([onCambio(), avisarProcesoDesactualizado(e)]);

    const guardar = (formulario) => ejecutar(() => guardarProceso({
        proceso: procesoDelFormulario(modo, 'editar'), formulario, onGuardado: alGuardar, onDesactualizado: alDesactualizar,
    }));
    const cambiarEstado = (estado, observacion) => ejecutar(() => cambiarEstadoProceso({
        proceso: procesoDelFormulario(modo, 'estado'), estado, observacion, onGuardado: alGuardar, onDesactualizado: alDesactualizar,
    }));
    // El contrato se guardó (el editor ya avisó): se cierra y se recarga, porque el proceso pudo pasar a celebrado.
    const alGuardarContrato = () => {
        setModo((actual) => modoTrasGuardarContrato(actual, proceso.id));
        onCambio();
    };

    return (
        <div className="exc-detalle" ref={contenedor}>
            {error && (
                <div className="exv-aviso" role="alert">
                    No se pudieron actualizar los datos del proceso. {error.mensaje}{' '}
                    {error.reintentable && <button type="button" className="exv-btn" onClick={onReintentar}>Reintentar</button>}
                </div>
            )}

            <section className="exc-bloque" aria-labelledby="exc-proceso-titulo">
                <header className="exc-bloque-cabecera">
                    <h3 id="exc-proceso-titulo">Proceso</h3>
                    {edita && !modo && (
                        <div className="pl-tarjeta-acciones exv-no-imprimir">
                            <button type="button" className="gp-boton" data-foco="editar" disabled={ocupado} onClick={() => abrir(abrirFormularioDetalle('editar', proceso), 'editar')}>
                                <FontAwesomeIcon icon={faPen} aria-hidden="true" /> Editar proceso
                            </button>
                            <button type="button" className="gp-boton" data-foco="estado" disabled={ocupado} onClick={() => abrir(abrirFormularioDetalle('estado', proceso), 'estado')}>
                                <FontAwesomeIcon icon={faArrowRightArrowLeft} aria-hidden="true" /> Cambiar estado
                            </button>
                        </div>
                    )}
                </header>

                {modo?.tipo === 'editar' ? (
                    <EditorProceso proceso={modo.proceso} fuenteIds={fuentesDelDetalle(detalle)} guardando={ocupado} onGuardar={guardar} onCancelar={() => setModo(null)} />
                ) : (
                    <>
                        <dl className="pl-datos exc-datos">
                            <Dato titulo="Número">{String(proceso.codigo_proceso ?? '').trim() || '—'}</Dato>
                            <Dato titulo="Monto"><span className="ct-valor">{textoValor(proceso.monto)}</span></Dato>
                            <Dato titulo="Fuente de financiación">{proceso.fuente?.nombre ?? '—'}</Dato>
                            <Dato titulo="Responsable">{proceso.responsable?.nombre ?? '—'}</Dato>
                            <Dato titulo="Apertura">{textoFecha(proceso.fecha_apertura)}</Dato>
                            <Dato titulo="Cierre">{textoFecha(proceso.fecha_cierre)}</Dato>
                            <Dato titulo="Plataforma">
                                {etiquetaPlataforma(proceso.plataforma)}
                                {enlace && (
                                    <>
                                        {' · '}
                                        <a href={enlace.enlace} target="_blank" rel="noopener noreferrer" className="ct-enlace">
                                            {enlace.texto} <FontAwesomeIcon icon={faArrowUpRightFromSquare} aria-hidden="true" />
                                            <span className="ct-oculto"> (se abre en otra pestaña)</span>
                                        </a>
                                    </>
                                )}
                            </Dato>
                            <Dato titulo="Entidad contratante">{proceso.entidad_contratante?.nombre ?? '—'}</Dato>
                        </dl>
                        <div className="exc-observaciones">
                            <b>Observaciones</b>
                            <p>{proceso.observaciones || 'Sin observaciones.'}</p>
                        </div>
                        {modo?.tipo === 'estado' && (
                            <FormularioEstado proceso={modo.proceso} guardando={ocupado} onGuardar={cambiarEstado} onCancelar={() => setModo(null)} />
                        )}
                    </>
                )}
            </section>

            <section className="exc-bloque" aria-labelledby="exc-contratos-titulo">
                <header className="exc-bloque-cabecera">
                    <h3 id="exc-contratos-titulo">Contratos <span className="exc-cuenta">· {textoContratosProceso(contratos)}</span></h3>
                    {registra && (
                        <button
                            type="button"
                            className="gp-boton exv-no-imprimir"
                            data-foco="contrato"
                            disabled={ocupado || modo !== null}
                            onClick={() => abrir(abrirFormularioDetalle('contrato', proceso), 'contrato')}
                        >
                            <FontAwesomeIcon icon={faFileSignature} aria-hidden="true" /> Registrar contrato
                        </button>
                    )}
                </header>
                {contratos.length > 0 && (
                    <ul className="exc-contratos">
                        {contratos.map((c) => {
                            const fila = filaContratoProceso(c);
                            return (
                                <li key={fila.id} className={fila.anulado ? 'anulado' : undefined}>
                                    <Link to={fila.ruta} className="ct-enlace exc-contrato-numero">{fila.numero}</Link>
                                    <span className="exc-contrato-objeto">{fila.objeto}<small>{fila.contratista}</small></span>
                                    <span className={`ct-estado ct-estado-${fila.tono}`}>{fila.estado}</span>
                                    <span className="ct-valor exc-contrato-valor">{fila.valor}</span>
                                </li>
                            );
                        })}
                    </ul>
                )}
                {edita && !detalle.admite_contratos && (
                    <p className="pl-ayuda">Podrás registrar contratos cuando el proceso esté adjudicado o celebrado.</p>
                )}
            </section>

            {modo?.tipo === 'contrato' && proyecto && (
                <DialogoContrato
                    proyecto={proyecto}
                    proceso={modo.proceso}
                    onGuardado={alGuardarContrato}
                    onCancelar={() => setModo(null)}
                />
            )}
        </div>
    );
};

export default ProcesoDetalle;
```

Crear `resources/js/utils/contratacionPanel.js`:

```js
// Vista "Contratación" del tablero (GET /tablero/contratacion): los filtros (en la URL), las
// tarjetas por semáforo y por estado, los procesos por etapa actual y las filas de la tabla. Todo
// sale de lo que calcula el backend (semáforo, etapa actual, avisos): aquí no se mira la fecha de
// hoy ni se suma ni promedia ningún avance (R13). Funciones puras, probadas en contratacionPanel.test.js.

import { etiquetaRevision } from './avisosContratacion';
import { ESTADOS_PROCESO, PLATAFORMAS, etiquetaEstadoProceso, etiquetaPlataforma, numeroProceso, textoFecha, textoValor, tonoEstado } from './contratacion';
import { rutaExpedienteContratacion, rutaFichaProyecto } from './navegacion';

// Los valores son los del backend (TableroContratacion), en su orden de prioridad.
export const SEMAFOROS = [
    ['vencida', 'Vencida', 'coral'],
    ['proxima', 'Próxima', 'ambar'],
    ['al_dia', 'Al día', 'verde'],
    ['sin_expediente', 'Sin expediente', 'neutro'],
    ['terminado', 'Terminado', 'neutro'],
];

/** La etiqueta y el tono (`ct-estado-<tono>`) del semáforo de un proceso; null si no se conoce. */
export const etiquetaSemaforo = (valor) => {
    const encontrado = SEMAFOROS.find(([v]) => v === valor);
    return encontrado ? { valor: encontrado[0], texto: encontrado[1], tono: encontrado[2] } : null;
};

// ---------- Filtros ----------

export const FILTROS_VACIOS = {
    modalidad_id: '',
    plataforma: '',
    fuente_id: '',
    responsable_id: '',
    estado: '',
    semaforo: '',
};

const CLAVES_FILTRO = Object.keys(FILTROS_VACIOS);
const esId = (valor) => /^[1-9]\d*$/.test(valor);
const VALIDOS = {
    modalidad_id: esId,
    plataforma: (v) => PLATAFORMAS.some(([p]) => p === v),
    fuente_id: esId,
    responsable_id: esId,
    estado: (v) => ESTADOS_PROCESO.some(([e]) => e === v),
    semaforo: (v) => SEMAFOROS.some(([s]) => s === v),
};

/**
 * Los filtros de la URL (`?vista=contratacion&estado=publicado…`). Un valor que la API rechazaría
 * (un id que no es número, un estado que no existe) se descarta: así una URL vieja o mal escrita
 * no deja el tablero en error.
 */
export const filtrosDeConsulta = (consulta) => Object.fromEntries(CLAVES_FILTRO.map((clave) => {
    const valor = String(consulta?.get?.(clave) ?? '').trim();
    return [clave, VALIDOS[clave](valor) ? valor : ''];
}));

/** Los parámetros de GET /tablero/contratacion: solo los filtros con valor. */
export const parametrosApi = (filtros) => Object.fromEntries(CLAVES_FILTRO.filter((c) => filtros?.[c]).map((c) => [c, filtros[c]]));

export const hayFiltros = (filtros) => Object.keys(parametrosApi(filtros)).length > 0;

/** La consulta de la URL con estos filtros: conserva lo demás (la vista) y quita los vacíos. */
export const consultaConFiltros = (previos, filtros) => {
    const siguientes = new URLSearchParams(previos);
    CLAVES_FILTRO.forEach((clave) => {
        if (filtros?.[clave]) siguientes.set(clave, filtros[clave]);
        else siguientes.delete(clave);
    });
    return siguientes;
};

/** El filtro de una tarjeta: elegirla filtra por su valor; elegir la activa lo quita. */
export const alternarFiltro = (filtros, clave, valor) => ({
    ...filtros,
    [clave]: !valor || filtros[clave] === valor ? '' : valor,
});

const OPCIONES_DE = {
    modalidad_id: (o) => (o?.modalidades ?? []).map((m) => String(m.id)),
    plataforma: (o) => o?.plataformas ?? [],
    fuente_id: (o) => (o?.fuentes ?? []).map((f) => String(f.id)),
    responsable_id: (o) => (o?.responsables ?? []).map((r) => String(r.id)),
};

/**
 * Los filtros cuyo valor ya no tiene ningún proceso (las opciones del servidor no dependen de los
 * filtros): hay que quitarlos para no dejar un filtro oculto que vacía la tabla. Sin opciones
 * (aún no cargan), ninguno.
 */
export const filtrosFueraDeOpciones = (filtros, opciones) => {
    if (!opciones) return [];
    return Object.keys(OPCIONES_DE).filter((clave) => filtros?.[clave] && !OPCIONES_DE[clave](opciones).includes(filtros[clave]));
};

const deCatalogo = (items) => (items ?? []).map((i) => ({ valor: String(i.id), etiqueta: i.nombre }));

/** Las opciones de cada select de filtro: las que da el servidor y, para estado y semáforo, todas. */
export const opcionesFiltros = (opciones) => ({
    modalidad_id: deCatalogo(opciones?.modalidades),
    plataforma: (opciones?.plataformas ?? []).map((p) => ({ valor: p, etiqueta: etiquetaPlataforma(p) })),
    fuente_id: deCatalogo(opciones?.fuentes),
    responsable_id: deCatalogo(opciones?.responsables),
    estado: ESTADOS_PROCESO.map(([valor, etiqueta]) => ({ valor, etiqueta })),
    semaforo: SEMAFOROS.map(([valor, etiqueta]) => ({ valor, etiqueta })),
});

// ---------- Resumen ----------

const cuenta = (conteos, clave) => Number(conteos?.[clave]) || 0;

/** Las cinco tarjetas del semáforo, con lo que cuenta el servidor (ya filtrado). */
export const tarjetasSemaforo = (resumen, activo) => SEMAFOROS.map(([valor, etiqueta, tono]) => ({
    valor,
    etiqueta,
    tono,
    cantidad: cuenta(resumen?.por_semaforo, valor),
    activa: activo === valor,
}));

/** Las tarjetas por estado del proceso: las que tienen procesos y, aunque quede en cero, la filtrada. */
export const tarjetasEstado = (resumen, activo) => ESTADOS_PROCESO
    .map(([valor, etiqueta]) => ({ valor, etiqueta, tono: tonoEstado(valor), cantidad: cuenta(resumen?.por_estado, valor), activa: activo === valor }))
    .filter((t) => t.cantidad > 0 || t.activa);

/**
 * Cuántos procesos tiene cada etapa actual (la primera sin aprobar), en el orden del catálogo, y
 * qué parte del total son (para el ancho de la barra: es una proporción de procesos, no un avance).
 * `sinEtapa`: los que no tienen etapa actual (sin expediente, cerrado o con todo aprobado).
 */
export const etapasActuales = (resumen) => {
    const total = Number(resumen?.total) || 0;
    const etapas = (resumen?.por_etapa ?? []).map((e) => ({
        id: e.id,
        nombre: e.nombre,
        orden: e.orden,
        procesos: Number(e.procesos) || 0,
        porcentaje: total ? Math.round(((Number(e.procesos) || 0) / total) * 100) : 0,
    }));
    const enEtapa = etapas.reduce((suma, e) => suma + e.procesos, 0);
    return { etapas, sinEtapa: Math.max(0, total - enEtapa) };
};

// ---------- Tabla ----------

// La etapa actual sin revisión que anunciar: "pendiente" es lo normal de la etapa en curso.
const revisionVisible = (estado) => (estado && estado !== 'pendiente' ? etiquetaRevision(estado) : null);

const textoSinEtapa = (proceso) => {
    if (!proceso.expediente) return 'Sin expediente';
    if (proceso.expediente.estado === 'cerrado') return 'Expediente cerrado';
    // Sin requisitos que cuenten no hay etapas que aprobar: "Todas aprobadas" sería engañoso.
    if (!(Number(proceso.expediente.progreso?.total) > 0)) return 'Sin requisitos';
    return 'Todas aprobadas';
};

const etapaDe = (proceso) => (proceso.etapa_actual
    ? { texto: proceso.etapa_actual.nombre, revision: revisionVisible(proceso.etapa_actual.estado_revision) }
    : { texto: textoSinEtapa(proceso), revision: null });

const textoODefecto = (valor, siVacio) => String(valor ?? '').trim() || siVacio;

/** Un proceso de GET /tablero/contratacion tal como lo muestra la tabla. */
export const filaProceso = (proceso) => ({
    id: proceso.id,
    numero: numeroProceso(proceso),
    objeto: textoODefecto(proceso.objeto, 'Sin objeto'),
    proyecto: proceso.proyecto
        ? { id: proceso.proyecto.id, nombre: textoODefecto(proceso.proyecto.nombre, `Proyecto ${proceso.proyecto.id}`), ruta: rutaFichaProyecto(proceso.proyecto.id) }
        : null,
    modalidad: proceso.modalidad?.nombre ?? '—',
    monto: textoValor(proceso.monto),
    estado: { texto: etiquetaEstadoProceso(proceso.estado), tono: tonoEstado(proceso.estado) },
    etapa: etapaDe(proceso),
    avance: proceso.expediente ? Number(proceso.expediente.progreso?.porcentaje) || 0 : null,
    cierre: textoFecha(proceso.fecha_cierre),
    semaforo: etiquetaSemaforo(proceso.semaforo),
    avisos: proceso.avisos ?? null,
    rutaChecklist: rutaExpedienteContratacion(proceso.id),
});

export const textoListaVacia = (conFiltros) => (conFiltros
    ? 'Ningún proceso coincide con los filtros elegidos.'
    : 'Aún no hay procesos de contratación registrados.');
```

En `resources/css/ExpedienteContratacion.css`, reemplazar (1 de 1):

```css
    background: var(--gp-ambar-suave);
    border-radius: var(--exv-radio);
}
```

por:

```css
    background: var(--gp-ambar-suave);
    border-radius: var(--exv-radio);
}

/* Datos del proceso y sus contratos (ProcesoDetalle), con o sin expediente. */
.exc-detalle { display: grid; gap: 1rem; margin: 0 0 1rem; }
.exc-detalle > .exv-aviso { margin: 0; }
.exc-proceso { margin: 0 0 1rem; }
.exc-bloque {
    min-width: 0;
    padding: .9rem 1rem;
    background: var(--exv-tarjeta);
    border: 1px solid var(--exv-linea-suave);
    border-radius: var(--exv-radio);
}
.exc-bloque-cabecera { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem 1rem; margin-bottom: .4rem; }
.exc-bloque-cabecera h3 { margin: 0; font-size: 1rem; }
.exc-cuenta { font-size: .82rem; font-weight: 400; color: var(--exv-tinta-tenue); }
.exc-datos { margin-top: .2rem; }
.exc-observaciones { margin-top: .8rem; font-size: .86rem; }
.exc-observaciones b { display: block; font-family: var(--gp-fuente-datos); font-size: .66rem; font-weight: 400; letter-spacing: .08em; text-transform: uppercase; color: var(--gp-texto-3); }
.exc-observaciones p { margin: .1rem 0 0; white-space: pre-line; overflow-wrap: anywhere; }
.exc-detalle .ct-cambio-estado, .exc-detalle .pl-formulario { margin-top: .8rem; }

.exc-contratos { display: grid; gap: 0; margin: .3rem 0 0; padding: 0; list-style: none; }
.exc-contratos li {
    display: grid;
    grid-template-columns: minmax(6rem, auto) minmax(0, 1fr) auto auto;
    align-items: center;
    gap: .4rem 1rem;
    padding: .5rem 0;
    font-size: .86rem;
    border-bottom: 1px solid var(--exv-linea-suave);
}
.exc-contratos li:last-child { border-bottom: 0; }
.exc-contratos li.anulado .exc-contrato-objeto, .exc-contratos li.anulado .exc-contrato-valor { color: var(--gp-texto-3); text-decoration: line-through; }
.exc-contrato-numero { font-weight: 650; }
.exc-contrato-objeto { min-width: 0; overflow-wrap: anywhere; }
.exc-contrato-objeto small { display: block; font-size: .76rem; color: var(--exv-tinta-tenue); }
.exc-contrato-valor { font-family: var(--gp-fuente-datos); white-space: nowrap; text-align: right; }

@media (max-width: 720px) {
    .exc-contratos li { grid-template-columns: minmax(0, 1fr) auto auto; }
    .exc-contrato-objeto { grid-column: 1 / -1; grid-row: 2; }
    .exc-bloque-cabecera .pl-tarjeta-acciones, .exc-bloque-cabecera > .gp-boton { width: 100%; }
    .exc-bloque-cabecera .pl-tarjeta-acciones .gp-boton { flex: 1; justify-content: center; }
}
```

En `resources/css/Tablero.css`, reemplazar (1 de 1):

```css
    .tb-fase.compacto .tb-mapa { min-height: 50vw; }
    .sala-vista > * { height: auto; }
}
```

por:

```css
    .tb-fase.compacto .tb-mapa { min-height: 50vw; }
    .sala-vista > * { height: auto; }
}

/* ---------- Vista Contratación (ContratacionPanel): reutiliza las piezas de ExpedientesSgrPanel.css ---------- */
/* `.exs-resumen.ctp-resumen`: con más especificidad que `.exs-resumen` (5 columnas), que puede ir después en el bundle. */
.exs-resumen.ctp-resumen { grid-template-columns: repeat(6, minmax(0, 1fr)); }
.ctp-punto { display: inline-block; flex-shrink: 0; width: .55rem; height: .55rem; margin-right: .3rem; vertical-align: .05em; background: var(--ctp-color, var(--gp-texto-3)); border-radius: 50%; }
.ctp-semaforo { display: inline-flex; align-items: center; white-space: nowrap; }
.ctp-tarjeta-vencida, .ctp-semaforo-vencida { --ctp-color: var(--gp-coral); }
.ctp-tarjeta-proxima, .ctp-semaforo-proxima { --ctp-color: var(--gp-ambar); }
.ctp-tarjeta-al_dia, .ctp-semaforo-al_dia { --ctp-color: var(--gp-verde-texto); }
.ctp-tarjeta-sin_expediente, .ctp-semaforo-sin_expediente { --ctp-color: transparent; }
.ctp-tarjeta-sin_expediente .ctp-punto, .ctp-semaforo-sin_expediente .ctp-punto { box-shadow: inset 0 0 0 1.5px var(--gp-texto-3); }
.ctp-tarjeta-terminado, .ctp-semaforo-terminado { --ctp-color: var(--gp-texto-2); }

.ctp-fila { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 1rem; }
.ctp-subtitulo { margin-bottom: .6rem; font-size: 1.02rem; }
.ctp-estados { display: flex; flex-wrap: wrap; gap: .5rem; }
.ctp-estado {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .3rem .55rem;
    font-size: .84rem;
    background: var(--gp-fondo-2);
    border: 1px solid var(--gp-linea);
    border-radius: var(--gp-radio);
    transition: background .12s, border-color .12s;
}
.ctp-estado:hover { background: var(--gp-superficie-2); }
.ctp-estado.activa { background: var(--gp-azul-suave); border-color: var(--gp-azul-texto); }
.ctp-estado strong { font-family: var(--gp-fuente-datos); }
.ctp-etapas { display: grid; gap: .5rem; margin: 0; padding: 0; list-style: none; }
.ctp-etapas li { display: grid; grid-template-columns: minmax(0, 14rem) minmax(3rem, 1fr) auto; align-items: center; gap: .75rem; font-size: .85rem; }
.ctp-etapa-nombre { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.ctp-etapas strong { font-family: var(--gp-fuente-datos); font-size: .8rem; white-space: nowrap; }
.ctp-sin-etapa { margin-top: .6rem; }

.ctp-objeto { display: -webkit-box; max-width: 22rem; overflow: hidden; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.3; }
.ctp-tabla td { vertical-align: top; }
.ctp-tabla .ct-estado { white-space: nowrap; }
.ctp-col-avisos .ct-avisos { min-width: 8rem; }

@media (max-width: 1000px) {
    .exs-resumen.ctp-resumen { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .ctp-fila { grid-template-columns: minmax(0, 1fr); }
}

@media (max-width: 720px) {
    .exs-resumen.ctp-resumen { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ctp-etapas li { grid-template-columns: minmax(0, 1fr) auto; }
    .ctp-etapas .tb-pista { grid-column: 1 / -1; grid-row: 2; }
    .ctp-objeto { max-width: 60%; text-align: right; }
    .ctp-col-avisos .ct-avisos { justify-content: flex-end; min-width: 0; }
    .ctp-tabla td { vertical-align: middle; }
}
```

En `resources/js/components/Dashboard.jsx`, reemplazar (1 de 3):

```jsx
import { Link, useSearchParams } from 'react-router-dom';
import axios from 'axios';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCalendarDays, faChartColumn, faClipboardCheck, faFileSignature, faLocationDot, faTv } from '@fortawesome/free-solid-svg-icons';
import '../../css/Tablero.css';
import ContratosPanel from './ContratosPanel';
import DistribucionMunicipios from './DistribucionMunicipios';
import Estadisticas from './Estadisticas';
```

por:

```jsx
import { Link, useSearchParams } from 'react-router-dom';
import axios from 'axios';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCalendarDays, faChartColumn, faClipboardCheck, faFileSignature, faGavel, faLocationDot, faTv } from '@fortawesome/free-solid-svg-icons';
import '../../css/Tablero.css';
import ContratacionPanel from './ContratacionPanel';
import ContratosPanel from './ContratosPanel';
import DistribucionMunicipios from './DistribucionMunicipios';
import Estadisticas from './Estadisticas';
```

En `resources/js/components/Dashboard.jsx`, reemplazar (2 de 3):

```jsx
    { id: VISTA.ESTADISTICAS, titulo: 'Estadísticas', icono: faChartColumn },
    { id: VISTA.CONTRATOS, titulo: 'Avance de contratos', icono: faFileSignature },
    { id: VISTA.EXPEDIENTES, titulo: 'Expedientes', icono: faClipboardCheck },
];

const hoyEnTexto = () => new Date().toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
```

por:

```jsx
    { id: VISTA.ESTADISTICAS, titulo: 'Estadísticas', icono: faChartColumn },
    { id: VISTA.CONTRATOS, titulo: 'Avance de contratos', icono: faFileSignature },
    { id: VISTA.EXPEDIENTES, titulo: 'Expedientes', icono: faClipboardCheck },
    // Solo en el tablero normal: la sala no la recorre.
    { id: VISTA.CONTRATACION, titulo: 'Contratación', icono: faGavel },
];

const hoyEnTexto = () => new Date().toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
```

En `resources/js/components/Dashboard.jsx`, reemplazar (3 de 3):

```jsx
    const renderVista = (vista, compacto = false) => {
        if (vista === VISTA.ESTADISTICAS) return <Estadisticas proyectos={proyectos} />;
        if (vista === VISTA.CONTRATOS) return <ContratosPanel proyectos={proyectos} compacto={compacto} />;
        if (vista === VISTA.EXPEDIENTES) {
            return (
                <ExpedientesSgrPanel
```

por:

```jsx
    const renderVista = (vista, compacto = false) => {
        if (vista === VISTA.ESTADISTICAS) return <Estadisticas proyectos={proyectos} />;
        if (vista === VISTA.CONTRATOS) return <ContratosPanel proyectos={proyectos} compacto={compacto} />;
        if (vista === VISTA.CONTRATACION) return <ContratacionPanel />;
        if (vista === VISTA.EXPEDIENTES) {
            return (
                <ExpedientesSgrPanel
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (1 de 5):

```jsx
import axios from '../axios';
import { ChecklistPorEtapa } from './ExpedienteChecklist';
import ExpedienteHistorial from './ExpedienteHistorial';
import useOcupado from './useOcupado';
import {
    contarOpcionales, motivoNoEliminarPropio, plantillasConVersion, requiereAprobacionNoAplica, textoOpcionales,
```

por:

```jsx
import axios from '../axios';
import { ChecklistPorEtapa } from './ExpedienteChecklist';
import ExpedienteHistorial from './ExpedienteHistorial';
import ProcesoDetalle from './ProcesoDetalle';
import useOcupado from './useOcupado';
import {
    contarOpcionales, motivoNoEliminarPropio, plantillasConVersion, requiereAprobacionNoAplica, textoOpcionales,
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (2 de 5):

```jsx
    const [anuncio, setAnuncio] = useState('');
    const nuevaRevision = useCallback(() => setRevision((r) => r + 1), []);
    const [ocupado, ejecutar] = useOcupado();

    const puedeEditar = ROLES_EDICION.includes(user?.rol);
    const editable = puedeEditar && expediente?.estado === 'borrador';
```

por:

```jsx
    const [anuncio, setAnuncio] = useState('');
    const nuevaRevision = useCallback(() => setRevision((r) => r + 1), []);
    const [ocupado, ejecutar] = useOcupado();
    // GET /procesos/{p}/detalle: los datos del proceso y sus contratos, tenga o no expediente. Se
    // pide aparte: si falla, el checklist sigue funcionando.
    const [detalle, setDetalle] = useState(null);
    const [errorDetalle, setErrorDetalle] = useState(null);
    const turnoDetalle = useRef(0);

    const puedeEditar = ROLES_EDICION.includes(user?.rol);
    const editable = puedeEditar && expediente?.estado === 'borrador';
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (3 de 5):

```jsx

    useEffect(() => { cargar(); }, [cargar]);

    // Para el selector de etapa de los propios. Sin la lista, solo se ofrece la que el propio ya tiene.
    useEffect(() => {
        let vigente = true;
```

por:

```jsx

    useEffect(() => { cargar(); }, [cargar]);

    // Solo vale la última respuesta; desmontarse la descarta.
    const cargarDetalle = useCallback(async () => {
        const numero = ++turnoDetalle.current;
        setErrorDetalle(null);
        try {
            const { data } = await axios.get(`/procesos/${procesoId}/detalle`);
            if (numero === turnoDetalle.current) setDetalle(data);
        } catch (e) {
            if (numero === turnoDetalle.current) setErrorDetalle(errorDeProceso(e));
        }
    }, [procesoId]);

    useEffect(() => {
        cargarDetalle();
        return () => { turnoDetalle.current += 1; };
    }, [cargarDetalle]);

    // Tras editar el proceso, cambiar su estado o registrarle un contrato: cambian sus datos, su
    // cabecera y sus avisos, así que se recargan el detalle y el expediente.
    const recargarProceso = () => Promise.all([cargarDetalle(), cargar()]);

    // Para el selector de etapa de los propios. Sin la lista, solo se ofrece la que el propio ya tiene.
    useEffect(() => {
        let vigente = true;
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (4 de 5):

```jsx

            {error && <div className="exv-aviso" role="alert">{error.mensaje} {reintentar}</div>}

            {!expediente ? (
                <SinExpediente
                    plantillas={disponibles.plantillas}
```

por:

```jsx

            {error && <div className="exv-aviso" role="alert">{error.mensaje} {reintentar}</div>}

            <ProcesoDetalle
                detalle={detalle}
                error={errorDetalle}
                user={user}
                onReintentar={cargarDetalle}
                onCambio={recargarProceso}
            />

            {!expediente ? (
                <SinExpediente
                    plantillas={disponibles.plantillas}
```

En `resources/js/components/ExpedienteContratacion.jsx`, reemplazar (5 de 5):

```jsx
};

/**
 * El expediente de contratación de un proceso (`/contratacion/:procesoId`): su checklist por etapa,
 * el avance total y por etapa, y el cierre, que solo avisa de lo pendiente. Sin expediente, ofrece
 * crearlo con las plantillas que le corresponden. Un id que no es válido no llega a la API.
 */
const ExpedienteContratacion = ({ user }) => {
```

por:

```jsx
};

/**
 * El expediente de contratación de un proceso (`/contratacion/:procesoId`): los datos del proceso y
 * sus contratos (con editar, cambiar el estado y registrar contrato), su checklist por etapa, el
 * avance total y por etapa, y el cierre, que solo avisa de lo pendiente. Sin expediente, ofrece
 * crearlo con las plantillas que le corresponden. Un id que no es válido no llega a la API.
 */
const ExpedienteContratacion = ({ user }) => {
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (1 de 6):

```jsx
import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowRightArrowLeft, faArrowUpRightFromSquare, faClipboardCheck, faFileSignature, faGavel, faPen, faPlus, faTrash, faXmark } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { Link } from 'react-router-dom';
import axios from '../axios';
```

por:

```jsx
import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import Swal from 'sweetalert2';
import { faArrowRightArrowLeft, faClipboardCheck, faFileSignature, faGavel, faPen, faPlus, faTrash, faXmark } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { Link } from 'react-router-dom';
import axios from '../axios';
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (2 de 6):

```jsx
import { rutaExpedienteContratacion } from '../utils/navegacion';
import { mensajeApi } from '../utils/plantillasChecklist';
import {
    avanceExpediente, CAMPOS_PROCESO, ESTADOS_PROCESO, MAXIMO_OBSERVACION, MAXIMO_OBSERVACIONES_PROCESO, PLATAFORMAS, avisoCambioEstado, datosEstado, datosProceso,
    erroresPorCampo, esEdicionDesactualizada, etiquetaEstadoProceso, etiquetaPlataforma, formularioProceso,
    modoTrasGuardarContrato, numeroProceso, opcionesConActual, opcionesFuenteProceso, ordenarCatalogo, puedeEditarProcesos, puedeRegistrarContrato,
    textoContratos, textoFecha, textoValor, tonoEstado, validarProceso, valorEnEdicion,
} from '../utils/contratacion';
import ChipsAvisosProceso from './ChipsAvisosProceso';
import ContratoEditor from './contratos/ContratoEditor';
import useDialogoModal from './useDialogoModal';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';

// Catálogos del formulario: su ruta y cómo se guarda cada respuesta.
const comoLista = (datos) => (Array.isArray(datos) ? datos : []);
const CATALOGOS = [
    { clave: 'modalidades', ruta: '/modalidades', guardar: (d, { setModalidades }) => setModalidades(ordenarCatalogo(d)) },
    { clave: 'entidades', ruta: '/entidades', guardar: (d, { setEntidades }) => setEntidades(comoLista(d)) },
    { clave: 'fuentes', ruta: '/fuentesFinanciacion', guardar: (d, { setFuentes }) => setFuentes(comoLista(d)) },
    { clave: 'responsables', ruta: '/responsables', guardar: (d, { setResponsables }) => setResponsables(comoLista(d)) },
];

// Un contrato nuevo no tiene pestañas que recargar: el editor lo cierra quien lo abrió al guardar.
const sinRecargar = async () => null;

/**
 * Diálogo modal accesible: Escape llama a `onEscape`, un clic en el fondo a `onFondo` y, al
 * cerrar, el foco vuelve a donde estaba (el botón "Procesos" de la fila).
```

por:

```jsx
import { rutaExpedienteContratacion } from '../utils/navegacion';
import { mensajeApi } from '../utils/plantillasChecklist';
import {
    avanceExpediente, etiquetaEstadoProceso, etiquetaPlataforma, modoTrasGuardarContrato, puedeEditarProcesos, puedeRegistrarContrato,
    textoContratos, textoFecha, textoValor, tonoEstado,
} from '../utils/contratacion';
import ChipsAvisosProceso from './ChipsAvisosProceso';
import {
    DialogoContrato, FormularioEstado, FormularioProceso, NumeroProceso, cambiarEstadoProceso, guardarProceso, useCatalogosProceso,
} from './FormulariosProceso';
import useDialogoModal from './useDialogoModal';
import useOcupado from './useOcupado';
import '../../css/Plantillas.css';
import '../../css/Contratacion.css';

/**
 * Diálogo modal accesible: Escape llama a `onEscape`, un clic en el fondo a `onFondo` y, al
 * cerrar, el foco vuelve a donde estaba (el botón "Procesos" de la fila).
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (3 de 6):

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
        {children}
        {error && <small id={idError} className="ct-error-campo">{error}</small>}
    </label>
);

/**
 * Formulario de un proceso. Valida antes de enviar con las reglas del servidor; `onGuardar`
 * devuelve los errores por campo del servidor, o null. Escape cancela sin cerrar el diálogo.
 */
const FormularioProceso = ({ proceso, fuenteIds, modalidades, entidades, fuentes, responsables, guardando, onGuardar, onCancelar }) => {
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

    const fuentesProceso = opcionesFuenteProceso(fuentes, proceso?.fuente, fuenteIds);

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
            {campo('fuente_id', 'Fuente de financiación (opcional)', (
                <select {...control('fuente_id')}>
                    <option value="">Sin fuente</option>
                    {fuentesProceso.delProyecto.length > 0 && (
                        <optgroup label="Fuentes del proyecto">
                            {fuentesProceso.delProyecto.map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                        </optgroup>
                    )}
                    {fuentesProceso.delProyecto.length > 0 && fuentesProceso.otras.length > 0 ? (
                        <optgroup label="Otras fuentes">
                            {fuentesProceso.otras.map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                        </optgroup>
                    ) : fuentesProceso.otras.map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                </select>
            ))}
            {campo('responsable_id', 'Responsable (opcional)', (
                <select {...control('responsable_id')}>
                    <option value="">Sin responsable</option>
                    {opcionesConActual(responsables, proceso?.responsable).map((o) => <option key={o.valor} value={o.valor}>{o.etiqueta}</option>)}
                </select>
            ))}
            {campo('fecha_apertura', 'Fecha de apertura (opcional)', <input type="date" {...control('fecha_apertura')} />)}
            {campo('fecha_cierre', 'Fecha de cierre (opcional)', <input type="date" {...control('fecha_cierre')} />)}
            {campo('observaciones', 'Observaciones (opcional)', (
                <textarea rows={3} maxLength={MAXIMO_OBSERVACIONES_PROCESO} {...control('observaciones')} />
            ), true)}
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
 * El avance del expediente del proceso (su propio conteo de requisitos), su estado si está
 * cerrado, el aviso de plantilla y el enlace al checklist. Al navegar, la página que abrió el
```

por:

```jsx
    );
};

/**
 * El avance del expediente del proceso (su propio conteo de requisitos), su estado si está
 * cerrado, el aviso de plantilla y el enlace al checklist. Al navegar, la página que abrió el
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (4 de 6):

```jsx
const ProcesosProyecto = ({ proyecto, user, onCerrar, onCambio }) => {
    const [procesos, setProcesos] = useState(null);
    const [error, setError] = useState(null);
    const [modalidades, setModalidades] = useState(null);
    const [entidades, setEntidades] = useState(null);
    const [fuentes, setFuentes] = useState(null);
    const [responsables, setResponsables] = useState(null);
    const [errorCatalogos, setErrorCatalogos] = useState(null);
    // null; { tipo: 'proceso', proceso: null | proceso }, { tipo: 'estado', proceso } o { tipo: 'contrato', proceso }.
    const [modo, setModo] = useState(null);
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    const retorno = useRef(null); // data-foco del control que recupera el foco al volver a la lista
    const turno = useRef(0);
    const turnoCatalogos = useRef(0);
    const edita = puedeEditarProcesos(user);

    // Solo vale la última respuesta, y solo si es de este proyecto: las tardías se descartan.
```

por:

```jsx
const ProcesosProyecto = ({ proyecto, user, onCerrar, onCambio }) => {
    const [procesos, setProcesos] = useState(null);
    const [error, setError] = useState(null);
    const {
        modalidades, entidades, fuentes, responsables, errorCatalogos, cargarCatalogos, pendientes: catalogosPendientes,
    } = useCatalogosProceso();
    // null; { tipo: 'proceso', proceso: null | proceso }, { tipo: 'estado', proceso } o { tipo: 'contrato', proceso }.
    const [modo, setModo] = useState(null);
    const [ocupado, ejecutar] = useOcupado();
    const contenedor = useRef(null);
    const retorno = useRef(null); // data-foco del control que recupera el foco al volver a la lista
    const turno = useRef(0);
    const edita = puedeEditarProcesos(user);

    // Solo vale la última respuesta, y solo si es de este proyecto: las tardías se descartan.
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (5 de 6):

```jsx
        return () => { turno.current += 1; };
    }, [cargar]);

    // Los catálogos se cargan al abrir y, con "Reintentar", se piden otra vez los que fallaron. Sin
    // modalidades no se crea ni edita; sin los demás el formulario conserva lo que el proceso tiene.
    const cargarCatalogos = useCallback(async (pendientes = CATALOGOS.map((c) => c.clave)) => {
        const numero = ++turnoCatalogos.current;
        setErrorCatalogos(null);
        const pedidos = CATALOGOS.filter((c) => pendientes.includes(c.clave));
        const respuestas = await Promise.allSettled(pedidos.map((c) => axios.get(c.ruta)));
        if (numero !== turnoCatalogos.current) return;
        pedidos.forEach((c, i) => {
            if (respuestas[i].status === 'fulfilled') c.guardar(respuestas[i].value.data, { setModalidades, setEntidades, setFuentes, setResponsables });
        });
        const fallo = respuestas.find((r) => r.status === 'rejected');
        if (fallo) setErrorCatalogos(`No se pudieron cargar las modalidades, entidades, fuentes o responsables: ${mensajeApi(fallo.reason)}`);
    }, []);

    useEffect(() => {
        cargarCatalogos();
        return () => { turnoCatalogos.current += 1; };
    }, [cargarCatalogos]);

    // El botón desaparece mientras se reintenta: el foco pasa al diálogo para no perderse.
    const reintentarCatalogos = () => {
        contenedor.current?.closest('[role="dialog"]')?.focus();
        const cargados = { modalidades, entidades, fuentes, responsables };
        cargarCatalogos(CATALOGOS.map((c) => c.clave).filter((clave) => cargados[clave] === null));
    };

    // Al volver a la lista, el foco regresa al botón que abrió el formulario (o al diálogo).
```

por:

```jsx
        return () => { turno.current += 1; };
    }, [cargar]);

    // Los catálogos se cargan al abrir (useCatalogosProceso) y, con "Reintentar", se piden otra vez
    // los que fallaron. El botón desaparece mientras se reintenta: el foco pasa al diálogo para no perderse.
    const reintentarCatalogos = () => {
        contenedor.current?.closest('[role="dialog"]')?.focus();
        cargarCatalogos(catalogosPendientes);
    };

    // Al volver a la lista, el foco regresa al botón que abrió el formulario (o al diálogo).
```

En `resources/js/components/ProcesosProyecto.jsx`, reemplazar (6 de 6):

```jsx
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

    // El contrato se guardó (el editor ya mostró sus avisos): se cierra (si sigue abierto) y se
    // recarga la lista, que trae la cuenta de contratos y el estado, que pudo pasar a celebrado con
```

por:

```jsx
        Swal.fire({ icon: 'warning', titleText: 'No se guardó tu cambio', text: mensajeApi(e) }),
    ]);

    const guardar = (formulario) => ejecutar(() => guardarProceso({
        proceso: modo?.proceso ?? null,
        proyectoId: proyecto.id,
        formulario,
        onGuardado: async () => {
            setModo(null);
            await cargar();
        },
        onDesactualizado: avisarDesactualizado,
    }));

    const cambiarEstado = (estado, observacion) => ejecutar(() => cambiarEstadoProceso({
        proceso: modo.proceso,
        estado,
        observacion,
        onGuardado: async () => {
            setModo(null);
            await cargar();
        },
        onDesactualizado: avisarDesactualizado,
    }));

    // El contrato se guardó (el editor ya mostró sus avisos): se cierra (si sigue abierto) y se
    // recarga la lista, que trae la cuenta de contratos y el estado, que pudo pasar a celebrado con
```

En `resources/js/utils/expedienteContratacion.js`, reemplazar (1 de 2):

```js
// (agruparChecklist) no cambia. Funciones puras, probadas en expedienteContratacion.test.js.

import { etiquetaAlerta, etiquetaRevision } from './avisosContratacion';
import { esIdContrato } from './contratosModulo';
import { opcionesEtapa } from './contratacion';
import { datosPropio, formularioPropio } from './expedienteChecklist';
import { compararEtapas, escaparHtml, mensajeApi, nombreEtapa, SIN_ETAPA } from './plantillasChecklist';
```

por:

```js
// (agruparChecklist) no cambia. Funciones puras, probadas en expedienteContratacion.test.js.

import { etiquetaAlerta, etiquetaRevision } from './avisosContratacion';
import { esIdContrato, filaContrato, rutaContrato } from './contratosModulo';
import { esEnlaceWeb, opcionesEtapa, PLATAFORMAS, puedeEditarProcesos } from './contratacion';
import { datosPropio, formularioPropio } from './expedienteChecklist';
import { compararEtapas, escaparHtml, mensajeApi, nombreEtapa, SIN_ETAPA } from './plantillasChecklist';
```

En `resources/js/utils/expedienteContratacion.js`, reemplazar (2 de 2):

```js
        detalle: `${incompatibles.map((p) => `${p.nombre} (${RAZONES[p.razon][2]})`).join(', ')}. ${conserva}`,
    };
};
```

por:

```js
        detalle: `${incompatibles.map((p) => `${p.nombre} (${RAZONES[p.razon][2]})`).join(', ')}. ${conserva}`,
    };
};

// ---------- Detalle del proceso (GET /procesos/{id}/detalle) ----------

/** Un contrato del proceso en el bloque "Contratos": el valor es el vigente o, sin él, el inicial. */
export const filaContratoProceso = (contrato) => ({
    ...filaContrato({ ...contrato, valor: contrato.valor ?? contrato.valor_inicial ?? null }),
    anulado: contrato.estado === 'anulado',
    ruta: rutaContrato(contrato.id),
});

export const textoContratosProceso = (contratos) => {
    const n = contratos?.length ?? 0;
    if (n === 0) return 'Este proceso aún no tiene contratos.';
    return n === 1 ? '1 contrato' : `${n} contratos`;
};

/**
 * "Registrar contrato" en la página del proceso: quien edita procesos, si el estado los admite
 * (`admite_contratos`) y si el proceso tiene proyecto, porque el editor de contratos ofrece los
 * procesos del proyecto elegido.
 */
export const puedeRegistrarDesdeDetalle = (usuario, detalle) => (
    puedeEditarProcesos(usuario) && Boolean(detalle?.admite_contratos) && Boolean(detalle?.proyecto)
);

/**
 * El enlace al proceso en su plataforma (SECOP II, SECOP I o la Tienda Virtual), solo si es un
 * enlace web completo; si no, null.
 */
export const enlaceProceso = (proceso) => {
    if (!esEnlaceWeb(proceso?.url_proceso)) return null;
    const plataforma = proceso.plataforma !== 'ninguna' ? PLATAFORMAS.find(([v]) => v === proceso.plataforma)?.[1] : null;
    return { enlace: String(proceso.url_proceso).trim(), texto: plataforma ? `Ver el proceso en ${plataforma}` : 'Ver el proceso' };
};

/**
 * Abre un formulario de la página del proceso ('editar', 'estado' o 'contrato') congelando el
 * proceso de ese momento, como la lista de procesos del proyecto. Tras un 409 el detalle se
 * recarga, pero lo que se guarda sigue llevando el `actualizado` de cuando se abrió: un segundo
 * intento vuelve a dar 409 en lugar de pisar el cambio de otra persona. Al reabrir trae lo último.
 */
export const abrirFormularioDetalle = (tipo, proceso) => ({ tipo, proceso });

/** El proceso congelado del formulario `tipo` si es el abierto; si no, null. */
export const procesoDelFormulario = (modo, tipo) => (modo?.tipo === tipo ? modo.proceso ?? null : null);

/** Las fuentes del proyecto del detalle (`proyecto.fuente_ids`), con las que el formulario agrupa "Fuentes del proyecto". */
export const fuentesDelDetalle = (detalle) => detalle?.proyecto?.fuente_ids ?? [];
```

En `resources/js/utils/tablero.js`, reemplazar (1 de 1):

```js
    ESTADISTICAS: 'estadisticas',
    CONTRATOS: 'contratos',
    EXPEDIENTES: 'expedientes',
};

const VISTAS_ANALITICAS = Object.values(VISTA);
```

por:

```js
    ESTADISTICAS: 'estadisticas',
    CONTRATOS: 'contratos',
    EXPEDIENTES: 'expedientes',
    // Solo del tablero normal: la sala no la recorre (secuenciaSala).
    CONTRATACION: 'contratacion',
};

const VISTAS_ANALITICAS = Object.values(VISTA);
```

- [ ] **Paso 4: Comprobar que pasan**

Run: `npx vitest run resources/js/utils/contratacionPanel.test.js resources/js/utils/expedienteContratacion.test.js resources/js/utils/tablero.test.js`
Expected: pasan todas.

Run: `php artisan test`
Expected: 1313 passed.

Run: `npm test`
Expected: 854 passed.

- [ ] **Paso 5: Commit**

```bash
git add resources/js/components/ContratacionPanel.jsx resources/js/components/FormulariosProceso.jsx resources/js/components/ProcesoDetalle.jsx resources/js/utils/contratacionPanel.js resources/js/utils/contratacionPanel.test.js resources/css/ExpedienteContratacion.css resources/css/Tablero.css resources/js/components/Dashboard.jsx resources/js/components/ExpedienteContratacion.jsx resources/js/components/ProcesosProyecto.jsx resources/js/utils/expedienteContratacion.js resources/js/utils/expedienteContratacion.test.js resources/js/utils/tablero.js resources/js/utils/tablero.test.js
git commit -m "feat: contracting dashboard view and process detail page"
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
git commit -m "build: production bundle for procurement phase 6"
```

Los archivos que no cambiaron se omiten. Los estilos nuevos de `Tablero.css` y `ExpedienteContratacion.css` se importan desde los componentes y van dentro de `app.js`: `public/css/app.css` no se comitea.

---

### Tarea 6: Verificación en la copia descartable

Usa la copia y las fotos de la tarea 1. Nunca `gest_pro`. La fase no tiene migraciones: no se ejecuta `migrate`.

- [ ] **Paso 1: Tomar las fotos con el código nuevo y comparar**

Con el código de las tareas 2 a 5 ya comiteado, sobre la misma copia:

```bash
for f in 2 3; do DB_DATABASE=gestpro_verif_tmp php "$TMP_DIR/gestpro_foto_contratacion$f.php" "$TMP_DIR/c10_despues_$f.json" gestpro_verif_tmp && php "$TMP_DIR/gestpro_comparar_contratacion3.php" "$TMP_DIR/c10_antes_$f.json" "$TMP_DIR/c10_despues_$f.json"; done
```

Expected: `SIN DIFERENCIAS` dos veces. El comparador admite claves nuevas; un valor distinto o algo que falta, no.

- [ ] **Paso 2: Preparar los datos de prueba en la copia**

Compilar en desarrollo (`npx mix`) si `public/` no tiene el código de esta fase. Arrancar el servidor desde `public/`, en un puerto libre (`netstat -ano | grep 8796`):

```bash
cd public && DB_DATABASE=gestpro_verif_tmp php -S 127.0.0.1:8796 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Crear un Administrador de prueba y un usuario Consulta de prueba, solo en la copia. Nunca usar sesiones de usuarios reales de `gest_pro`:

```bash
DB_DATABASE=gestpro_verif_tmp php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'verif@gestpro.test', 'rol' => App\Enums\Rol::Administrador]); App\Models\User::factory()->create(['email' => 'consulta@gestpro.test', 'rol' => App\Enums\Rol::Consulta]);"
```

Entrar por `http://127.0.0.1:8796/GestPro/` como el Administrador de prueba, borrando `localStorage` si hay una sesión anterior. Las llamadas a la API van con el token de esa sesión. Todo lo que sigue se hace **solo en la copia**.

1. **Quitar el expediente cerrado del proceso 2**, para poder crearle uno con la plantilla de licitación. Buscar su id y borrarlo con su historial:

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "SELECT id FROM proyectos_expedientes WHERE proceso_id=2;"
```

```bash
/c/xampp/mysql/bin/mysql -u root gestpro_verif_tmp -e "DELETE FROM checklist_historial WHERE expediente_id=<id>; DELETE FROM proyectos_expedientes WHERE id=<id>;"
```

2. **Publicar** la versión borrador de "Licitación pública – SECOP II" con `POST /catalogo-requisitos/versiones/{id}/publicar`.
3. **Crear el expediente del proceso 2** con esa plantilla: `POST /procesos/2/expediente` con `{"plantillas": [id]}`, usando el id de la plantilla de licitación (no "nueva plantilla").
4. **A la etapa 1**, por API, una `fecha_programada` pasada (ayer, por ejemplo) con `PUT /expedientes/{expediente}/etapas/{etapa}`.

- [ ] **Paso 3: Probar en el navegador sobre la copia**

Comprobar:

1. **La pestaña Contratación** (`/dashboard?vista=contratacion`):
   - 6 tarjetas en **una sola fila**: Procesos 3, Vencida 1, Próxima 0, Al día 2, Sin expediente 0, Terminado 0;
   - "Por estado del proceso" con los estados de los 3 procesos;
   - "Procesos por etapa actual" con "Planeación y estudios previos · 1 proceso";
   - los filtros (modalidad, plataforma, fuente, responsable, estado y semáforo) con las opciones de los procesos;
   - la tabla "Procesos de contratación" con los 3 procesos; los 2 de "nueva plantilla" con la etapa actual "Sin requisitos" (no "Todas aprobadas").
2. **Filtrar:** elegir el semáforo "Vencida": la tabla queda con 1 fila (el proceso 2), la URL pasa a `?vista=contratacion&semaforo=vencida` y el enlace "Checklist" de la fila lleva a `/contratacion/2`. "Quitar filtros" devuelve los 3. Recargar con la URL filtrada conserva el filtro.
3. **La página `/contratacion/2`:** los bloques "Proceso" (número, monto, fuente, responsable, apertura, cierre, plataforma, observaciones) y "Contratos", encima del checklist.
4. **"Editar proceso":** poner el número `LP-001-2026` y el monto `1.500.000.000` y guardar: el bloque "Proceso" muestra los valores nuevos.
5. **"Cambiar estado"** a Adjudicado: aparece "Registrar contrato", que abre el formulario del contrato con el proceso precargado. Cancelar: no se registra nada.
6. **El 409:** abrir "Editar proceso" y, con el formulario abierto, guardar un cambio del proceso 2 por API como otra persona (`PUT /procesos/2` con su `actualizado` vigente). Luego "Guardar proceso" en el formulario: avisa "No se guardó tu cambio". Un segundo "Guardar" con el mismo formulario vuelve a dar 409, y el cambio de la otra persona se conserva (comprobarlo en el bloque "Proceso" al recargar).
7. **El usuario Consulta de prueba** (cerrar la sesión del Administrador y entrar con él): en `/contratacion/2` ve los datos del proceso y los contratos, sin "Editar proceso", "Cambiar estado" ni "Registrar contrato"; la pestaña Contratación del tablero se ve igual.
8. **La ficha `/proyectos/1`:** el diálogo "Procesos" sigue editando y guardando un proceso como antes.
9. **El modo sala** no muestra la vista Contratación.
10. **La viabilización no cambia:** `GET /expedientes` sigue con los expedientes de viabilización y `/viabilizacion/<proyecto con expediente>` se ve como antes.

La consola del navegador no muestra errores nuevos. Los 401 de una sesión vieja o del cambio de usuario, los 403 del usuario Consulta y los 409 y 422 provocados son esperados.

- [ ] **Paso 4: Limpiar**

Detener el servidor, eliminar la copia y devolver `public/` a lo comiteado si se compiló en desarrollo:

```bash
/c/xampp/mysql/bin/mysql -u root -e "DROP DATABASE gestpro_verif_tmp;"
```

- [ ] **Paso 5: Ejecutar las suites una última vez**

Run: `php artisan test`
Expected: pasan todas, 1313.

Run: `npm test`
Expected: pasan todas, 854.

---

### Tarea 7: Informar y pedir validación

- [ ] **Paso 1: Actualizar el estado de la propuesta**

En `docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md`, reemplazar:

```markdown
La fase 5 (revisión por etapa, migración 23) está validada y aplicada en `gest_pro` (2026-11-19).
```

por:

```markdown
La fase 5 (revisión por etapa, migración 23) está validada y aplicada en `gest_pro` (2026-11-19). La fase 6 (tablero de Contratación y vista detallada del proceso, sin migraciones) está implementada y verificada en copia, pendiente de validar.
```

```bash
git add docs/superpowers/specs/2026-11-18-procesos-y-contratos-propuesta.md
git commit -m "docs: mark procurement phase 6 as implemented"
```

- [ ] **Paso 2: Informar**

Informar:
- los totales de pruebas;
- las comparaciones de las fotos con el código anterior y el nuevo (`SIN DIFERENCIAS` dos veces);
- lo revisado en el navegador.

Pedir **una** decisión: si valida la fase 6. Si la valida, integrar `feature/contratacion` en `main` y subirla. La fase no tiene migraciones: no hay nada que aplicar en `gest_pro`.

---

## Criterio de la fase

La fase está lista cuando:

- la pestaña "Contratación" del tablero muestra todos los procesos por estado, por etapa actual y por semáforo, con sus avisos, su avance y el enlace al checklist, y se puede filtrar por modalidad, plataforma, fuente, responsable, estado y semáforo, con los filtros en la URL;
- ningún avance se suma ni se promedia con otro (R13): el resumen solo cuenta procesos;
- la página del proceso muestra sus datos y sus contratos, tenga o no expediente, y quien puede editar edita el proceso, cambia su estado y registra un contrato ahí mismo;
- un guardado con datos desactualizados nunca pisa el cambio de otra persona;
- el diálogo "Procesos" de la ficha, las demás vistas del tablero y el modo sala no cambian;
- la viabilización no cambia: mismas respuestas, mismas cifras, y sus pruebas y la paridad del evaluador pasan;
- ningún dato ni ninguna respuesta existente cambia con el código nuevo.
