import React, { useEffect, useState } from 'react';
import { faEdit, faFile, faPlus, faTimes, faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import Swal from 'sweetalert2';
import axios from '../../axios';
import { avisarError } from '../../utils/avisosPlantillas';
import { etiquetaEstadoContrato, textoAvisoCierre } from '../../utils/contratos';
import { formatoMoneda } from '../../utils/moneda';
import ContratoEditor, { pedirProcesosProyecto, pedirTiposContrato } from './ContratoEditor';
import '../../../css/Parametros.css';
import '../../../css/Contratacion.css';

/**
 * El diálogo "Gestión de Contratos" de un proyecto (Parámetros → Proyectos → Contrato): la lista
 * de sus contratos y, al agregar o editar uno, el editor. Se monta al abrirse.
 *
 * - `proyecto`: la fila del proyecto (GET /proyectos); sus `contratos` se muestran mientras llega
 *   la lista de GET /listarContratos, que se pide al abrir: la fila puede traer versiones viejas
 *   (p. ej. de un contrato terminado después de cargar Parámetros) y editar desde ella daría 409.
 * - `onCerrar()`: al cerrar el diálogo.
 * - `onCambio()`: tras guardar, eliminar, terminar o liquidar un contrato (Parámetros recarga sus proyectos).
 * - `puedeEditar`: sin permiso, la lista y los contratos se ven sin controles para modificarlos.
 */
const ContratosProyecto = ({ proyecto, onCerrar, onCambio, puedeEditar = true }) => {
    // Contratos del proyecto: los de su fila hasta que llega la lista pedida al abrir.
    const [contratos, setContratos] = useState(() => (proyecto.contratos?.length > 0 ? proyecto.contratos : []));
    // El editor abierto: null en la lista; { contrato } al editar, { contrato: null } al agregar.
    const [editor, setEditor] = useState(null);
    // Catálogo de tipos y procesos del proyecto, pedidos al abrir; null mientras no cargan.
    const [tiposContrato, setTiposContrato] = useState(null);
    const [procesos, setProcesos] = useState(null);

    const listContratos = async () => {
        const response = await axios.get('/listarContratos', {
            params: {
                proyecto: proyecto.id
            }
        });
        setContratos(response.data);
        return response.data;
    };

    useEffect(() => {
        pedirTiposContrato().then(setTiposContrato);
        pedirProcesosProyecto(proyecto.id).then(setProcesos);
        // La lista al día; si falla, quedan los de la fila y se avisa.
        listContratos().catch((error) => avisarError('No se pudo cargar la lista de contratos', error));
    }, []);

    // Recarga la lista y devuelve el contrato `id` recargado (o null): lo usa el editor.
    const recargar = async (id) => {
        const lista = await listContratos();
        return (Array.isArray(lista) && lista.find(c => c.id === id)) || null;
    };

    // Abrir formulario para agregar contrato
    const handleAddNewContrato = () => setEditor({ contrato: null });

    // Editar contrato
    const handleEditContrato = (contrato) => setEditor({ contrato });

    const handleGuardado = () => {
        listContratos().catch((error) => avisarError('No se pudo recargar la lista de contratos', error));
        onCambio?.();
        setEditor(null);
    };

    // Eliminar contrato. El servidor lo rechaza si tiene actas, actividades o modificaciones, y
    // explica por qué: en ese caso se anula.
    const handleDeleteContrato = async (contrato) => {
        const { isConfirmed } = await Swal.fire({
            icon: 'warning',
            titleText: `¿Eliminar el contrato ${contrato.n_contrato}?`,
            text: 'También se borran sus anexos. No se puede deshacer.',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        });
        if (!isConfirmed) return;
        try {
            await axios.post('/eliminarContrato', { id: contrato.id });
            await listContratos();
            onCambio?.();
            Swal.fire({ icon: 'success', title: 'Contrato eliminado' });
        } catch (error) {
            avisarError('No se pudo eliminar el contrato', error);
        }
    };

    return (
        <div className="modal-overlay">
            <div className="modal-content contratos-modal" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2><FontAwesomeIcon icon={faFile} /> Gestión de Contratos - {proyecto?.nombre}</h2>
                    <button onClick={onCerrar} className="btn-close">×</button>
                </div>

                {editor ? (
                    <ContratoEditor
                        key={editor.contrato?.id ?? 'nuevo'}
                        proyecto={proyecto}
                        contrato={editor.contrato}
                        tiposContrato={tiposContrato}
                        procesosIniciales={procesos}
                        puedeEditar={puedeEditar}
                        recargar={recargar}
                        onCambio={onCambio}
                        onGuardado={handleGuardado}
                        onCancelar={() => setEditor(null)}
                    />
                ) : (
                    <>
                        <div className="contratos-modal-body">
                            <div className="contratos-list-header">
                                <h3>Contratos del Proyecto</h3>
                                {puedeEditar && (
                                    <button
                                        onClick={handleAddNewContrato}
                                        className="btn-add-contrato-header"
                                    >
                                        <FontAwesomeIcon icon={faPlus} /> Agregar Contrato
                                    </button>
                                )}
                            </div>

                            <div className="contratos-list">
                                {contratos.length === 0 ? (
                                    <div className="no-contratos-container">
                                        <p className="no-contratos">No hay contratos registrados para este proyecto</p>
                                        {puedeEditar && (
                                            <button
                                                onClick={handleAddNewContrato}
                                                className="btn-add-first-contrato"
                                            >
                                                <FontAwesomeIcon icon={faPlus} /> Agregar Primer Contrato
                                            </button>
                                        )}
                                    </div>
                                ) : (
                                    <div className="contratos-table">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>N° Contrato</th>
                                                    <th>Objeto</th>
                                                    <th>Valor</th>
                                                    <th>Estado</th>
                                                    <th>Anexos</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {contratos.map(contrato => (
                                                    <tr key={contrato.id}>
                                                        <td>{contrato.n_contrato}</td>
                                                        <td>{contrato.objeto}</td>
                                                        <td>{formatoMoneda(Number(contrato.valor))}</td>
                                                        <td>
                                                            {etiquetaEstadoContrato(contrato.estado)}
                                                            {textoAvisoCierre(contrato.aviso_cierre) && (
                                                                <span className="ct-aviso-cierre ct-estado ct-estado-ambar">{textoAvisoCierre(contrato.aviso_cierre)}</span>
                                                            )}
                                                        </td>
                                                        <td>
                                                            <span className="anexos-count">
                                                                {contrato.anexos ? contrato.anexos.length : 0} anexos
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div className="contrato-actions">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => handleEditContrato(contrato)}
                                                                    className="btn-edit-contrato"
                                                                    title="Editar contrato"
                                                                >
                                                                    <FontAwesomeIcon icon={faEdit} />
                                                                </button>
                                                                {puedeEditar && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => handleDeleteContrato(contrato)}
                                                                        className="btn-delete-contrato"
                                                                        title="Eliminar contrato"
                                                                    >
                                                                        <FontAwesomeIcon icon={faTrash} />
                                                                    </button>
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="modal-actions">
                            <button type="button" onClick={onCerrar} className="btn-cancel">
                                <FontAwesomeIcon icon={faTimes} /> Cerrar
                            </button>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
};

export default ContratosProyecto;
