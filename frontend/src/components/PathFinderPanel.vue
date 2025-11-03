<script setup>
import { ref, computed } from 'vue'
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

// Filtered entities for autocomplete
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

// Get selected entities
const sourceEntity = computed(() => {
  if (!sourceEntityId.value) return null
  return schemaStore.entities.find(e => (e.fqcn || e.name) === sourceEntityId.value)
})

const targetEntity = computed(() => {
  if (!targetEntityId.value) return null
  return schemaStore.entities.find(e => (e.fqcn || e.name) === targetEntityId.value)
})

// Path statistics
const pathStats = computed(() => {
  return getPathStats(foundPaths.value)
})

// Can search
const canSearch = computed(() => {
  return sourceEntityId.value && targetEntityId.value && !isSearching.value
})

// Select source entity
function selectSourceEntity(entity) {
  sourceEntityId.value = entity.fqcn || entity.name
  searchSource.value = entity.name
  showSourceDropdown.value = false
}

// Select target entity
function selectTargetEntity(entity) {
  targetEntityId.value = entity.fqcn || entity.name
  searchTarget.value = entity.name
  showTargetDropdown.value = false
}

// Clear source
function clearSource() {
  sourceEntityId.value = ''
  searchSource.value = ''
  foundPaths.value = []
  selectedPaths.value = new Set()
}

// Clear target
function clearTarget() {
  targetEntityId.value = ''
  searchTarget.value = ''
  foundPaths.value = []
  selectedPaths.value = new Set()
}

// Find paths
async function searchPaths() {
  if (!canSearch.value) return

  isSearching.value = true
  foundPaths.value = []
  selectedPaths.value = new Set()

  // Simulate async for better UX
  await new Promise(resolve => setTimeout(resolve, 100))

  const paths = findPaths(
    sourceEntity.value,
    targetEntity.value,
    schemaStore.entities,
    maxDepth.value
  )

  foundPaths.value = paths
  isSearching.value = false

  // Emit event with all paths
  if (paths.length > 0) {
    emit('paths-found', paths)
  }
}

// Toggle path selection
function togglePathSelection(index) {
  const newSet = new Set(selectedPaths.value)
  if (newSet.has(index)) {
    newSet.delete(index)
  } else {
    newSet.add(index)
  }
  selectedPaths.value = newSet
}

// Show selected paths
function showSelectedPaths() {
  if (selectedPaths.value.size === 0) return

  const paths = Array.from(selectedPaths.value).map(i => foundPaths.value[i])
  emit('show-path', paths)
}

// Show all paths
function showAllPaths() {
  if (foundPaths.value.length === 0) return
  emit('show-path', foundPaths.value)
}

// Show single path
function showPath(index) {
  emit('show-path', [foundPaths.value[index]])
}

// Select all paths
function selectAllPaths() {
  selectedPaths.value = new Set(foundPaths.value.map((_, i) => i))
}

// Clear selection
function clearSelection() {
  selectedPaths.value = new Set()
}

// Get relation type color
function getRelationTypeColor(type) {
  const colors = {
    1: '#10b981', // OneToOne - green
    2: '#3b82f6', // ManyToOne - blue
    4: '#f59e0b', // OneToMany - orange
    8: '#ef4444', // ManyToMany - red
  }
  return colors[type] || '#6b7280'
}

// Click outside to close dropdowns
function handleClickOutside(event) {
  if (!event.target.closest('.autocomplete-container')) {
    showSourceDropdown.value = false
    showTargetDropdown.value = false
  }
}

