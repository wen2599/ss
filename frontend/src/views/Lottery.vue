<template>
  <div class="lottery-container">
    <div class="lottery-card">
      <h2>最新开奖号码</h2>
      <div v-if="loading" class="loading-message">
        正在加载...
      </div>
      <div v-if="error" class="error-message">
        {{ error }}
      </div>
      <div v-if="draw" class="draw-details">
        <p><strong>期号:</strong> {{ draw.draw_number }}</p>
        <p><strong>开奖日期:</strong> {{ draw.draw_date }}</p>
        <p><strong>开奖号码:</strong> {{ draw.numbers }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'LotteryView',
  data() {
    return {
      draw: null,
      loading: false,
      error: null,
    };
  },
  created() {
    this.fetchLatestDraw();
  },
  methods: {
    async fetchLatestDraw() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get('/api/get-latest-draw.php');
        this.draw = response.data;
      } catch (err) {
        if (err.response && err.response.data && err.response.data.message) {
          this.error = err.response.data.message;
        } else {
          this.error = '无法加载开奖号码，请稍后再试。';
        }
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.lottery-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: calc(100vh - 80px);
  background-color: #f3f4f6;
}
.lottery-card {
  width: 100%;
  max-width: 400px;
  padding: 2rem;
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  text-align: center;
}
h2 {
  margin-bottom: 1.5rem;
  font-size: 1.75rem;
  font-weight: 600;
}
.loading-message, .error-message {
  margin-top: 1rem;
  margin-bottom: 1rem;
  font-size: 1rem;
}
.error-message {
  color: #ef4444;
}
.draw-details {
  font-size: 1.25rem;
}
</style>
