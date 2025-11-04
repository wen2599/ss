import React, { useState, useEffect } from 'react';
import Auth from './components/Auth';
import Lottery from './components/Lottery';
import './index.css';

function App() {
    // 尝试从 localStorage 获取用户信息
    const [user, setUser] = useState(() => {
        const savedUser = localStorage.getItem('lottery_user');
        try {
            return savedUser ? JSON.parse(savedUser) : null;
        } catch (e) {
            return null;
        }
    });

    // 当 user 状态变化时，更新 localStorage
    useEffect(() => {
        if (user) {
            localStorage.setItem('lottery_user', JSON.stringify(user));
        } else {
            localStorage.removeItem('lottery_user');
        }
    }, [user]);

    const handleLoginSuccess = (loggedInUser) => {
        setUser(loggedInUser);
    };

    const handleLogout = () => {
        setUser(null);
    };

    return (
        <div className="container">
            {user ? (
                <Lottery user={user} onLogout={handleLogout} />
            ) : (
                <Auth onLoginSuccess={handleLoginSuccess} />
            )}
        </div>
    );
}

export default App;