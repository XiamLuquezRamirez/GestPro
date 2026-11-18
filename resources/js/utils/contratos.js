// Lógica de presentación del formulario de contratos: estados, formulario, lo que se envía a
// /guardarContrato, el proceso del contrato y su precarga, y los valores vigentes.
// Funciones puras, probadas en contratos.test.js. Las reglas las valida el backend
// (ContratoController y ContratosService); aquí se adelantan los mismos mensajes.

import { erroresPorCampo, etiquetaEstadoProceso, etiquetaPlataforma, numeroProceso, valorDesdeApi, valorParaApi } from './contratacion';

// Los valores son las constantes de Contrato (contratos.test.js lo comprueba).
export const ESTADOS_CONTRATO = [
    ['celebrado', 'Celebrado'],
    ['en_ejecucion', 'En ejecución'],
    ['suspendido', 'Suspendido'],
    ['terminado', 'Terminado'],
    ['liquidado', 'Liquidado'],
    ['anulado', 'Anulado'],
];

/** La etiqueta del estado; un texto que no es de la lista se muestra tal cual. */
export const etiquetaEstadoContrato = (valor) => ESTADOS_CONTRATO.find(([v]) => v === valor)?.[1] ?? (valor || '—');

// Estados de un proceso al que se puede vincular un contrato nuevo (ProcesoLicitacion::ADMITEN_CONTRATOS).
const ADMITEN_CONTRATOS = ['adjudicado', 'celebrado'];

/** El formulario vacío. Sus claves son los campos de la API, en el orden del formulario. */
export const CONTRATO_VACIO = {
    proyecto: '',
    proceso_id: '',
    tipo_contrato_id: '',
    n_contrato: '',
    objeto: '',
    contratante: '',
    contratante_nit: '',
    contratista: '',
    contratista_identificacion: '',
    supervisor: '',
    interventoria: '',
    numero_rp: '',
    valor_inicial: '',
    fecha_inicio: '',
    fecha_fin_inicial: '',
    fecha_suscripcion: '',
    fecha_rp: '',
    fecha_acta_inicio: '',
    estado: '',
    anticipo: false,
    porcentaje_anticipo: '',
    avance_fisico: '',
    avance_financiero: '',
};

export const CAMPOS_CONTRATO = Object.keys(CONTRATO_VACIO);

const CAMPOS_TEXTO = [
    'n_contrato', 'objeto', 'contratante', 'contratante_nit', 'contratista',
    'contratista_identificacion', 'supervisor', 'interventoria', 'numero_rp',
];
const CAMPOS_ID = ['proyecto', 'proceso_id', 'tipo_contrato_id'];
const CAMPOS_FECHA = ['fecha_inicio', 'fecha_fin_inicial', 'fecha_suscripcion', 'fecha_rp', 'fecha_acta_inicio'];

// Largos máximos de ContratoController::reglas().
const MAXIMOS = {
    n_contrato: 100,
    objeto: 5000,
    contratante: 5000,
    contratante_nit: 30,
    contratista: 5000,
    contratista_identificacion: 30,
    supervisor: 255,
    interventoria: 5000,
    numero_rp: 40,
};

// La columna es decimal(15, 2): 13 cifras enteras y 2 decimales.
const CIFRAS_ENTERAS = 13;

const vacio = (valor) => valor === null || valor === undefined || String(valor).trim() === '';
const comoTexto = (valor) => (valor === null || valor === undefined ? '' : String(valor));
// Las fechas del listado pueden traer la hora ("2026-01-10 00:00:00"): el input date quiere el día.
const comoFecha = (valor) => (vacio(valor) ? '' : String(valor).slice(0, 10));
const esActivo = (valor) => valor === true || valor === 1 || valor === '1';

export const contratoVacio = (proyectoId = null) => ({ ...CONTRATO_VACIO, proyecto: comoTexto(proyectoId) });

/**
 * El formulario de un contrato, del listado (columnas crudas: `proceso_licitacion`, `anticipo`
 * 1/0) o de presentar. Un contrato sin valor ni fecha fin iniciales toma los vigentes.
 */
export const formularioContrato = (contrato) => {
    if (!contrato) return contratoVacio();
    const formulario = { id: contrato.id };
    CAMPOS_TEXTO.forEach((campo) => { formulario[campo] = comoTexto(contrato[campo]); });
    CAMPOS_FECHA.forEach((campo) => { formulario[campo] = comoFecha(contrato[campo]); });
    return {
        ...formulario,
        proyecto: comoTexto(contrato.proyecto),
        proceso_id: comoTexto(contrato.proceso_id ?? contrato.proceso_licitacion ?? contrato.proceso?.id),
        tipo_contrato_id: comoTexto(contrato.tipo_contrato_id ?? contrato.tipo_contrato?.id),
        valor_inicial: valorDesdeApi(contrato.valor_inicial ?? contrato.valor),
        fecha_fin_inicial: comoFecha(contrato.fecha_fin_inicial ?? contrato.fecha_fin),
        estado: comoTexto(contrato.estado),
        anticipo: esActivo(contrato.anticipo),
        porcentaje_anticipo: comoTexto(contrato.porcentaje_anticipo),
        avance_fisico: contrato.avance_fisico ?? '',
        avance_financiero: contrato.avance_financiero ?? '',
    };
};

