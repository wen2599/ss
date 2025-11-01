import React, { useState } from 'react';
import axios from 'axios';
import { useNavigate } from 'react-router-dom';

const API_LOGIN_URL = '/api_router.php?endpoint=auth&action=login';

function LoginForm({ onLogin }) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    try {
      const response = await axios.post(API_LOGIN_URL, {
        email,
        password,
      });

      if (response.data.success && response.data.token) {
        localStorage.setItem('authToken', response.data.token);
        // Also store user ID from the new API response
        if (response.data.user && response.data.user.id) {
            localStorage.setItem('userId', response.data.user.id);
        }
        
        // Pass the token and user ID to the parent component
        onLogin(response.data.token, response.data.user.id);

        navigate('/');
      } else {
        setError(response.data.message || 'Login failed. Please check your credentials.');
      }
    } catch (err) {
      console.error("Login error:", err);
      if (err.response && err.response.data && err.response.data.message) {
        setError(err.response.data.message);
      } else {
        setError('A network or server error occurred during login.');
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
