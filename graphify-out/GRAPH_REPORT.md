# Graph Report - .  (2026-07-16)

## Corpus Check
- Large corpus: 175 files � ~793,764 words. Semantic extraction will be expensive (many Claude tokens). Consider running on a subfolder.

## Summary
- 720 nodes · 905 edges · 100 communities (91 shown, 9 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 63 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Catalogos Base (EstadoFaseMunicipio)|Catalogos Base (Estado/Fase/Municipio)]]
- [[_COMMUNITY_Modelos de Contrato y Avances|Modelos de Contrato y Avances]]
- [[_COMMUNITY_Componentes React del Dashboard|Componentes React del Dashboard]]
- [[_COMMUNITY_ProyectoController (API CRUD)|ProyectoController (API CRUD)]]
- [[_COMMUNITY_Configuracion ComposerPHP|Configuracion Composer/PHP]]
- [[_COMMUNITY_Diseno Autorizacion y Dashboard|Diseno: Autorizacion y Dashboard]]
- [[_COMMUNITY_Dependencias NPM Frontend|Dependencias NPM Frontend]]
- [[_COMMUNITY_Mapas Leaflet y Ubicaciones|Mapas Leaflet y Ubicaciones]]
- [[_COMMUNITY_Diseno del Dashboard React|Diseno del Dashboard React]]
- [[_COMMUNITY_Modelos Entidad y Evento|Modelos Entidad y Evento]]
- [[_COMMUNITY_Tablas Hijas de Proyecto|Tablas Hijas de Proyecto]]
- [[_COMMUNITY_Autenticacion JWT y Usuarios|Autenticacion JWT y Usuarios]]
- [[_COMMUNITY_Seeders de Departamentos|Seeders de Departamentos]]
- [[_COMMUNITY_Modelo Proyecto (agregador)|Modelo Proyecto (agregador)]]
- [[_COMMUNITY_Modelo Contrato|Modelo Contrato]]
- [[_COMMUNITY_Modelo ProcesoLicitacion|Modelo ProcesoLicitacion]]
- [[_COMMUNITY_Service Providers Laravel|Service Providers Laravel]]
- [[_COMMUNITY_AuthController (loginlogout)|AuthController (login/logout)]]
- [[_COMMUNITY_Comprobante de Pago PSE (Hosting)|Comprobante de Pago PSE (Hosting)]]
- [[_COMMUNITY_UserFactory (testing)|UserFactory (testing)]]
- [[_COMMUNITY_Imagenes Anexo Competencia Ciudadana|Imagenes Anexo Competencia Ciudadana]]
- [[_COMMUNITY_CORS Middleware|CORS Middleware]]
- [[_COMMUNITY_Setup de Produccion|Setup de Produccion]]
- [[_COMMUNITY_CSRF Middleware|CSRF Middleware]]
- [[_COMMUNITY_Diseno Esquema de Autorizacion|Diseno: Esquema de Autorizacion]]
- [[_COMMUNITY_Configuracion de Permisos|Configuracion de Permisos]]
- [[_COMMUNITY_Assets Compilados (Mix)|Assets Compilados (Mix)]]
- [[_COMMUNITY_Comunidad 58|Comunidad 58]]
- [[_COMMUNITY_Comunidad 59|Comunidad 59]]
- [[_COMMUNITY_Comunidad 61|Comunidad 61]]
- [[_COMMUNITY_Comunidad 98|Comunidad 98]]
- [[_COMMUNITY_Comunidad 99|Comunidad 99]]

## God Nodes (most connected - your core abstractions)
1. `ProyectoController` - 48 edges
2. `Request` - 37 edges
3. `TestCase` - 28 edges
4. `User` - 17 edges
5. `Proyecto` - 15 edges
6. `Municipio` - 12 edges
7. `DemoDataSeeder` - 10 edges
8. `Evento` - 9 edges
9. `AvancesTest` - 9 edges
10. `HijasDeProyectoTest` - 9 edges

## Surprising Connections (you probably didn't know these)
- `GestPro Production Setup` --conceptually_related_to--> `Laravel Framework`  [INFERRED]
  PRODUCTION_SETUP.md → README.md
- `KpiStrip Component (6 métricas)` --references--> `Dashboard.jsx Component`  [EXTRACTED]
  docs/superpowers/plans/2026-07-07-dashboard-kpis-ejecutivos.md → README-Dashboard.md
- `MunicipioCard Component` --references--> `Dashboard.jsx Component`  [EXTRACTED]
  docs/superpowers/plans/2026-07-07-dashboard-municipio-card.md → README-Dashboard.md
- `Convención de Nombrado Rel() para belongsTo` --semantically_similar_to--> `Patrón delete-and-reinsert (PresupuestoProyecto)`  [INFERRED] [semantically similar]
  docs/superpowers/plans/2026-07-06-esquema-autorizacion.md → docs/superpowers/plans/2026-07-08-ubicacion-proyectos-mapa.md
