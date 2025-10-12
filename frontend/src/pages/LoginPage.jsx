
import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext'; // Corrected path
import { api } from '../api'; // Corrected path

const LoginPage = () => {
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const navigate = useNavigate();
    const { login } = useAuth();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');

        try {
            const response = await api.login(username, password);
            if (response.data.status === 'success') {
                login(response.data.user);
                navigate('/bills');
            } else {
                setError(response.data.message || '登录失败，请检查您的凭据。');
            }
        } catch (err) {
            setError(err.response?.data?.message || '发生网络错误，请稍后再试。');
        }
    };

    return (
        <div className="container">
            <div className="card">
                <h2>用户登录</h2>
                <form onSubmit={handleSubmit}>
                    <div className="form-group">
                        <label htmlFor="username">用户名</label>
                        <input
                            type="text"
                            id="username"
                            required
                            value={username}
                            onChange={(e) => setUsername(e.target.value)}
                        />
                    </div>
                    <div className="form-group">
                        <label htmlFor="password">密码</label>
                        <input
                            type="password"
                            id="password"
                            required
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                        />
                    </div>
                    <button type="submit" className="btn">登录</button>
                    {error && <p className="error-message">{error}</p>}
                </form>
                <div className="toggle-link">
                    <p>还没有账户？ <Link to="/register">立即注册</Link></p>
                </div>
            </div>
        </div>
    );
};

export default LoginPage;
