// frontend/src/App.jsx
import { useState } from 'react';
import './App.css';

// 关键改动：API URL 指向相对路径
// 之前: const API_URL = 'https://wenge.cloudns.ch/api/process.php';
const API_URL = '/api/process.php'; // <--- 修改这里！

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

    // ... 其他代码保持不变 ...

    try {
      // fetch 调用现在使用的是相对路径，会被 _worker.js 拦截
      const response = await fetch(API_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ emailText: inputText }),
      });

      // ... 其他代码保持不变 ...
    } catch (err) {
      console.error("API 请求失败:", err);
      setError('请求失败，请检查网络或 Worker 代理配置。');
    } finally {
      setIsLoading(false);
    }
  };

  // ... JSX 结构保持不变 ...
  return (
    <div className="container">
        {/* ... */}
    </div>
  );
}

export default App;
