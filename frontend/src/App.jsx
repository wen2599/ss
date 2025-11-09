// src/App.jsx
import React from 'react';
import { Routes, Route } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';

import Navbar from './components/Navbar';
import RequireAuth from './components/RequireAuth';

import HomePage from './pages/HomePage';
import AuthPage from './pages/AuthPage';
import EmailsPage from './pages/EmailsPage';
import SettlementsPage from './pages/SettlementsPage';

function App() {
  return (
    <AuthProvider>
      <Navbar />
      <main className="container">
        <Routes>
          {/* Public Routes */}
          <Route path="/" element={<HomePage />} />
          <Route path="/auth" element={<AuthPage />} />

          {/* Protected Routes */}
          <Route path="/emails" element={
            <RequireAuth>
              <EmailsPage />
            </RequireAuth>
          } />
          <Route path="/settlements" element={
            <RequireAuth>
              <SettlementsPage />
            </RequireAuth>
          } />
          
          {/* Fallback Route */}
          <Route path="*" element={<h1>404 - Page Not Found</h1>} />
        </Routes>
      </main>
    </AuthProvider>
  );
}

export default App;