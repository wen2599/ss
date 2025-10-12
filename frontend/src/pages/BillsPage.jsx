import React, { useState, useEffect } from 'react';
import { api } from '../api.js';
import './BillsPage.css';

const BillsPage = () => {
    const [emails, setEmails] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchEmails = async () => {
            try {
                setLoading(true);
                const response = await api.getEmails();
                setEmails(response.data);
                setError(null);
            } catch (err) {
                setError('Failed to fetch emails. Please make sure you are logged in.');
                console.error(err);
            } finally {
                setLoading(false);
            }
        };

        fetchEmails();
    }, []);

    if (loading) {
        return <div className="loading">Loading emails...</div>;
    }

    if (error) {
        return <div className="error-message">{error}</div>;
    }

    return (
        <div className="bills-page">
            <h1>Your Emails</h1>
            {emails.length > 0 ? (
                <ul className="email-list">
                    {emails.map((email) => (
                        <li key={email.id} className="email-item">
                            <div className="email-sender">{email.sender}</div>
                            <div className="email-subject">{email.subject}</div>
                            <div className="email-date">{new Date(email.received_at).toLocaleDateString()}</div>
                        </li>
                    ))}
                </ul>
            ) : (
                <p>You have no emails.</p>
            )}
        </div>
    );
};

export default BillsPage;
