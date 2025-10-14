import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import './LoginPage.css';
import { loginUser } from '../api';
import { useAuth } from '../context/AuthContext.jsx';

function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const navigate = useNavigate();
  const { login } = useAuth();

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError('');
    setIsLoading(true);

    try {
      const response = await loginUser({ email, password });
      if (response.user) {
        login(response.user);
        navigate('/bills');
      } else {
        setError(response.error || '登录失败，请检查您的凭据。');
      }
    } catch (err) {
      setError(err.message || '登录时发生错误。');
    } finally {
        setIsLoading(false);
    }
  };

  return (
    <div className="auth-page">
      <div className="card">
        <h1>用户登录</h1>
        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label>电子邮件</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              disabled={isLoading}
            />
          </div>
          <div className="form-group">
            <label>密码</label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              disabled={isLoading}
            />
          </div>
          <button type="submit" className="btn" disabled={isLoading}>
            {isLoading ? '登录中...' : '登录'}
          </button>
          {error && <p className="error-message">{error}</p>}
        </form>
        <div className="toggle-link">
          <p>还没有账户？ <Link to="/register">立即注册</Link></p>
        </div>
      </div>
    </div>
  );
}

export default LoginPage;