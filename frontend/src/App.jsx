import React, { useState, useEffect } from 'react';
import Auth from './components/Auth';
import LotteryDisplay from './components/LotteryDisplay';
import './App.css'; // 引入一些基本样式

function App() {
    const [user, setUser] = useState(null);

    // 页面加载时，尝试从 localStorage 恢复用户状态
    useEffect(() => {
        const storedUser = localStorage.getItem('lotteryUser');
        if (storedUser) {
            setUser(JSON.parse(storedUser));
        }
    }, []);

    const handleLoginSuccess = (userData) => {
        setUser(userData);
        // 将用户信息存入 localStorage，以便刷新页面后保持登录状态
        localStorage.setItem('lotteryUser', JSON.stringify(userData));
    };

    const handleLogout = () => {
        setUser(null);
        localStorage.removeItem('lotteryUser');
    };

    return (
        <div className="App">
            <header>
                <h1>开奖号码展示系统</h1>
                {user && (
                    <div className="user-info">
                        <span>欢迎, {user.email}</span>
                        <button onClick={handleLogout}>退出登录</button>
                    </div>
                )}
            </header>
            <main>
                {user ? (
                    <LotteryDisplay />
                ) : (
                    <Auth onLoginSuccess={handleLoginSuccess} />
                )}
            </main>
        </div>
    );
}

export default App;