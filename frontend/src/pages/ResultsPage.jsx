import React from 'react';
import { useApi } from '../hooks/useApi';
import { api } from '../api';
import Card from '../components/common/Card';

function ResultsPage() {
    const { data: numbers, loading, error } = useApi(api.getWinningNumbers);
    
    return (
        <Card>
            <div className="card-header">历史开奖公告</div>
            {loading && <p>加载中...</p>}
            {error && <p className="error-message">加载失败: {error.message}</p>}
            {numbers && (
                <div className="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>期号</th>
                                <th>开奖日期</th>
                                <th>开奖号码</th>
                            </tr>
                        </thead>
                        <tbody>
                            {numbers.map(num => (
                                <tr key={num.issue_number}>
                                    <td>{num.issue_number}</td>
                                    <td>{num.draw_date}</td>
                                    <td><strong>{num.numbers}</strong></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            {/* TODO: Pagination controls here */}
        </Card>
    );
}
export default ResultsPage;