<template>
  <div class="email-list-container">
    <h2>收到的邮件</h2>

    <!-- Unauthenticated User Message -->
    <div v-if="!isAuthenticated" class="unauthenticated-message">
      <p>请登录或注册以查看您的邮件。</p>
    </div>

    <div v-if="loading && isAuthenticated" class="loading-message">加载中...</div>
    <div v-if="error && isAuthenticated" class="error-message">{{ error }}</div>
    
    <div v-if="emails.length > 0 && isAuthenticated" class="email-table-wrapper">
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
    
    <div v-if="emails.length === 0 && !loading && isAuthenticated" class="no-emails-message">
      <p>未找到邮件。</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import apiClient from '../api';
import { useAuthStore } from '../stores/auth'; // Import the new Pinia store

const router = useRouter();
const authStore = useAuthStore(); // Initialize Pinia store

const emails = ref([]);
const loading = ref(true);
const error = ref(null);

// Get authentication status from the Pinia store
const isAuthenticated = computed(() => authStore.isAuthenticated);

async function fetchEmails() {
  if (!isAuthenticated.value) {
    emails.value = [];
    loading.value = false;
    return;
  }
  loading.value = true;
  error.value = null;
  try {
    const response = await apiClient.get('/emails');
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
  // The watcher will call fetchEmails on mount because of `immediate: true`
});

// Watch for changes in authentication status and re-fetch emails
watch(isAuthenticated, () => {
  fetchEmails();
}, { immediate: true }); // Use immediate to run on initial mount

</script>

<style scoped>
/* Styles remain the same */
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
