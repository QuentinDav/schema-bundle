<script setup>
import { computed, ref, onMounted } from 'vue'
import { useSchemaStore } from '@/stores/schema'
import { useReleasesStore } from '@/stores/releases'
import CreateReleaseModal from './CreateReleaseModal.vue'
import Icon from './Icon.vue'

const schemaStore = useSchemaStore()
const releasesStore = useReleasesStore()

const searchValue = computed({
  get: () => schemaStore.searchQuery,
  set: (value) => schemaStore.setSearchQuery(value),
})

const showCreateModal = ref(false)
const showReleaseSuccess = ref(false)

onMounted(() => {
  releasesStore.fetchReleases()
})

function handleReleaseCreated(result) {
  showReleaseSuccess.value = true
  setTimeout(() => {
    showReleaseSuccess.value = false
  }, 3000)
}
</script>

<template>
  <header class="app-header">
    <div class="header-content">
      <div class="header-left">
        <div class="logo">
          <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
            <rect width="32" height="32" rx="8" fill="url(#gradient)"/>
            <path
              d="M10 12h12M10 16h12M10 20h8"
              stroke="white"
              stroke-width="2"
              stroke-linecap="round"
            />
            <defs>
              <linearGradient id="gradient" x1="0" y1="0" x2="32" y2="32">
                <stop offset="0%" stop-color="#667eea"/>
                <stop offset="100%" stop-color="#764ba2"/>
              </linearGradient>
            </defs>
          </svg>
        </div>
        <div class="header-title">
          <h1>DB Schema Viewer</h1>
          <p class="subtitle">Visualize your database schema</p>
        </div>
      </div>

      <div class="header-center">
        <div class="search-box">
          <Icon name="magnifying-glass" class="search-icon" />
          <input
            v-model="searchValue"
            type="text"
            placeholder="Search tables..."
            class="search-input"
          />
          <span v-if="searchValue" class="search-results">
            {{ schemaStore.filteredEntities.length }} results
          </span>
        </div>
      </div>

      <div class="header-right">
        <div class="stats">
          <div class="stat-item">
            <span class="stat-value">{{ schemaStore.totalEntities }}</span>
            <span class="stat-label">Tables</span>
          </div>
          <div class="stat-item">
            <span class="stat-value">{{ schemaStore.totalFields }}</span>
            <span class="stat-label">Fields</span>
          </div>
          <div class="stat-item">
            <span class="stat-value">{{ schemaStore.totalRelations }}</span>
            <span class="stat-label">Relations</span>
          </div>
          <div>
            <button @click="showCreateModal = true" class="btn btn-release hover-lift">
              <Icon name="plus-circle" />
              <span>Create Release</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Success Toast -->
    <Transition name="toast">
      <div v-if="showReleaseSuccess" class="success-toast">
        Release created successfully!
      </div>
    </Transition>
  </header>

  <!-- Create Release Modal -->
  <CreateReleaseModal
    v-if="showCreateModal"
    @close="showCreateModal = false"
    @created="handleReleaseCreated"
  />
</template>

<style scoped>
.app-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 1.5rem 2rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 2rem;
  max-width: 1800px;
  margin: 0 auto;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-shrink: 0;
}

.logo {
  display: flex;
  align-items: center;
  justify-content: center;
}

.header-title h1 {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0;
  line-height: 1.2;
}

.subtitle {
  font-size: 0.875rem;
  opacity: 0.9;
  margin: 0.25rem 0 0 0;
}

.header-center {
  flex: 1;
  max-width: 500px;
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  padding: 0.75rem 1rem;
  backdrop-filter: blur(10px);
  transition: all 0.3s ease;
}

.search-box:focus-within {
  background: rgba(255, 255, 255, 0.25);
  box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
}

.search-icon {
  color: white;
  opacity: 0.8;
  margin-right: 0.75rem;
  flex-shrink: 0;
}

.search-input {
  background: none;
  border: none;
  color: white;
  font-size: 1rem;
  outline: none;
  width: 100%;
}

.search-input::placeholder {
  color: rgba(255, 255, 255, 0.7);
}

.search-results {
  font-size: 0.875rem;
  opacity: 0.9;
  padding: 0.25rem 0.75rem;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 6px;
  white-space: nowrap;
  margin-left: 0.5rem;
}

.header-right {
  flex-shrink: 0;
}

.stats {
  display: flex;
  gap: 2rem;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.stat-value {
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1;
}

.stat-label {
  font-size: 0.875rem;
  opacity: 0.9;
  margin-top: 0.25rem;
}

@media (max-width: 1024px) {
  .header-content {
    flex-wrap: wrap;
  }

  .header-center {
    order: 3;
    flex: 1 1 100%;
    max-width: none;
  }

  .stats {
    gap: 1rem;
  }

  .stat-value {
    font-size: 1.5rem;
  }
}

.btn {
  padding: 0.75rem 1.25rem;
  background: rgba(255, 255, 255, 0.2);
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  backdrop-filter: blur(10px);
}

.btn:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: translateY(-1px);
}

.btn-release {
  font-size: 0.875rem;
}

.icon {
  width: 20px;
  height: 20px;
}

/* Success Toast */
.success-toast {
  position: fixed;
  top: 120px;
  right: 2rem;
  background: var(--color-success-500);
  color: white;
  padding: var(--spacing-4) var(--spacing-6);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  z-index: var(--z-toast);
  font-weight: 600;
  animation: slide-in-right var(--transition-base) var(--ease-bounce);
}

.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
