import React, { useState } from 'react';
import axios from 'axios';
import { useNavigate } from 'react-router-dom';

// --- FIX: Change API_BASE_URL to relative path for Cloudflare Worker proxy ---
const API_BASE_URL = '/api'; 

function LoginForm({ onLogin }) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    try {
      const response = await axios.post(`${API_BASE_URL}/auth.php?action=login`, {
        email,
        password,
      });

      if (response.data.success && response.data.token) {
        localStorage.setItem('token', response.data.token);
        
        if (onLogin) {
            onLogin(response.data.user);
        }
        
        navigate('/');
        window.location.reload();
      } else {
        setError(response.data.message || '登录失败，请检查您的凭据。');
      }
    } catch (err) {
      console.error("Login error:", err);
      if (err.response && err.response.data && err.response.data.message) {
        setError(err.response.data.message);
      } else {
        setError('登录过程中发生网络或服务器错误。');
      }
    }
  };

  return (
    <div className="auth-form-container">
      <h2>用户登录</h2>
      <form onSubmit={handleSubmit} className="auth-form">
        <div className="form-group">
          <label htmlFor="email">邮箱:</label>
          <input
            type="email"
            id="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </div>
        <div className="form-group">
          <label htmlFor="password">密码:</label>
          <input
            type="password"
            id="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </div>
        <button type="submit" className="auth-button">登录</button>
      </form>
      {error && <p className="error-message">{error}</p>}
    </div>
  );
}

export default LoginForm;
