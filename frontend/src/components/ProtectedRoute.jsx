// src/components/ProtectedRoute.jsx
import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const ProtectedRoute = ({ children }) => {
  const { token } = useAuth();

  if (!token) {
    // 如果没有token，重定向到登录页面
    return <Navigate to="/login" replace />;
  }

  return children;
};

export default ProtectedRoute;