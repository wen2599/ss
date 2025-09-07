import React, { useState } from 'react';
import './Auth.css';
import { register, login } from '../api';

function Auth({ onClose, onLoginSuccess }) {
  const [isLoginView, setIsLoginView] = useState(true);
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [message, setMessage] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setMessage('');
    let response;
    if (isLoginView) {
      response = await login(phone, password);
    } else {
      response = await register(phone, password);
    }

    if (response.success) {
      if (isLoginView) {
        onLoginSuccess(response.user);
      } else {
        setMessage(`注册成功！您的ID是 ${response.displayId}。请登录。`);
        setIsLoginView(true);
      }
    } else {
      setMessage(response.message || '操作失败');
    }
  };

  return (
    <div className="auth-modal-overlay" onClick={onClose}>
      <div className="auth-modal-content" onClick={(e) => e.stopPropagation()}>
        <button className="close-button" onClick={onClose}>X</button>
        <h2>{isLoginView ? '登录' : '注册'}</h2>
        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label htmlFor="phone">手机号</label>
            <input
              type="text"
              id="phone"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              required
            />
          </div>
          <div className="form-group">
            <label htmlFor="password">密码</label>
            <input
              type="password"
              id="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </div>
          <button type="submit" className="submit-button">
            {isLoginView ? '登录' : '注册'}
          </button>
        </form>
        {message && <p className="message">{message}</p>}
        <p className="toggle-view">
          {isLoginView ? '还没有账户？' : '已有账户？'}
          <button onClick={() => setIsLoginView(!isLoginView)}>
            {isLoginView ? '点此注册' : '点此登录'}
          </button>
        </p>
      </div>
    </div>
  );
}

export default Auth;
