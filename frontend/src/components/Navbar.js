import React from 'react';
import { Link } from 'react-router-dom';
import './Navbar.css';

const Navbar = () => {
    return (
        <nav className="navbar">
            <div className="navbar-container">
                <Link to="/" className="navbar-logo">
                    首页
                </Link>
                <ul className="nav-menu">
                    <li className="nav-item">
                        <Link to="/bills" className="nav-links">
                            账单中心
                        </Link>
                    </li>
                </ul>
            </div>
        </nav>
    );
};

export default Navbar;
