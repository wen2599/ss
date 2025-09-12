import React, { useState, useEffect, useRef } from 'react';
import { getMessages, sendMessage } from '../api';
import { useAuth } from '../contexts/AuthContext';
import './Chat.css';

const Chat = ({ roomId }) => {
    const { currentUser } = useAuth();
    const [messages, setMessages] = useState([]);
    const [newMessage, setNewMessage] = useState('');
    const messagesEndRef = useRef(null);

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
    };

    const fetchMessages = async () => {
        if (roomId) {
            const response = await getMessages(roomId);
            if (response.success) {
                setMessages(response.messages);
            }
        }
    };

    useEffect(() => {
        fetchMessages();
        const interval = setInterval(fetchMessages, 3000); // Poll for new messages every 3 seconds
        return () => clearInterval(interval);
    }, [roomId]);

    useEffect(() => {
        scrollToBottom();
    }, [messages]);

    const handleSendMessage = async (e) => {
        e.preventDefault();
        if (newMessage.trim() === '') return;
        const response = await sendMessage(roomId, newMessage);
        if (response.success) {
            setNewMessage('');
            fetchMessages();
        }
    };

    return (
        <div className="chat-container">
            <div className="messages-list">
                {messages.map(msg => (
                    <div key={msg.id} className={`message ${msg.user_id === currentUser.id ? 'sent' : 'received'}`}>
                        <span className="message-sender">{msg.display_id}: </span>
                        <span className="message-text">{msg.message}</span>
                    </div>
                ))}
                <div ref={messagesEndRef} />
            </div>
            <form onSubmit={handleSendMessage} className="message-form">
                <input
                    type="text"
                    value={newMessage}
                    onChange={(e) => setNewMessage(e.target.value)}
                    placeholder="Type a message..."
                />
                <button type="submit">Send</button>
            </form>
        </div>
    );
};

export default Chat;
