<template>
  <div class="mail-organize-container">
    <h1>邮件整理</h1>

    <div v-if="loading" class="loading-state card">加载邮件中...</div>
    <div v-if="error" class="error-state card">加载邮件失败: {{ error }}</div>

    <div v-if="emails.length > 0" class="emails-list">
      <div v-for="email in emails" :key="email.id" class="email-item card">
        <div class="email-header">
          <span class="email-from">发件人: {{ email.from_address }}</span>
          <span class="email-date">收到时间: {{ formatDateTime(email.received_at) }}</span>
        </div>
        <h2 class="email-subject">主题: {{ email.subject }}</h2>
        <button @click="selectEmailForOrganization(email)" class="organize-button">整理邮件</button>
      </div>
    </div>
    <div v-else-if="!loading && !error" class="empty-state card">您还没有收到任何邮件可供整理。</div>

    <!-- 邮件整理表单区域 -->
    <div v-if="selectedEmail" class="organization-form-section card">
      <h2>整理邮件: {{ selectedEmail.subject }}</h2>
      <form @submit.prevent="saveOrganizedData">
        <div class="form-group">
          <label for="organizedSubject">主题:</label>
          <input type="text" id="organizedSubject" v-model="organizedSubject" class="form-control" />
        </div>
        <div class="form-group">
          <label for="organizedBody">内容:</label>
          <textarea id="organizedBody" v-model="organizedBody" class="form-control"></textarea>
        </div>
        <div v-if="selectedEmail.extracted_data && Object.keys(selectedEmail.extracted_data).length > 0" class="form-group">
          <label>提取数据:</label>
          <pre class="extracted-display">{{ JSON.stringify(selectedEmail.extracted_data, null, 2) }}</pre>
          <p class="help-text">您可以参考提取数据来填写表单。</p>
        </div>
        <button type="submit" class="submit-button">保存整理结果</button>
        <button type="button" @click="cancelOrganization" class="cancel-button">取消</button>
      </form>
    </div>
  </div>
</template>

<script>
import recordsService from '@/services/records.js';

export default {
  name: 'MailOrganize',
  data() {
    return {
      emails: [],
      loading: true,
      error: null,
      selectedEmail: null,
      organizedSubject: '',
      organizedBody: '',
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
        const token = localStorage.getItem('jwt_token');
        if (!token) {
          this.error = '用户未登录，请先登录。';
          this.loading = false;
          return;
        }
        const response = await recordsService.getMyRecords(token);
        this.emails = response.data.map(email => {
            if (typeof email.extracted_data === 'string') {
                try {
                    email.extracted_data = JSON.parse(email.extracted_data);
                } catch (e) {
                    console.error("Error parsing extracted_data:", e);
                    email.extracted_data = {};
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
    selectEmailForOrganization(email) {
      this.selectedEmail = email;
      this.organizedSubject = email.subject;
      this.organizedBody = email.body;
      // 这里可以根据 extracted_data 进一步预填充更复杂的表单字段
    },
    saveOrganizedData() {
      // 在这里实现保存整理后数据的逻辑
      // 可以调用一个新的后端API来保存整理后的表单数据
      console.log('保存整理后的数据:', {
        emailId: this.selectedEmail.id,
        subject: this.organizedSubject,
        body: this.organizedBody,
        // 更多整理后的字段
      });
      alert('整理结果已在控制台打印，实际保存功能待实现。');
      this.cancelOrganization();
    },
    cancelOrganization() {
      this.selectedEmail = null;
      this.organizedSubject = '';
      this.organizedBody = '';
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
.mail-organize-container {
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
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.email-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.email-subject {
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--text-primary);
}

.organize-button {
  align-self: flex-end;
  padding: 0.6rem 1.2rem;
  background-color: var(--primary-accent);
  color: white;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-size: 0.9rem;
  transition: background-color 0.2s ease;
}

.organize-button:hover {
  background-color: #4CAF50;
}

.organization-form-section h2 {
  color: var(--primary-accent);
  margin-bottom: 1.5rem;
  text-align: center;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  color: var(--text-primary);
  font-weight: 500;
}

.form-control {
  width: 100%;
  padding: 0.8rem;
  border: 1px solid var(--border-color);
  border-radius: 5px;
  background-color: var(--background-primary);
  color: var(--text-primary);
  box-sizing: border-box;
  font-size: 1rem;
}

textarea.form-control {
  min-height: 150px;
  resize: vertical;
}

.extracted-display {
  background-color: var(--background-primary);
  padding: 1rem;
  border-radius: 6px;
  white-space: pre-wrap;
  word-break: break-all;
  font-family: 'Fira Code', 'Consolas', 'Monaco', monospace;
  font-size: 0.9rem;
  color: var(--text-primary);
  border: 1px solid var(--border-color);
  max-height: 200px;
  overflow-y: auto;
}

.help-text {
  font-size: 0.85rem;
  color: var(--text-secondary);
  margin-top: 0.5rem;
}

.submit-button,
.cancel-button {
  padding: 0.8rem 1.5rem;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-size: 1rem;
  margin-top: 1.5rem;
  transition: background-color 0.2s ease;
}

.submit-button {
  background-color: var(--primary-accent);
  color: white;
  margin-right: 1rem;
}

.submit-button:hover {
  background-color: #4CAF50;
}

.cancel-button {
  background-color: var(--background-tertiary);
  color: var(--text-primary);
}

.cancel-button:hover {
  background-color: #999;
  color: white;
}
</style>
