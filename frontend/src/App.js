import React, { useState } from 'react';
import './App.css';
import GameTable from './components/GameTable';
import PlayerArea from './components/PlayerArea';
import Auth from './components/Auth';
import PointsManager from './components/PointsManager';
import ErrorNotification from './components/ErrorNotification';
import { useAppContext } from './contexts/AppContext';

/**
 * The main application component.
 * Renders the UI based on the state from AppContext.
 */
function App() {
  const {
    currentUser,
    roomId,
    gameState,
    error,
    clearError,
    logout,
    matchmake,
    startGame,
    updateUser
  } = useAppContext();

  const [showAuthModal, setShowAuthModal] = useState(false);
  const [showPointsModal, setShowPointsModal] = useState(false);

  const handleMatchmake = (gameMode) => {
    if (!currentUser) {
      setShowAuthModal(true);
    } else {
      matchmake(gameMode);
    }
  };

  const renderHeader = () => (
    <div className="app-header">
      <div className="auth-section">
        {currentUser ? (
          <div>
            <span>ID: {currentUser.displayId}</span>
            <span> | </span>
            <span>积分: {currentUser.points}</span>
            <button onClick={logout} className="header-button">退出登录</button>
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

  const renderLobby = () => (
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
    </div>
  );

  const renderGame = () => {
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
              <button onClick={startGame} disabled={room.players.length < 2}>
                开始游戏 ({room.players.length}/4 人)
              </button>
            )}
          </div>
        </div>
      </div>
    );
  };

  return (
    <>
      <ErrorNotification message={error} onClose={clearError} />

      {!gameState || !roomId ? renderLobby() : renderGame()}

      {showAuthModal && <Auth onClose={() => setShowAuthModal(false)} onLoginSuccess={() => setShowAuthModal(false)} />}

      {showPointsModal && <PointsManager
        currentUser={currentUser}
        onClose={() => setShowPointsModal(false)}
        onTransferSuccess={updateUser}
      />}
    </>
  );
}

export default App;