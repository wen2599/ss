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

  // A helper function to find player positions relative to the current player
  const getPlayerPositions = (players, currentPlayerId) => {
    const playerPositions = {
      bottom: null,
      left: null,
      right: null,
      top: null, // Only used in 4-player games, but good to have
    };

    const currentPlayerIndex = players.findIndex(p => p.id === currentPlayerId);
    if (currentPlayerIndex === -1) {
      // If current player not found, just return the first players in order
      if (players[0]) playerPositions.bottom = players[0];
      if (players[1]) playerPositions.left = players[1];
      if (players[2]) playerPositions.right = players[2];
      return playerPositions;
    }

    playerPositions.bottom = players[currentPlayerIndex];
    if (players.length === 3) {
      playerPositions.left = players[(currentPlayerIndex + 1) % 3];
      playerPositions.right = players[(currentPlayerIndex + 2) % 3];
    }
    // Add logic for 2 or 4 players if needed
    return playerPositions;
  };

  const playerPositions = getPlayerPositions(players, currentPlayerId);


  // Render game area if a room is joined
  return (
    <div className="game-board">
      {playerPositions.top && (
        <div className="player-area-top">
          <PlayerArea player={playerPositions.top} isCurrentPlayer={false} />
        </div>
      )}
      {playerPositions.left && (
        <div className="player-area-left">
          <PlayerArea player={playerPositions.left} isCurrentPlayer={false} />
        </div>
      )}

      <div className="game-table-area">
        <GameTable cardsOnTable={cardsOnTable} bottomCards={bottomCards} />
      </div>

      {playerPositions.right && (
        <div className="player-area-right">
          <PlayerArea player={playerPositions.right} isCurrentPlayer={false} />
        </div>
      )}
      {playerPositions.bottom && (
        <div className="player-area-bottom">
          <PlayerArea
             player={playerPositions.bottom}
             isCurrentPlayer={true} // The bottom player is always the current user
             gameId={gameId}
             roomId={currentRoomId}
             onPlay={fetchGameState}
          />
        </div>
      )}
    </div>
  );
}

export default App;