// --- 连接后端的 WebSocket ---

// 由于前端和（代理后的）后端都在同一个域名 https://ss.wenxiuxiu.eu.org 下
// Socket.IO 客户端将连接到该域名。
// Cloudflare 需要配置为将 /socket.io/ 路径的请求代理到 Serv00 上的后端服务。
const BACKEND_URL = 'https://ss.wenxiuxiu.eu.org'; // 或者直接 const socket = io(); 如果path配置正确

// 如果你的Cloudflare设置是让socket.io在根路径下工作，直接用下面的
// const socket = io();

// 如果你的Cloudflare设置是让socket.io在特定的子路径下工作，
// 例如，你将 https://ss.wenxiuxiu.eu.org/my-socket-path/ 代理到Serv00的socket.io
// 那么你需要像这样指定 path:
// const socket = io('https://ss.wenxiuxiu.eu.org', {
//   path: '/my-socket-path/socket.io' // 根据你的Cloudflare Worker/Tunnel配置
// });

// 假设Cloudflare将 /socket.io/ 标准路径代理到Serv00后端
const socket = io(BACKEND_URL, {
    // 如果Cloudflare代理了 /socket.io/ 路径，那么通常不需要特别指定path
    // path: "/socket.io", // Socket.IO 客户端默认会使用 /socket.io/，如果后端服务器也使用默认路径，则此行可省略。
                           // 确保 Cloudflare Worker 或 Tunnel 将 https://ss.wenxiuxiu.eu.org/socket.io/ 
                           // 正确地路由到你 Serv00 后端的 Socket.IO 服务。
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
    cardEl.dataset.cardId = cardData.id; 
    
    const img = document.createElement('img');
    if (isOpponentCard && !isRevealed) {
        img.src = `images/back.png`;
        img.alt = "Card Back";
    } else {
        img.src = `images/${cardData.image}`; 
        img.alt = `${cardData.rank} of ${cardData.suit}`;
    }
    cardEl.appendChild(img);

    if (!isOpponentCard) { 
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

    function displaySegment(segmentCards, targetEl) {
        if (!segmentCards) { 
             const numCards = targetEl.id.includes('front') ? 3 : 5;
             for (let i = 0; i < numCards ; i++) {
                const cardEl = createCardElement({id: `back-${targetEl.id}-${i}`, image: 'back.png'}, true, false);
                targetEl.appendChild(cardEl);
            }
            return;
        }
        segmentCards.forEach(card => {
            const cardEl = createCardElement(card, true, isRevealed);
            targetEl.appendChild(cardEl);
        });
    }
    
    if (isRevealed && arrangement) { 
        if (arrangement.front && arrangement.middle && arrangement.back) {
            displaySegment(arrangement.front, opponentFrontEl);
            displaySegment(arrangement.middle, opponentMiddleEl);
            displaySegment(arrangement.back, opponentBackEl);
        } else if (Array.isArray(arrangement) && arrangement.length === 13) { 
             let displayedCount = 0;
             arrangement.forEach(card => {
                const cardEl = createCardElement(card, true, isRevealed);
                if (displayedCount < 3) opponentFrontEl.appendChild(cardEl);
                else if (displayedCount < 8) opponentMiddleEl.appendChild(cardEl);
                else opponentBackEl.appendChild(cardEl);
                displayedCount++;
            });
        } else { 
            displaySegment(null, opponentFrontEl);
            displaySegment(null, opponentMiddleEl);
            displaySegment(null, opponentBackEl);
        }
    } else { 
        displaySegment(null, opponentFrontEl);
        displaySegment(null, opponentMiddleEl);
        displaySegment(null, opponentBackEl);
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
        if (event.target.classList.contains('clear-zone-btn')) return;
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
    statusMessageEl.textContent = '已连接到服务器！等待其他玩家...';
    console.log('Connected with id:', socket.id);
});

socket.on('playerId', (id) => {
    myPlayerId = id;
    myIdEl.textContent = id.substring(0,6); 
});

// Listener for player order updates from server
socket.on('playerOrderUpdate', (order) => {
    console.log("Received player order update:", order);
    playerOrderFromServer = order;
    updateOpponentIdDisplay();
});


function updateOpponentIdDisplay() {
    if (myPlayerId && playerOrderFromServer.length > 1) {
        const opponent = playerOrderFromServer.find(pId => pId !== myPlayerId);
        if (opponent) {
            opponentIdEl.textContent = opponent.substring(0, 6);
        } else {
            opponentIdEl.textContent = 'N/A';
        }
    } else if (playerOrderFromServer.length <= 1) {
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
    renderOpponentCards(null, false); 
    submitArrangementBtn.disabled = true; 
    submitArrangementBtn.textContent = '提交摆牌';
    updateOpponentIdDisplay(); // Update opponent display on new deal
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
        opponentFrontEl.innerHTML = '';
        opponentMiddleEl.innerHTML = '';
        opponentBackEl.innerHTML = '';
        mySpecialEl.textContent = '';
        opponentSpecialEl.textContent = '';
        gameResultEl.innerHTML = '';
        submitArrangementBtn.disabled = true;
        submitArrangementBtn.textContent = '提交摆牌';
        playerOrderFromServer = []; // Reset player order on game reset
        updateOpponentIdDisplay();
    }
    // Opponent ID update is now handled by 'playerOrderUpdate'
});


socket.on('gameResult', (result) => {
    statusMessageEl.textContent = '游戏结束！';
    submitArrangementBtn.disabled = true;
    gameResultEl.innerHTML = ''; 

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
    } else { 
        opponentCardsToDisplay = result.player1Cards;
        if (result.player1Special) {
            opponentSpecialText = `对手特殊牌型: ${result.player1Special}`;
        }
    }
    opponentSpecialEl.textContent = opponentSpecialText;
    
    if (opponentCardsToDisplay) {
        renderOpponentCards(opponentCardsToDisplay, true); 
    } else { 
        renderOpponentCards(null, false);
    }

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
    alert(`游戏错误: ${message}`);
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
    socket.emit('submitArrangement', arrangedCards);
});

renderOpponentCards(null, false);
updateOpponentIdDisplay(); // Initial call
