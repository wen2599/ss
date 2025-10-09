import React, { useState, useEffect } from 'react';
import './HistoryPage.css'; // We will create this CSS file next

const HistoryPage = () => {
  const [history, setHistory] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchHistory = () => {
      setLoading(true);
      setError(null);
      fetch('/api/getHistory')
        .then(response => {
          if (!response.ok) {
            throw new Error('网络响应错误');
          }
          return response.json();
        })
        .then(data => {
          if (data.error) {
            throw new Error(data.error);
          }
          setHistory(data);
          setLoading(false);
        })
        .catch(error => {
          console.error('获取历史记录失败:', error);
          setError(error.message);
          setLoading(false);
        });
    };

    fetchHistory();
  }, []);

  if (loading) {
    return <div className="history-container"><p>加载中...</p></div>;
  }

  if (error) {
    return <div className="history-container error-message"><p>加载失败: {error}</p></div>;
  }

  return (
    <div className="history-container">
      <h1>历史开奖记录</h1>
      {history.length > 0 ? (
        <table className="history-table">
          <thead>
            <tr>
              <th>期号</th>
              <th>开奖号码</th>
              <th>开奖日期</th>
            </tr>
          </thead>
          <tbody>
            {history.map(record => (
              <tr key={record.id}>
                <td>{record.issue_number}</td>
                <td>{record.winning_numbers}</td>
                <td>{record.drawing_date}</td>
              </tr>
            ))}
          </tbody>
        </table>
      ) : (
        <p>暂无历史记录。</p>
      )}
    </div>
  );
};

export default HistoryPage;