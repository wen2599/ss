import React, { useState } from 'react';
import { api } from '../api';
import { useNavigate } from 'react-router-dom';

function RegisterForm() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setMessage('');

        if (password !== confirmPassword) {
            setError('两次输入的密码不一致。');
            return;
        }

        try {
            const response = await api.register({ email, password });
            setMessage(response.message + ' 正在跳转到登录页面...');
            setTimeout(() => {
                navigate('/login');
            }, 2000);
        } catch (err) {
            setError(err.message || '注册失败，请稍后再试。');
        }
    };

    return (
        <form onSubmit={handleSubmit} className="form-container">
            <input
                type="email"
                placeholder="邮箱"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
            />
            <input
                type="password"
                placeholder="密码"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                minLength="6"
            />
            <input
                type="password"
                placeholder="确认密码"
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                required
            />
            <button type="submit">注册</button>
            {error && <p className="error-message">{error}</p>}
            {message && <p style={{ color: '#7cffcb' }}>{message}</p>}
        </form>
    );
}

export default RegisterForm;