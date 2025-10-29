const API_BASE_URL = 'https://wenge.cloudns.ch'; // 根据您的后端实际地址修改

const api = {
  request: async (method, url, data = null) => {
    const headers = {
      'Content-Type': 'application/json',
    };

    const config = {
      method: method,
      headers: headers,
      credentials: 'include', // 确保在跨域请求中发送 cookies
    };

    if (data) {
      config.body = JSON.stringify(data);
    }

    try {
      const response = await fetch(`${API_BASE_URL}${url}`, config);
      const responseData = await response.json();

      if (!response.ok) {
        console.error(`API Error: ${response.status} - ${responseData.message || 'Unknown error'}`);
        throw new Error(responseData.message || 'Something went wrong');
      }

      return responseData;
    } catch (error) {
      console.error('Network or API request failed:', error);
      throw error;
    }
  },

  get: (url) => api.request('GET', url, null),
  post: (url, data) => api.request('POST', url, data),
  put: (url, data) => api.request('PUT', url, data),
  delete: (url) => api.request('DELETE', url, null),
};

export default api;
