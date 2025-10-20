import React from 'react';
import './HomePage.css';

const HomePage = () => {
  return (
    <div className="homepage">
      <h1>欢迎使用账单与彩票跟踪器！</h1>
      <p>您管理账单和跟踪开奖结果的个人助理。</p>
      <div className="features">
        <h2>功能：</h2>
        <ul>
          <li>📧 使用 AI 自动处理您电子邮件中的账单。</li>
          <li>📊 在一个地方查看和管理您的所有账单。</li>
          <li>🎰 跟踪最新的开奖结果。</li>
          <li>🔒 安全的用户身份验证。</li>
        </ul>
      </div>
      <p>通过注册账户或登录开始使用。</p>
    </div>
  );
};

export default HomePage;
