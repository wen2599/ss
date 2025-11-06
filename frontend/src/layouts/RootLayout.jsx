import React from 'react';
import { Link, Outlet, useNavigate } from 'react-router-dom';

function RootLayout() {
    const navigate = useNavigate();
    const authToken = localStorage.getItem('authToken');

    const handleLogout = () => {
        localStorage.removeItem('authToken');
        navigate('/login');
    };

    return (
        <>
            <nav className="navbar">
                <div className="nav-container">
                    <Link to="/" className="nav-logo">Lottery App</Link>
                    <ul className="nav-menu">
                        {authToken ? (
                            <>
                                <li className="nav-item">
                                    <Link to="/" className="nav-links">Home</Link>
                                </li>
                                <li className="nav-item">
                                    <button onClick={handleLogout} className="nav-links-button">Logout</button>
                                </li>
                            </>
                        ) : (
                            <>
                                <li className="nav-item">
                                    <Link to="/login" className="nav-links">Login</Link>
                                </li>
                                <li className="nav-item">
                                    <Link to="/register" className="nav-links">Register</Link>
                                </li>
                            </>
                        )}
                    </ul>
                </div>
            </nav>
            <main>
                <Outlet />
            </main>
        </>
    );
}

export default RootLayout;