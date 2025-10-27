<template>
  <div class="mail-organize-container">
    <h1>邮件整理与结算</h1>

    <div v-if="loading" class="loading-state card">加载邮件中...</div>
    <div v-if="globalError" class="error-state card">{{ globalError }}</div>

    <div class="content-wrapper">
      <!-- Email List -->
      <div class="emails-list-panel">
        <div v-if="emails.length > 0">
          <div v-for="email in emails" :key="email.id" class="email-item card" :class="{ 'selected': selectedEmail && selectedEmail.id === email.id, 'processed': email.is_processed }">
            <div class="email-header">
              <span class="email-from">{{ email.from_address }}</span>
              <span class="email-date">{{ formatDateTime(email.received_at) }}</span>
            </div>
            <h2 class="email-subject">{{ email.subject }}</h2>
            <div class="status-and-button">
                <span v-if="email.is_processed" class="processed-badge">已处理</span>
                <button @click="generateSettlement(email)" class="ai-organize-button" :disabled="isAiLoading">
                    <span v-if="isAiLoading && selectedEmail && selectedEmail.id === email.id">处理中...</span>
                    <span v-else>AI 生成结算单</span>
                </button>
            </div>
          </div>
        </div>
        <div v-else-if="!loading" class="empty-state card">无待处理邮件。</div>
      </div>

      <!-- Settlement Slip Panel -->
      <div v-if="selectedEmail" class="settlement-panel card">
        <h2>结算单 ({{ selectedEmail.subject }})</h2>
        
        <div class="settlement-header">
          <div class="form-group">
            <label>客户名</label>
            <input type="text" v-model="settlement.customer_name" class="form-control" />
          </div>
          <div class="form-group">
            <label>期号</label>
            <input type="text" v-model="settlement.draw_period" class="form-control" />
          </div>
        </div>

        <div class="table-responsive">
          <table class="settlement-table">
            <thead>
              <tr>
                <th>玩法</th>
                <th>内容</th>
                <th>金额</th>
                <th>赔率</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(bet, index) in settlement.bets" :key="index">
                <td><input type="text" v-model="bet.type" class="form-control-table" /></td>
                <td><input type="text" v-model="bet.content" class="form-control-table" /></td>
                <td><input type="number" v-model.number="bet.amount" class="form-control-table" /></td>
                <td><input type="number" v-model.number="bet.odds" class="form-control-table" /></td>
                <td><button @click="removeBetRow(index)" class="btn-remove-row">删除</button></td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="2"><strong>总计</strong></td>
                <td colspan="3"><strong>{{ totalAmount.toFixed(2) }}</strong></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <button @click="addBetRow" class="btn-add-row">＋ 添加一行</button>

        <div class="ai-correction-section">
          <h3>AI 修正</h3>
          <div class="form-group">
            <label for="ai-correction">如果 AI 识别有误，请在此输入修正指令：</label>
            <textarea id="ai-correction" v-model="aiCorrectionFeedback" class="form-control" placeholder="例如：第二条的金额是 200，不是 20"></textarea>
          </div>
          <button @click="generateSettlement(selectedEmail, true)" class="ai-organize-button" :disabled="isAiLoading">
            <span v-if="isAiLoading">修正中...</span>
            <span v-else>提交修正指令</span>
          </button>
        </div>

        <div class="main-actions">
          <button @click="saveSettlement" class="submit-button" :disabled="isSaving">
            <span v-if="isSaving">保存中...</span>
            <span v-else>保存结算单</span>
          </button>
          <button @click="clearSettlement" class="cancel-button">清空</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import recordsService from '@/services/records.js';
import axios from 'axios';

const API_BASE_URL = '/api';

