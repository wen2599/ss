import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { getLotteryNumbers } from '../api/lottery';

const DashboardPage = () => {
    const [numbers, setNumbers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const { token, logout } = useAuth();

    useEffect(() => {
        const fetchNumbers = async () => {
            try {
                const data = await getLotteryNumbers(token);
                setNumbers(data);
            } catch (err) {
                setError(err.message);
                // 如果是 token 问题，可以自动登出
                if (err.message.includes('401') || err.message.includes('403')) {
                   logout();
                }
            } finally {
                setLoading(false);
            }
        };

        fetchNumbers();
    }, [token, logout]);

    if (loading) return <p>正在加载开奖号码...</p>;
    if (error) return <p style={{ color: 'red' }}>加载失败: {error}</p>;

    return (
        <div>
            <h2>最新开奖号码</h2>
            {numbers.length > 0 ? (
                <table>
                    <thead>
                        <tr>
                            <th>开奖日期</th>
                            <th>号码</th>
                        </tr>
                    </thead>
                    <tbody>
                        {numbers.map(item => (
                            <tr key={item.id}>
                                <td>{item.issue_date}</td>
                                <td>{item.number}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
                <p>暂无开奖数据。</p>
            )}
        </div>
    );
};

export default DashboardPage;