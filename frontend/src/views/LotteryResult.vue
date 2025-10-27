<template>
  <div class="lottery-result-container">
    <h1>最新彩票开奖结果</h1>

    <div v-if="loading" class="loading-state card">加载开奖结果中...</div>
    <div v-if="error" class="error-state card">加载开奖结果失败: {{ error }}</div>

    <div v-if="latestDraw" class="lottery-content card">
      <div class="lottery-header">
        <span class="lottery-type">彩票类型: {{ latestDraw.lottery_type }}</span>
        <span class="lottery-period">期号: {{ latestDraw.draw_period }}</span>
      </div>
      <div class="lottery-numbers">
        <span v-for="(number, index) in latestDraw.numbers.split(',')" :key="index" class="number-ball">
          {{ number }}
        </span>
      </div>
      <div class="lottery-footer">
        <span>开奖日期: {{ formatDate(latestDraw.draw_date) }}</span>
        <span v-if="latestDraw.recorded_at">记录时间: {{ formatDateTime(latestDraw.recorded_at) }}</span>
      </div>
    </div>
    <div v-else-if="!loading && !error" class="empty-state card">暂无彩票开奖数据。</div>
  </div>
</template>

<script>
import lotteryService from '@/services/lotteryService.js';

export default {
  name: 'LotteryResult',
  data() {
    return {
      latestDraw: null,
      loading: true,
      error: null,
    };
  },
  async created() {
    await this.fetchLatestDraw();
  },
  methods: {
    async fetchLatestDraw() {
      this.loading = true;
      this.error = null;
      try {
        const response = await lotteryService.getLatestDraw();
        if (response.status === 'success') {
          this.latestDraw = response.data;
        } else {
          this.error = response.message || '未能获取开奖数据。';
        }
      } catch (err) {
        console.error('获取最新开奖结果失败:', err);
        this.error = '无法连接到服务器或开奖数据不可用。';
      } finally {
        this.loading = false;
      }
    },
    formatDate(dateString) {
      if (!dateString) return 'N/A';
      return new Date(dateString).toLocaleDateString('zh-CN');
    },
    formatDateTime(dateTimeString) {
      if (!dateTimeString) return 'N/A';
      return new Date(dateTimeString).toLocaleString('zh-CN', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
      });
    }
  },
};
</script>

<style scoped>
.lottery-result-container {
  padding: 2rem;
  max-width: 800px;
  margin: 0 auto;
}

h1 {
  color: var(--primary-accent);
  text-align: center;
  margin-bottom: 2rem;
}

.card {
  background-color: var(--background-secondary);
  border-radius: 8px;
  box-shadow: var(--shadow-elevation-low);
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.loading-state,
.error-state,
.empty-state {
  text-align: center;
  padding: 2rem;
  font-size: 1.1rem;
  color: var(--text-secondary);
}

.error-state {
  background-color: var(--error-color);
  color: #fff;
}

.lottery-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.5rem;
}

.lottery-header {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 1rem;
}

.lottery-type {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--primary-accent);
}

.lottery-period {
  font-size: 1rem;
  color: var(--text-secondary);
}

.lottery-numbers {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  justify-content: center;
  margin-bottom: 1.5rem;
}

.number-ball {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background-color: var(--background-tertiary);
  color: var(--text-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1.5rem;
  border: 2px solid var(--primary-accent);
}

.lottery-footer {
  width: 100%;
  text-align: center;
  font-size: 0.9rem;
  color: var(--text-secondary);
  border-top: 1px solid var(--border-color);
  padding-top: 1rem;
  display: flex;
  justify-content: space-between;
}
</style>
