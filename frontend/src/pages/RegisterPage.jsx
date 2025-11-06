import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

const RegisterPage = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);

  const { register } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (password !== confirmPassword) {
      return setError('两次输入的密码不一致');
    }
    setError('');
    setSuccess('');
    setLoading(true);
    try {
      await register(email, password);
      setSuccess('注册成功! 您现在可以登录了。');
      setTimeout(() => navigate('/login'), 2000);
    } catch (err) {
      setError(err.message || '注册失败，该邮箱可能已被使用。');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="form-container">
      <h1>注册</h1>
      <form onSubmit={handleSubmit}>
        {error && <p className="error-message">{error}</p>}
        {success && <p style={{ color: '#7bed9f' }}>{success}</p>}
        <div className="form-group">
          <label htmlFor="email">邮箱</label>
          <input type="email" id="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
        </div>
        <div className="form-group">
          <label htmlFor="password">密码</label>
          <input type="password" id="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
        </div>
        <div className="form-group">
          <label htmlFor="confirm-password">确认密码</label>
          <input type="password" id="confirm-password" value={confirmPassword} onChange={(e) => setConfirmPassword(e.target.value)} required />
        </div>
        <button type="submit" className="btn" disabled={loading}>
          {loading ? '注册中...' : '注册'}
        </button>
      </form>
      <p>
        已有账户? <Link to="/login" className="form-link">前往登录</Link>
      </p>
    </div>
  );
};

export default RegisterPage;
