import React, { useEffect } from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import Navbar from './components/Navbar.jsx';
import HomePage from './pages/HomePage.jsx';
import LoginPage from './pages/LoginPage.jsx';
import RegisterPage from './pages/RegisterPage.jsx';
import BillsPage from './pages/BillsPage.jsx';
import ProtectedRoute from './components/ProtectedRoute.jsx'; // Import ProtectedRoute
import { AuthProvider, useAuth } from './contexts/AuthContext.jsx';
import { api } from './api.js';
import './App.css';

const AppContent = () => {
  const { login, logout, isAuthenticated } = useAuth(); // Add isAuthenticated

  useEffect(() => {
    // Only run checkUserAuth if the user is not already authenticated
    if (!isAuthenticated) {
      const checkUserAuth = async () => {
        try {
          const response = await api.checkAuth();
          if (response.data.isAuthenticated) {
            login(response.data.user);
          } else {
            logout();
          }
        } catch (error) {
          console.error('Auth check failed', error);
          logout();
        }
      };

      checkUserAuth();
    }
  }, [isAuthenticated, login, logout]);

  return (
    <>
      <Navbar />
      <Routes>
        <Route path="/" element={<HomePage />} />
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route 
          path="/bills" 
          element={
            <ProtectedRoute>
              <BillsPage />
            </ProtectedRoute>
          }
        />
      </Routes>
    </>
  );
};

function App() {
  return (
    <Router>
      <AuthProvider>
        <AppContent />
      </AuthProvider>
    </Router>
  );
}

export default App;
