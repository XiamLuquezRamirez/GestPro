import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faArrowRight, faCircleExclamation } from '@fortawesome/free-solid-svg-icons';
import axios from '../axios';
import CurvasNivel from './CurvasNivel';
import { getImageUrl } from '../utils/assetHelper';
import { tomarAvisoSesion } from '../utils/sesion';
import '../../css/Login.css';

const Login = ({ onLogin }) => {
    const navigate = useNavigate();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState(null);
    const [isLoading, setIsLoading] = useState(false);

    // Si se llegó aquí porque la sesión expiró, se dice. Solo se pone si hay aviso: en desarrollo,
    // React corre el efecto dos veces y la segunda ya no lo encuentra.
    useEffect(() => {
        const aviso = tomarAvisoSesion();
        if (aviso) setErrors(aviso);
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrors(null);
        setIsLoading(true);

        try {
            // Enviar login y recibir token + datos del usuario
            // Un 401 aquí son credenciales incorrectas, no una sesión que renovar.
            const { data } = await axios.post('/login', { email, password }, { _sinRenovar: true });
            const { token, user } = data;
            localStorage.setItem('token', token);

            // Configurar axios con el token
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

            // Usar los datos del usuario que ya recibimos
            onLogin(user);
            navigate('/dashboard');
        } catch (error) {
            if (error.response && error.response.status === 401) {
                setErrors('Credenciales incorrectas. Verifica tu correo y contraseña.');
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
        <div className="lg">
            <section className="lg-terreno">
                <CurvasNivel />
                <img className="lg-logo" src={getImageUrl('images/marca-completa.png')} alt="Es más que ingeniería" />
                <div className="lg-mensaje">
                    <span className="gp-etiqueta">Sistema de gestión de proyectos</span>
                    <h1>Cada proyecto, en su territorio.</h1>
                    <p>Formulación, licitación y ejecución en un solo tablero.</p>
                </div>
            </section>

            <section className="lg-acceso">
                <form onSubmit={handleSubmit} className="lg-formulario">
                    <div className="lg-marca">
                        <img src={getImageUrl('images/marca-hexagono.png')} width="44" height="44" alt="" />
                        <span>GestPro</span>
                    </div>

                    <h2>Iniciar sesión</h2>

                    {errors && (
                        <div className="lg-error" role="alert">
                            <FontAwesomeIcon icon={faCircleExclamation} />
                            {errors}
                        </div>
                    )}

                    <label htmlFor="email">Correo electrónico</label>
                    <input
                        type="email"
                        id="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        placeholder="ejemplo@empresa.com"
                        autoComplete="username"
                        autoFocus
                        required
                        disabled={isLoading}
                    />

                    <label htmlFor="password">Contraseña</label>
                    <input
                        type="password"
                        id="password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        placeholder="Tu contraseña"
                        autoComplete="current-password"
                        required
                        disabled={isLoading}
                    />

                    <button type="submit" className="gp-boton gp-boton-primario lg-entrar" disabled={isLoading}>
                        {isLoading ? 'Ingresando…' : <>Ingresar <FontAwesomeIcon icon={faArrowRight} /></>}
                    </button>

                    <p className="lg-ayuda">¿Necesitas ayuda? Contacta al administrador del sistema.</p>
                </form>
            </section>
        </div>
    );
};

export default Login;
