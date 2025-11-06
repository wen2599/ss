import { useState } from 'react';
import api from '../services/api';
import { Link, useNavigate } from 'react-router-dom';

const RegisterPage = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setSuccess('');
        try {
            const response = await api.post('/user.php?action=register', { email, password });
            if (response.data.success) {
                setSuccess('注册成功！正在重定向到登录...');
                setTimeout(() => navigate('/login'), 2000);
            } else {
                setError(response.data.message || '注册失败。');
            }
        } catch (err) {
            setError(err.response?.data?.message || '发生错误。');
        }
    };

    return (
        <div className="form-container">
            <form onSubmit={handleSubmit}>
                <h2>注册</h2>
                {error && <p className="error-message">{error}</p>}
                {success && <p style={{color: 'green', textAlign: 'center'}}>{success}</p>}
                <div className="form-group">
                    <label>电子邮件</label>
                    <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
                </div>
                <div className="form-group">
                    <label>密码（最少6个字符）</label>
                    <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
                </div>
                <button type="submit" className="btn">注册</button>
                 <p style={{ textAlign: 'center', marginTop: '1rem' }}>
                    已有帐户？ <Link to="/login">登录</Link>
                </p>
            </form>
        </div>
    );
};

export default RegisterPage;
