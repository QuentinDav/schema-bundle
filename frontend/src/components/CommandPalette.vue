<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-start justify-center pt-20 bg-black/50 backdrop-blur-sm"
        @click.self="close"
        @keydown.esc="close"
      >
        <div
          class="w-full max-w-2xl bg-white dark:bg-gray-800 rounded-xl shadow-2xl overflow-hidden"
          @click.stop
        >
          <!-- Search Input -->
          <div class="flex items-center gap-3 px-4 py-4 border-b border-gray-200 dark:border-gray-700">
            <svg
              class="w-5 h-5 text-gray-400 dark:text-gray-500"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
              />
            </svg>
            <input
              ref="searchInput"
              v-model="query"
              type="text"
              placeholder="Search entities, fields, or type a command..."
              class="flex-1 text-lg outline-none bg-transparent text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
              @keydown.down.prevent="selectNext"
              @keydown.up.prevent="selectPrevious"
              @keydown.enter.prevent="selectCurrent"
            />
            <kbd
              class="px-2 py-1 text-xs font-semibold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded"
            >
              ESC
            </kbd>
          </div>

          <!-- Results -->
          <div class="max-h-96 overflow-y-auto">
            <!-- No results -->
            <div
              v-if="displayedResults.length === 0 && query"
              class="p-8 text-center text-gray-500 dark:text-gray-400"
            >
              <svg
                class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M12 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
              <p class="text-sm font-medium">No results found</p>
              <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Try a different search term</p>
            </div>

            <!-- Quick Actions (when no query) -->
            <div v-if="!query" class="py-2">
              <div class="px-4 py-2 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase">
                Quick Actions
              </div>
              <button
                v-for="(action, idx) in quickActions"
                :key="action.id"
                class="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                :class="{ 'bg-blue-50 dark:bg-blue-900/30': selectedIndex === idx }"
                @click="executeAction(action)"
                @mouseenter="selectedIndex = idx"
              >
                <div
                  class="w-10 h-10 rounded-lg flex items-center justify-center"
                  :class="action.color || 'bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400'"
                >
                  <component :is="action.icon" class="w-5 h-5" />
                </div>
                <div class="flex-1 text-left">
                  <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ action.label }}</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">{{ action.description }}</div>
                </div>
                <kbd
                  v-if="action.shortcut"
                  class="px-2 py-1 text-xs font-mono text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded"
                >
                  {{ action.shortcut }}
                </kbd>
              </button>
            </div>

            <!-- Search Results -->
            <div v-if="query && displayedResults.length > 0" class="py-2">
              <div class="px-4 py-2 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase">
                Entities ({{ displayedResults.length }})
              </div>
              <button
                v-for="(result, idx) in displayedResults"
                :key="result.fqcn || result.name"
                class="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                :class="{ 'bg-blue-50 dark:bg-blue-900/30': selectedIndex === idx }"
                @click="selectEntity(result)"
                @mouseenter="selectedIndex = idx"
              >
                <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/50 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                  <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                    <path
                      d="M3 6a3 3 0 013-3h12a3 3 0 013 3v12a3 3 0 01-3 3H6a3 3 0 01-3-3V6z"
                      opacity="0.2"
                    />
                    <path
                      d="M3 6a3 3 0 013-3h12a3 3 0 013 3v3H3V6zm0 5h18v7a3 3 0 01-3 3H6a3 3 0 01-3-3v-7z"
                    />
                  </svg>
                </div>
                <div class="flex-1 text-left min-w-0">
                  <div
                    class="text-sm font-medium text-gray-900 dark:text-gray-100"
                    v-html="highlightText(result.name, result._matches)"
                  ></div>
                  <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                    {{ result.table }} • {{ result.fields?.length || 0 }} fields •
                    {{ result.relations?.length || 0 }} relations
                  </div>
                </div>
                <div class="flex gap-1">
                  <span
                    v-if="result.fields?.length"
                    class="px-2 py-1 text-xs font-semibold bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 rounded"
                  >
                    {{ result.fields.length }}
                  </span>
                  <span
                    v-if="result.relations?.length"
                    class="px-2 py-1 text-xs font-semibold bg-purple-100 dark:bg-purple-900/50 text-purple-600 dark:text-purple-400 rounded"
                  >
                    {{ result.relations.length }}
                  </span>
                </div>
              </button>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-between px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
            <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
              <span class="flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 font-mono bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded">↑</kbd>
                <kbd class="px-1.5 py-0.5 font-mono bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded">↓</kbd>
                Navigate
              </span>
              <span class="flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 font-mono bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded">↵</kbd>
                Select
              </span>
            </div>
            <div class="text-xs text-gray-400 dark:text-gray-500">
              Powered by Fuse.js
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { useSchemaStore } from '../stores/schema'
import { useSmartSearch, highlightMatches } from '../composables/useSmartSearch'
import {
  MagnifyingGlassIcon,
  BoltIcon,
  FunnelIcon,
  ArrowsPointingOutIcon,
  StarIcon,
  LinkIcon,
  TableCellsIcon,
} from '@heroicons/vue/24/outline'

