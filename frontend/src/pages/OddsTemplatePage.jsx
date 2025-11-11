import React, { useState, useEffect } from 'react';
import { apiService } from '../api';

function OddsTemplatePage() {
  const [template, setTemplate] = useState({
    special_code_odds: '',
    flat_special_odds: '',
    serial_code_odds: '',
    even_xiao_odds: '',
    six_xiao_odds: '',
    size_single_double_odds: ''
  });
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');
  const [isSuccess, setIsSuccess] = useState(false);

  useEffect(() => {
    fetchTemplate();
  }, []);

  const fetchTemplate = async () => {
    setLoading(true);
    try {
      const response = await apiService.getOddsTemplate();
      if (response.status === 'success') {
        setTemplate(response.data);
      }
    } catch (error) {
      console.error('获取赔率模板失败:', error);
      setMessage('获取模板失败: ' + error.message);
      setIsSuccess(false);
    } finally {
      setLoading(false);
    }
  };

  const handleInputChange = (field, value) => {
    setTemplate(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const handleSave = async () => {
    setSaving(true);
    setMessage('');
    
    try {
      const response = await apiService.updateOddsTemplate(template);
      if (response.status === 'success') {
        setMessage('赔率模板保存成功！');
        setIsSuccess(true);
      } else {
        setMessage('保存失败: ' + response.message);
        setIsSuccess(false);
      }
    } catch (error) {
      console.error('保存赔率模板失败:', error);
      setMessage('保存失败: ' + error.message);
      setIsSuccess(false);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="card">
        <div style={{ textAlign: 'center', padding: '2rem' }}>
          <p>正在加载赔率模板...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="card">
      <h2>赔率模板设置</h2>
      
      <div style={{
        backgroundColor: '#e7f3ff',
        border: '1px solid #b3d9ff',
        borderRadius: '8px',
        padding: '1rem',
        marginBottom: '1.5rem'
      }}>
        <p style={{ margin: 0, fontWeight: 'bold', color: '#0066cc' }}>
          💡 提示：请设置各种玩法的赔率，邮件结算将使用您设置的赔率进行计算
        </p>
      </div>

      <div style={{ display: 'grid', gap: '1rem', maxWidth: '500px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
          <label style={{ minWidth: '120px', fontWeight: 'bold' }}>特码赔率:</label>
          <input
            type="number"
            step="0.01"
            value={template.special_code_odds || ''}
            onChange={(e) => handleInputChange('special_code_odds', e.target.value)}
            placeholder="请输入特码赔率"
            style={{ flex: 1, padding: '0.5rem', border: '1px solid #ccc', borderRadius: '4px' }}
          />
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
          <label style={{ minWidth: '120px', fontWeight: 'bold' }}>平特赔率:</label>
          <input
            type="number"
            step="0.01"
            value={template.flat_special_odds || ''}
            onChange={(e) => handleInputChange('flat_special_odds', e.target.value)}
            placeholder="请输入平特赔率"
            style={{ flex: 1, padding: '0.5rem', border: '1px solid #ccc', borderRadius: '4px' }}
          />
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
          <label style={{ minWidth: '120px', fontWeight: 'bold' }}>串码赔率:</label>
          <input
            type="number"
            step="0.01"
            value={template.serial_code_odds || ''}
            onChange={(e) => handleInputChange('serial_code_odds', e.target.value)}
            placeholder="请输入串码赔率"
            style={{ flex: 1, padding: '0.5rem', border: '1px solid #ccc', borderRadius: '4px' }}
          />
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
          <label style={{ minWidth: '120px', fontWeight: 'bold' }}>连肖赔率:</label>
          <input
            type="number"
            step="0.01"
            value={template.even_xiao_odds || ''}
            onChange={(e) => handleInputChange('even_xiao_odds', e.target.value)}
            placeholder="请输入连肖赔率"
            style={{ flex: 1, padding: '0.5rem', border: '1px solid #ccc', borderRadius: '4px' }}
          />
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
          <label style={{ minWidth: '120px', fontWeight: 'bold' }}>六肖赔率:</label>
          <input
            type="number"
            step="0.01"
            value={template.six_xiao_odds || ''}
            onChange={(e) => handleInputChange('six_xiao_odds', e.target.value)}
            placeholder="请输入六肖赔率"
            style={{ flex: 1, padding: '0.5rem', border: '1px solid #ccc', borderRadius: '4px' }}
          />
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
          <label style={{ minWidth: '120px', fontWeight: 'bold' }}>大小单双赔率:</label>
          <input
            type="number"
            step="0.01"
            value={template.size_single_double_odds || ''}
            onChange={(e) => handleInputChange('size_single_double_odds', e.target.value)}
            placeholder="请输入大小单双赔率"
            style={{ flex: 1, padding: '0.5rem', border: '1px solid #ccc', borderRadius: '4px' }}
          />
        </div>
      </div>

      <div style={{ marginTop: '1.5rem', display: 'flex', gap: '0.5rem' }}>
        <button
          onClick={handleSave}
          disabled={saving}
          style={{
            padding: '0.75rem 1.5rem',
            backgroundColor: saving ? '#6c757d' : '#007bff',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: saving ? 'not-allowed' : 'pointer',
            fontSize: '1rem'
          }}
        >
          {saving ? '保存中...' : '保存模板'}
        </button>
        
        <button
          onClick={fetchTemplate}
          disabled={loading}
          style={{
            padding: '0.75rem 1.5rem',
            backgroundColor: '#6c757d',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: 'pointer',
            fontSize: '1rem'
          }}
        >
          重新加载
        </button>
      </div>

      {message && (
        <div style={{
          marginTop: '1rem',
          padding: '0.75rem',
          borderRadius: '4px',
          backgroundColor: isSuccess ? '#d4edda' : '#f8d7da',
          color: isSuccess ? '#155724' : '#721c24',
          border: `1px solid ${isSuccess ? '#c3e6cb' : '#f5c6cb'}`
        }}>
          {message}
        </div>
      )}
    </div>
  );
}

export default OddsTemplatePage;
