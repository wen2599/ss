import React from 'react';
import { Link } from 'react-router-dom';
import './HomePage.css';

const HomePage = () => {
    return (
        <div className="page-container home-container">
            <div className="hero-section">
                <h1 className="hero-title">欢迎来到账单中心</h1>
                <p className="hero-subtitle">轻松管理您的所有电子账单。</p>
                <div className="cta-buttons">
                    <Link to="/login" className="btn btn-primary">登录</Link>
                    <Link to="/register" className="btn btn-secondary">注册</Link>
                </div>
            </div>
        </div>
    );
};

export default HomePage;