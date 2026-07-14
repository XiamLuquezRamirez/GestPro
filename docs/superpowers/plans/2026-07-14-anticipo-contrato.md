# Anticipo del contrato y cálculo automático de amortización — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir indicar si un contrato lleva anticipo y su porcentaje, y usar esos datos para calcular automáticamente (de solo lectura) la amortización y el valor presente de cada acta de avance financiero.

**Architecture:** Migración que agrega `porcentaje_anticipo` a `contratos`; el backend expone y persiste `anticipo`/`porcentaje_anticipo` (la columna `anticipo` ya existía sin usarse); el frontend agrega un checkbox + input de porcentaje al formulario del contrato, y calcula `amortizacion_50`/`valor_presente_acta` en el momento en que el usuario escribe `valor_facturado` en el formulario de una nueva acta, dejando esos dos campos de solo lectura.

**Tech Stack:** Laravel 12 (PHP 8.2) + MySQL + `DB::table()` query builder, React 18 (sin TypeScript, sin Inertia), Laravel Mix. Build: `npm run dev` (no existe `npm run build`).

**Spec de referencia:** `docs/superpowers/specs/2026-07-10-anticipo-contrato-design.md`

**Nota sobre una adaptación del spec:** el spec original ubicaba el cálculo automático dentro de `handleActaFinancieraChange`, que en ese momento era el único handler del campo `valor_facturado`. Después de escribir el spec (pero antes de este plan) se commiteó por separado un fix de formato de moneda que introdujo `handleChangeValorFacturado` como handler dedicado de ese campo. Este plan calcula la amortización ahí, que es el punto real donde hoy se procesa el cambio de `valor_facturado` — cumple la misma intención del spec sin tocar `handleActaFinancieraChange` (que sigue manejando `descripcion`/`fecha_acta`/`porcentaje_ejecutado`/`archivo`).

---

### Task 1: Migración de `porcentaje_anticipo` y modelo `Contrato`

**Files:**
- Create: `database/migrations/2026_07_14_120000_add_porcentaje_anticipo_to_contratos_table.php`
- Modify: `app/Models/Contrato.php:13-17`
- Modify: `tests/Feature/Schema/ContratosTest.php:23-37`

- [ ] **Step 1: Modificar el test existente para exigir `porcentaje_anticipo`**

En `tests/Feature/Schema/ContratosTest.php`, reemplaza el método `test_contrato_casts_valor_and_anticipo_correctly`:

```php
    public function test_contrato_casts_valor_and_anticipo_correctly(): void
    {
        $proyecto = $this->crearProyecto();

        $contrato = Contrato::create([
            'proyecto' => $proyecto->id,
            'n_contrato' => 'C-001',
            'valor' => 25000000,
            'anticipo' => true,
            'porcentaje_anticipo' => 30,
        ]);

        $fresco = $contrato->fresh();
        $this->assertSame('25000000.00', $fresco->valor);
        $this->assertTrue($fresco->anticipo);
        $this->assertSame(30, $fresco->porcentaje_anticipo);
    }
```

- [ ] **Step 2: Ejecutar el test y confirmar que falla**

Run: `php artisan test --filter test_contrato_casts_valor_and_anticipo_correctly`
Expected: FAIL — columna `porcentaje_anticipo` no existe en `contratos` (error SQL "Unknown column") o, si la columna existiera sin `$fillable`, `assertSame(30, null)` fallaría.

- [ ] **Step 3: Crear la migración**

Crea `database/migrations/2026_07_14_120000_add_porcentaje_anticipo_to_contratos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->unsignedTinyInteger('porcentaje_anticipo')->nullable()->after('anticipo');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('porcentaje_anticipo');
        });
    }
};
```

- [ ] **Step 4: Agregar `porcentaje_anticipo` a `$fillable` en `Contrato.php`**

En `app/Models/Contrato.php`, el array `$fillable` actual es:

```php
    protected $fillable = [
        'proyecto', 'n_contrato', 'objeto', 'contratante', 'contratista', 'valor',
        'fecha_inicio', 'fecha_fin', 'interventoria', 'avance_fisico', 'avance_financiero',
        'estado', 'anticipo', 'proceso_licitacion',
    ];
```

