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
