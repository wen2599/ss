import React, { useState, useEffect } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import authService from '../services/auth';

// 导入页面组件
import HomePage from '../pages/HomePage';
import LoginPage from '../pages/LoginPage';
import RegisterPage from '../pages/RegisterPage';
import LotteryPage from '../pages/LotteryPage';
import LotteryResultPage from '../pages/LotteryResultPage';
import MailOrganizePage from '../pages/MailOrganizePage';
import MailOriginalPage from '../pages/MailOriginalPage';
import NotFoundPage from '../pages/NotFoundPage';

// 路由守卫组件
const PrivateRoute = ({ children }) => {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const checkAuth = async () => {
      try {
        const loggedIn = await authService.isLoggedIn();
        setIsAuthenticated(loggedIn);
      } catch (error) {
        console.error("Authentication check failed:", error);
        setIsAuthenticated(false); // On error, assume not authenticated
      } finally {
        setIsLoading(false);
      }
    };
    checkAuth();
  }, []);

  if (isLoading) {
    return <div>加载中...</div>; // 或者一个更友好的加载指示器
  }

  return isAuthenticated ? children : <Navigate to="/login" />;
};

const AppRouter = () => {
  return (
    <Router>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        
        <Route 
          path="/" 
          element=
            {<PrivateRoute>
              <HomePage />
            </PrivateRoute>}
        />
        <Route 
          path="/lottery" 
          element=
            {<PrivateRoute>
              <LotteryPage />
            </PrivateRoute>}
        />
        <Route 
          path="/lottery-result" 
          element=
            {<PrivateRoute>
              <LotteryResultPage />
            </PrivateRoute>}
        />
        <Route 
          path="/mail-organize" 
          element=
            {<PrivateRoute>
              <MailOrganizePage />
            </PrivateRoute>}
        />
        <Route 
          path="/mail-original" 
          element=
            {<PrivateRoute>
              <MailOriginalPage />
            </PrivateRoute>}
        />

        {/* 404 Not Found Page */}
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
    </Router>
  );
};

export default AppRouter;
