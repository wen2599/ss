import React from 'react';
import { useAuth } from '../context/AuthContext';

const UserProfile: React.FC = () => {
  const { user, logout } = useAuth();

  if (!user) {
    return null; // This component should only be rendered when the user is authenticated.
  }

  return (
    <div style={{ padding: '10px', border: '1px solid #ccc', marginTop: '20px', backgroundColor: '#f9f9f9' }}>
      <span>Welcome, <strong>{user.username}</strong>!</span>
      <button onClick={logout} style={{ float: 'right' }}>
        Logout
      </button>
    </div>
  );
};

export default UserProfile;
