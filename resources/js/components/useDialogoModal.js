import { useEffect } from 'react';
import { indiceFocoAtrapado } from '../utils/contratacion';

const ENFOCABLES = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Comportamiento de un diálogo modal sobre `caja`: el foco entra al abrir, Tab no sale de él y,
 * al cerrar, vuelve a donde estaba. Devuelve el `onKeyDown` de la caja: Escape llama a `onEscape`.
 */
const useDialogoModal = (caja, onEscape) => {
    useEffect(() => {
        const anterior = document.activeElement;
        caja.current?.focus();
        return () => {
            if (anterior instanceof HTMLElement && document.contains(anterior)) anterior.focus();
        };
    }, [caja]);

    return (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            onEscape();
            return;
        }
        if (e.key !== 'Tab') return;
        const focos = [...caja.current.querySelectorAll(ENFOCABLES)].filter((el) => el.getClientRects().length > 0);
        const destino = indiceFocoAtrapado(focos.indexOf(document.activeElement), focos.length, e.shiftKey);
        if (destino === null) return;
        e.preventDefault();
        (focos[destino] ?? caja.current).focus();
    };
};

export default useDialogoModal;
