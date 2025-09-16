import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useAuth } from '../context/AuthContext';

const HomePage = () => {
    const [selectedFile, setSelectedFile] = useState(null);
    const [backendResponse, setBackendResponse] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [storedLogs, setStoredLogs] = useState([]);
    const { user, logout } = useAuth();

    const UPLOAD_API_URL = "/api/api.php";
    const GET_LOGS_API_URL = "/api/get_logs.php";

    const fetchStoredLogs = async () => {
        try {
            const response = await axios.get(GET_LOGS_API_URL, { withCredentials: true });
            if (response.data.success) {
                setStoredLogs(response.data.data);
            }
        } catch (err) {
            console.error('Failed to fetch stored logs:', err);
            setError('Failed to fetch stored logs. You may need to log in again.');
        }
    };

    useEffect(() => {
        if (user) {
            fetchStoredLogs();
        }
    }, [user]);

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
                headers: { 'Content-Type': 'multipart/form-data' },
                withCredentials: true,
            });
            setBackendResponse(response.data);
            fetchStoredLogs();
        } catch (err) {
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
        if (!data || data.length === 0) return <p>没有可显示的数据。</p>;
        const headers = Object.keys(data[0]);
        return (
            <table>
                <thead>
                    <tr>{headers.map((h) => <th key={h}>{h}</th>)}</tr>
                </thead>
                <tbody>
                    {data.map((row, i) => (
                        <tr key={i}>{headers.map((h) => <td key={h}>{row[h]}</td>)}</tr>
                    ))}
                </tbody>
            </table>
        );
    };

    return (
        <>
            <header>
                <h1>聊天记录表单生成器</h1>
                <div>
                    <span>Welcome, {user.email}</span>
                    <button onClick={logout}>Logout</button>
                </div>
            </header>
            <div className="card">
                <h2>上传新记录</h2>
                <p>选择你的聊天记录文件（例如，WhatsApp导出的TXT文件），然后点击上传进行解析。</p>
                <input type="file" onChange={handleFileChange} accept=".txt,.json" />
                <button onClick={handleUpload} disabled={loading}>{loading ? '处理中...' : '上传并解析'}</button>
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
                ) : (<p>数据库中没有已存记录。</p>)}
            </div>
            {error && <p style={{ color: 'red' }}>错误: {error}</p>}
            {backendResponse && (
                <div className="card">
                    <h2>解析结果:</h2>
                    {backendResponse.parsedData && Array.isArray(backendResponse.parsedData) ? renderTable(backendResponse.parsedData) : <pre>{JSON.stringify(backendResponse, null, 2)}</pre>}
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
};

export default HomePage;
