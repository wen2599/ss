import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import Navbar from './components/Navbar.jsx';
import HomePage from './pages/HomePage.jsx';
import BillsPage from './pages/BillsPage.jsx';
import BillDetailsPage from './pages/BillDetailsPage.jsx';
import './App.css';

function App() {
  return (
    <Router>
      <Navbar />
      <div className="main-content">
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/bills" element={<BillsPage />} />
          <Route path="/bill/:id" element={<BillDetailsPage />} />
        </Routes>
      </div>
    </Router>
  );
}

export default App;
