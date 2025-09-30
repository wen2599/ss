import React, { useState } from 'react';
import { Outlet, NavLink, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import AuthModal from './AuthModal';
import '../App.css';

function MainLayout() {
  const { isAuthenticated, logout } = useAuth();
  const [isModalOpen, setIsModalOpen] = useState(false);
  const location = useLocation();

  return (
    <div className="container">
      <header className="app-header">
        <div className="header-left">
          {!isAuthenticated ? (
            <button onClick={() => setIsModalOpen(true)}>登录 / 注册</button>
          ) : (
            <button onClick={logout}>退出登录</button>
          )}
        </div>
        <div className="header-right">
          {isAuthenticated && (
            <>
              <NavLink to="/bills" className="header-link">我的账单</NavLink>
              <NavLink to="/settings" className="header-link">设置</NavLink>
              <NavLink to="/" className="header-link">主页</NavLink>
            </>
          )}
        </div>
      </header>
      <main>
        <Outlet />
      </main>
      {isModalOpen && <AuthModal onClose={() => setIsModalOpen(false)} />}
    </div>
  );
}

export default MainLayout;
