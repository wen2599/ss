import React from 'react';
import { Link } from 'react-router-dom';
import './Navbar.css';

const Navbar = () => {
    return (
        <nav className="navbar">
            <div className="navbar-brand">
                <Link to="/">账单中心</Link>
            </div>
            <ul className="navbar-links">
                <li><Link to="/bills">我的账单</Link></li>
            </ul>
        </nav>
    );
};

export default Navbar;
