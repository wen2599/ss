// frontend/src/components/LotteryDisplay.jsx
import React, { useState, useEffect } from 'react';
import { getLatestDraws } from '../services/api';
import './LotteryDisplay.css';

const LotteryDisplay = () => {
    const [draws, setDraws] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchDraws = async () => {
            try {
                setLoading(true);
                const data = await getLatestDraws();
                setDraws(data);
                setError(null);
            } catch (err) {
                setError('Failed to fetch lottery data. Please try again later.');
                console.error(err);
            } finally {
                setLoading(false);
            }
        };

        fetchDraws();
    }, []); // Empty dependency array means this effect runs once on mount.

    if (loading) {
        return <div className="loading">Loading...</div>;
    }

    if (error) {
        return <div className="error">{error}</div>;
    }

    return (
        <div className="lottery-container">
            <h1>Latest Lottery Results</h1>
            {draws.length === 0 ? (
                <p>No lottery data available at the moment.</p>
            ) : (
                <table className="lottery-table">
                    <thead>
                        <tr>
                            <th>Lottery Type</th>
                            <th>Issue Number</th>
                            <th>Draw Date</th>
                            <th>Numbers</th>
                        </tr>
                    </thead>
                    <tbody>
                        {draws.map((draw) => (
                            <tr key={draw.id}>
                                <td>{draw.lottery_type}</td>
                                <td>{draw.issue_number}</td>
                                <td>{draw.draw_date}</td>
                                <td className="numbers">{draw.numbers}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
};

export default LotteryDisplay;
