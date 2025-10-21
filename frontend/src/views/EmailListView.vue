<template>
  <div class="email-list-container">
    <h2>Received Emails</h2>
    <div v-if="loading" class="loading-message">Loading...</div>
    <div v-if="error" class="error-message">{{ error }}</div>
    <div v-if="emails.length > 0" class="email-table-wrapper">
      <table class="email-table">
        <thead>
          <tr>
            <th>From</th>
            <th>Subject</th>
            <th>Received</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="email in emails" :key="email.id" @click="viewEmail(email.id)">
            <td>{{ email.from_address }}</td>
            <td>{{ email.subject }}</td>
            <td>{{ formatDate(email.received_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else-if="!loading" class="no-emails-message">
      <p>No emails found.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const emails = ref([])
const loading = ref(true)
const error = ref(null)

const API_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/api';

async function fetchEmails() {
  loading.value = true
  error.value = null
  try {
    const response = await axios.get(`${API_URL}/emails`);
    emails.value = response.data.data; // Access the data array from pagination
  } catch (err) {
    console.error('Error fetching emails:', err);
    error.value = 'Failed to load emails. Is the backend running and accessible?'
  }
  loading.value = false
}

function viewEmail(id) {
  router.push({ name: 'email-detail', params: { id } })
}

function formatDate(dateString) {
  if (!dateString) return 'N/A';
  const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
  return new Date(dateString).toLocaleDateString(undefined, options);
}

onMounted(() => {
  fetchEmails()
})
</script>

<style scoped>
.email-list-container {
  font-family: Arial, sans-serif;
}

.loading-message, .error-message, .no-emails-message {
  text-align: center;
  padding: 2rem;
  font-size: 1.2rem;
}

.error-message {
  color: #d9534f;
}

.email-table-wrapper {
  border: 1px solid #ddd;
  border-radius: 8px;
  overflow: hidden;
}

.email-table {
  width: 100%;
  border-collapse: collapse;
}

.email-table th, .email-table td {
  padding: 1rem;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

.email-table thead th {
  background-color: #f7f7f7;
  font-weight: bold;
}

.email-table tbody tr {
  cursor: pointer;
  transition: background-color 0.2s;
}

.email-table tbody tr:hover {
  background-color: #f1f1f1;
}

.email-table tbody tr:last-child td {
  border-bottom: none;
}
</style>
