import { useState, useEffect, useContext } from 'react'
import { Table, Button, message } from 'antd'
import axios from 'axios'
import { Link } from 'react-router-dom'
import { AuthContext } from '../context/AuthContext'

const API_URL = 'http://localhost:8080/api' // Adjust this URL

function Emails() {
  const [emails, setEmails] = useState([])
  const { user } = useContext(AuthContext)

  useEffect(() => {
    const fetchEmails = async () => {
      if (!user) return;
      try {
        const token = localStorage.getItem('token')
        const response = await axios.get(`${API_URL}/email.php?action=get_emails`, {
          headers: { Authorization: `Bearer ${token}` },
        })
        setEmails(response.data)
      } catch (error) {
        message.error(error.response?.data?.error || '无法获取邮件列表')
      }
    }
    fetchEmails()
  }, [user])

  const handleRecognize = async (emailId) => {
    try {
      const token = localStorage.getItem('token')
      await axios.post(`${API_URL}/ai.php`, 
        { email_id: emailId, action: 'recognize' }, 
        { headers: { Authorization: `Bearer ${token}` } }
      );
      message.success('邮件识别任务已启动');
      // Optionally, refresh the list or update the specific item
    } catch (error) {
      message.error(error.response?.data?.error || '邮件识别失败');
    }
  };

  const columns = [
    { title: '发件人', dataIndex: 'from_email', key: 'from_email' },
    { title: '主题', dataIndex: 'subject', key: 'subject' },
    { title: '接收时间', dataIndex: 'received_at', key: 'received_at' },
    {
      title: '操作',
      key: 'action',
      render: (_, record) => (
        <span>
          <Button onClick={() => handleRecognize(record.id)} style={{ marginRight: 8 }}>
            识别
          </Button>
          <Link to={`/settlement/${record.id}`}>查看/编辑结算单</Link>
        </span>
      ),
    },
  ]

  return (
    <div style={{ padding: '20px' }}>
      <h2>邮件原文</h2>
      <Table dataSource={emails} columns={columns} rowKey="id" />
    </div>
  )
}

export default Emails