const avance = (valor) => {
    if (vacio(valor)) return null;
    const numero = Number(String(valor).replace(',', '.'));
    return Number.isFinite(numero) ? Math.min(100, Math.max(0, numero)) : null;
};

/**
 * Lo que espera `formContrato` de /guardarContrato. Se envían todos los campos: un opcional vacío
 * va como null y así se borra al editar. El valor y la fecha fin vigentes no se envían: los
 * calcula el servidor. Al editar, `contrato` es el que se abrió: van su id y su `updated_at`,
 * para que el servidor rechace el cambio si otra persona lo modificó entretanto.
 */
export const datosContrato = (formulario, contrato = null) => {
    const texto = (campo) => (vacio(formulario[campo]) ? null : String(formulario[campo]).trim());
    const datos = {};
    CAMPOS_CONTRATO.forEach((campo) => {
        if (CAMPOS_ID.includes(campo)) datos[campo] = texto(campo) === null ? null : Number(texto(campo));
        else datos[campo] = texto(campo);
    });
    const anticipo = Boolean(formulario.anticipo);
    return {
        ...(contrato ? { id: contrato.id } : {}),
        ...datos,
        valor_inicial: valorParaApi(formulario.valor_inicial),
        anticipo,
        porcentaje_anticipo: anticipo && texto('porcentaje_anticipo') !== null ? Number(texto('porcentaje_anticipo')) : null,
        avance_fisico: avance(formulario.avance_fisico),
        avance_financiero: avance(formulario.avance_financiero),
        ...(contrato ? { actualizado: contrato.updated_at ?? null } : {}),
    };
};

/** Errores del formulario por campo, con los mensajes del servidor; {} si se puede enviar. */
export const validarContrato = (formulario) => {
    const errores = {};
    const texto = (campo) => String(formulario[campo] ?? '').trim();
    if (!texto('n_contrato')) errores.n_contrato = 'Escribe el número del contrato.';
    if (!texto('objeto')) errores.objeto = 'Escribe el objeto del contrato.';
    CAMPOS_TEXTO.forEach((campo) => {
        if (!errores[campo] && texto(campo).length > MAXIMOS[campo]) {
            errores[campo] = `Admite hasta ${MAXIMOS[campo]} caracteres.`;
        }
    });
    const valor = valorParaApi(formulario.valor_inicial);
    if (valor === null) errores.valor_inicial = 'Escribe el valor inicial del contrato.';
    else if (valor.split('.')[0].length > CIFRAS_ENTERAS) {
        errores.valor_inicial = `El valor inicial admite hasta ${CIFRAS_ENTERAS} cifras enteras y 2 decimales.`;
    }
    const inicio = texto('fecha_inicio');
    const fin = texto('fecha_fin_inicial');
    if (inicio && fin && fin < inicio) errores.fecha_fin_inicial = 'La fecha de fin no puede ser anterior a la de inicio.';
    if (!texto('estado')) errores.estado = 'Elige el estado del contrato.';
    // En el orden del formulario: así se enfoca el primero.
    return Object.fromEntries(CAMPOS_CONTRATO.filter((c) => errores[c]).map((c) => [c, errores[c]]));
};

// Campos con un control en el formulario: el proyecto no lo tiene (es el de la lista abierta).
const CAMPOS_CON_CONTROL = CAMPOS_CONTRATO.filter((campo) => campo !== 'proyecto');

/**
 * Los errores de un 422 de /guardarContrato: `campos`, los que se muestran junto a su control, y
 * `otros`, los mensajes que no tienen dónde mostrarse (el proyecto, los anexos) y se avisan.
 * null si no es un 422 con errores.
 */
export const erroresGuardarContrato = (error) => erroresPorCampo(error, CAMPOS_CON_CONTROL);

/** El primer campo con error, en el orden del formulario; null si no hay. */
export const primerCampoConError = (errores) => CAMPOS_CON_CONTROL.find((campo) => errores?.[campo]) ?? null;

// ---------- Proceso del contrato ----------

const MAXIMO_OBJETO_OPCION = 60;

const etiquetaProceso = (proceso) => {
    const codigo = String(proceso.codigo_proceso ?? '').trim() || 'Sin número';
    const objeto = String(proceso.objeto ?? '').trim();
    const corto = objeto.length > MAXIMO_OBJETO_OPCION ? `${objeto.slice(0, MAXIMO_OBJETO_OPCION)}…` : objeto;
    return `${codigo}${corto ? ` · ${corto}` : ''} (${etiquetaEstadoProceso(proceso.estado)})`;
};

/**
 * Opciones del select de proceso: "Sin proceso", los del proyecto que admiten contratos
 * (adjudicados o celebrados) y el que el contrato ya tiene (`actual`, su resumen), aunque haya
 * cambiado de estado o la lista no haya cargado.
 */
