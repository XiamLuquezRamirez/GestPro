// Lógica de presentación del módulo Contratos (/contratos): filtros en la URL, filas de la lista,
// opciones de los selectores de proyecto, rutas y la vista de los contratos de la empresa en
// SECOP II (la vista en la URL, el NIT recordado y el estado de "Importar"). Funciones puras, probadas en
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
