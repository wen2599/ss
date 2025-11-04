import React, { useState, useEffect } from 'react';
import { getLatestLotteryNumber } from '../api';

export default function LotteryDisplay() {
    const [lotteryData, setLotteryData] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        const fetchData = async () => {
            try {
                const data = await getLatestLotteryNumber();
                setLotteryData(data);
            } catch (err) {
                setError('无法获取最新开奖号码，可能是暂无记录。');
            }
        };

        const intervalId = setInterval(fetchData, 30000); // 每30秒刷新一次
        fetchData(); // 立即加载一次

        return () => clearInterval(intervalId); // 组件卸载时清除定时器
    }, []);

    if (error) {
        return <div className="lottery-container error">{error}</div>;
    }

    if (!lotteryData) {
        return <div className="lottery-container">加载中...</div>;
    }

    return (
        <div className="lottery-container">
            <h1>最新开奖号码</h1>
            <p className="lottery-number">{lotteryData.number}</p>
            <p className="timestamp">更新时间: {new Date(lotteryData.created_at).toLocaleString()}</p>
        </div>
    );
}