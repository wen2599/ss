import React from 'react';
import useApi from '../hooks/useApi'; // 调整路径

const ResultsPage = () => {
    const { data: results, loading, error } = useApi('/matches/results');

    if (loading) return <p>加载中...</p>;
    if (error) return <p>加载结果失败: {error.message}</p>;

    return (
        <div className="card">
            <div className="card-header">
                <h3>比赛结果</h3>
            </div>
            <div className="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>比赛</th>
                            <th>结果</th>
                            <th>结束时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        {results && results.map(result => (
                            <tr key={result.id}>
                                <td>{result.match.name}</td>
                                <td>{result.result}</td>
                                <td>{new Date(result.match.endTime).toLocaleString()}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default ResultsPage;