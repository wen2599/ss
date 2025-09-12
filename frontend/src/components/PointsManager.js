import React, { useState } from 'react';
import { findUser, transferPoints } from '../api';
import { useAuth } from '../contexts/AuthContext';
import './PointsManager.css';

const PointsManager = ({ onClose }) => {
    const { currentUser, updateUser } = useAuth();
    const [recipientId, setRecipientId] = useState('');
    const [amount, setAmount] = useState('');
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');

    const handleTransfer = async (e) => {
        e.preventDefault();
        setError('');
        setMessage('');
        try {
            const response = await transferPoints(recipientId, amount);
            if (response.success) {
                setMessage(response.message);
                updateUser();
            } else {
                setError(response.message);
            }
        } catch (err) {
            setError('积分赠送失败，请检查网络连接。');
        }
    };

    return (
        <div className="modal-backdrop">
            <div className="modal-content">
                <h2>积分管理</h2>
                <button onClick={onClose} className="close-button">&times;</button>
                <p>您当前的积分为: {currentUser.points}</p>
                <form onSubmit={handleTransfer}>
                    <div className="form-group">
                        <label htmlFor="recipientId">接收人ID</label>
                        <input
                            type="text"
                            id="recipientId"
                            value={recipientId}
                            onChange={(e) => setRecipientId(e.target.value)}
                            required
                        />
                    </div>
                    <div className="form-group">
                        <label htmlFor="amount">赠送数量</label>
                        <input
                            type="number"
                            id="amount"
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                            required
                            min="1"
                        />
                    </div>
                    {message && <p className="success-message">{message}</p>}
                    {error && <p className="error-message">{error}</p>}
                    <button type="submit" className="submit-button">赠送</button>
                </form>
            </div>
        </div>
    );
};

export default PointsManager;
