import React, { useState } from 'react';
import axios from 'axios';
import { useNavigate, Link } from 'react-router-dom';

const RegisterPage = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError('');

        try {
            await axios.post('/api/register.php', { email, password });
            alert('注册成功！现在您可以登录了。');
            navigate('/login');
        } catch (err) {
            setError(err.response?.data?.message || '注册失败，请重试。');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="card auth-page">
            <h2>用户注册</h2>
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
                    <label htmlFor="password">密码 (最少8位)</label>
                    <input
                        type="password"
                        id="password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        required
                        minLength="8"
                    />
                </div>
                {error && <p className="error">{error}</p>}
                <button type="submit" disabled={loading}>
                    {loading ? '注册中...' : '注册'}
                </button>
            </form>
            <p>
                已有账户？ <Link to="/login">返回登录</Link>
            </p>
        </div>
    );
};

export default RegisterPage;
