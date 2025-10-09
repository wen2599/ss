import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import Navbar from './components/Navbar.jsx';
import LotteryPage from './pages/LotteryPage.jsx';
import LoginPage from './pages/LoginPage.jsx';
import RegisterPage from './pages/RegisterPage.jsx';
import BillsPage from './pages/BillsPage.jsx';
import BillDetailsPage from './pages/BillDetailsPage.jsx';
import HistoryPage from './pages/HistoryPage.jsx'; // Import the new HistoryPage component
import './App.css';

function App() {
  return (
    <Router>
      <Navbar />
      <Routes>
        <Route path="/" element={<LotteryPage />} />
        <Route path="/history" element={<HistoryPage />} /> {/* Add the new history route */}
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route path="/bills" element={<BillsPage />} />
        <Route path="/bill/:id" element={<BillDetailsPage />} />
      </Routes>
    </Router>
  );
}

export default App;