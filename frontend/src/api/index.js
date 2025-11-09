// File: frontend/src/api/index.js

const API_BASE_URL = 'https://wenge.cloudns.ch/index.php'; // 使用你的后端 URL

// 统一的请求函数
async function request(endpoint, options = {}) {
  options.credentials = 'include'; // 确保每次请求都携带 session cookie
  const url = `${API_BASE_URL}?endpoint=${endpoint}`;
  
  try {
    const response = await fetch(url, options);
    const data = await response.json();
    if (!response.ok) {
      throw new Error(data.message || `Error ${response.status}`);
    }
    return data;
  } catch (error) {
    console.error(`API request to ${endpoint} failed:`, error);
    throw error;
  }
}

export const apiService = {
  // --- Auth Endpoints ---
  register(email, password) { /* ... (保持不变) */ },
  login(email, password) { /* ... (保持不变) */ },
  logout() { /* ... (保持不变) */ },
  checkSession() { /* ... (保持不变) */ },
  
  // --- Lottery Endpoints ---
  getLotteryResults() {
    return request('get_lottery_results');
  },

  // --- Mails Endpoints ---
  /**
   * Fetches the list of emails for the currently logged-in user.
   */
  getEmails() {
    // 【核心修改】替换掉之前的模拟数据
    return request('get_emails');
  },
  
  // TODO: Add getEmailContent(id) if needed to fetch full email content

  // --- Settlements Endpoints (仍然是模拟数据) ---
  getSettlements() {
    return Promise.resolve({
        status: 'success',
        data: [
            { id: 1, bet_id: 101, total_win: 500, created_at: new Date().toISOString() },
            { id: 2, bet_id: 102, total_win: 0, created_at: new Date().toISOString() },
        ]
    });
  }
};

// 我们把不变的 Auth 函数也放进来，让代码更完整
apiService.register = function(email, password) {
  return request('register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  });
};
apiService.login = function(email, password) {
  return request('login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  });
};
apiService.logout = function() {
  return request('logout', { method: 'POST' });
};
apiService.checkSession = function() {
  return request('check_session');
};