- `Patrón delete-and-reinsert (PresupuestoProyecto)` --semantically_similar_to--> `Patrón Arquitectónico de Anexos de Contrato`  [INFERRED] [semantically similar]
  docs/superpowers/plans/2026-07-08-ubicacion-proyectos-mapa.md → docs/superpowers/plans/2026-07-10-gestion-avance-contratos.md

## Hyperedges (group relationships)
- **Dashboard React Presentational Components** — gestpro_readme_dashboard_dashboard_jsx, plans_2026_07_07_dashboard_kpis_ejecutivos_kpi_strip, plans_2026_07_07_dashboard_municipio_card_municipio_card, plans_2026_07_07_estadisticas_graficas_interactivas_estadisticas_jsx, plans_2026_07_08_dashboard_mapa_ubicaciones_mapa_ubicaciones [INFERRED 0.85]
- **Modelo de Avance de Contrato (financiero + físico + anticipo)** — plans_2026_07_10_gestion_avance_contratos_avance_financiero, plans_2026_07_10_gestion_avance_contratos_avance_fisico, plans_2026_07_10_gestion_avance_contratos_avance_financiero_tabla, plans_2026_07_14_anticipo_contrato_calculo_valor_presente, plans_2026_07_14_anticipo_contrato_contrato_model [INFERRED 0.85]
- **Funcionalidad de Mapas con Leaflet** — plans_2026_07_08_ubicacion_proyectos_mapa_proyecto_ubicacion, plans_2026_07_08_dashboard_mapa_ubicaciones_mapa_ubicaciones, plans_2026_07_08_dashboard_mapa_ubicaciones_leaflet_icon_fix, plans_2026_07_08_ubicacion_proyectos_mapa_leaflet [INFERRED 0.85]
- **Componentes del dashboard ejecutivo derivados de GET /proyectos** — specs_2026_07_07_dashboard_kpis_ejecutivos_design_kpistrip, specs_2026_07_07_dashboard_layout_alertas_design_alertaspanel, specs_2026_07_07_dashboard_municipio_card_design_municipiocard_componente, specs_2026_07_07_estadisticas_graficas_interactivas_design_estadisticas_componente [INFERRED 0.85]
- **Flujo de captura y visualización de ubicaciones de proyectos** — specs_2026_07_08_ubicacion_proyectos_mapa_design_tabla_proyecto_ubicaciones, specs_2026_07_08_ubicacion_proyectos_mapa_design_pestana_ubicacion, specs_2026_07_08_dashboard_mapa_ubicaciones_design_mapaubicaciones [EXTRACTED 0.75]
- **Gestión de avance de contrato con anticipo y actividades** — specs_2026_07_10_gestion_avance_contratos_design_avance_financiero, specs_2026_07_10_gestion_avance_contratos_design_actividades_ponderadas, specs_2026_07_10_anticipo_contrato_design_calculo_amortizacion [INFERRED 0.85]

## Communities (100 total, 9 thin omitted)

### Community 0 - "Catalogos Base (Estado/Fase/Municipio)"
Cohesion: 0.05
Nodes (22): BelongsTo, HasMany, HasMany, BelongsTo, HasMany, BaseTestCase, Contrato, ExampleTest (+14 more)

### Community 1 - "Modelos de Contrato y Avances"
Cohesion: 0.06
Nodes (27): BelongsTo, BelongsTo, HasMany, BelongsTo, BelongsTo, BelongsTo, HasMany, HasMany (+19 more)

### Community 2 - "Componentes React del Dashboard"
Cohesion: 0.05
Nodes (17): FILTRO_INICIAL, MESES_CORTOS, PALETA_MUNICIPIOS, Header(), formatearPresupuesto(), KpiStrip(), Login(), CENTRO_POR_DEFECTO (+9 more)

### Community 4 - "Configuracion Composer/PHP"
Cohesion: 0.04
Nodes (47): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+39 more)

### Community 5 - "Diseno: Autorizacion y Dashboard"
Cohesion: 0.05
Nodes (47): Autorización por rol con Gates y middleware can, Convención de sufijo Rel en relaciones Eloquent, Enum Rol Administrador Gestor Consulta, Esquema versionado y autorización por rol, Llaves foráneas con foreignId constrained, Manejo de error 403 en backend y axios, Migraciones de Laravel para tablas de negocio, Modelos Eloquent de 21 entidades de negocio (+39 more)

### Community 6 - "Dependencias NPM Frontend"
Cohesion: 0.07
Nodes (29): dependencies, axios, emoji-picker-react, @fortawesome/fontawesome-svg-core, @fortawesome/free-solid-svg-icons, @fortawesome/react-fontawesome, js-cookie, leaflet (+21 more)

### Community 7 - "Mapas Leaflet y Ubicaciones"
Cohesion: 0.09
Nodes (28): Catálogos Simples (Sector/Entidad/etc), Convención de Nombrado Rel() para belongsTo, Departamento Model, Fase y Estado Models, Municipio Model, ProyectoController (DB::table pattern), Proyecto Model, leafletIconFix Shared Module (+20 more)

