import React, { useState } from 'react';
import './Form.css';

// This component is now a pure form, managed by the Auth component.
const Register = ({ onRegister }) => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setSuccess('');

        try {
            const response = await fetch('/register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password }),
            });

            const data = await response.json();

            if (!response.ok) {
                if (response.status === 403) {
                    throw new Error('此邮箱未被授权注册，请联系管理员。');
                } 
                throw new Error(data.error || '注册失败');
            }

            setSuccess('注册成功！请切换到登录页面。');

            // Call the onRegister prop to notify the Auth component,
            // which can then decide to switch the view.
            if (onRegister) {
                setTimeout(onRegister, 1500);
            }

        } catch (err) {
            setError(err.message);
        }
    };

    return (
        <form className="auth-form" onSubmit={handleSubmit}>
            {error && <p className="error-message">{error}</p>}
            {success && <p className="success-message">{success}</p>}
            <div className="form-group">
                <label htmlFor="register-email">邮箱地址</label>
                <input
                    id="register-email"
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                    placeholder="请输入有效的邮箱地址"
                    autoComplete="email"
                />
            </div>
            <div className="form-group">
                <label htmlFor="register-password">设置密码</label>
                <input
                    id="register-password"
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
                    placeholder="至少6位字符"
                    autoComplete="new-password"
                />
            </div>
            <div className="form-actions">
                <button type="submit" className="button-primary">创建账户</button>
            </div>
        </form>
    );
};

export default Register;