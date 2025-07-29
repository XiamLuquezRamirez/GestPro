import React, { useState } from 'react';
import axios from '../axios';
import { useNavigate } from 'react-router-dom';
import { getImageUrl } from '../utils/assetHelper';

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
            console.log('Intentando login con:', { email, password: '***' });
            
            
            // Enviar login y recibir token + datos del usuario
            const loginRes = await axios.post('/login', { email, password });
            
            console.log('Respuesta del login:', loginRes.data);

            const { token, user } = loginRes.data;
            localStorage.setItem('token', token);
            
            console.log('Token guardado en localStorage');

            // Configurar axios con el token
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

            // Usar los datos del usuario que ya recibimos
            onLogin(user);
            
            console.log('Usuario establecido, navegando a dashboard');

            navigate('/dashboard');
        } catch (error) {
            console.error('Error en login:', error);
            console.error('Error response:', error.response);
            
            if (error.response && error.response.status === 401) {
                setErrors('Credenciales incorrectas. Verifica tu email y contraseña.');
            } else if (error.response && error.response.data && error.response.data.error) {
                setErrors(`Error del servidor: ${error.response.data.error}`);
            } else {
                setErrors('Error al iniciar sesión. Intenta nuevamente.');
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
                        <div className="logo-icon"><img src={getImageUrl('images/logo.png')} width="80" height="80" alt="GestPro" /></div>
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
