import React from 'react';
import Card from '../components/common/Card';

function HowToPlayPage() {
    const targetEmail = "bets@yourdomain.com"; // 从环境变量获取更佳

    return (
        <Card>
            <div className="card-header">玩法说明</div>
            <h3>如何通过邮件下注？</h3>
            <p>
                您只需要使用您注册时所用的邮箱，向我们的指定邮箱 <strong>{targetEmail}</strong> 发送一封邮件即可。
                我们的 AI 系统会自动识别邮件内容并为您生成注单。
            </p>
            
            <h4>邮件格式要求:</h4>
            <p>为了确保AI能准确识别，请尽量遵循以下格式。邮件标题随意，系统只读取正文。</p>

            <h5>示例 1: 简单下注</h5>
            <pre style={{ backgroundColor: '#1a1c23', padding: '1rem', borderRadius: '8px' }}>
                <code>
{`期号: 2023125
特码 49 100元
平码 25 50元`}
                </code>
            </pre>

            <h5>示例 2: 多个号码</h5>
            <pre style={{ backgroundColor: '#1a1c23', padding: '1rem', borderRadius: '8px' }}>
                <code>
{`2023125期
买 01, 07, 18, 22, 34, 48 各 10元`}
                </code>
            </pre>
            
            <h4>重要提示:</h4>
            <ul>
                <li>请确保期号正确，否则可能导致注单无效。</li>
                <li>一次邮件可以包含多个下注项目。</li>
                <li>下注成功后，您可以在“我的注单”页面看到状态更新。</li>
                <li>如果邮件格式无法被AI识别，该注单状态将为“错误”，请检查后重新发送。</li>
            </ul>
        </Card>
    );
}

export default HowToPlayPage;