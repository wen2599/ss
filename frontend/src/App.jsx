import React, { useState, useEffect } from 'react';

function App() {
  const [userEmail, setUserEmail] = useState('test@example.com'); // Default for demo
  const [emails, setEmails] = useState([]);
  const [selectedEmail, setSelectedEmail] = useState(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');

  const fetchEmails = async () => {
    if (!userEmail) {
      setError('Please enter a user email address.');
      return;
    }
    setIsLoading(true);
    setError('');
    setEmails([]);
    setSelectedEmail(null);

    try {
      const response = await fetch(`/api/get_emails.php?user_email=${encodeURIComponent(userEmail)}`);
      if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);

      const data = await response.json();
      if (!data.success) throw new Error(data.error || 'Failed to fetch emails.');

      setEmails(data.emails);
    } catch (err) {
      setError(err.message);
    } finally {
      setIsLoading(false);
    }
  };

  // Fetch emails on initial component load for the default user
  useEffect(() => {
    fetchEmails();
  }, []);

  const createAttachmentUrl = (emailId, filename) => {
      return `/api/get_attachment.php?user_email=${encodeURIComponent(userEmail)}&email_id=${encodeURIComponent(emailId)}&filename=${encodeURIComponent(filename)}`;
  };

  return (
    <div className="app-container">
      <aside className="sidebar">
        <header>
          <h1>Email Inbox</h1>
          <div className="input-group">
            <input
              type="email"
              value={userEmail}
              onChange={(e) => setUserEmail(e.target.value)}
              placeholder="user@example.com"
            />
            <button onClick={fetchEmails} disabled={isLoading}>
              {isLoading ? '...' : 'Fetch'}
            </button>
          </div>
        </header>
        <div className="email-list">
          {isLoading && <p>Loading emails...</p>}
          {error && <p className="error-message">{error}</p>}
          {emails.length > 0 ? (
            emails.map((email) => (
              <div
                key={email.id}
                className={`email-summary ${selectedEmail && selectedEmail.id === email.id ? 'selected' : ''}`}
                onClick={() => setSelectedEmail(email)}
              >
                <div className="from">{email.headers?.from || 'No Sender'}</div>
                <div className="subject">{email.headers?.subject || 'No Subject'}</div>
              </div>
            ))
          ) : (
            !isLoading && <p>No emails found.</p>
          )}
        </div>
      </aside>

      <main className="main-content">
        {selectedEmail ? (
          <div className="email-detail">
            <header className="detail-header">
              <h2>{selectedEmail.headers?.subject || 'No Subject'}</h2>
              <p><strong>From:</strong> {selectedEmail.headers?.from || 'N/A'}</p>
              <p><strong>To:</strong> {selectedEmail.headers?.to || 'N/A'}</p>
              <p><strong>Date:</strong> {selectedEmail.headers?.date || 'N/A'}</p>
            </header>

            {selectedEmail.attachments && selectedEmail.attachments.length > 0 && (
              <div className="attachments-section">
                <strong>Attachments:</strong>
                <ul>
                  {selectedEmail.attachments.map(att => (
                    <li key={att.filename}>
                      <a href={createAttachmentUrl(selectedEmail.id, att.filename)} download={att.filename}>
                        {att.filename} ({Math.round(att.size / 1024)} KB)
                      </a>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            <div className="email-body">
              {selectedEmail.htmlContent ? (
                <iframe
                  srcDoc={selectedEmail.htmlContent}
                  title="Email HTML Content"
                  sandbox="allow-same-origin" // Security best practice
                />
              ) : (
                <pre>{selectedEmail.textContent || 'No content.'}</pre>
              )}
            </div>
          </div>
        ) : (
          <div className="no-email-selected">
            <p>Select an email from the list to view its content.</p>
          </div>
        )}
      </main>
    </div>
  );
}

export default App;