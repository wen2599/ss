<template>
  <div class="email-detail-container">
    <button @click="goBack" class="back-button">&larr; 返回列表</button>
    <div v-if="loading" class="loading-message">正在加载邮件...</div>
    <div v-if="error" class="error-message">{{ error }}</div>
    <div v-if="email" class="email-content-wrapper">
      <div class="email-header-info">
        <h2>{{ email.subject || '无主题' }}</h2>
        <p><strong>发件人：</strong> {{ email.from_address }}</p>
        <p><strong>收件人：</strong> {{ email.to_address }}</p>
        <p><strong>接收时间：</strong> {{ formatDate(email.received_at) }}</p>
      </div>

      <div class="email-body-tabs">
        <button :class="{ active: activeTab === 'html' }" @click="activeTab = 'html'">HTML 视图</button>
        <button :class="{ active: activeTab === 'raw' }" @click="activeTab = 'raw'">原始文本</button>
        <button :class="{ active: activeTab === 'parsed' }" @click="activeTab = 'parsed'">解析数据 (JSON)</button>
      </div>

      <div class="email-body">
        <div v-if="activeTab === 'html'" class="html-view">
          <iframe :srcdoc="email.html_content || ''" @load="resizeIframe"></iframe>
        </div>
        <div v-if="activeTab === 'raw'" class="raw-view">
          <pre>{{ email.raw_content }}</pre>
        </div>
        <div v-if="activeTab === 'parsed'" class="parsed-view">
          <pre>{{ JSON.stringify(email.parsed_data, null, 2) }}</pre>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const router = useRouter()

const email = ref(null)
const loading = ref(true)
const error = ref(null)
const activeTab = ref('html')

const emailId = computed(() => route.params.id)
const API_URL = 'https://wenge.cloudns.ch/api';

async function fetchEmail() {
  if (!emailId.value) return;
  loading.value = true
  error.value = null
  try {
    const response = await axios.get(`${API_URL}/emails/${emailId.value}`);
    email.value = response.data
    // Default to HTML tab if content exists, otherwise RAW
    activeTab.value = response.data.html_content ? 'html' : 'raw';
  } catch (err) {
    console.error(`获取邮件 ${emailId.value} 时出错：`, err);
    error.value = '加载邮件详情失败。'
  }
  loading.value = false
}

function goBack() {
  router.push({ name: 'email-list' })
}

function formatDate(dateString) {
  if (!dateString) return '无';
  const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
  return new Date(dateString).toLocaleString('zh-CN', options);
}

function resizeIframe(event) {
  const iframe = event.target;
  if (iframe && iframe.contentWindow) {
    iframe.style.height = iframe.contentWindow.document.body.scrollHeight + 'px';
  }
}

onMounted(() => {
  fetchEmail()
})
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

.raw-view pre, .parsed-view pre {
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
}

.error-message {
  color: #d9534f;
}
</style>
