import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { login as apiLogin, register as apiRegister } from '../services/api';

/**
 * A reusable form component for both login and registration.
 * @param {{
 *   isLoginView: boolean,
 *   isLoading: boolean,
 *   onSubmit: (e: React.FormEvent<HTMLFormElement>, email: string, pass: string) => void
 * }} props
 */
const AuthForm = ({ isLoginView, isLoading, onSubmit }) => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');

  const handleSubmit = (e) => {
    e.preventDefault();
    onSubmit(e, email, password);
  };

  return (
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
          autoComplete={isLoginView ? "current-password" : "new-password"}
        />
      </div>
      <button type="submit" disabled={isLoading}>
        {isLoading
          ? (isLoginView ? '正在登录...' : '正在注册...')
          : (isLoginView ? '登录' : '注册')}
      </button>
    </form>
  );
};

/**
 * A modal component for handling user authentication (login and registration).
 * @param {{onClose: () => void}} props
 */
function AuthModal({ onClose }) {
  const [isLoginView, setIsLoginView] = useState(true);
  const [error, setError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const { login } = useAuth();

  // Reset state when switching between login and register views
  useEffect(() => {
    setError('');
    setSuccessMessage('');
  }, [isLoginView]);

  const handleLogin = async (event, email, password) => {
    setIsLoading(true);
    setError('');
    try {
      const data = await apiLogin(email, password);
      login(data.user);
      onClose(); // Close modal on successful login
    } catch (err) {
      setError(err.message || '登录失败，请检查您的凭据。');
    } finally {
      setIsLoading(false);
    }
  };

  const handleRegister = async (event, email, password) => {
    setIsLoading(true);
    setError('');
    setSuccessMessage('');
    try {
      await apiRegister(email, password);
      setSuccessMessage('注册申请已提交，请等待管理员批准。');
    } catch (err) {
      setError(err.message || '注册失败，该邮箱可能已被使用。');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()}>
        <button className="modal-close-button" onClick={onClose}>&times;</button>
        <h2>{isLoginView ? '登录' : '注册'}</h2>

        <AuthForm
          isLoginView={isLoginView}
          isLoading={isLoading}
          onSubmit={isLoginView ? handleLogin : handleRegister}
        />

        {error && <p className="error-message">{error}</p>}
        {successMessage && <p className="success-message">{successMessage}</p>}

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