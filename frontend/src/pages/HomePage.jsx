import React from 'react';
import { Link } from 'react-router-dom';
import './HomePage.css';

function HomePage() {
  return (
    <div className="home-page">
      <h1>欢迎使用电子账单系统</h1>
      <p>一个现代、高效的方式来管理您的所有电子账单。</p>
      <Link to="/bills" className="btn">查看我的账单</Link>
    </div>
  );
}

export default HomePage;