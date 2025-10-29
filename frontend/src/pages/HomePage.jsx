import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import authService from '../services/auth';

const HomePage = () => {
  const [currentUser, setCurrentUser] = useState(null);

  useEffect(() => {
    const getUser = async () => {
      const user = await authService.getCurrentUser();
      setCurrentUser(user);
    };
    getUser();
  }, []);

  const handleLogout = async () => {
    try {
      await authService.logout();
      window.location.href = '/login'; // 重定向到登录页
    } catch (error) {
      console.error('Logout failed:', error);
      // 即使注销失败，也尝试重定向，确保前端状态一致
      window.location.href = '/login';
    }
  };

  return (
    <div>
      <h1>欢迎，{currentUser ? currentUser.username : '访客'}！</h1>
      <p>这里是您的主页内容。</p>
      <nav>
        <ul>
          <li><Link to="/lottery">彩票</Link></li>
          <li><Link to="/lottery-result">彩票结果</Link></li>
          <li><Link to="/mail-organize">邮件整理</Link></li>
          <li><Link to="/mail-original">原始邮件</Link></li>
        </ul>
      </nav>
      <button onClick={handleLogout}>注销</button>
    </div>
  );
};

export default HomePage;
