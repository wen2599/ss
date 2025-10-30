import axios from 'axios';

const api = axios.create({
  baseURL: 'https://wenge.cloudns.ch',
});

export default api;