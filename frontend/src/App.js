import React, { useState } from 'react';
import './App.css';
import Auth from './components/Auth';
import PointsManager from './components/PointsManager';
import ErrorNotification from './components/ErrorNotification';
import FriendsList from './components/FriendsList';
import Leaderboard from './components/Leaderboard';
import LotteryDraw from './components/LotteryDraw';
import BetHistory from './components/BetHistory';
import { useAuth } from './contexts/AuthContext';
import { useError } from './contexts/ErrorContext';

/**
 * The main application component.
 */
function App() {
  const { currentUser, logout, updateUser } = useAuth();
  const { error, clearError } = useError();

  const [showAuthModal, setShowAuthModal] = useState(false);
  const [showPointsModal, setShowPointsModal] = useState(false);

  const handleLogout = () => {
    logout();
  };

  const renderHeader = () => (
    <div className="app-header">
      <div className="auth-section">
        {currentUser ? (
          <div>
            <span>ID: {currentUser.displayId}</span>
            <span> | </span>
            <span>积分: {currentUser.points}</span>
            <button onClick={handleLogout} className="header-button">退出登录</button>
          </div>
        ) : (
          <button onClick={() => setShowAuthModal(true)} className="header-button">注册/登录</button>
        )}
      </div>
      <div className="points-section">
        {currentUser && (
          <button onClick={() => setShowPointsModal(true)} className="header-button">积分管理</button>
        )}
      </div>
    </div>
  );

  return (
    <div className="App">
      {renderHeader()}
      <h1>六合彩</h1>

      <div className="main-content">
        <div className="top-section">
          <LotteryDraw />
        </div>
        <div className="bottom-section">
          {currentUser && <BetHistory />}
        </div>
      </div>

      <div className="lobby-features">
        {currentUser && <FriendsList />}
        <Leaderboard />
      </div>

      <ErrorNotification message={error} onClose={clearError} />

      {showAuthModal && <Auth onClose={() => setShowAuthModal(false)} onLoginSuccess={() => setShowAuthModal(false)} />}

      {showPointsModal && <PointsManager
        currentUser={currentUser}
        onClose={() => setShowPointsModal(false)}
        onTransferSuccess={updateUser}
      />}
    </div>
  );
}

export default App;