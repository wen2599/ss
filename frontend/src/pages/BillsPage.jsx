
import React from 'react';
import './BillsPage.css';

function BillsPage() {
  return (
    <div className="bills-page">
      <h1>Bills</h1>
      <div className="bill-content">
        <div className="email-raw">
          <h2>Raw Email</h2>
          <pre>
            {/* Raw email content will go here */}
          </pre>
        </div>
        <div className="email-parsed">
          <h2>Parsed Email</h2>
          <pre>
            {/* Parsed email content will go here */}
          </pre>
        </div>
      </div>
    </div>
  );
}

export default BillsPage;
