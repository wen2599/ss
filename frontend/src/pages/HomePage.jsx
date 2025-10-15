import React, { useState, useEffect } from 'react';
import { getLotteryResults } from '../api';
import './HomePage.css';

const LotteryBanner = ({ result }) => {
    if (!result) {
        return (
            <div className="lottery-banner">
                <h2>正在获取最新开奖结果...</h2>
            </div>
        );
    }

    const { issue_number, winning_numbers, drawing_date } = result;
    // The special number is the last one
    const specialNumber = winning_numbers[winning_numbers.length - 1];
    const normalNumbers = winning_numbers.slice(0, winning_numbers.length - 1);

    return (
        <div className="lottery-banner">
            <div className="banner-header">
                <h2>第 {issue_number} 期 开奖结果</h2>
                <span>{new Date(drawing_date).toLocaleDateString('zh-CN')}</span>
            </div>
            <div className="numbers-container">
                <div className="normal-numbers">
                    {normalNumbers.map((num, index) => (
                        <div key={index} className="number-ball">{num}</div>
                    ))}
                </div>
                <div className="plus-symbol">+</div>
                <div className="special-number">
                    <div className="number-ball special">{specialNumber}</div>
                </div>
            </div>
        </div>
    );
};

const HomePage = () => {
    const [lotteryResult, setLotteryResult] = useState(null);

    useEffect(() => {
        const fetchResults = async () => {
            try {
                const response = await getLotteryResults();
                if (response.status === 'success' && response.data) {
                    setLotteryResult(response.data);
                }
            } catch (error) {
                console.error("Failed to fetch lottery results:", error);
            }
        };
        fetchResults();
    }, []);

    return (
        <div className="home-page">
            <LotteryBanner result={lotteryResult} />
            <header className="hero-section">
                <h1>欢迎使用您的个人账单管理系统</h1>
                <p>轻松管理您的电子账单，永不错过付款。</p>
            </header>
            <section className="features-section">
                <div className="feature-card">
                    <h2>自动导入</h2>
                    <p>通过电子邮件自动导入您的账单。</p>
                </div>
                <div className="feature-card">
                    <h2>集中管理</h2>
                    <p>在一个地方查看和管理所有账单。</p>
                </div>
                <div className="feature-card">
                    <h2>安全可靠</h2>
                    <p>您的数据安全地存储和传输。</p>
                </div>
            </section>
        </div>
    );
};

export default HomePage;