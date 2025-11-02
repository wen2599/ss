// src/App.jsx
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
import SettingsPage from './pages/SettingsPage'; // 引入新页面

function App() {
  return (
    <AuthProvider>
      <Routes>
        {/* 公共路由 */}
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />

        {/* 受保护的路由 */}
        <Route 
          path="/" 
          element={<ProtectedRoute><DashboardLayout /></ProtectedRoute>}
        >
          <Route index element={<LotteryPage />} />
          <Route path="emails" element={<EmailsPage />} />
          <Route path="settlements" element={<SettlementsPage />} />
          {/* 新增的路由 */}
          <Route path="settings" element={<SettingsPage />} /> 
        </Route>

        <Route path="*" element={<h1>404 - Not Found</h1>} />
      </Routes>
    </AuthProvider>
  );
}

export default App;