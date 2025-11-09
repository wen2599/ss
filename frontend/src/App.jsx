// File: frontend/src/App.jsx (Refactored Routing)

import React from 'react';
import { Routes, Route } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';

// --- Core Components ---
import Navbar from './components/Navbar';
import RequireAuth from './components/RequireAuth';

// --- Page-Level Components ---
import HomePage from './pages/HomePage';
import AuthPage from './pages/AuthPage';
import EmailsPage from './pages/EmailsPage';
import EmailDetailPage from './pages/EmailDetailPage'; // Will become plain text view
import SettlementsListPage from './pages/SettlementsListPage'; // Renamed from SettlementsPage
import SettlementDetailPage from './pages/SettlementDetailPage'; // New settlement workbench

function App() {
  return (
    <AuthProvider>
      <Navbar />
      <main className="container">
        <Routes>
          {/* --- Public Routes --- */}
          <Route path="/" element={<HomePage />} />
          <Route path="/auth" element={<AuthPage />} />

          {/* --- Protected Routes --- */}
          <Route path="/emails" element={<RequireAuth><EmailsPage /></RequireAuth>} />
          <Route path="/emails/:emailId" element={<RequireAuth><EmailDetailPage /></RequireAuth>} />

          {/* --- New Settlement Routes --- */}
          <Route path="/settlements" element={<RequireAuth><SettlementsListPage /></RequireAuth>} />
          <Route path="/settlements/:emailId" element={<RequireAuth><SettlementDetailPage /></RequireAuth>} />
          
          {/* --- 404 Fallback --- */}
          <Route path="*" element={<div className="card"><h1>404 - Page Not Found</h1></div>} />
        </Routes>
      </main>
    </AuthProvider>
  );
}

export default App;
