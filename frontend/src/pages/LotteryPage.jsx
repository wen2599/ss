import React, { useState, useEffect } from 'react';

const LotteryPage = () => {
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchResults = async () => {
            try {
                const response = await fetch('/get_numbers');
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const data = await response.json();
                setResults(data);
            } catch (error) {
                setError(error.message);
            } finally {
                setLoading(false);
            }
        };

        fetchResults();
    }, []);

    if (loading) {
        return <div>Loading...</div>;
    }

    if (error) {
        return <div>Error: {error}</div>;
    }

    return (
        <div className="lottery-page">
            <h1>Lottery Results</h1>
            <div className="results-grid">
                {results.length > 0 ? (
                    results.map(result => (
                        <div key={result.id} className="result-card">
                            <h2>{result.lottery_type}</h2>
                            <p className="numbers">{result.numbers}</p>
                            <p className="drawn-at">Drawn at: {new Date(result.drawn_at).toLocaleString()}</p>
                        </div>
                    ))
                ) : (
                    <p>No results found.</p>
                )}
            </div>
        </div>
    );
};

export default LotteryPage;