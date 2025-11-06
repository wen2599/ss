import React from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

const Navbar = () => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <nav className="navbar">
      <div className="navbar-links">
        <NavLink to="/dashboard/emails">邮件原文</NavLink>
        <NavLink to="/dashboard/settlements">结算表单</NavLink>
      </div>
      <div className="navbar-user-section">
        {user && <span>欢迎, {user.email}</span>}
        <button onClick={handleLogout} className="btn">
          退出登录
        </button>
      </div>
    </nav>
  );
};

export default Navbar;
