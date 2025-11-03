import { useState } from 'react';

function Register() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setMessage('');
    setError('');

    try {
      const response = await fetch('/api/register.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email, password }),
      });

      const data = await response.json();

      if (response.ok && data.success) {
        setMessage(data.message);
        setEmail(''); // 清空表单
        setPassword('');
      } else {
        throw new Error(data.message || `HTTP 错误: ${response.status}`);
      }

    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <h2>用户注册</h2>
      <form onSubmit={handleSubmit} className="form-container">
        <div>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="输入邮箱"
            required
          />
        </div>
        <div>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="输入密码 (至少6位)"
            required
            minLength="6"
          />
        </div>
        <button type="submit" disabled={loading}>
          {loading ? '注册中...' : '立即注册'}
        </button>
      </form>
      {message && <p style={{ color: '#28a745' }}>{message}</p>}
      {error && <p className="error">{error}</p>}
    </div>
  );
}

export default Register;
