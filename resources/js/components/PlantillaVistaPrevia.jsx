import React, { useMemo } from 'react';
import { SIN_ETAPA, USO_CONTRATACION, agruparVistaPrevia, datosVistaPrevia, porRedactar, usoDePlantilla } from '../utils/plantillasChecklist';

/**
 * El checklist de la versión de trabajo como lo verá el usuario en un expediente. Una plantilla de
 * contratación se ve por etapa, en el orden del catálogo.
 */
const PlantillaVistaPrevia = ({ plantilla, version, requisitos }) => {
    const uso = usoDePlantilla(plantilla);
    const grupos = useMemo(() => agruparVistaPrevia(requisitos, plantilla.motor, uso), [requisitos, plantilla.motor, uso]);

    if (!version) return <p className="gp-vacio">La plantilla no tiene versiones.</p>;
    if (grupos.length === 0) return <p className="gp-vacio">La versión v{version.numero} no tiene requisitos activos.</p>;

    return (
        <div className="pl-vista">
            <p className="pl-ayuda">
                Versión v{version.numero}{version.estado === 'borrador' ? ' (borrador)' : ''}.
                {plantilla.motor === 'sgr'
                    ? ' En cada expediente, el evaluador SGR decide cuáles aplican según la fase, el sector y los componentes.'
                    : `${uso === USO_CONTRATACION ? ' Por etapa del proceso de contratación.' : ''} Los condicionados nacen "por definir" y una persona decide si aplican.`}
            </p>
            {grupos.map((g) => (
                <section key={g.clave} className="gp-panel pl-vista-grupo">
                    <h2 className="pl-grupo-titulo">
                        {g.titulo}
                        {g.inactiva && <span className="pl-insignia pl-insignia-alerta">Etapa inactiva</span>}
                        <span className="pl-ayuda">· {g.requisitos.length}</span>
                    </h2>
                    {g.clave === SIN_ETAPA && <p className="pl-aviso-suave">Elige la etapa de estos requisitos antes de publicar.</p>}
                    <ul>
                        {g.requisitos.map((r) => (
                            <li key={r.id}>
                                <input type="checkbox" disabled aria-label={`Vista previa de ${r.texto}`} />
                                <div>
                                    <div>{r.texto}</div>
                                    {r.descripcion && <small>{r.descripcion}</small>}
                                    <small className="pl-vista-datos">
                                        {datosVistaPrevia(r, plantilla.motor).join(' · ')}
                                        {porRedactar(r) && <span className="pl-insignia pl-insignia-alerta">Por redactar</span>}
                                    </small>
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
};

export default PlantillaVistaPrevia;
