import React from 'react';
import LoginForm from '../components/LoginForm'; // 调整路径
import { Link } from 'react-router-dom';

const Login = () => (
    <div className="card auth-card">
        <div className="card-header">
            <h3>登录</h3>
        </div>
        <LoginForm />
        <p className="auth-switch">
            还没有账户？ <Link to="/register">立即注册</Link>
        </p>
    </div>
);

export default Login;