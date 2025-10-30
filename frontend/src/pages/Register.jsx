import { useContext } from 'react'
import { AuthContext } from '../context/AuthContext'
import { Form, Input, Button, message } from 'antd'
import { Link } from 'react-router-dom'

function Register() {
  const { register } = useContext(AuthContext)

  const onFinish = async (values) => {
    try {
      await register(values.email)
      message.success('注册请求已发送。请检查您的邮箱以完成验证。')
    } catch (error) {
      message.error(error.response?.data?.error || '注册失败')
    }
  }

  return (
    <div style={{ maxWidth: '300px', margin: '50px auto', padding: '20px', border: '1px solid #eee', borderRadius: '8px' }}>
      <h2 style={{ textAlign: 'center', marginBottom: '20px' }}>注册</h2>
      <Form onFinish={onFinish}>
        <Form.Item
          name="email"
          rules={[{ required: true, message: '请输入您的邮箱' }, { type: 'email', message: '请输入有效的邮箱地址' }]}
        >
          <Input placeholder="邮箱" />
        </Form.Item>
        <Form.Item>
          <Button type="primary" htmlType="submit" style={{ width: '100%' }}>
            注册
          </Button>
        </Form.Item>
        <div style={{ textAlign: 'center' }}>
          已有账户？ <Link to="/login">立即登录</Link>
        </div>
      </Form>
    </div>
  )
}

export default Register