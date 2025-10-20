import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import './RegisterPage.css'; // Re-using LoginPage.css for similar styling

const RegisterPage = () => {
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const { register } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    setSuccess(null);

    if (password !== confirmPassword) {
      setError('密码不匹配。');
      return;
    }

    const result = await register(username, email, password);

    if (result.success) {
      setSuccess(result.message || '注册成功！请登录。');
      setUsername('');
      setEmail('');
      setPassword('');
      setConfirmPassword('');
      // Optionally, redirect to login page after a short delay
      setTimeout(() => {
        navigate('/login');
      }, 2000);
    } else {
      setError(result.message || '注册失败。');
    }
  };

  return (
    <div className="register-page">
      <h2>注册</h2>
      <form onSubmit={handleSubmit} className="register-form">
        {error && <div className="alert error">{error}</div>}
        {success && <div className="alert success">{success}</div>}
        <div className="form-group">
          <label htmlFor="reg-username">用户名：</label>
          <input
            type="text"
            id="reg-username"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            required
          />
        </div>
        <div className="form-group">
          <label htmlFor="reg-email">邮箱：</label>
          <input
            type="email"
            id="reg-email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </div>
        <div className="form-group">
          <label htmlFor="reg-password">密码：</label>
          <input
            type="password"
            id="reg-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </div>
        <div className="form-group">
          <label htmlFor="reg-confirm-password">确认密码：</label>
          <input
            type="password"
            id="reg-confirm-password"
            value={confirmPassword}
            onChange={(e) => setConfirmPassword(e.target.value)}
            required
          />
        </div>
        <button type="submit" className="btn">注册</button>
      </form>
    </div>
  );
};

export default RegisterPage;
