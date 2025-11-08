// src/pages/SettlementsPage.jsx
import React, { useState, useEffect } from 'react';
import { apiService } from '../api';

function SettlementsPage() {
    const [settlements, setSettlements] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        apiService.getSettlements()
            .then(data => setSettlements(data.data))
            .finally(() => setLoading(false));
    }, []);

    if (loading) return <p>正在加载结算单...</p>;

    return (
        <div className="card">
            <h2>结算表单</h2>
            <table>
                <thead>
                    <tr><th>ID</th><th>投注ID</th><th>总赢/亏</th><th>创建时间</th></tr>
                </thead>
                <tbody>
                    {settlements.map(item => (
                        <tr key={item.id}>
                            <td>{item.id}</td>
                            <td>{item.bet_id}</td>
                            <td style={{ color: item.total_win > 0 ? 'green' : 'red' }}>{item.total_win}</td>
                            <td>{new Date(item.created_at).toLocaleString()}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default SettlementsPage;