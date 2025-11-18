// File: frontend/src/components/QuickCalibrationModal.jsx (完全重写版)
import React, { useState, useEffect } from 'react';
import { apiService } from '../api';

function QuickCalibrationModal({ isOpen, onClose, lineData, emailId, onUpdate }) {
  const [correctedAmount, setCorrectedAmount] = useState('');
  const [reason, setReason] = useState('');
  const [isSaving, setIsSaving] = useState(false);
  const [calculationHelp, setCalculationHelp] = useState('');

  useEffect(() => {
    if (isOpen && lineData) {
      const originalAmount = lineData.batch_data?.data?.total_amount ?? 0;
      setCorrectedAmount(originalAmount.toString());
      setReason('');
      setCalculationHelp('');
      
      // 自动计算帮助文本
      calculateHelpText(lineData.text, originalAmount);
    }
  }, [isOpen, lineData]);

  const calculateHelpText = (text, currentAmount) => {
    if (!text) return;
    
    // 简单的金额提取逻辑
    const amountMatches = text.match(/(\d+)\s*(元|块|闷)/g);
    if (amountMatches) {
      const amounts = amountMatches.map(match => {
        const amount = match.match(/\d+/);
        return amount ? parseInt(amount[0]) : 0;
      });
      
      const totalFromText = amounts.reduce((sum, amount) => sum + amount, 0);
      
      if (totalFromText > 0 && totalFromText !== currentAmount) {
        setCalculationHelp(`检测到文本中可能的总金额: ${totalFromText} 元`);
      }
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!correctedAmount || isNaN(parseFloat(correctedAmount))) {
      alert('请输入有效的总金额');
      return;
    }

    const amount = parseFloat(correctedAmount);
    if (amount <= 0) {
      alert('金额必须大于0');
      return;
    }

    setIsSaving(true);
    
    try {
      // 构建请求负载 - 确保所有参数都是正确的类型
      const payload = {
        email_id: parseInt(emailId, 10), // 确保是数字
        line_number: parseInt(lineData.line_number, 10),
        batch_id: parseInt(lineData.batch_data?.batch_id, 10),
        corrected_total_amount: amount,
        reason: reason.trim(),
      };

      console.log('发送快速校准请求:', payload);

      // 验证必需参数
      if (!payload.email_id || !payload.line_number || !payload.batch_id) {
        throw new Error('缺少必要的参数，请刷新页面重试');
      }

      const result = await apiService.quickCalibrateAi(payload);

      if (result.status === 'success') {
        alert(result.message || '校准成功！');
        if (onUpdate) {
          onUpdate(lineData.line_number, result.data);
        }
        onClose();
      } else {
        throw new Error(result.message || '校准失败，请稍后重试');
      }

    } catch (error) {
      console.error("快速校准失败:", error);
      
      // 更友好的错误提示
      let errorMessage = error.message;
      if (error.message.includes('Email ID is required')) {
        errorMessage = '邮件ID参数错误，请刷新页面重试';
      } else if (error.message.includes('Record not found')) {
        errorMessage = '未找到对应的解析记录，可能已被删除';
      } else if (error.message.includes('Unauthorized')) {
        errorMessage = '登录已过期，请重新登录';
      }
      
      alert('错误: ' + errorMessage);
    } finally {
      setIsSaving(false);
    }
  };

  const handleAmountChange = (value) => {
    setCorrectedAmount(value);
    
    // 实时计算帮助
    if (lineData?.text && value) {
      calculateHelpText(lineData.text, parseFloat(value));
    }
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
        width: '500px',
        maxWidth: '90%',
        maxHeight: '90vh',
        overflow: 'auto'
      }}>
        <h3 style={{ marginTop: 0, marginBottom: '1.5rem', color: '#333' }}>
          🎯 快速校准AI解析
        </h3>

        {/* 原始文本显示 */}
        <div style={{
          backgroundColor: '#f8f9fa',
          padding: '1rem',
          borderRadius: '6px',
          marginBottom: '1.5rem',
          border: '1px solid #e9ecef'
        }}>
          <div style={{ fontSize: '0.9rem', color: '#6c757d', marginBottom: '0.5rem' }}>
            <strong>原始文本:</strong>
          </div>
          <div style={{
            fontFamily: 'monospace',
            whiteSpace: 'pre-wrap',
            wordBreak: 'break-all',
            backgroundColor: '#fff',
            padding: '0.75rem',
            borderRadius: '4px',
            border: '1px solid #dee2e6',
            fontSize: '0.85rem'
          }}>
            {lineData.text}
          </div>
        </div>

        {/* 当前解析信息 */}
        {lineData.batch_data && (
          <div style={{
            backgroundColor: '#fff3cd',
            padding: '1rem',
            borderRadius: '6px',
            marginBottom: '1.5rem',
            border: '1px solid #ffeaa7'
          }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <div>
                <strong>AI当前解析总额:</strong>
                <span style={{ 
                  fontSize: '1.2rem', 
                  fontWeight: 'bold', 
                  color: '#e74c3c',
                  marginLeft: '0.5rem'
                }}>
                  {lineData.batch_data?.data?.total_amount ?? '未识别'} 元
                </span>
              </div>
              {lineData.batch_data.data?.lottery_type && (
                <span style={{
                  backgroundColor: '#e7f3ff',
                  color: '#0066cc',
                  padding: '0.25rem 0.5rem',
                  borderRadius: '12px',
                  fontSize: '0.8rem',
                  fontWeight: 'bold'
                }}>
                  {lineData.batch_data.data.lottery_type}
                </span>
              )}
            </div>
          </div>
        )}

        <form onSubmit={handleSubmit}>
          {/* 金额输入 */}
          <div style={{ marginBottom: '1.5rem' }}>
            <label style={{ display: 'block', marginBottom: '0.5rem', fontWeight: 'bold' }}>
              💰 正确的总金额 (元)
            </label>
            <input
              type="number"
              step="0.01"
              min="0.01"
              value={correctedAmount}
              onChange={(e) => handleAmountChange(e.target.value)}
              style={{
                width: '100%',
                boxSizing: 'border-box',
                padding: '0.75rem',
                fontSize: '1.1rem',
                border: '2px solid #007bff',
                borderRadius: '6px',
                backgroundColor: '#f8fdff'
              }}
              autoFocus
              required
              disabled={isSaving}
            />
            {calculationHelp && (
              <div style={{
                fontSize: '0.8rem',
                color: '#28a745',
                marginTop: '0.5rem',
                fontStyle: 'italic'
              }}>
                💡 {calculationHelp}
              </div>
            )}
          </div>

          {/* 理由输入 */}
          <div style={{ marginBottom: '1.5rem' }}>
            <label style={{ display: 'block', marginBottom: '0.5rem', fontWeight: 'bold' }}>
              📝 修正理由 (选填，帮助AI学习)
            </label>
            <input
              type="text"
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="例如: 10.22各5块, 总共是10元"
              style={{
                width: '100%',
                boxSizing: 'border-box',
                padding: '0.75rem',
                border: '1px solid #ced4da',
                borderRadius: '6px'
              }}
              disabled={isSaving}
            />
            <div style={{
              fontSize: '0.8rem',
              color: '#6c757d',
              marginTop: '0.5rem'
            }}>
              提供修正理由可以帮助AI更好地理解您的意图，提高未来解析的准确性
            </div>
          </div>

          {/* 操作按钮 */}
          <div style={{ 
            display: 'flex', 
            justifyContent: 'flex-end', 
            gap: '1rem',
            paddingTop: '1rem',
            borderTop: '1px solid #e9ecef'
          }}>
            <button
              type="button"
              onClick={onClose}
              disabled={isSaving}
              style={{
                padding: '0.75rem 1.5rem',
                backgroundColor: '#6c757d',
                color: 'white',
                border: 'none',
                borderRadius: '6px',
                cursor: isSaving ? 'not-allowed' : 'pointer',
                fontSize: '1rem',
                opacity: isSaving ? 0.6 : 1
              }}
            >
              取消
            </button>
            <button
              type="submit"
              disabled={isSaving}
              style={{
                padding: '0.75rem 1.5rem',
                backgroundColor: isSaving ? '#6c757d' : '#28a745',
                color: 'white',
                border: 'none',
                borderRadius: '6px',
                cursor: isSaving ? 'not-allowed' : 'pointer',
                fontSize: '1rem',
                fontWeight: 'bold',
                opacity: isSaving ? 0.6 : 1
              }}
            >
              {isSaving ? '🔄 提交中...' : '🚀 提交给AI重新解析'}
            </button>
          </div>
        </form>

        {/* 提示信息 */}
        <div style={{
          marginTop: '1.5rem',
          padding: '1rem',
          backgroundColor: '#e7f3ff',
          borderRadius: '6px',
          border: '1px solid #b3d9ff'
        }}>
          <div style={{ fontSize: '0.9rem', color: '#0066cc' }}>
            <strong>💡 使用提示:</strong>
            <ul style={{ margin: '0.5rem 0 0 1rem', padding: 0 }}>
              <li>输入正确的总金额后，AI会重新解析下注内容</li>
              <li>系统会自动重新计算结算结果</li>
              <li>您的修正会帮助AI学习，提高未来准确性</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}

export default QuickCalibrationModal;