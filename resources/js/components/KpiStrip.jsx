import React from 'react';
import { calcularKpis } from '../utils/kpisDashboard';

// Abreviaturas para la tarjeta de distribución por fase, donde no cabe el
// nombre completo. Las fases que no estén aquí usan sus primeras 4 letras.
const ABREVIATURA_FASE = {
    'Formulación': 'Form',
    'Licitación': 'Lic',
    'Ejecución': 'Ejec',
    'Sin fase': 'S/F',
};

const abreviar = (fase) => ABREVIATURA_FASE[fase] || fase.slice(0, 4);

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
                className="kpi-tile"
                title={kpis.porFase.map(f => `${f.fase}: ${f.cantidad}`).join(' · ')}
            >
                <span className="kpi-icono">🔄</span>
                <div>
                    <div className="kpi-valor">
                        {kpis.porFase.length === 0
                            ? '—'
                            : kpis.porFase.map(f => f.cantidad).join(' · ')}
                    </div>
                    <div className="kpi-etiqueta">
                        {kpis.porFase.length === 0
                            ? 'Por fase'
                            : kpis.porFase.map(f => abreviar(f.fase)).join(' · ')}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default KpiStrip;
