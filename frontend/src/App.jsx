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
    if (loading) return <div>正在加载应用状态...</div>;
    if (!isAuthenticated) return <Navigate to="/login" replace />;
    return children;
};

function App() {
    const { isAuthenticated, user, logout } = useAuth();

    return (
        <div className="App">
            <header className="App-header">
                <h1>聊天记录解析器</h1>
                <nav>
                    <ul>
                        {!isAuthenticated ? (
                            <>
                                <li><Link to="/login">登录</Link></li>
                                <li><Link to="/register">注册</Link></li>
                            </>
                        ) : (
                            <>
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
                    <Route path="/login" element={<LoginPage />} />
                    <Route path="/register" element={<RegisterPage />} />
                    <Route path="*" element={<Navigate to="/" />} />
                </Routes>
            </main>
        </div>
    );
}

export default App;
