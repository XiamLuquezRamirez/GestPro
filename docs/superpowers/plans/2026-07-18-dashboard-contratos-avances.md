# Pestaña de Contratos con Avance Físico vs Financiero — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Agregar una pestaña "Contratos" al dashboard con un scatter de avance físico vs financiero de todos los contratos, una tabla con semáforo de desfase, y una gráfica de evolución temporal del contrato seleccionado.

**Architecture:** El cálculo vive en funciones puras (`resources/js/utils/avanceContratos.js`) probadas con Vitest, separadas del componente de presentación (`ContratosPanel.jsx`). El backend se amplía de forma aditiva para entregar el histórico completo de avances por actividad, que hoy no llega. El dashboard consume todo desde el endpoint `/proyectos` que ya carga.

**Tech Stack:** Laravel 12 (PHP 8.2) + PHPUnit, React 18, Recharts (ya instalado), Vitest (se instala en Task 1), Laravel Mix.

**Spec:** `docs/superpowers/specs/2026-07-18-dashboard-contratos-avances-design.md`

---

## Estructura de archivos

- **Crear:** `resources/js/utils/avanceContratos.js` — funciones puras de cálculo: aplanar contratos, desfase/severidad, series temporales. Sin React, sin axios.
- **Crear:** `resources/js/utils/avanceContratos.test.js` — tests Vitest de esas funciones.
- **Crear:** `resources/js/components/ContratosPanel.jsx` — la pestaña: KPIs, scatter, tabla y gráfica de evolución.
- **Modificar:** `resources/js/components/Dashboard.jsx` — botón de pestaña "Contratos" + render de `ContratosPanel`.
- **Modificar:** `app/Http/Controllers/ProyectoController.php` — adjuntar `avances` (histórico) por actividad en `proyectos` y `listarContratos`.
- **Modificar:** `tests/Feature/Schema/AvancesTest.php` — tests del histórico en los endpoints.
- **Modificar:** `package.json` — devDependency Vitest + script `test`.

**Fórmulas de referencia** (ya existen en `Parametros.jsx`, no reimplementar distinto):
```
avance_fisico     = Σ ( peso_actividad / 100 × último_porcentaje_ejecucion )
avance_financiero = Σ ( valor_facturado de actas ) / valor_vigente × 100
```

**Umbrales de desfase** (financiero − físico, puntos porcentuales): verde ≤10, ámbar 11-25, rojo >25.

**Entorno:** ejecutar desde `C:\xampp\htdocs\GestPro`. Backend: `php artisan test`. Frontend: `npm test` (tras Task 1) y `npx mix` para compilar.

---

## Task 1: Instalar Vitest

**Files:**
- Modify: `package.json`
- Create: `resources/js/utils/avanceContratos.test.js` (test de humo temporal)

- [ ] **Step 1: Instalar Vitest**

Run: `npm install --save-dev vitest`

- [ ] **Step 2: Agregar el script de test**

En `package.json`, dentro de `"scripts"`, agregar la línea `"test": "vitest run"` (dejando los demás scripts intactos). El bloque queda:

```json
    "scripts": {
        "dev": "npm run development",
        "development": "mix",
        "watch": "mix watch",
        "hot": "mix watch --hot",
        "prod": "npm run production",
        "production": "mix --production",
        "test": "vitest run"
    },
```

- [ ] **Step 3: Crear un test de humo para verificar la instalación**

Crear `resources/js/utils/avanceContratos.test.js`:

```js
import { describe, it, expect } from 'vitest';

describe('infraestructura de tests', () => {
    it('vitest corre', () => {
        expect(1 + 1).toBe(2);
    });
});
```

- [ ] **Step 4: Ejecutar los tests**

