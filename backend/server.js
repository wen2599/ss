// backend/server.js
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
    },
    // path: "/socket.io", // 这是默认值，通常不需要显式设置
    transports: ['websocket', 'polling'] // 明确指定，与客户端对应
});

const PORT = process.env.PORT || 22439; // Serv00可能会设置PORT环境变量, 22439是备用

let players = {}; // socketId: { id, hand, fullHandData, arrangedHand, isReady, specialHand }
let playerOrder = []; // 存储连接的玩家ID顺序，确保游戏按顺序进行

function broadcastPlayerOrder() {
    io.emit('playerOrderUpdate', playerOrder);
    console.log("Broadcasted player order:", playerOrder);
}

io.on('connection', (socket) => {
    console.log(`User connected: ${socket.id}. Current players: ${playerOrder.length}`);

    if (playerOrder.length >= 2 && !playerOrder.includes(socket.id)) {
        socket.emit('gameError', '房间已满，请稍后再试。');
        socket.disconnect(true);
        return;
    }

    if (!players[socket.id]) {
        players[socket.id] = { id: socket.id, hand: [], fullHandData: [], arrangedHand: null, isReady: false, specialHand: null };
        if (!playerOrder.includes(socket.id)) {
            playerOrder.push(socket.id);
        }
    }
    
    socket.emit('playerId', socket.id);
    broadcastPlayerOrder();

    if (playerOrder.length === 2 && !gameInProgress) {
        startGame();
    } else if (playerOrder.length < 2) {
        io.emit('gameState', { message: `等待玩家... (${playerOrder.length}/2)` });
    }


    socket.on('submitArrangement', (arrangedHandData) => {
        console.log(`Arrangement submitted by ${socket.id}:`, arrangedHandData);
        if (!players[socket.id] || !gameInProgress) {
            console.log(`Player ${socket.id} tried to submit but not in game or game not in progress.`);
            return;
        }

        const playerOriginalHand = players[socket.id].fullHandData;
        if (!playerOriginalHand || playerOriginalHand.length === 0) {
            socket.emit('gameError', '服务器没有您的手牌数据。');
            console.error(`No fullHandData for player ${socket.id}`);
            return;
        }

        const arrangedPlayerHand = {
            front: arrangedHandData.front.map(cardId => playerOriginalHand.find(c => c.id === cardId)).filter(Boolean),
            middle: arrangedHandData.middle.map(cardId => playerOriginalHand.find(c => c.id === cardId)).filter(Boolean),
            back: arrangedHandData.back.map(cardId => playerOriginalHand.find(c => c.id === cardId)).filter(Boolean)
        };
        
        if (arrangedPlayerHand.front.length !== 3 ||
            arrangedPlayerHand.middle.length !== 5 ||
            arrangedPlayerHand.back.length !== 5) {
            socket.emit('gameError', '提交的牌数不正确。');
            return;
        }
        
        const allArrangedCardIds = [
            ...arrangedPlayerHand.front.map(c=>c.id), 
            ...arrangedPlayerHand.middle.map(c=>c.id), 
            ...arrangedPlayerHand.back.map(c=>c.id)
        ];
        const uniqueCardIds = new Set(allArrangedCardIds);
        if (uniqueCardIds.size !== 13 ) {
            socket.emit('gameError', '提交的牌有重复或非您手牌中的牌。');
            return;
        }

        const special = gameLogic.checkSpecialHand(playerOriginalHand);
        if (special) {
            players[socket.id].specialHand = special;
            players[socket.id].arrangedHand = null; 
            console.log(`Player ${socket.id} has special hand: ${special.name}`);
        } else {
             if (!gameLogic.isValidArrangement(arrangedPlayerHand)) {
                socket.emit('gameError', '无效的摆牌：牌力未按头<中<尾道顺序。');
                return;
            }
            players[socket.id].arrangedHand = arrangedPlayerHand;
            players[socket.id].specialHand = null;
        }
       
        players[socket.id].isReady = true;
        io.to(socket.id).emit('arrangementAccepted');
        console.log(`Player ${socket.id} submitted arrangement and is ready.`);

        checkBothPlayersReady();
    });

    socket.on('disconnect', (reason) => {
        console.log(`User disconnected: ${socket.id}. Reason: ${reason}. Current players before removal: ${playerOrder.length}`);
        const playerIndex = playerOrder.indexOf(socket.id);
        if (playerIndex > -1) {
            playerOrder.splice(playerIndex, 1);
        }
        delete players[socket.id];
        broadcastPlayerOrder(); 
        
        if (gameInProgress && playerOrder.length < 2) {
            io.emit('gameError', '有玩家断开连接，游戏已重置。');
            console.log("A player disconnected mid-game. Resetting game.");
            resetGame(false); 
        } else if (!gameInProgress && playerOrder.length < 2) {
            io.emit('gameState', { message: `等待玩家... (${playerOrder.length}/2)` });
        } else if (playerOrder.length === 0) {
            console.log("All players disconnected. Resetting game.");
            resetGame(false);
        }
        console.log(`Current players after removal: ${playerOrder.length}`);
    });
});

