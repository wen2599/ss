import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import AuthLayout from '../components/AuthLayout';

function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const navigate = useNavigate();
  const { login } = useAuth();

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError('');

    if (!email || !password) {
      setError('需要填写邮箱和密码。');
      return;
    }

    try {
      const response = await fetch('/api/login.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email, password }),
      });

      const data = await response.json();

      if (data.success) {
        login(data.user);
        navigate('/');
      } else {
        setError(data.error || '登录失败。');
      }
    } catch (err) {
      setError('发生错误，请重试。');
    }
  };

  return (
    <AuthLayout>
      <h1>登录</h1>
      <form onSubmit={handleSubmit}>
        <div>
          <label htmlFor="email">邮箱：</label>
          <input
            type="email"
            id="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            autoComplete="email"
          />
        </div>
        <div>
          <label htmlFor="password">密码：</label>
          <input
            type="password"
            id="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            autoComplete="current-password"
          />
        </div>
        <button type="submit">登录</button>
      </form>
      {error && <p className="error">{error}</p>}
      <p>
        还没有账户？ <Link to="/register">注册</Link>
      </p>
    </AuthLayout>
  );
}

export default LoginPage;
