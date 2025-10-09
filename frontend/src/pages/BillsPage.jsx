import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import './BillsPage.css';

const BillsPage = () => {
    const [emails, setEmails] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetch('/api/get_emails')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    setEmails(data.emails);
                }
                setLoading(false);
            })
            .catch(error => {
                console.error('Error fetching emails:', error);
                setLoading(false);
            });
    }, []);

    if (loading) {
        return <div className="loading">Loading...</div>;
    }

    return (
        <div className="bills-container">
            <h1 className="bills-title">账单中心</h1>
            <div className="email-list">
                {emails.map(email => (
                    <Link to={`/bill/${email.id}`} key={email.id} className="email-item-link">
                        <div className="email-item">
                            <div className="email-subject">{email.subject}</div>
                            <div className="email-from">From: {email.from}</div>
                            <div className="email-date">
                                {new Date(email.created_at || email.received_at).toLocaleString()}
                            </div>
                        </div>
                    </Link>
                ))}
            </div>
        </div>
    );
};

export default BillsPage;
