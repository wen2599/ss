import React from 'react';
import { Routes, Route } from 'react-router-dom';
import { useAuth } from './context/AuthContext';

import Layout from './components/Layout';
import ProtectedRoute from './components/ProtectedRoute';

import HomePage from './pages/HomePage';
import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';

function App() {
    const { loading } = useAuth();

    if (loading) {
        return <div className="loading-container">Loading Application...</div>;
    }

    return (
        <Routes>
            <Route path="/" element={<Layout />}>
                {/* Protected Route */}
                <Route
                    index
                    element={
                        <ProtectedRoute>
                            <HomePage />
                        </ProtectedRoute>
                    }
                />

                {/* Public Routes */}
                <Route path="login" element={<LoginPage />} />
                <Route path="register" element={<RegisterPage />} />

                {/* Catch-all for not found pages - can be enhanced later */}
                <Route path="*" element={<h2>404 Not Found</h2>} />
            </Route>
        </Routes>
    );
}

export default App;
