<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useSchemaStore } from '@/stores/schema'
import { findPaths, formatPath, getPathStats } from '@/utils/pathFinder'
import Icon from './Icon.vue'

const emit = defineEmits(['paths-found', 'show-path'])

const schemaStore = useSchemaStore()

const sourceEntityId = ref('')
const targetEntityId = ref('')
const searchSource = ref('')
const searchTarget = ref('')
const showSourceDropdown = ref(false)
const showTargetDropdown = ref(false)
const maxDepth = ref(5)
const foundPaths = ref([])
const isSearching = ref(false)
const selectedPaths = ref(new Set())

const filteredSourceEntities = computed(() => {
  if (!searchSource.value) return []

  const query = searchSource.value.toLowerCase()
  return schemaStore.entities
    .filter(e =>
      e.name.toLowerCase().includes(query) ||
      (e.table?.toLowerCase().includes(query)) ||
      (e.fqcn || e.name).toLowerCase().includes(query)
    )
    .slice(0, 8)
})

const filteredTargetEntities = computed(() => {
  if (!searchTarget.value) return []

  const query = searchTarget.value.toLowerCase()
  return schemaStore.entities
    .filter(e =>
      e.name.toLowerCase().includes(query) ||
      (e.table?.toLowerCase().includes(query)) ||
      (e.fqcn || e.name).toLowerCase().includes(query)
    )
    .slice(0, 8)
})

const sourceEntity = computed(() => {
  if (!sourceEntityId.value) return null
  return schemaStore.entities.find(e => (e.fqcn || e.name) === sourceEntityId.value)
})

const targetEntity = computed(() => {
  if (!targetEntityId.value) return null
  return schemaStore.entities.find(e => (e.fqcn || e.name) === targetEntityId.value)
})

const pathStats = computed(() => {
  return getPathStats(foundPaths.value)
})

const canSearch = computed(() => {
  return sourceEntityId.value && targetEntityId.value && !isSearching.value
})

function selectSourceEntity(entity) {
  sourceEntityId.value = entity.fqcn || entity.name
  searchSource.value = entity.name
  showSourceDropdown.value = false
}

function selectTargetEntity(entity) {
  targetEntityId.value = entity.fqcn || entity.name
  searchTarget.value = entity.name
  showTargetDropdown.value = false
}

function clearSource() {
  sourceEntityId.value = ''
  searchSource.value = ''
  foundPaths.value = []
  selectedPaths.value = new Set()
}

function clearTarget() {
  targetEntityId.value = ''
  searchTarget.value = ''
  foundPaths.value = []
  selectedPaths.value = new Set()
}

async function searchPaths() {
  if (!canSearch.value) return

  isSearching.value = true
  foundPaths.value = []
  selectedPaths.value = new Set()

  await new Promise(resolve => setTimeout(resolve, 100))

  const paths = findPaths(
    sourceEntity.value,
    targetEntity.value,
    schemaStore.entities,
    maxDepth.value
  )

  foundPaths.value = paths
  isSearching.value = false

  if (paths.length > 0) {
    emit('paths-found', paths)
  }
}

function togglePathSelection(index) {
  const newSet = new Set(selectedPaths.value)
  if (newSet.has(index)) {
    newSet.delete(index)
  } else {
    newSet.add(index)
  }
  selectedPaths.value = newSet
}

function showSelectedPaths() {
  if (selectedPaths.value.size === 0) return

  const paths = Array.from(selectedPaths.value).map(i => foundPaths.value[i])
  emit('show-path', paths)
}

function showAllPaths() {
  if (foundPaths.value.length === 0) return
  emit('show-path', foundPaths.value)
}

function showPath(index) {
  emit('show-path', [foundPaths.value[index]])
}

function selectAllPaths() {
  selectedPaths.value = new Set(foundPaths.value.map((_, i) => i))
}

function clearSelection() {
  selectedPaths.value = new Set()
}

