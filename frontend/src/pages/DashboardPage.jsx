import { useState, useEffect } from 'react';
import api from '../services/api';

const DashboardPage = () => {
    const [emails, setEmails] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const fetchEmails = async () => {
        setLoading(true);
        try {
            const response = await api.get('/email.php?action=list');
            if (response.data.success) {
                setEmails(response.data.emails);
            } else {
                setError(response.data.message);
            }
        } catch (err) {
            setError('Failed to fetch emails.');
        }
        setLoading(false);
    };

    useEffect(() => {
        fetchEmails();
        const interval = setInterval(fetchEmails, 30000); // Refresh every 30 seconds
        return () => clearInterval(interval);
    }, []);

    const handleProcessEmail = async (emailId) => {
        try {
            const response = await api.get(`/email.php?action=process&id=${emailId}`);
            if (response.data.success) {
                // Refresh the list to show the updated status and result
                fetchEmails(); 
            } else {
                alert(`Processing failed: ${response.data.message}`);
            }
        } catch (err) {
            alert('An error occurred while processing the email.');
        }
    };

    return (
        <div>
            <div className="dashboard-header">
                <h2>Your Email Submissions</h2>
                <button onClick={fetchEmails} disabled={loading}>
                    {loading ? 'Refreshing...' : 'Refresh'}
                </button>
            </div>

            {error && <p className="error-message">{error}</p>}

            <div className="email-list">
                {emails.length === 0 && !loading && <p style={{textAlign: 'center', padding: '2rem'}}>No emails found. Send an email to your designated address to get started.</p>}
                
                {emails.map(email => (
                    <div key={email.id} className="email-item">
                        <div className="email-item-content">
                           <h3>Received: {new Date(email.received_at).toLocaleString()}</h3>
                           <p>Status: <span className={`status-badge status-${email.status.toLowerCase()}`}>{email.status}</span></p>
                           {email.status === 'pending' && (
                                <button onClick={() => handleProcessEmail(email.id)} style={{marginTop: '1rem'}}>
                                    Process Now
                                </button>
                           )}
                           {email.settlement_details && (
                                <div className="settlement-slip">
                                    <h4>Settlement Slip:</h4>
                                    <pre>{email.settlement_details}</pre>
                                </div>
                           )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};

export default DashboardPage;
