import React, { useState, useEffect } from 'react';
import { Routes, Route, Link, useNavigate } from 'react-router-dom';
import axios from 'axios';
import LotteryResults from './components/LotteryResults';
import EmailViewer from './components/EmailViewer';
import RegisterForm from './components/RegisterForm';
import LoginForm from './components/LoginForm';
import './App.css';

const API_CHECK_AUTH_URL = '/api_router.php?endpoint=auth&action=check_auth';

function App() {
  const [authToken, setAuthToken] = useState(localStorage.getItem('authToken'));
  const [userId, setUserId] = useState(localStorage.getItem('userId'));
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  useEffect(() => {
    const checkAuth = async () => {
      const token = localStorage.getItem('authToken');
      if (token) {
        try {
          const response = await axios.get(API_CHECK_AUTH_URL, {
            headers: { 'Authorization': `Bearer ${token}` }
          });
          if (response.data.success && response.data.loggedIn) {
            setAuthToken(token);
            setUserId(response.data.user.id);
          } else {
            handleLogout(); // Invalid token
          }
        } catch (error) {
          handleLogout(); // Error validating token
        } finally {
          setLoading(false);
        }
      } else {
        setLoading(false);
      }
    };

    checkAuth();
  }, []);

  const handleLogin = (token, id) => {
    setAuthToken(token);
    setUserId(id);
  };

  const handleLogout = () => {
    localStorage.removeItem('authToken');
    localStorage.removeItem('userId');
    setAuthToken(null);
    setUserId(null);
    navigate('/login');
  };

  if (loading) {
      return <div>Loading...</div>; // Or a spinner component
  }

  return (
    <div className="app">
      <nav className="main-nav">
        <Link to="/" className="nav-item">首页</Link>
        {!authToken ? (
          <>
            <Link to="/register" className="nav-item">注册</Link>
            <Link to="/login" className="nav-item">登录</Link>
          </>
        ) : (
          <button onClick={handleLogout} className="nav-item logout-button">退出 ({userId})</button>
        )}
      </nav>

      <main>
        <Routes>
          <Route path="/" element={authToken ? <HomeContent /> : <WelcomeMessage />} />
          <Route path="/register" element={<RegisterForm />} />
          <Route path="/login" element={<LoginForm onLogin={handleLogin} />} />
          <Route path="*" element={<NotFound />} />
        </Routes>
      </main>
    </div>
  );
}

function HomeContent() {
  const [activeView, setActiveView] = useState('lottery');
  return (
    <div className="home-content">
      <nav className="sub-nav">
        <button
          onClick={() => setActiveView('lottery')}
          className={activeView === 'lottery' ? 'active' : ''}
        >
          开奖结果
        </button>
        <button
          onClick={() => setActiveView('email')}
          className={activeView === 'email' ? 'active' : ''}
        >
          邮件查看
        </button>
      </nav>
      <div className="view-content">
        {activeView === 'lottery' && <LotteryResults />}
        {activeView === 'email' && <EmailViewer />}
      </div>
    </div>
  );
}

function WelcomeMessage() {
  return (
    <div className="welcome-message">
      <h1>欢迎来到彩票邮件管理系统</h1>
      <p>请<Link to="/login">登录</Link>或<Link to="/register">注册</Link>以查看内容。</p>
    </div>
  );
}

function NotFound() {
  return (
    <div className="not-found">
      <h2>404 - 页面未找到</h2>
      <p>您要找的页面不存在。</p>
      <Link to="/">返回首页</Link>
    </div>
  );
}

export default App;
