import React from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const Home = () => {
    const { isAuthenticated, user } = useAuth();

    return (
        <div className="home-container">
            <h1>Welcome to the Email Parser</h1>
            <p>Your intelligent tool for extracting key information from emails.</p>

            {isAuthenticated ? (
                <div className="welcome-back">
                    <h2>Hello, {user.username}!</h2>
                    <p>You are logged in and ready to go.</p>
                    <Link to="/parser" className="btn btn-primary">Go to Parser</Link>
                </div>
            ) : (
                <div className="call-to-action">
                    <p>Please log in or register to get started.</p>
                    <div>
                        <Link to="/login" className="btn btn-secondary">Login</Link>
                        <Link to="/register" className="btn btn-primary">Register</Link>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Home;