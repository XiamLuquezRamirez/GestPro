// Lógica de presentación del módulo de contratación: catálogos de modalidades y etapas.
// Funciones puras, probadas en contratacion.test.js. Las reglas las valida el backend; aquí se
// adelantan los mismos mensajes para no esperar un 422.

import { normalizar } from './navegacion';
import { mover } from './plantillasChecklist';

/** Modalidades y etapas se administran con gestionar-catalogos: solo un Administrador. */
export const puedeAdministrarCatalogos = (usuario) => usuario?.rol === 'Administrador';

/** Las reglas del formulario de cada catálogo, con los límites y mensajes del controlador. */
export const REGLAS_MODALIDAD = {
    maximoNombre: 199,
    faltaNombre: 'Escribe el nombre de la modalidad.',
    repetido: 'Ya existe una modalidad con ese nombre.',
};

export const REGLAS_ETAPA = {
    maximoNombre: 120,
    maximoDescripcion: 2000,
    faltaNombre: 'Escribe el nombre de la etapa.',
    repetido: 'Ya existe una etapa con ese nombre.',
};

export const CATALOGO_VACIO = { nombre: '', descripcion: '' };

export const formularioCatalogo = (item) => ({
    nombre: item?.nombre ?? '',
    descripcion: item?.descripcion ?? '',
});

/**
 * Errores del formulario por campo; {} si se puede enviar. El nombre no se repite sin contar
 * mayúsculas ni tildes, como en el servidor. `idActual` es el del registro que se edita.
 */
export const validarCatalogo = (formulario, items, idActual, reglas) => {
    const errores = {};
    const nombre = String(formulario.nombre ?? '').trim();
    if (!nombre) {
        errores.nombre = reglas.faltaNombre;
    } else if (nombre.length > reglas.maximoNombre) {
        errores.nombre = `El nombre admite hasta ${reglas.maximoNombre} caracteres.`;
    } else if ((items ?? []).some((i) => i.id !== idActual && normalizar(i.nombre) === normalizar(nombre))) {
        errores.nombre = reglas.repetido;
    }
    const descripcion = String(formulario.descripcion ?? '').trim();
    if (reglas.maximoDescripcion && descripcion.length > reglas.maximoDescripcion) {
        errores.descripcion = `La descripción admite hasta ${reglas.maximoDescripcion} caracteres.`;
    }
    return errores;
};

/** Lo que esperan /guardarModalidad y /guardarEtapaContratacion. `conDescripcion` solo en etapas. */
export const datosCatalogo = (formulario, item, conDescripcion = false) => ({
    accion: item ? 'Editar' : 'Agregar',
    ...(item ? { id: item.id } : {}),
    nombre: String(formulario.nombre ?? '').trim(),
    ...(conDescripcion ? { descripcion: String(formulario.descripcion ?? '').trim() || null } : {}),
});

/** Por orden y, a igual orden, por nombre (como las modalidades del servidor) y por id. */
export const ordenarCatalogo = (items) => [...(items ?? [])].sort((a, b) => (
    (a.orden ?? 0) - (b.orden ?? 0)
    || String(a.nombre ?? '').localeCompare(String(b.nombre ?? ''), 'es')
    || a.id - b.id
));

/** Los ids de la lista con el elemento `indice` movido una posición: lo que espera el PUT de orden. */
export const idsTrasMover = (items, indice, delta) => mover((items ?? []).map((i) => i.id), indice, delta);

/**
 * Los errores de validación de un 422 ({ errors }) repartidos: el primer mensaje de cada campo
 * del formulario y, aparte, los de otros campos. null si el error no es de validación.
 */
export const erroresPorCampo = (error, campos) => {
    const errores = error?.response?.status === 422 ? error.response.data?.errors : null;
    if (!errores || typeof errores !== 'object') return null;
    const enCampos = {};
    const otros = [];
    Object.entries(errores).forEach(([campo, mensajes]) => {
        const lista = [].concat(mensajes ?? []).map(String);
        if (campos.includes(campo)) {
            if (lista.length) enCampos[campo] = lista[0];
        } else {
            otros.push(...lista);
        }
    });
    return { campos: enCampos, otros };
};

/** Modalidades y etapas son femeninas: "Activa" o "Inactiva". */
export const textoEstadoCatalogo = (activo) => (activo ? 'Activa' : 'Inactiva');
