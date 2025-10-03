import React, { useState } from 'react';
import './EmailParser.css';

const EmailParser = () => {
    const [emailText, setEmailText] = useState('');
    const [parsedData, setParsedData] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState('');

    const handleProcessEmail = async () => {
        if (!emailText.trim()) {
            setError('邮件内容不能为空。');
            return;
        }

        setIsLoading(true);
        setError('');
        setParsedData(null);

        try {
            const response = await fetch('/process_email', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text: emailText }),
            });

            const data = await response.json();

            if (!response.ok) {
                // Handle both authentication errors and other server errors
                throw new Error(data.error || '发生未知错误。');
            }

            setParsedData(data);
        } catch (err) {
            setError(err.message);
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="parser-container">
            <h2>邮件解析器</h2>
            <p>请在下方粘贴原始邮件文本以提取其内容。</p>
            <textarea
                value={emailText}
                onChange={(e) => setEmailText(e.target.value)}
                placeholder="发件人: user@example.com&#10;主题: 你好世界&#10;&#10;这是邮件正文..."
                className="parser-textarea"
                disabled={isLoading}
            />
            <button
                onClick={handleProcessEmail}
                className="btn btn-primary"
                disabled={isLoading}
            >
                {isLoading ? '正在处理...' : '处理邮件'}
            </button>

            {error && <div className="error-message">{error}</div>}

            {parsedData && (
                <div className="results-container">
                    <h3>解析结果</h3>
                    <div className="result-item">
                        <strong>发件人:</strong>
                        <p>{parsedData.from}</p>
                    </div>
                    <div className="result-item">
                        <strong>收件人:</strong>
                        <p>{parsedData.to}</p>
                    </div>
                    <div className="result-item">
                        <strong>日期:</strong>
                        <p>{parsedData.date}</p>
                    </div>
                    <div className="result-item">
                        <strong>主题:</strong>
                        <p>{parsedData.subject}</p>
                    </div>
                    <div className="result-item">
                        <strong>正文:</strong>
                        <pre className="result-body">{parsedData.body}</pre>
                    </div>
                </div>
            )}
        </div>
    );
};

export default EmailParser;