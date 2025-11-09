// src/components/RequireAuth.jsx
import React from 'react';
import { useLocation, Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

function RequireAuth({ children }) {
  const { user } = useAuth();
  const location = useLocation();

  if (!user) {
    // 如果未登录，重定向到登录页，并记录从哪个页面来的
    return <Navigate to="/auth" state={{ from: location }} replace />;
  }

  return children;
}

export default RequireAuth;