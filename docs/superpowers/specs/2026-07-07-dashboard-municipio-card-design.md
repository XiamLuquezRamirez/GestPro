# Rediseño de la tarjeta de municipio en el Dashboard

**Fecha:** 2026-07-07
**Estado:** Aprobado, pendiente de plan de implementación

## Contexto

La auditoría inicial de GestPro (ver `docs/superpowers/specs/2026-07-06-esquema-autorizacion-design.md`) propuso un rediseño del Dashboard hacia una vista de "sala de control" por municipio, con semáforo de estado. Esta es la primera iteración de esa propuesta, acotada deliberadamente a una sola pieza visual.

`resources/js/components/Dashboard.jsx` (774 líneas — evolucionó respecto a lo documentado en la auditoría original, que describía una versión de 1575 líneas) organiza los proyectos en pestañas por fase (Formulación / Licitación / Ejecución). Dentro de cada pestaña, `municipios-grid` (líneas 342-370) renderiza una tarjeta `.municipio-card-modern` por municipio con: ícono fijo (🏙️), nombre, conteo de proyectos en esa fase, y una flecha. El color de borde (`border-left`) se asigna hoy por posición (`nth-child(1..4)` con colores fijos en `Dashboard.css:166-169`), sin relación con los datos reales.

Por separado existe una pestaña "Estadísticas y Filtros" con tarjetas KPI y 4 gráficas caseras (barras/pie con CSS puro) — **esa pestaña queda fuera de este cambio**, se decidió explícitamente no tocarla en esta iteración.

El endpoint `GET /proyectos` (`ProyectoController::proyectos()`, `app/Http/Controllers/ProyectoController.php:10-40`) ya devuelve `estado.color as color_estado` por cada proyecto (viene del catálogo real `estados`, ya versionado con migraciones en el trabajo de esquema/autorización) — no hace falta ningún cambio de backend para este rediseño.

## Alcance

Incluye:
- Extraer la tarjeta de municipio (hoy inline en el `.map()` de `Dashboard.jsx`) a un componente propio `MunicipioCard.jsx`.
- Agregar una barra segmentada por estado dentro de la tarjeta, con color real tomado de `proyecto.color_estado`.
- Eliminar el `border-left` arcoíris posicional (`nth-child`) de `.municipio-card-modern`, ya que pierde sentido frente a la barra segmentada.

No incluye (fuera de alcance, iteraciones futuras):
- La pestaña "Estadísticas y Filtros" (KPIs, las 4 gráficas caseras, el bloque muerto de "Presupuesto Mensual", "Filtros por Estado").
- La vista de detalle al hacer clic en un municipio (lista de `proyecto-card` individuales) y los modales de proyecto/contrato.
- El widget de Eventos al final de la página.
- Endpoint de agregación en el backend, modo "pantalla fija" con auto-refresco, y una regla concreta de "en riesgo/crítico" — todo esto quedó explícitamente para una iteración posterior, una vez validado este cambio visual.
- Cualquier cambio a `ProyectoController.php` o a las rutas.

## Diseño

### Componente `MunicipioCard.jsx`

Nuevo archivo `resources/js/components/MunicipioCard.jsx`. Recibe por props: `municipio` (objeto con `nombre`, `icon`), `proyectos` (array de proyectos de ese municipio+fase, ya filtrado por el padre igual que hoy vía `getProyectosPorMunicipioYNombreFase`), y `onClick`. Renderiza exactamente la misma estructura que hoy (`municipio-card-content`, `municipio-icon-modern`, `municipio-info-modern`, `proyectos-count-modern`, `municipio-arrow`), más una barra segmentada nueva debajo del conteo.

`Dashboard.jsx` reemplaza el bloque inline de `municipios-grid` (líneas 342-370) por un `.map()` que renderiza `<MunicipioCard />`, pasándole los mismos datos que ya calcula (`municipios`, `getProyectosPorMunicipioYNombreFase`, `handleMunicipioClick`). No cambia ningún estado, ningún efecto, ninguna función de filtrado existente en `Dashboard.jsx`.

### Barra segmentada por estado

Dentro de `MunicipioCard`, se agrupan los `proyectos` recibidos por `descripcion_estado`, y se renderiza un `<div>` por cada estado presente con:
- `width`: proporcional a `cantidad / total` de esa tarjeta.
- `background-color`: `proyecto.color_estado` (el primer proyecto de ese grupo — todos los proyectos del mismo estado comparten el mismo `color_estado`, viene del catálogo).

Si `color_estado` viene `null` (proyecto sin estado asignado), se usa un gris neutro fijo (`#9e9e9e`) como respaldo — no se reintroduce el mapa hardcodeado `getEstadoColor()` (ese sigue existiendo tal cual para la pestaña Estadísticas, que no se toca).

### CSS

Reglas nuevas en `resources/css/Dashboard.css` (no se crea un tercer archivo): una clase `.municipio-estado-bar` (contenedor flex, `height: 8px`, `border-radius`) y `.municipio-estado-segmento` (un `<span>` por segmento). Se eliminan las 4 reglas `.municipio-card-modern:nth-child(1..4)` (`Dashboard.css:166-169`) que hoy fijan el `border-left`.

### Comportamiento sin cambios

El clic en la tarjeta sigue llamando a `handleMunicipioClick(municipio.nombre)` exactamente igual que hoy — mismo comportamiento de navegación a la vista de detalle. Los estilos responsivos existentes de `.municipios-grid` (`Dashboard.css:772-835`, breakpoints por tamaño de pantalla) no se tocan.

### Verificación

No existe suite de tests de frontend en este proyecto (confirmado en el plan de esquema/autorización anterior — no hay Jest/Vitest configurado). La verificación es manual: levantar la app (`npm run dev` + servidor Laravel), iniciar sesión, entrar al Dashboard, y confirmar en cada pestaña de fase que:
- La grilla de municipios muestra la barra segmentada con colores reales de estado (no la paleta hardcodeada de antes).
- El conteo y el ancho de los segmentos suman correctamente al total de proyectos de la tarjeta.
- El clic en una tarjeta sigue llevando a la vista de detalle de ese municipio, sin cambios.
- La pestaña "Estadísticas y Filtros" se ve exactamente igual que antes del cambio.

## Decisiones registradas

- Estilo visual: se mantiene y evoluciona la identidad glass azul/blanco ya existente en `.municipio-card-modern` (no se adopta ahora el estilo oscuro de "sala de control" mostrado en la auditoría — eso queda para cuando se construya el modo pantalla fija).
- Se mantienen las pestañas por fase tal como están hoy; no se unifica en una sola vista con todos los municipios.
- La fuente de color del semáforo es el `estado` real del proyecto (`color_estado` del catálogo), no un cálculo de riesgo nuevo — esa regla queda para una fase posterior.
- Se extrae `MunicipioCard.jsx` como componente propio (en vez de dejar el JSX inline), aprovechando que ya se está reescribiendo esa sección exacta.
- Alcance limitado estrictamente a la tarjeta de municipio dentro de cada pestaña de fase; todo lo demás en `Dashboard.jsx` queda intacto.
