import React from 'react';
import RegisterForm from '../components/RegisterForm'; // 调整路径
import { Link } from 'react-router-dom';

const Register = () => (
    <div className="card auth-card">
        <div className="card-header">
            <h3>注册</h3>
        </div>
        <RegisterForm />
        <p className="auth-switch">
            已有账户？ <Link to="/login">立即登录</Link>
        </p>
    </div>
);

export default Register;