import React from 'react';
import { useAuth } from '../../context/AuthContext';
import { useNavigate } from 'react-router-dom';

const Header = () => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <header className="header">
      <div className="header-content">
        <h1>Dashboard</h1>
        <div className="user-menu">
          <span>{user?.email}</span>
          <button onClick={handleLogout}>Logout</button>
        </div>
      </div>
    </header>
  );
};

export default Header;
