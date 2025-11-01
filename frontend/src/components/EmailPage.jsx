import React, { useState, useEffect } from 'react';

const EmailPage = ({ authToken, onLogout }) => {
    const [emails, setEmails] = useState([]);
    const [error, setError] = useState('');

    useEffect(() => {
        const fetchEmails = async () => {
            setError('');
            try {
                const response = await fetch('/api_router.php?endpoint=email&action=get_emails', {
                    headers: { 'Authorization': `Bearer ${authToken}` },
                });

                const data = await response.json();

                if (data.success) {
                    setEmails(data.emails);
                } else {
                    setError(data.message || 'Failed to fetch emails');
                }
            } catch (err) {
                setError('An error occurred');
            }
        };

        if (authToken) {
            fetchEmails();
        }
    }, [authToken]);

    return (
        <div>
            <h2>Your Emails</h2>
            <button onClick={onLogout}>Logout</button>
            {error && <p style={{ color: 'red' }}>{error}</p>}
            <ul>
                {emails.map((email) => (
                    <li key={email.id}>
                        <h3>{email.subject}</h3>
                        <p>From: {email.from_address}</p>
                        <p>To: {email.to_address}</p>
                        <p>{email.body_text}</p>
                    </li>
                ))}
            </ul>
        </div>
    );
};

export default EmailPage;
