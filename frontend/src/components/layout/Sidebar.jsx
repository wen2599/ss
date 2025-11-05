import React from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

function Sidebar() {
    const { logout } = useAuth();
    const navigate = useNavigate();

    const handleLogout = () => {
        logout();
        navigate('/login');
    };

    return (
        <div className="sidebar">
            <h2>LottoSys</h2>
            <nav>
                <ul>
                    <li><NavLink to="/dashboard">仪表盘</NavLink></li>
                    <li><NavLink to="/results">开奖结果</NavLink></li>
                    <li><NavLink to="/my-bets">我的注单</NavLink></li>
                    <li><NavLink to="/how-to-play">玩法说明</NavLink></li>
                </ul>
            </nav>
            <div className="sidebar-footer">
                <button onClick={handleLogout} className="btn btn-secondary">登出</button>
            </div>
        </div>
    );
}

export default Sidebar;