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
    <nav style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '2rem', padding: '1rem', background: '#333' }}>
      <div>
        <Link to="/" style={{ marginRight: '1rem' }}>首页</Link>
        {isAuthenticated && <Link to="/dashboard">仪表盘</Link>}
      </div>
      <div>
        {isAuthenticated ? (
          <>
            <span style={{ marginRight: '1rem' }}>你好, {user?.email}</span>
            <button onClick={handleLogout}>登出</button>
          </>
        ) : (
          <>
            <Link to="/login" style={{ marginRight: '1rem' }}>登录</Link>
            <Link to="/register">注册</Link>
          </>
        )}
      </div>
    </nav>
  );
};

export default Navbar;