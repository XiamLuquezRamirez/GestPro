import React, { useMemo } from 'react';
import { agruparVistaPrevia, etiquetaCaracter, etiquetaTipo } from '../utils/plantillasChecklist';

/** El checklist de la versión de trabajo como lo verá el usuario en un expediente. */
const PlantillaVistaPrevia = ({ plantilla, version, requisitos }) => {
    const grupos = useMemo(() => agruparVistaPrevia(requisitos, plantilla.motor), [requisitos, plantilla.motor]);

    if (!version) return <p className="gp-vacio">La plantilla no tiene versiones.</p>;
    if (grupos.length === 0) return <p className="gp-vacio">La versión v{version.numero} no tiene requisitos activos.</p>;

    return (
        <div className="pl-vista">
            <p className="pl-ayuda">
                Versión v{version.numero}{version.estado === 'borrador' ? ' (borrador)' : ''}.
                {plantilla.motor === 'sgr'
                    ? ' En cada expediente, el evaluador SGR decide cuáles aplican según la fase, el sector y los componentes.'
                    : ' Los condicionados nacen "por definir" y una persona decide si aplican.'}
            </p>
            {grupos.map((g) => (
                <section key={g.clave} className="gp-panel pl-vista-grupo">
                    <h3>{g.titulo} <span className="pl-ayuda">· {g.requisitos.length}</span></h3>
                    <ul>
                        {g.requisitos.map((r) => (
                            <li key={r.id}>
                                <input type="checkbox" disabled aria-label={`Vista previa de ${r.texto}`} />
                                <div>
                                    <div>{r.texto}</div>
                                    {r.descripcion && <small>{r.descripcion}</small>}
                                    <small className="pl-vista-datos">
                                        {plantilla.motor === 'simple' && etiquetaCaracter(r.caracter)}
                                        {r.tipo_requisito && ` · ${etiquetaTipo(r.tipo_requisito)}`}
                                        {r.soporte_requerido && ` · Soporte: ${r.soporte_requerido}`}
                                        {r.cargo_cumplimiento && ` · Cumple: ${r.cargo_cumplimiento}`}
                                        {r.caracter === 'condicionado' && r.condicion_descripcion && ` · Aplica si: ${r.condicion_descripcion}`}
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
