import React, { useState } from 'react';

function LotteryTypeModal({ isOpen, onClose, onConfirm, loading }) {
  const [selectedTypes, setSelectedTypes] = useState([]);

  const lotteryTypes = [
    { value: '香港六合彩', label: '香港六合彩 (周二、四、六开奖)' },
    { value: '新澳门六合彩', label: '新澳门六合彩 (每日开奖)' },
    { value: '老澳门六合彩', label: '老澳门六合彩 (每日开奖)' }
  ];

  const handleTypeToggle = (type) => {
    setSelectedTypes(prev => 
      prev.includes(type) 
        ? prev.filter(t => t !== type)
        : [...prev, type]
    );
  };

  const handleConfirm = () => {
    if (selectedTypes.length === 0) {
      alert('请至少选择一种彩票类型');
      return;
    }
    onConfirm(selectedTypes);
  };

  if (!isOpen) return null;

  return (
    <div style={{
      position: 'fixed',
      top: 0,
      left: 0,
      right: 0,
      bottom: 0,
      backgroundColor: 'rgba(0,0,0,0.5)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      zIndex: 1000
    }}>
      <div style={{
        backgroundColor: 'white',
        padding: '2rem',
        borderRadius: '8px',
        minWidth: '400px',
        maxWidth: '500px'
      }}>
        <h3 style={{ marginTop: 0, marginBottom: '1.5rem' }}>选择彩票类型</h3>
        
        <div style={{ marginBottom: '1.5rem' }}>
          {lotteryTypes.map(type => (
            <div key={type.value} style={{ marginBottom: '0.5rem' }}>
              <label style={{ display: 'flex', alignItems: 'center', cursor: 'pointer' }}>
                <input
                  type="checkbox"
                  checked={selectedTypes.includes(type.value)}
                  onChange={() => handleTypeToggle(type.value)}
                  style={{ marginRight: '0.5rem' }}
                />
                {type.label}
              </label>
            </div>
          ))}
        </div>

        <div style={{
          backgroundColor: '#fff3cd',
          border: '1px solid #ffeaa7',
          borderRadius: '4px',
          padding: '1rem',
          marginBottom: '1.5rem'
        }}>
          <p style={{ margin: 0, color: '#856404', fontSize: '0.9rem' }}>
            💡 提示：请查看开奖记录确认开奖号码正确后再进行结算
          </p>
        </div>

        <div style={{ display: 'flex', gap: '0.5rem', justifyContent: 'flex-end' }}>
          <button
            onClick={onClose}
            disabled={loading}
            style={{
              padding: '0.5rem 1rem',
              backgroundColor: '#6c757d',
              color: 'white',
              border: 'none',
              borderRadius: '4px',
              cursor: loading ? 'not-allowed' : 'pointer'
            }}
          >
            取消
          </button>
          <button
            onClick={handleConfirm}
            disabled={loading || selectedTypes.length === 0}
            style={{
              padding: '0.5rem 1rem',
              backgroundColor: loading ? '#6c757d' : '#007bff',
              color: 'white',
              border: 'none',
              borderRadius: '4px',
              cursor: (loading || selectedTypes.length === 0) ? 'not-allowed' : 'pointer'
            }}
          >
            {loading ? '解析中...' : '开始解析'}
          </button>
        </div>
      </div>
    </div>
  );
}

export default LotteryTypeModal;