import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const RegisterPage = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const navigate = useNavigate();
  const { register } = useAuth();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    if (password.length < 6) {
      setError('密码长度不能少于6位');
      return;
    }
    try {
      const response = await register(email, password);
      setSuccess(response.data.message + ' 3秒后将跳转到登录页面...');
      setTimeout(() => navigate('/login'), 3000);
    } catch (err) {
      setError(err.response?.data?.message || '注册失败，请稍后再试。');
    }
  };

  return (
    <div className="form-container">
      <h2>注册</h2>
      <form onSubmit={handleSubmit}>
        <input type="email" placeholder="邮箱" value={email} onChange={(e) => setEmail(e.target.value)} required />
        <input type="password" placeholder="密码" value={password} onChange={(e) => setPassword(e.target.value)} required />
        {error && <p className="error">{error}</p>}
        {success && <p className="success">{success}</p>}
        <button type="submit" className="primary">注册</button>
      </form>
      <p>已有账号？ <Link to="/login">点击登录</Link></p>
    </div>
  );
};

export default RegisterPage;