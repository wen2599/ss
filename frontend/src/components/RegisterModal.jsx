import React, { useState } from 'react';
import axios from 'axios';
import './Modal.css'; // Shared styles for modals

const RegisterModal = ({ onClose, onRegisterSuccess }) => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (password !== confirmPassword) {
            setError('两次输入的密码不匹配。');
            return;
        }
        setLoading(true);
        setError('');

        const formData = new FormData();
        formData.append('email', email);
        formData.append('password', password);

        try {
            const response = await axios.post('/api/register.php', formData);

            if (response.data.success) {
                // Automatically log the user in after successful registration
                onRegisterSuccess(response.data.user);
                onClose();
            } else {
                setError(response.data.message || '注册失败。');
            }
        } catch (err) {
            setError(err.response?.data?.message || '注册时发生错误。');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="modal-backdrop" onClick={onClose}>
            <div className="modal-content" onClick={(e) => e.stopPropagation()}>
                <h2>创建账户</h2>
                <form onSubmit={handleSubmit}>
                    {error && <p className="error">{error}</p>}
                    <div className="form-group">
                        <label htmlFor="register-email">邮箱</label>
                        <input
                            type="email"
                            id="register-email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            required
                        />
                    </div>
                    <div className="form-group">
                        <label htmlFor="register-password">密码</label>
                        <input
                            type="password"
                            id="register-password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            required
                        />
                    </div>
                    <div className="form-group">
                        <label htmlFor="confirm-password">确认密码</label>
                        <input
                            type="password"
                            id="confirm-password"
                            value={confirmPassword}
                            onChange={(e) => setConfirmPassword(e.target.value)}
                            required
                        />
                    </div>
                    <button type="submit" disabled={loading}>
                        {loading ? '注册中...' : '注册'}
                    </button>
                </form>
                <button className="modal-close-btn" onClick={onClose}>×</button>
            </div>
        </div>
    );
};

export default RegisterModal;
