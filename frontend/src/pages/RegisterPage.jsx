import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import './LoginPage.css'; // Reusing login page styles
import { registerUser } from '../api';
import { useAuth } from '../context/AuthContext.jsx';

function RegisterPage() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const navigate = useNavigate();
    const { login } = useAuth();

    const handleSubmit = async (event) => {
        event.preventDefault();
        setError('');
        setIsLoading(true);

        try {
            const response = await registerUser({ username: email, email, password });
            if (response.user) {
                login(response.user);
                navigate('/bills');
            } else {
                setError(response.error || '注册失败。');
            }
        } catch (err) {
            setError(err.message || '注册时发生错误。');
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="auth-page">
            <div className="card">
                <h1>创建新账户</h1>
                <form onSubmit={handleSubmit}>
                    <div className="form-group">
                        <label>电子邮件</label>
                        <input
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            required
                            disabled={isLoading}
                        />
                    </div>
                    <div className="form-group">
                        <label>密码</label>
                        <input
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            required
                            disabled={isLoading}
                            autoComplete="new-password"
                        />
                    </div>
                    <button type="submit" className="btn" disabled={isLoading}>
                        {isLoading ? '注册中...' : '注册'}
                    </button>
                    {error && <p className="error-message">{error}</p>}
                </form>
                <div className="toggle-link">
                    <p>已经有账户了？ <Link to="/login">立即登录</Link></p>
                </div>
            </div>
        </div>
    );
}

export default RegisterPage;