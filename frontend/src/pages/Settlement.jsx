import { useState, useEffect, useContext } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { Form, Input, Button, Table, message, Card, InputNumber } from 'antd'
import axios from 'axios'
import { AuthContext } from '../context/AuthContext'

const API_URL = 'http://localhost:8080/api' // Adjust this URL

function Settlement() {
  const { emailId } = useParams()
  const [email, setEmail] = useState(null)
  const [bets, setBets] = useState([])
  const [dialogHistory, setDialogHistory] = useState([])
  const [userInput, setUserInput] = useState('')
  const [form] = Form.useForm()
  const { user } = useContext(AuthContext)
  const navigate = useNavigate()

  useEffect(() => {
    if (!user) {
        navigate('/login');
        return;
    }
    const fetchData = async () => {
      try {
        const token = localStorage.getItem('token')
        const response = await axios.get(`${API_URL}/email.php?action=get_email_details&id=${emailId}`, {
            headers: { Authorization: `Bearer ${token}` },
        });
        const data = response.data;
        setEmail({ body: data.body }); // Store email body
        setBets(JSON.parse(data.bets_json || '[]'));
        setDialogHistory(JSON.parse(data.dialog_history || '[]'));
        form.setFieldsValue({ bets: JSON.parse(data.bets_json || '[]') });
      } catch (error) {
        message.error(error.response?.data?.error || '无法加载结算数据');
      }
    };

    fetchData();
  }, [emailId, user, navigate, form]);

  const handleDialogSubmit = async () => {
    try {
        const token = localStorage.getItem('token')
        const response = await axios.post(`${API_URL}/ai.php`, 
            { action: 'dialog', email_id: emailId, message: userInput, history: dialogHistory }, 
            { headers: { Authorization: `Bearer ${token}` } }
        );
        const { response_text, corrected_json } = response.data;
        setDialogHistory(prev => [...prev, {role: 'user', content: userInput}, {role: 'assistant', content: response_text}]);
        setBets(corrected_json);
        form.setFieldsValue({ bets: corrected_json });
        setUserInput(''); // Clear input field
    } catch (error) {
        message.error(error.response?.data?.error || '对话失败');
    }
  };

  const onFinish = async (values) => {
    try {
        const token = localStorage.getItem('token');
        await axios.post(`${API_URL}/lottery.php`, 
            { action: 'save_settlement', email_id: emailId, bets: values.bets },
            { headers: { Authorization: `Bearer ${token}` } }
        );
        message.success('结算单已保存');
    } catch (error) {
        message.error(error.response?.data?.error || '保存失败');
    }
  };

  const betColumns = [
    { title: '用户', dataIndex: 'user', render: (text, record, index) => <Form.Item name={['bets', index, 'user']} noStyle><Input /></Form.Item> },
    { title: '号码', dataIndex: 'numbers', render: (text, record, index) => <Form.Item name={['bets', index, 'numbers']} noStyle><Input /></Form.Item> }, // Simple input, consider a number array editor
    { title: '特码', dataIndex: 'special', render: (text, record, index) => <Form.Item name={['bets', index, 'special']} noStyle><InputNumber /></Form.Item> },
    { title: '金额', dataIndex: 'amount', render: (text, record, index) => <Form.Item name={['bets', index, 'amount']} noStyle><InputNumber /></Form.Item> },
  ];

  return (
    <div style={{ display: 'flex', padding: '20px', gap: '20px' }}>
      <div style={{ flex: 1 }}>
        <h2>结算表单 (邮件ID: {emailId})</h2>
        <Card title="邮件原文" style={{ marginBottom: 20 }}>
            <pre>{email?.body}</pre>
        </Card>
        <Form form={form} onFinish={onFinish} initialValues={{ bets }}>
            <Form.List name="bets">
              {(fields, { add, remove }) => (
                <>
                  <Table dataSource={fields} columns={betColumns} rowKey="key" pagination={false} />
                  <Button onClick={() => add()} style={{ marginTop: 8 }}>添加一行</Button>
                </>
              )}
            </Form.List>
            <Form.Item style={{ marginTop: 20 }}>
                <Button type="primary" htmlType="submit">保存结算单</Button>
            </Form.Item>
        </Form>
      </div>
      <div style={{ width: '350px' }}>
        <h2>对话式修正</h2>
        <Card>
            <div style={{ height: '400px', overflowY: 'auto', marginBottom: '10px', border: '1px solid #eee', padding: '10px' }}>
                {dialogHistory.map((item, index) => (
                    <p key={index}><strong>{item.role}:</strong> {item.content}</p>
                ))}
            </div>
            <Input.TextArea value={userInput} onChange={e => setUserInput(e.target.value)} rows={3} placeholder="输入修正指令..." />
            <Button onClick={handleDialogSubmit} type="primary" style={{ marginTop: 10 }}>发送</Button>
        </Card>
      </div>
    </div>
  );
}

export default Settlement;