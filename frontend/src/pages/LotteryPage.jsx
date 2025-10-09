import React, { useState, useEffect } from 'react';

const LotteryPage = () => {
    const [results, setResults] = useState({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Define the three lottery types to display
    const lotteryTypes = ["大乐透", "双色球", "福彩3D"];

    useEffect(() => {
        const fetchResults = async () => {
            try {
                const response = await fetch('/get_numbers');
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const data = await response.json();
                // Organize results by lottery_type for easy lookup
                const resultsByType = data.reduce((acc, result) => {
                    acc[result.lottery_type] = result;
                    return acc;
                }, {});
                setResults(resultsByType);
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
            <h1>彩票开奖结果</h1>
            <div className="results-grid">
                {lotteryTypes.map(type => {
                    const result = results[type];
                    return (
                        <div key={type} className="result-card">
                            <h2>{type}</h2>
                            {result ? (
                                <>
                                    <p className="numbers">{result.numbers}</p>
                                    <p className="drawn-at">开奖时间: {new Date(result.drawn_at).toLocaleString()}</p>
                                </>
                            ) : (
                                <p className="no-result">暂无开奖结果</p>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

export default LotteryPage;