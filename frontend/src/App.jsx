import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import Navbar from './components/Navbar.jsx';
import HomePage from './pages/HomePage.jsx';
import BillsPage from './pages/BillsPage.jsx';
import BillDetailsPage from './pages/BillDetailsPage.jsx';
import LoginPage from './pages/LoginPage.jsx';
import RegisterPage from './pages/RegisterPage.jsx';
import LotteryPage from './pages/LotteryPage.jsx';
import EmailsPage from './pages/EmailsPage.jsx'; // Import EmailsPage
import { AuthProvider } from './context/AuthContext.jsx';
import './App.css';

function App() {
  return (
    <AuthProvider>
      <Router>
        <Navbar />
        <div className="main-content">
          <Routes>
            <Route path="/" element={<HomePage />} />
            <Route path="/login" element={<LoginPage />} />
            <Route path="/register" element={<RegisterPage />} />
            <Route path="/bills" element={<BillsPage />} />
            <Route path="/bill/:id" element={<BillDetailsPage />} />
            <Route path="/lottery" element={<LotteryPage />} />
            <Route path="/emails" element={<EmailsPage />} /> {/* Add EmailsPage Route */}
          </Routes>
        </div>
      </Router>
    </AuthProvider>
  );
}

export default App;
