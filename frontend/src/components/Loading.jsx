import React from 'react'
import './Loading.css'

const Loading = () => {
  return (
    <div className="loading-container">
      <div className="loading-spinner">
        <div className="spinner"></div>
      </div>
      <p>正在加载开奖数据...</p>
    </div>
  )
}

export default Loading