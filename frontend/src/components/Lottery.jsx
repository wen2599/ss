import React, { useState, useEffect } from 'react';

function Lottery({ user, onLogout }) {
    const [numbers, setNumbers] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        const fetchNumbers = async () => {
            try {
                // 请求 /api/?action=get_numbers
                const response = await fetch('/api/?action=get_numbers');
                const result = await response.json();
                if (result.success) {
                    setNumbers(result.data);
                } else {
                    setError(result.message || '获取数据失败');
                }
            } catch (err) {
                setError('无法连接到服务器');
            } finally {
                setIsLoading(false);
            }
        };

        fetchNumbers();
    }, []);

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <h1>开奖号码列表</h1>
                <div>
                    <span>欢迎, {user.email}</span>
                    <button onClick={onLogout} style={{ marginLeft: '1rem' }}>退出登录</button>
                </div>
            </div>
            
            {isLoading && <p>加载中...</p>}
            {error && <p className="error-message">{error}</p>}
            
            {!isLoading && !error && (
                <table className="lottery-table">
                    <thead>
                        <tr>
                            <th>开奖日期</th>
                            <th>期号</th>
                            <th>开奖号码</th>
                        </tr>
                    </thead>
                    <tbody>
                        {numbers.map((item) => (
                            <tr key={item.issue_number}>
                                <td>{item.draw_date}</td>
                                <td>{item.issue_number}</td>
                                <td>{item.winning_numbers}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}

export default Lottery;