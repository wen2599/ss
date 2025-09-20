import React, { useState } from 'react';
import axios from 'axios';
import { useAuth } from '../context/AuthContext';
import { useNavigate, Link } from 'react-router-dom';
import './Page.css'; // A generic stylesheet for pages

const LoginPage = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const { login } = useAuth();
    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError('');

        const payload = { email, password };

        try {
            const apiUrl = '/api/login.php';
            const response = await axios.post(apiUrl, payload, {
                withCredentials: true,
            });

            if (response.data.success) {
                login(response.data.user); // Update auth context
                navigate('/'); // Redirect to home page
            } else {
                setError(response.data.message || '登录失败，请检查您的凭证。');
            }
        } catch (err) {
            setError(err.response?.data?.message || '登录时发生错误。');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="page-container">
            <div className="form-container">
                <h2>用户登录</h2>
                <form onSubmit={handleSubmit}>
                    {error && <p className="error">{error}</p>}
                    <div className="form-group">
                        <label htmlFor="login-email">邮箱</label>
                        <input
                            type="email"
                            id="login-email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            required
                        />
                    </div>
                    <div className="form-group">
                        <label htmlFor="login-password">密码</label>
                        <input
                            type="password"
                            id="login-password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            required
                        />
                    </div>
                    <button type="submit" disabled={loading}>
                        {loading ? '登录中...' : '登录'}
                    </button>
                </form>
                <p className="redirect-link">
                    还没有账户？ <Link to="/register">在此注册</Link>
                </p>
            </div>
        </div>
    );
};

export default LoginPage;
