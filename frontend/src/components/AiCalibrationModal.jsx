// File: frontend/src/components/AiCalibrationModal.jsx (接收并使用 emailId)
import React, { useState, useEffect } from 'react';
import { apiService } from '../api';

function AiCalibrationModal({ isOpen, onClose, lineData, emailId, onUpdate }) {
  const [betType, setBetType] = useState('特码');
  const [targets, setTargets] = useState('');
  const [amountMode, setAmountMode] = useState('per_target');
  const [amount, setAmount] = useState('');
  const [reason, setReason] = useState('');
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    if (isOpen && lineData) {
      const initialBet = lineData.batch_data?.data?.bets?.[0] || {};
      setBetType(initialBet.bet_type || '特码');
      const initialTargets = Array.isArray(initialBet.targets) ? initialBet.targets.join('.') : '';
      setTargets(initialTargets);
      setAmount(initialBet.amount || '');
      setAmountMode('per_target');
      setReason('');
    }
  }, [isOpen, lineData]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsSaving(true);
    try {
      if (!targets || !amount) {
        throw new Error('下注目标和金额不能为空');
      }

      const payload = {
        email_id: parseInt(emailId, 10),
        line_number: lineData.line_number,
        batch_id: lineData.batch_data.batch_id,
        correction: {
          bet_type: betType,
          targets: targets,
          amount_mode: amountMode,
          amount: parseFloat(amount),
          reason: reason
        }
      };

      // 【调试步骤】检查我们即将发送的数据
      console.log('Sending calibration payload:', payload);
      
      // 检查 email_id 是否有效
      if (!payload.email_id || isNaN(payload.email_id)) {
        throw new Error('前端错误：Email ID 无效，无法发送请求。');
      }

      const result = await apiService.calibrateAiParse(payload);
      
      if (result.status === 'success') {
        alert('校准成功！');
        onUpdate(lineData.line_number, result.data);
        onClose();
      } else {
        throw new Error(result.message || '校准失败');
      }

    } catch (error) {
      console.error("Calibration failed:", error); // 在控制台打印详细错误
      alert('错误: ' + error.message);
    } finally {
      setIsSaving(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}>
      <div style={{ backgroundColor: 'white', padding: '2rem', borderRadius: '8px', width: '500px' }}>
        <h3 style={{ marginTop: 0 }}>校准AI解析</h3>
        
        <div style={{ backgroundColor: '#f5f5f5', padding: '0.75rem', borderRadius: '4px', marginBottom: '1rem', fontFamily: 'monospace' }}>
          <strong>原始文本:</strong> {lineData.text}
        </div>

        <form onSubmit={handleSubmit}>
          <div style={{ marginBottom: '1rem' }}>
            <label>玩法类型</label>
            <select value={betType} onChange={e => setBetType(e.target.value)} style={{ width: '100%', padding: '0.5rem' }}>
              <option value="特码">特码</option>
              <option value="平码">平码</option>
              <option value="连肖">连肖</option>
              <option value="六肖">六肖</option>
            </select>
          </div>
          <div style={{ marginBottom: '1rem' }}>
            <label>下注号码/目标 (用.或,分隔)</label>
            <input type="text" value={targets} onChange={e => setTargets(e.target.value)} style={{ width: '100%', padding: '0.5rem' }} />
          </div>
          <div style={{ marginBottom: '1rem' }}>
            <label>金额模式</label>
            <div style={{ display: 'flex', gap: '1rem', marginTop: '0.5rem' }}>
              <label><input type="radio" value="per_target" checked={amountMode === 'per_target'} onChange={e => setAmountMode(e.target.value)} /> 每个</label>
              <label><input type="radio" value="total" checked={amountMode === 'total'} onChange={e => setAmountMode(e.target.value)} /> 总共</label>
            </div>
          </div>
          <div style={{ marginBottom: '1rem' }}>
            <label>金额 (元)</label>
            <input type="number" value={amount} onChange={e => setAmount(e.target.value)} style={{ width: '100%', padding: '0.5rem' }} />
          </div>
          <div style={{ marginBottom: '1.5rem' }}>
            <label>修正理由 (选填，帮助AI学习)</label>
            <input type="text" value={reason} onChange={e => setReason(e.target.value)} placeholder="例如: AI未识别'块'为金额单位" style={{ width: '100%', padding: '0.5rem' }} />
          </div>
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '1rem' }}>
            <button type="button" onClick={onClose} disabled={isSaving}>取消</button>
            <button type="submit" disabled={isSaving} style={{ backgroundColor: '#28a745', color: 'white' }}>
              {isSaving ? '保存中...' : '保存并让AI学习'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default AiCalibrationModal;
