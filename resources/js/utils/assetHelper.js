/**
 * Función para obtener la URL correcta de los assets
 * @param {string} path - Ruta relativa del asset (ej: 'images/logo.png')
 * @returns {string} - URL completa del asset
 */
export const getAssetUrl = (path) => {
    const baseUrl = process.env.NODE_ENV === 'production' 
        ? 'https://ingeer.co/GestPro/public'
        : 'http://localhost:8000';
    
    return `${baseUrl}/${path}`;
};

/**
 * Función para obtener la URL correcta de las imágenes
 * @param {string} imagePath - Ruta relativa de la imagen (ej: 'images/logo.png')
 * @returns {string} - URL completa de la imagen
 */
export const getImageUrl = (imagePath) => {
    const baseUrl = process.env.NODE_ENV === 'production' 
        ? 'https://ingeer.co/GestPro/public'
        : 'http://localhost:8000';
    
    return `${baseUrl}/${imagePath}`;
}; 