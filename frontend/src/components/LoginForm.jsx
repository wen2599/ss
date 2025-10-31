import React, { useState } from 'react';
import axios from 'axios';
import { useNavigate } from 'react-router-dom';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';

function LoginForm({ onLogin }) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    try {
      const response = await axios.post(`${API_BASE_URL}/login.php`, {
        email,
        password,
      });

      if (response.data.success) {
        // Store token and user_id in local storage
        localStorage.setItem('authToken', response.data.token);
        localStorage.setItem('userId', response.data.user_id);
        onLogin(response.data.token, response.data.user_id);
        navigate('/'); // Redirect to home or dashboard
      } else {
        setError(response.data.message || '登录失败。');
      }
    } catch (err) {
      console.error("Login error:", err);
      if (err.response && err.response.data && err.response.data.message) {
        setError(err.response.data.message);
      } else if (err.message) {
        setError(err.message);
      } else {
        setError('登录过程中发生未知错误。');
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
