import React from 'react';
import { Routes, Route, Navigate, Link } from 'react-router-dom';
import { useAuth } from './context/AuthContext';
import HomePage from './pages/HomePage';
import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';
import AdminPage from './pages/AdminPage'; // Import the new AdminPage
import './App.css';

// A wrapper for standard protected routes
const ProtectedRoute = ({ children }) => {
    const { isAuthenticated, loading } = useAuth();
    if (loading) return <div>正在加载应用状态...</div>;
    if (!isAuthenticated) return <Navigate to="/login" replace />;
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
    const { isAuthenticated, user, logout } = useAuth();

    return (
        <div className="App">
            <header className="App-header">
                <h1>六合彩投注系统</h1>
                <nav>
                    <ul>
                        {!isAuthenticated ? (
                            <>
                                <li><Link to="/login">登录</Link></li>
                                <li><Link to="/register">注册</Link></li>
                            </>
                        ) : (
                            <>
                                {user?.is_superadmin && <li><Link to="/admin">后台管理</Link></li>}
                                <li><span>欢迎您, {user.email}</span></li>
                                <li><button onClick={logout}>退出</button></li>
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
                    <Route
                        path="/admin"
                        element={
                            <SuperAdminRoute>
                                <AdminPage />
                            </SuperAdminRoute>
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
