// --- 连接后端的 WebSocket ---
// 后端部署到 Serv00 后，需要将这里的 URL 修改为 Serv00 提供的地址和端口
// 例如: 'ws://your-username.serv00.net:PORT' (如果Serv00支持ws)
// 或者 'http://your-username.serv00.net:PORT' (如果仅用HTTP轮询，但这里用Socket.IO)
// Cloudflare Pages 部署前端时，通常需要配置后端API的绝对路径
// 在本地开发时，如果后端运行在 3000 端口:
const BACKEND_URL = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
                    ? 'http://localhost:22439'
                    : 'http://your-serv00-username.serv00.net:YOUR_SERV00_PORT'; // <--- 修改这里为你 Serv00 的后端地址和端口
const socket = io(BACKEND_URL);


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

// --- Helper Functions ---
function createCardElement(cardData, isOpponentCard = false, isRevealed = false) {
    const cardEl = document.createElement('div');
    cardEl.classList.add('card');
    cardEl.dataset.cardId = cardData.id; // 使用唯一ID
    
    const img = document.createElement('img');
    if (isOpponentCard && !isRevealed) {
        img.src = `images/back.png`;
        img.alt = "Card Back";
    } else {
        img.src = `images/${cardData.image}`; // 图片路径
        img.alt = `${cardData.rank} of ${cardData.suit}`;
    }
    cardEl.appendChild(img);

    if (!isOpponentCard) { // 自己的牌可以点击
        cardEl.addEventListener('click', () => handleCardClick(cardEl, cardData));
    }
    return cardEl;
}

function renderHand(handCards) {
    myHandEl.innerHTML = '';
    myCurrentHand = handCards; // 保存完整牌数据
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
            // 点击已摆放的牌，将其移回手牌区
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
        if (!segmentCards) { // 如果某一道是 null (例如对方还没摆好牌，结果已出)
             for (let i = 0; i < (targetEl.id.includes('front') ? 3 : 5) ; i++) {
                const cardEl = createCardElement({id: `back-${i}`, image: 'back.png'}, true, false);
                targetEl.appendChild(cardEl);
            }
            return;
        }
        segmentCards.forEach(card => {
            const cardEl = createCardElement(card, true, isRevealed);
            targetEl.appendChild(cardEl);
        });
    }
    
    if (isRevealed && arrangement) { // 明确有摆牌数据
        displaySegment(arrangement.front, opponentFrontEl);
        displaySegment(arrangement.middle, opponentMiddleEl);
        displaySegment(arrangement.back, opponentBackEl);
    } else if (arrangement && arrangement.length === 13) { // 可能是特殊牌型，显示13张背面或牌面
         let displayedCount = 0;
         arrangement.forEach(card => {
            const cardEl = createCardElement(card, true, isRevealed);
            if (displayedCount < 3) opponentFrontEl.appendChild(cardEl);
            else if (displayedCount < 8) opponentMiddleEl.appendChild(cardEl);
            else opponentBackEl.appendChild(cardEl);
            displayedCount++;
        });
    }
     else { // 默认显示背面
        for (let i = 0; i < 3; i++) opponentFrontEl.appendChild(createCardElement({id:`back-f-${i}`, image:'back.png'}, true, false));
        for (let i = 0; i < 5; i++) opponentMiddleEl.appendChild(createCardElement({id:`back-m-${i}`, image:'back.png'}, true, false));
        for (let i = 0; i < 5; i++) opponentBackEl.appendChild(createCardElement({id:`back-b-${i}`, image:'back.png'}, true, false));
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

    // 检查牌是否已在其他区域
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
        myHandEl.removeChild(selectedCardForArrangement); // 从手牌区移除
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
        //确保不是点击清空按钮触发的
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
    myIdEl.textContent = id.substring(0,6); // 显示部分ID
});

socket.on('dealCards', (data) => {
    statusMessageEl.textContent = '已发牌！请摆牌。';
    myHandEl.innerHTML = ''; // 清空旧牌
    zoneFrontEl.innerHTML = '';
    zoneMiddleEl.innerHTML = '';
    zoneBackEl.innerHTML = '';
    arrangedCards = { front: [], middle: [], back: [] }; // 重置摆牌
    mySpecialEl.textContent = '';
    gameResultEl.innerHTML = ''; // 清空上一局结果

    renderHand(data.hand);
    renderOpponentCards(null, false); // 对手牌显示背面
    opponentSpecialEl.textContent = '';
    submitArrangementBtn.disabled = true; // 初始禁用
    submitArrangementBtn.textContent = '提交摆牌';

    // 尝试自动识别特殊牌型 (这个逻辑应该在服务器端，客户端仅为提示)
    // 为简化，这里不加客户端的特殊牌型识别，完全依赖服务器
});

socket.on('arrangementAccepted', () => {
    statusMessageEl.textContent = '摆牌已提交！等待对手...';
    submitArrangementBtn.disabled = true;
    submitArrangementBtn.textContent = '已提交';
});

