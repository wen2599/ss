import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import { ThemeProvider, CssBaseline } from '@mui/material';
import { CustomThemeProvider } from './ThemeContext.jsx';
import Navbar from './components/Navbar.jsx';
import LotteryPage from './pages/LotteryPage.jsx';
import LoginPage from './pages/LoginPage.jsx';
import RegisterPage from './pages/RegisterPage.jsx';
import BillsPage from './pages/BillsPage.jsx';
import BillDetailsPage from './pages/BillDetailsPage.jsx';

function App() {
  return (
    <CustomThemeProvider>
      {(theme) => (
        <ThemeProvider theme={theme}>
          <CssBaseline />
          <Router>
            <Navbar />
            <main>
              <Routes>
                <Route path="/" element={<LotteryPage />} />
                <Route path="/login" element={<LoginPage />} />
                <Route path="/register" element={<RegisterPage />} />
                <Route path="/bills" element={<BillsPage />} />
                <Route path="/bill/:id" element={<BillDetailsPage />} />
              </Routes>
            </main>
          </Router>
        </ThemeProvider>
      )}
    </CustomThemeProvider>
  );
}

export default App;