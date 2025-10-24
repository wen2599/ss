<template>
  <div class="w-full max-w-4xl mx-auto">
    
    <!-- Page Title -->
    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">开奖结果</h1>

    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-12">
      <p class="text-lg text-gray-500 dark:text-gray-400">正在加载最新开奖结果...</p>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-center">
      {{ error }}
    </div>

    <!-- No Results State -->
    <div v-if="!isLoading && results.length === 0 && !error" class="text-center py-12">
      <p class="text-lg text-gray-500 dark:text-gray-400">暂无开奖结果可显示。</p>
    </div>

    <!-- Lottery Results Cards -->
    <div v-if="!isLoading && results.length > 0" class="space-y-8">
      <div v-for="result in results" :key="result.id" class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
        
        <!-- Card Header -->
        <div class="p-5 border-b border-gray-200 dark:border-gray-700">
          <div class="flex justify-between items-center">
            <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">{{ result.lottery_type }}</h2>
            <span class="px-3 py-1 text-sm font-medium bg-blue-100 text-blue-800 rounded-full dark:bg-blue-900 dark:text-blue-300">第 {{ result.issue_number }} 期</span>
          </div>
        </div>

        <!-- Card Body with Winning Numbers -->
        <div class="p-5">
          <div class="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-7 gap-4 text-center">
            <div 
              v-for="number in result.winning_numbers.split(',')" 
              :key="number" 
              class="flex flex-col items-center justify-center p-2 bg-gray-100 dark:bg-gray-700 rounded-lg"
            >
              <span class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ number }}</span>
              <span class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                {{ getNumberDetails(result.number_colors_json, number) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Card Footer -->
        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 text-right">
          <p class="text-sm text-gray-500 dark:text-gray-400">开奖日期: {{ new Date(result.draw_date).toLocaleString() }}</p>
        </div>
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
    // In some cases, the JSON string might be wrapped in quotes, so we parse it twice.
    const parsedOnce = JSON.parse(json);
    const data = typeof parsedOnce === 'string' ? JSON.parse(parsedOnce) : parsedOnce;
    
    const details = data[number];
    return details ? `${details.zodiac} / ${details.color}` : '';
  } catch (e) {
    console.error("Failed to parse number details JSON:", e, "Original JSON:", json);
    return ''; // Return empty string on parsing failure
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

<!-- No style block needed, all handled by Tailwind CSS -->
