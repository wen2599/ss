// File: frontend/src/api/index.js

const API_BASE_URL = 'https://wenge.cloudns.ch/index.php';

async function request(endpoint, options = {}, queryParams = '') {
  options.credentials = 'include';
  const url = `${API_BASE_URL}?endpoint=${endpoint}${queryParams}`;
  
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
  // Auth
  register(email, password) { /* ... */ },
  login(email, password) { /* ... */ },
  logout() { /* ... */ },
  checkSession() { /* ... */ },
  
  // Lottery
  getLotteryResults() { /* ... */ },

  // Mails
  getEmails() {
    return request('get_emails');
  },
  
  /**
   * Fetches the full content of a single email by its ID.
   */
  getEmailContent(id) {
    // 将 ID 作为查询参数传递
    return request('get_email_content', {}, `&id=${id}`);
  },

  // Settlements (模拟数据)
  getSettlements() { /* ... */ },
};

// --- 补全其他函数 ---
apiService.register = function(email, password) {
  return request('register', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email, password }) });
};
apiService.login = function(email, password) {
  return request('login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email, password }) });
};
apiService.logout = function() {
  return request('logout', { method: 'POST' });
};
apiService.checkSession = function() {
  return request('check_session');
};
apiService.getLotteryResults = function() {
  return request('get_lottery_results');
};
apiService.getSettlements = function() {
    return Promise.resolve({
        status: 'success',
        data: [ { id: 1, bet_id: 101, total_win: 500, created_at: new Date().toISOString() } ]
    });
};