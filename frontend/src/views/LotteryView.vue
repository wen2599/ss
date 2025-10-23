<template>
  <div class="lottery-container">
    <h1>中奖光荣榜</h1>
    <div v-if="isLoading" class="loading">正在加载...</div>
    <div v-if="error" class="error-message">{{ error }}</div>
    <table v-if="winners.length" class="winners-table">
      <thead>
        <tr>
          <th>用户名</th>
          <th>奖品</th>
          <th>抽奖日期</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="winner in winners" :key="winner.id">
          <td>{{ winner.username }}</td>
          <td>{{ winner.prize }}</td>
          <td>{{ new Date(winner.draw_date).toLocaleString() }}</td>
        </tr>
      </tbody>
    </table>
    <div v-if="!isLoading && !winners.length && !error" class="no-results">
      暂无中奖结果。
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '../api';

const winners = ref([]);
const isLoading = ref(true);
const error = ref(null);

onMounted(async () => {
  try {
    const response = await apiClient.get('/lottery_results.php');
    if (response.data.status === 'success') {
      winners.value = response.data.data;
    } else {
      throw new Error('Failed to fetch lottery results.');
    }
  } catch (err) {
    error.value = err.response?.data?.message || '无法加载中奖结果，请稍后再试。';
  } finally {
    isLoading.value = false;
  }
});
</script>

<style scoped>
.lottery-container {
  max-width: 800px;
  margin: 0 auto;
  padding: 2rem;
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
.winners-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 2rem;
}
.winners-table th, .winners-table td {
  border: 1px solid #ddd;
  padding: 0.75rem;
  text-align: left;
}
.winners-table th {
  background-color: #f7f7f7;
  font-weight: bold;
}
</style>
