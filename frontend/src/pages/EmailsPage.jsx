import React, { useState, useEffect, useCallback } from 'react';
import api from '../services/api';
import './EmailsPage.css'; // 添加新的样式文件

const EmailsPage = () => {
    const [emails, setEmails] = useState([]);
    const [selectedEmail, setSelectedEmail] = useState(null);
    const [batches, setBatches] = useState([]);
    const [loadingEmails, setLoadingEmails] = useState(true);
    const [loadingBatches, setLoadingBatches] = useState(false);
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
        setBatches([]); // Clear previous batches
        
        // If email status is 'new', it needs segmentation first.
        if (email.status === 'new') {
            try {
                setLoadingBatches(true);
                const segResponse = await api.post('/proxy.php?action=process_email_segmentation', { email_id: email.id });
                if(segResponse.data.status === 'success') {
                    // Refresh the email list to update status
                    await fetchEmails();
                } else {
                     setError(segResponse.data.message);
                }
            } catch (err) {
                setError('邮件分段失败');
                setLoadingBatches(false);
                return;
            }
        }
        
        // Now fetch the batches
        try {
            setLoadingBatches(true);
            const response = await api.get(`/proxy.php?action=get_email_batches&email_id=${email.id}`);
            if (response.data.status === 'success') {
                setBatches(response.data.data);
            } else {
                setError(response.data.message);
            }
        } catch (err) {
            setError('获取批次列表失败');
        } finally {
            setLoadingBatches(false);
        }
    };
    
    // TODO: Handlers for parsing, editing, deleting batches will go here

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
            <div className="batch-details-panel">
                {selectedEmail ? (
                    <>
                        <h2>批次详情 (来自: {selectedEmail.subject})</h2>
                        {loadingBatches ? <p>正在加载批次...</p> : (
                            <div className="batches-container">
                                {batches.length > 0 ? batches.map(batch => (
                                    <div key={batch.id} className="batch-card">
                                        <div className="batch-header">
                                            <span>#{batch.id} - {batch.timestamp_in_email || '无时间戳'}</span>
                                            <span className={`batch-status status-${batch.status}`}>{batch.status}</span>
                                        </div>
                                        <pre className="batch-content">{batch.batch_content}</pre>
                                        <div className="batch-actions">
                                            {/* Buttons will have onClick handlers later */}
                                            <button disabled={batch.status !== 'new'}>解析</button>
                                            <button>编辑</button>
                                            <button>删除</button>
                                        </div>
                                    </div>
                                )) : <p>此邮件没有可处理的批次。</p>}
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