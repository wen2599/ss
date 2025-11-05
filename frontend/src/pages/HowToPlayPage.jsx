import React from 'react';

const HowToPlayPage = () => (
    <div className="card">
        <div className="card-header">
            <h3>玩法介绍</h3>
        </div>
        <div className="card-body">
            <p>欢迎来到我们的竞猜平台！在这里，您可以对各种电竞赛事进行竞猜。</p>
            <h4>如何参与：</h4>
            <ol>
                <li><strong>注册/登录：</strong> 首先，您需要一个账户。如果您还没有账户，请先注册。</li>
                <li><strong>浏览赛事：</strong> 登录后，您可以在首页或赛事页面看到即将开始的比赛。</li>
                <li><strong>进行竞猜：</strong> 选择您感兴趣的比赛，点击“竞猜”按钮，选择您认为会获胜的队伍，并输入您想投注的金额。</li>
                <li><strong>查看结果：</strong> 比赛结束后，您可以在“我的竞猜”或“比赛结果”页面查看您的竞猜是否正确以及您的收益。</li>
            </ol>
            <h4>积分与奖励：</h4>
            <p>每次正确的竞猜都会为您赢得积分，积分可以用来兑换各种奖励。</p>
        </div>
    </div>
);

export default HowToPlayPage;