import React, { useState, useEffect } from 'react';
import './SettingsPage.css';

const SettingsPage = () => {
    const [apiKey, setApiKey] = useState('');
    const [apiKeyStatus, setApiKeyStatus] = useState('LOADING'); // LOADING, SET, NOT_SET
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');

    useEffect(() => {
        const fetchSettings = async () => {
            try {
                const response = await fetch('/api/actions/get_settings.php', {
                    credentials: 'include'
                });
                const data = await response.json();
                if (data.success) {
                    setApiKeyStatus(data.settings.gemini_api_key);
                } else {
                    setError(data.error || 'Failed to fetch settings.');
                    setApiKeyStatus('ERROR');
                }
            } catch (err) {
                setError('An error occurred while fetching settings.');
                setApiKeyStatus('ERROR');
            }
        };

        fetchSettings();
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setMessage('');
        setError('');

        if (!apiKey) {
            setError('API Key cannot be empty.');
            return;
        }

        try {
            const response = await fetch('/api/actions/update_setting.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    setting_name: 'gemini_api_key',
                    setting_value: apiKey,
                }),
            });

            const data = await response.json();

            if (data.success) {
                setMessage('API Key updated successfully!');
                setApiKeyStatus('SET');
                setApiKey(''); // Clear the input field for security
            } else {
                setError(data.error || 'Failed to update API Key.');
            }
        } catch (err) {
            setError('An error occurred while saving the setting.');
        }
    };

    return (
        <div className="settings-page">
            <h2>Application Settings</h2>
            <p>Use this page to configure application-level settings. Initially, only the Gemini API key can be updated.</p>

            <div className="api-key-status">
                <strong>Current Gemini API Key Status: </strong>
                {apiKeyStatus === 'LOADING' && <span className="status-loading">Loading...</span>}
                {apiKeyStatus === 'SET' && <span className="status-set">Key is set</span>}
                {apiKeyStatus === 'NOT_SET' && <span className="status-not-set">Key is not set</span>}
                {apiKeyStatus === 'ERROR' && <span className="status-error">Error loading status</span>}
            </div>

            <form onSubmit={handleSubmit} className="settings-form">
                <div className="form-group">
                    <label htmlFor="gemini-api-key">Update Gemini API Key</label>
                    <input
                        id="gemini-api-key"
                        type="password"
                        value={apiKey}
                        onChange={(e) => setApiKey(e.target.value)}
                        placeholder="Enter new API Key to update"
                        className="form-control"
                    />
                    <small>Your API key is stored securely. The field is empty for security, but the status above indicates if a key is present.</small>
                </div>

                <button type="submit" className="btn btn-primary">Save Settings</button>
            </form>

            {message && <div className="alert alert-success">{message}</div>}
            {error && <div className="alert alert-danger">{error}</div>}
        </div>
    );
};

export default SettingsPage;