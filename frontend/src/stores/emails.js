import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from './api'

export const useEmailStore = defineStore('emails', () => {
  const emails = ref([])
  const currentEmail = ref(null)
  const loading = ref(false)
  const error = ref(null)

  async function fetchEmails() {
    loading.value = true
    error.value = null
    try {
      // TODO: 后端实现 /emails 接口后，替换这里的模拟数据
      // const response = await apiClient.get('/emails');
      // emails.value = response.data;

      // --- 模拟数据 ---
      await new Promise(resolve => setTimeout(resolve, 500)); // 模拟网络延迟
      emails.value = [
        { id: 1, from: 'sender1@example.com', subject: 'Test Email 1', received_at: '2023-10-30 10:00:00' },
        { id: 2, from: 'sender2@example.com', subject: 'Another Test Email', received_at: '2023-10-30 11:30:00' },
      ];
      // --- 模拟数据结束 ---

    } catch (err) {
      error.value = 'Failed to fetch emails.'
      console.error(err)
    } finally {
      loading.value = false
    }
  }

  async function fetchEmailById(id) {
    loading.value = true
    error.value = null
    currentEmail.value = null;
    try {
      // TODO: 后端实现 /emails/{id} 接口后，替换这里的模拟数据
      // const response = await apiClient.get(`/emails/${id}`);
      // currentEmail.value = response.data;
      
      // --- 模拟数据 ---
      await new Promise(resolve => setTimeout(resolve, 500));
      currentEmail.value = {
        id: id,
        from_address: 'sender@example.com',
        subject: 'Example Subject',
        received_at: '2023-10-30 12:00:00',
        raw_content: '<html><body><h1>This is the raw HTML content</h1><p>Order ID: 12345</p><p>Amount: $99.99</p></body></html>',
        processed_form_data: {
          order_id: '12345',
          amount: '99.99',
          customer_name: 'John Doe'
        }
      };
      // --- 模拟数据结束 ---

    } catch (err) {
      error.value = `Failed to fetch email with id ${id}.`
      console.error(err)
    } finally {
      loading.value = false
    }
  }

  return { emails, currentEmail, loading, error, fetchEmails, fetchEmailById }
})