Reemplázalo por:

```php
    protected $fillable = [
        'proyecto', 'n_contrato', 'objeto', 'contratante', 'contratista', 'valor',
        'fecha_inicio', 'fecha_fin', 'interventoria', 'avance_fisico', 'avance_financiero',
        'estado', 'anticipo', 'porcentaje_anticipo', 'proceso_licitacion',
    ];
```

- [ ] **Step 5: Ejecutar la migración**

Run: `php artisan migrate`
Expected: la migración `2026_07_14_120000_add_porcentaje_anticipo_to_contratos_table` se aplica sin errores.

- [ ] **Step 6: Ejecutar el test y confirmar que pasa**

Run: `php artisan test --filter test_contrato_casts_valor_and_anticipo_correctly`
Expected: PASS

- [ ] **Step 7: Ejecutar toda la suite para descartar regresiones**

Run: `php artisan test`
Expected: todos los tests pasan (40 tests: los 39 previos + éste, que ya existía y solo se amplió).

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_07_14_120000_add_porcentaje_anticipo_to_contratos_table.php app/Models/Contrato.php tests/Feature/Schema/ContratosTest.php
git commit -m "feat: add porcentaje_anticipo column to contratos"
```

---

### Task 2: Backend — exponer y guardar `anticipo`/`porcentaje_anticipo`

**Files:**
- Modify: `app/Http/Controllers/ProyectoController.php:56` (método `proyectos()`)
- Modify: `app/Http/Controllers/ProyectoController.php:358-370` (método `guardarContrato()`)

- [ ] **Step 1: Agregar las columnas al `select()` de `proyectos()`**

En `app/Http/Controllers/ProyectoController.php:56`, la línea actual es:

```php
            $contratos = DB::table('contratos')
                ->select('id', 'n_contrato', 'objeto', 'valor', 'estado', 'contratante', 'contratista', 'fecha_inicio', 'fecha_fin', 'interventoria', 'avance_financiero', 'avance_fisico')
                ->where('proyecto', $proyecto->id)
                ->get();
```

Reemplázala por:

```php
            $contratos = DB::table('contratos')
                ->select('id', 'n_contrato', 'objeto', 'valor', 'estado', 'contratante', 'contratista', 'fecha_inicio', 'fecha_fin', 'interventoria', 'avance_financiero', 'avance_fisico', 'anticipo', 'porcentaje_anticipo')
                ->where('proyecto', $proyecto->id)
                ->get();
```

(`listarContratos()`, en la línea 969, no usa `select()` explícito — ya trae todas las columnas, no necesita cambios.)

- [ ] **Step 2: Agregar `anticipo`/`porcentaje_anticipo` al payload de `guardarContrato()`**

En `app/Http/Controllers/ProyectoController.php:358-370`, el array actual es:

```php
        $contratoPayload = [
            'n_contrato' => $formContrato['n_contrato'],
            'objeto' => $formContrato['objeto'],
            'contratante' => $nullableString($formContrato['contratante'] ?? null),
            'contratista' => $nullableString($formContrato['contratista'] ?? null),
            'valor' => $formContrato['valor'],
            'fecha_inicio' => $nullableDate($formContrato['fecha_inicio'] ?? null),
            'fecha_fin' => $nullableDate($formContrato['fecha_fin'] ?? null),
            'interventoria' => $nullableString($formContrato['interventoria'] ?? null),
            'avance_financiero' => $nullableInt($formContrato['avance_financiero'] ?? null),
            'avance_fisico' => $nullableInt($formContrato['avance_fisico'] ?? null),
            'estado' => $formContrato['estado'],
        ];
