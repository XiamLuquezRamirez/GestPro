// Sesión: el token JWT dura JWT_TTL minutos. Cuando una petición recibe 401, se renueva una
// sola vez (POST /refrescar-token acepta un token vencido dentro de JWT_REFRESH_TTL) y se repite
// la petición. Si ya no se puede renovar, la sesión termina y el login lo explica.

export const AVISO_SESION_EXPIRADA = 'Tu sesión expiró. Vuelve a iniciar sesión.';
export const EVENTO_SESION_EXPIRADA = 'gestpro:sesion-expirada';
const CLAVE_AVISO = 'gestpro:aviso-sesion';

/**
 * ¿Se debe intentar renovar la sesión ante este error? Solo un 401, con sesión, y nunca la propia
 * renovación ni el inicio de sesión (`_sinRenovar`) ni una petición que ya se repitió (`_renovada`).
 */
export const debeRenovar = (error, haySesion) => {
    const config = error?.config ?? {};
    return Boolean(haySesion)
        && error?.response?.status === 401
        && !config._sinRenovar
        && !config._renovada;
};

/**
 * Envuelve la función que renueva el token para que las peticiones que fallan a la vez compartan
 * una sola renovación: el servidor anula el token anterior, así que una segunda renovación con
 * él fallaría.
 */
export const crearRenovador = (refrescar) => {
    let enCurso = null;
    return () => {
        if (!enCurso) {
            enCurso = Promise.resolve()
                .then(refrescar)
                .finally(() => { enCurso = null; });
        }
        return enCurso;
    };
};

/** Deja el aviso para el login: sobrevive a que la pantalla cambie. */
export const guardarAvisoSesion = (almacen = window.sessionStorage) => {
    try {
        almacen.setItem(CLAVE_AVISO, 'expirada');
    } catch {
        // Sin almacenamiento, el login no mostrará el aviso; la sesión termina igual.
    }
};

/** El aviso de sesión expirada, si lo hay, y lo borra para que no se repita. */
export const tomarAvisoSesion = (almacen = window.sessionStorage) => {
    try {
        if (almacen.getItem(CLAVE_AVISO) !== 'expirada') return null;
        almacen.removeItem(CLAVE_AVISO);
        return AVISO_SESION_EXPIRADA;
    } catch {
        return null;
    }
};
