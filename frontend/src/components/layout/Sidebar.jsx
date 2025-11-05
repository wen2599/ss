import React from 'react';
import { NavLink } from 'react-router-dom';

const Sidebar = () => {
  return (
    <div className="sidebar">
      <div className="sidebar-header">
        <h2>竞猜平台</h2>
      </div>
      <ul className="sidebar-menu">
        <li><NavLink to="/dashboard">Dashboard</NavLink></li>
        <li><NavLink to="/results">Lottery Results</NavLink></li>
        <li><NavLink to="/my-bets">My Bets</NavLink></li>
        <li><NavLink to="/how-to-play">How to Play</NavLink></li>
        <li><NavLink to="/profile">Profile</NavLink></li>
      </ul>
    </div>
  );
};

export default Sidebar;
