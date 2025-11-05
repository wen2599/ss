import React from 'react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext'; // 调整路径

const Navbar = () => {
    const { user, logout } = useAuth();

    return (
        <nav className="navbar">
            <div className="nav-container">
                <NavLink to="/" className="nav-logo">
                    竞猜平台
                </NavLink>
                <ul className="nav-menu">
                    <li className="nav-item">
                        <NavLink to="/" className="nav-links">
                            首页
                        </NavLink>
                    </li>
                    <li className="nav-item">
                        <NavLink to="/bets" className="nav-links">
                            我的竞猜
                        </NavLink>
                    </li>
                    <li className="nav-item">
                        <NavLink to="/results" className="nav-links">
                            比赛结果
                        </NavLink>
                    </li>
                    <li className="nav-item">
                        <NavLink to="/how-to-play" className="nav-links">
                            玩法介绍
                        </NavLink>
                    </li>
                    {user ? (
                        <>
                            <li className="nav-item">
                                <NavLink to="/profile" className="nav-links">
                                    个人中心
                                </NavLink>
                            </li>
                            <li className="nav-item">
                                <a onClick={logout} className="nav-links">
                                    登出
                                </a>
                            </li>
                        </>
                    ) : (
                        <li className="nav-item">
                            <NavLink to="/login" className="nav-links">
                                登录
                            </NavLink>
                        </li>
                    )}
                </ul>
            </div>
        </nav>
    );
};

export default Navbar;
