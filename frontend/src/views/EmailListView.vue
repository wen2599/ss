<script setup>
import { onMounted } from 'vue';
import { useEmailStore } from '@/stores/emails';
import { storeToRefs } from 'pinia';
import { RouterLink } from 'vue-router';
import LoadingSpinner from '@/components/LoadingSpinner.vue';

const emailStore = useEmailStore();
const { emails, loading, error } = storeToRefs(emailStore);

onMounted(() => {
  emailStore.fetchEmails();
});
</script>

<template>
  <main>
    <h1>Received Emails</h1>
    <div v-if="loading">
      <LoadingSpinner />
    </div>
    <div v-else-if="error" class="error-message">
      {{ error }}
    </div>
    <div v-else-if="emails.length > 0" class="email-list">
      <table>
        <thead>
          <tr>
            <th>From</th>
            <th>Subject</th>
            <th>Received At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="email in emails" :key="email.id">
            <td>{{ email.from }}</td>
            <td>{{ email.subject }}</td>
            <td>{{ email.received_at }}</td>
            <td>
              <RouterLink :to="{ name: 'email-detail', params: { id: email.id } }">
                View Details
              </RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else class="no-emails">
      <p>No emails found.</p>
    </div>
  </main>
</template>

<style scoped>
main {
  padding: 2rem;
}
.error-message {
  color: #dc3545;
  text-align: center;
}
.no-emails {
  text-align: center;
  color: #888;
}
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1rem;
}
th, td {
  border: 1px solid var(--color-border);
  padding: 0.75rem;
  text-align: left;
}
th {
  background-color: #f8f9fa;
}
</style>