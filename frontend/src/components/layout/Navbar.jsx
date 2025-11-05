import React from 'react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

function Navbar() {
  const { user, logout } = useAuth();

  return (
    <header className="navbar">
      <div className="navbar-brand">
        <NavLink to={user ? "/dashboard" : "/"}>LottoSys</NavLink>
      </div>
      
      {user && (
        <nav className="navbar-links">
          <NavLink to="/dashboard">仪表盘</NavLink>
          <NavLink to="/results">开奖公告</NavLink>
          <NavLink to="/my-bets">我的注单</NavLink>
          <NavLink to="/how-to-play">玩法说明</NavLink>
        </nav>
      )}

      <div className="navbar-user">
        {user ? (
          <>
            <NavLink to="/profile">个人中心</NavLink>
            <button onClick={logout} className="btn btn-secondary">登出</button>
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