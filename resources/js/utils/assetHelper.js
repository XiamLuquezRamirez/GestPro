// Mismo origen + subdirectorio real de la app (window.__APP_BASE__, inyectado
// por resources/views/app.blade.php) — evita depender de un host/puerto
// hardcodeado que no coincide con el servidor que realmente sirvió la página.
const getBaseUrl = () => window.location.origin + (window.__APP_BASE__ || '');

/**
 * Función para obtener la URL correcta de los assets
 * @param {string} path - Ruta relativa del asset (ej: 'images/logo.png')
 * @returns {string} - URL completa del asset
 */
export const getAssetUrl = (path) => `${getBaseUrl()}/${path}`;

/**
 * Función para obtener la URL correcta de las imágenes
 * @param {string} imagePath - Ruta relativa de la imagen (ej: 'images/logo.png')
 * @returns {string} - URL completa de la imagen
 */
export const getImageUrl = (imagePath) => `${getBaseUrl()}/${imagePath}`;