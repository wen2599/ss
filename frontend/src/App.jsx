import React, { useState, useEffect, useCallback } from 'react';
import LotteryResults from './components/LotteryResults';
import Loading from './components/Loading';
import Login from './components/Login';
import Register from './components/Register';
import EmailViewer from './components/EmailViewer';
import { useAuth } from './context/AuthContext';
import './App.css';

const API_BASE_URL = import.meta.env.DEV ? '/api' : '/api';

// This component renders the Lottery part of the application
const LotteryDashboard = () => {
    const [results, setResults] = useState([]);
    const [lotteryType, setLotteryType] = useState('all');
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);
    const { token } = useAuth();

    const fetchData = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        
        let url = `${API_BASE_URL}/results`;
        const params = new URLSearchParams({ limit: 20 });
        if (lotteryType !== 'all') {
            params.append('type', lotteryType);
        }
        url = `${url}?${params.toString()}`;

        try {
            const response = await fetch(url);
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ error: 'Unknown error' }));
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            if (data.success) {
                setResults(data.data);
            } else {
                throw new Error(data.error || 'Failed to fetch results');
            }
        } catch (e) {
            console.error("Fetch error:", e);
            setError(e.message);
        } finally {
            setIsLoading(false);
        }
    }, [lotteryType, token]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    return (
        <>
            <div className="lottery-type-selector">
                <button onClick={() => setLotteryType('all')} className={lotteryType === 'all' ? 'active' : ''}>All</button>
                <button onClick={() => setLotteryType('双色球')} className={lotteryType === '双色球' ? 'active' : ''}>双色球</button>
                <button onClick={() => setLotteryType('大乐透')} className={lotteryType === '大乐透' ? 'active' : ''}>大乐透</button>
            </div>
            {isLoading ? <Loading /> : error ? <div className="error-message">{error}</div> : <LotteryResults results={results} />}
        </>
    );
};


// This component will contain the main application content for authenticated users
const AuthenticatedApp = () => {
    const { logout } = useAuth();
    const [currentView, setCurrentView] = useState('lottery'); // 'lottery' or 'emails'

    return (
        <div className="App">
            <header className="App-header">
                <nav className="main-nav">
                    <button onClick={() => setCurrentView('lottery')} className={currentView === 'lottery' ? 'active' : ''}>Lottery</button>
                    <button onClick={() => setCurrentView('emails')} className={currentView === 'emails' ? 'active' : ''}>Emails</button>
                </nav>
                <button onClick={logout} className="logout-button">Logout</button>
            </header>
            <main>
                {currentView === 'lottery' && <LotteryDashboard />}
                {currentView === 'emails' && <EmailViewer />}
            </main>
        </div>
    );
};


// This component will handle the authentication flow
const UnauthenticatedApp = () => {
    const [isRegistering, setIsRegistering] = useState(false);

    return (
        <div className="auth-container">
            {isRegistering ? (
                <>
                    <Register onRegisterSuccess={() => setIsRegistering(false)} />
                    <p>Already have an account? <button className="link-button" onClick={() => setIsRegistering(false)}>Login</button></p>
                </>
            ) : (
                <>
                    <Login />
                    <p>Don't have an account? <button className="link-button" onClick={() => setIsRegistering(true)}>Register</button></p>
                </>
            )}
        </div>
    );
};

function App() {
    const { isAuthenticated } = useAuth();
    return isAuthenticated() ? <AuthenticatedApp /> : <UnauthenticatedApp />;
}

export default App;
