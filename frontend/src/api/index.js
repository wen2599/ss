// File: frontend/src/api/index.js (添加下载功能)

const API_BASE_URL = 'https://wenge.cloudns.ch/index.php';

/**
 * 统一的API请求函数
 * @param {string} endpoint - API端点
 * @param {object} options - 请求选项
 * @param {string} queryParams - 查询参数
 * @returns {Promise} 返回Promise对象
 */
async function request(endpoint, options = {}, queryParams = '') {
  // 设置默认选项
  const defaultOptions = {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
    },
  };

  // 合并选项
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

    // 检查响应状态
    if (!response.ok) {
      // 如果是401未授权，可能是session过期，重定向到登录页
      if (response.status === 401) {
        if (typeof window !== 'undefined' && window.location.pathname !== '/auth') {
          window.location.href = '/auth';
        }
        throw new Error('登录已过期，请重新登录');
      }

      // 尝试解析错误信息
      let errorMessage = `Error ${response.status}`;
      try {
        const errorData = await response.text();
        if (errorData) {
          const parsedError = JSON.parse(errorData);
          errorMessage = parsedError.message || errorMessage;
        }
      } catch (e) {
        // 如果无法解析JSON，使用原始错误文本
        errorMessage = await response.text() || errorMessage;
      }

      throw new Error(errorMessage);
    }

    // 对于下载请求，返回blob
    if (endpoint === 'download_settlement') {
      return response.blob();
    }

    // 解析响应数据
    const data = await response.json();

    // 记录成功的API调用
    console.log(`API request to ${endpoint} successful`);

    return data;
  } catch (error) {
    console.error(`API request to ${endpoint} failed:`, error);

    // 如果是网络错误，提供更友好的错误信息
    if (error.name === 'TypeError' && error.message.includes('fetch')) {
      throw new Error('网络连接失败，请检查网络连接后重试');
    }

    throw error;
  }
}

// API服务对象
export const apiService = {
  // ==================== 认证相关 ====================

  /**
   * 用户注册
   * @param {string} email - 邮箱
   * @param {string} password - 密码
   * @returns {Promise}
   */
  register(email, password) {
    return request('register', {
      method: 'POST',
      body: JSON.stringify({ email, password })
    });
  },

  /**
   * 用户登录
   * @param {string} email - 邮箱
   * @param {string} password - 密码
   * @returns {Promise}
   */
  login(email, password) {
    return request('login', {
      method: 'POST',
      body: JSON.stringify({ email, password })
    });
  },

  /**
   * 用户登出
   * @returns {Promise}
   */
  logout() {
    return request('logout', { method: 'POST' });
  },

  /**
   * 检查会话状态
   * @returns {Promise}
   */
  checkSession() {
    return request('check_session');
  },

  // ==================== 彩票相关 ====================

  /**
   * 获取彩票结果
   * @param {string} type - 彩票类型
   * @param {number} limit - 限制数量
   * @returns {Promise}
   */
  getLotteryResults(type = 'all', limit = 10) {
    return request('get_lottery_results', {}, `&type=${encodeURIComponent(type)}&limit=${limit}`);
  },

  /**
   * 根据期号获取彩票结果
   * @param {string} type - 彩票类型
   * @param {string} issue - 期号
   * @returns {Promise}
   */
  getLotteryResultByIssue(type, issue) {
    return request('get_lottery_result_by_issue', {}, `&type=${encodeURIComponent(type)}&issue=${issue}`);
  },

  // ==================== 邮件相关 ====================

  /**
   * 获取邮件列表
   * @returns {Promise}
   */
  getEmails() {
    return request('get_emails');
  },

  /**
   * 获取邮件内容
   * @param {number} id - 邮件ID
   * @returns {Promise}
   */
  getEmailContent(id) {
    return request('get_email_content', {}, `&id=${id}`);
  },

  /**
   * 获取邮件详情（包含结算信息）
   * @param {number} id - 邮件ID
   * @returns {Promise}
   */
  getEmailDetails(id) {
    return request('get_email_details', {}, `&id=${id}`);
  },

  /**
   * 更新下注批次
   * @param {number} batchId - 批次ID
   * @param {object} data - 下注数据
   * @returns {Promise}
   */
  updateBetBatch(batchId, data) {
    return request('update_bet_batch', {
      method: 'POST',
      body: JSON.stringify({ batch_id: batchId, data: data })
    });
  },

  /**
   * 重新解析邮件
   * @param {number} emailId - 邮件ID
   * @returns {Promise}
   */
  reanalyzeEmail(emailId) {
    return request('reanalyze_email', {
      method: 'POST',
      body: JSON.stringify({ email_id: emailId })
    });
  },

  /**
   * 下载结算文件
   * @param {number} emailId - 邮件ID
   * @returns {Promise}
   */
  downloadSettlement(emailId) {
    return request('download_settlement', {}, `&id=${emailId}`);
  },

  // ==================== 赔率模板相关 ====================

  /**
   * 获取赔率模板
   * @returns {Promise}
   */
  getOddsTemplate() {
    return request('odds_template');
  },

  /**
   * 更新赔率模板
   * @param {object} templateData - 模板数据
   * @returns {Promise}
   */
  updateOddsTemplate(templateData) {
    return request('odds_template', {
      method: 'POST',
      body: JSON.stringify(templateData)
    });
  },

  // ==================== 结算相关 ====================

  /**
   * 获取结算列表（模拟数据）
   * @returns {Promise}
   */
  getSettlements() {
    // 暂时返回模拟数据
    return Promise.resolve({
      status: 'success',
      data: [
        {
          id: 1,
          bet_id: 101,
          total_win: 500,
          created_at: new Date().toISOString(),
          lottery_type: '香港六合彩',
          issue_number: '2024001'
        },
        {
          id: 2,
          bet_id: 102,
          total_win: -200,
          created_at: new Date(Date.now() - 86400000).toISOString(),
          lottery_type: '澳门六合彩',
          issue_number: '2024001'
        }
      ]
    });
  },

  /**
   * 运行结算（模拟数据）
   * @param {number} betId - 下注ID
   * @param {object} lotteryResult - 开奖结果
   * @returns {Promise}
   */
  runSettlement(betId, lotteryResult) {
    // 暂时返回模拟数据
    return Promise.resolve({
      status: 'success',
      data: {
        id: Date.now(),
        bet_id: betId,
        total_win: Math.random() > 0.5 ? 450 : -100,
        created_at: new Date().toISOString(),
        details: {
          winning_bets: Math.random() > 0.5 ? 2 : 0,
          total_bet_amount: 100,
          net_profit: Math.random() > 0.5 ? 350 : -100
        }
      }
    });
  }
};

