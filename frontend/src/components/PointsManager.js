import React, { useState } from 'react';
import './PointsManager.css';
import { findUser, transferPoints } from '../api';
import { useAppContext } from '../contexts/AppContext';

function PointsManager({ onClose }) {
  const { currentUser, updateUser } = useAppContext();
  const [searchPhone, setSearchPhone] = useState('');
  const [searchResult, setSearchResult] = useState(null);
  const [message, setMessage] = useState('');

  const [recipientId, setRecipientId] = useState('');
  const [amount, setAmount] = useState('');

  const handleSearch = async (e) => {
    e.preventDefault();
    setMessage('');
    setSearchResult(null);
    if (!searchPhone) {
      setMessage('请输入要查询的手机号');
      return;
    }
    const response = await findUser(searchPhone);
    if (response.success) {
      setSearchResult(`查询结果: 手机号 ${searchPhone} 对应的ID是 ${response.user.displayId}`);
    } else {
      setMessage(response.message || '查询失败');
    }
  };

  const handleTransfer = async (e) => {
    e.preventDefault();
    setMessage('');
    const transferAmount = parseInt(amount, 10);
    if (!recipientId || !transferAmount || transferAmount <= 0) {
        setMessage('请输入有效的接收人ID和大于0的积分数量');
        return;
    }
    const response = await transferPoints(recipientId, transferAmount);
    if (response.success) {
        setMessage(response.message);
        setRecipientId('');
        setAmount('');
        // Notify App.js to refresh the user's points
        updateUser();
    } else {
        setMessage(response.message || '赠送失败');
    }
  };

  return (
    <div className="points-modal-overlay" onClick={onClose}>
      <div className="points-modal-content" onClick={(e) => e.stopPropagation()}>
        <button className="close-button" onClick={onClose}>X</button>
        <h2>积分管理</h2>

        <div className="points-balance">
          <h3>我的积分</h3>
          <p>{currentUser?.points}</p>
        </div>

        <div className="user-search">
          <h3>查询玩家ID</h3>
          <form onSubmit={handleSearch}>
            <input
              type="text"
              placeholder="输入对方手机号"
              value={searchPhone}
              onChange={(e) => setSearchPhone(e.target.value)}
            />
            <button type="submit">查询</button>
          </form>
          {searchResult && <p className="search-result">{searchResult}</p>}
        </div>

        <div className="points-transfer">
          <h3>赠送积分</h3>
          <form onSubmit={handleTransfer}>
            <input
              type="text"
              placeholder="对方ID"
              value={recipientId}
              onChange={(e) => setRecipientId(e.target.value)}
            />
            <input
              type="number"
              placeholder="赠送数量"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
            />
            <button type="submit">确认赠送</button>
          </form>
        </div>

        {message && <p className="message">{message}</p>}
      </div>
    </div>
  );
}

export default PointsManager;
