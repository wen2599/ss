import { useState } from 'react';
import './Form.css';

function LoginForm() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');

  const handleSubmit = (event) => {
    event.preventDefault();
    console.log('Login attempt with:', { email, password });
    // TODO: Implement actual login logic
  };

  return (
    <div className="form-container">
      <form onSubmit={handleSubmit}>
        <h2>登录</h2>
        <div className="form-group">
          <label htmlFor="login-email">邮箱</label>
          <input
            id="login-email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </div>
        <div className="form-group">
          <label htmlFor="login-password">密码</label>
          <input
            id="login-password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </div>
        <button type="submit">登录</button>
      </form>
    </div>
  );
}

export default LoginForm;
