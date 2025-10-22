<template>
  <div class="lottery-container">
    <h1>彩票开奖结果</h1>
    <div v-if="isLoading" class="loading">正在加载...</div>
    <div v-if="error" class="error-message">{{ error }}</div>
    <div v-if="!isLoading && results.length" class="results-grid">
      <div v-for="result in results" :key="result.id" class="result-card">
        <div class="card-header">
          <span class="lottery-label">{{ getLotteryLabel(result.lottery_type) }}</span>
          <h2>{{ result.lottery_type }} - 第 {{ result.issue_number }} 期</h2>
        </div>
        <div class="card-body">
          <p class="draw-time">开奖时间: {{ new Date(result.draw_time).toLocaleString() }}</p>
          <div class="winning-numbers">
            <span v-for="number in result.winning_numbers" :key="number" class="number-ball">
              {{ number }}
            </span>
          </div>
        </div>
      </div>
    </div>
    <div v-if="!isLoading && !results.length && !error" class="no-results">
      暂无开奖结果。
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '../api';

const results = ref([]);
const isLoading = ref(true);
const error = ref(null);

const getLotteryLabel = (type) => {
  const labels = {
    '老澳': '老澳',
    '新澳': '新澳',
    '香港': '香港',
  };
  return labels[type] || type;
};

onMounted(async () => {
  try {
    const response = await apiClient.get('/lottery-results');
    if (response.data.status === 'success') {
      results.value = response.data.data;
    } else {
      throw new Error('Failed to fetch lottery results.');
    }
  } catch (err) {
    error.value = err.response?.data?.message || '无法加载开奖结果，请稍后再试。';
  } finally {
    isLoading.value = false;
  }
});
</script>

<style scoped>
.lottery-container {
  max-width: 1200px;
  margin: 0 auto;
}
.loading, .no-results {
  text-align: center;
  font-size: 1.2rem;
  color: #666;
}
.error-message {
  color: #d9534f;
  text-align: center;
}
.results-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
}
.result-card {
  border: 1px solid #ddd;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  background-color: #fff;
}
.card-header {
  display: flex;
  align-items: center;
  padding: 1rem;
  background-color: #f7f7f7;
  border-bottom: 1px solid #ddd;
}
.lottery-label {
  background-color: #007bff;
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-weight: bold;
  margin-right: 1rem;
}
.card-header h2 {
  margin: 0;
  font-size: 1.2rem;
}
.card-body {
  padding: 1rem;
}
.draw-time {
  font-size: 0.9rem;
  color: #666;
  margin-bottom: 1rem;
}
.winning-numbers {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.number-ball {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background-color: #e9ecef;
  font-weight: bold;
  font-size: 1.1rem;
}
</style>
