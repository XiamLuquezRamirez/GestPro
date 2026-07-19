# Cadena Presupuestal — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Agregar un bloque "Cadena presupuestal" (Planeado → Contratado → Ejecutado) en la pestaña "Estadísticas y Filtros" del dashboard, que reacciona a los filtros ya existentes.

**Architecture:** Una función pura en `resources/js/utils/cadenaPresupuestal.js` calcula los tres totales y sus porcentajes desde la lista de proyectos filtrados (que ya trae los contratos anidados con sus actas). `Estadisticas.jsx` la consume y renderiza tres barras horizontales entre la barra de filtros y la grilla de gráficas. Sin cambios en backend.

**Tech Stack:** React 18, Vitest (`npm test`), Laravel Mix (`npx mix`).

**Spec:** `docs/superpowers/specs/2026-07-18-cadena-presupuestal-design.md`

---

## Estructura de archivos

- **Crear:** `resources/js/utils/cadenaPresupuestal.js` — función pura `calcularCadenaPresupuestal(proyectos)`. Aislada del render para poder probarla.
- **Crear:** `resources/js/utils/cadenaPresupuestal.test.js` — tests Vitest de esa función.
- **Modificar:** `resources/js/components/Estadisticas.jsx` — import, llamada sobre `proyectosFiltrados`, y el bloque JSX.

**Datos de entrada** (ya los entrega `GET /GestPro/proyectos`, sin tocar backend): cada proyecto trae `presupuesto` (string decimal) y `contratos[]`, cada contrato con `valor` (string decimal, valor vigente con adiciones) y `avancesFinancieros[]` con `valor_facturado` (string decimal).

**Nota de entorno:** ejecutar desde `C:\xampp\htdocs\GestPro`. Tests JS con `npm test`; compilación con `npx mix`.

---

## Task 1: Función de cálculo `calcularCadenaPresupuestal`

**Files:**
- Create: `resources/js/utils/cadenaPresupuestal.js`
- Create: `resources/js/utils/cadenaPresupuestal.test.js`

- [ ] **Step 1: Escribir los tests que fallan**

Crear `resources/js/utils/cadenaPresupuestal.test.js` con exactamente este contenido:

