import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { registerUser } from '../api/auth';

const RegisterPage = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setMessage('');
        setLoading(true);
        try {
            const data = await registerUser(email, password);
            if (data.success) {
                setMessage('注册成功！请前往登录。');
                setTimeout(() => navigate('/login'), 2000);
            } else {
                setError(data.error || '注册失败');
            }
        } catch (err) {
            setError('网络错误或服务器无响应');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div>
            <h2>注册</h2>
            <form onSubmit={handleSubmit}>
                <div>
                    <label>邮箱:</label>
                    <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
                </div>
                <div>
                    <label>密码:</label>
                    <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
                </div>
                {message && <p style={{ color: 'green' }}>{message}</p>}
                {error && <p style={{ color: 'red' }}>{error}</p>}
                <button type="submit" disabled={loading}>{loading ? '注册中...' : '注册'}</button>
            </form>
            <p>已有账号？ <Link to="/login">去登录</Link></p>
        </div>
    );
};

export default RegisterPage;