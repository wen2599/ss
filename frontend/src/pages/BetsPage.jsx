import React from 'react';
import { useApi } from '../hooks/useApi'; // 使用花括号进行命名导入
import { api } from '../api';
import Card from '../components/common/Card';
import StatusPill from '../components/common/StatusPill';

// 模拟数据，直到后端API就绪
const mockBets = [
    { id: 1, issue_number: '2023125', status: 'processed', winning_amount: 1500.00 },
    { id: 2, issue_number: '2023125', status: 'error', winning_amount: 0.00 },
    { id: 3, issue_number: '2023126', status: 'pending', winning_amount: 0.00 },
    { id: 4, issue_number: '2023126', status: 'processing', winning_amount: 0.00 },
];

function BetsPage() {
    // 当后端API就绪时，解除下面的注释即可
    // const { data: bets, loading, error } = useApi(api.getMyBets);
    
    // 暂时使用模拟数据
    const bets = mockBets;
    const loading = false;
    const error = null;

    return (
        <Card>
            <div className="card-header">我的注单</div>
            {loading && <p>加载中...</p>}
            {error && <p style={{ color: 'var(--error-color)'}}>加载失败</p>}
            {bets && (
                <div className="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>注单ID</th>
                                <th>期号</th>
                                <th>状态</th>
                                <th>结算金额</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            {bets.map(bet => (
                                <tr key={bet.id}>
                                    <td>#{bet.id}</td>
                                    <td>{bet.issue_number}</td>
                                    <td><StatusPill status={bet.status} /></td>
                                    <td>¥{bet.winning_amount.toFixed(2)}</td>
                                    <td><button className="btn btn-secondary">查看详情</button></td>
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