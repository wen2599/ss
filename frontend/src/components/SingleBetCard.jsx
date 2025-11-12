// File: frontend/src/components/SingleBetCard.jsx
import React, { useState } from 'react';
import { apiService } from '../api';

function SingleBetCard({ lineData, emailId, onUpdate, onDelete }) {
  const [isParsing, setIsParsing] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [editableData, setEditableData] = useState('');

  const handleParse = async () => {
    setIsParsing(true);
    try {
      const result = await apiService.parseSingleBet(
        parseInt(emailId), 
        lineData.text, 
        lineData.line_number
      );
      
      if (result.status === 'success') {
        onUpdate(lineData.line_number, result.data);
      }
    } catch (error) {
      console.error('解析失败:', error);
      alert('解析失败: ' + error.message);
    } finally {
      setIsParsing(false);
    }
  };

  const handleEdit = () => {
    if (lineData.batch_data) {
      setEditableData(JSON.stringify(lineData.batch_data.data.bets, null, 2));
      setIsEditing(true);
    }
  };

  const handleSaveEdit = async () => {
    try {
      const updatedBets = JSON.parse(editableData);
      const updatedData = {
        ...lineData.batch_data.data,
        bets: updatedBets
      };

      await apiService.updateBetBatch(lineData.batch_data.batch_id, updatedData);
      onUpdate(lineData.line_number, { 
        batch_id: lineData.batch_data.batch_id,
        parse_result: updatedData 
      });
      setIsEditing(false);
    } catch (error) {
      alert('保存失败: ' + error.message);
    }
  };

  const handleDelete = () => {
    if (lineData.batch_data && window.confirm('确定要删除这条解析结果吗？')) {
      onDelete(lineData.line_number);
    }
  };

  return (
    <div style={{
      border: '1px solid #e0e0e0',
      borderRadius: '8px',
      padding: '1rem',
      marginBottom: '1rem',
      backgroundColor: lineData.is_parsed ? '#f8fdff' : '#f9f9f9'
    }}>
      {/* 行号标识 */}
      <div style={{
        display: 'inline-block',
        backgroundColor: '#007bff',
        color: 'white',
        borderRadius: '12px',
        padding: '0.25rem 0.5rem',
        fontSize: '0.8rem',
        marginBottom: '0.5rem'
      }}>
        第 {lineData.line_number} 条
      </div>

      {/* 原始文本 */}
      <div style={{
        backgroundColor: '#f5f5f5',
        padding: '0.75rem',
        borderRadius: '4px',
        marginBottom: '1rem',
        fontFamily: 'monospace',
        fontSize: '0.9rem',
        whiteSpace: 'pre-wrap'
      }}>
        {lineData.text}
      </div>

      {/* 操作按钮 */}
      <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
        {!lineData.is_parsed ? (
          <button
            onClick={handleParse}
            disabled={isParsing}
            style={{
              padding: '0.5rem 1rem',
              backgroundColor: isParsing ? '#6c757d' : '#28a745',
              color: 'white',
              border: 'none',
              borderRadius: '4px',
              cursor: isParsing ? 'not-allowed' : 'pointer',
              fontSize: '0.9rem'
            }}
          >
            {isParsing ? '解析中...' : '解析此条'}
          </button>
        ) : (
          <>
            <button
              onClick={handleEdit}
              style={{
                padding: '0.5rem 1rem',
                backgroundColor: '#007bff',
                color: 'white',
                border: 'none',
                borderRadius: '4px',
                cursor: 'pointer',
                fontSize: '0.9rem'
              }}
            >
              修改识别
            </button>
            <button
              onClick={handleDelete}
              style={{
                padding: '0.5rem 1rem',
                backgroundColor: '#dc3545',
                color: 'white',
                border: 'none',
                borderRadius: '4px',
                cursor: 'pointer',
                fontSize: '0.9rem'
              }}
            >
              删除解析
            </button>
          </>
        )}
      </div>

      {/* 解析结果 */}
      {lineData.is_parsed && lineData.batch_data && (
        <div style={{ marginTop: '1rem' }}>
          <div style={{
            backgroundColor: '#e8f5e8',
            border: '1px solid #4caf50',
            padding: '0.75rem',
            borderRadius: '4px'
          }}>
            <h4 style={{ margin: '0 0 0.5rem 0', color: '#2e7d32' }}>
              ✅ 解析结果
            </h4>
            
            {lineData.batch_data.data.bets?.map((bet, index) => (
              <div key={index} style={{ 
                marginBottom: '0.5rem',
                padding: '0.5rem',
                backgroundColor: 'white',
                borderRadius: '4px'
              }}>
                <div><strong>玩法:</strong> {bet.bet_type}</div>
                <div><strong>目标:</strong> {Array.isArray(bet.targets) ? bet.targets.join(', ') : bet.targets}</div>
                <div><strong>金额:</strong> {bet.amount} 元</div>
                {bet.lottery_type && <div><strong>彩种:</strong> {bet.lottery_type}</div>}
              </div>
            ))}

            {/* 结算信息 */}
            {lineData.batch_data.data.settlement && (
              <div style={{
                marginTop: '0.5rem',
                padding: '0.5rem',
                backgroundColor: '#fff3cd',
                borderRadius: '4px'
              }}>
                <div><strong>总下注:</strong> {lineData.batch_data.data.settlement.total_bet_amount} 元</div>
                <div><strong>中奖注数:</strong> {lineData.batch_data.data.settlement.winning_details?.length || 0}</div>
                {lineData.batch_data.data.settlement.net_profits && (
                  <div style={{ 
                    color: lineData.batch_data.data.settlement.net_profits.net_profit >= 0 ? 'red' : 'blue',
                    fontWeight: 'bold'
                  }}>
                    <strong>净盈亏:</strong> {lineData.batch_data.data.settlement.net_profits.net_profit >= 0 ? '+' : ''}
                    {lineData.batch_data.data.settlement.net_profits.net_profit} 元
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      )}

      {/* 编辑模式 */}
      {isEditing && (
        <div style={{ marginTop: '1rem' }}>
          <textarea
            value={editableData}
            onChange={(e) => setEditableData(e.target.value)}
            style={{
              width: '98%',
              height: '200px',
              fontFamily: 'monospace',
              fontSize: '0.9rem',
              border: '1px solid #ccc',
              padding: '8px',
              borderRadius: '4px'
            }}
          />
          <div style={{ marginTop: '0.5rem' }}>
            <button
              onClick={handleSaveEdit}
              style={{
                padding: '0.5rem 1rem',
                backgroundColor: '#28a745',
                color: 'white',
                border: 'none',
                borderRadius: '4px',
                cursor: 'pointer',
                marginRight: '0.5rem'
              }}
            >
              保存修改
            </button>
            <button
              onClick={() => setIsEditing(false)}
              style={{
                padding: '0.5rem 1rem',
                backgroundColor: '#6c757d',
                color: 'white',
                border: 'none',
                borderRadius: '4px',
                cursor: 'pointer'
              }}
            >
              取消
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

export default SingleBetCard;