import React from 'react';
import { Link, Outlet, useNavigate } from 'react-router-dom';

function RootLayout() {
    const navigate = useNavigate();
    const authToken = localStorage.getItem('authToken');

    const handleLogout = () => {
        localStorage.removeItem('authToken');
        navigate('/login');
    };

    return (
        <>
            <nav className="navbar">
                <div className="nav-container">
                    <Link to="/" className="nav-logo">开奖应用</Link>
                    <ul className="nav-menu">
                        {authToken ? (
                            <>
                                <li className="nav-item">
                                    <Link to="/" className="nav-links">首页</Link>
                                </li>
                                <li className="nav-item">
                                    <Link to="/emails" className="nav-links">我的邮件</Link>
                                </li>
                                <li className="nav-item">
                                    <button onClick={handleLogout} className="nav-links-button">登出</button>
                                </li>
                            </>
                        ) : (
                            <>
                                <li className="nav-item">
                                    <Link to="/login" className="nav-links">登录</Link>
                                </li>
                                <li className="nav-item">
                                    <Link to="/register" className="nav-links">注册</Link>
                                </li>
                            </>
                        )}
                    </ul>
                </div>
            </nav>
            <main>
                <Outlet />
            </main>
        </>
    );
}

export default RootLayout;