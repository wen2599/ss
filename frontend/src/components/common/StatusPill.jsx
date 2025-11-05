import React from 'react';

function StatusPill({ status }) {
    const statusMap = {
        pending: { text: '待处理', class: 'pill-warning' },
        processing: { text: '处理中', class: 'pill-info' },
        processed: { text: '已结算', class: 'pill-success' },
        error: { text: '错误', class: 'pill-error' },
    };

    const currentStatus = statusMap[status] || { text: '未知', class: '' };

    return (
        <span className={`pill ${currentStatus.class}`}>
            {currentStatus.text}
        </span>
    );
}

export default StatusPill;