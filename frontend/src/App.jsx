import React, { useState, useEffect, useCallback } from 'react';
import LotteryResults from './components/LotteryResults';
import Loading from './components/Loading';
import './App.css';
import { getResults } from './services/api';

// This component renders the Lottery part of the application
const LotteryDashboard = () => {
    const [results, setResults] = useState([]);
    const [lotteryType, setLotteryType] = useState('all');
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);

    const fetchData = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        
        try {
            const data = await getResults(lotteryType !== 'all' ? lotteryType : null, 20);
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
    }, [lotteryType]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    return (
        <div className="App">
            <header className="App-header">
                <h1>Lottery Results</h1>
            </header>
            <main>
                <div className="lottery-type-selector">
                    <button onClick={() => setLotteryType('all')} className={lotteryType === 'all' ? 'active' : ''}>All</button>
                    <button onClick={() => setLotteryType('双色球')} className={lotteryType === '双色球' ? 'active' : ''}>双色球</button>
                    <button onClick={() => setLotteryType('大乐透')} className={lotteryType === '大乐透' ? 'active' : ''}>大乐透</button>
                </div>
                {isLoading ? <Loading /> : error ? <div className="error-message">{error}</div> : <LotteryResults results={results} />}
            </main>
        </div>
    );
};

function App() {
    return <LotteryDashboard />;
}

export default App;
