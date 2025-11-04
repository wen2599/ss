import React, { useState, useEffect, useCallback } from 'react';
import LotteryResults from './components/LotteryResults';
import Loading from './components/Loading';
import './App.css';

const API_KEY = 'YOUR_SECRET_API_KEY';

function App() {
    const [results, setResults] = useState([]);
    const [lotteryType, setLotteryType] = useState('all');
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);

    const fetchData = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        
        let url = '/api/results';
        const params = new URLSearchParams();
        if (lotteryType !== 'all') {
            params.append('type', lotteryType);
        }
        params.append('limit', 20);
        
        url = `${url}?${params.toString()}`;

        try {
            const response = await fetch(url, {
                headers: {
                    'X-API-KEY': API_KEY
                }
            });
            
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
    }, [lotteryType]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    return (
        <div className="App">
            <header className="App-header">
                <h1>Lottery Results</h1>
                <div className="lottery-type-selector">
                    <button onClick={() => setLotteryType('all')} className={lotteryType === 'all' ? 'active' : ''}>All</button>
                    <button onClick={() => setLotteryType('双色球')} className={lotteryType === '双色球' ? 'active' : ''}>双色球</button>
                    <button onClick={() => setLotteryType('大乐透')} className={lotteryType === '大乐透' ? 'active' : ''}>大乐透</button>
                </div>
            </header>
            <main>
                {isLoading ? (
                    <Loading />
                ) : error ? (
                    <div className="error-message">Error: {error}</div>
                ) : (
                    <LotteryResults results={results} />
                )}
            </main>
        </div>
    );
}

export default App;
