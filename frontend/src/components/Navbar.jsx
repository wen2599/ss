import React from 'react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

function Navbar() {
  const { user, logout } = useAuth();
  
  return (
    <nav>
      <div>
        <NavLink to="/">首页</NavLink>
        {user && <NavLink to="/dashboard">仪表盘</NavLink>}
      </div>
      <div>
        {user ? (
          <button onClick={logout}>登出</button>
        ) : (
          <>
            <NavLink to="/login">登录</NavLink>
            <NavLink to="/register">注册</NavLink>
          </>
        )}
      </div>
    </nav>
  );
}

export default Navbar;