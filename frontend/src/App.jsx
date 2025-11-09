// File: frontend/src/App.jsx (Simplified Routes)

import React from 'react';
import { Routes, Route } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';

import Navbar from './components/Navbar';
import RequireAuth from './components/RequireAuth';

import HomePage from './pages/HomePage';
import AuthPage from './pages/AuthPage';
import EmailsListPage from './pages/EmailsListPage'; // Renamed for clarity
import EmailDetailPage from './pages/EmailDetailPage'; // This is now the core workbench

function App() {
  return (
    <AuthProvider>
      <Navbar />
      <main className="container">
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/auth" element={<AuthPage />} />

          <Route 
            path="/emails" 
            element={<RequireAuth><EmailsListPage /></RequireAuth>} 
          />
          <Route 
            path="/emails/:emailId" 
            element={<RequireAuth><EmailDetailPage /></RequireAuth>} 
          />
          
          <Route path="*" element={
            <div className="card"><h1>404 - 页面未找到</h1></div>
          } />
        </Routes>
      </main>
    </AuthProvider>
  );
}

export default App;
