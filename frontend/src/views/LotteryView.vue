<template>
  <div class="lottery-view">
    <div v-if="isLoading" class="status-message loading">正在加载最新开奖结果...</div>
    <div v-if="error" class="status-message error-message">{{ error }}</div>
    
    <!-- The New Hero Banner for the Latest Result -->
    <div v-if="latestResult" class="lottery-banner">
      <div class="banner-header">
        <h1>{{ latestResult.lottery_type }}</h1>
        <p class="issue-number">第 {{ latestResult.issue_number }} 期</p>
      </div>

      <div class="banner-body">
        <div class="winning-numbers-grid">
          <div 
            v-for="(number, index) in latestResult.winning_numbers.split(',')" 
            :key="index" 
            class="number-display"
          >
            <span class="number-value">{{ number }}</span>
            <span class="number-color-label" :style="{ backgroundColor: getBallColor(latestResult.number_colors_json, number) }">
              {{ getBallColorName(latestResult.number_colors_json, number) }}
            </span>
          </div>
        </div>
      </div>

      <div class="banner-footer">
        <p>开奖日期: {{ new Date(latestResult.draw_date).toLocaleString() }}</p>
      </div>
    </div>

    <div v-if="!isLoading && !latestResult && !error" class="status-message no-results">
      暂无开奖结果可显示。
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import apiClient from '../api';

const results = ref([]);
const isLoading = ref(true);
const error = ref(null);

// A computed property to always get the latest result (the first in the array)
const latestResult = computed(() => results.value.length > 0 ? results.value[0] : null);

// Parses the color from the JSON string
const getBallColor = (colorsJson, number) => {
  try {
    const colors = JSON.parse(colorsJson);
    return colors[number] || '#333'; // Default color if not found
  } catch (e) {
    return '#333';
  }
};

// A new function to get the color *name* (assuming the value in the JSON is the color name)
const getBallColorName = (colorsJson, number) => {
  try {
    const colors = JSON.parse(colorsJson);
    // This is a placeholder. You might need to map color hex codes to names.
    // For now, let's just return the hex code, but a real implementation would be better.
    const colorValue = colors[number];
    // Simple example of mapping
    const colorMap = {
      'red': '红色',
      'blue': '蓝色',
      'green': '绿色'
    };
    return colorMap[colorValue] || colorValue; // Return name or the value itself
  } catch (e) {
    return '未知';
  }
};

onMounted(async () => {
  try {
    const response = await apiClient.get('/lottery-results');
    if (response.data.status === 'success') {
      // Assuming the API returns results sorted from newest to oldest
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
  justify-content: center;
  min-height: 60vh;
}

.status-message {
  text-align: center;
  font-size: 1.2rem;
  color: var(--color-text-secondary);
  margin-top: 3rem;
}

.error-message {
  color: #ff6b6b; /* A more theme-appropriate error color */
}

.lottery-banner {
  width: 100%;
  max-width: 800px;
  background-color: var(--color-surface);
  border-radius: 12px;
  border-left: 8px solid var(--color-primary);
  box-shadow: var(--shadow-elevation-medium);
  padding: 2rem 2.5rem;
  display: flex;
  flex-direction: column;
  gap: 2rem;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.lottery-banner:hover {
  transform: translateY(-5px);
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.6);
}

.banner-header {
  text-align: center;
  border-bottom: 1px solid var(--color-border);
  padding-bottom: 1rem;
}

.banner-header h1 {
  color: var(--color-primary);
  font-size: 2.2rem;
  margin-bottom: 0.25rem;
}

.issue-number {
  color: var(--color-text-secondary);
  font-size: 1.1rem;
}

.winning-numbers-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
  gap: 1.5rem;
}

.number-display {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: var(--color-background);
  border-radius: 8px;
  padding: 1rem 0.5rem;
  border: 1px solid var(--color-border);
  box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);
}

.number-value {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--color-text-primary);
  line-height: 1.1;
}

.number-color-label {
  font-size: 0.8rem;
  font-weight: 500;
  color: #000;
  padding: 0.2rem 0.6rem;
  border-radius: 12px;
  margin-top: 0.5rem;
  text-transform: uppercase;
}

.banner-footer {
  text-align: center;
  color: var(--color-text-secondary);
  font-size: 0.9rem;
  padding-top: 1rem;
  border-top: 1px solid var(--color-border);
}
</style>
