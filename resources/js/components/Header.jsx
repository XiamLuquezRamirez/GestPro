import React from 'react';
import { faSignOutAlt, faCog, faHome, faBars } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { useNavigate, useLocation } from 'react-router-dom';

const Header = ({ user, onLogout }) => {
    const navigate = useNavigate();
    const location = useLocation();

    const getPageTitle = () => {
        switch(location.pathname) {
            case '/dashboard':
                return 'Dashboard de Gestión de Proyectos';
            case '/proyectos':
                return 'Gestionar Proyectos';
            case '/eventos':
                return 'Gestionar Eventos';
            case '/parametros':
                return 'Parámetros de Gestión de Proyectos';
            case '/navigation':
                return 'Panel de Navegación';
            default:
                return 'Gestión de Proyectos';
        }
    };

    const getNavigationButton = () => {
        // Si estamos en el panel de navegación, no mostrar botón de navegación
        if (location.pathname === '/navigation') {
            return null;
        }

        // Para todas las demás rutas, mostrar botón para regresar al panel de navegación
        return (
            <button onClick={() => navigate('/navigation')} className="nav-btn">
                <FontAwesomeIcon icon={faBars} /> Menú Principal
            </button>
        );
    };

    return (
        <header className="dashboard-header">
            <div className="header-content">
                <h1>{getPageTitle()}</h1>

                <div className="user-info">
                    <span>Bienvenido, {user.name}</span>
                    {getNavigationButton()}
                    <button onClick={onLogout} className="logout-btn">
                        <FontAwesomeIcon icon={faSignOutAlt} /> Cerrar sesión
                    </button>
                </div>
            </div>
        </header>
    );
};

export default Header;