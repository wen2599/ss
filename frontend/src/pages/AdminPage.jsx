import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useAuth } from '../context/AuthContext';
import { Navigate } from 'react-router-dom';

const AdminPage = () => {
    const { user } = useAuth();
    const [rules, setRules] = useState({
        zodiac_mappings: {},
        color_mappings: {},
        odds: {}
    });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    // This is a placeholder. In a real app, this should be a proper check.
    // The backend API will do the actual authorization check.
    const isSuperAdmin = user && user.id === 1878794912; // Hardcoded from config.php for UI purposes

    useEffect(() => {
        const fetchRules = async () => {
            try {
                const apiUrl = `${import.meta.env.VITE_API_BASE_URL || ''}/api/get_rules.php`;
                const response = await axios.get(apiUrl, { withCredentials: true });
                if (response.data.success) {
                    // Prettify the JSON for display in textareas
                    const prettifiedRules = {
                        zodiac_mappings: JSON.stringify(response.data.data.zodiac_mappings, null, 2),
                        color_mappings: JSON.stringify(response.data.data.color_mappings, null, 2),
                        odds: JSON.stringify(response.data.data.odds, null, 2),
                    };
                    setRules(prettifiedRules);
                } else {
                    setError('Failed to fetch rules.');
                }
            } catch (err) {
                setError('Error fetching rules: ' + err.message);
            } finally {
                setLoading(false);
            }
        };

        if (isSuperAdmin) {
            fetchRules();
        }
    }, [isSuperAdmin]);

    const handleSave = async () => {
        setError('');
        setSuccess('');
        setLoading(true);
        try {
            // We need to parse the JSON from textareas before sending
            const rulesToSave = {
                zodiac_mappings: JSON.parse(rules.zodiac_mappings),
                color_mappings: JSON.parse(rules.color_mappings),
                odds: JSON.parse(rules.odds),
            };

            const apiUrl = `${import.meta.env.VITE_API_BASE_URL || ''}/api/update_rules.php`;
            const response = await axios.post(apiUrl, rulesToSave, {
                withCredentials: true,
            });

            if (response.data.success) {
                setSuccess('Rules saved successfully!');
            } else {
                setError(response.data.message || 'Failed to save rules.');
            }
        } catch (err) {
            if (err instanceof SyntaxError) {
                setError('Invalid JSON format in one of the fields.');
            } else {
                setError('Error saving rules: ' + (err.response?.data?.message || err.message));
            }
        } finally {
            setLoading(false);
        }
    };

    const handleRuleChange = (key, value) => {
        setRules(prev => ({ ...prev, [key]: value }));
    };

    if (!isSuperAdmin) {
        return <Navigate to="/" replace />;
    }

    if (loading && !Object.keys(rules.odds).length) {
        return <div>Loading rules...</div>;
    }

    return (
        <div className="card">
            <h2>后台管理 - 规则设置</h2>
            <p>在这里管理生肖、波色和赔率的规则。请确保使用正确的JSON格式。</p>

            {error && <p className="error">{error}</p>}
            {success && <p style={{ color: 'green' }}>{success}</p>}

            <div className="form-group">
                <label htmlFor="zodiac_mappings">生肖号码对应 (Zodiac Mappings)</label>
                <textarea
                    id="zodiac_mappings"
                    rows="10"
                    value={rules.zodiac_mappings}
                    onChange={(e) => handleRuleChange('zodiac_mappings', e.target.value)}
                    style={{ fontFamily: 'monospace', width: '100%' }}
                />
            </div>

            <div className="form-group">
                <label htmlFor="color_mappings">波色号码对应 (Color Mappings)</label>
                <textarea
                    id="color_mappings"
                    rows="5"
                    value={rules.color_mappings}
                    onChange={(e) => handleRuleChange('color_mappings', e.target.value)}
                    style={{ fontFamily: 'monospace', width: '100%' }}
                />
            </div>

            <div className="form-group">
                <label htmlFor="odds">赔率 (Odds)</label>
                <textarea
                    id="odds"
                    rows="3"
                    value={rules.odds}
                    onChange={(e) => handleRuleChange('odds', e.target.value)}
                    style={{ fontFamily: 'monospace', width: '100%' }}
                />
            </div>

            <button onClick={handleSave} disabled={loading}>
                {loading ? '保存中...' : '保存规则'}
            </button>
        </div>
    );
};

export default AdminPage;
