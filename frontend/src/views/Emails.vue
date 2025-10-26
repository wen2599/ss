<template>
  <div class="emails-container">
    <div class="email-input-card">
      <h2>查看您的邮件</h2>
      <form @submit.prevent="fetchEmails">
        <div class="form-group">
          <label for="email">请输入您的邮箱地址</label>
          <input type="email" id="email" v-model="email" required placeholder="your.email@example.com">
        </div>
        <button type="submit" :disabled="loading" class="submit-btn">
          {{ loading ? '正在加载...' : '获取邮件' }}
        </button>
        <div v-if="error" class="error-message">
          {{ error }}
        </div>
      </form>
    </div>

    <div v-if="emails.length > 0" class="emails-list-card">
      <h3>收件箱</h3>
      <ul class="email-list">
        <li v-for="email in emails" :key="email.id" @click="selectEmail(email)" class="email-item">
          <div class="email-header">
            <strong>发件人:</strong> {{ email.from_address }}
          </div>
          <div class="email-subject">
            <strong>主题:</strong> {{ email.subject }}
          </div>
          <div class="email-date">
            {{ new Date(email.created_at).toLocaleString() }}
          </div>
        </li>
      </ul>
    </div>

    <div v-if="selectedEmail" class="email-viewer-card">
      <button @click="selectedEmail = null" class="close-btn">返回列表</button>
      <h3>{{ selectedEmail.subject }}</h3>
      <p><strong>发件人:</strong> {{ selectedEmail.from_address }}</p>
      <p><strong>日期:</strong> {{ new Date(selectedEmail.created_at).toLocaleString() }}</p>
      <hr>
      <div class="email-body">
        <pre>{{ selectedEmail.body }}</pre>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'EmailsView',
  data() {
    return {
      email: '',
      emails: [],
      selectedEmail: null,
      loading: false,
      error: null,
    };
  },
  methods: {
    async fetchEmails() {
      this.loading = true;
      this.error = null;
      this.emails = [];
      this.selectedEmail = null;
      try {
        const response = await axios.get(`/api/get_emails.php?email=${this.email}`);
        this.emails = response.data;
        if (this.emails.length === 0) {
            this.error = "没有找到该邮箱的邮件。";
        }
      } catch (err) {
        if (err.response && err.response.data && err.response.data.message) {
          this.error = err.response.data.message;
        } else {
          this.error = '无法加载邮件，请检查邮箱地址或稍后再试。';
        }
      } finally {
        this.loading = false;
      }
    },
    selectEmail(email) {
      this.selectedEmail = email;
    },
  },
};
</script>

<style scoped>
.emails-container {
  max-width: 800px;
  margin: 0 auto;
  padding: 1rem;
}
.email-input-card, .emails-list-card, .email-viewer-card {
  background-color: white;
  padding: 2rem;
  border-radius: 8px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
}
.form-group {
  margin-bottom: 1.5rem;
}
label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}
input {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 4px;
}
.submit-btn {
  width: 100%;
  padding: 0.75rem;
  border: none;
  border-radius: 4px;
  background-color: #4f46e5;
  color: white;
  font-size: 1rem;
  cursor: pointer;
}
.error-message {
  margin-top: 1rem;
  color: #ef4444;
}
.email-list {
  list-style-type: none;
  padding: 0;
}
.email-item {
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 1rem;
  margin-bottom: 1rem;
  cursor: pointer;
  transition: background-color 0.2s;
}
.email-item:hover {
  background-color: #f8fafc;
}
.email-header, .email-subject, .email-date {
  margin-bottom: 0.5rem;
}
.email-date {
  font-size: 0.875rem;
  color: #6b7280;
}
.close-btn {
  margin-bottom: 1rem;
  padding: 0.5rem 1rem;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  background-color: #f3f4f6;
  cursor: pointer;
}
.email-body {
  margin-top: 1rem;
  white-space: pre-wrap;
  word-wrap: break-word;
}
</style>
