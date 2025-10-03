import React, { useState } from 'react';
import './EmailParser.css';

const EmailParser = () => {
    const [emailText, setEmailText] = useState('');
    const [parsedData, setParsedData] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState('');

    const handleProcessEmail = async () => {
        if (!emailText.trim()) {
            setError('Email text cannot be empty.');
            return;
        }

        setIsLoading(true);
        setError('');
        setParsedData(null);

        try {
            const response = await fetch('/process_email', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text: emailText }),
            });

            const data = await response.json();

            if (!response.ok) {
                // Handle both authentication errors and other server errors
                throw new Error(data.error || 'An unexpected error occurred.');
            }

            setParsedData(data);
        } catch (err) {
            setError(err.message);
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="parser-container">
            <h2>Email Parser</h2>
            <p>Paste the raw email text below to extract its components.</p>
            <textarea
                value={emailText}
                onChange={(e) => setEmailText(e.target.value)}
                placeholder="From: user@example.com&#10;Subject: Hello World&#10;&#10;This is the body..."
                className="parser-textarea"
                disabled={isLoading}
            />
            <button
                onClick={handleProcessEmail}
                className="btn btn-primary"
                disabled={isLoading}
            >
                {isLoading ? 'Processing...' : 'Process Email'}
            </button>

            {error && <div className="error-message">{error}</div>}

            {parsedData && (
                <div className="results-container">
                    <h3>Parsed Results</h3>
                    <div className="result-item">
                        <strong>From:</strong>
                        <p>{parsedData.from}</p>
                    </div>
                    <div className="result-item">
                        <strong>To:</strong>
                        <p>{parsedData.to}</p>
                    </div>
                    <div className="result-item">
                        <strong>Date:</strong>
                        <p>{parsedData.date}</p>
                    </div>
                    <div className="result-item">
                        <strong>Subject:</strong>
                        <p>{parsedData.subject}</p>
                    </div>
                    <div className="result-item">
                        <strong>Body:</strong>
                        <pre className="result-body">{parsedData.body}</pre>
                    </div>
                </div>
            )}
        </div>
    );
};

export default EmailParser;