### Community 8 - "Diseno del Dashboard React"
Cohesion: 0.08
Nodes (27): Dashboard.jsx Component, Dashboard Mejorado GestPro, Glassmorphism / Efectos de Cristal, Paleta de Colores del Dashboard, Diseño Responsive por Tamaño de Pantalla, Cálculo Client-Side desde /proyectos, DemoDataSeeder (30 proyectos, 5 municipios), KpiStrip Component (6 métricas) (+19 more)

### Community 9 - "Modelos Entidad y Evento"
Cohesion: 0.15
Nodes (5): HasMany, BelongsTo, Entidad, Evento, DemoDataSeeder

### Community 10 - "Tablas Hijas de Proyecto"
Cohesion: 0.14
Nodes (8): BelongsTo, BelongsTo, BelongsTo, CheckFormulacion, EventoLicitacion, PresupuestoProyecto, HijasDeProyectoTest, Proyecto

### Community 11 - "Autenticacion JWT y Usuarios"
Cohesion: 0.14
Nodes (8): Authenticatable, AutorizacionTest, HasApiTokens, HasFactory, JWTSubject, User, Notifiable, UserRolTest

### Community 12 - "Seeders de Departamentos"
Cohesion: 0.14
Nodes (7): HasMany, Departamento, Seeder, AdminUserSeeder, DatabaseSeeder, DepartamentosSeeder, MunicipiosSeeder

### Community 13 - "Modelo Proyecto (agregador)"
Cohesion: 0.21
Nodes (3): BelongsTo, HasMany, Proyecto

### Community 14 - "Modelo Contrato"
Cohesion: 0.33
Nodes (3): BelongsTo, HasMany, Contrato

### Community 15 - "Modelo ProcesoLicitacion"
Cohesion: 0.33
Nodes (3): BelongsTo, HasMany, ProcesoLicitacion

### Community 16 - "Service Providers Laravel"
Cohesion: 0.28
Nodes (3): AppServiceProvider, RouteServiceProvider, ServiceProvider

### Community 17 - "AuthController (login/logout)"
Cohesion: 0.29
Nodes (3): Request, AuthController, Controller

### Community 18 - "Comprobante de Pago PSE (Hosting)"
Cohesion: 0.47
Nodes (6): Banco de Bogota, Banco de Bogota PSE hosting payment voucher (PDF 1754940557), HOSTING RED SAS service (Factura 48478), Payment of $160.955 COP (2025-03-07), PSE virtual payment system, TECNIPAGOS S A (NIT 8301097238)

### Community 19 - "UserFactory (testing)"
Cohesion: 0.47
Nodes (3): UserFactory, Factory, static

### Community 20 - "Imagenes Anexo Competencia Ciudadana"
Cohesion: 0.70
Nodes (5): Anexo image 1754929884_689a1adcadfdd (COMPETENCIA CIUDADANA graphic), Anexo image 1754930736_689a1e30e31cc (COMPETENCIA CIUDADANA graphic), Anexo image 1754931316_689a207482b1d (COMPETENCIA CIUDADANA graphic), Competencia Ciudadana (citizen competence / teamwork), Flat-design teamwork illustration (four hands gripping wrists)

### Community 21 - "CORS Middleware"
Cohesion: 0.60
Nodes (3): Request, Closure, CorsMiddleware

### Community 22 - "Setup de Produccion"
Cohesion: 0.40
Nodes (5): Apache mod_rewrite / .htaccess RewriteBase, CORS Middleware, JWT Authentication, GestPro Production Setup, Laravel Framework

### Community 24 - "Diseno: Esquema de Autorizacion"
Cohesion: 0.50
Nodes (4): Autorización por Rol (Gate/can middleware), Esquema Versionado y Autorización por Rol Plan, Rol Enum (Administrador/Gestor/Consulta), Versionado de Tablas en Migraciones Laravel

## Knowledge Gaps
- **111 isolated node(s):** `$schema`, `name`, `type`, `description`, `keywords` (+106 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **9 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `TestCase` connect `Catalogos Base (Estado/Fase/Municipio)` to `Modelos de Contrato y Avances`, `Tablas Hijas de Proyecto`, `Autenticacion JWT y Usuarios`?**
  _High betweenness centrality (0.020) - this node is a cross-community bridge._
- **Why does `Municipio` connect `Catalogos Base (Estado/Fase/Municipio)` to `Modelos de Contrato y Avances`, `Tablas Hijas de Proyecto`, `Seeders de Departamentos`?**
  _High betweenness centrality (0.015) - this node is a cross-community bridge._
- **Why does `Proyecto` connect `Modelo Proyecto (agregador)` to `Modelos de Contrato y Avances`?**
  _High betweenness centrality (0.012) - this node is a cross-community bridge._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _119 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Catalogos Base (Estado/Fase/Municipio)` be split into smaller, more focused modules?**
  _Cohesion score 0.052403846153846155 - nodes in this community are weakly interconnected._
- **Should `Modelos de Contrato y Avances` be split into smaller, more focused modules?**
  _Cohesion score 0.056261343012704176 - nodes in this community are weakly interconnected._
- **Should `Componentes React del Dashboard` be split into smaller, more focused modules?**
  _Cohesion score 0.052244897959183675 - nodes in this community are weakly interconnected._