import React from 'react';
import { NavLink } from 'react-router-dom';
import './Navbar.css';

const Navbar = () => {
  return (
    <header className="app-header">
      <div className="app-header-container">
        <NavLink to="/" className="app-logo">
          <h1>幸运中心</h1>
        </NavLink>
        <nav className="app-nav">
          <ul className="nav-menu">
            <li className="nav-item">
              <NavLink to="/" className={({ isActive }) => "nav-link" + (isActive ? " active" : "")}>
                开奖
              </NavLink>
            </li>
            <li className="nav-item">
              <NavLink to="/bills" className={({ isActive }) => "nav-link" + (isActive ? " active" : "")}>
                账单
              </NavLink>
            </li>
            <li className="nav-item">
              <NavLink to="/login" className={({ isActive }) => "nav-link" + (isActive ? " active" : "")}>
                登录
              </NavLink>
            </li>
            <li className="nav-item">
              <NavLink to="/register" className="nav-link nav-link-button">
                注册
              </NavLink>
            </li>
          </ul>
        </nav>
      </div>
    </header>
  );
};

export default Navbar;