import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate, Outlet } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import Home from './pages/Home';
import Login from './pages/Login';
import Register from './pages/Register'; // 引入注册页
import Dashboard from './pages/Dashboard';
import Navbar from './components/Navbar'; // 引入导航栏

// 私有路由守卫
function PrivateRoute() {
    const { user } = useAuth();
    return user ? <Outlet /> : <Navigate to="/login" />;
}

// 公开路由，如果已登录则重定向到 dashboard
function PublicRoute() {
    const { user } = useAuth();
    return !user ? <Outlet /> : <Navigate to="/dashboard" />;
}

function App() {
    return (
        <AuthProvider>
            <Router>
                <Navbar /> {/* 在所有页面顶部显示导航栏 */}
                <main>
                    <Routes>
                        <Route path="/" element={<Home />} />

                        {/* 公开路由 */}
                        <Route element={<PublicRoute />}>
                            <Route path="/login" element={<Login />} />
                            <Route path="/register" element={<Register />} />
                        </Route>

                        {/* 私有路由 */}
                        <Route element={<PrivateRoute />}>
                            <Route path="/dashboard" element={<Dashboard />} />
                        </Route>
                        
                        {/* 404 Not Found */}
                        <Route path="*" element={<Navigate to="/" />} />
                    </Routes>
                </main>
            </Router>
        </AuthProvider>
    );
}

export default App;