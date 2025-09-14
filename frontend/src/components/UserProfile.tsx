import React from 'react';
import { useAuth } from '../context/AuthContext';

const UserProfile: React.FC = () => {
  const { user, logout } = useAuth();

  if (!user) {
    return null; // This component should only be rendered when the user is authenticated.
  }

  return (
    <div style={{ padding: '10px', border: '1px solid #ccc', marginTop: '20px', backgroundColor: '#f9f9f9', overflow: 'hidden' }}>
      <div style={{ float: 'left' }}>
        <p style={{ margin: 0 }}>Welcome, <strong>{user.username}</strong></p>
        <p style={{ margin: '5px 0 0 0', color: '#555' }}>
          Phone: {user.phone} | Points: {user.points}
        </p>
      </div>
      <button onClick={logout} style={{ float: 'right', padding: '8px 12px' }}>
        Logout
      </button>
    </div>
  );
};

export default UserProfile;
