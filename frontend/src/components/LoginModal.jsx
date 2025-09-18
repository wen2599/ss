import React, { useState } from 'react';
import axios from 'axios';
import './Modal.css'; // Shared styles for modals

const LoginModal = ({ onClose, onLoginSuccess }) => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError('');

        const payload = {
            email: email,
            password: password,
        };

        try {
            const apiUrl = '/api/login.php';
            // Axios will automatically stringify the payload and set Content-Type: application/json
            const response = await axios.post(apiUrl, payload, {
                withCredentials: true, // This is crucial for sending session cookies
            });

            if (response.data.success) {
                onLoginSuccess(response.data.user);
                onClose(); // Close the modal
            } else {
                setError(response.data.message || '登录失败，请检查您的凭证。');
            }
        } catch (err) {
            setError(err.response?.data?.message || '登录时发生错误。');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="modal-backdrop" onClick={onClose}>
            <div className="modal-content" onClick={(e) => e.stopPropagation()}>
                <h2>用户登录</h2>
                <form onSubmit={handleSubmit}>
                    {error && <p className="error">{error}</p>}
                    <div className="form-group">
                        <label htmlFor="login-email">邮箱</label>
                        <input
                            type="email"
                            id="login-email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            required
                        />
                    </div>
                    <div className="form-group">
                        <label htmlFor="login-password">密码</label>
                        <input
                            type="password"
                            id="login-password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            required
                        />
                    </div>
                    <button type="submit" disabled={loading}>
                        {loading ? '登录中...' : '登录'}
                    </button>
                </form>
                <button className="modal-close-btn" onClick={onClose}>×</button>
            </div>
        </div>
    );
};

export default LoginModal;
