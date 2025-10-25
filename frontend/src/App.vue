
<template>
  <div id="app">
    <div class="container">
      <h1>邮件数据</h1>
      <div v-if="loading" class="loading">正在加载数据...</div>
      <div v-if="error" class="error">{{ error }}</div>
      <div v-if="!loading && !error && records.length === 0" class="loading">暂无数据。</div>
      <div v-if="records.length > 0" class="table-container">
        <table>
          <thead>
            <tr>
              <th>接收时间</th>
              <th>发件人</th>
              <th>主题</th>
              <th v-for="header in extractedHeaders" :key="header">{{ headerMapping[header] || header }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(record, index) in records" :key="index">
              <td>{{ formatDateTime(record.received_at) }}</td>
              <td>{{ record.from || 'N/A' }}</td>
              <td>{{ record.subject || 'N/A' }}</td>
              <td v-for="header in extractedHeaders" :key="header">
                {{ record.extracted_data ? (record.extracted_data[header] || 'N/A') : 'N/A' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      records: [],
      loading: true,
      error: null,
      apiUrl: 'https://wenge.cloudns.ch/api/get-records', 
      headerMapping: {
          'vendor': '供应商',
          'invoice_number': '账单号',
          'amount': '金额',
          'due_date': '截止日期'
      }
    };
  },
  computed: {
    extractedHeaders() {
      if (this.records.length === 0) {
        return [];
      }
      // Dynamically get headers from the first record's extracted_data
      return this.records[0].extracted_data ? Object.keys(this.records[0].extracted_data) : [];
    }
  },
  methods: {
    fetchData() {
      fetch(this.apiUrl)
        .then(response => {
          if (!response.ok) {
            throw new Error(`网络错误: ${response.statusText}`);
          }
          return response.json();
        })
        .then(data => {
          // Sort data by received_at date in descending order
          this.records = data.sort((a, b) => new Date(b.received_at) - new Date(a.received_at));
          this.loading = false;
        })
        .catch(error => {
          console.error('Fetch error:', error);
          this.error = '无法加载数据。请检查后端服务是否正常以及API路径是否正确。';
          this.loading = false;
        });
    },
    formatDateTime(dateTimeString) {
        if (!dateTimeString) return 'N/A';
        return new Date(dateTimeString).toLocaleString('zh-CN');
    }
  },
  created() {
    this.fetchData();
  }
};
</script>

<style>
/* Global styles are now in src/assets/main.css */
</style>