const schemaStore = useSchemaStore()
const isOpen = ref(false)
const searchInput = ref(null)
const selectedIndex = ref(0)
const query = ref('')

// Smart search
const { searchResults } = useSmartSearch(
  computed(() => schemaStore.entities),
  {
    keys: ['name', 'table', 'fqcn', 'fields.name'],
    threshold: 0.4,
  }
)

const displayedResults = computed(() => {
  return query.value ? searchResults.value.slice(0, 10) : []
})

// Quick actions
const quickActions = [
  {
    id: 'select-all',
    label: 'Select all entities',
    description: 'Show all entities on the graph',
    icon: TableCellsIcon,
    color: 'bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400',
    action: () => {
      const allFqcns = schemaStore.entities.map((e) => e.fqcn || e.name)
      schemaStore.setSelectedEntities(allFqcns)
      close()
    },
  },
  {
    id: 'select-core',
    label: 'Select core entities',
    description: 'Top 20 most connected entities',
    icon: StarIcon,
    color: 'bg-yellow-100 dark:bg-yellow-900/50 text-yellow-600 dark:text-yellow-400',
    action: () => {
      const sorted = [...schemaStore.entities]
        .sort((a, b) => (b.relations?.length || 0) - (a.relations?.length || 0))
        .slice(0, 20)
      const fqcns = sorted.map((e) => e.fqcn || e.name)
      schemaStore.setSelectedEntities(fqcns)
      close()
    },
  },
  {
    id: 'with-relations',
    label: 'Entities with relations',
    description: 'Show only entities that have relationships',
    icon: LinkIcon,
    color: 'bg-purple-100 dark:bg-purple-900/50 text-purple-600 dark:text-purple-400',
    action: () => {
      const withRels = schemaStore.entities
        .filter((e) => e.relations && e.relations.length > 0)
        .map((e) => e.fqcn || e.name)
      schemaStore.setSelectedEntities(withRels)
      close()
    },
  },
  {
    id: 'clear',
    label: 'Clear selection',
    description: 'Deselect all entities',
    icon: FunnelIcon,
    color: 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400',
    action: () => {
      schemaStore.clearSelectedEntities()
      close()
    },
  },
]

// Methods
const open = () => {
  isOpen.value = true
  selectedIndex.value = 0
  query.value = ''
  nextTick(() => {
    searchInput.value?.focus()
  })
}

const close = () => {
  isOpen.value = false
  query.value = ''
  selectedIndex.value = 0
}

const selectNext = () => {
  const maxIndex = query.value
    ? displayedResults.value.length - 1
    : quickActions.length - 1
  selectedIndex.value = Math.min(selectedIndex.value + 1, maxIndex)
}

const selectPrevious = () => {
  selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
}

const selectCurrent = () => {
  if (query.value && displayedResults.value.length > 0) {
    const result = displayedResults.value[selectedIndex.value]
    if (result) {
      selectEntity(result)
    }
  } else if (!query.value && quickActions.length > 0) {
    const action = quickActions[selectedIndex.value]
    if (action) {
      executeAction(action)
    }
  }
}

const selectEntity = (entity) => {
  const fqcn = entity.fqcn || entity.name
  schemaStore.setSelectedEntities([fqcn])
  close()
}

const executeAction = (action) => {
  action.action()
}

const highlightText = (text, matches) => {
  if (!matches || matches.length === 0) return text
  const relevantMatches = matches.filter((m) => m.key === 'name')
  return highlightMatches(text, relevantMatches)
}

// Keyboard shortcut (Cmd+K or Ctrl+K)
const handleKeydown = (event) => {
  if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
    event.preventDefault()
    if (isOpen.value) {
      close()
    } else {
      open()
    }
  }
}

// Lifecycle
onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})

// Reset selected index when results change
watch([query, displayedResults], () => {
  selectedIndex.value = 0
})

// Expose open/close for parent components
defineExpose({
  open,
  close,
  isOpen,
})
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active > div,
.modal-leave-active > div {
  transition: all 0.2s ease;
}

.modal-enter-from > div,
.modal-leave-to > div {
  transform: scale(0.95);
  opacity: 0;
}

kbd {
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

:deep(mark) {
  @apply bg-yellow-200 dark:bg-yellow-900/50 text-gray-900 dark:text-yellow-200 font-semibold px-0.5 rounded;
}
</style>
