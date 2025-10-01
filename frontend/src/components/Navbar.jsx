import React, { useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import AuthModal from './AuthModal';
import './Navbar.css';

function Navbar() {
  const { isAuthenticated, logout } = useAuth();
  const [isModalOpen, setIsModalOpen] = useState(false);
  const location = useLocation();

  return (
    <>
      <nav className="navbar">
        <div className="navbar-left">
          <NavLink to="/" className="navbar-brand">彩票助手</NavLink>
        </div>
        <div className="navbar-right">
          {isAuthenticated ? (
            <>
              <NavLink to="/bills" className="navbar-link">我的账单</NavLink>
              <NavLink to="/lottery-results" className="navbar-link">开奖结果</NavLink>
              <button onClick={logout} className="navbar-button">退出登录</button>
            </>
          ) : (
            <button onClick={() => setIsModalOpen(true)} className="navbar-button">登录 / 注册</button>
          )}
        </div>
      </nav>
      {isModalOpen && <AuthModal onClose={() => setIsModalOpen(false)} />}
    </>
  );
}

export default Navbar;