```

Reemplázalo por:

```php
        $contratoPayload = [
            'n_contrato' => $formContrato['n_contrato'],
            'objeto' => $formContrato['objeto'],
            'contratante' => $nullableString($formContrato['contratante'] ?? null),
            'contratista' => $nullableString($formContrato['contratista'] ?? null),
            'valor' => $formContrato['valor'],
            'fecha_inicio' => $nullableDate($formContrato['fecha_inicio'] ?? null),
            'fecha_fin' => $nullableDate($formContrato['fecha_fin'] ?? null),
            'interventoria' => $nullableString($formContrato['interventoria'] ?? null),
            'avance_financiero' => $nullableInt($formContrato['avance_financiero'] ?? null),
            'avance_fisico' => $nullableInt($formContrato['avance_fisico'] ?? null),
            'estado' => $formContrato['estado'],
            'anticipo' => (bool) ($formContrato['anticipo'] ?? false),
            'porcentaje_anticipo' => $nullableInt($formContrato['porcentaje_anticipo'] ?? null),
        ];
```

- [ ] **Step 3: Verificar que no hay regresiones**

Run: `php artisan test`
Expected: todos los tests pasan (no hay tests de ruta para `guardarContrato()`/`proyectos()`, así que este paso solo confirma que no se rompió nada de esquema/modelo).

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/ProyectoController.php
git commit -m "feat: persist and expose anticipo/porcentaje_anticipo on contratos"
```

---

### Task 3: Frontend — estado del formulario y `handleContratoChange` compatible con checkbox

**Files:**
- Modify: `resources/js/components/Parametros.jsx:230-244` (estado inicial `formContrato`)
- Modify: `resources/js/components/Parametros.jsx:258-261` (`handleContratoChange`)
- Modify: `resources/js/components/Parametros.jsx:615-623` (`handleAddContrato`)
- Modify: `resources/js/components/Parametros.jsx:634-648` (`handleEditContrato`)
- Modify: `resources/js/components/Parametros.jsx:714-722` (`handleSaveContrato`, reset tras guardar)
- Modify: `resources/js/components/Parametros.jsx:730-738` (`handleCancelContratoForm`)
- Modify: `resources/js/components/Parametros.jsx:756-763` (`handleAddNewContrato`)

- [ ] **Step 1: Agregar `anticipo`/`porcentaje_anticipo` al estado inicial**

En `resources/js/components/Parametros.jsx:230-244`, el estado inicial actual es:

```jsx
    const [formContrato, setFormContrato] = useState({
        n_contrato: '',
        objeto: '',
        contratante: '',
        contratista: '',
        valor: '',
        fecha_inicio: '',
        fecha_fin: '',
        interventoria: '',
        avance: '',
        avance_financiero: '',
        avance_fisico: '',
        estado: '',
        proyecto: ''
    });
```

Reemplázalo por:

```jsx
    const [formContrato, setFormContrato] = useState({
        n_contrato: '',
        objeto: '',
        contratante: '',
        contratista: '',
        valor: '',
        fecha_inicio: '',
        fecha_fin: '',
        interventoria: '',
        avance: '',
        avance_financiero: '',
        avance_fisico: '',
        estado: '',
        proyecto: '',
        anticipo: false,
        porcentaje_anticipo: ''
    });
```

- [ ] **Step 2: Hacer que `handleContratoChange` maneje checkboxes**

En `resources/js/components/Parametros.jsx:258-261`, el handler actual es:

```jsx
    const handleContratoChange = (e) => {
        const { name, value } = e.target;
        setFormContrato(prev => ({ ...prev, [name]: value }));
    };
```

Reemplázalo por:

```jsx
    const handleContratoChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormContrato(prev => ({ ...prev, [name]: type === 'checkbox' ? checked : value }));
    };
```

- [ ] **Step 3: Agregar los campos a los 4 puntos de reseteo que NO cargan un contrato existente**

En `resources/js/components/Parametros.jsx`, hay tres bloques literales de reseteo (`handleAddContrato` en la línea 615, el reseteo dentro de `handleSaveContrato` en la línea 714, `handleCancelContratoForm` en la línea 730, y `handleAddNewContrato` en la línea 756). Los cuatro tienen esta forma (con variaciones de espaciado):

```jsx
        setFormContrato({
            n_contrato: '', objeto: '', contratante: '',
            contratista: '', valor: '', fecha_inicio: '',
            fecha_fin: '', interventoria: '',
            avance_financiero: '',
            avance_fisico: '', estado: '',
            proyecto: proyectoContratos.id || ''
        });
```

