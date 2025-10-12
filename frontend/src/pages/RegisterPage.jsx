import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import './RegisterPage.css';
import { checkEmailAuthorization, registerUser } from '../api'; // Import API functions

function RegisterPage() {
  const [email, setEmail] = useState('');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  
  const navigate = useNavigate();

  const handleRegister = async (e) => {
    e.preventDefault();
    setError('');
    setMessage('');
    setIsLoading(true);

    try {
      // Step 1: Check if the email is authorized
      setMessage('Checking email authorization...');
      const authResponse = await checkEmailAuthorization(email);

      if (!authResponse.is_authorized) {
        setError('This email is not authorized to register. Please contact an administrator.');
        setIsLoading(false);
        return;
      }

      // Step 2: If authorized, proceed with registration
      setMessage('Email authorized. Proceeding with registration...');
      const registerData = { email, username, password };
      const registerResponse = await registerUser(registerData);

      // Handle success
      setMessage(registerResponse.message || 'Registration successful! Redirecting to login...');
      
      // Redirect to login page after a short delay
      setTimeout(() => {
        navigate('/login');
      }, 2000);

    } catch (err) {
      // Handle errors from either API call
      setError(err.message || 'An unexpected error occurred.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="register-container">
      <form onSubmit={handleRegister} className="register-form">
        <h2>Create Account</h2>
        
        {message && <p className="message">{message}</p>}
        {error && <p className="error">{error}</p>}

        <div className="input-group">
          <label htmlFor="email">Email</label>
          <input type="email" id="email" value={email} onChange={(e) => setEmail(e.target.value)} required disabled={isLoading} />
        </div>

        <div className="input-group">
          <label htmlFor="username">Username</label>
          <input type="text" id="username" value={username} onChange={(e) => setUsername(e.target.value)} required disabled={isLoading} />
        </div>

        <div className="input-group">
          <label htmlFor="password">Password</label>
          <input type="password" id="password" value={password} onChange={(e) => setPassword(e.target.value)} required disabled={isLoading} />
        </div>

        <button type="submit" className="register-button" disabled={isLoading}>
          {isLoading ? 'Processing...' : 'Register'}
        </button>
      </form>
    </div>
  );
}

export default RegisterPage;
