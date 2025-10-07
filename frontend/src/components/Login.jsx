import React, { useState } from 'react';
import './Form.css';

// This component is now a pure form, managed by the Auth component.
const Login = ({ onLogin }) => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');

        try {
            const response = await fetch('/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password }),
                credentials: 'include',
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || '登录失败');
            }

            // On success, call the onLogin prop passed from Auth component.
            // Auth component will handle closing the modal.
            onLogin(data.user);

        } catch (err) {
            setError(err.message);
        }
    };

    return (
        <form className="auth-form" onSubmit={handleSubmit}>
            {error && <p className="error-message">{error}</p>}
            <div className="form-group">
                <label htmlFor="login-email">邮箱地址</label>
                <input
                    id="login-email"
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                    placeholder="you@example.com"
                />
            </div>
            <div className="form-group">
                <label htmlFor="login-password">密码</label>
                <input
                    id="login-password"
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
                    placeholder="请输入您的密码"
                />
            </div>
            <div className="form-actions">
                <button type="submit" className="button-primary">登录</button>
            </div>
        </form>
    );
};

export default Login;
