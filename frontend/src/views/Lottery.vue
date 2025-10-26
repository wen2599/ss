<template>
  <div class="lottery-container">
    <div v-if="loading" class="loading-message">
      正在加载...
    </div>
    <div v-if="error" class="error-message">
      {{ error }}
    </div>
    <div v-if="draws.length > 0" class="draws-list">
      <div v-for="draw in draws" :key="draw.lottery_type" class="lottery-card">
        <h2>{{ draw.lottery_type }}</h2>
        <div class="draw-details">
          <p><strong>期号:</strong> {{ draw.draw_period }}</p>
          <p><strong>开奖日期:</strong> {{ draw.draw_date }}</p>
          <p><strong>开奖号码:</strong> {{ draw.numbers }}</p>
          <p><strong>生肖:</strong> {{ draw.zodiacs }}</p>
          <p><strong>颜色:</strong> {{ draw.colors }}</p>
        </div>
      </div>
    </div>
    <div v-if="!loading && draws.length === 0 && !error" class="no-data-message">
        暂无开奖数据。
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'LotteryView',
  data() {
    return {
      draws: [],
      loading: false,
      error: null,
    };
  },
  created() {
    this.fetchLatestDraws();
  },
  methods: {
    async fetchLatestDraws() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get('/api/get_all_latest_draws.php');
        this.draws = response.data;
      } catch (err) {
        this.error = '无法加载开奖号码，请稍后再试。';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.lottery-container {
  max-width: 800px;
  margin: 0 auto;
  padding: 1rem;
}
.lottery-card {
  background-color: white;
  padding: 2rem;
  border-radius: 8px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
}
h2 {
  margin-top: 0;
  margin-bottom: 1.5rem;
  font-size: 1.75rem;
  font-weight: 600;
  text-align: center;
}
.draw-details {
  font-size: 1.1rem;
  line-height: 1.6;
}
.draw-details p {
    margin: 0.5rem 0;
}
.loading-message, .error-message, .no-data-message {
  text-align: center;
  font-size: 1.2rem;
  padding: 2rem;
}
.error-message {
  color: #ef4444;
}
</style>
