import React, { useState, useEffect } from 'react';
import * as api from '../services/api';
import Loading from './Loading';

const EmailViewer = () => {
    const [emails, setEmails] = useState([]);
    const [selectedEmail, setSelectedEmail] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [bodyLoading, setBodyLoading] = useState(false);

    useEffect(() => {
        const fetchEmails = async () => {
            setLoading(true);
            setError(null);
            try {
                const response = await api.getEmails();
                if (response.success) {
                    setEmails(response.data);
                } else {
                    setError(response.error || 'Failed to fetch emails.');
                }
            } catch (err) {
                setError(err.message || 'An error occurred.');
            } finally {
                setLoading(false);
            }
        };
        fetchEmails();
    }, []);

    const handleSelectEmail = async (emailId) => {
        setBodyLoading(true);
        setSelectedEmail(null); // Clear previous email
        try {
            const response = await api.getEmailBody(emailId);
            if (response.success) {
                setSelectedEmail(response.data);
            }
        } catch (err) {
            // Handle error fetching body
        } finally {
            setBodyLoading(false);
        }
    };

    if (loading) return <Loading />;
    if (error) return <div className="error-message">{error}</div>;

    return (
        <div className="email-viewer">
            <div className="email-list">
                <h2>Inbox</h2>
                {emails.map(email => (
                    <div key={email.id} className="email-item" onClick={() => handleSelectEmail(email.id)}>
                        <p><strong>From:</strong> {email.sender}</p>
                        <p><strong>Subject:</strong> {email.subject}</p>
                        <small>{new Date(email.received_at).toLocaleString()}</small>
                    </div>
                ))}
            </div>
            <div className="email-body">
                {bodyLoading && <p>Loading email...</p>}
                {selectedEmail ? (
                    <div>
                        <h3>{selectedEmail.subject}</h3>
                        <p><strong>From:</strong> {selectedEmail.sender}</p>
                        <hr />
                        <div dangerouslySetInnerHTML={{ __html: selectedEmail.body }} />
                    </div>
                ) : !bodyLoading && (
                    <p>Select an email to read.</p>
                )}
            </div>
        </div>
    );
};

export default EmailViewer;
