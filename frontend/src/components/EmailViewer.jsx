import React, { useState, useEffect } from 'react';
import axios from 'axios';

const EMAILS_API_URL = '/api_router.php?endpoint=emails';
const EMAIL_DETAIL_API_URL = '/api_router.php?endpoint=email_details';

function EmailViewer() {
  const [emails, setEmails] = useState([]);
  const [selectedEmail, setSelectedEmail] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchEmails = async () => {
      const token = localStorage.getItem('authToken');
      if (!token) {
        setError('Please log in to view emails.');
        setLoading(false);
        return;
      }

      try {
        const response = await axios.get(EMAILS_API_URL, {
          headers: {
            'Authorization': `Bearer ${token}`
          }
        });

        if (response.data && response.data.success && Array.isArray(response.data.data)) {
            setEmails(response.data.data);
        } else {
            setError(response.data.message || 'Failed to fetch emails.');
            setEmails([]);
        }

      } catch (err) {
        if (err.response && err.response.status === 401) {
          setError('Your session has expired. Please log in again.');
        } else {
          setError('Could not load email list. Please try again later.');
        }
        console.error("Error fetching emails:", err);
      } finally {
        setLoading(false);
      }
    };

    fetchEmails();
  }, []);

  const handleEmailSelect = async (id) => {
    const token = localStorage.getItem('authToken');
    if (!token) {
        setSelectedEmail({ error: 'Please log in to view email content.' });
        return;
    }

    setSelectedEmail({ loading: true });
    try {
      const response = await axios.get(`${EMAIL_DETAIL_API_URL}&id=${id}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });

      if (response.data.success) {
        setSelectedEmail(response.data.data);
      } else {
        setSelectedEmail({ error: response.data.message || 'Could not load email content.' });
      }

    } catch (err) {
      console.error("Error fetching email details:", err);
      if (err.response && err.response.status === 401) {
        setSelectedEmail({ error: 'Your session has expired. Please log in again.' });
      } else {
        setSelectedEmail({ error: 'Could not load email content.' });
      }
    }
  };

  if (loading) return <p>Loading email list...</p>;

  return (
    <div className="email-viewer">
      <div className="email-list">
        <h2>收件箱</h2>
        {error ? (
          <p style={{ color: 'red', padding: '1rem' }}>{error}</p>
        ) : emails.length > 0 ? (
          <ul>
            {emails.map((email) => (
              <li key={email.id} onClick={() => handleEmailSelect(email.id)}>
                <div className="from">{email.from_address}</div>
                <div className="subject">{email.subject || '(No Subject)'}</div>
                <div className="date">{new Date(email.received_at).toLocaleString()}</div>
              </li>
            ))}
          </ul>
        ) : (
          <p style={{padding: '1rem'}}>Your inbox is empty.</p>
        )}
      </div>
      <div className="email-detail">
        {!selectedEmail ? (
          <p>Please select an email to view.</p>
        ) : selectedEmail.loading ? (
          <p>Loading email content...</p>
        ) : selectedEmail.error ? (
          <p style={{ color: 'red' }}>{selectedEmail.error}</p>
        ) : (
          <div>
            <h3>{selectedEmail.subject || '(No Subject)'}</h3>
            <p><strong>From:</strong> {selectedEmail.from_address}</p>
            <p><strong>Received:</strong> {new Date(selectedEmail.received_at).toLocaleString()}</p>
            <hr />
            {selectedEmail.body_html ? (
              <div dangerouslySetInnerHTML={{ __html: selectedEmail.body_html }} />
            ) : (
              <pre>{selectedEmail.body_text}</pre>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

export default EmailViewer;
