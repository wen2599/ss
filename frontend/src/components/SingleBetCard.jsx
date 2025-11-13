import React, { useState } from 'react';
import { apiService } from '../api';

function SingleBetCard({ lineData, emailId, onUpdate, onDelete, showParseButton = true }) {
  const [isParsing, setIsParsing] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [editableData, setEditableData] = useState('');
  const [showLotteryModal, setShowLotteryModal] = useState(false);

  const handleParse = () => {
    setShowLotteryModal(true);
  };

  const handleConfirmParse = async (lotteryTypes) => {
    setIsParsing(true);
    setShowLotteryModal(false);

    try {
      const numericEmailId = parseInt(emailId, 10);
      if (isNaN(numericEmailId)) {
        throw new Error('无效的邮件ID');
      }

      const result = await apiService.parseSingleBet(
        numericEmailId,
        lineData.text,
        lineData.line_number,
        lotteryTypes[0] // 使用第一个选择的彩票类型
      );

      if (result.status === 'success') {
        onUpdate(lineData.line_number, result.data);
      } else {
        alert('解析失败: ' + (result.message || '未知错误'));
      }
    } catch (error) {
      console.error('解析失败:', error);
      alert('解析失败: ' + error.message);
    } finally {
      setIsParsing(false);
    }
  };

  // 处理保存编辑的函数
  const handleSaveEdit = async () => {
    try {
      const updatedBets = JSON.parse(editableData);
      if (!Array.isArray(updatedBets)) {
        throw new Error("JSON 格式必须是一个数组 [...]");
      }

      // 这里应该调用API保存修改
      // await apiService.updateBetBatch(lineData.batch_data.batch_id, updatedBets);
      alert('修改保存成功');
      setIsEditing(false);
    } catch (e) {
      alert("JSON 格式错误或保存失败: " + e.message);
    }
  };

  // 格式化下注目标显示
  const formatTargets = (targets) => {
    if (!Array.isArray(targets)) {
      return String(targets || '');
    }
    
    // 对于数字，用点号分隔，保持与原下注单相似的格式
    if (targets.every(target => !isNaN(target))) {
      return targets.map(num => num.toString().padStart(2, '0')).join('.');
    }
    
    // 对于生肖或其他文本，用逗号分隔
    return targets.join(', ');
  };

  // 计算总下注金额
  const calculateTotalBet = (bets) => {
    if (!bets || !Array.isArray(bets)) return 0;
    
    let total = 0;
    bets.forEach(bet => {
      const amount = Number(bet.amount) || 0;
      const targets = bet.targets || [];
      
      if (bet.bet_type === '特码' || bet.bet_type === '号码' || bet.bet_type === '平码') {
        total += amount * (Array.isArray(targets) ? targets.length : 1);
      } else {
        total += amount;
      }
    });
    
    return total;
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
        backgroundColor: lineData.is_parsed ? '#28a745' : '#6c757d',
        color: 'white',
        borderRadius: '12px',
        padding: '0.25rem 0.5rem',
        fontSize: '0.8rem',
        marginBottom: '0.5rem'
      }}>
        第 {lineData.line_number} 条 {lineData.is_parsed ? '✅ 已解析' : '❌ 未解析'}
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
      {showParseButton && (
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
                onClick={() => {
                  setEditableData(JSON.stringify(lineData.batch_data.data.bets, null, 2));
                  setIsEditing(true);
                }}
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
                onClick={() => {
                  if (window.confirm('确定要删除这条解析结果吗？')) {
                    onDelete(lineData.line_number);
                  }
                }}
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
      )}

      {/* 彩票类型选择弹窗 */}
      {showLotteryModal && (
        <LotteryTypeModal
          isOpen={showLotteryModal}
          onClose={() => setShowLotteryModal(false)}
          onConfirm={handleConfirmParse}
          loading={isParsing}
        />
      )}

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

      {/* 显示彩票类型 */}
      {lineData.batch_data.data.lottery_type && (
        <div style={{
          marginBottom: '0.5rem',
          padding: '0.25rem 0.5rem',
          backgroundColor: '#d4edda',
          borderRadius: '4px',
          display: 'inline-block'
        }}>
          <strong>彩票类型:</strong> {lineData.batch_data.data.lottery_type}
        </div>
      )}

      {/* 优化显示格式 - 显示每个下注组合的统计 */}
      <div style={{ marginBottom: '0.5rem' }}>
        {lineData.batch_data.data.bets?.map((bet, index) => (
          <div key={index} style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'flex-start',
            marginBottom: '0.5rem',
            padding: '0.5rem',
            backgroundColor: 'white',
            borderRadius: '4px',
            border: '1px solid #ddd'
          }}>
            <div style={{ flex: 1 }}>
              <div style={{ fontWeight: 'bold', marginBottom: '0.25rem' }}>
                {bet.bet_type}
              </div>
              <div style={{ 
                fontFamily: 'monospace',
                fontSize: '0.9rem',
                color: '#666',
                wordBreak: 'break-word'
              }}>
                {formatTargets(bet.targets)}
              </div>
              <div style={{ fontSize: '0.8rem', color: '#888', marginTop: '0.25rem' }}>
                共 {bet.targets?.length || 0} 个
              </div>
            </div>
            <div style={{ 
              textAlign: 'right',
              minWidth: '80px'
            }}>
              <div style={{ fontWeight: 'bold', fontSize: '1rem' }}>
                {bet.amount} 元/{bet.bet_type === '六肖' ? '注' : '个'}
              </div>
              {bet.total_bet && bet.total_bet !== bet.amount && (
                <div style={{ fontSize: '0.8rem', color: '#666' }}>
                  小计: {bet.total_bet} 元
                </div>
              )}
            </div>
          </div>
        ))}
      </div>

      {/* 结算信息 */}
      {lineData.batch_data.data.settlement && (
        <div style={{
          marginTop: '0.5rem',
          padding: '0.75rem',
          backgroundColor: '#fff3cd',
          borderRadius: '4px',
          border: '1px solid #ffeaa7'
        }}>
          <div style={{ 
            display: 'grid', 
            gridTemplateColumns: '1fr 1fr',
            gap: '0.5rem',
            fontSize: '0.9rem'
          }}>
            <div><strong>总下注:</strong> {lineData.batch_data.data.settlement.total_bet_amount || lineData.batch_data.data.total_amount || calculateTotalBet(lineData.batch_data.data.bets)} 元</div>
            <div><strong>中奖注数:</strong> {lineData.batch_data.data.settlement.winning_details?.length || 0}</div>
          </div>
          {lineData.batch_data.data.settlement.net_profits && (
            <div style={{
              marginTop: '0.5rem',
              padding: '0.5rem',
              backgroundColor: lineData.batch_data.data.settlement.net_profits.net_profit >= 0 ? '#d4edda' : '#f8d7da',
              borderRadius: '4px',
              textAlign: 'center',
              fontWeight: 'bold',
              fontSize: '1.1rem',
              color: lineData.batch_data.data.settlement.net_profits.net_profit >= 0 ? '#155724' : '#721c24'
            }}>
              {lineData.batch_data.data.settlement.net_profits.net_profit >= 0 ? '盈利' : '亏损'} {Math.abs(lineData.batch_data.data.settlement.net_profits.net_profit)} 元
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
          <div style={{
            backgroundColor: '#fff3cd',
            border: '1px solid #ffeaa7',
            borderRadius: '4px',
            padding: '0.5rem',
            marginBottom: '0.5rem'
          }}>
            <p style={{ margin: 0, fontSize: '0.9rem', color: '#856404' }}>
              💡 请直接编辑以下代表下注内容的 JSON 数据：
            </p>
          </div>
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

function LotteryTypeModal({ isOpen, onClose, onConfirm, loading }) {
  const [selectedTypes, setSelectedTypes] = useState([]);

  const lotteryTypes = [
    { value: '香港六合彩', label: '香港六合彩 (周二、四、六开奖)' },
    { value: '新澳门六合彩', label: '新澳门六合彩 (每日开奖)' },
    { value: '老澳门六合彩', label: '老澳门六合彩 (每日开奖)' }
  ];

  const handleTypeToggle = (type) => {
    setSelectedTypes([type]); // 单选，只允许选择一个
  };

  const handleConfirm = () => {
    if (selectedTypes.length === 0) {
      alert('请选择一种彩票类型');
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
                  type="radio"
                  name="lotteryType"
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
            💡 提示：请根据下注单内容选择对应的彩票类型
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

export default SingleBetCard;
