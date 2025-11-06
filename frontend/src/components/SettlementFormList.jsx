import React, { useState, useEffect } from 'react';
import { getSettlements } from '../services/api';

const SettlementFormList = () => {
  const [settlements, setSettlements] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchSettlements = async () => {
      try {
        const data = await getSettlements();
        setSettlements(data);
      } catch (err) {
        setError('无法加载结算单: ' + err.message);
      } finally {
        setIsLoading(false);
      }
    };

    fetchSettlements();
  }, []);

  if (isLoading) return <p>正在加载结算单...</p>;
  if (error) return <p className="error-message">{error}</p>;

  return (
    <div>
      <h2>结算表单</h2>
      <p>此功能正在开发中。后端API实现后，这里将显示结算单列表。</p>
      {/* 
        You can map over the `settlements` state here once the API is ready.
        e.g.,
        <ul className="content-list">
          {settlements.map(item => <li key={item.id}>...</li>)}
        </ul>
      */}
    </div>
  );
};

export default SettlementFormList;
