import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faArrowTurnDown, faMagnifyingGlass } from '@fortawesome/free-solid-svg-icons';
import { ICONOS } from '../utils/iconosNavegacion';
import { DESTINOS, buscarEnPaleta } from '../utils/navegacion';

/** Ctrl+K: escribir a dónde se quiere ir, sea una sección o un proyecto. */
const PaletaComandos = ({ onCerrar }) => {
    const navigate = useNavigate();
    const [consulta, setConsulta] = useState('');
    const [proyectos, setProyectos] = useState([]);
    const [indice, setIndice] = useState(0);
    const lista = useRef(null);

    useEffect(() => {
        let vigente = true;
        // Sin proyectos el buscador sigue sirviendo para ir a cualquier sección.
        axios.get('/proyectos').then(({ data }) => { if (vigente) setProyectos(data); }).catch(() => {});
        return () => { vigente = false; };
    }, []);

    const resultados = useMemo(() => buscarEnPaleta(consulta, DESTINOS, proyectos), [consulta, proyectos]);

    useEffect(() => setIndice(0), [consulta]);

    useEffect(() => {
        lista.current?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' });
    }, [indice]);

    const ir = (resultado) => {
        onCerrar();
        navigate(resultado.ruta);
    };

    const alPulsar = (e) => {
        if (e.key === 'Escape') {
            onCerrar();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            setIndice((i) => Math.min(i + 1, resultados.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setIndice((i) => Math.max(i - 1, 0));
        } else if (e.key === 'Enter' && resultados[indice]) {
            ir(resultados[indice]);
        }
    };

    return (
        <div className="gp-paleta-fondo" onClick={onCerrar}>
            <div className="gp-paleta" role="dialog" aria-modal="true" aria-label="Buscar" onClick={(e) => e.stopPropagation()}>
                <div className="gp-paleta-entrada">
                    <FontAwesomeIcon icon={faMagnifyingGlass} />
                    <input
                        autoFocus
                        type="text"
                        value={consulta}
                        onChange={(e) => setConsulta(e.target.value)}
                        onKeyDown={alPulsar}
                        placeholder="Sección, catálogo o proyecto…"
                        aria-label="Sección, catálogo o proyecto"
                        role="combobox"
                        aria-expanded="true"
                        aria-controls="gp-paleta-lista"
                    />
                    <kbd>Esc</kbd>
                </div>
                <ul className="gp-paleta-lista" id="gp-paleta-lista" role="listbox" ref={lista}>
                    {resultados.length === 0 && <li className="gp-vacio">Nada coincide con “{consulta}”.</li>}
                    {resultados.map((r, i) => (
                        <li key={r.clave} role="option" aria-selected={i === indice}>
                            <button type="button" tabIndex={-1} onClick={() => ir(r)} onMouseEnter={() => setIndice(i)}>
                                <FontAwesomeIcon icon={ICONOS[r.icono]} fixedWidth />
                                <span className="gp-paleta-titulo">{r.titulo}</span>
                                <span className="gp-paleta-detalle">{r.detalle}</span>
                                {i === indice && <FontAwesomeIcon className="gp-paleta-enter" icon={faArrowTurnDown} rotation={90} />}
                            </button>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
};

export default PaletaComandos;
