import React from 'react';
import { Link } from 'react-router-dom';

function Home() {
  return (
    <div>
      <h1>欢迎来到 LottoSys</h1>
      <p>请 <Link to="/login">登录</Link> 或 <Link to="/register">注册</Link> 开始。</p>
    </div>
  );
}

export default Home;