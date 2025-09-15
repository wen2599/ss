// frontend/src/pages/BetHistory.tsx
import React, { useState, useEffect } from 'react';
import * as api from '../api';

interface Bet {
  id: number;
  numbers: string;
  period: string;
  bet_time: string;
  lottery_type: string;
  settled: number;
  winnings: number;
}

const BetHistory: React.FC = () => {
  const [betHistory, setBetHistory] = useState<Bet[]>([]);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchBetHistory = async () => {
      try {
        const response = await api.getBetHistory();
        if (response.data.success) {
          setBetHistory(response.data.data);
        } else {
          setError(response.data.message);
        }
      } catch (err: any) {
        setError(err.response?.data?.message || 'Failed to fetch bet history.');
      }
    };
    fetchBetHistory();
  }, []);

  return (
    <div className="container">
      <h1 className="title">投注历史</h1>
      {error && <p style={{ color: 'red' }}>{error}</p>}
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>号码</th>
            <th>期号</th>
            <th>投注时间</th>
            <th>彩票类型</th>
            <th>已结算</th>
            <th>奖金</th>
          </tr>
        </thead>
        <tbody>
          {betHistory.map(bet => (
            <tr key={bet.id}>
              <td>{bet.id}</td>
              <td>{bet.numbers}</td>
              <td>{bet.period}</td>
              <td>{bet.bet_time}</td>
              <td>{bet.lottery_type}</td>
              <td>{bet.settled ? '是' : '否'}</td>
              <td>{bet.winnings}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

export default BetHistory;
