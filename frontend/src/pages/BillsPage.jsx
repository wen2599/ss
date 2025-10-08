import React, { useState, useEffect } from 'react';
import './BillsPage.css'; // Assuming the CSS will be adapted or is already suitable

function BillsPage() {
  // State for the list of emails and the detailed view of a selected email
  const [emails, setEmails] = useState([]);
  const [selectedEmail, setSelectedEmail] = useState(null);
  const [loadingList, setLoadingList] = useState(true);
  const [loadingDetail, setLoadingDetail] = useState(false);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState('parsed'); // 'parsed' or 'raw'

  // Fetch the list of emails on component mount
  useEffect(() => {
    const fetchEmailList = async () => {
      try {
        setLoadingList(true);
        const response = await fetch('/get_emails', { credentials: 'include' });
        if (!response.ok) {
          if (response.status === 401) throw new Error('Unauthorized. Please log in.');
          throw new Error(`HTTP error! Status: ${response.status}`);
        }
        const data = await response.json();
        setEmails(data);
      } catch (err) {
        setError(err.message);
        console.error("Error fetching email list:", err);
      } finally {
        setLoadingList(false);
      }
    };

    fetchEmailList();
  }, []);

  // Function to fetch the details of a single selected email
  const handleEmailSelect = async (emailId) => {
    if (selectedEmail?.email.id === emailId) return; // Avoid re-fetching the same email

    try {
      setLoadingDetail(true);
      setSelectedEmail(null); // Clear previous selection
      const response = await fetch(`/get_emails?id=${emailId}`, { credentials: 'include' });
      if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
      const data = await response.json();
      setSelectedEmail(data);
      setActiveTab('parsed'); // Default to parsed view on new selection
    } catch (err) {
      setError(err.message);
      console.error("Error fetching email detail:", err);
    } finally {
      setLoadingDetail(false);
    }
  };

  const renderDetailView = () => {
    if (loadingDetail) {
      return <div className="detail-view-container loading">正在加载邮件详情...</div>;
    }

    if (!selectedEmail) {
      return (
        <div className="detail-view-container welcome">
          <h2>请在左侧选择一封邮件查看详情</h2>
        </div>
      );
    }

    const { email, slips } = selectedEmail;

    return (
      <div className="detail-view-container">
        <div className="detail-header">
          <h3>{email.subject || '(无主题)'}</h3>
          <p><strong>发件人:</strong> {email.from_address}</p>
          <p><strong>收到时间:</strong> {new Date(email.received_at).toLocaleString()}</p>
        </div>

        <div className="tabs">
          <button className={`tab-button ${activeTab === 'parsed' ? 'active' : ''}`} onClick={() => setActiveTab('parsed')}>
            解析结果
          </button>
          <button className={`tab-button ${activeTab === 'raw' ? 'active' : ''}`} onClick={() => setActiveTab('raw')}>
            邮件原文
          </button>
        </div>

        <div className="tab-content">
          {activeTab === 'parsed' && (
            <div className="parsed-slips-view">
              {slips && slips.length > 0 ? (
                <table>
                  <thead>
                    <tr>
                      <th>原始文本</th>
                      <th>投注类型</th>
                      <th>内容</th>
                      <th>金额</th>
                      <th>状态</th>
                    </tr>
                  </thead>
                  <tbody>
                    {slips.map((slip, index) => (
                      <tr key={index}>
                        <td>{slip.raw_text}</td>
                        {slip.is_valid ? (
                          <>
                            <td>{slip.parsed_data.type}</td>
                            <td>{slip.parsed_data.content}</td>
                            <td>{slip.parsed_data.amount.toFixed(2)}</td>
                            <td className="slip-valid">有效</td>
                          </>
                        ) : (
                          <td colSpan="4">无法解析</td>
                        )}
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : <p>此邮件没有可解析的投注单。</p>}
            </div>
          )}
          {activeTab === 'raw' && (
            <div className="raw-email-view">
              <iframe srcDoc={email.body_html || '<p>无HTML内容。</p>'} title="Raw Email Content" />
            </div>
          )}
        </div>
      </div>
    );
  };

  if (loadingList) {
    return <div className="bills-page">正在加载邮件列表...</div>;
  }

  if (error) {
    return <div className="bills-page error-message">错误: {error}</div>;
  }

  return (
    <div className="bills-page">
      <h1>账单中心</h1>
      <div className="bills-content-container">
        <div className="bills-list">
          {emails.length === 0 ? (
            <p>没有找到任何账单邮件。</p>
          ) : (
            emails.map((email) => (
              <div
                key={email.id}
                className={`bill-list-item ${selectedEmail?.email.id === email.id ? 'selected' : ''}`}
                onClick={() => handleEmailSelect(email.id)}
              >
                <h3>{email.subject || '(无主题)'}</h3>
                <p className="from-address">发件人: {email.from_address}</p>
                <p className="received-at">收到时间: {new Date(email.received_at).toLocaleString()}</p>
              </div>
            ))
          )}
        </div>
        {renderDetailView()}
      </div>
    </div>
  );
}

export default BillsPage;