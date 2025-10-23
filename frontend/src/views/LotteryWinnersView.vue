<template>
  <div class="lottery-winners-view">
    <h1>Lottery Winners</h1>
    <div v-if="loading" class="loading">Loading...</div>
    <div v-if="error" class="error">{{ error }}</div>
    <div v-if="winners.length > 0" class="winners-list">
      <table>
        <thead>
          <tr>
            <th>Username</th>
            <th>Prize</th>
            <th>Date</th>
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
    </div>
    <div v-else-if="!loading && !error">
      <p>No lottery winners to display at the moment.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '../api';

const winners = ref([]);
const loading = ref(true);
const error = ref(null);

onMounted(async () => {
  try {
    const response = await apiClient.get('/lottery-winners');
    if (response.data.status === 'success') {
      winners.value = response.data.data;
    } else {
      throw new Error(response.data.message || 'Failed to fetch lottery winners.');
    }
  } catch (err) {
    console.error('Error fetching lottery winners:', err);
    error.value = 'Could not load lottery winners. Please try again later.';
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.lottery-winners-view {
  max-width: 800px;
  margin: 2rem auto;
  padding: 2rem;
  background-color: #f9f9f9;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

h1 {
  text-align: center;
  margin-bottom: 2rem;
  color: #333;
}

.loading, .error {
  text-align: center;
  font-size: 1.2rem;
  padding: 1rem;
}

.error {
  color: #d9534f;
  background-color: #f2dede;
  border: 1px solid #ebccd1;
  border-radius: 4px;
}

.winners-list table {
  width: 100%;
  border-collapse: collapse;
}

.winners-list th, .winners-list td {
  border: 1px solid #ddd;
  padding: 12px;
  text-align: left;
}

.winners-list th {
  background-color: #f2f2f2;
  font-weight: bold;
}

.winners-list tr:nth-child(even) {
  background-color: #f9f9f9;
}

.winners-list tr:hover {
  background-color: #f1f1f1;
}
</style>
