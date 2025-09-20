import React, { useState } from 'react';
import axios from 'axios';
import { useNavigate, Link } from 'react-router-dom';
import './Page.css'; // A generic stylesheet for pages

const RegisterPage = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [success, setSuccess] = useState('');

    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (password !== confirmPassword) {
            setError('两次输入的密码不匹配。');
            return;
        }
        setLoading(true);
        setError('');
        setSuccess('');

        const payload = { email, password };

        try {
            const apiUrl = '/api/register.php';
            const response = await axios.post(apiUrl, payload);

            if (response.data.success) {
                setSuccess('注册成功！请登录。');
                setTimeout(() => {
                    navigate('/login');
                }, 2000); // Wait 2 seconds before redirecting
            } else {
                setError(response.data.message || '注册失败。');
            }
        } catch (err) {
            setError(err.response?.data?.message || '注册时发生错误。');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="page-container">
            <div className="form-container">
                <h2>创建账户</h2>
                <form onSubmit={handleSubmit}>
                    {error && <p className="error">{error}</p>}
                    {success && <p className="success">{success}</p>}
                    <div className="form-group">
                        <label htmlFor="register-email">邮箱</label>
                        <input
                            type="email"
                            id="register-email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            required
                        />
                    </div>
                    <div className="form-group">
                        <label htmlFor="register-password">密码</label>
                        <input
                            type="password"
                            id="register-password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            required
                        />
                    </div>
                    <div className="form-group">
                        <label htmlFor="confirm-password">确认密码</label>
                        <input
                            type="password"
                            id="confirm-password"
                            value={confirmPassword}
                            onChange={(e) => setConfirmPassword(e.target.value)}
                            required
                        />
                    </div>
                    <button type="submit" disabled={loading || success}>
                        {loading ? '注册中...' : '注册'}
                    </button>
                </form>
                <p className="redirect-link">
                    已经有账户了？ <Link to="/login">在此登录</Link>
                </p>
            </div>
        </div>
    );
};

export default RegisterPage;
