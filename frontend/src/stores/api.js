import axios from 'axios';

// 根据环境自动切换后端 API 地址
const isProduction = import.meta.env.PROD;

const apiClient = axios.create({
  baseURL: isProduction 
    ? 'https://wenge.cloudns.ch' // 你的生产环境后端地址
    : 'http://localhost:8080',    // 你的本地开发后端地址
  headers: {
    'Content-Type': 'application/json',
  },
});

export default apiClient;