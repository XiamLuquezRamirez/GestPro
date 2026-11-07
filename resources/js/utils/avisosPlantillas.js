import Swal from 'sweetalert2';
import { errorConLista, htmlErrores, mensajeApi } from './plantillasChecklist';

/**
 * Aviso de error. Un 422 con lista de errores se muestra como lista; lo demás, como texto.
 * Nunca se usa `title` con datos del usuario: Swal escapa `titleText` y `text`, y `htmlErrores` escapa lo suyo.
 */
export const avisarError = (titulo, error) => {
    const lista = errorConLista(error);
    if (lista) return Swal.fire({ icon: 'error', titleText: titulo, html: htmlErrores(lista.mensaje, lista.errores) });
    return Swal.fire({ icon: 'error', titleText: titulo, text: mensajeApi(error) });
};
