// src/App.jsx
import { useState, useEffect } from 'react';
import './index.css';

function App() {
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchResults = async () => {
      try {
        setLoading(true);
        const response = await fetch('/api/get_results');
        if (!response.ok) {
          throw new Error(`网络请求错误: ${response.status}`);
        }
        const data = await response.json();
        if (data.success) {
          setResults(data.data);
        } else {
          throw new Error(data.message || '获取数据失败');
        }
      } catch (e) {
        setError(e.message);
        console.error("Fetch error:", e);
      } finally {
        setLoading(false);
      }
    };

    fetchResults();
  }, []);

  return (
    <div className="container">
      <header>
        <h1>开奖结果中心</h1>
      </header>
      <main>
        {loading && <p className="status-info">正在加载中...</p>}
        {error && <p className="status-info error">加载失败: {error}</p>}
        {!loading && !error && (
          <div className="results-table">
            <div className="table-header">
              <div className="col type">类型</div>
              <div className="col issue">期号</div>
              <div className="col numbers">开奖号码</div>
              <div className="col time">时间</div>
            </div>
            <div className="table-body">
              {results.length > 0 ? (
                results.map((item) => (
                  <div className="table-row" key={item.id}>
                    <div className="col type">{item.lottery_type}</div>
                    <div className="col issue">{item.issue_number}</div>
                    <div className="col numbers">{item.winning_numbers}</div>
                    <div className="col time">{new Date(item.created_at).toLocaleString('zh-CN', { hour12: false })}</div>
                  </div>
                ))
              ) : (
                <p className="status-info">暂无数据</p>
              )}
            </div>
          </div>
        )}
      </main>
      <footer>
        <p>数据仅供参考</p>
      </footer>
    </div>
  );
}

export default App;
