import React, { useState } from 'react';
import { useAuth } from '../context/AuthContext';

interface AuthFormProps {
  isRegister?: boolean;
  onSwitchMode: () => void;
}

const AuthForm: React.FC<AuthFormProps> = ({ isRegister = false, onSwitchMode }) => {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [phone, setPhone] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const { login, register } = useAuth();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setMessage('');
    try {
      if (isRegister) {
        await register({ username, password, phone });
        setMessage('Registration successful! Please switch to login.');
      } else {
        await login({ username, password });
      }
    } catch (err: any) {
      setError(err.message || 'An error occurred.');
    }
  };

  return (
    <div style={{ maxWidth: '400px', margin: '20px auto', padding: '20px', border: '1px solid #ccc', borderRadius: '8px' }}>
      <h2>{isRegister ? 'Create Account' : 'Login'}</h2>
      <form onSubmit={handleSubmit}>
        <div style={{ marginBottom: '15px' }}>
          <label htmlFor="username" style={{ display: 'block', marginBottom: '5px' }}>Username</label>
          <input
            id="username"
            type="text"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            required
            style={{ width: '100%', padding: '8px', boxSizing: 'border-box' }}
          />
        </div>
        {isRegister && (
          <div style={{ marginBottom: '15px' }}>
            <label htmlFor="phone" style={{ display: 'block', marginBottom: '5px' }}>Phone Number</label>
            <input
              id="phone"
              type="tel"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              required
              style={{ width: '100%', padding: '8px', boxSizing: 'border-box' }}
            />
          </div>
        )}
        <div style={{ marginBottom: '15px' }}>
          <label htmlFor="password" style={{ display: 'block', marginBottom: '5px' }}>Password</label>
          <input
            id="password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            style={{ width: '100%', padding: '8px', boxSizing: 'border-box' }}
          />
        </div>
        {error && <p style={{ color: 'red', textAlign: 'center' }}>{error}</p>}
        {message && <p style={{ color: 'green', textAlign: 'center' }}>{message}</p>}
        <button type="submit" style={{ width: '100%', padding: '10px', cursor: 'pointer' }}>
          {isRegister ? 'Register' : 'Login'}
        </button>
      </form>
      <p style={{ textAlign: 'center', marginTop: '15px' }}>
        {isRegister ? 'Already have an account?' : "Don't have an account?"}
        <button onClick={onSwitchMode} style={{ background: 'none', border: 'none', color: 'blue', cursor: 'pointer', textDecoration: 'underline' }}>
          {isRegister ? 'Login' : 'Register'}
        </button>
      </p>
    </div>
  );
};

export default AuthForm;