// Mount
import { onMounted, onUnmounted } from 'vue'
onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})
onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <div class="path-finder-panel">
    <!-- Header -->
    <div class="panel-header">
      <div class="header-title">
        <Icon name="map" :size="20" />
        <h2>Path Finder</h2>
      </div>
      <p class="header-subtitle">Find connection paths between two entities</p>
    </div>

    <!-- Entity Selection -->
    <div class="panel-section">
      <h3 class="section-title">Select Entities</h3>

      <!-- Source Entity -->
      <div class="autocomplete-container">
        <label class="input-label">From</label>
        <div class="autocomplete-input-wrapper">
          <Icon name="arrow-up-circle" :size="16" class="input-icon" />
          <input
            v-model="searchSource"
            @focus="showSourceDropdown = true"
            type="text"
            placeholder="Search source entity..."
            class="autocomplete-input"
          />
          <button
            v-if="sourceEntityId"
            @click="clearSource"
            class="clear-input-btn"
          >
            <Icon name="x-mark" :size="14" />
          </button>
        </div>

        <!-- Dropdown -->
        <div v-if="showSourceDropdown && filteredSourceEntities.length > 0" class="autocomplete-dropdown">
          <div
            v-for="entity in filteredSourceEntities"
            :key="entity.fqcn || entity.name"
            @click="selectSourceEntity(entity)"
            class="autocomplete-item"
            :class="{ selected: sourceEntityId === (entity.fqcn || entity.name) }"
          >
            <Icon name="table-cells" :size="14" />
            <div class="item-content">
              <span class="item-name">{{ entity.name }}</span>
              <span class="item-meta">{{ entity.fields?.length || 0 }} fields</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Target Entity -->
      <div class="autocomplete-container">
        <label class="input-label">To</label>
        <div class="autocomplete-input-wrapper">
          <Icon name="arrow-down-circle" :size="16" class="input-icon" />
          <input
            v-model="searchTarget"
            @focus="showTargetDropdown = true"
            type="text"
            placeholder="Search target entity..."
            class="autocomplete-input"
          />
          <button
            v-if="targetEntityId"
            @click="clearTarget"
            class="clear-input-btn"
          >
            <Icon name="x-mark" :size="14" />
          </button>
        </div>

        <!-- Dropdown -->
        <div v-if="showTargetDropdown && filteredTargetEntities.length > 0" class="autocomplete-dropdown">
          <div
            v-for="entity in filteredTargetEntities"
            :key="entity.fqcn || entity.name"
            @click="selectTargetEntity(entity)"
            class="autocomplete-item"
            :class="{ selected: targetEntityId === (entity.fqcn || entity.name) }"
          >
            <Icon name="table-cells" :size="14" />
            <div class="item-content">
              <span class="item-name">{{ entity.name }}</span>
              <span class="item-meta">{{ entity.fields?.length || 0 }} fields</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Max Depth -->
      <div class="depth-control">
        <label class="input-label">
          Max depth
          <span class="label-hint">({{ maxDepth }} hops)</span>
        </label>
        <input
          v-model.number="maxDepth"
          type="range"
          min="2"
          max="8"
          class="depth-slider"
        />
      </div>

      <!-- Search Button -->
      <button
        @click="searchPaths"
        :disabled="!canSearch"
        class="search-btn"
        :class="{ searching: isSearching }"
      >
        <Icon :name="isSearching ? 'arrow-path' : 'magnifying-glass'" :size="18" />
        <span>{{ isSearching ? 'Searching...' : 'Find Paths' }}</span>
      </button>
    </div>

    <!-- Results -->
    <div v-if="foundPaths.length > 0" class="panel-section results-section">
      <div class="section-header">
        <h3 class="section-title">
          Results
          <span class="result-count">{{ pathStats.count }} {{ pathStats.count === 1 ? 'path' : 'paths' }}</span>
        </h3>

        <div class="result-actions">
          <button
            v-if="selectedPaths.size > 0"
            @click="clearSelection"
            class="action-btn-small"
            title="Clear selection"
          >
            <Icon name="x-mark" :size="14" />
          </button>
          <button
            @click="selectAllPaths"
            class="action-btn-small"
            title="Select all"
          >
            <Icon name="check" :size="14" />
          </button>
        </div>
      </div>

      <!-- Stats -->
      <div class="path-stats">
        <div class="stat-item">
          <span class="stat-label">Shortest</span>
          <span class="stat-value">{{ pathStats.shortestLength }} {{ pathStats.shortestLength === 1 ? 'hop' : 'hops' }}</span>
        </div>
        <div class="stat-item">
          <span class="stat-label">Longest</span>
          <span class="stat-value">{{ pathStats.longestLength }} {{ pathStats.longestLength === 1 ? 'hop' : 'hops' }}</span>
        </div>
        <div class="stat-item">
          <span class="stat-label">Average</span>
          <span class="stat-value">{{ pathStats.averageLength }} hops</span>
        </div>
      </div>

      <!-- Paths List -->
      <div class="paths-list">
        <div
          v-for="(path, index) in foundPaths"
          :key="index"
          class="path-item"
          :class="{ selected: selectedPaths.has(index) }"
        >
          <div class="path-header" @click="togglePathSelection(index)">
            <input
              type="checkbox"
              :checked="selectedPaths.has(index)"
              class="path-checkbox"
            />
            <div class="path-info">
              <span class="path-length">{{ path.length }} {{ path.length === 1 ? 'hop' : 'hops' }}</span>
              <span class="path-formula">{{ formatPath(path) }}</span>
            </div>
          </div>

          <button
            @click="showPath(index)"
            class="show-path-btn"
            title="Show this path in graph"
          >
            <Icon name="eye" :size="14" />
          </button>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="actions-bar">
        <button
          @click="showAllPaths"
          class="action-btn primary"
        >
          <Icon name="eye" :size="16" />
          <span>Show All Paths</span>
        </button>

        <button
          v-if="selectedPaths.size > 0"
          @click="showSelectedPaths"
          class="action-btn secondary"
        >
          <Icon name="check-circle" :size="16" />
          <span>Show Selected ({{ selectedPaths.size }})</span>
        </button>
      </div>
    </div>

    <!-- No Results -->
    <div v-else-if="foundPaths.length === 0 && sourceEntityId && targetEntityId && !isSearching" class="empty-results">
      <Icon name="exclamation-circle" :size="48" class="empty-icon" />
      <h3>No paths found</h3>
      <p>
        No connection found between
        <strong>{{ sourceEntity?.name }}</strong> and
        <strong>{{ targetEntity?.name }}</strong>
        within {{ maxDepth }} hops.
      </p>
      <p class="hint">Try increasing the max depth or selecting different entities.</p>
    </div>
  </div>
