import React, { useState, useEffect } from 'react';
import './App.css';
import GameTable from './components/GameTable';
import PlayerArea from './components/PlayerArea';
import { createRoom, joinRoom, getRoomState, startGame } from './api';

function App() {
  const [roomId, setRoomId] = useState(null);
  const [playerId, setPlayerId] = useState(null);
  const [gameState, setGameState] = useState(null);
  const [inputRoomId, setInputRoomId] = useState('');

  // Polling for game state updates
  useEffect(() => {
    if (roomId && playerId) {
      const interval = setInterval(() => {
        fetchGameState(roomId, playerId);
      }, 2000); // Poll every 2 seconds
      return () => clearInterval(interval);
    }
  }, [roomId, playerId]);

  const fetchGameState = async (currentRoomId, currentPlayerId) => {
    try {
      const response = await getRoomState(currentRoomId, currentPlayerId);
      if (response && response.success) {
        setGameState(response);
      } else {
        console.error('Failed to fetch game state:', response?.message);
      }
    } catch (error) {
      console.error('Error fetching game state:', error);
    }
  };

  const handleCreateRoom = async () => {
    const response = await createRoom();
    if (response && response.success) {
      setRoomId(response.roomId);
      setPlayerId(response.playerId);
      fetchGameState(response.roomId, response.playerId);
    }
  };

  const handleJoinRoom = async () => {
    const response = await joinRoom(inputRoomId);
    if (response && response.success) {
      setRoomId(response.roomId);
      setPlayerId(response.playerId);
      fetchGameState(response.roomId, response.playerId);
    }
  };

  const handleStartGame = async () => {
    if (roomId) {
      await startGame(roomId);
      fetchGameState(roomId, playerId); // Fetch state immediately after starting
    }
  };

  const renderOpponents = (opponents) => {
    const positions = [];
    switch (opponents.length) {
      case 1: // 2-player game
        positions.push('top');
        break;
      case 2: // 3-player game
        positions.push('left', 'right');
        break;
      case 3: // 4-player game
        positions.push('left', 'top', 'right');
        break;
      default:
        break;
    }

    return opponents.map((opponent, index) => (
      <div key={opponent.id} className={`opponent-position-${positions[index]}`}>
        <PlayerArea player={opponent} />
      </div>
    ));
  };


  if (!gameState) {
    return (
      <div className="App">
        <h1>十三张</h1>
        <div className="room-management">
          <h2>房间管理</h2>
          <button onClick={handleCreateRoom}>创建房间</button>
          <hr />
          <input
            type="text"
            placeholder="输入房间号"
            value={inputRoomId}
            onChange={(e) => setInputRoomId(e.target.value)}
          />
          <button onClick={handleJoinRoom}>加入房间</button>
        </div>
      </div>
    );
  }

  const { room, game } = gameState;
  const currentPlayer = room.players.find(p => p.id === playerId);
  const opponents = room.players.filter(p => p.id !== playerId);

  return (
    <div className="App">
      <div className="game-container">
        {renderOpponents(opponents)}

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
    </div>
  );
}

export default App;