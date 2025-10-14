import React from 'react';
import { Link, NavLink } from 'react-router-dom';
import './Navbar.css';

const Navbar = () => {
    return (
        <header className="navbar">
            <Link to="/" className="navbar-logo">电子账单系统</Link>
            <nav>
                <ul className="navbar-links">
                    <li><NavLink to="/bills" className={({ isActive }) => isActive ? 'active' : ''}>我的账单</NavLink></li>
                </ul>
            </nav>
        </header>
    );
};

export default Navbar;