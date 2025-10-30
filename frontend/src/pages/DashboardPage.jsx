import React, { useEffect, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';

const DashboardPage = () => {
    const { user } = useAuth();
    const [emails, setEmails] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchEmails = async () => {
            try {
                setLoading(true);
                // 调用新的受保护的API action
                const response = await api.get('/api.php?action=get_user_emails');
                setEmails(response.data);
            } catch (error) {
                console.error("无法获取邮件列表:", error);
            } finally {
                setLoading(false);
            }
        };

        fetchEmails();
    }, []);


    return (
        <div>
            <h2>仪表盘</h2>
            <p>欢迎回来, {user?.email}!</p>
            <h3>您的邮件列表</h3>
            {loading ? (
                <p>正在加载邮件...</p>
            ) : (
                <ul>
                    {emails.map(email => (
                        <li key={email.id}>{email.subject}</li>
                    ))}
                </ul>
            )}
        </div>
    );
};

export default DashboardPage;