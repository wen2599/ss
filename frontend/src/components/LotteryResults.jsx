import React from 'react'
import './LotteryResults.css'

const LotteryResults = ({ results }) => {
  if (results.length === 0) {
    return (
      <div className="no-results">
        <p>暂无开奖数据</p>
      </div>
    )
  }

  const formatDate = (dateString) => {
    const date = new Date(dateString)
    return date.toLocaleDateString('zh-CN', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    })
  }

  const formatNumbers = (numberString) => {
    return numberString.split('+').map((part, index) => (
      <span key={index}>
        {part.split(' ').map((num, numIndex) => (
          <span key={numIndex} className={`ball ${index === 1 ? 'special' : ''}`}>
            {num}
          </span>
        ))}
        {index === 0 && <span className="plus">+</span>}
      </span>
    ))
  }

  return (
    <div className="lottery-results">
      {results.map((result) => (
        <div key={result.id} className="result-card">
          <div className="result-header">
            <h3>{result.lottery_type}</h3>
            <span className="draw-date">{formatDate(result.draw_date)}</span>
          </div>
          <div className="result-numbers">
            {formatNumbers(result.lottery_number)}
          </div>
          <div className="result-footer">
            <span className="update-time">
              更新: {new Date(result.updated_at).toLocaleString()}
            </span>
          </div>
        </div>
      ))}
    </div>
  )
}

export default LotteryResults