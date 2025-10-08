import React, { useState, useEffect } from 'react';
import './BillsPage.css';

function BillsPage() {
  const [bills, setBills] = useState([]);
  const [selectedBill, setSelectedBill] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchBills = async () => {
      try {
        setLoading(true);
        const response = await fetch('/get_bills', { credentials: 'include' });
        if (!response.ok) {
          if (response.status === 401) {
            throw new Error('Unauthorized. Please log in.');
          } else {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
        }
        const data = await response.json();
        setBills(data);
        // Automatically select the first bill if available
        if (data.length > 0) {
          setSelectedBill(data[0]);
        }
      } catch (err) {
        setError(err.message);
        console.error("Error fetching bills:", err);
      } finally {
        setLoading(false);
      }
    };

    fetchBills();
  }, []);

  if (loading) {
    return <div className="bills-page">正在加载账单...</div>;
  }

  if (error) {
    return <div className="bills-page error-message">错误: {error}</div>;
  }

  return (
    <div className="bills-page">
      <h1>账单中心</h1>
      <div className="bills-content-container">
        <div className="bills-list">
          {bills.length === 0 ? (
            <p>没有找到任何账单邮件。</p>
          ) : (
            bills.map((bill) => (
              <div
                key={bill.email_id}
                className={`bill-list-item ${selectedBill && selectedBill.email_id === bill.email_id ? 'selected' : ''}`}
                onClick={() => setSelectedBill(bill)}
              >
                <h3>{bill.subject || '(无主题)'}</h3>
                <p className="from-address">发件人: {bill.from_address}</p>
                <p className="received-at">收到时间: {new Date(bill.received_at).toLocaleString()}</p>
                {bill.is_valid ? (
                  <span className="status-tag valid">有效</span>
                ) : (
                  <span className="status-tag invalid">无效</span>
                )}
              </div>
            ))
          )}
        </div>

        <div className="bill-detail">
          {selectedBill ? (
            <>
              <h2>账单详情与AI解析</h2>
              <div className="detail-header">
                <h3>主题: {selectedBill.subject || '(无主题)'}</h3>
                <p>发件人: {selectedBill.from_address}</p>
                <p>收到时间: {new Date(selectedBill.received_at).toLocaleString()}</p>
              </div>

              <div className="email-content-display">
                <div className="email-parsed-display">
                  <h3>AI 解析结果</h3>
                  {selectedBill.is_valid ? (
                    <pre>{JSON.stringify(selectedBill.parsed_data, null, 2)}</pre>
                  ) : (
                    <div className="parsed-error">
                      <p><strong>解析结果:</strong> 无效账单</p>
                    </div>
                  )}
                </div>
              </div>
            </>
          ) : ( bills.length > 0 ? (
            <p>请从左侧选择一封邮件查看详情。</p>
          ) : (
            <p>目前没有可供查看的邮件账单。</p>
          )
          )}
        </div>
      </div>
    </div>
  );
}

export default BillsPage;