socket.on('gameState', (data) => {
    statusMessageEl.textContent = data.message;
    if (data.message.includes("Game reset")) {
        // 清理界面，等待新游戏
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
    }
    if (data.opponentId && data.opponentId !== myPlayerId) {
        opponentIdEl.textContent = data.opponentId.substring(0,6);
    } else if (data.players) { // 如果服务器发送了所有玩家信息
        const opponent = Object.values(data.players).find(p => p.id !== myPlayerId);
        if (opponent) {
            opponentIdEl.textContent = opponent.id.substring(0,6);
        } else {
            opponentIdEl.textContent = 'N/A';
        }
    }
});

socket.on('gameResult', (result) => {
    statusMessageEl.textContent = '游戏结束！';
    submitArrangementBtn.disabled = true;

    gameResultEl.innerHTML = ''; // 清空旧结果
    
    const p1Id = Object.keys(socket._callbacks.$dealCards ? socket._callbacks.$dealCards[0].nsp.sockets : io.sockets.sockets)[0]; // 粗略获取第一个玩家ID
    const isPlayer1 = myPlayerId === p1Id; // 这可能不准确，最好服务器直接告诉谁是P1 P2

    // 显示自己的牌
    if (result.player1Special && myPlayerId === result.player1Id) { // 假设player1Id, player2Id由服务器传来
        mySpecialEl.textContent = `你的特殊牌型: ${result.player1Special}`;
    } else if (result.player2Special && myPlayerId === result.player2Id) {
        mySpecialEl.textContent = `你的特殊牌型: ${result.player2Special}`;
    } else {
        // 正常显示自己已摆好的牌 (如果不是特殊牌型)
        // renderArrangementZone(zoneFrontEl, arrangedCards.front);
        // renderArrangementZone(zoneMiddleEl, arrangedCards.middle);
        // renderArrangementZone(zoneBackEl, arrangedCards.back);
    }


    // 显示对手的牌
    let opponentCardsToDisplay;
    let opponentIsSpecial = false;
    if (result.player1Cards && myPlayerId !== result.player1Id) { // 假设result.player1Id是P1的ID
        opponentCardsToDisplay = result.player1Cards;
        if (result.player1Special) {
            opponentSpecialEl.textContent = `对手特殊牌型: ${result.player1Special}`;
            opponentIsSpecial = true;
        }
    } else if (result.player2Cards && myPlayerId !== result.player2Id) {
        opponentCardsToDisplay = result.player2Cards;
         if (result.player2Special) {
            opponentSpecialEl.textContent = `对手特殊牌型: ${result.player2Special}`;
            opponentIsSpecial = true;
        }
    }
    
    if (opponentCardsToDisplay) {
        if (opponentIsSpecial || (opponentCardsToDisplay.front && opponentCardsToDisplay.middle && opponentCardsToDisplay.back)) {
            renderOpponentCards(opponentCardsToDisplay, true); // 摊牌
        } else if (Array.isArray(opponentCardsToDisplay) && opponentCardsToDisplay.length === 13) {
            // 如果是13张牌的数组 (例如对方未完成摆牌，或特殊牌型只传了13张牌)
            renderOpponentCards(opponentCardsToDisplay, true);
        }
    }


    // 显示比牌结果
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

    if (result.details) { // 各道比牌详情
        const getOutcomeText = (outcome) => {
            if (myPlayerId === result.player1Id) { // 我是P1
                if (outcome === 1) return "<span style='color:green;'>赢</span>";
                if (outcome === -1) return "<span style='color:red;'>输</span>";
            } else { // 我是P2
                 if (outcome === 1) return "<span style='color:red;'>输</span>";
                 if (outcome === -1) return "<span style='color:green;'>赢</span>";
            }
            return "平";
        };
        resultDiv.innerHTML += `<p>头道: ${getOutcomeText(result.details.front)}</p>`;
        resultDiv.innerHTML += `<p>中道: ${getOutcomeText(result.details.middle)}</p>`;
        resultDiv.innerHTML += `<p>尾道: ${getOutcomeText(result.details.back)}</p>`;
        if (result.player1Score !== undefined && result.player2Score !== undefined) {
             const myScore = (myPlayerId === result.player1Id) ? result.player1Score : result.player2Score;
             const opponentScore = (myPlayerId === result.player1Id) ? result.player2Score : result.player1Score;
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
    // submitArrangementBtn.disabled = false; // 允许重新提交
});

socket.on('disconnect', () => {
    statusMessageEl.textContent = '与服务器断开连接。请刷新页面。';
});


// --- Event Listeners ---
submitArrangementBtn.addEventListener('click', () => {
    // 确保牌数正确
    if (arrangedCards.front.length !== 3 || arrangedCards.middle.length !== 5 || arrangedCards.back.length !== 5) {
        alert("每道牌的数量不正确！头道3张，中道5张，尾道5张。");
        return;
    }
    socket.emit('submitArrangement', arrangedCards);
    // submitArrangementBtn.disabled = true; // 在服务器确认后再禁用
});

// 初始化对手牌区为背面
renderOpponentCards(null, false);
