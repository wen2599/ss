const SUITS = ['hearts', 'diamonds', 'clubs', 'spades'];
const RANKS = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'jack', 'queen', 'king', 'ace'];
const RANK_VALUES = {
    '2': 2, '3': 3, '4': 4, '5': 5, '6': 6, '7': 7, '8': 8, '9': 9, '10': 10,
    'jack': 11, 'queen': 12, 'king': 13, 'ace': 14
};
const RANK_NAMES_FOR_IMAGE = { // 用于图片文件名
    '10': '10', 'jack': 'jack', 'queen': 'queen', 'king': 'king', 'ace': 'ace'
};

// --- 牌型枚举 (简化) ---
const HAND_TYPES = {
    HIGH_CARD: 0,
    PAIR: 1,
    TWO_PAIR: 2,
    THREE_OF_A_KIND: 3,
    STRAIGHT: 4,
    FLUSH: 5,
    FULL_HOUSE: 6,
    FOUR_OF_A_KIND: 7,
    STRAIGHT_FLUSH: 8,
    // 特殊牌型 (十三水)
    THREE_FLUSHES: 100, // 三同花
    THREE_STRAIGHTS: 101, // 三顺子
    SIX_PAIRS_PLUS_TRIP: 102, // 六对半 (含一个三条) - 严格是5对+1三条
    FIVE_PAIRS_PLUS_TRIP: 102, // 五对三条
    FOUR_TRIPS: 103, // 四套三条 (不可能在13张牌里) - 改为凑一色等
    ALL_SMALL: 104, // 全小
    ALL_BIG: 105, // 全大
    DRAGON: 106, // 一条龙 A-K
    ROYAL_DRAGON: 107, // 至尊清龙 (同花一条龙)
    // ... 更多特殊牌型
};


function createDeck() {
    const deck = [];
    for (const suit of SUITS) {
        for (const rank of RANKS) {
            const rankNameForImage = RANK_NAMES_FOR_IMAGE[rank] || rank;
            deck.push({
                suit,
                rank,
                value: RANK_VALUES[rank],
                id: `${rank}_of_${suit}`, // 唯一ID
                image: `${rankNameForImage}_of_${suit}.png`
            });
        }
    }
    return deck;
}

function shuffleDeck(deck) {
    for (let i = deck.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [deck[i], deck[j]] = [deck[j], deck[i]];
    }
    return deck;
}

function dealCards(deck, numPlayers = 2) {
    const hands = Array(numPlayers).fill(null).map(() => []);
    for (let i = 0; i < 13 * numPlayers; i++) {
        hands[i % numPlayers].push(deck.pop());
    }
    return hands;
}

// --- 辅助函数，用于牌型判断 ---
function getRankCounts(cards) {
    const counts = {};
    for (const card of cards) {
        counts[card.value] = (counts[card.value] || 0) + 1;
    }
    return counts;
}

function isFlush(cards) {
    if (cards.length === 0) return false;
    const firstSuit = cards[0].suit;
    return cards.every(card => card.suit === firstSuit);
}

function isStraight(cards) {
    if (cards.length < 3) return false; // 至少3张才能构成顺子（头道）
    const sortedValues = cards.map(c => c.value).sort((a, b) => a - b);
    // 处理 A-2-3-4-5 (A作为1)
    if (sortedValues.length === 5 && sortedValues[4] === RANK_VALUES.ace &&
        sortedValues[0] === RANK_VALUES['2'] && sortedValues[1] === RANK_VALUES['3'] &&
        sortedValues[2] === RANK_VALUES['4'] && sortedValues[3] === RANK_VALUES['5']) {
        // 把A的值临时看作1进行顺子判断，但实际比较时还是14
        const aceAsOneValues = sortedValues.map(v => v === RANK_VALUES.ace ? 1 : v).sort((a,b) => a-b);
         for (let i = 0; i < aceAsOneValues.length - 1; i++) {
            if (aceAsOneValues[i+1] - aceAsOneValues[i] !== 1) return false;
        }
        return true; // A2345顺子
    }

    for (let i = 0; i < sortedValues.length - 1; i++) {
        if (sortedValues[i+1] - sortedValues[i] !== 1) return false;
    }
    return true;
}