En cada uno de los 4 lugares, agrega `anticipo: false, porcentaje_anticipo: ''` antes del cierre `});`. Por ejemplo, `handleAddContrato` (línea 615-623) queda:

```jsx
        setFormContrato({
            n_contrato: '', objeto: '',
            contratante: '', contratista: '',
            valor: '', fecha_inicio: '',
            fecha_fin: '', interventoria: '',
            avance_financiero: '',
            avance_fisico: '', estado: '',
            proyecto: proyectoContratos.id || '',
            anticipo: false, porcentaje_anticipo: ''
        });
```

Y de igual manera para los otros 3 (`handleSaveContrato` línea ~714, `handleCancelContratoForm` línea ~730, `handleAddNewContrato` línea ~756): agrega `anticipo: false, porcentaje_anticipo: ''` al final del objeto, antes de `});`.

- [ ] **Step 4: Cargar `anticipo`/`porcentaje_anticipo` en `handleEditContrato`**

En `resources/js/components/Parametros.jsx:634-648`, el literal actual es:

```jsx
        setFormContrato({
            id: contrato.id || '',
            n_contrato: contrato.n_contrato || '',
            objeto: contrato.objeto || '',
            contratante: contrato.contratante || '',
            contratista: contrato.contratista || '',
            valor: contrato.valor ? formatCurrency(Number(contrato.valor)) : '',
            fecha_inicio: contrato.fecha_inicio || '',
            fecha_fin: contrato.fecha_fin || '',
            interventoria: contrato.interventoria || '',
            avance_financiero: contrato.avance_financiero || '',
            avance_fisico: contrato.avance_fisico || '',
            estado: contrato.estado || '',
            proyecto: contrato.proyecto || ''
        });
```

Reemplázalo por:

```jsx
        setFormContrato({
            id: contrato.id || '',
            n_contrato: contrato.n_contrato || '',
            objeto: contrato.objeto || '',
            contratante: contrato.contratante || '',
            contratista: contrato.contratista || '',
            valor: contrato.valor ? formatCurrency(Number(contrato.valor)) : '',
            fecha_inicio: contrato.fecha_inicio || '',
            fecha_fin: contrato.fecha_fin || '',
            interventoria: contrato.interventoria || '',
            avance_financiero: contrato.avance_financiero || '',
            avance_fisico: contrato.avance_fisico || '',
            estado: contrato.estado || '',
            proyecto: contrato.proyecto || '',
            anticipo: !!contrato.anticipo,
            porcentaje_anticipo: contrato.porcentaje_anticipo ?? ''
        });
```

(`contrato.anticipo` llega del backend como `0`/`1` vía `DB::table()`, no como booleano de Eloquent — `!!contrato.anticipo` lo normaliza a `true`/`false` en JS.)

- [ ] **Step 5: Compilar y verificar manualmente**

Run: `npm run dev`
Expected: "Compiled successfully" sin errores.

- [ ] **Step 6: Ejecutar la suite backend para descartar regresiones**

Run: `php artisan test`
Expected: todos los tests pasan (este task es puramente frontend, no debería afectar tests backend, pero se verifica igual).

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/Parametros.jsx
git commit -m "feat: track anticipo/porcentaje_anticipo in contract form state"
```

---

### Task 4: Frontend — checkbox y campo de porcentaje en la pestaña "Información del Contrato"

**Files:**
- Modify: `resources/js/components/Parametros.jsx:2464` (JSX de la pestaña "Información del Contrato")
- Modify: `resources/css/Parametros.css:680` (estilos)

- [ ] **Step 1: Agregar la fila del checkbox y el porcentaje**

En `resources/js/components/Parametros.jsx`, dentro de `contratoActiveTab === 'informacion'`, el bloque termina así (línea 2456-2465):

```jsx
                                                    <div className="form-group">
                                                        <label htmlFor="avance_fisico">Avance Físico</label>
                                                        <div className="avance-fisico-container">
                                                            <input type="text" id="avance_fisico" disabled name="avance_fisico" value={formContrato.avance_fisico} onChange={handleContratoChange} />
                                                            <button type="button" className="btn-calcular-avance" title='Calcular Avance Físico' onClick={handleCalcularAvanceFisico}><FontAwesomeIcon icon={faCalculator} /></button>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>
