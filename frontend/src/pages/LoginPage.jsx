// src/pages/LoginPage.jsx
import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';

const LoginPage = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const auth = useAuth();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    try {
      // 请求路径更新为指向代理的 proxy.php
      const response = await api.post('/proxy.php?action=login', { email, password });
      if (response.data.status === 'success') {
        auth.login(response.data.token);
      } else {
        setError(response.data.message || '登录失败');
      }
    } catch (err) {
      setError(err.response?.data?.message || '发生网络错误，请稍后再试');
    }
  };

  return (
    <div className="auth-form">
      <h1>登录</h1>
      {error && <p className="error-message">{error}</p>}
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
          <label htmlFor="password">密码</label>
          <input
            type="password"
            id="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </div>
        <button type="submit">登录</button>
      </form>
      <p>还没有账户？ <Link to="/register">立即注册</Link></p>
    </div>
  );
};

export default LoginPage;