import React, { useEffect, useState } from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import Login from './components/Login';
import Navigation from './components/Navigation';
import Dashboard from './components/Dashboard';
import Parametros from './components/Parametros';
//import axios from './axios';
import axios from 'axios';
import '../css/app.css';

function App() {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  const fetchUser = async () => {
    try {

      axios.get('https://ingeer.co/GestPro/public/sanctum/csrf-cookie', { withCredentials: true })


      const res = await axios.get('https://ingeer.co/GestPro/user', { withCredentials: true });
      setUser(res.data);
    } catch {
      setUser(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchUser();
  }, []);

  const logout = async () => {
    await axios.post('/logout');
    setUser(null);
  };

  if (loading) return <div>Cargando...</div>;

  return (
    <Router>
      <Routes>
        <Route
          path="/"
          element={user ? <Navigate to="/navigation" /> : <Login onLogin={setUser} />}
        />
        <Route
          path="/navigation"
          element={user ? <Navigation user={user} onLogout={logout} /> : <Navigate to="/" />}
        />
        <Route
          path="/dashboard"
          element={user ? <Dashboard user={user} onLogout={logout} /> : <Navigate to="/" />}
        />
        <Route
          path="/parametros"
          element={user ? <Parametros user={user} onLogout={logout} /> : <Navigate to="/" />}
        />
      </Routes>
    </Router>
  );
}

const root = ReactDOM.createRoot(document.getElementById("root"));
root.render(<App />);
