import { useState, useEffect } from 'react';
import { Routes, Route, Link, useLocation } from 'react-router-dom';
import Auth from './components/Auth';
import LotteryPage from './pages/LotteryPage';
import EmailCenter from './pages/EmailCenter';
import './theme.css';
import './components/Navbar.css'; // 我们将为导航栏创建新的样式

function App() {
  const [user, setUser] = useState(null);
  const location = useLocation(); // 获取当前路径以高亮显示活动链接

  // 用户会话检查逻辑保持不变
  useEffect(() => {
    const checkSession = async () => {
      try {
        const response = await fetch('/check_session', { credentials: 'include' });
        const data = await response.json();
        if (data.loggedin) setUser(data.user);
      } catch (err) {
        console.error("会话检查失败:", err);
      }
    };
    checkSession();
  }, []);

  const handleLogin = (userData) => setUser(userData);
  const handleLogout = () => setUser(null);

  return (
    <div className="App">
      <Auth user={user} onLogin={handleLogin} onLogout={handleLogout} />

      <header className="App-header">
        <h1>数据洞察中心</h1>
        {/* 只有在用户登录后才显示导航 */}
        {user && (
          <nav className="main-nav">
            <Link to="/" className={location.pathname === '/' ? 'active' : ''}>
              开奖结果
            </Link>
            <Link to="/emails" className={location.pathname === '/emails' ? 'active' : ''}>
              邮件中心
            </Link>
          </nav>
        )}
      </header>

      <main>
        {/* 如果用户未登录，显示提示信息 */}
        {!user ? (
          <p>请先登录以访问数据中心。</p>
        ) : (
          <Routes>
            <Route path="/" element={<LotteryPage />} />
            <Route path="/emails" element={<EmailCenter />} />
          </Routes>
        )}
      </main>
    </div>
  );
}

export default App;
