import React from 'react';

/**
 * A modal dialog to display raw text content.
 * Closes when the overlay or the close button is clicked.
 * @param {object} props
 * @param {boolean} props.open - Whether the modal is open.
 * @param {string} props.rawContent - The text content to display.
 * @param {function} props.onClose - The function to call when the modal should close.
 */
function RawModal({ open, rawContent, onClose }) {
  if (!open) return null;

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div
        className="modal-content"
        style={{
          maxWidth: 600,
          width: '98vw',
          minWidth: 260,
          maxHeight: '98vh',
          overflowY: 'auto',
          boxSizing: 'border-box'
        }}
        onClick={e => e.stopPropagation()}
      >
        <button className="modal-close-button" onClick={onClose}>&times;</button>
        <h2>邮件原文</h2>
        <div className="panel" style={{ background: '#f7f8fa', padding: '1em' }}>
          <pre className="raw-content-panel" style={{ fontSize: '1em', maxHeight: 400, overflow: 'auto' }}>
            {rawContent}
          </pre>
        </div>
      </div>
    </div>
  );
}

export default RawModal;