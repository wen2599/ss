<template>
  <div class="email-list-container">
    <h2>收到的邮件</h2>
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
            <td>{{ email.from_address }}</td>
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
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '../api'

const router = useRouter()
const emails = ref([])
const loading = ref(true)
const error = ref(null)

async function fetchEmails() {
  loading.value = true
  error.value = null
  try {
    const response = await apiClient.get('/emails');
    emails.value = response.data.data; // Access the data array from pagination
  } catch (err) {
    console.error('获取邮件时出错：', err);
    error.value = '加载邮件失败。请检查后端服务是否正在运行并可以访问？'
  }
  loading.value = false
}

function viewEmail(id) {
  router.push({ name: 'email-detail', params: { id } })
}

function formatDate(dateString) {
  if (!dateString) return '无';
  const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
  return new Date(dateString).toLocaleDateString('zh-CN', options);
}

onMounted(() => {
  fetchEmails()
})
</script>

<style scoped>
.email-list-container {
  font-family: Arial, sans-serif;
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
