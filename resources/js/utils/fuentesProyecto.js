// Lógica del selector de fuentes del formulario de proyecto. Las reglas definitivas
// están en el backend (FuentesProyecto); aquí solo se evita enviar lo que ya se sabe inválido.

const mismoId = (a, b) => String(a) === String(b);

/** Activas, más las inactivas que el proyecto ya tiene, para no obligar a quitarlas. */
export const fuentesDisponibles = (fuentes, seleccionadas) =>
    fuentes.filter((f) => f.activo || seleccionadas.some((id) => mismoId(id, f.id)));

/** Marcar o desmarcar una fuente siempre quita el "por definir". */
export const alternarFuente = ({ fuenteIds }, id) => ({
    fuenteIds: fuenteIds.some((actual) => mismoId(actual, id))
        ? fuenteIds.filter((actual) => !mismoId(actual, id))
        : [...fuenteIds, id],
    financiacionPorDefinir: false,
});

export const financiacionCompleta = ({ fuenteIds, financiacionPorDefinir }) =>
    Boolean(financiacionPorDefinir) || fuenteIds.length > 0;

export const datosFinanciacionDe = (proyecto) => ({
    fuenteIds: proyecto.fuente_ids ?? [],
    financiacionPorDefinir: Boolean(proyecto.financiacion_por_definir),
});

/** Texto en español para mostrar al usuario cuando falla una petición. */
export const mensajeDeError = (error, porDefecto = 'Inténtelo de nuevo.') => {
    if (error?.response?.status === 403) {
        return 'No tiene permiso para realizar esta acción.';
    }
    const datos = error?.response?.data;
    return [datos?.mensaje ?? datos?.message ?? porDefecto, ...(datos?.errores ?? [])].join(' ');
};
