import React from 'react';
import { Link } from 'react-router-dom';

const NotFoundPage = () => {
  return (
    <div>
      <h1>404 - 页面未找到</h1>
      <p>您要查找的页面不存在。</p>
      <Link to="/">返回首页</Link>
    </div>
  );
};

export default NotFoundPage;