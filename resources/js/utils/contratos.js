// Lógica de presentación del formulario de contratos: estados, formulario, lo que se envía a
// /guardarContrato, el proceso del contrato y su precarga, los valores vigentes y las pestañas
// del contrato (anexos, actas, actividades y modificaciones).
// Funciones puras, probadas en contratos.test.js. Las reglas las valida el backend
// (ContratoController y ContratosService); aquí se adelantan los mismos mensajes.

import { MAXIMO_OBSERVACION, erroresPorCampo, etiquetaEstadoProceso, etiquetaPlataforma, numeroProceso, valorDesdeApi, valorParaApi } from './contratacion';
import { numeroDeMoneda } from './moneda';

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

// ---------- Referencia de SECOP II ----------

/** Los campos de la referencia de SECOP II que guarda el contrato (sin control en el formulario). */
export const CAMPOS_SECOP = ['secop_id_contrato', 'secop_proceso', 'url_secop'];

/**
 * La referencia de SECOP II para /guardarContrato. Solo va si el formulario usó SECOP II
 * (`secop_consultado`, que marcan las utilidades de secop.js): entonces van los tres campos, vacío
 * como null, y `secop_consultado`, para que el servidor anote la fecha de la consulta. Si no, no
 * se envía nada y la referencia guardada no cambia, salvo que se haya desvinculado
 * (`secop_desvincular`, de desvincularSecop): entonces van los tres como null, sin consulta.
 */