function startGame() {
    if (playerOrder.length < 2) {
        console.log("Attempted to start game, but not enough players:", playerOrder);
        io.emit('gameState', { message: `等待玩家... (${playerOrder.length}/2)` });
        return;
    }
    console.log("Starting game with players:", playerOrder[0], playerOrder[1]);
    gameInProgress = true;
    deck = gameLogic.shuffleDeck(gameLogic.createDeck());
    
    // 确保只给 playerOrder 中的玩家发牌
    const playerIdsForGame = [playerOrder[0], playerOrder[1]];
    const hands = gameLogic.dealCards(deck, playerIdsForGame.length);

    playerIdsForGame.forEach((playerId, index) => {
        if (players[playerId]) {
            players[playerId].hand = hands[index].map(card => card.id);
            players[playerId].fullHandData = hands[index]; 
            players[playerId].isReady = false;
            players[playerId].arrangedHand = null;
            players[playerId].specialHand = null;
            io.to(playerId).emit('dealCards', { hand: hands[index] }); 
            console.log(`Dealt cards to ${playerId}`);
        } else {
            console.error(`Player ${playerId} not found in players object during startGame.`);
        }
    });

    io.emit('gameState', { message: "游戏开始！请摆牌。" });
    broadcastPlayerOrder();
}

