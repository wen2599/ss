import { useState, useEffect } from 'react';
import { Routes, Route, NavLink, useLocation } from 'react-router-dom';
import Navbar from './components/Navbar'; // 引入新的 Navbar 组件
import LotteryPage from './pages/LotteryPage';
import EmailCenter from './pages/EmailCenter';
import './theme.css';

function App() {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const location = useLocation();

  useEffect(() => {
    const checkSession = async () => {
      try {
        const response = await fetch('/api/check_session', { credentials: 'include' });
        if (!response.ok) throw new Error('Network response was not ok');
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
  const handleLogout = () => {
    // 在实际应用中，您可能想在这里清除更多状态或重定向
    setUser(null);
  };

  const getPageTitle = () => {
    switch (location.pathname) {
      case '/':
        return '开奖结果';
      case '/emails':
        return '邮件中心';
      default:
        return '数据洞察中心';
    }
  };

  const renderContent = () => {
    if (loading) {
        return <div className="loading-container"><div className="loader"></div><p>正在加载应用...</p></div>;
    }

    // The new logic always renders the routes, but protects the private ones.
    return (
        <Routes>
            {/* The LotteryPage is now always public */}
            <Route path="/" element={<LotteryPage />} />

            {/* The EmailCenter is protected. Show it only if logged in. */}
            <Route
                path="/emails"
                element={
                    user ? (
                        <EmailCenter />
                    ) : (
                        <div className="card centered-card">
                            <h2>请先登录</h2>
                            <p className="secondary-text">您需要登录后才能访问此页面。</p>
                        </div>
                    )
                }
            />
        </Routes>
    );
  };

  return (
    <div className="App">
      <Navbar user={user} onLogin={handleLogin} onLogout={handleLogout} />

      <header className="App-header">
        {user && <h1>{getPageTitle()}</h1>}
      </header>

      <main>
        {renderContent()}
      </main>
    </div>
  );
}

export default App;
