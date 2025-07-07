import React, { useState } from 'react';
import './App.css'; // You might need to create/update this CSS file
import GameTable from './components/GameTable'; // Ensure GameTable can receive bottomCards and gameState
import PlayerArea from './components/PlayerArea';
import { createRoom, joinRoom, getRoomState } from './api'; // Import API functions

function App() {
  const [inputRoomId, setInputRoomId] = useState(''); // Input field for room ID
  const [currentRoomId, setCurrentRoomId] = useState(null); // Joined room ID
  const [currentPlayerId, setCurrentPlayerId] = useState(null); // Current player's ID
  const [players, setPlayers] = useState([]); // List of players in the room
  const [cardsOnTable, setCardsOnTable] = useState([]); // Cards on the table
  const [bottomCards, setBottomCards] = useState([]); // State for bottom cards
  const [gameState, setGameState] = useState('waiting'); // State for game phase (waiting, calling_landlord, playing, etc.)


  const handleCreateRoom = async () => {
    const response = await createRoom();
    if (response && response.success) {
      console.log('Room created successfully:', response);
      setCurrentRoomId(response.roomId);
      await fetchGameState(response.roomId, response.playerId); // Fetch state after creating
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
        console.log('Game state fetched successfully:', gameStateResponse.room);
        setCurrentPlayerId(playerId); // Set current player ID
        setPlayers(Object.values(gameStateResponse.room.players)); // Update players state
        setCardsOnTable(gameStateResponse.room.discarded_cards); // Update cards on table state
        setBottomCards(gameStateResponse.room.bottom_cards || []); // Update bottom cards state;
        setGameState(gameStateResponse.room.state); // Update game state
      }
    } catch (error) {
      console.error('Failed to fetch game state:', error);
    }
  };

  // Conditional rendering based on whether a room is joined
  if (!currentRoomId) {
    return (
      <div className="App">
        <h1>斗地主多人游戏</h1>
        <div className="room-management">
          <h2>房间管理</h2>
          <input
            type="text"
            placeholder="输入房间ID"
            value={inputRoomId}
            onChange={(e) => setInputRoomId(e.target.value)}
          />
          <button onClick={handleCreateRoom}>创建房间</button>
          <button onClick={handleJoinRoom}>加入房间</button>
        </div>
      </div>
    );
  }

  // Render game area if a room is joined
  return (
    <div className="App">
      <h1>斗地主多人游戏</h1>
      <div className="game-container" style={{ display: 'flex', flexDirection: 'column', height: '100vh' }}> {/* Added flexbox for layout */}
        <div className="game-table-area">
          <GameTable cardsOnTable={cardsOnTable} bottomCards={bottomCards} gameState={gameState} />
        </div>
        {/* This div will hold the current player's hand and potentially action buttons */}
        </div>
        <div className="player-areas"> {/* Container for all player areas */}
          {players.map(player => {
            const isCurrent = player.id === currentPlayerId;
            return (
            <PlayerArea
              key={player.id}
              player={player}
              isCurrentPlayer={isCurrent}
            />
          ))}
        </div>
      </div>
    </div>
  );
}

export default App;