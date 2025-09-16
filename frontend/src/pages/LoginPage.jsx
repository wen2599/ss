import React, { useState } from 'react';
import axios from 'axios';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const LoginPage = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const navigate = useNavigate();
    const { login } = useAuth();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError('');

        try {
            const response = await axios.post('/api/login.php', { email, password }, { withCredentials: true });
            if (response.data.success) {
                login(response.data.user);
                navigate('/');
            }
        } catch (err) {
            setError(err.response?.data?.message || '登录失败，请检查您的凭证。');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="card auth-page">
            <h2>用户登录</h2>
            <form onSubmit={handleSubmit}>
                <div className="form-group">
                    <label htmlFor="email">邮箱</label>
                    <input
                        type="email"
                        id="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
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
                {error && <p className="error">{error}</p>}
                <button type="submit" disabled={loading}>
                    {loading ? '登录中...' : '登录'}
                </button>
            </form>
            <p>
                还没有账户？ <Link to="/register">点击这里注册</Link>
            </p>
        </div>
    );
};

export default LoginPage;
