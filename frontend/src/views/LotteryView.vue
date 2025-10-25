<template>
  <div>
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-800">最新开奖</h1>
      <p class="text-gray-500 mt-1">查看最新的官方开奖结果</p>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex justify-center items-center py-20">
      <div class="loader ease-linear rounded-full border-4 border-t-4 border-gray-200 h-12 w-12"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="card text-center p-8 bg-red-50 border-red-200">
      <h3 class="text-lg font-semibold text-red-700">加载失败</h3>
      <p class="text-red-600 mt-2">{{ error }}</p>
    </div>

    <!-- No Results State -->
    <div v-else-if="results.length === 0" class="card text-center p-8">
      <h3 class="text-lg font-semibold text-gray-700">暂无结果</h3>
      <p class="text-gray-500 mt-2">目前没有最新的开奖结果可供显示。</p>
    </div>

    <!-- Results Grid -->
    <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
      <div v-for="result in results" :key="result.id" class="card hover:shadow-xl transition-shadow duration-300">
        <!-- Card Header -->
        <div class="p-5 border-b">
          <h2 class="text-xl font-bold text-gray-800">{{ result.lottery_type }}</h2>
          <p class="text-sm text-gray-500">第 {{ result.issue_number }} 期</p>
        </div>

        <!-- Numbers Grid -->
        <div class="p-5">
          <div class="grid grid-cols-4 gap-3 text-center">
            <div v-for="number in result.winning_numbers.split(',')" :key="number" class="relative">
              <div class="aspect-square flex flex-col items-center justify-center bg-gray-100 rounded-full shadow-inner">
                <span class="text-2xl font-bold text-primary">{{ number }}</span>
              </div>
              <div class="text-xs text-gray-500 mt-1 truncate">
                {{ getNumberDetails(result.number_colors_json, number) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Card Footer -->
        <div class="p-4 bg-gray-50 border-t">
          <p class="text-xs text-gray-500 text-right">
            开奖日期: {{ new Date(result.draw_date).toLocaleString('zh-CN') }}
          </p>
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
