import React, { useState, useEffect } from 'react';
import axios from 'axios';
import './LotteryPage.css'; // Assuming you'll create a CSS file for this page

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';

const LotteryPage = () => {
    const [lotteryResults, setLotteryResults] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchLotteryResults = async () => {
            try {
                setLoading(true);
                setError(null);
                // Fetch results for all lottery types
                const lotteryTypes = ['新澳门六合彩', '香港六合彩', '老澳门六合彩']; // Example types
                const fetchPromises = lotteryTypes.map(type =>
                    axios.get(`${API_BASE_URL}/index.php?endpoint=get_lottery_results&lottery_type=${encodeURIComponent(type)}&limit=10`)
                );
                const responses = await Promise.all(fetchPromises);
                
                const allResults = responses.flatMap(response => {
                    if (response.data.status === 'success') {
                        return response.data.lottery_results;
                    } else {
                        console.error('Error fetching lottery results for a type:', response.data.message);
                        return [];
                    }
                });
                
                // Sort results by drawing_date and issue_number to interleave them chronologically
                allResults.sort((a, b) => {
                    const dateA = new Date(a.drawing_date);
                    const dateB = new Date(b.drawing_date);
                    if (dateA.getTime() !== dateB.getTime()) {
                        return dateB.getTime() - dateA.getTime(); // Latest date first
                    }
                    return parseInt(b.issue_number) - parseInt(a.issue_number); // Latest issue first for same date
                });

                setLotteryResults(allResults);
            } catch (err) {
                console.error('Failed to fetch lottery results:', err);
                setError(err.message || 'Failed to fetch lottery results.');
            } finally {
                setLoading(false);
            }
        };

        fetchLotteryResults();
    }, []);

    if (loading) {
        return <div className="lottery-page-container">加载中...</div>;
    }

    if (error) {
        return <div className="lottery-page-container error">错误: {error}</div>;
    }

    return (
        <div className="lottery-page-container">
            <h1>开奖结果</h1>
            {lotteryResults.length === 0 ? (
                <p>没有可用的开奖结果。</p>
            ) : (
                <div className="lottery-results-grid">
                    {lotteryResults.map((result) => (
                        <div key={result.id} className="lottery-card">
                            <h2>{result.lottery_type}</h2>
                            <p><strong>期号:</strong> {result.issue_number}</p>
                            <p><strong>开奖日期:</strong> {new Date(result.drawing_date).toLocaleDateString()}</p>
                            <p><strong>中奖号码:</strong> {result.winning_numbers ? result.winning_numbers.join(', ') : 'N/A'}</p>
                            <p><strong>生肖:</strong> {result.zodiac_signs ? result.zodiac_signs.join(', ') : 'N/A'}</p>
                            <p><strong>颜色:</strong> {result.colors ? result.colors.join(', ') : 'N/A'}</p>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
};

export default LotteryPage;