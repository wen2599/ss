import React, { useState } from 'react';
import Login from './Login';
import Register from './Register';
import './Auth.css';

function Auth({ onClose, onLogin }) {
    const [activeTab, setActiveTab] = useState('login'); // 'login' or 'register'

    const handleSuccessfulLogin = (userData) => {
        if (onLogin) {
            onLogin(userData);
        }
        onClose(); // Close modal on successful login
    };

    const handleSuccessfulRegister = () => {
        // Switch to the login tab after a successful registration
        setActiveTab('login');
    };

    const handleBackdropClick = (e) => {
        // Close the modal only if the backdrop itself is clicked
        if (e.target === e.currentTarget) {
            onClose();
        }
    };

    return (
        <div className="auth-modal-backdrop" onClick={handleBackdropClick}>
            <div className="auth-container card">
                <div className="auth-tabs">
                    <button
                        className={`tab-button ${activeTab === 'login' ? 'active' : ''}`}
                        onClick={() => setActiveTab('login')}
                    >
                        登录
                    </button>
                    <button
                        className={`tab-button ${activeTab === 'register' ? 'active' : ''}`}
                        onClick={() => setActiveTab('register')}
                    >
                        注册
                    </button>
                </div>

                <div className="auth-content">
                    {activeTab === 'login' ? (
                        <Login onLogin={handleSuccessfulLogin} />
                    ) : (
                        <Register onRegister={handleSuccessfulRegister} />
                    )}
                </div>

                <button onClick={onClose} className="close-button" aria-label="关闭">×</button>
            </div>
        </div>
    );
}

export default Auth;
