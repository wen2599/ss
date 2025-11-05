import React from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext'; // 调整路径

const Home = () => {
    const { user } = useAuth();

    return (
        <div className="home-container">
            <div className="hero-section">
                <h1>欢迎来到在线竞猜平台</h1>
                <p>参与热门电竞赛事竞猜，赢取丰厚奖励！</p>
                {user ? (
                    <Link to="/dashboard" className="btn">进入控制台</Link>
                ) : (
                    <Link to="/login" className="btn">立即开始</Link>
                )}
            </div>

            <div className="features-section">
                <div className="feature">
                    <h3>实时赔率</h3>
                    <p>提供实时更新的赔率，帮助您做出最佳决策。</p>
                </div>
                <div className="feature">
                    <h3>多样赛事</h3>
                    <p>涵盖多种主流电竞赛事，总有您关心的比赛。</p>
                </div>
                <div className="feature">
                    <h3>安全保障</h3>
                    <p>保障您的账户和资金安全，让您无后顾之忧。</p>
                </div>
            </div>
        </div>
    );
};

export default Home;