const express = require('express');
const http = require('http');
const socketIO = require('socket.io');
const gameLogic = require('./game.js');

const app = express();
const server = http.createServer(app);
const io = socketIO(server, {
    cors: {
        origin: "*", // 允许所有来源，生产环境请配置具体域名
        methods: ["GET", "POST"]
    }
});

const PORT = process.env.PORT || 22439; // Serv00可能会设置PORT环境变量

let players = {}; // { socketId: { id: socketId, hand: [], arrangedHand: null, isReady: false, specialHand: null }, ... }
let gameInProgress = false;
let deck = [];
let playerOrder = []; // [socketId1, socketId2]

io.on('connection', (socket) => {
    console.log('A user connected:', socket.id);

    if (Object.keys(players).length >= 2) {
        socket.emit('gameError', 'Game is full. Please try again later.');
        socket.disconnect();
        return;
    }

    players[socket.id] = { id: socket.id, hand: [], arrangedHand: null, isReady: false, specialHand: null };
    playerOrder.push(socket.id);
    socket.emit('playerId', socket.id); // 发送玩家自己的ID

    if (Object.keys(players).length === 2 && !gameInProgress) {
        startGame();
    }

    socket.on('submitArrangement', (arrangedHandData) => {
        if (!players[socket.id] || !gameInProgress) return;

        // 将牌的ID转换为完整的牌对象 (从玩家原始手牌中查找)
        const playerOriginalHand = players[socket.id].fullHandData;
        const arrangedPlayerHand = {
            front: arrangedHandData.front.map(cardId => playerOriginalHand.find(c => c.id === cardId)),
            middle: arrangedHandData.middle.map(cardId => playerOriginalHand.find(c => c.id === cardId)),
            back: arrangedHandData.back.map(cardId => playerOriginalHand.find(c => c.id === cardId))
        };

        // 检查牌数是否正确
        if (arrangedPlayerHand.front.length !== 3 ||
            arrangedPlayerHand.middle.length !== 5 ||
            arrangedPlayerHand.back.length !== 5) {
            socket.emit('gameError', 'Invalid number of cards in arrangement.');
            return;
        }
        
        // 检查是否使用了重复的牌或不属于自己的牌 (简化检查，只检查总数)
        const allArrangedCards = [
            ...arrangedPlayerHand.front, 
            ...arrangedPlayerHand.middle, 
            ...arrangedPlayerHand.back
        ];
        const uniqueCardIds = new Set(allArrangedCards.map(c => c.id));
        if (uniqueCardIds.size !== 13 || allArrangedCards.some(c => c === undefined)) {
            socket.emit('gameError', 'Invalid cards submitted. Cards might be duplicated or not from your hand.');
            return;
        }


        // 检查是否是特殊牌型 (优先于普通摆牌)
        const special = gameLogic.checkSpecialHand(playerOriginalHand);
        if (special) {
            players[socket.id].specialHand = special;
            players[socket.id].arrangedHand = null; // 特殊牌型不需要普通摆牌
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

    socket.on('disconnect', () => {
        console.log('User disconnected:', socket.id);
        const playerIndex = playerOrder.indexOf(socket.id);
        if (playerIndex > -1) {
            playerOrder.splice(playerIndex, 1);
        }
        delete players[socket.id];
        
        if (Object.keys(players).length < 2 && gameInProgress) {
            io.emit('gameError', 'A player disconnected. Game reset.');
            resetGame();
        } else if (Object.keys(players).length === 0) {
            resetGame();
        }
    });
});

function startGame() {
    console.log("Starting game with players:", playerOrder);
    gameInProgress = true;
    deck = gameLogic.shuffleDeck(gameLogic.createDeck());
    const hands = gameLogic.dealCards(deck, playerOrder.length);

    playerOrder.forEach((playerId, index) => {
        players[playerId].hand = hands[index].map(card => card.id); // 只发送牌的ID
        players[playerId].fullHandData = hands[index]; // 保存完整的牌数据供服务器使用
        players[playerId].isReady = false;
        players[playerId].arrangedHand = null;
        players[playerId].specialHand = null;
        io.to(playerId).emit('dealCards', { hand: hands[index] }); // 发送完整的牌信息给客户端
    });

    io.emit('gameState', { message: "Game started! Arrange your cards." });
}

function checkBothPlayersReady() {
    const readyPlayers = playerOrder.filter(id => players[id] && players[id].isReady);
    if (readyPlayers.length === 2 && gameInProgress) {
        // 两个玩家都准备好了，开始比较
        const player1Id = playerOrder[0];
        const player2Id = playerOrder[1];

        const player1 = players[player1Id];
        const player2 = players[player2Id];
        
        let results;
        let player1FinalHand = player1.arrangedHand;
        let player2FinalHand = player2.arrangedHand;
        let player1Special = player1.specialHand;
        let player2Special = player2.specialHand;

        // 处理特殊牌型
        // 规则：特殊牌型 > 普通牌型。如果都有特殊牌型，比较特殊牌型大小（这里简化为先报先赢或按某种预设顺序）
        // 如果一方特殊，一方普通，特殊方赢。
        // 如果双方都普通，则按道比牌。

        let gameOutcome = {};

        if (player1Special && player2Special) {
            // 双方都有特殊牌型，需要定义比较规则，这里简化：player1的特殊牌型默认赢 (或者按牌型大小)
            // 实际中，特殊牌型有大小之分，如至尊清龙 > 一条龙
            gameOutcome = {
                winner: player1Id,
                reason: `Both special hands. ${player1Special.name} (P1) vs ${player2Special.name} (P2). P1 wins by default special rule.`,
                player1Cards: player1.fullHandData, // 显示全部13张
                player2Cards: player2.fullHandData,
                player1Special: player1Special.name,
                player2Special: player2Special.name,
            };
        } else if (player1Special) {
            gameOutcome = {
                winner: player1Id,
                reason: `Player ${player1Id} has special hand: ${player1Special.name}`,
                player1Cards: player1.fullHandData,
                player2Cards: player2FinalHand || player2.fullHandData, // 如果对方没摆好，也显示原始牌
                player1Special: player1Special.name,
            };
        } else if (player2Special) {
            gameOutcome = {
                winner: player2Id,
                reason: `Player ${player2Id} has special hand: ${player2Special.name}`,
                player1Cards: player1FinalHand || player1.fullHandData,
                player2Cards: player2.fullHandData,
                player2Special: player2Special.name,
            };
        } else if (player1FinalHand && player2FinalHand) {
            // 双方都普通牌型
            results = gameLogic.comparePlayerHands(player1FinalHand, player2FinalHand);
            let winnerId = null;
            if (results.overallWinner === 'player1') winnerId = player1Id;
            if (results.overallWinner === 'player2') winnerId = player2Id;
            
            gameOutcome = {
                winner: winnerId,
                reason: "Normal hand comparison.",
                details: results.details, // 各道比较结果 1 (P1赢), -1 (P2赢), 0 (平)
                player1Cards: player1FinalHand,
                player2Cards: player2FinalHand,
                player1Score: results.player1Score,
                player2Score: results.player2Score
            };
        } else {
             // 有一方未完成摆牌且无特殊牌型，则另一方不战而胜
            if (!player1FinalHand && !player1Special) {
                 gameOutcome = { winner: player2Id, reason: `Player ${player1Id} did not complete arrangement.`, player1Cards: player1.fullHandData, player2Cards: player2FinalHand || player2.fullHandData };
            } else if (!player2FinalHand && !player2Special) {
                 gameOutcome = { winner: player1Id, reason: `Player ${player2Id} did not complete arrangement.`, player1Cards: player1FinalHand || player1.fullHandData, player2Cards: player2.fullHandData };
            } else {
                // 理论上不应该到这里，除非逻辑有误
                io.emit('gameError', "Error in determining game outcome.");
                resetGameAfterDelay();
                return;
            }
        }
        
        io.emit('gameResult', gameOutcome);
        console.log("Game ended. Results:", gameOutcome);
        resetGameAfterDelay();
    }
}

function resetGameAfterDelay() {
    setTimeout(() => {
        resetGame();
        // 如果还有两个玩家，自动开始新一局
        if (Object.keys(players).length === 2) {
            startGame();
        }
    }, 10000); // 10秒后重置并尝试开始新游戏
}


function resetGame() {
    console.log("Resetting game.");
    gameInProgress = false;
    deck = [];
    // 清理玩家状态，但保留连接的玩家
    for (const playerId in players) {
        players[playerId].hand = [];
        players[playerId].fullHandData = [];
        players[playerId].arrangedHand = null;
        players[playerId].isReady = false;
        players[playerId].specialHand = null;
    }
    // playerOrder 保持不变，除非有玩家断开连接
    io.emit('gameState', { message: "Game reset. Waiting for players..." });
}


server.listen(PORT, '0.0.0.0', () => { // 监听 0.0.0.0 允许外部访问
    console.log(`Backend server running on port ${PORT}`);
});
