import React, { useState } from 'react';
import Login from './Login';
import Register from './Register';
import './Auth.css';

const Auth = ({ user, onLogin, onLogout }) => {
    const [showLogin, setShowLogin] = useState(false);
    const [showRegister, setShowRegister] = useState(false);

    const handleLogout = async () => {
        const response = await fetch('/logout', {
            method: 'POST',
            credentials: 'include',
        });
        const data = await response.json();
        if (data.success) {
            onLogout();
        }
    };

    return (
        <div className="auth-container">
            {user ? (
                <div className="auth-loggedIn">
                    <span>欢迎, {user.username}</span>
                    <button onClick={handleLogout}>退出登录</button>
                </div>
            ) : (
                <div className="auth-loggedOut">
                    <button onClick={() => setShowLogin(true)}>登录</button>
                    <button onClick={() => setShowRegister(true)}>注册</button>
                </div>
            )}

            {showLogin && (
                <Login
                    onClose={() => setShowLogin(false)}
                    onLogin={onLogin}
                />
            )}

            {showRegister && (
                <Register
                    onClose={() => setShowRegister(false)}
                    onRegister={() => {
                        setShowRegister(false);
                        setShowLogin(true); // Switch to login form after successful registration
                    }}
                />
            )}
        </div>
    );
};

export default Auth;