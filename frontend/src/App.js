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

  if (!gameState) {
    return (
      <div className="App">
        <h1>十三张 (Chinese Poker)</h1>
        <div className="room-management">
          <h2>Room Management</h2>
          <button onClick={handleCreateRoom}>Create Room</button>
          <hr />
          <input
            type="text"
            placeholder="Enter Room ID"
            value={inputRoomId}
            onChange={(e) => setInputRoomId(e.target.value)}
          />
          <button onClick={handleJoinRoom}>Join Room</button>
        </div>
      </div>
    );
  }

  const { room, game } = gameState;
  const currentPlayer = room.players.find(p => p.id === playerId);
  const opponents = room.players.filter(p => p.id !== playerId);

  return (
    <div className="App">
      <h1>十三张 (Room: {room.id})</h1>
      <div className="game-container">
        <div className="player-area-top">
          {opponents[0] && <PlayerArea player={opponents[0]} />}
          {opponents[2] && <PlayerArea player={opponents[2]} />}
        </div>
        <div className="game-middle">
          <div className="player-area-left">
            {opponents[1] && <PlayerArea player={opponents[1]} />}
          </div>
          <div className="game-table-area">
            <GameTable game={game} />
            {room.state === 'waiting' && (
              <button onClick={handleStartGame} disabled={room.players.length < 2}>
                Start Game ({room.players.length}/4 players)
              </button>
            )}
          </div>
          <div className="player-area-right">
            {/* Reserved for 4th opponent, but layout can be tricky.
                For now, top can have 2 players. */}
          </div>
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
      </div>
    </div>
  );
}

export default App;