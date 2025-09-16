import { useState } from 'react';
import axios from 'axios';

function App() {
  const [selectedFile, setSelectedFile] = useState(null);
  const [backendResponse, setBackendResponse] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const BACKEND_API_URL = "https://wenge.cloudns.ch/backend/api/api.php";

  const handleFileChange = (event) => {
    setSelectedFile(event.target.files[0]);
    setBackendResponse(null); // 清除之前的响应
    setError(null);
  };

  const handleUpload = async () => {
    if (!selectedFile) {
      alert('请先选择一个聊天记录文件！');
      return;
    }

    setLoading(true);
    setError(null);
    setBackendResponse(null);

    const formData = new FormData();
    formData.append('chat_file', selectedFile);

    try {
      const response = await axios.post(BACKEND_API_URL, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      setBackendResponse(response.data);
    } catch (err) {
      console.error('上传或解析失败:', err);
      setError('上传或解析失败。' + (err.response?.data?.message || err.message));
    } finally {
      setLoading(false);
    }
  };

  const renderTable = (data) => {
    if (!data || data.length === 0) {
      return <p>没有可显示的数据。</p>;
    }

    const headers = Object.keys(data[0]);

    return (
      <table>
        <thead>
          <tr>
            {headers.map((header) => (
              <th key={header}>{header}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {data.map((row, index) => (
            <tr key={index}>
              {headers.map((header) => (
                <td key={header}>{row[header]}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    );
  };

  return (
    <>
      <h1>聊天记录表单生成器</h1>
      <div className="card">
        <p>
          选择你的聊天记录文件（例如，WhatsApp导出的TXT文件），然后点击上传进行解析。
        </p>
        <input type="file" onChange={handleFileChange} accept=".txt,.json" />
        <button onClick={handleUpload} disabled={loading}>
          {loading ? '处理中...' : '上传并解析'}
        </button>

        {error && <p style={{ color: 'red' }}>错误: {error}</p>}

        {backendResponse && (
          <div>
            <h2>解析结果:</h2>
            {backendResponse.parsedData && Array.isArray(backendResponse.parsedData) ? (
              renderTable(backendResponse.parsedData)
            ) : (
              <pre>{JSON.stringify(backendResponse, null, 2)}</pre>
            )}
            {backendResponse.rawContent && (
              <>
                <h3>原始聊天记录 (部分):</h3>
                <textarea readOnly value={backendResponse.rawContent.substring(0, 1000) + (backendResponse.rawContent.length > 1000 ? '...' : '')}></textarea>
              </>
            )}
          </div>
        )}
      </div>
    </>
  );
}

export default App;
