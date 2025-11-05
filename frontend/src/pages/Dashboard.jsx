import React from 'react';
import { Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import { api } from '../api';
import Card from '../components/common/Card'; // 假设你创建了Card组件

function Dashboard() {
    const { data: latestResult, loading, error } = useApi(() => api.getWinningNumbers(1)); // 获取最新一条

    return (
        <div>
            <h1>仪表盘</h1>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '1.5rem' }}>
                <Card>
                    <div className="card-header">最新开奖</div>
                    {loading && <p>加载中...</p>}
                    {error && <p>加载失败</p>}
                    {latestResult && latestResult.length > 0 && (
                        <div>
                            <h3>{latestResult[0].issue_number}期</h3>
                            <p><strong>开奖号码:</strong> {latestResult[0].numbers}</p>
                            <p><strong>日期:</strong> {latestResult[0].draw_date}</p>
                            <Link to="/results">查看更多 &rarr;</Link>
                        </div>
                    )}
                </Card>
                <Card>
                    <div className="card-header">快速导航</div>
                    <ul>
                        <li><Link to="/my-bets">查看我的注单</Link></li>
                        <li><Link to="/results">历史开奖记录</Link></li>
                    </ul>
                </Card>
                <Card>
                    <div className="card-header">如何下注？</div>
                    <p>通过注册邮箱发送格式化的注单到指定地址即可。</p>
                    <Link to="/how-to-play">查看详细说明 &rarr;</Link>
                </Card>
            </div>
        </div>
    );
}

export default Dashboard;