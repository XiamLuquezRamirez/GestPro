import React, { useEffect, useState } from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import Login from './components/Login';
import AppShell from './components/AppShell';
import Dashboard from './components/Dashboard';
import Parametros from './components/Parametros';
import ExpedienteViabilizacion from './components/ExpedienteViabilizacion';
import PlantillasChecklist from './components/PlantillasChecklist';
import PlantillaDetalle from './components/PlantillaDetalle';
import axios from './axios';
import '../css/app.css';

const INICIO = '/dashboard';

function App() {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  const getToken = () => localStorage.getItem('token');

  const fetchUser = async () => {
    try {
      const token = getToken();

      if (!token) {
        // No hay token, usuario no autenticado (normal)
        setUser(null);
        return;
      }

      const res = await axios.get('/user');
      setUser(res.data);
    } catch (error) {
      console.error('Error fetching user:', error);
      // Si hay error al obtener usuario, limpiar token y usuario
      localStorage.removeItem('token');
      setUser(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchUser();
  }, []);

  const logout = () => {
    localStorage.removeItem('token');
    setUser(null);
  };

  if (loading) return <div className="gp-vacio" role="status">Cargando…</div>;

  const privada = (elemento) => (user ? elemento : <Navigate to="/" />);

  return (
    <Router basename={window.__ROUTER_BASE__ || ''}>
      <Routes>
        <Route path="/" element={user ? <Navigate to={INICIO} /> : <Login onLogin={setUser} />} />

        {/* El modo sala ocupa toda la pantalla: va fuera del armazón con menú. */}
        <Route path="/sala" element={privada(<Dashboard sala />)} />

        <Route element={privada(<AppShell user={user} onLogout={logout} />)}>
          <Route path="/dashboard" element={<Dashboard />} />
          <Route path="/parametros" element={<Navigate to="/parametros/proyectos" replace />} />
          <Route path="/parametros/:seccion" element={<Parametros />} />
          {/* No usar /expedientes/...: bajo /GestPro chocaría con la API del mismo nombre. */}
          <Route path="/viabilizacion/:proyectoId" element={<ExpedienteViabilizacion user={user} />} />
          {/* Solo Administrador: el backend también lo exige. */}
          <Route path="/plantillas" element={user?.rol === 'Administrador' ? <PlantillasChecklist /> : <Navigate to={INICIO} replace />} />
          <Route path="/plantillas/:plantillaId" element={user?.rol === 'Administrador' ? <PlantillaDetalle /> : <Navigate to={INICIO} replace />} />
        </Route>

        <Route path="*" element={<Navigate to={user ? INICIO : '/'} replace />} />
      </Routes>
    </Router>
  );
}

const root = ReactDOM.createRoot(document.getElementById("root"));
root.render(<App />);
