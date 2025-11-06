import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';

function Register() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [loading, setLoading] = useState(false);
    const [isEmailAuthorized, setIsEmailAuthorized] = useState(false);
    const navigate = useNavigate();

    const handleEmailCheck = async (e) => {
        e.preventDefault();
        setError('');
        setMessage('');
        setLoading(true);

        try {
            const response = await fetch(`/api/?action=check_auth&email=${encodeURIComponent(email)}`);

            const contentType = response.headers.get("content-type");
            if (!response.ok || !contentType || !contentType.includes("application/json")) {
                throw new Error('服务器响应异常，请稍后重试。');
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || '邮箱授权检查失败。');
            }

            if (data.is_authorized) {
                setIsEmailAuthorized(true);
                setMessage('邮箱已授权，请输入密码完成注册。');
            } else {
                setError('此邮箱未被授权注册。');
            }
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    const handleRegisterSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setMessage('');
        setLoading(true);

        try {
            const response = await fetch('/api/?action=register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password }),
            });

            const contentType = response.headers.get("content-type");
            if (!response.ok || !contentType || !contentType.includes("application/json")) {
                throw new Error('服务器响应异常，请稍后重试。');
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || '注册失败。');
            }

            setMessage('注册成功！2秒后将跳转到登录页面。');
            setTimeout(() => navigate('/login'), 2000);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="auth-container">
            <form onSubmit={isEmailAuthorized ? handleRegisterSubmit : handleEmailCheck} className="auth-form">
                <h2>注册</h2>
                {error && <p className="error">{error}</p>}
                {message && <p className="success">{message}</p>}

                <div className="form-group">
                    <label htmlFor="email">邮箱</label>
                    <input
                        type="email"
                        id="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        required
                        disabled={loading || isEmailAuthorized}
                    />
                </div>

                {isEmailAuthorized && (
                    <div className="form-group">
                        <label htmlFor="password">密码</label>
                        <input
                            type="password"
                            id="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            minLength="6"
                            required
                            disabled={loading}
                        />
                    </div>
                )}

                <button type="submit" disabled={loading}>
                    {loading ? '处理中...' : (isEmailAuthorized ? '注册' : '下一步')}
                </button>
            </form>
        </div>
    );
}

export default Register;
