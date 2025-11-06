import React, { useState, useEffect } from 'react';
import DOMPurify from 'dompurify';

function EmailPage() {
  const [emails, setEmails] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchEmails = async () => {
      setLoading(true);
      setError(null);

      try {
        const token = localStorage.getItem('authToken');
        if (!token) {
          throw new Error('No authentication token found.');
        }

        const response = await fetch('/api/?action=get_emails', {
          headers: {
            'Authorization': `Bearer ${token}`
          }
        });

        if (!response.ok) {
          throw new Error(`Server responded with status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
          setEmails(data.data);
        } else {
          throw new Error(data.message || 'Failed to fetch emails.');
        }

      } catch (e) {
        console.error("Error fetching emails:", e);
        setError(e.message);
      } finally {
        setLoading(false);
      }
    };

    fetchEmails();
  }, []);

  const renderContent = () => {
    if (loading) {
      return <div className="status-message">Loading emails...</div>;
    }
    if (error) {
      return <div className="status-message error">Error: {error}</div>;
    }
    if (emails.length === 0) {
      return <div className="status-message">You have no emails.</div>;
    }
    return (
      <div className="email-list">
        {emails.map((email, index) => (
          <div key={index} className="email-item card">
            <div className="email-header">
              <strong>From:</strong> {email.from_email}<br/>
              <strong>To:</strong> {email.to_email}<br/>
              <strong>Subject:</strong> {email.subject}
            </div>
            <div className="email-body" dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(email.body) }} />
            <div className="email-footer">
              <em>Received: {new Date(email.received_at).toLocaleString('zh-CN')}</em>
            </div>
          </div>
        ))}
      </div>
    );
  };

  return (
    <div className="container">
      <header className="app-header">
        <h1>My Emails</h1>
      </header>
      <main>
        {renderContent()}
      </main>
    </div>
  );
}

export default EmailPage;
