import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const Navbar = () => {
  const { isAuthenticated, user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <nav>
      <div className="nav-left">
        <Link to="/">首页</Link>
        {isAuthenticated && <Link to="/dashboard">仪表盘</Link>}
      </div>
      <div className="nav-right">
        {isAuthenticated ? (
          <>
            <span>你好, {user.email}</span>
            <button onClick={handleLogout}>登出</button>
          </>
        ) : (
          <>
            <Link to="/login">登录</Link>
            <Link to="/register">注册</Link>
          </>
        )}
      </div>
    </nav>
  );
};

export default Navbar;