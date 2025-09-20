import React from 'react';
import { Link, Outlet } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import '../App.css'; // Re-using some of the main app styles

const Layout = () => {
    const { isAuthenticated, user, logout } = useAuth();

    return (
        <div className="App">
            <header className="App-header">
                <h1><Link to="/" className="logo">认证应用</Link></h1>
                <nav className="auth-buttons">
                    {!isAuthenticated ? (
                        <>
                            <Link to="/login" className="nav-link">登录</Link>
                            <Link to="/register" className="nav-link">注册</Link>
                        </>
                    ) : (
                        <>
                            <span className="welcome-user">欢迎, {user?.email}</span>
                            <button onClick={logout} className="nav-link-button">退出</button>
                        </>
                    )}
                </nav>
            </header>
            <main className="App-main">
                <Outlet /> {/* This is where the routed page components will be rendered */}
            </main>
        </div>
    );
};

export default Layout;
