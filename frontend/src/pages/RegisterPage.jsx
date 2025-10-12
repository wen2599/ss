import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import './LoginPage.css'; // Reusing login page styles
import { registerUser, checkEmailAuthorization } from '../api';

function RegisterPage() {
    const [username, setUsername] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [isCheckingEmail, setIsCheckingEmail] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const navigate = useNavigate();

    const handleEmailBlur = async () => {
        if (!email) return;
        setIsCheckingEmail(true);
        setError('');
        try {
            const res = await checkEmailAuthorization(email);
            if (!res.is_authorized) {
                setError('该邮箱未被授权注册，请联系管理员。');
            }
        } catch (err) {
            setError(err.message || '检查邮箱授权时出错。');
        } finally {
            setIsCheckingEmail(false);
        }
    };

    const handleSubmit = async (event) => {
        event.preventDefault();
        setError('');
        setSuccess('');
        setIsLoading(true);

        try {
            const response = await registerUser({ username, email, password });
            if (response.success) {
                setSuccess('注册成功！您现在可以登录了。');
                setTimeout(() => navigate('/login'), 2000);
            } else {
                setError(response.message || '注册失败。');
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
                        <label>用户名</label>
                        <input
                            type="text"
                            value={username}
                            onChange={(e) => setUsername(e.target.value)}
                            required
                            disabled={isLoading}
                        />
                    </div>
                    <div className="form-group">
                        <label>电子邮件</label>
                        <input
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            onBlur={handleEmailBlur}
                            required
                            disabled={isLoading}
                        />
                        {isCheckingEmail && <p>正在检查邮箱...</p>}
                    </div>
                    <div className="form-group">
                        <label>密码</label>
                        <input
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            required
                            disabled={isLoading}
                        />
                    </div>
                    <button type="submit" className="btn" disabled={isLoading || isCheckingEmail}>
                        {isLoading ? '注册中...' : '注册'}
                    </button>
                    {error && <p className="error-message">{error}</p>}
                    {success && <p className="success-message">{success}</p>}
                </form>
                <div className="toggle-link">
                    <p>已经有账户了？ <Link to="/login">立即登录</Link></p>
                </div>
            </div>
        </div>
    );
}

export default RegisterPage;