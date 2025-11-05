import React from 'react';

function BetHistory() {
    // TODO: 实现获取用户下注历史的 API 调用
    // const { data: bets, loading, error } = useApi(api.getMyBets);

    return (
        <div>
            <h2>我的下注历史</h2>
            <p>此功能正在开发中。</p>
            {/* 
            一旦后端 API 完成，可以像下面这样渲染：
            {loading && <p>加载中...</p>}
            {error && <p className="error-message">加载失败</p>}
            {bets && bets.map(bet => (
                <div key={bet.id}>
                    <p>期号: {bet.issue_number}</p>
                    <p>结算金额: {bet.winning_amount}</p>
                    <details>
                        <summary>查看原始邮件</summary>
                        <pre>{bet.raw_email_content}</pre>
                    </details>
                </div>
            ))}
            */}
        </div>
    );
}

export default BetHistory;