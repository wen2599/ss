import React from 'react';

function GameTable() {
  // TODO: Implement game table display logic

  return (
    <div className="game-table">
      <h2>游戏桌面</h2>
      <div className="discarded-cards">
        {/* TODO: Display discarded cards here */}
        <p>出牌区域</p>
      </div>
      <div className="deck">
        {/* TODO: Display deck placeholder or remaining cards count */}
        <p>牌堆</p>
      </div>
      {/* Add other game table elements as needed */}
    </div>
  );
}

export default GameTable;