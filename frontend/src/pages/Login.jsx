import { useContext } from 'react'
import { AuthContext } from '../context/AuthContext'
import { Form, Input, Button, message } from 'antd'
import { useNavigate } from 'react-router-dom'

function Login() {
  const { login } = useContext(AuthContext)
  const navigate = useNavigate()

  const onFinish = async (values) => {
    try {
      await login(values.email)
      message.success('登录成功')
      navigate('/')
    } catch (error) {
      message.error(error.response?.data?.error || '登录失败')
    }
  }

  return (
    <div style={{ maxWidth: '300px', margin: '50px auto', padding: '20px', border: '1px solid #eee', borderRadius: '8px' }}>
      <h2 style={{ textAlign: 'center', marginBottom: '20px' }}>登录</h2>
      <Form onFinish={onFinish}>
        <Form.Item
          name="email"
          rules={[{ required: true, message: '请输入您的邮箱' }, { type: 'email', message: '请输入有效的邮箱地址' }]}
        >
          <Input placeholder="邮箱" />
        </Form.Item>
        <Form.Item>
          <Button type="primary" htmlType="submit" style={{ width: '100%' }}>
            登录
          </Button>
        </Form.Item>
      </Form>
    </div>
  )
}

export default Login