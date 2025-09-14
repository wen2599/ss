// frontend/src/pages/Home.tsx
import React, { useState } from 'react';
import { useAuth } from '../context/AuthContext';
import AuthForm from '../components/AuthForm';
import UserProfile from '../components/UserProfile';
import BettingPanel from '../components/BettingPanel';
// The draw results can be re-added inside AppContent later if needed.

const AppContent: React.FC = () => {
  // This component holds the main application view for a logged-in user.
  return (
    <>
      <UserProfile />
      <h2 style={{ textAlign: 'center' }}>Betting Panel</h2>
      <BettingPanel />
    </>
  );
};

const Home: React.FC = () => {
  const { isAuthenticated, isLoading } = useAuth();
  const [isRegisterMode, setIsRegisterMode] = useState(false);

  return (
    <div style={{ padding: '20px', maxWidth: '800px', margin: '0 auto' }}>
      <h1 style={{ textAlign: 'center' }}>六合彩模拟投注</h1>

      {isLoading ? (
        <p style={{ textAlign: 'center' }}>Loading Application...</p>
      ) : isAuthenticated ? (
        <AppContent />
      ) : (
        <div>
          <AuthForm
            isRegister={isRegisterMode}
            onSwitchMode={() => setIsRegisterMode(!isRegisterMode)}
          />
        </div>
      )}
    </div>
  );
};

export default Home;
