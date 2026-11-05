import React, { useState, useEffect, useMemo } from 'react';
import { Link } from 'react-router-dom';
import axios from 'axios';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faArrowRight, faCalendarDays, faClock } from '@fortawesome/free-solid-svg-icons';

const parseFechaLocal = (fechaStr) => {
    const [anio, mes, dia] = fechaStr.split('-').map(Number);
    return new Date(anio, mes - 1, dia);
};

const fechaLegible = (fechaStr) => parseFechaLocal(fechaStr).toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });

const ProximosEventos = ({ limite = 5 }) => {
    const [eventos, setEventos] = useState(null);
    const [fallo, setFallo] = useState(false);

    useEffect(() => {
        let vigente = true;
        axios.get('/eventos')
            .then((response) => { if (vigente) setEventos(response.data); })
            // Es un panel secundario: lo dice sin romper el tablero, y no lo disfraza de "no hay eventos".
            .catch(() => { if (vigente) setFallo(true); });
        return () => { vigente = false; };
    }, []);

    const proximosEventos = useMemo(() => {
        if (!eventos) return [];
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);

        return eventos
            .filter((e) => e.estado !== 'cancelado' && e.estado !== 'completado' && parseFechaLocal(e.fecha) >= hoy)
            .sort((a, b) => parseFechaLocal(a.fecha) - parseFechaLocal(b.fecha))
            .slice(0, limite);
    }, [eventos, limite]);

    return (
        <aside className="tb-tarjeta tb-eventos-tarjeta gp-panel">
            <h3 className="tb-titulo-icono ambar"><FontAwesomeIcon icon={faClock} /> Próximos eventos</h3>
            {fallo && <p className="gp-vacio" role="alert">No se pudieron cargar los eventos.</p>}
            {!fallo && eventos === null && <p className="gp-vacio" role="status">Cargando…</p>}
            {!fallo && eventos !== null && proximosEventos.length === 0 && <p className="gp-vacio">No hay eventos próximos.</p>}
            {proximosEventos.length > 0 && (
                <ul className="tb-eventos">
                    {proximosEventos.map((evento) => (
                        <li key={evento.id}>
                            <FontAwesomeIcon icon={faCalendarDays} />
                            <span className="tb-evento-texto">
                                <span className="tb-evento-fecha">{fechaLegible(evento.fecha)}</span>
                                <span className="tb-evento-titulo">{evento.titulo}</span>
                                <span className="tb-evento-detalle">{evento.descripcion_proyecto}</span>
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            <Link to="/parametros/eventos" className="tb-ver-mas">Ver calendario <FontAwesomeIcon icon={faArrowRight} /></Link>
        </aside>
    );
};

export default ProximosEventos;
