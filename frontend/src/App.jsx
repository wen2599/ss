import React, { useState, useEffect } from 'react';

function App() {
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const fetchResults = async (type = '') => {
    try {
      setLoading(true);
      setError(null); // Clear previous errors
      const params = new URLSearchParams({ limit: '20' });
      if (type) params.append('type', type);
      
      // Use a relative path to leverage the API proxy/rewrite
      const response = await fetch(`/api/results?${params}`);
      
      if (!response.ok) {
        // Handle HTTP errors like 404 or 500
        throw new Error(`请求失败，状态码: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.success) {
        setResults(data.data);
      } else {
        setError(data.error || '获取数据时发生未知错误');
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchResults();
  }, []);

  return (
    <div>
      <h1>彩票开奖结果</h1>
      <div>
        <button onClick={() => fetchResults('')}>全部</button>
        <button onClick={() => fetchResults('双色球')}>双色球</button>
        <button onClick={() => fetchResults('大乐透')}>大乐透</button>
      </div>
      
      {loading && <div>加载中...</div>}
      {error && <div>错误: {error}</div>}
      
      <div>
        {results.map(item => (
          <div key={item.id}>
            <h3>{item.lottery_type}</h3>
            <p>号码: {item.lottery_number}</p>
            <p>日期: {item.draw_date}</p>
          </div>
        ))}
      </div>
    </div>
  );
}

export default App;
