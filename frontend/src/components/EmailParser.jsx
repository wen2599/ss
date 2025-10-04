import React, { useState } from 'react';

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
            <h1>邮件解析器</h1>
            <p>请在下方粘贴原始邮件文本以提取其内容。</p>
            <textarea
                value={emailText}
                onChange={(e) => setEmailText(e.target.value)}
                placeholder="发件人: user@example.com&#10;主题: 你好世界&#10;&#10;这是邮件正文..."
                disabled={isLoading}
            />
            <button
                onClick={handleProcessEmail}
                className="btn"
                disabled={isLoading}
            >
                {isLoading ? '正在处理...' : '处理邮件'}
            </button>

            {error && <p className="message error-message">{error}</p>}

            {parsedData && (
                <div className="parser-results">
                    <h3>解析结果</h3>
                    <p><strong>发件人:</strong> {parsedData.from}</p>
                    <p><strong>收件人:</strong> {parsedData.to}</p>
                    <p><strong>日期:</strong> {parsedData.date}</p>
                    <p><strong>主题:</strong> {parsedData.subject}</p>
                    <div>
                        <strong>正文:</strong>
                        <pre>{parsedData.body}</pre>
                    </div>
                </div>
            )}
        </div>
    );
};

export default EmailParser;