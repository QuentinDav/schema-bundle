<script setup>
import { computed, ref, onMounted } from 'vue'
import { useSchemaStore } from '@/stores/schema'
import { useReleasesStore } from '@/stores/releases'
import { usePlaygroundStore } from '@/stores/playground'
import CreateReleaseModal from './CreateReleaseModal.vue'
import Icon from './Icon.vue'

const schemaStore = useSchemaStore()
const releasesStore = useReleasesStore()
const playgroundStore = usePlaygroundStore()

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
  <header class="h-16 border-b border-[var(--color-border)] bg-[var(--color-surface)] flex items-center px-6 gap-8 flex-shrink-0">
    <div class="flex items-center gap-3 flex-shrink-0">
      <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-[var(--color-primary)] to-[var(--color-primary-hover)] flex items-center justify-center">
        <svg class="w-5 h-5 text-white" viewBox="0 0 20 20" fill="currentColor">
          <path d="M3 4h14M3 8h14M3 12h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </div>
      <div>
        <h1 class="text-base font-semibold text-[var(--color-text-primary)]">Schema Doc</h1>
        <p class="text-xs text-[var(--color-text-tertiary)]">Database Schema Explorer</p>
      </div>
    </div>

    <div class="flex-1 max-w-md">
      <button
        @click="$emit('open-command-palette')"
        class="relative w-full h-9 pl-10 pr-4 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-lg text-sm text-left hover:border-[var(--color-primary)] transition-colors group cursor-pointer"
      >
        <Icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--color-text-tertiary)] group-hover:text-[var(--color-primary)]" />
        <span class="text-[var(--color-text-tertiary)]">Search entities, fields...</span>
        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[var(--color-text-tertiary)] bg-[var(--color-surface-hover)] px-2 py-0.5 rounded font-mono">
          ⌘K
        </span>
      </button>
    </div>

    <div class="flex items-center gap-6 flex-shrink-0">
      <div class="flex items-center gap-6">
        <div class="text-center">
          <div class="text-lg font-bold text-[var(--color-text-primary)]">{{ schemaStore.totalEntities }}</div>
          <div class="text-xs text-[var(--color-text-tertiary)]">Tables</div>
        </div>
        <div class="text-center">
          <div class="text-lg font-bold text-[var(--color-text-primary)]">{{ schemaStore.totalFields }}</div>
          <div class="text-xs text-[var(--color-text-tertiary)]">Fields</div>
        </div>
        <div class="text-center">
          <div class="text-lg font-bold text-[var(--color-text-primary)]">{{ schemaStore.totalRelations }}</div>
          <div class="text-xs text-[var(--color-text-tertiary)]">Relations</div>
        </div>
      </div>

      <button
        @click="showCreateModal = true"
        class="flex items-center gap-2 h-9 px-4 bg-[var(--color-primary)] hover:bg-[var(--color-primary-hover)] text-white text-sm font-medium rounded-lg transition-all duration-200 hover:-translate-y-0.5"
      >
        <Icon name="plus-circle" class="w-4 h-4" />
        <span>Release</span>
      </button>
    </div>
  </header>

  <Transition name="toast">
    <div v-if="showReleaseSuccess" class="fixed top-20 right-6 bg-[var(--color-success)] text-white px-4 py-3 rounded-lg shadow-lg font-medium text-sm z-[var(--z-tooltip)]">
      Release created successfully!
    </div>
  </Transition>

  <CreateReleaseModal
    v-if="showCreateModal"
    @close="showCreateModal = false"
    @created="handleReleaseCreated"
  />
</template>

<style scoped>
.toast-enter-active {
  transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

.toast-leave-active {
  transition: all 0.2s ease-in;
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
