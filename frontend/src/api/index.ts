// frontend/src/api/index.ts
import axios from 'axios';

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

export const getLatestDraw = () => {
  return apiClient.get('/api/get_latest_draw.php');
};

// ... 其他 API 请求函数
