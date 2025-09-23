import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import AuthLayout from '../components/AuthLayout';

function RegisterPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const navigate = useNavigate();

  const handleSubmit = async (event) => {
    event.preventDefault();
    setError('');
    setMessage('');

    if (!email || !password) {
      setError('需要填写邮箱和密码。');
      return;
    }

    try {
      const response = await fetch('/register', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email, password }),
      });

      const data = await response.json();

      if (data.success) {
        setMessage('注册成功！正在跳转到登录页面...');
        setTimeout(() => {
          navigate('/login');
        }, 2000);
      } else {
        setError(data.error || '注册失败。');
      }
    } catch (err) {
      setError('发生错误，请重试。');
    }
  };

  return (
    <AuthLayout>
      <h1>注册</h1>
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
            autoComplete="new-password"
          />
        </div>
        <button type="submit">注册</button>
      </form>
      {error && <p className="error">{error}</p>}
      {message && <p className="success">{message}</p>}
      <p>
        已经有账户了？ <Link to="/login">登录</Link>
      </p>
    </AuthLayout>
  );
}

export default RegisterPage;
