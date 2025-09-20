import React from 'react';
import { useAuth } from '../context/AuthContext';

const HomePage = () => {
    const { user, logout } = useAuth();

    return (
        <div className="page-container">
            <div style={{ textAlign: 'center' }}>
                <h2>欢迎使用本应用！</h2>
                {user && <p>您的登录身份是: <strong>{user.email}</strong></p>}
                <p>这里是认证用户的主内容区域。</p>
                <button onClick={logout} style={{ marginTop: '20px' }}>
                    退出
                </button>
            </div>
        </div>
    );
};

export default HomePage;
