import React, { useState, useEffect } from 'react';
import LoginPage from './components/LoginPage';
import RegisterPage from './components/RegisterPage';
import EmailPage from './components/EmailPage';

const App = () => {
    const [page, setPage] = useState('login');
    const [authToken, setAuthToken] = useState(localStorage.getItem('authToken'));
    const [userId, setUserId] = useState(localStorage.getItem('userId'));

    useEffect(() => {
        if (authToken) {
            setPage('emails');
        }
    }, [authToken]);

    const handleLogin = (token, id) => {
        localStorage.setItem('authToken', token);
        localStorage.setItem('userId', id);
        setAuthToken(token);
        setUserId(id);
        setPage('emails');
    };

    const handleLogout = () => {
        localStorage.removeItem('authToken');
        localStorage.removeItem('userId');
        setAuthToken(null);
        setUserId(null);
        setPage('login');
    };

    let content;
    if (page === 'emails') {
        content = <EmailPage authToken={authToken} userId={userId} onLogout={handleLogout} />;
    } else if (page === 'register') {
        content = <RegisterPage onRegister={() => setPage('login')} />;
    } else {
        content = <LoginPage onLogin={handleLogin} onNavigateToRegister={() => setPage('register')} />;
    }

    return (
        <div>
            {content}
        </div>
    );
};

export default App;
