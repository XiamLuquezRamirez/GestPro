import React from 'react';

const formatearPresupuesto = (valor) => '$' + Math.round(valor / 1_000_000).toLocaleString('es-CO') + ' M';

const KpiStrip = ({ proyectos }) => {
    const total = proyectos.length;

    const presupuestoTotal = proyectos.reduce((sum, p) => sum + (parseFloat(p.presupuesto) || 0), 0);
    const totalContratos = proyectos.reduce((sum, p) => sum + (p.contratos ? p.contratos.length : 0), 0);
    const enRiesgo = proyectos.filter(p => p.descripcion_estado === 'Con retraso').length;
    const avancePromedio = total > 0
        ? Math.round(proyectos.reduce((sum, p) => sum + (parseInt(p.progreso, 10) || 0), 0) / total)
        : 0;
    const finalizados = 0;

    return (
        <div className="kpi-strip">
            <div className="kpi-tile">
                <span className="kpi-icono">📊</span>
                <div>
                    <div className="kpi-valor">{total}</div>
                    <div className="kpi-etiqueta">Total proyectos</div>
                </div>
            </div>
            <div className="kpi-tile">
                <span className="kpi-icono">💰</span>
                <div>
                    <div className="kpi-valor">{formatearPresupuesto(presupuestoTotal)}</div>
                    <div className="kpi-etiqueta">Presupuesto total</div>
                </div>
            </div>
            <div className="kpi-tile">
                <span className="kpi-icono">📄</span>
                <div>
                    <div className="kpi-valor">{totalContratos}</div>
                    <div className="kpi-etiqueta">Total de contratos</div>
                </div>
            </div>
            <div className="kpi-tile kpi-tile-riesgo">
                <span className="kpi-icono">⚠️</span>
                <div>
                    <div className="kpi-valor">{enRiesgo}</div>
                    <div className="kpi-etiqueta">En riesgo</div>
                </div>
            </div>
            <div className="kpi-tile">
                <span className="kpi-icono">📈</span>
                <div>
                    <div className="kpi-valor">{avancePromedio}%</div>
                    <div className="kpi-etiqueta">Avance promedio</div>
                </div>
            </div>
            <div className="kpi-tile" title="Aún no existe un estado de tipo Finalizado en el catálogo">
                <span className="kpi-icono">✅</span>
                <div>
                    <div className="kpi-valor">{finalizados}</div>
                    <div className="kpi-etiqueta">Finalizados</div>
                </div>
            </div>
        </div>
    );
};

export default KpiStrip;
