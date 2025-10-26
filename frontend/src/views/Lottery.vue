<template>
  <div class="lottery-container">
    <h1>开奖公告</h1>
    <div v-if="loading" class="loading">正在加载...</div>
    <div v-if="error" class="error">{{ error }}</div>
    <div v-if="draws.length" class="results-table">
      <table>
        <thead>
          <tr>
            <th>日期</th>
            <th>期数</th>
            <th>开奖号码</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="draw in draws" :key="draw.draw_period">
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
        const response = await fetch('https://ss.wenxiuxiu.eu.org/api.php?request=lottery-draws');
        if (!response.ok) {
          throw new Error('网络响应错误');
        }
        const result = await response.json();
        if (result.status === 'success') {
          this.draws = result.data;
        } else {
          throw new Error(result.message || '获取数据失败');
        }
      } catch (e) {
        this.error = e.message;
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
