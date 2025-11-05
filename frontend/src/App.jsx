import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';

// Layouts
import MainLayout from './components/layout/MainLayout';

// Pages - 确保这里的路径和文件名完全对应
import Home from './pages/Home';
import Login from './pages/Login';
import Register from './pages/Register';
import Dashboard from './pages/Dashboard';
import ResultsPage from './pages/ResultsPage'; // 修正了这里的引用
import BetsPage from './pages/BetsPage';
import HowToPlayPage from './pages/HowToPlayPage';
import ProfilePage from './pages/ProfilePage';

function PrivateRoutes() {
  const { user, loading } = useAuth();
  if (loading) {
    // 在验证用户身份时显示一个加载提示，防止页面闪烁
    return <div style={{ textAlign: 'center', paddingTop: '5rem' }}>正在验证身份...</div>;
  }
  return user ? <MainLayout /> : <Navigate to="/login" />;
}

function App() {
  return (
    <AuthProvider>
      <Router>
        <Routes>
          {/* Public routes */}
          <Route path="/" element={<Home />} />
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
          
          {/* Private routes wrapped in MainLayout */}
          <Route element={<PrivateRoutes />}>
            <Route path="/dashboard" element={<Dashboard />} />
            <Route path="/results" element={<ResultsPage />} />
            <Route path="/my-bets" element={<BetsPage />} />
            <Route path="/how-to-play" element={<HowToPlayPage />} />
            <Route path="/profile" element={<ProfilePage />} />
          </Route>
          
          <Route path="*" element={<Navigate to="/" />} />
        </Routes>
      </Router>
    </AuthProvider>
  );
}

export default App;