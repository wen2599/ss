import React, { useState } from 'react';

// API 调用函数封装
const apiCall = async (action, data) => {
    // 注意：我们请求的是 /api/ 路径，它会被 _worker.js 代理
    const response = await fetch(`/api/?action=${action}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
    });
    return response.json();
};

function Auth({ onLoginSuccess }) {
    const [isLogin, setIsLogin] = useState(true);
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setMessage('');
        setIsLoading(true);
        
        const action = isLogin ? 'login' : 'register';
        const result = await apiCall(action, { email, password });

        setIsLoading(false);
        if (result.success) {
            if (isLogin) {
                onLoginSuccess(result.user);
            } else {
                setMessage('注册成功！请切换到登录页面。');
                setIsLogin(true); // 注册成功后自动切换到登录
            }
        } else {
            setError(result.message || '操作失败');
        }
    };

    return (
        <div className="form-container">
            <h2>{isLogin ? '登录' : '注册'}</h2>
            <form onSubmit={handleSubmit}>
                <input
                    type="email"
                    placeholder="邮箱"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                />
                <input
                    type="password"
                    placeholder="密码"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    minLength="6"
                    required
                />
                <button type="submit" disabled={isLoading}>
                    {isLoading ? '处理中...' : (isLogin ? '登录' : '注册')}
                </button>
            </form>
            {error && <p className="error-message">{error}</p>}
            {message && <p>{message}</p>}
            <p>
                {isLogin ? '还没有账户？' : '已有账户？'}
                <span className="toggle-link" onClick={() => { setIsLogin(!isLogin); setError(''); setMessage(''); }}>
                    {isLogin ? '点击注册' : '点击登录'}
                </span>
            </p>
        </div>
    );
}

export default Auth;