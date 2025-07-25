import React, { useState } from 'react';
import axios from '../axios';
import { useNavigate } from 'react-router-dom';
import logo from '../../images/logo.png';

const Login = ({ onLogin }) => {
    const navigate = useNavigate();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState(null);
    const [isLoading, setIsLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrors(null);
        setIsLoading(true);

        try {
            // Petición CSRF primero
            await axios.get('/sanctum/csrf-cookie');
            

            // Luego enviar login
            await axios.post('/login', { email, password });

            // Obtener el usuario autenticado
            const res = await axios.get('/user');
            onLogin(res.data);

            // Redireccionar al dashboard
            navigate('/dashboard');
        } catch (error) {
            if (error.response && error.response.status === 422) {
                setErrors('Credenciales incorrectas. Por favor, verifique su email y contraseña.');
            } else {
                setErrors('Ocurrió un error al iniciar sesión. Por favor, intente nuevamente.');
            }
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="login-container">
            <div className="login-background">
                <div className="login-overlay"></div>
            </div>
            
            <div className="login-card">
                <div className="login-header">
                    <div className="logo-container">
                        <div className="logo-icon"><img src={logo} width="80" height="80" alt="GestPro" /></div>
                        <h1 className="logo-text">GestPro</h1>
                    </div>
                    <p className="login-subtitle">Sistema de Gestión de Proyectos</p>
                </div>

                <div className="login-form-container">
                    <h2 className="login-title">Iniciar Sesión</h2>
                    
                    {errors && (
                        <div className="error-message">
                            <span className="error-icon">⚠️</span>
                            {errors}
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="login-form">
                        <div className="form-group">
                            <label htmlFor="email" className="form-label">
                                <span className="label-icon">📧</span>
                                Correo Electrónico
                            </label>
                            <input
                                type="email"
                                id="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                className="form-input"
                                placeholder="ejemplo@empresa.com"
                                required
                                disabled={isLoading}
                            />
                        </div>

                        <div className="form-group">
                            <label htmlFor="password" className="form-label">
                                <span className="label-icon">🔒</span>
                                Contraseña
                            </label>
                            <input
                                type="password"
                                id="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                className="form-input"
                                placeholder="Ingrese su contraseña"
                                required
                                disabled={isLoading}
                            />
                        </div>

                        <button 
                            type="submit" 
                            className={`login-button ${isLoading ? 'loading' : ''}`}
                            disabled={isLoading}
                        >
                            {isLoading ? (
                                <>
                                    <span className="loading-spinner"></span>
                                    Iniciando sesión...
                                </>
                            ) : (
                                <>
                                    <span className="button-icon">🚀</span>
                                    Ingresar al Sistema
                                </>
                            )}
                        </button>
                    </form>

                    <div className="login-footer">
                        <p className="footer-text">
                            ¿Necesita ayuda? Contacte al administrador del sistema
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Login;
