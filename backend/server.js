const express = require('express');
const http = require('http');
const socketIO = require('socket.io');
const gameLogic = require('./game.js');

const app = express();
const server = http.createServer(app);

const FRONTEND_URL = 'https://ss.wenxiuxiu.eu.org'; // 定义前端URL

const io = socketIO(server, {
    cors: {
        origin: FRONTEND_URL, // 只允许你的前端域名访问
        methods: ["GET", "POST"]
    }
    // path: "/socket.io", // 这是默认值，通常不需要显式设置，除非Cloudflare代理了不同的路径
});

const PORT = process.env.PORT || 22439; // Serv00可能会设置PORT环境变量, 22439是备用

let players = {}; // { socketId: { id: socketId, hand: [], arrangedHand: null, isReady: false, specialHand: null }, ... }
let gameInProgress = false;
let deck = [];
let playerOrder = []; // [socketId1, socketId2] - 存储连接的玩家ID顺序

// Function to broadcast player order
function broadcastPlayerOrder() {
    io.emit('playerOrderUpdate', playerOrder);
    console.log("Broadcasted player order:", playerOrder);
}

io.on('connection', (socket) => {
    console.log('A user connected:', socket.id);

    if (playerOrder.length >= 2 && !playerOrder.includes(socket.id)) { // 简单处理，不允许超过2个不同玩家
        socket.emit('gameError', 'Game is full. Please try again later.');
        socket.disconnect();
        return;
    }

    if (!players[socket.id]) { // 确保玩家不重复添加
        players[socket.id] = { id: socket.id, hand: [], fullHandData: [], arrangedHand: null, isReady: false, specialHand: null };
        if (!playerOrder.includes(socket.id)) {
            playerOrder.push(socket.id);
        }
    }
    
    socket.emit('playerId', socket.id);
    broadcastPlayerOrder(); // Announce new player list

    if (playerOrder.length === 2 && !gameInProgress) {
        startGame();
    }

    socket.on('submitArrangement', (arrangedHandData) => {
        // ... (submitArrangement 逻辑保持不变) ...
        if (!players[socket.id] || !gameInProgress) return;

        const playerOriginalHand = players[socket.id].fullHandData;
        if (!playerOriginalHand || playerOriginalHand.length === 0) {
            socket.emit('gameError', 'No hand data found for submission.');
            return;
        }
        const arrangedPlayerHand = {
            front: arrangedHandData.front.map(cardId => playerOriginalHand.find(c => c.id === cardId)),
            middle: arrangedHandData.middle.map(cardId => playerOriginalHand.find(c => c.id === cardId)),
            back: arrangedHandData.back.map(cardId => playerOriginalHand.find(c => c.id === cardId))
        };

        if (arrangedPlayerHand.front.some(c=>c===undefined) || 
            arrangedPlayerHand.middle.some(c=>c===undefined) || 
            arrangedPlayerHand.back.some(c=>c===undefined)) {
            socket.emit('gameError', 'Invalid cards in arrangement (some cards not found).');
            return;
        }
        
        if (arrangedPlayerHand.front.length !== 3 ||
            arrangedPlayerHand.middle.length !== 5 ||
            arrangedPlayerHand.back.length !== 5) {
            socket.emit('gameError', 'Invalid number of cards in arrangement.');
            return;
        }
        
        const allArrangedCards = [
            ...arrangedPlayerHand.front, 
            ...arrangedPlayerHand.middle, 
            ...arrangedPlayerHand.back
        ];
        const uniqueCardIds = new Set(allArrangedCards.map(c => c.id));
        if (uniqueCardIds.size !== 13 ) {
            socket.emit('gameError', 'Invalid cards submitted. Cards might be duplicated or not from your hand.');
            return;
        }

        const special = gameLogic.checkSpecialHand(playerOriginalHand);
        if (special) {
            players[socket.id].specialHand = special;
            players[socket.id].arrangedHand = null; 
            console.log(`Player ${socket.id} has special hand: ${special.name}`);
        } else {
             if (!gameLogic.isValidArrangement(arrangedPlayerHand)) {
                socket.emit('gameError', 'Invalid arrangement: Hands are not in ascending order of strength (Front < Middle < Back).');
                return;
            }
            players[socket.id].arrangedHand = arrangedPlayerHand;
            players[socket.id].specialHand = null;
        }
       
        players[socket.id].isReady = true;
        io.to(socket.id).emit('arrangementAccepted');
        console.log(`Player ${socket.id} submitted arrangement.`);

        checkBothPlayersReady();
    });

    socket.on('disconnect', (reason) => {
        console.log('User disconnected:', socket.id, "Reason:", reason);
        const playerIndex = playerOrder.indexOf(socket.id);
        if (playerIndex > -1) {
            playerOrder.splice(playerIndex, 1);
        }
        delete players[socket.id];
        broadcastPlayerOrder(); // Announce updated player list
        
        if (playerOrder.length < 2 && gameInProgress) {
            io.emit('gameError', 'A player disconnected. Game reset.');
            resetGame(false); // Don't try to restart immediately if a player leaves mid-game
        } else if (playerOrder.length === 0) {
            resetGame(false);
        }
    });
});

