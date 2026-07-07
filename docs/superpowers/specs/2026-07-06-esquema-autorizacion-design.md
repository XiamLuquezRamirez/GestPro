# Esquema versionado y autorización por rol — GestPro

**Fecha:** 2026-07-06
**Estado:** Implementado 2026-07-06

## Contexto

GestPro gestiona el seguimiento de proyectos de la empresa por municipio (fases Formulación → Licitación → Ejecución, contratos, avances, checklist de formulación, eventos). Una auditoría inicial detectó dos riesgos altos que deben resolverse antes de invertir en el rediseño del dashboard:

1. El esquema de ~19 tablas de negocio existe solo en la base de datos MySQL local (`gest_pro`), sin migraciones de Laravel que lo versionen. No hay forma de reconstruirlo desde git.
2. No existe autorización por rol: cualquier usuario autenticado con JWT válido puede llamar a cualquiera de los ~50 endpoints de `routes/api.php`, incluidos los administrativos, pese a existir una columna `users.rol`.

Se confirmó por inspección directa de la base (`SHOW CREATE TABLE`, `mysqldump --no-data`) que **no existe ninguna llave foránea** en el esquema actual — todas las relaciones son columnas enteras sueltas sin `CONSTRAINT`. Varios campos monetarios y de porcentaje están en `varchar`/`text` en vez de tipos numéricos.

Solo existe la base local de desarrollo (no hay entorno de producción con datos reales). De las tablas de negocio, `municipios` (1.125 filas) y `departamentos` (34 filas) son el catálogo real de Colombia (probablemente importado de DANE); el resto (`proyectos`: 7 filas, `contratos`: 4, `users`: 2, `estados`: 6, `fases`: 3, etc.) son datos de prueba.

## Alcance

Incluye:
- Migraciones de Laravel para todas las tablas de negocio, con tipos corregidos y llaves foráneas reales.
- Preservación de los datos de `municipios` y `departamentos` vía seeders; recreación vacía del resto de tablas de negocio.
- Modelos Eloquent con relaciones para las 21 entidades de negocio.
- Sistema de autorización por rol (Administrador / Gestor / Consulta) usando Gates nativos de Laravel + middleware `can:`.
- Manejo de errores 403 coherente en backend y un mensaje claro en el interceptor de axios del frontend.
- Tests de Feature para migraciones, seeders y Gates.

No incluye (fuera de alcance, fases futuras):
- Refactor de `ProyectoController.php` para usar los nuevos modelos Eloquent en vez de `DB::table`.
- Ocultar/deshabilitar en el frontend pestañas o botones según rol (decisión explícita: solo backend por ahora).
- Catálogo de estados de contrato (`contratos.estado` queda como texto libre).
- Rediseño visual del dashboard o módulo de informes (fases posteriores, ver auditoría inicial).

## Diseño

### 1. Estrategia de migración

1. Exportar los datos actuales de `municipios` y `departamentos` a seeders dedicados (`DepartamentosSeeder`, `MunicipiosSeeder`) antes de tocar el esquema.
2. Escribir migraciones `Schema::create` (con `down()` en `Schema::dropIfExists`) para las ~19 tablas de negocio, en orden de dependencia (catálogos primero, luego `proyectos`, luego tablas que dependen de `proyectos`/`contratos`).
3. Añadir un seeder de usuario semilla (`Administrador`, `admin@gestpro.local`, password `Admin123!`) para poder autenticarse tras recrear `users`.
4. Aplicar con `php artisan migrate:fresh --seed` en el entorno local.

### 2. Correcciones de tipos y relaciones

