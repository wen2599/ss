// frontend/script.js

// --- 连接后端的 WebSocket ---

// 目标：所有通信都通过 Cloudflare 代理的 https://ss.wenxiuxiu.eu.org
// Cloudflare 需要配置将 /socket.io/ 路径（或其他指定路径）的请求代理到你 Serv00 上的后端服务。
// Socket.IO 客户端默认会连接到提供当前页面的主机的 /socket.io/ 路径。
// 如果你的 Cloudflare 设置正确地将 https://ss.wenxiuxiu.eu.org/socket.io/ 代理到 Serv00 后端，
// 那么下面的连接方式是正确的。
const socket = io('https://ss.wenxiuxiu.eu.org', {
    // path: "/socket.io",  // 这是 Socket.IO 客户端和服务器的默认路径。
                           // 如果你的 Cloudflare Worker/Tunnel 将 https://ss.wenxiuxiu.eu.org/socket.io/
                           // 代理到 Serv00 后端的 /socket.io/ 路径，那么这个设置是正确的，或者可以省略。
                           // 如果你的 Cloudflare 代理了不同的路径，例如 /my-custom-socket-path/socket.io/
                           // 那这里就需要设置为 path: "/my-custom-socket-path/socket.io"
    transports: ['websocket', 'polling'] // 明确指定 transport，websocket 优先
});


// --- DOM Elements ---
const statusMessageEl = document.getElementById('status-message');
const myIdEl = document.getElementById('my-id');
const opponentIdEl = document.getElementById('opponent-id');
const myHandEl = document.getElementById('my-hand');
const opponentFrontEl = document.getElementById('opponent-front');
const opponentMiddleEl = document.getElementById('opponent-middle');
const opponentBackEl = document.getElementById('opponent-back');
const opponentSpecialEl = document.getElementById('opponent-special');
const zoneFrontEl = document.getElementById('zone-front').querySelector('.droppable');
const zoneMiddleEl = document.getElementById('zone-middle').querySelector('.droppable');
const zoneBackEl = document.getElementById('zone-back').querySelector('.droppable');
const submitArrangementBtn = document.getElementById('submit-arrangement-btn');
const gameResultEl = document.getElementById('game-result');
const mySpecialEl = document.getElementById('my-special');
const clearZoneBtns = document.querySelectorAll('.clear-zone-btn');

// --- Game State ---
let myPlayerId = null;
let myCurrentHand = []; // 存放完整的牌对象 {suit, rank, value, image, id}
let arrangedCards = {
    front: [], // 存放牌的 id
    middle: [],
    back: []
};
let selectedCardForArrangement = null; // 存放被点击的牌的 DOM 元素
let playerOrderFromServer = []; // 用于存储从服务器获取的玩家顺序

// --- Helper Functions ---
function createCardElement(cardData, isOpponentCard = false, isRevealed = false) {
    const cardEl = document.createElement('div');
    cardEl.classList.add('card');
    if (cardData && cardData.id) { // 确保 cardData 和 cardData.id 存在
        cardEl.dataset.cardId = cardData.id;
    } else {
        // 处理 cardData 或 cardData.id 未定义的情况，例如可以给一个默认的 dataset
        // console.warn("Card data or ID is undefined, using default for dataset:", cardData);
        // cardEl.dataset.cardId = `unknown-${Math.random().toString(36).substring(7)}`;
    }
    
    const img = document.createElement('img');
    // 如果是对手的牌且未揭示，或者 cardData 无效，则显示背面
    if ((isOpponentCard && !isRevealed) || !cardData || !cardData.image) {
        img.src = `images/back.png`;
        img.alt = "Card Back";
    } else {
        img.src = `images/${cardData.image}`; 
        img.alt = `${cardData.rank} of ${cardData.suit}`;
    }
    cardEl.appendChild(img);

    // 只有自己的、有效的牌可以点击
    if (!isOpponentCard && cardData && cardData.id) { 
        cardEl.addEventListener('click', () => handleCardClick(cardEl, cardData));
    }
    return cardEl;
}

function renderHand(handCards) {
    myHandEl.innerHTML = '';
    myCurrentHand = handCards; 
    handCards.forEach(card => {
        const cardEl = createCardElement(card);
        myHandEl.appendChild(cardEl);
    });
}

function renderArrangementZone(zoneEl, cardIds) {
    zoneEl.innerHTML = '';
    cardIds.forEach(cardId => {
        const cardData = myCurrentHand.find(c => c.id === cardId);
        if (cardData) {
            const cardEl = createCardElement(cardData);
            cardEl.addEventListener('click', () => {
                moveCardToHandFromZone(cardId, zoneEl.parentElement.dataset.segment);
            });
            zoneEl.appendChild(cardEl);
        }
    });
    checkArrangementComplete();
}