</template>

<style scoped>
.path-finder-panel {
  height: 100%;
  display: flex;
  flex-direction: column;
  background: var(--color-gray-50);
  overflow-y: auto;
}

/* Header */
.panel-header {
  padding: var(--spacing-4);
  background: white;
  border-bottom: 1px solid var(--color-gray-200);
}

.header-title {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  margin-bottom: var(--spacing-1);
}

.header-title h2 {
  margin: 0;
  font-size: var(--text-lg);
  font-weight: 700;
  color: var(--color-gray-900);
}

.header-subtitle {
  margin: 0;
  font-size: var(--text-sm);
  color: var(--color-gray-600);
}

/* Section */
.panel-section {
  padding: var(--spacing-4);
  background: white;
  border-bottom: 1px solid var(--color-gray-200);
}

.section-title {
  margin: 0 0 var(--spacing-3) 0;
  font-size: var(--text-base);
  font-weight: 700;
  color: var(--color-gray-900);
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
}

.result-count {
  display: inline-flex;
  align-items: center;
  padding: 2px var(--spacing-2);
  background: var(--color-primary-100);
  color: var(--color-primary-700);
  font-size: var(--text-xs);
  font-weight: 700;
  border-radius: var(--radius-full);
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--spacing-3);
}

.result-actions {
  display: flex;
  gap: var(--spacing-1);
}

.action-btn-small {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  background: var(--color-gray-100);
  border: 1px solid var(--color-gray-300);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all var(--transition-base);
  color: var(--color-gray-600);
}

.action-btn-small:hover {
  background: var(--color-gray-200);
  color: var(--color-gray-800);
}

/* Autocomplete */
.autocomplete-container {
  position: relative;
  margin-bottom: var(--spacing-3);
}

.input-label {
  display: block;
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-gray-700);
  margin-bottom: var(--spacing-1-5);
}

.label-hint {
  font-weight: 400;
  color: var(--color-gray-500);
}

.autocomplete-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  background: var(--color-gray-50);
  border: 1px solid var(--color-gray-300);
  border-radius: var(--radius-md);
  padding: var(--spacing-2);
  transition: all var(--transition-base);
}

.autocomplete-input-wrapper:focus-within {
  border-color: var(--color-primary-500);
  background: white;
  box-shadow: 0 0 0 3px var(--color-primary-100);
}

.input-icon {
  color: var(--color-gray-400);
  margin-right: var(--spacing-2);
  flex-shrink: 0;
}

.autocomplete-input {
  flex: 1;
  border: none;
  background: transparent;
  font-size: var(--text-sm);
  outline: none;
  color: var(--color-gray-900);
}

.autocomplete-input::placeholder {
  color: var(--color-gray-400);
}

.clear-input-btn {
  background: none;
  border: none;
  padding: var(--spacing-1);
  cursor: pointer;
  color: var(--color-gray-400);
  display: flex;
  align-items: center;
  border-radius: var(--radius-sm);
  transition: all var(--transition-base);
  flex-shrink: 0;
}

.clear-input-btn:hover {
  background: var(--color-gray-200);
  color: var(--color-gray-600);
}

/* Dropdown */
.autocomplete-dropdown {
  position: absolute;
  top: calc(100% + var(--spacing-1));
  left: 0;
  right: 0;
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
  z-index: 100;
  max-height: 300px;
  overflow-y: auto;
}

.autocomplete-item {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-2) var(--spacing-3);
  cursor: pointer;
  transition: all var(--transition-base);
  border-bottom: 1px solid var(--color-gray-100);
}

.autocomplete-item:last-child {
  border-bottom: none;
}

.autocomplete-item:hover {
  background: var(--color-primary-50);
}

.autocomplete-item.selected {
  background: var(--color-primary-100);
}

.item-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}

.item-name {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-gray-900);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.item-meta {
  font-size: var(--text-xs);
  color: var(--color-gray-500);
}

/* Depth Control */
.depth-control {
  margin-bottom: var(--spacing-3);
}