Run: `npm test`
Expected: PASS — 1 test passed.

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json resources/js/utils/avanceContratos.test.js
git commit -m "chore: add vitest for frontend unit tests"
```

---

## Task 2: Backend — histórico de avances por actividad

**Files:**
- Modify: `app/Http/Controllers/ProyectoController.php` (métodos `proyectos` y `listarContratos`)
- Modify: `tests/Feature/Schema/AvancesTest.php`

**Contexto:** hoy ambos endpoints adjuntan solo `ultimo_avance` y `fecha_ultimo_avance` por actividad. Se agrega `avances` con el histórico completo. Es aditivo: no se altera ni se quita ningún campo existente.

- [ ] **Step 1: Escribir el test que falla**

En `tests/Feature/Schema/AvancesTest.php`, agregar al inicio los imports que falten (`use App\Enums\Rol;`, `use App\Models\User;`, `use Illuminate\Support\Facades\DB;`) y añadir este test dentro de la clase:

```php
    public function test_proyectos_entrega_historico_de_avances_por_actividad(): void
    {
        $contrato = $this->crearContrato();
        $actividad = ActividadContrato::create(['contrato_id' => $contrato->id, 'nombre' => 'Excavación', 'peso' => 50]);
        ActividadAvance::create(['actividad_id' => $actividad->id, 'fecha' => '2026-01-15', 'porcentaje_ejecucion' => 20]);
        ActividadAvance::create(['actividad_id' => $actividad->id, 'fecha' => '2026-02-15', 'porcentaje_ejecucion' => 60]);

        $admin = User::factory()->create(['rol' => Rol::Administrador]);
        $token = auth('api')->login($admin);

        $resp = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/GestPro/proyectos');
        $resp->assertOk();

        $proyectoJson = collect($resp->json())->firstWhere('id', $contrato->proyecto);
        $this->assertNotNull($proyectoJson);
        $contratoJson = collect($proyectoJson['contratos'])->firstWhere('id', $contrato->id);
        $actividadJson = collect($contratoJson['actividades'])->firstWhere('id', $actividad->id);

        $this->assertCount(2, $actividadJson['avances']);
        $this->assertSame('2026-01-15', substr($actividadJson['avances'][0]['fecha'], 0, 10));
        $this->assertSame(20, $actividadJson['avances'][0]['porcentaje_ejecucion']);
        $this->assertSame(60, $actividadJson['avances'][1]['porcentaje_ejecucion']);
    }

    public function test_actividad_sin_avances_entrega_arreglo_vacio(): void
    {
        $contrato = $this->crearContrato();
        $actividad = ActividadContrato::create(['contrato_id' => $contrato->id, 'nombre' => 'Acabados', 'peso' => 30]);

        $admin = User::factory()->create(['rol' => Rol::Administrador]);
        $token = auth('api')->login($admin);

        $resp = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/GestPro/proyectos');
        $resp->assertOk();

        $proyectoJson = collect($resp->json())->firstWhere('id', $contrato->proyecto);
        $contratoJson = collect($proyectoJson['contratos'])->firstWhere('id', $contrato->id);
        $actividadJson = collect($contratoJson['actividades'])->firstWhere('id', $actividad->id);

        $this->assertIsArray($actividadJson['avances']);
        $this->assertCount(0, $actividadJson['avances']);
    }

    public function test_listar_contratos_entrega_historico_de_avances(): void
    {
        $contrato = $this->crearContrato();
        $actividad = ActividadContrato::create(['contrato_id' => $contrato->id, 'nombre' => 'Estructura', 'peso' => 40]);
        ActividadAvance::create(['actividad_id' => $actividad->id, 'fecha' => '2026-03-01', 'porcentaje_ejecucion' => 45]);

        $admin = User::factory()->create(['rol' => Rol::Administrador]);
        $token = auth('api')->login($admin);

        $resp = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/GestPro/listarContratos?proyecto=' . $contrato->proyecto);
        $resp->assertOk();

        $contratoJson = collect($resp->json())->firstWhere('id', $contrato->id);
        $actividadJson = collect($contratoJson['actividades'])->firstWhere('id', $actividad->id);
        $this->assertCount(1, $actividadJson['avances']);
        $this->assertSame(45, $actividadJson['avances'][0]['porcentaje_ejecucion']);
    }
```

- [ ] **Step 2: Ejecutar los tests para verificar que fallan**

Run: `php artisan test --filter=AvancesTest`
Expected: los tres tests nuevos FAIL — clave `avances` inexistente (`Undefined array key "avances"`).

- [ ] **Step 3: Adjuntar el histórico en `proyectos`**

En `app/Http/Controllers/ProyectoController.php`, dentro del método `proyectos`, en el bucle `foreach ($actividades as $actividad)` (donde se asignan `ultimo_avance` y `fecha_ultimo_avance`), agregar después de esas dos asignaciones:

```php
                    $actividad->avances = DB::table('actividad_avances')
                        ->select('id', 'fecha', 'porcentaje_ejecucion')
                        ->where('actividad_id', $actividad->id)
                        ->orderBy('fecha', 'asc')
                        ->orderBy('id', 'asc')
                        ->get();
