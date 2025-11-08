// src/pages/AuthPage.jsx
import React, { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

function AuthPage() {
  const [isLogin, setIsLogin] = useState(true);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [message, setMessage] = useState('');
  const [isSuccess, setIsSuccess] = useState(false);
  const [loading, setLoading] = useState(false);
  
  const { login, register } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const from = location.state?.from?.pathname || '/emails';

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setMessage('');
    setIsSuccess(false);
    try {
      if (isLogin) {
        await login(email, password);
        navigate(from, { replace: true });
      } else {
        const response = await register(email, password);
        setMessage(response.message + " 现在可以登录了。");
        setIsSuccess(true);
        setIsLogin(true);
      }
    } catch (error) {
      setMessage(error.message);
      setIsSuccess(false);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="card" style={{ maxWidth: '400px', margin: 'auto', marginTop: '4rem' }}>
      <h2>{isLogin ? '登录' : '注册'}</h2>
      <form onSubmit={handleSubmit}>
        <div>
          <label htmlFor="email">邮箱地址</label>
          <input id="email" type="email" value={email} onChange={e => setEmail(e.target.value)} required autoComplete="email" disabled={loading} />
        </div>
        <div>
          <label htmlFor="password">密码</label>
          <input id="password" type="password" value={password} onChange={e => setPassword(e.target.value)} required autoComplete={isLogin ? "current-password" : "new-password"} disabled={loading} />
        </div>
        <button type="submit" disabled={loading}>{loading ? '处理中...' : (isLogin ? '登录' : '注册')}</button>
      </form>
      <p>
        {isLogin ? '还没有账户?' : '已有账户?'}
        <button onClick={() => { setIsLogin(!isLogin); setMessage(''); }} className="link-button" disabled={loading}>
          切换到{isLogin ? '注册' : '登录'}
        </button>
      </p>
      {message && <p className={`message ${isSuccess ? 'success' : ''}`}>{message}</p>}
    </div>
  );
}

export default AuthPage;