```

Reemplázalo por (agrega una nueva `form-row` antes del cierre de `contrato-form`):

```jsx
                                                    <div className="form-group">
                                                        <label htmlFor="avance_fisico">Avance Físico</label>
                                                        <div className="avance-fisico-container">
                                                            <input type="text" id="avance_fisico" disabled name="avance_fisico" value={formContrato.avance_fisico} onChange={handleContratoChange} />
                                                            <button type="button" className="btn-calcular-avance" title='Calcular Avance Físico' onClick={handleCalcularAvanceFisico}><FontAwesomeIcon icon={faCalculator} /></button>
                                                        </div>
                                                    </div>

                                                </div>
                                                <div className="form-row">
                                                    <div className="form-group form-group-checkbox">
                                                        <label htmlFor="anticipo">
                                                            <input
                                                                type="checkbox"
                                                                id="anticipo"
                                                                name="anticipo"
                                                                checked={formContrato.anticipo}
                                                                onChange={handleContratoChange}
                                                            />
                                                            ¿Lleva anticipo?
                                                        </label>
                                                    </div>
                                                    <div className="form-group">
                                                        <label htmlFor="porcentaje_anticipo">% de Anticipo</label>
                                                        <input
                                                            type="number"
                                                            id="porcentaje_anticipo"
                                                            name="porcentaje_anticipo"
                                                            value={formContrato.porcentaje_anticipo}
                                                            onChange={handleContratoChange}
                                                            min="0"
                                                            max="100"
                                                            disabled={!formContrato.anticipo}
                                                        />
                                                    </div>
                                                </div>
                                            </div>
```

- [ ] **Step 2: Agregar los estilos del checkbox**

En `resources/css/Parametros.css`, después de la regla `.form-group input[type="color"]` (termina en la línea 680, justo antes de `.modal-actions`), agrega:

```css
.form-group-checkbox label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.form-group-checkbox input[type="checkbox"] {
    width: auto;
}
```

- [ ] **Step 3: Compilar**

Run: `npm run dev`
Expected: "Compiled successfully" sin errores.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Parametros.jsx resources/css/Parametros.css
git commit -m "feat: add anticipo checkbox and percentage field to contract form"
```

---

### Task 5: Frontend — cálculo automático de amortización y valor presente en la acta

**Files:**
- Modify: `resources/js/components/Parametros.jsx:356-380` (handlers de moneda de la acta)
- Modify: `resources/js/components/Parametros.jsx:2600-2623` (JSX de los campos de la acta)

- [ ] **Step 1: Reemplazar `handleChangeValorFacturado` y eliminar los handlers de amortización/valor presente**

En `resources/js/components/Parametros.jsx:356-380`, el código actual es:

```jsx
    const handleChangeValorFacturado = (e) => {
        const { value } = e.target;
        const raw = value.replace(/\D/g, '');
        setFormActaFinanciera(prev => ({
            ...prev,
            valor_facturado: raw ? formatCurrency(raw) : ''
        }));
    };

    const handleChangeAmortizacion50 = (e) => {
        const { value } = e.target;
        const raw = value.replace(/\D/g, '');
        setFormActaFinanciera(prev => ({
            ...prev,
            amortizacion_50: raw ? formatCurrency(raw) : ''
        }));
    };
    const handleChangeValorPresenteActa = (e) => {
        const { value } = e.target;
        const raw = value.replace(/\D/g, '');
        setFormActaFinanciera(prev => ({
            ...prev,
            valor_presente_acta: raw ? formatCurrency(raw) : ''
        }));
    };
```

Reemplázalo por (el valor facturado ahora también dispara el cálculo de amortización y valor presente, usando `anticipo`/`porcentaje_anticipo` del contrato que se está editando; los otros dos handlers dejan de existir porque sus inputs pasan a ser de solo lectura):

