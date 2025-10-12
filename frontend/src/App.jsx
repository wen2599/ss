import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import Navbar from './components/Navbar.jsx';
import HomePage from './pages/HomePage.jsx';
import LoginPage from './pages/LoginPage.jsx';
import RegisterPage from './pages/RegisterPage.jsx';
import BillsPage from './pages/BillsPage.jsx';
import BillDetailsPage from './pages/BillDetailsPage.jsx';
import './App.css';

function App() {
  return (
    <Router>
      <Navbar />
      <Routes>
        <Route path="/" element={<HomePage />} />
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route path="/bills" element={<BillsPage />} />
        <Route path="/bill/:id" element={<BillDetailsPage />} />
      </Routes>
    </Router>
  );
}

export default App;
