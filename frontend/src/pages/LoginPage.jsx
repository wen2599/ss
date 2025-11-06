import { useState } from 'react';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { Link, useNavigate } from 'react-router-dom';

const LoginPage = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const { login } = useAuth();
    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        try {
            const response = await api.post('/user.php?action=login', { email, password });
            if (response.data.success) {
                login(response.data.token);
                navigate('/');
            } else {
                setError(response.data.message || '登录失败。');
            }
        } catch (err) {
            setError(err.response?.data?.message || '发生错误。');
        }
    };

    return (
        <div className="form-container">
            <form onSubmit={handleSubmit}>
                <h2>登录</h2>
                {error && <p className="error-message">{error}</p>}
                <div className="form-group">
                    <label>电子邮件</label>
                    <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
                </div>
                <div className="form-group">
                    <label>密码</label>
                    <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
                </div>
                <button type="submit" className="btn">登录</button>
                <p style={{ textAlign: 'center', marginTop: '1rem' }}>
                    没有帐户？ <Link to="/register">注册</Link>
                </p>
            </form>
        </div>
    );
};

export default LoginPage;
