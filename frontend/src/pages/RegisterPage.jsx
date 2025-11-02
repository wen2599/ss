// src/pages/RegisterPage.jsx
import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api from '../services/api';

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
    if (password.length < 6) {
      setError('密码必须至少6位');
      return;
    }
    try {
      // 请求路径更新为指向代理的 proxy.php
      const response = await api.post('/proxy.php?action=register', { email, password });
      if (response.data.status === 'success') {
        setSuccess('注册成功！正在跳转到登录页面...');
        setTimeout(() => navigate('/login'), 2000);
      } else {
        setError(response.data.message || '注册失败');
      }
    } catch (err) {
      setError(err.response?.data?.message || '发生网络错误，请稍后再试');
    }
  };

  return (
    <div className="auth-form">
      <h1>注册</h1>
      {error && <p className="error-message">{error}</p>}
      {success && <p style={{ color: 'lightgreen', textAlign: 'center' }}>{success}</p>}
      <form onSubmit={handleSubmit}>
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
        <div className="form-group">
          <label htmlFor="password">密码 (至少6位)</label>
          <input
            type="password"
            id="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </div>
        <button type="submit">注册</button>
      </form>
      <p>已有账户？ <Link to="/login">返回登录</Link></p>
    </div>
  );
};

export default RegisterPage;