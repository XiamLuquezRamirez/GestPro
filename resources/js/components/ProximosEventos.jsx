import React, { useState, useEffect, useMemo } from 'react';
import axios from 'axios';

const MESES_CORTOS = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

const parseFechaLocal = (fechaStr) => {
    const [anio, mes, dia] = fechaStr.split('-').map(Number);
    return new Date(anio, mes - 1, dia);
};

const formatearFechaCorta = (fechaStr) => {
    const fecha = parseFechaLocal(fechaStr);
    return {
        dia: String(fecha.getDate()).padStart(2, '0'),
        mes: MESES_CORTOS[fecha.getMonth()],
    };
};

const ProximosEventos = () => {
    const [eventos, setEventos] = useState([]);

    useEffect(() => {
        axios.get('/eventos').then(response => setEventos(response.data));
    }, []);

    const proximosEventos = useMemo(() => {
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);

        return eventos
            .filter(e => e.estado !== 'cancelado' && e.estado !== 'completado' && parseFechaLocal(e.fecha) >= hoy)
            .sort((a, b) => parseFechaLocal(a.fecha) - parseFechaLocal(b.fecha))
            .slice(0, 5);
    }, [eventos]);

    return (
        <aside className="proximos-eventos-panel">
            <h3>Próximos eventos</h3>
            {proximosEventos.length === 0 ? (
                <p className="tabla-sin-datos">No hay eventos próximos.</p>
            ) : (
                <ul className="proximos-eventos-lista">
                    {proximosEventos.map(evento => {
                        const { dia, mes } = formatearFechaCorta(evento.fecha);
                        return (
                            <li key={evento.id} className="proximo-evento-item">
                                <div className="proximo-evento-fecha">
                                    <span className="proximo-evento-dia">{dia}</span>
                                    <span className="proximo-evento-mes">{mes}</span>
                                </div>
                                <div className="proximo-evento-info">
                                    <span className="proximo-evento-titulo">{evento.titulo}</span>
                                    <span className="proximo-evento-proyecto">{evento.descripcion_proyecto}</span>
                                </div>
                                {evento.descripcion_municipio && (
                                    <span className="proximo-evento-municipio">{evento.descripcion_municipio}</span>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </aside>
    );
};

export default ProximosEventos;
