import React, { useMemo } from 'react';

const formatearPresupuesto = (valor) => '$' + Math.round(valor / 1_000_000).toLocaleString('es-CO') + ' M';

const ResumenFase = ({ proyectos, nombreFase }) => {
    const resumen = useMemo(() => {
        const total = proyectos.length;
        const presupuestoTotal = proyectos.reduce((sum, p) => sum + (parseFloat(p.presupuesto) || 0), 0);
        const avancePromedio = total > 0
            ? Math.round(proyectos.reduce((sum, p) => sum + (parseInt(p.progreso, 10) || 0), 0) / total)
            : 0;
        const enRiesgo = proyectos.filter(p => p.descripcion_estado === 'Con retraso').length;

        return { total, presupuestoTotal, avancePromedio, enRiesgo };
    }, [proyectos]);

    return (
        <aside className="resumen-fase-panel">
            <h3>Resumen de {nombreFase}</h3>
            <div className="resumen-fase-stat">
                <span className="resumen-fase-valor">{resumen.total}</span>
                <span className="resumen-fase-etiqueta">Proyectos en {nombreFase.toLowerCase()}</span>
            </div>
            <div className="resumen-fase-stat">
                <span className="resumen-fase-valor">{formatearPresupuesto(resumen.presupuestoTotal)}</span>
                <span className="resumen-fase-etiqueta">Presupuesto total</span>
            </div>
            <div className="resumen-fase-stat">
                <span className="resumen-fase-valor">{resumen.avancePromedio}%</span>
                <div className="resumen-fase-avance-bar">
                    <div className="resumen-fase-avance-fill" style={{ width: `${resumen.avancePromedio}%` }}></div>
                </div>
                <span className="resumen-fase-etiqueta">Avance promedio</span>
            </div>
            <div className="resumen-fase-stat">
                <span className={`resumen-fase-valor${resumen.enRiesgo > 0 ? ' en-riesgo' : ''}`}>{resumen.enRiesgo}</span>
                <span className="resumen-fase-etiqueta">En riesgo</span>
            </div>
        </aside>
    );
};

export default ResumenFase;
