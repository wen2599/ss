import { useState, useCallback } from 'react';
import './EmailUpload.css'; // Import the new stylesheet

function EmailUpload({ onUploadSuccess }) {
  const [error, setError] = useState(null);
  const [message, setMessage] = useState('');
  const [isUploading, setIsUploading] = useState(false);
  const [isDragging, setIsDragging] = useState(false);
  const [fileName, setFileName] = useState('');

  const handleFile = useCallback((selectedFile) => {
    if (selectedFile && selectedFile.name.endsWith('.eml')) {
      setFile(selectedFile);
      setFileName(selectedFile.name);
      setError(null);
      setMessage(''); // Clear previous messages
    } else {
      setFile(null);
      setFileName('');
      setError('文件无效，请选择一个 .eml 文件。');
    }
  }, []);

  const handleFileChange = (e) => {
    handleFile(e.target.files[0]);
  };

  const handleUpload = async () => {
    if (!file) {
      setError('请先选择或拖放一个 .eml 文件。');
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
        throw new Error(data.error || '上传失败，请检查文件或网络连接。');
      }

      setMessage(data.message || '文件上传成功！正在刷新列表...');
      setFile(null); // Reset after successful upload
      setFileName('');
      if (onUploadSuccess) {
        onUploadSuccess();
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setIsUploading(false);
    }
  };

  // Drag and drop handlers
  const handleDragEnter = (e) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(true);
  };
  const handleDragLeave = (e) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);
  };
  const handleDragOver = (e) => {
    e.preventDefault(); // Necessary to allow dropping
    e.stopPropagation();
  };
  const handleDrop = (e) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);
    handleFile(e.dataTransfer.files[0]);
  };

  // Need to hold the file in state for the upload function
  const [file, setFile] = useState(null);

  return (
    <div className="upload-container">
        <div 
            className={`upload-area ${isDragging ? 'dragging' : ''}`}
            onDragEnter={handleDragEnter}
            onDragLeave={handleDragLeave}
            onDragOver={handleDragOver}
            onDrop={handleDrop}
            onClick={() => document.getElementById('fileInput').click()}
        >
            <input 
                type="file" 
                id="fileInput" 
                hidden 
                accept=".eml" 
                onChange={handleFileChange}
                disabled={isUploading}
            />
            <div className="upload-instructions">
                <span className="upload-icon">📤</span>
                {fileName ? (
                    <p>已选择文件: <strong>{fileName}</strong></p>
                ) : (
                    <p>将 .eml 文件拖放到此处，或<strong>点击选择</strong></p>
                )}
                <span className="upload-hint">仅支持 .eml 格式的邮件文件</span>
            </div>
        </div>
        
        <div className="upload-actions">
            {error && <div className="error message-box">{error}</div>}
            {message && <div className="success message-box">{message}</div>}
            <button onClick={handleUpload} disabled={isUploading || !file}>
                {isUploading ? '上传中...' : '上传并解析'}
            </button>
        </div>
    </div>
  );
}

export default EmailUpload;
