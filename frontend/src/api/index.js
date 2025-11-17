// File: frontend/src/api/index.js (修复响应体多次读取问题)

const API_BASE_URL = 'https://wenge.cloudns.ch/index.php';

async function request(endpoint, options = {}, queryParams = '') {
  const defaultOptions = {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
    },
  };

  const finalOptions = {
    ...defaultOptions,
    ...options,
    headers: {
      ...defaultOptions.headers,
      ...options.headers,
    },
  };

  const url = `${API_BASE_URL}?endpoint=${endpoint}${queryParams}`;

  try {
    console.log(`Making API request to: ${endpoint}`);
    const response = await fetch(url, finalOptions);

    if (!response.ok) {
      if (response.status === 401) {
        if (typeof window !== 'undefined' && window.location.pathname !== '/auth') {
          window.location.href = '/auth';
        }
        throw new Error('登录已过期，请重新登录');
      }

      let errorMessage = `Error ${response.status}`;
      try {
        // 克隆响应以避免多次读取问题
        const errorResponse = response.clone();
        const errorText = await errorResponse.text();
        try {
          const errorData = JSON.parse(errorText);
          errorMessage = errorData.message || errorMessage;
        } catch (e) {
          errorMessage = errorText || errorMessage;
        }
      } catch (e) {
        // 如果无法读取错误响应，使用默认错误信息
        console.error('Failed to read error response:', e);
      }

      throw new Error(errorMessage);
    }

    if (endpoint === 'download_settlement') {
        return response.blob();
    }

    const data = await response.json();
    console.log(`API request to ${endpoint} successful`);
    return data;
  } catch (error) {
    console.error(`API request to ${endpoint} failed:`, error);
    if (error.name === 'TypeError' && error.message.includes('fetch')) {
      throw new Error('网络连接失败，请检查网络连接后重试');
    }
    throw error;
  }
}

export const apiService = {
  register(email, password) {
    return request('register', {
      method: 'POST',
      body: JSON.stringify({ email, password })
    });
  },

  login(email, password) {
    return request('login', {
      method: 'POST',
      body: JSON.stringify({ email, password })
    });
  },

  logout() {
    return request('logout', { method: 'POST' });
  },

  checkSession() {
    return request('check_session');
  },

  getLotteryResults(type = 'all', limit = 10) {
    return request('get_lottery_results', {}, `&type=${encodeURIComponent(type)}&limit=${limit}`);
  },

  getLotteryResultByIssue(type, issue) {
    return request('get_lottery_result_by_issue', {}, `&type=${encodeURIComponent(type)}&issue=${issue}`);
  },

  getEmails() {
    return request('get_emails');
  },

  getEmailContent(id) {
    return request('get_email_content', {}, `&id=${id}`);
  },

  getEmailDetails(id) {
    return request('get_email_details', {}, `&id=${id}`);
  },

  splitEmailLines(emailId) {
    return request('split_email_lines', {}, `&id=${emailId}`);
  },

  parseSingleBet(emailId, betText, lineNumber, lotteryType = '香港六合彩') {
    return request('parse_single_bet', {
      method: 'POST',
      body: JSON.stringify({
        email_id: emailId,
        bet_text: betText,
        line_number: lineNumber,
        lottery_type: lotteryType
      })
    });
  },

  updateBetBatch(batchId, data) {
    return request('update_bet_batch', {
      method: 'POST',
      body: JSON.stringify({ batch_id: batchId, data: data })
    });
  },

  deleteBetBatch(batchId) {
    return request('delete_bet_batch', {
      method: 'POST',
      body: JSON.stringify({ batch_id: batchId })
    });
  },

  reanalyzeEmail(emailId) {
    return request('reanalyze_email', {
      method: 'POST',
      body: JSON.stringify({ email_id: emailId })
    });
  },

  downloadSettlement(emailId) {
    return request('download_settlement', {}, `&id=${emailId}`);
  },

  smartParseEmail(emailId, lotteryTypes) {
    return request('smart_parse_email', {
      method: 'POST',
      body: JSON.stringify({
        email_id: emailId,
        lottery_types: lotteryTypes
      })
    });
  },

  /**
   * 快速校准AI
   * @param {object} payload - 包含金额和理由的校准数据
   * @returns {Promise}
   */
  quickCalibrateAi(payload) {
    console.log('快速校准API调用，payload:', payload);
    return request('quick_calibrate_ai', {
        method: 'POST',
        body: JSON.stringify(payload)
    });
  },

  getOddsTemplate() {
    return request('odds_template');
  },

  updateOddsTemplate(templateData) {
    return request('odds_template', {
      method: 'POST',
      body: JSON.stringify(templateData)
    });
  },

  getSettlements() {
    return Promise.resolve({
      status: 'success',
      data: [
        { id: 1, bet_id: 101, total_win: 500, created_at: new Date().toISOString() },
        { id: 2, bet_id: 102, total_win: -200, created_at: new Date(Date.now() - 86400000).toISOString() }
      ]
    });
  },

  runSettlement(betId, lotteryResult) {
    return Promise.resolve({
      status: 'success',
      data: {
        id: Date.now(),
        bet_id: betId,
        total_win: Math.random() > 0.5 ? 450 : -100,
        created_at: new Date().toISOString()
      }
    });
  }
};

export default apiService;