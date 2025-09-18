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

        const payload = { email, password };

        try {
            const apiBaseUrl = import.meta.env.VITE_API_BASE_URL;
            if (!apiBaseUrl) {
                console.error("FATAL: VITE_API_BASE_URL is not defined. API calls will fail. Please check your .env file in the /frontend directory.");
                setError("Application is not configured correctly. Please contact support.");
                setLoading(false);
                return;
            }
            const apiUrl = `${apiBaseUrl}/api/register.php`;
            // Axios automatically serializes the object to JSON and sets the correct Content-Type header.
            const response = await axios.post(apiUrl, payload);

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
                            autoComplete="new-password"
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
                            autoComplete="new-password"
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
