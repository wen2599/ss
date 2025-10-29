import React from 'react';
import { Link } from 'react-router-dom';

const NotFoundPage = () => {
  return (
    <div>
      <h1>404 - 页面未找到</h1>
      <p>抱歉，您访问的页面不存在。</p>
      <Link to="/">返回主页</Link>
    </div>
  );
};

export default NotFoundPage;
