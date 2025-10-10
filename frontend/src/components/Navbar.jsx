import React from 'react';
import { NavLink } from 'react-router-dom'; // Using NavLink for active link styling
import './Navbar.css';

const Navbar = () => {
  return (
    <nav className="navbar">
      <div className="navbar-container">
        <NavLink to="/" className="navbar-logo">
          应用中心
        </NavLink>
        <ul className="nav-menu">
          <li className="nav-item">
            <NavLink to="/" className={({ isActive }) => "nav-links" + (isActive ? " active" : "")}>
              开奖
            </NavLink>
          </li>
          <li className="nav-item">
            <NavLink to="/bills" className={({ isActive }) => "nav-links" + (isActive ? " active" : "")}>
              账单
            </NavLink>
          </li>
          <li className="nav-item">
            <NavLink to="/login" className={({ isActive }) => "nav-links" + (isActive ? " active" : "")}>
              登录
            </NavLink>
          </li>
          <li className="nav-item">
            <NavLink to="/register" className="nav-links-mobile">
              注册
            </NavLink>
          </li>
        </ul>
      </div>
    </nav>
  );
};

export default Navbar;