// --- 核心牌型判断函数 (简化版) ---
// 返回一个对象 { type: HAND_TYPES, primaryKicker: value, secondaryKicker: value, ... }
// kicker用于同牌型比较
function evaluateHand(handCards) {
    if (!handCards || handCards.length === 0) return { type: HAND_TYPES.HIGH_CARD, kickers: [] };
    
    const cards = [...handCards].sort((a, b) => b.value - a.value); // 从大到小排序
    const rankCounts = getRankCounts(cards);
    const values = Object.keys(rankCounts).map(Number).sort((a, b) => b - a); // 出现过的牌值
    const counts = Object.values(rankCounts).sort((a, b) => b - a); // 出现次数

    const flush = isFlush(cards);
    const straight = isStraight(cards);

    if (cards.length === 3) { // 头道 (3张)
        if (counts[0] === 3) return { type: HAND_TYPES.THREE_OF_A_KIND, primaryKicker: values.find(v => rankCounts[v] === 3), kickers: cards.map(c=>c.value) };
        if (counts[0] === 2) return { type: HAND_TYPES.PAIR, primaryKicker: values.find(v => rankCounts[v] === 2), kickers: cards.map(c=>c.value) };
        return { type: HAND_TYPES.HIGH_CARD, kickers: cards.map(c=>c.value) };
    }

    if (cards.length === 5) { // 中道、尾道 (5张)
        if (straight && flush) {
            // A2345同花顺，A算5
            if (cards.some(c => c.rank === 'ace') && cards.some(c => c.rank === '2')) {
                 return { type: HAND_TYPES.STRAIGHT_FLUSH, primaryKicker: RANK_VALUES['5'], kickers: cards.map(c=>c.value).sort((a,b)=>a-b).map(v => v === 14 ? 1 : v) };
            }
            return { type: HAND_TYPES.STRAIGHT_FLUSH, primaryKicker: cards[0].value, kickers: cards.map(c=>c.value) };
        }
        if (counts[0] === 4) return { type: HAND_TYPES.FOUR_OF_A_KIND, primaryKicker: values.find(v => rankCounts[v] === 4), secondaryKicker: values.find(v => rankCounts[v] === 1), kickers: cards.map(c=>c.value) };
        if (counts[0] === 3 && counts[1] === 2) return { type: HAND_TYPES.FULL_HOUSE, primaryKicker: values.find(v => rankCounts[v] === 3), secondaryKicker: values.find(v => rankCounts[v] === 2), kickers: cards.map(c=>c.value) };
        if (flush) return { type: HAND_TYPES.FLUSH, kickers: cards.map(c => c.value) };
        if (straight) {
             // A2345顺子，A算5
            if (cards.some(c => c.rank === 'ace') && cards.some(c => c.rank === '2')) {
                 return { type: HAND_TYPES.STRAIGHT, primaryKicker: RANK_VALUES['5'], kickers: cards.map(c=>c.value).sort((a,b)=>a-b).map(v => v === 14 ? 1 : v) };
            }
            return { type: HAND_TYPES.STRAIGHT, primaryKicker: cards[0].value, kickers: cards.map(c=>c.value) };
        }
        if (counts[0] === 3) return { type: HAND_TYPES.THREE_OF_A_KIND, primaryKicker: values.find(v => rankCounts[v] === 3), kickers: cards.map(c=>c.value) };
        if (counts[0] === 2 && counts[1] === 2) {
            const pairValues = values.filter(v => rankCounts[v] === 2).sort((a,b)=>b-a);
            return { type: HAND_TYPES.TWO_PAIR, primaryKicker: pairValues[0], secondaryKicker: pairValues[1], tertiaryKicker: values.find(v => rankCounts[v] === 1), kickers: cards.map(c=>c.value) };
        }
        if (counts[0] === 2) return { type: HAND_TYPES.PAIR, primaryKicker: values.find(v => rankCounts[v] === 2), kickers: cards.map(c=>c.value) };
        return { type: HAND_TYPES.HIGH_CARD, kickers: cards.map(c => c.value) };
    }
    return { type: HAND_TYPES.HIGH_CARD, kickers: cards.map(c=>c.value) }; // 默认或无效牌数
}


// 比较两手牌 (handInfo1, handInfo2 是 evaluateHand 的结果)
// 返回: 1 (hand1赢), -1 (hand2赢), 0 (平)
function compareSingleHand(handInfo1, handInfo2) {
    if (handInfo1.type > handInfo2.type) return 1;
    if (handInfo1.type < handInfo2.type) return -1;

    // 牌型相同，比较 Kicker
    if (handInfo1.primaryKicker && handInfo2.primaryKicker) {
        if (handInfo1.primaryKicker > handInfo2.primaryKicker) return 1;
        if (handInfo1.primaryKicker < handInfo2.primaryKicker) return -1;
    }
    if (handInfo1.secondaryKicker && handInfo2.secondaryKicker) {
        if (handInfo1.secondaryKicker > handInfo2.secondaryKicker) return 1;
        if (handInfo1.secondaryKicker < handInfo2.secondaryKicker) return -1;
    }
    if (handInfo1.tertiaryKicker && handInfo2.tertiaryKicker) {
        if (handInfo1.tertiaryKicker > handInfo2.tertiaryKicker) return 1;
        if (handInfo1.tertiaryKicker < handInfo2.tertiaryKicker) return -1;
    }
    // 对于顺子、同花、高牌，需要比较所有牌
    if ([HAND_TYPES.STRAIGHT_FLUSH, HAND_TYPES.FLUSH, HAND_TYPES.STRAIGHT, HAND_TYPES.HIGH_CARD].includes(handInfo1.type)) {
        const kickers1 = handInfo1.kickers.sort((a,b)=>b-a);
        const kickers2 = handInfo2.kickers.sort((a,b)=>b-a);
        for(let i=0; i< Math.min(kickers1.length, kickers2.length); i++) {
            if (kickers1[i] > kickers2[i]) return 1;
            if (kickers1[i] < kickers2[i]) return -1;
        }
    }
    return 0; // 平局
}

