import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';

// 布局
import MainLayout from './components/layout/MainLayout';

// 页面
import Home from './pages/Home';
import Login from './pages/Login';
import Register from './pages/Register';
import Dashboard from './pages/Dashboard';
import ResultsPage from './pages/ResultsPage';
import BetsPage from './pages/BetsPage';
import HowToPlayPage from './pages/HowToPlayPage';

function App() {
    return (
        <AuthProvider>
            <Router>
                <Routes>
                    <Route path="/login" element={<Login />} />
                    <Route path="/register" element={<Register />} />
                    <Route path="/" element={<PrivateRoute><MainLayout /></PrivateRoute>}>
                        <Route index element={<Navigate to="/dashboard" />} />
                        <Route path="dashboard" element={<Dashboard />} />
                        <Route path="results" element={<ResultsPage />} />
                        <Route path="my-bets" element={<BetsPage />} />
                        <Route path="how-to-play" element={<HowToPlayPage />} />
                    </Route>
                    {/* Fallback for non-authenticated users */}
                    <Route path="*" element={<Navigate to="/login" />} />
                </Routes>
            </Router>
        </AuthProvider>
    );
}

function PrivateRoute({ children }) {
    const { user } = useAuth();
    return user ? children : <Navigate to="/login" />;
}

export default App;
