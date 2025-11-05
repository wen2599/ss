import React from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

function Home() {
  const { user } = useAuth();
  
  return (
    <div>
      <h1>欢迎来到邮件下注系统</h1>
      <p>一个通过邮件即可轻松下注的平台。</p>
      {user ? (
        <p>您已登录。 <Link to="/dashboard">前往仪表盘</Link></p>
      ) : (
        <p><Link to="/login">登录</Link> 或 <Link to="/register">注册</Link> 开始使用。</p>
      )}
    </div>
  );
}

export default Home;