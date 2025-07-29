import React, { useEffect, useState } from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import Login from './components/Login';
import Navigation from './components/Navigation';
import Dashboard from './components/Dashboard';
import Parametros from './components/Parametros';
import axios from './axios';
import '../css/app.css';

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

  if (loading) return <div>Cargando...</div>;

  return (
    <Router>
      <Routes>
        <Route
          path="/GestPro"
          element={user ? <Navigate to="/navigation" /> : <Login onLogin={setUser} />}
        />
        <Route
          path="/navigation"
          element={user ? <Navigation user={user} onLogout={logout} /> : <Navigate to="/GestPro" />}
        />
        <Route
          path="/dashboard"
          element={user ? <Dashboard user={user} onLogout={logout} /> : <Navigate to="/GestPro" />}
        />
        <Route
          path="/parametros"
          element={user ? <Parametros user={user} onLogout={logout} /> : <Navigate to="/GestPro" />}
        />
      </Routes>
    </Router>
  );
}

const root = ReactDOM.createRoot(document.getElementById("root"));
root.render(<App />);