function renderOpponentCards(arrangement, isRevealed = false) {
    opponentFrontEl.innerHTML = '';
    opponentMiddleEl.innerHTML = '';
    opponentBackEl.innerHTML = '';

    function displaySegment(segmentCards, targetEl, numExpectedCards) {
        targetEl.innerHTML = ''; // 清空目标元素
        if (!segmentCards || !isRevealed) { 
             for (let i = 0; i < numExpectedCards ; i++) {
                // 传入一个模拟的 cardData 对象给 createCardElement，即使是背面
                const cardEl = createCardElement({ id: `back-${targetEl.id}-${i}`, image: 'back.png' }, true, false);
                targetEl.appendChild(cardEl);
            }
            return;
        }
        // 如果有牌，则显示牌面
        segmentCards.forEach(card => {
            const cardEl = createCardElement(card, true, true); // 对手的牌，已揭示
            targetEl.appendChild(cardEl);
        });
        // 如果实际牌数少于预期，用背面牌补齐
        for (let i = segmentCards.length; i < numExpectedCards; i++) {
            const cardEl = createCardElement({ id: `back-fill-${targetEl.id}-${i}`, image: 'back.png' }, true, false);
            targetEl.appendChild(cardEl);
        }
    }
    
    if (isRevealed && arrangement) { 
        if (arrangement.front && arrangement.middle && arrangement.back) { // 正常摆好的牌
            displaySegment(arrangement.front, opponentFrontEl, 3);
            displaySegment(arrangement.middle, opponentMiddleEl, 5);
            displaySegment(arrangement.back, opponentBackEl, 5);
        } else if (Array.isArray(arrangement) && arrangement.length === 13) { // 特殊牌型或未完成摆牌，显示13张
            let displayedCount = 0;
            const tempFront = [], tempMiddle = [], tempBack = [];
            arrangement.forEach(card => {
                if (displayedCount < 3) tempFront.push(card);
                else if (displayedCount < 8) tempMiddle.push(card);
                else tempBack.push(card);
                displayedCount++;
            });
            displaySegment(tempFront, opponentFrontEl, 3);
            displaySegment(tempMiddle, opponentMiddleEl, 5);
            displaySegment(tempBack, opponentBackEl, 5);
        } else { // 结构未知或不完整，全部显示背面
            displaySegment(null, opponentFrontEl, 3);
            displaySegment(null, opponentMiddleEl, 5);
            displaySegment(null, opponentBackEl, 5);
        }
    } else { // 默认或未揭示时，全部显示背面
        displaySegment(null, opponentFrontEl, 3);
        displaySegment(null, opponentMiddleEl, 5);
        displaySegment(null, opponentBackEl, 5);
    }
}


// --- Card Interaction Logic ---
function handleCardClick(cardEl, cardData) {
    if (selectedCardForArrangement) {
        selectedCardForArrangement.classList.remove('selected-for-arrangement');
    }
    selectedCardForArrangement = cardEl;
    selectedCardForArrangement.classList.add('selected-for-arrangement');
}

function addCardToZone(zoneType) {
    if (!selectedCardForArrangement) {
        alert("请先选择一张手牌！");
        return;
    }

    const cardId = selectedCardForArrangement.dataset.cardId;
    const zoneLimit = parseInt(document.getElementById(`zone-${zoneType}`).dataset.size);

    for (const seg in arrangedCards) {
        if (arrangedCards[seg].includes(cardId) && seg !== zoneType) {
            alert("这张牌已经在其他道了!");
            return;
        } else if (arrangedCards[seg].includes(cardId) && seg === zoneType) {
            alert("这张牌已经在这道了!");
            return;
        }
    }

    if (arrangedCards[zoneType].length < zoneLimit) {
        arrangedCards[zoneType].push(cardId);
        myHandEl.removeChild(selectedCardForArrangement); 
        selectedCardForArrangement = null;
        renderArrangementZone(document.getElementById(`zone-${zoneType}`).querySelector('.droppable'), arrangedCards[zoneType]);
    } else {
        alert(`${zoneType === 'front' ? '头' : (zoneType === 'middle' ? '中' : '尾')}道已满 (${zoneLimit}张)！`);
    }
}

function moveCardToHandFromZone(cardId, zoneType) {
    arrangedCards[zoneType] = arrangedCards[zoneType].filter(id => id !== cardId);
    const cardData = myCurrentHand.find(c => c.id === cardId);
    if (cardData) {
        const cardEl = createCardElement(cardData);
        myHandEl.appendChild(cardEl);
    }
    renderArrangementZone(document.getElementById(`zone-${zoneType}`).querySelector('.droppable'), arrangedCards[zoneType]);
}

clearZoneBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
        const zoneToClear = e.target.dataset.zone;
        arrangedCards[zoneToClear].forEach(cardId => {
            const cardData = myCurrentHand.find(c => c.id === cardId);
            if (cardData) {
                const cardEl = createCardElement(cardData);
                myHandEl.appendChild(cardEl);
            }
        });
        arrangedCards[zoneToClear] = [];
        renderArrangementZone(document.getElementById(`zone-${zoneToClear}`).querySelector('.droppable'), []);
    });
});


[zoneFrontEl, zoneMiddleEl, zoneBackEl].forEach(zoneDropArea => {
    zoneDropArea.parentElement.addEventListener('click', (event) => {
        if (event.target.classList.contains('clear-zone-btn') || event.target.closest('.card')) return; // 不处理清空按钮或牌的点击
        const zoneType = zoneDropArea.parentElement.dataset.segment;
        addCardToZone(zoneType);
    });
});

function checkArrangementComplete() {
    const totalArranged = arrangedCards.front.length + arrangedCards.middle.length + arrangedCards.back.length;
    if (totalArranged === 13 && 
        arrangedCards.front.length === 3 &&
        arrangedCards.middle.length === 5 &&
        arrangedCards.back.length === 5) {
        submitArrangementBtn.disabled = false;
    } else {
        submitArrangementBtn.disabled = true;
    }
}


// --- Socket Event Handlers ---
socket.on('connect', () => {
    statusMessageEl.textContent = '已连接！等待其他玩家...';
    console.log('Socket connected with id:', socket.id);
});

socket.on('connect_error', (err) => {
    statusMessageEl.textContent = `连接错误: ${err.message}。请检查网络或刷新。`;
    console.error('Socket connection error:', err);
});


socket.on('playerId', (id) => {
    myPlayerId = id;
    myIdEl.textContent = id.substring(0,6); 
});

socket.on('playerOrderUpdate', (order) => {
    console.log("Received player order update:", order);
    playerOrderFromServer = order;
    updateOpponentIdDisplay();
});


function updateOpponentIdDisplay() {
    if (myPlayerId && playerOrderFromServer.length > 0) { // 改为 > 0，即使只有一个玩家也更新
        const opponent = playerOrderFromServer.find(pId => pId !== myPlayerId);
        if (opponent) {
            opponentIdEl.textContent = opponent.substring(0, 6);
        } else if (playerOrderFromServer.length === 1 && playerOrderFromServer[0] === myPlayerId) {
            opponentIdEl.textContent = '等待对手';
        } else {
            opponentIdEl.textContent = 'N/A';
        }
    } else {
        opponentIdEl.textContent = '等待中';
    }
}


socket.on('dealCards', (data) => {
    statusMessageEl.textContent = '已发牌！请摆牌。';
    myHandEl.innerHTML = ''; 
    zoneFrontEl.innerHTML = '';
    zoneMiddleEl.innerHTML = '';
    zoneBackEl.innerHTML = '';
    arrangedCards = { front: [], middle: [], back: [] }; 
    mySpecialEl.textContent = '';
    opponentSpecialEl.textContent = '';
    gameResultEl.innerHTML = ''; 

    renderHand(data.hand);
    renderOpponentCards(null, false); // 对手牌默认显示背面
    submitArrangementBtn.disabled = true; 
    submitArrangementBtn.textContent = '提交摆牌';
    updateOpponentIdDisplay(); 
});

socket.on('arrangementAccepted', () => {
    statusMessageEl.textContent = '摆牌已提交！等待对手...';
    submitArrangementBtn.disabled = true;
    submitArrangementBtn.textContent = '已提交';
});

socket.on('gameState', (data) => {
    statusMessageEl.textContent = data.message;
    if (data.message.includes("Game reset")) {
        myHandEl.innerHTML = '等待新游戏...';
        zoneFrontEl.innerHTML = '';
        zoneMiddleEl.innerHTML = '';
        zoneBackEl.innerHTML = '';
        opponentFrontEl.innerHTML = ''; // 清空对手牌区
        opponentMiddleEl.innerHTML = '';
        opponentBackEl.innerHTML = '';
        renderOpponentCards(null, false); // 确保对手牌区在重置时显示背面
        mySpecialEl.textContent = '';
        opponentSpecialEl.textContent = '';
        gameResultEl.innerHTML = '';
        submitArrangementBtn.disabled = true;
        submitArrangementBtn.textContent = '提交摆牌';
        // playerOrderFromServer = []; // 后端会在reset时发送新的playerOrderUpdate
        updateOpponentIdDisplay();
    }
});


