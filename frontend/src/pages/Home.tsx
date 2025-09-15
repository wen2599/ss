// frontend/src/pages/Home.tsx
import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import AuthForm from '../components/AuthForm';
import UserProfile from '../components/UserProfile';
import BettingPanel from '../components/BettingPanel';
import LotteryBanner from '../components/LotteryBanner';
import { getLatestDraw } from '../api';

interface Lottery {
  lottery_type: string;
  winning_numbers: string;
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

  return (
    <>
      <div className="banner-container">
        {draws && Object.values(draws).map((lottery) => (
          <LotteryBanner key={lottery.lottery_type} lottery={lottery} />
        ))}
      </div>
      <UserProfile />
      <div style={{ textAlign: 'center', marginBottom: '20px' }}>
        <Link to="/bet-history">
          <button className="button">投注历史</button>
        </Link>
      </div>
      <h2 style={{ textAlign: 'center' }}>Betting Panel</h2>
      <BettingPanel />
    </>
  );
};

const Home: React.FC = () => {
  const { isAuthenticated, isLoading } = useAuth();
  const [isRegisterMode, setIsRegisterMode] = useState(false);

  return (
    <div className="container">
      <h1 className="title">六合彩模拟投注</h1>

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
