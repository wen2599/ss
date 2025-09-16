import React from 'react';
import { Routes, Route, Navigate, Link } from 'react-router-dom';
import { useAuth } from './context/AuthContext';
import HomePage from './pages/HomePage';
import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';
import './App.css'; // Import the new stylesheet

// A wrapper for protected routes
const ProtectedRoute = ({ children }) => {
    const { isAuthenticated, loading } = useAuth();
    if (loading) return <div>Loading application state...</div>;
    if (!isAuthenticated) return <Navigate to="/login" replace />;
    return children;
};

function App() {
    const { isAuthenticated, user, logout } = useAuth();

    return (
        <div className="App">
            <header className="App-header">
                <h1>Chat Log Parser</h1>
                <nav>
                    <ul>
                        {!isAuthenticated ? (
                            <>
                                <li><Link to="/login">Login</Link></li>
                                <li><Link to="/register">Register</Link></li>
                            </>
                        ) : (
                            <>
                                <li><span>Welcome, {user.email}</span></li>
                                <li><button onClick={logout}>Logout</button></li>
                            </>
                        )}
                    </ul>
                </nav>
            </header>
            <main>
                <Routes>
                    <Route
                        path="/"
                        element={
                            <ProtectedRoute>
                                <HomePage />
                            </ProtectedRoute>
                        }
                    />
                    <Route path="/login" element={<LoginPage />} />
                    <Route path="/register" element={<RegisterPage />} />
                    <Route path="*" element={<Navigate to="/" />} />
                </Routes>
            </main>
        </div>
    );
}

export default App;
