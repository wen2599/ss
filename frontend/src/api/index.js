// src/api/index.js
const API_BASE = '/api';

// 封装 fetch 请求，统一处理错误
async function request(endpoint, options = {}) {
  // 为了 session cookie, credentials 必须是 'include'
  options.credentials = 'include';
  
  try {
    const response = await fetch(`${API_BASE}/${endpoint}`, options);
    const data = await response.json();

    if (!response.ok) {
      // 如果后端返回错误，data.message 应该包含错误信息
      throw new Error(data.message || `Error ${response.status}`);
    }
    return data;
  } catch (error) {
    console.error(`API request to ${endpoint} failed:`, error);
    throw error; // 重新抛出，让调用者可以捕获
  }
}

export const apiService = {
  // Auth
  register(email, password) {
    return request('register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
    });
  },
  login(email, password) {
    return request('login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
    });
  },
  logout() {
    return request('logout', { method: 'POST' });
  },
  checkSession() {
    return request('check_session');
  },

  // Lottery
  getLotteryResults() {
    return request('get_lottery_results');
  },

  // Mails (假设的 API, 后端需要实现)
  getEmails() {
    // 假设后端端点是 'get_emails'
    // return request('get_emails');
    // --- 模拟数据 ---
    return Promise.resolve({
        status: 'success',
        data: [
            { id: 1, subject: "下注单 20250101", received_at: new Date().toISOString() },
            { id: 2, subject: "我的下注 20250102", received_at: new Date().toISOString() },
        ]
    });
  },

  // Settlements (假设的 API, 后端需要实现)
  getSettlements() {
    // 假设后端端点是 'get_settlements'
    // return request('get_settlements');
    // --- 模拟数据 ---
     return Promise.resolve({
        status: 'success',
        data: [
            { id: 1, bet_id: 101, total_win: 500, created_at: new Date().toISOString() },
            { id: 2, bet_id: 102, total_win: 0, created_at: new Date().toISOString() },
        ]
    });
  }
};