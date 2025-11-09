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
  register(email, password) {
    return request('register', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email, password }) });
  },
  login(email, password) {
    return request('login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email, password }) });
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
  getLotteryResultByIssue(type, issue) {
    return request('get_lottery_result_by_issue', {}, `&type=${encodeURIComponent(type)}&issue=${issue}`);
  },

  // Mails
  getEmails() {
    return request('get_emails');
  },
  
  /**
   * Fetches the full content of a single email by its ID.
   */
  getEmailContent(id) {
    return request('get_email_content', {}, `&id=${id}`);
  },

  getEmailDetails(id) {
    return request('get_email_details', {}, `&id=${id}`);
  },

  updateBetBatch(batchId, data) {
    return request('update_bet_batch', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ batch_id: batchId, data: data }),
    });
  },
  
  // Settlements (模拟数据)
  getSettlements() {
    return Promise.resolve({
        status: 'success',
        data: [ { id: 1, bet_id: 101, total_win: 500, created_at: new Date().toISOString() } ]
    });
  },
};
