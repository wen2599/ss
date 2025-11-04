import React, { useState, useEffect } from 'react'
import LotteryResults from './components/LotteryResults'
import Loading from './components/Loading'
import './App.css'

function App() {
  const [results, setResults] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [lotteryType, setLotteryType] = useState('')

  const fetchResults = async (type = '') => {
    try {
      setLoading(true)
      setError(null)
      
      const url = type 
        ? `https://wenge.cloudns.ch/api/results?type=${type}&limit=20`
        : 'https://wenge.cloudns.ch/api/results?limit=20'
      
      const response = await fetch(url)
      const data = await response.json()
      
      if (data.success) {
        setResults(data.data)
      } else {
        setError(data.error || '获取数据失败')
      }
    } catch (err) {
      setError('网络请求失败: ' + err.message)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchResults()
  }, [])

  const handleTypeChange = (type) => {
    setLotteryType(type)
    fetchResults(type)
  }

  return (
    <div className="app">
      <header className="app-header">
        <h1>🎰 彩票开奖结果</h1>
        <p>实时更新最新开奖号码</p>
      </header>

      <div className="controls">
        <button 
          className={lotteryType === '' ? 'active' : ''}
          onClick={() => handleTypeChange('')}
        >
          全部
        </button>
        <button 
          className={lotteryType === '双色球' ? 'active' : ''}
          onClick={() => handleTypeChange('双色球')}
        >
          双色球
        </button>
        <button 
          className={lotteryType === '大乐透' ? 'active' : ''}
          onClick={() => handleTypeChange('大乐透')}
        >
          大乐透
        </button>
      </div>

      <main className="app-main">
        {loading && <Loading />}
        {error && (
          <div className="error-message">
            {error}
            <button onClick={() => fetchResults(lotteryType)}>重试</button>
          </div>
        )}
        {!loading && !error && (
          <LotteryResults results={results} />
        )}
      </main>

      <footer className="app-footer">
        <p>数据来源: Telegram 频道 • 最后更新: {new Date().toLocaleString()}</p>
      </footer>
    </div>
)
}

export default App