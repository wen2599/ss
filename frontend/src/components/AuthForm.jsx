import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const AuthForm = ({ formType }) => {
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [email, setEmail] = useState('');
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    const { login } = useAuth();
    const navigate = useNavigate();

    const isLogin = formType === 'login';
    const title = isLogin ? '登录' : '注册';
    const endpoint = isLogin ? '/login' : '/register';
    const switchFormText = isLogin ? '还没有账户？' : '已经有账户了？';
    const switchFormLink = isLogin ? '/register' : '/login';
    const switchFormLinkText = isLogin ? '点此注册' : '点此登录';

    const handleSubmit = async (e) => {
        e.preventDefault();
        setIsLoading(true);
        setError('');
        setSuccess('');

        if (!isLogin && password.length < 8) {
            setError('密码长度至少为8位。');
            setIsLoading(false);
            return;
        }

        const body = isLogin ? { username, password } : { username, email, password };

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || (isLogin ? '登录失败。' : '注册失败。'));
            }

            if (isLogin) {
                login(data.user);
                navigate('/parser');
            } else {
                setSuccess('注册成功！正在跳转到登录页面...');
                setTimeout(() => navigate('/login'), 2000);
            }

        } catch (err) {
            setError(err.message);
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="auth-container">
            <form onSubmit={handleSubmit} className="auth-form">
                <h2>{title}</h2>
                {error && <p className="message error-message">{error}</p>}
                {success && <p className="message success-message">{success}</p>}

                <div className="form-group">
                    <label htmlFor="username">用户名</label>
                    <input
                        type="text"
                        id="username"
                        value={username}
                        onChange={(e) => setUsername(e.target.value)}
                        required
                    />
                </div>

                {!isLogin && (
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
                )}

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

                <button type="submit" className="btn" disabled={isLoading}>
                    {isLoading ? (isLogin ? '正在登录...' : '正在注册...') : title}
                </button>

                <p className="switch-form-text">
                    {switchFormText} <Link to={switchFormLink}>{switchFormLinkText}</Link>
                </p>
            </form>
        </div>
    );
};

export default AuthForm;