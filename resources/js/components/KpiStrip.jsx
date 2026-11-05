import React from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faCircleCheck, faCircleDollarToSlot, faFileCircleExclamation, faFileContract, faLayerGroup, faTriangleExclamation,
} from '@fortawesome/free-solid-svg-icons';
import { calcularKpis } from '../utils/kpisDashboard';

const Indicador = ({ icono, etiqueta, valor, detalle, titulo, tono = '', alerta = false, avance = null }) => (
    <div className={`tb-kpi ${alerta ? 'alerta' : tono}`} title={titulo}>
        <span className="tb-kpi-cabeza">
            <FontAwesomeIcon icon={icono} />
            <span>{etiqueta}</span>
        </span>
        <span className="tb-kpi-valor">{valor}</span>
        {avance !== null && <span className="tb-pista"><span style={{ width: `${Math.min(avance, 100)}%` }} /></span>}
        {detalle && <span className="tb-kpi-detalle">{detalle}</span>}
    </div>
);

const KpiStrip = ({ proyectos }) => {
    const kpis = calcularKpis(proyectos);
    const pctContratados = kpis.total > 0 ? Math.round((kpis.contratados / kpis.total) * 100) : 0;

    return (
        <section className="tb-kpis" aria-label="Indicadores globales">
            <Indicador icono={faLayerGroup} etiqueta="Proyectos" valor={kpis.total} tono="azul" detalle="En todas las fases" />
            <Indicador icono={faFileContract} etiqueta="Contratos" valor={kpis.totalContratos} tono="verde" detalle="Registrados" />
            <Indicador
                icono={faCircleCheck}
                etiqueta="Contratados"
                tono="verde"
                valor={`${pctContratados}%`}
                avance={pctContratados}
                detalle={`${kpis.contratados} de ${kpis.total} proyectos`}
                titulo="Proyectos que ya tienen contrato"
            />
            <Indicador
                icono={faCircleDollarToSlot}
                etiqueta="Ejecución financiera"
                tono="ambar"
                valor={kpis.pctEjecucion === null ? '—' : `${kpis.pctEjecucion}%`}
                avance={kpis.pctEjecucion}
                detalle="Facturado sobre contratado"
                titulo="Facturado en actas sobre el valor total contratado"
            />
            <Indicador
                icono={faTriangleExclamation}
                etiqueta="En riesgo"
                valor={kpis.enRiesgo}
                alerta={kpis.enRiesgo > 0}
                detalle="Proyectos con retraso"
            />
            <Indicador
                icono={faFileCircleExclamation}
                etiqueta="Desfase crítico"
                valor={kpis.desfaseCritico}
                alerta={kpis.desfaseCritico > 0}
                detalle="Contratos"
                titulo="Contratos donde lo facturado supera en más de 25 puntos al avance físico"
            />
        </section>
    );
};

export default KpiStrip;
