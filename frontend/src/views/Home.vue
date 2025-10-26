<template>
  <div class="container">
    <div class="welcome-banner">
      <h1>我的邮件收件箱</h1>
      <p>这里是您通过邮件自动处理和提取的所有记录。</p>
    </div>

    <div v-if="loading" class="loading-state">
      <p>正在从服务器加载您的邮件...</p>
    </div>

    <div v-else-if="error" class="error-state">
      <p>加载数据时出错：{{ error }}</p>
    </div>

    <div v-else-if="records.length === 0 && !latestDraw" class="empty-state">
      <p>您的收件箱是空的，也没有最新的开奖号码。</p>
      <p>一旦您配置的邮箱地址收到新的邮件，它们将自动出现在这里。</p>
    </div>

    <!-- 最新开奖号码显示区域 -->
    <div v-if="latestDraw" class="latest-draw-card">
      <div class="card-header">
        <h3 class="subject">{{ latestDraw.lottery_type }} 第:{{ latestDraw.draw_period }}期 开奖结果</h3>
        <span class="timestamp">{{ formatDateTime(latestDraw.draw_date) }}</span>
      </div>
      <div class="card-body">
        <p class="draw-numbers"><strong>开奖号码:</strong> {{ latestDraw.numbers }}</p>
        <p class="recorded-at">记录时间: {{ formatDateTime(latestDraw.recorded_at) }}</p>
      </div>
    </div>

    <div v-if="records.length > 0" class="records-grid">
      <div v-for="(record, index) in records" :key="index" class="record-card">
        <div class="card-header">
          <h3 class="subject">{{ record.subject }}</h3>
          <span class="timestamp">{{ formatDateTime(record.received_at) }}</span>
        </div>
        <div class="card-body">
          <p class="from"><strong>发件人:</strong> {{ record.from_address }}</p>
          <div v-if="record.extracted_data" class="extracted-data">
            <h4>提取的数据:</h4>
            <pre>{{ JSON.stringify(record.extracted_data, null, 2) }}</pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import recordsService from '@/services/records.js';
import lotteryService from '@/services/lotteryService.js'; // 导入新的开奖服务

export default {
  name: 'HomeView',
  data() {
    return {
      records: [],
      latestDraw: null, // 新增：存储最新开奖号码
      loading: true,
      error: null,
    };
  },
  async created() {
    await this.fetchLatestLotteryDraw(); // 获取最新开奖号码
    await this.fetchUserRecords();
  },
  methods: {
    async fetchUserRecords() {
      // this.loading = true; // 避免重复设置loading，由fetchLatestLotteryDraw或created统一管理
      this.error = null;
      try {
        const response = await recordsService.getMyRecords();
        this.records = response.data;
      } catch (err) {
        console.error('获取用户记录失败:', err);
        if (err.response && err.response.status !== 401) {
             this.error = err.response.data.message || '无法连接到服务器。';
        }
      } finally {
        // this.loading = false; // 避免重复设置loading
      }
    },
    async fetchLatestLotteryDraw() {
      this.loading = true;
      this.error = null;
      try {
        const response = await lotteryService.getLatestDraw();
        if (response.status === 'success') {
          this.latestDraw = response.data;
        } else {
          console.warn('No latest lottery draw found or error:', response.message);
          // 不将404视为错误，只表示没有数据
        }
      } catch (err) {
        console.error('获取最新开奖号码失败:', err);
        this.error = err.message || '无法获取最新开奖号码。';
      } finally {
        this.loading = false;
      }
    },
    formatDateTime(dateTimeString) {
      if (!dateTimeString) return '未知时间';
      const options = {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
      };
      return new Date(dateTimeString).toLocaleString('zh-CN', options);
    }
  }
};
</script>

<style scoped>
.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1rem;
}

.welcome-banner {
  margin-bottom: 2rem;
  padding: 1.5rem;
  background-color: #ffffff;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.welcome-banner h1 {
  margin: 0 0 0.5rem 0;
  font-size: 2rem;
}

.welcome-banner p {
  margin: 0;
  color: #6b7280;
}

.loading-state, .error-state, .empty-state {
  text-align: center;
  padding: 4rem 2rem;
  background-color: #ffffff;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  margin-top: 2rem;
}

.loading-state p, .error-state p, .empty-state p {
    margin: 0.5rem 0;
    font-size: 1.125rem;
    color: #4a5568;
}

.records-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.5rem;
}

.record-card {
  background-color: #ffffff;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  transition: transform 0.2s, box-shadow 0.2s;
}

.record-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0,0,0,0.1);
}

.card-header {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #f3f4f6;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.subject {
  font-size: 1.125rem;
  font-weight: 600;
  margin: 0;
  color: #1f2937;
  word-break: break-word;
}

.timestamp {
  font-size: 0.8rem;
  color: #6b7280;
  flex-shrink: 0;
  margin-left: 1rem;
}

.card-body {
  padding: 1rem 1.5rem;
}

.from {
  font-size: 0.9rem;
  color: #4b5563;
  margin: 0 0 1rem 0;
}

.extracted-data h4 {
  font-size: 0.9rem;
  font-weight: 600;
  color: #374151;
  margin: 0 0 0.5rem 0;
}

.extracted-data pre {
  background-color: #f9fafb;
  padding: 0.75rem;
  border-radius: 6px;
  white-space: pre-wrap;
  word-break: break-all;
  font-family: 'Menlo', 'Consolas', monospace;
  font-size: 0.85rem;
  color: #374151;
}

/* New styles for latest draw card */
.latest-draw-card {
  background-color: #e6f7ff; /* Light blue background */
  border-left: 5px solid #1890ff; /* Blue border on the left */
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  margin-bottom: 2rem;
  padding: 1.5rem;
}

.latest-draw-card .card-header {
  border-bottom: 1px solid #a0daff;
  margin-bottom: 1rem;
  padding: 0 0 1rem 0; /* Adjust padding */
}

.latest-draw-card .subject {
  color: #1890ff; /* Blue color for subject */
  font-size: 1.25rem;
}

.latest-draw-card .timestamp {
  color: #40a9ff;
}

.latest-draw-card .card-body {
  padding: 0; /* Adjust padding */
}

.latest-draw-card .draw-numbers {
  font-size: 1.1rem;
  font-weight: 700;
  color: #333;
  margin-bottom: 0.5rem;
}

.latest-draw-card .recorded-at {
  font-size: 0.85rem;
  color: #666;
}
</style>
