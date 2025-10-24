<template>
  <div class="lottery-view">
    <div v-if="isLoading" class="status-message loading">正在加载最新开奖结果...</div>
    <div v-if="error" class="status-message error-message">{{ error }}</div>

    <div v-if="!isLoading && results.length === 0 && !error" class="status-message no-results">
      暂无开奖结果可显示。
    </div>

    <div v-for="result in results" :key="result.id" class="lottery-banner">
      <div class="banner-header">
        <h1>{{ result.lottery_type }}</h1>
        <p class="issue-number">第 {{ result.issue_number }} 期</p>
      </div>
      <div class="banner-body">
        <div class="winning-numbers-grid">
          <div v-for="number in result.winning_numbers.split(',')" :key="number" class="number-display">
            <span class="number-value">{{ number }}</span>
            <span class="number-details">
              {{ getNumberDetails(result.number_colors_json, number) }}
            </span>
          </div>
        </div>
      </div>
      <div class="banner-footer">
        <p>开奖日期: {{ new Date(result.draw_date).toLocaleString() }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '../api';

const results = ref([]);
const isLoading = ref(true);
const error = ref(null);

const getNumberDetails = (json, number) => {
  if (!json) return '';
  try {
    const data = JSON.parse(json);
    const details = data[number];
    return details ? `${details.zodiac} / ${details.color}` : '';
  } catch (e) {
    return '';
  }
};

onMounted(async () => {
  try {
    const response = await apiClient.get('/lottery-results');
    if (response.data.status === 'success') {
      results.value = response.data.data;
    } else {
      throw new Error(response.data.message || 'Failed to fetch lottery results.');
    }
  } catch (err) {
    error.value = err.response?.data?.message || '无法加载开奖结果，请稍后再试。';
  } finally {
    isLoading.value = false;
  }
});
</script>

<style scoped>
.lottery-view {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2rem;
  padding: 2rem;
}
.status-message {
  text-align: center;
  font-size: 1.2rem;
  color: #888;
}
.error-message {
  color: #e74c3c;
}
.lottery-banner {
  width: 100%;
  max-width: 800px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  padding: 1.5rem;
}
.banner-header {
  text-align: center;
  margin-bottom: 1rem;
}
.banner-header h1 {
  font-size: 1.8rem;
  margin: 0;
}
.issue-number {
  font-size: 1rem;
  color: #555;
}
.winning-numbers-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 0.5rem;
  text-align: center;
}
.number-display {
  background: #f0f0f0;
  border-radius: 4px;
  padding: 0.5rem;
}
.number-value {
  font-size: 1.5rem;
  font-weight: bold;
}
.number-details {
  font-size: 0.8rem;
  color: #555;
}
.banner-footer {
  text-align: right;
  font-size: 0.9rem;
  color: #777;
  margin-top: 1rem;
}
</style>
