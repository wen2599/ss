import React, { useState, useEffect, useCallback } from 'react';
import api from '../services/api';
import './EmailsPage.css'; // 添加新的样式文件

const EmailsPage = () => {
    const [emails, setEmails] = useState([]);
    const [selectedEmail, setSelectedEmail] = useState(null);
    const [emailBody, setEmailBody] = useState(''); // New state for email body
    const [loadingEmails, setLoadingEmails] = useState(true);
    const [loadingBody, setLoadingBody] = useState(false); // New loading state for body
    const [error, setError] = useState('');

    const fetchEmails = useCallback(async () => {
        try {
            setLoadingEmails(true);
            const response = await api.get('/proxy.php?action=get_user_emails');
            if (response.data.status === 'success') {
                setEmails(response.data.data);
            } else {
                setError(response.data.message);
            }
        } catch (err) {
            setError('获取邮件列表失败');
        } finally {
            setLoadingEmails(false);
        }
    }, []);

    useEffect(() => {
        fetchEmails();
    }, [fetchEmails]);

    const handleSelectEmail = async (email) => {
        setSelectedEmail(email);
        setEmailBody(''); // Clear previous email body
        setError('');

        try {
            setLoadingBody(true);
            // Call the new endpoint to get the parsed email content
            const response = await api.get(`/proxy.php?action=get_email_content&email_id=${email.id}`);
            if (response.data.status === 'success') {
                // The backend returns an object with 'body' and 'type'
                const { body, type } = response.data.data;
                // If it's HTML, we'll display it as such. Otherwise, as plain text.
                setEmailBody({ content: body, isHtml: type === 'html' });
            } else {
                setError(response.data.message);
            }
        } catch (err) {
            setError('获取邮件内容失败');
        } finally {
            setLoadingBody(false);
        }
    };

    const handleGenerateTemplate = async (bodyContent) => {
        const templateName = prompt("Enter a name for this new template:", "my-template");
        if (!templateName) return;

        const aiProvider = prompt("Which AI provider? (cloudflare or gemini)", "cloudflare");
        if (!aiProvider) return;

        alert(`Generating template '${templateName}' using ${aiProvider}. This may take a moment...`);

        try {
            const response = await api.post('/proxy.php?action=generate_parsing_template', {
                email_body: bodyContent,
                template_name: templateName,
                ai_provider: aiProvider
            });

            if (response.data.status === 'success') {
                alert(`Template created successfully!\n\nRegex: ${response.data.regex}`);
            } else {
                alert(`Error: ${response.data.message}`);
            }
        } catch (err) {
            alert('An error occurred while generating the template.');
        }
    };

    return (
        <div className="emails-page-layout">
            <div className="email-list-panel">
                <h2>收件箱</h2>
                {loadingEmails ? <p>加载中...</p> : (
                    <ul>
                        {emails.map(email => (
                            <li 
                                key={email.id} 
                                className={selectedEmail?.id === email.id ? 'active' : ''}
                                onClick={() => handleSelectEmail(email)}
                            >
                                <div className="email-subject">{email.subject}</div>
                                <div className="email-from">{email.from}</div>
                                <div className="email-date">{new Date(email.received_at).toLocaleString()}</div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <div className="email-content-panel">
                {selectedEmail ? (
                    <>
                        <div className="email-content-header">
                            <h2>{selectedEmail.subject}</h2>
                            <button onClick={() => handleGenerateTemplate(emailBody.content)}>
                                Create AI Template
                            </button>
                        </div>
                        {loadingBody ? <p>正在加载内容...</p> : error ? <p className="error-message">{error}</p> : (
                            <div className="email-body">
                                {emailBody.isHtml ? (
                                    <div dangerouslySetInnerHTML={{ __html: emailBody.content }} />
                                ) : (
                                    <pre>{emailBody.content}</pre>
                                )}
                            </div>
                        )}
                    </>
                ) : (
                    <div className="placeholder">请从左侧选择一封邮件查看详情</div>
                )}
            </div>
        </div>
    );
};

export default EmailsPage;