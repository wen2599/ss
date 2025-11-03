import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { loginUser } from '../api/auth';

const LoginPage = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const { login } = useAuth();
    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            const data = await loginUser(email, password);
            if (data.token) {
                login(data.token);
                navigate('/');
            } else {
                setError(data.error || '登录失败');
            }
        } catch (err) {
            setError('网络错误或服务器无响应');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div>
            <h2>登录</h2>
            <form onSubmit={handleSubmit}>
                <div>
                    <label>邮箱:</label>
                    <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
                </div>
                <div>
                    <label>密码:</label>
                    <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
                </div>
                {error && <p style={{ color: 'red' }}>{error}</p>}
                <button type="submit" disabled={loading}>{loading ? '登录中...' : '登录'}</button>
            </form>
            <p>还没有账号？ <Link to="/register">去注册</Link></p>
        </div>
    );
};

export default LoginPage;