function handleClickOutside(event) {
  if (!event.target.closest('.autocomplete-container')) {
    showSourceDropdown.value = false
    showTargetDropdown.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <div class="h-full flex flex-col bg-[var(--color-surface)] overflow-y-auto">
    <div class="px-4 py-4 bg-[var(--color-surface-raised)] border-b border-[var(--color-border)]">
      <div class="flex items-center gap-2 mb-1">
        <Icon name="map" :size="20" class="text-[var(--color-primary)]" />
        <h2 class="m-0 text-base font-bold text-[var(--color-text-primary)]">Path Finder</h2>
      </div>
      <p class="m-0 text-sm text-[var(--color-text-secondary)]">Find connection paths between two entities</p>
    </div>

    <div class="px-4 py-4 bg-[var(--color-surface-raised)] border-b border-[var(--color-border)]">
      <h3 class="m-0 mb-3 text-sm font-bold text-[var(--color-text-primary)]">Select Entities</h3>

      <div class="autocomplete-container mb-3">
        <label class="block text-sm font-semibold text-[var(--color-text-primary)] mb-1.5">From</label>
        <div class="relative flex items-center bg-[var(--color-background)] border border-[var(--color-border)] rounded-md px-2 py-2 transition-all duration-200 focus-within:border-[var(--color-primary)] focus-within:bg-[var(--color-surface)]">
          <Icon name="arrow-up-circle" :size="16" class="text-[var(--color-text-tertiary)] mr-2 flex-shrink-0" />
          <input
            v-model="searchSource"
            @focus="showSourceDropdown = true"
            type="text"
            placeholder="Search source entity..."
            class="flex-1 border-0 bg-transparent text-sm outline-none text-[var(--color-text-primary)] placeholder:text-[var(--color-text-tertiary)]"
          />
          <button
            v-if="sourceEntityId"
            @click="clearSource"
            class="bg-transparent border-0 p-1 cursor-pointer text-[var(--color-text-tertiary)] flex items-center rounded transition-all duration-200 hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-secondary)] flex-shrink-0"
          >
            <Icon name="x-mark" :size="14" />
          </button>
        </div>

        <div v-if="showSourceDropdown && filteredSourceEntities.length > 0" class="absolute top-full left-0 right-0 mt-1 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-md shadow-lg overflow-hidden z-[100] max-h-[300px] overflow-y-auto">
          <div
            v-for="entity in filteredSourceEntities"
            :key="entity.fqcn || entity.name"
            @click="selectSourceEntity(entity)"
            class="flex items-center gap-2 px-3 py-2 cursor-pointer transition-all duration-200 border-b border-[var(--color-border)] last:border-b-0 hover:bg-[var(--color-primary-light)]"
            :class="{ 'bg-[var(--color-primary-light)]': sourceEntityId === (entity.fqcn || entity.name) }"
          >
            <Icon name="table-cells" :size="14" class="text-[var(--color-text-tertiary)]" />
            <div class="flex flex-col gap-0.5 flex-1 min-w-0">
              <span class="text-sm font-semibold text-[var(--color-text-primary)] whitespace-nowrap overflow-hidden text-ellipsis">{{ entity.name }}</span>
              <span class="text-xs text-[var(--color-text-secondary)]">{{ entity.fields?.length || 0 }} fields</span>
            </div>
          </div>
        </div>
      </div>

      <div class="autocomplete-container mb-3">
        <label class="block text-sm font-semibold text-[var(--color-text-primary)] mb-1.5">To</label>
        <div class="relative flex items-center bg-[var(--color-background)] border border-[var(--color-border)] rounded-md px-2 py-2 transition-all duration-200 focus-within:border-[var(--color-primary)] focus-within:bg-[var(--color-surface)]">
          <Icon name="arrow-down-circle" :size="16" class="text-[var(--color-text-tertiary)] mr-2 flex-shrink-0" />
          <input
            v-model="searchTarget"
            @focus="showTargetDropdown = true"
            type="text"
            placeholder="Search target entity..."
            class="flex-1 border-0 bg-transparent text-sm outline-none text-[var(--color-text-primary)] placeholder:text-[var(--color-text-tertiary)]"
          />
          <button
            v-if="targetEntityId"
            @click="clearTarget"
            class="bg-transparent border-0 p-1 cursor-pointer text-[var(--color-text-tertiary)] flex items-center rounded transition-all duration-200 hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-secondary)] flex-shrink-0"
          >
            <Icon name="x-mark" :size="14" />
          </button>
        </div>

        <div v-if="showTargetDropdown && filteredTargetEntities.length > 0" class="absolute top-full left-0 right-0 mt-1 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-md shadow-lg overflow-hidden z-[100] max-h-[300px] overflow-y-auto">
          <div
            v-for="entity in filteredTargetEntities"
            :key="entity.fqcn || entity.name"
            @click="selectTargetEntity(entity)"
            class="flex items-center gap-2 px-3 py-2 cursor-pointer transition-all duration-200 border-b border-[var(--color-border)] last:border-b-0 hover:bg-[var(--color-primary-light)]"
            :class="{ 'bg-[var(--color-primary-light)]': targetEntityId === (entity.fqcn || entity.name) }"
          >
            <Icon name="table-cells" :size="14" class="text-[var(--color-text-tertiary)]" />
            <div class="flex flex-col gap-0.5 flex-1 min-w-0">
              <span class="text-sm font-semibold text-[var(--color-text-primary)] whitespace-nowrap overflow-hidden text-ellipsis">{{ entity.name }}</span>
              <span class="text-xs text-[var(--color-text-secondary)]">{{ entity.fields?.length || 0 }} fields</span>
            </div>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <label class="block text-sm font-semibold text-[var(--color-text-primary)] mb-1.5">
          Max depth
          <span class="font-normal text-[var(--color-text-secondary)]">({{ maxDepth }} hops)</span>
        </label>
        <input
          v-model.number="maxDepth"
          type="range"
          min="2"
          max="8"
          class="w-full h-1.5 rounded-full outline-none"
          style="accent-color: var(--color-primary)"
        />
      </div>

      <button
        @click="searchPaths"
        :disabled="!canSearch"
        class="w-full flex items-center justify-center gap-2 px-3 py-3 bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-primary-hover)] text-white border-0 rounded-lg text-sm font-semibold cursor-pointer transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed hover:shadow-md hover:-translate-y-px"
        :class="{ 'bg-[var(--color-text-tertiary)]': isSearching }"
      >
        <Icon :name="isSearching ? 'arrow-path' : 'magnifying-glass'" :size="18" :class="{ 'animate-spin': isSearching }" />
        <span>{{ isSearching ? 'Searching...' : 'Find Paths' }}</span>
      </button>
    </div>

    <div v-if="foundPaths.length > 0" class="px-4 py-4 bg-[var(--color-surface-raised)] border-b border-[var(--color-border)] flex-1 flex flex-col">
      <div class="flex items-center justify-between mb-3">
        <h3 class="m-0 text-sm font-bold text-[var(--color-text-primary)] flex items-center gap-2">
          Results
          <span class="inline-flex items-center px-2 py-px bg-[var(--color-primary-light)] text-[var(--color-primary)] text-xs font-bold rounded-full">{{ pathStats.count }} {{ pathStats.count === 1 ? 'path' : 'paths' }}</span>
        </h3>

        <div class="flex gap-1">
          <button
            v-if="selectedPaths.size > 0"
            @click="clearSelection"
            class="flex items-center justify-center w-7 h-7 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-md cursor-pointer transition-all duration-200 text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
            title="Clear selection"
          >
            <Icon name="x-mark" :size="14" />
          </button>
          <button
            @click="selectAllPaths"
            class="flex items-center justify-center w-7 h-7 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-md cursor-pointer transition-all duration-200 text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
            title="Select all"
          >
            <Icon name="check" :size="14" />
          </button>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-2 mb-3">
        <div class="flex flex-col items-center px-2 py-2 bg-[var(--color-background)] border border-[var(--color-border)] rounded-md">
          <span class="text-xs text-[var(--color-text-secondary)] mb-0.5">Shortest</span>
          <span class="text-sm font-bold text-[var(--color-text-primary)]">{{ pathStats.shortestLength }} {{ pathStats.shortestLength === 1 ? 'hop' : 'hops' }}</span>
        </div>
        <div class="flex flex-col items-center px-2 py-2 bg-[var(--color-background)] border border-[var(--color-border)] rounded-md">
          <span class="text-xs text-[var(--color-text-secondary)] mb-0.5">Longest</span>
          <span class="text-sm font-bold text-[var(--color-text-primary)]">{{ pathStats.longestLength }} {{ pathStats.longestLength === 1 ? 'hop' : 'hops' }}</span>
        </div>
        <div class="flex flex-col items-center px-2 py-2 bg-[var(--color-background)] border border-[var(--color-border)] rounded-md">
          <span class="text-xs text-[var(--color-text-secondary)] mb-0.5">Average</span>
          <span class="text-sm font-bold text-[var(--color-text-primary)]">{{ pathStats.averageLength }} hops</span>
        </div>
      </div>

      <div class="mb-3 max-h-[400px] overflow-y-auto">
        <div
          v-for="(path, index) in foundPaths"
          :key="index"
          class="flex items-center gap-2 px-2 py-2 mb-2 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-md transition-all duration-200"
          :class="{ 'bg-[var(--color-primary-light)] border-[var(--color-primary)]/30': selectedPaths.has(index) }"
        >
          <div class="flex-1 flex items-center gap-2 cursor-pointer min-w-0" @click="togglePathSelection(index)">
            <input
              type="checkbox"
              :checked="selectedPaths.has(index)"
              class="cursor-pointer w-4 h-4 flex-shrink-0"
              style="accent-color: var(--color-primary)"
            />
            <div class="flex-1 flex flex-col gap-1 min-w-0">
              <span class="inline-flex items-center px-1.5 py-px bg-[#3b82f6]/10 text-[#3b82f6] text-[10px] font-bold rounded-sm w-fit">{{ path.length }} {{ path.length === 1 ? 'hop' : 'hops' }}</span>
              <span class="text-xs text-[var(--color-text-primary)] font-medium whitespace-nowrap overflow-hidden text-ellipsis">{{ formatPath(path) }}</span>
            </div>
          </div>

          <button
            @click="showPath(index)"
            class="flex items-center justify-center w-8 h-8 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-md cursor-pointer transition-all duration-200 text-[var(--color-text-secondary)] flex-shrink-0 hover:bg-[var(--color-primary-light)] hover:border-[var(--color-primary)]/30 hover:text-[var(--color-primary)]"
            title="Show this path in graph"
          >
            <Icon name="eye" :size="14" />
          </button>
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <button
          @click="showAllPaths"
          class="flex items-center justify-center gap-2 px-3 py-2.5 bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-primary-hover)] text-white border-0 rounded-md text-sm font-semibold cursor-pointer transition-all duration-200 hover:shadow-md hover:-translate-y-px"
        >
          <Icon name="eye" :size="16" />
          <span>Show All Paths</span>
        </button>

        <button
          v-if="selectedPaths.size > 0"
          @click="showSelectedPaths"
          class="flex items-center justify-center gap-2 px-3 py-2.5 bg-[var(--color-surface-raised)] border-2 border-[var(--color-primary)] text-[var(--color-primary)] rounded-md text-sm font-semibold cursor-pointer transition-all duration-200 hover:bg-[var(--color-primary-light)]"
        >
          <Icon name="check-circle" :size="16" />
          <span>Show Selected ({{ selectedPaths.size }})</span>
        </button>
      </div>
    </div>

    <div v-else-if="foundPaths.length === 0 && sourceEntityId && targetEntityId && !isSearching" class="p-8 text-center text-[var(--color-text-secondary)]">
      <Icon name="exclamation-circle" :size="48" class="mx-auto mb-3 text-[var(--color-text-tertiary)]" />
      <h3 class="text-base font-bold text-[var(--color-text-primary)] m-0 mb-2">No paths found</h3>
      <p class="text-sm text-[var(--color-text-secondary)] m-0 mb-2 leading-relaxed">
        No connection found between
        <strong class="text-[var(--color-text-primary)] font-semibold">{{ sourceEntity?.name }}</strong> and
        <strong class="text-[var(--color-text-primary)] font-semibold">{{ targetEntity?.name }}</strong>
        within {{ maxDepth }} hops.
      </p>
      <p class="text-sm text-[var(--color-text-secondary)] italic m-0">Try increasing the max depth or selecting different entities.</p>
    </div>
  </div>
</template>
