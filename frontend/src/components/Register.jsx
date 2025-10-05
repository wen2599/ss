import React, { useState } from 'react';
import './Form.css';

const Register = ({ onClose, onRegister }) => {
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setSuccess('');

        try {
            const response = await fetch('/register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || '注册失败');
            }

            setSuccess('注册成功！您现在可以登录了。');
            // Automatically close the modal after a delay and trigger the parent's action
            setTimeout(() => {
                onRegister();
            }, 1500);

        } catch (err) {
            setError(err.message);
        }
    };

    return (
        <div className="modal-backdrop" onClick={onClose}>
            <div className="modal-content" onClick={(e) => e.stopPropagation()}>
                <h2>注册</h2>
                <form onSubmit={handleSubmit}>
                    {error && <p className="error">{error}</p>}
                    {success && <p className="success">{success}</p>}
                    <div className="form-group">
                        <label htmlFor="register-username">用户名</label>
                        <input
                            id="register-username"
                            type="text"
                            value={username}
                            onChange={(e) => setUsername(e.target.value)}
                            required
                        />
                    </div>
                    <div className="form-group">
                        <label htmlFor="register-password">密码</label>
                        <input
                            id="register-password"
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            required
                        />
                    </div>
                    <div className="form-actions">
                        <button type="submit">注册</button>
                        <button type="button" onClick={onClose}>取消</button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default Register;