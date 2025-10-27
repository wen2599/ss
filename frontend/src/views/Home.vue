<template>
  <div class="home-container">
    <div class="navigation-bar card">
      <router-link to="/mail-original" class="nav-item">邮件原文</router-link>
      <router-link to="/mail-organize" class="nav-item">邮件整理</router-link>
      <router-link to="/lottery-result" class="nav-item">彩票开奖</router-link>
    </div>
    
    <!-- Header Section -->
    <div class="welcome-header card">
      <h1>Dashboard</h1>
      <p>Welcome back! Here's the latest lottery draw and your email inbox.</p>
    </div>

    <!-- Loading and Error States -->
    <div v-if="loading" class="state-card card">Loading data...</div>
    <div v-if="error" class="state-card error-card card">
      Error loading data: {{ error }}
    </div>

    <!-- Main Content Grid -->
    <div v-if="!loading && !error" class="main-grid">
      
      <!-- Latest Lottery Draw -->
      <div class="latest-draw-section card">
        <h2>Latest Lottery Draw</h2>
        <div v-if="latestDraw" class="lottery-content">
          <div class="lottery-header">
            <span class="lottery-type">{{ latestDraw.lottery_type }}</span>
            <span class="lottery-period">Period: {{ latestDraw.draw_period }}</span>
          </div>
          <div class="lottery-numbers">
            <span v-for="(number, index) in latestDraw.numbers.split(',')" :key="index" class="number-ball">
              {{ number }}
            </span>
          </div>
          <div class="lottery-footer">
            <span>Draw Date: {{ formatDate(latestDraw.draw_date) }}</span>
          </div>
        </div>
        <div v-else class="empty-state">No lottery draw data available.</div>
      </div>

      <!-- Email Records Section -->
      <div class="email-records-section card">
        <h2>Email Inbox</h2>
        <div v-if="records.length > 0" class="records-list">
          <div v-for="record in records" :key="record.id" class="record-item">
            <div class="record-header">
              <span class="from">{{ record.from_address }}</span>
              <span class="timestamp">{{ formatDateTime(record.received_at) }}</span>
            </div>
            <div class="record-body">
              <h3 class="subject">{{ record.subject }}</h3>
              <div v-if="record.extracted_data && Object.keys(record.extracted_data).length > 0" class="extracted-data">
                <h4>Extracted Data:</h4>
                <pre>{{ JSON.stringify(record.extracted_data, null, 2) }}</pre>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="empty-state">Your inbox is empty.</div>
      </div>
    </div>
  </div>
</template>

<script>
import recordsService from '@/services/records.js';
import lotteryService from '@/services/lotteryService.js';

export default {
  name: 'HomeView',
  data() {
    return {
      records: [],
      latestDraw: null,
      loading: true,
      error: null,
    };
  },
  async created() {
    this.fetchAllData();
  },
  methods: {
    async fetchAllData() {
      this.loading = true;
      this.error = null;
      try {
        const [drawResponse, recordsResponse] = await Promise.all([
          lotteryService.getLatestDraw(),
          recordsService.getMyRecords()
        ]);

        if (drawResponse.status === 'success') {
          this.latestDraw = drawResponse.data;
        }

        this.records = recordsResponse.data;

      } catch (err) {
        console.error('Failed to fetch data:', err);
        this.error = 'Could not connect to the server or data is unavailable.';
      } finally {
        this.loading = false;
      }
    },
    formatDate(dateString) {
      if (!dateString) return 'N/A';
      return new Date(dateString).toLocaleDateString('en-CA');
    },
    formatDateTime(dateTimeString) {
      if (!dateTimeString) return 'N/A';
      return new Date(dateTimeString).toLocaleString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
      });
    }
  }
};
</script>

<style scoped>
.home-container {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.navigation-bar {
  display: flex;
  justify-content: center;
  gap: 1.5rem;
  padding: 1rem;
  border-radius: 8px;
  background-color: var(--background-secondary);
  box-shadow: var(--shadow-elevation-low);
}

.nav-item {
  color: var(--primary-accent);
  text-decoration: none;
  font-weight: 600;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  transition: background-color 0.2s ease;
}

.nav-item:hover {
  background-color: var(--background-tertiary);
}

.welcome-header {
  border-left: 5px solid var(--primary-accent);
}

.state-card {
  text-align: center;
  padding: 2rem;
  font-size: 1.2rem;
}

.error-card {
  background-color: var(--error-color);
  color: #fff;
}

.main-grid {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 2rem;
}

/* Latest Lottery Draw Styles */
.latest-draw-section {
  display: flex;
  flex-direction: column;
}

.lottery-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.lottery-type {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--primary-accent);
}

.lottery-period {
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.lottery-numbers {
  display: flex;
  gap: 0.5rem;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 1.5rem;
}

.number-ball {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background-color: var(--background-tertiary);
  color: var(--text-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 1.1rem;
}

.lottery-footer {
  text-align: center;
  font-size: 0.9rem;
  color: var(--text-secondary);
}

/* Email Records Styles */
.email-records-section {
  display: flex;
  flex-direction: column;
}

.records-list {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.record-item {
  background-color: var(--background-tertiary);
  padding: 1rem;
  border-radius: 8px;
  border-left: 3px solid var(--primary-accent);
}

.record-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.subject {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1rem;
}

.extracted-data pre {
  background-color: var(--background-primary);
  padding: 0.75rem;
  border-radius: 6px;
  white-space: pre-wrap;
  word-break: break-all;
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.empty-state {
  text-align: center;
  color: var(--text-secondary);
  padding: 2rem;
}
</style>
