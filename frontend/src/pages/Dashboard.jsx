import React from 'react';
import useApi from '../hooks/useApi'; // 调整路径

const Dashboard = () => {
    const { data: matches, loading, error } = useApi('/matches/upcoming');

    if (loading) return <p>加载中...</p>;
    if (error) return <p>加载比赛失败: {error.message}</p>;

    return (
        <div className="card">
            <div className="card-header">
                <h3>即将开始的比赛</h3>
            </div>
            <div className="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>比赛</th>
                            <th>时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        {matches && matches.map(match => (
                            <tr key={match.id}>
                                <td>{match.name}</td>
                                <td>{new Date(match.startTime).toLocaleString()}</td>
                                <td>
                                    <button className="btn btn-secondary">猜</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default Dashboard;