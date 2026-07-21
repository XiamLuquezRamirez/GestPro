import React from 'react';
import { calcularKpis } from '../utils/kpisDashboard';

const KpiStrip = ({ proyectos }) => {
    const kpis = calcularKpis(proyectos);

    const pctContratados = kpis.total > 0
        ? Math.round((kpis.contratados / kpis.total) * 100)
        : 0;

    return (
        <div className="kpi-strip">
            <div className="kpi-tile">
                <span className="kpi-icono">📊</span>
                <div>
                    <div className="kpi-valor">{kpis.total}</div>
                    <div className="kpi-etiqueta">Total proyectos</div>
                </div>
            </div>

            <div className="kpi-tile">
                <span className="kpi-icono">📄</span>
                <div>
                    <div className="kpi-valor">{kpis.totalContratos}</div>
                    <div className="kpi-etiqueta">Total de contratos</div>
                </div>
            </div>

            <div className="kpi-tile kpi-tile-riesgo">
                <span className="kpi-icono">⚠️</span>
                <div>
                    <div className="kpi-valor">{kpis.enRiesgo}</div>
                    <div className="kpi-etiqueta">En riesgo</div>
                </div>
            </div>

            <div
                className="kpi-tile"
                title="Facturado en actas sobre el valor total contratado"
            >
                <span className="kpi-icono">💵</span>
                <div>
                    <div className="kpi-valor">
                        {kpis.pctEjecucion === null ? '—' : `${kpis.pctEjecucion}%`}
                    </div>
                    <div className="kpi-etiqueta">Ejecución financiera</div>
                </div>
            </div>

            <div
                className="kpi-tile"
                title={`${pctContratados}% de los proyectos ya tienen contrato`}
            >
                <span className="kpi-icono">🤝</span>
                <div>
                    <div className="kpi-valor">{kpis.contratados} / {kpis.total}</div>
                    <div className="kpi-etiqueta">Contratados</div>
                </div>
            </div>

            <div
                className={`kpi-tile${kpis.desfaseCritico > 0 ? ' kpi-tile-riesgo' : ''}`}
                title="Contratos donde lo facturado supera en más de 25 puntos al avance físico"
            >
                <span className="kpi-icono">🚨</span>
                <div>
                    <div className="kpi-valor">{kpis.desfaseCritico}</div>
                    <div className="kpi-etiqueta">Desfase crítico</div>
                </div>
            </div>
        </div>
    );
};

export default KpiStrip;
