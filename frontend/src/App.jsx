import { useState, useEffect } from 'react';
import { Routes, Route, NavLink } from 'react-router-dom';
import Auth from './components/Auth';
import LotteryPage from './pages/LotteryPage';
import EmailCenter from './pages/EmailCenter';
import './theme.css';
import './components/Navbar.css';

function App() {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const checkSession = async () => {
      try {
        const response = await fetch('/check_session', { credentials: 'include' });
        const data = await response.json();
        if (data.loggedin) {
          setUser(data.user);
        }
      } catch (err) {
        console.error("会话检查失败:", err);
      } finally {
        setLoading(false);
      }
    };
    checkSession();
  }, []);

  const handleLogin = (userData) => setUser(userData);
  const handleLogout = () => setUser(null);

  const renderContent = () => {
    if (loading) {
      return <p>正在加载...</p>;
    }
    if (!user) {
      return (
        <div className="card" style={{ textAlign: 'center', maxWidth: '400px' }}>
            <h2>欢迎来到数据洞察中心</h2>
            <p style={{ color: 'var(--color-text-secondary)' }}>请先登录以访问核心功能。</p>
        </div>
      );
    }
    return (
        <Routes>
            <Route path="/" element={<LotteryPage />} />
            <Route path="/emails" element={<EmailCenter />} />
        </Routes>
    );
  };

  return (
    <div className="App">
      <Auth user={user} onLogin={handleLogin} onLogout={handleLogout} />

      <header className="App-header">
        <h1>数据洞察中心</h1>
        {user && (
          <nav className="main-nav">
            <NavLink to="/">开奖结果</NavLink>
            <NavLink to="/emails">邮件中心</NavLink>
          </nav>
        )}
      </header>

      <main>
        {renderContent()}
      </main>
    </div>
  );
}

export default App;
