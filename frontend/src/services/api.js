import axios from 'axios';

const api = axios.create({
  baseURL: '/', // Set baseURL to the root of the domain
});

export default api;
