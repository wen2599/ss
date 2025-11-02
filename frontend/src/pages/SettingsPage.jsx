// src/pages/SettingsPage.jsx
import React, { useState, useEffect, useCallback } from 'react';
import api from '../services/api';
import './SettingsPage.css'; // 添加新的样式文件

const SettingsPage = () => {
  const [currentOdds, setCurrentOdds] = useState(null);
  const [oddsText, setOddsText] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [isUpdating, setIsUpdating] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const fetchCurrentOdds = useCallback(async () => {
    try {
      setIsLoading(true);
      setError('');
      const response = await api.get('/proxy.php?action=get_user_odds');
      if (response.data.status === 'success') {
        setCurrentOdds(response.data.data);
      } else {
        setError(response.data.message || '获取当前赔率失败');
      }
    } catch (err) {
      setError(err.response?.data?.message || '网络错误');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchCurrentOdds();
  }, [fetchCurrentOdds]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!oddsText.trim()) {
      setError('请输入赔率文本');
      return;
    }
    try {
      setIsUpdating(true);
      setError('');
      setSuccess('');
      const response = await api.post('/proxy.php?action=set_user_odds_by_text', {
        odds_text: oddsText,
      });

      if (response.data.status === 'success') {
        setSuccess('赔率设置成功！当前赔率已更新。');
        setOddsText(''); // 清空输入框
        // 重新获取并显示最新的赔率
        await fetchCurrentOdds();
      } else {
        setError(response.data.message || '设置失败');
      }
    } catch (err) {
      setError(err.response?.data?.message || '请求失败，请稍后再试');
    } finally {
      setIsUpdating(false);
    }
  };
  
  const renderOdds = (oddsData) => {
    if (!oddsData || Object.keys(oddsData).length === 0) {
      return <p>您当前没有设置任何赔率。</p>;
    }
    return Object.entries(oddsData).map(([category, plays]) => (
      <div key={category} className="odds-category">
        <h3>{category}</h3>
        <ul>
          {Object.entries(plays).map(([playType, value]) => (
            <li key={playType}>
              <span>{playType}:</span>
              <strong>{value}</strong>
            </li>
          ))}
        </ul>
      </div>
    ));
  };

  return (
    <div className="settings-page">
      <h1>结算设置</h1>
      <div className="settings-layout">
        <div className="settings-form-panel">
          <h2>通过文本自动设置赔率</h2>
          <p>请在下方文本框中输入或粘贴您的赔率表，系统将通过AI自动为您解析并设置。</p>
          <form onSubmit={handleSubmit}>
            <textarea
              value={oddsText}
              onChange={(e) => setOddsText(e.target.value)}
              placeholder="例如：特码 47, 红波/蓝波 2.8, 六肖 0.9..."
              rows="10"
              disabled={isUpdating}
            />
            {error && <p className="error-message">{error}</p>}
            {success && <p className="success-message">{success}</p>}
            <button type="submit" disabled={isUpdating}>
              {isUpdating ? '正在提交给AI处理...' : 'AI智能设置'}
            </button>
          </form>
        </div>
        <div className="current-odds-panel">
          <h2>我当前的赔率</h2>
          {isLoading ? <p>正在加载...</p> : renderOdds(currentOdds)}
        </div>
      </div>
    </div>
  );
};

export default SettingsPage;