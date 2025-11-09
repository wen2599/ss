// File: frontend/src/App.jsx (Corrected)

import React from 'react';
import { Routes, Route } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext'; // Corrected path

// --- 1. 核心组件导入 ---
import Navbar from './components/Navbar';
import RequireAuth from './components/RequireAuth';

// --- 2. 页面级组件导入 ---
import HomePage from './pages/HomePage';
import AuthPage from './pages/AuthPage';
import EmailsPage from './pages/EmailsPage'; // Corrected path
import EmailDetailPage from './pages/EmailDetailPage';
import SettlementsPage from './pages/SettlementsPage';

function App() {
  return (
    <AuthProvider>
      <Navbar />
      <main className="container">
        <Routes>
          {/* --- 公共路由 --- */}
          <Route path="/" element={<HomePage />} />
          <Route path="/auth" element={<AuthPage />} />

          {/* --- 受保护的路由 (需要登录) --- */}
          <Route 
            path="/emails" 
            element={<RequireAuth><EmailsPage /></RequireAuth>} 
          />
          <Route 
            path="/emails/:emailId" 
            element={<RequireAuth><EmailDetailPage /></RequireAuth>} 
          />
          <Route 
            path="/settlements" 
            element={<RequireAuth><SettlementsPage /></RequireAuth>} 
          />
          
          {/* --- 404 回退路由 --- */}
          <Route path="*" element={
            <div className="card"><h1>404 - 页面未找到</h1></div>
          } />
        </Routes>
      </main>
    </AuthProvider>
  );
}

export default App;
