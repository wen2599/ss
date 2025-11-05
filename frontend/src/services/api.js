import axios from 'axios';

const api = axios.create({
    baseURL: 'http://localhost:5000/api', // 后端API的基础URL
    timeout: 10000, // 请求超时时间
    headers: {
        'Content-Type': 'application/json',
    }
});

export default api;