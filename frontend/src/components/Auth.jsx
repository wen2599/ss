import React, { useState } from 'react';
import Login from './Login';
import Register from './Register';
import './Auth.css';

function Auth({ onClose, onLogin }) {
    const [isLoginView, setIsLoginView] = useState(true);

    const handleSuccessfulLogin = (userData) => {
        if (onLogin) {
            onLogin(userData);
        }
        onClose(); // Close modal on successful login
    };

    const handleSuccessfulRegister = () => {
        setIsLoginView(true); // Switch to login view after registration
    };

    const handleBackdropClick = (e) => {
        if (e.target === e.currentTarget) {
            onClose();
        }
    };

    return (
        <div className="auth-modal-backdrop" onClick={handleBackdropClick}>
            <div className="auth-container card">
                <div className="auth-header">
                    <h2>{isLoginView ? '登录账户' : '创建新账户'}</h2>
                    <p className="secondary-text">
                        {isLoginView ? '欢迎回来！' : '很高兴认识你！'}
                    </p>
                </div>

                {isLoginView ? (
                    <Login onLogin={handleSuccessfulLogin} />
                ) : (
                    <Register onRegister={handleSuccessfulRegister} />
                )}

                <div className="auth-toggle">
                    <button onClick={() => setIsLoginView(!isLoginView)} className="auth-toggle-button">
                        {isLoginView ? '还没有账户？立即注册' : '已有账户？前往登录'}
                    </button>
                </div>
                 <button onClick={onClose} className="close-button">×</button>
            </div>
        </div>
    );
}

export default Auth;
