import React, { useState } from 'react';
import { useAuth } from '../context/AuthContext';

function AuthModal({ onClose }) {
  const [isLoginView, setIsLoginView] = useState(true);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const { login } = useAuth();

  const handleLogin = async (event) => {
    event.preventDefault();
    if (isLoading) return;

    setError('');
    setSuccessMessage('');
    setIsLoading(true);
    try {
      const response = await fetch('/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
      });
      const data = await response.json();
      if (data.success) {
        login(data.user);
        onClose(); // Close modal on successful login
      } else {
        setError(data.error || '登录失败，请检查您的邮箱和密码。');
      }
    } catch (err) {
      setError('网络或服务器错误，请稍后重试。');
    } finally {
      setIsLoading(false);
    }
  };

  const handleRegister = async (event) => {
    event.preventDefault();
    if (isLoading) return;

    setError('');
    setSuccessMessage('');
    setIsLoading(true);
    try {
      const response = await fetch('/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
      });
      const data = await response.json();
      if (data.success) {
        // Set a more accurate success message based on the backend flow
        setSuccessMessage('注册申请已提交，请等待管理员批准。');
        // Clear form fields after successful submission
        setEmail('');
        setPassword('');
        // Optional: Do not switch to login view automatically, let the user close the modal.
      } else {
        setError(data.error || '注册失败，该邮箱可能已被注册或输入无效。');
      }
    } catch (err) {
      setError('网络或服务器错误，请稍后重试。');
    } finally {
      setIsLoading(false);
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
          <button type="submit" disabled={isLoading}>
            {isLoading
              ? (isLoginView ? '正在登录...' : '正在注册...')
              : (isLoginView ? '登录' : '注册')}
          </button>
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