export const datosSecop = (formulario) => {
    if (!formulario?.secop_consultado) {
        return formulario?.secop_desvincular ? Object.fromEntries(CAMPOS_SECOP.map((campo) => [campo, null])) : {};
    }
    return {
        ...Object.fromEntries(CAMPOS_SECOP.map((campo) => [campo, vacio(formulario[campo]) ? null : String(formulario[campo]).trim()])),
        secop_consultado: true,
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
 * para que el servidor rechace el cambio si otra persona lo modificó entretanto. La referencia de
 * SECOP II va solo si el formulario la tomó de SECOP II (datosSecop).
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
        ...datosSecop(formulario),
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

// ---------- Proyecto del contrato ----------

/**
 * El formulario tras elegir otro proyecto en el selector del módulo Contratos. El proceso era del
 * proyecto anterior y se quita; al volver al proyecto del contrato abierto (`contrato`), recupera
 * el suyo. Elegir el mismo proyecto devuelve el formulario sin cambios.
 */
export const cambiarProyecto = (formulario, proyectoId, contrato = null) => {
    const proyecto = comoTexto(proyectoId);
    if (proyecto === comoTexto(formulario.proyecto)) return formulario;
    const original = contrato ? formularioContrato(contrato) : null;
    return {
        ...formulario,
        proyecto,
        proceso_id: original && original.proyecto === proyecto ? original.proceso_id : '',
    };
};

/**
 * El proceso que el contrato abierto ya tiene (su resumen), mientras el formulario siga en el
 * proyecto del contrato; si se eligió otro proyecto, ese proceso ya no se ofrece. null si no hay.
 */
export const procesoDelContrato = (contrato, formulario) => {
    if (!contrato?.proceso) return null;
    return comoTexto(contrato.proyecto) === comoTexto(formulario?.proyecto) ? contrato.proceso : null;
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

// ---------- Pestañas del contrato ----------

// Formularios vacíos de las pestañas.
export const ANEXO_VACIO = { descripcion: '', archivo: null };
export const ACTA_VACIA = {
    descripcion: '', fecha_acta: '', valor_facturado: '', amortizacion_50: '',
    valor_presente_acta: '', porcentaje_ejecutado: '', archivo: null,
};
export const ACTIVIDAD_VACIA = { nombre: '', peso: '' };
export const AVANCE_VACIO = { fecha: '', porcentaje_ejecucion: '' };
export const MODIFICACION_VACIA = {
    numero_otrosi: '', tipo: 'adicion', valor_adicion: '',
    dias_prorroga: '', fecha_modificacion: '', justificacion: '',
};

/** Los anexos del contrato del listado (`nombre_archivo`, `ruta_archivo`) como los usa la pestaña. */
export const anexosDelContrato = (contrato) => (contrato?.anexos?.length
    ? contrato.anexos.map((anexo) => ({
        id: anexo.id,
        descripcion: anexo.descripcion,
        nombreArchivo: anexo.nombre_archivo,
        rutaArchivo: anexo.ruta_archivo,
        fecha: anexo.fecha,
    }))
    : []);

/** Los anexos como los espera `anexos` de /guardarContrato. */
export const anexosParaGuardar = (anexos) => anexos.map((anexo) => ({
    descripcion: anexo.descripcion,
    nombreArchivo: anexo.nombreArchivo,
    rutaArchivo: anexo.rutaArchivo,
    fecha: anexo.fecha,
}));

/**
 * Lo que se calcula de un acta con su valor facturado: la amortización del anticipo (si el
 * contrato lo lleva), el valor presente y el porcentaje ejecutado sobre el valor vigente.
 */
export const calculoActa = (valorFacturado, formulario, contrato) => {
    const porcentajeAnticipo = parseInt(formulario.porcentaje_anticipo, 10) || 0;
    const amortizacion = formulario.anticipo ? Math.round(valorFacturado * (porcentajeAnticipo / 100) * 100) / 100 : 0;
    const valorPresente = Math.round((valorFacturado - amortizacion) * 100) / 100;
    const vigente = valorVigente(formulario, contrato);
    const porcentajeEjecutado = vigente ? Math.round((valorFacturado / vigente) * 100 * 100) / 100 : 0;
    return { amortizacion, valorPresente, porcentajeEjecutado };
};

/** Lo que espera /guardarActaFinanciera. */
export const datosActa = (contratoId, formulario, rutaAnexo) => ({
    contrato_id: contratoId,
    descripcion: formulario.descripcion,
    fecha_acta: formulario.fecha_acta,
    valor_facturado: numeroDeMoneda(formulario.valor_facturado),
    amortizacion_50: formulario.amortizacion_50,
    valor_presente_acta: formulario.valor_presente_acta,
    porcentaje_ejecutado: formulario.porcentaje_ejecutado,
    anexo: rutaAnexo,
});

/**
 * Avance financiero: lo facturado en todas las actas sobre el valor vigente, con dos decimales;
 * sin actas o sin valor vigente, vacío.
 */
export const avanceFinancieroDeActas = (actas, vigente) => {
    if (actas.length === 0 || !vigente) return '';
    const totalFacturado = actas.reduce((suma, acta) => suma + (parseFloat(acta.valor_facturado) || 0), 0);
    return Math.round((totalFacturado / vigente) * 100 * 100) / 100;
};

/** La suma de los pesos de las actividades (deben sumar 100). */
export const sumaPesos = (actividades) => actividades.reduce((suma, a) => suma + (a.peso || 0), 0);

/** Avance físico: la suma ponderada (peso × último % de ejecución) de las actividades, redondeada. */
export const avanceFisicoDeActividades = (actividades) => Math.round(
    actividades.reduce((suma, a) => suma + ((a.peso || 0) / 100) * (a.ultimo_avance || 0), 0),
);

/**
 * La actividad tras registrarle un avance: si es el más reciente (o el primero), pasa a ser su
 * último avance; uno de una fecha anterior queda solo en el historial.
 */
export const actividadConAvance = (actividad, fecha, porcentaje) => {
    const esMasReciente = !actividad.fecha_ultimo_avance || fecha >= actividad.fecha_ultimo_avance;
    return esMasReciente
        ? { ...actividad, ultimo_avance: parseInt(porcentaje, 10), fecha_ultimo_avance: fecha }
        : actividad;
};

/** Lo que espera /guardarModificacionContrato: una prórroga no lleva valor, ni una adición días. */
export const datosModificacion = (contratoId, formulario) => ({
    contrato_id: contratoId,
    numero_otrosi: formulario.numero_otrosi,
    tipo: formulario.tipo,
    valor_adicion: formulario.tipo === 'prorroga' ? null : numeroDeMoneda(formulario.valor_adicion) || null,
    dias_prorroga: formulario.tipo === 'adicion' ? null : (formulario.dias_prorroga || null),
    fecha_modificacion: formulario.fecha_modificacion,
    justificacion: formulario.justificacion,
});

/** La etiqueta del tipo de modificación. */
export const etiquetaTipoModificacion = (tipo) => {
    if (tipo === 'adicion') return 'Adición';
    if (tipo === 'prorroga') return 'Prórroga';
    return 'Adición y Prórroga';
};

// ---------- Cierre del contrato: terminar y liquidar (fase 7A) ----------

// Estados a los que solo se llega con su acción («Terminar», «Liquidar»), no con el formulario
// (ContratosService::ESTADOS_DE_CIERRE).
const ESTADOS_DE_CIERRE = ['terminado', 'liquidado'];
// Estados desde los que se termina un contrato (CierreContratos::TERMINABLES).
const TERMINABLES = ['celebrado', 'en_ejecucion', 'suspendido', 'terminado'];
const ACCION_DE_ESTADO = { terminado: 'Terminar', liquidado: 'Liquidar' };

/** Los campos de los diálogos de terminar y liquidar, con los nombres de la API. */
export const CAMPOS_TERMINAR = ['fecha_terminacion_real', 'observacion'];
export const CAMPOS_LIQUIDAR = ['fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado', 'observacion'];

// Los mensajes de CierreContratos y ContratoController.
const MENSAJES_CIERRE = {
    sinTerminacion: 'Escribe la fecha de terminación real.',
    terminacionFutura: 'La fecha de terminación no puede ser posterior a hoy.',
    terminacionAnterior: 'La fecha de terminación no puede ser anterior a la de inicio.',
    sinLiquidacion: 'Escribe la fecha de liquidación.',
    liquidacionFutura: 'La fecha de liquidación no puede ser posterior a hoy.',
    liquidacionAnterior: 'La fecha de liquidación no puede ser anterior a la de terminación.',
    sinValor: 'Escribe el valor liquidado.',
    valorLargo: `El valor liquidado admite hasta ${CIFRAS_ENTERAS} cifras enteras y 2 decimales.`,
    observacionLarga: `La observación admite hasta ${MAXIMO_OBSERVACION} caracteres.`,
};

/**
 * Qué acciones de cierre ofrece un contrato guardado: terminar (celebrado, en ejecución,
 * suspendido o terminado, para corregir la fecha) y liquidar (cualquier estado salvo anulado;
 * liquidado, para completar o corregir), con la etiqueta de cada botón.
 */
export const accionesCierre = (contrato) => {
    const existe = !vacio(contrato?.id);
    const estado = contrato?.estado ?? null;
    return {
        terminar: existe && TERMINABLES.includes(estado),
        liquidar: existe && estado !== 'anulado',
        etiquetaTerminar: estado === 'terminado' ? 'Corregir terminación' : 'Terminar',
        etiquetaLiquidar: estado === 'liquidado' ? 'Corregir liquidación' : 'Liquidar',
    };
};

/**
 * La nota junto a los botones de cierre de un contrato guardado sin estado o con uno fuera de la
 * lista (un contrato antiguo sin convertir): no se puede terminar hasta elegir y guardar un estado
 * (el servidor lo rechaza); liquidar sí, como en el servidor. null si no aplica.
 */
export const notaSinEstadoCierre = (contrato) => {
    if (vacio(contrato?.id)) return null;
    return ESTADOS_CONTRATO.some(([valor]) => valor === contrato.estado)
        ? null
        : 'Elige un estado para el contrato y guárdalo para poder terminarlo.';
};

/**
 * El texto del aviso de datos de cierre que faltan (`aviso_cierre` de la API): "Faltan datos de
 * liquidación" si falta la fecha de liquidación o el valor; "Falta la fecha de terminación" si
 * solo falta esa. null si no falta nada.
 */
export const textoAvisoCierre = (aviso) => {
    const faltan = Array.isArray(aviso?.faltan) ? aviso.faltan : [];
    if (faltan.includes('fecha_liquidacion') || faltan.includes('valor_liquidado')) return 'Faltan datos de liquidación';
    if (faltan.includes('fecha_terminacion_real')) return 'Falta la fecha de terminación';
    return null;
};

// Un monto de la API ("1250000.50", "1000", 1500000.5) en centavos, como BigInt: así la resta es
// exacta. null si no hay monto o no se puede leer (más de dos decimales, texto).
const centavos = (monto) => {
    if (vacio(monto)) return null;
    const partes = /^(-?)(\d+)(?:\.(\d{1,2}))?$/.exec(String(monto).trim());
    if (!partes) return null;
    const valor = BigInt(partes[2]) * 100n + BigInt((partes[3] ?? '').padEnd(2, '0'));
    return partes[1] ? -valor : valor;
};

const montoDeCentavos = (valor) => {
    const absoluto = valor < 0n ? -valor : valor;
    const centimos = String(absoluto % 100n).padStart(2, '0');
    return `${valor < 0n ? '-' : ''}${absoluto / 100n}.${centimos}`;
};

/**
 * El saldo de la liquidación: el valor vigente (`valor`) menos el liquidado, con dos decimales
 * exactos ("250000.25"; negativo si lo liquidado supera lo vigente). null si falta alguno.
 */
export const saldoLiquidacion = (contrato) => {
    const vigente = centavos(contrato?.valor);
    const liquidado = centavos(contrato?.valor_liquidado);
    if (vigente === null || liquidado === null) return null;
    return montoDeCentavos(vigente - liquidado);
};

/** Si el contrato guardado muestra el bloque "Cierre": está terminado o liquidado, o tiene algún dato de cierre. */
export const muestraBloqueCierre = (contrato) => Boolean(contrato) && (
    ESTADOS_DE_CIERRE.includes(contrato.estado)
    || [contrato.fecha_terminacion_real, contrato.fecha_liquidacion, contrato.valor_liquidado].some((valor) => !vacio(valor))
);

/**
 * Las opciones del select de estado. Al editar, terminado y liquidado se deshabilitan (se llega a
 * ellos con «Terminar» y «Liquidar») salvo el estado guardado; un contrato nuevo puede llegar en
 * cualquiera (p. ej. uno de SECOP II ya liquidado).
 */
export const opcionesEstadoContrato = (estadoGuardado, esNuevo) => ESTADOS_CONTRATO.map(([valor, etiqueta]) => {
    const deshabilitado = !esNuevo && ESTADOS_DE_CIERRE.includes(valor) && valor !== estadoGuardado;
    return { valor, etiqueta: deshabilitado ? `${etiqueta} (usa «${ACCION_DE_ESTADO[valor]}»)` : etiqueta, deshabilitado };
});

/** El formulario del diálogo de terminar: la fecha guardada, si la hay. */
export const formularioTerminar = (contrato) => ({
    fecha_terminacion_real: comoFecha(contrato?.fecha_terminacion_real),
    observacion: '',
});

/** El formulario del diálogo de liquidar: los datos guardados, el valor con los miles agrupados. */
export const formularioLiquidar = (contrato) => ({
    fecha_terminacion_real: comoFecha(contrato?.fecha_terminacion_real),
    fecha_liquidacion: comoFecha(contrato?.fecha_liquidacion),
    valor_liquidado: valorDesdeApi(contrato?.valor_liquidado),
    observacion: '',
});

const observacionParaApi = (formulario) => (vacio(formulario?.observacion) ? null : String(formulario.observacion).trim());

/** Lo que espera POST /contratos/{id}/terminar, con la versión (`updated_at`) del contrato abierto. */
export const datosTerminar = (formulario, contrato) => ({
    fecha_terminacion_real: comoFecha(formulario.fecha_terminacion_real),
    observacion: observacionParaApi(formulario),
    actualizado: contrato?.updated_at ?? null,
});

/** Lo que espera POST /contratos/{id}/liquidar: el valor como número de la API ("1250000.5"). */
export const datosLiquidar = (formulario, contrato) => ({
    fecha_terminacion_real: comoFecha(formulario.fecha_terminacion_real),
    fecha_liquidacion: comoFecha(formulario.fecha_liquidacion),
    valor_liquidado: valorParaApi(formulario.valor_liquidado),
    observacion: observacionParaApi(formulario),
    actualizado: contrato?.updated_at ?? null,
});

// La fecha de terminación: obligatoria, no posterior a hoy ni anterior a la de inicio.
const errorTerminacion = (fecha, contrato, hoy) => {
    if (!fecha) return MENSAJES_CIERRE.sinTerminacion;
    if (fecha > hoy) return MENSAJES_CIERRE.terminacionFutura;
    const inicio = comoFecha(contrato?.fecha_inicio);
    if (inicio && fecha < inicio) return MENSAJES_CIERRE.terminacionAnterior;
    return null;
};

const errorObservacion = (formulario) => (
    String(formulario?.observacion ?? '').trim().length > MAXIMO_OBSERVACION ? MENSAJES_CIERRE.observacionLarga : null
);

const sinNulos = (errores) => Object.fromEntries(Object.entries(errores).filter(([, mensaje]) => mensaje));

/**
 * Errores del diálogo de terminar por campo, con los mensajes del servidor; {} si se puede
 * enviar. `hoy` es el día (año-mes-día) en la zona de contratación.
 */
export const erroresTerminar = (formulario, contrato, hoy) => sinNulos({
    fecha_terminacion_real: errorTerminacion(comoFecha(formulario?.fecha_terminacion_real), contrato, hoy),
    observacion: errorObservacion(formulario),
});

/**
 * Errores del diálogo de liquidar por campo; {} si se puede enviar. Las fechas no son posteriores
 * a `hoy`; la de terminación no es anterior a la de inicio ni la de liquidación a la de terminación.
 */
export const erroresLiquidar = (formulario, contrato, hoy) => {
    const terminacion = comoFecha(formulario?.fecha_terminacion_real);
    const liquidacion = comoFecha(formulario?.fecha_liquidacion);
    let errorLiquidacion = null;
    if (!liquidacion) errorLiquidacion = MENSAJES_CIERRE.sinLiquidacion;
    else if (liquidacion > hoy) errorLiquidacion = MENSAJES_CIERRE.liquidacionFutura;
    else if (terminacion && liquidacion < terminacion) errorLiquidacion = MENSAJES_CIERRE.liquidacionAnterior;
    const valor = valorParaApi(formulario?.valor_liquidado);
    let errorValor = null;
    if (valor === null) errorValor = MENSAJES_CIERRE.sinValor;
    else if (valor.split('.')[0].length > CIFRAS_ENTERAS) errorValor = MENSAJES_CIERRE.valorLargo;
    return sinNulos({
        fecha_terminacion_real: errorTerminacion(terminacion, contrato, hoy),
        fecha_liquidacion: errorLiquidacion,
        valor_liquidado: errorValor,
        observacion: errorObservacion(formulario),
    });
};

/**
 * Los errores de un 422 de terminar o liquidar: `campos`, los de los `campos` del diálogo, junto a
 * su control, y `general`, el primero de los demás o el `mensaje` de un 422 por estado (anulado,
 * liquidado). null si no es un 422.
 */
export const erroresCierre = (error, campos) => {
    if (error?.response?.status !== 422) return null;
    const porCampo = erroresPorCampo(error, campos);
    if (porCampo) return { campos: porCampo.campos, general: porCampo.otros[0] ?? null };
    const mensaje = error.response.data?.mensaje;
    return { campos: {}, general: typeof mensaje === 'string' && mensaje.trim() ? mensaje : null };
};

/**
 * El contrato abierto tras terminarlo o liquidarlo: del contrato presentado que responde la API
 * toma el estado, los datos de cierre, su aviso y la versión nueva; lo demás (anexos, actas…) se
 * conserva.
 */
export const contratoTrasCierre = (abierto, presentado) => ({
    ...abierto,
    estado: presentado.estado,
    fecha_terminacion_real: presentado.fecha_terminacion_real,
    fecha_liquidacion: presentado.fecha_liquidacion,
    valor_liquidado: presentado.valor_liquidado,
    aviso_cierre: presentado.aviso_cierre ?? null,
    updated_at: presentado.updated_at,
});

// Campos que no cuentan como cambio: el avance financiero se recalcula solo con las actas.
const SIN_COMPARAR = ['avance_financiero'];

/**
 * Si el formulario del contrato guardado tiene cambios sin guardar: lo que se enviaría a
 * /guardarContrato difiere de lo del contrato (sin el avance financiero, que se recalcula solo),
 * o hay anexos subidos que el contrato aún no tiene (los eliminados ya se borraron en el
 * servidor). Un contrato nuevo (sin `contrato`) no cuenta.
 */
export const cambiosSinGuardar = (formulario, contrato, anexos = []) => {
    if (!contrato) return false;
    // El valor se compara en centavos: "1500000.5" y "1500000.50" son el mismo.
    const comparable = (datos) => JSON.stringify(Object.fromEntries(Object.entries(datos)
        .filter(([campo]) => !SIN_COMPARAR.includes(campo))
        .map(([campo, valor]) => [campo, campo === 'valor_inicial' ? String(centavos(valor)) : valor])));
    if (comparable(datosContrato(formulario, contrato)) !== comparable(datosContrato(formularioContrato(contrato), contrato))) return true;
    const guardados = new Set((contrato.anexos ?? []).map((anexo) => String(anexo.id)));
    return (anexos ?? []).some((anexo) => !guardados.has(String(anexo.id)));
};

/**
 * Si SECOP II no puede cambiar el estado del contrato guardado: propone terminado o liquidado y
 * el contrato no está en ese estado (a esos estados se llega con «Terminar» y «Liquidar»). Lo
 * decide `esNuevo`, como en opcionesEstadoContrato: un contrato nuevo sí lo toma; uno guardado,
 * aunque su estado esté vacío, no.
 */
export const estadoCierreBloqueado = (estadoSecop, estadoGuardado, esNuevo = false) => (
    !esNuevo && ESTADOS_DE_CIERRE.includes(estadoSecop) && estadoSecop !== estadoGuardado
);

/**
 * El aviso de "Actualizar desde SECOP II" cuando propone terminado o liquidado y el contrato
 * guardado no lo está: qué acción usar. null si no aplica (contrato nuevo incluido), o si el
 * contrato ya está liquidado y SECOP II lo ve terminado (va detrás).
 */
export const avisoEstadoCierreSecop = (estadoSecop, estadoGuardado, esNuevo = false) => {
    if (!estadoCierreBloqueado(estadoSecop, estadoGuardado, esNuevo)) return null;
    if (estadoSecop === 'liquidado') return 'SECOP II dice «Liquidado»: usa «Liquidar» para registrarlo con sus fechas y valor.';
    if (estadoGuardado === 'liquidado') return null;
    return 'SECOP II dice «Terminado»: usa «Terminar» para registrarlo con su fecha de terminación.';
};
