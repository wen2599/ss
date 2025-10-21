<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '@/stores/api';

const apiStatus = ref('Checking...');
const apiMessage = ref('');

onMounted(async () => {
  try {
    const response = await apiClient.get('/ping');
    apiStatus.value = response.data.status || 'ok';
    apiMessage.value = response.data.message || '';
  } catch (error) {
    apiStatus.value = 'error';
    apiMessage.value = 'Failed to connect to the backend API.';
    console.error(error);
  }
});
</script>

<template>
  <main>
    <h1>Welcome to Email Form Processor</h1>
    <p>This application automatically processes incoming emails and converts them into structured data.</p>
    <div class="status-card">
      <h3>Backend API Status</h3>
      <p>Status: <span :class="apiStatus">{{ apiStatus }}</span></p>
      <p v-if="apiMessage">Message: {{ apiMessage }}</p>
    </div>
  </main>
</template>

<style scoped>
main {
  padding: 2rem;
  text-align: center;
}
.status-card {
  margin-top: 2rem;
  display: inline-block;
  padding: 1.5rem 2rem;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  background-color: #fafafa;
}
.status-card h3 {
  margin-bottom: 1rem;
}
.ok {
  color: #28a745;
  font-weight: bold;
}
.error {
  color: #dc3545;
  font-weight: bold;
}
</style>