import React, { useState } from 'react';
import { loginUser, registerUser } from '../api';

export default function Auth({ onLoginSuccess }) {
    const [isLoginView, setIsLoginView] = useState(true);
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setMessage('');

        try {
            if (isLoginView) {
                const data = await loginUser(email, password);
                setMessage(data.message);
                // 调用父组件传入的回调函数，通知 App 组件登录成功
                onLoginSuccess(data.user);
            } else {
                const data = await registerUser(email, password);
                setMessage(data.message + ' 现在可以登录了。');
                // 注册成功后自动切换到登录视图
                setIsLoginView(true);
            }
        } catch (err) {
            setError(err.message);
        }
    };

    return (
        <div className="auth-container">
            <h2>{isLoginView ? '登录' : '注册'}</h2>
            <form onSubmit={handleSubmit}>
                <div className="form-group">
                    <label htmlFor="email">邮箱</label>
                    <input
                        type="email"
                        id="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        required
                    />
                </div>
                <div className="form-group">
                    <label htmlFor="password">密码</label>
                    <input
                        type="password"
                        id="password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        required
                    />
                </div>
                <button type="submit">{isLoginView ? '登录' : '注册'}</button>
            </form>
            {error && <p className="error-message">{error}</p>}
            {message && <p className="success-message">{message}</p>}
            <p className="toggle-view">
                {isLoginView ? '还没有账户？' : '已有账户？'}
                <button onClick={() => {
                    setIsLoginView(!isLoginView);
                    setError('');
                    setMessage('');
                }}>
                    {isLoginView ? '点击注册' : '点击登录'}
                </button>
            </p>
        </div>
    );
}