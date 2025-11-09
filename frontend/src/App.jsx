// File: frontend/src/App.jsx
import React from 'react';
import { Routes, Route } from 'react-router-dom';
import Navbar from './components/Navbar';
import HomePage from './pages/HomePage';
import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';
import DashboardPage from './pages/DashboardPage';
import EmailsPage from './pages/EmailsPage';
import EmailDetailPage from './pages/EmailDetailPage'; // <-- 新增
import SettlementsPage from './pages/SettlementsPage';
import ResultsPage from './pages/ResultsPage';
import { AuthProvider, useAuth } from './contexts/AuthContext';

const RequireAuth = ({ children }) => {
  const { user } = useAuth();
  return user ? children : <LoginPage />;
};

function App() {
  return (
    <AuthProvider>
      <Navbar />
      <main className="container">
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
          <Route path="/dashboard" element={<RequireAuth><DashboardPage /></RequireAuth>} />
          <Route path="/emails" element={<RequireAuth><EmailsPage /></RequireAuth>} />
          <Route path="/emails/:emailId" element={<RequireAuth><EmailDetailPage /></RequireAuth>} /> {/* <-- 新增详情页路由 */}
          <Route path="/settlements" element={<RequireAuth><SettlementsPage /></RequireAuth>} />
          <Route path="/results" element={<RequireAuth><ResultsPage /></RequireAuth>} />
        </Routes>
      </main>
    </AuthProvider>
  );
}

export default App;