```js
import { describe, it, expect } from 'vitest';
import { calcularCadenaPresupuestal } from './cadenaPresupuestal';

describe('calcularCadenaPresupuestal', () => {
    // Los valores llegan de la API como strings decimales.
    const proyectos = [
        {
            id: 1,
            presupuesto: '1000000000.00',
            contratos: [
                {
                    id: 10,
                    valor: '600000000.00',
                    avancesFinancieros: [
                        { valor_facturado: '200000000.00' },
                        { valor_facturado: '100000000.00' },
                    ],
                },
                {
                    id: 11,
                    valor: '300000000.00',
                    avancesFinancieros: [],
                },
            ],
        },
        {
            id: 2,
            presupuesto: '500000000.00',
            contratos: [],
        },
    ];

    it('suma el presupuesto planeado de todos los proyectos', () => {
        expect(calcularCadenaPresupuestal(proyectos).planeado).toBe(1500000000);
    });

    it('suma el valor de todos los contratos', () => {
        expect(calcularCadenaPresupuestal(proyectos).contratado).toBe(900000000);
    });

    it('suma lo facturado de todas las actas', () => {
        expect(calcularCadenaPresupuestal(proyectos).ejecutado).toBe(300000000);
    });

    it('un proyecto sin contratos aporta a planeado pero no a contratado ni ejecutado', () => {
        const soloPlaneado = [{ id: 3, presupuesto: '800000000.00', contratos: [] }];
        const r = calcularCadenaPresupuestal(soloPlaneado);
        expect(r.planeado).toBe(800000000);
        expect(r.contratado).toBe(0);
        expect(r.ejecutado).toBe(0);
    });

    it('un contrato sin actas aporta a contratado pero no a ejecutado', () => {
        const sinActas = [{
            id: 4, presupuesto: '100000000.00',
            contratos: [{ id: 40, valor: '90000000.00', avancesFinancieros: [] }],
        }];
        const r = calcularCadenaPresupuestal(sinActas);
        expect(r.contratado).toBe(90000000);
        expect(r.ejecutado).toBe(0);
    });

    it('calcula el porcentaje contratado sobre lo planeado, redondeado', () => {
        // 900M / 1500M = 60%
        expect(calcularCadenaPresupuestal(proyectos).pctContratado).toBe(60);
    });

    it('calcula el porcentaje ejecutado sobre lo contratado, redondeado', () => {
        // 300M / 900M = 33.33 -> 33
        expect(calcularCadenaPresupuestal(proyectos).pctEjecutado).toBe(33);
    });

    it('pctContratado es null cuando no hay planeado (no divide por cero)', () => {
        const sinPlaneado = [{
            id: 5, presupuesto: '0.00',
            contratos: [{ id: 50, valor: '100000000.00', avancesFinancieros: [] }],
        }];
        expect(calcularCadenaPresupuestal(sinPlaneado).pctContratado).toBeNull();
    });

    it('pctEjecutado es null cuando no hay contratado', () => {
        const sinContratos = [{ id: 6, presupuesto: '100000000.00', contratos: [] }];
        expect(calcularCadenaPresupuestal(sinContratos).pctEjecutado).toBeNull();
    });

    it('excedente es la diferencia cuando lo contratado supera lo planeado', () => {
        const sobreContratado = [{
            id: 7, presupuesto: '100000000.00',
            contratos: [{ id: 70, valor: '150000000.00', avancesFinancieros: [] }],
        }];
        expect(calcularCadenaPresupuestal(sobreContratado).excedente).toBe(50000000);
    });

    it('excedente es 0 cuando lo contratado no supera lo planeado', () => {
        expect(calcularCadenaPresupuestal(proyectos).excedente).toBe(0);
    });

    it('una lista vacía devuelve ceros y porcentajes nulos', () => {
        const r = calcularCadenaPresupuestal([]);
        expect(r.planeado).toBe(0);
        expect(r.contratado).toBe(0);
        expect(r.ejecutado).toBe(0);
        expect(r.pctContratado).toBeNull();
        expect(r.pctEjecutado).toBeNull();
        expect(r.excedente).toBe(0);
    });

    it('tolera entradas nulas o mal formadas sin romperse', () => {
        expect(calcularCadenaPresupuestal(null).planeado).toBe(0);
        expect(calcularCadenaPresupuestal(undefined).planeado).toBe(0);
        const raros = [{ id: 8, presupuesto: null, contratos: null }];
        expect(calcularCadenaPresupuestal(raros).planeado).toBe(0);
    });
});
```

- [ ] **Step 2: Ejecutar los tests para verificar que fallan**

Run: `npm test`
Expected: FAIL — `Cannot find module './cadenaPresupuestal'` (el archivo aún no existe). Los 23 tests existentes de `avanceContratos.test.js` siguen pasando.

- [ ] **Step 3: Escribir la implementación**

Crear `resources/js/utils/cadenaPresupuestal.js`:

```js
// Cálculo de la cadena presupuestal: Planeado → Contratado → Ejecutado.
// Función pura, probada en cadenaPresupuestal.test.js.

const numero = (valor) => {
    const n = parseFloat(valor);
    return Number.isFinite(n) ? n : 0;
};

// Porcentaje redondeado de parte sobre total. Devuelve null si el total es 0
// para no dividir por cero ni mostrar Infinity/NaN en la interfaz.
const porcentaje = (parte, total) => (total > 0 ? Math.round((parte / total) * 100) : null);

/**
 * Suma los tres niveles presupuestales de una lista de proyectos.
 *
 * - planeado:   suma de proyectos.presupuesto
 * - contratado: suma de contratos.valor (valor vigente, ya incluye adiciones)
 * - ejecutado:  suma de avancesFinancieros.valor_facturado
 *
 * Devuelve además los porcentajes de avance de la cadena y el excedente de
 * contratación sobre lo planeado (0 si no hay excedente).
 */
export const calcularCadenaPresupuestal = (proyectos) => {
    const lista = Array.isArray(proyectos) ? proyectos : [];

    let planeado = 0;
    let contratado = 0;
    let ejecutado = 0;

    for (const proyecto of lista) {
        planeado += numero(proyecto?.presupuesto);

        for (const contrato of (proyecto?.contratos || [])) {
            contratado += numero(contrato?.valor);

            for (const acta of (contrato?.avancesFinancieros || [])) {
                ejecutado += numero(acta?.valor_facturado);
            }
        }
    }

    return {
        planeado,
        contratado,
        ejecutado,
        pctContratado: porcentaje(contratado, planeado),
        pctEjecutado: porcentaje(ejecutado, contratado),
        excedente: Math.max(0, contratado - planeado),
    };
};
```

