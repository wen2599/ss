import React, { useState } from 'react';
import { Routes, Route, Navigate, Link } from 'react-router-dom';
import { useAuth } from './context/AuthContext';
import HomePage from './pages/HomePage';
import AdminPage from './pages/AdminPage';
import LoginModal from './components/LoginModal';
import RegisterModal from './components/RegisterModal';
import './App.css';

// A wrapper for standard protected routes
const ProtectedRoute = ({ children }) => {
    const { isAuthenticated, loading } = useAuth();
    if (loading) return <div>正在加载应用状态...</div>;
    if (!isAuthenticated) return <Navigate to="/" replace />; // In V2, non-authed users see the homepage, but can't do much
    return children;
};

// A wrapper for super admin routes
const SuperAdminRoute = ({ children }) => {
    const { isAuthenticated, user, loading } = useAuth();
    if (loading) return <div>正在加载应用状态...</div>;
    if (!isAuthenticated || !user.is_superadmin) {
        return <Navigate to="/" replace />; // Redirect non-admins to home
    }
    return children;
};

function App() {
    const { isAuthenticated, user, login, logout } = useAuth();
    const [showLoginModal, setShowLoginModal] = useState(false);
    const [showRegisterModal, setShowRegisterModal] = useState(false);

    const handleLoginSuccess = (userData) => {
        login(userData);
        setShowLoginModal(false);
    };

    const handleRegisterSuccess = (userData) => {
        login(userData); // Log in the new user automatically
        setShowRegisterModal(false);
    };

    return (
        <div className="App">
            {showLoginModal && <LoginModal onClose={() => setShowLoginModal(false)} onLoginSuccess={handleLoginSuccess} />}
            {showRegisterModal && <RegisterModal onClose={() => setShowRegisterModal(false)} onRegisterSuccess={handleRegisterSuccess} />}

            <header className="App-header">
                <div className="header-left">
                    {!isAuthenticated ? (
                        <>
                            <button onClick={() => setShowLoginModal(true)}>登录</button>
                            <button onClick={() => setShowRegisterModal(true)} style={{marginLeft: '10px'}}>注册</button>
                        </>
                    ) : (
                        <button onClick={logout}>退出</button>
                    )}
                </div>
                <h1>六合彩投注系统</h1>
                <div className="header-right">
                    <button className="placeholder-btn">文本模板</button>
                </div>
            </header>
            <main>
                <Routes>
                    <Route path="/" element={<HomePage />} />
                    <Route
                        path="/admin"
                        element={
                            <SuperAdminRoute>
                                <AdminPage />
                            </SuperAdminRoute>
                        }
                    />
                    <Route path="*" element={<Navigate to="/" />} />
                </Routes>
            </main>
        </div>
    );
}

export default App;
