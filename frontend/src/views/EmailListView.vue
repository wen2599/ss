<template>
  <div class="email-list-container">
    <h2>收到的邮件</h2>

    <!-- Unauthenticated User Message -->
    <div v-if="!isAuthenticated" class="unauthenticated-message">
      <p>您当前未登录。只显示公开的邮件。 <router-link to="/login">登录</router-link> 以查看所有邮件。</p>
    </div>

    <div v-if="loading" class="loading-message">加载中...</div>
    <div v-if="error" class="error-message">{{ error }}</div>
    
    <div v-if="emails.length > 0" class="email-table-wrapper">
      <table class="email-table">
        <thead>
          <tr>
            <th>发件人</th>
            <th>主题</th>
            <th>接收时间</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="email in emails" :key="email.id" @click="viewEmail(email.id)">
            <td>{{ email.sender }}</td>
            <td>{{ email.subject }}</td>
            <td>{{ formatDate(email.received_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    
    <div v-else-if="!loading" class="no-emails-message">
      <p>未找到邮件。</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import apiClient from '../api';
import { store } from '../store'; // Import the store

const router = useRouter();
const emails = ref([]);
const loading = ref(true);
const error = ref(null);

// Get authentication status from the store
const isAuthenticated = computed(() => store.state.isAuthenticated);

async function fetchEmails() {
  loading.value = true;
  error.value = null;
  try {
    // The backend automatically filters emails based on authentication, so no change is needed here.
    const response = await apiClient.get('/list_emails.php');
    if (response && response.data && Array.isArray(response.data.data)) {
      emails.value = response.data.data;
    } else {
      emails.value = [];
      console.warn('API response for emails was not in the expected format:', response);
    }
  } catch (err) {
    emails.value = [];
    console.error('获取邮件时出错：', err);
    error.value = '加载邮件失败。请检查后端服务是否正在运行并可以访问？';
  }
  loading.value = false;
}

function viewEmail(id) {
  router.push({ name: 'email-detail', params: { id } });
}

function formatDate(dateString) {
  if (!dateString) return '无';
  const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
  return new Date(dateString).toLocaleDateString('zh-CN', options);
}

// --- Lifecycle and Watchers ---
onMounted(() => {
  fetchEmails();
});

// Watch for changes in authentication status and re-fetch emails
watch(isAuthenticated, (newAuthStatus, oldAuthStatus) => {
  // Re-fetch only if the status actually changes
  if (newAuthStatus !== oldAuthStatus) {
    fetchEmails();
  }
});

</script>

<style scoped>
.email-list-container {
  font-family: Arial, sans-serif;
}

.unauthenticated-message {
  background-color: #fffbe6;
  padding: 1rem;
  border: 1px solid #ffe58f;
  border-radius: 4px;
  margin-bottom: 1.5rem;
  text-align: center;
}

.unauthenticated-message a {
  font-weight: bold;
  color: #007bff;
}

.loading-message, .error-message, .no-emails-message {
  text-align: center;
  padding: 2rem;
  font-size: 1.2rem;
}

.error-message {
  color: #d9534f;
}

.email-table-wrapper {
  border: 1px solid #ddd;
  border-radius: 8px;
  overflow: hidden;
}

.email-table {
  width: 100%;
  border-collapse: collapse;
}

.email-table th, .email-table td {
  padding: 1rem;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

.email-table thead th {
  background-color: #f7f7f7;
  font-weight: bold;
}

.email-table tbody tr {
  cursor: pointer;
  transition: background-color 0.2s;
}

.email-table tbody tr:hover {
  background-color: #f1f1f1;
}

.email-table tbody tr:last-child td {
  border-bottom: none;
}
</style>
