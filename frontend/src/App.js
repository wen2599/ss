import React, { useState, useEffect } from 'react';
import './App.css';
import GameTable from './components/GameTable';
import PlayerArea from './components/PlayerArea';
import Auth from './components/Auth';
import PointsManager from './components/PointsManager';
import { matchmake, getRoomState, startGame, checkSession, logout } from './api';

/**
 * The main application component.
 * Manages user authentication, game state, and renders the main UI.
 */
function App() {
  const [currentUser, setCurrentUser] = useState(null);
  const [showAuthModal, setShowAuthModal] = useState(false);
  const [showPointsModal, setShowPointsModal] = useState(false);

  const [roomId, setRoomId] = useState(null);
  const [gameState, setGameState] = useState(null);
  const [stateHash, setStateHash] = useState(null);

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

  /**
   * Long-polling effect to keep the game state updated.
   * When a roomId is set, it starts a long-polling loop that
   * continuously fetches the latest game state from the server.
   */
  useEffect(() => {
    if (roomId && currentUser) {
      let active = true;

      const longPoll = async (hash) => {
        if (!active) return;
        try {
          const response = await getRoomState(roomId, hash);
          if (!active) return;

          if (response.success) {
            if (!response.no_change) {
              setGameState(response);
              setStateHash(response.state_hash);
              longPoll(response.state_hash); // Continue with the new hash
            } else {
              // If no change, just poll again with the same hash
              longPoll(hash);
            }
          } else {
            console.error('Failed to fetch game state:', response?.message);
            if (active) {
                setRoomId(null);
                setGameState(null);
            }
          }
        } catch (error) {
          console.error('Error in long-polling:', error);
          if (active) {
            // In case of network error, wait a bit before retrying
            setTimeout(() => longPoll(hash), 5000);
          }
        }
      };

      // Initial fetch
      const init = async () => {
          const initialState = await getRoomState(roomId);
          if (active && initialState.success) {
              setGameState(initialState);
              setStateHash(initialState.state_hash);
              longPoll(initialState.state_hash);
          } else if (active) {
              console.error('Failed to get initial state', initialState?.message);
              setRoomId(null);
          }
      };

      init();

      return () => {
        active = false;
      };
    }
  }, [roomId, currentUser]);

  const handleMatchmake = async (gameMode) => {
    if (!currentUser) {
      alert('请先登录再开始游戏');
      setShowAuthModal(true);
      return;
    }
    const response = await matchmake(gameMode);
    if (response && response.success) {
      setRoomId(response.roomId);
    } else {
      alert(response.message || '匹配失败');
    }
  };

  const handleStartGame = async () => {
    if (roomId) {
      await startGame(roomId);
      // The long-poll will detect the state change
    }
  };

  const handleLogout = async () => {
    await logout();
    setCurrentUser(null);
    setRoomId(null);
    setGameState(null);
    setStateHash(null);
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
        <div className="opponents-area">
          {opponents.map((opponent) => (
            <PlayerArea key={opponent.id} player={opponent} />
          ))}
        </div>
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