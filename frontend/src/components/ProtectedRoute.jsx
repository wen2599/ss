import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const ProtectedRoute = ({ children }) => {
  const { isAuthenticated, loading } = useAuth();

  if (loading) {
    return <div className="loading">正在加载认证信息...</div>;
  }

  if (!isAuthenticated) {
    // 如果用户未认证，重定向到登录页面
    return <Navigate to="/login" replace />;
  }

  return children;
};

export default ProtectedRoute;