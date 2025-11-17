<template>
  <div class="migration-timeline">
    <!-- Loading state -->
    <div v-if="loading" class="flex items-center justify-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 dark:border-blue-400"></div>
      <span class="ml-3 text-gray-600 dark:text-gray-400">Loading migration history...</span>
    </div>

    <!-- Error state -->
    <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
      <div class="flex items-start">
        <svg class="h-5 w-5 text-red-600 dark:text-red-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-red-800 dark:text-red-300">Error loading migration history</h3>
          <p class="mt-1 text-sm text-red-700 dark:text-red-400">{{ error }}</p>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!history || history.length === 0" class="text-center py-8 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
      <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No migrations found</h3>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This entity hasn't been modified by any migrations yet.</p>
    </div>

    <!-- Timeline -->
    <div v-else class="flow-root">
      <ul role="list" class="-mb-8">
        <li v-for="(entry, idx) in history" :key="`${entry.migration}-${idx}`">
          <div class="relative pb-8">
            <!-- Connector line -->
            <span
              v-if="idx !== history.length - 1"
              class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700"
              aria-hidden="true"
            ></span>

            <div class="relative flex items-start space-x-3">
              <!-- Icon -->
              <div>
                <div class="relative px-1">
                  <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/50 ring-8 ring-white dark:ring-gray-900">
                    <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                    </svg>
                  </div>
                </div>
              </div>

              <!-- Content -->
              <div class="min-w-0 flex-1">
                <div>
                  <div class="text-sm">
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ entry.migration }}</span>
                  </div>
                  <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    {{ migrationsStore.formatDate(entry.timestamp) }}
                    <span class="text-gray-400 dark:text-gray-500 ml-2">({{ migrationsStore.getRelativeTime(entry.timestamp) }})</span>
                  </p>
                  <p v-if="entry.description" class="mt-1 text-sm text-gray-600 dark:text-gray-400 italic">
                    {{ entry.description }}
                  </p>
                </div>

                <!-- Changes list -->
                <div class="mt-3 space-y-2">
                  <div
                    v-for="(change, changeIdx) in entry.changes"
                    :key="`${entry.migration}-change-${changeIdx}`"
                    class="rounded-lg bg-gray-50 dark:bg-gray-800/50 px-4 py-3 border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-colors"
                  >
                    <div class="flex items-start">
                      <span
                        class="inline-flex h-6 w-6 items-center justify-center rounded-full flex-shrink-0"
                        :class="getChangeBgColor(change.type)"
                      >
                        <component
                          :is="getChangeIcon(change.type)"
                          class="h-4 w-4"
                          :class="migrationsStore.getChangeTypeColor(change.type)"
                        />
                      </span>
                      <div class="ml-3 flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                          {{ migrationsStore.getChangeTypeLabel(change.type) }}
                        </p>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                          {{ change.description }}
                        </p>

                        <!-- SQL statement (collapsible) -->
                        <details v-if="change.sql" class="mt-2">
                          <summary class="text-xs text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300">
                            View SQL
                          </summary>
                          <pre class="mt-2 text-xs bg-gray-800 dark:bg-gray-900 text-gray-100 dark:text-gray-300 p-3 rounded overflow-x-auto"><code>{{ change.sql }}</code></pre>
                        </details>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </li>
      </ul>
    </div>

    <!-- Summary stats at bottom -->
    <div v-if="history && history.length > 0" class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
      <div class="grid grid-cols-3 gap-4 text-center">
        <div>
          <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ history.length }}</p>
          <p class="text-xs text-gray-500 dark:text-gray-400">Migrations</p>
        </div>
        <div>
          <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ totalChanges }}</p>
          <p class="text-xs text-gray-500 dark:text-gray-400">Changes</p>
        </div>
        <div>
          <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ changeTypes.size }}</p>
          <p class="text-xs text-gray-500 dark:text-gray-400">Change Types</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useMigrationsStore } from '../stores/migrations'
import {
  PlusCircleIcon,
  TrashIcon,
  PlusIcon,
  MinusIcon,
  PencilIcon,
  PencilSquareIcon,
  KeyIcon,
  LinkIcon,
  WrenchIcon,
  DocumentIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  entityFqcn: {
    type: String,
    required: true,
  },
})

const migrationsStore = useMigrationsStore()

const history = ref([])
const loading = ref(false)
const error = ref(null)

// Computed
const totalChanges = computed(() => {
  return history.value.reduce((sum, entry) => sum + (entry.changes?.length || 0), 0)
})

const changeTypes = computed(() => {
  const types = new Set()
  history.value.forEach(entry => {
    entry.changes?.forEach(change => types.add(change.type))
  })
  return types
})

// Methods
async function loadHistory() {
  loading.value = true
  error.value = null

  try {
    history.value = await migrationsStore.fetchEntityHistory(props.entityFqcn)
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

function getChangeIcon(type) {
  const icons = {
    create_table: PlusCircleIcon,
    drop_table: TrashIcon,
    add_column: PlusIcon,
    drop_column: MinusIcon,
    rename_column: PencilIcon,
    modify_column: PencilSquareIcon,
    add_index: KeyIcon,
    drop_index: KeyIcon,
    add_constraint: LinkIcon,
    drop_constraint: LinkIcon,
    alter_table: WrenchIcon,
  }

  return icons[type] || DocumentIcon
}

function getChangeBgColor(type) {
  const colors = {
    create_table: 'bg-green-100',
    drop_table: 'bg-red-100',
    add_column: 'bg-blue-100',
    drop_column: 'bg-orange-100',
    rename_column: 'bg-purple-100',
    modify_column: 'bg-yellow-100',
    add_index: 'bg-indigo-100',
    drop_index: 'bg-gray-100',
    add_constraint: 'bg-teal-100',
    drop_constraint: 'bg-pink-100',
    alter_table: 'bg-gray-100',
  }

  return colors[type] || 'bg-gray-100'
}

// Lifecycle
onMounted(() => {
  loadHistory()
})

// Watch for entity changes
watch(() => props.entityFqcn, () => {
  loadHistory()
})
</script>

<style scoped>
.migration-timeline {
  @apply w-full;
}

details > summary {
  list-style: none;
}

details > summary::-webkit-details-marker {
  display: none;
}

details[open] > summary {
  @apply text-blue-600;
}
</style>