.depth-slider {
  width: 100%;
  height: 6px;
  border-radius: var(--radius-full);
  outline: none;
  accent-color: var(--color-primary-500);
}

/* Search Button */
.search-btn {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-2);
  padding: var(--spacing-3);
  background: linear-gradient(135deg, var(--color-primary-500) 0%, var(--color-primary-600) 100%);
  color: white;
  border: none;
  border-radius: var(--radius-lg);
  font-size: var(--text-base);
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition-base);
}

.search-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}

.search-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.search-btn.searching {
  background: var(--color-gray-400);
}

/* Path Stats */
.path-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--spacing-2);
  margin-bottom: var(--spacing-3);
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: var(--spacing-2);
  background: var(--color-gray-50);
  border: 1px solid var(--color-gray-200);
  border-radius: var(--radius-md);
}

.stat-label {
  font-size: var(--text-xs);
  color: var(--color-gray-600);
  margin-bottom: 2px;
}

.stat-value {
  font-size: var(--text-sm);
  font-weight: 700;
  color: var(--color-gray-900);
}

/* Paths List */
.paths-list {
  margin-bottom: var(--spacing-3);
  max-height: 400px;
  overflow-y: auto;
}

.path-item {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-2);
  margin-bottom: var(--spacing-2);
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: var(--radius-md);
  transition: all var(--transition-base);
}

.path-item.selected {
  background: var(--color-primary-50);
  border-color: var(--color-primary-300);
}

.path-header {
  flex: 1;
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  cursor: pointer;
  min-width: 0;
}

.path-checkbox {
  cursor: pointer;
  width: 16px;
  height: 16px;
  accent-color: var(--color-primary-500);
  flex-shrink: 0;
}

.path-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.path-length {
  display: inline-flex;
  align-items: center;
  padding: 2px var(--spacing-1-5);
  background: var(--color-blue-100);
  color: var(--color-blue-700);
  font-size: 10px;
  font-weight: 700;
  border-radius: var(--radius-sm);
  width: fit-content;
}

.path-formula {
  font-size: var(--text-xs);
  color: var(--color-gray-700);
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.show-path-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: var(--color-gray-100);
  border: 1px solid var(--color-gray-300);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all var(--transition-base);
  color: var(--color-gray-600);
  flex-shrink: 0;
}

.show-path-btn:hover {
  background: var(--color-primary-100);
  border-color: var(--color-primary-300);
  color: var(--color-primary-600);
}

/* Action Bar */
.actions-bar {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2);
}

.action-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-2);
  padding: var(--spacing-2-5) var(--spacing-3);
  border: none;
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition-base);
}

.action-btn.primary {
  background: linear-gradient(135deg, var(--color-primary-500) 0%, var(--color-primary-600) 100%);
  color: white;
}

.action-btn.primary:hover {
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}

.action-btn.secondary {
  background: white;
  border: 2px solid var(--color-primary-500);
  color: var(--color-primary-600);
}

.action-btn.secondary:hover {
  background: var(--color-primary-50);
}

/* Empty Results */
.empty-results {
  padding: var(--spacing-8) var(--spacing-4);
  text-align: center;
  color: var(--color-gray-500);
}

.empty-icon {
  margin: 0 auto var(--spacing-3);
  color: var(--color-gray-300);
}

.empty-results h3 {
  font-size: var(--text-lg);
  font-weight: 700;
  color: var(--color-gray-700);
  margin: 0 0 var(--spacing-2) 0;
}

.empty-results p {
  font-size: var(--text-sm);
  color: var(--color-gray-600);
  margin: 0 0 var(--spacing-2) 0;
  line-height: 1.5;
}

.empty-results strong {
  color: var(--color-gray-900);
  font-weight: 600;
}

.hint {
  font-style: italic;
  color: var(--color-gray-500);
}

/* Scrollbar */
.path-finder-panel::-webkit-scrollbar,
.paths-list::-webkit-scrollbar,
.autocomplete-dropdown::-webkit-scrollbar {
  width: 8px;
}

.path-finder-panel::-webkit-scrollbar-track,
.paths-list::-webkit-scrollbar-track,
.autocomplete-dropdown::-webkit-scrollbar-track {
  background: var(--color-gray-100);
}

.path-finder-panel::-webkit-scrollbar-thumb,
.paths-list::-webkit-scrollbar-thumb,
.autocomplete-dropdown::-webkit-scrollbar-thumb {
  background: var(--color-gray-300);
  border-radius: var(--radius-full);
}

.path-finder-panel::-webkit-scrollbar-thumb:hover,
.paths-list::-webkit-scrollbar-thumb:hover,
.autocomplete-dropdown::-webkit-scrollbar-thumb:hover {
  background: var(--color-gray-400);
}

/* Results Section */
.results-section {
  flex: 1;
  display: flex;
  flex-direction: column;
}
</style>