socket.on('gameResult', (result) => {
    statusMessageEl.textContent = '游戏结束！';
    submitArrangementBtn.disabled = true;
    gameResultEl.innerHTML = ''; 

    console.log("Game result data received:", result);

    const amIPlayer1 = myPlayerId === result.player1Id;

    if (amIPlayer1 && result.player1Special) {
        mySpecialEl.textContent = `你的特殊牌型: ${result.player1Special}`;
    } else if (!amIPlayer1 && result.player2Special) {
        mySpecialEl.textContent = `你的特殊牌型: ${result.player2Special}`;
    } else {
        mySpecialEl.textContent = ''; 
    }

    let opponentCardsToDisplay;
    let opponentSpecialText = '';
    if (amIPlayer1) { 
        opponentCardsToDisplay = result.player2Cards;
        if (result.player2Special) {
            opponentSpecialText = `对手特殊牌型: ${result.player2Special}`;
        }
        opponentIdEl.textContent = result.player2Id ? result.player2Id.substring(0,6) : '对手';
    } else { 
        opponentCardsToDisplay = result.player1Cards;
        if (result.player1Special) {
            opponentSpecialText = `对手特殊牌型: ${result.player1Special}`;
        }
        opponentIdEl.textContent = result.player1Id ? result.player1Id.substring(0,6) : '对手';
    }
    opponentSpecialEl.textContent = opponentSpecialText;
    
    // 确保即使对方牌数据不完整或为null，也调用renderOpponentCards来显示背面
    renderOpponentCards(opponentCardsToDisplay || null, true); 


    const resultDiv = document.createElement('div');
    if (result.reason) {
        const reasonP = document.createElement('p');
        reasonP.textContent = `结果: ${result.reason}`;
        resultDiv.appendChild(reasonP);
    }

    if (result.winner === myPlayerId) {
        const winP = document.createElement('p');
        winP.innerHTML = '<strong>你赢了！</strong>';
        winP.style.color = 'green';
        resultDiv.appendChild(winP);
    } else if (result.winner && result.winner !== 'draw') {
        const loseP = document.createElement('p');
        loseP.innerHTML = '<strong>你输了！</strong>';
        loseP.style.color = 'red';
        resultDiv.appendChild(loseP);
    } else if (result.winner === 'draw'){
        const drawP = document.createElement('p');
        drawP.textContent = '平局！';
        resultDiv.appendChild(drawP);
    }

    if (result.details) { 
        const getOutcomeText = (detailScore) => {
            if (amIPlayer1) { 
                if (detailScore === 1) return "<span style='color:green;'>赢</span>";
                if (detailScore === -1) return "<span style='color:red;'>输</span>";
            } else { 
                 if (detailScore === 1) return "<span style='color:red;'>输</span>"; 
                 if (detailScore === -1) return "<span style='color:green;'>赢</span>"; 
            }
            return "平";
        };
        resultDiv.innerHTML += `<p>头道: ${getOutcomeText(result.details.front)}</p>`;
        resultDiv.innerHTML += `<p>中道: ${getOutcomeText(result.details.middle)}</p>`;
        resultDiv.innerHTML += `<p>尾道: ${getOutcomeText(result.details.back)}</p>`;
        
        if (result.player1Score !== undefined && result.player2Score !== undefined) {
             const myScore = amIPlayer1 ? result.player1Score : result.player2Score;
             const opponentScore = amIPlayer1 ? result.player2Score : result.player1Score;
             resultDiv.innerHTML += `<p>总道数: 你 ${myScore} : 对手 ${opponentScore}</p>`;
        }
    }
    gameResultEl.appendChild(resultDiv);

    setTimeout(() => {
        statusMessageEl.textContent = '10秒后开始新一局或等待玩家...';
    }, 2000);
});


socket.on('gameError', (message) => {
    statusMessageEl.textContent = `错误: ${message}`;
    // alert(`游戏错误: ${message}`); // 可以取消注释以弹窗提示
    console.error("Game Error from server:", message);
});

socket.on('disconnect', (reason) => {
    statusMessageEl.textContent = `与服务器断开连接: ${reason}。请刷新页面。`;
    console.log(`Disconnected: ${reason}`);
    playerOrderFromServer = [];
    updateOpponentIdDisplay();
});


// --- Event Listeners ---
submitArrangementBtn.addEventListener('click', () => {
    if (arrangedCards.front.length !== 3 || arrangedCards.middle.length !== 5 || arrangedCards.back.length !== 5) {
        alert("每道牌的数量不正确！头道3张，中道5张，尾道5张。");
        return;
    }
    console.log("Submitting arrangement:", arrangedCards);
    socket.emit('submitArrangement', arrangedCards);
});

// Initial setup
renderOpponentCards(null, false); // 初始化时显示对手牌背面
updateOpponentIdDisplay(); 
