import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import './BillDetailsPage.css'; // We will create this file

const BillDetailsPage = () => {
    const [email, setEmail] = useState(null);
    const [loading, setLoading] = useState(true);
    const { id } = useParams();

    useEffect(() => {
        fetch(`/api/get_emails?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.emails.length > 0) {
                    setEmail(data.emails[0]);
                }
                setLoading(false);
            })
            .catch(error => {
                console.error('Error fetching email details:', error);
                setLoading(false);
            });
    }, [id]);

    if (loading) {
        return <div className="loading">Loading...</div>;
    }

    if (!email) {
        return <div className="not-found">Email not found.</div>;
    }

    return (
        <div className="bill-details-container">
            <h1 className="bill-subject">{email.subject}</h1>
            <div className="bill-meta">
                <span>From: {email.from}</span>
                <span>To: {email.to}</span>
                <span>Date: {new Date(email.created_at).toLocaleString()}</span>
            </div>
            <div className="bill-body">
                {/* Render the HTML content of the email */}
                <div dangerouslySetInnerHTML={{ __html: email.html_content }} />
            </div>
        </div>
    );
};

export default BillDetailsPage;