```

- [ ] **Step 4: Adjuntar el histórico en `listarContratos`**

En el mismo archivo, dentro del método `listarContratos`, en su bucle `foreach ($actividades as $actividad)`, agregar el mismo bloque después de `fecha_ultimo_avance`:

```php
                $actividad->avances = DB::table('actividad_avances')
                    ->select('id', 'fecha', 'porcentaje_ejecucion')
                    ->where('actividad_id', $actividad->id)
                    ->orderBy('fecha', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();
```

(Ojo con la indentación: en `listarContratos` el bucle está un nivel menos anidado que en `proyectos`.)

- [ ] **Step 5: Ejecutar los tests para verificar que pasan**

Run: `php artisan test --filter=AvancesTest`
Expected: PASS todos.

- [ ] **Step 6: Ejecutar la suite completa**

Run: `php artisan test`
Expected: PASS toda la suite.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ProyectoController.php tests/Feature/Schema/AvancesTest.php
git commit -m "feat: expose full activity progress history in proyectos and listarContratos"
```

---

## Task 3: Utilidades de cálculo — aplanar contratos y desfase

**Files:**
- Create: `resources/js/utils/avanceContratos.js`
- Modify: `resources/js/utils/avanceContratos.test.js`

- [ ] **Step 1: Escribir los tests que fallan**

Reemplazar el contenido completo de `resources/js/utils/avanceContratos.test.js` por:

```js
import { describe, it, expect } from 'vitest';
import { calcularDesfase, severidadDesfase, aplanarContratos } from './avanceContratos';

describe('calcularDesfase', () => {
    it('resta financiero menos físico', () => {
        expect(calcularDesfase({ avance_fisico: 35, avance_financiero: 78 })).toBe(43);
    });

    it('puede ser negativo cuando lo ejecutado supera lo facturado', () => {
        expect(calcularDesfase({ avance_fisico: 70, avance_financiero: 55 })).toBe(-15);
    });

    it('trata null como 0', () => {
        expect(calcularDesfase({ avance_fisico: null, avance_financiero: 40 })).toBe(40);
    });
});

describe('severidadDesfase', () => {
    it('clasifica 10 como sano (borde inferior)', () => {
        expect(severidadDesfase(10)).toBe('sano');
    });

    it('clasifica 11 como atencion', () => {
        expect(severidadDesfase(11)).toBe('atencion');
    });

    it('clasifica 25 como atencion (borde superior)', () => {
        expect(severidadDesfase(25)).toBe('atencion');
    });

    it('clasifica 26 como critico', () => {
        expect(severidadDesfase(26)).toBe('critico');
    });

    it('un desfase negativo es sano', () => {
        expect(severidadDesfase(-15)).toBe('sano');
    });
});

describe('aplanarContratos', () => {
    const proyectos = [
        {
            id: 1,
            nombre: 'Acueducto Rural',
            contratos: [
                { id: 10, n_contrato: 'C-001', valor: '890000000.00', avance_fisico: 65, avance_financiero: 90, actividades: [], avancesFinancieros: [] },
                { id: 11, n_contrato: 'C-002', valor: '1240000000.00', avance_fisico: null, avance_financiero: null, actividades: [], avancesFinancieros: [] },
            ],
        },
        {
            id: 2,
            nombre: 'Parque Central',
            contratos: [
                { id: 12, n_contrato: 'C-005', valor: '620000000.00', avance_fisico: 42, avance_financiero: 58, actividades: [], avancesFinancieros: [] },
            ],
        },
    ];

    it('extrae todos los contratos de todos los proyectos', () => {
        expect(aplanarContratos(proyectos)).toHaveLength(3);
    });

    it('conserva el nombre del proyecto en cada contrato', () => {
        const filas = aplanarContratos(proyectos);
        expect(filas.find(c => c.id === 12).nombreProyecto).toBe('Parque Central');
    });

    it('calcula desfase y severidad de los contratos con datos', () => {
        const fila = aplanarContratos(proyectos).find(c => c.id === 10);
        expect(fila.desfase).toBe(25);
        expect(fila.severidad).toBe('atencion');
        expect(fila.sinDatos).toBe(false);
    });

    it('marca como sinDatos los contratos sin avances registrados', () => {
        const fila = aplanarContratos(proyectos).find(c => c.id === 11);
        expect(fila.sinDatos).toBe(true);
    });

    it('devuelve arreglo vacío si no hay proyectos', () => {
        expect(aplanarContratos([])).toEqual([]);
        expect(aplanarContratos(null)).toEqual([]);
    });
});
```

- [ ] **Step 2: Ejecutar para verificar que fallan**

Run: `npm test`
Expected: FAIL — no se puede importar desde `./avanceContratos` (el archivo no existe).

- [ ] **Step 3: Implementar las funciones**

Crear `resources/js/utils/avanceContratos.js`:

```js
// Utilidades de cálculo para la vista de contratos del dashboard.
// Funciones puras: sin React, sin peticiones. Probadas en avanceContratos.test.js.

// Umbrales de desfase (financiero − físico) en puntos porcentuales.
export const UMBRAL_SANO = 10;
export const UMBRAL_ATENCION = 25;

export const COLOR_SEVERIDAD = {
    sano: '#16a34a',
    atencion: '#f59e0b',
    critico: '#dc2626',
};

const numero = (valor) => {
    const n = parseFloat(valor);
    return Number.isFinite(n) ? n : 0;
};

// Desfase = avance financiero − avance físico. Positivo significa que se ha
// facturado más de lo ejecutado.
export const calcularDesfase = (contrato) =>
    numero(contrato?.avance_financiero) - numero(contrato?.avance_fisico);

export const severidadDesfase = (desfase) => {
    if (desfase <= UMBRAL_SANO) return 'sano';
    if (desfase <= UMBRAL_ATENCION) return 'atencion';
    return 'critico';
};

// Un contrato "sin datos" no tiene ni avance físico ni financiero registrados:
// graficarlo en (0,0) distorsiona la lectura del scatter.
const sinDatosDeAvance = (contrato) =>
    (contrato?.avance_fisico === null || contrato?.avance_fisico === undefined || contrato?.avance_fisico === '') &&
    (contrato?.avance_financiero === null || contrato?.avance_financiero === undefined || contrato?.avance_financiero === '');

// Convierte la estructura anidada proyectos[].contratos[] en una lista plana de
// filas listas para graficar y tabular.
export const aplanarContratos = (proyectos) => {
    if (!Array.isArray(proyectos)) return [];

    return proyectos.flatMap((proyecto) =>
        (proyecto.contratos || []).map((contrato) => {
            const sinDatos = sinDatosDeAvance(contrato);
            const desfase = calcularDesfase(contrato);

            return {
                ...contrato,
                nombreProyecto: proyecto.nombre,
                proyectoId: proyecto.id,
                avanceFisico: numero(contrato.avance_fisico),
                avanceFinanciero: numero(contrato.avance_financiero),
                valorNumerico: numero(contrato.valor),
                desfase,
                severidad: severidadDesfase(desfase),
                sinDatos,
            };
        })
    );
};
```

- [ ] **Step 4: Ejecutar para verificar que pasan**

Run: `npm test`
Expected: PASS todos.

- [ ] **Step 5: Commit**

```bash
git add resources/js/utils/avanceContratos.js resources/js/utils/avanceContratos.test.js
git commit -m "feat: add contract progress calculation utilities"
```

---

## Task 4: Utilidades de cálculo — serie temporal

**Files:**
- Modify: `resources/js/utils/avanceContratos.js`
- Modify: `resources/js/utils/avanceContratos.test.js`

**Contexto:** la serie reconstruye ambos avances en cada fecha de corte. Físico = suma ponderada usando, por actividad, su último avance con fecha ≤ corte. Financiero = suma acumulada de `valor_facturado` de actas con `fecha_acta` ≤ corte, dividida por el valor vigente.

- [ ] **Step 1: Escribir los tests que fallan**

Primero, ampliar el import existente al inicio de `resources/js/utils/avanceContratos.test.js`
para incluir la función nueva (no agregar un segundo `import` del mismo módulo):

```js
import { calcularDesfase, severidadDesfase, aplanarContratos, construirSerieTemporal } from './avanceContratos';
```

Luego agregar al final del archivo:

```js
describe('construirSerieTemporal', () => {
    const contrato = {
        valor: '1000000000.00',
        actividades: [
            {
                id: 1, nombre: 'Excavación', peso: 60,
                avances: [
                    { fecha: '2026-01-31', porcentaje_ejecucion: 50 },
                    { fecha: '2026-03-31', porcentaje_ejecucion: 100 },
                ],
            },
            {
                id: 2, nombre: 'Estructura', peso: 40,
                avances: [
                    { fecha: '2026-03-31', porcentaje_ejecucion: 25 },
                ],
            },
        ],
        avancesFinancieros: [
            { fecha_acta: '2026-02-28', valor_facturado: '200000000.00' },
            { fecha_acta: '2026-03-31', valor_facturado: '300000000.00' },
        ],
    };

    it('genera un punto por cada fecha de corte, ordenadas', () => {
        const serie = construirSerieTemporal(contrato);
        expect(serie.map(p => p.fecha)).toEqual(['2026-01-31', '2026-02-28', '2026-03-31']);
    });

    it('calcula el físico ponderado usando el último avance hasta cada fecha', () => {
        const serie = construirSerieTemporal(contrato);
        // 31-ene: Excavación 50% × peso 60 = 30; Estructura sin avances = 0
        expect(serie[0].fisico).toBe(30);
        // 28-feb: sin avances nuevos, se mantiene 30
        expect(serie[1].fisico).toBe(30);
        // 31-mar: Excavación 100% × 60 = 60; Estructura 25% × 40 = 10 → 70
        expect(serie[2].fisico).toBe(70);
    });

    it('acumula el financiero sobre valor_facturado, no lo reemplaza', () => {
        const serie = construirSerieTemporal(contrato);
        // 31-ene: sin actas todavía
        expect(serie[0].financiero).toBe(0);
        // 28-feb: 200M / 1000M = 20%
        expect(serie[1].financiero).toBe(20);
        // 31-mar: (200M + 300M) / 1000M = 50% (no 30%)
        expect(serie[2].financiero).toBe(50);
    });

    it('incluye la brecha en cada punto', () => {
        const serie = construirSerieTemporal(contrato);
        expect(serie[2].brecha).toBe(-20); // 50 financiero − 70 físico
    });

    it('devuelve arreglo vacío si el contrato no tiene valor vigente', () => {
        expect(construirSerieTemporal({ ...contrato, valor: 0 })).toEqual([]);
        expect(construirSerieTemporal({ ...contrato, valor: null })).toEqual([]);
    });

    it('devuelve arreglo vacío si no hay ninguna fecha de corte', () => {
        expect(construirSerieTemporal({ valor: '1000000000.00', actividades: [], avancesFinancieros: [] })).toEqual([]);
    });
});
```

- [ ] **Step 2: Ejecutar para verificar que fallan**

Run: `npm test`
Expected: FAIL — `construirSerieTemporal is not a function`.

- [ ] **Step 3: Implementar la función**

Agregar al final de `resources/js/utils/avanceContratos.js`:

```js
// Normaliza una fecha (que puede venir como '2026-01-31' o ISO completo) a 'YYYY-MM-DD'.
const soloFecha = (valor) => (valor ? String(valor).slice(0, 10) : null);

// Reconstruye la evolución de ambos avances a lo largo del contrato.
// Devuelve [{ fecha, fisico, financiero, brecha }] ordenado por fecha ascendente.
export const construirSerieTemporal = (contrato) => {
    const valorVigente = numero(contrato?.valor);
    if (!valorVigente) return [];

    const actividades = contrato.actividades || [];
    const actas = contrato.avancesFinancieros || [];

    // Fechas de corte: todas las fechas donde algo cambió, deduplicadas y ordenadas.
    const fechas = [
        ...actividades.flatMap((act) => (act.avances || []).map((a) => soloFecha(a.fecha))),
        ...actas.map((acta) => soloFecha(acta.fecha_acta)),
    ].filter(Boolean);

    const cortes = [...new Set(fechas)].sort();
    if (cortes.length === 0) return [];

    return cortes.map((corte) => {
        // Físico: suma ponderada del último avance de cada actividad hasta el corte.
        const fisico = actividades.reduce((suma, act) => {
            const avancesHasta = (act.avances || [])
                .filter((a) => soloFecha(a.fecha) <= corte)
                .sort((a, b) => soloFecha(a.fecha).localeCompare(soloFecha(b.fecha)));

            const ultimo = avancesHasta.length ? numero(avancesHasta[avancesHasta.length - 1].porcentaje_ejecucion) : 0;
            return suma + (numero(act.peso) / 100) * ultimo;
        }, 0);

        // Financiero: acumulado de lo facturado hasta el corte sobre el valor vigente.
        const facturadoAcumulado = actas
            .filter((acta) => soloFecha(acta.fecha_acta) <= corte)
            .reduce((suma, acta) => suma + numero(acta.valor_facturado), 0);

        const financiero = (facturadoAcumulado / valorVigente) * 100;

        const redondear = (n) => Math.round(n * 100) / 100;
        return {
            fecha: corte,
            fisico: redondear(fisico),
            financiero: redondear(financiero),
            brecha: redondear(financiero - fisico),
        };
    });
};
```

- [ ] **Step 4: Ejecutar para verificar que pasan**

Run: `npm test`
Expected: PASS todos.

- [ ] **Step 5: Commit**

```bash
git add resources/js/utils/avanceContratos.js resources/js/utils/avanceContratos.test.js
git commit -m "feat: add contract progress time series calculation"
```

---

## Task 5: Componente ContratosPanel — KPIs, scatter y tabla

**Files:**
- Create: `resources/js/components/ContratosPanel.jsx`

**Contexto:** sigue el patrón de `Estadisticas.jsx` — componente funcional que recibe `proyectos` como prop, constantes arriba, Recharts con `ResponsiveContainer`. Las clases CSS reutilizan las existentes del dashboard donde aplique; los estilos específicos van inline para no crear un archivo CSS nuevo en esta tarea.

- [ ] **Step 1: Crear el componente**

Crear `resources/js/components/ContratosPanel.jsx`:

```jsx
import React, { useState, useMemo } from 'react';
import {
    ResponsiveContainer, ScatterChart, Scatter, XAxis, YAxis, ZAxis,
    CartesianGrid, Tooltip, Legend, ReferenceLine,
    LineChart, Line,
} from 'recharts';
import { aplanarContratos, construirSerieTemporal, COLOR_SEVERIDAD } from '../utils/avanceContratos';

const formatearMoneda = (valor) => '$' + Math.round((valor || 0) / 1_000_000).toLocaleString('es-CO') + ' M';

const ETIQUETA_SEVERIDAD = {
    sano: 'Sano (≤10)',
    atencion: 'Atención (11-25)',
    critico: 'Crítico (>25)',
};

const TooltipScatter = ({ active, payload }) => {
    if (!active || !payload || !payload.length) return null;
    const c = payload[0].payload;
    return (
        <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 6, padding: '8px 10px', fontSize: 12 }}>
            <strong>{c.n_contrato}</strong><br />
            <span style={{ color: '#6b7280' }}>{c.nombreProyecto}</span><br />
            Físico: {c.avanceFisico}%<br />
            Financiero: {c.avanceFinanciero}%<br />
            <span style={{ color: COLOR_SEVERIDAD[c.severidad], fontWeight: 700 }}>
                Desfase: {c.desfase > 0 ? '+' : ''}{c.desfase}
            </span>
        </div>
    );
};

const ContratosPanel = ({ proyectos }) => {
    const [soloCriticos, setSoloCriticos] = useState(false);
    const [contratoSeleccionado, setContratoSeleccionado] = useState(null);

    const contratos = useMemo(() => aplanarContratos(proyectos), [proyectos]);

    const conDatos = contratos.filter(c => !c.sinDatos);
    const sinDatos = contratos.filter(c => c.sinDatos);

    const totalValor = contratos.reduce((s, c) => s + c.valorNumerico, 0);
    const promedioFisico = conDatos.length
        ? Math.round(conDatos.reduce((s, c) => s + c.avanceFisico, 0) / conDatos.length)
        : 0;
    const criticos = conDatos.filter(c => c.severidad === 'critico');

    const porSeveridad = ['sano', 'atencion', 'critico'].map(sev => ({
        severidad: sev,
        datos: conDatos.filter(c => c.severidad === sev),
    })).filter(g => g.datos.length > 0);

    const filasTabla = [...(soloCriticos ? criticos : conDatos)].sort((a, b) => b.desfase - a.desfase);

    const serie = contratoSeleccionado ? construirSerieTemporal(contratoSeleccionado) : [];

    if (contratos.length === 0) {
        return <div className="contratos-panel"><p>No hay contratos registrados.</p></div>;
    }

    return (
        <div className="contratos-panel">

            <div className="contratos-kpis" style={{ display: 'flex', gap: 12, marginBottom: 16, flexWrap: 'wrap' }}>
                <div style={{ flex: 1, minWidth: 140, background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 12 }}>
                    <div style={{ fontSize: 11, color: '#6b7280', textTransform: 'uppercase' }}>Contratos</div>
                    <div style={{ fontSize: 22, fontWeight: 700 }}>{contratos.length}</div>
                </div>
                <div style={{ flex: 1, minWidth: 140, background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 12 }}>
                    <div style={{ fontSize: 11, color: '#6b7280', textTransform: 'uppercase' }}>Valor total</div>
                    <div style={{ fontSize: 22, fontWeight: 700 }}>{formatearMoneda(totalValor)}</div>
                </div>
                <div style={{ flex: 1, minWidth: 140, background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 12 }}>
                    <div style={{ fontSize: 11, color: '#6b7280', textTransform: 'uppercase' }}>Avance físico prom.</div>
                    <div style={{ fontSize: 22, fontWeight: 700, color: '#2563eb' }}>{promedioFisico}%</div>
                </div>
                <div style={{ flex: 1, minWidth: 140, background: '#fff', border: `1px solid ${criticos.length ? '#fca5a5' : '#e5e7eb'}`, borderRadius: 8, padding: 12 }}>
                    <div style={{ fontSize: 11, color: '#6b7280', textTransform: 'uppercase' }}>Con desfase alto</div>
                    <div style={{ fontSize: 22, fontWeight: 700, color: criticos.length ? '#dc2626' : '#111827' }}>{criticos.length}</div>
                </div>
            </div>

            <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 14, marginBottom: 16 }}>
                <h3 style={{ fontSize: 14, margin: '0 0 8px' }}>Avance Físico vs Financiero</h3>
                {conDatos.length === 0 ? (
                    <p style={{ fontSize: 13, color: '#6b7280' }}>Ningún contrato tiene avances registrados todavía.</p>
                ) : (
                    <ResponsiveContainer width="100%" height={340}>
                        <ScatterChart margin={{ top: 10, right: 20, bottom: 20, left: 10 }}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis type="number" dataKey="avanceFisico" name="Físico" domain={[0, 100]}
                                label={{ value: 'Avance Físico %', position: 'insideBottom', offset: -10, fontSize: 12 }} />
                            <YAxis type="number" dataKey="avanceFinanciero" name="Financiero" domain={[0, 100]}
                                label={{ value: 'Avance Financiero %', angle: -90, position: 'insideLeft', fontSize: 12 }} />
                            <ZAxis range={[90, 90]} />
                            <ReferenceLine segment={[{ x: 0, y: 0 }, { x: 100, y: 100 }]} stroke="#94a3b8" strokeDasharray="5 5" />
                            <Tooltip content={<TooltipScatter />} />
                            <Legend />
                            {porSeveridad.map(({ severidad, datos }) => (
                                <Scatter
                                    key={severidad}
                                    name={ETIQUETA_SEVERIDAD[severidad]}
                                    data={datos}
                                    fill={COLOR_SEVERIDAD[severidad]}
                                    onClick={(punto) => setContratoSeleccionado(punto)}
                                    cursor="pointer"
                                />
                            ))}
                        </ScatterChart>
                    </ResponsiveContainer>
                )}
            </div>

            <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 14 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
                    <h3 style={{ fontSize: 14, margin: 0 }}>Detalle de contratos</h3>
                    <div style={{ display: 'flex', gap: 6 }}>
                        <button type="button" onClick={() => setSoloCriticos(false)}
                            style={{ fontSize: 11, padding: '4px 11px', borderRadius: 11, border: 'none', cursor: 'pointer', background: soloCriticos ? '#f3f4f6' : '#2563eb', color: soloCriticos ? '#6b7280' : '#fff' }}>
                            Todos
                        </button>
                        <button type="button" onClick={() => setSoloCriticos(true)}
                            style={{ fontSize: 11, padding: '4px 11px', borderRadius: 11, border: 'none', cursor: 'pointer', background: soloCriticos ? '#dc2626' : '#f3f4f6', color: soloCriticos ? '#fff' : '#6b7280' }}>
                            Solo críticos ({criticos.length})
                        </button>
                    </div>
                </div>

                <table style={{ width: '100%', fontSize: 12, borderCollapse: 'collapse' }}>
                    <thead>
                        <tr style={{ borderBottom: '2px solid #e5e7eb', color: '#6b7280', fontSize: 11, textTransform: 'uppercase' }}>
                            <th style={{ textAlign: 'left', padding: '6px 4px' }}>Contrato</th>
                            <th style={{ textAlign: 'left', padding: '6px 4px' }}>Proyecto</th>
                            <th style={{ padding: '6px 4px' }}>Valor</th>
                            <th style={{ padding: '6px 4px' }}>Físico</th>
                            <th style={{ padding: '6px 4px' }}>Financiero</th>
                            <th style={{ padding: '6px 4px' }}>Desfase</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filasTabla.map(c => (
                            <tr key={c.id}
                                onClick={() => setContratoSeleccionado(c)}
                                style={{
                                    borderBottom: '1px solid #f3f4f6', cursor: 'pointer',
                                    background: contratoSeleccionado?.id === c.id ? '#eff6ff' : (c.severidad === 'critico' ? '#fef2f2' : 'transparent'),
                                }}>
                                <td style={{ padding: '7px 4px', fontWeight: 600 }}>{c.n_contrato}</td>
                                <td style={{ padding: '7px 4px', color: '#6b7280' }}>{c.nombreProyecto}</td>
                                <td align="center">{formatearMoneda(c.valorNumerico)}</td>
                                <td align="center">{c.avanceFisico}%</td>
                                <td align="center">{c.avanceFinanciero}%</td>
                                <td align="center">
                                    <span style={{
                                        background: c.severidad === 'critico' ? '#fecaca' : c.severidad === 'atencion' ? '#fed7aa' : '#dcfce7',
                                        color: c.severidad === 'critico' ? '#991b1b' : c.severidad === 'atencion' ? '#9a3412' : '#166534',
                                        padding: '2px 9px', borderRadius: 10, fontWeight: 700,
                                    }}>
                                        {c.desfase > 0 ? '+' : ''}{c.desfase}
                                    </span>
                                </td>
                            </tr>
                        ))}
                        {sinDatos.map(c => (
                            <tr key={c.id} style={{ borderBottom: '1px solid #f3f4f6', color: '#9ca3af' }}>
                                <td style={{ padding: '7px 4px', fontWeight: 600 }}>{c.n_contrato}</td>
                                <td style={{ padding: '7px 4px' }}>{c.nombreProyecto}</td>
                                <td align="center">{formatearMoneda(c.valorNumerico)}</td>
                                <td align="center" colSpan="3" style={{ fontStyle: 'italic' }}>Sin datos de avance</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {contratoSeleccionado && (
                <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 14, marginTop: 16 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                        <h3 style={{ fontSize: 14, margin: 0 }}>
                            Evolución de {contratoSeleccionado.n_contrato}
                            <span style={{ color: '#6b7280', fontWeight: 400 }}> — {contratoSeleccionado.nombreProyecto}</span>
                        </h3>
                        <button type="button" onClick={() => setContratoSeleccionado(null)}
                            style={{ border: 'none', background: 'transparent', cursor: 'pointer', fontSize: 18, color: '#6b7280' }}>×</button>
                    </div>
                    {serie.length < 2 ? (
                        <p style={{ fontSize: 13, color: '#6b7280' }}>Datos insuficientes para graficar la evolución.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={280}>
                            <LineChart data={serie} margin={{ top: 10, right: 20, bottom: 10, left: 0 }}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="fecha" fontSize={11} />
                                <YAxis domain={[0, 100]} fontSize={11} />
                                <Tooltip formatter={(valor, nombre) => [`${valor}%`, nombre]} />
                                <Legend />
                                <Line type="monotone" dataKey="fisico" name="Físico" stroke="#2563eb" strokeWidth={2.5} dot={{ r: 3 }} />
                                <Line type="monotone" dataKey="financiero" name="Financiero" stroke="#16a34a" strokeWidth={2.5} dot={{ r: 3 }} />
                            </LineChart>
                        </ResponsiveContainer>
                    )}
                </div>
            )}

        </div>
    );
};

export default ContratosPanel;
```

- [ ] **Step 2: Verificar que compila**

Run: `npx mix`
Expected: `Compiled Successfully` sin errores.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/ContratosPanel.jsx public/js/app.js public/mix-manifest.json
git commit -m "feat: add ContratosPanel with scatter, table and evolution chart"
```

---

## Task 6: Integrar la pestaña en el Dashboard

**Files:**
- Modify: `resources/js/components/Dashboard.jsx`

- [ ] **Step 1: Importar el componente**

En `resources/js/components/Dashboard.jsx`, junto a los demás imports de componentes (después de `import ProximosEventos from './ProximosEventos';`), agregar:

```jsx
import ContratosPanel from './ContratosPanel';
```

- [ ] **Step 2: Agregar el botón de la pestaña**

En el bloque `tabs-header`, después del botón de "Estadísticas y Filtros", agregar:

```jsx
                            <button
                                className={`tab-button ${activeTab === 'contratos' ? 'active' : ''}`}
                                onClick={() => handleTabChange('contratos')}
                            >
                                Contratos
                            </button>
```

- [ ] **Step 3: Renderizar el panel**

En el bloque `tab-content`, después de la línea `{activeTab === 'estadisticas' && <Estadisticas proyectos={proyectos} />}`, agregar:

```jsx
                            {activeTab === 'contratos' && <ContratosPanel proyectos={proyectos} />}
```

- [ ] **Step 4: Compilar**

Run: `npx mix`
Expected: `Compiled Successfully`.

- [ ] **Step 5: Verificación manual en el navegador**

1. Abrir el dashboard e ir a la pestaña "Contratos".
2. Verificar los 4 KPIs (contratos, valor total, avance físico promedio, con desfase alto).
3. Verificar el scatter: puntos coloreados por severidad, diagonal punteada, tooltip al pasar el cursor.
4. Hacer clic en un punto → aparece la gráfica de evolución de ese contrato.
5. Hacer clic en una fila de la tabla → mismo efecto, y la fila queda resaltada.
6. Probar el filtro "Solo críticos".
7. Verificar que los contratos sin avances aparecen al final en gris con "Sin datos de avance" y NO como puntos en el scatter.
8. Verificar que un contrato con menos de 2 fechas de corte muestra "Datos insuficientes para graficar la evolución".

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/Dashboard.jsx public/js/app.js public/mix-manifest.json
git commit -m "feat: add Contratos tab to dashboard"
```

---

## Cierre

- [ ] **Suite backend completa:** `php artisan test` → todo verde.
- [ ] **Suite frontend:** `npm test` → todo verde.
- [ ] **Revisar el diff completo** de la rama antes de integrar.
- [ ] Confirmar que las pestañas existentes (fases, Estadísticas) siguen funcionando igual.
