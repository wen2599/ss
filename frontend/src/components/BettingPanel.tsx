import React, { useState } from 'react';

const BettingPanel: React.FC = () => {
  const [selectedNumbers, setSelectedNumbers] = useState<number[]>([]);
  const numbers = Array.from({ length: 49 }, (_, i) => i + 1);

  const handleNumberClick = (num: number) => {
    if (selectedNumbers.includes(num)) {
      setSelectedNumbers(selectedNumbers.filter(n => n !== num));
    } else {
      if (selectedNumbers.length < 6) {
        setSelectedNumbers([...selectedNumbers, num].sort((a, b) => a - b));
      }
    }
  };

  const handleClear = () => {
    setSelectedNumbers([]);
  };

  return (
    <div style={{ marginTop: '20px', border: '1px solid #ccc', padding: '10px' }}>
      <h3>选择号码</h3>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: '5px', maxWidth: '400px' }}>
        {numbers.map(num => (
          <button
            key={num}
            onClick={() => handleNumberClick(num)}
            style={{
              padding: '10px',
              backgroundColor: selectedNumbers.includes(num) ? 'lightblue' : 'white',
              border: '1px solid #ccc',
              cursor: 'pointer'
            }}
            disabled={selectedNumbers.length >= 6 && !selectedNumbers.includes(num)}
          >
            {num}
          </button>
        ))}
      </div>
      <div style={{ marginTop: '10px' }}>
        <strong>已选号码: </strong>
        <span>{selectedNumbers.join(', ')}</span>
      </div>
      <div style={{ marginTop: '10px' }}>
        <button onClick={handleClear}>清除</button>
        <button style={{ marginLeft: '10px' }} disabled>提交投注</button>
      </div>
    </div>
  );
};

export default BettingPanel;
