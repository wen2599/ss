import React, { useState } from 'react';
import { useAuth } from '../context/AuthContext';

function AuthModal({ onClose }) {
  const [isLoginView, setIsLoginView] = useState(true);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const { login } = useAuth();

  const handleLogin = async (event) => {
    event.preventDefault();
    setError('');
    setSuccessMessage('');
    try {
      const response = await fetch('backend/index.php?action=login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
      });
      const data = await response.json();
      if (data.success) {
        login(data.user);
        onClose(); // Close modal on successful login
      } else {
        setError(data.error || '登录失败。');
      }
    } catch (err) {
      setError('发生错误，请重试。');
    }
  };

  const handleRegister = async (event) => {
    event.preventDefault();
    setError('');
    setSuccessMessage('');
    try {
      const response = await fetch('backend/index.php?action=register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
      });
      const data = await response.json();
      if (data.success) {
        setSuccessMessage('注册成功！请登录。');
        setIsLoginView(true); // Switch to login view
      } else {
        setError(data.error || '注册失败。');
      }
    } catch (err) {
      setError('发生错误，请重试。');
    }
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()}>
        <button className="modal-close-button" onClick={onClose}>&times;</button>
        <h2>{isLoginView ? '登录' : '注册'}</h2>
        <form onSubmit={isLoginView ? handleLogin : handleRegister}>
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
              autoComplete={isLoginView ? "current-password" : "new-password"}
            />
          </div>
          <button type="submit">{isLoginView ? '登录' : '注册'}</button>
        </form>
        {error && <p className="error">{error}</p>}
        {successMessage && <p className="success">{successMessage}</p>}
        <div className="modal-toggle">
          {isLoginView ? (
            <p>还没有账户？ <button type="button" onClick={() => setIsLoginView(false)}>立即注册</button></p>
          ) : (
            <p>已有账户？ <button type="button" onClick={() => setIsLoginView(true)}>立即登录</button></p>
          )}
        </div>
      </div>
    </div>
  );
}

export default AuthModal;
