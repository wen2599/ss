import { useState, useEffect, useContext } from 'react'
import { Table, message } from 'antd'
import axios from 'axios'
import { AuthContext } from '../context/AuthContext'

const API_URL = 'http://localhost:8080/api' // Adjust this URL

function Dashboard() {
  const [lotteryResults, setLotteryResults] = useState([])
  const { user } = useContext(AuthContext)

  useEffect(() => {
    const fetchResults = async () => {
      if (!user) return;
      try {
        const token = localStorage.getItem('token')
        const response = await axios.get(`${API_URL}/lottery.php?action=get_results`, {
          headers: { Authorization: `Bearer ${token}` },
        })
        setLotteryResults(response.data)
      } catch (error) {
        message.error(error.response?.data?.error || '无法获取开奖记录')
      }
    }
    fetchResults()
  }, [user])

  const columns = [
    { title: '期号', dataIndex: 'issue', key: 'issue' },
    { title: '开奖日期', dataIndex: 'date', key: 'date' },
    {
      title: '开奖号码',
      dataIndex: 'numbers',
      key: 'numbers',
      render: (numbers) => JSON.parse(numbers).join(', '),
    },
    { title: '特码', dataIndex: 'special', key: 'special' },
  ]

  return (
    <div style={{ padding: '20px' }}>
      <h2>开奖记录</h2>
      <Table dataSource={lotteryResults} columns={columns} rowKey="id" />
    </div>
  )
}

export default Dashboard