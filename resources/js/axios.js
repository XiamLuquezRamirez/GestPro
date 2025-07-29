import axios from 'axios';

// Base URL según entornoclear
axios.defaults.baseURL =
    process.env.NODE_ENV === 'development'
        ? 'http://localhost:8000/GestPro/'
        : 'https://ingeer.co/GestPro/';

// Incluir credenciales en cross-origin si lo necesitas
axios.defaults.withCredentials = false; // ⚠️ false para JWT

// Agregar token JWT automáticamente si está guardado
axios.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

export default axios;       
