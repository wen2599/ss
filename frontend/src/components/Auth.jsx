import React, { useState } from 'react';

// API 调用函数封装
const apiCall = async (action, data) => {
    // 请求会由 public/_worker.js 代理到后端
    const response = await fetch(`/api/?action=${action}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
    });
    // 如果响应不是JSON，这里会抛出错误，可以被catch捕获
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
        
        try {
            const action = isLogin ? 'login' : 'register';
            const result = await apiCall(action, { email, password });

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
        } catch (err) {
            // 这个catch块可以捕获网络错误或JSON解析错误
            console.error("API call failed:", err);
            setError('请求失败，请检查网络或联系管理员。可能后端服务异常。');
        } finally {
            setIsLoading(false);
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
                    autoComplete="email" 
                />
                <input
                    type="password"
                    placeholder="密码"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    minLength="6"
                    required
                    autoComplete={isLogin ? "current-password" : "new-password"}
                />
                <button type="submit" disabled={isLoading}>                    {isLoading ? '处理中...' : (isLogin ? '登录' : '注册')}
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