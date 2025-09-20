import React, { useState } from 'react';
import { useAuth } from './context/AuthContext';
import LoginModal from './components/LoginModal';
import RegisterModal from './components/RegisterModal';
import './App.css';

function App() {
    const { isAuthenticated, user, login, logout, loading } = useAuth();
    const [showLoginModal, setShowLoginModal] = useState(false);
    const [showRegisterModal, setShowRegisterModal] = useState(false);

    const handleLoginSuccess = (userData) => {
        login(userData);
        setShowLoginModal(false);
    };

    const handleRegisterSuccess = (userData) => {
        login(userData); // Log in the new user automatically
        setShowRegisterModal(false);
    };

    if (loading) {
        return <div className="loading-container">正在加载应用状态...</div>;
    }

    return (
        <div className="App">
            {showLoginModal && <LoginModal onClose={() => setShowLoginModal(false)} onLoginSuccess={handleLoginSuccess} />}
            {showRegisterModal && <RegisterModal onClose={() => setShowRegisterModal(false)} onRegisterSuccess={handleRegisterSuccess} />}

            <header className="App-header">
                <h1>用户认证系统</h1>
                <div className="auth-buttons">
                    {!isAuthenticated ? (
                        <>
                            <button onClick={() => setShowLoginModal(true)}>登录</button>
                            <button onClick={() => setShowRegisterModal(true)}>注册</button>
                        </>
                    ) : (
                        <>
                            <span className="welcome-user">欢迎, {user?.email}</span>
                            <button onClick={logout}>退出</button>
                        </>
                    )}
                </div>
            </header>
            <main className="App-main">
                <div className="content-container">
                    {isAuthenticated ? (
                        <div>
                            <h2>您已成功登录</h2>
                            <p>这是一个极简版的应用程序。</p>
                        </div>
                    ) : (
                        <div>
                            <h2>请登录或注册</h2>
                            <p>所有功能都需要登录后才能使用。</p>
                        </div>
                    )}
                </div>
            </main>
        </div>
    );
}

export default App;