function startGame() {
    if (playerOrder.length < 2) {
        console.log("Not enough players to start game.");
        io.emit('gameState', { message: "Waiting for more players..." });
        return;
    }
    console.log("Starting game with players:", playerOrder);
    gameInProgress = true;
    deck = gameLogic.shuffleDeck(gameLogic.createDeck());
    const hands = gameLogic.dealCards(deck, playerOrder.length);

    playerOrder.forEach((playerId, index) => {
        if (players[playerId]) { // Make sure player still exists
            players[playerId].hand = hands[index].map(card => card.id);
            players[playerId].fullHandData = hands[index]; 
            players[playerId].isReady = false;
            players[playerId].arrangedHand = null;
            players[playerId].specialHand = null;
            io.to(playerId).emit('dealCards', { hand: hands[index] }); 
        }
    });

    io.emit('gameState', { message: "Game started! Arrange your cards." });
    broadcastPlayerOrder(); // Ensure opponent ID is updated on clients
}

function checkBothPlayersReady() {
    if (playerOrder.length < 2) return; // Need two players in the order

    const player1Id = playerOrder[0];
    const player2Id = playerOrder[1];

    // Ensure both players are still connected and in the `players` object
    if (!players[player1Id] || !players[player2Id]) {
        console.log("One or more players disconnected before comparison.");
        // Game might have been reset by disconnect handler, or wait for new players.
        return;
    }

    const player1 = players[player1Id];
    const player2 = players[player2Id];

    if (player1.isReady && player2.isReady && gameInProgress) {
        let gameOutcome = {};
        // ... (gameOutcome logic from previous server.js, ensure player1Id and player2Id are included)
        // (Make sure to use player1Id and player2Id consistently from playerOrder)
        const p1FinalHand = player1.arrangedHand;
        const p2FinalHand = player2.arrangedHand;
        const p1Special = player1.specialHand;
        const p2Special = player2.specialHand;

        if (p1Special && p2Special) {
            gameOutcome = {
                winner: player1Id, // Simplified: P1 wins if both special
                reason: `Both special hands. ${p1Special.name} (P1) vs ${p2Special.name} (P2). P1 wins by default.`,
                player1Cards: player1.fullHandData, player2Cards: player2.fullHandData,
                player1Special: p1Special.name, player2Special: p2Special.name,
                player1Id: player1Id, player2Id: player2Id
            };
        } else if (p1Special) {
            gameOutcome = {
                winner: player1Id, reason: `Player ${player1Id.substring(0,6)} has special: ${p1Special.name}`,
                player1Cards: player1.fullHandData, player2Cards: p2FinalHand || player2.fullHandData,
                player1Special: p1Special.name,
                player1Id: player1Id, player2Id: player2Id
            };
        } else if (p2Special) {
             gameOutcome = {
                winner: player2Id, reason: `Player ${player2Id.substring(0,6)} has special: ${p2Special.name}`,
                player1Cards: p1FinalHand || player1.fullHandData, player2Cards: player2.fullHandData,
                player2Special: p2Special.name,
                player1Id: player1Id, player2Id: player2Id
            };
        } else if (p1FinalHand && p2FinalHand) {
            const results = gameLogic.comparePlayerHands(p1FinalHand, p2FinalHand);
            let winnerId = null;
            if (results.overallWinner === 'player1') winnerId = player1Id;
            if (results.overallWinner === 'player2') winnerId = player2Id;
            gameOutcome = {
                winner: winnerId, reason: "Normal hand comparison.", details: results.details,
                player1Cards: p1FinalHand, player2Cards: p2FinalHand,
                player1Score: results.player1Score, player2Score: results.player2Score,
                player1Id: player1Id, player2Id: player2Id
            };
        } else {
            if (!p1FinalHand && !p1Special && (p2FinalHand || p2Special)) {
                 gameOutcome = { winner: player2Id, reason: `Player ${player1Id.substring(0,6)} did not complete.`, player1Cards: player1.fullHandData, player2Cards: p2FinalHand || p2.fullHandData, player1Id: player1Id, player2Id: player2Id };
            } else if (!p2FinalHand && !p2Special && (p1FinalHand || p1Special)) {
                 gameOutcome = { winner: player1Id, reason: `Player ${player2Id.substring(0,6)} did not complete.`, player1Cards: p1FinalHand || player1.fullHandData, player2Cards: player2.fullHandData, player1Id: player1Id, player2Id: player2Id };
            } else if (!p1FinalHand && !p1Special && !p2FinalHand && !p2Special) {
                 gameOutcome = { winner: 'draw', reason: `Both players failed to submit valid hands.`, player1Cards: player1.fullHandData, player2Cards: player2.fullHandData, player1Id: player1Id, player2Id: player2Id };
            }
             else {
                io.emit('gameError', "Error determining outcome or one player didn't submit.");
                resetGameAfterDelay();
                return;
            }
        }
        
        io.emit('gameResult', gameOutcome);
        console.log("Game ended. Results:", gameOutcome.reason, "Winner:", gameOutcome.winner);
        resetGameAfterDelay();
    }
}

function resetGameAfterDelay() {
    setTimeout(() => {
        resetGame(true); // Attempt to restart if players are still there
    }, 10000); 
}

function resetGame(tryRestart = true) {
    console.log("Resetting game.");
    gameInProgress = false;
    deck = [];
    for (const playerId in players) {
        if (players[playerId]) {
            players[playerId].hand = [];
            players[playerId].fullHandData = [];
            players[playerId].arrangedHand = null;
            players[playerId].isReady = false;
            players[playerId].specialHand = null;
        }
    }
    io.emit('gameState', { message: "Game reset. Waiting for players..." });
    broadcastPlayerOrder();

    if (tryRestart && playerOrder.length === 2) {
        console.log("Attempting to restart game with current players.");
        startGame();
    } else if (tryRestart) {
        console.log("Not enough players to auto-restart. Waiting for connections.");
    }
}

server.listen(PORT, '0.0.0.0', () => { 
    console.log(`Backend server running on port ${PORT}, allowed origin: ${FRONTEND_URL}`);
});
