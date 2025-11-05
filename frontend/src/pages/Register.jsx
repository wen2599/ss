import React from 'react';
import { Link } from 'react-router-dom';
import RegisterForm from '../components/RegisterForm'; // 我们将在下一步创建这个组件
import Card from '../components/common/Card';

function Register() {
  return (
    // 使用一个 wrapper 来居中卡片
    <div style={{ maxWidth: '500px', margin: '4rem auto' }}>
      <Card>
        <div className="card-header">
          <h2>创建您的账户</h2>
          <p style={{ color: 'var(--text-muted-color)', fontSize: '0.9rem', margin: '0.5rem 0 0 0' }}>
            已有账户？ <Link to="/login">立即登录</Link>
          </p>
        </div>
        <div style={{ padding: '1.5rem 0 0 0' }}>
          <RegisterForm />
        </div>
      </Card>
    </div>
  );
}

export default Register;