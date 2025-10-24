<template>
  <div class="w-full max-w-4xl mx-auto">
    
    <!-- Page Title -->
    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">中奖名单</h1>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <p class="text-lg text-gray-500 dark:text-gray-400">正在加载中奖名单...</p>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-center">
      {{ error }}
    </div>

    <!-- No Winners State -->
    <div v-if="!loading && winners.length === 0 && !error" class="text-center py-12">
      <p class="text-lg text-gray-500 dark:text-gray-400">目前暂无中奖者信息。</p>
    </div>

    <!-- Winners Table -->
    <div v-if="!loading && winners.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <!-- Table Head -->
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">用户名</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">奖项</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">中奖日期</th>
            </tr>
          </thead>
          <!-- Table Body -->
          <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
            <tr v-for="winner in winners" :key="winner.id" class="hover:bg-gray-100 dark:hover:bg-gray-700/50">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ winner.username }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">{{ winner.prize }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">{{ new Date(winner.draw_date).toLocaleString() }}</td>
            </tr>
          </tbody>
        </table>
      </div>
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
    error.value = '无法加载中奖名单，请稍后再试。';
  } finally {
    loading.value = false;
  }
});
</script>

<!-- No style block needed, all handled by Tailwind CSS -->
