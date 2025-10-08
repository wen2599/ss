import { useState, useEffect } from 'react';
import { Routes, Route, useLocation } from 'react-router-dom';
import Navbar from './components/Navbar';
import ProtectedRoute from './components/ProtectedRoute'; // 引入新组件
import LotteryPage from './pages/LotteryPage';
import EmailCenter from './pages/EmailCenter';
import BillsPage from './pages/BillsPage';
import './theme.css';

function App() {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const location = useLocation();

  useEffect(() => {
    const checkSession = async () => {
      try {
        const response = await fetch('/check_session', { credentials: 'include' });
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
    setUser(null);
  };

  const getPageTitle = () => {
    switch (location.pathname) {
      case '/':
        return '开奖结果';
      case '/emails':
        return '邮件中心';
      case '/bills':
        return '账单中心';
      default:
        return '数据洞察中心';
    }
  };

  const renderContent = () => {
    if (loading) {
      return <div className="loading-container"><div className="loader"></div><p>正在加载应用...</p></div>;
    }

    return (
      <Routes>
        <Route path="/" element={<LotteryPage />} />

        {/* 使用 ProtectedRoute 简化受保护的路由 */}
        <Route
          path="/emails"
          element={<ProtectedRoute user={user}><EmailCenter /></ProtectedRoute>}
        />
        <Route
          path="/bills"
          element={<ProtectedRoute user={user}><BillsPage /></ProtectedRoute>}
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
      <main>{renderContent()}</main>
    </div>
  );
}

export default App;
