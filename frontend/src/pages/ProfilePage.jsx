import React from 'react';
import { useAuth } from '../context/AuthContext'; // 调整路径

const ProfilePage = () => {
    const { user } = useAuth();

    if (!user) {
        return <p>请先登录。</p>;
    }

    return (
        <div className="card">
            <div className="card-header">
                <h3>个人中心</h3>
            </div>
            <div className="card-body">
                <p><strong>用户名:</strong> {user.username}</p>
                <p><strong>电子邮箱:</strong> {user.email}</p>
                {/* 这里可以添加更多用户信息，例如账户余额、历史记录等 */}
            </div>
        </div>
    );
};

export default ProfilePage;