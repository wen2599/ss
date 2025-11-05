import React from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

function Navbar() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = () => {
      logout();
      navigate('/login');
  };

  return (
    <header className="navbar">
      <div className="navbar-brand">
        <NavLink to="/">LottoSys</NavLink>
      </div>
      <nav className="navbar-links">
        <NavLink to="/dashboard">仪表盘</NavLink>
        <NavLink to="/results">开奖结果</NavLink>
        <NavLink to="/my-bets">我的注单</NavLink>
        <NavLink to="/how-to-play">玩法说明</NavLink>
      </nav>
      <div className="navbar-user">
        {user ? (
          <>
            <span>欢迎, {user.email}</span>
            <button onClick={handleLogout} className="btn btn-secondary">登出</button>
          </>
        ) : (
          <>
            <NavLink to="/login">登录</NavLink>
            <NavLink to="/register">注册</NavLink>
          </>
        )}
      </div>
    </header>
  );
}

export default Navbar;