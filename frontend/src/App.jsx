// src/App.jsx
import { useState, useEffect } from 'react';
import './index.css'; // 我们会创建这个文件

function App() {
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchResults = async () => {
      try {
        setLoading(true);
        // 注意：我们请求的是相对路径 /api/get_results
        // 这个请求会被 public/_worker.js 拦截并代理
        const response = await fetch('/api/get_results');
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        if (data.success) {
          setResults(data.data);
        } else {
          throw new Error(data.message || 'Failed to fetch data');
        }
      } catch (e) {
        setError(e.message);
        console.error("Fetch error:", e);
      } finally {
        setLoading(false);
      }
    };

    fetchResults();
  }, []); // 空依赖数组意味着这个 effect 只在组件挂载时运行一次

  return (
    <div className="container">
      <header>
        <h1>最新开奖结果</h1>
      </header>
      <main>
        {loading && <p className="loading">正在加载中...</p>}
        {error && <p className="error">加载失败: {error}</p>}
        {!loading && !error && (
          <div className="results-table">
            <div className="table-header">
              <div>开奖号码</div>
              <div>开奖时间</div>
            </div>
            <div className="table-body">
              {results.length > 0 ? (
                results.map((item) => (
                  <div className="table-row" key={item.id || item.created_at}>
                    <div className="number">{item.number}</div>
                    <div className="time">{new Date(item.created_at).toLocaleString('zh-CN')}</div>
                  </div>
                ))
              ) : (
                <p>暂无数据</p>
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