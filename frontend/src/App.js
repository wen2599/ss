import React, { useState, useEffect } from 'react';
import './App.css';
import GameTable from './components/GameTable';
import PlayerArea from './components/PlayerArea';
import Auth from './components/Auth';
import PointsManager from './components/PointsManager';
import { matchmake, getRoomState, startGame, checkSession, logout } from './api';

function App() {
  const [currentUser, setCurrentUser] = useState(null);
  const [showAuthModal, setShowAuthModal] = useState(false);
  const [showPointsModal, setShowPointsModal] = useState(false);

  const [roomId, setRoomId] = useState(null);
  const [gameState, setGameState] = useState(null);

  // Check session on initial load
  useEffect(() => {
    const verifySession = async () => {
      const response = await checkSession();
      if (response.success && response.isAuthenticated) {
        setCurrentUser(response.user);
      }
    };
    verifySession();
  }, []);

  // Polling for game state updates
  useEffect(() => {
    if (roomId && currentUser) {
      const interval = setInterval(() => {
        fetchGameState(roomId, currentUser.id);
      }, 2000);
      return () => clearInterval(interval);
    }
  }, [roomId, currentUser]);

  const fetchGameState = async (currentRoomId, currentPlayerId) => {
    try {
      const response = await getRoomState(currentRoomId, currentPlayerId);
      if (response.success) {
        setGameState(response);
      } else {
        console.error('Failed to fetch game state:', response?.message);
        setRoomId(null);
        setGameState(null);
      }
    } catch (error) {
      console.error('Error fetching game state:', error);
    }
  };

  const handleMatchmake = async (gameMode) => {
    if (!currentUser) {
      alert('请先登录再开始游戏');
      setShowAuthModal(true);
      return;
    }
    const response = await matchmake(gameMode);
    if (response && response.success) {
      setRoomId(response.roomId);
      fetchGameState(response.roomId, currentUser.id);
    } else {
      alert(response.message || '匹配失败');
    }
  };

  const handleStartGame = async () => {
    if (roomId) {
      await startGame(roomId);
      fetchGameState(roomId, currentUser.id);
    }
  };

  const handleLogout = async () => {
    await logout();
    setCurrentUser(null);
    setRoomId(null);
    setGameState(null);
  };

  const renderHeader = () => {
    return (
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
  };

  if (!gameState || !roomId) { // Show lobby if not in a room
    return (
      <div className="App">
        {renderHeader()}
        <h1>十三张</h1>
        <div className="game-mode-selection">
          <h2>选择游戏模式</h2>
          <div className="mode-buttons">
            <button onClick={() => handleMatchmake('normal_2')}>普通2分场</button>
            <button onClick={() => handleMatchmake('normal_5')}>普通5分场</button>
            <button onClick={() => handleMatchmake('double_2')}>翻倍2分场</button>
            <button onClick={() => handleMatchmake('double_5')}>翻倍5分场</button>
          </div>
        </div>
        {showAuthModal && <Auth onClose={() => setShowAuthModal(false)} onLoginSuccess={(user) => {
          setCurrentUser(user);
          setShowAuthModal(false);
        }} />}
        {showPointsModal && <PointsManager currentUser={currentUser} onClose={() => setShowPointsModal(false)} onTransferSuccess={() => checkSession().then(res => res.success && res.isAuthenticated && setCurrentUser(res.user))} />}
      </div>
    );
  }

  const { room, game } = gameState;
  const currentPlayer = room.players.find(p => p.id === currentUser.id);
  const opponents = room.players.filter(p => p.id !== currentUser.id);

  return (
    <div className="App">
       {renderHeader()}
      <div className="game-container">
        {opponents.map((opponent, index) => (
            <div key={opponent.id} className={`opponent-position-${['top', 'left', 'right'][index]}`}>
                 <PlayerArea player={opponent} />
            </div>
        ))}
        <div className="player-area-bottom">
          {currentPlayer && (
            <PlayerArea
              player={currentPlayer}
              isCurrentPlayer={true}
              gameId={game?.id}
              roomId={room.id}
            />
          )}
        </div>
        <div className="game-table-area">
          <GameTable game={game} />
          {room.state === 'waiting' && (
            <button onClick={handleStartGame} disabled={room.players.length < 2}>
              开始游戏 ({room.players.length}/4 人)
            </button>
          )}
        </div>
      </div>
      {showAuthModal && <Auth onClose={() => setShowAuthModal(false)} onLoginSuccess={(user) => {
          setCurrentUser(user);
          setShowAuthModal(false);
      }} />}
      {showPointsModal && <PointsManager currentUser={currentUser} onClose={() => setShowPointsModal(false)} onTransferSuccess={() => checkSession().then(res => res.success && res.isAuthenticated && setCurrentUser(res.user))} />}
    </div>
  );
}

export default App;