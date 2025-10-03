import React from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import './Navbar.css';

const Navbar = () => {
    const { isAuthenticated, user, logout } = useAuth();
    const navigate = useNavigate();

    const handleLogout = () => {
        logout();
        navigate('/login'); // Redirect to login page after logout
    };

    return (
        <nav className="navbar">
            <div className="navbar-container">
                <NavLink to="/" className="navbar-logo">
                    邮件解析器
                </NavLink>
                <ul className="nav-menu">
                    <li className="nav-item">
                        <NavLink to="/" className="nav-links" end>
                            首页
                        </NavLink>
                    </li>
                    {isAuthenticated && (
                        <li className="nav-item">
                            <NavLink to="/parser" className="nav-links">
                                解析器
                            </NavLink>
                        </li>
                    )}
                </ul>
                <div className="nav-auth">
                    {isAuthenticated ? (
                        <>
                            <span className="navbar-user">欢迎, {user.username}</span>
                            <button onClick={handleLogout} className="btn btn-outline">
                                登出
                            </button>
                        </>
                    ) : (
                        <>
                            <NavLink to="/login" className="btn btn-secondary">
                                登录
                            </NavLink>
                            <NavLink to="/register" className="btn btn-primary">
                                注册
                            </NavLink>
                        </>
                    )}
                </div>
            </div>
        </nav>
    );
};

export default Navbar;