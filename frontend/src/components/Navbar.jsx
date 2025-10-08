import { useState } from 'react';
import { NavLink } from 'react-router-dom';
import Auth from './Auth'; // 引入 Auth 组件用于弹窗
import './Navbar.css';

function Navbar({ user, onLogin, onLogout }) {
  const [showAuth, setShowAuth] = useState(false);

  const handleLogoutClick = async () => {
    try {
      await fetch('/logout', { method: 'POST', credentials: 'include' });
      onLogout();
    } catch (error) {
      console.error('退出登录失败:', error);
    }
  };

  return (
    <>
      <nav className="main-navbar">
        <div className="navbar-left">
          {user ? (
            <button onClick={handleLogoutClick} className="navbar-button logout-button">
              退出登录
            </button>
          ) : (
            <button onClick={() => setShowAuth(true)} className="navbar-button login-button">
              注册 / 登录
            </button>
          )}
        </div>
        <div className="navbar-right">
          {user && (
            <NavLink to="/emails" className="navbar-link">
              账单中心
            </NavLink>
          )}
        </div>
      </nav>
      {showAuth && (
        <Auth
          onLogin={(userData) => {
            onLogin(userData);
            setShowAuth(false);
          }}
          onClose={() => setShowAuth(false)}
        />
      )}
    </>
  );
}

export default Navbar;
