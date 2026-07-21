import React, { useMemo } from 'react';
import { calcularAvanceGrupo } from '../utils/kpisDashboard';

const formatearPresupuesto = (valor) => '$' + Math.round(valor / 1_000_000).toLocaleString('es-CO') + ' M';

const ResumenFase = ({ proyectos, nombreFase }) => {
    const resumen = useMemo(() => {
        const total = proyectos.length;
        const presupuestoTotal = proyectos.reduce((sum, p) => sum + (parseFloat(p.presupuesto) || 0), 0);
        // Ejecución financiera real (facturado sobre contratado). Sin contratos
        // no hay base para medir: se muestra "—" en vez de un 0% engañoso.
        const avance = calcularAvanceGrupo(proyectos);
        const enRiesgo = proyectos.filter(p => p.descripcion_estado === 'Con retraso').length;

        return { total, presupuestoTotal, avance, enRiesgo };
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
                {resumen.avance.tieneContratos ? (
                    <>
                        <span className="resumen-fase-valor">{resumen.avance.pct}%</span>
                        <div className="resumen-fase-avance-bar">
                            <div className="resumen-fase-avance-fill" style={{ width: `${resumen.avance.pct}%` }}></div>
                        </div>
                        <span className="resumen-fase-etiqueta">Ejecución financiera</span>
                    </>
                ) : (
                    <>
                        <span className="resumen-fase-valor">—</span>
                        <span className="resumen-fase-etiqueta">Sin contratos en esta fase</span>
                    </>
                )}
            </div>
            <div className="resumen-fase-stat">
                <span className={`resumen-fase-valor${resumen.enRiesgo > 0 ? ' en-riesgo' : ''}`}>{resumen.enRiesgo}</span>
                <span className="resumen-fase-etiqueta">En riesgo</span>
            </div>
        </aside>
    );
};

export default ResumenFase;
