import React from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import Header from './Header';
import '../../css/Navigation.css';

const Navigation = ({ user, onLogout }) => {
    const navigate = useNavigate();
    const location = useLocation();

    const modules = [
        {
            id: 'dashboard',
            title: 'Dashboard',
            description: 'Vista general y estadísticas',
            icon: '📊',
            color: '#1976d2',
            path: '/dashboard'
        },
        {
            id: 'parametros',
            title: 'Parámetros',
            description: 'Configuración del sistema',
            icon: '⚙️',
            color: '#607D8B',
            path: '/parametros'
        }
    ];

    const handleModuleClick = (modulePath) => {
        navigate(modulePath);
    };

    return (
        <div className="navigation-container">
            <Header user={user} onLogout={onLogout} />
            
            <div className="content-wrapper">
                <div className="page-header">
                    <h1>Panel de Navegación</h1>
                    <p>Seleccione el módulo que desea gestionar</p>
                </div>

                <div className="modules-grid">
                    {modules.map(module => (
                        <div 
                            key={module.id}
                            className="module-card"
                            onClick={() => handleModuleClick(module.path)}
                            style={{ borderLeftColor: module.color }}
                        >
                            <div className="module-icon" style={{ color: module.color }}>
                                {module.icon}
                            </div>
                            <div className="module-content">
                                <h3>{module.title}</h3>
                                <p>{module.description}</p>
                            </div>
                            <div className="module-arrow">
                                →
                            </div>
                        </div>
                    ))}
                </div>

                <div className="quick-stats">
                    <h2>Resumen Rápido</h2>
                    <div className="stats-grid">
                        <div className="stat-card">
                            <div className="stat-icon">📋</div>
                            <div className="stat-info">
                                <span className="stat-number">14</span>
                                <span className="stat-label">Proyectos Activos</span>
                            </div>
                        </div>
                        <div className="stat-card">
                            <div className="stat-icon">📅</div>
                            <div className="stat-info">
                                <span className="stat-number">8</span>
                                <span className="stat-label">Eventos Pendientes</span>
                            </div>
                        </div>
                        <div className="stat-card">
                            <div className="stat-icon">✅</div>
                            <div className="stat-info">
                                <span className="stat-number">6</span>
                                <span className="stat-label">Proyectos Aprobados</span>
                            </div>
                        </div>
                        <div className="stat-card">
                            <div className="stat-icon">🚀</div>
                            <div className="stat-info">
                                <span className="stat-number">4</span>
                                <span className="stat-label">En Ejecución</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Navigation; 