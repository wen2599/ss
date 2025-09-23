import React, { useState } from 'react';
import { Outlet, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import AuthModal from './AuthModal';
import '../App.css';

function MainLayout() {
  const { user, logout, isAuthenticated } = useAuth();
  const [isModalOpen, setIsModalOpen] = useState(false);

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
            <Link to="/bills" className="header-link">我的账单</Link>
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