export const opcionesProceso = (procesos, actual) => {
    const idActual = actual?.id === undefined || actual?.id === null ? null : String(actual.id);
    const opciones = (procesos ?? [])
        .filter((p) => ADMITEN_CONTRATOS.includes(p.estado) || String(p.id) === idActual)
        .map((p) => ({ valor: String(p.id), etiqueta: etiquetaProceso(p) }));
    if (idActual !== null && !opciones.some((o) => o.valor === idActual)) {
        opciones.push({ valor: idActual, etiqueta: etiquetaProceso(actual) });
    }
    return [{ valor: '', etiqueta: 'Sin proceso' }, ...opciones];
};

/** El proceso elegido en el select: de la lista del proyecto o, si no está, el del contrato. */
export const procesoElegido = (procesos, actual, procesoId) => {
    if (vacio(procesoId)) return null;
    const id = String(procesoId);
    return (procesos ?? []).find((p) => String(p.id) === id)
        ?? (actual && String(actual.id) === id ? actual : null);
};

/** Lo que se muestra de solo lectura del proceso elegido. */
export const datosDelProceso = (proceso) => {
    // numeroProceso ya descarta los enlaces que no son http(s).
    const { enlace } = numeroProceso(proceso);
    return {
        numero: String(proceso?.codigo_proceso ?? '').trim() || '—',
        enlace,
        modalidad: proceso?.modalidad?.nombre || '—',
        plataforma: etiquetaPlataforma(proceso?.plataforma),
    };
};

/** R4: si el proyecto tiene procesos y no se eligió ninguno, una nota lo recuerda; si no, null. */
export const notaProceso = (procesos, procesoId) => {
    if (!vacio(procesoId) || !(procesos ?? []).length) return null;
    return procesos.some((p) => ADMITEN_CONTRATOS.includes(p.estado))
        ? 'Este proyecto tiene procesos de contratación: si el contrato salió de uno, elígelo.'
        : 'Este proyecto tiene procesos de contratación, pero ninguno adjudicado o celebrado: cuando lo esté, podrás vincularle el contrato.';
};

/**
 * R8: la precarga del proceso (GET /procesos/{id}/precarga-contrato) llena solo los campos
 * vacíos: objeto, contratante y valor inicial. Un valor sugerido nulo o cero no se escribe.
 */
export const aplicarPrecarga = (formulario, precarga) => {
    if (!precarga) return formulario;
    const resultado = { ...formulario };
    ['objeto', 'contratante'].forEach((campo) => {
        if (vacio(resultado[campo]) && !vacio(precarga[campo])) resultado[campo] = String(precarga[campo]);
    });
    if (vacio(resultado.valor_inicial) && Number(precarga.valor_inicial) > 0) {
        resultado.valor_inicial = valorDesdeApi(precarga.valor_inicial);
    }
    return resultado;
};

// ---------- Valores vigentes ----------

/**
 * Si el valor o la fecha fin vigentes (inicial más adiciones y prórrogas) difieren de los
 * iniciales: solo entonces se muestran, de solo lectura.
 */
export const vigentesDistintos = (contrato) => {
    const distinto = (vigente, inicial, comparar) => !vacio(vigente) && !vacio(inicial) && comparar(vigente) !== comparar(inicial);
    return {
        valor: distinto(contrato?.valor, contrato?.valor_inicial, Number),
        fechaFin: distinto(contrato?.fecha_fin, contrato?.fecha_fin_inicial, comoFecha),
    };
};

/**
 * El valor vigente con el valor inicial que tiene el formulario: el inicial más las adiciones
 * que ya tiene el contrato (vigente menos inicial guardados). Es la base del avance financiero y
 * del porcentaje ejecutado de las actas.
 */
export const valorVigente = (formulario, contrato) => {
    const inicial = Number(valorParaApi(formulario?.valor_inicial) ?? 0);
    const adiciones = !vacio(contrato?.valor) && !vacio(contrato?.valor_inicial)
        ? Number(contrato.valor) - Number(contrato.valor_inicial)
        : 0;
    return Math.round((inicial + adiciones) * 100) / 100;
};

// ---------- Tipo de contrato ----------

/**
 * Opciones del select de tipo de contrato: los activos y el que el contrato ya tiene si está
 * inactivo, marcado. Con la lista sin cargar (null) se ofrece el actual para no perderlo.
 */
export const opcionesTipoContrato = (tipos, contrato) => {
    const opciones = (tipos ?? []).filter((t) => esActivo(t.activo)).map((t) => ({ valor: String(t.id), etiqueta: t.nombre }));
    const id = contrato?.tipo_contrato_id ?? contrato?.tipo_contrato?.id;
    if (!vacio(id) && !opciones.some((o) => o.valor === String(id))) {
        const enLista = (tipos ?? []).find((t) => String(t.id) === String(id));
        const nombre = enLista?.nombre ?? contrato?.tipo_contrato?.nombre ?? 'Tipo actual';
        opciones.push({ valor: String(id), etiqueta: enLista ? `${nombre} (inactivo)` : nombre });
    }
    return opciones;
};
