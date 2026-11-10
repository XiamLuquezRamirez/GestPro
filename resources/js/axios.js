import axios from 'axios';
import { EVENTO_SESION_EXPIRADA, crearRenovador, debeRenovar, guardarAvisoSesion } from './utils/sesion';

// Base URL: siempre el mismo origen (protocolo+host+puerto) desde el que se
// cargó la página — evita depender de un puerto hardcodeado que no coincide
// con el servidor que realmente sirvió la página (causaba fallos de CSRF/
// cookies por ser un origen cruzado distinto).
//
// El prefijo '/GestPro' es el `apiPrefix` fijado en bootstrap/app.php: todas
// las rutas de routes/api.php viven ahí siempre, en cualquier entorno — no
// depende de bajo qué subdirectorio se sirvan los archivos estáticos (eso es
// lo que representa window.__APP_BASE__, un concepto distinto), así que se
// hardcodea aquí en vez de reutilizar esa variable.
axios.defaults.baseURL = window.location.origin + '/GestPro/';

// Incluir credenciales en cross-origin si lo necesitas
axios.defaults.withCredentials = true; // Cambiado a true para CSRF

// Función para obtener el token CSRF
const getCsrfToken = () => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
};

// Agregar token JWT y CSRF automáticamente
axios.interceptors.request.use((config) => {
    // Agregar token JWT si está disponible
    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    
    // Agregar token CSRF si está disponible
    const csrfToken = getCsrfToken();
    if (csrfToken) {
        config.headers['X-CSRF-TOKEN'] = csrfToken;
    }
    
    return config;
});

// Una sola renovación del token para todas las peticiones que reciban 401 a la vez.
const renovarToken = crearRenovador(async () => {
    const { data } = await axios.post('refrescar-token', null, { _sinRenovar: true });
    localStorage.setItem('token', data.token);
    return data.token;
});

// La sesión ya no se puede renovar: se cierra y el login explica por qué (App escucha el evento).
const expirarSesion = () => {
    localStorage.removeItem('token');
    delete axios.defaults.headers.common.Authorization;
    guardarAvisoSesion();
    window.dispatchEvent(new Event(EVENTO_SESION_EXPIRADA));
};

// Interceptor para manejar 401 (sesión vencida), 419 (CSRF) y 403 (sin permiso)
axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const haySesion = Boolean(localStorage.getItem('token'));
        if (debeRenovar(error, haySesion)) {
            try {
                await renovarToken();
            } catch {
                expirarSesion();
                return Promise.reject(error);
            }
            // El interceptor de petición pone el token nuevo.
            return axios({ ...error.config, _renovada: true });
        }
        // Una petición ya repetida con token nuevo que vuelve a dar 401: la sesión no sirve.
        if (haySesion && error.response?.status === 401 && error.config?._renovada) {
            expirarSesion();
        }

        if (error.response && error.response.status === 419) {
            // Recargar la página para obtener un nuevo token CSRF
            console.warn('CSRF token mismatch, recargando página...');
            window.location.reload();
        }

        if (error.response && error.response.status === 403) {
            console.warn('No tienes permiso para realizar esta acción.');
        }

        return Promise.reject(error);
    }
);

export default axios;       
