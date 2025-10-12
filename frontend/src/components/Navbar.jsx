import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext.jsx';
import { api } from '../api.js';
import './Navbar.css';

const Navbar = () => {
    const { isAuthenticated, user, logout } = useAuth();
    const navigate = useNavigate();

    const handleLogout = async () => {
        try {
            await api.logout();
            logout(); // Clear auth state in context
            navigate('/'); // Redirect to home page
        } catch (error) {
            console.error('Logout failed', error);
            // Optionally, show an error message to the user
        }
    };

    return (
        <nav className="navbar">
            <div className="navbar-brand">
                <Link to="/">账单中心</Link>
            </div>
            <ul className="navbar-links">
                {isAuthenticated ? (
                    <>
                        <li><span className="navbar-user">{user?.email}</span></li>
                        <li><Link to="/bills">我的账单</Link></li>
                        <li><button onClick={handleLogout} className="logout-button">退出登录</button></li>
                    </>
                ) : (
                    <>
                        <li><Link to="/login">登录</Link></li>
                        <li><Link to="/register">注册</Link></li>
                    </>
                )}
            </ul>
        </nav>
    );
};

export default Navbar;