// ==================== 工具函数 ====================

/**
 * 处理API错误
 * @param {Error} error - 错误对象
 * @param {string} context - 上下文描述
 */
export function handleApiError(error, context = '操作') {
  console.error(`${context}失败:`, error);

  let userMessage = error.message;

  // 根据错误类型提供更友好的错误信息
  if (error.message.includes('网络连接失败')) {
    userMessage = '网络连接失败，请检查网络连接';
  } else if (error.message.includes('登录已过期')) {
    userMessage = '登录已过期，请重新登录';
  } else if (error.message.includes('401')) {
    userMessage = '认证失败，请重新登录';
  } else if (error.message.includes('403')) {
    userMessage = '权限不足，无法执行此操作';
  } else if (error.message.includes('404')) {
    userMessage = '请求的资源不存在';
  } else if (error.message.includes('500')) {
    userMessage = '服务器内部错误，请稍后重试';
  }

  // 在实际应用中，这里可以集成到通知系统
  if (typeof window !== 'undefined') {
    // 可以在这里添加全局通知
    alert(`${context}失败: ${userMessage}`);
  }

  return userMessage;
}

/**
 * 重试机制
 * @param {Function} apiCall - API调用函数
 * @param {number} maxRetries - 最大重试次数
 * @param {number} delay - 重试延迟(ms)
 * @returns {Promise}
 */
export function withRetry(apiCall, maxRetries = 3, delay = 1000) {
  return new Promise((resolve, reject) => {
    const attempt = (retryCount) => {
      apiCall()
        .then(resolve)
        .catch((error) => {
          if (retryCount < maxRetries) {
            console.log(`API调用失败，第${retryCount + 1}次重试...`);
            setTimeout(() => attempt(retryCount + 1), delay * retryCount);
          } else {
            reject(error);
          }
        });
    };

    attempt(0);
  });
}

/**
 * 防抖API调用
 * @param {Function} apiCall - API调用函数
 * @param {number} delay - 延迟时间(ms)
 * @returns {Function} 防抖函数
 */
export function debounceApiCall(apiCall, delay = 300) {
  let timeoutId;

  return (...args) => {
    return new Promise((resolve, reject) => {
      clearTimeout(timeoutId);
      timeoutId = setTimeout(() => {
        apiCall(...args).then(resolve).catch(reject);
      }, delay);
    });
  };
}

// 默认导出API服务
export default apiService;
