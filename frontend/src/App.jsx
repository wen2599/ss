import React from 'react';
import { Routes, Route } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';

import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';
import DashboardLayout from './pages/DashboardLayout';
import LotteryPage from './pages/LotteryPage';
import EmailsPage from './pages/EmailsPage';
import SettlementsPage from './pages/SettlementsPage';

function App() {
  return (
    <AuthProvider>
      <Routes>
        {/* 公共路由 */}
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />

        {/* 受保护的路由 (主应用界面) */}
        <Route 
          path="/" 
          element={
            <ProtectedRoute>
              <DashboardLayout />
            </ProtectedRoute>
          }
        >
          {/* 嵌套在DashboardLayout中的子路由 */}
          <Route index element={<LotteryPage />} /> {/* 默认页面 */}
          <Route path="emails" element={<EmailsPage />} />
          <Route path="settlements" element={<SettlementsPage />} />
        </Route>

        {/* 可以添加一个404页面 */}
        <Route path="*" element={<h1>404 - Not Found</h1>} />
      </Routes>
    </AuthProvider>
  );
}

export default App;