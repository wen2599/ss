import React from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const Home = () => {
    const { isAuthenticated, user } = useAuth();

    return (
        <div className="home-container">
            <h1>欢迎使用邮件解析器</h1>
            <p>一个用于从邮件中提取关键信息的智能工具。</p>

            {isAuthenticated ? (
                <div className="welcome-message">
                    <h2>你好, {user.username}!</h2>
                    <p>您已登录，可以开始使用了。</p>
                    <Link to="/parser" className="btn">前往解析器</Link>
                </div>
            ) : (
                <div className="home-actions">
                    <p>请登录或注册后开始使用。</p>
                    <div className="action-buttons">
                        <Link to="/login" className="btn">登录</Link>
                        <Link to="/register" className="btn">注册</Link>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Home;