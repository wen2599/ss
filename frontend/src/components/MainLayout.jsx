import React from 'react';
import { Outlet, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import '../App.css'; // Re-using styles from App.css

function MainLayout() {
  const { user, logout } = useAuth();

  return (
    <div className="container">
      <header className="app-header">
        <h1>邮件账单系统</h1>
        <nav>
          <Link to="/">邮件处理器</Link>
          <Link to="/bills">我的账单</Link>
        </nav>
        <div className="user-info">
          {user && <span>欢迎, {user.email}</span>}
          <button onClick={logout}>登出</button>
        </div>
      </header>
      <main>
        <Outlet />
      </main>
    </div>
  );
}

export default MainLayout;
