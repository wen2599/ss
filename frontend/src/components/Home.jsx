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
                <div className="welcome-message" style={{ marginTop: '2rem' }}>
                    <h2>你好, {user.username}!</h2>
                    <p>您已登录，可以开始使用了。</p>
                    <Link to="/parser" className="btn" style={{ marginTop: '1rem' }}>前往解析器</Link>
                </div>
            ) : (
                <div className="home-actions">
                    <p>请使用右上角的链接登录或注册后开始使用。</p>
                </div>
            )}
        </div>
    );
};

export default Home;