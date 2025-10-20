import React from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';
import './Navbar.css';

const Navbar = () => {
    const { isAuthenticated, logout } = useAuth();
    const navigate = useNavigate();

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    return (
        <header className="navbar-banner">
            <div className="navbar-container">
                <Link to="/" className="navbar-logo">电子账单系统</Link>
                <nav>
                    <ul className="navbar-links">
                        <li><NavLink to="/" className={({ isActive }) => isActive ? 'active' : ''}>首页</NavLink></li>
                        {isAuthenticated ? (
                            <>
                                <li><NavLink to="/bills" className={({ isActive }) => isActive ? 'active' : ''}>我的账单</NavLink></li>
                                <li><button onClick={handleLogout} className="logout-button">退出登录</button></li>
                            </>
                        ) : (
                            <>
                                <li><NavLink to="/login" className={({ isActive }) => isActive ? 'active' : ''}>登录</NavLink></li>
                                <li><NavLink to="/register" className={({ isActive }) => isActive ? 'active' : ''}>注册</NavLink></li>
                            </>
                        )}
                    </ul>
                </nav>
            </div>
        </header>
    );
};

export default Navbar;
