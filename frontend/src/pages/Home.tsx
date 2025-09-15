// frontend/src/pages/Home.tsx
import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import AuthForm from '../components/AuthForm';
import UserProfile from '../components/UserProfile';
import BettingPanel from '../components/BettingPanel';
import LotteryBanner from '../components/LotteryBanner';
import { getLatestDraw } from '../api';

interface Lottery {
  lottery_type: string;
  period: string;
  winning_numbers: string;
  draw_time: string;
}

const AppContent: React.FC = () => {
  const [draws, setDraws] = useState<Record<string, Lottery> | null>(null);

  useEffect(() => {
    const fetchDraws = async () => {
      try {
        const response = await getLatestDraw();
        if (response.data.success) {
          setDraws(response.data.data);
        }
      } catch (error) {
        console.error("Failed to fetch latest draws", error);
      }
    };
    fetchDraws();
  }, []);

  // This component holds the main application view for a logged-in user.
  return (
    <>
      {draws && (
        <div style={{ display: 'flex', justifyContent: 'space-around', marginBottom: '20px' }}>
          {Object.values(draws).map((lottery) => (
            <LotteryBanner key={lottery.lottery_type} lottery={lottery} />
          ))}
        </div>
      )}
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
