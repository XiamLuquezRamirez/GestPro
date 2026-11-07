import Swal from 'sweetalert2';
import { mensajeApi } from './plantillasChecklist';

/** Aviso de error con el texto escapado por Swal (nunca se usa `title` con datos del usuario). */
export const avisarError = (titulo, error) => Swal.fire({ icon: 'error', titleText: titulo, text: mensajeApi(error) });
