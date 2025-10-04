import React from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const Navbar = () => {
    const { isAuthenticated, user, logout } = useAuth();
    const navigate = useNavigate();

    const handleLogout = () => {
        logout();
        navigate('/login'); // Redirect to login page after logout
    };

    return (
        <nav className="navbar">
            <NavLink to="/" className="navbar-brand">
                邮件解析器
            </NavLink>
            <ul className="navbar-links">
                <li>
                    <NavLink to="/" end>
                        首页
                    </NavLink>
                </li>
                {isAuthenticated && (
                    <li>
                        <NavLink to="/parser">
                            解析器
                        </NavLink>
                    </li>
                )}
                {isAuthenticated ? (
                    <>
                        <li>
                            <span>欢迎, {user.username}</span>
                        </li>
                        <li>
                            <button onClick={handleLogout}>
                                登出
                            </button>
                        </li>
                    </>
                ) : (
                    <>
                        <li>
                            <NavLink to="/login">
                                登录
                            </NavLink>
                        </li>
                        <li>
                            <NavLink to="/register">
                                注册
                            </NavLink>
                        </li>
                    </>
                )}
            </ul>
        </nav>
    );
};

export default Navbar;