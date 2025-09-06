import React, { useState } from 'react';
import './App.css'; // You might need to create/update this CSS file
import GameTable from './components/GameTable'; // Ensure GameTable can receive bottomCards and gameState
import PlayerArea from './components/PlayerArea';
import { createRoom, joinRoom, getRoomState } from './api'; // Import API functions
// Test modification line

function App() {
  const [inputRoomId, setInputRoomId] = useState(''); // Input field for room ID
  const [currentRoomId, setCurrentRoomId] = useState(null); // Joined room ID
  const [currentPlayerId, setCurrentPlayerId] = useState(null); // Current player's ID
  const [players, setPlayers] = useState([]); // List of players in the room
  const [cardsOnTable, setCardsOnTable] = useState([]); // Cards on the table
  const [bottomCards, setBottomCards] = useState([]); // State for bottom cards
  const [gameId, setGameId] = useState(null); // State for game ID


  const handleCreateRoom = async (gameMode) => {
    console.log(`Creating room with mode: ${gameMode}`);
    // TODO: In the future, pass gameMode to the backend createRoom function.
    const response = await createRoom();
    if (response && response.success) {
      console.log('Room created successfully:', response);
      setCurrentRoomId(response.roomId);
      // For now, player ID is mocked. This will come from the login system later.
      const mockPlayerId = 'player_' + Math.random().toString(36).substr(2, 9);
      setCurrentPlayerId(mockPlayerId);
      await fetchGameState(response.roomId, mockPlayerId); // Fetch state after creating
    } else {
      console.error('Failed to create room:', response);
    }
  };

  const handleJoinRoom = async () => {
    if (inputRoomId) {
      const response = await joinRoom(inputRoomId);
      if (response && response.success) {
        console.log('Joined room successfully:', response);
        setCurrentRoomId(response.roomId);
        await fetchGameState(response.roomId, response.playerId); // Fetch state after joining
      } else {
        console.error('Failed to join room:', response);
      }
    } else {
      console.log('Please enter a Room ID to join.');
    }
  };

  const fetchGameState = async (roomId, playerId) => {
    try {
      const gameStateResponse = await getRoomState(roomId, playerId);
      if (gameStateResponse && gameStateResponse.success) {
        const room = gameStateResponse.room;
        console.log('Game state fetched successfully:', room);
        setCurrentPlayerId(playerId); // Set current player ID
        setPlayers(Object.values(room.players)); // Update players state
        setCardsOnTable(room.discarded_cards); // Update cards on table state
        setBottomCards(room.bottom_cards || []); // Update bottom cards state
        if (room.state === 'playing' && room.current_game_id) {
          setGameId(room.current_game_id);
        }
      }
    } catch (error) {
      console.error('Failed to fetch game state:', error);
    }
  };

  // Conditional rendering based on whether a room is joined
  if (!currentRoomId) {
    return (
      <div className="App">
        <h1>游戏大厅</h1>
        <div className="lobby-container">
          <div className="lobby-grid">
            <div className="lobby-item" onClick={() => handleCreateRoom('2_normal')}>
              <h2>2分普通场</h2>
              <p>底分: 2</p>
            </div>
            <div className="lobby-item" onClick={() => handleCreateRoom('2_double')}>
              <h2>2分翻倍场</h2>
              <p>底分: 2 (翻倍)</p>
            </div>
            <div className="lobby-item" onClick={() => handleCreateRoom('5_normal')}>
              <h2>5分普通场</h2>
              <p>底分: 5</p>
            </div>
            <div className="lobby-item" onClick={() => handleCreateRoom('5_double')}>
              <h2>5分翻倍场</h2>
              <p>底分: 5 (翻倍)</p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Render game area if a room is joined
  return (
    <div className="App">
      <h1>斗地主多人游戏</h1>
      <div
        className="game-container"
        style={{
          display: 'flex',
          flexDirection: 'column',
          height: '100vh'
        }}
      >
        <div className="game-table-area">
          <GameTable cardsOnTable={cardsOnTable} bottomCards={bottomCards} />
        </div>
        <div className="player-areas"> {/* Container for all player areas */}
          {players.map(player => {
            const isCurrent = player.id === currentPlayerId;
            return (
              <PlayerArea
                key={player.id}
                player={player}
                isCurrentPlayer={isCurrent}
                gameId={gameId}
                roomId={currentRoomId}
                onPlay={fetchGameState}
              />
            );
          })}
        </div>
      </div>
    </div>
  );
}

export default App;