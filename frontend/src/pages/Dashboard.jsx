import React from 'react';
import WinningNumbers from '../components/WinningNumbers';
import BetHistory from '../components/BetHistory';

function Dashboard() {
    return (
        <div>
            <h1>仪表盘</h1>
            <p>在这里您可以查看最新的开奖号码和您的下注历史。</p>
            <div className="container">
                <WinningNumbers />
            </div>
            <div className="container">
                <BetHistory />
            </div>
        </div>
    );
}

export default Dashboard;