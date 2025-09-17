import React from 'react';
import './DrawBanner.css'; // We will create this CSS file next

const DrawBanner = ({ title, drawData }) => {
    if (!drawData) {
        return (
            <div className="draw-banner">
                <h3>{title}</h3>
                <p>等待开奖...</p>
            </div>
        );
    }

    const { issue_number, winning_numbers } = drawData;
    const { numbers, colors } = winning_numbers;

    const colorMap = {
        red: '#dc3545',
        green: '#28a745',
        blue: '#007bff',
        unknown: '#6c757d'
    };

    return (
        <div className="draw-banner">
            <h3>{title} - 第 {issue_number} 期</h3>
            <div className="numbers-container">
                {numbers.map((num, index) => (
                    <div key={index} className="number-ball-wrapper">
                        <div
                            className="number-ball"
                            style={{ backgroundColor: colorMap[colors[index]] || colorMap.unknown }}
                        >
                            {String(num).padStart(2, '0')}
                        </div>
                        {index === 6 && <span className="special-marker">特</span>}
                    </div>
                ))}
            </div>
        </div>
    );
};

export default DrawBanner;
