<script setup>
import { onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useEmailStore } from '@/stores/emails';
import { storeToRefs } from 'pinia';
import LoadingSpinner from '@/components/LoadingSpinner.vue';

const route = useRoute();
const emailStore = useEmailStore();
const { currentEmail, loading, error } = storeToRefs(emailStore);

onMounted(() => {
  const emailId = route.params.id;
  emailStore.fetchEmailById(emailId);
});
</script>

<template>
  <main>
    <h1>Email Details</h1>
    <div v-if="loading">
      <LoadingSpinner />
    </div>
    <div v-else-if="error" class="error-message">
      {{ error }}
    </div>
    <div v-else-if="currentEmail" class="email-details-grid">
      <div class="detail-item">
        <strong>From:</strong>
        <span>{{ currentEmail.from_address }}</span>
      </div>
      <div class="detail-item">
        <strong>Subject:</strong>
        <span>{{ currentEmail.subject }}</span>
      </div>
      <div class="detail-item">
        <strong>Received At:</strong>
        <span>{{ currentEmail.received_at }}</span>
      </div>

      <div class="content-section">
        <h3>Raw Email Content</h3>
        <div class="raw-content-container">
            <iframe :srcdoc="currentEmail.raw_content" class="email-iframe"></iframe>
        </div>
      </div>

      <div class="content-section">
        <h3>Processed Form Data</h3>
        <pre class="processed-data">{{ JSON.stringify(currentEmail.processed_form_data, null, 2) }}</pre>
      </div>
    </div>
  </main>
</template>


<style scoped>
main {
  padding: 2rem;
}
.error-message {
  color: #dc3545;
}
.email-details-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
}

.detail-item {
  display: contents; /* Allows grid styling to apply to children */
}

.detail-item strong {
  font-weight: bold;
  margin-bottom: 0.25rem; 
  grid-column: 1;
}

.detail-item span {
  grid-column: 2;
  margin-bottom: 1rem;
}

.content-section {
  margin-top: 1rem;
  grid-column: 1 / -1; /* Span across all columns */
}

.content-section h3 {
  margin-bottom: 0.75rem;
  border-bottom: 1px solid var(--color-border);
  padding-bottom: 0.5rem;
}

.raw-content-container {
  border: 1px solid var(--color-border);
  border-radius: 4px;
  overflow: hidden;
  height: 400px; /* Default height */
  resize: vertical;
}

.email-iframe {
  width: 100%;
  height: 100%;
  border: none;
}

.processed-data {
  background-color: #f8f9fa;
  padding: 1rem;
  border-radius: 4px;
  border: 1px solid var(--color-border);
  white-space: pre-wrap; /* Allows long lines to wrap */
  word-wrap: break-word; /* Breaks long words if necessary */
}

/* Responsive grid layout */
@media (min-width: 768px) {
  .email-details-grid {
    grid-template-columns: 150px 1fr; /* Label and value columns */
    align-items: center;
  }
}
</style>