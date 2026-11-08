import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faClipboardCheck, faXmark } from '@fortawesome/free-solid-svg-icons';
import { COLOR_SEVERIDAD, construirSerieTemporal, severidadDesfase } from '../utils/avanceContratos';
import { textoAvanceProyecto } from '../utils/expedientesResumen';

const moneda = (valor) => (valor || valor === 0 ? `$ ${Math.round(Number(valor)).toLocaleString('es-CO')}` : '—');

const Dato = ({ etiqueta, children }) => (
    <div className="tb-dato">
        <dt className="gp-etiqueta">{etiqueta}</dt>
        <dd>{children || '—'}</dd>
    </div>
);

const Ventana = ({ titulo, subtitulo, onCerrar, children }) => {
    useEffect(() => {
        const alPulsar = (e) => { if (e.key === 'Escape') onCerrar(); };
        window.addEventListener('keydown', alPulsar);
        return () => window.removeEventListener('keydown', alPulsar);
    }, [onCerrar]);

    return (
        <div className="tb-modal-fondo" onClick={onCerrar}>
            <div className="tb-modal" role="dialog" aria-modal="true" aria-label={titulo} onClick={(e) => e.stopPropagation()}>
                <header className="tb-modal-cabecera">
                    <div>
                        {subtitulo && <span className="gp-etiqueta">{subtitulo}</span>}
                        <h2>{titulo}</h2>
                    </div>
                    <button type="button" className="gp-icono" onClick={onCerrar} aria-label="Cerrar">
                        <FontAwesomeIcon icon={faXmark} />
                    </button>
                </header>
                {children}
            </div>
        </div>
    );
};

const DetalleContrato = ({ contrato, onCerrar }) => {
    // Avances derivados del historial (actas + avances de actividad), igual que la
    // vista Contratos: las columnas del contrato son un snapshot que puede estar desactualizado.
    const serie = construirSerieTemporal(contrato);
    const ultimo = serie[serie.length - 1];

    return (
        <Ventana titulo={`Contrato ${contrato.n_contrato || contrato.numero || contrato.nombre}`} subtitulo={contrato.estado} onCerrar={onCerrar}>
            <div className="tb-modal-cuerpo">
                <dl className="tb-datos">
                    <div className="tb-dato ancho"><dt className="gp-etiqueta">Objeto</dt><dd>{contrato.objeto || contrato.descripcion || '—'}</dd></div>
                    <Dato etiqueta="Contratante">{contrato.contratante}</Dato>
                    <Dato etiqueta="Contratista">{contrato.contratista}</Dato>
                    <Dato etiqueta="Valor">{moneda(contrato.monto ?? contrato.valor)}</Dato>
                    <Dato etiqueta="Interventoría">{contrato.interventoria || contrato.interventor}</Dato>
                    <Dato etiqueta="Inicio">{contrato.fecha_inicio || contrato.fecha}</Dato>
                    <Dato etiqueta="Fin">{contrato.fecha_fin}</Dato>
                    {ultimo ? (
                        <>
                            <Dato etiqueta="Avance físico">{ultimo.fisico}%</Dato>
                            <Dato etiqueta="Avance financiero">{ultimo.financiero}%</Dato>
                            <Dato etiqueta="Desfase">
                                <span style={{ color: COLOR_SEVERIDAD[severidadDesfase(ultimo.brecha)], fontWeight: 700 }}>
                                    {ultimo.brecha > 0 ? '+' : ''}{ultimo.brecha}
                                </span>
                            </Dato>
                        </>
                    ) : (
                        <Dato etiqueta="Avance">Sin datos registrados</Dato>
                    )}
                </dl>
            </div>
        </Ventana>
    );
};

const ModalProyecto = ({ proyecto, resumenExpedientes, onCerrar }) => {
    const navigate = useNavigate();
    const [pestana, setPestana] = useState('datos');
    const [contrato, setContrato] = useState(null);

    const componentes = proyecto.componentesPresupuesto ?? [];
    const contratos = proyecto.contratos ?? [];
    const pestanas = [
        { id: 'datos', titulo: 'Datos generales' },
        { id: 'componentes', titulo: `Componentes (${componentes.length})` },
        ...(contratos.length > 0 ? [{ id: 'contratos', titulo: `Contratos (${contratos.length})` }] : []),
    ];

    if (contrato) return <DetalleContrato contrato={contrato} onCerrar={() => setContrato(null)} />;

    return (
        <Ventana titulo={proyecto.nombre} subtitulo={`${proyecto.descripcion_municipio ?? 'Sin municipio'} · ${proyecto.descripcion_fase ?? 'Sin fase'}`} onCerrar={onCerrar}>
            <div className="tb-modal-pestanas" role="tablist">
                {pestanas.map((p) => (
                    <button
                        key={p.id}
                        type="button"
                        role="tab"
                        aria-selected={pestana === p.id}
                        className={pestana === p.id ? 'activa' : ''}
                        onClick={() => setPestana(p.id)}
                    >
                        {p.titulo}
                    </button>
                ))}
            </div>

            <div className="tb-modal-cuerpo">
                {pestana === 'datos' && (
                    <>
                        <dl className="tb-datos">
                            <div className="tb-dato ancho"><dt className="gp-etiqueta">Descripción</dt><dd>{proyecto.descripcion || '—'}</dd></div>
                            <Dato etiqueta="Estado">{proyecto.descripcion_estado}</Dato>
                            {/* El total de componentes es 0 si no se desglosó: no significa que no haya presupuesto. */}
                            <Dato etiqueta="Presupuesto">{moneda(Number(proyecto.totalPresupuesto) || proyecto.presupuesto)}</Dato>
                            <Dato etiqueta="Entidad que presenta">{proyecto.descripcion_entidad_presenta}</Dato>
                            <Dato etiqueta="Entidad que financia">{proyecto.descripcion_entidad_financia}</Dato>
                            <Dato etiqueta="Fuente de financiación">{proyecto.fuente_financiacion}</Dato>
                            <Dato etiqueta="Requisitos cumplidos">{textoAvanceProyecto(proyecto.id, resumenExpedientes)}</Dato>
                        </dl>
                        <button type="button" className="gp-boton gp-boton-primario tb-modal-accion" onClick={() => navigate(`/viabilizacion/${proyecto.id}`)}>
                            <FontAwesomeIcon icon={faClipboardCheck} /> Abrir expediente
                        </button>
                    </>
                )}

                {pestana === 'componentes' && (
                    componentes.length === 0 ? <p className="gp-vacio">No hay componentes registrados.</p> : (
                        <ul className="tb-lista-simple">
                            {componentes.map((c) => (
                                <li key={c.id}><span>{c.componente}</span><strong>{moneda(c.valor)}</strong></li>
                            ))}
                        </ul>
                    )
                )}

                {pestana === 'contratos' && (
                    <ul className="tb-lista-simple clicable">
                        {contratos.map((c) => (
                            <li key={c.id}>
                                <button type="button" onClick={() => setContrato(c)}>
                                    <span className="tb-lista-texto"><b>{c.n_contrato}</b><span>{c.objeto}</span></span>
                                    <strong>{moneda(c.valor)}</strong>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Ventana>
    );
};

export default ModalProyecto;
