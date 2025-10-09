import React from 'react';
import { Routes, Route, Link } from 'react-router-dom';
import LotteryPage from './pages/LotteryPage';
import './App.css';

function App() {
    return (
        <div className="App">
            <nav>
                <Link to="/">Home</Link>
            </nav>
            <main>
                <Routes>
                    <Route path="/" element={<LotteryPage />} />
                </Routes>
            </main>
        </div>
    );
}

export default App;