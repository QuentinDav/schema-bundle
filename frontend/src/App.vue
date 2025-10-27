<script setup>
import { onMounted } from 'vue'
import { useSchemaStore } from '@/stores/schema'
import { useCommentsStore } from '@/stores/comments'
import AppHeader from '@/components/AppHeader.vue'
import AppSidebar from '@/components/AppSidebar.vue'
import TableDetails from '@/components/TableDetails.vue'
import ToastContainer from '@/components/ToastContainer.vue'

const schemaStore = useSchemaStore()
const commentsStore = useCommentsStore()

onMounted(() => {
  schemaStore.fetchSchema()
  commentsStore.fetchComments()
  commentsStore.fetchUsers()
})
</script>

<template>
  <div class="app-container">
    <AppHeader />

    <div class="app-main">
      <AppSidebar />

      <main class="app-content">
        <div v-if="schemaStore.loading" class="loading-state">
          <div class="spinner"></div>
          <p>Loading schema...</p>
        </div>

        <div v-else-if="schemaStore.error" class="error-state">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="12" cy="12" r="10" stroke-width="2" />
            <path d="M12 8v4m0 4h.01" stroke-width="2" stroke-linecap="round" />
          </svg>
          <h3>Error loading schema</h3>
          <p>{{ schemaStore.error }}</p>
          <button @click="schemaStore.fetchSchema()" class="retry-btn">Try Again</button>
        </div>

        <RouterView v-else />
      </main>
    </div>

    <TableDetails />
    <ToastContainer />
  </div>
</template>

<style scoped>
.app-container {
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
}

.app-main {
  display: flex;
  flex: 1;
  overflow: hidden;
}

.app-content {
  flex: 1;
  overflow: hidden;
  position: relative;
}

.loading-state,
.error-state {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  color: #9ca3af;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.spinner {
  width: 48px;
  height: 48px;
  border: 4px solid #e5e7eb;
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.loading-state p {
  font-size: 1rem;
  color: #6b7280;
  margin: 0;
}

.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.error-state svg {
  color: #ef4444;
}

.error-state h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #6b7280;
  margin: 0;
}

.error-state p {
  font-size: 0.875rem;
  color: #9ca3af;
  margin: 0;
}

.retry-btn {
  margin-top: 0.5rem;
  padding: 0.75rem 1.5rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.retry-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}
</style>