```jsx
    const handleChangeValorFacturado = (e) => {
        const { value } = e.target;
        const raw = value.replace(/\D/g, '');
        const valorFacturado = raw ? parseInt(raw, 10) : 0;
        const porcentajeAnticipo = parseInt(formContrato.porcentaje_anticipo, 10) || 0;
        const amortizacion = formContrato.anticipo ? Math.round(valorFacturado * (porcentajeAnticipo / 100)) : 0;
        const valorPresente = valorFacturado - amortizacion;
        setFormActaFinanciera(prev => ({
            ...prev,
            valor_facturado: raw ? formatCurrency(raw) : '',
            amortizacion_50: raw ? amortizacion : '',
            valor_presente_acta: raw ? valorPresente : ''
        }));
    };
```

- [ ] **Step 2: Hacer de solo lectura los inputs de amortización y valor presente**

En `resources/js/components/Parametros.jsx:2600-2623`, el JSX actual es:

```jsx
                                                    <div className="form-group">
                                                        <label htmlFor="amortizacion_50">Amortización 50%</label>
                                                        <input type="text" 
                                                        id="amortizacion_50" 
                                                        name="amortizacion_50" 
                                                        value={formActaFinanciera.amortizacion_50}  
                                                         onChange={handleChangeAmortizacion50} 
                                                         inputMode="numeric"
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
                                                        value={formActaFinanciera.valor_presente_acta} 
                                                        onChange={handleChangeValorPresenteActa} 
                                                        inputMode="numeric"
                                                        placeholder="$ 0"
                                                        />
                                                    </div>
```

Reemplázalo por:

```jsx
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
```

- [ ] **Step 3: Compilar**

Run: `npm run dev`
Expected: "Compiled successfully" sin errores. Confirma que no quedan referencias colgantes a `handleChangeAmortizacion50`/`handleChangeValorPresenteActa` (el build fallaría con "is not defined" si algo quedó sin actualizar).

- [ ] **Step 4: Ejecutar la suite backend para descartar regresiones**

Run: `php artisan test`
Expected: todos los tests pasan.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/Parametros.jsx
git commit -m "feat: auto-calculate amortizacion and valor presente from valor facturado"
```

---

### Task 6: Verificación manual end-to-end

**Files:** ninguno (solo verificación, sin cambios de código salvo que se encuentre un bug real).

- [ ] **Step 1: Levantar un servidor de prueba**

Run: `php -S 127.0.0.1:8050 -t public` (en segundo plano, en el worktree).

- [ ] **Step 2: Ejecutar un script de Playwright que cubra el flujo completo**

Guarda y ejecuta un script en el directorio scratchpad (login con `admin@gestpro.local` / `Admin123!`) que:
1. Abra el modal de gestión de contratos de un proyecto y edite un contrato.
2. En "Información del Contrato": marque "¿Lleva anticipo?", ponga `% de Anticipo = 30`, guarde el contrato.
3. Reabra el contrato y confirme que el checkbox sigue marcado y el porcentaje sigue en 30 (persistencia).
4. Vaya a la pestaña "Avance Financiero" y en "Agregar Nueva Acta" escriba `Valor Facturado = 1.000.000`.
5. Confirme que "Amortización (30%)" muestra `$ 300.000` y "Valor Presente del Acta" muestra `$ 700.000`, y que ambos inputs están deshabilitados.
6. Guarde el acta y confirme que la tabla de actas registradas muestra esos mismos valores.
7. Repita el flujo con un contrato SIN anticipo marcado y confirme que la amortización calculada es `$ 0` y el valor presente iguala al valor facturado.

Expected: todo lo anterior se cumple sin errores de consola ni de red (```response.status < 400```).

- [ ] **Step 3: Detener el servidor de prueba**

Termina el proceso de `php -S 127.0.0.1:8050 -t public`.

- [ ] **Step 4: Si se encontró algún bug real durante la verificación, corregirlo y volver a este Task hasta que el flujo completo pase.**

---

## Tras completar todas las tareas

Dispatch de un subagente de revisión final de rama completa, y luego usar `superpowers:finishing-a-development-branch` para cerrar la rama `feature/anticipo-contrato`.
