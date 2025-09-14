import React, { useState, useEffect } from 'react';
import { getMessages } from '../api';

// Define the structure of a Telegram message based on what we might need
interface TelegramMessage {
  message_id: number;
  date: number; // Unix timestamp
  text?: string;
  // We can add other fields like 'photo', 'video' etc. if needed
}

const MessageList: React.FC = () => {
  const [messages, setMessages] = useState<TelegramMessage[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const fetchMessages = async () => {
    try {
      // Don't set loading to true on refetch, to avoid UI flicker
      const response = await getMessages();
      if (response.data.success) {
        setMessages(response.data.data);
        setError(''); // Clear previous errors on successful fetch
      } else {
        // Only set error if it's a new one
        if (error !== response.data.message) {
            setError(response.data.message);
        }
      }
    } catch (err: any) {
      const newError = err.response?.data?.message || 'Failed to fetch messages.';
      if (error !== newError) {
          setError(newError);
      }
    } finally {
      setLoading(false); // Only sets loading to false on the first load
    }
  };

  useEffect(() => {
    fetchMessages(); // Initial fetch
    const interval = setInterval(fetchMessages, 5000); // Poll every 5 seconds
    return () => clearInterval(interval); // Cleanup on component unmount
  }, []); // Empty dependency array means this effect runs only once on mount

  return (
    <div style={{ marginTop: '20px', border: '1px solid #ccc', padding: '10px' }}>
      <h3>Latest Channel Messages</h3>
      {loading && <p>Loading messages...</p>}
      {error && <p style={{ color: 'red' }}>Error: {error}</p>}
      {!loading && !error && messages.length === 0 && <p>No messages to display.</p>}
      {messages.length > 0 && (
        <ul style={{ listStyleType: 'none', padding: 0 }}>
          {messages.map((msg) => (
            <li key={msg.message_id} style={{ marginBottom: '15px', borderBottom: '1px solid #eee', paddingBottom: '10px' }}>
              <p>{msg.text || <em>(No text content)</em>}</p>
              <small><em>{new Date(msg.date * 1000).toLocaleString()}</em></small>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
};

export default MessageList;