export default {
  name: 'MailOrganize',
  data() {
    return {
      emails: [],
      loading: true,
      globalError: null,
      selectedEmail: null,
      isAiLoading: false,
      isSaving: false, // 新增：保存状态
      aiCorrectionFeedback: '',
      settlement: {
        draw_period: '',
        customer_name: '',
        bets: [],
      },
    };
  },
  computed: {
    totalAmount() {
      return this.settlement.bets.reduce((sum, bet) => sum + (Number(bet.amount) || 0), 0);
    },
  },
  async created() {
    await this.fetchEmails();
  },
  methods: {
    async fetchEmails() {
      this.loading = true;
      this.globalError = null;
      try {
        const token = localStorage.getItem('jwt_token');
        if (!token) {
          this.globalError = '用户未登录，请先登录。';
          return;
        }
        // 注意：recordsService.getMyRecords 现在应该能返回 is_processed 字段
        const response = await recordsService.getMyRecords(token);
        this.emails = response.data;
      } catch (err) {
        this.globalError = err.response?.data?.message || '获取邮件列表失败。';
      } finally {
        this.loading = false;
      }
    },
    async generateSettlement(email, withCorrection = false) {
      // ... (此方法保持不变)
      if (this.isAiLoading) return;
      this.isAiLoading = true;
      this.globalError = null;
      this.selectedEmail = email;
      
      const payload = { email_id: email.id };
      if (withCorrection && this.aiCorrectionFeedback) {
        payload.correction = this.aiCorrectionFeedback;
      }

      try {
        const token = localStorage.getItem('jwt_token');
        const response = await axios.post(`${API_BASE_URL}/ai_process_email.php`, payload, {
          headers: { 'Authorization': `Bearer ${token}` },
        });

        if (response.data.status === 'success') {
          const aiData = response.data.data;
          this.settlement.draw_period = aiData.draw_period || '';
          this.settlement.customer_name = aiData.customer_name || '';
          this.settlement.bets = Array.isArray(aiData.bets) ? aiData.bets : [];
          this.aiCorrectionFeedback = '';
        } else {
          this.globalError = `AI 处理失败: ${response.data.message}`;
        }
      } catch (err) {
        this.globalError = err.response?.data?.message || 'AI服务调用失败，请检查后端日志。';
      } finally {
        this.isAiLoading = false;
      }
    },
    addBetRow() {
      this.settlement.bets.push({ type: '', content: '', amount: 0, odds: null });
    },
    removeBetRow(index) {
      this.settlement.bets.splice(index, 1);
    },
    async saveSettlement() {
      if (this.isSaving) return;
      this.isSaving = true;
      this.globalError = null;

      const settlementData = {
        ...this.settlement,
        total_amount: this.totalAmount,
      };

      try {
        const token = localStorage.getItem('jwt_token');
        const response = await axios.post(`${API_BASE_URL}/save_settlement.php`, {
            emailId: this.selectedEmail.id,
            settlementData: settlementData
        }, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        if (response.data.status === 'success') {
            // 标记邮件为已处理
            const foundEmail = this.emails.find(e => e.id === this.selectedEmail.id);
            if (foundEmail) {
                foundEmail.is_processed = 1;
            }
            this.clearSettlement(); // 清空表单，准备处理下一个
            alert('结算单已成功保存！');
        } else {
            this.globalError = `保存失败: ${response.data.message}`
        }
      } catch (err) {
        this.globalError = err.response?.data?.message || '保存失败，请检查网络或联系管理员。';
      } finally {
        this.isSaving = false;
      }
    },
    clearSettlement() {
      this.selectedEmail = null;
      this.aiCorrectionFeedback = '';
      this.settlement = { draw_period: '', customer_name: '', bets: [] };
    },
    formatDateTime(dateTimeString) {
      return new Date(dateTimeString).toLocaleString('zh-CN');
    },
  },
};
</script>

<style scoped>
/* ... (大部分样式保持不变) ... */
.mail-organize-container { padding: 2rem; max-width: 1600px; margin: 0 auto; }
h1 { text-align: center; color: var(--primary-accent); margin-bottom: 2rem; }
.card { background-color: var(--background-secondary); border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem; }
.loading-state, .error-state, .empty-state { text-align: center; padding: 2rem; font-size: 1.1rem; }
.error-state { background-color: var(--error-color); color: #fff; }

.content-wrapper { display: flex; gap: 2rem; align-items: flex-start; }
.emails-list-panel { flex: 1 1 450px; max-width: 500px; }
.settlement-panel { flex: 2 1 700px; position: sticky; top: 2rem; }

.email-item { border-left: 5px solid var(--border-color); transition: all 0.2s ease; }
.email-item.selected { border-left-color: var(--primary-accent); box-shadow: var(--shadow-elevation-medium); }
.email-item.processed { background-color: var(--background-tertiary); opacity: 0.7; }
.email-item.processed .email-subject { text-decoration: line-through; }

.email-header { display: flex; justify-content: space-between; font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.5rem; }
.email-subject { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; }

.status-and-button { display: flex; justify-content: space-between; align-items: center; }
.processed-badge { font-weight: bold; color: var(--success-color); background-color: rgba(76, 175, 80, 0.1); padding: 0.3rem 0.6rem; border-radius: 12px; }

.ai-organize-button { padding: 0.6rem 1.2rem; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }
.ai-organize-button:disabled { background-color: #5a8ebb; cursor: not-allowed; }

.settlement-panel h2 { color: var(--primary-accent); margin-top: 0; text-align: center; }
.settlement-header { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
.form-group { display: flex; flex-direction: column; flex: 1; }
.form-group label { margin-bottom: 0.5rem; font-weight: 500; }
.form-control { width: 100%; padding: 0.8rem; border-radius: 5px; border: 1px solid var(--border-color); }
textarea.form-control { min-height: 80px; }

.table-responsive { overflow-x: auto; max-height: 400px; }
.settlement-table { width: 100%; border-collapse: collapse; }
.settlement-table th, .settlement-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.settlement-table th { background-color: var(--background-tertiary); position: sticky; top: 0; }
.settlement-table tfoot strong { color: var(--primary-accent); font-size: 1.1rem; }

.form-control-table { width: 100%; padding: 0.5rem; border: 1px solid transparent; background-color: transparent; border-radius: 4px; }
.form-control-table:focus { background-color: var(--background-primary); border-color: var(--primary-accent); outline: none; }

.btn-remove-row, .btn-add-row { background: none; border: none; color: var(--error-color); cursor: pointer; }
.btn-add-row { color: var(--primary-accent); margin-top: 1rem; font-weight: bold; }

.ai-correction-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); }
.ai-correction-section h3 { margin-top: 0; }

.main-actions { margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end; }
.submit-button, .cancel-button { padding: 0.8rem 1.5rem; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
.submit-button { background-color: var(--primary-accent); color: white; }
.submit-button:disabled { background-color: #a5d6a7; cursor: not-allowed; }
.cancel-button { background-color: var(--background-tertiary); color: var(--text-primary); }
</style>
