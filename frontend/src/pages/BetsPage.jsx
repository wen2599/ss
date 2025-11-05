import React, { useState } from 'react';
import Card from '../components/common/Card';
import StatusPill from '../components/common/StatusPill'; // 假设创建了这个组件
// import { useApi } from '../hooks/useApi';
// import { api } from '../api';

// 模拟数据，直到后端API就绪
const mockBets = [
    { id: 1, issue_number: '2023001', status: 'processed', winning_amount: 1500.00, details: { /* ... */ } },
    { id: 2, issue_number: '2023001', status: 'error', winning_amount: 0.00, error_message: '无法识别格式' },
    { id: 3, issue_number: '2023002', status: 'pending', winning_amount: 0.00, details: null },
];

function BetsPage() {
    // const { data: bets, loading, error } = useApi(api.getMyBets);
    const [bets, setBets] = useState(mockBets);
    const loading = false;
    const error = null;

    return (
        <Card>
            <div className="card-header">我的注单</div>
            {loading && <p>加载中...</p>}
            {error && <p className="error-message">加载失败</p>}
            {bets && (
                <div className="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>期号</th>
                                <th>状态</th>
                                <th>结算金额</th>
                                <th>详情</th>
                            </tr>
                        </thead>
                        <tbody>
                            {bets.map(bet => (
                                <tr key={bet.id}>
                                    <td>{bet.id}</td>
                                    <td>{bet.issue_number}</td>
                                    <td><StatusPill status={bet.status} /></td>
                                    <td>¥{bet.winning_amount.toFixed(2)}</td>
                                    <td><button className="btn btn-secondary">查看</button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Card>
    );
}
export default BetsPage;