| Campo(s) | Antes | Después |
|---|---|---|
| `proyectos.presupuesto`, `contratos.valor`, `presupuesto_proyecto.valor`, `procesos_licitacion.monto`, `avance_financiero.valor_facturado/amortizacion_50/valor_presente_acta` | `varchar` | `decimal(15,2)` |
| `proyectos.progreso`, `contratos.avance_fisico`, `contratos.avance_financiero`, `avance_financiero.porcentaje_ejecutado`, `avance_fisico.valor_avance_fisico` | `varchar` | `unsignedTinyInteger` (0–100) |
| Columnas `activo` en todos los catálogos, `fases.dashboard`, `eventos_licitacion.cumplido`, `departamentos.habilitado` | `tinyint`/`char(2)` | `boolean` |
| `contratos.anticipo` | `char(2)` ('SI'/'NO') | `boolean` |
| `check_formulacion.checklist` | `longtext` | `json` (con cast en el modelo) |

Todas las columnas que hoy referencian otra tabla (`proyectos.municipio/estado/fase/sector/entidad_presenta/entidad_financia`, `contratos.proyecto`, `contratos.proceso_licitacion`, `anexos_contratos.contrato_id`, `avance_financiero.contrato_id`, `avance_fisico.contrato_id`, `presupuesto_proyecto.proyecto`, `procesos_licitacion.proyecto`, `eventos_licitacion.proyecto/proceso`, `eventos.tipo_eventos/prioridad/responsable/proyecto`, `estados.fase`, `municipios.departamento`) pasan a `foreignId()->constrained()`.

Regla de borrado: relaciones de "propiedad" (proyecto → contratos, checklist, presupuesto, procesos de licitación, eventos de licitación; contrato → anexos, avances) usan `cascadeOnDelete()`. Relaciones hacia catálogos (municipio, estado, fase, sector, entidad, tipo, prioridad, responsable) usan la restricción por defecto (impiden borrar un catálogo en uso), coherente con que el frontend ya usa `activo` para desactivar catálogos en vez de borrarlos.

`contratos.estado` se mantiene como texto libre — no existe hoy un catálogo de estados de contrato y crear uno es alcance nuevo.

### 3. Modelos Eloquent

Un modelo por tabla de negocio: `Municipio`, `Departamento`, `Proyecto`, `Contrato`, `Estado`, `Fase`, `Sector`, `Entidad`, `TipoProceso`, `Modalidad`, `TipoEvento`, `Prioridad`, `Responsable`, `PresupuestoProyecto`, `ProcesoLicitacion`, `EventoLicitacion`, `CheckFormulacion`, `AnexoContrato`, `AvanceFinanciero`, `AvanceFisico`, `Evento`. Cada uno declara `$fillable`, `casts` (decimal/boolean/json) y las relaciones `belongsTo`/`hasMany` correspondientes.

`ProyectoController.php` no se modifica en esta fase — sigue funcionando con `DB::table()` tal cual. Los modelos quedan disponibles para el refactor futuro del controlador y para el propio sistema de autorización.

**Convención de nombres de relación (descubierta durante la implementación):** cuando una columna FK es una sola palabra que coincide con el nombre natural de la relación (ej. columna `estado` y relación `estado()`), Eloquent siempre devuelve el valor crudo de la columna en vez de resolver la relación — `getAttribute()` comprueba `hasAttribute()` antes que `isRelation()`. Por eso, en todos los modelos, ese tipo de relación se nombra con sufijo `Rel` (`estadoRel()`, `faseRel()`, `municipioRel()`, `proyectoRel()`, `procesoRel()`, `modalidadRel()`, `prioridadRel()`, `responsableRel()`, `departamentoRel()`, `sectorRel()`). Las relaciones cuya columna FK es snake_case (ej. `entidad_presenta`, `tipo_proceso`, `proceso_licitacion`) no necesitan el sufijo, porque el nombre camelCase del método nunca coincide con el de la columna. Cualquier código futuro (incluido el refactor de `ProyectoController.php`) que use estos modelos debe respetar esta convención.

**Overrides de `$table` necesarios:** el pluralizador de Laravel adivina mal el nombre de tabla para tres modelos — `Entidad` (adivina `entidads`, la tabla real es `entidades`), `Modalidad` (adivina `modalidads`, real `modalidades`) y `AnexoContrato` (adivina `anexo_contratos`, real `anexos_contratos`). Los tres declaran `protected $table` explícito para corregirlo.

