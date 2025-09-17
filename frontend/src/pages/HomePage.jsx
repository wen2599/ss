import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useAuth } from '../context/AuthContext';

const HomePage = () => {
    const { user } = useAuth();

    // State for the upload form
    const [selectedFile, setSelectedFile] = useState(null);
    const [issueNumber, setIssueNumber] = useState('');

    // State for API responses and data display
    const [storedBets, setStoredBets] = useState([]);
    const [selectedBet, setSelectedBet] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [uploadSuccess, setUploadSuccess] = useState('');

    const UPLOAD_API_URL = "/api/api.php";
    const GET_BETS_API_URL = "/api/get_bets.php";

    const fetchStoredBets = async () => {
        try {
            const response = await axios.get(GET_BETS_API_URL, { withCredentials: true });
            if (response.data.success) {
                setStoredBets(response.data.data);
            }
        } catch (err) {
            console.error('Failed to fetch stored bets:', err);
            setError('无法获取历史投注记录。');
        }
    };

    useEffect(() => {
        if (user) {
            fetchStoredBets();
        }
    }, [user]);

    const handleFileChange = (event) => {
        setSelectedFile(event.target.files[0]);
    };

    const handleUpload = async () => {
        if (!selectedFile) {
            alert('请选择一个投注文件！');
            return;
        }
        if (!issueNumber) {
            alert('请输入期号！');
            return;
        }
        setLoading(true);
        setError('');
        setUploadSuccess('');
        setSelectedBet(null);

        const formData = new FormData();
        formData.append('bet_file', selectedFile);
        formData.append('issue_number', issueNumber);

        try {
            const response = await axios.post(UPLOAD_API_URL, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
                withCredentials: true,
            });
            setUploadSuccess(response.data.message);
            // After successful upload, refresh the list of bets
            fetchStoredBets();
        } catch (err) {
            setError('上传失败: ' + (err.response?.data?.message || err.message));
        } finally {
            setLoading(false);
        }
    };

    const renderSettlementDetails = (bet) => {
        if (bet.status !== 'settled' || !bet.settlement_data) {
            return <p>状态: 未结算</p>;
        }

        const { total_payout, details } = bet.settlement_data;

        return (
            <div>
                <h4>结算结果 (Settlement)</h4>
                <p><strong>状态: 已结算</strong></p>
                <p><strong>总赔付: {total_payout.toFixed(2)}</strong></p>
                {details && details.length > 0 && (
                    <table>
                        <thead>
                            <tr>
                                <th>玩法</th>
                                <th>内容</th>
                                <th>金额</th>
                                <th>赔付</th>
                            </tr>
                        </thead>
                        <tbody>
                            {details.map((win, index) => (
                                <tr key={index}>
                                    <td>{win.bet.display_name}</td>
                                    <td>{win.bet.type === 'special' ? win.bet.number : win.bet.name}</td>
                                    <td>{win.bet.amount}</td>
                                    <td>{win.payout.toFixed(2)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        );
    };

    return (
        <>
            <div className="card">
                <h2>提交新投注</h2>
                <div className="form-group">
                    <label htmlFor="issueNumber">期号 (Issue Number)</label>
                    <input
                        type="text"
                        id="issueNumber"
                        value={issueNumber}
                        onChange={(e) => setIssueNumber(e.target.value)}
                        placeholder="例如: 2025101"
                    />
                </div>
                <div className="form-group">
                    <label htmlFor="betFile">投注文件 (Bet File)</label>
                    <input type="file" id="betFile" onChange={handleFileChange} accept=".txt" />
                </div>
                <button onClick={handleUpload} disabled={loading}>
                    {loading ? '上传中...' : '上传投注'}
                </button>
                {uploadSuccess && <p style={{ color: 'green' }}>{uploadSuccess}</p>}
            </div>

            <div className="card">
                <h2>历史投注记录</h2>
                {storedBets.length > 0 ? (
                    <ul className="stored-logs-list">
                        {storedBets.map((bet) => (
                            <li key={bet.id}>
                                <span>期号: {bet.issue_number} - 状态: {bet.status}</span>
                                <span>{new Date(bet.created_at).toLocaleString()}</span>
                                <button onClick={() => setSelectedBet(bet)}>查看详情</button>
                            </li>
                        ))}
                    </ul>
                ) : (<p>没有历史投注记录。</p>)}
            </div>

            {error && <p className="error">{error}</p>}

            {selectedBet && (
                <div className="card">
                    <h2>投注详情: #{selectedBet.id}</h2>
                    <div className="form-group">
                        <label>原始投注内容</label>
                        <textarea readOnly value={selectedBet.original_content} rows="10" style={{width: '100%'}}></textarea>
                    </div>
                    {renderSettlementDetails(selectedBet)}
                </div>
            )}
        </>
    );
};

export default HomePage;
