import React, { useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { useNavigate } from 'react-router-dom';

const RegisterForm = () => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const { register } = useAuth();
    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        try {
            await register(email, password);
            navigate('/login');
        } catch (err) {
            setError(err.message || '注册失败，请稍后再试。');
        }
    };

    return (
        <form onSubmit={handleSubmit} className="form-container">
            {error && <p className="error-message">{error}</p>}
            <input
                type="email"
                placeholder="电子邮箱"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
            />
            <input
                type="password"
                placeholder="密码"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
            />
            <button type="submit" className="btn">注册</button>
        </form>
    );
};

export default RegisterForm;
