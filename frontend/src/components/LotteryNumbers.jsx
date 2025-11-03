import { useState, useEffect } from 'react';

function LotteryNumbers() {
  const [numbers, setNumbers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchNumbers = async () => {
      try {
        setLoading(true);
        // _worker.js 会将此请求代理到您的 PHP 后端
        const response = await fetch('/api/get_lottery_numbers.php');

        if (!response.ok) {
          throw new Error(`HTTP 错误! 状态: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
          setNumbers(data.data);
        } else {
          throw new Error(data.message || '获取数据失败');
        }

      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };

    fetchNumbers();
  }, []); // 空依赖数组确保只在组件挂载时运行一次

  return (
    <div>
      <h2>开奖号码列表</h2>
      {loading && <p className="loading">正在加载中...</p>}
      {error && <p className="error">错误: {error}</p>}
      {!loading && !error && (
        <ul className="item-list">
          {numbers.length > 0 ? (
            numbers.map((item) => (
              <li key={item.id}>
                <span>号码: {item.number}</span>
                <span>{new Date(item.received_at).toLocaleString()}</span>
              </li>
            ))
          ) : (
            <p>还没有任何开奖号码。</p>
          )}
        </ul>
      )}
    </div>
  );
}

export default LotteryNumbers;
