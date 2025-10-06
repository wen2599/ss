import { useState } from 'react';

// 为上传组件添加一些样式
const uploadBoxStyle = {
  border: '2px dashed var(--card-bg)',
  borderRadius: '8px',
  padding: '2rem',
  textAlign: 'center',
  cursor: 'pointer',
  transition: 'background-color 0.2s ease',
  marginBottom: '1.5rem',
};

const uploadBoxHoverStyle = {
  backgroundColor: 'var(--secondary-bg)',
};

function EmailUpload({ onUploadSuccess }) {
  const [file, setFile] = useState(null);
  const [error, setError] = useState(null);
  const [message, setMessage] = useState('');
  const [isUploading, setIsUploading] = useState(false);
  const [isHovering, setIsHovering] = useState(false);

  const handleFileChange = (e) => {
    const selectedFile = e.target.files[0];
    if (selectedFile && selectedFile.name.endsWith('.eml')) {
      setFile(selectedFile);
      setError(null);
      setMessage(`已选择文件: ${selectedFile.name}`);
    } else {
      setFile(null);
      setError('请选择一个有效的 .eml 文件。');
      setMessage('');
    }
  };

  const handleUpload = async () => {
    if (!file) {
      setError('请先选择一个文件再上传。');
      return;
    }

    setIsUploading(true);
    setError(null);
    setMessage('');

    const formData = new FormData();
    formData.append('eml_file', file);

    try {
      const response = await fetch('/email_upload', {
        method: 'POST',
        credentials: 'include',
        body: formData,
      });

      const data = await response.json();

      if (!response.ok || data.error) {
        throw new Error(data.error || '上传失败');
      }

      setMessage(data.message || '文件上传成功并已解析！');
      setFile(null); // 清除已选择的文件
      if (onUploadSuccess) {
        onUploadSuccess(); // 调用回调函数以刷新列表
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setIsUploading(false);
    }
  };

  return (
    <div>
      <div 
        style={{ ...uploadBoxStyle, ...(isHovering ? uploadBoxHoverStyle : {}) }}
        onMouseEnter={() => setIsHovering(true)}
        onMouseLeave={() => setIsHovering(false)}
        onClick={() => document.getElementById('fileInput').click()}
      >
        <input 
          type="file" 
          id="fileInput" 
          hidden 
          accept=".eml" 
          onChange={handleFileChange}
        />
        <p>将 .eml 文件拖放到此处，或点击以选择文件</p>
        {file && <p style={{ color: 'var(--accent-color)', marginTop: '1rem' }}>{file.name}</p>}
      </div>

      <div style={{ textAlign: 'center', marginTop: '1rem' }}>
        <button onClick={handleUpload} disabled={isUploading || !file}>
          {isUploading ? '正在上传...' : '上传并解析'}
        </button>
      </div>

      {message && <p style={{ color: 'var(--green-accent)', textAlign: 'center', marginTop: '1rem' }}>{message}</p>}
      {error && <p className="error" style={{ textAlign: 'center', marginTop: '1rem' }}>{error}</p>}
    </div>
  );
}

export default EmailUpload;
