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
        <header className="navbar">
            <Link to="/" className="navbar-logo">电子账单系统</Link>
            <nav>
                <ul className="navbar-links">
                    {isAuthenticated ? (
                        <>
                            <li><NavLink to="/lottery" className={({ isActive }) => isActive ? 'active' : ''}>开奖</NavLink></li>
                            <li><NavLink to="/emails" className={({ isActive }) => isActive ? 'active' : ''}>邮件原文</NavLink></li>
                            <li><NavLink to="/bills" className={({ isActive }) => isActive ? 'active' : ''}>结算</NavLink></li>
                            <li><button onClick={handleLogout}>退出登录</button></li>
                        </>
                    ) : (
                        <>
                            <li><NavLink to="/login" className={({ isActive }) => isActive ? 'active' : ''}>登录</NavLink></li>
                            <li><NavLink to="/register" className={({ isActive }) => isActive ? 'active' : ''}>注册</NavLink></li>
                        </>
                    )}
                </ul>
            </nav>
        </header>
    );
};

export default Navbar;