function checkBothPlayersReady() {
    if (playerOrder.length < 2 || !gameInProgress) {
        console.log("CheckBothPlayersReady: Not enough players or game not in progress.");
        return;
    }

    const player1Id = playerOrder[0];
    const player2Id = playerOrder[1];

    if (!players[player1Id] || !players[player2Id]) {
        console.log("CheckBothPlayersReady: One or both players not found in 'players' object.");
        // This might happen if a player disconnects right before this check
        return;
    }

    const player1 = players[player1Id];
    const player2 = players[player2Id];

    if (player1.isReady && player2.isReady) {
        console.log("Both players ready. Comparing hands...");
        let gameOutcome = {};
        
        const p1FinalHand = player1.arrangedHand;
        const p2FinalHand = player2.arrangedHand;
        const p1Special = player1.specialHand;
        const p2Special = player2.specialHand;

        if (p1Special && p2Special) {
            gameOutcome = { /* ... P1 wins by default ... */ 
                winner: player1Id, reason: `双方特殊牌型: ${p1Special.name} (P1) vs ${p2Special.name} (P2). P1胜 (规则简化).`,
                player1Cards: player1.fullHandData, player2Cards: player2.fullHandData,
                player1Special: p1Special.name, player2Special: p2Special.name,
                player1Id: player1Id, player2Id: player2Id
            };
        } else if (p1Special) {
            gameOutcome = { /* ... P1 special wins ... */ 
                winner: player1Id, reason: `玩家 ${player1Id.substring(0,6)} 特殊牌型: ${p1Special.name}`,
                player1Cards: player1.fullHandData, player2Cards: p2FinalHand || player2.fullHandData,
                player1Special: p1Special.name,
                player1Id: player1Id, player2Id: player2Id
            };
        } else if (p2Special) {
             gameOutcome = { /* ... P2 special wins ... */ 
                winner: player2Id, reason: `玩家 ${player2Id.substring(0,6)} 特殊牌型: ${p2Special.name}`,
                player1Cards: p1FinalHand || player1.fullHandData, player2Cards: player2.fullHandData,
                player2Special: p2Special.name,
                player1Id: player1Id, player2Id: player2Id
            };
        } else if (p1FinalHand && p2FinalHand) {
            const results = gameLogic.comparePlayerHands(p1FinalHand, p2FinalHand);
            let winnerId = null;
            if (results.overallWinner === 'player1') winnerId = player1Id;
            else if (results.overallWinner === 'player2') winnerId = player2Id;
            else winnerId = 'draw'; // explicitly set draw
            gameOutcome = { /* ... normal comparison ... */ 
                winner: winnerId, reason: "普通牌型比牌.", details: results.details,
                player1Cards: p1FinalHand, player2Cards: p2FinalHand,
                player1Score: results.player1Score, player2Score: results.player2Score,
                player1Id: player1Id, player2Id: player2Id
            };
        } else { // Handle cases where one or both didn't submit a valid normal hand and had no special
            if (!p1FinalHand && !p1Special && (p2FinalHand || p2Special)) {
                 gameOutcome = { winner: player2Id, reason: `玩家 ${player1Id.substring(0,6)} 未完成摆牌.`, player1Cards: player1.fullHandData, player2Cards: p2FinalHand || player2.fullHandData, player1Id: player1Id, player2Id: player2Id };
            } else if (!p2FinalHand && !p2Special && (p1FinalHand || p1Special)) {
                 gameOutcome = { winner: player1Id, reason: `玩家 ${player2Id.substring(0,6)} 未完成摆牌.`, player1Cards: p1FinalHand || player1.fullHandData, player2Cards: player2.fullHandData, player1Id: player1Id, player2Id: player2Id };
            } else if ((!p1FinalHand && !p1Special) && (!p2FinalHand && !p2Special)) { // Both failed
                 gameOutcome = { winner: 'draw', reason: `双方均未完成有效摆牌.`, player1Cards: player1.fullHandData, player2Cards: player2.fullHandData, player1Id: player1Id, player2Id: player2Id };
            } else { // Should not happen if logic is correct
                console.error("Unexpected state in checkBothPlayersReady outcome determination.");
                io.emit('gameError', "比牌时发生未知错误。");
                resetGameAfterDelay();
                return;
            }
        }
        
        io.emit('gameResult', gameOutcome);
        console.log("Game ended. Results:", gameOutcome.reason, "Winner:", gameOutcome.winner);
        resetGameAfterDelay();
    } else {
        if (!player1.isReady) console.log(`Player ${player1Id} is not ready.`);
        if (!player2.isReady) console.log(`Player ${player2Id} is not ready.`);
    }
}

function resetGameAfterDelay() {
    console.log("Game will reset in 10 seconds.");
    setTimeout(() => {
        resetGame(true); 
    }, 10000); 
}

function resetGame(tryRestart = true) {
    console.log("Resetting game state.");
    gameInProgress = false;
    deck = [];
    // Reset ready status for all players currently in `players` object
    // `playerOrder` will be rebuilt by new connections or kept if players are still connected
    for (const playerId in players) {
        if (players[playerId]) {
            // players[playerId].hand = []; // Don't clear hand here, dealCards will do it
            // players[playerId].fullHandData = [];
            players[playerId].arrangedHand = null;
            players[playerId].isReady = false;
            players[playerId].specialHand = null;
        }
    }
    io.emit('gameState', { message: "游戏已重置，等待玩家..." });
    broadcastPlayerOrder(); // Send current player list

    if (tryRestart && playerOrder.length === 2) {
        console.log("Attempting to restart game with current players.");
        startGame();
    } else if (tryRestart) {
        console.log(`Not enough players to auto-restart (${playerOrder.length}/2). Waiting for connections.`);
        io.emit('gameState', { message: `等待玩家... (${playerOrder.length}/2)` });
    }
}

server.listen(PORT, '0.0.0.0', () => { 
    console.log(`Backend server running on port ${PORT}, allowed origin: ${FRONTEND_URL}`);
});