- [ ] **Step 4: Ejecutar los tests para verificar que pasan**

Run: `npm test`
Expected: PASS — 36 tests en total (23 de `avanceContratos` + 13 nuevos).

- [ ] **Step 5: Commit**

```bash
git add resources/js/utils/cadenaPresupuestal.js resources/js/utils/cadenaPresupuestal.test.js
git commit -m "feat: add budget chain calculation utility"
```

---

## Task 2: Bloque visual en `Estadisticas.jsx`

**Files:**
- Modify: `resources/js/components/Estadisticas.jsx`

**Contexto del archivo** (verificado):
- Línea 7: helper existente `formatearPresupuesto(valor)` → devuelve `'$17.012 M'`. Reutilizarlo.
- Línea 27: `const proyectosFiltrados = proyectos.filter(...)` — la lista ya filtrada que alimenta todas las gráficas.
- Línea 161: cierra `<div className="filtros-barra">`.
- Línea 163: abre `<div className="graficas-grid">`.
- El bloque nuevo va **entre** esos dos, es decir después del cierre de `filtros-barra`.

- [ ] **Step 1: Agregar el import**

En `resources/js/components/Estadisticas.jsx`, después del import de Recharts (que termina en la línea 5 con `} from 'recharts';`), agregar:

```jsx
import { calcularCadenaPresupuestal } from '../utils/cadenaPresupuestal';
```

- [ ] **Step 2: Calcular la cadena sobre los proyectos filtrados**

Justo después del bloque que define `proyectosFiltrados` (termina con `});` en la línea ~34), agregar:

```jsx
    const cadena = calcularCadenaPresupuestal(proyectosFiltrados);
    // La barra más larga define la escala visual; las otras dos se leen en proporción.
    const maxCadena = Math.max(cadena.planeado, cadena.contratado, cadena.ejecutado);
    const anchoBarra = (valor) => (maxCadena > 0 ? `${(valor / maxCadena) * 100}%` : '0%');
    const hayFiltroActivo = Boolean(filtro.municipio || filtro.fase || filtro.estado || filtro.fechaDesde || filtro.fechaHasta);
```

- [ ] **Step 3: Agregar el bloque JSX**

Insertar entre el cierre de `<div className="filtros-barra">` y la apertura de `<div className="graficas-grid">`:

```jsx
            <div className="cadena-presupuestal-card" style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 16, marginBottom: 16 }}>
                <h3 style={{ fontSize: 15, margin: '0 0 14px' }}>
                    💰 Cadena presupuestal
                    {hayFiltroActivo && (
                        <span style={{ fontWeight: 400, color: '#6b7280', fontSize: 12 }}>
                            {' '}— {proyectosFiltrados.length} proyecto(s) según los filtros
                        </span>
                    )}
                </h3>

                {proyectosFiltrados.length === 0 ? (
                    <p style={{ fontSize: 13, color: '#6b7280', margin: 0 }}>
                        Sin proyectos que coincidan con los filtros.
                    </p>
                ) : (
                    <>
                        <div style={{ marginBottom: 12 }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, marginBottom: 4 }}>
                                <span style={{ color: '#374151', fontWeight: 600 }}>📋 Planeado</span>
                                <span style={{ fontWeight: 700 }}>{formatearPresupuesto(cadena.planeado)}</span>
                            </div>
                            <div style={{ background: '#e5e7eb', borderRadius: 4, height: 22 }}>
                                <div style={{ background: '#64748b', width: anchoBarra(cadena.planeado), height: '100%', borderRadius: 4 }} />
                            </div>
                        </div>

                        <div style={{ marginBottom: 12 }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, marginBottom: 4 }}>
                                <span style={{ color: '#374151', fontWeight: 600 }}>📝 Contratado</span>
                                <span style={{ fontWeight: 700, color: '#2563eb' }}>
                                    {formatearPresupuesto(cadena.contratado)}
                                    {cadena.pctContratado !== null && (
                                        <span style={{ color: '#6b7280', fontSize: 11, fontWeight: 400 }}>
                                            {' '}({cadena.pctContratado}% de lo planeado)
                                        </span>
                                    )}
                                </span>
                            </div>
                            <div style={{ background: '#e5e7eb', borderRadius: 4, height: 22 }}>
                                <div style={{ background: '#2563eb', width: anchoBarra(cadena.contratado), height: '100%', borderRadius: 4 }} />
                            </div>
                        </div>

                        <div>
                            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, marginBottom: 4 }}>
                                <span style={{ color: '#374151', fontWeight: 600 }}>💵 Ejecutado (facturado)</span>
                                <span style={{ fontWeight: 700, color: '#16a34a' }}>
                                    {formatearPresupuesto(cadena.ejecutado)}
                                    {cadena.pctEjecutado !== null && (
                                        <span style={{ color: '#6b7280', fontSize: 11, fontWeight: 400 }}>
                                            {' '}({cadena.pctEjecutado}% de lo contratado)
                                        </span>
                                    )}
                                </span>
                            </div>
                            <div style={{ background: '#e5e7eb', borderRadius: 4, height: 22 }}>
                                <div style={{ background: '#16a34a', width: anchoBarra(cadena.ejecutado), height: '100%', borderRadius: 4 }} />
                            </div>
                        </div>

                        {cadena.excedente > 0 && (
                            <div style={{ background: '#fffbeb', borderLeft: '3px solid #f59e0b', padding: '9px 11px', marginTop: 14, fontSize: 12, color: '#78350f' }}>
                                ⚠️ Lo contratado supera lo planeado en <strong>{formatearPresupuesto(cadena.excedente)}</strong>.
                            </div>
                        )}
                    </>
                )}
            </div>
```

- [ ] **Step 4: Compilar**

Run: `npx mix`
Expected: `Compiled Successfully`, sin errores.

- [ ] **Step 5: Verificar que el bloque llegó al bundle**

Run: `grep -c "cadena-presupuestal-card" public/js/app.js`
Expected: `1` o más. Si devuelve `0`, el componente no se incluyó — detenerse y reportar.

Nota: no buscar cadenas con acentos (ej. "Cadena presupuestal" funciona, pero textos con `í`/`é` aparecen escapados en el bundle).

- [ ] **Step 6: Confirmar que los tests siguen pasando**

Run: `npm test`
Expected: 36 passed.

Run: `php artisan test`
Expected: 55 passed (esta tarea no toca backend; es una comprobación de no-regresión).

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/Estadisticas.jsx public/js/app.js public/mix-manifest.json
git commit -m "feat: show budget chain in Estadisticas tab"
```

---

## Verificación manual (la hace el coordinador, no el subagente)

1. Abrir el dashboard → pestaña "Estadísticas y Filtros".
2. Confirmar que el bloque aparece **arriba**, entre los filtros y las gráficas.
3. Sin filtros, las cifras deben ser: Planeado **$17.012 M**, Contratado **$18.072 M** (106%), Ejecutado **$3.487 M** (19%), y el aviso ámbar de excedente **$1.060 M**.
4. Filtrar por Municipio = MEDELLÍN → las tres barras se recalculan.
5. Aplicar un filtro sin resultados (ej. combinar fase y estado incompatibles) → debe mostrar "Sin proyectos que coincidan con los filtros", no barras en cero.
