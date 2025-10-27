<template>
  <div class="mail-original-container">
    <h1>我的邮件原文</h1>

    <div v-if="loading" class="loading-state card">加载邮件中...</div>
    <div v-if="error" class="error-state card">加载邮件失败: {{ error }}</div>

    <div v-if="emails.length > 0" class="emails-list">
      <div v-for="email in emails" :key="email.id" class="email-item card">
        <div class="email-header">
          <span class="email-from">发件人: {{ email.from_address }}</span>
          <span class="email-date">收到时间: {{ formatDateTime(email.received_at) }}</span>
        </div>
        <h2 class="email-subject">主题: {{ email.subject }}</h2>
        <div class="email-body">
          <h3>邮件内容:</h3>
          <pre>{{ email.body }}</pre>
        </div>
        <div v-if="email.extracted_data && Object.keys(email.extracted_data).length > 0" class="extracted-data">
          <h3>提取数据:</h3>
          <pre>{{ JSON.stringify(email.extracted_data, null, 2) }}</pre>
        </div>
      </div>
    </div>
    <div v-else-if="!loading && !error" class="empty-state card">您还没有收到任何邮件。</div>
  </div>
</template>

<script>
import recordsService from '@/services/records.js';

export default {
  name: 'MailOriginal',
  data() {
    return {
      emails: [],
      loading: true,
      error: null,
    };
  },
  async created() {
    await this.fetchEmails();
  },
  methods: {
    async fetchEmails() {
      this.loading = true;
      this.error = null;
      try {
        const token = localStorage.getItem('jwt_token'); // 获取JWT token
        if (!token) {
          this.error = '用户未登录，请先登录。';
          this.loading = false;
          return;
        }
        const response = await recordsService.getMyRecords(token);
        // 后端返回的是一个数组，不需要再次解构data
        this.emails = response.data.map(email => {
            // 确保 extracted_data 是对象，如果不是则尝试解析
            if (typeof email.extracted_data === 'string') {
                try {
                    email.extracted_data = JSON.parse(email.extracted_data);
                } catch (e) {
                    console.error("Error parsing extracted_data:", e);
                    email.extracted_data = {}; // 解析失败则置为空对象
                }
            }
            return email;
        });

      } catch (err) {
        console.error('获取邮件失败:', err);
        this.error = err.response && err.response.data && err.response.data.message
                     ? err.response.data.message
                     : '无法连接到服务器或数据不可用。';
      } finally {
        this.loading = false;
      }
    },
    formatDateTime(dateTimeString) {
      if (!dateTimeString) return 'N/A';
      return new Date(dateTimeString).toLocaleString('zh-CN', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
      });
    }
  },
};
</script>

<style scoped>
.mail-original-container {
  padding: 2rem;
  max-width: 1000px;
  margin: 0 auto;
}

h1 {
  color: var(--primary-accent);
  text-align: center;
  margin-bottom: 2rem;
}

.card {
  background-color: var(--background-secondary);
  border-radius: 8px;
  box-shadow: var(--shadow-elevation-low);
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.loading-state,
.error-state,
.empty-state {
  text-align: center;
  padding: 2rem;
  font-size: 1.1rem;
  color: var(--text-secondary);
}

.error-state {
  background-color: var(--error-color);
  color: #fff;
}

.emails-list {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.email-item {
  border-left: 5px solid var(--primary-accent);
}

.email-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.email-subject {
  font-size: 1.3rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1rem;
}

.email-body h3,
.extracted-data h3 {
  color: var(--primary-accent);
  margin-top: 1.5rem;
  margin-bottom: 0.8rem;
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 0.5rem;
}

.email-body pre,
.extracted-data pre {
  background-color: var(--background-primary);
  padding: 1rem;
  border-radius: 6px;
  white-space: pre-wrap;
  word-break: break-all;
  font-family: 'Fira Code', 'Consolas', 'Monaco', monospace;
  font-size: 0.95rem;
  color: var(--text-primary);
  max-height: 400px;
  overflow-y: auto;
  border: 1px solid var(--border-color);
}
</style>