### 4. Autorización por rol

- `App\Enums\Rol`: enum nativo de PHP con los casos `Administrador`, `Gestor`, `Consulta`.
- `User::$casts` castea la columna `rol` a ese enum.
- Gates definidos en `AppServiceProvider::boot()`:
  - `gestionar-catalogos`: solo `Administrador`.
  - `gestionar-usuarios`: solo `Administrador`.
  - `editar-datos`: `Administrador` o `Gestor` (crear/editar/eliminar proyectos, contratos, anexos, actas, avances, procesos de licitación, checklist, eventos).
- Aplicación en `routes/api.php`: las rutas `GET` quedan solo bajo `auth:api` (cualquier rol autenticado puede leer, para que el dashboard funcione igual para los tres roles). Las rutas de escritura de catálogos se agrupan bajo `can:gestionar-catalogos`; las de escritura de proyectos/contratos/eventos bajo `can:editar-datos`; las de gestión de usuarios bajo `can:gestionar-usuarios`.
- Sin paquete nuevo (se descartó `spatie/laravel-permission` por ser más infraestructura de la que 3 roles fijos necesitan).

Matriz de permisos:

| Acción | Administrador | Gestor | Consulta |
|---|:---:|:---:|:---:|
| Ver proyectos, contratos, eventos, dashboard | ✓ | ✓ | ✓ |
| Crear/editar/eliminar proyectos, contratos, anexos, actas, avances, procesos de licitación, checklist, eventos | ✓ | ✓ | ✗ |
| Gestionar catálogos | ✓ | ✗ | ✗ |
| Gestionar usuarios | ✓ | ✗ | ✗ |

### 5. Manejo de errores

Un Gate fallido lanza `AuthorizationException` (403). Se verifica que el manejador de excepciones de `bootstrap/app.php` devuelva JSON para rutas `GestPro/*` (igual que ya hace para 401/419), no un redirect HTML. En `resources/js/axios.js` se añade manejo del 403 en el interceptor existente, mostrando un mensaje claro ("no tienes permiso para esta acción"). No se ocultan pestañas/botones en el frontend en esta fase — decisión explícita, para una fase posterior de rediseño de UI.

### 6. Testing

Tests de Feature (PHPUnit, ya presente en el proyecto):
- `migrate:fresh --seed` corre sin error; el conteo de `municipios`/`departamentos` tras la siembra coincide con el dataset exportado.
- Por cada Gate: un test que confirma que `Administrador` pasa; `Gestor` pasa `editar-datos` pero falla `gestionar-catalogos`/`gestionar-usuarios`; `Consulta` falla ambas rutas de escritura.
- Tests de modelo verificando que las relaciones (`Proyecto->municipioRel`, `Contrato->proyectoRel`, `Estado->faseRel`, etc. — ver convención de nombres en la sección 3) resuelven correctamente contra los datos semilla.

## Decisiones registradas

- Sin datos de producción en juego → se puede corregir el esquema libremente en esta fase.
- Se corrigen tipos y se agregan FKs ahora (no se deja para después).
- Se crean modelos Eloquent ahora, aunque el controlador no se refactoriza todavía.
- Roles: tres fijos (Administrador, Gestor, Consulta), sin necesidad de permisos configurables desde interfaz.
- Mecanismo de autorización: Gates + middleware `can:` nativos de Laravel, sin paquete adicional.
- Frontend: solo se agrega manejo de error 403 legible; no se oculta UI por rol todavía.
- Migración: recrear tablas de negocio desde cero, excepto `municipios`/`departamentos` que se preservan vía seeder por ser catálogo real, no datos de prueba.
- Usuario semilla: `admin@gestpro.local` / `Admin123!` (cambiar tras el primer ingreso).
