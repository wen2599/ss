import React from 'react';
import { Routes, Route } from 'react-router-dom';
import Navbar from './components/Navbar';
import AuthForm from './components/AuthForm'; // Consolidated auth form
import EmailParser from './components/EmailParser';
import ProtectedRoute from './components/ProtectedRoute';
import Home from './components/Home';
import './App.css'; // Import the new centralized stylesheet

function App() {
  return (
    <div className="App">
      <Navbar />
      <main>
        <Routes>
          {/* Public Routes */}
          <Route path="/" element={<Home />} />
          <Route path="/login" element={<AuthForm formType="login" />} />
          <Route path="/register" element={<AuthForm formType="register" />} />

          {/* Protected Routes */}
          <Route
            path="/parser"
            element={
              <ProtectedRoute>
                <EmailParser />
              </ProtectedRoute>
            }
          />

          {/* Fallback Route */}
          <Route path="*" element={<h2>404 Not Found</h2>} />
        </Routes>
      </main>
    </div>
  );
}

export default App;