// 检查玩家提交的牌是否合法（头道 < 中道 < 尾道）
function isValidArrangement(arrangedPlayerHand) {
    const frontEval = evaluateHand(arrangedPlayerHand.front);
    const middleEval = evaluateHand(arrangedPlayerHand.middle);
    const backEval = evaluateHand(arrangedPlayerHand.back);

    // console.log("Front:", frontEval);
    // console.log("Middle:", middleEval);
    // console.log("Back:", backEval);

    const middleBeatsFront = compareSingleHand(middleEval, frontEval);
    if (middleBeatsFront === -1) { // 中道输给头道
        // console.log("Invalid: Middle lost to Front");
        return false;
    }
    
    const backBeatsMiddle = compareSingleHand(backEval, middleEval);
    if (backBeatsMiddle === -1) { // 尾道输给中道
        // console.log("Invalid: Back lost to Middle");
        return false;
    }
    
    return true;
}

// 比较两个玩家的整手牌
// arrangedPlayer1Hand 和 arrangedPlayer2Hand 的结构: { front: [cards], middle: [cards], back: [cards] }
function comparePlayerHands(arrangedPlayer1Hand, arrangedPlayer2Hand) {
    const results = {
        front: compareSingleHand(evaluateHand(arrangedPlayer1Hand.front), evaluateHand(arrangedPlayer2Hand.front)),
        middle: compareSingleHand(evaluateHand(arrangedPlayer1Hand.middle), evaluateHand(arrangedPlayer2Hand.middle)),
        back: compareSingleHand(evaluateHand(arrangedPlayer1Hand.back), evaluateHand(arrangedPlayer2Hand.back)),
    };
    
    let player1Score = 0;
    let player2Score = 0;

    if (results.front === 1) player1Score++; else if (results.front === -1) player2Score++;
    if (results.middle === 1) player1Score++; else if (results.middle === -1) player2Score++;
    if (results.back === 1) player1Score++; else if (results.back === -1) player2Score++;
    
    // 简单计分，可以扩展打枪等
    let overallWinner = null;
    if (player1Score > player2Score) overallWinner = 'player1';
    else if (player2Score > player1Score) overallWinner = 'player2';
    else overallWinner = 'draw'; // 总分平

    return {
        details: results, // 各道比较结果
        player1Score,
        player2Score,
        overallWinner 
    };
}


// 简单判断特殊牌型 (示例，需要完善)
function checkSpecialHand(all13Cards) {
    // 示例：一条龙 (A-K不同花)
    const sortedValues = [...new Set(all13Cards.map(c => c.value))].sort((a, b) => a - b);
    if (sortedValues.length === 13 && sortedValues[0] === RANK_VALUES['2'] && sortedValues[12] === RANK_VALUES.ace) {
        let isDragon = true;
        for (let i = 0; i < 12; i++) {
            if (sortedValues[i+1] - sortedValues[i] !== 1) {
                isDragon = false;
                break;
            }
        }
        if (isDragon) return { type: HAND_TYPES.DRAGON, name: "一条龙" };
    }

    // 示例: 三同花
    const suitsCount = {};
    all13Cards.forEach(c => suitsCount[c.suit] = (suitsCount[c.suit] || 0) + 1);
    const suitGroups = {};
    all13Cards.forEach(c => {
        if (!suitGroups[c.suit]) suitGroups[c.suit] = [];
        suitGroups[c.suit].push(c);
    });
    
    let flushCount = 0;
    let validThreeFlush = true;
    if (Object.keys(suitGroups).length <= 3) { // 最多三种花色才可能三同花
        const frontFlushCards = [];
        const middleFlushCards = [];
        const backFlushCards = [];

        // 尝试组合 (这个逻辑比较复杂，这里仅示意)
        // 比如，黑桃有5张，红桃有5张，梅花有3张
        const suitsPresent = Object.keys(suitGroups);
        if (suitsPresent.length === 3) { // 刚好三种花色
            let s1 = suitGroups[suitsPresent[0]];
            let s2 = suitGroups[suitsPresent[1]];
            let s3 = suitGroups[suitsPresent[2]];

            // 尝试分配 3, 5, 5
            const arrangements = [
                [s1,s2,s3], [s1,s3,s2], [s2,s1,s3], [s2,s3,s1], [s3,s1,s2], [s3,s2,s1]
            ];
            for (const arr of arrangements) {
                if (arr[0].length >=3 && arr[1].length >=5 && arr[2].length >=5) {
                     // 进一步检查头中尾大小关系
                    return { type: HAND_TYPES.THREE_FLUSHES, name: "三同花" };
                }
            }
        }
    }


    // ... 其他特殊牌型判断
    return null; // 没有特殊牌型
}


module.exports = {
    createDeck,
    shuffleDeck,
    dealCards,
    evaluateHand,
    compareSingleHand,
    comparePlayerHands,
    isValidArrangement,
    checkSpecialHand,
    HAND_TYPES,
    RANK_VALUES // 导出方便前端或其他模块使用
};
