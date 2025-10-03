import React from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import './Navbar.css';

const Navbar = () => {
    const { isAuthenticated, user, logout } = useAuth();
    const navigate = useNavigate();

    const handleLogout = () => {
        logout();
        navigate('/login'); // Redirect to login page after logout
    };

    return (
        <nav className="navbar">
            <div className="navbar-container">
                <NavLink to="/" className="navbar-logo">
                    EmailParser
                </NavLink>
                <ul className="nav-menu">
                    <li className="nav-item">
                        <NavLink to="/" className="nav-links" end>
                            Home
                        </NavLink>
                    </li>
                    {isAuthenticated && (
                        <li className="nav-item">
                            <NavLink to="/parser" className="nav-links">
                                Parser
                            </NavLink>
                        </li>
                    )}
                </ul>
                <div className="nav-auth">
                    {isAuthenticated ? (
                        <>
                            <span className="navbar-user">Welcome, {user.username}</span>
                            <button onClick={handleLogout} className="btn btn-outline">
                                Logout
                            </button>
                        </>
                    ) : (
                        <>
                            <NavLink to="/login" className="btn btn-secondary">
                                Login
                            </NavLink>
                            <NavLink to="/register" className="btn btn-primary">
                                Sign Up
                            </NavLink>
                        </>
                    )}
                </div>
            </div>
        </nav>
    );
};

export default Navbar;