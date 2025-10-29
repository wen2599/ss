import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import authService from '../services/auth';

const LoginPage = () => {
  const [email, setEmail] = useState(''); // 接收 email
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      await authService.login(email, password); // 调用 authService.login 传入 email
      navigate('/'); // 登录成功后跳转到主页
    } catch (err) {
      setError(err.message || '登录失败');
    }
  };

  return (
    <div>
      <h1>登录</h1>
      <form onSubmit={handleSubmit}>
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
        <button type="submit">登录</button>
      </form>
      <p>还没有账户？ <Link to="/register">注册</Link></p>
    </div>
  );
};

export default LoginPage;
