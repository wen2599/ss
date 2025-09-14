import React, { useState } from 'react';
import { useAuth } from '../contexts/AuthContext';
import './Auth.css';

const Auth = ({ onClose, onLoginSuccess }) => {
    const [isLogin, setIsLogin] = useState(true);
    const [phone, setPhone] = useState('');
    const [password, setPassword] = useState('');
    const { login, register, error, clearError } = useAuth();

    const handleSubmit = async (e) => {
        e.preventDefault();
        clearError();
        try {
            isLogin ? await login(phone, password) : await register(phone, password);
            onLoginSuccess();
            onClose();
        } catch (error) {
            // Error is already set in the AuthContext
        }
    };

    return (
        <div className="modal-backdrop">
            <div className="modal-content">
                <h2>{isLogin ? '登录' : '注册'}</h2>
                <button onClick={onClose} className="close-button">&times;</button>
                <form onSubmit={handleSubmit}>
                    <div className="form-group">
                        <label htmlFor="phone">手机号</label>
                        <input
                            type="text"
                            id="phone"
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                            required
                        />
                    </div>
                    <div className="form-group">
                        <label htmlFor="password">密码</label>
                        <input
                            type="password"
                            id="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            required
                        />
                    </div>
                    {error && <p className="error-message">{error}</p>}
                    <button type="submit" className="submit-button">
                        {isLogin ? '登录' : '注册'}
                    </button>
                </form>
                <p className="toggle-auth" onClick={() => setIsLogin(!isLogin)}>
                    {isLogin ? '还没有账户？点击注册' : '已有账户？点击登录'}
                </p>
            </div>
        </div>
    );
};

export default Auth;
