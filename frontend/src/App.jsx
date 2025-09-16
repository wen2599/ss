import { useState, useEffect } from 'react';
import axios from 'axios';

function App() {
  const [selectedFile, setSelectedFile] = useState(null);
  const [backendResponse, setBackendResponse] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [storedLogs, setStoredLogs] = useState([]);

  const UPLOAD_API_URL = "/api/api.php";
  const GET_LOGS_API_URL = "/api/get_logs.php";

  const fetchStoredLogs = async () => {
    try {
      const response = await axios.get(GET_LOGS_API_URL);
      if (response.data.success) {
        setStoredLogs(response.data.data);
      }
    } catch (err) {
      console.error('Failed to fetch stored logs:', err);
      // We can show an error to the user if we want
    }
  };

  useEffect(() => {
    fetchStoredLogs();
  }, []);

  const handleFileChange = (event) => {
    setSelectedFile(event.target.files[0]);
    setBackendResponse(null);
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
      const response = await axios.post(UPLOAD_API_URL, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      setBackendResponse(response.data);
      // After a successful upload, refetch the logs
      fetchStoredLogs();
    } catch (err) {
      console.error('上传或解析失败:', err);
      setError('上传或解析失败。' + (err.response?.data?.message || err.message));
    } finally {
      setLoading(false);
    }
  };

  const handleSelectLog = (log) => {
    setBackendResponse({
        success: true,
        message: '从数据库加载记录成功。',
        fileName: log.filename,
        parsedData: log.parsed_data,
        rawContent: `Log recorded at: ${log.created_at}`
    });
    setError(null);
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
        <h2>上传新记录</h2>
        <p>
          选择你的聊天记录文件（例如，WhatsApp导出的TXT文件），然后点击上传进行解析。
        </p>
        <input type="file" onChange={handleFileChange} accept=".txt,.json" />
        <button onClick={handleUpload} disabled={loading}>
          {loading ? '处理中...' : '上传并解析'}
        </button>
      </div>

      <div className="card">
        <h2>已存记录</h2>
        {storedLogs.length > 0 ? (
          <ul>
            {storedLogs.map((log) => (
              <li key={log.id}>
                <span>{log.filename} - {new Date(log.created_at).toLocaleString()}</span>
                <button onClick={() => handleSelectLog(log)}>查看</button>
              </li>
            ))}
          </ul>
        ) : (
          <p>数据库中没有已存记录。</p>
        )}
      </div>

      {error && <p style={{ color: 'red' }}>错误: {error}</p>}

      {backendResponse && (
        <div className="card">
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
    </>
  );
}

export default App;
