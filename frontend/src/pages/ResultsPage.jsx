import React from 'react';
import { useApi } from '../hooks/useApi'; // 使用花括号进行命名导入
import { api } from '../api';
import Card from '../components/common/Card';

function ResultsPage() {
    // 调用 useApi，不带参数即表示 limit=100 (api.js中的默认值)
    const { data: numbers, loading, error } = useApi(api.getWinningNumbers);
    
    return (
        <Card>
            <div className="card-header">历史开奖公告</div>
            {loading && <p>加载中...</p>}
            {error && <p style={{ color: 'var(--error-color)'}}>加载失败: {error.message}</p>}
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
        </Card>
    );
}

export default ResultsPage;