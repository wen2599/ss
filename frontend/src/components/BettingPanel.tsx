import React, { useState } from 'react';
import { placeBet } from '../api';

const BettingPanel: React.FC = () => {
  const [selectedNumbers, setSelectedNumbers] = useState<number[]>([]);
  const [lotteryType, setLotteryType] = useState('Xin Ao');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const numbers = Array.from({ length: 49 }, (_, i) => i + 1);

  const handleNumberClick = (num: number) => {
    setSuccess('');
    setError('');
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
    setSuccess('');
    setError('');
  };

  const handleSubmit = async () => {
    if (selectedNumbers.length !== 6) {
      setError('请选择6个号码。');
      return;
    }
    setLoading(true);
    setError('');
    setSuccess('');
    try {
      const response = await placeBet(selectedNumbers, lotteryType);
      if (response.data.success) {
        setSuccess(response.data.message);
        setSelectedNumbers([]);
      } else {
        setError(response.data.message);
      }
    } catch (err: any) {
      setError(err.response?.data?.message || '下注失败，请稍后再试。');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ marginTop: '20px', border: '1px solid #ccc', padding: '10px' }}>
      <h3>选择号码</h3>
      <div>
        <label htmlFor="lottery-type">彩票类型: </label>
        <select id="lottery-type" value={lotteryType} onChange={(e) => setLotteryType(e.target.value)}>
          <option value="Xin Ao">新澳</option>
          <option value="Lao Ao">老澳</option>
          <option value="Gang Cai">港彩</option>
        </select>
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: '5px', maxWidth: '400px', marginTop: '10px' }}>
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
        <button
          style={{ marginLeft: '10px' }}
          onClick={handleSubmit}
          disabled={selectedNumbers.length !== 6 || loading}
        >
          {loading ? '提交中...' : '提交投注'}
        </button>
      </div>
      {error && <p style={{ color: 'red', marginTop: '10px' }}>错误: {error}</p>}
      {success && <p style={{ color: 'green', marginTop: '10px' }}>成功: {success}</p>}
    </div>
  );
};

export default BettingPanel;
