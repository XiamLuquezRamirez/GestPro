import { useCallback, useRef, useState } from 'react';

/** Ejecuta una acción a la vez: mientras corre, `ocupado` es true y otra llamada se ignora. */
const useOcupado = () => {
    const enCurso = useRef(false);
    const [ocupado, setOcupado] = useState(false);
    const ejecutar = useCallback(async (accion) => {
        if (enCurso.current) return undefined;
        enCurso.current = true;
        setOcupado(true);
        try {
            return await accion();
        } finally {
            enCurso.current = false;
            setOcupado(false);
        }
    }, []);
    return [ocupado, ejecutar];
};

export default useOcupado;
