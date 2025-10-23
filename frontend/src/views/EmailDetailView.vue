<template>
  <div class="email-detail-container">
    <button @click="goBack" class="back-button">&larr; 返回列表</button>

    <div v-if="loading" class="loading-message">正在加载邮件...</div>

    <!-- Custom Error Messages -->
    <div v-if="error" class="error-message">
      <p>{{ error.message }}</p>
      <!-- Provide a login link if the error is related to authentication -->
      <p v-if="error.showLoginLink">
        <router-link to="/login">请登录</router-link> 以查看这封邮件。
      </p>
    </div>

    <div v-if="email" class="email-content-wrapper">
      <div class="email-header-info">
        <h2>{{ email.subject || '无主题' }}</h2>
        <p><strong>发件人：</strong> {{ email.sender }}</p>
        <p><strong>收件人：</strong> {{ email.recipient }}</p>
        <p><strong>接收时间：</strong> {{ formatDate(email.received_at) }}</p>
      </div>

      <div class="email-body-tabs">
        <button :class="{ active: activeTab === 'html' }" @click="activeTab = 'html'">HTML 视图</button>
        <button :class="{ active: activeTab === 'text' }" @click="activeTab = 'text'">纯文本</button>
      </div>

      <div class="email-body">
        <div v-if="activeTab === 'html'" class="html-view">
          <iframe :srcdoc="email.body_html || '<p>此邮件没有HTML内容。</p>'" @load="resizeIframe"></iframe>
        </div>
        <div v-if="activeTab === 'text'" class="text-view">
          <pre>{{ email.body_text || '此邮件没有纯文本内容。' }}</pre>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import apiClient from '../api';
import { store } from '../store'; // Import store to check auth status

const route = useRoute();
const router = useRouter();

const email = ref(null);
const loading = ref(true);
const error = ref(null); // Will now be an object { message: string, showLoginLink: bool }
const activeTab = ref('html');

const emailId = computed(() => route.params.id);
const isAuthenticated = computed(() => store.state.isAuthenticated);

async function fetchEmail() {
  if (!emailId.value) return;
  loading.value = true;
  error.value = null;
  try {
    const response = await apiClient.get(`/get_email.php?id=${emailId.value}`);
    if (response && response.data && response.data.status === 'success') {
      email.value = response.data.data;
      activeTab.value = email.value.body_html ? 'html' : 'text';
    } else {
      throw new Error(response.data.message || '邮件数据格式不正确。');
    }
  } catch (err) {
    console.error(`获取邮件 ${emailId.value} 时出错：`, err);
    const status = err.response ? err.response.status : null;
    
    if (status === 403) {
      error.value = {
        message: '禁止访问：您无权查看此邮件。',
        showLoginLink: !isAuthenticated.value // Show login link if the user is not logged in
      };
    } else if (status === 404) {
      error.value = { message: '找不到邮件：无法在服务器上找到具有此ID的邮件。' };
    } else {
      error.value = { message: '加载邮件详情失败。请稍后再试。' };
    }
  }
  loading.value = false;
}

function goBack() {
  // Go back to the email list, or to the home page if history is not available.
  router.go(-1);
}

function formatDate(dateString) {
  if (!dateString) return '无';
  const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
  return new Date(dateString).toLocaleString('zh-CN', options);
}

// Resize the iframe to fit its content, preventing double scrollbars.
function resizeIframe(event) {
  const iframe = event.target;
  if (iframe && iframe.contentWindow) {
    // Adding a small buffer for better spacing
    iframe.style.height = (iframe.contentWindow.document.body.scrollHeight + 20) + 'px';
  }
}

onMounted(() => {
  fetchEmail();
});
</script>

<style scoped>
.email-detail-container {
  font-family: Arial, sans-serif;
}

.back-button {
  background-color: #f0f0f0;
  border: 1px solid #ccc;
  padding: 0.5rem 1rem;
  border-radius: 5px;
  cursor: pointer;
  margin-bottom: 1rem;
  font-size: 1rem;
}

.email-content-wrapper {
  border: 1px solid #ddd;
  border-radius: 8px;
  background-color: #fff;
}

.email-header-info {
  padding: 1.5rem;
  border-bottom: 1px solid #ddd;
}

.email-header-info h2 {
  margin-top: 0;
  margin-bottom: 0.5rem;
}

.email-header-info p {
  margin: 0.25rem 0;
  color: #555;
}

.email-body-tabs {
  display: flex;
  border-bottom: 1px solid #ddd;
  padding: 0 1.5rem;
  background-color: #f9f9f9;
}

.email-body-tabs button {
  padding: 1rem 1.5rem;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 1rem;
  color: #333;
  border-bottom: 3px solid transparent;
  margin-bottom: -1px; /* Overlap with container border */
}

.email-body-tabs button.active {
  border-bottom-color: #007bff;
  font-weight: bold;
}

.email-body {
  padding: 1.5rem;
}

.html-view iframe {
  width: 100%;
  border: 1px solid #ccc;
  border-radius: 5px;
  min-height: 400px; /* Minimum height */
}

.text-view pre {
  white-space: pre-wrap; /* Wrap long lines */
  word-break: break-all; /* Break long words */
  background-color: #f4f4f4;
  padding: 1rem;
  border-radius: 5px;
  max-height: 600px;
  overflow-y: auto;
}

.loading-message, .error-message {
  text-align: center;
  padding: 2rem;
  font-size: 1.2rem;
  background-color: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
  border-radius: 8px;
  margin: 1rem;
}

.error-message a {
  color: #0056b3;
  font-weight: bold;
}
</style>
