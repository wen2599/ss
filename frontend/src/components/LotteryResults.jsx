import React from 'react';
import './LotteryResults.css';

const LotteryResults = ({ results }) => {

    if (!results || results.length === 0) {
        return <p className="no-results">No lottery results found.</p>;
    }

    const formatDate = (dateString) => {
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return new Date(dateString).toLocaleDateString(undefined, options);
    };
    
    const renderLotteryNumber = (lotteryNumber, lotteryType) => {
        const parts = lotteryNumber.split('+');
        const mainNumbers = parts[0].trim().split(/\s+/);
        const specialNumber = parts[1] ? parts[1].trim() : '';

        return (
            <div className="lottery-number">
                <div className="main-numbers">
                    {mainNumbers.map((num, index) => (
                        <span key={index} className={`number-ball ${lotteryType === '双色球' ? 'red' : 'blue'}`}>{num}</span>
                    ))}
                </div>
                {specialNumber && (
                    <div className="special-number">
                        <span className={`number-ball ${lotteryType === '双色球' ? 'blue' : 'green'}`}>{specialNumber}</span>
                    </div>
                )}
            </div>
        );
    };

    return (
        <div className="lottery-results-container">
            {results.map((result) => (
                <div key={result.id} className="result-card">
                    <div className="result-header">
                        <h2 className="lottery-type">{result.lottery_type}</h2>
                        <p className="draw-date">{formatDate(result.draw_date)}</p>
                    </div>
                    {renderLotteryNumber(result.lottery_number, result.lottery_type)}
                </div>
            ))}
        </div>
    );
};

export default LotteryResults;
