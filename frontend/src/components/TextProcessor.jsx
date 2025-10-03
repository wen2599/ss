import React, { useState } from 'react';
import { processText } from '../services/api';

function TextProcessor() {
  const [text, setText] = useState('');
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsLoading(true);
    setError('');
    setResult(null);

    try {
      const response = await processText(text);
      setResult(response.data);
    } catch (err) {
      setError(err.message || 'An error occurred while processing the text.');
    }
    setIsLoading(false);
  };

  return (
    <div>
      <h1>文本处理器</h1>
      <form onSubmit={handleSubmit}>
        <textarea
          value={text}
          onChange={(e) => setText(e.target.value)}
          rows="10"
          cols="50"
          placeholder="在此处输入您的文本..."
        />
        <br />
        <button type="submit" disabled={isLoading}>
          {isLoading ? '正在处理...' : '处理文本'}
        </button>
      </form>
      {error && <div className="error">{error}</div>}
      {result && (
        <div>
          <h2>处理结果:</h2>
          <pre>{JSON.stringify(result, null, 2)}</pre>
        </div>
      )}
    </div>
  );
}

export default TextProcessor;
