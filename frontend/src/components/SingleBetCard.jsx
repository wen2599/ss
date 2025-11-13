// File: frontend/src/components/SingleBetCard.jsx (添加总金额修改功能)
import React, { useState } from 'react';
import { apiService } from '../api';

function SingleBetCard({ lineData, emailId, onUpdate, onDelete, showParseButton = true }) {
  const [isParsing, setIsParsing] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [editableData, setEditableData] = useState('');
  const [showLotteryModal, setShowLotteryModal] = useState(false);
  const [editingTotalAmount, setEditingTotalAmount] = useState(false);
  const [saving, setSaving] = useState(false);

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
        lotteryTypes[0]
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

  // 计算当前总下注金额
  const getCurrentTotalAmount = () => {
    if (!lineData.batch_data) return 0;
    
    const settlement = lineData.batch_data.data.settlement;
    if (settlement && settlement.total_bet_amount) {
      return settlement.total_bet_amount;
    }
    
    return lineData.batch_data.data.total_amount || 0;
  };

  // 开始编辑总金额
  const startEditTotalAmount = () => {
    setEditingTotalAmount(true);
  };

  // 保存总金额修改
  const saveTotalAmountEdit = async (newTotalAmount) => {
    if (!lineData.batch_data) return;

    setSaving(true);
    try {
      const numericTotalAmount = parseFloat(newTotalAmount);
      if (isNaN(numericTotalAmount) || numericTotalAmount <= 0) {
        throw new Error('请输入有效的总金额');
      }

      const currentTotalAmount = getCurrentTotalAmount();
      const ratio = numericTotalAmount / currentTotalAmount;

      // 按比例调整每个下注项的金额
      const updatedBets = lineData.batch_data.data.bets.map(bet => {
        const newAmount = Math.round(bet.amount * ratio * 100) / 100; // 保留2位小数
        const targetCount = Array.isArray(bet.targets) ? bet.targets.length : 1;
        
        let total_bet;
        if (bet.bet_type === '特码' || bet.bet_type === '号码' || bet.bet_type === '平码') {
          total_bet = newAmount * targetCount;
        } else {
          total_bet = newAmount;
        }

        return {
          ...bet,
          amount: newAmount,
          total_bet: total_bet
        };
      });

      const updatedBatchData = {
        ...lineData.batch_data.data,
        bets: updatedBets,
        total_amount: numericTotalAmount,
        // 添加修正记录，供AI学习
        correction: {
          original_total_amount: currentTotalAmount,
          corrected_total_amount: numericTotalAmount,
          correction_reason: "用户手动修正总下注金额",
          corrected_at: new Date().toISOString(),
          original_text: lineData.text,
          adjustment_ratio: ratio
        }
      };

      // 调用API更新批次数据
      const updateResult = await apiService.updateBetBatch(
        lineData.batch_data.batch_id,
        updatedBatchData
      );

      if (updateResult.status === 'success') {
        // 触发重新结算
        const reparseResult = await apiService.parseSingleBet(
          parseInt(emailId, 10),
          lineData.text,
          lineData.line_number,
          lineData.batch_data.data.lottery_type || '香港六合彩'
        );

        if (reparseResult.status === 'success') {
          onUpdate(lineData.line_number, reparseResult.data);
          alert('总金额修改成功，已重新结算！');
        } else {
          throw new Error('重新结算失败: ' + reparseResult.message);
        }
      } else {
        throw new Error(updateResult.message || '更新失败');
      }
    } catch (error) {
      console.error('保存总金额修改失败:', error);
      alert('保存失败: ' + error.message);
    } finally {
      setEditingTotalAmount(false);
      setSaving(false);
    }
  };

  // 取消总金额编辑
  const cancelTotalAmountEdit = () => {
    setEditingTotalAmount(false);
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

  // 获取目标数量
  const getTargetCount = (targets) => {
    if (!Array.isArray(targets)) return 1;
    return targets.length;
  };

  // 计算显示的总下注金额
  const displayTotalAmount = getCurrentTotalAmount();

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

      {/* 解析结果 - 聚合显示 */}
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
                marginBottom: '1rem',
                padding: '0.5rem',
                backgroundColor: '#d4edda',
                borderRadius: '4px',
                display: 'inline-block'
              }}>
                <strong>彩票类型:</strong> {lineData.batch_data.data.lottery_type}
              </div>
            )}

            {/* 下注信息显示 - 聚合显示 */}
            <div style={{ marginBottom: '1rem' }}>
              {lineData.batch_data.data.bets?.map((bet, index) => {
                const targetCount = getTargetCount(bet.targets);
                const isNumberBet = bet.bet_type === '特码' || bet.bet_type === '号码' || bet.bet_type === '平码';
                
                return (
                  <div key={index} style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'flex-start',
                    marginBottom: '0.75rem',
                    padding: '0.75rem',
                    backgroundColor: 'white',
                    borderRadius: '6px',
                    border: '1px solid #dee2e6'
                  }}>
                    <div style={{ flex: 1 }}>
                      <div style={{ 
                        display: 'flex', 
                        alignItems: 'center', 
                        marginBottom: '0.5rem',
                        gap: '0.5rem'
                      }}>
                        <div style={{ 
                          fontWeight: 'bold', 
                          fontSize: '1rem',
                          color: '#495057'
                        }}>
                          {bet.bet_type}
                        </div>
                        <div style={{ 
                          fontSize: '0.85rem',
                          color: '#6c757d'
                        }}>
                          共 {targetCount} 个{isNumberBet ? '号码' : '目标'}
                        </div>
                      </div>
                      
                      <div style={{ 
                        fontFamily: 'monospace',
                        fontSize: '0.9rem',
                        color: '#495057',
                        wordBreak: 'break-word',
                        lineHeight: '1.4',
                        backgroundColor: '#f8f9fa',
                        padding: '0.5rem',
                        borderRadius: '4px'
                      }}>
                        {formatTargets(bet.targets)}
                      </div>
                    </div>
                    
                    <div style={{ 
                      textAlign: 'right',
                      minWidth: '120px',
                      marginLeft: '0.5rem'
                    }}>
                      <div style={{ 
                        fontSize: '1rem', 
                        fontWeight: 'bold',
                        color: '#e74c3c'
                      }}>
                        {bet.amount} 元
                      </div>
                      <div style={{ 
                        fontSize: '0.8rem',
                        color: '#7f8c8d'
                      }}>
                        {isNumberBet ? '每个号码' : '每注'}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>

            {/* 结算信息 - 添加总金额修改功能 */}
            {lineData.batch_data.data.settlement && (
              <div style={{
                marginTop: '1rem',
                padding: '1rem',
                backgroundColor: '#fff3cd',
                borderRadius: '8px',
                border: '2px solid #ffeaa7'
              }}>
                <div style={{ 
                  display: 'flex', 
                  justifyContent: 'space-between', 
                  alignItems: 'center',
                  marginBottom: '1rem'
                }}>
                  <div style={{ flex: 1 }}>
                    <div style={{ 
                      display: 'grid', 
                      gridTemplateColumns: '1fr 1fr',
                      gap: '1rem',
                      fontSize: '1rem'
                    }}>
                      <div style={{ textAlign: 'center' }}>
                        <div style={{ fontSize: '0.9rem', color: '#6c757d', marginBottom: '0.25rem' }}>总下注</div>
                        {editingTotalAmount ? (
                          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                            <input
                              type="number"
                              value={displayTotalAmount}
                              onChange={(e) => {
                                // 这里只是UI更新，实际保存时再处理
                                const updatedBatchData = {
                                  ...lineData.batch_data.data,
                                  total_amount: parseFloat(e.target.value) || 0
                                };
                                setEditableData(JSON.stringify(updatedBatchData, null, 2));
                              }}
                              style={{
                                width: '120px',
                                padding: '0.5rem',
                                border: '2px solid #007bff',
                                borderRadius: '4px',
                                textAlign: 'center',
                                fontSize: '1rem',
                                fontWeight: 'bold'
                              }}
                              autoFocus
                            />
                            <span style={{ fontSize: '1rem', fontWeight: 'bold' }}>元</span>
                          </div>
                        ) : (
                          <div style={{ fontSize: '1.25rem', fontWeight: 'bold', color: '#e74c3c' }}>
                            {displayTotalAmount} 元
                          </div>
                        )}
                      </div>
                      <div style={{ textAlign: 'center' }}>
                        <div style={{ fontSize: '0.9rem', color: '#6c757d', marginBottom: '0.25rem' }}>中奖注数</div>
                        <div style={{ fontSize: '1.25rem', fontWeight: 'bold', color: '#27ae60' }}>
                          {lineData.batch_data.data.settlement.winning_details?.length || 0}
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div style={{ marginLeft: '1rem' }}>
                    {editingTotalAmount ? (
                      <div style={{ display: 'flex', gap: '0.5rem' }}>
                        <button
                          onClick={() => saveTotalAmountEdit(displayTotalAmount)}
                          disabled={saving}
                          style={{
                            padding: '0.5rem 1rem',
                            backgroundColor: saving ? '#6c757d' : '#28a745',
                            color: 'white',
                            border: 'none',
                            borderRadius: '6px',
                            cursor: saving ? 'not-allowed' : 'pointer',
                            fontSize: '0.9rem',
                            fontWeight: 'bold'
                          }}
                        >
                          {saving ? '保存中...' : '保存'}
                        </button>
                        <button
                          onClick={cancelTotalAmountEdit}
                          style={{
                            padding: '0.5rem 1rem',
                            backgroundColor: '#6c757d',
                            color: 'white',
                            border: 'none',
                            borderRadius: '6px',
                            cursor: 'pointer',
                            fontSize: '0.9rem'
                          }}
                        >
                          取消
                        </button>
                      </div>
                    ) : (
                      <button
                        onClick={startEditTotalAmount}
                        style={{
                          padding: '0.5rem 1rem',
                          backgroundColor: '#3498db',
                          color: 'white',
                          border: 'none',
                          borderRadius: '6px',
                          cursor: 'pointer',
                          fontSize: '0.9rem',
                          fontWeight: 'bold'
                        }}
                      >
                        修改总金额
                      </button>
                    )}
                  </div>
                </div>

                {lineData.batch_data.data.settlement.net_profits && (
                  <div style={{
                    padding: '0.75rem',
                    backgroundColor: lineData.batch_data.data.settlement.net_profits.net_profit >= 0 ? '#d4edda' : '#f8d7da',
                    borderRadius: '6px',
                    textAlign: 'center',
                    fontWeight: 'bold',
                    fontSize: '1.1rem',
                    color: lineData.batch_data.data.settlement.net_profits.net_profit >= 0 ? '#155724' : '#721c24',
                    border: `1px solid ${lineData.batch_data.data.settlement.net_profits.net_profit >= 0 ? '#c3e6cb' : '#f5c6cb'}`
                  }}>
                    {lineData.batch_data.data.settlement.net_profits.net_profit >= 0 ? '🎉 盈利' : '📉 亏损'} 
                    <span style={{ 
                      fontSize: '1.25rem',
                      marginLeft: '0.5rem'
                    }}>
                      {lineData.batch_data.data.settlement.net_profits.net_profit >= 0 ? '+' : ''}
                      {lineData.batch_data.data.settlement.net_profits.net_profit} 元
                    </span>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      )}

      {/* JSON编辑模式 */}
      {isEditing && (
        <div style={{ marginTop: '1rem' }}>
          <div style={{
            backgroundColor: '#e3f2fd',
            border: '2px solid #2196f3',
            borderRadius: '8px',
            padding: '1rem',
            marginBottom: '1rem'
          }}>
            <p style={{ margin: 0, fontSize: '1rem', color: '#0d47a1', fontWeight: 'bold' }}>
              💡 JSON 编辑模式
            </p>
            <p style={{ margin: '0.5rem 0 0 0', fontSize: '0.9rem', color: '#1565c0' }}>
              请直接编辑以下代表下注内容的 JSON 数据，保存后将自动重新结算
            </p>
          </div>
          <textarea
            value={editableData}
            onChange={(e) => setEditableData(e.target.value)}
            style={{
              width: '98%',
              height: '300px',
              fontFamily: 'monospace',
              fontSize: '0.9rem',
              border: '2px solid #ccc',
              padding: '1rem',
              borderRadius: '6px',
              lineHeight: '1.5'
            }}
          />
          <div style={{ marginTop: '1rem', display: 'flex', gap: '1rem' }}>
            <button
              onClick={() => {
                // 这里需要实现JSON编辑的保存逻辑
                alert('JSON编辑功能待实现');
              }}
              style={{
                padding: '0.75rem 1.5rem',
                backgroundColor: '#28a745',
                color: 'white',
                border: 'none',
                borderRadius: '6px',
                cursor: 'pointer',
                fontSize: '1rem',
                fontWeight: 'bold'
              }}
            >
              保存修改
            </button>
            <button
              onClick={() => setIsEditing(false)}
              style={{
                padding: '0.75rem 1.5rem',
                backgroundColor: '#6c757d',
                color: 'white',
                border: 'none',
                borderRadius: '6px',
                cursor: 'pointer',
                fontSize: '1rem'
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

// LotteryTypeModal 组件保持不变...
function LotteryTypeModal({ isOpen, onClose, onConfirm, loading }) {
  const [selectedTypes, setSelectedTypes] = useState([]);

  const lotteryTypes = [
    { value: '香港六合彩', label: '香港六合彩 (周二、四、六开奖)' },
    { value: '新澳门六合彩', label: '新澳门六合彩 (每日开奖)' },
    { value: '老澳门六合彩', label: '老澳门六合彩 (每日开奖)' }
  ];

  const handleTypeToggle = (type) => {
    setSelectedTypes([type]);
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
        borderRadius: '12px',
        minWidth: '400px',
        maxWidth: '500px',
        boxShadow: '0 10px 30px rgba(0,0,0,0.3)'
      }}>
        <h3 style={{ marginTop: 0, marginBottom: '1.5rem', color: '#2c3e50' }}>
          选择彩票类型
        </h3>

        <div style={{ marginBottom: '1.5rem' }}>
          {lotteryTypes.map(type => (
            <div key={type.value} style={{ marginBottom: '0.75rem' }}>
              <label style={{ display: 'flex', alignItems: 'center', cursor: 'pointer', padding: '0.5rem' }}>
                <input
                  type="radio"
                  name="lotteryType"
                  checked={selectedTypes.includes(type.value)}
                  onChange={() => handleTypeToggle(type.value)}
                  style={{ marginRight: '0.75rem', transform: 'scale(1.2)' }}
                />
                <span style={{ fontSize: '1rem' }}>{type.label}</span>
              </label>
            </div>
          ))}
        </div>

        <div style={{
          backgroundColor: '#fff3cd',
          border: '1px solid #ffeaa7',
          borderRadius: '6px',
          padding: '1rem',
          marginBottom: '1.5rem'
        }}>
          <p style={{ margin: 0, color: '#856404', fontSize: '0.9rem' }}>
            💡 提示：请根据下注单内容选择对应的彩票类型
          </p>
        </div>

        <div style={{ display: 'flex', gap: '1rem', justifyContent: 'flex-end' }}>
          <button
            onClick={onClose}
            disabled={loading}
            style={{
              padding: '0.75rem 1.5rem',
              backgroundColor: '#6c757d',
              color: 'white',
              border: 'none',
              borderRadius: '6px',
              cursor: loading ? 'not-allowed' : 'pointer',
              fontSize: '1rem'
            }}
          >
            取消
          </button>
          <button
            onClick={handleConfirm}
            disabled={loading || selectedTypes.length === 0}
            style={{
              padding: '0.75rem 1.5rem',
              backgroundColor: loading ? '#6c757d' : '#007bff',
              color: 'white',
              border: 'none',
              borderRadius: '6px',
              cursor: (loading || selectedTypes.length === 0) ? 'not-allowed' : 'pointer',
              fontSize: '1rem',
              fontWeight: 'bold'
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
