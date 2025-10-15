import React, { useState, useEffect } from 'react';
import { getLotteryResults } from '../api';
import './HomePage.css';

// Mapping color names to CSS classes
const colorMap = {
    '🟢': 'green',
    '🔵': 'blue',
    '🔴': 'red',
};

const LotteryBanner = ({ result, type }) => {
    if (!result) {
        return (
            <div className="lottery-banner-placeholder">
                <h2>未能加载 {type} 的最新开奖结果</h2>
            </div>
        );
    }

    const { issue_number, winning_numbers, zodiac_signs, colors, drawing_date } = result;
    const specialNumber = winning_numbers[winning_numbers.length - 1];
    const normalNumbers = winning_numbers.slice(0, winning_numbers.length - 1);
    const specialZodiac = zodiac_signs[zodiac_signs.length - 1];
    const normalZodiacs = zodiac_signs.slice(0, zodiac_signs.length - 1);
    const specialColor = colors[colors.length - 1];
    const normalColors = colors.slice(0, colors.length - 1);

    return (
        <div className="lottery-banner">
            <div className="banner-header">
                <h2>{result.lottery_type} - 第 {issue_number} 期</h2>
                <span>{new Date(drawing_date).toLocaleDateString('zh-CN')}</span>
            </div>
            <div className="results-grid">
                <div className="normal-results">
                    {normalNumbers.map((num, index) => (
                        <div key={index} className={`result-item ${colorMap[normalColors[index]] || ''}`}>
                            <div className="number-ball">{num}</div>
                            <div className="zodiac-sign">{normalZodiacs[index]}</div>
                        </div>
                    ))}
                </div>
                <div className="plus-symbol">+</div>
                <div className="special-result">
                     <div className={`result-item ${colorMap[specialColor] || ''}`}>
                        <div className="number-ball special">{specialNumber}</div>
                        <div className="zodiac-sign">{specialZodiac}</div>
                    </div>
                </div>
            </div>
        </div>
    );
};

const HomePage = () => {
    const [results, setResults] = useState({
        '新澳门六合彩': null,
        '香港六合彩': null,
        '老澳门六合彩': null,
    });
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchAllResults = async () => {
            try {
                const types = ['新澳门六合彩', '香港六合彩', '老澳门六合彩'];
                const promises = types.map(type => getLotteryResults(type));
                const responses = await Promise.all(promises);

                const newResults = {};
                responses.forEach((response, index) => {
                    if (response.status === 'success') {
                        newResults[types[index]] = response.data;
                    }
                });

                setResults(newResults);
            } catch (error) {
                console.error("Failed to fetch lottery results:", error);
            } finally {
                setLoading(false);
            }
        };
        fetchAllResults();
    }, []);

    return (
        <div className="home-page">
            <div className="lottery-container">
                {loading ? (
                    <div className="lottery-banner-placeholder"><h2>正在加载开奖结果...</h2></div>
                ) : (
                    Object.entries(results).map(([type, result]) => (
                        <LotteryBanner key={type} result={result} type={type} />
                    ))
                )}
            </div>
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