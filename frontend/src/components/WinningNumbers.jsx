import React from 'react';
import { useApi } from '../hooks/useApi';
import { api } from '../api';

function WinningNumbers() {
    const { data: numbers, loading, error } = useApi(api.getWinningNumbers);

    if (loading) return <p>正在加载开奖号码...</p>;
    if (error) return <p className="error-message">加载失败: {error.message}</p>;

    return (
        <div>
            <h2>最新开奖号码</h2>
            {numbers && numbers.length > 0 ? (
                <ul>
                    {numbers.map(num => (
                        <li key={num.issue_number}>
                            <strong>{num.issue_number}期</strong> ({num.draw_date}): {num.numbers}
                        </li>
                    ))}
                </ul>
            ) : (
                <p>暂无开奖数据。</p>
            )}
        </div>
    );
}

export default WinningNumbers;