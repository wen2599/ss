import React, { useState, useEffect, useCallback } from 'react';
import EmailList from '../components/EmailList';
import EmailDetailView from '../components/EmailDetailView';
import api from '../services/api';

const DashboardPage = () => {
  const [emails, setEmails] = useState([]);
  const [selectedEmailId, setSelectedEmailId] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  
  const fetchEmails = useCallback(async () => {
    try {
      setLoading(true);
      const response = await api.get('/emails/list.php');
      setEmails(response.data);
      setError('');
    } catch (err) {
      setError('无法加载邮件列表。');
      console.error(err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchEmails();
  }, [fetchEmails]);

  const handleSelectEmail = (id) => {
    setSelectedEmailId(id);
  };

  const handleUpdate = () => {
    // 刷新邮件列表和当前详情
    fetchEmails();
    if (selectedEmailId) {
      // 触发详情视图的刷新
      setSelectedEmailId(null); // 强制重新挂载组件
      setTimeout(() => setSelectedEmailId(selectedEmailId), 0);
    }
  };

  return (
    <div className="dashboard">
      <EmailList 
        emails={emails} 
        selectedEmailId={selectedEmailId} 
        onSelectEmail={handleSelectEmail} 
        loading={loading}
        error={error}
      />
      <EmailDetailView 
        emailId={selectedEmailId} 
        onUpdate={handleUpdate} 
      />
    </div>
  );
};

export default DashboardPage;