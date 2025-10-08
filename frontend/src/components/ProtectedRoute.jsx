
import React from 'react';

const ProtectedRoute = ({ user, children }) => {
  if (!user) {
    return (
      <div className="card centered-card">
        <h2>请先登录</h2>
        <p className="secondary-text">您需要登录后才能访问此页面。</p>
      </div>
    );
  }

  return children;
};

export default ProtectedRoute;
