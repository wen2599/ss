import React, { useState, useEffect } from 'react'

function App() {
  const [results, setResults] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const fetchResults = async () => {
    try {
      setLoading(true)
      const response = await fetch('/api/get-results?limit=50')
      const data = await response.json()
      
      if (data.success) {
        setResults(data.data)
      } else {
        setError('获取数据失败')
      }
    } catch (err) {
      setError('网络请求失败')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchResults()
    // 每30秒刷新一次数据
    const interval = setInterval(fetchResults, 30000)
    return () => clearInterval(interval)
  }, [])

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleString('zh-CN')
  }

  return (
    <div style={{ padding: '20px', maxWidth: '800px', margin: '0 auto' }}>
      <h1 style={{ textAlign: 'center', color: '#1890ff' }}>
        📮 开奖号码查询
      </h1>
      
      {error && (
        <div style={{ 
          color: '#ff4d4f', 
          textAlign: 'center', 
          padding: '10px',
          backgroundColor: '#fff2f0',
          border: '1px solid #ffccc7',
          borderRadius: '4px',
          marginBottom: '20px'
        }}>
          {error}
        </div>
      )}
      
      {loading && results.length === 0 ? (
        <div style={{ textAlign: 'center', padding: '40px' }}>
          加载中...
        </div>
      ) : (
        <div style={{ marginTop: '20px' }}>
          <div style={{ 
            display: 'flex', 
            justifyContent: 'space-between',
            alignItems: 'center',
            marginBottom: '16px',
            padding: '0 10px'
          }}>
            <span>共 {results.length} 条记录</span>
            <button 
              onClick={fetchResults}
              style={{
                padding: '6px 12px',
                backgroundColor: '#1890ff',
                color: 'white',
                border: 'none',
                borderRadius: '4px',
                cursor: 'pointer'
              }}
            >
              刷新
            </button>
          </div>
          
          <div style={{ 
            backgroundColor: '#fafafa',
            border: '1px solid #d9d9d9',
            borderRadius: '6px',
            overflow: 'hidden'
          }}>
            {results.map((item, index) => (
              <div
                key={item.id}
                style={{
                  padding: '16px',
                  borderBottom: index < results.length - 1 ? '1px solid #e8e8e8' : 'none',
                  backgroundColor: index % 2 === 0 ? 'white' : '#f9f9f9'
                }}
              >
                <div style={{ 
                  display: 'flex', 
                  justifyContent: 'space-between',
                  alignItems: 'flex-start',
                  flexWrap: 'wrap',
                  gap: '10px'
                }}>
                  <div style={{ flex: 1 }}>
                    <div style={{ fontSize: '18px', fontWeight: 'bold', color: '#fa541c' }}>
                      {item.numbers}
                    </div>
                    <div style={{ color: '#666', marginTop: '8px' }}>
                      {item.channel_name && `频道: ${item.channel_name}`}
                    </div>
                  </div>
                  <div style={{ textAlign: 'right', color: '#999' }}>
                    <div>{formatDate(item.draw_time)}</div>
                    <div style={{ fontSize: '12px', marginTop: '4px' }}>
                      {formatDate(item.created_at)}
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}

export default App
