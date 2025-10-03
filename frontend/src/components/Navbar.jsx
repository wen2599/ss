import React, { useState } from 'react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import AuthModal from './AuthModal';
import './Navbar.css';

/**
 * The main navigation bar for the application.
 * It displays different links based on the user's authentication status
 * and provides the entry point for login and registration via the AuthModal.
 */
function Navbar() {
  const { isAuthenticated, logout } = useAuth();
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  /**
   * Handles the user logout process, including API call and error handling.
   */
  const handleLogout = async () => {
    setIsLoggingOut(true);
    try {
      await logout();
      // On successful logout, the AuthContext will trigger a re-render.
    } catch (error) {
      alert(`退出登录失败: ${error.message || '请检查您的网络连接。'}`);
    } finally {
      setIsLoggingOut(false);
    }
  };

  return (
    <>
      <nav className="navbar">
        <div className="navbar-left">
          <NavLink to="/" className="navbar-brand">彩票助手</NavLink>
        </div>
        <div className="navbar-right">
          {isAuthenticated ? (
            // Authenticated user view
            <>
              <NavLink to="/bills" className="navbar-link">我的账单</NavLink>
              <NavLink to="/lottery-results" className="navbar-link">开奖结果</NavLink>
              <button onClick={handleLogout} className="navbar-button" disabled={isLoggingOut}>
                {isLoggingOut ? '正在退出...' : '退出登录'}
              </button>
            </>
          ) : (
            // Guest view
            <button onClick={() => setIsModalOpen(true)} className="navbar-button">
              登录 / 注册
            </button>
          )}
        </div>
      </nav>

      {/* The authentication modal is rendered here when needed */}
      {isModalOpen && <AuthModal onClose={() => setIsModalOpen(false)} />}
    </>
  );
}

export default Navbar;