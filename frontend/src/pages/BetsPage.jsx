import React from 'react';
import useApi from '../hooks/useApi'; // 调整路径

const BetsPage = () => {
    const { data: bets, loading, error } = useApi('/bets');

    if (loading) return <p>加载中...</p>;
    if (error) return <p>加载竞猜失败: {error.message}</p>;

    return (
        <div className="card">
            <div className="card-header">
                <h3>我的竞猜</h3>
            </div>
            <div className="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>比赛</th>
                            <th>我的选择</th>
                            <th>金额</th>
                            <th>状态</th>
                        </tr>
                    </thead>
                    <tbody>
                        {bets && bets.map(bet => (
                            <tr key={bet.id}>
                                <td>{bet.match.name}</td>
                                <td>{bet.prediction}</td>
                                <td>{bet.amount}</td>
                                <td><span className={`pill pill-${bet.status === 'won' ? 'success' : bet.status === 'lost' ? 'error' : 'warning'}`}>{bet.status}</span></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default BetsPage;