import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import './Navbar.css';

const Navbar = () => {
  const { isLoggedIn, logout, user } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    const result = await logout();
    if (result.success) {
      navigate('/login');
    }
  };

  return (
    <nav className="navbar">
      <div className="navbar-brand">
        <Link to="/">账单与彩票应用</Link>
      </div>
      <ul className="navbar-nav">
        {isLoggedIn ? (
          <>
            <li className="nav-item">
              <Link to="/bills">我的账单</Link>
            </li>
            <li className="nav-item">
              <Link to="/lottery">开奖结果</Link>
            </li>
            <li className="nav-item">
              <span>欢迎, {user?.username || '用户'}!</span>
            </li>
            <li className="nav-item">
              <button onClick={handleLogout} className="nav-link-button">退出登录</button>
            </li>
          </>
        ) : (
          <>
            <li className="nav-item">
              <Link to="/login">登录</Link>
            </li>
            <li className="nav-item">
              <Link to="/register">注册</Link>
            </li>
          </>
        )}
      </ul>
    </nav>
  );
};

export default Navbar;
