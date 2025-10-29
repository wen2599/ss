import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import authService from '../services/auth';

const RegisterPage = () => {
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      const response = await authService.register(username, email, password);
      setMessage(response.message || '注册成功！请登录。');
      setError('');
      // 注册成功后可以考虑自动跳转到登录页
      // navigate('/login'); 
    } catch (err) {
      setError(err.message || '注册失败');
      setMessage('');
    }
  };

  return (
    <div>
      <h1>注册</h1>
      <form onSubmit={handleSubmit}>
        <div>
          <label htmlFor="username">用户名:</label>
          <input
            type="text"
            id="username"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            required
          />
        </div>
        <div>
          <label htmlFor="email">邮箱:</label>
          <input
            type="email"
            id="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </div>
        <div>
          <label htmlFor="password">密码:</label>
          <input
            type="password"
            id="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </div>
        {error && <p style={{ color: 'red' }}>{error}</p>}
        {message && <p style={{ color: 'green' }}>{message}</p>}
        <button type="submit">注册</button>
      </form>
      <p>已经有账户？ <Link to="/login">登录</Link></p>
    </div>
  );
};

export default RegisterPage;
