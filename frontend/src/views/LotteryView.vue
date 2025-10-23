<template>
  <div class="lottery-results-container">
    <h1>最新开奖结果</h1>
    <div v-if="isLoading" class="loading">正在加载...</div>
    <div v-if="error" class="error-message">{{ error }}</div>
    <div v-if="results.length" class="results-list">
      <div v-for="result in results" :key="result.id" class="result-card">
        <div class="card-header">
          <span class="lottery-type">{{ result.lottery_type }}</span>
          <span class="issue-number">第 {{ result.issue_number }} 期</span>
        </div>
        <div class="card-body">
          <div class="winning-numbers">
            <span
              v-for="(number, index) in result.winning_numbers.split(',')"
              :key="index"
              class="number-ball"
              :style="{ backgroundColor: getBallColor(result.number_colors_json, number) }"
            >
              {{ number }}
            </span>
          </div>
        </div>
        <div class="card-footer">
          <span class="draw-date">开奖日期: {{ new Date(result.draw_date).toLocaleString() }}</span>
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

const getBallColor = (colorsJson, number) => {
  try {
    const colors = JSON.parse(colorsJson);
    return colors[number] || '#ccc';
  } catch (e) {
    return '#ccc';
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
.lottery-results-container {
  max-width: 900px;
  margin: 0 auto;
  padding: 2rem;
  font-family: 'Helvetica Neue', Arial, sans-serif;
}
h1 {
  text-align: center;
  color: #333;
  margin-bottom: 2rem;
}
.loading, .no-results {
  text-align: center;
  font-size: 1.2rem;
  color: #666;
  margin-top: 3rem;
}
.error-message {
  color: #d9534f;
  background-color: #f2dede;
  border: 1px solid #ebccd1;
  padding: 1rem;
  border-radius: 4px;
  text-align: center;
}
.results-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1.5rem;
}
.result-card {
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  box-shadow: 0 4px 8px rgba(0,0,0,0.05);
  background-color: #fff;
  transition: transform 0.2s;
}
.result-card:hover {
  transform: translateY(-5px);
}
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  background-color: #f7f7f7;
  border-bottom: 1px solid #e0e0e0;
  border-top-left-radius: 8px;
  border-top-right-radius: 8px;
}
.lottery-type {
  font-weight: bold;
  font-size: 1.1rem;
  color: #333;
}
.issue-number {
  font-size: 0.9rem;
  color: #777;
}
.card-body {
  padding: 1.5rem 1rem;
}
.winning-numbers {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.5rem;
}
.number-ball {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  color: white;
  font-size: 1rem;
  font-weight: bold;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.card-footer {
  padding: 0.75rem 1rem;
  background-color: #f7f7f7;
  border-top: 1px solid #e0e0e0;
  text-align: center;
  font-size: 0.85rem;
  color: #777;
  border-bottom-left-radius: 8px;
  border-bottom-right-radius: 8px;
}
</style>
