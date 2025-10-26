<template>
  <div class="emails-container">
    <div class="emails-list-card">
      <h2>您的收件箱</h2>
      <div v-if="loading" class="loading-message">
        正在加载...
      </div>
      <div v-if="error" class="error-message">
        {{ error }}
      </div>
      <div v-if="emails.length > 0">
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
      <div v-if="!loading && emails.length === 0 && !error" class="no-data-message">
        您的收件箱是空的。
      </div>
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
      emails: [],
      selectedEmail: null,
      loading: false,
      error: null,
    };
  },
  created() {
    this.fetchEmails();
  },
  methods: {
    async fetchEmails() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get(`/api/get_emails.php`);
        this.emails = response.data;
      } catch (err) {
        if (err.response && err.response.status === 401) {
            this.error = "请先登录以查看您的邮件。";
        } else {
          this.error = '无法加载邮件，请稍后再试。';
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
/* Styles from previous implementation */
.emails-container {
  max-width: 800px;
  margin: 0 auto;
  padding: 1rem;
}
.emails-list-card, .email-viewer-card {
  background-color: white;
  padding: 2rem;
  border-radius: 8px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
}
/* ... etc ... */
</style>
