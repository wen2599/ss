import React, { useState, useEffect } from 'react';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';

const EmailDetailView = ({ emailId, onUpdate }) => {
    const [email, setEmail] = useState(null);
    const [structuredData, setStructuredData] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const { user } = useAuth();

    useEffect(() => {
        if (!emailId) {
            setEmail(null);
            return;
        }

        const fetchEmailDetail = async () => {
            setLoading(true);
            setError('');
            try {
                const response = await api.get(`/emails/get_details.php?id=${emailId}`);
                setEmail(response.data);
                if (response.data.structured_data) {
                    setStructuredData(response.data.structured_data);
                } else {
                    setStructuredData([]);
                }
            } catch (err) {
                setError('加载邮件详情失败。');
                console.error(err);
            } finally {
                setLoading(false);
            }
        };

        fetchEmailDetail();
    }, [emailId]);

    const handleProcessEmail = async () => {
        setLoading(true);
        setError('');
        try {
            const response = await api.post('/emails/process.php', { id: emailId });
            setEmail(response.data);
            setStructuredData(response.data.structured_data);
            onUpdate(); // 通知父组件刷新列表状态
        } catch (err) {
            setError('AI处理邮件失败。');
        } finally {
            setLoading(false);
        }
    };
    
    const handleSettle = async () => {
        setLoading(true);
        setError('');
        try {
            const response = await api.post('/emails/settle.php', { id: emailId });
            setEmail(response.data);
            onUpdate(); // 通知父组件刷新列表状态
        } catch (err) {
            setError(err.response?.data?.message || '结算失败。');
        } finally {
            setLoading(false);
        }
    };
    
    const handleDataChange = (index, field, value) => {
        const newData = [...structuredData];
        newData[index][field] = value;
        // 如果是numbers字段，确保它是数组
        if (field === 'numbers' && typeof value === 'string') {
          newData[index][field] = value.split(',').map(n => n.trim());
        }
        setStructuredData(newData);
    };

    const handleSaveChanges = async () => {
        setLoading(true);
        setError('');
        try {
            await api.post('/emails/update_structured.php', {
                id: emailId,
                structured_data: structuredData
            });
            alert('修改已保存！');
        } catch (err) {
            setError('保存修改失败。');
        } finally {
            setLoading(false);
        }
    };

    if (!emailId) return <div className="email-detail">请从左侧选择一封邮件查看。</div>;
    if (loading) return <div className="email-detail loading">正在加载...</div>;
    if (error) return <div className="email-detail error">{error}</div>;
    if (!email) return null;

    return (
        <div className="email-detail">
            <h3>邮件详情</h3>
            <p><strong>主题:</strong> {email.subject}</p>
            <p><strong>来自:</strong> {email.from_address}</p>
            <p><strong>接收时间:</strong> {new Date(email.created_at).toLocaleString()}</p>
            <hr />
            
            <h4>邮件原文</h4>
            <pre style={{ whiteSpace: 'pre-wrap', backgroundColor: '#1e1e1e', padding: '10px', borderRadius: '5px' }}>
                {email.raw_content}
            </pre>

            {email.status === 'new' && user.is_authorized ? (
                <button onClick={handleProcessEmail} className="primary">使用 AI 整理</button>
            ) : email.status === 'new' && !user.is_authorized ? (
                <p>您的邮箱未授权，无法使用AI整理功能。</p>
            ) : null}

            {structuredData && structuredData.length > 0 && (
                <div className="structured-form">
                    <h4>AI 整理的投注单</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>类型</th>
                                <th>子类型/号码</th>
                                <th>号码/名称</th>
                                <th>金额</th>
                            </tr>
                        </thead>
                        <tbody>
                            {structuredData.map((item, index) => (
                                <tr key={index}>
                                    <td><input value={item.type} onChange={e => handleDataChange(index, 'type', e.target.value)} /></td>
                                    <td><input value={item.subtype || item.number} onChange={e => handleDataChange(index, item.subtype ? 'subtype' : 'number', e.target.value)} /></td>
                                    <td><input value={Array.isArray(item.numbers) ? item.numbers.join(',') : item.name} onChange={e => handleDataChange(index, item.numbers ? 'numbers' : 'name', e.target.value)} /></td>
                                    <td><input type="number" value={item.amount} onChange={e => handleDataChange(index, 'amount', parseFloat(e.target.value))} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <button onClick={handleSaveChanges} className="secondary">保存修改</button>
                    {email.status !== 'settled' && <button onClick={handleSettle} className="primary">开始结算</button>}
                </div>
            )}
            
            {email.status === 'settled' && email.settlement_result && (
                <div className="settlement-result">
                    <h4>结算结果</h4>
                    <p><strong>本期开奖号码:</strong> {email.settlement_result.lottery.winning_numbers.join(', ')} + {email.settlement_result.lottery.special_number}</p>
                    <p><strong>总投注额:</strong> {email.total_bet_amount}</p>
                    <p><strong>总中奖金额:</strong> {email.total_win_amount}</p>
                    <p><strong>盈亏:</strong> <span style={{color: email.settlement_result.profit >= 0 ? 'green' : 'red'}}>{email.settlement_result.profit}</span></p>
                    <h5>中奖详情:</h5>
                    <ul>
                        {email.settlement_result.details.filter(d => d.is_win).map((detail, i) => (
                           <li key={i}>{detail.description}: +{detail.win_amount}</li> 
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
};

export default EmailDetailView;