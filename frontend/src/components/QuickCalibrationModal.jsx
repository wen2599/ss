// File: frontend/src/components/QuickCalibrationModal.jsx
import React, { useState, useEffect } from 'react';
import { apiService } from '../api';

function QuickCalibrationModal({ isOpen, onClose, lineData, emailId, onUpdate }) {
  const [correctedAmount, setCorrectedAmount] = useState('');
  const [reason, setReason] = useState('');
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    if (isOpen && lineData) {
      const originalAmount = lineData.batch_data?.data?.total_amount ?? 0;
      setCorrectedAmount(originalAmount);
      setReason('');
    }
  }, [isOpen, lineData]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsSaving(true);
    try {
      const amount = parseFloat(correctedAmount);
      if (isNaN(amount)) {
        throw new Error('请输入有效的总金额');
      }

      // 构建请求负载 - 确保 emailId 是数字
      const payload = {
        email_id: emailId, // 直接使用数字类型的 emailId
        line_number: lineData.line_number,
        batch_id: lineData.batch_data.batch_id,
        corrected_total_amount: amount,
        reason: reason,
      };

      console.log('发送快速校准请求:', payload);

      // 验证 email_id 是否有效
      if (!payload.email_id || isNaN(payload.email_id)) {
        throw new Error(`前端错误：Email ID 无效，无法发送请求。当前值: ${payload.emailId}, 类型: ${typeof payload.emailId}`);
      }

      const result = await apiService.quickCalibrateAi(payload);

      if (result.status === 'success') {
        alert(result.message || '校准成功！');
        onUpdate(lineData.line_number, result.data);
        onClose();
      } else {
        throw new Error(result.message || '校准失败');
      }

    } catch (error) {
      console.error("快速校准失败:", error);
      alert('错误: ' + error.message);
    } finally {
      setIsSaving(false);
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
        borderRadius: '8px', 
        width: '500px', 
        maxWidth: '90%' 
      }}>
        <h3 style={{ marginTop: 0 }}>快速校准AI</h3>

        <div style={{ 
          backgroundColor: '#f5f5f5', 
          padding: '0.75rem', 
          borderRadius: '4px', 
          marginBottom: '1rem', 
          fontFamily: 'monospace', 
          whiteSpace: 'pre-wrap', 
          wordBreak: 'break-all' 
        }}>
          <strong>原始文本:</strong> {lineData.text}
        </div>

        <div style={{ 
          backgroundColor: '#fff3cd', 
          padding: '0.75rem', 
          borderRadius: '4px', 
          marginBottom: '1rem' 
        }}>
          <strong>AI当前解析总额:</strong> {lineData.batch_data?.data?.total_amount ?? '未识别'} 元
        </div>

        <form onSubmit={handleSubmit}>
          <div style={{ marginBottom: '1rem' }}>
            <label><strong>正确的总金额 (元)</strong></label>
            <input 
              type="number" 
              step="0.01" 
              value={correctedAmount} 
              onChange={e => setCorrectedAmount(e.target.value)} 
              style={{ 
                width: '100%', 
                boxSizing: 'border-box', 
                padding: '0.5rem', 
                fontSize: '1.2rem',
                border: '1px solid #ced4da',
                borderRadius: '4px'
              }} 
              autoFocus 
              required 
            />
          </div>
          <div style={{ marginBottom: '1.5rem' }}>
            <label>修正理由 (选填，给AI的提示)</label>
            <input 
              type="text" 
              value={reason} 
              onChange={e => setReason(e.target.value)} 
              placeholder="例如: 10.22各5块, 总共是10元" 
              style={{ 
                width: '100%', 
                boxSizing: 'border-box', 
                padding: '0.5rem',
                border: '1px solid #ced4da',
                borderRadius: '4px'
              }} 
            />
          </div>
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '1rem' }}>
            <button 
              type="button" 
              onClick={onClose} 
              disabled={isSaving}
              style={{
                padding: '0.5rem 1rem',
                backgroundColor: '#6c757d',
                color: 'white',
                border: 'none',
                borderRadius: '4px',
                cursor: isSaving ? 'not-allowed' : 'pointer'
              }}
            >
              取消
            </button>
            <button 
              type="submit" 
              disabled={isSaving} 
              style={{ 
                backgroundColor: '#28a745', 
                color: 'white',
                padding: '0.5rem 1rem',
                border: 'none',
                borderRadius: '4px',
                cursor: isSaving ? 'not-allowed' : 'pointer'
              }}
            >
              {isSaving ? '提交中...' : '提交给AI重新解析'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default QuickCalibrationModal;