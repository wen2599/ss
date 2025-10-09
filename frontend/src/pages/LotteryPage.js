import React, { useState, useEffect } from 'react';
import './LotteryPage.css'; // We will create this file

const LotteryPage = () => {
    const [lotteryNumber, setLotteryNumber] = useState('Loading...');

    useEffect(() => {
        fetch('/api/get_numbers')
            .then(response => response.json())
            .then(data => {
                if (data.lottery_number) {
                    setLotteryNumber(data.lottery_number);
                }
            })
            .catch(error => console.error('Error fetching lottery number:', error));
    }, []);

    return (
        <div className="lottery-container">
            <h1 className="lottery-title">最新开奖号码</h1>
            <p className="lottery-number">{lotteryNumber}</p>
        </div>
    );
};

export default LotteryPage;
