import { useState } from 'react';
import './App.css';

const API_URL = '/api/process.php';

function App() {
  const [inputText, setInputText] = useState('');
  const [result, setResult] = useState(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (event) => {
    event.preventDefault();
    setIsLoading(true);
    setError('');
    setResult(null);

    if (!inputText.trim()) {
      setError('请输入邮件内容！');
      setIsLoading(false);
      return;
    }

    try {
      const response = await fetch(API_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ emailText: inputText }),
      });

      if (!response.ok) {
        throw new Error(`HTTP 错误! 状态: ${response.status}`);
      }

      const data = await response.json();

      if (data.success) {
        setResult(data.data);
      } else {
        setError(data.error || '后端处理失败');
      }
    } catch (err) {
      console.error("API 请求失败:", err);
      setError('无法连接到服务器，请检查网络或代理配置。');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <>
      <form onSubmit={handleSubmit}>
        <textarea
          value={inputText}
          onChange={(e) => setInputText(e.target.value)}
          placeholder="在此处粘贴邮件文本..."
          rows="10"
          disabled={isLoading}
        />
        <button type="submit" disabled={isLoading}>
          {isLoading ? '正在处理...' : '处理文本'}
        </button>
      </form>

      {error && <div className="error">{error}</div>}

      {result && (
        <div className="result">
          <h2>处理结果</h2>
          <p><strong>字符数:</strong> {result.charCount}</p>
          <p><strong>单词数:</strong> {result.wordCount}</p>
          <p><strong>关键词:</strong></p>
          <ul>
            {result.keywords && result.keywords.map((keyword, index) => (
              <li key={index}>{keyword}</li>
            ))}
          </ul>
        </div>
      )}
    </>
  );
}

export default App;
