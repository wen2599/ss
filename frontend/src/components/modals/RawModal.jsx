import React from 'react';
import './RawModal.css';

/**
 * A simple modal component for displaying raw, pre-formatted text content.
 *
 * @param {{
 *   open: boolean,
 *   rawContent: string,
 *   onClose: () => void
 * }} props
 */
function RawModal({ open, rawContent, onClose }) {
  if (!open) {
    return null;
  }

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content raw-modal-content" onClick={e => e.stopPropagation()}>
        <button className="modal-close-button" onClick={onClose}>&times;</button>
        <h2>邮件原文</h2>
        <div className="raw-modal-panel">
          <pre className="raw-content-pre">
            {rawContent || '没有内容可显示。'}
          </pre>
        </div>
      </div>
    </div>
  );
}

export default RawModal;