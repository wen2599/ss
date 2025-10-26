<template>
  <div class="lottery-container">
    <h1>开奖公告</h1>
    <div v-if="loading" class="loading">正在加载...</div>
    <div v-if="error" class="error">{{ error }}</div>
    <div v-if="draws.length" class="results-table">
      <table>
        <thead>
          <tr>
            <th>彩票类型</th>
            <th>日期</th>
            <th>期数</th>
            <th>开奖号码</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="draw in draws" :key="draw.draw_period + draw.lottery_type">
            <td>{{ draw.lottery_type }}</td>
            <td>{{ draw.draw_date }}</td>
            <td>{{ draw.draw_period }}</td>
            <td class="numbers">
              <span v-for="(number, index) in draw.numbers.split(',')" :key="index" class="number-ball">{{ number }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else-if="!loading && !error">
      <p>目前没有开奖记录。</p>
    </div>
  </div>
</template>

<script>
import axios from 'axios'; // 使用 axios 替代 fetch

export default {
  name: 'LotteryView',
  data() {
    return {
      loading: true,
      error: null,
      draws: [],
    };
  },
  created() {
    this.fetchLotteryDraws();
  },
  methods: {
    async fetchLotteryDraws() {
      this.loading = true;
      try {
        // 修改为使用 route 参数，并使用 axios
        const response = await axios.get('/api.php?route=lottery-draws');
        if (response.data.status === 'success') {
          this.draws = response.data.data;
        } else {
          throw new Error(response.data.message || '获取数据失败');
        }
      } catch (e) {
        console.error("Error fetching lottery draws:", e);
        this.error = e.message || '无法获取开奖记录。';
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
  margin: 2rem auto;
  padding: 1rem;
  text-align: center;
}

.loading, .error {
  margin-top: 1rem;
  font-size: 1.2rem;
  color: #888;
}

.error {
  color: #e74c3c;
}

.results-table table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1rem;
}

thead {
  background-color: #f2f2f2;
}

th, td {
  border: 1px solid #ddd;
  padding: 12px;
  text-align: center;
}

.numbers {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 5px;
  flex-wrap: wrap;
}

.number-ball {
  display: inline-block;
  width: 35px;
  height: 35px;
  line-height: 35px;
  border-radius: 50%;
  background-color: #3498db;
  color: white;
  font-weight: bold;
  font-size: 1rem;
  text-align: center;
}
</style>
