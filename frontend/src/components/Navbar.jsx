// File: frontend/src/components/Navbar.jsx

import React from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

function Navbar() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    try {
      await logout();
      navigate('/');
    } catch (error) {
      console.error("Logout failed:", error);
    }
  };

  return (
    <nav className="navbar">
      <div className="nav-brand">
        <NavLink to="/">结算系统</NavLink>
      </div>
      <ul className="nav-links">
        <li><NavLink to="/">开奖记录</NavLink></li>
        {user && <li><NavLink to="/emails">邮件原文</NavLink></li>}
        {user && <li><NavLink to="/odds-template">赔率设置</NavLink></li>}
      </ul>
      <div className="nav-auth">
        {user ? (
          <>
            <span>欢迎, {user.email}</span>
            <button onClick={handleLogout}>退出登录</button>
          </>
        ) : (
          <NavLink to="/auth">注册/登录</NavLink>
        )}
      </div>
    </nav>
  );
}

export default Navbar;
