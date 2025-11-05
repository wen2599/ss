import React, { useState } from 'react';
import { api } from '../api';
import { useNavigate } from 'react-router-dom';

function RegisterForm() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [loading, setLoading] = useState(false);
    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setMessage('');

        if (password.length < 6) {
            setError('密码长度不能少于6位。');
            return;
        }
        if (password !== confirmPassword) {
            setError('两次输入的密码不一致。');
            return;
        }
        
        setLoading(true);
        try {
            const response = await api.register({ email, password });
            setMessage(response.message + ' 2秒后将跳转到登录页面...');
            setTimeout(() => {
                navigate('/login');
            }, 2000);
        } catch (err) {
            setError(err.message || '注册失败，该邮箱可能已被使用。');
        } finally {
            setLoading(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="form-container">
            <input
                type="email"
                placeholder="请输入您的邮箱"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                disabled={loading}
            />
            <input
                type="password"
                placeholder="请输入密码 (至少6位)"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                minLength="6"
                disabled={loading}
            />
            <input
                type="password"
                placeholder="请再次确认密码"
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                required
                disabled={loading}
            />
            <button type="submit" className="btn" disabled={loading}>
                {loading ? '注册中...' : '立即注册'}
            </button>
            {error && <p style={{ color: 'var(--error-color)', textAlign: 'center' }}>{error}</p>}
            {message && <p style={{ color: 'var(--success-color)', textAlign: 'center' }}>{message}</p>}
        </form>
    );
}

export default RegisterForm;