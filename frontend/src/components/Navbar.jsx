// src/components/Navbar.jsx
import React from 'react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import './Navbar.css';

const Navbar = () => {
  const { logout } = useAuth();

  return (
    <nav className="navbar">
      <div className="navbar-brand">
        管理系统
      </div>
      <div className="navbar-links">
        <NavLink to="/" end>开奖号码</NavLink>
        <NavLink to="/emails">邮件原文</NavLink>
        <NavLink to="/settlements">结算清单</NavLink>
        {/* 新增的入口 */}
        <NavLink to="/settings">结算设置</NavLink> 
      </div>
      <div className="navbar-actions">
        <button onClick={logout} className="logout-button">退出登录</button>
      </div>
    </nav>
  );
};

export default Navbar;