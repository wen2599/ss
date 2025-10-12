import React from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.js'; // Import useAuth
import './Navbar.css';

const Navbar = () => {
    const { isAuthenticated, user, logout } = useAuth(); // Use the auth context
    const navigate = useNavigate();

    const handleLogout = () => {
        logout(); // Clear the auth state
        navigate('/login'); // Redirect to login page
    };

    return (
        <nav className="navbar">
            <div className="navbar-brand">
                <Link to="/">账单中心</Link>
            </div>
            <ul className="navbar-links-left">
                <li><NavLink to="/" className={({ isActive }) => isActive ? 'active' : ''}>主页</NavLink></li>
                {isAuthenticated && (
                    <li><NavLink to="/bills" className={({ isActive }) => isActive ? 'active' : ''}>我的账单</NavLink></li>
                )}
            </ul>
            <ul className="navbar-links-right">
                {isAuthenticated ? (
                    <>
                        <li className="navbar-user-greeting">欢迎, {user?.username}!</li>
                        <li><button onClick={handleLogout} className="logout-button">登出</button></li>
                    </>
                ) : (
                    <>
                        <li><NavLink to="/login" className={({ isActive }) => isActive ? 'active' : ''}>登录</NavLink></li>
                        <li><NavLink to="/register" className={({ isActive }) => isActive ? 'active' : ''}>注册</NavLink></li>
                    </>
                )}
            </ul>
        </nav>
    );
};

export default Navbar;
