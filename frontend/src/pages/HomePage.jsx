import React from 'react';
import './HomePage.css';

const HomePage = () => {
  return (
    <div className="homepage">
      <h1>Welcome to Bill & Lottery Tracker!</h1>
      <p>Your personal assistant for managing bills and tracking lottery results.</p>
      <div className="features">
        <h2>Features:</h2>
        <ul>
          <li>📧 Automatically process bills from your emails using AI.</li>
          <li>📊 View and manage all your bills in one place.</li>
          <li>🎰 Track the latest lottery results.</li>
          <li>🔒 Secure user authentication.</li>
        </ul>
      </div>
      <p>Get started by registering an account or logging in.</p>
    </div>
  );
};

export default HomePage;
